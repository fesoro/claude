# Presenter / View Model (Middle ⭐⭐)

## İcmal

Presenter / View Model pattern — domain model-in (Eloquent model) birbaşa view-ya və ya API response-a çevrilməsinin qarşısını alır. Domain model storage structure-ı əks etdirir; View Model isə görüntülənmə məqsədi üçün optimize olunmuş representation-dır. Laravel-də `JsonResource` (API Resource) bu pattern-in ən geniş istifadə olunan formasıdır.

## Niyə Vacibdir

`$order->toArray()` və ya `$order->toJson()` API contract-ı olursa, DB sütunu adı dəyişdikdə API pozulur. `created_at` timestamp-ni `"2024-01-15T10:30:00Z"` kimi göstərmək, `total` dollar-ı `"$1,250.00"` kimi formatlamaq, `user_id` əvəzinə `author: {name, avatar}` qaytarmaq — bunlar Presenter-in işidir. Domain model bu formatlamadan xəbərsiz olmalıdır.

## Əsas Anlayışlar

- **Presenter**: view-specific formatting logic-i encapsulate edən class; template/Blade view üçün
- **API Resource (`JsonResource`)**: Laravel-in built-in API presentation class-ı; `toArray()` override
- **View Model**: controller-ın view-ya ötürdüyü strongly-typed data bag; Blade template üçün
- **API versioning**: `OrderApiV1Resource` vs `OrderApiV2Resource` — eyni model, fərqli representation
- **Resource Collection**: `JsonResource::collection()` — list halında serialize etmək
- **Conditional fields**: `$this->when()`, `$this->mergeWhen()` — context-based field inclusion

## Praktik Baxış

### Real istifadə

- `OrderResource` — API response: snake_case field-lər, formatted datetime, nested relations
- `OrderPresenter` — Blade view: currency formatting, status label, human-readable dates
- `UserPublicResource` — authentication olmadan göstərilən məhdud user data
- `OrderApiV2Resource` — API v2 üçün yeni format, geriyə uyğun deyil
- `ProductSearchResource` — axtarış nəticəsi üçün minimal fields

### Trade-off-lar

- **Müsbət**: domain model API contract-dan ayrılır; DB schema dəyişikliyindən API qorunur; formatting logic bir yerdə; API versioning asanlaşır
- **Mənfi**: əlavə class; Resource-da N+1 risk (relation əl ilə yüklənmədikdə); çox Resource class layihəni şişidir
- **`toArray()` vs Resource**: `toArray()` sadədir amma API contract-a bağlanır; Resource daha flexible

### İstifadə etməmək

- Internal admin tools-da API yalnız bir client tərəfindən istifadə olunursa — sadə `toArray()` kifayət edir
- Prototip mərhələsindəki layihələrdə — əvvəlcə `toArray()`, sonra refactor
- Yalnız bir endpoint-dən çağırılan, versiyalanmayan sadə endpoint-lər üçün

### Common mistakes

1. Resource-da business logic etmək — `if ($this->discount > 0) { $this->applyDiscount(); }` — bu service-in işidir
2. Resource-da DB query etmək — `$this->orders()->count()` Resource içindən — N+1 yaradır; eager load et
3. Eloquent Accessor-u API contract kimi istifadə etmək — `$order->formatted_price` → model dəyişdikdə API pozulur
4. Resource-da authorization — `$this->when(auth()->user()->isAdmin(), ...)` — policy istifadə et

### Anti-Pattern Nə Zaman Olur?

**Eloquent model birbaşa API contract:**

```php
// BAD — model birbaşa serialize olunur; DB schema = API contract
class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        // $order->toArray() — DB column adları, timestamp format, hidden olmayan hər şey görsənir
        return response()->json($order->toArray());
        // API response:
        // {"id":1, "user_id":5, "created_at":"2024-01-15 10:30:00", "updated_at":"..."}
        // user_id → user obyekti istəsən, column adı dəyişsə → API pozulur
    }
}

// GOOD — Resource API surface-ni nəzarət edir
class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        return response()->json(new OrderResource($order));
        // API response:
        // {"id":1, "status":"paid", "total":"$125.00", "created_at":"2024-01-15T10:30:00Z",
        //  "customer": {"id":5, "name":"Ali Əliyev"}}
        // DB dəyişsə Resource dəyişir — API sabit qalır
    }
}
```

**Resource-da N+1 yaratmaq:**

