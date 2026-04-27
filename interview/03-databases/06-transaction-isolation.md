# Transaction Isolation Levels (Senior ⭐⭐⭐)

## İcmal
Transaction isolation levels — paralel çalışan transaction-ların bir-birindən nə qədər təcrid olunduğunu müəyyən edir. Bu mövzu Senior interview-larda həm "biliklər" (anomaliyaları izah et), həm "praktika" (real bug göstər) bucağından soruşulur. Yalnız adları sıralamaq yetərli deyil — anomaliyanın real dünyada necə göründüyünü izah etmək gözlənilir.

## Niyə Vacibdir
Yanlış isolation level seçimi ya data corruption-a (çox aşağı isolation), ya da performans problemlərinə (çox yüksək isolation) səbəb olur. İnterviewer bu sualla sizin concurrent sistemlərdə real bug-larla üzləşib-üzləşmədiyinizi, trade-off-ları başa düşüb-anladığınızı yoxlayır.

## Əsas Anlayışlar

- **READ UNCOMMITTED**: Ən aşağı isolation — dirty read mümkün; commit edilməmiş dəyişiklikləri görür. Praktikada demək olar ki, istifadə edilmir. PostgreSQL-də texniki olaraq READ COMMITTED kimi davranır.
- **READ COMMITTED**: PostgreSQL default — yalnız commit olmuş dataları görür. Non-repeatable read mümkündür. Əksər OLTP iş yükü üçün uyğundur.
- **REPEATABLE READ**: Eyni transaction içindəki eyni sorğu hər dəfə eyni nəticəni verir. MySQL InnoDB default. Phantom read mümkün (standarta görə), amma PostgreSQL MVCC-i ilə phantom read da qarşısı alınır.
- **SERIALIZABLE**: Ən güclü — sanki transaction-lar ardıcıl icra olunur. PostgreSQL SSI (Serializable Snapshot Isolation) — lock olmadan conflict detection ilə tətbiq edir.
- **Dirty Read**: T1 commit etməmiş datanı T2 oxuyur; T1 rollback edərsə T2 "olmayan" datanı gördü. READ UNCOMMITTED-da mümkün.
- **Non-repeatable Read**: T1 eyni row-u iki dəfə oxuyur; arada T2 update edib commit edir; T1 fərqli nəticə görür. READ COMMITTED-da mümkün.
- **Phantom Read**: T1 range query icra edir; arada T2 yeni row insert edir; T1 yenidən sorğu etdikdə yeni row görünür. REPEATABLE READ-də mümkün (standarta görə).
- **Write Skew**: REPEATABLE READ-də mümkün anomaliya. T1 və T2 eyni şərti oxuyur, hər ikisi öz snapshot-ı əsasında update edir; son vəziyyət constraint-i pozur. Classic: doctor on-call problem.
- **Lost Update**: İki transaction eyni row-u oxuyur, hər ikisi ayrıca yazır — biri digərinin dəyişikliyini silir. Application-level `fetch-compute-write` pattern-ında riski var.
- **MVCC (Multi-Version Concurrency Control)**: PostgreSQL-in concurrency tətbiqi — hər transaction öz snapshot-ını görür. Read-lər write-ları, write-lar read-ləri bloklamır. Snapshot transaction başladığı anda alınır.
- **FOR UPDATE**: Row-ları pessimistic lock ilə bağlayır — concurrent update-dən qoruyur. Digər `FOR UPDATE` transaction-lar gözləyir.
- **FOR SHARE**: Digər transaction-ların read etməsinə icazə verir, lakin update/delete etmələrinə yox. Shared lock.
- **FOR NO KEY UPDATE**: FK reference-ə daxil olmayan update-lər üçün daha zəif lock — daha az deadlock riski.
- **Advisory Locks**: Application-level database lock — `pg_advisory_xact_lock(key)`. Table-lardan asılı deyil, custom mutex kimi.
- **Serializable Snapshot Isolation (SSI)**: PostgreSQL-in SERIALIZABLE tətbiqi. Phantom read-ə və write skew-a qarşı qoruyur, lakin lock olmadan. Conflict detect ediləndə serialization failure (ERROR 40001) verir — retry lazımdır.
- **Performance vs Safety trade-off**: READ COMMITTED (sürətli, az safe) ↔ SERIALIZABLE (yavaş, tam safe). Default: READ COMMITTED + explicit locking where needed.

## Praktik Baxış

