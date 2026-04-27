# Database Migration Strategies (Senior ⭐⭐⭐)

## İcmal
Database migration — schema dəyişikliklərini production-da downtime olmadan necə tətbiq etmək məsələsidir. "Sadəcə `ALTER TABLE` et" yanaşması production-da table-ı saatlarla kilit altında saxlaya bilər. Senior interview-larda zero-downtime migration texnikaları, expand-contract pattern, rollback strategiyaları soruşulur.

## Niyə Vacibdir
Schema migration yanlış edilsə production outage baş verə bilər. İnterviewer bu sualla sizin real production-da migration keçirdiyinizi, riskləri bildiyinizi, backward compatibility-ni nəzərə alıb-almadığınızı yoxlayır. "Biz maintenance window-da edirik" cavabı qəbul edilə bilər — lakin zero-downtime alternativini bilmək Senior developer-in borcudur.

## Əsas Anlayışlar

- **Locking:** `ALTER TABLE ADD COLUMN` — PostgreSQL-də sürətlidir (metadata dəyişiklik). Lakin `ADD COLUMN NOT NULL DEFAULT` köhnə versiyalarda (< PG11) bütün row-ları yenidən yazır → uzun exclusive lock
- **PostgreSQL 11+ Optimization:** `DEFAULT` dəyərli `NOT NULL` column artıq metadata-da saxlanır, table scan lazım deyil — mükəmməl
- **Expand-Contract Pattern (3 mərhələli):** 1) Yeni column/table əlavə et (expand); 2) Hər iki formada data yaz, background-da backfill; 3) Köhnəni sil (contract). Zero-downtime üçün standart yanaşma
- **pg_repack:** Table-ı lock olmadan yenidən qurmaq üçün extension. Bloat-ı aradan qaldırır. `VACUUM FULL`-un lock-suz alternatividir
- **gh-ost:** GitHub-ın MySQL online schema change tool-u. Triggers yox, binlog istifadə edir. Production MySQL migration-ları üçün sənaye standartı
- **pt-online-schema-change (Percona):** Triggers + shadow table ilə MySQL online migration. `gh-ost`-dan köhnə amma geniş istifadə olunur
- **Zero-Downtime Deployment:** Migration deploy-dan əvvəl çalışır, yeni kod həm köhnə hem yeni schema-nı başa düşür. "Backward compatible migration" tələbi
- **Backward Compatible Migration:** Köhnə kod yeni schema-da da işləyə bilməlidir. Column silmə mümkün deyil, yalnız əlavə etmək (köhnə kod unknown column-u ignore edir)
- **NOT NULL without DEFAULT:** PostgreSQL-də hər row-u scan edir, NULL check edir — table lock uzun çəkir. Həll: əvvəl nullable əlavə et, backfill et, sonra `NOT NULL` et
- **CREATE INDEX CONCURRENTLY:** Index yaranarkən table lock olmur. Uzun çəkir (table scan) amma production-da traffic kəsilmir. YANLIŞ: `CREATE INDEX idx... ON table(col)` (lock!)
- **REINDEX CONCURRENTLY:** Mövcud corrupt/bloated index-i lock olmadan yenidən qurmaq
- **Column Rename:** Expand-contract: yeni column əlavə et + trigger/application ilə data sync et + köhnə column sil. Birbaşa `ALTER TABLE RENAME COLUMN` breaking change-dir
- **Rollback Strategy:** `down()` metodu data itirəcəksə (column drop, data transform) rollback zərərli ola bilər. Önəmli: migration-dan əvvəl backup
- **Idempotent Migration:** Eyni migration-ı ikinci dəfə çalışdırmaq zərər vermir — `IF NOT EXISTS`, `IF EXISTS` yoxlamaları
- **Migration Squash:** Çox kiçik migration-ları birləşdirmək — development-da sürət, lakin production history silinir
- **Schema Version Control:** Laravel migrations, Flyway, Liquibase — versiyalanmış, git-tracked schema
- **Deploy Sequence:** Migration ƏVVƏL, sonra kod deploy. Köhnə kod yeni schema-nı handle etməlidir (rollback mümkün olsun)

## Praktik Baxış

