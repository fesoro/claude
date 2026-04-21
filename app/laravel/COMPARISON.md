# 3 Stack — Eyni DDD E-Commerce Layihəsi

Bu repo-da **eyni biznes sistem** üç fərqli texnologiya stack-ində paralel realizə olunub. Məqsəd — müqayisəli öyrənmə.

| Stack | Yer | Texnologiya | Fayl sayı |
|-------|-----|-------------|-----------|
| **Laravel** (orijinal) | [`app-laravel/`](./) | PHP 8.3 + Laravel 13 + Eloquent | ~400 |
| **Spring Boot** | [`app-spring/`](../app-spring/) | Java 21 + Spring Boot 3.4 + JPA + Hibernate | 268 |
| **Go** | [`app-go/`](../app-go/) | Go 1.23 + Gin + GORM | 124 |

## Hər layihənin sənədi

- **Laravel**: [`app-laravel/DOCUMENTATION.md`](./DOCUMENTATION.md)
- **Spring**: [`app-spring/DOCUMENTATION.md`](../app-spring/DOCUMENTATION.md) — Laravel ↔ Spring müqayisəsi
- **Go**: [`app-go/DOCUMENTATION.md`](../app-go/DOCUMENTATION.md) — Laravel ↔ Spring ↔ Go müqayisəsi

## Endpoint nümunələri (3 stack-də eyni)

[`HTTP_EXAMPLES.md`](./HTTP_EXAMPLES.md) — bütün curl-larla.

## Eyni Funksionallıq, 3 Stack

Hər 3 layihədə var:

✅ 5 Bounded Context (User, Product, Order, Payment, Notification) + Shared Kernel
✅ 4 ayrı DB (Database-per-Bounded-Context)
✅ 43 endpoint (auth, products, orders, payments, webhooks, health, admin)
✅ JWT auth + 2FA TOTP
✅ Forgot/Reset password
✅ CQRS (Command/Query Bus + middleware pipeline)
✅ Outbox Pattern + Inbox + DLQ
✅ Strategy Pattern (PaymentGateway × 3) + Circuit Breaker
✅ ACL (Anti-Corruption Layer)
✅ Saga (OrderSaga — orchestration)
✅ State Machine (OrderStatus, PaymentStatus)
✅ Value Objects (Money, Stock, Email, Password, Address)
✅ Specification Pattern
✅ Aggregate Root + Domain Events + Integration Events
✅ Distributed Lock + Tagged Cache
✅ Webhook with HMAC-SHA256
✅ Audit logging
✅ 7 HTTP middleware (CorrelationID, Idempotency, Tenant, Audit, ApiVersion, FeatureFlag, ForceJSON)
✅ 5 CLI command (Artisan / Spring Shell / cobra)
✅ Email templates (Blade / Thymeleaf / text/template)
✅ Rate limiting
✅ Health check (Kubernetes-compatible)
✅ Docker Compose (eyni 6 servis)
✅ Unit + Integration + Feature tests

## Eyni Komandalar (Makefile)

Hər 3 layihədə eyni `make` interfeysi:
```bash
make up        # docker servisləri qaldır
make run       # tətbiqi işə sal
make migrate   # DB migration
make seed      # test data
make test      # bütün testlər
make logs      # log-lar
make down      # dayandır
```

## Texnologiya Mapping (Tam Cədvəl)

