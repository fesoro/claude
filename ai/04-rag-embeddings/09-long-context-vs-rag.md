# Long Context vs RAG: 1M Token Window-da Nə Vaxt RAG-a Ehtiyac Yoxdur (Senior)

> **Oxucu kütləsi:** Senior backend developerlər və arxitektorlar — bir informasiya sistemi üçün "RAG qurum, yoxsa bütün məzmunu prompt-a yığım?" qərarını verməlidirlər.
> **Bu faylın qonşu fayllarla fərqi:**
> - `03-rag-architecture.md` — RAG pipeline-ın "necə" qurulması. Bu fayl "nə vaxt **qurulmamalı**" haqqındadır.
> - `04-chunking-strategies.md` — chunk-lama texnikaları. Bu fayl chunk-lamağa alternativ olan long-context yanaşmasını müqayisə edir.
> - `07-contextual-retrieval.md` — RAG-ı gücləndirən indexing texnikası. Bu fayl isə RAG-ın özünün alternativlərini araşdırır.
> - `05-query-transformation-hyde.md` — retrieval-ı gücləndirən sorğu texnikaları. Bu fayl retrieval-ın özünün lazım olub-olmadığını müzakirə edir.
> - `11-rag-evaluation-rerank.md` — RAG eval. Burada long-context vs RAG qiymətləndirmə framework-ü.

---

## Mündəricat

1. 2026-cı il reallığı: kontekst pəncərələri harda çatıb
2. "Needle in haystack" aldatmacası
3. Xərc riyaziyyatı: RAG vs long context
4. Latency reallığı: prefill və cache
5. Keyfiyyət reallığı: multi-hop deqradasiyası
6. Nə vaxt **long context qalib gəlir**
7. Nə vaxt **RAG qalib gəlir**
8. Hybrid pattern-lar: "RAG the haystack, long-context the needles"
9. Prompt caching — körpü texnologiyası
10. Long-context üçün chunk ölçüsü adaptasiyaları
11. Position-aware texnikalar (middle-of-context problemi)
12. Laravel implementation: unified interface
13. Miqrasiya ssenariləri
14. Qərar cədvəli və müsahibə xülasəsi

---

## 1. 2026-cı İl Reallığı: Kontekst Pəncərələri Harda Çatıb

2026-cı il aprel ayı etibarilə frontier model-lərin kontekst pəncərəsi:

| Model | Context window | Input qiymət (/ M token) | Cache read |
|-------|---------------|--------------------------|------------|
| Claude Opus 4.7 | 1M token | $15 | $1.50 |
| Claude Sonnet 4.7 | 1M token | $3 | $0.30 |
| Claude Haiku 4.5 | 200K token | $1 | $0.10 |
| Gemini 2.5 Pro | 2M token | $1.25 | $0.31 |
| Gemini 2.5 Flash | 1M token | $0.15 | $0.04 |
| GPT-5 | 400K token | $5 | $1.25 |
| GPT-5-mini | 400K token | $0.40 | $0.10 |

1M token ≈ 750,000 söz ≈ 2500 səhifə sənəd ≈ 50K sətir kod.

Bu rəqəmlər 2 il əvvəlkindən **50× böyükdür** (2024 əvvəli: 128K standard). Sual meydana çıxır: bütün şirkət biliyi artıq prompt-a sığırsa, niyə RAG infrastruktur-una sərmayə etmək?

**Qısa cavab**: Bəzi hallarda, doğrudan, RAG lazım deyil. Bəzi hallarda isə RAG getdikcə daha kritik olur. Seçim konkret use case-ə, xərcə, keyfiyyətə və arxitekturaya baxır.

---

## 2. "Needle in Haystack" Aldatmacası

### 2.1 NIAH nədir

Needle-in-Haystack (NIAH) eval: uzun kontekstin ortasına bir "iynə" (random fakt) yerləşdir, modeldən həmin faktı tap.

```
[50,000 tokens of random Wikipedia text]
...
"The best pizza in San Francisco is at Tony's Pizza Napoletana."
...
[50,000 more tokens of random text]

Question: "Where is the best pizza in San Francisco?"
```

Frontier model-lər bu testdə 99%+ uğurludur. Bu nəticələr təqdimatlarda göstərilir və guya "uzun kontekst işləyir" arqumentidir.

### 2.2 Niyə bu aldatmacadır

NIAH **tək-faktlı, sintetik** testdir. Real use case-lər bunlar deyil:

| Real task | NIAH-nın qaçırdığı |
|-----------|---------------------|
| Multi-hop reasoning | İki fakt tapmaq + əlaqələndirmək |
| Summary across sections | Konfliktli məlumatı birləşdirmək |
| Comparison | İki entity-nin xüsusiyyətlərini çıxarıb müqayisə |
| Implicit retrieval | Sualı doğru-dan tək bir cümləyə xəritələndirmək mümkün deyil |
| Temporal reasoning | "Ən son dəyişiklik nə oldu?" — tarixləri müqayisə |
| Aggregation | "Neçə sənəd X haqqındadır?" — sayma |

