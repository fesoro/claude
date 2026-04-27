# Index Types and B-Tree (Middle ⭐⭐)

## İcmal

Index — database-in axtarış sürətini artıran data strukturudur. Bu mövzu interview-da həm "necə işləyir" (B-Tree internals), həm də "nə vaxt istifadə edərsiniz" (real query optimization) bucaqlarından soruşulur. Index-siz JOIN-lar yavaş sorğulara, lazımsız index-lər isə yavaş write-lara səbəb olur. "Hər column-a index qoy" yanaşması production-da ciddi problem yaradır.

## Niyə Vacibdir

Production-da ən çox rast gəlinən performance problemlərindən biri "missing index"dir. İnterviewer bu sualla sizin query execution plan-ı oxuya bilib-bilmədiyinizi, fərqli index növlərini nə zaman istifadə etdiyinizi, over-indexing problemini baxıb-baxmadığınızı yoxlayır. "Index əlavə etdim" kifayət deyil — niyə həmin index, niyə həmin column, niyə həmin sırada, alternativlər nə idi? Senior developer-lər bunları əsaslandırır.

## Əsas Anlayışlar

### B-Tree Index (Əsas Index Növü)

- **B-Tree (Balanced Tree):** PostgreSQL-in default index növü. Balanced — hər leaf node eyni dərinlikdədir. Axtarış O(log n). Equality (`=`), range (`<`, `>`, `BETWEEN`, `LIKE 'prefix%'`) sorğuları üçün ideal.
- **B-Tree Node Strukturu:** Hər node-da sorted keys + pointers var. Internal node-lar yönləndirir, leaf node-lar data pointer-lərini saxlayır.
- **Bölünmə (Page Split):** Node dolu olanda bölünür — bu write-larda overhead yaradır. Heavy insert yükündə `fillfactor` azaldılır (məsələn 70%).
- **Index Pages:** PostgreSQL-də default 8KB page. Index-lərin özü də paginated-dir — buffer pool-da cache olunurlar.

### Index Növləri

- **B-Tree Index:** Əsas növ — sorted, balanced tree. Equality və range queries üçün ideal. `ORDER BY` push-down imkanı verir.
- **Hash Index:** Exact match üçün (`=` only). Range query-ləri dəstəkləmir. PostgreSQL 10-dan WAL-logged (crash-safe). B-Tree-dən nadir hallarda daha sürətli — hash function O(1), lakin RAM-da saxlanılmır.
- **GIN (Generalized Inverted Index):** Array, JSONB, full-text search üçün — içindəki elementlərə görə axtarış. `@>`, `&&`, `@@` operatorları. Write-da yavaş (posting list update).
- **GiST (Generalized Search Tree):** Geometric data, full-text — PostGIS, tsvector. Range types üçün.
- **BRIN (Block Range Index):** Çox böyük sequential tablolar üçün — time-series, log tables. Çox kiçik ölçü (megabytes). Sequential scan-dən daha az effektiv, lakin əhəmiyyətli disk qənaəti.
- **SP-GiST (Space-Partitioned GiST):** Non-balanced tree strukturları — radix tree, quad tree. Telecom prefix matching, geometric partitioning.

### Composite Index

- **Leftmost Prefix Rule:** `(a, b, c)` composite index — `(a)`, `(a,b)`, `(a,b,c)` sorğularını dəstəkləyir; `(b)`, `(c)`, `(b,c)` başlayan sorğular üçün index yoxdur (sequential scan).
- **Column Sırası:** High selectivity column əvvəl gəlməlidir. `(user_id, status)` — user_id milyon fərqli dəyər, status yalnız 5 — user_id əvvəl.
- **Sort Order:** `(created_at DESC, id DESC)` — `ORDER BY created_at DESC` olan sorğular index-only scan edir.
- **Equality + Range Mix:** Equality column-lar əvvəl, range column sonra: `(user_id, status, created_at)` — `user_id = ? AND status = ? AND created_at > ?`.

### Partial Index

