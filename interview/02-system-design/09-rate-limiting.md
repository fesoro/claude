# Rate Limiting (Lead ⭐⭐⭐⭐)

## İcmal
Rate limiting, bir istifadəçinin və ya client-in müəyyən vaxt ərzindəki sorğu sayını məhdudlaşdıran mexanizmdir. DDoS hücumlarından qorunmaq, API fair use təmin etmək, backend-i overload-dan qorumaq üçün vacibdir. Interview-larda rate limiting, yalnız "Redis-də sayğac saxla" qədər sadə deyil — alqoritm seçimi, distributed implementation, bypass scenarios hamısı müzakirə olunur.

## Niyə Vacibdir
Stripe, Twitter API, GitHub API, Google Maps API — hər public API rate limit istifadə edir. Rate limiting olmadan tək pis bir client bütün sistemi çökürə bilər. Lead mühəndis rate limiting alqoritmlərini, distributed rate limiting çətinliklərini və müxtəlif granularities (IP, user, API key, endpoint) müzakirə edə bilir. Bu mövzu API gateway, DDoS protection, fair use interview-larında çıxır.

## Əsas Anlayışlar

### 1. Rate Limiting Alqoritmləri

**Token Bucket**
```
Bucket capacity: 100 tokens
Refill rate: 10 tokens/sec

Request gəlir:
  Token var → token götür, request keçir
  Token yoxdur → reject (429 Too Many Requests)

Burst allowed: bucket doluysa 100 request anlıq
Steady state: 10 req/sec
```
- Pros: Burst traffic allowance
- Cons: Implementation çox az mürəkkəb
- Use case: API rate limiting (Twitter, Stripe)

**Leaky Bucket**
```
Bucket capacity: 100
Leak rate: 10 req/sec (sabit sürət)

Request gəlir:
  Yer var → bucketa əlavə et, 10/sec process
  Yer yoxdur → reject
```
- Pros: Sabit output rate, traffic shaping
- Cons: Burst-ı qəbul etmir (queue dolu olsa reject)
- Use case: Network traffic shaping, outbound API calls

**Fixed Window Counter**
```
Window: 1 dəqiqə (00:00 - 01:00)
Limit: 100 req/window

00:00-01:00: 100 request → full
01:00-02:00: Counter sıfırlanır, 100 yenidən

Problem: Window boundary attack:
00:59 = 100 request
01:00 = 100 request
→ 2 saniyədə 200 request keçdi!
```
- Pros: Sadə, az memory
- Cons: Window boundary problem

**Sliding Window Log**
```
Hər request-in timestamp-ini saxla:
[00:00:01, 00:00:03, 00:00:05, ..., 00:00:58]

Yeni request gəlir (00:01:02):
  Son 1 dəq: [00:00:03, ..., 00:01:02] = say
  100-dən az → keçir, timestamp əlavə et
  100-dən çox → reject
```
- Pros: Dəqiq, window boundary problem yoxdur
- Cons: Memory-intensive (hər request timestamp saxlanır)

**Sliding Window Counter (Hybrid)**
```
Current window count + Previous window count × overlap

Nümunə (1 dəqiqəlik limit=100):
Previous window (00:00-01:00): 60 request
Current window (01:00-02:00): 40 request (30 saniyə keçib)
Current time: 01:30

Estimated requests in last 60 sec:
= 40 + 60 × (60-30)/60
= 40 + 60 × 0.5
= 40 + 30 = 70 < 100 → allow
```
- Pros: Memory efficient, dəqiq
- Cons: Approximate (sliding window log qədər dəqiq deyil)
- Use case: Cloudflare, production rate limiters

### 2. Redis-based Distributed Rate Limiting

**Simple Counter (Fixed Window)**
```lua
-- Lua script (atomic execution)
local key = "rate_limit:" .. user_id .. ":" .. current_window
local count = redis.call("INCR", key)
if count == 1 then
    redis.call("EXPIRE", key, window_size)
end
if count > limit then
    return 0  -- rate limited
end
return 1  -- allowed
```

