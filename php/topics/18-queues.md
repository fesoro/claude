# Queues (Növbələr) - Tam Hərtərəfli Bələdçi

## 1. Queue Nədir?

**Queue (növbə)** — FIFO (First In, First Out) prinsipilə işləyən data strukturudur. Message queue kontekstində, ağır və ya vaxt tələb edən əməliyyatları background-da asinxron emal etmək üçün istifadə olunur.

### Niyə Queue Lazımdır?

```
Queue olmadan (sinxron):
User -> Register -> Send Welcome Email (2s) -> Create Profile (1s) -> Subscribe Newsletter (1s)
                                                                    -> Response (4s total)

Queue ilə (asinxron):
User -> Register -> Dispatch Jobs to Queue -> Response (50ms)
                                    |
                    Background Worker: Send Welcome Email
                    Background Worker: Create Profile  
                    Background Worker: Subscribe Newsletter
```

**Əsas faydaları:**
- **Sürətli response time** — istifadəçi gözləmir
- **Reliability** — job uğursuz olsa, retry olunur
- **Scalability** — daha çox worker əlavə et
- **Decoupling** — komponentlər müstəqildir
- **Load leveling** — pik yükü tarazlayır
- **Rate limiting** — xarici API-lərə nəzakətli müraciət

---

## 2. Synchronous vs Asynchronous Processing

### Synchronous (Sinxron)

*Synchronous (Sinxron) üçün kod nümunəsi:*
```php
// Sinxron - istifadəçi hər şeyin bitməsini gözləyir
class RegisterController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());       // 50ms
        
        Mail::to($user)->send(new WelcomeEmail($user));    // 2000ms (SMTP)
        $user->createProfile();                             // 100ms
        $this->subscribeToNewsletter($user);                // 500ms
        $this->syncWithCRM($user);                          // 1000ms
        
        // Total: ~3650ms - istifadəçi 3.6 saniyə gözləyir!
        return response()->json(['user' => $user], 201);
    }
}
```

### Asynchronous (Asinxron)

*Asynchronous (Asinxron) üçün kod nümunəsi:*
```php
// Asinxron - job-lar queue-ya göndərilir, istifadəçi gözləmir
class RegisterController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());       // 50ms

        // Queue-ya göndər (hər biri ~5ms)
        SendWelcomeEmail::dispatch($user);
        CreateUserProfile::dispatch($user);
        SubscribeToNewsletter::dispatch($user);
        SyncWithCRM::dispatch($user);

        // Total: ~70ms - istifadəçi dərhal cavab alır!
        return response()->json(['user' => $user], 201);
    }
}
```

---

## 3. Laravel Queue System

### 3.1 Queue Connections vs Queue Names

*3.1 Queue Connections vs Queue Names üçün kod nümunəsi:*
```php
// config/queue.php

// Connection = queue driver (redis, database, sqs, etc.)
// Queue = connection daxilindəki ayrı queue (default, high, low, emails)

'connections' => [
    // Connection: redis
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',      // Default queue adı
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],

    // Connection: database
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],

    // Connection: sqs
    'sqs' => [
        'driver' => 'sqs',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
        'queue' => env('SQS_QUEUE', 'default'),
        'suffix' => env('SQS_SUFFIX'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'after_commit' => false,
    ],
],
```

*'after_commit' => false, üçün kod nümunəsi:*
```php
// Müxtəlif queue-lara dispatch
SendEmail::dispatch($user)->onQueue('emails');
GenerateReport::dispatch($report)->onQueue('reports');
ProcessImage::dispatch($image)->onQueue('media');

// Worker fərqli queue-ları dinləyir (prioritet sırası ilə)
// php artisan queue:work redis --queue=high,emails,default,low
```

### 3.2 Queue Drivers

| Driver | İstifadə | Üstünlük | Mənfi |
|--------|----------|----------|-------|
| `sync` | Development, test | Job dərhal icra olunur | Asinxron deyil |
| `database` | Kiçik layihə | Əlavə service lazım deyil | Yavaş, DB yükü artır |
| `redis` | Production (əksər hallar) | Çox sürətli, Horizon dəstəyi | Redis server lazım |
| `beanstalkd` | Production | Yüngül, sürətli | Məhdud feature-lər |
| `sqs` | AWS infrastrukturu | Managed, scalable | AWS bağımlılığı, latency |
| `rabbitmq` | Enterprise, microservice | Güclü routing, reliability | Complex setup |

*həll yanaşmasını üçün kod nümunəsi:*
```bash
# Database driver üçün migration
php artisan queue:table
php artisan migrate

# Failed jobs table
php artisan queue:failed-table
php artisan migrate

# Job batches table
php artisan queue:batches-table
php artisan migrate
```

### 3.3 Job Yaratmaq

*3.3 Job Yaratmaq üçün kod nümunəsi:*
```bash
php artisan make:job ProcessOrder
php artisan make:job SendWelcomeEmail
php artisan make:job GenerateInvoicePdf
```

*php artisan make:job GenerateInvoicePdf üçün kod nümunəsi:*
```php
// app/Jobs/ProcessOrder.php
namespace App\Jobs;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksimum retry sayı
     */
    public int $tries = 3;

    /**
     * Retry-lər arası gözləmə (saniyə)
     */
    public array $backoff = [30, 60, 120]; // 30s, 60s, 120s

    /**
     * Job timeout (saniyə)
     */
    public int $timeout = 120;

    /**
     * Nə vaxt retry-dan vaz keçmək lazımdır
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(6);
    }

    /**
     * Maximum exceptions before failing
     */
    public int $maxExceptions = 3;

    public function __construct(
        public Order $order
    ) {}

    /**
     * Job middleware-ləri
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->order->id),
        ];
    }

    /**
     * Job-un əsas logic-i
     */
    public function handle(PaymentService $paymentService): void
    {
        // Payment emal et
        $result = $paymentService->charge(
            $this->order->payment_method_id,
            $this->order->total
        );

        if ($result->successful()) {
            $this->order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' => $result->transactionId(),
            ]);

            // Növbəti job-ları dispatch et
            SendOrderConfirmation::dispatch($this->order);
            UpdateInventory::dispatch($this->order);
        } else {
            // Payment uğursuz - exception throw edərək retry trigger edirik
            throw new PaymentFailedException($result->error());
        }
    }

    /**
     * Bütün retry-lar uğursuz olduqda
     */
    public function failed(\Throwable $exception): void
    {
        // Admin-ə bildiriş göndər
        Log::error("Order #{$this->order->id} processing failed permanently", [
            'error' => $exception->getMessage(),
            'order' => $this->order->toArray(),
        ]);

        $this->order->update(['status' => 'payment_failed']);

        // Admin notification
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new OrderProcessingFailed($this->order, $exception));
    }
}
```