### 2.3 Real benchmark-lar

**RULER** (2024, NVIDIA) — multi-task long context eval. Nəticələr:

| Model | NIAH accuracy | Multi-hop accuracy | Aggregation |
|-------|---------------|---------------------|-------------|
| Claude 3.5 Sonnet (200K input) | 99% | 68% | 54% |
| GPT-4o (128K) | 98% | 61% | 48% |
| Gemini 1.5 Pro (1M) | 96% | 52% | 39% |

Multi-hop və aggregation task-larda dəqiqlik 50-70% səviyyəsinə düşür. Yəni real task-larda uzun context **tam etibarlı deyil**.

**LongBench** (Tsinghua) — 21 tapşırıq tipi üzrə eval. Oxşar pattern: NIAH-da yüksək, sintez və qarışıq tapşırıqlarda orta.

### 2.4 Praktik çıxarış

Long context "işləyir" cümləsini "modelim tək bir faktı prompt-ın hər yerindən tapa bilər" kimi oxu. Bu **lazımdır, amma kifayət deyil**. Kompleks task-larda long context daxilində "pseudo-retrieval" lazımdır, amma retrieval-ın RAG-dakı kimi eksplisit deyil, model-in attention-ına etibar edir.

---

## 3. Xərc Riyaziyyatı: RAG vs Long Context

### 3.1 Per-query xərc — sadə sorğu

**Ssenari**: 500K token sənəd bazasında "Apple Q3 2024 revenue" sorğusuna cavab ver.

**Yanaşma A: Long context (Claude Sonnet 4.7)**:
- Input: 500K × $3/M = **$1.50**
- Output: 500 token × $15/M = $0.008
- **Total: ~$1.51**

**Yanaşma B: Long context + prompt caching**:
- İlk çağırış (cache write): 500K × $3.75/M = $1.88
- Sonrakı çağırışlar (cache read): 500K × $0.30/M = **$0.15**
- Output: $0.008
- **Total (2-ci sorğudan başlayaraq): ~$0.16**

**Yanaşma C: RAG (5 chunk × 500 token retrieval)**:
- Embedding sorğu: ~$0.00002
- Vector search: ~$0 (öz DB)
- LLM input: 2500 token × $3/M = $0.0075
- Output: $0.008
- **Total: ~$0.02**

### 3.2 Xərc cədvəli — miqdar üzrə

100K sorğu/gün olan servisdə aylıq xərc:

| Yanaşma | Per query | 30 gün | Qeyd |
|---------|-----------|--------|------|
| RAG (5 chunks) | $0.02 | $60,000 | Embedding + infra xərci çıxılmayıb |
| Long context (cached) | $0.16 | $480,000 | 500K token document, 95% cache hit |
| Long context (no cache) | $1.51 | $4,530,000 | İlk çağırışdan sonra ağılsızdır |

**Yekun**: Yüksək volume və böyük korpus üçün RAG 8-75× ucuzdur.

### 3.3 Break-even analizi

Cache hit rate-ə görə break-even:

```
RAG cost / query ≈ $0.02
LC cached cost / query ≈ $0.16

Break-even: LC məqbul olur əgər daily query count × $0.16 <= RAG total 
(embedding + infra + LLM)
```

Real tənlik:
- Kiçik korpus (< 100K token): LC cache-li hər iki səviyyədə yaxın
- Orta korpus (100K-1M): RAG 2-10× ucuzdur
- Böyük korpus (> 1M): LC sığmır, RAG məcburidir

### 3.4 Gizli xərclər

RAG-da:
- Vector DB (pgvector hosted: ~$100-1000/ay, Pinecone: ~$70-2000/ay)
- Embedding xərci (təkrar indexing, new content)
- Rerank API (~$2/1K calls)
- Contextualization (~$1/M doc token, bax fayl 07)
- Development/maintenance

Long context-də:
- Cache management (5 dəqiqə vs 1 saat TTL)
- Large token counts → multi-region latency
- Model provider lock-in (1M context-i dəstəkləyən provider azdır)

---

## 4. Latency Reallığı: Prefill və Cache

### 4.1 Prefill nədir

LLM çağırışında iki mərhələ var:
1. **Prefill**: giriş token-lərini emal et (parallel-izable)
2. **Decode**: çıxış token-lərini ardıcıl generate et

Prefill 1M token üçün:
- Claude Opus 4.7 (no cache): **20-60 saniyə**
- Claude Sonnet 4.7 (no cache): 15-45 saniyə
- Gemini 2.5 Pro (no cache): 10-30 saniyə

Bu istifadəçinin UI-da gözlədiyi vaxtdır. Adi web app-də 30 saniyəlik "düşünürəm" göstərgəsi qəbuledilməzdir.

### 4.2 Cache effektini

