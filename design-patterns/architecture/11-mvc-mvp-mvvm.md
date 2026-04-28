# MVC, MVP, MVVM (Middle)

## İcmal

MVC, MVP, MVVM — UI application-larında concerns-ləri necə ayırmaq haqqında üç pattern-dir. **MVC** (Model-View-Controller): Laravel-in əsas pattern-i; Controller request-i alır, Model-dən data götürür, View-a ötürür. **MVP** (Model-View-Presenter): View passivdir, Presenter View-dan məsuldur; PHP-də nadir. **MVVM** (Model-View-ViewModel): ViewModel View-un state-ini idarə edir, data binding var; Vue.js, Livewire kontekstindədir.

## Niyə Vacibdir

Laravel developer kimi MVC-ni bilmək kifayət deyil — Livewire istifadə edəndə MVVM yazırsınız. Inertia.js ilə işlədikdə backend MVC + frontend MVVM kombinasiyası var. "Fat Controller" anti-pattern-ini tanımaq, business logic-i doğru yerə yerləşdirmək Senior səviyyənin əlamətidir. Həmçinin "bu Livewire component niyə bu qədər mürəkkəb oldu?" sualının cavabı çox vaxt MVVM prinsiplərinin pozulmasıdır.

## Əsas Anlayışlar

- **Model**: business data və qaydaları — Eloquent model, Service, Domain class
- **View**: istifadəçiyə göstərilən UI — Blade template, Vue component, React component
- **Controller** (MVC): HTTP request-i alır, Model-i çağırır, View-a data ötürür; thin olmalıdır
- **Presenter** (MVP): View ilə birlikdə işləyir, View passivdir; Presenter View metodlarını çağırır
- **ViewModel** (MVVM): View-un state-ini, əməliyyatlarını saxlayır; View data binding ilə ViewModel-i izləyir
- **Data Binding**: ViewModel dəyişdikdə View avtomatik yenilənir (Vue.js `reactive`, Livewire `$this->property`)
- **Fat Controller**: bütün business logic controller-dədir — MVC-nin ən məşhur anti-pattern-i
- **Two-Way Binding**: View dəyişikliyi ViewModel-ə, ViewModel dəyişikliyi View-a avtomatik əks olunur (Livewire `wire:model`)

## Praktik Baxış

- **MVC — nə vaxt**: ənənəvi Laravel API + Blade; server-side rendering; simple CRUD; komanda artıq MVC bilir
- **MVVM — nə vaxt**: real-time interactive UI; Livewire component-ləri; Vue/React ilə Inertia; state-heavy forms
- **MVP — nə vaxt**: PHP-də demək olar ki, istifadə olunmur; Android development-də populyardır
- **Trade-off-lar**: MVC = sadə, universaldır; MVVM = daha çox boilerplate amma interactive UI üçün daha uyğun; MVP = PHP ekosisteminin arasında yer tapmadı
- **Hansı hallarda istifadə etməmək**: sadə statik səhifə üçün Livewire/MVVM — over-engineering; mürəkkəb real-time form üçün ənənəvi MVC — çox Ajax call lazım olur
- **Common mistakes**: Controller-ə DB query yazmaq; Livewire component-ə business logic doldurmaqlı

### Anti-Pattern Nə Zaman Olur?

**Fat Controller** — MVC-nin ən geniş yayılmış pozuntusu. Controller-də validation, business logic, DB query, email göndərmə hamısı birlikdə — 200+ sətir controller metodu. Nəticə: test etmək mümkünsüzdür, eyni logic başqa controller-də lazım olanda kopyalanır, bir dəyişiklik bir neçə yeri sındırır. Qayda: Controller yalnız request al → service çağır → response qaytar.

**Fat Livewire Component** — MVVM-nin pozuntusu. Livewire component-ə ORM query, business rule, email göndərmə hamısı doldurulur. Component-i başqa kontekstdə (queue, API) istifadə etmək mümkünsüzləşir. ViewModel (Livewire component) yalnız UI state-i idarə etməlidir — business logic Service-ə aid olmalıdır.

---

## Nümunələr

### Ümumi Nümunə

**MVC**: müştəri brauzerə URL daxil edir → Router → Controller (request alır) → Model (data) → View (HTML) → brauzer.

**MVVM**: istifadəçi form doldurmağa başlayır → ViewModel (Livewire component) real-time state saxlayır → View (Blade/template) ViewModel-i izləyir → data dəyişdikdə View avtomatik yenilənir.

