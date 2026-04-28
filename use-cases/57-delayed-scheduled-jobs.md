# Delayed & Scheduled Jobs at Scale (Lead)

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

---

## Problem niyə yaranır?

`dispatch()->delay()` Laravel-in ən sadə scheduled job mexanizmidir və kiçik miqyasda əla işləyir. Lakin milyonlarla job söhbətinə gəldikdə, bu yanaşmanın əsas problemi ortaya çıxır: **Redis yaddaşda saxlanılan bütün job-ların payload-larını eyni anda tutur.** Hər job ortalama 1–5 KB tutduğunu fərz etsək, 10 milyon pending "7-day reminder" job-u 10–50 GB Redis RAM deməkdir. Bundan əlavə, Laravel Redis queue-da delayed job-ları `ZSET` (sorted set) data strukturunda saxlayır — key scheduled timestamp, value isə serialized job payload-dur. Queue worker hər saniyə bu ZSET-i polling edir, vaxtı keçmiş job-ları çıxarır və real queue-ya keçirir. 10 milyon elementli ZSET-də bu polling əməliyyatı getdikcə bahalılaşır, özellikle çox sayda worker eyni anda `ZRANGEBYSCORE` əmri göndərəndə Redis-də ciddi lock contention yaranır.

İkinci böyük problem **cancellation mexanizminin olmamasıdır.** Redis ZSET-ə bir dəfə yazılmış job-u ləğv etmək texniki cəhətdən demək olar ki, mümkünsüzdür — job-un tam payload-unu bilmədən onu ZSET-dən çıxara bilməzsiniz, job ID-si ilə birbaşa lookup isə Redis ZSET-in dəstəkləmədiyi bir əməliyyatdır. Bu o deməkdir ki, istifadəçi abunəliyi ləğv edəndə artıq queue-ya yazılmış "trial ending" email-ləri yenə də göndəriləcək — əgər job özü başlanğıcda bir yoxlama etmirərsə.

Üçüncü problem **timezone handling**-dir. İstifadəçi-spesifik bildirişlər üçün "hər gün saat 10:00-da notification göndər" tələbi əslində çox mürəkkəbdir. Dünya üzrə 400-dən çox timezone mövcuddur, DST (Daylight Saving Time) keçidləri ilə vaxtlar ildə 2 dəfə dəyişir. `dispatch()->delay(now()->setTime(10, 0))` serverin timezone-unda (adətən UTC) işləyir — istifadəçinin yerli saatında deyil. Əgər 1 milyon istifadəçi üçün ayrı-ayrı delay-lə job dispatch etsəniz, Redis həm memory, həm də CPU baxımından çökər. Düzgün yanaşma isə DB-based scheduler ilə hər istifadəçi üçün UTC-yə convert edilmiş scheduled_for vaxtını saxlamaq və ya hourly tick + per-user evaluation pattern istifadə etməkdir.

---

## Job Cancellation (Tam Implementation)

DB-based yanaşmada cancellation sadədir: job dispatch edilərkən DB-yə yazılır, job özü işləməzdən əvvəl `cancelled` yoxlaması edir.

```php
<?php
// app/Models/ScheduledJob.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledJob extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'payload',
        'scheduled_for',
        'status',
    ];

    protected $casts = [
        'payload'       => 'array',
        'scheduled_for' => 'datetime',
    ];

    public function isCancelled(): bool
    {
        // DB-dən fresh oxu — cache-dən deyil
        return $this->fresh()->status === 'cancelled';
    }
}
```

