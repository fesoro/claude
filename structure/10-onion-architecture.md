# Onion Architecture (Senior)

Onion Architecture (Jeffrey Palermo) kodu concentric layer-lərə bölür — burada
dependency-lər içəriyə axır. Domain model mərkəzdə dayanır, infrastructure narahatlıqlarından azaddır.

**Layer-lər (içdən çölə):**
- **Domain Model** — Entity, Value Object (ən iç)
- **Domain Services** — Business logic interface-ləri
- **Application Services** — Use case-lər, orchestration
- **Infrastructure** — Persistence, external servislər (ən çöl)

**Əsas qayda:** Çöl layer-lər iç layer-lərdən asılıdır, heç vaxt əksinə deyil.

---

## Laravel

```
app/
├── Core/                                   # Domain Model (innermost)
│   ├── User/
│   │   ├── User.php                       # Entity
│   │   ├── UserProfile.php
│   │   └── ValueObjects/
│   │       ├── Email.php
│   │       ├── UserId.php
│   │       └── Password.php
│   ├── Order/
│   │   ├── Order.php
│   │   ├── OrderLine.php
│   │   └── ValueObjects/
│   │       ├── OrderId.php
│   │       ├── OrderStatus.php
│   │       └── Money.php
│   └── Shared/
│       ├── AggregateRoot.php
│       ├── Entity.php
│       └── ValueObject.php
│
├── DomainServices/                         # Domain Services layer
│   ├── User/
│   │   ├── UserRepositoryInterface.php
│   │   ├── PasswordHasherInterface.php
│   │   └── UserUniquenessChecker.php
│   ├── Order/
│   │   ├── OrderRepositoryInterface.php
│   │   ├── OrderTotalCalculator.php
│   │   └── DiscountPolicy.php
│   └── Shared/
│       ├── EventDispatcherInterface.php
│       └── UnitOfWorkInterface.php
│
├── ApplicationServices/                    # Application Services layer
│   ├── User/
│   │   ├── RegisterUserService.php
│   │   ├── GetUserService.php
│   │   ├── UpdateUserService.php
│   │   └── DTOs/
│   │       ├── RegisterUserDTO.php
│   │       ├── UserResponseDTO.php
│   │       └── UpdateUserDTO.php
│   ├── Order/
│   │   ├── PlaceOrderService.php
│   │   ├── GetOrderService.php
│   │   ├── CancelOrderService.php
│   │   └── DTOs/
│   │       ├── PlaceOrderDTO.php
│   │       └── OrderResponseDTO.php
│   └── Auth/
│       ├── AuthenticationService.php
│       └── AuthorizationService.php
│
├── Infrastructure/                         # Infrastructure (outermost)
│   ├── Persistence/
│   │   ├── Eloquent/
│   │   │   ├── EloquentUserRepository.php
│   │   │   ├── EloquentOrderRepository.php
│   │   │   └── Models/
│   │   │       ├── UserModel.php
│   │   │       └── OrderModel.php
│   │   └── Migrations/
│   ├── Services/
│   │   ├── BcryptPasswordHasher.php
│   │   └── LaravelEventDispatcher.php
│   ├── Mail/
│   │   └── MailgunMailer.php
│   ├── Cache/
│   │   └── RedisCacheAdapter.php
│   └── Providers/
│       ├── DomainServiceProvider.php
│       └── InfrastructureServiceProvider.php
│
└── Presentation/
    ├── Http/
    │   ├── Controllers/
    │   │   ├── UserController.php
    │   │   └── OrderController.php
    │   ├── Requests/
    │   │   ├── RegisterUserRequest.php
    │   │   └── PlaceOrderRequest.php
    │   ├── Resources/
    │   │   ├── UserResource.php
    │   │   └── OrderResource.php
    │   └── Middleware/
    └── Console/
        └── Commands/

tests/
├── Unit/
│   ├── Core/
│   └── DomainServices/
├── Integration/
│   └── Infrastructure/
└── Feature/
    └── Presentation/
```

