# Rate Limiting (Junior)

## İcmal

Rate limiting müəyyən vaxt ərzində client-in göndərə biləcəyi request sayını məhdudlaşdırır.
API abuse, DDoS hücumları və resource exhaustion-dan qorunmaq üçün istifadə olunur.

```
Client: 100 request/dəqiqə limit
  Request 1-100:  200 OK
  Request 101:    429 Too Many Requests
                  Retry-After: 30
```


## Niyə Vacibdir

Nəzarətsiz API trafiqi bir istifadəçinin bütün resursları tükətməsinə imkan verir. DDoS həmləsindən qorunmaq, SLA-nı qorumaq, billing düzgünlüyü — rate limiting bunların hamısı üçün kritikdir. Token bucket vs sliding window seçimi burst trafikinə münasibəti müəyyən edir.

## Əsas Anlayışlar

### Rate Limiting Alqoritmləri

**1. Token Bucket**
Bucket-ə sabit sürətlə token əlavə olunur. Hər request bir token istifadə edir.
Bucket boşdursa request reject olunur.

```
Bucket capacity: 10 tokens
Refill rate: 1 token/saniyə

t=0:  bucket=10, request -> bucket=9  (OK)
t=0:  bucket=9,  request -> bucket=8  (OK)
...
t=0:  bucket=1,  request -> bucket=0  (OK)
t=0:  bucket=0,  request -> REJECTED  (429)
t=1:  bucket=1,  request -> bucket=0  (OK, 1 token refill oldu)
```

Üstünlük: Burst traffic-ə icazə verir (bucket dolana qədər)
İstifadə: AWS API Gateway, Stripe API

**2. Leaky Bucket**
Request-lər bucket-ə daxil olur, sabit sürətlə emal olunur. Bucket dolduqda reject.

```
Bucket size: 10
Leak rate: 1 request/saniyə

Request-lər bucket-ə düşür, sabit sürətlə "sızır" (emal olunur).
Bucket dolduqda yeni request-lər reject olunur.
```

Üstünlük: Output rate sabit (smooth traffic)
Mənfi: Burst traffic buffer olunur və ya itirilir

**3. Fixed Window Counter**
Sabit vaxt pəncərəsi (1 dəqiqə) ərzində counter artırılır. Limit keçdikdə reject.

```
Window: 1 dəqiqə, Limit: 100

12:00:00 - 12:00:59 -> counter: 0...100 (OK)
12:00:45 -> counter: 101 (REJECTED)
12:01:00 -> counter reset: 0 (yeni window)
```

Mənfi: Window boundary-də burst problem. 12:00:50-da 100 + 12:01:00-da 100 =
2 saniyə ərzində 200 request (limit 2x aşılır).

**4. Sliding Window Log**
Hər request-in timestamp-ını saxlayır. Window daxilindəki request-ləri sayır.

```
Window: 1 dəqiqə, Limit: 100
Timestamps: [12:00:01, 12:00:05, 12:00:10, ...]

Yeni request (12:00:45):
  - 12:00:45-dən 1 dəqiqə geri bax
  - [11:59:45 - 12:00:45] arasındakı request-ləri say
  - Count < 100 -> ALLOW, timestamp əlavə et
  - Count >= 100 -> REJECT
```

Üstünlük: Dəqiq, boundary problem yoxdur
Mənfi: Yaddaş çox istifadə edir (hər timestamp saxlanılır)

**5. Sliding Window Counter**
Fixed window + sliding window-un hibrid yanaşması. Əvvəlki window-un çəkili ortalamasını hesablayır.

```
Window: 1 dəqiqə, Limit: 100
Previous window (12:00): 80 requests
Current window (12:01): 30 requests
Current position: 12:01:15 (25% of current window)

Estimated count = 80 * (1 - 0.25) + 30 = 60 + 30 = 90
90 < 100 -> ALLOW
```

Üstünlük: Yaddaş effektiv, dəqiqə yaxın
Ən geniş yayılmış: Cloudflare, Redis-based implementations

### Distributed Rate Limiting

Bir neçə server arasında rate limit shared olmalıdır:

```
Server A: user123 -> 50 requests
Server B: user123 -> 50 requests
Total: 100 requests (limit 100-dür, amma aşılıb!)

Həll: Redis centralized counter
  Server A -> Redis INCR user123:rate -> 51
  Server B -> Redis INCR user123:rate -> 52
  Redis atomic əməliyyat ilə dəqiq count
```

