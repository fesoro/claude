# Database Deadlocks (Senior ⭐⭐⭐)

## İcmal
Deadlock — iki və ya daha çox transaction-ın bir-birinin buraxmasını gözlədiyi vəziyyətdir. Heç biri irəliləyə bilmir. Database-lər deadlock-u detect edib birini rollback edir. Bu sual Senior interview-larda həm teoriya, həm real debug təcrübəsi kimi soruşulur.

## Niyə Vacibdir
Deadlock-lar production-da gözlənilmədən baş verə bilər, debug etmək çətindir, intermittent-dir. İnterviewer bu sualla sizin concurrent sistemlərdə nə qədər dərin düşündüyünüzü yoxlayır: deadlock-u reproduce edə bilərsinizmi, necə qarşısını alırsınız, nə vaxt "by design" qəbul edib retry əlavə edirsiniz?

## Əsas Anlayışlar

- **Deadlock**: T1 A-nı gözləyir, T2 B-ni gözləyir; T1 B-ni lock edib, T2 A-nı lock edib → circular wait. Heç biri irəliləyə bilmir.
- **Circular Wait**: Deadlock-un fundamental şərti — lock dependency graph-da dövrü yol (cycle) var. Dövr yoxdursa deadlock yoxdur.
- **Deadlock Detection**: Database lock dependency graph-ı yoxlayır, dövrü tapır, bir transaction-ı rollback edir. PostgreSQL default `deadlock_timeout = 1s` — 1 saniyə gözlədikdən sonra detection başlayır.
- **Victim Selection**: Adətən ən az iş görən (ən kiçik transaction weight) qurban seçilir. PostgreSQL case-specific seçim edir.
- **Deadlock Prevention vs Detection**: Prevention — lock order sıralaması ilə deadlock mümkün olmur. Detection — after-the-fact rollback + retry.
- **Lock Ordering**: Bütün yerlerde eyni sıra ilə lock almaq → circular wait mümkün olmur. Bank transfer: həmişə aşağı ID-li hesab əvvəl lock alınır.
- **Row-Level vs Table-Level Lock**: Row-level lock daha az conflict (yalnız eyni row üçün). Table-level lock bütün table-ı bloklayır. Row-level lock-da deadlock hələ mümkündür.
- **Retry Logic**: Deadlock-dan sonra transaction-ı exponential backoff ilə yenidən cəhd etmək. Deadlock müəyyən hallarda qaçınılmazdır.
- **Lock Wait Timeout**: `lock_timeout = '5s'` — müəyyən vaxt gözlədikdən sonra error ver. Deadlock detectiondan daha tez reaksiya.
- **Statement Timeout**: `statement_timeout = '30s'` — sorğu çox uzunsa kəs. Deadlock ilə eyni deyil, amma uzun gözləmə vəziyyəti üçün.
- **Deadlock-prone patterns**: Multiple table update-ləri fərqli sırada, batch processing, queue-based işlər, ON CONFLICT DO UPDATE (upsert).
- **Optimistic Locking**: Lock almaq əvəzinə version check — deadlock-u tamamilə eliminate edir. Conflict az olduğunda effektiv.
- **FK Constraint Locks**: PostgreSQL-də INSERT child table → parent table-da `FOR KEY SHARE` lock. Concurrent DELETE parent + INSERT child → deadlock mümkündür.
- **Gap Locks (MySQL InnoDB)**: REPEATABLE READ-də range lock-lar — MySQL-in gap lock mexanizmi PostgreSQL-dən fərqlidir. MySQL-də deadlock daha tez-tez olur.
- **`pg_locks` view**: PostgreSQL-də mövcud lock-ları, gözləmə vəziyyətlərini görmək.
- **`deadlock_timeout`**: PostgreSQL-in deadlock yoxlamasına başlayana qədər gözlədiyini müəyyən edir. Default 1s. Çox aşağı olsa overhead artar.

## Praktik Baxış

**Interview-da yanaşma:**
- Klassik "bank transfer deadlock" nümunəsini hazırlayın — circular wait-i vizual izah edin
- Prevention strategiyalarını sıralayın: lock ordering, smaller transactions, optimistic locking, retry
- "Production-da necə debug etdiniz?" sualına hazır olun: `pg_locks`, log analizi