**Interview-da yanaşma:**
- "ALTER TABLE production-da nə qədər vaxt alır?" — table ölçüsünə görə dəqiqələrdən saatlara
- Expand-contract pattern-ı addım-addım izah edin
- `CREATE INDEX CONCURRENTLY`-ni mütləq qeyd edin — "production-da index həmişə CONCURRENTLY"

**Follow-up suallar:**
- "NOT NULL column əlavə etmək niyə risk daşıyır?" — PostgreSQL < 11: full table scan + lock
- "Column adını dəyişdirmək zero-downtime-la necə olur?" — Expand-contract: yeni column + sync + köhnəni sil
- "Migration-ı rollback etmək həmişə mümkündürmü?" — Data silmə, dönüşüm → rollback data itkisi yarada bilər
- "Migration-ı deploy-dan əvvəl mi, sonra mı çalışdırırsınız?" — Əvvəl, lakin backward compatible olmalıdır
- "Replication ilə migration münasibəti?" — Large migration → WAL artır, replica lag arta bilər

**Ümumi səhvlər:**
- "Development-da işlər, production-da da işləyər" demək — table ölçüsü fərqi dramatik
- Index-i `CONCURRENTLY` olmadan yaratmaq
- Rollback-in data itkisinə səbəb ola biləcəyini qeyd etməmək
- Migration-dan sonra kod deploy etmək (köhnə kod yeni schemada crash edər)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Expand-contract pattern-ı bilmək
- `NOT NULL DEFAULT` strategiyasını izah etmək
- "Migration-ı deploy-dan əvvəl çalışdırırıq, yeni kod həm köhnə həm yeni schema-nı başa düşür" demək
- pg_repack / gh-ost kimi toolları bilmək

## Nümunələr

### Tipik Interview Sualı
"1 milyard row olan `orders` tablosuna `tax_amount` adlı NOT NULL column əlavə etmək istəyirsiniz. Downtime olmadan necə edərsiniz?"

### Güclü Cavab
Birbaşa `ALTER TABLE orders ADD COLUMN tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0` çalışdırmaq PostgreSQL 10 və aşağısında bütün row-ları yenidən yazırdı — saatlar çəkərdi, production dayanardı. PostgreSQL 11+ default dəyərli NOT NULL column metadata-da saxlayır, table scan lazım deyil — sürətlidir. Lakin əmin olmaq üçün expand-contract istifadə edərdim:

**Addım 1 (Deploy A):** `ALTER TABLE ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT NULL` — sürətli, lock yox, köhnə kod bu column-u ignore edir.

**Addım 2 (Deploy B — application dəyişikliyi):** Yeni kod `tax_amount`-u hesablayıb yazar. Background job köhnə row-ları batch-lərlə update edir.

**Addım 3 (Deploy C — sonra):** Backfill tamamlandıqda `ALTER TABLE ALTER COLUMN tax_amount SET NOT NULL` — artıq NULL row olmadığından sürətli.

Bu expand-contract pattern-dir: əvvəlcə genişlət (nullable əlavə et), sonra məhdudlaşdır (NOT NULL et).

### Kod Nümunəsi
```php
// Laravel migration — Expand-Contract: addım 1
// Migration 1: Nullable column əlavə et (instantaneous, lock yox)
Schema::table('orders', function (Blueprint $table) {
    $table->decimal('tax_amount', 10, 2)
          ->nullable()
          ->after('total_amount')
          ->comment('Tax amount — backfill in progress');
});
```

```php
// Backfill command — migration-da deyil, ayrı artisan command
class BackfillOrderTaxAmount extends Command
{
    protected $signature = 'orders:backfill-tax-amount';

    public function handle(): int
    {
        $total   = Order::whereNull('tax_amount')->count();
        $bar     = $this->output->createProgressBar($total);
        $updated = 0;

        Order::whereNull('tax_amount')
            ->chunkById(500, function ($orders) use ($bar, &$updated) {
                foreach ($orders as $order) {
                    $order->update([
                        'tax_amount' => round($order->total_amount * 0.18, 2),
                    ]);
                    $bar->advance();
                    $updated++;
                }
                // Database-i nəfəs aldır — production I/O-nu azalt
                usleep(50_000); // 50ms
            });

        $bar->finish();
        $this->info("\nBackfilled {$updated} orders.");
        return Command::SUCCESS;
    }
}
```

