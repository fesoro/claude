# Job / Task Scheduler — DB Design (Middle ⭐⭐)

## İcmal

Background job sistemi hər ciddi backend-in ayrılmaz hissəsidir: email göndərmək, hesabat yaratmaq, API sync etmək, media process etmək. Əsas problemlər: exactly-once execution, retry logic, distributed lock, priority queue, scheduled (cron-style) jobs.

---

## Tövsiyə olunan DB Stack

```
Job queue:    PostgreSQL    (jobs, schedules — source of truth)
Locking:      Redis         (distributed lock, worker heartbeat)
Dead letter:  PostgreSQL    (failed jobs audit)
```

---

## Niyə PostgreSQL (yox Redis)?

```
Redis queue (LPUSH/BRPOP):
  ✓ Çox sürətli
  ✗ Persistence zəif (AOF lag)
  ✗ Query yoxdur: "bütün failed jobs" tapmaq çətin
  ✗ Scheduled jobs üçün uyğun deyil

PostgreSQL queue:
  ✓ ACID: job itirilmir
  ✓ Query: status, type, priority üzrə filter
  ✓ FOR UPDATE SKIP LOCKED: atomic job claim
  ✓ Scheduled jobs: next_run_at column kifayət edir
  ✓ Dead letter: failed jobs table-da qalır

Praktikada hibrid:
  PostgreSQL: job storage + scheduling
  Redis: distributed lock (worker uniqueness)
  
  Laravel Horizon: Redis queues + PostgreSQL metrics
  Sidekiq: Redis (application), PostgreSQL (optional)
  Faktiki seçim: workload-a baxır
```

---

## Core Schema

```sql
-- ==================== JOBS ====================
CREATE TABLE jobs (
    id              BIGSERIAL PRIMARY KEY,

    -- Job identity
    queue           VARCHAR(100) NOT NULL DEFAULT 'default',
    -- 'default', 'emails', 'reports', 'imports', 'critical'

    type            VARCHAR(255) NOT NULL,
    -- 'App\Jobs\SendEmailJob', 'generate_invoice', 'sync_products'

    -- Payload
    payload         JSONB NOT NULL DEFAULT '{}',
    -- {user_id: 123, template: "welcome", order_id: 456}

    -- Status machine
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    -- pending → reserved → running → completed / failed / cancelled

    -- Priority (daha aşağı = daha yüksək prioritet)
    priority        SMALLINT NOT NULL DEFAULT 100,
    -- 1 = critical, 50 = high, 100 = normal, 200 = low

    -- Scheduling
    scheduled_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),  -- nə vaxt işlənsin
    available_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),  -- retry delay sonrası

    -- Attempt tracking
    attempts        SMALLINT NOT NULL DEFAULT 0,
    max_attempts    SMALLINT NOT NULL DEFAULT 3,
    timeout_s       INTEGER NOT NULL DEFAULT 60,        -- max execution time

    -- Retry
    retry_after_s   INTEGER DEFAULT 60,                 -- next retry delay

    -- Worker info
    worker_id       VARCHAR(255),                       -- lock-ı tutan worker
    reserved_at     TIMESTAMPTZ,                        -- claim edildi

    -- Result
    result          JSONB,                              -- job output (optional)
    error_message   TEXT,
    error_trace     TEXT,

    -- Deduplication (eyni job 2 dəfə queue-ya əlavə olmasın)
    unique_key      VARCHAR(255) UNIQUE,
    -- 'send_welcome_email:user:123', NULL = no dedup

    created_at      TIMESTAMPTZ DEFAULT NOW(),
    started_at      TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    failed_at       TIMESTAMPTZ
);

-- Worker-in növbəti job-u claim etdiyi query:
-- SELECT * FROM jobs
-- WHERE status = 'pending'
--   AND available_at <= NOW()
--   AND queue = 'default'
-- ORDER BY priority ASC, scheduled_at ASC
-- LIMIT 1
-- FOR UPDATE SKIP LOCKED;  ← critical: digər worker eyni job-u götürməsin

CREATE INDEX idx_jobs_claim ON jobs(status, queue, available_at, priority)
    WHERE status = 'pending';
CREATE INDEX idx_jobs_worker ON jobs(worker_id) WHERE status = 'running';
CREATE INDEX idx_jobs_unique ON jobs(unique_key) WHERE unique_key IS NOT NULL;

-- ==================== RECURRING SCHEDULES (Cron) ====================
CREATE TABLE job_schedules (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,  -- 'daily_report', 'weekly_cleanup'

    job_type    VARCHAR(255) NOT NULL,         -- Job class / handler
    payload     JSONB NOT NULL DEFAULT '{}',

    queue       VARCHAR(100) DEFAULT 'default',
    priority    SMALLINT DEFAULT 100,

    -- Cron expression
    cron_expr   VARCHAR(100) NOT NULL,         -- '0 9 * * 1-5' (weekdays 9am)
    timezone    VARCHAR(50) DEFAULT 'UTC',     -- 'Asia/Baku'

    -- Next run
    next_run_at TIMESTAMPTZ NOT NULL,

    -- Overlap protection
    allow_overlap BOOLEAN DEFAULT FALSE,       -- eyni anda 2 instance çalışsın?

    -- Status
    is_active   BOOLEAN DEFAULT TRUE,

    -- Stats
    last_run_at TIMESTAMPTZ,
    last_job_id BIGINT REFERENCES jobs(id),
    run_count   BIGINT DEFAULT 0,

    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== FAILED JOBS (Dead Letter Queue) ====================
CREATE TABLE failed_jobs (
    id              BIGSERIAL PRIMARY KEY,
    job_id          BIGINT NOT NULL,           -- original job ID

    queue           VARCHAR(100) NOT NULL,
    type            VARCHAR(255) NOT NULL,
    payload         JSONB NOT NULL,

    -- Failure details
    exception_class VARCHAR(255),
    error_message   TEXT NOT NULL,
    error_trace     TEXT,

    -- Attempt history
    attempts        SMALLINT NOT NULL,

    failed_at       TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== JOB BATCHES ====================
-- Çox job-un tamamlanmasını track etmək (Fan-out then fan-in)
CREATE TABLE job_batches (
    id              VARCHAR(100) PRIMARY KEY,   -- UUID
    name            VARCHAR(255),

    total_jobs      INTEGER NOT NULL DEFAULT 0,
    pending_jobs    INTEGER NOT NULL DEFAULT 0,
    failed_jobs_count INTEGER NOT NULL DEFAULT 0,

    -- Callbacks (sonunda nə edilsin)
    then_callback   TEXT,                       -- serialized callback
    catch_callback  TEXT,
    finally_callback TEXT,

    -- Status
    cancelled_at    TIMESTAMPTZ,
    finished_at     TIMESTAMPTZ,

    created_at      TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Job State Machine

```
pending
  │
  ▼ (worker claims: FOR UPDATE SKIP LOCKED)
