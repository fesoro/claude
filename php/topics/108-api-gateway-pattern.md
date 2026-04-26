# API Gateway Pattern və BFF (Backend for Frontend) (Senior)

## Mündəricat
1. [API Gateway nədir?](#api-gateway-nədir)
2. [Məsuliyyətlər](#məsuliyyətlər)
3. [API Gateway vs Load Balancer vs Reverse Proxy](#api-gateway-vs-load-balancer-vs-reverse-proxy)
4. [BFF Pattern](#bff-pattern)
5. [Request Aggregation](#request-aggregation)
6. [Cross-cutting Concerns](#cross-cutting-concerns)
7. [PHP İmplementasiya Nümunəsi](#php-implementasiya-nümunəsi)
8. [Alətlər](#alətlər)
9. [Çatışmazlıqlar](#çatışmazlıqlar)
10. [İntervyu Sualları](#intervyu-sualları)

---

## API Gateway nədir?

API Gateway — bütün client sorğuları üçün **tək giriş nöqtəsi**. Client-lər servislərə birbaşa deyil, gateway vasitəsilə müraciət edir.

```
Birbaşa servis çağırışı (Gateway yoxdur):
                    ┌──────────────┐
Mobile App ────────►│ Order Service│
Mobile App ────────►│ User Service │
Mobile App ────────►│ Payment Svc  │
                    └──────────────┘
  → 3 fərqli URL, 3 fərqli auth, 3 fərqli rate limit

API Gateway ilə:
                    ┌─────────────┐    ┌──────────────┐
Mobile App ────────►│             │───►│ Order Service│
Web App    ────────►│  API Gateway│───►│ User Service │
3rd Party  ────────►│             │───►│ Payment Svc  │
                    └─────────────┘    └──────────────┘
  → 1 URL, 1 auth, 1 rate limit
```

---

## Məsuliyyətlər

```
┌─────────────────────────────────────────────────────────┐
│                     API Gateway                         │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │
│  │ Routing  │  │   Auth   │  │    Rate Limiting      │  │
│  │ /users/* │  │ JWT/API  │  │  100 req/min/user     │  │
│  │ /orders/*│  │   Key    │  │                       │  │
│  └──────────┘  └──────────┘  └──────────────────────┘  │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │
│  │  SSL     │  │ Request  │  │     Caching           │  │
│  │Termination│ │Aggregation│  │  GET /products cache  │  │
│  └──────────┘  └──────────┘  └──────────────────────┘  │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │
│  │ Logging  │  │ Tracing  │  │   Load Balancing      │  │
│  │& Metrics │  │(Trace ID)│  │  (round-robin etc.)   │  │
│  └──────────┘  └──────────┘  └──────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## API Gateway vs Load Balancer vs Reverse Proxy

```
┌──────────────────┬───────────────────┬──────────────────────┐
│                  │  Reverse Proxy    │  API Gateway         │
├──────────────────┼───────────────────┼──────────────────────┤
│ Məqsəd           │ Traffic forward   │ API idarəetməsi       │
│ Auth             │ ❌                │ ✅                    │
│ Rate Limiting    │ Əsas              │ Qabaqcıl, per-user    │
│ Request Transform│ ❌                │ ✅                    │
│ Aggregation      │ ❌                │ ✅                    │
│ Routing          │ Path-based        │ Content-aware         │
│ Nümunə           │ Nginx, HAProxy    │ Kong, AWS API GW      │
└──────────────────┴───────────────────┴──────────────────────┘

Load Balancer: Eyni servisdə yük paylaşması (L4 - TCP/UDP)
Reverse Proxy: HTTP-yə yönləndirmə (L7 - HTTP)  
API Gateway: L7 + API-specific features
```

---

## BFF Pattern

**Backend for Frontend** — hər frontend tipi üçün ayrı backend.

```
Problem: Ümumi API müxtəlif client-lərin ehtiyaclarını ödəyə bilmir

             ┌─────────────────┐
Mobile App ──►│  Shared API     │◄── Web App
             │  (hamı üçün)    │◄── IoT Device
             └─────────────────┘
  → Mobile çox data alır (batareya itkisi)
  → Web az data alır (çoxlu sorğu lazımdır)
  → IoT üçün format uyğun deyil

Həll: BFF
             ┌─────────────────┐
Mobile App ──►│  Mobile BFF     │
             └────────┬────────┘
                      │ aggregate + optimize
Web App    ──►┌────────▼────────┐     ┌──────────────┐
             │   Web BFF       │────►│  Microservices│
             └────────┬────────┘     └──────────────┘
                      │
IoT Device ──►┌────────▼────────┐
             │   IoT BFF        │
             └─────────────────┘
```

**BFF faydaları:**
- Hər client üçün optimized response
- Client-specific auth (mobile: device token, web: session)
- Performance: Mobile üçün az data, web üçün rich data
- Team autonomy: Mobile team öz BFF-ini idarə edir

*- Team autonomy: Mobile team öz BFF-ini idarə edir üçün kod nümunəsi:*
```php
// Mobile BFF — minimal data
class MobileOrderController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $order = $this->orderService->findById($id);
        
        // Mobile üçün minimal response
        return response()->json([
            'id'     => $order->id,
            'status' => $order->status,
            'total'  => $order->total,
            // Web-dəki kimi 50 sahə yox!
        ]);
    }
}

// Web BFF — ətraflı data
class WebOrderController extends Controller
{
    public function show(string $id): JsonResponse
    {
        // Paralel sorğularla ətraflı data topla
        [$order, $customer, $tracking] = array_map(
            fn($promise) => $promise->wait(),
            [
                $this->orderService->findByIdAsync($id),
                $this->customerService->findByOrderAsync($id),
                $this->trackingService->getStatusAsync($id),
            ]
        );
        
        return response()->json([
            'order'    => $order->toFullArray(),
            'customer' => $customer->toArray(),
            'tracking' => $tracking->toArray(),
            // Tam ətraflı response
        ]);
    }
}
```

---

## Request Aggregation

Gateway birdən çox backend sorğusunu birləşdirə bilər:

```
Client: GET /dashboard
  ↓
Gateway:
  → GET /users/123          (User Service)
  → GET /orders?user=123    (Order Service)
  → GET /notifications/123  (Notification Service)
  ↓
Gateway birləşdirir:
  → { user: {...}, orders: [...], notifications: [...] }
  ↓
Client: 1 sorğu ilə bütün data
```

*Client: 1 sorğu ilə bütün data üçün kod nümunəsi:*
```php
class DashboardAggregator
{
    public function aggregate(int $userId): array
    {
        // Paralel sorğular
        $promises = [
            'user'          => $this->httpClient->getAsync("/users/$userId"),
            'orders'        => $this->httpClient->getAsync("/orders?user=$userId"),
            'notifications' => $this->httpClient->getAsync("/notifications/$userId"),
        ];
        
        // Hamısını gözlə (parallel execution)
        $results = [];
        foreach ($promises as $key => $promise) {
            try {
                $results[$key] = json_decode($promise->wait()->getBody(), true);
            } catch (\Exception $e) {
                $results[$key] = null;  // Partial failure OK
                Log::warning("Aggregation failed for $key: {$e->getMessage()}");
            }
        }
        
        return $results;
    }
}
```

---

## Cross-cutting Concerns

*Cross-cutting Concerns üçün kod nümunəsi:*
```php
// Gateway middleware stack

class AuthenticationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token || !$this->jwtService->validate($token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $request->merge(['user' => $this->jwtService->decode($token)]);
        return $next($request);
    }
}

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = "rate_limit:{$request->user('id')}:{$request->path()}";
        $limit = 100;  // sorğu/dəqiqə
        
        $current = Cache::increment($key);
        if ($current === 1) {
            Cache::expire($key, 60);
        }
        
        if ($current > $limit) {
            return response()->json(
                ['error' => 'Too Many Requests'],
                429,
                ['Retry-After' => Cache::ttl($key)]
            );
        }
        
        return $next($request);
    }
}

class RequestTracingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Trace ID yarat və ya yönləndir
        $traceId = $request->header('X-Trace-Id', Str::uuid()->toString());
        $request->headers->set('X-Trace-Id', $traceId);
        
        $response = $next($request);
        
        $response->headers->set('X-Trace-Id', $traceId);
        return $response;
    }
}
```

---

## PHP İmplementasiya Nümunəsi

*PHP İmplementasiya Nümunəsi üçün kod nümunəsi:*
```php
// Sadə gateway layer Laravel-də

class GatewayRouter
{
    private array $routes = [
        '/api/orders'   => 'http://order-service:8001',
        '/api/users'    => 'http://user-service:8002',
        '/api/payments' => 'http://payment-service:8003',
    ];
    
    public function forward(Request $request): Response
    {
        $targetBase = $this->findRoute($request->path());
        
        if (!$targetBase) {
            return response()->json(['error' => 'Not Found'], 404);
        }
        
        $targetUrl = $targetBase . '/' . $request->path();
        
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Internal-Token' => config('gateway.internal_token'),
                    'X-User-Id' => $request->user('id'),
                    'X-Trace-Id' => $request->header('X-Trace-Id'),
                ])
                ->send($request->method(), $targetUrl, [
                    'json' => $request->all(),
                    'query' => $request->query(),
                ]);
            
            return response(
                $response->body(),
                $response->status(),
                $response->headers()
            );
        } catch (ConnectionException $e) {
            return response()->json(['error' => 'Service Unavailable'], 503);
        } catch (RequestException $e) {
            return response()->json(['error' => 'Bad Gateway'], 502);
        }
    }
    
    private function findRoute(string $path): ?string
    {
        foreach ($this->routes as $prefix => $target) {
            if (str_starts_with("/$path", $prefix)) {
                return $target;
            }
        }
        return null;
    }
}
```

---

## Service Discovery ilə inteqrasiya

API Gateway backend servis URL-lərini hardcode etmək əvəzinə Service Discovery-dən dinamik öyrənə bilər:

```
Static routing (sadə, amma kövrək):
  Gateway: /api/orders → http://order-service:8001

