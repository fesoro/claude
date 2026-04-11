# Audit Log / Activity Log Sistemi Dizayni

## Problem

Sistemde kimin, ne vaxt, ne etdiyini izlemek lazimdir. Bu hem tehlukesizlik, hem compliance (GDPR, SOX, HIPAA), hem de debugging ucun vacibdir.

Meselen:
- Admin bir user-i sildi -- kim sildi? Ne vaxt? Niye?
- Musterinin odemeleri deyisdi -- kim deyisdi? Evvelki deyer ne idi?
- Sistem audit-i zamani butun data deyisikliklerinin tarixcesini gostermek lazimdir

Bu **audit logging** problemidir.

### Problem niyə yaranır?

Audit olmadan "müştərinin bakiyyəsi kiminsə tərəfindən azaldıldı" kimi şikayətlərə cavab vermək mümkün deyil. Tipik ssenari: admin panel-də bir user başqa bir istifadəçinin planını pulsuz etdi — nə sistemdə iz var, nə də kim etdiyini bilmək mümkündür. GDPR, SOX, HIPAA kimi compliance tələbləri audit trail-i qanuni öhdəlik edir. Bug tracking-də: "data nə vaxt, necə belə oldu?" sualına yalnız audit log ilə cavab vermək mümkündür.

---

## Audit Log Nedir?

Audit log -- sistemdeki butun vacib emeliyyatlarin qeydidir. Her qeyd asagidakilari saxlayir:

- **Kim?** -- Emeliyyati eden user
- **Ne vaxt?** -- Daqiq tarix ve saat
- **Ne etdi?** -- Yaratdi, deyisdi, sildi
- **Neyi?** -- Hansi model/resurs
- **Evvelki deyer** -- Deyisiklikden evvel
- **Yeni deyer** -- Deyisiklikden sonra
- **Harada?** -- IP adres, user agent, URL

---

## Database Design

### Audit Logs Table

*Bu kod kim-nə vaxt-nə etdi məlumatlarını, IP, URL və dəyişiklikləri saxlayan audit_logs cədvəlini yaradır:*

```php
// database/migrations/2026_01_01_000001_create_audit_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Kim etdi?
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_type')->default('user'); // user, admin, system, api

            // Ne etdi?
            $table->string('action'); // created, updated, deleted, restored, login, etc.

            // Neyi?
            $table->string('auditable_type'); // App\Models\Order
            $table->unsignedBigInteger('auditable_id');

            // Deyisiklikler
            $table->json('old_values')->nullable(); // Evvelki deyerler
            $table->json('new_values')->nullable(); // Yeni deyerler

            // Kontekst
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable(); // GET, POST, PUT, DELETE
            $table->json('metadata')->nullable(); // Elave melumat

            $table->timestamp('created_at')->useCurrent();

            // Index-ler -- axtaris performance ucun
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['auditable_type', 'action', 'created_at']);
        });
    }
};
```

### AuditLog Model

*Bu kod morph relation ilə istənilən model-ə bağlana bilən AuditLog Eloquent modelini göstərir:*

```php
// app/Models/AuditLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false; // Yalniz created_at istifade edirik

    protected $fillable = [
        'user_id', 'user_type', 'action',
        'auditable_type', 'auditable_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent', 'url', 'method',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Emeliyyati eden user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Audit olunan model (polymorphic)
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Deyisen field-lerin siyahisi
     */
    public function getChangedFieldsAttribute(): array
    {
        if ($this->action !== 'updated' || !$this->old_values || !$this->new_values) {
            return [];
        }

        return array_keys($this->new_values);
    }

    /**
     * Insan oxuya bilecek format
     */
    public function getDescriptionAttribute(): string
    {
        $userName = $this->user?->name ?? 'Sistem';
        $modelName = class_basename($this->auditable_type);
        $modelId = $this->auditable_id;

        return match ($this->action) {
            'created'  => "{$userName} yeni {$modelName} #{$modelId} yaratdi.",
            'updated'  => "{$userName} {$modelName} #{$modelId} yeniledi.",
            'deleted'  => "{$userName} {$modelName} #{$modelId} sildi.",
            'restored' => "{$userName} {$modelName} #{$modelId} berpa etdi.",
            'login'    => "{$userName} sisteme daxil oldu.",
            'logout'   => "{$userName} sistemden cixdi.",
            default    => "{$userName} {$this->action} emeliyyati etdi ({$modelName} #{$modelId}).",
        };
    }
}
```

