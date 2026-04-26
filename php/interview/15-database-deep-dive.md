# Database Deep Dive (Senior)

## 1. Normalization nədir? Normal formalar izah edin.

**1NF (First Normal Form):**
- Hər sütunda atomic (bölünməz) dəyər olmalı
- Təkrarlanan qruplar olmamalı

```
-- Pis (1NF pozulub)
| id | name  | phones                    |
|----|-------|---------------------------|
| 1  | Orxan | 050-111-11-11, 055-222-22 |

-- Yaxşı (1NF)
| id | name  | phone         |
|----|-------|---------------|
| 1  | Orxan | 050-111-11-11 |
| 1  | Orxan | 055-222-22-22 |
-- və ya ayrı phones cədvəli
```

**2NF (Second Normal Form):**
- 1NF olmalı
- Bütün non-key sütunlar tam primary key-dən asılı olmalı (partial dependency yoxdur)

```
-- Pis (2NF pozulub) — composite key (student_id, course_id)
| student_id | course_id | student_name | grade |
|------------|-----------|--------------|-------|
-- student_name yalnız student_id-dən asılıdır, course_id-dən yox

-- Yaxşı — ayrı cədvəllər
students: (id, name)
enrollments: (student_id, course_id, grade)
```

**3NF (Third Normal Form):**
- 2NF olmalı
- Transitive dependency olmamalı (A → B → C yoxdur)

```
-- Pis (3NF pozulub)
| employee_id | department_id | department_name |
-- department_name → department_id-dən asılıdır, employee_id-dən yox

-- Yaxşı
employees: (id, department_id)
departments: (id, name)
```

**Denormalization — nə vaxt?**
- Reporting / read-heavy sorğular
- JOIN-lar çox yavaş olanda
- Cache-ə alternativ olaraq

```php
// Laravel-də denormalized field
// orders cədvəlinə customer_name əlavə et (JOIN azalt)
Schema::table('orders', function (Blueprint $table) {
    $table->string('customer_name')->after('user_id');
});

// Sync saxla
class UserObserver {
    public function updated(User $user): void {
        if ($user->isDirty('name')) {
            $user->orders()->update(['customer_name' => $user->name]);
        }
    }
}
```

---

## 2. MySQL Storage Engine-lər: InnoDB vs MyISAM

| Xüsusiyyət | InnoDB (default) | MyISAM |
|---|---|---|
| Transactions | Bəli (ACID) | Yox |
| Row-level locking | Bəli | Table-level locking |
| Foreign keys | Bəli | Yox |
| Crash recovery | Bəli | Zəif |
| Full-text search | Bəli (5.6+) | Bəli |
| Performance (read) | Yaxşı | Bir az sürətli |
| Performance (write) | Yaxşı | Yavaş (table lock) |

**Nəticə:** Həmişə InnoDB istifadə edin.

---

## 3. Database Replication və Sharding

### Replication (Read Replicas)
```
Master (write) ──────► Replica 1 (read)
                ├────► Replica 2 (read)
                └────► Replica 3 (read)
```

```php
// Laravel config
'mysql' => [
    'read' => [
        'host' => ['replica1.db.com', 'replica2.db.com'],
    ],
    'write' => [
        'host' => ['master.db.com'],
    ],
    'sticky' => true, // Write sonrası eyni request-də master-dan oxu
],

// Manual seçim
DB::connection('mysql::read')->table('users')->get();
DB::connection('mysql::write')->table('users')->insert([...]);
```

### Sharding (Horizontal Partitioning)
```
Shard 1: users id 1-1M
Shard 2: users id 1M-2M
Shard 3: users id 2M-3M
```

```php
// Sharding strategiyaları
// 1. Range-based: id aralığına görə
// 2. Hash-based: hash(user_id) % shard_count
// 3. Directory-based: lookup table

// Laravel-də manual sharding
class ShardManager {
    public function getConnection(int $userId): string {
        $shard = $userId % 3; // 3 shard
        return "mysql_shard_{$shard}";
    }
}

$connection = $shardManager->getConnection($userId);
DB::connection($connection)->table('orders')->where('user_id', $userId)->get();
```

---

## 4. Deadlock nədir və necə qarşısı alınır?

İki tranzaksiya bir-birinin lock-unu gözləyir → heç biri bitə bilmir.

```
Transaction A: LOCK row 1, waiting for row 2
Transaction B: LOCK row 2, waiting for row 1
→ DEADLOCK!
```

