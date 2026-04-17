# API Gateway

## Nədir? (What is it?)

API Gateway microservices arxitekturasinda butun API request-lerinin kecdiyi tek giris noqtesidir (single entry point). Routing, authentication, rate limiting, request transformation, monitoring kimi cross-cutting concerns-i merkezlesdirir. Client bir nece microservice yerine yalniz gateway ile danisir.

```
API Gateway olmadan:
  Client --> User Service     (auth check lazim)
  Client --> Order Service    (auth check lazim)
  Client --> Payment Service  (auth check lazim)
  (Her service ozunu qorumalıdır, client her servisi bilmelidir)

API Gateway ile:
  Client --> [API Gateway] --> User Service
                           --> Order Service
                           --> Payment Service
  (Auth bir yerde, client yalniz gateway-i bilir)
```

## Necə İşləyir? (How does it work?)

### API Gateway Architecture

```
                    ┌─────────────────────────────────┐
                    │          API Gateway              │
                    │                                   │
  Clients ──────>  │  1. Rate Limiting                 │
                    │  2. Authentication (JWT verify)   │
                    │  3. Authorization (scope check)   │
                    │  4. Request Validation            │
                    │  5. Request Transformation        │
                    │  6. Routing                       │
                    │  7. Load Balancing                │
                    │  8. Response Transformation       │
                    │  9. Caching                       │
                    │  10. Logging & Monitoring         │
                    │                                   │
                    └──────┬──────┬──────┬─────────────┘
                           │      │      │
                    ┌──────┴┐ ┌───┴───┐ ┌┴──────────┐
                    │ User  │ │ Order │ │ Payment   │
                    │Service│ │Service│ │ Service   │
                    └───────┘ └───────┘ └───────────┘
```

### Gateway Patterns

```
1. API Gateway Pattern:
   Single gateway, butun request-ler burdan kecir.
   Sadedir, amma SPOF (Single Point of Failure) riski.

2. Backend for Frontend (BFF):
   Her client tipi ucun ayri gateway.
   
   Mobile App  --> [Mobile BFF]  --> Services
   Web App     --> [Web BFF]     --> Services
   3rd Party   --> [Public API]  --> Services

3. API Composition:
   Gateway bir nece service-den data toplayir, birlesdirir.
   
   GET /api/dashboard -->
     Gateway parallel olaraq:
       User Service -> user data
       Order Service -> recent orders
       Analytics Service -> stats
     Birlesdirip tek response qaytarir.
```

### Request/Response Transformation

```
Client Request:                    Backend Service Request:
GET /api/v2/users/42        -->    GET /users/42
Authorization: Bearer xxx          X-User-ID: extracted-from-jwt
Accept: application/json           X-Request-ID: uuid-generated
                                   X-Forwarded-For: client-ip

Backend Response:                  Gateway Response:
{"user_id": 42,              -->   {"data": {
 "user_name": "Orkhan"}             "id": 42,
                                     "name": "Orkhan"
                                   },
                                   "meta": {"version": "v2"}}
```

## Əsas Konseptlər (Key Concepts)

### API Gateway vs Reverse Proxy vs Load Balancer

```
+-------------------+-------------------+------------------+
| Feature           | Reverse Proxy     | API Gateway      |
+-------------------+-------------------+------------------+
| Primary purpose   | Request forward   | API management   |
| Auth              | Basic             | Advanced (JWT)   |
| Rate limiting     | Basic             | Per-user/key     |
| Transformation    | Limited           | Request/Response |
| API versioning    | No                | Yes              |
| Analytics         | Basic logs        | API analytics    |
| Developer portal  | No                | Yes              |
| Service discovery | No                | Yes              |
+-------------------+-------------------+------------------+
```

### Popular API Gateway Solutions

```
Kong         - Open source, Lua/Nginx based, plugin ecosystem
AWS API Gateway - Managed, Lambda integration
Traefik      - Docker/K8s native, auto-discovery
Envoy        - Service mesh, L7 proxy
Tyk          - Open source, Go based
KrakenD      - High performance, stateless
Laravel      - Custom gateway with middleware (sade use case-ler ucun)
```

## PHP/Laravel ilə İstifadə

### Laravel as API Gateway

