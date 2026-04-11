# CAP Theorem & BASE vs ACID

## Mündəricat
1. CAP Theorem nədir
2. CP, AP, CA sistemlər
3. ACID xassələri
4. BASE modeli
5. Real dünya nümunələri
6. PHP İmplementasiyası
7. İntervyu Sualları

---

## CAP Theorem nədir

CAP Theorem 2000-ci ildə Eric Brewer tərəfindən irəli sürülmüş, sonra formal olaraq sübut edilmişdir. Distributed sistemlər eyni anda aşağıdakı 3 xassənin yalnız 2-sini təmin edə bilər:

```
         Consistency (C)
              /\
             /  \
            /    \
           /  ???  \
          /----------\
Availability (A) --- Partition Tolerance (P)
```

**Consistency (Ardıcıllıq):**
Hər oxuma ən son yazını və ya xəta qaytarır. Bütün node-lar eyni anda eyni datanı görür.

**Availability (Mövcudluq):**
Hər sorğu cavab alır (xəta da ola bilər, amma timeout yox). Node-lardan bəzisi çöksə belə sistem cavab verir.

**Partition Tolerance (Bölünmə dözümlülüyü):**
Şəbəkə bölünməsi (node-lar arası mesajların itirilməsi) baş versə belə sistem işləməyə davam edir.

**Əsas həqiqət:** Real distributed sistemlərdə network partition qaçılmazdır. Buna görə praktikada seçim **CP vs AP** arasındadır.

```
Network partition baş verdi:
Node A ←✗→ Node B

CP sistem: Node B cavab verməyi dayandırır (availability fəda edilir)
AP sistem: Node B köhnə data ilə cavab verir (consistency fəda edilir)
```

---

## CP, AP, CA Sistemlər

### CP Sistemlər (Consistency + Partition Tolerance)

Partition zamanı availability fəda edilir. Sistem cavab verməkdənsə yanlış cavab verməyi rədd edir.

```
Client → Leader Node
              |
         [Partition]
              |
         Follower Node
         (cavab vermir, leader ilə sync deyil)
```

**Nümunələr:**
- **PostgreSQL / MySQL** (single-node və ya synchronous replication)
- **Redis** (default)
- **HBase**
- **Zookeeper**
- **etcd**

### AP Sistemlər (Availability + Partition Tolerance)

Partition zamanı consistency fəda edilir. Sistem hər zaman cavab verir, amma köhnə data ola bilər.

```
Client A → Node 1 → write: price=100
               |
          [Partition]
               |
Client B → Node 2 → read: price=90  (köhnə data)
```

**Nümunələr:**
- **Cassandra**
- **DynamoDB** (default)
- **CouchDB**
- **Riak**

### CA Sistemlər (Consistency + Availability)

Partition Tolerance yoxdur — yəni yalnız single-node sistemlər. Real distributed sistemdə mümkün deyil.

**Nümunələr:**
- Single-node PostgreSQL
- Single-node MySQL

---

## ACID Xassələri

ACID relational database-lər üçün transaction zəmanətləridir:

```
BEGIN TRANSACTION;
  UPDATE accounts SET balance = balance - 100 WHERE id = 1;
  UPDATE accounts SET balance = balance + 100 WHERE id = 2;
COMMIT;
```

**A — Atomicity (Atomiklik):**
Transaction ya tam icra olunur, ya da heç biri. Yarıda qalan vəziyyət olmur.

**C — Consistency (Ardıcıllıq):**
Transaction database-i bir valid vəziyyətdən digərinə aparır. Constraints pozulmur.

**I — Isolation (İzolasiya):**
Paralel transaction-lar bir-birinə görünmür (müəyyən isolation level-ə qədər).

```
Isolation Levels:
READ UNCOMMITTED  → Dirty read mümkün
READ COMMITTED    → Dirty read yox, amma non-repeatable read var
REPEATABLE READ   → Non-repeatable read yox, amma phantom read var
SERIALIZABLE      → Tam izolasiya, amma ən yavaş
```

**D — Durability (Davamlılıq):**
Commit olunmuş transaction sistem çöksə belə qalır (WAL/redo log).

---

## BASE Modeli

BASE NoSQL sistemlər üçün ACID-ə alternativdir. Eventual consistency üzərindədir.

**BA — Basically Available:**
Sistem həmişə cavab verir, amma data köhnə ola bilər.

**S — Soft State:**
Sistem vəziyyəti zamanla dəyişə bilər, input olmasa belə (background sync).

**E — Eventually Consistent:**
Sistem nəhayət ardıcıl vəziyyətə çatacaq — amma nə vaxt bəlli deyil.

```
BASE Timeline:

t=0:  User A writes: stock=10 → Node 1
t=1:  User B reads from Node 2: stock=15  (köhnə)
t=2:  Replication tamamlanır
t=3:  User C reads from Node 2: stock=10  (yeni, eventually consistent)
```

---

## Real Dünya Nümunələri

### PostgreSQL — CP

```
Primary ──sync── Standby
   |
   └── Writes burada
   
Standby geridə qalırsa write-lar bloklanır (synchronous_commit=on)
```

### Cassandra — AP + Tunable Consistency

```
Cassandra Ring:
   Node A
  /       \
Node C   Node B

Replication Factor = 3
Write: bütün 3 node-a yazılır
Read: QUORUM = 2 node-dan oxu

consistency_level=ONE   → AP (fast, stale data possible)
consistency_level=QUORUM → CP-yə yaxın
consistency_level=ALL   → CP (ən yavaş)
```

