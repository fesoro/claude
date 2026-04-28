# Laravel Design Patterns

Laravel 10/11 fokuslu, 5+ il təcrübəli PHP developer üçün praktik pattern-lər. Hər mövzu real layihə ssenarisi, trade-off analizi, anti-pattern nümunəsi və test strategiyası ilə əhatə olunub.

---

## Mövzular

| Fayl | Pattern | Səviyyə | Qısa izah |
|------|---------|---------|-----------|
| [01-repository-pattern.md](01-repository-pattern.md) | Repository Pattern | ⭐⭐ Middle | Data access-i business logic-dən ayırmaq; storage abstraction |
| [02-service-layer.md](02-service-layer.md) | Service Layer | ⭐⭐ Middle | Business logic-i controller-dan ayırmaq; thin controller |
| [03-pipeline.md](03-pipeline.md) | Pipeline | ⭐⭐⭐ Senior | Ardıcıl stage-lərdən keçən data/request emal zənciri |
| [04-specification.md](04-specification.md) | Specification | ⭐⭐⭐ Senior | Business rule-ları reusable, kombinasiya olunan object-lərə çıxarmaq |
| [05-event-listener.md](05-event-listener.md) | Event-Listener | ⭐⭐ Middle | Loose coupling; async side effects; broadcasting |
| [06-di-vs-service-locator.md](06-di-vs-service-locator.md) | DI vs Service Locator | ⭐⭐ Middle | Constructor injection vs `app()`; IoC Container |
| [07-policy-handler-pattern.md](07-policy-handler-pattern.md) | Policy / Handler | ⭐⭐⭐ Senior | "When X then Y" domain rule-ları; DDD event-driven axın |
| [08-command-query-bus.md](08-command-query-bus.md) | Command / Query Bus | ⭐⭐⭐ Senior | CQRS; use case-ləri Handler-lərə yönləndirmək |
| [09-state-machine-workflow.md](09-state-machine-workflow.md) | State Machine | ⭐⭐⭐ Senior | Entity state keçidlərini mərkəzləşdirmək; illegal transition prevention |
| [10-action-class.md](10-action-class.md) | Action Class | ⭐ Junior | Single-action controller; bir use case = bir class |
| [11-form-object.md](11-form-object.md) | Form Object | ⭐⭐ Middle | FormRequest + transformation; `toCommand()`, `toData()` |
| [12-presenter-view-model.md](12-presenter-view-model.md) | Presenter / View Model | ⭐⭐ Middle | Domain model-i API/View representation-dan ayırmaq |
| [13-lazy-loading-eager-loading.md](13-lazy-loading-eager-loading.md) | Lazy vs Eager Loading | ⭐⭐ Middle | N+1 problemi, `with()`, `preventLazyLoading()` |
| [14-unit-of-work.md](14-unit-of-work.md) | Unit of Work | ⭐⭐⭐ Senior | Atomik transaction boundary; `DB::transaction()` + `afterCommit` |

---

## Oxuma Yolları

### Junior → Middle (Yeni başlayanlar üçün)

```
10-action-class.md          ← Controller-ı parçalamaq
  ↓
06-di-vs-service-locator.md ← DI anlayışı, IoC Container
  ↓
02-service-layer.md         ← Business logic-i yerləşdirmək
  ↓
11-form-object.md           ← Input transformation
  ↓
12-presenter-view-model.md  ← Output formatting
  ↓
13-lazy-loading-eager-loading.md ← N+1 problem
  ↓
05-event-listener.md        ← Async side effects
  ↓
01-repository-pattern.md    ← Storage abstraction
```

### Middle → Senior (Arxitektura yönümlü)

```
01-repository-pattern.md    ← Əsas
  ↓
02-service-layer.md         ← Business logic
  ↓
03-pipeline.md              ← Complex flow
  ↓
04-specification.md         ← Business rule encapsulation
  ↓
07-policy-handler-pattern.md ← DDD event-driven
  ↓
08-command-query-bus.md     ← CQRS
  ↓
09-state-machine-workflow.md ← State management
  ↓
14-unit-of-work.md          ← Transaction boundary
```

### Praktik Problem → Pattern

| Problem | Baxılan Pattern |
|---------|----------------|
| Fat controller | [10-action-class.md](10-action-class.md), [02-service-layer.md](02-service-layer.md) |
| N+1 query | [13-lazy-loading-eager-loading.md](13-lazy-loading-eager-loading.md) |
| Test etmək çətin | [01-repository-pattern.md](01-repository-pattern.md), [06-di-vs-service-locator.md](06-di-vs-service-locator.md) |
| Partial DB update | [14-unit-of-work.md](14-unit-of-work.md) |
| Illegal state transition | [09-state-machine-workflow.md](09-state-machine-workflow.md) |
| API contract DB-yə bağlı | [12-presenter-view-model.md](12-presenter-view-model.md) |
| Business rule hər yerdə | [04-specification.md](04-specification.md) |
| Service chain | [05-event-listener.md](05-event-listener.md), [07-policy-handler-pattern.md](07-policy-handler-pattern.md) |
| Input complexity | [11-form-object.md](11-form-object.md) |

---

## Əlaqəli Qovluqlar

- [`../behavioral/`](../behavioral/) — Observer, Strategy, Command, State, Mediator, Chain of Responsibility
- [`../structural/`](../structural/) — Facade, Decorator, Proxy, Adapter
- [`../creational/`](../creational/) — Singleton, Factory Method, Object Pool
- [`../ddd/`](../ddd/) — DDD, Value Objects, Aggregates, Domain Events, Bounded Context
- [`../integration/`](../integration/) — CQRS, Event Sourcing, Outbox Pattern
- [`../architecture/`](../architecture/) — SOLID, Hexagonal, Layered Architectures
- [`../general/`](../general/) — DTO, Code Smells & Refactoring