### 3.4 Job Dispatch Üsulları

*3.4 Job Dispatch Üsulları üçün kod nümunəsi:*
```php
use App\Jobs\ProcessOrder;

// 1. Standart dispatch (asinxron - queue-ya göndərir)
ProcessOrder::dispatch($order);

// 2. dispatch helper function
dispatch(new ProcessOrder($order));

// 3. Sinxron dispatch (test üçün, queue-ya göndərmir)
ProcessOrder::dispatchSync($order);

// 4. Response göndərildikdən sonra (queue-suz, amma non-blocking)
ProcessOrder::dispatchAfterResponse($order);

// 5. Müəyyən connection-a
ProcessOrder::dispatch($order)->onConnection('redis');

// 6. Müəyyən queue-ya
ProcessOrder::dispatch($order)->onQueue('high-priority');

// 7. Gecikmiş dispatch
ProcessOrder::dispatch($order)->delay(now()->addMinutes(10));

// 8. Şərtli dispatch
ProcessOrder::dispatchIf($order->needsProcessing(), $order);
ProcessOrder::dispatchUnless($order->isProcessed(), $order);

// 9. After database commit (transaction bitdikdən sonra)
ProcessOrder::dispatch($order)->afterCommit();

// 10. Chain (ardıcıl icra)
// Əvvəl payment, sonra confirmation, sonra inventory
Bus::chain([
    new ProcessPayment($order),
    new SendOrderConfirmation($order),
    new UpdateInventory($order),
])->dispatch();

// 11. allOnQueue - chain üçün queue seçimi
Bus::chain([
    new ProcessPayment($order),
    new SendOrderConfirmation($order),
])->onQueue('orders')
  ->onConnection('redis')
  ->dispatch();
```

### 3.5 Job Chaining (Workflow)

Job-ların ardıcıl icra olunmasını təmin edir. Bir job uğursuz olsa, chain dayanır.

*Job-ların ardıcıl icra olunmasını təmin edir. Bir job uğursuz olsa, ch üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Bus;

// Sadə chain
Bus::chain([
    new PullProductData($product),
    new TransformProductImages($product),
    new UploadToMarketplace($product),
    new NotifyVendor($product),
])->catch(function (\Throwable $e) {
    // Chain-dəki hər hansı job fail olsa
    Log::error("Product sync chain failed: " . $e->getMessage());
    NotifyAdmin::dispatch("Product sync failed for #{$product->id}");
})->dispatch();

// Dynamic chain
$jobs = collect($order->items)->map(function ($item) {
    return new ProcessOrderItem($item);
})->toArray();

// Bütün item-ları emal et, sonra confirmation göndər
Bus::chain([
    ...$jobs,
    new SendOrderConfirmation($order),
])->dispatch();
```

### 3.6 Job Batching

Bir qrup job-u bir "batch" olaraq izləmək. Hamısı bitdikdə və ya biri fail olduqda callback.

*Bir qrup job-u bir "batch" olaraq izləmək. Hamısı bitdikdə və ya biri  üçün kod nümunəsi:*
```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

// Batch yarat
$batch = Bus::batch([
    new ImportUsers($chunk1),
    new ImportUsers($chunk2),
    new ImportUsers($chunk3),
    new ImportUsers($chunk4),
    new ImportUsers($chunk5),
])
->then(function (Batch $batch) {
    // BÜTÜN job-lar uğurla tamamlandı
    Log::info("Import completed! Processed {$batch->totalJobs} jobs.");
    Notification::send($admin, new ImportCompleted($batch));
})
->catch(function (Batch $batch, \Throwable $e) {
    // İlk fail olan job-da çağırılır
    Log::error("Import batch failed: " . $e->getMessage());
})
->finally(function (Batch $batch) {
    // Batch bitdikdə (uğurlu və ya uğursuz)
    Cache::forget("import:status:{$batch->id}");
})
->onQueue('imports')
->allowFailures()          // Bir job fail olsa digərləri davam etsin
->name('User Import 2024-01-15')
->dispatch();

// Batch ID-ni saxla (progress tracking üçün)
Cache::put("import:batch_id", $batch->id, 3600);

// Batch status yoxla
$batch = Bus::findBatch($batchId);
echo "Progress: {$batch->progress()}%";          // 0-100
echo "Total: {$batch->totalJobs}";
echo "Pending: {$batch->pendingJobs}";
echo "Failed: {$batch->failedJobs}";
echo "Finished: " . ($batch->finished() ? 'Yes' : 'No');
echo "Cancelled: " . ($batch->cancelled() ? 'Yes' : 'No');

// Batch-ə dinamik job əlavə et
$batch->add([
    new ImportUsers($newChunk),
]);

// Batch-i cancel et
$batch->cancel();
```

### Real-World Batch Nümunəsi

*Real-World Batch Nümunəsi üçün kod nümunəsi:*
```php
class ImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $file = $request->file('csv');
        $rows = $this->parseCsv($file);

        // 100-lük chunk-lara böl
        $chunks = array_chunk($rows, 100);

        $jobs = array_map(fn ($chunk) => new ImportUsers($chunk), $chunks);

        $batch = Bus::batch($jobs)
            ->then(fn (Batch $batch) => event(new ImportCompleted($batch->id)))
            ->name('CSV Import - ' . $file->getClientOriginalName())
            ->onQueue('imports')
            ->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
            'message' => 'Import started',
        ]);
    }

    public function status(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            abort(404, 'Batch not found');
        }

        return response()->json([
            'id' => $batch->id,
            'name' => $batch->name,
            'progress' => $batch->progress(),
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'created_at' => $batch->createdAt->toISOString(),
            'finished_at' => $batch->finishedAt?->toISOString(),
        ]);
    }
}
```

### 3.7 Job Middleware

*3.7 Job Middleware üçün kod nümunəsi:*
```php
// Built-in middleware

