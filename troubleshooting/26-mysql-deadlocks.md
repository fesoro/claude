# MySQL Deadlocks (Senior)

## Problem (nəyə baxırsan)
Tətbiq log-ları `Deadlock found when trying to get lock; try restarting transaction` (SQLSTATE 40001, error 1213) göstərir. Bəzi transaction-lar təsadüfi uğursuz olur. InnoDB iki transaction-ın bir-birini bloklamasını aşkar etdi və həll etmək üçün birini kill etdi.

Simptomlar:
- Yük altında fasiləli uğursuzluqlar, az trafikdə normal
- `SQLSTATE[40001]: Serialization failure: 1213 Deadlock found`
- Deadlock mesajı ilə Laravel `QueryException`
- Yalnız bəzi əməliyyatlar təsirlənir (adətən çoxlu sətir və ya cədvəli yeniləyənlər)
- Worker-lər job-ları retry etməli olur

## Sürətli triage (ilk 5 dəqiqə)

### Bunun deadlock olduğunu təsdiqlə, lock-wait timeout deyil

İki fərqli xəta:
- **Deadlock (1213)**: cycle aşkarlandı, InnoDB birini rollback edir
- **Lock wait timeout (1205)**: cycle yoxdur, amma bir transaction > `innodb_lock_wait_timeout` gözlədi

App üçün hər ikisi oxşar görünür, amma səbəb və fix fərqlidir.

### Sonuncu deadlock-a bax

```sql
SHOW ENGINE INNODB STATUS\G
```

`LATEST DETECTED DEADLOCK`-a scroll et. Hər iki transaction-ı, hansı lock-ları tutduğunu, nə gözlədiklərini göstərir.

Nümunə:
```
*** (1) TRANSACTION:
TRANSACTION 42,  ACTIVE 3 sec updating or deleting
mysql tables in use 1, locked 1
LOCK WAIT 3 lock struct(s), heap size 1080
MySQL thread id 7, query id 20 ... updating
UPDATE orders SET status='paid' WHERE id=1

*** (1) WAITING FOR THIS LOCK:
RECORD LOCKS space id 0 page no 3 n bits 72 index PRIMARY of table `mydb`.`orders`

*** (2) TRANSACTION:
TRANSACTION 43, ACTIVE 4 sec updating or deleting
...
UPDATE orders SET status='paid' WHERE id=2

*** (2) WAITING FOR THIS LOCK:
...
```

### Zaman keçdikcə deadlock-ları tut

`my.cnf`-də `innodb_print_all_deadlocks = 1` hər deadlock-u error log-a yazır:

```ini
[mysqld]
innodb_print_all_deadlocks = 1
```

Sonra:
```bash
grep -A 50 "DEADLOCK" /var/log/mysql/error.log
```

## Diaqnoz

### Klassik deadlock pattern-i

İki transaction, iki sətir, əks qaydada:

**T1**:
```sql
BEGIN;
UPDATE orders SET status='paid' WHERE id=1;  -- locks row 1
-- ...
UPDATE orders SET status='paid' WHERE id=2;  -- waits for row 2
```

**T2** (eyni anda):
```sql
BEGIN;
UPDATE orders SET status='paid' WHERE id=2;  -- locks row 2
-- ...
UPDATE orders SET status='paid' WHERE id=1;  -- waits for row 1 → CYCLE → DEADLOCK
```

InnoDB cycle-ı aşkar edir, bir transaction-ı kill edir.

### Az aşkar pattern-lər

1. **Fərqli cədvəllər, gizli FK lock-lar**:
   - T1 `orders`-i yeniləyir, `order_items`-ə insert edir (`orders`-ə FK)
   - T2 `order_items`-i yeniləyir, bu `orders`-də FK lock götürür
   - Sıralama fərqli olsa cycle mümkündür