```php
// routes/api.php - Gateway routing
use App\Http\Controllers\Gateway\GatewayController;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // User service
    Route::any('users/{path?}', [GatewayController::class, 'proxyToUserService'])
        ->where('path', '.*');

    // Order service
    Route::any('orders/{path?}', [GatewayController::class, 'proxyToOrderService'])
        ->where('path', '.*');

    // Payment service
    Route::any('payments/{path?}', [GatewayController::class, 'proxyToPaymentService'])
        ->where('path', '.*')
        ->middleware('can:manage-payments');

    // Aggregation endpoint
    Route::get('dashboard', [GatewayController::class, 'dashboard']);
});
```

### Gateway Controller (Proxy)

```php
namespace App\Http\Controllers\Gateway;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GatewayController extends Controller
{
    private array $services = [
        'user' => 'http://user-service:8001',
        'order' => 'http://order-service:8002',
        'payment' => 'http://payment-service:8003',
    ];

    public function proxyToUserService(Request $request, ?string $path = ''): JsonResponse
    {
        return $this->proxy($request, 'user', "users/{$path}");
    }

    public function proxyToOrderService(Request $request, ?string $path = ''): JsonResponse
    {
        return $this->proxy($request, 'order', "orders/{$path}");
    }

    public function proxyToPaymentService(Request $request, ?string $path = ''): JsonResponse
    {
        return $this->proxy($request, 'payment', "payments/{$path}");
    }

    /**
     * Request-i backend service-e proxy et
     */
    private function proxy(Request $request, string $service, string $path): JsonResponse
    {
        $baseUrl = $this->services[$service];
        $url = "{$baseUrl}/api/{$path}";
        $requestId = (string) \Illuminate\Support\Str::uuid();

        $response = Http::withHeaders([
            'X-Request-ID' => $requestId,
            'X-User-ID' => (string) $request->user()->id,
            'X-User-Role' => $request->user()->role,
            'X-Forwarded-For' => $request->ip(),
        ])
        ->timeout(30)
        ->send(
            $request->method(),
            $url . '?' . $request->getQueryString(),
            [
                'json' => $request->all(),
            ]
        );

        return response()->json(
            $response->json(),
            $response->status()
        )->header('X-Request-ID', $requestId);
    }

    /**
     * Dashboard - bir nece service-den data topla (aggregation)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Parallel requests
        $responses = Http::pool(fn ($pool) => [
            $pool->as('user')
                ->get("{$this->services['user']}/api/users/{$userId}"),
            $pool->as('orders')
                ->get("{$this->services['order']}/api/users/{$userId}/orders/recent"),
            $pool->as('stats')
                ->get("{$this->services['order']}/api/users/{$userId}/stats"),
        ]);

        return response()->json([
            'user' => $responses['user']->json(),
            'recent_orders' => $responses['orders']->json(),
            'stats' => $responses['stats']->json(),
        ]);
    }
}
```

### Service Registry

```php
namespace App\Services\Gateway;

use Illuminate\Support\Facades\Cache;

class ServiceRegistry
{
    /**
     * Service URL-ini config ve ya service discovery-den al
     */
    public function getServiceUrl(string $service): string
    {
        // Config-den
        $url = config("services.gateway.{$service}.url");
        if ($url) return $url;

        // Service discovery (Consul, etcd) - cache ile
        return Cache::remember("service:url:{$service}", 30, function () use ($service) {
            // Consul-dan service URL al
            $response = Http::get("http://consul:8500/v1/catalog/service/{$service}");
            $services = $response->json();

            if (empty($services)) {
                throw new \RuntimeException("Service not found: {$service}");
            }

            $instance = $services[array_rand($services)]; // Random instance
            return "http://{$instance['ServiceAddress']}:{$instance['ServicePort']}";
        });
    }
}
```

### Circuit Breaker Pattern

