# VACUUM, Autovacuum & Table Bloat (PostgreSQL)

> **Seviyye:** Advanced ⭐⭐⭐

## MVCC ve dead tuples

PostgreSQL **MVCC** (Multi-Version Concurrency Control) istifade edir. UPDATE/DELETE row-u silmir — **yeni versiya** yaradir, kohne row "dead tuple" qalir.

```sql
-- Misal: bir UPDATE 2 row versiyasi yaradir
UPDATE users SET email = 'new@x.com' WHERE id = 1;
-- Disk-de:
-- (id=1, email='old@x.com', xmax=tx100)  <- dead (silinib)
-- (id=1, email='new@x.com', xmin=tx100)  <- live
```

Dead tuple-lar disk-de qalir, table boyuyur, query yavasir = **table bloat**.

| Termin | Menasi |
|--------|--------|
| **Live tuple** | Aktiv, gorunen row |
| **Dead tuple** | Kohne versiya, hec bir transaction ucun gorunmur |
| **Bloat** | Table/index real lazimi olcuden boyukdur |
| **xmin/xmax** | Row-un yarandigi/silindigi transaction ID |

---

## VACUUM ne edir?

`VACUUM` dead tuple-lari **silmir**, sadece "yeniden istifade ucun azaddir" deye isareler. Free space map (FSM) yenilenir.

```sql
VACUUM;                  -- Butun database (autovacuum kimi)
VACUUM orders;           -- Yalniz orders table
VACUUM ANALYZE orders;   -- VACUUM + statistika yenile
VACUUM VERBOSE orders;   -- Verbose output
VACUUM (VERBOSE, ANALYZE) orders;  -- Yeni sintaksis
```

**Onemli:** Adi `VACUUM` **exclusive lock qoymur** — concurrent SELECT/INSERT/UPDATE-lere icaze verir. Yalniz `SHARE UPDATE EXCLUSIVE` lock alir (ANALYZE, ALTER TABLE-i blok edir).

---

## VACUUM vs VACUUM FULL

| Xususiyyet | VACUUM | VACUUM FULL |
|------------|--------|-------------|
| **Lock** | SHARE UPDATE EXCLUSIVE | ACCESS EXCLUSIVE (her sey blok!) |
| **Disk space** | Geri qaytarmir (reuse ucun isareler) | Geri qaytarir |
| **Table rewrite** | Xeyr | Beli (yeni file yaradir) |
| **Sureti** | Tez | Cox yavas |
| **Production safe?** | Beli | Xeyr (downtime!) |
| **Index** | Saxlayir | Yeniden qurur |

```sql
-- VACUUM FULL: table-i fiziki olaraq yeniden yazir
-- 100GB table -> 100GB free disk space LAZIMDIR (kopya ucun)
VACUUM FULL orders;  -- Production-da ETMA!

-- Alternativ: pg_repack (online, lock yoxdur)
```

---

## Autovacuum daemon

PostgreSQL background-da **autovacuum worker**-lar isledir, dead tuple threshold-u kecen table-lara `VACUUM` + `ANALYZE` edir.

```ini
# postgresql.conf
autovacuum = on                          # Default: on
autovacuum_max_workers = 3               # Paralel worker sayi
autovacuum_naptime = 1min                # Her bir cycle arasi tezlik

# Vacuum threshold formula:
# threshold = autovacuum_vacuum_threshold + autovacuum_vacuum_scale_factor * n_live_tup
autovacuum_vacuum_threshold = 50         # Min dead tuples
autovacuum_vacuum_scale_factor = 0.2     # Table-in 20%-i dead olsa

# Analyze threshold (statistika):
autovacuum_analyze_threshold = 50
autovacuum_analyze_scale_factor = 0.1    # 10%
```

**Misal:** 1M row-lu table-da autovacuum baslayir: `50 + 0.2 * 1_000_000 = 200_050` dead tuple olduqda.

### Per-table tuning

Yuksek-write table-lar (orders, events) ucun daha aqressiv setting:

```sql
ALTER TABLE orders SET (
    autovacuum_vacuum_scale_factor = 0.05,  -- 5%-de baslat
    autovacuum_vacuum_threshold = 1000,
    autovacuum_analyze_scale_factor = 0.02
);
```

---

## Bloat detection queries

```sql
-- Dead tuple statistikasi
SELECT 
    schemaname, relname,
    n_live_tup, n_dead_tup,
    ROUND(n_dead_tup::numeric / NULLIF(n_live_tup, 0) * 100, 2) AS dead_pct,
    last_vacuum, last_autovacuum,
    last_analyze, last_autoanalyze
FROM pg_stat_user_tables
WHERE n_dead_tup > 1000
ORDER BY dead_pct DESC NULLS LAST;
```

