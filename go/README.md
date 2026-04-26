# Go (Golang) Proqramlaşdırma Dili

## Bu folder haqqında

PHP/Laravel developer üçün Go dilini sıfırdan professional arxitektura səviyyəsinə qədər öyrənmə yolu. Hər mövzu real layihə təcrübəsindən gələn praktik biliklər, trade-off analizi və kod nümunələri ilə çatdırılır.

**Hədəf auditoriya:** 5+ il PHP/Laravel təcrübəsi olan developer, Go-ya keçid etmək istəyir  
**Ümumi fayl sayı:** 88 mövzu + README  
**Proqramlaşdırma dili:** Go (Golang), kod nümunələri `go` bloklarında

---

## Səviyyələr

### ⭐ Junior (01-15) — Go əsasları

Tam sıfırdan başlayan: sintaksis, dəyişkənlər, əsas məlumat tipləri, funksiyalar, paketlər.

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-introduction.md](01-introduction.md) | Go dilinə giriş — niyə Go, PHP ilə müqayisə, Hello World |
| 02 | [02-variables.md](02-variables.md) | Dəyişkənlər — `var`, `:=`, sıfır dəyərlər, shadowing |
| 03 | [03-data-types.md](03-data-types.md) | Məlumat tipləri — int, float, bool, string, tip dönüşümü |
| 04 | [04-operators.md](04-operators.md) | Operatorlar — riyazi, müqayisə, məntiqi, bitwise |
| 05 | [05-conditionals.md](05-conditionals.md) | Şərtlər — if/else, switch, tipik Go patterns |
| 06 | [06-loops.md](06-loops.md) | Dövrələr — yalnız `for`, range, break/continue |
| 07 | [07-functions.md](07-functions.md) | Funksiyalar — multiple return, variadic, first-class functions |
| 08 | [08-arrays-and-slices.md](08-arrays-and-slices.md) | Array və Slice — fərq, append, copy, capacity |
| 09 | [09-maps.md](09-maps.md) | Map — yaratma, oxuma, silmə, iteration, nil map |
| 10 | [10-structs.md](10-structs.md) | Struct — tərif, embedding, method-lar, PHP class müqayisəsi |
| 11 | [11-pointers.md](11-pointers.md) | Pointer — `&`, `*`, nil pointer, PHP reference fərqi |
| 12 | [12-strings-and-strconv.md](12-strings-and-strconv.md) | String — rune, UTF-8, strconv, strings paketi |
| 13 | [13-file-operations.md](13-file-operations.md) | Fayl əməliyyatları — oxuma, yazma, defer, os paketi |
| 14 | [14-packages-and-modules.md](14-packages-and-modules.md) | Paket və modul — go.mod, import, visibility qaydaları |
| 15 | [15-recursion.md](15-recursion.md) | Rekursiya — klassik nümunələr, stack overflow, tail recursion |

---

### ⭐⭐ Middle (16-32) — Orta səviyyə konseptlər

Interface, error handling, concurrency əsasları, test yazma, Go-ya xas patterns.

| # | Fayl | Mövzu |
|---|------|-------|
| 16 | [16-regexp.md](16-regexp.md) | Regular expressions — `regexp` paketi, compile, find, replace |
| 17 | [17-interfaces.md](17-interfaces.md) | Interface — implicit implementation, composition, duck typing |
| 18 | [18-error-handling.md](18-error-handling.md) | Xəta idarəetməsi — `error`, `errors.Is`, `errors.As`, wrapping |
| 19 | [19-type-assertions.md](19-type-assertions.md) | Type assertion — type switch, interface dönüşümü |
| 20 | [20-json-encoding.md](20-json-encoding.md) | JSON — encoding/decoding, struct tags, custom marshaler |
| 21 | [21-enums.md](21-enums.md) | Enum — `iota`, typed constants, PHP enum müqayisəsi |
| 22 | [22-init-and-modules.md](22-init-and-modules.md) | `init()` funksiyası — icra sırası, package initialization |
| 23 | [23-time-and-scope.md](23-time-and-scope.md) | Time paketi, scope qaydaları — variable shadowing, closure scope |
| 24 | [24-testing.md](24-testing.md) | Test yazma — `testing`, table-driven tests, `t.Run`, coverage |
| 25 | [25-logging.md](25-logging.md) | Logging — `log`, `slog` (Go 1.21+), structured logging |
| 26 | [26-cli-app.md](26-cli-app.md) | CLI tətbiq — `flag`, `cobra`, subcommand, argüman parsing |
| 27 | [27-goroutines-and-channels.md](27-goroutines-and-channels.md) | Goroutine və Channel — `go`, `chan`, buffered, select |
| 28 | [28-context.md](28-context.md) | Context — timeout, cancellation, deadline, context propagation |
| 29 | [29-generics.md](29-generics.md) | Generics — type parameters, constraints, Go 1.18+ |
| 30 | [30-io-reader-writer.md](30-io-reader-writer.md) | io.Reader/Writer — streaming, pipe, bufio |
| 31 | [31-go-embed.md](31-go-embed.md) | go:embed — statik faylları binary-ə daxil etmək |
| 32 | [32-go-workspace.md](32-go-workspace.md) | Go Workspace — `go.work`, multi-module development |
| 75 | [75-errgroup.md](75-errgroup.md) | errgroup — paralel goroutine, xəta propagasiya, fan-out |

