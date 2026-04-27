# MVCC (Multi-Version Concurrency Control) (Lead ⭐⭐⭐⭐)

## İcmal
MVCC — eyni data-nın bir neçə versiyasını saxlayaraq readers-ı writers-dan ayırma mexanizmidir. PostgreSQL, MySQL InnoDB, Oracle — hamısı MVCC istifadə edir. "Reads don't block writes, writes don't block reads" — bu MVCC-nin əsas vədi. Lead interview-larda database internals-ın dərini kimi soruşulur.

## Niyə Vacibdir
MVCC-ni başa düşmək transaction isolation, bloat, vacuum, snapshot isolation, deadlock kimi bir çox mövzunun kökündür. İnterviewer bu sualla sizin database-in "niyə belə davranır" sualına cavab verə bilmənizi yoxlayır. MVCC olmadan isolation → serialization → performance crash olardı. Hər ciddli PostgreSQL production problemi — table bloat, slow vacuum, XID wraparound — MVCC-nin yan effektlərindəndir.

## Əsas Anlayışlar

- **MVCC Əsas İdeya:** Hər transaction başlayanda "snapshot" alır — o andan əvvəl commit olmuş data-nı görür. Heç bir lock olmadan. Readers writers-ı gözlətmir, writers readers-ı gözlətmir
- **xmin:** Row-u yaradan transaction-ın XID-si. Bu XID-dən sonra başlayan transaction-lar row-u görə bilər (əgər `xmin` committed-dirsə)
- **xmax:** Row-u silən/update edən transaction-ın XID-si. `0` = aktiv row, dəyər var = silinmiş/update edilmiş (lakin fiziki olaraq hələ orada)
- **ctid:** Row-un fiziki mövqeyi — `(page_number, offset_within_page)`. Update əməliyyatı yeni ctid yaradır
- **Dead Tuple:** Update/delete edilmiş köhnə row versiyası. `xmax` set edilib amma fiziki olaraq silinməyib. Disk tutmaqda davam edir
- **VACUUM:** Dead tuple-ları fiziki olaraq silir, disk space-i "Free Space Map"-ə qaytarır. Bloat-ı azaldır
- **AUTOVACUUM:** Background-da avtomatik vacuum — `pg_stat_user_tables`-dan `n_dead_tup` threshold-u izləyir. Söndürmək çox risklidir
- **Table Bloat:** Çox dead tuple-dan table-ın şişməsi. `Seq Scan` daha çox data oxuyur, index daha böyük, disk daha dolur. High-write table-larda aylıq vacuum taktikası lazımdır
- **Snapshot Isolation:** Transaction başlayanda snapshot alınır — bütün transaction boyunca eyni "dünya" görünür. Başqa transaction-ların yazıları görünmür
- **Transaction ID (XID):** Hər transaction-a ardıcıl 32-bit integer verilir. PostgreSQL-də global counter
- **XID Wraparound:** 2^31 ≈ 2.1 milyard transaction sonra XID sıfırlanır. Bu olmadan "future" transaction-lar "past" kimi görünər, bütün data invisible olar. `VACUUM FREEZE` ilə qarşısı alınır
- **Visibility Rules:** Row görünür əgər: `xmin` committed VƏ (`xmax` 0 YAXUD `xmax` hələ aktiv YAXUD `xmax` aborted-dir)
- **Read Committed:** Hər SQL statement yeni snapshot alır. Bir transaction içindəki iki SELECT fərqli data görə bilər
- **Repeatable Read:** Transaction başlayanda 1 snapshot — bütün transaction boyunca eyni. Digər transaction-ların commit-ləri görünmür
- **HOT (Heap Only Tuple) Update:** Əgər update edilən column heç bir index-də yoxdursa, yeni tuple həmin page-ə yazılır və köhnə tuple HOT chain-ə daxil edilir. Index yenilənmir → daha az I/O
- **Tuple Visibility Cache (pg_xact):** Transaction-ların committed/aborted statusu `pg_xact` SLRU cache-də saxlanır. Visibility check zamanı burada axtarılır
- **Freeze:** Köhnə `xmin` dəyərini "frozen" olaraq işarələmək — XID wraparound riskini sıfırlayır. `VACUUM FREEZE` bunu edir

## Praktik Baxış

