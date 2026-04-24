# UPDATE Statement

> **Seviyye:** Beginner ⭐

Mövcud row-ları dəyişdirmək. **WHERE olmayan UPDATE — TƏHLÜKƏLİ** (bütün table dəyişir).

## Basic UPDATE

```sql
-- Tək sütun
UPDATE users SET name = 'Ali V.' WHERE id = 1;

-- Bir neçə sütun
UPDATE users SET 
    name = 'Ali V.',
    email = 'aliv@x.com',
    updated_at = NOW()
WHERE id = 1;
```

**KRİTİK qayda:** Həmişə WHERE olsun! Olmazsa bütün table update olur.

```sql
-- PRODUCTION-DA MƏHV EDİCİ
UPDATE users SET role = 'admin';           -- BÜTÜN user-lər admin olur!

-- Test əvvəlcə SELECT ilə
SELECT * FROM users WHERE id = 1;          -- hansi row dəyişəcək?
-- Əmin olduqdan sonra UPDATE
```

## Hesablamalar İlə UPDATE

```sql
-- Sütunun özünə istinad
UPDATE products SET price = price * 1.18 WHERE category = 'electronics';

-- Counter artır
UPDATE users SET login_count = login_count + 1 WHERE id = 1;

-- COALESCE ilə NULL-dən qoru
UPDATE users SET points = COALESCE(points, 0) + 10 WHERE id = 1;
```

## UPDATE + FROM (PostgreSQL) / UPDATE + JOIN (MySQL)

Başqa table-dan dəyərləri istifadə edərək update.

### PostgreSQL: UPDATE ... FROM

```sql
-- users cədvəlini orders-dən agreggate ilə update et
UPDATE users u SET 
    total_orders = stats.cnt,
    total_spent = stats.total
FROM (
    SELECT user_id, COUNT(*) AS cnt, SUM(total) AS total
    FROM orders 
    WHERE status = 'paid'
    GROUP BY user_id
) stats
WHERE u.id = stats.user_id;

-- Sadə JOIN-like
UPDATE orders o SET 
    customer_name = u.name
FROM users u
WHERE o.user_id = u.id AND o.customer_name IS NULL;
```

### MySQL: UPDATE ... JOIN

```sql
-- MySQL-də FROM-yoxdur, JOIN syntax
UPDATE users u
JOIN (
    SELECT user_id, COUNT(*) AS cnt, SUM(total) AS total
    FROM orders WHERE status = 'paid'
    GROUP BY user_id
) stats ON stats.user_id = u.id
SET u.total_orders = stats.cnt,
    u.total_spent = stats.total;

-- Sadə UPDATE JOIN
UPDATE orders o
JOIN users u ON u.id = o.user_id
SET o.customer_name = u.name
WHERE o.customer_name IS NULL;
```

## CASE WHEN ile Şərtli UPDATE

```sql
-- Role-a görə fərqli artım
UPDATE employees
SET salary = CASE role
    WHEN 'senior' THEN salary * 1.15
    WHEN 'mid'    THEN salary * 1.10
    WHEN 'junior' THEN salary * 1.05
    ELSE salary
END
WHERE department = 'engineering';

-- Bu 1 query-di. "Looping" əvəzinə çox sürətli!
```

## UPDATE ... RETURNING

```sql
-- PostgreSQL
UPDATE users SET login_count = login_count + 1, last_login = NOW()
WHERE id = 1
RETURNING id, login_count, last_login;

-- Bulk update ilə - bütün dəyişən row-lar
UPDATE orders SET status = 'shipped' 
WHERE status = 'pending' AND created_at < NOW() - INTERVAL '1 day'
RETURNING id, user_id;

-- MySQL 8.0.32+: RETURNING dəstək
```

**İstifadə:** Application-a nə dəyişdiyini bildirmək, audit log, notification.

## UPDATE ... LIMIT (MySQL only)

```sql
-- Yalnız 100 row update et
UPDATE orders SET status = 'archived' 
WHERE created_at < '2025-01-01' 
LIMIT 100;

-- Batch-da böyük table update - loop ilə
-- PHP/SQL kod:
-- DO { UPDATE ... LIMIT 1000 } WHILE affected_rows > 0
```

**Niyə lazım?** Millionlarla row-u bir transactionda update etmək **lock-lar** tutur, WAL şişirir, replication gecikir. Batch-lara böl.

### PostgreSQL batch update (LIMIT yoxdur)

```sql
-- Subquery ilə
WITH batch AS (
    SELECT id FROM orders 
    WHERE status = 'pending' AND created_at < '2025-01-01'
    LIMIT 1000
)
UPDATE orders SET status = 'archived' 
WHERE id IN (SELECT id FROM batch);

-- Loop ilə application tərəfindən
-- Loop: while (affected > 0) { UPDATE ... }
```

## UPDATE + Subquery

```sql
-- User-in ən son order tarixi
UPDATE users u SET 
    last_order_at = (
        SELECT MAX(created_at) 
        FROM orders 
        WHERE user_id = u.id
    );

-- Daha sürətli: JOIN versiyası
UPDATE users u
SET last_order_at = (SELECT MAX(created_at) FROM orders o WHERE o.user_id = u.id);
-- (PG: FROM, MySQL: JOIN)
```

## Optimistic Locking ilə UPDATE

```sql
-- Version column istifadə
UPDATE products 
SET price = 100, version = version + 1
WHERE id = 1 AND version = 5;

-- Əgər affected_rows = 0, başqası update etmişdir - retry
```

Ətraflı: `42-optimistic-locking.md`.

## UPDATE Performansı

### 1. Index-ə söykənmiş WHERE

