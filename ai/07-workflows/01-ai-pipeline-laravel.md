# Laravel-da Güclü AI Emal Pipeline-ları Qurmaq (Middle)

> **Oxucu:** Baş tərtibatçılar və arxitektlər  
> **Ön şərtlər:** Laravel növbələri (queues), Redis, əsas Claude API istifadəsi

---

## 1. Niyə AI Pipeline-ları Xüsusi Yanaşma Tələb Edir

Standart iş növbələri ya uğurla başa çatan, ya da açıq-aşkar uğursuz olan işlər üçün nəzərdə tutulub. AI emalı bu fərziyyəni bir neçə cəhətdən pozur:

- **Xarici API asılılığı** — Anthropic, OpenAI və ya yerli Ollama nümunəsindən asılısınız; bunlar yavaş, rate-limited və ya əlçatmaz ola bilər.
- **Qismən uğursuzluqlar** — bir iş API fasiləsindən əvvəl 10 sənəddən 7-sini emal edə bilər. Bütün işi yenidən başlatmaq token və pul xərcini artırır.
- **Qeyri-deterministik gecikmə** — eyni prompt server yükündən asılı olaraq 800 ms və ya 30 saniyə çəkə bilər.
- **Xərc artması** — düşünülməmiş retry məntiqi token xərclərini 5–10x artıra bilər.
- **Model deqradasiyası** — model yeniləməsi xüsusi istifadə halınız üçün keyfiyyəti səssizcə azalda bilər.

Burada təsvir edilən arxitektura AI çağırışlarını **etibarsız infrastruktur** kimi qəbul edir, paylanmış sistemlər üçün istifadə edilən eyni pattern-ləri (circuit breaker-lar, bulkhead-lər, fallback-lər) tətbiq edir və onları Laravel-in növbə ekosisteminə inteqrasiya edir.

---

## 2. Əsas Arxitektura

```
┌─────────────────────────────────────────────────────┐
│                   HTTP Sorğusu                       │
└──────────────────────┬──────────────────────────────┘
                       │
              ┌────────▼────────┐
              │  Pipeline Girişi │  (işləri göndər)
              └────────┬────────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
   ┌────▼────┐   ┌────▼────┐   ┌────▼────┐
   │ Yüksək  │   │ Normal  │   │  Toplu  │
   │ Növbə   │   │ Növbə   │   │  Növbə  │
   └────┬────┘   └────┬────┘   └────┬────┘
        │              │              │
        └──────────────┼──────────────┘
                       │
              ┌────────▼────────┐
              │  AIJob Əsası    │
              │  - Retry məntiqi│
              │  - Fallback     │
              │  - Circuit Bkr  │
              └────────┬────────┘
                       │
              ┌────────▼────────┐
              │  AI Provayder   │
              │  Claude → Ollama│
              └─────────────────┘
```

---

## 3. Əsas AIJob Sinifi

Təməl — retry, fallback və observability məsələlərini əhatə edən bir əsas iş sinifidir. Fərdi işlər bunu genişləndirir və yalnız `process()` metodunu həyata keçirir.

