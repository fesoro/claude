# API Gateway

## Nədir? (What is it?)

API Gateway bütün API request-lərinin keçdiyi tək giriş nöqtəsidir (single entry point).
Client-lər birbaşa microservice-lərə müraciət etmir, əvəzinə API Gateway vasitəsilə keçir.
Gateway routing, authentication, rate limiting, logging və digər cross-cutting concern-ları
bir yerdə idarə edir.

```
Mobile App ──┐
Web App ─────┤──> [API Gateway] ──┬──> User Service
3rd Party ───┘         |          ├──> Order Service
                  - Auth           ├──> Payment Service
                  - Rate Limit     └──> Notification Service
                  - Logging
                  - Transform
```

## Əsas Konseptlər (Key Concepts)

### API Gateway-in Funksiyaları

**1. Request Routing**
Gələn request-i uyğun backend service-ə yönləndirir.

```
GET /api/users/123      -> User Service (port 8001)
POST /api/orders        -> Order Service (port 8002)
GET /api/products       -> Product Service (port 8003)
```

**2. Authentication & Authorization**
Hər request-də token/key yoxlanılır, backend service-lər auth ilə məşğul olmur.

```
Client -> API Gateway -> JWT valid? -> YES -> Forward to service
                                    -> NO  -> 401 Unauthorized
```

**3. Rate Limiting**
Hər client/API key üçün request limiti tətbiq edir.

```
Free plan:    100 requests/saat
Pro plan:     10,000 requests/saat
Enterprise:   unlimited
```

**4. Request/Response Transformation**
Request və ya response-u dəyişdirmək: header əlavə etmək, format çevirmək, field-ları filter etmək.

```
Client göndərir: XML
Gateway çevirir: JSON -> Backend service
Backend qaytarır: JSON with internal fields
Gateway filter edir: JSON with public fields only -> Client
```

**5. Load Balancing**
Eyni service-in bir neçə instance-ı arasında trafiki paylaşdırır.

**6. Caching**
Tez-tez istənən response-ları cache edir, backend yükünü azaldır.

**7. Circuit Breaking**
Backend service down olduqda, request-ləri dayandırır, fallback qaytarır.

**8. Logging & Monitoring**
Bütün API traffic-ini bir yerdə log edir, metrics toplayır.

**9. API Versioning**
```
/api/v1/users -> Legacy service
/api/v2/users -> New service
```

**10. Request Aggregation (BFF - Backend for Frontend)**
Bir client request-i ilə bir neçə service-ə müraciət edib nəticəni birləşdirir.

```
GET /api/dashboard
  -> User Service: user profile
  -> Order Service: recent orders
  -> Notification Service: unread count
  <- Combined response to client
```

### API Gateway Patternləri

**Edge Gateway:** Bütün external traffic üçün tək gateway
**Internal Gateway:** Microservice-lər arası traffic üçün
**BFF (Backend for Frontend):** Hər client tipi üçün ayrı gateway

```
Mobile App  -> [Mobile BFF Gateway]  -> Services
Web App     -> [Web BFF Gateway]     -> Services
Partner API -> [Partner Gateway]     -> Services
```

## Arxitektura (Architecture)

### Kong API Gateway

Kong Nginx üzərində qurulmuş, plugin-based API gateway-dir.

```yaml
# docker-compose.yml
version: '3'
services:
  kong:
    image: kong:3.4
    environment:
      KONG_DATABASE: postgres
      KONG_PG_HOST: kong-db
      KONG_PROXY_LISTEN: 0.0.0.0:8000, 0.0.0.0:8443 ssl
      KONG_ADMIN_LISTEN: 0.0.0.0:8001
    ports:
      - "8000:8000"
      - "8443:8443"
      - "8001:8001"

  kong-db:
    image: postgres:15
    environment:
      POSTGRES_DB: kong
      POSTGRES_USER: kong
      POSTGRES_PASSWORD: kong
```

