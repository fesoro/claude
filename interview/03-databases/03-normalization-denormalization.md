# Normalization vs Denormalization (Middle ⭐⭐)

## İcmal

Normalization — data redundancy-ni azaltmaq üçün tabloları bölmə prosesidir. Denormalization — performance üçün məqsədli olaraq redundancy əlavə etməkdir. Bu sual data modeling bacarığınızı yoxlayır: siz nə vaxt normalize, nə vaxt denormalize edərdiniz? "Full normalize etmək həmişə yaxşıdır" mifi real production sistemlərini yavaşladır. Doğru cavab həmişə use case-ə, read/write ratio-ya, traffic pattern-ə bağlıdır.

## Niyə Vacibdir

Yanlış data modeling bütün layihənin performance-ını məhv edə bilər. İnterviewer bu sualla sizin read vs write trade-off-unu dərk edib-etmədiyinizi, "full normalize etmək həmişə yaxşıdır" mifindən azad olub-olmadığınızı, və real sistemlərdə (reporting, analytics, high-traffic) hansı kompromisləri etdiyinizi görür. Senior səviyyədə bu sualın cavabı mütləq konkret real-world nümunə ilə dəstəklənməlidir — CQRS, materialized view, ya da Elasticsearch sinxronizasiyası haqqında danışmaq gözlənilir.

## Əsas Anlayışlar

### Normal Formlar

- **1NF (First Normal Form):** Hər column atomic dəyər saxlayır; tekrar qrup yoxdur. Məsələn `phone_numbers = "555-1234, 555-5678"` 1NF pozur — ayrı table lazımdır.
- **2NF (Second Normal Form):** 1NF + hər non-key column tam primary key-dən asılıdır. Partial dependency yoxdur. `(order_id, product_id) → product_name` — product_name yalnız product_id-dən asılıdır, bu partial dependency-dir, 2NF pozur.
- **3NF (Third Normal Form):** 2NF + transitive dependency yoxdur. Non-key column başqa non-key column-dan asılı deyil. `zip_code → city` — city, zip_code-dan asılıdır, lakin zip_code primary key deyil — 3NF pozur, ayrı table lazımdır.
- **BCNF (Boyce-Codd Normal Form):** 3NF-in daha güclü versiyası — hər functional dependency determinant super-key-dir. Praktikada 3NF çox halda kifayət edir.
- **4NF / 5NF:** Multi-valued dependency-ləri aradan qaldırır — praktikada nadir istifadə.

### Anomaliyalar

- **Update Anomaly:** Normalize olmamış tabloda eyni datanı birdən çox yerdə update etmək lazım gəlir. `customer_email` orders-da 1000 sətirdə varsa, hər email dəyişikliyində hamısı update olunmalıdır — biri unudulsa, inconsistency baş verir.
- **Insert Anomaly:** Əsas entity olmadan əlaqəli data insert etmək mümkün olmur. Orders-da `product_name` saxlanılırsa, məhsul sifariş edilmədikcə datanı daxil edə bilmirsən.
- **Delete Anomaly:** Bir entity-ni sildikdə əlaqəli vacib data da itirilir. Əgər müştərinin son sifarişini silsən, müştərinin özü haqqında bütün data da itə bilər.
- **Data Redundancy:** Eyni datanın birdən çox yerdə olması — disk + memory waste, consistency riski, update overhead.

### Denormalization Texnikaları

- **Calculated columns:** Dəyəri hesablanıb saxlanan column — `total_price = qty * unit_price`. Hər dəfə hesablamaq əvəzinə hazır saxlanır.
- **Summary tables:** Aggregate-ləri ayrı tabloda saxlamaq — `daily_sales`, `monthly_revenue`. Reporting sistemlərinin əsasıdır.
- **Materialized View:** Denormalization-un "managed" variantı — query nəticəsini fiziki olaraq saxlayır, `REFRESH` ilə yenilənir. PostgreSQL-in `CONCURRENTLY` variant-ı lock olmadan refresh edir.
- **Embedded documents (NoSQL):** MongoDB-də denormalization-un natural forması — post içinə comments-i gömmək, `author_name`-i hər yerdə saxlamaq.
- **CQRS (Command Query Responsibility Segregation):** Write modeli normalize, read modeli denormalize. Separate read store — Elasticsearch, Redis, ya da denormalized summary table.
- **JOIN overhead:** Normalization JOIN-ları artırır — yüksək load-da performance aşağı düşə bilər. 5 table JOIN-ı hər sorğuda baha başa gəlir.
- **Price snapshot antipattern:** `order_items`-da `unit_price` saxlamamaq səhvdir — məhsul qiyməti dəyişsə tarixi sifariş qiyməti də "dəyişər". Snapshot — denormalization-un vacib olduğu hal.

