# API Rate Limiting Dizaynı (Middle)

## Problem Təsviri

Public və ya partner-facing API yaratdığınız zaman, bu API-ni abuse-dan (sui-istifadədən) qorumalısınız:

- **DDoS hücumları** — minlərlə sorğu göndərilir
- **Brute force** — login endpoint-inə sonsuz cəhd
- **Scraping** — data-nızı toplu şəkildə çəkən bot-lar
- **Unintentional overload** — client-in buggy kodu sonsuz loop-da sorğu göndərir
- **Fair usage** — bir client bütün server resurslarını istifadə edir, digərləri gözləyir

Rate limiting olmadan, bir nəfər bütün sistemi çökdürə bilər.

### Problem niyə yaranır?

Developer API-ni yazanda yalnız normal istifadəni nəzərə alır. Lakin real dünyada: (1) müştərinin buggy kodu sonsuz loop-da sorğu göndərir, (2) rəqib data scraping edir, (3) bot brute force hücumu edir, (4) viral post zamanı birdəfəlik trafik spike olur. Rate limiting olmadan bu hallarda server CPU/memory tükənir, legitimate user-lər təsirlənir.

```
Normallıq:  User A → 10 req/dəq → OK
            User B → 15 req/dəq → OK

Abuse:      User C → 10,000 req/dəq → Server çökür → User A və B də təsirlənir!
```

---

## Rate Limiting Alqoritmləri

### 1. Fixed Window Counter

Ən sadə yanaşma. Zaman pəncərəsinə (məsələn, 1 dəqiqə) sorğu sayını hesablayır.

```
Dəqiqə 1 (00:00-01:00): [|||||||||||] 11/10 → 11-ci sorğu rədd!
Dəqiqə 2 (01:00-02:00): [|||]           3/10 → OK
```

**Problem:** "Boundary" problemi. 00:59-da 10 sorğu + 01:01-də 10 sorğu = 2 saniyə ərzində 20 sorğu.

*Bu kod Redis INCR ilə sabit zaman pəncərəsini sayan ən sadə rate limiter implementasiyasını göstərir:*

```php
// app/Services/RateLimiter/FixedWindowRateLimiter.php
namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

class FixedWindowRateLimiter
{
    /**
     * Fixed window rate limiter.
     *
     * @param string $key      Unikal identifikator (user_id, ip, api_key)
     * @param int    $maxRequests  Pəncərədə icazə verilən max sorğu
     * @param int    $windowSeconds  Pəncərə müddəti (saniyə)
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public function attempt(string $key, int $maxRequests, int $windowSeconds): array
    {
        $windowKey = $key . ':' . floor(time() / $windowSeconds);

        // Atomik əməliyyat — INCR + EXPIRE
        $current = Redis::incr($windowKey);

        // İlk sorğudursa, TTL təyin et
        if ($current === 1) {
            Redis::expire($windowKey, $windowSeconds);
        }

        $remaining = max(0, $maxRequests - $current);
        $resetAt = (floor(time() / $windowSeconds) + 1) * $windowSeconds;

        return [
            'allowed' => $current <= $maxRequests,
            'remaining' => $remaining,
            'reset_at' => (int) $resetAt,
            'limit' => $maxRequests,
        ];
    }
}
```

### 2. Sliding Window Log

Hər sorğunun timestamp-ini saxlayır. Daha dəqiqdir, amma daha çox memory istifadə edir.

*Bu kod hər sorğunun timestamp-ini Redis Sorted Set-də saxlayan daha dəqiq sliding window rate limiter-i göstərir:*