```bash
# Service əlavə et
curl -X POST http://localhost:8001/services \
  --data name=user-service \
  --data url=http://user-service:8080

# Route əlavə et
curl -X POST http://localhost:8001/services/user-service/routes \
  --data "paths[]=/api/users" \
  --data "methods[]=GET" \
  --data "methods[]=POST"

# Rate limiting plugin
curl -X POST http://localhost:8001/services/user-service/plugins \
  --data name=rate-limiting \
  --data config.minute=100 \
  --data config.policy=redis \
  --data config.redis_host=redis

# JWT authentication plugin
curl -X POST http://localhost:8001/services/user-service/plugins \
  --data name=jwt

# Request transformer
curl -X POST http://localhost:8001/services/user-service/plugins \
  --data name=request-transformer \
  --data "config.add.headers=X-Request-ID:$(uuidgen)"
```

### Nginx API Gateway Konfiqurasiyası

```nginx
# /etc/nginx/conf.d/api_gateway.conf

# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;
limit_req_zone $http_x_api_key zone=api_key_limit:10m rate=100r/s;

# Upstreams
upstream user_service {
    server 10.0.1.1:8001;
    server 10.0.1.2:8001;
}

upstream order_service {
    server 10.0.2.1:8002;
    server 10.0.2.2:8002;
}

# Cache
proxy_cache_path /tmp/nginx_cache levels=1:2
    keys_zone=api_cache:10m max_size=1g inactive=60m;

server {
    listen 443 ssl;
    server_name api.example.com;

    ssl_certificate /etc/ssl/certs/api.pem;
    ssl_certificate_key /etc/ssl/private/api.key;

    # Global rate limit
    limit_req zone=api_limit burst=20 nodelay;

    # JWT validation via auth subrequest
    location = /auth {
        internal;
        proxy_pass http://auth_service/validate;
        proxy_pass_request_body off;
        proxy_set_header Content-Length "";
        proxy_set_header X-Original-URI $request_uri;
        proxy_set_header Authorization $http_authorization;
    }

    # User service
    location /api/v1/users {
        auth_request /auth;
        auth_request_set $user_id $upstream_http_x_user_id;

        proxy_pass http://user_service;
        proxy_set_header X-User-ID $user_id;
        proxy_set_header X-Request-ID $request_id;
    }

    # Order service
    location /api/v1/orders {
        auth_request /auth;
        proxy_pass http://order_service;
    }

    # Public endpoints (no auth)
    location /api/v1/products {
        proxy_cache api_cache;
        proxy_cache_valid 200 5m;
        proxy_pass http://product_service;
    }

    # Health check
    location /health {
        access_log off;
        return 200 '{"status":"ok"}';
        add_header Content-Type application/json;
    }
}
```

### AWS API Gateway

