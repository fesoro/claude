# Database Refactoring (Expand-Contract, Strangler, Dual-Write) (Senior)

## Niye DB refactoring cetindir?

Code refactoring asandir — kod tek yerdedir, deploy etdin bitdi. **Database refactoring** bunlari sinxronlasdirmaq lazimdir:

1. **Schema** (DDL deyisikligi)
2. **Data** (movcud row-lar yeni schema-ya uygun olmali)
3. **Application code** (deploy zamani 2 versiya is goruse biler!)
4. **Other clients** (analytics, reporting, microservices)
5. **Backup/restore compatibility**

| Code refactoring | DB refactoring |
|------------------|----------------|
| Atomic deploy mumkundur | Atomic deyil (data + schema + code) |
| Rollback git revert ile | Rollback DDL + data restore |
| Test environment-de tam test | Production-only edge case-ler |
| 1 aktor (deploy) | Multi-aktor (CDC, replicas, ETL) |

---

## Expand-Contract pattern (universal)

5 merhele, her birinin oz deploy-u:

```
1. EXPAND   ─ Yeni schema element elave et (additive, safe)
2. WRITE    ─ Application her ikisine yazsin (dual-write)
3. BACKFILL ─ Movcud data-ni yeni schema-ya kopyala
4. READ     ─ Application yeni schema-dan oxusun
5. CONTRACT ─ Kohne schema element-i sil
```

Her merhele production-a deploy olunur, problem olarsa onceki merhele-e qayit. **Hec bir merheledə downtime olmamalidir.**

---

## Use case 1: Column rename (`name` -> `full_name`)

### Adim 1: Expand

```php
// migration_001
Schema::table('users', function (Blueprint $t) {
    $t->string('full_name', 100)->nullable()->after('name');
});
```

### Adim 2: Dual-write (application)

```php
// app/Models/User.php
class User extends Model {
    protected $fillable = ['name', 'full_name', 'email'];
    
    public function setNameAttribute(string $value): void {
        $this->attributes['name'] = $value;
        $this->attributes['full_name'] = $value;  // Dual-write
    }
}

// Ya da DB trigger-i ile (app deploy zerer vermesin)
```

```sql
-- Alternative: trigger ile dual-write
CREATE TRIGGER sync_full_name
BEFORE INSERT OR UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.full_name = COALESCE(NEW.full_name, NEW.name);
END;
```

### Adim 3: Backfill (Laravel job)

```php
// app/Console/Commands/BackfillFullName.php
class BackfillFullName extends Command
{
    public function handle(): void
    {
        DB::table('users')
            ->whereNull('full_name')
            ->orderBy('id')
            ->chunkById(1000, function ($users) {
                $ids = $users->pluck('id');
                DB::table('users')
                    ->whereIn('id', $ids)
                    ->update(['full_name' => DB::raw('name')]);
                
                $this->info("Backfilled {$ids->count()} rows");
                usleep(100_000);  // throttle: 100ms pause
            });
    }
}
```

### Adim 4: Migrate reads

```php
// Application kodu yeni column-dan oxuyur
$user = User::select('id', 'full_name', 'email')->find($id);
echo $user->full_name;
```

### Adim 5: Contract

```php
// migration_002 (1-2 hefte sonra, monitoring-den sonra!)
Schema::table('users', function (Blueprint $t) {
    $t->dropColumn('name');
});
```

---

## Use case 2: Switching ID type (BIGINT -> UUID)

En cetin refactoring-lerden biri. **Dual ID** pattern:

```php
// Adim 1: UUID column elave
Schema::table('orders', function (Blueprint $t) {
    $t->uuid('uuid')->nullable()->unique()->after('id');
});

// Adim 2: Yeni row-lara UUID generate
class Order extends Model {
    protected static function booted() {
        static::creating(function ($order) {
            $order->uuid = (string) Str::uuid();
        });
    }
}

// Adim 3: Backfill kohne row-lara
DB::table('orders')->whereNull('uuid')->lazyById(1000)->each(function ($o) {
    DB::table('orders')->where('id', $o->id)->update(['uuid' => Str::uuid()]);
});

// Adim 4: API-de uuid expose et (id deyil)
// route/api.php
Route::get('/orders/{uuid}', fn($uuid) => Order::where('uuid', $uuid)->firstOrFail());

// Adim 5: FK-leri uuid-e migrate (hard part!)
// order_items.order_id (BIGINT) -> order_items.order_uuid (UUID)
//   - Yeni column
//   - Backfill (JOIN orders ON orders.id = order_items.order_id)
//   - App double-write
//   - Switch reads
//   - Drop old column
```

---

## Use case 3: Table split (one table -> two tables)

`users` table boyukdur, sensitive data ayri table-a kocurmek.

```sql
-- Mevcud:
users (id, name, email, password, ssn, credit_card, created_at)

-- Hedef:
users (id, name, email, created_at)
user_secrets (user_id, password, ssn, credit_card)  -- separate, encrypted
```

