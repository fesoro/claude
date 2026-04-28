# Concurrency Patterns (Senior ⭐⭐⭐)

---

## 1. Concurrency vs Parallelism Fərqi

```
CONCURRENCY (eyni vaxtda çox işlə məşğul olmaq):
  ┌─────────────────────────────────────────┐
  │ Task A: ████░░░░████░░░░████            │
  │ Task B: ░░░░████░░░░████░░░░████        │
  │ (Tək CPU core — tasks arasında switching)│
  └─────────────────────────────────────────┘
  → Eyni vaxtda yalnız BİR task işləyir
  → Müxtəlif task-lar arasında context switch edilir
  → I/O-bound problemlər üçün effektiv

PARALLELISM (eyni vaxtda həqiqətən çox iş görmək):
  ┌─────────────────────────────────────────┐
  │ CPU Core 1: Task A: ████████████        │
  │ CPU Core 2: Task B: ████████████        │
  │ (Çox CPU core — tasks HƏQIQƏTƏN parallel)│
  └─────────────────────────────────────────┘
  → Fiziki olaraq eyni vaxtda çox task işləyir
  → CPU-bound problemlər üçün lazımdır

Nümunə:
  Concurrency: Bir aşpaz 3 yeməyi növbəti bişirir
  Parallelism: 3 aşpaz eyni vaxtda 3 yeməyi bişirir
```

---

## 2. PHP-nin Single-Threaded Nature

PHP standart olaraq **single-threaded**-dir:
- Hər request ayrı bir PHP process-dir
- Bir process-in içərisində yalnız bir kod xətti icra edilir
- Thread yoxdur (pthreads extension istisna olmaqla — deprecated)
- Hər request öz memory space-ini alır

```
HTTP Request 1 → PHP Process A (Worker 1) → DB Query → Response
HTTP Request 2 → PHP Process B (Worker 2) → DB Query → Response
HTTP Request 3 → PHP Process C (Worker 3) → DB Query → Response
```

PHP-nin "concurrency"-si process-level-dədir, thread-level deyil.

---

## 3. PHP-də Parallelism Üsulları

### 3.1 pcntl_fork

*3.1 pcntl_fork üçün kod nümunəsi:*
```php
<?php
// Yalnız CLI-də işləyir, web request-də istifadə edilməz

$pids = [];

for ($i = 0; $i < 4; $i++) {
    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Fork failed');
    }

    if ($pid === 0) {
        // Bu child process-dir
        echo "Child {$i}: processing job {$i}\n";
        // Ağır iş...
        sleep(1);
        exit(0); // Child mütləq exit etməlidir!
    }

    $pids[] = $pid;
}

// Parent process — bütün child-ları gözləyir
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

echo "All children finished\n";
```

---

### 3.2 symfony/process

*3.2 symfony/process üçün kod nümunəsi:*
```php
use Symfony\Component\Process\Process;

// Ardıcıl işlətmək:
$process = new Process(['php', 'artisan', 'import:users', '--chunk=1000']);
$process->run();

// Parallel işlətmək:
$processes = [];
foreach (range(1, 4) as $chunk) {
    $process = new Process(['php', 'artisan', 'import:users', "--chunk={$chunk}"]);
    $process->start(); // Bloklamadan başlat
    $processes[] = $process;
}

// Hamısının bitməsini gözlə
foreach ($processes as $process) {
    $process->wait();
    if (! $process->isSuccessful()) {
        throw new \RuntimeException($process->getErrorOutput());
    }
}
```

---

### 3.3 spatie/async

*3.3 spatie/async üçün kod nümunəsi:*
```php
use Spatie\Async\Pool;

$pool = Pool::create();

$pool
    ->add(function () {
        // Process 1: Ağır hesablama
        return array_sum(range(1, 1_000_000));
    })
    ->then(function (int $result) {
        echo "Sum: {$result}\n";
    });

$pool
    ->add(function () {
        // Process 2: Başqa ağır hesablama
        return count(array_unique(range(1, 500_000)));
    })
    ->then(function (int $result) {
        echo "Unique count: {$result}\n";
    });

// Hər iki process parallel icra edilir
await($pool);
```

---

## 4. PHP Fibers (PHP 8.1) — Coroutine

PHP 8.1-də gəldi. **Single-threaded** içərisində cooperative multitasking imkanı verir.

*PHP 8.1-də gəldi. **Single-threaded** içərisində cooperative multitask üçün kod nümunəsi:*
```php
<?php
// Fiber: suspend/resume edə bilən bir hesablama vahidi

$fiber = new Fiber(function (): void {
    echo "Fiber başladı\n";

    // Bu nöqtədə dayandır, caller-a qayıt
    $value = Fiber::suspend('mən suspendəm');

    echo "Fiber davam etdi, aldı: {$value}\n";
    echo "Fiber bitdi\n";
});

// Fiber-i başlat (suspend-ə qədər işləyir)
$result = $fiber->start();
echo "Fiber suspended, qaytardı: {$result}\n"; // "mən suspendəm"

// Fiber-i davam etdir, dəyər göndər
$fiber->resume('salam fiber!');

echo "Main program bitdi\n";
```