```sql
-- BAD: full table scan
UPDATE orders SET archived = TRUE WHERE DATE(created_at) < '2025-01-01';

-- GOOD: index istifadə et
UPDATE orders SET archived = TRUE WHERE created_at < '2025-01-01';
```

### 2. Lock-ları minimallaşdır

```sql
-- BAD: hər order-i ayrı transaction-da update et - çox connection overhead
FOR order_id IN (...) DO
    UPDATE orders SET status = 'x' WHERE id = order_id;
END;

-- GOOD: bulk update
UPDATE orders SET status = 'x' WHERE id IN (1, 2, 3, ...);

-- DAHA YAXŞI (milyon row): batch
-- WHILE (1)  
--   UPDATE ... WHERE ... LIMIT 1000;
--   SLEEP 0.1;  -- replication-ə nəfəs ver
-- END
```

### 3. Dəyişməyən dəyəri update etmə

```sql
-- BAD: indi mövcud olan dəyər ilə update - tətbiq olunsa da lock və WAL yaradır
UPDATE products SET price = 100 WHERE id = 1;
-- əgər price onsuz da 100-durs - lazımsız iş

-- GOOD: dəyişdirmə yoxdursa skip et
UPDATE products SET price = 100 WHERE id = 1 AND price != 100;
```

## Audit Trail

```sql
-- updated_at avtomatik
CREATE TRIGGER update_users_updated_at 
BEFORE UPDATE ON users 
FOR EACH ROW 
EXECUTE FUNCTION update_updated_at_column();

-- Və ya UPDATE-də explicit
UPDATE users SET name = 'Ali V.', updated_at = NOW() WHERE id = 1;

-- Laravel: timestamps = true olduqda avtomatik edilir
```

## Safe Update Mode (MySQL)

```sql
-- MySQL Workbench default: WHERE key column olmadan UPDATE error verir
SET SQL_SAFE_UPDATES = 1;
UPDATE users SET name = 'X';
-- ERROR: You are using safe update mode...

-- Off:
SET SQL_SAFE_UPDATES = 0;
```

**Production-da** həmişə ON saxla - məhv edici UPDATE-lərin qarşısını alır.

## UPSERT vs UPDATE

```sql
-- UPDATE: row olmalıdır əvvəl
UPDATE users SET login_count = login_count + 1 WHERE email = 'ali@x.com';
-- Əgər user yoxdursa, heç nə dəyişmir (affected_rows = 0)

-- UPSERT: varsa update, yoxdursa insert
INSERT INTO users (email, login_count) VALUES ('ali@x.com', 1)
ON CONFLICT (email) DO UPDATE SET login_count = users.login_count + 1;
```

Ətraflı: `14-insert-statement.md` (UPSERT hissəsi) + `38-bulk-operations-and-upsert.md`.

## Laravel Nümunəsi

```php
// Eloquent
$user = User::find(1);
$user->name = 'Ali V.';
$user->save();

// Mass update (events trigger olmur)
User::where('id', 1)->update(['name' => 'Ali V.']);

// Bulk update ilə CASE
DB::table('employees')->whereIn('role', ['senior', 'mid'])->update([
    'salary' => DB::raw("CASE role 
        WHEN 'senior' THEN salary * 1.15 
        WHEN 'mid' THEN salary * 1.10 END")
]);

// Increment / Decrement
User::where('id', 1)->increment('login_count');
User::where('id', 1)->increment('login_count', 5);
User::where('id', 1)->decrement('balance', 100);

// UpdateOrCreate (UPSERT)
User::updateOrCreate(
    ['email' => 'ali@x.com'],
    ['name' => 'Ali', 'last_login' => now()]
);

// JOIN update
DB::table('orders')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->update(['orders.customer_name' => DB::raw('users.name')]);

// Batch update (böyük table üçün)
$affected = 1;
while ($affected > 0) {
    $affected = DB::table('orders')
        ->where('status', 'pending')
        ->where('created_at', '<', now()->subMonth())
        ->limit(1000)
        ->update(['status' => 'archived']);
    
    sleep(1);  // replication-ə nəfəs ver
}
```

## Interview Sualları

**Q: `UPDATE` edəndə affected_rows niyə 0 ola bilər?**
A: Səbəb:
1. WHERE şərtinə uyğun row yoxdur
2. Update olunan dəyər mövcud dəyərlə eynidir (bəzi DB-lərdə)
3. Optimistic lock qeydiyyatdan keçə bilmədi (version uyğun deyil)

**Q: 10 milyon row-u necə update etməli?**
A: 
1. Batch-lara böl (1000-10000 per batch)
2. Hər batch single transaction
3. Batch arası sleep (replication-a nəfəs, other query-lərə yer)
4. WHERE indexed sütunda olsun
5. BAD: hər row üçün ayrı UPDATE (connection overhead)
6. BAD: 1 transaction-da hamısı (lock, WAL şişməsi, replication lag)

**Q: `UPDATE users SET password = ?` niyə təhlükəli?**
A: WHERE yoxdur — bütün user-lərin password-u dəyişir. Həmişə WHERE yaz. `SQL_SAFE_UPDATES=1` ilə bu səhv qarşısı alınır.

**Q: PostgreSQL UPDATE MVCC-də necə işləyir?**
A: UPDATE = row-un yeni versiyasını yaradır (köhnəni "dead tuple" edir). VACUUM köhnə versiyaları silir. Bu həm də niyə UPDATE həmişə yeni heap row pozisiyası tutur (HOT update istisnaları ilə). Ətraflı: `66-mvcc-deep-dive.md`.

**Q: `UPDATE` altında lock davranışı?**
A: UPDATE hədəf row-larda **row-level lock** (`FOR UPDATE` implicit) yaradır. Əgər WHERE şərti index istifadə etmirsə, bir çox row locked olur. Lock isolation level-dən asılıdır.
