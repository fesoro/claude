# Optimistic vs Pessimistic Locking (Senior ⭐⭐⭐)

## İcmal
Concurrent update zamanı data integrity-ni qorumaq üçün iki əsas strategiya var: pessimistic locking (əvvəlcədən bloklama) və optimistic locking (conflict olduqda reject). Seçim read/write ratio, conflict frequency, latency tələbinə, və user experience-a görə edilir. Bu sual Senior interview-larda demək olar ki, hər zaman çıxır.

## Niyə Vacibdir
Real production sistemlərindəki race condition-ları həll etmək bu iki mexanizmdən birini tələb edir. İnterviewer sizin hansı halda hansını seçdiyinizi, trade-off-ları bildiyinizi, real kod nümunəsi verə bildiyinizi yoxlayır. "Sadəcə transaction istifadə et" cavabı qəbul edilmir — transaction yalnız atomicity zəmanətləndirir, concurrent conflict-ləri həll etmir.

## Əsas Anlayışlar

- **Pessimistic Locking:** "Conflict mütləq olacaq" fərziyyəsi — data-nı oxuyanda lock al, işin bitdikdə burax. Digər transaction gözləyir
- **Optimistic Locking:** "Conflict nadir olacaq" fərziyyəsi — lock yoxdur, commit zamanı conflict yoxla. Conflict varsa reject et, client retry etməlidir
- **SELECT FOR UPDATE:** Pessimistic locking-in SQL qarşılığı. Row-u exclusive lock-la bloklar. Digər `FOR UPDATE` ya da `UPDATE` gözləyir
- **SELECT FOR SHARE:** Shared lock — başqaları da `FOR SHARE` edə bilər amma `FOR UPDATE` edə bilməz. "Oxuyacam, başqaları da oxusun amma dəyişdirməsin"
- **Version Column:** Optimistic locking-in klassik implementasiyası. Hər update-də `version` artır; commit zamanı `WHERE version = expected` yoxlanılır
- **Lost Update Problem:** İki transaction eyni row-u oxuyur (version=1), hər ikisi update edir — sonuncunun yazısı birincini silir. Locking olmadan default davranış
- **Stale Read:** Optimistic locking-də data oxunub conflict yoxlanana qədər başqası dəyişdirə bilər. Bu "stale" (köhnə) data ilə iş görmək deməkdir
- **Retry Logic:** Optimistic locking conflict-dən sonra retry tələb edir — application-da retry loop yazılmalıdır; exponential backoff lazımdır
- **Deadlock Risk:** Pessimistic locking-də iki transaction bir-birinin lock-unu gözləyirsə deadlock. Optimistic locking-də deadlock yoxdur (lock yoxdur)
- **Throughput:** Optimistic — lock yoxdur, throughput yüksəkdir; conflict az olduqda ideal. Pessimistic — gözləmə var, throughput aşağıdır, lakin conflict halında daha predictable
- **Retry Storm:** Optimistic locking-də yüksək conflict varsa hər transaction dəfələrlə retry edir → sistem daha da yüklənir → daha çox conflict. Feedback loop problemi
- **NOWAIT:** `SELECT FOR UPDATE NOWAIT` — lock ala bilmirsə dərhal error ver, gözləmə. Timeout əvəzinə instant failure
- **SKIP LOCKED:** `SELECT FOR UPDATE SKIP LOCKED` — locked row-ları atla, növbəti açıq row-u götür. Job queue processing üçün ideal pattern
- **CAS (Compare-And-Swap):** `UPDATE ... WHERE version = X AND id = Y` — atomik müqayisə-yaz. 0 affected rows = conflict
- **Timestamp-based Optimistic:** Version integer əvəzinə `updated_at` timestamp istifadə. Dezavantaj: eyni saniyədə iki update conflict-i gizlədə bilər
- **Application-Level Lock:** Redis `SET NX` ilə application lock. Database lock-dan fərqli — network partition-da stale lock riski var

## Praktik Baxış

**Interview-da yanaşma:**
- "Conflict ehtimalı nədir?" sualı ilə başlayın — bu seçim kriteriyadır
- Nümunə: ticket booking = pessimistic (son 1 bileti almaq istəyənlər çox → yüksək conflict), user profile update = optimistic (hər user öz profili dəyişir → conflict nadir)
- Laravel-də hər ikisini kod nümunəsi ilə göstərə bilmək

