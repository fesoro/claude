# CQRS (Command Query Responsibility Segregation) (Senior)

## Mündəricat
1. [CQRS nədir, niyə lazımdır](#cqrs-nədir-niyə-lazımdır)
2. [Command vs Query ayrılması](#command-vs-query-ayrılması)
3. [Read Model vs Write Model](#read-model-vs-write-model)
4. [CQRS + Event Sourcing](#cqrs--event-sourcing)
5. [Command Bus Pattern](#command-bus-pattern)
6. [Query Bus Pattern](#query-bus-pattern)
7. [Laravel-də CQRS Implementation](#laravel-də-cqrs-implementation)
8. [Real Nümunə: E-commerce Order Sistemi](#real-nümunə-e-commerce-order-sistemi)
9. [Read Database vs Write Database](#read-database-vs-write-database)
10. [Projections / Read Model Update](#projections--read-model-update)
11. [CQRS Üstünlükləri və Mənfi Cəhətləri](#cqrs-üstünlükləri-və-mənfi-cəhətləri)
12. [Nə vaxt CQRS istifadə etməli](#nə-vaxt-cqrs-istifadə-etməli)
13. [CQRS + DDD](#cqrs--ddd)
14. [İntervyu Sualları və Cavabları](#intervyu-sualları-və-cavabları)

---

## CQRS nədir, niyə lazımdır

CQRS (Command Query Responsibility Segregation) - **oxuma (read)** və **yazma (write)** əməliyyatlarını **ayrı modellərdə** idarə etmək prinsipidir. Bertrand Meyer-in CQS (Command Query Separation) prinsipinin arxitektura səviyyəsində tətbiqidir.

**CQS Prinsipi (metod səviyyəsində):**
- Hər metod ya **command** (state dəyişdirir, nəticə qaytarmır) ya da **query** (state dəyişdirmir, nəticə qaytarır) olmalıdır.

**CQRS Prinsipi (arxitektura səviyyəsində):**
- Oxuma və yazma əməliyyatları üçün **fərqli modellər**, potensial olaraq **fərqli verilənlər bazaları** istifadə olunur.

```
Ənənəvi yanaşma (eyni model):
┌─────────────────────────────┐
│         OrderModel          │
│  ┌──────────┬────────────┐  │
│  │  Create   │  GetById   │  │
│  │  Update   │  GetAll    │  │
│  │  Delete   │  Search    │  │
│  │  (Write)  │  (Read)    │  │
│  └──────────┴────────────┘  │
│         ┌──────┐            │
│         │  DB  │            │
│         └──────┘            │
└─────────────────────────────┘

CQRS yanaşması (ayrı modellər):
┌──────────────┐   ┌──────────────┐
│ Write Model  │   │  Read Model  │
│              │   │              │
│ PlaceOrder   │   │ GetOrder     │
│ CancelOrder  │   │ SearchOrders │
│ UpdateStatus │   │ GetReport    │
│              │   │              │
│  ┌────────┐  │   │  ┌────────┐  │
│  │Write DB│  │   │  │Read DB │  │
│  └────────┘  │   │  └────────┘  │
└──────────────┘   └──────────────┘
```

**Niyə lazımdır:**

1. **Performans** - Read və write yükləri müstəqil scale edilir
2. **Sadəlik** - Hər model öz məsuliyyətinə fokuslanır
3. **Optimallaşdırma** - Read model sorğular üçün, write model business logic üçün optimallaşdırılır
4. **Elastiklik** - Read üçün denormalized data, write üçün normalized data istifadə edilə bilər
5. **Security** - Read və write üçün fərqli permission-lar tətbiq oluna bilər

---

## Command vs Query ayrılması

### Command (Əmr)

Command **state dəyişdirən** əməliyyatdır. Business logic burada cəmlənir.

*Command **state dəyişdirən** əməliyyatdır. Business logic burada cəmlə üçün kod nümunəsi:*
```php
// Bu kod CQRS command strukturunu göstərir
namespace App\Commands;

// Command class-ı - yalnız data daşıyır, logic yoxdur
class PlaceOrderCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
        public readonly string $shippingAddress,
        public readonly string $paymentMethod,
        public readonly ?string $couponCode = null,
    ) {}
}

class UpdateOrderStatusCommand
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $newStatus,
        public readonly ?string $note = null,
    ) {}
}

class CancelOrderCommand
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $reason,
        public readonly int $cancelledBy,
    ) {}
}
```

### Query (Sorğu)

Query **state dəyişdirməyən**, yalnız data qaytaran əməliyyatdır.

*Query **state dəyişdirməyən**, yalnız data qaytaran əməliyyatdır üçün kod nümunəsi:*
```php
// Bu kod CQRS query strukturunu göstərir
namespace App\Queries;

// Query class-ı - hansı data lazım olduğunu bildirir
class GetOrderDetailsQuery
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $requestedBy,
    ) {}
}

class ListUserOrdersQuery
{
    public function __construct(
        public readonly int $userId,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $status = null,
        public readonly ?string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
    ) {}
}

class SearchOrdersQuery
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?string $status = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?float $minAmount = null,
        public readonly ?float $maxAmount = null,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {}
}

class GetOrderStatisticsQuery
{
    public function __construct(
        public readonly string $period = 'monthly', // daily, weekly, monthly, yearly
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
    ) {}
}
```

---

## Read Model vs Write Model

### Write Model (Domain Model)

Write Model business logic-i əhatə edir, normalized data istifadə edir və domain qaydalarını tətbiq edir.

*Write Model business logic-i əhatə edir, normalized data istifadə edir üçün kod nümunəsi:*
```php
// Bu kod CQRS write model (domain model) strukturunu göstərir
namespace App\Domain\Order;

// Write Model - business logic, validation, domain rules
class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'shipping_address_id',
        'payment_method',
        'coupon_id',
    ];

    // Relationships (normalized)
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // Business logic
    public function confirm(): void
    {
        if ($this->status !== OrderStatus::PENDING->value) {
            throw new InvalidOrderStateException(
                "Yalnız pending statusdakı sifarişlər təsdiqlənə bilər. Cari status: {$this->status}"
            );
        }

        if ($this->items->isEmpty()) {
            throw new EmptyOrderException('Boş sifariş təsdiqlənə bilməz.');
        }

        $this->update([
            'status' => OrderStatus::CONFIRMED->value,
            'confirmed_at' => now(),
        ]);

        event(new OrderConfirmed($this));
    }

    public function cancel(string $reason): void
    {
        $nonCancellableStatuses = [
            OrderStatus::SHIPPED->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::CANCELLED->value,
        ];

        if (in_array($this->status, $nonCancellableStatuses)) {
            throw new InvalidOrderStateException(
                "Bu statusda sifariş ləğv edilə bilməz: {$this->status}"
            );
        }

        $this->update([
            'status' => OrderStatus::CANCELLED->value,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        event(new OrderCancelled($this, $reason));
    }

    public function ship(string $trackingNumber): void
    {
        if ($this->status !== OrderStatus::CONFIRMED->value) {
            throw new InvalidOrderStateException('Yalnız təsdiqlənmiş sifarişlər göndərilə bilər.');
        }

        $this->update([
            'status' => OrderStatus::SHIPPED->value,
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber,
        ]);

        event(new OrderShipped($this, $trackingNumber));
    }

    public function calculateTotal(): float
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        $discount = $this->coupon
            ? $this->coupon->calculateDiscount($subtotal)
            : 0;

        $taxableAmount = $subtotal - $discount;
        $tax = round($taxableAmount * 0.18, 2); // 18% ƏDV

        return round($taxableAmount + $tax, 2);
    }
}
```

### Read Model (View/Projection)

Read Model oxuma üçün optimallaşdırılmış, denormalized data istifadə edir. Heç bir business logic yoxdur.

*Read Model oxuma üçün optimallaşdırılmış, denormalized data istifadə e üçün kod nümunəsi:*
```php
// Bu kod CQRS read model strukturunu göstərir
namespace App\ReadModels;

// Read Model - denormalized, oxuma üçün optimallaşdırılmış
class OrderReadModel extends Model
{
    protected $table = 'order_read_models'; // Ayrı cədvəl

    protected $fillable = [
        'order_id',
        'order_number',
        'user_id',
        'user_name',
        'user_email',
        'status',
        'status_label',
        'item_count',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'currency',
        'formatted_total',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_country',
        'payment_method',
        'payment_method_label',
        'tracking_number',
        'items_summary',  // JSON: [{name, quantity, price}]
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'items_summary' => 'array',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // No relationships needed - data is denormalized!
    // No business logic!

    // Scope-lar (oxuma üçün filtrlər)
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_number', 'LIKE', "%{$term}%")
              ->orWhere('user_name', 'LIKE', "%{$term}%")
              ->orWhere('user_email', 'LIKE', "%{$term}%");
        });
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        return $query;
    }
}

// Read Model Migration
Schema::create('order_read_models', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('order_id')->unique();
    $table->string('order_number')->index();
    $table->unsignedBigInteger('user_id')->index();
    $table->string('user_name');
    $table->string('user_email');
    $table->string('status')->index();
    $table->string('status_label');
    $table->unsignedInteger('item_count');
    $table->decimal('subtotal', 12, 2);
    $table->decimal('tax_amount', 12, 2);
    $table->decimal('discount_amount', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->index();
    $table->string('currency', 3)->default('AZN');
    $table->string('formatted_total');
    $table->string('shipping_address_line1')->nullable();
    $table->string('shipping_address_line2')->nullable();
    $table->string('shipping_city')->nullable();
    $table->string('shipping_country')->nullable();
    $table->string('payment_method');
    $table->string('payment_method_label');
    $table->string('tracking_number')->nullable();
    $table->json('items_summary');
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancellation_reason')->nullable();
    $table->timestamps();

    // Composite index for common queries
    $table->index(['user_id', 'status', 'created_at']);
    $table->index(['status', 'created_at']);
});
```

---

## CQRS + Event Sourcing

CQRS Event Sourcing ilə birlikdə çox güclü bir kombinasiya yaradır. Write tərəfi event-ləri event store-a yazır, read tərəfi bu event-lərdən projeksiyalar yaradır.

```
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│  Command ──> Command Handler ──> Aggregate ──> Event Store   │
│                                                    │         │
│                                              Event Bus       │
│                                                    │         │
│  Query ──> Query Handler ──> Read Model <── Projector        │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

*└──────────────────────────────────────────────────────────────┘ üçün kod nümunəsi:*
```php
// 1. Command gəlir
class PlaceOrderCommand
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly array $items,
    ) {}
}

// 2. Command Handler aggregate-i yükləyir və əməliyyatı icra edir
class PlaceOrderHandler
{
    public function __construct(
        private EventStoreRepository $repository,
    ) {}

    public function handle(PlaceOrderCommand $command): void
    {
        // Aggregate yaradılır
        $order = OrderAggregate::create(
            orderId: $command->orderId,
            customerId: $command->customerId,
        );

        // Items əlavə edilir
        foreach ($command->items as $item) {
            $order->addItem(
                productId: $item['product_id'],
                quantity: $item['quantity'],
                unitPrice: $item['unit_price'],
            );
        }

        // Event-lər event store-a yazılır
        $this->repository->save($order);
    }
}

// 3. Event Store-a yazılan event-lər Projector tərəfindən dinlənir
class OrderProjector
{
    public function onOrderCreated(OrderCreated $event): void
    {
        OrderReadModel::create([
            'order_id' => $event->orderId,
            'customer_id' => $event->customerId,
            'status' => 'draft',
            'item_count' => 0,
            'total' => 0,
            'created_at' => $event->occurredOn,
        ]);
    }

    public function onOrderItemAdded(OrderItemAdded $event): void
    {
        $readModel = OrderReadModel::where('order_id', $event->orderId)->first();

        $items = $readModel->items_summary ?? [];
        $items[] = [
            'product_id' => $event->productId,
            'quantity' => $event->quantity,
            'unit_price' => $event->unitPrice,
            'line_total' => $event->quantity * $event->unitPrice,
        ];

        $readModel->update([
            'items_summary' => $items,
            'item_count' => count($items),
            'total' => collect($items)->sum('line_total'),
        ]);
    }

    public function onOrderConfirmed(OrderConfirmed $event): void
    {
        OrderReadModel::where('order_id', $event->orderId)
            ->update([
                'status' => 'confirmed',
                'confirmed_at' => $event->occurredOn,
            ]);
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        OrderReadModel::where('order_id', $event->orderId)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => $event->occurredOn,
                'cancellation_reason' => $event->reason,
            ]);
    }
}

// 4. Query handler read model-dən oxuyur
class GetOrderDetailsHandler
{
    public function handle(GetOrderDetailsQuery $query): OrderDetailsDTO
    {
        $readModel = OrderReadModel::where('order_id', $query->orderId)->firstOrFail();

        return new OrderDetailsDTO(
            orderId: $readModel->order_id,
            status: $readModel->status,
            items: $readModel->items_summary,
            total: $readModel->total,
            createdAt: $readModel->created_at,
        );
    }
}
```

---

## Command Bus Pattern

Command Bus command-ları uyğun handler-lərə yönləndirən vasitəçidir. Middleware-lər vasitəsilə cross-cutting concerns (logging, validation, transaction) tətbiq edir.

*Command Bus command-ları uyğun handler-lərə yönləndirən vasitəçidir. M üçün kod nümunəsi:*
```php
// Bu kod command bus implementasiyasını göstərir
namespace App\Bus;

// Command Bus Interface
interface CommandBusInterface
{
    public function dispatch(object $command): mixed;
}

// Command Bus Implementation
class CommandBus implements CommandBusInterface
{
    private array $handlers = [];
    private array $middlewares = [];

    public function __construct(
        private Container $container,
    ) {}

    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function addMiddleware(CommandMiddleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function dispatch(object $command): mixed
    {
        $commandClass = get_class($command);

        if (!isset($this->handlers[$commandClass])) {
            throw new CommandHandlerNotFoundException(
                "Handler tapılmadı: {$commandClass}"
            );
        }

        $handler = $this->container->make($this->handlers[$commandClass]);

        // Middleware chain
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function ($next, CommandMiddleware $middleware) {
                return function ($command) use ($middleware, $next) {
                    return $middleware->handle($command, $next);
                };
            },
            function ($command) use ($handler) {
                return $handler->handle($command);
            }
        );

        return $pipeline($command);
    }
}

// Command Handler Interface
interface CommandHandlerInterface
{
    public function handle(object $command): mixed;
}

// Middleware-lər
interface CommandMiddleware
{
    public function handle(object $command, Closure $next): mixed;
}

// Logging Middleware
class LoggingMiddleware implements CommandMiddleware
{
    public function handle(object $command, Closure $next): mixed
    {
        $commandClass = class_basename($command);

        Log::info("Command başladı: {$commandClass}", [
            'command' => get_object_vars($command),
        ]);

        $startTime = microtime(true);

        try {
            $result = $next($command);

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Command tamamlandı: {$commandClass}", [
                'duration_ms' => $duration,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("Command uğursuz: {$commandClass}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

// Transaction Middleware
class TransactionMiddleware implements CommandMiddleware
{
    public function handle(object $command, Closure $next): mixed
    {
        return DB::transaction(function () use ($command, $next) {
            return $next($command);
        });
    }
}

// Validation Middleware
class ValidationMiddleware implements CommandMiddleware
{
    public function __construct(
        private Container $container,
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        $validatorClass = get_class($command) . 'Validator';

        if (class_exists($validatorClass)) {
            $validator = $this->container->make($validatorClass);
            $validator->validate($command);
        }

        return $next($command);
    }
}

// Authorization Middleware
class AuthorizationMiddleware implements CommandMiddleware
{
    public function handle(object $command, Closure $next): mixed
    {
        if ($command instanceof AuthorizableCommand) {
            $user = auth()->user();

            if (!$command->authorize($user)) {
                throw new UnauthorizedException(
                    'Bu əməliyyat üçün icazəniz yoxdur.'
                );
            }
        }

        return $next($command);
    }
}
```

### Command Handler nümunələri

*Command Handler nümunələri üçün kod nümunəsi:*
```php
// PlaceOrder Command Handler
class PlaceOrderHandler implements CommandHandlerInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private CouponService $couponService,
    ) {}

    public function handle(object $command): Order
    {
        /** @var PlaceOrderCommand $command */

        // Məhsulların mövcudluğunu yoxla
        $orderItems = [];
        $subtotal = 0;

        foreach ($command->items as $item) {
            $product = $this->productRepository->findOrFail($item['product_id']);

            if ($product->stock < $item['quantity']) {
                throw new InsufficientStockException(
                    "Məhsul '{$product->name}' üçün kifayət qədər stok yoxdur. " .
                    "Tələb: {$item['quantity']}, Mövcud: {$product->stock}"
                );
            }

            $lineTotal = $product->price * $item['quantity'];
            $subtotal += $lineTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'line_total' => $lineTotal,
            ];
        }

        // Kupon tətbiqi
        $discount = 0;
        if ($command->couponCode) {
            $coupon = $this->couponService->validateAndGet($command->couponCode);
            $discount = $coupon->calculateDiscount($subtotal);
        }

        // Vergi hesablanması
        $taxableAmount = $subtotal - $discount;
        $taxAmount = round($taxableAmount * 0.18, 2);
        $total = round($taxableAmount + $taxAmount, 2);

        // Sifariş yaratma
        $order = $this->orderRepository->create([
            'user_id' => $command->userId,
            'order_number' => Order::generateOrderNumber(),
            'status' => OrderStatus::PENDING->value,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'shipping_address' => $command->shippingAddress,
            'payment_method' => $command->paymentMethod,
            'coupon_code' => $command->couponCode,
        ]);

        // Order items yaratma
        foreach ($orderItems as $item) {
            $order->items()->create($item);
        }

        // Event yayımlama
        event(new OrderPlaced($order));

        return $order;
    }
}

// CancelOrder Command Handler
class CancelOrderHandler implements CommandHandlerInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PaymentService $paymentService,
        private InventoryService $inventoryService,
    ) {}

    public function handle(object $command): void
    {
        /** @var CancelOrderCommand $command */
        $order = $this->orderRepository->findOrFail($command->orderId);

        // Authorization yoxlaması
        if ($order->user_id !== $command->cancelledBy) {
            $user = User::find($command->cancelledBy);
            if (!$user?->isAdmin()) {
                throw new UnauthorizedException('Bu sifarişi ləğv etmək hüququnuz yoxdur.');
            }
        }

        // Domain logic
        $order->cancel($command->reason);

        // Ödəniş geri qaytarılması
        if ($order->payment && $order->payment->status === 'completed') {
            $this->paymentService->refund($order->payment);
        }

        // Stok geri qaytarılması
        $this->inventoryService->releaseStock($order);
    }
}
```

---

## Query Bus Pattern

*Query Bus Pattern üçün kod nümunəsi:*
```php
// Bu kod query bus implementasiyasını göstərir
namespace App\Bus;

// Query Bus Interface
interface QueryBusInterface
{
    public function ask(object $query): mixed;
}

// Query Bus Implementation
class QueryBus implements QueryBusInterface
{
    private array $handlers = [];

    public function __construct(
        private Container $container,
    ) {}

    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }

    public function ask(object $query): mixed
    {
        $queryClass = get_class($query);

        if (!isset($this->handlers[$queryClass])) {
            throw new QueryHandlerNotFoundException(
                "Query handler tapılmadı: {$queryClass}"
            );
        }

        $handler = $this->container->make($this->handlers[$queryClass]);

        return $handler->handle($query);
    }
}

// Query Handler nümunələri
class GetOrderDetailsHandler
{
    public function __construct(
        private OrderReadModel $readModel,
    ) {}

    public function handle(GetOrderDetailsQuery $query): OrderDetailsDTO
    {
        $order = $this->readModel
            ->where('order_id', $query->orderId)
            ->first();

        if (!$order) {
            throw new OrderNotFoundException("Sifariş tapılmadı: {$query->orderId}");
        }

        // Authorization
        if ($order->user_id !== $query->requestedBy) {
            $user = User::find($query->requestedBy);
            if (!$user?->isAdmin()) {
                throw new UnauthorizedException('Bu sifarişə baxmaq hüququnuz yoxdur.');
            }
        }

        return new OrderDetailsDTO(
            id: $order->order_id,
            orderNumber: $order->order_number,
            status: $order->status,
            statusLabel: $order->status_label,
            userName: $order->user_name,
            userEmail: $order->user_email,
            items: $order->items_summary,
            itemCount: $order->item_count,
            subtotal: $order->subtotal,
            taxAmount: $order->tax_amount,
            discountAmount: $order->discount_amount,
            total: $order->total,
            formattedTotal: $order->formatted_total,
            shippingAddress: [
                'line1' => $order->shipping_address_line1,
                'line2' => $order->shipping_address_line2,
                'city' => $order->shipping_city,
                'country' => $order->shipping_country,
            ],
            paymentMethod: $order->payment_method_label,
            trackingNumber: $order->tracking_number,
            createdAt: $order->created_at,
            confirmedAt: $order->confirmed_at,
            shippedAt: $order->shipped_at,
            deliveredAt: $order->delivered_at,
        );
    }
}

class ListUserOrdersHandler
{
    public function handle(ListUserOrdersQuery $query): PaginatedResultDTO
    {
        $result = OrderReadModel::forUser($query->userId)
            ->when($query->status, fn ($q) => $q->withStatus($query->status))
            ->orderBy($query->sortBy, $query->sortDirection)
            ->paginate($query->perPage, ['*'], 'page', $query->page);

        return new PaginatedResultDTO(
            items: $result->map(fn ($order) => OrderListItemDTO::fromReadModel($order)),
            total: $result->total(),
            perPage: $result->perPage(),
            currentPage: $result->currentPage(),
            lastPage: $result->lastPage(),
        );
    }
}

class GetOrderStatisticsHandler
{
    public function handle(GetOrderStatisticsQuery $query): OrderStatisticsDTO
    {
        $baseQuery = OrderReadModel::query()
            ->dateRange($query->dateFrom, $query->dateTo);

        return new OrderStatisticsDTO(
            totalOrders: (clone $baseQuery)->count(),
            totalRevenue: (clone $baseQuery)
                ->where('status', '!=', 'cancelled')
                ->sum('total'),
            averageOrderValue: (clone $baseQuery)
                ->where('status', '!=', 'cancelled')
                ->avg('total'),
            cancelledOrders: (clone $baseQuery)
                ->where('status', 'cancelled')
                ->count(),
            statusBreakdown: (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as count, SUM(total) as total_revenue')
                ->groupBy('status')
                ->get()
                ->toArray(),
        );
    }
}
```

### DTO-lar (Data Transfer Objects)

*DTO-lar (Data Transfer Objects) üçün kod nümunəsi:*
```php
// Bu kod CQRS-da istifadə olunan DTO strukturunu göstərir
namespace App\DTOs;

class OrderDetailsDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly array $items,
        public readonly int $itemCount,
        public readonly float $subtotal,
        public readonly float $taxAmount,
        public readonly float $discountAmount,
        public readonly float $total,
        public readonly string $formattedTotal,
        public readonly array $shippingAddress,
        public readonly string $paymentMethod,
        public readonly ?string $trackingNumber,
        public readonly Carbon $createdAt,
        public readonly ?Carbon $confirmedAt,
        public readonly ?Carbon $shippedAt,
        public readonly ?Carbon $deliveredAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'customer' => [
                'name' => $this->userName,
                'email' => $this->userEmail,
            ],
            'items' => $this->items,
            'item_count' => $this->itemCount,
            'pricing' => [
                'subtotal' => $this->subtotal,
                'tax' => $this->taxAmount,
                'discount' => $this->discountAmount,
                'total' => $this->total,
                'formatted_total' => $this->formattedTotal,
            ],
            'shipping_address' => $this->shippingAddress,
            'payment_method' => $this->paymentMethod,
            'tracking_number' => $this->trackingNumber,
            'dates' => [
                'created_at' => $this->createdAt->toISOString(),
                'confirmed_at' => $this->confirmedAt?->toISOString(),
                'shipped_at' => $this->shippedAt?->toISOString(),
                'delivered_at' => $this->deliveredAt?->toISOString(),
            ],
        ];
    }
}

class OrderListItemDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly int $itemCount,
        public readonly string $formattedTotal,
        public readonly Carbon $createdAt,
    ) {}

    public static function fromReadModel(OrderReadModel $model): self
    {
        return new self(
            id: $model->order_id,
            orderNumber: $model->order_number,
            status: $model->status,
            statusLabel: $model->status_label,
            itemCount: $model->item_count,
            formattedTotal: $model->formatted_total,
            createdAt: $model->created_at,
        );
    }
}
```

---

## Laravel-də CQRS Implementation

### Service Provider ilə qeydiyyat

*Service Provider ilə qeydiyyat üçün kod nümunəsi:*
```php
// Bu kod CQRS handler-lərini service provider-də qeydiyyatdan keçirməyi göstərir
namespace App\Providers;

class CQRSServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Command Bus
        $this->app->singleton(CommandBusInterface::class, function ($app) {
            $bus = new CommandBus($app);

            // Middleware-ləri əlavə et
            $bus->addMiddleware(new LoggingMiddleware());
            $bus->addMiddleware(new AuthorizationMiddleware());
            $bus->addMiddleware(new ValidationMiddleware($app));
            $bus->addMiddleware(new TransactionMiddleware());

            // Command handler-ləri qeydiyyat et
            $bus->register(PlaceOrderCommand::class, PlaceOrderHandler::class);
            $bus->register(CancelOrderCommand::class, CancelOrderHandler::class);
            $bus->register(UpdateOrderStatusCommand::class, UpdateOrderStatusHandler::class);
            $bus->register(AddOrderItemCommand::class, AddOrderItemHandler::class);

            return $bus;
        });

        // Query Bus
        $this->app->singleton(QueryBusInterface::class, function ($app) {
            $bus = new QueryBus($app);

            // Query handler-ləri qeydiyyat et
            $bus->register(GetOrderDetailsQuery::class, GetOrderDetailsHandler::class);
            $bus->register(ListUserOrdersQuery::class, ListUserOrdersHandler::class);
            $bus->register(SearchOrdersQuery::class, SearchOrdersHandler::class);
            $bus->register(GetOrderStatisticsQuery::class, GetOrderStatisticsHandler::class);

            return $bus;
        });
    }
}
```

### Auto-discovery ilə qeydiyyat

*Auto-discovery ilə qeydiyyat üçün kod nümunəsi:*
```php
// Handler-ləri avtomatik tapmaq üçün attribute istifadə
#[AsCommandHandler]
class PlaceOrderHandler
{
    #[HandlesCommand(PlaceOrderCommand::class)]
    public function handle(PlaceOrderCommand $command): Order
    {
        // ...
    }
}

#[AsQueryHandler]
class GetOrderDetailsHandler
{
    #[HandlesQuery(GetOrderDetailsQuery::class)]
    public function handle(GetOrderDetailsQuery $query): OrderDetailsDTO
    {
        // ...
    }
}

// Auto-discovery Service Provider
class CQRSAutoDiscoveryProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommandBusInterface::class, function ($app) {
            $bus = new CommandBus($app);

            // App\Commands\Handlers namespace-dəki bütün handler-ləri tap
            $handlers = $this->discoverHandlers(
                app_path('Commands/Handlers'),
                'App\\Commands\\Handlers',
            );

            foreach ($handlers as $commandClass => $handlerClass) {
                $bus->register($commandClass, $handlerClass);
            }

            return $bus;
        });
    }

    private function discoverHandlers(string $directory, string $namespace): array
    {
        $handlers = [];

        foreach (glob("{$directory}/*.php") as $file) {
            $className = $namespace . '\\' . basename($file, '.php');
            $reflection = new ReflectionClass($className);

            foreach ($reflection->getMethods() as $method) {
                if ($method->getName() === 'handle' && $method->getNumberOfParameters() > 0) {
                    $commandType = $method->getParameters()[0]->getType()?->getName();
                    if ($commandType) {
                        $handlers[$commandType] = $className;
                    }
                }
            }
        }

        return $handlers;
    }
}
```

### Controller-lərdə istifadə

*Controller-lərdə istifadə üçün kod nümunəsi:*
```php
// Bu kod CQRS pattern-ini Laravel controller-lərdə istifadəsini göstərir
namespace App\Http\Controllers\Api;

