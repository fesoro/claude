# AI Semantic Caching: Xərc və Latency Optimizasiyası (Senior)

> **Kim üçündür:** Production AI tətbiqləri quran senior developerlər. LLM xərclərini azaltmaq və latency-ni yaxşılaşdırmaq üçün caching strategiyaları.
>
> **Əhatə dairəsi:** Exact caching, semantic caching, Redis + pgvector implementasiyası, cache invalidation, Laravel inteqrasiyası, real cost impact hesablaması.

---

## 1. Niyə LLM Caching Vacibdir

```
Nümunə: Aylıq 100,000 AI sorğusu olan tətbiq

Exact duplicate rate: ~15% (eyni sual dəfələrlə soruşulur)
Semantic duplicate rate: ~35% (mənaca eyni, söz fərqli)

Yalnız exact cache: 100K × 0.15 = 15,000 sorğu cache-dən
Semantic cache:     100K × 0.35 = 35,000 sorğu cache-dən

Claude Sonnet qiyməti: $3/1M input + $15/1M output tokens
Orta sorğu: 500 input + 300 output tokens = $0.006/sorğu

Qənaət (semantic cache):
  35,000 × $0.006 = $210/ay qənaət
  İllik: $2,520 qənaət

Latency:
  AI cavab: 2-10s
  Cache hit: 5-20ms
  UX fərqi: dramatik
```

---

## 2. Cache Növləri

### 2.1 Exact Cache

Eyni prompt → eyni response. Hash-based.

```
Faydaları:
  - Çox sürətli (O(1) lookup)
  - Deterministic
  - Sıfır false positive

Çatışmazlıqları:
  - "Redis nədir?" vs "redis nedir?" → 2 fərqli cache entry
  - Kiçik dəyişiklik → cache miss
  - Hit rate aşağı
```

### 2.2 Semantic Cache

Mənaca oxşar promptlar → eyni response.

```
"Laravel-də Queue worker-i necə restart etmək olar?"
"laravel queue worker restart etmek üçün nə etmeli?"
"How to restart a Laravel queue worker?"

→ Hamısı eyni cached response-u alır

Faydaları:
  - Yüksək hit rate
  - Dil fərqi, yazı fərqi əhəmiyyətsiz

Çatışmazlıqları:
  - Embedding hesablaması lazımdır (overhead)
  - Threshold tuning tələb edir
  - Mümkün false positive (semantically close amma fərqli sual)
```

### 2.3 Prompt Caching (Provider-side)

Anthropic, OpenAI-nın öz caching sistemi — eyni prefix-li promptlar üçün 90% endirim. Bu semantic caching ilə qarışdırılmamalıdır.

```
Prompt caching: "Bu 5000 token sistem promptu hər dəfə təkrar hesablanmasın"
Semantic cache: "Eyni mənalı istifadəçi sorğusuna cavabı DB-dən qaytar"
```

---

## 3. Arxitektura

```
İstifadəçi sorğusu
       │
       ▼
[Exact Cache Check]  ──hit──→  Cached response (5ms)
       │ miss
       ▼
[Embedding yaratma]  ←── text → vector (20ms)
       │
       ▼
[Semantic Similarity Search]  ──hit──→  Cached response (25ms)
  (pgvector / Redis VSS)
       │ miss
       ▼
[LLM API Call]  (2-10s, $0.006)
       │
       ▼
[Response + embedding → Cache-ə yaz]
       │
       ▼
İstifadəçiyə cavab
```

---

## 4. Redis Exact Cache

```php
<?php
// app/Services/Cache/ExactPromptCache.php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Redis;

class ExactPromptCache
{
    private const DEFAULT_TTL = 3600; // 1 saat

    public function get(string $prompt, array $params = []): ?string
    {
        $key = $this->buildKey($prompt, $params);
        $cached = Redis::get($key);

        if ($cached) {
            Redis::incr("cache_stats:exact:hits");
            return $cached;
        }

        Redis::incr("cache_stats:exact:misses");
        return null;
    }

    public function set(
        string $prompt,
        string $response,
        array  $params = [],
        int    $ttl    = self::DEFAULT_TTL,
    ): void {
        $key = $this->buildKey($prompt, $params);
        Redis::setex($key, $ttl, $response);
    }

    private function buildKey(string $prompt, array $params): string
    {
        // Model, temperature də hash-ə daxil edilir
        // Eyni prompt amma fərqli model → fərqli cache
        $content = json_encode([
            'prompt'      => trim(strtolower($prompt)),
            'model'       => $params['model'] ?? 'default',
            'temperature' => $params['temperature'] ?? 0.0,
        ]);

        return 'ai:exact:' . hash('sha256', $content);
    }
}
```

---

## 5. Semantic Cache: pgvector ilə

### 5.1 Database Schema

