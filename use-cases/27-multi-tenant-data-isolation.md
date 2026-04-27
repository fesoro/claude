# Multi-tenant Data Isolation (Senior)

## Ssenari

SaaS platformasında hər müştərinin (tenant) datası digər müştərilərdən izolə edilməlidir. Fərqli izolyasiya strategiyaları, row-level security, tenant context.

---

## İzolyasiya Strategiyaları

```
1. Separate Database per Tenant:
   Tenant A → DB_A
   Tenant B → DB_B

   ✅ Tam izolyasiya
   ✅ Tenant-specific backup/restore
   ✅ Custom schema mümkün
   ❌ Connection pool overhead (1000 tenant = 1000 DB)
   ❌ Schema migration-lar çətin
   İstifadə: Enterprise SaaS, compliance requirements

2. Separate Schema per Tenant (PostgreSQL):
   DB: myapp
   tenant_a.orders, tenant_a.users
   tenant_b.orders, tenant_b.users

   ✅ İzolasiya yaxşıdır
   ✅ Eyni DB, ayrı schema
   ❌ Schema migration mürəkkəb
   İstifadə: PostgreSQL-specific, orta tenant sayı

3. Shared Database, Row-level Isolation:
   Bütün tenantlar eyni cədvəldə
   orders: tenant_id sütunu ilə ayrılır

   ✅ Sadə, az infrastructure
   ✅ Migration asan
   ❌ Bug → data leak riski
   ❌ Tenant-specific indexlər çətin
   İstifadə: SMB SaaS, yüzlərlə/minlərlə tenant
```

---

## Row-level İzolyasiya (Shared DB)

*Bu kod hər cədvəldə tenant_id sütunu ilə row-level izolyasiyanı və PostgreSQL RLS policy-ni göstərir:*

```sql
-- Hər cədvəldə tenant_id
CREATE TABLE orders (
    id          BIGINT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    customer_id INT NOT NULL,
    total       DECIMAL(10,2),
    created_at  TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_tenant_created (tenant_id, created_at)
);

-- PostgreSQL Row Level Security
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation ON orders
    USING (tenant_id = current_setting('app.tenant_id')::int);

-- Laravel hər sorğuda: SET app.tenant_id = 42
```

---

## Laravel İmplementasiyası

*Bu kod Tenant model-ini, TenantContext singleton-unu, middleware-i, global scope-u, HasTenant trait-ini və model istifadəsini göstərir:*

```php
// Tenant model
class Tenant extends Model
{
    protected $fillable = ['name', 'domain', 'db_connection', 'plan'];
    
    public static function findByDomain(string $domain): ?self
    {
        return static::where('domain', $domain)->first();
    }
}

// TenantContext — request boyunca tenant-ı saxla
class TenantContext
{
    private static ?Tenant $current = null;
    
    public static function set(Tenant $tenant): void
    {
        self::$current = $tenant;
    }
    
    public static function get(): Tenant
    {
        if (!self::$current) {
            throw new \RuntimeException('Tenant context qurulmayıb');
        }
        return self::$current;
    }
    
    public static function id(): int
    {
        return self::get()->id;
    }
    
    public static function clear(): void
    {
        self::$current = null;
    }
}

// Middleware — tenant-ı müəyyən et
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();  // acme.myapp.com
        
        // Subdomain-dən tenant tap
        $subdomain = explode('.', $host)[0];
        $tenant    = Tenant::where('subdomain', $subdomain)->first();
        
        if (!$tenant) {
            return response()->json(['error' => 'Tenant tapılmadı'], 404);
        }
        
        // Tenant context qur
        TenantContext::set($tenant);
        
        // PostgreSQL RLS üçün
        DB::statement("SET app.tenant_id = {$tenant->id}");
        
        $response = $next($request);
        
        TenantContext::clear();
        
        return $response;
    }
}

// Global scope — hər sorğuya tenant_id əlavə et
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable() . '.tenant_id', TenantContext::id());
    }
}

// HasTenant trait — Tenant-a məxsus modellər üçün
trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        // Global scope — bütün query-lərə tenant filter
        static::addGlobalScope(new TenantScope());
        
        // Create zamanı tenant_id avtomatik
        static::creating(function (Model $model) {
            if (!$model->tenant_id) {
                $model->tenant_id = TenantContext::id();
            }
        });
    }
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

// Model
class Order extends Model
{
    use HasTenant;
    
    protected $fillable = ['customer_id', 'total', 'status'];
    // tenant_id həmişə avtomatik əlavə olunur, filter olunur
}

// İstifadə
// $orders = Order::all();  ← yalnız cari tenant-ın order-ları!
// $order = Order::create([...]);  ← tenant_id avtomatik
```

---

## Separate DB per Tenant

*Bu kod hər tenant üçün dinamik DB connection yaradıb migration-ları işlədən ayrı verilənlər bazası strategiyasını göstərir:*

