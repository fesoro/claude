# Go Backend — 38 Mövzu (01-38)

Go ilə praktiki backend development: HTTP server/client, database, ORM, project structure, testing, real-world patterns. Middle ⭐⭐-dan Senior ⭐⭐⭐-ə qədər.

**Ön şərt:** `core/` folder-i tamamlanmış olmalıdır.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | HTTP & API | 01-04 | Middle ⭐⭐ | HTTP server, client, routing, test |
| 2 | Database | 05-06 | Middle ⭐⭐ | database/sql, ORM, sqlx, GORM |
| 3 | Konfigurasiya & Patterns | 07-10 | Middle ⭐⭐ | Config, data structures, functional options, templates |
| 4 | Sistem & Alətlər | 11-19 | Senior ⭐⭐⭐ | TCP, processes, files, rate limiting, testing, project, repo |
| 5 | Production Patterns | 20-33 | Senior ⭐⭐⭐ | Validation, migrations, cron, versioning, email, webhook, SSE, pagination, idempotency |
| 6 | İnfrastruktur & Observability | 34-38 | Middle–Senior ⭐⭐–⭐⭐⭐ | Redis, sqlc, TLS, health check, OpenTelemetry | |

---

## Faza 1: HTTP & API (01-04) — Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [01](01-http-server.md) | HTTP Server | `net/http`, handler, ServeMux, middleware zənciri |
| [02](02-http-client.md) | HTTP Client | `http.Client`, timeout, retry, custom transport |
| [03](03-middleware-and-routing.md) | Middleware & Routing | Chi/Gorilla mux, middleware chain, path params |
| [04](04-httptest.md) | HTTP Test | `httptest.NewRecorder`, handler test, integration test |

---

## Faza 2: Database (05-06) — Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [05](05-database.md) | database/sql | Əlaqə pool, query, scan, transaction, prepared statement |
| [06](06-orm-and-sqlx.md) | ORM & sqlx | GORM, sqlx, named query, struct scan, PHP Eloquent müqayisəsi |

---

## Faza 3: Konfiqurasiya & Patterns (07-10) — Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [07](07-environment-and-config.md) | Environment & Config | `.env`, Viper, config struct, multi-env |
| [08](08-data-structures.md) | Data Structures | Stack, queue, linked list, tree — Go ilə implementasiya |
| [09](09-functional-options.md) | Functional Options | Option pattern, builder alternative, API design |
| [10](10-text-templates.md) | Text Templates | `text/template`, `html/template`, delimiters, funcs |

---

## Faza 4: Sistem & Alətlər (11-19) — Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [11](11-tcp-server.md) | TCP Server | `net.Listener`, raw TCP, custom protocol |
| [12](12-processes-and-signals.md) | Processes & Signals | `os/exec`, `syscall`, SIGTERM/SIGINT, process management |
| [13](13-files-advanced.md) | Files Advanced | `os`, `bufio`, `filepath`, directory walk, watch |
| [14](14-xml-and-url.md) | XML & URL | `encoding/xml`, `net/url`, query params parsing |
| [15](15-rate-limiting.md) | Rate Limiting | Token bucket, `golang.org/x/time/rate`, per-IP limit |
| [16](16-mocking-and-testify.md) | Mocking & Testify | Interface mock, `testify/mock`, `gomock`, assertion |
| [17](17-graceful-shutdown.md) | Graceful Shutdown | `os.Signal`, `context.WithCancel`, server drain |
| [18](18-project-structure.md) | Project Structure | Standard layout, `/cmd`, `/internal`, `/pkg` |
| [19](19-repository-pattern.md) | Repository Pattern | Interface-based repo, test double, PHP Repository müqayisəsi |

---

## Faza 5: Production Patterns (20-32) — Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [20](20-errgroup.md) | errgroup | Parallel goroutine-lar, ilk xəta ilə ləğv, `golang.org/x/sync` |
| [21](21-input-validation.md) | Input Validation | `validator` paketi, custom rule, struct tag validation |
| [22](22-database-migrations.md) | Database Migrations | `golang-migrate`, embed SQL, CI/CD inteqrasiyası |
| [23](23-file-upload.md) | File Upload | Multipart form, S3 upload, size/type limit, streaming |
| [24](24-cron-scheduler.md) | Cron Scheduler | `robfig/cron`, `gocron`, distributed lock ilə |
| [25](25-api-versioning.md) | API Versioning | URL prefix, header-based, content negotiation |
| [26](26-email-smtp.md) | Email & SMTP | `net/smtp`, MIME, HTML email, attachment, queue |
| [27](27-webhook.md) | Webhook | HMAC signature verify, retry logic, fan-out |
| [28](28-go-generate.md) | go generate | `//go:generate`, stringer, mockgen, sqlc inteqrasiyası |
| [29](29-background-jobs.md) | Background Jobs | Goroutine pool, job queue, asynq (Redis-backed) |
| [30](30-sse-server-sent-events.md) | SSE | `http.Flusher`, real-time push, reconnect |
| [31](31-swagger-openapi.md) | Swagger & OpenAPI | `swaggo/swag`, annotation, Swagger UI, spec generation |
| [32](32-pagination.md) | Pagination | Offset, cursor, keyset — performans müqayisəsi |
| [33](33-idempotency-pattern.md) | Idempotency Pattern | Idempotency-Key header, Redis SET NX, atomic replay prevention |

---

## Faza 6: İnfrastruktur & Observability (34-38) — Middle–Senior ⭐⭐–⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [34](34-redis-client.md) | Redis Client | go-redis v9, pool, pipeline, pub/sub, distributed lock |
| [35](35-sqlc.md) | sqlc | Type-safe SQL code generation, `:one/:many/:exec`, repo pattern |
| [36](36-tls-https.md) | TLS/HTTPS | `tls.Config`, Let's Encrypt autocert, mTLS, HSTS |
| [37](37-health-check.md) | Health Check | Liveness/readiness probe, dependency check, Kubernetes YAML |
| [38](38-opentelemetry-tracing.md) | OpenTelemetry Tracing | OTel SDK, OTLP, span, trace propagation, slog inteqrasiyası |

---

## PHP/Laravel → Go Backend Müqayisəsi

| PHP/Laravel | Go Backend |
|-------------|------------|
| Laravel Router | `net/http` ServeMux / Chi |
| Middleware | Handler wrapper funksiyası |
| Eloquent ORM | GORM / sqlx |
| `.env` + config/ | Viper / `os.Getenv` |
| Repository pattern | Interface + struct |
| `artisan make:migration` | golang-migrate |
| `dispatch(new Job)` | goroutine / asynq |
| Guzzle HTTP client | `net/http` Client |
| PHP-FPM worker | goroutine (built-in) |
| `phpunit` mock | testify/mock / gomock |
