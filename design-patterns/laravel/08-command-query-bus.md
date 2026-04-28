# Command Bus / Query Bus (Senior ⭐⭐⭐)

## İcmal

Command Bus / Query Bus pattern, application-level istəkləri (command, query) handler-lərə yönləndirir. Mediator pattern-in tətbiqidir: controller "nə etmək lazımdır" bilir (command), amma "kim edəcək" bilmir. Bus bu routing-i öhdəsinə götürür. CQRS (Command Query Responsibility Segregation) prinsipi üzərindədir: yazma əməliyyatları (Command) oxuma əməliyyatlarından (Query) ayrılır.

## Niyə Vacibdir

Böyüyən Laravel layihələrində controller-lar bir neçə service-ə bağlanır; eyni use case-i HTTP, Artisan, Queue-dan çağırmaq çətinləşir; cross-cutting concerns (logging, transaction, validation) hər yerdə təkrarlana bilər. Command Bus bu mərkəzləşdirməni təmin edir: hər use case bir Handler-dədir, Bus middleware-i bütün command-lara tətbiq edir.

## Əsas Anlayışlar

- **Command**: "bir şey et" — mutation, side effect; `CreateOrder`, `PlaceOrder`, `CancelSubscription`; void və ya result qaytara bilər; immutable data object
- **Query**: "nəyi gətir" — oxuma, mutation yoxdur; `GetOrderById`, `ListActiveUsers`; həmişə nəticə qaytarır
- **Handler**: bir Command/Query-ə bir Handler; single responsibility; business logic buradadır
- **Command Bus**: command-i düzgün handler-ə çatdıran dispatcher; middleware pipeline-a malikdir
- **Query Bus**: query-i handler-ə yönləndirir; cache middleware əlavə etmək asandır
- **Bus Middleware**: cross-cutting concerns — transaction, logging, validation, authorization
- **CQRS**: Command və Query modelləri ayrılır; read model ayrı read replica-dan oxuya bilər

## Praktik Baxış

- **Real istifadə**: order placement, user registration, subscription management — hər use case bir Command+Handler; admin dashboard, reporting — Query+Handler; Artisan command-lardan eyni Handler-i çağırmaq
- **Trade-off-lar**: hər use case üçün iki class (Command + Handler) — boilerplate artır; debugging mürəkkəbləşir (stack trace uzanır); kiçik layihələrdə overhead-dir
- **İstifadə etməmək**: sadə CRUD layihədə; 3-5 endpoint-li kiçik API-larda; bus yalnız forward/routing edirsə dəyər yaratmır

- **Common mistakes**:
  1. Handler-dən başqa handler dispatch etmək — gizli dependency yaranır
  2. Query handler-də state dəyişdirmək — CQRS pozulur
  3. Command-a 15+ parametr qoymaq — use case-i bölmək lazımdır
  4. `static` method ilə handler — DI işləmir, test olmur

### Anti-Pattern Nə Zaman Olur?

**Bus 3 command üçün** — overkill:
```php
// BAD — bütün app boyunca yalnız 3 command var, heç vaxt böyüməyəcək
// Bu üçün bus qururuq: CreateUser, UpdateUser, DeleteUser
// Bunlar sadə CRUD — service layer kifayətdir

// GOOD — Bus dəyər verəndə:
// 20+ command, async dispatch lazımdır,
// həm HTTP, həm Artisan, həm Queue-dan eyni handler çağırılır
```

**Handler hər şeyi edir:**
```php
// BAD — handler bir metod içində hər şeyi edir
class CreateOrderHandler
{
    public function __invoke(CreateOrderCommand $cmd): void
    {
        // 1. DB-dən yükləmək
        $user = User::find($cmd->userId);
        $products = Product::whereIn('id', $cmd->productIds)->get();

        // 2. Business logic
        $total = $products->sum('price');
        $order = Order::create(['user_id' => $user->id, 'total' => $total]);

        // 3. HTTP call — payment gateway
        $response = Http::post('https://stripe.com/charge', [...]);

        // 4. Email
        Mail::to($user)->send(new OrderConfirmed($order));

        // 5. Audit log
        DB::table('audit_logs')->insert([...]);
    }
}

// GOOD — handler orkestrasiya edir; işi domain + service-lərə verir
class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepository     $orders,
        private readonly PaymentGateway      $payments,
        private readonly EventDispatcher     $events,
    ) {}

    public function __invoke(CreateOrderCommand $cmd): Order
    {
        $order = $this->orders->createFromCommand($cmd);
        $this->payments->charge($order);
        $this->events->dispatch(new OrderCreated($order));
        return $order;
    }
}
```

