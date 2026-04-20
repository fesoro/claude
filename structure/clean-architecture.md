# Clean Architecture

Clean Architecture (Robert C. Martin) software-i concentric layer-lərə bölür — burada dependency-lər içəriyə doğru baxır.
Əsas prinsip: iç layer-lər çöl layer-lər haqqında heç nə bilmir.

**Layer-lər (içdən çölə):**
- **Entities** — Enterprise business rule-ları
- **Use Cases** — Application business rule-ları
- **Interface Adapters** — Controller, Presenter, Gateway
- **Frameworks & Drivers** — DB, Web, UI, External API-lər

---

## Laravel

```
app/
├── Domain/                         # Entities layer
│   ├── User/
│   │   ├── User.php                # Entity
│   │   ├── UserRepositoryInterface.php
│   │   └── ValueObjects/
│   │       ├── Email.php
│   │       └── UserId.php
│   ├── Order/
│   │   ├── Order.php
│   │   ├── OrderLine.php
│   │   ├── OrderRepositoryInterface.php
│   │   └── ValueObjects/
│   │       ├── OrderId.php
│   │       └── Money.php
│   └── Shared/
│       ├── AggregateRoot.php
│       └── ValueObject.php
│
├── Application/                    # Use Cases layer
│   ├── User/
│   │   ├── Commands/
│   │   │   ├── CreateUser/
│   │   │   │   ├── CreateUserCommand.php
│   │   │   │   └── CreateUserHandler.php
│   │   │   └── UpdateUser/
│   │   │       ├── UpdateUserCommand.php
│   │   │       └── UpdateUserHandler.php
│   │   ├── Queries/
│   │   │   └── GetUser/
│   │   │       ├── GetUserQuery.php
│   │   │       └── GetUserHandler.php
│   │   └── DTOs/
│   │       └── UserDTO.php
│   ├── Order/
│   │   ├── Commands/
│   │   │   └── PlaceOrder/
│   │   │       ├── PlaceOrderCommand.php
│   │   │       └── PlaceOrderHandler.php
│   │   └── Queries/
│   │       └── GetOrder/
│   │           ├── GetOrderQuery.php
│   │           └── GetOrderHandler.php
│   └── Contracts/
│       ├── EventBusInterface.php
│       └── UnitOfWorkInterface.php
│
├── Infrastructure/                 # Frameworks & Drivers layer
│   ├── Persistence/
│   │   ├── Eloquent/
│   │   │   ├── UserEloquentRepository.php
│   │   │   └── OrderEloquentRepository.php
│   │   └── Migrations/
│   ├── Mail/
│   │   └── UserWelcomeMailer.php
│   ├── Queue/
│   │   └── Jobs/
│   ├── Cache/
│   │   └── RedisCacheAdapter.php
│   └── Providers/
│       ├── DomainServiceProvider.php
│       └── InfrastructureServiceProvider.php
│
├── Interfaces/                     # Interface Adapters layer
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── UserController.php
│   │   │   └── OrderController.php
│   │   ├── Requests/
│   │   │   ├── CreateUserRequest.php
│   │   │   └── PlaceOrderRequest.php
│   │   ├── Resources/
│   │   │   ├── UserResource.php
│   │   │   └── OrderResource.php
│   │   └── Middleware/
│   ├── Console/
│   │   └── Commands/
│   └── Api/
│       └── Routes/
│           ├── user.php
│           └── order.php
│
config/
database/
routes/
tests/
├── Unit/
│   ├── Domain/
│   └── Application/
├── Integration/
│   └── Infrastructure/
└── Feature/
    └── Interfaces/
```

---

## Symfony

