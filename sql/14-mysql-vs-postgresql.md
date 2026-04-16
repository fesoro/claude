# MySQL vs PostgreSQL

## Umumi Muqayise

| Xususiyyet | MySQL (InnoDB) | PostgreSQL |
|------------|---------------|------------|
| Default Isolation | REPEATABLE READ | READ COMMITTED |
| MVCC | Undo log ile | Tuple versioning |
| JSON dəstəyi | JSON (8.0+) | JSONB (binary, indexable) |
| Full-text search | FULLTEXT index | GIN/GiST + tsvector |
| Partitioning | Range, List, Hash, Key | Range, List, Hash (native) |
| Materialized Views | Yoxdur | Var |
| CTE (WITH) | 8.0+ | Hemishe olub |
| Window Functions | 8.0+ | Hemishe olub |
| UPSERT | INSERT ... ON DUPLICATE KEY | INSERT ... ON CONFLICT |
| Replication | Binary log (async/semi-sync) | WAL (streaming/logical) |
| Extension system | Yoxdur | Guclu (PostGIS, pg_trgm, ...) |

---

## Syntax Ferqleri

### Auto-Increment / Serial

```sql
-- MySQL
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255)
);

-- PostgreSQL
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,  -- ve ya GENERATED ALWAYS AS IDENTITY
    name VARCHAR(255)
);

-- PostgreSQL (modern)
CREATE TABLE users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(255)
);
```

### UPSERT

```sql
-- MySQL
INSERT INTO users (email, name) VALUES ('john@mail.com', 'John')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- PostgreSQL
INSERT INTO users (email, name) VALUES ('john@mail.com', 'John')
ON CONFLICT (email) DO UPDATE SET name = EXCLUDED.name;
```

### LIMIT / Pagination

```sql
-- MySQL
SELECT * FROM users LIMIT 10 OFFSET 20;

-- PostgreSQL (eynidir)
SELECT * FROM users LIMIT 10 OFFSET 20;

-- PostgreSQL (standard SQL)
SELECT * FROM users FETCH FIRST 10 ROWS ONLY OFFSET 20;
```

### String Functions

```sql
-- MySQL
SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM users;
SELECT IF(age > 18, 'adult', 'minor') FROM users;
SELECT IFNULL(nickname, name) FROM users;

-- PostgreSQL
SELECT STRING_AGG(name, ', ') FROM users;
SELECT CASE WHEN age > 18 THEN 'adult' ELSE 'minor' END FROM users;
SELECT COALESCE(nickname, name) FROM users;
```

### Boolean

```sql
-- MySQL: TINYINT(1), 0/1
SELECT * FROM users WHERE is_active = 1;

-- PostgreSQL: native BOOLEAN, true/false
SELECT * FROM users WHERE is_active = TRUE;
SELECT * FROM users WHERE is_active; -- Qisa yol
```

---

## PostgreSQL-in Ustunlukleri

### 1. JSONB

```sql
-- Binary JSON - index-lene biler, query-lene biler
CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    data JSONB NOT NULL
);

INSERT INTO events (data) VALUES ('{"type": "click", "page": "/home", "user_id": 1}');

-- JSONB query
SELECT * FROM events WHERE data->>'type' = 'click';
SELECT * FROM events WHERE data @> '{"type": "click"}'; -- Contains operator
SELECT data->'page' FROM events; -- JSON deyeri qaytarir

-- GIN index (suretli JSONB axtarisi)
CREATE INDEX idx_events_data ON events USING GIN (data);

-- Partial JSONB index
CREATE INDEX idx_events_type ON events USING GIN ((data->'type'));
```

**MySQL JSON (muqayise):**

```sql
-- MySQL JSON (8.0+)
CREATE TABLE events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    data JSON NOT NULL
);

SELECT * FROM events WHERE JSON_EXTRACT(data, '$.type') = 'click';
-- ve ya qisa yol:
SELECT * FROM events WHERE data->>'$.type' = 'click';

-- MySQL-de JSON index: generated column lazimdir
ALTER TABLE events ADD COLUMN event_type VARCHAR(50) 
    GENERATED ALWAYS AS (data->>'$.type') STORED;
CREATE INDEX idx_event_type ON events (event_type);
```

### 2. Array Type

```sql
-- PostgreSQL
CREATE TABLE posts (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255),
    tags TEXT[] -- Array of text
);

INSERT INTO posts (title, tags) VALUES ('Redis Guide', ARRAY['redis', 'cache', 'php']);

SELECT * FROM posts WHERE 'redis' = ANY(tags);
SELECT * FROM posts WHERE tags @> ARRAY['redis', 'php']; -- Her ikisini ehate edir

-- GIN index
CREATE INDEX idx_posts_tags ON posts USING GIN (tags);
```