---

### ⭐⭐⭐ Senior (33-55, 76-78, 80, 82, 85-87) — Real layihə inkişafı

HTTP server/client, database, test, layihə strukturu, production patterns.

| # | Fayl | Mövzu |
|---|------|-------|
| 33 | [33-http-server.md](33-http-server.md) | HTTP server — `net/http`, handler, ServeMux, Go 1.22 routing |
| 34 | [34-http-client.md](34-http-client.md) | HTTP client — timeout, retry, custom transport, connection pool |
| 35 | [35-middleware-and-routing.md](35-middleware-and-routing.md) | Middleware, routing — chain, auth, logging, third-party (chi, gin) |
| 36 | [36-httptest.md](36-httptest.md) | HTTP test — `httptest.NewRecorder`, test server, integration test |
| 37 | [37-database.md](37-database.md) | Database — `database/sql`, connection pool, transaction, prepared stmt |
| 38 | [38-orm-and-sqlx.md](38-orm-and-sqlx.md) | ORM və sqlx — sqlx, GORM, migration, Eloquent müqayisəsi |
| 39 | [39-environment-and-config.md](39-environment-and-config.md) | Environment və config — `.env`, viper, 12-factor app |
| 40 | [40-embedding.md](40-embedding.md) | Struct embedding — composition vs inheritance, promoted fields |
| 41 | [41-slice-advanced.md](41-slice-advanced.md) | Slice (ətraflı) — backing array, copy trap, 3-index slice |
| 42 | [42-struct-advanced.md](42-struct-advanced.md) | Struct (ətraflı) — tags, anonymous, method set, comparable |
| 43 | [43-pointers-advanced.md](43-pointers-advanced.md) | Pointer (ətraflı) — unsafe, pointer arithmetic, nil dereference |
| 44 | [44-data-structures.md](44-data-structures.md) | Məlumat strukturları — stack, queue, linked list, heap Go-da |
| 45 | [45-functional-options.md](45-functional-options.md) | Functional Options pattern — constructor alternativləri |
| 46 | [46-text-templates.md](46-text-templates.md) | Text/HTML template — `text/template`, `html/template`, XSS qoruması |
| 47 | [47-tcp-server.md](47-tcp-server.md) | TCP server — `net.Listener`, raw socket, protocol implementasiya |
| 48 | [48-processes-and-signals.md](48-processes-and-signals.md) | Process, signal — SIGTERM, SIGINT, os/exec, subprocess |
| 49 | [49-files-advanced.md](49-files-advanced.md) | Fayl (ətraflı) — walk, watch, temp file, atomic write |
| 50 | [50-xml-and-url.md](50-xml-and-url.md) | XML, URL — encoding/xml, url.Values, query string parsing |
| 51 | [51-rate-limiting.md](51-rate-limiting.md) | Rate limiting — token bucket, `golang.org/x/time/rate`, middleware |
| 52 | [52-mocking-and-testify.md](52-mocking-and-testify.md) | Mock, testify — `testify/mock`, interface-based mocking |
| 53 | [53-graceful-shutdown.md](53-graceful-shutdown.md) | Graceful shutdown — signal, context, in-flight request tamamlama |
| 54 | [54-project-structure.md](54-project-structure.md) | Layihə strukturu — `cmd/`, `internal/`, `pkg/`, Standard Go Layout |
| 55 | [55-repository-pattern.md](55-repository-pattern.md) | Repository pattern — interface, implementation, test double |
| 76 | [76-input-validation.md](76-input-validation.md) | Input Validation — validator/v10, struct tags, custom validator |
| 77 | [77-database-migrations.md](77-database-migrations.md) | Database Migrations — goose, golang-migrate, go:embed, CI/CD |
| 78 | [78-file-upload.md](78-file-upload.md) | File Upload — multipart, streaming, S3, MinIO, güvənlik |
| 80 | [80-cron-scheduler.md](80-cron-scheduler.md) | Cron Scheduler — robfig/cron, panic recovery, distributed lock |
| 82 | [82-api-versioning.md](82-api-versioning.md) | API Versioning — URL path, header, deprecation, Sunset header |
| 85 | [85-email-smtp.md](85-email-smtp.md) | Email / SMTP — gomail, HTML şablon, async göndərmə, MailHog |
| 86 | [86-webhook.md](86-webhook.md) | Webhook — qəbul etmək, imza yoxlama, retry, idempotency |
| 87 | [87-go-generate.md](87-go-generate.md) | go generate — stringer, mockery, sqlc, kod generasiyası |

