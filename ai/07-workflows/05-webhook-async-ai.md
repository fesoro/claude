# 36 — Asinxron AI Pattern-ləri: Webhook-lar, Polling və SSE

> **Oxucu:** Baş tərtibatçılar və arxitektlər  
> **Problem:** AI əməliyyatları adətən 5–120 saniyə çəkir. Sinxron HTTP sorğuları bunu idarə edə bilmir.

---

## 1. Sinxron AI ilə Əsas Problem

HTTP-nin əksər infrastrukturlarda (yük balanslaşdırıcılar, proksilər, CDN-lər) faktiki vaxt aşımı ~30 saniyədir. AI çağırışları bunu tez-tez keçir. Həll **asinxron arxitektura**dır:

1. Müştəri iş göndərir → dərhal iş ID alır (202 Accepted)
2. Server asinxron şəkildə emal edir
3. Müştəri tamamlanma haqqında öyrənir: webhook, polling və ya SSE vasitəsilə

| Pattern  | Ən Uyğun Hal                      | Müştəri Mürəkkəbliyi | Server Mürəkkəbliyi |
|----------|-----------------------------------|----------------------|---------------------|
| Webhook  | Server-server inteqrasiyaları     | Aşağı (yalnız al)   | Orta                |
| Polling  | Sadə müştərilər, mobil tətbiqlər  | Orta                 | Aşağı               |
| SSE      | Brauzer UX, canlı tərəqqi         | Aşağı                | Aşağı               |

---

## 2. İş Göndərmə Endpoint-i

Giriş nöqtəsi müştərinin hansı bildiriş pattern-ini seçdiyindən asılı olmayaraq ardıcıldır. Əsas odur ki, iş ID ilə dərhal qayıdılsın.

```php
<?php
// app/Http/Controllers/AI/JobController.php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\SubmitJobRequest;
use App\Models\AIJob;
use App\Jobs\AI\ProcessDocumentJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class JobController extends Controller
{
    /**
     * AI emal işi göndər.
     *
     * Dərhal 202 Accepted qaytarır.
     * Müştəri /jobs/{id} pollinga edə və ya webhook URL-i təmin edə bilər.
     */
    public function submit(SubmitJobRequest $request): JsonResponse
    {
        // İdempotentlik: eyni açarla mövcud işi yoxla
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $existing = AIJob::where('idempotency_key', $idempotencyKey)
                ->where('created_at', '>', now()->subHours(24))
                ->first();

            if ($existing) {
                // Sanki biz onu yeni yaratmışıq kimi eyni cavabı qaytar
                return response()->json($existing->toStatusArray(), 200)
                    ->header('X-Idempotent-Replayed', 'true');
            }
        }

        $job = AIJob::create([
            'id'              => Str::uuid(),
            'type'            => $request->type,
            'payload'         => $request->input(),
            'status'          => 'pending',
            'webhook_url'     => $request->webhook_url,
            'webhook_secret'  => $request->webhook_url ? Str::random(32) : null,
            'idempotency_key' => $idempotencyKey,
            'user_id'         => auth()->id(),
            'tenant_id'       => auth()->user()->tenant_id,
            'expires_at'      => now()->addDays(7),
        ]);

        // Faktiki işi göndər — bu dərhal qayıdır
        ProcessDocumentJob::dispatch($job)->onQueue('ai-normal');

        return response()->json([
            'job_id'     => $job->id,
            'status'     => 'pending',
            'status_url' => route('ai.jobs.status', $job->id),
            'created_at' => $job->created_at->toIso8601String(),
        ], 202);
    }

    /**
     * Cari iş statusunu al (polling müştəriləri üçün).
     */
    public function status(AIJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        return response()->json($job->toStatusArray());
    }
}
```

```php
<?php
// app/Models/AIJob.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AIJob extends Model
{
    use HasUuids;

    protected $casts = [
        'payload'    => 'array',
        'result'     => 'array',
        'metadata'   => 'array',
        'expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at'=> 'datetime',
    ];

    public function toStatusArray(): array
    {
        return [
            'job_id'        => $this->id,
            'status'        => $this->status, // pending|processing|completed|failed
            'progress'      => $this->progress,
            'result'        => $this->status === 'completed' ? $this->result : null,
            'error'         => $this->status === 'failed' ? $this->error_message : null,
            'created_at'    => $this->created_at->toIso8601String(),
            'started_at'    => $this->started_at?->toIso8601String(),
            'completed_at'  => $this->completed_at?->toIso8601String(),
            'estimated_completion' => $this->estimatedCompletion(),
        ];
    }

    private function estimatedCompletion(): ?string
    {
        if ($this->status !== 'processing' || ! $this->started_at) {
            return null;
        }

        // İş tipi ortalama gecikməsinə əsaslanan sadə evristika
        $averages = [
            'summarize' => 15,
            'translate' => 20,
            'analyze'   => 30,
            'generate'  => 45,
        ];

        $avgSeconds = $averages[$this->type] ?? 30;
        return $this->started_at->addSeconds($avgSeconds)->toIso8601String();
    }
}
```

