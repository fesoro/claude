# ACID Properties (Middle ⭐⭐)

## İcmal

ACID — database transaction-larının əsas zəmanətlərini təsvir edən 4 xüsusiyyətin abbreviaturasıdır. Bu sual interview-da demək olar ki, həmişə çıxır, çünki database reliability-nin əsasını təşkil edir. Yalnız hərfləri ezberləmək yetərli deyil — real layihədə hər xüsusiyyətin nə vaxt kritik olduğunu, isolation level-ların performans trade-off-larını, anomaliyaların real nümunələrini bilmək lazımdır. Senior üçün: İzolasyon anomaliyaları, distributed ACID, WAL mexanizmi.

## Niyə Vacibdir

Bank tətbiqindən tutmuş e-commerce-ə qədər hər ciddi sistemdə data integrity əsas problemdir. İnterviewer bu sualla sizin transaction mexanizmini başa düşüb-anlamadığınızı, isolation level-ların performans trade-off-larını bilibməmənizi, və real bug-ları necə debug etdiyinizi yoxlayır. "ACID nədir?" sualına cavab verə bilmək Junior üçün kifayətdir; Senior üçün isolation level-lar, deadlock-lar, WAL, MVCC, distributed ACID lazımdır.

## Əsas Anlayışlar

### Atomicity (Atomluq)

- Transaction ya **tamamilə uğurlu** olur, ya da **tamamilə geri alınır**. Yarımçıq vəziyyət olmur.
- "All or nothing" — bank transfer: Alice-dən çıx + Bob-a əlavə et. Əgər birincisi uğurlu, ikincisi xətalı olsa → ikisi də geri alınır.
- **Mexanizm**: WAL (Write-Ahead Log) — dəyişikliklər əvvəlcə log-a yazılır, sonra data file-a. Crash olarsa log-dan rollback.
- **Savepoint**: Transaction içindəki partial rollback nöqtəsi. `SAVEPOINT sp1; ROLLBACK TO sp1;`
- PHP/Laravel-də: `DB::transaction()` — exception → automatic rollback.

### Consistency (Tutarlılıq)

- Transaction bitdikdən sonra database məlum qaydalar (constraints, triggers, cascades) çərçivəsindən kənara çıxmır.
- Valid state-dən valid state-ə keçir.
- **Diqqət**: Consistency = constraint-lərin qorunması (NOT NULL, UNIQUE, FK, CHECK). Data corruption-ın olmaması deyil — bu Atomicity-dir.
- Nümunə: `balance >= 0` CHECK constraint. Mənfi bakiyəyə girmə cəhdi → transaction rollback.
- **Application-level consistency**: Bəzi consistency qaydaları application-da (service layer) enforce olunur.

### Isolation (İzolyasiya)

- Paralel çalışan transaction-lar bir-birini görməməlidir — sanki serialized icra olunur.
- **Praktikada**: Tam isolation = SERIALIZABLE, amma çox yavaş. Daha az isolation = daha sürətli amma anomaliyalar.
- 4 isolation level var: READ UNCOMMITTED → READ COMMITTED → REPEATABLE READ → SERIALIZABLE.
- **MVCC** (Multi-Version Concurrency Control): PostgreSQL-in default yanaşması — hər transaction öz snapshot-ını görür, read-lər write-ları bloklamır.

### Durability (Dayanıqlılıq)

- Commit olan transaction disk failure, power outage olsa belə itirilmir.
- **WAL (Write-Ahead Log)**: Commit gəlmədən əvvəl log diska `fsync` ilə yazılır. Crash sonra log-dan recovery mümkündür.
- **Synchronous commit**: Default. Commit = WAL diska flush.
- **Asynchronous commit**: Daha sürətli amma crash-da son few ms transaction-lar itə bilər.
- **RAID, replication**: Durability-ni artırır.
- PostgreSQL: `synchronous_commit = on` (default). `off` desən performance artır amma data loss riski.

### Isolation Level-lər

