# Multi-Tenancy Arxitekturası

## Mündəricat
1. [Multi-Tenancy nədir?](#multi-tenancy-nədir)
2. [İzolyasiya Modelləri](#izolyasiya-modelləri)
3. [Trade-off Müqayisəsi](#trade-off-müqayisəsi)
4. [Tenant Context Propagation](#tenant-context-propagation)
5. [Cross-Tenant Sorğuların Önlənməsi](#cross-tenant-sorğuların-önlənməsi)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Multi-Tenancy nədir?

```
SaaS məhsul — bir infrastructure, çox müştəri (tenant).

Hər tenant:
  - Öz dataına sahib
  - Digər tenantın datanı görə bilməz
  - Eyni application instance-ı istifadə edir

Nümunələr:
  Salesforce, Slack, GitHub — hər şirkət bir tenant

Tərif:
  Tenant = Bir müştəri (şirkət, organization)
  Tenant isolation = Tenantlar bir-birinin datasını görə bilməz
```

---

## İzolyasiya Modelləri

```
Model 1: DB-per-Tenant (Ən güclü izolyasiya)
  ┌──────────┐ ┌──────────┐ ┌──────────┐
  │ Tenant A │ │ Tenant B │ │ Tenant C │
  │ DB_A     │ │ DB_B     │ │ DB_C     │
  └──────────┘ └──────────┘ └──────────┘
  
  + Tam data izolyasiyası
  + Tenant-specific backup/restore
  + Ayrıca compliance (GDPR - data lokasiyası)
  + Performance izolyasiyası (noisy neighbor yoxdur)
  - Daha yüksək infrastructure xərci
  - Hər tenant üçün migration idarəsi
  - Connection pool management

Model 2: Schema-per-Tenant (Orta izolyasiya)
  Bir DB, hər tenant üçün ayrı schema (PostgreSQL-də real)
  
  ┌─────────────────────────────────────┐
  │              Database               │
  │  ┌──────────┐ ┌──────────┐         │
  │  │schema_A  │ │schema_B  │ ...     │
  │  │  orders  │ │  orders  │         │
  │  │  users   │ │  users   │         │
  │  └──────────┘ └──────────┘         │
  └─────────────────────────────────────┘
  
  + Güclü izolyasiya (DB səviyyəsindəki)
  + Orta xərc
  - PostgreSQL-də yaxşı, MySQL-də məhdud
  - Çox tenant olduqda schema sayı artır

Model 3: Row-Level (Shared DB, Shared Schema)
  ┌─────────────────────────────────────┐
  │              orders tablosu         │
  │  id │ tenant_id │ amount │ status   │
  │  1  │ tenant_A  │  100   │ paid     │
  │  2  │ tenant_B  │  250   │ pending  │
  │  3  │ tenant_A  │   50   │ paid     │
  └─────────────────────────────────────┘
  
  + Ən aşağı xərc
  + Sadə migration
  + Mərkəzləşdirilmiş idarəetmə
  - Ən az izolyasiya (kod xətası → data leak!)
  - "Noisy neighbor" problemi
  - Tenant-specific backup çətin
```

---

## Trade-off Müqayisəsi

```
┌──────────────────┬────────────┬────────────┬────────────┐
│                  │ DB-per-    │ Schema-    │ Row-Level  │
│                  │ Tenant     │ per-Tenant │            │
├──────────────────┼────────────┼────────────┼────────────┤
│ Izolyasiya       │ ✅ Güclü   │ ✅ Orta    │ ⚠️ Zəif   │
│ Xərc             │ ❌ Yüksək  │ ⚠️ Orta    │ ✅ Aşağı  │
│ Performans       │ ✅ İzolə   │ ✅ İzolə   │ ⚠️ Paylaşır│
│ Backup/Restore   │ ✅ Asan    │ ✅ Asan    │ ❌ Çətin  │
│ Compliance       │ ✅ Asan    │ ✅ Orta    │ ❌ Çətin  │
│ Migration        │ ❌ Çətin   │ ⚠️ Orta    │ ✅ Asan   │
│ Tenant sayı      │ Az (100-ler)│Orta(1000-lər)│Çox(100K+)│
└──────────────────┴────────────┴────────────┴────────────┘

Nə vaxt hansı:
  DB-per-Tenant:  Enterprise SaaS, az böyük müştəri, compliance tələbi
  Schema-per:     Orta ölçülü SaaS, PostgreSQL istifadə
  Row-Level:      Çox sayda kiçik tenant, startup, cost-sensitive
```

---

## Tenant Context Propagation

```
Hər request-in hansı tenant üçün olduğu bilinməlidir.

Subdomain-based:
  tenant-a.app.com → tenant = "tenant-a"
  tenant-b.app.com → tenant = "tenant-b"

Path-based:
  app.com/tenant-a/dashboard → tenant = "tenant-a"

JWT claim-based:
  {
    "sub": "user_123",
    "tenant_id": "tenant-a"
  }

API Key-based:
  X-API-Key: key_for_tenant_a

Propagation chain:
  Request → Middleware (tenant resolve) → Context → Repository
  
  TenantContext (singleton per-request):
    set(Tenant $tenant)
    get(): Tenant
  
  Repository tənant context-dən tenantId oxuyur.
```

---

## Cross-Tenant Sorğuların Önlənməsi

```
Row-Level model-də ən böyük risk:
  $order = Order::find($id);  // tenant_id yoxlanmır!
  // Başqa tenantın order-i qaytarıla bilər!

Həll strategiyaları:

1. Global Scope (Laravel):
   Model-ə avtomatik WHERE tenant_id = ? əlavə et.
   Unutmaq mümkün deyil.

2. Repository wrapper:
   findById() həmişə tenant filter tətbiq edir.
   Direct DB query yasaq.

3. DB row-level security (PostgreSQL):
   CREATE POLICY tenant_isolation ON orders
     USING (tenant_id = current_setting('app.tenant_id'));
   
   DB səviyyəsindəki qoruq — kod xətası belə data leak etmir.

4. Automated test:
   Hər CRUD əməliyyatı üçün "başqa tenant görə bilməz" testi.
```

---

## PHP İmplementasiyası

```php
<?php
// Tenant Context — Request-scoped singleton
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

    public static function getId(): string
    {
        return self::get()->getId();
    }
}

// Middleware — tenant resolve
class TenantMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Subdomain-dən tenant resolve et
        $subdomain = explode('.', $request->getHost())[0];
        $tenant = $this->tenantRepo->findBySubdomain($subdomain);

        if (!$tenant || !$tenant->isActive()) {
            return response()->json(['error' => 'Tenant tapılmadı'], 404);
        }

        TenantContext::set($tenant);

        // DB connection-ı tenant schema-sına yönləndir (schema-per-tenant)
        // $this->db->setSearchPath($tenant->getSchemaName());

        return $next($request);
    }
}
```

```php
<?php
// Row-Level: Laravel Global Scope
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('tenant_id', TenantContext::getId());
    }
}

// Model-ə əlavə et
class Order extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        // Yaradarkən tenant_id avtomatik əlavə et
        static::creating(function (Order $order) {
            $order->tenant_id = TenantContext::getId();
        });
    }
}

// İstifadə — tenant filter avtomatik tətbiq olunur
$orders = Order::where('status', 'pending')->get();
// SQL: SELECT * FROM orders WHERE tenant_id = 'tenant-a' AND status = 'pending'
```

```php
<?php
// Schema-per-tenant: connection routing
class TenantAwareRepository
{
    private PDO $connection;

    public function __construct(ConnectionManager $connections)
    {
        $tenantId = TenantContext::getId();
        $schema   = "tenant_{$tenantId}";

        $this->connection = $connections->getForSchema($schema);
        $this->connection->exec("SET search_path TO {$schema}, public");
    }

    public function findOrders(): array
    {
        // Bu connection yalnız bu tenant-ın schema-sında işləyir
        return $this->connection
            ->query('SELECT * FROM orders')
            ->fetchAll();
    }
}
```

---

## İntervyu Sualları

- Multi-tenancy-nin 3 modelini müqayisə edin. Hansını nə vaxt seçərdiniz?
- Row-Level modeldə tenant data leak-in qarşısını necə alırsınız?
- "Noisy neighbor" problemi nədir? Hansı modeldə ən çox baş verir?
- Laravel Global Scope tenant isolation üçün yetərlidirmi?
- DB-per-Tenant modeldə migration-ları necə idarə edərdiniz?
- GDPR data residency tələbləri üçün hansı model ən uyğundur?
- PostgreSQL Row-Level Security tenant isolation üçün necə istifadə edilir?