---

## Custom Audit Trait

Model-lere elave olunan trait -- butun deyisiklikleri avtomatik izleyir:

*Bu kod model created/updated/deleted hadisələrini avtomatik izləyən `Auditable` trait-ini göstərir:*

```php
// app/Models/Concerns/Auditable.php
namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Audit olunmamali field-ler
     * Model-de override ede bilersiniz:
     * protected array $auditExclude = ['password', 'remember_token'];
     */
    protected function getAuditExcludedFields(): array
    {
        return property_exists($this, 'auditExclude')
            ? $this->auditExclude
            : ['password', 'remember_token', 'two_factor_secret'];
    }

    /**
     * Yalniz bu field-leri audit et (set olunubsa)
     */
    protected function getAuditIncludedFields(): ?array
    {
        return property_exists($this, 'auditInclude')
            ? $this->auditInclude
            : null;
    }

    /**
     * Model boot olunanda observer-leri qeydiyyat et
     */
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAudit('created', [], $model->getAuditableAttributes());
        });

        static::updated(function ($model) {
            $original = $model->getAuditableOriginal();
            $changed  = $model->getAuditableChanges();

            if (!empty($changed)) {
                $model->logAudit('updated', $original, $changed);
            }
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getAuditableAttributes(), []);
        });

        // SoftDeletes istifade olunursa
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->logAudit('restored', [], $model->getAuditableAttributes());
            });
        }
    }

    /**
     * Bu model-in audit log-lari
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')
            ->orderByDesc('created_at');
    }

    /**
     * Audit log yaz
     */
    protected function logAudit(string $action, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'user_type'      => Auth::check() ? 'user' : 'system',
            'action'         => $action,
            'auditable_type' => get_class($this),
            'auditable_id'   => $this->getKey(),
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            'url'            => Request::fullUrl(),
            'method'         => Request::method(),
        ]);
    }

    /**
     * Audit ucun uygun attribute-lari al (exclude/include filter ile)
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        return $this->filterAuditFields($attributes);
    }

    /**
     * Deyismis field-lerin evvelki deyerlerini al
     */
    protected function getAuditableOriginal(): array
    {
        $dirty = $this->getDirty();
        $original = [];

        foreach (array_keys($dirty) as $key) {
            $original[$key] = $this->getOriginal($key);
        }

        return $this->filterAuditFields($original);
    }

    /**
     * Deyismis field-lerin yeni deyerlerini al
     */
    protected function getAuditableChanges(): array
    {
        return $this->filterAuditFields($this->getDirty());
    }

    /**
     * Hassas field-leri filter et
     */
    protected function filterAuditFields(array $fields): array
    {
        $excluded = $this->getAuditExcludedFields();
        $included = $this->getAuditIncludedFields();

        if ($included !== null) {
            $fields = array_intersect_key($fields, array_flip($included));
        }

        return array_diff_key($fields, array_flip($excluded));
    }
}
```

### Model-lerde Istifade

*Bu kod `Auditable` trait-ini model-ə əlavə edərək avtomatik audit logging-i aktiv edən nümunəni göstərir:*

```php
// app/Models/Order.php
namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id', 'product_id', 'quantity', 'total', 'status',
    ];

    // Bu field-ler audit olunmayacaq
    protected array $auditExclude = ['updated_at'];
}
```

*protected array $auditExclude = ['updated_at']; üçün kod nümunəsi:*
```php
// app/Models/User.php
namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Auditable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    // Hassas field-ler audit-den xaric
    protected array $auditExclude = [
        'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes',
    ];
}
```

