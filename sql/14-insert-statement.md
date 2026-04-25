# INSERT Statement (Junior)

Table-a yeni row əlavə etmək. Sadə görünsə də **UPSERT, RETURNING, bulk insert, ON CONFLICT** kimi güclü imkanları var.

## Single Row Insert

```sql
-- Bütün sütunlar sırası ilə
INSERT INTO users VALUES (1, 'Ali', 'ali@x.com', NOW());

-- Sütun adları ilə (TÖVSİYƏ OLUNUR — schema dəyişsə sınmır)
INSERT INTO users (name, email) VALUES ('Ali', 'ali@x.com');

-- Default istifadə et
INSERT INTO users (name, email, role) VALUES ('Ali', 'ali@x.com', DEFAULT);
-- role üçün `DEFAULT` dəyəri tətbiq olunur

-- Bütün defaults
INSERT INTO users DEFAULT VALUES;
```

## Multi-Row Insert (Bulk)

```sql
-- 1 query ilə çox row
INSERT INTO users (name, email) VALUES 
    ('Ali', 'ali@x.com'),
    ('Veli', 'veli@x.com'),
    ('Orkhan', 'orkhan@x.com');

-- Performans: N tək INSERT-dən N × 10 dəfə sürətli
```

**Qayda:** Bulk insert-də batch ölçüsü optimal 500-1000 row. Çox böyük batch memory problem yarada bilər.

## INSERT SELECT

Başqa query-nin nəticəsini insert et.

```sql
-- Başqa table-dan kopyala
INSERT INTO users_archive (id, name, email, created_at)
SELECT id, name, email, created_at 
FROM users 
WHERE active = 0 AND last_login < NOW() - INTERVAL '1 year';

-- JOIN ilə
INSERT INTO order_summary (user_id, total_orders, total_spent)
SELECT user_id, COUNT(*), SUM(total)
FROM orders
GROUP BY user_id;

-- Data transformation
INSERT INTO normalized_emails (user_id, email)
SELECT id, LOWER(TRIM(email)) FROM users;
```

## RETURNING (PostgreSQL, MySQL 8.0.32+)

Insert sonrası yaradılmış row-u qaytarır.

```sql
-- PostgreSQL: RETURNING
INSERT INTO users (name, email) 
VALUES ('Ali', 'ali@x.com')
RETURNING id, created_at;
-- Netice: (42, '2026-04-24 14:30:00')

-- Bütün sütunlar
INSERT INTO users (name, email) VALUES (...) RETURNING *;

-- Multi-row
INSERT INTO users (name, email) VALUES 
    ('Ali', 'ali@x.com'),
    ('Veli', 'veli@x.com')
RETURNING id, name;

-- MySQL: RETURNING 8.0.32-dən dəstəklənir (az dəstək)
-- MySQL-də alternativ:
INSERT INTO users (name, email) VALUES ('Ali', 'ali@x.com');
SELECT LAST_INSERT_ID();                   -- auto-generated ID
```

**İstifadə:** Application-a ID və timestamp kimi auto-generated dəyərləri qaytarmaq.

## ON CONFLICT / ON DUPLICATE KEY — UPSERT

### PostgreSQL

```sql
-- Əgər unique constraint conflict varsa nə et?

-- 1. NOTHING (ignore)
INSERT INTO users (email, name) VALUES ('ali@x.com', 'Ali')
ON CONFLICT (email) DO NOTHING;

-- 2. UPDATE (UPSERT)
INSERT INTO users (email, name, login_count) VALUES ('ali@x.com', 'Ali', 1)
ON CONFLICT (email) DO UPDATE SET 
    login_count = users.login_count + 1,
    last_login = NOW();

-- 3. EXCLUDED - cəhd edilən yeni dəyəri göstərir
INSERT INTO users (email, name) VALUES ('ali@x.com', 'Ali V.')
ON CONFLICT (email) DO UPDATE SET name = EXCLUDED.name;
-- name column-u 'Ali V.' (cəhd edilən yeni dəyər) olur
```

### MySQL

