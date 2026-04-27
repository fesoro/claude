# BFF Pattern (Backend for Frontend) (Senior)

## Mündəricat
1. [BFF nədir?](#bff-nədir)
2. [Niyə lazımdır?](#niyə-lazımdır)
3. [BFF vs API Gateway](#bff-vs-api-gateway)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## BFF nədir?

```
BFF (Backend for Frontend) — Sam Newman pattern.
Hər frontend növü üçün ayrı backend layer.

Ənənəvi:
  Mobile App ──┐
  Web App    ──┼──► Generic API ──► Microservices
  Desktop    ──┘

BFF:
  Mobile App ──► Mobile BFF ──┐
  Web App    ──► Web BFF    ──┼──► Microservices
  Partner    ──► Partner BFF──┘

Hər BFF öz client-ini "tanıyır":
  Mobile BFF:  bandwidth az → compressed, minimal data
  Web BFF:     rich data → full response, SSR data
  Partner BFF: stable, versioned, SLA-li API
```

---

## Niyə lazımdır?

```
Problem — Generic API:
  Order detail endpoint:
    Mobile lazım olan: id, status, total, 2 item
    Web lazım olan: id, status, total, tüm items, customer, shipping
    Admin lazım olan: hamısı + logs + timestamps

  Generic API hamısını qaytarır:
    → Mobile gereksiz data alır (bandwidth israf)
    → Hər client fərqli transformation edir
    → API versioning hər client üçün konfliktidir

BFF həlli:
  Mobile BFF:
    GET /orders/42 → yalnız lazım olan sahələr
    Aggregation: OrderService + minimal ShippingService
    Response: 2KB

  Web BFF:
    GET /orders/42 → rich data
    Aggregation: OrderService + ShippingService + CustomerService
    Response: 15KB

  Hər team öz BFF-ini idarə edir:
    Mobile team → Mobile BFF
    Web team    → Web BFF
    API versioning konflikti yoxdur!
```

---

## BFF vs API Gateway

```
API Gateway:
  Cross-cutting concerns: auth, rate limit, SSL termination, routing
  Client-agnostic
  Infrastructure layer

BFF:
  Client-specific logic: aggregation, transformation, formatting
  Business logic ola bilər
  Application layer

Birlikdə:
  Client → API Gateway (auth, rate limit) → BFF (aggregation) → Services

API Gateway BFF deyil:
  Gateway routing edir, BFF transform edir.
  Gateway infrastructure, BFF application.
```

---

## PHP İmplementasiyası

```php
<?php
// Mobile BFF — minimal, compressed response
class MobileOrderController
{
    public function show(int $orderId, Request $request): JsonResponse
    {
        // Yalnız mobile-a lazım olan data
        $order   = $this->orderService->findById($orderId);
        $status  = $this->shippingService->getStatus($orderId);

        // Mobile üçün minimal response
        return new JsonResponse([
            'id'          => $order->getId(),
            'status'      => $order->getStatus(),
            'total'       => $order->getTotal(),
            'item_count'  => count($order->getItems()),
            'tracking'    => $status->getTrackingNumber(),
        ]);
    }
}

// Web BFF — rich, aggregated response
class WebOrderController
{
    public function show(int $orderId): JsonResponse
    {
        // Paralel sorğular — web üçün daha çox data
        [$order, $customer, $shipping, $timeline] = array_map(
            fn($p) => $p->wait(),
            [
                $this->orderService->findByIdAsync($orderId),
                $this->customerService->getByOrderAsync($orderId),
                $this->shippingService->getFullDetailsAsync($orderId),
                $this->timelineService->getOrderTimelineAsync($orderId),
            ]
        );

        return new JsonResponse([
            'order'    => $order->toArray(),
            'customer' => $customer->toArray(),
            'shipping' => $shipping->toArray(),
            'timeline' => $timeline->toArray(),
        ]);
    }
}
```

---

## İntervyu Sualları

- BFF pattern nədir? Kimin tərəfindən idarə olunmalıdır?
- API Gateway ilə BFF fərqi nədir?
- BFF-in "team ownership" üstünlüyü nədir?
- BFF-lərin sayı artdıqca duplication riski necə idarə olunur?
- GraphQL BFF-in alternativimi? Fərqləri nədir?
