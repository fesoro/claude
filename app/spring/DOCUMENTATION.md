# Ecommerce Spring — Laravel ilə Müqayisə Sənədi

Bu layihə `/home/orkhan/Projects/claude/app/` qovluğundakı **Laravel 13 DDD e-commerce** layihəsinin **Spring Boot 3.4 + Java 21** ekvivalentidir. Hər bir komponent 1-1 uyğunluqla yazılıb, kommentlərdə Laravel qarşılığı qeyd olunub.

## İçindəkilər

1. [Layihə Quruluşu](#layihə-quruluşu)
2. [Texnologiya Mapping-i](#texnologiya-mapping-i)
3. [Pattern-lər](#pattern-lər)
4. [Layer Mapping-i](#layer-mapping-i)
5. [Hər Bounded Context](#bounded-context-lər)
6. [Cross-Cutting Concerns](#cross-cutting-concerns)
7. [İşə Salma](#işə-salma)

---

## Layihə Quruluşu

```
spring/
├── pom.xml                             ← Laravel: composer.json
├── docker-compose.yml                  ← eyni 6 servis
├── Dockerfile                          ← JDK 21 multi-stage
├── docker/
│   ├── nginx/default.conf
│   ├── mysql/init.sql                  ← 4 DB yaradır
│   └── scripts/entrypoint.sh
├── src/main/
│   ├── java/az/ecommerce/
│   │   ├── EcommerceApplication.java   ← bootstrap/app.php
│   │   ├── shared/                     ← src/Shared/ (Shared Kernel)
│   │   ├── user/                       ← src/User/
│   │   ├── product/                    ← src/Product/
│   │   ├── order/                      ← src/Order/
│   │   ├── payment/                    ← src/Payment/
│   │   ├── notification/               ← src/Notification/
│   │   ├── webhook/                    ← Webhook subdomain
│   │   ├── search/                     ← SearchController
│   │   ├── health/                     ← HealthCheckController
│   │   ├── admin/                      ← FailedJobController
│   │   ├── interceptor/                ← app/Http/Middleware/
│   │   ├── config/                     ← 14 @Configuration class
│   │   └── shell/                      ← app/Console/Commands/
│   └── resources/
│       ├── application.yml             ← config/*.php (14 fayl)
│       ├── application-{dev,test,docker}.yml
│       ├── logback-spring.xml          ← StructuredLogger
│       ├── db/migration/{user,product,order,payment}/
│       │                               ← database/migrations/ (24 fayl, 4 DB üzrə bölünüb)
│       └── templates/                  ← resources/views/emails/
└── src/test/
    ├── java/az/ecommerce/
    │   ├── unit/                       ← tests/Unit/
    │   ├── integration/                ← tests/Integration/
    │   ├── feature/                    ← tests/Feature/
    │   └── seed/                       ← database/seeders/
```

## Texnologiya Mapping-i

| Laravel | Spring Boot | Niyə? |
|---------|-------------|-------|
| **Laravel 13** | **Spring Boot 3.4** | Ən son LTS |
| **PHP 8.3** | **Java 21** | Modern JDK, records, virtual threads, pattern matching |
| **Eloquent ORM** | **Spring Data JPA + Hibernate** | Industry standart |
| **DI Container** | **Spring IoC** | Auto-wiring `@Component`, `@Service` |
| **Sanctum** | **Spring Security + JWT** (jjwt) | Stateless, scalable |
| **Laravel Policy** | **`@PreAuthorize`** + Spring Security | Method-level authz |
| **Form Request** | **DTO + `@Valid` + Bean Validation** | `@NotBlank`, `@Email`, `@Size` |
| **API Resource** | **Java record + MapStruct** | Type-safe mapping |
| **Events / Listeners** | **`ApplicationEventPublisher` + `@EventListener`** | In-process |
| **Queue / Job** | **Spring Rabbit + `@RabbitListener`** | Distributed |
| **Mailable + Blade** | **JavaMailSender + Thymeleaf** | Template engine |
| **Cache (Redis)** | **`@Cacheable` + spring-data-redis** | Annotation-driven |
| **Tagged Cache** | **TaggedCacheService** (custom) | Spring Cache tag dəstəkləmir |
| **Distributed Lock** | **Redisson** (`RLock`) | Watchdog, fairness |
| **Migration** | **Flyway** | SQL changeset, version controlled |
| **Seeder** | **`CommandLineRunner` `@Profile("seed")`** | `mvn spring-boot:run -Dspring-boot.run.profiles=seed` |
| **Factory** | **Builder class + Instancio** | Test data |
| **Artisan command** | **Spring Shell `@ShellComponent`** | CLI |
| **Scheduled task** | **`@Scheduled`** | `@EnableScheduling` |
| **Middleware** | **`HandlerInterceptor`** / **`OncePerRequestFilter`** | HTTP layer |
| **Service Provider** | **`@Configuration` + `@Bean`** | DI bindings |
| **config/*.php** | **`application.yml` + `@ConfigurationProperties`** | Type-safe config |
| **php-amqplib** | **spring-boot-starter-amqp** | RabbitMQ |
| **Stripe SDK** | Strategy + ACL | Eyni pattern |
| **CircuitBreaker** | **Resilience4j** (`@CircuitBreaker`) | Annotation-driven |
| **Custom Logger** | **SLF4J + Logback + logstash-encoder** | JSON output |
| **DDD bounded contexts** | **Spring Modulith** (`@Modulithic`) | Compile-time isolation |
| **Event Sourcing** | **Axon Framework** (`@Aggregate`, `@EventHandler`) | Out-of-box ES |
| **Saga** | **Axon `@Saga`** | Declarative orchestration |
| **Outbox Pattern** | **Spring Modulith Event Publication Registry** | Avtomatik |
| **Test (PHPUnit)** | **JUnit 5 + Mockito + Testcontainers** | Real infra in tests |

## Pattern-lər

### 1. CQRS (Command Query Responsibility Segregation)
- **Laravel**: `SimpleCommandBus`, `SimpleQueryBus` + middleware pipeline
- **Spring**: `shared/application/bus/` interface-lər, `shared/infrastructure/bus/SimpleCommandBus.java` impl
- Hər command/query üçün ayrı handler `@Service`
- Pipeline: Logging → Idempotency → Validation → Transaction → RetryOnConcurrency → Handler

### 2. Aggregate Root + Domain Events
- **Laravel**: `AggregateRoot::recordEvent()`, `pullDomainEvents()`
- **Spring**: `shared/domain/AggregateRoot.java` (POJO, JPA-dan asılı deyil)
- Repository.save()-dən sonra `EventDispatcher.dispatchAll(aggregate)` çağrılır
- Domain Event → sinxron `@EventListener`
- Integration Event → Outbox → RabbitMQ

### 3. Value Object
- **Laravel**: immutable PHP class
- **Spring**: **Java record** + compact constructor validation
- Nümunələr: `Money(amount, currency)`, `Email`, `Stock`, `OrderId`, `Address`

### 4. Repository Pattern
- Domain layer-də interface (`UserRepository`)
- Infrastructure-də impl (`UserRepositoryImpl` adapts JPA)
- Spring Data JPA (`JpaUserRepository` interface — runtime impl)

### 5. Decorator Pattern
- **Laravel**: `CachedProductRepository wraps EloquentProductRepository`
- **Spring**: `CachedProductRepository @Primary` decorate edir `ProductRepositoryImpl`-i

### 6. Strategy Pattern (Payment)
- `PaymentGateway` interface + 3 impl (`CreditCardGateway`, `PayPalGateway`, `BankTransferGateway`)
- `PaymentStrategyResolver` Spring DI ilə bütün impl-ləri Map-ə yığır

### 7. Circuit Breaker (Resilience4j)
- Laravel: custom `CircuitBreaker.php`
- Spring: `@CircuitBreaker(name="paymentGateway", fallbackMethod="fallback")` annotation
- Konfiqurasiya: `application.yml-də resilience4j.circuitbreaker.instances.paymentGateway`

### 8. Anti-Corruption Layer (ACL)
- **Laravel**: `PaymentGatewayACL.php`
- **Spring**: `payment/application/acl/PaymentGatewayACL.java` — domain-i Stripe response dəyişikliklərindən qoruyur

### 9. Outbox Pattern
- 2 hissədən ibarət:
  1. `OutboxRelay` — DomainEvent-lər outbox cədvəlinə yazılır (eyni transaction)
  2. `OutboxPublisherJob` — `@Scheduled` hər dəqiqə published=false-ları RabbitMQ-yə publish edir
- Spring Modulith Event Publication Registry bunu avtomatik də edir

### 10. Saga Pattern (Axon)
- Laravel: `OrderSaga.php` (manual orchestration)
- Spring: `@Saga` + `@SagaEventHandler` (Axon Framework declarative)

### 11. Specification Pattern
- `Specification<T>` functional interface (`and`, `or`, `not` composition)

### 12. Distributed Lock
- **Laravel**: Redis SETNX manual
- **Spring**: **Redisson** `RLock` (watchdog, fairness)

### 13. Multi-Tenancy
- **Laravel**: `BelongsToTenant` trait + `tenant_id` sütun
- **Spring**: Hibernate multi-tenancy + `TenantInterceptor` + `TenantIdentifierResolver`

### 14. Event Sourcing
- Laravel: custom `EloquentEventStore` + `SnapshotStore`
- Spring: **Axon Framework** out-of-box (event_store, snapshots cədvəlləri)

## Layer Mapping-i

```
┌─ Laravel src/<Context>/        ─┬─→ Spring az/ecommerce/<context>/
│                                  │
├─ Application/                    ├─ application/
│  ├─ Commands/<Name>/             │  ├─ command/<name>/
│  │  ├─ <Name>Command.php         │  │  ├─ <Name>Command.java (record)
│  │  └─ <Name>Handler.php         │  │  └─ <Name>Handler.java (@Service)
│  ├─ Queries/<Name>/              │  ├─ query/<name>/
│  ├─ Services/                    │  ├─ service/
│  ├─ DTOs/                        │  ├─ dto/
│  ├─ ProcessManagers/             │  ├─ processmanager/
│  └─ Subscribers/                 │  └─ subscriber/
│                                  │
├─ Domain/                         ├─ domain/
│  ├─ Entities/<Aggregate>.php     │  ├─ <Aggregate>.java (POJO)
│  ├─ ValueObjects/                │  ├─ valueobject/
│  ├─ Events/                      │  ├─ event/
│  ├─ Enums/                       │  ├─ enums/
│  ├─ Specifications/              │  ├─ specification/
│  ├─ Sagas/                       │  ├─ saga/
│  ├─ Services/                    │  ├─ service/
│  ├─ Factories/                   │  ├─ factory/
│  ├─ Policies/                    │  ├─ policy/
│  └─ Repositories/<I>Interface.php│  └─ repository/<Name>Repository.java (interface)
│                                  │
└─ Infrastructure/                 └─ infrastructure/
   ├─ Models/<Name>Model.php          ├─ persistence/<Name>Entity.java (JPA)
   │                                  │  + Jpa<Name>Repository.java (Spring Data)
   │                                  │  + <Name>RepositoryImpl.java (adapter)
   ├─ Repositories/Eloquent*.php      ├─ persistence/<Name>RepositoryImpl.java
   ├─ Outbox/                         ├─ outbox/
   ├─ Projectors/                     ├─ readmodel/<Name>Projector.java
   ├─ ReadModel/                      ├─ readmodel/
   └─ Providers/<Name>Provider.php    └─ config/<Name>Config.java
```

## Bounded Context-lər

### User Context
| Layer | Laravel fayl | Spring fayl |
|-------|-------------|-------------|
| Aggregate | `src/User/Domain/Entities/User.php` | `user/domain/User.java` |
| VO | `Email.php`, `Password.php`, `UserId.php` | `user/domain/valueobject/{Email,Password,UserId}.java` |
| Event | `UserRegisteredEvent.php` | `user/domain/event/UserRegisteredEvent.java` |
| Command | `RegisterUserCommand.php` + `Handler` | `user/application/command/{RegisterUserCommand,RegisterUserHandler}.java` |
| Query | `GetUserQuery.php` + `Handler` | `user/application/query/{GetUserQuery,GetUserHandler}.java` |
| Repository | `EloquentUserRepository.php` | `user/infrastructure/persistence/UserRepositoryImpl.java` |
| Controller | `AuthController.php`, `UserController.php`, `TwoFactorController.php` | `user/infrastructure/web/{AuthController,UserController,TwoFactorController}.java` |
| 2FA | `TwoFactorService.php` | `shared/infrastructure/auth/TwoFactorService.java` (googleauth) |

### Product Context
| Komponent | Laravel | Spring |
|-----------|---------|--------|
| Money VO | `Money.php` (qəpiklə) | `Money.java` record + arithmetic |
| Stock VO | `Stock.php` | `Stock.java` (decrease/increase) |
| Specification | `ProductIsInStockSpec` | `ProductIsInStockSpec.java` |
| Cached repo | `CachedProductRepository` | `CachedProductRepository @Primary` |
| Currency | enum | enum (USD, EUR, AZN) |

### Order Context (ən kompleks)
| Komponent | Laravel | Spring |
|-----------|---------|--------|
| Aggregate | `Order.php` | `Order.java` (state machine) |
| State machine | `OrderStatusEnum.php::canTransitionTo()` | `OrderStatusEnum.java::canTransitionTo()` |
| 7 event | `OrderCreated/Confirmed/Paid/Cancelled` + 2 Integration | eyni adlarla `event/` paketdə |
| Saga | `OrderSaga.php` | `OrderSaga.java` (Axon `@Saga`) |
| Outbox | `OutboxPublisher.php` | `OutboxPublisherJob.java` (`@Scheduled`) |
| Read Model | `OrderReadModel + Projector` | `OrderReadModelEntity + OrderListProjector` |
| Domain Service | `OrderDomainService::calculateOrderPrice()` | eyni metodla |

### Payment Context
| Komponent | Laravel | Spring |
|-----------|---------|--------|
| Strategy | `PaymentGatewayInterface + 3 impl` | `PaymentGateway + 3 @Component` |
| Resolver | `PaymentStrategyResolver` | DI Map ilə (`PaymentStrategyResolver`) |
| Circuit Breaker | custom `CircuitBreaker.php` | Resilience4j `@CircuitBreaker` |
| ACL | `PaymentGatewayACL.php` | `PaymentGatewayACL.java` |

### Notification Context
| Komponent | Laravel | Spring |
|-----------|---------|--------|
| 4 Listener | `OrderCreated/PaymentCompleted/Failed/LowStock` | eyni adlarla `@EventListener` + `@Async` |
| 5 Mail template | `resources/views/emails/*.blade.php` | `resources/templates/*.html` (Thymeleaf) |
| EmailChannel | `EmailChannel.php` | `EmailChannel.java` (JavaMailSender) |

## Cross-Cutting Concerns

### HTTP Middleware (7) → Interceptor
| Laravel Middleware | Spring Component |
|--------------------|------------------|
| `CorrelationIdMiddleware` | `CorrelationIdInterceptor` (MDC) |
| `IdempotencyMiddleware` | `IdempotencyInterceptor` (24h Redis TTL) |
| `FeatureFlagMiddleware` | `FeatureFlagInterceptor` |
| `ApiVersionMiddleware` | `ApiVersionInterceptor` |
| `TenantMiddleware` | `TenantInterceptor` |
| `AuditMiddleware` | `AuditInterceptor` |
| `ForceJsonResponse` | `ForceJsonResponseFilter` |

### CommandBus Middleware Pipeline (sıra önəmlidir!)
```
Logging (10) → Idempotency (20) → Validation (30) → Transaction (40) → RetryOnConcurrency (50) → Handler
```
`@Order` qiyməti ilə sıralanır.

### Exception → HTTP Status Mapping
| Exception | HTTP Status |
|-----------|-------------|
| `EntityNotFoundException` | 404 |
| `ValidationException` / `MethodArgumentNotValidException` | 400 |
| `DomainException` | 422 |
| `AccessDeniedException` | 403 |
| `Exception` (default) | 500 |

`shared/infrastructure/api/GlobalExceptionHandler.java` (`@RestControllerAdvice`)

## İşə Salma

### Lokal (development)
```bash
# 1. Docker servisləri qaldır
docker-compose up -d mysql rabbitmq redis mailpit

# 2. Spring Boot run
./mvnw spring-boot:run

# 3. Test
./mvnw test

# 4. Seed (test data)
./mvnw spring-boot:run -Dspring-boot.run.profiles=seed
```

### Tam Docker
```bash
docker-compose up --build
# Endpoint: http://localhost:8080
# RabbitMQ UI: http://localhost:15672 (guest/guest)
# Mailpit UI: http://localhost:8025
```

### Spring Shell (Artisan əvəzi)
```bash
java -jar target/ecommerce-spring-1.0.0.jar
shell:> outbox:publish
shell:> projection:rebuild
shell:> queue:failed-monitor
```

## Endpoint-lər (43 ədəd, Laravel-dəki kimi)

| Endpoint | Method | Auth | Controller |
|----------|--------|------|------------|
| `/api/auth/register` | POST | ❌ | AuthController |
| `/api/auth/login` | POST | ❌ | AuthController |
| `/api/auth/me` | GET | ✅ | AuthController |
| `/api/auth/2fa/{enable,confirm,disable,verify}` | POST | ✅/❌ | TwoFactorController |
| `/api/users/{id}` | GET | ❌ | UserController |
| `/api/products` | GET | ❌ | ProductController |
| `/api/products/{id}` | GET | ❌ | ProductController |
| `/api/products` | POST | ✅ | ProductController |
| `/api/products/{id}/stock` | PATCH | ✅ | ProductController |
| `/api/orders` | POST | ✅ | OrderController |
| `/api/orders/{id}` | GET | ✅ | OrderController |
| `/api/orders/user/{userId}` | GET | ✅ | OrderController |
| `/api/orders/{id}/cancel` | POST | ✅ | OrderController |
| `/api/orders/{id}/status` | PATCH | ✅ | OrderController |
| `/api/payments/process` | POST | ✅ | PaymentController |
| `/api/payments/{id}` | GET | ✅ | PaymentController |
| `/api/webhooks` | GET/POST/PATCH/DELETE | ✅ | WebhookController |
| `/api/health/{,live,ready}` | GET | ❌ | HealthCheckController |
| `/api/admin/failed-jobs/*` | GET/POST/DELETE | ✅ ADMIN | FailedJobController |

## Öyrənmə Tövsiyəsi

Hər dəfə Laravel-də nəyisə görəndə:
1. Aşağıdakı cədvəldə Spring qarşılığını tap
2. Spring layihəsində o paketə bax (kommentlərdə Laravel fayl adı yazılıb)
3. İki versiyanı yan-yana oxu

**Vacib fərqlər:**
- **Domain layer Spring-də saf POJO-dur** — JPA annotation-ları infrastructure UserEntity-də. Bu `Persistence Ignorance` prinsipidir.
- **Java record** PHP class-dan üstündür: immutable, equals/hashCode auto, toString auto.
- **DI compile-time-da tip yoxlanır**. Laravel-də runtime-da binding tapmırsa exception. Spring-də compile-də xəta verir.
- **`@Transactional`** Laravel-dəki `DB::transaction()` callback-dən fərqli olaraq AOP proxy ilə işləyir — public metodlarda işləyir.
- **Bean Validation** annotation-ları (`@NotBlank`, `@Email`, `@Valid`) Laravel Form Request rules-dan daha type-safe-dir.
- **JWT stateless-dir** — Sanctum-un personal_access_tokens cədvəlinə ehtiyac yoxdur, amma istəsəniz refresh token üçün saxlaya bilərsiniz.

## Bilməli olduğunuz vacib quraşdırma detalları

### Multi-DB Transactional Outbox — **həll edilib**
Hər context-in öz `outbox_messages` cədvəli var:
- `user_db/V8__create_user_outbox.sql`
- `product_db/V3__create_product_outbox.sql`
- `order_db/V5__create_outbox_messages.sql` (mövcud)
- `payment_db/V3__create_payment_outbox.sql`

Alternativ: **Spring Modulith Event Publication Registry** artıq bunu out-of-box edir
(`pom.xml`-də `spring-modulith-events-jpa` dependency var). Context-aware router
yaratmaq əvəzinə Spring Modulith-in `@ApplicationModuleListener` istifadə etmək tövsiyə olunur.

### Axon Framework optional-dır
Default-da OrderSaga sadə Spring `@EventListener`-dir. Tam event sourcing üçün:
1. `pom.xml`-də `axon-spring-boot-starter` dependency-ni uncomment edin
2. `OrderSaga`-da `@Saga`, `@StartSaga`, `@SagaEventHandler` annotations-larını qaytarın
3. Axon `JpaEventStorageEngine` konfiqurasiya edin

### CommandBus middleware sırası
```
LoggingMiddleware       (order=10)  ← ən xarici
IdempotencyMiddleware   (order=20)
ValidationMiddleware    (order=30)
TransactionMiddleware   (order=40)  ← @Primary userTransactionManager istifadə edir
RetryOnConcurrencyMiddleware (order=50)  ← ən daxili (handler-dən əvvəl)
```

Başqa context (Order, Payment) üçün handler-də açıq `@Transactional(transactionManager="orderTransactionManager")` istifadə edin.

### Custom Bean Validation annotation-ları
- `@ValidUuid` — UUID format
- `@ValidMoney` — qəpiklə müsbət
- `@ValidCurrency` — USD/EUR/AZN
- `@ValidOrderStatus` — enum yoxlama

### Test profil
```bash
./mvnw test                # H2 in-memory + Flyway disabled
./mvnw verify              # Testcontainers ilə real MySQL
./mvnw spring-boot:run -Dspring-boot.run.profiles=seed  # test data
```

## Final Müqayisə Cədvəli

| Sahə | Laravel | Spring (bu layihə) |
|---|---|---|
| Bounded Context | `src/<Name>/` | `az.ecommerce.<name>/` (Spring Modulith) |
| Database-per-context | 4 connection | 4 `@Configuration` + 4 Flyway folder |
| Aggregate Root | `extends AggregateRoot` | `extends AggregateRoot` (POJO) |
| Value Object | immutable PHP class | Java `record` + compact constructor |
| Domain Event sinxron | event() helper | `EventDispatcher.dispatchAll()` |
| Integration Event async | RabbitMQ via php-amqplib | `OutboxRelay` → `OutboxPublisherJob` |
| Repository | `EloquentXxxRepository` | `XxxRepositoryImpl` (adapts JPA) |
| Cache | `Cache::tags(...)` | `TaggedCacheService` (Redis SET) |
| Distributed Lock | Redis SETNX | Redisson `RLock` |
| Circuit Breaker | custom | Resilience4j `@CircuitBreaker` |
| Strategy | `PaymentGatewayInterface` + 3 impl | eyni — DI Map ilə |
| Saga | `OrderSaga` (manual) | `OrderSaga` (Spring `@EventListener`) |
| State Machine | enum-da `canTransitionTo` | eyni — Java enum |
| Form Request | `app/Http/Requests/` | DTO + `@Valid` + Bean Validation |
| Resource | `app/Http/Resources/` | Java record `fromDomain()` |
| Mailable | `Mail::to()->send()` | JavaMailSender + Thymeleaf |
| Job | `dispatch(...)` | `@Async` + `@RabbitListener` |
| Listener | `Event::listen()` | `@EventListener` + `@Async` |
| Observer | `Model::observe()` | JPA `@EntityListeners` |
| Middleware | `app/Http/Middleware/` | `HandlerInterceptor` + `OncePerRequestFilter` |
| Policy | `Gate::define` | Spring Security `@PreAuthorize` |
| Console Command | `php artisan ...` | Spring Shell `@ShellComponent` |
| Scheduler | `Console/Kernel.php` | `@Scheduled` |
| Audit | `AuditMiddleware` + log | `AuditInterceptor` + `AuditService` (JPA) |
| 2FA | `TwoFactorService` | `TwoFactorService` (googleauth TOTP) |
| Webhook delivery | `SendWebhookJob` | `SendWebhookJob` + `WebhookService` (HMAC) |
| Password Reset | `ForgotPasswordController` | `PasswordResetService` |
| Outbox Pattern | `OutboxPublisher` | `OutboxRelay` + `OutboxPublisherJob` |
| Inbox Pattern | `InboxStore` | `IdempotentConsumer` + `InboxMessageEntity` |
| DLQ | `DeadLetterQueue` | `DeadLetterListener` (`@RabbitListener`) |
| Read Model | `OrderListProjector` | eyni — `@EventListener @Async` |
| Multi-tenancy | `BelongsToTenant` trait | Hibernate multi-tenancy + `TenantInterceptor` |
| Feature Flags | `FeatureFlag` | `@ConfigurationProperties` |
| Rate Limiting | `RateLimiter::for(...)` | Bucket4j |
| API Versioning | `ApiVersionMiddleware` | `ApiVersionInterceptor` (MDC) |
| Idempotency | `IdempotencyMiddleware` | `IdempotencyMiddleware` (Redis 24h TTL) |
| Correlation ID | `CorrelationIdMiddleware` | `CorrelationIdInterceptor` (MDC) |
| Health Check | `HealthCheckController` | `HealthCheckController` + Actuator |
| Failed Jobs Admin | `FailedJobController` | `FailedJobController` + `DeadLetterRepository` |
| Structured Logging | `StructuredLogger` | logback-spring.xml + LogstashEncoder |
