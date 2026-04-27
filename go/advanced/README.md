# Go Advanced — 27 Mövzu (01-27)

Production-ready arxitektura: advanced concurrency, design patterns, gRPC, security, resilience, observability, microservices, clean architecture. Senior ⭐⭐⭐-dan Architect ⭐⭐⭐⭐⭐-ə qədər.

**Ön şərt:** `core/` + `backend/` tamamlanmış olmalıdır.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Advanced Concurrency | 01-03 | Lead ⭐⭐⭐⭐ | Mutex, atomic, channel patterns |
| 2 | Design & Code Quality | 04-05 | Lead ⭐⭐⭐⭐ | Design patterns, reflection |
| 3 | Sistem & Auth | 06-12 | Lead ⭐⭐⭐⭐ | WebSocket, security, caching, DI, JWT, gRPC |
| 4 | Resilience & Testing | 13-20 | Lead ⭐⭐⭐⭐ | Singleflight, OAuth2, event bus, fuzz, circuit breaker, GraphQL |
| 5 | Arxitektura & İnfrastruktur | 21-27 | Architect ⭐⭐⭐⭐⭐ | Profiling, memory, Docker, observability, microservices |

---

## Faza 1: Advanced Concurrency (01-03) — Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [01](01-advanced-concurrency.md) | Advanced Concurrency I | `sync.Mutex`, `sync.RWMutex`, `sync.WaitGroup`, `sync.Once` |
| [02](02-advanced-concurrency-2.md) | Advanced Concurrency II | `atomic`, lock-free structures, goroutine pool |
| [03](03-channel-patterns.md) | Channel Patterns | Fan-in/out, pipeline, semaphore, done channel |

---

## Faza 2: Design & Code Quality (04-05) — Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [04](04-design-patterns.md) | Design Patterns | Singleton, factory, decorator, observer — Go ilə |
| [05](05-reflection.md) | Reflection | `reflect` paketi, dynamic dispatch, struct field inspection |

---

## Faza 3: Sistem & Auth (06-12) — Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [06](06-websocket.md) | WebSocket | `gorilla/websocket`, hub pattern, broadcast, reconnect |
| [07](07-security.md) | Security | HTTPS, CORS, SQL injection, bcrypt, HMAC, input sanitize |
| [08](08-caching.md) | Caching | In-memory cache, Redis, cache-aside pattern, TTL |
| [09](09-dependency-injection.md) | Dependency Injection | Manuel DI, `Wire`, `Fx` — PHP DI Container müqayisəsi |
| [10](10-jwt-and-auth.md) | JWT & Auth | `golang-jwt/jwt`, access/refresh token, middleware |
| [11](11-build-tags.md) | Build Tags | `//go:build`, conditional compile, integration test separation |
| [12](12-grpc.md) | gRPC | Protobuf, service definition, streaming, Go client/server |

---

## Faza 4: Resilience & Testing (13-20) — Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [13](13-singleflight.md) | Singleflight | Thundering herd, `golang.org/x/sync/singleflight`, cache stampede |
| [14](14-oauth2.md) | OAuth2 & OIDC | Authorization code flow, PKCE, token refresh, Go library |
| [15](15-event-bus.md) | Event Bus | In-process pub/sub, typed events, goroutine fan-out |
| [16](16-testcontainers.md) | Testcontainers | Real DB/Redis integration test, Docker-based fixtures |
| [17](17-fuzz-testing.md) | Fuzz Testing | `go test -fuzz`, corpus, coverage-guided fuzzing |
| [18](18-circuit-breaker-and-retry.md) | Circuit Breaker & Retry | `gobreaker`, exponential backoff, half-open state |
| [19](19-sync-pool.md) | sync.Pool | Object reuse, GC pressure azaltma, byte buffer pool |
| [20](20-graphql.md) | GraphQL | `gqlgen`, schema-first, N+1, DataLoader, subscription |

---

## Faza 5: Arxitektura & İnfrastruktur (21-27) — Architect ⭐⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [21](21-profiling-and-benchmarking.md) | Profiling & Benchmarking | `pprof`, flame graph, `go test -bench`, CPU/memory profil |
| [22](22-memory-management.md) | Memory Management | GC internals, escape analysis, GOGC, heap vs stack |
| [23](23-docker-and-deploy.md) | Docker & Deploy | Multi-stage build, distroless, Kubernetes, CI/CD pipeline |
| [24](24-monitoring-and-observability.md) | Monitoring & Observability | Prometheus, OpenTelemetry, trace, SLI/SLO |
| [25](25-message-queues.md) | Message Queues | Kafka, RabbitMQ, NATS — consumer, DLQ, idempotency |
| [26](26-microservices.md) | Microservices | Service discovery, health check, saga pattern, API gateway |
| [27](27-clean-architecture.md) | Clean Architecture | Hexagonal, ports & adapters, dependency rule, DI |

---

## PHP/Laravel → Go Advanced Müqayisəsi

| PHP/Laravel | Go Advanced |
|-------------|-------------|
| Thread/process yoxdur | `sync.Mutex`, `sync.RWMutex`, goroutine pool |
| Laravel Events | Event bus (channel-based pub/sub) |
| Horizon (Redis queue) | Goroutine pool + Redis / Kafka consumer |
| Laravel DI Container | Wire / Fx / manual DI |
| Passport/Sanctum | `golang-jwt/jwt` + middleware |
| `rebing/graphql-laravel` | `gqlgen` (schema-first) |
| Telescope, Pulse | pprof, OpenTelemetry, Prometheus |
| PHP-FPM + Nginx | Standalone binary, minimal container |
| Lumen microservice | Go binary, ~5MB, <10ms startup |