**Interview-da yanaşma:**
- Anomaliyaları real nümunə ilə izah edin — "bank transfer, yanlış balance" kimi
- "Default olaraq hansı istifadə edirsiniz?" — READ COMMITTED + explicit locking where needed
- SERIALIZABLE-ı nə vaxt seçdiyinizi konkret söyləyin: financial ledger, inventory check, concurrent quota enforcement

**Follow-up suallar interviewerlər soruşur:**
- "Write skew anomaliyası nədir? Hansı isolation level qarşısını alır?"
- "Lost update-i necə həll edərdiniz?" — FOR UPDATE, MVCC optimistic lock
- "SERIALIZABLE həmişə istifadə etməli deyilsiniz, niyə?" — serialization failure + retry overhead
- "PostgreSQL-in default isolation level-i nədir? MySQL?"
- "MVCC nədir, read-ların write-ları bloklamasına necə mane olur?"
- "Eyni transaction içində eyni sorğunu iki dəfə çalışdırdınız, fərqli nəticə aldınız — nə ola bilər?"

**Ümumi candidate səhvləri:**
- Yalnız 4 isolation level adını saymaq — anomaliyaları izah etməmək
- Write skew-u bilməmək
- "SERIALIZABLE = ən yaxşı seçim, həmişə istifadə et" demək
- FOR UPDATE-in nə etdiyini bilməmək
- READ COMMITTED ilə REPEATABLE READ-in nə vaxt fərqli davrandığını bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Write skew nümunəsi vermək — doctor on-call problem
- SSI-nin locking-siz necə işlədiyini bilmək
- Real layihədən "biz READ COMMITTED + explicit FOR UPDATE istifadə etdik, çünki..." demək
- `pg_stat_activity`-dən lock gözləyən transaction-ları tanımaq

## Nümunələr

### Tipik Interview Sualı
"Bir konfrans otağı rezervasyon sistemi dizayn edirsiniz. İki user eyni anda eyni otağı rezerv etmək istəyir. Hansı isolation level istifadə edərdiniz? Hansı anomaliya riski var?"

### Güclü Cavab
"Bu klassik write skew problemidir. İzah edim:

READ COMMITTED ilə: Hər iki transaction 'otaq boşdur' görür (commit olmuş data). Hər ikisi insert edir — iki rezervasyon yaranır. Constraint pozulur.

REPEATABLE READ ilə: Hər iki transaction eyni snapshot-ı görür. T1 'otaq boşdur' görür, T2 'otaq boşdur' görür. Hər ikisi insert edir — yenə write skew. REPEATABLE READ yeni insert-ləri görə bilmir (phantom read aspekti).

SERIALIZABLE ilə: SSI conflict detect edir — biri commit edəndə digərini rollback edir. Amma serialization failure-lar artır, retry logic lazımdır.

Praktikada mən SELECT...FOR UPDATE istifadə edərdim — daha az overhead, daha anlaşıqlı semantika:
```sql
BEGIN;
SELECT * FROM rooms WHERE id = ? FOR UPDATE; -- Lock alır
-- Boşdursa rezerv et, deyilsə exception at
INSERT INTO reservations ...;
COMMIT;
```
Bu READ COMMITTED ilə işləyir. İkinci transaction gözləyir, birinci commit edəndə davam edir, amma artıq locked. Bu üsul daha az serialization failure yaradır, amma deadlock riski var — lock ordering lazımdır."

### Kod Nümunəsi — Anomaliya Simulasiyası

```sql
-- Non-repeatable Read (READ COMMITTED-da)
-- Transaction A:
BEGIN;
SELECT balance FROM accounts WHERE id = 1;  -- 1000 görür
-- ... işlər ...
SELECT balance FROM accounts WHERE id = 1;  -- 900 görür! (B update etdi)
COMMIT;

-- Transaction B (arada):
BEGIN;
UPDATE accounts SET balance = 900 WHERE id = 1;
COMMIT;

-- REPEATABLE READ-də isə:
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ;
SELECT balance FROM accounts WHERE id = 1;  -- 1000 görür
-- B commit edir...
SELECT balance FROM accounts WHERE id = 1;  -- Hələ 1000 görür (snapshot)
COMMIT;
```

### Kod Nümunəsi — Write Skew (Doctor On-Call)