**Çıxış:**
```
Fiber başladı
Fiber suspended, qaytardı: mən suspendəm
Fiber davam etdi, aldı: salam fiber!
Fiber bitdi
Main program bitdi
```

**Praktiki nümunə — çox Fiber ilə I/O simulation:**

```php
<?php

function simulateHttpRequest(string $url, int $delayMs): string
{
    // Reallıqda bu: curl, stream, socket olardı
    usleep($delayMs * 1000);
    return "Response from {$url}";
}

$fibers = [];
$urls = [
    'https://api1.example.com/users',
    'https://api2.example.com/products',
    'https://api3.example.com/orders',
];

// Fiber-ləri yarat
foreach ($urls as $url) {
    $fiber = new Fiber(function () use ($url): string {
        Fiber::suspend("starting:{$url}");
        return simulateHttpRequest($url, 100);
    });
    $fibers[] = $fiber;
}

// Hamısını başlat
foreach ($fibers as $fiber) {
    $fiber->start();
}

// Hamısı bitənə qədər dövr et
$results = [];
while (array_filter($fibers, fn(Fiber $f) => ! $f->isTerminated())) {
    foreach ($fibers as $fiber) {
        if ($fiber->isSuspended()) {
            $results[] = $fiber->resume();
        }
    }
}

print_r($results);
```

---

## 5. ReactPHP Event Loop

ReactPHP PHP-də non-blocking, event-driven proqramlama üçündür:

*ReactPHP PHP-də non-blocking, event-driven proqramlama üçündür üçün kod nümunəsi:*
```php
<?php
require 'vendor/autoload.php';

use React\EventLoop\Loop;
use React\Http\Browser;

$loop   = Loop::get();
$client = new Browser($loop);

// Non-blocking HTTP requests
$promises = [];
foreach (['https://api1.com', 'https://api2.com', 'https://api3.com'] as $url) {
    $promises[] = $client->get($url);
}

// Hamısı parallel işləyir (event loop sayəsində)
\React\Promise\all($promises)->then(function (array $responses) {
    foreach ($responses as $response) {
        echo $response->getStatusCode() . "\n";
    }
});

$loop->run(); // Event loop-u işlət
```

**Konsept:**
```
Event Loop:
  while (true) {
      processCallbacks();
      pollI/O();         ← I/O event-ləri yoxla
      processTimers();
      if (noMoreWork) break;
  }
```

---

## 6. Swoole Coroutines

*6. Swoole Coroutines üçün kod nümunəsi:*
```php
<?php
// Swoole sayəsində PHP coroutine-lar mümkün olur

Co\run(function () {
    // Bu iki request PARALLEL icra edilir
    [$dbResult, $httpResult] = Co\join([
        function () {
            // Non-blocking DB sorğusu
            $mysql = new Swoole\Coroutine\MySQL();
            $mysql->connect(['host' => 'localhost', 'user' => 'root', 'password' => '', 'database' => 'test']);
            return $mysql->query('SELECT * FROM users LIMIT 100');
        },
        function () {
            // Non-blocking HTTP sorğusu
            $client = new Swoole\Coroutine\Http\Client('api.example.com', 443, true);
            $client->get('/data');
            return $client->body;
        },
    ]);

    // İkisi də tamamlandı
    var_dump($dbResult, $httpResult);
});
```

---

## 7. Laravel Octane Concurrent Tasks

Laravel Octane, Swoole/RoadRunner üzərindən concurrent task-lar dəstəkləyir:

*Laravel Octane, Swoole/RoadRunner üzərindən concurrent task-lar dəstək üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laravel\Octane\Facades\Octane;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // 3 task PARALLEL icra edilir — hər biri ayrı coroutine-da
        [$users, $orders, $revenue] = Octane::concurrently([
            fn() => \App\Models\User::count(),
            fn() => \App\Models\Order::whereDate('created_at', today())->count(),
            fn() => \App\Models\Order::whereDate('created_at', today())->sum('total'),
        ]);

        // Nəticə: ən uzun task qədər gözlədik (hamısı parallel işlədi)
        return response()->json([
            'total_users'    => $users,
            'orders_today'   => $orders,
            'revenue_today'  => $revenue,
        ]);
    }
}
```

**Timeout ilə:**

```php
[$result1, $result2] = Octane::concurrently([
    fn() => slowDatabaseQuery(),
    fn() => externalApiCall(),
], timeout: 3000); // 3 saniyə gözlə, sonra exception
```

---

## 8. Concurrency Patterns Laravel-də

### 8.1 Mutex / Distributed Lock

**Problem:** Eyni vaxtda iki process eyni işi etməsin (idempotency).

***Problem:** Eyni vaxtda iki process eyni işi etməsin (idempotency) üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

class ReportGenerationService
{
    /**
     * Eyni anda yalnız bir report generation işləsin.
     */
    public function generate(int $reportId): array
    {
        // Redis lock: "report-1" key-i üçün 30 saniyəlik lock
        $lock = Cache::lock("report-generation:{$reportId}", 30);

        try {
            // Lock almağa çalış, 10 saniyə gözlə
            $lock->block(10);

            // Critical section başlayır
            return $this->doGenerateReport($reportId);

        } catch (LockTimeoutException $e) {
            throw new \RuntimeException(
                "Report {$reportId} artıq başqa bir process tərəfindən generasiya edilir"
            );
        } finally {
            // Lock-u burax (finally — exception olsa belə)
            optional($lock)->release();
        }
    }

    /**
     * Owner check ilə lock — yalnız lock sahibi buraxabilər.
     */
    public function generateWithOwnerCheck(int $reportId): void
    {
        $lock = Cache::lock("report:{$reportId}", 60);

        if (! $lock->get()) {
            throw new \RuntimeException('Could not acquire lock');
        }

        // Lock token-ini saxla (başqa yerə ötürmək üçün)
        $token = $lock->owner();

        try {
            $this->doGenerateReport($reportId);
        } finally {
            // Yalnız bu owner lock-u buraxır
            Cache::restoreLock("report:{$reportId}", $token)->release();
        }
    }

    private function doGenerateReport(int $reportId): array
    {
        // Ağır hesablama...
        sleep(5);
        return ['status' => 'done', 'report_id' => $reportId];
    }
}
```

