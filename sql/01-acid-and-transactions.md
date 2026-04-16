# ACID & Transactions

## ACID nedir?

ACID - database transaction-larin etibarliliqini temin eden 4 xususiyyetdir.

### A - Atomicity (Bolunmezlik)

Transaction ya **tamam** icra olunur, ya da **hec biri** icra olunmur. Yarimciq qalmaz.

**Misal:** Bank transferi - hesabdan pul cixir amma diger hesaba daxil olmur? Bu ola bilmez!

```sql
START TRANSACTION;

UPDATE accounts SET balance = balance - 500 WHERE id = 1;  -- Pul cixir
UPDATE accounts SET balance = balance + 500 WHERE id = 2;  -- Pul daxil olur

COMMIT;  -- Her ikisi ugurlu olarsa, deyisiklikleri saxla
```

Eger ikinci UPDATE ugursuz olarsa, birinci de geri qaytarilir (ROLLBACK olunur).

**PHP (Laravel) misali:**

```php
DB::transaction(function () {
    $sender = Account::findOrFail(1);
    $receiver = Account::findOrFail(2);

    $sender->decrement('balance', 500);
    $receiver->increment('balance', 500);
});
// Exception bas vererse, her sey avtomatik ROLLBACK olunur
```

### C - Consistency (Uygunluq)

Transaction baslamazdan evvel ve bitdikden sonra database **valid** veziyyetde olmalidir. Butun constraint-ler, rule-lar qorunmalidir.

```sql
-- balance menfi ola bilmez deyə CHECK constraint var
ALTER TABLE accounts ADD CONSTRAINT positive_balance CHECK (balance >= 0);

START TRANSACTION;
UPDATE accounts SET balance = balance - 1000 WHERE id = 1;
-- Eger balance 500-dursa, constraint pozulur ve transaction FAIL olur
COMMIT;
```

### I - Isolation (Tecridlik)

Eyni anda isleyen transaction-lar bir-birini gormur. Sanki ardicil (sequential) isleyirler.

```
Transaction A:  READ balance (1000) ---> UPDATE balance = 500
Transaction B:       READ balance (1000) ---> UPDATE balance = 700

-- Isolation olmasa, biri digerin deyisikliyini eze biler (Lost Update)
-- Isolation ile bu problem hell olunur (bax: 02-isolation-levels.md)
```

### D - Durability (Dayaniqlilik)

COMMIT edildikden sonra data **hec vaxt** itmir - server crash olsa bele.

Database bunu **Write-Ahead Log (WAL)** vasitesile edir:
1. Evvelce deyisiklik log-a yazilir (disk-e)
2. Sonra actual data deyisdirilir
3. Crash olsa, restart zamani log-dan recovery edilir

---

## Transaction Management

### Manual Transaction (PDO)

```php
$pdo = new PDO('mysql:host=localhost;dbname=shop', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $stmt->execute([2, 101]);

    $stmt = $pdo->prepare("INSERT INTO orders (product_id, quantity) VALUES (?, ?)");
    $stmt->execute([101, 2]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### Laravel Transaction

```php
// Variant 1: Closure (avtomatik rollback)
DB::transaction(function () {
    Order::create([...]);
    Product::where('id', 1)->decrement('stock', 2);
});

// Variant 2: Manual
DB::beginTransaction();

try {
    Order::create([...]);
    Product::where('id', 1)->decrement('stock', 2);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### Nested Transactions (Savepoints)

```php
DB::transaction(function () {
    // Outer transaction
    User::create(['name' => 'John']);

    try {
        DB::transaction(function () {
            // Inner transaction (SAVEPOINT yaradilir)
            Profile::create(['user_id' => 1]);
            // Exception bas verse, yalniz bu hisse rollback olunur
        });
    } catch (\Exception $e) {
        // Outer transaction davam edir
        Log::warning('Profile creation failed');
    }
});
```

MySQL-de bu SAVEPOINT mexanizmi ile isleyir:

```sql
START TRANSACTION;
INSERT INTO users (name) VALUES ('John');

SAVEPOINT sp1;
INSERT INTO profiles (user_id) VALUES (1);
-- Ugursuz olarsa:
ROLLBACK TO SAVEPOINT sp1;

-- Outer transaction davam edir
COMMIT;
```

---

## Transaction Deadlines & Timeouts

Uzun suren transaction-lar lock-lar saxlayir ve diger transaction-lari bloklayir.

```php
// Laravel: Transaction timeout (MySQL)
DB::statement('SET innodb_lock_wait_timeout = 5'); // 5 saniye gozle, sonra fail et

DB::transaction(function () {
    // Bu 5 saniye erzinde lock ala bilmese, exception atir
    $order = Order::lockForUpdate()->find(1);
    // ...
});
```

---

## Interview suallari

**Q: ACID-in hansi xususiyyeti en bahalisidir (performance baximindan)?**
A: **Isolation** - cunki lock-lar, MVCC ve eyni anda isleyen transaction-lari idare etmek en cox resurs teleb edir. Buna gore muxtelif isolation level-ler var (bax: 02-isolation-levels.md).

**Q: NoSQL database-ler ACID-i destekleyir?**
A: Evveller yox idi, amma indi bezi destekleyir. MongoDB 4.0+ multi-document ACID transaction destekleyir. Redis isə single-command atomicity verir, amma multi-command transaction ucun MULTI/EXEC istifade olunur (tam ACID deyil).

**Q: Transaction icinde HTTP request gonderilse ne olar?**
A: Pis praktika! Transaction rollback olsa, HTTP request geri qaytarila bilmez. Evvelce transaction-i bitir, sonra HTTP request gonder. Yaxud event/queue istifade et.

```php
// YANLIS
DB::transaction(function () {
    $order = Order::create([...]);
    Http::post('https://payment.api/charge', [...]); // Rollback olsa ne olar?
});

// DOGRU
$order = DB::transaction(function () {
    return Order::create([...]);
});
// Transaction ugurlu bitdi, indi request gonder
Http::post('https://payment.api/charge', [...]);
```