**Qarşısının alınması:**
```php
// 1. Həmişə eyni sırada lock et
// Pis
// Tx A: lock user 1, then lock user 2
// Tx B: lock user 2, then lock user 1

// Yaxşı — həmişə kiçik id-dən böyüyə
$ids = collect([$fromUserId, $toUserId])->sort()->values();
DB::transaction(function () use ($ids, $amount) {
    $from = User::where('id', $ids[0])->lockForUpdate()->first();
    $to = User::where('id', $ids[1])->lockForUpdate()->first();
    // transfer...
});

// 2. Tranzaksiyaları qısa saxla
// 3. Retry mechanism
DB::transaction(function () {
    // ...
}, 3); // Deadlock olsa 3 dəfə retry

// 4. Optimistic locking
class Product extends Model {
    protected $casts = ['version' => 'integer'];
}

$product = Product::find(1);
$affected = Product::where('id', 1)
    ->where('version', $product->version)
    ->update([
        'stock' => $product->stock - 1,
        'version' => $product->version + 1,
    ]);

if ($affected === 0) {
    throw new OptimisticLockException('Record was modified by another process');
}
```

---

## 5. EXPLAIN nədir və necə oxunur?

```sql
EXPLAIN SELECT * FROM orders WHERE user_id = 5 AND status = 'pending';

-- Nəticə:
-- type: ref (index istifadə edir) / ALL (full table scan — PIS)
-- possible_keys: idx_user_status
-- key: idx_user_status (faktiki istifadə olunan index)
-- rows: 15 (tarama ediləcək sətir sayı)
-- Extra: Using index condition
```

**type sütunu (yaxşıdan pisə):**
```
system > const > eq_ref > ref > range > index > ALL

const   — primary key ilə tək sətir (ən yaxşı)
eq_ref  — JOIN-da unique index
ref     — non-unique index
range   — BETWEEN, >, <, IN
index   — full index scan (bütün index oxunur)
ALL     — full table scan (ən pis — INDEX LAZIMDIR!)
```

```php
// Laravel-də EXPLAIN
$query = Order::where('user_id', 5)->where('status', 'pending');
dd($query->explain()->toArray());

// Və ya DB::listen ilə yavaş sorğuları tutmaq
DB::listen(function ($query) {
    if ($query->time > 100) {
        Log::warning("Slow query [{$query->time}ms]: {$query->sql}");
    }
});
```

---

## 6. Database Connection Pooling

```
Problem: Hər request yeni DB connection açır → overhead
Həll: Connection pool — əvvəlcədən açılmış connection-ları paylaşır
```

```php
// PHP-FPM-də persistent connections
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
],

// Laravel Octane (Swoole) — built-in connection pool
// config/octane.php
'warm' => [
    'database',  // DB connection boot zamanı hazırla
    'cache',
],

// PgBouncer (PostgreSQL) / ProxySQL (MySQL) — external pooler
// DB_HOST=pgbouncer-host (application → pooler → database)
```

---

## 7. PostgreSQL vs MySQL — nə vaxt hansı?

| Xüsusiyyət | PostgreSQL | MySQL |
|---|---|---|
| JSON support | Çox güclü (jsonb, index) | Yaxşı (JSON type) |
| Full-text search | Güclü | Əsas |
| Partitioning | Native | Bəli |
| CTEs / Window functions | Güclü | Bəli (8.0+) |
| GIS / Spatial | PostGIS (ən yaxşı) | Əsas |
| Replication | Logical + Physical | Binary log |
| Performance (simple) | Yaxşı | Bir az sürətli |
| Performance (complex) | Daha yaxşı | Yaxşı |
| Concurrency (MVCC) | Güclü | Yaxşı |
| Laravel support | Tam | Tam |

**PostgreSQL seç:** Complex queries, JSON-heavy, GIS, data integrity vacibdir
**MySQL seç:** Sadə CRUD, geniş hosting dəstəyi, mövcud tooling

```php
// PostgreSQL-specific Laravel
// JSONB query
User::whereJsonContains('preferences->languages', 'az')->get();

// Array column
$table->jsonb('tags');
User::whereJsonContains('tags', ['php', 'laravel'])->get();

// Full-text search (PostgreSQL)
User::whereFullText(['name', 'bio'], 'developer')->get();

// CTE (Common Table Expression)
$users = DB::table(
    DB::raw('(WITH active_users AS (
        SELECT * FROM users WHERE active = true
    ) SELECT * FROM active_users) as sub')
)->get();
```
