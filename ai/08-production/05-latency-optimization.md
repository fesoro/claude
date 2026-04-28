# AI Sistemləri üçün Gecikmə Optimallaşdırması (Senior)

> **Auditoriya:** Baş tərtibatçılar və memarlar  
> **Məqsəd:** AI ilə işləyən funksiyalar üçün P50 < 1s qəbul edilmiş gecikmə və P99 < 10s əldə etmək

---

## 1. AI Gecikmələrini Başa Düşmək

AI gecikmələrinin fərqli optimallaşdırma strategiyaları tələb edən iki ayrı komponenti var:

```
Ümumi gecikmə = İlk tokendə vaxt (TTFT) + Yaratma gecikmə

İlk tokendə vaxt:
├── AI provayderinə şəbəkə gediş-qayıdış müddəti (50-200ms)
├── Server tərəfli növbə (dəyişkən)
├── Model yükləmə / kontekst işləmə (100-500ms)
└── Prefill hesablaması (giriş uzunluğu ilə miqyaslanır)

Yaratma gecikmə:
└── Çıxış tokenləri × token/saniyə (adətən 50-100 tok/s)
    └── 500 çıxış tokeni ÷ 80 tok/s = əlavə 6.25 saniyə
```

**Qəbul edilmiş gecikmə** istifadəçilərin hiss etdiyi şeydir, ümumi gecikmə deyil. İlk sözü 800ms-də göstərən cavab, 500 sözün hamısını 3 saniyədə bir anda çatdırandan daha sürətli hiss etdirir.

### İstifadə Halına Görə Gecikmə Hədəfləri

| İstifadə Halı | P50 TTFT | P95 Ümumi | Strategiya |
|--------------------|----------|-----------|---------------------------------|
| İnteraktiv söhbət | < 1s | < 8s | Axın + paralel RAG |
| Qısa tamamlama | < 500ms | < 3s | Haiku modeli + keş |
| Sənəd analizi | N/A | < 60s | Asinxron + irəliləyiş bildirişi |
| Kod tamamlama | < 300ms | < 2s | Axın + əvvəlcədən isidir |
| Toplu emal | N/A | Ən yaxşı cəhd | Növbə işçiləri + toplu emal |

---

## 2. Axın: Ən Təsirli Optimallaşdırma

Axın, faktiki yaratma sürətini dəyişdirmədən *qəbul edilmiş* gecikmənü 80% azaldır.

```php
<?php
// app/Services/AI/ClaudeStreamingService.php

namespace App\Services\AI;

use Anthropic\Anthropic;
use Generator;

class ClaudeStreamingService
{
    private Anthropic $client;

    public function __construct()
    {
        $this->client = new Anthropic(apiKey: config('services.anthropic.key'));
    }

    /**
     * Claude-dan tokenləri PHP Generator kimi axın edin.
     * Çağıran tokenləri gəldikdə istehlak edir.
     */
    public function stream(
        array  $messages,
        string $model = 'claude-sonnet-4-5',
        string $systemPrompt = '',
        int    $maxTokens = 2048,
    ): Generator {
        $params = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];

        if ($systemPrompt) {
            $params['system'] = $systemPrompt;
        }

        $stream = $this->client->messages->stream($params);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta') {
                yield [
                    'type' => 'token',
                    'text' => $event->delta->text ?? '',
                ];
            }

            if ($event->type === 'message_delta') {
                yield [
                    'type'         => 'usage',
                    'input_tokens' => $event->usage->input_tokens ?? 0,
                    'output_tokens'=> $event->usage->output_tokens ?? 0,
                ];
            }
        }
    }

    /**
     * Axınlı cavablar üçün SSE controller idarəedicisi.
     */
    public function streamToSSE(array $messages, string $model = 'claude-sonnet-4-5'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () use ($messages, $model) {
            if (ob_get_level()) ob_end_clean();

            $fullResponse = '';

            foreach ($this->stream($messages, $model) as $event) {
                if ($event['type'] === 'token') {
                    $fullResponse .= $event['text'];

                    echo 'event: token' . "\n";
                    echo 'data: ' . json_encode(['text' => $event['text']]) . "\n\n";
                    flush();
                }

                if ($event['type'] === 'usage') {
                    echo 'event: done' . "\n";
                    echo 'data: ' . json_encode([
                        'usage'     => $event,
                        'full_text' => $fullResponse,
                    ]) . "\n\n";
                    flush();
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

---

## 3. HTTP Müştərisi üçün Bağlantı Hovuzu

PHP-FPM standart olaraq hər sorğu üçün yeni HTTP bağlantısı yaradır. Octane (Swoole) ilə davamlı bağlantı hovuzlarını saxlaya bilərik:

```php
<?php
// app/Services/AI/HttpConnectionPool.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Factory;