use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\Skip;

class ProcessOrder implements ShouldQueue
{
    public function middleware(): array
    {
        return [
            // Eyni order üçün paralel icranı əngəllə
            new WithoutOverlapping($this->order->id),

            // Rate limiting (dəqiqədə max 10)
            new RateLimited('orders'),

            // Exception throttling
            (new ThrottlesExceptions(3, 5))  // 3 exception -> 5 dəqiqə gözlə
                ->backoff(5),                 // 5 dəqiqə

            // Şərtli skip
            Skip::when($this->order->isProcessed()),
        ];
    }
}

// Custom middleware
class EnsureOrderNotProcessed
{
    public function handle(ProcessOrder $job, \Closure $next): void
    {
        if ($job->order->isProcessed()) {
            // Job-u skip et
            return;
        }

        $next($job);
    }
}

// RateLimiter təyin et
// app/Providers/AppServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('orders', function (object $job) {
    return Limit::perMinute(10);
});

RateLimiter::for('external-api', function (object $job) {
    return Limit::perMinute(30)->by($job->apiKey);
});
```

### 3.8 Rate Limiting Jobs

*3.8 Rate Limiting Jobs üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;

class CallExternalApi implements ShouldQueue
{
    public function handle(): void
    {
        // Redis throttle ilə rate limiting
        Redis::throttle('external-api')
            ->allow(30)           // 30 request
            ->every(60)           // 60 saniyə ərzində
            ->then(function () {
                // API call
                $response = Http::get('https://api.example.com/data');
                $this->processResponse($response);
            }, function () {
                // Rate limit - job-u geri qoy
                $this->release(30); // 30 saniyə sonra yenidən cəhd et
            });
    }
}

// Middleware ilə rate limiting
class RateLimitedApiCall
{
    public function handle(object $job, \Closure $next): void
    {
        Redis::throttle('api:' . $job->apiName)
            ->allow(100)
            ->every(60)
            ->then(
                fn () => $next($job),
                fn () => $job->release(10)
            );
    }
}
```

### 3.9 Failed Jobs

*3.9 Failed Jobs üçün kod nümunəsi:*
```php
// config/queue.php
'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

*'table' => 'failed_jobs', üçün kod nümunəsi:*
```bash
# Failed jobs idarəsi
php artisan queue:failed                    # Siyahı
php artisan queue:retry all                 # Hamısını yenidən cəhd et
php artisan queue:retry 5                   # ID=5 olan job-u retry et
php artisan queue:retry --queue=emails      # emails queue-dakı failed job-ları retry
php artisan queue:forget 5                  # ID=5-i sil
php artisan queue:flush                     # Bütün failed job-ları sil
php artisan queue:prune-failed              # 24 saatdan köhnə olanları sil
php artisan queue:prune-failed --hours=48   # 48 saatdan köhnə olanları sil
```

*php artisan queue:prune-failed --hours=48   # 48 saatdan köhnə olanlar üçün kod nümunəsi:*
```php
// Job-da failed method
class ProcessOrder implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1m, 5m, 15m

    public function handle(): void
    {
        // ...
    }

    /**
     * Bütün retry-lar bitdikdən sonra çağırılır
     */
    public function failed(\Throwable $exception): void
    {
        // Log yaz
        Log::error("ProcessOrder failed permanently", [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Sifarişi yenilə
        $this->order->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        // Admin-ə bildir
        Notification::route('mail', config('app.admin_email'))
            ->notify(new JobFailedNotification($this, $exception));
    }
}

// Global failed job handler
// app/Providers/AppServiceProvider.php
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;

Queue::failing(function (JobFailed $event) {
    Log::channel('failed-jobs')->error('Job failed', [
        'connection' => $event->connectionName,
        'queue' => $event->job->getQueue(),
        'exception' => $event->exception->getMessage(),
    ]);
});
```

### 3.10 Unique Jobs

Eyni job-un queue-da təkrarlanmasının qarşısını alır.

*Eyni job-un queue-da təkrarlanmasının qarşısını alır üçün kod nümunəsi:*
```php
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

// Unique - queue-da eyni ID-li job varsa, yenisi əlavə olunmur
class GenerateReport implements ShouldQueue, ShouldBeUnique
{
    public function __construct(
        public Report $report
    ) {}

    /**
     * Unique lock müddəti (saniyə)
     */
    public int $uniqueFor = 3600;

    /**
     * Unique ID (bu ID ilə yalnız 1 job ola bilər)
     */
    public function uniqueId(): string
    {
        return 'report:' . $this->report->id;
    }

    /**
     * Unique lock-un saxlanacağı cache store
     */
    public function uniqueVia(): Repository
    {
        return Cache::driver('redis');
    }

    public function handle(): void
    {
        // Report generate et
    }
}

// ShouldBeUniqueUntilProcessing - emal başlayana qədər unique
// Emal başladıqda lock release olunur, yeni eyni job əlavə oluna bilər
class SyncProduct implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'sync:product:' . $this->product->id;
    }
}
```

### 3.11 Job Events

*3.11 Job Events üçün kod nümunəsi:*
```php
// app/Providers/AppServiceProvider.php
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Queue;

// Job emal edilmədən əvvəl
Queue::before(function (JobProcessing $event) {
    Log::debug("Processing job on {$event->connectionName}/{$event->job->getQueue()}");
});

// Job uğurla emal edildikdən sonra
Queue::after(function (JobProcessed $event) {
    Log::debug("Job processed: {$event->job->resolveName()}");
    
    // Metrics
    $duration = microtime(true) - LARAVEL_START;
    Metrics::timing('queue.job.duration', $duration, [
        'job' => $event->job->resolveName(),
        'queue' => $event->job->getQueue(),
    ]);
});

