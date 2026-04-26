# Amazon — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     Amazon Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ Amazon DynamoDB      │ Product catalog, sessions, shopping cart │
│ Amazon Aurora MySQL  │ Order management, transactional data     │
│ Amazon Aurora PG     │ Financial, analytics (newer systems)     │
│ Amazon ElastiCache   │ Redis — session, cache, rate limiting    │
│ Amazon RDS MySQL     │ Legacy systems, seller data              │
│ Amazon S3            │ Product images, static assets           │
│ Amazon Elasticsearch │ Product search (OpenSearch)              │
│ Amazon Redshift      │ Analytics data warehouse                 │
│ Amazon Kinesis       │ Event streaming (Kafka alternative)      │
│ Amazon Neptune       │ Product recommendations (graph)          │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Niyə DynamoDB?

```
Amazon-ın DynamoDB-ni yaratma tarixi:

2004: "Dynamo" paper (Amazon internal)
  Problem: Peak traffic (Black Friday, Prime Day)
  RDBMS → availability problemi
  
  "The Amazon Dynamo paper changed the industry"
  - Werner Vogels (Amazon CTO)

DynamoDB (2012 public launch):
  Amazon-ın internal Dynamo-nu cloud-a çıxardı
  
İstifadə sahəsi:
  ✓ Shopping cart (çox yazma, consistent read lazım)
  ✓ Product catalog (billions of items, key-value access)
  ✓ Session management
  ✓ Order status tracking
  ✓ Inventory (stock) snapshot

Üstünlüklər:
  ✓ Single-digit millisecond latency at any scale
  ✓ Fully managed (no admin overhead)
  ✓ Auto-scaling
  ✓ Multi-AZ by default
  ✓ Global tables (multi-region)
```

---

## DynamoDB Table Design

```
Amazon DynamoDB Access Pattern-first design:

Shopping Cart:
  PK: user_id
  SK: product_id
  Attributes: quantity, price_snapshot, added_at
  
  CRUD: GetItem, PutItem, DeleteItem, Query(PK=user_id)

Table: ShoppingCart
┌───────────────┬───────────────┬──────────────────────────────────┐
│ PK (user_id)  │ SK (item_key) │ Attributes                       │
├───────────────┼───────────────┼──────────────────────────────────┤
│ user:12345    │ cart#meta     │ {created_at, item_count}         │
│ user:12345    │ item#A1B2C3   │ {qty:2, price:99.99, name:...}   │
│ user:12345    │ item#D4E5F6   │ {qty:1, price:29.99, name:...}   │
└───────────────┴───────────────┴──────────────────────────────────┘

Product Catalog (Single Table Design):
┌──────────────────┬──────────────────┬──────────────────────────────┐
│ PK               │ SK               │ Attributes                   │
├──────────────────┼──────────────────┼──────────────────────────────┤
│ PROD#B07XJ8C8F5  │ DETAILS          │ {name, description, brand}   │
│ PROD#B07XJ8C8F5  │ PRICE#USD        │ {amount:29.99, currency:USD} │
│ PROD#B07XJ8C8F5  │ INV#US-EAST      │ {quantity:500, reserved:10}  │
│ PROD#B07XJ8C8F5  │ IMAGE#1          │ {url, width, height}         │
│ CAT#Electronics  │ PROD#B07XJ8C8F5  │ {sort_rank:0.95}             │
└──────────────────┴──────────────────┴──────────────────────────────┘

Queries:
  Get product: GetItem(PK=PROD#xxx, SK=DETAILS)
  Get all product data: Query(PK=PROD#xxx)
  Category products: Query(PK=CAT#Electronics) with SK begins_with PROD#
```

---

## Aurora MySQL: Order Management

