# Data Modeling & Schema Design

> **Seviyye:** Intermediate ⭐⭐

## Data Modeling Nedir?

Real-world melumatlarini database structure-a cevirmek prosesidir. Duzgun schema design - performansli, scalable, ve maintain edile bilen sistem demekdir.

## Schema Design Prinsipleri

### 1. Dogru Data Type Secimi

```sql
-- YANLIS: Her sey VARCHAR
CREATE TABLE orders (
    id VARCHAR(255),           -- Niye string?
    user_id VARCHAR(255),      -- FK integer olmalidir
    total VARCHAR(255),        -- Hesablama mumkun deyil
    quantity VARCHAR(255),     -- Hesablama mumkun deyil
    is_paid VARCHAR(255),      -- Boolean olmalidir
    created_at VARCHAR(255)    -- Date olmalidir
);

-- DOGRU: Uygun tipler
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    is_paid BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### Esas Data Type Tovsiyeler

| Melumat | Tovsiye olunan tip | Sebebi |
|---------|-------------------|--------|
| **Primary Key** | BIGINT UNSIGNED | 4 milyard+ row, auto_increment |
| **UUID** | BINARY(16) veya CHAR(36) | BINARY daha suretli, CHAR oxunaqli |
| **Pul** | DECIMAL(12,2) | FLOAT/DOUBLE deqiq deyil! |
| **Boolean** | BOOLEAN (TINYINT(1)) | |
| **Email** | VARCHAR(320) | RFC standart max uzunlugu |
| **IP address** | VARBINARY(16) | IPv4 + IPv6, INET_ATON/INET_NTOA |
| **Phone** | VARCHAR(20) | +country code ile |
| **Enum values** | ENUM veya VARCHAR | ENUM deyisiklik ALTER lazimdir |
| **JSON data** | JSON (MySQL) / JSONB (PG) | Validation + index destegi |
| **Tarix** | DATE / DATETIME / TIMESTAMP | TIMESTAMP timezone-aware |
| **Boyuk metn** | TEXT | VARCHAR(max) evezine |
| **Status** | VARCHAR(20) veya ENUM | INT status kodu oxunmaz |

### 2. Primary Key Secimi

```sql
-- Auto-increment (EN POPULYAR)
-- Ustunluk: Suretli insert, kicik index, sequential
-- Dezavantaj: Predict edile biler, distributed system-de problem
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
);

-- UUID v4
-- Ustunluk: Globally unique, distributed system ucun
-- Dezavantaj: Boyuk (16 byte), random - index fragmentation
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID())  -- MySQL 8.0+
);

-- UUID v7 (zamana gore siralanan, 2024+ trend)
-- Ustunluk: Sequential kimi insert performance, globally unique
-- Binary olaraq saxla:
CREATE TABLE users (
    id BINARY(16) PRIMARY KEY
);

-- Composite Primary Key
CREATE TABLE order_items (
    order_id BIGINT UNSIGNED,
    product_id BIGINT UNSIGNED,
    quantity INT UNSIGNED NOT NULL,
    PRIMARY KEY (order_id, product_id)
);
```

### 3. Foreign Key ve Referential Integrity

```sql
-- Foreign Key ile
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT    -- User siline bilmez (sifaris varsa)
        ON UPDATE CASCADE     -- User id deyisse, avtomatik yenilenir
);

CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE     -- Order silinse, item-ler de silinir
    FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT    -- Product siline bilmez
);
```

**ON DELETE secenekleri:**
| Action | Izah | Ne vaxt |
|--------|------|---------|
| `RESTRICT` | Silmeye icaze vermir | Parent vacibdirse |
| `CASCADE` | Usaq row-lari da silir | Composition relation |
| `SET NULL` | FK-ni NULL edir | Optional relation |
| `SET DEFAULT` | Default deyer qoyur | Nadir |

### 4. Status Management

```sql
-- YANLIS: Status ucun integer
status = 1  -- Bu ne demekdir?
status = 2  -- Oxunmaz!

-- DOGRU: String status
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    CHECK (status IN ('pending', 'confirmed', 'shipped', 'delivered', 'cancelled'))
);

-- Ayrica: Status transition history
CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(20),
    to_status VARCHAR(20) NOT NULL,
    changed_by BIGINT UNSIGNED,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX idx_order_status (order_id, created_at)
);
```

## Real-World Schema Design Misallari

### E-Commerce Schema

```sql
-- Users
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(320) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_active (is_active)
);

-- Addresses (bir user-in bir nece adresi ola biler)
CREATE TABLE addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('shipping', 'billing') NOT NULL,
    line1 VARCHAR(255) NOT NULL,
    line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_code CHAR(2) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, type)
);

-- Products
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(12,2) NOT NULL,
    cost DECIMAL(12,2),             -- Maye deyeri
    stock_quantity INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON,                   -- Flexible attributes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_active_price (is_active, price)
);

-- Orders
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) NOT NULL UNIQUE,  -- Human-readable
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(12,2) NOT NULL,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    shipping_address_id BIGINT UNSIGNED,
    billing_address_id BIGINT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_status (user_id, status),
    INDEX idx_created (created_at),
    INDEX idx_order_number (order_number)
);