```php
<?php
// app/Jobs/AI/BaseAIJob.php

namespace App\Jobs\AI;

use App\Services\AI\CircuitBreaker;
use App\Services\AI\FallbackModelResolver;
use App\Services\AI\AIJobMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

abstract class BaseAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Fallback model retry-ləri daxil olmaqla cəmi cəhd sayı.
     * Hər "real" cəhd $fallbackModels sayı qədər model sınaya bilər.
     */
    public int $tries = 3;

    /**
     * Maksimum icra vaxtı. Claude uzun sənədlərdə yavaş ola bilər.
     */
    public int $timeout = 120;

    /**
     * Saniyə ilə backoff cədvəli.
     * Thundering herd-dən qaçınmaq üçün jitter ilə eksponensial.
     */
    public array $backoff = [10, 60, 300];

    /**
     * Bu cəhd üçün hazırda hansı modeldə olduğumuzu izləyir.
     */
    protected string $currentModel;

    /**
     * Sınanacaq modellərin sıralı siyahısı. Birincisi = əsas.
     */
    protected array $modelFallbackChain = [
        'claude-sonnet-4-5',
        'claude-haiku-4-5',
        'ollama/llama3',
    ];

    /**
     * Overlap açarını dəyişdirmək üçün alt siniflərdə ləğv edin.
     */
    protected function overlapKey(): ?string
    {
        return null;
    }

    public function middleware(): array
    {
        $middleware = [
            // Rate limit: növbə işçisi başına dəqiqədə maksimum 50 AI işi
            new ThrottlesExceptions(10, 1),
        ];

        if ($key = $this->overlapKey()) {
            // Eyni resursun dublikat emalının qarşısını al
            $middleware[] = (new WithoutOverlapping($key))
                ->releaseAfter(30)
                ->expireAfter(300);
        }

        return $middleware;
    }

    public function handle(
        CircuitBreaker $circuitBreaker,
        FallbackModelResolver $fallbackResolver,
        AIJobMetrics $metrics,
    ): void {
        $startTime = microtime(true);
        $this->currentModel = $this->modelFallbackChain[0];

        foreach ($this->modelFallbackChain as $model) {
            $this->currentModel = $model;

            // Cəhddən əvvəl circuit breaker-i yoxla
            if ($circuitBreaker->isOpen($model)) {
                logger()->warning("{$model} üçün circuit açıqdır, fallback sınanır", [
                    'job' => static::class,
                ]);
                continue;
            }

            try {
                $result = $this->process($model);

                // Uğuru qeyd et
                $circuitBreaker->recordSuccess($model);
                $metrics->record(static::class, $model, microtime(true) - $startTime, true);

                $this->onSuccess($result, $model);
                return;

            } catch (\App\Exceptions\AI\RateLimitException $e) {
                // Rate limited — növbəyə burax, uğursuzluq kimi sayma
                $metrics->record(static::class, $model, microtime(true) - $startTime, false, 'rate_limit');
                $this->release(60); // 60 saniyə gözlə
                return;

            } catch (\App\Exceptions\AI\ModelUnavailableException $e) {
                // Circuit breaker üçün uğursuzluğu qeyd et
                $circuitBreaker->recordFailure($model);
                $metrics->record(static::class, $model, microtime(true) - $startTime, false, 'unavailable');
                logger()->info("{$model} modeli əlçatmaz, fallback sınanır");
                continue;

            } catch (Throwable $e) {
                $circuitBreaker->recordFailure($model);
                $metrics->record(static::class, $model, microtime(true) - $startTime, false, $e::class);

                // Zəncirdəki son modeldə Laravel-in retry mexanizmini işə salmaq üçün yenidən at
                if ($model === end($this->modelFallbackChain)) {
                    throw $e;
                }
                continue;
            }
        }

        // Bütün modellər uğursuz oldu
        throw new \App\Exceptions\AI\AllModelsFailedException(
            "Fallback zəncirindəki bütün modellər uğursuz oldu: " . static::class
        );
    }

    /**
     * Faktiki AI emal məntiqini burada həyata keçirin.
     * @return mixed onSuccess()-ə ötürüləcək istənilən nəticə
     */
    abstract protected function process(string $model): mixed;

    /**
     * Uğurlu emaldan sonra çağırılır. Nəticələri idarə etmək üçün ləğv edin.
     */
    protected function onSuccess(mixed $result, string $model): void
    {
        // Defolt: heç nə etmə
    }

    /**
     * İş daimi uğursuzluğa uğradıqda çağırılır (bütün retry-lar tükəndi).
     */
    public function failed(Throwable $exception): void
    {
        logger()->error('AI İşi daimi uğursuz oldu', [
            'job'       => static::class,
            'exception' => $exception->getMessage(),
            'model'     => $this->currentModel ?? 'bilinmir',
        ]);

        $this->onFailed($exception);
    }

    protected function onFailed(Throwable $exception): void
    {
        // Xüsusi uğursuzluq idarəsi üçün alt siniflərdə ləğv edin
    }
}
```

### Konkret İş Nümunəsi

