# Software Architecture — Folder Structures

Müxtəlif software architecture pattern-ləri üçün folder strukturları.
Hər fayl **Laravel**, **Symfony**, **Spring Boot (Java)** və **Go** üzrə praktik nümunələr təqdim edir.

---

## Səviyyələr üzrə Mövzular

### ⭐ Junior

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-mvc.md](01-mvc.md) | MVC |
| 02 | [02-layered-architecture.md](02-layered-architecture.md) | Layered Architecture |

### ⭐⭐ Middle

| # | Fayl | Mövzu |
|---|------|-------|
| 03 | [03-pipe-and-filter.md](03-pipe-and-filter.md) | Pipe and Filter Architecture |
| 04 | [04-plugin-architecture.md](04-plugin-architecture.md) | Plugin Architecture |
| 05 | [05-modular-monolith.md](05-modular-monolith.md) | Modular Monolith |
| 06 | [06-vertical-slice.md](06-vertical-slice.md) | Vertical Slice Architecture |

### ⭐⭐⭐ Senior

| # | Fayl | Mövzu |
|---|------|-------|
| 07 | [07-clean-architecture.md](07-clean-architecture.md) | Clean Architecture |
| 08 | [08-hexagonal-architecture.md](08-hexagonal-architecture.md) | Hexagonal Architecture (Ports & Adapters) |
| 09 | [09-onion-architecture.md](09-onion-architecture.md) | Onion Architecture |
| 10 | [10-ddd.md](10-ddd.md) | Domain-Driven Design |
| 11 | [11-event-driven.md](11-event-driven.md) | Event-Driven Architecture |
| 12 | [12-strangler-fig.md](12-strangler-fig.md) | Strangler Fig Pattern |
| 13 | [13-anti-corruption-layer.md](13-anti-corruption-layer.md) | Anti-Corruption Layer |
| 14 | [14-bff.md](14-bff.md) | Backend for Frontend (BFF) |

### ⭐⭐⭐⭐ Lead

| # | Fayl | Mövzu |
|---|------|-------|
| 15 | [15-api-gateway.md](15-api-gateway.md) | API Gateway Architecture |
| 16 | [16-cqrs.md](16-cqrs.md) | CQRS |
| 17 | [17-event-sourcing.md](17-event-sourcing.md) | Event Sourcing |
| 18 | [18-soa.md](18-soa.md) | Service-Oriented Architecture |
| 19 | [19-microservices.md](19-microservices.md) | Microservices Architecture |
| 20 | [20-decomposition-strategies.md](20-decomposition-strategies.md) | Decomposition Strategies |
| 21 | [21-distributed-monolith.md](21-distributed-monolith.md) | Distributed Monolith (Anti-Pattern) |
| 22 | [22-choreography-vs-orchestration.md](22-choreography-vs-orchestration.md) | Choreography vs Orchestration |
| 23 | [23-serverless.md](23-serverless.md) | Serverless Architecture |

### ⭐⭐⭐⭐⭐ Architect

| # | Fayl | Mövzu |
|---|------|-------|
| 24 | [24-self-contained-systems.md](24-self-contained-systems.md) | Self-Contained Systems (SCS) |
| 25 | [25-reactive-actor-model.md](25-reactive-actor-model.md) | Reactive Architecture / Actor Model |
| 26 | [26-space-based.md](26-space-based.md) | Space-Based Architecture |
| 27 | [27-cell-based-architecture.md](27-cell-based-architecture.md) | Cell-Based Architecture |
| 28 | [28-lambda-kappa-architecture.md](28-lambda-kappa-architecture.md) | Lambda / Kappa Architecture |
| 29 | [29-big-ball-of-mud.md](29-big-ball-of-mud.md) | Big Ball of Mud (Anti-Pattern) |

---

## Reading Paths

### Laravel developer üçün əsas yol
`01-mvc` → `02-layered` → `05-modular-monolith` → `07-clean` → `08-hexagonal` → `10-ddd` → `16-cqrs`

### Microservices yoluna keçid
`05-modular-monolith` → `11-event-driven` → `12-strangler-fig` → `16-cqrs` → `17-event-sourcing` → `19-microservices` → `20-decomposition-strategies` → `21-distributed-monolith`

### Senior-dən Lead-ə (Domain-centric)
`07-clean` → `08-hexagonal` → `09-onion` → `10-ddd` → `16-cqrs` → `17-event-sourcing`

### Integration & Legacy
`13-anti-corruption-layer` → `12-strangler-fig` → `20-decomposition-strategies`

### Distributed Systems (Lead → Architect)
`19-microservices` → `22-choreography-vs-orchestration` → `15-api-gateway` → `14-bff` → `24-self-contained-systems` → `27-cell-based-architecture`

---

## Decision Guide — Hansını Seçim?

| Ssenari | Arxitektura |
|---------|-------------|
| MVP, kiçik komanda, sürətli çatdırma | `01-mvc`, `02-layered` |
| Böyüyən monolith, team genişlənir | `05-modular-monolith` |
| Feature-centric, test isolation | `06-vertical-slice` |
| Domain mürəkkəbdir, uzun ömürlü sistem | `07-clean`, `08-hexagonal`, `10-ddd` |
| Legacy sistemdən köçüş | `12-strangler-fig`, `13-acl` |
| Çoxlu frontend (mobile, web, partner) | `14-bff`, `15-api-gateway` |
| Read/Write yükü fərqlidir | `16-cqrs` |
| Audit log, undo/redo, event replay | `17-event-sourcing` |
| Müstəqil team-lər, independent deploy | `19-microservices`, `24-scs` |
| Serverless, cloud-native | `23-serverless` |
| 100K+ concurrent, real-time state | `25-reactive-actor`, `26-space-based` |
| Global distribution, blast radius | `27-cell-based` |
| Big data, analytics pipeline | `28-lambda-kappa` |
| Köhnə sistemin vəziyyəti necədir? | `29-big-ball-of-mud` |
