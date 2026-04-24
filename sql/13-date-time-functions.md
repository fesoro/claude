# Date/Time Functions & Intervals

> **Seviyye:** Beginner ⭐

Tarix və zamanla işləmək — PHP backend dev-in gündəlik işidir: log-lar, report-lar, expired check, audit trail, scheduler.

## Cari tarix/zaman

```sql
-- Standard SQL
SELECT CURRENT_DATE;                       -- '2026-04-24'
SELECT CURRENT_TIME;                       -- '14:30:00+04'
SELECT CURRENT_TIMESTAMP;                  -- '2026-04-24 14:30:00+04'

-- PostgreSQL-specific
SELECT NOW();                              -- '2026-04-24 14:30:00+04' (txn start-ı)
SELECT CLOCK_TIMESTAMP();                  -- real həqiqi zaman
SELECT STATEMENT_TIMESTAMP();              -- statement başlangıcı

-- MySQL
SELECT NOW();                              -- '2026-04-24 14:30:00'
SELECT CURDATE();                          -- '2026-04-24'
SELECT CURTIME();                          -- '14:30:00'
SELECT SYSDATE();                          -- həqiqi zaman (statement-dən müstəqil)
```

**Fərq (vacibdir):**
- `NOW()` — transaction başlandıqda fixed (eyni transaction-da təkrar çağırsan eyni dəyər).
- `CLOCK_TIMESTAMP()` / `SYSDATE()` — hər çağırılanda real-time.

## DATE Parts Çıxartmaq

### EXTRACT (standard)

```sql
SELECT EXTRACT(YEAR FROM created_at) FROM orders;          -- 2026
SELECT EXTRACT(MONTH FROM created_at) FROM orders;         -- 4
SELECT EXTRACT(DAY FROM created_at) FROM orders;           -- 24
SELECT EXTRACT(HOUR FROM created_at) FROM orders;          -- 14
SELECT EXTRACT(DOW FROM created_at) FROM orders;           -- 5 (Friday, 0=Sun)
SELECT EXTRACT(ISODOW FROM created_at) FROM orders;        -- 5 (Friday, 1=Mon)
SELECT EXTRACT(WEEK FROM created_at) FROM orders;          -- 17 (week #)
SELECT EXTRACT(QUARTER FROM created_at) FROM orders;       -- 2
```

### MySQL-də alternativ

```sql
SELECT YEAR(created_at), MONTH(created_at), DAY(created_at) FROM orders;
SELECT DAYOFWEEK(created_at);              -- 1=Sunday, 7=Saturday
SELECT WEEKDAY(created_at);                -- 0=Monday, 6=Sunday
SELECT DAYOFYEAR(created_at);
SELECT WEEKOFYEAR(created_at);
```

### PostgreSQL-də

```sql
SELECT date_part('year', created_at) FROM orders;
```

## DATE_TRUNC — Truncation (kəsmə)

Tarixi daha böyük vahidə qədər "kəs".

```sql
-- PostgreSQL: DATE_TRUNC
SELECT DATE_TRUNC('hour', created_at) FROM orders;
-- '2026-04-24 14:00:00' (saata qədər)

SELECT DATE_TRUNC('day', created_at) FROM orders;
-- '2026-04-24 00:00:00'

SELECT DATE_TRUNC('month', created_at) FROM orders;
-- '2026-04-01 00:00:00'

-- MySQL alternativi
SELECT DATE_FORMAT(created_at, '%Y-%m-%d 00:00:00') FROM orders;
SELECT DATE(created_at) FROM orders;       -- sadəcə date
```

**Klassik istifadə: reporting**

```sql
-- Aylıq sifaris sayi
SELECT 
    DATE_TRUNC('month', created_at) AS month,
    COUNT(*) AS order_count,
    SUM(total) AS revenue
FROM orders
WHERE created_at >= '2026-01-01'
GROUP BY DATE_TRUNC('month', created_at)
ORDER BY month;
```

## INTERVAL — Tarix Arifmetikası

### PostgreSQL

```sql
-- INTERVAL syntax
SELECT NOW() + INTERVAL '1 day';
SELECT NOW() - INTERVAL '7 days';
SELECT NOW() + INTERVAL '1 month 15 days';
SELECT NOW() + INTERVAL '2 hours 30 minutes';

-- 7 gündən əvvəl yaradılmış sifarişlər
SELECT * FROM orders 
WHERE created_at > NOW() - INTERVAL '7 days';

-- 30 gündən köhnə pending
SELECT * FROM orders
WHERE status = 'pending' 
  AND created_at < NOW() - INTERVAL '30 days';
```

