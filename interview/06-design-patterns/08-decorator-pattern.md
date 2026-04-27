# Decorator Pattern (Senior ⭐⭐⭐)

## İcmal

Decorator pattern — bir object-in davranışını dinamik olaraq, inheritance olmadan genişləndirən structural pattern-dir. "Attach additional responsibilities to an object dynamically. Decorators provide a flexible alternative to subclassing for extending functionality." Əsas ideya: Decorator, wrap etdiyi object ilə eyni interface-i implement edir, lakin öz məntiqini əlavə edir. Laravel-in cache, logging, middleware sistemi — Decorator pattern-in real produksiya tətbiqlərindədir. Interview-larda bu pattern composition-over-inheritance suallarında, middleware dizaynında, ya da "CachedRepository necə implement edərsiniz?" sualında çıxır.

## Niyə Vacibdir

Decorator pattern-in əsas gücü — mövcud kodu dəyişmədən yeni davranış əlavə etməkdir. Subclass yaratmaq: Hər kombinasiya üçün ayrı subclass lazımdır — `CachedLoggingUserRepository`, `LoggingUserRepository`, `CachedUserRepository` — kombinatorik partlama. Decorator ilə: `new LoggingDecorator(new CachingDecorator(new EloquentUserRepository()))` — istənilən kombinasiya, istənilən sırada. Interviewer bu mövzuda yoxlayır: "Composition vs inheritance fərqi nədir?" "Middleware pipeline necə işləyir?" "Cache + logging ayrı class-larda necə tutulur?" Bu sualları cavablandırmaq OOP dərinliyinizi ortaya qoyur.

## Əsas Anlayışlar

**Decorator pattern komponentləri:**
- **Component interface**: Həm real object, həm decorator-ların implement etdiyi contract
- **Concrete Component**: Əsas davranış — wrap edilən real object
- **Base Decorator**: Component interface-i implement edir, daxilindəki component-ə referans saxlayır
- **Concrete Decorators**: Əsas davranışdan əvvəl/sonra öz məntiqini əlavə edir

**Decorator vs Subclassing:**
- Subclassing compile-time-da fixdir. Decorator runtime-da dinamik olaraq əlavə edilir
- 3 feature üçün subclass: 2^3 = 8 kombinasiya lazımdır. Decorator ilə: 3 decorator class, istənilən sırada stack edilir
- Inheritance: "is-a" relationship. Decorator: "wraps-a" relationship

**Transparent wrapping:**
- Decorator, wrap etdiyi object ilə eyni interface-i implement edir
- Client dekorasiya olunmuş mu, olmamış mu — bilmir. Bu şəffaflıq (transparency) pattern-in gücüdür

**Sıra vacibdir:**
- `new Cache(new Log(repo))` — əvvəl cache yoxlanır, miss olsa log edilərək repo-ya gedir
- `new Log(new Cache(repo))` — hər sorğu log edilir, sonra cache yoxlanır
- Müxtəlif sıra müxtəlif davranış verir

**PHP/Laravel middleware:**
- Laravel HTTP middleware stack — Decorator pattern-dir. Hər middleware request/response-u wrap edir
- `$middleware = [Auth::class, ThrottleRequests::class, ValidatePostSize::class]`
- Daxildən xaricə: Route handler, sonra ValidatePostSize, sonra Throttle, sonra Auth

**I/O Streams (Java analogy):**
- `new BufferedReader(new InputStreamReader(new FileInputStream("file.txt")))` — klassik Decorator nümunəsi
- Hər layer ayrı məsuliyyət: file okuma, character encoding, buffering

**Anti-pattern: Too many decorators:**
- Decorator stack çox dərin olduqda debug çətin olur. Stack trace-də 10 decorator layer görəndə problem harada olduğu aydın olmur
- Çözüm: Decorator-ları explicit adlandırmaq, logging decorator-u hər zaman ən xaricdə saxlamaq

**Decorator vs Proxy:**
- Decorator: Davranış əlavə edir (open extension). Proxy: Giriş idarə edir (access control, lazy loading)
- Real fərq: Intent. Decorator — enrich. Proxy — control