2. **Index və row lock-lar**:
   - Qeyri-primary index-lərdə update-lər gap lock vasitəsilə çoxlu sətri lock edə bilər (xüsusilə `REPEATABLE READ` ilə)

3. **Auto-increment insert-lər**:
   - Nadir rejimlərdə bulk insert-lər AUTO-INC lock-da deadlock edə bilər

4. **Fərqli qaydalı `IN (…)` siyahılarla batch update-lər**:
   ```sql
   UPDATE t WHERE id IN (1, 2, 3);  -- T1
   UPDATE t WHERE id IN (3, 2, 1);  -- T2 — can deadlock
   ```

### Niyə "yük altında"?

Deadlock concurrency tələb edir. Az trafik → race yoxdur. Yüksək trafik → çoxlu transaction üst-üstə düşür → cycle-lar mümkün olur.

## Fix (bleeding-i dayandır)

### Qısamüddətli

1. **App-də retry** — deadlock-lar dizayn baxımından retryable-dır
2. **Trafiki azalt** — əgər tək retry tier varsa və uğursuzluqlar onu aşırsa
3. **Daha qısa transaction-lar** — daha tez bitir, daha az üst-üstə düşmə

### Ortamüddətli

1. **Tutarlı lock sıralaması** — sətirləri həmişə eyni qaydada yenilə
   ```php
   // Sort IDs before batch update
   $ids = collect($ids)->sort()->values()->all();
   Order::whereIn('id', $ids)->update(['status' => 'paid']);
   ```

2. **Daha dar lock-lar** — daha yaxşı index-lər → daha az gap lock
   - UPDATE-in WHERE-ində istifadə olunan sütuna index əlavə et
   - MySQL 8 və `READ COMMITTED` isolation gap lock-ları azaldır

3. **Daha qısa transaction-lar** — yavaş əməliyyatlar ətrafında lock tutma
   - Transaction daxilində HTTP çağırış yox
   - Transaction daxilində file I/O yox
   - Transaction daxilində istifadəçi input gözləmə yox

4. **Təhlükəsiz olduqda isolation səviyyəsini aşağı sal**:
   ```sql
   SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
   ```
   Default `REPEATABLE READ`-dir. `READ COMMITTED`-də daha az gap lock var. Bunu uyğun olduqda session-başına və ya transaction-başına et.

5. **Batch əməliyyatları düşünülmüş şəkildə** — daha kiçik batch-lər, tutarlı qayda.

### Lock wait timeout tənzimi

```sql
SET GLOBAL innodb_lock_wait_timeout = 5;  -- default 50
```

Qısa → fail fast, tez retry. Uzun → daha çox gözləmə, amma daha az retry. Retry strategiyana uyğunlaşdır.

## Əsas səbəbin analizi

- İki transaction nə idi? (`SHOW ENGINE INNODB STATUS`-dan.)
- Hər birinin lock sıralaması necə idi?
- Hansı index istifadə olundu (və ya olunmadı) — gap lock-lar əhatəni genişləndirdimi?
- Kod-da sıralamanı məcbur edə bilərikmi?

## Qarşısının alınması

- Prod-da `innodb_print_all_deadlocks = 1` aktivləşdir
- Deadlock dərəcəsi threshold-u keçəndə alert
- App-də error 1213 üçün retry məntiqi
- Code review: bulk əməliyyatları və çoxlu-cədvəl update-lərini işarələ
- Paralel write-ləri simulyasiya edən testlər

## PHP/Laravel xüsusi qeydlər

### Deadlock-da Laravel retry

`DB::transaction($callback, $attempts)`:
```php
DB::transaction(function () use ($order) {
    $order->lockForUpdate()->first();
    // work
}, 5);  // retry up to 5 times on deadlock
```

Laravel error 1213-də avtomatik olaraq retry edir. Adi `QueryException` tutulur və yenidən icra olunur.

### Manual retry pattern-i