### OLTP vs OLAP Fərqi

- **OLTP (Online Transaction Processing):** Sürətli write/read, az data, normalization üstünlüklü. E-commerce, banking.
- **OLAP (Online Analytical Processing):** Böyük data, complex aggregate query-lər, denormalization üstünlüklü. Star schema, snowflake schema. Data warehouse.
- **Star Schema:** Central fact table (sifarişlər) + dimension tables (müştəri, məhsul, vaxt) — denormalized, analytics üçün.

### Read vs Write Trade-off

| | Normalize | Denormalize |
|---|---|---|
| Read performance | JOIN lazım | Sürətli (hazır data) |
| Write performance | Yalnız bir yerdə | Birdən çox yerdə update |
| Storage | Az yer | Çox yer |
| Consistency | Güclü | Staleness riski |
| Use case | OLTP | OLAP, reporting, high-traffic read |

## Praktik Baxış

### Interview-da Yanaşma

1. **Birinci soruşun:** "Read/write ratio nədir? Bu table OLTP-dir, OLAP-dir, ya ikisi birlikdəmi?"
2. **SQL-u default seç, normalize et** — sonra performance problem olarsa denormalize düşün.
3. **"Normalization həmişə doğrudur"** fikrindən qaçın — "it depends" deyin.
4. **Konkret nümunə verin:** "Sifariş tarixçəsi reportinq üçün denormalized view yaratdıq."
5. **Consistency problemini qeyd edin:** "Denormalize etdikdə iki yer update olmalıdır — bu sync risk yaradır."

### Follow-up Suallar (İnterviewerlər soruşur)

- "3NF-i real nümunə ilə izah edin" — zip_code → city nümunəsi ver.
- "Reporting table-ınızı necə optimize etdiniz?" — Materialized view, refresh strategy.
- "Materialized view nə vaxt istifadə edərsiniz?" — Slow aggregate query, real-time lazım deyil.
- "CQRS-in dezavantajı nədir?" — Eventual consistency, iki data store sync saxlamaq.
- "order_items-da unit_price-ı niyə saxlayırsınız?" — Price change-dən tarixi qorumaq üçün.
- "MongoDB-də denormalization nə vaxt problem olur?" — Document size limit, embedded array-in böyüməsi.
- "Denormalize table-da consistency necə təmin olunur?" — Trigger, event, background job.

### Common Mistakes

- "Normalization həmişə doğrudur" demək — context-i nəzərə almamaq.
- Normal form-ları sadəcə ezberləmək, anomaliyaları izah etməmək.
- Denormalization-un consistency riskini qeyd etməmək.
- `unit_price` snapshot-ı niyə lazım olduğunu bilməmək.
- Materialized view-un stale data riski haqqında danışmamaq.
- CQRS-i yalnız microservices ilə əlaqələndirmək — monolith-də də tətbiq olunur.

### Yaxşı → Əla Cavab

- **Yaxşı:** Fərqləri sadalayır, 1NF-3NF izah edir.
- **Əla:** CQRS pattern-ini real senaryoda izah edir, materialized view praktik kontekstdə göstərir, "price snapshot" antipattern-dən danışır, read/write trade-off-u açıqlar, real production nümunəsi ilə dəstəkləyir.

### Real Production Ssenariləri

- **E-commerce:** `orders` + `order_items` normalized; `order_summaries` materialized view — reporting üçün denormalized.
- **Social media:** Posts normalized; timeline denormalized Redis-də — fan-out-on-write.
- **Analytics:** OLTP PostgreSQL; nightly ETL → Redshift/BigQuery star schema — OLAP sorğuları üçün.
- **Search:** Products normalized PostgreSQL-də; Elasticsearch-də denormalized document — full-text search üçün.
- **Financial reporting:** `transactions` normalized; `daily_balances` summary table — günlük closing balance.

## Nümunələr

### Tipik Interview Sualı

"E-commerce tabelinizə baxanda sifarişlər tablonuzda müştəri adı, email saxlanılıb. Bu problemi izah edin və həll edin."

### Güclü Cavab

Bu klassik normalization problemidir. `orders` tabloda `customer_name` və `customer_email` saxlamaq **update anomaliyasına** səbəb olur: müştəri emailini dəyişdikdə həmin müştərinin bütün sifarişlərini update etmək lazım gəlir. **Delete anomaliyası** da var: müştərini silsək tarix sifariş datası itirilir. **Insert anomaliyası:** Müştəri sifariş verməsə, onu heç daxil edə bilmirsən.

