# Proxy Pattern (Lead ⭐⭐⭐⭐)

## İcmal

Proxy pattern — başqa bir object-in yerini tutan, ona girişi idarə edən structural pattern-dir. "Provide a surrogate or placeholder for another object to control access to it." Proxy, real object ilə eyni interface-i implement edir — client proxynin real object olmadığını bilmir. Lazy initialization, access control, caching, logging, remote service-lərə şəffaf giriş — bunların hamısı Proxy ilə həll olunur. Laravel-in Eloquent lazy-loading (N+1 problemi bu proxy davranışının nəticəsidir), `Auth::user()` lazy resolve, Doctrine-in entity proxy-ləri — real produksiya nümunələridir. Lead səviyyəsindəki interview-larda "Lazy loading vs eager loading arxitekturasını necə dizayn edərdiniz?" ya da "Access control layer necə qurarsınız?" suallarında çıxır.

## Niyə Vacibdir

Proxy pattern — sistemin mərkəzi kəsişmə nöqtəsidir. Bir servisdə bütün method call-larını log etmək, rate limiting tətbiq etmək, cache əlavə etmək lazımdır — service-i dəyişmədən Proxy class-ı yazılır. Decorator ilə çox oxşardır, lakin intent fərqlidir: Decorator davranış əlavə edir (enrich), Proxy giriş idarə edir (control). Doctrine ORM-in Ghost Object pattern-i, PHP-nin `ProxyManager` library-si, APM tool-larının transparent method instrumentation-ı — hamısı Proxy-dir. Bu mövzunu Lead səviyyəsindən izah edə bilmək üçün: lazy loading-in necə işlədiyini, virtual proxy-nin garbage collection-a təsirini, dynamic proxy generation-ı başa düşmək lazımdır.

## Əsas Anlayışlar

**Proxy növləri:**

**1. Virtual Proxy (Lazy Loading):**
- Ağır (expensive) object-in yaradılmasını ilk istifadəyə kimi gecikdirir
- Eloquent-in related model-ləri: `$order->user` — ilk çağrıda SQL atılır, sonra cache-lənir
- Problem: N+1 query — Proxy-nin yan təsiri

**2. Protection Proxy (Access Control):**
- Real object-ə giriş üçün authorization yoxlayır
- `AuthorizedOrderRepository` — yalnız cari user-in order-larına icazə verir
- Spring Security-nin method-level `@PreAuthorize` — dynamic proxy ilə

**3. Remote Proxy:**
- Local interface arxasında remote service-i gizləndirir
- gRPC client stub, SOAP proxy — network call-ı local method call kimi göstərir
- Transparency: Client local mı, remote mu olduğunu bilmir

**4. Caching Proxy:**
- Real object-ə get call-larını önbellekleşdirir
- Decorator-ın Caching implementasiyası da texniki cəhətdən Caching Proxy-dir
- Fərq intent-dədir: Proxy — access control/laziness. Decorator — enrichment

**5. Logging/Instrumentation Proxy:**
- Hər method call-ı log edir, timing əlavə edir
- APM tool-ları (Datadog, New Relic) bu cür transparent proxy yaradır bytecode instrumentation ilə

**Dynamic Proxy (PHP/Java):**
- Compile-time Proxy class-ı yazmadan, runtime-da otomatik Proxy generate etmək
- PHP: `ProxyManager` library (`ocramius/proxy-manager`) — Doctrine istifadə edir
- Java: `java.lang.reflect.Proxy` — dinamik proxy
- PHP `__call()` magic method — basit dynamic proxy yaratmaq üçün

**Proxy vs Decorator:**
- Struktur: Eyni — ikisi də component-in interface-ini implement edir, component-ə referans saxlayır
- **Intent fərqi:**
  - Proxy: Giriş idarəsi (lazy init, auth, remote, cache)
  - Decorator: Davranış əlavəsi (yeni funksiya genişləndirir)
- Proxy çox vaxt transparent-dır (client bilmir). Decorator açıq stack-dir (client qurur)

**Proxy vs Adapter:**
- Adapter: Interface-i çevirir (uyğunsuzluq problemi)
- Proxy: Eyni interface-i saxlayır, access idarə edir

**Lazy loading N+1 problemi:**
- Virtual Proxy-nin klassik problemi: `foreach($orders as $order) { $order->user->name; }` — hər `$order->user` ayrı SQL
- Həll: Eager loading (`with('user')`), ya da proxy-nin aware olduğu batch loader (DataLoader pattern)

**Null Proxy:**
- Null check-ləri aradan qaldırır: Null object-in Proxy variantı
- `NullCache::get()` — həmişə null qaytarır, `NullCache::put()` — heç nə etmir

**Thread-safe Proxy:**
- Mutable shared object-lərə girişi lock ilə idarə edir
- `SynchronizedProxy::method()` — `synchronized(lock) { real.method(); }`

