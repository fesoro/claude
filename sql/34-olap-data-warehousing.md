# OLAP, Data Warehousing & Columnar Databases

## OLTP vs OLAP

Database-ler iki ferqli workload ucun dizayn olunur:

| Xususiyyet | OLTP | OLAP |
|------------|------|------|
| **Meqsed** | Emeliyyat islemek | Analiz / Hesabat |
| **Sorgu tipi** | INSERT, UPDATE, DELETE | SELECT (aggregation) |
| **Data volume** | GB-ler | TB-PB |
| **Sorgu nece?** | Tek row: `WHERE id = 5` | Milyon row: `SUM(), AVG(), GROUP BY` |
| **Users** | Minlerle (app users) | Onlarla (analist, BI) |
| **Latency** | < 10ms | Saniye - deqiqe |
| **Normalization** | 3NF (normalize) | Star/Snowflake (denormalize) |
| **Misallar** | MySQL, PostgreSQL | ClickHouse, BigQuery, Redshift |

### Misal: E-Commerce

```sql
-- OLTP sorgusu (suretli, tek row)
SELECT * FROM orders WHERE id = 12345;
INSERT INTO orders (...) VALUES (...);

-- OLAP sorgusu (yavas OLTP-de, suretli OLAP-de)
SELECT
    DATE_TRUNC('month', created_at) AS month,
    category,
    COUNT(*) AS order_count,
    SUM(total) AS revenue,
    AVG(total) AS avg_order_value,
    COUNT(DISTINCT user_id) AS unique_customers
FROM orders
JOIN order_items ON orders.id = order_items.order_id
JOIN products ON order_items.product_id = products.id
WHERE created_at >= '2024-01-01'
GROUP BY month, category
ORDER BY month, revenue DESC;
-- Bu sorgu OLTP-de 50M row scan eder = yavas
-- OLAP-de columnar storage ile = suretli
```

## Row-Based vs Column-Based Storage

### Row-Based (MySQL, PostgreSQL)

```
Disk-de saxlanma:
Row 1: [id=1, name="iPhone", price=999, stock=50, category="electronics"]
Row 2: [id=2, name="MacBook", price=2499, stock=30, category="electronics"]
Row 3: [id=3, name="AirPods", price=249, stock=100, category="accessories"]

SELECT * FROM products WHERE id = 1;
→ 1 row oxu, butun column-lar gelir ✅ Suretli

SELECT AVG(price) FROM products;
→ Butun row-lari oxu, yalniz price lazimdir ❌ Yavas (lazimsiz data oxunur)
```

### Column-Based (ClickHouse, BigQuery)

```
Disk-de saxlanma:
id column:       [1, 2, 3]
name column:     ["iPhone", "MacBook", "AirPods"]
price column:    [999, 2499, 249]
stock column:    [50, 30, 100]
category column: ["electronics", "electronics", "accessories"]

SELECT * FROM products WHERE id = 1;
→ Her column-dan 1 deyer oxu, birlestir ❌ Nisbeten yavas

SELECT AVG(price) FROM products;
→ Yalniz price column oxu ✅ Cox suretli (minimal I/O)
→ SIMD/vectorized processing mumkun
→ Compression cox effektiv (eyni tip data yan-yana)
```

## Data Warehouse Arxitekturasi

```
Source Systems (OLTP):
  MySQL ──────┐
  PostgreSQL ──┤
  MongoDB ─────┤ ETL / ELT ──→ Data Warehouse ──→ BI Tools
  API-lar ─────┤                (ClickHouse,       (Metabase,
  CSV/Excel ───┘                 BigQuery)           Grafana,
                                                     Tableau)

ETL: Extract → Transform → Load (evvelce transform)
ELT: Extract → Load → Transform (evvelce yukle, sonra transform - modern)
```

### Star Schema

Data warehouse-da en cox istifade olunan dizayn.

