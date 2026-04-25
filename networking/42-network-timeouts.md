# Network Timeouts & Connection Management (Middle)

## İcmal

Hər network çağırışı uğursuz ola bilər — server cavab verməyə bilər, şəbəkə kəsilə bilər, servis yavaşlaya bilər. **Timeout, retry, və connection pooling** bu reallığı idarə etmək üçün əsas mexanizmlərdir. Bunları düzgün konfiqurasiya etməmək production-da cascading failure-lara səbəb olur.

## Niyə Vacibdir

- Timeout olmayan API çağırışı → thread/worker əbədi gözləyir → exhaustion → downtime
- Retry olmayan network çağırışı → bir anlıq xəta → user error
- Connection pool olmayan DB → hər request yeni connection → yavaşlıq, connection limit
- Yanlış timeout dəyərləri → ya çox tez fail edir, ya da çox gec

## Əsas Anlayışlar

### Timeout Növləri

```
Connection Timeout: TCP handshake bitənə qədər gözlə
  → Server mövcud deyilsə, yaxud firewall block edirsə trigger olur
  → Adətən 2-5 saniyə

Read Timeout (Response Timeout): Connection qurulduqdan sonra cavab gözlə
  → Server yavaş cavab verirsə trigger olur
  → Endpointə görə dəyişir: 30s (normal), 120s (file upload), 300s (report)

Write Timeout: Request göndərilənə qədər gözlə
  → Böyük payload, yavaş upload zamanı
  → Adətən read timeout-dan az

Total/Overall Timeout: Bütün əməliyyat üçün hard limit
  → Retry-larla birlikdə hesablanır
```

### Retry Strategiyaları

```
Fixed retry:
  attempt 1 → fail → 1s gözlə → attempt 2 → fail → 1s gözlə → attempt 3

Exponential backoff:
  attempt 1 → fail → 1s → attempt 2 → fail → 2s → attempt 3 → fail → 4s

Exponential backoff + jitter (tövsiyə edilən):
  attempt 1 → fail → 1s ± 0.3s → attempt 2 → fail → 2s ± 0.6s → ...
  Jitter: bir anda çoxlu client retry etməsin (thundering herd)

Max attempts: 3-5 (daha çox nadir hallarda)
Retry yalnız: idempotent request-lər üçün (GET, PUT) və ya explicitly safe olan POST-lar
```

### Hansı Xətalarda Retry Etmək

```
Retry et:
  ✓ Connection timeout (server tam başlamayıb)
  ✓ 503 Service Unavailable (müvəqqəti)
  ✓ 429 Too Many Requests (Retry-After header-a bax)
  ✓ Network reset/connection refused (müvəqqəti)

Retry etmə:
  ✗ 400 Bad Request (data problem, retry kömək etməz)
  ✗ 401/403 Unauthorized (credentials problem)
  ✗ 404 Not Found (resource yoxdur)
  ✗ 422 Unprocessable (validation error)
  ✗ 500 Internal Server Error (idempotent deyilsə — data duplicate riski)
```

### Connection Pooling

```
Poolsuz (hər request):
  Request → TCP connect (3-way handshake) → TLS handshake → Query → Disconnect
  Overhead: ~50-200ms, DB connection limit sürətlə dolar

Connection Pool:
  App start → N connection yarat → Pool-da saxla
  Request → Pool-dan al → Query → Pool-a qaytar (disconnect yox!)
  Overhead: ~0-1ms (handshake yoxdur)

Pool parameters:
  min_connections: Həmişə hazır olan (5-10)
  max_connections: Maximum limit (25-100, DB server-ə görə)
  connection_timeout: Pool-da connection gözlə (2-5s)
  idle_timeout: İstifadəsiz connection-u bağla (10min)
  max_lifetime: Connection-u yenilə (30min-1saat) — connection rot üçün
```

## Praktik Baxış

### Laravel Database Connection Pool

```php
// config/database.php
'mysql' => [
    'driver'         => 'mysql',
    'host'           => env('DB_HOST', '127.0.0.1'),
    'options'        => [
        PDO::ATTR_TIMEOUT => 5,  // Connection timeout
    ],
    // Laravel default-da PDO persistent connections istifadə edir
    // FPM-in hər worker-i öz connection-unu saxlayır
    // max_connections = fpm_workers × db_connections_per_worker
],
```

### Laravel HTTP Client (Timeouts)

