# Domain-Driven Design (Lead)

DDD software-i əsas business domain ətrafında modelləşdirməyə fokuslanır.
Developer-lərlə domain mütəxəssisləri arasında ortaq dil (Ubiquitous Language) istifadə edir.

**Əsas anlayışlar:**
- **Bounded Context** — Modelin təyin olunduğu və tətbiq olunduğu sərhəd
- **Entity** — Zaman keçdikcə identity-si saxlanan obyekt
- **Value Object** — Öz atribut-ları ilə təyin olunan dəyişməz (immutable) obyekt
- **Aggregate** — Bir vahid kimi qəbul edilən entity/value object klasteri
- **Aggregate Root** — Aggregate-ə giriş nöqtəsi
- **Repository** — Aggregate-lər üçün data persistence-ni abstract edir
- **Domain Service** — Hər hansı entity-yə aid olmayan business logic
- **Domain Event** — Domain-də baş vermiş nəsə
- **Factory** — Mürəkkəb aggregate-lər yaradır
- **Specification** — Query kriteriyalarını kapsullaşdırır

---

## Laravel

```
app/
├── Domain/                                 # Core domain layer
│   ├── Identity/                           # Bounded Context: Identity
│   │   ├── Aggregate/
│   │   │   ├── User/
│   │   │   │   ├── User.php               # Aggregate Root
│   │   │   │   ├── UserProfile.php        # Entity within aggregate
│   │   │   │   └── UserFactory.php
│   │   │   └── Role/
│   │   │       ├── Role.php
│   │   │       └── Permission.php
│   │   ├── ValueObject/
│   │   │   ├── Email.php
│   │   │   ├── Password.php
│   │   │   ├── FullName.php
│   │   │   └── UserId.php
│   │   ├── Repository/
│   │   │   ├── UserRepositoryInterface.php
│   │   │   └── RoleRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── PasswordHashingService.php
│   │   │   └── UserUniquenessChecker.php
│   │   ├── Event/
│   │   │   ├── UserRegistered.php
│   │   │   └── UserRoleAssigned.php
│   │   ├── Specification/
│   │   │   ├── ActiveUserSpecification.php
│   │   │   └── UserByEmailSpecification.php
│   │   ├── Exception/
│   │   │   ├── UserNotFoundException.php
│   │   │   ├── DuplicateEmailException.php
│   │   │   └── InvalidCredentialsException.php
│   │   └── Policy/
│   │       └── UserRegistrationPolicy.php
│   │
│   ├── Catalog/                            # Bounded Context: Catalog
│   │   ├── Aggregate/
│   │   │   ├── Product/
│   │   │   │   ├── Product.php            # Aggregate Root
│   │   │   │   ├── ProductVariant.php
│   │   │   │   └── ProductImage.php
│   │   │   └── Category/
│   │   │       ├── Category.php
│   │   │       └── CategoryTree.php
│   │   ├── ValueObject/
│   │   │   ├── ProductId.php
│   │   │   ├── SKU.php
│   │   │   ├── Price.php
│   │   │   └── Money.php
│   │   ├── Repository/
│   │   │   ├── ProductRepositoryInterface.php
│   │   │   └── CategoryRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── PricingService.php
│   │   │   └── ProductSearchService.php
│   │   └── Event/
│   │       ├── ProductCreated.php
│   │       └── ProductPriceChanged.php
│   │
│   ├── Ordering/                           # Bounded Context: Ordering
│   │   ├── Aggregate/
│   │   │   └── Order/
│   │   │       ├── Order.php              # Aggregate Root
│   │   │       ├── OrderLine.php
│   │   │       └── OrderFactory.php
│   │   ├── ValueObject/
│   │   │   ├── OrderId.php
│   │   │   ├── OrderStatus.php
│   │   │   ├── Address.php
│   │   │   └── Money.php
│   │   ├── Repository/
│   │   │   └── OrderRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── OrderTotalCalculator.php
│   │   │   └── DiscountService.php
│   │   └── Event/
│   │       ├── OrderPlaced.php
│   │       ├── OrderConfirmed.php
│   │       └── OrderCancelled.php
│   │
│   └── Shared/                             # Shared Kernel
│       ├── AggregateRoot.php
│       ├── Entity.php
│       ├── ValueObject.php
│       ├── DomainEvent.php
│       ├── DomainException.php
│       ├── Specification.php
│       └── Collection.php
│
├── Application/                            # Application layer
│   ├── Identity/
│   │   ├── Command/
│   │   │   ├── RegisterUser/
│   │   │   │   ├── RegisterUserCommand.php
│   │   │   │   └── RegisterUserHandler.php
│   │   │   └── AssignRole/
│   │   │       ├── AssignRoleCommand.php
│   │   │       └── AssignRoleHandler.php
│   │   ├── Query/
│   │   │   └── GetUser/
│   │   │       ├── GetUserQuery.php
│   │   │       └── GetUserHandler.php
│   │   └── EventHandler/
│   │       └── SendWelcomeEmailOnUserRegistered.php
│   ├── Catalog/
│   │   ├── Command/
│   │   │   └── CreateProduct/
│   │   │       ├── CreateProductCommand.php
│   │   │       └── CreateProductHandler.php
│   │   └── Query/
│   │       └── SearchProducts/
│   │           ├── SearchProductsQuery.php
│   │           └── SearchProductsHandler.php
│   └── Ordering/
│       ├── Command/
│       │   └── PlaceOrder/
│       │       ├── PlaceOrderCommand.php
│       │       └── PlaceOrderHandler.php
│       └── Query/
│           └── GetOrder/
│               ├── GetOrderQuery.php
│               └── GetOrderHandler.php
│
├── Infrastructure/
│   ├── Identity/
│   │   ├── Persistence/
│   │   │   ├── EloquentUserRepository.php
│   │   │   └── EloquentRoleRepository.php
│   │   └── Service/
│   │       └── BcryptPasswordHashingService.php
│   ├── Catalog/
│   │   └── Persistence/
│   │       ├── EloquentProductRepository.php
│   │       └── EloquentCategoryRepository.php
│   ├── Ordering/
│   │   └── Persistence/
│   │       └── EloquentOrderRepository.php
│   ├── Shared/
│   │   ├── EventDispatcher.php
│   │   └── UnitOfWork.php
│   └── Providers/
│       ├── IdentityServiceProvider.php
│       ├── CatalogServiceProvider.php
│       └── OrderingServiceProvider.php
│
└── Interfaces/
    └── Http/
        ├── Controllers/
        │   ├── Identity/
        │   │   └── UserController.php
        │   ├── Catalog/
        │   │   └── ProductController.php
        │   └── Ordering/
        │       └── OrderController.php
        ├── Requests/
        └── Resources/
```