| Level | Dirty Read | Non-Repeatable | Phantom Read | Write Skew |
|-------|-----------|----------------|--------------|------------|
| READ UNCOMMITTED | ✓ mümkün | ✓ mümkün | ✓ mümkün | ✓ mümkün |
| READ COMMITTED | ✗ | ✓ mümkün | ✓ mümkün | ✓ mümkün |
| REPEATABLE READ | ✗ | ✗ | ✓ mümkün* | ✓ mümkün |
| SERIALIZABLE | ✗ | ✗ | ✗ | ✗ |

*PostgreSQL REPEATABLE READ-də phantom read-lər də qorunur (MVCC sayəsində).

### Anomaliya Növləri

**Dirty Read** (Kirli oxuma):
- T1 commit etməmiş datanı T2 oxuyur. T1 rollback edərsə T2 "olmayan" datanı gördü.
- Yalnız READ UNCOMMITTED-da.
- Demək olar ki, istifadə edilmir (bank/order sistemlərində heç zaman).

**Non-Repeatable Read** (Təkrarlanmayan oxuma):
- T1 eyni row-u iki dəfə oxuyur. Arada T2 update edib commit edir. T1 fərqli nəticə görür.
- READ COMMITTED-da mümkün.
- Nümunə: Hesabatda eyni user-in balance-ını 2 dəfə oxuyursan, arada dəyişdi.

**Phantom Read** (Xəyal oxuma):
- T1 range query icra edir. Arada T2 **yeni row INSERT** edir. T1 yenidən sorğu etdikdə yeni row görünür.
- REPEATABLE READ-da mümkün (əksər implementasiyalarda; PostgreSQL-də MVCC qoruyur).
- Nümunə: "18 yaşdan böyük userləri tap" — arada yeni user insert olundu.

**Lost Update** (İtirilmiş güncəlləmə):
- T1 və T2 eyni row-u oxuyur (balance=100). T1 100→90 yazır. T2 da 100→110 yazır. T1-in update-i itirildi.
- `SELECT ... FOR UPDATE` ya da optimistic locking ilə həll.

**Write Skew** (Yazma eğikliyi):
- T1 və T2 eyni şərti oxuyur (invariant-ı yoxlayır). Hər ikisi ayrıca yazır. Nəticə invariant-ı pozur.
- REPEATABLE READ-da mümkün.
- Klassik nümunə: Doctor on-call sistemi. ≥1 doktor on-call olmalı. T1 "2 var" görüb çıxır. T2 "2 var" görüb çıxır. Nəticə: 0 doktor.

### PostgreSQL Default: READ COMMITTED

- Hər statement öz snapshot-ını alır (transaction deyil, statement).
- Commit olmuş datanı görür.
- Non-repeatable read mümkündür.
- Çox hallarda yetərlidir. `FOR UPDATE` ilə konkret row-ları lock et.

### MySQL InnoDB Default: REPEATABLE READ

- Transaction başladıqda snapshot alınır, bütün transaction boyunca o snapshot görünür.
- Phantom read: Gap lock-lar ilə qorunur (PostgreSQL-dən fərqli yanaşma).
- Gap lock-lar deadlock riskini artırır.

### Two-Phase Commit (2PC) — Distributed ACID

- Birden çox database-ə span edən transaction.
- **Phase 1 (Prepare)**: Coordinator bütün participant-lardan "hazırsan?" soruşur.
- **Phase 2 (Commit/Abort)**: Hamı hazırdırsa commit, biri yox deyirsə abort.
- **Problemi**: Blocking protocol — coordinator crash olarsa participant-lar locked qalır.
- Alternativ: **Saga pattern** — local transaction-lar + compensating transaction-lar.

### Optimistic vs Pessimistic Locking

**Pessimistic** (`SELECT ... FOR UPDATE`):
- Transaction boyunca row-u lock edir.
- Digər transaction gözləyir.
- Write conflict çox olduqda yaxşı.

**Optimistic** (version column):
- Lock yoxdur. Update zamanı version yoxlanır.
- `UPDATE ... WHERE id=? AND version=?`. 0 rows → conflict, retry.
- Read-heavy, conflict az olduqda yaxşı.
- Deadlock riski yoxdur.