---

### ⭐⭐⭐⭐ Lead (56-67, 79, 81, 83, 84, 88) — İleri mövzular

Advanced concurrency, design patterns, WebSocket, gRPC, security, caching, JWT.

| # | Fayl | Mövzu |
|---|------|-------|
| 56 | [56-advanced-concurrency.md](56-advanced-concurrency.md) | Advanced concurrency — `sync.Mutex`, `RWMutex`, `WaitGroup`, `Once` |
| 57 | [57-advanced-concurrency-2.md](57-advanced-concurrency-2.md) | Advanced concurrency 2 — `atomic`, lock-free, goroutine pool |
| 58 | [58-channel-patterns.md](58-channel-patterns.md) | Channel patterns — fan-in/fan-out, pipeline, semaphore, done channel |
| 59 | [59-design-patterns.md](59-design-patterns.md) | Design patterns — singleton, factory, decorator, observer Go-da |
| 60 | [60-reflection.md](60-reflection.md) | Reflection — `reflect` paketi, runtime tip yoxlama, use-case-lər |
| 61 | [61-websocket.md](61-websocket.md) | WebSocket — `gorilla/websocket`, real-time, hub pattern |
| 62 | [62-security.md](62-security.md) | Security — HTTPS, CORS, SQL injection, input validation, bcrypt |
| 63 | [63-caching.md](63-caching.md) | Caching — in-memory, Redis, cache-aside pattern, invalidation |
| 64 | [64-dependency-injection.md](64-dependency-injection.md) | Dependency Injection — manual DI, Wire, Fx, interface-based |
| 65 | [65-jwt-and-auth.md](65-jwt-and-auth.md) | JWT, Auth — token yaratma/yoxlama, middleware, refresh token |
| 66 | 66-build-tags.md | Build tags — `//go:build`, platform-specific code, test tags |
| 67 | 67-grpc.md | gRPC — protobuf, unary, streaming, interceptor, status codes |
| 79 | [79-singleflight.md](79-singleflight.md) | Singleflight — thundering herd, cache stampede, request dedup |
| 81 | [81-oauth2.md](81-oauth2.md) | OAuth2 / OIDC — authorization code, PKCE, Google/GitHub login |
| 83 | [83-event-bus.md](83-event-bus.md) | Event Bus — domain events, pub-sub, sinxron/asinxron |
| 84 | [84-testcontainers.md](84-testcontainers.md) | Testcontainers — real PostgreSQL/Redis ilə inteqrasiya testi |
| 88 | [88-fuzz-testing.md](88-fuzz-testing.md) | Fuzz Testing — go test -fuzz, corpus, parser/validator testi |

---

### ⭐⭐⭐⭐⭐ Architect (68-74) — Arxitektura səviyyəsi

Production sistemlər, microservices, observability, performans mühəndisliyi, clean architecture.

