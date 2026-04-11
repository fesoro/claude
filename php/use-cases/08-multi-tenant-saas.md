# Multi-Tenant SaaS Application Dizayny

## Problem

Bir SaaS (Software as a Service) application yaradirsiniz. Yuzlerle, minlerle musteriniz (tenant) var ve her biri eyni application-dan istifade edir, amma oz datalarini gorur. Meselen, bir project management toolu dusunun -- her sirket oz layihelerini, oz iscilarini gormelidir. Basqa sirketin datasina catim olmamalidir.

Bu, **multi-tenancy** problemidir. Bir application instance-i coxlu musteriyi xidmet edir, amma her musterinin datalari bir-birinden izole olmalidir.

### Problem niyə yaranır?

Developer tenant izolasiyasını düzgün implement etməzsə: (1) `tenant_id` filtrini unutmaq — Company A Company B-nin sifarişlərini görür (data leak); (2) background job-da tenant context itirmək — job bütün tenant-ların datasını işləyir; (3) bir tenant-ın yüklü sorğusu shared DB-ni yavaşladır — digər tenant-lar "noisy neighbor" problemi yaşayır. Bu həm texniki, həm hüquqi (GDPR, data sovereignty) problemdir.

---

## Tenant Isolation Strategiyalari

Multi-tenancy-nin 3 esas yanasmasi var:

### 1. Shared Database, Shared Schema (tenant_id column)

En sade yanasma. Butun tenant-larin datalari eyni database-de, eyni table-larda saxlanilir. Her table-da `tenant_id` column olur.

```
users table:
| id | tenant_id | name       | email              |
|----|-----------|------------|--------------------|
| 1  | 1         | Orxan      | orxan@company1.com |
| 2  | 1         | Elvin      | elvin@company1.com |
| 3  | 2         | Nigar      | nigar@company2.com |
| 4  | 2         | Sevinc     | sevinc@company2.com|
```

**Ustunlukleri:**
- En az resurs istifadesi (bir database)
- Migration asandir
- Yeni tenant yaratmaq anliqdir

**Catismazliqlari:**
- Data leak riski var (tenant_id filter unutsaniz)
- Boyuk data olduqda performance problemi
- Bir tenant-in yuklu sorgusu basqalarini yavasladir

### 2. Shared Database, Separate Schema

Her tenant ucun ayri schema (PostgreSQL-de) yaradilir. Table strukturu eynidir, amma her biri oz namespace-indedir.

```
tenant_1.users
tenant_2.users
tenant_3.users
```

**Ustunlukleri:**
- Daha yaxsi izolasiya
- tenant_id filter lazim deyil
- Index-ler tenant-a ozeldir

**Catismazliqlari:**
- Schema sayi artdiqca idare etmek cetinlesir
- Migration her schema-ya ayri aparilmalidir

### 3. Separate Database per Tenant

Her tenant oz database-ine sahibdir.

```
database: tenant_company1
database: tenant_company2
database: tenant_company3
```

**Ustunlukleri:**
- Tam izolasiya
- Bir tenant-in datalari asanliqla backup/restore olunur
- Performance izolasiyasi

**Catismazliqlari:**
- En cox resurs istifade eden yanasma
- Database connection management cetindir
- Cross-tenant reporting mumkun deyil (ve ya cox cetindir)

---

## Laravel-de Multi-Tenancy -- Shared Database Yanasmasi

En populyar yanasma olan "Shared Database with tenant_id" uygulamasini etrafli gorek.

### Addim 1: Tenants Table ve Model

*Bu kod tenant-ları plan, slug və custom domain ilə saxlayan cədvəli yaradır:*

```php
// database/migrations/2026_01_01_000001_create_tenants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // Sirket adi
            $table->string('slug')->unique();    // URL ucun: company1
            $table->string('domain')->nullable()->unique(); // Custom domain
            $table->string('plan')->default('free'); // Abuneliq plani
            $table->json('settings')->nullable(); // Tenant-a ozel parametrler
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
```