## Praktik Baxış

### Interview-a Yanaşma

1. Hər hərfi izah edin, həyatdan nümunə verin (bank transfer, e-commerce order).
2. Isolation level-ları sadalayın və hər birinin hansı anomaliyadan qoruduğunu bildiyin.
3. "PostgreSQL-də default isolation level nədir?" sualına hazır olun (READ COMMITTED).
4. ACID-in performance cost-unu qeyd edin — SERIALIZABLE yavaşdır.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Isolation level-ları sıralayın" — dirty read, non-repeatable read, phantom read, write skew ilə.
- "SERIALIZABLE nə vaxt istifadə edərdiniz?" — Fraud detection, financial ledger.
- "Distributed sistemdə ACID necə təmin olunur?" — 2PC, Saga pattern.
- "WAL nədir?" — Write-Ahead Log, durability mexanizmi.
- "MVCC nədir?" — Multi-Version Concurrency Control, read-write conflict azaltma.
- "Write skew anomaliyası nədir? Nümunə?" — Doctor on-call problemi.
- "Optimistic locking nə vaxt pessimistic-dən üstündür?"

### Common Mistakes

- **Consistency-ni "data corruption olmur" kimi izah etmək** — Bu Atomicity-dir. Consistency = constraint-lər.
- Isolation level-ları bilməmək — yalnız "Isolation = isolation" demək.
- ACID-in performance cost-unu qeyd etməmək.
- "SERIALIZABLE hər zaman istifadə edin" demək — performans trade-off var.
- Write skew-u bilməmək.
- MVCC-nin lock-free read-i necə təmin etdiyini bilməmək.

### Yaxşı → Əla Cavab

- **Yaxşı**: 4 hərfi izah edir, bank transfer nümunəsi verir.
- **Əla**: Anomaliyaları real nümunə ilə izah edir, WAL-ı bilir, MVCC-ni qeyd edir, write skew nümunəsi verir, distributed ACID-in niyə çətin olduğunu bilir, optimistic vs pessimistic locking seçimini izah edir.

### Real Production Ssenariləri

- Bank transfer: SERIALIZABLE (ya da READ COMMITTED + FOR UPDATE).
- E-commerce order: READ COMMITTED + explicit locking.
- Analytics dashboard: READ COMMITTED (eventual consistency OK).
- Inventory management: REPEATABLE READ ya SERIALIZABLE (overselling problem).
- Doctor on-call: SERIALIZABLE ya advisory lock.

## Nümunələr

### Tipik Interview Sualı

"Bank tətbiqinizdə pul köçürməsi zamanı hansı ACID xüsusiyyətləri kritikdir və bu xüsusiyyətlər olmadan nə baş verə bilər?"

### Güclü Cavab

Bank transfer üçün bütün 4 xüsusiyyət kritikdir, lakin fərqli səbəblərdən:

**Atomicity** olmadan: Alice-in hesabından 100$ çıxılır, lakin Bob-un hesabına yazılmamış halda sistem çökürsə — pul itir. Ya da Alice-in balansı azalır, Bob-unki artmır.

**Consistency** olmadan: `balance >= 0` constraint pozulur — mənfi balans mümkün olur.

**Isolation** olmadan (READ COMMITTED): İki paralel transfer eyni bakiyəni oxuyub ikisi də "500$ var" görüb pul köçürə bilər — double spending. Bu xüsusilə `SELECT balance; ... UPDATE balance` kimi əməliyyatlarda olur. `FOR UPDATE` ilə həll.

**Durability** olmadan: Commit əldə etdikdən sonra power outage olsa transaction itirilə bilər. WAL bu zəmanəti verir.

Praktikada: READ COMMITTED + `SELECT ... FOR UPDATE` ya da SERIALIZABLE.

### Kod Nümunəsi

