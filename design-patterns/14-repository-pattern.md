# Repository Pattern (Middle ⭐⭐)

## İcmal
Repository pattern data access logic-i business logic-dən ayıran bir abstraction layer-dir. Business logic konkret storage mexanizmini (MySQL, Redis, API, flat file) bilmir — yalnız interface ilə danışır. Bu sayədə storage-ı dəyişmək, test etmək və kodu ayrı-ayrı inkişaf etdirmək asanlaşır.

## Niyə Vacibdir
Laravel layihələrinin böyüməsi ilə controller-larda, service-lərdə Eloquent query-ləri yayılır. Unit test yazmaq çətinləşir (real DB tələb edir), storage-ı dəyişmək (Eloquent → API) bütün kod bazasını təsir edir. Repository bu problemi bir yerdə həll edir.

## Əsas Anlayışlar
- **Repository interface**: contract — hansı data operations mövcuddur (find, save, delete, search)
- **Concrete repository**: interface-i implement edən sinif (EloquentUserRepository, ApiUserRepository)
- **In-memory repository**: test üçün real storage olmadan işləyən fake implementasiya
- **Query Object**: repository-ə filterleme/sorting şərtlərini ötürmək üçün object
- **Thin repository**: yalnız data access — business logic SERVICE-ə aiddir
- **Unit of Work**: bir neçə repository-ni bir transaction-da birləşdirmək

## Praktik Baxış
- **Real istifadə**: testability vacib olan layihələr, storage dəyişikliyi planlananda, microservice-ə keçid düşünüləndə, DDD (Domain-Driven Design) tətbiq olunanda
- **Trade-off-lar**: əlavə abstraction layer = əlavə kod, əlavə complexity; Eloquent-in güclü query API-si (scope, eager loading, chunk) repository arxasında itirilə bilər
- **İstifadə etməmək**: kiçik/orta Laravel layihələrində (Eloquent direkt istifadə sadədir); prototip mərhələsindəki layihələrdə; team Eloquent-in query API-sinə öyrəşibsə
- **Common mistakes**:
  1. Repository-yə business logic qoymaq (`calculateDiscount()`, `sendEmail()`)
  2. Hər model üçün repository yaratmaq — real ehtiyac olmadan
  3. Generic repository (`findBy()`, `findAll()`) — Eloquent-in güclü API-sini bypass edir
  4. Repository-dən başqa repository çağırmaq — coupling artır

## Nümunələr

### Ümumi Nümunə
Bir onlayn mağazada `OrderService.placeOrder()` metodu var. Bu metod `user-i tap`, `inventory yoxla`, `order saxla`, `payment al` əməliyyatlarını edir. Test zamanı real DB lazım olmamalıdır — `InMemoryOrderRepository` ilə test etmək mümkün olmalıdır. Repository bu izolasiyani təmin edir.

### PHP/Laravel Nümunəsi

**Interface müəyyən etmək:**

```php
<?php

namespace App\Repositories\Contracts;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findAll(UserFilter $filter): Collection;
    public function save(User $user): User;
    public function delete(int $id): void;
    public function existsByEmail(string $email): bool;
}
```

**Eloquent implementasiyası:**

```php
namespace App\Repositories;

use App\Repositories\Contracts\UserRepositoryInterface;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findAll(UserFilter $filter): Collection
    {
        $query = User::query();

        if ($filter->isActive !== null) {
            $query->where('is_active', $filter->isActive);
        }

        if ($filter->role) {
            $query->where('role', $filter->role);
        }

        if ($filter->search) {
            $query->where(function ($q) use ($filter) {
                $q->where('name', 'like', "%{$filter->search}%")
                  ->orWhere('email', 'like', "%{$filter->search}%");
            });
        }

        return $query->orderBy($filter->sortBy, $filter->sortDir)
                     ->paginate($filter->perPage);
    }

    public function save(User $user): User
    {
        $user->save();
        return $user->fresh();
    }

    public function delete(int $id): void
    {
        User::destroy($id);
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
}
```

**Test üçün In-Memory implementasiyası:**