```
src/
├── Domain/                         # Entities layer
│   ├── User/
│   │   ├── Entity/
│   │   │   └── User.php
│   │   ├── Repository/
│   │   │   └── UserRepositoryInterface.php
│   │   ├── ValueObject/
│   │   │   ├── Email.php
│   │   │   └── UserId.php
│   │   ├── Event/
│   │   │   └── UserCreatedEvent.php
│   │   └── Exception/
│   │       └── UserNotFoundException.php
│   ├── Order/
│   │   ├── Entity/
│   │   │   ├── Order.php
│   │   │   └── OrderLine.php
│   │   ├── Repository/
│   │   │   └── OrderRepositoryInterface.php
│   │   └── ValueObject/
│   │       ├── OrderId.php
│   │       └── Money.php
│   └── Shared/
│       ├── AggregateRoot.php
│       ├── DomainEvent.php
│       └── ValueObject.php
│
├── Application/                    # Use Cases layer
│   ├── User/
│   │   ├── Command/
│   │   │   ├── CreateUser/
│   │   │   │   ├── CreateUserCommand.php
│   │   │   │   └── CreateUserCommandHandler.php
│   │   │   └── UpdateUser/
│   │   │       ├── UpdateUserCommand.php
│   │   │       └── UpdateUserCommandHandler.php
│   │   ├── Query/
│   │   │   └── GetUser/
│   │   │       ├── GetUserQuery.php
│   │   │       └── GetUserQueryHandler.php
│   │   └── DTO/
│   │       └── UserDTO.php
│   ├── Order/
│   │   ├── Command/
│   │   │   └── PlaceOrder/
│   │   │       ├── PlaceOrderCommand.php
│   │   │       └── PlaceOrderCommandHandler.php
│   │   └── Query/
│   │       └── GetOrder/
│   │           ├── GetOrderQuery.php
│   │           └── GetOrderQueryHandler.php
│   └── Contract/
│       ├── CommandBusInterface.php
│       └── QueryBusInterface.php
│
├── Infrastructure/                 # Frameworks & Drivers layer
│   ├── Persistence/
│   │   ├── Doctrine/
│   │   │   ├── Repository/
│   │   │   │   ├── DoctrineUserRepository.php
│   │   │   │   └── DoctrineOrderRepository.php
│   │   │   ├── Mapping/
│   │   │   │   ├── User.orm.xml
│   │   │   │   └── Order.orm.xml
│   │   │   └── Migration/
│   │   └── InMemory/
│   │       └── InMemoryUserRepository.php
│   ├── Messenger/
│   │   ├── CommandBus.php
│   │   └── QueryBus.php
│   ├── Mailer/
│   │   └── SymfonyMailerAdapter.php
│   └── Cache/
│       └── RedisCacheAdapter.php
│
├── Interfaces/                     # Interface Adapters layer
│   ├── Http/
│   │   ├── Controller/
│   │   │   ├── UserController.php
│   │   │   └── OrderController.php
│   │   ├── Request/
│   │   │   ├── CreateUserRequest.php
│   │   │   └── PlaceOrderRequest.php
│   │   └── Response/
│   │       ├── UserResponse.php
│   │       └── OrderResponse.php
│   ├── CLI/
│   │   └── Command/
│   │       └── CreateUserCLICommand.php
│   └── EventSubscriber/
│       └── ExceptionSubscriber.php
│
config/
├── packages/
├── routes/
└── services.yaml
tests/
├── Unit/
│   ├── Domain/
│   └── Application/
├── Integration/
│   └── Infrastructure/
└── Functional/
    └── Interfaces/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── domain/                         # Entities layer
│   ├── user/
│   │   ├── User.java              # Entity
│   │   ├── UserRepository.java    # Interface
│   │   ├── valueobject/
│   │   │   ├── Email.java
│   │   │   └── UserId.java
│   │   ├── event/
│   │   │   └── UserCreatedEvent.java
│   │   └── exception/
│   │       └── UserNotFoundException.java
│   ├── order/
│   │   ├── Order.java
│   │   ├── OrderLine.java
│   │   ├── OrderRepository.java
│   │   └── valueobject/
│   │       ├── OrderId.java
│   │       └── Money.java
│   └── shared/
│       ├── AggregateRoot.java
│       ├── DomainEvent.java
│       └── ValueObject.java
│
├── application/                    # Use Cases layer
│   ├── user/
│   │   ├── command/
│   │   │   ├── CreateUserCommand.java
│   │   │   ├── CreateUserCommandHandler.java
│   │   │   ├── UpdateUserCommand.java
│   │   │   └── UpdateUserCommandHandler.java
│   │   ├── query/
│   │   │   ├── GetUserQuery.java
│   │   │   └── GetUserQueryHandler.java
│   │   └── dto/
│   │       └── UserDTO.java
│   ├── order/
│   │   ├── command/
│   │   │   ├── PlaceOrderCommand.java
│   │   │   └── PlaceOrderCommandHandler.java
│   │   └── query/
│   │       ├── GetOrderQuery.java
│   │       └── GetOrderQueryHandler.java
│   └── port/
│       ├── EventPublisher.java
│       └── UnitOfWork.java
│
├── infrastructure/                 # Frameworks & Drivers layer
│   ├── persistence/
│   │   ├── jpa/
│   │   │   ├── entity/
│   │   │   │   ├── UserJpaEntity.java
│   │   │   │   └── OrderJpaEntity.java
│   │   │   ├── repository/
│   │   │   │   ├── JpaUserRepository.java
│   │   │   │   └── JpaOrderRepository.java
│   │   │   └── mapper/
│   │   │       ├── UserMapper.java
│   │   │       └── OrderMapper.java
│   │   └── migration/
│   ├── messaging/
│   │   └── SpringEventPublisher.java
│   ├── mail/
│   │   └── SpringMailAdapter.java
│   ├── cache/
│   │   └── RedisCacheAdapter.java
│   └── config/
│       ├── BeanConfig.java
│       ├── SecurityConfig.java
│       └── PersistenceConfig.java
│
├── interfaces/                     # Interface Adapters layer
│   ├── rest/
│   │   ├── controller/
│   │   │   ├── UserController.java
│   │   │   └── OrderController.java
│   │   ├── request/
│   │   │   ├── CreateUserRequest.java
│   │   │   └── PlaceOrderRequest.java
│   │   ├── response/
│   │   │   ├── UserResponse.java
│   │   │   └── OrderResponse.java
│   │   └── advice/
│   │       └── GlobalExceptionHandler.java
│   ├── cli/
│   │   └── CreateUserCLIRunner.java
│   └── scheduler/
│       └── OrderCleanupScheduler.java
│
src/main/resources/
├── application.yml
└── db/migration/

src/test/java/com/example/app/
├── unit/
│   ├── domain/
│   └── application/
├── integration/
│   └── infrastructure/
└── functional/
    └── interfaces/
```

