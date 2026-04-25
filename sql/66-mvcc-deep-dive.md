# MVCC Deep Dive (Multi-Version Concurrency Control) (Senior)

## MVCC nedir?

**Multi-Version Concurrency Control** - eyni row-un bir nece versiyasini saxlamaq usulu. Reader-ler oz "snapshot"-larini gorur, writer-ler yeni versiya yaradir. Lock olmadan concurrent oxuma + yazma mumkun olur.

**Esas qaydalar:**
- Reader-ler writer-leri bloklamir
- Writer-ler reader-leri bloklamir
- Yalniz writer-ler bir-birini bloklayir (eyni row-da)

```
Transaction A (READ)         Transaction B (WRITE)
SELECT balance               UPDATE balance = 500
FROM accounts WHERE id = 1   WHERE id = 1
-> 1000 gorur (eski versiya) -> Yeni versiya yaradir
                             COMMIT
SELECT balance               
-> 1000 gorur (REPEATABLE READ-de)
   yaxud 500 (READ COMMITTED-de)
COMMIT
```

---

## Niye lazimdir?

Lock-based concurrency-de problem:
- Read locks writer-leri bloklayir
- Write locks reader-leri bloklayir  
- Throughput azalir, deadlock artir

MVCC-de:
- 1000 concurrent reader + 100 writer islesin - hec biri digerini gozlemir
- Yalniz row-level conflict-de problem (eyni row-u 2 transaction yazmaq isteyirse)

---

## PostgreSQL MVCC Implementation

### Tuple Versiyalama

PostgreSQL-de her row (tuple) gizli sutunlar saxlayir:

| Hidden column | Menasi |
|---------------|--------|
| `xmin` | Bu versiyani yaradan transaction ID |
| `xmax` | Bu versiyani silen/yenileyen TX ID (0 = canli) |
| `cmin` | TX icinde hansi command yaradib |
| `cmax` | TX icinde hansi command silib |
| `ctid` | Fiziki yer (page, offset) |

**Misal:**

```sql
-- Initial state
INSERT INTO accounts (id, balance) VALUES (1, 1000);
-- Tuple: id=1, balance=1000, xmin=100, xmax=0, ctid=(0,1)
```

```sql
-- TX 200: balance = 500
UPDATE accounts SET balance = 500 WHERE id = 1;
-- Eski tuple: id=1, balance=1000, xmin=100, xmax=200  -> "DEAD"
-- Yeni tuple: id=1, balance=500,  xmin=200, xmax=0    -> "LIVE"
```

> **Vacib:** PostgreSQL-de UPDATE = INSERT (yeni versiya) + mark old as dead. **In-place update YOXDUR**.

### Snapshot Isolation

Transaction basliyanda **snapshot** alinir:

```
Snapshot = {
    xmin: 195,           // bu xmin-den asagi - hamisi gorulur
    xmax: 250,           // bu xmax-dan yuxari - hec biri gorulmur
    xip_list: [201, 220] // bu anda icra olunan TX-lar (gorulmez)
}
```

Bir tuple visible olur eger:
- `xmin < snapshot.xmax` (yaranib)
- `xmin not in xip_list` (commit olunub)
- `xmax = 0` yaxud `xmax > snapshot.xmax` yaxud `xmax in xip_list` (silinmemis)

### Read View

Eyni TX icinde 2 SELECT eyni snapshot-i istifade edir (REPEATABLE READ-de):

```sql
BEGIN ISOLATION LEVEL REPEATABLE READ;
SELECT balance FROM accounts WHERE id = 1; -- snapshot alinir
-- Baska TX UPDATE edib COMMIT etse bele:
SELECT balance FROM accounts WHERE id = 1; -- Eyni netice (snapshot-dan)
COMMIT;
```

READ COMMITTED-de her statement yeni snapshot alir.

### CTID ve Heap

```sql
SELECT ctid, xmin, xmax, * FROM accounts WHERE id = 1;
```

```
 ctid  | xmin | xmax | id | balance
-------+------+------+----+---------
 (0,3) | 200  |   0  | 1  |  500
```

`(0,3)` = page 0, item 3. UPDATE-den sonra ctid deyisir (yeni yer).

### HOT (Heap-Only Tuple) Updates

Eger UPDATE yalniz **non-indexed** column-lari deyisirse ve eyni page-de yer varsa:
- Index entry yenilenmir
- Eski tuple → yeni tuple "redirect" pointeri ile baglanir
- Index okunda eski tuple-a gedir, oradan yeniye redirect olur

