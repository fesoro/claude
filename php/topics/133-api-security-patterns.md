# API Security Patterns

## Mündəricat
1. [Authentication vs Authorization](#authentication-vs-authorization)
2. [API Key Management](#api-key-management)
3. [Rate Limiting & Throttling](#rate-limiting--throttling)
4. [Input Validation & Output Encoding](#input-validation--output-encoding)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Authentication vs Authorization

```
Authentication (Kimlik): "Sən kimsən?"
  API Key, JWT, OAuth2, mTLS

Authorization (İcazə): "Nə edə bilərsən?"
  RBAC, ABAC, Scopes, ACL

JWT həyat dövrü:
  Client → Login → Server issues JWT (15 dəq ömür)
  Client → Request + JWT → Server validates
  JWT expired → Refresh token ilə yenilə

mTLS (Mutual TLS):
  Server client-in sertifikatını da yoxlayır
  Service-to-service authentication üçün ideal
  Client sertifikat olmadan bağlantı rədd edilir

API Key vs JWT:
  API Key: statik, server-side revoke mümkün, sadə
  JWT: stateless, expiry built-in, payload daşıyır
  mTLS: ən güclü, infrastruktur tələb edir
```

---

## API Key Management

```
API Key best practices:

1. Prefix ilə tip göstərin:
   sk_live_xxxx   → production secret key
   sk_test_xxxx   → test key
   pk_live_xxxx   → public key (client-side)

2. Hash-ləyin, plain saxlamayın:
   DB-də: sha256(apiKey) saxla
   Comparison: hash_equals(sha256($input), $storedHash)

3. Scopes/permissions:
   API key yalnız müəyyən əməliyyatlara icazəli
   key_id: "orders:read, orders:write"
   key_id: "webhooks:create" (billing yox)

4. Key rotation:
   Köhnə key-i dərhal silməyin — 24 saat overlap
   Yeni key → test et → köhnəni disable et

5. Per-key rate limiting:
   Hər API key öz limitinə malikdir

6. Key leakage detection:
   GitHub, Slack, email-i scan et
   Leaked key → dərhal revoke
```

---

## Rate Limiting & Throttling

```
Rate Limiting — müəyyən müddətdə neçə sorğu:
  100 req/minute per API key
  1000 req/hour per IP

Throttling — aşıldıqda nə olur:
  Option 1: 429 Too Many Requests qaytarın
  Option 2: Sorğunu queue-ya sal (delay)
  Option 3: Degraded response (cached data)

Algoritmalar:
  Token Bucket:
    Hər saniyə N token əlavə edilir (max capacity)
    Sorğu gəldikdə token istifadə edilir
    Ani burst mümkündür (bufer kimi)

  Leaky Bucket:
    Sabit sürətlə sorğu emal edilir
    Burst mümkün deyil

  Fixed Window:
    Hər dəqiqədə sayğac sıfırlanır
    Window kənarında burst mümkündür

  Sliding Window:
    Daha hamar — son N saniyənin sorğusu sayılır

Response headers:
  X-RateLimit-Limit: 100
  X-RateLimit-Remaining: 45
  X-RateLimit-Reset: 1716825600
  Retry-After: 60
```

---

## Input Validation & Output Encoding

```
Input Validation:
  Hər daxil olan data potensial zərərlidir
  Whitelist > Blacklist
  Type checking, format checking, business rules

Output Encoding:
  HTML context → htmlspecialchars()
  JSON context → json_encode() (avtomatik)
  SQL context  → prepared statements (never concatenation)
  Shell context → escapeshellarg()

CORS:
  Access-Control-Allow-Origin: https://app.example.com  (specific, not *)
  Credentials ilə * istifadə qadağandır
  Preflight cache: Access-Control-Max-Age: 3600

Security Headers:
  Content-Security-Policy: default-src 'self'
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  Referrer-Policy: strict-origin-when-cross-origin
```

---

## PHP İmplementasiyası

```php
<?php
// 1. API Key authentication middleware
namespace App\Security;

class ApiKeyAuthenticator
{
    public function __construct(
        private ApiKeyRepository $repository,
        private \Psr\Log\LoggerInterface $logger,
    ) {}

    public function authenticate(Request $request): ApiKey
    {
        $rawKey = $request->headers->get('X-API-Key')
            ?? throw new UnauthorizedException("API key tələb olunur");

        // Prefix yoxla
        if (!str_starts_with($rawKey, 'sk_')) {
            throw new UnauthorizedException("Yanlış key formatı");
        }

        // Hash ilə axtar (plain text saxlanmır)
        $keyHash = hash('sha256', $rawKey);
        $apiKey  = $this->repository->findByHash($keyHash)
            ?? throw new UnauthorizedException("Yanlış API key");

        if ($apiKey->isRevoked()) {
            $this->logger->warning('Revoked key istifadə cəhdi', [
                'key_id' => $apiKey->getId(),
                'ip'     => $request->getClientIp(),
            ]);
            throw new UnauthorizedException("Key ləğv edilib");
        }

        if ($apiKey->isExpired()) {
            throw new UnauthorizedException("Key müddəti bitmişdir");
        }

        return $apiKey;
    }
}
```

```php
<?php
// 2. Token Bucket Rate Limiter (Redis)
class TokenBucketRateLimiter
{
    public function __construct(
        private \Redis $redis,
        private int    $capacity  = 100, // max tokens
        private int    $refillRate = 10, // tokens per second
    ) {}

    public function consume(string $key, int $tokens = 1): RateLimitResult
    {
        $bucketKey = "rate_limit:{$key}";

        // Lua script — atomic operation
        $script = <<<LUA
        local key        = KEYS[1]
        local capacity   = tonumber(ARGV[1])
        local refillRate = tonumber(ARGV[2])
        local tokens     = tonumber(ARGV[3])
        local now        = tonumber(ARGV[4])

        local bucket     = redis.call('HMGET', key, 'tokens', 'last_refill')
        local current    = tonumber(bucket[1]) or capacity
        local lastRefill = tonumber(bucket[2]) or now

        -- Token əlavə et
        local elapsed = now - lastRefill
        current = math.min(capacity, current + elapsed * refillRate)

        if current >= tokens then
            current = current - tokens
            redis.call('HMSET', key, 'tokens', current, 'last_refill', now)
            redis.call('EXPIRE', key, 3600)
            return {1, current}  -- allowed
        else
            redis.call('HMSET', key, 'tokens', current, 'last_refill', now)
            redis.call('EXPIRE', key, 3600)
            return {0, current}  -- denied
        end
        LUA;

        [$allowed, $remaining] = $this->redis->eval(
            $script,
            [$bucketKey, $this->capacity, $this->refillRate, $tokens, microtime(true)],
            1
        );

        return new RateLimitResult(
            allowed:   (bool) $allowed,
            remaining: (int) $remaining,
            limit:     $this->capacity,
        );
    }
}
```

```php
<?php
// 3. Security Headers middleware
class SecurityHeadersMiddleware
{
    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self'; object-src 'none'"
        );

        // Server məlumatını gizlə
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
```

---

## İntervyu Sualları

- API Key-i plain text saxlamaq niyə yanlışdır?
- Token Bucket ilə Fixed Window rate limiting fərqi nədir?
- mTLS nə zaman istifadə edilir?
- CORS `*` origin ilə credentials istifadə etmək niyə mümkün deyil?
- JWT-nin qısa ömürlü olması üçün trade-off nədir?
- API key leaked olduqda düzgün addımlar nədir?
