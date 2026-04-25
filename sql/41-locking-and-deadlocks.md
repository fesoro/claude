# Locking & Deadlocks (Middle)

## Lock nedir?

Lock - eyni anda bir nece transaction-in eyni data-ni deyisdirerken conflict yaratmasinin qarsisini alan mexanizmdir.

---

## Lock Novleri

### 1. Shared Lock (S Lock / Read Lock)

Bir nece transaction eyni anda oxuya biler, amma **hec biri yaza bilmez**.

```sql
-- MySQL
SELECT * FROM accounts WHERE id = 1 LOCK IN SHARE MODE;
-- ve ya MySQL 8.0+:
SELECT * FROM accounts WHERE id = 1 FOR SHARE;

-- PostgreSQL
SELECT * FROM accounts WHERE id = 1 FOR SHARE;
```

```php
// Laravel
$account = Account::where('id', 1)->sharedLock()->first();
```

### 2. Exclusive Lock (X Lock / Write Lock)

Yalniz **bir** transaction oxuya ve yaza biler. Diger butun transaction-lar **gozleyir**.

```sql
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;
```

```php
// Laravel
$account = Account::where('id', 1)->lockForUpdate()->first();
```

### Lock Compatibility

|  | S Lock | X Lock |
|--|--------|--------|
| **S Lock** | ✅ Uygun | ❌ Conflict |
| **X Lock** | ❌ Conflict | ❌ Conflict |

---

## Lock Granularity

### Row Lock (InnoDB default)
Yalniz mueyyen row-lar lock olunur. Diger row-lara toxunulmur.

```sql
-- Yalniz id=1 row-u lock olunur
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;
-- Diger transaction-lar id=2, id=3, ... ile isleye biler
```

### Table Lock (MyISAM, ve ya explicit)
Butun table lock olunur.

```sql
LOCK TABLES accounts WRITE;
-- Butun table lock olunur, hec kim oxuya/yaza bilmez
-- ... emeliyyatlar ...
UNLOCK TABLES;
```

### Gap Lock (InnoDB - REPEATABLE READ)
Movcud row-lar arasindaki "boslugu" lock edir. Phantom read-in qarsisini alir.

```sql
-- Index-de bu deyerler var: 10, 20, 30
SELECT * FROM orders WHERE id BETWEEN 10 AND 20 FOR UPDATE;

-- Gap lock: (10, 20) araligina yeni row INSERT etmek BLOKLANIR
-- Yeni id=15 INSERT etmek mumkun deyil (transaction bitene kimi)
```

### Next-Key Lock
Row lock + Gap lock birlikde. InnoDB-de default locking usulu.

```
Row-lar: 10, 20, 30
Next-Key Locks: (-inf, 10], (10, 20], (20, 30], (30, +inf)
```

---

## Optimistic vs Pessimistic Locking

### Pessimistic Locking
"Conflict olacaq" ferziyyesi ile **evvelceden** lock edir.

```php
DB::transaction(function () {
    // Row lock olunur - diger transaction-lar gozleyir
    $account = Account::lockForUpdate()->find(1);
    $account->balance -= 100;
    $account->save();
});
```

**Ne vaxt istifade et:**
- Conflict ehtimali yuksekdir
- Mali emeliyyatlar (pul transferi)
- Short-lived transaction-lar

### Optimistic Locking
Lock etmir, yalniz SAVE zamani version-u yoxlayir. Conflict varsa, retry edir.

```php
// Database-de `version` sutunu olmalidir
// Migration:
Schema::table('accounts', function (Blueprint $table) {
    $table->integer('version')->default(0);
});

// Manual implementation
DB::transaction(function () {
    $account = Account::find(1);
    $oldVersion = $account->version;
    
    $account->balance -= 100;
    
    $affected = DB::table('accounts')
        ->where('id', 1)
        ->where('version', $oldVersion)  // Version deyisibse, 0 row update olunur
        ->update([
            'balance' => $account->balance,
            'version' => $oldVersion + 1,
        ]);
    
    if ($affected === 0) {
        throw new OptimisticLockException('Row was modified by another transaction');
    }
});
```

**Laravel-de hazir dəstək yoxdur**, amma `updated_at` ile yoxlamaq olar:

```php
$affected = DB::table('accounts')
    ->where('id', 1)
    ->where('updated_at', $account->updated_at) // timestamp version kimi
    ->update([...]);
```

**Ne vaxt istifade et:**
- Conflict ehtimali asagidir
- Read-heavy system-ler
- Lock gozleme istemirsense (long-running operations)

---

## Deadlock

Iki ve ya daha cox transaction bir-birini gozleyir ve hec biri ireli gede bilmir.

```
Transaction A:                          Transaction B:
Lock row 1 (ugurlu)                     Lock row 2 (ugurlu)
Lock row 2 (gozleyir B-ni...)           Lock row 1 (gozleyir A-ni...)
-- DEADLOCK! Hec biri ireli gede bilmez
```