```php
// app/Services/RateLimiter/SlidingWindowLogRateLimiter.php
namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

class SlidingWindowLogRateLimiter
{
    public function attempt(string $key, int $maxRequests, int $windowSeconds): array
    {
        $now = microtime(true);
        $windowStart = $now - $windowSeconds;

        // Redis Sorted Set istifadə edirik
        // Score = timestamp, Member = unikal ID
        $redisKey = "rate_limit:sliding:{$key}";

        // Lua script — atomik əməliyyat
        $script = <<<'LUA'
            local key = KEYS[1]
            local now = tonumber(ARGV[1])
            local window_start = tonumber(ARGV[2])
            local max_requests = tonumber(ARGV[3])
            local ttl = tonumber(ARGV[4])
            local member = ARGV[5]

            -- Köhnə qeydləri sil
            redis.call('ZREMRANGEBYSCORE', key, '-inf', window_start)

            -- Cari sayı hesabla
            local current = redis.call('ZCARD', key)

            if current < max_requests then
                -- Yeni sorğunu əlavə et
                redis.call('ZADD', key, now, member)
                redis.call('EXPIRE', key, ttl)
                return {1, max_requests - current - 1}
            else
                return {0, 0}
            end
        LUA;

        $result = Redis::eval(
            $script,
            1, // key sayı
            $redisKey,
            $now,
            $windowStart,
            $maxRequests,
            $windowSeconds,
            $now . ':' . mt_rand() // unikal member
        );

        return [
            'allowed' => (bool) $result[0],
            'remaining' => (int) $result[1],
            'reset_at' => (int) ceil($now + $windowSeconds),
            'limit' => $maxRequests,
        ];
    }
}
```

### 3. Token Bucket Alqoritmi

Bir "vedrə" var, içində token-lər var. Hər sorğu 1 token istifadə edir. Token-lər müəyyən sürətlə yenilənir. Vedrə boşdursa — sorğu rədd edilir.

```
Vedrə tutumu: 10 token
Yeniləmə sürəti: 1 token/saniyə

Vaxt 0: [●●●●●●●●●●] 10/10
User 3 sorğu göndərir...
Vaxt 0: [●●●●●●●○○○] 7/10
1 saniyə keçir (+1 token)...
Vaxt 1: [●●●●●●●●○○] 8/10
```

**Üstünlük:** Burst traffic-ə icazə verir (vedrə doluysa), amma uzun müddətdə ortalama sürəti məhdudlaşdırır.

*Bu kod Lua script ilə atomik şəkildə token dolduran və istehlak edən token bucket rate limiter-i göstərir:*

```php
// app/Services/RateLimiter/TokenBucketRateLimiter.php
namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

class TokenBucketRateLimiter
{
    /**
     * Token Bucket rate limiter — Redis Lua script ilə atomik.
     *
     * @param string $key          Unikal identifikator
     * @param int    $bucketSize   Vedrə tutumu (max burst)
     * @param float  $refillRate   Token əlavə sürəti (token/saniyə)
     * @param int    $tokensNeeded Sorğu üçün lazım olan token sayı
     */
    public function attempt(
        string $key,
        int $bucketSize,
        float $refillRate,
        int $tokensNeeded = 1
    ): array {
        $redisKey = "rate_limit:token_bucket:{$key}";
        $now = microtime(true);

        // Lua script — atomik token bucket
        $script = <<<'LUA'
            local key = KEYS[1]
            local bucket_size = tonumber(ARGV[1])
            local refill_rate = tonumber(ARGV[2])
            local now = tonumber(ARGV[3])
            local tokens_needed = tonumber(ARGV[4])

            -- Mövcud vəziyyəti oxu
            local data = redis.call('HMGET', key, 'tokens', 'last_refill')
            local tokens = tonumber(data[1])
            local last_refill = tonumber(data[2])

            -- İlk dəfədirsə, vedrəni doldur
            if tokens == nil then
                tokens = bucket_size
                last_refill = now
            end

            -- Keçən müddətə görə token əlavə et
            local elapsed = now - last_refill
            local new_tokens = elapsed * refill_rate
            tokens = math.min(bucket_size, tokens + new_tokens)

            -- Token varmı?
            local allowed = 0
            if tokens >= tokens_needed then
                tokens = tokens - tokens_needed
                allowed = 1
            end

            -- Yenilə
            redis.call('HMSET', key, 'tokens', tokens, 'last_refill', now)
            redis.call('EXPIRE', key, math.ceil(bucket_size / refill_rate) * 2)

            return {allowed, math.floor(tokens)}
        LUA;

        $result = Redis::eval(
            $script,
            1,
            $redisKey,
            $bucketSize,
            $refillRate,
            $now,
            $tokensNeeded
        );

        return [
            'allowed' => (bool) $result[0],
            'remaining' => (int) $result[1],
            'limit' => $bucketSize,
        ];
    }
}
```

### 4. Leaky Bucket Alqoritmi

Token Bucket-ə bənzəyir, amma sorğular sabit sürətlə "sızır" (işlənir). Burst traffic yığılır, amma sabit sürətlə işlənir.