Prompt caching prefill-i dramatik azaldır:
- Cached prefill (1M token): ~2-5 saniyə
- Cache miss: 20-60 saniyə

95% cache hit rate tətbiq etsən, average prefill ~3-5 saniyə olur. Yenə də RAG-dan yavaşdır (RAG ~200-500 ms).

### 4.3 RAG latency profili

```
Query
  │
  ▼
Embedding (20-50 ms)
  │
  ▼
Vector search (10-50 ms)
  │
  ▼
Rerank (opsional, 50-150 ms)
  │
  ▼
LLM prefill 2500 tokens (~100-200 ms)
  │
  ▼
Decode (~50 ms / 100 tokens)
  │
  ▼
Response

Total: 300-700 ms for full response
```

Long context üçün:
```
Query
  │
  ▼
LLM prefill 1M tokens (2-60 sec depending on cache)
  │
  ▼
Decode
  │
  ▼
Response

Total: 2.5-61 sec
```

### 4.4 Streaming töhfəsi

Her iki yanaşmada streaming mümkündür, amma:
- **RAG streaming**: ilk token 300 ms, sonrakı token-lər 20-50 ms
- **Long context streaming**: ilk token 2-60 sec (prefill qurtarmalıdır), sonrakı 20-50 ms

UX baxımından RAG-ın "time to first token" üstünlüyü kritikdir.

---

## 5. Keyfiyyət Reallığı: Multi-Hop Deqradasiyası

### 5.1 "Lost in the middle" fenomen

Liu et al. (2023, "Lost in the Middle") kəşf etdi: LLM-lər prompt-un **ortasında** yerləşdirilmiş məlumatı əvvəl və sondakından daha zəif istifadə edir.

```
Prompt structure:    [START]  [MIDDLE]  [END]
Retrieval accuracy:   85%      55%       80%
```

Bu effect uzun context-də kəskinləşir. 1M token prompt-da ortanın 600K token-i attention-da nisbətən zəifdir.

### 5.2 Multi-hop deqradasiyası nümunəsi

**Task**: "Apple-ın Q3 2024-də ən çox gəlir gətirən məhsulu, həmin məhsulun Q3 2023-dəki pay-ını müqayisə et və artım faizini hesabla."

**Long context approach**: 2024 və 2023 hesabatları (800K token) prompt-a yığ, sual ver.

Gözlənilən: model iki faktı (2024 top product, 2023 pay) tapır, müqayisə edir. Real: 60-70% accuracy — model bəzən 2023 rəqəmini unudur və ya səhv məhsul seçir.

**RAG approach**: Sub-query decomposition (bax fayl 05), iki ayrıca retrieval, nəticələri birləşdir. Daha dəqiq, çünki hər sub-task small context-də həll olunur.

### 5.3 Attention dilution

1M token-də model-in attention-ı bütün token-lər arasında bölünür. Prompt uzaldıqca:
- Tək cümlənin attention weight-i azalır
- Recency bias (son tokenlər daha çox təsir edir) güclənir
- "Middle" zona ignore olunur

RAG-da yalnız 5K-20K token context varıdır, attention dilution minimaldır.

### 5.4 Halüsinasiya aspektri

Long context-də model bəzən "kontekstdə olmayan amma məntiqi" məlumat uydurub cavaba qatır. Kontekst çox böyük olanda faithfulness yoxlaması çətinləşir.

RAG-da yalnız retrieved chunks prompt-dadırsa, halüsinasiya eval-i daha dəqiqdir.

---

## 6. Nə Vaxt Long Context Qalib Gəlir

Düzgün use case-lərdə long context RAG-dan sadə, ucuz və daha keyfiyyətli ola bilir.

### 6.1 Kiçik, stabil korpus

**Ssenari**: 200K token olan product specification. Günə 500 query gəlir.

Long context:
- Cache hit rate ~99% (corpus dəyişmir)
- Cache read: 200K × $0.30/M = **$0.06 per query**
- RAG infrastructure qurulmasına dəyməz

**Qərar**: Long context.

### 6.2 Ad-hoc analiz / research

**Ssenari**: Data analyst bir dəfəlik böyük log dump analiz etmək istəyir.

Long context:
- Bir dəfə yüklə, 20-30 sual ver
- Indexing pipeline-a vaxt sərf etmək mənasız

**Qərar**: Long context (caching ilə).

### 6.3 Kod review single repo

**Ssenari**: Claude Code CLI bir repository-nin bütün kodunu yükləyir, developer refactor təklifləri istəyir.

Long context:
- Repository 500K token altındadır
- Cache ilə ardıcıl suallar ucuzdur
- RAG-da chunk boundary funksiya/class bölür, keyfiyyəti pisləşdirir

**Qərar**: Long context. Bu Claude Code və Cursor IDE-nin default patternidır.

### 6.4 Qısa sessiyalı chat

