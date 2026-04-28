# BFF Pattern (Backend for Frontend) (Senior ⭐⭐⭐)

## İcmal

BFF (Backend for Frontend) — hər frontend növü üçün ayrı, xüsusi backend layer. Sam Newman tərəfindən adlandırılan bu pattern, generic API-nin bütün client-lərə eyni cavabı qaytardığı vəziyyəti həll edir. Mobile BFF mobil client-ə, Web BFF brauzerə, Partner BFF xarici tərəfdaşlara optimallaşdırılmış cavablar qaytarır.

## Niyə Vacibdir

Generic API anti-pattern: `GET /orders/42` mobile-a 2KB, web-ə 15KB, admin-ə 40KB data lazımdır — amma eyni endpoint hamısını qaytarır. Mobile lazımsız data alır (bandwidth israf), web client transformation edir (iş çoxalır), API versioning bütün client-lər arasında konflikt yaradır. BFF hər frontend-in öz data contract-ına sahib olmasını təmin edir. "Frontend team öz backend-ini idarə edir" prinsipini reallaşdırır.

## Əsas Anlayışlar

- **BFF**: bir frontend tipi üçün xüsusi backend layer; aggregation, transformation, formatting
- **API Gateway**: authentication, rate limiting, SSL termination — client-agnostic; BFF-dən fərqlidir
- **Team ownership**: mobile team Mobile BFF-i, web team Web BFF-i idarə edir — API versioning konflikti yoxdur
- **Aggregation**: BFF bir neçə downstream service-i çağırıb nəticəni birləşdirir
- **Response shaping**: cavabı client-in ehtiyacına görə şəkillendirir (field seçimi, format çevirmə)
- **Graceful degradation**: bir downstream service fail olsa partial cavab qaytarır

## Praktik Baxış

- **Real istifadə**: e-commerce (mobile vs web vs admin panel fərqli data tələb edir), fintech (banking mobile app vs web dashboard), SaaS (customer-facing vs partner API vs internal admin)
- **Trade-off-lar**: hər BFF öz codebase-idir → duplication riski; team autonomy artır → coordination azalır; downstream service contract-ları dəyişəndə BFF güncəllənməlidir
- **İstifadə etməmək**: tək frontend tipi varsa (sadə monolith); az sayda client eyni data tələb edirsə; kiçik team birdən çox codebase saxlaya bilmirsə
- **Common mistakes**: BFF-ə business logic yerləşdirmək (o servislərə aiddir); BFF-ləri ümumi library ilə tight coupling etmək; GraphQL-i BFF yerinə tətbiq edib BFF-in üstünlüklərini itirmək

## Anti-Pattern Nə Zaman Olur?

**Çox sayda BFF — kod duplikasiyası:**
Mobile BFF, Web BFF, Tablet BFF, Smart TV BFF — hamısında eyni auth middleware, eyni error handling, eyni logging yazılır. Shared library yarat, amma hər BFF öz contract-ını qoruyur. 4 ayrı BFF-in hər birini maintain etmək overhead-dir — client-lər eyni data tələb edirsə, sadə API Gateway + response shaping bəs edə bilər.

**BFF-ə business logic yerləşdirmək:**
`MobileBFF.calculateDiscount()` — bu məntiq servislərə aiddir. BFF yalnız şəkilləndirir, hesablamır. Business logic BFF-dən başqa servislərə köçürülmədikcə duplikasiya artır və testability azalır. BFF bir service gibi davranmağa başlasa, əslində domain service yaratmısınız.

**Tək team birdən çox BFF idarə edir:**
BFF-in özü bottleneck olur — bütün frontend dəyişiklikləri bir team-in deployment-ını gözləyir. BFF ownership mütləq frontend team-ə verilməlidir; əks halda API Gateway ilə eyni mərkəzləşmə problemi yaranır.

**BFF-i API Gateway ilə əvəz etmək:**
API Gateway authentication, rate limiting, routing edir. BFF isə data aggregation, transformation, client-specific logic edir. İkisi fərqli məsuliyyətdir. BFF-siz yalnız Gateway olan arxitekturada client-lər mürəkkəb composition öz tərəflərindən etmək məcburiyyətindədir.

## Nümunələr

### Ümumi Nümunə

E-commerce sifariş detayı səhifəsi:

- **Mobile BFF**: `id`, `status`, `total`, `item_count`, `tracking_number` — 2KB, compressed JSON
- **Web BFF**: bütün item-lər, customer info, shipping timeline, invoice PDF link — 15KB, rich JSON
- **Partner BFF**: standarlaşdırılmış, versioned, SLA-li API — breaking change yoxdur