---

## Queued Audit Logging (Performance)

Audit log yazmaq I/O emeliyyatidir. Yuksek yuklu sistemlerde bu, esas emeliyyati yavasladira biler. Hellli: queue istifade et.

*Bu kod audit log yazmağı ayrı queue-ya göndərərək əsas əməliyyatı yavaşlatmadan asinxron izləməni göstərir:*

```php
// app/Jobs/WriteAuditLog.php
namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class WriteAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private array $auditData,
    ) {
        $this->onQueue('audit'); // Ayri queue -- esas queue-nu bloklama
    }

    public function handle(): void
    {
        AuditLog::create($this->auditData);
    }
}
```

Trait-de sinxron emeliyyat evezine job dispatch et:

*Bu kod config-ə görə audit log-unu ya sinxron, ya da asinxron queue-ya göndərən yenilənmiş `logAudit` metodunu göstərir:*

```php
// Auditable trait-de logAudit metodunu deyis
protected function logAudit(string $action, array $oldValues, array $newValues): void
{
    $data = [
        'user_id'        => Auth::id(),
        'user_type'      => Auth::check() ? 'user' : 'system',
        'action'         => $action,
        'auditable_type' => get_class($this),
        'auditable_id'   => $this->getKey(),
        'old_values'     => $oldValues ?: null,
        'new_values'     => $newValues ?: null,
        'ip_address'     => Request::ip(),
        'user_agent'     => Request::userAgent(),
        'url'            => Request::fullUrl(),
        'method'         => Request::method(),
    ];

    // Async yazma -- esas emeliyyati yavaslatmir
    if (config('audit.async', false)) {
        WriteAuditLog::dispatch($data);
    } else {
        AuditLog::create($data);
    }
}
```

---

## Spatie/Laravel-Activitylog Paketi

Hazir paket ile daha suretli baslangic:

*Hazir paket ile daha suretli baslangic üçün kod nümunəsi:*
```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

### Model-de Istifade

*Bu kod `spatie/laravel-activitylog` paketini model-ə əlavə edib hansı sahələrin izləniləcəyini konfiqurasiya edir:*

```php
// app/Models/Article.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Article extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'content', 'status', 'author_id'];

    /**
     * Activity log parametrleri
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'content', 'status'])   // Yalniz bu field-leri izle
            ->logOnlyDirty()                              // Yalniz deyisen field-leri log et
            ->dontSubmitEmptyLogs()                       // Deyisiklik yoxdursa log yazma
            ->setDescriptionForEvent(function (string $eventName) {
                return match ($eventName) {
                    'created' => 'Meqale yaradildi',
                    'updated' => 'Meqale yenilendi',
                    'deleted' => 'Meqale silindi',
                    default   => "Meqale: {$eventName}",
                };
            });
    }
}
```

### Manual Log Yazmaq

*Bu kod spatie paketinin fluent API-si ilə modellə əlaqəli ətraflı audit log yazmasını göstərir:*

```php
use Spatie\Activitylog\Facades\Activity;

// Sade log
activity()
    ->log('User admin panelinə daxil oldu.');

// Model ile elaqeli log
activity()
    ->performedOn($order)
    ->causedBy($user)
    ->withProperties([
        'old_status' => 'pending',
        'new_status' => 'shipped',
        'tracking'   => 'AZ123456789',
    ])
    ->log('Sifarişin statusu dəyişdirildi.');

// Farkli log adi ile (ayri-ayri izlemek ucun)
activity('payment')
    ->performedOn($payment)
    ->causedBy($user)
    ->withProperties([
        'amount'   => 150.00,
        'currency' => 'AZN',
    ])
    ->log('Odeme qaytarildi (refund).');
```

### Log-lari Oxumaq

*Bu kod activity log qeydlərini model, user və tarixə görə filtrləməyi göstərir:*

```php
// Son 20 aktivlik
$activities = Activity::latest()->take(20)->get();

foreach ($activities as $activity) {
    echo $activity->description;          // "Meqale yaradildi"
    echo $activity->causer->name;         // "Orxan"
    echo $activity->subject;              // Article model instance
    echo $activity->properties['old'];    // Evvelki deyerler
    echo $activity->properties['attributes']; // Yeni deyerler
    echo $activity->created_at;
}

// Mueyyen model-in tarixcesi
$articleLogs = Activity::forSubject($article)->get();

// Mueyyen user-in emeliyyatlari
$userActions = Activity::causedBy($user)->get();
```

---

## Audit Log Search ve Filtering

Admin panel ucun audit log axtaris sistemi:

*Bu kod admin panel üçün çoxlu filterlər dəstəkləyən audit log axtarış controller-ini göstərir:*

```php
// app/Http/Controllers/Admin/AuditLogController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->orderByDesc('created_at');

        // User-e gore filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Action-a gore filter
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Model type-a gore filter
        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->model_type);
        }

        // Mueyyen model instance
        if ($request->filled('model_id')) {
            $query->where('auditable_id', $request->model_id);
        }

        // Tarix araligina gore filter
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Deyisen field-e gore axtaris (JSON icinde)
        if ($request->filled('field_name')) {
            $field = $request->field_name;
            $query->where(function ($q) use ($field) {
                $q->whereJsonContainsKey("old_values->{$field}")
                  ->orWhereJsonContainsKey("new_values->{$field}");
            });
        }

        // Serbest metn axtarisi (deyerler icinde)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('old_values', 'LIKE', "%{$search}%")
                  ->orWhere('new_values', 'LIKE', "%{$search}%")
                  ->orWhere('metadata', 'LIKE', "%{$search}%");
            });
        }

        // IP adrese gore
        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        $logs = $query->paginate(50);

        return view('admin.audit-logs.index', compact('logs'));
    }

    /**
     * Bir model-in tam tarixcesi
     */
    public function history(string $modelType, int $modelId)
    {
        $fullModelType = "App\\Models\\{$modelType}";

        $logs = AuditLog::where('auditable_type', $fullModelType)
            ->where('auditable_id', $modelId)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.audit-logs.history', [
            'logs'       => $logs,
            'modelType'  => $modelType,
            'modelId'    => $modelId,
        ]);
    }

    /**
     * User-in butun emeliyyatlari
     */
    public function userActivity(int $userId)
    {
        $logs = AuditLog::where('user_id', $userId)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.audit-logs.user-activity', compact('logs'));
    }
}
```

---

## Event Sourcing ile Audit

Event Sourcing -- datanin cari halini saxlamaq evezine, butun deyisiklikleri (event-leri) saxlamaqdir. Bu, en guclu audit log formasidir.

*Event Sourcing -- datanin cari halini saxlamaq evezine, butun deyisikl üçün kod nümunəsi:*
```php
// app/Events/OrderEvents/OrderCreated.php
namespace App\Events\OrderEvents;

