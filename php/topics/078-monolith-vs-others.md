# Monolit Arxitektura və Digərləri ilə Fərqləri (Middle)

## Mündəricat
1. [Monolit Arxitektura nədir](#monolit-arxitektura-nədir)
2. [Traditional Monolith](#traditional-monolith)
3. [Monolith üstünlükləri və mənfi cəhətləri](#monolith-üstünlükləri-və-mənfi-cəhətləri)
4. [Modulyar Monolit (Modular Monolith)](#modulyar-monolit-modular-monolith)
5. [Microservices](#microservices)
6. [Service Discovery](#service-discovery)
7. [API Gateway](#api-gateway)
8. [Service Mesh](#service-mesh)
9. [Circuit Breaker Pattern](#circuit-breaker-pattern)
10. [Saga Pattern (Distributed Transactions)](#saga-pattern-distributed-transactions)
11. [Microservices üstünlükləri və mənfi cəhətləri](#microservices-üstünlükləri-və-mənfi-cəhətləri)
12. [Serverless](#serverless)
13. [SOA (Service-Oriented Architecture)](#soa-service-oriented-architecture)
14. [Monolith vs Microservices vs Modulyar Monolit müqayisə cədvəli](#monolith-vs-microservices-vs-modulyar-monolit-müqayisə-cədvəli)
15. [Conway's Law](#conways-law)
16. [Distributed Systems Challenges](#distributed-systems-challenges)
17. [Strangler Fig Pattern](#strangler-fig-pattern)
18. [Backend for Frontend (BFF)](#backend-for-frontend-bff)
19. [API Versioning](#api-versioning)
20. [Nə vaxt hansı arxitektura seçilməli](#nə-vaxt-hansı-arxitektura-seçilməli)
21. [Team Size və Arxitektura Əlaqəsi](#team-size-və-arxitektura-əlaqəsi)
22. [Real-World Migration Story](#real-world-migration-story)
23. [Laravel-in Monolit kimi güclü tərəfləri](#laravel-in-monolit-kimi-güclü-tərəfləri)
24. [İntervyu Sualları və Cavabları](#intervyu-sualları-və-cavabları)

---

## Monolit Arxitektura nədir

Monolit Arxitektura (Monolithic Architecture) - bütün tətbiqin **tək bir kod bazasında**, **tək bir deploy vahidində** və **tək bir prosesdə** işlədiyi arxitektura yanaşmasıdır. Bütün komponentlər - UI, business logic, data access, background jobs - hamısı eyni layihə daxilindədir və birlikdə deploy edilir.

**Sadə analogiya:** Monolit arxitektura böyük bir mağazaya bənzəyir - burada hər şey bir dam altındadır: ərzaq, geyim, elektronika, mebel. Hər şey bir yerdədir, girişdən çıxışa qədər bir binada idarə olunur. Microservices isə ticarət mərkəzidir - hər mağaza müstəqildir, öz kassası, öz anbarı, öz personalı var.

```
Monolit Arxitektura:
┌─────────────────────────────────────────────┐
│              Laravel Application             │
│                                             │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐    │
│  │   User    │ │  Order   │ │ Payment  │    │
│  │  Module   │ │  Module  │ │  Module  │    │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘    │
│       │            │            │           │
│  ┌────┴────────────┴────────────┴─────┐     │
│  │        Shared Database (MySQL)      │     │
│  └─────────────────────────────────────┘     │
│                                             │
│  Single Deploy Unit | Single Process         │
└─────────────────────────────────────────────┘

Microservices Arxitekturası:
┌──────────┐  ┌──────────┐  ┌──────────┐
│   User   │  │  Order   │  │ Payment  │
│ Service  │  │ Service  │  │ Service  │
│          │  │          │  │          │
│ ┌──────┐ │  │ ┌──────┐ │  │ ┌──────┐ │
│ │ DB 1 │ │  │ │ DB 2 │ │  │ │ DB 3 │ │
│ └──────┘ │  │ └──────┘ │  │ └──────┘ │
└──────────┘  └──────────┘  └──────────┘
  Ayrı deploy   Ayrı deploy   Ayrı deploy
```

---

## Traditional Monolith

Traditional Monolith - ən klassik arxitektura formasıdır. Layihənin bütün hissələri bir-birinə sıx bağlıdır (tightly coupled). Tipik Laravel layihəsi mahiyyətcə monolitdir.

### Tipik Laravel Monolit Strukturu

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── UserController.php
│   │   ├── OrderController.php
│   │   ├── PaymentController.php
│   │   └── ProductController.php
│   ├── Middleware/
│   └── Requests/
├── Models/
│   ├── User.php
│   ├── Order.php
│   ├── Payment.php
│   └── Product.php
├── Services/
│   ├── UserService.php
│   ├── OrderService.php
│   ├── PaymentService.php
│   └── ProductService.php
├── Repositories/
├── Events/
├── Jobs/
├── Mail/
└── Notifications/
config/
database/
resources/
routes/
```

### Laravel Monolitdə Tipik Service Əlaqəsi

*Laravel Monolitdə Tipik Service Əlaqəsi üçün kod nümunəsi:*
```php
namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Events\OrderPlaced;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private PaymentService $paymentService,
        private InventoryService $inventoryService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Monolitdə bütün əməliyyatlar eyni prosesdə, eyni DB-də baş verir.
     * Transaksiya ilə atomiklik təmin olunur.
     */
    public function placeOrder(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
            // 1. Stok yoxla (eyni DB-dən)
            foreach ($items as $item) {
                $this->inventoryService->checkAvailability(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // 2. Sifariş yarat (eyni DB-yə)
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total' => $this->calculateTotal($items),
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            // 3. Ödəniş emal et (eyni prosesdə)
            $payment = $this->paymentService->charge(
                $user,
                $order->total
            );

            $order->update([
                'payment_id' => $payment->id,
                'status' => 'paid',
            ]);

            // 4. Stoku azalt (eyni DB transaction daxilində)
            foreach ($items as $item) {
                $this->inventoryService->decrementStock(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // 5. Bildiriş göndər (asinxron ola bilər, amma eyni app daxilində)
            event(new OrderPlaced($order));

            return $order;
        });
    }

    private function calculateTotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $total += $product->price * $item['quantity'];
        }
        return $total;
    }
}
```

**Burada nə baş verir:**
- Bütün service-lər eyni prosesdə çağırılır
- `DB::transaction()` sayəsində ya hamısı uğurlu olur, ya da heç biri (ACID)
- Heç bir network call yoxdur, bütün əlaqələr in-memory-dir
- Hər hansı service-dən exception atılsa, bütün əməliyyat geri qaytarılır (rollback)

---

## Monolith üstünlükləri və mənfi cəhətləri

### Üstünlükləri

| Üstünlük | Açıqlama |
|----------|----------|
| **Sadə development** | Bir IDE-də bütün kodu görürsən, debug etmək asandır |
| **Sadə deploy** | Tək bir `git push` və ya `php artisan deploy` |
| **ACID transactions** | DB transaction ilə data consistency təmin olunur |
| **Aşağı latency** | In-process call, network overhead yoxdur |
| **Asan testing** | Bütün integration testləri bir yerdə |
| **Sadə debugging** | Stack trace bütün flow-nu göstərir |
| **Aşağı operational cost** | Bir server, bir DB, sadə monitoring |
| **Kod paylaşımı** | Shared models, helpers, utilities |
| **IDE dəstəyi** | Refactoring, "Find Usages", auto-complete tam işləyir |

### Mənfi cəhətləri

| Mənfi cəhət | Açıqlama |
|-------------|----------|
| **Scaling çətinliyi** | Bütün tətbiqi scale etmək lazımdır, hissə-hissə olmur |
| **Deploy riski** | Kiçik bir dəyişiklik bütün sistemi təsir edə bilər |
| **Texnoloji lock-in** | Bir framework/dil ilə bağlısınız (məs. yalnız PHP/Laravel) |
| **Build vaxtı** | Böyüdükcə CI/CD pipeline yavaşlayır |
| **Team bottleneck** | Çox developer eyni codebase-də conflict yaşaya bilər |
| **Tight coupling riski** | Zaman keçdikcə modullar bir-birinə dolaşa bilər (Spaghetti code) |
| **Memory/Resource** | Bütün modullar hər request-də yüklənir |
| **Single point of failure** | Bir modul çöksə, bütün tətbiq çökür |

### Monolit Scaling Strategiyası (Laravel)

*Monolit Scaling Strategiyası (Laravel) üçün kod nümunəsi:*
```php
// config/database.php - Read/Write Splitting ilə horizontal scaling
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', '10.0.0.1'),
            env('DB_READ_HOST_2', '10.0.0.2'),
            env('DB_READ_HOST_3', '10.0.0.3'),
        ],
    ],
    'write' => [
        'host' => [
            env('DB_WRITE_HOST', '10.0.0.10'),
        ],
    ],
    'sticky' => true, // Write-dan sonra eyni connection-dan read et
    'driver' => 'mysql',
    'database' => env('DB_DATABASE', 'forge'),
],

// Monolitdə horizontal scaling üçün load balancer arxasında
// multiple instance işlədilir:

// nginx.conf
// upstream laravel_app {
//     server 10.0.1.1:9000;
//     server 10.0.1.2:9000;
//     server 10.0.1.3:9000;
// }
```

---

## Modulyar Monolit (Modular Monolith)

Modulyar Monolit - monolit və microservices arasında bir "orta yol"dur. Tək bir deploy vahidində işləyir, amma daxili olaraq **aydın ayrılmış modullardan** ibarətdir. Hər modulun öz domain-i, öz model-ləri, öz migration-ları var.

```
app/
├── Modules/
│   ├── User/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   ├── Events/
│   │   ├── Routes/
│   │   ├── Database/Migrations/
│   │   ├── Tests/
│   │   └── UserServiceProvider.php
│   │
│   ├── Order/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Events/
│   │   ├── Contracts/         ← Public interface (digər modullar bunu istifadə edir)
│   │   │   └── OrderServiceInterface.php
│   │   ├── Routes/
│   │   ├── Database/Migrations/
│   │   ├── Tests/
│   │   └── OrderServiceProvider.php
│   │
│   ├── Payment/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Contracts/
│   │   │   └── PaymentServiceInterface.php
│   │   ├── Routes/
│   │   ├── Database/Migrations/
│   │   ├── Tests/
│   │   └── PaymentServiceProvider.php
│   │
│   └── Inventory/
│       ├── ...
│       └── InventoryServiceProvider.php
```

### Modul Interface-ləri

*Modul Interface-ləri üçün kod nümunəsi:*
```php
namespace App\Modules\Payment\Contracts;

/**
 * Payment modulunun public API-si.
 * Digər modullar YALNIZ bu interface vasitəsilə Payment modulu ilə əlaqə qurur.
 * Payment modulunun daxili implementasiyasını bilmirlər.
 */
interface PaymentServiceInterface
{
    public function charge(int $userId, float $amount, string $currency = 'USD'): PaymentResult;
    public function refund(string $paymentId, ?float $amount = null): RefundResult;
    public function getPaymentStatus(string $paymentId): PaymentStatus;
}
```

*public function getPaymentStatus(string $paymentId): PaymentStatus; üçün kod nümunəsi:*
```php
namespace App\Modules\Payment\Services;

use App\Modules\Payment\Contracts\PaymentServiceInterface;
use App\Modules\Payment\Models\Payment;

class StripePaymentService implements PaymentServiceInterface
{
    public function charge(int $userId, float $amount, string $currency = 'USD'): PaymentResult
    {
        // Daxili implementasiya - digər modullar bunu bilmir
        $stripeCharge = \Stripe\Charge::create([
            'amount' => $amount * 100,
            'currency' => $currency,
            'customer' => $this->getStripeCustomerId($userId),
        ]);

        $payment = Payment::create([
            'user_id' => $userId,
            'stripe_charge_id' => $stripeCharge->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $stripeCharge->status,
        ]);

        return new PaymentResult(
            success: $stripeCharge->status === 'succeeded',
            paymentId: $payment->id,
            transactionId: $stripeCharge->id,
        );
    }

    // ...
}
```

*həll yanaşmasını üçün kod nümunəsi:*
```php
namespace App\Modules\Payment;

use Illuminate\Support\ServiceProvider;
use App\Modules\Payment\Contracts\PaymentServiceInterface;
use App\Modules\Payment\Services\StripePaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentServiceInterface::class, StripePaymentService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}
```

### Modullar arası əlaqə (Event vasitəsilə)

*Modullar arası əlaqə (Event vasitəsilə) üçün kod nümunəsi:*
```php
namespace App\Modules\Order\Services;

use App\Modules\Payment\Contracts\PaymentServiceInterface;
use App\Modules\Order\Events\OrderPlaced;

class OrderService
{
    public function __construct(
        private PaymentServiceInterface $paymentService, // Interface vasitəsilə!
    ) {}

    public function placeOrder(int $userId, array $items): Order
    {
        return DB::transaction(function () use ($userId, $items) {
            $order = Order::create([...]);

            // Payment modulu ilə əlaqə interface vasitəsilə
            $paymentResult = $this->paymentService->charge(
                $userId,
                $order->total
            );

            if (!$paymentResult->success) {
                throw new PaymentFailedException($paymentResult->error);
            }

            // Digər modullara event vasitəsilə xəbər ver
            // Bu loose coupling təmin edir
            event(new OrderPlaced($order));

            return $order;
        });
    }
}
```

**Modulyar Monolitin əsas qaydaları:**
1. Modullar bir-birinin **daxili classlarına** birbaşa müraciət etməməlidir
2. Modullar arası əlaqə **interface** və ya **event** vasitəsilədir
3. Hər modul öz **migration**-larına sahibdir
4. Bir modulun modeli digər modulun cədvəlinə birbaşa `JOIN` etməməlidir
5. Gələcəkdə hər hansı modul müstəqil microservice ola bilməlidir

---

## Microservices

Microservices arxitekturası - tətbiqin kiçik, müstəqil, ayrıca deploy edilən **service-lərə** bölündüyü arxitektura yanaşmasıdır. Hər service öz domain-inə sahibdir, öz verilənlər bazası var və digər service-lərlə network üzərindən (HTTP/gRPC/Message Queue) əlaqə qurur.

### Əsas Prinsiplər

```
Microservices Əsas Prinsipləri:

1. Single Responsibility    - Hər service bir iş görür
2. Independently Deployable - Hər service ayrıca deploy edilir
3. Own Data Store           - Hər service öz DB-sinə sahibdir
4. Communicate via Network  - HTTP, gRPC, Message Queue
5. Decentralized Governance - Hər komanda öz texnologiyasını seçə bilər
6. Failure Isolation        - Bir service çökəndə digərləri işləməyə davam edir
7. Observable               - Hər service monitor edilə bilər
```

### PHP/Laravel ilə Microservice Nümunəsi

**Order Service (Laravel)**
```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\OrderService;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $order = $this->orderService->placeOrder($validated);

        return response()->json([
            'data' => $order,
            'message' => 'Order created successfully',
        ], 201);
    }
}
```

*'message' => 'Order created successfully', üçün kod nümunəsi:*
```php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Events\OrderCreated;

class OrderService
{
    /**
     * Microservices-də digər service-lərlə HTTP vasitəsilə əlaqə qurulur.
     * Bu əsas fərqdir: monolitdə in-process call, burada network call.
     */
    public function placeOrder(array $data): Order
    {
        // 1. User Service-dən istifadəçi məlumatlarını al (HTTP call)
        $userResponse = Http::withToken(config('services.user.token'))
            ->timeout(5)
            ->retry(3, 100) // 3 dəfə cəhd, 100ms interval
            ->get(config('services.user.url') . "/api/users/{$data['user_id']}");

        if ($userResponse->failed()) {
            throw new \RuntimeException('User service unavailable');
        }

        // 2. Inventory Service-dən stok yoxla (HTTP call)
        $stockResponse = Http::withToken(config('services.inventory.token'))
            ->timeout(5)
            ->post(config('services.inventory.url') . '/api/stock/check', [
                'items' => $data['items'],
            ]);

        if ($stockResponse->failed()) {
            throw new \RuntimeException('Inventory service unavailable');
        }

        if (!$stockResponse->json('available')) {
            throw new \RuntimeException('Insufficient stock');
        }

        // 3. Sifarişi öz DB-mizdə yarat
        $order = Order::create([
            'user_id' => $data['user_id'],
            'status' => 'pending',
            'total' => $stockResponse->json('total'),
        ]);

        foreach ($data['items'] as $item) {
            $order->items()->create($item);
        }

        // 4. Event publish et (RabbitMQ/Kafka vasitəsilə)
        // Digər service-lər bu event-ə subscribe olub öz işlərini görür
        event(new OrderCreated($order));

        return $order;
    }
}
```

**Service Config:**
```php
// config/services.php
return [
    'user' => [
        'url' => env('USER_SERVICE_URL', 'http://user-service:8001'),
        'token' => env('USER_SERVICE_TOKEN'),
    ],
    'inventory' => [
        'url' => env('INVENTORY_SERVICE_URL', 'http://inventory-service:8002'),
        'token' => env('INVENTORY_SERVICE_TOKEN'),
    ],
    'payment' => [
        'url' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8003'),
        'token' => env('PAYMENT_SERVICE_TOKEN'),
    ],
    'notification' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8004'),
        'token' => env('NOTIFICATION_SERVICE_TOKEN'),
    ],
];
```

---

## Service Discovery

Microservices mühitində service-lərin sayı artdıqca, hər service-in harada olduğunu (IP, port) bilmək çətinləşir. Service Discovery bu problemi həll edir - service-lər özlərini qeydiyyatdan keçirir və digər service-lər onları dinamik olaraq tapır.

```
Service Discovery:

┌─────────────┐         ┌──────────────────┐
│ Order Service│────────▶│ Service Registry │
│  "Mənə User │         │  (Consul/Eureka) │
│   Service    │         │                  │
│   lazımdır"  │◀────────│ User Service:    │
└─────────────┘  cavab:  │  10.0.1.5:8001   │
                IP:port  │  10.0.1.6:8001   │
                         │  10.0.1.7:8001   │
                         └──────────────────┘
                                ▲
                    Qeydiyyat   │   Health check
                         ┌──────┴──────┐
                         │ User Service │
                         │ (instance 1) │
                         │ (instance 2) │
                         │ (instance 3) │
                         └──────────────┘
```

### Client-Side Discovery (PHP nümunəsi)

*Client-Side Discovery (PHP nümunəsi) üçün kod nümunəsi:*
```php
namespace App\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ServiceDiscovery
{
    private string $consulUrl;

    public function __construct()
    {
        $this->consulUrl = config('services.consul.url', 'http://consul:8500');
    }

    /**
     * Consul-dan service-in sağlam instance-larını tap
     */
    public function discover(string $serviceName): string
    {
        $instances = Cache::remember(
            "service.{$serviceName}",
            now()->addSeconds(30), // 30 saniyə cache
            function () use ($serviceName) {
                $response = Http::get(
                    "{$this->consulUrl}/v1/health/service/{$serviceName}",
                    ['passing' => true] // Yalnız sağlam olanları
                );

                return collect($response->json())
                    ->map(fn ($entry) => [
                        'host' => $entry['Service']['Address'],
                        'port' => $entry['Service']['Port'],
                    ])
                    ->toArray();
            }
        );

        if (empty($instances)) {
            throw new ServiceUnavailableException(
                "No healthy instances found for {$serviceName}"
            );
        }

        // Round-robin load balancing
        $instance = $instances[array_rand($instances)];

        return "http://{$instance['host']}:{$instance['port']}";
    }
}

// İstifadəsi:
class OrderService
{
    public function __construct(
        private ServiceDiscovery $discovery,
    ) {}

    public function getUserDetails(int $userId): array
    {
        $baseUrl = $this->discovery->discover('user-service');

        return Http::get("{$baseUrl}/api/users/{$userId}")->json();
    }
}
```

**Docker/Kubernetes mühitində** service discovery daha sadədir, çünki DNS əsaslı discovery daxili olaraq mövcuddur:
***Docker/Kubernetes mühitində** service discovery daha sadədir, çünki  üçün kod nümunəsi:*
```yaml
# docker-compose.yml
services:
  user-service:
    image: user-service:latest
    # Docker avtomatik olaraq "user-service" DNS adını yaradır
    # Digər service-lər sadəcə http://user-service:8000 istifadə edə bilər
```

---

## API Gateway

API Gateway - bütün client request-lərinin keçdiyi **tək giriş nöqtəsidir** (single entry point). Client-lər birbaşa microservice-lərlə əlaqə qurmur, əvəzinə API Gateway vasitəsilə əlaqə qurur.

```
API Gateway:

  Mobile App ──┐
               │
  Web App ─────┤     ┌──────────────┐    ┌──────────────┐
               ├────▶│  API Gateway  │───▶│ User Service │
  3rd Party ───┤     │              │───▶│ Order Service│
               │     │ - Routing    │───▶│ Payment Svc  │
  IoT Device ──┘     │ - Auth       │───▶│ Inventory    │
                      │ - Rate Limit │───▶│ Notification │
                      │ - Logging    │    └──────────────┘
                      │ - Caching    │
                      │ - SSL Term.  │
                      └──────────────┘
```

### Laravel ilə sadə API Gateway

*Laravel ilə sadə API Gateway üçün kod nümunəsi:*
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ApiGatewayController extends Controller
{
    /**
     * Sadə API Gateway - request-i uyğun service-ə yönləndirir.
     * Real dünyada Kong, AWS API Gateway, Traefik istifadə olunur.
     */
    private array $serviceMap = [
        'users' => 'http://user-service:8001',
        'orders' => 'http://order-service:8002',
        'payments' => 'http://payment-service:8003',
        'products' => 'http://product-service:8004',
    ];

    public function proxy(Request $request, string $service, string $path): JsonResponse
    {
        // 1. Service mövcuddurmu?
        if (!isset($this->serviceMap[$service])) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $baseUrl = $this->serviceMap[$service];
        $targetUrl = "{$baseUrl}/api/{$path}";

        // 2. Rate limiting (middleware-də ola bilər)
        // 3. Authentication token-i forward et
        $headers = [
            'Authorization' => $request->header('Authorization'),
            'X-Request-ID' => $request->header('X-Request-ID', uniqid('req_')),
            'X-Forwarded-For' => $request->ip(),
        ];

        // 4. Request-i target service-ə forward et
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->{strtolower($request->method())}(
                $targetUrl,
                $request->all()
            );

        return response()->json(
            $response->json(),
            $response->status()
        );
    }

    /**
     * Aggregation pattern - bir neçə service-dən data toplayıb birləşdir.
     * Client bir request göndərir, gateway arxada bir neçə service-ə müraciət edir.
     */
    public function getUserDashboard(Request $request, int $userId): JsonResponse
    {
        // Paralel olaraq bir neçə service-ə müraciət et
        $responses = Http::pool(fn ($pool) => [
            $pool->as('user')
                ->get("http://user-service:8001/api/users/{$userId}"),
            $pool->as('orders')
                ->get("http://order-service:8002/api/users/{$userId}/orders?limit=5"),
            $pool->as('notifications')
                ->get("http://notification-service:8004/api/users/{$userId}/notifications?unread=true"),
        ]);

        return response()->json([
            'user' => $responses['user']->json(),
            'recent_orders' => $responses['orders']->json(),
            'notifications' => $responses['notifications']->json(),
        ]);
    }
}
```

*'notifications' => $responses['notifications']->json(), üçün kod nümunəsi:*
```php
// routes/api.php
Route::middleware(['auth:api', 'throttle:1000,1'])
    ->any('/gateway/{service}/{path}', [ApiGatewayController::class, 'proxy'])
    ->where('path', '.*');

Route::middleware(['auth:api'])
    ->get('/dashboard/{userId}', [ApiGatewayController::class, 'getUserDashboard']);
```

---

## Service Mesh

Service Mesh - microservice-lər arasındakı **network əlaqəsini** idarə edən infrastruktur layer-dir. Hər service-in yanına bir **sidecar proxy** (məsələn Envoy, Linkerd) yerləşdirilir və bu proxy bütün gələn/gedən traffic-i idarə edir.

```
Service Mesh (Istio/Linkerd):

┌─────────────────────────┐    ┌─────────────────────────┐
│    Order Service Pod     │    │    User Service Pod      │
│                         │    │                         │
│  ┌─────────────────┐    │    │    ┌─────────────────┐  │
│  │  Order Service   │    │    │    │  User Service    │  │
│  │  (Laravel app)   │    │    │    │  (Laravel app)   │  │
│  └───────┬─────────┘    │    │    └───────┬─────────┘  │
│          │               │    │            │             │
│  ┌───────▼─────────┐    │    │    ┌───────▼─────────┐  │
│  │  Envoy Proxy     │◄──┼────┼──▶ │  Envoy Proxy     │  │
│  │  (Sidecar)       │    │    │    │  (Sidecar)       │  │
│  │                  │    │    │    │                  │  │
│  │ - mTLS           │    │    │    │ - mTLS           │  │
│  │ - Load Balancing │    │    │    │ - Load Balancing │  │
│  │ - Retry          │    │    │    │ - Retry          │  │
│  │ - Circuit Break  │    │    │    │ - Circuit Break  │  │
│  │ - Observability  │    │    │    │ - Observability  │  │
│  └─────────────────┘    │    │    └─────────────────┘  │
└─────────────────────────┘    └─────────────────────────┘
                 │                        │
                 └───────────┬────────────┘
                     ┌───────▼───────┐
                     │ Control Plane │
                     │  (Istiod)     │
                     │               │
                     │ - Config      │
                     │ - Certificates│
                     │ - Policies    │
                     └───────────────┘

Service Mesh-in üstünlüyü: Application kod dəyişikliyinə ehtiyac yoxdur!
Laravel tətbiqi heç nəyi bilmir - proxy hər şeyi idarə edir.
```

**Service Mesh-in həll etdiyi problemlər:**
- **mTLS**: Service-lər arası şifrəli əlaqə (sertifikat idarəetməsi avtomatik)
- **Traffic management**: Canary deploy, A/B testing, traffic splitting
- **Observability**: Distributed tracing, metrics, logging
- **Resilience**: Retry, timeout, circuit breaking - kod yazmadan

---

## Circuit Breaker Pattern

Circuit Breaker - bir service-in cavab vermədiyini aşkarlayıb, uğursuz olacağını bildiyimiz halda **daha çox request göndərməyin qarşısını alan** pattern-dir. Elektrik sigortasına bənzəyir: həddindən artıq yüklənmə olduqda dövrəni açır.

```
Circuit Breaker States:

  ┌──────────┐   Failure threshold   ┌──────────┐
  │  CLOSED   │ ──────────────────▶  │   OPEN   │
  │           │   aşıldıqda          │          │
  │ Normal    │                      │ Request  │
  │ operation │   ◀──────────────── │ reject   │
  │           │   Uğurlu olduqda    │ olunur   │
  └──────────┘                      └────┬─────┘
       ▲                                  │
       │                                  │ Timeout
       │           ┌──────────┐           │ keçdikdən
       │           │HALF-OPEN │           │ sonra
       └───────────│          │◀──────────┘
         Uğurlu    │Məhdud    │
         olduqda   │request   │
                   │göndərilir│
                   └──────────┘
```

### PHP/Laravel ilə Circuit Breaker

*PHP/Laravel ilə Circuit Breaker üçün kod nümunəsi:*
```php
namespace App\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Closure;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private string $serviceName,
        private int $failureThreshold = 5,      // Neçə uğursuz cəhddən sonra aç
        private int $recoveryTimeout = 30,       // Neçə saniyədən sonra yenidən cəhd et
        private int $halfOpenMaxAttempts = 3,    // Half-open-da neçə request burax
    ) {}

    /**
     * Circuit breaker vasitəsilə remote service-ə müraciət et.
     *
     * @param Closure $action   - Əsas əməliyyat (remote call)
     * @param Closure|null $fallback - Circuit açıq olduqda alternativ cavab
     */
    public function call(Closure $action, ?Closure $fallback = null): mixed
    {
        $state = $this->getState();

        // Circuit açıqdırsa, fallback qaytar
        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptRecovery()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
            } else {
                return $fallback
                    ? $fallback()
                    : throw new CircuitOpenException(
                        "Circuit breaker is OPEN for {$this->serviceName}"
                    );
            }
        }

        try {
            $result = $action();

            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            $failureCount = $this->getFailureCount();

            if ($failureCount >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
            }

            if ($fallback) {
                return $fallback();
            }

            throw $e;
        }
    }

    private function getState(): string
    {
        return Cache::get("circuit_breaker.{$this->serviceName}.state", self::STATE_CLOSED);
    }

    private function transitionTo(string $state): void
    {
        Cache::put("circuit_breaker.{$this->serviceName}.state", $state, now()->addMinutes(5));

        if ($state === self::STATE_OPEN) {
            Cache::put(
                "circuit_breaker.{$this->serviceName}.opened_at",
                now()->timestamp,
                now()->addMinutes(5)
            );
        }

        if ($state === self::STATE_CLOSED) {
            $this->resetFailureCount();
        }
    }

    private function shouldAttemptRecovery(): bool
    {
        $openedAt = Cache::get("circuit_breaker.{$this->serviceName}.opened_at", 0);
        return (now()->timestamp - $openedAt) >= $this->recoveryTimeout;
    }

    private function recordFailure(): void
    {
        $key = "circuit_breaker.{$this->serviceName}.failures";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addMinutes(5));
    }

    private function recordSuccess(): void
    {
        $state = $this->getState();
        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_CLOSED);
        }
    }

    private function getFailureCount(): int
    {
        return Cache::get("circuit_breaker.{$this->serviceName}.failures", 0);
    }

    private function resetFailureCount(): void
    {
        Cache::forget("circuit_breaker.{$this->serviceName}.failures");
    }
}
```

### Circuit Breaker istifadəsi

*Circuit Breaker istifadəsi üçün kod nümunəsi:*
```php
namespace App\Services;

use App\Infrastructure\CircuitBreaker;
use Illuminate\Support\Facades\Http;

class UserServiceClient
{
    private CircuitBreaker $circuitBreaker;

    public function __construct()
    {
        $this->circuitBreaker = new CircuitBreaker(
            serviceName: 'user-service',
            failureThreshold: 5,
            recoveryTimeout: 30,
        );
    }

    public function getUser(int $userId): array
    {
        return $this->circuitBreaker->call(
            // Əsas əməliyyat
            action: function () use ($userId) {
                $response = Http::timeout(5)
                    ->get("http://user-service:8001/api/users/{$userId}");

                if ($response->failed()) {
                    throw new \RuntimeException("User service error: {$response->status()}");
                }

                return $response->json();
            },
            // Fallback - circuit açıq olduqda cache-dən oxu
            fallback: function () use ($userId) {
                $cached = cache()->get("user.{$userId}");
                if ($cached) {
                    return array_merge($cached, ['_source' => 'cache']);
                }
                throw new \RuntimeException('User service unavailable and no cache');
            }
        );
    }
}
```

---

## Saga Pattern (Distributed Transactions)

Microservices mühitində **ACID transaction** mümkün deyil, çünki hər service-in öz DB-si var. Saga pattern bu problemi həll edir - **bir sıra lokal transaksiyalar** icra edilir, hər biri uğurlu olduqda növbəti addım, uğursuz olduqda **compensating transaction** (geri qaytarma) icra edilir.

```
Saga Pattern - Sifariş Yaratma:

Normal axın (hamısı uğurlu):
┌───────────┐   ┌───────────┐   ┌───────────┐   ┌───────────┐
│ 1. Create  │──▶│ 2. Reserve│──▶│ 3. Process│──▶│ 4. Send   │
│    Order   │   │    Stock  │   │   Payment │   │   Email   │
│  (Order    │   │(Inventory │   │ (Payment  │   │(Notific.  │
│   Service) │   │  Service) │   │  Service) │   │  Service) │
└───────────┘   └───────────┘   └───────────┘   └───────────┘

Compensation axını (3-cü addımda xəta):
┌───────────┐   ┌───────────┐   ┌───────────┐
│ Cancel     │◀──│ Release   │◀──│  PAYMENT  │
│ Order      │   │ Stock     │   │  FAILED!  │
│(compensate)│   │(compensate│   │           │
└───────────┘   └───────────┘   └───────────┘
```

### İki növ Saga:

**1. Choreography (Xoreografiya):** Hər service öz event-ini publish edir, növbəti service həmin event-ə subscribe olub öz işini görür. Mərkəzi koordinator yoxdur.

**2. Orchestration (Orkestrasiya):** Mərkəzi bir koordinator (orchestrator) bütün addımları idarə edir.

### Orchestration Saga (Laravel nümunəsi)

*Orchestration Saga (Laravel nümunəsi) üçün kod nümunəsi:*
```php
namespace App\Sagas;

use App\Enums\SagaStatus;
use App\Models\SagaLog;

abstract class AbstractSaga
{
    protected array $steps = [];
    protected array $completedSteps = [];
    protected string $sagaId;

    public function __construct()
    {
        $this->sagaId = uniqid('saga_');
    }

    /**
     * Saga-nı icra et - bütün addımları ardıcıl yerinə yetir.
     * Hər hansı addımda xəta baş verərsə, compensate et.
     */
    public function execute(array $data): SagaResult
    {
        $this->log('Saga started', $data);

        foreach ($this->steps as $step) {
            try {
                $this->log("Executing step: {$step['name']}");

                // Addımı icra et
                $result = call_user_func($step['action'], $data);

                // Data-ya nəticəni əlavə et (növbəti addımlar istifadə edə bilsin)
                $data = array_merge($data, $result ?? []);

                $this->completedSteps[] = $step;

                $this->log("Step completed: {$step['name']}", $result ?? []);

            } catch (\Throwable $e) {
                $this->log("Step failed: {$step['name']}", [
                    'error' => $e->getMessage(),
                ]);

                // Uğursuz oldu - compensate et
                $this->compensate($data);

                return new SagaResult(
                    success: false,
                    sagaId: $this->sagaId,
                    error: $e->getMessage(),
                    failedStep: $step['name'],
                );
            }
        }

        $this->log('Saga completed successfully');

        return new SagaResult(
            success: true,
            sagaId: $this->sagaId,
            data: $data,
        );
    }

    /**
     * Compensation - tamamlanmış addımları tərsinə geri qaytar.
     */
    protected function compensate(array $data): void
    {
        $this->log('Starting compensation');

        // Tərsinə sıra ilə compensate et (LIFO)
        foreach (array_reverse($this->completedSteps) as $step) {
            try {
                if (isset($step['compensation'])) {
                    $this->log("Compensating step: {$step['name']}");
                    call_user_func($step['compensation'], $data);
                    $this->log("Compensation completed: {$step['name']}");
                }
            } catch (\Throwable $e) {
                // Compensation da uğursuz oldu - manual müdaxilə lazımdır
                $this->log("Compensation FAILED: {$step['name']}", [
                    'error' => $e->getMessage(),
                ]);
                // Alert göndər, Dead Letter Queue-ya yaz
                report($e);
            }
        }

        $this->log('Compensation finished');
    }

    protected function log(string $message, array $context = []): void
    {
        SagaLog::create([
            'saga_id' => $this->sagaId,
            'message' => $message,
            'context' => $context,
            'timestamp' => now(),
        ]);
    }
}
```

*'timestamp' => now(), üçün kod nümunəsi:*
```php
namespace App\Sagas;

use Illuminate\Support\Facades\Http;

class PlaceOrderSaga extends AbstractSaga
{
    public function __construct(
    ) {
        parent::__construct();

        $this->steps = [
            [
                'name' => 'create_order',
                'action' => fn ($data) => $this->createOrder($data),
                'compensation' => fn ($data) => $this->cancelOrder($data),
            ],
            [
                'name' => 'reserve_stock',
                'action' => fn ($data) => $this->reserveStock($data),
                'compensation' => fn ($data) => $this->releaseStock($data),
            ],
            [
                'name' => 'process_payment',
                'action' => fn ($data) => $this->processPayment($data),
                'compensation' => fn ($data) => $this->refundPayment($data),
            ],
            [
                'name' => 'send_notification',
                'action' => fn ($data) => $this->sendNotification($data),
                // Notification-ın compensation-ı yoxdur - kritik deyil
            ],
        ];
    }

    private function createOrder(array $data): array
    {
        $response = Http::post('http://order-service:8002/api/orders', [
            'user_id' => $data['user_id'],
            'items' => $data['items'],
        ]);

        $response->throw();

        return ['order_id' => $response->json('id')];
    }

    private function cancelOrder(array $data): void
    {
        Http::patch(
            "http://order-service:8002/api/orders/{$data['order_id']}/cancel"
        )->throw();
    }

    private function reserveStock(array $data): array
    {
        $response = Http::post('http://inventory-service:8003/api/reservations', [
            'order_id' => $data['order_id'],
            'items' => $data['items'],
        ]);

        $response->throw();

        return ['reservation_id' => $response->json('reservation_id')];
    }

    private function releaseStock(array $data): void
    {
        Http::delete(
            "http://inventory-service:8003/api/reservations/{$data['reservation_id']}"
        )->throw();
    }

    private function processPayment(array $data): array
    {
        $response = Http::post('http://payment-service:8004/api/payments', [
            'order_id' => $data['order_id'],
            'user_id' => $data['user_id'],
            'amount' => $data['total'],
        ]);

        $response->throw();

        return ['payment_id' => $response->json('payment_id')];
    }

    private function refundPayment(array $data): void
    {
        Http::post(
            "http://payment-service:8004/api/payments/{$data['payment_id']}/refund"
        )->throw();
    }

    private function sendNotification(array $data): void
    {
        Http::post('http://notification-service:8005/api/notifications', [
            'user_id' => $data['user_id'],
            'type' => 'order_placed',
            'data' => ['order_id' => $data['order_id']],
        ]);
        // Notification uğursuz olsa da saga davam edir (optional step)
    }
}

// İstifadə:
// $saga = new PlaceOrderSaga();
// $result = $saga->execute([
//     'user_id' => 1,
//     'items' => [['product_id' => 10, 'quantity' => 2]],
//     'total' => 99.99,
// ]);
```

---

## Microservices üstünlükləri və mənfi cəhətləri

### Üstünlükləri

| Üstünlük | Açıqlama |
|----------|----------|
| **Independent deployment** | Hər service ayrıca deploy edilir, digərlərinə təsir etmir |
| **Technology diversity** | Hər service fərqli dil/framework istifadə edə bilər |
| **Granular scaling** | Yalnız yüklənən service scale edilir |
| **Fault isolation** | Bir service çöksə, digərləri işləyir |
| **Team autonomy** | Kiçik komandalar müstəqil işləyir |
| **Faster releases** | Kiçik codebase = sürətli CI/CD |
| **Specialized optimization** | Hər service öz workload-ına uyğun optimize edilir |

### Mənfi cəhətləri

| Mənfi cəhət | Açıqlama |
|-------------|----------|
| **Distributed complexity** | Network latency, partial failures, eventual consistency |
| **Data consistency** | ACID transaction yoxdur, Saga pattern lazımdır |
| **Operational overhead** | Çoxlu service-i deploy/monitor/debug etmək çətindir |
| **Integration testing** | Bütün service-lərin birlikdə test edilməsi çətindir |
| **Network overhead** | Hər service call network request-dir |
| **Debugging çətinliyi** | Distributed tracing lazımdır (Jaeger, Zipkin) |
| **Data duplication** | Service-lər arasında data paylaşımı çətindir |
| **Infrastructure cost** | Hər service üçün ayrı resource lazımdır |
| **Versioning complexity** | API uyğunluğunu qorumaq çətindir |

---

## Serverless

Serverless - developer-lərin server idarəçiliyi ilə məşğul olmadan **yalnız kod yazdığı** arxitektura modelidir. Kod yalnız lazım olduqda icra edilir (event-triggered), server avtomatik scale edilir, ödəniş yalnız istifadəyə görə edilir.

```
Serverless (Function as a Service - FaaS):

Client Request
      │
      ▼
┌─────────────┐
│ API Gateway │  (AWS API Gateway / Google Cloud Endpoints)
│ (managed)   │
└──────┬──────┘
       │
       ▼
┌─────────────┐   Trigger
│   Lambda     │ ◀─── S3 Event
│  Function    │ ◀─── SQS Message
│              │ ◀─── Cron Schedule
│  PHP/Node/   │ ◀─── DynamoDB Stream
│  Python/Go   │
└──────┬──────┘
       │
       ▼
  ┌─────────┐
  │  DB /    │  (DynamoDB, Aurora Serverless, etc.)
  │  Cache   │
  └─────────┘

Xüsusiyyətlər:
- Server yoxdur (cloud provider idarə edir)
- 0-dan milyonlara avtomatik scale
- Ödəniş: yalnız icra vaxtı üçün (milliseconds)
- Stateless - hər invocation müstəqildir
```

### PHP Lambda Function nümunəsi (Bref ilə)

*PHP Lambda Function nümunəsi (Bref ilə) üçün kod nümunəsi:*
```php
// handler.php (Bref framework ilə AWS Lambda-da PHP)
use Bref\Context\Context;
use Bref\Event\Http\HttpHandler;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\Http\HttpResponse;

class OrderHandler extends HttpHandler
{
    public function handleRequest(HttpRequestEvent $event, Context $context): HttpResponse
    {
        $body = json_decode($event->getBody(), true);

        // Business logic
        $order = $this->processOrder($body);

        return new HttpResponse(
            json_encode(['order_id' => $order['id']]),
            ['Content-Type' => 'application/json'],
            201
        );
    }

    private function processOrder(array $data): array
    {
        // DynamoDB-yə yaz
        $dynamoDb = new \Aws\DynamoDb\DynamoDbClient([
            'region' => 'eu-west-1',
            'version' => 'latest',
        ]);

        $orderId = uniqid('ord_');

        $dynamoDb->putItem([
            'TableName' => 'orders',
            'Item' => [
                'id' => ['S' => $orderId],
                'user_id' => ['S' => $data['user_id']],
                'total' => ['N' => (string) $data['total']],
                'status' => ['S' => 'pending'],
                'created_at' => ['S' => date('c')],
            ],
        ]);

        // SQS-ə mesaj göndər (asinxron emal üçün)
        $sqs = new \Aws\Sqs\SqsClient([
            'region' => 'eu-west-1',
            'version' => 'latest',
        ]);

        $sqs->sendMessage([
            'QueueUrl' => getenv('ORDER_QUEUE_URL'),
            'MessageBody' => json_encode([
                'order_id' => $orderId,
                'action' => 'process_payment',
            ]),
        ]);

        return ['id' => $orderId];
    }
}

return new OrderHandler();
```

*return new OrderHandler(); üçün kod nümunəsi:*
```yaml
# serverless.yml (Bref ilə Laravel/PHP on AWS Lambda)
service: my-laravel-app

provider:
  name: aws
  region: eu-west-1
  runtime: provided.al2

plugins:
  - ./vendor/bref/bref

functions:
  web:
    handler: public/index.php
    runtime: php-83-fpm
    timeout: 28
    events:
      - httpApi: '*'

  artisan:
    handler: artisan
    runtime: php-83-console
    timeout: 120

  queue-worker:
    handler: Bref\LaravelBridge\Queue\QueueHandler
    runtime: php-83
    timeout: 59
    events:
      - sqs:
          arn: !GetAtt OrderQueue.Arn
```

### Cold Start Problemi

Cold start - serverless function uzun müddət çağırılmadıqda, yenidən çağırıldığında **container-in yüklənməsi üçün əlavə vaxt** tələb etməsidir.

```
Cold Start vs Warm Start:

Cold Start (ilk çağırış və ya uzun fasilədən sonra):
┌─────────────┬──────────────┬──────────────┬─────────────┐
│ Container   │  Runtime     │  Application │  Function   │
│ yaradılması │  yüklənməsi  │  bootstrap   │  execution  │
│  (~200ms)   │   (~300ms)   │   (~500ms)   │   (~50ms)   │
└─────────────┴──────────────┴──────────────┴─────────────┘
|◄─────────── Cold start delay ~1000ms ────────────────▶|

Warm Start (container artıq mövcuddur):
                                          ┌─────────────┐
                                          │  Function   │
                                          │  execution  │
                                          │   (~50ms)   │
                                          └─────────────┘
                                          |◄── ~50ms ──▶|

PHP/Laravel cold start xüsusilə ağırdır çünki:
- PHP runtime yüklənməlidir
- Composer autoloader yüklənməlidir
- Laravel bootstrap (service providers, config, routes) ağırdır
- Tipik cold start: 1-3 saniyə (Node.js: 100-500ms, Go: 50-100ms)
```

**Cold start azaltma yolları:**
```php
// 1. Provisioned Concurrency (AWS) - həmişə isti saxla (amma pul ödəyirsən)
// serverless.yml
// functions:
//   web:
//     provisionedConcurrency: 5

// 2. Laravel optimization (cold start azaltmaq üçün)
// deploy script-ində:
// php artisan config:cache
// php artisan route:cache
// php artisan view:cache
// php artisan event:cache

// 3. Lazy loading - yalnız lazım olanı yüklə
// AppServiceProvider.php
public function register(): void
{
    // Ağır service-ləri lazy yüklə
    $this->app->singleton(HeavyService::class, function ($app) {
        return new HeavyService(/* ... */);
    });
}
```

### Serverless üstünlükləri və mənfi cəhətləri

**Üstünlükləri:**
- Server idarəçiliyi yoxdur, operational overhead minimal
- Avtomatik scaling (0-dan milyonlara)
- Pay-per-use (istifadə etməsən, ödəmirsən)
- Yüksək availability (cloud provider təmin edir)

**Mənfi cəhətləri:**
- Cold start problemi (xüsusilə PHP üçün ağır)
- Vendor lock-in (AWS Lambda-dan GCP-yə keçmək çətindir)
- Execution time limit (AWS Lambda: max 15 dəqiqə)
- Local development/debugging çətindir
- Stateless məhdudiyyəti
- Monitoring/debugging mürəkkəbliyi

---

## SOA (Service-Oriented Architecture)

SOA - microservices-dən əvvəl mövcud olan, tətbiqin **service-lərə** bölündüyü arxitektura yanaşmasıdır. Microservices-dən fərqli olaraq, SOA adətən **daha böyük, daha az sayda service** istifadə edir və ortaq infrastruktur (ESB - Enterprise Service Bus) vasitəsilə əlaqə qurur.

```
SOA vs Microservices:

SOA:
┌──────────────────────────────────────────────┐
│            Enterprise Service Bus (ESB)       │
│    ┌─────────────────────────────────────┐   │
│    │  Routing │ Transformation │ Mediation│   │
│    └────┬────────────┬───────────────┬───┘   │
│         │            │               │       │
│    ┌────▼───┐  ┌─────▼────┐   ┌─────▼────┐  │
│    │  CRM   │  │ Billing  │   │   ERP     │  │
│    │Service │  │ Service  │   │  Service  │  │
│    │(böyük) │  │ (böyük)  │   │  (böyük)  │  │
│    └────────┘  └──────────┘   └──────────┘  │
│                                             │
│   Paylaşılan DB ola bilər                    │
│   SOAP/XML əsaslı əlaqə                     │
│   ESB - single point of failure              │
└──────────────────────────────────────────────┘

Microservices:
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│User Svc│ │Auth Svc│ │Cart Svc│ │Pay Svc │ │Email   │
│        │ │        │ │        │ │        │ │  Svc   │
│ own DB │ │ own DB │ │ own DB │ │ own DB │ │ own DB │
└───┬────┘ └───┬────┘ └───┬────┘ └───┬────┘ └───┬────┘
    │          │          │          │          │
    └──────────┴──────────┴──────────┴──────────┘
        REST/gRPC/Event-based, decentralized
```

**SOA vs Microservices fərqləri:**

| Xüsusiyyət | SOA | Microservices |
|-------------|-----|---------------|
| Service ölçüsü | Böyük (enterprise service) | Kiçik (bir domain funksiyası) |
| Əlaqə | ESB vasitəsilə (SOAP/XML) | REST/gRPC/Events (JSON) |
| Data paylaşımı | Paylaşılan DB ola bilər | Hər service öz DB-si |
| Governance | Mərkəzləşdirilmiş | Decentralized |
| Deploy | Adətən birlikdə | Müstəqil |
| Reuse fokus | Service reuse əsasdır | Bounded context əsasdır |

---

## Monolith vs Microservices vs Modulyar Monolit müqayisə cədvəli

| Xüsusiyyət | Monolit | Modulyar Monolit | Microservices |
|------------|---------|------------------|---------------|
| **Deploy** | Tək vahid | Tək vahid | Müstəqil deploy |
| **Codebase** | Tək repo | Tək repo (modullara bölünmüş) | Çoxlu repo |
| **Database** | Paylaşılan DB | Paylaşılan DB (modul əsaslı sxemalar) | Hər service öz DB-si |
| **Əlaqə** | In-process (method call) | In-process (interface/event) | Network (HTTP/gRPC/MQ) |
| **Transaction** | ACID (DB transaction) | ACID (DB transaction) | Eventual consistency (Saga) |
| **Scaling** | Bütövlükdə | Bütövlükdə | Müstəqil |
| **Complexity** | Aşağı | Orta | Yüksək |
| **Team size** | 2-15 developer | 5-30 developer | 30+ developer |
| **Latency** | Ən aşağı | Aşağı | Yüksək (network) |
| **Fault isolation** | Yoxdur | Məhdud | Tam |
| **Tech diversity** | Tək texnologiya | Tək texnologiya | İstənilən texnologiya |
| **Debugging** | Asan | Orta | Çətin |
| **Testing** | Asan | Orta | Çətin |
| **Startup cost** | Aşağı | Orta | Yüksək |
| **Operational cost** | Aşağı | Aşağı | Yüksək |
| **Refactoring** | IDE-dən asan | İnterfeys sayəsində nəzarətli | Service API vasitəsilə |

---

## Conway's Law

> "Sistem dizayn edən təşkilatlar, təşkilatın kommunikasiya strukturunu əks etdirən dizaynlar yaratmağa məhkumdurlar." — Melvin Conway, 1967

Bu o deməkdir ki, **yazılım arxitekturası** qaçılmaz olaraq **təşkilatın strukturunu** əks etdirir.

```
Conway's Law:

Təşkilat strukturu:                  Sistem arxitekturası:

┌───────────────────┐               ┌───────────────────┐
│    Management     │               │   Monolith App    │
│  ┌──────┬──────┐  │               │  ┌──────┬──────┐  │
│  │Team A│Team B│  │     ────▶     │  │Mod A │Mod B │  │
│  │(PHP) │(PHP) │  │               │  │(sıx  │(sıx  │  │
│  │      │      │  │               │  │bağlı)│bağlı)│  │
│  └──────┴──────┘  │               │  └──────┴──────┘  │
└───────────────────┘               └───────────────────┘

VS

┌────────┐ ┌────────┐ ┌────────┐   ┌────────┐ ┌────────┐ ┌────────┐
│Team A  │ │Team B  │ │Team C  │   │Service │ │Service │ │Service │
│(Python)│ │(PHP)   │ │(Go)    │──▶│   A    │ │   B    │ │   C    │
│müstəqil│ │müstəqil│ │müstəqil│   │        │ │        │ │        │
└────────┘ └────────┘ └────────┘   └────────┘ └────────┘ └────────┘
```

**Inverse Conway Maneuver:** Əgər microservices arxitekturası istəyirsinizsə, əvvəlcə komandanı kiçik, müstəqil hissələrə bölün. Arxitektura komanda strukturundan doğacaq.

**Praktik nəticə:**
- 5 nəfərlik bir komandanız varsa, microservices etməyin - monolitlə başlayın
- 50+ developer varsa, modulyar monolit və ya microservices düşünün
- Komanda quruluşunu arxitektura ilə uyğunlaşdırın

---

## Distributed Systems Challenges

### CAP Theorem

```
CAP Theorem (Brewer's Theorem):

Distributed sistemdə eyni anda YALNIZ İKİSİNİ seçə bilərsiniz:

        Consistency (C)
          /          \
         /            \
        /   MÜMKÜN     \
       /    DEYİL       \
      /                  \
Availability (A) ─────── Partition Tolerance (P)

C - Consistency: Hər oxuma ən son yazılan datanı qaytarır
A - Availability: Hər request cavab alır (success və ya failure)
P - Partition Tolerance: Network bölünmələrində sistem işləməyə davam edir

Real dünyada network bölünmələri qaçılmazdır, ona görə:
- CP sistemi: Consistency + Partition Tolerance (məs. MongoDB strong consistency mode)
  → Network partition zamanı availability-dən imtina edir
- AP sistemi: Availability + Partition Tolerance (məs. Cassandra, DynamoDB)
  → Network partition zamanı consistency-dən imtina edir (eventual consistency)
- CA sistemi: Praktikada mümkün deyil distributed sistemdə
  → Yalnız single-node (monolit + tək DB) mümkündür
```

### Network Partition və Latency

*Network Partition və Latency üçün kod nümunəsi:*
```php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ResilientServiceClient
{
    /**
     * Distributed systems challenges-ə qarşı müdafiə strategiyaları.
     */
    public function getProductDetails(int $productId): array
    {
        // 1. Timeout - network latency-ə qarşı
        // Microservice cavab verməsə, sonsuza qədər gözləmə
        $timeout = 3; // saniyə

        // 2. Retry with exponential backoff - geçici xətalar üçün
        $maxRetries = 3;
        $baseDelay = 100; // millisaniyə

        // 3. Cache fallback - availability təmin etmək üçün
        $cacheKey = "product.{$productId}";

        try {
            $response = Http::timeout($timeout)
                ->retry($maxRetries, function (int $attempt) use ($baseDelay) {
                    // Exponential backoff: 100ms, 200ms, 400ms
                    return $baseDelay * (2 ** ($attempt - 1));
                }, function (\Exception $e, $request) {
                    // Yalnız 5xx xətalarında retry et, 4xx-lərdə yox
                    return $e instanceof \Illuminate\Http\Client\ConnectionException
                        || ($e instanceof \Illuminate\Http\Client\RequestException
                            && $e->response->serverError());
                })
                ->get("http://product-service:8005/api/products/{$productId}");

            $data = $response->json();

            // Uğurlu cavabı cache-ə yaz (fallback üçün)
            Cache::put($cacheKey, $data, now()->addMinutes(30));

            return $data;

        } catch (\Throwable $e) {
            // 4. Graceful degradation - service unavailable olduqda
            $cached = Cache::get($cacheKey);

            if ($cached) {
                // Cache-dən köhnə data qaytar (stale data better than no data)
                return array_merge($cached, [
                    '_stale' => true,
                    '_cached_at' => Cache::get("{$cacheKey}.cached_at"),
                ]);
            }

            // Heç bir fallback yoxdur
            throw new ServiceUnavailableException(
                "Product service unavailable: {$e->getMessage()}"
            );
        }
    }
}
```

### Idempotency (Təkrar-müqavimət)

Distributed sistemlərdə network retry-lar eyni əməliyyatın bir neçə dəfə icra edilməsinə səbəb ola bilər. Idempotency bunu həll edir:

*Distributed sistemlərdə network retry-lar eyni əməliyyatın bir neçə də üçün kod nümunəsi:*
```php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Closure;

class IdempotencyMiddleware
{
    /**
     * Eyni request təkrar göndərildikdə, əvvəlki cavabı qaytar.
     * Client hər mutating request ilə Idempotency-Key header göndərir.
     */
    public function handle(Request $request, Closure $next)
    {
        // Yalnız mutating request-lər üçün (POST, PUT, PATCH, DELETE)
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $cacheKey = "idempotency.{$idempotencyKey}";

        // Bu key ilə əvvəl request emal edilib?
        if ($cached = Cache::get($cacheKey)) {
            return response()->json(
                $cached['body'],
                $cached['status'],
                ['X-Idempotent-Replayed' => 'true']
            );
        }

        // İlk dəfədir - emal et
        $response = $next($request);

        // Cavabı cache-ə yaz (24 saat)
        Cache::put($cacheKey, [
            'body' => $response->getData(true),
            'status' => $response->getStatusCode(),
        ], now()->addHours(24));

        return $response;
    }
}
```

---

## Strangler Fig Pattern

Strangler Fig Pattern - monolitdən microservices-ə **tədricən**, **risksiz** keçid strategiyasıdır. Adını tropik bir bitkidən alıb - bu bitki ağaca sarılır, tədricən onu əvəz edir, ağac ölür, bitki qalır.

```
Strangler Fig Pattern:

Mərhələ 1: Bütün trafik monolitə gedir
┌──────────┐     ┌─────────────────────┐
│  Client   │────▶│     Monolith        │
└──────────┘     │  [User][Order][Pay]  │
                 └─────────────────────┘

Mərhələ 2: Proxy/Facade əlavə edilir, ilk service ayrılır
┌──────────┐     ┌──────────────┐     ┌───────────────────┐
│  Client   │────▶│   Proxy /    │────▶│    Monolith       │
└──────────┘     │  API Gateway │     │  [User][--][Pay]  │
                 └──────┬───────┘     └───────────────────┘
                        │
                        │ /orders/*
                        ▼
                 ┌──────────────┐
                 │ Order Service│  (yeni microservice)
                 │  (extracted) │
                 └──────────────┘

Mərhələ 3: Daha çox service ayrılır
┌──────────┐     ┌──────────────┐     ┌───────────────────┐
│  Client   │────▶│   Proxy /    │────▶│    Monolith       │
└──────────┘     │  API Gateway │     │  [User][--][--]   │
                 └──┬────┬──────┘     └───────────────────┘
                    │    │
           /orders/*│    │/payments/*
                    ▼    ▼
              ┌──────┐ ┌──────────┐
              │Order │ │ Payment  │
              │ Svc  │ │  Svc     │
              └──────┘ └──────────┘

Mərhələ 4: Monolit tamamilə əvəz edilir
┌──────────┐     ┌──────────────┐
│  Client   │────▶│   Proxy /    │
└──────────┘     │  API Gateway │
                 └──┬────┬───┬──┘
                    │    │   │
                    ▼    ▼   ▼
              ┌────┐ ┌────┐ ┌────┐
              │User│ │Ordr│ │Pay │
              │Svc │ │Svc │ │Svc │
              └────┘ └────┘ └────┘
```

### Laravel ilə Strangler Fig Implementation

*Laravel ilə Strangler Fig Implementation üçün kod nümunəsi:*
```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StranglerFigProxy
{
    /**
     * Request-i ya monolitdə emal et, ya da yeni microservice-ə yönləndir.
     * Feature flag ilə tədricən keçid edilir.
     */

    /**
     * Hansı route-ların yeni service-ə yönləndirilməli olduğunu göstərən config.
     */
    private array $routingRules = [
        // pattern => ['service_url' => '...', 'enabled' => true/false, 'percentage' => 0-100]
        'api/orders*' => [
            'service_url' => 'http://order-service:8002',
            'enabled' => true,
            'percentage' => 100, // 100% trafik yeni service-ə
        ],
        'api/payments*' => [
            'service_url' => 'http://payment-service:8003',
            'enabled' => true,
            'percentage' => 50, // 50% trafik yeni service-ə (canary)
        ],
        'api/users*' => [
            'service_url' => 'http://user-service:8001',
            'enabled' => false, // Hələ monolitdə
            'percentage' => 0,
        ],
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach ($this->routingRules as $pattern => $config) {
            if (!$config['enabled']) {
                continue;
            }

            if ($request->is($pattern)) {
                // Canary deployment: müəyyən faiz trafiki yeni service-ə
                if (rand(1, 100) <= $config['percentage']) {
                    return $this->proxyToService($request, $config['service_url']);
                }
            }
        }

        // Default: monolitdə emal et
        return $next($request);
    }

    private function proxyToService(Request $request, string $serviceUrl): \Illuminate\Http\JsonResponse
    {
        $targetUrl = $serviceUrl . '/' . $request->path();

        $response = Http::withHeaders($request->headers->all())
            ->timeout(10)
            ->{strtolower($request->method())}($targetUrl, $request->all());

        return response()->json(
            $response->json(),
            $response->status(),
            ['X-Served-By' => 'microservice']
        );
    }
}
```

### Database Migration Strategy

*Database Migration Strategy üçün kod nümunəsi:*
```php
/**
 * Strangler Fig zamanı database migration strategiyası:
 *
 * 1. Dual Write - Həm monolitin DB-sinə, həm yeni service-in DB-sinə yaz
 * 2. Shadow Read - Hər iki DB-dən oxu və nəticələri müqayisə et
 * 3. Cutover - Yeni DB-ni əsas et, köhnəni kəs
 */

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DualWriteOrderService
{
    /**
     * Migration dövründə həm köhnə, həm yeni DB-yə yaz.
     */
    public function createOrder(array $data): Order
    {
        // 1. Köhnə monolith DB-yə yaz (əsas mənbə)
        $order = Order::create($data);

        // 2. Yeni Order Service-ə asinxron yaz (shadow write)
        try {
            Http::async()->post('http://order-service:8002/api/orders', [
                'legacy_id' => $order->id,
                ...$data,
            ]);
        } catch (\Throwable $e) {
            // Shadow write uğursuz olsa da əsas əməliyyat davam edir
            Log::warning('Shadow write failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $order;
    }

    /**
     * Shadow read - hər iki mənbədən oxu, fərqləri log et.
     */
    public function getOrder(int $orderId): Order
    {
        $localOrder = Order::findOrFail($orderId);

        // Shadow read (async, nəticəni müqayisə üçün)
        try {
            $remoteOrder = Http::timeout(2)
                ->get("http://order-service:8002/api/orders/by-legacy/{$orderId}")
                ->json();

            // Fərqləri yoxla
            if ($localOrder->total != $remoteOrder['total']) {
                Log::error('Data inconsistency detected', [
                    'order_id' => $orderId,
                    'local_total' => $localOrder->total,
                    'remote_total' => $remoteOrder['total'],
                ]);
            }
        } catch (\Throwable $e) {
            // Shadow read uğursuz olsa da əsas cavab qaytarılır
        }

        return $localOrder;
    }
}
```

---

## Backend for Frontend (BFF)

BFF pattern - hər frontend platforması üçün **ayrı backend service** yaratmaq yanaşmasıdır. Mobil, web və digər client-lərin fərqli ehtiyacları olduğu üçün, hər biri üçün optimize edilmiş API layer təmin edilir.

```
BFF Pattern:

             ┌──────────────┐
             │ Mobile BFF   │  ← Kiçik payload, pagination, offline support
             │ (Laravel)    │
┌─────────┐  └──────┬───────┘
│ Mobile  │─────────┘  │
│  App    │            ▼
└─────────┘     ┌────────────┐
                │            │
                │ Microservices
                │            │
┌─────────┐    │ ┌────────┐ │
│ Web SPA │────┘ │User Svc│ │
└─────────┘  │   │Order   │ │
             │   │Payment │ │
             ▼   └────────┘ │
       ┌──────────────┐     │
       │   Web BFF    │─────┘
       │  (Laravel)   │  ← Zəngin data, GraphQL/REST, SSR support
       └──────────────┘

┌─────────┐  ┌──────────────┐
│ Admin   │──│  Admin BFF   │  ← Ətraflı data, bulk operations, reports
│ Panel   │  │  (Laravel)   │
└─────────┘  └──────────────┘
```

### Laravel BFF nümunəsi

*Laravel BFF nümunəsi üçün kod nümunəsi:*
```php
namespace App\Http\Controllers\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * Mobile BFF - mobil tətbiq üçün optimize edilmiş API.
 * Kiçik payload, məhdud data, battery-friendly.
 */
class MobileOrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Http::get('http://order-service:8002/api/orders', [
            'user_id' => auth()->id(),
            'limit' => 10,
        ])->json('data');

        // Mobil üçün yalnız lazımi fieldləri qaytar (bandwidth azaltmaq)
        $simplified = collect($orders)->map(fn ($order) => [
            'id' => $order['id'],
            'status' => $order['status'],
            'total' => $order['total'],
            'item_count' => count($order['items']),
            'created' => $order['created_at'],
            // Mobil üçün items detailları YOX - ayrı endpoint-dən alınacaq
        ]);

        return response()->json(['data' => $simplified]);
    }
}

namespace App\Http\Controllers\Web;

/**
 * Web BFF - web tətbiq üçün zəngin API.
 * Bir request-də daha çox data qaytarılır.
 */
class WebOrderController extends Controller
{
    public function index(): JsonResponse
    {
        // Web üçün bir neçə service-dən paralel data çək (aggregation)
        $responses = Http::pool(fn ($pool) => [
            $pool->as('orders')
                ->get('http://order-service:8002/api/orders', [
                    'user_id' => auth()->id(),
                    'include' => 'items,shipping,payments', // Ətraflı data
                    'limit' => 25,
                ]),
            $pool->as('stats')
                ->get('http://analytics-service:8006/api/users/' . auth()->id() . '/order-stats'),
        ]);

        return response()->json([
            'data' => $responses['orders']->json('data'),
            'meta' => [
                'stats' => $responses['stats']->json(),
                'pagination' => $responses['orders']->json('meta'),
            ],
        ]);
    }
}
```

---

## API Versioning

Microservices mühitində API-lərin versiyalanması vacibdir - client-ləri pozmadan API-ni dəyişdirmək lazımdır.

### Versioning Strategiyaları

*Versioning Strategiyaları üçün kod nümunəsi:*
```php
// 1. URL Path Versioning (ən populyar)
// /api/v1/orders
// /api/v2/orders

// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('orders', V1\OrderController::class);
});

Route::prefix('v2')->group(function () {
    Route::apiResource('orders', V2\OrderController::class);
});

// 2. Header Versioning
// Accept: application/vnd.myapp.v2+json

namespace App\Http\Middleware;

class ApiVersionMiddleware
{
    public function handle($request, Closure $next)
    {
        $accept = $request->header('Accept', '');

        if (preg_match('/application\/vnd\.myapp\.v(\d+)\+json/', $accept, $matches)) {
            $request->attributes->set('api_version', (int) $matches[1]);
        } else {
            $request->attributes->set('api_version', 1); // Default v1
        }

        return $next($request);
    }
}

// 3. Query Parameter Versioning
// /api/orders?version=2

// Controller-də versiyaya görə response formatla
namespace App\Http\Controllers\Api;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): JsonResponse
    {
        $version = $request->attributes->get('api_version', 1);

        return match ($version) {
            1 => response()->json(new V1OrderResource($order)),
            2 => response()->json(new V2OrderResource($order)),
            default => response()->json(['error' => 'Unsupported API version'], 400),
        };
    }
}

// V1 Resource - köhnə format
namespace App\Http\Resources\V1;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,
            'status' => $this->status,
            'customer_name' => $this->user->name, // V1-də flat field
        ];
    }
}

// V2 Resource - yeni format (nested, daha ətraflı)
namespace App\Http\Resources\V2;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'total' => [
                'amount' => $this->total,
                'currency' => $this->currency,
                'formatted' => number_format($this->total, 2) . ' ' . $this->currency,
            ],
            'status' => [
                'code' => $this->status,
                'label' => $this->status_label,
                'color' => $this->status_color,
            ],
            'customer' => [        // V2-də nested object
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'items' => V2OrderItemResource::collection($this->items),
            'timestamps' => [
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ],
        ];
    }
}
```

---

## Nə vaxt hansı arxitektura seçilməli

```
Arxitektura Seçim Qərar Ağacı:

Layihə yeni başlayır?
├── Bəli
│   ├── Kiçik komanda (2-5 dev)?
│   │   └── ✅ Monolit (Laravel)
│   ├── Orta komanda (5-15 dev)?
│   │   └── ✅ Modulyar Monolit
│   └── Böyük komanda (15+ dev)?
│       └── Domain-lər aydındırsa?
│           ├── Bəli → ✅ Microservices
│           └── Xeyr → ✅ Modulyar Monolit (sonra ayır)
│
├── Mövcud monolit var, problemlər yaranır?
│   ├── Deploy çox tez-tez lazımdır, bir hissəni dəyişmək digərlərini pozur?
│   │   └── ✅ Strangler Fig ilə tədricən microservices-ə keç
│   ├── Scaling problemi var (bəzi hissələr çox yüklüdür)?
│   │   └── ✅ Yüklü hissəni ayrı service et
│   └── Komanda böyüyür, conflict çoxdur?
│       └── ✅ Əvvəlcə Modulyar Monolit et, sonra ayır
│
└── Xüsusi use case
    ├── Event-driven, asinxron processing çoxdur?
    │   └── ✅ Microservices + Event Bus (RabbitMQ/Kafka)
    ├── Sporadic workload (bəzən çox, bəzən heç yük yoxdur)?
    │   └── ✅ Serverless (Lambda)
    └── Enterprise integration, çox sistem birləşdirmək lazımdır?
        └── ✅ SOA
```

### Qərar üçün əsas amillər

*Qərar üçün əsas amillər üçün kod nümunəsi:*
```php
/**
 * Arxitektura seçimi üçün yoxlama siyahısı (Checklist).
 *
 * Bu real kod deyil, qərar prosesini kodlaşdırmış analogiyadır.
 */

class ArchitectureDecisionHelper
{
    public function recommend(ProjectContext $context): string
    {
        // Faktor 1: Komanda ölçüsü
        if ($context->teamSize <= 5) {
            return 'monolith'; // Kiçik komanda üçün microservices overhead çoxdur
        }

        // Faktor 2: Domain complexity
        if (!$context->domainsAreClearlyDefined) {
            return 'modular_monolith'; // Domain hələ aydın deyilsə, əvvəl kəşf et
        }

        // Faktor 3: Scaling ehtiyacları
        if ($context->hasIndependentScalingNeeds) {
            return 'microservices'; // Müxtəlif hissələr fərqli scale olmalıdırsa
        }

        // Faktor 4: Deploy frequency
        if ($context->deployFrequencyPerWeek > 20) {
            return 'microservices'; // Çox tez-tez deploy lazımdırsa
        }

        // Faktor 5: Operational capacity
        if (!$context->hasDevOpsExpertise) {
            return 'monolith'; // K8s, Docker, monitoring təcrübəsi yoxdursa
        }

        // Default
        return 'modular_monolith'; // Ən balanslaşdırılmış seçim
    }
}
```

---

## Team Size və Arxitektura Əlaqəsi

Amazon-un məşhur "Two Pizza Team" qaydası - bir komanda iki pizza ilə doyacaq qədər kiçik olmalıdır (6-8 nəfər).

```
Team Size → Arxitektura tövsiyəsi:

1-3 developer:
└── ✅ Monolit (Laravel)
    Səbəb: Overhead ən az, bütün resurslar feature development-ə gedir

4-8 developer:
└── ✅ Monolit və ya Modulyar Monolit
    Səbəb: Codebase strukturu lazımdır, amma ayrı service hələ erkəndir

8-20 developer:
└── ✅ Modulyar Monolit (microservices-ə keçid planı ilə)
    Səbəb: Kod conflict başlayır, modul sərhədləri vacibdir

20-50 developer:
└── ✅ Microservices (2-3 service ilə başla, tədricən artır)
    Səbəb: Müstəqil komandalar müstəqil deploy etməlidir

50+ developer:
└── ✅ Microservices + Platform Team
    Səbəb: Hər komandanın öz service-i, platform team infrastrukturu idarə edir

Nəticə:
┌─────────────────────────────────────────────────────┐
│  "Microservices ilə başlamayın.                      │
│   Monolitlə başlayın, böyüdükcə ayırın."            │
│                                                     │
│   — Martin Fowler, "MonolithFirst"                   │
└─────────────────────────────────────────────────────┘
```

---

## Real-World Migration Story

Aşağıda tipik bir Laravel monolitin tədricən microservices-ə keçirilməsi hekayəsi verilmişdir. Bu çox şirkətin yaşadığı real ssenaridır.

### Başlanğıc: E-Commerce Monolith

```
İlk gün (2019): 3 developer, Laravel monolith
- User management
- Product catalog
- Order processing
- Payment handling
- Email notifications
- Admin panel
- Reporting

Bütün bunlar BİR Laravel app-dadır. Əla işləyir!
```

### Problemlər yaranır (2021, 15 developer):

```
Problemlər:
1. Deploy-lar 45 dəqiqə çəkir (böyük test suite)
2. Bir developer-in payment dəyişikliyi catalog-u pozdu
3. Black Friday-da bütün tətbiqi scale etmək lazım gəldi,
   halbuki yalnız order processing yüklü idi
4. Git merge conflict-lər həddən artıq
5. Yeni developer onboarding 2 həftə çəkir
```

### Addım 1: Modulyar Monolit

*Addım 1: Modulyar Monolit üçün kod nümunəsi:*
```php
// İlk addım: kodu modullara ayır, amma EYNI APP daxilində

// Əvvəl:
// app/Services/OrderService.php (Payment-ı birbaşa çağırır)

// Sonra:
// app/Modules/Order/Contracts/OrderServiceInterface.php
// app/Modules/Order/Services/OrderService.php
// app/Modules/Payment/Contracts/PaymentGatewayInterface.php
// app/Modules/Payment/Services/StripePaymentGateway.php

// Modullararası əlaqə YALNIZ interface və event vasitəsilə

// Architectural test (ArchUnit/Deptrac ilə)
// deptrac.yaml
// layers:
//   - name: Order
//     collectors:
//       - type: className
//         regex: App\\Modules\\Order\\.*
//   - name: Payment
//     collectors:
//       - type: className
//         regex: App\\Modules\\Payment\\.*
// ruleset:
//   Order:
//     - Payment  # Order, Payment-ın daxili classlarına müraciət edə bilməz
//                # Yalnız Contracts/ altındakı interface-lərə müraciət edə bilər
```

### Addım 2: İlk Microservice Çıxarılır (Notification)

*Addım 2: İlk Microservice Çıxarılır (Notification) üçün kod nümunəsi:*
```php
// Niyə Notification ilk?
// - Ən az coupling (digər service-lərdən asılı deyil)
// - Uğursuz olsa, kritik deyil (email gecikə bilər)
// - Sadə interface: event qəbul et, email/SMS göndər

// Monolitdə əvvəl:
class OrderPlacedListener
{
    public function handle(OrderPlaced $event): void
    {
        // Monolitdə birbaşa mail göndərirdik
        Mail::to($event->order->user->email)
            ->send(new OrderConfirmationMail($event->order));
    }
}

// İndi: Event-i RabbitMQ-ya göndər, ayrı service emal edir
class OrderPlacedListener
{
    public function handle(OrderPlaced $event): void
    {
        // RabbitMQ-ya publish et
        RabbitMQ::publish('notifications', [
            'type' => 'order_placed',
            'user_id' => $event->order->user_id,
            'order_id' => $event->order->id,
            'email' => $event->order->user->email,
        ]);
    }
}

// Notification Service (ayrı Laravel app):
// Bu service RabbitMQ-dan mesaj alıb email/SMS göndərir
class NotificationWorker
{
    public function handleOrderPlaced(array $message): void
    {
        Mail::to($message['email'])
            ->send(new OrderConfirmationMail($message['order_id']));
    }
}
```

### Addım 3: Order Service Ayrılır

```
İlk feature flag ilə trafik bölünür:
- 10% trafik yeni Order Service-ə
- 90% trafik köhnə monolitə
- Monitorinq: response time, error rate, data consistency
- 2 həftə müşahidə, problem yoxdursa faiz artır
- 10% → 25% → 50% → 75% → 100%
- Əmin olduqdan sonra monolitdən Order kodunu sil
```

### Nəticə (2023):

```
Arxitektura:
┌─────────────┐
│ API Gateway │
│  (Kong)     │
└──┬──┬──┬──┬─┘
   │  │  │  │
   ▼  ▼  ▼  ▼
┌────┐┌────┐┌────┐┌────────────────────┐
│User││Ordr││Ntfy││   Monolith          │
│Svc ││Svc ││Svc ││ (Payment, Catalog,  │
│    ││    ││    ││  Admin, Reporting)   │
└────┘└────┘└────┘└────────────────────┘

Gələcək plan:
- Payment Service ayırmaq (Strangler Fig)
- Catalog Service ayırmaq
- Monolitdə yalnız Admin + Reporting qalacaq
```

---

## Laravel-in Monolit kimi güclü tərəfləri

Laravel monolit arxitektura üçün ən güclü framework-lərdən biridir. Bir çox hallarda microservices-ə ehtiyac yoxdur, çünki Laravel daxilində olan tools bu ehtiyacları qarşılayır.

### 1. Queue sistemi (Asinxron Processing)

*1. Queue sistemi (Asinxron Processing) üçün kod nümunəsi:*
```php
// Microservices-in əsas üstünlüklərindən biri asinxron emal-dır.
// Laravel queues bunu monolitdə həll edir.

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Order $order,
    ) {}

    public function handle(): void
    {
        // Bu ayrı prosesdə, asinxron işləyir
        // Microservice-ə ehtiyac yoxdur
        $this->processPayment();
        $this->updateInventory();
        $this->sendNotification();
    }

    // Queue worker ayrı prosesdə işləyir - "microservice-like"
    // php artisan queue:work --queue=orders --tries=3
}
```

### 2. Event sistemi (Loose Coupling)

*2. Event sistemi (Loose Coupling) üçün kod nümunəsi:*
```php
// Monolitdə modullar arası loose coupling təmin etmək üçün
// Event sistemi microservices event bus-a bənzər işləyir

// EventServiceProvider:
protected $listen = [
    OrderPlaced::class => [
        UpdateInventoryListener::class,
        SendOrderConfirmationListener::class,
        NotifyAdminListener::class,
        UpdateAnalyticsListener::class,
    ],
];

// Hər listener müstəqildir, bir-birini bilmir
// Queue ilə işlədilərsə, paralel və asinxron emal olunur
class SendOrderConfirmationListener implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        // Bu ayrı queue worker-da asinxron çalışır
        Mail::to($event->order->user)->send(new OrderConfirmation($event->order));
    }

    // Uğursuz olarsa - bir microservice-in çökməsi kimi davranır
    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Log::error('Order confirmation failed', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 3. Horizon ilə Monitoring

*3. Horizon ilə Monitoring üçün kod nümunəsi:*
```php
// Laravel Horizon - queue monitoring dashboard
// Microservices mühitində hər service üçün ayrı monitoring lazımdır
// Laravel-də Horizon hər şeyi bir yerdə göstərir

// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-orders' => [
            'connection' => 'redis',
            'queue' => ['orders'],
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balance' => 'auto',
        ],
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'maxProcesses' => 5,
        ],
        'supervisor-reports' => [
            'connection' => 'redis',
            'queue' => ['reports'],
            'maxProcesses' => 3,
        ],
    ],
],
// Hər queue ayrı "service" kimi scale edilir, amma eyni app daxilindədir
```

### 4. Database Read/Write Splitting

*4. Database Read/Write Splitting üçün kod nümunəsi:*
```php
// Monolitdə scaling problemi? Read replica-lar əlavə et

// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1'),
            env('DB_READ_HOST_2'),
        ],
    ],
    'write' => [
        'host' => [env('DB_WRITE_HOST')],
    ],
    'sticky' => true,
],

// Controller-də heç bir dəyişiklik lazım deyil!
// Laravel avtomatik olaraq SELECT-ləri read replica-ya,
// INSERT/UPDATE/DELETE-ləri master-ə yönləndirir
```

### 5. Cache Layer

*5. Cache Layer üçün kod nümunəsi:*
```php
// Microservices-də hər service öz cache-inə sahibdir
// Laravel monolitdə eyni şeyi tag-lı cache ilə edə bilərsiniz

class ProductService
{
    public function getProduct(int $id): Product
    {
        return Cache::tags(['products'])->remember(
            "product.{$id}",
            now()->addHours(1),
            fn () => Product::with('category', 'images')->findOrFail($id)
        );
    }

    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);

        // Yalnız bu product-ın cache-ini təmizlə
        Cache::tags(['products'])->forget("product.{$id}");

        return $product->fresh();
    }

    public function clearAllProductCache(): void
    {
        Cache::tags(['products'])->flush();
    }
}
```

### 6. Task Scheduling

*6. Task Scheduling üçün kod nümunəsi:*
```php
// Schedule ilə background task-lar (microservice olmadan)
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Hər gün report hazırla (ayrı "reporting service" lazım deyil)
    $schedule->job(new GenerateDailyReport)
        ->dailyAt('06:00')
        ->onOneServer(); // Multi-server mühitdə yalnız birində

    // Hər saat köhnə session-ları təmizlə
    $schedule->command('session:gc')
        ->hourly();

    // Hər 5 dəqiqədə stok yoxla
    $schedule->job(new CheckLowStockJob)
        ->everyFiveMinutes()
        ->withoutOverlapping();

    // Hər həftə analitik göndər
    $schedule->job(new SendWeeklyAnalytics)
        ->weeklyOn(1, '09:00') // Bazar ertəsi 09:00
        ->environments(['production']);
}
```

**Nəticə:** Laravel monoliti ilə çox şeyi edə bilərsiniz. Microservices-ə keçmədən əvvəl **bu imkanları tam istifadə edin**. Əgər bunlar yetərsiz qalırsa, onda tədricən (Strangler Fig ilə) ayırmağa başlayın.

---

## İntervyu Sualları və Cavabları

### S1: Monolit və Microservices arasındakı əsas fərq nədir?

**Cavab:** Monolit bütün tətbiqin tək bir deploy vahidində, tək bir prosesdə işlədiyi arxitekturadır. Bütün modullar eyni codebase-dədir, eyni DB-ni paylaşır, in-process əlaqə qurur. Microservices isə tətbiqin kiçik, müstəqil service-lərə bölündüyü arxitekturadır - hər birinin öz DB-si, öz deploy prosesi var və bir-birləri ilə network vasitəsilə əlaqə qurur.

Əsas fərqlər:
- **Deploy**: Monolit - hamısı birlikdə; Microservices - hər biri ayrıca
- **Data**: Monolit - paylaşılan DB; Microservices - hər service öz DB-si
- **Communication**: Monolit - method call; Microservices - HTTP/gRPC/MQ
- **Transaction**: Monolit - ACID; Microservices - Eventual Consistency, Saga
- **Failure**: Monolit - bir modul çöksə, hamısı çökür; Microservices - fault isolation

---

### S2: Niyə kiçik bir startup üçün microservices tövsiyə olunmur?

**Cavab:** Kiçik startup-lar üçün microservices uyğun deyil bir neçə səbəbə görə:

1. **Operational overhead**: Kubernetes, Docker, service mesh, distributed tracing, log aggregation - bütün bunlar idarə edilməlidir. 3-5 nəfərlik komanda buna vaxt itirməməlidir.
2. **Premature decomposition**: Startup dövründə domain hələ tam aydın deyil, tez-tez dəyişir. Yanlış yerdən kəssəniz, service-lər arası kommunikasiya cəhənnəmə çevrilir.
3. **Distributed systems complexity**: Network latency, partial failures, data consistency - bunlar monolit-də yoxdur.
4. **Development speed**: Monolitdə bir feature-ı 1 gündə yazırsınızsa, microservices-də eyni feature 3-5 gün çəkə bilər.
5. **Cost**: Hər service üçün ayrı server/container, ayrı CI/CD pipeline, ayrı monitoring lazımdır.

Martin Fowler-in məsləhəti: "MonolithFirst" - əvvəlcə monolit yazın, domain-i anlayın, lazım gəldikdə ayırın.

---

### S3: CAP Theorem-i izah edin və microservices-ə necə təsir edir?

**Cavab:** CAP Theorem deyir ki, distributed sistemdə eyni anda yalnız ikisini əldə edə bilərsiniz: Consistency (hər oxuma son yazılanı qaytarır), Availability (hər request cavab alır), Partition Tolerance (network bölünmələrində sistem işləyir).

Real dünyada network bölünmələri qaçılmazdır, ona görə seçim C vs A arasındadır:
- **CP**: Consistency qorunur, availability qurban verilir (network bölünmə zamanı bəzi request-lər reject olunur)
- **AP**: Availability qorunur, consistency qurban verilir (eventual consistency)

Microservices-ə təsiri: Hər service öz DB-sinə sahibdir, ona görə distributed transaction mümkün deyil. Eventual consistency qəbul edilməlidir. Saga pattern ilə cross-service data consistency təmin olunur. Monolitdə isə tək DB olduğu üçün CAP problemi yoxdur - ACID transaction yetərlidir.

---

### S4: Circuit Breaker pattern nə üçün lazımdır?

**Cavab:** Microservices-də bir service çökdükdə, onu çağıran service-lər timeout-a gözləyir. Bu cascading failure yaradır - bir service-in çökməsi bütün sistemi yavaşladır.

Circuit Breaker bunu həll edir:
- **Closed** state: Normal əlaqə, request-lər keçir
- **Open** state: Service çökdükdə dövrə açılır, request-lər dərhal reject olunur (gözləmə yoxdur), fallback cavab qaytarılır
- **Half-Open** state: Bir müddətdən sonra test request göndərilir, uğurlu olsa circuit bağlanır

Faydaları: cascading failure-ın qarşısını alır, sağlam olmayan service-ə əlavə yük vermir, fallback ilə graceful degradation təmin edir.

---

### S5: Saga pattern nədir və nə vaxt istifadə olunur?

**Cavab:** Saga - microservices-də distributed transaction əvəz edən pattern-dir. Bir sıra lokal transaksiyalardan ibarətdir, hər birinin compensation (geri qaytarma) əməliyyatı var.

**İki növü var:**
1. **Choreography**: Hər service event publish edir, növbəti service subscribe olub öz işini görür. Koordinator yoxdur. Sadə flow-lar üçün yaxşıdır, amma mürəkkəb flow-larda izləmək çətindir.
2. **Orchestration**: Mərkəzi Saga Orchestrator bütün addımları ardıcıl idarə edir. Mürəkkəb flow-lar üçün yaxşıdır, amma orchestrator single point of failure ola bilər.

**Misal:** Sifariş yaratma: Order yarat → Stok rezerv et → Ödəniş al → Bildiriş göndər. Ödəniş uğursuz olarsa: Stoku geri burax → Sifarişi ləğv et (compensation).

**Nə vaxt istifadə olunur:** Bir neçə microservice-in datası eyni business transaction-da dəyişməli olduqda. Monolitdə DB transaction yetərli olduğu üçün Saga lazım deyil.

---

### S6: Strangler Fig pattern nədir?

**Cavab:** Strangler Fig - monolitdən microservices-ə **tədricən, risksiz** keçid strategiyasıdır. Adını tropik bitkidən alıb: bitki ağaca sarılır, tədricən onu əvəz edir, ağac ölür, bitki qalır.

Addımlar:
1. Monolitin qarşısına proxy/API Gateway qoyulur
2. Ən az coupled modul seçilir və ayrı microservice olaraq yazılır
3. Proxy müəyyən route-ları yeni service-ə yönləndirir (əvvəlcə kiçik faiz - canary)
4. Yeni service stabil işlədikdən sonra trafik tam yönləndirilir
5. Monolitdən həmin modul silinir
6. Növbəti modul üçün təkrarlanır

**Üstünlüyü:** Big bang rewrite yoxdur (çox risklidir), tədricən keçid, geri qaytarma asandır, istənilən vaxtda dayandırıla bilər.

---

### S7: Conway's Law nədir və arxitekturaya necə təsir edir?

**Cavab:** Conway's Law deyir ki: "Sistem dizayn edən təşkilatlar, təşkilatın kommunikasiya strukturunu əks etdirən dizaynlar yaradırlar." Yəni yazılım arxitekturası komanda strukturunun güzgüsüdür.

Əgər 3 komandanız varsa və onlar bir-biri ilə sıx əlaqədədirsə, monolit yazacaqsınız. Əgər 10 müstəqil komandanız varsa, microservices meydana gələcək.

**Inverse Conway Maneuver:** Bu qanundan bilərəkdən istifadə etmək. Əgər microservices istəyirsinizsə, əvvəlcə komandanı kiçik, müstəqil hissələrə bölün - arxitektura özbaşına uyğunlaşacaq. Böyük bir komandanı ayırmadan microservices etməyə çalışmaq uğursuz olur.

---

### S8: Modulyar Monolit nədir və niyə populyarlaşır?

**Cavab:** Modulyar Monolit - tək bir deploy vahidində işləyən, amma daxili olaraq aydın sərhədli modullara bölünmüş arxitekturadır. Monolitin sadəliyi (tək deploy, ACID, in-process) ilə microservices-in təşkilatı üstünlüklərini (aydın sərhədlər, loose coupling, team autonomy) birləşdirir.

Populyarlaşmasının səbəbləri:
- Microservices-in operational overhead-i çox yüksəkdir
- Bir çox şirkət microservices-ə keçdikdən sonra peşman oldu ("distributed monolith" yaratdılar)
- Shopify kimi böyük şirkətlər modulyar monolit istifadə edir
- Əvvəlcə modulyar monolit yazıb, lazım gəldikdə modulu microservice-ə çevirmək ən təhlükəsiz yoldur

Qaydası: Modullar bir-birinin daxili classlarına müraciət etmir, yalnız public interface və event vasitəsilə əlaqə qurur. Bu sayədə istənilən modul gələcəkdə asanlıqla ayrıla bilər.

---

### S9: BFF (Backend for Frontend) pattern nədir?

**Cavab:** BFF - hər frontend platforması üçün ayrı backend API layer yaratmaq yanaşmasıdır.

Səbəbi: Mobil tətbiq az data, kiçik payload istəyir (battery, bandwidth məhdudiyyəti). Web tətbiq daha zəngin data istəyir. Admin panel ətraflı data, bulk operations istəyir. Bir ümumi API hamısını razı sala bilmir.

BFF ilə: Mobile BFF yalnız lazımi field-ləri qaytarır, Web BFF aggregation edir (bir request-lə bir neçə service-dən data yığır), Admin BFF ətraflı data və report-lar qaytarır.

**Mənfi cəhəti:** Kod dublikatı ola bilər, daha çox service idarə etmək lazımdır.

---

### S10: Microservices-də ən böyük səhv nədir?

**Cavab:** Ən böyük səhv **distributed monolith** yaratmaqdır. Bu elə bir sistemdir ki, microservices adı ilə yazılır, amma:
- Service-lər bir-birindən sıx asılıdır (tight coupling)
- Bir service-i deploy etmək üçün digər service-ləri də deploy etmək lazımdır
- Paylaşılan DB istifadə olunur
- Sinxron chain: A → B → C → D (bir request 4 service-i gəzir)
- Service-lər arasında data model paylaşılır

Bu, monolitin bütün mənfi cəhətlərini saxlayır, üstəgəl distributed system-in mürəkkəbliyini əlavə edir. Ən pis haldır. Həlli: ya düzgün microservices edin (loose coupling, own DB, async communication), ya da monolitə geri dönün.

---

### S11: Serverless nə vaxt uyğundur?

**Cavab:** Serverless bu hallarda uyğundur:
- **Sporadic workload**: Yük bəzən çox, bəzən sıfırdır (məs. gecə heç request yoxdur)
- **Event-driven processing**: S3-ə fayl yükləndi → emal et, SQS mesajı gəldi → işlə
- **Background tasks**: Image resize, PDF generation, data transformation
- **MVP/Prototype**: Tez bir şey düzəldib sınamaq lazımdır, server idarəetməklə vaxt itirmək istəmirsiniz
- **Webhooks**: Xarici service-lərdən gələn event-ləri qəbul edən sadə endpoint-lər

Uyğun deyil:
- **Long-running processes**: AWS Lambda max 15 dəqiqə (Laravel request lifecycle buna uyğun deyil)
- **Consistent latency**: Cold start problemi real-time tətbiqlər üçün uyğun deyil
- **Stateful applications**: WebSocket, session-based apps
- **High-throughput, steady load**: Həmişə yüklü olan tətbiqlər üçün server daha ucuz başa gəlir

---

### S12: API Versioning niyə vacibdir və hansı yanaşma ən yaxşısıdır?

**Cavab:** API versioning vacibdir çünki microservices mühitində API consumer-lar (digər service-lər, mobil app-lar) köhnə API-yə bağlıdır. API-ni dəyişəndə bütün consumer-ları eyni anda yeniləmək mümkün deyil.

Üç əsas yanaşma:
1. **URL Path** (`/api/v1/orders`): Ən sadə, ən çox istifadə olunan. Aydın, cache-friendly.
2. **Header** (`Accept: application/vnd.app.v2+json`): URL təmiz qalır, amma test etmək çətindir.
3. **Query Parameter** (`/api/orders?version=2`): Sadə, amma URL-yə qarışır.

Tövsiyəm: **URL Path versioning** - ən geniş yayılmış, ən sadə, ən az sürprizli. Breaking change olduqda yeni versiya yaradılır, köhnə versiya müəyyən müddət dəstəklənir (deprecation period), sonra silinir.

---

### S13: Distributed tracing nədir və niyə microservices-də vacibdir?

**Cavab:** Distributed tracing - bir request-in microservices arasındakı bütün yolunu izləmək texnologiyasıdır. Monolitdə stack trace bütün flow-nu göstərir. Microservices-də isə bir request 5-10 service-i gəzə bilər, hər birinin öz log-u var.

Distributed tracing hər request-ə unikal trace ID verir. Bu ID bütün service-lər arasında ötürülür. Nəticədə bütün request yolunu bir dashboard-da görə bilərsiniz.

*Distributed tracing hər request-ə unikal trace ID verir. Bu ID bütün s üçün kod nümunəsi:*
```php
// Hər request-ə trace ID əlavə et
class TraceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $traceId = $request->header('X-Trace-ID', (string) Str::uuid());
        $request->headers->set('X-Trace-ID', $traceId);
        
        // Bütün log-lara trace ID əlavə et
        Log::shareContext(['trace_id' => $traceId]);
        
        $response = $next($request);
        $response->headers->set('X-Trace-ID', $traceId);
        
        return $response;
    }
}

// Digər service-ə request göndərəndə trace ID-ni ötür
Http::withHeaders(['X-Trace-ID' => request()->header('X-Trace-ID')])
    ->get('http://payment-service/api/payments');
```

Tools: Jaeger, Zipkin, AWS X-Ray, Datadog APM.

---

### S14: Monoliti nə vaxt ayırmaq lazımdır? Hansı signallar var?

**Cavab:** Bu signallar göründükdə ayırmağı düşünün:

1. **Deploy qorxusu**: Kiçik dəyişiklik üçün bütün sistemi deploy etmək riskli hiss olunur
2. **Uzun CI/CD**: Build/test 30+ dəqiqə çəkir
3. **Team conflict**: Merge conflict-lər həddən artıq, komandalar bir-birini gözləyir
4. **Scaling problemi**: Tətbiqin bir hissəsi çox yüklüdür, amma bütövü scale etmək lazımdır
5. **Onboarding çətinliyi**: Yeni developer kodu anlamaq üçün həftələr sərf edir
6. **Texnoloji lock-in**: Bir modul üçün fərqli texnologiya lazımdır amma mümkün deyil
7. **Blast radius**: Bir modulda bug bütün sistemi çökdürür

**Amma əvvəlcə soruşun:**
- Bu problemlər modulyar monolit ilə həll oluna bilməzmi?
- DevOps təcrübəmiz yetərlidirmi?
- Domain sərhədləri aydındırmı?

Cavab "yox"dursa, əvvəlcə modulyar monolit edin, sonra ayırın.

---

## Anti-patternlər

**1. Erkən Microservice-ə Keçid (Premature Decomposition)**
Layihənin ilk günündən domain sərhədlərini bilmədən microservice arxitekturası qurmaq — yanlış bölünmüş service-lər çoxlu distributed transaction, data uyğunsuzluğu, ağır network overhead yaradır. Əvvəlcə monolit qurun, domain sərhədlərini öyrənin, yalnız sonra ayırın.

**2. Distributed Monolith**
Servis kimi görkəm verən amma hər deployment-da hamının bir yerdə deploy olunması lazım olan microservice arxitekturası — microservice-in mürəkkəbliyini alırsınız, faydalarını almırsınız. Hər servis həqiqətən müstəqil deploy oluna bilməlidir; əgər olmursa, arxitekturu yenidən nəzərdən keçirin.

**3. Microservice-lərdə Sinxron HTTP Zənciri**
`Order Service → Payment Service → Inventory Service → Notification Service` kimi dərin sinxron HTTP zəncirləri qurmaq — bir servis gecikdikdə bütün zəncir gecikir, bir servis çökdükdə bütün əməliyyat uğursuz olur. Uzun iş axınları üçün asinxron mesajlaşma (event-driven) istifadə edin.

**4. Shared Database Microservice-lər Arasında**
Bir neçə microservice-in eyni database schema-sını paylaşması — servislərin müstəqilliyi pozulur, bir servisin schema dəyişikliyi digərini sındırır, köçürmə mümkünsüzləşir. Hər microservice-in öz dedicated database-i olmalıdır; data paylaşmaq üçün API ya da event istifadə edin.

**5. Monolitin Bütün Problemlərini Arxitektura Dəyişikliyi ilə Həll Etməyə Çalışmaq**
Yavaş CI/CD, yavaş test, poor code quality kimi problemlər üçün microservice-ə keçidi həll kimi görmək — bu problemlər arxitektura problemi deyil, mühəndislik prosesi problemidir. Əvvəlcə kod keyfiyyətini, test coverage-ı, CI/CD pipeline-ı düzəldin; arxitektura dəyişikliyi son çarədir.

**6. Conway Qanununu Nəzərə Almamaq**
Komanda strukturuna uyğun olmayan arxitektura seçmək — 3 nəfərlik komanda onlarca microservice saxlamağa çalışır, operational overhead komandanı boğur. Arxitektura komandanın ölçüsünə, bacarığına və domain bilgisine uyğun olmalıdır; "2 pizza team" qaydasını nəzərə alın.