```php
// Migration 3: NOT NULL constraint əlavə et (backfill bitdikdən sonra)
// Əvvəlcə NULL olmadığını verify et:
// SELECT COUNT(*) FROM orders WHERE tax_amount IS NULL; → 0 olmalıdır
Schema::table('orders', function (Blueprint $table) {
    $table->decimal('tax_amount', 10, 2)
          ->nullable(false)  // NOT NULL et
          ->change();
});
```

```sql
-- CREATE INDEX CONCURRENTLY — table lock yox
-- YANLIŞ (production-da table lock olur):
CREATE INDEX idx_orders_user_id ON orders(user_id);
-- Böyük tabloda bu saatlarla lock saxlayır!

-- DOĞRU (zero-downtime):
CREATE INDEX CONCURRENTLY idx_orders_user_id ON orders(user_id);
-- Table açıq qalır, yavaş (full scan) amma traffic kəsilmir
-- Əgər yarıda dayanarsa INVALID vəziyyətdə qalır:
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'orders';
-- pg_index.indisvalid = false → DROP edib yenidən yarat

-- REINDEX CONCURRENTLY — mövcud index-i yenilə (bloat/corrupt)
REINDEX INDEX CONCURRENTLY idx_orders_user_id;

-- NOT NULL check — backfill bitdikdən sonra
SELECT COUNT(*) FROM orders WHERE tax_amount IS NULL;
-- 0 olmalıdır, sonra NOT NULL əlavə et
```

```sql
-- Column rename — Expand-Contract ilə
-- Məsələ: user_id → customer_id rename, zero-downtime

-- Step 1: Yeni column əlavə et
ALTER TABLE orders ADD COLUMN customer_id BIGINT;
-- Instantaneous — yeni column nullable

-- Step 2: Trigger ilə hər iki column-u sync et
CREATE OR REPLACE FUNCTION fn_sync_customer_id()
RETURNS TRIGGER AS $$
BEGIN
    -- Yeni column-a köhnədən kopyala
    IF NEW.user_id IS NOT NULL AND NEW.customer_id IS NULL THEN
        NEW.customer_id := NEW.user_id;
    END IF;
    -- Köhnə column-a yenidən kopyala (köhnə kod üçün)
    IF NEW.customer_id IS NOT NULL AND NEW.user_id IS NULL THEN
        NEW.user_id := NEW.customer_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_customer_id
BEFORE INSERT OR UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION fn_sync_customer_id();

-- Step 3: Köhnə data-nı backfill et
UPDATE orders
SET customer_id = user_id
WHERE customer_id IS NULL;

-- Step 4: Application-ı customer_id istifadəsinə keçir (deploy)
-- Köhnə kod user_id yazır → trigger customer_id-ni sync edir
-- Yeni kod customer_id yazır → trigger user_id-ni sync edir

-- Step 5: İndex köçür
CREATE INDEX CONCURRENTLY idx_orders_customer_id ON orders(customer_id);

-- Step 6: Trigger sil, köhnə column sil (növbəti release-də)
DROP TRIGGER trg_sync_customer_id ON orders;
ALTER TABLE orders DROP COLUMN user_id;
```

```yaml
# Flyway migration versioning (Java/Spring proyektlər)
# Fayl adlandırma: V{version}__{description}.sql
# V1__create_orders_table.sql
# V2__add_tax_amount_nullable.sql
# V3__backfill_tax_amount.sql
# V4__make_tax_amount_not_null.sql

# flyway.conf
flyway.url=jdbc:postgresql://localhost:5432/mydb
flyway.user=app_user
flyway.password=${DB_PASSWORD}
flyway.locations=classpath:db/migration
flyway.baselineOnMigrate=true
flyway.outOfOrder=false
flyway.validateOnMigrate=true
```