### Rate Limit Headers

```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1640000000

HTTP/1.1 429 Too Many Requests
Retry-After: 30
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1640000000
```

## Arxitektura

### Redis-Based Rate Limiter

```
[Client] -> [API Gateway/LB] -> [Rate Limiter] -> [Application]
                                      |
                                   [Redis]
                                 (centralized counter)

Flow:
1. Request gəlir, client identifier (IP, API key, user ID) çıxarılır
2. Redis-dən current count oxunur
3. Limit-dən azdırsa: count artır, request forward et
4. Limit-dən çoxdursa: 429 qaytarır
```

### Multi-Tier Rate Limiting

```
Tier 1: IP-based (Cloudflare/Nginx)     -> 1000 req/min per IP
Tier 2: API Key (API Gateway)           -> 500 req/min per key
Tier 3: User-based (Application)        -> 100 req/min per user
Tier 4: Endpoint-based (Controller)     -> 10 req/min for /api/export

Hər tier fərqli limitlər tətbiq edir.
```

### Redis Lua Script (Atomic Sliding Window)

```lua
-- sliding_window_rate_limit.lua
local key = KEYS[1]
local window = tonumber(ARGV[1])  -- window size in seconds
local limit = tonumber(ARGV[2])   -- max requests
local now = tonumber(ARGV[3])     -- current timestamp

-- Köhnə entry-ləri sil
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)

-- Current count
local count = redis.call('ZCARD', key)

if count < limit then
    -- Allow: timestamp əlavə et
    redis.call('ZADD', key, now, now .. '-' .. math.random(1000000))
    redis.call('EXPIRE', key, window)
    return {1, limit - count - 1}  -- allowed, remaining
else
    return {0, 0}  -- rejected, 0 remaining
end
```

## Nümunələr

### Laravel Built-in Rate Limiting

```php
// app/Providers/RouteServiceProvider.php (Laravel 10)
// app/Providers/AppServiceProvider.php (Laravel 11)

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Per-user rate limit
    RateLimiter::for('api', function ($request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Tiered rate limits
    RateLimiter::for('api-tiered', function ($request) {
        $user = $request->user();

        if (!$user) {
            return Limit::perMinute(10)->by($request->ip());
        }

        return match ($user->plan) {
            'enterprise' => Limit::none(),
            'pro' => Limit::perMinute(1000)->by($user->id),
            'basic' => Limit::perMinute(100)->by($user->id),
            default => Limit::perMinute(30)->by($user->id),
        };
    });

    // Multiple limits
    RateLimiter::for('uploads', function ($request) {
        return [
            Limit::perMinute(10)->by($request->user()->id),     // 10/dəqiqə
            Limit::perDay(100)->by($request->user()->id),       // 100/gün
        ];
    });

    // Custom response
    RateLimiter::for('login', function ($request) {
        return Limit::perMinute(5)
            ->by($request->ip())
            ->response(function ($request, $headers) {
                return response()->json([
                    'error' => 'Too many login attempts. Please try again later.',
                    'retry_after' => $headers['Retry-After'],
                ], 429, $headers);
            });
    });
}
```

### Route Middleware

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});

Route::middleware('throttle:uploads')->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
});

Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Inline throttle
Route::middleware('throttle:10,1')->group(function () {
    // 10 requests per 1 minute
    Route::post('/export', [ExportController::class, 'create']);
});
```

### Custom Rate Limiter

```php
// app/Services/SlidingWindowRateLimiter.php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SlidingWindowRateLimiter
{
    public function attempt(string $key, int $limit, int $windowSeconds): array
    {
        $now = microtime(true);
        $windowStart = $now - $windowSeconds;

        $result = Redis::eval(
            $this->luaScript(),
            1,
            "rate_limit:{$key}",
            $windowSeconds,
            $limit,
            $now
        );

        return [
            'allowed' => (bool) $result[0],
            'remaining' => $result[1],
            'limit' => $limit,
            'reset' => (int) ceil($now) + $windowSeconds,
        ];
    }

    private function luaScript(): string
    {
        return <<<'LUA'
        local key = KEYS[1]
        local window = tonumber(ARGV[1])
        local limit = tonumber(ARGV[2])
        local now = tonumber(ARGV[3])

        redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
        local count = redis.call('ZCARD', key)

        if count < limit then
            redis.call('ZADD', key, now, now .. '-' .. math.random(1000000))
            redis.call('EXPIRE', key, window)
            return {1, limit - count - 1}
        else
            return {0, 0}
        end
        LUA;
    }
}

