# Transaction Commands (BEGIN / COMMIT / ROLLBACK / SAVEPOINT) (Junior)

Transaction — bir neçə əməliyyatı **bütöv** vahid kimi icra etmək. Ya hamısı keçir (COMMIT), ya heç biri (ROLLBACK).

**Qeyd:** ACID və isolation level konseptləri üçün: `21-acid-and-transactions.md`, `40-isolation-levels.md`.

## BEGIN / START TRANSACTION

Transaction başlat.

```sql
-- PostgreSQL (hər ikisi)
BEGIN;
START TRANSACTION;

-- MySQL
START TRANSACTION;
BEGIN;

-- İsolation level ilə
BEGIN ISOLATION LEVEL READ COMMITTED;
START TRANSACTION ISOLATION LEVEL SERIALIZABLE;
```

## COMMIT

Transaction-i yekunlaşdır, **dəyişiklikləri qeyd et**.

```sql
BEGIN;
INSERT INTO orders (user_id, total) VALUES (1, 100);
UPDATE users SET order_count = order_count + 1 WHERE id = 1;
COMMIT;
-- Hər iki dəyişiklik saxlanıldı
```

## ROLLBACK

Transaction-i **ləğv et**, dəyişiklikləri geri al.

```sql
BEGIN;
INSERT INTO orders (user_id, total) VALUES (1, 100);
UPDATE users SET balance = balance - 100 WHERE id = 1;
-- balance < 0 olur - səhv!
ROLLBACK;
-- Heç nə dəyişmədi
```

## Auto-commit

Default: hər statement avtomatik COMMIT olunur (transaction "tək statement").

```sql
-- Default (auto-commit ON)
INSERT INTO users (name) VALUES ('Ali');       -- avtomatik COMMIT

-- Auto-commit off etmək üçün:
-- PostgreSQL: auto-commit həmişə ON, BEGIN ilə transaction başlat
-- MySQL:
SET autocommit = 0;
INSERT INTO users (name) VALUES ('Ali');       -- COMMIT olunmur
COMMIT;                                        -- əllə
```

**Qayda:** Application kodda həmişə explicit `BEGIN` / `COMMIT` istifadə et.

## Klassik Nümunə: Money Transfer

```sql
-- Ali'den Veli'ya 100 köçür
BEGIN;
UPDATE accounts SET balance = balance - 100 WHERE user_id = 1;  -- Ali
UPDATE accounts SET balance = balance + 100 WHERE user_id = 2;  -- Veli
COMMIT;

-- Əgər birinci keçsə, ikinci səhv olsa?
BEGIN;
UPDATE accounts SET balance = balance - 100 WHERE user_id = 1;
-- ERROR: server crashes here
-- Ali 100 itirdi, Veli heç nə almadı!

-- Transaction ilə:
-- Crash → heç bir UPDATE commit olmur → balans öncəki kimidir
```

## SAVEPOINT — Nested Transaction

Transaction içində "checkpoint" — yalnız bir hissəni rollback etmək olur.

```sql
BEGIN;
INSERT INTO orders (user_id, total) VALUES (1, 100);

SAVEPOINT before_items;

INSERT INTO order_items (order_id, product_id) VALUES (1, 5);
INSERT INTO order_items (order_id, product_id) VALUES (1, 7);

-- Səhv oldu - item-lərə geri qayıt, amma order qalsın
ROLLBACK TO SAVEPOINT before_items;

-- Alternative item
INSERT INTO order_items (order_id, product_id) VALUES (1, 9);

COMMIT;
-- orders row + 1 item (id=9) saxlanıldı
```

### SAVEPOINT RELEASE

```sql
-- SAVEPOINT artıq lazım deyilsə free et
RELEASE SAVEPOINT before_items;
```

## Read-Only Transaction

Yalnız SELECT edəcəksənsə — performans daha yaxşı (locks az).