Həll: `customers` tablonu ayır, `orders`-da yalnız `customer_id` (foreign key) saxla — **3NF-ə çatırıq**.

Lakin burada trade-off var: hər sifariş göstərəndə JOIN lazımdır. Əgər bu yüksək-trafik reporting sistemi üçündürsə, mən denormalized `order_summary` materialized view yaradardım — JOIN-siz sürətli oxuma olsun. `REFRESH MATERIALIZED VIEW CONCURRENTLY` ilə lock olmadan yeniləmək mümkündür.

### Kod Nümunəsi

```sql
-- Normalize edilməmiş (problematik) — anomaliyalar var
CREATE TABLE orders_bad (
    order_id       INT PRIMARY KEY,
    customer_name  VARCHAR(100),   -- update anomaly riski!
    customer_email VARCHAR(100),   -- update anomaly riski!
    product_name   VARCHAR(100),   -- update anomaly riski!
    product_price  DECIMAL(10,2),  -- price change-dən qorunmur!
    quantity       INT
);

-- Normalize edilmiş (3NF) — hər entity öz tabloda
CREATE TABLE customers (
    customer_id SERIAL PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(100)  UNIQUE NOT NULL
);

CREATE TABLE products (
    product_id SERIAL PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    price      DECIMAL(10,2) NOT NULL  -- cari qiymət
);

CREATE TABLE orders (
    order_id    SERIAL PRIMARY KEY,
    customer_id INT NOT NULL REFERENCES customers(customer_id),
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE order_items (
    id         SERIAL PRIMARY KEY,
    order_id   INT NOT NULL REFERENCES orders(order_id),
    product_id INT NOT NULL REFERENCES products(product_id),
    quantity   INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL  -- SNAPSHOT! Qiymət dəyişsə tarixi qoruyur
    -- Bu denormalization-dur, lakin "price snapshot" vacibdir
);
```

```sql
-- Denormalized reporting view (CQRS oxuma tərəfi)
CREATE MATERIALIZED VIEW order_summary AS
SELECT
    o.order_id,
    c.name                                      AS customer_name,
    c.email                                     AS customer_email,
    SUM(oi.quantity * oi.unit_price)            AS total_amount,
    COUNT(oi.id)                                AS item_count,
    MAX(p.name)                                 AS first_product,
    o.created_at
FROM orders o
JOIN customers  c  ON c.customer_id  = o.customer_id
JOIN order_items oi ON oi.order_id   = o.order_id
JOIN products   p  ON p.product_id   = oi.product_id
GROUP BY o.order_id, c.name, c.email, o.created_at;

-- Unique index — CONCURRENTLY refresh üçün lazımdır
CREATE UNIQUE INDEX ON order_summary (order_id);

-- Lock olmadan periodik yeniləmə (production-da cronjob)
REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary;

-- Fast read — JOIN yoxdur, index-only scan
SELECT * FROM order_summary
WHERE customer_email = 'user@example.com'
ORDER BY created_at DESC;
```

```sql
-- 2NF pozulması nümunəsi
CREATE TABLE order_products_bad (
    order_id     INT,
    product_id   INT,
    quantity     INT,
    product_name VARCHAR(100),  -- yalnız product_id-dən asılı → partial dependency!
    PRIMARY KEY (order_id, product_id)
);

-- 2NF-ə uyğun: product_name products tabloda olmalıdır
CREATE TABLE order_items_good (
    order_id   INT,
    product_id INT REFERENCES products(product_id),
    quantity   INT,
    PRIMARY KEY (order_id, product_id)
    -- product_name JOIN ilə gəlir, burada yoxdur
);
```

```sql
-- 3NF pozulması nümunəsi
CREATE TABLE users_bad (
    user_id  INT PRIMARY KEY,
    name     VARCHAR(100),
    zip_code VARCHAR(10),
    city     VARCHAR(100),  -- zip_code-dan asılı → transitive dependency!
    state    VARCHAR(50)    -- zip_code-dan asılı!
);

-- 3NF-ə uyğun: zip code ayrı tabloda
CREATE TABLE zip_codes (
    zip_code VARCHAR(10) PRIMARY KEY,
    city     VARCHAR(100),
    state    VARCHAR(50)
);

CREATE TABLE users_good (
    user_id  INT PRIMARY KEY,
    name     VARCHAR(100),
    zip_code VARCHAR(10) REFERENCES zip_codes(zip_code)
);
```

### İkinci Nümunə: Summary Table Pattern

