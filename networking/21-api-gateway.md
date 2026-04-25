# API Gateway (Middle)

## İcmal

API Gateway microservices arxitekturasında bütün API request-lərinin keçdiyi tək giriş nöqtəsidir (single entry point). Routing, authentication, rate limiting, request transformation, monitoring kimi cross-cutting concern-ləri mərkəzləşdirir. Client bir neçə microservice yerinə yalnız gateway ilə danışır.

```
API Gateway olmadan:
  Client --> User Service     (auth check lazım)
  Client --> Order Service    (auth check lazım)
  Client --> Payment Service  (auth check lazım)
  (Hər service özünü qorumalıdır, client hər servisi bilməlidir)

API Gateway ilə:
  Client --> [API Gateway] --> User Service
                           --> Order Service
                           --> Payment Service
  (Auth bir yerdə, client yalnız gateway-i bilir)
```

## Niyə Vacibdir

Microservice arxitekturasında hər servis öz auth, rate limiting, logging mexanizmini implement etsəydi, bu code duplication, inconsistency və maintenance yükü yaradardı. API Gateway bu concern-ləri bir yerdə mərkəzləşdirir. Bundan əlavə, client-ə bir neçə servisi birləşdirmiş (aggregated) response qaytarmaq mümkün olur — client bir request edir, gateway paralel olaraq bir neçə servisdən data toplayıb birləşdirir.

## Əsas Anlayışlar

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
   Single gateway, bütün request-lər burdan keçir.
   Sadədir, amma SPOF (Single Point of Failure) riski.

2. Backend for Frontend (BFF):
   Hər client tipi üçün ayrı gateway.

   Mobile App  --> [Mobile BFF]  --> Services
   Web App     --> [Web BFF]     --> Services
   3rd Party   --> [Public API]  --> Services