```sql
-- Fact Table (emeliyyat datalari, boyukdur)
CREATE TABLE fact_sales (
    sale_id BIGINT,
    date_key INT,          -- FK to dim_date
    product_key INT,       -- FK to dim_product
    customer_key INT,      -- FK to dim_customer
    store_key INT,         -- FK to dim_store
    quantity INT,
    unit_price DECIMAL(10,2),
    total_amount DECIMAL(12,2),
    discount DECIMAL(10,2)
);

-- Dimension Tables (descriptive, kicikdir)
CREATE TABLE dim_date (
    date_key INT PRIMARY KEY,     -- 20240115
    full_date DATE,
    day_of_week VARCHAR(10),
    month VARCHAR(10),
    quarter INT,
    year INT,
    is_weekend BOOLEAN,
    is_holiday BOOLEAN
);

CREATE TABLE dim_product (
    product_key INT PRIMARY KEY,
    product_name VARCHAR(255),
    category VARCHAR(100),
    subcategory VARCHAR(100),
    brand VARCHAR(100)
);

CREATE TABLE dim_customer (
    customer_key INT PRIMARY KEY,
    customer_name VARCHAR(255),
    city VARCHAR(100),
    country VARCHAR(100),
    segment VARCHAR(50)       -- 'premium', 'regular'
);

-- Analitik sorgu (Star Schema ile)
SELECT
    d.year, d.quarter,
    p.category, p.brand,
    c.country,
    SUM(f.total_amount) AS revenue,
    COUNT(DISTINCT f.customer_key) AS customers,
    AVG(f.total_amount) AS avg_order
FROM fact_sales f
JOIN dim_date d ON f.date_key = d.date_key
JOIN dim_product p ON f.product_key = p.product_key
JOIN dim_customer c ON f.customer_key = c.customer_key
WHERE d.year = 2024
GROUP BY d.year, d.quarter, p.category, p.brand, c.country
ORDER BY revenue DESC;
```

---

## ClickHouse

Aciq-menbe, column-oriented OLAP database. Yandex terefinden yaradilib. **Cox suretli** aggregation sorguları ucun.

### Xususiyyetler

```
✅ Columnar storage (analitik sorgular ucun ideal)
✅ Vectorized query execution (SIMD)
✅ Real-time data ingestion (100K+ rows/saniye)
✅ SQL support (MySQL wire protocol)
✅ Compression (LZ4, ZSTD) - 10x+ disk qenayeti
✅ Distributed queries (cluster)
✅ Materialized views
❌ UPDATE/DELETE yavas (OLTP ucun deyil!)
❌ Transaction yoxdur
❌ JOIN limitli (boyuk-boyuk JOIN yavas)
```

### ClickHouse Table Yaratma

```sql
-- MergeTree engine (esas engine)
CREATE TABLE events (
    event_date Date,
    event_time DateTime,
    user_id UInt64,
    event_type String,
    page_url String,
    country LowCardinality(String),   -- Enum-like optimization
    device LowCardinality(String),
    duration UInt32,
    revenue Decimal64(2)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(event_date)    -- Ayliq partition
ORDER BY (event_type, user_id, event_time)  -- Primary key (siralama)
TTL event_date + INTERVAL 1 YEAR;     -- 1 ilden kohne data avtomatik silinir

-- Insert (bulk, suretli)
INSERT INTO events VALUES
    ('2024-01-15', '2024-01-15 10:30:00', 123, 'page_view', '/products', 'AZ', 'mobile', 5, 0),
    ('2024-01-15', '2024-01-15 10:31:00', 456, 'purchase', '/checkout', 'TR', 'desktop', 120, 99.99);
```

### ClickHouse Sorgulamalar

