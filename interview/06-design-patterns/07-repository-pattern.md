# Repository Pattern (Senior ⭐⭐⭐)

## İcmal

Repository pattern — data access logic-i business logic-dən ayıran structural pattern-dir. Domain layer (service-lər, business logic) data-nın haradan gəldiyini bilmir — database, cache, API, in-memory. Repository bu abstraction layer-ı təmin edir. Laravel-də Eloquent-i birbaşa service-lərdə istifadə etmək Repository-nin olmadığı vəziyyətdir. Interview-larda bu pattern data layer abstraction, testability, DDD (Domain-Driven Design) sualları ilə birlikdə gəlir.

## Niyə Vacibdir

Repository pattern-in əsas dəyəri testability-dir. Repository interface varsa, service test-ləri real database olmadan in-memory fake repository ilə işləyə bilir — testlər 10-100x sürətlənir. İkinci dəyər: ORM dəyişsə (Eloquent → Doctrine), service layer-ı toxunmadan yalnız repository implementasiyasını dəyişmək lazım olur. Interviewer bu mövzuda yoxlayır: "Active Record vs Repository fərqi?" "Laravel-də Repository lazımdırmı?" Bu sualları cavablandırmaq praktik dizayn düşüncənizi göstərir.

## Əsas Anlayışlar

**Repository nədir:**
- Collection-like interface data-ya daxil olmaq üçün. `find(id)`, `findAll()`, `save(entity)`, `delete(entity)` — database olmayan bir collection kimi görünür
- Domain layer `findActiveUsers()` çağırır — SQL haqqında bilmir

**Active Record vs Repository:**
- **Active Record** (Eloquent): Model həm data, həm business logic, həm persistence bilir. `User::find(1)`, `$user->save()`. Sadə, fast to write, lakin model-ə database dependency daxil olur
- **Repository**: Domain object persistence-dən ayrıdır. `$repo->findById(1)` — model/entity yalnız data daşıyır
- Laravel-in Eloquent-i Active Record pattern-dir — bu onun dizayn seçimidir

**Repository interface:**
- Interface olmadan Repository-nin faydası azdır — service concrete implementation-a bağlı qalır
- Interface: `UserRepositoryInterface` — `find()`, `findByEmail()`, `save()`, `delete()`
- Concrete: `EloquentUserRepository` — Eloquent istifadə edir
- Fake: `InMemoryUserRepository` — test-lərdə istifadə olunur

**Generic vs Specific Repository:**
- Generic: `Repository<T>` — `find()`, `findAll()`, `save()`, `delete()` — hər entity üçün eyni
- Specific: `UserRepository` — `findByEmail()`, `findActiveUsers()`, `findByRole()` — domain-specific methods
- Tövsiyə: Generic base + domain-specific extension

**Unit of Work pattern:**
- Repository ilə birlikdə istifadə olunur. Əməliyyat boyunca dəyişiklikləri track edir, sonda bir transaction ilə database-ə yazır
- Laravel-in DB::transaction() — primitiv Unit of Work

**Query Object:**
- Complex query-ləri encapsulate edir. `ActiveUsersWithOrdersQuery` class-ı sorğu məntiğini saxlayır
- Repository-nin `findAll(QueryObject $query)` metodu ilə birlikdə istifadə olunur

**Laravel-də Repository lazımdırmı:**
- Kiçik/orta layihə: Eloquent birbaşa service-lərdə qəbul edilə bilər
- Böyük layihə / test coverage vacibdir / ORM dəyişmə ehtimalı var → Repository tövsiyə olunur
- Pragmatic yanaşma: Eloquent-i service-ə inject et, test üçün mock et — tam Repository olmadan da mümkündür

## Praktik Baxış

**Interview-da yanaşma:**
Repository-nin niyə lazım olduğunu testability üzərindən izah edin. "Eloquent birbaşa service-də istifadə etsəm, service unit test-lərim real database-ə bağlı olur. Repository interface ilə in-memory fake inject edib database olmadan test edirəm."

**Follow-up suallar:**
- "Laravel Eloquent-i service-də birbaşa istifadə etmək niyə bəzən qəbul edilə bilər?" → Small/medium app, team expertise, pragmatic tradeoff
- "Repository ilə Eloquent scope-larını necə birləşdirirsiniz?" → `ActiveUsersQuery` scope-u repository method içindəki Eloquent query-yə əlavə etmək
- "Repository cache-i haraya koyursunuz?" → Repository implementation içinə — service bilmir cache varmı
- "DDD entity vs Eloquent model fərqi?" → DDD entity: Business invariants, rich domain logic. Eloquent: Database table mapping, ActiveRecord

**Ümumi səhvlər:**
- Repository interface olmadan implement etmək — concrete implementation-a tie edilir
- Repository-nin Eloquent query-ləri üçün wrapper olduğunu düşünmək — əslində domain contract-dır
- Hər entity üçün məcburi Repository yaratmaq — pragmatism vacibdir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Unit of Work ilə Repository birlikdə istifadəsini, Query Object pattern-i, ya da DDD Aggregate Root kontekstındəki Repository-ni izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Your UserService is tightly coupled to Eloquent. You want to make it testable without a database and potentially switch ORMs in the future. How would you introduce the Repository Pattern?"