**Token Bucket with Redis**
```lua
local key = "tokens:" .. user_id
local capacity = 100
local refill_rate = 10  -- per second
local now = tonumber(ARGV[1])

local data = redis.call("HMGET", key, "tokens", "last_refill")
local tokens = tonumber(data[1]) or capacity
local last_refill = tonumber(data[2]) or now

-- Calculate refill
local elapsed = now - last_refill
local refill = elapsed * refill_rate
tokens = math.min(capacity, tokens + refill)

if tokens >= 1 then
    tokens = tokens - 1
    redis.call("HMSET", key, "tokens", tokens, "last_refill", now)
    redis.call("EXPIRE", key, 3600)
    return 1  -- allowed
end
return 0  -- limited
```

### 3. Rate Limiting Granularity
```
Level 1: IP-based
  100 req/min per IP
  DDoS protection, unauthenticated APIs

Level 2: User-based
  1000 req/min per user_id
  Authenticated APIs

Level 3: API Key-based
  Free tier: 100 req/min
  Pro tier: 10K req/min
  Enterprise: unlimited

Level 4: Endpoint-based
  POST /api/login: 5 req/min (brute force protection)
  GET /api/search: 100 req/min
  POST /api/upload: 10 req/min

Level 5: Resource-based
  /api/users/{id}: 100 req/min per target user
  (prevent scraping specific user's data)
```

### 4. Distributed Rate Limiting Problemləri

**Race Condition:**
```
Server A reads counter: 99
Server B reads counter: 99
Server A increments: 100 (OK)
Server B increments: 100 (OK - but 101st request should be blocked!)
```
Həll: Redis Lua script (atomic), Redis INCR + EXPIRE atomic

**Network Partition:**
```
Redis unreachable → Rate limiter fails
Options:
1. Fail open (allow all) → DDoS risk
2. Fail closed (block all) → availability problem
3. Local fallback (in-memory counter) → temporary inconsistency

Production choice: Fail open + alert (availability > security for most APIs)
```

**Clock Skew:**
Multiple server-lərdə saat fərqli ola bilər:
- Fixed window: Fərqli server-lər fərqli window-da ola bilər
- NTP sync (< 1ms difference) adətən kifayətdir

### 5. Rate Limit Headers
```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
Retry-After: 30

HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1640995200
Retry-After: 30
Content-Type: application/json

{"error": "rate_limit_exceeded", "message": "Too many requests. Retry after 30 seconds."}
```

### 6. Rate Limiting Arxitekturası
```
Client
  │
[CDN Layer]       ← IP-based DDoS protection
  │
[Load Balancer]   ← Basic IP rate limiting (nginx)
  │
[API Gateway]     ← User/API key based rate limiting (Kong/AWS API GW)
  │
[Application]     ← Business logic rate limiting
  │
[Redis Cluster]   ← Distributed counter store
```

### 7. Throttling vs Rate Limiting
**Rate Limiting:** Hard limit — exceed → reject
**Throttling:** Soft limit — exceed → slow down (queue, delay)

```
Throttling nümunəsi:
100 req/sec limit
101. request → 10ms gecikmə ilə emal
200. request → 100ms gecikmə ilə emal
Reject yoxdur, ancaq yavaşlayır
```

### 8. Bypass Techniques and Mitigations
**IP rotation:** Multiple IP-dən sorğu
- Həll: User-ID based limiting (auth token)

**Distributed attack:** Bot network-dən
- Həll: ML-based anomaly detection, CAPTCHA

**Cache poisoning:** Rate limit bypass
- Həll: Per-resource keys, not just per-IP