Dynamic (Service Discovery ilə):
  Consul/Kubernetes DNS:
    Gateway: /api/orders → dns://order-service
    order-service DNS → [10.0.1.5:8001, 10.0.1.6:8001]
    Gateway yükü paylaşır, yeni pod-lar avtomatik tapılır
```

**Rate Limiting alqoritmləri:**

```
Token Bucket (ən geniş yayılmış):
  - Vedrə müəyyən tezlikdə token dolar
  - Hər sorğu bir token xərclər
  - Burst imkan verir (vedrə doluysa anlıq çoxlu sorğu)

Sliding Window:
  - Son N saniyədəki sorğu sayı
  - Token Bucket-dən daha dəqiq, daha az burst
  
Fixed Window:
  - Hər zaman pəncərəsinin əvvəlindən say sıfırlanır
  - Pəncərə kenarında burst problemi var
```

## Alətlər

| Alət | Tip | Xüsusiyyətlər |
|------|-----|---------------|
| Kong | Open Source/Enterprise | Plugin ekosistemi, Kubernetes native |
| AWS API Gateway | Cloud | Serverless, Lambda inteqrasiyası |
| Nginx | Reverse Proxy + Gateway | Lightweight, performanslı |
| Traefik | Cloud-native | Docker/K8s auto-discovery |
| Tyk | Open Source | GraphQL dəstəyi |

---

## Çatışmazlıqlar

```
❌ Single Point of Failure:
   Gateway çöksə, bütün API çöküş → Yüksək availability tələb edir

