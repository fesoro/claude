# JSON, Full-Text Search & Advanced Features

> **Seviyye:** Intermediate ⭐⭐

## JSON Columns

### MySQL JSON (8.0+)

```sql
CREATE TABLE products (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    attributes JSON,
    settings JSON
);

INSERT INTO products (name, attributes) VALUES (
    'Laptop',
    '{"brand": "Dell", "specs": {"ram": 16, "storage": 512}, "colors": ["black", "silver"]}'
);

-- JSON path ile sorgu
SELECT name, 
    attributes->>'$.brand' AS brand,
    attributes->'$.specs.ram' AS ram,
    JSON_EXTRACT(attributes, '$.colors[0]') AS first_color
FROM products;

-- WHERE
SELECT * FROM products WHERE attributes->>'$.brand' = 'Dell';
SELECT * FROM products WHERE JSON_EXTRACT(attributes, '$.specs.ram') >= 16;
SELECT * FROM products WHERE JSON_CONTAINS(attributes->'$.colors', '"black"');

-- JSON modify
UPDATE products SET attributes = JSON_SET(attributes, '$.specs.ram', 32) WHERE id = 1;
UPDATE products SET attributes = JSON_REMOVE(attributes, '$.colors[1]') WHERE id = 1;
UPDATE products SET attributes = JSON_ARRAY_APPEND(attributes, '$.colors', 'white') WHERE id = 1;

-- Index (generated column lazimdir)
ALTER TABLE products ADD COLUMN brand VARCHAR(100) 
    GENERATED ALWAYS AS (attributes->>'$.brand') STORED;
CREATE INDEX idx_brand ON products (brand);
```

### PostgreSQL JSONB

```sql
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    attributes JSONB
);

-- Sorgu (daha guclu operator-lar)
SELECT * FROM products WHERE attributes->>'brand' = 'Dell';
SELECT * FROM products WHERE attributes @> '{"brand": "Dell"}';  -- Contains
SELECT * FROM products WHERE attributes ? 'brand';               -- Key movcuddur?
SELECT * FROM products WHERE attributes ?& ARRAY['brand', 'specs']; -- Butun key-ler var?
SELECT * FROM products WHERE attributes ?| ARRAY['brand', 'model']; -- En az biri var?

-- Nested
SELECT * FROM products WHERE (attributes->'specs'->>'ram')::int >= 16;

-- JSONB modify
UPDATE products SET attributes = attributes || '{"new_field": "value"}';
UPDATE products SET attributes = attributes - 'old_field';
UPDATE products SET attributes = jsonb_set(attributes, '{specs,ram}', '32');

-- GIN Index (butun JSONB ucun!)
CREATE INDEX idx_attrs ON products USING GIN (attributes);
-- Indi @>, ?, ?&, ?| operator-lari index istifade edir

-- Partial GIN index
CREATE INDEX idx_brand ON products USING GIN ((attributes->'brand'));

-- BTREE index (specific path ucun)
CREATE INDEX idx_brand_btree ON products ((attributes->>'brand'));
```

### Laravel JSON

```php
// Migration
$table->json('attributes')->nullable();

// Cast
class Product extends Model
{
    protected $casts = [
        'attributes' => 'array', // ve ya 'json', 'collection'
    ];
}

// Istifade
$product->attributes = ['brand' => 'Dell', 'specs' => ['ram' => 16]];
$product->save();

// Query (-> operator)
Product::where('attributes->brand', 'Dell')->get();
Product::where('attributes->specs->ram', '>=', 16)->get();

// PostgreSQL JSONB operator-lari
Product::whereRaw("attributes @> ?", [json_encode(['brand' => 'Dell'])])->get();
```

---

## Full-Text Search

### MySQL FULLTEXT