```sql
-- Atomicity nümunəsi: bank transfer
BEGIN;
  UPDATE accounts SET balance = balance - 100
  WHERE user_id = 1 AND balance >= 100;   -- Consistency: mənfi balans olmur

  -- Əgər 0 rows updated → InsufficientFunds, ROLLBACK
  INSERT INTO transaction_log (from_id, to_id, amount, created_at)
  VALUES (1, 2, 100, NOW());

  UPDATE accounts SET balance = balance + 100
  WHERE user_id = 2;
  -- Burda exception olarsa, hər iki UPDATE geri alınır (Atomicity)
COMMIT;   -- Durable: WAL-a flush, sonra data file

-- Isolation level dəyişdirmə (PostgreSQL)
-- Method 1: Session-üçün
SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
BEGIN;
  SELECT balance FROM accounts WHERE user_id = 1 FOR UPDATE;
  -- Digər transaction bu row-u dəyişdirə bilməz
  UPDATE accounts SET balance = balance - 100 WHERE user_id = 1;
COMMIT;

-- Method 2: Single transaction
BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED;
  -- ...
COMMIT;

-- Non-repeatable read nümunəsi (READ COMMITTED)
-- Transaction A (biri bunu edir):
BEGIN;
  SELECT balance FROM accounts WHERE id = 1;  -- 1000 görür
  -- ... digər şeylər edir ...
  SELECT balance FROM accounts WHERE id = 1;  -- 900 görür! (B commit etdi)
COMMIT;
-- READ COMMITTED-da belə olur.
-- REPEATABLE READ-da hər iki SELECT eyni nəticəni verir.

-- Write Skew nümunəsi (Doctor on-call)
-- REPEATABLE READ-da PROBLEM var:
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ;
  SELECT COUNT(*) FROM on_call WHERE active = true;  -- 2 görür
  -- T2 eyni anda eyni query edir, 2 görür
  -- T1 ON active = false (öz id-si üçün)
  -- T2 ON active = false (öz id-si üçün)
  -- Nəticə: 0 on-call doktor! Invariant pozuldu!
COMMIT;

-- HƏLL: SERIALIZABLE ya da advisory lock
BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE;
  SELECT COUNT(*) FROM on_call WHERE active = true;
  UPDATE on_call SET active = false WHERE doctor_id = ?;
COMMIT;
-- SERIALIZABLE: Conflict detect edib birini rollback edir.
-- Retry logic lazımdır (error 40001).

-- Savepoint nümunəsi
BEGIN;
  UPDATE orders SET status = 'processing' WHERE id = 1;
  SAVEPOINT after_order_update;

  BEGIN
    UPDATE inventory SET quantity = quantity - 1 WHERE product_id = 42;
  EXCEPTION WHEN OTHERS THEN
    ROLLBACK TO after_order_update;  -- Yalnız inventory update-i geri al
    UPDATE orders SET status = 'pending', note = 'inventory failed' WHERE id = 1;
  END;
COMMIT;

-- WAL-ı yoxlamaq
SHOW wal_level;              -- replica (default)
SHOW synchronous_commit;     -- on (default — safe)
-- 'off' versiyası: Daha sürətli, amma crash-da son ms-lər itirilir

-- MVCC: Hər transaction öz snapshot-ını görür
SHOW transaction_isolation;  -- read committed (default)
SELECT txid_current();        -- Cari transaction ID (MVCC-nin əsası)
```

```php
// Laravel-də ACID transaction
DB::transaction(function () use ($fromId, $toId, $amount) {
    // Pessimistic locking — row-ları lock edir
    $from = Account::lockForUpdate()->findOrFail($fromId);

    if ($from->balance < $amount) {
        throw new InsufficientFundsException("Insufficient balance");
    }

    $from->decrement('balance', $amount);
    Account::findOrFail($toId)->increment('balance', $amount);

    TransactionLog::create([
        'from_id'    => $fromId,
        'to_id'      => $toId,
        'amount'     => $amount,
        'created_at' => now(),
    ]);
    // Exception → automatic ROLLBACK (Atomicity)
    // COMMIT → WAL flush (Durability)
}, 3);  // 3 retry cəhd (deadlock/serialization failure üçün)

// Optimistic locking (version column)
class Account extends Model
{
    use OptimisticLocking;   // ya da manual version check

    public function transferTo(Account $target, float $amount): void
    {
        DB::transaction(function () use ($target, $amount) {
            $self = Account::where('id', $this->id)
                           ->where('version', $this->version)
                           ->lockForUpdate()
                           ->first();

            if (!$self) {
                throw new StaleDataException("Account was modified");
            }

            $self->update([
                'balance' => $self->balance - $amount,
                'version' => $self->version + 1,
            ]);

            $target->increment('balance', $amount);
            $target->increment('version');
        });
    }
}

// Isolation level Laravel-də
DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
DB::transaction(function () {
    // Critical operations
}, 3);  // Retry on serialization failure (40001)
```