*Bu kod tenant üçün plan limitlərini qaytaran yardımçı metodlarla Tenant modelini göstərir:*

```php
// app/Models/Tenant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'domain', 'plan', 'settings', 'is_active',
    ];

    protected $casts = [
        'settings'  => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Tenant-in aktiv olub olmadigini yoxla
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Tenant-in plan limitlerini al
     */
    public function getPlanLimits(): array
    {
        return config("plans.{$this->plan}", [
            'max_users'    => 5,
            'max_projects' => 10,
            'max_storage'  => 1024, // MB
        ]);
    }
}
```

### Addim 2: Users Table-a tenant_id elave et

*Bu kod users cədvəlinə tenant izolasiyası üçün `tenant_id` foreign key sütunu əlavə edir:*

```php
// database/migrations/2026_01_01_000002_add_tenant_id_to_users_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                  ->after('id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->index('tenant_id');
        });
    }
};
```

### Addim 3: Tenant Context (Singleton Service)

Hal-hazirda hansi tenant-in kontekstinde oldugumuzu saxlayan service:

*Bu kod cari tenant-ı singleton kimi saxlayan TenantContext service-ini göstərir:*

```php
// app/Services/TenantContext.php
namespace App\Services;

use App\Models\Tenant;
use RuntimeException;

class TenantContext
{
    private ?Tenant $currentTenant = null;

    /**
     * Cari tenant-i set et
     */
    public function set(Tenant $tenant): void
    {
        if (!$tenant->isActive()) {
            throw new RuntimeException("Tenant [{$tenant->slug}] is not active.");
        }

        $this->currentTenant = $tenant;
    }

    /**
     * Cari tenant-i al
     */
    public function get(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Cari tenant-in ID-sini al (ve ya exception at)
     */
    public function id(): int
    {
        if ($this->currentTenant === null) {
            throw new RuntimeException('No tenant has been set.');
        }

        return $this->currentTenant->id;
    }

    /**
     * Tenant set olunub ya yox
     */
    public function has(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Tenant kontekstini sifirla
     */
    public function forget(): void
    {
        $this->currentTenant = null;
    }
}
```

*Bu kod TenantContext-i bütün request boyunca eyni instance olaraq saxlamaq üçün singleton kimi qeydiyyatdan keçirir:*

```php
// app/Providers/AppServiceProvider.php
namespace App\Providers;

use App\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton kimi qeydiyyat -- butun request boyunca eyni instance
        $this->app->singleton(TenantContext::class);
    }
}
```

### Addim 4: Tenant Resolution Middleware

Tenant-i nece mueyyen edirik? Bir nece yol var:

#### Subdomain-based Tenant Resolution

*Bu kod subdomain-dən tenant-ı müəyyən edib TenantContext-ə təyin edən middleware-i göstərir:*

```php
// app/Http/Middleware/ResolveTenantFromSubdomain.php
namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveTenantFromSubdomain
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost(); // meselen: company1.myapp.com
        $baseDomain = config('app.base_domain'); // myapp.com

        // Subdomain-i cigar
        $subdomain = str_replace(".{$baseDomain}", '', $host);

        if ($subdomain === $host) {
            // Subdomain yoxdur -- esas sayt
            return $next($request);
        }

        // Tenant-i tap
        $tenant = Tenant::where('slug', $subdomain)
            ->orWhere('domain', $host) // Custom domain desteyi
            ->first();

        if (!$tenant) {
            throw new NotFoundHttpException('Tenant tapilmadi.');
        }

        if (!$tenant->isActive()) {
            abort(403, 'Bu hesab deaktiv edilib.');
        }

        // Tenant kontekstini set et
        $this->tenantContext->set($tenant);

        return $next($request);
    }
}
```

#### Header-based Tenant Resolution (API ucun)

*Bu kod `X-Tenant` request header-indən tenant-ı tapan API middleware-ini göstərir:*

