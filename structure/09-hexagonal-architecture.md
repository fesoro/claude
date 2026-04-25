# Hexagonal Architecture (Senior)

Hexagonal Architecture (Alistair Cockburn) application core-unu port-lar (interface-lər) və adapter-lər (implementation-lar) vasitəsilə
xarici narahatlıqlardan təcrid edir.

**Əsas anlayışlar:**
- **Application Core** — Business logic, domain
- **Ports** — Core-un xaricdənkilərlə necə ünsiyyət qurduğunu təyin edən interface-lər
  - **Driving/Primary Ports** — Xarici dünya core-u necə çağırır (use case-lər)
  - **Driven/Secondary Ports** — Core xarici dünyanı necə çağırır (repository-lər, servislər)
- **Adapters** — Port-ların implementation-ları
  - **Driving/Primary Adapters** — HTTP controller-lər, CLI, gRPC
  - **Driven/Secondary Adapters** — Database, email, external API-lər

---

## Laravel

```
app/
├── Core/                                   # Application Core
│   ├── Domain/
│   │   ├── User/
│   │   │   ├── User.php
│   │   │   ├── UserProfile.php
│   │   │   └── ValueObject/
│   │   │       ├── Email.php
│   │   │       ├── UserId.php
│   │   │       └── Password.php
│   │   ├── Order/
│   │   │   ├── Order.php
│   │   │   ├── OrderLine.php
│   │   │   └── ValueObject/
│   │   │       ├── OrderId.php
│   │   │       └── Money.php
│   │   └── Shared/
│   │       ├── AggregateRoot.php
│   │       └── DomainEvent.php
│   │
│   ├── Port/                              # Ports (Interfaces)
│   │   ├── Driving/                       # Primary ports (inbound)
│   │   │   ├── User/
│   │   │   │   ├── CreateUserUseCaseInterface.php
│   │   │   │   ├── GetUserUseCaseInterface.php
│   │   │   │   └── UpdateUserUseCaseInterface.php
│   │   │   └── Order/
│   │   │       ├── PlaceOrderUseCaseInterface.php
│   │   │       ├── GetOrderUseCaseInterface.php
│   │   │       └── CancelOrderUseCaseInterface.php
│   │   └── Driven/                        # Secondary ports (outbound)
│   │       ├── Persistence/
│   │       │   ├── UserRepositoryInterface.php
│   │       │   └── OrderRepositoryInterface.php
│   │       ├── Messaging/
│   │       │   └── EventPublisherInterface.php
│   │       ├── Mail/
│   │       │   └── MailerInterface.php
│   │       ├── Payment/
│   │       │   └── PaymentGatewayInterface.php
│   │       └── Cache/
│   │           └── CacheInterface.php
│   │
│   └── UseCase/                           # Use case implementations
│       ├── User/
│       │   ├── CreateUserUseCase.php
│       │   ├── GetUserUseCase.php
│       │   └── UpdateUserUseCase.php
│       ├── Order/
│       │   ├── PlaceOrderUseCase.php
│       │   ├── GetOrderUseCase.php
│       │   └── CancelOrderUseCase.php
│       └── DTO/
│           ├── CreateUserInput.php
│           ├── UserOutput.php
│           ├── PlaceOrderInput.php
│           └── OrderOutput.php
│
├── Adapter/                               # Adapters
│   ├── Driving/                           # Primary adapters (inbound)
│   │   ├── Http/
│   │   │   ├── Controller/
│   │   │   │   ├── UserController.php
│   │   │   │   └── OrderController.php
│   │   │   ├── Request/
│   │   │   │   ├── CreateUserRequest.php
│   │   │   │   └── PlaceOrderRequest.php
│   │   │   ├── Resource/
│   │   │   │   ├── UserResource.php
│   │   │   │   └── OrderResource.php
│   │   │   └── Middleware/
│   │   ├── Console/
│   │   │   └── Command/
│   │   │       └── CreateUserCommand.php
│   │   └── GraphQL/
│   │       ├── Query/
│   │       └── Mutation/
│   │
│   └── Driven/                            # Secondary adapters (outbound)
│       ├── Persistence/
│       │   ├── Eloquent/
│       │   │   ├── EloquentUserRepository.php
│       │   │   └── EloquentOrderRepository.php
│       │   └── InMemory/
│       │       └── InMemoryUserRepository.php
│       ├── Messaging/
│       │   ├── RabbitMQEventPublisher.php
│       │   └── LaravelEventPublisher.php
│       ├── Mail/
│       │   └── LaravelMailer.php
│       ├── Payment/
│       │   ├── StripePaymentGateway.php
│       │   └── PayPalPaymentGateway.php
│       └── Cache/
│           └── RedisCacheAdapter.php
│
└── Providers/
    └── HexagonalServiceProvider.php

routes/
tests/
├── Unit/
│   └── Core/
├── Integration/
│   └── Adapter/
│       └── Driven/
└── Feature/
    └── Adapter/
        └── Driving/
```

