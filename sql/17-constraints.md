# Constraints (PK, FK, UNIQUE, CHECK, NOT NULL, DEFAULT)

> **Seviyye:** Beginner ⭐

Constraint — table-a qoyulan **qayda**. Data integrity-ni DB səviyyəsində təmin edir.

## 5 Əsas Constraint

1. **NOT NULL** — NULL icazə verilmir
2. **UNIQUE** — dəyər unikal olmalıdır
3. **PRIMARY KEY** — NOT NULL + UNIQUE (hər row unikal)
4. **FOREIGN KEY** — başqa table-a istinad
5. **CHECK** — xüsusi şərt

Plus **DEFAULT** — dəyər verilməsə bu istifadə olunur.

## PRIMARY KEY

Hər table-ın **1 primary key** olmalıdır — row-u unique identify edir.

```sql
-- Inline
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255)
);

-- Composite PK (çox sütundan ibarət)
CREATE TABLE order_items (
    order_id BIGINT,
    product_id BIGINT,
    quantity INT,
    PRIMARY KEY (order_id, product_id)
);

-- Named constraint
CREATE TABLE users (
    id BIGINT,
    name VARCHAR(255),
    CONSTRAINT pk_users PRIMARY KEY (id)
);
```

**PK xüsusiyyətləri:**
- NOT NULL avtomatik
- UNIQUE avtomatik
- Hər table-da **yalnız 1** PK
- B-Tree index avtomatik yaradılır (clustered index MySQL InnoDB-də)

## FOREIGN KEY

Bir table-in sütunu başqa table-in PK-nə istinad edir.

```sql
-- Inline syntax
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id)
);

-- Named
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ON DELETE / ON UPDATE action-ları
CREATE TABLE orders (
    user_id BIGINT REFERENCES users(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
);
```

### ON DELETE / ON UPDATE Actions

| Action | Davranış |
|--------|----------|
| `RESTRICT` / `NO ACTION` | Referenced row-u silmək/dəyişmək **qadağan** (default) |
| `CASCADE` | Parent silin əndə child də silinir |
| `SET NULL` | Parent silin əndə child-ın FK-ı NULL olur |
| `SET DEFAULT` | Parent silin əndə FK DEFAULT dəyər alır |

```sql
-- Nümunə strategiyalar:

-- User silinəndə order-ləri də silmək (risk!)
ON DELETE CASCADE

-- User silinməsin, order varsa 
ON DELETE RESTRICT              -- default

-- User silinəndə order anonimləşsin
ON DELETE SET NULL              -- user_id NULL olur

-- Order qalsın, amma user silinə bilməz
-- Soft delete istifadə et
```

Ətraflı FK: `46-foreign-keys-deep.md`.

## UNIQUE Constraint

Dəyər table-də **unique** olmalıdır.

```sql
-- Inline
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    email VARCHAR(255) UNIQUE
);

-- Named
CREATE TABLE users (
    email VARCHAR(255),
    CONSTRAINT uk_email UNIQUE (email)
);

-- Composite UNIQUE (kombinasiyanın unikal olması)
CREATE TABLE followings (
    follower_id BIGINT,
    following_id BIGINT,
    UNIQUE (follower_id, following_id)
);
```

### UNIQUE və NULL

```sql
-- Standard: NULL-lar unique sayılmır (bir neçə NULL OK)
CREATE TABLE users (
    email VARCHAR(255) UNIQUE
);
INSERT INTO users (email) VALUES (NULL), (NULL);   -- OK!

-- PostgreSQL 15+: NULLS NOT DISTINCT (NULL-ları da unique)
CREATE UNIQUE INDEX idx_email ON users (email) NULLS NOT DISTINCT;

-- SQL Server: yalniz 1 NULL icazə verir!
-- Həll: filtered index
CREATE UNIQUE INDEX idx_email ON users(email) WHERE email IS NOT NULL;
```

### UNIQUE Partial Index

```sql
-- Yalnız aktiv user-lər üçün unique email
-- (Soft delete-də olan user-i yenidən qeydiyyatdan keçirməyə imkan verir)
CREATE UNIQUE INDEX idx_email_active 
ON users (email) 
WHERE deleted_at IS NULL;                           -- PostgreSQL
```

