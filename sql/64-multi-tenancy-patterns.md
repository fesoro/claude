# Multi-Tenancy Patterns

> **Seviyye:** Advanced ⭐⭐⭐

## Multi-Tenancy nedir?

Bir aplikasiya - cox musteri (tenant). Her tenant-in oz user-leri, oz data-si, izole muhit. SaaS-larin essas pattern-i (Slack, Notion, Shopify, GitHub Organizations).

```
SaaS aplikasiya
   ├── Tenant A (Acme Corp)
   │   ├── Users: alice, bob, ...
   │   └── Data: 10K orders, 500 products
   ├── Tenant B (Globex)
   │   ├── Users: jane, ...
   │   └── Data: 50K orders, 2K products
   └── Tenant C (Initech)
       └── ...
```

---

## 3 Esas Strategiya

| Strategy | Izolasiya | Cost | Operations | Scaling |
|----------|-----------|------|------------|---------|
| **Single DB, shared schema** | Asagi (logical) | En ucuz | Sade | Cetin |
| **Schema per tenant** | Orta | Orta | Orta | Orta |
| **DB per tenant** | Yuksek (fiziki) | Bahali | Murekkeb | Asan |

---

## Strategy 1: Single DB, Shared Schema (tenant_id Column)

Butun tenant-lerin data-si eyni table-da, `tenant_id` ile ayrilir.

```sql
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    user_id BIGINT,
    amount DECIMAL(10,2),
    created_at TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_tenant_user (tenant_id, user_id)
);

-- Her query-de tenant_id filter MEHCBURIDIR
SELECT * FROM orders WHERE tenant_id = 5 AND user_id = 100;
```

### Plus

- En ucuz - 1 DB, 1 server
- Cross-tenant analytics asan (`GROUP BY tenant_id`)
- Schema migration 1 defe
- Resource pooling

### Minus

- **Noisy neighbor** - bir tenant-in heavy query digerlerini yavaslatir
- **Data leak riski** - tenant_id unutdun, basqa tenant-in data-sini gosterirsen!
- Backup/restore tenant-spesifik cetin
- 1 tenant 90% data-ni alirsa - other-lere zerer

### Laravel: Global Scope + Middleware

```php
// 1. Tenant context (request-da)
class TenantMiddleware
{
    public function handle($request, Closure $next)
    {
        $tenant = Tenant::where('subdomain', $request->getHost())->firstOrFail();
        app()->instance('tenant', $tenant);
        
        return $next($request);
    }
}

// 2. Trait + Global Scope
trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->bound('tenant')) {
                $builder->where('tenant_id', app('tenant')->id);
            }
        });

        static::creating(function ($model) {
            if (app()->bound('tenant') && !$model->tenant_id) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }
}

// 3. Model-de istifade
class Order extends Model
{
    use BelongsToTenant;
}

// Indi avtomatik
Order::all();  // SELECT * FROM orders WHERE tenant_id = ?
Order::create(['amount' => 100]);  // tenant_id avtomatik
```

### Tehlukeli kod

```php
// PIS - global scope bypass
Order::withoutGlobalScopes()->get();  // Butun tenant-leri qaytarir!

// PIS - raw query global scope ist etmir
DB::select('SELECT * FROM orders WHERE id = ?', [$id]);
// Hansi tenant-in order-i? Yoxlamir!

// DOGRU
DB::select('SELECT * FROM orders WHERE id = ? AND tenant_id = ?', 
    [$id, app('tenant')->id]);
```

### Row-Level Security (PostgreSQL)

PostgreSQL native dest verir - DB seviyyesinde tenant filter:

```sql
-- 1. RLS-i aktiv et
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;

-- 2. Policy yarat
CREATE POLICY tenant_isolation ON orders
    USING (tenant_id = current_setting('app.tenant_id')::BIGINT);

-- 3. Connection-da tenant set et
SET app.tenant_id = 5;

-- 4. Indi avtomatik filter olunur
SELECT * FROM orders;  -- yalniz tenant 5
```

```php
// Laravel connection-da set
DB::statement("SET app.tenant_id = ?", [$tenantId]);
```

**Plus:** Hetta raw SQL data leak edə bilməz.
**Minus:** Application-superuser bypass ede biler, debug cetin.

---

## Strategy 2: Schema per Tenant (PostgreSQL)

Her tenant-in oz schema-si var, eyni DB icinde.