---

## Symfony

```
src/
├── Domain/
│   ├── Identity/                           # Bounded Context
│   │   ├── Aggregate/
│   │   │   └── User/
│   │   │       ├── User.php
│   │   │       ├── UserProfile.php
│   │   │       └── UserFactory.php
│   │   ├── ValueObject/
│   │   │   ├── Email.php
│   │   │   ├── Password.php
│   │   │   └── UserId.php
│   │   ├── Repository/
│   │   │   └── UserRepositoryInterface.php
│   │   ├── Service/
│   │   │   └── PasswordHashingService.php
│   │   ├── Event/
│   │   │   └── UserRegisteredEvent.php
│   │   ├── Specification/
│   │   │   └── ActiveUserSpecification.php
│   │   └── Exception/
│   │       └── UserNotFoundException.php
│   │
│   ├── Catalog/                            # Bounded Context
│   │   ├── Aggregate/
│   │   │   └── Product/
│   │   │       ├── Product.php
│   │   │       └── ProductVariant.php
│   │   ├── ValueObject/
│   │   │   ├── ProductId.php
│   │   │   ├── SKU.php
│   │   │   └── Money.php
│   │   ├── Repository/
│   │   │   └── ProductRepositoryInterface.php
│   │   ├── Service/
│   │   │   └── PricingService.php
│   │   └── Event/
│   │       └── ProductCreatedEvent.php
│   │
│   ├── Ordering/                           # Bounded Context
│   │   ├── Aggregate/
│   │   │   └── Order/
│   │   │       ├── Order.php
│   │   │       └── OrderLine.php
│   │   ├── ValueObject/
│   │   │   ├── OrderId.php
│   │   │   ├── OrderStatus.php
│   │   │   └── Address.php
│   │   ├── Repository/
│   │   │   └── OrderRepositoryInterface.php
│   │   ├── Service/
│   │   │   └── OrderTotalCalculator.php
│   │   └── Event/
│   │       ├── OrderPlacedEvent.php
│   │       └── OrderCancelledEvent.php
│   │
│   └── Shared/
│       ├── AggregateRoot.php
│       ├── Entity.php
│       ├── ValueObject.php
│       └── DomainEvent.php
│
├── Application/
│   ├── Identity/
│   │   ├── Command/
│   │   │   └── RegisterUser/
│   │   │       ├── RegisterUserCommand.php
│   │   │       └── RegisterUserCommandHandler.php
│   │   ├── Query/
│   │   │   └── GetUser/
│   │   │       ├── GetUserQuery.php
│   │   │       └── GetUserQueryHandler.php
│   │   └── EventHandler/
│   │       └── SendWelcomeEmailOnUserRegistered.php
│   ├── Catalog/
│   │   ├── Command/
│   │   └── Query/
│   └── Ordering/
│       ├── Command/
│       └── Query/
│
├── Infrastructure/
│   ├── Identity/
│   │   ├── Persistence/
│   │   │   ├── DoctrineUserRepository.php
│   │   │   └── Mapping/
│   │   │       └── User.orm.xml
│   │   └── Service/
│   │       └── BcryptPasswordHashingService.php
│   ├── Catalog/
│   │   └── Persistence/
│   │       ├── DoctrineProductRepository.php
│   │       └── Mapping/
│   ├── Ordering/
│   │   └── Persistence/
│   │       ├── DoctrineOrderRepository.php
│   │       └── Mapping/
│   └── Shared/
│       ├── Messenger/
│       │   ├── CommandBus.php
│       │   └── QueryBus.php
│       └── Doctrine/
│           └── DoctrineUnitOfWork.php
│
└── UI/
    └── Http/
        └── Controller/
            ├── Identity/
            │   └── UserController.php
            ├── Catalog/
            │   └── ProductController.php
            └── Ordering/
                └── OrderController.php

config/
├── packages/
└── services.yaml
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── domain/
│   ├── identity/                           # Bounded Context
│   │   ├── aggregate/
│   │   │   ├── User.java                  # Aggregate Root
│   │   │   ├── UserProfile.java
│   │   │   └── Role.java
│   │   ├── valueobject/
│   │   │   ├── Email.java
│   │   │   ├── Password.java
│   │   │   ├── FullName.java
│   │   │   └── UserId.java
│   │   ├── repository/
│   │   │   └── UserRepository.java        # Interface
│   │   ├── service/
│   │   │   └── PasswordHashingService.java
│   │   ├── event/
│   │   │   ├── UserRegisteredEvent.java
│   │   │   └── UserRoleAssignedEvent.java
│   │   ├── specification/
│   │   │   └── ActiveUserSpecification.java
│   │   ├── factory/
│   │   │   └── UserFactory.java
│   │   └── exception/
│   │       ├── UserNotFoundException.java
│   │       └── DuplicateEmailException.java
│   │
│   ├── catalog/                            # Bounded Context
│   │   ├── aggregate/
│   │   │   ├── Product.java
│   │   │   └── ProductVariant.java
│   │   ├── valueobject/
│   │   │   ├── ProductId.java
│   │   │   ├── SKU.java
│   │   │   └── Money.java
│   │   ├── repository/
│   │   │   └── ProductRepository.java
│   │   ├── service/
│   │   │   └── PricingService.java
│   │   └── event/
│   │       └── ProductCreatedEvent.java
│   │
│   ├── ordering/                           # Bounded Context
│   │   ├── aggregate/
│   │   │   ├── Order.java
│   │   │   └── OrderLine.java
│   │   ├── valueobject/
│   │   │   ├── OrderId.java
│   │   │   ├── OrderStatus.java
│   │   │   └── Address.java
│   │   ├── repository/
│   │   │   └── OrderRepository.java
│   │   ├── service/
│   │   │   └── OrderTotalCalculator.java
│   │   └── event/
│   │       └── OrderPlacedEvent.java
│   │
│   └── shared/
│       ├── AggregateRoot.java
│       ├── Entity.java
│       ├── ValueObject.java
│       ├── DomainEvent.java
│       └── Specification.java
│
├── application/
│   ├── identity/
│   │   ├── command/
│   │   │   ├── RegisterUserCommand.java
│   │   │   └── RegisterUserCommandHandler.java
│   │   ├── query/
│   │   │   ├── GetUserQuery.java
│   │   │   └── GetUserQueryHandler.java
│   │   └── eventhandler/
│   │       └── SendWelcomeEmailHandler.java
│   ├── catalog/
│   │   ├── command/
│   │   └── query/
│   └── ordering/
│       ├── command/
│       └── query/
│
├── infrastructure/
│   ├── identity/
│   │   ├── persistence/
│   │   │   ├── JpaUserRepository.java
│   │   │   └── entity/
│   │   │       └── UserJpaEntity.java
│   │   └── service/
│   │       └── BcryptPasswordHashingService.java
│   ├── catalog/
│   │   └── persistence/
│   │       └── JpaProductRepository.java
│   ├── ordering/
│   │   └── persistence/
│   │       └── JpaOrderRepository.java
│   └── shared/
│       ├── config/
│       │   └── BeanConfig.java
│       └── event/
│           └── SpringEventPublisher.java
│
└── interfaces/
    └── rest/
        ├── identity/
        │   └── UserController.java
        ├── catalog/
        │   └── ProductController.java
        └── ordering/
            └── OrderController.java
```