class OrderController extends Controller
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {}

    // Write əməliyyatları (Command)
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $command = new PlaceOrderCommand(
            userId: auth()->id(),
            items: $request->validated('items'),
            shippingAddress: $request->validated('shipping_address'),
            paymentMethod: $request->validated('payment_method'),
            couponCode: $request->validated('coupon_code'),
        );

        $order = $this->commandBus->dispatch($command);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
            ],
            'message' => 'Sifariş uğurla yaradıldı',
        ], 201);
    }

    public function cancel(CancelOrderRequest $request, int $orderId): JsonResponse
    {
        $command = new CancelOrderCommand(
            orderId: $orderId,
            reason: $request->validated('reason'),
            cancelledBy: auth()->id(),
        );

        $this->commandBus->dispatch($command);

        return response()->json([
            'message' => 'Sifariş ləğv edildi',
        ]);
    }

    public function updateStatus(UpdateStatusRequest $request, int $orderId): JsonResponse
    {
        $command = new UpdateOrderStatusCommand(
            orderId: $orderId,
            newStatus: $request->validated('status'),
            note: $request->validated('note'),
        );

        $this->commandBus->dispatch($command);

        return response()->json(['message' => 'Status yeniləndi']);
    }

    // Read əməliyyatları (Query)
    public function show(int $orderId): JsonResponse
    {
        $query = new GetOrderDetailsQuery(
            orderId: $orderId,
            requestedBy: auth()->id(),
        );

        $result = $this->queryBus->ask($query);

        return response()->json(['data' => $result->toArray()]);
    }

    public function index(ListOrdersRequest $request): JsonResponse
    {
        $query = new ListUserOrdersQuery(
            userId: auth()->id(),
            page: $request->validated('page', 1),
            perPage: $request->validated('per_page', 15),
            status: $request->validated('status'),
            sortBy: $request->validated('sort_by', 'created_at'),
            sortDirection: $request->validated('sort_direction', 'desc'),
        );

        $result = $this->queryBus->ask($query);

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'total' => $result->total,
                'per_page' => $result->perPage,
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
            ],
        ]);
    }

    public function search(SearchOrdersRequest $request): JsonResponse
    {
        $query = new SearchOrdersQuery(
            searchTerm: $request->validated('q'),
            status: $request->validated('status'),
            dateFrom: $request->validated('date_from'),
            dateTo: $request->validated('date_to'),
            minAmount: $request->validated('min_amount'),
            maxAmount: $request->validated('max_amount'),
            page: $request->validated('page', 1),
            perPage: $request->validated('per_page', 20),
        );

        $result = $this->queryBus->ask($query);

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'total' => $result->total,
                'current_page' => $result->currentPage,
            ],
        ]);
    }

    public function statistics(OrderStatisticsRequest $request): JsonResponse
    {
        $query = new GetOrderStatisticsQuery(
            period: $request->validated('period', 'monthly'),
            dateFrom: $request->validated('date_from'),
            dateTo: $request->validated('date_to'),
        );

        $result = $this->queryBus->ask($query);

        return response()->json(['data' => $result]);
    }
}
```

---

## Real Nümunə: E-commerce Order Sistemi

Tam CQRS implementasiyası ilə e-commerce sifariş sistemi.

### Fayl strukturu

```
app/
├── Commands/
│   ├── PlaceOrderCommand.php
│   ├── CancelOrderCommand.php
│   ├── UpdateOrderStatusCommand.php
│   └── Handlers/
│       ├── PlaceOrderHandler.php
│       ├── CancelOrderHandler.php
│       └── UpdateOrderStatusHandler.php
├── Queries/
│   ├── GetOrderDetailsQuery.php
│   ├── ListUserOrdersQuery.php
│   ├── SearchOrdersQuery.php
│   ├── GetOrderStatisticsQuery.php
│   └── Handlers/
│       ├── GetOrderDetailsHandler.php
│       ├── ListUserOrdersHandler.php
│       ├── SearchOrdersHandler.php
│       └── GetOrderStatisticsHandler.php
├── Bus/
│   ├── CommandBusInterface.php
│   ├── CommandBus.php
│   ├── QueryBusInterface.php
│   ├── QueryBus.php
│   └── Middleware/
│       ├── LoggingMiddleware.php
│       ├── TransactionMiddleware.php
│       ├── ValidationMiddleware.php
│       └── AuthorizationMiddleware.php
├── Domain/
│   └── Order/
│       ├── Order.php (Write Model)
│       ├── OrderItem.php
│       ├── OrderStatus.php
│       └── Events/
│           ├── OrderPlaced.php
│           ├── OrderConfirmed.php
│           ├── OrderShipped.php
│           └── OrderCancelled.php
├── ReadModels/
│   └── OrderReadModel.php
├── Projectors/
│   └── OrderProjector.php
├── DTOs/
│   ├── OrderDetailsDTO.php
│   ├── OrderListItemDTO.php
│   └── OrderStatisticsDTO.php
└── Http/
    └── Controllers/
        └── Api/
            └── OrderController.php
