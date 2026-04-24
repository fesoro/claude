# Isolation Levels

> **Seviyye:** Intermediate ⭐⭐

## Niye lazimdir?

Eyni anda bir nece transaction isleyende problemler yarana biler. Isolation level-ler bu problemlerin hansinin qarsisini almagi mueyyenlesdirir.

---

## Problemler (Anomalies)

### 1. Dirty Read

Bir transaction, diger transaction-in henuz COMMIT etmediyi data-ni oxuyur.

```
Transaction A:                          Transaction B:
UPDATE accounts SET balance = 500       
WHERE id = 1;                           
(COMMIT etmeyib!)                       SELECT balance FROM accounts WHERE id = 1;
                                        -- 500 gorur (COMMIT olunmamis data!)
ROLLBACK;                               -- Amma 500 hec vaxt real olmadi!
```

### 2. Non-Repeatable Read

Eyni transaction icinde eyni query-ni 2 defe cagirirsan, ferqli neticeler qaytarir (baskasi UPDATE edib).

```
Transaction A:                          Transaction B:
SELECT balance FROM accounts 
WHERE id = 1;  -- 1000 gorur
                                        UPDATE accounts SET balance = 500
                                        WHERE id = 1;
                                        COMMIT;
SELECT balance FROM accounts 
WHERE id = 1;  -- 500 gorur! (Ferqli!)
```

### 3. Phantom Read

Eyni query-ni 2 defe cagirirsan, evvelki olmayan yeni row-lar peyda olur (baskasi INSERT edib).

```
Transaction A:                          Transaction B:
SELECT * FROM orders 
WHERE status = 'pending';  -- 5 row
                                        INSERT INTO orders (...) 
                                        VALUES (..., 'pending');
                                        COMMIT;
SELECT * FROM orders 
WHERE status = 'pending';  -- 6 row! (Phantom!)
```

### 4. Lost Update

Iki transaction eyni row-u oxuyur ve update edir, biri digerinin deyisikliyini ezir.

```
Transaction A:                          Transaction B:
SELECT balance FROM accounts 
WHERE id = 1;  -- 1000
                                        SELECT balance FROM accounts 
                                        WHERE id = 1;  -- 1000
UPDATE accounts 
SET balance = 1000 - 200 = 800;
COMMIT;
                                        UPDATE accounts 
                                        SET balance = 1000 - 300 = 700;
                                        COMMIT;
-- Neticede balance 700 oldu. A-nin 200 azaltmasi itdi!
```

---

## Isolation Level-ler

| Level | Dirty Read | Non-Repeatable Read | Phantom Read |
|-------|-----------|-------------------|-------------|
| READ UNCOMMITTED | Mumkun | Mumkun | Mumkun |
| READ COMMITTED | Yox | Mumkun | Mumkun |
| REPEATABLE READ | Yox | Yox | Mumkun* |
| SERIALIZABLE | Yox | Yox | Yox |

*MySQL InnoDB-de REPEATABLE READ phantom read-i de gap lock ile qarsisin alir.

---

### READ UNCOMMITTED

En zeyif seviye. Diger transaction-larin COMMIT etmediyi data-ni gorursen.

```sql
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
START TRANSACTION;
SELECT * FROM accounts; -- Dirty read mumkundur
COMMIT;
```

**Ne vaxt istifade olunur?** Demek olar ki hec vaxt. Yalniz approximate saymalar ucun (meselen, dashboard-da "teqribi order sayi").

### READ COMMITTED

PostgreSQL-in **default** seviyesi. Yalniz COMMIT olunmus data-ni gorursen.

```sql
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
START TRANSACTION;
SELECT balance FROM accounts WHERE id = 1; -- 1000
-- Baskasi balance-i 800 edir ve COMMIT edir
SELECT balance FROM accounts WHERE id = 1; -- 800 (ferqli!)
COMMIT;
```

**PHP misali:**

```php
DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
DB::transaction(function () {
    $balance1 = DB::table('accounts')->where('id', 1)->value('balance');
    // ... uzun emeliyyat ...
    $balance2 = DB::table('accounts')->where('id', 1)->value('balance');
    // $balance1 !== $balance2 ola biler!
});
```

