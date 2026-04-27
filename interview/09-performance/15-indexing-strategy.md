# Database Indexing Strategy (Senior ⭐⭐⭐)

## İcmal

Database indexing — sorğuların sürətləndirilməsi üçün B-Tree, Hash, GiST, GIN kimi məlumat strukturlarının cədvəl üzərindəki sütunlara tətbiqidir. Index olmadan DB hər sorğu üçün bütün cədvəli oxuyur (Seq Scan). Index ilə isə birbaşa hədəf sətirə atlanılır. Lakin hər sütuna index qoymaq da yanlışdır — yazma əməliyyatları yavaşlayır, storage artır. Düzgün index strategiyası query pattern analizi tələb edir.

## Niyə Vacibdir

Çox layihədə performans probleminin həlli "düzgün index əlavə etmək"dir. 10M cədvəldə index olmayan sütun üzrə axtarış = Seq Scan = saniyələrlə gözləmə. Amma yanlış index = hər INSERT/UPDATE/DELETE 5-10ms əlavə vaxt + storage. Senior developer sadəcə "index əlavə et" deyil, hansı index, nə zaman, necə yaratmaq bilir.

## Əsas Anlayışlar

- **Index növləri (PostgreSQL):**
  - **B-Tree** — default, =, <, >, BETWEEN, ORDER BY
  - **Hash** — yalnız `=` (PostgreSQL 10+ disk-safe)
  - **GiST** — geometry, full-text, custom types
  - **GIN** — JSONB, array, full-text (inverted index)
  - **BRIN** — Block Range Index, böyük time-series cədvəl
  - **Partial index** — WHERE şərtli (xüsusi hallar üçün)
  - **Covering index** — INCLUDE ilə extra sütunlar

- **Composite index qaydaları:**
  - Sol sütun ən selective (az unique dəyər yox!)
  - Əslində: ən çox filter olunan önə
  - Range condition ən sona (B-Tree rule)
  - `(a, b, c)` → `WHERE a = ? AND b = ?` işləyir, `WHERE b = ?` işləmir (left prefix)

- **Index selectivity:**
  - Yüksək selectivity (az duplicate) → index faydalı
  - Aşağı selectivity (çox duplicate) → index faydasız ola bilər
  - Nümunə: `status` (3 dəyər) = aşağı; `email` = yüksək
  - Statistika: `pg_stats.n_distinct` (negative = fraction of rows)

- **Index bloat:**
  - DELETE/UPDATE sonrası "ölü" index entry-lər qalır
  - `VACUUM` → ölü entry-ləri təmizləyir
  - `REINDEX CONCURRENTLY` → tam yenidən yarat

- **Index hits vs misses:**
  - `pg_stat_user_indexes` — index hit sayı
  - Hit rate = idx_scan / (idx_scan + seq_scan)
  - Köhnə index (hit=0) → sil (write overhead yox etmək)

- **MySQL vs PostgreSQL fərqləri:**
  - MySQL InnoDB: clustered index (primary key ilə data birlikdə)
  - PostgreSQL: heap table (data və index ayrı)
  - MySQL: secondary index → primary key-ə pointer (double lookup)

## Praktik Baxış

**Index yaratmaq (Laravel migration):**

```php
// Simple index
$table->index('email');
$table->index(['status', 'created_at']);

// Unique
$table->unique(['user_id', 'product_id'], 'uq_user_product');

// Partial index (raw)
DB::statement('CREATE INDEX idx_orders_pending ON orders (user_id, created_at)
               WHERE status = \'pending\'');

// Covering index
DB::statement('CREATE INDEX idx_orders_covering
               ON orders (user_id, status, created_at DESC)
               INCLUDE (id, total)');

// GIN for JSONB
$table->index('metadata', 'idx_products_metadata', 'gin');

// CONCURRENTLY — production, lock olmadan
DB::statement('CREATE INDEX CONCURRENTLY idx_orders_status
               ON orders (status, created_at DESC)');
```

**Composite index dizaynı:**

```sql
-- Query:
SELECT id, total, created_at
FROM orders
WHERE user_id = 123
  AND status = 'pending'
ORDER BY created_at DESC
LIMIT 20;

-- Index strategiyası:
-- 1. user_id = ən selective (hər user üçün az sifariş)
-- 2. status = filter
-- 3. created_at DESC = sort
-- 4. INCLUDE id, total = covering (seq heap fetch yox)

CREATE INDEX idx_orders_user_status_date
ON orders (user_id, status, created_at DESC)
INCLUDE (id, total);

-- EXPLAIN: "Index Only Scan" → çox sürətli
```