// Job fail olduqda
Queue::failing(function (JobFailed $event) {
    Log::error("Job FAILED: {$event->job->resolveName()}", [
        'exception' => $event->exception->getMessage(),
    ]);
});

// Job queue-ya əlavə olunduqda
Queue::looping(function () {
    // Worker hər loop-da
    // DB connections-u yenidən qur (uzun müddət işləyən worker üçün)
});
```

---

## 4. Queue Patterns

### 4.1 Priority Queues

*4.1 Priority Queues üçün kod nümunəsi:*
```php
// Job-ları müxtəlif priority queue-lara göndər
SendCriticalAlert::dispatch($alert)->onQueue('critical');
ProcessPayment::dispatch($order)->onQueue('high');
SendEmail::dispatch($user)->onQueue('default');
GenerateReport::dispatch($report)->onQueue('low');

// Worker yüksək prioritetdən başlayır
// php artisan queue:work redis --queue=critical,high,default,low
// Əvvəl critical queue-dakı bütün job-lar emal olunur,
// sonra high, sonra default, sonra low
```

### 4.2 Delayed / Scheduled Jobs

*4.2 Delayed / Scheduled Jobs üçün kod nümunəsi:*
```php
// Müəyyən müddət sonra icra et
SendReminderEmail::dispatch($user)->delay(now()->addHours(24));
ExpireReservation::dispatch($reservation)->delay(now()->addMinutes(30));

// Job-un özündə
class SendFollowUpEmail implements ShouldQueue
{
    public function __construct(
        public User $user,
        public int $dayAfter = 3
    ) {
        // Constructor-da delay təyin et
        $this->delay = now()->addDays($this->dayAfter);
    }
}

// Scheduler ilə birlikdə
// app/Console/Kernel.php
$schedule->job(new CleanupExpiredSessions)->daily();
$schedule->job(new GenerateDailyReport, 'reports')->dailyAt('23:00');
```

### 4.3 Fan-out Pattern

Bir event-i bir neçə consumer-ə göndərmək.

*Bir event-i bir neçə consumer-ə göndərmək üçün kod nümunəsi:*
```php
// Event-based fan-out
class OrderCreated
{
    public function __construct(public Order $order) {}
}

// Listener-lər (hər biri ayrı queue-da)
class SendOrderConfirmation implements ShouldQueue
{
    public string $queue = 'emails';
    
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}

class UpdateInventory implements ShouldQueue
{
    public string $queue = 'inventory';
    
    public function handle(OrderCreated $event): void
    {
        foreach ($event->order->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
    }
}

class NotifyWarehouse implements ShouldQueue
{
    public string $queue = 'notifications';
    
    public function handle(OrderCreated $event): void
    {
        // Warehouse notification
    }
}

class SyncWithERP implements ShouldQueue
{
    public string $queue = 'integrations';
    
    public function handle(OrderCreated $event): void
    {
        // ERP sync
    }
}

// Trigger
event(new OrderCreated($order));
// Bütün listener-lər paralel olaraq öz queue-larında emal olunur
```

### 4.4 Competing Consumers

Eyni queue-nu bir neçə worker dinləyir. İlk boş olan worker job-u alır.

*Eyni queue-nu bir neçə worker dinləyir. İlk boş olan worker job-u alır üçün kod nümunəsi:*
```bash
# 4 worker eyni queue-nu dinləyir
php artisan queue:work redis --queue=default &
php artisan queue:work redis --queue=default &
php artisan queue:work redis --queue=default &
php artisan queue:work redis --queue=default &

# Supervisor ilə (production)
# numprocs=4 (supervisor 4 proses yaradır)
```

---

## 5. Idempotency in Queue Jobs

**Idempotent job** — eyni job bir neçə dəfə icra olunsa eyni nəticəni verir. Queue "at-least-once" delivery təmin etdiyi üçün çox vacibdir.

### Problem

*Problem üçün kod nümunəsi:*
```php
// İDEMPOTENT DEYİL - 2 dəfə icra olunsa 2 dəfə ödəniş alınır!
class ProcessPayment implements ShouldQueue
{
    public function handle(): void
    {
        $this->paymentGateway->charge($this->order->total); // Təkrar charge!
        $this->order->update(['status' => 'paid']);
    }
}
```

### Həll

*Həll üçün kod nümunəsi:*
```php
// İDEMPOTENT - neçə dəfə icra olunsa da eyni nəticə
class ProcessPayment implements ShouldQueue
{
    public function handle(): void
    {
        // Guard clause - artıq ödənilib?
        if ($this->order->isPaid()) {
            Log::info("Order #{$this->order->id} already paid, skipping.");
            return;
        }

        // Idempotency key ilə
        $idempotencyKey = "payment:{$this->order->id}:{$this->order->updated_at->timestamp}";
        
        $lock = Cache::lock($idempotencyKey, 60);
        if (!$lock->get()) {
            return; // Başqa worker emal edir
        }

        try {
            DB::transaction(function () {
                // Pessimistic lock ilə yenidən yoxla
                $order = Order::lockForUpdate()->find($this->order->id);

                if ($order->isPaid()) {
                    return;
                }

                $result = $this->paymentGateway->charge(
                    $order->total,
                    idempotencyKey: "order_{$order->id}"  // Payment gateway-ə idempotency key
                );

                $order->update([
                    'status' => 'paid',
                    'transaction_id' => $result->transactionId(),
                    'paid_at' => now(),
                ]);
            });
        } finally {
            $lock->release();
        }
    }
}
```

---

## 6. Queue Monitoring

### 6.1 Laravel Horizon (Redis driver üçün)

*6.1 Laravel Horizon (Redis driver üçün) üçün kod nümunəsi:*
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

*php artisan migrate üçün kod nümunəsi:*
```php
// config/horizon.php
return [
    'domain' => null,
    'path' => 'horizon',

    'use' => 'default',

    'middleware' => ['web'],

    'waits' => [
        'redis:critical' => 30,   // 30 saniyədən çox gözləsə xəbərdarlıq
        'redis:default' => 60,
        'redis:low' => 120,
    ],

    'trim' => [
        'recent' => 60,           // Recent jobs 60 dəqiqə saxla
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080, // Failed jobs 7 gün saxla
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'maxTime' => 3600,        // 1 saat sonra worker restart
                'maxJobs' => 1000,         // 1000 job sonra restart
                'memory' => 128,           // 128 MB limit
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'queue' => ['critical', 'high', 'default', 'low'],
                'balance' => 'auto',       // auto, simple, false
                'tries' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
                'queue' => ['default'],
                'balance' => 'simple',
            ],
        ],
    ],
];
```

*'balance' => 'simple', üçün kod nümunəsi:*
```php
// Horizon dashboard access control
// app/Providers/HorizonServiceProvider.php
protected function gate(): void
{
    Gate::define('viewHorizon', function (?User $user) {
        return $user?->isAdmin();
    });
}
```

*return $user?->isAdmin(); üçün kod nümunəsi:*
```bash
# Horizon başlat
php artisan horizon