```sql
UPDATE accounts SET balance = 500 WHERE id = 1;
-- balance index-li deyilse + page-de yer var -> HOT update
-- Index update etmir, suretli
```

**HOT terslerine sebebler:**
- Indexed column UPDATE
- Page dolub (yeni yer yox) -> ferqli page-e
- `fillfactor` 100% (default 100) - elave yer yox

```sql
-- Frequent UPDATE-li table-de fillfactor azalt
ALTER TABLE accounts SET (fillfactor = 80);
-- Yeni page-lerin %20-si elave yer ucundur, HOT update mumkun olur
```

### Bloat Sebebleri

**Bloat** - dead tuple-lerin diskde yer tutmasi:

1. UPDATE/DELETE her zaman yeni dead tuple yaradir
2. VACUUM gec gelirse, dead tuple-ler bo yer kimi yigilir
3. Index-de da bloat olur (her UPDATE index entry yeniler)

**Yoxla:**

```sql
SELECT relname, n_dead_tup, n_live_tup, 
       (n_dead_tup::float / n_live_tup) AS dead_ratio
FROM pg_stat_user_tables
ORDER BY dead_ratio DESC;
```

### VACUUM Zerureti

`VACUUM` dead tuple-leri **mark** edir (silmir, sade istifade icin azad edir):

```sql
VACUUM accounts;        -- dead tuple-leri mark, bos yer azad
VACUUM FULL accounts;   -- table-i tamamile yeniden yazar (LOCK!)
ANALYZE accounts;       -- statistic update (planner ucun)

-- Birge
VACUUM (ANALYZE, VERBOSE) accounts;
```

**autovacuum** - background-da avtomatik isleyir (default ON):

```sql
-- Settings
SHOW autovacuum_vacuum_threshold;       -- 50 (min dead tup)
SHOW autovacuum_vacuum_scale_factor;    -- 0.2 (table-in 20%-i)
-- Trigger: dead_tup > threshold + scale_factor * total_rows
```

---

## InnoDB (MySQL) MVCC Implementation

### Undo Log

PostgreSQL-de yeni versiya yeni yerde yaranir. **InnoDB-de tersi** - row in-place update olunur, eski versiya **undo log**-da saxlanir.

```
Tablespace:
| id=1 | balance=500 (current) | DB_TRX_ID=200 | DB_ROLL_PTR=0xABC |
                                                         |
                                                         v
Undo log:
| id=1 | balance=1000 (eski) | DB_TRX_ID=100 | DB_ROLL_PTR=0xDEF |
                                                         |
                                                         v (daha eski versiya)
```

### Hidden Columns

| Column | Menasi |
|--------|--------|
| `DB_TRX_ID` | 6 byte. Bu row-u son deyisen TX ID |
| `DB_ROLL_PTR` | 7 byte. Undo log entry-ye pointer |
| `DB_ROW_ID` | 6 byte. Yalniz primary key olmasa |

### Read View

InnoDB read view = transaction snapshot. SELECT zamani:

```
m_ids:        active TX-lar (gorunmez)
m_low_limit_id: en boyuk known TX ID + 1
m_up_limit_id:  ilk active TX ID
```

Visibility check (PG-yə oxsar):
- `DB_TRX_ID < m_up_limit_id` -> visible
- `DB_TRX_ID >= m_low_limit_id` -> invisible
- `DB_TRX_ID in m_ids` -> invisible
- Aksi halda undo log-dan eski versiya oxu

### Rollback Segments

Undo log-lar **rollback segment**-lerde saxlanir. ROLLBACK olanda buradan eski deyer geri qaytarilir.

```sql
SHOW ENGINE INNODB STATUS;
-- TRANSACTIONS bolmesinde "History list length" gorulur
-- = oxunmamis undo entry sayi (yuksek olsa = bloat)
```

### MySQL-de Bloat Yoxdur (~Az)

In-place update sebebi ile MySQL-de PostgreSQL kimi VACUUM lazim deyil. Amma:
- Undo log boyumeyə bilər (long-running TX)
- `OPTIMIZE TABLE` arada lazim olur (defrag, stats refresh)

---

## PostgreSQL vs InnoDB MVCC

