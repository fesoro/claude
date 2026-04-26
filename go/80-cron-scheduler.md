# Cron Scheduler (Senior)

## İcmal

Go-da vaxt əsaslı tapşırıqlar üçün `robfig/cron` paketi standartdır. Standard Unix cron ifadələrini dəstəkləyir, goroutine-lərdə işlər icra edir, graceful shutdown-u dəstəkləyir. PHP/Laravel-in `Kernel.php → $schedule->command()` sisteminin Go ekvivalenti.

## Niyə Vacibdir

- Backend sistemlərinin böyük hissəsi scheduled job tələb edir: hesabat, email, təmizlik, sinxronizasiya
- Go-da ayrı worker prosesi lazım deyil — ana tətbiq içindədir
- Goroutine-lərdə işlər paralel çalışır — biri gecikirsə digərləri bloklanmır
- Graceful shutdown: tətbiq dayananda işlər yarımçıq qalmır

## Əsas Anlayışlar

**Cron ifadəsi:**
```
┌──── saniyə (0-59)     ← bəzən yoxdur
│ ┌── dəqiqə (0-59)
│ │ ┌─ saat (0-23)
│ │ │ ┌ ay günü (1-31)
│ │ │ │ ┌ ay (1-12)
│ │ │ │ │ ┌ həftə günü (0-6, 0=bazar)
│ │ │ │ │ │
* * * * * *
```

```
@every 30s   — hər 30 saniyə
@every 5m    — hər 5 dəqiqə
@hourly      — hər saat (0 * * * *)
@daily       — hər gün gecəyarı
@weekly      — həftəlik
@monthly     — aylıq
```

**`robfig/cron` v3 xüsusiyyətlər:**
- Thread-safe (goroutine-safe)
- Dinamik job əlavə etmək/silmək
- Custom logger dəstəyi
- Timezone dəstəyi

## Praktik Baxış

**Nə vaxt istifadə et:**
- Hesabat generasiyası (gecəyarı, həftəlik)
- Email/bildiriş göndərmə (vaxtında)
- Cache invalidation
- Database təmizliyi (köhnə qeydlər)
- Third-party sinxronizasiya (API pull)
- Monitoring health checks

**Production qaydaları:**
- Bir işi eyni anda iki dəfə çalışmasının qarşısını al (distributed lock + singleflight)
- Job-ların uzun sürdüyünü izlə — metric qeyd et
- Xəta olduqda alert göndər
- Multi-instance deployment: Redis-dən distributed lock istifadə et

**Common mistakes:**
- Job panic-i scheduler-i öldürür — recover() əlavə et
- Overlapping job — əvvəlki hələ davam edir, yenisi başlayır
- Timezone müqayisə xətası — həmişə UTC istifadə et

## Nümunələr

### Nümunə 1: Əsas istifadə

```go
package main

import (
    "context"
    "log/slog"
    "os"
    "os/signal"
    "syscall"

    "github.com/robfig/cron/v3"
)

// go get github.com/robfig/cron/v3

func main() {
    logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

    c := cron.New(
        cron.WithSeconds(),    // saniyə dəqiqliyi
        cron.WithLogger(cron.VerbosePrintfLogger(nil)),
    )

    // Hər 30 saniyə
    c.AddFunc("@every 30s", func() {
        logger.Info("Health check işlədi")
    })

    // Hər gün saat 02:00-da
    c.AddFunc("0 0 2 * * *", func() {
        logger.Info("Gecəlik hesabat başladı")
        if err := generateNightlyReport(context.Background()); err != nil {
            logger.Error("Hesabat xətası", slog.String("error", err.Error()))
        }
    })

    // Həftədə bir — bazar ertəsi 08:00
    c.AddFunc("0 0 8 * * 1", func() {
        logger.Info("Həftəlik email göndərilir")
        sendWeeklyDigest(context.Background())
    })

    // Hər dəqiqə — DB-dən köhnə sessiyaları sil
    c.AddFunc("@every 1m", func() {
        cleanExpiredSessions(context.Background())
    })

    c.Start()
    defer c.Stop() // Graceful: cari işlər tamamlanana qədər gözlə

    // Signal gözlə
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGTERM, syscall.SIGINT)
    <-quit

    logger.Info("Scheduler dayanır, cari işlər tamamlanır...")
    // c.Stop() ctx qaytarır — bloklar
    ctx := c.Stop()
    <-ctx.Done()
    logger.Info("Scheduler dayandı")
}

func generateNightlyReport(ctx context.Context) error { return nil }
func sendWeeklyDigest(ctx context.Context)            {}
func cleanExpiredSessions(ctx context.Context)        {}
```

### Nümunə 2: Panic recovery — bir iş çöksə scheduler davam edir

```go
package main

import (
    "fmt"
    "log/slog"
    "runtime/debug"

    "github.com/robfig/cron/v3"
)

type safeJob struct {
    name    string
    fn      func()
    logger  *slog.Logger
}

func (j *safeJob) Run() {
    defer func() {
        if r := recover(); r != nil {
            j.logger.Error("Job panic",
                slog.String("job", j.name),
                slog.String("panic", fmt.Sprintf("%v", r)),
                slog.String("stack", string(debug.Stack())),
            )
            // Alert göndər (PagerDuty, Slack...)
        }
    }()
    j.fn()
}

func addSafeJob(c *cron.Cron, schedule, name string, fn func(), logger *slog.Logger) (cron.EntryID, error) {
    return c.AddJob(schedule, &safeJob{
        name:   name,
        fn:     fn,
        logger: logger,
    })
}

func main() {
    c := cron.New(cron.WithSeconds())
    logger := slog.Default()

    addSafeJob(c, "@every 1m", "risky-job", func() {
        panic("bu iş çöküb!") // scheduler davam edir
    }, logger)

    addSafeJob(c, "@every 5m", "safe-job", func() {
        slog.Info("Bu iş normal işləyir")
    }, logger)

    c.Start()
}
```

