# Architectural Patterns (Arxitektura Pattern-lər)

Arxitektura pattern-lər tətbiqin ümumi strukturunu, qatlar arasındakı asılılıqları və komponentlər arasındakı kommunikasiya modelini müəyyən edir. Design pattern-lərdən fərqli olaraq, arxitektura pattern-lər tək bir class deyil, bütün sistem haqqında qərar verir: kod harada yaşayır, kim kimə asılıdır, domain logic harada saxlanır.

Bu folder PHP/Laravel developer-lər üçün nəzərdə tutulub. Hər mövzu real layihə nümunələri, folder strukturları və anti-pattern-lərlə izah olunub.

---

## Fayllar

| # | Fayl | Mövzu | Səviyyə |
|---|------|--------|---------|
| 01 | [01-design-patterns-overview.md](01-design-patterns-overview.md) | Design Patterns Ümumi Baxış | ⭐ Junior |
| 02 | [02-solid-principles.md](02-solid-principles.md) | SOLID Prinsipləri | ⭐ Junior |
| 03 | [03-grasp-principles.md](03-grasp-principles.md) | GRASP Prinsipləri | ⭐⭐⭐ Senior |
| 04 | [04-layered-architectures.md](04-layered-architectures.md) | Layered Architecture (müqayisəli baxış) | ⭐⭐⭐ Senior |
| 05 | [05-hexagonal-architecture.md](05-hexagonal-architecture.md) | Hexagonal Architecture / Ports & Adapters | ⭐⭐⭐⭐ Lead |
| 06 | [06-onion-architecture.md](06-onion-architecture.md) | Onion Architecture | ⭐⭐⭐ Senior |
| 07 | [07-n-tier-architecture.md](07-n-tier-architecture.md) | N-Tier Architecture | ⭐⭐⭐ Senior |
| 08 | [08-modular-monolith.md](08-modular-monolith.md) | Modular Monolith | ⭐⭐⭐ Senior |
| 09 | [09-microservices-vs-modular-monolith.md](09-microservices-vs-modular-monolith.md) | Microservices vs Modular Monolith | ⭐⭐⭐⭐ Lead |
| 10 | [10-data-mapper-vs-active-record.md](10-data-mapper-vs-active-record.md) | Data Mapper vs Active Record | ⭐⭐⭐ Senior |
| 11 | [11-mvc-mvp-mvvm.md](11-mvc-mvp-mvvm.md) | MVC, MVP, MVVM | ⭐⭐ Middle |
| 12 | [12-clean-architecture.md](12-clean-architecture.md) | Clean Architecture (Uncle Bob) | ⭐⭐⭐⭐ Lead |

---

## Reading Paths

### Junior-dan Başlayan

Əsasları möhkəm qurmaq üçün:

1. **[02-solid-principles.md](02-solid-principles.md)** — SOLID: SRP, OCP, LSP, ISP, DIP — hər PHP developer bilməlidir
2. **[01-design-patterns-overview.md](01-design-patterns-overview.md)** — GoF pattern-lərin Laravel nümunələri ilə icmalı
3. **[11-mvc-mvp-mvvm.md](11-mvc-mvp-mvvm.md)** — MVC/MVVM — Laravel + Livewire-in əsas arxitektura pattern-i
4. **[07-n-tier-architecture.md](07-n-tier-architecture.md)** — Layer vs Tier fərqi, fiziki deployment anlayışı
5. **[04-layered-architectures.md](04-layered-architectures.md)** — Layered → Hexagonal → Clean → Onion müqayisəsi
6. **[03-grasp-principles.md](03-grasp-principles.md)** — Məsuliyyəti doğru yerə vermək

### Senior/Lead üçün

Mürəkkəb arxitektura qərarları üçün:

1. **[10-data-mapper-vs-active-record.md](10-data-mapper-vs-active-record.md)** — Eloquent AR vs Doctrine DM; Hybrid pattern
2. **[06-onion-architecture.md](06-onion-architecture.md)** — Domain isolation, dependency rule, DDD ilə uyğunluq
3. **[05-hexagonal-architecture.md](05-hexagonal-architecture.md)** — Ports & Adapters, framework-dən müstəqil domain
4. **[12-clean-architecture.md](12-clean-architecture.md)** — Uncle Bob-un 4 ring-i; Use Case-lər; Entities; Dependency Rule
5. **[08-modular-monolith.md](08-modular-monolith.md)** — Module boundaries, shared kernel, module coupling
6. **[09-microservices-vs-modular-monolith.md](09-microservices-vs-modular-monolith.md)** — Qərar çərçivəsi, Saga, Conway's Law, Strangler Fig

---

## "Hansını Seç?" Decision Tree

```
Layihə kiçikdir, komanda 1-5 nəfər?
  → Klassik Laravel structure (no ceremony)
  → MVC + Eloquent (11, 01)

Domain mürəkkəbdir, komanda 5-15 nəfər?
  → Modular Monolith (08)
  → Eloquent + Repository Hybrid (10)

Framework-dən müstəqil domain lazımdır?
  → Hexagonal (05) və ya Onion (06)
  → Data Mapper yanaşması (10)

Use Case-lər aydın, testability prioritetdir?
  → Clean Architecture (12)

ORM seçimi: Eloquent vs Doctrine?
  → Sadə CRUD: Eloquent (Active Record) (10)
  → Mürəkkəb domain: Eloquent + Repository Hybrid (10)
  → Domain purity lazımdır: Doctrine (Data Mapper) (10)

UI real-time interaktivdir?
  → Livewire = MVVM (11)
  → Inertia = MVC backend + MVVM frontend (11)

Hər modul fərqli scale tələb edir, team 15+?
  → Microservices (09) — əvvəlcə Modular Monolith-dən başla

Deployment arxitekturası haqqında düşünürsən?
  → N-Tier (07)
```

---

## Əlaqəli Folderlər

- **[../laravel/](../laravel/)** — Repository Pattern, Service Layer, DI — bu arxitekturaların Laravel-spesifik implementasiyaları
- **[../ddd/](../ddd/)** — Domain-Driven Design — Hexagonal/Onion/Clean ilə birlikdə istifadə olunur
- **[../integration/](../integration/)** — CQRS, Event Sourcing, Saga — Microservices ilə lazım olan pattern-lər
- **[../general/](../general/)** — ADR, Technical Debt, Code Smells — arxitektura qərar prosesini dəstəkləyən mövzular