-- Order Items (sifarisin icindeki mehsullar)
CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,    -- Snapshot! Product adi deyise biler
    product_price DECIMAL(12,2) NOT NULL,  -- Snapshot! Qiymet deyise biler
    quantity INT UNSIGNED NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id)
);

-- Payments
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    method VARCHAR(30) NOT NULL,         -- 'credit_card', 'paypal', 'bank_transfer'
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    gateway_transaction_id VARCHAR(255), -- Odenis gateway-in ID-si
    gateway_response JSON,               -- Gateway cavabi
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_gateway_txn (gateway_transaction_id)
);
```

> **Vacib:** `order_items`-de `product_name` ve `product_price` **snapshot** kimi saxlanilir. Product-in adi ve ya qiymeti sonradan deyise biler, amma sifaris zamanindaki deyer saxlanmalidir!

### Polymorphic Relations

Bir table birden cox table ile elaqeli olduqda:

```sql
-- YANLIS: Her sey ucun ayri FK
CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED,
    product_id BIGINT UNSIGNED,
    video_id BIGINT UNSIGNED
    -- Yenisi elave olanda ALTER TABLE lazimdir!
);

-- DOGRU: Polymorphic
CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commentable_type VARCHAR(50) NOT NULL,  -- 'Post', 'Product', 'Video'
    commentable_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_commentable (commentable_type, commentable_id),
    INDEX idx_user (user_id)
);
-- Qeyd: FK constraint polymorphic-de islemir - application seviyyesinde yoxlamaq lazimdir
```

### Tags / Many-to-Many

```sql
CREATE TABLE tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE
);

-- Pivot table
CREATE TABLE taggables (
    tag_id BIGINT UNSIGNED NOT NULL,
    taggable_type VARCHAR(50) NOT NULL,
    taggable_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (tag_id, taggable_type, taggable_id),
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_taggable (taggable_type, taggable_id)
);
```

## Anti-Patterns (Qacinilasi Seyler)

### 1. God Table

```sql
-- YANLIS: Her sey bir table-da
CREATE TABLE entities (
    id INT PRIMARY KEY,
    type VARCHAR(50),     -- 'user', 'product', 'order'
    field1 VARCHAR(255),  -- type-a gore menasi deyisir
    field2 VARCHAR(255),
    field3 TEXT,
    field4 DECIMAL(10,2)
    -- NULL-larla dolu, oxunmaz!
);
```

### 2. Comma-Separated Values

```sql
-- YANLIS
CREATE TABLE users (
    id INT PRIMARY KEY,
    roles VARCHAR(255)  -- 'admin,editor,viewer'
);
-- Query: WHERE FIND_IN_SET('admin', roles) → index istifade etmir!

-- DOGRU: Ayri table
CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED,
    role VARCHAR(50),
    PRIMARY KEY (user_id, role)
);
```

### 3. Nullable Foreign Keys Everywhere

```sql
-- YANLIS: Nece ki yuxarida gosterdik, her FK nullable
CREATE TABLE orders (
    user_id BIGINT UNSIGNED,          -- NULL ola bilermi? Sifarisi kim verdi?
    shipping_address_id BIGINT UNSIGNED  -- Bu NULL ola biler (pickup)
);

-- DOGRU: Yalniz meqsedli NULL-lar
CREATE TABLE orders (
    user_id BIGINT UNSIGNED NOT NULL,        -- Mutleq olmalidir
    shipping_address_id BIGINT UNSIGNED NULL  -- Pickup halinda NULL ola biler
);
```

## Naming Conventions

| Element | Convention | Misal |
|---------|-----------|-------|
| **Table** | Plural, snake_case | `order_items` |
| **Column** | Singular, snake_case | `first_name` |
| **Primary Key** | `id` | `id` |
| **Foreign Key** | `{singular_table}_id` | `user_id` |
| **Boolean** | `is_`, `has_`, `can_` prefix | `is_active` |
| **Timestamp** | `_at` suffix | `created_at`, `paid_at` |
| **Date** | `_date` suffix | `birth_date` |
| **Index** | `idx_{table}_{columns}` | `idx_orders_user_status` |
| **Unique** | `uq_{table}_{columns}` | `uq_users_email` |

## Interview Suallari

1. **INT vs BIGINT vs UUID - primary key ucun hansi secilmelidir?**
   - Tek database: BIGINT (suretli, kicik). Distributed: UUID v7 (sequential + unique). UUID v4 index fragmentation yaradir.

2. **order_items-de product_name ve product_price niye saxlanilir?**
   - Snapshot pattern: product adi ve qiymeti deyise biler, amma sifaris zamanindaki deyerler qorunmalidir.

3. **Polymorphic relation-larin dezavantaji?**
   - FK constraint qoymaq mumkun deyil, referential integrity application seviyyesinde izlenmelidir.

4. **DECIMAL vs FLOAT pul ucun?**
   - DECIMAL: Deqiq hesablama (0.1 + 0.2 = 0.3). FLOAT: Approximate (0.1 + 0.2 = 0.30000000000000004). Pul ucun HER ZAMAN DECIMAL.

5. **Schema design-da en boyuk sehvler?**
   - God table, comma-separated values, yanlis data type, FK olmadan reference, NULL suiistifadesi.