**Follow-up suallar interviewerlər soruşur:**
- "Deadlock-u necə reproduce edəcəksiniz? Nə lazımdır?"
- "Deadlock prevention üçün hansı yanaşmanı üstün tutursunuz?"
- "Optimistic vs pessimistic locking seçim meyarı nədir?"
- "`deadlock_timeout` nə işə yarayır? `lock_timeout` ilə fərqi nədir?"
- "FK constraint deadlock-ları necə baş verir?"
- "Retry logic nə vaxt sonsuz loop-a çevrilər, bunu necə önləyərsiniz?"

**Ümumi candidate səhvləri:**
- "Deadlock-lar olmamalıdır" demək — bəzən kaçınılmazdır, retry lazımdır
- Lock ordering-i bilməmək
- Retry logic yazmadan "deadlock problemi həll etdim" demək
- Deadlock detection ilə lock wait timeout-u qarışdırmaq
- MySQL gap lock-ları PostgreSQL ilə eyni kimi anlatmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Lock ordering-i consistent şəkildə tətbiq etmə — aşağı ID əvvəl
- `pg_locks` ilə real debug etmə nümunəsi
- "Biz deadlock-ları eliminate edə bilmədik, lakin retry ilə handle etdik" demək
- FK constraint deadlock senarisini bilmək

## Nümunələr

### Tipik Interview Sualı
"Bank transfer sisteminizdə iki user eyni anda bir-birinə pul göndərir. Deadlock baş verir. Bu ssenarini izah edin və həll edin."

### Güclü Cavab
"Klassik deadlock ssenarisi — circular wait.

Alice Bob-a göndərir: Transaction T1 — Alice hesabını lock edir (id=1), sonra Bob-u lock etmək istəyir.
Bob Alice-ə göndərir: Transaction T2 — Bob hesabını lock edir (id=2), sonra Alice-i lock etmək istəyir.

T1: id=1 lock → id=2 gözləyir
T2: id=2 lock → id=1 gözləyir
Circular wait → Deadlock!

PostgreSQL 1 saniyə sonra detect edir, birini rollback edir, ERROR 40P01 verir.

Həll 1 — Lock ordering (prevention):
Həmişə aşağı ID-li hesabı əvvəl lock et. Alice(1)→Bob(2) transfer üçün: id=1, sonra id=2. Bob(2)→Alice(1) transfer üçün: yenə id=1, sonra id=2. Eyni sıra → circular wait mümkün deyil.

Həll 2 — Retry (detection + recovery):
Deadlock detect edildikdə (ERROR 40P01) exponential backoff ilə yenidən cəhd et. Bu həmişə lazımdır — lock ordering tətbiq etmək mümkün olmayan hallarda da olur.

Həll 3 — Optimistic locking:
Lock almaq əvəzinə version column ilə conflict detect et. Conflict az olduğunda daha performanslı."

### Kod Nümunəsi — Deadlock Ssenarisi

```sql
-- PROBLEM: Deadlock ssenarisi
-- Terminal 1: Alice → Bob transfer
BEGIN;
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;  -- Alice lock alındı
-- Şimdi Bob-u lock etmək istəyir, amma T2 tutub...

-- Terminal 2: Bob → Alice transfer (eyni anda)
BEGIN;
SELECT * FROM accounts WHERE id = 2 FOR UPDATE;  -- Bob lock alındı
-- Şimdi Alice-i lock etmək istəyir, amma T1 tutub...

-- T1 id=2-ni gözləyir, T2 id=1-i gözləyir → DEADLOCK!
-- PostgreSQL: ERROR 40P01: deadlock detected
-- Detail: Process X waits for ShareLock on transaction Y
--         Process Y waits for ShareLock on transaction X

-- HƏLL: Lock ordering — həmişə aşağı ID əvvəl
-- T1 (Alice→Bob): id=1 əvvəl lock, sonra id=2
-- T2 (Bob→Alice): id=1 əvvəl lock, sonra id=2 (eyni sıra!)
-- Circular wait mümkün deyil!

CREATE OR REPLACE FUNCTION transfer_money(
    from_id  INT,
    to_id    INT,
    amount   NUMERIC
) RETURNS VOID AS $$
DECLARE
    first_id  INT := LEAST(from_id, to_id);
    second_id INT := GREATEST(from_id, to_id);
    from_bal  NUMERIC;
BEGIN
    -- Həmişə aşağı ID əvvəl lock alınır
    PERFORM id FROM accounts WHERE id = first_id  FOR UPDATE;
    PERFORM id FROM accounts WHERE id = second_id FOR UPDATE;

    SELECT balance INTO from_bal FROM accounts WHERE id = from_id;
    IF from_bal < amount THEN
        RAISE EXCEPTION 'Insufficient funds: % < %', from_bal, amount;
    END IF;

    UPDATE accounts SET balance = balance - amount WHERE id = from_id;
    UPDATE accounts SET balance = balance + amount WHERE id = to_id;
END;
$$ LANGUAGE plpgsql;
```

