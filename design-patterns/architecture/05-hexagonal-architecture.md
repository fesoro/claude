# Hexagonal Architecture / Ports & Adapters (Lead ⭐⭐⭐⭐)

## İcmal
Hexagonal Architecture (Ports & Adapters), domain core-u ən ortaya qoyur — heç bir framework, library və ya external system bilmir. Xarici dünya ilə kommunikasiya interface-lər (Ports) vasitəsilə aparılır; real implementasiyalar (Adapters) isə infrastruktur qatında yaşayır. Domain yalnız domain ilə danışır.

## Niyə Vacibdir
Laravel tətbiqlərinin böyük problemi: domain logic, Eloquent, Facades, artisan commands, HTTP request-lər ilə iç-içə keçir. Eloquent model dəyişəndə domain pozulur; framework versiyası yüksəldikdə business logic sınır; test etmək üçün bütün stack lazım olur. Hexagonal Architecture ilə domain pure PHP-dir — xarici dünyadan tamamilə izolyasiya olunub. Bu, test etməyi, dəyişikliyi, köçürməyi dəfə daha asan edir.

## Əsas Anlayışlar
- **Domain Core**: pure PHP — heç bir `use Illuminate\...` yoxdur; business rule-lar, entity-lər, value object-lər, domain service-lər burada
- **Primary / Driving Port**: xarici sistemin domain-i drive etmək üçün istifadə etdiyi interface (HTTP Controller, CLI Command, Test — domain-ı invoke edir)
- **Secondary / Driven Port**: domain-in xarici sistemi drive etmək üçün istifadə etdiyi interface (UserRepository, EmailSender, Cache — domain bunları çağırır, implementasiyasını bilmir)
- **Primary Adapter**: primary port-un real implementasiyası — Laravel Controller, Artisan Command, WebSocket Handler
- **Secondary Adapter**: secondary port-un real implementasiyası — EloquentUserRepository, SendgridEmailAdapter, RedisCache
- **Dependency Rule**: oxlar daxərə baxır — infrastructure domain-i bilir, domain infrastructure bilmir; əksi qətiyyən olmaz
- **Application Layer**: use case-ləri orchestrate edir; domain entity-ləri ilə port-ları koordinasiya edir; framework logic yoxdur

## Praktik Baxış
- **Real istifadə**: uzun-ömürlü enterprise tətbiqlər, multiple delivery mechanism (HTTP + CLI + Queue), test-driven development, framework migration (Laravel → Symfony mümkün olur), DDD ilə birlikdə
- **Trade-off-lar**: domain tamamilə izolyasiya, yüksək test coverage asanlığı, framework dəyişimi mümkün; **lakin** əhəmiyyətli boilerplate (hər feature üçün interface + adapter); kiçik team üçün yüksək cognitive overhead; DI setup mürəkkəbdir; produktivlik əvvəlcə aşağı düşür
- **İstifadə etməmək**: sadə CRUD admin panelləri; startup MVP (tez dəyişən requirements); 2-3 kişilik team-lər (framework structure daha praktikdir); domain logic minimaldırsa
- **Common mistakes**:
  1. **Domain-ə Laravel import etmək**: `use Illuminate\Database\Eloquent\Model` domain entity-sinin içindəsə, domain artıq pure deyil — infrastructure-a dependency var
  2. **Application layer-i skip etmək**: Controller birbaşa domain service-ə keçir — use case orchestration unudulur
  3. **Port-ları çox granular etmək**: `findById`, `findByEmail`, `findByUsername` ayrı port-lar — bir `UserRepository` port-u bəsdir
  4. **Adapter-ləri test etmək yerinə domain-i test etməmək**: sürəti üstünlük — domain test-ləri ms-lər içindədir, framework test-ləri saniyələr

### Anti-Pattern Nə Zaman Olur?

**Sadə CRUD üçün Hexagonal**: heç vaxt dəyişməyəcək `UserSettings` CRUD-u üçün `UserSettingsRepository` port-u, `EloquentUserSettingsAdapter`, `UserSettingsServiceProvider` yaratmaq — 4 class bir basit əməliyyat üçün. Hexagonal-ın dəyəri yalnız real dəyişim nöqtələrinin olduğu yerdə görünür. Hər feature üçün port yaratmayın — yalnız xarici sistemlə kommunikasiya nöqtələri üçün.

## Nümunələr

