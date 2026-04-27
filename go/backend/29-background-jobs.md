# Background Job Processing — asynq (Senior)

## İcmal

Background job processing — uzun sürən və ya asinxron icra edilməli işləri (email göndərmə, PDF generasiya, hesabat, API sync) HTTP request-in xaricinə çıxarmaq üçün istifadə olunan pattern-dir. Go-da bu məqsəd üçün ən geniş yayılmış həll **asynq** kitabxanasıdır.

`asynq` Redis-i backend kimi istifadə edir. Task-lar Redis-ə yazılır, worker process onları oxuyub icra edir. Sidekiq (Ruby) arxitekturuna bənzəyir. `machinery`, `gocraft/work`, Temporal kimi alternativlər də mövcuddur, lakin asynq Go ekosisteminin standartına çevrilmişdir.

## Niyə Vacibdir

- HTTP handler-də 500ms-dən çox davam edən işləri background-a köçürmək — response time azalır
- Email, push notification, Slack mesajı — response-u gözlətməməliyik
- Idempotency, retry, dead letter queue — davamlılıq tələb edən proseslər
- Priority queue — ödəniş taskları, standart bildirişdən önə keçə bilsin
- Rate limiting — xarici API-ləri sürət limitinə görə tənzimlənmiş şəkildə çağırmaq

## Əsas Anlayışlar

### Task

Task — icra ediləcək işin özüdür. `Type` (identifier) + `Payload` (JSON) ilə müəyyən edilir:

```go
const TypeEmailWelcome = "email:welcome"

type EmailWelcomePayload struct {
    UserID int
    Email  string
    Name   string
}

func NewEmailWelcomeTask(userID int, email, name string) (*asynq.Task, error) {
    payload, err := json.Marshal(EmailWelcomePayload{UserID: userID, Email: email, Name: name})
    if err != nil {
        return nil, err
    }
    return asynq.NewTask(TypeEmailWelcome, payload), nil
}
```

### Client — Task göndərmək

```go
client := asynq.NewClient(asynq.RedisClientOpt{Addr: "localhost:6379"})
defer client.Close()

// Dərhal icra
task, _ := NewEmailWelcomeTask(42, "user@example.com", "Ali")
info, err := client.Enqueue(task)

// 5 dəqiqə sonra icra
info, err = client.Enqueue(task, asynq.ProcessIn(5*time.Minute))

// Müəyyən vaxtda icra
info, err = client.Enqueue(task, asynq.ProcessAt(time.Now().Add(24*time.Hour)))

// Prioritet queue-ya göndər
info, err = client.Enqueue(task, asynq.Queue("critical"))

// Unique task — eyni payload 5 dəqiqə ərzində bir dəfə
info, err = client.Enqueue(task, asynq.Unique(5*time.Minute))
```

### Server — Task işləmək

```go
srv := asynq.NewServer(
    asynq.RedisClientOpt{Addr: "localhost:6379"},
    asynq.Config{
        // Queue prioritetləri: critical 3x daha tez işlənir
        Queues: map[string]int{
            "critical": 6,
            "default":  3,
            "low":      1,
        },
        Concurrency: 10, // eyni anda max 10 worker goroutine
        ErrorHandler: asynq.ErrorHandlerFunc(func(ctx context.Context, task *asynq.Task, err error) {
            slog.Error("task failed", "type", task.Type(), "err", err)
        }),
    },
)

mux := asynq.NewServeMux()
mux.HandleFunc(TypeEmailWelcome, handleEmailWelcome)
mux.HandleFunc(TypeReportGenerate, handleReportGenerate)

if err := srv.Run(mux); err != nil {
    log.Fatal(err)
}
```

### Handler

```go
func handleEmailWelcome(ctx context.Context, task *asynq.Task) error {
    var payload EmailWelcomePayload
    if err := json.Unmarshal(task.Payload(), &payload); err != nil {
        return fmt.Errorf("unmarshal: %w", err)
    }

    // Context-dən deadline/cancellation yoxla
    select {
    case <-ctx.Done():
        return ctx.Err()
    default:
    }

    if err := sendWelcomeEmail(ctx, payload.Email, payload.Name); err != nil {
        return fmt.Errorf("send email: %w", err)  // asynq retry edəcək
    }
    return nil  // nil → task completed
}
```

### Retry mexanizmi

```go
// Default: 25 retry, exponential backoff
// Custom retry count:
task := asynq.NewTask(TypeEmailWelcome, payload,
    asynq.MaxRetry(3),
    asynq.Timeout(30*time.Second),
)

// Retry-ni dayandırmaq (bir xəta növü üçün):
func handleEmailWelcome(ctx context.Context, task *asynq.Task) error {
    // ...
    if errors.Is(err, ErrInvalidEmail) {
        return fmt.Errorf("%w: %w", asynq.SkipRetry, err) // retry olmayacaq
    }
    return err
}
```

### Periodic (Cron) Task-lar

```go
scheduler := asynq.NewScheduler(
    asynq.RedisClientOpt{Addr: "localhost:6379"},
    nil,
)

// Hər gün saat 06:00-da
task := asynq.NewTask("report:daily", nil)
scheduler.Register("0 6 * * *", task)

// Hər 5 dəqiqədə
healthTask := asynq.NewTask("health:check", nil)
scheduler.Register("*/5 * * * *", healthTask)

scheduler.Run()
```

