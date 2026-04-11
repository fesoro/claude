# Swoole & ReactPHP (Async PHP)

## Mündəricat
1. [Niyə Async PHP?](#niyə-async-php)
2. [ReactPHP — Event Loop](#reactphp--event-loop)
3. [Swoole — Coroutine & Co-routine](#swoole--coroutine)
4. [FPM vs Async Müqayisəsi](#fpm-vs-async-müqayisəsi)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə Async PHP?

```
PHP-FPM problemi: hər request → ayrı worker.
Worker I/O gözləyərkən bloklanır.

100 concurrent user → 100 worker lazımdır.
1000 concurrent user → 1000 worker → RAM/CPU problem.

Async həll:
  Bir proses → minlərlə concurrent connection.
  I/O gözləyərkən başqa işi görür.

Node.js nümunəsi (async-ın niyə işlədiyini göstərir):
  1 thread → 10,000 concurrent HTTP sorğu handle edir.
  Çünki I/O non-blocking, CPU-bound deyil.

PHP async variantları:
  ReactPHP  → Pure PHP event loop
  Swoole    → C extension, coroutine support
  Fibers    → PHP 8.1 low-level primitive (library-lər bunun üstündə)
  Amp v3    → Fibers əsaslı async framework
```

---

## ReactPHP — Event Loop

```
ReactPHP — pure PHP, event-driven non-blocking I/O.

Event Loop əsas konsepti:
  while (true) {
      I/O ready olan event-ləri yoxla
      Callback-ləri çağır
      Timer-ları yoxla
  }

Komponentlər:
  EventLoop  → Əsas loop
  Stream     → Non-blocking I/O
  Promise    → Async nəticə
  Http       → Non-blocking HTTP server/client
  Timer      → setTimeout/setInterval analogu

Blocking vs Non-blocking:
  Blocking:    $data = file_get_contents('http://api.com'); // gözlər
  Non-blocking: $client->get('http://api.com')->then(fn($r) => process($r));
                // callback registrasiya, davam et

Promise chain:
  $client->get('/users')
      ->then(fn($response) => json_decode($response->getBody()))
      ->then(fn($users) => processUsers($users))
      ->catch(fn($error) => handleError($error));
```

---

## Swoole — Coroutine

```
Swoole — C extension, PHP-yə coroutine əlavə edir.

Coroutine:
  Lightweight "green thread".
  OS thread deyil — user-space.
  Cooperative scheduling (yield nöqtələrində switch).
  Bir OS thread-i → minlərlə coroutine.

Swoole HTTP Server:
  $server = new Swoole\HTTP\Server('0.0.0.0', 9501);
  
  $server->on('request', function($request, $response) {
      // Bu callback coroutine içindədir
      // go() ilə parallel coroutine başlatmaq olar
      $data = \Swoole\Coroutine\Http\get('http://api.com/users');
      $response->end(json_encode($data));
  });
  
  $server->start();

Swoole faydaları vs FPM:
  - Yüksək concurrent connection (100K+)
  - Shared memory (stateful mümkündür)
  - WebSocket, TCP server native dəstək
  - Coroutine-based async DB/Redis

Swoole məhdudiyyətləri:
  - Shared state → race condition riski
  - Memory leak → uzun müddətli proses
  - Laravel/Symfony tam dəstək (Octane ilə)
  - Learning curve yüksəkdir
```

---

## FPM vs Async Müqayisəsi

```
┌──────────────────┬──────────────────┬──────────────────┐
│                  │    PHP-FPM       │  Swoole/Async    │
├──────────────────┼──────────────────┼──────────────────┤
│ Concurrency      │ Process-based    │ Coroutine-based  │
│ Memory           │ Per-request      │ Shared (uzun ömür│
│ State            │ Stateless        │ Stateful mümkün  │
│ Crash isolation  │ ✅ Tam           │ ⚠️ Proses paylaşır│
│ I/O model        │ Blocking         │ Non-blocking     │
│ Max concurrency  │ max_children     │ 10K+             │
│ Memory leak risk │ Az (restart)     │ Yüksək           │
│ WebSocket        │ ❌              │ ✅ Native         │
│ Learning curve   │ Aşağı           │ Yüksək           │
│ Ecosystem        │ Tam             │ Məhdud           │
└──────────────────┴──────────────────┴──────────────────┘

Nə vaxt Swoole/Async:
  ✓ WebSocket server
  ✓ High concurrent connections (chat, gaming)
  ✓ Long polling
  ✓ Microservice internal API (yüksək throughput)

Nə vaxt FPM:
  ✓ Standart web API
  ✓ Sadə CRUD
  ✓ Team async bilmirsə
  ✓ Framework dəstəyi vacibdir
```

---

## PHP İmplementasiyası

```php
<?php
// ReactPHP HTTP Server
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\EventLoop\Loop;
use Psr\Http\Message\ServerRequestInterface;

$server = new HttpServer(function (ServerRequestInterface $request) {
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode(['path' => $request->getUri()->getPath()])
    );
});

$socket = new React\Socket\SocketServer('0.0.0.0:8080');
$server->listen($socket);
echo "Server işləyir: http://0.0.0.0:8080\n";

// ReactPHP ilə parallel HTTP sorğular
use React\Http\Browser;

$browser = new Browser();

// Paralel 3 API sorğusu
$promises = [
    $browser->get('http://api1.com/data'),
    $browser->get('http://api2.com/data'),
    $browser->get('http://api3.com/data'),
];

React\Promise\all($promises)->then(function (array $responses) {
    foreach ($responses as $response) {
        echo $response->getBody() . "\n";
    }
});

Loop::run();
```

```php
<?php
// Swoole Coroutine — parallel DB queries
use Swoole\Coroutine;

Coroutine\run(function () {
    // İki DB sorğusu paralel icra edilir
    [$users, $orders] = Coroutine\batch([
        'users'  => fn() => DB::query('SELECT * FROM users LIMIT 10'),
        'orders' => fn() => DB::query('SELECT * FROM orders LIMIT 10'),
    ]);

    echo "Users: " . count($users) . "\n";
    echo "Orders: " . count($orders) . "\n";
});

// Laravel Octane (Swoole ilə)
// php artisan octane:start --server=swoole --workers=4
// Əvvəlki framework lifecycle fərqi:
//   FPM: hər request → bootstrap (container, config, providers)
//   Octane: bir dəfə bootstrap → request-lər paylaşır → 10x sürətli
```

---

## İntervyu Sualları

- ReactPHP event loop necə işləyir?
- Swoole coroutine OS thread-dən nəylə fərqlənir?
- PHP-FPM stateless olduğu üçün güclüdür — Swoole-da shared state riskləri nələrdir?
- Laravel Octane nə üçün Swoole/RoadRunner istifadə edir?
- Async PHP-nin WebSocket üçün FPM-dən niyə daha uyğun olduğunu izah edin.