# Horizon statusu
php artisan horizon:status

# Horizon dayandır
php artisan horizon:terminate

# Horizon pause/continue
php artisan horizon:pause
php artisan horizon:continue

# Worker sayını scale et
php artisan horizon:supervisor-1:scale 20
```

### Horizon Metrics

Horizon aşağıdakı metric-ləri göstərir:
- **Jobs Per Minute** — dəqiqədə emal olunan job sayı
- **Runtime** — ortalama job icra müddəti
- **Throughput** — ümumi throughput
- **Wait Time** — job-un queue-da gözləmə müddəti
- **Failed Jobs** — uğursuz job-lar
- **Recent Jobs** — son job-lar (status, runtime, exception)

### 6.2 Supervisor Configuration

*6.2 Supervisor Configuration üçün kod nümunəsi:*
```ini
; /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/horizon.log
stopwaitsecs=3600
```

*stopwaitsecs=3600 üçün kod nümunəsi:*
```bash
# Supervisor əmrləri
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
sudo supervisorctl stop horizon
sudo supervisorctl restart horizon
sudo supervisorctl status
```

---

## 7. Real-World Nümunələr

### 7.1 Email Göndərmə

*7.1 Email Göndərmə üçün kod nümunəsi:*
```php
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $user
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->user)->send(new WelcomeMail($this->user));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Welcome email failed for user #{$this->user->id}: {$exception->getMessage()}");
    }
}

// Dispatch
SendWelcomeEmail::dispatch($user);
```

### 7.2 PDF Generation

*7.2 PDF Generation üçün kod nümunəsi:*
```php
class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // PDF generation uzun çəkə bilər
    public int $tries = 2;

    public function __construct(
        public Invoice $invoice
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $pdf = Pdf::loadView('invoices.template', [
            'invoice' => $this->invoice->load('items', 'customer'),
        ]);

        $path = "invoices/{$this->invoice->number}.pdf";
        Storage::disk('s3')->put($path, $pdf->output());

        $this->invoice->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ]);

        // İstifadəçiyə bildir
        $this->invoice->customer->notify(
            new InvoiceReady($this->invoice)
        );
    }
}
```

### 7.3 Image Processing

*7.3 Image Processing üçün kod nümunəsi:*
```php
class ProcessUploadedImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public Image $image
    ) {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $originalPath = Storage::disk('s3')->path($this->image->path);

        // Thumbnail
        $thumb = InterventionImage::make($originalPath)
            ->fit(150, 150)
            ->encode('webp', 80);
        Storage::disk('s3')->put(
            $this->image->thumbnailPath(),
            $thumb->stream()
        );

        // Medium
        $medium = InterventionImage::make($originalPath)
            ->resize(800, null, fn ($constraint) => $constraint->aspectRatio())
            ->encode('webp', 85);
        Storage::disk('s3')->put(
            $this->image->mediumPath(),
            $medium->stream()
        );

        // Large
        $large = InterventionImage::make($originalPath)
            ->resize(1920, null, fn ($constraint) => $constraint->aspectRatio())
            ->encode('webp', 90);
        Storage::disk('s3')->put(
            $this->image->largePath(),
            $large->stream()
        );

        $this->image->update([
            'processed' => true,
            'sizes' => [
                'thumbnail' => $this->image->thumbnailPath(),
                'medium' => $this->image->mediumPath(),
                'large' => $this->image->largePath(),
            ],
        ]);
    }
}
```

### 7.4 Report Generation with Batch

*7.4 Report Generation with Batch üçün kod nümunəsi:*
```php
class GenerateMonthlyReport implements ShouldQueue
{
    public int $timeout = 600;

    public function __construct(
        public string $month,
        public int $year
    ) {}

    public function handle(): void
    {
        $startDate = Carbon::create($this->year, $this->month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        // Böyük data-nı chunk-lara böl
        $days = collect(CarbonPeriod::create($startDate, $endDate));

        $jobs = $days->map(fn (Carbon $day) => new ProcessDayReport($day))->toArray();

        Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // Bütün günlər emal olundu, final report-u birləşdir
                CompileMonthlyReport::dispatch($this->month, $this->year);
            })
            ->name("Monthly Report {$this->year}-{$this->month}")
            ->onQueue('reports')
            ->dispatch();
    }
}

class ProcessDayReport implements ShouldQueue, Batchable
{
    use Batchable;

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $stats = [
            'date' => $this->day->format('Y-m-d'),
            'orders' => Order::whereDate('created_at', $this->day)->count(),
            'revenue' => Order::whereDate('created_at', $this->day)->sum('total'),
            'new_users' => User::whereDate('created_at', $this->day)->count(),
        ];

