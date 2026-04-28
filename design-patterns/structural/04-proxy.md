# Proxy (Senior ⭐⭐⭐)

## İcmal
Proxy pattern real object-in yerinə keçən surrogate (vəkil) object-dir. Client real object ilə eyni interface vasitəsilə işləyir, lakin Proxy araya girərək əlavə məsuliyyət götürür: cache, access control, lazy initialization, logging. "Nə etdiyini" dəyişmir, "necə etdiyini" dəyişir.

## Niyə Vacibdir
Laravel-in Eloquent lazy loading (virtual proxy), Gate/Policy (protection proxy), Redis cache wrappers (caching proxy) — hamısı Proxy pattern-ə nümunədir. Performance optimization üçün caching, security üçün access control, testability üçün fake proxy yazmağı bilmək senior PHP developer-in əsas bacarıqlarından biridir.

## Əsas Anlayışlar
- **Virtual Proxy**: real object-i lazım olanadək yaratmır (lazy initialization) — ağır resursları gecikdirir
- **Protection Proxy**: real object-ə müraciəti yoxlayır, icazə yoxdursa bloklayır
- **Caching Proxy**: real object-in cavabını cache-ləyir, eyni sorğunu ikinci dəfə cache-dən verir
- **Remote Proxy**: real object başqa server/process-dədir; proxy şəbəkə kommunikasiyasını idarə edir
- **Logging/Audit Proxy**: real object-ə edilən hər çağırışı log edir (audit trail, debugging)
- **Subject interface**: həm Proxy, həm Real Subject-in implement etdiyi ortaq contract — bu olmasa client Proxy-ni tanımaz

## Praktik Baxış
- **Real istifadə**: repository result-larını Redis-də cache-ləmək, permission check-ləri centrallaşdırmaq, ağır third-party API client-lərini lazy init etmək, test double-lar (mock/spy)
- **Trade-off-lar**: əlavə indirection — stack trace mürəkkəbləşir; performance overhead (az da olsa); Proxy-nin özü bug mənbəyi ola bilər; interface-i sinxron saxlamaq əlavə iş tələb edir
- **İstifadə etməmək**: çox sadə, bir yerdə istifadə olunan object-lər üçün; cache invalidation mürəkkəbdirsə (stale data riski yüksəkdir); thread-safe olması lazım olan, amma Proxy-nin race condition yaratdığı hallarda
- **Common mistakes**: Proxy-yə çox məsuliyyət yükləmək (cache + auth + log eyni Proxy-də) — Single Responsibility pozulur; cache key-lərinin collision-u; Proxy-nin interface-i genişlətməsi (Real Subject-in interface-indən çıxması)

### Anti-Pattern Nə Zaman Olur?
Proxy **latency əlavə etmədən heç bir real dəyər verməyəndə** anti-pattern olur:

- **"Pass-through" Proxy**: Proxy-nin əlavə heç bir iş görmədən sadəcə real object-ə yönləndirir. Bu boş indirection-dır — kod mürəkkəbləşdi, faydası yoxdur.
- **Stale cache**: Caching Proxy düzgün invalidation olmadan işlədilir. User yenilənir, amma Proxy köhnə cache qaytarır. Bu vəziyyətdə Proxy performance vermir, data integrity pozur.
- **Proxy zənciri explosion**: `ProtectedProxy(AuditProxy(CachingProxy(LoggingProxy(real))))` — 4 qatlı Proxy. Hər çağırış 4 qatdan keçir. Stack trace oxunmaz olur. Bunları birləşdirəcəksinizsə, Decorator pattern ilə daha aydın modelləyin.
- **Hidden business logic**: Protection Proxy içinə "istifadəçi premium-dursa bu metodu göstər" kimi biznes qaydaları qoymaq. Proxy access kontrol üçün, iş qaydaları Policy/Service üçündür.
- **Interface drift**: Zamanla Proxy-yə Real Subject-də olmayan metodlar əlavə olunur. Client birbaşa Proxy-yə asılı olur. Bu, Liskov Substitution Principle pozuntusudur.

