# Use Case: Delayed & Scheduled Jobs at Scale

## Problem
- "Email reminder 7 gün sonra" — milyonlarla pending job
- Subscription renewal hər ay
- Trial expiration alert
- Cart abandonment 24 saat sonra
- Time-sensitive notifications (10:00 AM user TZ)

---

## Həll: Multiple strategy

```
Job müddəti       | Strategy
─────────────────────────────────────────────────────────
< 1 saat         | Redis ZSET (delayed queue)
< 1 gün          | Laravel Queue ->delay()
1-30 gün         | Database table + cron poller
> 30 gün         | Calendar/scheduled service (AWS EventBridge)
Cron-based       | Laravel Scheduler
User timezone    | Hourly tick + per-user evaluation
```

---

## 1. Short delay (Laravel built-in)

```php
<?php
// Sadə case — Redis ZSET arxa planda
SendEmail::dispatch($userId)->delay(now()->addMinutes(15));

// Conditional retry
SendEmail::dispatch($userId)
    ->delay(now()->addMinutes(30))
    ->onQueue('emails');

// Schedule at specific time
SendEmail::dispatch($userId)
    ->delay(now()->setTime(10, 0));   // bu gün 10:00

// Bütün delay-lər queue worker tərəfindən polling olunur
// php artisan queue:work --queue=emails
```

---

## 2. Long-term delayed jobs (DB-based)

```sql
CREATE TABLE scheduled_jobs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    job_class VARCHAR(255),
    payload JSON,
    scheduled_for TIMESTAMP,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled'),
    attempts INT DEFAULT 0,
    last_error TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_status_scheduled (status, scheduled_for),
    INDEX idx_class (job_class)
);
```

```php
<?php
class ScheduledJobService
{
    public function schedule(string $jobClass, array $payload, \DateTime $when): int
    {
        return DB::table('scheduled_jobs')->insertGetId([
            'job_class'     => $jobClass,
            'payload'       => json_encode($payload),
            'scheduled_for' => $when,
            'status'        => 'pending',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
    
    public function cancel(int $jobId): void
    {
        DB::table('scheduled_jobs')
            ->where('id', $jobId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
    }
}

// Schedule
$svc = app(ScheduledJobService::class);
$svc->schedule(
    SendTrialEndingEmail::class,
    ['user_id' => 42],
    now()->addDays(7)
);
```

---

## 3. Poller (every minute)

```php
<?php
// app/Console/Commands/ScheduledJobPoller.php
class ScheduledJobPoller extends Command
{
    protected $signature = 'scheduled-jobs:poll';
    
    public function handle(): int
    {
        // FOR UPDATE SKIP LOCKED — multi-worker safe
        $jobs = DB::transaction(function () {
            $rows = DB::table('scheduled_jobs')
                ->where('status', 'pending')
                ->where('scheduled_for', '<=', now())
                ->orderBy('scheduled_for')
                ->limit(100)
                ->lockForUpdate()    // SKIP LOCKED — Postgres
                ->get();
            
            // Mark as processing
            DB::table('scheduled_jobs')
                ->whereIn('id', $rows->pluck('id'))
                ->update(['status' => 'processing', 'updated_at' => now()]);
            
            return $rows;
        });
        
        $this->info("Dispatching {$jobs->count()} jobs");
        
        foreach ($jobs as $job) {
            try {
                $jobClass = $job->job_class;
                $payload = json_decode($job->payload, true);
                
                // Dispatch to queue (sync execute etmə)
                dispatch(new $jobClass(...$payload))
                    ->onQueue('scheduled-execution');
                
                DB::table('scheduled_jobs')
                    ->where('id', $job->id)
                    ->update(['status' => 'completed', 'updated_at' => now()]);
            } catch (\Throwable $e) {
                DB::table('scheduled_jobs')
                    ->where('id', $job->id)
                    ->update([
                        'status'      => $job->attempts >= 3 ? 'failed' : 'pending',
                        'attempts'    => $job->attempts + 1,
                        'last_error'  => $e->getMessage(),
                        'scheduled_for' => $job->attempts >= 3 ? null : now()->addMinutes(5),
                        'updated_at'  => now(),
                    ]);
            }
        }
        
        return self::SUCCESS;
    }
}

// Schedule
Schedule::command('scheduled-jobs:poll')->everyMinute();
```

---

## 4. User timezone-aware scheduling

```php
<?php
// Problem: "Email göndər user-in saat 10:00-da"
// User-lər müxtəlif TZ-də

class TimezoneAwareScheduler
{
    public function scheduleDailyReminder(User $user): void
    {
        $userTz = new \DateTimeZone($user->timezone ?? 'UTC');
        $next10am = (new \DateTime('today 10:00', $userTz))->modify('+1 day');
        
        // UTC-yə convert et (DB həmişə UTC saxlayır)
        $utc = $next10am->setTimezone(new \DateTimeZone('UTC'));
        
        DB::table('scheduled_jobs')->insert([
            'job_class'     => SendDailyReminder::class,
            'payload'       => json_encode(['user_id' => $user->id]),
            'scheduled_for' => $utc->format('Y-m-d H:i:s'),
            'status'        => 'pending',
        ]);
    }
}

// Alternativ: hourly poll + per-user check
class HourlyTimezoneCheck extends Command
{
    public function handle(): void
    {
        // Bütün TZ-lər üçün indiki saat 10 olan user-ləri tap
        $users = User::all()->filter(function ($u) {
            $userNow = new \DateTime('now', new \DateTimeZone($u->timezone));
            return (int) $userNow->format('G') === 10;   // saat 10
        });
        
        foreach ($users as $user) {
            SendDailyReminderJob::dispatch($user->id);
        }
    }
}

// Schedule hər saat
Schedule::command('reminder:hourly-check')->hourly();
```

