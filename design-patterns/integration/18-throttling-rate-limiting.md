# Throttling / Rate Limiting (Middle ⭐⭐)

## İcmal

**Rate Limiting** — client-ə müəyyən vaxt ərzində göndərə biləcəyi sorğu sayını limitləmək (API qorunması). **Throttling** — sistemin öz daxili resurslarını qorumaq üçün işlənmə sürətini məhdudlaşdırmaq (server-side). İkisi tez-tez eyni mənada işlənir, lakin fərqli perspektivdir: rate limiting dışarıdan gələni, throttling içəridəki işlənməni məhdudlaşdırır.

## Niyə Vacibdir

Açıq API-lər olmadan: bir client bot ilə dəqiqədə 10,000 sorğu göndərir, server CPU 100%-ə çatır, bütün digər istifadəçilər cavab ala bilmir. Rate limiting ilə: həmin client 429 alır, digərləri normal işləyir. Queue throttling olmadan: 10,000 email job eyni anda işlənməyə çalışır, DB bağlantıları tükənir. Throttling bu yükü zamanla paylaşır.

## Əsas Anlayışlar

- **Fixed Window**: 1 dəqiqəlik pəncərədə N sorğu; pəncərə sonunda sayaç sıfırlanır; burst problem (pəncərə bitən/başlayan anda 2x sorğu mümkündür)
- **Sliding Window**: hər sorğuda son N dəqiqənin sorğularını say; daha düzgün, amma daha çox memory
- **Token Bucket**: bucket-da N token var; hər sorğu bir token yeyir; bucket müntəzəm doldurulur; burst icazəsi mümkündür
- **Leaky Bucket**: sorğular sabit sürətlə işlənir; bucket dolu olduqda yeni sorğu drop edilir; smooth output
- **Per-process vs distributed**: PHP FPM multi-process — in-memory rate limiter hər process üçün ayrıca sayır; distributed rate limiting üçün Redis lazımdır

## Praktik Baxış

- **Real istifadə**: public API (per-user, per-IP, per-API-key limitləri), login (brute force), form submission (spam), queue throttling (email blast), payment processing (günlük limit)
- **Trade-off-lar**: sistem qorunur; lakin false positive (legitimate user limitə çatır); sliding window Redis memory tələb edir; rate limit response user-friendly olmalıdır (429 + `Retry-After` header)
- **İstifadə etməmək**: internal service-to-service çağrıları üçün (trust boundary daxilindəki); ya da çox aşağı limitlər qoyaraq legitimate trafiki kəsmək
- **Common mistakes**: per-process rate limiting (FPM-də işləmir, Redis lazımdır); rate limit olduğunda user-friendly cavab yoxdur; `Retry-After` header göndərməmək

## Anti-Pattern Nə Zaman Olur?

**Per-process rate limiting — FPM multi-process-də işləmir:**
PHP FPM hər request üçün ayrı process açır. In-memory `$counter++` hər process-də müstəqildir — 10 FPM worker varsa effektiv limit 10x artır. Redis-based distributed rate limiting mütləq lazımdır: `INCR + EXPIRE` atomic əməliyyatı.

**Rate limit-in çox aşağı qoyulması:**
`limit: 10/saat` — normal istifadəçi mobil app-da bir neçə dəqiqə ərzindəki hərəkəti ilə limiti keçir. Rate limit production traffic-ini analiz edib təyin edin; "normal user"-in P95 sorğu sayından 3-5x yuxarı olsun.

**Rate limit response-un user-friendly olmaması:**
429 status kodu + `{"error": "too many requests"}` — client nə zaman yenidən cəhd etməli bilmir. Mütləq `Retry-After` header göndərin: `Retry-After: 60` (60 saniyə sonra cəhd et). API klientləri bu header-ı oxuyub avtomatik gözlə bilir.

