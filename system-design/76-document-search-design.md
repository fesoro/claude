# Document Search Design (Algolia-like)

Sənəd axtarış sistemi dizaynı. Algolia, Meilisearch, Typesense kimi məhsulların necə işlədiyini öyrənirik. Bu fayl `12-search-systems.md`-ı tamamlayır — orada ümumi axtarış sistemləri (Elasticsearch), burada isə instant search, UI-yönəlik "search-as-a-service" məhsulu var.

---

## Tələblər (Requirements)

### Funksional (Functional)
- Müştərinin sənədlərində (product catalog, help articles, dashboard) axtarış
- Instant search — hər hərfə nəticə (search-as-you-type)
- Typo tolerance — "laarvel" → "laravel" tapsın
- Faceted search — filtrlər (kateqoriya, qiymət, reytinq)
- Sinonimlər — "sneaker" = "trainer"
- Highlighting — uyğun hissələri <mark> içində qaytar
- Multi-tenant — 10k müştəri, hər birinin öz indexi
- Relevance tuning — hər tenant öz boost formulasını qura bilər
- Analytics — top queries, zero-result queries

### Qeyri-funksional (Non-functional)
- p99 latency < 50ms (UI blok olmasın)
- 100M query/gün (~1200 QPS ortalama, peak 10k QPS)
- Index size: hər tenant 100k-10M sənəd
- Indexing lag < 1 saniyə (eventual consistency)
- Availability 99.99%

### Algolia vs ümumi Elasticsearch (Algolia vs general Elasticsearch)
- Algolia **instant search** üçün optimize olunub — kiçik index per tenant, çox sürətli
- Elasticsearch **log/analytics** üçün də işləyir — daha böyük, daha yavaş, daha çox funksional
- Algolia **edge-based** — CDN kimi yaxın region-dən cavab verir
- Algolia **UI-first** — JS client, InstantSearch widget-lər hazırdır

---

## Miqyas (Scale)

```
Tenants          : 10,000
Docs per tenant  : 100k - 10M
Total docs       : ~10B
Queries/day      : 100M   (ortalama 1.2k QPS, peak 10k QPS)
p99 latency      : < 50ms
Indexing lag     : < 1s
```

---

## Yüksək səviyyəli arxitektura (High-level architecture)

```
+---------------+       +-----------------+       +--------------------+
|  Client (JS)  | ----> |   Edge / CDN    | ----> |  Query Router      |
|  InstantSearch|       |  (Cloudflare)   |       |  (tenant_id route) |
+---------------+       +-----------------+       +--------------------+
                                                          |
                                                          v
                                                  +-----------------+
                                                  |  Search Nodes   |
                                                  |  (inverted idx) |
                                                  +-----------------+
                                                          ^
+---------------+       +-----------------+       +--------------------+
|  Indexer API  | ----> |  Write Queue    | ----> |  Indexing Workers  |
|  (SaveObject) |       |  (Kafka)        |       |  (build index)     |
+---------------+       +-----------------+       +--------------------+
```

---

## Multi-tenancy (Multi-tenancy)

### Strategiya 1: Shared index (small tenants)
- Bir index-də bir neçə kiçik tenant
- Hər sənəddə `tenant_id` field var, filter kimi işlədilir
- Cost-effective — çoxlu kiçik müştəri üçün

### Strategiya 2: Dedicated cluster (big tenants)
- Böyük müştəri (məsələn 10M sənədlik Shopify store) üçün öz node
- Isolation — bir tenantın yükü başqasına təsir etmir
- SLA-ları dəstəkləmək asan

```php
// Laravel Scout — tenant-aware
class Product extends Model
{
    use Searchable;

    public function searchableAs(): string
    {
        return 'products_tenant_' . $this->tenant_id;
    }
}
```

---

## Index strukturu (Index structure)

### Inverted index (əsas struktur)
```
Term         | Postings list (doc_id, field, position, TF)
-------------+-------------------------------------------------
"laravel"    | [(1, title, 0, 2), (5, body, 12, 1), (9, body, 3, 1)]
"framework"  | [(1, title, 1, 1), (3, body, 5, 2)]
"php"        | [(1, body, 10, 3), (5, title, 0, 1), (7, body, 2, 1)]
```

### Forward index (snippet üçün)
```
doc_id | tokens
-------+---------------------------------------------------
1      | ["laravel", "framework", "php", "mvc", ...]
5      | ["php", "web", "application", "laravel", ...]
```

### Facet index (bitmap)
```
Facet           | Bitmap (doc_ids)
----------------+-------------------
category:php    | 11010110...
category:js     | 00101001...
price:0-50      | 10010010...
```