```

### Projector - Read Model-i yeniləyən

*Projector - Read Model-i yeniləyən üçün kod nümunəsi:*
```php
// Bu kod event-ləri dinləyərək read model-i yeniləyən projector-u göstərir
namespace App\Projectors;

class OrderProjector
{
    // OrderPlaced event-i gəldikdə read model yaradılır
    public function onOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order->load(['user', 'items.product', 'shippingAddress']);

        OrderReadModel::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $order->user_id,
            'user_name' => $order->user->name,
            'user_email' => $order->user->email,
            'status' => $order->status,
            'status_label' => $this->getStatusLabel($order->status),
            'item_count' => $order->items->count(),
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'discount_amount' => $order->discount_amount,
            'total' => $order->total,
            'currency' => 'AZN',
            'formatted_total' => number_format($order->total, 2) . ' AZN',
            'shipping_address_line1' => $order->shippingAddress?->line1,
            'shipping_address_line2' => $order->shippingAddress?->line2,
            'shipping_city' => $order->shippingAddress?->city,
            'shipping_country' => $order->shippingAddress?->country,
            'payment_method' => $order->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel($order->payment_method),
            'items_summary' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->toArray(),
        ]);
    }

    public function onOrderConfirmed(OrderConfirmed $event): void
    {
        OrderReadModel::where('order_id', $event->order->id)
            ->update([
                'status' => 'confirmed',
                'status_label' => 'Təsdiqləndi',
                'confirmed_at' => $event->order->confirmed_at,
            ]);
    }

    public function onOrderShipped(OrderShipped $event): void
    {
        OrderReadModel::where('order_id', $event->order->id)
            ->update([
                'status' => 'shipped',
                'status_label' => 'Göndərildi',
                'tracking_number' => $event->trackingNumber,
                'shipped_at' => $event->order->shipped_at,
            ]);
    }

    public function onOrderDelivered(OrderDelivered $event): void
    {
        OrderReadModel::where('order_id', $event->order->id)
            ->update([
                'status' => 'delivered',
                'status_label' => 'Çatdırıldı',
                'delivered_at' => $event->order->delivered_at,
            ]);
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        OrderReadModel::where('order_id', $event->order->id)
            ->update([
                'status' => 'cancelled',
                'status_label' => 'Ləğv edildi',
                'cancelled_at' => $event->order->cancelled_at,
                'cancellation_reason' => $event->reason,
            ]);
    }

    // User profili yeniləndikdə bütün read model-ləri yenilə
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        OrderReadModel::where('user_id', $event->user->id)
            ->update([
                'user_name' => $event->user->name,
                'user_email' => $event->user->email,
            ]);
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Gözləyir',
            'confirmed' => 'Təsdiqləndi',
            'processing' => 'Hazırlanır',
            'shipped' => 'Göndərildi',
            'delivered' => 'Çatdırıldı',
            'cancelled' => 'Ləğv edildi',
            default => $status,
        };
    }

    private function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            'credit_card' => 'Kredit kartı',
            'bank_transfer' => 'Bank köçürməsi',
            'cash_on_delivery' => 'Qapıda ödəmə',
            default => $method,
        };
    }
}