**Follow-up suallar:**
- "Optimistic locking-də retry loop necə yazılır? Max retry nə qədər?"
- "SKIP LOCKED nə üçün istifadə olunur?" — Job queue: hər worker fərqli job götürür
- "Replication ilə optimistic locking birlikdə işlərmi?" — Read replica-dan köhnə data oxunarsa yanlış version görünər — lag riski
- "Distributed sistemdə locking necə işləyir?" — Database lock database boundary-sindən kənara çıxmır; Redis ya da ZooKeeper distributed lock lazım ola bilər
- "Deadlock olduqda nə edirsiniz?" — `DEADLOCK_TIMEOUT`, retry, lock ordering

**Ümumi səhvlər:**
- "Pessimistic həmişə daha etibarlıdır" demək — throughput cost-unu qeyd etməmək
- Optimistic locking-də version column unutmaq — "timestamp kifayətdir" demək
- Retry logic yazmamaq — conflict olduqda user "500 error" alır
- SKIP LOCKED pattern-ni bilməmək — job queue üçün əvəzolunmazdır
- Yüksək conflict-li sistemdə optimistic seçmək → retry storm

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- SKIP LOCKED ilə job queue tətbiqini bilmək
- "Conflict rate-ə görə seçirik" demək
- Distributed sistemdə database lock-un kifayət etmədiyini bilmək
- Retry storm-u və onun çözümünü (circuit breaker, queue) izah etmək

## Nümunələr

### Tipik Interview Sualı
"Bilet satışı sistemi dizayn edirsiniz. Son 1 bilet qalıb, 1000 user eyni anda onu almaq istəyir. Necə handle edərdiniz?"

### Güclü Cavab
Bu yüksək conflict ehtimalı olan scenaridir — 1000 user 1 bilet üçün yarışır. İki seçim var:

**Pessimistic locking:** `SELECT FOR UPDATE` ilə bilet row-unu kilid altına al. Birinci user kilid alır, digərlər gözləyir. Birinci `quantity > 0` yoxlar, azaldır, commit edir; növbəti gəlir, artıq 0-dır, reject. UX aydındır — user ya alır ya da "sold out" mesajı alır.

**Optimistic locking:** Version column ilə. 1000 user eyni anda `version=1` oxuyur, hamısı `WHERE version = 1` ilə update cəhd edir. Yalnız 1-i keçir (1 affected row), digər 999-u retry edir. Retry count × retry delay = çox gecikir, retry storm yarana bilər.

**Böyük miqyaslı sistem üçün daha yaxşı:** Redis `DECR` ilə atomik counter. `DECR ticket:1` əmri atomic-dir — 0-dan aşağı düşürsə "sold out". Database-ə yalnız uğurlu rezervasiyalar yazılır. Bu 100,000+ concurrent user üçün scalable-dir.

Saytda: ödəniş prosesi kimi kritik axınlarda pessimistic locking güvənilirdir; Redis counter ilə pre-check performanslıdır.

### Kod Nümunəsi
```sql
-- PESSIMISTIC LOCKING: SELECT FOR UPDATE
-- Terminal 1
BEGIN;
SELECT id, quantity, version
FROM tickets
WHERE id = 1
FOR UPDATE;
-- Digər transaction bu nöqtədə gözləyəcək (ya da timeout)

UPDATE tickets
SET quantity = quantity - 1
WHERE id = 1 AND quantity > 0;

INSERT INTO purchases (ticket_id, user_id, created_at)
VALUES (1, 42, NOW());

COMMIT;

-- NOWAIT — gözləmə, dərhal fail
BEGIN;
SELECT * FROM tickets WHERE id = 1 FOR UPDATE NOWAIT;
-- ERROR: could not obtain lock on row in relation "tickets"
-- Application bunu catch edib "busy, try again" mesajı verə bilər
ROLLBACK;

-- SKIP LOCKED — job queue processing
-- Worker 1:
BEGIN;
SELECT id, payload, attempts
FROM jobs
WHERE status = 'pending'
  AND scheduled_at <= NOW()
ORDER BY priority DESC, created_at ASC
FOR UPDATE SKIP LOCKED
LIMIT 1;
-- Worker 2 eyni anda çalışırsa, Worker 1-in götürdüyü job-u atlar
-- Conflict yoxdur — hər worker fərqli job götürür
UPDATE jobs SET status = 'processing', locked_at = NOW()
WHERE id = :job_id;
COMMIT;
```

