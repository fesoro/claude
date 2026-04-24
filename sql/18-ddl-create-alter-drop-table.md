# DDL: CREATE / ALTER / DROP TABLE

> **Seviyye:** Beginner ⭐

DDL (Data Definition Language) — schema-nı dəyişdirən əmrlər. Production-da **backwards-compatible** ALTER vacibdir (downtime qaçınmaq üçün).

## CREATE TABLE

```sql
-- Sadə
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Constraint-larla
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT status_check CHECK (status IN ('pending', 'paid', 'shipped', 'cancelled')),
    INDEX idx_user_created (user_id, created_at DESC)
);
```

## CREATE TABLE IF NOT EXISTS

```sql
-- Varsa error verme
CREATE TABLE IF NOT EXISTS users (
    id BIGINT PRIMARY KEY,
    ...
);
```

**İstifadə:** Migration-lar, initial setup.

## CREATE TABLE AS (CTAS)

Başqa query-nin nəticəsindən table yarat.

```sql
-- Arxiv table
CREATE TABLE orders_archive AS 
SELECT * FROM orders WHERE status = 'cancelled';

-- Schema kopyası (data-sız)
CREATE TABLE orders_template AS 
SELECT * FROM orders WHERE 1 = 0;

-- Aggregate dan table
CREATE TABLE user_stats AS
SELECT user_id, COUNT(*) as order_count, SUM(total) as total_spent
FROM orders
GROUP BY user_id;
```

**Diqqət:** CTAS constraint-ları və index-ləri **kopyalamır** (yalnız tip və data).

## CREATE TABLE LIKE

Strukturu kopyala (data olmadan).

```sql
-- MySQL
CREATE TABLE orders_2026 LIKE orders;

-- PostgreSQL
CREATE TABLE orders_2026 (LIKE orders INCLUDING ALL);
-- INCLUDING ALL: constraint + index + default
```

## TEMPORARY TABLE

```sql
-- Session-scope
CREATE TEMPORARY TABLE temp_results (
    id BIGINT,
    score INT
);

-- Session bitdikdə avtomatik silinir
```

**İstifadə:** Kompleks query-də intermediate step-lər, reporting.

## ALTER TABLE

Schema-da dəyişiklik.

### Sütun əlavə et

```sql
-- Sonuna
ALTER TABLE users ADD COLUMN phone VARCHAR(20);

-- Xüsusi yerə (MySQL)
ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email;
ALTER TABLE users ADD COLUMN id BIGINT FIRST;

-- Default ilə
ALTER TABLE users ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;
```

**PostgreSQL:** DEFAULT-lı NOT NULL sütun əlavə etmək PG 11+-də **instant** (metadata dəyişir), PG 10-da table rewrite.

**MySQL InnoDB 8.0+:** Online DDL instant algorithm - sürətli.

### Sütun sil

```sql
ALTER TABLE users DROP COLUMN phone;

-- Xəbərdarlıq: production-da DROP COLUMN
-- - Həqiqətən table-ı rewrite edə bilər (MySQL)
-- - Data həmişəlik itir
-- - Code-da hələ də istifadə olunurdusa → crash
```

**Best practice:** İki ALTER mərhələsi:
1. Application-dan sütun istifadəsini sil (deploy)
2. Sütunu DB-dən sil

### Sütun dəyişdir (rename / tip)

```sql
-- Rename
-- PostgreSQL
ALTER TABLE users RENAME COLUMN phone TO mobile;

-- MySQL 8+
ALTER TABLE users RENAME COLUMN phone TO mobile;

-- MySQL köhnə versiya
ALTER TABLE users CHANGE phone mobile VARCHAR(20);

-- Type dəyiş
-- PostgreSQL
ALTER TABLE users ALTER COLUMN age TYPE BIGINT;

-- MySQL
ALTER TABLE users MODIFY COLUMN age BIGINT;

-- Cast ilə (PG)
ALTER TABLE users ALTER COLUMN age TYPE INTEGER USING age::INTEGER;
```