// EventServiceProvider-da qeydiyyat
protected $listen = [
    OrderPlaced::class => [OrderProjector::class . '@onOrderPlaced'],
    OrderConfirmed::class => [OrderProjector::class . '@onOrderConfirmed'],
    OrderShipped::class => [OrderProjector::class . '@onOrderShipped'],
    OrderDelivered::class => [OrderProjector::class . '@onOrderDelivered'],
    OrderCancelled::class => [OrderProjector::class . '@onOrderCancelled'],
    UserProfileUpdated::class => [OrderProjector::class . '@onUserProfileUpdated'],
];
```

---

## Read Database vs Write Database

Böyük miqyaslı sistemlərdə read və write üçün tamamilə ayrı verilənlər bazaları istifadə oluna bilər.

*Böyük miqyaslı sistemlərdə read və write üçün tamamilə ayrı verilənlər üçün kod nümunəsi:*
```php
// config/database.php
'connections' => [
    // Write database (Master)
    'mysql_write' => [
        'driver' => 'mysql',
        'host' => env('DB_WRITE_HOST', '127.0.0.1'),
        'port' => env('DB_WRITE_PORT', '3306'),
        'database' => env('DB_WRITE_DATABASE', 'orders_write'),
        'username' => env('DB_WRITE_USERNAME', 'root'),
        'password' => env('DB_WRITE_PASSWORD', ''),
    ],

    // Read database (Replica)
    'mysql_read' => [
        'driver' => 'mysql',
        'host' => env('DB_READ_HOST', '127.0.0.1'),
        'port' => env('DB_READ_PORT', '3306'),
        'database' => env('DB_READ_DATABASE', 'orders_read'),
        'username' => env('DB_READ_USERNAME', 'root'),
        'password' => env('DB_READ_PASSWORD', ''),
    ],
],

