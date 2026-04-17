# API Rate Limiting

## Nədir? (What is it?)

Rate limiting muəyyen zaman ərzində client-in edə biləcəyi request sayini məhdudlaşdıran mexanizmdir. Məqsəd:
- **Abuse prevention**: Brute force, scraping, DOS attack
- **Fair usage**: Bütün user-lərə eyni pay
- **Resource protection**: Server, DB overload-dan qoru
- **Cost control**: Third-party API çağırışlarini saxla

```
Rate limit olmadan:
  Bad actor: 1 saniyede 10000 request --> Server crash

Rate limit ile (100 req/min):
  User: request 1-100  --> 200 OK
  User: request 101    --> 429 Too Many Requests
```

## Necə İşləyir? (How does it work?)

### 1. Fixed Window Counter

```
Sadə: Her 1 deqiqe-de counter sıfırlanır.

Dakika 10:00-10:01  |  10:01-10:02  |  10:02-10:03
  counter=100        |  counter=0    |  counter=0
  (window full)      |  (reset)      |

Time: 10:00:59 - User 100 request etdi -> BLOCKED
Time: 10:01:00 - Window reset, +100 olur -> OK

Problem (burst problem):
  10:00:59 -> 100 request (ok, window sonu)
  10:01:00 -> 100 request (ok, yeni window)
  1 saniyede 200 request kecdi! Limit 100 idi amma.
```

### 2. Sliding Window Log

```
Her request-in timestamp-i saxlanir. Son 60 saniyede nece request olub hesablanir.

Redis sorted set ile:
  user:123:requests = [
    {timestamp: 1000, score: 1000},
    {timestamp: 1001, score: 1001},
    ...
  ]

Her request:
  1. 60 saniye öncekileri sil:  ZREMRANGEBYSCORE key 0 (now-60)
  2. Count: ZCARD key
  3. If count < limit: add current timestamp
  4. Else: 429

Memory intensive! Her request icun bir timestamp.
```

### 3. Sliding Window Counter (Approximation)

```
Fixed window + previous window-in weighted share-i.

Current window (10:01-10:02):  60 request edilib (25% keçib)
Previous window (10:00-10:01): 100 request edilib

Formula:
  effective_count = current + previous * (1 - elapsed_percent)
                  = 60 + 100 * (1 - 0.25)
                  = 60 + 75
                  = 135

If 135 > limit(100): BLOCKED
```

### 4. Token Bucket

```
Bucket-də token-lar var. Her request 1 token yeyir.
Token-lar müəyyən sürətle doldurulur.

Bucket capacity: 100 tokens
Refill rate: 10 tokens/saniye

Initial:  [##########] 100 tokens
Request arrives -> token decrements:
  [#########.] 99 tokens  -> OK
  [########..] 98 tokens  -> OK
  ...
  [..........]  0 tokens  -> 429 (block)

Refill:  her saniyede 10 token əlavə olunur (max 100).

Ustunluk: Burst traffic-e icaze verir.
  User 1 deqiqe quiet idi -> bucket full olur -> birden 100 request ata bilər.
```

### 5. Leaky Bucket

```
Fixed rate-de request-leri process edir. Bucket dolsa yeni request drop olunur.

[Requests in] --> [Bucket (queue)] --> [Process at fixed rate]

Bucket size: 100
Leak rate: 10/saniye

Requests come in burst: 100 request per saniye
Bucket fills up, amma 10/sec ile process olunur
Bucket dolsa: 429

Fərq token bucket-dən:
  Token bucket: Burst-ə icaze verir (immediate process)
  Leaky bucket: Smooth output rate (queue-ed process)
```

### 6. Distributed Rate Limiting (Redis)

```
Problem: Multiple server instances var. Hər birində counter ayri olsa limit bypass olunur.

Həll: Shared storage (Redis) istifade et.

Redis INCR with expire:
  INCR rate_limit:user_123
  If == 1: EXPIRE rate_limit:user_123 60

  If > 100: BLOCK
  Else: ALLOW

Atomic operation (Lua script):
  local current = redis.call('INCR', KEYS[1])
  if current == 1 then
    redis.call('EXPIRE', KEYS[1], 60)
  end
  if current > 100 then
    return 0
  end
  return 1
```

### 7. Rate Limit Headers

