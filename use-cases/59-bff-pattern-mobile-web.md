# BFF Pattern (Lead)

## Problem
- 3 müxtəlif client: Mobile app, Web SPA, Partner B2B
- Mobile: bandwidth-sensitive, minimal data, offline-friendly
- Web: rich data, full features
- Partner: stable, versioned, SLA-li
- Backend microservice cluster (User, Order, Payment, Catalog)

Generic API hamısına eyni response qaytarır → Mobile gereksiz data, Partner unstable.

---

## Həll: 3 ayrı BFF (Backend for Frontend)

```
                       ┌─→ Mobile BFF ─→ aggregation, minimal response
Mobile App (iOS/Android)│
                       │
Web SPA (React) ───────┼─→ Web BFF ──→ rich data, GraphQL
                       │
Partner App (B2B) ─────┴─→ Partner BFF → versioned REST, OpenAPI

Hər BFF arxa servislərə (UserService, OrderService) çağırır.
```

---

## 1. Mobile BFF (minimal response)

```php
<?php
// app/Http/Controllers/Mobile/OrderController.php
namespace App\Http\Controllers\Mobile;

class OrderController
{
    public function show(int $orderId): JsonResponse
    {
        // Yalnız mobile-a lazım olan data — paralel fetch
        [$order, $tracking] = Octane::concurrently([
            fn() => $this->orderService->find($orderId),
            fn() => $this->shippingService->getTracking($orderId),
        ]);
        
        return response()->json([
            'id'         => $order->id,
            'status'     => $order->status,
            'total'      => $order->total,
            'item_count' => count($order->items),    // yalnız count, list yox
            'tracking'   => [
                'number' => $tracking->number,
                'eta'    => $tracking->eta_iso,
            ],
        ]);
    }
    
    public function listOrders(Request $req): JsonResponse
    {
        $userId = auth()->id();
        $orders = $this->orderService->listByUser($userId, limit: 20);
        
        // Stripped response — list view-də tam data lazım deyil
        return response()->json([
            'data' => $orders->map(fn($o) => [
                'id'     => $o->id,
                'status' => $o->status,
                'total'  => $o->total,
                'date'   => $o->created_at->format('Y-m-d'),
            ]),
            'cursor' => $orders->nextCursor(),
        ]);
    }
}
```

```
Mobile BFF features:
  ✓ Minimal payload (bandwidth save)
  ✓ Pre-aggregated (1 request)
  ✓ Image URL-ləri optimized size (CDN parameters)
  ✓ Compressed (gzip + JSON)
  ✓ Cache-Control: max-age=60 (offline-friendly)
  ✓ ETag (304 not modified)
```

---

## 2. Web BFF (GraphQL)

```php
<?php
// app/GraphQL/Schema/orders.graphql
type Order {
    id: ID!
    status: OrderStatus!
    total: Money!
    items: [OrderItem!]!
    customer: User!
    shippingAddress: Address!
    billingAddress: Address!
    payment: Payment!
    timeline: [TimelineEvent!]!
    relatedProducts: [Product!]!
    estimatedDelivery: DateTime
}

type Query {
    order(id: ID!): Order @auth @find
    orders(first: Int = 20, after: String): OrderConnection @paginate(type: CONNECTION)
}
```

```
Web BFF features:
  ✓ GraphQL — client field-ləri seçir
  ✓ Rich data (full nested)
  ✓ DataLoader N+1 qarşı
  ✓ Real-time subscription (order status update)
  ✓ Browser-side caching (Apollo cache)
```

---

## 3. Partner BFF (versioned REST + OpenAPI)

```php
<?php
// app/Http/Controllers/Partner/V1/OrderController.php
namespace App\Http\Controllers\Partner\V1;

#[OA\Get(path: '/v1/orders/{id}', tags: ['orders'])]
#[OA\Response(response: 200, description: 'Order details', content: new OA\JsonContent(ref: '#/components/schemas/Order'))]
class OrderController
{
    public function show(int $orderId, Request $req): JsonResponse
    {
        $partner = $req->user();
        
        // Authorization: partner can only see own customer orders
        $order = $this->orderService->find($orderId);
        if ($order->merchant_id !== $partner->merchant_id) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        
        // Full, stable response — partner kontraktına uyğun
        return response()->json([
            'id'              => $order->id,
            'merchant_id'     => $order->merchant_id,
            'status'          => $order->status,
            'currency'        => $order->currency,
            'subtotal_cents'  => $order->subtotal,
            'tax_cents'       => $order->tax,
            'shipping_cents'  => $order->shipping,
            'total_cents'     => $order->total,
            'items'           => $order->items->map(fn($i) => [
                'sku'         => $i->sku,
                'name'        => $i->name,
                'quantity'    => $i->quantity,
                'price_cents' => $i->price,
            ]),
            'customer'        => [
                'id'    => $order->customer_id,
                'email' => $order->customer->email,
            ],
            'created_at'      => $order->created_at->toIso8601String(),
            'updated_at'      => $order->updated_at->toIso8601String(),
        ]);
    }
}
```

