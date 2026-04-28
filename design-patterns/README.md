# Design Patterns & Architecture

Bu baza 8 kateqoriyada strukturlaşdırılmış design pattern materiallarından ibarətdir.

Pattern öyrənmək = pattern-ləri hər yerdə görmək deyil. Məqsəd: **hansı problemi hansı pattern həll edir** bilmək və lazım olduqda düzgün tətbiq etmək.

---

## Kateqoriyalar

| Folder | Mövzu | Fayl sayı | Səviyyə aralığı |
|--------|-------|-----------|-----------------|
| [creational/](creational/) | Yaradıcı Pattern-lər (GoF) | 6 | Junior–Middle |
| [structural/](structural/) | Struktur Pattern-lər (GoF) | 7 | Middle–Senior |
| [behavioral/](behavioral/) | Davranış Pattern-lər (GoF) | 11 | Middle–Senior |
| [laravel/](laravel/) | Laravel-specific Pattern-lər | 13 | Junior–Senior |
| [ddd/](ddd/) | Domain-Driven Design | 9 | Senior–Architect |
| [architecture/](architecture/) | Arxitektura Pattern-lər | 12 | Middle–Lead |
| [integration/](integration/) | İnteqrasiya / Distributed Systems | 15 | Senior–Architect |
| [general/](general/) | Ümumi Pattern-lər | 8 | Middle–Lead |
| **Cəmi** | | **81** | |

---

## Oxuma Yolları

### Junior Laravel Developer
creational/01 → creational/02 → structural/01 → behavioral/02 → laravel/01-05

### Middle → Senior keçid
laravel/06-13 → ddd/01-04 → architecture/01-05 → integration/01-04

### DDD Öyrənən
ddd/ (hamısı) → laravel/08-13 → integration/01-05 → architecture/05-09

### System Design / Architect
architecture/ → integration/ → ddd/ → general/

### Distributed Systems Focus
integration/01-10 → integration/13-15 → general/03 → general/08 → ddd/04-06

---

## Level Sistemi

| Level | İşarə | Hədəf |
|-------|-------|-------|
| Junior | ⭐ | İlk addımlar |
| Middle | ⭐⭐ | Real layihə |
| Senior | ⭐⭐⭐ | Mürəkkəb problem |
| Lead | ⭐⭐⭐⭐ | Team / System dizayn |
| Architect | ⭐⭐⭐⭐⭐ | Enterprise miqyas |

---

## Kateqoriya Xülasəsi

### Creational Patterns
GoF-un yaradıcı pattern-ləri — obyekt yaradılmasını idarə edir.
Singleton, Factory Method, Abstract Factory, Builder, Prototype, Object Pool.

### Structural Patterns
GoF-un struktur pattern-ləri — class-ların bir-birinə bağlanma üsulları.
Facade, Adapter, Decorator, Proxy, Composite, Flyweight, Bridge.

### Behavioral Patterns
GoF-un davranış pattern-ləri — obyektlər arasında kommunikasiya.
Observer, Strategy, Command, Template Method, Iterator, Chain of Responsibility, State, Mediator, Visitor, Memento, Null Object.

### Laravel Patterns
Laravel ekosisteminin spesifik pattern-ləri — günlük Laravel development-ə birbaşa tətbiq olunur.
Repository, Service Layer, Pipeline, Specification, Event-Listener, DI vs Service Locator, Policy Handler, Command/Query Bus, State Machine, Action Class, Form Object, Presenter/ViewModel, Lazy vs Eager Loading.

### DDD
Domain-Driven Design taktiki pattern-ləri — mürəkkəb domain modelling üçün.
DDD Overview, Value Objects, DDD Patterns, Aggregates, Domain Events, Bounded Context, Shared Kernel, Domain vs App Service, Aggregate Design Heuristics.

### Architecture
Arxitektura pattern-ləri — sistemi bir bütöv olaraq qurmaq.
Design Patterns Overview, SOLID, GRASP, Layered, Hexagonal, Onion, N-Tier, Modular Monolith, Microservices vs Modular, Data Mapper vs Active Record, MVC/MVP/MVVM, Clean Architecture.

### Integration
Distributed systems və inteqrasiya pattern-ləri — servislərarası kommunikasiya.
CQRS, Event Sourcing, Saga, Outbox, 2PC, Strangler Fig, Bulkhead, Anti-Corruption Layer, BFF, API Composition, Choreography vs Orchestration, EIP, ES+CQRS Combined, Read Model Projection, ES Snapshots.

### General
Cross-cutting mövzular — hər yerdə işlənən, düzgün tətbiq edilmədikcə texniki borca çevrilən.
DTO, Code Smells & Refactoring, Concurrency Patterns, Multi-Tenancy, Technical Debt, ADR, Cache-Aside, Caching Strategies.