**Partial index (az yer, böyük effekt):**

```sql
-- Status 'pending' olan cərgələr cəmisi 5% -- 95%-ni index-ə yükləmə
CREATE INDEX idx_orders_pending_user
ON orders (user_id, created_at)
WHERE status = 'pending';

-- Faydalı: admin dashboard "pending orders" görünüşü
-- İstifadə edilmir: status='completed' sorğularda

-- PHP/Laravel:
$query->where('user_id', $userId)
      ->where('status', 'pending')
      ->orderBy('created_at', 'desc');
// PostgreSQL planner partial index-i seçir
```

**GIN index (JSONB):**

```sql
-- JSONB axtarışı üçün
CREATE INDEX idx_products_meta ON products USING GIN (metadata);

-- Query (GIN işləyir):
SELECT * FROM products
WHERE metadata @> '{"color": "red", "brand": "Nike"}';

-- GIN + path search:
SELECT * FROM products
WHERE metadata->'specs'->>'weight' = '500g';
-- Bu işləmir GIN ilə, expression index lazım:
CREATE INDEX idx_products_weight ON products ((metadata->'specs'->>'weight'));
```

**BRIN index (time-series):**

```sql
-- Böyük log cədvəli — insert order-i ilə sıralanır
CREATE TABLE system_logs (
    id BIGSERIAL,
    created_at TIMESTAMPTZ DEFAULT now(),
    level TEXT,
    message TEXT
);

-- BRIN: çox az yer, date range sorğuları üçün
CREATE INDEX idx_logs_brin ON system_logs USING BRIN (created_at);
-- B-Tree index: ~3% table size
-- BRIN: ~0.01% table size
-- Range query: BRIN daha az effective, amma kafi

-- Yaxşı: "Bu gün + dünün logları" sorğusu
-- Pis: Random date lookup (B-Tree daha yaxşı)
```

**Index istifadəsini yoxlamaq:**

```sql
-- Cədvəldəki bütün index-lər və onların istifadəsi
SELECT
    indexname,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch,
    pg_size_pretty(pg_relation_size(indexrelid)) as size
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
  AND relname = 'orders'
ORDER BY idx_scan DESC;

-- idx_scan = 0 olan index-lər = faydasız, sil
```

**Unused index tapıb silmək:**

```sql
-- Production-da istifadə edilməyən index-lər
SELECT schemaname, tablename, indexname,
       pg_size_pretty(pg_relation_size(indexrelid)) as index_size
FROM pg_stat_user_indexes
WHERE idx_scan = 0
  AND indexname NOT LIKE '%_pkey'
ORDER BY pg_relation_size(indexrelid) DESC;

-- Sil (CONCURRENTLY — production lock yoxdur)
DROP INDEX CONCURRENTLY idx_old_unused;
```

**Online schema change (production):**

```bash
# pg_repack — table bloat + VACUUM full alternativia
pg_repack --table orders --jobs 4

# pt-online-schema-change (MySQL)
pt-online-schema-change \
  --alter "ADD INDEX idx_status_date (status, created_at)" \
  --execute D=myapp,t=orders

# gh-ost (GitHub, MySQL)
gh-ost \
  --alter "ADD INDEX idx_status_date (status, created_at)" \
  --table=orders \
  --execute
```

**Laravel — index audit artisan command:**

```php
// app/Console/Commands/IndexAuditCommand.php
class IndexAuditCommand extends Command
{
    protected $signature = 'db:index-audit';

    public function handle(): void
    {
        // Unused indexes
        $unused = DB::select("
            SELECT indexname, tablename,
                   pg_size_pretty(pg_relation_size(indexrelid)) as size
            FROM pg_stat_user_indexes
            WHERE idx_scan = 0
              AND indexname NOT LIKE '%_pkey'
              AND schemaname = 'public'
        ");

        $this->table(['Index', 'Table', 'Size'], collect($unused)->map(fn($r) => [
            $r->indexname,
            $r->tablename,
            $r->size,
        ]));

        // Missing indexes (seq scan > 1000 olan cədvəllər)
        $seqScans = DB::select("
            SELECT relname, seq_scan, idx_scan,
                   round(seq_scan::numeric / (seq_scan + idx_scan + 1) * 100, 1) as seq_pct
            FROM pg_stat_user_tables
            WHERE seq_scan > 1000
              AND seq_scan > idx_scan
            ORDER BY seq_scan DESC
            LIMIT 10
        ");

        $this->table(['Table', 'Seq Scans', 'Idx Scans', 'Seq %'], collect($seqScans)->map(fn($r) => [
            $r->relname, $r->seq_scan, $r->idx_scan, $r->seq_pct . '%',
        ]));
    }
}
```

