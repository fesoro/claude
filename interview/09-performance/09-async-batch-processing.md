# Async and Batch Processing (Senior ⭐⭐⭐)

## İcmal

Async processing — HTTP request cycle-ından kənar, arxa planda icra olunan iş növüdür. Batch processing — çoxlu məlumatı dəstələrlə (batch) işləmə strategiyasıdır. Hər ikisi birlikdə tez-tez görünür: 100K email göndərmək, gecəlik hesabat yaratmaq, böyük file import etmək — bunlar nə real-time response-da ola bilər, nə də tək əməliyyat kimi. Düzgün arxitektura: queue + batch + idempotency.

## Niyə Vacibdir

E-commerce checkout-u düşünün: sifariş qeydi, ödəniş prosessing, email göndərmə, inventory azaltma, analitik event. Hamısı HTTP request-in içinə sıxışdırılsa — 5 saniyəlik response. Yalnız kritikləri sinxron, qalanları async queue-ya atılsa — 200ms response. Bu ayrımı etmək senior developer-in vacib vəzifəsidir.

## Əsas Anlayışlar

- **Async processing qatları:**
  - **Queue + Worker** — Job sistemi (Laravel Queue, RabbitMQ, SQS)
  - **Scheduled jobs** — Cron, artisan schedule
  - **Event-driven** — Domain event, listener async
  - **Stream processing** — Kafka, Kinesis (real-time batch)

- **Batch processing pattern-ləri:**
  - **Chunk-based:** 1000-lik partiyalarda işlə
  - **Cursor-based:** Memory-safe iteration
  - **Parallel batch:** Çoxlu worker eyni anda
  - **Fan-out:** 1 job → N sub-job-lara bölünür

- **Queue sistemləri:**
  - **Database queue** — sadə, ACID, yavaş
  - **Redis queue** — sürətli, amma persistence riski
  - **SQS** — managed, at-least-once delivery
  - **RabbitMQ** — routing, dead letter, competing consumers
  - **Kafka** — high throughput, replay, event stream

- **Job design prinsipləri:**
  - **Idempotent** — eyni job 2 dəfə işləsə eyni nəticə
  - **Small** — 1 məsuliyyət, tez bitir
  - **Retriable** — xəta gəlsə yenidən cəhd edilə bilər
  - **Atomic** — ya hamısı, ya heç biri

- **Delivery semantics:**
  - **At-most-once** — 1 dəfə cəhd, uğursuz olsa itirilir
  - **At-least-once** — retry var, duplicate mümkün (idempotency lazım)
  - **Exactly-once** — çətin, idempotent DB operasiya ilə simulate

- **Rate limiting + throttling:**
  - Queue-da istehsal sürətini məhdudlaşdır
  - External API limitinə uyğun işlə

## Praktik Baxış

**Laravel Queue — temel iş axını:**

```php
// Job yaratmaq
class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;           // max retry
    public int $backoff = 60;        // retry arasında saniyə
    public int $timeout = 30;        // max icra müddəti
    public bool $deleteWhenMissingModels = true; // model yoxdursa sil

    public function __construct(
        private readonly int $invoiceId,  // Model əvəzinə ID (serialization)
    ) {}

    public function handle(): void
    {
        $invoice = Invoice::with('client', 'items')->find($this->invoiceId);

        if (! $invoice) {
            return; // Model yoxdur, idempotent
        }

        if ($invoice->email_sent_at) {
            return; // Artıq göndərilib — idempotent check
        }

        Mail::to($invoice->client->email)->send(new InvoiceMail($invoice));

        $invoice->update(['email_sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Invoice email failed', [
            'invoice_id' => $this->invoiceId,
            'error' => $e->getMessage(),
        ]);
        // Alert, Slack notification...
    }
}

// Dispatch
SendInvoiceEmail::dispatch($invoice->id)
    ->onQueue('emails')
    ->delay(now()->addMinutes(2));
```

**Batch processing — Laravel Bus::batch():**

```php
// Böyük user list-i email göndərmək
class BulkEmailService
{
    public function sendNewsletter(string $subject, string $template): void
    {
        $jobs = [];

        // Chunk ilə memory-safe batch hazırla
        User::where('newsletter_subscribed', true)
            ->select('id')
            ->chunk(500, function ($users) use ($subject, $template, &$jobs) {
                foreach ($users as $user) {
                    $jobs[] = new SendNewsletterEmail($user->id, $subject, $template);
                }
            });

        // Batch göndər
        Bus::batch($jobs)
            ->name("newsletter:{$subject}")
            ->onQueue('newsletter')
            ->allowFailures() // 1 fail hamısı dayanmasın
            ->finally(function (Batch $batch) {
                Log::info('Newsletter completed', [
                    'total' => $batch->totalJobs,
                    'failed' => $batch->failedJobs,
                ]);
            })
            ->dispatch();
    }
}
```

**Parallel batch (fan-out):**

```php
// 1 ImportOrder job → N ProcessOrderLine job-lara bölünür
class ImportOrdersJob implements ShouldQueue
{
    public function handle(): void
    {
        $chunks = Order::cursor()->chunk(100);

        $batches = [];
        foreach ($chunks as $chunk) {
            $batches[] = new BatchProcessor($chunk->pluck('id')->toArray());
        }

        Bus::chain($batches)->dispatch(); // sequential
        // və ya Bus::batch($batches)->dispatch(); // parallel
    }
}
```