| Aspect | PostgreSQL | InnoDB |
|--------|-----------|--------|
| UPDATE strategiyasi | INSERT new + mark dead | In-place update |
| Eski versiya yeri | Heap (eyni table) | Undo log |
| Bloat problemi | Buyuk (VACUUM lazim) | Az (undo log az yer) |
| Long TX impact | Bloat, autovacuum bloka | Undo log boyumesi |
| ROLLBACK suretli? | Beli (sade) | Yox (undo apply lazim) |
| READ snapshot | Tuple-larda xmin/xmax | Read view + undo chain |
| Index bloat | Beli (index-de de yeni entry) | Az |

---

## Lock-free Reads Consequences

### Phantom Read (PostgreSQL READ COMMITTED)

```
TX A:                          TX B:
SELECT * FROM orders 
WHERE status='pending';
(5 row gorur)
                               INSERT INTO orders (status) VALUES('pending');
                               COMMIT;
SELECT * FROM orders 
WHERE status='pending';
(6 row gorur - yeni snapshot)
```

REPEATABLE READ-de tek snapshot sebebi ile bu olmur.

### Write Skew (REPEATABLE READ)

```
On-call doctor table: Alice (on=true), Bob (on=true)
Constraint: en azi 1 doctor on-call olmali

TX A: Alice off-call etmek
SELECT COUNT(*) WHERE on=true; -- 2 (snapshot)
UPDATE doctors SET on=false WHERE name='Alice'; -- OK (count >= 2)

TX B: Bob off-call etmek  
SELECT COUNT(*) WHERE on=true; -- 2 (eyni snapshot!)
UPDATE doctors SET on=false WHERE name='Bob'; -- OK (count >= 2)

COMMIT both -> 0 doctor on-call! Constraint pozulur.
```

**Helli:** SERIALIZABLE isolation yaxud `SELECT ... FOR UPDATE`.

---

## Long-Running Transaction Problemi

### xmin Horizon

PostgreSQL-de uzun TX `pg_stat_activity.backend_xmin`-i tutur. autovacuum bu xmin-den eski olan dead tuple-leri **temizleye bilmir** (cunki o TX hele de gorebiler).

```sql
-- Long TX tap
SELECT pid, age(backend_xmin) AS xmin_age, query, state, 
       NOW() - xact_start AS duration
FROM pg_stat_activity
WHERE state = 'active'
ORDER BY xmin_age DESC;
```

**Netice:** 1 saatliq TX -> butun saatda dead tuple yigilir -> bloat artir -> performance dusur.

### Idle in Transaction

```sql
BEGIN;
SELECT 1;
-- 30 dakika gozleyir (idle in transaction)
COMMIT;
```

`idle_in_transaction_session_timeout = '5min'` qoymaq tovsiye olunur:

```sql
ALTER SYSTEM SET idle_in_transaction_session_timeout = '5min';
```

### Laravel-de Helli

```php
// PIS - uzun transaction
DB::transaction(function () {
    $users = User::all(); // 100K user
    foreach ($users as $user) {
        // API call (yavas!)
        Http::post('https://api.example.com', ['email' => $user->email]);
    }
}); // 30 dakika davam edir!

// YAXSI - kicik transaction-lar, async work outside
$users = User::all();
foreach ($users as $user) {
    DB::transaction(function () use ($user) {
        $user->update(['status' => 'processing']);
    });
    
    SendApiRequest::dispatch($user); // queue
}
```

---

## SERIALIZABLE in PostgreSQL: SSI

**Serializable Snapshot Isolation** - lock-suz serializable.

```sql
BEGIN ISOLATION LEVEL SERIALIZABLE;
-- ...
COMMIT;
-- ERROR: could not serialize access due to read/write dependencies among transactions
```

PostgreSQL conflict detection edir (predicate locks). Conflict olduqda bir TX abort olur.

**Laravel-de retry:**

```php
DB::transaction(function () {
    // SERIALIZABLE work
}, attempts: 5); // Laravel deadlock/serialization error-da retry edir
```

---

## MVCC vs Locking

| Aspect | MVCC | Pessimistic Locking |
|--------|------|--------------------|
| Read blocking | Yox | Beli (write lock saxla) |
| Write blocking | Eyni row-da beli | Beli |
| Throughput | Yuksek | Asagi |
| Deadlock | Az | Cox |
| Storage overhead | Beli (versiyalar) | Az |
| Implementation | PostgreSQL, InnoDB | SQL Server (default) |

> SQL Server 2005+ MVCC dest verir (`READ_COMMITTED_SNAPSHOT` ON), amma default OFF-dir.

---

## Laravel Transactions ve MVCC

### Repeatable Read Snapshot

