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
