# Lazy Loading vs Eager Loading (Middle ⭐⭐)

## İcmal

Lazy Loading — Eloquent relation-larına ilk dəfə əl atdıqda ayrıca SQL query atılmasıdır. Eager Loading isə `with()` ilə əsas sorğu ilə birlikdə relation-ları əvvəlcədən yükləməkdir. N+1 problemi — 1 sorğu ilə gətirilən N model üçün N əlavə sorğu atılması — Lazy Loading-in yanlış istifadəsinin əsas simptomudur.

## Niyə Vacibdir

100 sifariş listəsini göstərən bir API endpoint düşünün. `$order->user->name` yazılırsa hər sifariş üçün ayrı SQL gedər — 101 query. Production-da bu minlərlə sorğuya çevrilir, DB load artır, response time uzanır. Eager Loading ilə bu 2-3 sorğuya endirilir. N+1 problemi PHP/Laravel layihələrinin ən çox rast gəlinən performance problemidir.

## Əsas Anlayışlar

- **Lazy Loading**: `$order->user` — əl atanda SQL atılır; `SELECT * FROM users WHERE id = ?`
- **Eager Loading**: `Order::with('user')` — əsas sorğu ilə birlikdə; `SELECT * FROM users WHERE id IN (?)`
- **N+1 problem**: 1 list sorğusu + N relation sorğusu; N = model sayı
- **`preventLazyLoading()`**: Laravel 8.10+ — development-də lazy loading cəhdi exception atar
- **`with()` vs `load()`**: `with()` sorğu zamanı; `load()` model artıq yüklənmişdən sonra
- **`loadMissing()`**: yalnız yüklənməmişlərə əl atar; artıq yüklüdürsə skip edir
- **Nested eager loading**: `with('items.product.category')` — dərin relation zənciri
- **Counted eager loading**: `withCount('items')` — count üçün ayrıca query açmır

## Praktik Baxış

### Real istifadə

- API endpoint-lər — JSON resource relation-larla
- Admin panel — list view-lar; hər sətirdə relation data
- Report generation — aggregate data ilə birlikdə relation
- Export — CSV/Excel-də relation field-ləri

### Trade-off-lar

- **Eager loading**: N+1 yoxdur; amma həmişə load olunan data bəzən istifadə olunmur — unused JOIN
- **Lazy loading**: sadədir, qısa kod; amma N+1 riski yüksəkdir; production-da aşkarlana bilmir
- **`select()` ilə birlikdə**: yalnız lazım olan sütunları seçmək — memory istifadəsini azaldır

### İstifadə etməmək

- Həmişə eager load etmək: list yalnız bir model göstərirsə, relation lazım olmaya bilər — conditional eager load
- `with('everything')` — bütün relation-ları həmişə yükləmək: bəzən unused data DB-ni yük altına alır
- `preventLazyLoading()` production-da: development toolunda qalsın, production exception-a çevrilir

### Common mistakes

1. Controller-da eager load etməyi unutmaq — resource/presenter N+1 yaradır
2. API Resource içindən `$this->relation` — resource yüklənmə statusunu bilmir
3. `load()` ilə döngü içindən çağırmaq — N+1 yenidən yaranır
4. Production-da `preventLazyLoading()` — caught olmayan exception production-ı çökdürür

### Anti-Pattern Nə Zaman Olur?

**N+1 problemi — klassik nümunə:**

```php
// BAD — N+1
class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::paginate(20); // 1 sorğu: SELECT * FROM orders LIMIT 20

        return response()->json(
            $orders->map(function (Order $order) {
                return [
                    'id'       => $order->id,
                    'customer' => $order->user->name,   // +1 sorğu PER ORDER
                    'items'    => $order->items->count(), // +1 sorğu PER ORDER
                ];
                // Nəticə: 1 + 20 + 20 = 41 sorğu
            })
        );
    }
}

// GOOD — 3 sorğu
class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::with(['user', 'items'])  // Eager load
                       ->paginate(20);
        // Sorğular: SELECT * FROM orders + SELECT * FROM users WHERE id IN (...) + SELECT * FROM order_items WHERE order_id IN (...)

        return response()->json(
            $orders->map(function (Order $order) {
                return [
                    'id'       => $order->id,
                    'customer' => $order->user->name,    // Cache-dən — sorğu yoxdur
                    'items'    => $order->items->count(), // Cache-dən — sorğu yoxdur
                ];
            })
        );
    }
}
```

