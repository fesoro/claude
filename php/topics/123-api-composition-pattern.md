# API Composition Pattern

## Mündəricat
1. [Problem: Distributed Data](#problem-distributed-data)
2. [API Composition](#api-composition)
3. [Parallel vs Sequential Calls](#parallel-vs-sequential-calls)
4. [CQRS ilə Alternativ](#cqrs-ilə-alternativ)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Distributed Data

```
Microservice-lərdə data paylanmışdır:
  OrderService    → sifarişlər
  CustomerService → müştərilər
  ProductService  → məhsullar
  ShippingService → çatdırılma

Client "sifariş detayı" səhifəsi üçün hamısı lazımdır.

Pis yanaşma — client hər servisi ayrıca çağırır:
  Client → GET /orders/42
  Client → GET /customers/7
  Client → GET /products/1,2,3
  Client → GET /shipping/42
  → 4 ayrı HTTP round-trip, yavaş, mürəkkəb client kodu

Həll: API Composition
  BFF/Gateway hər servisi çağırır, nəticəni birləşdirir.
  Client yalnız bir endpoint-ə sorğu edir.
```

---

## API Composition

```
Composer (BFF/Gateway):
  1. Client sorğusu alır
  2. Lazımi servisləri çağırır (parallel!)
  3. Nəticələri birləşdirir
  4. Tək cavab qaytarır

┌─────────┐   GET /orders/42/detail   ┌──────────────┐
│ Client  │─────────────────────────► │   Composer   │
└─────────┘                           │              │
                                      │ OrderSvc ────►│
                                      │ CustomerSvc ──►│ (parallel)
                                      │ ShippingSvc ──►│
                                      │              │
                                      │ ← merge ─────│
└─────────────────────────────────────◄──── response ─┘

Scatter-Gather pattern:
  Scatter: sorğuları yay (parallel)
  Gather:  cavabları topla, birləşdir
```

---

## Parallel vs Sequential Calls

```
Sequential (yanlış):
  order    = await OrderService.get(42)      // 50ms
  customer = await CustomerService.get(7)    // 40ms
  shipping = await ShippingService.get(42)   // 60ms
  Toplam:  150ms

Parallel (düzgün):
  [order, customer, shipping] = await Promise.all([
      OrderService.get(42),      // 50ms ┐
      CustomerService.get(7),    // 40ms ├── paralel
      ShippingService.get(42),   // 60ms ┘
  ])
  Toplam: max(50, 40, 60) = 60ms

2.5x sürətli!

Partial failure:
  Bir servis fail olsa nə?
  Option 1: Hamısı fail → 503
  Option 2: Mövcud data qaytarılır, fail olan null/default
  Option 3: Retry (timeout ilə)

Tövsiyə: Non-critical servis fail olsa degraded response qaytarın.
```

---

## CQRS ilə Alternativ

```
API Composition dezavantajı:
  JOIN mümkün deyil (cross-service filter)
  "Azərbaycanlı premium müştərilərin son 10 sifarişi" →
  CustomerService + OrderService join = problematik

CQRS Read Model:
  Ayrıca "Order Detail View" read modeli saxla.
  OrderService + CustomerService event-lərindən build et.
  Bir DB-də joined view kimi mövcud.
  Sorğu direkt bu view-dan.

Trade-off:
  API Composition: Sadə, real-time, amma cross-service filter yox
  CQRS Read Model: Mürəkkəb, eventual consistency, amma güclü sorğu
```

---

## PHP İmplementasiyası

```php
<?php
// Parallel API Composition (async HTTP client)
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

class OrderDetailComposer
{
    public function __construct(private Client $http) {}

    public function compose(int $orderId): array
    {
        // Əvvəlcə sifarişi al (digərləri buna bağlıdır)
        $order = $this->http->get("/orders/{$orderId}")->toArray();

        // Paralel sorğular
        $promises = [
            'customer' => $this->http->getAsync("/customers/{$order['customer_id']}"),
            'shipping' => $this->http->getAsync("/shipping/{$orderId}"),
            'products' => $this->http->getAsync("/products?ids=" . implode(',', $order['product_ids'])),
        ];

        $results = Utils::settle($promises)->wait();

        return [
            'order'    => $order,
            'customer' => $results['customer']['state'] === 'fulfilled'
                ? $results['customer']['value']->toArray()
                : null,
            'shipping' => $results['shipping']['state'] === 'fulfilled'
                ? $results['shipping']['value']->toArray()
                : ['status' => 'unknown'],
            'products' => $results['products']['state'] === 'fulfilled'
                ? $results['products']['value']->toArray()
                : [],
        ];
    }
}
```

---

## İntervyu Sualları

- API Composition pattern nədir? Client özü çağırmasından fərqi?
- Parallel calls sequential-dan neçə dəfə sürətli ola bilər?
- Bir servis timeout verərsə API Composition necə davranmalıdır?
- Cross-service filter lazım olduqda API Composition-un məhdudiyyəti nədir?
- GraphQL API Composition-un bir formasımı sayılır?