**Konkret misal:**

```sql
-- Transaction A:
START TRANSACTION;
UPDATE accounts SET balance = balance - 100 WHERE id = 1; -- Row 1 lock
UPDATE accounts SET balance = balance + 100 WHERE id = 2; -- Row 2 gozleyir...

-- Transaction B (eyni anda):
START TRANSACTION;
UPDATE accounts SET balance = balance - 50 WHERE id = 2;  -- Row 2 lock
UPDATE accounts SET balance = balance + 50 WHERE id = 1;  -- Row 1 gozleyir...

-- DEADLOCK!
```

### Deadlock-un hell edilmesi

MySQL/PostgreSQL avtomatik deadlock detection edir ve transaction-lardan birini **ROLLBACK** edir (victim secir).

```
ERROR 1213 (40001): Deadlock found when trying to get lock; try restarting transaction
```

### Deadlock-un qarsisini almaq

**1. Eyni sirda lock et:**

```php
// YANLIS: Random sirada lock
DB::transaction(function () use ($fromId, $toId) {
    $from = Account::lockForUpdate()->find($fromId);
    $to = Account::lockForUpdate()->find($toId);
    // ...
});

// DOGRU: Hemishe kicik ID-den boyuye lock et
DB::transaction(function () use ($fromId, $toId) {
    $ids = [min($fromId, $toId), max($fromId, $toId)];
    $accounts = Account::lockForUpdate()
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get()
        ->keyBy('id');
    
    $from = $accounts[$fromId];
    $to = $accounts[$toId];
    // ...
});
```

**2. Transaction-lari qisa saxla:**

```php
// YANLIS: Transaction icinde yavas emeliyyat
DB::transaction(function () {
    $data = Http::get('https://slow-api.com/data'); // 5 saniye gozleyir
    Order::create($data);
});

// DOGRU: Evvelce data-ni al, sonra transaction
$data = Http::get('https://slow-api.com/data');
DB::transaction(function () use ($data) {
    Order::create($data);
});
```

**3. Lock timeout teyin et:**

```sql
-- MySQL
SET innodb_lock_wait_timeout = 5; -- 5 saniye sonra timeout

-- PostgreSQL
SET lock_timeout = '5s';
```

**4. Retry mexanizmi:**

```php
function executeWithRetry(Closure $callback, int $maxRetries = 3): mixed
{
    $attempts = 0;
    
    while (true) {
        try {
            return DB::transaction($callback);
        } catch (\Illuminate\Database\DeadlockException $e) {
            $attempts++;
            if ($attempts >= $maxRetries) {
                throw $e;
            }
            usleep(random_int(1000, 50000)); // Random delay (jitter)
        }
    }
}

// Istifade
executeWithRetry(function () {
    $account = Account::lockForUpdate()->find(1);
    $account->decrement('balance', 100);
});
```

---

## Deadlock-lari Monitor Etmek

```sql
-- MySQL: Son deadlock melumati
SHOW ENGINE INNODB STATUS;
-- LATEST DETECTED DEADLOCK bolmesine bax

-- MySQL: Hazirki lock-lar
SELECT * FROM performance_schema.data_locks;
SELECT * FROM performance_schema.data_lock_waits;

-- PostgreSQL: Hazirki lock-lar
SELECT * FROM pg_locks WHERE NOT granted;

-- PostgreSQL: Lock gozleyen query-ler
SELECT 
    blocked.pid AS blocked_pid,
    blocked.query AS blocked_query,
    blocking.pid AS blocking_pid,
    blocking.query AS blocking_query
FROM pg_stat_activity blocked
JOIN pg_locks bl ON bl.pid = blocked.pid AND NOT bl.granted
JOIN pg_locks kl ON kl.locktype = bl.locktype 
    AND kl.database = bl.database 
    AND kl.relation = bl.relation 
    AND kl.granted
JOIN pg_stat_activity blocking ON blocking.pid = kl.pid;
```

---

## Interview suallari

**Q: Optimistic ve Pessimistic locking arasindaki ferq nedir?**
A: Pessimistic - data-ni evvelceden lock edir (SELECT FOR UPDATE). Conflict yuksek olan ssenarilerde istifade olunur. Optimistic - lock etmir, yalniz save zamani version/timestamp yoxlayir. Conflict az olan ssenarilerde daha performanslidir.

**Q: Deadlock bas verdikde ne edersin?**
A: 1) `SHOW ENGINE INNODB STATUS` ile hansi query-lerin deadlock yaratdigini tap. 2) Lock siralamasini duzelt (hemishe eyni sira). 3) Transaction-lari qisalt. 4) Retry mexanizmi elave et.

**Q: Gap lock nedir ve niye lazimdir?**
A: Range query zamani movcud row-lar arasindaki boslugu lock edir. Phantom read-in (yeni row INSERT olunmasinin) qarsisini alir. InnoDB REPEATABLE READ isolation level-de avtomatik istifade olunur.