**Scheduled batch processing:**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Gecəlik cleanup
    $schedule->job(new CleanExpiredTokensJob())
        ->dailyAt('02:00')
        ->withoutOverlapping(30) // 30 dəq lock
        ->runInBackground()
        ->onFailure(function () {
            // alert göndər
        });

    // Saatlıq report
    $schedule->job(new GenerateHourlyReportJob())
        ->hourly()
        ->between('08:00', '22:00')
        ->onOneServer(); // distributed mühitdə yalnız 1 server
}
```

**Idempotency key pattern:**

```php
class ProcessPaymentJob implements ShouldQueue
{
    public function handle(): void
    {
        $idempotencyKey = "payment:{$this->orderId}:{$this->attempt}";

        // Redis lock ilə duplicate job qarşısı
        $lock = Cache::lock("job_lock:{$idempotencyKey}", 60);

        if (! $lock->get()) {
            Log::info("Job already running: {$idempotencyKey}");
            return;
        }

        try {
            // DB-də processed check
            if (Payment::where('idempotency_key', $idempotencyKey)->exists()) {
                return; // artıq işlənib
            }

            $result = $this->paymentGateway->charge($this->orderId);

            Payment::create([
                'order_id' => $this->orderId,
                'idempotency_key' => $idempotencyKey,
                'amount' => $result->amount,
                'status' => 'completed',
            ]);
        } finally {
            $lock->release();
        }
    }
}
```

**Rate-limited queue:**

```php
// config/queue.php
// Hər saniyədə max 10 email göndər
class SendEmailJob implements ShouldQueue
{
    public function middleware(): array
    {
        return [
            new RateLimited('emails'), // Laravel ThrottlesExceptions
            new WithoutOverlapping($this->userId),
        ];
    }
}

// AppServiceProvider
RateLimiter::for('emails', function (object $job) {
    return Limit::perSecond(10);
});
```

**Trade-offs:**
- Database queue — ACID, amma polling overhead
- Redis queue — sürətli, at-least-once, Redis restart = data loss
- SQS — managed, amma visibility timeout mürəkkəb
- Batch size: böyük batch → memory, kiçik batch → overhead
- Parallel workers → race condition riski

**Common mistakes:**
- Job-da Model serialize etmək (böyük payload)
- Idempotency olmadan retry etmək
- Timeout set etməmək (zombie job)
- Failed job-ları izlememək
- Queue-da transaction açmaq (rollback job fail etmir)

## Nümunələr

### Real Ssenari: 500K user-ə gecəlik email

```
Tələb: Gecə 02:00-da bütün aktiv user-lərə (500K) newsletter göndər.
Məhdudiyyət: SendGrid rate limit — saniyədə 1000 email

Arxitektura:
1. Cron 02:00 → GenerateNewsletterBatchJob dispatch
2. Job: User::cursor() ilə 1000-lik chunk-lar
3. Hər chunk → SendBatchEmailsJob (Bus::batch)
4. Rate limiter middleware: max 1000/s
5. Failed job → dead letter queue → alert

Nəticə: 500K email → 500 saniyə (~8 dəq)
Sabah monitoring: 499,234 delivered, 766 failed
```

### Kod Nümunəsi

```php
<?php

// app/Jobs/ProcessImportFileJob.php
class ProcessImportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 saat
    public int $tries = 1;      // retry yox (idempotency zəif)

    public function __construct(
        private readonly string $filePath,
        private readonly int $importId,
    ) {}

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);

        if ($import->status !== 'pending') {
            return; // Artıq işlənir/tamamlanıb
        }

        $import->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $jobs = [];
            $lineNumber = 0;

            // CSV-ni chunk-larla oxu
            $generator = $this->readCsvInChunks($this->filePath, 500);

            foreach ($generator as $chunk) {
                $jobs[] = new ProcessImportChunkJob(
                    importId: $this->importId,
                    lines: $chunk,
                    startLine: $lineNumber,
                );
                $lineNumber += count($chunk);
            }

            Bus::batch($jobs)
                ->name("import:{$this->importId}")
                ->onQueue('imports')
                ->allowFailures()
                ->finally(function (Batch $batch) use ($import) {
                    $import->update([
                        'status' => $batch->failedJobs > 0 ? 'partial' : 'completed',
                        'completed_at' => now(),
                        'total_processed' => $batch->processedJobs(),
                        'total_failed' => $batch->failedJobs,
                    ]);
                })
                ->dispatch();

        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function readCsvInChunks(string $path, int $chunkSize): Generator
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $chunk = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $chunk[] = array_combine($headers, $row);

                if (count($chunk) === $chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if ($chunk) {
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }
}
```

## Praktik Tapşırıqlar

1. **Idempotent job:** Eyni job 3 dəfə dispatch edilsə yalnız 1 dəfə işlənsin — Redis lock + DB check ilə implement et.

2. **Bus::batch:** 10K user-ə email göndərən batch implement et, `finally` callback-də tamamlanma statistikasını log et.

3. **Rate limiter:** `RateLimiter::for` ilə saniyədə 100 job limiti qoy, test üçün 500 job dispatch et.

4. **Dead letter queue:** Failed job-ların ayrı queue-ya keçməsini konfiqurasiya et, Horizon-da bu queue-yu izlə.

5. **Import benchmark:** 100K row CSV-ni 1 job vs 100 × 1K batch ilə müqayisə et — total vaxt, memory.

## Əlaqəli Mövzular

- `03-caching-layers.md` — Job nəticələrini cache-ləmək
- `06-memory-leak-detection.md` — Long-running job memory
- `07-garbage-collection.md` — Worker-də GC idarəetmə
- `05-connection-pool-tuning.md` — Worker connection pool
- `11-apm-tools.md` — Queue metrikalarını APM ilə izlə
