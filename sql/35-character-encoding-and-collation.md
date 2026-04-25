# Character Encoding & Collation (Middle)

## Niye bu movzu senior ucun vacibdir?

Character encoding xətaları production-da **en cox itirilən məlumat** səbəblərindəndir. MySQL `utf8` çox tez-tez `utf8mb4` zənn edilir, amma **əslində 4-byte Unicode (emoji, bəzi Asiya xarakterləri) saxlaya bilmir**. Prod-da `💩` göndərmək emoji-ni silir, string-i kəsir ve ya error verir.

Collation səhvləri isə `WHERE name = 'Çölüstan'` query-sinin **index istifadə etməməsinə**, `ORDER BY` nəticələrinin gözlənilməz olmasına və hətta UNIQUE constraint-in "iki ayrı" string hesab etdiyi "ELI" vs "eli"-ni ayırmamasına səbəb olur.

---

## Encoding vs Collation

| Anlayış | Nədir? | Misal |
|---------|--------|-------|
| **Character Set / Encoding** | Byte → character mapping (necə saxlanılır) | `utf8mb4`, `latin1`, `UTF8` |
| **Collation** | Müqayisə + sort qaydası | `utf8mb4_unicode_ci`, `en_US.UTF-8`, `C` |

**Encoding** — "Bu byte nə hərfdir?"
**Collation** — "`'a' < 'B'` doğrudurmu? `'é' == 'e'` doğrudurmu?"

---

## MySQL: `utf8` vs `utf8mb4` tələsi

### MySQL `utf8` = 3-byte UTF-8 (həqiqi utf8 DEYIL!)

MySQL-in tarixi səhvi. `utf8` = **en çox 3 byte** simvollar. BMP xaricindəki simvollar (emoji, bəzi Çin hieroglifleri) **itir**.

```sql
-- TƏHLÜKƏLI: utf8 ilə emoji
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(255)
) CHARACTER SET utf8 COLLATE utf8_general_ci;

INSERT INTO messages (text) VALUES ('Salam 💩 dünya');
-- MySQL < 8.0: ERROR 1366: Incorrect string value
-- MySQL 8.0+: `utf8` = `utf8mb3`, yenə də 4-byte qəbul etmir

SELECT text FROM messages; -- Salam  dünya   (emoji silinib!)
```

### Hell: utf8mb4 (4-byte həqiqi UTF-8)

```sql
-- DOĞRU: utf8mb4 ilə tam Unicode dəstəyi
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(255)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

INSERT INTO messages (text) VALUES ('Salam 💩 dünya');
SELECT text FROM messages; -- Salam 💩 dünya  ✓
```

### MySQL 8.0+ default: utf8mb4

MySQL 8.0-dan etibarən **default encoding artıq `utf8mb4`**-dür. Əgər MySQL 5.7-dən migrate olursansa, **köhnə cədvəllər hələ də `utf8`** ola bilər — yoxla:

```sql
-- Butun table-lərin encoding-ini yoxla
SELECT table_schema, table_name, table_collation
FROM information_schema.tables
WHERE table_schema = 'myapp';

-- Hər sütunun collation-i
SELECT table_name, column_name, character_set_name, collation_name
FROM information_schema.columns
WHERE table_schema = 'myapp' AND character_set_name IS NOT NULL;

-- utf8mb3/utf8 istifadə edən table-lər (köhnə!)
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'myapp' 
  AND table_collation LIKE '%utf8\_%';  -- utf8_general_ci, utf8mb3_*, vs.
```

### Köhnə `utf8` table-i `utf8mb4`-ə migrate et

```sql
-- 1. Database default-u deyiş (yeni table-lər üçün)
ALTER DATABASE myapp CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- 2. Hər table
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- 3. Diqqət: VARCHAR(255) ilə UNIQUE index problemi!
-- utf8: 255 × 3 = 765 byte (OK, MySQL max index 767 byte idi)
-- utf8mb4: 255 × 4 = 1020 byte (TOO BIG for old InnoDB < 5.7 = 767 limit)
-- Fix: innodb_large_prefix = ON (default 5.7+), VARCHAR(191) (191×4=764)

-- Köhnə MySQL-də workaround:
ALTER TABLE users MODIFY COLUMN email VARCHAR(191);
```