```php
<?php
// app/Jobs/AI/SummarizeDocumentJob.php

namespace App\Jobs\AI;

use App\Models\Document;
use App\Services\AI\ClaudeService;
use App\Events\DocumentSummarized;

class SummarizeDocumentJob extends BaseAIJob
{
    public function __construct(
        private readonly int $documentId,
        private readonly string $targetLength = 'medium',
    ) {
        $this->onQueue('ai-normal');
    }

    protected function overlapKey(): string
    {
        return "document-summarize-{$this->documentId}";
    }

    protected function process(string $model): string
    {
        $document = Document::findOrFail($this->documentId);

        $claude = app(ClaudeService::class);

        $summary = $claude->complete(
            model: $model,
            prompt: $this->buildPrompt($document->content, $this->targetLength),
            maxTokens: 1024,
        );

        $document->update([
            'summary'          => $summary,
            'summary_model'    => $model,
            'summarized_at'    => now(),
        ]);

        return $summary;
    }

    protected function onSuccess(mixed $result, string $model): void
    {
        broadcast(new DocumentSummarized($this->documentId, $model));
    }

    protected function onFailed(\Throwable $exception): void
    {
        Document::findOrFail($this->documentId)->update([
            'summary_status' => 'failed',
            'summary_error'  => $exception->getMessage(),
        ]);
    }

    private function buildPrompt(string $content, string $length): string
    {
        $lengths = [
            'short'  => '2-3 cümlə',
            'medium' => '1 abzas (4-6 cümlə)',
            'long'   => '3-4 abzas',
        ];

        $target = $lengths[$length] ?? $lengths['medium'];

        return "Aşağıdakı sənədi {$target} həcmində xülasə edin. Əsas tapıntılara və həyata keçirilə bilən fikirlərə diqqət yetirin.\n\n<document>\n{$content}\n</document>";
    }
}
```

---

## 4. 10.000 Sənədin Emalı — Toplu + Növbə Pipeline-ı

Böyük sənəd dəstlərinin emalı işin idarə edilə bilən hissələrə bölünməsini, tərəqqinin izlənməsini və qismən uğursuzluqların düzgün idarə edilməsini tələb edir.

```php
<?php
// app/Services/AI/DocumentBatchProcessor.php

namespace App\Services\AI;

use App\Jobs\AI\SummarizeDocumentJob;
use App\Models\Document;
use App\Models\ProcessingBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class DocumentBatchProcessor
{
    /**
     * Laravel Batch-lərdən istifadə edərək 10.000-ə qədər sənədi emal et.
     *
     * Arxitektura:
     * - 100-lük hissələrə böl (idarə edilə bilən parça ölçüsü)
     * - Hər hissə tərəqqi izləmə üçün Bus::batch() kimi göndərilir
     * - Ana batch bütün uşaq batch-ləri izləyir
     */
    public function processAll(array $filters = []): ProcessingBatch
    {
        $batch = ProcessingBatch::create([
            'status'     => 'pending',
            'filters'    => $filters,
            'started_at' => now(),
        ]);

        // Yaddaş səmərəliliyi üçün cursor istifadə et — heç vaxt 10k modeli birdən yükləmə
        $jobs = [];

        Document::query()
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->whereNull('summarized_at')
            ->orderBy('id')
            ->cursor()
            ->chunk(100)
            ->each(function ($chunk) use (&$jobs, $batch) {
                $chunkJobs = $chunk->map(fn($doc) => new SummarizeDocumentJob($doc->id))->toArray();
                $jobs[] = $chunkJobs;
            });

        // Düzləndir və tək izlənilən batch kimi göndər
        $busBatch = Bus::batch(array_merge(...$jobs))
            ->name("document-summarization-{$batch->id}")
            ->allowFailures() // Biri uğursuz olsa hamısını ləğv etmə
            ->onQueue('ai-batch')
            ->then(function (Batch $b) use ($batch) {
                $batch->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
            })
            ->catch(function (Batch $b, \Throwable $e) use ($batch) {
                logger()->error('Toplu emal xətası', [
                    'batch_id' => $batch->id,
                    'error'    => $e->getMessage(),
                ]);
            })
            ->finally(function (Batch $b) use ($batch) {
                $batch->update([
                    'total_jobs'   => $b->totalJobs,
                    'failed_jobs'  => $b->failedJobs,
                    'bus_batch_id' => $b->id,
                ]);
            })
            ->dispatch();

        $batch->update([
            'status'       => 'processing',
            'bus_batch_id' => $busBatch->id,
            'total_jobs'   => $busBatch->totalJobs,
        ]);

        return $batch;
    }

    /**
     * Batch üçün real vaxt tərəqqisini əldə et.
     */
    public function getProgress(ProcessingBatch $batch): array
    {
        $busBatch = Bus::findBatch($batch->bus_batch_id);

        if (! $busBatch) {
            return ['status' => 'bilinmir'];
        }

        return [
            'status'          => $batch->status,
            'total'           => $busBatch->totalJobs,
            'processed'       => $busBatch->processedJobs(),
            'failed'          => $busBatch->failedJobs,
            'pending'         => $busBatch->pendingJobs,
            'progress_percent'=> $busBatch->progress(),
            'estimated_remaining_seconds' => $this->estimateRemaining($busBatch),
        ];
    }

    private function estimateRemaining(\Illuminate\Bus\Batch $batch): ?int
    {
        if ($batch->processedJobs() === 0) {
            return null;
        }

        $elapsed  = now()->diffInSeconds($batch->createdAt);
        $rate     = $batch->processedJobs() / $elapsed; // saniyə başına işlər
        $remaining = $batch->pendingJobs;

        return $rate > 0 ? (int) ($remaining / $rate) : null;
    }
}
```