## Praktik Baxış

### Project Strukturu

```
cmd/
  api/main.go        — HTTP server
  worker/main.go     — asynq worker server
internal/
  tasks/
    client.go        — Enqueue funksiyaları
    handlers.go      — Handler funksiyaları
    types.go         — Task type constant-ları + payload struct-ları
```

### Worker main.go

```go
func main() {
    redisOpt := asynq.RedisClientOpt{Addr: os.Getenv("REDIS_ADDR")}

    srv := asynq.NewServer(redisOpt, asynq.Config{
        Queues: map[string]int{
            "critical": 6,
            "default":  3,
            "low":      1,
        },
        Concurrency: 20,
    })

    mux := asynq.NewServeMux()
    tasks.RegisterHandlers(mux, deps)

    // Graceful shutdown: SIGTERM/SIGINT gəldikdə in-flight task-lar bitər
    if err := srv.Run(mux); err != nil {
        log.Fatal(err)
    }
}
```

### Middleware (logging, panic recovery)

```go
func loggingMiddleware(h asynq.Handler) asynq.Handler {
    return asynq.HandlerFunc(func(ctx context.Context, task *asynq.Task) error {
        start := time.Now()
        err := h.ProcessTask(ctx, task)
        slog.Info("task processed",
            "type", task.Type(),
            "duration", time.Since(start),
            "error", err,
        )
        return err
    })
}

mux.Use(loggingMiddleware)
```

### Trade-off-lar

| | asynq | Temporal | Simple goroutine |
|--|-------|----------|-----------------|
| Davamlılıq | Redis (persist) | DB-based | Yox (restart = itkisi) |
| Retry | Var | Var | Əl ilə |
| Workflow | Yox | Var | Yox |
| Complexity | Az | Orta/Çox | Çox az |
| Use case | Task queue | Complex workflow | Fire-and-forget |

### Nə vaxt istifadə etməmək

- Çox sadə, bir dəfəlik işlər üçün goroutine + channel kifayətdir
- Workflow orkestrasyonu lazımdırsa → Temporal
- Message broker integration lazımdırsa → Kafka/RabbitMQ birbaşa

## Nümunələr

### User qeydiyyatı zamanı email göndərmə

```go
// Handler-də:
func (h *AuthHandler) Register(w http.ResponseWriter, r *http.Request) {
    user, err := h.svc.CreateUser(r.Context(), input)
    if err != nil {
        // ...
    }

    // Email-i background-a at, response-u gözlətmə
    task, _ := tasks.NewEmailWelcomeTask(user.ID, user.Email, user.Name)
    h.taskClient.Enqueue(task, asynq.Queue("default"))

    json.NewEncoder(w).Encode(user)
}
```

### Unique task — double enqueue-nun qarşısını al

```go
// Eyni userId üçün 10 dəqiqə ərzində yalnız bir report task
task := asynq.NewTask(TypeReportGenerate, payload,
    asynq.TaskID(fmt.Sprintf("report:%d", userID)),  // deterministic ID
    asynq.Unique(10*time.Minute),
)
client.Enqueue(task) // İkinci dəfə çağırılsa, ErrTaskIDConflict qaytar
```

## Praktik Tapşırıqlar

1. **Sadə email worker:** User qeydiyyatı → welcome email task → worker handler
2. **Priority queue:** Ödəniş bildirişi "critical", marketing email "low" queue-ya göndər
3. **Retry test:** Xəta verən handler yaz, 3 retry sonra dead letter-ə düşdüyünü yoxla
4. **asynqmon:** `docker run -d -p 8080:8080 hibiken/asynqmon` ilə monitoring dashboard qur
5. **Periodic report:** Hər gün gecə saat 02:00-da DB-dən statistika topla, email göndər

## PHP ilə Müqayisə

```
Laravel Queue   →   asynq (Go)
─────────────────────────────────────────────────────
Job class       →   Task (type + payload struct)
dispatch()      →   client.Enqueue()
handle()        →   handler function (ctx, task)
Horizon         →   asynqmon (monitoring UI)
failed_jobs     →   asynq dead task set (Redis)
queue:work      →   srv.Run(mux)
delay()         →   asynq.ProcessIn(duration)
onQueue()       →   asynq.Queue("name")
ShouldBeUnique  →   asynq.Unique() + TaskID
```

**Fərqlər:**
- Laravel: PHP-də class-based, autoloading, Eloquent ilə inteqrasiya
- asynq: Go-da function-based, Redis protokolunu birbaşa istifadə edir
- Laravel: database queue driver da var; asynq yalnız Redis
- asynq: typed payload (struct), Laravel: serializasiya edilmiş PHP object

## Əlaqəli Mövzular

- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — goroutine əsasları
- [28-context.md](28-context.md) — context ilə timeout/cancellation
- [53-graceful-shutdown.md](53-graceful-shutdown.md) — in-flight task-ların tamamlanması
- [63-caching.md](63-caching.md) — Redis inteqrasiyası
- [72-message-queues.md](72-message-queues.md) — Kafka/RabbitMQ ilə fərq
- [80-cron-scheduler.md](80-cron-scheduler.md) — alternativ cron yanaşması