| # | Fayl | Mövzu |
|---|------|-------|
| 68 | [68-profiling-and-benchmarking.md](68-profiling-and-benchmarking.md) | Profiling, Benchmarking — pprof, flame graph, `go test -bench`, benchstat |
| 69 | [69-memory-management.md](69-memory-management.md) | Memory Management — GC internals, heap/stack, escape analysis, GOGC |
| 70 | [70-docker-and-deploy.md](70-docker-and-deploy.md) | Docker, Deploy — multi-stage build, distroless/scratch, Kubernetes, CI/CD |
| 71 | [71-monitoring-and-observability.md](71-monitoring-and-observability.md) | Monitoring, Observability — Prometheus, OpenTelemetry, slog, SLO/SLI |
| 72 | [72-message-queues.md](72-message-queues.md) | Message Queues — Kafka, RabbitMQ, NATS, DLQ, idempotency, retry |
| 73 | [73-microservices.md](73-microservices.md) | Microservices — service discovery, circuit breaker, saga, gRPC, health check |
| 74 | [74-clean-architecture.md](74-clean-architecture.md) | Clean Architecture — hexagonal, ports & adapters, DI, Laravel MVC müqayisəsi |

---

## Oxuma Yolları

### Sürətli Başlanğıc (PHP developer üçün — 2 həftə)

PHP bilən developer üçün ən vacib fərqlər və Go-nun əsas konseptləri:

```
01 → 02 → 03 → 07 → 08 → 09 → 10 → 11
→ 17 (interface — PHP abstract class deyil!)
→ 18 (error handling — exception yoxdur!)
→ 27 (goroutine — PHP-də yoxdur)
→ 33 (HTTP server) → 37 (database) → 39 (config)
```

**Başlamaq üçün:** `01-introduction.md` PHP ilə müqayisəni ətraflı izah edir.

---

### Tam Öyrənmə Yolu (3-4 ay)

Sıfırdan Architect səviyyəsinə qədər:

```
Həftə 1-2 (Junior):     01 → 15
Həftə 3-5 (Middle):     16 → 32
Həftə 6-9 (Senior):     33 → 55
Həftə 10-12 (Lead):     56 → 67
Həftə 13-14 (Architect): 68 → 74
```

---

### Backend API Developer (6-8 həftə)

REST API qurmaq, database, auth, deployment — praktik yol:

```
Əsaslar:  01-11, 14, 17-18, 20
HTTP API: 27-28, 33-36, 39
Database: 37-38
Auth:     62, 65
Testing:  24, 36, 52
Deploy:   53, 54, 70
Prod:     71, 68
```

---

### DevOps / Infrastructure Go

CLI tool, sistem proqramlaşdırma, Kubernetes operator yazma:

```
Əsaslar:     01-15
CLI:         26, 48
Fayl/IO:     13, 30, 49
Şəbəkə:      33-34, 47
Deployment:  70
Monitoring:  71, 68, 69
Concurrency: 56-58
```

---

### Microservice Arxitektur

Tam microservice sisteminin qurulması:

```
Go əsasları: 01-18
Concurrency: 27-28, 56-58
HTTP/gRPC:   33-35, 67
Database:    37-38
Config:      39
Auth/Sec:    62, 65
Testing:     24, 36, 52
Graceful:    48, 53
Arch:        54, 55, 74
Deploy:      70
Monitoring:  71, 68, 69
Messaging:   72
Microservice: 73
```

---

## PHP/Laravel → Go Əsas Fərqlər

| PHP/Laravel | Go |
|-------------|-----|
| Exception (`throw`, `try/catch`) | `error` qaytarma, `errors.Is/As` |
| Abstract class, Interface | Yalnız interface (implicit) |
| Composer | go.mod (built-in) |
| PHP-FPM (hər request yeni proses) | Tək proses, goroutine ilə concurrency |
| Laravel Eloquent ORM | sqlx, GORM (və ya raw SQL) |
| `.env` + Laravel Config | os.Getenv, Viper |
| Laravel Queue + Horizon | Goroutine, Kafka/RabbitMQ |
| Artisan CLI | Cobra |
| Namespace | Package |
| `null` | Nil pointer, zero value |
| Laravel DI Container | Manual DI və ya Wire/Fx |

---

## Əlavə Resurslar

- **Rəsmi sənəd:** https://go.dev/doc/
- **Go Tour:** https://go.dev/tour/
- **Effective Go:** https://go.dev/doc/effective_go
- **Go Playground:** https://go.dev/play/
- **pkg.go.dev:** https://pkg.go.dev/
- **Awesome Go:** https://awesome-go.com/