### Ümumi Nümunə
Elektrik rozetası düşünün: rozetanın (port) standart interface-i var. İstənilən cihaz (adapter) bu standarta uyğun olarsa işləyir. Rozetanın Alman mü İtalyan olduğu cihazı maraqlandırmır. Əksinə, adaptör rozetal məndən aslı — rozeta adaptordən aslı deyil. Domain rozetadır: interface standartlaşdırılmış; adapter-lər isə real cihazlardır (Eloquent, Sendgrid, Redis).

### PHP/Laravel Nümunəsi

**Layihə strukturu:**

```
src/
├── Domain/                     # Pure PHP — NO Laravel
│   └── User/
│       ├── Entity/
│       │   └── User.php
│       ├── ValueObject/
│       │   ├── UserId.php
│       │   ├── Email.php
│       │   └── HashedPassword.php
│       ├── Repository/
│       │   └── UserRepository.php      ← Secondary Port (interface)
│       ├── Service/
│       │   ├── UserRegistrationService.php
│       │   └── PasswordHasher.php      ← Secondary Port (interface)
│       └── Event/
│           └── UserRegistered.php
│
├── Application/                # Use cases — minimal Laravel dependency
│   └── User/
│       ├── Command/
│       │   └── RegisterUserCommand.php
│       └── Handler/
│           └── RegisterUserHandler.php
│
├── Infrastructure/             # Laravel adapters — implements ports
│   ├── Persistence/
│   │   └── EloquentUserRepository.php  ← Secondary Adapter
│   ├── Email/
│   │   └── SendgridEmailAdapter.php    ← Secondary Adapter
│   ├── Auth/
│   │   └── BcryptPasswordHasher.php    ← Secondary Adapter
│   └── ServiceProvider/
│       └── UserServiceProvider.php     ← DI wiring
│
└── Presentation/               # Primary adapters
    ├── Http/
    │   ├── Controller/
    │   │   └── UserController.php      ← Primary Adapter
    │   └── Request/
    │       └── RegisterUserRequest.php
    └── Console/
        └── RegisterAdminCommand.php    ← Primary Adapter (CLI)
```

**Domain Layer — Pure PHP:**

```php
<?php

namespace App\Domain\User\ValueObject;

// Value Objects — framework yoxdur
final class Email
{
    private readonly string $value;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }
        $this->value = strtolower($email);
    }

    public function equals(self $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }
}

final class UserId
{
    public function __construct(private readonly string $value)
    {
        if (empty($value)) throw new \InvalidArgumentException("UserId cannot be empty");
    }

    public static function generate(): self { return new self(uniqid('user_', true)); }
    public function __toString(): string { return $this->value; }
}

// ─────────────────────────────────────────────
// Domain Entity — NO Eloquent, NO Laravel
// ─────────────────────────────────────────────

namespace App\Domain\User\Entity;

class User
{
    private array $domainEvents = [];

    public function __construct(
        private UserId         $id,
        private Email          $email,
        private HashedPassword $password,
        private string         $name,
        private \DateTimeImmutable $createdAt,
        private bool           $isActive = true,
    ) {}

    public static function register(Email $email, HashedPassword $password, string $name): self
    {
        $user = new self(UserId::generate(), $email, $password, $name, new \DateTimeImmutable());
        $user->domainEvents[] = new UserRegistered($user->id, $email);
        return $user;
    }

    public function changeEmail(Email $newEmail): void
    {
        if ($this->email->equals($newEmail)) return;
        $this->email = $newEmail;
        $this->domainEvents[] = new UserEmailChanged($this->id, $newEmail);
    }

    public function deactivate(): void
    {
        if (!$this->isActive) throw new \DomainException("User already inactive");
        $this->isActive = false;
        $this->domainEvents[] = new UserDeactivated($this->id);
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    public function getId(): UserId    { return $this->id; }
    public function getEmail(): Email  { return $this->email; }
    public function getName(): string  { return $this->name; }
    public function isActive(): bool   { return $this->isActive; }
}
```

**Secondary Ports (Domain Interfaces):**

```php
<?php

namespace App\Domain\User\Repository;

// Secondary Port — domain tərəfindədir; implementasiya yoxdur
interface UserRepository
{
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function save(User $user): void;
    public function nextIdentity(): UserId;
}

namespace App\Domain\User\Service;

// Secondary Port — password hashing domain concern-idir; bcrypt bilmir
interface PasswordHasher
{
    public function hash(string $plainPassword): HashedPassword;
    public function verify(string $plainPassword, HashedPassword $hashed): bool;
}

// Secondary Port — email göndərmə
interface UserNotifier
{
    public function notifyRegistration(User $user): void;
    public function notifyEmailChange(User $user, Email $oldEmail): void;
}
```