```sql
-- Database migration
CREATE TABLE ai_cache_entries (
    id          BIGSERIAL PRIMARY KEY,
    prompt_hash VARCHAR(64) NOT NULL UNIQUE,  -- exact lookup
    prompt      TEXT NOT NULL,
    response    TEXT NOT NULL,
    embedding   vector(1536) NOT NULL,        -- OpenAI/Cohere embedding
    model       VARCHAR(100) NOT NULL,
    metadata    JSONB DEFAULT '{}',
    hit_count   INT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    expires_at  TIMESTAMPTZ NOT NULL,
    
    INDEX (prompt_hash),
    INDEX (expires_at)
);

-- HNSW semantic search üçün
CREATE INDEX ai_cache_embedding_idx
ON ai_cache_entries USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);
```

### 5.2 Semantic Cache Service

```php
<?php
// app/Services/Cache/SemanticCache.php

namespace App\Services\Cache;

use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\DB;

class SemanticCache
{
    private const SIMILARITY_THRESHOLD = 0.92; // 0.92+ → eyni mənada
    private const DEFAULT_TTL_HOURS    = 24;

    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Semantik oxşar cached cavab tap.
     * Threshold-dan yuxarı cosine similarity → cache hit.
     */
    public function get(string $prompt, string $model): ?string
    {
        // 1. Exact check (sürətli)
        $exactKey  = $this->promptHash($prompt, $model);
        $exactHit  = DB::table('ai_cache_entries')
            ->where('prompt_hash', $exactKey)
            ->where('expires_at', '>', now())
            ->value('response');

        if ($exactHit) {
            $this->incrementHitCount($exactKey);
            return $exactHit;
        }

        // 2. Semantic search (embedding + pgvector)
        $queryEmbedding = $this->embeddings->embed($prompt);
        $vectorStr      = $this->formatVector($queryEmbedding);

        $result = DB::selectOne(
            <<<SQL
            SELECT id, response, 1 - (embedding <=> :vector::vector) AS similarity
            FROM ai_cache_entries
            WHERE model       = :model
              AND expires_at  > NOW()
              AND 1 - (embedding <=> :vector::vector) >= :threshold
            ORDER BY embedding <=> :vector::vector
            LIMIT 1
            SQL,
            [
                'vector'    => $vectorStr,
                'model'     => $model,
                'threshold' => self::SIMILARITY_THRESHOLD,
            ],
        );

        if ($result) {
            DB::table('ai_cache_entries')
                ->where('id', $result->id)
                ->increment('hit_count');

            return $result->response;
        }

        return null;
    }

    public function set(
        string $prompt,
        string $response,
        string $model,
        array  $metadata = [],
        int    $ttlHours = self::DEFAULT_TTL_HOURS,
    ): void {
        $embedding = $this->embeddings->embed($prompt);

        DB::table('ai_cache_entries')->upsert(
            [
                'prompt_hash' => $this->promptHash($prompt, $model),
                'prompt'      => $prompt,
                'response'    => $response,
                'embedding'   => $this->formatVector($embedding),
                'model'       => $model,
                'metadata'    => json_encode($metadata),
                'expires_at'  => now()->addHours($ttlHours),
                'hit_count'   => 0,
                'created_at'  => now(),
            ],
            ['prompt_hash'],
            ['response', 'embedding', 'expires_at', 'metadata'],
        );
    }

    private function promptHash(string $prompt, string $model): string
    {
        return hash('sha256', strtolower(trim($prompt)) . '|' . $model);
    }

    private function formatVector(array $embedding): string
    {
        return '[' . implode(',', $embedding) . ']';
    }

    private function incrementHitCount(string $hash): void
    {
        DB::table('ai_cache_entries')
            ->where('prompt_hash', $hash)
            ->increment('hit_count');
    }
}
```

### 5.3 Embedding Service

```php
<?php
// app/Services/AI/EmbeddingService.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    // Cohere embed-v3 (multilingual, 1024 dim) — daha sürətli, ucuz
    // OpenAI text-embedding-3-small (1536 dim) — daha tanınmış
    private const MODEL    = 'text-embedding-3-small';
    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    public function embed(string $text): array
    {
        // Embedding-i cache-lə — eyni text üçün yenidən hesablamaq lazım deyil
        $cacheKey = 'embedding:' . hash('sha256', $text);

        return Cache::remember($cacheKey, 86400, function () use ($text) {
            $response = Http::withToken(config('services.openai.key'))
                ->post(self::ENDPOINT, [
                    'model'           => self::MODEL,
                    'input'           => $text,
                    'encoding_format' => 'float',
                ])
                ->throw()
                ->json('data.0.embedding');

            return $response;
        });
    }

    public function embedBatch(array $texts): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->post(self::ENDPOINT, [
                'model'           => self::MODEL,
                'input'           => $texts,
                'encoding_format' => 'float',
            ])
            ->throw()
            ->json('data');

        return collect($response)
            ->sortBy('index')
            ->pluck('embedding')
            ->toArray();
    }
}
```