## Praktik Baxış

**Interview-da yanaşma:**
Proxy-ni növlərinə görə izah edin. Real sistemdə ən çox Virtual Proxy (lazy loading) və Protection Proxy (authorization) sualları gəlir. Eloquent N+1 problemi Proxy-nin yan təsiridir — bu mövzunu dərindən izah etmək Lead səviyyəsini göstərir.

**"Nə vaxt Proxy seçərdiniz?" sualına cavab:**
- Resource-intensive object-in lazy initialization-ı lazım olduqda
- Object-ə girişi centralize etmək, audit etmək lazım olduqda
- Remote service-ni local kimi expose etmək lazım olduqda
- Caching logic-i real object-dən ayırmaq lazım olduqda (bu üçün Decorator da istifadə oluna bilər)

**Anti-pattern-lər:**
- Proxy-dən Proxy yaratmaq — "Proxy hell": stack çox dərin, debug çətin
- Proxy-nin real object-in biznes məntiqini bilməsi — Proxy yalnız access idarə etməlidir
- Virtual Proxy-ni thread-safe etməmək — double-checked locking unutmaq
- Bütün class-lar üçün avtomatik Proxy yaratmaq — performance overhead, complexity artır

**Follow-up suallar:**
- "Eloquent-in lazy loading-i Proxy pattern-dirmi?" → Bəli — `$order->user` Virtual Proxy. İlk access-də query atılır, sonra cached. N+1 buradan gəlir
- "Dynamic Proxy nədir, PHP-də necə yaradılır?" → `ProxyManager` library, ya da `__call()` magic method. Runtime-da real class-ın bütün metodlarını intercept edir
- "Protection Proxy ABAC ilə necə birlikdə işləyir?" → Proxy `isAllowed($user, $resource, $action)` yoxlayır. Policy engine-ə sorğu göndərir, false olsa exception atar
- "Proxy-nin Decorator-dan üstünlüyü nədir?" → Lazy initialization (Decorator bacarmır), access control semantics daha aydın, client proxy-nin real olmadığını bilmir

## Nümunələr

### Tipik Interview Sualı

"You're building a multi-tenant SaaS. Users should only access their own tenant's data. The data layer uses repositories, but you don't want to add tenant filtering logic inside every repository method. How would you transparently enforce this?"

### Güclü Cavab

Bu Protection Proxy-nin ideal use-case-idir.

`TenantScopedRepositoryProxy<T>` — hər repository interface-i üçün istifadə olunur. Constructor-da real repository və current tenant-ı alır.

`findById()` — real repository-dən gətirir, sonra tenant-ı yoxlayır. Uyğun deyilsə `ResourceForbiddenException` atır.

`findAll()` — real repository-yə `tenantId` filter-i avtomatik əlavə edir.

`save()` — record-un `tenant_id`-ni cari tenant-la override edir — başqa tenant-a data yazmaq mümkün olmur.

ServiceProvider-da: Hər repository-nin proxy variantı bind edilir. `OrderRepositoryInterface` → `TenantScopedProxy(EloquentOrderRepository)`.

Bu yanaşma ilə hər repository method-una `where('tenant_id', $tenant)` əlavə etmək lazım deyil. Bir yerdə — Proxy-də centralize edilib.

### Kod Nümunəsi

```php
// ===== VIRTUAL PROXY — Lazy Initialization =====

interface HeavyReportInterface
{
    public function generate(): ReportData;
    public function summary(): string;
    public function export(string $format): string;
}

// Real object — generate etmək çox vaxt alır
class HeavyReport implements HeavyReportInterface
{
    private ReportData $data;

    public function __construct(
        private readonly ReportRepository $repo,
        private readonly DateRange $range,
    ) {
        // Ağır initialization — DB query-lər, aggregation-lar
        $this->data = $this->repo->buildComplexReport($range);
    }

    public function generate(): ReportData { return $this->data; }
    public function summary(): string { return $this->data->summary(); }
    public function export(string $format): string { return $this->data->export($format); }
}

// Virtual Proxy — real object-i lazım olana kimi yaratmır
class LazyReportProxy implements HeavyReportInterface
{
    private ?HeavyReport $real = null;

    public function __construct(
        private readonly ReportRepository $repo,
        private readonly DateRange $range,
    ) {
        // Constructor asan — real object yaradılmır
    }

    private function getReal(): HeavyReport
    {
        if ($this->real === null) {
            $this->real = new HeavyReport($this->repo, $this->range);
        }
        return $this->real;
    }

    public function generate(): ReportData { return $this->getReal()->generate(); }
    public function summary(): string { return $this->getReal()->summary(); }
    public function export(string $format): string { return $this->getReal()->export($format); }
}
```