```php
use Illuminate\Database\QueryException;

function withDeadlockRetry(Closure $cb, int $max = 3)
{
    $attempt = 0;
    while (true) {
        try {
            return $cb();
        } catch (QueryException $e) {
            $attempt++;
            // SQLSTATE 40001 = deadlock / serialization failure
            if ($e->getCode() != '40001' || $attempt >= $max) {
                throw $e;
            }
            usleep(random_int(10000, 100000) * $attempt); // jittered backoff
        }
    }
}
```

### `SELECT ... FOR UPDATE` ilə tutarlı sıralama

```php
// Lock orders in consistent ID order
$orders = Order::whereIn('id', $ids)
    ->orderBy('id')
    ->lockForUpdate()
    ->get();

foreach ($orders as $order) {
    $order->update(['status' => 'paid']);
}
```

ID qaydası ilə əldə edilən lock-lar eyni qayda tutan digər transaction-larla deadlock-un qarşısını alır.

### Eloquent sıralanmış update-lər

```php
// Explicitly sorted before batch update
$ids = User::where('plan', 'trial')
    ->orderBy('id')
    ->pluck('id')
    ->all();

User::whereIn('id', $ids)->update(['plan' => 'expired']);
```

### Transaction daxilində etmə

```php
DB::transaction(function () use ($order) {
    $order->update(['status' => 'processing']);
    
    // BAD — HTTP call holds locks
    Http::timeout(30)->post('https://slow-api...')->throw();
    
    $order->update(['status' => 'done']);
});
```

HTTP çağırışı xaricə çıxar; transaction lock-ları yalnız qısa müddətə tutur.

## Yadda saxlanmalı real komandalar

```sql
-- See latest deadlock
SHOW ENGINE INNODB STATUS\G

-- Active transactions
SELECT * FROM information_schema.innodb_trx ORDER BY trx_started;

-- Who's waiting for what (MySQL 8)
SELECT 
  r.trx_id waiting, r.trx_query waiting_q,
  b.trx_id blocking, b.trx_query blocking_q
FROM performance_schema.data_lock_waits w
JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_engine_transaction_id
JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_engine_transaction_id;

-- Settings
SHOW VARIABLES LIKE 'innodb_lock_wait_timeout';
SHOW VARIABLES LIKE 'innodb_print_all_deadlocks';
SHOW VARIABLES LIKE 'transaction_isolation';

-- Change session isolation
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;

-- Enable all-deadlocks logging
SET GLOBAL innodb_print_all_deadlocks = ON;
```

```php
// Quick diagnostic from Laravel
DB::select("SHOW ENGINE INNODB STATUS");
```

## Müsahibə bucağı

"Düzəltdiyin bir deadlock haqqında danış."

Güclü cavab:
- "Yük altında order status yeniləmələrində fasiləli uğursuzluqlarımız var idi. Log-lar error 1213 göstərdi."
- "`innodb_print_all_deadlocks`-u aktivləşdirdim. 30 dəqiqə prod trafiyindən sonra 12 deadlock trace-i vardı."
- "Pattern: iki job paralel olaraq order-lər üçün ödəniş aparırdı, hər biri fərqli ID sırası ilə sətirlərə toxunurdu."
- "Fix 1: batch update-də ID-ləri sırala. Fix 2: retry-on-deadlock əlavə et (Laravel-in `DB::transaction(..., 3)`). Fix 3: transaction scope-unu azalt — xarici API çağırışı transaction-dan çıxardım."
- "Nəticə: deadlock dərəcəsi ~5/dəq-dan ~1/saat-a düşdü. Qalanları təmiz retry olunur."
- "Ümumi prinsip: deadlock-lar concurrency bug-ıdır, database problemi deyil. Tutarlı lock sıralaması struktur fix-dir."

Bonus: deadlock (cycle, error 1213) və lock-wait timeout (error 1205) arasındakı fərqi qeyd et — fərqli mitigation yolları.