---

## 6. Tam Cache Pipeline Laravel-də

```php
<?php
// app/Services/AI/CachedAIService.php

namespace App\Services\AI;

use App\Services\Cache\ExactPromptCache;
use App\Services\Cache\SemanticCache;

class CachedAIService
{
    public function __construct(
        private readonly ClaudeService  $claude,
        private readonly ExactPromptCache $exactCache,
        private readonly SemanticCache  $semanticCache,
    ) {}

    public function chat(
        string $prompt,
        string $model       = 'claude-haiku-4-5',
        float  $temperature = 0.0,
        bool   $bypassCache = false,
    ): array {
        // Cache bypass (admin panel, debug)
        if ($bypassCache) {
            return $this->fetchFromLLM($prompt, $model, $temperature);
        }

        // 1. Exact cache (deterministik, sürətli)
        $exactResult = $this->exactCache->get($prompt, compact('model', 'temperature'));
        if ($exactResult) {
            return ['response' => $exactResult, 'source' => 'exact_cache'];
        }

        // 2. Semantic cache (embedding-based, yavaş amma yüksək hit rate)
        if ($temperature <= 0.1) {  // Yalnız deterministik sorğular üçün
            $semanticResult = $this->semanticCache->get($prompt, $model);
            if ($semanticResult) {
                return ['response' => $semanticResult, 'source' => 'semantic_cache'];
            }
        }

        // 3. LLM çağırışı
        return $this->fetchFromLLM($prompt, $model, $temperature);
    }

    private function fetchFromLLM(string $prompt, string $model, float $temperature): array
    {
        $response = $this->claude->messages(
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $model,
            temperature: $temperature,
        );

        // Cache-ə yaz (async — user gözləməsin)
        dispatch(function () use ($prompt, $response, $model, $temperature) {
            $this->exactCache->set($prompt, $response, compact('model', 'temperature'));

            if ($temperature <= 0.1) {
                $this->semanticCache->set($prompt, $response, $model);
            }
        })->afterResponse();

        return ['response' => $response, 'source' => 'llm'];
    }
}
```

---

## 7. Cache Invalidation

### 7.1 TTL Strategiyaları

```php
// Mövzuya görə fərqli TTL
$ttlByTopic = match(true) {
    str_contains($prompt, 'Laravel versiyon') => 7,      // 7 gün — nadir dəyişir
    str_contains($prompt, 'qiymət')           => 1,      // 1 gün — tez dəyişir
    str_contains($prompt, 'cari')             => 0,      // Cache etmə!
    str_contains($prompt, 'bu gün')           => 0,      // Cache etmə!
    default                                   => 24,     // 24 saat standart
};
```

### 7.2 Manual Invalidation

```php
<?php
// app/Console/Commands/InvalidateCacheByTopic.php

class InvalidateCacheByTopic extends Command
{
    protected $signature = 'cache:ai:invalidate {topic}';

    public function handle(): void
    {
        $topic = $this->argument('topic');

        // Mövzu ilə əlaqəli bütün cache entry-ləri sil
        $deleted = DB::table('ai_cache_entries')
            ->whereRaw('LOWER(prompt) LIKE ?', ['%' . strtolower($topic) . '%'])
            ->delete();

        $this->info("Deleted {$deleted} cache entries related to '{$topic}'");
    }
}
```

### 7.3 Hansı Sorğuları Cache Etmə

```php
private function shouldCache(string $prompt, float $temperature): bool
{
    // Yüksək temperature → qeyri-deterministik, cache mənasız
    if ($temperature > 0.3) {
        return false;
    }

    // Real-time data sorğuları
    $nonCacheableKeywords = [
        'bu gün', 'indi', 'cari', 'son xəbər', 'hazırda',
        'today', 'now', 'current', 'latest', 'right now',
    ];

    foreach ($nonCacheableKeywords as $keyword) {
        if (str_contains(strtolower($prompt), $keyword)) {
            return false;
        }
    }

    return true;
}
```

---

## 8. Metrics və Monitoring

```php
<?php
// app/Services/Cache/CacheMetrics.php

class CacheMetrics
{
    public function getStats(): array
    {
        $exactHits   = Redis::get('cache_stats:exact:hits')   ?? 0;
        $exactMisses = Redis::get('cache_stats:exact:misses') ?? 0;
        
        $semanticHits = DB::table('ai_cache_entries')
            ->sum('hit_count');
        
        $totalCacheEntries = DB::table('ai_cache_entries')
            ->where('expires_at', '>', now())
            ->count();

        $exactTotal = $exactHits + $exactMisses;

        return [
            'exact_hit_rate'    => $exactTotal > 0 ? $exactHits / $exactTotal : 0,
            'semantic_hits'     => $semanticHits,
            'total_entries'     => $totalCacheEntries,
            'estimated_savings' => $this->estimateSavings($exactHits, $semanticHits),
        ];
    }

    private function estimateSavings(int $exactHits, int $semanticHits): array
    {
        $avgCostPerRequest = 0.006; // $0.006 per request
        $totalHits         = $exactHits + $semanticHits;
        $savedUSD          = $totalHits * $avgCostPerRequest;

        return [
            'saved_usd'  => round($savedUSD, 2),
            'saved_requests' => $totalHits,
        ];
    }
}
```

