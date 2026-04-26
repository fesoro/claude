# Laravel Ecosystem (Senior)

## 1. Laravel Livewire nədir?

Server-side rendered reactive components. JavaScript yazmadan dinamik UI.

```php
// Livewire component
class SearchProducts extends Component {
    public string $query = '';
    public string $category = '';
    public int $minPrice = 0;
    public int $maxPrice = 10000;

    // Real-time search — hər dəfə $query dəyişəndə çağırılır
    public function updatedQuery(): void {
        $this->resetPage(); // Pagination sıfırla
    }

    public function render(): View {
        $products = Product::query()
            ->when($this->query, fn ($q) => $q->where('name', 'like', "%{$this->query}%"))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->whereBetween('price', [$this->minPrice, $this->maxPrice])
            ->paginate(20);

        return view('livewire.search-products', compact('products'));
    }
}

// Blade: livewire/search-products.blade.php
// <div>
//     <input wire:model.live.debounce.300ms="query" placeholder="Axtar...">
//     <select wire:model.live="category">...</select>
//     @foreach($products as $product)
//         <x-product-card :product="$product" />
//     @endforeach
//     {{ $products->links() }}
// </div>
```

**Nə vaxt Livewire?**
- Admin panel, dashboard
- Search, filter, form-lar
- Real-time updates (polling)
- JavaScript bilməyən backend team

**Nə vaxt Livewire deyil?**
- Çox interaktiv UI (drag-drop, canvas)
- Offline-first tətbiqlər
- Çox yüksək interaktivlik tələb edən SPA

---

## 2. Inertia.js nədir?

SPA yaratmaq üçün — Laravel backend + Vue/React/Svelte frontend, amma API yazmadan.

```php
// Controller — adi Laravel controller kimi
class UserController extends Controller {
    public function index(): \Inertia\Response {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->when(request('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->paginate(10)
                ->through(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'filters' => request()->only('search'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse {
        User::create($request->validated());
        return redirect()->route('users.index')->with('success', 'User yaradıldı.');
    }
}

// Vue component (Pages/Users/Index.vue)
// <script setup>
// import { router } from '@inertiajs/vue3'
// defineProps({ users: Object, filters: Object })
// </script>
// <template>
//     <input v-model="filters.search" @input="router.get('/users', filters)">
//     <tr v-for="user in users.data">...</tr>
// </template>
```

**Livewire vs Inertia:**
| | Livewire | Inertia.js |
|---|---|---|
| Frontend | Blade (server-rendered) | Vue/React/Svelte |
| JS bilməsi | Yox (əsasən) | Bəli |
| SPA hissi | Yox (AJAX updates) | Bəli |
| SEO | Yaxşı (SSR) | SSR lazımdır |
| İstifadə | Admin panel, CRUD | Full SPA |

---

## 3. Laravel Pennant — Feature Flags

```php
// composer require laravel/pennant

// Feature təyin et
use Laravel\Pennant\Feature;

// AppServiceProvider
Feature::define('new-checkout', function (User $user) {
    return match(true) {
        $user->isAdmin() => true,           // Admin-lər həmişə görür
        $user->isBetaTester() => true,      // Beta tester-lər görür
        default => Lottery::odds(1, 10),     // 10%-ə açıq (gradual rollout)
    };
});

// İstifadə
if (Feature::active('new-checkout')) {
    return view('checkout.new');
}
return view('checkout.old');

// Blade-da
@feature('new-checkout')
    <x-new-checkout />
@else
    <x-old-checkout />
@endfeature

// Middleware
Route::get('/checkout', NewCheckoutController::class)
    ->middleware('feature:new-checkout');

// API response-da
Feature::for($user)->all(); // Bütün feature flag-ların vəziyyəti

// A/B Testing
Feature::define('checkout-button-color', function () {
    return Arr::random(['blue', 'green', 'red']);
});

$color = Feature::value('checkout-button-color');
```

---

