# API Composition Pattern (Senior ⭐⭐⭐)

## İcmal

API Composition — microservice arxitekturasında paylanmış data-nı bir sorğu ilə client-ə qaytarmaq üçün scatter-gather yanaşmasıdır. Composer (BFF, Gateway, ya da dedicated service) müxtəlif servisləri paralel çağırır, nəticələri birləşdirir, tək cavab qaytarır. Client-in çoxlu servisi ayrı-ayrı çağırmasının əvəzinə bir endpoint çağırması kifayət edir.

## Niyə Vacibdir

Microservice-lərdə data paylanmışdır: `OrderService`, `CustomerService`, `ProductService`, `ShippingService` hər biri öz DB-sini idarə edir. "Sifariş detayı" səhifəsi hamısını tələb edir. Client bu 4 servisi ayrı-ayrı çağırarsa: 4 HTTP round-trip, network latency 4x artır, client kodu mürəkkəbləşir, partial failure idarəsi client-ə düşür. API Composition bu problemi kompozisyon qatında həll edir. Paralel execution latency-ni `max(t1, t2, t3)` edir, sequential `t1+t2+t3` deyil.

## Əsas Anlayışlar

- **Scatter**: sorğuları paralel yay — eyni vaxtda bütün downstream servisləri çağır
- **Gather**: cavabları topla — hamısı tamamlandıqda birləşdir
- **Partial failure**: bir servis fail olsa digərləri bloklanmamalı; degraded response daha yaxşıdır
- **Timeout isolation**: hər sorğunun öz timeout-u olmalı; bir yavaş servis hamısını gözlətməməlidir
- **CQRS alternativ**: cross-service filter lazım olduqda composition deyil, read model daha uyğundur
- **N+1 composition**: bir siyahıda hər element üçün ayrı servis çağırışı — klassik anti-pattern

## Praktik Baxış

- **Real istifadə**: e-commerce dashboard (sifarişlər + müştəri + çatdırılma statusu), sosial media feed (post + user + like count + comment count), SaaS analytics (metrics + alerts + recommendations)
- **Trade-off-lar**: latency azalır (paralel); client sadələşir; partial failure gracefully idarə edilə bilər; lakin composition qatında xata idarəsi mürəkkəbləşir; downstream değişiklikləri composer-ə yansıyır; caching mürəkkəbdir
- **İstifadə etməmək**: cross-service JOIN lazım olan hallarda (filtr + sıralama çox servisdə); tez-tez çağrılan, real-time olmayan data üçün (read model daha uyğundur); downstream-lər sync olmamalıdırsa
- **Common mistakes**: sequential composition (parallel əvəzinə); timeout olmadan composition (bir yavaş servis hamısını bloklar); partial failure-ı işləməmək (hamısı fail kimi saymaq)

## Anti-Pattern Nə Zaman Olur?

**N+1 composition — siyahı + hər element üçün ayrı çağrı:**
```
GET /orders → 20 sifariş qaytarır
foreach order: GET /customers/{id}  ← 20 ayrı HTTP call!
```
Həll: batch endpoint istifadə et (`GET /customers?ids=1,2,3...`), ya da CQRS read model-dən denormalized data oxu.

**Synchronous composition tez-tez çağrılan endpoint-lər üçün:**
Hər request-də 5 servis çağırılırsa, caching olmadan sistem miqyaslanmaz. Composition nəticəsini cache-ləmək, ya da event-driven read model qurmaq lazımdır.

**Timeout olmadan composition:**
Bir downstream 30 saniyə cavab vermirsə, bütün composition bloklanır. Hər sorğunun müstəqil timeout-u olmalı, timeout-dan sonra null/default qaytarılmalıdır.

## Nümunələr

### Ümumi Nümunə