**API Resource-da N+1:**

```php
// BAD — Resource relation yüklənib-yüklənmədiyini bilmir
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'customer' => $this->user->name,   // Hər resource üçün sorğu!
            'items'    => $this->items->count(), // Hər resource üçün sorğu!
        ];
    }
}

// Controller eager load etmir:
$orders = Order::paginate(20);                        // 1 sorğu
return OrderResource::collection($orders);             // + 20 + 20 = 41 sorğu!

// GOOD — Controller eager load edir, Resource `whenLoaded()` istifadə edir
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            // whenLoaded: yalnız eager load olunubsa daxil et; olmayanda null
            'customer' => $this->whenLoaded('user', fn() => $this->user->name),
            'items'    => $this->whenLoaded('items', fn() => $this->items->count()),
        ];
    }
}

// Controller:
$orders = Order::with(['user', 'items'])->paginate(20); // 3 sorğu
return OrderResource::collection($orders);               // Sorğu yoxdur
```

## Nümunələr

### Ümumi Nümunə

Bir kitabxana sistemi düşünün. 50 kitabın listəsini çıxarırsınız. Hər kitabın müəllifi, naşiri, kateqoriyası lazımdır. Lazy Loading: hər kitab üçün "müəllif kim?", "naşir kim?", "kateqoriya nədir?" — 150 əlavə sorğu. Eager Loading: "bu 50 kitabın müəllifləri, naşirləri, kateqoriyaları hamısını gətir" — 3 sorğu.

### PHP/Laravel Nümunəsi

**N+1 aşkar etmək — `preventLazyLoading()`:**

```php
<?php

// AppServiceProvider.php — yalnız development-də aktiv et
namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Development-də: lazy loading cəhdi → exception
        // Production-da: log et, amma exception ATMA
        Model::preventLazyLoading(! app()->isProduction());

        // Production-da: exception əvəzinə log et
        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            $message = "Lazy loading violation on model [{$model::class}] relation [{$relation}]";

            if (app()->isProduction()) {
                logger()->warning($message);   // Production: log, crash yox
            } else {
                throw new \Exception($message); // Dev: crash, düzəlt!
            }
        });
    }
}
```

**Eager loading üsulları:**

```php
<?php

// 1. with() — sorğu zamanı eager load
$orders = Order::with(['user', 'items'])->get();
// SQL: SELECT * FROM orders
//      SELECT * FROM users WHERE id IN (1, 2, 3, ...)
//      SELECT * FROM order_items WHERE order_id IN (1, 2, 3, ...)

// 2. Nested eager loading — dərin relation zənciri
$orders = Order::with(['items.product.category'])->get();
// SQL: + SELECT * FROM products WHERE id IN (...)
//       + SELECT * FROM categories WHERE id IN (...)

// 3. withCount — count üçün ayrı sorğu açmır
$orders = Order::withCount(['items', 'statusTransitions'])->get();
// $order->items_count — həmişə hazırdır; items yüklənmədən

// 4. withSum, withMin, withMax, withAvg
$orders = Order::withSum('items', 'quantity')->get();
// $order->items_sum_quantity

// 5. Conditional eager loading
$orders = Order::with([
    'user',
    'items' => function ($query) {
        $query->where('quantity', '>', 0)->select(['id', 'order_id', 'product_id', 'quantity']);
    },
])->get();

// 6. load() — model artıq yüklənibsə sonradan əlavə et
$order = Order::find(1);
// Sonradan lazım olduğu aydın oldu:
$order->load(['user', 'items']);

// 7. loadMissing() — yalnız yüklənməmişlərə əl atar
$order->loadMissing('user'); // user artıq yüklüdürsə heç nə etmir

// 8. loadMorph, loadCount, loadSum
$order->loadCount('items');
$order->loadSum('items', 'quantity');
```

**Performans müqayisəsi — debugbar ilə:**