---

## Symfony

```
src/
├── Core/                                   # Application Core
│   ├── Domain/
│   │   ├── User/
│   │   │   ├── Entity/
│   │   │   │   ├── User.php
│   │   │   │   └── UserProfile.php
│   │   │   ├── ValueObject/
│   │   │   │   ├── Email.php
│   │   │   │   └── UserId.php
│   │   │   └── Event/
│   │   │       └── UserCreatedEvent.php
│   │   ├── Order/
│   │   │   ├── Entity/
│   │   │   │   ├── Order.php
│   │   │   │   └── OrderLine.php
│   │   │   └── ValueObject/
│   │   │       ├── OrderId.php
│   │   │       └── Money.php
│   │   └── Shared/
│   │       └── AggregateRoot.php
│   │
│   ├── Port/
│   │   ├── Driving/
│   │   │   ├── User/
│   │   │   │   ├── CreateUserUseCaseInterface.php
│   │   │   │   └── GetUserUseCaseInterface.php
│   │   │   └── Order/
│   │   │       ├── PlaceOrderUseCaseInterface.php
│   │   │       └── GetOrderUseCaseInterface.php
│   │   └── Driven/
│   │       ├── Persistence/
│   │       │   ├── UserRepositoryInterface.php
│   │       │   └── OrderRepositoryInterface.php
│   │       ├── Messaging/
│   │       │   └── EventPublisherInterface.php
│   │       ├── Mail/
│   │       │   └── MailerInterface.php
│   │       └── Payment/
│   │           └── PaymentGatewayInterface.php
│   │
│   └── UseCase/
│       ├── User/
│       │   ├── CreateUserUseCase.php
│       │   └── GetUserUseCase.php
│       ├── Order/
│       │   ├── PlaceOrderUseCase.php
│       │   └── GetOrderUseCase.php
│       └── DTO/
│           ├── CreateUserInput.php
│           ├── UserOutput.php
│           ├── PlaceOrderInput.php
│           └── OrderOutput.php
│
├── Adapter/
│   ├── Driving/
│   │   ├── Http/
│   │   │   ├── Controller/
│   │   │   │   ├── UserController.php
│   │   │   │   └── OrderController.php
│   │   │   └── Request/
│   │   │       ├── CreateUserRequest.php
│   │   │       └── PlaceOrderRequest.php
│   │   ├── CLI/
│   │   │   └── Command/
│   │   │       └── CreateUserCLICommand.php
│   │   └── GraphQL/
│   │       ├── Query/
│   │       └── Mutation/
│   │
│   └── Driven/
│       ├── Persistence/
│       │   ├── Doctrine/
│       │   │   ├── DoctrineUserRepository.php
│       │   │   ├── DoctrineOrderRepository.php
│       │   │   └── Mapping/
│       │   └── InMemory/
│       │       └── InMemoryUserRepository.php
│       ├── Messaging/
│       │   └── SymfonyMessengerPublisher.php
│       ├── Mail/
│       │   └── SymfonyMailerAdapter.php
│       └── Payment/
│           ├── StripePaymentGateway.php
│           └── PayPalPaymentGateway.php

config/
└── services.yaml
tests/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── core/                                   # Application Core
│   ├── domain/
│   │   ├── user/
│   │   │   ├── User.java
│   │   │   ├── UserProfile.java
│   │   │   ├── valueobject/
│   │   │   │   ├── Email.java
│   │   │   │   └── UserId.java
│   │   │   └── event/
│   │   │       └── UserCreatedEvent.java
│   │   ├── order/
│   │   │   ├── Order.java
│   │   │   ├── OrderLine.java
│   │   │   └── valueobject/
│   │   │       ├── OrderId.java
│   │   │       └── Money.java
│   │   └── shared/
│   │       ├── AggregateRoot.java
│   │       └── DomainEvent.java
│   │
│   ├── port/
│   │   ├── driving/                       # Primary ports
│   │   │   ├── user/
│   │   │   │   ├── CreateUserUseCase.java
│   │   │   │   ├── GetUserUseCase.java
│   │   │   │   └── UpdateUserUseCase.java
│   │   │   └── order/
│   │   │       ├── PlaceOrderUseCase.java
│   │   │       └── GetOrderUseCase.java
│   │   └── driven/                        # Secondary ports
│   │       ├── persistence/
│   │       │   ├── UserRepository.java
│   │       │   └── OrderRepository.java
│   │       ├── messaging/
│   │       │   └── EventPublisher.java
│   │       ├── mail/
│   │       │   └── Mailer.java
│   │       └── payment/
│   │           └── PaymentGateway.java
│   │
│   └── usecase/
│       ├── user/
│       │   ├── CreateUserUseCaseImpl.java
│       │   └── GetUserUseCaseImpl.java
│       ├── order/
│       │   ├── PlaceOrderUseCaseImpl.java
│       │   └── GetOrderUseCaseImpl.java
│       └── dto/
│           ├── CreateUserInput.java
│           ├── UserOutput.java
│           ├── PlaceOrderInput.java
│           └── OrderOutput.java
│
├── adapter/
│   ├── driving/                           # Primary adapters
│   │   ├── rest/
│   │   │   ├── controller/
│   │   │   │   ├── UserController.java
│   │   │   │   └── OrderController.java
│   │   │   ├── request/
│   │   │   │   ├── CreateUserRequest.java
│   │   │   │   └── PlaceOrderRequest.java
│   │   │   ├── response/
│   │   │   │   ├── UserResponse.java
│   │   │   │   └── OrderResponse.java
│   │   │   └── advice/
│   │   │       └── GlobalExceptionHandler.java
│   │   ├── grpc/
│   │   │   └── UserGrpcService.java
│   │   └── scheduler/
│   │       └── OrderCleanupScheduler.java
│   │
│   └── driven/                            # Secondary adapters
│       ├── persistence/
│       │   ├── jpa/
│       │   │   ├── JpaUserRepository.java
│       │   │   ├── JpaOrderRepository.java
│       │   │   ├── entity/
│       │   │   │   ├── UserJpaEntity.java
│       │   │   │   └── OrderJpaEntity.java
│       │   │   └── mapper/
│       │   │       ├── UserMapper.java
│       │   │       └── OrderMapper.java
│       │   └── inmemory/
│       │       └── InMemoryUserRepository.java
│       ├── messaging/
│       │   └── KafkaEventPublisher.java
│       ├── mail/
│       │   └── SpringMailAdapter.java
│       ├── payment/
│       │   ├── StripePaymentGateway.java
│       │   └── PayPalPaymentGateway.java
│       └── config/
│           └── AdapterConfig.java

src/main/resources/
├── application.yml
└── db/migration/
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
│   ├── core/                               # Application Core
│   │   ├── domain/
│   │   │   ├── user/
│   │   │   │   ├── user.go
│   │   │   │   ├── email.go
│   │   │   │   ├── user_id.go
│   │   │   │   └── events.go
│   │   │   ├── order/
│   │   │   │   ├── order.go
│   │   │   │   ├── order_line.go
│   │   │   │   ├── money.go
│   │   │   │   └── events.go
│   │   │   └── shared/
│   │   │       └── aggregate_root.go
│   │   │
│   │   ├── port/
│   │   │   ├── driving/                   # Primary ports (inbound)
│   │   │   │   ├── user/
│   │   │   │   │   ├── create_user.go     # Use case interface
│   │   │   │   │   ├── get_user.go
│   │   │   │   │   └── update_user.go
│   │   │   │   └── order/
│   │   │   │       ├── place_order.go
│   │   │   │       └── get_order.go
│   │   │   └── driven/                    # Secondary ports (outbound)
│   │   │       ├── persistence/
│   │   │       │   ├── user_repository.go # Interface
│   │   │       │   └── order_repository.go
│   │   │       ├── messaging/
│   │   │       │   └── event_publisher.go
│   │   │       ├── mail/
│   │   │       │   └── mailer.go
│   │   │       └── payment/
│   │   │           └── payment_gateway.go
│   │   │
│   │   └── usecase/
│   │       ├── user/
│   │       │   ├── create_user.go         # Implementation
│   │       │   ├── get_user.go
│   │       │   └── update_user.go
│   │       ├── order/
│   │       │   ├── place_order.go
│   │       │   └── get_order.go
│   │       └── dto/
│   │           ├── user_dto.go
│   │           └── order_dto.go
│   │
│   └── adapter/
│       ├── driving/                       # Primary adapters (inbound)
│       │   ├── http/
│       │   │   ├── handler/
│       │   │   │   ├── user_handler.go
│       │   │   │   └── order_handler.go
│       │   │   ├── request/
│       │   │   │   ├── create_user.go
│       │   │   │   └── place_order.go
│       │   │   ├── response/
│       │   │   │   ├── user_response.go
│       │   │   │   └── order_response.go
│       │   │   ├── middleware/
│       │   │   │   └── auth.go
│       │   │   └── router/
│       │   │       └── router.go
│       │   ├── grpc/
│       │   │   ├── server.go
│       │   │   └── proto/
│       │   └── cli/
│       │       └── commands.go
│       │
│       └── driven/                        # Secondary adapters (outbound)
│           ├── persistence/
│           │   ├── postgres/
│           │   │   ├── user_repo.go
│           │   │   ├── order_repo.go
│           │   │   └── connection.go
│           │   └── inmemory/
│           │       └── user_repo.go
│           ├── messaging/
│           │   └── nats_publisher.go
│           ├── mail/
│           │   └── smtp_mailer.go
│           └── payment/
│               ├── stripe_gateway.go
│               └── paypal_gateway.go
│
├── pkg/
├── go.mod
└── Makefile
```