class HttpConnectionPool
{
    private static array $clients = [];

    /**
     * AI provayderi üçün yenidən istifadə edilə bilən HTTP müştərisi alın.
     * Octane ilə bu müştəri sorğular arasında davam edir.
     *
     * Bağlantı hovuzunun üstünlükləri:
     * - TCP əl sıxışma yükünü aradan qaldırır (sorğu başına ~50-100ms)
     * - HTTP/2 multiplekslemesini saxlayır (bir bağlantı üzərindən çoxsaylı eyni vaxtlı sorğular)
     * - TLS sessiyasını yenidən istifadə edir (~100ms qənaət)
     */
    public static function get(string $provider = 'anthropic'): \Illuminate\Http\Client\PendingRequest
    {
        if (! isset(static::$clients[$provider])) {
            static::$clients[$provider] = Http::baseUrl(self::baseUrl($provider))
                ->withHeaders([
                    'x-api-key'    => self::apiKey($provider),
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(120)
                ->connectTimeout(5)
                ->withOptions([
                    'curl' => [
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_TCP_KEEPIDLE  => 60,
                        CURLOPT_TCP_KEEPINTVL => 10,
                    ],
                ]);
        }

        return static::$clients[$provider];
    }

    public static function reset(): void
    {
        static::$clients = [];
    }

    private static function baseUrl(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'https://api.anthropic.com',
            'openai'    => 'https://api.openai.com',
            'ollama'    => config('services.ollama.url', 'http://localhost:11434'),
            default     => throw new \InvalidArgumentException("Naməlum provayeder: {$provider}"),
        };
    }

    private static function apiKey(string $provider): string
    {
        return match ($provider) {
            'anthropic' => config('services.anthropic.key'),
            'openai'    => config('services.openai.key'),
            'ollama'    => '',
            default     => '',
        };
    }
}
```

---

## 4. Http::pool() ilə Paralel API Çağırışları

Bir sorğu bir neçə müstəqil AI çağırışı tələb etdikdə (məs., RAG əldə etmə + kontekst formatlaması + yaratma planlaması), onları paralel olaraq işlədin:

```php
<?php
// app/Services/AI/ParallelAIService.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class ParallelAIService
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly ClaudeService    $claude,
    ) {}

    /**
     * Maksimum paralelliklə RAG boru kəmərini icra edin.
     *
     * Ardıcıl (naiv):     Embed(200ms) + Əldə et(150ms) + Yarat(3000ms) = 3350ms
     * Paralel (optimal):  Embed+Klassifikasiya paralel(200ms) + Əldə et(150ms) + Yarat(3000ms) = 3350ms
     *
     * Əsl qazanc müstəqil ön emal addımlarını paralel işlətməkdir.
     */
    public function chatWithRAG(string $query, int $tenantId): array
    {
        // Mərhələ 1: Paralel ön emal (embedding + klassifikasiya)
        // Bunlar müstəqildir və eyni vaxtda işləyə bilər
        [$embedding, $intent] = $this->runInParallel([
            'embed'   => fn() => $this->embeddings->embed($query),
            'classify'=> fn() => $this->claude->complete(
                model: 'claude-haiku-4-5',
                prompt: "Niyyəti 'faktiki', 'söhbətcil' və ya 'texniki' olaraq klassifikasiya edin: {$query}",
                maxTokens: 10,
            ),
        ]);

        // Mərhələ 2: Embedding nəticəsindən istifadə edərək əldə etmə
        $chunks = $this->retrieveRelevant($embedding, $tenantId, intent: trim($intent));

        // Mərhələ 3: Yaratma (axın)
        $response = $this->claude->complete(
            model: 'claude-sonnet-4-5',
            prompt: $this->buildPrompt($query, $chunks),
        );

        return compact('response', 'chunks', 'intent');
    }

    /**
     * Fiber-lərdən istifadə edərək çoxsaylı bağlamaları paralel icra edin.
     * Nəticələri giriş ilə eyni ardıcıllıqda qaytarır.
     */
    public function runInParallel(array $callables): array
    {
        $results = [];
        $fibers  = [];

        // Bütün fiber-ləri başladın
        foreach ($callables as $key => $callable) {
            $fiber         = new \Fiber($callable);
            $fibers[$key]  = $fiber;
            $fiber->start();
        }

        // Nəticələri toplayın
        foreach ($fibers as $key => $fiber) {
            while (! $fiber->isTerminated()) {
                $fiber->resume();
            }
            $results[$key] = $fiber->getReturn();
        }

        return array_values($results);
    }

    /**
     * Çoxsaylı istifadəçi sorğularını toplu emal edin — N sorğunu paralel işləyin.
     * Toplu ön emal üçün faydalıdır.
     */
    public function batchProcess(array $queries, callable $processor, int $concurrency = 5): array
    {
        $results = [];
        $chunks  = array_chunk($queries, $concurrency, true);

        foreach ($chunks as $chunk) {
            $batch = [];

            foreach ($chunk as $key => $query) {
                $batch[$key] = fn() => $processor($query);
            }

            $batchResults = $this->runInParallel($batch);

            foreach (array_keys($chunk) as $idx => $key) {
                $results[$key] = $batchResults[$idx];
            }
        }

        return $results;
    }

    private function retrieveRelevant(array $embedding, int $tenantId, string $intent): array
    {
        $limit = $intent === 'factual' ? 7 : 3;

        return \App\Models\DocumentChunk::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('content, 1 - (embedding <=> ?) as score', [json_encode($embedding)])
            ->having('score', '>=', 0.7)
            ->orderByDesc('score')
            ->limit($limit)
            ->get(['content', 'score'])
            ->toArray();
    }

    private function buildPrompt(string $query, array $chunks): string
    {
        $context = collect($chunks)
            ->map(fn($c, $i) => "<source id=\"{$i}\">{$c['content']}</source>")
            ->implode("\n");

        return "<context>\n{$context}\n</context>\n\nSual: {$query}";
    }
}
```

---

## 5. Ümumi Promptlar üçün Keşi Əvvəlcədən İsitmək

Sistem promptları + statik kontekst Anthropic-in prompt keşləmə API-sindən istifadə edərək əvvəlcədən keşlənə bilər:

```php
<?php
// app/Services/AI/PromptPreWarmer.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class PromptPreWarmer
{
    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    /**
     * Tez-tez istifadə edilən promptları əvvəlcədən isidin.
     * Keş təzəliyini təmin etmək üçün gündəlik planlanır.
     *
     * Anthropic prompt keşi keşlənmiş giriş tokenlarını ~90% azaldır.
     * Keş TTL 5 dəqiqədir (hər istifadədə yenilənir).
     */
    public function warmCommonPrompts(): void
    {
        $tenants = \App\Models\Tenant::with('aiConfig')
            ->where('is_active', true)
            ->get();

        foreach ($tenants as $tenant) {
            $this->warmTenantPrompt($tenant);
        }
    }

    private function warmTenantPrompt(\App\Models\Tenant $tenant): void
    {
        $systemPrompt = $tenant->aiConfig?->system_prompt ?? '';

        if (strlen($systemPrompt) < 1000) {
            return; // Prompt keşləmə yalnız böyük promptlar üçün faydalıdır
        }

        try {
            // Keşi isitmək üçün minimal "ping" sorğusu göndərin
            $this->claude->complete(
                model: 'claude-sonnet-4-5',
                systemPrompt: $systemPrompt,
                prompt: 'Salam',
                maxTokens: 5,
                promptCaching: true,  // Prompt keş başlıqlarını aktivləşdir
            );

            Cache::put("prompt_warmed:{$tenant->id}", true, now()->addMinutes(4));

        } catch (\Throwable $e) {
            logger()->warning("{$tenant->id} kirayəçisi üçün prompt isitmə uğursuz oldu", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Proqnozlaşdırıla bilən sorğular üçün cavabları əvvəlcədən yaradın.
     * Çoxsaylı istifadəçinin soruşduğu FAQ tipli suallar üçün.
     */
    public function preGenerateCommonAnswers(array $commonQueries): void
    {
        foreach ($commonQueries as $query) {
            $cacheKey = 'pregened:' . md5($query);

            if (Cache::has($cacheKey)) continue;

            $response = $this->claude->complete(
                model: 'claude-sonnet-4-5',
                prompt: $query,
            );

            Cache::put($cacheKey, $response, now()->addHours(12));
        }
    }
}
```

---

## 6. Histoqram ilə Gecikmə Ölçmə Middleware

```php
<?php
// app/Http/Middleware/AILatencyMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AILatencyMiddleware
{
    /**
     * AI son nöqtələri üçün gecikmə histoqramını izləyin.
     * Bucketlər: <500ms, <1s, <2s, <5s, <10s, <30s, >30s
     */
    private array $buckets = [500, 1000, 2000, 5000, 10000, 30000];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAIRequest($request)) {
            return $next($request);
        }

        $start    = hrtime(true);
        $response = $next($request);
        $latencyMs = (hrtime(true) - $start) / 1_000_000;

        $this->recordLatency($request, $latencyMs);

        // Debugging üçün gecikmə cavaba əlavə edin
        $response->headers->set('X-AI-Latency-Ms', (int) $latencyMs);

        return $response;
    }

    private function recordLatency(Request $request, float $latencyMs): void
    {
        $endpoint = $request->route()?->getName() ?? $request->path();
        $model    = $request->attributes->get('ai_model', 'naməlum');
        $date     = now()->format('YmdH'); // Saatlıq bucketlər

        // Histoqram bucketini artırın
        $bucket = $this->getBucket($latencyMs);
        Cache::increment("latency:hist:{$endpoint}:{$date}:bucket_{$bucket}", 1);

        // Orta hesablama üçün ümumi izlə
        Cache::increment("latency:sum:{$endpoint}:{$date}", (int) $latencyMs);
        Cache::increment("latency:count:{$endpoint}:{$date}", 1);

        // Debugging üçün yavaş sorğuları izləyin
        if ($latencyMs > 10000) {
            logger()->warning('Yavaş AI sorğusu', [
                'endpoint'   => $endpoint,
                'latency_ms' => (int) $latencyMs,
                'model'      => $model,
                'user_id'    => auth()->id(),
            ]);
        }
    }

    /**
     * Histoqramdan P50/P95/P99 persentillərini alın.
     * İzləmə paneli tərəfindən çağırılır.
     */
    public function getPercentiles(string $endpoint, string $hour): array
    {
        $histogram = [];
        foreach ($this->buckets as $bucket) {
            $histogram[$bucket] = (int) Cache::get("latency:hist:{$endpoint}:{$hour}:bucket_{$bucket}", 0);
        }
        $histogram[PHP_INT_MAX] = (int) Cache::get("latency:hist:{$endpoint}:{$hour}:bucket_" . PHP_INT_MAX, 0);

        $total      = array_sum($histogram);
        $sum        = (int) Cache::get("latency:sum:{$endpoint}:{$hour}", 0);
        $count      = (int) Cache::get("latency:count:{$endpoint}:{$hour}", 0);

        if ($total === 0) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'avg' => 0, 'count' => 0];
        }

        return [
            'p50'   => $this->percentileFromHistogram($histogram, $total, 0.50),
            'p95'   => $this->percentileFromHistogram($histogram, $total, 0.95),
            'p99'   => $this->percentileFromHistogram($histogram, $total, 0.99),
            'avg'   => $count > 0 ? round($sum / $count) : 0,
            'count' => $total,
        ];
    }

    private function getBucket(float $latencyMs): int
    {
        foreach ($this->buckets as $bucket) {
            if ($latencyMs < $bucket) return $bucket;
        }
        return PHP_INT_MAX;
    }

    private function percentileFromHistogram(array $histogram, int $total, float $percentile): int
    {
        $target  = (int) ceil($total * $percentile);
        $cumulative = 0;

        foreach ($histogram as $bucket => $count) {
            $cumulative += $count;
            if ($cumulative >= $target) {
                return $bucket === PHP_INT_MAX ? 30000 : $bucket;
            }
        }

        return 30000;
    }

    private function isAIRequest(Request $request): bool
    {
        return str_contains($request->path(), 'ai/') ||
               str_contains($request->path(), 'chat') ||
               $request->attributes->has('ai_model');
    }
}
```

---

## 7. Coğrafi Yönləndirmə

AI trafikini AI provayderinin məlumat mərkəzlərinə ən yaxın yerə yerləşdirin:

```php
<?php
// config/ai.php

