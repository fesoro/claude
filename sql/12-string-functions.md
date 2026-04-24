# String Functions

> **Seviyye:** Beginner ⭐

String ilə işləmək üçün ən çox istifadə olunan funksiyalar. **Hər backend dev gündə onlarla istifadə edir**.

## String Birləşdirmə

```sql
-- PostgreSQL: || operator
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- MySQL: CONCAT()
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;

-- CONCAT_WS (with separator) - MySQL və PostgreSQL
SELECT CONCAT_WS(' ', first_name, middle_name, last_name) FROM users;
-- middle_name NULL-dırsa avtomatik skip edir!
```

**NULL pitfall:**
```sql
-- PostgreSQL: NULL ilə birləşdirmə NULL qaytarır
SELECT 'Ali ' || NULL;                      -- NULL

-- MySQL: NULL ilə CONCAT NULL qaytarır
SELECT CONCAT('Ali ', NULL);                -- NULL

-- Həll: COALESCE və ya CONCAT_WS
SELECT 'Ali ' || COALESCE(middle, '');
SELECT CONCAT_WS(' ', 'Ali', middle, 'Veliyev');
```

## LENGTH / CHAR_LENGTH

```sql
-- Bayt uzunluğu (multi-byte char problem)
SELECT LENGTH('salam');                     -- 5 (ASCII)
SELECT LENGTH('salam 👋');                  -- 10 (emoji 4 bayt)

-- Simvol sayı (düzgün)
SELECT CHAR_LENGTH('salam 👋');             -- 6
SELECT CHARACTER_LENGTH('salam 👋');        -- eyni

-- PostgreSQL
SELECT LENGTH('salam 👋');                  -- 6 (PostgreSQL UTF8-de char qəbul edir)
SELECT OCTET_LENGTH('salam 👋');            -- 10 (bayt)
```

## Hərflərin dəyişdirilməsi

```sql
SELECT UPPER('hello');                      -- 'HELLO'
SELECT LOWER('HELLO');                      -- 'hello'

-- PostgreSQL: INITCAP (Title Case)
SELECT INITCAP('hello world');              -- 'Hello World'

-- MySQL: MySQL UCFIRST yoxdur - manual
SELECT CONCAT(UPPER(LEFT(name, 1)), LOWER(SUBSTRING(name, 2)));
```

## SUBSTRING / SUBSTR

String-dən bir hissə çıxart.

```sql
-- SUBSTRING(string FROM start FOR length) - standard
SELECT SUBSTRING('PostgreSQL' FROM 5 FOR 3);        -- 'reS'

-- MySQL: SUBSTRING(string, start, length)
SELECT SUBSTRING('MySQL Database', 7, 8);           -- 'Database'

-- PostgreSQL alt variantı
SELECT SUBSTR('PostgreSQL', 5, 3);                  -- 'reS'

-- LEFT / RIGHT (ilk/son N simvol)
SELECT LEFT('hello world', 5);              -- 'hello'
SELECT RIGHT('hello world', 5);             -- 'world'
```

**Diqqət:** Index **1-dən başlayır** (0 yox)!

## TRIM - Boşluq / Simvol Təmizlə

```sql
-- Başdan və sondan boşluq sil
SELECT TRIM('  hello  ');                   -- 'hello'

-- Yalnız başdan
SELECT LTRIM('  hello  ');                  -- 'hello  '

-- Yalnız sondan
SELECT RTRIM('  hello  ');                  -- '  hello'

-- Xüsusi simvollar
SELECT TRIM(BOTH 'x' FROM 'xxhelloxx');     -- 'hello'
SELECT TRIM(LEADING '0' FROM '000123');     -- '123'
SELECT TRIM(TRAILING '.' FROM 'hello...');  -- 'hello'
```

**İstifadə:** Email, telefon nömrəsi kimi user input-dan əlavə boşluqları təmizləmək.

## REPLACE