```
Partner BFF features:
  ✓ Versioning (/v1, /v2 — old API deprecation slow)
  ✓ OpenAPI spec auto-generated
  ✓ Stable contract (breaking change yox)
  ✓ Rate limiting per partner (different SLA tiers)
  ✓ API key + HMAC signature
  ✓ Webhook outbound (event subscription)
```

---

## 4. Common: backend service clients

```php
<?php
// Hər BFF eyni microservice client-lərini istifadə edir (kod təkrar yox)
namespace App\Services\Internal;

class OrderServiceClient
{
    public function __construct(private HttpClient $http) {}
    
    public function find(int $id): OrderDto
    {
        $response = $this->http->get("http://order-service.internal/orders/$id");
        return OrderDto::fromArray($response->json());
    }
    
    public function listByUser(int $userId, int $limit): OrderCollection
    {
        // ...
    }
}

// İstifadə hər BFF-də:
class MobileOrderController
{
    public function __construct(
        private OrderServiceClient $orderService,
        private ShippingServiceClient $shippingService,
    ) {}
}
```

---

## 5. Routing & Auth differentiation

```php
<?php
// routes/api.php

// Mobile BFF
Route::prefix('mobile')->middleware(['auth:sanctum', 'mobile.throttle'])->group(function () {
    Route::get('/orders/{id}', [Mobile\OrderController::class, 'show']);
    Route::get('/orders', [Mobile\OrderController::class, 'list']);
});

// Web BFF (GraphQL)
Route::post('/graphql', \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class);

// Partner BFF
Route::prefix('v1')->middleware(['auth:partner-api', 'partner.rate-limit'])->group(function () {
    Route::get('/orders/{id}', [Partner\V1\OrderController::class, 'show']);
});
Route::prefix('v2')->middleware(['auth:partner-api'])->group(function () {
    Route::get('/orders/{id}', [Partner\V2\OrderController::class, 'show']);
});
```

---

## 6. Performance comparison

```
Generic API (no BFF):
  Mobile order detail: 15 KB response, 250ms latency
  Web order detail:    15 KB response, 250ms latency
  Partner:             15 KB response, 250ms latency

BFF:
  Mobile:  2 KB,  90ms (paralel fetch + minimal projection)
  Web:     30 KB, 180ms (rich, GraphQL Schemaless cache friendly)
  Partner: 12 KB, 200ms (full + audit metadata)

Mobile bandwidth save: 87%
Mobile latency save: 64%
Partner-bağlı: stable contract → integration breakage azalır
```

---

## 7. Team ownership

```
Microservice arxasında centralised team (User, Order, Payment).
BFF — frontend-team-in öz kodudur:

  Mobile team    →  owns Mobile BFF
  Web team       →  owns Web BFF
  Partner team   →  owns Partner BFF

Üstünlük:
  ✓ Frontend team backend-dən asılı deyil (öz iterasiyaları)
  ✓ Schema dəyişikliyi öz BFF-də (microservice-ə toxunmur)
  ✓ A/B test BFF səviyyəsində

Çatışmazlıq:
  ✗ Hər BFF eyni domain logic-ini duplikat edə bilər
  ✗ Microservice contract dəyişikliyi 3 BFF-də yenilənir
  ✗ Operational overhead (3 service-i deploy + monitor)
```

---

## 8. Pitfalls

