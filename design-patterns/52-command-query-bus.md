# Command Bus, Query Bus, Mediator Pattern (Senior)

## Mündəricat
1. [Mediator pattern nədir?](#mediator-pattern-nədir)
2. [Command Bus](#command-bus)
3. [Query Bus](#query-bus)
4. [Handler pattern](#handler-pattern)
5. [Middleware pipeline (bus middleware)](#middleware-pipeline-bus-middleware)
6. [Symfony Messenger](#symfony-messenger)
7. [Laravel Bus](#laravel-bus)
8. [League Tactician](#league-tactician)
9. [Async command (queue inteqrasiya)](#async-command-queue-inteqrasiya)
10. [Pitfalls](#pitfalls)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Mediator pattern nədir?

```
Problem:
  100+ controller var, hər birinin service dependency-si fərqli.
  UserController → UserService, EmailService, LoggerService, CacheService...
  
  Controller-lər "fat" olur, tight coupling yaranır.

Mediator həlli:
  Controller bilməlidir yalnız: "Bu komanda/sorğu göndərirəm".
  Kim handle edir? — Mediator qərar verir (route edir).
  
  Controller → Mediator (Bus) → Handler (business logic)

Fayda:
  ✓ Controller thin — yalnız HTTP-dən bus-a çevirir
  ✓ Handler-lər təkil məsuliyyətli (SRP)
  ✓ Business logic framework-independent
  ✓ Bus middleware — logging, transaction, validation cross-cutting
  ✓ Test asan — handler birbaşa test olunur
  ✓ Async dəstəyi asan (queue-ya göndər)
```

---

## Command Bus

```
Command — bir action, nəticə gözləmir (və ya void/success qaytarır).
Verb şəklində — CreateUser, UpdateOrderStatus, SendEmail.

Command Bus — command-i uyğun handler-ə çatdırır.
```

```php
<?php
// 1. Command class — immutable data
class CreateUserCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}
}

// 2. Handler — business logic
class CreateUserHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private EventDispatcher $events,
    ) {}
    
    public function __invoke(CreateUserCommand $command): void
    {
        if ($this->users->existsByEmail($command->email)) {
            throw new UserAlreadyExistsException();
        }
        
        $user = new User(
            name: $command->name,
            email: $command->email,
            password: $this->hasher->hash($command->password),
        );
        
        $this->users->save($user);
        
        $this->events->dispatch(new UserRegistered($user));
    }
}

// 3. Bus interface
interface CommandBus
{
    public function dispatch(object $command): void;
}

// 4. Basit implementasiya
class SimpleCommandBus implements CommandBus
{
    public function __construct(
        private array $handlers,   // Command::class => Handler
        private Container $container,
    ) {}
    
    public function dispatch(object $command): void
    {
        $handlerClass = $this->handlers[$command::class]
            ?? throw new \RuntimeException("No handler for " . $command::class);
        
        $handler = $this->container->get($handlerClass);
        $handler($command);
    }
}

// 5. Controller istifadəsi
class RegisterController
{
    public function __construct(private CommandBus $bus) {}
    
    public function register(Request $req): Response
    {
        $this->bus->dispatch(new CreateUserCommand(
            name: $req->input('name'),
            email: $req->input('email'),
            password: $req->input('password'),
        ));
        
        return response()->json(['status' => 'ok']);
    }
}
```

---

## Query Bus

```
Query — məlumat oxumaq, mutation yox. Həmişə nəticə qaytarır.
Noun formada — GetUserById, ListOrders, SearchProducts.

CQRS (Command Query Responsibility Segregation):
  Command ≠ Query — fərqli model, fərqli bus.
```

```php
<?php
// Query class
class GetUserByIdQuery
{
    public function __construct(public readonly int $id) {}
}

// Query handler — nəticə QAYTARIR
class GetUserByIdHandler
{
    public function __construct(private UserReadModel $reader) {}
    
    public function __invoke(GetUserByIdQuery $query): ?UserView
    {
        return $this->reader->findById($query->id);
    }
}

// Query bus
interface QueryBus
{
    public function ask(object $query): mixed;
}

// İstifadə
class UserController
{
    public function __construct(private QueryBus $queries) {}
    
    public function show(int $id): Response
    {
        $user = $this->queries->ask(new GetUserByIdQuery($id));
        
        if (!$user) {
            return response()->json(['error' => 'not found'], 404);
        }
        
        return response()->json($user);
    }
}

// Niyə Command və Query ayrı?
//   Command: yazır, transactional, retry-able, async ola bilər
//   Query:   oxuyur, cache-lenebile, read-replica istifadə edə bilər
//   CQRS scale-də: fərqli storage (write DB + read model)
```

---

## Handler pattern

```
Handler — tək məsuliyyətli class, bir command/query üçün.
"Action" / "Use case" / "Interactor" eyni konsept (müxtəlif ad).

Naming:
  CreateUserCommand        → CreateUserHandler
  CreateUserAction         → CreateUserHandler
  RegisterUser (action)    → RegisterUserUseCase
  CreateUser use case       → CreateUserInteractor

Struktur:
  app/
    UseCases/
      User/
        CreateUser/
          CreateUserCommand.php
          CreateUserHandler.php
          CreateUserResponse.php  (lazımdırsa)
        GetUser/
          GetUserByIdQuery.php
          GetUserByIdHandler.php

Qaydalar:
  ✓ 1 handler = 1 use case
  ✓ Handler state-siz (no instance property dəyişmir)
  ✓ Handler bir method-a sahibdir — __invoke və ya handle
  ✓ Framework-less handler-lər (Symfony/Laravel-ə bağlı deyil)
```

---

## Middleware pipeline (bus middleware)

```
Command bus handler-ə gələnə qədər middleware-lərdən keçir.
Cross-cutting concerns — transaction, logging, validation, auth.

    dispatch(command)
         │
         ▼
    [Logger middleware]    — log start
         │
         ▼
    [Validation middleware] — validate data
         │
         ▼
    [Transaction middleware] — DB transaction başla
         │
         ▼
    [Handler]                — business logic
         │
         ▼
    [Transaction middleware] — commit / rollback
         │
         ▼
    [Logger middleware]    — log end
```

```php
<?php
interface Middleware
{
    public function handle(object $command, callable $next): mixed;
}

class TransactionMiddleware implements Middleware
{
    public function __construct(private DatabaseManager $db) {}
    
    public function handle(object $command, callable $next): mixed
    {
        return $this->db->transaction(function () use ($command, $next) {
            return $next($command);
        });
    }
}

class LoggingMiddleware implements Middleware
{
    public function __construct(private LoggerInterface $log) {}
    
    public function handle(object $command, callable $next): mixed
    {
        $this->log->info("Dispatching " . $command::class);
        $start = microtime(true);
        
        try {
            $result = $next($command);
            $this->log->info("Success", ['duration' => microtime(true) - $start]);
            return $result;
        } catch (\Throwable $e) {
            $this->log->error("Failed", ['exception' => $e]);
            throw $e;
        }
    }
}

class ValidationMiddleware implements Middleware
{
    public function __construct(private ValidatorInterface $v) {}
    
    public function handle(object $command, callable $next): mixed
    {
        $violations = $this->v->validate($command);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }
        return $next($command);
    }
}

// Pipeline builder
class PipelineCommandBus implements CommandBus
{
    public function __construct(
        private array $middleware,     // Middleware[]
        private CommandBus $terminalBus,
    ) {}
    
    public function dispatch(object $command): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $mw) => fn($cmd) => $mw->handle($cmd, $next),
            fn($cmd) => $this->terminalBus->dispatch($cmd)
        );
        
        return $pipeline($command);
    }
}
```

---

## Symfony Messenger

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: command.bus
        
        buses:
            command.bus:
                middleware:
                    - validation
                    - doctrine_transaction
            query.bus:
                middleware:
                    - validation
            event.bus:
                default_middleware: allow_no_handlers
        
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
            sync: 'sync://'
        
        routing:
            'App\Message\Command\*': async     # bütün command-lar queue-ya
            'App\Message\Query\*': sync        # query sync
```

```php
<?php
// Command
namespace App\Message\Command;

class CreateUserCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}

// Handler
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateUserHandler
{
    public function __construct(private UserRepository $users) {}
    
    public function __invoke(CreateUserCommand $cmd): void
    {
        // ...
    }
}

// Dispatch
use Symfony\Component\Messenger\MessageBusInterface;

class UserController extends AbstractController
{
    public function __construct(private MessageBusInterface $commandBus) {}
    
    public function register(Request $req): Response
    {
        $this->commandBus->dispatch(new CreateUserCommand(...));
        return new JsonResponse(['ok' => true]);
    }
}

// Async consumer
// php bin/console messenger:consume async
```

---

## Laravel Bus

```php
<?php
// Laravel Command (Job) — default olaraq async queue
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class CreateUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
    
    public function handle(UserRepository $users, EventDispatcher $events): void
    {
        $user = new User($this->name, $this->email);
        $users->save($user);
        $events->dispatch(new UserRegistered($user));
    }
}

// Dispatch
CreateUser::dispatch('Ali', 'a@b.com');        // queue-ya düşür
CreateUser::dispatchSync('Ali', 'a@b.com');    // sync işlənir

// Laravel-in native Bus-u həm "Job" (async), həm "Command" kimidir
// "ShouldQueue" interface-i olmayan Job-lar sync işləyir
```

---

## League Tactician

```bash
composer require league/tactician
composer require league/tactician-container  # DI container handler resolution
```

```php
<?php
use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use League\Tactician\Handler\Locator\InMemoryLocator;

$locator = new InMemoryLocator();
$locator->addHandler(new CreateUserHandler(), CreateUserCommand::class);

$handlerMiddleware = new CommandHandlerMiddleware(
    new ClassNameExtractor(),
    $locator,
    new HandleInflector()
);

$bus = new CommandBus([
    new LoggingMiddleware($logger),
    new TransactionMiddleware($db),
    $handlerMiddleware,  // last — terminal
]);

$bus->handle(new CreateUserCommand('Ali', 'a@b.com'));
```

---

## Async command (queue inteqrasiya)

```php
<?php
// Bəzi command-lar sync, bəziləri async — bus qərar verir

// Marker interface
interface AsyncCommand {}

class SendWelcomeEmailCommand implements AsyncCommand {
    public function __construct(public readonly int $userId) {}
}

// Bus middleware
class AsyncDispatchMiddleware implements Middleware
{
    public function __construct(private Queue $queue) {}
    
    public function handle(object $command, callable $next): mixed
    {
        if ($command instanceof AsyncCommand) {
            $this->queue->push(
                new ExecuteCommandJob(get_class($command), get_object_vars($command))
            );
            return null;  // sync return yox
        }
        
        return $next($command);   // adi flow
    }
}

// Worker tərəfdə (queue-dan gələn job)
class ExecuteCommandJob
{
    public function handle(CommandBus $bus, SerializerInterface $serializer)
    {
        $command = $serializer->deserialize($this->data, $this->class);
        $bus->dispatch($command);    // sync work (amma artıq worker context-dədir)
    }
}
```

---

## Pitfalls

```
❌ Anti-patterns
  - Handler-də başqa command dispatch — nested, gizli dependency
  - Query handler-də state dəyişdir (CQRS pozulur)
  - Command-da primitive parameter sel (no validation, weak typing)
  - Bus-u everywhere inject et — over-engineering
  - Framework-specific command — portable deyil
  - "Fat command" — 20 parametr (use case bölün)
  - Synchronous command chain — transaction timeout

✓ Best practices
  - Command/query immutable (readonly constructor)
  - Handler stateless + dependency injection ilə
  - 1 handler = 1 command mapping
  - Middleware pipeline ilə cross-cutting
  - Validation command-ə giriş nöqtəsində (FormRequest, Command assert)
  - Bus abstraction (framework-independent interface)
  - Command async qərarı bus middleware və ya marker interface ilə
```

---

## İntervyu Sualları

- Command Bus pattern-in əsas məqsədi nədir?
- Command və Query arasındakı fərq nədir (CQRS)?
- Handler stateless niyə olmalıdır?
- Bus middleware hansı problemi həll edir?
- Command-i async işlətmək üçün hansı yanaşmalar var?
- Symfony Messenger və Laravel Bus arasında fərq?
- League Tactician niyə seçilir (built-in Laravel Bus əvəzinə)?
- "Fat command" nədir və necə parçalanır?
- Controller-də birbaşa service çağırmaq yerinə command bus-dan istifadə — faydaları?
- Query handler-in nəticəsi cache olunmalıdır? Necə?
- Command validation harada edilməlidir?
- Command bus-dan `event dispatcher`-ə hansı fərq var?