```php
use Illuminate\Support\Facades\Http;

// Əsas timeout konfiqurasiyası
$response = Http::timeout(30)           // Read timeout: 30s
    ->connectTimeout(5)                  // Connection timeout: 5s
    ->retry(
        times: 3,                        // Max 3 cəhd
        sleepMilliseconds: 1000,         // 1s gözlə
        when: function ($exception, $request) {
            // Yalnız connection xətalarında retry et
            return $exception instanceof \Illuminate\Http\Client\ConnectionException;
        },
        throw: false                     // Exception atmaq əvəzinə null qaytar
    )
    ->get('https://api.example.com/users');

// Exponential backoff üçün:
Http::retry(3, function (int $attempt) {
    return $attempt * 1000;  // 1s, 2s, 3s
})->get('...');
```

### Guzzle Manual Konfiqurasiya

```php
$client = new \GuzzleHttp\Client([
    'timeout'         => 30,    // Read timeout
    'connect_timeout' => 5,     // Connection timeout
    'http_errors'     => false, // 4xx/5xx exception atmır, response qaytar
]);

// Middleware ilə retry
$handlerStack = \GuzzleHttp\HandlerStack::create();
$handlerStack->push(\GuzzleHttp\Middleware::retry(
    function ($retries, $request, $response, $exception) {
        if ($retries >= 3) return false;
        if ($exception instanceof \GuzzleHttp\Exception\ConnectException) return true;
        if ($response && $response->getStatusCode() === 503) return true;
        return false;
    },
    function ($retries) {
        return (int) pow(2, $retries) * 1000;  // Exponential: 1s, 2s, 4s
    }
));
```

### Redis Timeout

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'default' => [
        'host'         => env('REDIS_HOST', '127.0.0.1'),
        'port'         => env('REDIS_PORT', '6379'),
        'timeout'      => 2.5,     // Connect timeout (saniyə)
        'read_timeout' => 5,       // Read timeout
        'retry_interval' => 100,   // Reconnect interval (ms)
        'persistent'   => true,    // Connection pool kimi davran
    ],
],
```

### Trade-offs

```
Timeout çox qısa:
  + Sürətli fail, user gözləmir
  - Bəzən normal yavaş cavablar da fail olur (false positive)

Timeout çox uzun:
  + Heç bir normal request fail olmur
  - Worker-lər bloklanır, sistem yavaşlayır

Retry çox:
  + Müvəqqəti xətalar özü həll olur
  - Yük artır, idempotency problemləri

Retry az:
  + Minimal yük
  - Müvəqqəti xətalarda user xəta görür

Connection pool çox böyük:
  + Heç bir request gözləmir
  - DB max_connections keçilir, hamısı fail edir

Connection pool çox kiçik:
  + DB-yə az yük
  - Peak traffic-da request-lər gözləyir
```

### Common Mistakes

```
❌ Timeout qoymamaq (əbədi gözləmə)
❌ Non-idempotent POST-ları retry etmək (iki dəfə ödəniş)
❌ Bütün xətalarda retry (400-ı retry etmək mənasızdır)
❌ Connection pool-u tam doldurmaq (max_connections headroom saxla)
❌ Timeout-u hardcode etmək (ENV-dən alın, servisə görə fərqli)
❌ Retry zamanı jitter istifadə etməmək (thundering herd)
```

## Nümunələr

### Praktik Timeout Matrix

```
Servis          Connect  Read    Retry  Strategi
──────────────────────────────────────────────────
Internal API    2s       10s     3x     Exponential+jitter
External API    5s       30s     2x     Linear
Payment API     5s       60s     0x     Manual retry (idempotency key)
AI/LLM API      5s       120s    1x     Bir cəhd (baha)
DB query        3s       30s     0x     Application-level
File upload     5s       300s    0x     Upload ID ilə resume
Health check    1s       2s      0x     Fast fail
```

### Queue Job Timeout

```php
class ProcessPaymentJob implements ShouldQueue
{
    public $timeout = 60;        // Job 60s-dən çox işləyə bilməz
    public $tries = 3;           // 3 cəhd
    public $backoff = [10, 30];  // 10s, 30s gözlə

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(5);  // 5 dəq içində tamamlanmalı
    }
}
```

## Praktik Tapşırıqlar

1. **Timeout test:** `curl --max-time 5 --connect-timeout 2 https://httpbin.org/delay/10` — nə baş verir?
2. **Pool yoxla:** `SHOW STATUS LIKE 'Threads_connected'` (MySQL) — neçə aktiv connection?
3. **Retry log:** Guzzle middleware ilə hər retry-ı log et, production-da neqadar olur?
4. **Timeout calculate:** Əgər endpoint 95th percentile 800ms cavab verirsə, timeout nə qoymalısan?

## Əlaqəli Mövzular

- [TCP - Transmission Control Protocol](03-tcp.md)
- [HTTP Protocol](05-http-protocol.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
- [Load Balancing](18-load-balancing.md)
