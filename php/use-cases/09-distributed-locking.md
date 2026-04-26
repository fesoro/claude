# Distributed Locking ve Concurrency Control (Senior)

## Problem

Sizin application-unuz bir nece server-de (horizontal scaling) isleyir. Iki farkli server eyni anda eyni resursa daxil olur ve data corruption bas verir.

Meselen:
- 2 request eyni anda eyni mehsulun son edadini almaga calisir
- Hər ikisi "1 eded qaldi" gorur
- Her ikisi sifaris verir
- Neticede -1 eded mehsul olur (oversell!)

Bu **race condition**-dur ve **distributed locking** ile hell olunur.

### Problem niyə yaranır?

Tək serverdə `DB::transaction()` + `lockForUpdate()` race condition-u həll edir. Lakin horizontal scaling-də (2+ server) hər serverin öz process məkanı var — DB lock yalnız DB səviyyəsindədir, amma server-lər arasında koordinasiya olmur. Daha kritik ssenari: iki server eyni cron job-u eyni anda işlədəndə — duplicate email kampaniyası, ikiqat hesabat, double charge. Bu halda tək DB transaction kifayət deyil; server-lər arası koordinasiya üçün Redis kimi paylaşılan mərkəzi lock mənbəyi lazımdır.

---

## Race Condition Nedir?

Race condition -- iki ve ya daha cox process/thread eyni anda eyni datani oxuyub deyisdirende, neticenin icra siralamasindan asili olmasidir.

```
Server A:                    Server B:
1. stock = read(product)     1. stock = read(product)
   stock = 1                    stock = 1
2. if stock > 0:             2. if stock > 0:
3.   stock = stock - 1       3.   stock = stock - 1
4.   write(product, 0)       4.   write(product, 0)
5.   create_order()          5.   create_order()

Neticede: 2 sifaris yaradildi, amma stock 1 idi!
```

---

## Mutex ve Semaphore

### Mutex (Mutual Exclusion)
Yalniz **bir** process/thread resursa daxil ola biler. Digerleri gozlemeli ve ya imtina almalidir.

### Semaphore
Eyni anda **N** process/thread daxil ola biler. Meselen, API rate limiting ucun: eyni anda max 5 request.

---

## Laravel Cache::lock -- Esas Mexanizm

Laravel-in built-in lock mexanizmi Redis, Memcached, DynamoDB ve ya database driver-leri ile isleyir.

### Sade Istifade

*Bu kod `Cache::lock` ilə sadə distributed lock alıb try/finally ilə release etməyi göstərir:*

```php
use Illuminate\Support\Facades\Cache;

// 10 saniye muddetlik lock al
$lock = Cache::lock('process-order-123', 10);

if ($lock->get()) {
    try {
        // Bu blok yalniz bir process terefinden icra olunur
        $this->processOrder(123);
    } finally {
        // Mutleq release et!
        $lock->release();
    }
} else {
    // Lock alinmadi -- basqa process artiq isleyir
    return response()->json([
        'error' => 'Sifarisiniz artiq islenir, zehmet olmasa gozleyin.',
    ], 409);
}
```

### Block ile Gozleme

*Bu kod lock almaq üçün müəyyən müddət gözləyən və timeout halında `LockTimeoutException` atan `block()` metodunu göstərir:*

```php
use Illuminate\Support\Facades\Cache;

$lock = Cache::lock('generate-report', 60);

// 10 saniye gozle, lock alinmasa exception at
try {
    $lock->block(10, function () {
        // Lock alindi, isimizi gorek
        $this->generateMonthlyReport();
    });
} catch (LockTimeoutException $e) {
    // 10 saniye gozledik, amma lock alinmadi
    Log::warning('Report generation lock timeout');
    return response()->json(['error' => 'Sistem mesguldur.'], 503);
}
```

---

## Real-World Numune 1: Concurrent Order Processing

Mehsul sifarisi zamani race condition-un qarsisini almaq:

*Bu kod Redis distributed lock ilə DB `lockForUpdate()` birləşdirərək concurrent order race condition-unu önləyən service-i göstərir:*

