# API Rate Limiting (Middle)

## İcmal

Rate limiting müəyyən zaman ərzində client-in edə biləcəyi request sayını məhdudlaşdıran mexanizmdir. Məqsəd:
- **Abuse prevention**: Brute force, scraping, DoS attack
- **Fair usage**: Bütün user-lərə eyni pay
- **Resource protection**: Server, DB overload-dan qoru
- **Cost control**: Third-party API çağırışlarını saxla

```
Rate limit olmadan:
  Bad actor: 1 saniyədə 10000 request --> Server crash

Rate limit ilə (100 req/min):
  User: request 1-100  --> 200 OK
  User: request 101    --> 429 Too Many Requests
```

## Niyə Vacibdir

Rate limiting olmayan API login endpoint-i brute force hücumuna açıqdır. Bir bot 1 saniyədə minlərlə şifrə cəhdi edə bilər. Bundan əlavə, tərəfindəniz API-ni istifadə edən pis niyyətli bir müştəri serverinizi digər müştərilər üçün əlçatmaz edə bilər. Rate limiting həm təhlükəsizliyi, həm fair usage-i, həm də tier-based pricing modelini dəstəkləyir.

## Əsas Anlayışlar

### 1. Fixed Window Counter

```
Sadə: Hər 1 dəqiqədə counter sıfırlanır.

Dəqiqə 10:00-10:01  |  10:01-10:02  |  10:02-10:03
  counter=100        |  counter=0    |  counter=0
  (window full)      |  (reset)      |

Problem (burst problem):
  10:00:59 -> 100 request (ok, window sonu)
  10:01:00 -> 100 request (ok, yeni window)
  1 saniyədə 200 request keçdi! Limit 100 idi amma.
```

### 2. Sliding Window Log

```
Hər request-in timestamp-i saxlanır. Son 60 saniyədə neçə request olub hesablanır.

Redis sorted set ilə:
  user:123:requests = [
    {timestamp: 1000, score: 1000},
    {timestamp: 1001, score: 1001},
    ...
  ]

Hər request:
  1. 60 saniyə öncəkiləri sil:  ZREMRANGEBYSCORE key 0 (now-60)
  2. Count: ZCARD key
  3. If count < limit: add current timestamp
  4. Else: 429

Memory intensive! Hər request üçün bir timestamp.
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
Bucket-də token-lar var. Hər request 1 token yeyir.
Token-lar müəyyən sürətlə doldurulur.

Bucket capacity: 100 tokens
Refill rate: 10 tokens/saniyə

Initial:  [##########] 100 tokens
Request arrives -> token decrements:
  [#########.] 99 tokens  -> OK
  [########..] 98 tokens  -> OK
  ...
  [..........]  0 tokens  -> 429 (block)

Refill:  hər saniyədə 10 token əlavə olunur (max 100).

Üstünlük: Burst traffic-ə icazə verir.
  User 1 dəqiqə quiet idi -> bucket full olur -> birdən 100 request ata bilər.
```

### 5. Leaky Bucket

```
Fixed rate-də request-ləri process edir. Bucket dolsa yeni request drop olunur.

[Requests in] --> [Bucket (queue)] --> [Process at fixed rate]

Bucket size: 100
Leak rate: 10/saniyə

Requests come in burst: 100 request per saniyə
Bucket dolur, amma 10/sec ilə process olunur
Bucket dolsa: 429

Fərq token bucket-dən:
  Token bucket: Burst-ə icazə verir (immediate process)
  Leaky bucket: Smooth output rate (queue-ed process)
```

### 6. Distributed Rate Limiting (Redis)

```
Problem: Multiple server instances var. Hər birində counter ayrı olsa limit bypass olunur.

Həll: Shared storage (Redis) istifadə et.

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
X-RateLimit-Remaining: 42       # Neçə request qalıb
X-RateLimit-Reset: 1634567890   # Next reset Unix timestamp

Limit keçilərsə:
HTTP/1.1 429 Too Many Requests
Retry-After: 30                 # 30 saniyə sonra yenə cəhd et
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1634567920
```

### Rate Limit Identifier

```
Kimə əsasən limit?

1. IP address
   - Pros: Anonymous users üçün
   - Cons: NAT arxasında çoxlu user, VPN bypass

2. User ID (authenticated)
   - Pros: Accurate per-user
   - Cons: Unauthenticated endpoint-lərdə işləmir

3. API Key
   - Pros: Per-client tracking, tier-based limits
   - Cons: API key leak

Praktik: User authenticated-dirsə user_id, yoxsa IP.
```

### Tier-Based Limits

```
Free tier:      100 req/hour
Basic tier:     1000 req/hour
Pro tier:       10000 req/hour
Enterprise:     Unlimited (SLA-based)

Middleware-də user-in plan-ına görə limit seçilir.
```

## Praktik Baxış

**Trade-off-lar:**
- Token bucket burst-ə icazə verir (API-lər üçün yaxşı), leaky bucket output-u smooth edir (network shaping üçün)
- Per-IP limit NAT arxasında çoxlu istifadəçini bloklaya bilər
- Redis distributed rate limiting — əlavə dependency, amma multi-server üçün mütləq lazım

**Nə vaxt istifadə edilməməlidir:**
- Internal service-to-service sorğularda rate limiting tez-tez lazımsız overhead yaradır
- Whitelist ilə trusted partner-ləri limit-dən azad edin

