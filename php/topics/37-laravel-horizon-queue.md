# Laravel Horizon (Middle)

## Mündəricat
1. [Horizon nədir?](#horizon-nədir)
2. [Supervisor anatomiyası](#supervisor-anatomiyası)
3. [Quraşdırma və config](#quraşdırma-və-config)
4. [Autoscaling strategy](#autoscaling-strategy)
5. [Job lifecycle, failure, retry](#job-lifecycle-failure-retry)
6. [Metrics & dashboard](#metrics--dashboard)
7. [Dead job management](#dead-job-management)
8. [Deployment considerations](#deployment-considerations)
9. [Production troubleshooting](#production-troubleshooting)
10. [Horizon alternativləri](#horizon-alternativləri)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Horizon nədir?

```
Laravel Horizon — Laravel queue worker-lərini idarə edən dashboard + process manager.

Xüsusiyyətlər:
  - Queue worker-lər config ilə supervise olunur
  - Autoscaling (workload əsasında worker sayı avtomatik)
  - Real-time dashboard (Vue.js UI)
  - Metrics: throughput, runtime, failures
  - Failed job management
  - Redis queue driver tələb edir

Adi supervisor-dan fərq:
  Supervisor:  OS-level, statik worker sayı
  Horizon:     app-level, dinamik worker sayı, Laravel-aware

Plus: Slack/email alert, silent failure detection.
```

---

## Supervisor anatomiyası

```
Horizon > Master Process > Supervisors > Processes (workers)

┌─ Horizon Master (php artisan horizon)
│
├── Supervisor "high-priority" (config-dən)
│     ├─ Worker 1 (queue: high, critical)
│     ├─ Worker 2 (queue: high, critical)
│     └─ Worker 3 (queue: high, critical)
│
└── Supervisor "default"
      ├─ Worker 1 (queue: default, emails)
      └─ Worker 2 (queue: default, emails)

Supervisor qaydaları:
  - Hər supervisor fərqli queue(-lər) üçün
  - Autoscale range: minProcesses, maxProcesses
  - balance: "simple", "auto", "false"
```

---

## Quraşdırma və config

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
php artisan horizon   # start
```

```php
<?php
// config/horizon.php
return [
    // Redis connection
    'use' => 'default',
    
    // Environments-ə görə fərqli config
    'environments' => [
        'production' => [
            'supervisor-high' => [
                'connection' => 'redis',
                'queue' => ['critical', 'high'],
                'balance' => 'auto',           // dinamik paylama
                'autoScalingStrategy' => 'time',   // ya da 'size'
                'minProcesses' => 2,
                'maxProcesses' => 20,
                'balanceMaxShift' => 3,        // bir anda max 3 worker artırma
                'balanceCooldown' => 3,        // 3s sonra yenidən balance
                'maxTime' => 0,                // max worker runtime (seconds)
                'maxJobs' => 1000,             // worker N job-dan sonra restart (memory leak)
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default', 'emails'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 10,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 120,
            ],
            
            'supervisor-long' => [
                'connection' => 'redis',
                'queue' => ['exports', 'reports'],
                'balance' => 'false',          // fixed worker count
                'processes' => 2,
                'memory' => 512,                // böyük job-lar çox yaddaş
                'timeout' => 900,               // 15 dəq
                'tries' => 1,                   // export-lar retry edilmir
            ],
        ],
        
        'staging' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
                // ...
            ],
        ],
    ],
    
    // Dashboard auth
    'middleware' => ['web', 'auth', 'can:viewHorizon'],
    
    // Notifications
    'slack' => [
        'webhook_url' => env('HORIZON_SLACK_WEBHOOK'),
        'channel' => '#alerts',
        'users' => [],
    ],
    
    // Wait time threshold — N saniyədən çox gözləyirsə alert
    'waits' => [
        'redis:default' => 60,
        'redis:critical' => 15,
    ],
    
    // Retention (metric history)
    'trim' => [
        'recent' => 60,         // dəqiqə
        'pending' => 60,
        'completed' => 60,
        'failed' => 10080,      // 7 gün
        'monitored' => 10080,
    ],
];
```

---

## Autoscaling strategy

```
balance modes:

  'false' (static):
    processes = 5
    Always 5 worker, heç vaxt dəyişmir.
    Use case: resource limit, predictable workload.
  
  'simple':
    Worker-lər bərabər paylanır queue-lərə.
    queue: ['high', 'low']
    maxProcesses: 10
    → 5 worker "high"-a, 5 "low"-a — qeyd: queue-lər boş olsa da.
    Use case: sadə setup, workload bir qədər stabil.
  
  'auto' (RECOMMENDED):
    Worker-lər workload-a görə dinamik paylanır.
    Boş queue-lərdən worker alınır, yüklü queue-lərə köçürülür.
    
    Strategy:
      'time':  pending job × avg runtime → time estimate
      'size':  pending job count

Nümunə:
  'queue' => ['critical', 'default', 'low']
  'maxProcesses' => 10
  
  Scenario 1: critical=100, default=20, low=5
    Auto: ~7 worker critical-a, ~2 default-a, ~1 low-a
  
  Scenario 2: critical=0, default=0, low=100
    Auto: ~1-2 critical/default (hazır qalsın), ~8 low-a
  
  balanceMaxShift: bir çağırışda neçə worker paylamaq
  balanceCooldown: hər N saniyədə bir yenidən hesabla
```

---

## Job lifecycle, failure, retry

```php
<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 5;              // max attempt
    public int $maxExceptions = 3;      // max unique exception types
    public int $timeout = 60;           // max runtime
    public int $backoff = 10;           // retry delay (array ola bilər)
    
    public function __construct(public readonly int $orderId) {}
    
    // Queue + connection seçmək
    public function viaQueue(): string { return 'payments'; }
    public function viaConnection(): string { return 'redis'; }
    
    // Tags — dashboard-da filter üçün
    public function tags(): array
    {
        return ['payment', "order:{$this->orderId}"];
    }
    
    // Retry delay strategy — exponential
    public function backoff(): array
    {
        return [1, 5, 15, 60];   // 1s, 5s, 15s, 60s
    }
    
    // Rate limit
    public function middleware(): array
    {
        return [
            new RateLimited('payment-api'),
            new WithoutOverlapping($this->orderId),   // eyni order paralel işlənməsin
        ];
    }
    
    public function handle(PaymentService $payment): void
    {
        $order = Order::findOrFail($this->orderId);
        $payment->charge($order);
    }
    
    // Exception → fail
    public function failed(\Throwable $e): void
    {
        Log::error("Payment failed for order {$this->orderId}", [
            'exception' => $e,
        ]);
        
        // User-ə xəbər ver
        OrderPaymentFailedEvent::dispatch($this->orderId);
    }
}
```

```php
<?php
// Rate limiting job
RateLimiter::for('payment-api', function (object $job) {
    return Limit::perMinute(60)->by('stripe-api');
});

// Manual retry — Horizon UI-dən və ya CLI
// php artisan queue:retry all
// php artisan queue:retry 12345
// php artisan queue:forget 12345
```

---

## Metrics & dashboard

```
Horizon dashboard: /horizon

Əsas metriklər:
  - Throughput (jobs/minute)
  - Runtime histogram (per job type)
  - Failed jobs (son 24 saat)
  - Recent jobs
  - Active workers (real-time)
  - Wait time per queue
  - Memory usage per supervisor

Metric explorer:
  Son N saat per queue işləmə rejimi
  Hansı tag (job) ən yavaşdır?
  Peak hour-lar nə vaxtdır?
```

```php
<?php
// Metrics programmatic
use Laravel\Horizon\Contracts\MetricsRepository;

$metrics = app(MetricsRepository::class);

$jobCounts = $metrics->measuredJobs();
$throughput = $metrics->throughputForJob('App\\Jobs\\ProcessPayment');
$runtime   = $metrics->runtimeForJob('App\\Jobs\\ProcessPayment');

// Snapshots (Redis-də saxlanır)
$snapshots = $metrics->snapshotsForJob('App\\Jobs\\ProcessPayment');
```

---

## Dead job management

```
Failed job — "tries" bitdikdən sonra uğursuz iş.
DB-də failed_jobs cədvəlində saxlanır (və ya Redis-də).

Horizon dashboard-da:
  - Exception stack trace
  - Job payload
  - Retry əmri (bir klikdə)
  - Delete

php artisan queue:failed          # siyahı
php artisan queue:retry 12345     # retry one
php artisan queue:retry all       # retry all
php artisan queue:forget 12345    # delete
php artisan queue:flush           # delete all failed
```

```php
<?php
// Custom failure handler
use Illuminate\Queue\Events\JobFailed;

Queue::failing(function (JobFailed $event) {
    // Slack alert
    Notification::route('slack', config('alerts.slack'))
        ->notify(new JobFailedNotification($event));
});

// Auto-retry with different strategy
Queue::failing(function (JobFailed $event) {
    if ($event->exception instanceof TransientException) {
        // Silent, requeue with delay
        dispatch($event->job->getPayload()['data'])
            ->delay(now()->addMinutes(10));
    }
});
```

---

## Deployment considerations

```bash
# 1. Horizon graceful restart (deploy sonrası)
php artisan horizon:terminate
# Horizon supervisor mövcud job-ları bitirir, yeni job qəbul etmir
# Sonra Supervisor/systemd yenidən başladır

# 2. Supervisor config
# /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /var/www/app/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/horizon.log
stopwaitsecs=3600   # graceful timeout

# supervisorctl reread
# supervisorctl update
# supervisorctl start horizon

# 3. Kubernetes deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: horizon
spec:
  replicas: 1          # YALNIZ 1 HORIZON MASTER OLMALIDIR (multi-node ÜÇÜN)
  strategy:
    type: Recreate
  template:
    spec:
      containers:
      - name: horizon
        image: myapp:latest
        command: ["php", "artisan", "horizon"]
        lifecycle:
          preStop:
            exec:
              command: ["php", "artisan", "horizon:terminate"]
        terminationGracePeriodSeconds: 3600

# ⚠ Çox vacib: Eyni Redis queue-yə bağlı birdən çox Horizon master işlətmə!
# Autoscale logic konflikt yaradır. Bir master, çoxlu worker.
```

---

## Production troubleshooting

```
Problem                         | Diaqnoz                                  | Həll
──────────────────────────────────────────────────────────────────────────────────
Job-lar saxlanır, işlənmir      | Supervisor çökmüş?                       | horizon:status; restart
                                 | maxProcesses=0?                          | config yoxla
                                 | Redis down?                              | redis-cli ping

Queue çox yavaş                  | Worker sayı az                          | maxProcesses artır
                                 | Job runtime uzun                        | profile, optimize
                                 | DB connection exhaust                   | DB pool artır

Memory leak (worker OOM)         | Eloquent model cache                    | model::unsetRelations()
                                 | Log::info() çox                          | log channel dəyişdir
                                 | maxJobs=0                                | maxJobs=1000 qoy

Failed job lots                  | External API rate limit                 | RateLimited middleware
                                 | Timeout too short                       | timeout artır
                                 | Retry logic yoxdur                       | tries artır

Queue wait time yüksək           | Burst traffic                            | autoScaling 'auto'
                                 | Priority queue düzgün deyil              | critical queue ayır

Horizon dashboard yavaş          | Metric retention böyük                   | trim config azalt
                                 | Redis memory dolub                       | redis MAXMEMORY policy
```

```bash
# Debug commands
php artisan horizon:status              # running/stopped
php artisan horizon:list                # supervisors list
php artisan horizon:snapshot            # force metric snapshot
php artisan queue:monitor redis:default --max=100   # alert if backlog > 100

# Monitor specific queue
php artisan tinker
>>> Queue::size('default')
=> 45   # pending job count

>>> Redis::hgetall('horizon:supervisors')   # active supervisors
```

---

## Horizon alternativləri

```
Alternative                     | Use case
───────────────────────────────────────────────────────────────
Supervisord (statik)           | Sadə setup, monitoring yoxdur
Laravel Queue:work (bare)      | CI/CD, testing
Symfony Messenger              | Symfony framework stack
RabbitMQ + rabbit-mq-manager   | Message queue, RabbitMQ-centric
Gearman + GearmanManager       | Classical distributed work
RoadRunner Jobs (built-in)     | Octane, Go-based worker pool
Temporal / Conductor           | Long-running workflow, not just queue

Horizon niyə secilir Laravel üçün?
  ✓ Laravel-native, sıfır adaptation
  ✓ Beautiful UI
  ✓ Autoscaling built-in
  ✓ Team-ə "queue visibility" verir
  
Horizon çatışmazlıq:
  ✗ Redis only (Amazon SQS, RabbitMQ dəstək yoxdur)
  ✗ Multi-master yox (bir server single point)
  ✗ Storage backend dəyişdirilə bilmir
```

---

## İntervyu Sualları

- Laravel Horizon ilə adi Supervisor arasındakı fərq nədir?
- `balance: auto` necə işləyir?
- `maxJobs` dəyərinin mənası nədir? Niyə 0 təhlükəlidir?
- Failed job-ların retry strategiyasını necə təyin edirsiniz?
- `WithoutOverlapping` middleware nəyə xidmət edir?
- Horizon master birdən çox server-də necə işləyir? (Trick question)
- Exponential backoff job-da necə konfiqurasiya olunur?
- Rate-limit external API üçün job middleware necə yazılır?
- Deploy zamanı Horizon graceful restart necə olur?
- Queue wait time yüksəkdir — hansı addımları atırsınız?
- Memory leak-li worker necə aşkarlanır?
- Horizon metric retention (`trim`) niyə optimize edilir?