```php
// app/Services/OrderService.php
namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Cache\Lock;

class OrderService
{
    /**
     * Sifaris yarat -- distributed lock ile qorunmus
     */
    public function placeOrder(int $userId, int $productId, int $quantity): Order
    {
        // Mehsula ozel lock -- farkli mehsullar paralel islenilir
        $lockKey = "order:product:{$productId}";
        $lock = Cache::lock($lockKey, 30); // 30 saniye max

        try {
            // 5 saniye gozle, sonra timeout
            return $lock->block(5, function () use ($userId, $productId, $quantity) {
                return DB::transaction(function () use ($userId, $productId, $quantity) {
                    // Lock icinde en son deyeri oxu
                    $product = Product::lockForUpdate()->findOrFail($productId);

                    if ($product->stock < $quantity) {
                        throw new \DomainException(
                            "Kifayet qeder mehsul yoxdur. Movcud: {$product->stock}"
                        );
                    }

                    // Stock-u azalt
                    $product->decrement('stock', $quantity);

                    // Sifarisi yarat
                    $order = Order::create([
                        'user_id'    => $userId,
                        'product_id' => $productId,
                        'quantity'   => $quantity,
                        'total'      => $product->price * $quantity,
                        'status'     => 'confirmed',
                    ]);

                    return $order;
                });
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw new \RuntimeException(
                'Sifaris sistemi hal-hazirda mesguldur. Yeniden cehd edin.'
            );
        }
    }
}
```

### Niye hem distributed lock, hem de `lockForUpdate()` istifade edirik?

- **Distributed lock (Redis):** Farkli server-lerdeki process-leri sinxronizasiya edir
- **lockForUpdate() (Database):** Database seviyyesinde row-level lock -- eyni transaction icinde data consistency temin edir

Bu "belt and suspenders" yanasmasi en etibarlisidir.

---

## Real-World Numune 2: Cron Job Duplication Prevention

Coxlu server olduqda, eyni cron job butun server-lerde eyni anda isleyir:

*Bu kod `withoutOverlapping()` və `onOneServer()` ilə cron job-un yalnız bir serverdə işləməsini təmin edir:*

```php
// app/Console/Kernel.php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule): void
{
    // withoutOverlapping() -- eyni anda yalniz bir server islesin
    $schedule->command('reports:generate-daily')
        ->dailyAt('02:00')
        ->withoutOverlapping(30)  // 30 deq lock
        ->onOneServer();          // Yalniz 1 serverde

    $schedule->command('invoices:send-reminders')
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer();
}
```

`onOneServer()` daxili olaraq cache lock istifade edir. Amma custom job-larda ozumuz yazmaliyiq:

*Bu kod distributed lock ilə eyni anda yalnız bir serverdə işləyən payment processing command-ını göstərir:*

```php
// app/Console/Commands/ProcessPendingPayments.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessPendingPayments extends Command
{
    protected $signature = 'payments:process-pending';
    protected $description = 'Gozlemede olan odemeleri isle';

    public function handle(): int
    {
        $lock = Cache::lock('cmd:process-pending-payments', 600); // 10 deq

        if (!$lock->get()) {
            $this->warn('Bu command artiq basqa serverde isleyir.');
            Log::info('ProcessPendingPayments skipped -- lock held by another process.');
            return self::SUCCESS;
        }

        try {
            $this->info('Gozlemede olan odemeler islenir...');

            $pendingPayments = Payment::where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(5))
                ->cursor();

            foreach ($pendingPayments as $payment) {
                try {
                    $this->processPayment($payment);
                    $this->info("Payment #{$payment->id} islendi.");
                } catch (\Throwable $e) {
                    Log::error("Payment #{$payment->id} ugursuz: {$e->getMessage()}");
                    $this->error("Payment #{$payment->id} ugursuz.");
                }
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function processPayment(Payment $payment): void
    {
        // Her payment ucun ayri lock -- paralel processing mumkun olsun
        $paymentLock = Cache::lock("payment:{$payment->id}", 120);

        if (!$paymentLock->get()) {
            Log::info("Payment #{$payment->id} artiq islenir, skip.");
            return;
        }

        try {
            // Payment processing mentiqi...
            $payment->update(['status' => 'processed']);
        } finally {
            $paymentLock->release();
        }
    }
}
```

---

## Redis Distributed Lock (Redlock Algorithm)

Laravel-in `Cache::lock` sade Redis SETNX emrine esaslanir. Daha etibarlisi Redlock algorithm-dir. Bu algorithm coxlu musteqil Redis node istifade edir.

### Redlock Prinsipi

