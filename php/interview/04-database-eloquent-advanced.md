# Database və Eloquent Advanced

## 1. Database Indexing nədir və nə vaxt istifadə olunmalıdır?

Index — verilənlər bazasında axtarışı sürətləndirən data strukturudur (B-Tree).

```sql
-- Tək sütun index
CREATE INDEX idx_users_email ON users(email);

-- Composite index (sıralama vacibdir!)
CREATE INDEX idx_orders_user_status ON orders(user_id, status);

-- Unique index
CREATE UNIQUE INDEX idx_users_email_unique ON users(email);

-- Partial index (PostgreSQL)
CREATE INDEX idx_orders_pending ON orders(created_at) WHERE status = 'pending';
```

**Laravel migration-da:**
```php
Schema::table('orders', function (Blueprint $table) {
    $table->index('user_id');
    $table->index(['user_id', 'status']); // composite
    $table->unique('email');
    $table->fullText('description'); // full-text search
});
```

**Nə vaxt index lazımdır?**
- WHERE, JOIN, ORDER BY-da istifadə olunan sütunlar
- Foreign key sütunları
- Tez-tez axtarılan sütunlar

**Nə vaxt lazım deyil?**
- Kiçik cədvəllər (< 1000 sətir)
- Tez-tez INSERT/UPDATE olunan sütunlar (index write-ı yavaşladır)
- Aşağı cardinality sütunlar (boolean kimi)

**Composite index sıralaması:**
```sql
-- INDEX (user_id, status, created_at)
-- Bu sorğular index-dən istifadə edir:
WHERE user_id = 1
WHERE user_id = 1 AND status = 'active'
WHERE user_id = 1 AND status = 'active' AND created_at > '2024-01-01'

-- Bu sorğu index-dən istifadə ETMİR (sol tərəf əksikdir):
WHERE status = 'active'
WHERE created_at > '2024-01-01'
```

---

## 2. Database Transactions necə işləyir?

ACID prinsipləri:
- **Atomicity** — hamısı və ya heç biri
- **Consistency** — data həmişə valid vəziyyətdə
- **Isolation** — paralel tranzaksiyalar bir-birinə mane olmur
- **Durability** — commit olunmuş data itmir

```php
// Laravel-də transaction
DB::transaction(function () {
    $order = Order::create([...]);

    foreach ($items as $item) {
        $order->items()->create($item);

        Product::where('id', $item['product_id'])
            ->decrement('stock', $item['quantity']);
    }

    Payment::create([
        'order_id' => $order->id,
        'amount' => $order->total,
    ]);
}); // Xəta olsa avtomatik rollback

// Manuel transaction
DB::beginTransaction();
try {
    // əməliyyatlar...
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// Deadlock retry
DB::transaction(function () {
    // ...
}, 5); // 5 dəfə retry et

// Pessimistic locking
$user = User::where('id', 1)->lockForUpdate()->first(); // SELECT ... FOR UPDATE
$user->balance -= 100;
$user->save();
```

**Isolation Levels:**
- **Read Uncommitted** — dirty read mümkün
- **Read Committed** (PostgreSQL default) — yalnız commit olunmuş data
- **Repeatable Read** (MySQL default) — eyni sorğu eyni nəticə
- **Serializable** — tam izolyasiya, ən yavaş

---

## 3. Query Optimization — yavaş sorğuları necə optimallaşdırmaq?

```php
// 1. SELECT * əvəzinə yalnız lazımlı sütunlar
User::select('id', 'name', 'email')->get();

// 2. Chunk — böyük datasetləri hissə-hissə emal et
User::where('active', false)
    ->chunkById(1000, function ($users) {
        foreach ($users as $user) {
            $user->delete();
        }
    });

// 3. Cursor — yaddaş effektiv iterasiya (Generator istifadə edir)
foreach (User::where('active', true)->cursor() as $user) {
    // Hər dəfə 1 model yaddaşda
}

// 4. Subquery
$users = User::addSelect([
    'last_login_at' => Login::select('created_at')
        ->whereColumn('user_id', 'users.id')
        ->latest()
        ->limit(1),
])->get();

// 5. Raw expression (ehtiyatla)
$users = User::select(DB::raw('DATE(created_at) as date, COUNT(*) as count'))
    ->groupBy('date')
    ->get();

// 6. Explain — sorğu planını görmək
User::where('email', 'test@test.com')->explain();

// 7. Database query log
DB::enableQueryLog();
// ... əməliyyatlar
dd(DB::getQueryLog());
```

---

## 4. Laravel Migrations Best Practices