- **Müəyyən şərtə uyan row-lar üçün:** `WHERE deleted_at IS NULL` — yalnız aktiv recordlar index-dədir. Index ölçüsü kiçilir, axtarış sürətlənir.
- **Sparse data üçün:** Yalnız `status = 'pending'` olan sifarişlər üçün index — pending olmayan 95% sətir index-dən çıxır.
- **Write overhead azalır:** Silinen/cancelled record-lar index-ə daxil edilmir.
- **Partial index limitation:** Sorğu `WHERE` şərti index-in şərti ilə uyğun gəlməlidir — planner avtomatik tanır.

### Covering Index (Index-Only Scan)

- **Covering Index:** Query-nin lazım olan bütün column-ları index-də saxlanır — table-a get lazım deyil. I/O minimaldır.
- **INCLUDE clause (PostgreSQL 11+):** `CREATE INDEX ON orders(user_id) INCLUDE (status, total_amount)` — status, total_amount index-ə əlavə olunur, lakin search key kimi işlənmir.
- **Index-Only Scan:** Execution plan-da "Index Only Scan" görünsə, table heap-ə get yoxdur — ən sürətli sorğu növü.
- **Visibility Map:** Index-only scan üçün tuple-ların visible olduğunu yoxlayır. `VACUUM` sonrası daha effektiv.

### Index Selectivity

- **Selectivity:** `COUNT(DISTINCT column) / COUNT(*)` — yüksək selectivity daha faydalı index. `user_id` ≈ 1.0, `status` ≈ 0.001.
- **Low Cardinality Column:** Boolean, gender, status kimi column-lara ayrıca index mənasızdır. Optimizer bütün table-ı skan etməyi seçər.
- **Histogram:** `pg_stats.histogram_bounds` — data distribution-u. Skewed distribution-da index-i optimizer yanlış istifadə edə bilər.

### Index Overhead və Maintenance

- **Write Overhead:** Hər `INSERT/UPDATE/DELETE` bütün index-ləri update edir. 10 index = 10 B-Tree update — write-heavy tabloda mümkün qədər az index.
- **Index Bloat:** Silmə/update-lərdən sonra index-in şişməsi. `VACUUM` dead tuple-ları təmizləyir; `REINDEX CONCURRENTLY` index-i yenidən qurur (production-da lock-suz).
- **Index-Only Scan + HOT:** Heap Only Tuple (HOT) update — index-i update etmədən table-da update. Əgər indexed column dəyişmirsə HOT işləyir.
- **Unused Index:** `pg_stat_user_indexes.idx_scan = 0` — heç istifadə edilməmiş index. Disk + write overhead var, amma heç bir fayda yoxdur — silin.

### Clustered vs Non-Clustered

- **Clustered Index (MySQL InnoDB Primary Key):** Data fiziki olaraq index sırası ilə saxlanır. Range scan çox sürətlidir. Yalnız bir clustered index ola bilər.
- **Non-Clustered Index (PostgreSQL adətən):** Ayrı data structure — heap-ə pointer var. Hər index lookup + heap fetch.
- **PostgreSQL CLUSTER:** `CLUSTER orders USING idx_orders_created_at` — table-ı fiziki olaraq index sırasına görə sıralayır. One-shot, auto-update yoxdur.

## Praktik Baxış

### Interview-da Yanaşma

1. **B-Tree-nin niyə "balanced" olduğunu izah edin** — O(log n) axtarış, leaf nodes eyni dərinlikdə.
2. **Composite index sırasını explain edin:** high selectivity column əvvəl, equality əvvəl range-dən.
3. **"Index həmişə faydalıdır" fikrini rədd edin** — yazma overhead, maintenance cost, cardinality.
4. **EXPLAIN ANALYZE-i əzbər bilin:** "Seq Scan" = index yoxdur/istifadə edilmir; "Index Scan" = index var; "Index Only Scan" = covering index.
5. **Partial index nümunəsi verin** — "active records üçün partial index 90% kiçik idi."

### Follow-up Suallar (İnterviewerlər soruşur)