*Bu kod sabit sürətlə sorğuları buraxan leaky bucket rate limiter-i Redis Lua script ilə göstərir:*

```php
// app/Services/RateLimiter/LeakyBucketRateLimiter.php
namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

class LeakyBucketRateLimiter
{
    /**
     * Leaky Bucket — sorğuları queue kimi saxlayır, sabit sürətlə buraxır.
     *
     * @param string $key
     * @param int    $bucketSize  Maksimum queue uzunluğu
     * @param float  $leakRate    Saniyədə neçə sorğu buraxılır
     */
    public function attempt(string $key, int $bucketSize, float $leakRate): array
    {
        $redisKey = "rate_limit:leaky_bucket:{$key}";
        $now = microtime(true);

        $script = <<<'LUA'
            local key = KEYS[1]
            local bucket_size = tonumber(ARGV[1])
            local leak_rate = tonumber(ARGV[2])
            local now = tonumber(ARGV[3])

            local data = redis.call('HMGET', key, 'water', 'last_leak')
            local water = tonumber(data[1]) or 0
            local last_leak = tonumber(data[2]) or now

            -- Keçən müddətdə sızan su miqdarı
            local elapsed = now - last_leak
            local leaked = elapsed * leak_rate
            water = math.max(0, water - leaked)

            local allowed = 0
            if water < bucket_size then
                water = water + 1
                allowed = 1
            end

            redis.call('HMSET', key, 'water', water, 'last_leak', now)
            redis.call('EXPIRE', key, math.ceil(bucket_size / leak_rate) * 2)

            return {allowed, math.floor(bucket_size - water)}
        LUA;

        $result = Redis::eval($script, 1, $redisKey, $bucketSize, $leakRate, $now);

        return [
            'allowed' => (bool) $result[0],
            'remaining' => (int) $result[1],
            'limit' => $bucketSize,
        ];
    }
}
```

---

## Laravel Rate Limiting — Built-in Həllər

### ThrottleRequests Middleware

Laravel default olaraq `ThrottleRequests` middleware təmin edir.

*Bu kod Laravel-in built-in throttle middleware-ini route-lara tətbiq etməyi göstərir:*

```php
// routes/api.php — Laravel 11
use Illuminate\Support\Facades\Route;

// Sadə istifadə: dəqiqədə 60 sorğu
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
});

// Named rate limiter
Route::middleware('throttle:api')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

### Custom Rate Limiter Definition

*Bu kod user tipi, API planı və endpoint-ə görə fərqli limitlər təyin edən custom rate limiter-ləri göstərir:*

```php
// app/Providers/AppServiceProvider.php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // 1. Default API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // 2. Authenticated user-lər üçün daha yüksək limit
        RateLimiter::for('authenticated-api', function (Request $request) {
            if ($request->user()) {
                // Premium user-lər üçün daha yüksək limit
                $limit = $request->user()->isPremium() ? 1000 : 300;
                return Limit::perMinute($limit)->by($request->user()->id);
            }

            // Autentifikasiya olunmamış — IP-yə görə
            return Limit::perMinute(30)->by($request->ip());
        });

        // 3. Login endpoint üçün ciddi limit (brute force qoruma)
        RateLimiter::for('login', function (Request $request) {
            return [
                // IP başına dəqiqədə 5 cəhd
                Limit::perMinute(5)->by($request->ip()),
                // Email başına dəqiqədə 3 cəhd
                Limit::perMinute(3)->by($request->input('email')),
            ];
        });

        // 4. API Key əsaslı rate limiting
        RateLimiter::for('api-key', function (Request $request) {
            $apiKey = $request->header('X-API-Key');

            if (!$apiKey) {
                return Limit::perMinute(10)->by($request->ip());
            }

            // API key-ə görə fərqli limitlər
            $plan = $this->getPlanByApiKey($apiKey);

            return match ($plan) {
                'free' => Limit::perMinute(100)->by($apiKey),
                'basic' => Limit::perMinute(500)->by($apiKey),
                'pro' => Limit::perMinute(2000)->by($apiKey),
                'enterprise' => Limit::perMinute(10000)->by($apiKey),
                default => Limit::perMinute(10)->by($request->ip()),
            };
        });

        // 5. Endpoint-ə görə fərqli limit
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => 'Upload limiti keçildi. Saatda maksimum 20 fayl upload edə bilərsiniz.',
                        'retry_after' => $headers['Retry-After'] ?? null,
                    ], 429);
                });
        });
    }

    private function getPlanByApiKey(string $apiKey): string
    {
        // Cache ilə plan yoxlaması
        return cache()->remember(
            "api_key_plan:{$apiKey}",
            3600,
            fn () => \App\Models\ApiKey::where('key', $apiKey)->value('plan') ?? 'unknown'
        );
    }
}
```

---

## Custom Rate Limiter Middleware

Laravel-in built-in throttle middleware-i kifayət etmədikdə, custom middleware yaza bilərsiniz.

*Bu kod token bucket alqoritmi ilə rate limit yoxlayan və response header-ləri əlavə edən custom middleware-i göstərir:*

```php
// app/Http/Middleware/AdvancedRateLimitMiddleware.php
namespace App\Http\Middleware;

