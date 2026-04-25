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
- **Logging Proxy**: real object-ə edilən hər çağırışı log edir (audit, debugging)
- **Subject interface**: həm Proxy, həm Real Subject-in implement etdiyi ortaq contract

## Praktik Baxış
- **Real istifadə**: repository result-larını Redis-də cache-ləmək, permission check-ləri centrallaşdırmaq, ağır third-party API client-lərini lazy init etmək, test double-lar (mock/spy)
- **Trade-off-lar**: əlavə indirection — stack trace mürəkkəbləşir; performance overhead (az da olsa); Proxy-nin özü bug mənbəyi ola bilər; interface-i sinxron saxlamaq əlavə iş tələb edir
- **İstifadə etməmək**: çox sadə, bir yerdə istifadə olunan object-lər üçün; cache invalidation mürəkkəbdirsə (stale data riski yüksəkdir); thread-safe olması lazım olan, amma Proxy-nin race condition yaratdığı hallarda
- **Common mistakes**: Proxy-yə çox məsuliyyət yükləmək (cache + auth + log eyni Proxy-də) — Single Responsibility pozulur; cache key-lərinin collision-u; Proxy-nin interface-i genişlətməsi (Real Subject-in interface-indən çıxması)

## Nümunələr

### Ümumi Nümunə
Bank-ın ATM sistemi düşünün. ATM (Proxy) əsl bank serveri (Real Subject) ilə eyni interface-i paylaşır. Lakin ATM kart yoxlama, limit kontrolu, log tutma əməliyyatlarını edir — siz bank serverini birbaşa əlləmirsiz. Server dəyişsə, ATM interface-i eyni qalır.

### PHP/Laravel Nümunəsi

**Caching Proxy — ən geniş istifadə:**

```php
<?php

// Subject interface — həm Real, həm Proxy implement edir
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function findAll(): Collection;
    public function save(User $user): User;
}

// Real Subject
class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findAll(): Collection
    {
        return User::all();
    }

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
        private readonly UserRepositoryInterface $real,   // wrapped repository
        private readonly CacheRepository         $cache,
        private readonly int                     $ttl = 3600,
    ) {}

    public function find(int $id): ?User
    {
        return $this->cache->remember(
            key:     "users:{$id}",
            ttl:     $this->ttl,
            callback: fn() => $this->real->find($id)
        );
    }

    public function findAll(): Collection
    {
        return $this->cache->remember(
            key:     'users:all',
            ttl:     $this->ttl,
            callback: fn() => $this->real->findAll()
        );
    }

    public function save(User $user): User
    {
        $saved = $this->real->save($user);

        // Cache invalidate et
        $this->cache->forget("users:{$saved->id}");
        $this->cache->forget('users:all');

        return $saved;
    }
}

// ServiceProvider — client heç bir şey bilmir
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, function (Application $app) {
            $real = new EloquentUserRepository();
            return new CachingUserRepository($real, $app->make(CacheRepository::class));
        });
    }
}
```

**Virtual Proxy — lazy initialization:**

