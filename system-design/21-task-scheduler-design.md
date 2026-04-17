# Task Scheduler Design

## Nədir? (What is it?)

Task scheduler müəyyən vaxtda və ya müntəzəm intervallarla tapşırıqları avtomatik
icra edən sistemdir. Cron jobs, delayed jobs, recurring tasks, və distributed
scheduling əhatə edir. Email göndərmə, report generation, data cleanup, billing
kimi əməliyyatlar scheduler ilə idarə olunur.

Sadə dillə: saat zəngi kimi - müəyyən vaxtda sizi oyandırır (task-ı icra edir).
Hər gün, hər həftə, və ya bir dəfəlik ola bilər.

```
Schedule Definition                    Execution
  │                                      │
  ▼                                      ▼
┌──────────────┐    ┌──────────┐    ┌──────────┐
│ "Every day   │───▶│Scheduler │───▶│  Worker  │
│  at 3:00 AM  │    │  Engine  │    │  Process │
│  run cleanup"│    │          │    │          │
└──────────────┘    └──────────┘    └──────────┘
```

## Əsas Konseptlər (Key Concepts)

### Scheduling Types

```
1. Cron-based:     "*/5 * * * *"     (every 5 minutes)
2. Fixed delay:    "every 30 seconds after last completion"
3. Fixed rate:     "every 30 seconds regardless of completion"
4. One-time:       "run at 2024-12-25 00:00:00"
5. Event-triggered: "run when file uploaded"

Cron Expression:
  ┌───────── minute (0-59)
  │ ┌─────── hour (0-23)
  │ │ ┌───── day of month (1-31)
  │ │ │ ┌─── month (1-12)
  │ │ │ │ ┌─ day of week (0-7, 0=Sun)
  * * * * *

  0 3 * * *     → Every day at 3:00 AM
  */15 * * * *  → Every 15 minutes
  0 0 1 * *     → First day of every month
  0 9 * * 1-5   → Weekdays at 9:00 AM
```

### Distributed Scheduling Challenges

```
Problem: Multiple servers, each running scheduler

Server 1: [Scheduler] → Run cleanup job ──┐
Server 2: [Scheduler] → Run cleanup job ──┤── Same job runs 3 times!
Server 3: [Scheduler] → Run cleanup job ──┘

Solutions:
1. Single scheduler (leader election)
2. Distributed lock (Redis/DB lock)
3. Database-based scheduling (one server claims job)
4. Dedicated scheduler service
```

### Job Queue Architecture

```
┌──────────┐     ┌───────────┐     ┌──────────┐
│Scheduler │────▶│   Queue   │────▶│ Workers  │
│          │     │           │     │          │
│ Schedule │     │ ┌───────┐ │     │ Worker 1 │
│ Manager  │     │ │ Job A │ │     │ Worker 2 │
│          │     │ │ Job B │ │     │ Worker 3 │
│          │     │ │ Job C │ │     │ ...      │
└──────────┘     │ └───────┘ │     └──────────┘
                 └───────────┘
```

### Retry and Error Handling

```
Job Execution:
  Attempt 1: Failed (timeout)     → retry after 60s
  Attempt 2: Failed (exception)   → retry after 120s
  Attempt 3: Failed (exception)   → retry after 240s
  Attempt 4: Failed               → move to failed_jobs, alert team

Backoff Strategies:
  Fixed:       60s, 60s, 60s
  Linear:      60s, 120s, 180s
  Exponential: 60s, 120s, 240s, 480s
  With jitter: 60s ± random(30s)  (prevent thundering herd)
```

## Arxitektura (Architecture)

### Distributed Task Scheduler