Hər BFF downstream-dən eyni servisləri çağırır amma fərqli data seçir, fərqli format qaytarır.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\BFF\Mobile\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

// Mobile BFF — bandwidth minimal, kompressiyaya uyğun
class MobileOrderController extends Controller
{
    public function show(string $orderId): JsonResponse
    {
        // Mobile-a yalnız lazım olan data — 2 servis çağrısı
        $results = Http::pool(fn($pool) => [
            $pool->as('order')->timeout(3)->get("/internal/orders/{$orderId}"),
            $pool->as('shipping')->timeout(3)->get("/internal/shipping/{$orderId}"),
        ]);

        $order   = $results['order']->ok()   ? $results['order']->json()   : null;
        $shipping = $results['shipping']->ok() ? $results['shipping']->json() : null;

        if (!$order) {
            return response()->json(['error' => 'Sifariş tapılmadı'], 404);
        }

        // WHY: Mobile screen-də yalnız bu field-lər göstərilir
        // item_count göstərilir, items[] array-i deyil — bandwidth qənaəti
        return response()->json([
            'id'         => $order['id'],
            'status'     => $order['status'],
            'total'      => number_format($order['total'], 2),
            'item_count' => $order['item_count'],
            'tracking'   => $shipping['tracking_number'] ?? null,
            'eta'        => isset($shipping['estimated_delivery'])
                ? date('M j', strtotime($shipping['estimated_delivery']))
                : null,
        ]);
    }
}
```

```php
<?php

namespace App\BFF\Web\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

// Web BFF — rich data, parallel calls
class WebOrderController extends Controller
{
    public function show(string $orderId): JsonResponse
    {
        // Əvvəl order al (customer_id və s. lazımdır)
        $order = Http::timeout(5)
            ->get("/internal/orders/{$orderId}")
            ->throw()
            ->json();

        // Paralel sorğular — sequential 150ms → parallel 60ms
        $results = Http::pool(fn($pool) => [
            $pool->as('customer')->timeout(3)->get("/internal/customers/{$order['customer_id']}"),
            $pool->as('shipping')->timeout(3)->get("/internal/shipping/{$orderId}/details"),
            $pool->as('timeline')->timeout(3)->get("/internal/orders/{$orderId}/timeline"),
            $pool->as('invoice')->timeout(3)->get("/internal/orders/{$orderId}/invoice-url"),
        ]);

        return response()->json([
            'order' => [
                'id'       => $order['id'],
                'status'   => $order['status'],
                'items'    => $order['items'],         // Tam siyahı — web-ə lazımdır
                'subtotal' => $order['subtotal'],
                'tax'      => $order['tax'],
                'total'    => $order['total'],
            ],
            'customer'    => $results['customer']->ok() ? $results['customer']->json() : null,
            'shipping'    => $results['shipping']->ok() ? $results['shipping']->json() : ['status' => 'unknown'],
            'timeline'    => $results['timeline']->ok() ? $results['timeline']->json() : [],
            'invoice_url' => $results['invoice']->ok()  ? $results['invoice']->json('url') : null,
        ]);
    }

    // Dashboard — partial failure tolerant
    public function dashboard(): JsonResponse
    {
        $results = Http::pool(fn($pool) => [
            $pool->as('stats')->timeout(2)->get('/internal/stats/today'),
            $pool->as('recent_orders')->timeout(2)->get('/internal/orders?limit=5'),
            $pool->as('alerts')->timeout(2)->get('/internal/alerts/active'),
            $pool->as('revenue')->timeout(2)->get('/internal/revenue/monthly'),
        ]);

        // WHY: bir servis fail olsa dashboard tamamilə boş olmamalıdır
        return response()->json([
            'stats'         => $results['stats']->ok()         ? $results['stats']->json()         : null,
            'recent_orders' => $results['recent_orders']->ok() ? $results['recent_orders']->json() : [],
            'alerts'        => $results['alerts']->ok()        ? $results['alerts']->json()        : [],
            'revenue'       => $results['revenue']->ok()       ? $results['revenue']->json()       : null,
            '_meta' => [
                'partial_failure' => collect($results)->some(fn($r) => !$r->ok()),
            ],
        ]);
    }
}
```

```php
<?php

namespace App\BFF\Partner\Controllers;

