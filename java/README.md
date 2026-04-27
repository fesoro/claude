# Java & Spring Öyrənmə Folderi

Bu folder Java dili, Spring Framework və distributed sistemlər üçün lazım olan bütün mövzuları əhatə edir. Hər alt qovluq fərqli bir məqsədə xidmət edir.

## Struktur

```
java/
├── core/        — Java dili, JVM, Collections, Concurrency (100 fayl)
├── spring/      — Spring Boot, MVC, Data/JPA, Security, Modern Java (108 fayl)
├── advanced/    — Spring Cloud, Architecture, Production Engineering (27 fayl)
├── comparison/  — Java/Spring vs PHP/Laravel müqayisəsi (138 fayl)
└── examples/    — 9 işlək mini layihə: Hello Spring → Microservices Demo
```

**Cəmi: 374 fayl + 9 kod nümunəsi** — sıfırdan expert səviyyəyə qədər.

---

## Hansı folderdən başlamalıyam?

| Sən kimsən? | Başlanğıc folder | Sonra |
|-------------|------------------|-------|
| **Java-nı heç bilmirəm** | [core/](core/) 01-29 | core/ davam → spring/ |
| **Laravel developer-əm, Java-ya keçirəm** | [comparison/](comparison/) — hər mövzunu Laravel-lə müqayisə edir | comparison/ → spring/ |
| **Java bilirəm, Spring yenisiyəm** | [spring/](spring/) 01-20 | spring/ davam → advanced/ |
| **Spring bilirəm, mikroservisə keçirəm** | [advanced/](advanced/) | — |
| **Senior interview-a hazırlaşıram** | hər üçünün Advanced/Expert bölmələri | — |

---

## [examples/](examples/) — İşlək Mini Layihələr (9 layihə)

Nəzəriyyəni real kodda görmək üçün. 01-08 H2 ilə birbaşa çalışır; 09 Docker tələb edir.

| # | Layihə | Səviyyə | Əsas Konseptlər |
|---|--------|---------|-----------------|
| 01 | [Hello Spring](examples/01-hello-spring/) | ⭐ Junior | `@RestController`, DTO, `ResponseEntity` |
| 02 | [Todo API](examples/02-todo-api/) | ⭐⭐ Middle | JPA, H2, Validation, Exception handling |
| 03 | [Book Store](examples/03-book-store/) | ⭐⭐ Middle | `@OneToMany`, Pagination, Flyway |
| 04 | [JWT Auth](examples/04-jwt-auth/) | ⭐⭐⭐ Senior | Spring Security, JWT, BCrypt |
| 05 | [Blog API](examples/05-blog-api/) | ⭐⭐⭐ Senior | Security + JPA + Cache + Pagination |
| 06 | [Order Events](examples/06-order-events/) | ⭐⭐⭐ Senior | Spring Events, `@Async`, state machine |
| 07 | [Task Scheduler](examples/07-task-scheduler/) | ⭐⭐⭐⭐ Lead | `@Scheduled`, job tracking, metrics |
| 08 | [E-Commerce DDD](examples/08-ecommerce-ddd/) | ⭐⭐⭐⭐ Lead | DDD layers, Aggregates, Value Objects |
| 09 | [Microservices Demo](examples/09-microservices-demo/) | ⭐⭐⭐⭐ Lead | 2 servis, REST + Kafka, Docker Compose |

---

## Seviyyə göstəriciləri

Bütün alt qovluqlarda mövzular sadədən mürəkkəbə sıralanıb. Hər fayl başında `> **Seviyye:** X ⭐` formatı istifadə olunur.

| Göstərici | Kimə uyğundur |
|-----------|---------------|
| ⭐ Beginner | Java/Spring ilk dəfə görənlər |
| ⭐⭐ Intermediate | Junior/Mid — gündəlik iş mövzuları |
| ⭐⭐⭐ Advanced | Senior backend — internals, observability |
| ⭐⭐⭐⭐ Expert | Staff/Principal — JVM, distributed, reactive |

---

## [core/](core/) — Java Dili və JVM (100 fayl)

Java syntax, OOP, Collections, Streams, Generics, Concurrency, JVM, Design Patterns, Testing əsasları.

**Faza xülasəsi:**