class OrderCreated
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly int $productId,
        public readonly int $quantity,
        public readonly float $total,
        public readonly string $occurredAt,
    ) {}

    public function toArray(): array
    {
        return [
            'order_id'    => $this->orderId,
            'user_id'     => $this->userId,
            'product_id'  => $this->productId,
            'quantity'    => $this->quantity,
            'total'       => $this->total,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
```

*'occurred_at' => $this->occurredAt, üçün kod nümunəsi:*
```php
// app/Events/OrderEvents/OrderStatusChanged.php
namespace App\Events\OrderEvents;

class OrderStatusChanged
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?int $changedBy,
        public readonly ?string $reason,
        public readonly string $occurredAt,
    ) {}
}
```

*public readonly string $occurredAt, üçün kod nümunəsi:*
```php
// database/migrations/2026_01_01_000001_create_event_store_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_store', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('aggregate_type');  // Order, User, Payment
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type');      // OrderCreated, OrderStatusChanged
            $table->json('payload');           // Event data
            $table->json('metadata')->nullable(); // User, IP, etc.
            $table->unsignedBigInteger('version'); // Aggregate version (optimistic lock)
            $table->timestamp('occurred_at');

            $table->index(['aggregate_type', 'aggregate_id', 'version']);
            $table->index('event_type');
            $table->index('occurred_at');
        });
    }
};
```

*$table->index('occurred_at'); üçün kod nümunəsi:*
```php
// app/Services/EventStore.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventStore
{
    /**
     * Event-i saxla
     */
    public function append(
        string $aggregateType,
        int $aggregateId,
        string $eventType,
        array $payload,
        int $expectedVersion,
        ?array $metadata = null
    ): void {
        $nextVersion = $expectedVersion + 1;

        try {
            DB::table('event_store')->insert([
                'event_id'       => Str::uuid()->toString(),
                'aggregate_type' => $aggregateType,
                'aggregate_id'   => $aggregateId,
                'event_type'     => $eventType,
                'payload'        => json_encode($payload),
                'metadata'       => $metadata ? json_encode($metadata) : null,
                'version'        => $nextVersion,
                'occurred_at'    => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Version conflict -- basqasi evvel yazmis (optimistic locking)
            throw new \RuntimeException(
                "Concurrency conflict: {$aggregateType}#{$aggregateId} v{$nextVersion}"
            );
        }
    }

    /**
     * Aggregate-in butun event-lerini al
     */
    public function getEvents(string $aggregateType, int $aggregateId): array
    {
        return DB::table('event_store')
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get()
            ->map(fn ($row) => [
                'event_type'  => $row->event_type,
                'payload'     => json_decode($row->payload, true),
                'metadata'    => json_decode($row->metadata, true),
                'version'     => $row->version,
                'occurred_at' => $row->occurred_at,
            ])
            ->toArray();
    }

    /**
     * Son version nomresini al
     */
    public function getLatestVersion(string $aggregateType, int $aggregateId): int
    {
        return (int) DB::table('event_store')
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->max('version') ?? 0;
    }
}
```

Event replay ile sifarsin tam tarixcesini almaq:

*Event replay ile sifarsin tam tarixcesini almaq üçün kod nümunəsi:*
```php
// Order-in tam tarixcesini goster
$events = $eventStore->getEvents('Order', $orderId);

foreach ($events as $event) {
    echo "[{$event['occurred_at']}] {$event['event_type']}: ";
    print_r($event['payload']);
}

// Output:
// [2026-01-15 10:00:00] OrderCreated: {user_id: 1, product_id: 5, quantity: 2, total: 100}
// [2026-01-15 10:05:00] OrderStatusChanged: {old: pending, new: confirmed}
// [2026-01-15 14:30:00] OrderStatusChanged: {old: confirmed, new: shipped, tracking: AZ123}
// [2026-01-16 09:00:00] OrderStatusChanged: {old: shipped, new: delivered}
```

---

## GDPR Compliance

GDPR (General Data Protection Regulation) -- Avropa data qoruma qanunu. Audit log-lar sexsi data saxlaya biler, bu da ozel diqqet teleb edir.

*GDPR (General Data Protection Regulation) -- Avropa data qoruma qanunu üçün kod nümunəsi:*
```php
// app/Services/GdprAuditService.php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GdprAuditService
{
    /**
     * Hassas field-ler -- bu deyerler anonymize olunmalidir
     */
    private array $sensitiveFields = [
        'email', 'phone', 'address', 'name',
        'credit_card', 'ssn', 'date_of_birth',
        'ip_address',
    ];

    /**
     * "Right to be Forgotten" -- User-in butun datalarini anonymize et
     */
    public function anonymizeUserData(User $user): void
    {
        Log::info("GDPR: User #{$user->id} data anonymization started.");

        DB::transaction(function () use ($user) {
            // 1. Audit log-larda user melumatlarini anonymize et
            AuditLog::where('user_id', $user->id)->update([
                'ip_address' => null,
                'user_agent' => null,
            ]);

            // 2. Audit log-larin icindeki hassas deyerleri temizle
            $logs = AuditLog::where('user_id', $user->id)->get();

            foreach ($logs as $log) {
                $log->update([
                    'old_values' => $this->anonymizeValues($log->old_values),
                    'new_values' => $this->anonymizeValues($log->new_values),
                ]);
            }

            // 3. User haqqinda olan audit log-lari da anonymize et
            $userLogs = AuditLog::where('auditable_type', User::class)
                ->where('auditable_id', $user->id)
                ->get();

            foreach ($userLogs as $log) {
                $log->update([
                    'old_values' => $this->anonymizeValues($log->old_values),
                    'new_values' => $this->anonymizeValues($log->new_values),
                ]);
            }

            Log::info("GDPR: User #{$user->id} data anonymization completed.");
        });
    }

    /**
     * Hassas deyerleri anonymize et
     */
    private function anonymizeValues(?array $values): ?array
    {
        if (!$values) {
            return null;
        }

        foreach ($this->sensitiveFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '[ANONYMIZED]';
            }
        }

        return $values;
    }

    /**
     * "Right to Access" -- User-in butun audit datalarini export et
     */
    public function exportUserAuditData(User $user): array
    {
        // User-in etdiyi emeliyyatlar
        $actionsPerformed = AuditLog::where('user_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($log) => [
                'date'       => $log->created_at->toIso8601String(),
                'action'     => $log->action,
                'model'      => class_basename($log->auditable_type),
                'model_id'   => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
            ])
            ->toArray();

        // User haqqinda olan emeliyyatlar
        $actionsOnUser = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($log) => [
                'date'        => $log->created_at->toIso8601String(),
                'action'      => $log->action,
                'performed_by'=> $log->user_id,
                'old_values'  => $log->old_values,
                'new_values'  => $log->new_values,
            ])
            ->toArray();

        return [
            'user_id'            => $user->id,
            'export_date'        => now()->toIso8601String(),
            'actions_performed'  => $actionsPerformed,
            'actions_on_profile' => $actionsOnUser,
        ];
    }
}
```

---

## Log Rotation ve Archiving

Audit log-lar zamanla cox boyuk ola biler. Kohneleri archive etmek lazimdir:

*Audit log-lar zamanla cox boyuk ola biler. Kohneleri archive etmek laz üçün kod nümunəsi:*
```php
// app/Console/Commands/ArchiveAuditLogs.php
namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive
        {--months=12 : Nece aydan kohne log-lari archive et}
        {--delete : Archive-den sonra sil}';

    protected $description = 'Kohne audit log-lari archive et';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $cutoffDate = now()->subMonths($months);

        $this->info("Archive edilecek: {$cutoffDate->toDateString()}-den evvelki log-lar");

        // Sayini goster
        $count = AuditLog::where('created_at', '<', $cutoffDate)->count();
        $this->info("Tapildi: {$count} audit log");

        if ($count === 0) {
            $this->info('Archive edilecek log yoxdur.');
            return self::SUCCESS;
        }

        // Batch-larda archive et (memory ucun)
        $batchSize = 5000;
        $archived = 0;

        AuditLog::where('created_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunk($batchSize, function ($logs) use (&$archived) {
                // JSON formatinda fayla yaz
                $date = now()->format('Y-m-d_H-i-s');
                $filename = "audit-archive/archive_{$date}_{$archived}.json";

                $data = $logs->map(fn ($log) => $log->toArray())->toArray();

                Storage::disk('s3')->put(
                    $filename,
                    json_encode($data, JSON_PRETTY_PRINT)
                );

                $archived += $logs->count();
                $this->info("Archive edildi: {$archived} log");
            });

        // Silme
        if ($this->option('delete')) {
            if ($this->confirm("Dogrudan {$count} kohne audit log silinsin?")) {
                // Batch-larda sil -- boyuk DELETE-den qacinmaq ucun
                $deleted = 0;
                while (true) {
                    $batchDeleted = AuditLog::where('created_at', '<', $cutoffDate)
                        ->limit($batchSize)
                        ->delete();

                    if ($batchDeleted === 0) {
                        break;
                    }

                    $deleted += $batchDeleted;
                    $this->info("Silindi: {$deleted} log");
                }

                $this->info("Toplam {$deleted} kohne audit log silindi.");
            }
        }

        $this->info('Archive tamamlandi.');
        return self::SUCCESS;
    }
}
```

*return self::SUCCESS; üçün kod nümunəsi:*
```php
// Scheduler-de ayda bir defe islet
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Her ayin 1-de, gece 3:00-da, 12 aydan kohne log-lari archive et
    $schedule->command('audit:archive --months=12 --delete')
        ->monthlyOn(1, '03:00')
        ->withoutOverlapping()
        ->onOneServer();
}
```

---

## Table Partitioning (Boyuk Sistemler ucun)

Milyonlarla audit log olduqda, table partitioning performance ucun vacibdir:

*Milyonlarla audit log olduqda, table partitioning performance ucun vac üçün kod nümunəsi:*
```sql
-- PostgreSQL-de ayliq partition
CREATE TABLE audit_logs (
    id BIGSERIAL,
    user_id BIGINT,
    action VARCHAR(50),
    auditable_type VARCHAR(255),
    auditable_id BIGINT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP NOT NULL
) PARTITION BY RANGE (created_at);