**DANGER:** Type dəyişmə bəzən table rewrite tələb edir (saatlar çəkə bilər).

### NOT NULL / DEFAULT dəyişdir

```sql
-- PostgreSQL
ALTER TABLE users ALTER COLUMN name SET NOT NULL;
ALTER TABLE users ALTER COLUMN name DROP NOT NULL;
ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user';
ALTER TABLE users ALTER COLUMN role DROP DEFAULT;

-- MySQL
ALTER TABLE users MODIFY COLUMN name VARCHAR(255) NOT NULL;
ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user';
```

### Rename table

```sql
-- PostgreSQL
ALTER TABLE users RENAME TO members;

-- MySQL
RENAME TABLE users TO members;
-- və ya
ALTER TABLE users RENAME TO members;
```

### Constraint əlavə / sil

```sql
-- PK
ALTER TABLE users ADD PRIMARY KEY (id);
ALTER TABLE users DROP PRIMARY KEY;                -- MySQL
ALTER TABLE users DROP CONSTRAINT users_pkey;      -- PG

-- UNIQUE
ALTER TABLE users ADD CONSTRAINT uk_email UNIQUE (email);
ALTER TABLE users DROP CONSTRAINT uk_email;
ALTER TABLE users DROP INDEX uk_email;             -- MySQL

-- FK
ALTER TABLE orders 
    ADD CONSTRAINT fk_user 
    FOREIGN KEY (user_id) REFERENCES users(id) 
    ON DELETE CASCADE;

ALTER TABLE orders DROP CONSTRAINT fk_user;
ALTER TABLE orders DROP FOREIGN KEY fk_user;       -- MySQL köhnə

-- CHECK
ALTER TABLE products ADD CONSTRAINT price_check CHECK (price >= 0);
ALTER TABLE products DROP CONSTRAINT price_check;
```

### Index əlavə / sil

```sql
-- Əlavə
CREATE INDEX idx_user_email ON users(email);
ALTER TABLE users ADD INDEX idx_email (email);     -- MySQL

-- Sil
DROP INDEX idx_user_email;                         -- PG
DROP INDEX idx_user_email ON users;                -- MySQL 8+
ALTER TABLE users DROP INDEX idx_email;            -- MySQL
```

Ətraflı: `26-create-index-syntax-basics.md`.

## DROP TABLE

```sql
DROP TABLE users;
DROP TABLE IF EXISTS users;                         -- yoxdursa error verme

-- CASCADE - FK-lı digər table-ları da drop et
DROP TABLE users CASCADE;                           -- PG
```

**Xəbərdarlıq:** DROP **geri qaytarılmaz**. Backup-siz nəticə kritikdir.

## Online vs Offline DDL

### MySQL Online DDL (InnoDB)

```sql
-- INSTANT - milisaniyə (yalnız metadata dəyişir)
ALTER TABLE users ADD COLUMN x VARCHAR(20) NULL, ALGORITHM=INSTANT;

-- INPLACE - table rewrite olmur, amma read/write qismən blocked
ALTER TABLE users ADD INDEX idx_name (name), ALGORITHM=INPLACE;

-- COPY - tam rewrite, uzun lock
ALTER TABLE users ADD COLUMN x VARCHAR(20), ALGORITHM=COPY;
```

**Qayda:** Production-da əvvəlcə `ALGORITHM=INSTANT` yoxla, sonra `INPLACE`, son çarə `COPY`.

### PostgreSQL Online DDL

```sql
-- Instant
ALTER TABLE users ADD COLUMN x VARCHAR(20);                              -- fast
ALTER TABLE users ADD COLUMN x VARCHAR(20) DEFAULT 'val';                -- PG11+ fast

-- Table rewrite (slow, exclusive lock)
ALTER TABLE users ALTER COLUMN x TYPE INTEGER;
ALTER TABLE users ADD COLUMN x INT NOT NULL;  -- PG10 - rewrite, PG11+ fast

-- CONCURRENT index (yavaş amma lock yox)
CREATE INDEX CONCURRENTLY idx_users_email ON users(email);
```