```php
// BAD — Resource içindən relation yükləmək
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'total'    => $this->total,
            // Bu hər order üçün ayrı SQL query edir — N+1!
            'customer' => $this->user->name,
            'items'    => $this->items->count(), // Yenə N+1
        ];
    }
}

// Controller-da eager load yoxdur
Order::paginate(20); // 20 order + 20 user query + 20 items query = 41 query!

// GOOD — Controller eager load edir
Order::with(['user', 'items'])->paginate(20); // 3 query, N+1 yoxdur
// Resource eyni, amma data artıq yüklüdür
```

**Presenter-ə business logic qoymaq:**

```php
// BAD — Presenter qiymət hesablayır; bu domain-in işidir
class OrderPresenter
{
    public function __construct(private Order $order) {}

    public function getTotal(): string
    {
        // Business logic burada — discount, tax calculation
        $discount = $this->order->coupon?->value ?? 0;
        $tax      = $this->order->subtotal * 0.18;
        $total    = $this->order->subtotal - $discount + $tax;
        return '$' . number_format($total, 2); // Prezentasiya + business qarışıqdır
    }
}

// GOOD — Presenter yalnız format edir; business hesablanmış gəlir
class OrderPresenter
{
    public function __construct(private Order $order) {}

    // order->total artıq hesablanmış — Presenter yalnız format edir
    public function getFormattedTotal(): string
    {
        return '$' . number_format($this->order->total, 2);
    }

    public function getStatusLabel(): string
    {
        return match($this->order->status) {
            'pending'   => 'Gözlənilir',
            'paid'      => 'Ödənilmişdir',
            'shipped'   => 'Göndərilmişdir',
            'delivered' => 'Çatdırılmışdır',
            'cancelled' => 'Ləğv edilmişdir',
            default     => $this->order->status,
        };
    }
}
```

## Nümunələr

### Ümumi Nümunə

Xəbər agentliyini düşünün. Jurnalistin yazdığı xəbər (domain model) verilənlər bazasında saxlanılır. Web sayt üçün: tam mətn, şəkillər, author bio. Mobil app üçün: qısa başlıq, thumbnail, excerpt. API v1 üçün: köhnə format. API v2 üçün: yeni format. Eyni domain model, fərqli Presenter/Resource-lar.

### PHP/Laravel Nümunəsi

**API Resource — Laravel-in built-in yolu:**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'     => $this->id,
            'status' => $this->status,

            // Formatted output — domain model-dən fərqli
            'total'             => '$' . number_format($this->total, 2),
            'total_cents'       => (int) ($this->total * 100), // Payment gateway üçün
            'created_at'        => $this->created_at->toIso8601String(), // ISO 8601
            'created_at_human'  => $this->created_at->diffForHumans(),  // "2 hours ago"

            // Nested resource — ayrı Resource class
            'customer' => new UserSummaryResource($this->whenLoaded('user')),

            // Conditional field — admin olduqda görünsün
            $this->mergeWhen($request->user()?->isAdmin(), [
                'internal_notes' => $this->internal_notes,
                'cost_price'     => $this->cost_price,
            ]),

            // Collection
            'items'    => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
        ];
    }

    // Wrapper — "data" key əlavə etmək
    public static function collection($resource): OrderResourceCollection
    {
        return new OrderResourceCollection($resource);
    }
}
```

**Resource Collection — list response üçün:**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderResourceCollection extends ResourceCollection
{
    public $collects = OrderResource::class;

    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total'        => $this->total(),
                'per_page'     => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page'    => $this->lastPage(),
            ],
        ];
    }
}
```

**Blade üçün Presenter class:**

```php
<?php

namespace App\Presenters;

use App\Models\Order;

class OrderPresenter
{
    public function __construct(
        private readonly Order $order,
    ) {}

    // Static factory — view-da `OrderPresenter::wrap($order)`
    public static function wrap(Order $order): self
    {
        return new self($order);
    }

    public function formattedTotal(): string
    {
        return number_format($this->order->total, 2, '.', ',') . ' AZN';
    }

    public function statusLabel(): string
    {
        return match($this->order->status) {
            'pending'   => 'Gözlənilir',
            'paid'      => 'Ödənilmişdir',
            'shipped'   => 'Göndərilmişdir',
            'delivered' => 'Çatdırılmışdır',
            'cancelled' => 'Ləğv edilmişdir',
            default     => ucfirst($this->order->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match($this->order->status) {
            'pending'   => 'badge-warning',
            'paid'      => 'badge-info',
            'shipped'   => 'badge-primary',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger',
            default     => 'badge-secondary',
        };
    }

    public function formattedDate(): string
    {
        return $this->order->created_at->format('d M Y, H:i');
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->order->status, ['pending', 'paid']);
    }

    // Delegate — presenter wraps model; direct access
    public function __get(string $name): mixed
    {
        return $this->order->{$name};
    }
}
```

