# Bulk Operations & UPSERT Patterns

> **Seviyye:** Intermediate ⭐⭐

## Niye bulk operations?

10000 row INSERT etmek lazimdir. Iki yol var:

```php
// YOL 1: Loop-da tek-tek (PIS!)
foreach ($rows as $row) {
    DB::table('users')->insert($row);
}
// 10000 query, her biri ~5ms = 50 saniye

// YOL 2: Bulk insert (DOGRU)
DB::table('users')->insert($rows);
// 1 query (ve ya bir nece batch) = 200ms
```

Network round-trip, parsing, transaction overhead - hamsi tek-tek query-de cox bahalidir.

---

## Bulk INSERT - Single Statement, Multiple VALUES

```sql
-- 1 statement, 5 row
INSERT INTO users (name, email, status) VALUES
    ('Anna', 'anna@x.com', 'active'),
    ('Bob', 'bob@x.com', 'active'),
    ('Carol', 'carol@x.com', 'pending'),
    ('David', 'david@x.com', 'active'),
    ('Eve', 'eve@x.com', 'inactive');
```

**Nece sayda row bir defede?** MySQL-de `max_allowed_packet` limiti var (default 64MB). Cox boyuk INSERT-i parcala:

```php
// Batch insert - 1000-er row
collect($allRows)->chunk(1000)->each(function ($chunk) {
    DB::table('users')->insert($chunk->toArray());
});
```

Real numbers:
- 1 row per query × 10000: ~50s
- 100 row per batch: ~600ms
- 1000 row per batch: ~250ms
- 10000 row per batch (1 query): ~200ms (amma packet limit risk)

Optimum: **500-2000 row per batch**.

---

## UPSERT - INSERT ya da UPDATE

User var? UPDATE et. Yox? INSERT et. Race condition olmasin.

### MySQL: ON DUPLICATE KEY UPDATE

```sql
INSERT INTO users (email, name, last_login) 
VALUES ('anna@x.com', 'Anna', NOW())
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    last_login = NOW();
```

`VALUES(col)` - INSERT-de gelen deyere referans (deprecated MySQL 8.0+). Yeni syntax:

```sql
INSERT INTO users (email, name, last_login) 
VALUES ('anna@x.com', 'Anna', NOW()) AS new_data
ON DUPLICATE KEY UPDATE 
    name = new_data.name,
    last_login = new_data.last_login;
```

> **Diqqet:** `ON DUPLICATE KEY UPDATE` UNIQUE constraint pozulduqda iseleyir - PRIMARY KEY ve ya UNIQUE INDEX olmalidir.

### PostgreSQL: ON CONFLICT

PostgreSQL-de daha temiz syntax:

```sql
-- DO NOTHING - movcuddursa skip
INSERT INTO users (email, name) 
VALUES ('anna@x.com', 'Anna')
ON CONFLICT (email) DO NOTHING;

-- DO UPDATE - movcuddursa update
INSERT INTO users (email, name, last_login) 
VALUES ('anna@x.com', 'Anna', NOW())
ON CONFLICT (email) DO UPDATE SET 
    name = EXCLUDED.name,
    last_login = EXCLUDED.last_login;
```

`EXCLUDED` - INSERT-de gelen yeni row-a referans.

**Conditional update** - yalniz mueyyen halda:

```sql
INSERT INTO products (sku, price, updated_at) 
VALUES ('PRD-001', 99.99, NOW())
ON CONFLICT (sku) DO UPDATE SET 
    price = EXCLUDED.price,
    updated_at = EXCLUDED.updated_at
WHERE products.price <> EXCLUDED.price;  -- yalniz qiymet deyiserse
```

### MySQL INSERT IGNORE - DO NOTHING analoqu

```sql
-- Conflict olsa, error atmir, sadece o row-u skip edir
INSERT IGNORE INTO users (email, name) VALUES 
    ('anna@x.com', 'Anna'),
    ('bob@x.com', 'Bob');
```

> **Diqqet:** `INSERT IGNORE` butun error-lari yox edir (data type, NOT NULL violation de). Tehlukelidir - dogrusu `ON DUPLICATE KEY UPDATE id=id` (no-op).

---

## Laravel UPSERT API