```php
// Laravel-də Pessimistic Locking
DB::transaction(function () use ($ticketId, $userId) {
    // FOR UPDATE — bu row kilid altındadır
    $ticket = Ticket::where('id', $ticketId)
        ->lockForUpdate()
        ->first();

    if (!$ticket || $ticket->quantity <= 0) {
        throw new TicketSoldOutException("Bilet satılıb.");
    }

    $ticket->decrement('quantity');

    Purchase::create([
        'ticket_id' => $ticketId,
        'user_id'   => $userId,
        'price'     => $ticket->price,
    ]);
});

// Shared lock — oxuma üçün (digərləri oxuya bilər, update edə bilməz)
$ticket = Ticket::where('id', $ticketId)
    ->sharedLock()   // SELECT ... FOR SHARE
    ->first();

// NOWAIT — gözləmə, dərhal exception
try {
    DB::statement('SET LOCAL lock_timeout = \'0\'');
    $ticket = Ticket::lockForUpdate()->find($ticketId);
} catch (\Exception $e) {
    return response()->json(['error' => 'Server busy, retry'], 503);
}
```

```php
// Laravel-də Optimistic Locking (version column ilə)
// Migration: $table->unsignedInteger('version')->default(1);

class UserProfile extends Model
{
    protected $fillable = ['name', 'bio', 'avatar_url', 'version'];

    public function updateWithOptimisticLock(array $data): bool
    {
        $expectedVersion = $this->version;

        $affected = static::where('id', $this->id)
            ->where('version', $expectedVersion)
            ->update(array_merge($data, [
                'version' => $expectedVersion + 1,
            ]));

        if ($affected === 0) {
            throw new OptimisticLockException(
                "Conflict: başqa biri bu profili dəyişdirdi. Yenidən cəhd edin."
            );
        }

        $this->version = $expectedVersion + 1;
        return true;
    }
}

// Retry ilə istifadə
function updateUserProfile(int $userId, array $data): void
{
    $maxRetries = 3;
    $baseDelay  = 100; // ms

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $profile = UserProfile::find($userId);
            $profile->updateWithOptimisticLock($data);
            return; // Uğurlu
        } catch (OptimisticLockException $e) {
            if ($attempt === $maxRetries - 1) {
                throw new \RuntimeException(
                    "3 cəhddən sonra conflict həll edilmədi.", 0, $e
                );
            }
            // Exponential backoff: 100ms, 200ms, 400ms
            usleep($baseDelay * (2 ** $attempt) * 1000);
        }
    }
}
```

```sql
-- Optimistic locking — SQL tərəfi
-- UPDATE yalnız version uyğun gəldikdə baş verir
UPDATE user_profiles
SET
  name    = 'Yeni Ad',
  bio     = 'Yeni bio',
  version = version + 1,
  updated_at = NOW()
WHERE id = 42
  AND version = 5;  -- Gözlənilən version

-- affected rows yoxla:
-- 1 = uğurlu (version uyğun idi)
-- 0 = conflict (başqası dəyişdirib, version uyğun gəlmədi)

-- PHP/PDO-da:
-- $stmt->rowCount() === 0 → OptimisticLockException

-- Timestamp-based (daha az dəqiq)
UPDATE orders
SET
  status     = 'shipped',
  updated_at = NOW()
WHERE id = 100
  AND updated_at = '2025-01-15 10:30:00';
-- PROBLEM: eyni saniyədə iki update conflict-i miss edə bilər
```

```php
// SKIP LOCKED ilə Job Queue — Laravel manualı
class JobWorker
{
    public function process(): void
    {
        DB::transaction(function () {
            // Locked job-ları atla — başqa worker götürüb
            $job = DB::table('jobs')
                ->where('status', 'pending')
                ->where('scheduled_at', '<=', now())
                ->orderBy('priority', 'desc')
                ->orderBy('created_at')
                ->lockForUpdate()  // + SKIP LOCKED SQL-ə əlavə olunur
                ->first();
            // Laravel-də SKIP LOCKED üçün:
            // ->whereRaw('1=1 FOR UPDATE SKIP LOCKED')
            // Ya da raw query

            if (!$job) {
                return; // İş yoxdur
            }

            DB::table('jobs')
                ->where('id', $job->id)
                ->update([
                    'status'     => 'processing',
                    'locked_at'  => now(),
                    'locked_by'  => gethostname(),
                    'attempts'   => DB::raw('attempts + 1'),
                ]);

            // İşi icra et
            $this->execute($job);
        });
    }
}
```