Sifariş detayı: `OrderService`-dən sifariş alınır, sonra `CustomerService`, `ShippingService`, `ProductService` paralel çağrılır. Ən uzun cavab 60ms-dirsə, toplam 60ms + overhead olur — sequential 150ms+ deyil. `ShippingService` fail olsa `status: "unknown"` qaytarılır, sifariş məlumatı görünür qalır.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Composition;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\JsonResponse;

class OrderDetailComposer
{
    public function compose(string $orderId, string $userId): array
    {
        // 1. Əvvəl əsas resursu al (digərləri buna bağlıdır)
        // WHY: customer_id, product_ids kimi ID-lər lazımdır paralel sorğular üçün
        $order = Http::timeout(5)
            ->get(config('services.order.url') . "/orders/{$orderId}")
            ->throw()    // 4xx/5xx-da exception at
            ->json();

        // 2. Paralel scatter — eyni vaxtda 3 sorğu
        $results = Http::pool(fn(Pool $pool) => [
            $pool
                ->as('customer')
                ->timeout(3)
                ->get(config('services.customer.url') . "/customers/{$order['customer_id']}"),

            $pool
                ->as('shipping')
                ->timeout(3)
                ->get(config('services.shipping.url') . "/shipping/{$orderId}"),

            $pool
                ->as('products')
                ->timeout(3)
                ->get(config('services.product.url') . '/products', [
                    'ids' => implode(',', $order['product_ids']),
                ]),
        ]);

        // 3. Gather — cavabları birləşdir; hər biri müstəqil
        return [
            'order'    => $this->formatOrder($order),
            'customer' => $results['customer']->ok()
                ? $results['customer']->json()
                : null,     // WHY: customer olmasa da sifariş göstərilə bilər
            'shipping' => $results['shipping']->ok()
                ? $results['shipping']->json()
                : ['status' => 'unknown', 'eta' => null],
            'products' => $results['products']->ok()
                ? $this->indexByProductId($results['products']->json())
                : [],
            '_meta' => [
                'has_partial_failure' => collect($results)->some(fn($r) => !$r->ok()),
            ],
        ];
    }

    private function formatOrder(array $order): array
    {
        return [
            'id'         => $order['id'],
            'status'     => $order['status'],
            'items'      => $order['items'],
            'total'      => $order['total'],
            'created_at' => $order['created_at'],
        ];
    }

    private function indexByProductId(array $products): array
    {
        return collect($products)->keyBy('id')->toArray();
    }
}
```

```php
<?php

// Controller — composition qatını çağırır
class OrderDetailController extends Controller
{
    public function __construct(
        private OrderDetailComposer $composer,
    ) {}

    public function show(string $orderId, Request $request): JsonResponse
    {
        try {
            $data = $this->composer->compose($orderId, $request->user()->id);
            return response()->json($data);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // OrderService-in özü fail olarsa — 503
            return response()->json([
                'error' => 'Sifariş məlumatı əldə edilə bilmədi',
            ], 503);
        }
    }
}
```

```php
<?php

// Guzzle ilə daha çevik async composition
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Exception\RequestException;

class DashboardComposer
{
    public function __construct(private Client $http) {}

    public function compose(int $userId): array
    {
        // Promise-lar başlayır — async
        $promises = [
            'user'            => $this->http->getAsync("/users/{$userId}", ['timeout' => 2]),
            'recent_orders'   => $this->http->getAsync("/orders?user_id={$userId}&limit=5", ['timeout' => 3]),
            'recommendations' => $this->http->getAsync("/recommendations/{$userId}", ['timeout' => 2]),
            'notifications'   => $this->http->getAsync("/notifications?user_id={$userId}&unread=1", ['timeout' => 2]),
        ];

        // settle() — hamısı tamamlanana qədər gözlə, fail olanlar exception atmır
        $results = Utils::settle($promises)->wait();

        return [
            'user'            => $this->extract($results['user']),
            'recent_orders'   => $this->extract($results['recent_orders'], default: []),
            'recommendations' => $this->extract($results['recommendations'], default: []),
            'notifications'   => $this->extract($results['notifications'], default: []),
        ];
    }