// Write Model - yazma database-inə qoşulur
class Order extends Model
{
    protected $connection = 'mysql_write';
    // ...
}

// Read Model - oxuma database-inə qoşulur
class OrderReadModel extends Model
{
    protected $connection = 'mysql_read';
    // ...
}
```

### Eventual Consistency idarəetməsi

*Eventual Consistency idarəetməsi üçün kod nümunəsi:*
```php
// Read model yenilənməsini asinxron etmək
class AsyncOrderProjector implements ShouldQueue
{
    public string $queue = 'projections';

    public function onOrderPlaced(OrderPlaced $event): void
    {
        // Bu asinxron işləyir - eventual consistency
        // Read database bir neçə saniyə geridə qala bilər

        $order = $event->order->load(['user', 'items', 'shippingAddress']);

        DB::connection('mysql_read')->table('order_read_models')->insert([
            // ...
        ]);
    }
}

// Controller-də eventually consistent cavab
class OrderController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->commandBus->dispatch(new PlaceOrderCommand(/* ... */));

        // Write model-dən birbaşa cavab qaytarırıq (strong consistency)
        // Read model hələ yenilənməyib ola bilər
        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
            ],
            'message' => 'Sifariş yaradıldı',
        ], 201);
    }

    public function show(int $orderId): JsonResponse
    {
        try {
            // Read model-dən oxu
            $result = $this->queryBus->ask(new GetOrderDetailsQuery(
                orderId: $orderId,
                requestedBy: auth()->id(),
            ));

            return response()->json(['data' => $result->toArray()]);
        } catch (OrderNotFoundException $e) {
            // Read model hələ yenilənməyib ola bilər
            // Write model-dən yoxla
            $order = Order::on('mysql_write')->find($orderId);

            if ($order) {
                // Mövcuddur amma read model hələ yaradılmayıb
                return response()->json([
                    'message' => 'Sifariş tapıldı, detallar qısa müddətdə əlçatan olacaq',
                    'data' => ['id' => $order->id, 'status' => $order->status],
                ], 202); // 202 Accepted
            }

            throw $e;
        }
    }
}
```

---

## Projections / Read Model Update

*Projections / Read Model Update üçün kod nümunəsi:*
```php
// Müxtəlif read model-lər müxtəlif ehtiyaclar üçün

