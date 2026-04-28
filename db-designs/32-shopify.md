# Shopify — DB Design & Technology Stack (Senior ⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    Shopify Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL (Vitess)       │ Shops, products, orders (core)           │
│ Redis                │ Sessions, cache, rate limiting, Resque   │
│ Elasticsearch        │ Product/order search                     │
│ Apache Kafka         │ Event streaming (Shopify created Sarama) │
│ HDFS + Spark         │ Analytics, data science                  │
│ Amazon S3            │ Product images, shop assets              │
│ Memcached            │ Application cache                        │
└──────────────────────┴──────────────────────────────────────────┘

Shopify = Vitess-in co-creator:
  Vitess-i YouTube ilə birlikdə industry standard etdi
  PlanetScale: Shopify engineers tərəfindən yaradıldı
  (Vitess-as-a-Service)
```

---

## Shopify-in DB Tarixi

```
2004: Ruby on Rails + MySQL (monolith)
  Tobias Lütke (Shopify founder) Rails-in early contributor
  Single MySQL server
  "Shopify was built to demo Rails"
  
2010-2014: Scale problems
  Black Friday → traffic spike
  Vertical scaling limit
  
2014: Sharding başladı
  Pod architecture: "shop pods"
  Her shard = bir group of shops
  
2016: Vitess adoption
  YouTube-un Vitess-i götürdü
  Transparent resharding
  
2019: PlanetScale founded
  Ex-Shopify + ex-YouTube engineers
  Vitess-as-a-Service
  
Flash sale architecture:
  Kylie Jenner makeup launch (2016)
  "We had to redesign everything for this"
  → Queue-based checkout, Redis inventory
```

---

## Multi-Tenant Architecture

```
Shopify = millions of stores on shared infrastructure

Tenant isolation:
  shop_id = shard key (via Vitess)
  
  Vitess VSchema:
  {
    "sharded": true,
    "vindexes": {
      "hash": {"type": "hash"}
    },
    "tables": {
      "orders": {
        "column_vindexes": [
          {"column": "shop_id", "name": "hash"}
        ]
      }
    }
  }

Shop pod:
  Each pod: N shards + MySQL cluster
  Shop → assigned to pod at creation
  Large shop → dedicated pod (enterprise)

Data isolation:
  shop_id on EVERY table (no cross-shop data)
  Row-level: WHERE shop_id = :current_shop
  Application enforces this (no accidental cross-tenant)
```

---

## MySQL Schema (Core Shopify)

```sql
-- shop_id hər cədvəldədir! (multi-tenant)