## Nümunələr

### Ümumi Nümunə

Poçt idarəsi düşünün. Gişə müştəridən məktub alır (controller), "kime göndərilsin" ünvanına baxır (command), məktubu müvafiq şöbəyə verir (bus routing), şöbə çatdırmağı öhdəsinə götürür (handler). Gişə poçt göndərməyi bilmir — bus bilir.

### PHP/Laravel Nümunəsi

**Command + Handler:**

```php
<?php

namespace App\Application\Order;

// Command — immutable data object; "nə etmək lazımdır"
readonly class CreateOrderCommand
{
    public function __construct(
        public int    $userId,
        public array  $items,      // [['product_id' => 1, 'quantity' => 2], ...]
        public string $currency = 'USD',
    ) {}
}

// Handler — bir use case, bir class
class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepository  $orders,
        private readonly ProductRepository $products,
        private readonly EventDispatcher  $events,
    ) {}

    public function __invoke(CreateOrderCommand $cmd): Order
    {
        // Domain-dan validate
        $productIds = array_column($cmd->items, 'product_id');
        $products = $this->products->findMany($productIds);

        if ($products->count() !== count($productIds)) {
            throw new \DomainException('One or more products not found');
        }

        $order = $this->orders->create(
            userId:   $cmd->userId,
            items:    $cmd->items,
            currency: $cmd->currency,
        );

        // Event fire — listener-lər emaili, inventory-ni handle edir
        $this->events->dispatch(new OrderCreated($order));

        return $order;
    }
}
```

**Query + Handler:**

```php
<?php

namespace App\Application\Order;

// Query — "nəyi gətir"; noun formada
readonly class GetOrderByIdQuery
{
    public function __construct(public int $orderId) {}
}

// Query Handler — yalnız oxuyur, heç nə dəyişmir
class GetOrderByIdHandler
{
    public function __construct(
        private readonly OrderReadModel $readModel,
    ) {}

    public function __invoke(GetOrderByIdQuery $query): ?OrderView
    {
        // Read model — write model-dən ayrıdır; read replica istifadə edə bilər
        return $this->readModel->findById($query->orderId);
    }
}
```

**Sadə Command Bus implementasiyası:**

```php
<?php

namespace App\Infrastructure\Bus;

interface CommandBus
{
    public function dispatch(object $command): mixed;
}

class SimpleCommandBus implements CommandBus
{
    // Command class → Handler class mapping
    private array $map = [];

    public function __construct(private readonly \Illuminate\Contracts\Container\Container $container) {}

    public function register(string $commandClass, string $handlerClass): self
    {
        $this->map[$commandClass] = $handlerClass;
        return $this;
    }

    public function dispatch(object $command): mixed
    {
        $handlerClass = $this->map[$command::class]
            ?? throw new \RuntimeException('No handler registered for ' . $command::class);

        // Container dependency-ləri inject edir
        $handler = $this->container->make($handlerClass);
        return ($handler)($command);
    }
}
```

**Bus Middleware — cross-cutting concerns:**

```php
<?php

// Transaction middleware — hər command transaction içindədir
class TransactionMiddleware
{
    public function __construct(private readonly \Illuminate\Database\DatabaseManager $db) {}

    public function handle(object $command, callable $next): mixed
    {
        return $this->db->transaction(fn() => $next($command));
    }
}

// Logging middleware
class LoggingMiddleware
{
    public function __construct(private readonly \Psr\Log\LoggerInterface $logger) {}

    public function handle(object $command, callable $next): mixed
    {
        $start = microtime(true);
        $this->logger->info('Command dispatching', ['command' => $command::class]);

        try {
            $result = $next($command);
            $this->logger->info('Command completed', [
                'command'  => $command::class,
                'duration' => round((microtime(true) - $start) * 1000, 2) . 'ms',
            ]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Command failed', [
                'command' => $command::class,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

// Middleware pipeline ilə bus
class MiddlewarePipelineCommandBus implements CommandBus
{
    public function __construct(
        private readonly CommandBus $inner,       // terminal bus
        private readonly array      $middleware,  // Middleware[]
    ) {}

    public function dispatch(object $command): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $mw) => fn($cmd) => $mw->handle($cmd, $next),
            fn($cmd) => $this->inner->dispatch($cmd)
        );

        return $pipeline($command);
    }
}
```

**ServiceProvider-da wiring:**