3. API Composition:
   Gateway bir neçə service-dən data toplayır, birləşdirir.

   GET /api/dashboard -->
     Gateway paralel olaraq:
       User Service -> user data
       Order Service -> recent orders
       Analytics Service -> stats
     Birləşdirib tək response qaytarır.
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
Laravel      - Custom gateway with middleware (sadə use case-lər üçün)
```

## Praktik Baxış

**Üstünlüklər:**
- Cross-cutting concern-ləri bir yerdə toplayır (DRY)
- Client-ə sadə, unified API interface
- Aggregation ilə N+1 problem-i azaltmaq
- Centralized logging və monitoring

**Trade-off-lar:**
- SPOF riski — gateway down olsa bütün API-lər əlçatmaz olur (HA qurmaq lazım)
- Əlavə latency — hər request gateway-dən keçir
- Gateway-in özü bottleneck ola bilər

**Nə vaxt istifadə edilməməlidir:**
- Monolitik tətbiqdə gateway əlavə overhead yaradır
- Çox sadə, bir-iki servisdən ibarət sistemlər üçün reverse proxy bəs edir

**Anti-pattern-lər:**
- Gateway-də business logic yerləşdirmək (routing + transformation olmalıdır, biznes logikası deyil)
- Stateful gateway — scale etmək çətin olur
- Timeout qoymadan backend-ə proxy etmək
- Request ID olmadan logging — request-i trace etmək mümkün olmur

## Nümunələr

### Ümumi Nümunə

Laravel bir neçə microservice üçün sadə gateway rolunu oynaya bilər: Sanctum ilə auth, throttle middleware ilə rate limiting, `Http::pool()` ilə parallel aggregation. Böyük sistemlər üçün Kong, Traefik kimi dedicated gateway-lər tövsiyə olunur.

### Kod Nümunəsi

**Laravel as API Gateway — Routes:**

```php
// routes/api.php
use App\Http\Controllers\Gateway\GatewayController;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::any('users/{path?}', [GatewayController::class, 'proxyToUserService'])
        ->where('path', '.*');

    Route::any('orders/{path?}', [GatewayController::class, 'proxyToOrderService'])
        ->where('path', '.*');

    Route::any('payments/{path?}', [GatewayController::class, 'proxyToPaymentService'])
        ->where('path', '.*')
        ->middleware('can:manage-payments');

    Route::get('dashboard', [GatewayController::class, 'dashboard']);
});
```

**Gateway Controller (Proxy + Aggregation):**

```php
namespace App\Http\Controllers\Gateway;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GatewayController extends Controller
{
    private array $services = [
        'user'    => 'http://user-service:8001',
        'order'   => 'http://order-service:8002',
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

    private function proxy(Request $request, string $service, string $path): JsonResponse
    {
        $baseUrl = $this->services[$service];
        $url = "{$baseUrl}/api/{$path}";
        $requestId = (string) \Illuminate\Support\Str::uuid();

        $response = Http::withHeaders([
            'X-Request-ID'    => $requestId,
            'X-User-ID'       => (string) $request->user()->id,
            'X-User-Role'     => $request->user()->role,
            'X-Forwarded-For' => $request->ip(),
        ])
        ->timeout(30)
        ->send(
            $request->method(),
            $url . '?' . $request->getQueryString(),
            ['json' => $request->all()]
        );

        return response()->json(
            $response->json(),
            $response->status()
        )->header('X-Request-ID', $requestId);
    }

    /**
     * Dashboard — bir neçə service-dən data topla (aggregation)
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
            'user'          => $responses['user']->json(),
            'recent_orders' => $responses['orders']->json(),
            'stats'         => $responses['stats']->json(),
        ]);
    }
}
```

**Service Registry:**

```php
namespace App\Services\Gateway;

use Illuminate\Support\Facades\Cache;

class ServiceRegistry
{
    public function getServiceUrl(string $service): string
    {
        $url = config("services.gateway.{$service}.url");
        if ($url) return $url;

        // Service discovery (Consul, etcd) — cache ilə
        return Cache::remember("service:url:{$service}", 30, function () use ($service) {
            $response = Http::get("http://consul:8500/v1/catalog/service/{$service}");
            $services = $response->json();

            if (empty($services)) {
                throw new \RuntimeException("Service not found: {$service}");
            }

            $instance = $services[array_rand($services)];
            return "http://{$instance['ServiceAddress']}:{$instance['ServicePort']}";
        });
    }
}
```

**Circuit Breaker Pattern:**

```php
namespace App\Services\Gateway;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private const STATE_CLOSED    = 'closed';
    private const STATE_OPEN      = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function call(string $service, callable $request): mixed
    {
        $state = $this->getState($service);

        if ($state === self::STATE_OPEN) {
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
}
```

**API Versioning in Gateway:**

```php
Route::prefix('v1')->group(function () {
    Route::any('users/{path?}', function (Request $request, $path = '') {
        return proxy($request, 'user', "v1/users/{$path}");
    })->where('path', '.*');
});

Route::prefix('v2')->group(function () {
    Route::any('users/{path?}', function (Request $request, $path = '') {
        $response = proxy($request, 'user', "v2/users/{$path}");
        return transformV2Response($response);
    })->where('path', '.*');
});
```

## Praktik Tapşırıqlar

1. **Laravel gateway implement etmək:** User Service, Order Service üçün proxy controller yazın. Hər request-ə `X-Request-ID` header əlavə edin. Postman ilə `/api/v1/users/1` endpoint-ini test edib backend service-ə düzgün header-lərin çatdığını yoxlayın.

2. **Aggregation endpoint:** `/api/v1/dashboard` endpoint-i qurun. `Http::pool()` ilə paralel olaraq User, Order, Stats service-lərindən data toplayın. Sequential vs parallel sorğunun latency fərqini ölçün.

3. **Circuit breaker:** `CircuitBreaker` class-ını gateway controller-ə inteqrasiya edin. Backend servisi dayandırın, 5 uğursuz request-dən sonra circuit-in OPEN vəziyyətinə keçdiyini yoxlayın.

4. **Service discovery (sadə):** Sabit URL-lər əvəzinə `config('services.gateway.user.url')` istifadə edin. `.env` faylında URL-ləri dəyişib gateway-i restart etmədən fərqli backend-ə yönləndirib yoxlayın.

5. **Rate limiting per service:** Payment endpoint-i üçün daha sərt rate limit qoyun (`throttle:10,1`), adi API endpoint-lər üçün `throttle:100,1` istifadə edin. 429 response-da `Retry-After` header-inin gəldiyini yoxlayın.

## Əlaqəli Mövzular

- [Reverse Proxy](19-reverse-proxy.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [API Versioning](22-api-versioning.md)
- [Load Balancing](18-load-balancing.md)
- [OAuth2](14-oauth2.md)