```
❌ BFF-də biznes məntiqi yığılır — microservice anemic olur
   ✓ Aggregation OK, biznes logic microservice-də

❌ BFF microservice-yə birbaşa DB query atır
   ✓ Yalnız HTTP/gRPC API üzərindən

❌ Hər BFF üçün ayrı auth sistemi
   ✓ Centralized auth service, BFF token validate edir

❌ Cross-BFF code duplication (DTO mapper, error handling)
   ✓ Shared library / package (PHP-də Composer)

❌ "Generic BFF" — hər client üçün switch
   ✓ Per-client BFF (BFF-in əsas məğzi budur!)

❌ N+1 microservice çağırışları
   ✓ Paralel (Octane::concurrently, Promise::all)
```

---

## 9. Monitoring per BFF

```
Per-BFF dashboard:
  - Request rate (mobile vs web vs partner)
  - Latency distribution (P50, P99)
  - Error rate per endpoint
  - Backend microservice latency (downstream)
  - Cache hit ratio
  - Bandwidth usage (mobile critical)

Alerting:
  - Mobile P99 > 500ms → page (UX critical)
  - Partner SLA breach → Slack + email
  - Web GraphQL N+1 detected → Slack
```

---

## Problem niyə yaranır?

Real layihələrdə tək bir generic API-nin bütün client növlərini eyni şəkildə xidmət etməyə çalışması texniki borcun əsas mənbəyidir. Məsələn, `/api/orders/{id}` endpoint-i backend-də mövcud olan bütün məlumatları qaytarır: müştəri detalları, ödəniş tarixçəsi, shipping məlumatları, audit log-lar, admin metadata-sı — ümumilikdə 50+ sahə. Mobile tətbiq bu response-un 90%-ni heç istifadə etmir, lakin hər request-də həmin data şəbəkə üzərindən ötürülür. 3G şəbəkəsindəki istifadəçi 15 KB məlumat alır, amma ona 1.5 KB kifayət edəcəkdi. Bu artıq yük həm battery, həm də latency baxımından real UX problemə çevrilir.

Versioning isə başqa bir böhrankənar nöqtədir. Mobile team yeni field əlavə etmək, mövcud field-i rename etmək və ya pagination strukturunu dəyişmək istəyəndə bunu `v2` endpoint kimi publish edir. Web team eyni endpoint-i başqa şəkildə inkişaf etdirmək istəyir — fərqli `v2` yaranır. Partner B2B isə üç ildən bəri `v1`-i istifadə edir və hər hansı breaking change onların sistemlərini sıradan çıxarır. Nəticədə bir API 4-5 versiya, hər birinin öz quirk-ləri, öz bug-ları və öz documentation-ı ilə paralel yaşayır. Maintenance yükü eksponensial artır, yeni feature-lar isə "bu versiyaya da backport etmək lazımdır" pressurunun altında yavaşlayır.

Əsas texniki problem bundan ibarətdir: generic API heç bir client-i yaxşı bilmir. O, ən geniş ortaq kəsişməyə (lowest common denominator) xidmət edir — hər client üçün "kifayət qədər yaxşı", amma heç biri üçün optimal deyil. Mobile client-in network constraint-lərini bilmir. Web client-in real-time subscription ehtiyacını bilmir. Partner-in SLA tələblərini və field mapping konvensiyalarını bilmir. BFF pattern bu problemi hər client üçün ayrı "translation layer" yaratmaqla həll edir: backend mikroservislər öz domain modelini saxlayır, BFF isə həmin modeli konkret client-in dilinə çevirir.

---

## 10. Partner BFF — Tam Implementation

Partner B2B BFF-nin spesifik tələbləri var: versioned REST, strict SLA, API key autentifikasiya, field mapping (partner-in öz terminologiyası), rate limiting per partner tier. Aşağıda bu tələblərin hamısını əhatə edən tam implementation verilmişdir.

### Rate Limiting — Partner Tier-ləri

```php
<?php
// app/Http/Middleware/PartnerRateLimit.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PartnerRateLimit
{
    /**
     * Partner tier-ə görə fərqli rate limit tətbiq edir.
     *
     * Tier-lər:
     *   basic    → 100 req/dəq
     *   standard → 500 req/dəq
     *   premium  → 2000 req/dəq
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Partner $partner */
        $partner = $request->user();

        $limits = [
            'basic'    => 100,
            'standard' => 500,
            'premium'  => 2000,
        ];

        $maxAttempts = $limits[$partner->tier] ?? 100;
        $key = "partner_rate:{$partner->id}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'error' => 'rate_limit_exceeded',
                'message' => "Rate limit exceeded. Retry after {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429)->withHeaders([
                'X-RateLimit-Limit'     => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After'           => $seconds,
            ]);
        }

        RateLimiter::hit($key, 60); // 1 dəqiqəlik window

        $response = $next($request);

        // Response header-lərinə rate limit məlumatı əlavə et
        return $response->withHeaders([
            'X-RateLimit-Limit'     => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
        ]);
    }
}
```

