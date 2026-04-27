# E-Commerce App — DB Design

## Tövsiyə olunan DB Stack
```
Primary:       PostgreSQL        (məhsullar, sifarişlər, istifadəçilər — ACID)
Cache:         Redis             (səbət, session, qiymət cache)
Search:        Elasticsearch     (məhsul axtarışı, autocomplete)
Media:         S3 + CDN          (məhsul şəkilləri)
Analytics:     ClickHouse        (satış analitikası, hesabatlar)
Recommendation: Neo4j / Redis   (oxşar məhsullar)
```

---

## Niyə PostgreSQL?

```
E-commerce-in əsas tələbləri:
  ✓ ACID: ödəniş + stok azaltma atomik olmalıdır
  ✓ Complex queries: kateqoriya + qiymət + brend + stok filtrləri
  ✓ Transactions: order yerləşdirmə multi-table update
  ✓ Foreign keys: referential integrity (order_items → products)
  
PostgreSQL spesifik üstünlüklər:
  ✓ JSONB: məhsul xüsusiyyətləri (attributes) flex schema
  ✓ Full-text search (tsvector): axtarış (Elasticsearch əvəzinə kiçik miqyasda)
  ✓ Window functions: "Ən çox satılan məhsullar" analitikası
  ✓ Partial indexes: WHERE status = 'active' olan sorğular
  ✓ PostGIS: yaxınlıqdakı mağazalar
```

---

## Schema Design

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name    VARCHAR(100),
    last_name     VARCHAR(100),
    phone         VARCHAR(20),
    status        VARCHAR(20) DEFAULT 'active',  -- active, suspended, deleted
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    updated_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE addresses (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID NOT NULL REFERENCES users(id),
    label      VARCHAR(50),   -- 'Ev', 'İş'
    line1      VARCHAR(255) NOT NULL,
    line2      VARCHAR(255),
    city       VARCHAR(100) NOT NULL,
    country    CHAR(2) NOT NULL,
    postal_code VARCHAR(20),
    is_default  BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CATALOG ====================
CREATE TABLE categories (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id   UUID REFERENCES categories(id),
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    path        TEXT,    -- materialized path: /electronics/phones/
    depth       INT DEFAULT 0,
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE
);
-- Materialized path niyə? Bütün subcategory-ləri bir sorğu ilə almaq üçün
-- WHERE path LIKE '/electronics/%'

CREATE TABLE brands (
    id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name     VARCHAR(100) UNIQUE NOT NULL,
    slug     VARCHAR(100) UNIQUE NOT NULL,
    logo_url TEXT
);

CREATE TABLE products (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id   UUID NOT NULL REFERENCES categories(id),
    brand_id      UUID REFERENCES brands(id),
    name          VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) UNIQUE NOT NULL,
    description   TEXT,
    attributes    JSONB DEFAULT '{}',  -- {"color": "red", "storage": "128GB"}
    base_price    NUMERIC(12,2) NOT NULL,
    currency      CHAR(3) DEFAULT 'AZN',
    status        VARCHAR(20) DEFAULT 'draft',  -- draft, active, discontinued
    is_featured   BOOLEAN DEFAULT FALSE,
    seo_title     VARCHAR(255),
    seo_desc      TEXT,
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    updated_at    TIMESTAMPTZ DEFAULT NOW()
);

-- JSONB attributes üçün GIN index
CREATE INDEX idx_products_attributes ON products USING GIN (attributes);
-- Full-text search üçün
CREATE INDEX idx_products_fts ON products
    USING GIN (to_tsvector('azerbaijani', name || ' ' || COALESCE(description, '')));

