# Advanced — Cloud, Architecture, Deployment — 26 Mövzu (01-26)

Mikroservis dünyası: Spring Cloud, distributed arxitektura pattern-ləri, production deployment. Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ səviyyə.

**Şərt:** [../spring/](../spring/) qovluğunun tamamı, xüsusən Security, Data, Messaging (Kafka).

**Öyrənmə yolu:** 01 → 24 sıra ilə. Arxitektura mövzuları müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Spring Cloud | 01-08 | Expert ⭐⭐⭐⭐ | Gateway, Eureka, Config, OpenFeign, Resilience4j, Tracing, Prometheus |
| 2 | Architecture Patterns | 09-20 | Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ | SOLID, Clean, Hexagonal, DDD, CQRS, ES, Saga, Outbox, gRPC |
| 3 | Deployment | 21-24 | Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ | Docker, Kubernetes, GitHub Actions CI/CD, GraalVM native |
| 4 | Production Engineering | 25-26 | Lead ⭐⭐⭐⭐ | Multi-tenancy patterns, production profiling workflow |

---

## Səviyyə Legendi

- **Beginner ⭐** — Java ilk dəfə görür
- **Intermediate ⭐⭐** — Əsas syntax biləni, istehsalata hazırdır
- **Advanced ⭐⭐⭐** — Mövzunu dərindən başa düşmək üçün
- **Expert ⭐⭐⭐⭐** — Tuning, internals, performance optimization, tech-lead səviyyə

---

## Phase 1: Spring Cloud (01-08)

Mikroservis ekosistemi: service discovery, centralized config, API gateway, resilience, distributed tracing.

| # | Mövzu | Səv. |
|---|-------|------|
| [01](01-cloud-overview.md) | Spring Cloud ekosistemi, komponentlər | Expert ⭐⭐⭐⭐ |
| [02](02-cloud-gateway.md) | routing, predicates, filters, rate limiting | Expert ⭐⭐⭐⭐ |
| [03](03-cloud-eureka.md) | service discovery, self-registration, heartbeat | Expert ⭐⭐⭐⭐ |
| [04](04-cloud-config.md) | config server, git backend, @RefreshScope | Expert ⭐⭐⭐⭐ |
| [05](05-cloud-openfeign.md) | declarative HTTP client, fallback, timeout | Expert ⭐⭐⭐⭐ |
| [06](06-cloud-resilience4j.md) | CircuitBreaker, Retry, Bulkhead, RateLimiter | Expert ⭐⭐⭐⭐ |
| [07](07-cloud-sleuth-zipkin.md) | Micrometer Tracing, OTEL Collector, head/tail-based sampling | Expert ⭐⭐⭐⭐ |
| [08](08-actuator-prometheus.md) | metrics export, Micrometer, Prometheus/Grafana | Expert ⭐⭐⭐⭐ |

## Phase 2: Architecture Patterns (09-20)

Enterprise distributed sistem dizaynı üçün əsas pattern-lər.

| # | Mövzu | Səv. |
|---|-------|------|
| [09](09-solid-principles.md) | SRP, OCP, LSP, ISP, DIP — Java nümunələri | Advanced ⭐⭐⭐ |
| [10](10-clean-architecture.md) | Uncle Bob clean architecture, use-case layer | Expert ⭐⭐⭐⭐ |
| [11](11-hexagonal-architecture.md) | Ports & Adapters, package strukturu, Spring-də tətbiq | Expert ⭐⭐⭐⭐ |
| [12](12-ddd-tactical.md) | Entity, ValueObject, Aggregate, Repository, DomainService | Expert ⭐⭐⭐⭐ |
| [13](13-cqrs.md) | command/query ayrılması, read/write model | Expert ⭐⭐⭐⭐ |
| [14](14-event-sourcing.md) | event store, snapshot, replay | Expert ⭐⭐⭐⭐ |
| [15](15-saga-pattern.md) | choreography vs orchestration | Expert ⭐⭐⭐⭐ |
| [16](16-outbox-pattern.md) | transactional outbox, Debezium CDC | Expert ⭐⭐⭐⭐ |
| [17](17-api-gateway-patterns.md) | BFF, aggregation, protocol translation | Expert ⭐⭐⭐⭐ |
| [18](18-database-per-service.md) | data ownership, distributed transactions | Expert ⭐⭐⭐⭐ |
| [19](19-strangler-fig-pattern.md) | legacy miqrasiya strategiyası | Expert ⭐⭐⭐⭐ |
| [20](20-grpc.md) | protobuf, service definition, Spring gRPC | Expert ⭐⭐⭐⭐ |

## Phase 3: Deployment (21-24)

Spring Boot tətbiqinin production-a çatdırılması.

| # | Mövzu | Səv. |
|---|-------|------|
| [21](21-docker-spring-boot.md) | multi-stage Dockerfile, layered jar, JVM flags | Advanced ⭐⭐⭐ |
| [22](22-kubernetes-basics.md) | Deployment, Service, ConfigMap, Secret, liveness/readiness | Expert ⭐⭐⭐⭐ |
| [23](23-github-actions-cicd.md) | Maven/Gradle build, test, container push pipeline | Advanced ⭐⭐⭐ |
| [24](24-graalvm-native.md) | AOT compilation, Spring Boot 3 native, reflection config | Expert ⭐⭐⭐⭐ |

## Phase 4: Production Engineering (25-26)

Production sistemlərinin idarəsi: multi-tenant arxitektura, profiling metodologiyası.

| # | Mövzu | Səv. |
|---|-------|------|
| [25](25-multi-tenancy-patterns.md) | Row-level, Schema-per-tenant, DB-per-tenant; TenantContext, AbstractRoutingDataSource | Lead ⭐⭐⭐⭐ |
| [26](26-production-profiling-workflow.md) | Simptom → diaqnoz: CPU/latency/memory/deadlock playbook, JFR continuous | Lead ⭐⭐⭐⭐ |

---

**← Əvvəlki:** [spring/](../spring/) — Spring Framework & Boot (100 mövzu)

*26 fayl | Son yenilənmə: 2026-04-27*