1. Cari zamani qeyd et
2. N Redis node-dan ardici olaraq lock almaga calis
3. Eger N/2 + 1 (majority) node-dan lock alinibsa VE kecen zaman lock TTL-den azdirsaa, lock alinib sayilir
4. Lock alinamazsa, butun node-lardan release et

*Bu kod çoxlu müstəqil Redis node-undan majority lock alan Redlock alqoritminin PHP implementasiyasını göstərir:*

```php
// app/Services/RedlockService.php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedlockService
{
    private array $servers;
    private int $retryCount;
    private int $retryDelay; // ms
    private float $clockDriftFactor = 0.01;

    public function __construct()
    {
        // Coxlu Redis instance
        $this->servers = [
            Redis::connection('lock1'),
            Redis::connection('lock2'),
            Redis::connection('lock3'),
        ];
        $this->retryCount = 3;
        $this->retryDelay = 200;
    }

    /**
     * Lock almaga calis
     */
    public function lock(string $resource, int $ttl): ?array
    {
        $token = bin2hex(random_bytes(20));

        for ($attempt = 0; $attempt < $this->retryCount; $attempt++) {
            $startTime = microtime(true) * 1000;
            $locksAcquired = 0;

            foreach ($this->servers as $server) {
                if ($this->acquireOnServer($server, $resource, $token, $ttl)) {
                    $locksAcquired++;
                }
            }

            // Kecen zamani hesabla
            $elapsedTime = microtime(true) * 1000 - $startTime;
            $drift = ($ttl * $this->clockDriftFactor) + 2;
            $validityTime = $ttl - $elapsedTime - $drift;

            // Majority alinib ve zaman kecmeyib?
            $quorum = (int) (count($this->servers) / 2) + 1;
            if ($locksAcquired >= $quorum && $validityTime > 0) {
                return [
                    'resource' => $resource,
                    'token'    => $token,
                    'validity' => $validityTime,
                ];
            }

            // Ugursuz -- hamidan release et
            $this->unlockAll($resource, $token);

            // Retry etmezden evvel random gozle (thundering herd-den qacinmaq ucun)
            usleep($this->retryDelay * 1000 + random_int(0, $this->retryDelay * 1000));
        }

        return null; // Lock alinmadi
    }

    /**
     * Lock-u release et
     */
    public function unlock(array $lock): void
    {
        $this->unlockAll($lock['resource'], $lock['token']);
    }

    private function acquireOnServer($server, string $resource, string $token, int $ttl): bool
    {
        // SET key token NX PX ttl
        return (bool) $server->set($resource, $token, 'PX', $ttl, 'NX');
    }

    private function unlockAll(string $resource, string $token): void
    {
        // Lua script ile atomic release -- yalniz oz token-imizi silmeliyik
        $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;

        foreach ($this->servers as $server) {
            try {
                $server->eval($script, 1, $resource, $token);
            } catch (\Throwable) {
                // Server erisilemir -- ignore
            }
        }
    }
}
```

---

## Database-Based Locking

Redis olmadigi muhitlerde database ile lock:

### MySQL/PostgreSQL Row-Level Locking

*MySQL/PostgreSQL Row-Level Locking üçün kod nümunəsi:*
```php
// Pessimistic locking -- SELECT ... FOR UPDATE
DB::transaction(function () {
    // Bu row lock olunur, basqa transaction gozleyir
    $product = Product::where('id', 1)->lockForUpdate()->first();

    if ($product->stock > 0) {
        $product->decrement('stock');
        Order::create([...]);
    }
});
```

### Custom Database Lock Table

*Custom Database Lock Table üçün kod nümunəsi:*
```php
// database/migrations/2026_01_01_000001_create_locks_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributed_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');           // Lock sahibi
            $table->timestamp('expires_at');   // Ne vaxt bitir
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
```

