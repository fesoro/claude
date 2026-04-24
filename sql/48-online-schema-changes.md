# Online Schema Changes (Zero-Downtime DDL)

> **Seviyye:** Expert ⭐⭐⭐⭐

## Niye DDL tehlikelidir?

`ALTER TABLE` adi halda **table lock** qoyur — production-da read/write bloklanir, application **down** olur. 100M row-lu table-da `ALTER` saatlerle aca biler.

Replication-da daha pis: master-de DDL biter, replica-da hele icra olunur — **replication lag** boyuyur, read-replica-lar kohnelir.

| DDL | MySQL (default) | PostgreSQL (default) |
|-----|-----------------|----------------------|
| ADD COLUMN (nullable, no default) | INSTANT (8.0+) | INSTANT |
| ADD COLUMN (with default) | INSTANT (8.0.12+) | INSTANT (PG 11+) |
| DROP COLUMN | INPLACE (lock) | INSTANT (logical) |
| RENAME COLUMN | INPLACE | INSTANT |
| CHANGE COLUMN TYPE | COPY (rebuild!) | REWRITE (cox vaxt) |
| ADD INDEX | INPLACE (read-allowed) | LOCK (CONCURRENTLY-siz) |
| ADD FOREIGN KEY | INPLACE | LOCK (validation) |
| ADD CHECK | LOCK | LOCK (NOT VALID-siz) |

---

## MySQL ALTER TABLE algorithms

MySQL 5.6+ `ALGORITHM` ve `LOCK` parametri ile DDL davranisini idare edir.

```sql
ALTER TABLE orders 
    ADD COLUMN tracking_no VARCHAR(50),
    ALGORITHM=INPLACE,    -- COPY etmir, table file-i deyisir
    LOCK=NONE;            -- Read/write icaze ver
```

| Algorithm | Necedir | Lock |
|-----------|---------|------|
| `COPY` | Yeni table yaradir, data kopyalayir, swap edir | TABLE LOCK (read-only) |
| `INPLACE` | Movcud table-i deyisir | METADATA LOCK (qisa) |
| `INSTANT` | Yalniz metadata deyisir, data toxunulmur | Anlik |

| LOCK options | Effect |
|--------------|--------|
| `LOCK=NONE` | Concurrent read+write |
| `LOCK=SHARED` | Read-yes, write-no |
| `LOCK=EXCLUSIVE` | Hec ne |
| `LOCK=DEFAULT` | Hansi mumkundurse en az |

```sql
-- INSTANT (MySQL 8.0+): demek olar her sey aninda
ALTER TABLE orders ADD COLUMN status VARCHAR(20) DEFAULT 'pending', ALGORITHM=INSTANT;

-- INSTANT istifade oluna bilenler (8.0.29+):
-- - ADD COLUMN (any position 8.0.29+)
-- - DROP COLUMN
-- - ADD/DROP virtual column
-- - RENAME COLUMN
-- - SET/DROP DEFAULT

-- INSTANT istifade OLUNA BILMEYENLER:
-- - Column type deyismek
-- - Indexed column deyismek
-- - PRIMARY KEY deyismek
```

---

## pt-online-schema-change (Percona)

Trigger-based: yeni table yaradir, trigger ile dual-write edir, kohne data-ni copy edir, atomic swap.

```bash
pt-online-schema-change \
    --alter "ADD COLUMN tracking_no VARCHAR(50)" \
    D=myapp,t=orders \
    --execute
```

**Plus:** Hemiseki MySQL-de isleyir (5.5+, 8.0). Read replica-larda manual run lazim deyil.

**Minus:**
- Trigger overhead (her INSERT/UPDATE/DELETE 2x is gorur)
- Foreign key isleyir, amma murekkebdir
- Disk space 2x lazimdir (yeni table)
- Replica-da delay yarada biler

---

## gh-ost (GitHub) — triggerless

gh-ost binlog-dan oxuyur, trigger istifade etmir. Ozu bir replica kimi davranir.

```bash
gh-ost \
    --user=ghost \
    --password=xxx \
    --host=db-master \
    --database=myapp \
    --table=orders \
    --alter="ADD COLUMN tracking_no VARCHAR(50)" \
    --max-load=Threads_running=25 \
    --critical-load=Threads_running=1000 \
    --chunk-size=1000 \
    --throttle-control-replicas="db-replica-1,db-replica-2" \
    --execute
```

**Ustunlukleri:**
- Trigger yoxdur — heç bir overhead production-a
- Throttle: replica lag-i goruse, slow olar
- Pause/resume mumkundur
- Cut-over moment teyin oluna biler (off-peak)

| Tool | Trigger | Replica-aware | Sureti | Tovsiye |
|------|---------|---------------|--------|---------|
| pt-osc | Beli | Manual | Tez | Kicik table-lar |
| gh-ost | Xeyr | Beli (auto throttle) | Orta | Boyuk table-lar (>10M) |
| LHM | Beli | Manual | Tez | Ruby/Rails |
| Native ALTER | — | Async | INSTANT-da en tez | MySQL 8.0+ ADD/DROP |