### upsert() - Laravel 8+

```php
DB::table('users')->upsert(
    [
        ['email' => 'anna@x.com', 'name' => 'Anna', 'last_login' => now()],
        ['email' => 'bob@x.com', 'name' => 'Bob', 'last_login' => now()],
    ],
    ['email'],                          // unique by columns
    ['name', 'last_login']              // update bu sutunlari
);
```

Generated SQL:
```sql
-- MySQL
INSERT INTO users (email, name, last_login) VALUES (?, ?, ?), (?, ?, ?)
ON DUPLICATE KEY UPDATE name = VALUES(name), last_login = VALUES(last_login);

-- PostgreSQL
INSERT INTO users (email, name, last_login) VALUES (?, ?, ?), (?, ?, ?)
ON CONFLICT (email) DO UPDATE SET 
    name = EXCLUDED.name, last_login = EXCLUDED.last_login;
```

### insertOrIgnore()

```php
DB::table('users')->insertOrIgnore([
    ['email' => 'anna@x.com', 'name' => 'Anna'],
    ['email' => 'bob@x.com', 'name' => 'Bob'],  // movcuddursa skip
]);
// MySQL: INSERT IGNORE
// PostgreSQL: INSERT ... ON CONFLICT DO NOTHING
```

### Eloquent updateOrCreate() - tek row

```php
User::updateOrCreate(
    ['email' => 'anna@x.com'],          // axtaris kriteriyasi
    ['name' => 'Anna', 'last_login' => now()]  // update / insert deyerleri
);
```

> **Diqqet:** `updateOrCreate()` 2 query edir (SELECT + UPDATE/INSERT). Race condition var - eyni anda 2 process eyni row yarada biler. Tehlukesiz olmaq ucun unique constraint + try/catch lazim, ya da `upsert()` istifade et.

### updateOrInsert() - DB facade

```php
DB::table('users')->updateOrInsert(
    ['email' => 'anna@x.com'],
    ['name' => 'Anna', 'last_login' => now()]
);
```

---

## Race Conditions - Real Problem

```php
// 2 process eyni anda
User::updateOrCreate(['email' => 'anna@x.com'], [...]);

// SELECT email='anna@x.com' → not found (her ikisinde)
// INSERT → biri ugurlu, ikincisi UNIQUE constraint violation!
```

**Hellr:**

```php
// 1. Try/catch + retry
try {
    User::create(['email' => $email, ...]);
} catch (QueryException $e) {
    if ($e->errorInfo[1] == 1062) {  // MySQL duplicate key
        User::where('email', $email)->update([...]);
    } else {
        throw $e;
    }
}

// 2. upsert() - tek atomik statement, race-safe
DB::table('users')->upsert(
    [['email' => $email, 'last_login' => now()]],
    ['email'],
    ['last_login']
);

// 3. SELECT FOR UPDATE (pessimistic lock)
DB::transaction(function () use ($email) {
    $user = User::where('email', $email)->lockForUpdate()->first();
    if ($user) {
        $user->update(['last_login' => now()]);
    } else {
        User::create(['email' => $email, ...]);
    }
});
```

---

## Idempotent Upsert - Webhook Pattern

Stripe webhook gelir. Eyni event 2 defe gelse, dublikat olmasin:

```php
// events table-da event_id UNIQUE
DB::table('webhook_events')->insertOrIgnore([
    'event_id' => $request['id'],          // Stripe-in unique id
    'type' => $request['type'],
    'payload' => json_encode($request),
    'received_at' => now(),
]);

$inserted = DB::table('webhook_events')
    ->where('event_id', $request['id'])
    ->where('processed', false)
    ->exists();

if ($inserted) {
    // Process eylem - yalniz birinci defe
}
```

---

## Bulk LOAD - Cox Boyuk Datasets

CSV file-dan milyonlarla row import etmek? `INSERT` cox yavasdir.

### MySQL: LOAD DATA INFILE

```sql
LOAD DATA INFILE '/var/lib/mysql-files/users.csv'
INTO TABLE users
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS  -- header skip
(name, email, status);
```

10x-100x suretli `INSERT VALUES`-den. 10 milyon row 30 saniyede.