```sql
-- Hər gecə çalışan nightly aggregation
-- Reporting-də canlı SUM(total) əvəzinə hazır data istifadə edilir

CREATE TABLE daily_sales_summary (
    summary_date DATE PRIMARY KEY,
    total_orders INT         NOT NULL DEFAULT 0,
    total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    avg_order_value DECIMAL(10,2),
    new_customers INT        NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP  DEFAULT NOW()
);

-- Nightly job (pg_cron, Laravel schedule)
INSERT INTO daily_sales_summary
    (summary_date, total_orders, total_revenue, avg_order_value)
SELECT
    DATE(created_at)          AS summary_date,
    COUNT(*)                  AS total_orders,
    SUM(total_amount)         AS total_revenue,
    AVG(total_amount)         AS avg_order_value
FROM orders
WHERE DATE(created_at) = CURRENT_DATE - 1
GROUP BY DATE(created_at)
ON CONFLICT (summary_date)
DO UPDATE SET
    total_orders    = EXCLUDED.total_orders,
    total_revenue   = EXCLUDED.total_revenue,
    avg_order_value = EXCLUDED.avg_order_value,
    updated_at      = NOW();

-- Dashboard sorğusu — canlı aggregate yox, hazır data
SELECT * FROM daily_sales_summary
ORDER BY summary_date DESC
LIMIT 30;
```

```python
# Django / CQRS pattern
# Write model — normalized (command side)
class Order(models.Model):
    customer  = models.ForeignKey('Customer', on_delete=models.PROTECT)
    created_at = models.DateTimeField(auto_now_add=True)

class OrderItem(models.Model):
    order      = models.ForeignKey(Order, on_delete=models.CASCADE)
    product    = models.ForeignKey('Product', on_delete=models.PROTECT)
    quantity   = models.PositiveIntegerField()
    unit_price = models.DecimalField(max_digits=10, decimal_places=2)
    # unit_price: snapshot — product.price dəyişsə tarixi qoruyur

# Read model — denormalized (query side)
# Elasticsearch ya da ayrı summary table
class OrderSummaryDocument:
    """
    Search/reporting üçün denormalized document.
    Hər order create/update zamanı sync olunur.
    """
    order_id       : int
    customer_name  : str     # JOIN-siz hazır
    customer_email : str
    total_amount   : float   # Pre-calculated
    item_count     : int
    product_names  : list    # Embedded, JOIN lazım deyil

def sync_order_to_search(order_id: int) -> None:
    """Order dəyişəndə search index-ini yenilə."""
    order = Order.objects.select_related('customer') \
                         .prefetch_related('items__product') \
                         .get(id=order_id)
    
    doc = {
        'order_id':      order.id,
        'customer_name': order.customer.name,
        'customer_email': order.customer.email,
        'total_amount':  sum(i.quantity * i.unit_price for i in order.items.all()),
        'product_names': [i.product.name for i in order.items.all()],
    }
    # Elasticsearch-ə yaz
    es_client.index(index='orders', id=order_id, body=doc)
```

## Praktik Tapşırıqlar

1. University database-i dizayn edin: students, courses, professors, enrollments — 3NF-ə çatın. Bütün anomaliyaları əvvəlcə normalize olunmamış tabloda göstərin.
2. Eyni database-dən reporting üçün denormalized `course_summary` materialized view yaradın, `REFRESH CONCURRENTLY` testi keçirin.
3. `EXPLAIN ANALYZE` ilə normalize vs denormalize query-lərin performance-ını müqayisə edin: 5-table JOIN vs materialized view scan.
4. PostgreSQL-də `REFRESH MATERIALIZED VIEW` zamanı stale data pəncərəsini ölçün, `CONCURRENTLY` fərqini göstərin.
5. E-commerce sisteminizin `order_items`-ında `unit_price` saxlamaq niyə vacibdir — məhsul qiyməti dəyişdikdən sonra tarixi sifariş üçün SQL testlə göstərin.
6. `orders_bad` tablonu yaradın, update anomaliyasını simulasiya edin: müştəri emailini yalnız bir sətirdə dəyişin, inconsistency-i göstərin.
7. Star schema dizayn edin: `fact_orders` mərkəzdə, `dim_customer`, `dim_product`, `dim_date` kənar — analytics sorğusu yazın.
8. Laravel-də CQRS tətbiq edin: `OrderCreated` event-i Elasticsearch-ə sync edən listener yazın.

## Əlaqəli Mövzular

- `01-sql-vs-nosql.md` — NoSQL-da denormalization default yanaşmadır.
- `04-index-types.md` — Denormalize cədvəllərdə index strategiyası.
- `05-query-optimization.md` — JOIN-ların performans testi, EXPLAIN ANALYZE.
- `06-transaction-isolation.md` — Denormalized data-nın consistency-si transaction-la qorunur.