```sql
-- Table size (heap + indexes + toast)
SELECT 
    relname,
    pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
    pg_size_pretty(pg_relation_size(relid)) AS heap_size,
    pg_size_pretty(pg_indexes_size(relid)) AS indexes_size
FROM pg_stat_user_tables
ORDER BY pg_total_relation_size(relid) DESC
LIMIT 10;
```

```sql
-- Index bloat (pgstattuple extension lazimdir)
CREATE EXTENSION pgstattuple;

SELECT 
    indexname,
    pg_size_pretty(real_size::bigint) AS real_size,
    pg_size_pretty(extra_size::bigint) AS bloat_size,
    ROUND(extra_ratio::numeric, 2) AS bloat_pct
FROM pgstatindex('idx_orders_status'::regclass);
```

---

## ANALYZE ve planner statistics

`ANALYZE` table-dan sample goturur, `pg_statistic`-de saxlayir. Planner bunu istifade edir (selectivity, row count estimates).

```sql
ANALYZE orders;
ANALYZE orders (status, created_at);  -- Yalniz bele sutunlar

-- Default sample size: 30k row * default_statistics_target (100)
-- Boyuk table-da artir:
ALTER TABLE orders ALTER COLUMN status SET STATISTICS 1000;
```

**Pis statistika = pis plan**. EXPLAIN-de `rows=1` yazir amma realda 1M row-dur — bu statistika kohnedirsa yaranir.

---

## Transaction ID wraparound (xid wraparound horror)

PostgreSQL transaction ID **32-bit** (~2 milyard). Wraparound olarsa **data itkisi**!

```sql
-- Datbase-in xid yasini yoxla
SELECT datname, age(datfrozenxid) FROM pg_database ORDER BY age DESC;

-- Table-larin xid yasini yoxla
SELECT relname, age(relfrozenxid) FROM pg_class 
WHERE relkind = 'r' ORDER BY age DESC LIMIT 10;
```

**Freeze**: kohne row-larin xmin-ni `FrozenTransactionId` ile evez edir, beleliklerle wraparound problemi yox olur.

```ini
autovacuum_freeze_max_age = 200000000   # 200M xid-de mecburi vacuum
vacuum_freeze_min_age = 50000000
```

Eger xid wraparound yaxinlasirsa (yas > 1.5B), PostgreSQL **read-only** rejime kecer. Onun qarsisini almaq ucun:

```sql
VACUUM FREEZE orders;  -- Mecburi freeze
```

---

## Long-running transaction-lar autovacuum-u blok edir

Uzun transaction `xmin horizon`-u geride saxlayir — autovacuum dead tuple-lari sile bilmir (cunki bu kohne tx hele bunlari gore biler).

```sql
-- Uzun-suren transaction-lari tap
SELECT pid, usename, state, 
       now() - xact_start AS xact_age,
       now() - query_start AS query_age,
       query
FROM pg_stat_activity
WHERE state != 'idle'
  AND xact_start < now() - INTERVAL '5 minutes'
ORDER BY xact_age DESC;

-- Lazim olarsa kill et
SELECT pg_terminate_backend(12345);
```

**Anti-pattern:** Laravel-de uzun transaction:

```php
// PIS: Transaction icinde xarici API call (saatlerle aca biler)
DB::transaction(function () {
    $order = Order::create([...]);
    Http::post('https://payment.gateway/charge', [...]);  // 30 san wait!
});

// YAXSI: Transaction-i qisa saxla, payment-i transaction xaricinde
$order = DB::transaction(fn() => Order::create([...]));
$result = Http::post('https://payment.gateway/charge', [...]);
```

---

## pg_repack — online VACUUM FULL alternativi

`pg_repack` extension table-i **lock olmadan** rebuild edir. Trigger ve temporary table istifade edir.

```bash
# Install
sudo apt install postgresql-15-repack

# Repack table (online, no lock)
pg_repack -d myapp -t orders

# Repack only indexes
pg_repack -d myapp -t orders --only-indexes
```

**Necedir:** yeni table yaradir, trigger ile yeni yazilari ora kopyalayir, kohne data-ni transfer edir, sonra atomik swap edir.

`pgcompacttable` — daha sade (Perl script), trigger istifade etmir, UPDATE ile data-ni table-in basina kocurur. Yavas, amma extension lazim deyil.

---

## fillfactor ve HOT updates

`fillfactor` — page-in nece %-i yazma vaxti dolur. Default 100 (heap), 90 (index).

```sql
-- 80%-e qoy: 20% bos space UPDATE-ler ucun
ALTER TABLE orders SET (fillfactor = 80);

-- VACUUM FULL ile yeniden yaz (yeni fillfactor tetbiq olunsun)
VACUUM FULL orders;
```

**HOT (Heap-Only Tuple) update**: eger UPDATE indexed olmayan sutunu deyisirse VE yeni row eyni page-de yer tapirsa, index-leri yenilemir, daha az dead tuple yaranir.