```php
DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
DB::transaction(function () {
    $balance1 = Account::find(1)->balance; // snapshot
    sleep(5);
    $balance2 = Account::find(1)->balance; // eyni snapshot, eyni deyer
});
```

### SELECT FOR UPDATE (Row Lock)

```php
DB::transaction(function () {
    $account = Account::lockForUpdate()->find(1); // row lock
    $account->balance -= 100;
    $account->save();
}); // COMMIT-de lock azad
```

InnoDB-de bu **gap lock** da yarada biler (REPEATABLE READ-de) - phantom read qarsisini alir.

### SKIP LOCKED (Queue Pattern)

```sql
SELECT * FROM jobs 
WHERE status = 'pending' 
ORDER BY id 
LIMIT 1
FOR UPDATE SKIP LOCKED;
```

Laravel queue Redis-i istifade etse bele, DB queue driver bunu istifade edir. Multiple worker eyni job-i alma riski yox.

---

## MVCC Diagnostics

### PostgreSQL

```sql
-- Bloat ratio
SELECT 
    schemaname, tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
    n_dead_tup, n_live_tup,
    round(n_dead_tup::numeric / NULLIF(n_live_tup, 0), 2) AS dead_ratio
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC LIMIT 10;

-- Long TX
SELECT pid, NOW() - xact_start AS duration, query
FROM pg_stat_activity 
WHERE xact_start IS NOT NULL
ORDER BY duration DESC;

-- VACUUM stats
SELECT relname, last_vacuum, last_autovacuum, vacuum_count, autovacuum_count
FROM pg_stat_user_tables;
```

### MySQL

```sql
-- History list (undo log boyu)
SHOW ENGINE INNODB STATUS;
-- "History list length 1234" - 1234 undo entry

-- Long TX
SELECT * FROM information_schema.innodb_trx 
ORDER BY trx_started ASC LIMIT 5;

-- Lock waits
SELECT * FROM performance_schema.data_lock_waits;
```

---

## Interview suallari

**Q: PostgreSQL-de UPDATE niye yeni tuple yaradir?**
A: MVCC implementation. Eski versiya snapshot-larda hele de gorulebiler. UPDATE = INSERT new + mark old dead (xmax set). Index entry-leri de yenilenir (HOT update istisna). Bu sebeble PostgreSQL-de UPDATE-heavy workload bloat yaradir, VACUUM lazimdir.

**Q: HOT update nedir?**
A: Heap-Only Tuple update. Yalniz non-indexed column yenilenirse VE eyni page-de yer varsa, yeni tuple eyni page-de yaranir, index update edilmir, eski tuple yeniye redirect-le baglanir. Suretlidir, bloat azaldir. fillfactor 80-90% qoymaq HOT-i daha cox tetikleyir.

**Q: Long-running transaction MVCC-de niye problem yaradir?**
A: PostgreSQL-de TX `xmin` saxlayir. autovacuum bu xmin-den asagi olan dead tuple-leri temizleye bilmir (TX hele gorebiler). Netice: bloat boyumeye baslayir, performance dusur. Helli: TX qisa saxla, idle_in_transaction_session_timeout qoy, batch isleri kicik chunk-lara bol.

**Q: PostgreSQL SERIALIZABLE necedir?**
A: SSI (Serializable Snapshot Isolation) - lock-suz. Snapshot isolation + predicate lock conflict detection. Conflict tapilanda bir TX abort olur (`could not serialize`). Laravel-de `DB::transaction(fn=>..., attempts: 3)` ile retry. Performance SERIALIZABLE-de digerlerinden asagidir, amma lock-based-den yuksek.

**Q: InnoDB MVCC PostgreSQL-den ne ile ferqlenir?**
A: InnoDB in-place update edir, eski versiya undo log-da saxlanir. Bloat az olur. ROLLBACK undo log oxuyub deyeri geri qaytarir (PG-de sade dead mark). REPEATABLE READ-de gap lock istifade edir (PG-de yox - SSI ile yox edilir). InnoDB-de VACUUM yoxdur, PG-de mecburi.

**Q: SELECT FOR UPDATE MVCC ile nece islayir?**
A: MVCC reader-leri bloklamir, amma FOR UPDATE row-da explicit write lock alir. Diger TX-lar UPDATE/DELETE/FOR UPDATE bloklanir. Amma adi SELECT (lock-suz) MVCC sayesinde davam edir - oz snapshot-larini gorur. Bu sebeble inventory check kimi critical pathda FOR UPDATE lazim.
