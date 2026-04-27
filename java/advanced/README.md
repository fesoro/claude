# Advanced — Cloud, Architecture, Deployment — 27 Mövzu (01-27)

Mikroservis dünyası: Spring Cloud, distributed arxitektura pattern-ləri, production deployment. Senior ⭐⭐⭐ → Lead ⭐⭐⭐⭐ səviyyə.

**Şərt:** [../spring/](../spring/) qovluğunun tamamı, xüsusən Security, Data, Messaging (Kafka).

**Öyrənmə yolu:** 01 → 24 sıra ilə. Arxitektura mövzuları müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Spring Cloud | 01-08 | Lead ⭐⭐⭐⭐ | Gateway, Eureka, Config, OpenFeign, Resilience4j, Tracing, Prometheus |
| 2 | Architecture Patterns | 09-20 | Senior ⭐⭐⭐ → Lead ⭐⭐⭐⭐ | SOLID, Clean, Hexagonal, DDD, CQRS, ES, Saga, Outbox, gRPC |
| 3 | Deployment | 21-24 | Senior ⭐⭐⭐ → Lead ⭐⭐⭐⭐ | Docker, Kubernetes, GitHub Actions CI/CD, GraalVM native |
| 4 | Production Engineering | 25-27 | Lead ⭐⭐⭐⭐ | Multi-tenancy patterns, production profiling workflow, ADR |

---

## Səviyyə Legendi

- **Junior ⭐** — Java ilk dəfə görür
- **Middle ⭐⭐** — Əsas syntax biləni, istehsalata hazırdır
- **Senior ⭐⭐⭐** — Mövzunu dərindən başa düşmək üçün
- **Lead ⭐⭐⭐⭐** — Tuning, internals, performance optimization, tech-lead səviyyə

---

## Phase 1: Spring Cloud (01-08)

Mikroservis ekosistemi: service discovery, centralized config, API gateway, resilience, distributed tracing.

| # | Mövzu | Səv. |
|---|-------|------|
| [01](01-cloud-overview.md) | Spring Cloud ekosistemi, komponentlər | Lead ⭐⭐⭐⭐ |
| [02](02-cloud-gateway.md) | routing, predicates, filters, rate limiting | Lead ⭐⭐⭐⭐ |
| [03](03-cloud-eureka.md) | service discovery, self-registration, heartbeat | Lead ⭐⭐⭐⭐ |
| [04](04-cloud-config.md) | config server, git backend, @RefreshScope | Lead ⭐⭐⭐⭐ |
| [05](05-cloud-openfeign.md) | declarative HTTP client, fallback, timeout | Lead ⭐⭐⭐⭐ |
| [06](06-cloud-resilience4j.md) | CircuitBreaker, Retry, Bulkhead, RateLimiter | Lead ⭐⭐⭐⭐ |
| [07](07-cloud-sleuth-zipkin.md) | Micrometer Tracing, OTEL Collector, head/tail-based sampling | Lead ⭐⭐⭐⭐ |
| [08](08-actuator-prometheus.md) | metrics export, Micrometer, Prometheus/Grafana | Lead ⭐⭐⭐⭐ |

## Phase 2: Architecture Patterns (09-20)

Enterprise distributed sistem dizaynı üçün əsas pattern-lər.

| # | Mövzu | Səv. |
|---|-------|------|
| [09](09-solid-principles.md) | SRP, OCP, LSP, ISP, DIP — Java nümunələri | Senior ⭐⭐⭐ |
| [10](10-clean-architecture.md) | Uncle Bob clean architecture, use-case layer | Lead ⭐⭐⭐⭐ |
| [11](11-hexagonal-architecture.md) | Ports & Adapters, package strukturu, Spring-də tətbiq | Lead ⭐⭐⭐⭐ |
| [12](12-ddd-tactical.md) | Entity, ValueObject, Aggregate, Repository, DomainService | Lead ⭐⭐⭐⭐ |
| [13](13-cqrs.md) | command/query ayrılması, read/write model | Lead ⭐⭐⭐⭐ |
| [14](14-event-sourcing.md) | event store, snapshot, replay | Lead ⭐⭐⭐⭐ |
| [15](15-saga-pattern.md) | choreography vs orchestration | Lead ⭐⭐⭐⭐ |
| [16](16-outbox-pattern.md) | transactional outbox, Debezium CDC | Lead ⭐⭐⭐⭐ |
| [17](17-api-gateway-patterns.md) | BFF, aggregation, protocol translation | Lead ⭐⭐⭐⭐ |
| [18](18-database-per-service.md) | data ownership, distributed transactions | Lead ⭐⭐⭐⭐ |
| [19](19-strangler-fig-pattern.md) | legacy miqrasiya strategiyası | Lead ⭐⭐⭐⭐ |
| [20](20-grpc.md) | protobuf, service definition, Spring gRPC | Lead ⭐⭐⭐⭐ |

## Phase 3: Deployment (21-24)

Spring Boot tətbiqinin production-a çatdırılması.

| # | Mövzu | Səv. |
|---|-------|------|
| [21](21-docker-spring-boot.md) | multi-stage Dockerfile, layered jar, JVM flags | Senior ⭐⭐⭐ |
| [22](22-kubernetes-basics.md) | Deployment, Service, ConfigMap, Secret, liveness/readiness | Lead ⭐⭐⭐⭐ |
| [23](23-github-actions-cicd.md) | Maven/Gradle build, test, container push pipeline | Senior ⭐⭐⭐ |
| [24](24-graalvm-native.md) | AOT compilation, Spring Boot 3 native, reflection config | Lead ⭐⭐⭐⭐ |

## Phase 4: Production Engineering (25-27)

Production sistemlərinin idarəsi: multi-tenant arxitektura, profiling metodologiyası.

| # | Mövzu | Səv. |
|---|-------|------|
| [25](25-multi-tenancy-patterns.md) | Row-level, Schema-per-tenant, DB-per-tenant; TenantContext, AbstractRoutingDataSource | Lead ⭐⭐⭐⭐ |
| [26](26-production-profiling-workflow.md) | Simptom → diaqnoz: CPU/latency/memory/deadlock playbook, JFR continuous | Lead ⭐⭐⭐⭐ |
| [27](27-adr-architecture-decision-records.md) | ADR nədir, Nygard/MADR formatı, lifecycle, adr-tools, real nümunələr | Lead ⭐⭐⭐⭐ |

---

**← Əvvəlki:** [spring/](../spring/) — Spring Framework & Boot (100 mövzu)

*27 fayl | Son yenilənmə: 2026-04-27*