### Pattern:

```php
// 1. Yeni table yarat
Schema::create('user_secrets', function (Blueprint $t) {
    $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
    $t->string('password');
    $t->string('ssn_encrypted');
    $t->string('credit_card_encrypted');
    $t->timestamps();
});

// 2. Dual-write (app + trigger)
class User extends Model {
    public function setPasswordAttribute($val) {
        $this->attributes['password'] = $val;
        UserSecret::updateOrCreate(['user_id' => $this->id], ['password' => $val]);
    }
}

// 3. Backfill
User::whereDoesntHave('secret')->lazyById(1000)->each(function ($u) {
    UserSecret::create([
        'user_id' => $u->id,
        'password' => $u->password,
        'ssn_encrypted' => encrypt($u->ssn),
    ]);
});

// 4. App reads from new table
$user = User::with('secret')->find($id);
$ssn = decrypt($user->secret->ssn_encrypted);

// 5. Drop kohne sutunlar
Schema::table('users', function ($t) {
    $t->dropColumn(['password', 'ssn', 'credit_card']);
});
```

---

## Use case 4: JSON -> Relational

```sql
-- Evvel: orders.metadata (JSON column)
{"shipping_method": "express", "gift_wrap": true, "notes": "..."}

-- Sonra: orders_metadata table (normalize)
```

```php
// 1. Expand
Schema::create('order_attributes', function ($t) {
    $t->id();
    $t->foreignId('order_id')->constrained();
    $t->string('key');
    $t->text('value');
    $t->unique(['order_id', 'key']);
});

// 2. Backfill
Order::whereNotNull('metadata')->lazyById(1000)->each(function ($o) {
    foreach ($o->metadata as $key => $value) {
        OrderAttribute::create([
            'order_id' => $o->id,
            'key' => $key,
            'value' => is_array($value) ? json_encode($value) : (string) $value,
        ]);
    }
});

// 3. Dual-write
// 4. Migrate reads
// 5. Drop metadata column
```

---

## Strangler fig pattern

Kohne sistemi tedricen yeni ile evez et — eyni vaxt ikisi de isleyir.

```
[Client] -> [Proxy/Router]
                |
                +-- (legacy routes) -> [Legacy DB/App]
                +-- (new routes)    -> [New DB/Service]
```

**Misal:** Monolit Laravel app-dan microservice-e kecid.

```php
// app/Services/OrderService.php
class OrderService {
    public function getOrder(int $id): array {
        if (config('features.use_order_service')) {
            // Yeni: HTTP call to microservice
            return Http::get(config('services.order_service.url') . "/orders/{$id}")->json();
        }
        
        // Kohne: Direct DB
        return Order::find($id)->toArray();
    }
}

// Tedricen feature flag-i %5 -> %50 -> %100
```

---

## Event-driven migration (CDC + outbox)

Boyuk system-de Debezium ile real-time migration:

```
[Old DB] --binlog--> [Debezium] --Kafka--> [Consumer] --> [New DB]
   |                                                          ^
   +---app writes (still working)                              |
                                                               |
[App] --reads from Old DB initially, sonra New DB --------------+
```

**Adimlar:**
1. Debezium kohne DB-ni Kafka-ya stream edir
2. Consumer service yeni DB-ni populate edir (transformation lazimdirsa)
3. Initial snapshot + ongoing CDC
4. Yeni DB hazir olduqda — app reads switch (feature flag)
5. Verify (parallel run)
6. Old DB read-only, sonra archive

---

## Parallel run + comparison

Refactoring zamani **shadow mode** — yeni kod isler, amma neticeni ferqli yere yazir, kohne ile muqayise olunur.

```php
class OrderTotalCalculator {
    public function calculate(Order $order): float {
        $oldResult = $this->oldAlgorithm($order);
        
        // Shadow: yeni algoritmi de hesabla, log et
        try {
            $newResult = $this->newAlgorithm($order);
            
            if (abs($oldResult - $newResult) > 0.01) {
                Log::warning('Algorithm mismatch', [
                    'order_id' => $order->id,
                    'old' => $oldResult,
                    'new' => $newResult,
                    'diff' => $oldResult - $newResult,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('New algorithm failed', ['error' => $e->getMessage()]);
        }
        
        return $oldResult;  // Hele de kohne netice qaytarilir
    }
}
```

Bir nece hefte sonra log-larda ferq yoxsa, switch et.

---

## Feature flag-le migration

```php
// config/features.php
return [
    'new_user_table' => env('FEATURE_NEW_USER_TABLE', false),
];

// Service
class UserRepository {
    public function find(int $id): User {
        return Feature::active('new_user_table')
            ? User::on('new_db')->find($id)
            : User::on('old_db')->find($id);
    }
}

// Laravel Pennant ile per-user gradual rollout:
Feature::define('new_user_table', fn ($user) => 
    $user && $user->id % 100 < 5  // 5%-e elcatan
);
```

---

## Laravel-specific refactoring tools

