# External API-dən Gələn Response-u Userə Göstərmək (Middle)

## Problem Təsviri

Təsəvvür edin ki, sizin Laravel application-da bir endpoint var. Bu endpoint external bir API-yə (məsələn, ödəniş gateway, currency exchange rate, shipping calculator və s.) HTTP sorğusu göndərir. Lakin bu external API:

- **Yavaş cavab verə bilər** (5-30 saniyə gecikmə)
- **Timeout ola bilər** (heç cavab gəlməz)
- **Intermittent error** verə bilər (500, 502, 503)
- **Rate limit** edə bilər (429 Too Many Requests)

User isə frontend-də gözləyir və "loading..." spinner görür. 30 saniyə gözlədikdən sonra timeout alır — bu, **çox pis user experience**-dir.

```
User → Laravel App → External API (yavaş/timeout)
         ↑                    ↓
    Gözləyir...         Cavab gəlmir
```

Bu problemi həll etmək üçün bir neçə yanaşma var.

### Problem niyə yaranır?

Developer external API-ni sadəcə `Http::get($externalUrl)` ilə çağırır. Bu sinxron (bloklaşdırıcı) çağırışdır — PHP worker cavab gəlmədikcə gözləyir. FPM-in worker pool-u (məs. 20 worker) limitli olduğu üçün 20 eyni vaxtda gələn yavaş sorğu bütün worker-ları tutur, növbəti sorğular növbəyə düşür. Real ssenaridə: shipping calculator API-si tez-tez yavaşlayır → checkout endpoint-i 15+ saniyə cavab vermir → user səbrsizlənib "Pay" düyməsinə ikinci dəfə basır → double charge riski.

---

## Həll 1: Async Job + Polling

### Konsept

User sorğu göndərdikdə, Laravel dərhal `202 Accepted` cavab qaytarır və background job dispatch edir. Frontend isə müəyyən intervalla (polling) status endpoint-inə sorğu göndərir.

```
1. User → POST /api/fetch-data → 202 Accepted + request_id
2. Background Job → External API → Nəticəni DB-yə yazır
3. User → GET /api/fetch-data/status/{request_id} → pending/completed
```

### Implementation

#### Migration

*Bu kod asinxron sorğuları izləmək üçün database cədvəlini yaradır:*

```php
// database/migrations/2024_01_01_create_async_requests_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('async_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('type'); // request tipi
            $table->json('payload')->nullable(); // request payload
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('result')->nullable(); // nəticə
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('async_requests');
    }
};
```

#### Model

*Bu kod asinxron sorğunun model sinifini, status konstantlarını və yardımçı metodlarını təyin edir:*

```php
// app/Models/AsyncRequest.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AsyncRequest extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'type',
        'payload',
        'status',
        'result',
        'error_message',
        'attempts',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'completed_at' => 'datetime',
    ];

    // Status konstantları
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->request_id = $model->request_id ?? Str::uuid()->toString();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsCompleted(array $result): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
```

#### Controller

*Bu kod sorğunu qəbul edib dərhal 202 qaytaran və polling üçün status endpointi olan controller-i göstərir:*

```php
// app/Http/Controllers/ExternalDataController.php
namespace App\Http\Controllers;

use App\Jobs\FetchExternalDataJob;
use App\Models\AsyncRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalDataController extends Controller
{
    /**
     * Async sorğu başlat — dərhal 202 qaytarır, job dispatch edir.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $asyncRequest = AsyncRequest::create([
            'user_id' => $request->user()->id,
            'type' => 'external_data_fetch',
            'payload' => $validated,
            'status' => AsyncRequest::STATUS_PENDING,
        ]);

        // Background job dispatch edirik
        FetchExternalDataJob::dispatch($asyncRequest);

        return response()->json([
            'message' => 'Sorğunuz qəbul edildi. Status endpoint-dən izləyə bilərsiniz.',
            'request_id' => $asyncRequest->request_id,
            'status_url' => route('async.status', $asyncRequest->request_id),
        ], 202);
    }

    /**
     * Status yoxlama endpoint — frontend buraya polling edir.
     */
    public function status(string $requestId): JsonResponse
    {
        $asyncRequest = AsyncRequest::where('request_id', $requestId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $response = [
            'request_id' => $asyncRequest->request_id,
            'status' => $asyncRequest->status,
            'created_at' => $asyncRequest->created_at->toIso8601String(),
        ];

        if ($asyncRequest->isCompleted()) {
            $response['data'] = $asyncRequest->result;
            $response['completed_at'] = $asyncRequest->completed_at->toIso8601String();
        }

        if ($asyncRequest->status === AsyncRequest::STATUS_FAILED) {
            $response['error'] = $asyncRequest->error_message;
        }

        return response()->json($response);
    }
}
```