// 1. Order List Projection - siyahı görünüşü üçün
class OrderListProjection
{
    public function project(OrderPlaced $event): void
    {
        DB::table('order_list_view')->insert([
            'order_id' => $event->order->id,
            'order_number' => $event->order->order_number,
            'customer_name' => $event->order->user->name,
            'status' => $event->order->status,
            'total' => $event->order->total,
            'item_count' => $event->order->items->count(),
            'created_at' => $event->order->created_at,
        ]);
    }
}

// 2. Order Detail Projection - təfərrüat görünüşü üçün
class OrderDetailProjection
{
    public function project(OrderPlaced $event): void
    {
        // Tam denormalized data saxlayır
        DB::table('order_detail_view')->insert([
            // Bütün əlaqəli data bir sətirdə
        ]);
    }
}

// 3. Dashboard Statistics Projection - statistika üçün
class DashboardProjection
{
    public function onOrderPlaced(OrderPlaced $event): void
    {
        $date = $event->order->created_at->format('Y-m-d');

        DB::table('daily_order_stats')
            ->updateOrInsert(
                ['date' => $date],
                [
                    'order_count' => DB::raw('order_count + 1'),
                    'total_revenue' => DB::raw("total_revenue + {$event->order->total}"),
                    'updated_at' => now(),
                ]
            );
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        $date = $event->order->created_at->format('Y-m-d');

        DB::table('daily_order_stats')
            ->where('date', $date)
            ->update([
                'cancelled_count' => DB::raw('cancelled_count + 1'),
                'total_revenue' => DB::raw("total_revenue - {$event->order->total}"),
                'updated_at' => now(),
            ]);
    }
}

// 4. Search Projection - Elasticsearch üçün
class OrderSearchProjection
{
    public function __construct(
        private Client $elasticsearch,
    ) {}

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order->load(['user', 'items.product']);

        $this->elasticsearch->index([
            'index' => 'orders',
            'id' => $order->id,
            'body' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->user->name,
                'customer_email' => $order->user->email,
                'status' => $order->status,
                'total' => $order->total,
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                ])->toArray(),
                'created_at' => $order->created_at->toISOString(),
            ],
        ]);
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->elasticsearch->update([
            'index' => 'orders',
            'id' => $event->order->id,
            'body' => [
                'doc' => [
                    'status' => $event->order->status,
                ],
            ],
        ]);
    }
}

// Projection Rebuild Command
class RebuildProjectionCommand extends Command
{
    protected $signature = 'projections:rebuild {name}';

    public function handle(): void
    {
        $name = $this->argument('name');

        $projector = match ($name) {
            'order-list' => app(OrderListProjection::class),
            'order-detail' => app(OrderDetailProjection::class),
            'dashboard' => app(DashboardProjection::class),
            'search' => app(OrderSearchProjection::class),
            default => throw new \InvalidArgumentException("Bilinməyən projection: {$name}"),
        };

        $this->info("'{$name}' projection-u yenidən qurulur...");

        // Mövcud data-nı təmizlə
        $projector->reset();

        // Bütün order-ları yenidən project et
        Order::with(['user', 'items.product', 'shippingAddress'])
            ->chunk(100, function ($orders) use ($projector) {
                foreach ($orders as $order) {
                    $projector->project(new OrderPlaced($order));

                    // Status dəyişiklikləri
                    if ($order->confirmed_at) {
                        $projector->onOrderConfirmed(new OrderConfirmed($order));
                    }
                    if ($order->shipped_at) {
                        $projector->onOrderShipped(new OrderShipped($order, $order->tracking_number));
                    }
                    if ($order->cancelled_at) {
                        $projector->onOrderCancelled(new OrderCancelled($order, $order->cancellation_reason));
                    }
                }
            });

        $this->info('Projection yenidən quruldu.');
    }
}
```

---

## CQRS Üstünlükləri və Mənfi Cəhətləri

### Üstünlükləri

1. **Independent Scaling** - Read və write yükləri müstəqil scale edilir. Adətən read 80-90% olur.
2. **Optimized Models** - Hər model öz məqsədinə uyğun optimallaşdırılır.
3. **Simplified Queries** - Read model denormalized olduğu üçün sorğular sadə və sürətli olur.
4. **Better Security** - Read və write üçün fərqli permission-lar tətbiq oluna bilər.
5. **Event Sourcing Compatibility** - Event Sourcing ilə mükəmməl uyğunluq.
6. **Flexibility** - Read üçün fərqli texnologiyalar istifadə edilə bilər (SQL, Elasticsearch, Redis).
7. **Team Independence** - Read və write komandaları müstəqil işləyə bilər.

### Mənfi Cəhətləri

1. **Complexity** - Sistem mürəkkəbliyi əhəmiyyətli dərəcədə artır.
2. **Eventual Consistency** - Read model dərhal yenilənmir, müvəqqəti data uyğunsuzluğu ola bilər.
3. **Data Duplication** - Eyni data bir neçə yerdə saxlanır.
4. **Infrastructure Cost** - Ayrı database-lər, message broker-lər əlavə xərc yaradır.
5. **Learning Curve** - Komandanın öyrənməsi vaxt aparır.
6. **Over-engineering Risk** - Sadə tətbiqlər üçün lazımsız mürəkkəblik.
7. **Debugging Difficulty** - Event axınını izləmək çətin ola bilər.
8. **Projection Maintenance** - Read model-lərin yenilənmə prosesi əlavə iş tələb edir.

---

## Nə vaxt CQRS istifadə etməli

### CQRS istifadə edin

```
✅ Read/Write nisbəti çox fərqlidir (məsələn, 90% read, 10% write)
✅ Read və write üçün fərqli optimizasiya lazımdır
✅ Mürəkkəb domain logic var (DDD ilə birlikdə)
✅ Yüksək performans tələbləri var
✅ Microservice arxitekturası istifadə olunur
✅ Event Sourcing tətbiq etmək istəyirsiniz
✅ Fərqli read modellər lazımdır (list, detail, search, report)
✅ Sistem yükü artdıqca scale etmək lazımdır
```

### CQRS istifadə etməyin

```
❌ Sadə CRUD əməliyyatları
❌ Kiçik, monolit tətbiqlər
❌ Read/Write nisbəti bərabərdir
❌ Domain logic sadədir
❌ Komandanın CQRS təcrübəsi yoxdur
❌ MVP/prototip hazırlayarkən
❌ Eventual consistency qəbul edilməz
❌ İnfrastruktur büdcəsi məhduddur
```

### Aralıq yanaşma: Simplified CQRS

Tam CQRS çox mürəkkəb olduqda, sadələşdirilmiş versiya istifadə etmək olar: eyni database, amma fərqli modellər.

*Tam CQRS çox mürəkkəb olduqda, sadələşdirilmiş versiya istifadə etmək  üçün kod nümunəsi:*
```php
// Sadələşdirilmiş CQRS - eyni DB, fərqli modellər