```
Response-da client-ə məlumat ver:

HTTP/1.1 200 OK
X-RateLimit-Limit: 100          # Maximum per window
X-RateLimit-Remaining: 42       # Neçə request qalib
X-RateLimit-Reset: 1634567890   # Next reset Unix timestamp

Limit keçilərsə:
HTTP/1.1 429 Too Many Requests
Retry-After: 30                 # 30 saniye sonra yenə cəhd et
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1634567920

Content-Type: application/json
{
  "error": "Too many requests",
  "retry_after": 30
}
```

## Əsas Konseptlər (Key Concepts)

### Rate Limit Identifier

```
Kimə əsasen limit? Secenek:

1. IP address
   - Pros: Anonymous users ucun
   - Cons: NAT arxasinda coxlu user, VPN bypass

2. User ID (authenticated)
   - Pros: Accurate per-user
   - Cons: Unauthenticated endpoints-də ishlemir

3. API Key
   - Pros: Per-client tracking, tier-based limits
   - Cons: API key leak

4. Device ID / Session
   - Pros: Granular
   - Cons: Easily spoofed

Practical: User authenticated-dirse user_id, yoxsa IP.
```

### Tier-Based Limits

```
Free tier:      100 req/hour
Basic tier:     1000 req/hour
Pro tier:       10000 req/hour
Enterprise:     Unlimited (SLA-based)

DB-də:
  users: id, plan, rate_limit_override

Middleware-də user-in plan-ina gore limit secilir.
```

### Different Limits per Endpoint

```
POST /login:           5 per minute    (brute force protection)
POST /register:        3 per hour      (spam prevention)
GET  /users/:id:       100 per minute  (normal usage)
POST /reports:         10 per minute   (expensive operation)
POST /upload:          5 per minute    (bandwidth)
```

### Retry-After Header

```
Server 429 qaytaranda Retry-After ver:

Retry-After: 30                          # seconds
Retry-After: Wed, 21 Oct 2025 07:28:00   # HTTP-date

Client bunu görüb exponential backoff edir:
  try 1 -> 429, wait 30s
  try 2 -> 429, wait 60s
  try 3 -> 429, wait 120s
```

### Graceful Degradation

```
Rate limit hit olanda ne etmeli?

1. Block (default) - 429 qaytar

2. Queue - request-i queue-a at, gec process et
   Pros: No lost requests
   Cons: Latency increase

3. Throttle - request-i yavashlat (add delay)
   Pros: Smooth degradation
   Cons: Client kavshaq gorunur

4. Fallback - cached response qaytar
   Pros: Functional
   Cons: Stale data
```

## PHP/Laravel ilə İstifadə

### Built-in Throttle Middleware

```php
// routes/api.php

// 60 request per minute, per IP
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

// Auth-lanmisdirsa user_id, else IP
Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Named limiter (daha flexible)
Route::middleware('throttle:api')->group(function () {
    // ...
});
```

### Custom RateLimiter (Laravel 8+)

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot()
{
    // Basit API limit
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by(
            $request->user()?->id ?: $request->ip()
        );
    });

    // Login brute force
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)
            ->by($request->ip())
            ->response(function () {
                return response()->json([
                    'error' => 'Too many login attempts.'
                ], 429);
            });
    });

    // Tier-based
    RateLimiter::for('uploads', function (Request $request) {
        $user = $request->user();

        return match($user?->plan) {
            'free' => Limit::perHour(10)->by($user->id),
            'pro' => Limit::perHour(100)->by($user->id),
            'enterprise' => Limit::none(),
            default => Limit::perHour(5)->by($request->ip()),
        };
    });

    // Multiple limits (both must pass)
    RateLimiter::for('expensive', function (Request $request) {
        return [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perDay(1000)->by($request->user()?->id),
        ];
    });
}
```

### Route-da İstifadə

```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/uploads', [UploadController::class, 'store'])
        ->middleware('throttle:uploads');
});
```

### Manual Rate Limiting (RateLimiter facade)

```php
use Illuminate\Support\Facades\RateLimiter;

public function sendOtp(Request $request)
{
    $key = 'send-otp:' . $request->ip();

    // Limit check
    if (RateLimiter::tooManyAttempts($key, 3)) {
        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'error' => "Try again in {$seconds} seconds."
        ], 429);
    }

    // Increment counter (1 dakika TTL)
    RateLimiter::increment($key, 60);

    // Send OTP logic
    $otp = rand(100000, 999999);
    // ... send SMS ...

    return response()->json(['message' => 'OTP sent']);
}