### Partner API Key Auth Guard

```php
<?php
// app/Http/Guards/PartnerApiKeyGuard.php
namespace App\Http\Guards;

use App\Models\Partner;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PartnerApiKeyGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        UserProvider $provider,
        private Request $request
    ) {
        $this->provider = $provider;
    }

    public function user(): ?Partner
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $apiKey = $this->request->header('X-Partner-API-Key');

        if (empty($apiKey)) {
            return null;
        }

        // API key-in prefix-i ilə partner-i tap (timing-safe)
        // Format: "pk_live_xxxxx" → prefix "pk_live_" ilə axtarış
        [$prefix] = explode('_', $apiKey, 3) + ['', '', ''];

        $partner = Partner::where('api_key_prefix', substr($apiKey, 0, 12))
            ->where('is_active', true)
            ->first();

        if (!$partner || !Hash::check($apiKey, $partner->api_key_hash)) {
            return null;
        }

        // Son görülmə vaxtını yenilə (async — performansı bloklamasın)
        $partner->updateQuietly(['last_seen_at' => now()]);

        return $this->user = $partner;
    }

    public function validate(array $credentials = []): bool
    {
        return false; // Stateless guard — validate istifadə edilmir
    }
}
```

### V2 Order Controller — Tam

```php
<?php
// app/Http/Controllers/Partner/V2/OrderController.php
namespace App\Http\Controllers\Partner\V2;

use App\Http\Controllers\Controller;
use App\Services\Internal\OrderServiceClient;
use App\Services\Internal\ShippingServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Partner Orders v2', description: 'Stable order API for B2B partners')]
class OrderController extends Controller
{
    public function __construct(
        private OrderServiceClient    $orderService,
        private ShippingServiceClient $shippingService,
    ) {}

    #[OA\Get(
        path: '/v2/orders/{orderId}',
        summary: 'Get order details',
        tags: ['Partner Orders v2'],
        parameters: [
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order details', content: new OA\JsonContent(ref: '#/components/schemas/PartnerOrderV2')),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function show(int $orderId, Request $request): JsonResponse
    {
        /** @var \App\Models\Partner $partner */
        $partner = $request->user();

        $order = $this->orderService->find($orderId);

        if (!$order) {
            return response()->json([
                'error' => 'not_found',
                'message' => "Order #{$orderId} not found.",
            ], 404);
        }

        // Partner yalnız öz merchant-ına aid sifarişlərə baxa bilər
        if ($order->merchant_id !== $partner->merchant_id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have access to this order.',
            ], 403);
        }

        // Partner-specific response — stable API contract
        // v2-nin fərqi: reference_id əlavə olundu, amount-lar cents-ə çevrildi,
        // fulfillment_status partner terminologiyasına uyğunlaşdırıldı
        return response()->json([
            'order_reference'       => $order->reference_id,           // v2: yeni sahə
            'placed_at'             => $order->created_at->toIso8601String(),
            'fulfillment_status'    => $this->mapFulfillmentStatus($order->status),
            'line_items'            => $order->items->map(fn($item) => [
                'sku'              => $item->product->sku,
                'name'             => $item->product->name,
                'quantity'         => $item->quantity,
                'unit_price_cents' => (int) ($item->price * 100),
                'subtotal_cents'   => (int) ($item->price * $item->quantity * 100),
            ]),
            'pricing'               => [
                'subtotal_cents'   => (int) ($order->subtotal * 100),
                'tax_cents'        => (int) ($order->tax * 100),
                'shipping_cents'   => (int) ($order->shipping * 100),
                'discount_cents'   => (int) ($order->discount * 100),
                'total_cents'      => (int) ($order->total * 100),
                'currency'         => $order->currency,
            ],
            'shipping_address'      => $this->formatAddress($order->shippingAddress),
            'estimated_delivery_at' => $order->estimated_delivery?->toIso8601String(),
        ])->withHeaders([
            // Partner API-ları üçün cache-ləmə siyasəti
            'Cache-Control' => 'private, max-age=30',
            'ETag'          => '"' . md5($order->updated_at->toIso8601String()) . '"',
        ]);
    }

    #[OA\Get(
        path: '/v2/orders',
        summary: 'List orders',
        tags: ['Partner Orders v2'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 200)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated order list'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'   => 'nullable|string|in:pending,processing,shipped,delivered,cancelled',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        /** @var \App\Models\Partner $partner */
        $partner = $request->user();

        $result = $this->orderService->listByMerchant(
            merchantId: $partner->merchant_id,
            filters: $validated,
            page: $validated['page'] ?? 1,
            perPage: $validated['per_page'] ?? 50,
        );

        return response()->json([
            'data' => $result->items()->map(fn($order) => [
                'order_reference'    => $order->reference_id,
                'placed_at'          => $order->created_at->toIso8601String(),
                'fulfillment_status' => $this->mapFulfillmentStatus($order->status),
                'total_cents'        => (int) ($order->total * 100),
                'currency'           => $order->currency,
                'item_count'         => $order->items_count,
            ]),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
                'last_page'    => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Partner terminologiyasına mapping.
     * Internal status-lar dəyişsə belə, partner API-si stable qalır.
     */
    private function mapFulfillmentStatus(string $internalStatus): string
    {
        return match ($internalStatus) {
            'created', 'payment_pending' => 'pending',
            'paid', 'picking', 'packing' => 'processing',
            'shipped', 'in_transit'      => 'shipped',
            'delivered'                  => 'delivered',
            'cancelled', 'refunded'      => 'cancelled',
            default                      => 'unknown',
        };
    }

    private function formatAddress(object $address): array
    {
        return [
            'line1'       => $address->line1,
            'line2'       => $address->line2 ?? null,
            'city'        => $address->city,
            'state'       => $address->state ?? null,
            'postal_code' => $address->postal_code,
            'country'     => $address->country_code, // ISO 3166-1 alpha-2
        ];
    }
}
```