```sql
-- String içində əvəz et
SELECT REPLACE('hello world', 'world', 'PHP');     -- 'hello PHP'

-- Hər tərəfdə bütün occurrence-lərə tətbiq olunur
SELECT REPLACE('aaa', 'a', 'b');                   -- 'bbb'
```

**İstifadə:** Data təmizləmə, URL generate etmə.

```sql
-- Slug yarat
SELECT LOWER(REPLACE('Hello World 2026', ' ', '-'));     -- 'hello-world-2026'
```

## POSITION / INSTR / STRPOS

```sql
-- String-də bir alt-string-in mövqeyi (1-based, tapılmasa 0)
SELECT POSITION('@' IN 'ali@example.com');          -- 4 (standard)

-- MySQL
SELECT INSTR('ali@example.com', '@');              -- 4

-- PostgreSQL
SELECT STRPOS('ali@example.com', '@');             -- 4
SELECT POSITION('@' IN 'ali@example.com');         -- 4
```

**İstifadə:** Email-dən domain çıxart:

```sql
-- PostgreSQL
SELECT SUBSTRING(email FROM POSITION('@' IN email) + 1) AS domain
FROM users;

-- MySQL
SELECT SUBSTRING(email, INSTR(email, '@') + 1) AS domain FROM users;
```

## SPLIT_PART / SUBSTRING_INDEX

```sql
-- PostgreSQL: SPLIT_PART
SELECT SPLIT_PART('ali@example.com', '@', 2);      -- 'example.com'
SELECT SPLIT_PART('a,b,c,d', ',', 3);              -- 'c'

-- MySQL: SUBSTRING_INDEX
SELECT SUBSTRING_INDEX('ali@example.com', '@', -1);    -- 'example.com'
SELECT SUBSTRING_INDEX('a,b,c,d', ',', 3);             -- 'a,b,c' (ilk 3)
SELECT SUBSTRING_INDEX(SUBSTRING_INDEX('a,b,c,d', ',', 3), ',', -1);    -- 'c'
```

## REPEAT

```sql
SELECT REPEAT('ab', 3);                    -- 'ababab'
SELECT REPEAT('-', 20);                    -- '--------------------' (cədvəl ayırıcısı)
```

## REVERSE

```sql
SELECT REVERSE('hello');                   -- 'olleh'

-- İstifadə: email domain-ə görə sürətli index
CREATE INDEX idx_email_reversed ON users (REVERSE(email));
-- WHERE REVERSE(email) LIKE 'moc.liamg@%'  -- gmail.com ile bitenler
```

## Padding

```sql
-- LPAD / RPAD - boşluqla (və ya xüsusi simvolla) doldur
SELECT LPAD('7', 3, '0');                  -- '007'
SELECT LPAD('123', 5, '0');                -- '00123'
SELECT RPAD('hi', 10, '.');                -- 'hi........'

-- İstifadə: fixed-width output, ID formatting
SELECT LPAD(CAST(id AS TEXT), 8, '0') FROM orders;   -- '00000123'
```

## REGEX

### PostgreSQL

```sql
-- REGEXP_REPLACE
SELECT REGEXP_REPLACE('abc123def', '[0-9]+', 'NUM');     -- 'abcNUMdef'

-- REGEXP_MATCHES (match-ləri array kimi)
SELECT REGEXP_MATCHES('abc123def456', '[0-9]+', 'g');

-- ~ operator (match tapılırsa)
SELECT * FROM users WHERE email ~ '^[a-z]+@[a-z]+\.com$';
SELECT * FROM users WHERE email ~* 'gmail';              -- case-insensitive
```

### MySQL

```sql
-- REGEXP_REPLACE (MySQL 8+)
SELECT REGEXP_REPLACE('abc123def', '[0-9]+', 'NUM');

-- REGEXP operator
SELECT * FROM users WHERE email REGEXP '^[a-z]+@[a-z]+\\.com$';

-- REGEXP_LIKE (MySQL 8+)
SELECT * FROM users WHERE REGEXP_LIKE(email, 'gmail', 'i');
```