Bitmap-lər çox sürətlidir — AND/OR əməliyyatları bit operation-dur.

---

## Tokenization (Tokenization)

Pipeline:
1. **Lowercasing**: "Laravel" → "laravel"
2. **Unicode normalization**: NFKC forması
3. **Diacritic removal**: "café" → "cafe", "Məmməd" → "memmed"
4. **Stopword removal** (opsional): "the", "a", "is" (amma short query-lərdə saxla)
5. **Stemming** (opsional): "running" → "run" — EHTİYATLI ol, precision-ı aşağı sala bilər
6. **N-gram / edge-ngram** prefix match üçün: "laravel" → "l", "la", "lar", "lara", ...

```php
// Meilisearch settings
$index->updateSettings([
    'searchableAttributes' => ['title', 'description'],
    'stopWords' => ['the', 'a', 'an'],
    'synonyms' => ['laravel' => ['framework', 'php']],
]);
```

---

## Ranking (Ranking)

Algolia **tie-breaking** yanaşması — multi-criteria, hər biri növbəti criterion-u qırır:

1. **Typo count** — 0 typo sənədlər əvvəl, 1 typo sonra, 2 typo ən son
2. **Geo distance** — yaxın məkan əvvəl (əgər geo-search-dirsə)
3. **Words matched** — nə qədər çox söz uyğun gəlir
4. **Proximity** — sözlər bir-birinə nə qədər yaxın ("laravel php" — 1 söz aralı > 5 söz aralı)
5. **Attribute** — hansı field-də tapıldı? title > description > body
6. **Exact match** — dəqiq uyğunluq > prefix match
7. **Custom ranking** — popularity, price, date, rating (tenant təyin edir)

```json
{
  "ranking": [
    "typo",
    "geo",
    "words",
    "proximity",
    "attribute",
    "exact",
    "custom"
  ],
  "customRanking": [
    "desc(popularity)",
    "asc(price)"
  ]
}
```

BM25 də işlənə bilər amma Algolia daha sadə multi-criteria istifadə edir (izahı asan, tenant tune edə bilir).

---

## Typo tolerance (Typo tolerance)

- **Edit distance**: Damerau-Levenshtein (insert, delete, substitute, transpose)
- 1-2 typo-ya icazə (söz uzunluğuna görə — qısa sözlər üçün 0 typo)
- **BK-tree**: dictionary-də typo axtarışı O(log n)
- **Levenshtein automaton**: daha sürətli, state machine

```
Query: "laarvel"  (transpose: aa → ra)
Candidates within distance 1:
  - "laravel" (1 edit: transpose a↔r)
  - "larval"  (1 edit: delete e)

Ranked:
  1. Exact "laravel" matches
  2. 1-typo "laravel" matches
  3. 1-typo "larval" matches
```

---

## Prefix matching (Prefix matching)

Instant search üçün istifadəçi hələ yazarkən cavab lazımdır:

- **Edge n-grams**: "laravel" → ["l", "la", "lar", "lara", "larav", "larave", "laravel"]
- Index ölçüsü artır, amma prefix query O(1) olur
- Alternativ: trie structure

```
User types: "la"
Match: "laravel", "larry", "latex", ...
Ranking: typo=0, prefix match, custom ranking (popularity)
```

---

## Faceted search (Faceted search)

Filter + count:

```
Query: "phone"
Facets:
  brand:
    - Apple  (120)
    - Samsung (95)
    - Google (30)
  price:
    - 0-500   (80)
    - 500-1000 (140)
    - 1000+   (25)
```

Bitmap index ilə çox sürətli — AND əməliyyatı:
```
(query_matches) AND (brand:Apple) AND (price:500-1000)
= bitmap1 AND bitmap2 AND bitmap3
```

---

## Search-as-you-type (Search-as-you-type)

Arxitektura:
- **Prefix index** ayrıca saxlanır
- Typo-tolerant prefix match
- Debouncing client-side (50-100ms)
- Response caching edge-də (CDN)
- Keep-alive HTTP connection — round-trip azalır

Hədəf: p99 < 50ms (network + index query + rendering).

---

## Sinonimlər (Synonyms)

```json
{
  "synonyms": [
    { "type": "synonym", "synonyms": ["sneaker", "trainer", "running shoe"] },
    { "type": "oneWaySynonym", "input": "laptop", "synonyms": ["macbook", "thinkpad"] }
  ]
}
```

**Query expansion**: "sneaker" → search for ("sneaker" OR "trainer" OR "running shoe").