### Route Qeydiyyatı

```php
<?php
// routes/partner.php — Partner BFF üçün ayrı route fayl

use App\Http\Controllers\Partner;
use Illuminate\Support\Facades\Route;

// V1 — mövcud partner-lər üçün (deprecated, amma hələ aktiv)
Route::prefix('v1')
    ->middleware(['auth:partner-api', 'partner.rate-limit', 'throttle:partner-v1'])
    ->name('partner.v1.')
    ->group(function () {
        Route::get('/orders/{id}',  [Partner\V1\OrderController::class, 'show']);
        Route::get('/orders',       [Partner\V1\OrderController::class, 'index']);
        Route::get('/products/{id}',[Partner\V1\ProductController::class, 'show']);
    });

// V2 — yeni partner-lər üçün (aktiv inkişaf)
Route::prefix('v2')
    ->middleware(['auth:partner-api', 'partner.rate-limit'])
    ->name('partner.v2.')
    ->group(function () {
        Route::get('/orders/{orderId}', [Partner\V2\OrderController::class, 'show']);
        Route::get('/orders',           [Partner\V2\OrderController::class, 'index']);
        Route::get('/products/{sku}',   [Partner\V2\ProductController::class, 'show']);
        Route::get('/webhooks',         [Partner\V2\WebhookController::class, 'index']);
        Route::post('/webhooks',        [Partner\V2\WebhookController::class, 'store']);
    });
```

### OpenAPI Spec Generation (L5-Swagger)

```php
<?php
// config/l5-swagger.php — əsas konfigurasiya
// composer require darkaonline/l5-swagger

// app/Http/Controllers/Partner/V2/ApiController.php — OpenAPI annotasiyalar
namespace App\Http\Controllers\Partner\V2;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '2.0.0',
    title: 'Partner API',
    description: 'Stable B2B API for merchant partners. Breaking changes are versioned.',
    contact: new OA\Contact(email: 'partner-support@example.com'),
)]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    in: 'header',
    name: 'X-Partner-API-Key',
)]
#[OA\Schema(
    schema: 'PartnerOrderV2',
    required: ['order_reference', 'placed_at', 'fulfillment_status', 'line_items', 'pricing'],
    properties: [
        new OA\Property(property: 'order_reference', type: 'string', example: 'ORD-2024-00123'),
        new OA\Property(property: 'placed_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'fulfillment_status', type: 'string', enum: ['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
        new OA\Property(property: 'pricing', properties: [
            new OA\Property(property: 'total_cents', type: 'integer', example: 4999),
            new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        ], type: 'object'),
    ]
)]
class ApiController extends Controller {}
```