**Decorator vs Chain of Responsibility:**
- Chain of Responsibility: Zəncirlənmiş handler-lar, hər biri request-i forward edə bilər ya da qəbul edə bilər
- Decorator: Hamı iştirak edir, birinin davranışı digərini wrap edir

## Praktik Baxış

**Interview-da yanaşma:**
Dekorasiya olunmuş repository nümunəsindən başlayın: "EloquentUserRepository-ni cache layer-la wrap etmək istəyirəm, lakin service-i dəyişmədən." CachedUserRepository → EloquentUserRepository wrapping nümunəsini izah edin. Sonra middleware analogy-si ilə birləşdirin.

**"Nə vaxt Decorator seçərdiniz?" sualına cavab:**
- Cross-cutting concern (logging, caching, validation) mövcud class-a əlavə etmək lazım olduqda
- Kombinasiyalar çox olduqda (cache + log + retry — hər üçü ayrı class olmalı)
- Runtime-da davranış dəyişdirmək lazım olduqda
- Open-Closed Principle qorumaq lazım olduqda

**Anti-pattern-lər:**
- Decorator-ı Component interface olmadan implement etmək — type safety itirilir, client decorator-ı görür
- Decorator-ın component-i birbaşa extend etməsi — bu inheritance-dir, Decorator deyil
- Stateful decorator-larda thread-safety-i unutmaq (shared cache decorator-da race condition)
- Decorator stack-ini invisible etmək — debug-da nə wrap etdiyini bilmək lazımdır

**Follow-up suallar:**
- "Laravel middleware Decorator pattern-dirmi?" → Bəli — eyni interface (Closure $next), wrap etmə, sıra vacibdir
- "CachedRepository-nin cache invalidation-ını haraya koyarsınız?" → Decorator içinə: `save()` çağrılanda cache flush
- "Decorator thread-safe olmalıdırmı?" → Stateless decorator-lar thread-safe. Stateful-lar (cache) mutex/atomic ops lazım
- "Decorator-ı runtime-da necə əlavə/çıxarmaq olar?" → Decorator stack-i list-də saxlamaq, dinamik compose etmək

## Nümunələr

### Tipik Interview Sualı

"Your UserRepository is used by many services. You want to add caching to reduce database load, and logging for debugging — but without modifying the repository or the services. How would you implement this?"

### Güclü Cavab

Bu Decorator pattern-in ideal use-case-idir. `UserRepositoryInterface` artıq mövcuddur. İki decorator yazıram:

`CachingUserRepository` — constructor-da real repository alır. `findById()` çağrılanda əvvəl cache yoxlayır, miss olduqda real repository-yə gedir, cavabı cache-ə yazır. `save()` və `delete()` çağrılanda cache-i invalidate edir.

`LoggingUserRepository` — hər method call-ı log edir: method adı, parametrlər, duration, result count.

ServiceProvider-da:
```
new LoggingUserRepository(
    new CachingUserRepository(
        new EloquentUserRepository(),
        $cache
    ),
    $logger
)
```

Service `UserRepositoryInterface` istifadə edir — decorator olduğunu bilmir. Caching əlavə etmək/çıxarmaq üçün yalnız ServiceProvider-da bir sətir dəyişir.

### Kod Nümunəsi