## Nümunələr

### Ümumi Nümunə
Bank-ın ATM sistemi düşünün. ATM (Proxy) əsl bank serveri (Real Subject) ilə eyni interface-i paylaşır. Lakin ATM kart yoxlama, limit kontrolu, log tutma əməliyyatlarını edir — siz bank serverini birbaşa əlləmirsiz. Server dəyişsə, ATM interface-i eyni qalır. Bu Proxy-nin əsas dəyəridir: real object-i müştəridən ayırmaq.

### PHP/Laravel Nümunəsi

**Caching Proxy — ən geniş istifadə:**

```php
<?php

// Subject interface — həm Real, həm Proxy implement edir
// Bu interface olmasa client Proxy-ni tanımaz
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function findAll(): Collection;
    public function save(User $user): User;
}

// Real Subject — DB ilə birbaşa işləyir
class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User     { return User::find($id); }
    public function findAll(): Collection   { return User::all(); }
    public function save(User $user): User
    {
        $user->save();
        return $user->fresh();
    }
}

// Caching Proxy — eyni interface, əlavə məsuliyyət: cache
class CachingUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $real,    // real subject — inject olunur
        private readonly CacheRepository         $cache,
        private readonly int                     $ttl = 3600,
    ) {}

    public function find(int $id): ?User
    {
        // Cache varsa: real object-ə getmir — DB query yoxdur
        return $this->cache->remember(
            key:      "users:{$id}",
            ttl:      $this->ttl,
            callback: fn() => $this->real->find($id)
        );
    }

    public function findAll(): Collection
    {
        return $this->cache->remember(
            key:      'users:all',
            ttl:      $this->ttl,
            callback: fn() => $this->real->findAll()
        );
    }

    public function save(User $user): User
    {
        $saved = $this->real->save($user);

        // Write-through: save edildikdə cache invalidate olunur
        // Stale data riski önlənir
        $this->cache->forget("users:{$saved->id}");
        $this->cache->forget('users:all');

        return $saved;
    }
}

// ServiceProvider — client heç bir şey bilmir: Eloquent-mi, Cache-mi?
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, function (Application $app) {
            $real = new EloquentUserRepository();
            // Eloquent-i Caching Proxy ilə sarıyırıq — client dəyişmir
            return new CachingUserRepository($real, $app->make(CacheRepository::class));
        });
    }
}
```

**Virtual Proxy — lazy initialization:**

```php
interface PdfGenerator
{
    public function generate(array $data): string;
    public function merge(array $pdfs): string;
}

// Real Subject — yaradılması ağır (wkhtmltopdf process başladılır)
class WkhtmltopdfGenerator implements PdfGenerator
{
    private $process;

    public function __construct()
    {
        // Bu işi hər request-də etmək israfedicidir
        $this->process = new Process(['wkhtmltopdf', '--version']);
        $this->process->run();
    }

    public function generate(array $data): string { /* ... */ return ''; }
    public function merge(array $pdfs): string    { /* ... */ return ''; }
}

// Virtual Proxy — yalnız ilk çağırışda real object yaradılır
class LazyPdfGenerator implements PdfGenerator
{
    private ?PdfGenerator $real = null; // null = henüz yaradılmayıb

    private function getInstance(): PdfGenerator
    {
        if ($this->real === null) {
            // İlk real PDF tələbindən əvvəl heç bir proses başlamır
            $this->real = new WkhtmltopdfGenerator();
        }
        return $this->real;
    }

    public function generate(array $data): string
    {
        return $this->getInstance()->generate($data);
    }

    public function merge(array $pdfs): string
    {
        return $this->getInstance()->merge($pdfs);
    }
}

// PDF lazım olmayan request-lərdə (məs: API endpoint-lər) process başlamır
app()->singleton(PdfGenerator::class, LazyPdfGenerator::class);
```