-- Her ay ucun partition yarat
CREATE TABLE audit_logs_2026_01 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');

CREATE TABLE audit_logs_2026_02 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');

CREATE TABLE audit_logs_2026_03 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-03-01') TO ('2026-04-01');
```

Laravel-de avtomatik partition yaratma:

*Laravel-de avtomatik partition yaratma üçün kod nümunəsi:*
```php
// app/Console/Commands/CreateAuditPartition.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateAuditPartition extends Command
{
    protected $signature = 'audit:create-partition {--months-ahead=3}';
    protected $description = 'Gelecek aylar ucun audit log partition yarat';

    public function handle(): int
    {
        $monthsAhead = (int) $this->option('months-ahead');

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $date = now()->addMonths($i);
            $year = $date->format('Y');
            $month = $date->format('m');
            $partitionName = "audit_logs_{$year}_{$month}";

            $startDate = $date->startOfMonth()->toDateString();
            $endDate = $date->copy()->addMonth()->startOfMonth()->toDateString();

            // Partition artiq var mi yoxla
            $exists = DB::selectOne(
                "SELECT 1 FROM pg_tables WHERE tablename = ?",
                [$partitionName]
            );

            if ($exists) {
                $this->info("{$partitionName} artiq movcuddur.");
                continue;
            }

            DB::statement("
                CREATE TABLE {$partitionName} PARTITION OF audit_logs
                FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')
            ");

            $this->info("{$partitionName} yaradildi.");
        }

        return self::SUCCESS;
    }
}
```

---

## Yekun: Audit Log Sistemi Checklisti

| Element                        | Status | Qeyd                                      |
|--------------------------------|--------|--------------------------------------------|
| Database schema                | Vacib  | Uygun index-ler ile                        |
| Avtomatik model tracking       | Vacib  | Trait ve ya Observer ile                    |
| Old/New value saxlama          | Vacib  | Neler deyisdiyini gormek ucun              |
| User ve IP tracking            | Vacib  | Kim ve haradandan                          |
| Hassas data exclude            | Vacib  | Sifre, kredit kart qeyd olunmamali        |
| Queued logging                 | Vacib  | Performance ucun async yazma               |
| Search ve filtering            | Vacib  | Admin panel ucun                           |
| GDPR compliance                | Vacib  | Anonymize ve export imkani                 |
| Log rotation/archiving         | Vacib  | Disk/storage idaresi ucun                  |
| Table partitioning             | Boyuk sistemler | Milyonlarla qeyd olduqda          |
| Event sourcing                 | Xususi hallar | Tam tarixce lazim olduqda            |

**Tovsiye:** Kicik/orta layihelerde `spatie/laravel-activitylog` ile baslayin. Boyuk ve ya compliance-li layihelerde custom Auditable trait + queued logging + partitioning istifade edin. Event sourcing yalniz tam tarixce ve replay lazim olduqda secin.

---

## Interview Sualları və Cavablar

**S: Audit log sinxron yazılmalıdır, yoxsa asinxron? Trade-off-ları nədir?**
C: Sinxron: data consistency tam — əməliyyatla eyni transaction-da yazılır, heç bir log itirilmir. Dezavantaj: DB yazma latency-ni artırır. Asinxron (queue): performance yaxşıdır, request bloklanmır. Dezavantaj: job fail olsa log itirilə bilər. Kritik compliance sistemlərində (SOX, HIPAA) sinxron tövsiyə edilir; adi audit üçün asinxron daha praktikdir. Kompromis: sync DB yazma + async background enrichment.

**S: GDPR-a görə audit log-ları necə idarə etmək lazımdır?**
C: GDPR "right to erasure" tələb edir, amma audit trail-i silmək compliance pozuntusudur. Həll: audit log-da birbaşa PII saxlamaq yerinə `user_id` saxla. User silinəndə loqda `user_id` null et (anonymize), amma əməliyyat tarixi qalsın. Əgər mütləq PII lazımdırsa, ayrı şifrəli cədvəldə saxla, GDPR silmə zamanı yalnız oranı sil.

**S: Audit log cədvəli milyonlarla sətirə çatanda nə olur?**
C: Index-lər belə yavaşlaya bilər. Həll yolları: (1) PostgreSQL table partitioning (aylıq partition), (2) köhnə log-ları cold storage-a (S3) köçür, hot data-nı DB-də saxla, (3) ClickHouse kimi analitik DB-yə axtar — böyük sistemlər üçün audit log-ları append-only log sisteminə (ClickHouse, OpenSearch) yazılır.

**S: Observer vs Event Listener vs Trait — audit üçün hansını seçmək lazımdır?**
C: Trait (`Auditable`) — modelin özünə daxildir, yeni model-lər üçün sadəcə `use Auditable` əlavə etmək kifayətdir, rahatdır. Observer — model class-ından ayrıdır, bir yerdən idarə edilir, bir çox modeli bir observer-dan izləmək mümkündür. Event Listener — ən decoupled, amma daha çox boilerplate. Tövsiyə: `Auditable` trait + `$auditExclude` / `$auditInclude` override imkanı.

---

## Anti-patterns

**1. Audit log-a şifrə/kart yazmaq**
`old_values: {"password": "abc123"}` — PII/sensitive data audit log-da plain text saxlanılır. `$auditExclude = ['password', 'card_number', 'ssn']` list-i mütləq olmalıdır.

**2. Synchronous logging — request-i ləngitmək**
Hər model dəyişikliyi üçün DB-yə sinxron yazma — yüksək yüklü sistemdə latency artır. Audit log-ları queue-a göndər, background-da yaz.

**3. Audit log cədvəlini indeksləməmək**
`user_id`, `auditable_type`, `created_at` üzərindən axtarış yavaşdır. Bu sütunlar indekslənməlidir, xüsusən `(auditable_type, auditable_id)` composite index.

**4. Audit log-ları silmək**
GDPR, SOX, HIPAA — audit trail-i silmək qanun pozuntusudur. Soft delete bile olmamalıdır. Log rotation: köhnəlmiş log-ları cold storage-a köçür, silmə.

**5. Çox şeyi log-lamaq**
Hər `SELECT` sorğusunu, hər token refresh-i log-lamaq — data explosion, storage xərci, axtarışı çətinləşdirir. Yalnız state-dəyişdirən əməliyyatlar (create, update, delete, login, logout) log-lanmalıdır.

**6. Context yoxdur**
"Order updated" — kim? Nə dəyişdi? Niyə? Audit log-da `user_id`, `ip`, `user_agent`, `old_values`, `new_values` mütləqdir.