**Ssenari**: Customer support — istifadəçi konkret bir product haqqında sual verir, şirkət manuali 80K token.

Long context:
- 80K token × cache read $0.30/M = $0.024 per query
- RAG qurma effort-una dəyməz

**Qərar**: Long context.

### 6.5 Prototyping və MVP

**Ssenari**: Startup yeni RAG məhsulu test edir, hələ infrastruktur yoxdur.

Long context:
- Çıxarışı ölç, customer demand-ı anla
- 3-6 ay sonra RAG-a miqrasiya et

**Qərar**: Long context MVP.

### 6.6 Q&A research paper

**Ssenari**: Araşdırma assistenti — tədqiqatçı bir 50-səhifəlik paper üzərinə suallar verir.

Long context:
- Paper 40K token, tək çağırışda oxunur
- RAG-da citations-ı düzgün yerləşdirmək daha çətindir

**Qərar**: Long context.

---

## 7. Nə Vaxt RAG Qalib Gəlir

### 7.1 Böyük, hərəkətli korpus

**Ssenari**: Konfluens wiki — 10M token, gündə 100 yeni səhifə əlavə olunur.

Long context: 10M context window yoxdur. Even 1M sığmaz.

**Qərar**: RAG məcburdir.

### 7.2 Freshness / dinamik data

**Ssenari**: News aggregator — hər saatda yeni məqalələr gəlir.

Long context: Bütün context hər dəfə yenilənməlidir, cache itirilir.

RAG: Yeni məqalələri index-ə əlavə et, cache strukturu dəyişmir.

**Qərar**: RAG.

### 7.3 Multi-tenant SaaS

**Ssenari**: 10,000 kiracı, hər birinin öz sənədləri.

Long context: Hər kiracının datasını ayrıca cache etmək yaddaş baxımından çətindir, cost-u multi-10×.

RAG: `tenant_id` filter + single index.

**Qərar**: RAG.

### 7.4 Audit və citations

**Ssenari**: Hüquqi assistant — cavabda dəqiq sənəd istinadı tələb olunur.

Long context: LLM "məlumat bu sənəddədir" deyir, amma dəqiq chunk-ı göstərmir. Halüsinasiya riski var.

RAG: Retrieved chunk metadata ilə birlikdə citation-ı proqramatik şəkildə əlavə edilir.

**Qərar**: RAG.

### 7.5 Scale (1000+ query/san)

**Ssenari**: Ticarət platforması, real-time recommendation.

Long context: Hər query ~2 saniyə latency, ticket spike-lərdə 10+ sec.

RAG: Retrieval 200 ms, generation 300 ms, ümumi 500 ms.

**Qərar**: RAG.

### 7.6 Tight cost budget

**Ssenari**: Free tier chat bot, hər istifadəçi xərci $0.01-dən aşağı olmalıdır.

Long context: $0.15+ per query (1M cached).

RAG: $0.01-0.03 per query.

**Qərar**: RAG.

### 7.7 Heterogeneous data types

**Ssenari**: Bilik bazası SQL DB, Wiki, PDF, Slack messages — bir neçə mənbə.

Long context: Hamısını bir prompt-a yığmaq qeyri-mümkündür (quotalar, formatlar).

RAG: Hər mənbə ayrı-ayrı indexlə, metadata ilə filter.

**Qərar**: RAG.

---

## 8. Hybrid Pattern-lar: "RAG the Haystack, Long-Context the Needles"

### 8.1 Əsas ideya

RAG-ın recall-ını long context-in synthesis gücü ilə birləşdir:

1. **RAG** ilə **200 chunk** (~100K token) candidate retrieval
2. **Long context** ilə bu 100K token-dəki suallara synthesis/reasoning

Bu, "RAG-ın geniş əhatəsi + LC-nin dərin anlayışı" kombinasiyasıdır.

### 8.2 Pipeline

```
query
  │
  ▼
Embedding
  │
  ▼
Vector search top-200  (100K tokens)
  │
  ▼
(Opsional) BM25 + RRF
  │
  ▼
LLM (Sonnet 4.7) — 100K prompt
  │
  ▼
Answer with synthesis across all 200 chunks
```

### 8.3 Xərc müqayisəsi

| Yanaşma | Input token | Per query |
|---------|-------------|-----------|
| Klassik RAG (5 chunks) | 2.5K | $0.01 |
| Hybrid (200 chunks) | 100K | $0.30 |
| Full long context (500K doc) | 500K | $1.50 |

Hybrid xərci 30× artır, amma 200 chunk retrieval-i ilə full-doc-un 90%+ keyfiyyətinə çatır.

### 8.4 Nə vaxt hybrid optimal

- Suallar **synthesis** tələb edir (tək chunk kifayət etmir)
- Korpus böyükdür (LC yanaşması özü mümkün deyil)
- Budget 10× RAG-dan böyükdür, amma full LC deyil