---

## Golang

```
project/
├── cmd/
│   └── api/
│       └── main.go                 # Entry point
│
├── internal/
│   ├── domain/                     # Entities layer
│   │   ├── user/
│   │   │   ├── user.go             # Entity
│   │   │   ├── repository.go       # Interface
│   │   │   ├── email.go            # Value Object
│   │   │   ├── user_id.go          # Value Object
│   │   │   ├── events.go           # Domain Events
│   │   │   └── errors.go
│   │   ├── order/
│   │   │   ├── order.go
│   │   │   ├── order_line.go
│   │   │   ├── repository.go
│   │   │   ├── money.go
│   │   │   └── errors.go
│   │   └── shared/
│   │       ├── aggregate_root.go
│   │       └── domain_event.go
│   │
│   ├── application/                # Use Cases layer
│   │   ├── user/
│   │   │   ├── command/
│   │   │   │   ├── create_user.go
│   │   │   │   └── update_user.go
│   │   │   ├── query/
│   │   │   │   └── get_user.go
│   │   │   └── dto/
│   │   │       └── user_dto.go
│   │   ├── order/
│   │   │   ├── command/
│   │   │   │   └── place_order.go
│   │   │   └── query/
│   │   │       └── get_order.go
│   │   └── port/
│   │       ├── event_bus.go
│   │       └── unit_of_work.go
│   │
│   ├── infrastructure/             # Frameworks & Drivers layer
│   │   ├── persistence/
│   │   │   ├── postgres/
│   │   │   │   ├── user_repository.go
│   │   │   │   ├── order_repository.go
│   │   │   │   └── connection.go
│   │   │   └── migration/
│   │   │       └── migrations.go
│   │   ├── messaging/
│   │   │   └── nats_event_bus.go
│   │   ├── cache/
│   │   │   └── redis_cache.go
│   │   └── config/
│   │       └── config.go
│   │
│   └── interfaces/                 # Interface Adapters layer
│       ├── http/
│       │   ├── handler/
│       │   │   ├── user_handler.go
│       │   │   └── order_handler.go
│       │   ├── request/
│       │   │   ├── create_user.go
│       │   │   └── place_order.go
│       │   ├── response/
│       │   │   ├── user_response.go
│       │   │   └── order_response.go
│       │   ├── middleware/
│       │   │   ├── auth.go
│       │   │   └── logging.go
│       │   └── router/
│       │       └── router.go
│       ├── grpc/
│       │   ├── server.go
│       │   └── proto/
│       └── cli/
│           └── commands.go
│
├── pkg/                            # Shared public packages
│   ├── logger/
│   └── validator/
├── go.mod
├── go.sum
└── Makefile
```
