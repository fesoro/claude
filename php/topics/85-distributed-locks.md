# Distributed Locks

## Mündəricat
1. Lokal lock-ların niyə işləmədiyi
2. Redis SETNX + expire pattern
3. Redis Redlock alqoritmi
4. Database-based locks
5. Fencing tokens
6. PHP İmplementasiyası
7. İntervyu Sualları

---

## Lokal Lock-ların Niyə İşləmədiyi

Single-process tətbiqdə mutex və ya semaphore kifayətdir. Amma distributed sistemdə:

```
Process 1 (Server A)          Process 2 (Server B)
      |                               |
  mutex.lock()                   mutex.lock()
      |                               |
   ✅ locked                       ✅ locked  ← PROBLEM!
      |                               |
  read stock=10               read stock=10
  write stock=9               write stock=9
      |                               |
  mutex.unlock()              mutex.unlock()
  
Nəticə: stock=9 olmalı idi, iki dəfə azaldılmadı!
```

**Niyə lokal lock işləmir:**
- Hər process öz memory-sində ayrı mutex saxlayır
- Başqa server bu mutex-dən xəbərsizdir
- Horizontal scaling-də vəziyyət paylaşılmır

---

## Redis SETNX + Expire Pattern

Ən sadə distributed lock: `SET key value NX EX ttl`

```
Client 1: SET lock:order:42 "client1-uuid" NX EX 30
→ OK (lock alındı)

Client 2: SET lock:order:42 "client2-uuid" NX EX 30
→ nil (lock artıq var)

30 saniyə sonra:
→ lock avtomatik silinir (expire)
```

**NX:** Yalnız key mövcud deyilsə yaz (atomik)
**EX 30:** 30 saniyə sonra avtomatik sil (deadlock qorunması)

**Kritik problem — lock vaxtından əvvəl bitə bilər:**

```
t=0:  Client 1 lock alır (TTL=30s)
t=20: Client 1 hələ işini bitirməyib (GC pause, network delay)
t=30: Lock expire olur
t=31: Client 2 lock alır
t=35: Client 1 işini bitirir, Client 2-nin lock-unu silir!
```

**Həll: Lock value olaraq unikal token istifadə et**

```
DEL əvvəl value-nu yoxla (Lua script ilə atomik):

if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
```

---

## Redis Redlock Alqoritmi

Tək Redis node single point of failure-dır. Redlock 5 müstəqil Redis node üzərindədir:

```
Redis 1   Redis 2   Redis 3   Redis 4   Redis 5
   |          |         |         |         |
Client SETNX → hər birinə eyni anda

Qaydalar:
- Quorum = N/2 + 1 = 3 node-dan cavab gəlməlidir
- Əgər 3+ node-da lock alınmışsa → lock uğurludur
- Əgər < 3 node-da alınmışsa → bütün node-lardan lock silinir

Validity time = TTL - elapsed_time - clock_drift
```

**Redlock Timeline:**

```
t=0ms:  Client → Redis 1 (OK, 10ms)
t=10ms: Client → Redis 2 (OK, 12ms)
t=22ms: Client → Redis 3 (timeout, 50ms)
t=72ms: Client → Redis 4 (OK, 8ms)
t=80ms: Client → Redis 5 (OK, 9ms)

4/5 node-da lock var → Quorum (3) keçdi → Uğurlu
Keçən vaxt: 80ms
Validity: 30000ms - 80ms - drift(10ms) = 29910ms
```

---

## Database-Based Locks

Redis mövcud deyilsə və ya əlavə dependency istənmirsə:

### Pessimistic Lock (SELECT FOR UPDATE)

```sql
BEGIN;
SELECT * FROM jobs WHERE id = 1 FOR UPDATE;
-- İş görülür
UPDATE jobs SET status = 'done' WHERE id = 1;
COMMIT;
```

```
Client 1: SELECT ... FOR UPDATE → row lock alır
Client 2: SELECT ... FOR UPDATE → bloklanır (gözləyir)
Client 1: COMMIT → lock açılır
Client 2: indi oxuya bilər
```

### Advisory Locks (PostgreSQL)

```sql
-- Session-level lock (rəqəm: hash of resource name)
SELECT pg_try_advisory_lock(12345);  -- true/false qaytarır

-- İş görülür

SELECT pg_advisory_unlock(12345);
```

**Üstünlüyü:** Row lock-dan fərqli olaraq hər hansı resursa lock qoya bilərsiniz.

---

## Fencing Tokens

Lock expire olub başqa client almış olsa belə köhnə client yaza bilər — **fencing token** bunu önləyir:

```
               Lock Service
                    |
Client 1 → lock → token=33
Client 2 → lock → token=34  (Client 1-in lock-u expire oldu)

Client 1 → Storage: write(data, token=33)
Storage:  "33 < 34, reject!" ← QORUNMA

Client 2 → Storage: write(data, token=34)
Storage:  "34 == latest, accept" ✅
```

**Token monotonic artmalıdır.** Storage hər zaman ən son token-dən kiçik yazıları rədd edir.

```
Database tərəfindən tətbiq:

CREATE TABLE lock_tokens (
    resource_name VARCHAR(255) PRIMARY KEY,
    current_token BIGINT NOT NULL DEFAULT 0
);

-- Lock alanda token artırılır:
UPDATE lock_tokens SET current_token = current_token + 1
WHERE resource_name = 'order:42'
RETURNING current_token;
```

