# Command Pattern (Senior ⭐⭐⭐)

## İcmal

Command pattern — bir sorğunu (request, action) object kimi encapsulate edən behavioral pattern-dir. "Encapsulate a request as an object, thereby letting you parameterize clients with different requests, queue or log requests, and support undoable operations." Bu pattern undo/redo, queue, macro, audit log kimi xüsusiyyətlər üçün əsas yaradır. Laravel-in Artisan command sistemi, job queue-lar, Command Bus pattern — bunların hamısı Command pattern üzərindən qurulub.

## Niyə Vacibdir

Command pattern — decoupling-in güclü nümunəsidir. Invoker hansı command-ın nə etdiyini bilmir, yalnız `execute()` çağırır. Bu test-friendly-dir (mock command inject et), extensible-dir (yeni command = yeni class), composable-dir (macro commands). Interviewer bu sualda yoxlayır: CQRS-i bilirsinizmi? Command Bus nədir? Undo/redo arxitekturasını necə qurarsınız?

## Əsas Anlayışlar

**Command pattern komponentləri:**
- **Command interface**: `execute()` metodu (bəzən `undo()` da)
- **Concrete Command**: Spesifik action-ı encapsulate edir. Receiver-ə referans saxlayır
- **Invoker**: Command-ı çağırır. Command-ın nə etdiyini bilmir — yalnız `execute()` bilir
- **Receiver**: Əsl iş burada görülür. Command Receiver-in metodunu çağırır

**Command vs Function:**
- Function stateless-dir. Command object — state saxlaya bilər (undo üçün əvvəlki state, context)
- Command queue-ya göndərilə bilər, serialize edilə bilər, log edilə bilər

**Undo/Redo:**
- Hər command `execute()` + `undo()` metodunu implement edir
- Invoker command history stack saxlayır
- Undo: Stack-dən son command-ı pop et, `undo()` çağır
- Redo: Redo stack-dən command-ı pop et, `execute()` çağır

**Macro commands:**
- Composite pattern ilə: `MacroCommand` bir neçə command-ı saxlayır, execute etdikdə hamısını ardıcıl çağırır
- Transaction rollback: Macro-nun tərsi — hər step-i undo etmək

**Command Bus:**
- Invoker kimi işləyən middleware pipeline. Command handler-ları tapır, middleware-lər tətbiq edir (logging, validation, transaction), handler-ı çağırır
- Laravel Tactician, laravel-command-bus

**CQRS (Command Query Responsibility Segregation):**
- Command: Sistemi dəyişdirən əməliyyatlar (write) — return value yoxdur, side effect var
- Query: Oxuyan əməliyyatlar (read) — side effect yoxdur, data qaytarır
- Əsas ideyası: Command ilə Query-ni eyni class-da qarışdırmamaq
- Laravel: Command-lar `app/Commands/`, Query-lər `app/Queries/` ayrı folder-larda

**Artisan Command:**
- Laravel-in Artisan sistemi Command pattern tətbiqidir. Hər Artisan command bir Command object-dir
- `handle()` metodu — execute-dur

**Laravel Jobs vs Command:**
- Job (Queue Job): Asenkron, background işlər. Serializable. Command pattern-in queue tətbiqi
- Command Bus command-ı: Sync ya da async dispatch. CQRS kontekstinda

## Praktik Baxış

**Interview-da yanaşma:**
Command pattern-i izah edərkən undo/redo-ya fokuslanmaq effektiv olur — bu pattern-in əsas differentiation-ıdır. Sonra CQRS ilə əlaqəsini qeyd edin.

**Follow-up suallar:**
- "Command Bus nədir, niyə lazımdır?" → Middleware pipeline: logging, transaction, authorization hər command üçün avtomatik tətbiq olunur
- "CQRS-in üstünlükləri?" → Command və Query-i ayrı optimize etmək. Read replica-ı Query üçün, write-heavy server Command üçün
- "Command pattern Artisan-da necə görünür?" → `php artisan make:command` — hər command `Command` class-ı extend edir, `handle()` metodu execute-dur
- "Transactional command nədir?" → Command execute zamanı exception olsa rollback — Command Bus middleware ilə

**Ümumi səhvlər:**
- Command-ı Command Bus-suz istifadə etmək — middleware-lər əldən veririlir
- CQRS-i həmişə event sourcing ilə birlikdə düşünmək — bunlar ayrı pattern-lərdir
- Command-ı data transfer etmək üçün DTO kimi istifadə etmək (əslində action-dır)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
CQRS read/write separation-ını database scaling kontekstinde (read replica), ya da Command Bus middleware pipeline-ın cross-cutting concerns-i necə həll etdiyini izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Design an order processing system where orders can be placed, cancelled, and refunded. You need full audit logging of all operations. How would you use the Command Pattern?"

### Güclü Cavab

Bu use-case Command pattern + Command Bus-un ideal tətbiqi.

Hər operation bir Command: `PlaceOrderCommand`, `CancelOrderCommand`, `RefundOrderCommand`. Hər biri data saxlayır (order_id, reason, user_id) lakin business logic-i handler-da olur.

Command Bus middleware: `LoggingMiddleware` hər command-ı audit log-a yazır — handler-lara toxunmadan. `TransactionMiddleware` hər command-ı DB transaction-da execute edir.

Undo: `CancelOrderCommand`-ın `undo()` metodu `PlaceOrderCommand` state-ini restore edə bilər (soft delete + restore).

CQRS: `GetOrdersQuery` read replica-dan işləyir. `PlaceOrderCommand` primary DB-yə yazır.

### Kod Nümunəsi