### REPEATABLE READ

MySQL InnoDB-nin **default** seviyesi. Transaction basladiqda data-nin "snapshot"-ini gorursen. Transaction boyu eyni data gorursen.

```sql
SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;
START TRANSACTION;
SELECT balance FROM accounts WHERE id = 1; -- 1000
-- Baskasi balance-i 800 edir ve COMMIT edir
SELECT balance FROM accounts WHERE id = 1; -- Yene 1000! (snapshot)
COMMIT;
```

**MVCC (Multi-Version Concurrency Control)** ile isleyir:
- Her row-un versiyasi var
- Transaction basladiqda movcud versiyalarin snapshot-i alinir
- Diger transaction-larin sonraki deyisiklikleri gorsenmir

### SERIALIZABLE

En guclu seviye. Transaction-lar sanki ardicil (bir-bir) isleyir.

```sql
SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
START TRANSACTION;
SELECT COUNT(*) FROM orders WHERE status = 'pending'; -- 5
-- Baskasi yeni pending order elave etmeye calissa, BLOKLANIR
-- ve ya error alir
INSERT INTO orders (...) VALUES (...);
COMMIT;
```

**Neticesi:** En yavas, en cox lock istifade edir. Deadlock ehtimali yuksekdir.

---

## MySQL vs PostgreSQL ferqi

### MySQL (InnoDB)
- Default: **REPEATABLE READ**
- MVCC + Gap Locks istifade edir
- REPEATABLE READ-de bele phantom read-lerin qarsisini alir (gap lock ile)

### PostgreSQL
- Default: **READ COMMITTED**
- MVCC istifade edir (lock-suz oxuma)
- SERIALIZABLE-de **SSI (Serializable Snapshot Isolation)** istifade edir - lock yerine conflict detection

---

## Laravel-de Isolation Level

```php
// Sade usul: raw statement
DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
DB::transaction(function () {
    // ...
});

// Connection level (config/database.php):
'mysql' => [
    'isolation' => 'READ COMMITTED', // Laravel 10+
    // ...
],
```

---

## Hansi seviyeni secmelisen?

| Ssenari | Tovsiye olunan level |
|---------|---------------------|
| Dashboard/reporting query-leri | READ COMMITTED |
| Umumi CRUD emeliyyatlari | READ COMMITTED / REPEATABLE READ |
| Mali emeliyyatlar (pul transferi) | REPEATABLE READ + explicit lock |
| Inventory/stock management | SERIALIZABLE ve ya SELECT ... FOR UPDATE |
| Yuksek traffic read-heavy app | READ COMMITTED |

---

## Interview suallari

**Q: Niye SERIALIZABLE her yerde istifade olunmur?**
A: Performance. Lock-lar ve ya conflict detection sebebinden throughput ciddi sekilde azalir. Concurrent transaction-lar bir-birini gozleyir ve ya fail olur.

**Q: MVCC nedir ve nece isleyir?**
A: Multi-Version Concurrency Control. Her row-un bir nece versiyasi saxlanilir. Reader-ler lock qoymadan oz versiyalarini oxuyur, writer-ler yeni versiya yaradir. Bu sebebden "readers don't block writers, writers don't block readers."

**Q: SELECT ... FOR UPDATE ne edir?**
A: Oxudugun row-lari lock edir. Diger transaction-lar bu row-lari UPDATE ve ya DELETE ede bilmir (amma adi SELECT ede biler - MVCC sebebinden). Transaction bitene kimi lock saxlanilir.

```php
// Laravel
DB::transaction(function () {
    $account = Account::where('id', 1)->lockForUpdate()->first();
    // Diger transaction-lar bu row-u deyise bilmez
    $account->balance -= 100;
    $account->save();
});
```

**Q: SELECT ... LOCK IN SHARE MODE / FOR SHARE ne edir?**
A: Shared lock qoyur. Diger transaction-lar oxuya biler, amma yazma bilmez.

```php
$account = Account::where('id', 1)->sharedLock()->first();
```