```php
// app/Http/Middleware/ResolveTenantFromHeader.php
namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHeader
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = $request->header('X-Tenant');

        if (!$tenantSlug) {
            return response()->json([
                'error' => 'X-Tenant header teleb olunur.',
            ], 400);
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'error' => 'Tenant tapilmadi ve ya aktiv deyil.',
            ], 404);
        }

        $this->tenantContext->set($tenant);

        return $next($request);
    }
}
```

#### Auth-based Tenant Resolution (User login-den sonra)

*Bu kod autentifikasiya olmuş istifadəçinin tenant-ını context-ə təyin edən middleware-i göstərir:*

```php
// app/Http/Middleware/ResolveTenantFromAuth.php
namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromAuth
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant) {
            $this->tenantContext->set($user->tenant);
        }

        return $next($request);
    }
}
```

### Addim 5: Tenant-Aware Models -- Global Scope

En muhum hisse! Her model avtomatik olaraq yalniz cari tenant-in datalarini gostermelidir.

*Bu kod bütün Eloquent query-lərə avtomatik `tenant_id` filteri əlavə edən global scope-u göstərir:*

```php
// app/Models/Scopes/TenantScope.php
namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Butun query-lere avtomatik tenant_id filter elave et
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->has()) {
            $builder->where(
                $model->qualifyColumn('tenant_id'),
                $tenantContext->id()
            );
        }
    }
}
```

*$tenantContext->id() üçün kod nümunəsi:*
```php
// app/Models/Concerns/BelongsToTenant.php
namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Model boot olunanda TenantScope elave et
     */
    protected static function bootBelongsToTenant(): void
    {
        // Butun query-lere avtomatik filter
        static::addGlobalScope(new TenantScope());

        // Yeni record yarananda avtomatik tenant_id set et
        static::creating(function ($model) {
            $tenantContext = app(TenantContext::class);

            if ($tenantContext->has() && !$model->tenant_id) {
                $model->tenant_id = $tenantContext->id();
            }
        });
    }

    /**
     * Tenant relation
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Indi bu trait-i butun tenant-a aid model-lere elave edirik:

*Bu kod `BelongsToTenant` trait-ini istifadə edərək tenant izolasiyasını avtomatik tətbiq edən model nümunəsini göstərir:*

```php
// app/Models/Project.php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'description', 'status'];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
```

*return $this->hasMany(Task::class); üçün kod nümunəsi:*
```php
// app/Models/Task.php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use BelongsToTenant;

    protected $fillable = ['project_id', 'title', 'completed'];
}
```

Indi her yerde avtomatik filter isleyir:

*Bu kod global scope sayəsində controller-də heç bir tenant filteri yazmadan avtomatik izolasiya işlədiyini göstərir:*

```php
// Controller-de hec bir tenant filter yazmaga ehtiyac yoxdur!
// Global Scope avtomatik WHERE tenant_id = ? elave edir.

$projects = Project::with('tasks')->paginate(20);
// SQL: SELECT * FROM projects WHERE tenant_id = 1 LIMIT 20

$task = Task::findOrFail($taskId);
// SQL: SELECT * FROM tasks WHERE tenant_id = 1 AND id = 5
// Basqa tenant-in task-ini tapa bilmez!
```

### Addim 6: Tenant-Aware Caching

Cache key-lere tenant prefix elave etmeliyik, yoxsa farkli tenant-lar eyni cache-i paylasacaq:

*Bu kod cache key-lərinə tenant prefix əlavə edərək tenant-lar arası cache sızmasını önləyən service-i göstərir:*

```php
// app/Services/TenantCacheManager.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TenantCacheManager
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    /**
     * Tenant prefix-li cache key yarat
     */
    private function key(string $key): string
    {
        $tenantId = $this->tenantContext->id();
        return "tenant_{$tenantId}:{$key}";
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return Cache::put($this->key($key), $value, $ttl);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($this->key($key), $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->key($key));
    }

    /**
     * Tenant-in butun cache-ini temizle
     */
    public function flush(): void
    {
        // Tag-based cache istifade etmek daha yaxsidir
        Cache::tags(["tenant_{$this->tenantContext->id()}"])->flush();
    }
}
```

### Addim 7: Tenant-Aware Queues

Queue job-lari islenende tenant konteksti itir. Buna gore job-a tenant_id elave etmeliyik:

*Bu kod queue job-larında tenant kontekstini saxlayan və bərpa edən `TenantAware` trait-ini göstərir:*

```php
// app/Jobs/Concerns/TenantAware.php
namespace App\Jobs\Concerns;