```php
<?php
// app/Services/ScheduledJobService.php
namespace App\Services;

use App\Jobs\SendScheduledEmail;
use App\Models\ScheduledJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduledJobService
{
    public function scheduleEmail(User $user, string $type, Carbon $scheduledFor): ScheduledJob
    {
        $job = ScheduledJob::create([
            'user_id'       => $user->id,
            'type'          => $type,
            'payload'       => ['user_id' => $user->id, 'type' => $type],
            'scheduled_for' => $scheduledFor,
            'status'        => 'pending',
        ]);

        // Job dispatch edilir, amma job özü başlanğıcda status yoxlayır
        SendScheduledEmail::dispatch($job->id)->delay($scheduledFor);

        Log::info('Scheduled email job created', [
            'job_id'         => $job->id,
            'user_id'        => $user->id,
            'type'           => $type,
            'scheduled_for'  => $scheduledFor->toISOString(),
        ]);

        return $job;
    }

    public function cancel(ScheduledJob $job): bool
    {
        // Yalnız pending job-u cancel edə bilərik
        $updated = ScheduledJob::where('id', $job->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        if ($updated) {
            Log::info('Scheduled job cancelled', ['job_id' => $job->id]);
        }

        return (bool) $updated;
    }

    public function cancelAllForUser(int $userId, ?string $type = null): int
    {
        $query = ScheduledJob::where('user_id', $userId)
            ->where('status', 'pending');

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->update(['status' => 'cancelled']);
    }
}
```

```php
<?php
// app/Jobs/SendScheduledEmail.php
namespace App\Jobs;

use App\Models\ScheduledJob;
use App\Notifications\ScheduledEmailNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // saniyə

    public function __construct(
        private readonly int $scheduledJobId
    ) {}

    public function handle(): void
    {
        // 1. DB-dən fresh status oxu
        $scheduledJob = ScheduledJob::find($this->scheduledJobId);

        if (!$scheduledJob) {
            Log::warning('Scheduled job record not found', [
                'scheduled_job_id' => $this->scheduledJobId,
            ]);
            return;
        }

        // 2. Cancellation yoxlaması — ən vacib addım
        if ($scheduledJob->status === 'cancelled') {
            Log::info('Scheduled job is cancelled, skipping execution', [
                'scheduled_job_id' => $this->scheduledJobId,
            ]);
            return; // Heç bir əməliyyat etmə, sadəcə çıx
        }

        // 3. Artıq işlənibsə (at-least-once delivery edge case)
        if ($scheduledJob->status === 'completed') {
            Log::info('Scheduled job already completed, skipping', [
                'scheduled_job_id' => $this->scheduledJobId,
            ]);
            return;
        }

        // 4. Processing-ə keç
        $scheduledJob->update(['status' => 'processing']);

        try {
            $user = User::findOrFail($scheduledJob->payload['user_id']);

            // 5. User hələ də aktiv yoxlaması (əlavə guard)
            if (!$user->active) {
                Log::info('User is inactive, skipping scheduled email', [
                    'user_id' => $user->id,
                ]);
                $scheduledJob->update(['status' => 'cancelled']);
                return;
            }

            // 6. Əsl iş
            $user->notify(new ScheduledEmailNotification($scheduledJob->type));

            $scheduledJob->update(['status' => 'completed']);

            Log::info('Scheduled email sent successfully', [
                'scheduled_job_id' => $this->scheduledJobId,
                'user_id'          => $user->id,
                'type'             => $scheduledJob->type,
            ]);

        } catch (\Throwable $e) {
            $scheduledJob->update([
                'status'     => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            Log::error('Scheduled email job failed', [
                'scheduled_job_id' => $this->scheduledJobId,
                'error'            => $e->getMessage(),
            ]);

            throw $e; // Queue-ya retry üçün rethrow et
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Bütün retry-lar bitdikdən sonra çağırılır
        ScheduledJob::where('id', $this->scheduledJobId)
            ->update(['status' => 'failed']);

        Log::error('Scheduled job permanently failed after all retries', [
            'scheduled_job_id' => $this->scheduledJobId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
```

```php
<?php
// app/Http/Controllers/ScheduledJobController.php
namespace App\Http\Controllers;

use App\Models\ScheduledJob;
use App\Services\ScheduledJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledJobController extends Controller
{
    public function __construct(
        private ScheduledJobService $service
    ) {}

    public function cancel(Request $request, ScheduledJob $scheduledJob): JsonResponse
    {
        // Authorization: yalnız öz job-unu cancel edə bilər
        if ($scheduledJob->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cancelled = $this->service->cancel($scheduledJob);

        if (!$cancelled) {
            return response()->json([
                'error'   => 'Job artıq işlənmiş və ya ləğv edilmişdir.',
                'status'  => $scheduledJob->fresh()->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Scheduled job ləğv edildi.',
            'job_id'  => $scheduledJob->id,
        ]);
    }
}
```