**Application Layer — Use Cases:**

```php
<?php

namespace App\Application\User\Handler;

// Application layer — framework dependency minimal (yalnız exception classes)
class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,      // Secondary Port inject olunur
        private PasswordHasher $hasher,     // Secondary Port inject olunur
        private UserNotifier   $notifier,   // Secondary Port inject olunur
    ) {}

    public function handle(RegisterUserCommand $command): string
    {
        // 1. Validate
        $email = new Email($command->email);

        if ($this->users->findByEmail($email)) {
            throw new UserAlreadyExistsException("User with email {$email} already exists");
        }

        // 2. Domain operation
        $password = $this->hasher->hash($command->password);
        $user = User::register($email, $password, $command->name);

        // 3. Persist
        $this->users->save($user);

        // 4. Side effects
        $this->notifier->notifyRegistration($user);

        return (string) $user->getId();
    }
}
```

**Infrastructure Adapters:**

```php
<?php

namespace App\Infrastructure\Persistence;

// Secondary Adapter — UserRepository port-unu implement edir
class EloquentUserRepository implements UserRepository
{
    public function findById(UserId $id): ?User
    {
        $model = UserModel::find((string) $id);
        return $model ? $this->toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::where('email', (string) $email)->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function save(User $user): void
    {
        $model = UserModel::updateOrCreate(
            ['id' => (string) $user->getId()],
            [
                'email'      => (string) $user->getEmail(),
                'name'       => $user->getName(),
                'is_active'  => $user->isActive(),
            ]
        );

        // Domain events-i dispatch et
        foreach ($user->pullDomainEvents() as $event) {
            event($event);
        }
    }

    private function toDomain(UserModel $model): User
    {
        return User::reconstitute(
            id: new UserId($model->id),
            email: new Email($model->email),
            password: HashedPassword::fromHash($model->password),
            name: $model->name,
            createdAt: new \DateTimeImmutable($model->created_at),
            isActive: (bool) $model->is_active,
        );
    }
}

namespace App\Infrastructure\Auth;

// Secondary Adapter — PasswordHasher port-u
class BcryptPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): HashedPassword
    {
        return HashedPassword::fromHash(password_hash($plainPassword, PASSWORD_BCRYPT));
    }

    public function verify(string $plainPassword, HashedPassword $hashed): bool
    {
        return password_verify($plainPassword, (string) $hashed);
    }
}

namespace App\Infrastructure\Email;

// Secondary Adapter — UserNotifier port-u
class LaravelMailUserNotifier implements UserNotifier
{
    public function notifyRegistration(User $user): void
    {
        \Illuminate\Support\Facades\Mail::to((string) $user->getEmail())
            ->queue(new \App\Mail\WelcomeEmail($user->getName()));
    }

    public function notifyEmailChange(User $user, Email $oldEmail): void
    {
        \Illuminate\Support\Facades\Mail::to((string) $oldEmail)
            ->queue(new \App\Mail\EmailChangedNotification($user->getName()));
    }
}
```

**Primary Adapters:**

```php
<?php

namespace App\Presentation\Http\Controller;

// Primary Adapter — domain-i HTTP üzərindən drive edir
class UserController extends Controller
{
    public function __construct(private RegisterUserHandler $handler) {}

    public function register(RegisterUserRequest $request): JsonResponse
    {
        try {
            $userId = $this->handler->handle(new RegisterUserCommand(
                name: $request->validated('name'),
                email: $request->validated('email'),
                password: $request->validated('password'),
            ));

            return response()->json(['user_id' => $userId], 201);
        } catch (UserAlreadyExistsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}

namespace App\Presentation\Console;

// Primary Adapter — eyni domain-i CLI üzərindən drive edir
class RegisterAdminCommand extends Command
{
    protected $signature   = 'user:register-admin {email} {name}';
    protected $description = 'Register a new admin user';

    public function __construct(private RegisterUserHandler $handler) { parent::__construct(); }

    public function handle(): int
    {
        $password = $this->secret('Password:');

        $userId = $this->handler->handle(new RegisterUserCommand(
            name: $this->argument('name'),
            email: $this->argument('email'),
            password: $password,
        ));

        $this->info("Admin registered: {$userId}");
        return Command::SUCCESS;
    }
}
```