Praktik nümunə: hüquqi discovery — müraciətlər minlərlə sənədə bölünür, amma hər sorğuya ~100 sənəd uyğun gələ bilər. Hybrid burada idealdır.

### 8.5 İki-addımlı hybrid

Daha sofistikə variant:

```
step 1: RAG retrieves top-200 chunks (rough recall)
       │
       ▼
step 2: LLM (Haiku, cheap) filters 200 → 20 most relevant
       │
       ▼
step 3: LLM (Sonnet/Opus) answers from 20 chunks with high precision
```

Xərc: Haiku filter ~$0.02 + Sonnet answer ~$0.05 = $0.07. Full LC-dən 20× ucuz, klassik RAG-dan 7× bahalı, amma daha yüksək keyfiyyət.

---

## 9. Prompt Caching — Körpü Texnologiyası

### 9.1 Caching-in long context-i iqtisadi etməsi

Cache olmasa long context praktik olaraq mümkün deyil ($1.50/query). Cache ilə $0.15 — 10× uçuz, amma yenə də RAG-dan bahalı.

### 9.2 Cache-ın effektiv istifadəsi

**Bad pattern** — hər sessiyada tam sənəd:
```
Call 1: [fullDoc (1M tokens)] + "Question 1"
Call 2: [fullDoc (1M tokens)] + "Question 2"
Call 3: [fullDoc (1M tokens)] + "Question 3"

Cache hit rate: 100%, amma hər istifadəçi ayrı sessiyada → hər yeni istifadəçidə cache miss.
```

**Better pattern** — shared immutable cache:
```
System prompt + fullDoc cached with cache_control.
Bu cache bütün istifadəçilər arasında ortaq istifadə olunur
(eyni prefix bytes → eyni cache entry).

Call: [cached_prefix] + "<user_specific_question>"
```

### 9.3 Cache invalidation

Sənəd dəyişdikdə bütün cache itir. Böyük sənədlərdə bu böyük saving itkidir. Pattern:
- Sənədi immutable "versions" kimi sakla
- Yeni versiya yaradıldıqda yeni cache entry
- Köhnə versiya bir müddət cache-də qalır (backward compat)

### 9.4 Cache TTL strategy

| TTL | Qiymət | Use case |
|-----|--------|----------|
| 5 dəqiqə (default) | Ucuz | Active user session |
| 1 saat | 2× write cost | Shared corpus, multiple users |

Shared corpus üçün 1 saatlıq cache iqtisadi olur — write cost-u eyni gün ərzində minlərlə query arasında amortize olunur.

---

## 10. Long-Context Üçün Chunk Ölçüsü Adaptasiyaları

Əgər long-context yanaşmaya keçsən (və ya hybrid edirsənsə), **chunk ölçüləri artmalıdır**.

### 10.1 Klassik RAG chunk sizes

| Strateji | Chunk size |
|----------|-----------|
| Precision-focused | 256-512 token |
| Balanced | 512-1024 token |
| Context-heavy | 1024-2048 token |

### 10.2 Long-context uyğun chunk sizes

Long context yanaşmasında prompt-a çoxlu chunk sığır. Daha böyük chunk-lar:
- Daha az chunk count
- Daha böyük semantic vahid (full section, article)
- Azaldılmış chunk boundary artifact-ları

| Strateji | Long-context size |
|----------|-------------------|
| Section-based | 2K-5K token |
| Article-based | 5K-20K token |
| Full document (small) | 50K-200K token |

### 10.3 Hybrid — variable chunking

Retrieve etdiyin chunk ölçüsü final LLM-ə göndəriləndən fərqli ola bilər (parent-child pattern, bax fayl 04):

- Index: 512-token children for precision retrieval
- Retrieve: match on children
- Return to LLM: 4K-token parent chunks

Bu, long-context-ın böyük prompt qabiliyyətini parent chunks ilə istifadə edir, retrieval precision-ı isə child chunks saxlayır.

---

## 11. Position-Aware Texnikalar (Middle-of-Context Problemi)

### 11.1 Re-ordering strategy

Retrieval-dan gələn chunk-lar tipik olaraq relevansa görə sıralanmış olur. Long-context-ə göndərəndə:
- Ən relevant chunk-ları **başa və sona** qoy
- Orta relevant olanları ortada

```php
function lostInMiddleOrdering(array $chunks): array
{
    // Relevant score-a görə azalan sırada
    usort($chunks, fn($a, $b) => $b['score'] <=> $a['score']);

    $reordered = [];
    $left = 0;
    $right = count($chunks) - 1;
    $useLeft = true;

    // Zigzag: başa 1, sona 2, başa 3, sona 4, ...
    while ($left <= $right) {
        if ($useLeft) {
            $reordered[] = $chunks[$left++];
        } else {
            array_unshift($reordered, $chunks[$right--]);
        }
        $useLeft = !$useLeft;
    }

    return $reordered;
}
```

### 11.2 Explicit position marking

Prompt-da "MOST IMPORTANT" markalama kömək edir:

```
Review the following context carefully. Pay special attention to the sections 
marked [CRITICAL].

[CRITICAL] Section 1: ...
Section 2: ...
Section 3: ...
[CRITICAL] Section 4: ...

Question: ...
```

### 11.3 Chain-of-Verification post-processing

Kompleks suallarda Claude-dan iki addımlı cavab istə:
1. Drafted answer ver
2. Verify: hər iddia üçün kontekstdə source cümləsini qeyd et

Bu pattern halüsinasiya riskini azaldır, attention-ı bütün kontekstə yönəldir.

---

## 12. Laravel Implementation: Unified Interface

Real layihələrdə bəzən eyni endpoint həm RAG, həm long-context istifadə edə bilir. Unified interface lazımdır.

### 12.1 Abstract strategy

```php
<?php
// app/Services/RAG/AnswerStrategy.php

namespace App\Services\RAG;

interface AnswerStrategy
{
    public function answer(string $query, array $context = []): AnswerResult;
}

class AnswerResult
{
    public function __construct(
        public readonly string $answer,
        public readonly array $citations,
        public readonly string $strategy, // 'rag' | 'long-context' | 'hybrid'
        public readonly int $latencyMs,
        public readonly float $costUsd,
        public readonly array $metadata,
    ) {}
}
```

### 12.2 RAG strategy (klassik)

```php
<?php
// app/Services/RAG/Strategies/RagAnswerStrategy.php

namespace App\Services\RAG\Strategies;

use App\Services\RAG\AnswerStrategy;
use App\Services\RAG\AnswerResult;
use App\Services\RAG\HybridSearchService;
use App\Services\RAG\PromptAugmentationService;
use Illuminate\Support\Facades\Http;

class RagAnswerStrategy implements AnswerStrategy
{
    public function __construct(
        private HybridSearchService $search,
        private PromptAugmentationService $augment,
    ) {}

    public function answer(string $query, array $context = []): AnswerResult
    {
        $start = microtime(true);

        $candidates = $this->search->search($query, topK: 10);
        $prompt = $this->augment->buildPrompt($query, $candidates);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-7',
            'max_tokens' => 1024,
            'system' => $prompt['system'],
            'messages' => $prompt['messages'],
        ]);

        $data = $response->json();
        $answer = $data['content'][0]['text'];

        $inputTokens = $data['usage']['input_tokens'];
        $outputTokens = $data['usage']['output_tokens'];
        $cost = ($inputTokens * 3 + $outputTokens * 15) / 1_000_000;

        return new AnswerResult(
            answer: $answer,
            citations: $this->augment->extractCitations($answer, $candidates),
            strategy: 'rag',
            latencyMs: (int)((microtime(true) - $start) * 1000),
            costUsd: $cost,
            metadata: [
                'chunks_retrieved' => $candidates->count(),
                'input_tokens' => $inputTokens,
            ],
        );
    }
}
```

### 12.3 Long-context strategy

```php
<?php
// app/Services/RAG/Strategies/LongContextAnswerStrategy.php

namespace App\Services\RAG\Strategies;

use App\Models\KnowledgeDocument;
use App\Services\RAG\AnswerStrategy;
use App\Services\RAG\AnswerResult;
use Illuminate\Support\Facades\Http;

class LongContextAnswerStrategy implements AnswerStrategy
{
    public function answer(string $query, array $context = []): AnswerResult
    {
        $start = microtime(true);

        // Sənəd ID-lərini contextdən al (router tərəfindən verilir)
        $docIds = $context['document_ids'] ?? [];
        $documents = KnowledgeDocument::whereIn('id', $docIds)->get();

        $combinedText = $documents
            ->map(fn($d) => sprintf("<document id=\"%d\" title=\"%s\">\n%s\n</document>",
                $d->id, $d->title, $d->raw_content))
            ->implode("\n\n");

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-7',
            'max_tokens' => 1500,
            'system' => [
                [
                    'type' => 'text',
                    'text' => 'You are a helpful assistant. Answer based only on the provided documents. Cite with <document id="X"> when quoting.',
                ],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $combinedText,
                        // Tam sənədləri cache — sonrakı sorğular ucuzlaşır
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                    [
                        'type' => 'text',
                        'text' => "\n\nQuestion: {$query}",
                    ],
                ],
            ]],
        ]);

        $data = $response->json();
        $answer = $data['content'][0]['text'];

        // Cost hesablama — cache read daha ucuzdur
        $cacheRead = $data['usage']['cache_read_input_tokens'] ?? 0;
        $cacheWrite = $data['usage']['cache_creation_input_tokens'] ?? 0;
        $freshInput = $data['usage']['input_tokens'] ?? 0;
        $output = $data['usage']['output_tokens'];

        $cost = (
            $cacheRead * 0.30 +
            $cacheWrite * 3.75 +
            $freshInput * 3 +
            $output * 15
        ) / 1_000_000;

        return new AnswerResult(
            answer: $answer,
            citations: $this->extractLcCitations($answer, $documents),
            strategy: 'long-context',
            latencyMs: (int)((microtime(true) - $start) * 1000),
            costUsd: $cost,
            metadata: [
                'docs_in_context' => $documents->count(),
                'cache_read_tokens' => $cacheRead,
                'cache_write_tokens' => $cacheWrite,
                'fresh_input_tokens' => $freshInput,
            ],
        );
    }

    private function extractLcCitations(string $answer, $documents): array
    {
        preg_match_all('/<document id="(\d+)">/', $answer, $matches);
        $citedIds = array_unique(array_map('intval', $matches[1]));

        return $documents
            ->whereIn('id', $citedIds)
            ->map(fn($d) => [
                'document_id' => $d->id,
                'title' => $d->title,
            ])
            ->values()
            ->all();
    }
}
```

