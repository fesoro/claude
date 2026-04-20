# Typeahead / Autocomplete Design

## Nədir? (What is it?)

**Typeahead / autocomplete** — istifadəçi axtarış qutusuna simvol yazdıqca, real-time
olaraq ən populyar tamamlanma variantlarını göstərən sistemdir. Google, Amazon,
YouTube, Twitter (@mention) və IDE-lərdəki kod tamamlama — hamısı bu pattern-i
istifadə edir.

Sadə dillə: istifadəçi "doc" yazır, sistem "docker", "docker compose", "documentation"
kimi top-N suggestion qaytarır — 50-100ms içində, hər tuş basılışında.

## Tələblər (Requirements)

### Funksional

1. **Prefix matching**: user prefix yazır → top-N (adətən 10) təklif qaytar
2. **Ranking**: popularity, recency, personalization əsasında sırala
3. **Typo tolerance**: "dcoker" → "docker" (fuzzy match)
4. **Personalization** (optional): user history, location, dil
5. **Real-time updates**: trending queries sürətlə suggestion-lara düşməlidir

### Non-functional

- **Latency**: p99 < 100ms (hər keystroke üçün)
- **Throughput**: peak 1000-5000 QPS
- **Availability**: 99.9%+
- **Freshness**: daily full rebuild + hourly trending delta

### Capacity Estimation

```
DAU:             10M users
Searches/user:   ~10/day
Total queries:   100M/day  ≈ 1,200 QPS avg, 5,000 QPS peak
Keystrokes:      avg 6 chars × 100M = 600M prefix req/day
Unique queries:  10M-100M terms in catalog
Index size:      100M × 20B = ~2GB (compressed trie)
Raw trie memory: 100M × 10 chars × 50B ≈ 50GB (uncompressed)
```

## Naive Yanaşma (Why SQL LIKE fails)

```sql
SELECT query FROM search_log
WHERE query LIKE 'doc%'
ORDER BY popularity DESC LIMIT 10;
```

- `LIKE 'prefix%'` B-tree index istifadə edir, amma hər keystroke yeni sorğu
- 5000 QPS × hər query 50-200ms → database çökür
- Ranking üçün `ORDER BY` full sort tələb edir, typo tolerance yoxdur

Düzgün həll — **in-memory prefix index** (Trie və ya Redis sorted set).

## Trie (Prefix Tree)

Trie hər node-da bir simvol saxlayır; root-dan istənilən node-a path həmin prefix-dir.
Hər node-da **precomputed top-K** saxlanır — lookup `O(prefix_length)`.

```
                root
               / | \
              d  c  m
             / \
            o   a
           / \   \
          c   g   r
         / \
        k   :           ← ":" = end-of-word marker
       /
      e
     /
    r:   topK = [docker(9500), docker compose(7200),
                 dockerfile(5100), docker swarm(2800), ...]

Lookup "docke":  root → d → o → c → k → e  →  node("e").topK  (O(L))
```

- Hər node: `char`, `children[]`, `topK[]`, `isEndOfWord`
- TopK əvvəlcədən batch job ilə hesablanır; lookup O(L), write O(L) + top-K update
- **Compressed trie (radix/Patricia)**: tək uşağı olan zənciri bir edge-ə
  birləşdirir — "doc-ker" 6 node yerinə 1. ~5x yaddaş qənaəti.

## Weighted Ranking

Hər terminal node `(query, weight)` saxlayır. Weight formulası:

```
score = α · log(frequency)         # popularity
      + β · decay(recency)         # trending (time-decay)
      + γ · personalization_score  # user-specific
      + δ · ctr                    # click-through rate
```

Top-K hər node üçün pre-computed; child top-K-lər parent-də heap ilə merge olur.

## Redis Sorted Set Alternative

Trie yerinə hər prefix üçün sorted set:

```
ZADD typeahead:doc 9500 "docker"
ZADD typeahead:doc 7200 "docker compose"
ZREVRANGEBYSCORE typeahead:doc +inf -inf LIMIT 0 10
```

- Implementation sadə, Redis native
- Yaddaş çox — hər prefix üçün ayrı set
- Yalnız populyar / short prefix-lər (1-3 char) üçün; uzun prefix → trie

## Caching Strategiyası

```
┌─────────────────────────────────────────────┐
│ L1: Browser cache (LRU, 50 entry)           │  ← client-side
│ L2: CDN edge cache (short prefix, 60s TTL)  │  ← 1-2 char
│ L3: Redis hot prefix LRU (top 10K prefix)   │  ← warm
│ L4: In-memory trie (all prefixes)           │  ← typeahead service
└─────────────────────────────────────────────┘
```

- 1-2 char prefix → pre-baked (cəmi ~700 kombinasiya)
- Cache hit ratio hədəf: > 90%

## Typo Tolerance