        Cache::put("report:daily:{$this->day->format('Y-m-d')}", $stats, 86400);
    }
}
```

### 7.5 Webhook Processing

*7.5 Webhook Processing üçün kod nümunəsi:*
```php
class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];
    public int $maxExceptions = 5;

    public function __construct(
        public array $payload,
        public string $type
    ) {
        $this->onQueue('webhooks');
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->payload['id'] ?? md5(json_encode($this->payload))),
        ];
    }

    public function handle(): void
    {
        match ($this->type) {
            'payment.completed' => $this->handlePaymentCompleted(),
            'payment.failed'    => $this->handlePaymentFailed(),
            'subscription.renewed' => $this->handleSubscriptionRenewed(),
            'refund.created'    => $this->handleRefundCreated(),
            default => Log::warning("Unknown webhook type: {$this->type}"),
        };
    }

    private function handlePaymentCompleted(): void
    {
        $order = Order::where('external_id', $this->payload['order_id'])->first();
        if (!$order || $order->isPaid()) {
            return; // Idempotency
        }

        DB::transaction(function () use ($order) {
            $order->markAsPaid($this->payload['transaction_id']);
            event(new OrderPaid($order));
        });
    }

    private function handlePaymentFailed(): void
    {
        $order = Order::where('external_id', $this->payload['order_id'])->first();
        $order?->markPaymentFailed($this->payload['reason'] ?? 'Unknown error');
    }
}

// Webhook controller
class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Signature verification
        if (!$this->verifySignature($request)) {
            abort(401, 'Invalid signature');
        }

        // Queue-ya göndər (dərhal 200 qaytar)
        ProcessWebhook::dispatch(
            $request->all(),
            $request->header('X-Event-Type', 'unknown')
        );

        return response()->json(['status' => 'received']);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');
        $computed = hash_hmac('sha256', $request->getContent(), config('services.payment.webhook_secret'));
        return hash_equals($computed, $signature);
    }
}
```

### 7.6 Data Import/Export

*7.6 Data Import/Export üçün kod nümunəsi:*
```php
class ExportUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public User $requestedBy,
        public array $filters = []
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $filename = "exports/users_" . now()->format('Y-m-d_His') . ".csv";

        // Stream to S3
        $tempFile = tempnam(sys_get_temp_dir(), 'export');
        $handle = fopen($tempFile, 'w');

        // Header
        fputcsv($handle, ['ID', 'Name', 'Email', 'Created At', 'Orders Count']);

        // Data (chunk ilə memory idarəsi)
        User::query()
            ->when($this->filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->withCount('orders')
            ->chunk(1000, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->created_at->format('Y-m-d H:i:s'),
                        $user->orders_count,
                    ]);
                }
            });

        fclose($handle);

        // Upload to S3
        Storage::disk('s3')->put($filename, file_get_contents($tempFile));
        unlink($tempFile);

        // İstifadəçiyə bildir
        $this->requestedBy->notify(new ExportReady($filename));
    }
}
```

---

## 8. Queue Best Practices

### 8.1 Job-ları Kiçik və Fokuslu Saxlayın

*8.1 Job-ları Kiçik və Fokuslu Saxlayın üçün kod nümunəsi:*
```php
// PIS: Bir job-da çox iş
class ProcessOrder implements ShouldQueue
{
    public function handle(): void
    {
        $this->validateOrder();
        $this->processPayment();
        $this->updateInventory();
        $this->sendConfirmationEmail();
        $this->syncWithERP();
        $this->notifyWarehouse();
        $this->generateInvoice();
        // Biri fail olsa hamısı fail olur!
    }
}

// YAXSI: Kiçik, fokuslu job-lar + chain
Bus::chain([
    new ValidateOrder($order),
    new ProcessPayment($order),
    fn () => Bus::batch([           // Paralel icra
        new UpdateInventory($order),
        new SendConfirmation($order),
        new SyncWithERP($order),
        new NotifyWarehouse($order),
        new GenerateInvoice($order),
    ])->dispatch(),
])->dispatch();
```

### 8.2 Serialization-a Diqqət

*8.2 Serialization-a Diqqət üçün kod nümunəsi:*
```php
// PIS: Böyük data serialize etmə
class ProcessData implements ShouldQueue
{
    public function __construct(
        public Collection $users  // 10000 user serialize olunur!
    ) {}
}

// YAXSI: ID göndər, job-da yüklə
class ProcessData implements ShouldQueue
{
    public function __construct(
        public array $userIds
    ) {}

    public function handle(): void
    {
        $users = User::whereIn('id', $this->userIds)->get();
        // ...
    }
}

// ƏN YAXSI: SerializesModels trait istifadə edin (Model-lər avtomatik ID ilə serialize olunur)
class ProcessOrder implements ShouldQueue
{
    use SerializesModels;

    public function __construct(
        public Order $order  // Yalnız ID serialize olunur, handle()-da yenidən yüklənir
    ) {}
}
```

### 8.3 Timeout və Memory

*8.3 Timeout və Memory üçün kod nümunəsi:*
```php
class HeavyJob implements ShouldQueue
{
    public int $timeout = 300;       // 5 dəqiqə
    // php artisan queue:work --timeout=300

    public function handle(): void
    {
        // Chunk ilə yaddaşı idarə et
        User::chunk(500, function ($users) {
            foreach ($users as $user) {
                $this->process($user);
            }
            // Garbage collection
            gc_collect_cycles();
        });
    }
}

// Worker memory limit
// php artisan queue:work --memory=256   (256 MB)
// php artisan queue:work --max-time=3600 (1 saat sonra restart)
// php artisan queue:work --max-jobs=1000 (1000 job sonra restart)
```

### 8.4 Graceful Shutdown

*8.4 Graceful Shutdown üçün kod nümunəsi:*
```bash
# Worker-i təhlükəsiz dayandır (cari job-u bitir, sonra dayanır)
php artisan queue:restart

# SIGTERM siqnalı (supervisor göndərir)
# Worker cari job-u bitirir və dayanır (stopwaitsecs-ə diqqət)
```

*Worker cari job-u bitirir və dayanır (stopwaitsecs-ə diqqət) üçün kod nümunəsi:*
```php
// Job-da graceful shutdown yoxlama
class LongRunningJob implements ShouldQueue
{
    public function handle(): void
    {
        foreach ($this->items as $item) {
            if (app('queue.worker')->shouldQuit) {
                // Worker dayanmaq istəyir, job-u geri qoy
                $this->release(0);
                return;
            }

            $this->process($item);
        }
    }
}
```

### 8.5 After Commit

*8.5 After Commit üçün kod nümunəsi:*
```php
// Database transaction commit olduqdan sonra dispatch et
// Əks halda job icra olunanda data hələ commit olmamış ola bilər