---

### 8.2 Semaphore — N Concurrent Process Limiti

Mutex yalnız 1 process-ə icazə verir. Semaphore N process-ə icazə verir.

*Mutex yalnız 1 process-ə icazə verir. Semaphore N process-ə icazə veri üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SemaphoreService
{
    private const MAX_CONCURRENT = 5; // Eyni vaxtda maks 5 API call

    /**
     * Daha düzgün implementation: Redis Lua script ilə atomic
     */
    public function callWithLuaSemaphore(string $endpoint): string
    {
        $key    = 'semaphore:api';
        $max    = self::MAX_CONCURRENT;
        $expiry = 60;

        // Lua script: atomic check-and-increment
        $script = <<<'LUA'
        local current = redis.call('GET', KEYS[1])
        if current == false then current = 0 end
        if tonumber(current) >= tonumber(ARGV[1]) then
            return -1
        end
        redis.call('INCR', KEYS[1])
        redis.call('EXPIRE', KEYS[1], ARGV[2])
        return redis.call('GET', KEYS[1])
        LUA;

        $result = Redis::eval($script, 1, $key, $max, $expiry);

        if ($result === -1) {
            throw new \RuntimeException('Semaphore limit reached. Too many concurrent requests.');
        }

        try {
            return $this->makeApiCall($endpoint);
        } finally {
            Redis::decr($key);
        }
    }

    private function makeApiCall(string $endpoint): string
    {
        // HTTP call...
        return "response from {$endpoint}";
    }
}
```

---

### 8.3 Producer-Consumer: Laravel Queue (SKIP LOCKED)

*8.3 Producer-Consumer: Laravel Queue (SKIP LOCKED) üçün kod nümunəsi:*
```php
// app/Jobs/ProcessOrder.php
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int    $timeout = 60;

    public function __construct(public readonly int $orderId) {}

    public function handle(OrderService $service): void
    {
        $service->process($this->orderId);
    }

    public function failed(\Throwable $exception): void
    {
        // Dead letter queue-ya göndər
        dispatch(new FailedOrderNotification($this->orderId, $exception->getMessage()));
    }
}
```

*dispatch(new FailedOrderNotification($this->orderId, $exception->getMe üçün kod nümunəsi:*
```sql
-- Laravel-in database queue driver-ı bu SQL-i işlədir:
-- SKIP LOCKED: artıq başqa worker tərəfindən kilidlənmiş sətirləri atla
SELECT * FROM jobs
WHERE queue = 'default'
  AND reserved_at IS NULL
  AND available_at <= NOW()
ORDER BY id ASC
LIMIT 1
FOR UPDATE SKIP LOCKED;
```

---

### 8.4 Fan-out / Fan-in: Bus::batch()

*8.4 Fan-out / Fan-in: Bus::batch() üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

class NotificationController extends Controller
{
    public function sendToAll(): \Illuminate\Http\JsonResponse
    {
        $users = \App\Models\User::all();

        // Fan-out: bütün istifadəçilərə ayrı job-lar yarat
        $jobs = $users->map(fn($user) => new SendNotification($user->id));

        // Fan-in: hamısı bitdikdə callback çağır
        $batch = Bus::batch($jobs->all())
            ->name('bulk-notification')
            ->allowFailures() // Bəziləri fail olsa da davam et
            ->then(function (Batch $batch) {
                // FAN-IN: hamısı uğurla bitdi
                \Log::info("All {$batch->totalJobs} notifications sent");
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // İlk failure-da çağırılır
                \Log::error("Batch failed: {$e->getMessage()}");
            })
            ->finally(function (Batch $batch) {
                // Uğurlu/uğursuz — hər halda çağırılır
                \Log::info("Batch finished. Failed: {$batch->failedJobs}");
            })
            ->dispatch();

        return response()->json([
            'batch_id'   => $batch->id,
            'total_jobs' => $batch->totalJobs,
        ]);
    }

    public function batchStatus(string $batchId): \Illuminate\Http\JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        return response()->json([
            'id'           => $batch->id,
            'total_jobs'   => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs'  => $batch->failedJobs,
            'progress'     => $batch->progress(),
            'finished'     => $batch->finished(),
        ]);
    }
}
```

---

### 8.5 Bulkhead Pattern — Separate Queue Connections