```sql
CREATE TABLE articles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    body TEXT,
    FULLTEXT INDEX ft_articles (title, body)
);

-- Natural Language Mode (default)
SELECT *, MATCH(title, body) AGAINST('database optimization') AS relevance
FROM articles
WHERE MATCH(title, body) AGAINST('database optimization')
ORDER BY relevance DESC;

-- Boolean Mode (operator-lar ile)
SELECT * FROM articles 
WHERE MATCH(title, body) AGAINST('+database -mysql +optimization' IN BOOLEAN MODE);
-- + = olmalidir, - = olmamalidir, * = prefix match

-- Expansion Mode
SELECT * FROM articles 
WHERE MATCH(title, body) AGAINST('database' WITH QUERY EXPANSION);
-- Ilk neticelerdeki sozleri istifade ederek genislendilmis axtaris
```

### PostgreSQL Full-Text Search

```sql
-- tsvector + tsquery
SELECT * FROM articles
WHERE to_tsvector('english', title || ' ' || body) @@ to_tsquery('english', 'database & optimization');

-- Relevance ranking
SELECT title, 
    ts_rank(to_tsvector('english', title || ' ' || body), 
            to_tsquery('english', 'database & optimization')) AS rank
FROM articles
WHERE to_tsvector('english', title || ' ' || body) @@ to_tsquery('english', 'database & optimization')
ORDER BY rank DESC;

-- GIN index (suretli full-text)
CREATE INDEX idx_ft ON articles USING GIN (to_tsvector('english', title || ' ' || body));

-- Stored tsvector column (daha suretli)
ALTER TABLE articles ADD COLUMN search_vector tsvector;
UPDATE articles SET search_vector = to_tsvector('english', title || ' ' || body);
CREATE INDEX idx_search ON articles USING GIN (search_vector);

-- Trigger ile avtomatik yenileme
CREATE TRIGGER update_search_vector
BEFORE INSERT OR UPDATE ON articles
FOR EACH ROW EXECUTE FUNCTION
tsvector_update_trigger(search_vector, 'pg_catalog.english', title, body);
```

### Laravel Scout (Application-level Full-Text)

```php
// Algolia, Meilisearch, ve ya database driver
// composer require laravel/scout

class Article extends Model
{
    use Searchable;
    
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}

// Axtaris
Article::search('database optimization')->get();
Article::search('database')->where('status', 'published')->paginate(20);
```

---

## Window Functions

SQL-de qruplasdirmadan aggregate hesablama imkani. MySQL 8.0+ ve PostgreSQL-de var.

```sql
-- ROW_NUMBER: Siralama nomresi
SELECT 
    name, department, salary,
    ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS rank
FROM employees;
-- Her departmentde en yuksek maas 1-ci nomre alir

-- RANK: Eyni deyerlere eyni nomre (bosliq ile)
-- DENSE_RANK: Eyni deyerlere eyni nomre (bosliq olmadan)

-- Running total
SELECT 
    date, amount,
    SUM(amount) OVER (ORDER BY date) AS running_total
FROM transactions;

-- Moving average (son 7 gun)
SELECT 
    date, amount,
    AVG(amount) OVER (ORDER BY date ROWS BETWEEN 6 PRECEDING AND CURRENT ROW) AS moving_avg
FROM daily_sales;

-- Evvelki/novbeti deyer
SELECT 
    date, amount,
    LAG(amount, 1) OVER (ORDER BY date) AS previous_amount,
    LEAD(amount, 1) OVER (ORDER BY date) AS next_amount,
    amount - LAG(amount, 1) OVER (ORDER BY date) AS diff
FROM daily_sales;
```

### Laravel-de Window Functions

```php
// Raw expression ile
$results = DB::table('employees')
    ->select('name', 'department', 'salary')
    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) as rank')
    ->get();

// Yalniz her departmentden top 3
$topEmployees = DB::table(
    DB::raw('(SELECT *, ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) as rn FROM employees) sub')
)->where('rn', '<=', 3)->get();
```

---

## Common Table Expressions (CTE)