// Global olaraq
// config/queue.php
'redis' => [
    'after_commit' => true,  // Bütün job-lar üçün
],

// Per-job
ProcessOrder::dispatch($order)->afterCommit();

// Job class-da
class ProcessOrder implements ShouldQueue
{
    public bool $afterCommit = true;
}
```

---

## 9. Laravel Horizon Deep Dive

### Dashboard Features

```
/horizon
├── Dashboard (ümumi baxış)
├── Monitoring (tag-based monitoring)
├── Metrics
│   ├── Jobs (throughput, runtime per job class)
│   ├── Queues (throughput, wait time per queue)
├── Recent Jobs (completed, failed, pending)
├── Failed Jobs (exception details, retry)
├── Batches (batch progress, status)
└── Tags (tagged job tracking)
```

### Tag-based Monitoring

*Tag-based Monitoring üçün kod nümunəsi:*
```php
class ProcessOrder implements ShouldQueue
{
    /**
     * Horizon tag-ları
     */
    public function tags(): array
    {
        return [
            'order:' . $this->order->id,
            'user:' . $this->order->user_id,
            'type:payment',
        ];
    }
}

// Horizon dashboard-da tag ilə filter edə bilərsiniz
```

### Auto Balancing

*Auto Balancing üçün kod nümunəsi:*
```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'balance' => 'auto',           // auto-scaling
            'balanceMaxShift' => 1,        // Hər balance cycle-da max 1 worker shift
            'balanceCooldown' => 3,        // Balance cycle arası 3 saniyə
            'minProcesses' => 1,           // Minimum 1 worker
            'maxProcesses' => 20,          // Maximum 20 worker
            'queue' => ['critical', 'high', 'default', 'low'],
        ],
    ],
],

// Horizon hansı queue-da çox job varsa, ora daha çox worker ayırır
// Əgər critical queue-da job yığılıbsa, digər queue-lardan worker çəkir
```

### Horizon Notifications

*Horizon Notifications üçün kod nümunəsi:*
```php
// app/Providers/HorizonServiceProvider.php
use Laravel\Horizon\Horizon;

Horizon::routeSlackNotificationsTo(
    'https://hooks.slack.com/services/xxx/yyy/zzz',
    '#queue-alerts'
);

Horizon::routeMailNotificationsTo('admin@example.com');

// Long wait notification
// config/horizon.php
'waits' => [
    'redis:critical' => 15,   // 15 saniyədən çox gözləsə Slack/email
    'redis:default' => 60,
],
```

### Deploy ilə Horizon

*Deploy ilə Horizon üçün kod nümunəsi:*
```bash
#!/bin/bash
# deploy.sh

# Horizon-u təhlükəsiz dayandır
php artisan horizon:terminate

# Kod yenilə, composer install, migration, etc.
git pull origin main
composer install --no-dev
php artisan migrate --force
php artisan optimize

