# UNION, INTERSECT, EXCEPT (Junior)

Set əməliyyatları — iki query-nin nəticəsini birləşdirir, kəsir və ya çıxarır.

## UNION

İki query nəticəsini **birləşdirir**. Duplicate-lər silinir.

```sql
-- Aktiv və pending user-ler (duplicate-siz)
SELECT id, name FROM active_users
UNION
SELECT id, name FROM pending_users;
```

### UNION Qaydaları

1. Hər iki query-də **eyni sayda** sütun olmalıdır
2. Sütun **tipləri** uyğun olmalıdır (uyğun olmayanlar cast olunmalıdır)
3. Sütun **adları** birinci query-dən alınır
4. `ORDER BY` yalnız axırda, ümumi nəticə üçün

```sql
SELECT id, name, 'active' AS source FROM active_users
UNION
SELECT id, name, 'pending' AS source FROM pending_users
ORDER BY name;      -- ORDER BY ümumi nəticədə
```

## UNION ALL

Duplicate-ləri **silmir**. Daha **sürətli** (dedup lazım deyil).

```sql
-- Bütün siffrlər (duplicate ola bilər)
SELECT user_id FROM orders
UNION ALL
SELECT user_id FROM pending_orders;
```

### UNION vs UNION ALL — hansını istifadə et?

```
UNION      → duplicate-ler OLMALI DEYIL (dedup = extra work)
UNION ALL  → duplicate olsa da olar (sürətli, default seçim)
```

**Qayda:** Mütləq duplicate aradan qaldırmaq lazım deyilsə, **UNION ALL** istifadə et. Çox daha sürətlidir.

## INTERSECT

Hər iki query-də **olan** row-ları qaytarır. Duplicate silinir.

```sql
-- Həm aktiv, həm VIP user-lər (ID hər iki table-da)
SELECT user_id FROM active_users
INTERSECT
SELECT user_id FROM vip_users;
```

**Alternativi (MySQL-də INTERSECT yoxdur - 8.0.31-də əlavə olundu):**

```sql
-- INNER JOIN ilə:
SELECT DISTINCT a.user_id 
FROM active_users a 
INNER JOIN vip_users v ON v.user_id = a.user_id;

-- EXISTS ilə:
SELECT user_id FROM active_users a
WHERE EXISTS (SELECT 1 FROM vip_users v WHERE v.user_id = a.user_id);
```

## EXCEPT (SQL Server) / MINUS (Oracle)

Birinci query-də olan, **ikinci query-də olmayan** row-lar.

```sql
-- PostgreSQL: EXCEPT
SELECT user_id FROM active_users
EXCEPT
SELECT user_id FROM banned_users;
-- Aktiv amma banned OLMAYAN

-- Oracle: MINUS (eyni effekt)
SELECT user_id FROM active_users
MINUS
SELECT user_id FROM banned_users;
```

**MySQL 8.0.31-den əvvəl EXCEPT yoxdur:**

```sql
-- NOT IN ile (NULL pitfall-a diqqət!)
SELECT user_id FROM active_users
WHERE user_id NOT IN (SELECT user_id FROM banned_users);

-- NOT EXISTS (NULL-safe):
SELECT user_id FROM active_users a
WHERE NOT EXISTS (SELECT 1 FROM banned_users b WHERE b.user_id = a.user_id);

-- LEFT JOIN trick:
SELECT a.user_id 
FROM active_users a 
LEFT JOIN banned_users b ON b.user_id = a.user_id 
WHERE b.user_id IS NULL;
```

## INTERSECT ALL / EXCEPT ALL

Duplicate-ləri **saxlayır** (standart DB-lərdə dəstəklənir).

```sql
-- Hər row neçə dəfə hər iki query-də varsa o qədər
SELECT user_id FROM orders
INTERSECT ALL
SELECT user_id FROM refunds;
-- Əgər user 3 sifariş edib 2 dəfə refund alıbsa, nəticə 2 row olur
```

## Praktik Nümunələr

### 1. Çox mənbədən user siyahısı

```sql
-- Bütün user mənbələri: sign-up, import, API
SELECT id, email, 'signup' AS source FROM signup_users
UNION ALL
SELECT id, email, 'import' AS source FROM imported_users
UNION ALL
SELECT id, email, 'api' AS source FROM api_users
ORDER BY email;
```