**Interview-da yanaşma:**
- "MVCC reads-ı writes-dan ayırır" — bu cümləni xmin/xmax ilə izah edə bilmək lazımdır
- Dead tuple / vacuum bağlantısını qeyd edin: "UPDATE etdikdə köhnə version həmişə orada qalır, VACUUM silir"
- XID wraparound problemini bilmək — production-da critical, shutdown-a səbəb olur

**Follow-up suallar:**
- "VACUUM niyə lazımdır?" — Dead tuple-ları silmək, XID wraparound qarşısını almaq
- "AUTOVACUUM-u söndürmək nə baş verər?" — Table bloat → query yavaşlayır, XID wraparound → database shutdown
- "Snapshot transaction başlayanda götürülür, statement-dən əvvəl mi?" — Isolation level-ə bağlıdır: Read Committed = hər statement, Repeatable Read = transaction başı
- "HOT update nə vaxt mümkün deyil?" — Index-li column update edildikdə
- "Uzun transaction-ların VACUUM-a təsiri?" — Uzun transaction öz snapshot-ından əvvəlki dead tuple-ları VACUUM silə bilmir → bloat

**Ümumi səhvlər:**
- "MVCC = versioning" deyib izah etməmək — xmin/xmax mexanizmini bilmək lazımdır
- VACUUM-u yalnız "temizlik" kimi bilmək — XID wraparound önəmini qeyd etməmək
- HOT update-i bilməmək
- Uzun transaction-ların VACUUM-u bloklamasını bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- xmin/xmax fiziki atributlarını bilmək
- XID wraparound-u production risk kimi izah etmək — "PostgreSQL 2.1B transaction sonra shutdown edir"
- MVCC-nin isolation level-larla necə əlaqəli olduğunu izah etmək
- Uzun transaction-ların dead tuple accumulation-a necə səbəb olduğunu bilmək

## Nümunələr

### Tipik Interview Sualı
"PostgreSQL-də bir row update etdikdə nə baş verir? Köhnə data haraya gedir? VACUUM bunda nə rolu oynayır?"

### Güclü Cavab
PostgreSQL-də UPDATE əslən iki əməliyyatdır: köhnə row-u "məntiqi olaraq silmək" + yeni row yazmaq. Köhnə row fiziki olaraq silinmir — `xmax` update edən transaction-ın XID-ı ilə işarələnir. Yeni row heap-ə yazılır, `xmin` yeni transaction XID-ı olur. Bu "dead tuple"-dur.

Eyni anda çalışan digər transaction-lar öz snapshot-larına görə eski ya yeni versiyonu görürlər — heç bir lock olmadan. Bu MVCC-nin əsas faydası.

VACUUM process-i sonradan bu dead tuple-ları fiziki olaraq silir, boş space-i "free space map"-ə qaytarır. AUTOVACUUM bunu avtomatik edir — ancaq `autovacuum_vacuum_scale_factor` threshold-u keçincə gecikdirir.

High-update workload-da AUTOVACUUM gecikdikdə table bloat baş verir — table ölçüsü şişir, Seq Scan-lar daha çox disk block oxuyur, query-lər yavaşlayır.

### Kod Nümunəsi
```sql
-- MVCC-ni vizualizasiya etmək
-- System column-larını görüntülə
SELECT xmin, xmax, ctid, id, name, balance
FROM accounts
WHERE id = 1;
-- ctid  = (0, 1)  -- page 0, offset 1 (fiziki yer)
-- xmin  = 1234    -- bu transaction ID bu row-u yaratdı
-- xmax  = 0       -- 0 = aktiv row, silinməyib
-- Commit olan xmin-dən sonra başlayan transaction görə bilər

-- Bir UPDATE etdikdən sonra
BEGIN;
UPDATE accounts SET balance = 900 WHERE id = 1;
-- Hələ commit etmədik

-- Başqa session-da:
SELECT xmin, xmax, ctid, id, balance
FROM accounts
WHERE id = 1;
-- Hələ köhnə row görünür (snapshot isolation)
-- ctid dəyişməyib, xmax set edildi

-- Commit etdikdən sonra yeni session:
COMMIT;
SELECT xmin, xmax, ctid, id, balance
FROM accounts WHERE id = 1;
-- Yeni ctid (yeni fiziki yer)
-- Yeni xmin (bu update transaction-ın XID-si)
-- Köhnə row hələ disktədir (dead tuple)!
```