> **OpenAPI generation:** `php artisan l5-swagger:generate` komandası annotation-lardan avtomatik `public/api-docs/partner-v2.json` yaradır. Bu spec-i Swagger UI, Postman, ya da partner developer portal-ına import etmək olar.

---

## Trade-offs

| Yanaşma | Üstünlüklər | Çatışmazlıqlar | Team structure tələbi |
|---|---|---|---|
| **Single Generic API** | Sadə deployment, tək codebase, az operational yük | Hər client üçün suboptimal response, versioning çətin, one team bottleneck | 1 backend team bütün client-ləri idarə edir |
| **BFF per Client** | Hər client üçün optimal response, müstəqil deployment, team autonomy | Operational overhead (N servis), kod dublikasiya riski, microservice call sayı artır | Hər client üçün ayrı team (Mobile, Web, Partner) |
| **GraphQL as BFF** | Client field seçimi, tək endpoint, strongly typed schema | Mobile-da over-fetching hələ mümkün (lazy developers), N+1 riski, caching çətin, partner SLA üçün uyğun deyil | Full-stack yönümlü team, DataLoader expertise lazım |

**Nə zaman hansını seçmək:**

- **Single API** — startup mərhələsi, 1-2 client, team kiçikdir
- **BFF per client** — hər client-in fərqli data/performance tələbləri varsa, team-lər müstəqildir
- **GraphQL** — Web SPA dominant client-dirsə, frontend team güclüdürsə, real-time subscription lazımdırsa

---

## Anti-patternlər

**1. BFF-i biznes logic ilə doldurmaq**
BFF-in məqsədi aggregation və transformation-dır. Qiymət hesablaması, endirim qaydaları, inventory yoxlaması kimi biznes qaydalar BFF-də deyil, müvafiq mikroservisdə olmalıdır. BFF-də biznes logic yığılırsa, eyni logicanı birdən çox BFF-də təkrar yazmağa məcbur olursunuz.

```php
// Yanlış — BFF-də biznes hesablama
public function show(int $id): JsonResponse
{
    $order = $this->orderService->find($id);
    $discount = $order->total > 1000 ? 0.10 : 0.05; // BİZNES LOGICANIZ BURADA DEYİL!
    $finalPrice = $order->total * (1 - $discount);
    // ...
}

// Düzgün — mikroservisdən hazır nəticəni al
public function show(int $id): JsonResponse
{
    $order = $this->orderService->findWithPricing($id); // servis hesablayır
    // BFF yalnız field mapping edir
}
```

**2. Downstream servisləri BFF-dən birbaşa DB-yə qoşmaq**
BFF mikroservislərin database-inə birbaşa qoşulmamalıdır. Bu, mikroservisin əsas prinsipini (data ownership) pozur. BFF yalnız mikroservisin açıq etdiyi HTTP/gRPC interfeysi üzərindən danışmalıdır.

**3. Hər feature üçün yeni BFF yaratmaq**
"Mobile team yeni feature istəyir → yeni BFF" yanaşması yanlışdır. BFF client tipinə görə (Mobile, Web, Partner) yaradılır, feature-a görə yox. Əks halda onlarla BFF yaranır, hamısını idarə etmək mümkünsüz olur.

**4. BFF-lər arasında kod dublikatı**
Üç BFF-in hər birinin öz `OrderServiceClient`, öz error handling, öz logging utility-si olması problematikdir. Shared internal package (Composer private package) yaradın: service client-lər, DTO-lar, ortak middleware-lər bu package-dən gəlsin. BFF-ə məxsus olan yalnız transformation logic-i olsun.

**5. BFF-ni authentication gateway kimi istifadə etmək**
JWT verify etmək, session idarə etmək, OAuth flow-u həyata keçirmək BFF-in işi deyil. Bu məsuliyyət ya ayrıca API Gateway-ə, ya da Identity Service-ə aiddir. BFF yalnız artıq verify olunmuş token-i qəbul edir və istifadəçi kontekstini downstream-ə ötürür.

**6. Mobile BFF-də heavy computation etmək**
Mobile BFF-in latency-si kritikdir (P99 < 300ms hədəfi). Burada image resize, PDF generation, həcmli data aggregation kimi əməliyyatlar etmək yanlışdır. Ağır işləri async job-lara keçirin, BFF yalnız hazır nəticəni qaytarsın. "Compute where it's cheap, serve where it's fast."