```sql
-- Write Skew problemi (REPEATABLE READ-də)
-- Ssenari: Minimum 1 doktor on-call olmalıdır. 2 var.

-- Transaction A (Dr. Əli "çıxmaq istəyir"):
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ;
SELECT COUNT(*) FROM on_call WHERE shift_date = CURRENT_DATE;  -- 2 görür
-- 2 > 1 olduğu üçün çıxa bilər deyə düşünür
UPDATE on_call SET active = false WHERE doctor_id = 1 AND shift_date = CURRENT_DATE;
COMMIT;

-- Transaction B (Dr. Murad — eyni anda):
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ;
SELECT COUNT(*) FROM on_call WHERE shift_date = CURRENT_DATE;  -- 2 görür (snapshot)
-- 2 > 1 olduğu üçün çıxa bilər deyə düşünür
UPDATE on_call SET active = false WHERE doctor_id = 2 AND shift_date = CURRENT_DATE;
COMMIT;

-- Nəticə: 0 doktor on-call! Constraint pozuldu.
-- Hər iki transaction öz snapshot-ına görə düzgün qərar verdi, amma birlikdə yanlış nəticə.

-- HƏLL 1: SERIALIZABLE — SSI detect edir
BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE;
SELECT COUNT(*) FROM on_call WHERE shift_date = CURRENT_DATE;
UPDATE on_call SET active = false WHERE doctor_id = ?;
COMMIT;
-- B commit edəndə: ERROR 40001: could not serialize access due to concurrent update
-- Retry lazımdır

-- HƏLL 2: Explicit locking ilə READ COMMITTED (daha az overhead)
BEGIN;
-- Range-i lock et — hamısı üçün
SELECT * FROM on_call WHERE shift_date = CURRENT_DATE FOR UPDATE;
-- İndi yalnız bir transaction davam edə bilər
SELECT COUNT(*) FROM on_call WHERE shift_date = CURRENT_DATE AND active = true;
-- count = 1-dirsə, update etmə; count >= 2 isə, update et
UPDATE on_call SET active = false WHERE doctor_id = ? AND shift_date = CURRENT_DATE;
COMMIT;
```

### Kod Nümunəsi — Laravel + Isolation Level

```php
// FOR UPDATE — pessimistic locking
DB::transaction(function () use ($roomId, $userId, $startTime, $endTime) {
    // Row-u lock et — digər transaction gözləyir
    $conflict = DB::table('reservations')
        ->where('room_id', $roomId)
        ->where('start_time', '<', $endTime)
        ->where('end_time', '>', $startTime)
        ->lockForUpdate()         // SELECT ... FOR UPDATE
        ->exists();

    if ($conflict) {
        throw new RoomNotAvailableException('Room is already booked');
    }

    DB::table('reservations')->insert([
        'room_id'    => $roomId,
        'user_id'    => $userId,
        'start_time' => $startTime,
        'end_time'   => $endTime,
        'created_at' => now(),
    ]);
});

// SERIALIZABLE isolation + retry
function withSerializableTransaction(callable $callback, int $maxRetries = 3): mixed
{
    $attempt = 0;
    while (true) {
        try {
            return DB::transaction(function () use ($callback) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                return $callback();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // PostgreSQL serialization failure: SQLSTATE 40001
            if ($e->getCode() !== '40001' || ++$attempt >= $maxRetries) {
                throw $e;
            }
            // Exponential backoff
            usleep(50000 * (2 ** $attempt)); // 100ms, 200ms, 400ms
        }
    }
}

// Optimistic locking — version column ilə
// version field: hər update-də 1 artır
DB::transaction(function () use ($orderId, $expectedVersion, $newStatus) {
    $updated = DB::table('orders')
        ->where('id', $orderId)
        ->where('version', $expectedVersion)  // Concurrent update-i önlə
        ->update([
            'status'  => $newStatus,
            'version' => $expectedVersion + 1,
        ]);

    if ($updated === 0) {
        throw new OptimisticLockException('Order was modified by another process');
    }
});
```

### Kod Nümunəsi — Monitoring

```sql
-- Mövcud isolation level-ləri gör
SHOW transaction_isolation;
-- Nəticə: read committed

-- Cari session üçün dəyişdir
SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ;

-- Bir transaction üçün
BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE;

-- Lock gözləyən transaction-ları gör
SELECT
    pid,
    state,
    wait_event_type,
    wait_event,
    LEFT(query, 100) AS query_preview,
    now() - query_start AS waiting_for
FROM pg_stat_activity
WHERE wait_event_type = 'Lock'
ORDER BY waiting_for DESC;

-- Lock dependency — kim kimi gözləyir
SELECT
    blocked.pid            AS blocked_pid,
    blocked.query          AS blocked_query,
    blocking.pid           AS blocking_pid,
    blocking.query         AS blocking_query
FROM pg_stat_activity blocked
JOIN pg_stat_activity blocking ON blocking.pid = ANY(pg_blocking_pids(blocked.pid))
WHERE blocked.wait_event_type = 'Lock';
```