```php
// Component Interface
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findActive(): Collection;
    public function save(User $user): User;
    public function delete(User $user): void;
}

// Concrete Component — əsas implementasiya
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
        return User::where('status', 'active')->get();
    }

    public function save(User $user): User
    {
        $user->save();
        return $user->fresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}

// Base Decorator — boilerplate delegation
abstract class UserRepositoryDecorator implements UserRepositoryInterface
{
    public function __construct(
        protected readonly UserRepositoryInterface $inner
    ) {}

    public function findById(int $id): ?User
    {
        return $this->inner->findById($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->inner->findByEmail($email);
    }

    public function findActive(): Collection
    {
        return $this->inner->findActive();
    }

    public function save(User $user): User
    {
        return $this->inner->save($user);
    }

    public function delete(User $user): void
    {
        $this->inner->delete($user);
    }
}

// Concrete Decorator 1: Caching
class CachingUserRepository extends UserRepositoryDecorator
{
    private const TTL = 3600; // 1 saat

    public function __construct(
        UserRepositoryInterface $inner,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($inner);
    }

    public function findById(int $id): ?User
    {
        return $this->cache->remember(
            "user:{$id}",
            self::TTL,
            fn() => $this->inner->findById($id)
        );
    }

    public function findByEmail(string $email): ?User
    {
        $key = 'user:email:' . md5($email);
        return $this->cache->remember(
            $key,
            self::TTL,
            fn() => $this->inner->findByEmail($email)
        );
    }

    public function save(User $user): User
    {
        $saved = $this->inner->save($user);
        // Cache invalidation — write-through
        $this->cache->forget("user:{$saved->id}");
        $this->cache->forget('user:email:' . md5($saved->email));
        return $saved;
    }

    public function delete(User $user): void
    {
        $this->inner->delete($user);
        $this->cache->forget("user:{$user->id}");
        $this->cache->forget('user:email:' . md5($user->email));
    }
}

// Concrete Decorator 2: Logging
class LoggingUserRepository extends UserRepositoryDecorator
{
    public function __construct(
        UserRepositoryInterface $inner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($inner);
    }

    public function findById(int $id): ?User
    {
        $start = microtime(true);
        $result = $this->inner->findById($id);
        $ms = round((microtime(true) - $start) * 1000, 2);

        $this->logger->debug('UserRepository::findById', [
            'id'      => $id,
            'found'   => $result !== null,
            'duration_ms' => $ms,
        ]);

        return $result;
    }

    public function save(User $user): User
    {
        $result = $this->inner->save($user);

        $this->logger->info('UserRepository::save', [
            'user_id' => $result->id,
            'email'   => $result->email,
        ]);

        return $result;
    }

    // Digər metodlar oxşar pattern ilə...
    public function findByEmail(string $email): ?User
    {
        return $this->inner->findByEmail($email);
    }

    public function findActive(): Collection
    {
        return $this->inner->findActive();
    }

    public function delete(User $user): void
    {
        $this->inner->delete($user);
        $this->logger->info('UserRepository::delete', ['user_id' => $user->id]);
    }
}

// ServiceProvider — stack composition
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, function ($app) {
            return new LoggingUserRepository(
                new CachingUserRepository(
                    new EloquentUserRepository(),
                    $app->make(CacheInterface::class)
                ),
                $app->make(LoggerInterface::class)
            );
        });
    }
}

// Service — decorator-dan xəbərsiz
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users  // Decorator stack görünmür
    ) {}

    public function getUser(int $id): User
    {
        return $this->users->findById($id)
            ?? throw new UserNotFoundException($id);
    }
}
```

```php
// Laravel Middleware — Decorator pattern tətbiqi
// Hər middleware Request-i wrap edir:

class ThrottleRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tooManyAttempts($request)) {
            throw new ThrottleRequestsException();
        }
        $response = $next($request);  // Inner component-ə dəlir
        return $this->addHeaders($response); // Cavabı genişləndirir
    }
}

// Stack: Auth → Throttle → ValidatePostSize → RouteHandler
// Hər middleware digərini wrap edir — Decorator zənciri
```

## Praktik Tapşırıqlar

- `CachingUserRepository` yazın — `findById()`, `save()`, `delete()` cache-dən istifadə etsin
- `RetryDecorator` yazın — müəyyən exception-larda N dəfə retry etsin
- `MetricsDecorator` yazın — hər repository call-ı StatsD/Prometheus-a göndərsin
- Decorator stack-ini `array_reduce` ilə dinamik compose edin — config-dən feature flag-a görə
- Laravel middleware-də custom decorator yazın: Request body-ni decrypt edən middleware

## Əlaqəli Mövzular

- [Repository Pattern](07-repository-pattern.md) — Decorator-ın ən çox tətbiq olduğu yer
- [Proxy Pattern](13-proxy-pattern.md) — Oxşar struktur, fərqli intent
- [Strategy Pattern](05-strategy-pattern.md) — Strategy + Decorator kombinasiyası
- [Chain of Responsibility](14-chain-of-responsibility.md) — Middleware pipeline alternativ yanaşması
- [SOLID Principles](01-solid-principles.md) — OCP: mövcud kodu dəyişmədən genişləndirmək
