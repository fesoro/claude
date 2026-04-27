# Long-Running PHP Prosesləri (Workers, Daemon-lar) (Middle)

## Mündəricat
1. [Problem: PHP-nin Short-Lived Modeli](#problem-phpnin-short-lived-modeli)
2. [Worker Loop Əsasları](#worker-loop-əsasları)
3. [PCNTL Signal Handling](#pcntl-signal-handling)
4. [Memory Leak Qarşısının Alınması](#memory-leak-qarşısının-alınması)
5. [Supervisor Konfiqurasiyası](#supervisor-konfigurasiyası)
6. [Graceful Shutdown Pattern](#graceful-shutdown-pattern)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: PHP-nin Short-Lived Modeli

```
FPM modeli:                Long-running:
┌───────────────────┐      ┌─────────────────────────────────┐
│ Request → Script  │      │ Script başladı                  │
│ → Response        │      │   └─ Job 1 işlə                 │
│ → Process dies    │      │   └─ Job 2 işlə                 │
│ (< 1 saniyə)      │      │   └─ Job 3 işlə                 │
└───────────────────┘      │   └─ ... saatlarla davam edir   │
                           └─────────────────────────────────┘

Queue worker, cron daemon, scheduler — uzun müddət işləyir.

Əlavə problemlər:
  - Memory leak zamanla yığılır
  - DB connection kəsilə bilər (connection timeout)
  - Deploy zamanı graceful shutdown lazımdır
  - Crash olduqda avtomatik restart lazımdır
  - Signal-ları (SIGTERM, SIGINT) handle etmək lazımdır
```

---

## Worker Loop Əsasları

```
Tipik worker loop:

┌──────────────────────────────────────────────────┐
│                  Worker Loop                     │
│                                                  │
│  while ($this->running) {                        │
│      │                                           │
│      ▼                                           │
│  Job var?  ── No ──► sleep() ──► dövrəyə qayıt  │
│      │ Yes                                       │
│      ▼                                           │
│  Job götür                                       │
│      │                                           │
│      ▼                                           │
│  Job işlə                                        │
│      │                                           │
│      ▼                                           │
│  ACK / NACK                                      │
│      │                                           │
│      ▼                                           │
│  Memory/connection sağlamlığını yoxla            │
│      │                                           │
│      └──────────────────────────────────────────┘
│                                                  │
│  $this->running = false olduqda → clean exit     │
└──────────────────────────────────────────────────┘
```

---

## PCNTL Signal Handling

```
POSIX siqnalları:

SIGTERM (15) → "Lütfən dayansın"  — graceful shutdown
SIGINT  (2)  → Ctrl+C             — interaktiv dayandırma
SIGHUP  (1)  → Terminal bağlandı  — konfig reload
SIGKILL (9)  → "Dərhal öldür"    — handle edilə BİLMƏZ

Düzgün signal handling olmadan:
  kill <pid> → proses anında dayanır
  → işlənən job yarımçıq qalır
  → DB transaction açıq qala bilər
  → mesaj itə bilər

Signal handler:
  kill <pid> → SIGTERM → handler çağırılır
  → $running = false
  → cari job tamamlanır
  → təmiz çıxış
```

---

## Memory Leak Qarşısının Alınması

```
Memory leak mənbələri:

1. Unset edilməyən böyük obyektlər
   $data = loadMegabytes(); // unset etmirsən → leak

2. Circular references (gc tuta bilmir)
   $a->b = $b;
   $b->a = $a;
   unset($a, $b); // destructor çağırılmır!
   gc_collect_cycles(); // manual GC lazımdır

3. Static caching
   SomeClass::$cache[] = $data; // statik array böyüyür
   
4. Logger/event handler yığılması
   $dispatcher->addListener(...); // hər job üçün əlavə olunur

Müdafiə strategiyaları:
  - Hər job-dan sonra unset()
  - Dövri gc_collect_cycles()
  - Memory threshold-u yoxla → restart
  - pm.max_requests (FPM worker üçün)
```

---

## Supervisor Konfiqurasiyası

```ini
; /etc/supervisor/conf.d/queue-worker.conf

[program:queue-worker]
command=php /var/www/artisan queue:work --sleep=3 --tries=3
directory=/var/www
user=www-data
autostart=true
autorestart=true           ; crash olduqda restart
startsecs=1                ; 1 saniyədən çox işlərsə "started" sayılır
startretries=3             ; max 3 cəhd
stopwaitsecs=60            ; SIGTERM-dən sonra 60s gözlə, sonra SIGKILL
numprocs=4                 ; 4 paralel worker
process_name=%(program_name)s_%(process_num)02d

; Log
stdout_logfile=/var/log/supervisor/worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stderr_logfile=/var/log/supervisor/worker-error.log

; Environment
environment=APP_ENV="production",LOG_CHANNEL="stderr"
```

```bash
# Supervisor əmrləri
supervisorctl status
supervisorctl start queue-worker:*
supervisorctl stop queue-worker:*
supervisorctl restart queue-worker:*
supervisorctl reread   # konfiq dəyişdikdə
supervisorctl update   # yeni proqramları aktivləşdir
```

---

## Graceful Shutdown Pattern

```
Deploy zamanı graceful shutdown:

1. Deploy başlar
2. Supervisor-a SIGTERM göndərilir
3. Worker cari job-u tamamlayır
4. Yeni job götürmür
5. Təmiz çıxır
6. Supervisor yeni versiya ilə restart edir

stopwaitsecs = 60 → 60 saniyə gözlə
Əgər 60s-dən uzun işləyən job varsa SIGKILL göndərilir!
```

---

## PHP İmplementasiyası

```php
<?php
// Tam worker prosesi — signal handling, memory check, graceful shutdown

declare(ticks=1); // pcntl_signal üçün lazımdır

class QueueWorker
{
    private bool $running = true;
    private bool $processing = false;
    private int $processedJobs = 0;
    private float $startMemory;

    // Restart threshold (MB)
    private const MEMORY_LIMIT_MB = 128;
    // Bu qədər job-dan sonra restart (memory leak qarşısı)
    private const MAX_JOBS = 1000;

    public function __construct(
        private QueueInterface $queue,
        private LoggerInterface $logger,
    ) {
        $this->startMemory = memory_get_usage(true);
        $this->registerSignalHandlers();
    }

    private function registerSignalHandlers(): void
    {
        // Graceful shutdown
        pcntl_signal(SIGTERM, function(): void {
            $this->logger->info('SIGTERM alındı, cari job-dan sonra dayanacaq');
            $this->running = false;
        });

        pcntl_signal(SIGINT, function(): void {
            $this->logger->info('SIGINT alındı (Ctrl+C)');
            $this->running = false;
        });

        // Config reload
        pcntl_signal(SIGHUP, function(): void {
            $this->logger->info('SIGHUP alındı, konfiqurasiya yenilənir');
            $this->reloadConfig();
        });
    }

    public function run(): void
    {
        $this->logger->info('Worker başladı', ['pid' => getmypid()]);

        while ($this->running) {
            pcntl_signal_dispatch(); // siqnalları yoxla

            $job = $this->queue->pop();

            if ($job === null) {
                sleep(1); // job yoxdur, gözlə
                continue;
            }

            $this->processing = true;
            $this->processJob($job);
            $this->processing = false;
            $this->processedJobs++;

            // Memory/job limit yoxlaması
            if ($this->shouldRestart()) {
                $this->logger->info('Restart threshold-a çatıldı', [
                    'jobs' => $this->processedJobs,
                    'memory_mb' => $this->getCurrentMemoryMB(),
                ]);
                break; // loop-dan çıx → Supervisor restart edəcək
            }
        }

        $this->logger->info('Worker dayandı', [
            'processed_jobs' => $this->processedJobs,
        ]);
    }

    private function processJob(Job $job): void
    {
        try {
            $job->handle();
            $this->queue->ack($job);
        } catch (\Throwable $e) {
            $this->logger->error('Job uğursuz', [
                'job' => $job->getId(),
                'error' => $e->getMessage(),
            ]);
            $this->queue->nack($job);
        } finally {
            // Memory leak-in qarşısını al
            unset($job);
            gc_collect_cycles();
        }
    }

    private function shouldRestart(): bool
    {
        if ($this->processedJobs >= self::MAX_JOBS) {
            return true;
        }

        if ($this->getCurrentMemoryMB() > self::MEMORY_LIMIT_MB) {
            return true;
        }

        return false;
    }

    private function getCurrentMemoryMB(): float
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2);
    }

    private function reloadConfig(): void
    {
        // Konfiqurasiyanı yenidən yüklə
    }
}

// Başlatma skripti
$worker = new QueueWorker(
    new RedisQueue(),
    new Logger(),
);
$worker->run();
```

---

## İntervyu Sualları

- `pcntl_signal` ilə `pcntl_async_signals` fərqi nədir? `declare(ticks=1)` nə üçün lazımdır?
- Worker SIGTERM aldıqda cari job-u tamamlamalıdırmı? Niyə?
- Supervisor `stopwaitsecs`-i artırmaq nə zaman lazımdır?
- Worker-da memory leak aşkarladınız — necə debug edərdiniz?
- `gc_collect_cycles()` hər job-dan sonra çağırmaq performansa necə təsir edir?
- Deploy zamanı "0 downtime" üçün worker-ları necə yenidən başladarsınız?
- `numprocs=4` ilə 4 worker işlədirsiniz — DB connection pool neçə olmalıdır?
