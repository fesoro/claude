# DELETE & TRUNCATE (Junior)

Row-ları silmək. DELETE vs TRUNCATE vs Soft Delete — hər birinin öz istifadə halı var.

## Basic DELETE

```sql
-- WHERE ilə (həmişə!)
DELETE FROM users WHERE id = 1;
DELETE FROM orders WHERE status = 'cancelled' AND created_at < '2025-01-01';

-- DELETE-dən əvvəl SELECT ilə test et
SELECT * FROM users WHERE id = 1;
-- Əmin olduqdan sonra:
DELETE FROM users WHERE id = 1;
```

**KRİTİK:** WHERE olmasa **bütün** table silinir!

```sql
-- MƏHV EDİCİ (production-da BAĞLAMA!)
DELETE FROM users;                         -- bütün user-lər silinir
```

## DELETE + JOIN

### PostgreSQL: DELETE ... USING

```sql
-- Active olmayan user-lərin sifarişlərini sil
DELETE FROM orders o
USING users u
WHERE o.user_id = u.id AND u.active = FALSE;

-- Bir neçə JOIN
DELETE FROM order_items oi
USING orders o, users u
WHERE oi.order_id = o.id 
  AND o.user_id = u.id 
  AND u.deleted_at IS NOT NULL;
```

### MySQL: DELETE ... JOIN

```sql
-- MySQL syntax
DELETE o FROM orders o
JOIN users u ON u.id = o.user_id
WHERE u.active = FALSE;

-- Hansı table-dan sildiyini aydın göstər
DELETE o, oi FROM orders o
JOIN order_items oi ON oi.order_id = o.id
JOIN users u ON u.id = o.user_id
WHERE u.active = FALSE;
-- orders və order_items hər ikisindən sil
```

## DELETE ... RETURNING (PostgreSQL)

```sql
-- Silinən row-ları qaytar
DELETE FROM orders 
WHERE status = 'cancelled' AND created_at < '2025-01-01'
RETURNING id, user_id, total;

-- İstifadə: audit log, backup, notification
```

## DELETE ... LIMIT (MySQL only)

```sql
-- Batch delete
DELETE FROM old_logs WHERE created_at < '2024-01-01' LIMIT 1000;

-- Loop ilə böyük table təmizləmək
-- WHILE (affected > 0)
--   DELETE FROM old_logs ... LIMIT 10000;
--   SLEEP 0.5;
-- END
```

### PostgreSQL batch delete

```sql
-- PostgreSQL-də LIMIT yoxdur - subquery ilə
DELETE FROM old_logs 
WHERE id IN (
    SELECT id FROM old_logs 
    WHERE created_at < '2024-01-01' 
    LIMIT 10000
);
```

**Niyə batch vacibdir:** Böyük DELETE:
1. Row-level lock-lar uzun müddət tutur
2. WAL/binlog şişirir
3. Replication lag yaradır
4. Transaction rollback-i baha olur (səhv olsa)

## Foreign Key + DELETE

```sql
-- CASCADE - user silinəndə orders də silinir
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE
);

-- RESTRICT (default) - referenced row silinə bilməz
CREATE TABLE orders (
    user_id BIGINT REFERENCES users(id) ON DELETE RESTRICT
);
-- DELETE FROM users WHERE id = 1; → ERROR əgər order var

-- SET NULL - user silinəndə orders.user_id NULL olur
CREATE TABLE orders (
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL
);

-- SET DEFAULT
CREATE TABLE orders (
    user_id BIGINT REFERENCES users(id) ON DELETE SET DEFAULT
);
```

Ətraflı FK davranışı: `46-foreign-keys-deep.md`.

## TRUNCATE — Table-ı tamamilə boşalt

```sql
-- Bütün row-ları sil (table structure qalır)
TRUNCATE TABLE logs;
-- MySQL: TRUNCATE logs;
-- PostgreSQL: TRUNCATE logs;
```