### Laravel default

`config/database.php`:

```php
'mysql' => [
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',  // ve ya utf8mb4_0900_ai_ci (MySQL 8)
    // ...
],

// Migration default (Laravel ozu bu charset-i istifade edir)
```

---

## MySQL Collation seçimi

| Collation | Case? | Accent? | Unicode? | Tövsiyə |
|-----------|-------|---------|----------|---------|
| `utf8mb4_bin` | Sensitive | Sensitive | Binary compare | Exact matching, tokens |
| `utf8mb4_general_ci` | Insensitive | Insensitive | Köhnə, qeyri-dəqiq | **İstifadə ETMƏ** |
| `utf8mb4_unicode_ci` | Insensitive | Insensitive | Unicode 4.0.0 | Köhnə app-lar |
| `utf8mb4_unicode_520_ci` | Insensitive | Insensitive | Unicode 5.2.0 | 5.6-5.7 |
| `utf8mb4_0900_ai_ci` | Accent-Insensitive | Insensitive | Unicode 9.0 | **MySQL 8.0+ default** ✓ |
| `utf8mb4_0900_as_cs` | Sensitive | Sensitive | Unicode 9.0 | Case-sensitive lazımsa |

**`ci`** = case insensitive, **`ai`** = accent insensitive, **`cs`** = case sensitive, **`as`** = accent sensitive, **`bin`** = binary (byte-by-byte).

```sql
-- Case sensitivity demosu
CREATE TABLE t (s VARCHAR(10));

-- utf8mb4_0900_ai_ci (default)
INSERT INTO t VALUES ('Eli'), ('ELI'), ('Əli');
SELECT * FROM t WHERE s = 'eli';  -- 3 row (case + accent insensitive)
SELECT DISTINCT s COLLATE utf8mb4_0900_ai_ci FROM t; -- 1 grup

-- utf8mb4_0900_as_cs ilə
SELECT * FROM t WHERE s COLLATE utf8mb4_0900_as_cs = 'eli'; -- 0 row
```

---

## PostgreSQL Encoding

PostgreSQL-in encoding-i `initdb` zamanı (cluster yaradıldıqda) təyin olunur. Bütün databazalarda eyni olmalıdır (adətən `UTF8`).

```sql
-- Cluster/database encoding-ini yoxla
SELECT datname, pg_encoding_to_char(encoding), datcollate, datctype
FROM pg_database;

-- datname | pg_encoding_to_char | datcollate  | datctype
-- myapp   | UTF8                | en_US.UTF-8 | en_US.UTF-8
```

PostgreSQL-də `UTF8` **hər zaman 4-byte dəstəkli** (emoji problemi yoxdur). MySQL-dəki `utf8` tələsi burada YOXDUR.

```sql
-- Yeni DB ilə UTF8 yarat
CREATE DATABASE myapp
    WITH ENCODING 'UTF8'
         LC_COLLATE = 'en_US.UTF-8'
         LC_CTYPE = 'en_US.UTF-8'
         TEMPLATE = template0;  -- template0 mühüm!
```

### PostgreSQL Collation

```sql
-- Column-level collation
CREATE TABLE users (
    name TEXT COLLATE "en_US.UTF-8",
    slug TEXT COLLATE "C"  -- Byte-order, ən sürətli
);

-- Query-level override
SELECT * FROM users ORDER BY name COLLATE "tr_TR.UTF-8"; -- Türk əlifba sırası

-- Case-insensitive unique index
CREATE UNIQUE INDEX idx_users_email_ci 
ON users (LOWER(email));
-- Və ya citext tipindən istifadə et
```

### `citext` (case-insensitive text)

```sql
CREATE EXTENSION IF NOT EXISTS citext;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email CITEXT UNIQUE
);

INSERT INTO users (email) VALUES ('John@Mail.com');
SELECT * FROM users WHERE email = 'john@mail.com';  -- TAPIR!
INSERT INTO users (email) VALUES ('JOHN@MAIL.COM');  -- UNIQUE violation!
```

