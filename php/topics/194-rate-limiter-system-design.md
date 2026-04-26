# System Design: Rate Limiter (Lead)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Alqoritmlər](#alqoritmlər)
3. [Distributed Rate Limiter](#distributed-rate-limiter)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  API sorğularını limit et
  Fərqli limit növləri: per-user, per-IP, per-API-key
  Fərqli window: saniyəlik, dəqiqəlik, saatlıq
  Soft limit: xəbərdarlıq
  Hard limit: 429 Too Many Requests

Qeyri-funksional:
  Aşağı gecikmə: < 1ms (hər sorğuya tətbiq edilir)
  Distributed: bir neçə server eyni limit paylaşır
  Accurate: az yanlış positive/negative
  Resilient: Redis down olsa degraded (not fail completely)
```

---

## Alqoritmlər

```
1. Fixed Window Counter:
   Her dəqiqəni ayrıca say
   Key: "rate:user1:2026041014:05" (saat:dəqiqə)
   
   Problem: Window kənarında burst
   Dəqiqənin son saniyəsindən + yeni dəqiqənin ilk saniyəsi:
   2× limit sığır

2. Sliding Window Log:
   Hər sorğunun timestamp-ini saxla
   Son 60 saniyədəki sayı hesabla
   
   Dəqiq amma memory çox (hər sorğu timestamp saxlanır)

3. Sliding Window Counter (ən praktik):
   İki window counter birləşdir
   current_count = prev_window × overlap_ratio + curr_window
   
   overlap_ratio = (1 - window_elapsed / window_size)
   
   Nümunə (60s window, 10:00:45-dadır):
   prev_window (10:00): 40 sorğu
   curr_window (10:01): 15 sorğu
   elapsed = 45s, ratio = 1 - 45/60 = 0.25
   estimated = 40 × 0.25 + 15 = 25 sorğu

4. Token Bucket:
   Hər saniyə N token əlavə edilir
   Sorğu gəldikdə token istifadə edilir
   Burst mümkündür (token yığıla bilər)
   API-lər üçün uyğun (burst traffic OK)

5. Leaky Bucket:
   Sabit sürətlə axır
   Queue-a düşür, sabit sürətlə işlənir
   Burst qadağan (smooth traffic)
   Network trafikini smooth etmək üçün
```

---

## Distributed Rate Limiter

```
Problem: 3 server, hər biri local counter
  User A → Server1: 99/100 limit
  User A → Server2: 99/100 limit (bilmir Server1-dəki barədə)
  Effektiv limit: 300 (limit deyil!)

Həll 1 — Redis Centralized:
  Bütün server-lər eyni Redis-ə baxır
  Atomic increment: INCR + EXPIRE
  Dezavantaj: Redis latency + SPOF

Həll 2 — Redis Cluster:
  user_id → hash → Redis shard
  Dağıtılmış, amma eyni user həmişə eyni shard-a
  Resiliency: replica set

Həll 3 — Local + Sync:
  Hər server local counter saxlayır
  Periodically Redis-ə sync edir
  Slightly inaccurate amma daha sürətli
  Trade-off: accuracy vs latency

Lua Script (atomic):
  Redis single-threaded → Lua script atomic
  INCR + check + EXPIRE bir əməliyyatda
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Sliding Window Counter (Redis)
class SlidingWindowRateLimiter
{
    public function __construct(
        private \Redis $redis,
        private int    $limit  = 100,
        private int    $window = 60,  // seconds
    ) {}

    public function isAllowed(string $identifier): RateLimitResult
    {
        $now        = microtime(true);
        $windowStart = $now - $this->window;

        $currentKey  = "rl:{$identifier}:" . (int) ($now / $this->window);
        $previousKey = "rl:{$identifier}:" . ((int) ($now / $this->window) - 1);

        // Atomic Lua script
        $script = <<<LUA
        local currentKey  = KEYS[1]
        local previousKey = KEYS[2]
        local window      = tonumber(ARGV[1])
        local now         = tonumber(ARGV[2])
        local limit       = tonumber(ARGV[3])

        local currentCount  = tonumber(redis.call('GET', currentKey))  or 0
        local previousCount = tonumber(redis.call('GET', previousKey)) or 0

        -- Window içindəki qalan zamanın nisbəti
        local elapsedInWindow = now % window
        local overlapRatio    = 1 - (elapsedInWindow / window)

        -- Smooth estimate
        local estimated = math.floor(previousCount * overlapRatio) + currentCount

        if estimated >= limit then
            return {0, estimated, 0}  -- denied
        end

        -- Increment current window
        local newCount = redis.call('INCR', currentKey)
        if newCount == 1 then
            redis.call('EXPIRE', currentKey, window * 2)
        end

        local remaining = limit - (estimated + 1)
        return {1, estimated + 1, remaining}  -- allowed
        LUA;

        [$allowed, $current, $remaining] = $this->redis->eval(
            $script,
            [$currentKey, $previousKey, $this->window, $now, $this->limit],
            2
        );

        return new RateLimitResult(
            allowed:   (bool) $allowed,
            current:   (int)  $current,
            remaining: (int)  $remaining,
            limit:     $this->limit,
            resetAt:   (int)  (ceil($now / $this->window) * $this->window),
        );
    }
}
```

```php
<?php
// 2. Token Bucket (Redis Lua script)
class TokenBucketRateLimiter
{
    public function __construct(
        private \Redis $redis,
        private int    $capacity   = 100,
        private float  $refillRate = 10.0,  // tokens per second
    ) {}

    public function consume(string $identifier, int $tokens = 1): RateLimitResult
    {
        $key    = "tb:{$identifier}";
        $now    = microtime(true);

        $script = <<<LUA
        local key        = KEYS[1]
        local capacity   = tonumber(ARGV[1])
        local refillRate = tonumber(ARGV[2])
        local tokens     = tonumber(ARGV[3])
        local now        = tonumber(ARGV[4])

        local data      = redis.call('HMGET', key, 'amount', 'last_refill')
        local amount    = tonumber(data[1]) or capacity
        local lastRefill = tonumber(data[2]) or now

        -- Token refill
        local elapsed = now - lastRefill
        amount = math.min(capacity, amount + elapsed * refillRate)

        if amount >= tokens then
            amount = amount - tokens
            redis.call('HMSET', key, 'amount', amount, 'last_refill', now)
            redis.call('EXPIRE', key, 3600)
            return {1, math.floor(amount)}
        else
            redis.call('HMSET', key, 'amount', amount, 'last_refill', now)
            redis.call('EXPIRE', key, 3600)
            return {0, math.floor(amount)}
        end
        LUA;

        [$allowed, $remaining] = $this->redis->eval(
            $script,
            [$key, $this->capacity, $this->refillRate, $tokens, $now],
            1
        );

        return new RateLimitResult(
            allowed:   (bool) $allowed,
            remaining: (int)  $remaining,
            limit:     $this->capacity,
            resetAt:   (int)  ($now + ($tokens / $this->refillRate)),
        );
    }
}
```

```php
<?php
// 3. Rate Limit Middleware
class RateLimitMiddleware
{
    public function __construct(
        private SlidingWindowRateLimiter $limiter,
        private array $rules = [
            'default'   => ['limit' => 100, 'window' => 60],
            'premium'   => ['limit' => 1000, 'window' => 60],
            'anonymous' => ['limit' => 20, 'window' => 60],
        ],
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identifier = $this->getIdentifier($request);
        $rule       = $this->getRule($request);

        $this->limiter->setLimit($rule['limit']);
        $this->limiter->setWindow($rule['window']);

        $result = $this->limiter->isAllowed($identifier);

        if (!$result->allowed) {
            return new JsonResponse(
                ['error' => 'Rate limit exceeded', 'retry_after' => $result->resetAt - time()],
                429,
                [
                    'X-RateLimit-Limit'     => $rule['limit'],
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset'     => $result->resetAt,
                    'Retry-After'           => $result->resetAt - time(),
                ]
            );
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit',     (string) $rule['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $result->remaining)
            ->withHeader('X-RateLimit-Reset',     (string) $result->resetAt);
    }

    private function getIdentifier(ServerRequestInterface $request): string
    {
        $claims = $request->getAttribute('auth_claims');
        return $claims ? "user:{$claims['sub']}" : "ip:{$request->getServerParams()['REMOTE_ADDR']}";
    }

    private function getRule(ServerRequestInterface $request): array
    {
        $claims = $request->getAttribute('auth_claims');

        if (!$claims) {
            return $this->rules['anonymous'];
        }

        if (in_array('premium', $claims['roles'] ?? [])) {
            return $this->rules['premium'];
        }

        return $this->rules['default'];
    }
}
```

---

## İntervyu Sualları

- Fixed Window və Sliding Window rate limit fərqi nədir?
- Token Bucket ilə Leaky Bucket — hər birinin use case-i nədir?
- Distributed rate limiting üçün niyə Redis Lua script lazımdır?
- Rate limit keçdikdə 429 header-ları hansılardır?
- Redis down olduqda rate limiter necə davranmalıdır?
- Per-user, per-IP, per-endpoint limitlərini eyni anda necə tətbiq edərdiniz?
