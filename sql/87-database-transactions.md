# Database Transactions (Senior)

## Mündəricat
1. [ACID Xüsusiyyətləri](#acid-xüsusiyyətləri)
2. [İzolyasiya Problemləri](#izolyasiya-problemləri)
3. [İzolyasiya Səviyyələri](#izolyasiya-səviyyələri)
4. [Locking Strategiyaları](#locking-strategiyaları)
5. [Deadlock](#deadlock)
6. [PHP/Laravel İmplementasiyası](#phplaravel-implementasiyası)
7. [Practical Nümunələr](#practical-nümunələr)
8. [İntervyu Sualları](#intervyu-sualları)

---

## ACID Xüsusiyyətləri

```
// Bu kod ACID xüsusiyyətlərini bank köçürməsi nümunəsi ilə izah edir
A — Atomicity (Atomiklik)
  Transaction-dakı bütün əməliyyatlar ya hamısı icra olur, ya heç biri.
  "Yarım transaction" olmur.
  
  Nümunə: Bank köçürməsi
    BEGIN;
      accounts SET balance = balance - 100 WHERE id = 1;  ← ya
      accounts SET balance = balance + 100 WHERE id = 2;  ← ya hər ikisi
    COMMIT;
  Yalnız birinci icra olunarsa → rollback!

C — Consistency (Ardıcıllıq)
  Transaction DB-ni bir valid state-dən digərinə aparır.
  Constraints, triggers, rules qorunur.
  
  Nümunə: balance >= 0 constraint
    100$ balansda 200$ çıxarmaq → constraint pozulur → rollback

I — Isolation (İzolyasiya)
  Paralel transaction-lar bir-birinə müdaxilə etmir.
  (Tam izolyasiya baha başa gəlir → isolation levels)

D — Durability (Davamlılıq)
  Commit edilmiş data server crash olsa da qalır.
  WAL (Write-Ahead Log) + fsync ilə təmin edilir.
```

---

## İzolyasiya Problemləri

```
// Bu kod dirty read, non-repeatable read, phantom read və lost update problemlərini göstərir
Dirty Read:
  TX1: UPDATE accounts SET balance = 500 WHERE id=1  (hələ commit yox)
  TX2: SELECT balance FROM accounts WHERE id=1  → 500 görür!
  TX1: ROLLBACK
  TX2: Yanlış data ilə işlədi ❌

Non-repeatable Read:
  TX1: SELECT balance FROM accounts WHERE id=1  → 100
  TX2: UPDATE accounts SET balance = 200 WHERE id=1; COMMIT
  TX1: SELECT balance FROM accounts WHERE id=1  → 200 (dəyişdi!)
  TX1: Eyni sorğu, fərqli nəticə ❌

Phantom Read:
  TX1: SELECT COUNT(*) FROM orders WHERE status='pending'  → 5
  TX2: INSERT INTO orders (status) VALUES ('pending'); COMMIT
  TX1: SELECT COUNT(*) FROM orders WHERE status='pending'  → 6 (yeni row!)
  TX1: Range dəyişdi ❌

Lost Update:
  TX1: SELECT balance FROM accounts WHERE id=1  → 100
  TX2: SELECT balance FROM accounts WHERE id=1  → 100
  TX1: UPDATE accounts SET balance = 100 + 50 WHERE id=1  → 150
  TX2: UPDATE accounts SET balance = 100 + 30 WHERE id=1  → 130 (TX1-in update-i itdi!)
  Final: 130, olmalı idi: 180 ❌
```

---

## İzolyasiya Səviyyələri

```
// Bu kod dörd izolyasiya səviyyəsini və onların qoruma imkanlarını müqayisəli göstərir
┌─────────────────────┬─────────────┬──────────────────┬──────────────┐
│ Isolation Level     │ Dirty Read  │ Non-repeatable   │ Phantom Read │
├─────────────────────┼─────────────┼──────────────────┼──────────────┤
│ READ UNCOMMITTED    │ Mümkün      │ Mümkün           │ Mümkün       │
│ READ COMMITTED      │ ✅ Önlənir  │ Mümkün           │ Mümkün       │
│ REPEATABLE READ     │ ✅ Önlənir  │ ✅ Önlənir       │ Mümkün*      │
│ SERIALIZABLE        │ ✅ Önlənir  │ ✅ Önlənir       │ ✅ Önlənir   │
└─────────────────────┴─────────────┴──────────────────┴──────────────┘

* MySQL InnoDB REPEATABLE READ-də Phantom Read-i MVCC ilə önləyir

Default:
  MySQL InnoDB: REPEATABLE READ
  PostgreSQL: READ COMMITTED
  
Performance:
  READ UNCOMMITTED (ən sürətli) ↔ SERIALIZABLE (ən yavaş)
  
Praktikada:
  Əksər aplikasiyalar: READ COMMITTED
  Bank, fintech: SERIALIZABLE (critical sections)
  Analytics: READ UNCOMMITTED (stale data OK)
```

---

## Locking Strategiyaları

```
// Bu kod pessimistic və optimistic locking strategiyalarını müqayisə edir
Pessimistic Locking:
  "Başqası dəyişdirəcək" deyə əvvəlcə lock al.
  
  SELECT ... FOR UPDATE  → Exclusive lock
  SELECT ... FOR SHARE   → Shared lock (oxuma üçün)
  
  ✅ Conflict-ləri tam önləyir
  ❌ Throughput aşağıdır (bəklər)
  ❌ Deadlock riski

Optimistic Locking:
  "Başqası dəyişdirməyəcək" fərz et, commit-də yoxla.
  
  Version field istifadə et:
  SELECT id, balance, version FROM accounts WHERE id=1
  UPDATE accounts 
    SET balance=150, version=version+1 
    WHERE id=1 AND version=1  ← version uyğun gəlmirsə 0 row affected
  
  ✅ Yüksək throughput (lock yoxdur)
  ❌ Retry logic lazımdır
  ❌ High contention-da çox retry
```

---

## Deadlock

```
// Bu kod deadlock vəziyyətini və onun qarşısının alınma yollarını izah edir
Deadlock:
  TX1 → A lock-u tutub, B-yi gözləyir
  TX2 → B lock-u tutub, A-nı gözləyir
  İkisi də bir-birini gözləyir → sonsuz gözləmə!

┌─────────────────────────────────────────────┐
│ TX1                    TX2                  │
│                                             │
│ Lock A ✅              Lock B ✅            │
│ Gözlə B... ←──────────── Gözlə A...        │
│           →──────────────                  │
│           DEADLOCK!                         │
└─────────────────────────────────────────────┘

DB-nin həlli:
  Deadlock detect edilər → bir TX rollback edilir
  MySQL: "Deadlock found when trying to get lock; try restarting transaction"

Deadlock önlmə:
  1. Həmişə eyni sırada lock al
     TX1: Lock A, Lock B
     TX2: Lock A, Lock B  (eyni sıra!)
     
  2. Transaction-ları qısa tut
  
  3. Lock zamanını azalt
     SELECT sadəcə lazım olan row-ları lock etsin
     
  4. Optimistic locking (heç lock yoxdur)
```

---

## PHP/Laravel İmplementasiyası

*PHP/Laravel İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod Laravel-də transaction, pessimistic/optimistic locking istifadəsini göstərir
// Basic transaction
DB::transaction(function () {
    Order::create([...]);
    Payment::create([...]);
});

// Manual transaction
DB::beginTransaction();
try {
    $order = Order::create([...]);
    $this->processPayment($order);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// Savepoints (nested transactions)
DB::transaction(function () {
    $order = Order::create([...]);
    
    DB::transaction(function () use ($order) {
        // Inner transaction → savepoint
        $this->processItems($order);
        // Inner rollback → savepoint-ə qədər rollback
    });
});

// Pessimistic locking
$account = DB::transaction(function () use ($accountId, $amount) {
    $account = Account::where('id', $accountId)
        ->lockForUpdate()       // SELECT ... FOR UPDATE
        ->first();
    
    if ($account->balance < $amount) {
        throw new InsufficientFundsException();
    }
    
    $account->decrement('balance', $amount);
    return $account;
});

// Shared lock (oxuma üçün)
$account = Account::where('id', $accountId)
    ->sharedLock()            // SELECT ... FOR SHARE / LOCK IN SHARE MODE
    ->first();

// Optimistic locking (version/timestamp)
class Account extends Model
{
    public function withdraw(int $amount): bool
    {
        $updated = DB::table('accounts')
            ->where('id', $this->id)
            ->where('version', $this->version)  // Version check
            ->where('balance', '>=', $amount)
            ->update([
                'balance' => DB::raw("balance - $amount"),
                'version' => DB::raw('version + 1'),
            ]);
        
        if ($updated === 0) {
            throw new OptimisticLockException('Hesab başqası tərəfindən dəyişdirilib');
        }
        
        return true;
    }
}

// Retry with optimistic locking
function withRetry(callable $fn, int $maxAttempts = 3): mixed
{
    $attempts = 0;
    while (true) {
        try {
            return $fn();
        } catch (OptimisticLockException $e) {
            $attempts++;
            if ($attempts >= $maxAttempts) throw $e;
            usleep(random_int(10, 100) * 1000); // Random backoff
        }
    }
}
```

---

## Practical Nümunələr

*Practical Nümunələr üçün kod nümunəsi:*
```php
// Bu kod bank köçürməsi və flash sale üçün deadlock-safe pessimistic locking nümunəsi göstərir
// Bank transfer — pessimistic locking
class BankTransferService
{
    public function transfer(int $fromId, int $toId, int $amount): void
    {
        DB::transaction(function () use ($fromId, $toId, $amount) {
            // Deadlock önlə: həmişə kiçik ID-ni əvvəl lock al
            [$firstId, $secondId] = $fromId < $toId 
                ? [$fromId, $toId] 
                : [$toId, $fromId];
            
            $first  = Account::where('id', $firstId)->lockForUpdate()->first();
            $second = Account::where('id', $secondId)->lockForUpdate()->first();
            
            [$from, $to] = $fromId < $toId 
                ? [$first, $second] 
                : [$second, $first];
            
            if ($from->balance < $amount) {
                throw new InsufficientFundsException();
            }
            
            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);
            
            TransactionLog::create([
                'from_account' => $fromId,
                'to_account'   => $toId,
                'amount'       => $amount,
            ]);
        });
    }
}

// Flash sale — inventory deduction
class FlashSaleService
{
    public function purchase(int $productId, int $userId, int $quantity): Order
    {
        return DB::transaction(function () use ($productId, $userId, $quantity) {
            // Pessimistic lock — concurrent purchase zamanı
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->first();
            
            if ($product->stock < $quantity) {
                throw new OutOfStockException('Stokda yetərsiz məhsul');
            }
            
            $product->decrement('stock', $quantity);
            
            return Order::create([
                'user_id'    => $userId,
                'product_id' => $productId,
                'quantity'   => $quantity,
                'total'      => $product->price * $quantity,
            ]);
        });
    }
}
```

---

## İntervyu Sualları

**1. ACID nədir, hər hərfi izah et.**
Atomicity: Transaction bölünməzdir, hamısı ya da heç biri. Consistency: DB constraint-lar qorunur. Isolation: Paralel transaction-lar bir-birindən izolə edilir. Durability: Commit edilmiş data persist olur (WAL).

**2. 4 izolyasiya problemi nədir?**
Dirty Read: Commit edilməmiş data-nı oxumaq. Non-repeatable Read: Eyni sorğu TX daxilindəfərqli nəticə verir (başqası update etdi). Phantom Read: Range sorğusunda yeni row-lar görünür (başqası insert etdi). Lost Update: İki TX eyni vaxtda oxuyub yazır, biri digərinin update-ini ötürür.

**3. Pessimistic vs Optimistic Locking nə vaxt seçilir?**
Pessimistic: Yüksək contention, conflict ehtimalı yüksəkdir (bank transfer, flash sale). Optimistic: Az contention, conflict nadir (profile update, regular order). Pessimistic: lock saxlayır, deadlock riski. Optimistic: lock yoxdur, retry lazımdır.

**4. Deadlock necə önlənir?**
Həmişə eyni sırada lock al (bank transfer: kiçik ID-ni əvvəl). Transaction-ları qısa tut. Minimal row-ları lock et. Optimistic locking istifadə et. Bulk operasiyalarda chunk-lara böl.

**5. MySQL default izolyasiya səviyyəsi nədir, niyə?**
REPEATABLE READ. Dirty read və non-repeatable read-dən qoruyur. Phantom read-i MVCC (Multi-Version Concurrency Control) ilə effektiv olaraq önləyir. READ COMMITTED-dən yüksək consistency, SERIALIZABLE-dən daha yüksək performance.

**6. MVCC (Multi-Version Concurrency Control) nədir?**
MySQL InnoDB-nin reader-lerin writer-ları bloklamaması üçün istifadə etdiyi mexanizm. Hər row-un dəyişiklik tarixçəsi (undo log-da) saxlanılır. Reader, transaction başladığı andakı "snapshot"-ı görür — yazma zamanı lock olmur. Bu READ COMMITTED və REPEATABLE READ-i mümkün edir. SERIALIZABLE isə range lock-lar istifadə edir.

**7. `DB::transaction()` Laravel-də nested transaction-ları necə idarə edir?**
Laravel nested transaction-larda savepoint-lər istifadə edir. Daxili `DB::transaction()` xəta atarsa yalnız o savepoint-ə qədər rollback edilir, xarici transaction davam edir. Bu `SAVEPOINT`/`ROLLBACK TO SAVEPOINT` SQL əmrlərinə çevrilir. Ancaq bəzi DB driver-lar savepoint-i tam dəstəkləmir (MySQL-in bazı konfiqurasyonlarında).

**8. SELECT FOR UPDATE vs SELECT FOR SHARE fərqi nədir?**
`FOR UPDATE` — exclusive lock: başqa heç kim oxuya ya da yaza bilməz. `FOR SHARE` (LOCK IN SHARE MODE) — shared lock: başqaları da `FOR SHARE` ilə oxuya bilir amma yazmaq üçün gözləməlidir. Oxuma-ağırlıqlı, nadiren yazılan hallarda `FOR SHARE` daha az contention yaradır.

---

## Anti-patternlər

**1. Transaction-ı çox geniş saxlamaq**
HTTP sorğusunun başından sonuna kimi tək transaction açıq saxlamaq — external API call, email göndərmə, uzun hesablama transaction daxilindədir, lock-lar uzun tutulur, deadlock ehtimalı artır. Transaction-ı yalnız DB yazma əməliyyatlarını əhatə edəcək şəkildə kiçik tutun; external call-lar transaction xaricindən olsun.

**2. Deadlock-u ignore etmək**
`DB::transaction()` bloğunda iki fərqli sırada eyni sətirləri lock etmək — deadlock baş verir, MySQL xəta atır, əməliyyat uğursuz olur, retry mexanizmi yoxdur. Həmişə eyni sırada lock alın (məs: kiçik ID-dən böyüyə); deadlock exception-ını catch edib exponential backoff ilə retry edin.

**3. Optimistic locking üçün `version` sütunu yoxlamadan update**
`UPDATE orders SET status='confirmed' WHERE id=?` — aradakı dəyişiklik nəzərə alınmır, lost update baş verir. Optimistic locking əlavə edin: `WHERE id=? AND version=?`; affected rows 0 olarsa conflict var, yenidən yükləyib retry edin.

**4. SERIALIZABLE izolyasiyanı hər yerdə istifadə etmək**
"Ən güvənli izolyasiya" düşüncəsilə bütün əməliyyatları SERIALIZABLE ilə işlətmək — throughput kəskin azalır, lock contentioni artır, performans problemləri yaranır. İzolyasiya səviyyəsini işin real tələbinə görə seçin; əksər hallarda REPEATABLE READ (MySQL default) kifayət edir.

**5. Transaction-da exception-ı udmaq**
`try { DB::transaction(...) } catch (\Exception $e) { Log::error($e) }` — xəta log-lanır, amma yuxarıya atılmır, caller əməliyyatın uğursuz olduğunu bilmir. Exception-ı həmişə yenidən atın (`throw`); caller-ın xəbəri olmadan yarım qalmış proseslər yaratmayın.

**6. Read-only sorğular üçün transaction açmaq**
SELECT sorğularını `DB::transaction()` içinə salmaq — lazımsız overhead, snapshot isolation xərcləri artır. Transaction yalnız yazma əməliyyatları üçün açın; oxuma sorğuları transaction xaricindən çalışsın.