**Blade view-da Presenter istifadəsi:**

```blade
{{-- resources/views/orders/show.blade.php --}}
@php $p = \App\Presenters\OrderPresenter::wrap($order) @endphp

<div class="order-card">
    <span class="badge {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
    <p>Sifariş #{{ $p->id }}</p>
    <p>Məbləğ: {{ $p->formattedTotal() }}</p>
    <p>Tarix: {{ $p->formattedDate() }}</p>

    @if ($p->canBeCancelled())
        <button>Ləğv et</button>
    @endif
</div>
```

**API Versioning — fərqli Resource class-lar:**

```php
<?php

namespace App\Http\Resources\V1;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'amount'     => $this->total, // V1: "amount" field adı
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

namespace App\Http\Resources\V2;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'     => $this->id,
            'status' => [
                'code'  => $this->status,
                'label' => $this->status_label, // V2: status object
            ],
            'total' => [                          // V2: "total" field adı + currency
                'amount'   => $this->total,
                'currency' => 'AZN',
                'formatted'=> number_format($this->total, 2) . ' AZN',
            ],
            'timestamps' => [                     // V2: timestamps object
                'created' => $this->created_at->toIso8601String(),
                'updated' => $this->updated_at->toIso8601String(),
            ],
        ];
    }
}

// Route versioning
Route::prefix('v1')->group(function () {
    Route::get('/orders/{order}', function (Order $order) {
        return new \App\Http\Resources\V1\OrderResource($order);
    });
});

Route::prefix('v2')->group(function () {
    Route::get('/orders/{order}', function (Order $order) {
        return new \App\Http\Resources\V2\OrderResource($order);
    });
});
```

**Controller — Resource ilə thin:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderController extends Controller
{
    public function index(): ResourceCollection
    {
        $orders = Order::with(['user', 'items'])    // Eager load — N+1 yoxdur
                        ->where('user_id', auth()->id())
                        ->latest()
                        ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        $order->load(['user', 'items.product', 'statusTransitions']);

        return new OrderResource($order);
    }
}
```

**Test etmək — Resource output yoxlamaq:**

```php
<?php

class OrderResourceTest extends TestCase
{
    public function test_resource_formats_total_correctly(): void
    {
        $order = Order::factory()->create(['total' => 1250.50]);

        $resource = new OrderResource($order);
        $array    = $resource->toArray(request());

        $this->assertEquals('$1,250.50', $array['total']);
    }

    public function test_admin_fields_hidden_from_non_admin(): void
    {
        $user  = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create();

        $this->actingAs($user);

        $resource = new OrderResource($order);
        $array    = $resource->toArray(request());

        $this->assertArrayNotHasKey('internal_notes', $array);
        $this->assertArrayNotHasKey('cost_price', $array);
    }

    public function test_admin_fields_visible_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create(['internal_notes' => 'VIP customer']);

        $this->actingAs($admin);

        $resource = new OrderResource($order);
        $array    = $resource->toArray(request());

        $this->assertEquals('VIP customer', $array['internal_notes']);
    }
}
```

## Praktik Tapşırıqlar

1. Mövcud bir controller-da `$user->toArray()` istifadəsini tapın; `UserResource` yaradın; bütün timestamp-ləri ISO 8601-ə çevirin; `password` field-ini gizləyin; controller-ı yeniləyin
2. `OrderPresenter` yazın: `formattedTotal()`, `statusLabel()`, `statusBadgeClass()`, `canBeCancelled()`; Blade template-ə inteqrasiya edin; unit test yazın (Eloquent olmadan)
3. `OrderResource` v1 və v2 yaradın — eyni Order model üçün; route prefix ilə versioning qurun; ikisini eyni anda test edin

## Əlaqəli Mövzular

- [13-lazy-loading-eager-loading.md](13-lazy-loading-eager-loading.md) — Resource-da N+1 problemi və həlli
- [10-action-class.md](10-action-class.md) — Action Resource qaytarır
- [../general/01-dto.md](../general/01-dto.md) — Presenter DTO-ya bənzər; lakin output-focused
- [../structural/04-proxy.md](../structural/04-proxy.md) — Presenter model-i wrap edir; Proxy pattern oxşarlığı
- [../structural/03-decorator.md](../structural/03-decorator.md) — Presenter model-ə behavior əlavə edir