```php
// Dynamic DB connection
class TenantDatabaseManager
{
    public function connect(Tenant $tenant): void
    {
        $config = [
            'driver'    => 'mysql',
            'host'      => $tenant->db_host ?? config('database.connections.mysql.host'),
            'database'  => "tenant_{$tenant->id}",
            'username'  => $tenant->db_user ?? config('database.connections.mysql.username'),
            'password'  => $tenant->db_password ?? config('database.connections.mysql.password'),
        ];
        
        // Runtime-da connection əlavə et
        config(["database.connections.tenant" => $config]);
        
        // Cache-i təmizlə
        DB::purge('tenant');
        DB::reconnect('tenant');
    }
    
    public function createDatabase(Tenant $tenant): void
    {
        $dbName = "tenant_{$tenant->id}";
        
        DB::statement("CREATE DATABASE IF NOT EXISTS `$dbName`");
        
        // Migrations çalışdır
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);
    }
}

// Middleware — separate DB
class TenantDatabaseMiddleware
{
    public function __construct(private TenantDatabaseManager $manager) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);
        TenantContext::set($tenant);
        
        // Tenant DB-sinə qoşul
        $this->manager->connect($tenant);
        
        // Bütün model-lər 'tenant' connection istifadə etsin
        app()->bind('db.tenant', fn() => DB::connection('tenant'));
        
        return $next($request);
    }
}
```

---

## Tenant Onboarding

*Bu kod yeni tenant üçün DB yaradıb admin user, default settings qurub welcome email göndərən provisioning servisini göstərir:*

```php
class TenantProvisioningService
{
    public function provision(CreateTenantData $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            // Tenant yarat
            $tenant = Tenant::create([
                'name'      => $data->name,
                'subdomain' => $data->subdomain,
                'email'     => $data->email,
                'plan'      => $data->plan,
            ]);
            
            // DB yarat (separate DB strategy)
            $this->dbManager->createDatabase($tenant);
            
            // Admin user yarat
            $this->createAdminUser($tenant, $data->adminUser);
            
            // Default settings
            $this->seedDefaultData($tenant);
            
            // Welcome email
            Mail::to($data->email)->send(new TenantWelcomeMail($tenant));
            
            return $tenant;
        });
    }
    
    private function seedDefaultData(Tenant $tenant): void
    {
        TenantContext::set($tenant);
        
        // Default roles, permissions, settings
        Role::create(['name' => 'admin', 'tenant_id' => $tenant->id]);
        Role::create(['name' => 'user',  'tenant_id' => $tenant->id]);
        
        Setting::create(['key' => 'currency', 'value' => 'USD', 'tenant_id' => $tenant->id]);
    }
}
```

---

## İntervyu Sualları

**1. Multi-tenancy-nin 3 əsas strategiyası hansılardır?**
Separate DB: tam izolyasiya, enterprise. Separate Schema (PG): eyni DB, ayrı schema. Shared DB + row-level (tenant_id): sadə, yüzlərlə tenant. Tradeoff: izolyasiya vs infrastructure xərci vs mürəkkəblik.

**2. Row-level izolyasiyada data leak riski necə azaldılır?**
Global Scope (Laravel): hər query-ə tenant_id filter avtomatik əlavə edilir. PostgreSQL RLS: DB səviyyəsində tenant_id enforce edilir (application bypass belə). Middleware: hər requestdə tenant context qurulur. Test: tenant sızdırması olmasın deyə tenant-crossing testlər yazılır.

**3. Tenant context nədir, necə saxlanılır?**
Request boyunca cari tenant-ı saxlayan singleton/static class. Subdomain/header/JWT-dən resolve edilir. Middleware-dən set edilir. Global scope bu context-dən tenant_id alır. Request bitdikdə clear edilir (long-running process-lərdə kritik).

**4. Separate DB strategy migration-ı necə idarə edilir?**
Hər tenant DB-sinə ayrıca migration run etmək lazımdır. Artisan command: `php artisan tenants:migrate` — bütün tenantları iterate et, hər birinə migrate çalışdır. Rolling migration (hər tenantı tədricən) vs all-at-once.

---

## Tenant-aware Queue Jobs

*Bu kod queue job-larında tenant context-i yenidən quran və finally blokunda təmizləyən tenant-aware job-u göstərir:*

```php
// Queue job-larında tenant context itirilir — bunu düzgün idarə et
class TenantAwareJob implements ShouldQueue
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $payload,
    ) {}

    public function handle(): void
    {
        // Job-da tenant-ı yenidən qur (middleware işləmir)
        $tenant = Tenant::findOrFail($this->tenantId);
        TenantContext::set($tenant);

        try {
            // DB connection qur (separate DB strategy üçün)
            app(TenantDatabaseManager::class)->connect($tenant);

            // Real iş
            $this->processPayload($this->payload);
        } finally {
            TenantContext::clear(); // Sonrakı job üçün temizlə
        }
    }
}

// Tenant-aware event listener
class OrderShippedListener
{
    public function handle(OrderShipped $event): void
    {
        // Event-i dispatch edərkən tenant_id-i event-ə daxil et
        $tenantId = $event->tenantId;
        TenantAwareJob::dispatch($tenantId, ['order_id' => $event->orderId]);
    }
}
```