### Növbə Konfiqurasiyası

```php
// config/queue.php — əlaqəli bölmə

'connections' => [
    'redis' => [
        'driver'     => 'redis',
        'connection' => 'default',
        'queue'      => 'default',
        'retry_after' => 180, // 3 dəqiqə — yavaş AI cavablarını əhatə edir
        'block_for'  => null,
        'after_commit' => true, // Vacibdir: DB tranzaksiyasından sonra göndər
    ],
],

// config/horizon.php — növbə prioriteti və işçi ayrılması
'environments' => [
    'production' => [
        'ai-high' => [
            'connection'   => 'redis',
            'queue'        => ['ai-high'],
            'balance'      => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'tries'        => 3,
            'timeout'      => 120,
        ],
        'ai-normal' => [
            'connection'   => 'redis',
            'queue'        => ['ai-normal'],
            'balance'      => 'auto',
            'minProcesses' => 5,
            'maxProcesses' => 20,
            'tries'        => 3,
            'timeout'      => 120,
        ],
        'ai-batch' => [
            'connection'   => 'redis',
            'queue'        => ['ai-batch'],
            'balance'      => 'simple', // Toplu üçün sabit işçilər
            'processes'    => 10,
            'tries'        => 3,
            'timeout'      => 180,
        ],
    ],
],
```

---

## 5. AI API-ları üçün Circuit Breaker

Circuit breaker, AI provayderi çökdükdə kaskad uğursuzluqların qarşısını alır. Model üzrə uğursuzluq dərəcələrini izləyir və uğursuzluqlar həddini keçdikdə dövrəni "açır".