*$table->timestamp('created_at')->useCurrent(); üçün kod nümunəsi:*
```php
// app/Services/DatabaseLockService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseLockService
{
    private string $owner;

    public function __construct()
    {
        // Her process ucun unikal identifikator
        $this->owner = Str::uuid()->toString();
    }

    /**
     * Lock almaga calis
     */
    public function acquire(string $key, int $ttlSeconds = 30): bool
    {
        $expiresAt = now()->addSeconds($ttlSeconds);

        try {
            // Evvelce expire olmus lock-lari temizle
            DB::table('distributed_locks')
                ->where('key', $key)
                ->where('expires_at', '<', now())
                ->delete();

            // Yeni lock yarat
            DB::table('distributed_locks')->insert([
                'key'        => $key,
                'owner'      => $this->owner,
                'expires_at' => $expiresAt,
            ]);

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate key -- lock artiq movcuddur
            return false;
        }
    }

    /**
     * Lock-u release et
     */
    public function release(string $key): bool
    {
        // Yalniz oz lock-umuzu release edirik
        $deleted = DB::table('distributed_locks')
            ->where('key', $key)
            ->where('owner', $this->owner)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Lock-u uzat (daha cox vaxt lazimdir)
     */
    public function extend(string $key, int $additionalSeconds): bool
    {
        $updated = DB::table('distributed_locks')
            ->where('key', $key)
            ->where('owner', $this->owner)
            ->update([
                'expires_at' => now()->addSeconds($additionalSeconds),
            ]);

        return $updated > 0;
    }
}
```

---

## PostgreSQL Advisory Locks

PostgreSQL-in xususi advisory lock sistemi var -- table yaratmaga ehtiyac yoxdur:

*PostgreSQL-in xususi advisory lock sistemi var -- table yaratmaga ehti üçün kod nümunəsi:*
```php
// app/Services/PostgresAdvisoryLock.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class PostgresAdvisoryLock
{
    /**
     * Session-level advisory lock al (session bitene qeder saxlanir)
     * String key-i integer-e cevirmeliyik
     */
    public function acquire(string $key): bool
    {
        $lockId = crc32($key);

        // pg_try_advisory_lock -- gozlemeden cavab qaytarir
        $result = DB::selectOne(
            'SELECT pg_try_advisory_lock(?) as acquired',
            [$lockId]
        );

        return $result->acquired;
    }

    /**
     * Blocking lock -- lock alinana qeder gozle
     */
    public function acquireBlocking(string $key): bool
    {
        $lockId = crc32($key);

        // Bu, lock alinana qeder block edir
        DB::selectOne('SELECT pg_advisory_lock(?)', [$lockId]);

        return true;
    }

    /**
     * Lock-u release et
     */
    public function release(string $key): bool
    {
        $lockId = crc32($key);

        $result = DB::selectOne(
            'SELECT pg_advisory_unlock(?) as released',
            [$lockId]
        );

        return $result->released;
    }

    /**
     * Transaction-level advisory lock
     * Transaction bitende avtomatik release olunur
     */
    public function acquireForTransaction(string $key): bool
    {
        $lockId = crc32($key);

        $result = DB::selectOne(
            'SELECT pg_try_advisory_xact_lock(?) as acquired',
            [$lockId]
        );

        return $result->acquired;
    }
}
```

Istifade numunesi:

*Istifade numunesi üçün kod nümunəsi:*
```php
// Payment islemede PostgreSQL advisory lock
$advisoryLock = app(PostgresAdvisoryLock::class);

DB::transaction(function () use ($advisoryLock, $paymentId) {
    // Transaction-level lock -- transaction bitende avtomatik release olunur
    if (!$advisoryLock->acquireForTransaction("payment:{$paymentId}")) {
        throw new \RuntimeException('Bu odeme artiq islenir.');
    }

    $payment = Payment::findOrFail($paymentId);
    // ... payment processing ...
});
```

---

## Deadlock Prevention

Deadlock -- iki process bir-birinin lock-unu gozleyir ve her ikisi sonsuza qeder bloklenir:

```
Process A: Lock(X) alinib, Lock(Y) gozleyir
Process B: Lock(Y) alinib, Lock(X) gozleyir
--> DEADLOCK!
```

### Prevention Strategiyalari