```
┌────────────────────────────────────────────┐
│            Scheduler Service               │
│                                            │
│  ┌──────────────┐  ┌───────────────────┐  │
│  │ Schedule     │  │ Task Registry     │  │
│  │ Store (DB)   │  │                   │  │
│  │              │  │ - CleanupJob      │  │
│  │ cron, next   │  │ - ReportJob       │  │
│  │ run time,    │  │ - BillingJob      │  │
│  │ last run     │  │ - SyncJob         │  │
│  └──────┬───────┘  └───────────────────┘  │
│         │                                  │
│  ┌──────┴───────┐                         │
│  │ Tick Engine  │ (every second, check     │
│  │              │  if any task should run)  │
│  └──────┬───────┘                         │
└─────────┼─────────────────────────────────┘
          │
   ┌──────┴──────┐
   │ Job Queue   │
   │ (Redis/SQS) │
   └──────┬──────┘
          │
   ┌──────┼──────────────┐
   │      │              │
┌──┴───┐ ┌┴─────┐ ┌─────┴┐
│Work 1│ │Work 2│ │Work 3│
└──────┘ └──────┘ └──────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Task Scheduling

```php
// app/Console/Kernel.php
class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Basic scheduling
        $schedule->command('orders:cleanup-expired')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/cleanup.log'));

        // Daily report at 6 AM
        $schedule->command('reports:daily')
            ->dailyAt('06:00')
            ->onOneServer()
            ->emailOutputOnFailure('admin@example.com');

        // Every 5 minutes
        $schedule->command('queue:monitor', ['default', '--max=100'])
            ->everyFiveMinutes();

        // Weekly on Monday
        $schedule->command('billing:generate-invoices')
            ->weeklyOn(1, '02:00')
            ->onOneServer()
            ->before(function () {
                Log::info('Starting weekly invoice generation');
            })
            ->after(function () {
                Log::info('Completed weekly invoice generation');
            });

        // Job dispatch on schedule
        $schedule->job(new ProcessAnalytics, 'analytics')
            ->everyThirtyMinutes()
            ->onOneServer();

        // Closure-based
        $schedule->call(function () {
            DB::table('sessions')
                ->where('last_activity', '<', now()->subHours(24))
                ->delete();
        })->daily()->description('Clean expired sessions');

        // Conditional scheduling
        $schedule->command('telescope:prune')
            ->daily()
            ->when(function () {
                return app()->environment('production');
            });
    }
}
```

### Custom Scheduled Jobs

```php
// app/Jobs/GenerateDailyReport.php
class GenerateDailyReport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;   // 5 minutes
    public int $tries = 3;
    public int $backoff = 60;
    public int $uniqueFor = 3600; // Unique for 1 hour

    public function __construct(
        private Carbon $date
    ) {}

    public function handle(ReportService $reports): void
    {
        $report = $reports->generateDaily($this->date);

        // Store report
        Storage::disk('s3')->put(
            "reports/{$this->date->format('Y/m/d')}/daily.pdf",
            $report->toPdf()
        );

        // Notify admins
        User::role('admin')->each(function ($admin) use ($report) {
            $admin->notify(new DailyReportReady($report));
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Daily report generation failed', [
            'date' => $this->date->toDateString(),
            'error' => $exception->getMessage(),
        ]);

        // Alert ops team
        Notification::route('slack', config('services.slack.ops_channel'))
            ->notify(new JobFailedNotification('GenerateDailyReport', $exception));
    }

    public function uniqueId(): string
    {
        return $this->date->toDateString();
    }
}
```

### Database-Based Task Scheduler

```php
// Migration
Schema::create('scheduled_tasks', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('command');
    $table->string('cron_expression');
    $table->json('parameters')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_run_at')->nullable();
    $table->timestamp('next_run_at')->nullable();
    $table->string('last_status')->nullable(); // success, failed
    $table->text('last_output')->nullable();
    $table->integer('timeout')->default(60);
    $table->integer('max_retries')->default(3);
    $table->timestamps();
});

// Dynamic Scheduler
class DynamicScheduler
{
    public function registerTasks(Schedule $schedule): void
    {
        $tasks = ScheduledTask::where('is_active', true)->get();

        foreach ($tasks as $task) {
            $event = $schedule->command($task->command, $task->parameters ?? [])
                ->cron($task->cron_expression)
                ->onOneServer()
                ->withoutOverlapping($task->timeout)
                ->before(function () use ($task) {
                    $task->update(['last_run_at' => now()]);
                })
                ->after(function () use ($task) {
                    $task->update([
                        'last_status' => 'success',
                        'next_run_at' => CronExpression::factory($task->cron_expression)
                            ->getNextRunDate(),
                    ]);
                })
                ->onFailure(function () use ($task) {
                    $task->update(['last_status' => 'failed']);
                });
        }
    }
}
```

### Distributed Lock for Scheduling

```php
// Ensure job runs on only one server
class DistributedScheduleLock
{
    public function acquireLock(string $taskName, int $ttlSeconds = 300): bool
    {
        return Cache::lock("schedule:{$taskName}", $ttlSeconds)->get();
    }

    public function releaseLock(string $taskName): void
    {
        Cache::lock("schedule:{$taskName}")->release();
    }
}