```php
<?php
// app/Services/AI/CircuitBreaker.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    /**
     * Vəziyyətlər: closed (normal), open (bloklama), half-open (sınaq)
     */
    private const STATE_CLOSED    = 'closed';
    private const STATE_OPEN      = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly int $failureThreshold = 5,    // açılmadan əvvəlki uğursuzluqlar
        private readonly int $successThreshold = 2,    // half-open-dan bağlanmaq üçün uğurlar
        private readonly int $openDurationSeconds = 60, // açıq qalma müddəti
        private readonly int $windowSeconds = 120,      // sayım üçün hərəkətli pəncərə
    ) {}

    public function isOpen(string $model): bool
    {
        return match ($this->getState($model)) {
            self::STATE_OPEN      => true,
            self::STATE_HALF_OPEN => false, // Bir sınaq sorğusuna icazə ver
            self::STATE_CLOSED    => false,
        };
    }

    public function recordSuccess(string $model): void
    {
        $state = $this->getState($model);

        if ($state === self::STATE_HALF_OPEN) {
            $successes = Cache::increment($this->successKey($model));

            if ($successes >= $this->successThreshold) {
                $this->close($model);
                Log::info("{$model} üçün circuit breaker BAĞLANDI");
            }
        }

        // Bağlı vəziyyətdə uğur zamanı uğursuzluq sayacını sıfırla
        if ($state === self::STATE_CLOSED) {
            Cache::forget($this->failureKey($model));
        }
    }

    public function recordFailure(string $model): void
    {
        $state = $this->getState($model);

        if ($state === self::STATE_OPEN) {
            return; // Artıq açıqdır, ediləcək bir şey yoxdur
        }

        if ($state === self::STATE_HALF_OPEN) {
            // Sınaq zamanı uğursuz oldu — dövrəni yenidən aç
            $this->open($model);
            Log::warning("{$model} üçün circuit breaker yenidən AÇILDI (half-open sınağı uğursuz oldu)");
            return;
        }

        // STATE_CLOSED: uğursuzluğu say
        $failures = Cache::increment(
            $this->failureKey($model),
            1,
            now()->addSeconds($this->windowSeconds),
        );

        if ($failures >= $this->failureThreshold) {
            $this->open($model);
            Log::warning("{$model} üçün circuit breaker {$failures} uğursuzluqdan sonra AÇILDI");
        }
    }

    public function getMetrics(string $model): array
    {
        return [
            'state'    => $this->getState($model),
            'failures' => (int) Cache::get($this->failureKey($model), 0),
            'successes'=> (int) Cache::get($this->successKey($model), 0),
        ];
    }

    private function getState(string $model): string
    {
        $state = Cache::get($this->stateKey($model), self::STATE_CLOSED);

        // Vaxt bitdikdən sonra open-dan half-open-a keçid
        if ($state === self::STATE_OPEN) {
            $openedAt = Cache::get($this->openedAtKey($model));
            if ($openedAt && now()->timestamp - $openedAt >= $this->openDurationSeconds) {
                $this->halfOpen($model);
                return self::STATE_HALF_OPEN;
            }
        }

        return $state;
    }

    private function open(string $model): void
    {
        Cache::forever($this->stateKey($model), self::STATE_OPEN);
        Cache::forever($this->openedAtKey($model), now()->timestamp);
        Cache::forget($this->successKey($model));
    }

    private function halfOpen(string $model): void
    {
        Cache::forever($this->stateKey($model), self::STATE_HALF_OPEN);
        Cache::forget($this->successKey($model));
    }

    private function close(string $model): void
    {
        Cache::forever($this->stateKey($model), self::STATE_CLOSED);
        Cache::forget($this->failureKey($model));
        Cache::forget($this->successKey($model));
        Cache::forget($this->openedAtKey($model));
    }

    private function stateKey(string $model): string    { return "cb:state:{$model}"; }
    private function failureKey(string $model): string  { return "cb:failures:{$model}"; }
    private function successKey(string $model): string  { return "cb:successes:{$model}"; }
    private function openedAtKey(string $model): string { return "cb:opened_at:{$model}"; }
}
```

---

## 6. Broadcasting ilə Tərəqqi İzləmə

Uzun müddətli pipeline-lar üçün müştərilərə real vaxt tərəqqi yeniləmələri lazımdır.

```php
<?php
// app/Events/AIJobProgress.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class AIJobProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $batchId,
        public readonly int    $total,
        public readonly int    $processed,
        public readonly int    $failed,
        public readonly string $status,
        public readonly ?string $currentItem = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("batch.{$this->batchId}")];
    }

    public function broadcastAs(): string
    {
        return 'progress';
    }

    public function broadcastWith(): array
    {
        return [
            'total'     => $this->total,
            'processed' => $this->processed,
            'failed'    => $this->failed,
            'percent'   => $this->total > 0 ? round(($this->processed / $this->total) * 100) : 0,
            'status'    => $this->status,
            'item'      => $this->currentItem,
        ];
    }
}
```

```php
<?php
// app/Jobs/AI/TrackableAIJob.php — tərəqqi hesabatı üçün mixin

namespace App\Jobs\AI;

trait ReportsProgress
{
    protected function reportProgress(
        string $batchId,
        int    $total,
        int    $processed,
        int    $failed,
        string $status = 'processing',
        ?string $currentItem = null,
    ): void {
        broadcast(new \App\Events\AIJobProgress(
            batchId: $batchId,
            total: $total,
            processed: $processed,
            failed: $failed,
            status: $status,
            currentItem: $currentItem,
        ));
    }
}
```

---

## 7. Uğursuz İş İdarəsi və Dead Letter Queue