*Prevention Strategiyalari üçün kod nümunəsi:*
```php
// app/Services/SafeLockService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SafeLockService
{
    /**
     * Strategiya 1: Lock Ordering
     * Her zaman eyni sirada lock al -- deadlock mumkun olmur
     */
    public function acquireMultipleLocks(array $resourceKeys, int $ttl = 30): array
    {
        // VACIB: Key-leri sort et -- her zaman eyni sirada lock almaq
        sort($resourceKeys);

        $locks = [];

        foreach ($resourceKeys as $key) {
            $lock = Cache::lock($key, $ttl);

            if (!$lock->get()) {
                // Bir lock alinmadisa, evvelkileri release et
                foreach ($locks as $acquiredLock) {
                    $acquiredLock->release();
                }

                throw new \RuntimeException(
                    "Lock alinmadi: {$key}. Butun lock-lar release edildi."
                );
            }

            $locks[] = $lock;
        }

        return $locks;
    }

    /**
     * Strategiya 2: Timeout ile lock
     * Sonsuza qeder gozleme -- timeout qoy
     */
    public function acquireWithTimeout(string $key, int $ttl = 30, int $waitSeconds = 5): mixed
    {
        $lock = Cache::lock($key, $ttl);

        try {
            return $lock->block($waitSeconds, function () {
                return true;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            Log::warning("Lock timeout: {$key} -- {$waitSeconds}s gozledikden sonra");
            return false;
        }
    }

    /**
     * Strategiya 3: Retry with Exponential Backoff
     */
    public function acquireWithRetry(
        string $key,
        int $ttl = 30,
        int $maxRetries = 3,
        int $baseDelayMs = 100
    ): bool {
        $lock = Cache::lock($key, $ttl);

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($lock->get()) {
                return true;
            }

            // Exponential backoff + jitter
            $delay = $baseDelayMs * (2 ** $attempt);
            $jitter = random_int(0, $delay);
            usleep(($delay + $jitter) * 1000);

            Log::info("Lock retry {$attempt}/{$maxRetries} for {$key}");
        }

        return false;
    }
}
```

---

## Real-World Numune 3: Idempotent API Endpoint

Eyni request-in 2 defe gonderilmesinin qarsisini almaq:

*Eyni request-in 2 defe gonderilmesinin qarsisini almaq üçün kod nümunəsi:*
```php
// app/Http/Middleware/IdempotencyMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Yalniz mutating request-ler ucun
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $cacheKey = "idempotency:{$idempotencyKey}";
        $lockKey = "idempotency_lock:{$idempotencyKey}";

        // Evvelce cavab qaytarilib?
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse) {
            return response()->json(
                $cachedResponse['body'],
                $cachedResponse['status']
            )->header('X-Idempotency-Replayed', 'true');
        }

        // Lock al -- eyni anda 2 eyni request gelerse
        $lock = Cache::lock($lockKey, 30);

        if (!$lock->get()) {
            return response()->json([
                'error' => 'Bu request artiq islenir.',
            ], 409);
        }

        try {
            $response = $next($request);

            // Cavabi cache-le (24 saat)
            Cache::put($cacheKey, [
                'body'   => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], 86400);

            return $response;
        } finally {
            $lock->release();
        }
    }
}
```

---

## Optimistic Locking Alternative

Lock almaq evezine, data deyisibse ugursuz et:

*Lock almaq evezine, data deyisibse ugursuz et üçün kod nümunəsi:*
```php
// Optimistic locking -- version column ile
// database/migrations/..._add_version_to_products.php
Schema::table('products', function (Blueprint $table) {
    $table->unsignedBigInteger('version')->default(0);
});
```

*$table->unsignedBigInteger('version')->default(0); üçün kod nümunəsi:*
```php
// app/Services/OptimisticOrderService.php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OptimisticOrderService
{
    public function placeOrder(int $productId, int $quantity, int $maxRetries = 3): void
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $product = Product::findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new \DomainException('Stokda kifayet qeder mehsul yoxdur.');
            }

            // Version ile update -- basqasi deyisdibse 0 row update olunur
            $affected = DB::table('products')
                ->where('id', $productId)
                ->where('version', $product->version) // <-- esas shert!
                ->update([
                    'stock'   => $product->stock - $quantity,
                    'version' => $product->version + 1,
                ]);

            if ($affected > 0) {
                // Ugurlu! Sifaris yarat
                Order::create([
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                ]);
                return;
            }

            // Data deyisib -- retry
            if ($attempt < $maxRetries) {
                usleep(random_int(10000, 50000)); // 10-50ms gozle
            }
        }

        throw new \RuntimeException(
            'Sifaris yaradilmadi -- coxlu muraciet oldugu ucun. Yeniden cehd edin.'
        );
    }
}
```

---

## Yekun: Ne Vaxt Hansi Yanasmani Secmeli?