```java
// Java/JPA ilə hər iki locking
@Entity
@Table(name = "tickets")
public class Ticket {
    @Id
    private Long id;

    private int quantity;

    @Version  // JPA optimistic locking annotation
    private Long version;
}

// Repository
public interface TicketRepository extends JpaRepository<Ticket, Long> {

    // Pessimistic
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("SELECT t FROM Ticket t WHERE t.id = :id")
    Optional<Ticket> findByIdWithLock(@Param("id") Long id);

    // Optimistic — default (version annotation ilə)
    Optional<Ticket> findById(Long id);
}

// Service
@Transactional
public void purchasePessimistic(Long ticketId, Long userId) {
    Ticket ticket = ticketRepo.findByIdWithLock(ticketId)
        .orElseThrow(TicketNotFoundException::new);

    if (ticket.getQuantity() <= 0) {
        throw new SoldOutException();
    }
    ticket.setQuantity(ticket.getQuantity() - 1);
    ticketRepo.save(ticket);
    purchaseRepo.save(new Purchase(ticketId, userId));
}

@Retryable(value = OptimisticLockingFailureException.class,
           maxAttempts = 3,
           backoff = @Backoff(delay = 100, multiplier = 2))
@Transactional
public void purchaseOptimistic(Long ticketId, Long userId) {
    Ticket ticket = ticketRepo.findById(ticketId)
        .orElseThrow(TicketNotFoundException::new);

    if (ticket.getQuantity() <= 0) {
        throw new SoldOutException();
    }
    ticket.setQuantity(ticket.getQuantity() - 1);
    // OptimisticLockingFailureException atılarsa @Retryable yenidən cəhd edir
    ticketRepo.save(ticket);
}
```

### İkinci Nümunə — Distributed Lock (Redis)

```php
// Database lock database boundary-sindən kənara çıxmır
// Microservices-də Redis distributed lock lazımdır

use Illuminate\Support\Facades\Redis;

class RedisDistributedLock
{
    private string $key;
    private string $token;
    private int $ttlSeconds;

    public function __construct(string $resource, int $ttlSeconds = 30)
    {
        $this->key        = "lock:{$resource}";
        $this->token      = bin2hex(random_bytes(16)); // Unikal token
        $this->ttlSeconds = $ttlSeconds;
    }

    public function acquire(): bool
    {
        // SET NX EX — atomic: yalnız key yoxdursa set et
        $result = Redis::set(
            $this->key,
            $this->token,
            'NX', // Only if Not eXists
            'EX', // EXpire
            $this->ttlSeconds
        );
        return $result !== null;
    }

    public function release(): bool
    {
        // Yalnız öz token-imizlə qoyduğumuz lock-u buraxırıq
        // Lua script: atomic check-and-delete
        $script = <<<LUA
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;
        return Redis::eval($script, 1, $this->key, $this->token) === 1;
    }
}

// İstifadə
$lock = new RedisDistributedLock("ticket:purchase:{$ticketId}");

if (!$lock->acquire()) {
    return response()->json(['error' => 'Başqa birisi bu əməliyyatı edir'], 409);
}

try {
    // Database əməliyyatı — artıq distributed lock altında
    DB::transaction(fn() => $this->processTicketPurchase($ticketId, $userId));
} finally {
    $lock->release(); // Hər halda burax
}
```

## Praktik Tapşırıqlar

- İki parallel request-i simulate edin: pessimistic locking olmadan `UPDATE accounts SET balance = balance - 100 WHERE balance >= 100` — race condition reproduce edin (balance mənfi ola bilər)
- `lockForUpdate()` əlavə edin, eyni testi yenidən edin — race condition yox oldu
- Version column ilə optimistic locking yazın: `UPDATE ... WHERE version = ?` — 0 affected rows halını handle edin
- SKIP LOCKED ilə job queue: 5 parallel worker başladın, hər worker hansı job-u götürdüyünü log edin — overlap olmadığını görün
- Yüksək conflict scenariyasını simulasiya edin: 100 concurrent request, optimistic locking, retry storm-u ölçün — neçəsi max retry-a çatır?
- Redis distributed lock implement edin, TTL bitdikdə lock-un avtomatik buraxıldığını verify edin
- PostgreSQL `lock_timeout` sessiyon parametri ilə deadlock-u timeout ilə handle edin

## Əlaqəli Mövzular
- `07-database-deadlocks.md` — Pessimistic locking deadlock riski — lock ordering strategiyası
- `06-transaction-isolation.md` — Isolation level + locking strategiyası arasındakı fərq
- `02-acid-properties.md` — Atomicity qoruma mexanizmləri
- `09-connection-pooling.md` — Lock gözləmə connection-u saxlayır — pool exhaustion riski