```php
<?php
// app/Services/AI/DeadLetterQueue.php

namespace App\Services\AI;

use App\Models\AIFailedJob;
use Illuminate\Queue\Events\JobFailed;

class DeadLetterQueue
{
    /**
     * EventServiceProvider vasitəsilə qeydiyyat:
     * Queue::failing(fn(JobFailed $e) => app(DeadLetterQueue::class)->handle($e));
     */
    public function handle(JobFailed $event): void
    {
        $payload = json_decode($event->job->getRawBody(), true);

        AIFailedJob::create([
            'job_class'    => $payload['displayName'] ?? 'bilinmir',
            'job_data'     => $payload,
            'queue'        => $event->job->getQueue(),
            'connection'   => $event->connectionName,
            'exception'    => $event->exception->getMessage(),
            'exception_class' => $event->exception::class,
            'failed_at'    => now(),
            'can_retry'    => $this->isRetryable($event->exception),
        ]);
    }

    private function isRetryable(\Throwable $e): bool
    {
        // Yenidən cəhd etmə: doğrulama xətaları, tapılmayan, ödəniş problemləri
        $nonRetryable = [
            \App\Exceptions\AI\InvalidPromptException::class,
            \App\Exceptions\AI\BillingException::class,
        ];

        return ! in_array($e::class, $nonRetryable, true);
    }

    /**
     * Yenidən cəhd edilə bilən uğursuz işləri yenidən oynada — əl ilə və ya cədvəldə işlədilə bilər.
     */
    public function replayFailed(int $limit = 100): int
    {
        $replayed = 0;

        AIFailedJob::where('can_retry', true)
            ->where('replayed_at', null)
            ->where('failed_at', '>', now()->subDay())
            ->limit($limit)
            ->get()
            ->each(function (AIFailedJob $failedJob) use (&$replayed) {
                try {
                    $jobClass = $failedJob->job_class;
                    $command  = unserialize($failedJob->job_data['data']['command']);

                    dispatch($command);

                    $failedJob->update(['replayed_at' => now()]);
                    $replayed++;

                } catch (\Throwable $e) {
                    logger()->error('AI işini yenidən oynamaq uğursuz oldu', [
                        'id'    => $failedJob->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return $replayed;
    }
}
```

```php
// AI uğursuz işlər cədvəli üçün miqrasiya
// database/migrations/xxxx_create_ai_failed_jobs_table.php

Schema::create('ai_failed_jobs', function (Blueprint $table) {
    $table->id();
    $table->string('job_class');
    $table->json('job_data');
    $table->string('queue');
    $table->string('connection');
    $table->text('exception');
    $table->string('exception_class');
    $table->boolean('can_retry')->default(true);
    $table->timestamp('failed_at');
    $table->timestamp('replayed_at')->nullable();
    $table->timestamps();

    $table->index(['can_retry', 'failed_at', 'replayed_at']);
    $table->index('exception_class');
});
```

---

## 8. Prioritet Növbə Strategiyası

| Növbə      | İstifadə Halı                         | İşçilər | Vaxt Aşımı |
|------------|---------------------------------------|---------|------------|
| `ai-high`  | İnteraktiv istifadəçi sorğuları (chat)| 10      | 30s        |
| `ai-normal`| Arxa planda zənginləşdirmə            | 20      | 120s       |
| `ai-batch` | Kütləvi emal (10k sənəd)              | 10      | 180s       |
| `ai-low`   | Analitika, təcili olmayan yenidən emal| 5       | 300s       |

```php
// Kontekstə əsasən uyğun növbəyə göndər
class AIQueueRouter
{
    public function dispatch(BaseAIJob $job, string $context = 'normal'): void
    {
        $queue = match ($context) {
            'interactive' => 'ai-high',
            'batch'       => 'ai-batch',
            'background'  => 'ai-low',
            default       => 'ai-normal',
        };

        dispatch($job->onQueue($queue));
    }
}
```

---

## 9. Əsas Dizayn Prinsipləri

1. **İdempotentlik** — Hər AI işi çoxlu dəfə təhlükəsiz işlənə bilməlidir. `WithoutOverlapping` istifadə edin və `processed_at` zaman damğalarını izləyin.
2. **Qismən uğursuzluğa dözümlülük** — Bus::batch()-də `allowFailures()` istifadə edin. Heç vaxt bir pis sənədin 10k-lıq işi bloklamasına icazə verməyin.
3. **Xərclərə uyğun retry-lar** — Eksponensial backoff ödənişli API-ların daim yüklənməsinin qarşısını alır. Rate limit istisnaları release etməlidir, retry deyil.
4. **Model fallback** — Həmişə yerli bir fallback-iniz olsun (Ollama). Hətta aşağı keyfiyyətli çıxış iş uğursuzluğundan yaxşıdır.
5. **Observability birinci** — Hər çağırışda istifadə olunan modeli, gecikməni və token sayını qeyd edin. Ölçmədiyiniz şeyi optimallaşdıra bilməzsiniz.