use App\Services\RateLimiter\TokenBucketRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdvancedRateLimitMiddleware
{
    public function __construct(
        private TokenBucketRateLimiter $rateLimiter
    ) {}

    /**
     * Rate limit yoxlaması — response header-ləri ilə.
     */
    public function handle(Request $request, Closure $next, string $tier = 'default'): Response
    {
        $config = $this->getTierConfig($tier);
        $identifier = $this->getIdentifier($request);

        $result = $this->rateLimiter->attempt(
            key: "{$tier}:{$identifier}",
            bucketSize: $config['bucket_size'],
            refillRate: $config['refill_rate']
        );

        // Rate limit header-ləri — hər response-a əlavə et
        $headers = [
            'X-RateLimit-Limit' => $config['bucket_size'],
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Policy' => $tier,
        ];

        if (!$result['allowed']) {
            // 429 Too Many Requests
            $retryAfter = (int) ceil(1 / $config['refill_rate']);

            return response()->json([
                'error' => 'Rate limit keçildi. Zəhmət olmasa bir az gözləyin.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders(array_merge($headers, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Remaining' => 0,
            ]));
        }

        // Sorğunu burax və header-ləri əlavə et
        $response = $next($request);

        foreach ($headers as $headerName => $headerValue) {
            $response->headers->set($headerName, (string) $headerValue);
        }

        return $response;
    }

    /**
     * Tier konfiqurasiyası.
     */
    private function getTierConfig(string $tier): array
    {
        return match ($tier) {
            'free' => [
                'bucket_size' => 100,
                'refill_rate' => 1.67,    // ~100/dəqiqə
            ],
            'basic' => [
                'bucket_size' => 500,
                'refill_rate' => 8.33,    // ~500/dəqiqə
            ],
            'pro' => [
                'bucket_size' => 2000,
                'refill_rate' => 33.33,   // ~2000/dəqiqə
            ],
            'enterprise' => [
                'bucket_size' => 10000,
                'refill_rate' => 166.67,  // ~10000/dəqiqə
            ],
            default => [
                'bucket_size' => 60,
                'refill_rate' => 1.0,     // ~60/dəqiqə
            ],
        };
    }

    /**
     * Sorğu edəni tanımaq — user_id, api_key, və ya IP.
     */
    private function getIdentifier(Request $request): string
    {
        // 1. API Key varsa
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return 'key:' . $apiKey;
        }

        // 2. Authenticated user varsa
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        // 3. IP address
        return 'ip:' . $request->ip();
    }
}
```

### Route-larda istifadəsi

*Bu kod tier əsaslı advanced rate limit middleware-ini route qruplarına tətbiq etməyi göstərir:*

```php
// routes/api.php
use App\Http\Middleware\AdvancedRateLimitMiddleware;

// Tier əsaslı rate limiting
Route::middleware([AdvancedRateLimitMiddleware::class . ':free'])->group(function () {
    Route::get('/public/data', [PublicController::class, 'index']);
});

Route::middleware(['auth:sanctum', AdvancedRateLimitMiddleware::class . ':pro'])->group(function () {
    Route::get('/data', [DataController::class, 'index']);
    Route::post('/data', [DataController::class, 'store']);
});
```

---

## Distributed Rate Limiting

Çoxlu server olduqda, hər serverin öz counter-i olsa, real limit N * limit olar. Redis mərkəzi counter kimi istifadə olunur.

*Bu kod çoxlu server üçün Redis-də mərkəzi counter saxlayan distributed sliding window rate limiter-i göstərir:*

```php
// app/Services/RateLimiter/DistributedSlidingWindowRateLimiter.php
namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