### 12.4 Strategy router

```php
<?php
// app/Services/RAG/StrategyRouter.php

namespace App\Services\RAG;

use App\Services\RAG\Strategies\RagAnswerStrategy;
use App\Services\RAG\Strategies\LongContextAnswerStrategy;
use App\Services\RAG\Strategies\HybridAnswerStrategy;

class StrategyRouter
{
    public function __construct(
        private RagAnswerStrategy $rag,
        private LongContextAnswerStrategy $longContext,
        private HybridAnswerStrategy $hybrid,
    ) {}

    public function select(string $query, array $context): AnswerStrategy
    {
        $corpusTokens = $context['corpus_tokens'] ?? 0;
        $isAdHoc = $context['is_ad_hoc'] ?? false;
        $budgetPerQuery = $context['budget_usd'] ?? 0.05;

        // Ad-hoc research, kiçik corpus → long context
        if ($isAdHoc && $corpusTokens < 500_000) {
            return $this->longContext;
        }

        // Kiçik + stabil + orta budget → long context (cached)
        if ($corpusTokens < 200_000 && $budgetPerQuery >= 0.15) {
            return $this->longContext;
        }

        // Kompleks + böyük budget → hybrid
        if (($context['query_complexity'] ?? 'simple') === 'complex'
            && $budgetPerQuery >= 0.30) {
            return $this->hybrid;
        }

        // Default: RAG
        return $this->rag;
    }
}
```

---

## 13. Miqrasiya Ssenariləri

### 13.1 RAG sisteminin long-context-ə sadələşdirilməsi

**Situasiya**: Sənin RAG sistemin qurulub, amma korpus 200K token-dir, eval-də keyfiyyət problem-li görünür.

**Miqrasiya addımları**:
1. Corpus-u tam LLM-ə göndər, eval dataset üzərində A/B test et
2. Long-context NDCG@5 və faithfulness RAG-dan yüksək olarsa miqrasiya et
3. Prompt caching aktivləşdir (1 saat TTL shared corpus üçün)
4. Retrieval infra-nı siliyin — sadə pipeline
5. Document update-ləri monitoring et — cache invalidation qaydaları

**Risklər**:
- Cost dərhal artır, gecikmiş counter-strike yoxdur
- Provider lock-in (1M context dəstəkləyən provider azdır)
- Latency istifadəçi-facing app-lərdə problem-li

### 13.2 Long-context prototype-dan RAG-a

**Situasiya**: MVP long-context ilə qurulub, indi 1M-dən böyük korpus olacaq.

**Miqrasiya addımları**:
1. Long-context-də yaratdığın eval set-i saxla (gold standard)
2. Incremental RAG pipeline qur (embedding, vector store, retrieval)
3. Eval-də RAG-ın long-context keyfiyyətinin 85%+-na çatmasına əmin ol
4. Shadow mode: production long-context, amma RAG-ı paralel çağır və müqayisə et
5. Full cut-over + monitoring

---

## 14. Qərar Cədvəli və Müsahibə Xülasəsi

### 14.1 Decision matrix

| Faktor | RAG | Long Context | Hybrid |
|--------|-----|--------------|--------|
| Corpus size < 200K token | Overkill | **Optimal** | Possible |
| Corpus 200K-1M | Praktik | **Şərtli** (cost) | **Optimal** |
| Corpus > 1M | **Məcburi** | Qeyri-mümkün | **Məcburi** |
| High freshness (hourly updates) | **Optimal** | Cache invalidation | RAG-based |
| Multi-tenant | **Məcburi** | Mümkün deyil | RAG-based |
| Citations / audit | **Optimal** | Halüsinasiya riski | RAG-based |
| Multi-hop reasoning | Orta | Zəif | **Yaxşı** |
| Single-doc Q&A | Overkill | **Optimal** | - |
| Ad-hoc analysis | Çətin | **Optimal** | - |
| Scale > 1000 q/s | **Optimal** | Çətin | Orta |
| Budget $0.01-0.05/q | **Optimal** | Yox | Orta |
| Budget > $0.15/q | Opsional | **Mümkün** | **Optimal** |
| Prototyping MVP | Over-engineer | **Optimal** | - |