- "EXPLAIN ANALYZE output-da hansı metrics-ə baxırsınız?" — actual rows vs estimated, Buffers, actual time.
- "Composite index-də column sırası niyə önəmlidir?" — leftmost prefix rule.
- "Full-text search üçün hansı index istifadə edərdiniz?" — GIN + tsvector, ILIKE-dən fərqi.
- "Over-indexing problemi nədir?" — Write overhead, storage, maintenance complexity.
- "Index bloat nədir, necə həll olunur?" — VACUUM, REINDEX CONCURRENTLY.
- "Covering index nə zaman lazımdır?" — SELECT-dəki bütün column-lar index-dədirsə.
- "PostgreSQL-də Hash index B-Tree-dən nə zaman üstündür?" — Nadir hallarda — exact match, equal-sized short keys.

### Common Mistakes

- "Hər column-a index qoy" demək — write performance aşağı düşür.
- Composite index-də column sırasını bilməmək — leftmost prefix rule.
- Low-cardinality column-a index qoymaq (gender, status kimi ayrıca).
- Index-in write performance-a təsirini qeyd etməmək.
- `LIKE '%keyword%'` sorğusu üçün B-Tree index kömək etmir — GIN lazımdır.
- `LOWER(email)` kimi function-wrapped query üçün regular index işləmir — expression index lazımdır.

### Yaxşı → Əla Cavab

- **Yaxşı:** Index növlərini sadalayır, B-Tree-ni izah edir.
- **Əla:** EXPLAIN ANALYZE nəticəsini oxuyur, partial + covering index-i real use case ilə izah edir, unused index monitoring-dən danışır, index bloat + VACUUM anlayır, expression index nümunəsi verir.

### Real Production Ssenariləri

- `orders(user_id, status, created_at)` composite index + `WHERE deleted_at IS NULL` partial — aktiv orders lookup 100x sürətləndi.
- GIN index `products.search_vector` üzərindən — `LIKE '%keyword%'` 2s-dən 5ms-ə düşdü.
- `pg_stat_user_indexes` ilə 12 unused index tapıb sildik — write throughput 30% artdı.
- `INCLUDE` ilə covering index — reporting sorğusunun Index Only Scan etməsi table I/O-nu sıfırladı.

## Nümunələr

### Tipik Interview Sualı

"Sifarişlər tablonuzda `user_id` və `status` üzərindən filter edirsiniz. Index-ləri necə qurardınız?"

### Güclü Cavab

İlk öncə access pattern-i analiz edərdim. Əgər sorğu həm `user_id`, həm `status` ilə filter edirsə, composite index `(user_id, status)` ən uyğundur — `user_id` high cardinality-dir, əvvəlcə gəlməlidir. Leftmost prefix rule ilə `WHERE user_id = ?` sorğusu da bu index-dən istifadə edər.

Status yalnız bir neçə dəyər alırsa (low cardinality), onu composite-ə əlavə etmək hər user-in sifarişlərini status-a görə daha da effektiv filterlər. Lakin sorğu `status = 'pending'` üzərindən ayrıca çalışırsa, həmin halda ayrı index lazımdır.

`WHERE deleted_at IS NULL` əlavə olaraq varsa, partial index daha effektivdir — silinmiş sifarişlər index-dən çıxar, ölçü azalar, axtarış sürətlənər. Son olaraq `EXPLAIN ANALYZE` ilə "Seq Scan" qalıb-qalmadığını yoxlardım.

### Kod Nümunəsi

```sql
-- Sadə index — user_id üzərindən
CREATE INDEX idx_orders_user_id ON orders(user_id);

-- Composite index — leftmost prefix rule
-- (user_id, status) sırası: user_id high cardinality → əvvəl
CREATE INDEX idx_orders_user_status ON orders(user_id, status);

-- Bu sorğular index-dən istifadə edir:
SELECT * FROM orders WHERE user_id = 1;
SELECT * FROM orders WHERE user_id = 1 AND status = 'pending';
SELECT * FROM orders WHERE user_id = 1 AND status = 'pending'
  ORDER BY created_at DESC;

-- Bu sorğu index-dən istifadə ETMƏZ (user_id yoxdur):
SELECT * FROM orders WHERE status = 'pending';  -- Seq Scan!
-- Bu halda ayrı index lazımdır:
CREATE INDEX idx_orders_status ON orders(status);

-- Partial index — yalnız active/pending sifarişlər
-- Silinnmiş 80% sətir index-dən çıxır
CREATE INDEX idx_orders_active_user ON orders(user_id, created_at)
WHERE deleted_at IS NULL AND status NOT IN ('cancelled', 'refunded');

-- Covering index — table-a get lazım deyil (Index Only Scan)
CREATE INDEX idx_orders_covering ON orders(user_id, status)
INCLUDE (created_at, total_amount);
-- Bu sorğu Index Only Scan edir:
SELECT user_id, status, created_at, total_amount
FROM orders
WHERE user_id = 42 AND status = 'pending';
```