// Executing with rate limit wrapper
RateLimiter::attempt(
    $key,
    $maxAttempts = 5,
    function() {
        // logic
    },
    $decaySeconds = 60
);
```

### Custom Response Headers

```php
// app/Http/Middleware/RateLimitHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $key = 'api:' . ($request->user()?->id ?: $request->ip());
        $limit = 100;
        $remaining = $limit - RateLimiter::attempts($key);

        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', now()->addMinute()->timestamp);

        return $response;
    }
}
```

### Redis-based Distributed Rate Limiting

```php
use Illuminate\Support\Facades\Redis;

class TokenBucketLimiter
{
    public function check(string $key, int $capacity, int $refillRate): bool
    {
        $script = <<<LUA
        local tokens_key = KEYS[1]
        local timestamp_key = KEYS[2]
        local capacity = tonumber(ARGV[1])
        local refill_rate = tonumber(ARGV[2])
        local now = tonumber(ARGV[3])

        local last_tokens = tonumber(redis.call("GET", tokens_key)) or capacity
        local last_time = tonumber(redis.call("GET", timestamp_key)) or now

        local delta = math.max(0, now - last_time)
        local filled_tokens = math.min(capacity, last_tokens + (delta * refill_rate))

        local allowed = filled_tokens >= 1
        if allowed then
            filled_tokens = filled_tokens - 1
        end

        redis.call("SETEX", tokens_key, 60, filled_tokens)
        redis.call("SETEX", timestamp_key, 60, now)

        return allowed and 1 or 0
        LUA;

        $result = Redis::eval(
            $script,
            2,
            "bucket:{$key}:tokens",
            "bucket:{$key}:timestamp",
            $capacity,
            $refillRate,
            time()
        );

        return $result === 1;
    }
}

// Usage
$limiter = new TokenBucketLimiter();
if (!$limiter->check('user_' . auth()->id(), 100, 10)) {
    abort(429, 'Rate limit exceeded');
}
```

### Exception Handling (429)

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof ThrottleRequestsException) {
        return response()->json([
            'error' => 'Too many requests',
            'retry_after' => $exception->getHeaders()['Retry-After'] ?? 60,
        ], 429, $exception->getHeaders());
    }

    return parent::render($request, $exception);
}
```

### Frontend Handling

```javascript
async function apiCall(url, options = {}) {
    const response = await fetch(url, options);

    if (response.status === 429) {
        const retryAfter = response.headers.get('Retry-After') || 60;
        console.log(`Rate limited. Retrying in ${retryAfter}s`);

        await new Promise(r => setTimeout(r, retryAfter * 1000));
        return apiCall(url, options); // retry
    }

    // Show remaining quota
    const remaining = response.headers.get('X-RateLimit-Remaining');
    if (remaining && remaining < 10) {
        console.warn(`Only ${remaining} requests left!`);
    }

    return response.json();
}
```

## Interview Sualları

**Q1: Rate limiting niye vacibdir?**

Səbəblər:
1. **DOS/DDoS protection**: Malicious traffic server-i boğa bilər
2. **Brute force**: Login, password reset endpoint-lərində
3. **Resource protection**: DB, external API cost-larini qoru
4. **Fair usage**: Multi-tenant app-də bir user bashqalarini etkileyir
5. **Billing**: Tier-based pricing (Pro vs Free)

**Q2: Token bucket vs Leaky bucket fərqi?**

**Token bucket**: Bucket-də token-lar var, request gelende token yeyir. Burst-ə icaze verir (bucket doludursa 100 request immediate). Refill rate constant.

**Leaky bucket**: Request-lər queue-ya daxil olur, fixed rate-də process olunur. Output rate smooth, burst yoxdur. Queue dolsa drop.

İstifadə:
- Token bucket: API rate limiting (AWS, Stripe)
- Leaky bucket: Network packet shaping

**Q3: Fixed window counter niye problemlidir?**

Window boundary-də 2x burst mümkündür:
- 10:00:59 - 100 request (window end)
- 10:01:00 - 100 request (new window)
- 1 saniyede 200 request keçdi, amma limit 100 idi!

Həll: Sliding window (log və ya approximation).

**Q4: Distributed rate limiting necə işləyir?**

