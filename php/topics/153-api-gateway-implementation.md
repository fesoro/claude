# System Design: API Gateway Implementation

## Mündəricat
1. [API Gateway nədir?](#api-gateway-nədir)
2. [Əsas Funksiyalar](#əsas-funksiyalar)
3. [Dizayn Qərarları](#dizayn-qərarları)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## API Gateway nədir?

```
API Gateway — microservice-lər üçün tək giriş nöqtəsi.
Client bütün servislərə birbaşa deyil, Gateway üzərindən çıxış əldə edir.

Həll etdiyi problemlər:
  ✓ Cross-cutting concerns mərkəzləşdirilir (auth, rate limit, logging)
  ✓ Client sadə bir endpoint bilir
  ✓ Service discovery client-dən gizlənir
  ✓ Protocol translation (REST → gRPC, REST → WebSocket)

API Gateway vs Load Balancer:
  Load Balancer: L4 (TCP) — traffic distribution
  API Gateway:   L7 (HTTP) — request routing + transformation

Mövcud həllər:
  Kong, AWS API Gateway, Azure API Management
  Nginx + Lua, Traefik, Envoy
  Custom (PHP/Go/Node.js)
```

---

## Əsas Funksiyalar

```
1. Authentication & Authorization:
   JWT validation, API key verification
   OAuth2 token introspection
   Services-ə yalnız doğrulanmış sorğular çatır

2. Rate Limiting:
   Global, per-user, per-API-key limitlər
   Throttling (429 Too Many Requests)

3. Request Routing:
   /api/orders/* → OrderService
   /api/users/*  → UserService
   Path rewriting, versioning

4. Load Balancing:
   Backend service-lər arasında paylaşdırma
   Health check → sağlam olmayan servisləri çıxart

5. Circuit Breaker:
   Backend fail olsa → gateway fallback qaytarır
   Cascade failure önlənir

6. Request/Response Transformation:
   Header əlavə et/sil
   Request body dəyişdir
   Protocol translate et

7. Caching:
   GET sorğularını cache et
   Backend yükünü azalt

8. Observability:
   Hər sorğu log, metric, trace
   Merkəzləşmiş monitoring
```

---

## Dizayn Qərarları

```
Sync vs Async:
  Gateway sync işləyir (request-response)
  Daxili async ola bilər (non-blocking I/O)

Plugin Architecture:
  Kong, AWS Gateway plugin-based
  Hər funksiya plugin (rate limit, auth, transform)
  Zəncir: Plugin1 → Plugin2 → Backend

Health Check:
  Active: Gateway periodically probe edir
  Passive: xətalı cavabdan anlamaq
  /health endpoint → 200 OK (healthy)

Service Discovery:
  Static config: hardcoded service URL-ləri
  Dynamic: Consul, Kubernetes Service Discovery
  Kong + Consul: dinamik routing

Bottleneck:
  Gateway SPOF ola bilər
  → Multiple Gateway instance
  → Stateless design (state-i Redis-ə)
  → Async middleware-lər
```

---

## PHP İmplementasiyası

```php
<?php
// Sadə PHP API Gateway (PSR-15 Middleware ilə)
namespace App\Gateway;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

// 1. Auth Middleware
class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private JwtValidator $validator) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            $claims = $this->validator->validate($token);
            $request = $request->withAttribute('auth_claims', $claims);
            return $handler->handle($request);
        } catch (InvalidTokenException $e) {
            return new JsonResponse(['error' => 'Invalid token'], 401);
        }
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
```

```php
<?php
// 2. Rate Limit Middleware
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private \Redis $redis,
        private int    $limit  = 100, // requests per window
        private int    $window = 60,  // seconds
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $claims = $request->getAttribute('auth_claims');
        $key    = "rate_limit:{$claims['sub']}";

        $current = $this->redis->incr($key);

        if ($current === 1) {
            $this->redis->expire($key, $this->window);
        }

        $remaining = max(0, $this->limit - $current);
        $reset     = $this->redis->ttl($key);

        if ($current > $this->limit) {
            return new JsonResponse(['error' => 'Rate limit exceeded'], 429, [
                'X-RateLimit-Limit'     => $this->limit,
                'X-RateLimit-Remaining' => 0,
                'Retry-After'           => $reset,
            ]);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit',     (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset',     (string) (time() + $reset));
    }
}
```

```php
<?php
// 3. Request Router — microservice-ə proxy
class ServiceRouter implements MiddlewareInterface
{
    private array $routes = [
        '/api/orders'   => 'http://order-service:8080',
        '/api/users'    => 'http://user-service:8080',
        '/api/payments' => 'http://payment-service:8080',
    ];

    public function __construct(
        private \GuzzleHttp\Client $httpClient,
        private CircuitBreaker     $circuitBreaker,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path    = $request->getUri()->getPath();
        $backend = $this->matchRoute($path);

        if ($backend === null) {
            return new JsonResponse(['error' => 'Route not found'], 404);
        }

        return $this->circuitBreaker->call($backend, function () use ($request, $backend, $path) {
            $backendUrl = $backend . $path;

            $response = $this->httpClient->request(
                $request->getMethod(),
                $backendUrl,
                [
                    'headers' => $this->forwardHeaders($request),
                    'body'    => $request->getBody(),
                    'timeout' => 10,
                ]
            );

            return new ProxyResponse($response);
        });
    }

    private function matchRoute(string $path): ?string
    {
        foreach ($this->routes as $prefix => $backend) {
            if (str_starts_with($path, $prefix)) {
                return $backend;
            }
        }
        return null;
    }

    private function forwardHeaders(ServerRequestInterface $request): array
    {
        $headers = [];
        foreach (['Accept', 'Content-Type', 'X-Correlation-ID'] as $header) {
            $value = $request->getHeaderLine($header);
            if ($value !== '') {
                $headers[$header] = $value;
            }
        }

        // Auth claims-i header kimi göndər (service-ə)
        $claims = $request->getAttribute('auth_claims');
        if ($claims) {
            $headers['X-User-Id']    = $claims['sub'];
            $headers['X-User-Roles'] = implode(',', $claims['roles'] ?? []);
        }

        return $headers;
    }
}
```

```php
<?php
// 4. Gateway Bootstrap — middleware pipeline
class GatewayKernel
{
    private array $middleware = [];

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => new class($middleware, $next) implements RequestHandlerInterface {
                public function __construct(private $middleware, private $next) {}
                public function handle(ServerRequestInterface $request): ResponseInterface {
                    return $this->middleware->process($request, $this->next);
                }
            },
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface {
                    return new JsonResponse(['error' => 'No handler'], 500);
                }
            }
        );

        return $pipeline->handle($request);
    }
}

// İstifadə:
$gateway = new GatewayKernel();
$gateway
    ->addMiddleware(new CorrelationIdMiddleware())
    ->addMiddleware(new RequestLoggingMiddleware($logger))
    ->addMiddleware(new JwtAuthMiddleware($jwtValidator))
    ->addMiddleware(new RateLimitMiddleware($redis))
    ->addMiddleware(new ServiceRouter($httpClient, $circuitBreaker));
```

---

## İntervyu Sualları

- API Gateway niyə microservice arxitekturasında vacibdir?
- Gateway özü SPOF ola bilər — necə qarşısını alırsınız?
- Auth Gateway-də yoxlanılmalıdır, yoxsa hər servisdə ayrıca?
- Rate limiting Gateway-də yoxsa service-lərdə?
- Circuit breaker Gateway-də necə işləyir?
- Kong vs custom Gateway — hər birinin use case-i nədir?