1. **Edit distance 1-2** — Levenshtein automaton ilə trie traverse
2. **BK-tree** — metric space indexing, edit distance queries
3. **Soundex / Metaphone** — fonetik similarity
4. **Fallback on 0 results** — exact match olmasa fuzzy-yə keç

Real sistem: exact match → 0 nəticə isə fuzzy fallback (slow path).

## Data Pipeline və Sharding

```
User queries → Kafka → Spark batch → Ranking job → Trie builder
                                                        │ atomic swap
                                                        ▼
                                             Typeahead service (RAM)
```

- Query log → Kafka → daily Spark aggregation → weight calculation
- New trie build → atomic pointer swap (zero downtime)
- Trending (hourly): real-time counter → delta merge into live index

**Refresh cadence:** full rebuild daily 02:00 (Spark batch) + trending delta hourly
(in-memory merge) + hot trending 5-min (Redis overlay) + personalization real-time.

**Sharding:** `shard(prefix) = hash(prefix[0:2]) % num_shards`. Client ilk 2
char-a görə shard seçir; hot prefix-lər ("a", "s") replicated.

## API Design

```
GET /suggest?q=doc&n=10&user_id=42&locale=en
HTTP/1.1 200 OK
Cache-Control: public, max-age=60

{
  "prefix": "doc",
  "suggestions": [
    {"text": "docker",         "score": 9500, "source": "global"},
    {"text": "docker compose", "score": 7200, "source": "global"},
    {"text": "documentation",  "score": 6800, "source": "personal"}
  ],
  "latency_ms": 18
}
```

## PHP Trie Implementation

```php
class TrieNode
{
    /** @var array<string, TrieNode> */
    public array $children = [];
    public bool $isEnd = false;
    /** @var array<int, array{query: string, weight: float}> */
    public array $topK = [];
}

class Trie
{
    private TrieNode $root;

    public function __construct(private int $k = 10)
    {
        $this->root = new TrieNode();
    }

    public function insert(string $word, float $weight): void
    {
        $node = $this->root;
        $len = mb_strlen($word);

        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($word, $i, 1);
            $node->children[$ch] ??= new TrieNode();
            $node = $node->children[$ch];
            $this->updateTopK($node, $word, $weight);
        }
        $node->isEnd = true;
    }

    public function search(string $prefix): array
    {
        $node = $this->root;
        $len = mb_strlen($prefix);

        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($prefix, $i, 1);
            $node = $node->children[$ch] ?? null;
            if (!$node) return [];
        }
        return $node->topK;
    }

    private function updateTopK(TrieNode $node, string $word, float $weight): void
    {
        foreach ($node->topK as $idx => $item) {
            if ($item['query'] === $word) {
                $node->topK[$idx]['weight'] = $weight;
                $this->sortAndTrim($node);
                return;
            }
        }
        $node->topK[] = ['query' => $word, 'weight' => $weight];
        $this->sortAndTrim($node);
    }

    private function sortAndTrim(TrieNode $node): void
    {
        usort($node->topK, fn($a, $b) => $b['weight'] <=> $a['weight']);
        $node->topK = array_slice($node->topK, 0, $this->k);
    }
}
```

## Redis Sorted Set (Laravel)

```php
class TypeaheadRedisService
{
    public function __construct(private int $k = 10) {}

    public function addQuery(string $query, float $weight): void
    {
        $maxPrefixLen = min(mb_strlen($query), 4); // memory limit
        for ($i = 1; $i <= $maxPrefixLen; $i++) {
            $prefix = mb_strtolower(mb_substr($query, 0, $i));
            Redis::zadd("typeahead:{$prefix}", $weight, $query);
            Redis::zremrangebyrank("typeahead:{$prefix}", 0, -($this->k + 1));
        }
    }

    public function suggest(string $prefix, int $n = 10): array
    {
        $results = Redis::zrevrange("typeahead:" . mb_strtolower($prefix), 0, $n - 1, ['withscores' => true]);
        $out = [];
        foreach ($results as $query => $score) {
            $out[] = ['text' => $query, 'score' => (float) $score];
        }
        return $out;
    }
}

class SuggestController extends Controller
{
    public function __invoke(Request $request, TypeaheadRedisService $service): JsonResponse
    {
        $prefix = $request->string('q')->trim()->value();
        $n = min($request->integer('n', 10), 20);
        if (mb_strlen($prefix) < 1) return response()->json(['suggestions' => []]);

        return response()
            ->json(['prefix' => $prefix, 'suggestions' => $service->suggest($prefix, $n)])
            ->header('Cache-Control', 'public, max-age=60');
    }
}

class RebuildTypeaheadIndex extends Command
{
    protected $signature = 'typeahead:rebuild';

    public function handle(TypeaheadRedisService $service): void
    {
        DB::table('search_log')
            ->selectRaw('query, COUNT(*) as freq, MAX(created_at) as last_seen')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('query')->having('freq', '>=', 5)
            ->chunk(1000, function ($rows) use ($service) {
                foreach ($rows as $row) {
                    $boost = now()->diffInHours($row->last_seen) < 24 ? 1.5 : 1.0;
                    $service->addQuery($row->query, log($row->freq + 1) * $boost);
                }
            });
    }
}

// Schedule: daily full rebuild + hourly trending delta
Schedule::command('typeahead:rebuild')->dailyAt('02:00');
Schedule::command('typeahead:trending')->hourly();
```