```php
// ===== PROTECTION PROXY — Multi-Tenant Access Control =====

class TenantScopedOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $inner,
        private readonly TenantContext $tenant,
    ) {}

    public function findById(int $id): ?Order
    {
        $order = $this->inner->findById($id);

        if ($order === null) return null;

        // Tenant yoxlaması — başqa tenant-ın order-ına giriş qadağandır
        if ($order->tenant_id !== $this->tenant->id()) {
            throw new TenantAccessViolationException(
                "Order #{$id} belongs to different tenant"
            );
        }

        return $order;
    }

    public function findAll(OrderCriteria $criteria): Collection
    {
        // Criteria-ya tenant filter avtomatik əlavə edir
        $tenantCriteria = $criteria->withTenantId($this->tenant->id());
        return $this->inner->findAll($tenantCriteria);
    }

    public function save(Order $order): Order
    {
        // Tenant-ı override et — başqa tenant-a yazmağın qarşısını al
        $order->tenant_id = $this->tenant->id();
        return $this->inner->save($order);
    }

    public function delete(Order $order): void
    {
        // Əvvəlcə ownership yoxla
        if ($order->tenant_id !== $this->tenant->id()) {
            throw new TenantAccessViolationException("Cannot delete order from another tenant");
        }
        $this->inner->delete($order);
    }
}

// ServiceProvider — Proxy stack
$this->app->bind(OrderRepositoryInterface::class, function ($app) {
    return new TenantScopedOrderRepository(
        new EloquentOrderRepository(),
        $app->make(TenantContext::class)
    );
});
```

```php
// ===== DYNAMIC PROXY — __call() ilə =====

class LoggingProxy
{
    public function __construct(
        private readonly object $target,
        private readonly LoggerInterface $logger,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        if (!method_exists($this->target, $method)) {
            throw new \BadMethodCallException("Method {$method} not found");
        }

        $start = microtime(true);

        try {
            $result = $this->target->$method(...$args);
            $ms = round((microtime(true) - $start) * 1000, 2);

            $this->logger->debug('Method call', [
                'class'       => get_class($this->target),
                'method'      => $method,
                'duration_ms' => $ms,
                'success'     => true,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Method call failed', [
                'class'  => get_class($this->target),
                'method' => $method,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

// İstifadə — hər hansı object-i wrap edə bilir
$proxy = new LoggingProxy(
    new EloquentOrderRepository(),
    $logger
);
// $proxy->findById(1) → log → real findById → log result
```

```php
// ===== CACHING PROXY — Rate-limited API client =====

class CachedExchangeRateProxy implements ExchangeRateInterface
{
    public function __construct(
        private readonly ExchangeRateInterface $real,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function getRate(string $from, string $to): float
    {
        $key = "exchange:{$from}:{$to}";

        return $this->cache->remember($key, $this->ttlSeconds, function () use ($from, $to) {
            // Real API çağrısı yalnız cache miss olduqda
            return $this->real->getRate($from, $to);
        });
    }

    public function getRates(string $base, array $targets): array
    {
        $cached = [];
        $missing = [];

        // Batch cache lookup
        foreach ($targets as $target) {
            $key = "exchange:{$base}:{$target}";
            $cached[$target] = $this->cache->get($key);
            if ($cached[$target] === null) {
                $missing[] = $target;
            }
        }

        // Yalnız cache miss olanları real API-dən al
        if (!empty($missing)) {
            $fresh = $this->real->getRates($base, $missing);
            foreach ($fresh as $target => $rate) {
                $this->cache->put("exchange:{$base}:{$target}", $rate, $this->ttlSeconds);
                $cached[$target] = $rate;
            }
        }

        return $cached;
    }
}
```

## Praktik Tapşırıqlar

- `AuthorizationProxy` yazın: Hər CRUD metodunda `$policy->can($user, $action, $resource)` yoxlasın
- Virtual Proxy tətbiq edin: `LazyPdfReportProxy` — PDF yalnız `download()` çağrıldıqda generate olsun
- `RateLimitedApiProxy` yazın: Dəqiqədə N sorğudan çox olduqda `TooManyRequestsException` atsın
- Eloquent N+1 problem simulate edin: 100 order loop-layın, `$order->user->name` çağırın — query count izləyin. Sonra `with('user')` ilə düzəldin
- `ProxyManager` library-sini incələyin: Ghost Object, Virtual Proxy, Access Interceptor nümunələri

## Əlaqəli Mövzular

- [Decorator Pattern](08-decorator-pattern.md) — Oxşar struktur, fərqli intent. Decorator enriches, Proxy controls
- [Repository Pattern](07-repository-pattern.md) — Tenant-scoped Proxy repository üzərindədir
- [Dependency Injection](11-dependency-injection.md) — Proxy DI container ilə inject edilir
- [Chain of Responsibility](14-chain-of-responsibility.md) — Middleware pipeline — zəncirlənmiş proxy-lər kimi
- [Adapter and Facade](09-adapter-facade.md) — Remote Proxy, Adapter ilə yaxın: hər ikisi şəffaf access