**MVP**: View yalnız Presenter-in dediklərini edir — "düyməyə basdım" deyir, Presenter nə etmək lazım olduğunu qərar verir.

### Kod Nümunəsi

**MVC — Laravel:**

```php
<?php

// ✅ Thin Controller — MVC-nin düzgün tətbiqi
namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    // Controller yalnız: request al → service çağır → response qaytar
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            userId: $request->user()->id,
            items:  $request->validated('items'),
        );

        return response()->json([
            'id'     => $order->id,
            'status' => $order->status,
            'total'  => $order->total,
        ], 201);
    }

    public function index(): \Illuminate\View\View
    {
        $orders = $this->orderService->getForUser(auth()->id());
        return view('orders.index', compact('orders')); // View-a data ötür
    }
}

// Service — business logic burada
namespace App\Services;

class OrderService
{
    public function __construct(
        private OrderRepository $orders,
        private InventoryService $inventory,
    ) {}

    public function create(int $userId, array $items): Order
    {
        $this->inventory->validateStock($items);
        $order = Order::create(['user_id' => $userId, 'status' => 'pending']);
        // ... items əlavə et, total hesabla
        return $order;
    }
}

// ❌ Fat Controller — anti-pattern
class BadOrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Validation controller-dədir
        $request->validate(['items' => 'required|array']);

        // Business logic controller-dədir
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if ($product->stock < $item['quantity']) {
                return response()->json(['error' => 'Stok yetərli deyil'], 422);
            }
        }

        // DB əməliyyatı controller-dədir
        $order = Order::create([...]);
        foreach ($request->items as $item) {
            $order->items()->create([...]);
        }

        // Email controller-dədir
        Mail::to(auth()->user())->send(new OrderConfirmation($order));

        return response()->json(['id' => $order->id]);
        // Bu controller test etmək mümkünsüzdür!
    }
}
```

**MVVM — Laravel Livewire:**

```php
<?php

namespace App\Livewire;

use App\Services\OrderService;
use Livewire\Component;
use Livewire\Attributes\Rule;

// Livewire Component = ViewModel
class CreateOrderForm extends Component
{
    // ViewModel state — View bu property-ləri izləyir (wire:model)
    #[Rule('required|array|min:1')]
    public array $items = [];

    public float $total = 0;
    public bool $loading = false;
    public ?string $successMessage = null;

    // ViewModel metodu — UI event-lərini idarə edir
    public function addItem(int $productId, int $quantity): void
    {
        $this->items[] = ['product_id' => $productId, 'quantity' => $quantity];
        $this->recalculateTotal(); // UI state yenilənir
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->recalculateTotal();
    }

    // Business logic Service-ə aid — ViewModel yalnız orkestr edir
    public function submit(OrderService $orderService): void
    {
        $this->validate();
        $this->loading = true;

        try {
            $order = $orderService->create(
                userId: auth()->id(),
                items:  $this->items,
            );

            $this->successMessage = "Sifariş #{$order->id} yaradıldı!";
            $this->reset(['items', 'total']); // State sıfırla
        } finally {
            $this->loading = false;
        }
    }

    private function recalculateTotal(): void
    {
        // Total hesabı — bu UI state-dir, business rule deyil
        $this->total = collect($this->items)->sum(function ($item) {
            $product = \App\Models\Product::find($item['product_id']);
            return ($product?->price ?? 0) * $item['quantity'];
        });
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.create-order-form');
    }
}
```

```blade
{{-- View — ViewModel-i (Livewire component-i) izləyir --}}
<div>
    {{-- wire:model = two-way binding --}}
    <input wire:model.live="newQuantity" type="number" />

    {{-- ViewModel metodunu çağır --}}
    <button wire:click="addItem({{ $productId }}, {{ $quantity }})">
        Əlavə et
    </button>

    {{-- ViewModel state-i göstər --}}
    <p>Cəmi: {{ number_format($total, 2) }} AZN</p>

    {{-- Loading state — ViewModel-dən --}}
    <button wire:click="submit" wire:loading.attr="disabled">
        <span wire:loading.remove>Sifariş ver</span>
        <span wire:loading>Yüklənir...</span>
    </button>

    @if($successMessage)
        <div class="alert alert-success">{{ $successMessage }}</div>
    @endif
</div>
```

**Eyni feature — MVC (Laravel) vs MVVM (Livewire):**