use App\Models\Tenant;
use App\Services\TenantContext;

trait TenantAware
{
    public int $tenantId;

    /**
     * Job yarananda cari tenant-i yadda saxla
     */
    public function initializeTenantAware(): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->has()) {
            $this->tenantId = $tenantContext->id();
        }
    }

    /**
     * Job islenende tenant kontekstini berpa et
     */
    public function restoreTenantContext(): void
    {
        if (isset($this->tenantId)) {
            $tenant = Tenant::findOrFail($this->tenantId);
            app(TenantContext::class)->set($tenant);
        }
    }
}
```

*app(TenantContext::class)->set($tenant); üçün kod nümunəsi:*
```php
// app/Jobs/GenerateProjectReport.php
namespace App\Jobs;

use App\Jobs\Concerns\TenantAware;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProjectReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use SerializesModels, TenantAware;

    public function __construct(
        public int $projectId,
    ) {
        $this->initializeTenantAware();
    }

    public function handle(): void
    {
        // Tenant kontekstini berpa et
        $this->restoreTenantContext();

        // Indi Global Scope duzgun isleyir
        $project = Project::with('tasks')->findOrFail($this->projectId);

        // Report yaratma mentiqi...
    }
}
```

### Addim 8: Tenant-Aware File Storage

*Addim 8: Tenant-Aware File Storage üçün kod nümunəsi:*
```php
// app/Services/TenantStorageManager.php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantStorageManager
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    /**
     * Tenant-in storage path-ini al
     */
    private function tenantPath(string $path = ''): string
    {
        $tenantId = $this->tenantContext->id();
        return "tenants/{$tenantId}" . ($path ? "/{$path}" : '');
    }

    /**
     * Fayl yukle
     */
    public function upload(UploadedFile $file, string $directory = 'uploads'): string
    {
        $path = $this->tenantPath($directory);
        return Storage::disk('s3')->putFile($path, $file);
    }

    /**
     * Fayl sil
     */
    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($this->tenantPath($path));
    }

    /**
     * Tenant-in istifade etdiyi storage hecmini hesabla
     */
    public function getUsedStorage(): int
    {
        $files = Storage::disk('s3')->allFiles($this->tenantPath());
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += Storage::disk('s3')->size($file);
        }

        return $totalSize;
    }
}
```

---

## Separate Database Yanasmasi (Stancl/Tenancy ilə)

Daha ciddi izolasiya lazim olduqda `stancl/tenancy` paketi ile ayri database yanasmasi:

*Daha ciddi izolasiya lazim olduqda `stancl/tenancy` paketi ile ayri da üçün kod nümunəsi:*
```bash
composer require stancl/tenancy
php artisan tenancy:install
```

*php artisan tenancy:install üçün kod nümunəsi:*
```php
// config/tenancy.php -- Esas konfiqurasiya
return [
    'tenant_model' => \App\Models\Tenant::class,

    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    // Central (paylasilan) domain-ler
    'central_domains' => [
        'myapp.com',
        'www.myapp.com',
    ],

    // Tenancy bootstrap-lari -- neler avtomatik deyissin
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'template_tenant_connection' => 'tenant',
        'prefix' => 'tenant_',
        'suffix' => '',
    ],
];
```

*'prefix' => 'tenant_', üçün kod nümunəsi:*
```php
// app/Models/Tenant.php -- Stancl Tenancy ile
namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Tenant yarananda avtomatik elave olunan data
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'plan',
            'is_active',
        ];
    }
}
```

Yeni tenant yaratmaq:

*Yeni tenant yaratmaq üçün kod nümunəsi:*
```php
// Yeni tenant ve onun database-ini yarat
$tenant = Tenant::create([
    'id'   => 'company1',
    'name' => 'Company One',
    'plan' => 'premium',
]);