### Güclü Cavab

İlk addım: `UserRepositoryInterface` yaratmaq. Domain-in ehtiyacı olan metodları müəyyən etmək: `findById()`, `findByEmail()`, `save()`, `delete()`, `findActiveWithOrders()`.

İkinci addım: `EloquentUserRepository` — interface-i implement edir, Eloquent istifadə edir.

Üçüncü addım: `InMemoryUserRepository` test-lər üçün — database olmadan, sürətli.

`UserService`-in constructor-unda `UserRepositoryInterface` inject edilir. ServiceProvider-da `EloquentUserRepository` bind edilir. Test-lərdə `InMemoryUserRepository` inject edilir.

Artıq service test-ləri 100ms əvəzinə 5ms işləyir. ORM dəyişsə — yalnız `EloquentUserRepository` yenisi ilə əvəz olunur, `UserService` toxunulmur.

### Kod Nümunəsi

```php
// Repository Interface — domain contract
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;

    /** @return Collection<User> */
    public function findActive(): Collection;

    /** @return Collection<User> */
    public function findWithPendingOrders(): Collection;

    public function save(User $user): User;
    public function delete(User $user): void;
    public function existsByEmail(string $email): bool;
}

// Eloquent Implementation
class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findActive(): Collection
    {
        return User::where('status', 'active')
            ->where('email_verified_at', '!=', null)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findWithPendingOrders(): Collection
    {
        return User::whereHas('orders', fn($q) => $q->where('status', 'pending'))
            ->with('orders:id,user_id,status,total')
            ->get();
    }

    public function save(User $user): User
    {
        $user->save();
        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
}

// In-Memory Implementation (test üçün)
class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];
    private int $nextId = 1;

    public function findById(int $id): ?User
    {
        return collect($this->users)->firstWhere('id', $id);
    }

    public function findByEmail(string $email): ?User
    {
        return collect($this->users)->firstWhere('email', $email);
    }

    public function findActive(): Collection
    {
        return collect($this->users)
            ->filter(fn(User $u) => $u->status === 'active' && $u->email_verified_at !== null)
            ->values();
    }

    public function findWithPendingOrders(): Collection
    {
        return collect($this->users)
            ->filter(fn(User $u) => $u->orders->where('status', 'pending')->isNotEmpty())
            ->values();
    }

    public function save(User $user): User
    {
        if (!$user->id) {
            $user->id = $this->nextId++;
        }
        $this->users[$user->id] = $user;
        return $user;
    }

    public function delete(User $user): void
    {
        unset($this->users[$user->id]);
    }

    public function existsByEmail(string $email): bool
    {
        return collect($this->users)->contains('email', $email);
    }
}

// Service — repository interface-indən asılıdır
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,  // Interface!
        private readonly EventDispatcher $events,
    ) {}

    public function register(RegisterUserDTO $dto): User
    {
        if ($this->users->existsByEmail($dto->email)) {
            throw new EmailAlreadyTakenException($dto->email);
        }

        $user = new User([
            'email'    => $dto->email,
            'password' => Hash::make($dto->password),
            'status'   => 'active',
        ]);

        $user = $this->users->save($user);
        $this->events->dispatch(new UserRegistered($user->id, $user->email, now()));

        return $user;
    }
}

// ServiceProvider binding
$this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);

// Test — database olmadan
class UserServiceTest extends TestCase
{
    public function test_register_user_successfully(): void
    {
        $repo    = new InMemoryUserRepository();
        $events  = new FakeEventDispatcher();
        $service = new UserService($repo, $events);

        $dto  = new RegisterUserDTO('test@example.com', 'password123');
        $user = $service->register($dto);

        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue($events->wasDispatched(UserRegistered::class));
    }
}
```

## Praktik Tapşırıqlar

- `OrderRepositoryInterface` yazın, `EloquentOrderRepository` implement edin
- `InMemoryOrderRepository` yazın — test-ləri database olmadan işlədin
- Repository-yə cache əlavə edin: `CachedUserRepository` wraps `EloquentUserRepository`
- Query Object pattern: `ActiveUsersWithOrdersQuery` class-ı → Repository-nin `findAll(Query $q)` metodu
- DI Container binding: Feature flag ilə production-da Eloquent, canary-da MongoDB implementation

## Əlaqəli Mövzular

- [SOLID Principles](01-solid-principles.md) — DIP: service-lər interface-ə depend edir
- [Dependency Injection](11-dependency-injection.md) — Repository DI ilə inject edilir
- [Decorator Pattern](08-decorator-pattern.md) — Cached Repository: decorator tətbiqi
- [Proxy Pattern](13-proxy-pattern.md) — Lazy-loading Repository proxy
- [Specification Pattern](15-specification-pattern.md) — Repository + Specification query compositing