### Nümunə 3: Overlapping qarşısını al — distributed lock

```go
package main

import (
    "context"
    "fmt"
    "log/slog"
    "sync"
    "time"
)

// Sadə in-process lock (production-da Redis lock istifadə et)
type JobLock struct {
    mu      sync.Mutex
    running map[string]bool
}

func NewJobLock() *JobLock {
    return &JobLock{running: make(map[string]bool)}
}

func (l *JobLock) Run(name string, fn func()) {
    l.mu.Lock()
    if l.running[name] {
        l.mu.Unlock()
        slog.Warn("Job artıq işləyir, keçilir", slog.String("job", name))
        return // Overlapping yoxdur
    }
    l.running[name] = true
    l.mu.Unlock()

    defer func() {
        l.mu.Lock()
        delete(l.running, name)
        l.mu.Unlock()
    }()

    fn()
}

// Redis distributed lock ilə (production)
type RedisJobRunner struct {
    redis RedisClient
}

func (r *RedisJobRunner) TryRun(ctx context.Context, jobName string, ttl time.Duration, fn func(ctx context.Context) error) error {
    lockKey := fmt.Sprintf("cron:lock:%s", jobName)

    // SET NX EX — atomik lock
    ok, err := r.redis.SetNX(ctx, lockKey, "1", ttl)
    if err != nil || !ok {
        return fmt.Errorf("lock alınmadı: %s artıq işləyir", jobName)
    }

    defer r.redis.Del(ctx, lockKey)

    return fn(ctx)
}

type RedisClient interface {
    SetNX(ctx context.Context, key, value string, ttl time.Duration) (bool, error)
    Del(ctx context.Context, keys ...string) error
}
```

### Nümunə 4: Dinamik job idarəsi

```go
package main

import (
    "fmt"
    "sync"

    "github.com/robfig/cron/v3"
)

type SchedulerService struct {
    cron    *cron.Cron
    entries map[string]cron.EntryID
    mu      sync.Mutex
}

func NewSchedulerService() *SchedulerService {
    return &SchedulerService{
        cron:    cron.New(cron.WithSeconds()),
        entries: make(map[string]cron.EntryID),
    }
}

func (s *SchedulerService) AddJob(name, schedule string, fn func()) error {
    s.mu.Lock()
    defer s.mu.Unlock()

    // Əvvəlki varsa sil
    if id, ok := s.entries[name]; ok {
        s.cron.Remove(id)
    }

    id, err := s.cron.AddFunc(schedule, fn)
    if err != nil {
        return fmt.Errorf("job əlavə xətası: %w", err)
    }

    s.entries[name] = id
    return nil
}

func (s *SchedulerService) RemoveJob(name string) {
    s.mu.Lock()
    defer s.mu.Unlock()

    if id, ok := s.entries[name]; ok {
        s.cron.Remove(id)
        delete(s.entries, name)
    }
}

func (s *SchedulerService) ListJobs() []string {
    s.mu.Lock()
    defer s.mu.Unlock()

    names := make([]string, 0, len(s.entries))
    for name := range s.entries {
        names = append(names, name)
    }
    return names
}
```

### Nümunə 5: Metric ilə monitorinq

```go
package main

import (
    "log/slog"
    "time"
)

type InstrumentedJob struct {
    name    string
    fn      func()
    metrics Metrics
    logger  *slog.Logger
}

type Metrics interface {
    RecordJobDuration(name string, duration time.Duration)
    RecordJobError(name string)
    RecordJobStart(name string)
}

func (j *InstrumentedJob) Run() {
    start := time.Now()
    j.metrics.RecordJobStart(j.name)
    j.logger.Info("Job başladı", slog.String("job", j.name))

    defer func() {
        duration := time.Since(start)
        j.metrics.RecordJobDuration(j.name, duration)
        j.logger.Info("Job tamamlandı",
            slog.String("job", j.name),
            slog.Duration("duration", duration),
        )
    }()

    j.fn()
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
Üç job olan scheduler yaz: hər 30 saniyə health check, hər 5 dəqiqə cache yeniləmə, gecəyarı log faylı arxivləmə. Graceful shutdown əlavə et.

**Tapşırıq 2:**
Overlapping qarşısını alan job runner yaz. Bir iş hələ çalışırsa növbəti buraxılsın. Log-a yazılsın.

**Tapşırıq 3:**
HTTP endpoint-i ilə dinamik scheduler: `POST /jobs` — yeni job əlavə et, `DELETE /jobs/:name` — sil, `GET /jobs` — siyahı.

## Əlaqəli Mövzular

- [53-graceful-shutdown.md](53-graceful-shutdown.md) — Graceful shutdown
- [48-processes-and-signals.md](48-processes-and-signals.md) — Signal idarəsi
- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — Goroutine əsasları
- [71-monitoring-and-observability.md](71-monitoring-and-observability.md) — Metric