#### Job

*Bu kod retry mexanizmi və exponential backoff ilə external API-yə sorğu göndərən background job-u göstərir:*

```php
// app/Jobs/FetchExternalDataJob.php
namespace App\Jobs;

use App\Models\AsyncRequest;
use App\Services\ExternalApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchExternalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksimum cəhd sayı
     */
    public int $tries = 3;

    /**
     * Retry arasında gözləmə (saniyə)
     */
    public array $backoff = [5, 15, 30];

    /**
     * Job timeout (saniyə)
     */
    public int $timeout = 60;

    public function __construct(
        public AsyncRequest $asyncRequest
    ) {}

    public function handle(ExternalApiService $apiService): void
    {
        $this->asyncRequest->markAsProcessing();

        try {
            $result = $apiService->fetchData(
                $this->asyncRequest->payload
            );

            $this->asyncRequest->markAsCompleted($result);

            Log::info('External data fetch completed', [
                'request_id' => $this->asyncRequest->request_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('External data fetch failed', [
                'request_id' => $this->asyncRequest->request_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Son cəhd idisə, failed olaraq işarələ
            if ($this->attempts() >= $this->tries) {
                $this->asyncRequest->markAsFailed($e->getMessage());
            }

            throw $e; // Queue-nun retry mexanizmi işləsin
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->asyncRequest->markAsFailed($exception->getMessage());

        Log::critical('External data fetch permanently failed', [
            'request_id' => $this->asyncRequest->request_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

#### Routes

*Bu kod async fetch endpointlərini auth middleware ilə qeydiyyatdan keçirir:*

```php
// routes/api.php
use App\Http\Controllers\ExternalDataController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/fetch-data', [ExternalDataController::class, 'initiate']);
    Route::get('/fetch-data/status/{requestId}', [ExternalDataController::class, 'status'])
        ->name('async.status');
});
```

#### Frontend Polling (JavaScript)

*Bu kod hər 2 saniyədən bir status endpoint-ini yoxlayan JavaScript polling funksiyasını göstərir:*

```javascript
// Frontend — hər 2 saniyədən bir status yoxlayır
async function fetchDataWithPolling(query) {
    // 1. Sorğunu başlat
    const initResponse = await fetch('/api/fetch-data', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ query }),
    });

    const { request_id, status_url } = await initResponse.json();

    // 2. Polling — status yoxla
    return new Promise((resolve, reject) => {
        const maxAttempts = 30; // 30 * 2s = 60 saniyə max
        let attempts = 0;

        const pollInterval = setInterval(async () => {
            attempts++;

            try {
                const statusResponse = await fetch(status_url, {
                    headers: { 'Authorization': `Bearer ${token}` },
                });
                const result = await statusResponse.json();

                if (result.status === 'completed') {
                    clearInterval(pollInterval);
                    resolve(result.data);
                } else if (result.status === 'failed') {
                    clearInterval(pollInterval);
                    reject(new Error(result.error));
                } else if (attempts >= maxAttempts) {
                    clearInterval(pollInterval);
                    reject(new Error('Timeout: Sorğu çox uzun çəkdi'));
                }
                // status hələ pending/processing — davam et
            } catch (err) {
                clearInterval(pollInterval);
                reject(err);
            }
        }, 2000);
    });
}
```

---

## Həll 2: Webhook-based (Callback URL)

### Konsept

External API-yə sorğu göndərərkən callback URL veririk. API nəticəni hazırladıqda bizim URL-ə POST göndərir.

```
1. User → POST /api/request-data → 202 Accepted
2. Laravel → External API (with callback_url)
3. External API → POST callback_url → Laravel qəbul edir
4. User frontend-də notification alır
```

### Implementation

*Bu kod external API-nin nəticəni göndərdiyi webhook callback controller-ini və imza yoxlamasını göstərir:*

```php
// app/Http/Controllers/WebhookCallbackController.php
namespace App\Http\Controllers;