---

## Golang

```
project/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── domain/
│   │   ├── identity/                       # Bounded Context
│   │   │   ├── aggregate/
│   │   │   │   ├── user.go                # Aggregate Root
│   │   │   │   ├── user_profile.go
│   │   │   │   └── role.go
│   │   │   ├── valueobject/
│   │   │   │   ├── email.go
│   │   │   │   ├── password.go
│   │   │   │   └── user_id.go
│   │   │   ├── repository/
│   │   │   │   └── user_repository.go     # Interface
│   │   │   ├── service/
│   │   │   │   └── password_hashing.go
│   │   │   ├── event/
│   │   │   │   └── user_registered.go
│   │   │   ├── specification/
│   │   │   │   └── active_user.go
│   │   │   └── errors.go
│   │   │
│   │   ├── catalog/                        # Bounded Context
│   │   │   ├── aggregate/
│   │   │   │   ├── product.go
│   │   │   │   └── product_variant.go
│   │   │   ├── valueobject/
│   │   │   │   ├── product_id.go
│   │   │   │   ├── sku.go
│   │   │   │   └── money.go
│   │   │   ├── repository/
│   │   │   │   └── product_repository.go
│   │   │   ├── service/
│   │   │   │   └── pricing.go
│   │   │   └── event/
│   │   │       └── product_created.go
│   │   │
│   │   ├── ordering/                       # Bounded Context
│   │   │   ├── aggregate/
│   │   │   │   ├── order.go
│   │   │   │   └── order_line.go
│   │   │   ├── valueobject/
│   │   │   │   ├── order_id.go
│   │   │   │   ├── order_status.go
│   │   │   │   └── address.go
│   │   │   ├── repository/
│   │   │   │   └── order_repository.go
│   │   │   ├── service/
│   │   │   │   └── order_total_calculator.go
│   │   │   └── event/
│   │   │       └── order_placed.go
│   │   │
│   │   └── shared/
│   │       ├── aggregate_root.go
│   │       ├── entity.go
│   │       ├── value_object.go
│   │       ├── domain_event.go
│   │       └── specification.go
│   │
│   ├── application/
│   │   ├── identity/
│   │   │   ├── command/
│   │   │   │   └── register_user.go
│   │   │   ├── query/
│   │   │   │   └── get_user.go
│   │   │   └── eventhandler/
│   │   │       └── send_welcome_email.go
│   │   ├── catalog/
│   │   │   ├── command/
│   │   │   └── query/
│   │   └── ordering/
│   │       ├── command/
│   │       └── query/
│   │
│   ├── infrastructure/
│   │   ├── identity/
│   │   │   ├── persistence/
│   │   │   │   └── postgres_user_repo.go
│   │   │   └── service/
│   │   │       └── bcrypt_password.go
│   │   ├── catalog/
│   │   │   └── persistence/
│   │   │       └── postgres_product_repo.go
│   │   ├── ordering/
│   │   │   └── persistence/
│   │   │       └── postgres_order_repo.go
│   │   └── shared/
│   │       ├── config/
│   │       │   └── config.go
│   │       └── event/
│   │           └── event_dispatcher.go
│   │
│   └── interfaces/
│       └── http/
│           ├── handler/
│           │   ├── user_handler.go
│           │   ├── product_handler.go
│           │   └── order_handler.go
│           ├── middleware/
│           └── router/
│               └── router.go
│
├── pkg/
│   └── ddd/
│       ├── aggregate.go
│       └── event.go
├── go.mod
└── Makefile
```