```sql
-- GIN index — Full-text search
ALTER TABLE products
ADD COLUMN search_vector tsvector
  GENERATED ALWAYS AS (
    to_tsvector('english', COALESCE(name,'') || ' ' || COALESCE(description,''))
  ) STORED;

CREATE INDEX idx_products_fts ON products USING GIN(search_vector);

-- Full-text axtarış — GIN index istifadə edir
SELECT id, name FROM products
WHERE search_vector @@ to_tsquery('english', 'gaming & laptop');

-- Ranking ilə
SELECT id, name,
       ts_rank(search_vector, to_tsquery('english', 'gaming & laptop')) AS rank
FROM products
WHERE search_vector @@ to_tsquery('english', 'gaming & laptop')
ORDER BY rank DESC;

-- JSONB üçün GIN
CREATE INDEX idx_products_specs ON products USING GIN(specs);
SELECT * FROM products WHERE specs @> '{"ram": "16GB"}';
```

```sql
-- EXPLAIN ANALYZE — index yoxlama
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT order_id, total_amount
FROM orders
WHERE user_id = 42 AND status = 'pending'
ORDER BY created_at DESC
LIMIT 10;

-- Nəticəni oxuma:
-- "Seq Scan on orders" → Index yoxdur / istifadə edilmir
--   rows=1000000 → bütün table skanlanır
-- "Index Scan using idx_orders_user_status" → Index işlənir
-- "Index Only Scan using idx_orders_covering" → Table-a get yoxdur
-- cost=0.00..8.30 rows=10 width=12 → Estimate
-- actual time=0.123..0.145 rows=10 loops=1 → Real icra
-- Buffers: shared hit=8 read=2 → read=disk I/O, hit=cache

-- Actual rows vs estimated rows böyük fərq = statistics problem
-- ANALYZE orders; → statistics-i yenilə
```

```sql
-- Expression index — function-wrapped query üçün
-- Bu işləmir (LOWER function B-Tree-ni bypass edir):
SELECT * FROM users WHERE LOWER(email) = 'user@example.com';
-- Həll: expression index
CREATE INDEX idx_users_email_lower ON users(LOWER(email));
-- İndi işləyir:
SELECT * FROM users WHERE LOWER(email) = 'user@example.com';  -- Index Scan!

-- BRIN index — time-series, sequential data üçün
-- Çox kiçik ölçü, lakin B-Tree-dən az precise
CREATE INDEX idx_logs_created_brin ON logs USING BRIN(created_at)
WITH (pages_per_range = 128);
-- Kiçik log tablosu üçün B-Tree, böyük (TB-level) üçün BRIN
```

```sql
-- Index effektivliyini monitoring
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan,       -- neçə dəfə istifadə edilib
    idx_tup_read,   -- neçə tuple oxunub
    idx_tup_fetch,  -- neçə tuple fetch olunub
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
ORDER BY idx_scan ASC;  -- idx_scan=0 → istifadəsiz index, silin!

-- Index bloat yoxlama
SELECT
    tablename,
    indexname,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
ORDER BY pg_relation_size(indexrelid) DESC;

-- Production-da lock-suz index yenidən qurma
REINDEX INDEX CONCURRENTLY idx_orders_user_status;
```