**Retry storms:** 429 alır → hamı eyni anda retry
- Həll: Retry-After header, exponential backoff with jitter

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Niyə rate limiting lazımdır? (DDoS, fair use, backend protection)
2. Alqoritm seçimini əsaslandır (token bucket — burst lazım, sliding window — dəqiqlik)
3. Granularity müzakirə et (IP? user? endpoint?)
4. Distributed implementation-da race condition probleminə toxun
5. Fail behavior: fail open vs fail closed

### Ümumi Namizəd Səhvləri
- Yalnız IP-based rate limiting düşünmək
- Distributed rate limiting-in race condition problemini bilməmək
- 429 response header-lərini unutmaq
- DLQ / retry storm problemini nəzərə almamaq
- Burst traffic üçün token bucket üstünlüyünü bilməmək

### Senior vs Architect Fərqi
**Senior**: Alqoritm seçimi, Redis implementation, granularity dizaynı.

**Architect**: Rate limiting-i business model ilə əlaqələndirir (tier-based pricing), rate limit bypass-ının business impact-ini ölçür, adaptive rate limiting (sistem yükünə görə dinamik limit dəyişdirir), multi-region rate limiting consistency problemlərini həll edir, abuse detection + ML anomaly detection entegrasyonu.

## Nümunələr

### Tipik Interview Sualı
"Design a rate limiter for a public API. The system should support per-user and per-endpoint limits."

### Güclü Cavab
```
Rate limiter design:

Requirements:
- Per-user: 1000 req/min (authenticated)
- Per-IP: 100 req/min (unauthenticated)
- Per-endpoint: /login 5 req/min, /search 500 req/min
- Latency overhead: < 5ms
- High availability: Rate limiter failure should fail open

Algorithm: Sliding Window Counter
- Token bucket da yaxşıdır amma sliding window daha dəqiq
- Memory: O(1) per user

Redis Structure:
Key: rl:{user_id}:{endpoint}:{window}
Type: INCR (atomic counter)
TTL: window_size * 2

Middleware Flow:
1. Extract user_id from JWT / IP from header
2. Build Redis key
3. INCR key (Lua script, atomic)
4. If first increment → SET EXPIRE
5. Check count vs limit
6. Add X-RateLimit-* headers
7. Allow or 429

Multi-limit check:
Check order: endpoint limit → user limit → IP limit
First violation → return 429

Distributed:
- Redis Cluster (3 nodes, replication factor 2)
- Lua script for atomicity
- Fail open if Redis unavailable (allow request, log warning)

Monitoring:
- Rate limited requests per endpoint (counter)
- Top rate-limited users (sorted set)
- Redis latency (should be < 1ms)
```

### Sliding Window Counter Pseudocode
```
function isAllowed(userId, endpoint):
    now = currentTimestamp()
    currentWindow = floor(now / 60)  // 1-minute window
    prevWindow = currentWindow - 1

    currKey = "rl:{userId}:{endpoint}:{currentWindow}"
    prevKey = "rl:{userId}:{endpoint}:{prevWindow}"

    currCount = redis.GET(currKey) or 0
    prevCount = redis.GET(prevKey) or 0

    // Seconds elapsed in current window
    elapsed = now % 60
    
    // Weighted estimate
    estimate = currCount + prevCount * (60 - elapsed) / 60

    if estimate >= LIMIT:
        return false  // Rate limited

    redis.INCR(currKey)
    redis.EXPIRE(currKey, 120)  // 2x window for safety
    return true
```

## Praktik Tapşırıqlar
- Token bucket alqoritmini Redis ilə implement edin
- Sliding window counter Lua script yazın
- Rate limiting middleware Laravel-də qurun
- Stress test: 10K concurrent users, limit=100/min
- Bypass attempts: IP rotation, user rotation test edin

## Əlaqəli Mövzular
- [14-api-gateway.md] — API Gateway-də rate limiting
- [04-load-balancing.md] — LB-level rate limiting
- [05-caching-strategies.md] — Redis usage patterns
- [16-circuit-breaker.md] — Circuit breaker with rate limiting
- [21-backpressure.md] — Backpressure mechanisms