### Attack/Failure Nümunəsi — Lost Update

```
Ssenari: E-commerce — iki admin eyni zamanda ürün stokunu azaldır

Kod (application-level fetch-compute-write, locking yox):
1. Admin A: SELECT stock FROM products WHERE id = 1 → 100
2. Admin B: SELECT stock FROM products WHERE id = 1 → 100
3. Admin A: UPDATE products SET stock = 100 - 30 = 70 WHERE id = 1
4. Admin B: UPDATE products SET stock = 100 - 50 = 50 WHERE id = 1
   (A-nın azaltmasını görmür — köhnə dəyərdən hesabladı)
5. Son vəziyyət: stock = 50
   Həqiqi: 100 - 30 - 50 = 20 olmalıydı
   30 ədəd "yoxa çıxdı"!

Həll 1: Atomic update (en sadə)
UPDATE products SET stock = stock - 30 WHERE id = 1;
-- Database əməliyyatı atomikdir — application-level fetch yoxdur

Həll 2: FOR UPDATE
BEGIN;
SELECT stock FROM products WHERE id = 1 FOR UPDATE; -- Lock
-- Həm check, həm update
UPDATE products SET stock = stock - 30 WHERE id = 1;
COMMIT;

Həll 3: Optimistic locking
UPDATE products SET stock = stock - 30, version = version + 1
WHERE id = 1 AND version = ?; -- Beklenen version
-- 0 row affected → başqası dəyişdirdi → retry
```

## Praktik Tapşırıqlar

- İki terminal açın, READ COMMITTED-da non-repeatable read anomaliyasını simulasiya edin
- Doctor on-call write skew-u reproduce edin: iki terminal eyni anda COUNT oxusun, hər ikisi update etsin
- Laravel-də `lockForUpdate()` vs `sharedLock()` fərqini test edin — ikincisi niyə write-ı bloklamır?
- SERIALIZABLE isolation-da serialization failure (`ERROR 40001`) alın, retry logic yazın
- `pg_stat_activity` ilə lock gözləyən transaction-ları monitorinq edin
- Optimistic locking üçün Eloquent modelinə `version` field əlavə edin, concurrent update testini yazın

## Ətraflı Qeydlər

**PostgreSQL vs MySQL isolation fərqləri**:

| Anomaliya        | PostgreSQL READ COMMITTED | PostgreSQL REPEATABLE READ | MySQL REPEATABLE READ |
|-----------------|--------------------------|----------------------------|-----------------------|
| Dirty Read       | Yoxdur                   | Yoxdur                     | Yoxdur                |
| Non-repeatable   | Mümkün                   | Yoxdur                     | Yoxdur                |
| Phantom Read     | Mümkün                   | Yoxdur (MVCC)              | Mümkün (gap lock)     |
| Write Skew       | Mümkün                   | Mümkün                     | Mümkün                |

PostgreSQL REPEATABLE READ-də MVCC sayəsində phantom read da yoxdur — standartdan daha güclüdür.

**`SELECT FOR UPDATE SKIP LOCKED`**: Queue pattern-da çox işlənir — lock alınmış row-ları keç, boş olanı götür. Deadlock riski yoxdur:
```sql
SELECT id FROM jobs
WHERE status = 'pending'
ORDER BY id
FOR UPDATE SKIP LOCKED
LIMIT 1;
```

**Isolation level monitoring**:
```sql
-- Hər session-un isolation level-ini gör
SELECT pid, application_name, state,
       query_start,
       now() - xact_start AS txn_duration,
       LEFT(query, 60) AS query_preview
FROM pg_stat_activity
WHERE xact_start IS NOT NULL
  AND state != 'idle'
ORDER BY txn_duration DESC;
```

**Serialization failure retry üçün best practice**: Retry sonsuz deyil — max attempts + circuit breaker. Retry-da eyni data oxuyursansa cache-dən oku deyil, database-dən yenidən oxu — stale data ilə yenidən uğursuz olarsan.

## Əlaqəli Mövzular
- `02-acid-properties.md` — ACID fundamentals — Isolation prinsipinin özəyi
- `07-database-deadlocks.md` — Yüksək isolation → deadlock riski artır
- `12-mvcc.md` — PostgreSQL-in isolation tətbiqi — snapshot məntiqini izah edir
- `13-optimistic-pessimistic-locking.md` — Isolation level alternativləri