### gh-ost / pt-online-schema-change

Production-da böyük MySQL table-larında istifadə olunan tool-lar. Shadow table yaradır, data köçürür, sonra swap edir. **Downtime siz** ALTER.

## Backwards-Compatible Migration

Production-da schema dəyişikliklərini **2 deploy** mərhələsinə böl.

### Nümunə: Sütun rename

**BAD (1 step):**
```sql
-- Code: $user->phone → $user->mobile
-- DB: ALTER TABLE users RENAME COLUMN phone TO mobile;
-- PROBLEM: əvvəlki code deploy-da `$user->phone` hələ istifadə olunur
-- İkisindən biri crash edir
```

**GOOD (expand-contract):**
```sql
-- Step 1 (deploy): əlavə et (old + new işləyir)
ALTER TABLE users ADD COLUMN mobile VARCHAR(20);
UPDATE users SET mobile = phone;
-- Trigger: phone dəyişiklikləri mobile-ə də yaz
-- Code: həm phone, həm mobile yazıb oxu

-- Step 2 (deploy): yalnız yeni istifadə
-- Code: yalnız `mobile` işlət

-- Step 3 (deploy): köhnə-ni sil
ALTER TABLE users DROP COLUMN phone;
```

Ətraflı: `70-database-refactoring.md`.

## Schema Migration Tools

Production-da **manual** DDL yazmırıq. Migration tool istifadə olunur:

- Laravel: `php artisan make:migration` + Eloquent Schema Builder
- Rails: `rails generate migration`
- Django: `manage.py makemigrations`
- Flyway / Liquibase (Java)
- node-pg-migrate, Knex, Prisma (JS)

Ətraflı: `23-migrations.md`.

## Laravel Nümunəsi

```php
// Migration
php artisan make:migration create_orders_table

// Up
public function up() {
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained();
        $table->decimal('total', 10, 2);
        $table->string('status', 20)->default('pending');
        $table->timestamps();
        $table->softDeletes();
        
        $table->index(['user_id', 'created_at']);
    });
}

// Down
public function down() {
    Schema::dropIfExists('orders');
}

// ALTER migration
Schema::table('orders', function (Blueprint $table) {
    $table->string('payment_method', 50)->nullable()->after('total');
    $table->dropColumn('deprecated_field');
    $table->index('status');
});

// Rename sütun
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('phone', 'mobile');
});
```

## Interview Sualları

**Q: `ALTER TABLE ADD COLUMN` production-da təhlükəlidir?**
A: Bəli — table-a görə. Kiçik table-da OK. Milyon row-lu table-da NOT NULL + DEFAULT əlavə etmək MySQL/PG köhnə versiyalarda **table rewrite** və uzun müddət lock tutur. PG 11+ / MySQL 8+-də INSTANT algorithm bu problemi həll edir.

**Q: `DROP TABLE` geri qaytarıla bilər?**
A: Xeyir. Backup-dan restore lazımdır. `IF EXISTS` yalniz error vermir.

**Q: Sütun rename production-da necə təhlükəsiz edilir?**
A: Expand-contract pattern:
1. Deploy 1: yeni sütun əlavə, application hər iki sütuna yaz
2. Deploy 2: application yalnız yeni sütuna yaz/oxu
3. Deploy 3: köhnə sütun drop

**Q: `CREATE TABLE LIKE` və `CREATE TABLE AS` fərqi?**
A: `LIKE` — schema strukturu kopyalayır (index, constraint), data yox. `AS` — data kopyalayır, amma constraint və index-ləri yox.

**Q: MySQL `ALGORITHM=INPLACE` və `ALGORITHM=COPY` fərqi?**
A: INPLACE — table rewrite olmur, partial lock, fast. COPY — tam rewrite, uzun lock, slow. MySQL 8+-də INSTANT da var - metadata only.
