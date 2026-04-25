# CQRS (Lead ⭐⭐⭐⭐)

## İcmal
CQRS (Command Query Responsibility Segregation), write əməliyyatlarını (Commands) read əməliyyatlarından (Queries) tam ayırır. Hər iki tərəfin öz model-i, öz data path-i, öz optimizasiyaları olur. Bu, read-heavy sistemlər üçün miqyaslanma imkanı, mürəkkəb domain-lər üçün isə daha aydın kod strukturu verir.

## Niyə Vacibdir
Ənənəvi CRUD service-lərinin problemi: eyni model həm yazma, həm oxuma üçün istifadə olunur. Write-da validation, business rule-lar, aggregate invariant-lar vacibdir; read-da isə tez, denormalized, view-specific data lazımdır. Bu fərqli tələblər bir model-ə sıxışdırıldıqda ya query-lər yavaşlayır (JOIN çoxalır), ya da write logic if/else-lərə boğulur. CQRS hər tərəfi müstəqil optimize etməyə imkan verir.

## Əsas Anlayışlar
- **Command**: sistem state-ini dəyişdirir; `void` ya `CommandResult` qaytarır; side effect-lər var; adlandırma: imperative (`CreateOrder`, `UpdateUserEmail`, `CancelSubscription`)
- **Query**: state-i oxuyur; data qaytarır; heç bir side effect yoxdur; adlandırma: interrogative (`GetOrderDetails`, `ListUserOrders`, `FindActiveProducts`)
- **Command Handler**: bir command-ı emal edir; domain logic, validation, persistence
- **Query Handler**: bir query-ni emal edir; data oxuyur, DTO qaytarır; caching, denormalization mümkün
- **CommandBus / QueryBus**: command/query-ni uyğun handler-ə yönləndirir
- **Read Model / Projection**: query side üçün ayrı, denormalized data representation

## Praktik Baxış
- **Real istifadə**: e-commerce order management, banking transaction history, social feed (write rare, read very frequent), audit-critical systems, Event Sourcing ilə natural pair
- **Trade-off-lar**: write/read modelləri ayrı optimize olunur; read model scaling müstəqil; lakin eventual consistency (write → read sync delay); CRUD app-a görə əhəmiyyətli complexity artımı; debugging çətinləşir (data iki yerdədir)
- **İstifadə etməmək**: sadə CRUD admin panelləri üçün; startup MVP mərhələsindəki tez dəyişən model-lər üçün; kiçik team-lər üçün (cognitive overhead); read/write tərəfləri demək olar ki, eynidirsə
- **Common mistakes**: CQRS-i yalnız class-ların adlandırması kimi görmək (real ayrılma olmadan); read model-i yalnız `SELECT *`-a dəyişdirmək; command handler-i query kimi istifadə etmək (state dəyişdirib data qaytarmaq)

## Nümunələr

### Ümumi Nümunə
Bank sistemi: pul köçürmə (command) ilə hesab balansı görmə (query) tamam fərqli tələblərə malikdir. Köçürmə zamanı: fraud check, limit validation, transaction log, balance update — hər şey atomik olmalıdır. Balans görəndə: cəld, cached, mövcud bütün hesabları göstər — eventual consistency qəbul edilə bilər. CQRS bu iki fərqli dünyaya ayrı stack verir.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\CQRS;

// ─────────────────────────────────────────────
// COMMAND SIDE
// ─────────────────────────────────────────────

// Command — immutable data bag; side effects olacaq
final class CreateOrderCommand
{
    public function __construct(
        public readonly string $customerId,
        public readonly array  $items,    // [['product_id' => ..., 'quantity' => ...], ...]
        public readonly string $currency = 'USD',
    ) {}
}

final class CancelOrderCommand
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $cancelledBy,
        public readonly string $reason,
    ) {}
}

// Command Handler
class CreateOrderHandler
{
    public function __construct(
        private OrderRepository             $orders,
        private ProductRepository           $products,
        private \Illuminate\Events\Dispatcher $events,
    ) {}

    public function handle(CreateOrderCommand $command): string  // order ID qaytarır
    {
        // Validate
        $this->ensureCustomerExists($command->customerId);

        // Build aggregate
        $order = Order::create(
            customerId: $command->customerId,
            currency: $command->currency,
        );

        foreach ($command->items as $item) {
            $product = $this->products->findById($item['product_id']);
            $order->addItem($product, $item['quantity']);
        }

        // Persist
        $this->orders->save($order);

        // Raise domain events
        $this->events->dispatch(new OrderCreatedEvent($order->id, $command->customerId));

        return $order->id;
    }

    private function ensureCustomerExists(string $customerId): void
    {
        if (!Customer::find($customerId)) {
            throw new \DomainException("Customer not found: {$customerId}");
        }
    }
}

// ─────────────────────────────────────────────
// QUERY SIDE
// ─────────────────────────────────────────────

// Query — side effect yoxdur
final class GetOrderDetailsQuery
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $requestingUserId,
    ) {}
}

final class ListUserOrdersQuery
{
    public function __construct(
        public readonly string $userId,
        public readonly int    $page = 1,
        public readonly int    $perPage = 20,
        public readonly string $status = 'all',
    ) {}
}

