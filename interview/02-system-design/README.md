# System Design Interview — Suallar və Cavablar

Bu bölmə system design interview-larına hazırlıq üçündür. Hər fayl real interview sualını, güclü cavab strukturunu, arxitektura nümunəsini və praktik tapşırıqları əhatə edir. Mövzular sadədən mürəkkəbə (Senior → Lead → Architect) sıralanmışdır.

---

## Mövzular Səviyyəyə Görə

### Senior ⭐⭐⭐ (01–06)

| # | Fayl | Mövzu | Qısa İzah |
|---|------|-------|-----------|
| 01 | [01-system-design-approach.md](01-system-design-approach.md) | System Design Interview Approach | Interview strukturu, sual aydınlaşdırma, scalability, trade-off müzakirəsi |
| 02 | [02-back-of-envelope.md](02-back-of-envelope.md) | Back-of-Envelope Estimation | QPS, storage, bandwidth hesablamaları, order of magnitude düşüncəsi |
| 03 | [03-scalability-fundamentals.md](03-scalability-fundamentals.md) | Scalability Fundamentals | Vertical vs horizontal scaling, stateless design, bottleneck tespiti |
| 04 | [04-load-balancing.md](04-load-balancing.md) | Load Balancing Strategies | Round-robin, least connections, consistent hash, L4 vs L7 LB |
| 05 | [05-caching-strategies.md](05-caching-strategies.md) | Caching Strategies | Cache-aside, write-through, eviction, CDN, thundering herd |
| 06 | [06-database-selection.md](06-database-selection.md) | Database Selection Criteria | SQL vs NoSQL, when to use what, CAP alignment, polyglot persistence |

---

### Lead ⭐⭐⭐⭐ (07–16, 20–22, 25)

| # | Fayl | Mövzu | Qısa İzah |
|---|------|-------|-----------|
| 07 | [07-database-sharding.md](07-database-sharding.md) | Database Sharding | Horizontal partitioning, shard key seçimi, rebalancing, cross-shard query |
| 08 | [08-message-queues.md](08-message-queues.md) | Message Queues | Kafka, RabbitMQ, at-least-once, ordering, consumer groups |
| 09 | [09-rate-limiting.md](09-rate-limiting.md) | Rate Limiting | Token bucket, leaky bucket, sliding window, distributed rate limit |
| 10 | [10-cdn-design.md](10-cdn-design.md) | CDN Design | Edge caching, origin pull, cache invalidation, geo-routing |
| 11 | [11-consistent-hashing.md](11-consistent-hashing.md) | Consistent Hashing | Virtual nodes, minimal rebalancing, hotspot prevention |
| 12 | [12-cap-theorem-practice.md](12-cap-theorem-practice.md) | CAP Theorem in Practice | CP vs AP sistemlər, partition tolerance, PACELC əlavəsi |
| 13 | [13-idempotency-design.md](13-idempotency-design.md) | Idempotency Design | Idempotency key, at-least-once + idempotent = exactly-once effect |
| 14 | [14-api-gateway.md](14-api-gateway.md) | API Gateway Patterns | Auth, rate limit, routing, aggregation, BFF pattern |
| 15 | [15-service-discovery.md](15-service-discovery.md) | Service Discovery | Client-side vs server-side, Consul, Kubernetes DNS, health checks |
| 16 | [16-circuit-breaker.md](16-circuit-breaker.md) | Circuit Breaker Pattern | Closed/open/half-open states, failure threshold, fallback |
| 20 | [20-monitoring-observability.md](20-monitoring-observability.md) | Monitoring and Observability | Metrics, logs, traces (3 pillars), SLO/SLA, alerting |
| 21 | [21-backpressure.md](21-backpressure.md) | Backpressure | Pull vs push, bounded queue, load shedding, Kafka consumer lag |
| 22 | [22-data-partitioning.md](22-data-partitioning.md) | Data Partitioning Strategies | Range/hash/directory partitioning, hotspot, cross-partition query |
| 25 | [25-outbox-pattern.md](25-outbox-pattern.md) | Outbox Pattern | Dual-write problem, transactional outbox, CDC relay, idempotent consumer |

---

### Architect ⭐⭐⭐⭐⭐ (17–19, 23–24)

