# Integration Patterns

Distributed sistemlər, microservice-lər arası inteqrasiya və resilience pattern-lərini əhatə edir. Real production layihələrindəki əksər texniki problemlər bu pattern-lərin birinin ya da kombinasiyasının düzgün tətbiq edilməməsindən qaynaqlanır.

---

## Fayllar

| Fayl | Pattern | Səviyyə | Qısa izah |
|------|---------|---------|-----------|
| 01-cqrs.md | CQRS | Lead ⭐⭐⭐⭐ | Write/Read ayrılması; CommandBus, QueryBus, Read Model |
| 02-event-sourcing.md | Event Sourcing | Architect ⭐⭐⭐⭐⭐ | State əvəzinə event-lər; audit trail, time travel |
| 03-saga-pattern.md | Saga | Senior ⭐⭐⭐ | Distributed transaction; compensating transaction |
| 04-outbox-pattern.md | Outbox Pattern | Senior ⭐⭐⭐ | Dual write problemi; reliable event publish |
| 05-two-phase-commit.md | Two-Phase Commit | Lead ⭐⭐⭐⭐ | Distributed atomicity; XA transaction; blocking protocol |
| 06-strangler-fig-pattern.md | Strangler Fig | Senior ⭐⭐⭐ | Legacy miqrasiyası; proxy, feature flag, shadow mode |
| 07-bulkhead-pattern.md | Bulkhead | Senior ⭐⭐⭐ | Resource izolyasiyası; cascading failure önlənməsi |
| 08-anti-corruption-layer.md | ACL + Sidecar + Ambassador | Senior ⭐⭐⭐ | Domain modeli qorunması; xarici sistem translation |
| 09-bff-pattern.md | BFF (Backend for Frontend) | Senior ⭐⭐⭐ | Hər frontend üçün ayrı backend; response shaping |
| 10-api-composition-pattern.md | API Composition | Senior ⭐⭐⭐ | Scatter-gather; parallel service calls; partial failure |
| 11-choreography-vs-orchestration.md | Choreography vs Orchestration | Senior ⭐⭐⭐ | Event-driven vs mərkəzi koordinasiya |
| 12-eip-patterns.md | Enterprise Integration Patterns | Lead ⭐⭐⭐⭐ | Message routing, filtering, splitting, aggregation |
| 13-event-sourcing-cqrs-combined.md | ES + CQRS Combined | Lead ⭐⭐⭐⭐ | İkisinin birlikdə implementasiyası; projection rebuild |
| 14-cqrs-read-model-projection.md | CQRS Read Model & Projection | Lead ⭐⭐⭐⭐ | Denormalized read model; inline vs async projection |
| 15-event-sourcing-snapshots.md | Event Sourcing Snapshots | Lead ⭐⭐⭐⭐ | Performance optimization; snapshot versioning |
| 16-circuit-breaker.md | Circuit Breaker | Senior ⭐⭐⭐ | Fail-fast; Closed/Open/Half-Open; cascading failure |
| 17-retry-pattern.md | Retry Pattern | Middle ⭐⭐ | Exponential backoff + jitter; idempotency; transient failure |
| 18-throttling-rate-limiting.md | Throttling / Rate Limiting | Middle ⭐⭐ | API qorunması; Token Bucket; Redis-based limiter |

---

## Oxuma Yolları

### Distributed Systems Giriş (Middle → Senior)
Microservice dünyasına yeni girənlər üçün əsas pattern-lər:

1. [Retry Pattern](17-retry-pattern.md) — transient failure, exponential backoff
2. [Throttling / Rate Limiting](18-throttling-rate-limiting.md) — API qorunması, Redis limiter
3. [Circuit Breaker](16-circuit-breaker.md) — fail-fast, cascading failure
4. [Bulkhead Pattern](07-bulkhead-pattern.md) — resource izolyasiyası
5. [API Composition Pattern](10-api-composition-pattern.md) — scatter-gather, parallel calls
6. [BFF Pattern](09-bff-pattern.md) — frontend-specific backend
7. [Outbox Pattern](04-outbox-pattern.md) — dual write, reliable event publish
8. [Saga Pattern](03-saga-pattern.md) — distributed transaction, compensation

### Event-Driven Systems (Senior → Lead)
Event-driven arxitektura qurmaq istəyənlər üçün:

1. [Outbox Pattern](04-outbox-pattern.md) — reliable event publish
2. [Choreography vs Orchestration](11-choreography-vs-orchestration.md) — event koordinasiyası
3. [CQRS](01-cqrs.md) — command/query ayrılması
4. [CQRS Read Model & Projection](14-cqrs-read-model-projection.md) — denormalized read model
5. [Event Sourcing](02-event-sourcing.md) — event-lərdən state
6. [Event Sourcing Snapshots](15-event-sourcing-snapshots.md) — performance optimization
7. [Event Sourcing + CQRS Combined](13-event-sourcing-cqrs-combined.md) — tam implementasiya
8. [Enterprise Integration Patterns](12-eip-patterns.md) — message routing, EIP

### Resilience Patterns (Senior)
Production sistemlərinin dayanıqlığı üçün:

1. [Retry Pattern](17-retry-pattern.md) — transient failure handling
2. [Circuit Breaker](16-circuit-breaker.md) — fail-fast, system protection
3. [Bulkhead Pattern](07-bulkhead-pattern.md) — resource isolation
4. [Throttling / Rate Limiting](18-throttling-rate-limiting.md) — overload prevention
5. [Outbox Pattern](04-outbox-pattern.md) — message delivery guarantee
6. [Saga Pattern](03-saga-pattern.md) — distributed transaction resilience

### Legacy Modernization (Senior → Lead)
Köhnə sistemi yeni arxitekturaya miqrasiya:

1. [Strangler Fig Pattern](06-strangler-fig-pattern.md) — inkremental miqrasiya
2. [Anti-Corruption Layer](08-anti-corruption-layer.md) — domain model qorunması
3. [BFF Pattern](09-bff-pattern.md) — frontend-specific layer
4. [Two-Phase Commit](05-two-phase-commit.md) — 2PC vs Saga müqayisəsi
5. [Saga Pattern](03-saga-pattern.md) — mikroservis distributed transaction
