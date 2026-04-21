# E-Commerce DDD Project Documentation

Bu sened layihenin tam texniki dokumentasiyasidir. Yeni developer komandaya qosulduqda, bu senedi oxuyaraq layihenin arxitekturasini, strukturunu ve is prinsipini basa dusmelidir.

---

## Mundericat

1. [Layihe Haqqinda](#1-layihe-haqqinda)
2. [Texnologiya Steki](#2-texnologiya-steki)
3. [Layiheni Ishe Salmaq](#3-layiheni-ishe-salmaq)
4. [Qovluq Strukturu](#4-qovluq-strukturu)
5. [Arxitektura](#5-arxitektura)
6. [Bounded Context-ler](#6-bounded-context-ler)
7. [CQRS — Command ve Query Ayrilmasi](#7-cqrs--command-ve-query-ayrilmasi)
8. [Event Sistemi](#8-event-sistemi)
9. [Event Sourcing](#9-event-sourcing)
10. [API Endpoint-leri](#10-api-endpoint-leri)
11. [Middleware Pipeline](#11-middleware-pipeline)
12. [Verilenbazasi](#12-verilenbazasi)
13. [Design Pattern-ler](#13-design-pattern-ler)
14. [Infrastructure Servisler](#14-infrastructure-servisler)
15. [Docker ve Deploy](#15-docker-ve-deploy)
16. [Testler](#16-testler)
17. [Error Handling](#17-error-handling)
18. [Yeni Feature Elave Etme Rehberi](#18-yeni-feature-elave-etme-rehberi)
19. [FAQ — Tez-Tez Verilen Suallar](#19-faq--tez-tez-verilen-suallar)
20. [Senior-Level Arxitektural Pattern-ler](#20-senior-level-arxitektural-pattern-ler)

---

## 1. Layihe Haqqinda

Bu layihe **Senior PHP Developer** olmaq ucun lazim olan butun movzulari praktiki olaraq oyrenmek meqsedile yaradilmis **E-Commerce** teqbiqidir.

Layihe real production sistemlerinde istifade olunan arxitektura ve pattern-leri numayis etdirir:

- **Domain-Driven Design (DDD)** ile modulyar qurulusu var
- **CQRS** ile oxuma ve yazma emeliyyatlari ayrilmisdir
- **Event Sourcing** ile butun deyisiklikler event kimi saxlanilir
- **Event-Driven Architecture** ile modullar arasinda asinxron elaqe qurulub
- Butun kodlar **Azerbaycan dilinde** serh edilib — her bir class, metod ve prinsip izah olunub

**Teqbiqin esas funksiyalari:**
- Istifadeci qeydiyyati ve autentifikasiya (2FA daxil)
- Mehsul idareetmesi (CRUD, stok, sekil)
- Sifarislerin yaradilmasi ve idare edilmesi
- Odenish emalati (Strategy pattern ile coxlu gateway)
- Bildiris sistemi (Email, SMS kanallari)
- Webhook sistemi
- Admin paneli ucun failed job idareetmesi

---

## 2. Texnologiya Steki

| Texnologiya | Versiya | Meqsed |
|---|---|---|
| PHP | 8.3 | Esas proqramlashdirma dili |
| Laravel | 13 | Framework |
| MySQL | 8.0 | Esas verilenbazasi |
| Redis | Latest | Cache, session, queue, distributed lock |
| RabbitMQ | 3-management | Message broker (asinxron mesajlashma) |
| Nginx | Latest | Reverse proxy, web server |
| Docker + Compose | Latest | Konteynerleshdirme ve orkestrleshdirme |
| Mailpit | Latest | Inkishaf muhitinde email testi |
| Laravel Sanctum | - | API token autentifikasiyasi |

**Composer paketleri:**
- `php-amqplib/php-amqplib` — RabbitMQ ile islemek ucun

---

## 3. Layiheni Ishe Salmaq

### Docker ile (tovsiye olunan)

```bash
# 1. Layiheni klonla
git clone <repo-url>
cd app

# 2. Docker konteynerlerini ishlet
docker-compose up -d

# 3. Bitmesini gozle (MySQL hazir olana qeder)
# entrypoint.sh avtomatik olaraq:
#   - Composer install edir
#   - .env faylini yaradir
#   - APP_KEY generate edir
#   - Migration-lari ishledir

# 4. Teqbiq hazirdir
# API:            http://localhost:8080
# RabbitMQ UI:    http://localhost:15672 (guest/guest)
# Mailpit UI:     http://localhost:8025
# MySQL:          localhost:3306
# Redis:          localhost:6379
```

### Lokal muhtde (Docker olmadan)

```bash
# 1. Composer paketleri
composer install

# 2. .env faylini hazirla
cp .env.example .env
php artisan key:generate

# 3. Verilenbazasini yarat (SQLite ile)
touch database/database.sqlite
php artisan migrate

# 4. Serveri ishlet
php artisan serve
```

### Muhit Deyishenleri (.env)

| Deyishen | Defolt | Izah |
|---|---|---|
| `DB_CONNECTION` | sqlite | Verilenbazasi driveri |
| `CACHE_STORE` | database | Cache saxlama yeri |
| `QUEUE_CONNECTION` | database | Queue driveri |
| `RABBITMQ_HOST` | localhost | RabbitMQ serveri |
| `RABBITMQ_PORT` | 5672 | RabbitMQ portu |
| `REDIS_HOST` | 127.0.0.1 | Redis serveri |

---

## 4. Qovluq Strukturu

```
app/
├── app/                          # Laravel Application Layer
│   ├── Console/Commands/         # Artisan komandalar
│   │   ├── ConsumeRabbitMQCommand.php    # RabbitMQ mesaj iscisi
│   │   ├── GracefulWorkerCommand.php     # Tehlukesiz dayandirilan worker
│   │   ├── MonitorFailedJobsCommand.php  # Ugursuz job monitorinqi
│   │   ├── PublishOutboxCommand.php      # Outbox mesajlarini gonder
│   │   └── RebuildProjectionCommand.php  # Read model yeniden qur
│   ├── Http/
│   │   ├── Controllers/          # API Controller-ler (13 eded)
│   │   ├── Middleware/           # HTTP Middleware-ler (8 eded)
│   │   ├── Requests/            # Form Request validasiya class-lari
│   │   ├── Resources/           # API Response formatlayicilari
│   │   └── Transformers/        # API versiya transformatorlari (V1, V2)
│   ├── Jobs/                     # Queue job-lari (7 eded)
│   ├── Listeners/                # Event listener-ler (5 eded)
│   ├── Mail/                     # Email shablonlari
│   ├── Models/                   # Eloquent model-ler
│   ├── Observers/                # Model observer-ler (3 eded)
│   ├── Policies/                 # Authorization policy-ler (3 eded)
│   ├── Providers/                # Service provider-ler
│   │   ├── AppServiceProvider.php        # Event, Observer, Rate limit qeydiyyati
│   │   └── DomainServiceProvider.php     # DDD Composition Root
│   └── Rules/                    # Custom validasiya qaydalari
│
├── src/                          # Domain-Driven Design Core
│   ├── User/                     # ── User Bounded Context ──
│   │   ├── Application/          #   Command, Query, DTO, Service
│   │   ├── Domain/               #   Entity, ValueObject, Event, Repository Interface
│   │   └── Infrastructure/       #   Eloquent Model, Repository Implementation
│   ├── Product/                  # ── Product Bounded Context ──
│   │   ├── Application/
│   │   ├── Domain/
│   │   └── Infrastructure/
│   ├── Order/                    # ── Order Bounded Context ──
│   │   ├── Application/
│   │   ├── Domain/
│   │   └── Infrastructure/       #   + Outbox, ReadModel
│   ├── Payment/                  # ── Payment Bounded Context ──
│   │   ├── Application/          #   + ACL (Anti-Corruption Layer)
│   │   ├── Domain/
│   │   └── Infrastructure/       #   + CircuitBreaker, Gateway
│   ├── Notification/             # ── Notification Bounded Context ──
│   │   ├── Application/
│   │   ├── Domain/
│   │   └── Infrastructure/       #   + Email/SMS Channels
│   └── Shared/                   # ── Shared Kernel (ortaq kod) ──
│       ├── Application/          #   CommandBus, QueryBus, Middleware
│       ├── Domain/               #   AggregateRoot, Entity, ValueObject, DomainEvent
│       └── Infrastructure/       #   (asagida etraflı)
│           ├── Audit/            #     Audit log sistemi
│           ├── Auth/             #     2FA, autentifikasiya
│           ├── Bus/              #     SimpleCommandBus, SimpleQueryBus, EventDispatcher
│           ├── Cache/            #     TaggedCacheService
│           ├── ContextCommunication/  # Bounded context arasi elaqe
│           ├── EventSourcing/    #     EventSourcedAggregateRoot, EventStore, Snapshot
│           ├── FeatureFlags/     #     Feature flag sistemi
│           ├── Locking/          #     Redis Distributed Lock
│           ├── Logging/          #     Structured JSON logging
│           ├── Messaging/        #     RabbitMQ Publisher/Consumer, DLQ, Inbox, IdempotentConsumer
│           ├── Multitenancy/     #     Multi-tenant desteyi, TenantAwareRepository
│           ├── Persistence/      #     QueryOptimizer, UnitOfWork, ReadReplicaConnection
│           ├── RateLimiting/     #     Plan-based rate limiter
│           ├── Resilience/       #     GracefulDegradation, RetryableRepository
│           ├── Api/              #     BackendForFrontend (BFF)
│           ├── Search/           #     Global axtarish servisi
│           └── Webhook/          #     Webhook sistemi
│
├── config/                       # Konfiqurasiya faylari (14 eded)
├── database/
│   ├── migrations/               # Verilenbazasi migration-lari (20 eded)
│   ├── factories/                # Test ucun data factory-ler
│   └── seeders/                  # Ilkin data yükleyiciler
├── routes/
│   ├── api.php                   # Esas API route-lari
│   └── api_v1.php                # API v1 route-lari
├── tests/                        # Testler (Feature, Integration, Unit)
├── docker/                       # Docker konfiqurasiyasi
│   ├── entrypoint.sh             # Konteyner baslangic scripti
│   ├── nginx/default.conf        # Nginx konfiqurasiyasi
│   └── mysql/init.sql            # MySQL ilkin qurma
├── docker-compose.yml            # Docker servis tenimleri
├── Dockerfile                    # PHP 8.3-FPM image
└── composer.json                 # PHP asililiklar
```

---

## 5. Arxitektura

### 5.1 Umumi Arxitektura: Modulyar Monolit + DDD

Bu layihe **Modulyar Monolit** arxitekturasindadir — mikro xidmetlerin ustunluklerini (modul izolasiyasi, ayri verilenbazasi) monolit saxlayaraq temin edir.

```
┌─────────────────────────────────────────────────────────────────┐
│                        HTTP REQUEST                              │
│                  (API Gateway / Nginx)                            │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                   MIDDLEWARE PIPELINE                             │
│  ForceJsonResponse → ApiVersion → Throttle → Auth → Audit        │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                     CONTROLLER LAYER                             │
│              (app/Http/Controllers/)                              │
│         Request → CommandBus/QueryBus → Response                 │
└──────────┬────────────────┬──────────────────┬──────────────────┘
           │                │                  │
     ┌─────▼─────┐   ┌─────▼─────┐      ┌─────▼─────┐
     │  COMMAND   │   │   QUERY   │      │   EVENT   │
     │   BUS      │   │    BUS    │      │ DISPATCHER│
     │ (yazma)    │   │ (oxuma)   │      │           │
     └─────┬──────┘   └─────┬─────┘      └─────┬─────┘
           │                │                   │
           ▼                ▼                   ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BOUNDED CONTEXTS (src/)                        │
│                                                                  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐│
│  │   User   │ │ Product  │ │  Order   │ │ Payment  │ │Notific.││
│  │ Context  │ │ Context  │ │ Context  │ │ Context  │ │Context ││
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └────────┘│
│                                                                  │
│  Hər context 3 layerden ibaretdir:                               │
│  ┌────────────────────────────────────────────────────┐          │
│  │  Domain        (Entity, ValueObject, Event, Spec)  │          │
│  │  Application   (Command, Query, Handler, DTO)      │          │
│  │  Infrastructure(Repository, Model, Adapter)        │          │
│  └────────────────────────────────────────────────────┘          │
└─────────────────────────────────────────────────────────────────┘
           │                                    │
           ▼                                    ▼
┌──────────────────┐                 ┌──────────────────┐
│   MySQL (4 DB)   │                 │    RabbitMQ      │
│  user_db         │                 │  (Async Events)  │
│  product_db      │                 └──────────────────┘
│  order_db        │                 ┌──────────────────┐
│  payment_db      │                 │     Redis        │
└──────────────────┘                 │ (Cache, Lock)    │
                                     └──────────────────┘
```

### 5.2 DDD Layer Qaydalari

| Layer | Asililik | Mezmun | Qayda |
|---|---|---|---|
| **Domain** | Hec bir asililik yoxdur | Entity, ValueObject, Event, Repository Interface, Specification | Framework kodu ola bilmez. Yalniz saf PHP. |
| **Application** | Domain-den asilidir | Command, Query, Handler, DTO, Service | Use case-leri orkestrasiya edir. Oz biznes mentiqI yoxdur. |
| **Infrastructure** | Domain + Application-dan asilidir | Eloquent Model, Repository Impl, Adapter | Framework ve xarici servislerle isleyir. |

**Asililik istiqameti:** Infrastructure → Application → Domain (terse hec vaxt!)

### 5.3 Composition Root

`DomainServiceProvider.php` — butun dependency injection-larin konfiqurasiya olundugu yer:

```php
// Interface → Implementation mapping
CommandBus::class     → SimpleCommandBus (middleware pipeline ile)
QueryBus::class       → SimpleQueryBus
EventDispatcher::class → EventDispatcher (+ RabbitMQPublisher)
EventStore::class     → EloquentEventStore
EventUpcaster::class  → EventUpcaster (+ ConcreteEventUpcaster migration-lari)
```

**Bounded Context Provider-lerin Qeydiyyati:**
DomainServiceProvider.boot() metodu butun bounded context provider-leri yukleyir:
- OrderServiceProvider, PaymentServiceProvider, ProductServiceProvider
- UserServiceProvider, NotificationServiceProvider

**Middleware pipeline sirasi (Command Bus):**
```
Command → LoggingMiddleware → IdempotencyMiddleware → ValidationMiddleware → TransactionMiddleware → Handler
```

| Sira | Middleware | Vezifesi |
|---|---|---|
| 1 | `LoggingMiddleware` | Komandani log-layir (dublikat cehdi de logda gorunsun) |
| 2 | `IdempotencyMiddleware` | Dublikat command-i bloklayir (idempotencyKey() metodu ile) |
| 3 | `ValidationMiddleware` | DTO-nu validasiya edir |
| 4 | `TransactionMiddleware` | Butun emeliyyati DB transaction icinde ishledir |

---

## 6. Bounded Context-ler

### 6.1 User Context (`src/User/`)

Istifadeci idareetmesi — qeydiyyat, profil, autentifikasiya.

| Qat | Fayl | Meqsed |
|---|---|---|
| Domain | `Entities/User.php` | User aggregate root |
| Domain | `ValueObjects/Email.php` | Email value object (validasiyali) |
| Domain | `ValueObjects/Password.php` | Sifre value object (hash-li) |
| Domain | `Events/UserRegisteredEvent.php` | Domain event |
| Domain | `Events/UserRegisteredIntegrationEvent.php` | Diger modullara gonderilen event |
| Domain | `Repositories/UserRepositoryInterface.php` | Repository interfeysi |
| Application | `Commands/RegisterUser/` | Qeydiyyat command + handler |
| Application | `Queries/GetUser/` | Istifadeci melumati query + handler |
| Application | `DTOs/UserDTO.php` | Data transfer object |
| Infrastructure | `Models/UserModel.php` | Eloquent model |
| Infrastructure | `Repositories/EloquentUserRepository.php` | Repository implementasiyasi |

### 6.2 Product Context (`src/Product/`)

Mehsul idareetmesi — CRUD, stok, qiymет, sekil.

| Qat | Fayl | Meqsed |
|---|---|---|
| Domain | `Entities/Product.php` | Product aggregate root |
| Domain | `ValueObjects/MoneyValueObject.php` | Pul ve valyuta |
| Domain | `ValueObjects/StockValueObject.php` | Stok miqdarI |
| Domain | `Events/StockDecreasedEvent.php` | Stok azalma eventi |
| Domain | `Events/LowStockIntegrationEvent.php` | Stok azdir — diger modullar ucun |
| Domain | `Specifications/ProductIsInStockSpec.php` | Stok yoxlama spesifikasiyasi |
| Application | `Commands/CreateProduct/` | Mehsul yaratma |
| Application | `Commands/UpdateStock/` | Stok yenileme |
| Application | `Queries/ListProducts/` | Mehsul siyahisi (filtrli) |
| Infrastructure | `Repositories/CachedProductRepository.php` | Cache-li repository (Decorator) |

### 6.3 Order Context (`src/Order/`)

Sifarish idareetmesi — yaratma, legv, status deyishme, event sourcing.

| Qat | Fayl | Meqsed |
|---|---|---|
| Domain | `Entities/Order.php` | Order aggregate root |
| Domain | `Entities/EventSourcedOrder.php` | Event sourcing ile isleyen order |
| Domain | `Sagas/OrderSaga.php` | Distributed transaction orkestratoru |
| Domain | `Specifications/OrderCanBeCancelledSpec.php` | Legv oluna biler? |
| Domain | `Factories/OrderFactory.php` | Murekeeb order yaratma |
| Infrastructure | `Outbox/OutboxRepository.php` | Outbox pattern |
| Infrastructure | `ReadModel/OrderReadModel.php` | CQRS oxuma modeli |

### 6.4 Payment Context (`src/Payment/`)

Odenish emalati — coxlu gateway, circuit breaker, ACL.

| Qat | Fayl | Meqsed |
|---|---|---|
| Domain | `Entities/Payment.php` | Payment aggregate root |
| Domain | `Strategies/PaymentGatewayInterface.php` | Strategy pattern interfeysi |
| Domain | `Strategies/CreditCardGateway.php` | Kredit karti strategiyasi |
| Domain | `Strategies/PayPalGateway.php` | PayPal strategiyasi |
| Domain | `Strategies/BankTransferGateway.php` | Bank transferi strategiyasi |
| Application | `ACL/PaymentAntiCorruptionLayer.php` | Anti-Corruption Layer |
| Infrastructure | `CircuitBreaker/CircuitBreaker.php` | Xarici servis qorumasi |
| Infrastructure | `Gateway/StripeGatewayAdapter.php` | Stripe adapter |

### 6.5 Notification Context (`src/Notification/`)

Bildiris sistemi — email, SMS, multi-channel.

| Qat | Fayl | Meqsed |
|---|---|---|
| Domain | `Services/NotificationServiceInterface.php` | Gonderme interfeysi |
| Application | `Services/NotificationApplicationService.php` | Orkestrator |
| Infrastructure | `Channels/EmailChannel.php` | Email kanali |
| Infrastructure | `Channels/SmsChannel.php` | SMS kanali |

### 6.6 Shared Kernel (`src/Shared/`)

Butun context-lerin istifade etdiyi ortaq kod.

**Domain:**
- `AggregateRoot.php` — event yaratma bazasi
- `Entity.php` — identity-si olan obyekt
- `ValueObject.php` — deyersiz, immutable obyekt
- `DomainEvent.php` — domen hadisesi interface
- `IntegrationEvent.php` — modullar arasi hadise interface

**Application:**
- `CommandBus.php` / `QueryBus.php` — CQRS bus interfeysleri

---

## 7. CQRS — Command ve Query Ayrilmasi

### Nedir?

**CQRS (Command Query Responsibility Segregation)** — yazma (Command) ve oxuma (Query) emeliyyatlarini ayri-ayri idare etmek.

### Axin

```
YAZMA (Command):
  Controller → CommandBus → Middleware Pipeline → CommandHandler → Domain → Repository → DB

OXUMA (Query):
  Controller → QueryBus → QueryHandler → Repository/ReadModel → DB
```

### Command numunesi

```php
// 1. Controller command yaradir:
$command = new CreateOrderCommand(userId: 1, items: [...], currency: 'AZN');

// 2. CommandBus-a gonderir:
$result = $this->commandBus->dispatch($command);

// 3. CommandBus middleware pipeline ishledir:
//    Logging → Validation → Transaction → CreateOrderHandler

// 4. Handler domain mentiqini chaqirir:
class CreateOrderHandler {
    public function handle(CreateOrderCommand $cmd): OrderDTO {
        $order = Order::create($cmd->userId, $cmd->items, $cmd->currency);
        $this->repository->save($order);
        $this->eventDispatcher->dispatch($order->pullDomainEvents());
        return OrderDTO::fromEntity($order);
    }
}
```

### Query numunesi

```php
// 1. Controller query yaradir:
$query = new GetOrderQuery(orderId: 'ord_123');

// 2. QueryBus-a gonderir (middleware YOXDUR — oxuma ucun lazim deyil):
$result = $this->queryBus->ask($query);

// 3. Handler birbaşa DB-den oxuyur:
class GetOrderHandler {
    public function handle(GetOrderQuery $query): OrderDTO {
        $order = $this->repository->findById($query->orderId);
        return OrderDTO::fromEntity($order);
    }
}
```

### Middleware Pipeline (yalniz Command ucun)

| Sira | Middleware | Vezifsesi |
|---|---|---|
| 1 | `LoggingMiddleware` | Komandani log-layir (debug ve audit ucun) |
| 2 | `ValidationMiddleware` | DTO-nu validasiya edir |
| 3 | `TransactionMiddleware` | Butun emeliyyati DB transaction icinde ishledir |

---

## 8. Event Sistemi

### 8.1 Event Tipleri

| Tip | Meqsed | Nece gonderilir | Numune |
|---|---|---|---|
| **Domain Event** | Eyni bounded context daxilinde | Sinxron (EventDispatcher) | `OrderCreatedEvent` |
| **Integration Event** | Basqa bounded context-e | Asinxron (RabbitMQ) | `LowStockIntegrationEvent` |

### 8.2 Event Axini

```
1. Aggregate event yaradir:
   $this->recordEvent(new OrderCreatedEvent($orderId));

2. Repository save edir → EventDispatcher chaqirilir:
   $this->eventDispatcher->dispatch($order->pullDomainEvents());

3. EventDispatcher:
   ├── Domain Event → Laravel Listener (sinxron)
   │   └── SendOrderConfirmationListener → Email gonder
   │   └── DispatchPaymentJobListener → Odenish job-u dispatch et
   │
   └── Integration Event → RabbitMQ Publisher (asinxron)
       └── LowStockIntegrationEvent → "product.stock.low" routing key
```

### 8.3 Qeydiyyatli Listener-ler

`AppServiceProvider.php`-de konfiqurasiya olunub:

| Event | Listener | Ne edir |
|---|---|---|
| `OrderPlacedEvent` | `SendOrderConfirmationListener` | Tesdiq emaili gonderir |
| `OrderPlacedEvent` | `DispatchPaymentJobListener` | Odenish job-unu queue-ya gonderir |
| `PaymentProcessedEvent` | `UpdateOrderOnPaymentListener` | Sifarish statusunu yenileyir |
| `ProductStockChangedEvent` | `CheckLowStockListener` | Stok azalibsa admin-e bildiris |
| `JobFailed` | `FailedJobNotificationListener` | Ugursuz job ucun log/alert |

### 8.4 Outbox Pattern

Sifarish yaradilib amma event gonderilmedise — data uygunsuzlugu yaranir. Outbox pattern bunu hell edir:

```
1. Order yaradilir + OutboxMessage yazilir → eyni DB transaction icinde
2. PublishOutboxCommand (cron) outbox cedvelini oxuyur
3. Her mesaji RabbitMQ-ya gonderir
4. Ugurlu gonderilenler outbox-dan silinir
```

Fayl: `src/Order/Infrastructure/Outbox/OutboxRepository.php`

---

## 9. Event Sourcing

### Nedir?

Aggregate-in veziyyetini birbaşa DB-de saxlamaq evezine, **butun deyishiklikleri event kimi** saxlayiriq. Aggregate-i yuklemek ucun butun event-leri sirayla tebiq edirik.

### Fayl Strukturu

| Fayl | Meqsed |
|---|---|
| `Shared/Infrastructure/EventSourcing/EventSourcedAggregateRoot.php` | Event sourcing baza class-i |
| `Order/Domain/Entities/EventSourcedOrder.php` | Event sourced order implementasiyasi |
| `Shared/Infrastructure/EventSourcing/Snapshot.php` | Snapshot data class-i |
| `Shared/Infrastructure/EventSourcing/SnapshotStore.php` | Snapshot saxlama/yukleme |

### Axin

```
YARATMA:
  Order::create() → recordThat(OrderCreatedEvent) → event uncommittedEvents-e elave olunur
  
YUKLEME (snapshot olmadan):
  EventStore-dan butun event-leri oxu → her birini apply et → aggregate hazir

YUKLEME (snapshot ile):
  SnapshotStore-dan sonuncu snapshot-u yukle → EventStore-dan yalniz sonraki event-leri oxu → apply et
  
SNAPSHOT QAYDA: Her 100 event-den sonra avtomatik snapshot chekilir
```

### Event Upcasting (Schema Versiyalashma)

Event Store-da kohne versiya event-ler var. Yeni kod onlari oxuyanda problem yaranir — saheler uygun gelmir. Upcaster bunu hell edir:

```
v1: { "order_id": "abc", "user_id": "123" }                          ← kohne
v2: { "order_id": "abc", "user_id": "123", "currency": "AZN" }       ← yeni sahe
v3: { "order_id": "abc", "user_id": "123", "currency": "AZN", "total_amount": 0 }
```

**Fayllar:**
- `EventUpcaster.php` — upcasting mexanizmi (register + upcast)
- `ConcreteEventUpcaster.php` — real versiya migration-lari

**Qeydiyyatli upcaster-ler:**
| Event | Versiya | Deyishiklik |
|---|---|---|
| OrderCreatedEvent | v1→v2 | `currency` sahesi elave olundu (default: AZN) |
| OrderCreatedEvent | v2→v3 | `total_amount` sahesi elave olundu (default: 0) |
| PaymentCompletedEvent | v1→v2 | `currency` sahesi elave olundu |
| ProductCreatedEvent | v1→v2 | `description` sahesi elave olundu |

**DB migration ile ferqi:** DB migration bazadaki datani deyishir. Event upcaster event-i deyishmir — oxuyanda yaddashda chevirir (on-the-fly).

### Read Model Projector

Event sourcing ile yazma optimallashdirilib, amma oxuma yavasdir (her defe replay lazim). Projector bunu hell edir:

**Fayllar:**
- `Projector.php` — abstract projector baza class-i (idempotent, rebuild olunan)
- `OrderListProjector.php` — konkret implementasiya
- `OrderReadModel.php` — denormalized oxuma cedveli

**Axin:**
```
OrderCreatedEvent   → INSERT order_read_models (status: pending, total: 0)
OrderItemAddedEvent → UPDATE total_amount, item_count
OrderConfirmedEvent → UPDATE status = 'confirmed'
OrderPaidEvent      → UPDATE status = 'paid'
OrderCancelledEvent → UPDATE status = 'cancelled'
```

**Rebuild:** Proyeksiyada xeta tapilsa, `php artisan projection:rebuild` ile sifirdan yeniden qurulur.

---

## 10. API Endpoint-leri

### Autentifikasiya

| Metod | Endpoint | Auth | Throttle | Meqsed |
|---|---|---|---|---|
| POST | `/api/auth/register` | - | 3/deq | Qeydiyyat |
| POST | `/api/auth/login` | - | 5/deq | Daxil ol |
| POST | `/api/auth/logout` | Token | - | Chix |
| GET | `/api/auth/me` | Token | - | Cari istifadeci |
| POST | `/api/auth/forgot-password` | - | - | Sifre berpa |
| POST | `/api/auth/reset-password` | - | - | Sifre sifirla |

### Iki Faktorlu Autentifikasiya (2FA)

| Metod | Endpoint | Auth | Meqsed |
|---|---|---|---|
| POST | `/api/auth/2fa/enable` | Token | 2FA aktiv et |
| POST | `/api/auth/2fa/confirm` | Token | 2FA tesdiq et |
| POST | `/api/auth/2fa/disable` | Token | 2FA sondur |
| POST | `/api/auth/2fa/verify` | - | 2FA kod yoxla |
| POST | `/api/auth/2fa/verify-backup` | - | Ehtiyat kodla daxil ol |

### Mehsullar

| Metod | Endpoint | Auth | Meqsed |
|---|---|---|---|
| GET | `/api/products` | - | Mehsul siyahisi (CQRS Query) |
| GET | `/api/products/{id}` | - | Mehsul detali (CQRS Query) |
| POST | `/api/products` | Token | Mehsul yarat (CQRS Command) |
| PATCH | `/api/products/{id}/stock` | Token | Stok yenile (CQRS Command) |
| GET | `/api/products/{id}/images` | - | Sekilleri al |
| POST | `/api/products/{id}/images` | Token | Sekil elave et |
| DELETE | `/api/products/{id}/images/{imageId}` | Token | Sekil sil |

### Sifarishler

| Metod | Endpoint | Auth | Meqsed |
|---|---|---|---|
| POST | `/api/orders` | Token | Sifarish yarat (CQRS Command) |
| GET | `/api/orders/{id}` | Token | Sifarish detali (CQRS Query) |
| GET | `/api/orders/user/{userId}` | Token | Istifadeci sifarishleri (CQRS Query) |
| POST | `/api/orders/{id}/cancel` | Token | Sifarishi legv et (CQRS Command) |
| PATCH | `/api/orders/{id}/status` | Token | Status deyish (CQRS Command) |

### Odenishler

| Metod | Endpoint | Auth | Throttle | Meqsed |
|---|---|---|---|---|
| POST | `/api/payments/process` | Token | 10/deq | Odenish emal et |
| GET | `/api/payments/{id}` | Token | - | Odenish detali |

### Health Check

| Metod | Endpoint | Auth | Meqsed |
|---|---|---|---|
| GET | `/api/health` | - | Tam sagliq yoxlamasi (DB, Redis, RabbitMQ, Disk) |
| GET | `/api/health/live` | - | Kubernetes liveness probe |
| GET | `/api/health/ready` | - | Kubernetes readiness probe |

### Diger

| Metod | Endpoint | Auth | Meqsed |
|---|---|---|---|
| GET | `/api/search?q=laptop` | - | Qlobal axtarish |
| GET | `/api/notifications/preferences` | Token | Bildiris terchleri |
| PUT | `/api/notifications/preferences/{type}` | Token | Terch yenile |
| POST | `/api/webhooks` | Token | Webhook yarat |
| GET | `/api/webhooks` | Token | Webhook-lari siyahila |
| DELETE | `/api/webhooks/{id}` | Token | Webhook sil |
| PATCH | `/api/webhooks/{id}` | Token | Webhook aktiv/deaktiv |

### API Versiyalashma

API iki yolla versiyalashdirilir:

1. **URL prefiksi:** `/api/v1/products` — `routes/api_v1.php`-de tenimlenib
2. **Header:** `X-API-Version: v1` — `EnsureApiVersion` middleware ile idarə olunur

Response formati `V1/V2 Transformer`-ler vasitesile deyishir.

---

## 11. Middleware Pipeline

### HTTP Middleware Sirasi

```
Request
  │
  ├─ ForceJsonResponse          Accept: application/json mecbur edir
  ├─ EnsureApiVersion           X-API-Version header-ini oxuyur
  ├─ CorrelationIdMiddleware    Unique request ID yaradir (log izleme)
  ├─ throttle:api               Rate limiting (60/120 sorgu/deq)
  ├─ auth:sanctum               Token autentifikasiya (qorunan route-lar)
  ├─ TenantMiddleware           Multi-tenant context teyin edir
  ├─ IdempotencyMiddleware      Teker sorgu qorumasi
  ├─ AuditMiddleware            Sorgu audit log-u
  ├─ FeatureFlagMiddleware      Feature flag yoxlamasi
  │
  ▼
Controller
```

### Rate Limiting Konfiqurasiyasi

| Ad | Limit | Kimlere | Meqsed |
|---|---|---|---|
| `api` | 60/deq (guest), 120/deq (auth) | Hamiya | Umumi qoruma |
| `login` | 5/deq | IP-ye gore | Brute force qorumasi |
| `register` | 3/deq | IP-ye gore | Spam qorumasi |
| `payment` | 10/deq | User/IP-ye gore | Kart firibgerliyinin qarshisi |
| `orders` | 20/deq | User/IP-ye gore | Sifarish spami |
| `products` | 120/deq | IP-ye gore | Oxuma-agirlikli |

---

## 12. Verilenbazasi

### Multi-Database Arxitekturasi

Her bounded context oz verilenbazasina malikdir (database-per-context):

| Bağlanti Adi | DB Adi | Context | Meqsed |
|---|---|---|---|
| `user_db` | user_db | User | Istifadeci melumatlari |
| `product_db` | product_db | Product | Mehsul melumatlari |
| `order_db` | order_db | Order | Sifarish melumatlari |
| `payment_db` | payment_db | Payment | Odenish melumatlari |

Lokalda SQLite, Docker-de MySQL istifade olunur.

### Migration Siyahisi

| Migration | Cedvel | Meqsed |
|---|---|---|
| `create_users_domain_table` | users | Istifadeci |
| `create_products_table` | products | Mehsul |
| `create_orders_table` | orders | Sifarish |
| `create_payments_table` | payments | Odenish |
| `create_outbox_messages_table` | outbox_messages | Outbox pattern |
| `create_circuit_breaker_table` | circuit_breakers | Circuit breaker veziyyeti |
| `create_event_store_table` | event_store | Event sourcing |
| `create_order_read_model_table` | order_read_models | CQRS read model |
| `create_idempotency_keys_table` | idempotency_keys | Teker sorgu |
| `create_audit_logs_table` | audit_logs | Audit log |
| `create_webhooks_table` | webhooks | Webhook-lar |
| `create_tenants_table` | tenants | Multi-tenancy |
| `add_two_factor_to_users` | users (alter) | 2FA desteyi |
| `create_notification_preferences_table` | notification_preferences | Bildiris tercleri |
| `create_product_images_table` | product_images | Mehsul sekilleri |
| `add_performance_indexes` | (index-ler) | Performans indeksleri |
| `add_performance_indexes` | dead_letter_messages | Dead Letter Queue |
| `create_projector_and_idempotent_tables` | projector_processed_events | Projector idempotentlik |
| `create_projector_and_idempotent_tables` | processed_messages | Idempotent consumer |
| `create_process_manager_states` | process_manager_states | Persistent saga state |
| `create_inbox_messages` | inbox_messages | Inbox pattern |

### Indeks Strategiyasi

```sql
-- Sifarishler: istifadecinin sifarishlerini tarixa gore sirala
INDEX idx_orders_user_date (user_id, created_at)

-- Sifarishler: status filtrI + tarix siralama
INDEX idx_orders_status_date (status, created_at)

-- Odenishler: sifarishe aid odenishleri tap
INDEX idx_payments_order_status (order_id, status)

-- Mehsullar: qiymet araligi ile axtarish
INDEX idx_products_price (price)

-- Mehsullar: tam metn axtarishi
FULLTEXT idx_products_fulltext (name, description)
```

---

## 13. Design Pattern-ler

### Layihede Istifade Olunan Pattern-ler

| # | Pattern | Harada | Fayl |
|---|---|---|---|
| 1 | **Aggregate Root** | Butun Entity-ler | `Shared/Domain/AggregateRoot.php` |
| 2 | **Value Object** | Money, Email, Password, Stock | `*/Domain/ValueObjects/` |
| 3 | **Repository** | Data erishimi | `*/Domain/Repositories/` (interface), `*/Infrastructure/Repositories/` (impl) |
| 4 | **Factory** | Order yaratma | `Order/Domain/Factories/OrderFactory.php` |
| 5 | **Strategy** | Odenish gateway-leri | `Payment/Domain/Strategies/` |
| 6 | **Decorator** | Cache-li repository | `Product/Infrastructure/Repositories/CachedProductRepository.php` |
| 7 | **Observer** | Model lifecycle | `app/Observers/` |
| 8 | **Circuit Breaker** | Xarici servis qorumasi | `Payment/Infrastructure/CircuitBreaker/` |
| 9 | **Adapter** | Stripe inteqrasiyasi | `Payment/Infrastructure/Gateway/StripeGatewayAdapter.php` |
| 10 | **Saga** | Distributed transaction | `Order/Domain/Sagas/OrderSaga.php` |
| 11 | **Specification** | Biznes qayda yoxlamasi | `*/Domain/Specifications/` |
| 12 | **Outbox** | Etibarlı event gonderme | `Order/Infrastructure/Outbox/` |
| 13 | **Anti-Corruption Layer** | Context siniri qorumasi | `Payment/Application/ACL/` |
| 14 | **CQRS** | Oxuma/yazma ayrilmasi | `Shared/Application/Bus/` |
| 15 | **Event Sourcing** | State deyishikliklerini event kimi saxla | `Shared/Infrastructure/EventSourcing/` |
| 16 | **Snapshot** | Event sourcing performansi | `Shared/Infrastructure/EventSourcing/Snapshot*.php` |
| 17 | **Domain Service** | Aggregate-ler arasi biznes mentiqi | `Order/Domain/Services/OrderDomainService.php` |
| 18 | **Event Upcaster** | Event schema versiyalashmasi | `Shared/Infrastructure/EventSourcing/EventUpcaster.php` |
| 19 | **Projector** | Event-den read model-e proyeksiya | `Shared/Infrastructure/EventSourcing/Projector.php` |
| 20 | **Unit of Work** | Atomik aggregate persist + event dispatch | `Shared/Infrastructure/Persistence/UnitOfWork.php` |
| 21 | **Idempotent Consumer** | Exactly-once mesaj emali | `Shared/Infrastructure/Messaging/IdempotentConsumer.php` |
| 22 | **Process Manager** | Chox addimli distributed workflow | `Order/Application/ProcessManagers/OrderFulfillmentProcessManager.php` |
| 23 | **Event Subscriber** | Bir class bir nece event dinleyir | `Shared/Infrastructure/Bus/DomainEventSubscriber.php` |
| 24 | **Retry + Decorator** | Dayaniqli repository wrapper | `Shared/Infrastructure/Resilience/RetryableRepository.php` |
| 25 | **Graceful Degradation** | Servis chokende sistemin islemesi | `Shared/Infrastructure/Resilience/GracefulDegradation.php` |
| 26 | **Backend For Frontend** | Klient tipine gore API formati | `Shared/Infrastructure/Api/BackendForFrontend.php` |
| 27 | **Inbox Pattern** | Gelen mesajlari etibarlI emal | `Shared/Infrastructure/Messaging/InboxStore.php` |
| 28 | **Persistent Saga** | DB-ye persist olunan saga state | `Shared/Infrastructure/EventSourcing/PersistentProcessManager.php` |
| 29 | **Tenant-Aware Repository** | Row-level security | `Shared/Infrastructure/Multitenancy/TenantAwareRepository.php` |
| 30 | **Command Idempotency** | CommandBus seviyyesinde dublikat qorumasi | `Shared/Application/Middleware/IdempotencyMiddleware.php` |
| 31 | **Read Replica** | Master-slave DB ayrilmasi | `Shared/Infrastructure/Persistence/ReadReplicaConnection.php` |

---

## 14. Infrastructure Servisler

### 14.1 Distributed Lock

**Fayl:** `src/Shared/Infrastructure/Locking/DistributedLock.php`

Race condition-larin qarshisini almaq ucun Redis-based distributed lock. Eyni anda iki prosesin eyni resursa muracietini bloklayir.

```php
$lock = app(DistributedLock::class);
$lock->execute('order:create:user:123', function () {
    // Yalniz bir proses bura daxil ola biler
    $this->createOrder($userId);
});
```

### 14.2 Dead Letter Queue (DLQ)

**Fayl:** `src/Shared/Infrastructure/Messaging/DeadLetterQueue.php`

Emal edile bilmeyen mesajlari saxlayan xususi novbe. Retry bitdikden sonra mesaj itmir — DLQ-da saxlanilir.

```php
// Job failed() metodunda:
$dlq->push('payment.process', $payload, $exception, ['order_id' => $orderId]);

// Admin panelde yoxla:
$dlq->getPending();           // Gozleyen mesajlari gor
$dlq->retry('dlq-uuid');      // Yeniden gonder
$dlq->discard('dlq-uuid');    // At
```

### 14.3 Feature Flags

**Fayl:** `src/Shared/Infrastructure/FeatureFlags/FeatureFlag.php`

Deploy etmeden xususiyyetleri aktiv/deaktiv etme mexanizmi.

```php
if ($featureFlag->isEnabled('payment_paypal_enabled')) {
    // PayPal goster
}

// Tehcili sondurme (kill switch):
$featureFlag->disable('payment_paypal_enabled'); // 1 saniyeye sonduruldu
```

### 14.4 Circuit Breaker

**Fayl:** `src/Payment/Infrastructure/CircuitBreaker/CircuitBreaker.php`

Xarici servis chokdukde sistemi qoruyan pattern. 3 state: CLOSED (normal) → OPEN (bloklama) → HALF_OPEN (sinaq).

```php
$circuitBreaker->execute(function () use ($gateway) {
    return $gateway->charge($amount); // Stripe API
});
// 5 ugursuzluqdan sonra → OPEN → 30 san gozle → HALF_OPEN → 1 sinaq sorgusu
```

### 14.5 Structured Logging

**Fayl:** `src/Shared/Infrastructure/Logging/StructuredLogger.php`

JSON formatinda log sistemi. Correlation ID ile bir sorgunun butun loglarini birleshdirmek mumkundur.

```php
StructuredLogger::log('order.created', [
    'order_id' => 'ord_123',
    'user_id' => 42,
    'amount' => 150.00,
]);
// Butun loglar avtomatik correlation_id, timestamp, environment ehtiva edir
```

### 14.6 Webhook Sistemi

**Fayl:** `src/Shared/Infrastructure/Webhook/WebhookService.php`

Event bas verende mushterinin URL-ine POST sorgusu gondermek. HMAC imza ile tehlukesizlik.

- Exponential backoff retry: 30s → 120s → 300s
- 10 ardichil ugursuzluqdan sonra webhook avtomatik deaktiv olur
- Butun gonderimler log-lanir (`WebhookLogModel`)

### 14.7 Multi-tenancy

**Fayl:** `src/Shared/Infrastructure/Multitenancy/`

Bir teqbiqde bir nece kirachi (tenant) desteyi. TenantMiddleware request-den tenant-i teyin edir.

### 14.8 Audit Log

**Fayl:** `src/Shared/Infrastructure/Audit/`

Istifadeci emeliyyatlarini izleyen audit sistemi. Kim, ne zaman, ne etdi.

### 14.9 Search Service

**Fayl:** `src/Shared/Infrastructure/Search/SearchService.php`

Butun entity-lerde (User, Product, Order) eyni anda axtarish. `GET /api/search?q=laptop`

### 14.10 Idempotency (2 qat)

**HTTP qati:** `app/Http/Middleware/IdempotencyMiddleware.php` — `X-Idempotency-Key` header ile HTTP sorqulari ucun.
**Command qati:** `src/Shared/Application/Middleware/IdempotencyMiddleware.php` — CommandBus pipeline-inda queue/cron/saga-dan gelen command-lar ucun.

### 14.11 Unit of Work

**Fayl:** `src/Shared/Infrastructure/Persistence/UnitOfWork.php`

Birden chox aggregate-in atomik persist-i. Transaction-da birdefe yazilir, event-ler yalniz ugurlu transaction-dan sonra dispatch olunur.

### 14.12 Read Replica Connection

**Fayl:** `src/Shared/Infrastructure/Persistence/ReadReplicaConnection.php`

Yazma master DB-ye, oxuma replica DB-ye yonlendirir. Read-your-own-writes (sticky session) desteyi var.

### 14.13 Retryable Repository

**Fayl:** `src/Shared/Infrastructure/Resilience/RetryableRepository.php`

Deadlock, timeout, connection lost kimi muveqqeti DB xetalarinda avtomatik retry. Exponential backoff + jitter.

### 14.14 Graceful Degradation

**Fayl:** `src/Shared/Infrastructure/Resilience/GracefulDegradation.php`

Xarici servis chokende fallback/default strategiya ile ishlemeye davam etme. Circuit Breaker ile birlikde ishleyir.

### 14.15 Backend For Frontend (BFF)

**Fayl:** `src/Shared/Infrastructure/Api/BackendForFrontend.php`

Mobil/Web/Admin klient tiplerine gore ferqlI API response formati. `X-Client-Type` header ile mueyyen olunur.

### 14.16 Inbox Pattern

**Fayl:** `src/Shared/Infrastructure/Messaging/InboxStore.php`

Outbox-in eksi — gelen mesajlari evvelce DB-ye yazib, sonra emal edir. Dublikat qorumasi + retry + monitoring.

### 14.17 Persistent Process Manager

**Fayl:** `src/Shared/Infrastructure/EventSourcing/PersistentProcessManager.php`

Saga/Process Manager state-ini DB-ye persist edir. Server restart olsa bele proses davam ede biler. Timeout ashkarlama desteyi var.

### 14.18 Event Upcaster

**Fayl:** `src/Shared/Infrastructure/EventSourcing/EventUpcaster.php` + `ConcreteEventUpcaster.php`

Event schema versiyalashmasi — kohne event-leri oxuyanda yeni formata on-the-fly chevirir. DB migration-in event versiyasi.

### 14.19 Tenant-Aware Repository

**Fayl:** `src/Shared/Infrastructure/Multitenancy/TenantAwareRepository.php`

Row-level security — her sorguya avtomatik tenant filteri elave edir. Admin mode ile filtersiz ishleme imkani var.

---

## 15. Docker ve Deploy

### Docker Servisleri

```yaml
services:
  app:        # PHP 8.3-FPM — Laravel teqbiqi
  nginx:      # Reverse proxy (port 8080)
  mysql:      # MySQL 8.0 (port 3306) — 4 DB yaradir
  rabbitmq:   # Message broker (port 5672, UI: 15672)
  redis:      # Cache + Lock + Queue (port 6379)
  mailpit:    # Email test (SMTP: 1025, UI: 8025)
```

### Konteyner Baslangic Prosesi (entrypoint.sh)

```
1. MySQL hazirliq gozlemesi (wait_for_mysql)
2. Composer install
3. .env faylini .env.docker-dan kopyala
4. APP_KEY generate et (eger yoxdursa)
5. Cache temizle
6. Migration ishlet
7. PHP-FPM bashla
```

### Nginx Konfiqurasiyasi

```
- Port: 80
- Root: /var/www/html/public
- try_files: $uri $uri/ /index.php (Laravel routing)
- FastCGI: app:9000
- Statik fayl cache: 1 ay
```

### Kubernetes Desteyi

Health check endpoint-leri Kubernetes probes ucun hazirdir:

```yaml
# Liveness: Proses ishleyir?
livenessProbe:
  httpGet:
    path: /api/health/live
    port: 80
  periodSeconds: 10

# Readiness: Traffic qebul ede biler?
readinessProbe:
  httpGet:
    path: /api/health/ready
    port: 80
  periodSeconds: 5
```

---

## 16. Testler

### Test Strukturu

```
tests/
├── Feature/              # API endpoint testleri
│   ├── OrderApiTest.php
│   ├── PaymentApiTest.php
│   ├── ProductApiTest.php
│   └── UserApiTest.php
├── Integration/          # Repository/service inteqrasiya testleri
│   ├── EloquentProductRepositoryTest.php
│   └── EloquentUserRepositoryTest.php
└── Unit/                 # Domain mentiq testleri
    ├── Order/
    │   ├── OrderStatusTest.php
    │   └── OrderCanBeCancelledSpecTest.php
    ├── Product/
    │   ├── MoneyValueObjectTest.php
    │   └── StockValueObjectTest.php
    ├── User/
    │   └── EmailValueObjectTest.php
    └── Shared/
        └── SpecificationTest.php
```

### Test Ishetme

```bash
# Butun testler
php artisan test

# Yalniz unit testler
php artisan test --testsuite=Unit

# Yalniz feature testler
php artisan test --testsuite=Feature

# Konkret test faylI
php artisan test tests/Unit/Product/MoneyValueObjectTest.php
```

---

## 17. Error Handling

### Qlobal Exception Handling

`bootstrap/app.php`-de butun exception-lar merkezi olaraq idarə olunur:

| Exception | HTTP Status | Ne zaman |
|---|---|---|
| `EntityNotFoundException` | 404 | Domain entity tapilmadi |
| `ValidationException` | 400 | Validasiya ugursuz |
| `DomainException` | 422 | Biznes qaydasI pozuldu |
| `AuthenticationException` | 401 | Token etibarsiz |
| `AuthorizationException` | 403 | Icaze yoxdur |
| `ModelNotFoundException` | 404 | Eloquent model tapilmadi |
| `NotFoundHttpException` | 404 | Route tapilmadi |
| `TooManyRequestsHttpException` | 429 | Rate limit ashildi |
| `CircuitBreakerOpenException` | 503 | Xarici servis elchatmaz |
| `Throwable` | 500 | Gozlenilmeyen xeta |

### Response Formati

Butun API cavablari `ApiResponse` resursu ile eyni formatda qaytarilir:

```json
{
    "success": false,
    "message": "Sifarish tapilmadi.",
    "data": null,
    "errors": null,
    "meta": {
        "timestamp": "2024-01-15T14:30:22Z",
        "version": "v1"
    }
}
```

---

## 18. Yeni Feature Elave Etme Rehberi

### Addim 1: Bounded Context Sec

Yeni funksiya hansı context-e aiddir? Movcud contextlere uygun gelmirse yeni context yarat.

### Addim 2: Domain Layer (src/Context/Domain/)

```
1. Entity yaradirsan? → Domain/Entities/ qovlugunda
2. Value Object? → Domain/ValueObjects/
3. Domain Event? → Domain/Events/
4. Repository Interface? → Domain/Repositories/
5. Spesifikasiya? → Domain/Specifications/
```

**Qayda:** Domain layer-de hec bir framework kodu olmamalidir! Yalniz saf PHP.

### Addim 3: Application Layer (src/Context/Application/)

```
1. Yazma emeliyyati? → Commands/FeatureName/FeatureNameCommand.php + FeatureNameHandler.php
2. Oxuma emeliyyati? → Queries/FeatureName/FeatureNameQuery.php + FeatureNameHandler.php
3. DTO? → DTOs/FeatureNameDTO.php
```

### Addim 4: Infrastructure Layer (src/Context/Infrastructure/)

```
1. Eloquent Model → Models/
2. Repository Implementation → Repositories/
3. Xarici servis adapter → Adapters/
```

### Addim 5: Controller + Route

```php
// app/Http/Controllers/FeatureController.php
class FeatureController extends Controller {
    public function store(Request $request): JsonResponse {
        $command = new CreateFeatureCommand(...);
        $result = $this->commandBus->dispatch($command);
        return ApiResponse::success($result, 201);
    }
}

// routes/api.php
Route::post('/features', [FeatureController::class, 'store'])->middleware('auth:sanctum');
```

### Addim 6: Migration

```bash
php artisan make:migration create_features_table
```

### Addim 7: Test yaz

```
tests/Unit/Context/       → Domain mentiq testleri
tests/Feature/            → API endpoint testleri
tests/Integration/        → Repository testleri
```

---

## 19. FAQ — Tez-Tez Verilen Suallar

### "Yeni bounded context nece yaradilir?"

1. `src/ContextName/Domain/` — Entity, ValueObject, Event, Repository Interface
2. `src/ContextName/Application/` — Command, Query, Handler, DTO
3. `src/ContextName/Infrastructure/` — Model, Repository Impl, Provider
4. `ServiceProvider` yarat ve `DomainServiceProvider`-de qeydiyyat et

### "Command ile Query arasinda ferq nedir?"

- **Command:** Sistemi deyishdirir (create, update, delete). Neticesi DTO-dur.
- **Query:** Sistemi deyishdirmir (read). Neticesi DTO-dur.
- Command middleware pipeline-dan kechir (logging, validation, transaction).
- Query birbaşa handler-e gedir.

### "Domain Event ile Integration Event ferqi?"

- **Domain Event:** Eyni context daxilinde sinxron ishleyir. Meselen: `OrderCreatedEvent` → sifariş yaradilanda emaili gonder.
- **Integration Event:** Basqa context-e asinxron (RabbitMQ ile) gonderilir. Meselen: `LowStockIntegrationEvent` → stok azalanda notification gonder.

### "Niye her context-in oz DB-si var?"

Data izolasiyasi. Bir context-in DB-si chokse, diger context-ler ishleyir. Gelecekde her context-i ayri microservice-e chevirmek asan olur.

### "Circuit Breaker ne zaman ishleyir?"

Xarici API (Stripe, PayPal) ardichil 5 defe ugursuz olduqda OPEN state-e kechir. 30 saniye hec bir sorgu gondermir. Sonra 1 sinaq sorgusu gondererek yoxlayir.

### "Feature flag nece elave edilir?"

1. `config/features.php`-ye defolt deyer elave et: `'new_feature' => false`
2. Kodda yoxla: `$featureFlag->isEnabled('new_feature')`
3. Deploy etmeden aktiv et: `$featureFlag->enable('new_feature')` (Redis cache ile)

### "Webhook nece ishleyir?"

1. Mushteri webhook qeydiyyat edir: URL + dinlediyi event-ler
2. Event bas verir → `WebhookService::dispatch()` chaqirilir
3. Uygun webhook-lar tapilir → `SendWebhookJob` dispatch olunur (async)
4. Job POST sorgusu gonderir + HMAC imza ile tehlukesizlik
5. Ugursuz olsa exponential backoff ile retry: 30s → 120s → 300s

### "Artisan komandalar?"

```bash
# RabbitMQ mesajlarini dinle
php artisan rabbitmq:consume payment_queue

# Outbox mesajlarini gonder
php artisan outbox:publish

# Read model-i yeniden qur
php artisan projection:rebuild OrderReadModel

# Ugursuz job-lari izle
php artisan jobs:monitor

# Graceful worker (SIGTERM destekli)
php artisan worker:graceful --sleep=3 --max-jobs=100
```

### "Process Manager ile Saga ferqi nedir?"

- **Saga:** Reaktiv — event gelir, reaksiya verir. State-i yoxdur. Yalniz kompensasiya (undo) edir.
- **Process Manager:** Proaktiv — prosesin harada oldugunu bilir, novbeti addimi mueyyen edir. State DB-de saxlanilir.
- Saga = Domino dashlari (biri yixilir → novbeti avtomatik). Process Manager = Dirijor (orkestri idare edir).

### "Event Upcaster ne vaxt lazimdir?"

Event Store-da kohne format event-ler olanda. Meselen 6 ay evvel OrderCreatedEvent-de `currency` sahesi yox idi. Indi kod `currency` gozleyir. Upcaster kohne event-i oxuyanda avtomatik `currency: 'AZN'` elave edir. Event Store-dakI data deyishmir — yalniz oxuyanda chevirilir.

### "Inbox ve Outbox ferqi nedir?"

- **Outbox:** Gonderici teref. "Mesaji evvelce DB-ye yaz, sonra RabbitMQ-ya gonder." Mesaj itmir.
- **Inbox:** Qebulcu teref. "Mesaji evvelce DB-ye yaz, sonra emal et." Dublikat emal olunmur.
- Ikisi birlikde = "Transactional Messaging" — tam etibarlI mesajlashma.

### "Graceful Degradation nece ishleyir?"

Stripe chokdu → butun sayt 500 qaytarir (PIS). Graceful Degradation ile: Stripe chokdu → odenish deaktiv, amma mehsullari gormek, sebete elave etmek mumkundur. `GracefulDegradation::withFallback()` primary ugursuz olsa fallback-i chaqirir.

### "Command Idempotency vs HTTP Idempotency ferqi?"

- **HTTP Idempotency:** `X-Idempotency-Key` header ile HTTP sorqularini qoruyur. Yalniz controller-e chatmamish dublikati bloklayir.
- **Command Idempotency:** CommandBus pipeline-inda ishleyir. Queue worker, cron job, saga-dan gelen command-lari da qoruyur. Her iki qat lazimdir chunku command-lar HTTP-den bashqa yerlerden de gele biler.

### "Layihede nece test yazilir?"

```bash
# Yeni test yarat
php artisan make:test OrderApiTest        # Feature test
php artisan make:test MoneyTest --unit    # Unit test

# Testleri ishlet
php artisan test
```

---

## Diaqramlar

### Request Lifecycle

```
HTTP Request
    │
    ▼
Nginx (port 8080)
    │
    ▼
PHP-FPM (port 9000)
    │
    ▼
Laravel Middleware Pipeline
    │
    ▼
Controller
    │
    ├── Command? → CommandBus → Middleware → Handler → Domain → Repository → DB
    │                                                      └──→ EventDispatcher
    │                                                              ├── Listener (sinxron)
    │                                                              └── RabbitMQ (asinxron)
    │
    └── Query? → QueryBus → Handler → Repository/ReadModel → DB
    │
    ▼
JSON Response
```

### Event Flow

```
User Action → Controller → Command → Handler → Aggregate
                                                   │
                                          recordEvent(DomainEvent)
                                                   │
                                          Repository.save()
                                                   │
                                          EventDispatcher.dispatch()
                                                   │
                              ┌────────────────────┼────────────────────┐
                              ▼                    ▼                    ▼
                     Laravel Listener       RabbitMQ Publisher    Outbox Table
                     (sinxron)              (asinxron)           (etibarlI)
                              │                    │                    │
                              ▼                    ▼                    ▼
                     Email/Job/etc.        Diger Context-ler    PublishOutboxCmd
```

---

## 20. Senior-Level Arxitektural Pattern-ler

Bu bolme layiheye elave olunmus qabaqcil arxitektural pattern-leri etraflI izah edir.

### 20.1 Domain Service

**Fayl:** `src/Order/Domain/Services/OrderDomainService.php`

Hec bir aggregate-e aid olmayan, amma domain-e aid olan biznes mentiqi. Meselen: endirim hesablama bir nece aggregate-den (User VIP statusu + Order meblegi + kampaniya) melumat teleb edir — hec biri tek bashina bunu ede bilmez.

```php
$pricing = $domainService->calculateOrderPrice(
    subtotal: 150000,      // 1500 AZN
    isVipCustomer: true,
    orderCount: 12,
);
// Result: 25% endirim (VIP 15% + loyalty 5% + bulk 10%, max 25%)
```

**Domain Service vs Application Service:**
- Domain Service: Biznes qaydasi (endirim hesablama). Repository chaqirmir.
- Application Service: Use case orkestrasiyasi (command handle etme). Repository chaqirir.

### 20.2 Process Manager

**Fayl:** `src/Order/Application/ProcessManagers/OrderFulfillmentProcessManager.php`

Chox addimli distributed workflow koordinatoru. Saga-dan gucludur — state saxlayir, timeout idare edir, novbeti addimi mueyyen edir.

**State Machine:**
```
INITIATED → PAYMENT_PENDING → PAYMENT_COMPLETED → STOCK_RESERVED → SHIPMENT_CREATED → COMPLETED
                    │
                    └──→ COMPENSATING → FAILED (odenish ugursuz olsa)
```

**Saga vs Process Manager:**
- Saga: Reaktiv — yalniz event-lere reaksiya verir, state-i yoxdur.
- Process Manager: Proaktiv — prosesin harada oldugunu bilir, novbeti addimi mueyyen edir.

### 20.3 Persistent Process Manager (DB-Persisted Saga)

**Fayl:** `src/Shared/Infrastructure/EventSourcing/PersistentProcessManager.php`

Process Manager state-ini DB-ye yazir. Server restart olsa bele proses davam ede biler.

```php
$pm = app(PersistentProcessManager::class);
$state = $pm->loadOrCreate('order-123', OrderFulfillment::class);
$pm->transition('order-123', OrderFulfillment::class, 'payment_completed', $data, $steps);

// Timeout olmus prosesleri tap:
$timedOut = $pm->findTimedOut(timeoutMinutes: 30);
```

### 20.4 Event Subscriber

**Fayl:** `src/Shared/Infrastructure/Bus/DomainEventSubscriber.php`
**Numune:** `src/Order/Application/Subscribers/OrderLifecycleSubscriber.php`

Bir class bir nece event dinleyir. Listener-den ferqi: listener tek event ucun, subscriber bir nece elaqeli event ucun.

```php
class OrderLifecycleSubscriber extends DomainEventSubscriber {
    protected function subscribedEvents(): array {
        return [
            OrderCreatedEvent::class   => 'onOrderCreated',
            OrderPaidEvent::class      => 'onOrderPaid',
            OrderCancelledEvent::class => 'onOrderCancelled',
        ];
    }
}
```

### 20.5 Read Replica Connection (CQRS at DB Level)

**Fayl:** `src/Shared/Infrastructure/Persistence/ReadReplicaConnection.php`

Yazma master-e, oxuma replica-ya yonlendirir. CQRS-i infrastructure seviyyesinde tamamlayir.

```php
$conn = app(ReadReplicaConnection::class);
$conn->forWrite()->table('orders')->insert([...]); // Master-e
$conn->forRead()->table('orders')->get();           // Replica-dan
```

**Read-your-own-writes:** Bu request-de yazma oldusa, oxuma da master-den olur (sticky session).

### 20.6 Backend For Frontend (BFF)

**Fayl:** `src/Shared/Infrastructure/Api/BackendForFrontend.php`

Her klient tipi ucun ayri API response formati:
- **Mobil:** Minimal data — id, status, total (3 sahe). Pagination: 10.
- **Web:** Orta detallar — status_label, currency, dates. Pagination: 20.
- **Admin:** Maksimal — user_id, payment_status, internal_notes. Pagination: 50.

```php
$clientType = BackendForFrontend::resolveClientType($request); // X-Client-Type header-den
$formatted = $bff->formatOrderList($orders, $clientType);
```

### 20.7 Inbox Pattern

**Fayl:** `src/Shared/Infrastructure/Messaging/InboxStore.php`

Outbox-in eksi. Gelen mesajlari evvelce DB-ye yazib, sonra emal edir.

```
Outbox (gonderici): Handler → DB + Outbox (1 transaction) → Publisher → RabbitMQ
Inbox (qebulcu):    RabbitMQ → Consumer → Inbox DB (ack) → InboxProcessor → Handler
```

**Outbox + Inbox = Transactional Messaging** — distributed sistemlerde etibarlI mesajlashmanin tam implementasiyasi.

### 20.8 Graceful Degradation

**Fayl:** `src/Shared/Infrastructure/Resilience/GracefulDegradation.php`

Xarici servis chokende butun sistemin chokmemsini temin edir. Circuit Breaker xetani ASHKAR edir, Graceful Degradation ona REAKSİYA verir.

```php
$result = GracefulDegradation::withFallback(
    'stripe',
    fn() => $stripe->charge($amount),           // Primary: Stripe-a sor
    fn() => $this->queueForLater($orderId),      // Fallback: queue-ya yaz, sonra cehd et
);
```

**Strategiyalar:** Fallback (evezedici cavab), Default (sabit deyer), Skip (deaktiv et), Queue (sonraya saxla).

### 20.9 Tenant-Aware Repository

**Fayl:** `src/Shared/Infrastructure/Multitenancy/TenantAwareRepository.php`

Her sorguya avtomatik `WHERE tenant_id = ?` elave edir. Developer unuda bilmez — sistem avtomatik filtrleyir.

```php
// Normal sorgu — avtomatik tenant filteri:
TenantAwareRepository::applyTenantScope(Product::query())->get();

// Admin emeliyyati — filtersiz:
TenantAwareRepository::withoutTenant(fn() => Order::all());

// Bashqa tenant kimi:
TenantAwareRepository::asTenant($otherTenant, fn() => Order::count());
```

**Multi-tenancy tipleri:**
| Tip | Izah | Izolyasiya | Qiymet |
|---|---|---|---|
| Shared DB + tenant_id | Bu layihe | Ashagi | Ucuz |
| Separate Schema | Her tenant ayri schema | Orta | Orta |
| Separate DB | Her tenant ayri DB | Yuksek | Bahalı |

### 20.10 Command Idempotency Middleware

**Fayl:** `src/Shared/Application/Middleware/IdempotencyMiddleware.php`

HTTP IdempotencyMiddleware yalniz HTTP sorqulari qoruyur. Bu middleware CommandBus pipeline-inda isleyir — queue, cron, saga-dan gelen command-lari da qoruyur.

```php
class ProcessPaymentCommand implements Command {
    public function idempotencyKey(): string {
        return "payment:{$this->orderId}:{$this->amount}";
    }
}
// Eyni orderId + amount ile ikinci command avtomatik bloklanir
```

### 20.11 Retryable Repository

**Fayl:** `src/Shared/Infrastructure/Resilience/RetryableRepository.php`

DB connection timeout, deadlock kimi muveqqeti xetalarda avtomatik retry edir.

```php
$product = RetryableRepository::execute(
    fn() => $this->productRepo->findById($id),
    maxAttempts: 3,
    baseDelayMs: 100,   // 100ms → 200ms → 400ms (exponential backoff)
    useJitter: true,     // Thundering herd qorumasi
);
```

**Transient vs Permanent xeta:** Yalniz muveqqeti xetalari (deadlock, timeout, connection lost) retry edir. Permanent xetalari (syntax error, table not found) derhal atir.

### 20.12 Unit of Work

**Fayl:** `src/Shared/Infrastructure/Persistence/UnitOfWork.php`

Birdən chox aggregate-in atomik persist-i + event dispatch koordinasiyasi.

```php
$unitOfWork->track($order, fn($agg) => $orderRepo->save($agg));
$unitOfWork->track($product, fn($agg) => $productRepo->save($agg));
$unitOfWork->commit(); // 1 transaction-da her ikisini yaz, sonra event-leri dispatch et
```

**Niye event-ler transaction-dan SONRA dispatch olunur?** Transaction rollback olsa, xarici servise gonderilmish mesaji geri ala bilmerik (dual-write problem).

---

*Bu dokumentasiya layihe ile birlikde yenilenmeli ve aktuallasdirilmalidir.*