Two strategies:
- **Index-time**: synonym-ləri index-ə yaz (daha sürətli, amma dəyişmək çətin)
- **Query-time**: query-ni expand et (çevik, amma yavaş)

---

## Personalization (Personalization)

İstifadəçi tarixçəsinə əsasən boost:
- Əvvəl kliklənmiş kateqoriyalar
- Əvvəlki axtarış query-ləri
- User segment (premium, free, region)

```
base_score = textual_relevance * custom_ranking
final_score = base_score * (1 + personalization_boost)
```

Privacy qorumaq üçün — anonymized user_token.

---

## Relevance tuning (Relevance tuning)

Hər tenant öz index-ini tune edə bilər:
- **Searchable attributes**: title > description > tags (order matters)
- **Custom ranking**: popularity, rating, date DESC
- **Synonyms**: per-tenant
- **Stopwords**: dil-ə görə
- **Rules**: "if query='adidas', pin sponsored results"

---

## Indexing pipeline (Indexing pipeline)

```
Client SaveObject API
      |
      v
Ingest API (validation, rate limit)
      |
      v
Kafka (tenant_id partition)
      |
      v
Indexer workers
      |
      +--> Build inverted index delta
      +--> Update forward index
      +--> Update facet bitmaps
      |
      v
Atomic swap to read replicas
```

- **Incremental** — yalnız dəyişən sənədlər
- **Async** — müştəri gözləmir
- **Eventual consistency** — ~1s lag
- **Batch mode** — böyük import üçün `saveObjects([...])`

```php
// Laravel Scout — avtomatik index
$product->save();   // Scout $product-u queue-ya atır
$product->unsearchable();  // index-dən sil
Product::makeAllSearchable();  // bulk reindex
```

---

## Geo-search (Geo-search)

- Hər sənəddə `_geoloc: {lat, lng}`
- Ranking-də `geo` criterion — yaxın məsafə əvvəl
- Radius filter: `aroundLatLng=40.4,49.8&aroundRadius=5000`
- Cross-ref: `71-proximity-service-design.md`

---

## Snippets / highlighting (Snippets / highlighting)

Forward index-dən match olan fragment-i çıxar:

```json
{
  "title": "Laravel Framework Guide",
  "_highlightResult": {
    "title": {
      "value": "<mark>Laravel</mark> Framework Guide",
      "matchLevel": "full"
    }
  },
  "_snippetResult": {
    "description": {
      "value": "... a powerful <mark>Laravel</mark> tutorial for ..."
    }
  }
}
```

---

## Analytics (Analytics)

Track et:
- Top queries (populyar nə axtarırlar)
- Zero-result queries (nə tapılmır → sinonim əlavə et)
- Click position (nəticənin hansı sırası kliklənir)
- Conversion (klik → alış)

Bu data ranking-ə feed edilir:
```
If query X gets lots of clicks on doc Y → boost Y for query X
```

---

## Laravel nümunə (Laravel example)

```php
// 1. Install
composer require laravel/scout algolia/algoliasearch-client-php

// 2. Model
class Article extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'body'       => strip_tags($this->body),
            'author'     => $this->author->name,
            'category'   => $this->category->slug,
            'popularity' => $this->views_count,
            '_tags'      => $this->tags->pluck('name'),
        ];
    }

    public function searchableAs(): string
    {
        return 'articles_' . app()->environment();
    }
}

// 3. Search
$results = Article::search('laravel queue')
    ->where('category', 'php')
    ->take(20)
    ->get();

// 4. Index management
php artisan scout:import "App\Models\Article"
php artisan scout:flush "App\Models\Article"
```

Meilisearch driver:
```php
// config/scout.php
'driver' => env('SCOUT_DRIVER', 'meilisearch'),

'meilisearch' => [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key'  => env('MEILISEARCH_KEY'),
],
```

---

## Self-host vs managed (Self-host vs managed)

| Seçim | Güclü tərəf | Zəif tərəf |
|-------|-------------|------------|
| **Algolia** | SaaS, CDN edge, sabit p50<20ms, UI widget-lər | Bahalı ($$$), vendor lock-in |
| **Meilisearch** | Open source, sürətli, sadə | Self-host, scale etmək lazım |
| **Typesense** | Open source, geo, faceting güclü | Daha yeni, ekosistem kiçik |
| **Elasticsearch** | Çox funksional, analytics | Instant search üçün çətin, resurs-ac |

---

## Müsahibə sualları (Interview Q&A)

**S1: Inverted index nədir və niyə axtarışda istifadə olunur?**
C: Inverted index — term → postings list (o termi olan sənədlərin siyahısı) mapping-dir. Forward index-in əksi (doc → terms). Axtarışda O(1) term lookup + postings merge edərək uyğun sənədləri tapırıq. Full table scan-dan milyon dəfə sürətli.

