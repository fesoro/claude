# HTTP Client Patterns (Guzzle, retry, async, concurrent) (Middle)

## Mündəricat
1. [HTTP client nə üçündür?](#http-client-nə-üçündür)
2. [Guzzle əsasları](#guzzle-əsasları)
3. [Timeout konfiqurasiyası](#timeout-konfiqurasiyası)
4. [Retry strategy](#retry-strategy)
5. [Circuit breaker integration](#circuit-breaker-integration)
6. [Concurrent requests (async)](#concurrent-requests-async)
7. [Connection pooling](#connection-pooling)
8. [PSR-18 HTTP Client](#psr-18-http-client)
9. [Laravel HTTP facade](#laravel-http-facade)
10. [Mock & testing](#mock--testing)
11. [Best practices](#best-practices)
12. [İntervyu Sualları](#intervyu-sualları)

---

## HTTP client nə üçündür?

```
Hər PHP backend başqa servislərə HTTP sorğu göndərir:
  - Payment gateway (Stripe, PayPal)
  - Email API (SendGrid, Mailgun)
  - SMS (Twilio)
  - External APIs (Google, Facebook)
  - Internal microservices
  - Webhook callback-lər

Native PHP:
  - file_get_contents($url) — sadə, kontrol az
  - curl_exec() — güclü, amma boilerplate çox

Modern PHP:
  - Guzzle — de-facto standart
  - Symfony HttpClient — yüngül alternativ
  - Saloon — "API SDK builder" (Laravel üçün)
  - Laravel Http facade — Guzzle wrapper

Əsas tələblər:
  - Timeout (sonsuz gözləmə olmamalı!)
  - Retry (flaky network qarşı)
  - Connection pooling (yenidən istifadə)
  - Circuit breaker (cascade failure qarşı)
  - Observability (log, metric, trace)
```

---

## Guzzle əsasları

```bash
composer require guzzlehttp/guzzle
```

```php
<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;  // 4xx
use GuzzleHttp\Exception\ServerException;  // 5xx

$client = new Client([
    'base_uri'        => 'https://api.example.com',
    'timeout'         => 5.0,           // TOTAL timeout (connect + transfer)
    'connect_timeout' => 2.0,           // connect faza limit
    'read_timeout'    => 5.0,           // stream read chunk timeout
    'http_errors'     => true,          // 4xx/5xx → exception (default true)
    'verify'          => true,          // SSL cert verify (production: true!)
    'headers'         => [
        'Accept'     => 'application/json',
        'User-Agent' => 'MyApp/1.0',
    ],
]);

try {
    $res = $client->post('/users', [
        'json' => ['name' => 'Ali'],
        'headers' => [
            'Authorization' => "Bearer $token",
        ],
    ]);
    
    $data = json_decode((string) $res->getBody(), true);
    
} catch (ConnectException $e) {
    // DNS fail, connection refused, network unreachable
    // RETRY edilə bilər
} catch (ClientException $e) {
    // 4xx — client error (bad request, not found, unauthorized)
    // RETRY EDILMƏMƏLİDİR (400/401/403/404)
    $status = $e->getResponse()->getStatusCode();
} catch (ServerException $e) {
    // 5xx — server error (bad gateway, service unavailable)
    // RETRY edilə bilər (500, 502, 503, 504)
} catch (RequestException $e) {
    // Ümumi request problem
}
```

---

## Timeout konfiqurasiyası

```
Heç vaxt default timeout (sonsuz) ilə production-da istifadə etmə!

Timeout növləri:
  connect_timeout   TCP əlaqə qurma limiti (2-5s)
  timeout           Total request-response limiti (5-30s)
  read_timeout      Chunk read limit (stream üçün)

PHP FPM default request timeout ~30s.
İçəri HTTP client 60s gözləyirsə — worker asılı qalır!

Qaydalar:
  İnternal API (fast):     connect 1s, total 3s
  External (orta):         connect 2s, total 10s
  Slow external (batch):   connect 5s, total 60s (+ queue işlət)
  
  ❌ timeout=0 (unlimited) — heç vaxt!
```

```php
<?php
// Timeout budget pattern — cumulative
$remainingBudget = 10.0;  // 10 saniyə total

$start = microtime(true);
$res1 = $client->get('/a', ['timeout' => $remainingBudget]);
$elapsed = microtime(true) - $start;
$remainingBudget -= $elapsed;

if ($remainingBudget <= 0) {
    throw new TimeoutException('Budget exceeded');
}

$res2 = $client->get('/b', ['timeout' => $remainingBudget]);
```

---

## Retry strategy

```php
<?php
// Guzzle middleware ilə retry
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\RejectedPromise;

function retryDecider(): callable
{
    return function (
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $exception = null
    ): bool {
        // Max 3 retry
        if ($retries >= 3) return false;
        
        // Connect error retry OK
        if ($exception instanceof ConnectException) return true;
        
        // 5xx retry OK
        if ($response && $response->getStatusCode() >= 500) return true;
        
        // 429 (Too Many Requests) retry — Retry-After header-i oxu
        if ($response && $response->getStatusCode() === 429) return true;
        
        // 4xx retry YOX (client error)
        return false;
    };
}

function retryDelay(): callable
{
    // Exponential backoff + jitter
    return function (int $retries, ?ResponseInterface $response = null): int {
        // Retry-After header-ə hörmət et
        if ($response && $response->hasHeader('Retry-After')) {
            return ((int) $response->getHeaderLine('Retry-After')) * 1000;  // ms
        }
        
        // 2^retries * 1000 ms + jitter (±25%)
        $base = 1000 * (2 ** $retries);      // 1s, 2s, 4s
        $jitter = random_int(-250, 250);
        return $base + $jitter;
    };
}

$stack = HandlerStack::create();
$stack->push(Middleware::retry(retryDecider(), retryDelay()));

$client = new Client([
    'handler'  => $stack,
    'base_uri' => 'https://api.example.com',
    'timeout'  => 5.0,
]);

// Idempotent request retry güvənlidir (GET, PUT, DELETE)
// POST retry — idempotency key lazım!
```

```
Retry anti-patterns:
  ❌ 400/401/403/404 retry — səhv tərəfindir, təkrar eyni cavab
  ❌ POST retry idempotency key olmadan — duplicate!
  ❌ Fixed delay (heç bir jitter yox) — thundering herd
  ❌ Unbounded retry — cascade failure
  ❌ Retry etmədən log yox — debug imkanı getdi

Best:
  ✓ Exponential backoff + jitter
  ✓ Max retry count (3-5)
  ✓ Idempotent operation-larda retry
  ✓ Retry-After header-ə hörmət
  ✓ Hər retry-da log (count, reason)
```

---

## Circuit breaker integration

```php
<?php
// Guzzle + circuit breaker (ackintosh/ganesha)
use Ackintosh\Ganesha\Builder;
use Ackintosh\Ganesha\GuzzleMiddleware;

$ganesha = Builder::build([
    'timeWindow'              => 30,     // 30s window
    'failureRateThreshold'    => 50,     // >50% fail → open
    'minimumRequests'         => 10,
    'intervalToHalfOpen'      => 5,      // half-open probe
    'adapter'                 => new Ackintosh\Ganesha\Storage\Adapter\Redis($redis),
]);

$stack = HandlerStack::create();
$stack->push(new GuzzleMiddleware($ganesha));

$client = new Client(['handler' => $stack]);

try {
    $res = $client->get('https://flaky-api.com/data');
} catch (\Ackintosh\Ganesha\Exception\RejectedException $e) {
    // Circuit OPEN — fallback
    return $cachedResponse;
}
```

---

## Concurrent requests (async)

```php
<?php
// Guzzle Promise API — paralel request
use GuzzleHttp\Promise;

$client = new Client();

// Promise (unresolved)
$promises = [
    'users'  => $client->getAsync('https://api.com/users'),
    'orders' => $client->getAsync('https://api.com/orders'),
    'stats'  => $client->getAsync('https://api.com/stats'),
];

// Hamısını gözlə (hər biri uğurlu olmalıdır — fail-fast)
$responses = Promise\Utils::unwrap($promises);

$users  = json_decode((string) $responses['users']->getBody(), true);
$orders = json_decode((string) $responses['orders']->getBody(), true);

// Settle — hər birini ayrıca, fail baş verə bilər
$results = Promise\Utils::settle($promises)->wait();
foreach ($results as $key => $result) {
    if ($result['state'] === 'fulfilled') {
        $response = $result['value'];
    } else {
        $error = $result['reason'];
    }
}
```

```php
<?php
// Pool — maksimum concurrent limit
use GuzzleHttp\Pool;

$urls = [/* 1000 URL */];

$requests = function ($urls) use ($client) {
    foreach ($urls as $url) {
        yield new \GuzzleHttp\Psr7\Request('GET', $url);
    }
};

$pool = new Pool($client, $requests($urls), [
    'concurrency' => 10,    // 10 request paralel
    'fulfilled' => function (ResponseInterface $response, int $idx) {
        echo "$idx OK\n";
    },
    'rejected' => function (\Throwable $reason, int $idx) {
        echo "$idx FAIL: {$reason->getMessage()}\n";
    },
]);

$pool->promise()->wait();
// 1000 URL, 10-ar paralel → ~100 batch
```

---

## Connection pooling

```
Connection pooling — TCP handshake cost azaldır.

Həmin host-a yeni request:
  1. DNS lookup (~10-50ms)
  2. TCP handshake (~10-50ms)  
  3. TLS handshake (~50-100ms)
  4. HTTP request-response
  Toplam: ~200-300ms

Connection reuse:
  1. HTTP request-response yalnız ~20-50ms

Guzzle default cURL-i istifadə edir — cURL handle reuse yoxdur FPM-də.
Hər request yeni connection!

Həllər:
  1. PHP-FPM ilə: yenidən istifadə çətin (process bitir).
  2. Octane/RoadRunner: worker həyatı boyu handle keepalive edir.
  3. HTTP/2: multiplexing — bir connection, çoxlu stream.
  4. Keep-alive header: server tərəfdən connection açıq saxlanır.
```

```php
<?php
// HTTP/2 multiplexing (curl 7.33+)
$client = new Client([
    'curl' => [
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_KEEPIDLE => 60,
    ],
]);

// Persistent curl handle (Octane-da kritik)
$handle = curl_share_init();
curl_share_setopt($handle, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
curl_share_setopt($handle, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
```

---

## PSR-18 HTTP Client

```php
<?php
// PSR-18 — framework-independent HTTP client interface
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class ApiClient
{
    public function __construct(
        private ClientInterface $http,            // PSR-18
        private RequestFactoryInterface $factory, // PSR-17
    ) {}
    
    public function getUser(int $id): array
    {
        $request = $this->factory
            ->createRequest('GET', "https://api.com/users/$id")
            ->withHeader('Accept', 'application/json');
        
        $response = $this->http->sendRequest($request);
        return json_decode((string) $response->getBody(), true);
    }
}

// DI container-də adapter seç:
// - Guzzle PSR-18 adapter:  php-http/guzzle7-adapter
// - Symfony HttpClient:      symfony/http-client
// - Buzz, Shuttle, etc.
```

---

## Laravel HTTP facade

```php
<?php
// Laravel 7+ Http facade — Guzzle wrapper
use Illuminate\Support\Facades\Http;

// Sadə GET
$response = Http::get('https://api.example.com/users');
$data = $response->json();

// POST + auth + timeout + retry
$response = Http::withHeaders(['X-App' => 'MyApp'])
    ->withToken($token)
    ->timeout(5)
    ->connectTimeout(2)
    ->retry(3, 100, function ($exception) {
        return $exception instanceof \Illuminate\Http\Client\ConnectionException;
    })
    ->post('https://api.example.com/orders', [
        'customer_id' => 1,
        'amount' => 100,
    ]);

if ($response->failed()) {
    $status = $response->status();
    $body   = $response->body();
}

// Async + concurrent
use Illuminate\Http\Client\Pool;

$responses = Http::pool(fn (Pool $pool) => [
    $pool->get('https://api.com/a'),
    $pool->get('https://api.com/b'),
    $pool->get('https://api.com/c'),
]);

// Base URL client
$github = Http::baseUrl('https://api.github.com')
    ->withHeaders(['Accept' => 'application/vnd.github+json']);

$repos = $github->get('/users/laravel/repos')->json();

// Macros (reusable client factory)
Http::macro('sendgrid', function () {
    return Http::baseUrl('https://api.sendgrid.com/v3')
        ->withToken(config('services.sendgrid.key'));
});

Http::sendgrid()->post('/mail/send', [...]);
```

---

## Mock & testing

```php
<?php
// Laravel
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.example.com/users/*' => Http::response(['id' => 1, 'name' => 'Ali'], 200),
    'api.example.com/orders' => Http::response([], 500),
    '*' => Http::response('not found', 404),
]);

// Test kod
$response = Http::get('https://api.example.com/users/1');
// Fake response

Http::assertSent(function ($request) {
    return $request->url() === 'https://api.example.com/users/1'
        && $request->method() === 'GET';
});

// Guzzle
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

$mock = new MockHandler([
    new Response(200, [], '{"id":1}'),
    new Response(500),
    new ConnectException('timeout', new Request('GET', 'test')),
]);

$stack = HandlerStack::create($mock);
$client = new Client(['handler' => $stack]);

// 1-ci call: 200, 2-ci: 500, 3-cü: timeout
```

---

## Best practices

```
✓ Həmişə timeout təyin et (5-10s default)
✓ 5xx və connect error-da retry, 4xx-də YOX
✓ Exponential backoff + jitter
✓ Retry-After header-i oxu
✓ Circuit breaker external API-lər üçün
✓ POST idempotency key (duplicate qorunma)
✓ Response status code YOXLA (failed() və ya status() ilə)
✓ Body parse etməzdən əvvəl JSON valid yoxla
✓ Timeout budget (nested çağırışlar üçün)
✓ Request/response log (sensitive data redact et!)
✓ Correlation ID ötür (X-Request-ID header)
✓ HTTP/2 multiplexing (HTTPS + HTTP/2 imkanı varsa)
✓ PSR-18 interface istifadə (framework decouple)
✓ Mock test-lərdə, real API dev ortamında

❌ SSL verify=false heç vaxt (MITM risk)
❌ Password, token-ləri URL-də göndərmə
❌ Unbounded concurrent pool (thundering herd)
❌ Fire-and-forget without logging
```

---

## İntervyu Sualları

- Guzzle-də `timeout` və `connect_timeout` fərqi nədir?
- Hansı HTTP status kodlarını retry edərsiniz? 4xx niyə yox?
- Exponential backoff + jitter niyə lazımdır?
- Idempotency key POST retry üçün niyə kritikdir?
- Circuit breaker HTTP client-də necə işləyir?
- Concurrent 1000 request — pool concurrency niyə lazımdır?
- PHP-FPM-də HTTP connection pooling niyə çətindir?
- HTTP/2 multiplexing PHP client-dən necə istifadə olunur?
- PSR-18 interface nəyə xidmət edir?
- Laravel `Http::fake()` testdə necə işləyir?
- `Retry-After` header-i necə hörmətlə işləyirsiniz?
- 3 servis paralel çağırılır, biri fail edir — neçə variantı var davranışın?