**Anti-pattern-lər:**
- `Retry-After` header-i olmadan 429 qaytarmaq — client nə vaxt yenidən cəhd edəcəyini bilmir
- In-memory counter multi-server mühitdə — hər server ayrı counter sayır, limit bypass olunur
- Bütün endpoint-lərə eyni limit — login üçün çox, GET /products üçün az
- Rate limit hit-lərini log etməməmk — attack pattern-ləri görünmür

## Nümunələr

### Ümumi Nümunə

Laravel-in built-in `throttle` middleware-i Redis-ə əsaslanır. `RateLimiter::for()` ilə custom, tier-based limit-lər təyin etmək mümkündür. Production-da mütləq Redis driver istifadə olunmalıdır.

### Kod Nümunəsi

**Built-in Throttle Middleware:**

```php
// routes/api.php

// 60 request per minute, per IP
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

// Auth-lanmışdırsa user_id, yoxsa IP
Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Named limiter
Route::middleware('throttle:api')->group(function () {
    // ...
});
```

**Custom RateLimiter (Laravel 8+):**

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot()
{
    // Sadə API limit
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
            'free'       => Limit::perHour(10)->by($user->id),
            'pro'        => Limit::perHour(100)->by($user->id),
            'enterprise' => Limit::none(),
            default      => Limit::perHour(5)->by($request->ip()),
        };
    });

    // Multiple limits (hər ikisi pass etməli)
    RateLimiter::for('expensive', function (Request $request) {
        return [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perDay(1000)->by($request->user()?->id),
        ];
    });
}
```

**Route-da İstifadə:**

```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/uploads', [UploadController::class, 'store'])
        ->middleware('throttle:uploads');
});
```

**Manual Rate Limiting (RateLimiter facade):**

```php
use Illuminate\Support\Facades\RateLimiter;

public function sendOtp(Request $request)
{
    $key = 'send-otp:' . $request->ip();

    if (RateLimiter::tooManyAttempts($key, 3)) {
        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'error' => "Try again in {$seconds} seconds."
        ], 429);
    }

    RateLimiter::increment($key, 60); // 1 dəqiqə TTL

    // Send OTP logic
    $otp = rand(100000, 999999);
    // ... send SMS ...

    return response()->json(['message' => 'OTP sent']);
}
```

**Custom Response Headers:**

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $key       = 'api:' . ($request->user()?->id ?: $request->ip());
        $limit     = 100;
        $remaining = $limit - RateLimiter::attempts($key);

        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', now()->addMinute()->timestamp);

        return $response;
    }
}
```

**Redis-based Token Bucket:**

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

// İstifadə
$limiter = new TokenBucketLimiter();
if (!$limiter->check('user_' . auth()->id(), 100, 10)) {
    abort(429, 'Rate limit exceeded');
}
```

**429 Exception Handling:**

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof ThrottleRequestsException) {
        return response()->json([
            'error'       => 'Too many requests',
            'retry_after' => $exception->getHeaders()['Retry-After'] ?? 60,
        ], 429, $exception->getHeaders());
    }

    return parent::render($request, $exception);
}
```

**Frontend Retry Logic:**

```javascript
async function apiCall(url, options = {}) {
    const response = await fetch(url, options);

    if (response.status === 429) {
        const retryAfter = response.headers.get('Retry-After') || 60;
        console.log(`Rate limited. Retrying in ${retryAfter}s`);

        await new Promise(r => setTimeout(r, retryAfter * 1000));
        return apiCall(url, options); // retry
    }

    const remaining = response.headers.get('X-RateLimit-Remaining');
    if (remaining && remaining < 10) {
        console.warn(`Only ${remaining} requests left!`);
    }

    return response.json();
}
```

## Praktik Tapşırıqlar

1. **Login brute force qoruması:** `POST /login` endpoint-inə `throttle:login` middleware əlavə edin — hər IP üçün 5 cəhd/dəqiqə. 6-cı cəhddə `429 Too Many Requests` + `Retry-After: 60` header-lərini yoxlayın.

2. **Tier-based limits:** `user.plan` (free/pro/enterprise) sütununa görə müxtəlif rate limit qurun. Free üçün 10/saat, Pro üçün 100/saat, Enterprise üçün limitsiz. Hər plan üçün test keçirin.

3. **Rate limit headers:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` header-lərini bütün response-lara əlavə edin. Postman-da header-lərin düzgün azaldığını izləyin.

4. **Redis distributed testing:** Docker Compose ilə 2 PHP-FPM instance qaldırın, Redis shared cache istifadə edin. Hər iki instance-ə sorğu göndərib rate limit-in düzgün paylaşıldığını yoxlayın (in-memory cache ilə sınayın — fərqi görün).

5. **Token bucket implement:** `TokenBucketLimiter` class-ını Redis Lua script ilə implement edin. Burst allowance-ı test edin: 10 saniyə gözləyin (bucket dolur), sonra 100 request biranda göndərin — hamısı keçməlidir.

## Əlaqəli Mövzular

- [API Security](17-api-security.md)
- [API Gateway](21-api-gateway.md)
- [Network Security](26-network-security.md)
- [CDN](20-cdn.md)
- [JWT](15-jwt.md)