*8.5 Bulkhead Pattern — Separate Queue Connections üçün kod nümunəsi:*
```php
// config/queue.php
'connections' => [
    // Kritik işlər üçün ayrı connection
    'critical' => [
        'driver'      => 'redis',
        'connection'  => 'default',
        'queue'       => 'critical',
        'retry_after' => 90,
    ],
    // Normal işlər
    'default' => [
        'driver'      => 'redis',
        'connection'  => 'default',
        'queue'       => 'default',
        'retry_after' => 90,
    ],
    // Ağır hesablama işləri (bulk)
    'bulk' => [
        'driver'      => 'redis',
        'connection'  => 'default',
        'queue'       => 'bulk',
        'retry_after' => 300,
    ],
],
```

*'retry_after' => 300, üçün kod nümunəsi:*
```php
// Job dispatch — queue seç
PaymentProcessJob::dispatch($paymentId)
    ->onConnection('critical')  // Ödəniş — kritik queue
    ->onQueue('critical');

SendEmailJob::dispatch($userId)
    ->onConnection('default');  // Email — normal queue

GenerateReportJob::dispatch($reportId)
    ->onConnection('bulk')      // Report — bulk queue (worker count az)
    ->onQueue('bulk');
```

*->onQueue('bulk'); üçün kod nümunəsi:*
```bash
# Bulkhead: hər queue üçün ayrı worker pool
php artisan queue:work critical --queue=critical --sleep=0 &
php artisan queue:work default  --queue=default            &
php artisan queue:work bulk     --queue=bulk   --sleep=3   &
# bulk üçün az worker — sistem əsas işlər üçün qorunur
```

---

### 8.6 Backpressure — Queue Size Monitoring

*8.6 Backpressure — Queue Size Monitoring üçün kod nümunəsi:*
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class MonitorQueueBackpressure extends Command
{
    protected $signature   = 'queue:monitor-backpressure';
    protected $description = 'Monitor queue depth and apply backpressure';

    private const THRESHOLDS = [
        'default'  => ['warning' => 1000, 'critical' => 5000],
        'critical' => ['warning' => 100,  'critical' => 500],
    ];

    public function handle(): void
    {
        foreach (self::THRESHOLDS as $queue => $thresholds) {
            $size = Queue::size($queue);

            if ($size >= $thresholds['critical']) {
                // Backpressure: yeni job qəbulunu dayandır
                \Cache::put("queue.{$queue}.backpressure", true, 60);
                $this->error("CRITICAL: Queue '{$queue}' has {$size} jobs!");
                \Log::critical("Queue backpressure: {$queue} has {$size} jobs");

            } elseif ($size >= $thresholds['warning']) {
                $this->warn("WARNING: Queue '{$queue}' has {$size} jobs");
                \Log::warning("Queue growing: {$queue} has {$size} jobs");

            } else {
                // Normal — backpressure-u qaldır
                \Cache::forget("queue.{$queue}.backpressure");
            }
        }
    }
}
```

*\Cache::forget("queue.{$queue}.backpressure"); üçün kod nümunəsi:*
```php
// Controller-də backpressure yoxlaması
class OrderController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        if (Cache::get('queue.default.backpressure')) {
            return response()->json([
                'error'       => 'System is under high load. Please try again in a moment.',
                'retry_after' => 30,
            ], 503);
        }

        $order = Order::create($request->validated());
        ProcessOrderJob::dispatch($order->id);

        return response()->json($order, 201);
    }
}
```

---

## 9. Race Condition Nümunəsi — Inventory Count

### Problem:

*Problem: üçün kod nümunəsi:*
```php
// ❌ RACE CONDITION — İki request eyni vaxtda işləsə:
class InventoryService
{
    public function purchase(int $productId, int $quantity): bool
    {
        $product = Product::find($productId);

        // REQUEST 1: stock = 1, quantity = 1 → 1 >= 1 ✓
        // REQUEST 2: stock = 1, quantity = 1 → 1 >= 1 ✓ (hər ikisi keçdi!)
        if ($product->stock < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        // REQUEST 1: UPDATE products SET stock = 0
        // REQUEST 2: UPDATE products SET stock = -1 ← PROBLEM!
        $product->decrement('stock', $quantity);

        return true;
    }
}
```

**Timeline:**
```
Time | Request 1                     | Request 2
-----|-------------------------------|-------------------------------
T1   | SELECT stock → 1              |
T2   |                               | SELECT stock → 1
T3   | 1 >= 1 → OK                   |
T4   |                               | 1 >= 1 → OK
T5   | UPDATE stock = stock - 1 → 0  |
T6   |                               | UPDATE stock = stock - 1 → -1 ← PROBLEM!
```

### Həll 1 — Pessimistic Locking:

*Həll 1 — Pessimistic Locking: üçün kod nümunəsi:*
```php
// ✅ lockForUpdate() — SELECT FOR UPDATE
class InventoryService
{
    public function purchase(int $productId, int $quantity): bool
    {
        return DB::transaction(function () use ($productId, $quantity) {
            // Row-u lock et — başqa transaction bu sətiri oxuya bilməz
            $product = Product::lockForUpdate()->findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new \Exception('Insufficient stock');
            }

            $product->decrement('stock', $quantity);

            return true;
        });
    }
}
```

### Həll 2 — Optimistic Locking (version column):

*Həll 2 — Optimistic Locking (version column): üçün kod nümunəsi:*
```php
// Migration
Schema::table('products', function (Blueprint $table) {
    $table->unsignedBigInteger('version')->default(0);
});
```

*$table->unsignedBigInteger('version')->default(0); üçün kod nümunəsi:*
```php
// ✅ Optimistic Locking
class InventoryService
{
    public function purchase(int $productId, int $quantity): bool
    {
        $maxRetries = 3;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $product = Product::findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new \Exception('Insufficient stock');
            }