---

## 5. Recurring (subscription renewal)

```php
<?php
class SubscriptionRenewalScheduler
{
    public function scheduleNext(Subscription $sub): void
    {
        $nextBilling = match ($sub->cycle) {
            'monthly'  => $sub->current_period_end->copy()->addMonth(),
            'yearly'   => $sub->current_period_end->copy()->addYear(),
            'weekly'   => $sub->current_period_end->copy()->addWeek(),
        };
        
        DB::table('scheduled_jobs')->insert([
            'job_class'     => RenewSubscriptionJob::class,
            'payload'       => json_encode(['subscription_id' => $sub->id]),
            'scheduled_for' => $nextBilling,
            'status'        => 'pending',
        ]);
    }
}

class RenewSubscriptionJob implements ShouldQueue
{
    public function handle(Subscription $sub, PaymentGateway $payment): void
    {
        try {
            $charge = $payment->charge($sub->user, $sub->amount);
            $sub->update([
                'current_period_end' => match ($sub->cycle) {
                    'monthly' => $sub->current_period_end->addMonth(),
                    'yearly'  => $sub->current_period_end->addYear(),
                },
            ]);
            
            // Schedule next
            app(SubscriptionRenewalScheduler::class)->scheduleNext($sub);
        } catch (PaymentFailed $e) {
            // Dunning management — 3 retry sonra cancel
            $this->handleFailedRenewal($sub, $e);
        }
    }
    
    private function handleFailedRenewal(Subscription $sub, $e): void
    {
        $sub->failed_attempts++;
        $sub->save();
        
        if ($sub->failed_attempts < 3) {
            // Retry 1, 3, 7 gün sonra
            $delay = [1, 3, 7][$sub->failed_attempts - 1];
            DB::table('scheduled_jobs')->insert([
                'job_class'     => self::class,
                'payload'       => json_encode(['subscription_id' => $sub->id]),
                'scheduled_for' => now()->addDays($delay),
                'status'        => 'pending',
            ]);
            
            Mail::to($sub->user)->send(new PaymentFailedMail($sub, $delay));
        } else {
            $sub->update(['status' => 'cancelled']);
            Mail::to($sub->user)->send(new SubscriptionCancelledMail($sub));
        }
    }
}
```

---

## 6. Cancellation handling

```php
<?php
// User trial cancel etdi — pending email-ləri ləğv et
class CancelUserScheduledJobsListener
{
    public function handle(UserCancelledTrial $event): void
    {
        DB::table('scheduled_jobs')
            ->where('payload->user_id', $event->userId)
            ->where('status', 'pending')
            ->whereIn('job_class', [
                SendTrialEndingEmail::class,
                SendTrialEndedEmail::class,
                ChargeForSubscriptionJob::class,
            ])
            ->update(['status' => 'cancelled']);
    }
}
```

---

## 7. Performance optimization

```php
<?php
// Problem: 10M pending job → poller hər dəqiqə LOCK-dur
// Həll: shard by ID range + multiple worker

class ShardedPoller extends Command
{
    protected $signature = 'scheduled-jobs:poll {--shard=0} {--total=4}';
    
    public function handle(): int
    {
        $shard = (int) $this->option('shard');
        $total = (int) $this->option('total');
        
        $jobs = DB::table('scheduled_jobs')
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->whereRaw('id % ? = ?', [$total, $shard])
            ->limit(100)
            ->get();
        
        // Process...
    }
}

// 4 worker paralel
// php artisan scheduled-jobs:poll --shard=0 --total=4
// php artisan scheduled-jobs:poll --shard=1 --total=4
// ...
```

---

## 8. AWS EventBridge alternative

```php
<?php
// Long-term scheduling (cron-style)
use Aws\EventBridge\EventBridgeClient;

$client = new EventBridgeClient([/* ... */]);

// Tək vaxtlı schedule
$client->putRule([
    'Name'               => "user-trial-end-{$userId}",
    'ScheduleExpression' => 'at(2026-05-01T10:00:00)',
    'State'              => 'ENABLED',
]);

$client->putTargets([
    'Rule'    => "user-trial-end-{$userId}",
    'Targets' => [[
        'Id'    => '1',
        'Arn'   => 'arn:aws:lambda:...:function:trigger-app',
        'Input' => json_encode(['user_id' => $userId, 'event' => 'trial_end']),
    ]],
]);

// Lambda → app webhook → handle event

// Üstünlük:
//   ✓ AWS managed (heç bir DB lazım deyil)
//   ✓ Years-long schedule mümkün
//   ✓ Cron expression
//   ✗ External dependency
//   ✗ Latency yüksək (~30s)
```

---

## 9. Pitfalls

```
❌ At-least-once delivery — eyni job 2 dəfə işlənə bilər
   ✓ Idempotent job design (UNIQUE check, idempotency key)

❌ Polling lock contention — 10 worker eyni row-lara
   ✓ FOR UPDATE SKIP LOCKED, ya da shard

❌ TZ change — DST keçidi (1 saat fərq)
   ✓ TZ-aware comparison, UTC saxla, hourly tick safety net

❌ Job class refactoring — pending job-lar köhnə class-a istinad
   ✓ Backward-compatible aliases, ya da migration script

❌ Long-pending jobs lost — 30+ gün
   ✓ Heartbeat metric "oldest pending age"

❌ Failure not retried — silent fail
   ✓ Failed jobs dashboard, alert
```
