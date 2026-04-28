# Rate Limiter — DB Design (Senior ⭐⭐⭐)

## İcmal

Rate limiting API-ləri abusedən, DDoS-dan və bot trafıkindən qoruyur. 5 əsas alqoritm var — hər birinin fərqli Redis/DB dizayn tələbləri var. Redis rate limiting üçün defacto standartdır: atomic operasiyalar, < 1ms latency.

---

## Tövsiyə olunan DB Stack

```
Rate limit state:  Redis      (in-memory counters, O(1) operations)
Config / rules:    PostgreSQL (rate limit policies, per-plan limits)
Analytics:         ClickHouse (blocked requests, abuse patterns)
```

---

## 5 Alqoritm

### 1. Fixed Window Counter

```
Sadəlik: hər time window üçün counter
  Window: 1 dəqiqə
  Limit: 100 request/dəqiqə

Redis:
  INCR  rl:fw:{user_id}:{YYYYMMHHMM}
  EXPIRE rl:fw:{user_id}:{YYYYMMHHMM} 60

Problem — Edge burst:
  14:35:59 → 100 request (limit dolu)
  14:36:00 → yeni window, 100 request yenidən
  → 2 saniyədə 200 request buraxıldı!
```

### 2. Sliding Window Log

```
Hər request-in timestamp-ini sorted set-də saxla
Window: son 60 saniyəni saxla, köhnəni sil

Redis:
  now = current timestamp (ms)
  window_start = now - 60000 (ms)
  
  ZADD  rl:log:{user_id} {now} {request_uuid}
  ZREMRANGEBYSCORE rl:log:{user_id} 0 {window_start}
  count = ZCARD rl:log:{user_id}
  EXPIRE rl:log:{user_id} 60
  
  count < limit → allow

Con: Memory-heavy — hər request bir element
     100 req/s × 1000 user = 6M elements (60s window)
```

### 3. Sliding Window Counter (Hybrid — Tövsiyə)

```
Fixed window problemini çözdür, memory-efficient:

Approximate count = prev_window × remaining_ratio + curr_window

Nümunə:
  Window: 60s, limit: 100
  Current time: 14:35:45 → 75% window keçib
  Previous window count: 80
  Current window count: 30
  
  Estimate = 80 × (1 - 0.75) + 30 = 80×0.25 + 30 = 50
  50 < 100 → allow

Redis:
  prev = GET rl:sw:{user_id}:{prev_minute}  → 80
  curr = GET rl:sw:{user_id}:{curr_minute}  → 30
  elapsed_pct = (current_second % 60) / 60   → 0.75
  estimate = prev × (1 - elapsed_pct) + curr
  
  estimate < limit → INCR curr key → allow

Pro: Fixed burst problem həll edildi, sadə implementation
```

### 4. Token Bucket

```
Bucket N token saxlayır (capacity)
Hər saniyə rate token əlavə edilir (max = capacity)
Hər request 1 token istifadə edir → burst allowed

Redis Lua (atomic):
  local tokens    = tonumber(redis.call('GET', KEYS[1])) or capacity
  local last_time = tonumber(redis.call('GET', KEYS[2])) or now
  local elapsed   = now - last_time
  
  tokens = math.min(capacity, tokens + elapsed * rate)
  
  if tokens >= 1 then
      redis.call('SET', KEYS[1], tokens - 1)
      redis.call('SET', KEYS[2], now)
      return 1  -- allowed
  end
  return 0      -- rejected

Pro: Burst allowed (bucket dolub axır)
     Gradual refill → smooth traffic
Con: State management mürəkkəbdir
```

### 5. Leaky Bucket

```
Queue + sabit drain rate:
  Request gəlir → queue-ya əlavə et
  Worker sabit rate ilə queue-dan oxuyur
  Queue dolu → reject (overflow)

Redis:
  LLEN  bucket:{user_id}  → queue size
  → size < capacity: RPUSH bucket:{user_id} {request_id}
  → size >= capacity: reject
  
  Worker: LPOP bucket:{user_id} hər X ms-də

Pro: Çıxış axını tamamilə sabitdir (DDoS-a ideal)
Con: Request queue-da gözləyir → yüksək latency
     Gaming/realtime üçün uyğun deyil
```

---

## Alqoritm Müqayisəsi