| Konsept | Laravel | Spring Boot | Go |
|---------|---------|-------------|-----|
| **Web framework** | Laravel | Spring Web MVC | Gin |
| **ORM** | Eloquent | Spring Data JPA + Hibernate | GORM |
| **Migration** | Laravel Migrations | Flyway | golang-migrate |
| **DI Container** | Service Container (auto) | Spring IoC (auto) | Manual wiring |
| **Validation** | Form Request rules | Bean Validation `@Valid` | validator/v10 struct tags |
| **Auth** | Sanctum | Spring Security + JWT | golang-jwt |
| **2FA** | pragmarx/google2fa | googleauth | pquerna/otp |
| **Cache** | `Cache::tags(...)` | Spring Cache + Redis | go-redis + custom |
| **Distributed Lock** | Redis SETNX | Redisson `RLock` | redsync |
| **Queue** | Laravel Queue + Redis | Spring Rabbit | amqp091-go |
| **CQRS Bus** | custom `SimpleCommandBus` | custom + Axon (opsional) | custom generic Bus |
| **Circuit Breaker** | custom | Resilience4j `@CircuitBreaker` | sony/gobreaker |
| **Rate Limiting** | `RateLimiter::for(...)` | Bucket4j | x/time/rate |
| **JWT** | Sanctum tokens | jjwt | golang-jwt |
| **Mail** | Mail::to()->send() | JavaMailSender + Thymeleaf | wneessen/go-mail |
| **CLI** | Artisan command | Spring Shell `@ShellComponent` | cobra |
| **Scheduler** | Console/Kernel `$schedule` | `@Scheduled` | time.Ticker + goroutine |
| **Logging** | StructuredLogger | SLF4J + Logback + LogstashEncoder | slog (stdlib) |
| **Config** | config/*.php | application.yml + `@ConfigurationProperties` | viper YAML |
| **Test** | PHPUnit | JUnit 5 + Mockito | testify |
| **Integration test** | DatabaseRefresh | Testcontainers | testcontainers-go |
| **HTTP test** | `$this->json(...)` | MockMvc | httptest |

## Performance Müqayisəsi (təxmini, eyni hardware-də)

| Metric | Laravel (PHP-FPM) | Spring (JVM) | Go |
|--------|-------------------|--------------|-----|
| Cold start | ~500ms | ~3-5s | ~50ms |
| RAM (idle) | ~50MB | ~300MB | ~15MB |
| Docker image | ~200MB | ~250MB | ~15MB |
| Concurrency model | Process-per-request (PHP-FPM) | Thread + virtual threads | goroutine (100k+) |
| RPS (single instance, simple endpoint) | ~1k | ~10k | ~50k |

> Bu rəqəmlər təxminidir. Real performans business logic mürəkkəbliyindən və DB-dən asılıdır.

## Hansını Seçmək?

| Sahə | Tövsiyə |
|------|---------|
| **Sürətli prototip, kiçik komanda** | Laravel — convention over configuration, geniş ekosistem |
| **Enterprise, böyük komanda, mürəkkəb biznes** | Spring — type safety, böyük ekosistem, JEP virtual threads |
| **High-throughput API, mikroservis, K8s** | Go — kiçik image, sürətli start, az resurs |
| **CRUD-ağırlıqlı admin panel** | Laravel + Filament |
| **Event-driven, CQRS-heavy** | Spring + Axon, yaxud Go + Watermill |
| **Real-time/WebSocket-ağırlıqlı** | Go (goroutine konkurensi) və ya Node.js |

## Müqayisəli Öyrənmə Yolu

Bu 3 layihəni paralel oxumaq üçün tövsiyə:

### 1. Sadədən başla — Value Object
- Laravel: `app-laravel/src/Product/Domain/ValueObjects/Money.php`
- Spring: `app-spring/src/main/java/az/ecommerce/product/domain/valueobject/Money.java`
- Go: `app-go/internal/product/domain/value_objects.go` (Money struct)

### 2. Aggregate Root
- Laravel: `app-laravel/src/Order/Domain/Entities/Order.php`
- Spring: `app-spring/src/main/java/az/ecommerce/order/domain/Order.java`
- Go: `app-go/internal/order/domain/order.go`

### 3. Command Handler (CQRS)
- Laravel: `app-laravel/src/Order/Application/Commands/CreateOrder/`
- Spring: `app-spring/src/main/java/az/ecommerce/order/application/command/createorder/`
- Go: `app-go/internal/order/application/handlers.go` → `CreateOrderHandler`

### 4. Strategy Pattern (Payment)
- Laravel: `app-laravel/src/Payment/Domain/Strategies/`
- Spring: `app-spring/src/main/java/az/ecommerce/payment/domain/strategy/` + `payment/infrastructure/gateway/`
- Go: `app-go/internal/payment/infrastructure/gateway/gateways.go`

### 5. Saga Pattern
- Laravel: `app-laravel/src/Order/Domain/Sagas/OrderSaga.php`
- Spring: `app-spring/src/main/java/az/ecommerce/order/domain/saga/OrderSaga.java`
- Go: `app-go/internal/order/infrastructure/saga/order_saga.go`

### 6. Composition Root (DI)
- Laravel: `app-laravel/app/Providers/DomainServiceProvider.php`
- Spring: `app-spring/src/main/java/az/ecommerce/EcommerceApplication.java` + `@Configuration` class-lar
- Go: `app-go/internal/server/server.go`

## İşə Salma (3 stack-də)

```bash
# Laravel
cd app-laravel && make up && make migrate && make seed

# Spring (Java 21 lazımdır lokal-da, yaxud tam Docker)
cd app-spring && make up-all

# Go (heç nə lazım deyil — Docker hər şeyi qurur)
cd app-go && make up-all

# Hər biri http://localhost:8080-da işləyir
# Test: HTTP_EXAMPLES.md
```

## Ümumi xülasə

3 stack — eyni DDD prinsipləri, eyni endpoint-lər, eyni cavab formatı. Fərq yalnız sintaksis və idiomatic stildədir. Beləliklə hər birinin güclü və zəif tərəflərini real layihə kontekstində görə bilərsiniz.