### ICU collations (PG 10+)

```sql
-- Unicode-standard lokal-asılı olmayan sort
CREATE COLLATION en_natural (
    provider = icu,
    locale = 'en-u-kn-true'  -- kn = natural numeric sort
);

-- "file10" "file2"-dən sonra gəlir (natural sort)
SELECT filename FROM files ORDER BY filename COLLATE en_natural;
```

---

## Real-world tələlər

### 1. Index collation mismatch

Query-nin collation-i index-in collation-i ilə uyğun olmalıdır. Yoxsa **index istifadə olunmur**.

```sql
-- Table default utf8mb4_0900_ai_ci
CREATE INDEX idx_name ON users (name);

-- Query explicit ferqli collation ilə
SELECT * FROM users WHERE name COLLATE utf8mb4_bin = 'John';
-- Full table scan! Index istifadə olunmur.
```

### 2. JOIN collation mismatch

```sql
-- users.email utf8mb4_unicode_ci
-- contacts.email utf8mb4_0900_ai_ci
SELECT u.* FROM users u
JOIN contacts c ON u.email = c.email;
-- ERROR 1267: Illegal mix of collations

-- Fix: collation cast
SELECT u.* FROM users u
JOIN contacts c ON u.email COLLATE utf8mb4_0900_ai_ci = c.email;

-- Və ya table-ları eyni collation-a gətir.
```

### 3. LIKE və case sensitivity

```sql
-- utf8mb4_0900_ai_ci-də
SELECT * FROM products WHERE name LIKE '%Laptop%';
-- 'laptop', 'LAPTOP', 'lapton' uyğun gəlir (accent-ins, case-ins)

-- Dəqiq match istəsən
SELECT * FROM products WHERE name LIKE BINARY '%Laptop%';
-- Yalnız 'Laptop' (case-sensitive, byte-wise)

-- PostgreSQL-də
SELECT * FROM products WHERE name ILIKE '%laptop%';  -- case-insensitive LIKE
SELECT * FROM products WHERE name ~* 'laptop';       -- regex, case-insensitive
```

### 4. `ORDER BY` dil-asılı sort

```sql
-- Türk əlifbasında 'ç' 'c'-dən sonra gəlir
-- Amma 'ç' Unicode-da müəyyən mövqedədir
SELECT name FROM users ORDER BY name;
-- Default collation Türk qaydasına uyğun olmaya bilər

-- Fix: language-specific collation
SELECT name FROM users ORDER BY name COLLATE "tr_TR.UTF-8";  -- PG
SELECT name FROM users ORDER BY name COLLATE utf8mb4_tr_0900_ai_ci; -- MySQL
```

### 5. String length — byte vs character

```sql
-- MySQL
-- VARCHAR(255) = 255 simvol (char), byte DEYIL
-- utf8mb4-də bir simvol 1-4 byte ola bilər
-- LENGTH() = byte sayı, CHAR_LENGTH() = simvol sayı

SELECT LENGTH('salam'), CHAR_LENGTH('salam');           -- 5, 5
SELECT LENGTH('salam 💩'), CHAR_LENGTH('salam 💩');     -- 10, 7
SELECT LENGTH('Əli'), CHAR_LENGTH('Əli');               -- 4, 3 (Ə = 2 byte)

-- PostgreSQL-də
SELECT octet_length('salam 💩'), char_length('salam 💩'); -- 10, 7
```

---

## PHP/Laravel tələləri

### 1. Connection charset

```php
// config/database.php
'mysql' => [
    'charset' => 'utf8mb4',  // ÖNƏMLİ
    'collation' => 'utf8mb4_unicode_ci',
    // ...
],

// PDO ilə manual
$pdo = new PDO(
    'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
    'user', 'pass',
    [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']
);
```

**Bu olmasa:** client 3-byte `utf8` göndərir, table `utf8mb4` qəbul edir, amma emoji-lər yenə də data itkisi ilə nəticələnə bilər (connection-layer truncation).

### 2. Migration-da explicit charset