**Protection Proxy — access control:**

```php
interface DocumentRepository
{
    public function find(int $id): ?Document;
    public function update(int $id, array $data): Document;
    public function delete(int $id): void;
}

class EloquentDocumentRepository implements DocumentRepository
{
    public function find(int $id): ?Document   { return Document::find($id); }
    public function update(int $id, array $data): Document
    {
        $doc = Document::findOrFail($id);
        $doc->update($data);
        return $doc->fresh();
    }
    public function delete(int $id): void      { Document::destroy($id); }
}

// Protection Proxy — permission yoxlayır, real object-ə yalnız icazə olarsa gedir
class ProtectedDocumentRepository implements DocumentRepository
{
    public function __construct(
        private readonly DocumentRepository $real,
        private readonly Gate               $gate,
        private readonly User               $currentUser,
    ) {}

    public function find(int $id): ?Document
    {
        $document = $this->real->find($id);
        // Tapıldı amma icazə yoxdur — boş qaytar, 404 kimi davranır
        if ($document && $this->gate->denies('view', $document)) {
            throw new AccessDeniedException("Cannot view document #{$id}");
        }
        return $document;
    }

    public function update(int $id, array $data): Document
    {
        $document = $this->real->find($id); // əvvəl tap
        if ($this->gate->denies('update', $document)) {
            throw new AccessDeniedException("Cannot update document #{$id}");
        }
        return $this->real->update($id, $data); // icazə var — real-a ötür
    }

    public function delete(int $id): void
    {
        $document = $this->real->find($id);
        if ($this->gate->denies('delete', $document)) {
            throw new AccessDeniedException("Cannot delete document #{$id}");
        }
        $this->real->delete($id);
    }
}
```

**Logging/Audit Proxy — audit trail:**

```php
class AuditingUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $real,
        private readonly AuditLogger             $logger,
        private readonly ?User                   $actor = null,
    ) {}

    public function save(User $user): User
    {
        $isNew    = !$user->exists;
        $original = $user->getOriginal(); // dəyişiklikdən əvvəlki dəyərlər

        $saved = $this->real->save($user); // real işi et

        // Nə dəyişdi, kim etdi, nə vaxt — audit log
        $this->logger->log([
            'action'    => $isNew ? 'user.created' : 'user.updated',
            'actor_id'  => $this->actor?->id,
            'target_id' => $saved->id,
            'changes'   => $user->getChanges(),    // yeni dəyərlər
            'original'  => $original,              // köhnə dəyərlər
            'ip'        => request()->ip(),
            'at'        => now()->toIso8601String(),
        ]);

        return $saved;
    }

    // Read metodları audit log yazmır — yalnız dəyişiklik log-lanır
    public function find(int $id): ?User    { return $this->real->find($id); }
    public function findAll(): Collection   { return $this->real->findAll(); }
}
```

**Proxy zənciri — layered composition:**

```php
// ServiceProvider-da bir neçə Proxy-ni zəncirlə
$this->app->bind(UserRepositoryInterface::class, function (Application $app) {
    $eloquent  = new EloquentUserRepository();

    // Zəncir: client → protected → audited → cached → eloquent
    // Hər qat öz məsuliyyətini yerinə yetirir
    $cached    = new CachingUserRepository($eloquent, $app->make(CacheRepository::class));
    $audited   = new AuditingUserRepository($cached, $app->make(AuditLogger::class), auth()->user());
    $protected = new ProtectedDocumentRepository($audited, $app->make(Gate::class), auth()->user());

    return $protected;
});
```

**Laravel-dəki Proxy nümunələri:**