## NOT NULL

```sql
-- Inline
CREATE TABLE users (
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL
);

-- ALTER ilə
ALTER TABLE users ALTER COLUMN name SET NOT NULL;
ALTER TABLE users ALTER COLUMN email SET NOT NULL;
```

**Best Practice:** Əksər sütunlar NOT NULL olsun, DEFAULT dəyərlə.

```sql
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,                        -- MUTLƏQ
    total DECIMAL(10,2) NOT NULL DEFAULT 0,         -- DEFAULT 0
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    shipped_at TIMESTAMPTZ                          -- NULL OK (hələ göndərilməyib)
);
```

## DEFAULT

```sql
-- Sadə default
CREATE TABLE users (
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    is_active BOOLEAN DEFAULT TRUE
);

-- Generated / funksiya ilə
CREATE TABLE users (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY    -- PostgreSQL
);

-- MySQL: NOW() / CURRENT_TIMESTAMP
CREATE TABLE users (
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### DEFAULT və INSERT

```sql
-- Explicit DEFAULT
INSERT INTO users (name, role) VALUES ('Ali', DEFAULT);

-- Atla (DEFAULT tətbiq olunur)
INSERT INTO users (name) VALUES ('Ali');

-- Bütün DEFAULTs
INSERT INTO users DEFAULT VALUES;
```

## CHECK Constraint

Xüsusi şərt yoxlayır.

```sql
-- Inline
CREATE TABLE products (
    price DECIMAL(10,2) CHECK (price >= 0),
    quantity INT CHECK (quantity >= 0),
    discount_percent INT CHECK (discount_percent BETWEEN 0 AND 100)
);

-- Named
CREATE TABLE orders (
    status VARCHAR(20),
    CONSTRAINT status_check CHECK (status IN ('pending', 'paid', 'shipped', 'cancelled'))
);

-- Çox sütunlu (table-level)
CREATE TABLE events (
    start_at TIMESTAMP,
    end_at TIMESTAMP,
    CONSTRAINT check_dates CHECK (end_at > start_at)
);

-- ALTER ilə əlavə et
ALTER TABLE users ADD CONSTRAINT age_check CHECK (age >= 0 AND age <= 150);
```

### CHECK və NULL

```sql
-- CHECK NULL ilə UNKNOWN qaytarir — keçir (row qəbul olunur)
ALTER TABLE products ADD CONSTRAINT price_check CHECK (price > 0);
INSERT INTO products (price) VALUES (NULL);        -- OK!

-- NULL qadağan etmək üçün NOT NULL + CHECK
ALTER TABLE products ALTER COLUMN price SET NOT NULL;
```

### CHECK-də Funksiyalar (PG 12+)

```sql
-- PostgreSQL: IMMUTABLE funksiya OK
CREATE TABLE emails (
    email TEXT,
    CHECK (email ~ '^[^@]+@[^@]+\.[^@]+$')         -- regex valid email
);

-- MySQL 8.0.16+ də CHECK işləyir
```

## Composite / Multi-Column Constraint

```sql
-- PK composite
CREATE TABLE user_roles (
    user_id BIGINT,
    role_id BIGINT,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- UNIQUE composite
CREATE TABLE slots (
    venue_id BIGINT,
    date DATE,
    time TIME,
    UNIQUE (venue_id, date, time)
);

-- CHECK composite
CREATE TABLE events (
    ...
    CHECK (end_at > start_at AND end_at < start_at + INTERVAL '30 days')
);
```

## Constraint əlavə etmək / silmək

```sql
-- Əlavə et
ALTER TABLE users ADD CONSTRAINT uk_email UNIQUE (email);
ALTER TABLE orders ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id);
ALTER TABLE users ADD CONSTRAINT age_check CHECK (age >= 0);

-- Sil
ALTER TABLE users DROP CONSTRAINT uk_email;
ALTER TABLE orders DROP CONSTRAINT fk_user;