-- ==================== SHOPS ====================
CREATE TABLE shops (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    myshopify_domain VARCHAR(255) UNIQUE NOT NULL,  -- store.myshopify.com
    custom_domain   VARCHAR(255) UNIQUE,
    
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    
    -- Plan
    plan_name       VARCHAR(50),
    
    -- Location
    country_code    CHAR(2),
    currency        CHAR(3) DEFAULT 'USD',
    timezone        VARCHAR(50),
    
    -- Status
    is_active       BOOLEAN DEFAULT TRUE,
    
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== PRODUCTS ====================
CREATE TABLE products (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    shop_id     BIGINT UNSIGNED NOT NULL,  -- shard key
    
    title       VARCHAR(255) NOT NULL,
    body_html   MEDIUMTEXT,
    vendor      VARCHAR(255),
    product_type VARCHAR(255),
    
    -- Handle for URL
    handle      VARCHAR(255) NOT NULL,
    
    -- Status
    status      ENUM('active', 'draft', 'archived') DEFAULT 'draft',
    
    -- Tags
    tags        TEXT,  -- comma-separated (legacy Shopify)
    
    -- SEO
    seo_title       VARCHAR(70),
    seo_description VARCHAR(320),
    
    published_at    DATETIME,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_shop_handle (shop_id, handle),
    INDEX idx_shop_status (shop_id, status)
) ENGINE=InnoDB;

-- Product variants (size, color combinations)
CREATE TABLE variants (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    shop_id     BIGINT UNSIGNED NOT NULL,
    product_id  BIGINT UNSIGNED NOT NULL,
    
    title       VARCHAR(255),   -- "Small / Red"
    
    -- Options
    option1     VARCHAR(255),   -- "Small"
    option2     VARCHAR(255),   -- "Red"
    option3     VARCHAR(255),
    
    -- Pricing
    price       DECIMAL(10,2) NOT NULL,
    compare_at_price DECIMAL(10,2),
    cost_per_item DECIMAL(10,2),
    
    -- Inventory
    sku         VARCHAR(255),
    barcode     VARCHAR(255),
    
    inventory_policy ENUM('deny', 'continue') DEFAULT 'deny',
    -- deny: out of stock → cannot order
    -- continue: out of stock → can still order (backorder)
    
    inventory_quantity INT DEFAULT 0,
    
    -- Shipping
    requires_shipping BOOLEAN DEFAULT TRUE,
    weight       DECIMAL(10,3),
    weight_unit  ENUM('kg', 'g', 'lb', 'oz') DEFAULT 'kg',
    
    UNIQUE INDEX idx_shop_sku (shop_id, sku),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- ==================== ORDERS ====================
CREATE TABLE orders (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    shop_id         BIGINT UNSIGNED NOT NULL,
    order_number    INT UNSIGNED NOT NULL,  -- shop-scoped: #1001
    
    -- Customer
    customer_id     BIGINT UNSIGNED,
    email           VARCHAR(255),
    phone           VARCHAR(30),
    
    -- Status
    financial_status ENUM('pending', 'authorized', 'partially_paid',
                          'paid', 'partially_refunded', 'refunded',
                          'voided') DEFAULT 'pending',
    fulfillment_status ENUM('unfulfilled', 'partial', 'fulfilled',
                             'restocked') DEFAULT 'unfulfilled',
    
    -- Addresses (snapshot)
    shipping_address JSON,
    billing_address  JSON,
    
    -- Pricing
    subtotal_price   DECIMAL(10,2) NOT NULL,
    total_discounts  DECIMAL(10,2) DEFAULT 0,
    total_shipping   DECIMAL(10,2) DEFAULT 0,
    total_tax        DECIMAL(10,2) DEFAULT 0,
    total_price      DECIMAL(10,2) NOT NULL,
    currency         CHAR(3) NOT NULL,
    
    -- Source
    source_name     VARCHAR(50),  -- 'web', 'pos', 'mobile'
    
    -- Risk
    risk_level      ENUM('low', 'medium', 'high'),
    
    -- Note
    note            TEXT,
    tags            TEXT,
    
    processed_at    DATETIME,
    cancelled_at    DATETIME,
    cancel_reason   VARCHAR(100),
    
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_shop_number (shop_id, order_number),
    INDEX idx_shop_created (shop_id, created_at DESC),
    INDEX idx_customer (shop_id, customer_id)
) ENGINE=InnoDB;

-- ==================== INVENTORY ====================
-- Multi-location inventory (Shopify feature)
CREATE TABLE inventory_items (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    shop_id     BIGINT UNSIGNED NOT NULL,
    variant_id  BIGINT UNSIGNED NOT NULL UNIQUE,
    sku         VARCHAR(255),
    tracked     BOOLEAN DEFAULT TRUE,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory_levels (
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    location_id       BIGINT UNSIGNED NOT NULL,
    available         INT NOT NULL DEFAULT 0,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (inventory_item_id, location_id)
) ENGINE=InnoDB;
```

---

## Flash Sale Architecture

```
Kylie Jenner Lip Kit problem (2016):
  100K orders in milliseconds
  Normal checkout → database overwhelmed
  
Shopify-in həlli:

1. Inventory lock (Redis):
   DECR inventory:{variant_id}  -- atomic!
   < 0: rollback, sold out
   
   Lua script (atomic check + decrement):
   local stock = redis.call('GET', KEYS[1])
   if tonumber(stock) > 0 then
       redis.call('DECR', KEYS[1])
       return 1  -- success
   else
       return 0  -- sold out
   end

2. Queue-based checkout:
   Order request → Resque (Redis-based queue)
   Worker processes → payment → confirm
   
   User sees: "Order placed, processing..."
   Not: "Submit and wait 30 seconds"

3. Oversell prevention:
   Redis inventory: source of truth
   MySQL: updated async (eventual)
   
4. Read from replica:
   Product pages: MySQL read replica
   Checkout: MySQL master (consistency)

5. Rate limiting per shop:
   Flash sale → extra limits
   Protect shared infrastructure
```

---

## Shopify App Store: Webhooks

```
Shopify App ecosystem: 8,000+ apps
  Apps register webhooks
  Shopify fires on events: order created, product updated, etc.

Webhook delivery:
  Event → Kafka → Webhook service
  Retry: 5 attempts over 48 hours
  HMAC signature: X-Shopify-Hmac-Sha256 header
  
  HMAC = SHA-256(shared_secret + body)
  App verifies: replay attack prevention
  
Volume:
  Millions of webhooks per minute
  Shop with 10 apps × 100 orders/day = 1000 webhooks/day
  
MySQL:
CREATE TABLE webhook_subscriptions (
    id          BIGINT PRIMARY KEY AUTO_INCREMENT,
    shop_id     BIGINT NOT NULL,
    topic       VARCHAR(100),  -- 'orders/create', 'products/update'
    address     VARCHAR(500),  -- destination URL
    format      ENUM('json', 'xml') DEFAULT 'json',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

---

## Scale Faktları

```
Numbers (2023):
  1.75M+ active merchants
  700M+ customers worldwide
  $235B+ GMV (2022)
  Peak: Black Friday 2023: 61,000 orders/minute
  
  MySQL via Vitess:
  Hundreds of shards
  Petabytes of merchant data
  
  Redis:
  Job queues (Resque)
  Inventory locks
  Session cache
  
Engineering:
  ~10,000 employees
  PlanetScale co-founded by ex-Shopify engineers
  Ruby on Rails → still primary language
  "Rails scales if you do it right"
```

---

## Shopify-dən Öyrəniləcəklər

```
1. Vitess = horizontal MySQL:
   Shop pod → Vitess shard
   Resharding: transparent to application

2. shop_id everywhere:
   Multi-tenant: every query scoped to shop
   Accidental cross-tenant impossible if enforced

3. Redis inventory for flash sales:
   DECR atomic → no oversell
   Async MySQL sync → eventual consistency OK

4. Queue-based checkout:
   Don't process synchronously under spike
   Queue → async worker → confirm

5. Rails still works at scale:
   "Ruby is slow" → myth
   Proper caching + async + DB optimization > language choice
   "Premature optimization is evil"

6. Webhooks with HMAC:
   Shared secret + body hash
   App verifies → prevents replay attacks
   Industry standard for webhook security
```