---

## Event Storming Workshop

```
Event Storming — DDD-də domain-i kəşf etmək üçün collaborative workshop texnikası.
Alberto Brandolini tərəfindən yaradılıb. Sticky note-larla fiziki və ya virtual board-da işlənir.

Level 1 — Big Picture (4-8 saat):
┌─────────────────────────────────────────────────────────────────┐
│  BOARD (soldan sağa — zaman axını)                              │
│                                                                 │
│  🟠 Domain Events (narıncı)                                     │
│     OrderPlaced → PaymentCharged → InventoryReserved → Shipped  │
│                                                                 │
│  🩷 Hotspots / Problems (çəhrayı)                               │
│     "Payment niyə 2 dəfə çəkilir?" — investigate               │
│                                                                 │
│  🟡 Actors / Users (sarı)                                       │
│     Customer, Warehouse Operator, Finance Team                  │
└─────────────────────────────────────────────────────────────────┘

Level 2 — Process Modeling:
┌─────────────────────────────────────────────────────────────────┐
│  🔵 Commands (mavi) → 🟠 Events → 🟣 Policies (bənövşəyi)      │
│                                                                 │
│  PlaceOrder → OrderPlaced → [When OrderPlaced: ReserveStock]   │
│  ChargePayment → PaymentCharged → [When Paid: ConfirmOrder]    │
│                                                                 │
│  🟢 Read Models / Views (yaşıl)                                 │
│     "Order Summary" — Customer sees this before placing        │
└─────────────────────────────────────────────────────────────────┘

Level 3 — Design Level (Software):
  Commands → Aggregate → Events
  PlaceOrder → Order aggregate → OrderPlaced event

Nəticə — Bounded Context-lər aşkar olunur:
  Ordering BC | Payment BC | Inventory BC | Notification BC
```

