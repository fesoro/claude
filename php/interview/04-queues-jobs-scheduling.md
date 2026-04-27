# Queues, Jobs və Task Scheduling (Senior)

## 1. Laravel Queue sistemi necə işləyir?

Queue — uzun sürən əməliyyatları arxa fona göndərir, response vaxtını azaldır.

**Queue Drivers:** `sync`, `database`, `redis`, `sqs`, `beanstalkd`

```php
// Job yaratmaq
class ProcessPayment implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;           // Maksimum cəhd
    public int $backoff = 60;        // Cəhdlər arası gözləmə (saniyə)
    public int $timeout = 120;       // Maksimum icra müddəti
    public int $maxExceptions = 2;   // Maksimum exception sayı

    public function __construct(
        private Order $order,
    ) {}

    public function handle(PaymentGateway $gateway): void {
        $gateway->charge($this->order->total, $this->order->payment_method);
        $this->order->update(['status' => 'paid']);
    }

    // Uğursuz olduqda
    public function failed(Throwable $exception): void {
        Log::error('Payment failed', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);
        $this->order->update(['status' => 'payment_failed']);
    }

    // Retry qərarı
    public function retryUntil(): DateTime {
        return now()->addHours(1);
    }

    // Job-u queue-ya göndərməzdən əvvəl yoxla
    public function middleware(): array {
        return [
            new RateLimited('payments'),
            new WithoutOverlapping($this->order->id),
        ];
    }
}

// Dispatch etmə yolları
ProcessPayment::dispatch($order);
ProcessPayment::dispatch($order)->onQueue('payments');
ProcessPayment::dispatch($order)->delay(now()->addMinutes(5));

// Conditional dispatch
ProcessPayment::dispatchIf($order->total > 0, $order);
ProcessPayment::dispatchUnless($order->is_free, $order);
```

---

## 2. Job Chaining və Batching

```php
// Chain — ardıcıl icra, biri uğursuz olsa dayandırır
Bus::chain([
    new ProcessPayment($order),
    new UpdateInventory($order),
    new SendConfirmationEmail($order),
    new NotifyWarehouse($order),
])->onQueue('orders')->dispatch();

// Batch — paralel icra, progress tracking
$batch = Bus::batch([
    new ImportUsers($chunk1),
    new ImportUsers($chunk2),
    new ImportUsers($chunk3),
])
->then(function (Batch $batch) {
    Log::info('All imports completed!');
})
->catch(function (Batch $batch, Throwable $e) {
    Log::error('Import failed: ' . $e->getMessage());
})
->finally(function (Batch $batch) {
    Notification::send($admin, new ImportFinished($batch));
})
->allowFailures()
->onQueue('imports')
->dispatch();

// Batch progress (API və ya frontend-dən)
$batch = Bus::findBatch($batchId);
$batch->progress();    // 0-100
$batch->totalJobs;
$batch->failedJobs;
$batch->processedJobs();
```

---

## 3. Queue Worker və Supervisor

```bash
# Worker başlatma
php artisan queue:work redis --queue=payments,default --tries=3 --timeout=90

# Supervisor config (/etc/supervisor/conf.d/laravel-worker.conf)
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/worker.log
stopwaitsecs=3600

# Horizon (Redis queue monitoring)
php artisan horizon
```

**queue:work vs queue:listen:**
- `queue:work` — kodu yaddaşda saxlayır, sürətli, deployment sonrası restart lazımdır
- `queue:listen` — hər job üçün yenidən boot edir, yavaş, development üçün

---

## 4. Laravel Horizon

Redis queue-ların monitorinqi və idarəsi.

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['payments', 'notifications', 'default'],
            'balance' => 'auto', // avtomatik balanslaşdırma
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'tries' => 3,
            'timeout' => 90,
        ],
    ],
],

// Metrics və monitoring
// /horizon — web dashboard
// Failed jobs, throughput, runtime, wait times
```

---

## 5. Task Scheduling

```php
// app/Console/Kernel.php və ya routes/console.php
Schedule::command('reports:daily')
    ->dailyAt('02:00')
    ->timezone('Asia/Baku')
    ->withoutOverlapping()
    ->onOneServer()               // Bir neçə server olduqda
    ->runInBackground()
    ->emailOutputOnFailure('admin@example.com');

Schedule::job(new CleanTemporaryFiles)
    ->hourly()
    ->between('8:00', '22:00');

Schedule::call(function () {
    DB::table('sessions')->where('last_activity', '<', now()->subDay())->delete();
})->daily();

// Frequency options
->everyMinute()
->everyFiveMinutes()
->hourly()
->daily()
->weekly()
->monthly()
->cron('0 2 * * 1-5')  // Custom cron

// Conditions
->when(fn () => app()->isProduction())
->skip(fn () => app()->isDownForMaintenance())
->environments(['production'])
```

**Crontab setup:**
```bash
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Unique Jobs və Rate Limiting

```php
// Unique Job — eyni anda yalnız bir instance
class ProcessPodcast implements ShouldQueue, ShouldBeUnique {
    public int $uniqueFor = 3600; // 1 saat unique qalır

    public function uniqueId(): string {
        return $this->podcast->id;
    }
}

// Rate Limiting middleware
class RateLimitedJob implements ShouldQueue {
    public function middleware(): array {
        return [
            (new RateLimited('external-api'))
                ->dontRelease(), // limit aşılanda silsin (retry etməsin)
        ];
    }
}

// RateLimiter təyin et (AppServiceProvider)
RateLimiter::for('external-api', function ($job) {
    return Limit::perMinute(30);
});
```
