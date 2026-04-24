# CASE Expressions

> **Seviyye:** Beginner ⭐

`CASE` — SQL-də **if-else** ekvivalenti. İstənilən yerdə istifadə oluna bilir (SELECT, WHERE, ORDER BY, GROUP BY, UPDATE).

## İki Növ CASE

### 1. Simple CASE — dəyərə görə

```sql
SELECT 
    name,
    CASE status
        WHEN 'active' THEN 'Aktiv'
        WHEN 'pending' THEN 'Gözləyir'
        WHEN 'banned' THEN 'Bloklanıb'
        ELSE 'Bilinmir'
    END AS status_display
FROM users;
```

**Diqqət:** Simple CASE `=` müqayisəsi edir. NULL ilə işləmir!

```sql
-- YANLIŞ - NULL heç bir WHEN-ə uyğun gəlmir
CASE status WHEN NULL THEN 'Yoxdur' ELSE status END;
-- Nəticə: həmişə ELSE şaxəsi

-- DOĞRU - Searched CASE istifadə et
CASE WHEN status IS NULL THEN 'Yoxdur' ELSE status END;
```

### 2. Searched CASE — şərtə görə

```sql
SELECT 
    name,
    age,
    CASE
        WHEN age < 18 THEN 'minor'
        WHEN age BETWEEN 18 AND 64 THEN 'adult'
        WHEN age >= 65 THEN 'senior'
        ELSE 'unknown'
    END AS age_group
FROM users;
```

**Daha güclü:** Simple CASE-dən. Kompleks şərt yazmaq olar.

## CASE Harada İstifadə Olunur

### 1. SELECT — dəyər çevirmək

```sql
SELECT 
    order_id,
    total,
    CASE
        WHEN total < 100 THEN 'small'
        WHEN total < 1000 THEN 'medium'
        ELSE 'large'
    END AS order_size
FROM orders;
```

### 2. WHERE — şərtli filter

```sql
-- Role-a görə fərqli şərt
SELECT * FROM users
WHERE CASE 
    WHEN role = 'admin' THEN 1 = 1             -- admin üçün həmişə true
    WHEN role = 'user' AND active = 1 THEN 1 = 1   -- user-lər üçün yalnız aktiv
    ELSE 1 = 0                                 -- digərləri false
END;

-- Ekvivalent (daha oxunaqlı):
WHERE (role = 'admin') OR (role = 'user' AND active = 1);
```

**Qayda:** WHERE-də CASE çox az istifadə olunur — adətən OR ilə əvəz olunur.

### 3. ORDER BY — sıralama prioriteti

```sql
-- Status-a görə xüsusi sıra
SELECT * FROM orders
ORDER BY 
    CASE status
        WHEN 'urgent' THEN 1
        WHEN 'pending' THEN 2
        WHEN 'completed' THEN 3
        ELSE 4
    END,
    created_at DESC;

-- MySQL alternative: FIELD()
SELECT * FROM orders
ORDER BY FIELD(status, 'urgent', 'pending', 'completed'), created_at DESC;
```

### 4. GROUP BY — qruplaşdırma kateqoriyası

```sql
-- Order sayını ölçüyə görə qrupla
SELECT 
    CASE
        WHEN total < 100 THEN 'small'
        WHEN total < 1000 THEN 'medium'
        ELSE 'large'
    END AS order_size,
    COUNT(*) AS count
FROM orders
GROUP BY 
    CASE
        WHEN total < 100 THEN 'small'
        WHEN total < 1000 THEN 'medium'
        ELSE 'large'
    END;

-- Daha oxunaqlı - CTE istifadə et:
WITH sized AS (
    SELECT *,
        CASE WHEN total < 100 THEN 'small'
             WHEN total < 1000 THEN 'medium'
             ELSE 'large' END AS order_size
    FROM orders
)
SELECT order_size, COUNT(*) FROM sized GROUP BY order_size;
```

### 5. Aggregate içində — şərti hesablama (PIVOT)

Bu CASE-in **ən güclü** istifadəsidir.

```sql
-- Hər user-in status-a görə sifariş sayı
SELECT 
    user_id,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) AS paid_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count,
    SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) AS revenue,
    AVG(CASE WHEN status = 'paid' THEN total END) AS avg_paid_order
FROM orders
GROUP BY user_id;
```

**Qeyd:** `ELSE` yoxdursa NULL-dir. `COUNT()` NULL-ləri ignorlayır, ona görə `ELSE 0` lazım deyil.

### PostgreSQL: FILTER clause (daha oxunaqlı)

```sql
-- PostgreSQL: CASE əvəzinə FILTER
SELECT 
    user_id,
    COUNT(*) FILTER (WHERE status = 'paid') AS paid_count,
    COUNT(*) FILTER (WHERE status = 'pending') AS pending_count,
    SUM(total) FILTER (WHERE status = 'paid') AS revenue
FROM orders
GROUP BY user_id;
```

### 6. UPDATE — şərtli dəyişiklik

