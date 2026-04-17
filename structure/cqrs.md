# CQRS (Command Query Responsibility Segregation)

CQRS separates read (Query) and write (Command) operations into different models.
Each side can have its own data store, optimized for its workload.

**Key concepts:**
- **Command** — Changes state, returns nothing (or just an ID)
- **Query** — Returns data, changes nothing
- **Command Handler** — Processes a command
- **Query Handler** — Processes a query
- **Read Model** — Optimized for querying
- **Write Model** — Optimized for business rules and writes

---

## Laravel

```
app/
├── Command/                            # Write side
│   ├── User/
│   │   ├── CreateUser/
│   │   │   ├── CreateUserCommand.php
│   │   │   ├── CreateUserHandler.php
│   │   │   └── CreateUserValidator.php
│   │   ├── UpdateUser/
│   │   │   ├── UpdateUserCommand.php
│   │   │   ├── UpdateUserHandler.php
│   │   │   └── UpdateUserValidator.php
│   │   └── DeleteUser/
│   │       ├── DeleteUserCommand.php
│   │       └── DeleteUserHandler.php
│   ├── Order/
│   │   ├── PlaceOrder/
│   │   │   ├── PlaceOrderCommand.php
│   │   │   ├── PlaceOrderHandler.php
│   │   │   └── PlaceOrderValidator.php
│   │   └── CancelOrder/
│   │       ├── CancelOrderCommand.php
│   │       └── CancelOrderHandler.php
│   └── Bus/
│       ├── CommandBusInterface.php
│       └── CommandBus.php
│
├── Query/                              # Read side
│   ├── User/
│   │   ├── GetUserById/
│   │   │   ├── GetUserByIdQuery.php
│   │   │   ├── GetUserByIdHandler.php
│   │   │   └── GetUserByIdResult.php
│   │   ├── ListUsers/
│   │   │   ├── ListUsersQuery.php
│   │   │   ├── ListUsersHandler.php
│   │   │   └── ListUsersResult.php
│   │   └── SearchUsers/
│   │       ├── SearchUsersQuery.php
│   │       ├── SearchUsersHandler.php
│   │       └── SearchUsersResult.php
│   ├── Order/
│   │   ├── GetOrderById/
│   │   │   ├── GetOrderByIdQuery.php
│   │   │   ├── GetOrderByIdHandler.php
│   │   │   └── GetOrderByIdResult.php
│   │   └── ListOrdersByUser/
│   │       ├── ListOrdersByUserQuery.php
│   │       ├── ListOrdersByUserHandler.php
│   │       └── ListOrdersByUserResult.php
│   └── Bus/
│       ├── QueryBusInterface.php
│       └── QueryBus.php
│
├── Domain/                             # Domain models (write model)
│   ├── User/
│   │   ├── User.php
│   │   ├── UserRepositoryInterface.php
│   │   └── Events/
│   │       ├── UserCreated.php
│   │       └── UserUpdated.php
│   └── Order/
│       ├── Order.php
│       ├── OrderRepositoryInterface.php
│       └── Events/
│           ├── OrderPlaced.php
│           └── OrderCancelled.php
│
├── ReadModel/                          # Read models (projections)
│   ├── User/
│   │   ├── UserReadModel.php
│   │   ├── UserReadRepository.php
│   │   └── UserProjector.php
│   └── Order/
│       ├── OrderReadModel.php
│       ├── OrderReadRepository.php
│       └── OrderProjector.php
│
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Write/
│   │   │   ├── EloquentUserRepository.php
│   │   │   └── EloquentOrderRepository.php
│   │   └── Read/
│   │       ├── MysqlUserReadRepository.php
│   │       └── ElasticsearchOrderReadRepository.php
│   ├── Bus/
│   │   ├── LaravelCommandBus.php
│   │   └── LaravelQueryBus.php
│   └── Projector/
│       └── EventProjectorService.php
│
└── Http/
    ├── Controllers/
    │   ├── Command/
    │   │   ├── UserCommandController.php
    │   │   └── OrderCommandController.php
    │   └── Query/
    │       ├── UserQueryController.php
    │       └── OrderQueryController.php
    └── Requests/
        ├── CreateUserRequest.php
        └── PlaceOrderRequest.php
```

---

## Symfony