```php
// Laravel-də index əlavə etmə (migration)
Schema::table('orders', function (Blueprint $table) {
    // Composite index
    $table->index(['user_id', 'status'], 'idx_orders_user_status');

    // Covering index (INCLUDE) — raw SQL lazımdır
    DB::statement(
        'CREATE INDEX idx_orders_covering ON orders(user_id, status) INCLUDE (created_at, total_amount)'
    );

    // Partial index — raw SQL
    DB::statement(
        'CREATE INDEX idx_orders_active ON orders(user_id, created_at) WHERE deleted_at IS NULL'
    );

    // Full-text search
    $table->fullText(['title', 'description'], 'idx_products_fulltext');
});

// Eloquent-də index-ə uyğun sorğu
// DOĞRU: (user_id, status) composite index istifadə edir
Order::where('user_id', $userId)
     ->where('status', 'pending')
     ->orderBy('created_at', 'desc')
     ->limit(10)
     ->select(['id', 'status', 'created_at', 'total_amount'])  // covering columns
     ->get();

// YANLIQ: leftmost column yoxdur → Seq Scan!
Order::where('status', 'pending')->get();

// Expression index üçün
Order::whereRaw('LOWER(customer_email) = ?', [strtolower($email)])->get();
```

### İkinci Nümunə: Index Strategiya Problemi

```sql
-- Sual: Bu tabloda hansı index-lər lazımdır?
-- users: 10M row
-- posts: 500M row  
-- Sorğu 1: Aktiv user-lərin son 100 postunu göstər
-- Sorğu 2: Tag ilə post axtarışı (full-text)
-- Sorğu 3: User-in aktiv olmayan postlarını sil (soft delete)

-- Sorğu 1 üçün:
CREATE INDEX idx_posts_user_recent ON posts(user_id, created_at DESC)
WHERE deleted_at IS NULL;
-- user_id equality → əvvəl; created_at DESC → sort order uyğunlaşır

-- Sorğu 2 üçün:
CREATE INDEX idx_posts_tags ON posts USING GIN(tags);  -- tags JSONB/array olarsa
-- ya da
ALTER TABLE posts ADD COLUMN search_vec tsvector
    GENERATED ALWAYS AS (to_tsvector('english', title || ' ' || body)) STORED;
CREATE INDEX idx_posts_fts ON posts USING GIN(search_vec);

-- Sorğu 3 üçün:
-- Soft delete — WHERE user_id = ? AND deleted_at IS NULL → partial index kömək edir
-- Lakin DELETE üçün həmin partial index: index-dən çıxan row-lar üçün vacuum lazım

-- NƏYƏ INDEX LAZIM DEYİL?
-- posts.status — yalnız 4 dəyər, low cardinality
-- users.gender — boolean-a bənzər, low cardinality
-- posts.view_count — continuous update, index bloat yaranar
```

## Praktik Tapşırıqlar

1. PostgreSQL-də 1 milyon row olan `orders` tablonu yaradın, `EXPLAIN ANALYZE` ilə index olmadan vs composite index ilə fərqi müqayisə edin — actual time fərqini ölçün.
2. Composite index `(user_id, status, created_at)` üçün hansı sorğuların işləyib-işləmədiyini test edin — leftmost prefix rule-u praktikada görün.
3. `pg_stat_user_indexes` istifadə edərək unused index-ləri tapın (idx_scan = 0 olan-ları), silin.
4. `orders` tablonuzda `WHERE deleted_at IS NULL` filter üçün partial index yaradın, ölçü fərqini `pg_relation_size()` ilə ölçün.
5. Full-text search üçün GIN index yaradın, `ILIKE '%keyword%'` vs `@@` to_tsquery performance-ını müqayisə edin — Buffers fərqini izah edin.
6. Expression index yaradın: `LOWER(email)` üçün, `EXPLAIN ANALYZE` ilə index-in istifadə edildiyini göstərin.
7. `REINDEX CONCURRENTLY` əmrini bloated index üzərindən işlədin, ölçü fərqini ölçün.
8. Covering index ilə "Index Only Scan" əldə edin, `Buffers: shared read=0` göstərin.

## Əlaqəli Mövzular

- `05-query-optimization.md` — EXPLAIN ANALYZE-in dərin oxunuşu, query planner.
- `03-normalization-denormalization.md` — Denormalized tablolarda index strategiyası.
- `06-transaction-isolation.md` — Index-lərin MVCC ilə qarşılıqlı təsiri.
- `10-database-replication.md` — Read replica-da index fərqləri, replica-da index rebuild.