### Kod Nümunəsi — PHP/Laravel Retry

```php
// app/Services/AccountService.php
class AccountService
{
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        $this->withDeadlockRetry(function () use ($fromId, $toId, $amount) {
            DB::transaction(function () use ($fromId, $toId, $amount) {
                // Lock ordering: kiçik ID həmişə əvvəl
                $ids = $fromId < $toId ? [$fromId, $toId] : [$toId, $fromId];

                // Eyni sırada lock — circular wait mümkün deyil
                $accounts = Account::whereIn('id', $ids)
                    ->orderBy('id')         // Sıra mütləqdir!
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $from = $accounts[$fromId];
                $to   = $accounts[$toId];

                if ($from->balance < $amount) {
                    throw new InsufficientFundsException(
                        "Balance {$from->balance} < amount {$amount}"
                    );
                }

                $from->decrement('balance', $amount);
                $to->increment('balance', $amount);

                // Audit log
                TransferRecord::create([
                    'from_account_id' => $fromId,
                    'to_account_id'   => $toId,
                    'amount'          => $amount,
                    'processed_at'    => now(),
                ]);
            });
        });
    }

    private function withDeadlockRetry(callable $callback, int $maxAttempts = 3): void
    {
        $attempt = 0;
        while (true) {
            try {
                $callback();
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                $isDeadlock = in_array($e->getCode(), [
                    '40P01', // PostgreSQL deadlock
                    '1213',  // MySQL deadlock
                ]);

                if (!$isDeadlock || ++$attempt >= $maxAttempts) {
                    throw $e; // Deadlock deyil, ya limit doldu → throw
                }

                // Exponential backoff: 50ms, 100ms, 200ms
                // Jitter əlavə et — eyni vaxtda retry collision önlə
                $sleepMs = (50 * (2 ** ($attempt - 1))) + random_int(0, 20);
                usleep($sleepMs * 1000);

                Log::warning('Deadlock retry', [
                    'attempt' => $attempt,
                    'from_id' => $fromId ?? null,
                    'sleep_ms' => $sleepMs,
                ]);
            }
        }
    }
}
```

### Kod Nümunəsi — Debug və Monitoring

```sql
-- PostgreSQL-də deadlock debug

-- 1. Lock-ları gör
SELECT
    l.pid,
    l.locktype,
    l.relation::regclass   AS table_name,
    l.mode,
    l.granted,
    LEFT(a.query, 80)      AS query_preview,
    now() - a.query_start  AS waiting_for
FROM pg_locks l
JOIN pg_stat_activity a USING (pid)
WHERE NOT l.granted           -- Gözləyən lock-lar
ORDER BY waiting_for DESC;

-- 2. Kim kimi bloklayır
SELECT
    blocked.pid              AS blocked_pid,
    LEFT(blocked.query, 60)  AS blocked_query,
    blocking.pid             AS blocking_pid,
    LEFT(blocking.query, 60) AS blocking_query,
    now() - blocked.query_start AS waiting_for
FROM pg_stat_activity blocked
JOIN pg_stat_activity blocking
    ON blocking.pid = ANY(pg_blocking_pids(blocked.pid))
WHERE blocked.wait_event_type = 'Lock';

-- 3. postgresql.conf — deadlock logging aktiv et
-- log_lock_waits = on
-- deadlock_timeout = 1s  (default)
-- Log-da belə görünür:
-- ERROR: deadlock detected
-- DETAIL: Process 12345 waits for ShareLock on transaction 67890
--         Process 67890 waits for ShareLock on transaction 12345
--         Hint: See server log for query details.

-- 4. lock_timeout — deadlock-dan əvvəl timeout
ALTER SYSTEM SET lock_timeout = '5s';  -- 5 saniyədən uzun gözləyərsə xəta ver
SELECT pg_reload_conf();

-- 5. Uzun çalışan transaction-ları tap
SELECT pid, now() - query_start AS duration, state, LEFT(query, 80)
FROM pg_stat_activity
WHERE state != 'idle'
  AND query_start < NOW() - INTERVAL '30 seconds'
ORDER BY duration DESC;

-- 6. Problematik transaction-ı terminate et
SELECT pg_terminate_backend(12345);
```

### Attack/Failure Nümunəsi — FK Constraint Deadlock