```sql
-- Gunluk unique visitor ve page view
SELECT
    event_date,
    uniqExact(user_id) AS unique_visitors,
    count() AS total_page_views,
    countIf(event_type = 'purchase') AS purchases,
    sumIf(revenue, event_type = 'purchase') AS total_revenue,
    purchases / unique_visitors AS conversion_rate
FROM events
WHERE event_date >= '2024-01-01' AND event_date <= '2024-01-31'
GROUP BY event_date
ORDER BY event_date;

-- Funnel analizi
SELECT
    countIf(event_type = 'page_view') AS step1_views,
    countIf(event_type = 'add_to_cart') AS step2_cart,
    countIf(event_type = 'checkout') AS step3_checkout,
    countIf(event_type = 'purchase') AS step4_purchase,
    round(step2_cart / step1_views * 100, 2) AS view_to_cart_pct,
    round(step4_purchase / step1_views * 100, 2) AS view_to_purchase_pct
FROM events
WHERE event_date = '2024-01-15';

-- Top pages by country
SELECT
    country,
    page_url,
    count() AS views,
    uniqExact(user_id) AS unique_users,
    avg(duration) AS avg_duration
FROM events
WHERE event_type = 'page_view'
  AND event_date >= today() - 7
GROUP BY country, page_url
ORDER BY views DESC
LIMIT 20;
```

### Materialized View (Real-Time Aggregation)

```sql
-- Saatlik statistikalar avtomatik hesablanir
CREATE MATERIALIZED VIEW hourly_stats
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (hour, event_type, country)
AS SELECT
    toStartOfHour(event_time) AS hour,
    event_type,
    country,
    count() AS event_count,
    uniqExact(user_id) AS unique_users,
    sum(revenue) AS total_revenue
FROM events
GROUP BY hour, event_type, country;

-- Sorgu (precomputed, cox suretli)
SELECT * FROM hourly_stats
WHERE hour >= now() - INTERVAL 24 HOUR
ORDER BY hour DESC;
```

### PHP ile ClickHouse

```php
// composer require smi2/phpclickhouse

$db = new ClickHouseDB\Client([
    'host' => 'localhost',
    'port' => 8123,
    'username' => 'default',
    'password' => '',
]);
$db->database('analytics');

// Bulk insert (suretli)
$db->insert('events', [
    ['2024-01-15', '2024-01-15 10:30:00', 123, 'page_view', '/products', 'AZ', 'mobile', 5, 0],
    ['2024-01-15', '2024-01-15 10:31:00', 456, 'purchase', '/checkout', 'TR', 'desktop', 120, 99.99],
], ['event_date', 'event_time', 'user_id', 'event_type', 'page_url', 'country', 'device', 'duration', 'revenue']);

// Query
$result = $db->select("
    SELECT event_date, count() AS views, uniqExact(user_id) AS unique_users
    FROM events
    WHERE event_date >= '2024-01-01'
    GROUP BY event_date
    ORDER BY event_date
");

foreach ($result->rows() as $row) {
    echo "{$row['event_date']}: {$row['views']} views, {$row['unique_users']} users\n";
}

// Laravel ile (adi PDO connection - MySQL protocol)
// config/database.php
'clickhouse' => [
    'driver' => 'mysql',  // MySQL wire protocol
    'host' => 'localhost',
    'port' => 9004,       // MySQL port
    'database' => 'analytics',
    'username' => 'default',
    'password' => '',
],

// Istifade
$stats = DB::connection('clickhouse')->select("
    SELECT toYYYYMM(event_date) AS month, SUM(revenue) AS revenue
    FROM events
    GROUP BY month
    ORDER BY month
");
```

---

## Google BigQuery / AWS Redshift

Fully managed cloud data warehouse-lar.

### BigQuery