-- Named olmayan - MySQL
ALTER TABLE users DROP INDEX email;        -- UNIQUE INDEX adı ilə
```

## Deferrable Constraints (PostgreSQL)

Transaction sonunda yoxlansın.

```sql
-- Transaction sonunda check
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    prev_id BIGINT REFERENCES orders(id) DEFERRABLE INITIALLY DEFERRED
);

-- Transaction ortasında FK pozula bilər, sonunda düzələrsə OK
BEGIN;
INSERT INTO orders (id, prev_id) VALUES (2, 1);   -- prev_id=1 hələ yoxdur
INSERT INTO orders (id) VALUES (1);               -- indi yarandı
COMMIT;                                           -- FK check burada, OK
```

**İstifadə:** Circular FK, batch insert.

## Constraint Yoxlama

```sql
-- PostgreSQL
SELECT conname, contype FROM pg_constraint WHERE conrelid = 'users'::regclass;

-- MySQL
SELECT * FROM information_schema.table_constraints WHERE table_name = 'users';

-- Violation halında error message
ERROR: new row for relation "users" violates check constraint "age_check"
ERROR: duplicate key value violates unique constraint "uk_email"
ERROR: insert or update violates foreign key constraint "fk_user"
```

## Laravel Nümunəsi

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();                                  // PK
    $table->string('email')->unique();             // UNIQUE
    $table->string('name');                        // NOT NULL (default)
    $table->string('status', 20)->default('active');
    $table->integer('age')->nullable();
    $table->timestamp('created_at')->useCurrent();
    
    // CHECK
    // Laravel native method yoxdur - raw
});

// CHECK raw
DB::statement('ALTER TABLE users ADD CONSTRAINT age_check CHECK (age >= 0)');

// Foreign key
Schema::table('orders', function (Blueprint $table) {
    $table->foreignId('user_id')
          ->constrained()
          ->onDelete('cascade');
});

// Composite unique
Schema::create('followings', function (Blueprint $table) {
    $table->foreignId('follower_id');
    $table->foreignId('following_id');
    $table->unique(['follower_id', 'following_id']);
});
```

## Best Practices

1. **PK həmişə olsun** — `BIGINT` və ya `UUID`
2. **FK olan sütunlar indexed olsun** (MySQL avtomatik, PG yox)
3. **NOT NULL default** — yalnız mütləq NULL ola biləcəyi üçün NULL icazə ver
4. **UNIQUE constraint-ə partial index əlavə et** (soft delete-lə birlikdə işlədikdə)
5. **CHECK constraint** domain validation — DB-də enforce et, yalnız application-dan inanma
6. **Named constraint-lar** — ALTER/DROP zamanı asan olsun
7. **DEFAULT** — database səviyyəsində, application default əvəzinə

## Interview Sualları

**Q: `PRIMARY KEY` və `UNIQUE` fərqi?**
A: PK = NOT NULL + UNIQUE + cədvəldə yalnız 1 ədəd + clustered index (MySQL). UNIQUE NULL icazə verir, bir cədvəldə bir neçə UNIQUE ola bilər.

**Q: `ON DELETE CASCADE` təhlükəlidir?**
A: Hə - parent silindikdə child-in bütün zəncirini silə bilər, bəzən gözlənilməz davranış. Explicit hallarda istifadə et. Əksər hallarda `ON DELETE RESTRICT` + soft delete daha təhlükəsizdir.

**Q: NULL dəyər UNIQUE-i pozur?**
A: Standart SQL-də: xeyir (NULL ≠ NULL). Bir neçə NULL-a icazə verilir. PG 15+ `NULLS NOT DISTINCT` ilə dəyişə bilər. SQL Server yalnız 1 NULL-a icazə verir.

**Q: CHECK constraint və trigger arasında fərq nədir?**
A: CHECK — sadə, fast, DB optimize edə bilir (planning). Trigger — kompleks logic, başqa table-lara baxa bilir, audit yaza bilir, amma daha yavaşdır.

**Q: Foreign key hər zaman indexed olmalıdır?**
A: **BƏLİ**. MySQL avtomatik FK üçün index yaradır. PostgreSQL YOX! Sən əllə yaratmalısan. Əks halda parent DELETE çox yavaş olur (FK lookup üçün child-də full scan).