```php
namespace App\Repositories\Fake;

class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $store = [];
    private int   $nextId = 1;

    public function find(int $id): ?User
    {
        return $this->store[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        return collect($this->store)
            ->first(fn(User $u) => $u->email === $email);
    }

    public function findAll(UserFilter $filter): Collection
    {
        return collect($this->store)
            ->when($filter->isActive !== null, fn($c) =>
                $c->where('is_active', $filter->isActive)
            )
            ->values();
    }

    public function save(User $user): User
    {
        if (!$user->id) {
            $user->id = $this->nextId++;
        }
        $this->store[$user->id] = $user;
        return $user;
    }

    public function delete(int $id): void
    {
        unset($this->store[$id]);
    }

    public function existsByEmail(string $email): bool
    {
        return collect($this->store)->contains(fn(User $u) => $u->email === $email);
    }
}
```

**ServiceProvider-da binding:**

```php
namespace App\Providers;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        // Test environment-da fake repository
        if ($this->app->environment('testing')) {
            $this->app->bind(
                UserRepositoryInterface::class,
                InMemoryUserRepository::class
            );
        }
    }
}
```

**Service class — business logic burada, repository sadəcə data access:**

```php
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EventDispatcher $events
    ) {}

    public function register(RegisterUserData $data): User
    {
        // Business rule — repository bilmir
        if ($this->users->existsByEmail($data->email)) {
            throw new EmailAlreadyTakenException($data->email);
        }

        $user = new User([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => Hash::make($data->password),
        ]);

        $saved = $this->users->save($user);

        // Business side effect — repository bilmir
        $this->events->dispatch(new UserRegistered($saved));

        return $saved;
    }
}
```

**Test — real DB olmadan:**

```php
class UserServiceTest extends TestCase
{
    public function test_register_throws_if_email_taken(): void
    {
        $repo = new InMemoryUserRepository();

        // Mövcud user əlavə et
        $existing = new User(['email' => 'test@example.com']);
        $repo->save($existing);

        $service = new UserService($repo, new NullEventDispatcher());

        $this->expectException(EmailAlreadyTakenException::class);

        $service->register(new RegisterUserData(
            name:     'New User',
            email:    'test@example.com',
            password: 'secret'
        ));
    }

    public function test_register_fires_user_registered_event(): void
    {
        $repo   = new InMemoryUserRepository();
        $events = new FakeEventDispatcher();

        $service = new UserService($repo, $events);
        $service->register(new RegisterUserData(
            name:     'Alice',
            email:    'alice@example.com',
            password: 'secret'
        ));

        $this->assertTrue($events->hasDispatched(UserRegistered::class));
    }
}
```

**Query Object pattern — repository-ə filter ötürmək:**

```php
// Query object — filter criteria encapsulate edir
class UserFilter
{
    public function __construct(
        public readonly ?bool   $isActive  = null,
        public readonly ?string $role      = null,
        public readonly ?string $search    = null,
        public readonly string  $sortBy    = 'created_at',
        public readonly string  $sortDir   = 'desc',
        public readonly int     $perPage   = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            isActive: $request->boolean('is_active'),
            role:     $request->input('role'),
            search:   $request->input('search'),
            sortBy:   $request->input('sort_by', 'created_at'),
            sortDir:  $request->input('sort_dir', 'desc'),
            perPage:  $request->integer('per_page', 15),
        );
    }
}

// Controller — query object yaradır, repository-ə ötürür
class UserController
{
    public function index(Request $request, UserRepositoryInterface $users): JsonResponse
    {
        $filter = UserFilter::fromRequest($request);
        $result = $users->findAll($filter);

        return response()->json(UserResource::collection($result));
    }
}
```

## Praktik Tapşırıqlar
1. `ProductRepositoryInterface` yazın (`findBySku()`, `findByCategory()`, `findLowStock()` metodları ilə); `EloquentProductRepository` implement edin; `InMemoryProductRepository` test üçün implement edin
2. Mövcud bir controller-da Eloquent-i birbaşa istifadə edən kodu repository arxasına keçirin; unit test yazın
3. `UserFilter` query object yaradın; controller-dan `Request` → `UserFilter` → repository pipeline qurun

## Əlaqəli Mövzular
- [15-service-layer.md](15-service-layer.md) — Repository-ni istifadə edən service layer
- [04-singleton.md](04-singleton.md) — Repository-lərin DI container-da binding-i
- [07-adapter.md](07-adapter.md) — Xarici API-ni repository interface-inə adapt etmək
- [11-command.md](11-command.md) — Command Handler-lar repository istifadə edir