---

## 3. Retry ilə Webhook Çatdırılması

Webhook-lar server-server inteqrasiyaları üçün üstünlük verilən pattern-dir. Kritik tələblər:

1. **İmzalanmış yüklər** — alıcı həqiqiliyi yoxlaya bilər
2. **Backoff ilə retry** — hədəf server müvəqqəti olaraq əlçatmaz ola bilər
3. **İdempotentlik** — webhook-lar birdən çox dəfə çatdırıla bilər
4. **Vaxt aşımı idarəsi** — yavaş alıcıları bloklama

```php
<?php
// app/Jobs/AI/DeliverWebhookJob.php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 7;
    public int $timeout = 30;

    // Eksponensial backoff: 1d, 5d, 15d, 30d, 1s, 2s, 6s
    public array $backoff = [60, 300, 900, 1800, 3600, 7200, 21600];

    public function __construct(
        private readonly string $jobId,
        private readonly string $event,
    ) {}

    public function handle(): void
    {
        $aiJob = AIJob::findOrFail($this->jobId);

        if (! $aiJob->webhook_url) {
            return;
        }

        $payload = $this->buildPayload($aiJob);
        $signature = $this->sign($payload, $aiJob->webhook_secret);

        $delivery = WebhookDelivery::create([
            'ai_job_id'  => $aiJob->id,
            'event'      => $this->event,
            'attempt'    => $this->attempts(),
            'url'        => $aiJob->webhook_url,
            'payload'    => $payload,
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type'        => 'application/json',
                'X-Webhook-Event'     => $this->event,
                'X-Webhook-Signature' => "sha256={$signature}",
                'X-Webhook-Delivery'  => $delivery->id,
                'X-Webhook-Timestamp' => now()->timestamp,
            ])
            ->timeout(15)
            ->post($aiJob->webhook_url, $payload);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body'   => substr($response->body(), 0, 1000),
                'delivered_at'    => now(),
                'success'         => $response->successful(),
            ]);

            if (! $response->successful()) {
                // 4xx = retry etmə (müştəri xətası), 5xx = retry et
                if ($response->clientError()) {
                    $this->fail(new \RuntimeException(
                        "Webhook {$response->status()} ilə rədd edildi: {$response->body()}"
                    ));
                    return;
                }

                // 5xx — retry tetiklemek üçün at
                throw new \RuntimeException("Webhook server xətası: {$response->status()}");
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $delivery->update(['error' => $e->getMessage()]);
            throw $e; // Retry tetikle
        }
    }

    private function buildPayload(AIJob $job): array
    {
        return [
            'event'      => $this->event,
            'job_id'     => $job->id,
            'status'     => $job->status,
            'result'     => $job->result,
            'error'      => $job->error_message,
            'created_at' => $job->created_at->toIso8601String(),
            'completed_at'=> $job->completed_at?->toIso8601String(),
        ];
    }

    private function sign(array $payload, string $secret): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
```

```php
<?php
// app/Http/Controllers/AI/WebhookVerificationTrait.php
// Gələn webhook-ları yoxlamaq üçün müştəri tətbiqində istifadə edin

trait VerifiesWebhookSignature
{
    protected function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        $timestamp  = $request->header('X-Webhook-Timestamp');

        if (! $signature || ! $timestamp) {
            return false;
        }

        // 5 dəqiqədən köhnə webhook-ları rədd et (replay hücumunun qarşısını al)
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
```

---

## 4. Polling API

Polling sadədir və hər yerdə işləyir, lakin lazımsız yük yaradır. Tədricən artan backoff tövsiyələri ilə azaldın:

```php
<?php
// app/Http/Controllers/AI/PollingController.php

namespace App\Http\Controllers\AI;

use App\Models\AIJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollingController extends Controller
{
    /**
     * Polling üçün optimallaşdırılmış status endpoint-i.
     *
     * Status ilə HTTP 200 qaytarır, həmçinin növbəti polling vaxtı haqqında ipucları.
     * Bant genişliyini azaltmaq üçün şərti GET-lər üçün ETag istifadə edir.
     */
    public function status(Request $request, AIJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        $etag = md5($job->status . $job->updated_at->timestamp);

        // Şərti GET — heç nə dəyişməyibsə 304 qaytar
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        $status = $job->toStatusArray();

        // Müştəri davranışını idarə etmək üçün polling ipucları əlavə et
        $status['_polling'] = $this->pollingHints($job);

        return response()
            ->json($status)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'no-store');
    }

    private function pollingHints(AIJob $job): array
    {
        if (in_array($job->status, ['completed', 'failed'])) {
            return [
                'should_poll'       => false,
                'reason'            => 'İş terminal vəziyyətdədir',
            ];
        }

        // İşin nə qədər işlədiyinə əsasən backoff tövsiyəsi
        $ageSeconds = $job->created_at->diffInSeconds(now());

        $recommendedIntervalSeconds = match (true) {
            $ageSeconds < 30  => 2,   // İlk 30s-də hər 2s-dən bir polling
            $ageSeconds < 120 => 5,   // İlk 2 dəqiqədə hər 5s-dən bir
            $ageSeconds < 300 => 15,  // İlk 5 dəqiqədə hər 15s-dən bir
            default           => 30,  // Bundan sonra hər 30s-dən bir
        };

        return [
            'should_poll'                => true,
            'recommended_interval_seconds' => $recommendedIntervalSeconds,
            'next_poll_at'               => now()->addSeconds($recommendedIntervalSeconds)->toIso8601String(),
        ];
    }

    /**
     * Toplu status endpoint-i — bir sorğuda çoxlu işi yoxla.
     * Müştəri tərəfi polling sorğularını 80% azaldır.
     */
    public function batchStatus(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'job_ids'   => 'required|array|max:50',
            'job_ids.*' => 'uuid',
        ])['job_ids'];

        $jobs = AIJob::whereIn('id', $ids)
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy('id');

        return response()->json([
            'jobs' => collect($ids)->mapWithKeys(fn($id) => [
                $id => isset($jobs[$id]) ? $jobs[$id]->toStatusArray() : ['error' => 'tapılmadı'],
            ]),
        ]);
    }
}
```

---

## 5. SSE Tərəqqi Endpoint-i

Server-Sent Events brauzer müştəriləri üçün ən yaxşı UX-dir. WebSocket-lərdən daha sadədir (HTTP, tek yönlü) və proksilərdən keçir.

```php
<?php
// app/Http/Controllers/AI/SSEController.php

namespace App\Http\Controllers\AI;

use App\Models\AIJob;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SSEController extends Controller
{
    /**
     * AI işi üçün real vaxt tərəqqi yeniləmələrini axıt.
     *
     * Laravel-in daxili StreamedResponse SSE-ni yerli olaraq idarə edir.
     * Əlavə paket tələb olunmur.
     */
    public function stream(Request $request, AIJob $job): StreamedResponse
    {
        $this->authorize('view', $job);

        // SSE başlıqları
        return response()->stream(function () use ($job) {
            // Real vaxt çatdırılması üçün çıxış buferizasiyasını söndür
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $lastEventId = request()->header('Last-Event-ID');
            $pollInterval = 1; // DB yoxlamaları arasında saniyə
            $maxDuration  = 300; // maksimum 5 dəqiqə
            $startTime    = time();

            while (true) {
                // Vaxt aşımı qoruması
                if (time() - $startTime > $maxDuration) {
                    $this->sendSSEEvent('timeout', ['message' => 'Axın vaxtı bitdi. Zəhmət olmasa yenidən qoşulun.']);
                    break;
                }

                // Müştəri bağlantısı kəsildi
                if (connection_aborted()) {
                    break;
                }

                // DB-dən işi yenilə
                $job->refresh();

                $data = $job->toStatusArray();
                $data['server_time'] = now()->toIso8601String();

                $this->sendSSEEvent('progress', $data, $job->updated_at->timestamp);

                // Terminal vəziyyətlər — axını bağla
                if (in_array($job->status, ['completed', 'failed'])) {
                    $this->sendSSEEvent('done', ['final_status' => $job->status]);
                    break;
                }

                sleep($pollInterval);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no', // Nginx buferizasiyasını söndür
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Token-token AI çıxışını axıt (chat/generasiya UX üçün).
     */
    public function streamGeneration(Request $request): StreamedResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:10000',
        ]);

        return response()->stream(function () use ($request) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $claude = app(\App\Services\AI\ClaudeService::class);

            // Claude-dan gəldikləri kimi tokenləri axıt
            $claude->stream(
                prompt: $request->input('prompt'),
                model: 'claude-sonnet-4-5',
                onToken: function (string $token) {
                    $this->sendSSEEvent('token', ['text' => $token]);
                    flush();
                },
                onComplete: function (array $usage) {
                    $this->sendSSEEvent('complete', ['usage' => $usage]);
                },
            );
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sendSSEEvent(string $event, array $data, ?int $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        flush();
    }
}
```

---

## 6. İdempotentlik Tətbiqi

İdempotentlik müştərilər sorğuları yenidən cəhd etdikdə (şəbəkə xətaları, vaxt aşımları) ikiqat emalın qarşısını alır.