```sql
-- Sade CTE
WITH recent_orders AS (
    SELECT * FROM orders WHERE created_at > NOW() - INTERVAL 30 DAY
)
SELECT user_id, COUNT(*) AS order_count, SUM(total_amount) AS total
FROM recent_orders
GROUP BY user_id;

-- Multiple CTEs
WITH 
active_users AS (
    SELECT id, name FROM users WHERE last_login > NOW() - INTERVAL 7 DAY
),
user_orders AS (
    SELECT user_id, COUNT(*) AS cnt FROM orders GROUP BY user_id
)
SELECT au.name, COALESCE(uo.cnt, 0) AS order_count
FROM active_users au
LEFT JOIN user_orders uo ON uo.user_id = au.id;

-- Recursive CTE (agac strukturu)
WITH RECURSIVE category_tree AS (
    -- Base case
    SELECT id, name, parent_id, 1 AS depth, name AS path
    FROM categories WHERE parent_id IS NULL
    
    UNION ALL
    
    -- Recursive case
    SELECT c.id, c.name, c.parent_id, ct.depth + 1, CONCAT(ct.path, ' > ', c.name)
    FROM categories c
    JOIN category_tree ct ON ct.id = c.parent_id
)
SELECT * FROM category_tree ORDER BY path;

-- Netice:
-- Electronics
-- Electronics > Laptops
-- Electronics > Laptops > Gaming Laptops
-- Electronics > Phones
```

---

## Generated/Computed Columns

```sql
-- MySQL
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    quantity INT,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    -- STORED: disk-de saxlanilir, index-lene biler
    -- VIRTUAL: her oxunanda hesablanir, disk tutmur
    created_year INT GENERATED ALWAYS AS (YEAR(created_at)) STORED,
    INDEX idx_year (created_year)
);

-- PostgreSQL (GENERATED ALWAYS AS)
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    quantity INT,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED
);
```

```php
// Laravel Migration
$table->decimal('total_price', 10, 2)->storedAs('quantity * unit_price');
$table->integer('created_year')->storedAs('YEAR(created_at)');
// $table->decimal('total_price', 10, 2)->virtualAs('quantity * unit_price');
```

---

## Lateral Joins (PostgreSQL)

Her row ucun subquery icra etmek.

```sql
-- Her user-in son 3 order-ini al
SELECT u.name, recent_orders.*
FROM users u
CROSS JOIN LATERAL (
    SELECT id, total_amount, created_at
    FROM orders
    WHERE user_id = u.id
    ORDER BY created_at DESC
    LIMIT 3
) recent_orders;
```

MySQL 8.0-da `LATERAL` derived tables:

```sql
SELECT u.name, ro.id, ro.total_amount
FROM users u,
LATERAL (
    SELECT id, total_amount FROM orders WHERE user_id = u.id ORDER BY created_at DESC LIMIT 3
) ro;
```

---

## Interview suallari

**Q: JSON column ne vaxt istifade etmeli, ne vaxt ayri table?**
A: JSON: flexible schema, nadir sorğulanan data, metadata, settings, audit payload. Ayri table: structured data, tez-tez sorğulanan, JOIN/FK lazim olan data. JSON-i "lazy schema" kimi istifadə etmə.

**Q: Full-text search ucun MySQL/PostgreSQL vs Elasticsearch?**
A: Sade full-text ucun DB built-in kifayetdir. Boyuk data (milyonlarla document), complex scoring, faceting, fuzzy matching, real-time indexing lazimdiRsa - Elasticsearch/Meilisearch daha yaxsidir. DB-den baslayib, lazim olduqda migrate et.

**Q: Window functions niye muhimdur?**
A: GROUP BY olmadan aggregate hesablama imkani verir. Running total, ranking, previous/next row, moving average kimi hesablamalari tek query-de edir. Bunlarsiz application-da ve ya bir nece query ile etmek lazim gelirdi.
