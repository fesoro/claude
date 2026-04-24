# Java & Spring Öyrənmə Folderi

Bu folder Java dili, Spring Framework və distributed sistemlər üçün lazım olan bütün mövzuları əhatə edir. Hər alt qovluq fərqli bir məqsədə xidmət edir.

## Struktur

```
java/
├── core/        — Java dili, JVM, Collections, Concurrency (95 fayl)
├── spring/      — Spring Boot, MVC, Data/JPA, Security, Testing (88 fayl)
├── advanced/    — Spring Cloud, Architecture, Deployment (24 fayl)
└── comparison/  — Java/Spring vs PHP/Laravel müqayisəsi (133 fayl)
```

**Cəmi: 340 fayl** — sıfırdan expert səviyyəyə qədər.

---

## Hansı folderdən başlamalıyam?

| Sən kimsən? | Başlanğıc folder | Sonra |
|-------------|------------------|-------|
| **Java-nı heç bilmirəm** | [core/](core/) 000-028 | core/ davam → spring/ |
| **Laravel developer-əm, Java-ya keçirəm** | [comparison/](comparison/) — hər mövzunu Laravel-lə müqayisə edir | comparison/ → spring/ |
| **Java bilirəm, Spring yenisiyəm** | [spring/](spring/) 000-019 | spring/ davam → advanced/ |
| **Spring bilirəm, mikroservisə keçirəm** | [advanced/](advanced/) | — |
| **Senior interview-a hazırlaşıram** | hər üçünün Advanced/Expert bölmələri | — |

---

## Seviyyə göstəriciləri

Bütün alt qovluqlarda mövzular sadədən mürəkkəbə sıralanıb. İki fərqli işarələmə sistemi var:

**core/, spring/, advanced/** istifadə edir: *Başlanğıc / Orta / İrəli / Ekspert* (cədvəldə sütun kimi)

**comparison/** istifadə edir: ⭐ / ⭐⭐ / ⭐⭐⭐ / ⭐⭐⭐⭐ (hər fayl başında `> **Seviyye:** X ⭐` kimi)

| Göstərici | Kimə uyğundur |
|-----------|---------------|
| ⭐ Beginner / Başlanğıc | Java/Spring ilk dəfə görənlər |
| ⭐⭐ Intermediate / Orta | Junior/Mid — gündəlik iş mövzuları |
| ⭐⭐⭐ Advanced / İrəli | Senior backend — internals, observability |
| ⭐⭐⭐⭐ Expert / Ekspert | Staff/Principal — JVM, distributed, reactive |

---

## [core/](core/) — Java Dili və JVM (95 fayl)

Java syntax, OOP, Collections, Streams, Generics, Concurrency, JVM, Design Patterns, Testing əsasları.

**Faza xülasəsi:**

1. Setup & Basics (000-003) — quraşdırma, IntelliJ, naming
2. Core Syntax (004-012) — variables, operators, control flow
3. OOP (013-027) — classes, inheritance, records
4. Essentials (028-043) — Collections, DateTime, modern syntax
5. Functional & Streams (044-051) — lambdas, Optional, Streams
6. Generics (052-055) — type parameters, wildcards
7. I/O & Tooling (056-060) — Maven, Gradle, debugging
8. Concurrency (061-070) — threads, CompletableFuture, virtual threads
9. JVM & Memory (071-078) — GC, JIT, profiling
10. Advanced Features (079-086) — reflection, sealed, pattern matching, JPMS
11. Design Patterns (087-089) — GoF
12. Testing Basics (090-094) — JUnit5, AssertJ, Mockito

Detallı cədvəl: [core/README.md](core/README.md)

---

## [spring/](spring/) — Spring Boot & Framework (88 fayl)

Spring Boot onboarding, IoC container, MVC/REST, Spring Data JPA, Security, AOP, messaging, Spring Testing.

**Faza xülasəsi:**

1. Boot Onboarding (000-008) — Initializr, starter, autoconfig, actuator
2. Core Container (009-019) — IoC, DI, beans, scopes, events
3. MVC/REST (020-030) — controllers, exception handling, validation
4. Data & JPA (031-046) — entity, repositories, JPQL, transactions, Flyway, HikariCP
5. AOP (047-051) — pointcuts, advices, proxy types
6. Security (052-062) — auth, JWT, OAuth2, CORS, session
7. Integration & Messaging (063-083) — cache, Redis, Mongo, Kafka, RabbitMQ, WebSocket, Batch, GraphQL, AI, WebFlux
8. Spring Testing (084-087) — @SpringBootTest, WebMvcTest, DataJpaTest, Testcontainers

**Şərt:** [core/](core/) qovluğundakı Java fundamentals (OOP, Collections, Generics, Lambdas, Maven/Gradle).

Detallı cədvəl: [spring/README.md](spring/README.md)

---

## [advanced/](advanced/) — Cloud, Architecture, Deployment (24 fayl)

Mikroservis ekosistemi, distributed architecture pattern-ləri, production deployment. İrəli → Ekspert səviyyə.

**Faza xülasəsi:**

1. Spring Cloud (000-007) — Gateway, Eureka, Config, OpenFeign, Resilience4j, Sleuth, Prometheus
2. Architecture Patterns (008-019) — SOLID, Clean, Hexagonal, DDD, CQRS, ES, Saga, Outbox, gRPC
3. Deployment (020-023) — Docker, Kubernetes, GitHub Actions, GraalVM native

**Şərt:** [spring/](spring/) tam, xüsusən Security, Data, Messaging.

Detallı cədvəl: [advanced/README.md](advanced/README.md)

---

## [comparison/](comparison/) — Java/Spring vs PHP/Laravel (133 fayl) — **YENİ**

Hər mövzunu həm Java/Spring, həm də PHP/Laravel tərəfindən göstərir. Laravel bildiyin mövzular üzərindən Spring-ə körpü qurur.

- **Languages** (45 fayl) — dil səviyyəsi müqayisəsi (Java vs PHP)
  - ⭐ Beginner (01–14): syntax, OOP əsasları
  - ⭐⭐ Intermediate (15–30): collections, streams, modern Java
  - ⭐⭐⭐ Advanced (31–40): sealed, reflection, concurrency, async
  - ⭐⭐⭐⭐ Expert (41–45): JVM internals, JPMS, FFM
- **Frameworks** (88 fayl) — framework səviyyəsi müqayisəsi (Spring vs Laravel)
  - ⭐ Beginner (01–20): Spring Boot Hello World → REST API → validation
  - ⭐⭐ Intermediate (21–48): JPA, transactions, security, testing, queue
  - ⭐⭐⭐ Advanced (49–72): AOP, internals, resilience, observability
  - ⭐⭐⭐⭐ Expert (73–88): microservices, Cloud, reactive, Kafka, native

Detallı cədvəl: [comparison/README.md](comparison/README.md)

---

## Tövsiyə olunan yollar

### A. Laravel → Spring keçidi (ən populyar yol)

1. `comparison/languages/` ⭐ 01–14 (Java syntax OOP) — 2 həftə
2. `comparison/frameworks/` ⭐ 01–20 (Spring Boot-a giriş) — 2 həftə
3. `comparison/languages/` ⭐⭐ 15–30 (collections, streams) — 3 həftə
4. `comparison/frameworks/` ⭐⭐ 21–48 (JPA, security, queue) — 4 həftə
5. `spring/` 063-083 (integration mövzuları) və `advanced/` — lazım olduqda

### B. Java-nı sıfırdan (Laravel bilmirəm)

1. `core/` 000-043 — syntax, OOP, Collections (3-4 həftə)
2. `core/` 044-060 — Streams, Generics, Maven (2 həftə)
3. `spring/` 000-046 — Boot, IoC, MVC, JPA (4-5 həftə)
4. `core/` 061-089 — Concurrency, JVM, Patterns (paralel oxu)
5. `spring/` 047-087 və `advanced/` — senior mövzuları

### C. Senior interview prep

1. `core/` 071-078 (JVM, GC, JIT)
2. `core/` 061-070 (Concurrency dərin)
3. `spring/` 047-051 (AOP), 031-046 (Data/JPA transactions)
4. `advanced/` tam
5. `comparison/frameworks/` ⭐⭐⭐⭐ 73-88 (reactive, Cloud, Kafka)

---

## Qeydlər

- Fayl nömrələri: `core/spring/advanced` 000-dan başlayır; `comparison` 01-dən başlayır — bu fərq qəsdəndir, hər folder öz daxili ardıcıllığına uyğundur.
- Hər fayl müstəqil oxuna bilər; amma nömrə ardıcıllığı optimum öyrənmə yoludur.