```php
// Command interface
interface Command
{
    public function execute(): void;
}

interface ReversibleCommand extends Command
{
    public function undo(): void;
}

// Concrete Command — CQRS write side
class PlaceOrderCommand
{
    public function __construct(
        public readonly int    $userId,
        public readonly array  $items,
        public readonly string $shippingAddress,
        public readonly string $idempotencyKey,
    ) {}
}

// Command Handler (Receiver)
class PlaceOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly InventoryService $inventory,
        private readonly EventDispatcher $events,
    ) {}

    public function handle(PlaceOrderCommand $command): Order
    {
        // Business logic burada
        $this->inventory->reserve($command->items);

        $order = $this->orders->create([
            'user_id'          => $command->userId,
            'items'            => $command->items,
            'shipping_address' => $command->shippingAddress,
            'idempotency_key'  => $command->idempotencyKey,
            'status'           => 'placed',
        ]);

        $this->events->dispatch(new OrderPlaced($order));

        return $order;
    }
}

// Command Bus
class CommandBus
{
    private array $handlers = [];
    private array $middleware = [];

    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(object $command): mixed
    {
        $handler = $this->handlers[get_class($command)]
            ?? throw new \RuntimeException('No handler for ' . get_class($command));

        // Middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($carry, $middleware) => fn($cmd) => $middleware($cmd, $carry),
            $handler
        );

        return $pipeline($command);
    }
}

// Middleware — cross-cutting concerns
class DatabaseTransactionMiddleware
{
    public function __invoke(object $command, callable $next): mixed
    {
        return DB::transaction(fn() => $next($command));
    }
}

class AuditLogMiddleware
{
    public function __invoke(object $command, callable $next): mixed
    {
        $commandName = class_basename($command);
        $startTime   = microtime(true);

        try {
            $result = $next($command);

            AuditLog::create([
                'command'    => $commandName,
                'payload'    => json_encode($command),
                'user_id'    => auth()->id(),
                'success'    => true,
                'duration_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            return $result;
        } catch (\Throwable $e) {
            AuditLog::create([
                'command' => $commandName,
                'payload' => json_encode($command),
                'user_id' => auth()->id(),
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

// Undo/Redo — text editor nümunəsi
class CommandHistory
{
    private array $history = [];
    private array $redoStack = [];

    public function execute(ReversibleCommand $command): void
    {
        $command->execute();
        $this->history[] = $command;
        $this->redoStack = [];  // Yeni command redo stack-i silir
    }

    public function undo(): void
    {
        if (empty($this->history)) return;

        $command = array_pop($this->history);
        $command->undo();
        $this->redoStack[] = $command;
    }

    public function redo(): void
    {
        if (empty($this->redoStack)) return;

        $command = array_pop($this->redoStack);
        $command->execute();
        $this->history[] = $command;
    }
}
```

### Anti-Pattern Nümunəsi

**Problem:** Business logic birbaşa controller-da, hər action üçün transaction + logging əl ilə.

```php
// ❌ Anti-pattern — God Controller
class OrderController extends Controller
{
    public function place(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => auth()->id(),
                'items'   => $request->items,
                'status'  => 'placed',
            ]);

            foreach ($request->items as $item) {
                Inventory::where('product_id', $item['id'])
                    ->decrement('stock', $item['qty']);
            }

            Mail::to(auth()->user())->send(new OrderConfirmation($order));

            AuditLog::create([
                'action'   => 'place_order',
                'order_id' => $order->id,
                'user_id'  => auth()->id(),
            ]);

            DB::commit();
            return response()->json($order);
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
        // Problem 1: Test edilmir — DB, Mail, Inventory mock etmək çətindir
        // Problem 2: CancelOrderController, RefundOrderController-da eyni boilerplate
        // Problem 3: Yeni cross-cutting concern (rate limiting) → hər controller-da əl ilə
        // Problem 4: Audit log business logic-ə qarışıb, ayrıla bilmir
    }
}

// ✅ Command Pattern + Command Bus ilə
class PlaceOrderCommand
{
    public function __construct(
        public readonly int   $userId,
        public readonly array $items,
    ) {}
}

// Middleware-lər bir dəfə yazılır, bütün command-lara avtomatik tətbiq olunur
$bus->addMiddleware(new DatabaseTransactionMiddleware());
$bus->addMiddleware(new AuditLogMiddleware());

// Controller sadə qalır
class OrderController extends Controller
{
    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->bus->dispatch(new PlaceOrderCommand(
            userId: auth()->id(),
            items:  $request->validated('items'),
        ));
        return response()->json($order);
    }
}
```

**Niyə Command Bus daha yaxşıdır:** Transaction + AuditLog bütün command-lara bir dəfə yazılır. Handler yalnız business logic-i bilir. Test zamanı yalnız handler test edilir, middleware-lər ayrıca test edilir.

---

## Praktik Tapşırıqlar

- CQRS qurun: `PlaceOrderCommand` yazır, `GetUserOrdersQuery` oxuyur (ayrı handler-lar)
- Command Bus implement edin: transaction + logging middleware
- Undo/Redo sistemi: mətn redaktoru — `InsertTextCommand`, `DeleteTextCommand`, her ikisi undo() ilə
- Laravel artisan custom command yazın: `php artisan orders:process-pending`
- Command idempotency: Eyni command iki dəfə göndərilsə nə olur? İdempotency key ilə həll edin

## Əlaqəli Mövzular

- [SOLID Principles](01-solid-principles.md) — SRP + OCP, hər command bir class
- [Observer / Event](04-observer-event.md) — Command execute → Event dispatch
- [Strategy Pattern](05-strategy-pattern.md) — Command handler selection strategy
- [Chain of Responsibility](14-chain-of-responsibility.md) — Command middleware pipeline