```sql
-- PostgreSQL
BEGIN READ ONLY;
SELECT * FROM users;
SELECT * FROM orders;
COMMIT;

-- MySQL
START TRANSACTION READ ONLY;
```

**İstifadə:** Uzun SELECT-lər, consistency view-lərdə.

## Isolation Level Dəyişmək

```sql
-- Transaction başlatmadan əvvəl
SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;
BEGIN;
-- SELECT-lər consistent snapshot görür
COMMIT;

-- Session-də default dəyişmək (MySQL)
SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;

-- Transaction başlayarkən
BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE;
```

Ətraflı: `40-isolation-levels.md`.

## Explicit Locking Transaction-da

```sql
-- SELECT ... FOR UPDATE (row-level exclusive lock)
BEGIN;
SELECT * FROM accounts WHERE user_id = 1 FOR UPDATE;
-- Bu row digər transaction-ların UPDATE-i üçün lock-lanır
UPDATE accounts SET balance = balance - 100 WHERE user_id = 1;
COMMIT;

-- SELECT ... FOR SHARE (row-level share lock)
BEGIN;
SELECT * FROM products WHERE id = 1 FOR SHARE;
-- Başqa SELECT FOR SHARE icazə verilir, UPDATE gözləyir
COMMIT;

-- SKIP LOCKED (locked olanları atla - queue üçün)
BEGIN;
SELECT * FROM jobs 
WHERE status = 'pending' 
ORDER BY created_at 
LIMIT 1 
FOR UPDATE SKIP LOCKED;     -- başqası işlədiyini atla
-- İş gör
UPDATE jobs SET status = 'done' WHERE id = ?;
COMMIT;
```

Ətraflı: `41-locking-and-deadlocks.md`.

## Transaction Uzunluğu

```sql
-- BAD - uzun transaction
BEGIN;
SELECT * FROM users;                                -- 10sn
-- Application external API çağırır (30sn)
UPDATE users SET ...;
COMMIT;
-- 40 saniyəlik lock — digər transaction-ları bloklayır

-- GOOD - transaction qısa olsun
SELECT * FROM users;                                -- transaction-dan kənar
-- External API call
BEGIN;
UPDATE users SET ...;
COMMIT;                                             -- millisaniyələr
```

**Qayda:** Transaction içində:
- External API çağırma
- File I/O etmə  
- Interactive user input gözləmə

## Deadlock

```sql
-- Transaction A:
BEGIN;
UPDATE a SET x = 1 WHERE id = 1;  -- lock A:1
UPDATE b SET x = 1 WHERE id = 2;  -- WAITS for lock B:2

-- Transaction B:
BEGIN;
UPDATE b SET x = 1 WHERE id = 2;  -- lock B:2
UPDATE a SET x = 1 WHERE id = 1;  -- WAITS for lock A:1

-- Deadlock! DB birini kill edir:
-- ERROR: deadlock detected
```

**Həll:** Lock-ları **consistent order**-də al (məs, həmişə id ascending).

Ətraflı: `41-locking-and-deadlocks.md`.

## Transaction və DDL

```sql
-- PostgreSQL: DDL transactional!
BEGIN;
ALTER TABLE users ADD COLUMN x VARCHAR(20);
INSERT INTO users (name, x) VALUES ('Ali', 'val');
ROLLBACK;
-- ALTER də rollback olundu - sütun yoxdur

-- MySQL: DDL implicit COMMIT
BEGIN;
ALTER TABLE users ADD COLUMN x VARCHAR(20);   -- bura COMMIT olur!
INSERT INTO users (name) VALUES ('Ali');      -- yeni transaction
ROLLBACK;
-- Yalnız INSERT rollback olundu, ALTER qaldı
```

**Qayda:** MySQL-də DDL-ı transactional saymayın.

## Nested BEGIN