❌ Latency artımı:
   Hər sorğu gateway-dən keçir → Əlavə network hop

❌ God Object riski:
   Çox business logic gateway-ə qoyulsa → Bottleneck

❌ Debugging çətinliyi:
   Gateway + servis arasındakı xətaları izləmək

Həllər:
✅ Gateway-i cluster-da işlət (HA)
✅ Yalnız cross-cutting concerns burada (auth, rate limit)
✅ Business logic servislərdə qalsın
✅ Distributed tracing (Jaeger, Zipkin)
```

---

## İntervyu Sualları

**1. API Gateway nə üçün istifadə edilir?**
Bütün client sorğuları üçün tək giriş nöqtəsi. Auth, rate limiting, routing, SSL termination, logging, tracing kimi cross-cutting concern-ləri mərkəzləşdirir. Servislər bu concern-lərlə özləri məşğul olmur.

**2. BFF pattern nədir, nə zaman lazımdır?**
Hər frontend tipi (mobile, web, IoT) üçün ayrı backend. Müxtəlif client-lərin fərqli data ehtiyacları olduqda, ümumi API hər iki tərəf üçün optimal ola bilmir. BFF hər client-ə optimized response verir.

**3. API Gateway ilə Load Balancer fərqi nədir?**
Load Balancer (L4) eyni servisdə yükü paylaşır. Reverse Proxy (L7) HTTP sorğularını yönləndirir. API Gateway L7 + API-specific features: auth, rate limiting, request transformation, aggregation.

**4. Gateway-də nə saxlamaq olmaz?**
Business logic gateway-ə qoyulmamalıdır. Bu gateway-i "God Object"-ə çevirir, servislərin müstəqilliyini pozur. Gateway yalnız cross-cutting concern-lərə cavabdeh olmalıdır.

**5. Request aggregation nədir, hansı riskləri var?**
Gateway bir neçə backend sorğusunu birləşdirib client-ə tək cavab verir. Risk: bir servis yavaş olarsa, bütün cavab gec gəlir. Həll: timeout + partial failure tolerace (uğursuz servisin cavabını null kimi göstər).

---

## Anti-patternlər

**1. Business logic-i Gateway-ə yerləşdirmək**
Endirim hesablaması, order validation, business qaydaları Gateway-ə köçürmək — Gateway "God Object"-ə çevrilir, servislərin müstəqilliyi pozulur, Gateway-i dəyişmədən business qaydaları yenilənə bilmir. Gateway yalnız cross-cutting concern-lər (auth, rate limiting, routing, logging) üçün məsuldur.

**2. Gateway-i tək uğursuzluq nöqtəsi (SPOF) kimi qurmaq**
Yalnız bir Gateway instance-ı işlətmək — Gateway çöksə bütün sisteme giriş kəsilir. Gateway-i horizontal scale et, load balancer arxasında çoxlu instance işlət, circuit breaker tətbiq et.

**3. Request aggregation-da partial failure-ı idarə etməmək**
Çoxlu backend sorğusunu birləşdirərkən bir backend-in uğursuzluğu bütün response-u uğursuz etmək — user heç bir məlumat ala bilmir, bir servisin problemi bütün sorğunu öldürür. Partial failure tolerace tətbiq et: uğursuz servisin cavabını `null` ya da default dəyər kimi göstər, digər servislərin cavabı qaytarılsın.

**4. Gateway-də autentifikasiyanı keçərək backend servislərinə güvənmək**
Gateway token-i yoxlayır, amma backend servisləri "gateway-dən gəldiyinə güvənir", öz auth yoxlaması etmir — gateway bypass edilsə (daxili şəbəkədən birbaşa servis çağrışı) autentifikasiya olmadan məlumat alına bilər. Zero-trust: hər servis daxili sorğuları da doğrulamalıdır (mutual TLS, internal JWT).

**5. Rate limiting-i yalnız IP ünvanına görə tətbiq etmək**
`throttle by IP` — NAT arxasındakı bütün istifadəçilər eyni limiti paylaşır, bir şirkətin bütün əməkdaşları bloklanır. Rate limiting-i həm IP, həm API key/token əsasında tətbiq et, authenticated user-lər üçün user_id-yə görə ayır.

**6. BFF pattern-i olmadan bütün client-lərə eyni Gateway response-unu vermək**
Mobile, web, IoT client-lərinin hamısına eyni ağır response qaytarmaq — mobil client 200 sahədən 10-nu istifadə edir, lazımsız data ötürülür, mobil şəbəkədə yavaşlama olur. BFF (Backend for Frontend) pattern-i tətbiq et, hər client tipi üçün optimized response yarat.