```
Client -> CloudFront (CDN) -> API Gateway -> Lambda / ECS / EC2

Features:
- REST API və HTTP API tipləri
- Lambda authorizers (custom auth logic)
- Request/Response mapping templates
- Usage plans + API keys
- Throttling: account-level və method-level
- Caching: 0.5GB - 237GB
- WAF integration
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel API Gateway Pattern

```php
// routes/api.php - Gateway routing
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // User service proxy
    Route::any('/users/{path?}', [GatewayController::class, 'proxyToUsers'])
        ->where('path', '.*');

    // Order service proxy
    Route::any('/orders/{path?}', [GatewayController::class, 'proxyToOrders'])
        ->where('path', '.*');
});
```

```php
// app/Http/Controllers/GatewayController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GatewayController extends Controller
{
    private array $serviceMap = [
        'users' => 'http://user-service:8001',
        'orders' => 'http://order-service:8002',
        'products' => 'http://product-service:8003',
    ];

    public function proxyToUsers(Request $request, ?string $path = '')
    {
        return $this->proxy($request, 'users', $path);
    }

    public function proxyToOrders(Request $request, ?string $path = '')
    {
        return $this->proxy($request, 'orders', $path);
    }

    private function proxy(Request $request, string $service, string $path)
    {
        $baseUrl = $this->serviceMap[$service];
        $url = "{$baseUrl}/{$path}";

        $response = Http::withHeaders([
                'X-User-ID' => $request->user()->id,
                'X-Request-ID' => $request->header('X-Request-ID', uniqid()),
                'X-Forwarded-For' => $request->ip(),
            ])
            ->timeout(10)
            ->retry(2, 100)
            ->{strtolower($request->method())}($url, $request->all());

        return response($response->body(), $response->status())
            ->withHeaders($response->headers());
    }
}
```

### API Gateway Middleware Stack

```php
// app/Http/Middleware/ApiKeyAuthentication.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $client = \DB::table('api_clients')
            ->where('api_key', hash('sha256', $apiKey))
            ->where('is_active', true)
            ->first();

        if (!$client) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $request->merge(['api_client' => $client]);
        return $next($request);
    }
}
```

```php
// app/Http/Middleware/RequestLogger.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_');
        $request->headers->set('X-Request-ID', $requestId);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel('api')->info('API Request', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->status(),
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'api_client' => $request->get('api_client')?->name ?? 'unknown',
        ]);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Response-Time', $duration . 'ms');

        return $response;
    }
}
```

### Response Transformation

```php
// app/Http/Middleware/ApiVersionTransformer.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersionTransformer
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $version = $request->header('API-Version', 'v2');

        if ($version === 'v1' && $response->headers->get('Content-Type') === 'application/json') {
            $data = json_decode($response->getContent(), true);
            $transformed = $this->transformToV1($data);
            $response->setContent(json_encode($transformed));
        }

        return $response;
    }

    private function transformToV1(array $data): array
    {
        // V2 field adlarını V1-ə çevir
        if (isset($data['full_name'])) {
            $data['name'] = $data['full_name'];
            unset($data['full_name']);
        }
        return $data;
    }
}
```

## Real-World Nümunələr

**Netflix Zuul:** Custom API gateway - routing, filtering, monitoring. Hər saniyə milyonlarla
request handle edir. Dynamic routing rules, canary deployments dəstəkləyir.

**Amazon API Gateway:** Serverless API-lər üçün fully managed service. Lambda ilə entegrasiya,
auto-scaling, pay-per-request pricing modeli.

**Uber:** Custom Go-based API gateway. Mobile app üçün BFF pattern istifadə edir.
Request aggregation ilə bir mobile request-ə bir neçə service cavab verir.

**Stripe:** API versioning üçün öz gateway-lərini istifadə edirlər. Hər API version
ayrı transformation layer-dən keçir. Backward compatibility illərlə saxlanılır.

## Interview Sualları

**S: API Gateway niyə lazımdır?**
C: Cross-cutting concerns-ları (auth, rate limiting, logging) bir yerdə idarə etmək üçün.
Client-lərin backend service topology-sini bilməsinə ehtiyac yoxdur. Service-lər
dəyişdikdə client-lər təsirlənmir.

**S: API Gateway single point of failure ola bilərmi?**
C: Bəli, buna görə HA (High Availability) konfiqurasiya vacibdir. Multiple instances,
health checks, auto-scaling, multi-region deployment lazımdır. AWS API Gateway kimi
managed service-lər bunu avtomatik həll edir.

**S: BFF pattern nədir və nə vaxt istifadə olunur?**
C: Backend for Frontend - hər client tipi (mobile, web, IoT) üçün ayrı API Gateway.
Mobile app az data istəyir, web app daha çox. BFF hər client üçün optimal response
formalaşdırır.

**S: API versioning necə həll edilir?**
C: URL-based (/api/v1/), Header-based (API-Version: 2), Query param (?version=2).
URL-based ən sadə və geniş yayılmışdır. Gateway transformation layer ilə köhnə
version-ları yeni service-lərdə map edə bilər.

## Best Practices

1. **Gateway-i thin saxlayın** - Business logic gateway-ə qoymayın, yalnız routing və cross-cutting concerns
2. **Timeout-ları düzgün tənzimləyin** - Backend service-lərin response time-ına uyğun timeout
3. **Rate limiting mütləq olsun** - DDoS və abuse-dan qorunmaq üçün
4. **Request/Response logging** - Debug və audit üçün bütün traffic-i log edin
5. **Circuit breaker əlavə edin** - Backend down olduqda cascade failure-ı önləyin
6. **Caching istifadə edin** - GET request-lər üçün response caching backend yükünü azaldır
7. **API versioning strategiyası** - Əvvəldən versioning plan edin
8. **Health check endpoint** - Gateway-in özü və backend service-lər üçün ayrı health check
9. **Correlation ID** - Hər request-ə unique ID verin, bütün service-lər arasında izləyin
10. **Managed service düşünün** - Öz gateway-inizi yazmaq yerinə Kong, AWS API GW istifadə edin