```sql
-- Tenant-ler ucun schema-lar
CREATE SCHEMA tenant_acme;
CREATE SCHEMA tenant_globex;

-- Her schema-da eyni table-lar
CREATE TABLE tenant_acme.orders (...);
CREATE TABLE tenant_globex.orders (...);

-- Connection-da search_path set et
SET search_path TO tenant_acme;
SELECT * FROM orders;  -- tenant_acme.orders
```

### Laravel ile

```php
class TenantConnectionService
{
    public function switchToTenant(string $tenantSchema): void
    {
        DB::statement("SET search_path TO {$tenantSchema}, public");
    }
}

// Middleware
class TenantSchemaMiddleware
{
    public function handle($request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);
        DB::statement("SET search_path TO tenant_{$tenant->id}, public");
        
        return $next($request);
    }
}
```

### Shared vs Tenant-Specific Tables

```sql
-- public schema - shared
CREATE TABLE public.tenants (id, name, plan);
CREATE TABLE public.subscriptions (...);

-- tenant_<id> - tenant-specific
CREATE TABLE tenant_5.users (...);
CREATE TABLE tenant_5.orders (...);
```

### Plus

- Logical izolasiya yaxsi - schema barrier
- Backup/restore per tenant: `pg_dump -n tenant_acme`
- Tenant-specific schema modification mumkun
- 1 DB connection, schema switch ucuzdur

### Minus

- Migration butun schema-larda run edilmelidir (loop)
- Schema sayi sehd var (PG-de praktiki ~10K)
- Cross-tenant query cetinlesir
- MySQL desteklemir (yalniz PostgreSQL ve Oracle)

### Migration Looping

```php
// Migration - butun tenant schema-larda
class CreateOrdersTable extends Migration
{
    public function up()
    {
        $tenants = DB::table('tenants')->pluck('id');
        
        foreach ($tenants as $tenantId) {
            DB::statement("SET search_path TO tenant_{$tenantId}");
            
            Schema::create('orders', function (Blueprint $t) {
                $t->id();
                $t->decimal('amount', 10, 2);
                $t->timestamps();
            });
        }
    }
}
```

---

## Strategy 3: DB per Tenant

Her tenant tam ayri DB-de.

```
PostgreSQL cluster
   ├── tenant_acme_db (orders, users, ...)
   ├── tenant_globex_db (orders, users, ...)
   └── tenant_initech_db (orders, users, ...)
```

### Connection switching

```php
// config/database.php-de manager DB
'connections' => [
    'manager' => [
        'driver' => 'pgsql',
        'host' => 'manager-db.example.com',
        'database' => 'manager',
    ],
    'tenant' => [
        'driver' => 'pgsql',
        'host' => null,        // dynamic
        'database' => null,    // dynamic
    ],
],
```

```php
class TenantConnectionResolver
{
    public function setupTenant(Tenant $tenant): void
    {
        config([
            'database.connections.tenant.host' => $tenant->db_host,
            'database.connections.tenant.database' => $tenant->db_name,
            'database.connections.tenant.username' => $tenant->db_user,
            'database.connections.tenant.password' => decrypt($tenant->db_pass),
        ]);
        
        DB::purge('tenant');  // Cached connection-i sil
        DB::reconnect('tenant');
    }
}

// Model
class Order extends Model
{
    protected $connection = 'tenant';
}
```

### Plus

- **En guclu izolasiya** - fiziki ayrilma
- Per-tenant backup/restore trivial
- Per-tenant tuning, scaling
- Compliance (HIPAA, banking) - data residency
- Noisy neighbor yox
- Per-tenant DB version, encryption mumkun

### Minus

- En bahali (her tenant DB resource-u)
- Onboarding murekkebdir (yeni DB provision)
- Cross-tenant query mumkun deyil (lazimdirsa data warehouse)
- Schema migration butun DB-lerde run lazim
- Connection pool exhaust riski

### Aurora Cluster vs Separate DB

AWS Aurora ile DB per tenant practical olur:

```
1 Aurora cluster
  ├── tenant_acme_db (logical)
  ├── tenant_globex_db (logical)
  └── ... (yuzlerle DB)
```

vs

```
Per-tenant Aurora cluster (en bahali)
  ├── Cluster A (tenant_acme)
  ├── Cluster B (tenant_globex)
  └── ...
```

Cluster icinde 100+ DB normal-dir, Aurora-da connection multiplexing var.

---

## Tradeoffs Table