---

## Symfony

```
src/
├── Core/                                   # Domain Model (innermost)
│   ├── User/
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   └── UserProfile.php
│   │   └── ValueObject/
│   │       ├── Email.php
│   │       ├── UserId.php
│   │       └── Password.php
│   ├── Order/
│   │   ├── Entity/
│   │   │   ├── Order.php
│   │   │   └── OrderLine.php
│   │   └── ValueObject/
│   │       ├── OrderId.php
│   │       └── Money.php
│   └── Shared/
│       ├── AggregateRoot.php
│       └── ValueObject.php
│
├── DomainService/                          # Domain Services layer
│   ├── User/
│   │   ├── UserRepositoryInterface.php
│   │   ├── PasswordHasherInterface.php
│   │   └── UserUniquenessChecker.php
│   ├── Order/
│   │   ├── OrderRepositoryInterface.php
│   │   └── OrderTotalCalculator.php
│   └── Shared/
│       ├── EventDispatcherInterface.php
│       └── UnitOfWorkInterface.php
│
├── ApplicationService/                     # Application Services layer
│   ├── User/
│   │   ├── RegisterUserService.php
│   │   ├── GetUserService.php
│   │   └── DTO/
│   │       ├── RegisterUserDTO.php
│   │       └── UserResponseDTO.php
│   ├── Order/
│   │   ├── PlaceOrderService.php
│   │   ├── GetOrderService.php
│   │   └── DTO/
│   │       ├── PlaceOrderDTO.php
│   │       └── OrderResponseDTO.php
│   └── Auth/
│       └── AuthenticationService.php
│
├── Infrastructure/                         # Infrastructure (outermost)
│   ├── Persistence/
│   │   ├── Doctrine/
│   │   │   ├── Repository/
│   │   │   │   ├── DoctrineUserRepository.php
│   │   │   │   └── DoctrineOrderRepository.php
│   │   │   └── Mapping/
│   │   │       ├── User.orm.xml
│   │   │       └── Order.orm.xml
│   │   └── Migration/
│   ├── Service/
│   │   ├── BcryptPasswordHasher.php
│   │   └── SymfonyEventDispatcher.php
│   ├── Mailer/
│   │   └── SymfonyMailerAdapter.php
│   └── Cache/
│       └── RedisCacheAdapter.php
│
└── Presentation/
    ├── Http/
    │   ├── Controller/
    │   │   ├── UserController.php
    │   │   └── OrderController.php
    │   └── Request/
    │       ├── RegisterUserRequest.php
    │       └── PlaceOrderRequest.php
    └── CLI/
        └── Command/

config/
├── packages/
└── services.yaml
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── core/                                   # Domain Model (innermost)
│   ├── user/
│   │   ├── User.java
│   │   ├── UserProfile.java
│   │   └── valueobject/
│   │       ├── Email.java
│   │       ├── UserId.java
│   │       └── Password.java
│   ├── order/
│   │   ├── Order.java
│   │   ├── OrderLine.java
│   │   └── valueobject/
│   │       ├── OrderId.java
│   │       ├── OrderStatus.java
│   │       └── Money.java
│   └── shared/
│       ├── AggregateRoot.java
│       ├── Entity.java
│       └── ValueObject.java
│
├── domainservice/                          # Domain Services layer
│   ├── user/
│   │   ├── UserRepository.java            # Interface
│   │   ├── PasswordHasher.java
│   │   └── UserUniquenessChecker.java
│   ├── order/
│   │   ├── OrderRepository.java
│   │   └── OrderTotalCalculator.java
│   └── shared/
│       ├── EventDispatcher.java
│       └── UnitOfWork.java
│
├── applicationservice/                     # Application Services layer
│   ├── user/
│   │   ├── RegisterUserService.java
│   │   ├── GetUserService.java
│   │   ├── UpdateUserService.java
│   │   └── dto/
│   │       ├── RegisterUserDTO.java
│   │       └── UserResponseDTO.java
│   ├── order/
│   │   ├── PlaceOrderService.java
│   │   ├── GetOrderService.java
│   │   └── dto/
│   │       ├── PlaceOrderDTO.java
│   │       └── OrderResponseDTO.java
│   └── auth/
│       └── AuthenticationService.java
│
├── infrastructure/                         # Infrastructure (outermost)
│   ├── persistence/
│   │   ├── jpa/
│   │   │   ├── repository/
│   │   │   │   ├── JpaUserRepository.java
│   │   │   │   └── JpaOrderRepository.java
│   │   │   ├── entity/
│   │   │   │   ├── UserJpaEntity.java
│   │   │   │   └── OrderJpaEntity.java
│   │   │   └── mapper/
│   │   │       ├── UserMapper.java
│   │   │       └── OrderMapper.java
│   │   └── migration/
│   ├── service/
│   │   ├── BcryptPasswordHasher.java
│   │   └── SpringEventDispatcher.java
│   ├── mail/
│   │   └── SpringMailAdapter.java
│   └── config/
│       ├── BeanConfig.java
│       └── PersistenceConfig.java
│
└── presentation/
    └── rest/
        ├── controller/
        │   ├── UserController.java
        │   └── OrderController.java
        ├── request/
        │   ├── RegisterUserRequest.java
        │   └── PlaceOrderRequest.java
        └── response/
            ├── UserResponse.java
            └── OrderResponse.java
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
│   ├── core/                               # Domain Model (innermost)
│   │   ├── user/
│   │   │   ├── user.go
│   │   │   ├── user_profile.go
│   │   │   ├── email.go                   # Value Object
│   │   │   ├── user_id.go
│   │   │   └── password.go
│   │   ├── order/
│   │   │   ├── order.go
│   │   │   ├── order_line.go
│   │   │   ├── order_id.go
│   │   │   ├── order_status.go
│   │   │   └── money.go
│   │   └── shared/
│   │       ├── aggregate_root.go
│   │       ├── entity.go
│   │       └── value_object.go
│   │
│   ├── domainservice/                      # Domain Services layer
│   │   ├── user/
│   │   │   ├── repository.go             # Interface
│   │   │   ├── password_hasher.go
│   │   │   └── uniqueness_checker.go
│   │   ├── order/
│   │   │   ├── repository.go
│   │   │   └── total_calculator.go
│   │   └── shared/
│   │       ├── event_dispatcher.go
│   │       └── unit_of_work.go
│   │
│   ├── application/                        # Application Services layer
│   │   ├── user/
│   │   │   ├── register_user.go
│   │   │   ├── get_user.go
│   │   │   ├── update_user.go
│   │   │   └── dto/
│   │   │       ├── register_user_dto.go
│   │   │       └── user_response_dto.go
│   │   ├── order/
│   │   │   ├── place_order.go
│   │   │   ├── get_order.go
│   │   │   └── dto/
│   │   │       ├── place_order_dto.go
│   │   │       └── order_response_dto.go
│   │   └── auth/
│   │       └── authentication.go
│   │
│   ├── infrastructure/                     # Infrastructure (outermost)
│   │   ├── persistence/
│   │   │   ├── postgres/
│   │   │   │   ├── user_repo.go
│   │   │   │   ├── order_repo.go
│   │   │   │   └── connection.go
│   │   │   └── migration/
│   │   ├── service/
│   │   │   ├── bcrypt_hasher.go
│   │   │   └── event_dispatcher.go
│   │   ├── cache/
│   │   │   └── redis_cache.go
│   │   └── config/
│   │       └── config.go
│   │
│   └── presentation/
│       └── http/
│           ├── handler/
│           │   ├── user_handler.go
│           │   └── order_handler.go
│           ├── request/
│           │   ├── register_user.go
│           │   └── place_order.go
│           ├── response/
│           │   ├── user_response.go
│           │   └── order_response.go
│           ├── middleware/
│           │   └── auth.go
│           └── router/
│               └── router.go
│
├── pkg/
├── go.mod
└── Makefile
```