### TRUNCATE vs DELETE FROM

| Xüsusiyyət | DELETE FROM (WHERE-siz) | TRUNCATE |
|------------|-------------------------|----------|
| Sürət | Slow (row-row) | Çox sürətli (table file-i silir) |
| WAL/binlog | Hər row üçün log | Minimal log |
| Trigger | ON DELETE triggers chag işləyir | Trigger işləmir |
| Foreign key | Cascades işləyir | PG: CASCADE/RESTART ayrıca. MySQL: FK varsa ERROR |
| Auto-increment | Sıfırlanmır | Sıfırlanır (MySQL default, PG: RESTART IDENTITY ilə) |
| Rollback | Transaction-da rollback edilir | PG: bəli, MySQL: DDL kimi (implicit commit) |

```sql
-- PostgreSQL TRUNCATE seçimləri
TRUNCATE TABLE logs RESTART IDENTITY;       -- sequence sıfırla
TRUNCATE TABLE logs CASCADE;                -- foreign key-li table-lara da tətbiq et
TRUNCATE TABLE logs, audit_logs;           -- bir neçə table birgə

-- MySQL: FK olanda ERROR
-- Disable FK:
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE logs;
SET FOREIGN_KEY_CHECKS = 1;
```

## Soft Delete

Row-u **fiziki silmək** əvəzinə **deleted_at flag** qoy.

```sql
-- deleted_at column əlavə et
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMPTZ;

-- "Sil" - set flag
UPDATE users SET deleted_at = NOW() WHERE id = 1;

-- Aktiv user-ləri seç
SELECT * FROM users WHERE deleted_at IS NULL;

-- Bərpa et
UPDATE users SET deleted_at = NULL WHERE id = 1;
```

**Üstünlük:** Məlumat saxlanır, restore edilə bilər, audit saxlanır.
**Çatışmazlıq:** Hər query `WHERE deleted_at IS NULL` lazımdır. Unique constraint-lar çətinləşir.

Ətraflı: `47-soft-deletes-patterns.md`.

## DELETE Performans

### 1. Index-li WHERE

```sql
-- BAD: full table scan
DELETE FROM orders WHERE DATE(created_at) < '2025-01-01';

-- GOOD
DELETE FROM orders WHERE created_at < '2025-01-01';
```

### 2. Partition Pruning (böyük table)

```sql
-- 1 illik partition-ları bir anda sil
ALTER TABLE orders DROP PARTITION p_2024;       -- MySQL
-- PostgreSQL: DETACH PARTITION + DROP
-- Bu millionlarla DELETE-dən milyon dəfə sürətlidir
```

Partition strategy: `60-sharding-and-partitioning.md`.

### 3. Batch DELETE

```sql
-- Böyük delete - batch ilə
WHILE affected > 0:
    DELETE FROM old_logs WHERE created_at < '2025-01-01' LIMIT 10000;
    COMMIT;
    SLEEP(0.5);
END;
```

### 4. PostgreSQL: DELETE və VACUUM

```sql
-- DELETE PG-də row-u həqiqətən silmir, "dead tuple" işarələyir
DELETE FROM users WHERE id = 1;

-- VACUUM-dan sonra space geri qaytarılır
VACUUM users;

-- Space-i dərhal qaytarmaq üçün
VACUUM FULL users;                          -- exclusive lock alır!
```

Ətraflı: `68-vacuum-and-bloat.md`.

## DROP TABLE — Schema-nı sil

DELETE/TRUNCATE table-ın **məzmununu** silir. DROP table-ın **özünü** silir.

```sql
DROP TABLE logs;                            -- table silinir
DROP TABLE IF EXISTS logs;                  -- yoxdursa error verme
DROP TABLE logs CASCADE;                    -- FK-lı digər table-lara da tətbiq et
```

**Xəbərdarlıq:** DROP geri qaytarılmaz (backup-siz).