```php
// Laravel-den
DB::statement("LOAD DATA LOCAL INFILE '/tmp/users.csv' INTO TABLE users 
    FIELDS TERMINATED BY ',' (name, email, status)");
```

> **Security:** `LOCAL` keyword client file-i oxuyur. PDO-da `PDO::MYSQL_ATTR_LOCAL_INFILE = true` lazimdir.

### PostgreSQL: COPY FROM

```sql
COPY users (name, email, status) 
FROM '/tmp/users.csv' 
DELIMITER ',' CSV HEADER;
```

```php
// Laravel-de PDO ile
$pdo = DB::connection()->getPdo();
$pdo->pgsqlCopyFromFile('users', '/tmp/users.csv', ',', '\\\\N', '"');
```

PostgreSQL-de en suretli bulk load metodu. `\copy` (psql client) network uzerinden de isleyir.

---

## Bulk UPDATE Patterns

### Single UPDATE - WHERE IN

```sql
-- Eyni deyere set (sade)
UPDATE users SET status = 'active' WHERE id IN (1, 2, 3, 4, 5);

-- 10000 ID? IN list cox boyuyur, query plan yavaslayir
-- Limit: MySQL ~65535 placeholder, PostgreSQL ~32767
```

### UPDATE FROM JOIN - Ferqli deyerler

User-lerin balance-ni temp table-dan update et:

```sql
-- PostgreSQL
UPDATE users u
SET balance = t.new_balance,
    updated_at = NOW()
FROM temp_balances t
WHERE u.id = t.user_id;

-- MySQL (JOIN syntax)
UPDATE users u
JOIN temp_balances t ON u.id = t.user_id
SET u.balance = t.new_balance,
    u.updated_at = NOW();
```

### UPDATE with CASE WHEN

Bir nece row-u ferqli deyerlere update:

```sql
UPDATE products
SET price = CASE id
    WHEN 1 THEN 99.99
    WHEN 2 THEN 149.50
    WHEN 3 THEN 79.00
    ELSE price
END
WHERE id IN (1, 2, 3);
```

Laravel package: `barryvdh/laravel-debugbar`-in author-i `iamfarhad/laravel-bulk` ve s. var, amma adeten manual SQL daha simple.

---

## Bulk DELETE - Avoid Lock

Boyuk DELETE table-i blok edir:

```sql
-- PIS: 10 milyon row delete - cox uzun lock
DELETE FROM logs WHERE created_at < '2025-01-01';
```

**Hell: Batch delete:**

```php
do {
    $deleted = DB::table('logs')
        ->where('created_at', '<', '2025-01-01')
        ->limit(1000)
        ->delete();
    
    usleep(50000);  // 50ms - DB-ye nefes ver
} while ($deleted > 0);
```

```sql
-- MySQL: DELETE LIMIT-i destekleyir
DELETE FROM logs WHERE created_at < '2025-01-01' LIMIT 1000;

-- PostgreSQL: ORDER BY/LIMIT desteklemir DELETE-de, CTE ile:
DELETE FROM logs WHERE id IN (
    SELECT id FROM logs WHERE created_at < '2025-01-01' LIMIT 1000
);
```

### TRUNCATE alternativi

Butun table-i silmek? `TRUNCATE` daha suretli (DDL, log etmir):

```sql
TRUNCATE TABLE temp_imports;
-- Saniyede milyonlarla row silir, amma:
-- - Rollback olmur
-- - FK reference-li ola bilmez (CASCADE lazim)
-- - AUTO_INCREMENT reset olur
```

---

## RETURNING Clause (PostgreSQL, MySQL 8.0.21+)

UPDATE/INSERT-den sonra deyisilen row-lari geri qaytarir:

```sql
-- PostgreSQL
INSERT INTO orders (user_id, amount) VALUES (1, 99.99)
RETURNING id, created_at;

UPDATE orders SET status = 'shipped' WHERE id = 5
RETURNING id, status, updated_at;

DELETE FROM orders WHERE status = 'cancelled'
RETURNING *;
```

**Bulk update + audit log:**

```sql
WITH updated AS (
    UPDATE products SET price = price * 1.1 
    WHERE category_id = 5
    RETURNING id, price
)
INSERT INTO price_audit (product_id, new_price, changed_at)
SELECT id, price, NOW() FROM updated;
```