use App\Events\ExternalDataReady;
use App\Models\AsyncRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookCallbackController extends Controller
{
    /**
     * External API nəticəni buraya göndərir.
     */
    public function handle(Request $request, string $requestId): JsonResponse
    {
        // Webhook imza doğrulaması (security)
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid webhook signature', ['request_id' => $requestId]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $asyncRequest = AsyncRequest::where('request_id', $requestId)->first();

        if (!$asyncRequest) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $asyncRequest->markAsCompleted($request->input('data'));

        // WebSocket vasitəsilə userə real-time bildiriş göndər
        broadcast(new ExternalDataReady($asyncRequest))->toOthers();

        return response()->json(['message' => 'Received'], 200);
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        $secret = config('services.external_api.webhook_secret');

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature ?? '');
    }
}
```

*Bu kod callback URL ilə external API-yə sorğu göndərən və sinxron fetch edən service metodlarını göstərir:*

```php
// app/Services/ExternalApiService.php — webhook URL ilə sorğu
namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExternalApiService
{
    /**
     * External API-yə sorğu göndər — callback URL ilə.
     */
    public function fetchDataWithCallback(array $payload, string $callbackUrl): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.external_api.key'),
            ])
            ->post(config('services.external_api.url') . '/async-request', [
                'data' => $payload,
                'callback_url' => $callbackUrl,
            ]);

        return $response->json();
    }

    /**
     * Sinxron data fetch (digər həllər üçün).
     */
    public function fetchData(array $payload): array
    {
        $response = Http::timeout(15)
            ->retry(3, 1000, function (\Exception $e, \Illuminate\Http\Client\PendingRequest $request) {
                // Yalnız server error və timeout-larda retry et
                return $e instanceof \Illuminate\Http\Client\ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException
                        && $e->response->status() >= 500);
            })
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.external_api.key'),
            ])
            ->post(config('services.external_api.url') . '/data', $payload);

        $response->throw(); // Error varsa exception at

        return $response->json();
    }
}
```

#### Event və Broadcasting

*Bu kod webhook callback-dən sonra real-time bildiriş üçün broadcast event-ini göstərir:*

```php
// app/Events/ExternalDataReady.php
namespace App\Events;

use App\Models\AsyncRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExternalDataReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AsyncRequest $asyncRequest
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->asyncRequest->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'data.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->asyncRequest->request_id,
            'status' => $this->asyncRequest->status,
            'data' => $this->asyncRequest->result,
        ];
    }
}
```

---

## Həll 3: Cache Layer (Stale-While-Revalidate)

### Konsept

External API-dən gələn data-nı cache-ləyirik. Cache köhnəlsə belə, köhnə data-nı qaytarıb background-da yeniləyirik. User heç vaxt gözləmir.

```
1. User sorğu → Cache-də var? → Bəli → Qaytarır (hətta köhnə olsa da)
                                        → Background-da yenilə
                 → Yox → External API-dən çək → Cache-ə yaz → Qaytarır
```

### Implementation

*Bu kod stale-while-revalidate strategiyasını tətbiq edən cache service-ini göstərir — köhnə data qaytarılır, arxa planda yenilənir:*

```php
// app/Services/CachedExternalApiService.php
namespace App\Services;

