# Java / Spring Boot Examples — Praktiki Mini Layihələr

Java və Spring Boot konseptlərini real ssenarilər üzərindən göstərən **10 müstəqil, işlək mini layihə**.

## Layihələr

| # | Layihə | Növ | Səviyyə | Əsas Konseptlər |
|---|--------|-----|---------|-----------------|
| 01 | [Hello Spring](./01-hello-spring/) | REST API | ⭐ Junior | `@RestController`, DTO, `ResponseEntity` |
| 02 | [Todo API](./02-todo-api/) | CRUD API | ⭐⭐ Middle | JPA, H2, Validation, Exception handling |
| 03 | [Book Store](./03-book-store/) | Relations API | ⭐⭐ Middle | `@OneToMany`, Pagination, `@Query` |
| 04 | [JWT Auth](./04-jwt-auth/) | Auth Service | ⭐⭐⭐ Senior | Spring Security, JWT, BCrypt |
| 05 | [Blog API](./05-blog-api/) | Full API | ⭐⭐⭐ Senior | Security + JPA + Cache + Pagination |
| 06 | [Order Events](./06-order-events/) | Event-Driven | ⭐⭐⭐ Senior | Spring Events, `@Async`, state machine |
| 07 | [Task Scheduler](./07-task-scheduler/) | Background Jobs | ⭐⭐⭐⭐ Lead | `@Scheduled`, job tracking, metrics |
| 08 | [E-Commerce DDD](./08-ecommerce-ddd/) | DDD + CQRS | ⭐⭐⭐⭐ Lead | Aggregates, Value Objects, domain events |
| 09 | [Microservices Demo](./09-microservices-demo/) | 2 servis + Kafka | ⭐⭐⭐⭐ Lead | Order→Notification, REST + Kafka, Docker Compose |
| 10 | [CQRS + Event Sourcing](./10-cqrs-event-sourcing/) | Bank Account | ⭐⭐⭐⭐⭐ Architect | Event Store, Aggregate, Projection, Replay |

## Tələblər

- Java 21+
- Maven 3.9+
- H2 in-memory DB — **01-08 üçün əlavə quraşdırma lazım deyil**
- Docker — **09 üçün (Kafka + PostgreSQL), 10 üçün (PostgreSQL)**

## Sürətli Başlanğıc

```bash
cd java/examples/02-todo-api
./mvnw spring-boot:run
# → http://localhost:8080
# → H2 Console: http://localhost:8080/h2-console
```

## Tövsiyə Olunan Oxuma Sırası

**Java/Spring-ə yeni başlayanlar:** `01` → `02` → `03`

**PHP/Laravel developer:** `01` → `02` → `04` → `05`

**Senior backend fokus:** `04` → `05` → `06` → `07` → `08`

**DDD / Architecture fokus:** `06` → `07` → `08`

**Microservices fokus:** `06` → `08` → `09`