```sql
-- BigQuery SQL (standard SQL)
-- Pricing: $5 per TB scanned (on-demand)

SELECT
    FORMAT_DATE('%Y-%m', order_date) AS month,
    product_category,
    COUNT(*) AS orders,
    SUM(total) AS revenue,
    APPROX_COUNT_DISTINCT(user_id) AS unique_customers
FROM `project.dataset.orders`
WHERE order_date >= '2024-01-01'
GROUP BY month, product_category
ORDER BY month, revenue DESC;

-- Partitioned table (scan maliyyetini azaltir)
CREATE TABLE `project.dataset.events`
(
    event_time TIMESTAMP,
    user_id INT64,
    event_type STRING,
    page_url STRING,
    revenue NUMERIC
)
PARTITION BY DATE(event_time)   -- Gune gore partition
CLUSTER BY event_type, user_id; -- Cluster by (sort order)
```

## OLAP vs Diger Database-ler

| Xususiyyet | PostgreSQL | ClickHouse | BigQuery | TimescaleDB |
|------------|-----------|------------|----------|-------------|
| **Tip** | OLTP | OLAP | OLAP | OLTP + TS |
| **Storage** | Row | Column | Column | Row (hybrid) |
| **INSERT** | Suretli | Cox suretli | Batch | Suretli |
| **UPDATE** | Suretli | Cox yavas | Yavas | Suretli |
| **Aggregation** | Yavas (boyuk data) | Cox suretli | Cox suretli | Orta |
| **Transaction** | ACID | Xeyr | Xeyr | ACID |
| **JOIN** | Suretli | Limitli | Suretli | Suretli |
| **Hosting** | Self/Cloud | Self/Cloud | GCP only | Self/Cloud |
| **Cost** | Server cost | Server cost | Per-query | Server cost |

## Ne Vaxt Ne Istifade Etmeli?

```
Emeliyyat datalari (CRUD, transactions)?
└── PostgreSQL / MySQL (OLTP)

Server/app metrikleri (time-series)?
└── TimescaleDB / InfluxDB

Real-time analytics dashboard?
└── ClickHouse (self-host) veya BigQuery (cloud)

Boyuk data analitika, BI hesabatlar?
└── BigQuery (GCP) / Redshift (AWS) / Snowflake

Hamisi birlikde?
└── PostgreSQL (OLTP) → CDC → ClickHouse (OLAP) → Grafana/Metabase
```

## Real-World Data Pipeline

```
Application (Laravel)
    │
    ├── PostgreSQL (OLTP) ← User, Order, Payment data
    │       │
    │       └── CDC (Debezium) ──→ ClickHouse (OLAP)
    │                                    │
    │                              Grafana / Metabase
    │                              (Dashboard, Reports)
    │
    ├── TimescaleDB ← Server metrics, app performance
    │       │
    │       └── Grafana (monitoring)
    │
    └── Redis ← Cache, sessions, real-time counters
```

## Interview Suallari

1. **OLTP ile OLAP ferqi?**
   - OLTP: Transactional (INSERT/UPDATE, tek row, suretli). OLAP: Analytical (SELECT, milyon row aggregation, complex query).

2. **Columnar storage niye analitik sorgular ucun suretlidir?**
   - Yalniz lazim olan column oxunur (minimal I/O). Eyni tip data yan-yana oldugu ucun compression daha effektiv. SIMD/vectorized processing mumkun.

3. **Star Schema nedir?**
   - Merkezi fact table (emeliyyat datalari) + dimension table-lar (descriptive). Denormalize - JOIN sadə, analitik sorgular ucun optimize.

4. **ClickHouse niye UPDATE/DELETE-de yavasdir?**
   - Columnar storage append-only dizayn olunub. UPDATE bir row deyismek ucun butun column file-lari yeniden yazir. OLTP workload ucun deyil.

5. **ETL ve ELT ferqi?**
   - ETL: Source → Transform → Load (transform evvel). ELT: Source → Load → Transform (cloud DW-nin computing gucunden istifade). Modern yanasmada ELT tercih olunur.

6. **BigQuery nece qiymetlendirilir?**
   - On-demand: $5/TB scanned. Partition + clustering ile scan olunacaq datani azaldiraraq xerce qenayat edersiniz.
