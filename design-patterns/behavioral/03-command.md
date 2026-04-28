# Command (Middle ⭐⭐)

## İcmal
Command pattern action və ya request-i ayrı bir object kimi encapsulate edir. Bu sayədə action-ları parametrize etmək, queue-ya əlavə etmək, log etmək və undo/redo dəstəkləmək mümkün olur. "Nə ediləcək"i "nə vaxt ediləcək"dən ayırır.

## Niyə Vacibdir
Laravel Jobs, Artisan Commands və event sourcing — hamısı bu pattern üzərindədir. Command-i anlamaq Laravel-in daxili arxitekturasını, job queue mexanizmini və undo/redo kimi feature-ları düzgün qurmağı öyrədir.

## Əsas Anlayışlar
- **Command interface**: `execute()` (və istəyə bağlı `undo()`) metodlarını müəyyən edir
- **ConcreteCommand**: konkret action-ı həyata keçirir, Receiver-ə istinad saxlayır
- **Receiver**: əsl iş görən object (OrderService, PaymentService və s.)
- **Invoker**: command-i saxlayan və icra edən (CommandBus, Scheduler, Queue Worker)
- **Client**: ConcreteCommand yaradır, Receiver-i bağlayır
- **Command history**: undo/redo üçün icra olunan command-lərin siyahısı

## Praktik Baxış
- **Real istifadə**: shopping cart actions (add/remove item + undo), batch data processing, wizard steps, transaction rollback, audit logging
- **Trade-off-lar**: hər action üçün ayrı class yazmaq class explosion yaradır; sadə method call-ların yerinə istifadə etmək over-engineering-dir
- **İstifadə etməmək**: tək istifadəlik, undo/redo tələb etməyən, queue-ya əlavə edilməyəcək sadə operasiyalarda
- **Common mistakes**: Command-ə çox business logic qoymaq — business logic Receiver-ə (Service class-a) aiddir; Command sadəcə koordinasiya edir
- **Anti-Pattern Nə Zaman Olur?**: Sadə metod çağırışları Command object-inə çevirildikdə — əgər queue, undo, history yoxdursa, `$orderService->cancel($order)` birbaşa çağırmaq `new CancelOrderCommand($order); $bus->dispatch($cmd)` yazmaqdan daha aydındır. "Command Pattern istifadə etdim" deyə hər CRUD əməliyyatı üçün ayrı Command + Handler + ayrı map yazmaq — class explosion yaradır, navigate etmək çətinləşir. Qayda: Command pattern-in dəyəri queue/async, undo/redo, audit history, ya da CQRS üçündür — bu üçü yoxdursa, sadə service method çağırışı kifayətdir.

## Nümunələr

### Ümumi Nümunə
Bir text editor düşünün. "Bold", "Italic", "Undo" — hər biri bir Command object-dir. Editor (Invoker) bu command-ləri history-də saxlayır. "Undo" düyməsi yalnız son command-in `undo()` metodunu çağırır. Bu sayədə editor business logic bilmədən istənilən action-ı geri qaytara bilir.

### PHP/Laravel Nümunəsi

```php
<?php

// Command interface
interface Command
{
    public function execute(): void;
    public function undo(): void;
}

// Receiver — əsl business logic burada
class OrderService
{
    public function createOrder(array $data): Order
    {
        return Order::create($data);
    }

    public function cancelOrder(Order $order): void
    {
        $order->update(['status' => 'cancelled']);
        event(new OrderCancelled($order));
    }
}

// ConcreteCommand
class CreateOrderCommand implements Command
{
    private ?Order $createdOrder = null;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly array $orderData
    ) {}

    public function execute(): void
    {
        $this->createdOrder = $this->orderService->createOrder($this->orderData);
    }

    public function undo(): void
    {
        if ($this->createdOrder) {
            $this->orderService->cancelOrder($this->createdOrder);
        }
    }
}

// Invoker — command history saxlayır
class CommandBus
{
    private array $history = [];

    public function execute(Command $command): void
    {
        $command->execute();
        $this->history[] = $command;
    }

    public function undo(): void
    {
        $command = array_pop($this->history);
        $command?->undo();
    }
}

// İstifadəsi
$bus = new CommandBus();
$bus->execute(new CreateOrderCommand($orderService, $orderData));
// Bir şey səhv getdisə:
$bus->undo();
```