            // UPDATE yalnız version dəyişməyibsə işlər
            $updated = Product::where('id', $productId)
                ->where('version', $product->version)   // Version check
                ->where('stock', '>=', $quantity)        // Extra safety
                ->update([
                    'stock'   => DB::raw("stock - {$quantity}"),
                    'version' => DB::raw('version + 1'),
                ]);

            if ($updated === 1) {
                return true; // Uğurlu
            }

            // Version dəyişib — başqa biri əvvəl yeniliyib, retry et
            usleep(random_int(1000, 10000)); // 1-10ms gözlə
        }

        throw new \Exception('Could not complete purchase after retries. Please try again.');
    }
}
```

---

## 10. Race Condition Nümunəsi — Wallet Balance

### Problem:

*Problem: üçün kod nümunəsi:*
```php
// ❌ Double spending problem
class WalletService
{
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        $from = Wallet::find($fromId);
        $to   = Wallet::find($toId);

        // Race condition: eyni vaxtda iki transfer
        if ($from->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        $from->decrement('balance', $amount);
        $to->increment('balance', $amount);
    }
}
```

### Həll — Deadlock-dan Qaçaraq Pessimistic Locking:

*Həll — Deadlock-dan Qaçaraq Pessimistic Locking: üçün kod nümunəsi:*
```php
// ✅ Deadlock önləmə: həmişə kiçik ID-li wallet-i əvvəl lock et
class WalletService
{
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        DB::transaction(function () use ($fromId, $toId, $amount) {
            // Deadlock prevention: lock-ları həmişə eyni sırada al
            // Əgər sıra random olsa: T1 locks A then B, T2 locks B then A → DEADLOCK
            [$firstId, $secondId] = $fromId < $toId
                ? [$fromId, $toId]
                : [$toId, $fromId];

            // Həmişə kiçik ID-ni əvvəl lock et
            $wallets = Wallet::lockForUpdate()
                ->whereIn('id', [$firstId, $secondId])
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $from = $wallets[$fromId];
            $to   = $wallets[$toId];

            if ($from->balance < $amount) {
                throw new \Exception(
                    "Insufficient balance. Available: {$from->balance}, Required: {$amount}"
                );
            }

            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);

            // Audit log
            WalletTransaction::create([
                'from_wallet_id' => $fromId,
                'to_wallet_id'   => $toId,
                'amount'         => $amount,
                'type'           => 'transfer',
            ]);
        });
    }
}
```

---

## 11. Optimistic vs Pessimistic Locking Müqayisəsi

```
                    Optimistic Locking        Pessimistic Locking
                    ──────────────────────    ──────────────────────
Mexanizm            version column check      SELECT FOR UPDATE
Conflict zamanı     Retry (CAS)               Gözlə (block)
Throughput          Yüksək (lock yoxdur)      Aşağı (lock var)
Conflict çox olsa   Retry overhead artır      Gözlə → yavaş
Deadlock riski      Yoxdur                    Var (əgər sıra yanlışdırsa)
İstifadə            Az conflict, çox oxuma    Kritik yazma, az conflict
Nümunə              Wiki edit, optimistic UI  Ödəniş, inventory
```

---

## 12. SELECT FOR UPDATE SKIP LOCKED

*12. SELECT FOR UPDATE SKIP LOCKED üçün kod nümunəsi:*
```php
// Queue-un database driver-ı bunu istifadə edir
// Eyni job-u iki worker almayacaq

class DatabaseQueueWorker
{
    public function fetchNextJob(string $queue): ?array
    {
        return DB::transaction(function () use ($queue) {
            // SKIP LOCKED: başqa transaction tərəfindən lock edilmiş sətirləri atla
            $job = DB::table('jobs')
                ->where('queue', $queue)
                ->where('reserved_at', null)
                ->where('available_at', '<=', now()->timestamp)
                ->orderBy('id')
                ->lockForUpdate()   // FOR UPDATE + SKIP LOCKED
                ->first();

            if (! $job) {
                return null;
            }

            DB::table('jobs')
                ->where('id', $job->id)
                ->update([
                    'reserved_at' => now()->timestamp,
                    'attempts'    => $job->attempts + 1,
                ]);

            return (array) $job;
        });
    }
}
```

---

## 13. Redis Atomic Operations

*13. Redis Atomic Operations üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisAtomicService
{
    /**
     * INCR — Atomic increment (thread-safe sayaç)
     */
    public function incrementCounter(string $key): int
    {
        return Redis::incr($key);
    }

    /**
     * GETSET — Atomic get-and-set
     */
    public function atomicGetAndSet(string $key, string $newValue): ?string
    {
        return Redis::getset($key, $newValue);
    }

    /**
     * Lua script ilə atomic check-and-set
     * Eyni vaxtda oxu + yaz → race condition yoxdur
     */
    public function atomicCheckAndDecrement(string $key, int $minValue): bool
    {
        $script = <<<'LUA'
        local current = tonumber(redis.call('GET', KEYS[1]))
        if current == nil then current = 0 end
        if current <= tonumber(ARGV[1]) then
            return 0
        end
        redis.call('DECR', KEYS[1])
        return 1
        LUA;

        $result = Redis::eval($script, 1, $key, $minValue);

        return $result === 1;
    }

    /**
     * Rate limiting ilə atomic Lua script
     */
    public function rateLimitedAction(string $userId, int $maxPerMinute): bool
    {
        $key    = "rate_limit:{$userId}:" . floor(time() / 60);
        $script = <<<'LUA'
        local current = redis.call('INCR', KEYS[1])
        if current == 1 then
            redis.call('EXPIRE', KEYS[1], 60)
        end
        if current > tonumber(ARGV[1]) then
            return 0
        end
        return 1
        LUA;

        return Redis::eval($script, 1, $key, $maxPerMinute) === 1;
    }
}
```