```sql
-- 1. INSERT IGNORE (conflict olsa silently skip)
INSERT IGNORE INTO users (email, name) VALUES ('ali@x.com', 'Ali');

-- 2. ON DUPLICATE KEY UPDATE
INSERT INTO users (email, name, login_count) VALUES ('ali@x.com', 'Ali', 1)
ON DUPLICATE KEY UPDATE 
    login_count = login_count + 1,
    last_login = NOW();

-- 3. VALUES() function - cəhd edilən yeni dəyər
INSERT INTO users (email, name) VALUES ('ali@x.com', 'Ali V.')
ON DUPLICATE KEY UPDATE name = VALUES(name);
```

Ətraflı UPSERT: `38-bulk-operations-and-upsert.md`.

## INSERT ... SELECT ilə UPSERT

```sql
-- Batch upsert
INSERT INTO user_stats (user_id, login_count, last_login)
SELECT user_id, COUNT(*), MAX(logged_in_at)
FROM login_events
WHERE logged_in_at > NOW() - INTERVAL '1 hour'
GROUP BY user_id
ON CONFLICT (user_id) DO UPDATE SET
    login_count = user_stats.login_count + EXCLUDED.login_count,
    last_login = GREATEST(user_stats.last_login, EXCLUDED.last_login);
```

## INSERT ... ON CONFLICT və `FILTER`

```sql
-- Yalnız müəyyən şərtdə update et
INSERT INTO products (id, price) VALUES (1, 100)
ON CONFLICT (id) DO UPDATE SET 
    price = EXCLUDED.price
WHERE products.price != EXCLUDED.price;      -- yalnız fərqli olsa
```

## INSERT ilə DEFAULT və NULL

```sql
-- DEFAULT keyword - DEFAULT dəyəri tətbiq et
INSERT INTO users (name, email, role) VALUES ('Ali', 'ali@x.com', DEFAULT);

-- NULL - sütunu NULL et
INSERT INTO users (name, email, middle_name) VALUES ('Ali', 'ali@x.com', NULL);

-- Sütunu ignore et (DEFAULT tətbiq olunur)
INSERT INTO users (name, email) VALUES ('Ali', 'ali@x.com');
-- role, middle_name, created_at — DEFAULT-larını alır
```

## Bulk Insert Performans

### PostgreSQL: COPY (en sürətli)

```sql
-- CSV-dən millionlarla row
COPY users (name, email) FROM '/tmp/users.csv' WITH (FORMAT csv, HEADER true);

-- stdin-dən
COPY users (name, email) FROM STDIN WITH (FORMAT csv);
-- <CSV data>
-- \.
```

### MySQL: LOAD DATA

```sql
LOAD DATA INFILE '/tmp/users.csv'
INTO TABLE users
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(name, email);
```

### Transaction + Batch

```sql
BEGIN;
INSERT INTO users (name, email) VALUES 
    ('User1', 'u1@x.com'),
    ('User2', 'u2@x.com'),
    -- ... 1000 row
    ;
INSERT INTO users (name, email) VALUES 
    -- 1000-2000 row
    ;
COMMIT;
```

**Qayda:** Single transaction-da batch — 1 transaction-in overhead-ini paylaşır.

## Auto-Increment ID Alma

### MySQL

```sql
INSERT INTO users (name, email) VALUES ('Ali', 'ali@x.com');
SELECT LAST_INSERT_ID();                   -- yeni yaradılan ID

-- Multi-row: LAST_INSERT_ID() İLK row-un ID-ni qaytarır
INSERT INTO users (name) VALUES ('A'), ('B'), ('C');
SELECT LAST_INSERT_ID();                   -- 'A'-nın ID-i (3 növ id yaradılsa da)
```

### PostgreSQL

```sql
-- RETURNING istifadə et
INSERT INTO users (name, email) VALUES ('Ali', 'ali@x.com')
RETURNING id;

-- Sequence nextval (ID-ni əvvəlcədən almaq)
SELECT nextval('users_id_seq');            -- növbəti ID
INSERT INTO users (id, name) VALUES (nextval('users_id_seq'), 'Ali');
```

## INSERT Səhvləri və Həll

### 1. Duplicate Key

```sql
-- ERROR: duplicate key value violates unique constraint
-- Həll: ON CONFLICT istifadə et (yuxarıda göstərildi)
```

### 2. NOT NULL Violation

```sql
-- ERROR: null value in column "email"
-- Həll: ya dəyər ver, ya da DEFAULT qoy, ya da NULL icazə ver
```