```sql
-- Dead tuple monitoring
SELECT
  relname,
  n_live_tup,
  n_dead_tup,
  ROUND(n_dead_tup * 100.0
        / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_pct,
  last_autovacuum,
  last_vacuum,
  pg_size_pretty(pg_total_relation_size(relid)) AS total_size
FROM pg_stat_user_tables
WHERE n_dead_tup > 100
ORDER BY n_dead_tup DESC;
-- dead_pct > 20% → manual VACUUM düşün
-- last_autovacuum çox köhnədirsə → autovacuum yavaş işləyir

-- Table bloat analizi (daha dəqiq)
SELECT
  schemaname,
  tablename,
  pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total,
  pg_size_pretty(pg_relation_size(schemaname||'.'||tablename))       AS table,
  pg_size_pretty(pg_indexes_size(schemaname||'.'||tablename))        AS indexes,
  n_live_tup,
  n_dead_tup
FROM pg_stat_user_tables
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 20;
```

```sql
-- VACUUM əməliyyatları
VACUUM accounts;                     -- Sadə vacuum — dead tuples sil
VACUUM (VERBOSE) accounts;           -- Ətraflı çıxış
VACUUM (VERBOSE, ANALYZE) accounts;  -- Vacuum + statistics update
VACUUM FULL accounts;                -- Tam yenidən yaz — EXCLUSIVE LOCK! Production-da diqqət

-- Autovacuum threshold ayarı — high-traffic table üçün
ALTER TABLE accounts SET (
  autovacuum_vacuum_scale_factor    = 0.01,  -- 1% dead tuples → vacuum (default 20%)
  autovacuum_analyze_scale_factor   = 0.005, -- 0.5% dəyişiklik → analyze (default 10%)
  autovacuum_vacuum_cost_delay      = 2      -- ms — throttling (default 20)
);
-- Bu yanaşma accounts table-ında daha tez-tez vacuum tetikler

-- XID wraparound riski monitorinqi
SELECT
  datname,
  age(datfrozenxid)                        AS xid_age,
  2147483647 - age(datfrozenxid)           AS xids_until_wraparound,
  CASE
    WHEN age(datfrozenxid) > 1500000000 THEN 'CRITICAL - VACUUM FREEZE NOW!'
    WHEN age(datfrozenxid) > 1000000000 THEN 'WARNING'
    ELSE 'OK'
  END AS status
FROM pg_database
ORDER BY age(datfrozenxid) DESC;
-- age > 1.5 billion → VACUUM FREEZE lazımdır (PostgreSQL özü warning verir)
-- age > 2 billion   → PostgreSQL SHUTS DOWN — READ ONLY MODE!

-- VACUUM FREEZE — XID-ları "frozen" edir, wraparound riskini sıfırlayır
VACUUM FREEZE accounts;
VACUUM FREEZE VERBOSE accounts;  -- Ətraflı çıxış

-- Table-lə frozen XID-i yoxla
SELECT
  relname,
  age(relfrozenxid) AS frozen_xid_age
FROM pg_class
WHERE relkind = 'r'
ORDER BY age(relfrozenxid) DESC
LIMIT 10;
```

```sql
-- MVCC snapshot demo — iki terminal
-- Terminal 1: Transaction başlat, snapshot al
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ;
SELECT balance FROM accounts WHERE id = 1;
-- 1000 görür — snapshot burada alındı

-- Terminal 2: Update et və commit et
UPDATE accounts SET balance = 800 WHERE id = 1;
COMMIT;

-- Terminal 1 (hələ açıq):
SELECT balance FROM accounts WHERE id = 1;
-- HƏLƏ 1000 görür! Çünki snapshot T1 başlayanda alındı
-- REPEATABLE READ: transaction boyunca eyni snapshot
COMMIT;

-- Terminal 1 yeni transaction ilə:
BEGIN;
SELECT balance FROM accounts WHERE id = 1;
-- Artıq 800 görür — yeni snapshot = T2-nin commit-ini görür
COMMIT;

-- READ COMMITTED ilə fərq:
BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED;
SELECT balance FROM accounts WHERE id = 1;  -- 800 görür (T2 commit olub)
-- READ COMMITTED: hər SELECT yeni snapshot alır
```