**Laravel Jobs = Command Pattern:**

```php
// Laravel Job = Command object
class SendWelcomeEmail implements ShouldQueue
{
    public function __construct(private readonly User $user) {}

    // execute() == handle()
    public function handle(MailService $mailService): void
    {
        $mailService->sendWelcome($this->user);
    }
}

// Invoker = Queue Worker
dispatch(new SendWelcomeEmail($user));               // async
dispatch_sync(new SendWelcomeEmail($user));          // sync
SendWelcomeEmail::dispatch($user)->delay(now()->addMinutes(5));
```

**Command Bus — manual implementation:**

```php
// Command (DTO kimi — handler bilmir)
class RegisterUserCommand
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $name
    ) {}
}

// Handler (Receiver rolu)
class RegisterUserHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EventDispatcher $events
    ) {}

    public function handle(RegisterUserCommand $command): User
    {
        $user = $this->users->create([
            'email'    => $command->email,
            'password' => Hash::make($command->password),
            'name'     => $command->name,
        ]);

        $this->events->dispatch(new UserRegistered($user));

        return $user;
    }
}

// CommandBus mapping
class CommandBus
{
    private array $handlers = [
        RegisterUserCommand::class => RegisterUserHandler::class,
    ];

    public function dispatch(object $command): mixed
    {
        $handlerClass = $this->handlers[$command::class]
            ?? throw new \RuntimeException("No handler for " . $command::class);

        return app($handlerClass)->handle($command);
    }
}

// Controller — sadəcə HTTP layer
class AuthController
{
    public function register(RegisterRequest $request, CommandBus $bus): JsonResponse
    {
        $user = $bus->dispatch(new RegisterUserCommand(
            email:    $request->email,
            password: $request->password,
            name:     $request->name,
        ));

        return response()->json(['user' => $user], 201);
    }
}
```

**Laravel Artisan Command = Command Pattern:**

```php
class SyncProductPrices extends Command
{
    protected $signature = 'products:sync-prices {--dry-run}';

    // handle() = execute()
    public function handle(PriceService $priceService): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry run mode — no changes will be saved');
        }

        $priceService->syncAll(dryRun: $this->option('dry-run'));

        return Command::SUCCESS;
    }
}
```

## Praktik Tapşırıqlar
1. Shopping cart üçün `AddItemCommand`, `RemoveItemCommand`, `ClearCartCommand` yazın — hər birinin `undo()` metodu olsun; `CommandBus` history saxlasın
2. `RegisterUserCommand` + `RegisterUserHandler` cütlüyü yaradın; handler test üçün `InMemoryUserRepository` ilə işləsin
3. Mövcud bir controller metodunu `Command + Handler` pattern-inə refactor edin; command-i queue-ya atın

## Əlaqəli Mövzular
- [../creational/02-factory-method.md](../creational/02-factory-method.md) — Command object-lərinin yaradılması
- [01-observer.md](01-observer.md) — Command icrasından sonra event fire etmək
- [../laravel/02-service-layer.md](../laravel/02-service-layer.md) — Handler-ın Receiver rolu Service Layer-lə üst-üstə düşür
- [../laravel/05-event-listener.md](../laravel/05-event-listener.md) — Laravel Jobs ilə müqayisə
- [../laravel/08-command-query-bus.md](../laravel/08-command-query-bus.md) — Command Bus production tətbiqi
- [../integration/01-cqrs.md](../integration/01-cqrs.md) — Command pattern CQRS-in "Command" tərəfini əmələ gətirir