---

## Bounded Context Map

```
8 Context Relationship Pattern:

1. Partnership — iki team birlikdə işləyir, eyni sprint
   [Ordering] ←→ [Inventory]

2. Shared Kernel — ortaq kod (domain model, DB schema)
   [Ordering] == shared == [Reporting]
   ⚠ Risk: dəyişiklik hər iki tərəfi pozur

3. Customer-Supplier — downstream (müştəri) upstream-dən asılıdır
   [Payment] ──▶ [Ordering]  (Ordering = supplier, Payment = customer)
   Supplier: "API hazır olacaq Q2-də"

4. Conformist — downstream upstream-in modelinə tam uyğunlaşır
   [Notification] adapts to [Ordering] model as-is
   (Ordering team-ə heç bir təsiri yoxdur)

5. Anti-Corruption Layer (ACL) — downstream öz modelini qoruyur
   [New Ordering] ──[ACL]──▶ [Legacy ERP]
   ACL: legacy model-i yeni domain model-ə translate edir

6. Open Host Service (OHS) — public API + documentation
   [Inventory] exposes REST/gRPC API for any consumer

7. Published Language — standart format (JSON Schema, Protobuf, OpenAPI)
   [Payment] publishes events using Cloudevents spec

8. Separate Ways — heç bir inteqrasiya yoxdur
   [Analytics] ←✗→ [Ordering]  (Analytics öz datasını alır)

Real E-commerce Context Map:
┌──────────────┐     Customer-Supplier     ┌──────────────┐
│   Ordering   │ ─────────────────────────▶│   Payment    │
│      BC      │                           │      BC      │
└──────┬───────┘                           └──────────────┘
       │ OHS (events via Kafka)
       ▼
┌──────────────┐     ACL                   ┌──────────────┐
│  Inventory   │ ─────────[ACL]───────────▶│  Legacy WMS  │
│      BC      │                           │   (old ERP)  │
└──────────────┘                           └──────────────┘
       │ Published Language (Cloudevents)
       ▼
┌──────────────┐
│ Notification │
│      BC      │
└──────────────┘
```

---

## Ubiquitous Language

```
Ubiquitous Language — domain expert + developer eyni terminləri istifadə edir.
Kod, sənəd, danışıq — hamısında eyni dil.

❌ YANLIŞ (texniki dil domain language-i gizlədir):
  class OrderManager {
      public function processTransaction(int $userId, array $data) {
          $record = $this->db->insert('orders', [...]);
          $this->emailService->send($userId, 'order_confirm');
      }
  }

✅ DÜZGÜN (ubiquitous language kod-da görünür):
  class Order {
      public static function place(
          CustomerId $customerId,
          OrderLines $lines,
          ShippingAddress $address,
      ): self { ... }

      public function confirm(): void { ... }
      public function cancel(CancellationReason $reason): void { ... }
      public function ship(TrackingNumber $trackingNumber): void { ... }
  }

Glossary nümunəsi (domain expert ilə razılaşdırılır):
  "Order"         → müştərinin satınalma niyyəti (placed, not yet confirmed)
  "Confirmation"  → payment + inventory reserve uğurlu olduqda
  "Shipment"      → fiziki çatdırma prosesi başladı
  "Cancellation"  → hər iki tərəf (customer/system) initiate edə bilər

  ⚠ "Transaction" termini işlətmə — DB transaction ilə qarışır
  ⚠ "Process" termini işlətmə — OS process ilə qarışır
  ✓ Domain-specific terminlər seç: "place", "confirm", "ship", "cancel"
```
