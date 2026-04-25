# Design Patterns

Design pattern-lər təkrar-təkrar qarşılaşılan proqramlaşdırma problemlərinə sınaqdan keçmiş həll yollarıdır. Bu bölmə GoF (Gang of Four) klassik pattern-lərini, Laravel-spesifik pattern-ləri və arxitektura pattern-lərini 5+ il təcrübəli PHP/Laravel developer-ı üçün əhatə edir.

Pattern öyrənmək = pattern-ləri hər yerdə görmək deyil. Məqsəd: **hansı problemi hansı pattern həll edir** bilmək və lazım olduqda düzgün tətbiq etmək.

---

## Creational Patterns (Obyekt Yaradılması)

| Fayl | Pattern | Səviyyə |
|------|---------|---------|
| [01-singleton.md](01-singleton.md) | Singleton | Junior ⭐ |
| [02-facade.md](02-facade.md) | Facade | Junior ⭐ |
| [03-factory-method.md](03-factory-method.md) | Factory Method | Junior ⭐ |
| [04-abstract-factory.md](04-abstract-factory.md) | Abstract Factory | Middle ⭐⭐ |
| [05-builder.md](05-builder.md) | Builder | Middle ⭐⭐ |
| [06-prototype.md](06-prototype.md) | Prototype | Middle ⭐⭐ |

---

## Structural Patterns (Struktur)

| Fayl | Pattern | Səviyyə |
|------|---------|---------|
| [07-adapter.md](07-adapter.md) | Adapter | Middle ⭐⭐ |
| [08-decorator.md](08-decorator.md) | Decorator | Middle ⭐⭐ |
| [17-proxy.md](17-proxy.md) | Proxy | Senior ⭐⭐⭐ |
| [18-composite.md](18-composite.md) | Composite | Senior ⭐⭐⭐ |
| [24-bridge.md](24-bridge.md) | Bridge | Senior ⭐⭐⭐ |
| [28-flyweight.md](28-flyweight.md) | Flyweight | Lead ⭐⭐⭐⭐ |

---

## Behavioral Patterns (Davranış)

| Fayl | Pattern | Səviyyə |
|------|---------|---------|
| [09-observer.md](09-observer.md) | Observer | Middle ⭐⭐ |
| [10-strategy.md](10-strategy.md) | Strategy | Middle ⭐⭐ |
| [11-command.md](11-command.md) | Command | Middle ⭐⭐ |
| [12-template-method.md](12-template-method.md) | Template Method | Middle ⭐⭐ |
| [13-iterator.md](13-iterator.md) | Iterator | Middle ⭐⭐ |
| [19-chain-of-responsibility.md](19-chain-of-responsibility.md) | Chain of Responsibility | Senior ⭐⭐⭐ |
| [20-state.md](20-state.md) | State | Senior ⭐⭐⭐ |
| [23-mediator.md](23-mediator.md) | Mediator | Senior ⭐⭐⭐ |
| [27-visitor.md](27-visitor.md) | Visitor | Lead ⭐⭐⭐⭐ |

---

## Laravel-Specific Patterns

| Fayl | Pattern | Səviyyə |
|------|---------|---------|
| [14-repository-pattern.md](14-repository-pattern.md) | Repository Pattern | Middle ⭐⭐ |
| [15-service-layer.md](15-service-layer.md) | Service Layer | Middle ⭐⭐ |
| [16-event-listener.md](16-event-listener.md) | Event-Listener Pattern | Middle ⭐⭐ |
| [21-pipeline.md](21-pipeline.md) | Pipeline | Senior ⭐⭐⭐ |
| [22-specification.md](22-specification.md) | Specification | Senior ⭐⭐⭐ |

---

## Architectural Patterns

| Fayl | Pattern | Səviyyə |
|------|---------|---------|
| [25-cqrs.md](25-cqrs.md) | CQRS | Lead ⭐⭐⭐⭐ |
| [26-ddd-patterns.md](26-ddd-patterns.md) | DDD Tactical Patterns | Lead ⭐⭐⭐⭐ |
| [29-event-sourcing.md](29-event-sourcing.md) | Event Sourcing | Architect ⭐⭐⭐⭐⭐ |
| [30-hexagonal-architecture.md](30-hexagonal-architecture.md) | Hexagonal Architecture | Architect ⭐⭐⭐⭐⭐ |

---

## Reading Paths

### Path 1: Laravel Developer (Junior → Senior)
Aktiv Laravel developer üçün ən vacib pattern-lər:

`01` → `02` → `03` → `05` → `07` → `08` → `09` → `10` → `11` → `14` → `15` → `16`

Singleton → Facade → Factory Method → Builder → Adapter → Decorator → Observer → Strategy → Command → Repository → Service Layer → Event-Listener

### Path 2: GoF Complete (Middle → Lead)
GoF 23 klassik pattern-i tam öyrənmək istəyənlər üçün:

`03` → `04` → `05` → `06` → `07` → `08` → `09` → `10` → `11` → `12` → `13` → `17` → `18` → `19` → `20` → `23` → `24` → `27`

### Path 3: Architecture (Senior → Architect)
Böyük sistemlər dizayn edənlər üçün:

`14` → `15` → `21` → `22` → `25` → `26` → `29` → `30`

Repository → Service Layer → Pipeline → Specification → CQRS → DDD → Event Sourcing → Hexagonal Architecture