```php
<?php

class CommandBusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommandBus::class, function ($app) {
            $terminal = (new SimpleCommandBus($app))
                ->register(CreateOrderCommand::class, CreateOrderHandler::class)
                ->register(CancelOrderCommand::class, CancelOrderHandler::class)
                ->register(UpdateUserProfileCommand::class, UpdateUserProfileHandler::class);

            // Middleware sarır: logging → transaction → handler
            return new MiddlewarePipelineCommandBus($terminal, [
                $app->make(LoggingMiddleware::class),
                $app->make(TransactionMiddleware::class),
            ]);
        });

        $this->app->singleton(QueryBus::class, function ($app) {
            $terminal = (new SimpleCommandBus($app))
                ->register(GetOrderByIdQuery::class, GetOrderByIdHandler::class)
                ->register(ListOrdersQuery::class, ListOrdersHandler::class);

            // Query bus yalnız logging — transaction lazım deyil
            return new MiddlewarePipelineCommandBus($terminal, [
                $app->make(LoggingMiddleware::class),
            ]);
        });
    }
}
```

**Controller — thin, bus-a bağlıdır:**

```php
<?php

class OrderController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus   $queries,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        // HTTP → Command; controller business logic bilmir
        $order = $this->commands->dispatch(new CreateOrderCommand(
            userId:   $request->user()->id,
            items:    $request->validated('items'),
            currency: $request->validated('currency', 'USD'),
        ));

        return response()->json(new OrderResource($order), 201);
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->queries->ask(new GetOrderByIdQuery($id));

        if (!$order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(new OrderResource($order));
    }
}
```

**Laravel-in built-in Bus (Job dispatching):**

```php
<?php

// Laravel-in Dispatchable trait-i ilə — Job eyni zamanda Command-dır
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly int    $orderId,
        public readonly string $paymentMethod,
    ) {}

    // Handler əslində handle() metodudur
    public function handle(PaymentGateway $gateway, OrderRepository $orders): void
    {
        $order = $orders->findById($this->orderId);
        $gateway->charge($order, $this->paymentMethod);
    }
}

// Dispatch — queue-ya atır (async)
ProcessPaymentJob::dispatch($order->id, 'stripe');

// Sync dispatch — test üçün
ProcessPaymentJob::dispatchSync($order->id, 'stripe');
```

**Handler test etmək — dependency injection sayəsində sadə:**

```php
<?php

class CreateOrderHandlerTest extends TestCase
{
    public function test_creates_order_and_dispatches_event(): void
    {
        $orders   = $this->createMock(OrderRepository::class);
        $products = $this->createMock(ProductRepository::class);
        $events   = $this->createMock(EventDispatcher::class);

        $products->method('findMany')->willReturn(collect([
            new Product(['id' => 1, 'price' => 50.00]),
        ]));

        $expectedOrder = new Order(['id' => 100, 'total' => 50.00]);
        $orders->expects($this->once())->method('create')->willReturn($expectedOrder);

        $events->expects($this->once())
               ->method('dispatch')
               ->with($this->isInstanceOf(OrderCreated::class));

        $handler = new CreateOrderHandler($orders, $products, $events);
        $result  = $handler(new CreateOrderCommand(
            userId:   1,
            items:    [['product_id' => 1, 'quantity' => 1]],
        ));

        $this->assertEquals(100, $result->id);
    }
}
```

## Praktik Tapşırıqlar

1. `PlaceOrderCommand` + `PlaceOrderHandler` yazın; `OrderController::store()`-dan dispatch edin; Handler-i unit test edin — real DB olmadan
2. `TransactionMiddleware` + `LoggingMiddleware` pipeline qurun; hər command avtomatik transaction içindəsin
3. `GetActiveOrdersQuery` + `GetActiveOrdersHandler` yazın; query handler-ə cache middleware əlavə edin — eyni query 5 dəqiqə cache-ləsin
4. Laravel Job-u Command kimi istifadə edin: `GenerateInvoiceJob` — sync endpoint-dən, həm də queue-dan çağırıla bilsin

## Əlaqəli Mövzular

- [07-policy-handler-pattern.md](07-policy-handler-pattern.md) — Policy command bus-a göndərir
- [05-event-listener.md](05-event-listener.md) — Event vs Command fərqi; hər biri fərqli məqsəd
- [02-service-layer.md](02-service-layer.md) — Service Layer vs Command Bus: ikisi alternativdir
- [../behavioral/08-mediator.md](../behavioral/08-mediator.md) — Bus əslində Mediator pattern-in tətbiqidir
- [../behavioral/03-command.md](../behavioral/03-command.md) — Command pattern əsasları
- [../integration/01-cqrs.md](../integration/01-cqrs.md) — CQRS arxitekturası; ayrı read/write model
- [../ddd/08-domain-service-vs-app-service.md](../ddd/08-domain-service-vs-app-service.md) — Handler application service rolundadır