### 2. Həm PHP, həm JS bilən developer-lər

```sql
SELECT developer_id FROM skills WHERE skill = 'PHP'
INTERSECT
SELECT developer_id FROM skills WHERE skill = 'JavaScript';
```

### 3. Sifariş etmiş amma refund almamış user-lər

```sql
-- EXCEPT
SELECT DISTINCT user_id FROM orders
EXCEPT
SELECT DISTINCT user_id FROM refunds;
```

### 4. Data Reconciliation (iki mənbəni müqayisə et)

```sql
-- Local-də var, remote-da yox olan ID-lər
SELECT id FROM local_users
EXCEPT
SELECT id FROM remote_users;

-- Remote-da var, local-də yox olanlar
SELECT id FROM remote_users
EXCEPT
SELECT id FROM local_users;
```

## Column Sayı Uyğunlaşdırmaq

```sql
-- Query-lərin sütun sayı fərqli olsa:
SELECT id, name, email FROM users
UNION
SELECT id, name FROM admins;
-- ERROR: sütun sayı uyğun deyil

-- Həll: NULL ilə doldur
SELECT id, name, email FROM users
UNION
SELECT id, name, NULL AS email FROM admins;
```

## Column Tipləri Uyğun Olmalıdır

```sql
-- Cast lazım ola bilər
SELECT id::TEXT AS id_str FROM users        -- PostgreSQL cast
UNION
SELECT CAST(id AS CHAR) FROM legacy_users;

-- MySQL / PostgreSQL
SELECT CAST(id AS CHAR(20)) FROM users
UNION
SELECT code FROM codes;
```

## Operator Prioriteti

```sql
-- INTERSECT daha yüksək prioritetə sahibdir UNION-dan
SELECT id FROM a
UNION
SELECT id FROM b
INTERSECT 
SELECT id FROM c;

-- Aşağıdakı kimi parse olunur:
-- a UNION (b INTERSECT c)

-- Parantez istifadə et oxunması asan olsun:
SELECT id FROM a
UNION
(SELECT id FROM b INTERSECT SELECT id FROM c);
```

## Performance Məsləhəti

```sql
-- UNION duplicate-ləri yoxlayır (slow)
SELECT ...                  -- 1M row
UNION                       -- dedup
SELECT ...;                 -- 1M row

-- UNION ALL = dedup-siz (fast)
SELECT ... UNION ALL SELECT ...;

-- Dedup SİZİN TƏRƏFİNİZDƏ lazımsa, query-lərin hər birində distinct et:
SELECT DISTINCT ... UNION ALL SELECT DISTINCT ...;
-- Hər query-də ayrı dedup kiçik set-də işləyir, yekunda birləşir
```

## Laravel Nümunəsi

```php
// UNION
$active = DB::table('active_users')->select('id', 'name');
$pending = DB::table('pending_users')->select('id', 'name');
$all = $active->union($pending)->get();

// UNION ALL
$all = $active->unionAll($pending)->get();

// ORDER BY UNION sonra
$all = $active->unionAll($pending)->orderBy('name')->get();
```

## Interview Sualları

**Q: `UNION` və `UNION ALL` fərqi və hansı daha sürətlidir?**
A: `UNION` duplicate-ləri silir, `UNION ALL` silmir. `UNION ALL` daha sürətlidir — dedup işi yoxdur. Duplicate ola bilməyəcəyini bilirsənsə və ya duplicate-i istəyirsənsə — `UNION ALL`.

**Q: MySQL-də `EXCEPT` nə ilə əvəz olunur?**
A: `NOT IN` (NULL problem-i ilə), `NOT EXISTS` (NULL-safe), `LEFT JOIN ... WHERE right.id IS NULL` (anti-join pattern).

**Q: `UNION`-da sütun adı necə təyin olunur?**
A: Birinci query-nin sütun adları nəticənin sütun adı olur. Alias yazmaq lazımdırsa, birinci query-də yaz.

**Q: `UNION`-da `ORDER BY`-ı hər query-də yazmaq olar?**
A: Xeyir. `ORDER BY` yalnız sonuncu query-dən sonra, ümumi nəticə üçün yazılır. Tək query-də ORDER BY yazmaq sub-query-lə mümkündür.