```sql
-- Amazon order system (simplified)

CREATE TABLE orders (
    order_id         VARCHAR(36) PRIMARY KEY,  -- 'D01-1234567-1234567'
    customer_id      BIGINT UNSIGNED NOT NULL,
    status           VARCHAR(30) NOT NULL,
    -- pending_payment, processing, shipped, delivered, cancelled, returned
    
    subtotal         DECIMAL(10,2) NOT NULL,
    shipping_amount  DECIMAL(8,2) DEFAULT 0,
    tax_amount       DECIMAL(8,2) DEFAULT 0,
    total_amount     DECIMAL(10,2) NOT NULL,
    currency         CHAR(3) DEFAULT 'USD',
    
    shipping_address JSON NOT NULL,    -- snapshot
    billing_address  JSON,
    
    payment_method   VARCHAR(30),
    payment_ref      VARCHAR(100),
    
    placed_at        DATETIME NOT NULL,
    estimated_delivery DATE,
    delivered_at     DATETIME,
    
    INDEX idx_customer (customer_id, placed_at DESC),
    INDEX idx_status   (status, placed_at DESC)
);

CREATE TABLE order_items (
    id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id         VARCHAR(36) NOT NULL,
    asin             VARCHAR(10) NOT NULL,   -- Amazon ASIN (B07XJ8C8F5)
    seller_id        BIGINT UNSIGNED,
    
    -- Snapshot
    item_name        VARCHAR(500) NOT NULL,
    quantity         SMALLINT UNSIGNED NOT NULL,
    unit_price       DECIMAL(10,2) NOT NULL,
    total_price      DECIMAL(10,2) NOT NULL,
    
    -- Fulfillment
    fulfillment_type VARCHAR(20),  -- 'FBA', 'FBM', 'Amazon'
    warehouse_id     VARCHAR(20),
    tracking_number  VARCHAR(100),
    
    shipped_at       DATETIME,
    delivered_at     DATETIME,
    
    INDEX idx_order (order_id),
    INDEX idx_asin  (asin)
);

-- Returns & Refunds
CREATE TABLE returns (
    return_id     VARCHAR(36) PRIMARY KEY,
    order_id      VARCHAR(36) NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    reason        VARCHAR(50) NOT NULL,
    condition     VARCHAR(20),  -- 'damaged', 'not_as_described', 'changed_mind'
    status        VARCHAR(20) DEFAULT 'requested',
    refund_amount DECIMAL(10,2),
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## Amazon Product Search (OpenSearch)

```json
{
  "index": "products",
  "mappings": {
    "properties": {
      "asin":          {"type": "keyword"},
      "title":         {"type": "text", "fields": {"raw": {"type": "keyword"}}},
      "description":   {"type": "text"},
      "brand":         {"type": "keyword"},
      "category_tree": {"type": "keyword"},
      "price":         {"type": "scaled_float", "scaling_factor": 100},
      "rating":        {"type": "half_float"},
      "review_count":  {"type": "integer"},
      "is_prime":      {"type": "boolean"},
      "is_available":  {"type": "boolean"},
      "attributes":    {"type": "flattened"},
      "bullets":       {"type": "text"},
      "search_terms":  {"type": "text", "analyzer": "english"},
      "sales_rank":    {"type": "integer"}
    }
  }
}
```

---

## Amazon Redshift: Analytics

```sql
-- Amazon-ın business analytics warehouse
-- Column-oriented storage → analytics sürətli

-- Fact table
CREATE TABLE fact_orders (
    order_date_key  INT NOT NULL,
    customer_key    INT NOT NULL,
    product_key     INT NOT NULL,
    seller_key      INT NOT NULL,
    category_key    INT NOT NULL,
    warehouse_key   INT NOT NULL,
    order_id        VARCHAR(36),
    quantity        SMALLINT,
    unit_price      DECIMAL(10,2),
    total_amount    DECIMAL(10,2),
    shipping_days   SMALLINT
) DISTKEY(order_date_key)
  SORTKEY(order_date_key, category_key);

-- Analytics queries
-- "Son 30 gündə ən çox satılan məhsullar"
SELECT p.product_name, SUM(fo.quantity) AS total_sold
FROM fact_orders fo
JOIN dim_products p ON p.product_key = fo.product_key
JOIN dim_dates d ON d.date_key = fo.order_date_key
WHERE d.full_date >= CURRENT_DATE - 30
GROUP BY p.product_name
ORDER BY total_sold DESC
LIMIT 20;
```

---

## Amazon-ın Microservice Əmri (2002)

```
Jeff Bezos Mandate (2002):
  "All teams will henceforth expose their data and functionality
   through service interfaces.
   
   Teams must communicate with each other through these interfaces.
   There will be no other form of interprocess communication allowed.
   No direct linking. No direct reads of another team's data store.
   
   Anyone who doesn't do this will be fired."

Bu directive → Amazon-ın microservice arxitekturasını yaratdı
Bu directive → AWS-in yaranmasına (2006) gətirib çıxardı
"We had to build it for ourselves, so we sold it to others"

DB Implication:
  Hər servis öz DB-sinə malikdir
  Başqa servisin DB-sinə birbaşa access yoxdur
  API vasitəsilə kommunikasiya
  → Polyglot persistence (hər servis öz DB seçir)
```

---

## Scale Faktları

```
Numbers (Amazon 2023):
  500M+ product listings
  300M+ active customers
  Prime Day 2022: 300M+ items sold
  
  DynamoDB:
  Trillions of API calls per year
  10 trillion+ requests/day at peak
  Single-digit ms latency
  
  AWS Infrastructure:
  32 geographic regions
  102 availability zones
  
  Black Friday/Cyber Monday:
  AWS scales up to handle the load
  "Amazon is its own best customer"

Fulfillment Centers:
  Robots + AI → warehouse optimization
  PostgreSQL: warehouse inventory management
  Kinesis: real-time picking optimization
```

---

## Dərslər

```
Amazon-dan öyrəniləcəklər:

1. "Two-pizza team" rule:
   Kiçik teams → independent → own DB
   Conway's Law: system = team structure

2. API-first mandate:
   Heç bir direct DB access
   Service boundary = data boundary

3. DynamoDB single-table design:
   Access pattern-first modeling
   JOIN yoxdur → embed ya denormalize
   
4. Eventual consistency trade-off:
   Shopping cart: eventually consistent OK
   Payment: strongly consistent (Aurora)
   
5. Aurora vs RDS:
   Aurora: 5x MySQL performance
   Compute + Storage ayrı
   Storage: 6-way replication across 3 AZs

6. "Database per service":
   Hər microservice öz DB-ni seçir
   Order Service: Aurora MySQL
   Catalog Service: DynamoDB
   Search: OpenSearch
```