```
┌─────────────────┬──────────┬──────────┬───────────┬──────────┐
│                 │Fixed Win │Sliding Log│Sliding W. │Token Bkt │
├─────────────────┼──────────┼──────────┼───────────┼──────────┤
│ Edge burst?     │ ✗ (var)  │ ✓        │ ✓ (approx)│ ✓        │
│ Memory          │ Minimal  │ Per req  │ Minimal   │ Minimal  │
│ Complexity      │ Low      │ Medium   │ Medium    │ High     │
│ Burst support   │ ✗        │ ✗        │ ✗         │ ✓        │
│ Smooth output   │ ✗        │ ✗        │ ✗         │ ✗ / Leaky│
└─────────────────┴──────────┴──────────┴───────────┴──────────┘

Tövsiyə:
  API rate limiting:  Sliding Window Counter (balance + simple)
  Burst-tolerant:     Token Bucket (API tier with burst)
  DDoS protection:    Fixed Window (per IP, fast check)
  Smooth pipeline:    Leaky Bucket (batch processing)
```

---

## DB Schema: Rate Limit Configuration

```sql
-- Rate limit qaydaları
CREATE TABLE rate_limit_rules (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Scope
    scope       VARCHAR(20) NOT NULL,  -- 'global', 'user', 'plan', 'endpoint', 'ip'
    
    -- Endpoint filter (NULL = all)
    endpoint    VARCHAR(255),          -- '/api/v1/payments'
    method      VARCHAR(10),           -- 'POST', NULL = all
    
    -- Plan-based
    plan        VARCHAR(50),           -- 'free', 'pro', 'enterprise'
    
    -- Algorithm
    algorithm   VARCHAR(30) DEFAULT 'sliding_window',
    -- 'fixed_window', 'sliding_window', 'token_bucket', 'leaky_bucket'
    
    -- Limits
    max_requests    INTEGER NOT NULL,  -- 100
    window_seconds  INTEGER NOT NULL,  -- 60
    
    -- Token bucket specific
    burst_capacity  INTEGER,           -- max burst tokens
    refill_rate     NUMERIC(10, 2),    -- tokens/second
    
    retry_after_seconds INTEGER DEFAULT 60,
    
    is_active   BOOLEAN DEFAULT TRUE,
    priority    SMALLINT DEFAULT 100,  -- daha aşağı = daha yüksək prioritet
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Nümunə qaydalar
INSERT INTO rate_limit_rules (scope, endpoint, max_requests, window_seconds, priority) VALUES
  ('ip',       NULL,                    60,   60,  10),  -- IP: 60/min (DDoS)
  ('global',   NULL,                   100,   60,  20),  -- hamı: 100/min
  ('plan',     NULL,                  1000,   60,  30),  -- pro plan: 1000/min
  ('endpoint', '/api/v1/login',          5,  300,   5),  -- login: 5/5min (strictest)
  ('endpoint', '/api/v1/payments',      10,   60,   5);  -- payment: 10/min

-- User-specific overrides (VIP müştərilər, internal tools)
CREATE TABLE rate_limit_overrides (
    user_id         BIGINT NOT NULL,
    endpoint        VARCHAR(255),
    max_requests    INTEGER NOT NULL,
    window_seconds  INTEGER NOT NULL,
    reason          TEXT,
    expires_at      TIMESTAMPTZ,        -- NULL = permanent
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, COALESCE(endpoint, ''))
);

-- Rate limit events (audit, analytics)
CREATE TABLE rate_limit_events (
    id          BIGSERIAL,
    user_id     BIGINT,
    ip_address  INET,
    endpoint    VARCHAR(255),
    rule_id     UUID REFERENCES rate_limit_rules(id),
    action      VARCHAR(10) NOT NULL,   -- 'allowed', 'rejected'
    request_count INTEGER,
    created_at  TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (created_at);
```

---

## Redis Key Design

```
Naming convention:
  rl:{algo}:{scope}:{identifier}:{window}

Nümunələr:
  rl:fw:user:123:20260428T1435         -- fixed window, user 123, minute
  rl:sw:ip:1.2.3.4:20260428           -- sliding window, IP, day
  rl:tb:apikey:xyz123                   -- token bucket, API key
  rl:fw:endpoint:POST:/api/login:ip:1.2.3.4:20260428T1435

Multi-level rate limiting (layered check):
  1. IP-level (DDoS defense) — fastest check
  2. User-level (account limits)
  3. Endpoint-level (sensitive operations)
  4. Plan-level (subscription tier)
  
  First reject wins — response qaytarılır
  All levels cached in Redis — single round-trip mümkün (pipeline)
```

---

## Laravel Middleware Implementation

