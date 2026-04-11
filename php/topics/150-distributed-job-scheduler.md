# System Design: Distributed Job Scheduler

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Job schedule etmək: dəqiq vaxt, CRON, delay
  Job icra etmək: bir və ya daha çox worker
  Retry: uğursuz job-ları yenidən cəhd etmək
  Priority: yüksək priority job-lar önce işlənir
  Monitoring: job statusları, history

Qeyri-funksional:
  Exactly-once semantics (job iki dəfə icra edilməsin)
  Horizontal scaling (worker-lar əlavə et)
  Fault tolerance (scheduler fail olsa job-lar itirilməsin)
  Yüksək mövcudluq: 99.9%

Nümunə:
  Hər gecə saat 02:00 backup al
  Ödənişi 15 dəqiqə sonra emal et
  Hesabat hər bazar ertəsi göndər
  10 saniyədən uzun cəhddən sonra timeout et
```

---

## Yüksək Səviyyəli Dizayn

```
┌────────────────┐    ┌──────────────────────┐
│  Job Producer  │───►│   Scheduler Service   │
│ (API/cron)     │    │  (Leader + Standby)   │
└────────────────┘    └──────────┬────────────┘
                                 │ enqueue
                      ┌──────────▼────────────┐
                      │    Job Queue (Redis    │
                      │    / DB-based)         │
                      └──────────┬────────────┘
                                 │ poll
                  ┌──────────────┼──────────────┐
                  │              │              │
           ┌──────▼──┐    ┌──────▼──┐    ┌──────▼──┐
           │Worker 1 │    │Worker 2 │    │Worker 3 │
           └──────┬──┘    └──────┬──┘    └──────┬──┘
                  │              │              │
                  └──────────────┴──────────────┘
                                 │
                      ┌──────────▼────────────┐
                      │   Job History DB       │
                      │   Monitoring           │
                      └────────────────────────┘

Scheduler HA:
  Leader election (Redis SETNX / ZooKeeper / Raft)
  Leader → cron job-ları zamanında enqueue edir
  Standby → leader fail olsa devralır
```

---

## Komponent Dizaynı

```
Job Queue seçimi:

Redis (Delayed Queue):
  ZADD jobs:delayed {timestamp} {jobId}
  Worker: ZRANGEBYSCORE (keçmiş vaxtlı job-ları al)
  Sürətli, amma durability az (AOF/RDB ilə artırılır)

Database-based Queue:
  PostgreSQL/MySQL: jobs cədvəli
  SELECT ... FOR UPDATE SKIP LOCKED (row lock, başqası görmür)
  Durability yaxşı, amma scale limiti var

Dedicated Queue (Celery, Sidekiq):
  Kafka, RabbitMQ, SQS
  High throughput, partitioning

Exactly-once icra:
  Worker → job götür (status: running, locked_until: now+timeout)
  Job tamamlananda → status: completed
  locked_until keçibsə başqa worker götürə bilər (heartbeat lazım)

Heartbeat:
  Worker hər 30 saniyə locked_until yeniləyir
  Worker crash olsa → timeout keçir → başqa worker götürür
  
Idempotent jobs:
  Job 2 dəfə çalışarsa nəticə eyni olmalıdır
  External API call-lar üçün idempotency key istifadə et
```

---

## PHP İmplementasiyası

```php
<?php
// Job Definition
namespace App\Scheduler\Domain;

class Job
{
    private JobId     $id;
    private string    $type;       // 'backup', 'report', 'email'
    private array     $payload;
    private JobStatus $status;
    private int       $priority;   // 1-10 (yüksək = əvvəl)
    private int       $maxRetries;
    private int       $attempt;
    private ?\DateTimeImmutable $scheduledAt;
    private ?\DateTimeImmutable $lockedUntil;
    private ?string   $lockedBy;   // Worker ID

    public static function createDelayed(
        string $type,
        array  $payload,
        \DateTimeImmutable $scheduledAt,
        int    $priority   = 5,
        int    $maxRetries = 3,
    ): self {
        $job = new self();
        $job->id          = JobId::generate();
        $job->type        = $type;
        $job->payload     = $payload;
        $job->status      = JobStatus::PENDING;
        $job->priority    = $priority;
        $job->maxRetries  = $maxRetries;
        $job->attempt     = 0;
        $job->scheduledAt = $scheduledAt;
        return $job;
    }
}
```

```php
<?php
// Database-based queue worker (PostgreSQL SKIP LOCKED)
class JobWorker
{
    private string $workerId;
    private const LOCK_TIMEOUT_SECONDS = 300; // 5 dəqiqə
    private const HEARTBEAT_INTERVAL   = 30;  // 30 saniyə
    private const POLL_INTERVAL        = 1;   // 1 saniyə