Multiple server instances varsa, hər birində ayri counter bypass problem yaradır. Həll: Shared storage (Redis).

Redis atomic operations (INCR + EXPIRE, ya da Lua script) race condition-siz counter-i increment edir. Bütün server-lər eyni counter-ə baxır.

**Q5: Per-user vs per-IP rate limiting?**

**Per-user**: Authenticated endpoints üçün. Accurate, tier-based limits dəstəkləyir. NAT/VPN problem yoxdur.

**Per-IP**: Unauthenticated endpoints (login, register). NAT arxasinda çoxlu user ola bilər - innocent user bloklana bilər. VPN ile bypass olar.

Praktikada: auth olsa user, yoxsa IP.

**Q6: 429-un "Retry-After" header-i necə istifade olunur?**

Server 429 qaytaranda Retry-After header client-ə nə vaxt yenidən cəhd etmək olacağını deyir:
```
Retry-After: 30          (30 saniye)
Retry-After: Wed, 21 Oct 2025 07:28:00   (specific time)
```

Client library-lər bunu oxuyur və exponential backoff-la retry edir. Manual retry olmadan, server-in hesabladığı optimum zamanı gözləyir.

**Q7: Rate limit-in bypass üsulları və protection?**

**Bypass**:
1. IP rotation (proxy, VPN)
2. Multiple API keys
3. Distributed bot network
4. User-Agent spoofing

**Protection**:
1. Device fingerprinting (browser fingerprint)
2. Captcha ikinci layer
3. Behavior analysis (ML-based anomaly)
4. Tight rate limits for new accounts
5. IP reputation services (Cloudflare)

**Q8: Login endpoint üçün necə rate limit qurmaq lazim?**

```php
RateLimiter::for('login', function (Request $request) {
    // Həm IP həm username üzrə limit
    return [
        Limit::perMinute(5)->by($request->ip()),
        Limit::perMinute(5)->by($request->input('email').$request->ip()),
    ];
});
```

Bele: Bir IP 5 faili attempt edə bilər, eyni zamanda bir email 5 attempt. Lock account after N failures.

**Q9: Rate limit-in cache storage seçimi?**

**Memory (APCu, array)**: Single server, lost on restart
**File**: Persistence, amma slow, race condition
**Database**: Persistent, amma slow
**Memcached**: Fast, distributed, no persistence
**Redis (ən çox)**: Fast, persistent, atomic ops, TTL support

Laravel default-ta cache driver istifade edir (`CACHE_DRIVER` config).

**Q10: API user-ə rate limit məlumatini necə çatdırmaq lazim?**

Response header-lərlə:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 73
X-RateLimit-Reset: 1634567890
```

429 response-da:
```
Retry-After: 30
X-RateLimit-Reset: 1634567920
```

Documentation-da:
- Endpoint-lər üzrə limit-lər
- Tier-based limitler
- Headers-in description-i
- Retry best practices

## Best Practices

1. **Hər endpoint üçün uygun limit**:
   - Login: 5/min
   - API: 60-1000/min
   - Heavy ops: 10/min
   - Unauthenticated: stricter

2. **Tier-based limits**: Free, Basic, Pro, Enterprise plan-lari.

3. **Response header-lər mandatory**: X-RateLimit-* və Retry-After her zaman.

4. **Graceful degradation**: Full block yerine degrade (cached response, queue).

5. **Logging**: Rate limit hit-ləri logla - attack pattern detection üçün.

6. **Alerting**: Anomaly detection - sudden spike-lərdə alert.

7. **Whitelist**: Internal IP-lər, trusted partner-lər rate limit-dən exempt.

8. **Gradual rollout**: Yeni limit tətbiq etməzdən əvvəl monitor mode-da test et.

9. **Documentation**: API docs-da hər endpoint üçün limit aydın yaz.

10. **Redis production-da**: Memory cache yalniz dev üçün - production Redis/Memcached.

11. **Distributed-safe**: Single-instance assumption-dan qaç - multi-server hazir ol.

12. **Client library-lər retry logic**: Automatic exponential backoff, jitter.

13. **Token bucket for burst-friendly APIs**: User experience yaxşılaşdırır.

14. **Captcha fallback**: Rate limit hit-də captcha göstər (CAPTCHA + rate limit combo).

15. **Cost analysis**: Rate limit Redis usage-ini monitor et - expensive ola bilər.