---

## 14. Redlock Algorithm PHP-də

Birdən çox Redis node-da distributed locking:

*Birdən çox Redis node-da distributed locking üçün kod nümunəsi:*
```php
<?php
// composer require ronnylt/redlock-php

use RedlockPhp\Redlock;

class RedlockService
{
    private Redlock $redlock;

    public function __construct()
    {
        // Birdən çox Redis server — fault tolerance üçün
        $servers = [
            ['host' => 'redis1', 'port' => 6379, 'timeout' => 0.01],
            ['host' => 'redis2', 'port' => 6379, 'timeout' => 0.01],
            ['host' => 'redis3', 'port' => 6379, 'timeout' => 0.01],
        ];

        $this->redlock = new Redlock($servers);
    }

    public function withDistributedLock(string $resource, callable $callback): mixed
    {
        $ttl  = 10000; // 10 saniyə (millisecond)
        $lock = $this->redlock->lock($resource, $ttl);

        if (! $lock) {
            throw new \RuntimeException("Could not acquire distributed lock for: {$resource}");
        }

        try {
            return $callback();
        } finally {
            $this->redlock->unlock($lock);
        }
    }
}

// İstifadə:
$redlock->withDistributedLock('payment:123', function () {
    processPayment(123);
});
```

**Redlock Alqoritmi:**
```
1. Cari timestamp götür
2. N Redis server-in çoxuna (N/2 + 1) lock almağa çalış
3. Əgər çoxluğu aldısa VƏ alınan vaxt TTL-dən azdırsa → lock uğurludur
4. Əgər çoxluğu almadısa → bütün aldıqlarını burax
```

---

## 15. Real-World: Flash Sale (10,000 İstifadəçi, 100 Məhsul)

*15. Real-World: Flash Sale (10,000 İstifadəçi, 100 Məhsul) üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class FlashSaleService
{
    private const STOCK_KEY  = 'flash_sale:stock:';
    private const WINNER_KEY = 'flash_sale:winners:';

    /**
     * Flash sale başlamazdan əvvəl stock-u Redis-ə yüklə.
     */
    public function initializeSale(int $saleId, int $stock): void
    {
        Redis::set(self::STOCK_KEY . $saleId, $stock);
        Redis::del(self::WINNER_KEY . $saleId);
    }

    /**
     * 10,000 concurrent request üçün thread-safe purchase.
     *
     * Strategy:
     * 1. Redis DECR ilə atomic stock azalt
     * 2. DB-yə yalnız uğurlu olanlar üçün yaz
     * 3. Pessimistic DB lock yoxdur → Redis bottleneck-dir
     */
    public function purchase(int $saleId, int $userId): array
    {
        $stockKey = self::STOCK_KEY . $saleId;

        // ATOMIC decrement — race condition yoxdur
        $remaining = Redis::decr($stockKey);

        if ($remaining < 0) {
            // Stock bitdi — artırıb geri qoy (negative olmasın)
            Redis::incr($stockKey);

            return [
                'success' => false,
                'message' => 'Sorry, this item is sold out!',
            ];
        }

        // Winner-i Redis set-ə əlavə et (duplicate check)
        $added = Redis::sadd(self::WINNER_KEY . $saleId, $userId);

        if (! $added) {
            // Bu user artıq satın alıb
            Redis::incr($stockKey); // Stock-u geri qoy
            return [
                'success' => false,
                'message' => 'You have already purchased this item.',
            ];
        }

        // DB-yə asinxron yaz (queue ilə)
        \App\Jobs\RecordFlashSalePurchase::dispatch($saleId, $userId)
            ->onQueue('critical');

        return [
            'success'   => true,
            'message'   => 'Congratulations! Purchase successful.',
            'remaining' => max(0, $remaining),
        ];
    }
}
```

*'remaining' => max(0, $remaining), üçün kod nümunəsi:*
```php
// app/Jobs/RecordFlashSalePurchase.php
class RecordFlashSalePurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $saleId,
        public readonly int $userId
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            FlashSalePurchase::create([
                'flash_sale_id' => $this->saleId,
                'user_id'       => $this->userId,
                'purchased_at'  => now(),
            ]);

            // Inventory-ni DB-də də azalt (eventual consistency)
            FlashSale::where('id', $this->saleId)
                ->decrement('remaining_stock');
        });
    }
}
```

**Arxitektura:**
```
10,000 requests → Load Balancer
                       ↓
               PHP Workers (many)
                       ↓
               Redis DECR (atomic, fast)    ← Bottleneck yox, Redis single-threaded
                       ↓ (winner only)
               Queue Workers (few)
                       ↓
               Database (relaxed write)