// Write: Eloquent Model ilə business logic
class Order extends Model
{
    public function confirm(): void { /* business logic */ }
    public function cancel(string $reason): void { /* business logic */ }
}

// Read: Query Builder ilə optimallaşdırılmış sorğular
class OrderQueryService
{
    public function getOrderList(int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $userId)
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.total',
                'users.name as user_name',
                DB::raw('COUNT(order_items.id) as item_count'),
                'orders.created_at',
            ])
            ->groupBy('orders.id')
            ->orderByDesc('orders.created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getOrderDetails(int $orderId): ?object
    {
        return DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('addresses', 'orders.shipping_address_id', '=', 'addresses.id')
            ->where('orders.id', $orderId)
            ->select([
                'orders.*',
                'users.name as user_name',
                'users.email as user_email',
                'addresses.line1 as shipping_line1',
                'addresses.city as shipping_city',
            ])
            ->first();
    }
}

// Controller-də istifadə
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Query Service - read optimized
        $orders = app(OrderQueryService::class)->getOrderList(
            userId: auth()->id(),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        );

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        // Command Handler - write with business logic
        $order = app(PlaceOrderHandler::class)->handle(
            new PlaceOrderCommand(/* ... */)
        );

        return response()->json($order, 201);
    }
}
```

---

## CQRS + DDD

CQRS Domain-Driven Design ilə birlikdə istifadə edildikdə ən effektiv olur.

*CQRS Domain-Driven Design ilə birlikdə istifadə edildikdə ən effektiv  üçün kod nümunəsi:*
```php
// Bounded Context: Order Management
// Write tərəfi - Domain Model (DDD)

namespace App\Domain\OrderManagement;

// Value Objects
class Money
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Məbləğ mənfi ola bilməz');
        }
    }

    public function add(Money $other): Money
    {
        $this->ensureSameCurrency($other);
        return new Money($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): Money
    {
        $this->ensureSameCurrency($other);
        return new Money($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): Money
    {
        return new Money(round($this->amount * $factor, 2), $this->currency);
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException();
        }
    }

    public function getAmount(): float { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
}

class OrderId
{
    public function __construct(
        private readonly string $value,
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Order ID boş ola bilməz');
        }
    }

    public function value(): string { return $this->value; }
    public function equals(OrderId $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }
}

class CustomerId
{
    public function __construct(private readonly string $value) {}
    public function value(): string { return $this->value; }
}

// Aggregate Root
class Order
{
    private OrderId $id;
    private CustomerId $customerId;
    private OrderStatus $status;
    private array $items = [];
    private Money $subtotal;
    private Money $total;
    private array $domainEvents = [];

    private function __construct() {}

    public static function place(OrderId $id, CustomerId $customerId, array $items): self
    {
        if (empty($items)) {
            throw new EmptyOrderException('Sifariş ən az bir item-dən ibarət olmalıdır');
        }

        $order = new self();
        $order->id = $id;
        $order->customerId = $customerId;
        $order->status = OrderStatus::PENDING;
        $order->items = $items;
        $order->calculateTotals();

        $order->recordEvent(new OrderPlacedDomainEvent(
            orderId: $id,
            customerId: $customerId,
            total: $order->total,
            occurredOn: new \DateTimeImmutable(),
        ));

        return $order;
    }

    public function confirm(): void
    {
        if (!$this->status->equals(OrderStatus::PENDING)) {
            throw new InvalidOrderStateTransitionException(
                "Pending olmayan sifariş təsdiqlənə bilməz. Cari status: {$this->status}"
            );
        }

        $this->status = OrderStatus::CONFIRMED;

        $this->recordEvent(new OrderConfirmedDomainEvent(
            orderId: $this->id,
            occurredOn: new \DateTimeImmutable(),
        ));
    }

    public function ship(string $trackingNumber): void
    {
        if (!$this->status->equals(OrderStatus::CONFIRMED)) {
            throw new InvalidOrderStateTransitionException(
                'Yalnız təsdiqlənmiş sifarişlər göndərilə bilər.'
            );
        }

        $this->status = OrderStatus::SHIPPED;

        $this->recordEvent(new OrderShippedDomainEvent(
            orderId: $this->id,
            trackingNumber: $trackingNumber,
            occurredOn: new \DateTimeImmutable(),
        ));
    }