## Personalization, Abuse, Scale Examples

**Personalization:** `final_score = 0.7 · global_score + 0.3 · user_history_score`.
User history Redis hash-da: `user:42:queries` → `{query: count}`.

**Abuse və privacy:** rate limit IP başına 100 req/min; bot detection (uniform
interval = bot); PII filter (email/phone/SSN pattern-li query-ləri index-ə salma);
user history 90 gündən sonra anonymize; profanity blacklist.

**Real scale:** Google (billions/day, ML-ranked, sub-100ms globally), Amazon
(500M+ catalog, personalized by purchase), Twitter @mention (user graph +
follower count), VS Code (symbol table + ML ranking, IntelliCode/Copilot).

## Müsahibə Sualları (Interview Q&A)

**S1: Niyə trie, hash map deyil?**
C: Hash map yalnız exact key match verir. Trie prefix-based lookup üçün
optimize olunub — `O(L)` traversal və her node-da pre-computed top-K. Hash map
ilə 100M key üzərində prefix filter `O(N)` olardı.

**S2: Top-K-nı hər node-da saxlamaq yaddaş israfı deyilmi?**
C: Space-time trade-off. Pre-computed top-K olmasa, lookup zamanı subtree-ni
DFS ilə tam gəzmək lazımdır — `O(N)` worst case. Pre-computed ilə `O(L)` garantee.
100M × K=10 × 32B = ~32GB əlavə, amma latency 10x yaxşılaşır.

**S3: Index nə qədər tez-tez yenilənməlidir?**
C: İki cadence: daily full rebuild (02:00 batch) + hourly trending delta.
Atomic pointer swap ilə zero-downtime. Real-time trending üçün Redis overlay
(son 1 saat populyar).

**S4: Redis sorted set vs in-memory trie — hansı?**
C: Scale asılıdır. Kiçik catalog (< 1M) üçün Redis sadədir və kifayətdir.
Böyük catalog (10M+) üçün trie yaddaş baxımından effektivdir — hər prefix üçün
ayrı set yerinə shared structure. Hybrid: short prefix (1-3 char) → Redis,
long prefix → trie service.

**S5: Typo tolerance necə əlavə edirsən?**
C: Exact prefix match əvvəl cəhd olunur. Əgər 0 nəticə, fuzzy fallback —
Levenshtein automaton ilə edit distance ≤ 2 variantları trie üzərində axtar.
BK-tree və ya symspell alqoritmi də mümkündür. Fuzzy slow path-dır.

**S6: Personalization necə tətbiq edilir?**
C: Global top-K + user-specific history weighted merge. User history Redis-də
`user:{id}:queries` hash-ında. Final score = `0.7 * global + 0.3 * user`.
Cold start üçün yalnız global. Location/language də feature kimi.

**S7: 1000 QPS-də 100ms p99 necə saxlayırsan?**
C: (1) In-memory trie — disk I/O yox. (2) Hot prefix-lər pre-cached.
(3) Short prefix pre-baked. (4) CDN edge 60s TTL. (5) Sharding.
(6) Connection pooling, HTTP/2 multiplexing. Real bottleneck adətən GC pauses.

**S8: Trending query-lər dərhal görünməlidir — necə?**
C: Batch pipeline slow (daily) olduğu üçün real-time layer: Kafka stream →
Flink / Redis counter → hourly trending top. Serve zamanı
`merge(trie_result, trending_overlay)` — trending-ə boost. Yeni event
(məs. "Olimpiya 2026") dəqiqələr içində görünür.

## Best Practices

1. **In-memory index** — Typeahead disk-dən oxumamalıdır, hər şey RAM-da
2. **Pre-computed top-K** — Runtime-da sort etmə, build time-da hazır olsun
3. **Atomic index swap** — Zero-downtime rebuild üçün pointer swap
4. **Short prefix pre-baking** — 1-2 char prefix-ləri tam cache et
5. **Debounce client-side** — 100-150ms debounce, hər keystroke-da request göndərmə
6. **Request cancellation** — Yeni keystroke gəldikdə köhnə request-i cancel et
7. **CDN caching** — Short TTL (60s) ilə CDN edge-də cache et
8. **Rate limit** — IP-per-min limit qoy, bot abuse önlə
9. **PII filter** — Email/phone/SSN pattern-li query-ləri index-ə salma
10. **Monitoring** — p50/p95/p99 latency, cache hit ratio, trending lag
11. **Fuzzy fallback** — Yalnız 0 nəticədə, default slow path-dır
12. **Personalization opt-in** — Privacy üçün istifadəçiyə seçim ver