| # | Fayl | Mövzu | Qısa İzah |
|---|------|-------|-----------|
| 17 | [17-distributed-transactions.md](17-distributed-transactions.md) | Distributed Transactions | 2PC, Saga (choreography/orchestration), TCC, compensating transactions |
| 18 | [18-event-driven-architecture.md](18-event-driven-architecture.md) | Event-Driven Architecture | Event sourcing, CQRS uyumu, event schema evolution, eventual consistency |
| 19 | [19-cqrs-practice.md](19-cqrs-practice.md) | CQRS in Practice | Read/write model ayrılması, projections, sync vs async, complexity trade-off |
| 23 | [23-eventual-consistency.md](23-eventual-consistency.md) | Eventual Consistency | BASE vs ACID, consistency models, conflict resolution, CRDT, anti-entropy |
| 24 | [24-leader-election.md](24-leader-election.md) | Leader Election | Raft, ZooKeeper, etcd lease, split-brain, fencing token, quorum |

---

## Reading Paths

### System Design Interview Sprint (5 gün)
Tez hazırlıq üçün prioritet mövzular — yüksək ehtimallı suallar:

```
Gün 1 — Foundations:
  01 (approach) → 02 (estimation) → 03 (scalability) → 04 (load balancing)

Gün 2 — Data Layer:
  05 (caching) → 06 (DB selection) → 07 (sharding) → 22 (partitioning)

Gün 3 — Reliability & Scale:
  08 (message queues) → 09 (rate limiting) → 13 (idempotency) → 16 (circuit breaker)

Gün 4 — Distributed Systems Core:
  12 (CAP theorem) → 11 (consistent hashing) → 21 (backpressure) → 10 (CDN)

Gün 5 — Architect-level:
  17 (distributed transactions) → 23 (eventual consistency) → 25 (outbox) → 18 (event-driven)
```

---

### Distributed Systems Deep Dive
Distributed systems arxitekturası üzrə dərin öyrənmə:

```
1. CAP Theorem: 12-cap-theorem-practice.md
2. Consistent Hashing: 11-consistent-hashing.md
3. Data Partitioning: 22-data-partitioning.md (+ 07-database-sharding.md)
4. Replication & Lag: 23-eventual-consistency.md
5. Leader Election: 24-leader-election.md
6. Distributed Transactions: 17-distributed-transactions.md
7. Outbox Pattern: 25-outbox-pattern.md
8. Message Queues: 08-message-queues.md
9. Backpressure: 21-backpressure.md
```

---

### Event-Driven Architecture Path
Event-driven sistemlər, Kafka, CQRS, Saga:

```
1. Message Queues: 08-message-queues.md
2. Event-Driven Architecture: 18-event-driven-architecture.md
3. CQRS in Practice: 19-cqrs-practice.md
4. Idempotency Design: 13-idempotency-design.md
5. Outbox Pattern: 25-outbox-pattern.md
6. Distributed Transactions: 17-distributed-transactions.md
7. Eventual Consistency: 23-eventual-consistency.md
8. Backpressure: 21-backpressure.md
```

---

### Reliability & Resilience Path
Production-grade sistemlər, failure handling:

```
1. Circuit Breaker: 16-circuit-breaker.md
2. Rate Limiting: 09-rate-limiting.md
3. Idempotency Design: 13-idempotency-design.md
4. Backpressure: 21-backpressure.md
5. Monitoring & Observability: 20-monitoring-observability.md
6. Outbox Pattern: 25-outbox-pattern.md
7. Leader Election: 24-leader-election.md
```

---

### Data Infrastructure Path
Database design, sharding, partitioning, caching:

```
1. Database Selection: 06-database-selection.md
2. Caching Strategies: 05-caching-strategies.md
3. Database Sharding: 07-database-sharding.md
4. Consistent Hashing: 11-consistent-hashing.md
5. Data Partitioning: 22-data-partitioning.md
6. CAP Theorem: 12-cap-theorem-practice.md
7. Eventual Consistency: 23-eventual-consistency.md
8. CQRS in Practice: 19-cqrs-practice.md
```

---

## Hazırlıq Strategiyası

**Senior (01–06)**: Əsas framework-ları əzbərləyin. Hər sistemin `Functional Requirements → Non-functional Requirements → High-level design → Deep dive → Trade-offs` axını ilə izah edə biləsiniz.

**Lead (07–16, 20–22, 25)**: Hər pattern üçün "niyə?" sualını cavablandırın. Kafka-nı nə zaman istifadə etməli, nə zaman etməməli? Sharding-i nə zaman seçməli? Trade-off-suz cavab "tam cavab" deyil.

**Architect (17–19, 23–24)**: Bu səviyyədə düzgün cavab yoxdur — sistemi justify etmək, alternativləri müzakirə etmək, real şirkətlərin necə həll etdiyini bilmək əsasdır. "Netflix/Amazon/Uber bunu belə həll etdi, biz isə bu trade-off-a görə fərqli seçirik" formatlı cavablar güclüdür.