reserved
  │
  ▼ (worker starts execution)
running
  │
  ├──── success ───► completed
  │
  ├──── timeout ───► pending (attempts < max_attempts)
  │                  → available_at = NOW() + retry_backoff
  │
  ├──── exception ──► pending (attempts < max_attempts)
  │                  → exponential backoff
  │
  └──── max attempts reached ──► failed
                                 → failed_jobs table-a kopyala
                                 → alert göndər
```

---

## Worker: Atomic Job Claim

```sql
-- Worker job götürür (atomic, race condition yoxdur)
WITH claimed AS (
    SELECT id FROM jobs
    WHERE status = 'pending'
      AND queue = 'emails'
      AND available_at <= NOW()
    ORDER BY priority ASC, available_at ASC
    LIMIT 1
    FOR UPDATE SKIP LOCKED
)
UPDATE jobs
SET status      = 'running',
    reserved_at = NOW(),
    started_at  = NOW(),
    worker_id   = :worker_uuid,
    attempts    = attempts + 1
WHERE id = (SELECT id FROM claimed)
RETURNING *;

-- FOR UPDATE SKIP LOCKED:
-- Digər worker eyni row-a çatanda SKIP edir (lock gözləmir)
-- Bu pattern PostgreSQL 9.5+ — distributed queue üçün ideal
```

---

## Retry: Exponential Backoff

```
Cəhd 1:  fail → 1 dəqiqə sonra retry
Cəhd 2:  fail → 5 dəqiqə
Cəhd 3:  fail → 30 dəqiqə
Cəhd 4:  fail → 2 saat
Cəhd 5:  fail → 12 saat
max_attempts sonra → DLQ (failed_jobs)

Formula: delay = base × 2^(attempt - 1) + random_jitter

PHP:
  $delay = 60 * (2 ** ($attempt - 1));       // 60, 120, 240, 480...
  $jitter = random_int(0, 30);                // thundering herd önlə
  $available_at = now()->addSeconds($delay + $jitter);
  
DB update on failure:
  UPDATE jobs SET
    status = CASE
      WHEN attempts >= max_attempts THEN 'failed'
      ELSE 'pending'
    END,
    available_at = NOW() + INTERVAL ':delay seconds',
    error_message = :error,
    failed_at = CASE WHEN attempts >= max_attempts THEN NOW() END
  WHERE id = :job_id;
```

---

## Recurring Jobs (Cron)

```
Scheduler process (hər dəqiqə bir dəfə çalışır):

1. Due schedule-ları tap:
   SELECT * FROM job_schedules
   WHERE is_active = TRUE
     AND next_run_at <= NOW();

2. Overlap check (allow_overlap = FALSE):
   Son job hələ running-dir? → skip

3. Job yarat:
   INSERT INTO jobs (type, payload, queue, priority)
   SELECT job_type, payload, queue, priority
   FROM job_schedules WHERE id = :schedule_id;

4. next_run_at hesabla (cron expression parse et):
   UPDATE job_schedules SET
     next_run_at  = :next_occurrence,
     last_run_at  = NOW(),
     last_job_id  = :new_job_id,
     run_count    = run_count + 1
   WHERE id = :schedule_id;

