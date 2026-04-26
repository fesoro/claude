# Multi-Tenancy Architecture (Senior)

## İcmal

**Multi-tenancy** — bir proqram tətbiqi bir neçə müştərini (tenant) bir infrastruktur üzərində, hər müştərinin dataları izolasiyalı şəkildə xidmət etmə arxitekturasıdır. SaaS (Software as a Service) tətbiqlərində əsas model budur.

**Tenant** — müştəri, company, organization, workspace ola bilər. Məs: Slack-da hər şirkət bir tenantdir; GitHub-da hər organization.

**Məqsədlər:**
- **Cost efficiency** — bir infrastructure bir neçə müştəriyə xidmət edir
- **Operational simplicity** — bir deployment, bir codebase
- **Data isolation** — bir müştərinin datası başqasına görünməməlidir
- **Customization** — hər tenant öz tənzimləmələrini ala bilər


## Niyə Vacibdir

SaaS məhsulları bir infrastruktur üzərində onlarla müştəriyə xidmət edir. Tenant isolation — data leakage qarşısı, performance noisy neighbor, schema strategiyası — SaaS arxitekturasının əsasıdır. Laravel-də Sanctum, row-level security, separate DB — trade-off-ları bilmək lazımdır.

## Əsas Anlayışlar

### 3 Əsas Pattern

### 1. Shared Database / Shared Schema (Pool Model)

Bütün tenantlər eyni cədvəllərdə, `tenant_id` sütunu ilə ayrılır.

```
users table:
| id | tenant_id | email            | name  |
|----|-----------|------------------|-------|
| 1  | 1         | a@tenant1.com    | Alice |
| 2  | 1         | b@tenant1.com    | Bob   |
| 3  | 2         | c@tenant2.com    | Carol |
```

**Üstünlüklər:**
- Ən ucuz (infrastructure, maintenance)
- Onboarding sürətli
- Ölçülə bilən — minlərlə tenant

**Dezavantajlar:**
- Zəif izolasiya — kod səhvi data leak-a gətirə bilər
- "Noisy neighbor" — bir tenant digərlərinin performansına təsir edir
- Compliance (GDPR, HIPAA) mürəkkəb
- Backup/restore per-tenant çətin

### 2. Shared Database / Separate Schema (Bridge Model)

Bir DB, amma hər tenant öz schema-sında.

```
Database: app_db
├── tenant_1_schema
│   ├── users
│   └── orders
├── tenant_2_schema
│   ├── users
│   └── orders
```

**Üstünlüklər:**
- Orta səviyyəli izolasiya
- Per-tenant backup mümkündür
- Schema customization

**Dezavantajlar:**
- Migration hər schema üçün çalışdırılmalıdır
- Connection pool mürəkkəb
- 10k+ tenant-dan sonra problemli

### 3. Separate Database per Tenant (Silo Model)

Hər tenantin öz DB-si var.

```
├── Database: tenant_1_db
├── Database: tenant_2_db
└── Database: tenant_3_db
```

**Üstünlüklər:**
- Ən güclü izolasiya
- Enterprise compliance üçün ideal
- Per-tenant scaling, backup, restore
- Data residency (GDPR EU region)

**Dezavantajlar:**
- Ən bahalı
- Connection sayı artır
- DevOps mürəkkəbliyi

### Tenant Identification Strategiyaları

1. **Subdomain** — `tenant1.app.com`, `tenant2.app.com`
2. **Path prefix** — `app.com/tenant1`, `app.com/tenant2`
3. **Domain** — `tenant1.com` custom domain
4. **Header** — `X-Tenant-ID: tenant1`
5. **JWT claim** — tokendə `tenant_id`

## Arxitektura

```
                    ┌─────────────────┐
                    │   DNS / CDN     │
                    │  *.app.com      │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  Load Balancer  │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  Tenant Router  │
                    │ (middleware)    │
                    └────────┬────────┘
                             │
            ┌────────────────┼────────────────┐
            │                │                │
       ┌────▼────┐     ┌─────▼────┐     ┌─────▼────┐
       │ App     │     │ App      │     │ App      │
       │ Server  │     │ Server   │     │ Server   │
       └────┬────┘     └─────┬────┘     └─────┬────┘
            │                │                │
            └────────────────┼────────────────┘
                             │
                    ┌────────▼────────┐
                    │ Tenant Registry │
                    │ (who is who)    │
                    └────────┬────────┘
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
    ┌─────▼─────┐      ┌─────▼─────┐      ┌─────▼─────┐
    │ Tenant 1  │      │ Tenant 2  │      │ Shared    │
    │ Database  │      │ Database  │      │ DB (pool) │
    └───────────┘      └───────────┘      └───────────┘
```