1. Setup & Basics (01-04) — quraşdırma, IntelliJ, naming
2. Core Syntax (05-13) — variables, operators, control flow
3. OOP (14-28) — classes, inheritance, records
4. Essentials (29-44) — Collections, DateTime, modern syntax
5. Functional & Streams (45-52) — lambdas, Optional, Streams
6. Generics (53-56) — type parameters, wildcards
7. I/O & Tooling (57-61) — Maven, Gradle, debugging
8. Concurrency (62-71) — threads, CompletableFuture, virtual threads
9. JVM & Memory (72-79) — GC, JIT, profiling
10. Advanced Features (80-87) — reflection, sealed, pattern matching, JPMS
11. Design Patterns (88-90) — GoF
12. Testing Basics (91-95) — JUnit5, AssertJ, Mockito
13. Practical Java (96-97) — resource management, Maven multi-module
14. Modern Concurrency & Perf (98-99) — Structured Concurrency, JMH
15. Performance Patterns (100) — Object Pool, Commons Pool2

Detallı cədvəl: [core/README.md](core/README.md)

---

## [spring/](spring/) — Spring Boot & Framework (108 fayl)

Spring Boot onboarding, IoC container, MVC/REST, Spring Data JPA, Security, AOP, messaging, Spring Testing.

**Faza xülasəsi:**

1. Boot Onboarding (01-09) — Initializr, starter, autoconfig, actuator
2. Core Container (10-20) — IoC, DI, beans, scopes, events
3. MVC/REST (21-31) — controllers, exception handling, validation
4. Data & JPA (32-47) — entity, repositories, JPQL, transactions, Flyway, HikariCP
5. AOP (48-52) — pointcuts, advices, proxy types
6. Security (53-63) — auth, JWT, OAuth2, CORS, session
7. Integration & Messaging (64-84) — cache, Redis, Mongo, Kafka, RabbitMQ, WebSocket, Batch, GraphQL, AI, WebFlux
8. Spring Testing (85-89) — @SpringBootTest, WebMvcTest, DataJpaTest, Testcontainers, Security Testing
9. Modern Spring & Java (90-100) — virtual threads, records, sealed, observability, graceful shutdown
10. HTTP Clients (101-103) — RestClient, @HttpExchange, @RestClientTest
11. Production Patterns (104-108) — SSE, Webhook delivery, Background jobs, Fuzz testing, Singleflight

**Şərt:** [core/](core/) qovluğundakı Java fundamentals (OOP, Collections, Generics, Lambdas, Maven/Gradle).

Detallı cədvəl: [spring/README.md](spring/README.md)

---

## [advanced/](advanced/) — Cloud, Architecture, Production Engineering (27 fayl)

Mikroservis ekosistemi, distributed architecture pattern-ləri, production deployment. Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ səviyyə.

**Faza xülasəsi:**

1. Spring Cloud (01-08) — Gateway, Eureka, Config, OpenFeign, Resilience4j, Micrometer Tracing, Prometheus
2. Architecture Patterns (09-20) — SOLID, Clean, Hexagonal, DDD, CQRS, ES, Saga, Outbox, gRPC
3. Deployment (21-24) — Docker, Kubernetes, GitHub Actions, GraalVM native
4. Production Engineering (25-26) — Multi-tenancy, Production Profiling Workflow

**Şərt:** [spring/](spring/) tam, xüsusən Security, Data, Messaging.

Detallı cədvəl: [advanced/README.md](advanced/README.md)

---

## [comparison/](comparison/) — Java/Spring vs PHP/Laravel (139 fayl)

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
5. `spring/` 64-84 (integration mövzuları) və `advanced/` — lazım olduqda

### B. Java-nı sıfırdan (Laravel bilmirəm)

1. `core/` 01-44 — syntax, OOP, Collections (3-4 həftə)
2. `core/` 45-61 — Streams, Generics, Maven (2 həftə)
3. `spring/` 01-47 — Boot, IoC, MVC, JPA (4-5 həftə)
4. `core/` 62-90 — Concurrency, JVM, Patterns (paralel oxu)
5. `spring/` 48-89 və `advanced/` — senior mövzuları

### C. Senior interview prep

1. `core/` 72-79 (JVM, GC, JIT)
2. `core/` 62-71 (Concurrency dərin)
3. `spring/` 48-52 (AOP), 32-47 (Data/JPA transactions)
4. `advanced/` tam
5. `comparison/frameworks/` ⭐⭐⭐⭐ 73-88 (reactive, Cloud, Kafka)

---

## Qeydlər

- Hər fayl müstəqil oxuna bilər; amma nömrə ardıcıllığı optimum öyrənmə yoludur.