**DI Wiring — Service Provider:**

```php
<?php

namespace App\Infrastructure\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Port → Adapter binding
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(PasswordHasher::class, BcryptPasswordHasher::class);
        $this->app->bind(UserNotifier::class, LaravelMailUserNotifier::class);
    }
}
```

**Testing — domain test-i framework olmadan:**

```php
<?php

// Domain test — Laravel-siz, ms-lər içindədir
class RegisterUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private FakePasswordHasher     $hasher;
    private FakeUserNotifier       $notifier;
    private RegisterUserHandler    $handler;

    protected function setUp(): void
    {
        // Real adapters deyil — fake/in-memory implementasiyalar
        $this->users    = new InMemoryUserRepository();
        $this->hasher   = new FakePasswordHasher();
        $this->notifier = new FakeUserNotifier();

        $this->handler = new RegisterUserHandler(
            $this->users,
            $this->hasher,
            $this->notifier,
        );
    }

    public function test_registers_user_successfully(): void
    {
        $userId = $this->handler->handle(new RegisterUserCommand(
            name: 'Orkhan',
            email: 'orkhan@example.com',
            password: 'secret123',
        ));

        $this->assertNotEmpty($userId);
        $this->assertNotNull($this->users->findByEmail(new Email('orkhan@example.com')));
        $this->assertTrue($this->notifier->wasNotified('orkhan@example.com'));
    }

    public function test_throws_when_email_already_exists(): void
    {
        $this->handler->handle(new RegisterUserCommand('Orkhan', 'orkhan@example.com', 'pass'));

        $this->expectException(UserAlreadyExistsException::class);
        $this->handler->handle(new RegisterUserCommand('Other', 'orkhan@example.com', 'pass'));
    }
}

// Fake adapter — test üçün
class InMemoryUserRepository implements UserRepository
{
    private array $users = [];

    public function findByEmail(Email $email): ?User
    {
        return $this->users[(string) $email] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[(string) $user->getEmail()] = $user;
    }

    public function findById(UserId $id): ?User { return null; }
    public function nextIdentity(): UserId { return UserId::generate(); }
}
```

## Praktik Tapşırıqlar
1. Mövcud Laravel layihənizin `UserController::register()` metodunu götürün; domain, application, infrastructure qatlarına böln; `InMemoryUserRepository` ilə test yazın — heç bir Laravel serivisi olmadan
2. `ProductCatalog` domain qurun: `Product` entity, `Money` VO, `ProductRepository` port; `EloquentProductRepository` adapter; `InMemoryProductRepository` test adapter; coverage 100%
3. Framework swap testi: `BcryptPasswordHasher` yerinə `Argon2PasswordHasher` yazın; yalnız Service Provider-da binding-i dəyişdirin; domain, application, test heç dəyişmədən işləsin
4. Multiple delivery mechanism: eyni `PlaceOrderHandler` use case-i həm `OrderController` (HTTP), həm `PlaceOrderCommand` (Artisan), həm `QueuedOrderJob` (Queue) vasitəsilə çağırın; handler dəyişmir

## Əlaqəli Mövzular
- [Layered Architectures](04-layered-architectures.md) — Hexagonal-ın ənənəvi layered-dən fərqi müqayisəli izah olunur
- [Onion Architecture](06-onion-architecture.md) — DDD ilə uyğun alternativ; domain-in mərkəzdə olması eynidir
- [DDD](../ddd/01-ddd.md) — Hexagonal DDD ilə natural uyğundur; domain entity, aggregate, VO burada yaşayır
- [Aggregates](../ddd/04-ddd-aggregates.md) — aggregate root-lar domain entity kimi Hexagonal core-da yaşayır
- [CQRS](../integration/01-cqrs.md) — application layer CQRS ilə tamamlanır; command/query handler-lər use case-lərdir
- [Event Sourcing](../integration/02-event-sourcing.md) — event store secondary adapter-dır; domain port vasitəsilə bilir
- [Repository Pattern](../laravel/01-repository-pattern.md) — secondary port; hexagonal-ın əsas adapter növüdür
- [Adapter Pattern](../structural/02-adapter.md) — secondary adapters GoF Adapter pattern-in tətbiqidir