---

## 9. Threshold Seçimi

Semantic cache-in ən kritik parametri: similarity threshold.

```
Threshold çox yüksək (>0.98):
  → Hit rate aşağı düşür
  → Yalnız demək olar ki, eyni suallar cache-dən keçir
  → Semantic cache-in faydası azalır

Threshold çox aşağı (<0.85):
  → False positive yaranır
  → "Redis performance" vs "Redis security" → eyni cavab ✗
  → İstifadəçi yanlış cavab alır

Tövsiyə edilən: 0.90-0.95 aralığı
  → Task-specific calibration lazımdır
  → A/B test edin
```

```php
// Threshold calibration
// Test pair-ləri ilə optimal threshold tap
$testPairs = [
    ['Redis nədir?', 'Redis haqqında məlumat ver.', true],   // should match
    ['Redis nədir?', 'PostgreSQL nədir?',            false], // should NOT match
    ['Queue worker restart', 'Horizon restart',      true],  // should match
    ['Cost azaltmaq', 'Xərcləri optimize et',       true],  // should match
    ['PHP 8 enum', 'Java enum',                      false], // should NOT match
];

// Hər pair üçün similarity hesabla → threshold seç
```

---

## 10. Anti-Pattern-lər

### Hər Şeyi Cache Etmək

```php
// YANLIŞ: Temperature 1.0 olan yaradıcı cavabı cache etmək
$this->cache->set($creativityPrompt, $response, temperature: 1.0);
// → İstifadəçilər hər dəfə eyni "yaradıcı" cavab alır

// DOĞRU: Yalnız deterministik sorğuları cache et
if ($temperature <= 0.3) {
    $this->cache->set(...);
}
```

### Threshold-u Test Etmədən Seçmək

```
Production-da threshold test edin:
  Shadow mode: cache-i log edin amma response-u override etməyin
  Threshold oxşarlığı: threshold X ilə log false positive rate-i
  Sonra threshold-u yavaş yüksəldin
```

### User-Specific Data-nı Global Cache Etmək

```php
// YANLIŞ: İstifadəçi-spesifik sorğu
$prompt = "Mənim son 5 sifarişimi göstər";
$this->cache->setGlobal($prompt, $response); // Hər istifadəçi eyni cavabı alır!

// DOĞRU: User-specific prompt-lar cache edilmir
// Yalnız generic, user-agnostic sorğular cache olunur
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Semantic Cache İmplementasiyası

`SemanticCacheService` implement et. `SemanticCacheEntry` modeli: `query_text`, `query_embedding`, `response`, `ttl`, `hit_count`. Sorğu gəldikdə: embed et, pgvector-dən `cosine_similarity > 0.92` olan keş tapırsansa qaytar. Yoxdursa LLM çağır, cavabı keş et.

### Tapşırıq 2: Cache Hit Rate Dashboard

`cache_hits` vs `cache_misses` metrikasını 1 həftə boyunca log et. Hit rate hesabla: `hits / (hits + misses)`. Similarity threshold-u 0.88-dən 0.95-ə dəyiş. Threshold artdıqca hit rate azalır, quality artır — optimal balansı tap. Bu məlumat threshold seçimini əsaslandırır.

### Tapşırıq 3: Cache Invalidation Test

`SemanticCacheEntry` üçün TTL-based invalidation: 24 saatdan köhnə keşlər `cache:prune-semantic` scheduled command ilə silin. Sonra topic-specific invalidation: "pricing" mövzusunda yenilənmə baş verdikdə bütün pricing-related keşlərini sil. Cosine similarity ilə related keşləri tapmaq mümkündür?

---

## Əlaqəli Mövzular

- [04-ai-idempotency-circuit-breaker.md](04-ai-idempotency-circuit-breaker.md) — Idempotency patterns
- [../08-production/04-cost-optimization.md](../08-production/04-cost-optimization.md) — Ümumi xərc optimizasiyası
- [../02-claude-api/09-prompt-caching.md](../02-claude-api/09-prompt-caching.md) — Provider-side prompt caching
- [../04-rag-embeddings/01-embeddings-vector-search.md](../04-rag-embeddings/01-embeddings-vector-search.md) — Embedding əsasları