## 4. Laravel Pulse — Real-time Monitoring

```php
// composer require laravel/pulse
// php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"

// Dashboard: /pulse

// Nə monitor edir:
// - Slow requests (response time)
// - Slow queries
// - Slow jobs
// - Exceptions
// - Cache hit/miss rate
// - Queue throughput
// - Server stats (CPU, RAM)
// - Active users

// Custom recorder
use Laravel\Pulse\Facades\Pulse;

Pulse::record('external_api_call', 'stripe', now()->timestamp)
    ->max()
    ->count();

// Custom card
// resources/views/vendor/pulse/dashboard.blade.php
// <x-pulse::card>
//     <x-pulse::card-header name="External API Calls">
//     </x-pulse::card-header>
//     ...
// </x-pulse::card>
```

---

## 5. Laravel Reverb — WebSocket Server

```php
// composer require laravel/reverb
// php artisan reverb:install
// php artisan reverb:start

// PHP-də yazılmış WebSocket server — Pusher əvəzinə
// Artıq xarici servisə ehtiyac yoxdur

// .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=my-app
REVERB_APP_KEY=my-key
REVERB_APP_SECRET=my-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

// Event broadcast (əvvəlki kimi)
class OrderStatusUpdated implements ShouldBroadcast {
    public function broadcastOn(): array {
        return [new PrivateChannel('orders.' . $this->order->user_id)];
    }
}

// Frontend — Laravel Echo
// Echo.private(`orders.${userId}`)
//     .listen('OrderStatusUpdated', (e) => {
//         showNotification(e.order.status);
//     });

// Scaling: Redis ilə çoxlu Reverb instance
// REVERB_SCALING_ENABLED=true
```

---

## 6. Horizontal Scaling — Stateless Laravel

```php
// Problem: 3 server var, load balancer arxasında. Session/Cache/File paylaşılmalıdır.

// 1. Session — Redis-ə köçür
// .env
SESSION_DRIVER=redis

// 2. Cache — Redis (bütün server-lər eyni Redis-ə qoşulur)
CACHE_DRIVER=redis

// 3. Queue — Redis
QUEUE_CONNECTION=redis

// 4. File storage — S3 (lokal disk yox)
FILESYSTEM_DISK=s3

// 5. Logs — centralized (ELK, Datadog)
LOG_CHANNEL=stderr  // Container stdout/stderr → log aggregator

// 6. Scheduled tasks — yalnız bir server-də
$schedule->command('reports:daily')
    ->daily()
    ->onOneServer(); // Redis lock istifadə edir

// 7. Deployment — bütün server-lər eyni anda
// Blue-green deployment və ya rolling update

// Load Balancer health check
Route::get('/health', function () {
    DB::select('SELECT 1');
    Redis::ping();
    return response('OK', 200);
});

// Sticky sessions (lazım olarsa)
// Nginx: upstream backend { ip_hash; server app1; server app2; }
// Amma stateless daha yaxşıdır — sticky session lazım olmasın
```

---

## 7. OpenAPI / Swagger Documentation

```php
// composer require darkaonline/l5-swagger
// L5-Swagger Laravel Annotations ilə

use OpenApi\Attributes as OA;

#[OA\Info(title: "E-Commerce API", version: "2.0")]
#[OA\Server(url: "https://api.example.com")]
class ApiController extends Controller {}

#[OA\Schema(
    schema: "User",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Orxan"),
        new OA\Property(property: "email", type: "string", format: "email"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
    ]
)]
class UserResource extends JsonResource {}

class UserController extends ApiController {
    #[OA\Get(
        path: "/api/v1/users",
        summary: "İstifadəçilərin siyahısı",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Uğurlu",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array",
                            items: new OA\Items(ref: "#/components/schemas/User")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Autentifikasiya tələb olunur"),
        ]
    )]
    public function index(Request $request): UserCollection {
        // ...
    }
}

// Generate: php artisan l5-swagger:generate
// View: /api/documentation
```