```bash
# Zero-downtime deployment sequence
# (Blue-Green ya da Rolling deploy ilə)

# 1. Migration ƏVVƏL çalışdır (backward compatible olmalıdır)
php artisan migrate --force
# Yeni column nullable əlavə olundu
# Köhnə kod: column-u ignore edir (bilmir)
# Yeni kod: column-u yazır

# 2. Yeni kod deploy et (rolling — köhnə + yeni pod eyni anda çalışır)
# Köhnə pod: nullable column-u ignore edir — işləyir
# Yeni pod: nullable column-a yazar — işləyir

# 3. Köhnə pod-lar tamamilə aradan qalxdıqda backfill et
php artisan orders:backfill-tax-amount

# 4. NOT NULL migration çalışdır
php artisan migrate --force  # Migration 3

# Risky migration-ları detect et (CI/CD-da)
grep -rn "->change()"         database/migrations/  # Potensial lock
grep -rn "->drop\|dropColumn" database/migrations/  # Data itkisi riski
grep -rn "->integer\|->string" database/migrations/ # Default size check

# Migration test: development-da tam simulate et
php artisan migrate:fresh    # Sıfırdan qurmaq
php artisan migrate:rollback # Rollback test

# Production check — migration gözləyənlər var mı?
php artisan migrate:status
```

```php
// Dangerous migration detector — CI/CD hook
// tests/Feature/MigrationSafetyTest.php
class MigrationSafetyTest extends TestCase
{
    public function test_migrations_are_backward_compatible(): void
    {
        $migrationsPath = database_path('migrations');
        $files = glob("{$migrationsPath}/*.php");

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // NOT NULL column addition without default — risk
            if (preg_match('/->notNull\(\)/', $content) &&
                !preg_match('/->nullable\(\)/', $content) &&
                !preg_match('/->default\(/', $content)) {
                $this->fail("Migration {$file} adds NOT NULL without default — review required");
            }

            // Dropping column — data loss
            if (preg_match('/dropColumn|->drop\(/', $content)) {
                // Warning — not fail, manual review
                $this->addWarning("Migration {$file} drops column — ensure backward compat");
            }
        }
        $this->assertTrue(true); // All checks passed
    }
}
```

### İkinci Nümunə — pg_repack

```bash
# pg_repack — VACUUM FULL-ın lock-suz alternatividir
# Table-ı bloat-dan azad edir, lock olmadan

# Install
apt-get install postgresql-15-repack

# Table-ı yenidən qur (lock olmadan)
pg_repack -h localhost -U postgres -d mydb -t orders

# Index-ləri yenidən qur
pg_repack -h localhost -U postgres -d mydb -t orders --only-indexes

# Bütün database
pg_repack -h localhost -U postgres -d mydb --no-kill-backend

# Nə vaxt lazımdır?
# SELECT pg_size_pretty(pg_total_relation_size('orders')) AS total;
# pg_freespace extension ilə bloat ölçmək
# Bloat > table size 30% → pg_repack düşün
```

## Praktik Tapşırıqlar

- 10M row olan tabloya `CREATE INDEX` vs `CREATE INDEX CONCURRENTLY` çalışdırın, `pg_locks`-dan lock tipini izləyin
- Expand-contract pattern-i tətbiq edin: `user_id` → `customer_id` rename — 5 addımın hamısını edin
- Backfill job yazın: 5M row-u 1000-lik chunk-larla NULL-dan doldurun, production I/O-nu simulate etmək üçün `sleep` əlavə edin
- `ALTER TABLE ADD COLUMN NOT NULL DEFAULT` — PostgreSQL 10 vs 11-də davranış fərqini test edin (`EXPLAIN ANALYZE` ilə)
- CI/CD-a migration safety check əlavə edin: risky migration-ları aşkar edib PR-da warning versin
- Rollback test: `down()` metodu data itkisinə səbəb olan migration yazın, sonra bunun niyə təhlükəli olduğunu izah edin

## Əlaqəli Mövzular
- `04-index-types.md` — `CREATE INDEX CONCURRENTLY` istifadəsi
- `10-database-replication.md` — Böyük migration replica lag-a səbəb olur
- `11-write-ahead-logging.md` — Böyük migration WAL-ı şişirir, checkpoint tezliyinə təsir edir
- `12-mvcc.md` — Migration zamanı dead tuple artması, bloat