// Read DTO — view-specific data; domain model deyil
final class OrderDetailsDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly array  $items,       // simplified, denormalized
        public readonly float  $totalAmount,
        public readonly string $currency,
        public readonly \Carbon\Carbon $createdAt,
    ) {}
}

// Query Handler — sadəcə oxuyur, optimize oluna bilər
class GetOrderDetailsHandler
{
    public function handle(GetOrderDetailsQuery $query): OrderDetailsDTO
    {
        // Read model-dən oxuyuruq — domain model deyil, view model
        // N+1 problemi yoxdur: JOIN ilə bir sorğu
        $row = \DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->select([
                'orders.id',
                'orders.status',
                'orders.total_amount',
                'orders.currency',
                'orders.created_at',
                'customers.name as customer_name',
                'customers.email as customer_email',
            ])
            ->where('orders.id', $query->orderId)
            ->first();

        if (!$row) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Order not found");
        }

        $items = \DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(['order_items.quantity', 'order_items.unit_price', 'products.name'])
            ->where('order_items.order_id', $query->orderId)
            ->get()
            ->toArray();

        return new OrderDetailsDTO(
            id: $row->id,
            status: $row->status,
            customerName: $row->customer_name,
            customerEmail: $row->customer_email,
            items: $items,
            totalAmount: $row->total_amount,
            currency: $row->currency,
            createdAt: \Carbon\Carbon::parse($row->created_at),
        );
    }
}
```

**CommandBus / QueryBus — Laravel binding:**

```php
<?php

// Manual CommandBus
class CommandBus
{
    private array $handlers = [];

    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function dispatch(object $command): mixed
    {
        $handlerClass = $this->handlers[$command::class]
            ?? throw new \RuntimeException('No handler registered for ' . $command::class);

        $handler = app($handlerClass);  // Laravel container-dan resolve
        return $handler->handle($command);
    }
}

// AppServiceProvider
public function register(): void
{
    $this->app->singleton(CommandBus::class, function () {
        $bus = new CommandBus();
        $bus->register(CreateOrderCommand::class, CreateOrderHandler::class);
        $bus->register(CancelOrderCommand::class, CancelOrderHandler::class);
        return $bus;
    });

    $this->app->singleton(QueryBus::class, function () {
        $bus = new QueryBus();
        $bus->register(GetOrderDetailsQuery::class, GetOrderDetailsHandler::class);
        $bus->register(ListUserOrdersQuery::class, ListUserOrdersHandler::class);
        return $bus;
    });
}

// Controller — clean, no business logic
class OrderController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus   $queryBus,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $command = new CreateOrderCommand(
            customerId: $request->user()->id,
            items: $request->validated('items'),
        );

        $orderId = $this->commandBus->dispatch($command);

        return response()->json(['order_id' => $orderId], 201);
    }

    public function show(string $orderId, Request $request): JsonResponse
    {
        $query = new GetOrderDetailsQuery(
            orderId: $orderId,
            requestingUserId: $request->user()->id,
        );

        $details = $this->queryBus->dispatch($query);

        return response()->json($details);
    }
}
```

**Separate Read Model — performance optimization:**

```php
<?php

// CQRS + ayrı read DB: write MySQL, read replica və ya denormalized table
// Event listener: write tamamlandıqdan sonra read model güncəllənir

class UpdateOrderReadModelListener
{
    public function handle(OrderCreatedEvent $event): void
    {
        // orders_read_model cədvəlini güncəllə — denormalized, query-friendly
        \DB::table('orders_read_model')->insert([
            'id'            => $event->orderId,
            'customer_name' => Customer::find($event->customerId)->name,
            'status'        => 'pending',
            'item_count'    => Order::find($event->orderId)->items()->count(),
            'total'         => Order::find($event->orderId)->total_amount,
            'created_at'    => now(),
        ]);
    }
}
```

## Praktik Tapşırıqlar
1. Mövcud bir CRUD controller-i götürün (`OrderController`-in `store` + `show` metodları); command/query obyektlərinə ayırın; handler-lər yaradın; controller yalnız bus.dispatch() çağırsın
2. `CommandBus` class-ı yazın: `register()` + `dispatch()`; 3 command + 3 handler; PHPUnit test: hər command düzgün handler-ə yönləndirilirmi?
3. Read model üçün ayrı denormalized cədvəl (`user_stats_read`) yaradın: `total_orders`, `total_spent`, `last_order_date`; `UserOrderPlaced` event-i cədvəli güncəlləsin; `GetUserStatsQuery` handler bu cədvəldən oxusun
4. CQRS-i Event Sourcing ilə birləşdirin: command handler event store-a write edir; projection listener read model-i güncəlləyir; tam `OrderLifecycle` simulation

## Əlaqəli Mövzular
- [Event Sourcing](29-event-sourcing.md) — CQRS ilə natural pair; command events generate edir, query projection-dan oxuyur
- [Command Pattern](11-command.md) — CQRS-in command tərəfi bu pattern üzərindədir
- [Mediator](23-mediator.md) — CommandBus mediator-dır; command-ı handler-ə yönləndirir
- [Repository Pattern](../php/topics/) — command handler repository-dən, query handler DB-dən birbaşa oxuya bilər
- [DDD Tactical Patterns](26-ddd-patterns.md) — CQRS DDD aggregate-ləri ilə birlikdə tez-tez işlənir