Distributed scheduler lock (Redis):
  SET scheduler:lock :server_id NX EX 60
  → Yalnız 1 server lock alır → o çalışır
  → 60 saniyə sonra lock expires → başqası ala bilər
```

---

## Worker Heartbeat (Stuck Job Detection)

```
Problem: worker öldü, job "running" qalır (stuck)

Həll: heartbeat + cleanup

Worker: hər 15 saniyə bir:
  UPDATE jobs SET reserved_at = NOW()
  WHERE id = :job_id AND worker_id = :worker_id;

Monitor process: hər dəqiqə:
  -- Timeout-a düşmüş job-ları tap
  UPDATE jobs SET
    status = 'pending',
    worker_id = NULL,
    available_at = NOW()
  WHERE status = 'running'
    AND reserved_at < NOW() - INTERVAL ':timeout seconds';

  -- Artıq işləmir, amma timeout keçməyib:
  -- Bu worker ölüb → Redis-də worker key yoxdur → cleanup

Redis heartbeat:
  SET worker:{worker_id}:heartbeat 1 EX 30
  Worker öldü → key expires → monitor "worker missing" detect edir
```

---

## Job Deduplication

```
Problem: eyni email-i 2 dəfə göndərmə

Həll: unique_key column

Job queue-ya əlavə etməzdən əvvəl:
  unique_key = "send_invoice_email:invoice:456"
  
  INSERT INTO jobs (type, payload, unique_key, ...)
  VALUES ('SendInvoiceEmail', {...}, 'send_invoice_email:invoice:456', ...)
  ON CONFLICT (unique_key) DO NOTHING;
  → Artıq queue-da varsa, silent ignore

unique_key TTL:
  Job completed → unique_key NULL-a sıfırla (yenidən göndərməyə icazə ver)
  UPDATE jobs SET unique_key = NULL WHERE id = :id AND status = 'completed';
  
  Alternativ: completed job-u saxla → unique_key qalsın (audit trail)
```

---

## Laravel-də Praktik Nümunə

```php
// Job class
class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 60;   // ilk retry delay

    public function __construct(private int $invoiceId) {}

    public function handle(): void
    {
        $invoice = Invoice::findOrFail($this->invoiceId);
        Mail::to($invoice->customer->email)
            ->send(new InvoiceMail($invoice));
    }

    public function failed(Throwable $e): void
    {
        // Max attempts sonra çağrılır
        Log::error("Invoice email failed", [
            'invoice_id' => $this->invoiceId,
            'error'      => $e->getMessage(),
        ]);
        // Slack alert, monitoring event...
    }

    // Deduplication key
    public function uniqueId(): string
    {
        return "invoice:{$this->invoiceId}";
    }
}

// Dispatch (scheduling)
SendInvoiceEmail::dispatch($invoice->id)
    ->onQueue('emails')
    ->delay(now()->addMinutes(5));  // 5 dəqiqə sonra göndər

// Recurring schedule (App\Console\Kernel)
$schedule->job(new GenerateDailyReport)->dailyAt('06:00')->timezone('Asia/Baku');
```

---

## Anti-Patterns

```
✗ Job içindən job dispatch etmək (sonsuz loop):
  Job A → dispatch Job A → sonsuz rekursiya
  Depth limit + unique key ilə qoru

✗ Payload-da böyük data saxlamaq:
  Job payload-da 10MB JSON → DB şişir
  Payload-da yalnız ID saxla, job içində DB-dən oxu

✗ FOR UPDATE olmadan job claim:
  SELECT → UPDATE (2 addım) → race condition
  FOR UPDATE SKIP LOCKED həmişə istifadə et

✗ Timeout-suz job:
  Stuck job → worker thread bloklanır
  timeout_s her job-da mütləq set et

✗ DLQ-nu nəzərə almamaq:
  Failed jobs susqunca gedir → production problem bilinmir
  Alert + monitoring mütləq

✗ Hər şeyi eyni queue-da:
  Kritik ödəniş email-i + ağır hesabat → eyni queue
  Hesabat queue-nu doldurur → email gecikir
  Queue separation: 'critical', 'emails', 'reports', 'imports'
```

---

## Tanınmış Həllər

```
Laravel Horizon:
  Redis queues + Supervisor
  Dashboard: throughput, failed jobs, wait times
  Auto-scaling workers per queue

Sidekiq (Ruby):
  Redis-based
  Pro: scheduled jobs, batches, rate limiting
  Enterprise: workflow, super-fetch

Celery (Python):
  Redis or RabbitMQ broker
  PostgreSQL results backend
  Beat: periodic task scheduler

BullMQ (Node.js):
  Redis-based, TypeScript-first
  Flows: parent-child job dependencies

Faktiki böyük sistemlər:
  Shopify: custom Resque (Redis) + PostgreSQL
  GitHub: Resque → Sidekiq
  Airbnb: Apache Airflow (DAG-based workflows)
  Uber: Cadence / Temporal (workflow engine)
```