```php
namespace App\Services\Gateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';      // Normal - request-ler kecir
    private const STATE_OPEN = 'open';          // Xeta coxdur - request-ler bloklenir
    private const STATE_HALF_OPEN = 'half_open'; // Test - bir request burax

    public function call(string $service, callable $request): mixed
    {
        $state = $this->getState($service);

        if ($state === self::STATE_OPEN) {
            // Cooldown bitibmi yoxla
            if ($this->cooldownExpired($service)) {
                $this->setState($service, self::STATE_HALF_OPEN);
            } else {
                throw new ServiceUnavailableException("{$service} is currently unavailable");
            }
        }

        try {
            $result = $request();
            $this->recordSuccess($service);
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure($service);

            $failures = Cache::increment("circuit:{$service}:failures");
            if ($failures >= 5) {
                $this->setState($service, self::STATE_OPEN);
                Cache::put("circuit:{$service}:open_at", now(), 60);
            }

            throw $e;
        }
    }

    private function getState(string $service): string
    {
        return Cache::get("circuit:{$service}:state", self::STATE_CLOSED);
    }

    private function setState(string $service, string $state): void
    {
        Cache::put("circuit:{$service}:state", $state, 300);
        if ($state === self::STATE_CLOSED) {
            Cache::forget("circuit:{$service}:failures");
        }
    }

    private function cooldownExpired(string $service): bool
    {
        $openAt = Cache::get("circuit:{$service}:open_at");
        return $openAt && $openAt->addSeconds(30)->isPast();
    }

    private function recordSuccess(string $service): void
    {
        if ($this->getState($service) === self::STATE_HALF_OPEN) {
            $this->setState($service, self::STATE_CLOSED);
        }
    }

    private function recordFailure(string $service): void {}
}
```

### API Versioning in Gateway

```php
// Gateway seviyyesinde versioning

Route::prefix('v1')->group(function () {
    Route::any('users/{path?}', function (Request $request, $path = '') {
        // v1 -> User Service v1 endpoint
        return proxy($request, 'user', "v1/users/{$path}");
    })->where('path', '.*');
});

Route::prefix('v2')->group(function () {
    Route::any('users/{path?}', function (Request $request, $path = '') {
        // v2 -> Yeni User Service ve ya transform olunmus response
        $response = proxy($request, 'user', "v2/users/{$path}");

        // Response transformation (v1 -> v2 format)
        return transformV2Response($response);
    })->where('path', '.*');
});
```

## Interview Sualları

### 1. API Gateway nedir ve niye lazimdir?
**Cavab:** Microservices ucun single entry point-dir. Authentication, rate limiting, routing, monitoring kimi cross-cutting concerns-i merkezlesdirir. Client bir cox service yerine yalniz gateway ile danisir.

### 2. API Gateway ve reverse proxy arasinda ferq nedir?
**Cavab:** Reverse proxy request forwarding, load balancing, SSL termination edir. API Gateway bunlarin hamisi + API-specific funksiyalar: auth, rate limiting per user/key, request/response transformation, API versioning, developer portal, analytics.

### 3. Backend for Frontend (BFF) pattern nedir?
**Cavab:** Her client tipi (mobile, web, 3rd party) ucun ayri gateway. Mobile app az data ister, web app daha cox. Her BFF oz client-inin ehtiyaclarina uygun response yaradir. Over-fetching/under-fetching azalir.

### 4. Circuit breaker pattern nedir?
**Cavab:** Backend service down olanda gateway-in request gondermesini dayandirmasi. 3 state: Closed (normal), Open (blok, service down), Half-Open (test edir). Service recover olunca Open->Half-Open->Closed kecidir. Cascading failure-in qarsisini alir.

### 5. API composition nedir?
**Cavab:** Gateway-in bir nece microservice-den data toplayib tek response qaytarmasidir. `GET /dashboard` -> parallel olaraq user, orders, stats service-lerden data alir ve birlesdirir. Client tek request edir, gateway composition edir.

### 6. Gateway-in SPOF (Single Point of Failure) riski nece hell olunur?
**Cavab:** Gateway-in ozunu HA qurun: multiple instances + load balancer, auto-scaling, health checks, geographic distribution. Gateway stateless olmalidir ki asan scale olunsun.

## Best Practices

1. **Stateless gateway** - State saxlamayin, asan scale ucun
2. **Circuit breaker** - Backend failure-i izolyasiya edin
3. **Timeout teyin edin** - Her backend call ucun timeout
4. **Request ID** - Her request-e unique ID verin (tracing)
5. **Parallel requests** - Aggregation zamani parallel call edin
6. **Cache** - Cacheable response-lari gateway-de cache edin
7. **Rate limiting** - Per-user, per-API-key, per-endpoint
8. **Health checks** - Backend servisleri muntezer yoxlayin
9. **Logging/monitoring** - Butun request-leri loglayin
10. **Versioning** - Gateway seviyyesinde API versioning