### 3. Materialized Views

```sql
CREATE MATERIALIZED VIEW daily_stats AS
SELECT DATE(created_at) AS day, COUNT(*) AS total, SUM(amount) AS revenue
FROM orders GROUP BY DATE(created_at);

REFRESH MATERIALIZED VIEW CONCURRENTLY daily_stats;
```

### 4. CTEs ve Recursive Queries

```sql
-- Recursive CTE: Kategoriya agaci
WITH RECURSIVE category_tree AS (
    SELECT id, name, parent_id, 0 AS depth
    FROM categories WHERE parent_id IS NULL
    
    UNION ALL
    
    SELECT c.id, c.name, c.parent_id, ct.depth + 1
    FROM categories c
    JOIN category_tree ct ON ct.id = c.parent_id
)
SELECT * FROM category_tree ORDER BY depth, name;
```

### 5. Advanced Index Types

```sql
-- GIN (Generalized Inverted Index) - JSONB, array, full-text ucun
CREATE INDEX idx_data ON events USING GIN (data);

-- GiST (Generalized Search Tree) - geometric, range, full-text ucun
CREATE INDEX idx_location ON places USING GiST (coordinates);

-- BRIN (Block Range Index) - boyuk, sirali table-lar ucun (cox kicik index)
CREATE INDEX idx_orders_date ON orders USING BRIN (created_at);

-- Partial Index
CREATE INDEX idx_active ON users (email) WHERE deleted_at IS NULL;

-- Expression Index
CREATE INDEX idx_lower_email ON users (LOWER(email));
```

---

## MySQL-in Ustunlukleri

### 1. Replication sadəliyi
MySQL replication setup etmek daha asandir.

### 2. Clustering
MySQL Group Replication, InnoDB Cluster hazir cluster helli verir.

### 3. Ecosistem
Daha genis hosting destəyi, daha cox tool/framework destəyi.

### 4. Performance (bezi ssenarilerde)
Sade read-heavy workload-larda MySQL bezen daha suretli ola biler.

---

## Laravel-de ferqler

```php
// MySQL-de isleyen amma PostgreSQL-de islemir:
DB::raw('IFNULL(column, default)');  // PostgreSQL: COALESCE(column, default)
DB::raw('GROUP_CONCAT(name)');       // PostgreSQL: STRING_AGG(name, ',')

// PostgreSQL-de isleyen amma MySQL-de islemir (ve ya ferqli):
// JSONB operators
DB::table('events')->whereRaw("data @> ?", [json_encode(['type' => 'click'])]);

// Array
DB::table('posts')->whereRaw("'redis' = ANY(tags)");

// Laravel abstraction istifade et:
// Her ikisinde isleyir:
User::whereNull('deleted_at')->get();
User::select('name')->groupBy('name')->get();
```

---

## Hansini secmeli?

| Ssenari | Tovsiye |
|---------|---------|
| Sade web app, CMS | MySQL (sade setup, genis dəstək) |
| Complex queries, analytics | PostgreSQL |
| JSON-heavy data | PostgreSQL (JSONB) |
| GIS/Location data | PostgreSQL (PostGIS) |
| Maximum hosting uygunlugu | MySQL |
| Data integrity kritikdir | PostgreSQL |
| Full-text search (basic) | Her ikisi (amma PG daha guclu) |
| Boyuk scale read-heavy | MySQL (sade replication) |

---

## Interview suallari

**Q: MySQL ve PostgreSQL arasinda nece seçim edersin?**
A: Layihənin tələblərinə baxıram. Complex query-lər, JSONB, GIS, materialized view lazımdırsa - PostgreSQL. Sadə CRUD, geniş hosting dəstəyi, asan replication lazımdırsa - MySQL. Əksər Laravel layihələrində hər ikisi yaxşı işləyir.

**Q: PostgreSQL-de JSONB vs ayrı table?**
A: Strukturlu, sorğulanan data üçün ayrı table (normalization). Yarı-strukturlu, dəyişkən schema data üçün JSONB (audit log, settings, metadata). JSONB-ni "lazy schema" kimi istifadə etmə - əgər bilirsən ki data strukturlu olacaq, table yarat.

**Q: MySQL-in REPEATABLE READ vs PostgreSQL-in READ COMMITTED default-u - niyə fərqlidir?**
A: MySQL InnoDB gap lock istifadə edir (phantom read-ı REPEATABLE READ-də belə qarşısını alır), ona görə default olaraq daha güclü isolation seçilib. PostgreSQL MVCC-ni fərqli implement edir və READ COMMITTED-i performance/correctness balansı üçün daha uyğun hesab edir.