    public function __construct(
        private \PDO            $db,
        private JobHandlerRegistry $handlers,
        private \Psr\Log\LoggerInterface $logger,
    ) {
        $this->workerId = gethostname() . ':' . getmypid();
    }

    public function run(): void
    {
        $this->logger->info("Worker başladı: {$this->workerId}");

        while (true) {
            $job = $this->acquireJob();

            if ($job === null) {
                sleep(self::POLL_INTERVAL);
                continue;
            }

            // Heartbeat thread (ayrı goroutine Swoole ilə, ya Fiber)
            $this->processJob($job);
        }
    }

    private function acquireJob(): ?array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM jobs
                 WHERE status = 'pending'
                   AND scheduled_at <= NOW()
                   AND (locked_until IS NULL OR locked_until < NOW())
                 ORDER BY priority DESC, scheduled_at ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED"
            );
            $stmt->execute();
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                $this->db->rollBack();
                return null;
            }

            // Job-u lock et
            $lockedUntil = (new \DateTimeImmutable())
                ->modify('+' . self::LOCK_TIMEOUT_SECONDS . ' seconds')
                ->format('Y-m-d H:i:s');

            $update = $this->db->prepare(
                "UPDATE jobs SET status = 'running',
                                  locked_until = ?,
                                  locked_by = ?,
                                  attempt = attempt + 1
                 WHERE id = ?"
            );
            $update->execute([$lockedUntil, $this->workerId, $job['id']]);

            $this->db->commit();
            return $job;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function processJob(array $job): void
    {
        $handler = $this->handlers->get($job['type'])
            ?? throw new UnknownJobTypeException($job['type']);

        try {
            $handler->handle(json_decode($job['payload'], true));

            $this->markCompleted($job['id']);
            $this->logger->info("Job tamamlandı: {$job['id']}");

        } catch (\Throwable $e) {
            $this->logger->error("Job uğursuz: {$job['id']}", ['error' => $e->getMessage()]);
            $this->handleFailure($job, $e);
        }
    }

    private function handleFailure(array $job, \Throwable $e): void
    {
        $attempt    = $job['attempt'];
        $maxRetries = $job['max_retries'];

        if ($attempt < $maxRetries) {
            // Exponential backoff
            $delay = min(3600, 2 ** $attempt * 60); // max 1 saat
            $retryAt = (new \DateTimeImmutable())->modify("+{$delay} seconds");

            $stmt = $this->db->prepare(
                "UPDATE jobs SET status = 'pending',
                                  locked_until = NULL,
                                  locked_by = NULL,
                                  scheduled_at = ?,
                                  last_error = ?
                 WHERE id = ?"
            );
            $stmt->execute([$retryAt->format('Y-m-d H:i:s'), $e->getMessage(), $job['id']]);
        } else {
            // Max retry keçdi → failed
            $stmt = $this->db->prepare(
                "UPDATE jobs SET status = 'failed', last_error = ? WHERE id = ?"
            );
            $stmt->execute([$e->getMessage(), $job['id']]);
        }
    }

    private function markCompleted(string $jobId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE jobs SET status = 'completed', completed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$jobId]);
    }
}
```

```php
<?php
// CRON-based Job Scheduler (Leader-based)
class CronScheduler
{
    public function __construct(
        private \Redis $redis,
        private JobRepository $jobs,
        private array $cronJobs, // ['backup' => '0 2 * * *', ...]
    ) {}

    public function tick(): void
    {
        // Leader election — yalnız bir instance çalışır
        $acquired = $this->redis->set('scheduler:leader', gethostname(),
            ['NX', 'EX' => 30]); // 30 saniyə TTL

        if (!$acquired) {
            return; // Başqası leader-dir
        }

        foreach ($this->cronJobs as $type => $expression) {
            if ($this->isDue($expression)) {
                $this->jobs->enqueue(Job::createDelayed(
                    type:        $type,
                    payload:     [],
                    scheduledAt: new \DateTimeImmutable(),
                ));
            }
        }

        // Heartbeat — leadership yenilə
        $this->redis->expire('scheduler:leader', 30);
    }

    private function isDue(string $cronExpression): bool
    {
        // Cron expression parse et
        // composer require dragonmantank/cron-expression
        $cron = new \Cron\CronExpression($cronExpression);
        return $cron->isDue();
    }
}
```

---

## İntervyu Sualları

- Exactly-once job execution necə təmin edilir?
- `SELECT FOR UPDATE SKIP LOCKED` nəyə lazımdır?
- Worker crash olduqda işlənən job necə bərpa edilir?
- Distributed scheduler-da leader election niyə lazımdır?
- Exponential backoff retry strategiyasını izah edin.
- Job priority queueing necə tətbiq edilir?