---

## PostgreSQL — non-blocking DDL

PostgreSQL bir cox `ALTER`-i artiq lock-suz edir.

```sql
-- INSTANT (metadata only, PG 11+):
ALTER TABLE orders ADD COLUMN status VARCHAR(20) DEFAULT 'pending';
-- PG 11+: default-li column elave etmek INSTANT (kohnelerde rewrite idi)

-- DROP COLUMN: logical only, fiziki silinmir (sonra VACUUM FULL/pg_repack lazim)
ALTER TABLE orders DROP COLUMN old_field;
```

**Tehlikeli olanlari ehmal et:**

```sql
-- TEHLIKELI: type deyismek (rewrite!)
ALTER TABLE orders ALTER COLUMN status TYPE TEXT;
-- 100M row-da saatlerle ACCESS EXCLUSIVE lock!

-- TEHLIKELI: NOT NULL constraint (full table scan, lock)
ALTER TABLE orders ALTER COLUMN status SET NOT NULL;
```

### NOT VALID + VALIDATE pattern

PostgreSQL CHECK ve FK-da `NOT VALID` ile validation-u sonraya tehir edir:

```sql
-- Adim 1: Constraint elave et, amma movcud row-lari yoxlama (qisa lock)
ALTER TABLE orders 
    ADD CONSTRAINT chk_amount_positive CHECK (amount > 0) NOT VALID;

-- Adim 2: Validation ayri (SHARE UPDATE EXCLUSIVE, read/write icaze verir)
ALTER TABLE orders VALIDATE CONSTRAINT chk_amount_positive;
```

Yeni INSERT/UPDATE-ler hemen yoxlanir, amma kohne data-da yox.

### CREATE INDEX CONCURRENTLY

```sql
-- LOCK qoyur (write blok):
CREATE INDEX idx_orders_status ON orders(status);

-- Lock qoymur (read+write icaze):
CREATE INDEX CONCURRENTLY idx_orders_status ON orders(status);
-- Daha yavas (2x scan), amma production-safe
```

> **Diqqet:** `CONCURRENTLY` transaction icinde isleye bilmir. Eger sehv olarsa index `INVALID` qalir — `DROP INDEX CONCURRENTLY` edip yeniden yaratmaq lazimdir.

```sql
-- Invalid index-leri tap
SELECT indexrelid::regclass FROM pg_index WHERE NOT indisvalid;
```

---

## Expand-Contract pattern (universal)

En etibarli zero-downtime schema change pattern-i.

**Senariy:** `users.name` -> `users.full_name` rename.

### 1. Expand (yeni schema elave et)

```php
// Migration 1: Yeni column nullable yarat
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('full_name')->nullable()->after('name');
    });
}
```

### 2. Dual-write (eski ve yeni yazilir)

```php
// Application kodu - her ikisini yazir
class User extends Model {
    public function setNameAttribute($value) {
        $this->attributes['name'] = $value;
        $this->attributes['full_name'] = $value;
    }
}
// Deploy bu kodu
```

### 3. Backfill (kohne data-ni copy)

```php
// Job: batch-le copy et
User::whereNull('full_name')
    ->lazyById(1000)
    ->each(fn($u) => $u->update(['full_name' => $u->name]));
```

### 4. Migrate reads (yeni column-dan oxu)

```php
// Application-i yeni column oxumaga deyis, deploy
$users = User::select('id', 'full_name')->get();
```

### 5. Contract (kohnenin silinmesi)

```php
// Migration: kohne column-u sil
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('name');
});
```

| Adim | Risk | Geri qaytarma |
|------|------|--------------|
| Expand | Yox (additive) | Column drop |
| Dual-write | Yox | Kod revert |
| Backfill | Az | Background job stop |
| Migrate reads | Orta | Eski code-a qayit |
| Contract | Yuksek (irreversible) | Column geri qayda biler, amma data itib |

---

## Laravel migration anti-patterns

```php
// ANTI-PATTERN 1: Boyuk table-da column type deyismek
Schema::table('orders', function ($t) {
    $t->text('status')->change();  // 100M row -> hours of lock!
});

// ANTI-PATTERN 2: Migration-da Eloquent
Order::all()->each(fn($o) => $o->update([...]));  // Memory blow!

// ANTI-PATTERN 3: NOT NULL bir merhelede
$table->string('email')->nullable(false)->change();
// Eger NULL row-lar varsa - error. Yoxsa - lock!

// ANTI-PATTERN 4: Foreign key add-i boyuk table-da
$table->foreign('user_id')->references('id')->on('users');
// Validation full table scan!
```

### Duzgun pattern

```php
// 1. Concurrent index (raw SQL, transaction xaricinde)
public function up(): void
{
    DB::statement('CREATE INDEX CONCURRENTLY idx_orders_status ON orders(status)');
}

// Disable transaction wrapping (Laravel 9+):
public $withinTransaction = false;
```

---

## Replication-aware migrations

MySQL row-based replication: DDL replicate olunur, amma binlog-da event olur.