## Nümunələr

### Yanaşma 1: Manual Implementation (Shared Schema)

```php
// app/Models/Traits/BelongsToTenant.php
namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Global scope — bütün query-lərə tenant_id filter əlavə edir
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = app('currentTenant')?->id) {
                $builder->where('tenant_id', $tenantId);
            }
        });

        // Creating zamanı avtomatik tenant_id təyin et
        static::creating(function ($model) {
            if (!$model->tenant_id && app()->bound('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }
}

// app/Models/Order.php
class Order extends Model
{
    use BelongsToTenant;
    // ...
}

// app/Http/Middleware/IdentifyTenant.php
class IdentifyTenant
{
    public function handle($request, Closure $next)
    {
        $subdomain = explode('.', $request->getHost())[0];
        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

        app()->instance('currentTenant', $tenant);

        // Logging, caching key-lərinə tenant əlavə et
        config(['cache.prefix' => "tenant:{$tenant->id}"]);

        return $next($request);
    }
}

// routes/web.php
Route::middleware(['identify.tenant'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});
```

### Yanaşma 2: stancl/tenancy (Separate Database)

```bash
composer require stancl/tenancy
php artisan tenancy:install
```

```php
// config/tenancy.php
return [
    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'database' => [
        'prefix' => 'tenant_',
        'suffix' => '',
        'template_connection' => 'mysql',
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],
];

// app/Models/Tenant.php
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'plan', 'owner_email'];
    }
}

// routes/tenant.php
Route::middleware([
    'web',
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'Tenant: ' . tenant('name');
    });
    Route::resource('orders', OrderController::class);
});

// Tenant yarat
$tenant = Tenant::create([
    'id' => 'acme',
    'name' => 'ACME Corp',
]);
$tenant->domains()->create(['domain' => 'acme.app.com']);
// Avtomatik yeni DB yaradılır: tenant_acme
// Avtomatik migration çalışdırılır
```

### Yanaşma 3: spatie/laravel-multitenancy

```bash
composer require spatie/laravel-multitenancy
```

```php
// config/multitenancy.php
return [
    'tenant_finder' => Spatie\Multitenancy\TenantFinder\DomainTenantFinder::class,
    'switch_tenant_tasks' => [
        Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class,
        Spatie\Multitenancy\Tasks\PrefixCacheTask::class,
    ],
    'tenant_database_connection_name' => 'tenant',
    'landlord_database_connection_name' => 'landlord',
];

// app/Models/Tenant.php
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    // domain, database sütunları
}

// Middleware avtomatik tenant-ı təyin edir
// Queue-larda da avtomatik tenant context saxlanır

// Tenant modelləri üçün:
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Order extends Model
{
    use UsesTenantConnection; // tenant DB-dən oxu
}

class Plan extends Model
{
    use UsesLandlordConnection; // central DB-dən oxu
}
```

### Tenant-Aware Queue Jobs

```php
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

class SendInvoiceEmail implements ShouldQueue, TenantAware
{
    // Job execute zamanı tenant avtomatik switch olunur
}

class SystemWideReport implements ShouldQueue, NotTenantAware
{
    // Tenant context olmadan işləyir
}
```

### Cache və File Storage İzolasiyası

```php
// Cache tenant-aware
Cache::tags(['tenant:' . tenant('id')])->put('stats', $data, 3600);

// Filesystem per-tenant disk
// config/filesystems.php
'disks' => [
    'tenant' => [
        'driver' => 'local',
        'root' => storage_path('tenants/' . tenant('id')),
    ],
],
```

## Real-World Nümunələr

- **Shopify** — 1.7M+ merchant, shared infrastructure, pod-based sharding
- **Slack** — hər workspace bir tenant, separate database shards
- **Salesforce** — multi-tenant pioneer, "Org" konsepti, shared schema + metadata
- **GitHub** — organization = tenant, shared infrastructure
- **Notion** — workspace-based multi-tenancy
- **Atlassian (Jira/Confluence)** — Cloud: shared DB; Premium: dedicated instance
- **AWS** — Cognito User Pools (multi-tenant auth)
- **Microsoft 365** — hər tenant öz Azure AD-sində izolasiyalı

## Praktik Tapşırıqlar

