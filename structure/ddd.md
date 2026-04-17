# Domain-Driven Design (DDD)

DDD focuses on modeling software around the core business domain.
It uses a shared language (Ubiquitous Language) between developers and domain experts.

**Key concepts:**
- **Bounded Context** — A boundary within which a model is defined and applicable
- **Entity** — An object with identity that persists over time
- **Value Object** — An immutable object defined by its attributes
- **Aggregate** — A cluster of entities/value objects treated as a unit
- **Aggregate Root** — The entry point to an aggregate
- **Repository** — Abstracts data persistence for aggregates
- **Domain Service** — Business logic that doesn't belong to an entity
- **Domain Event** — Something that happened in the domain
- **Factory** — Creates complex aggregates
- **Specification** — Encapsulates query criteria

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