// Partner BFF — versioned, stable contract, SLA-li
// WHY: tərəfdaşlar breaking change-ə dözə bilmir — versioning kritikdir
class PartnerOrderController extends Controller
{
    // v1 — original contract; heç vaxt dəyişdirilmir
    public function showV1(string $orderId): JsonResponse
    {
        $order = Http::get("/internal/orders/{$orderId}")->throw()->json();

        return response()->json([
            'order_id' => $order['id'],           // WHY: tərəfdaşlar bu field adını gözləyir
            'status'   => $this->mapStatus($order['status']), // internal → external status
            'amount'   => $order['total'],
            'currency' => $order['currency'],
        ]);
    }

    // v2 — yeni field-lər əlavə olunub, amma köhnə field-lər qorunub
    public function showV2(string $orderId): JsonResponse
    {
        $order = Http::get("/internal/orders/{$orderId}")->throw()->json();

        return response()->json([
            'order_id'   => $order['id'],
            'status'     => $this->mapStatus($order['status']),
            'amount'     => $order['total'],
            'currency'   => $order['currency'],
            'line_items' => $order['items'] ?? [],  // v2-yə əlavə; v1-dəki field-lər pozulmayıb
        ]);
    }

    private function mapStatus(string $internal): string
    {
        return match ($internal) {
            'pending'   => 'AWAITING_PAYMENT',
            'confirmed' => 'CONFIRMED',
            'shipped'   => 'IN_TRANSIT',
            'delivered' => 'DELIVERED',
            'cancelled' => 'CANCELLED',
            default     => 'UNKNOWN',
        };
    }
}
```

```php
<?php

// Laravel route qruplaşdırması — hər BFF ayrı route group
// routes/api.php

// Mobile BFF — /mobile/ prefix, own middleware
Route::prefix('mobile')
    ->middleware(['auth:sanctum', 'mobile-rate-limit'])
    ->group(function () {
        Route::get('/orders/{id}', [MobileOrderController::class, 'show']);
        Route::get('/orders', [MobileOrderController::class, 'index']);
    });

// Web BFF — /web/ prefix, session auth
Route::prefix('web')
    ->middleware(['auth:web', 'web-rate-limit'])
    ->group(function () {
        Route::get('/orders/{id}', [WebOrderController::class, 'show']);
        Route::get('/dashboard', [WebOrderController::class, 'dashboard']);
    });

// Partner BFF — /partner/ prefix, API key auth, versioned
Route::prefix('partner/v1')
    ->middleware(['auth:partner-key', 'partner-rate-limit'])
    ->group(function () {
        Route::get('/orders/{id}', [PartnerOrderController::class, 'showV1']);
    });

Route::prefix('partner/v2')
    ->middleware(['auth:partner-key', 'partner-rate-limit'])
    ->group(function () {
        Route::get('/orders/{id}', [PartnerOrderController::class, 'showV2']);
    });
```

## Praktik Tapşırıqlar

1. Mövcud generic `OrderController`-i götürün; Mobile BFF və Web BFF-ə ayırın; hər biri fərqli field seçimi qaytarsın; shared auth middleware saxlansın
2. Laravel `Http::pool()` ilə parallel aggregation yazın: 3 downstream servis, hər biri fail ola bilər; partial failure-da degraded response qaytarın; response time-ı ölçün
3. Partner BFF üçün `/partner/v1` və `/partner/v2` yazın; v2 v1-in bütün field-lərini qorusun; breaking change test edin: v1 response-u olan bir test yazın, v2 dəyişiklikləri onu sındırmamalıdır
4. BFF-in API Gateway ilə birlikdə işləməsini qurun: Nginx/Laravel middleware-də auth, rate limiting; BFF-də aggregation; downstream-də business logic — hər layer öz məsuliyyətini bilsin

## Əlaqəli Mövzular

- [API Composition Pattern](10-api-composition-pattern.md) — BFF-in əsas aggregation mexanizmi
- [Anti-Corruption Layer](08-anti-corruption-layer.md) — downstream legacy sistem model-ləri BFF-də translate edilir
- [CQRS](01-cqrs.md) — BFF query tərəfi üçün read model-dən oxuya bilər
- [Choreography vs Orchestration](11-choreography-vs-orchestration.md) — BFF downstream-ləri orchestrate edir
- [Circuit Breaker](16-circuit-breaker.md) — downstream servis çöküşündə BFF-in fail-fast davranışı
- [Retry Pattern](17-retry-pattern.md) — BFF-dən downstream-ə transient failure-larda retry
- [Repository Pattern](../laravel/01-repository-pattern.md) — downstream data qatı
- [Service Layer](../laravel/02-service-layer.md) — BFF-in çağırdığı application service-lər