// Usage in custom scheduler
class CustomScheduler
{
    public function __construct(
        private DistributedScheduleLock $lock,
    ) {}

    public function runIfNotLocked(string $taskName, callable $task): void
    {
        if (!$this->lock->acquireLock($taskName)) {
            Log::debug("Task {$taskName} already running on another server");
            return;
        }

        try {
            $task();
        } finally {
            $this->lock->releaseLock($taskName);
        }
    }
}
```

### Queue Monitoring

```php
class QueueMonitorCommand extends Command
{
    protected $signature = 'queue:health-check';
    protected $description = 'Check queue health and alert if issues';

    public function handle(): int
    {
        $queues = ['default', 'notifications', 'reports', 'analytics'];

        foreach ($queues as $queue) {
            $size = Queue::size($queue);
            $failedCount = DB::table('failed_jobs')
                ->where('queue', $queue)
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            // Alert if queue is backing up
            if ($size > 1000) {
                Log::warning("Queue {$queue} has {$size} pending jobs");
                $this->alertOps("Queue Alert: {$queue} has {$size} jobs");
            }

            // Alert on high failure rate
            if ($failedCount > 10) {
                Log::error("Queue {$queue} has {$failedCount} failures in last hour");
                $this->alertOps("Queue Failure: {$queue} - {$failedCount} failures/hour");
            }
        }

        return self::SUCCESS;
    }
}
```

## Real-World Nümunələr

1. **Airflow (Apache)** - DAG-based task orchestration, Python
2. **Celery** - Distributed task queue for Python
3. **Sidekiq** - Background job processor for Ruby
4. **AWS EventBridge** - Serverless event scheduling
5. **Kubernetes CronJobs** - Container-based scheduled tasks

## Interview Sualları

**S1: Distributed mühitdə job-un bir dəfə icra olunmasını necə təmin edirsiniz?**
C: Distributed lock (Redis SETNX), database advisory lock, leader election.
Laravel-də `onOneServer()` method Redis lock istifadə edir. Lock TTL
job timeout-dan böyük olmalıdır.

**S2: Job failure necə idarə olunur?**
C: Retry with exponential backoff, dead letter queue (failed_jobs table),
alerting on failure, circuit breaker pattern. Laravel `$tries`, `$backoff`,
`failed()` method ilə idarə edir.

**S3: Long-running job server restart olsa nə baş verir?**
C: Job queue-da qalır, başqa worker pick up edir. Progress tracking üçün
checkpoint mechanism: job öz progress-ini cache/DB-yə yazır, restart olsa
son checkpoint-dən davam edir.

**S4: Priority scheduling necə implement olunur?**
C: Fərqli priority queue-lar yaradın (critical, high, default, low).
Worker-lər əvvəl yüksək priority queue-dan işləyir. Laravel-də
`--queue=critical,high,default` ilə priority sırası təyin olunur.

**S5: Task dependency necə idarə olunur?**
C: Job chaining: Job A tamamlandan sonra Job B başlayır.
Laravel Bus::chain([JobA, JobB, JobC]). DAG (Directed Acyclic Graph)
ilə complex dependency-lər idarə oluna bilər (Airflow yanaşması).

**S6: Cron job vs message queue fərqi nədir?**
C: Cron - zaman əsaslı, periodic, sabit interval. Queue - event əsaslı,
on-demand, variable load. Cron: "hər gün saat 3-də", Queue: "order yarananda
email göndər". Tez-tez birlikdə istifadə olunur.

**S7: Scheduler bottleneck olsa nə edirsiniz?**
C: Scheduler yalnız dispatch edir, ağır işi workers görür. Worker sayını
artırın (horizontal scaling). Queue-ları bölün (queue per job type).
Auto-scaling: queue size-a görə worker sayı artır/azalır.

## Best Practices

1. **Idempotent Jobs** - Eyni job iki dəfə icra olunsa eyni nəticə versin
2. **Timeout Setting** - Hər job üçün timeout təyin edin
3. **onOneServer()** - Distributed mühitdə duplicate prevention
4. **withoutOverlapping()** - Eyni job overlap etməsin
5. **Monitoring** - Queue size, job duration, failure rate track edin
6. **Graceful Shutdown** - SIGTERM-da current job-u tamamlayın
7. **Retry Strategy** - Exponential backoff with jitter
8. **Failed Job Handling** - Alert + manual retry interface
9. **Job Batching** - Böyük taskları kiçik batch-lara bölün
10. **Logging** - Hər job-un start/end/duration-ını log edin