**S2: Typo tolerance necə işləyir?**
C: Damerau-Levenshtein distance — 1-2 edit-ə icazə (insert, delete, substitute, transpose). BK-tree və ya Levenshtein automaton data structure-ları ilə dictionary-də sürətli typo lookup edirik. Ranking-də 0-typo sənədlər əvvəl, 1-typo sonra, 2-typo ən son.

**S3: Algolia və Elasticsearch arasında fərq?**
C: Algolia instant search üçün optimize olunub — kiçik index, edge CDN, p50<20ms, UI-first. Elasticsearch general-purpose — log analytics, aggregation, böyük data üçün güclü. Algolia managed SaaS, Elasticsearch self-host və ya Elastic Cloud. Algolia tie-breaking ranking, Elasticsearch BM25 default.

**S4: Multi-tenancy necə həyata keçirərdin?**
C: İki strategiya — kiçik tenant-lar üçün shared index (tenant_id filter ilə), böyük tenant-lar üçün dedicated cluster. Trade-off: cost vs isolation. Noisy neighbor problem-dan qaçmaq üçün rate limiting per tenant, resource quota.

**S5: Search-as-you-type p99<50ms necə saxlamaq?**
C: (a) Edge CDN — istifadəçiyə yaxın region (b) Prefix index ayrıca saxlanır (c) Keep-alive HTTP (d) Debounce client-side 50-100ms (e) Small index per tenant — memory-də saxlanır (f) No JOIN, no complex aggregation — yalnız bitmap AND/OR.

**S6: Facet count necə hesablayırsan performant?**
C: Bitmap index — hər facet value üçün bitmap (doc_id-lər). Query match bitmap-i ilə AND edirsən, sonra popcount (bit sayı) alırsan. SIMD instruction-larla çox sürətli. Roaring bitmaps sıxışdırma üçün.

**S7: Sinonim əlavə etsəm index yenidən qurulmalıdır?**
C: Asılıdır. Index-time synonym-də — bəli, çünki sinonim-lər token-lər yanında yazılıb. Query-time synonym-də — xeyr, yalnız query expand olunur. Algolia query-time edir — çevikdir. Trade-off: query-time bir az yavaşdır (query daha böyük olur).

**S8: Laravel-də milyon sənədi necə index edərdin?**
C: (a) `php artisan scout:import` batch-lə (default 500). (b) Queue işçiləri `queue:work` paralel. (c) Böyük data üçün chunked `chunkById`. (d) Rate limit Algolia-da var — 10k ops/s plan-dan asılı. (e) Sadəcə dəyişən rekord-ları yenilə — `Searchable` trait avtomatik. (f) Zero-downtime reindex — yeni index-ə yaz, atomic alias swap.

---

## Best practices (Best practices)

1. **Kiçik index** — yalnız axtarılan field-ləri index et, body-ni snippet üçün forward index-də saxla
2. **Searchable attributes sırası** önəmlidir — title > description > tags
3. **Custom ranking əlavə et** — textual relevance tək başına kifayət deyil (popularity, date, rating)
4. **Zero-result queries track et** — sinonim əlavə etmək üçün
5. **Typo tolerance qısa sözlər üçün 0** — "cat" üçün 1 typo çox fərqli söz qaytarır
6. **Stop word diqqətlə işlət** — "to be or not to be" query-sini tamamilə sıfır etmə
7. **Stemming ehtiyatla** — "running" → "run" OK, amma "university" → "univers" pis
8. **Multi-tenant isolation** — rate limit, resource quota, separate index
9. **Incremental indexing** — full reindex yalnız schema dəyişikliyində
10. **Atomic alias swap** — yeni index build et, sonra alias-ı swap et (zero-downtime)
11. **Analytics-i ranking-ə feed et** — click position → re-rank
12. **p99 ölç** — ortalama latency yalan deyir, tail latency önəmlidir
13. **Cache-i edge-də saxla** — populyar query-lər CDN-də 1-5 saniyə
14. **InstantSearch widget-ləri istifadə et** — frontend-i sıfırdan yazma
15. **Schema-nı versionla** — `articles_v1`, `articles_v2`, alias ilə switch

---

## Cross-references (Cross-references)

- `12-search-systems.md` — ümumi search system dizaynı (Elasticsearch-focused)
- `71-proximity-service-design.md` — geo-search (yaxınlıq)
- `case-studies/` — Shopify, Booking kimi axtarış istifadə edən şirkətlər