---

## Trade-offs

| Strategiya | Max delay | Reliability | Complexity | Nə zaman istifadə et |
|------------|-----------|-------------|------------|----------------------|
| **Redis ZSET** (`->delay()`) | ~7 gün tövsiyə | Medium — Redis restart-da itə bilər | Aşağı | Sadə, qısamüddətli delay (< 1 gün); cancellation tələb olunmur |
| **Laravel Queue `->delay()`** | Texniki limitsiz, praktik < 1 ay | Medium | Aşağı | Prototip, az həcmli job; production-da tövsiyə olunmur |
| **DB-based poller** | Limitsiz | Yüksək — DB ACID, izlənilə bilir | Orta | Cancellation lazımdır; milyonlarla job; audit trail tələb olunur |
| **AWS EventBridge Scheduler** | 1 ilədək | Çox yüksək — AWS managed | Yüksək (vendor setup) | Çox uzunmüddətli schedule; serverless arxitektura; external trigger |
| **Hourly tick + per-user eval** | N/A (recurring) | Yüksək | Orta | User timezone-a görə notification; recurring reminder |

---

## Anti-patternlər

**1. Milyonlarla job-u Redis-də saxlamaq**
`->delay()` ilə 10M job dispatch etmək Redis-i memory baxımından öldürür. Hər job payload ortalama 2–5 KB-dır; 10M job 20–50 GB RAM deməkdir. Bunun üstəgəl ZSET polling performansı da aşağı düşür. Həll: DB-based scheduler istifadə edin; Redis yalnız qısamüddətli (< 1 gün) delay üçün işləyin.

**2. Job cancel mexanizmi olmadan schedule etmək**
İstifadəçi abunəliyi ləğv edəndə artıq queue-ya yazılmış email-lər yenə göndərilir. "Trial ending" reminder-ı cancel olunan user-ə çatır — UX problemi, dəstək yükü. Həll: Hər scheduled job-u DB-də qeyd edin; job başlanğıcda `cancelled` statusunu yoxlasın.

**3. Timezone-u ignore etmək**
`now()->setTime(10, 0)` server UTC-sini götürür — Bakıda yaşayan istifadəçi notification-ı saat 10:00 yox, 14:00-da alır. DST keçidlərində isə vaxt daha da sürüşür. Həll: Hər istifadəçi üçün `scheduled_for`-u UTC-yə convert edərək saxlayın; ya da hourly tick + per-user timezone yoxlaması istifadə edin.

**4. Hər scheduled job üçün ayrı worker prosesi ayırmaq**
"Trial email" üçün bir worker, "renewal" üçün başqa bir worker, "reminder" üçün üçüncü worker. Resurs israfı, idarəetmə çətinliyi. Həll: Vahid `scheduled-execution` queue ilə tək worker pool istifadə edin; prioritetləşdirmə lazımdırsa ayrı queue-lar, amma eyni worker pool.

**5. Retry siyasəti olmadan uzun-müddətli job dispatch etmək**
7 gün sonra işləyəcək job fail olur — heç bir retry yoxdur, istifadəçi heç vaxt email almır, log-da da görünmür. Həll: `$tries`, `$backoff`, `failed()` metodunu tətbiq edin; DB-based scheduler isə `attempts` sayacını tutsun.

**6. Job-u schedule edib DB-yə yazmamaq**
Redis-ə yazılmış job-ların heç bir audit trail-i yoxdur. "Bu user niyə email almadı?" sualının cavabı yoxdur. Həll: Hər scheduled job-u `scheduled_jobs` cədvəlinə yazın — status, payload, scheduled_for, attempts, last_error ilə. Monitoring dashboard qurula bilsin.

**7. `withoutOverlapping()` olmadan scheduler-i çalışdırmaq**
`everyMinute()` ilə qeydiyyatdan keçirilmiş poller komandası 60 saniyədən uzun çəkərsə növbəti cron tiki onu üst-üstə işlədər. Eyni job-lar ikiqat dispatch oluna bilər. Həll: `Schedule::command('scheduled-jobs:poll')->everyMinute()->withoutOverlapping()` istifadə edin; uzun poller üçün timeout da qeyd edin: `->withoutOverlapping(5)` (5 dəqiqə).