### 14.2 Ən vacib anti-pattern-lar

1. **"NIAH yüksəkdir, deməli RAG lazım deyil"** — tək faktlı test, multi-hop-da model zəifdir
2. **Cache-siz long context** — $1.50/query, iqtisadi deyil
3. **Eval-siz miqrasiya** — öz dataset-ində nəticələri ölç
4. **Static prompt hər dəyişiklikdə** — cache itir, qeyri-effektivdir
5. **Hybrid-də cache-i ignore etmək** — retrieval-dan gələn top-200 chunk-ın da stabil prefix hissəsi cache-lənə bilər

### 14.3 Müsahibə xülasəsi

> 2026-cı il etibarilə frontier LLM-lərin 1M-2M token kontekst pəncərəsi var. Bu, "RAG hələ lazımdır?" sualını doğurur. Qısa cavab: **seçim use case-ə görə verilir**. Long context qazanır: kiçik stabil korpus (< 500K token), ad-hoc analiz, code review, single-document Q&A — xüsusilə prompt caching ilə. RAG qazanır: böyük korpus (> 1M), freshness, multi-tenant, citations, scale, tight budget. Xərc cədvəli: 1M context cached read $0.15/q vs RAG $0.01-0.03/q. Latency: prefill 1M token 2-60 saniyə (cache olmadan), RAG 300-700 ms. Keyfiyyət: NIAH benchmark aldatmacadır — multi-hop task-larda model 50-70% accuracy-ə düşür. Hybrid pattern: RAG 200 chunk retrieve edir, long context onları synthesis edir ("RAG the haystack, long-context the needles"). Prompt caching long-context-i iqtisadi edən körpü texnologiyasıdır. Chunk ölçüləri long-context yanaşmasında 2K-5K-a qədər böyüyə bilər. "Lost in the middle" effecti üçün retrieved chunk-ları start/end-ə qoy. Laravel-də Strategy pattern ilə unified interface qur, router use case metadata-sına əsasən seçim edir.

---

## 15. Əsas Çıxarışlar

- 1M kontekst pəncərəsi RAG-ı **öldürmür** — sadəcə use case seçimini dəyişdirir
- NIAH nəticələri aldadıcıdır — real task-larda multi-hop və synthesis-də model zəifdir
- Xərc riyaziyyatı: RAG 8-75× ucuzdur orta-böyük korpus üçün
- Latency: RAG 300-700 ms, long-context 2-60 saniyə (cache-siz)
- Prompt caching long-context-i iqtisadi edir, amma RAG-ı tam əvəz etmir
- Hybrid pattern — RAG+LC birləşməsi yüksək keyfiyyət/maliyyət balansı
- Chunk ölçüləri strategy-ə görə dəyişməlidir
- "Lost in the middle" və attention dilution long-context-in keyfiyyət riskləridir
- Unified Strategy interface Laravel-də miqrasiya və A/B test-i asanlaşdırır
- Qərar öz dataset-inin üzərində eval ilə verilməlidir — bir ölçü universal deyil

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Long Context vs RAG Benchmark

Eyni 20 sual üçün iki yanaşmanı test et: (a) tam sənədi context-ə yüklə (long context), (b) RAG ilə top-5 chunk əldə et. Hər yanaşma üçün keyfiyyəti (1-5), latency-ni, token xərcini ölç. Sənədin ölçüsünü artır (10K→50K→200K token) — nə vaxt RAG üstün olur?

### Tapşırıq 2: "Lost in the Middle" Eksperimenti

50K token-lik sənəddə cavab faktını 3 müxtəlif mövqedə yerləşdir: başında (0-5K), ortasında (22K-28K), sonunda (45K-50K). Hər 3 mövqe üçün Claude-un həmin faktı tapıb tapmamasını yoxla. Middle mövqedə accuracy aşağı düşürsə, RAG-ın bu problemi həll etdiyini sübut et.

### Tapşırıq 3: Hybrid Strategy Router

Sorğu gəldikdə sənəd ölçüsünə baxaraq strategiyanı avtomatik seçən `StrategyRouter` implement et: `< 50K token` → long context, `50K-500K` → hybrid (LC + RAG), `> 500K` → RAG only. 3 fərqli ölçüdə sənəd + 20 sorğu üzərindən router-ın seçimlərini yoxla.

---

## Əlaqəli Mövzular

- `03-rag-architecture.md` — RAG pipeline-ın əsas quruluşu
- `07-contextual-retrieval.md` — RAG keyfiyyətini artırmaqla LC-nin üstünlüyünü azaltmaq
- `10-agentic-rag.md` — Agentic loop ilə dinamik strategiya seçimi
- `../02-claude-api/07-extended-thinking.md` — Long context + extended thinking kombinasiyası