```

---

## 16. İntervyu Sualları

**Sual 1:** Concurrency ilə parallelism fərqi nədir?

**Cavab:** Concurrency — eyni vaxtda çox işlə məşğul olmaq (context switching), parallelism — fiziki olaraq eyni vaxtda çox işi yerinə yetirmək (çox CPU core). PHP-də tək process içərisində həqiqi parallelism yoxdur, lakin Fibers/ReactPHP/Swoole ilə cooperative concurrency mümkündür. Process level-də isə (queue workers, pcntl_fork) həqiqi parallelism var.

---

**Sual 2:** Race condition nədir, nümunə verin?

**Cavab:** Bir sistemin doğru işləməsinin birden çox proses/thread-in icra sırasından asılı olması vəziyyətidir. Məsələn: inventory check + decrement arasında başqa bir request eyni məhsulu alsa — ikisi birlikdə neqativ stock-a düşər. Həll: pessimistic lock (`lockForUpdate()`) və ya optimistic lock (version column).

---

**Sual 3:** `lockForUpdate()` vs `sharedLock()` fərqi?

**Cavab:** `lockForUpdate()` — `SELECT FOR UPDATE` — sətiri həm oxuma, həm yazma üçün lock edir. Başqa transaction bu sətiri lock ala bilməz. `sharedLock()` — `SELECT FOR SHARE` — çox transaction eyni anda oxuya bilər, amma heç biri yaza bilməz. Ödəniş kimi kritik yazma əməliyyatları üçün `lockForUpdate()`, yalnız oxuma consistency lazım olduqda `sharedLock()`.

---

**Sual 4:** Deadlock necə baş verir və necə önlənir?

**Cavab:** T1 A-nı lock edir, B-ni gözləyir. T2 B-ni lock edir, A-nı gözləyir. Həll: həmişə eyni sırada lock al (məsələn, kiçik ID-li sətri əvvəl lock et). DB-lər deadlock-u auto-detect edib birini rollback edir, amma bu gözlənilməz davranışdır.

---

**Sual 5:** Redis Lua script-i nə üçün istifadə edilir?

**Cavab:** Redis single-threaded-dir, amma birdən çox əmri atomic icra etmək üçün Lua script lazımdır. `GET` + `SET` iki ayrı əmrdir — aralarında başqa client dəyişə bilər. Lua script server-də bir atomic blok kimi icra edilir.

---

**Sual 6:** PHP Fibers nədir?

**Cavab:** PHP 8.1-də gələn, cooperative multitasking imkanı verən lightweight concurrency primitivi. Fiber `Fiber::suspend()` ilə özünü dondurur, caller `resume()` ilə davam etdirir. Generator-a bənzər, lakin daha güclüdür. Async framework-lər (ReactPHP, Amp) Fiber üzərindən qurulur.

---

**Sual 7:** Fan-out / Fan-in pattern nədir?

**Cavab:** Fan-out — bir task-ı çox paralel task-a bölmək. Fan-in — bütün paralel task-ların nəticəsini toplamaq. Laravel-də `Bus::batch()` bunu dəstəkləyir: minlərlə user-ə notification göndər (fan-out), hamısı bitdikdə summary report yarat (fan-in).

---

**Sual 8:** Bulkhead pattern nədir?

**Cavab:** Bir sistemin partlayışının digərini etkiləməsinin önlənməsi. Laravel queue-da ayrı connections/queues istifadə etmək — bulk işlər (report generation) sıxışdırsa, kritik işlər (payment) etkilənmir. Gəmi bölmələri kimi: biri dolsa digəri təsirlənmir.

---

**Sual 9:** Optimistic locking nə zaman seçilməlidir?

**Cavab:** Conflict az olduqda: çoxu oxuyur, az hissəsi yazır. Conflict çox olduqda pessimistic locking daha sürətlidir (retry loopundan daha az xərc). Optimistic locking-in üstünlüyü: lock yoxdur → deadlock yoxdur, yüksək concurrent oxuma performansı.

---

**Sual 10:** Flash sale-də niyə Redis istifadə edirik, birbaşa DB-yə niyə yazmırıq?

**Cavab:** DB `SELECT FOR UPDATE` under high load-da bottleneck yaradır. 10,000 concurrent request-in hamısı lock gözləyəcək. Redis DECR atomic-dir və son dərəcə sürətlidir (>100,000 ops/sec). Uğurlu alışları queue ilə DB-yə asinxron yazırıq — DB yükü normallaşır.

---

**Sual 11:** Redlock nə üçün lazımdır, `Cache::lock()` yetərli deyilmi?

**Cavab:** `Cache::lock()` tək Redis node-u üçün işləyir. Əgər o Redis node down olsa, lock itirilir. Redlock N node-un (adətən 5) çoxluğundan (3+) lock alır — tək node down olsa sistem işləməyə davam edir. Kritik distributed sistemlər üçün lazımdır.

---

**Sual 12:** SKIP LOCKED nədir?

**Cavab:** PostgreSQL/MySQL-in `SELECT FOR UPDATE SKIP LOCKED` əmridir. Artıq başqa transaction tərəfindən lock edilmiş sətirləri keç, növbəti lock edilməmiş sətiri götür. Laravel-in database queue driver-ı bunu istifadə edir — iki worker eyni job-u götürməz, bir-birini gözləmədən davam edər.

---

## Anti-patternlər

## Anti-Pattern Nə Zaman Olur?

**PHP-FPM single-threaded request modelinə concurrent pattern tətbiq etmək**
Standard Laravel/PHP-FPM mühitdə hər request öz prosesidir — eyni proses içərisində Fiber, coroutine, ya da async/await işlətmək üçün Swoole ya Octane lazımdır. Vanilla PHP-FPM-də `ReactPHP`, `spatie/async` ya da `Fiber`-i gətirib "parallel işlər" demək yanışdır — hər şey sequentially icra olunur, memory shared deyil. Bu pattern-ləri yalnız Swoole, RoadRunner, ya CLI context-də istifadə et.

```php
// YANLIŞ — PHP-FPM-də "concurrent" zənn etmək
// Bu kod sıraya icra olunur, parallel deyil!
$fiber1 = new Fiber(fn() => fetchFromApi('api1.com'));
$fiber2 = new Fiber(fn() => fetchFromApi('api2.com'));
$fiber1->start();
$fiber2->start();
// PHP-FPM-də bunlar hələ də sequential — event loop yoxdur