```php
// 1. Job-larla backfill
class BackfillOrderUuid implements ShouldQueue {
    use Batchable;
    
    public function __construct(public array $orderIds) {}
    
    public function handle(): void {
        foreach ($this->orderIds as $id) {
            DB::table('orders')->where('id', $id)
                ->whereNull('uuid')
                ->update(['uuid' => Str::uuid()]);
        }
    }
}

// Dispatcher
$batch = Bus::batch(
    Order::whereNull('uuid')->pluck('id')->chunk(500)
        ->map(fn($ids) => new BackfillOrderUuid($ids->toArray()))
        ->toArray()
)
->name('Backfill order UUIDs')
->onQueue('backfill')
->dispatch();

// 2. Scout for index sync (Algolia/Meilisearch)
Order::makeAllSearchable();  // Rebuild index after schema change

// 3. Telescope-ile query monitoring
// /telescope/queries -- Yeni schema-da query patterns?
```

---

## Rollback strategy

| Merhele | Rollback | Risk |
|---------|----------|------|
| Expand | DROP COLUMN/TABLE (yeni elementi sil) | Yox |
| Dual-write | Code revert | Yox |
| Backfill | Stop job (data qalir) | Yox |
| Migrate reads | Code revert (kohne column-dan oxu) | Az |
| Contract | **Restore from backup** (column silindi!) | Yuksek |

> **Qaydası:** Contract-i 1-2 hefte gozle. Telescope-de kohne column query-leri sifir olduqdan sonra sil.

---

## Staging-de testing

```php
// 1. Production data anonymized snapshot
php artisan db:dump --anonymize > staging.sql

// 2. Migration apply
php artisan migrate --database=staging

// 3. Backfill job run
php artisan backfill:run --database=staging

// 4. App tests with new schema
php artisan test --env=staging

// 5. Performance test (k6, Apache Bench)
k6 run --vus 100 --duration 5m load-test.js
```

---

## Anti-patterns (etmeyin!)

```php
// 1. ANTI: Big-bang migration
// 100M row UPDATE bir transaction-da
DB::table('users')->update(['status' => DB::raw('LOWER(status)')]);

// 2. ANTI: Schema + code eyni deploy-da
// Migration ve code-u ayri deploy et!

// 3. ANTI: DROP COLUMN deploy-dan derhal sonra
// 1-2 hefte gozle, kohne deploy-lar ola biler

// 4. ANTI: Migration-da Eloquent
public function up() {
    User::all()->each(...);  // Memory blow + model schema mismatch
}

// 5. ANTI: Backfill master-de full table scan
DB::table('orders')->update([...]);  // Replication lag, lock!
```

---

## Interview suallari

**Q: Production-da boyuk table-da column rename nece edersen?**
A: Expand-contract pattern: 1) Yeni column nullable elave (migration), 2) App-da dual-write (model setter ya da DB trigger), 3) Backfill job batch-le (chunkById, throttle), 4) App reads-i yeni column-a deyis (deploy + monitoring), 5) 1-2 hefte gozledikden sonra kohne column-u sil. Hec bir merhelede downtime yoxdur, her merhele rollback olunur.

**Q: BIGINT id-den UUID-e niye kecirik, nece edirik?**
A: Sebebler: 1) Distributed system-de kollidisya yox, 2) ID enumeration attack qarsisi, 3) Merge between databases asan. Nece: dual-ID period — uuid column elave, app yeni row-larda generate, backfill kohnelere, API-de uuid expose, FK-leri tedricen migrate. Storage 16 byte vs 8 byte cost var (UUID v7 sequential time-based daha index-friendly).

**Q: Strangler pattern niye populardir?**
A: Big-bang rewrite riskidir — yeni sistem hazir olmaya, kohne dayanmali. Strangler tedricen kecidi temin edir: proxy layer routing edir, yeni feature-lar yeni system-de, kohnelar sticking, kohne sistem zamanla "stranger" olunur. Risk azdir, hemise rollback mumkundur, business uninterrupted.

**Q: Backfill job production-da DB-ni asagi sala bilermi? Nece qabaqlamaq olar?**
A: Beli — boyuk UPDATE-ler lock yaradir, replication lag artir. Qabaqlama: 1) `chunkById` istifade et (1000 row/batch), 2) Her batch arasi sleep (100ms), 3) Replica lag monitor et, > 30s olarsa pause, 4) Off-peak hour-da run et, 5) Indexed sutunla filter et (sequential scan etme), 6) Job queue priority asagi qoy.

**Q: Shadow mode (parallel run) niye useful-dur?**
A: Yeni kod/algoritm/schema-i production traffic ile test edirsen, amma neticesini real istifade etmirsen — yalniz log/compare edirsen. Real edge case-ler tapilir (test-de gormediyin), confidence yaranir migration etmek ucun. Cost: 2x compute, amma data integrity zedelenmir. Misal: pricing engine refactor, fraud detection model deploy, encryption migration.