```
src/
├── Command/                            # Write side
│   ├── User/
│   │   ├── CreateUser/
│   │   │   ├── CreateUserCommand.php
│   │   │   └── CreateUserCommandHandler.php
│   │   ├── UpdateUser/
│   │   │   ├── UpdateUserCommand.php
│   │   │   └── UpdateUserCommandHandler.php
│   │   └── DeleteUser/
│   │       ├── DeleteUserCommand.php
│   │       └── DeleteUserCommandHandler.php
│   └── Order/
│       ├── PlaceOrder/
│       │   ├── PlaceOrderCommand.php
│       │   └── PlaceOrderCommandHandler.php
│       └── CancelOrder/
│           ├── CancelOrderCommand.php
│           └── CancelOrderCommandHandler.php
│
├── Query/                              # Read side
│   ├── User/
│   │   ├── GetUserById/
│   │   │   ├── GetUserByIdQuery.php
│   │   │   ├── GetUserByIdQueryHandler.php
│   │   │   └── GetUserByIdResult.php
│   │   └── ListUsers/
│   │       ├── ListUsersQuery.php
│   │       ├── ListUsersQueryHandler.php
│   │       └── ListUsersResult.php
│   └── Order/
│       ├── GetOrderById/
│       │   ├── GetOrderByIdQuery.php
│       │   ├── GetOrderByIdQueryHandler.php
│       │   └── GetOrderByIdResult.php
│       └── ListOrdersByUser/
│           ├── ListOrdersByUserQuery.php
│           ├── ListOrdersByUserQueryHandler.php
│           └── ListOrdersByUserResult.php
│
├── Domain/                             # Write model
│   ├── User/
│   │   ├── Entity/
│   │   │   └── User.php
│   │   ├── Repository/
│   │   │   └── UserRepositoryInterface.php
│   │   └── Event/
│   │       ├── UserCreatedEvent.php
│   │       └── UserUpdatedEvent.php
│   └── Order/
│       ├── Entity/
│       │   └── Order.php
│       ├── Repository/
│       │   └── OrderRepositoryInterface.php
│       └── Event/
│           └── OrderPlacedEvent.php
│
├── ReadModel/                          # Read model (projections)
│   ├── User/
│   │   ├── UserView.php
│   │   ├── UserViewRepository.php
│   │   └── UserProjector.php
│   └── Order/
│       ├── OrderView.php
│       ├── OrderViewRepository.php
│       └── OrderProjector.php
│
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Write/
│   │   │   ├── DoctrineUserRepository.php
│   │   │   └── DoctrineOrderRepository.php
│   │   └── Read/
│   │       ├── DbalUserViewRepository.php
│   │       └── ElasticsearchOrderViewRepository.php
│   ├── Messenger/
│   │   ├── CommandBusConfig.php
│   │   └── QueryBusConfig.php
│   └── Projector/
│       └── AsyncProjectorSubscriber.php
│
└── UI/
    └── Http/
        ├── Controller/
        │   ├── UserCommandController.php
        │   ├── UserQueryController.php
        │   ├── OrderCommandController.php
        │   └── OrderQueryController.php
        └── Request/
            ├── CreateUserRequest.php
            └── PlaceOrderRequest.php

config/
├── packages/
│   └── messenger.yaml                  # Command & query bus config
└── services.yaml
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── command/                            # Write side
│   ├── user/
│   │   ├── CreateUserCommand.java
│   │   ├── CreateUserCommandHandler.java
│   │   ├── UpdateUserCommand.java
│   │   ├── UpdateUserCommandHandler.java
│   │   ├── DeleteUserCommand.java
│   │   └── DeleteUserCommandHandler.java
│   ├── order/
│   │   ├── PlaceOrderCommand.java
│   │   ├── PlaceOrderCommandHandler.java
│   │   ├── CancelOrderCommand.java
│   │   └── CancelOrderCommandHandler.java
│   └── bus/
│       ├── CommandBus.java             # Interface
│       └── SpringCommandBus.java       # Impl
│
├── query/                              # Read side
│   ├── user/
│   │   ├── GetUserByIdQuery.java
│   │   ├── GetUserByIdQueryHandler.java
│   │   ├── GetUserByIdResult.java
│   │   ├── ListUsersQuery.java
│   │   ├── ListUsersQueryHandler.java
│   │   └── ListUsersResult.java
│   ├── order/
│   │   ├── GetOrderByIdQuery.java
│   │   ├── GetOrderByIdQueryHandler.java
│   │   ├── GetOrderByIdResult.java
│   │   ├── ListOrdersByUserQuery.java
│   │   ├── ListOrdersByUserQueryHandler.java
│   │   └── ListOrdersByUserResult.java
│   └── bus/
│       ├── QueryBus.java
│       └── SpringQueryBus.java
│
├── domain/                             # Write model
│   ├── user/
│   │   ├── User.java
│   │   ├── UserRepository.java
│   │   └── event/
│   │       ├── UserCreatedEvent.java
│   │       └── UserUpdatedEvent.java
│   └── order/
│       ├── Order.java
│       ├── OrderRepository.java
│       └── event/
│           └── OrderPlacedEvent.java
│
├── readmodel/                          # Read model
│   ├── user/
│   │   ├── UserView.java
│   │   ├── UserViewRepository.java
│   │   └── UserProjector.java
│   └── order/
│       ├── OrderView.java
│       ├── OrderViewRepository.java
│       └── OrderProjector.java
│
├── infrastructure/
│   ├── persistence/
│   │   ├── write/
│   │   │   ├── JpaUserRepository.java
│   │   │   └── JpaOrderRepository.java
│   │   └── read/
│   │       ├── JdbcUserViewRepository.java
│   │       └── ElasticsearchOrderViewRepository.java
│   ├── messaging/
│   │   └── KafkaEventPublisher.java
│   └── config/
│       ├── CqrsConfig.java
│       └── PersistenceConfig.java
│
└── interfaces/
    └── rest/
        ├── command/
        │   ├── UserCommandController.java
        │   └── OrderCommandController.java
        ├── query/
        │   ├── UserQueryController.java
        │   └── OrderQueryController.java
        └── dto/
            ├── CreateUserRequest.java
            └── PlaceOrderRequest.java

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
│   ├── command/                        # Write side
│   │   ├── user/
│   │   │   ├── create_user.go          # Command + Handler
│   │   │   ├── update_user.go
│   │   │   └── delete_user.go
│   │   ├── order/
│   │   │   ├── place_order.go
│   │   │   └── cancel_order.go
│   │   └── bus/
│   │       └── command_bus.go          # Interface + Impl
│   │
│   ├── query/                          # Read side
│   │   ├── user/
│   │   │   ├── get_user.go             # Query + Handler + Result
│   │   │   ├── list_users.go
│   │   │   └── search_users.go
│   │   ├── order/
│   │   │   ├── get_order.go
│   │   │   └── list_orders_by_user.go
│   │   └── bus/
│   │       └── query_bus.go
│   │
│   ├── domain/                         # Write model
│   │   ├── user/
│   │   │   ├── user.go
│   │   │   ├── repository.go           # Interface
│   │   │   └── events.go
│   │   └── order/
│   │       ├── order.go
│   │       ├── repository.go
│   │       └── events.go
│   │
│   ├── readmodel/                      # Read model (projections)
│   │   ├── user/
│   │   │   ├── user_view.go
│   │   │   ├── repository.go
│   │   │   └── projector.go
│   │   └── order/
│   │       ├── order_view.go
│   │       ├── repository.go
│   │       └── projector.go
│   │
│   ├── infrastructure/
│   │   ├── persistence/
│   │   │   ├── write/
│   │   │   │   ├── postgres_user_repo.go
│   │   │   │   └── postgres_order_repo.go
│   │   │   └── read/
│   │   │       ├── postgres_user_view_repo.go
│   │   │       └── elastic_order_view_repo.go
│   │   ├── messaging/
│   │   │   └── nats_event_publisher.go
│   │   └── config/
│   │       └── config.go
│   │
│   └── interfaces/
│       └── http/
│           ├── handler/
│           │   ├── user_command_handler.go
│           │   ├── user_query_handler.go
│           │   ├── order_command_handler.go
│           │   └── order_query_handler.go
│           ├── request/
│           │   ├── create_user.go
│           │   └── place_order.go
│           └── router/
│               └── router.go
│
├── pkg/
│   └── cqrs/
│       ├── command.go
│       └── query.go
├── go.mod
└── Makefile
```
