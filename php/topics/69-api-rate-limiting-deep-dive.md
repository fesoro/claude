# API Rate Limiting — Token Bucket, Sliding Window, Algorithms

## Mündəricat
1. [Rate Limiting Alqoritmləri](#rate-limiting-alqoritmləri)
2. [Token Bucket](#token-bucket)
3. [Leaky Bucket](#leaky-bucket)
4. [Fixed Window Counter](#fixed-window-counter)
5. [Sliding Window Log](#sliding-window-log)
6. [Sliding Window Counter](#sliding-window-counter)
7. [Distributed Rate Limiting](#distributed-rate-limiting)
8. [PHP İmplementasiyası](#php-implementasiyası)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Rate Limiting Alqoritmləri

```
// Bu kod rate limiting alqoritmlərini memory və burst xüsusiyyətləri baxımından müqayisə edir
Niyə rate limiting?
  ✅ DDoS / brute force önlə
  ✅ API abuse qarşısını al
  ✅ Fair usage (bir user hamını yavaşlatmasın)
  ✅ Cost control (expensive operations)
  ✅ Downstream service protection

Alqoritm müqayisəsi:
┌─────────────────────┬──────────┬──────────────┬─────────────┐
│                     │ Burst    │ Smooth       │ Memory      │
├─────────────────────┼──────────┼──────────────┼─────────────┤
│ Token Bucket        │ ✅ Yaxşı │ Orta         │ Az          │
│ Leaky Bucket        │ ❌ Yoxdur│ ✅ Mükəmməl  │ Az          │
│ Fixed Window        │ ✅ Var   │ ❌ Boundary  │ Az          │
│ Sliding Window Log  │ ❌ Yoxdur│ ✅ Mükəmməl  │ Çox         │
│ Sliding Window Ctr  │ Orta     │ ✅ Yaxşı     │ Az          │
└─────────────────────┴──────────┴──────────────┴─────────────┘
```

---

## Token Bucket

```
// Bu kod Token Bucket alqoritmini burst traffic icazəsi nümunəsi ilə izah edir
Bucket → token-larla dolu
Hər sorğu 1 token istifadə edir
Dolu deyilsə → 429 Too Many Requests
Bucket müəyyən sürətlə dolur

rate = 10 req/s, burst = 20:

t=0:  bucket=[20] full
t=0.1: 5 sorğu → bucket=[15]
t=0.2: 5 sorğu → bucket=[10]
       1s refill başlayır (10 token/s)
t=1.0: bucket=[20] (1s-də 10 token əlavə, 10 istifadə = 20)

✅ Burst traffic-ə icazə verir (mobile app spike)
✅ Ortalama rate-i enforce edir
✅ Memory-efficient (yalnız token count + timestamp)

İstifadə: AWS API Gateway, Stripe API
```

---

## Leaky Bucket

```
// Bu kod Leaky Bucket alqoritminin sabit çıxış sürəti ilə trafik hamarlaşdırmasını göstərir
Request-lər bucket-ə daxil olur
Fixed rate ilə "sızır" (process edilir)
Bucket dolu olduqda yeni sorğular reject olur

         ┌─────────┐
requests │▓▓▓▓▓▓▓▓▓│ (queue)
────────►│         │
         │         │──► fixed rate ilə çıxır (e.g. 10 req/s)
         └─────────┘
           overflow → 429

✅ Mükəmməl smooth output (traffic shaping)
✅ Downstream servisi qoruyur
❌ Burst traffic-ə icazə vermir
❌ Request latency artır (queue-da gözləyir)

İstifadə: Network traffic shaping, printer queue
```

---

## Fixed Window Counter

```
// Bu kod Fixed Window Counter alqoritmini boundary burst problemi ilə göstərir
Zaman window-lara bölünür (1 dəq)
Hər window-da limit: 100 req

00:01:00 → 00:01:59  | count=0
00:01:50: 100 sorğu → count=100, limit doldu
00:02:00: window reset → count=0, 100 sorğu yenə olar

Problem: Boundary attack!
  00:01:50 - 100 sorğu (son 10s)
  00:02:00 - 100 sorğu (ilk 10s)
  → 10 saniyədə 200 sorğu!

✅ Sadə, az memory
❌ Boundary-də 2x burst mümkündür
```

---

## Sliding Window Log

```
// Bu kod Sliding Window Log alqoritminin dəqiq amma memory-intensive işləməsini göstərir
Hər sorğunun timestamp-i saxlanılır
Sorğu gəldikdə son window-dakı sorğuları say

Limit: 100 req/min

Sorğu gəldi t=60.5s:
  Log: [0.1, 0.2, 0.5, ..., 60.3, 60.4]
  Son 60s: t > 0.5 olan-ları say
  Count = 98 < 100 → keç

✅ Dəqiq
✅ Boundary problemi yoxdur
❌ Çox memory (hər request-in timestamp-i)
❌ Çox Redis ops (cleanup + count)
```

---

## Sliding Window Counter

```
// Bu kod Sliding Window Counter alqoritminin memory-efficient approximate hesablamasını göstərir
Fixed window + öncəki window-un ağırlıqlı cəmi

current_count + prev_count * (1 - elapsed/window)

Nümunə:
  window = 60s, limit = 100
  Öncəki window: 80 sorğu
  Hazırkı window: 30s keçib (50%), 30 sorğu
  
  estimate = 30 + 80 * (1 - 30/60) = 30 + 40 = 70 → keç

✅ Memory-efficient (2 sayğac)
✅ Smooth, az burst
✅ Fixed window-un boundary problemini azaldır
❌ Approximate (dəqiq deyil)

İstifadə: Redis sliding window, Nginx limit_req
```

---

## Distributed Rate Limiting

```
// Bu kod distributed sistemdə Redis shared counter ilə race condition həllini göstərir
Problem: Multi-server deployment-də hər server öz sayğacını saxlayır

Server 1: user:123 → 60 req
Server 2: user:123 → 60 req
Total: 120 req (limit 100 keçildi!)

Həll: Redis shared counter

Bütün serverlər eyni Redis counter-ı istifadə edir:
  INCR rate:user:123:minute:202401151430

Race condition:
  Server1: GET → 99
  Server2: GET → 99
  Server1: SET 100 → ok
  Server2: SET 100 → ok!
  Total: 100 (olmalı idi 101)
  
Həll: Lua script (atomic)
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod Redis Lua script istifadə edərək Token Bucket və Sliding Window middleware-i göstərir
// Token Bucket (Redis-based)
class TokenBucketRateLimiter
{
    public function __construct(
        private \Redis $redis,
        private int $capacity,      // Max tokens
        private int $refillRate,    // Tokens per second
    ) {}
    
    public function attempt(string $key): bool
    {
        $script = <<<LUA
        local key = KEYS[1]
        local capacity = tonumber(ARGV[1])
        local refill_rate = tonumber(ARGV[2])
        local now = tonumber(ARGV[3])
        
        local data = redis.call('HMGET', key, 'tokens', 'last_refill')
        local tokens = tonumber(data[1]) or capacity
        local last_refill = tonumber(data[2]) or now
        
        -- Refill
        local elapsed = now - last_refill
        local new_tokens = math.min(capacity, tokens + elapsed * refill_rate)
        
        if new_tokens < 1 then
            return 0  -- No tokens
        end
        
        -- Use token
        redis.call('HMSET', key, 'tokens', new_tokens - 1, 'last_refill', now)
        redis.call('EXPIRE', key, capacity / refill_rate + 1)
        return 1
        LUA;
        
        return (bool) $this->redis->eval(
            $script,
            [$key, $this->capacity, $this->refillRate, microtime(true)],
            1
        );
    }
}

// Sliding Window Counter (Laravel Middleware)
class RateLimitMiddleware
{
    private array $limits = [
        'api/payments'  => ['limit' => 10,  'window' => 60],   // 10/min
        'api/search'    => ['limit' => 100, 'window' => 60],   // 100/min
        'api/*'         => ['limit' => 1000, 'window' => 3600], // 1000/hr
    ];
    
    public function handle(Request $request, Closure $next): Response
    {
        $key    = $this->getKey($request);
        $config = $this->getConfig($request->path());
        
        $result = $this->checkLimit($key, $config['limit'], $config['window']);
        
        if (!$result['allowed']) {
            return response()->json(
                ['error' => 'Too Many Requests', 'retry_after' => $result['retry_after']],
                429,
                [
                    'X-RateLimit-Limit'     => $config['limit'],
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset'     => $result['reset'],
                    'Retry-After'           => $result['retry_after'],
                ]
            );
        }
        
        $response = $next($request);
        
        return $response->withHeaders([
            'X-RateLimit-Limit'     => $config['limit'],
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Reset'     => $result['reset'],
        ]);
    }
    
    private function checkLimit(string $key, int $limit, int $window): array
    {
        $script = <<<LUA
        local key = KEYS[1]
        local limit = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local now = tonumber(ARGV[3])
        local prev_window_key = key .. ':prev'
        
        -- Current window
        local current = tonumber(redis.call('GET', key) or 0)
        -- Previous window  
        local previous = tonumber(redis.call('GET', prev_window_key) or 0)
        
        -- Window position (0.0 to 1.0)
        local window_pos = (now % window) / window
        
        -- Estimated count
        local estimated = current + previous * (1 - window_pos)
        
        if estimated >= limit then
            local ttl = redis.call('TTL', key)
            return {0, 0, ttl}
        end
        
        -- Increment
        local new_count = redis.call('INCR', key)
        if new_count == 1 then
            redis.call('EXPIRE', key, window * 2)
        end
        
        return {1, limit - new_count, redis.call('TTL', key)}
        LUA;
        
        [$allowed, $remaining, $ttl] = $this->redis->eval(
            $script,
            [$key, $limit, $window, time()],
            1
        );
        
        return [
            'allowed'     => (bool) $allowed,
            'remaining'   => max(0, $remaining),
            'reset'       => time() + $ttl,
            'retry_after' => $ttl,
        ];
    }
    
    private function getKey(Request $request): string
    {
        $userId = $request->user()?->id ?? $request->ip();
        return "rate_limit:{$userId}:" . date('YmdHi');
    }
}

// Laravel built-in throttle
Route::middleware('throttle:api')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

// config/rate_limiting.php
RateLimiter::for('api', function (Request $request) {
    return [
        Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()),
        Limit::perDay(10000)->by($request->user()?->id),
    ];
});

// Per-route custom limits
RateLimiter::for('payments', function (Request $request) {
    return Limit::perMinute(10)
        ->by($request->user()->id)
        ->response(fn() => response()->json(['error' => 'Too many payment attempts'], 429));
});
```

---

## İntervyu Sualları

**1. Token Bucket alqoritmini izah et.**
Bucket müəyyən kapasitəyə sahibdir. Sabit sürətlə dolur. Hər sorğu 1 token istifadə edir. Bucket boşdursa 429. Burst traffic-ə icazə verir (dolu bucket-dən ani çəkiş). Ortalama rate-i enforce edir.

**2. Fixed Window-un boundary problemi nədir?**
Window keçidində 2x burst mümkündür. 00:59-da 100 sorğu + 01:00-da 100 sorğu = 2 saniyədə 200 sorğu. Sliding Window bu problemi həll edir.

**3. Sliding Window Counter niyə memory-efficient-dir?**
Log variantında hər sorğunun timestamp-i saxlanılır (O(n) memory). Counter variantında yalnız 2 sayğac: cari window + əvvəlki window. Approximate amma çox az memory ilə yaxşı nəticə.

**4. Distributed rate limiting-in problemi nədir?**
Hər server öz counter-ı saxlayırsa limit N-server = limit * N olur. Həll: Redis shared counter + Lua script (atomic). Lua script: GET + check + SET atomik olaraq — race condition yoxdur.

**5. Rate limit response header-ları hansılardır?**
X-RateLimit-Limit: ümumi limit. X-RateLimit-Remaining: qalan sorğu. X-RateLimit-Reset: counter sıfırlanma vaxtı (Unix timestamp). Retry-After: 429-da nə vaxt yenidən cəhd et (saniyə). RFC 6585 standartı.

**6. Soft limit vs Hard limit fərqi nədir?**
Hard limit: 429 qaytarır, sorğu rədd edilir. Soft limit: limit keçildikdə sorğu qəbul edilir amma yavaşladılır (throttling) ya da warning log yazılır. Premium/trial tier fərqi: trial-da hard limit, premium-da soft limit ilə daha yaxşı UX.

**7. Rate limiting-i hansı qatda tətbiq etmək daha yaxşıdır?**
Ən yuxarı qatda (WAF, Nginx, API Gateway) tətbiq etmək lazımdır — bot trafiki app serverinə çatmadan kəsilir. Application layer-da daha granular (per-endpoint, per-user) rate limit əlavə oluna bilər. İkisi tamamlayıcıdır, ancaq application layer tək başına yetərli deyil.

**8. Laravel Throttle middleware-i necə işləyir?**
`RateLimiter::for()` ilə named limiter müəyyən edilir. Default driver: cache (Redis tövsiyə edilir). `Limit::perMinute(60)->by($user->id)` — user-based. `->response()` ilə custom 429 cavab. `perMinutes(5, 3)`: 5 dəqiqədə 3 cəhd — brute force üçün.

---

## Anti-patternlər

**1. In-memory (per-server) counter ilə distributed sistemdə rate limit tətbiq etmək**
Hər server öz counter-ını saxlayır — 10 server varsa real limit faktiki olaraq 10 qat artır, rate limiting mənasını itirir. Redis shared counter istifadə edin: Lua script ilə GET + increment + TTL atomik əməliyyatı, bütün serverlar eyni sayğacı paylaşır.

**2. Fixed Window alqoritmi ilə boundary burst-a imkan vermək**
Window keçidindəki 2x burst problem göz ardı edilir — hər iki window-un sonunda/başında 200 sorğu 2 saniyədə keçir. Sliding Window Log ya da Sliding Window Counter istifadə edin; token bucket alqoritmi burst-a məhdud icazə verərkən ortalama rate-i qoruyur.

**3. Bütün endpoint-lərə eyni limit tətbiq etmək**
`/api/login` və `/api/products` üçün eyni 100 req/dəq — login endpoint brute force hücumuna açıq qalır, products endpoint lazımsız məhdudlaşır. Endpoint-ə görə ayrı limit qurun: auth endpoint-ləri daha sərt (10 req/dəq), public oxuma endpoint-ləri daha geniş.

**4. Rate limit aşıldıqda `Retry-After` header-ı göndərməmək**
429 cavab qaytarılır, client nə vaxt retry edəcəyini bilmir — bəzi client-lər dərhal retry edir, yük artmağa davam edir. `Retry-After` header-ını həmişə əlavə edin: window sıfırlanana qədər neçə saniyə qaldığını bildirin, client-lər backoff tətbiq edə bilsin.

**5. Rate limit-i yalnız application layer-da tətbiq etmək**
PHP kodunda rate limit var, lakin Nginx/API Gateway-dən keçən ham trafik filtrlənmir — botlar birbaşa app serverini yükləyir. Rate limit-i mümkün qədər yuxarı qatda tətbiq edin: Nginx `limit_req_zone`, API Gateway, ya da WAF; application layer son müdafiə xəttidir.

**6. Rate limit key-i yalnız IP ünvanına əsaslandırmaq**
`$key = $request->ip()` — NAT arxasındakı bütün ofis istifadəçiləri eyni IP paylaşır, biri limit-i doldurur, hamı bloklanır. Authenticated sorğular üçün `user_id` əsaslı rate limit istifadə edin; IP limitini yalnız unauthenticated trafik üçün istifadə edin.