| Ssenari                       | Tovsiye olunan yanasma         |
|-------------------------------|--------------------------------|
| Tek server, tek process       | Database transaction + lockForUpdate |
| Coxlu server, qisa emeliyyat  | Redis Cache::lock              |
| Coxlu server, kritik emeliyyat| Redlock algorithm              |
| PostgreSQL istifade edirsiniz | Advisory locks                 |
| Yuksek oxuma, az yazma        | Optimistic locking (version)   |
| Cron job duplication          | withoutOverlapping + onOneServer |
| Idempotent API                | Idempotency key + lock         |

**Qizil qayda:** Lock-u her zaman `finally` blokunda release edin, yoxsa diger process-ler sonsuza qeder gozleyecek. Timeout her zaman qoyun -- "sonsuza qeder gozle" hec vaxt yaxsi fikir deyil.

---

## Interview Sualları və Cavablar

**S: Mutex ilə Semaphore-un fərqi nədir?**
C: Mutex — yalnız 1 process resursdan istifadə edə bilər (binary lock). Semaphore — eyni anda N process daxil ola bilər. Məsələn, API-yə eyni anda maksimum 5 concurrent request göndərmək istəyirsinizsə, semaphore (N=5) istifadə edilir. Laravel-də `Cache::lock` mutex-dir; semaphore üçün counter-based tracking lazımdır.

**S: Distributed lock üçün niyə Redis, DB yox?**
C: DB-yə `INSERT INTO locks` atomik deyil tam mənada — yüksək concurrent yükdə race condition ola bilər. Redis `SET key value NX PX ttl` əməliyyatı tamamilə atomikdir — ya set edilir, ya da deyil. Redis in-memory olduğu üçün çox sürətlidir (microseconds). DB lock cədvəli isə darboğaz yaradır.

**S: Lock TTL çox qısa olsa nə baş verər?**
C: Process hələ işini bitirməmişdən lock expire olur, başqa process eyni resursa daxil olur — race condition. Lock TTL prosessin maksimum işləmə müddətindən çox olmalıdır. Uzun sürən əməliyyatlar üçün lock renewal (heartbeat) tətbiq edilir: hər 10 saniyədə bir TTL-i uzatmaq.

**S: Redlock algorithm nədir, nə zaman lazımdır?**
C: Redlock — Redis cluster-ında distributed locking üçün. Tək Redis node fail olsa lock itirilir. Redlock N Redis node-dan N/2+1-dən lock almağa çalışır (majority quorum). Əksər hallarda tək Redis node kifayətdir; critical financial işləmlər üçün Redlock düşünülə bilər.

**S: Fencing token nədir?**
C: Lock alındıqda artan bir token (counter) verilir. Uzun sürmüş process lock expire olduqdan sonra işini bitirməyə çalışsa, köhnə token ilə gəlir. Server token-i yoxlayır — köhnə token ilə gələn yazma rədd edilir. Bu "stale process" problemini həll edir.

**S: `withoutOverlapping()` vs `onOneServer()` — fərqi nədir?**
C: `withoutOverlapping()` — eyni server-də cron job-un paralel işləməsini önləyir (bir instansiya hələ işləyirsə, yeni instansiya başlamır). `onOneServer()` — çoxlu server-də yalnız bir server-də işlədilir (distributed lock ilə). Production-da ikisi birlikdə istifadə olunmalıdır.

---

## Anti-patternlər

**1. Lock-u finally blokunda release etməmək**
Exception olsa lock sonsuza qədər açıq qalır — digər process-lər gözləyir. Həmişə `try/finally { $lock->release(); }`.

**2. Timeout olmadan lock gözləmək**
`$lock->block(0)` — process heç vaxt davam etmir, deadlock. Məntiqli timeout qoyun: `$lock->block(10)` (10 saniyə gözlə, sonra exception).

**3. Distributed lock üçün DB istifadə etmək**
`INSERT INTO locks` — DB darboğazı, həmçinin DB down olsa lock da işləmir. Redis SET NX + PX — atomik, fast, TTL dəstəyi var.

**4. Lock granularity çox geniş**
Bütün inventory-ni bir lock ilə qorumaq — yalnız bir process işləyir. `"lock:product:{$productId}"` kimi fine-grained lock istifadə edin.

**5. Fencing token olmadan lock**
Process lock aldı, GC pause oldu, lock expire oldu, başqa process aldı, birinci process hələ işləyir — iki process eyni vaxtda. Fencing token (monotonically increasing ID) ilə stale lock-ları rədd edin.