### MySQL

```sql
-- DATE_ADD / DATE_SUB
SELECT DATE_ADD(NOW(), INTERVAL 1 DAY);
SELECT DATE_SUB(NOW(), INTERVAL 7 DAY);
SELECT DATE_ADD(NOW(), INTERVAL 1 MONTH);
SELECT DATE_ADD(NOW(), INTERVAL '1:30' HOUR_MINUTE);

-- Birbaşa operator (işləyir)
SELECT NOW() + INTERVAL 1 DAY;

-- 7 gündən əvvəlki sifarişlər
SELECT * FROM orders 
WHERE created_at > NOW() - INTERVAL 7 DAY;
```

## Tarix fərqi (fark hesabla)

### PostgreSQL: AGE və çıxma

```sql
-- İki tarix arasındakı fərq
SELECT AGE('2026-04-24', '2023-01-15');    -- '3 years 3 mons 9 days'

-- İki timestamp arasındakı fərq (INTERVAL)
SELECT NOW() - created_at FROM orders;

-- Saat fərqi
SELECT EXTRACT(EPOCH FROM (NOW() - created_at)) / 3600 AS hours
FROM orders;
```

### MySQL: DATEDIFF, TIMESTAMPDIFF

```sql
-- Gün fərqi
SELECT DATEDIFF(NOW(), created_at) FROM orders;

-- Istənilən vahiddə
SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) FROM orders;
SELECT TIMESTAMPDIFF(MINUTE, created_at, NOW()) FROM orders;
SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM orders;
SELECT TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM users;
```

## Timezone işləmək

### PostgreSQL (TIMESTAMPTZ istifadə et!)

```sql
-- TIMESTAMPTZ - UTC-də saxlanır, göstərildikdə session tz-də
CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    happens_at TIMESTAMPTZ NOT NULL
);

INSERT INTO events (happens_at) VALUES ('2026-04-24 14:00:00+04');  -- Baku TZ
SELECT happens_at FROM events;                      -- session TZ-də göstərilir

-- Başqa timezone-da göstər
SELECT happens_at AT TIME ZONE 'Asia/Baku' FROM events;
SELECT happens_at AT TIME ZONE 'America/New_York' FROM events;
SELECT happens_at AT TIME ZONE 'UTC' FROM events;

-- Current session TZ
SHOW TIME ZONE;
SET TIME ZONE 'Asia/Baku';
```

### MySQL

```sql
-- MySQL TIMESTAMP UTC-də saxlanir, session TZ-də göstərilir
-- MySQL DATETIME - timezone-siz
CREATE TABLE events (
    id BIGINT PRIMARY KEY,
    happens_at DATETIME,           -- timezone-siz, dəyər aldığın kimi saxlanır
    created_at TIMESTAMP           -- UTC-də saxlanır
);

-- Session TZ
SELECT @@session.time_zone;
SET time_zone = '+04:00';
SET time_zone = 'Asia/Baku';      -- mysql_tz_table yüklənmişdirsə

-- CONVERT_TZ
SELECT CONVERT_TZ(created_at, '+00:00', '+04:00') FROM orders;
```

## Format / Parse

### PostgreSQL

```sql
-- TO_CHAR (format)
SELECT TO_CHAR(NOW(), 'YYYY-MM-DD');            -- '2026-04-24'
SELECT TO_CHAR(NOW(), 'DD/MM/YYYY HH24:MI');    -- '24/04/2026 14:30'
SELECT TO_CHAR(NOW(), 'Month');                 -- 'April'
SELECT TO_CHAR(NOW(), 'Day');                   -- 'Friday'

-- TO_DATE / TO_TIMESTAMP (parse)
SELECT TO_DATE('24/04/2026', 'DD/MM/YYYY');
SELECT TO_TIMESTAMP('24-04-2026 14:30', 'DD-MM-YYYY HH24:MI');
```

### MySQL

```sql
-- DATE_FORMAT
SELECT DATE_FORMAT(NOW(), '%Y-%m-%d');           -- '2026-04-24'
SELECT DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i');     -- '24/04/2026 14:30'
SELECT DATE_FORMAT(NOW(), '%M');                 -- 'April'
SELECT DATE_FORMAT(NOW(), '%W');                 -- 'Friday'

-- STR_TO_DATE
SELECT STR_TO_DATE('24/04/2026', '%d/%m/%Y');
SELECT STR_TO_DATE('24-04-2026 14:30', '%d-%m-%Y %H:%i');
```

### Format Specifiers (MySQL)