use App\Jobs\RefreshExternalDataCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CachedExternalApiService
{
    // Cache müddəti: 10 dəqiqə
    private const CACHE_TTL = 600;

    // Stale (köhnəlmiş) data-nı hələ də göstərə biləcəyimiz müddət: 1 saat
    private const STALE_TTL = 3600;

    public function __construct(
        private ExternalApiService $apiService
    ) {}

    /**
     * Stale-while-revalidate strategiyası ilə data çəkmək.
     */
    public function getData(string $key, array $payload): ?array
    {
        $cacheKey = 'external_data:' . md5($key . serialize($payload));
        $staleCacheKey = $cacheKey . ':stale';

        // 1. Əsas cache-ə bax (fresh data)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Stale cache-ə bax (köhnə amma hələ istifadə oluna bilən data)
        $staleData = Cache::get($staleCacheKey);
        if ($staleData !== null) {
            // Stale data qaytarırıq, amma background-da yeniləyirik
            Log::info('Returning stale data, refreshing in background', ['key' => $key]);
            RefreshExternalDataCache::dispatch($cacheKey, $staleCacheKey, $payload);
            return $staleData;
        }

        // 3. Heç bir cache yoxdur — sinxron çəkməliyik
        try {
            $data = $this->apiService->fetchData($payload);
            $this->cacheData($cacheKey, $staleCacheKey, $data);
            return $data;
        } catch (\Throwable $e) {
            Log::error('External API fetch failed, no cache available', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null; // Fallback: null qaytarırıq
        }
    }

    /**
     * Data-nı həm fresh həm də stale cache-ə yazır.
     */
    public function cacheData(string $cacheKey, string $staleCacheKey, array $data): void
    {
        Cache::put($cacheKey, $data, self::CACHE_TTL);
        Cache::put($staleCacheKey, $data, self::STALE_TTL);
    }
}
```

*Bu kod cache-i arxa planda yeniləyən, ShouldBeUnique ilə dublikat job-ları önləyən job-u göstərir:*

```php
// app/Jobs/RefreshExternalDataCache.php
namespace App\Jobs;

use App\Services\CachedExternalApiService;
use App\Services\ExternalApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshExternalDataCache implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public string $cacheKey,
        public string $staleCacheKey,
        public array $payload
    ) {}

    /**
     * ShouldBeUnique — eyni cache key üçün eyni anda yalnız 1 job çalışsın.
     */
    public function uniqueId(): string
    {
        return $this->cacheKey;
    }

    public function handle(
        ExternalApiService $apiService,
        CachedExternalApiService $cachedService
    ): void {
        try {
            $data = $apiService->fetchData($this->payload);
            $cachedService->cacheData($this->cacheKey, $this->staleCacheKey, $data);
            Log::info('Cache refreshed successfully', ['key' => $this->cacheKey]);
        } catch (\Throwable $e) {
            Log::warning('Cache refresh failed', [
                'key' => $this->cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

---

## Həll 4: Circuit Breaker Pattern

### Konsept

External API çox error verəndə, sorğuları dayandırırıq (circuit "açılır"). Müəyyən müddətdən sonra cəhd edirik ("half-open"). Uğurlu olsa, normal rejimə qayıdırıq ("closed").

```
CLOSED (normal) → Error çox artır → OPEN (sorğu göndərmir, dərhal fallback)
     ↑                                        ↓
     ← HALF-OPEN (bir sorğu cəhd edir) ←──────
```

### Implementation

*Bu kod closed/open/half-open vəziyyətlərini idarə edən Circuit Breaker pattern-ini göstərir:*

```php
// app/Services/CircuitBreaker.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';       // Normal — sorğular göndərilir
    private const STATE_OPEN = 'open';           // Açıq — sorğular bloklanır
    private const STATE_HALF_OPEN = 'half_open'; // Yarı açıq — test sorğusu göndərilir

    public function __construct(
        private string $service,
        private int $failureThreshold = 5,   // 5 uğursuz cəhddən sonra aç
        private int $recoveryTimeout = 30,    // 30 saniyə sonra yenidən cəhd et
        private int $successThreshold = 2     // 2 uğurlu cəhddən sonra bağla
    ) {}

    private function cacheKey(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }

    public function getState(): string
    {
        return Cache::get($this->cacheKey('state'), self::STATE_CLOSED);
    }

    /**
     * Circuit breaker vasitəsilə sorğu icra et.
     *
     * @param callable $action    Əsas sorğu
     * @param callable $fallback  Circuit açıqdırsa, fallback funksiyası
     * @return mixed
     */
    public function call(callable $action, callable $fallback): mixed
    {
        $state = $this->getState();

        // OPEN — sorğu göndərmədən dərhal fallback qaytarırıq
        if ($state === self::STATE_OPEN) {
            // Recovery timeout keçibsə, half-open-ə keç
            if ($this->recoveryTimeoutExpired()) {
                Log::info("Circuit breaker [{$this->service}]: OPEN → HALF_OPEN");
                $this->setState(self::STATE_HALF_OPEN);
                return $this->attemptCall($action, $fallback);
            }

            Log::debug("Circuit breaker [{$this->service}]: OPEN — returning fallback");
            return $fallback();
        }

        // HALF_OPEN — bir test sorğusu göndər
        if ($state === self::STATE_HALF_OPEN) {
            return $this->attemptCall($action, $fallback);
        }

        // CLOSED — normal iş rejimi
        return $this->attemptCall($action, $fallback);
    }

    private function attemptCall(callable $action, callable $fallback): mixed
    {
        try {
            $result = $action();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            Log::warning("Circuit breaker [{$this->service}]: Failure recorded", [
                'error' => $e->getMessage(),
                'failures' => $this->getFailureCount(),
            ]);

            return $fallback();
        }
    }

    private function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = Cache::increment($this->cacheKey('successes'));

            if ($successes >= $this->successThreshold) {
                Log::info("Circuit breaker [{$this->service}]: HALF_OPEN → CLOSED");
                $this->setState(self::STATE_CLOSED);
                $this->resetCounters();
            }
        } else {
            // CLOSED state-də failure counter-i sıfırla
            Cache::forget($this->cacheKey('failures'));
        }
    }

    private function recordFailure(): void
    {
        $failures = Cache::increment($this->cacheKey('failures'));

        if ($failures >= $this->failureThreshold) {
            Log::warning("Circuit breaker [{$this->service}]: CLOSED → OPEN (failures: {$failures})");
            $this->setState(self::STATE_OPEN);
            Cache::put($this->cacheKey('opened_at'), now()->timestamp, 3600);
        }
    }

    private function setState(string $state): void
    {
        Cache::put($this->cacheKey('state'), $state, 3600);
    }

    private function getFailureCount(): int
    {
        return (int) Cache::get($this->cacheKey('failures'), 0);
    }

    private function recoveryTimeoutExpired(): bool
    {
        $openedAt = Cache::get($this->cacheKey('opened_at'), 0);
        return (now()->timestamp - $openedAt) >= $this->recoveryTimeout;
    }

    private function resetCounters(): void
    {
        Cache::forget($this->cacheKey('failures'));
        Cache::forget($this->cacheKey('successes'));
        Cache::forget($this->cacheKey('opened_at'));
    }
}
```

#### Circuit Breaker istifadəsi

*Bu kod circuit breaker-i real servis çağırışına tətbiq edən nümunəni göstərir:*

```php
// app/Services/ResilientExternalApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ResilientExternalApiService
{
    private CircuitBreaker $circuitBreaker;

    public function __construct(
        private ExternalApiService $apiService
    ) {
        $this->circuitBreaker = new CircuitBreaker(
            service: 'external_payment_api',
            failureThreshold: 5,
            recoveryTimeout: 30,
            successThreshold: 2
        );
    }

    public function fetchData(array $payload): array
    {
        return $this->circuitBreaker->call(
            // Əsas sorğu
            action: fn() => $this->apiService->fetchData($payload),

            // Fallback — circuit açıqdırsa və ya error olanda
            fallback: function () use ($payload) {
                Log::info('Using fallback for external data');

                // Fallback 1: Cache-dən köhnə data qaytarmağa çalış
                $cacheKey = 'external_fallback:' . md5(serialize($payload));
                $cached = Cache::get($cacheKey);

                if ($cached) {
                    return array_merge($cached, ['_stale' => true]);
                }

                // Fallback 2: Default dəyər qaytarır
                return [
                    '_fallback' => true,
                    '_message' => 'Xidmət müvəqqəti əlçatan deyil. Zəhmət olmasa bir az sonra yenidən cəhd edin.',
                ];
            }
        );
    }
}
```

---

## Həll 5: Queue + WebSocket (Real-time Notification)

### Konsept

Job dispatch + WebSocket broadcasting birləşdirilir. User gözləmək əvəzinə, real-time notification alır.

### Laravel Echo + Pusher/Soketi ilə implementation

*Laravel Echo + Pusher/Soketi ilə implementation üçün kod nümunəsi:*
```php
// app/Jobs/FetchAndBroadcastJob.php
namespace App\Jobs;

use App\Events\ExternalDataReady;
use App\Events\ExternalDataFailed;
use App\Models\AsyncRequest;
use App\Services\ResilientExternalApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchAndBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [3, 10, 30];

    public function __construct(
        public AsyncRequest $asyncRequest
    ) {}

    public function handle(ResilientExternalApiService $apiService): void
    {
        $this->asyncRequest->markAsProcessing();

        try {
            $result = $apiService->fetchData($this->asyncRequest->payload);

            $this->asyncRequest->markAsCompleted($result);

            // Real-time bildiriş — WebSocket
            broadcast(new ExternalDataReady($this->asyncRequest));

        } catch (\Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                $this->asyncRequest->markAsFailed($e->getMessage());
                broadcast(new ExternalDataFailed($this->asyncRequest));
            }
            throw $e;
        }
    }
}
```

#### Frontend — Laravel Echo ilə real-time dinləmə

*Frontend — Laravel Echo ilə real-time dinləmə üçün kod nümunəsi:*
```javascript
// WebSocket ilə real-time nəticə almaq
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true,
});

// User-ə məxsus private channel-ı dinlə
Echo.private(`user.${userId}`)
    .listen('.data.ready', (event) => {
        console.log('Data hazırdır:', event.data);
        // UI-ı yenilə
        updateUI(event.data);
    })
    .listen('.data.failed', (event) => {
        console.error('Sorğu uğursuz oldu:', event.error);
        showError(event.error);
    });
```

---

## HTTP Client — Timeout və Retry Konfiqurasiyası

### Laravel HTTP Client

*Laravel HTTP Client üçün kod nümunəsi:*
```php
// config/services.php
return [
    'external_api' => [
        'url' => env('EXTERNAL_API_URL'),
        'key' => env('EXTERNAL_API_KEY'),
        'webhook_secret' => env('EXTERNAL_API_WEBHOOK_SECRET'),
        'timeout' => env('EXTERNAL_API_TIMEOUT', 15),       // saniyə
        'connect_timeout' => env('EXTERNAL_API_CONNECT_TIMEOUT', 5),
        'retry_times' => env('EXTERNAL_API_RETRY_TIMES', 3),
        'retry_sleep' => env('EXTERNAL_API_RETRY_SLEEP', 500), // millisaniyə
    ],
];
```

*'retry_sleep' => env('EXTERNAL_API_RETRY_SLEEP', 500), // millisaniyə üçün kod nümunəsi:*
```php
// app/Services/ExternalApiService.php — ətraflı konfiqurasiya
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    /**
     * Konfiqurasiya edilmiş HTTP client qaytarır.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.external_api.url'))
            ->timeout(config('services.external_api.timeout', 15))
            ->connectTimeout(config('services.external_api.connect_timeout', 5))
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.external_api.key'),
                'Accept' => 'application/json',
            ])
            ->retry(
                times: config('services.external_api.retry_times', 3),
                sleepMilliseconds: config('services.external_api.retry_sleep', 500),
                when: function (\Exception $e, PendingRequest $request) {
                    // Retry yalnız bu halda:
                    if ($e instanceof ConnectionException) {
                        Log::warning('Connection failed, retrying...');
                        return true;
                    }

                    if ($e instanceof RequestException) {
                        $status = $e->response->status();

                        // 429 (Rate Limited) — Retry-After header-ə bax
                        if ($status === 429) {
                            $retryAfter = $e->response->header('Retry-After');
                            if ($retryAfter) {
                                sleep((int) $retryAfter);
                            }
                            return true;
                        }

                        // 5xx Server Error — retry et
                        if ($status >= 500) {
                            return true;
                        }
                    }

                    // 4xx Client Error — retry etmə (bizim səhvimizdir)
                    return false;
                },
                throw: true // son cəhddən sonra exception at
            );
    }

    public function fetchData(array $payload): array
    {
        $response = $this->client()->post('/data', $payload);
        return $response->json();
    }
}
```

### Guzzle Retry Middleware (Aşağı səviyyəli kontrol)

*Guzzle Retry Middleware (Aşağı səviyyəli kontrol) üçün kod nümunəsi:*
```php
// app/Services/GuzzleClientFactory.php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class GuzzleClientFactory
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function create(array $config = []): Client
    {
        $stack = HandlerStack::create();

        // Retry middleware
        $stack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        // Logging middleware
        $stack->push(Middleware::log($this->logger, new \GuzzleHttp\MessageFormatter(
            '{method} {uri} - {code} {phrase} - {res_header_Content-Length}B - {total_time}s'
        )));

        return new Client(array_merge([
            'handler' => $stack,
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => true,
        ], $config));
    }

    /**
     * Hansı hallarda retry ediləcəyini müəyyən edir.
     */
    private function retryDecider(): callable
    {
        return function (
            int $retries,
            Request $request,
            ?Response $response = null,
            ?\Exception $exception = null
        ): bool {
            // Maksimum 3 retry
            if ($retries >= 3) {
                return false;
            }

            // Connection error — retry
            if ($exception instanceof ConnectException) {
                $this->logger->warning("Connection error, retry #{$retries}", [
                    'uri' => (string) $request->getUri(),
                ]);
                return true;
            }

            // Server error (5xx) — retry
            if ($response && $response->getStatusCode() >= 500) {
                $this->logger->warning("Server error {$response->getStatusCode()}, retry #{$retries}");
                return true;
            }

            return false;
        };
    }

    /**
     * Exponential backoff — hər retry-da gözləmə artır.
     */
    private function retryDelay(): callable
    {
        return function (int $retries): int {
            // 1s, 2s, 4s (exponential backoff)
            return (int) pow(2, $retries) * 1000;
        };
    }
}
```

---

## Monitoring və Alerting

### Health Check Controller

*Health Check Controller üçün kod nümunəsi:*
```php
// app/Http/Controllers/HealthCheckController.php
namespace App\Http\Controllers;

use App\Services\CircuitBreaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'external_api' => $this->checkExternalApi(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = !in_array(false, array_column($checks, 'healthy'));

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['healthy' => true, 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Cache::store('redis')->put('health_check', true, 5);
            return ['healthy' => true, 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkExternalApi(): array
    {
        $cb = new CircuitBreaker('external_payment_api');
        $state = $cb->getState();

        return [
            'healthy' => $state !== 'open',
            'circuit_state' => $state,
            'message' => $state === 'open'
                ? 'Circuit breaker OPEN — API əlçatan deyil'
                : 'Normal',
        ];
    }

    private function checkQueue(): array
    {
        try {
            $size = \Illuminate\Support\Facades\Queue::size();
            return [
                'healthy' => $size < 1000,
                'queue_size' => $size,
                'message' => $size < 1000 ? 'Normal' : 'Queue backlog çox böyükdür',
            ];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }
}
```

---

## Fallback Strategiyası — Xülasə

| Vəziyyət | Strategiya | Nümunə |
|-----------|-----------|--------|
| API yavaşdır (>5s) | Async Job + Polling/WebSocket | User gözləmir, background-da çəkilir |
| API down-dır | Circuit Breaker + Cache Fallback | Köhnə data göstərilir |
| API rate limit edir | Retry with backoff + Queue | Sorğular növbəyə düşür |
| Network timeout | Retry middleware | Avtomatik yenidən cəhd |
| Bütün fallback-lar uğursuz | Graceful degradation | User-ə məlumatlandırıcı mesaj |

---

## Interview Sualları və Cavablar

**S: External API-ə sinxron HTTP sorğu göndərməyin nə problemi var?**
C: HTTP sorğusu bloklaşdırıcıdır — cavab gəlmədikcə PHP worker serbest deyil. FPM worker pool bitərsə, yeni request-lər queue-a düşür. Bir yavaş API bütün server capacity-ni tuta bilər. Həll: async job dispatch et, 202 Accepted qaytar, nəticə webhook/polling ilə al.

**S: Circuit Breaker nədir, state-ləri necə işləyir?**
C: Circuit Breaker üç vəziyyətdə olur:
- **Closed** (normal): Sorğular keçir, uğursuzluqlar sayılır.
- **Open** (açıq): Ardıcıl N uğursuzluqdan sonra açılır. Bütün sorğular dərhal rədd edilir (fast fail), API çağırılmır.
- **Half-Open** (test): Müəyyən vaxtdan (timeout) sonra yarı açılır. 1 test sorğusu buraxılır: uğurluysa Closed-a keçir, uğursuzsaOpen-da qalır.
Niyə vacibdir: open API-yə hər request göndərmək thread pool-u tükədir, latency artır. Fast fail ilə sistem öz resurslarını qoruyur.

**S: Polling vs Webhook — hansını seçərdiniz?**
C: Asılıdır. Polling sadədir, client idarə edir, xarici service webhook dəstəkləməyə bilər — dezavantaj: lazımsız sorğular (boş response). Webhook daha effektivdir — event baş verəndə push edilir, latency minimaldır — dezavantaj: public endpoint lazımdır, delivery zəmanəti yoxdur (yenidən cəhd lazımdır). Production-da hər ikisi birlikdə: webhook + polling reconciliation.

**S: Exponential backoff + jitter niyə lazımdır?**
C: API artıq overload olduqda eyni anda bütün client-lər retry etsə (thundering herd), problemi daha da pisləşdirir. Exponential backoff: hər retry-da gözləmə ikiqat artır (1s, 2s, 4s, 8s). Jitter: random variasiya əlavə edir ki, bütün client-lər eyni anda retry etməsin. Bu iki birlikdə API-nin özünü bərpa etməsinə imkan verir.

**S: Cache-as-fallback strategiyası nə zaman düzgündür?**
C: Məlumatın bir az köhnə olması qəbul edilə bilərsə (currency rate, product catalog, weather). Kritik real-time data (bank balansı, uçuş statusu) üçün stale cache istifadə etmək təhlükəlidir. Həmişə response-da `X-Cache-Age` header-i göndərin ki, client məlumatın nə qədər köhnə olduğunu bilsin.

**S: Webhook delivery zəmanətini necə təmin edersiniz?**
C: Webhook delivery at-most-once zəmanəti verir. Exactly-once üçün: (1) webhook event-lərini DB-yə yaz, (2) idempotency key ilə dublikatları filtrele, (3) uğursuz delivery-ləri retry et (exponential backoff ilə). Müştəri webhook endpoint-i 200 qaytarmazsa, 24 saat ərzində 5-10 dəfə yenidən cəhd et.

---

## Interview-da Bu Sualı Necə Cavablandırmaq

1. **Problemi dəqiq təsvir edin** — sync HTTP call blocking olur, user experience pozulur
2. **Bir neçə həll təklif edin** — polling, webhook, cache, circuit breaker
3. **Trade-off-ları izah edin** — polling = sadə amma server yükü artır; WebSocket = real-time amma complexity artır
4. **Production-ready detalları qeyd edin** — monitoring, fallback, retry, timeout config
5. **Kod yazın** — təmiz, SOLID prinsiplərinə uyğun implementation göstərin

---

## Anti-patternlər

**1. Timeout qoymamaq**
`Http::get($url)` — default timeout yoxdur, 30 saniyə asılı qala bilər. Həmişə `Http::timeout(5)->get($url)` istifadə edin.

**2. Retry-da exponential backoff olmamaq**
Hər 1 saniyədə retry — xarici API artıq overload olubsa siz daha da yükləyirsiniz. `retry(3, fn($attempt) => 100 * pow(2, $attempt))` istifadə edin.

**3. Circuit breaker olmadan fail**
API saatlarla down olsa belə hər request try edir — thread pool tükənir. Circuit breaker pattern: 5 uğursuz request sonrası 30 saniyə açıq qal.

**4. Xarici API-ni sinxron HTTP request-lə çağırmaq**
User-facing request içindən yavaş API çağırmaq — user gözləyir, timeout riski. Queue-based async processing: job dispatch et, webhook ya da polling ilə nəticəni al.

**5. Uğursuz request-ləri log etməmək**
API nə vaxt, niyə uğursuz oldu bilmirsiniz. Hər failure-ı context ilə log edin: URL, status code, response time, retry count.

**6. API key-i kod içinə yazmaq**
`$apiKey = 'sk-live-...'` — git history-ə düşür. `.env` + config file + secrets manager istifadə edin.

**7. Circuit breaker state-ini memory-də saxlamaq**
Tək server üçün in-memory state işləyir, amma horizontal scaling-də hər server özünün circuit state-inə sahib olur — biri open, digəri closed. State Redis-də mərkəzləşdirilmiş saxlanılmalıdır ki, bütün server-lər eyni vəziyyəti görsün.

**8. Webhook endpoint-ini auth olmadan açmaq**
Hər kəs fake webhook göndərə bilər — fake ödəniş uğurlu göstərilə bilər. Webhook imzasını (HMAC signature) mütləq yoxlayın: `hash_equals(hash_hmac('sha256', $payload, $secret), $signature)`.