```php
// 1. Eloquent lazy loading = Virtual Proxy
$order = Order::find(1);
// $order->user əsasında User yüklənməyib — Virtual Proxy
$order->user; // User query yalnız bu an çalışır (lazy load)

// 2. Laravel Facade = Static Proxy
Cache::get('key');
// Facade → app('cache') → CacheManager → RedisStore
// Client birbaşa implementation bilmir — Proxy kimi davranır

// 3. Laravel Middleware = Protection Proxy analogu
class EnsureUserIsSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()->hasActiveSubscription()) {
            return response()->json(['error' => 'Subscription required'], 403);
        }
        return $next($request); // real controller-a ötür
    }
}
```

**Proxy vs Decorator fərqi:**

```php
// PROXY: client şəffaflıqla real object-ə çatmaq istəyir
// Proxy onu qoruyur, gecikdirir, ya cache-ləyir — amma məqsəd "real işi etmək"dir
class CachingRepo implements RepoInterface {
    public function __construct(private RepoInterface $real) {}
    public function find(int $id): ?Model {
        return cache()->remember("item:{$id}", 3600, fn() => $this->real->find($id));
    }
}

// DECORATOR: əsas davranışın üstünə yeni davranış əlavə etmək
// Məqsəd "əlavə etmək" — access kontrol deyil, genişlənmə
class LoggingRepo implements RepoInterface {
    public function __construct(private RepoInterface $wrapped) {}
    public function find(int $id): ?Model {
        Log::info("Finding #{$id}");
        return $this->wrapped->find($id); // eyni interface, amma log əlavə edildi
    }
}
// Struktur eynidir — niyyət fərqlidir:
// Proxy = surrogat/nəzarət, Decorator = genişlənmə/wrap
```

## Praktik Tapşırıqlar
1. `ProductRepositoryInterface` üçün `CachingProductRepository` yazın: `findBySku()` → Redis 1 saat cache; `save()` → cache invalidate et; PHPUnit test yaz — mock cache ilə real DB-yə getmədən.
2. `DocumentRepository`-ı `ProtectedDocumentRepository` ilə wrap edin; Laravel `Gate` əvəzinə test üçün sadə role array ilə işləsin: `['admin', 'editor']` — PHPUnit test yaz, icazəsiz çağırışda exception atılsın.
3. Caching + Audit Proxy-ni birləşdirin: `ProductRepository`-ni `audited → cached → eloquent` zənciri kimi qurun; save əməliyyatında həm audit log yazılsın, həm cache invalidate olsun.

## Əlaqəli Mövzular
- [03-decorator.md](03-decorator.md) — Proxy ilə Decorator fərqi: eyni struktur, fərqli niyyət
- [01-facade.md](01-facade.md) — Laravel Facade statik proxy-dir
- [02-adapter.md](02-adapter.md) — Adapter interface dəyişdirir; Proxy eyni interface saxlayır
- [../laravel/01-repository-pattern.md](../laravel/01-repository-pattern.md) — Proxy-nin wrap etdiyi real subject çox vaxt Repository-dir
- [../laravel/06-di-vs-service-locator.md](../laravel/06-di-vs-service-locator.md) — DI container proxy-ləri avtomatik wiring edir
- [../creational/01-singleton.md](../creational/01-singleton.md) — Caching Proxy tez-tez singleton-larla kombinasiya olunur
- [../behavioral/07-state.md](../behavioral/07-state.md) — Virtual Proxy lazy init ilə State pattern-i birləşdirmək mümkündür
- [../behavioral/11-null-object.md](../behavioral/11-null-object.md) — Null Object Protection Proxy-yə alternativ: exception atmaq əvəzinə boş davranış
- [../integration/01-cqrs.md](../integration/01-cqrs.md) — Read model Proxy kimi cache qatı ilə tez-tez birgə istifadə olunur
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Proxy Single Responsibility-i qorumağa kömək edir
- [../architecture/05-hexagonal-architecture.md](../architecture/05-hexagonal-architecture.md) — Hexagonal arxitekturada port implementation-ları Proxy kimi qurulaıq bilər