```python
# PostgreSQL MVCC monitoring — Python skript
import psycopg2

def check_mvcc_health(conn):
    """Table bloat və XID wraparound yoxla"""
    with conn.cursor() as cur:

        # Dead tuple ratio
        cur.execute("""
            SELECT relname,
                   n_dead_tup,
                   n_live_tup,
                   ROUND(n_dead_tup * 100.0
                         / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_pct,
                   last_autovacuum
            FROM pg_stat_user_tables
            WHERE n_dead_tup > 1000
            ORDER BY n_dead_tup DESC
            LIMIT 10
        """)
        tables = cur.fetchall()
        for t in tables:
            if t[3] and t[3] > 20:
                print(f"BLOAT ALERT: {t[0]}: {t[3]}% dead tuples!")

        # XID wraparound riski
        cur.execute("""
            SELECT datname,
                   age(datfrozenxid) AS xid_age
            FROM pg_database
            ORDER BY age(datfrozenxid) DESC
        """)
        for row in cur.fetchall():
            age = row[1]
            if age > 1_500_000_000:
                print(f"XID CRITICAL: {row[0]}: age={age:,}. Run VACUUM FREEZE!")
            elif age > 1_000_000_000:
                print(f"XID WARNING: {row[0]}: age={age:,}")

def monitor_long_transactions(conn, threshold_seconds=300):
    """Uzun açıq transaction-ları tap — VACUUM-u bloklayanlar"""
    with conn.cursor() as cur:
        cur.execute("""
            SELECT pid,
                   usename,
                   now() - xact_start AS duration,
                   query,
                   state
            FROM pg_stat_activity
            WHERE xact_start IS NOT NULL
              AND now() - xact_start > interval '%s seconds'
            ORDER BY duration DESC
        """, (threshold_seconds,))
        for row in cur.fetchall():
            print(f"LONG TX: pid={row[0]}, user={row[1]}, "
                  f"duration={row[2]}, state={row[4]}")
            print(f"  Query: {str(row[3])[:100]}")
```

### İkinci Nümunə — HOT Update

```sql
-- HOT Update demonstrasiyası
CREATE TABLE products (
  id         BIGSERIAL PRIMARY KEY,
  name       TEXT NOT NULL,
  price      DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_products_price ON products(price);

-- price update → HOT MÜMKÜN DEYİL (indexed column)
-- products index-i yenilənir, yeni ctid yaranır
UPDATE products SET price = 99.99 WHERE id = 1;

-- name update → HOT MÜMKÜNDÜr (indexed deyil)
-- Heç bir index yenilənmir, daha az I/O
UPDATE products SET name = 'New Name' WHERE id = 1;

-- HOT update olub-olmadığını yoxla
SELECT relname, n_tup_upd, n_tup_hot_upd,
       ROUND(n_tup_hot_upd * 100.0
             / NULLIF(n_tup_upd, 0), 2) AS hot_upd_pct
FROM pg_stat_user_tables
WHERE relname = 'products';
-- hot_upd_pct yüksək → daha az index maintenance → daha sürətli

-- Praktik nəticə: Tez-tez update edilən, index-li olmayan
-- column-ları ayrı table-a çıxarmaq performansı artıra bilər
```

## Praktik Tapşırıqlar

- `xmin`, `xmax`, `ctid` system column-larını UPDATE-dən əvvəl və sonra müqayisə edin; köhnə dead tuple-un hələ disktə olduğunu `pg_filenode_relation` ilə verify edin
- Yüksək update trafiki yaradın (1000 UPDATE/san), `pg_stat_user_tables`-dan `n_dead_tup` artmasını 30 saniyəlik intervalda izləyin
- VACUUM manual çalışdırın, `n_dead_tup`-un sıfırlandığını görün; `VACUUM FULL` ilə table ölçüsünün kiçildiyini müşahidə edin
- XID wraparound alert: `pg_database`-dən `age(datfrozenxid)` > 500M olan database-ləri tap, `VACUUM FREEZE` tətbiq et
- Uzun açıq transaction simulasiyası: Terminal 1-də `BEGIN` et, Terminal 2-də çox update et, AUTOVACUUM-un niyə işləmədiyini izlə
- REPEATABLE READ vs READ COMMITTED fərqini iki terminalla demo edin: T1 açıqkən T2 commit edsin, hər isolation level-də T1-in nə gördüyünü müqayisə edin

## Əlaqəli Mövzular
- `11-write-ahead-logging.md` — WAL ilə MVCC birgə işləyir; hər dead tuple WAL-da qeydə alınır
- `06-transaction-isolation.md` — MVCC isolation level-ların texniki əsasıdır
- `07-database-deadlocks.md` — MVCC deadlock-u azaldır amma tam aradan qaldırmır
- `05-query-optimization.md` — Table bloat query performance-ını ciddi şəkildə aşağı salır
