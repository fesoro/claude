# Multi-Tenant SaaS — DB Design (Senior ⭐⭐⭐)

## İcmal

Multi-tenancy: bir tətbiqin birdən çox müştəriyə (tenant) xidmət etməsi. SaaS şirkəti üçün ən kritik arxitektura qərarlarından biridir — yanlış seçim sonradan yenidən qurmağa məcbur edir.

---

## 3 Əsas Yanaşma

```
┌────────────────┬──────────────────┬──────────────────────────────────────┐
│ Yanaşma        │ Nümunə           │ Xüsusiyyət                           │
├────────────────┼──────────────────┼──────────────────────────────────────┤
│ Shared DB,     │ Shopify, Slack   │ Bir DB, hər cədvəldə tenant_id sütunu│
│ Shared Schema  │                  │                                      │
├────────────────┼──────────────────┼──────────────────────────────────────┤
│ Shared DB,     │ PostgreSQL SaaS  │ Bir DB, hər tenant üçün ayrı schema  │
│ Separate Schema│                  │ (schema-level isolation)             │
├────────────────┼──────────────────┼──────────────────────────────────────┤
│ Separate DB    │ GitHub Ent.,     │ Hər tenant üçün ayrı DB instance     │
│ per Tenant     │ Salesforce Orgs  │                                      │
└────────────────┴──────────────────┴──────────────────────────────────────┘
```

---

## Yanaşma 1: Shared DB + Shared Schema (tenant_id)

```sql
-- Ən çox istifadə olunan pattern (Laravel default)
-- Hər cədvəldə tenant_id sütunu

CREATE TABLE workspaces (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name       VARCHAR(100) NOT NULL,
    subdomain  VARCHAR(63) UNIQUE NOT NULL,
    plan       ENUM('starter', 'growth', 'enterprise') DEFAULT 'starter',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Hər domain entity-sində tenant_id
CREATE TABLE projects (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id),  -- tenant key!
    name         VARCHAR(100) NOT NULL,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE tasks (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id),  -- hər cədvəldə
    project_id   UUID NOT NULL REFERENCES projects(id),
    title        VARCHAR(255) NOT NULL,
    assignee_id  UUID,
    status       VARCHAR(20) DEFAULT 'todo',
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

-- VACIB: Hər index-ə workspace_id əlavə et
CREATE INDEX idx_projects_workspace ON projects(workspace_id);
CREATE INDEX idx_tasks_workspace    ON tasks(workspace_id, status);
CREATE INDEX idx_tasks_project      ON tasks(project_id, workspace_id);

-- BÜTÜN QUERY-LƏR workspace_id ilə filterlənməlidir:
SELECT * FROM tasks
WHERE workspace_id = :current_tenant_id  -- HƏMİŞƏ!
  AND project_id = :project_id;
```

**Laravel-də Tenant Scoping:**

```php
// Global scope — avtomatik tenant filter
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('workspace_id', auth()->user()->workspace_id);
    }
}

// Bütün model-lərdə
trait BelongsToTenant
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
        
        static::creating(function ($model) {
            $model->workspace_id ??= auth()->user()->workspace_id;
        });
    }
}

class Task extends Model
{
    use BelongsToTenant;
}
// Artıq Task::all() → avtomatik WHERE workspace_id = ?
```

---

## Yanaşma 2: Shared DB + Separate Schema (PostgreSQL)

```sql
-- PostgreSQL schemas: hər tenant üçün ayrı namespace
-- CREATE SCHEMA tenant_{id}

-- Setup new tenant:
CREATE SCHEMA tenant_abc123;

-- Bu schemada tablolar
CREATE TABLE tenant_abc123.tasks (
    id         UUID PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    status     VARCHAR(20),
    created_at TIMESTAMPTZ DEFAULT NOW()
    -- tenant_id sütunu LAZIM DEYİL — schema artıq isolate edir
);

-- Public schema: shared data (plans, features, etc.)
CREATE TABLE public.workspaces (
    id        UUID PRIMARY KEY,
    schema_name VARCHAR(63) UNIQUE NOT NULL,  -- 'tenant_abc123'
    plan      VARCHAR(20)
);

-- Runtime: search_path dəyiş
SET search_path = tenant_abc123, public;
-- Artıq:
SELECT * FROM tasks;
-- → tenant_abc123.tasks oxuyur
```

**Laravel-də Schema Switching:**

```php
// Tenancy for Laravel paketi kimi
class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = explode('.', $request->getHost())[0];
        $workspace = Workspace::where('subdomain', $subdomain)->firstOrFail();
        
        // PostgreSQL schema switch
        DB::statement("SET search_path = tenant_{$workspace->id}, public");
        
        app()->instance('current_tenant', $workspace);
        
        return $next($request);
    }
}
```

**Trade-offs:**

```
Üstünlüklər:
  ✓ tenant_id sütunları lazım deyil
  ✓ Tenant-specific migrations mümkün
  ✓ Partial backup: bir tenant-ın datası
  ✓ DROP SCHEMA → tam silinmə
  
Çatışmazlıqlar:
  ✗ PostgreSQL: max ~10K schema (praktik limit)
  ✗ Schema creation/migration yavaş
  ✗ Cross-tenant queries çətin
  ✗ Connection pooling (PgBouncer) schema state saxlamır
```

---

## Yanaşma 3: Separate Database per Tenant