| Specifier | Nə | Misal |
|-----------|----|----|
| `%Y` | 4-rəqəm il | 2026 |
| `%y` | 2-rəqəm il | 26 |
| `%m` | ay (01-12) | 04 |
| `%d` | gün (01-31) | 24 |
| `%H` | saat 24h | 14 |
| `%i` | dəqiqə | 30 |
| `%s` | saniyə | 45 |
| `%M` | ay adı | April |
| `%W` | həftə günü | Friday |

## Unix Timestamp

```sql
-- PostgreSQL
SELECT EXTRACT(EPOCH FROM NOW());                -- unix timestamp
SELECT TO_TIMESTAMP(1714000000);                 -- unix → timestamp

-- MySQL
SELECT UNIX_TIMESTAMP(NOW());
SELECT FROM_UNIXTIME(1714000000);
```

## Həftənin başlanğıcı / ayın sonu

```sql
-- PostgreSQL
SELECT DATE_TRUNC('week', NOW());                           -- həftə başı (Monday)
SELECT DATE_TRUNC('month', NOW());                          -- ay başı
SELECT DATE_TRUNC('month', NOW()) + INTERVAL '1 month - 1 day';    -- ay sonu

-- MySQL
SELECT DATE(NOW() - INTERVAL (WEEKDAY(NOW())) DAY);         -- həftə başı
SELECT LAST_DAY(NOW());                                     -- ay sonu
SELECT DATE_FORMAT(NOW(), '%Y-%m-01');                      -- ay başı
```

## İnterval Hesabla

```sql
-- Neçə gün idi order yaradılıb?
SELECT 
    id,
    created_at,
    NOW() - created_at AS age,                         -- PostgreSQL INTERVAL
    EXTRACT(DAY FROM NOW() - created_at) AS days_old
FROM orders;

-- MySQL: DATEDIFF
SELECT id, created_at, DATEDIFF(NOW(), created_at) AS days_old FROM orders;
```

## Index Pitfall

```sql
-- BAD: funksiya sütuna tətbiq olunur, index işləmir
WHERE YEAR(created_at) = 2026;
WHERE DATE(created_at) = '2026-04-24';
WHERE EXTRACT(YEAR FROM created_at) = 2026;

-- GOOD: range istifadə et
WHERE created_at >= '2026-01-01' AND created_at < '2027-01-01';
WHERE created_at >= '2026-04-24' AND created_at < '2026-04-25';

-- Alternativ: functional index (son çarə)
CREATE INDEX idx_year ON orders ((YEAR(created_at)));    -- MySQL 8+
```

## Laravel Carbon ilə İşləmək

```php
// Carbon kutubxanasi (Laravel-de default)
use Carbon\Carbon;

$now = Carbon::now();
$yesterday = Carbon::now()->subDay();
$nextWeek = Carbon::now()->addWeek();

// Laravel query
Order::where('created_at', '>', Carbon::now()->subDays(7))->get();

// Eloquent automatic casts
class Order extends Model {
    protected $casts = [
        'created_at' => 'datetime',
        'shipped_at' => 'datetime:Y-m-d',
    ];
}

// Accessor
$order->created_at->diffForHumans();        // '2 hours ago'
$order->created_at->format('d M Y');
```

## Interview Sualları

**Q: `WHERE YEAR(created_at) = 2026` niyə slow?**
A: `YEAR()` hər row-da çağrılır, B-Tree index istifadə olunmur. Həll: range (`>= '2026-01-01' AND < '2027-01-01'`) və ya functional index.

**Q: MySQL `TIMESTAMP` vs `DATETIME` fərqi?**
A: `TIMESTAMP` UTC-də saxlanır, session timezone-a çevrilir (4 bayt, 1970-2038 aralığı). `DATETIME` timezone-siz, dəyər necə yazılıbsa elə saxlanır (8 bayt, daha geniş aralıq).

**Q: PostgreSQL `NOW()` və `CLOCK_TIMESTAMP()` fərqi?**
A: `NOW()` transaction başlandığında fixed — eyni transaction-da neçə dəfə çağırsan, eyni dəyər. `CLOCK_TIMESTAMP()` hər çağırıldıqda real-time.

**Q: Unix timestamp saxlamaq (`BIGINT`) vs `TIMESTAMP` hansı daha yaxşıdır?**
A: TIMESTAMP daha yaxşıdır — indexed, DB funksiyaları işləyir, human-readable. BIGINT-i yalnız legacy və ya xüsusi halda istifadə et.

**Q: Multi-region app-da hansı timezone istifadə etməli?**
A: **UTC-də saxla, client-də görüntülə**. PostgreSQL `TIMESTAMPTZ` kimi. Application kodunda user-in timezone-una çevir.