    public function cancel(string $reason): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidOrderStateTransitionException(
                "Terminal statusdakı sifariş ləğv edilə bilməz: {$this->status}"
            );
        }

        $this->status = OrderStatus::CANCELLED;

        $this->recordEvent(new OrderCancelledDomainEvent(
            orderId: $this->id,
            reason: $reason,
            occurredOn: new \DateTimeImmutable(),
        ));
    }

    private function calculateTotals(): void
    {
        $currency = $this->items[0]->getPrice()->getCurrency();
        $this->subtotal = new Money(0, $currency);

        foreach ($this->items as $item) {
            $this->subtotal = $this->subtotal->add(
                $item->getPrice()->multiply($item->getQuantity())
            );
        }

        // 18% ƏDV
        $tax = $this->subtotal->multiply(0.18);
        $this->total = $this->subtotal->add($tax);
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Getters
    public function getId(): OrderId { return $this->id; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getTotal(): Money { return $this->total; }
}

// Command Handler (Application Layer)
class PlaceOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogService $productCatalog,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(PlaceOrderCommand $command): void
    {
        $orderId = new OrderId($command->orderId);
        $customerId = new CustomerId($command->customerId);

        // Domain Service vasitəsilə item-ləri yarat
        $items = [];
        foreach ($command->items as $item) {
            $product = $this->productCatalog->getProduct($item['product_id']);
            $items[] = new OrderItem(
                productId: $product->getId(),
                productName: $product->getName(),
                price: $product->getPrice(),
                quantity: $item['quantity'],
            );
        }

        // Aggregate Root-dan istifadə
        $order = Order::place($orderId, $customerId, $items);

        // Repository vasitəsilə saxla
        $this->orderRepository->save($order);

        // Domain Event-ləri yayımla
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
```

---

## İntervyu Sualları və Cavabları

### Sual 1: CQRS nədir və niyə lazımdır?

**Cavab:** CQRS (Command Query Responsibility Segregation) oxuma və yazma əməliyyatlarını ayrı modellərdə idarə etmək prinsipidir. Write model business logic və validation-la məşğuldur, normalized data istifadə edir. Read model isə sorğular üçün optimallaşdırılmış, denormalized data istifadə edir. Bu ayrılma performans optimizasiyası, müstəqil scaling, daha sadə modellər və daha yaxşı security təmin edir. Xüsusilə read/write nisbəti çox fərqli olan sistemlərdə (90% read, 10% write) çox effektivdir.

### Sual 2: CQS ilə CQRS arasında nə fərq var?

**Cavab:** CQS (Command Query Separation) metod səviyyəsində prinsipdir: hər metod ya state dəyişdirməli (command), ya da data qaytarmalıdır (query), ikisini eyni anda etməməlidir. CQRS isə arxitektura səviyyəsində bu prinsipin tətbiqidir: read və write əməliyyatları üçün tamamilə fərqli modellər, potensial olaraq fərqli verilənlər bazaları istifadə olunur. CQS bir class daxilində tətbiq olunur, CQRS isə bütün sistem arxitekturasına təsir edir.

### Sual 3: Command Bus nədir və necə işləyir?

**Cavab:** Command Bus command obyektlərini uyğun handler-lərə yönləndirən vasitəçidir. Controller command yaradır, Command Bus onu uyğun handler-ə göndərir. Bus middleware-lər vasitəsilə cross-cutting concerns tətbiq edir: logging, validation, authorization, database transaction. Bu, controller-ləri sadə saxlayır, business logic-i handler-lərdə cəmləyir və middleware-lər vasitəsilə ortaq funksionallığı təkrar istifadə etməyə imkan verir.

### Sual 4: Read Model ilə Write Model arasında nə fərq var?

**Cavab:** Write Model normalized verilənlər bazası strukturu istifadə edir, business logic ehtiva edir, domain qaydalarını tətbiq edir və relationships vasitəsilə əlaqəli data-ya müraciət edir. Read Model isə denormalized strukturdur, business logic yoxdur, bütün lazım olan data bir cədvəldə/sənəddə saxlanır və JOIN-sız sürətli sorğular təmin edir. Write Model data integrity-ni, Read Model isə sorğu performansını təmin edir.

### Sual 5: CQRS-də Eventual Consistency problemi necə həll olunur?

**Cavab:** Write model dəyişdikdə read model asinxron olaraq event-lər vasitəsilə yenilənir. Bu müddətdə read model köhnə data göstərə bilər. Həll yolları: 1) Yazma əməliyyatından sonra write model-dən birbaşa cavab qaytarmaq, 2) Polling/WebSocket ilə yeniləmə bildirişi göndərmək, 3) Read-your-writes consistency pattern istifadə etmək (istifadəçi öz dəyişikliklərini write model-dən oxuyur), 4) UI-da "yenilənir..." statusu göstərmək.

### Sual 6: CQRS ilə Event Sourcing niyə birlikdə istifadə olunur?

**Cavab:** Event Sourcing state-i event-lər ardıcıllığı kimi saxlayır. CQRS ilə birlikdə write tərəfi event-ləri Event Store-a yazır, read tərəfi isə bu event-lərdən projeksiyalar yaradır. Bu kombinasiya tam audit trail, event replay imkanı, müxtəlif read modellər yaratma imkanı və temporal queries təmin edir. Lakin bu iki pattern bir-biri olmadan da istifadə edilə bilər.

### Sual 7: Projections nədir və necə işləyir?

**Cavab:** Projections (proyeksiyalar) event-lərdən yaradılan read model-lərdir. Hər event gəldikdə projector həmin event-i emal edib read model-i yeniləyir. Fərqli ehtiyaclar üçün fərqli projections yaradıla bilər: list view, detail view, dashboard statistics, search index. Projections yenidən qurula bilər (replay) ki, bu da yeni read model əlavə etməyi və ya mövcudları düzəltməyi asanlaşdırır.

### Sual 8: CQRS-i nə vaxt istifadə etməməlisiniz?

**Cavab:** Sadə CRUD tətbiqlərdə, kiçik monolit sistemlərdə, domain logic olmayan və ya çox sadə olan hallarda, read/write nisbəti bərabər olduqda, komandanın CQRS təcrübəsi olmadıqda, MVP/prototip hazırlayarkən CQRS istifadə etməmək daha yaxşıdır. CQRS əhəmiyyətli mürəkkəblik əlavə edir, bu mürəkkəbliyin faydalı olub-olmadığını qiymətləndirmək lazımdır. Sadələşdirilmiş CQRS (eyni DB, fərqli modellər) başlanğıc üçün yaxşı kompromisdir.

### Sual 9: Laravel-də CQRS necə implementasiya olunur?

**Cavab:** Laravel-də CQRS üçün: 1) Command class-ları yaradılır (data daşıyıcı), 2) CommandHandler class-ları yaradılır (business logic), 3) CommandBus implementasiya edilir (middleware chain ilə), 4) Query və QueryHandler class-ları yaradılır, 5) QueryBus implementasiya edilir, 6) ServiceProvider-da bus-lar və handler-lər qeydiyyat edilir, 7) Controller-lərdə CommandBus::dispatch() və QueryBus::ask() istifadə edilir. Projections üçün Laravel Event/Listener sistemi istifadə olunur.

### Sual 10: CQRS ilə DDD-nin əlaqəsi nədir?

**Cavab:** CQRS DDD ilə çox yaxşı uyğun gəlir. Write tərəfi DDD-nin Aggregate Root, Entity, Value Object, Domain Event konseptlərini istifadə edir. Command Handler application layer-da yerləşir və domain model-i idarə edir. Read tərəfi isə domain model-dən asılı deyil, birbaşa database sorğuları ilə data qaytarır. Bu ayrılma domain model-in saf qalmasını təmin edir - business logic yalnız write tərəfindədir. Bounded Context-lər arasında Integration Event-lər vasitəsilə əlaqə qurulur.

### Sual 11: CQRS-də data consistency-ni necə təmin edirsiniz?

**Cavab:** Write tərəfində strong consistency istifadə olunur - database transaction-lar, optimistic/pessimistic locking. Read tərəfində eventual consistency qəbul edilir - projections asinxron yenilənir. Vacib ssenarilərdə read-your-writes pattern istifadə edilir: istifadəçi öz yaratdığı data-nı write model-dən oxuyur. Proyeksiya gecikmələri monitoring edilir, dead letter queue ilə uğursuz yeniləmələr izlənir.

### Sual 12: Simplified CQRS nədir?

**Cavab:** Simplified CQRS tam CQRS-in sadələşdirilmiş versiyasıdır: eyni verilənlər bazası istifadə olunur, amma write üçün Eloquent Model (business logic ilə), read üçün isə Query Builder (optimallaşdırılmış sorğular) istifadə olunur. Bu yanaşma ayrı database-lərin, message broker-lərin, projection-ların mürəkkəbliyindən qaçır, amma yenə də read/write separation-ın faydalarını təmin edir. Böyümə ehtiyacı yarandıqda tam CQRS-ə keçid daha asan olur.

---

## Anti-patternlər

**1. Command Handler-da Sorğu (Query) Etmək**
Command handler-ın içindən read-model-ə sorğu göndərmək və nəticəni return etmək — Command/Query ayrılığı pozulur, handler-ın məsuliyyəti genişlənir. Command-lar yalnız state dəyişikliyi etməli, nəticə üçün ayrıca Query göndərilməlidir.

**2. Read Model-i Write Model-dən Birbaşa İrs Almaq**
Read DTO-larının Eloquent model-lərindən extend etməsi — read tərəfi write infrastrukturuna bağlanır, read optimallaşdırması məhdudlaşır. Read model-lər sadə POPO (Plain Old PHP Object) və ya ayrı View-specific class-lar olmalıdır.

**3. Hər Sadə CRUD Tətbiqinə CQRS Tətbiq Etmək**
Kiçik, az trafik alan tətbiqlərə tam CQRS infrastrukturu qurmaq — projection, event bus, ayrı database kimi əlavə mürəkkəblik gətirir, fayda isə minimaldır. CQRS yalnız read/write yükü kəskin ayrılan, mürəkkəb domain-li sistemlər üçün seçilməlidir.

**4. Eventual Consistency-ni Nəzərə Almamaq**
Projection-ların sinxron yenilənəcəyini fərz edib istifadəçinin yazdığı datanı dərhal read model-dən oxumağa çalışmaq — gecikmiş yeniləmə zamanı köhnə data göstərilir, istifadəçi çaşır. Read-your-writes pattern tətbiq edin; lazım olan hallarda write model-dən oxuyun.

**5. Command Validation-u Atlamaq**
Command handler-ın içinə gəlib validation etməmək — invalid business state yaranır, domain invariant-lar pozulur. Command-lar handler-a çatmadan validate olunmalıdır: Form Request (HTTP), ya da Command constructor-da guard clause-lar.

**6. Projection Uğursuzluqlarını İzləməmək**
Projection yeniləmə job-larının uğursuz olduğu halları loglamadan, alertsiz buraxmaq — read model geri qalır, istifadəçilər köhnə data görür amma heç kim xəbər tutmur. Dead Letter Queue, Horizon monitoring, projection lag alerting mütləq qurulmalıdır.
