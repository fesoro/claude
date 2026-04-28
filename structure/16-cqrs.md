# CQRS (Lead)

CQRS read (Query) və write (Command) əməliyyatlarını ayrı model-lərə bölür.
Hər tərəfin öz data store-u ola bilər — öz yükünə optimallaşdırılmış.

**Əsas anlayışlar:**
- **Command** — State-i dəyişir, heç nə qaytarmır (yalnız ID qaytara bilər)
- **Query** — Data qaytarır, heç nə dəyişmir
- **Command Handler** — Command-i emal edir
- **Query Handler** — Query-ni emal edir
- **Read Model** — Sorğu üçün optimallaşdırılıb
- **Write Model** — Business rule-lar və write-lar üçün optimallaşdırılıb

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

---

## Sync vs Async Read Model

```
SYNC (Simple CQRS):
  Command Handler → saves to write DB
                  → immediately updates read model (same transaction)
  ✓ Consistency: read model həmişə up-to-date
  ✗ Performance: write operation iki DB-yə yazır

  Nə vaxt: Consistency kritikdir, traffic azdır

ASYNC (Full CQRS with Eventual Consistency):
  Command Handler → saves to write DB → publishes DomainEvent
  Projector (async) ← subscribes to event → updates read model

  ✓ Performance: write path yüngüldür
  ✓ Read model fərqli storage-da ola bilər (Elasticsearch, Redis)
  ✗ Lag: read model bir neçə millisaniyə geri qala bilər
  ✗ "Read your own writes" problemi (həll: version check)

  Nə vaxt: High read load, read model fərqli storage tələb edir
```

---

## Command Bus Middleware (Laravel)

```php
// Command Bus with middleware pipeline

// CommandBus.php
class CommandBus
{
    private array $middleware = [];

    public function dispatch(Command $command): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($carry, $middleware) => fn($cmd) => $middleware->handle($cmd, $carry),
            fn($cmd) => $this->resolve($cmd)->handle($cmd)
        );

        return $pipeline($command);
    }
}

// Middleware stack:
// 1. ValidationMiddleware    — command DTO validate et
// 2. AuthorizationMiddleware — user bu command-ı icra edə bilərmi?
// 3. LoggingMiddleware       — command audit log
// 4. TransactionMiddleware   — DB transaction wrap
// 5. Handler                 — actual business logic

// PlaceOrderCommand — immutable DTO
final class PlaceOrderCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly array  $items,
        public readonly string $shippingAddress,
    ) {}
}

// PlaceOrderHandler
class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $cmd): string
    {
        $order = Order::place(
            userId:  UserId::fromString($cmd->userId),
            items:   $cmd->items,
            address: Address::fromString($cmd->shippingAddress),
        );

        $this->orderRepository->save($order);

        // Domain events → read model update (async via queue)
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventBus->publish($event);
        }

        return $order->id()->toString();
    }
}
```

---

## Eventual Consistency — "Read Your Own Writes"

```php
// Problem: user creates order, immediately redirects to order list
// Read model not yet updated → order not visible!

// Solution 1: Version-based polling
class OrderQueryController extends Controller
{
    public function index(Request $request)
    {
        $expectedVersion = $request->query('v'); // version after write

        if ($expectedVersion) {
            // Poll until read model catches up (max 2s)
            $retries = 0;
            while ($retries < 10) {
                $orders = $this->queryBus->dispatch(new ListOrdersQuery($request->user()->id));
                if ($orders->version >= $expectedVersion) break;
                usleep(200_000); // 200ms
                $retries++;
            }
        }

        return OrderResource::collection($orders);
    }
}

// Solution 2: Write-through to read model synchronously for critical paths
// Only use async for non-critical (analytics, reports)

// Solution 3: Return command result directly (bypass read model)
// POST /orders → returns created order data from write model
// Subsequent GET /orders uses (eventually consistent) read model
```

---

## Read Model Storage Seçimi

```
Write Model:       PostgreSQL (ACID, normalized)
Read Models:
  Order list:      PostgreSQL (separate read-optimized table, denormalized)
  Order search:    Elasticsearch (full-text, filters)
  Dashboard stats: Redis (pre-aggregated counters)
  Reports:         ClickHouse / BigQuery (analytical queries)

Projector nümunəsi (Laravel):
class OrderSummaryProjector
{
    public function onOrderPlaced(OrderPlaced $event): void
    {
        // Redis counter increment
        Redis::hincrby("user:{$event->userId}:stats", 'total_orders', 1);
        Redis::hincrbyfloat("user:{$event->userId}:stats", 'total_spent', $event->total);

        // Elasticsearch index
        $this->elasticsearch->index([
            'index' => 'orders',
            'id'    => $event->orderId,
            'body'  => [
                'user_id'    => $event->userId,
                'total'      => $event->total,
                'status'     => 'placed',
                'created_at' => $event->occurredAt,
                'items'      => $event->items,
            ],
        ]);
    }
}
```