```php
<?php
// app/Http/Middleware/IdempotencyMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * İdempotentlik açarı davranışı:
     * 1. İlk sorğu: normal emal et, cavabı keşdə saxla
     * 2. Eyni açar: keşdəki cavabı qaytar (yenidən emal etmə)
     * 3. Açar 24 saatdan sonra expires
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // Açar formatını yoxla
        if (strlen($key) > 255 || ! preg_match('/^[\w\-]+$/', $key)) {
            return response()->json(['error' => 'Etibarsız Idempotency-Key formatı'], 422);
        }

        $cacheKey = "idempotency:{$request->user()?->id}:{$key}";

        // Hazırda bu açarı emal edib etmədiyimizi yoxla (eyni vaxtlı sorğular)
        $lockKey = "{$cacheKey}:lock";
        if (Cache::has($lockKey)) {
            return response()->json([
                'error'   => 'Bu Idempotency-Key ilə sorğu hal-hazırda emal edilir.',
                'status'  => 'processing',
            ], 409);
        }

        // Keşdəki cavabı qaytar (varsa)
        if ($cached = Cache::get($cacheKey)) {
            return response()
                ->json($cached['body'], $cached['status'])
                ->header('X-Idempotent-Replayed', 'true')
                ->header('X-Idempotent-Replayed-At', $cached['cached_at']);
        }

        // Emal kilidini al
        Cache::put($lockKey, true, now()->addMinutes(5));

        try {
            $response = $next($request);

            // Uğurlu cavabları keşdə saxla (2xx, 4xx — yenidən cəhd edilə bilən 5xx deyil)
            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'body'      => json_decode($response->getContent(), true),
                    'status'    => $response->getStatusCode(),
                    'cached_at' => now()->toIso8601String(),
                ], now()->addHours(24));
            }

            return $response;

        } finally {
            Cache::forget($lockKey);
        }
    }
}
```

```php
// bootstrap/app.php-də qeydiyyat (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\IdempotencyMiddleware::class,
    ]);
})
```

---

## 7. Tam Marşrutlar və İnteqrasiya

```php
// routes/api.php

use App\Http\Controllers\AI\{JobController, PollingController, SSEController};

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // İş göndərmə
    Route::post('/ai/jobs', [JobController::class, 'submit']);
    Route::get('/ai/jobs/{job}', [JobController::class, 'status']);

    // Polling
    Route::get('/ai/jobs/{job}/poll', [PollingController::class, 'status']);
    Route::post('/ai/jobs/batch-status', [PollingController::class, 'batchStatus']);

    // SSE axınları
    Route::get('/ai/jobs/{job}/stream', [SSEController::class, 'stream']);
    Route::get('/ai/stream/generate', [SSEController::class, 'streamGeneration']);
});
```

---

## 8. Müştəri Tərəfi İstifadə Nümunəsi (JavaScript)

```javascript
// Polling pattern-i
async function pollJobStatus(jobId) {
  let interval = 2000;
  
  while (true) {
    const res = await fetch(`/api/v1/ai/jobs/${jobId}/poll`, {
      headers: { 'If-None-Match': lastEtag }
    });

    if (res.status === 304) {
      // Heç nə dəyişməyib — keşdəki məlumatı istifadə et, tövsiyə olunan intervalı gözlə
    } else {
      const data = await res.json();
      lastEtag = res.headers.get('ETag');
      
      if (data.status === 'completed') return data.result;
      if (data.status === 'failed') throw new Error(data.error);
      
      interval = (data._polling?.recommended_interval_seconds ?? 5) * 1000;
    }

    await new Promise(r => setTimeout(r, interval));
  }
}

// SSE pattern-i (daha yaxşı UX)
function streamJobProgress(jobId, onProgress) {
  const es = new EventSource(`/api/v1/ai/jobs/${jobId}/stream`);
  
  es.addEventListener('progress', (e) => {
    onProgress(JSON.parse(e.data));
  });
  
  es.addEventListener('done', (e) => {
    es.close();
  });
  
  es.onerror = () => {
    // Last-Event-ID başlığından istifadə edərək avtomatik yenidən qoşulur
    // Brauzer bunu SSE üçün avtomatik idarə edir
  };
  
  return es;
}
```

---

## 9. İnfrastruktur Mülahizələri

- **Nginx:** SSE endpoint-ləri üçün `proxy_read_timeout 300;` və `proxy_buffering off;` qurun.
- **Yük balanslaşdırıcıları:** SSE bağlantıları üçün yapışqan sessiyalar və ya birbaşa yönləndirmə istifadə edin.
- **Redis:** Pub/sub SSE üçün DB polling-i əvəz edə bilər — hər saniyə DB-ni sorğu etmək əvəzinə iş yeniləmə kanalına abunə olun.
- **İdempotentlik TTL:** 24 saat standartdır. Stripe 24 saat istifadə edir; retry pəncərənizə əsasən seçin.
