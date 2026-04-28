# Domain-Driven Design (DDD)

DDD mürəkkəb business domain-ləri modelləmək üçün prinsiplər və pattern-lər toplusudur. Eric Evans tərəfindən 2003-cü ildə təqdim edilmiş bu yanaşma kodun strukturunun business domain-i əks etdirməsini tələb edir.

---

## Strategic DDD vs Tactical DDD

**Strategic DDD** — böyük miqyas, sistem/komanda səviyyəsi:
- **Bounded Context** — domain-in müəyyən kontekstdə sərhədlənmiş modeli
- **Ubiquitous Language** — hər BC-nin öz dili; developer + domain ekspert eyni dildə
- **Context Map** — BC-lər arası əlaqə xəritəsi (ACL, Shared Kernel, Customer/Supplier)

**Tactical DDD** — implementasiya səviyyəsi building block-lar:
- **Entity** — unikal ID-si olan domain obyekti; ID-yə görə müqayisə
- **Value Object** — identity-si olmayan, dəyərinə görə müəyyən olunan immutable obyekt
- **Aggregate** — consistency boundary; yalnız root vasitəsilə daxil olunur
- **Domain Event** — domain-də baş vermiş fakt (past tense); aggregate-lərarası loose coupling
- **Repository** — aggregate persistence abstraction-ı
- **Domain Service** — heç bir entity-yə aid olmayan business logic; stateless
- **Application Service** — use case orkestrasiyası; infrastructure koordinasiyası

---

## Fayllar

| Fayl | Mövzu | Səviyyə | Qısa izah |
|------|-------|---------|-----------|
| [01-ddd.md](01-ddd.md) | DDD Overview | Senior ⭐⭐⭐ | Strategic + tactical DDD, layered architecture, ubiquitous language |
| [02-value-objects.md](02-value-objects.md) | Value Objects | Middle ⭐⭐ | Immutable building blocks, equality by value, Eloquent cast |
| [03-ddd-patterns.md](03-ddd-patterns.md) | DDD Tactical Patterns | Lead ⭐⭐⭐⭐ | Entity, VO, Aggregate, Domain Service, Repository tam seti |
| [04-ddd-aggregates.md](04-ddd-aggregates.md) | Aggregates | Senior ⭐⭐⭐ | Consistency boundary, aggregate root, invariant qoruma |
| [05-ddd-domain-events.md](05-ddd-domain-events.md) | Domain Events | Senior ⭐⭐⭐ | Event dispatch, Outbox pattern, integration events |
| [06-ddd-bounded-context.md](06-ddd-bounded-context.md) | Bounded Context | Lead ⭐⭐⭐⭐ | Context Map, ACL, Customer/Supplier, OHS, event-based inteqrasiya |
| [07-shared-kernel.md](07-shared-kernel.md) | Shared Kernel | Lead ⭐⭐⭐⭐ | BC-lər arası paylaşılan kod, riskler, governance |
| [08-domain-service-vs-app-service.md](08-domain-service-vs-app-service.md) | Domain Service vs App Service | Senior ⭐⭐⭐ | Ayrımın qaydaları, anemic domain, orchestration vs logic |
| [09-aggregate-design-heuristics.md](09-aggregate-design-heuristics.md) | Aggregate Design Heuristics | Lead ⭐⭐⭐⭐ | Boundary qərarları, ölçü, contention, eventual consistency |

---

## Oxuma Yolu

### Junior/Middle — Əsas Anlayışlar
1. **[01-ddd.md](01-ddd.md)** — DDD nədir, niyə lazımdır, nə vaxt istifadə edilməz
2. **[02-value-objects.md](02-value-objects.md)** — Primitive obsession-dan qurtulmaq, Money/Email/Address VO

### Senior — Tactical DDD
3. **[04-ddd-aggregates.md](04-ddd-aggregates.md)** — Aggregate, root, invariant qoruma
4. **[05-ddd-domain-events.md](05-ddd-domain-events.md)** — Domain event, post-TX dispatch, Outbox
5. **[08-domain-service-vs-app-service.md](08-domain-service-vs-app-service.md)** — Business logic haradadır?
6. **[03-ddd-patterns.md](03-ddd-patterns.md)** — Bütün tactical pattern-lər bir arada

### Lead/Architect — Strategic DDD
7. **[06-ddd-bounded-context.md](06-ddd-bounded-context.md)** — Context Map, inteqrasiya pattern-ləri
8. **[07-shared-kernel.md](07-shared-kernel.md)** — BC-lər arası paylaşma
9. **[09-aggregate-design-heuristics.md](09-aggregate-design-heuristics.md)** — Aggregate boundary qərarları

---

## Nə Zaman DDD İstifadə Etmək?

### DDD lazımdır:
- Mürəkkəb business rules var (banking, insurance, e-commerce checkout)
- Domain ekspertləri ilə sıx əməkdaşlıq tələb olunur
- Uzunmüddətli layihə, böyüyəcək domain
- Çoxlu komanda, müstəqil domain-lər (BC-lər lazım olur)

### DDD lazım deyil:
- Sadə CRUD (admin panel, settings, CMS) — Eloquent + Form Request kifayətdir
- Prototip, MVP, exploration mərhələsi — tez dəyişəcək domain-i erkən DDD ilə modelləmək israf
- Kiçik team + sadə domain — overhead faydasından çox
- Business logic minimum (yalnız data saxlama + göstərmə)

### Qərar guide:
```
"Sizdə domain ekspertləri varmı?" → Xeyr  → DDD lazım deyil (hələlik)
"Business rule-lar mürəkkəbdirmi?" → Xeyr → CRUD kifayətdir
"Çoxlu komanda müstəqil işləməlidirmi?" → Bəli → BC-lər düşünün
"Uzunmüddətli inkişaf edəcəkmi?" → Bəli → DDD investment-ə dəyər

CRUD üçün DDD = 10x complexity, 0x business value.
Mürəkkəb domain üçün DDD = uzunmüddətli maintainability investment.
```

---

## DDD + Laravel — Praktik Qeydlər

Eloquent Active Record pattern DDD-nin Data Mapper yanaşması ilə tam uyğun gəlmir. Praktik kompromis:

1. **Domain entity-lər** — Eloquent-dən asılı olmayan plain PHP class-lar
2. **Eloquent model-lər** — yalnız persistence üçün "thin model" (data bag)
3. **Repository** — domain entity ↔ Eloquent model arasında çevirir
4. **Custom Cast** — Value Object-ləri Eloquent model-ə inteqrasiya edir
5. **Domain Events** — repository `save()` çağrısından sonra dispatch olunur

Bu yanaşma tam DDD izolyasiyasını vermir (Eloquent hələ işlənir), lakin Laravel ekosisteminin üstünlüklərini saxlayır.

---

## Əlaqəli Qovluqlar

- [laravel/](../laravel/) — Repository, Service Layer, Specification, Event Listener
- [integration/](../integration/) — CQRS, Event Sourcing, Saga, Outbox, ACL
- [architecture/](../architecture/) — Hexagonal, Onion, Clean, Modular Monolith
- [general/](../general/) — DTO, Code Smells, Refactoring