Laravel-de:
```php
$inserted = DB::select("INSERT INTO orders (...) VALUES (...) RETURNING id, created_at");
```

---

## Benchmark - Bulk vs Tek

10000 row INSERT, MySQL 8, local network:

| Method | Time | Queries |
|--------|------|---------|
| `INSERT` loop (tek-tek) | 52 sec | 10000 |
| Batch INSERT (100 row) | 850 ms | 100 |
| Batch INSERT (1000 row) | 280 ms | 10 |
| Single INSERT (10000) | 195 ms | 1 |
| `LOAD DATA INFILE` | 95 ms | 1 |

UPSERT 10000 row (50% conflict):

| Method | Time |
|--------|------|
| `updateOrCreate()` loop | 88 sec |
| `upsert()` batch | 320 ms |

---

## Common Pitfalls

### 1. Memory limit

```php
// 1 milyon row collect-da memory bitir
$rows = User::all()->map(...)->toArray();  // PIS

// Generator-le
foreach (User::lazy() as $user) { ... }
```

### 2. Transaction icinde cox bulk

```php
DB::transaction(function () {
    DB::table('logs')->insert($millionRows);  // Transaction log dolur!
});

// Daha yaxsi: kicik transaction-larda batch
collect($millionRows)->chunk(5000)->each(function ($batch) {
    DB::transaction(function () use ($batch) {
        DB::table('logs')->insert($batch->toArray());
    });
});
```

### 3. Index update overhead

Bulk INSERT zamani index yenilenir. Cox boyuk import ucun:

```sql
-- 1. Index-leri sil
ALTER TABLE big_table DROP INDEX idx_email;

-- 2. Bulk insert
LOAD DATA INFILE ... INTO TABLE big_table;

-- 3. Index-i bir defede yarat (daha suretli)
ALTER TABLE big_table ADD INDEX idx_email (email);
```

---

## Interview suallari

**Q: ON DUPLICATE KEY UPDATE ile updateOrCreate() arasinda ferq?**
A: `ON DUPLICATE KEY UPDATE` (ve `upsert()`) atomik tek statement-dir - race condition yoxdur. `updateOrCreate()` 2 query edir (SELECT + INSERT/UPDATE) - 2 process eyni anda eyni email yarada bilmek isteyirse, biri UNIQUE violation alir. Production-da hemise upsert() istifade et.

**Q: 1 milyon row INSERT etmek isteyirem, en yaxsi yol?**
A: 1) Format CSV-dirse - `LOAD DATA INFILE` (MySQL) ve ya `COPY FROM` (PostgreSQL) - 100x suretli. 2) Application-dan gelirse - 500-2000 row chunk-da batch INSERT. 3) Index-leri evvelce sil, sonra yeniden yarat. 4) `disable foreign key checks` muveqqeti olaraq.

**Q: PostgreSQL EXCLUDED nedir?**
A: `INSERT ... ON CONFLICT DO UPDATE` icinde `EXCLUDED` INSERT-in yeni getirdiyi deyerlere referansdir. `users.name` movcud deyerdir, `EXCLUDED.name` yeni deyerdir. Conditional update ucun cox faydalidir: `WHERE products.price <> EXCLUDED.price`.

**Q: TRUNCATE vs DELETE - ne vaxt hansi?**
A: `TRUNCATE` butun table-i silir, DDL operation-dur, transaction log-a yazmir, AUTO_INCREMENT reset edir, demek olar ki insant. `DELETE WHERE` filter desteklemir TRUNCATE. `TRUNCATE` rollback olunmur (MySQL-de), FK CASCADE lazimdir. Conditional delete-de `DELETE`, butun temp table cleanup-da `TRUNCATE`.

**Q: Niye RETURNING clause faydalidir?**
A: INSERT/UPDATE/DELETE-den sonra deyisilen row-lari elave query olmadan alirsan. Otherwise: INSERT, sonra `SELECT WHERE id = LAST_INSERT_ID()` - 2 round-trip. Bulk operation-da en faydali: 1000 row update-den sonra hansilarinin id-sinin deyisdiyini bilmek ucun ayri SELECT lazim olmur.