```sql
-- Role-a görə maaş artımı
UPDATE employees
SET salary = CASE role
    WHEN 'senior' THEN salary * 1.15
    WHEN 'mid' THEN salary * 1.10
    WHEN 'junior' THEN salary * 1.05
    ELSE salary
END;

-- Searched CASE ilə
UPDATE products
SET price = CASE
    WHEN category = 'electronics' AND stock > 100 THEN price * 0.9
    WHEN category = 'food' AND expires_at < NOW() + INTERVAL '7 days' THEN price * 0.5
    ELSE price
END;
```

## COALESCE, NULLIF — CASE-in Qısa Formaları

### COALESCE — ilk NOT NULL dəyəri qaytarır

```sql
-- CASE versiyası:
SELECT CASE 
    WHEN phone IS NOT NULL THEN phone
    WHEN email IS NOT NULL THEN email
    ELSE 'N/A'
END FROM contacts;

-- COALESCE versiyası:
SELECT COALESCE(phone, email, 'N/A') FROM contacts;
```

### NULLIF — iki dəyər bərabərdirsə NULL

```sql
-- CASE versiyası:
SELECT CASE WHEN a = b THEN NULL ELSE a END FROM t;

-- NULLIF versiyası:
SELECT NULLIF(a, b) FROM t;

-- Klassik istifadə: sıfıra bölmədən qaçmaq
SELECT total / NULLIF(quantity, 0) FROM orders;
-- quantity = 0 olanda NULLIF NULL qaytarir, boolunmez - NULL qaytarir
```

NULL haqqinda daha ətraflı: `09-null-handling-coalesce.md`.

## CASE-də Data Type Uyğunluğu

**Qayda:** Bütün WHEN nəticələri uyğun tipdə olmalıdır.

```sql
-- YANLIŞ - fərqli tiplər
SELECT CASE 
    WHEN x > 0 THEN 'positive'     -- string
    WHEN x < 0 THEN -1             -- integer!
    ELSE 0
END FROM nums;
-- ERROR və ya implicit cast (təhlükəli)

-- DOĞRU - hamısı eyni tip
SELECT CASE 
    WHEN x > 0 THEN 'positive'
    WHEN x < 0 THEN 'negative'
    ELSE 'zero'
END FROM nums;
```

## İç-içə CASE (Nested)

```sql
SELECT 
    CASE status
        WHEN 'active' THEN 
            CASE 
                WHEN last_login > NOW() - INTERVAL '7 days' THEN 'active-recent'
                ELSE 'active-stale'
            END
        WHEN 'pending' THEN 'pending'
        ELSE 'inactive'
    END AS detailed_status
FROM users;
```

**Qayda:** 2 səviyyədən çox iç-içə istifadə etmə — oxunmur. CTE ilə böl.

## CASE ELSE Yoxdursa NULL-dir

```sql
SELECT 
    name,
    CASE 
        WHEN age < 18 THEN 'minor'
        WHEN age >= 18 THEN 'adult'
    END AS category
FROM users;
-- age NULL-dırsa nəticə NULL (ELSE yoxdur)
```

**Qayda:** Həmişə `ELSE` yaz, explicit ol.

## Laravel Nümunəsi

```php
// SELECT-də CASE
$orders = DB::table('orders')
    ->select('*', DB::raw("
        CASE 
            WHEN total < 100 THEN 'small'
            WHEN total < 1000 THEN 'medium'
            ELSE 'large'
        END AS size
    "))
    ->get();

// ORDER BY-da CASE
$orders = DB::table('orders')
    ->orderByRaw("CASE status 
        WHEN 'urgent' THEN 1 
        WHEN 'pending' THEN 2 
        ELSE 3 END")
    ->get();

// Pivot (status-a görə sayım)
$stats = DB::table('orders')
    ->select('user_id',
        DB::raw("COUNT(CASE WHEN status='paid' THEN 1 END) AS paid"),
        DB::raw("COUNT(CASE WHEN status='failed' THEN 1 END) AS failed"))
    ->groupBy('user_id')
    ->get();
```

## Interview Sualları

**Q: `Simple CASE` və `Searched CASE` fərqi?**
A: Simple CASE `=` müqayisə edir, tək expression-a uyğun gəlir (və NULL ilə işləmir). Searched CASE hər WHEN-də müstəqil şərt yazmağa imkan verir — daha flexible.

**Q: `CASE status WHEN NULL THEN 'yox'` niyə işləmir?**
A: SQL-də NULL `= NULL` FALSE-dır (3-valued logic). Həll: `CASE WHEN status IS NULL THEN 'yox'` — Searched CASE istifadə et.

**Q: CASE performans bahalıdır?**
A: Xeyir — sadə müqayisədir. Amma WHERE-də çox yerə yazılması index-in istifadə olunmamasına səbəb ola bilər. PIVOT (GROUP BY + CASE) sürətlidir — tək scan ilə işləyir.

**Q: `SUM(CASE WHEN ... THEN 1 ELSE 0 END)` və `COUNT(CASE WHEN ... THEN 1 END)` fərqi?**
A: Effekt eynidir (SUM-da 0 cəmə təsir etmir, COUNT-da NULL sayılmır). `COUNT(CASE WHEN ... END)` daha idiomatic.