return [
    'routing' => [
        /**
         * Sorğuları ən yaxın AI son nöqtəsinə yönləndirin.
         * Anthropic-in API-si əsasən ABŞ-dadır.
         * AB istifadəçiləri üçün: OpenAI-nin AB son nöqtələrini və ya Azure OpenAI-ni nəzərdən keçirin.
         */
        'strategy' => env('AI_ROUTING_STRATEGY', 'nearest'),

        'endpoints' => [
            'us-east' => [
                'provider' => 'anthropic',
                'url'      => 'https://api.anthropic.com',
                'regions'  => ['us-east-1', 'us-east-2', 'us-west-1'],
                'latency_ms' => 50,
            ],
            'eu-west' => [
                'provider' => 'azure-openai',
                'url'      => env('AZURE_OPENAI_EU_URL'),
                'regions'  => ['eu-west-1', 'eu-central-1'],
                'latency_ms' => 40,
            ],
        ],
    ],
];
```

---

## 8. Spekulyativ İcra

Yüksək trafikli proqnozlaşdırıla bilən promptlar üçün cavabları tələb edilmədən əvvəl yaradın:

```php
<?php
// app/Services/AI/SpeculativeExecutor.php

namespace App\Services\AI;

/**
 * İstifadəçi onları işə salmadan əvvəl AI çağırışlarını spekulyativ icra edin.
 * Növbəti istifadəçi hərəkəti yüksək dərəcədə proqnozlaşdırıla bildikdə işləyir.
 *
 * Nümunə: İstifadəçi sənədi görüntüləyir → "Xülasə et"ə kliklədikdə
 * anında hazır olması üçün xülasəni əvvəlcədən yaradın.
 */