**Queue throttling olmadan email blast:**
100,000 email job bir anda queue-ya düşür — worker-lər şişir, email provider rate limit verir, DB bağlantıları tükənir. Queue throttling ilə saniyədə 10 email gönder — sistem sabit qalır, email provider xoşbəxt olur.

## Nümunələr

### Ümumi Nümunə

```
Token Bucket:

bucket: [🪙 🪙 🪙 🪙 🪙]  (max=5)
refill: hər saniyə +1 token

Sorğu gəlir → token götür
Bucket boşdursa → reject (429)

3 saniyədə sonra bucket = [🪙 🪙 🪙]
→ burst: 3 sorğu ani olaraq qəbul edilir
```

### PHP/Laravel Nümunəsi

```php
<?php

// Laravel RateLimiter — RouteServiceProvider-də
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// AppServiceProvider ya da RouteServiceProvider-də
RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
    return $request->user()
        ? Limit::perMinute(60)->by($request->user()->id)   // Auth user: 60/dəq
        : Limit::perMinute(20)->by($request->ip());         // Anonim: 20/dəq
});

// API key-based limit
RateLimiter::for('partner-api', function (\Illuminate\Http\Request $request) {
    $plan = $request->user()?->plan ?? 'free';

    return match ($plan) {
        'enterprise' => Limit::perMinute(1000)->by($request->user()->id),
        'pro'        => Limit::perMinute(100)->by($request->user()->id),
        default      => Limit::perMinute(10)->by($request->user()->id),  // free
    };
});

// Route-da istifadə
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

Route::middleware(['auth:partner-key', 'throttle:partner-api'])->group(function () {
    Route::get('/partner/orders', [PartnerOrderController::class, 'index']);
});
```

```php
<?php

// Redis-based sliding window rate limiter
// WHY: FPM multi-process — in-memory limiter hər process üçün ayrıdır
// Redis ilə bütün process-lər paylaşılan sayaca sahibdir

class RedisRateLimiter
{
    public function __construct(
        private \Illuminate\Redis\Connections\Connection $redis,
    ) {}

    /**
     * Sliding window rate limit — INCR + EXPIRE ilə
     * @return bool — true: sorğu qəbul edildi; false: limit keçildi
     */
    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $current = $this->redis->incr($key);

        if ($current === 1) {
            // İlk sorğu — TTL set et
            $this->redis->expire($key, $windowSeconds);
        }

        return $current <= $limit;
    }

    /**
     * Sorted set ilə daha dəqiq sliding window
     */
    public function allowSlidingWindow(string $key, int $limit, int $windowSeconds): bool
    {
        $now    = microtime(true);
        $window = $now - $windowSeconds;

        // Köhnə sorğuları sil
        $this->redis->zremrangebyscore($key, '-inf', $window);

        // Mövcud sorğu sayı
        $count = $this->redis->zcard($key);

        if ($count >= $limit) {
            return false;
        }

        // Yeni sorğu əlavə et
        $this->redis->zadd($key, $now, uniqid('', true));
        $this->redis->expire($key, $windowSeconds + 1);

        return true;
    }

    /**
     * Neçə saniyə sonra yenidən cəhd etmək lazımdır
     */
    public function retryAfter(string $key, int $windowSeconds): int
    {
        $ttl = $this->redis->ttl($key);
        return max(0, $ttl);
    }
}

// Middleware olaraq istifadə
class ApiRateLimitMiddleware
{
    public function __construct(private RedisRateLimiter $limiter) {}

    public function handle(\Illuminate\Http\Request $request, \Closure $next): mixed
    {
        $userId = $request->user()?->id ?? $request->ip();
        $key    = "rate_limit:api:{$userId}";

        if (!$this->limiter->allow($key, limit: 60, windowSeconds: 60)) {
            $retryAfter = $this->limiter->retryAfter($key, 60);

            return response()->json([
                'error'   => 'Too Many Requests',
                'message' => "Rate limit keçildi. {$retryAfter} saniyə sonra yenidən cəhd edin.",
            ], 429, [
                'Retry-After'           => $retryAfter,
                'X-RateLimit-Limit'     => 60,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset'     => now()->addSeconds($retryAfter)->timestamp,
            ]);
        }

        $response = $next($request);

        // Header-lara rate limit məlumatını əlavə et
        $remaining = max(0, 60 - (int) \Redis::get($key));
        $response->headers->set('X-RateLimit-Limit', 60);
        $response->headers->set('X-RateLimit-Remaining', $remaining);

        return $response;
    }
}
```