---

## Cross-tenant Admin Queries

*Bu kod superadmin üçün global scope-u bypass edərək bütün tenant-ların məlumatlarını yığan analitik sorğuları göstərir:*

```php
// Superadmin tenant-ları aşan sorğu üçün global scope-u bypass et
class AdminAnalyticsService
{
    public function getTotalRevenueAllTenants(): array
    {
        // withoutGlobalScopes() — tenant filter olmadan
        return Order::withoutGlobalScopes()
            ->selectRaw('tenant_id, SUM(total) as revenue')
            ->groupBy('tenant_id')
            ->get()
            ->toArray();
    }

    public function getTopTenantsBy(string $metric): Collection
    {
        // Yalnız admin panel-də, heç vaxt tenant user-a açma!
        return Tenant::with(['orders' => function ($q) {
            $q->withoutGlobalScopes(); // Tenant scope bypass
        }])->get();
    }
}
```

---

## İntervyu Sualları

**5. Queue job-larında tenant context necə idarə edilir?**
Middleware queue worker-da işləmir. Tenant context request bitdikdə clear olur. Həll: job-a `tenant_id` parametri ötür, `handle()` metodunun əvvəlində tenant-ı DB-dən yüklə, context qur, `finally` blokunda clear et. `TenantContext::set()` + `finally TenantContext::clear()` zəruri.

**6. Tenant-a aid test yazarkən nə nəzərə alınmalı?**
Test-lərdə tenant context qurulmalıdır: `TenantContext::set($tenant)` setUp-da. Tenant-crossing test: A tenantının user-i B tenantının data-sına müraciət edə bilmir? — `assertThrows` ilə yoxla. Global scope-u bypass edən kod: admin panel test-lərində `withoutGlobalScopes()` istifadəsini ayrı test et.

---

## Anti-patternlər

**1. tenant_id filterlənməsini hər sorğuya əl ilə əlavə etmək**
`WHERE tenant_id = $currentTenant` şərtini hər Eloquent sorğusunda manual yazmaq — bir yerdə unutmaq data leak yaradır, başqa tenantin məlumatları görünə bilir. Global Scope tətbiq et, `tenant_id` filterlənməsi avtomatik hər sorğuya əlavə edilsin.

**2. Tenant kontekstini request boyunca temizlememek**
Long-running process-lərdə (queue worker, artisan command) tenant kontekstini request bitdikdən sonra sıfırlamamaq — növbəti job əvvəlki tenantin kontekstini miras alır, yanlış tenant adına əməliyyatlar icra olunur. Hər job başlanğıcında tenant kontekstini qurun, bitdikdə mütləq `TenantContext::clear()` çağır.

**3. Tenant isolation-ı yalnız application layerında tətbiq etmək**
Tenant ayrımını yalnız Laravel Global Scope ilə həyata keçirmək, DB səviyyəsində heç bir məhdudiyyət qoymamaq — developer xəta etdikdə, Global Scope bypass edildikdə (məs. `withoutGlobalScopes()`) bütün tenantların data-sı açıq olur. Həssas məlumatlara PostgreSQL RLS (Row Level Security) əlavə et.

**4. Bütün tenantların migration-larını eyni anda çalışdırmaq**
`tenants:migrate` əmrini bütün tenant DB-lərinə eyni anda (paralel) çalışdırmaq — DB server-ə eyni anda çox bağlantı açılır, aşırı yük yaranır, migrasyonlar bir-birini bloklaşdıra bilər. Rolling migration istifadə et, tenantları kiçik qruplarla yenilə.

**5. Tenant subdomain/header doğrulamasını middleware-dən keçirməmək**
Tenant-ı URL parametrindən birbaşa controller-da resolve etmək — istənilən tenant ID-si ilə sorğu atılıb başqa tenantin data-sına potensial çıxış əldə edilə bilər. Tenant resolve etməyi mərkəzləşdirilmiş middleware-ə ver, etibarsız tenant-ları early reject et.

**6. Shared schema-da tenant-a aid indeksləri düzgün qurmamaq**
Shared DB strategiyasında indeksləri `tenant_id` olmadan qurmaq — `SELECT * FROM orders WHERE status = 'pending'` bütün tenantların data-sını oxuyur, böyük cədvəllərdə yavaşlıq yaranır. Composite indekslər qurun: `(tenant_id, status)`, `(tenant_id, created_at)`.