# Supervisor Horizon-u yenidən başladacaq (autorestart=true)
# və ya manual:
sudo supervisorctl restart horizon
```

---

## 10. İntervyu Sualları və Cavabları

### S1: Queue nədir və nə vaxt istifadə etmək lazımdır?

**Cavab:** Queue vaxt tələb edən əməliyyatları background-da asinxron emal etmək üçün istifadə olunur. İstifadəçinin cavab gözləməsi lazım olmayan hər bir əməliyyat queue-ya göndərilə bilər: email göndərmə, PDF generation, image processing, external API calls, report generation, data import/export, webhook processing. Queue istifadə etdikdə response time dramatik azalır, application daha scalable olur.

### S2: Queue connection ilə queue adı arasında fərq nədir?

**Cavab:**
- **Connection**: Queue driver/backend (redis, database, sqs, rabbitmq). Mesajların harada saxlanacağını müəyyən edir.
- **Queue name**: Bir connection daxilindəki fərqli queue-lar (default, emails, reports, high, low). Priority və izolyasiya üçün istifadə olunur.

*- **Queue name**: Bir connection daxilindəki fərqli queue-lar (default üçün kod nümunəsi:*
```php
// Connection: redis, Queue: high-priority
ProcessOrder::dispatch($order)->onConnection('redis')->onQueue('high-priority');
```

### S3: Job retry mexanizmi necə işləyir?

**Cavab:** Job exception throw etdikdə, Laravel onu `retry_after` müddətindən sonra yenidən queue-ya qoyur. `$tries` property maksimum cəhd sayını, `$backoff` array isə cəhdlər arası gözləmə müddətini müəyyən edir. Bütün retry-lar uğursuz olduqda, job `failed_jobs` table-ına yazılır və `failed()` method çağırılır.

***Cavab:** Job exception throw etdikdə, Laravel onu `retry_after` müdd üçün kod nümunəsi:*
```php
public int $tries = 3;
public array $backoff = [30, 60, 120]; // 1-ci retry: 30s, 2-ci: 60s, 3-cü: 120s
```

### S4: Idempotent job nədir və niyə vacibdir?

**Cavab:** Eyni job-un bir neçə dəfə icra olunmasının eyni nəticəni verməsi. Queue "at-least-once" delivery təmin edir — network problemi, worker crash, timeout zamanı job təkrar icra oluna bilər. Payment processing kimi kritik əməliyyatlarda idempotency olmasa, istifadəçidən 2 dəfə pul çıxıla bilər. Həll: unique constraint, idempotency key, guard clause, database lock.

### S5: Job Batching nədir və real-world nümunə verin?

**Cavab:** Bir qrup job-u bir "batch" olaraq izləmək. CSV import: 10000 sətirlik fayl 100-lük chunk-lara bölünür, 100 job yaradılır, batch kimi track olunur. Hamısı bitdikdə "then" callback, biri fail olsa "catch" callback icra olunur. Batch-in progress-ini real-time izləyə bilərsiniz (progress(), pendingJobs, failedJobs).

### S6: Laravel Horizon nədir və nə üçün lazımdır?

**Cavab:** Horizon Laravel-in Redis queue-ları üçün dashboard və konfiqurasiya paketidir. Təmin edir:
- Web dashboard (job status, metrics, failed jobs)
- Auto-scaling (queue yükünə görə worker sayını artır/azalt)
- Wait time monitoring (alerting)
- Job tagging və axtarış
- Real-time metrics (throughput, runtime, wait time)
- Programmatic queue configuration (config/horizon.php)
- Slack/email notifications

Production-da Redis queue istifadə edirsinizsə, Horizon istifadə etmək tövsiyə olunur.

### S7: Queue worker-i production-da necə idarə edirsiniz?

**Cavab:**
1. **Supervisor** — worker proseslərini idarə edir (autorestart, numprocs)
2. **Horizon** — Redis queue üçün (auto-scaling, monitoring)
3. `--max-time=3600` — 1 saat sonra worker restart (memory leak qarşısı)
4. `--max-jobs=1000` — 1000 job sonra restart
5. `--memory=256` — 256MB limit
6. Deploy zamanı `php artisan queue:restart` (graceful shutdown)
7. **Monitoring**: Horizon dashboard, failed jobs alert, queue depth tracking

### S8: Job chaining ilə batching arasında fərq nədir?

**Cavab:**
- **Chaining**: Job-lar **ardıcıl** icra olunur. A -> B -> C. Biri fail olsa chain dayanır. Sıra vacib olduqda istifadə olunur (payment -> confirmation -> shipping).
- **Batching**: Job-lar **paralel** icra olunur. Hamısı bitdikdə callback. Sıra vacib deyil ama hamısının nəticəsi lazımdır (CSV import, bulk notification).

### S9: afterCommit nə vaxt istifadə olunmalıdır?

**Cavab:** Database transaction daxilindəki kod hələ commit olmadan job dispatch oluna bilər. Job işlədikdə data hələ database-də olmaya bilər (race condition). `afterCommit` job-un yalnız transaction commit olduqdan sonra dispatch olunmasını təmin edir.

***Cavab:** Database transaction daxilindəki kod hələ commit olmadan jo üçün kod nümunəsi:*
```php
DB::transaction(function () {
    $order = Order::create($data); // Hələ commit olmayıb
    ProcessOrder::dispatch($order)->afterCommit(); // Commit olduqdan sonra dispatch
});
```

### S10: Queue-da error handling strategiyanız nədir?

**Cavab:**
1. **Retry**: `$tries` + `$backoff` (exponential backoff)
2. **failed() method**: Son cəhd uğursuz olduqda (log, notify admin, update status)
3. **Idempotency**: Guard clause, database lock
4. **Dead letter**: Failed jobs table, periodic retry/cleanup
5. **Monitoring**: Horizon alerts, failed job count threshold
6. **Circuit breaker**: External API down olduqda job-ları dayandır
7. **Job middleware**: `ThrottlesExceptions` — çox exception olsa gözlə

Bu bələdçi Laravel Queue sisteminin bütün aspektlərini əhatə edir. İntervyuda queue pattern-ləri, error handling, monitoring və best practices haqqında dərin bilgi göstərmək vacibdir.

---

## Anti-patterns

**1. Job-da həddindən artıq data serialization**
`ProcessOrderJob(Order $order)` — Eloquent model serialize olur, bütün relations, attributes. Job payload 100KB+ olur. Bunun əvəzinə yalnız ID pass et: `ProcessOrderJob(int $orderId)`, handle()-də fresh data oxu.

**2. Poison pill — sonsuz retry**
Hər zaman fail olan job `$tries = 0` (unlimited) ilə queue-nu bloklayr. `$tries` məhdud olmalı (3-5), `$backoff` exponential olmalı. Max retry keçdikdə `failed()` metodunda log + alert.

**3. Sync driver-i production-da istifadə**
`QUEUE_CONNECTION=sync` — job dərhal işlənir, user gözləyir, timeout riski. Production-da həmişə `redis` və ya `sqs`. `sync` yalnız test/local-da.

**4. Job-da uzun DB transaction**
Job handler-ın tamamı `DB::transaction()` içindədir, daxilindən HTTP request göndərilir → 30s timeout → transaction lock. External API call-ı transaction xaricinə çıxar.

**5. Job idempotenliyi yoxlamaq**
Retry zamanı eyni işi iki dəfə etmək — iki ödəniş, iki email. Hər job-da: "artıq icra olunubmu?" yoxlaması (`processed_jobs` cədvəli, ya da cache flag) mütləqdir.

**6. Çox böyük job batch**
Milyonluq job-ları eyni anda dispatch etmək — queue dolur, memory tükənir. Chunk-larla dispatch et, Horizon-da batch-size limitlə.

**7. Failed job-ları izləməmək**
`failed_jobs` cədvəli böyüyür, heç kim baxmır. Laravel Horizon ilə monitoring, threshold-dan çox failed job olduqda alert mütləqdir.

**8. Queue worker-i restart etmədən deploy**
Köhnə worker yeni kod-u bilmir — yeni class-lar, renamed metodlar. Deploy-dan sonra `php artisan queue:restart` mütləqdir (Horizon/Supervisor ilə avtomatlaşdır).

**9. Job-u Yanlış Queue-ya Dispatch Etmək**
Ağır hesabat job-larını `default` queue-ya göndərmək, həmin queue-da isə kritik email job-ları da var — ağır job-lar kritik mesajların çatdırılmasını gecikdirir. Queue-ları işin növünə görə ayırın: `critical`, `default`, `reports`, `bulk`; hər queue üçün ayrı worker proseslər qeydiyyatdan keçirin.

**10. Queue Job-unun Müddətini (Timeout) Qeyd Etməmək**
`$timeout` təyin etmədən uzun çalışan job-lar worker prosesini blok edir; `restart_after` müddəti keçdikdə job "lost" görünür. Hər job üçün `public $timeout` əlavə edin. Çox uzun iş varsa, job-u kiçik hissələrə (chunk) bölün. Horizon-da queue-specific `timeout` konfiqurasiyası ilə idarə edin.