## Safe Delete Mode (MySQL)

```sql
-- Safe updates mode - WHERE key olmadan DELETE error verir
SET SQL_SAFE_UPDATES = 1;

DELETE FROM users WHERE name = 'Ali';       -- ERROR (PK WHERE deyil)
DELETE FROM users WHERE id = 1;             -- OK
```

**Production-da ON** saxla.

## Transaction + DELETE

```sql
BEGIN;
DELETE FROM orders WHERE user_id = 1;
DELETE FROM addresses WHERE user_id = 1;
DELETE FROM users WHERE id = 1;
COMMIT;

-- Səhv olsa
ROLLBACK;
-- Hecne silinmir
```

## DELETE Səhvləri

### 1. Foreign key violation

```sql
-- ERROR: update or delete on table "users" violates foreign key constraint
-- Səbəb: orders table-ında user-in sifarişi var
-- Həll 1: ON DELETE CASCADE
-- Həll 2: əvvəlcə child row-ları sil
BEGIN;
DELETE FROM orders WHERE user_id = 1;
DELETE FROM users WHERE id = 1;
COMMIT;
```

### 2. Böyük DELETE çöküş

```sql
-- 10M row DELETE - timeout, lock escalation, replication lag
-- Həll: batch ilə
```

### 3. TRUNCATE və FK (MySQL)

```sql
-- MySQL TRUNCATE FK-lı table-da ERROR
-- Həll 1: FK-ni drop et, TRUNCATE, FK-ni geri qoy
-- Həll 2: DELETE FROM (daha yavaş)
```

## Laravel Nümunəsi

```php
// Eloquent
User::find(1)->delete();
User::where('id', 1)->delete();
User::destroy(1);
User::destroy([1, 2, 3]);

// Where-siz delete
User::truncate();                           // PK FK olmadıqda

// Soft delete (SoftDeletes trait)
class User extends Model {
    use SoftDeletes;
}
User::find(1)->delete();                    // deleted_at set edir
User::withTrashed()->find(1);               // silinmişləri də daxil et
User::onlyTrashed()->get();                 // yalnız silinmişlər
User::find(1)->restore();                   // bərpa
User::find(1)->forceDelete();               // həqiqi silmək

// Batch delete
User::where('active', false)->chunkById(1000, function ($users) {
    foreach ($users as $user) {
        $user->delete();
    }
});

// Raw DELETE
DB::table('orders')->where('status', 'cancelled')->delete();
DB::statement('TRUNCATE TABLE logs');
```

## Interview Sualları

**Q: `DELETE FROM table` və `TRUNCATE table` fərqi?**
A: 
- DELETE: row-row silir, WAL/binlog yaradır, trigger işləyir, rollback edilə bilər
- TRUNCATE: çox sürətli (file-level), minimal log, trigger işləmir, auto-increment sıfırlanır

**Q: 100M row-lu table-dan 50M-ni necə silərsən?**
A:
1. Əgər çox silinir — yeni table yaradıb saxlanacaqları köçür, sonra DROP old
2. Partition varsa — partition drop
3. Yox, batch DELETE (10k-lik) + VACUUM

**Q: Soft delete nə vaxt istifadə etməli?**
A: 
- User-lərin məlumatı bərpa oluna bilməlidir
- Audit/compliance üçün history lazımdır
- Hard delete digər sistemləri sındıra bilər (order → user əlaqəsi)
NƏ ETMƏ: GDPR "right to be forgotten" — soft delete bəs deyil, hard delete lazımdır.

**Q: `DELETE` və `DROP` fərqi?**
A: DELETE row-ları silir, table qalır. DROP table-ın özünü silir (schema + data).

**Q: PostgreSQL-də DELETE-dən sonra disk boşalmır?**
A: MVCC - dead tuple-lar saxlanır. `VACUUM` onları işarəliyir, `VACUUM FULL` space-i geri qaytarır (amma exclusive lock alır).