| Aspekt | Shared | Schema | DB |
|--------|--------|--------|-----|
| **Isolation** | Logical | Schema-level | Physical |
| **Cost / tenant** | $0.10 | $1 | $50+ |
| **Scaling per tenant** | Mumkun deyil | Limited | Asan |
| **Onboarding speed** | Instant | Sec | Min |
| **Cross-tenant query** | Easy | Medium | Hard |
| **Migration** | 1 run | Loop | Loop |
| **Backup per tenant** | Hard | Easy | Trivial |
| **GDPR delete tenant** | Hard | Easy | Drop DB |
| **Resource limit** | DB-wide | DB-wide | Per tenant |
| **Best for** | SMB SaaS | Mid-market | Enterprise |

---

## Hibrid Pattern (Real)

Cox SaaS-lar **hibrid** istifade edir:

```
- Free tier:        Shared schema (cox tenant, ucuz)
- Paid tier:        Schema per tenant
- Enterprise:       Dedicated DB
```

```php
class TenantStrategy
{
    public static function for(Tenant $tenant): string
    {
        return match($tenant->plan) {
            'free' => 'shared',
            'pro' => 'schema',
            'enterprise' => 'database',
        };
    }
}
```

---

## Laravel Packages

### stancl/tenancy

Hem schema, hem DB strategiyani destekleyir.

```php
// composer require stancl/tenancy

// Tenant model
class Tenant extends BaseTenant
{
    public function getInternal(): array
    {
        return ['domain', 'plan'];
    }
}

// Routes
Route::middleware(['tenant'])->group(function () {
    Route::get('/', fn() => 'Tenant: ' . tenant('id'));
});

// Per-tenant migration
php artisan tenants:migrate
```

### spatie/laravel-multitenancy

Daha sade, single-tenant-current pattern:

```php
class CurrentTenant extends Tenant
{
    public function makeCurrent(): static
    {
        config(['database.connections.tenant.database' => $this->database]);
        DB::purge('tenant');
        return $this;
    }
}
```

---

## Tenant Onboarding

### Shared schema - instant

```php
$tenant = Tenant::create(['name' => 'Acme']);
// O qeder. Indi tenant_id=5 ile data yarana biler.
```

### Schema per tenant

```php
public function provision(Tenant $tenant): void
{
    DB::statement("CREATE SCHEMA tenant_{$tenant->id}");
    
    // Migration run
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--database' => 'tenant_specific',
    ]);
}
```

### DB per tenant

```php
public function provision(Tenant $tenant): void
{
    // 1. DB yarat
    DB::statement("CREATE DATABASE tenant_{$tenant->slug}");
    
    // 2. User yarat
    $password = Str::random(32);
    DB::statement("CREATE USER tenant_{$tenant->slug} WITH PASSWORD '{$password}'");
    DB::statement("GRANT ALL ON DATABASE tenant_{$tenant->slug} TO tenant_{$tenant->slug}");
    
    // 3. Tenant qeydleri
    $tenant->update([
        'db_name' => "tenant_{$tenant->slug}",
        'db_user' => "tenant_{$tenant->slug}",
        'db_password' => encrypt($password),
    ]);
    
    // 4. Migration
    $this->switchToTenant($tenant);
    Artisan::call('migrate');
    
    // 5. Seed default data
    Artisan::call('db:seed', ['--class' => 'TenantSeeder']);
}
```

---

## Routing - Subdomain vs Header vs Path

```php
// 1. Subdomain: acme.app.com
Route::domain('{tenant}.app.com')->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

// 2. Header: X-Tenant-ID: 5
class TenantHeaderMiddleware
{
    public function handle($req, $next) {
        $tenantId = $req->header('X-Tenant-ID');
        app()->instance('tenant', Tenant::findOrFail($tenantId));
        return $next($req);
    }
}

// 3. Path: app.com/acme/dashboard
Route::prefix('{tenant}')->middleware('tenant.path')->group(function () {
    Route::get('/dashboard', ...);
});

// 4. JWT claim: token icinde tenant_id
$payload = JWTAuth::parseToken()->getPayload();
$tenantId = $payload->get('tenant_id');
```

| Method | Plus | Minus |
|--------|------|-------|
| Subdomain | Custom branding (acme.app.com) | DNS, SSL setup |
| Header | API-friendly | Manuel set |
| Path | URL-share simple | Cross-tenant link confusion |
| JWT | Stateless | Token expiration handling |

---

## Cross-Tenant Queries

Admin panel - butun tenant-lerin sayini gormek isteyirsen:

```php
// Shared schema - asan
DB::table('orders')
    ->select('tenant_id', DB::raw('COUNT(*) as count'))
    ->groupBy('tenant_id')
    ->get();

// Schema per tenant - cetin
$results = collect();
$tenants = Tenant::all();
foreach ($tenants as $tenant) {
    DB::statement("SET search_path TO tenant_{$tenant->id}");
    $count = DB::table('orders')->count();
    $results->put($tenant->id, $count);
}

// DB per tenant - en cetin
// Hell: ETL → data warehouse (Snowflake, BigQuery)
// Per-tenant DB → daily ETL → analytics DB
```

---

## Noisy Neighbor

Shared schema-da bir tenant heavy query atir, herkes yavaslayir.

**Hellr:**

```sql
-- 1. Resource Governor / SQL Server-de RESOURCE_POOL
-- 2. PostgreSQL: per-user connection limits
ALTER USER tenant_5_user CONNECTION LIMIT 10;

-- 3. Statement timeout per tenant
SET statement_timeout = '5s';

-- 4. Read replica - heavy tenant-leri replica-ya yonelt
```

```php
// Application: heavy tenant-leri yonelt
class ConnectionRouter
{
    public function for(Tenant $tenant): string
    {
        if ($tenant->is_heavy_user) {
            return 'replica_dedicated';
        }
        return 'main';
    }
}
```

---

## Data Export per Tenant

GDPR / data portability:

```php
public function exportTenantData(Tenant $tenant): string
{
    $path = storage_path("exports/tenant_{$tenant->id}.zip");
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);

    // Shared schema
    $tables = ['users', 'orders', 'products'];
    foreach ($tables as $table) {
        $data = DB::table($table)->where('tenant_id', $tenant->id)->get();
        $zip->addFromString("{$table}.json", $data->toJson());
    }

    // DB per tenant - daha sade
    // exec("pg_dump tenant_{$tenant->slug} > {$path}.sql");

    $zip->close();
    return $path;
}
```

---

## Senior Production Tips

```
1. tenant_id-ni HƏMISHƏ index-le (composite-de birinci sutun)
2. Test-de cross-tenant access-i actively yoxla
3. Logging-de tenant_id elave et (tracing)
4. Rate limit per tenant
5. Backup strategy choose-da gec - migration cetin
6. SSO / IdP integration tenant-aware olmalidir
7. Connection pool-da tenant-aware sizing
8. Query log-da tenant_id - debug ucun
```

---

## Interview suallari

**Q: Shared schema vs DB per tenant - hansini secmeli?**
A: Tenant sayi cox (1000+) ve hamisi kicikdirse - shared schema (ucuz, scalable). Tenant az amma boyukdurse, ya da compliance teleb edirse (HIPAA, banking) - DB per tenant. Hibrid: free plan shared, enterprise plan dedicated. Cost vs isolation tradeoff.

**Q: Shared schema-da data leak qarsisini nece almaq olar?**
A: 1) Global scope (Eloquent) hemise tenant_id filter elave etsin. 2) Raw query-leri ban et / code review. 3) PostgreSQL Row-Level Security (RLS) - DB seviyyesinde garanti. 4) Test-de actively cross-tenant access yoxla (her endpoint ucun integration test). 5) Tenant_id butun unique constraint-lerin parci olsun.

**Q: Schema per tenant-de migration nece idare olunur?**
A: Migration butun tenant schema-larda loop ile run edilir. Yeni tenant onboarding-da template schema-dan yaranar, sonra migration. Risk: bir migration bir schema-da fail olarsa, partial state-de qalir - transaction ile sarib transactional DDL (PostgreSQL desteklemir hamsi DDL-i). stancl/tenancy package bu loop-i avtomatlasdirir.

**Q: Noisy neighbor problem-i nece hell olunur?**
A: Shared schema-da tehlukeli. Hellr: 1) Per-tenant query/connection limit. 2) Heavy tenant-leri ayri read replica-ya. 3) Statement timeout. 4) Resource pool (Aurora). 5) Tenant-i monitoring et, threshold-u keciren-leri ayri DB-ye migrate et. Long-term: enterprise plan-larini DB-per-tenant-e cixar.

**Q: Subdomain routing-de tenant resolution nece isleyir?**
A: 1) Wildcard DNS: `*.app.com` yonlendirir. 2) SSL: wildcard cert (`*.app.com`) ya per-tenant cert (Let's Encrypt automation). 3) Middleware-de `Request::getHost()`-dan subdomain-i parse et, Tenant table-da axtar, app instance-a bind et. 4) Cache: tenant lookup-i Redis-de cache et (her request-de DB hit etme). 5) Custom domain support-u ucun: tenant-de `custom_domain` column saxla.