---

## PHP İmplementasiyası

```php
<?php

class RedisDistributedLock
{
    private Redis $redis;
    private string $lockKey;
    private string $lockToken;
    private int $ttlMs;

    // Lua script: atomik yoxla-sil əməliyyatı
    private const RELEASE_SCRIPT = <<<'LUA'
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("del", KEYS[1])
    else
        return 0
    end
    LUA;

    // Lua script: lock uzatma (yalnız sahibi uzada bilər)
    private const EXTEND_SCRIPT = <<<'LUA'
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("pexpire", KEYS[1], ARGV[2])
    else
        return 0
    end
    LUA;

    public function __construct(
        Redis $redis,
        string $resource,
        int $ttlMs = 30000
    ) {
        $this->redis    = $redis;
        $this->lockKey  = "lock:{$resource}";
        $this->lockToken = bin2hex(random_bytes(16)); // Unikal token
        $this->ttlMs    = $ttlMs;
    }

    /**
     * Lock almağa çalış
     *
     * @param int $retryTimes Neçə dəfə cəhd et
     * @param int $retryDelayMs Cəhdlər arasında gözləmə (ms)
     */
    public function acquire(int $retryTimes = 3, int $retryDelayMs = 200): bool
    {
        for ($i = 0; $i < $retryTimes; $i++) {
            $result = $this->redis->set(
                $this->lockKey,
                $this->lockToken,
                ['NX', 'PX' => $this->ttlMs]
            );

            if ($result === true) {
                return true;
            }

            if ($i < $retryTimes - 1) {
                usleep($retryDelayMs * 1000);
            }
        }

        return false;
    }

    /**
     * Lock-u burax (yalnız sahibi buraxa bilər)
     */
    public function release(): bool
    {
        $result = $this->redis->eval(
            self::RELEASE_SCRIPT,
            [$this->lockKey, $this->lockToken],
            1
        );

        return (int) $result === 1;
    }

    /**
     * TTL uzat (uzun sürən əməliyyatlar üçün)
     */
    public function extend(int $additionalMs): bool
    {
        $result = $this->redis->eval(
            self::EXTEND_SCRIPT,
            [$this->lockKey, $this->lockToken, (string) $additionalMs],
            1
        );

        return (int) $result === 1;
    }

    /**
     * Callback-i lock altında icra et (auto-release)
     */
    public function withLock(callable $callback, int $retryTimes = 3): mixed
    {
        if (!$this->acquire($retryTimes)) {
            throw new \RuntimeException(
                "Could not acquire lock for: {$this->lockKey}"
            );
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
}

// Fencing token ilə database lock
class DatabaseLockWithFencing
{
    private PDO $db;

    public function acquireWithToken(string $resource): ?int
    {
        $stmt = $this->db->prepare(<<<SQL
            INSERT INTO distributed_locks (resource, token, acquired_at, expires_at)
            VALUES (:resource, nextval('lock_token_seq'), NOW(), NOW() + INTERVAL '30 seconds')
            ON CONFLICT (resource) DO UPDATE
              SET token      = nextval('lock_token_seq'),
                  acquired_at = NOW(),
                  expires_at  = NOW() + INTERVAL '30 seconds'
              WHERE distributed_locks.expires_at < NOW()
            RETURNING token
        SQL);

        $stmt->execute([':resource' => $resource]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['token'] : null;
    }

    public function writeWithFencing(string $resource, array $data, int $token): bool
    {
        // Yalnız ən son token sahibi yaza bilər
        $stmt = $this->db->prepare(<<<SQL
            UPDATE resource_data
            SET payload      = :payload,
                last_token   = :token
            WHERE resource_name = :resource
              AND last_token < :token
        SQL);

        $stmt->execute([
            ':payload'  => json_encode($data),
            ':token'    => $token,
            ':resource' => $resource,
        ]);

        return $stmt->rowCount() > 0;
    }
}

// İstifadə nümunəsi
function processOrder(int $orderId, Redis $redis, PDO $db): void
{
    $lock = new RedisDistributedLock($redis, "order:{$orderId}", ttlMs: 15000);

    $lock->withLock(function () use ($orderId, $db) {
        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order['status'] !== 'pending') {
            return; // İdempotency: artıq işlənib
        }

        // Uzun sürən əməliyyat
        processPayment($orderId);

        $db->prepare('UPDATE orders SET status = ? WHERE id = ?')
           ->execute(['processed', $orderId]);
    });
}
```

---

## İntervyu Sualları

- PHP `flock()` funksiyası niyə distributed sistemdə işləmir?
- Redis `SET key value NX EX 30` əmrinin atomikliyi niyə vacibdir? `SETNX` + `EXPIRE` iki ayrı əmr istifadə etsək nə problem yaranar?
- Lock expire olub başqa process almışdıqda köhnə process da hələ işləyirsə nə baş verir? Fencing token bu problemi necə həll edir?
- Redlock alqoritmi 5 Redis node-dan neçəsindən lock almalıdır? Niyə bu say seçilib?
- Database advisory locks ilə row-level locks arasındakı fərq nədir?
- Lock alan process crash olsa deadlock yaranmaması üçün nə edilir?
- Redlock-a qarşı Martin Kleppmann-ın tənqidi nədir?
- Lock TTL-ni nə qədər seçmək lazımdır? Çox qısa və çox uzun olmasının fəsadları nədir?