```php
<?php

// Laravel Queue Throttling — email blast
// Saniyədə 10 emaildən çox göndərməmək üçün

// config/queue.php — throttled connection
// 'throttled_email' => [
//     'driver' => 'redis',
//     'queue'  => 'emails',
//     'retry_after' => 90,
// ],

class SendWelcomeEmailJob implements ShouldQueue
{
    public function handle(MailService $mail): void
    {
        $mail->sendWelcome($this->user);
    }
}

// dispatch() zamanı — delay ilə throttle
class EmailBlastService
{
    public function sendToAll(array $users): void
    {
        // Saniyədə 10 email = hər 100ms-dən bir job
        $delayMs = 0;

        foreach ($users as $user) {
            dispatch(new SendWelcomeEmailJob($user))
                ->onQueue('emails')
                ->delay(now()->addMilliseconds($delayMs));

            $delayMs += 100;  // 100ms = 10/saniyə throttle
        }
    }
}

// Laravel Horizon ilə daha güclü queue throttling:
// horizon.php — environments.production.supervisor.maxProcesses
// Horizon-un "processes" limiti də bir növ throttling-dir
```

```php
<?php

// Login brute force qorunması
class LoginController extends Controller
{
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $key = 'login:' . $request->ip();

        // IP-ə görə: 5 cəhd / 1 dəqiqə
        if (\RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $seconds = \RateLimiter::availableIn($key);

            return response()->json([
                'error'   => 'Too many login attempts.',
                'message' => "Please try again in {$seconds} seconds.",
            ], 429, ['Retry-After' => $seconds]);
        }

        // Login cəhdi
        if (!\Auth::attempt($request->only('email', 'password'))) {
            \RateLimiter::hit($key, decaySeconds: 60);
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        \RateLimiter::clear($key);  // Uğurlu login — sayacı sıfırla

        return response()->json(['token' => $request->user()->createToken('api')->plainTextToken]);
    }
}
```

## Praktik Tapşırıqlar

1. `RedisRateLimiter` class yazın: `allow()` INCR + EXPIRE ilə; `allowSlidingWindow()` sorted set ilə; test: 60 sorğu → qəbul; 61-ci → reject; 60 saniyə sonra yenidən qəbul
2. Laravel `RateLimiter::for()` ilə API plan-based limit qurun: `free=10/dəq`, `pro=100/dəq`, `enterprise=1000/dəq`; middleware-i route-lara tətbiq edin; 429 cavabında `Retry-After` header göndərin
3. Email blast throttling: 1000 user-ə email göndərmək üçün saniyədə 10 email throttle edin; dispatch delay ilə; Horizon dashboard-da queue depth-ini izləyin
4. Login brute force qorunması: IP + email kombinasiyası ilə 5 cəhd / 15 dəqiqə; uğurlu loginda sayacı sıfırla; test: 5 yanlış → locked; 16 dəqiqə sonra yenidən cəhd edə bilir

## Əlaqəli Mövzular

- [Retry Pattern](17-retry-pattern.md) — 429 alındıqda `Retry-After` header-a baxaraq gözlə
- [Circuit Breaker](16-circuit-breaker.md) — rate limit sistematik şəkildə keçildikcə CB açıla bilər
- [Bulkhead Pattern](07-bulkhead-pattern.md) — throttling + bulkhead birlikdə resurs qoruması
- [BFF Pattern](09-bff-pattern.md) — hər BFF üçün ayrı rate limit qaydaları (mobile vs partner)