**MySQL strategy:**
1. Replica-larda DDL evvelce manual edin (read-only mode)
2. Master-de DDL et — replica-larda artiq mevcuddur, sade olarsa "noop"
3. Ya da `gh-ost` istifade et — replica-aware

**PostgreSQL logical replication:**
- DDL **replicate olmur**! Subscriber-de manual lazimdir
- `CREATE PUBLICATION` yenilemek lazim ola biler

```sql
-- PostgreSQL: yeni column publication-a elave
ALTER PUBLICATION my_pub SET TABLE orders (id, status, amount, new_col);
```

---

## Boyuk table migration playbook

100M+ row-lu `events` table-da yeni column elave etmek + backfill:

```bash
# 1. Off-peak vaxt sec (gece 02:00)
# 2. Replication lag baseline-i isar et

# 3. Schema deyisikliyi (expand)
mysql> ALTER TABLE events ADD COLUMN tenant_id BIGINT NULL,
       ALGORITHM=INSTANT;  # MySQL 8.0+
# Ya da:
gh-ost --alter "ADD COLUMN tenant_id BIGINT NULL" --execute

# 4. Backfill (Laravel job, throttled)
php artisan backfill:tenant-id --chunk=1000 --sleep-ms=100
```

```php
// app/Console/Commands/BackfillTenantId.php
DB::table('events')
    ->whereNull('tenant_id')
    ->orderBy('id')
    ->chunkById(1000, function ($events) {
        $ids = $events->pluck('id');
        DB::table('events')
            ->whereIn('id', $ids)
            ->update(['tenant_id' => DB::raw('user_id / 1000000 + 1')]);
        
        // Replication lag yoxla, lazim olarsa pause
        $lag = DB::select("SHOW SLAVE STATUS")[0]->Seconds_Behind_Master ?? 0;
        if ($lag > 30) sleep(5);
    });
```

```sql
-- 5. Validation: hec NULL qalmadi?
SELECT COUNT(*) FROM events WHERE tenant_id IS NULL;

-- 6. NOT NULL constraint (NOT VALID pattern PG-de):
ALTER TABLE events MODIFY COLUMN tenant_id BIGINT NOT NULL;
```

---

## Interview suallari

**Q: MySQL-de boyuk table-da column elave etmek lazimdir. Production-da nece edersen?**
A: 1) Yoxla MySQL 8.0+ ALGORITHM=INSTANT mumkundurmu (yeni column at end, default deyer). 2) Eger beli — sade `ALTER ... ALGORITHM=INSTANT, LOCK=NONE`. 3) Eger yox (type change, indexed column) — `gh-ost` ve ya `pt-online-schema-change` istifade et. 4) gh-ost daha yaxsidir cunki triggersiz, throttle replica-aware. 5) Off-peak vaxt sec, monitorinq et.

**Q: PostgreSQL-de NOT NULL constraint elave etmek niye tehlikelidir?**
A: `ALTER TABLE x ALTER COLUMN y SET NOT NULL` ACCESS EXCLUSIVE lock alir, butun row-lari scan edir. Boyuk table-da uzun sure butun query-leri blok edir. Workaround: 1) `ADD CONSTRAINT chk_y_not_null CHECK (y IS NOT NULL) NOT VALID` (qisa lock), 2) `VALIDATE CONSTRAINT` (lock-suz scan), 3) PG 12+: bunu sonra real `SET NOT NULL`-a cevirmek olar — planner CHECK-i ekvivalent gorur.

**Q: gh-ost ile pt-online-schema-change arasinda hansini secersen ve niye?**
A: Boyuk table-larda (10M+) gh-ost — cunki triggersiz, production yuku artmir, throttle replica lag-a gore avtomatik islayir, cut-over kontrol oluna bilir. Kicik table-lar (kicik downtime qebul olunur) ucun pt-osc daha sade. gh-ost-un cetinligi: GTID-based replication lazim olur ve setup pt-osc-den murekkebdir.

**Q: Expand-contract pattern-de en risikli adim hansidir?**
A: **Contract** (kohne sutunu silmek) — irreversible. Eger application-da haradasa hele kohne sutun istifade olunursa, error verecek. Buna gore: 1) Contract-i 1-2 hefte gozle (logging, monitoring), 2) Production logs-da kohne sutuna erise yoxla, 3) Telescope/Pulse ile query analyze et, 4) Backup goturak sonra DROP COLUMN.

**Q: CREATE INDEX CONCURRENTLY niye transaction icinde isleye bilmir?**
A: `CONCURRENTLY` 2-fazli scan edir (movcud row-lari index-le, sonra concurrent yazilari catdir). Bunlarin arasinda commit point lazimdir — beleliklerle istifade edilen MVCC snapshot deyisir. Transaction icinde tek snapshot olur, bele DDL mumkun deyil. Eger ugursuz olarsa index `indisvalid=false` qalir — manual `DROP INDEX CONCURRENTLY ... ; CREATE INDEX CONCURRENTLY ...` lazimdir.
