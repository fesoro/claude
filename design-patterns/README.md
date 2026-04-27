# Design Patterns & Architecture

Design pattern-lər təkrar-təkrar qarşılaşılan proqramlaşdırma problemlərinə sınaqdan keçmiş həll yollarıdır. Bu bölmə GoF (Gang of Four) klassik pattern-lərini, DDD taktiki pattern-lərini, arxitektura pattern-lərini və distributed systems pattern-lərini 5+ il təcrübəli PHP/Laravel developer üçün əhatə edir.

Pattern öyrənmək = pattern-ləri hər yerdə görmək deyil. Məqsəd: **hansı problemi hansı pattern həll edir** bilmək və lazım olduqda düzgün tətbiq etmək.

---

## Junior ⭐

Hər PHP developer bilməli olan fundamental anlayışlar və ən çox istifadə olunan pattern-lər.

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [01-singleton.md](01-singleton.md) | Singleton | Creational |
| [02-facade.md](02-facade.md) | Facade | Structural |
| [03-factory-method.md](03-factory-method.md) | Factory Method | Creational |
| [31-solid-principles.md](31-solid-principles.md) | SOLID Principles | Fundamental |
| [32-value-objects.md](32-value-objects.md) | Value Objects | DDD taktiki |
| [33-dto.md](33-dto.md) | DTO (Data Transfer Object) | Structural |
| [34-design-patterns-overview.md](34-design-patterns-overview.md) | Design Patterns Overview | Xəritə |

---

## Middle ⭐⭐

Real layihələrdə aktiv istifadə olunan GoF pattern-lər, Laravel-specific yanaşmalar və ilk arxitektura qərarları.

### Creational & Structural Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [04-abstract-factory.md](04-abstract-factory.md) | Abstract Factory | Creational |
| [05-builder.md](05-builder.md) | Builder | Creational |
| [06-prototype.md](06-prototype.md) | Prototype | Creational |
| [07-adapter.md](07-adapter.md) | Adapter | Structural |
| [08-decorator.md](08-decorator.md) | Decorator | Structural |

### Behavioral Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [09-observer.md](09-observer.md) | Observer | Behavioral |
| [10-strategy.md](10-strategy.md) | Strategy | Behavioral |
| [11-command.md](11-command.md) | Command | Behavioral |
| [12-template-method.md](12-template-method.md) | Template Method | Behavioral |
| [13-iterator.md](13-iterator.md) | Iterator | Behavioral |

### Laravel-Specific Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [14-repository-pattern.md](14-repository-pattern.md) | Repository Pattern | Laravel |
| [15-service-layer.md](15-service-layer.md) | Service Layer | Laravel |
| [16-event-listener.md](16-event-listener.md) | Event-Listener Pattern | Laravel |

### Design Principles & Architecture Basics

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [35-di-vs-service-locator.md](35-di-vs-service-locator.md) | DI vs Service Locator | Principles |
| [36-code-smells-refactoring.md](36-code-smells-refactoring.md) | Code Smells & Refactoring | Quality |
| [37-ddd.md](37-ddd.md) | DDD Introduction | Architecture |
| [38-modular-monolith.md](38-modular-monolith.md) | Modular Monolith | Architecture |
| [39-microservices-vs-modular-monolith.md](39-microservices-vs-modular-monolith.md) | Microservices vs Modular Monolith | Trade-offs |
| [40-layered-architectures.md](40-layered-architectures.md) | Layered Architectures | Architecture |
| [41-onion-architecture.md](41-onion-architecture.md) | Onion Architecture | Architecture |
| [42-n-tier-architecture.md](42-n-tier-architecture.md) | N-Tier Architecture | Architecture |
| [43-choreography-vs-orchestration.md](43-choreography-vs-orchestration.md) | Choreography vs Orchestration | Distributed |
| [44-grasp-principles.md](44-grasp-principles.md) | GRASP Principles | Principles |

---

## Senior ⭐⭐⭐

Kompleks domain modelling, advanced structural pattern-lər, resilience pattern-ləri və distributed systems əsasları.

### Advanced GoF Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [17-proxy.md](17-proxy.md) | Proxy | Structural |
| [18-composite.md](18-composite.md) | Composite | Structural |
| [19-chain-of-responsibility.md](19-chain-of-responsibility.md) | Chain of Responsibility | Behavioral |
| [20-state.md](20-state.md) | State | Behavioral |
| [21-pipeline.md](21-pipeline.md) | Pipeline | Laravel |
| [22-specification.md](22-specification.md) | Specification | Laravel |
| [23-mediator.md](23-mediator.md) | Mediator | Behavioral |
| [24-bridge.md](24-bridge.md) | Bridge | Structural |

### DDD Tactical Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [45-ddd-aggregates.md](45-ddd-aggregates.md) | DDD Aggregates | DDD |
| [46-ddd-domain-events.md](46-ddd-domain-events.md) | DDD Domain Events | DDD |
| [47-ddd-bounded-context.md](47-ddd-bounded-context.md) | DDD Bounded Context | DDD |
| [48-shared-kernel.md](48-shared-kernel.md) | Shared Kernel | DDD |
| [49-domain-service-vs-app-service.md](49-domain-service-vs-app-service.md) | Domain Service vs Application Service | DDD |
| [50-aggregate-design-heuristics.md](50-aggregate-design-heuristics.md) | Aggregate Design Heuristics | DDD |