---

## Interview Sualları və Cavablar

**S: Laravel `->delay()` vs DB-based scheduler fərqi nədir, hansını seçərdiniz?**

`->delay()` Redis ZSET-ə job payload-unu yazır; sürətli və sadədir, amma cancellation yoxdur, milyonlarla job-da memory problemi yaranır, izlənilmə çətindir. DB-based scheduler isə hər job-u cədvəldə saxlayır — cancel edə bilərsiniz, status görə bilərsiniz, audit trail var, sonsuz müddət schedule edə bilərsiniz. Seçim kriteriyi: 1 günə qədər, cancellation lazım deyil, kiçik həcm → `->delay()`. Uzunmüddətli, cancellation tələb olunan, high-volume → DB-based. Real məhsullarda adətən ikisini birlikdə işlədirəm: qısa delay üçün `->delay()`, uzun deadline üçün DB.

**S: User timezone-una görə notification schedule etməyi necə implement edərdiniz?**

İki yanaşma var. Birinci: schedule anında istifadəçinin timezone-unu UTC-yə çevirirəm — `$userTz = new DateTimeZone($user->timezone); $targetTime = new DateTime('today 10:00', $userTz); $utc = $targetTime->setTimezone(new DateTimeZone('UTC'));` — bu UTC vaxtını DB-ə yazıram, poller UTC-də müqayisə edir. İkinci yanaşma: hourly tick — hər saat işləyən bir command bütün istifadəçiləri gəzir, yerli saati 10:00 olanları tap, dispatch et. Birinci daha səmərəlidir; ikincisi DST edge case-lərinə qarşı daha davamlıdır.

**S: Job cancel edə bilmirsiniz — niyə?**

`->delay()` ilə dispatch edilmiş job Redis ZSET-dədir. Redis ZSET yalnız sorted-set semantics dəstəkləyir — elementi silmək üçün `ZREM`-i çağırmalısınız, amma bunun üçün elementin tam dəyərini bilməlisiniz (serialized payload). Job ID ilə birbaşa lookup mümkün deyil. Bundan əlavə, Laravel queue worker job-u ZSET-dən real queue-ya keçirəndən sonra artıq ZSET-də yoxdur — queue-dakı job-u isə ləğv etməyin rəsmi API-si yoxdur. Həll: cancellation lazımdırsa, DB-based scheduler — job başlanğıcda `cancelled` statusunu özü yoxlayır.

**S: 50 milyon pending delayed job-u idarə etmək üçün arxitektura necə qurardınız?**

Birinci addım: Redis-dən tamamilə uzaqlaşmaq — 50M payload Redis RAM-ı öldürər. DB-based scheduler-ə keçmək: `scheduled_jobs` cədvəli, `(status, scheduled_for)` compound index, `SKIP LOCKED` ilə poller. İkinci addım: sharding — `id % N = shard` ilə N paralel poller prosesi, hər biri öz shard-ını işlər. Üçüncü addım: cədvəli partitionlamaq — `scheduled_for` sütununa görə range partition (PostgreSQL `PARTITION BY RANGE`), köhnə partitionları archive etmək. Dördüncü addım: monitoring — "oldest pending job age" metrikası, əgər bu dəyər 5 dəqiqəni keçirsə alert. 50M job üçün AWS EventBridge Scheduler da alternativdir: hər rule bir job, AWS öz infrastrukturunu idarə edir.

**S: Cart abandonment email-ini necə implement edərdiniz?**

İstifadəçi checkout-u tərk edəndə bir event fire edirik. Event listener cart-ı DB-ə yazır, `scheduled_jobs`-a `SendCartAbandonmentEmail` job-unu 24 saat sonraya schedule edir. Əgər istifadəçi bu 24 saat ərzində alış-verişi tamamlayarsa, `OrderCompleted` event-i listener-i tetikləyir — həmin listener `user_id`-ə görə pending `SendCartAbandonmentEmail` job-larını `cancelled` edir. Job işə düşəndə əvvəlcə `cancelled` yoxlar, sonra user-in son 24 saatda order verib-vermədiyini yoxlar (double guard), yalnız sonra email göndərir. Bu şəkildə yanlış email göndərmə ehtimalını sıfıra endiririk.
