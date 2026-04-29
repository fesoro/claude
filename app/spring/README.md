# Ecommerce Spring

**Laravel 13 DDD e-commerce** layihəsinin **Spring Boot 3.4 + Java 21** ekvivalenti.

## Quick Start

```bash
docker-compose up -d
./mvnw spring-boot:run
```

Endpoint: `http://localhost:8080/api/health`

## Sənədləşdirmə

📖 [DOCUMENTATION.md](DOCUMENTATION.md) — Laravel ↔ Spring tam müqayisə (öyrənmə üçün).

## Əsas Texnologiyalar

- **Spring Boot 3.4** + Java 21
- **Spring Modulith** — DDD bounded context isolation
- **Axon Framework** — Event Sourcing + Saga
- **Resilience4j** — Circuit Breaker, Bulkhead, Retry
- **Spring Data JPA** — 4 ayrı datasource (Database-per-Bounded-Context)
- **Flyway** — DB migration
- **Spring Security + JWT** — Sanctum əvəzi
- **RabbitMQ** — Integration events
- **Redis + Redisson** — Cache + Distributed lock
- **Thymeleaf** — Email templates
- **Spring Shell** — Artisan commands

## Arxitektura

5 Bounded Context: **User · Product · Order · Payment · Notification**
+ **Shared Kernel** + Webhook/Search/Health/Admin subdomain-ləri

Hər context-də DDD layered struktur:
```
domain/        ← saf POJO, framework-dən asılı deyil
application/   ← CQRS handler-lər, DTO-lar, saga
infrastructure/← JPA entity, controller, gateway impl
```

## Test

```bash
./mvnw test                                    # bütün testlər (unit + feature)
./mvnw test -Dtest=MoneyValueObjectTest        # tək test
./mvnw test -Dtest="*ApiTest"                  # feature testlər
./mvnw verify                                  # integration test-lər (Testcontainers)
```

## Endpoint Nümunələri

📋 [`../laravel/HTTP_EXAMPLES.md`](../laravel/HTTP_EXAMPLES.md) — bütün 3 stack üçün curl nümunələri (eyni `localhost:8080` portu).

## Şifrlər və mənbələr

Bu layihə Laravel versiyası ilə **eyni biznes məntiq** və **eyni pattern-ləri** istifadə edir. Heç bir komponent sadələşdirilməyib və ya çıxarılmayıb. Məqsəd — **müqayisəli öyrənmə**.