```sql
-- HOT update statistikasi
SELECT relname, n_tup_upd, n_tup_hot_upd,
       ROUND(100.0 * n_tup_hot_upd / NULLIF(n_tup_upd, 0), 2) AS hot_pct
FROM pg_stat_user_tables
ORDER BY n_tup_upd DESC;
-- HOT % yuksek olmalidir (50%+ yaxsi)
```

---

## Laravel scheduler ile periodic VACUUM

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Boyuk yazma table-larini her gece manual analyze
    $schedule->call(function () {
        DB::statement('VACUUM ANALYZE orders');
        DB::statement('VACUUM ANALYZE order_items');
    })->dailyAt('03:00');
    
    // Bloat monitoring (Slack alert)
    $schedule->call(function () {
        $bloated = DB::select("
            SELECT relname, n_dead_tup,
                ROUND(n_dead_tup::numeric / NULLIF(n_live_tup, 0) * 100, 2) AS pct
            FROM pg_stat_user_tables
            WHERE n_dead_tup > 100000
              AND n_dead_tup::float / NULLIF(n_live_tup, 0) > 0.3
        ");
        
        if (count($bloated) > 0) {
            Notification::route('slack', config('services.slack.ops'))
                ->notify(new TableBloatAlert($bloated));
        }
    })->hourly();
}
```

---

## Troubleshooting checklist

| Symptom | Sebeb | Hell |
|---------|-------|------|
| Table boyuyur, row sayi artmir | Bloat | Autovacuum tuning, pg_repack |
| Query plan sehv | Kohne stats | `ANALYZE table` |
| Autovacuum islemir | Long-running tx | tx-leri tap, kill et |
| `database is shut down` | Xid wraparound | `VACUUM FREEZE` (offline) |
| HOT update az | Index column-u UPDATE olunur | Schema redesign |
| Disk dolur | Bloat + index bloat | pg_repack + reindex |

---

## REINDEX — index bloat ucun

Index-ler de bloat olur (xususile B-tree, cox UPDATE/DELETE-de).

```sql
-- Index-i yeniden qur (ACCESS EXCLUSIVE lock!)
REINDEX INDEX idx_orders_status;
REINDEX TABLE orders;

-- PostgreSQL 12+: CONCURRENTLY (lock yoxdur)
REINDEX INDEX CONCURRENTLY idx_orders_status;
REINDEX TABLE CONCURRENTLY orders;
```

---

## Interview suallari

**Q: VACUUM ile VACUUM FULL arasinda esas ferq nedir?**
A: Adi `VACUUM` dead tuple-lari "yeniden istifade ucun azad" deye isareler, exclusive lock qoymur, disk-i geri qaytarmir. `VACUUM FULL` ise table-i tamam yeniden yazir, ACCESS EXCLUSIVE lock qoyur (butun query-leri blok edir!), disk-i geri qaytarir, amma 2x disk space lazimdir. Production-da `VACUUM FULL` istifade etme — `pg_repack` istifade et.

**Q: Autovacuum table-da niye islemir?**
A: 3 esas sebeb: 1) Long-running transaction `xmin horizon`-u geride saxlayir — `pg_stat_activity`-den kohne tx-leri tap, kill et. 2) Per-table autovacuum disable olunub: `ALTER TABLE x SET (autovacuum_enabled = false)` ile yoxla. 3) Threshold cox boyukdur (default 20%). Yuksek-write table-da `autovacuum_vacuum_scale_factor`-u 0.05-e qoy.

**Q: Transaction ID wraparound nedir, niye tehlikelidir?**
A: PostgreSQL 32-bit xid istifade edir (~2 milyard transaction). Eger autovacuum row-lari "freeze" etmirse, xid biter — database **read-only** rejime kecer (data itkisi qarsisi). `pg_stat_user_tables`-den `age(relfrozenxid)`-i monitorinq et. `autovacuum_freeze_max_age` (default 200M) catdiqda autovacuum mecburi run-i edir.

**Q: HOT update nedir ve niye vacibdir?**
A: Heap-Only Tuple update — eger UPDATE-de heç bir indexed sutun deyismirse VE yeni row versiyasi eyni page-de yer tapirsa, PostgreSQL index-i yenilemir, kohne row-u "redirect" ile birlesdirir. Bu daha az bloat, daha tez UPDATE demekdir. `fillfactor=80` qoymaq, page-de bos yer saxlamaq HOT update probability-ni artirir. Hot_pct 50%+ olmalidir.

**Q: Production-da bir table 100GB olub, amma realda 30GB data-si var. Ne edersen?**
A: 1) `pgstattuple`-den bloat-i tesdiq et. 2) `VACUUM FULL` ETMA (downtime). 3) `pg_repack -d db -t table` istifade et — online, lock yoxdur. 4) Index-leri ayrica `REINDEX CONCURRENTLY` ile yeniden qur. 5) Sebebini tap: long-running tx, kohne autovacuum settings, cox UPDATE-edilen indexed sutun. 6) Per-table autovacuum aqressivlesdir.