**1. Shared schema vs separate DB — hansını seçərsən?**
- **Shared schema**: çox sayda kiçik müştəri (SaaS), maliyyət vacib, sürətli onboarding
- **Separate DB**: enterprise, compliance (HIPAA), böyük data, performance izolasiya
- **Hibrid**: kiçik müştərilər shared, enterprise ayrı DB-də

**2. Noisy neighbor problemini necə həll edirsən?**
- Rate limiting per tenant
- Connection pooling limits
- Per-tenant quotas (CPU, storage)
- Shard tenants across DB servers
- Dedicated resource pools for large tenants

**3. Migration-ı multi-tenant sistemdə necə çalışdırırsan?**
- Shared schema: bir dəfə, bütün tenantlərə təsir edir
- Separate schema/DB: hər tenant üçün ayrı çalışdır (`stancl/tenancy` `tenants:migrate` command)
- Queue-da paralel çalışdır, error handling vacibdir
- Blue-green migration — backward compatible dəyişikliklər

**4. Tenant-specific customization necə təmin edilir?**
- Feature flags per tenant
- Settings table (key-value)
- Themes, logo, domain
- Custom fields (JSON column)
- Plugin/extension sistemi

**5. Data isolation necə zəmanətlənir?**
- Global scope bütün query-lərə tenant_id əlavə edir
- Row-Level Security (PostgreSQL RLS)
- Testing — penetration testing, data leakage auditing
- Code review diqqətli
- Middleware tenant context təyin edir

**6. Central (landlord) DB nə üçündür?**
Tenants list, plans, billing, global settings, admin users. Per-tenant DB-də tenant özü haqqında metadata saxlanmaz — sirkulyar asılılıq olar.

**7. Bir tenant silinəndə ne olur?**
- Soft delete + 30 gün grace period
- Backup yaratdıqdan sonra hard delete
- GDPR "right to erasure" — qəsdən hard delete
- Foreign key-lər diqqətli — cascade yoxsa orphan data

**8. Multi-tenant sistemdə caching necə olur?**
- Cache key-lərində tenant ID prefix: `tenant:123:stats`
- Separate Redis DB per tenant (lux option)
- Tag-based invalidation (`Cache::tags(['tenant:123'])`)
- Laravel `spatie/laravel-multitenancy` avtomatik prefix əlavə edir

**9. Queue-da tenant context necə saxlanır?**
Job payload-a tenant_id əlavə et. Handler execute edəndə tenant context-i switch et (`tenancy()->initialize($tenant)`). `stancl/tenancy` və `spatie/laravel-multitenancy` bunu avtomatik edir.

**10. Subdomain vs path-based routing?**
- **Subdomain** (`acme.app.com`): daha peşəkar görünür, SSL wildcard lazımdır, DNS daha mürəkkəb
- **Path** (`app.com/acme`): sadə SSL, sadə routing, amma daha az peşəkar görünür
- **Custom domain** (`acme.com`): enterprise plan, DNS/SSL tenant-specific

## Praktik Baxış

1. **Tenant context-i middleware-də erkən təyin et** — bütün tətbiqə yayılsın
2. **Global scope-lar istifadə et** — tenant_id filter unudulmasın
3. **Testing** — data leakage test-i mütləq yaz (tenant A, tenant B-nin datasına baxa bilmir)
4. **Logging** — hər log-da tenant_id olsun (debugging üçün)
5. **Monitoring per tenant** — hansı tenant nə qədər resource işlədir
6. **Per-tenant rate limiting** — noisy neighbor-dan qoru
7. **Connection pooling** — çox sayda DB üçün PgBouncer, ProxySQL
8. **Backup strategiyası** — per-tenant restore imkanı saxla
9. **Soft delete tenants** — accidental deletion-dan qoru
10. **Tenant onboarding avtomatlaşdır** — DB create, migration, seeding bir əmrə
11. **Landlord DB-dən lazımsız query etmə** — hər request-də tenant lookup cache et
12. **Regionlar üzrə deploy** — GDPR data residency üçün (EU müştəriləri EU DB-də)


## Əlaqəli Mövzular

- [Database Design](09-database-design.md) — schema-per-tenant vs shared DB
- [Data Partitioning](26-data-partitioning.md) — tenant-based sharding
- [Auth](14-authentication-authorization.md) — tenant-scoped access control
- [Caching](03-caching-strategies.md) — tenant cache isolation
- [E-Commerce Design](24-e-commerce-design.md) — multi-vendor marketplace