**7. BFF-i database-ə birbaşa qoşmaq**
"Sadəcə bu query-ni birbaşa atmaq daha sürətlidir" düşüncəsi ilə BFF-dən birbaşa `orders` cədvəlinə query atılması arxitekturanı pozur. BFF database haqqında bilməməlidir — o, yalnız servis layer-lə danışır. Bir dəfə bu qayda pozulsa, BFF tədricən "mini-monolit"ə çevrilir.

---

## Interview Sualları və Cavablar

**S: BFF pattern nədir, nə zaman istifadə edilir?**

BFF (Backend for Frontend) — hər client növü üçün ayrıca backend aggregation layer-dir. Bir generic API-nin bütün client-lərə eyni response qaytarması yerinə, hər client öz BFF-inə malik olur. Mobile BFF minimal data qaytarır, Web BFF GraphQL ilə client-driven query dəstəkləyir, Partner BFF versioned REST ilə stable contract təmin edir. İstifadə şərtləri: client-lərin data tələbləri əhəmiyyətli dərəcədə fərqlidirsə, hər client üçün fərqli team varsa, client-specific performance optimizasiya lazımdırsa.

**S: GraphQL BFF-i əvəzləyə bilərmi?**

Kısmi olaraq bəli, tam olaraq xeyr. GraphQL Web SPA üçün mükəmməl BFF alternatividir: client lazım olan sahələri özü seçir, N+1 DataLoader ilə həll edilir, subscription real-time-ı dəstəkləyir. Lakin Partner B2B üçün GraphQL uyğun deyil: partner-lər OpenAPI/REST-ə əsaslanır, SLA guarantee-si GraphQL-də çətin, breaking change idarəçiliyi versioned REST-dən mürəkkəbdir. Mobile üçün de isə GraphQL over-fetching-ə açıq qalır — lazımsız field-ləri query-yə salmaq developer-dən disiplin tələb edir. Praktiki qərar: Web BFF-i GraphQL, digər BFF-lər REST ilə qurmaq.

**S: BFF-in microservice arxitekturunda rolu nədir?**

Mikroservislər domain-centric design üzrə qurulur: Order Service sifarişləri, User Service istifadəçiləri idarə edir. Bu servislərin heç biri "Mobile app-ın order list ekranı üçün lazım olan data" anlayışına sahib deyil. BFF bu boşluğu doldurur — microservice-lərdən məlumat toplayır (orchestration), client-in anlayacağı formata çevirir (transformation) və lazımsız round-trip-ləri aradan qaldırır (aggregation). BFF olmadan client tərəfi 5 mikroservisi ayrı-ayrı çağırmalı, nəticələri özü birləşdirməli olardı.

**S: Mobile BFF-i Web BFF-dən ayırmaqdaki əsas fayda nədir?**

İkisi arasındakı əsas fərq optimization vektoru: Mobile BFF bandwidth və latency üzrə optimallaşır (minimal payload, agressive caching, gzip, image URL-də CDN parameters), Web BFF isə data zenginliyi və flexibility üzrə (full nested response, GraphQL field selection, real-time subscription). Bu fərqli tələblər eyni kod bazasından idarə oluna bilməz — biri "daha az göndər", digəri "daha çox imkan ver" deyir. Üstəlik, Mobile team öz release cycle-na sahib olur: App Store review prosesi Web-dən asılı olmadan davam edir, BFF dəyişiklikləri yalnız öz client-ini təsir edir.

**S: BFF code sharing-i necə idarə edərdiniz?**

Private Composer package yanaşması: ortaq kod `packages/bff-shared` kimi repository-də yaşayır, hər BFF onu `composer.json`-da dependency kimi qeyd edir. Shared package-ə daxil olan şeylər: internal service client-lər (`OrderServiceClient`, `UserServiceClient`), DTO-lar, ortak exception handler-lər, monitoring utility-ləri (request logging, distributed tracing). BFF-ə məxsus olan şeylər isə shared-dən kənarda qalır: response transformation, client-specific middleware, auth logic. Bu ayrımın testi sadədir — "bu kod ikinci BFF-də də eyni şəkildə istifadə ediləcəkmi?" sualına cavab bəli-dirsə, shared-ə keçin.