```sql
-- PostgreSQL: xəta
BEGIN;
BEGIN;                                      -- WARNING: transaction already in progress
-- SAVEPOINT istifadə et

-- MySQL: ikinci BEGIN əvvəlkini implicit COMMIT edir!
BEGIN;
INSERT ...;
BEGIN;                                      -- implicit COMMIT!
-- İkinci transaction
```

**Qayda:** Application tərəfində "nested transaction" üçün SAVEPOINT istifadə et.

## Transaction Status Yoxla

```sql
-- PostgreSQL
SELECT pg_current_xact_id();                        -- current txid
SELECT * FROM pg_stat_activity WHERE state = 'idle in transaction';
-- "idle in transaction" long-running = problem

-- MySQL
SHOW ENGINE INNODB STATUS;
SELECT * FROM information_schema.INNODB_TRX;
```

## Laravel Nümunəsi

```php
// Transaction (automatic rollback on exception)
DB::transaction(function () {
    DB::table('accounts')->where('id', 1)->decrement('balance', 100);
    DB::table('accounts')->where('id', 2)->increment('balance', 100);
    
    // Exception → automatic ROLLBACK
});

// Manual control
DB::beginTransaction();
try {
    DB::table('orders')->insert([...]);
    DB::table('order_items')->insert([...]);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// Retry on deadlock (Laravel built-in)
DB::transaction(function () {
    // ...
}, attempts: 3);            // 3 dəfə retry et deadlock olarsa

// Savepoint - automatic nested transaction (Laravel)
DB::transaction(function () {
    User::create([...]);
    
    DB::transaction(function () {    // Laravel SAVEPOINT istifadə edir
        Order::create([...]);
        // throw → yalnız Order rollback, User qalır
    });
});

// Isolation level
DB::transaction(function () {
    DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    // ...
});

// FOR UPDATE
$user = User::where('id', 1)->lockForUpdate()->first();
$user->balance -= 100;
$user->save();
```

## Best Practices

1. **Kiçik transactions** — lock-ları saniyələrlə tutma
2. **External call-ları transaction-dan kənar** — retry ilə idempotency
3. **Consistent lock order** — deadlock qarşısı
4. **Read-only-ləri explicit et** — optimizer yaxşılaşdırır
5. **Retry deadlock-da** — transient error
6. **Uzun SELECT MVCC cleanup blayır** (PG) — monitor et

## Interview Sualları

**Q: BEGIN / COMMIT / ROLLBACK nə üçün lazımdır?**
A: Atomicity — bir neçə əməliyyatın ya hamısı, ya heç biri. Money transfer klassik nümunədir — iki UPDATE arasında crash olarsa rollback ilə məlumat bütövlüyü qorunur.

**Q: Auto-commit OFF etmək bəyənildimi?**
A: Application tərəfində əllə kontrol daha aydındır — explicit `BEGIN / COMMIT`. Global OFF unutma və unclosed transaction riski yaradır.

**Q: SAVEPOINT nə üçün lazımdır?**
A: Nested transaction — transaction ortasında partial rollback. Laravel kimi ORM-lər `DB::transaction` iç-içə çağrışlarını SAVEPOINT-lə implement edir.

**Q: Uzun transaction niyə təhlükəlidir?**
A: 
1. Lock-lar uzun tutulur — digər transaction bloklanır
2. PG-də "dead tuple"-ların temizlənməsi (VACUUM) gecikir
3. Replication lag artır
4. Rollback uzun sürür

**Q: `SELECT ... FOR UPDATE` nə vaxt istifadə edilir?**
A: Read-modify-write pattern-də — row-u oxuyub dəyəri modify edib geri yazmalı oluruq və araada kimsə dəyişdirməsinə icazə vermək istəmirik. Məs, account balance, inventory.

**Q: Deadlock nə üçün olur və necə həll olunur?**
A: İki transaction bir-birinin tutduğu lock-u gözləyir (circular wait). DB birini kill edir (`deadlock detected` error). Həll: lock-ları həmişə consistent sırada al (id ASC), transaction-ı qisa saxla, retry mexanizmi əlavə et.