## FORMAT — Rəqəmi Stringe Çevir

```sql
-- MySQL: thousand separator
SELECT FORMAT(1234567.89, 2);              -- '1,234,567.89'

-- PostgreSQL: TO_CHAR
SELECT TO_CHAR(1234567.89, 'FM9,999,999.00');    -- '1,234,567.89'
SELECT TO_CHAR(0.5, 'FM999.99%');                 -- '.5%'
```

## String Comparison

```sql
-- Birbaşa (case-sensitive, collation-dan asılı)
SELECT 'apple' = 'Apple';                  -- FALSE (adətən)

-- ILIKE (PostgreSQL) / case-insensitive collation (MySQL)
SELECT 'apple' ILIKE 'APPLE';              -- TRUE (PostgreSQL)

-- LOWER comparison (portable)
SELECT LOWER('apple') = LOWER('Apple');    -- TRUE

-- SOUNDEX - səs oxşarlıq (approx match)
SELECT SOUNDEX('Robert'), SOUNDEX('Rupert'); -- hər ikisi 'R163'
-- Yalniz latın hərfləri üçün işləyir
```

## String Aggregate (Çoxu Birləşdir)

### PostgreSQL: STRING_AGG

```sql
-- Hər country üçün user adlarını siyahıya al
SELECT 
    country,
    STRING_AGG(name, ', ' ORDER BY name) AS user_list
FROM users
GROUP BY country;
-- 'AZ' | 'Ali, Orkhan, Veli'
```

### MySQL: GROUP_CONCAT

```sql
SELECT 
    country,
    GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') AS user_list
FROM users
GROUP BY country;

-- Limit var (default 1024 bayt) - artırmaq üçün:
SET SESSION group_concat_max_len = 1000000;
```

## Laravel Nümunəsi

```php
// Raw SQL
DB::select("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users");

// Query Builder
DB::table('users')
    ->select(DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))
    ->where(DB::raw('LOWER(email)'), 'like', '%gmail%')
    ->get();

// Eloquent accessor (PHP tarafinda)
class User extends Model {
    public function getFullNameAttribute() {
        return $this->first_name . ' ' . $this->last_name;
    }
}
```

## Performance Qaydaları

1. **WHERE-də funksiya sutun-a tətbiq etmə** — functional index yaratmasa index istifadə olunmur:
   ```sql
   -- BAD
   WHERE LOWER(email) = 'ali@x.com';
   
   -- GOOD (functional index)
   CREATE INDEX idx_email_lower ON users (LOWER(email));
   ```

2. **LIKE `%prefix`** — index işləmir, **`prefix%`** — index işləyir.

3. **Full-text search** lazımdırsa — `tsvector` (PostgreSQL) və ya `FULLTEXT` (MySQL) istifadə et.

## Interview Sualları

**Q: `CONCAT('Ali', NULL)` nə qaytarır?**
A: NULL. `CONCAT_WS` isə NULL-ları skip edir və çalışır.

**Q: `LENGTH('salam')` və `CHAR_LENGTH('salam')` fərqi?**
A: `LENGTH` bayt sayını qaytarır (multi-byte char-da problem). `CHAR_LENGTH` simvol sayı. UTF-8 string-lər üçün həmişə `CHAR_LENGTH` istifadə et.

**Q: `WHERE LOWER(email) = 'x'` niyə slow?**
A: Funksiya sütunun hər row-una tətbiq olunur, B-Tree index istifadə olunmur. Həll: functional index (`CREATE INDEX ... ON ... (LOWER(email))`).

**Q: `STRING_AGG` vs `GROUP_CONCAT` fərqi?**
A: Funksiyası eyni - çox row-u bir string-ə birləşdirir. STRING_AGG PostgreSQL, GROUP_CONCAT MySQL. MySQL-də default limit 1024 bayt-dır (`group_concat_max_len`).
