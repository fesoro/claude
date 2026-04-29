# 06 — Design Patterns (Interview Hazırlığı)

Bu bölmə backend developer interview-larında ən çox soruşulan design pattern mövzularını əhatə edir. Middle-dan Lead səviyyəsinə qədər 16 mövzu, real interview sualları, güclü cavab nümunələri və production kod nümunələri. Hər pattern üçün "nə vaxt istifadə etmək, nə vaxt etməmək" trade-off-ları ayrıca izah olunub.

---

## Mövzular

### Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-solid-principles.md](01-solid-principles.md) | SOLID Principles |
| 02 | [02-factory-patterns.md](02-factory-patterns.md) | Factory Method and Abstract Factory |
| 03 | [03-singleton-pattern.md](03-singleton-pattern.md) | Singleton Pattern (and its problems) |
| 04 | [04-observer-event.md](04-observer-event.md) | Observer / Event Pattern |
| 10 | [10-builder-pattern.md](10-builder-pattern.md) | Builder Pattern |

### Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 05 | [05-strategy-pattern.md](05-strategy-pattern.md) | Strategy Pattern |
| 06 | [06-command-pattern.md](06-command-pattern.md) | Command Pattern |
| 07 | [07-repository-pattern.md](07-repository-pattern.md) | Repository Pattern |
| 08 | [08-decorator-pattern.md](08-decorator-pattern.md) | Decorator Pattern |
| 09 | [09-adapter-facade.md](09-adapter-facade.md) | Adapter and Facade Patterns |
| 11 | [11-dependency-injection.md](11-dependency-injection.md) | Dependency Injection |
| 12 | [12-template-method.md](12-template-method.md) | Template Method Pattern |

### Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 13 | [13-proxy-pattern.md](13-proxy-pattern.md) | Proxy Pattern |
| 14 | [14-chain-of-responsibility.md](14-chain-of-responsibility.md) | Chain of Responsibility |
| 15 | [15-specification-pattern.md](15-specification-pattern.md) | Specification Pattern |
| 16 | [16-state-pattern.md](16-state-pattern.md) | State Pattern |

---

## Reading Paths

### Creational Patterns (Object yaradılması)

Object yaradılmasını encapsulate edən, flexible və reusable pattern-lər:

1. [03-singleton-pattern.md](03-singleton-pattern.md) — Singleton: faydaları və anti-pattern tərəfləri
2. [02-factory-patterns.md](02-factory-patterns.md) — Factory Method vs Abstract Factory
3. [10-builder-pattern.md](10-builder-pattern.md) — Builder: mürəkkəb object yaradılması, fluent interface
4. [11-dependency-injection.md](11-dependency-injection.md) — DI Container: object graph idarəetməsi

### Structural Patterns (Object birləşdirmə)

Mövcud class-ları yeni funksionallıq üçün birləşdirən pattern-lər:

1. [09-adapter-facade.md](09-adapter-facade.md) — Adapter: interface uyğunlaşdırma. Facade: sadələşdirmə
2. [08-decorator-pattern.md](08-decorator-pattern.md) — Decorator: runtime davranış əlavəsi
3. [13-proxy-pattern.md](13-proxy-pattern.md) — Proxy: access control, lazy loading, caching
4. [07-repository-pattern.md](07-repository-pattern.md) — Repository: data access abstraction

### Behavioral Patterns (Object davranışı)

Object-lər arasında kommunikasiya və məsuliyyət bölgüsü:

1. [01-solid-principles.md](01-solid-principles.md) — SOLID: bütün pattern-lərin nəzəri əsası
2. [04-observer-event.md](04-observer-event.md) — Observer/Event: loose coupling ilə xəbərdarlıq
3. [05-strategy-pattern.md](05-strategy-pattern.md) — Strategy: algorithm family, runtime seçim
4. [06-command-pattern.md](06-command-pattern.md) — Command: action encapsulation, undo/redo, CQRS
5. [12-template-method.md](12-template-method.md) — Template Method: inheritance-based algorithm skeleton
6. [14-chain-of-responsibility.md](14-chain-of-responsibility.md) — CoR: middleware pipeline, request processing
7. [15-specification-pattern.md](15-specification-pattern.md) — Specification: composable business rules
8. [16-state-pattern.md](16-state-pattern.md) — State: object state machine, illegal transition prevention

### PHP/Laravel Developer — Interview Hazırlığı (1 həftə)

Laravel backend developer üçün ən vacib pattern-lər sıra ilə:

1. [01-solid-principles.md](01-solid-principles.md) — Hər cavabın bazası
2. [05-strategy-pattern.md](05-strategy-pattern.md) — Laravel driver sistemi, if-else elimination
3. [07-repository-pattern.md](07-repository-pattern.md) — Data layer abstraction, testability
4. [11-dependency-injection.md](11-dependency-injection.md) — Laravel Service Container dərinliyi
5. [08-decorator-pattern.md](08-decorator-pattern.md) — Middleware, CachedRepository
6. [06-command-pattern.md](06-command-pattern.md) — CQRS, Command Bus, Jobs
7. [04-observer-event.md](04-observer-event.md) — Laravel Events, Listeners

### Senior → Lead Keçidi (2. həftə)

1. [09-adapter-facade.md](09-adapter-facade.md) — Vendor lock-in, Port-and-Adapter
2. [13-proxy-pattern.md](13-proxy-pattern.md) — Lazy loading, N+1, multi-tenant protection
3. [14-chain-of-responsibility.md](14-chain-of-responsibility.md) — Pipeline design, middleware internals
4. [15-specification-pattern.md](15-specification-pattern.md) — DDD, composable business rules
5. [10-builder-pattern.md](10-builder-pattern.md) — Fluent API, Laravel Factory
6. [12-template-method.md](12-template-method.md) — Template Method vs Strategy trade-off
7. [16-state-pattern.md](16-state-pattern.md) — State machine: Order/Payment lifecycle, illegal transition prevention