```
Hər tenant → ayrı DB instance

Nümunə:
  tenant_acme.db.internal:5432
  tenant_globex.db.internal:5432
  tenant_initech.db.internal:5432

Database routing:
  Tenant metadata DB (master DB):
    tenant_id → connection string mapping
    
  App: request gəlir → tenant_id tap → DB-yə qoşul
```

```php
// Database routing
class TenantDatabaseManager
{
    public function connectFor(Workspace $workspace): void
    {
        config(['database.connections.tenant' => [
            'driver'   => 'pgsql',
            'host'     => $workspace->db_host,
            'database' => $workspace->db_name,
            'username' => $workspace->db_user,
            'password' => decrypt($workspace->db_password),
        ]]);
        
        DB::reconnect('tenant');
    }
}
```

**Trade-offs:**

```
Üstünlüklər:
  ✓ Tam data isolation (GDPR, HIPAA üçün ideal)
  ✓ Tenant-specific backup/restore
  ✓ Performance isolation (bir tenant digərini yavaşlatmır)
  ✓ Custom DB config per tenant (büyük müştəri üçün)
  ✓ Tenant-specific encryption keys
  
Çatışmazlıqlar:
  ✗ Çox bahalı (hər tenant ayrı DB server)
  ✗ Migration: N DB-ni güncəlləmək lazım
  ✗ Resource utilization aşağı (kiçik tenantlar üçün)
  ✗ Connection pool per tenant → çox qoşuntu
```

---

## Row-Level Security (PostgreSQL RLS)

```sql
-- Shared schema-da amma DB-level enforcement
-- tenant_id sütununu app-dan asılı olmadan enforce edir

ALTER TABLE tasks ENABLE ROW LEVEL SECURITY;

-- Policy: user yalnız öz workspace-ının datalarını görür
CREATE POLICY tenant_isolation ON tasks
    USING (workspace_id = current_setting('app.current_workspace_id')::uuid);

-- Her request əvvəl:
SET LOCAL app.current_workspace_id = 'abc-123-def';

-- Artıq SELECT * FROM tasks → avtomatik filterlənir
-- INSERT workspace_id yanlış tenant → blocked
-- Dev yanlışlıqla WHERE workspace_id unuddusa belə safe

-- Performans:
-- RLS policy index istifadə edir (workspace_id indexed olmalı)
```

---

## Tenant Onboarding Pipeline

```
Yeni müştəri qeydiyyatı:
  1. workspaces table-a insert
  2. (schema approach) → CREATE SCHEMA tenant_xyz
  3. (DB approach) → provision new DB
  4. İlk admin user yaratmaq
  5. Seeding: default data (status types, roles, etc.)
  6. Welcome email göndər

Laravel Artisan command:
  php artisan tenant:create --name="ACME Corp" --plan=growth
  php artisan tenant:migrate --tenant=xyz  (schema/DB approach)
  php artisan tenant:seed --tenant=xyz
```

---

## Hansını Seçmək Lazımdır?

```
Shared Schema (tenant_id):
  ✓ SMB SaaS, startup, MVP
  ✓ < 10K tenant
  ✓ Az ops yükü
  ✓ Laravel-in default approach
  Nümunə: Jira, Trello, Asana

Separate Schema (PostgreSQL):
  ✓ Orta miqyaslı SaaS
  ✓ Tenant-specific customizations lazımdır
  ✓ GDPR data isolation tələbi
  ✓ < 5K tenant (schema limit)
  Nümunə: Notion (hybrid)

Separate DB:
  ✓ Enterprise SaaS
  ✓ Tam isolation tələbi (bank, healthcare)
  ✓ Tenant-specific compliance
  ✓ < 1K tenant (cost reasons)
  Nümunə: Salesforce, GitHub Enterprise

Hybrid (çox yaygın):
  Kiçik tenantlar: shared schema
  Böyük/enterprise tenantlar: dedicated DB
  Shopify: shared amma Vitess ilə sharding
```

---

## Anti-Patterns

```
✗ tenant_id INDEX-siz:
  WHERE workspace_id = ? → full table scan
  Mütləq hər tenant cədvəlinə index

✗ Cross-tenant query:
  SELECT * FROM tasks WHERE status = 'todo'
  -- WHERE workspace_id yoxdur! Data leak!
  Global scope-ları istifadə et

✗ Shared cache-da tenant data:
  Redis-də key: "user:123" → başqa tenant oxuya bilər
  Düzgün: "tenant:{id}:user:123"

✗ Tenant schema yaratmaq üçün string interpolation:
  "CREATE SCHEMA tenant_{$input}" → SQL injection!
  UUID-dən schema adı düzəlt, istifadəçi inputunu sanitize et

✗ Migration unify etməmək (schema approach):
  Hər tenant migration-ı manually run etmək
  Automation + tracking table lazımdır
```

---

## Praktik Tapşırıqlar

```
1. Laravel-də tenant_id Global Scope yaz:
   - BelongsToTenant trait yarat
   - 3 model-ə tətbiq et
   - Test: tenant A, tenant B data-sını görə bilərmi?

2. Migration tracker:
   Schema-per-tenant approach üçün:
   - Hansı tenant-da hansı migration run edilib?
   - tenant_migrations table yarat
   - Artisan command: tenant:migrate --pending

3. Tenant provisioning:
   - Yeni tenant POST /register
   - workspaces insert
   - Admin user yaratmaq
   - Default seed data
   - Welcome notification

4. RLS test:
   - PostgreSQL-də RLS policy yaz
   - current_setting-i dəyiş
   - Başqa tenant-ın datası görünürmü? (görmənməlidir)
```