class SpeculativeExecutor
{
    public function __construct(
        private readonly ClaudeService $claude,
        private readonly SemanticCache $cache,
    ) {}

    /**
     * İstifadəçi sənəd səhifəsini görüntüləyərkən çağırılır.
     * Gözlənilən növbəti cavabları spekulyativ olaraq hazırlayır.
     */
    public function onDocumentView(\App\Models\Document $document): void
    {
        $speculativeQueries = [
            "Bu sənədi 3 nöqtə ilə xülasə et",
            "Bu sənəddəki əsas fəaliyyət maddələri hansılardır?",
            "Bu sənəd hansı sualları cavabsız buraxır?",
        ];

        foreach ($speculativeQueries as $query) {
            if ($this->cache->get($query, context: $document->id)) {
                continue; // Artıq keşlənib
            }

            // Cavabları əvvəlcədən yaratmaq üçün aşağı prioritetli işlər sıraya daxil edin
            dispatch(function () use ($query, $document) {
                $response = $this->claude->complete(
                    prompt: $query . "\n\n" . $document->content,
                    model: 'claude-haiku-4-5',
                );
                $this->cache->put($query, $response, context: $document->id);
            })->onQueue('ai-speculative');
        }
    }
}
```

---

## 9. Gecikmə Optimallaşdırması Xülasəsi

| Texnika | Gecikmə Azalması | Tətbiq |
|-----------------------------|-----------------------|----------------------|
| Axın (TTFT) | 80% qəbul edilmiş | Söhbət üçün zəruri |
| Semantik keş | 100% (keş uyğunluğu) | Yüksək təsir |
| Bağlantı hovuzu (Octane) | 50-150ms | Orta səy |
| Paralel ön emal | 30-50% | Orta səy |
| Model seçimi (Haiku) | 3-5x daha sürətli | Asan |
| Prompt keşləmə | Giriş tokenlarında 40% + daha sürətli | Az səy |
| Əvvəlcədən isitmə | 100% (isidilmiş uyğunluq) | Orta səy |
| Coğrafi yönləndirmə | 50-150ms | Yüksək infrastruktur səyi |

**Söhbət üçün ən təsirli stek:** Axın + Semantik keş + Bağlantı hovuzu + Model yönləndirmə = naiv tətbiqa nisbətən ~90% qəbul edilmiş gecikmənin azalması.

## Praktik Tapşırıqlar

### 1. Streaming SSE Tətbiqi
Laravel-də Claude streaming cavabını Server-Sent Events ilə frontend-ə ötürün. `StreamedResponse` istifadə edin, hər token gəldikcə `data: {chunk}\n\n` formatında göndərin. Frontend-də `EventSource` obyekti yaradın, tokenləri DOM-a append edin. TTFT (Time to First Token) `<500ms` hədəfinə çatdığınızı ölçün.

### 2. Semantic Cache Benchmark
Redis ilə embedding-based semantic cache qurun. 100 test sorğusu hazırlayın (50 unikal + 50 semantik dublikat). Cache olmadan vs cache ilə latency-ni müqayisə edin. Cosine similarity threshold-u `0.92` ilə başlayın. Hit rate, false positive rate (yanlış cache hit) və ümumi xərc azalmasını hesabat kimi çıxarın.

### 3. Connection Pool Benchmark
Guzzle client-i singleton kimi Laravel service container-ə qeydiyyatdan keçirin (`app()->singleton()`). Persistent connection ilə yeni connection yaratma arasında `p99` latency fərqini ölçün. 100 paralel sorğu göndərən load test yazın (`Guzzle Pool` istifadə edin). Fərqi qeyd edin.

## Əlaqəli Mövzular

- [AI Xərclərinin Optimallaşdırılması](./04-cost-optimization.md)
- [Observability Logging](./02-observability-logging.md)
- [Semantic Caching](../07-workflows/08-semantic-caching.md)
- [Streaming UI](../07-workflows/06-ai-streaming-ui.md)
- [Multi-Provider Failover](./15-multi-provider-failover.md)