    private function extract(array $settled, mixed $default = null): mixed
    {
        if ($settled['state'] === 'fulfilled') {
            return json_decode($settled['value']->getBody()->getContents(), true);
        }

        // WHY: partial failure-da default qaytarılır, composition ölmür
        return $default;
    }
}
```

```php
<?php

// N+1 composition — pis nümunə və həlli
class OrderListComposer
{
    // YANLIŞ — N+1 composition
    public function composeBad(array $orders): array
    {
        return array_map(function ($order) {
            // 20 sifariş = 20 ayrı HTTP call!
            $customer = Http::get("/customers/{$order['customer_id']}")->json();
            return array_merge($order, ['customer' => $customer]);
        }, $orders);
    }

    // DÜZGÜN — batch composition
    public function composeGood(array $orders): array
    {
        // Bütün customer ID-lərini topla
        $customerIds = array_unique(array_column($orders, 'customer_id'));

        // Batch sorğu — bir HTTP call
        $customers = Http::get('/customers', [
            'ids' => implode(',', $customerIds),
        ])->json();

        // Index et
        $customerIndex = collect($customers)->keyBy('id')->toArray();

        // Map et — HTTP call yoxdur
        return array_map(function ($order) use ($customerIndex) {
            return array_merge($order, [
                'customer' => $customerIndex[$order['customer_id']] ?? null,
            ]);
        }, $orders);
    }
}
```

```php
<?php

// Caching ilə composition — tez-tez dəyişməyən data üçün
class CachedProductComposer
{
    public function getProducts(array $productIds): array
    {
        $cached   = [];
        $missing  = [];

        // Cache-dən olan-olmayan ayır
        foreach ($productIds as $id) {
            $data = Cache::get("product:{$id}");
            if ($data !== null) {
                $cached[$id] = $data;
            } else {
                $missing[] = $id;
            }
        }

        // Yalnız cache-dən olmayan-ları fetch et
        if (!empty($missing)) {
            $fetched = Http::get('/products', ['ids' => implode(',', $missing)])->json();

            foreach ($fetched as $product) {
                // WHY: məhsul data-sı nadir dəyişir — 5 dəqiqə cache kifayətdir
                Cache::put("product:{$product['id']}", $product, now()->addMinutes(5));
                $cached[$product['id']] = $product;
            }
        }

        return $cached;
    }
}
```

## Praktik Tapşırıqlar

1. `Http::pool()` ilə 4 servisli composition yazın: bir servis timeout verəndə 2 saniyədən sonra default qayıtmalı; hamısı parallel çağrılmalı; response-da `_meta.partial_failure` flag olmalıdır
2. N+1 composition anti-pattern-i tapın (mövcud bir controller-dən): hər entity üçün ayrı HTTP call tapın; batch endpoint ilə əvəzləyin; əvvəl/sonra latency ölçün
3. `OrderSummaryComposer` yazın: bir siyahıda 20+ sifarişin hər biri üçün customer datası lazımdır; batch fetch + in-memory index strategiyasını tətbiq edin; PHPUnit test yazın: 20 sifariş → yalnız 1 HTTP call customer service-ə
4. Caching layer əlavə edin: product data 5 dəqiqə cache-lənsin; cache-miss olanda batch fetch; Redis ile test edin, cache-hit olanda sıfır HTTP call olsun

## Əlaqəli Mövzular

- [BFF Pattern](09-bff-pattern.md) — API Composition BFF-in əsas alətidir
- [CQRS Read Model](14-cqrs-read-model-projection.md) — cross-service JOIN lazım olanda composition alternatividir
- [Choreography vs Orchestration](11-choreography-vs-orchestration.md) — composition da bir növ orchestration-dır
- [Bulkhead Pattern](07-bulkhead-pattern.md) — hər downstream servis üçün ayrı timeout/connection pool