```php
// Ağır PDF generator — hər request-də lazım deyil
interface PdfGenerator
{
    public function generate(array $data): string;
    public function merge(array $pdfs): string;
}

// Real Subject — yaradılması ağır (wkhtmltopdf, headless Chrome)
class WkhtmltopdfGenerator implements PdfGenerator
{
    private $process;

    public function __construct()
    {
        // Ağır initialization — process başladılır
        $this->process = new Process(['wkhtmltopdf', '--version']);
        $this->process->run();
    }

    public function generate(array $data): string { /* ... */ }
    public function merge(array $pdfs): string    { /* ... */ }
}

// Virtual Proxy — yalnız ilk çağırışda real object yaradılır
class LazyPdfGenerator implements PdfGenerator
{
    private ?PdfGenerator $real = null;

    private function getInstance(): PdfGenerator
    {
        if ($this->real === null) {
            $this->real = new WkhtmltopdfGenerator(); // ilk çağırışda yaradılır
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

// PDF lazım olmayan request-lərdə process başlamır
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

// Protection Proxy — permission yoxlayır
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
        if ($document && $this->gate->denies('view', $document)) {
            throw new AccessDeniedException("Cannot view document #{$id}");
        }
        return $document;
    }

    public function update(int $id, array $data): Document
    {
        $document = $this->real->find($id);
        if ($this->gate->denies('update', $document)) {
            throw new AccessDeniedException("Cannot update document #{$id}");
        }
        return $this->real->update($id, $data);
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

**Logging Proxy — audit trail:**

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
        $original = $user->getOriginal();

        $saved = $this->real->save($user);

        $this->logger->log([
            'action'    => $isNew ? 'user.created' : 'user.updated',
            'actor_id'  => $this->actor?->id,
            'target_id' => $saved->id,
            'changes'   => $user->getChanges(),
            'original'  => $original,
            'ip'        => request()->ip(),
            'at'        => now()->toIso8601String(),
        ]);

        return $saved;
    }

    public function find(int $id): ?User    { return $this->real->find($id); }
    public function findAll(): Collection   { return $this->real->findAll(); }
}
```

**Proxy zənciri — bir neçə Proxy-nin birləşdirilməsi:**

```php
// ServiceProvider-da layered proxy
$this->app->bind(UserRepositoryInterface::class, function (Application $app) {
    $eloquent  = new EloquentUserRepository();
    $cached    = new CachingUserRepository($eloquent, $app->make(Cache::class));
    $audited   = new AuditingUserRepository($cached, $app->make(AuditLogger::class), auth()->user());
    $protected = new ProtectedDocumentRepository($audited, $app->make(Gate::class), auth()->user());

    return $protected;
    // Request axışı: client → protected → audited → cached → eloquent
});
```

**Laravel-dəki Proxy nümunələri:**

```php
// 1. Eloquent lazy loading = Virtual Proxy
$order = Order::find(1);
$order->user; // User query yalnız bu an çalışır (lazy)

// 2. Laravel Facade = Static Proxy
Cache::get('key');
// Facade → app('cache') → CacheManager → RedisStore
// Client birbaşa implementation bilmir

// 3. Laravel Middleware = bir növ Protection Proxy
// Request middleware-dən keçir, sonra controller-a çatır
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
// PROXY: interface-i control edir, real object-ə GIRIŞ qoruyr/gecikdirir
// Məqsəd: access control, caching, lazy init
// Client FƏRQINI BILMIR (transparent)
class CachingRepo implements RepoInterface {
    public function __construct(private RepoInterface $real) {}
    // real object-i bilir, amma client bilmir
}

// DECORATOR: behaviour ƏLAVƏ edir, real object həmişə mövcuddur
// Məqsəd: run-time feature əlavə etmək
// Eyni interface-i implement edir, amma MƏQSƏD fərqlidir
class LoggingRepo implements RepoInterface {
    public function __construct(private RepoInterface $wrapped) {}
    // wrapped object-in üstünə yeni behaviour əlavə edir
}
// Struktur eynidir, niyyət fərqlidir:
// Proxy = surrogat/vəkil, Decorator = wrapper/genişlətmə
```

## Praktik Tapşırıqlar
1. `ProductRepositoryInterface` üçün `CachingProductRepository` yazın: `findBySku()` → Redis 1 saat cache; `save()` → cache invalidate et; test edin
2. `DocumentRepository`-ı `Protection Proxy` ilə wrap edin; `Gate` əvəzinə sadə role array ilə test edin
3. Caching + Logging Proxy-ni birləşdirin — `ProductRepository`-ni cache→log→eloquent zənciri kimi qurun

## Əlaqəli Mövzular
- [06-decorator.md](06-decorator.md) — Proxy ilə Decorator fərqi: eyni struktur, fərqli niyyət
- [14-repository-pattern.md](14-repository-pattern.md) — Proxy-nin wrap etdiyi real subject
- [07-adapter.md](07-adapter.md) — Adapter interface dəyişdirir; Proxy eyni interface saxlayır
- [08-facade.md](08-facade.md) — Laravel Facade static proxy-dir