// app/Http/Middleware/CustomRateLimit.php
namespace App\Http\Middleware;

use App\Services\SlidingWindowRateLimiter;
use Closure;

class CustomRateLimit
{
    public function __construct(private SlidingWindowRateLimiter $limiter) {}

    public function handle($request, Closure $next, int $limit = 60, int $window = 60)
    {
        $key = $request->user()?->id ?? $request->ip();
        $result = $this->limiter->attempt($key, $limit, $window);

        if (!$result['allowed']) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => $result['reset'] - time(),
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $result['limit'],
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $result['reset'],
                'Retry-After' => $result['reset'] - time(),
            ]);
        }

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $result['limit'],
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Reset' => $result['reset'],
        ]);
    }
}
```

## Real-World Nümunələr

**GitHub API:** 5,000 requests/saat authenticated, 60/saat unauthenticated.
Token bucket alqoritmi. X-RateLimit headers ilə bildiriş.

**Stripe API:** 100 read requests/saniyə, 100 write requests/saniyə per API key.
429 response Retry-After header ilə. Exponential backoff tövsiyə olunur.

**Twitter/X API:** Tier-based. Free: 1,500 tweets/read per month.
Basic: $100/mo, 10,000 tweets/read per month. Endpoint-based limits.

**Cloudflare:** Edge-level rate limiting. Milyonlarla IP üçün real-time.
JavaScript challenge, CAPTCHA, block kimi fərqli aksiyalar.

## Praktik Tapşırıqlar

**S: Token bucket vs leaky bucket fərqi?**
C: Token bucket burst traffic-ə icazə verir (bucket capacity qədər), leaky bucket
output-u sabit saxlayır. Token bucket API rate limiting üçün yaxşıdır (burst-lar normal),
leaky bucket network traffic shaping üçün (sabit rate lazım).

**S: Distributed rate limiting necə həll olunur?**
C: Redis kimi centralized store istifadə olunur. Atomic INCR əməliyyatı ilə counter
artırılır. Lua script ilə check+increment atomic edilir. Race condition olmasın deyə
Redis single-threaded olması kömək edir.

**S: Rate limit aşıldıqda nə qaytarılmalıdır?**
C: HTTP 429 Too Many Requests. Headers: Retry-After (neçə saniyə gözləmək),
X-RateLimit-Limit (total limit), X-RateLimit-Remaining (qalan),
X-RateLimit-Reset (reset timestamp). Body-də error mesajı.

**S: Sliding window niyə fixed window-dan yaxşıdır?**
C: Fixed window-da boundary problem var - iki window-un qovşağında limit 2x aşıla bilər.
Sliding window hər an son N saniyədəki request-ləri sayır, boundary problem yoxdur.

## Praktik Baxış

1. **Response headers göndərin** - Client limit, remaining, reset bilməlidir
2. **Retry-After header** - Client-ə nə qədər gözləməyi bildirin
3. **Graceful degradation** - Redis down olsa rate limiting-i disable edin, app işləsin
4. **Multiple dimensions** - IP, user, API key, endpoint üzrə ayrı limitlər
5. **Whitelist** - Internal service-lər, monitoring üçün rate limit tətbiq etməyin
6. **Logging** - Rate limit hit-ləri log edin, abuse pattern-ləri analiz edin
7. **Dynamic limits** - Plan-based, time-based (peak hours) dəyişən limitlər
8. **Documentation** - API docs-da rate limit-ləri açıq yazın


## Əlaqəli Mövzular

- [API Gateway](02-api-gateway.md) — rate limiting mərkəzləşdirməsi
- [Load Balancing](01-load-balancing.md) — upstream limit
- [Backpressure & Load Shedding](57-backpressure-load-shedding.md) — sistematik yük idarəsi
- [Auth](14-authentication-authorization.md) — kim nə qədər sorğu göndərə bilər
- [Webhook Delivery](82-webhook-delivery-system.md) — outbound rate limit