**Trade-offs:**
- Index artıqlığı → INSERT/UPDATE/DELETE hər index üçün +1-5ms
- Covering index → storage artır, amma Index Only Scan
- BRIN → az yer, amma yalnız sorted data üçün effektiv
- GIN → JSONB axtarışı üçün güclü, amma write overhead çox
- Partial index → az yer, amma yalnız WHERE şərtli sorğular üçün

**Common mistakes:**
- `SELECT *` ilə covering index effektini itirmək
- Hər foreign key üçün avtomatik index yaratmaq (read-heavy olmayan FK-lər üçün lazımsız)
- Range sütununu composite index-in soluna qoymaq
- Production-da `CREATE INDEX` (CONCURRENT olmadan) — table lock!
- `status` kimi low-selectivity sütunu tək başına index-ləmək

## Nümunələr

### Real Ssenari: Orders API 3 index — əslində 1 lazım idi

```
Mövcud index-lər:
1. idx_orders_user_id (user_id)
2. idx_orders_status (status)
3. idx_orders_created (created_at)

Əsas sorğu:
SELECT * FROM orders
WHERE user_id = 123
  AND status = 'pending'
ORDER BY created_at DESC
LIMIT 20;

EXPLAIN: PostgreSQL idx_orders_user_id seçir, amma həm status filter
        həm sort üçün heap fetch edir → yavaş

Həll:
DROP INDEX idx_orders_status;
DROP INDEX idx_orders_created;
CREATE INDEX idx_orders_user_status_date
ON orders (user_id, status, created_at DESC);

Nəticə:
- 3 index → 1 index (yazma overhead azaldı)
- Sorğu: Index Only Scan (heap fetch yoxdur)
- p99: 280ms → 8ms
```

### Kod Nümunəsi

```php
<?php

// Migration: Optimal indexes
class AddOrdersIndexes extends Migration
{
    public function up(): void
    {
        // User dashboard: pending orders
        DB::statement('
            CREATE INDEX CONCURRENTLY idx_orders_user_pending
            ON orders (user_id, created_at DESC)
            WHERE status = \'pending\'
        ');

        // Admin: date range + status filter
        DB::statement('
            CREATE INDEX CONCURRENTLY idx_orders_admin_listing
            ON orders (status, created_at DESC)
            INCLUDE (id, user_id, total)
        ');

        // Full-text search on customer name (in JSON)
        DB::statement('
            CREATE INDEX CONCURRENTLY idx_orders_customer_gin
            ON orders USING GIN (customer_data jsonb_path_ops)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_orders_user_pending');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_orders_admin_listing');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_orders_customer_gin');
    }
}
```

## Praktik Tapşırıqlar

1. **EXPLAIN audit:** Mövcud bir layihədə 5 endpoint götür, hər birinin əsas SQL-ini `EXPLAIN ANALYZE` ilə yoxla, Seq Scan olanları tap.

2. **Composite index yarat:** `orders` cədvəli üçün query pattern analiz et, ən çox istifadə olunan WHERE + ORDER BY sütunlarını tap, composite index yarat.

3. **Index audit command:** Yuxarıdakı artisan command-ı implement et, `idx_scan = 0` olan index-ləri tapıb report et.

4. **Partial index:** `status = 'active'` olan user-ların yalnız 10%-i ola bilər — partial index yarat, `pg_relation_size` ilə tam index ilə fərqini müqayisə et.

5. **JSONB GIN:** `metadata` JSONB sütununun olan cədvəl yarat, GIN index olmadan vs ilə `@>` operator sorğusunu EXPLAIN ilə müqayisə et.

## Əlaqəli Mövzular

- `02-query-optimization.md` — Index ilə query optimallaşdırma
- `08-pagination-strategies.md` — Keyset pagination üçün index
- `01-performance-profiling.md` — Index effektivliyini profiling ilə ölçmək
- `05-connection-pool-tuning.md` — Index azaldıqda DB yükü azalır
- `11-apm-tools.md` — Slow query APM ilə aşkarlama