### 3. Foreign Key Violation

```sql
-- ERROR: insert violates foreign key constraint
-- Həll: referenced row əvvəlcə yaradılmalıdır
BEGIN;
INSERT INTO users (id, name) VALUES (1, 'Ali');
INSERT INTO orders (user_id, total) VALUES (1, 100);   -- indi OK
COMMIT;
```

### 4. CHECK Constraint Violation

```sql
-- ERROR: new row violates check constraint
-- Həll: constraint-ə uyğun dəyər ver
```

## Transaction-da INSERT

```sql
BEGIN;
INSERT INTO orders (user_id, total) VALUES (1, 100) RETURNING id;
-- assume returned id = 42
INSERT INTO order_items (order_id, product_id, quantity) VALUES (42, 5, 2);
INSERT INTO order_items (order_id, product_id, quantity) VALUES (42, 7, 1);
COMMIT;

-- Xəta olsa
ROLLBACK;                                  -- heç bir row əlavə olunmur
```

## Laravel Nümunəsi

```php
// Eloquent create
$user = User::create([
    'name' => 'Ali',
    'email' => 'ali@x.com',
]);
echo $user->id;       // auto-generated

// Mass insert (Eloquent events trigger OLUNMUR)
User::insert([
    ['name' => 'Ali', 'email' => 'ali@x.com'],
    ['name' => 'Veli', 'email' => 'veli@x.com'],
]);

// Insert və ID qaytarma (Query Builder)
$id = DB::table('users')->insertGetId([
    'name' => 'Ali',
    'email' => 'ali@x.com',
]);

// Upsert (Laravel 8+)
User::upsert(
    [
        ['email' => 'ali@x.com', 'name' => 'Ali', 'login_count' => 1],
        ['email' => 'veli@x.com', 'name' => 'Veli', 'login_count' => 1],
    ],
    ['email'],                         // unique key
    ['name', 'login_count']            // update sütunları
);

// INSERT SELECT
DB::table('users_archive')->insertUsing(
    ['id', 'name', 'email'],
    DB::table('users')->select('id', 'name', 'email')->where('active', 0)
);

// FirstOrCreate
$user = User::firstOrCreate(
    ['email' => 'ali@x.com'],           // axtarış
    ['name' => 'Ali', 'role' => 'user'] // yoxdursa bu dəyərlərlə yarad
);

// UpdateOrCreate
$user = User::updateOrCreate(
    ['email' => 'ali@x.com'],
    ['name' => 'Ali V.', 'last_login' => now()]
);
```

## Interview Sualları

**Q: 10,000 row-u ən sürətli necə insert edərsən?**
A: 
1. `COPY` (PG) / `LOAD DATA` (MySQL) — ən sürətli
2. Bulk insert batch-ları (500-1000 row/query) single transaction-da
3. Indexlər kənar edilir, yüklədikdən sonra geri qoyulur
4. `autocommit` off, `synchronous_commit` off (risk qəbul olunarsa)

**Q: PostgreSQL `INSERT ... ON CONFLICT DO UPDATE` və MySQL `ON DUPLICATE KEY UPDATE` fərqi?**
A: Funksional eynidir. PostgreSQL `EXCLUDED` tablosunu istifadə edir (yeni cəhd edilən dəyər). MySQL `VALUES(col)` funksiyasını istifadə edir. PG daha güclü — conflict target-i (hansi unique constraint?) dəqiq göstərmək olar.

**Q: `RETURNING` olmayan MySQL-də necə yeni ID alarsan?**
A: `LAST_INSERT_ID()` funksiyası (session-scope). Multi-row insert-də ilk row-un ID-ni qaytarır, digərləri sequential.

**Q: `INSERT` edib `SELECT`-də dərhal tapmadıqda niyə?**
A: Replication lag ola bilər (master-slave). Read query replica-ya gedir, yazı hələ oraya çatmayıb. Həll: sticky session və ya `read-after-write` üçün master-dən oxu.

**Q: 1 milyon row insert edərkən transaction vacibdir?**
A: Hə. Single transaction overhead paylaşılır. Amma çox böyük transaction WAL/binlog şişirir — batch-lara böl (hər 10k-də commit).