// DOĞRU — Octane/Swoole ilə concurrent
[$result1, $result2] = Octane::concurrently([
    fn() => fetchFromApi('api1.com'),
    fn() => fetchFromApi('api2.com'),
]);
// Bu həqiqətən parallel — Octane Swoole coroutine-ları istifadə edir

// PHP-FPM-də parallelism üçün düzgün yol: queue
ApiCallJob::dispatch('api1.com');
ApiCallJob::dispatch('api2.com');
// Ayrı worker proseslər icra edir — həqiqi parallelism
```

---

**1. Race condition-ı "nadir olur" deyə ignore etmək**
Yüksək yük olmadan test edib concurrent access problemini gözdən qaçırmaq — flash sale, kampaniya başlanğıcı kimi yüklü anlarda inventory mənfi olur, dublikat sifarişlər yaranır. `SELECT FOR UPDATE` ya da Redis atomic əməliyyatları ilə kritik bölgələri qoru.

**2. Pessimistic locking-i hər yerdə tətbiq etmək**
Az yazılan, çox oxunan cədvəllərdə `lockForUpdate()` işlətmək — oxuma əməliyyatları da bloklanır, throughput kəskin aşağı düşür, deadlock riski artır. Yüksək conflict ehtimalı az olan yerlər üçün optimistic locking (`version` column) istifadə et.

**3. Cache lock (mutex) vaxtaşımı olmadan tətbiq etmək**
`Cache::lock('key')` yaradıb TTL göstərməmək — prosess qəfil çöksə lock sonsuza qədər aktiv qalır, başqa worker-lər bloklanır. Həmişə `Cache::lock('key', $seconds)` ilə TTL ver, `block()` yerinə `get()` ilə mövcudluğu yoxla.

**4. Queue worker-lərini idempotent etmədən `retry` konfiqurasiya etmək**
`tries = 3` qoyub job-u idempotent etməmək — uğursuzluq halında eyni iş üç dəfə icra edilir, dublikat ödəniş, dublikat email göndərilir. Hər job-u idempotency key ilə qoru, eyni əməliyyatın iki dəfə icrasını önləmək üçün DB constraint ya da Redis flag istifadə et.

**5. Database queue driver-i yüksək yüklü mühitdə işlətmək**
Production-da `QUEUE_CONNECTION=database` buraxmaq — çoxlu worker-lər DB cədvəlini polling edərkən əhəmiyyətli yük yaradır, queue latency artır. Production üçün Redis queue driver-ı işlət, `SKIP LOCKED` dəstəyi olan DB driver-ı yalnız aşağı yüklü hallar üçün saxla.

**6. Deadlock ehtimalını nəzərə almadan çoxlu cədvəl lock-ları yaratmaq**
Fərqli transaction-larda cədvəlləri fərqli sırada locklamaq (T1: A→B, T2: B→A) — hər iki transaction bir-birini gözləyib donur. Bütün transaction-larda cədvəl lock sırasını sabit tut, lock müddətini minimuma endir, `deadlock` xətası üçün retry mexanizmi yaz.

---

## Əlaqəli Mövzular

- [CQRS](../integration/01-cqrs.md) — read/write ayrılması ilə lock contentioni azaldır
- [Technical Debt](05-technical-debt.md) — concurrency bug-ları ən gizli texniki borclardan biridir
- [Outbox Pattern](../integration/04-outbox-pattern.md) — distributed transaction-da race condition-sız eventual consistency