class DistributedSlidingWindowRateLimiter
{
    /**
     * Sliding Window Counter — fixed window-un dəqiqliyini artırır.
     *
     * Əvvəlki pəncərədəki sorğuların çəkili ortalamsı ilə cari pəncərəni birləşdirir.
     */
    public function attempt(string $key, int $maxRequests, int $windowSeconds): array
    {
        $now = time();
        $currentWindow = floor($now / $windowSeconds) * $windowSeconds;
        $previousWindow = $currentWindow - $windowSeconds;

        // Pəncərə daxilində keçən vaxtın nisbəti (0.0 - 1.0)
        $elapsed = ($now - $currentWindow) / $windowSeconds;

        $currentKey = "rate:{$key}:{$currentWindow}";
        $previousKey = "rate:{$key}:{$previousWindow}";

        // Atomik əməliyyat — Lua script
        $script = <<<'LUA'
            local current_key = KEYS[1]
            local previous_key = KEYS[2]
            local max_requests = tonumber(ARGV[1])
            local window_seconds = tonumber(ARGV[2])
            local elapsed_ratio = tonumber(ARGV[3])

            local previous_count = tonumber(redis.call('GET', previous_key) or '0')
            local current_count = tonumber(redis.call('GET', current_key) or '0')

            -- Sliding window hesabı
            local estimated = previous_count * (1 - elapsed_ratio) + current_count

            if estimated >= max_requests then
                return {0, 0, current_count}
            end

            -- Sorğunu say
            local new_count = redis.call('INCR', current_key)
            redis.call('EXPIRE', current_key, window_seconds * 2)

            local remaining = math.max(0, max_requests - (previous_count * (1 - elapsed_ratio) + new_count))
            return {1, math.floor(remaining), new_count}
        LUA;

        $result = Redis::eval(
            $script,
            2,
            $currentKey,
            $previousKey,
            $maxRequests,
            $windowSeconds,
            $elapsed
        );

        return [
            'allowed' => (bool) $result[0],
            'remaining' => (int) $result[1],
            'limit' => $maxRequests,
            'reset_at' => (int) ($currentWindow + $windowSeconds),
        ];
    }
}
```

---

## Rate Limit Headers

Standart rate limit header-ləri client-lərə öz sorğularını idarə etməyə kömək edir.

```
HTTP/1.1 200 OK
X-RateLimit-Limit: 100          ← Pəncərədəki max sorğu sayı
X-RateLimit-Remaining: 73       ← Qalan sorğu sayı
X-RateLimit-Reset: 1704067260   ← Pəncərə sıfırlama vaxtı (Unix timestamp)

HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1704067260
Retry-After: 45                 ← Neçə saniyə sonra yenidən cəhd etmək olar
```

### Global Response Header Middleware

*Global Response Header Middleware üçün kod nümunəsi:*
```php
// app/Http/Middleware/RateLimitHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitHeaders
{
    /**
     * Laravel throttle middleware-indən sonra əlavə header-lər əlavə edir.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Laravel throttle header-lərini standart formata çevirmək
        if ($response->headers->has('X-RateLimit-Limit')) {
            // Artıq var — dəyişmə
            return $response;
        }

        // Retry-After varsa, 429 cavabdır
        if ($response->headers->has('Retry-After')) {
            $retryAfter = $response->headers->get('Retry-After');
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', (string) (time() + (int) $retryAfter));
        }

        return $response;
    }
}
```

---

## API Key Management Model

*API Key Management Model üçün kod nümunəsi:*
```php
// app/Models/ApiKey.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'plan',
        'rate_limit',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key', // API key-i response-larda gizlə
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->key = $model->key ?? self::generateKey();
        });
    }

    public static function generateKey(): string
    {
        return 'sk_' . Str::random(48);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * İstifadə olunma vaxtını yeniləmək.
     */
    public function touchUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
```

---

## Graceful Degradation

Rate limit olduqda, 429 qaytarmaq əvəzinə, degraded response qaytarmaq mümkündür.

*Rate limit olduqda, 429 qaytarmaq əvəzinə, degraded response qaytarmaq üçün kod nümunəsi:*
```php
// app/Http/Middleware/GracefulRateLimitMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class GracefulRateLimitMiddleware
{
    /**
     * Rate limit keçildikdə, 429 əvəzinə cached/degraded cavab qaytarır.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $identifier = $request->user()?->id ?? $request->ip();
        $key = "rate_limit:counter:{$identifier}";
        $limit = 100;
        $window = 60;

        $current = (int) Cache::get($key, 0);

        if ($current >= $limit) {
            // Rate limit keçilib — amma 429 əvəzinə degraded cavab qaytarırıq
            return $this->degradedResponse($request);
        }

        // Counter artır
        Cache::increment($key);
        if ($current === 0) {
            Cache::put($key, 1, $window);
        }

        $response = $next($request);

        // Header əlavə et
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $limit - $current - 1));

        return $response;
    }

    /**
     * Degraded response — cached və ya sadələşdirilmiş data.
     */
    private function degradedResponse(Request $request): Response
    {
        // Endpoint-ə görə cached cavab qaytarır
        $cacheKey = 'degraded_response:' . md5($request->fullUrl());
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return response()->json(array_merge($cached, [
                '_degraded' => true,
                '_message' => 'Sorğu limiti keçildi. Cached data qaytarılır.',
            ]), 200, [
                'X-RateLimit-Remaining' => '0',
                'X-Degraded-Response' => 'true',
            ]);
        }

        // Cache yoxdursa, 429 qaytarmalıyıq
        return response()->json([
            'error' => 'Çox sayda sorğu göndərdiniz. Zəhmət olmasa gözləyin.',
            'retry_after' => 60,
        ], 429, [
            'Retry-After' => '60',
        ]);
    }
}
```

---

## Rate Limit Monitoring

*Rate Limit Monitoring üçün kod nümunəsi:*
```php
// app/Listeners/LogRateLimitHit.php
namespace App\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class LogRateLimitHit
{
    /**
     * Rate limit hadisələrini izləyir — monitoring və alerting üçün.
     */
    public function handleRateLimitExceeded(string $identifier, string $tier): void
    {
        // Redis counter — monitoring üçün
        $key = "metrics:rate_limit_exceeded:" . date('Y-m-d-H');
        Redis::hincrby($key, $tier, 1);
        Redis::expire($key, 86400); // 24 saat saxla

        Log::warning('Rate limit exceeded', [
            'identifier' => $identifier,
            'tier' => $tier,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

---

## Alqoritm Müqayisəsi

| Alqoritm | Dəqiqlik | Memory | Burst | Mürəkkəblik | İstifadə Halı |
|----------|---------|--------|-------|-------------|----------------|
| Fixed Window | Aşağı | Aşağı | Pəncərə sərhədində problem | Sadə | Sadə limitlər |
| Sliding Window Log | Yüksək | Yüksək | Yaxşı | Orta | Dəqiq limitlər |
| Sliding Window Counter | Yaxşı | Aşağı | Yaxşı | Orta | Əksər hallarda ən yaxşı |
| Token Bucket | Yaxşı | Aşağı | Burst-a icazə verir | Orta | API Gateway |
| Leaky Bucket | Yaxşı | Aşağı | Sabit sürət | Orta | Sabit throughput lazım olanda |

---

## Interview Sualları və Cavablar

**S: Fixed Window alqoritminin boundary burst problemi nədir?**
C: Pəncərə 00:00-01:00 və 01:00-02:00 kimi bölünür. İstifadəçi 00:59-da 100 sorğu, 01:01-də yenə 100 sorğu göndərə bilər — 2 saniyə ərzində 200 sorğu keçir, amma limit 100-dür. Sliding Window Counter bu problemi həll edir: cari pəncərəni əvvəlki pəncərənin çəkili ortalama ilə birləşdirir.

**S: Token Bucket ilə Leaky Bucket-in fərqi nədir?**
C: Token Bucket burst traffic-ə icazə verir — vedrə doluysa eyni anda çox sorğu keçə bilər (məs. 10 token varsa 10 request eyni anda). Leaky Bucket isə sabit sürətlə "sızır" — burst-u queue-ya alır, amma eyni sürətlə işləyir. API Gateway-lərdə Token Bucket daha populyardır, çünki bursts-lara icazə verir. Downstream service-ləri qorumaq üçün Leaky Bucket daha uyğundur.

**S: Rate limiting üçün niyə Redis INCR + Lua script istifadə edirik, sadə INCR kifayət deyilmi?**
C: Sadə INCR race condition yaradır: iki request eyni anda counter-i 99 görür, hər ikisi INCR edir, hər ikisi 100-ə çatır, hər ikisi keçir. Lua script bütün əməliyyatları (ZREMRANGEBYSCORE, ZCARD, ZADD) atomik edir — Redis single-threaded olduğu üçün script icra olunarkən başqa command işlənmir.

**S: Distributed sistemdə (çoxlu server) rate limiting necə işləyir?**
C: Hər serverin öz in-memory counter-i olsa, N server × limit = faktiki limit artır. Həll: Redis mərkəzi counter kimi istifadə — bütün server-lər eyni Redis key-inə yazır. Buna distributed rate limiting deyilir. Redlock algorithm ilə daha güclü consistency mümkündür.

**S: User brute force hücumunu rate limiting ilə necə qoruyursunuz?**
C: Login endpoint-inə çoxlu layer: (1) IP başına dəqiqədə 5 cəhd, (2) email/username başına dəqiqədə 3 cəhd, (3) account lock — 10 uğursuz cəhddən sonra 15 dəqiqəlik kilidlə. İki layer mühümdür çünki botlar proxy-dən fərqli IP-lər istifadə edə bilər — email-based limit bunu tutur.

**S: 429 qaytarmaq əvəzinə degraded response nə zaman mantıqlıdır?**
C: Read-only, caching mümkün olan endpoint-lərdə: məhsul siyahısı, axtarış nəticələri. Cache-dəki köhnə data 429-dan daha yaxşı UX verir. Mutation endpoint-lərində (ödəniş, sifariş) degraded response düzgün deyil — user-ə açıq mesaj ver: "Həddindən artıq sorğu göndərdiniz, Xn sonra cəhd edin."

**S: API plan-larında fərqli rate limit necə idarə edilir?**
C: API key-ə bağlı plan məlumatı DB-də saxlanılır, cache-lənir (Redis, 1 saatlik TTL). Hər sorğuda plan yoxlanılır, limiti ona uyğun tətbiq olunur. Enterprise müştərilər üçün custom limit mümkündür. `RateLimiter::for('api', fn($req) => Limit::perMinute($req->user()->planLimit()))`.

---

## Interview-da Bu Sualı Necə Cavablandırmaq

1. **Niyə lazımdır** — abuse, fair usage, DDoS qoruma
2. **Alqoritmləri bilin** — ən azı Token Bucket və Sliding Window izah edə bilməlisiniz
3. **Redis-in rolu** — distributed rate limiting üçün central counter
4. **Lua script** — niyə atomik əməliyyat lazımdır (race condition)
5. **Header-lər** — `X-RateLimit-*` və `Retry-After` header-ləri
6. **Tier-based limiting** — fərqli plan-larda fərqli limitlər
7. **Graceful degradation** — 429 qaytarmaq yerinə nə etmək olar
8. **Laravel built-in** — `RateLimiter::for()` və `throttle` middleware

---

## Anti-patterns

**1. Fixed Window-un boundary burst problemi**
59-cu saniyədə 100 request, 61-ci saniyədə yenə 100 request — 2 saniyədə 200 request keçir. Sliding Window Counter bu problemi həll edir.

**2. Hər middleware-i rate limit etmək**
Health check, static assets, internal API endpoint-lərini rate limit etmək — legitim trafiki bloklamaq. Yalnız user-facing, mutation endpoint-ləri limitlə.

**3. IP-based limiting tək başına**
NAT arxasında 100 user eyni IP-dən gəlir — hamısı birlikdə bloklanır. User ID + IP kombinasiyası daha ədalətlidir.

**4. `Retry-After` header-ini qaytarmamaq**
429 qaytar, amma client nə vaxt retry edəcəyini bilmir — exponential backoff yazmaq zorunda qalır. `Retry-After` header-i həmişə olmalıdır.

**5. Non-atomic counter**
Race condition: iki request eyni anda sayacı 99 görür, hər ikisi 100-ə çatdırır, hər ikisi keçir. Redis INCR + Lua script atomic olmalıdır.

**6. Rate limit state-ini DB-də saxlamaq**
Hər request-də DB query — 100ms latency əlavə olunur. Rate limit state həmişə Redis-də (in-memory) saxlanmalıdır.