```sql
-- FK Constraint Deadlock — gizli, çox görülmür

-- Cədvəllər:
-- orders (id, user_id, ...)
-- order_items (id, order_id, ...)  -- FK: order_id → orders.id

-- Ssenari:
-- T1: Köhnə order-i delete et
-- T2: Yeni order_item insert et (eyni order-ə)

-- Transaction T1:
BEGIN;
DELETE FROM orders WHERE id = 42;
-- PostgreSQL: orders row-unu ExclusiveLock ilə lock edir
-- Lakin! order_items-dəki FK yoxlanması üçün...
-- ... order_items-i ShareLock ilə lock etmək istəyir

-- Transaction T2 (eyni anda):
BEGIN;
INSERT INTO order_items (order_id, ...) VALUES (42, ...);
-- order_items-ə ExclusiveLock alır (yeni row)
-- FK yoxlaması üçün orders.id=42-ni ShareLock ilə lock etmək istəyir
-- T1 orders-i tutub!

-- T1: orders (Exclusive) → order_items (Share) gözləyir
-- T2: order_items (Exclusive) → orders (Share) gözləyir
-- DEADLOCK!

-- Həll: FK yoxlaması sırası nəzərə alınmalı
-- Child-ı əvvəl delete et, sonra parent-i
BEGIN;
DELETE FROM order_items WHERE order_id = 42;  -- Əvvəl child
DELETE FROM orders WHERE id = 42;              -- Sonra parent
COMMIT;
-- Ya da: ON DELETE CASCADE — database özü həll edir
```

### İkinci Nümunə — Batch Processing Deadlock

```php
// Batch processing-də deadlock — çox görülür

// PROBLEM: Fərqli sırada update
// Worker 1: [1,2,3,4,5] — bu sırada update edir
// Worker 2: [5,4,3,2,1] — əks sırada update edir → Deadlock riski yüksək!

// ❌ Yanlış: sırasız batch
$jobs = Job::where('status', 'pending')->get();
foreach ($jobs as $job) {
    DB::transaction(function () use ($job) {
        $job->lockForUpdate()->first();
        // ... process ...
    });
}

// ✅ Düzgün: hər zaman eyni sıra (ID-ə görə ascending)
$jobs = Job::where('status', 'pending')
    ->orderBy('id', 'asc')  // Consistent sıra — deadlock azalır
    ->lockForUpdate()
    ->get();

// Daha yaxşısı: chunk + consistent ordering
Job::where('status', 'pending')
    ->orderBy('id')
    ->chunk(100, function ($jobs) {
        foreach ($jobs as $job) {
            DB::transaction(function () use ($job) {
                // ... process ...
            });
        }
    });

// Queue-based worker-lar üçün: pessimistic lock + skip locked
$job = Job::where('status', 'pending')
    ->orderBy('id')
    ->lockForUpdate()
    ->skip(0)  // Laravel: ->skipLocked() — PostgreSQL FOR UPDATE SKIP LOCKED
    ->first();

// SKIP LOCKED: Başqa transaction-ın tutduğu row-ları keç — deadlock yoxdur
DB::table('jobs')
    ->where('status', 'pending')
    ->orderBy('id')
    ->limit(1)
    ->lockForUpdate()  // + SKIP LOCKED PostgreSQL-də
    ->first();
```

## Praktik Tapşırıqlar

- İki terminal açın, bank transfer deadlock-unu reproduce edin, PostgreSQL log-unda `ERROR: deadlock detected` mesajını görün
- Lock ordering ilə eyni ssenarini deadlock-suz icra edin, fərqi müşahidə edin
- Laravel-də deadlock retry middleware/service yazın — `40P01` error code-u tutun
- `pg_locks` + `pg_blocking_pids()` ilə blocked query-ləri real-time izləyin
- Batch update: eyni row-ları fərqli sırada update edən iki proses — deadlock reproduce edin, sonra consistent ordering ilə düzəldin
- `lock_timeout = '3s'` konfiqurasiya edin — 3 saniyədən uzun gözləyən transaction-ların xəta almasını gözləyin
- FK constraint deadlock-unu simulasiya edin — parent delete + child insert eyni anda

## Əlaqəli Mövzular
- `06-transaction-isolation.md` — Yüksək isolation → deadlock riski artır
- `13-optimistic-pessimistic-locking.md` — Deadlock-u azaldan strategiyalar
- `02-acid-properties.md` — Atomicity və rollback — deadlock victim rollback
- `12-mvcc.md` — MVCC deadlock-u necə azaldır — read-lər write-ları bloklamır
