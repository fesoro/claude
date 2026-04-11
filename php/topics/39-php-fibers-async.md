# PHP Fibers və Async Proqramlaşdırma

## Mündəricat
1. [PHP Fibers nədir?](#php-fibers-nədir)
2. [Fiber Lifecycle](#fiber-lifecycle)
3. [Fibers vs Generators vs Coroutines](#fibers-vs-generators-vs-coroutines)
4. [Event Loop Konsepti](#event-loop-konsepti)
5. [ReactPHP](#reactphp)
6. [Swoole / OpenSwoole](#swoole--openswoole)
7. [Laravel Octane](#laravel-octane)
8. [Async PHP-nin Mənalı Olduğu Hallar](#async-phpnin-mənalı-olduğu-hallar)
9. [Real Use Case: 10k Concurrent Connection](#real-use-case-10k-concurrent-connection)
10. [İntervyu Sualları](#intervyu-sualları)

---

## PHP Fibers nədir?

PHP 8.1 ilə gəldi. Fiber — ayrı bir execution stack-ə malik olan, əsas proqramı bloklamadan icra oluna bilən kod bloğudur.

**Thread deyil!** Fiber-lər:
- Single-threaded-dir (yalnız bir Fiber eyni anda icra olunur)
- Cooperative multitasking — Fiber özü `suspend` etməlidir
- Preemptive deyil — OS Fiber-i dayandırmır

```
Thread (OS idarə edir):        Fiber (Fiber özü idarə edir):

Thread1 ──────[preempt]──      Fiber1 ──────[suspend()]──
Thread2         ──────────     Main          ──────────
Thread1 ──────────             Fiber1                 ──

Həm thread1 həm thread2        Eyni anda yalnız biri işləyir,
eyni anda icra oluna bilər     əl çəkmə öz əlindədir
```

---

## Fiber Lifecycle

```
new Fiber(callable) → created
       │
  start($value)
       │
       ▼
    running ──→ suspend($value) ──→ suspended
       │              ↑                  │
       │         resume($value) ─────────┘
       │
       ▼
  terminated
```

**Kod nümunəsi:**

```php
// Əsas Fiber nümunəsi
$fiber = new Fiber(function(): string {
    echo "Fiber başladı\n";
    
    $value = Fiber::suspend('birinci suspend');
    echo "Resume edildi, gələn dəyər: $value\n";
    
    $value = Fiber::suspend('ikinci suspend');
    echo "Yenidən resume, dəyər: $value\n";
    
    return 'fiber tamamlandı';
});

// Fiber-i başlat
$result1 = $fiber->start();
echo "Fiber suspend etdi: $result1\n";  // "birinci suspend"

// Fiber-i davam etdir
$result2 = $fiber->resume('salam birinci');
echo "Fiber suspend etdi: $result2\n";  // "ikinci suspend"

// Son davam
$fiber->resume('salam ikinci');
echo "Fiber tamamlandı: " . $fiber->getReturn() . "\n";
```

**Çıxış:**
```
Fiber başladı
Fiber suspend etdi: birinci suspend
Resume edildi, gələn dəyər: salam birinci
Fiber suspend etdi: ikinci suspend
Yenidən resume, dəyər: salam ikinci
Fiber tamamlandı: fiber tamamlandı
```

**Fiber metodları:**

```php
$fiber = new Fiber(fn() => Fiber::suspend());

$fiber->start(...$args);    // Fiber-i başlat
$fiber->resume($value);     // Suspend edilmiş Fiber-i davam etdir
$fiber->getReturn();        // Fiber-in return dəyərini al
$fiber->isStarted();        // Başlayıbmı?
$fiber->isRunning();        // İcra edilirmi?
$fiber->isSuspended();      // Suspend edilib?
$fiber->isTerminated();     // Tamamlanıb?

// Fiber içindən:
Fiber::this();              // Cari Fiber-i al
Fiber::suspend($value);     // Suspend et, $value əsas proqrama göndərilir
```

---

## Fibers vs Generators vs Coroutines

```
┌────────────────┬──────────────────────┬───────────────────────────┐
│                │    Generators        │         Fibers            │
├────────────────┼──────────────────────┼───────────────────────────┤
│ PHP versiyası  │ PHP 5.5+             │ PHP 8.1+                  │
│ Başlatma       │ Funksiya çağırışı    │ new Fiber() + start()     │
│ Suspend        │ yield                │ Fiber::suspend()          │
│ Resume         │ ->send($value)       │ ->resume($value)          │
│ İstifadə       │ Iterator, lazy eval  │ Async, event loop         │
│ Stack          │ Yoxdur (flat)        │ Ayrı execution stack      │
│ Nested suspend │ Mümkün deyil         │ Mümkündür                 │
└────────────────┴──────────────────────┴───────────────────────────┘
```

**Coroutine** — ümumi anlayışdır. Fiber və Generator hər ikisi coroutine növüdür.

---

## Event Loop Konsepti

```
┌─────────────────────────────────────────────┐
│                  Event Loop                 │
│                                             │
│  while (true) {                             │
│      $events = checkReadySockets();         │
│      foreach ($events as $event) {         │
│          $handler($event);  // callback     │
│      }                                      │
│      checkTimers();                         │
│  }                                          │
└─────────────────────────────────────────────┘

Ənənəvi PHP (blocking):          Async PHP (non-blocking):
                                  
Request → DB query → wait         Request → DB query start
          (2s wait)               ↓                    ↓
          → response              Event Loop       (2s later)
                                  ↓                    ↓
                                  Başqa sorğu      DB callback
                                  icra edilir      response
```

---

## ReactPHP

Event-driven, non-blocking I/O framework.

*Event-driven, non-blocking I/O framework üçün kod nümunəsi:*
```bash
composer require react/http react/mysql
```

**Sadə HTTP server:**

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();

$server = new React\Http\HttpServer(function (
    Psr\Http\Message\ServerRequestInterface $request
) use ($loop): React\Http\Message\Response {
    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/plain'],
        "Salam dünya!\n"
    );
});

$socket = new React\Socket\SocketServer('0.0.0.0:8080');
$server->listen($socket);

echo "Server 8080 portunda işləyir\n";
$loop->run();
```

**Async database sorğusu:**

```php
<?php
use React\MySQL\Factory;

$factory = new Factory();

$connection = $factory->createLazyConnection('user:pass@localhost/dbname');

// Non-blocking sorğu — callback-based
$connection->query('SELECT * FROM users WHERE id = ?', [1])
    ->then(function (React\MySQL\QueryResult $result) {
        var_dump($result->resultRows);
    }, function (Exception $e) {
        echo 'Xəta: ' . $e->getMessage() . "\n";
    });

// Eyni anda başqa iş görülə bilər
echo "Bu sətir DB cavabından ƏVVƏL çap edilir\n";
```

---

## Swoole / OpenSwoole

Swoole — PHP üçün yüksək performanslı, coroutine-based networking framework.

*Swoole — PHP üçün yüksək performanslı, coroutine-based networking fram üçün kod nümunəsi:*
```bash
pecl install swoole
# php.ini: extension=swoole
```

**Swoole HTTP Server:**

```php
<?php
$server = new Swoole\HTTP\Server("0.0.0.0", 9501);

// Worker-lər başladıqda bir dəfə çağırılır
$server->on('WorkerStart', function (Swoole\Server $server, int $workerId) {
    // Burada PDO, Redis bağlantısı yaradılır — bir dəfə!
});

$server->on('request', function (
    Swoole\HTTP\Request $request,
    Swoole\HTTP\Response $response
) {
    // Hər sorğu üçün — amma coroutine içindədir
    $response->header("Content-Type", "text/plain");
    $response->end("Salam, Swoole!\n");
});

$server->start();
```

**Coroutine ilə async database:**

```php
<?php
use Swoole\Coroutine\MySQL;

// Swoole coroutine içindəki kod sanki sinxron görünür,
// amma əslində async işləyir
go(function () {
    $db = new MySQL();
    $db->connect([
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'database' => 'test',
    ]);

    // Bu sətir coroutine-i suspend edir, başqa coroutine icra olunur
    $result = $db->query('SELECT SLEEP(2)');
    // 2 saniyə sonra davam edir
    
    echo "Nəticə: ";
    var_dump($result);
});
```

---

## Laravel Octane

Laravel Octane — tətbiqi yaddaşda saxlayaraq sorğular arasında bootstrap xərcini aradan qaldırır.

*Laravel Octane — tətbiqi yaddaşda saxlayaraq sorğular arasında bootstr üçün kod nümunəsi:*
```bash
composer require laravel/octane
php artisan octane:install --server=swoole  # və ya roadrunner
php artisan octane:start --workers=4
```

**Necə işləyir:**

```
Ənənəvi Laravel (PHP-FPM):
Hər sorğu:
  bootstrap → service container → routes → middleware → controller → response
  (50-100ms)

Laravel Octane (Swoole):
Server başlarken: bootstrap bir dəfə
Hər sorğu:
  (hazır container) → routes → middleware → controller → response
  (1-5ms)
```

**⚠️ Kritik Pitfall-lar:**

**1. Static state contamination:**

```php
// ❌ Yanlış: Static property sorğular arasında qalır
class OrderService
{
    private static array $cache = [];
    
    public function process(): void
    {
        static::$cache[] = $data; // Növbəti sorğuda da burada olacaq!
    }
}

// ✅ Düzgün: Instance property istifadə et
class OrderService
{
    private array $cache = [];
    // Hər sorğu yeni instance yaradır (əgər singleton deyilsə)
}
```

**2. Singleton leak:**

```php
// ❌ Yanlış: Singleton state birinci sorğudan qalır
class CurrentUser
{
    private static ?User $user = null;
    
    public static function set(User $user): void
    {
        static::$user = $user;
    }
}

// ✅ Düzgün: Request lifecycle-a bağla
// octane:swoole config-inda "flush" listener
protected $listeners = [
    RequestHandled::class => [
        FlushCurrentUser::class,
    ],
];
```

**3. Database bağlantıları:**

```php
// Octane ilə Laravel avtomatik DB bağlantılarını sıfırlayır
// amma custom bağlantılar manual sıfırlanmalıdır

// config/octane.php
'listeners' => [
    RequestTerminated::class => [
        \App\Listeners\ResetCustomConnections::class,
    ],
],
```

---

## Amp v3 — Fiber-Based Async

Amp v3 PHP 8.1 Fiber-lərini native istifadə edən async framework-dür. Callback hell yoxdur, sinxron görünən async kod yazılır:

*Amp v3 PHP 8.1 Fiber-lərini native istifadə edən async framework-dür.  üçün kod nümunəsi:*
```bash
composer require amphp/amp amphp/http-client
```

*composer require amphp/amp amphp/http-client üçün kod nümunəsi:*
```php
<?php
use Amp\Future;
use Amp\Http\Client\HttpClientBuilder;
use function Amp\async;
use function Amp\await;

// Bir neçə HTTP sorğusunu paralel et
$client = HttpClientBuilder::buildDefault();

// async() — Fiber yaradıb background-da icra edir
$future1 = async(function () use ($client) {
    $response = $client->request(new Request('https://api.example.com/users'));
    return $response->getBody()->buffer();
});

$future2 = async(function () use ($client) {
    $response = $client->request(new Request('https://api.example.com/products'));
    return $response->getBody()->buffer();
});

// await() — Fiber::suspend() kimi işləyir, başqa Fiber-lər işləyə bilər
[$users, $products] = Future\await([$future1, $future2]);

// Laravel-də parallel HTTP client sorğuları üçün ReactPHP və ya Amp istifadə edilir
// Standart Laravel Http::pool() da bu konsepti istifadə edir (amma blocking üsulla)
```

**Laravel Http::pool() — daxili paralel HTTP sorğuları:**

```php
// Laravel-in built-in concurrent HTTP client (Guzzle promise-based)
[$users, $products, $orders] = Http::pool(fn (Pool $pool) => [
    $pool->as('users')->get('https://api/users'),
    $pool->as('products')->get('https://api/products'),
    $pool->as('orders')->get('https://api/orders'),
]);

// Bütün sorğular eyni anda göndərilir, ən uzun sorğu qədər gözlənilir
// PHP-FPM ilə işləyir — Fiber tələb etmir (Guzzle async istifadə edir)
echo $users->json();
echo $products->json();
```

---

## Async PHP-nin Mənalı Olduğu Hallar

```
✅ Mənalı:
  - Çox sayda eyni vaxtda gələn uzun-müddətli I/O (WebSocket, SSE)
  - Paralel HTTP API sorğuları (eyni anda 10 xarici API çağırışı)
  - Real-time chat, gaming server
  - Long-polling endpoint-lər
  - Queue worker-lər (Swoole ilə)

❌ Mənasız / az fayda:
  - Standart CRUD tətbiqləri
  - Az sayda istifadəçi olan tətbiqlər
  - CPU-bound işlər (async bunun üçün deyil)
  - Sadə REST API (PHP-FPM + nginx kifayətdir)
```

---

## Real Use Case: 10k Concurrent Connection

**PHP-FPM ilə:**

```
10,000 eyni vaxtda istifadəçi:
  - Hər istifadəçi üçün 1 PHP-FPM worker lazımdır
  - 10,000 worker = ~10GB+ RAM
  - Praktiki olaraq mümkün deyil
  
Həll: Nginx → proxy → queue (RabbitMQ) → PHP işçiləri
```

**Swoole ilə:**

```
10,000 eyni vaxtda istifadəçi:
  - 4 CPU = 4 worker
  - Hər worker coroutine-larla 1000+ bağlantı saxlayır
  - 4 worker × 1000 = 4,000+ (artıq reallaşdırılabilir)
  
Yaddaş: ~500MB (çünki state paylaşılır)
```

*Yaddaş: ~500MB (çünki state paylaşılır) üçün kod nümunəsi:*
```php
// Swoole WebSocket server — 10k bağlantı
$server = new Swoole\WebSocket\Server("0.0.0.0", 9502);

$server->set([
    'worker_num' => swoole_cpu_num(),
    'max_conn' => 10000,
    'heartbeat_check_interval' => 60,
    'heartbeat_idle_time' => 120,
]);

// Bütün bağlantıları yaddaşda saxla
$connections = new Swoole\Table(10000);
$connections->column('user_id', Swoole\Table::TYPE_INT);
$connections->create();

$server->on('open', function ($server, $request) use ($connections) {
    $connections->set($request->fd, ['user_id' => $request->get['user_id'] ?? 0]);
    echo "Bağlantı açıldı: {$request->fd}\n";
});

$server->on('message', function ($server, $frame) {
    // Mesajı broadcast et
    foreach ($server->connections as $fd) {
        if ($server->isEstablished($fd)) {
            $server->push($fd, $frame->data);
        }
    }
});

$server->on('close', function ($server, $fd) use ($connections) {
    $connections->del($fd);
});

$server->start();
```

---

## İntervyu Sualları

**1. PHP Fiber nədir, thread-dən fərqi nədir?**
Fiber single-threaded cooperative multitasking mexanizmidir. Thread-dən fərqli olaraq, eyni anda yalnız bir Fiber icra olunur. Fiber özü `Fiber::suspend()` çağırmalıdır — OS onu dayandırmır. Thread-lər isə OS tərəfindən preemptive olaraq dəyişdirilir.

**2. Laravel Octane ilə ənənəvi PHP-FPM arasındakı fərq nədir?**
PHP-FPM hər sorğuda tətbiqi yenidən bootstrap edir (~50-100ms). Octane tətbiqi bir dəfə yaddaşa yükləyir, sonrakı sorğular bootstrap olmadan icra edilir (~1-5ms). Amma singleton ve static state-lərin sorğular arası sıfırlanmasına diqqət lazımdır.

**3. Swoole coroutine-ları Fiber-dən necə fərqlənir?**
Swoole coroutine-ları Swoole runtime tərəfindən idarə edilir və I/O operasiyalarında avtomatik yield edir. Fiber-lər PHP-nin native mexanizmidir və manual `suspend/resume` tələb edir. Swoole əslində daxilən Fiber-ə bənzər mexanizm istifadə edir.

**4. ReactPHP nə üçün istifadə edilir?**
Event-driven, non-blocking I/O tətbiqləri üçün. HTTP server, TCP/UDP server, async database sorğuları, parallel HTTP client sorğuları. PHP-FPM olmadan uzun müddətli proses kimi işləyir.

**5. Async PHP istifadə edərkən ən böyük risk nədir?**
Memory leaks və state contamination. Uzun müddətli proseslərdə (Swoole, Octane) statik dəyişənlər, singleton-lar sorğular arasında qalır. Hər sorğudan sonra düzgün sıfırlanmasa, bir istifadəçinin datası başqasına görünə bilər.

**6. 100,000 eyni vaxtda WebSocket bağlantısı necə idarə olunur?**
Swoole və ya ReactPHP ilə event loop əsaslı server istifadə edilir. Hər bağlantı üçün ayrı process/thread yaratmaq əvəzinə, I/O multiplexing (epoll/kqueue) istifadə edilir. Bu cür server az sayda worker-lə çox sayda bağlantını idarə edə bilər.

**7. Fiber::suspend() nə qaytarır?**
`resume($value)` ilə göndərilən dəyəri qaytarır. `$value = Fiber::suspend('ilk')` — burada `$value` növbəti `resume()` çağırışındakı arqumentdir.

**8. PHP-də native thread varmı?**
Parallel extension (`parallel\Runtime`) vasitəsilə real thread-lər mümkündür amma PHP-nin standard library-si thread-safe deyil. Fiber-lər isə thread deyil — single-threaded coroutine-lardır.

**9. Amp (amphp) framework nədir, ReactPHP-dən fərqi?**
Amp v3+ Fiber-lər üzərindən qurulmuş async framework-dür. ReactPHP callback-based, Amp isə coroutine-based-dir — `await` sintaksisi ilə sinxron görünən async kod yazılır. ReactPHP daha köhnə, daha geniş yayılmış; Amp daha müasir PHP 8.1+ Fiber API-sini native istifadə edir. Hər ikisi non-blocking I/O üçün event loop işlədir.

**10. FrankenPHP nədir?**
Go dilində yazılmış, PHP-ni Go HTTP serverin içinə embed edən modern PHP server. Apache, Nginx, PHP-FPM kombinasiyasını əvəzləyir. Worker mode-da Laravel tətbiqini yaddaşda saxlayır (Octane-ə bənzər). HTTP/3, early hints, Mercure (server-sent events) built-in dəstəkləyir. Larvel Octane 2.0+ FrankenPHP server-ini rəsmən dəstəkləyir.

**11. Fiber exception propagation necə işləyir?**
Fiber içindəki atılmayan exception `$fiber->start()` ya da `$fiber->resume()` çağırışında outer kontekstə yayılır. `$fiber->throw(new \Exception())` isə Fiber içinə exception inject edir — `$value = Fiber::suspend()` ifadəsi exception atır. Bu pattern Fiber içindəki I/O xətalarını outer koda ötürmək üçün istifadə edilir.

**12. `parallel` extension Fiber-dən nə ilə fərqlənir?**
`parallel\Runtime` həqiqi OS thread-lər yaradır — CPU-bound işlər paralel icra olunur. Fiber isə single-threaded coroutine-dır, CPU-bound işlər üçün fayda vermir. `parallel` PHP Fiber-dən əvvəl mövcud idi, lakin PHP standard library-sinin thread-unsafe hissələrini paylaşmaq olmur. Yalnız primitive type-lar, array, closure-lar thread-lər arasında ötürülə bilir.

---

## Fiber-lərlə Practical Patterns

### Timeout Pattern

*Timeout Pattern üçün kod nümunəsi:*
```php
<?php
// Fiber ilə timeout implementasiyası
function withTimeout(callable $task, float $timeoutSeconds): mixed
{
    $fiber = new Fiber($task);
    $startTime = microtime(true);

    $result = $fiber->start();

    while (!$fiber->isTerminated()) {
        if (microtime(true) - $startTime > $timeoutSeconds) {
            // Fiber-i terminate etmək üçün exception göndər
            try {
                $fiber->throw(new \RuntimeException("Timeout exceeded: {$timeoutSeconds}s"));
            } catch (\RuntimeException) {
                // Fiber exception-ı tutub bitdi
            }
            throw new \RuntimeException("Task timed out after {$timeoutSeconds}s");
        }
        $result = $fiber->resume();
    }

    return $fiber->getReturn();
}
```

### Concurrent Tasks — Fan-out Pattern

*Concurrent Tasks — Fan-out Pattern üçün kod nümunəsi:*
```php
<?php
// Bir neçə işi "eyni vaxtda" icra et (cooperative concurrency)
function runConcurrently(callable ...$tasks): array
{
    // Hər task üçün Fiber yarat
    $fibers = array_map(fn($task) => new Fiber($task), $tasks);
    $results = [];

    // Bütün Fiber-ləri başlat
    foreach ($fibers as $fiber) {
        $fiber->start();
    }

    // Hamısı bitənə qədər round-robin icra et
    $running = true;
    while ($running) {
        $running = false;
        foreach ($fibers as $i => $fiber) {
            if ($fiber->isSuspended()) {
                $fiber->resume();
                $running = true;
            }
            if ($fiber->isTerminated() && !isset($results[$i])) {
                $results[$i] = $fiber->getReturn();
            }
        }
    }

    return $results;
}

// İstifadə:
[$userData, $orderData] = runConcurrently(
    fn() => fetchUserFromDb(1),
    fn() => fetchOrdersFromApi(1),
);
```

### Laravel-də Octane Concurrent Tasks (real istifadə)

*Laravel-də Octane Concurrent Tasks (real istifadə) üçün kod nümunəsi:*
```php
<?php
use Laravel\Octane\Facades\Octane;

// Octane::concurrently() Swoole coroutines istifadə edir
// PHP-FPM-də işləmir, yalnız Swoole/RoadRunner ilə

[$users, $products, $stats] = Octane::concurrently([
    fn() => User::with('profile')->paginate(20),
    fn() => Product::active()->take(10)->get(),
    fn() => DB::table('orders')->selectRaw('COUNT(*) as total, SUM(amount) as revenue')->first(),
]);

// Üç DB sorğusu paralel icra edilir
// Toplam süre = max(t1, t2, t3) yerine t1+t2+t3
```

---

## Anti-patternlər

**1. Fiber-ləri thread kimi istifadə etmək**
PHP Fiber-lərinin paralel icra etdiyini düşünüb CPU-bound işləri Fiber-ə vermək — Fiber-lər single-threaded coroutine-lardır, eyni anda yalnız bir Fiber işləyir, CPU-intensive task-lər digər Fiber-ləri bloklamağa davam edir. CPU parallelizmi üçün `parallel` extension ya da ayrı proseslər istifadə et.

**2. Laravel Octane-də singleton state-ləri sorğular arası sıfırlamamaq**
Octane ilə tətbiqi uzun müddətli proses kimi işlədib static property-ləri, singleton cache-ləri sıfırlamamaq — bir istifadəçinin datası başqasının sorğusuna sıza bilər, memory leak baş verər. `octane:install` ilə state reset listener-ləri əlavə et, `scoped()` binding-lərdən istifadə et.

**3. Blocking I/O əməliyyatlarını async mühitdə birbaşa çağırmaq**
Swoole/ReactPHP event loop-unda standart `file_get_contents()`, `mysqli_query()` işlətmək — bu funksiyalar bütün event loop-u bloklayır, bütün digər bağlantılar donur. Swoole coroutine-aware client-lər ya da ReactPHP async adapter-lar istifadə et.

**4. `Fiber::suspend()` xətasını handle etməmək**
Fiber-in suspend/resume dövrünü try-catch olmadan yazmaq — Fiber içərisindəki exception outer kontekstə yayılmır, unhandled qalır, sistem qeyri-müəyyən vəziyyətə düşür. Fiber-lərdə exception handling-i ayrıca planla, `$fiber->getReturn()` çağıranda `isTerminated()` yoxla.

**5. Çox sayda Fiber yaradıb memory-i doldurmaq**
Hər incoming request üçün yeni Fiber yaratmaq — Fiber-lər yaddaş tutur, minlərlə suspended Fiber RAM-ı doldurur. Fiber pool ya da bounded concurrency mexanizmi istifadə et, eyni anda işləyən Fiber sayına limit qoy.

**6. Async PHP-ni hər layihəyə tətbiq etmək**
Sadə CRUD tətbiqlərini Swoole ya da Octane-ə keçirtmək — mürəkkəblik artır, debugging çətinləşir, state management baş ağrısına çevrilir, real fayda isə yoxdur. Async PHP-ni yalnız yüksək concurrency, WebSocket ya da real-time tələb edən hallarda seç.