-- Product variants (S, M, L beden; Qırmızı, Mavi rəng)
CREATE TABLE product_variants (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id    UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku           VARCHAR(100) UNIQUE NOT NULL,
    attributes    JSONB NOT NULL,  -- {"size": "M", "color": "red"}
    price         NUMERIC(12,2),   -- NULL → parent product price
    compare_price NUMERIC(12,2),   -- "Köhnə qiymət" (keçirilmiş)
    weight_grams  INT,
    images        JSONB DEFAULT '[]',  -- [{url, alt, sort}]
    sort_order    INT DEFAULT 0,
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- Inventory (stok)
CREATE TABLE inventory (
    variant_id    UUID PRIMARY KEY REFERENCES product_variants(id),
    quantity      INT NOT NULL DEFAULT 0,
    reserved      INT NOT NULL DEFAULT 0,  -- rezerv edilmiş (ödəniş gözlənilir)
    warehouse_id  UUID,
    updated_at    TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT quantity_non_negative CHECK (quantity >= 0),
    CONSTRAINT reserved_non_negative CHECK (reserved >= 0)
);
-- available = quantity - reserved

-- ==================== ORDERS ====================
CREATE TABLE orders (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID REFERENCES users(id),
    status          VARCHAR(30) NOT NULL DEFAULT 'pending',
    -- pending, payment_pending, confirmed, processing, shipped, delivered, cancelled, refunded
    subtotal        NUMERIC(12,2) NOT NULL,
    discount_amount NUMERIC(12,2) DEFAULT 0,
    shipping_amount NUMERIC(12,2) DEFAULT 0,
    tax_amount      NUMERIC(12,2) DEFAULT 0,
    total           NUMERIC(12,2) NOT NULL,
    currency        CHAR(3) DEFAULT 'AZN',
    shipping_addr   JSONB NOT NULL,  -- snapshot (address sonradan dəyişsə belə qalır)
    notes           TEXT,
    coupon_code     VARCHAR(50),
    placed_at       TIMESTAMPTZ,
    confirmed_at    TIMESTAMPTZ,
    shipped_at      TIMESTAMPTZ,
    delivered_at    TIMESTAMPTZ,
    cancelled_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE order_items (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID NOT NULL REFERENCES orders(id),
    variant_id      UUID NOT NULL REFERENCES product_variants(id),
    product_name    VARCHAR(255) NOT NULL,  -- snapshot
    variant_sku     VARCHAR(100) NOT NULL,  -- snapshot
    variant_attrs   JSONB,                  -- snapshot
    quantity        INT NOT NULL,
    unit_price      NUMERIC(12,2) NOT NULL, -- snapshot (qiymət dəyişsə belə)
    total_price     NUMERIC(12,2) NOT NULL
);
-- Niyə snapshot? Məhsul silinəndə və ya qiymət dəyişəndə köhnə sifariş qorunur

CREATE TABLE payments (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID NOT NULL REFERENCES orders(id),
    provider        VARCHAR(50) NOT NULL,  -- 'stripe', 'paypal', 'cash'
    provider_ref    VARCHAR(255),          -- Stripe charge_id
    amount          NUMERIC(12,2) NOT NULL,
    currency        CHAR(3) DEFAULT 'AZN',
    status          VARCHAR(30) NOT NULL,  -- pending, authorized, captured, failed, refunded
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== PROMOTIONS ====================
CREATE TABLE coupons (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code            VARCHAR(50) UNIQUE NOT NULL,
    type            VARCHAR(20) NOT NULL,  -- 'percent', 'fixed', 'free_shipping'
    value           NUMERIC(8,2) NOT NULL,
    min_order_amount NUMERIC(12,2),
    max_uses        INT,
    used_count      INT DEFAULT 0,
    valid_from      TIMESTAMPTZ,
    valid_until     TIMESTAMPTZ,
    is_active       BOOLEAN DEFAULT TRUE
);

-- ==================== REVIEWS ====================
CREATE TABLE reviews (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  UUID NOT NULL REFERENCES products(id),
    user_id     UUID NOT NULL REFERENCES users(id),
    order_id    UUID REFERENCES orders(id),  -- yalnız alan şərh yaza bilər
    rating      SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title       VARCHAR(255),
    body        TEXT,
    is_verified BOOLEAN DEFAULT FALSE,       -- alışla doğrulanmış
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (product_id, user_id)             -- bir user bir məhsula bir şərh
);
```

---

## Redis Dizaynı

```
# Səbət (Cart) — Redis Hash
HSET cart:{user_id} {variant_id} {quantity}
HGETALL cart:{user_id}
EXPIRE cart:{user_id} 604800       -- 7 gün TTL

# Məhsul qiymət cache
SET price:{variant_id} 99.99 EX 3600

# Session
SET session:{token} {user_id_json} EX 86400

# Stock cache (sürətli yoxlama)
SET stock:{variant_id} 150 EX 300  -- 5 dəqiqə

# Flash sale counter (atomik)
DECR flash_stock:{variant_id}      -- atomic, thread-safe

# Son baxılan məhsullar
LPUSH viewed:{user_id} {product_id}
LTRIM viewed:{user_id} 0 19        -- son 20
```

---

## Elasticsearch Dizaynı

```json
{
  "index": "products",
  "mappings": {
    "properties": {
      "name":        {"type": "text", "analyzer": "azerbaijani"},
      "description": {"type": "text", "analyzer": "azerbaijani"},
      "brand":       {"type": "keyword"},
      "category_id": {"type": "keyword"},
      "price":       {"type": "scaled_float", "scaling_factor": 100},
      "rating":      {"type": "half_float"},
      "in_stock":    {"type": "boolean"},
      "attributes":  {"type": "object", "dynamic": true},
      "created_at":  {"type": "date"}
    }
  }
}
```

---

## Kritik Dizayn Qərarları

```
1. Order item snapshot:
   Məhsul adı, qiymət, SKU order_items-da kopyalanır
   Niyə? Məhsul silinsə, yenilənəsə köhnə sifariş qorunmalıdır
   Trade-off: data duplication amma correctness

2. Inventory.reserved sütunu:
   Ödəniş başladıqda quantity azaltma, reserved artırma
   Ödəniş uğurlu: reserved azal (artıq satılmış)
   Ödəniş uğursuz: reserved azal (available qaytarılır)
   Niyə? Stok qıtlığı və double-sell önlənir

3. Categories materialized path:
   Bütün subcategory-ləri almaq: WHERE path LIKE '/electronics/%'
   Alternativ: Closure table (join-intensive)
   Adjacency list (recursive CTE) — performans fərqli

4. JSONB attributes products-da:
   Fərqli məhsul tiplərinin fərqli xüsusiyyətləri var
   Telefon: {storage, ram, color}
   Kişi geyimi: {size, color, material}
   JSONB → flexible, index-able

5. Addresses snapshot in orders:
   JSONB shipping_addr sifariş zamanı kopyalanır
   User adresini sonradan dəyişsə, köhnə sifariş adresi qorunur
```

---

## Critical Queries və İndexlər

```sql
-- Stok kontrolu ilə sifarişin atomik şəkildə işlənməsi
BEGIN;
  -- Stoku yoxla və rezerv et (pessimistic lock)
  UPDATE inventory
  SET reserved = reserved + 2
  WHERE variant_id = 'abc' AND (quantity - reserved) >= 2
  RETURNING *;

  -- 0 row affected = stok yoxdur → ROLLBACK
  INSERT INTO orders (...) VALUES (...);
  INSERT INTO order_items (...) VALUES (...);
COMMIT;

-- Ən çox satılan məhsullar (son 30 gün)
SELECT p.name, SUM(oi.quantity) as total_sold
FROM order_items oi
JOIN product_variants pv ON pv.id = oi.variant_id
JOIN products p ON p.id = pv.product_id
JOIN orders o ON o.id = oi.order_id
WHERE o.status IN ('delivered', 'completed')
  AND o.placed_at > NOW() - INTERVAL '30 days'
GROUP BY p.id, p.name
ORDER BY total_sold DESC
LIMIT 10;

-- Məhsul axtarışı (PostgreSQL FTS)
SELECT id, name, base_price
FROM products
WHERE to_tsvector('simple', name) @@ plainto_tsquery('simple', 'telefon samsung')
  AND status = 'active'
ORDER BY ts_rank(to_tsvector('simple', name), plainto_tsquery('simple', 'telefon samsung')) DESC;

-- İndexlər
CREATE INDEX idx_orders_user_status ON orders(user_id, status, placed_at DESC);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_inventory_available ON inventory(variant_id) WHERE (quantity - reserved) > 0;
CREATE INDEX idx_products_active_cat ON products(category_id, base_price) WHERE status = 'active';
```

---

## Best Practices

```
✓ Inventoru transaction daxilində UPDATE et (double-sell önlənir)
✓ Order items-da price snapshot saxla
✓ Soft delete (deleted_at timestamp) products üçün
✓ Ödəniş state machine: pending → authorized → captured
✓ Coupon istifadəsini atomik UPDATE ilə say (race condition)
✓ Elasticsearch-ə async index (main axını yavaşlatmasın)
✓ Product images S3-də, DB-də yalnız JSONB URL array
✓ Audit log: hər status dəyişikliyi (Order history)

Anti-patterns:
✗ Inventory-ni SELECT → check → UPDATE (race condition!)
✗ Order items-da FK-dan qiymət almaq (snapshot yoxdur)
✗ Soft delete olmayan product delete (sifarişlər broken)
✗ Cart-ı DB-də saxlamaq (Redis daha uyğun)
```

---

## Flash Sale Inventory (Redis Lua)

```
Problem: 10,000 insan eyni anda 100 ədəd məhsul almağa çalışır

Naive approach (MySQL):
  SELECT stock FROM inventory WHERE product_id = ?
  IF stock > 0:
    UPDATE inventory SET stock = stock - 1
  → Race condition: 2 nəfər eyni anda yoxlayır, ikisi də satın alır!

Redis Lua (atomic):
  local stock = redis.call('GET', KEYS[1])
  if stock == false then return -1 end  -- not found
  if tonumber(stock) <= 0 then return 0 end  -- sold out
  redis.call('DECR', KEYS[1])
  return tonumber(stock) - 1  -- remaining

-- Setup: Flash sale başlamazdan əvvəl
SET inventory:flash:{variant_id} 100  -- 100 ədəd

-- Hər alış cəhdində:
EVAL {lua_script} 1 inventory:flash:{variant_id}
-- Return 0: sold out, Return N: remaining

-- Async MySQL sync:
-- Redis counter → Kafka → MySQL consumer (eventual)

Multi-currency:
  prices tablosu:
    product_id, currency, amount, updated_at
  Exchange rates Redis-də:
    SET fx:USD:AZN 1.70 EX 1800  -- 30 dəq TTL
  Hesab: price_usd * redis.get(fx:USD:AZN)

Tax calculation:
  tax_rules tablosu:
    country, state, product_category, rate
  Application layer:
    tax = subtotal * tax_rate(country, category)
  Mürəkkəb US vergi qaydalari üçün: Taxjar/Avalara API
```