```php
<?php

// Laravel Debugbar quraşdırma:
// composer require barryvdh/laravel-debugbar --dev

// app/Http/Controllers/OrderController.php
class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        // TEST 1: N+1
        $ordersWithN1 = Order::paginate(20);
        foreach ($ordersWithN1 as $order) {
            $name = $order->user->name;    // +1 sorğu hər order üçün
            $count = $order->items->count(); // +1 sorğu hər order üçün
        }
        // Debugbar: 41 queries, ~150ms

        // TEST 2: Eager loading
        $ordersEager = Order::with(['user', 'items'])->paginate(20);
        foreach ($ordersEager as $order) {
            $name = $order->user->name;     // Cache-dən
            $count = $order->items->count(); // Cache-dən
        }
        // Debugbar: 3 queries, ~15ms

        return response()->json(['message' => 'Check debugbar']);
    }
}
```

**Spesifik sütunlar seçmək — memory optimizasiyası:**

```php
<?php

// BAD — bütün sütunlar yüklənir; user-in password, remember_token, etc. də gəlir
$orders = Order::with('user')->get();

// GOOD — yalnız lazım olan sütunlar
$orders = Order::with(['user:id,name,email'])->get();
// SQL: SELECT id, name, email FROM users WHERE id IN (...)

// Daha kompleks — constrained eager loading
$orders = Order::with([
    'user' => fn($q) => $q->select(['id', 'name', 'email']),
    'items' => fn($q) => $q->select(['id', 'order_id', 'product_id', 'quantity', 'price'])
                            ->with(['product:id,name,sku']),
])->paginate(20);
```

**`when()` ilə conditional eager loading:**

```php
<?php

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        // Yalnız detail view-da items lazımdır; list-də yox
        $query->when($request->boolean('include_items'), function ($q) {
            $q->with('items.product');
        });

        // Yalnız admin üçün user data
        $query->when(auth()->user()->isAdmin(), function ($q) {
            $q->with('user');
        });

        $orders = $query->paginate(20);

        return OrderResource::collection($orders);
    }
}
```

**N+1 debug prosesi — real nümunə:**

```php
<?php

// 1. PROBLEM: API endpoint-in respons time 2+ saniyədir

// 2. Debugbar ilə yoxlama:
// Database: 347 queries in 1840ms

// 3. Problem tapılır — OrderResource içindən:
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'customer' => $this->user->name,           // N sorğu
            'total'    => $this->total,
            'item_count' => $this->items->count(),     // N sorğu
            'last_status'=> $this->statusTransitions   // N sorğu
                               ->sortByDesc('created_at')
                               ->first()?->to_state,
        ];
    }
}

// 4. HƏLLİ — controller-da eager load + Resource-da whenLoaded
$orders = Order::with([
    'user:id,name',
    'items',
    'statusTransitions' => fn($q) => $q->latest()->limit(1),
])->withCount('items')
  ->paginate(20);

// Resource:
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'customer'   => $this->whenLoaded('user', fn() => $this->user->name),
            'total'      => $this->total,
            'item_count' => $this->items_count, // withCount — sorğusuz
            'last_status'=> $this->whenLoaded('statusTransitions',
                               fn() => $this->statusTransitions->first()?->to_state
                           ),
        ];
    }
}

// 5. Nəticə: 347 queries → 4 queries; 1840ms → 45ms
```

## Praktik Tapşırıqlar

1. Laravel Debugbar quraşdırın; mövcud bir list endpoint açın; query sayını görün; eager loading əlavə edərək query sayını minimuma endirin
2. `AppServiceProvider`-da `preventLazyLoading()` aktiv edin; bütün test suite-i işlədin; çıxan exception-ları düzəldin; test-lər keçəndə yeni N+1 qalmadığını bilin
3. `Order::with(['user', 'items.product', 'statusTransitions'])` sorğusu üçün yalnız lazım olan sütunları seçin (`user:id,name`, `items:id,order_id,quantity`, `products:id,name`); before/after memory istifadəsini `memory_get_usage()` ilə ölçün

## Əlaqəli Mövzular

- [12-presenter-view-model.md](12-presenter-view-model.md) — API Resource-da `whenLoaded()` ilə N+1 önləmək
- [01-repository-pattern.md](01-repository-pattern.md) — Repository-də eager loading strategiyası
- [../ddd/04-ddd-aggregates.md](../ddd/04-ddd-aggregates.md) — Aggregate yükləmə; consistency boundary
- [../general/02-code-smells-refactoring.md](../general/02-code-smells-refactoring.md) — N+1 bir code smell-dir