// Domain bagla
$tenant->domains()->create(['domain' => 'company1.myapp.com']);

// Tenant database-inde migration-lari islet
Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

// Tenant database-ine seed at
Artisan::call('tenants:seed', ['--tenants' => [$tenant->id]]);
```

---

## Migration Strategiyasi

### Shared Database ucun Migration

*Shared Database ucun Migration üçün kod nümunəsi:*
```php
// Her yeni table-da tenant_id olmalidir
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 10, 2);
    $table->string('status'); // draft, sent, paid
    $table->timestamps();

    // Composite index -- tenant_id her zaman ilk olmalidir
    $table->index(['tenant_id', 'status']);
    $table->index(['tenant_id', 'created_at']);
});
```

### Separate Database ucun Migration

*Separate Database ucun Migration üçün kod nümunəsi:*
```bash
# Butun tenant-larin database-lerinde migration islet
php artisan tenants:migrate

# Yalniz bir tenant ucun
php artisan tenants:migrate --tenants=company1

# Rollback
php artisan tenants:rollback --tenants=company1
```

---

## Data Isolation ve Security

### Test: Tenant Isolation-u yoxla

*Test: Tenant Isolation-u yoxla üçün kod nümunəsi:*
```php
// tests/Feature/TenantIsolationTest.php
namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_only_see_own_projects(): void
    {
        // Iki farkli tenant yarat
        $tenant1 = Tenant::factory()->create(['slug' => 'company1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'company2']);

        // Her birine project yarat
        $project1 = Project::factory()->create([
            'tenant_id' => $tenant1->id,
            'name'      => 'Tenant 1 Project',
        ]);
        $project2 = Project::factory()->create([
            'tenant_id' => $tenant2->id,
            'name'      => 'Tenant 2 Project',
        ]);

        // Tenant 1 kontekstinde isleyek
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant1);

        // Tenant 1 yalniz oz project-ini gormelidir
        $projects = Project::all();

        $this->assertCount(1, $projects);
        $this->assertEquals('Tenant 1 Project', $projects->first()->name);
    }

    public function test_tenant_cannot_access_other_tenants_data(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $otherProject = Project::factory()->create([
            'tenant_id' => $tenant2->id,
        ]);

        // Tenant 1 kontekstinde Tenant 2-nin project-ini tapa bilmesin
        app(TenantContext::class)->set($tenant1);

        $this->assertNull(Project::find($otherProject->id));
    }

    public function test_new_record_automatically_gets_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $project = Project::create([
            'name'        => 'Auto Tenant Project',
            'description' => 'Test',
        ]);

        $this->assertEquals($tenant->id, $project->tenant_id);
    }
}
```

---

## Yekun: Hansi Yanasmani Secmeli?

| Kriteriya                  | Shared DB + tenant_id | Separate Schema | Separate DB |
|----------------------------|-----------------------|-----------------|-------------|
| Qurasma murakkebliyi       | Asagi                 | Orta            | Yuksek      |
| Data izolasiyasi           | Zeif                  | Orta            | Guclu       |
| Performance izolasiyasi    | Yox                   | Qismen          | Tam         |
| Resurs istifadesi          | Az                    | Orta            | Cox         |
| Tenant sayi limiti         | Limitsiz              | ~1000           | ~100-500    |
| Tenant-a ozel backup       | Cetin                 | Mumkun          | Asan        |
| Cross-tenant reporting     | Asan                  | Cetin           | Cox cetin   |

**Tovsiye:**
- Kicik/orta SaaS, cox tenant: **Shared DB + tenant_id**
- Orta SaaS, data izolasiyasi vacib: **Separate Schema**
- Enterprise SaaS, compliance teleb olunur: **Separate Database**

Bu pattern SaaS interview-lerinde en cox sorusulan movzulardan biridir. Tenant isolation-u hem teknik, hem de biznes perspektivinden izah ede bilmek vacibdir.

---

## Interview Sualları və Cavablar

**S: Shared DB vs Separate DB — nə zaman hansını seçərsiniz?**
C: Shared DB + tenant_id: startup, çoxlu tenant (1000+), az resurs, tenant məlumatlarının tamamilə izole olması kritik deyil. Separate DB: enterprise müştərilər, compliance tələbləri (HIPAA, PCI), tenant-a özel backup/restore, performance izolasiyası lazımdır. Əksər SaaS başlangıcları shared DB ilə başlayır, böyüdükcə separate DB-yə keçir.

**S: Global scope tenant_id filtrini unutsanız nə baş verər?**
C: Data leak — bir tenant başqa tenantin məlumatlarını görür. Bu kritik təhlükəsizlik problemidir. Buna qarşı: (1) bütün tenant modellərinə `HasTenant` trait mütləq əlavə olunur, (2) CI/CD-də integration testlər cross-tenant leak yoxlayır, (3) Code review checklist-ə daxil edilir.

**S: Background job-da tenant konteksti necə saxlanılır?**
C: HTTP request-dən dispatch edilən job-da `TenantContext` HTTP middleware tərəfindən set edilir, amma job worker-ında bu middleware yoxdur. Həll: job constructor-a `tenant_id` pass et, `handle()` metodunun əvvəlində `TenantContext::set()` çağır. Bu mütləq edilməlidir, yoxsa bütün tenant-ların datası görünür.

**S: Tenant-a özel konfiqurasiya (settings) necə idarə edilir?**
C: `tenants` cədvəlindəki `settings` JSON column — tenant-a özel feature flags, branding, limitleri saxlayır. Cache-lənir (Redis, 1 saatlıq TTL) — hər request-də DB sorğusu lazım deyil. `$tenant->getSetting('max_users', default: 10)` kimi helper metod istifadəsi tövsiyə edilir.

**S: Tenant subdomain routing necə işləyir?**
C: `company1.app.com` → middleware subdomain-i oxuyur (`company1`) → tenant-ı DB-dən/cache-dən tap → `TenantContext::set($tenant)`. Custom domain-lər: `company1.com` → `domains` cədvəlindən tenant-ı tap. Wildcard SSL sertifikatı (*.app.com) lazımdır.

---

## Anti-patterns

**1. Tenant_id global scope-u unutmaq**
Bir model-ə `HasTenant` trait-i əlavə etməyi unutmaq → başqa tenant-ın datasını göstərmək (data leak). Bütün tenant-a məxsus modellər trait ilə qorunmalıdır, integration test-lərlə cross-tenant leak yoxlanmalıdır.

**2. Background job-da tenant context itirmək**
HTTP request-dən dispatch edilən job-da `TenantContext` yoxdur — tenant_id scope işləmir, bütün tenant-ların datası görünür. Job-a tenant_id pass et, handle()-də `TenantContext::set()` çağır.

**3. Shared DB-də tenant_id indeksi yoxdur**
`SELECT * FROM orders WHERE tenant_id = 42` — full table scan. Hər cədvəldə `tenant_id` indeksi (yaxud composite index) mütləqdir.

**4. Tenant-a məxsus migration-ları unutmaq**
Separate DB strategiyasında yeni migration yalnız central DB-ə run edilir, tenant DB-ləri köhnə schema ilə qalır. `tenants:migrate` command bütün tenant DB-lərini güncəlləməlidir.

**5. Tenant context-i clear etməmək**
Long-running PHP process-lərdə (Octane, Swoole) bir request-dən sonra TenantContext clear edilməzsə, növbəti request əvvəlki tenant kontekstindən istifadə edir.