```php
// Yanlış: default charset-i güvənmə
Schema::create('messages', function (Blueprint $table) {
    $table->text('body');
});

// Daha yaxşı: explicit
Schema::create('messages', function (Blueprint $table) {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';
    $table->text('body');
});
```

### 3. Emoji validation

```php
// Laravel form validation
$request->validate([
    'username' => ['required', 'string', 'max:50', function ($attr, $value, $fail) {
        // Çoxu regex ilə emoji-ni bloklayır:
        if (preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}]/u', $value)) {
            $fail("Username emoji içerə bilməz.");
        }
    }],
]);

// PHP string length tələsi
strlen('💩');           // 4 (byte)
mb_strlen('💩');        // 1 (character)
mb_strlen('💩', 'UTF-8'); // 1
```

---

## Debugging reçeptləri

```sql
-- MySQL: səhv görünən string-in real byte-larını gör
SELECT name, HEX(name), LENGTH(name), CHAR_LENGTH(name)
FROM users WHERE id = 1;

-- name: Fran?ois
-- HEX: 467261 6E??? 6F6973   <- ?? = mojibake (wrong encoding)

-- Connection-ın encoding-ini yoxla
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';

-- | character_set_client     | utf8mb4  |
-- | character_set_connection | utf8mb4  |
-- | character_set_database   | utf8mb4  |
-- | character_set_results    | utf8mb4  |
-- | character_set_server     | latin1   |  <- PROBLEM!
```

```sql
-- PostgreSQL: client encoding
SHOW client_encoding;  -- UTF8 olmalıdır
SHOW server_encoding;  -- UTF8 olmalıdır
```

---

## Interview sualları

**Q: Niyə MySQL-də `utf8` istifadə etmək olmaz?**
A: MySQL-in `utf8` encoding-i **3-byte limitli**-dir ki, 4-byte UTF-8 simvollarını (emoji, bəzi CJK, musiqi notları) saxlaya bilmir. Həqiqi UTF-8 üçün `utf8mb4` lazımdır. MySQL 8.0-dan `utf8mb4` default-dur, amma 5.7-dən migrate olan köhnə table-lər hələ də köhnə `utf8`-də qala bilər.

**Q: `utf8mb4_unicode_ci` və `utf8mb4_0900_ai_ci` fərqi?**
A: Hər ikisi case-insensitive və accent-insensitive, lakin Unicode versiyası fərqlidir. `utf8mb4_0900_ai_ci` Unicode 9.0 qaydalarını istifadə edir və MySQL 8.0 default-dur — daha yeni simvollar üçün düzgün sort/compare verir. `utf8mb4_unicode_ci` Unicode 4.0 qaydaları (MySQL 5.6/5.7). Yeni layihədə `0900` versiyasını istifadə et.

**Q: PostgreSQL-də email üçün case-insensitive unique necə təmin etmək olar?**
A: Üç yol: 1) `citext` extension + UNIQUE column, 2) `CREATE UNIQUE INDEX idx ON users (LOWER(email))`, 3) `CHECK (email = LOWER(email))` + normal UNIQUE. `citext` ən təmiz API, amma sort-larda gözlənilməz nəticə verə bilər. `LOWER()` index ilk iki variantdan daha portativ.

**Q: Index olan column-da collation fərqi olsa nə baş verir?**
A: Query-nin istifadə etdiyi collation index-in collation-i ilə uyğun gəlmirsə, **MySQL/PG index-dən istifadə edə bilmir** və full table scan edir. Misal: column `utf8mb4_unicode_ci`-dirsə, amma query `WHERE name COLLATE utf8mb4_bin = 'X'` edirsə, index ignore olunur.

**Q: Emoji saxlamaq lazım gələrsə, VARCHAR sütun uzunluğu necə hesablanmalıdır?**
A: MySQL-də VARCHAR(n) = n simvol, amma storage hesablaması byte-la aparılır (utf8mb4 üçün max 4 byte/simvol). Index limit (InnoDB köhnə = 767 byte) VARCHAR(191) = 764 byte sabit tuturdu. MySQL 5.7+ `innodb_large_prefix` default ON olduğundan bu problem yoxdur, amma legacy sistemlərdə yadda saxla.