```php
<?php

// === MVC yanaşması — server-side, full page reload ===

// Controller (MVC)
class TraditionalOrderController extends Controller
{
    public function create(): \Illuminate\View\View
    {
        $products = Product::available()->get();
        return view('orders.create', compact('products'));
    }

    public function store(StoreOrderRequest $request): \Illuminate\Http\RedirectResponse
    {
        $order = app(OrderService::class)->create(
            auth()->id(),
            $request->validated('items')
        );
        return redirect()->route('orders.show', $order)
                         ->with('success', 'Sifariş yaradıldı!');
    }
}

// Blade View (MVC)
// resources/views/orders/create.blade.php
// <form method="POST" action="{{ route('orders.store') }}">
//   @csrf
//   <!-- form fields -->
//   <button type="submit">Sifariş ver</button>
// </form>
// Full page reload olur

// === MVVM yanaşması — Livewire, real-time ===

// CreateOrderForm extends Component (MVVM — yuxarıda göstərilib)
// <livewire:create-order-form /> ilə istifadə
// Ajax ilə real-time update — page reload yoxdur
```

**Laravel Inertia = MVC backend + MVVM frontend:**

```php
<?php

// Backend — MVC Controller (Laravel)
namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function create(): Response
    {
        // MVC: Controller data hazırlayır, View-a ötürür
        return Inertia::render('Orders/Create', [
            'products' => Product::available()->get(['id', 'name', 'price']),
        ]);
    }

    public function store(StoreOrderRequest $request): \Illuminate\Http\RedirectResponse
    {
        $order = app(OrderService::class)->create(
            auth()->id(),
            $request->validated('items')
        );
        return redirect()->route('orders.show', $order);
    }
}
```

```vue
<!-- Frontend — MVVM (Vue.js Component) -->
<template>
  <div>
    <!-- View: ViewModel-i izləyir -->
    <div v-for="(item, index) in items" :key="index">
      {{ item.name }} × {{ item.quantity }}
      <button @click="removeItem(index)">Sil</button>
    </div>

    <p>Cəmi: {{ total }} AZN</p>

    <button @click="submit" :disabled="loading">
      {{ loading ? 'Yüklənir...' : 'Sifariş ver' }}
    </button>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

// ViewModel — state və methodlar
const items = ref([])
const loading = ref(false)

// Computed — ViewModel-in hesablanmış state-i
const total = computed(() =>
  items.value.reduce((sum, item) => sum + item.price * item.quantity, 0)
)

// ViewModel metodu
const addItem = (product, quantity) => {
  items.value.push({ ...product, quantity })
}

const removeItem = (index) => {
  items.value.splice(index, 1)
}

// Backend MVC Controller-ə POST
const submit = () => {
  loading.value = true
  useForm({ items: items.value }).post('/orders', {
    onFinish: () => { loading.value = false }
  })
}
</script>
```

## Praktik Tapşırıqlar

1. Mövcud bir Fat Controller tapın (150+ sətir); business logic-i `OrderService`-ə köçürün; Controller yalnız 3-5 sətirlik metodlara ensin; test yazın — service unit test + controller feature test
2. Eyni "sifariş yaratma" feature-unu iki yollu implement edin: ənənəvi MVC (Blade + Form + Redirect) + Livewire MVVM (real-time, no reload); hər ikisinin test-ini yazın; UX fərqini müşahidə edin
3. Livewire component-in nə qədər şey etdiyini yoxlayın — business logic, DB query, email göndərmə varsa, `OrderService`-ə köçürün; component yalnız UI state idarə etsin
4. Inertia.js qurulumu: Laravel backend (MVC) + Vue.js frontend (MVVM); eyni `OrderController`-i Inertia ilə refactor edin; backend dəyişmədən frontend Vue-ya keçsin

## Əlaqəli Mövzular

- [SOLID Prinsipləri](02-solid-principles.md) — Fat Controller SRP pozuntusudur
- [Service Layer](../laravel/02-service-layer.md) — MVC-də Controller-dən ayırılan business logic burada yaşayır
- [Repository Pattern](../laravel/01-repository-pattern.md) — Model layer-ın daha strukturlu versiyası
- [Design Patterns Ümumi Baxış](01-design-patterns-overview.md) — Observer (Livewire event-ləri), Command pattern-ləri
- [Hexagonal Architecture](05-hexagonal-architecture.md) — MVC/MVVM Primary Adapter kimi Hexagonal-da yer alır