```php
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $checks = [
            $this->checkIp($request),
            $this->checkUser($request),
            $this->checkEndpoint($request),
        ];

        foreach ($checks as [$allowed, $limit, $remaining, $retryAfter]) {
            if (!$allowed) {
                return $this->tooManyRequests($limit, $remaining, $retryAfter);
            }
        }

        return $next($request)->withHeaders($this->rateLimitHeaders(
            $checks[1][1], $checks[1][2]  // user-level limit headers
        ));
    }

    private function checkUser(Request $request): array
    {
        $user = $request->user();
        if (!$user) return [true, 60, 60, null];

        $rule = $this->getRuleForPlan($user->plan);
        return $this->slidingWindow("user:{$user->id}", $rule);
    }

    private function slidingWindow(string $identifier, object $rule): array
    {
        $now    = now()->getTimestampMs();
        $window = $rule->window_seconds * 1000;
        $key    = "rl:sw:{$identifier}";

        [$count] = Redis::pipeline(function ($pipe) use ($key, $now, $window) {
            $pipe->zremrangebyscore($key, 0, $now - $window);
            $pipe->zadd($key, $now, "{$now}_" . uniqid());
            $pipe->zcard($key);
            $pipe->expire($key, $rule->window_seconds + 1);
        });

        $allowed   = $count <= $rule->max_requests;
        $remaining = max(0, $rule->max_requests - $count);

        return [$allowed, $rule->max_requests, $remaining, $allowed ? null : $rule->retry_after_seconds];
    }

    private function tooManyRequests(int $limit, int $remaining, int $retryAfter): Response
    {
        return response()->json([
            'error'       => 'rate_limit_exceeded',
            'message'     => "{$limit} requests per window allowed.",
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'X-RateLimit-Limit'     => $limit,
            'X-RateLimit-Remaining' => $remaining,
            'Retry-After'           => $retryAfter,
        ]);
    }
}
```

---

## HTTP Response Headers (Standart)

```
RFC 6585 + IETF RateLimit draft:

X-RateLimit-Limit:     100        -- window limiti
X-RateLimit-Remaining: 45         -- qalan sorğular
X-RateLimit-Reset:     1714316100 -- reset UNIX timestamp
Retry-After:           35         -- neçə saniyə gözlə (429-da)

429 Too Many Requests response body:
  {
    "error": "rate_limit_exceeded",
    "message": "100 requests per minute. Retry after 35 seconds.",
    "retry_after": 35,
    "limit": 100,
    "window": 60
  }
```

---

## Distributed Rate Limiting

```
Problem: 3 app server → hər biri local counter saxlayır
  Server A: user X üçün 80 request say
  Server B: user X üçün 80 request say
  → Toplam: 160 request buraxıldı, limit 100 idi!

Həll: Centralized Redis
  Bütün server-lər eyni Redis-ə yazır
  INCR atomic operasiya → race condition yoxdur

Redis Cluster:
  3+ node shard cluster
  Konsistentlik: eventual (tiny window-da ±1 request mümkün)
  Availability: node fail-da cluster işləyir

Lua script (atomicity):
  Multi-command sequence → single EVAL call
  INCR + EXPIRE atomically = race condition yoxdur
  Check-then-act pattern Lua ilə safe olur

Cross-region:
  Region A + B hər biri öz Redis-i
  Global rate limiting: CRDTs (eventual consistency accepted)
  Çox nadir tələb: çox güclü consistency lazım deyil
```

---

## Tanınmış Sistemlər

```
Stripe:
  Tier-based: Test mode 100/s, Live mode endpoint-specific
  Per endpoint: /charges daha strict
  Retry-After header + idempotency keys combo

GitHub API:
  Authenticated: 5000 req/hour
  Unauthenticated: 60 req/hour
  Search: 30 req/min (stricter endpoint)
  GraphQL: 5000 points/hour (query complexity score)

Twitter/X:
  Per-app + per-user limits
  Window: 15 dəqiqə
  Different per endpoint: read vs write

Kong API Gateway:
  Plugin: rate-limiting
  Backend: Redis
  Strategy: sliding window + local policy cache

Nginx:
  ngx_http_limit_req_module
  Leaky bucket implementation
  limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
  limit_req zone=api burst=20 nodelay;
```

---

## Anti-Patterns

```
✗ Local in-memory counter (distributed):
  Hər process öz count-unu saxlayır → rate limit bypass
  Həmişə centralized Redis istifadə et

✗ Database-only rate limiting:
  Hər request üçün DB query → 10ms+ əlavə latency
  Redis: < 1ms

✗ Global shared key:
  "rl:global" → bütün user-lər eyni bucket-ı bölüşür
  Per-user + per-endpoint ayrı key-lər lazımdır

✗ Retry-After header olmadan 429:
  Client nə vaxt retry edəcəyini bilmir
  → Thundering herd: hamı eyni anda retry edir

✗ Rate limit event-ləri sync loglamaq:
  Async log: Queue → ClickHouse
  Sync log: request-i yavaşladır

✗ Rate limiti yalnız app layer-də:
  Nginx / API Gateway layer-da da tətbiq et
  Defense in depth: CDN → Gateway → App
```
