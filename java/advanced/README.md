# Advanced — Cloud, Architecture, Deployment — 24 Mövzu (000-023)

Mikroservis dünyası: Spring Cloud, distributed arxitektura pattern-ləri, production deployment. İrəli → Ekspert səviyyə.

**Şərt:** [../spring/](../spring/) qovluğunun tamamı, xüsusən Security, Data, Messaging (Kafka).

**Öyrənmə yolu:** 000 → 023 sıra ilə. Arxitektura mövzuları müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Spring Cloud | 000-007 | Ekspert | Gateway, Eureka, Config, OpenFeign, Resilience4j, Sleuth, Prometheus |
| 2 | Architecture Patterns | 008-019 | İrəli → Ekspert | SOLID, Clean, Hexagonal, DDD, CQRS, ES, Saga, Outbox, gRPC |
| 3 | Deployment | 020-023 | İrəli → Ekspert | Docker, Kubernetes, GitHub Actions CI/CD, GraalVM native |

---

## Səviyyə Legendi

- **Başlanğıc** — Java ilk dəfə görür
- **Orta** — Əsas syntax biləni, istehsalata hazırdır
- **İrəli** — Mövzunu dərindən başa düşmək üçün
- **Ekspert** — Tuning, internals, performance optimization, tech-lead səviyyə

---

## Phase 1: Spring Cloud (000-007)

Mikroservis ekosistemi: service discovery, centralized config, API gateway, resilience, distributed tracing.

| # | Mövzu | Səv. |
|---|-------|------|
| [000](000-cloud-overview.md) | Spring Cloud ekosistemi, komponentlər | Ekspert |
| [001](001-cloud-gateway.md) | routing, predicates, filters, rate limiting | Ekspert |
| [002](002-cloud-eureka.md) | service discovery, self-registration, heartbeat | Ekspert |
| [003](003-cloud-config.md) | config server, git backend, @RefreshScope | Ekspert |
| [004](004-cloud-openfeign.md) | declarative HTTP client, fallback, timeout | Ekspert |
| [005](005-cloud-resilience4j.md) | CircuitBreaker, Retry, Bulkhead, RateLimiter | Ekspert |
| [006](006-cloud-sleuth-zipkin.md) | distributed tracing, trace/span, Zipkin | Ekspert |
| [007](007-actuator-prometheus.md) | metrics export, Micrometer, Prometheus/Grafana | Ekspert |

## Phase 2: Architecture Patterns (008-019)

Enterprise distributed sistem dizaynı üçün əsas pattern-lər.

| # | Mövzu | Səv. |
|---|-------|------|
| [008](008-solid-principles.md) | SRP, OCP, LSP, ISP, DIP — Java nümunələri | İrəli |
| [009](009-clean-architecture.md) | Uncle Bob clean architecture, use-case layer | Ekspert |
| [010](010-hexagonal-architecture.md) | Ports & Adapters, package strukturu, Spring-də tətbiq | Ekspert |
| [011](011-ddd-tactical.md) | Entity, ValueObject, Aggregate, Repository, DomainService | Ekspert |
| [012](012-cqrs.md) | command/query ayrılması, read/write model | Ekspert |
| [013](013-event-sourcing.md) | event store, snapshot, replay | Ekspert |
| [014](014-saga-pattern.md) | choreography vs orchestration | Ekspert |
| [015](015-outbox-pattern.md) | transactional outbox, Debezium CDC | Ekspert |
| [016](016-api-gateway-patterns.md) | BFF, aggregation, protocol translation | Ekspert |
| [017](017-database-per-service.md) | data ownership, distributed transactions | Ekspert |
| [018](018-strangler-fig-pattern.md) | legacy miqrasiya strategiyası | Ekspert |
| [019](019-grpc.md) | protobuf, service definition, Spring gRPC | Ekspert |

## Phase 3: Deployment (020-023)

Spring Boot tətbiqinin production-a çatdırılması.

| # | Mövzu | Səv. |
|---|-------|------|
| [020](020-docker-spring-boot.md) | multi-stage Dockerfile, layered jar, JVM flags | İrəli |
| [021](021-kubernetes-basics.md) | Deployment, Service, ConfigMap, Secret, liveness/readiness | Ekspert |
| [022](022-github-actions-cicd.md) | Maven/Gradle build, test, container push pipeline | İrəli |
| [023](023-graalvm-native.md) | AOT compilation, Spring Boot 3 native, reflection config | Ekspert |

---

**← Əvvəlki:** [spring/](../spring/) — Spring Framework & Boot (88 mövzu)

*24 fayl | Son yenilənmə: 2026-04-24*