### Redis — CP (default Sentinel/Cluster)

```
Master → Slave 1
       → Slave 2

Master çökdükdə:
- Sentinel yeni master seçir
- Bu müddətdə writes reject edilir (availability fəda)
```

---

## PHP İmplementasiyası

```php
<?php

/**
 * CAP Trade-off demo: Consistency vs Availability seçimi
 */

// AP pattern: Stale data qəbul edilir, həmişə cavab ver
class AvailabilityFirstRepository
{
    private Redis $redis;
    private PDO $db;
    private int $staleTtl = 300; // 5 dəqiqə köhnə data qəbul edilir

    public function getProductStock(int $productId): array
    {
        $cacheKey = "stock:{$productId}";

        // Cache-dən oxu (köhnə ola bilər — AP)
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false) {
            return json_decode($cached, true) + ['source' => 'cache'];
        }

        // DB-dən oxu
        try {
            $stmt = $this->db->prepare('SELECT stock, updated_at FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->redis->setex($cacheKey, $this->staleTtl, json_encode($data));
                return $data + ['source' => 'db'];
            }
        } catch (PDOException $e) {
            // DB əlçatmaz — fallback: köhnə cache varsa istifadə et
            $staleKey = "stock:stale:{$productId}";
            $stale = $this->redis->get($staleKey);
            if ($stale) {
                return json_decode($stale, true) + ['source' => 'stale_cache', 'warning' => 'DB unavailable'];
            }
        }

        return ['stock' => 0, 'source' => 'default', 'warning' => 'No data available'];
    }
}

// CP pattern: Köhnə data qəbul edilmir, əgər əmin deyilsə xəta ver
class ConsistencyFirstRepository
{
    private PDO $primary;
    private PDO $replica;

    public function getAccountBalance(int $accountId, bool $strongConsistency = true): float
    {
        if ($strongConsistency) {
            // Həmişə primary-dən oxu (AP fəda edilir — replica lag yoxdur)
            $stmt = $this->primary->prepare(
                'SELECT balance FROM accounts WHERE id = ? FOR SHARE'
            );
        } else {
            // Replica-dan oxu (stale ola bilər)
            $stmt = $this->replica->prepare(
                'SELECT balance FROM accounts WHERE id = ?'
            );
        }

        $stmt->execute([$accountId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new \RuntimeException("Account {$accountId} not found");
        }

        return (float) $result['balance'];
    }

    // ACID transaction nümunəsi
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        $this->primary->beginTransaction();

        try {
            // Pessimistic lock — Isolation
            $stmt = $this->primary->prepare(
                'SELECT balance FROM accounts WHERE id = ? FOR UPDATE'
            );

            $stmt->execute([$fromId]);
            $from = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt->execute([$toId]);
            $to = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($from['balance'] < $amount) {
                throw new \RuntimeException('Insufficient funds');
            }

            // Atomicity: hər ikisi ya olur ya olmur
            $this->primary->prepare(
                'UPDATE accounts SET balance = balance - ? WHERE id = ?'
            )->execute([$amount, $fromId]);

            $this->primary->prepare(
                'UPDATE accounts SET balance = balance + ? WHERE id = ?'
            )->execute([$amount, $toId]);

            $this->primary->commit(); // Durability: WAL-a yazılır
        } catch (\Throwable $e) {
            $this->primary->rollBack(); // Atomicity: hər şey geri alınır
            throw $e;
        }
    }
}

// BASE pattern: Eventual consistency ilə inventory sync
class BaseInventoryService
{
    private Redis $redis;

    /**
     * Əvvəlcə local state-i yenilə (Soft State),
     * background-da digər node-larla sync et (Eventually Consistent)
     */
    public function decrementStock(int $productId, int $quantity): bool
    {
        $key = "inventory:{$productId}";

        // Optimistic local decrement
        $newStock = $this->redis->decrBy($key, $quantity);

        if ($newStock < 0) {
            // Geri al — oversell protection
            $this->redis->incrBy($key, $quantity);
            return false;
        }

        // Async sync to DB (eventually consistent)
        $this->redis->lPush('inventory:sync:queue', json_encode([
            'product_id' => $productId,
            'delta'      => -$quantity,
            'timestamp'  => microtime(true),
        ]));

        return true; // Basically Available: həmişə cavab ver
    }
}
```

---

## İntervyu Sualları

- CAP teoreminin 3 xassəsini izah edin. Real distributed sistemdə hansı ikisini seçmək məcburiyyətindəsiniz?
- Cassandra-nın tunable consistency-si CAP teoremlə necə əlaqəlidir?
- ACID-in "Isolation" xassəsi nə deməkdir? Hansı isolation level-lər var və aralarındakı fərq nədir?
- BASE modeli nədir? Hansı hallarda ACID-dən BASE-ə keçmək düzgündür?
- PostgreSQL synchronous vs asynchronous replication-da hansı CAP trade-off var?
- "Eventual consistency" sistemdə bir istifadəçinin öz yazısını oxuya bilməməsi problemi necə həll edilir?
- Split-brain problemi nədir? CP sistem bunu necə həll edir?
- Redis Sentinel cluster-da master çökdükdə nə baş verir? Bu CP yoxsa AP-dir?