### Application & Integration Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [51-policy-handler-pattern.md](51-policy-handler-pattern.md) | Policy Handler Pattern | Application |
| [52-command-query-bus.md](52-command-query-bus.md) | Command/Query Bus | Application |
| [53-state-machine-workflow.md](53-state-machine-workflow.md) | State Machine Workflow | Application |
| [54-strangler-fig-pattern.md](54-strangler-fig-pattern.md) | Strangler Fig Pattern | Migration |
| [55-bulkhead-pattern.md](55-bulkhead-pattern.md) | Bulkhead Pattern | Resilience |
| [56-anti-corruption-layer.md](56-anti-corruption-layer.md) | Anti-Corruption Layer | Integration |
| [57-bff-pattern.md](57-bff-pattern.md) | BFF (Backend for Frontend) | API |
| [58-api-composition-pattern.md](58-api-composition-pattern.md) | API Composition Pattern | API |
| [59-saga-pattern.md](59-saga-pattern.md) | Saga Pattern | Distributed |
| [60-outbox-pattern.md](60-outbox-pattern.md) | Outbox Pattern | Distributed |
| [61-multi-tenancy.md](61-multi-tenancy.md) | Multi-Tenancy | Architecture |

---

## Lead ⭐⭐⭐⭐

Distributed transactions, event-driven architecture dərinliyi, advanced GoF pattern-lər və sistem miqyasında qərar qəbuletmə.

### Advanced GoF Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [27-visitor.md](27-visitor.md) | Visitor | Behavioral |
| [28-flyweight.md](28-flyweight.md) | Flyweight | Structural |

### Distributed & Event-Driven Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [62-concurrency-patterns.md](62-concurrency-patterns.md) | Concurrency Patterns | Distributed |
| [63-two-phase-commit.md](63-two-phase-commit.md) | Two-Phase Commit (2PC) | Distributed |
| [64-event-sourcing-cqrs-combined.md](64-event-sourcing-cqrs-combined.md) | Event Sourcing + CQRS Combined | Event-Driven |
| [65-cqrs-read-model-projection.md](65-cqrs-read-model-projection.md) | CQRS Read Model Projection | Event-Driven |
| [66-eip-patterns.md](66-eip-patterns.md) | EIP (Enterprise Integration Patterns) | Integration |
| [67-event-sourcing-snapshots.md](67-event-sourcing-snapshots.md) | Event Sourcing Snapshots | Event-Driven |

---

## Architect ⭐⭐⭐⭐⭐

Sistem arxitekturasını formalaşdıran, uzunmüddətli texniki qərarlar verən və bütün sistemin keyfiyyətinə cavabdeh olan rol üçün.

### Foundational Architectural Patterns

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [25-cqrs.md](25-cqrs.md) | CQRS | Architecture |
| [26-ddd-patterns.md](26-ddd-patterns.md) | DDD Tactical Patterns (Overview) | Architecture |
| [29-event-sourcing.md](29-event-sourcing.md) | Event Sourcing | Architecture |
| [30-hexagonal-architecture.md](30-hexagonal-architecture.md) | Hexagonal Architecture | Architecture |

### Engineering Leadership

| Fayl | Mövzu | Qeyd |
|------|-------|------|
| [68-technical-debt.md](68-technical-debt.md) | Technical Debt Management | Leadership |
| [69-architecture-decision-records.md](69-architecture-decision-records.md) | Architecture Decision Records (ADR) | Leadership |

---

## Reading Paths

### Path 1: Laravel Developer (Junior → Senior)
Aktiv Laravel developer üçün ən vacib pattern-lər — praktik dəyəri yüksək olanlar:

```
01 → 02 → 03 → 31 → 33 → 05 → 07 → 08 → 09 → 10 → 11 → 14 → 15 → 16 → 35 → 36
```

Singleton → Facade → Factory Method → SOLID → DTO → Builder → Adapter → Decorator → Observer → Strategy → Command → Repository → Service Layer → Event-Listener → DI vs Service Locator → Code Smells

---

### Path 2: GoF Complete (Middle → Lead)
Gang of Four 23 klassik pattern-i tam öyrənmək istəyənlər üçün:

```
03 → 04 → 05 → 06 → 07 → 08 → 17 → 18 → 24 → 28 → 09 → 10 → 11 → 12 → 13 → 19 → 20 → 23 → 27
```

Factory Method → Abstract Factory → Builder → Prototype → Adapter → Decorator → Proxy → Composite → Bridge → Flyweight → Observer → Strategy → Command → Template Method → Iterator → Chain of Responsibility → State → Mediator → Visitor

---

### Path 3: DDD Path (Middle → Senior)
Domain-Driven Design öyrənmək istəyənlər üçün — tactical pattern-dən strategic-ə:

```
37 → 32 → 33 → 26 → 45 → 46 → 47 → 48 → 49 → 50 → 51 → 44
```

DDD Introduction → Value Objects → DTO → DDD Tactical Overview → Aggregates → Domain Events → Bounded Context → Shared Kernel → Domain vs App Service → Aggregate Heuristics → Policy Handler → GRASP Principles

---

### Path 4: Architecture Patterns (Senior → Architect)
Böyük sistem arxitekturası dizayn edənlər üçün:

```
38 → 39 → 40 → 41 → 42 → 30 → 25 → 52 → 53 → 54 → 56 → 29 → 64 → 65 → 67 → 69
```

Modular Monolith → Microservices vs Modular Monolith → Layered → Onion → N-Tier → Hexagonal → CQRS → Command/Query Bus → State Machine → Strangler Fig → Anti-Corruption Layer → Event Sourcing → ES+CQRS Combined → Read Model Projection → Snapshots → ADR

---

### Path 5: Distributed Patterns (Senior → Lead)
Microservices və distributed systems üçün kritik pattern-lər:

```
43 → 55 → 57 → 58 → 59 → 60 → 61 → 62 → 63 → 66
```

Choreography vs Orchestration → Bulkhead → BFF → API Composition → Saga → Outbox → Multi-Tenancy → Concurrency Patterns → 2PC → EIP Patterns
