# Go Advanced — 44 Mövzu (01-44)

Production-ready arxitektura: advanced concurrency, design patterns, gRPC, security, resilience, observability, microservices, clean architecture, DDD/CQRS/Event Sourcing, Saga, K8s, CI/CD. Senior ⭐⭐⭐-dan Architect ⭐⭐⭐⭐⭐-ə qədər.

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
| 6 | Architecture Patterns I | 28-34 | Lead–Architect ⭐⭐⭐⭐⭐ | SOLID, Hexagonal, DDD, CQRS, Event Sourcing, Saga, Outbox |
| 7 | Architecture Patterns II | 35-37 | Lead ⭐⭐⭐⭐ | API Gateway, DB-per-service, Strangler Fig |
| 8 | Ops & Komandalı İş | 38-41 | Senior–Lead ⭐⭐⭐⭐ | Kubernetes, CI/CD, Multi-tenancy, ADR |\n| 9 | Testing & Operations | 42-44 | Senior ⭐⭐⭐ | Feature flags, load testing, secret management | |

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

---

## Faza 6: Architecture Patterns I (28-34) — Lead ⭐⭐⭐⭐ → Architect ⭐⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [28](28-solid-principles.md) | SOLID Prinsipləri | Go kontekstində S-O-L-I-D, interface-lərlə tətbiq |
| [29](29-hexagonal-architecture.md) | Hexagonal Architecture | Ports & Adapters, domain/ports/adapters qovluq strukturu |
| [30](30-ddd-tactical.md) | DDD Tactical Patterns | Entity, Value Object, Aggregate, Domain Event, Repository |
| [31](31-cqrs.md) | CQRS | Command/Query ayrılması, Command Bus, read/write model |
| [32](32-event-sourcing.md) | Event Sourcing | Event store, aggregate reconstruction, snapshot, EventStore interface |
| [33](33-saga-pattern.md) | Saga Pattern | Distributed transaction, Choreography vs Orchestration, kompensasiya |
| [34](34-outbox-pattern.md) | Outbox Pattern | Transactional outbox, FOR UPDATE SKIP LOCKED, CDC/Debezium |

---

## Faza 7: Architecture Patterns II (35-37) — Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [35](35-api-gateway-patterns.md) | API Gateway Patterns | Reverse proxy, auth middleware, BFF aggregation, Traefik |
| [36](36-database-per-service.md) | Database per Service | DB seçimi per service, API composition, event-driven sync |
| [37](37-strangler-fig-pattern.md) | Strangler Fig Pattern | PHP→Go miqrasiya, percentage routing, anti-corruption layer |

---

## Faza 8: Ops & Komandalı İş (38-41) — Senior ⭐⭐⭐ → Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [38](38-kubernetes-basics.md) | Kubernetes Basics | Pod, Deployment, Service, ConfigMap, HPA, health probe-lar |
| [39](39-github-actions-cicd.md) | GitHub Actions CI/CD | ci.yml, cd.yml, GHCR, golangci-lint, staging/prod deploy |
| [40](40-multi-tenancy.md) | Multi-tenancy | DB-per-tenant, schema-per-tenant, row-level isolation, middleware |
| [41](41-adr-architecture-decision-records.md) | ADR | Architecture Decision Records, lifecycle, 3 real nümunə |

---

## Faza 9: Testing & Operations (42-44) — Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [42](42-feature-flags.md) | Feature Flags | Static/dynamic flags, targeting, percentage rollout, GrowthBook |
| [43](43-load-testing.md) | Load Testing | `testing.B`, vegeta, k6, P95/P99, CI benchmark regression |
| [44](44-secret-management.md) | Secret Management | Env var, AWS Secrets Manager, Vault dynamic secrets, secret scanning |

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