```php
// Zero-downtime migration — böyük cədvəldə sütun əlavə etmək
// Addım 1: nullable sütun əlavə et
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable()->after('email');
});

// Addım 2: Data-nı doldur (ayrı migration və ya command)
User::query()->whereNull('phone')->chunkById(1000, function ($users) {
    foreach ($users as $user) {
        $user->update(['phone' => $user->profile->phone ?? '']);
    }
});

// Addım 3: Nullable-ı götür (lazım olarsa)
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable(false)->default('')->change();
});

// Rollback-ı unutma
public function down(): void {
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('phone');
    });
}
```

---

## 5. Eloquent Model Casting və Accessors/Mutators

```php
class Order extends Model {
    // Attribute casting
    protected function casts(): array {
        return [
            'total' => 'decimal:2',
            'metadata' => 'array',      // JSON sütun
            'is_paid' => 'boolean',
            'shipped_at' => 'datetime',
            'status' => OrderStatus::class, // Enum cast
            'options' => AsCollection::class,
            'secret' => 'encrypted',     // avtomatik encrypt/decrypt
        ];
    }

    // Modern accessor (PHP 8+ Attribute)
    protected function fullPrice(): Attribute {
        return Attribute::make(
            get: fn () => $this->price + $this->tax,
        );
    }

    // Accessor + Mutator
    protected function name(): Attribute {
        return Attribute::make(
            get: fn (string $value) => ucfirst($value),
            set: fn (string $value) => strtolower($value),
        );
    }
}

// Enum casting
enum OrderStatus: string {
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
```

---

## 6. Soft Deletes, Pruning və Model Events

```php
class Post extends Model {
    use SoftDeletes;

    // Prunable — köhnə data-nı avtomatik sil
    use Prunable;

    public function prunable(): Builder {
        // 6 aydan köhnə soft-deleted postları sil
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subMonths(6));
    }
}

// Sorğularda
Post::all();                    // soft deleted-lər daxil deyil
Post::withTrashed()->get();     // hamısı
Post::onlyTrashed()->get();     // yalnız silinmişlər
$post->restore();               // geri qaytar
$post->forceDelete();           // həqiqi sil

// Pruning command (schedule-da)
$schedule->command('model:prune')->daily();
```

---

## 7. Query Builder vs Eloquent — nə vaxt hansını istifadə etmək?

```php
// Eloquent — model xüsusiyyətləri lazım olanda
$users = User::with('posts')
    ->where('active', true)
    ->get(); // Collection of User models

// Query Builder — performans kritik olanda, böyük data
$stats = DB::table('orders')
    ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as revenue'))
    ->where('created_at', '>=', now()->subMonth())
    ->groupBy('status')
    ->get(); // Collection of stdClass

// Bulk operations — Eloquent event-lər fire OLMUR
DB::table('users')->where('last_login', '<', now()->subYear())->delete();
User::where('last_login', '<', now()->subYear())->delete(); // model events fire olur amma yavaşdır
```

**Qayda:** CRUD və relationships üçün Eloquent, reporting və bulk operations üçün Query Builder.

---

## 8. Database Seeding və Factories

```php
// Factory
class UserFactory extends Factory {
    public function definition(): array {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ];
    }

    // State
    public function admin(): static {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    // Relationship ilə
    public function withPosts(int $count = 3): static {
        return $this->has(Post::factory()->count($count));
    }
}

// İstifadə
User::factory()->count(10)->create();
User::factory()->admin()->withPosts(5)->create();

// Seeder
class DatabaseSeeder extends Seeder {
    public function run(): void {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
```

---

## 9. Redis ilə Caching strategiyaları

```php
// Basic cache
$users = Cache::remember('active_users', 3600, function () {
    return User::where('active', true)->get();
});

// Tags (Redis/Memcached)
Cache::tags(['users', 'admins'])->put('admin_list', $admins, 3600);
Cache::tags(['users'])->flush(); // users tag-li bütün cache silinir

// Cache invalidation strategiyaları
class UserObserver {
    public function saved(User $user): void {
        Cache::forget("user:{$user->id}");
        Cache::tags(['users'])->flush();
    }
}

// Atomic locks
$lock = Cache::lock('process-order-' . $orderId, 10);
if ($lock->get()) {
    try {
        // Yalnız bir process bu kodu icra edə bilər
        $this->processOrder($orderId);
    } finally {
        $lock->release();
    }
}

// Rate limiting with cache
$key = 'api-calls:' . $user->id;
if (Cache::increment($key) === 1) {
    Cache::put($key, 1, 60); // TTL set
}
if (Cache::get($key) > 100) {
    abort(429, 'Too many requests');
}
```