```python
# Python psycopg2 ilə isolation level
import psycopg2
from psycopg2.extensions import (
    ISOLATION_LEVEL_READ_COMMITTED,
    ISOLATION_LEVEL_REPEATABLE_READ,
    ISOLATION_LEVEL_SERIALIZABLE
)

conn = psycopg2.connect(dsn="...")

# SERIALIZABLE test
conn.set_isolation_level(ISOLATION_LEVEL_SERIALIZABLE)

with conn.cursor() as cur:
    # Serialization failure exception handling
    try:
        cur.execute("SELECT SUM(balance) FROM accounts")
        total_before = cur.fetchone()[0]
        # ... operations ...
        cur.execute("SELECT SUM(balance) FROM accounts")
        total_after = cur.fetchone()[0]
        # SERIALIZABLE ilə total_before == total_after guarantee var
        assert total_before == total_after
        conn.commit()
    except psycopg2.errors.SerializationFailure:
        conn.rollback()
        # Retry logic
```

```sql
-- pg_stat_activity ilə transaction monitoring
-- Uzun çalışan transaction-ları tap
SELECT
    pid,
    usename,
    state,
    wait_event_type,
    query_start,
    now() - query_start AS duration,
    LEFT(query, 80) AS query_snippet
FROM pg_stat_activity
WHERE state != 'idle'
  AND query_start < NOW() - INTERVAL '30 seconds'
ORDER BY duration DESC;

-- Isolation level anomaliyasını test etmə (2 terminal açıq)
-- Terminal 1:
BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED;
SELECT balance FROM accounts WHERE id = 1;  -- 1000

-- Terminal 2 (arada):
UPDATE accounts SET balance = 900 WHERE id = 1;
COMMIT;

-- Terminal 1 (devam):
SELECT balance FROM accounts WHERE id = 1;  -- 900 (non-repeatable read!)
COMMIT;
```

## Praktik Tapşırıqlar

1. "Dirty read", "non-repeatable read", "phantom read" arasındakı fərqi **bir nümunə** ilə izah edin.
2. PostgreSQL-də `SHOW transaction_isolation;` çalıştırın — default dəyər nədir? MySQL-də?
3. Bank transfer kodunuzu transaction olmadan yazın, sonra hansı race condition-lar olduğunu göstərin.
4. `SELECT ... FOR UPDATE` ilə `SELECT ... FOR SHARE` arasındakı fərqi araşdırın.
5. 2 terminal açın: READ COMMITTED-da non-repeatable read reproduce edin.
6. SERIALIZABLE-da doctor on-call write skew-unu reproduce edin, sonra həll edin.
7. Distributed transaction üçün Saga pattern-i araşdırın — 2PC ilə trade-off-larını müqayisə edin.
8. Laravel-də `DB::transaction()` exception handling-i test edin — exception olarsa rollback olduğunu verify edin.

## Əlaqəli Mövzular

- `06-transaction-isolation.md` — Isolation level-ların dərin analizi, write skew, SSI.
- `07-database-deadlocks.md` — Yüksək isolation → deadlock riski.
- `12-mvcc.md` — PostgreSQL-in isolation-u MVCC ilə necə tətbiq etdiyi.
- `13-optimistic-pessimistic-locking.md` — Consistency qoruma strategiyaları.
