# Microservices vs Modular Monolith — Dərin Müqayisə

---

## 1. Əsas Fərqlər

```
                    Modular Monolith              Microservices
                    ────────────────────────      ────────────────────────
Deployment unit     Tək binary/app                Çox müstəqil service
Scaling             Bütün app bir yerdə scale     Hər service müstəqil scale
Communication       In-process (function call)    Out-of-process (network)
Memory sharing      Paylaşılan process memory     Heç biri paylaşmır
Team autonomy       Orta (shared codebase)        Yüksək (ayrı repo/deploy)
Complexity          Aşağı-Orta                    Yüksək
Latency             Nanoseconds (in-memory)       Milliseconds (network)
Consistency         Strong (ACID transaction)     Eventual consistency
Testing             Asandır                       Çətindir (contract tests)
Debugging           Asandır (stack trace)         Çətindir (distributed trace)
Fault isolation     Aşağı (crash → all down)      Yüksək (circuit breaker)
```

---

## 2. Modulyar Monolit: In-Process, Single Deployment

Modulyar monolit — bir deployment unit, lakin daxili olaraq modul sərhədləri var:

```
┌──────────────────────────────────────────────────────────┐
│                    Laravel Application                    │
│                                                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────────────┐  │
│  │   Order    │  │  Payment   │  │   Notification     │  │
│  │   Module   │  │  Module    │  │      Module        │  │
│  │            │  │            │  │                    │  │
│  │ OrderSvc   │→ │ PaySvc     │→ │ NotifSvc           │  │
│  │ OrderRepo  │  │ PayRepo    │  │ NotifRepo          │  │
│  └────────────┘  └────────────┘  └────────────────────┘  │
│         ↓                ↓                ↓              │
│  ┌──────────────────────────────────────────────────────┐ │
│  │              Shared Database (PostgreSQL)            │ │
│  └──────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
         ↓ deploy
    Single VM / Container
```

**Üstünlüklər:**
- Deployment sadədir — bir artifact
- ACID transaction modüllar arasında işləyir
- Refactoring asandır
- Local development asandır
- Performance: in-process calls

---

## 3. Microservices: Out-of-Process, Independent Deployment

```
┌────────────────┐   HTTP/gRPC   ┌────────────────┐
│  Order Service │ ─────────────→│ Payment Service│
│  :8001         │               │  :8002         │
│  [PostgreSQL]  │               │  [PostgreSQL]  │
└────────────────┘               └────────────────┘
        ↓ async event                    ↓
   [RabbitMQ/Kafka]              ┌────────────────┐
        ↓                        │ Notif. Service │
┌────────────────┐               │  :8003         │
│ Inventory Svc  │               │  [PostgreSQL]  │
│  :8004         │               └────────────────┘
│  [PostgreSQL]  │
└────────────────┘

Hər service:
  - Ayrı repository
  - Ayrı CI/CD pipeline
  - Ayrı database
  - Müstəqil deploy edilə bilər
```

---

## 4. Communication Patterns

### 4.1 Sync: REST vs gRPC

**REST:**
```
Pros: Universal, human-readable, tooling çoxdur
Cons: HTTP overhead, schema validation əl ilə, versioning çətin

POST /api/orders HTTP/1.1
Content-Type: application/json
{"product_id": 1, "quantity": 2}
```

**gRPC:**
```
Pros: Binary protocol (Protocol Buffers), strongly typed, code generation, streaming
Cons: Setup mürəkkəb, browser support məhduddur, debug çətin

// Protobuf schema
service OrderService {
  rpc CreateOrder (CreateOrderRequest) returns (CreateOrderResponse);
  rpc GetOrders   (GetOrdersRequest)   returns (stream Order);
}
message CreateOrderRequest {
  int32  product_id = 1;
  int32  quantity   = 2;
}
```

**Laravel-də gRPC nümunəsi:**

```php
// composer require spiral/roadrunner-grpc grpc/grpc

// proto/order.proto
// service OrderService {
//   rpc CreateOrder (CreateOrderRequest) returns (CreateOrderResponse) {}
// }

// Generated PHP stub-dan implement:
// app/Grpc/OrderService.php

namespace App\Grpc;

use App\Services\OrderService as OrderDomainService;
use Spiral\RoadRunner\GRPC\ContextInterface;

class OrderService implements \OrderService\OrderServiceInterface
{
    public function __construct(
        private OrderDomainService $orderService
    ) {}

    public function CreateOrder(
        ContextInterface $ctx,
        \OrderService\CreateOrderRequest $in
    ): \OrderService\CreateOrderResponse {
        $order = $this->orderService->create(
            productId: $in->getProductId(),
            quantity:  $in->getQuantity(),
        );

        $response = new \OrderService\CreateOrderResponse();
        $response->setOrderId($order->id);
        $response->setStatus('created');

        return $response;
    }
}
```

---

### 4.2 Async: Domain Events vs Integration Events Fərqi

```
Domain Event:
  - Bir bounded context daxilindədir
  - In-process ola bilər (event bus, observer)
  - "OrderWasPlaced" — Order module öz daxilindəki hadisəni elan edir
  - Implementation detail

Integration Event:
  - Bounded context-lər arasındadır
  - Always async (message broker vasitəsilə)
  - "OrderPlacedIntegrationEvent" — Payment service-ə, Notif service-ə xəbər verir
  - Public contract (versioning lazımdır)
```

*- Public contract (versioning lazımdır) üçün kod nümunəsi:*
```php
// Domain Event (in-process)
class OrderWasPlaced
{
    public function __construct(
        public readonly int    $orderId,
        public readonly int    $userId,
        public readonly float  $total,
        public readonly Carbon $occurredAt,
    ) {}
}

// Integration Event (cross-service, async)
class OrderPlacedIntegrationEvent
{
    public string $eventType    = 'order.placed';
    public string $eventVersion = '1.0';
    public string $eventId;
    public array  $payload;

    public function __construct(int $orderId, int $userId, float $total)
    {
        $this->eventId = (string) \Str::uuid();
        $this->payload = [
            'order_id'    => $orderId,
            'user_id'     => $userId,
            'total'       => $total,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
```

*'occurred_at' => now()->toISOString(), üçün kod nümunəsi:*
```php
// Integration event publish etmək (RabbitMQ/Redis Pub-Sub)
class OrderService
{
    public function placeOrder(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {
            $order = Order::create($data);
            // Domain event dispatch (in-process)
            event(new OrderWasPlaced($order->id, $order->user_id, $order->total));
            return $order;
        });

        // Integration event — transaction-dan sonra
        $this->messageBus->publish(
            topic: 'orders',
            event: new OrderPlacedIntegrationEvent($order->id, $order->user_id, $order->total)
        );

        return $order;
    }
}
```

---

## 5. Data Management

### 5.1 Shared Database (Modular Monolith)

```
┌─────────────────────────────────────────┐
│           Shared PostgreSQL             │
│                                         │
│  orders  ←──── order_items              │
│  users   ←──── payments                 │
│  products ───→ inventory                │
└─────────────────────────────────────────┘
```

**Pros:**
- ACID transactions modüllar arasında işləyir
- JOIN-lar asandır
- Data consistency avtomatikdir

**Cons:**
- Schema dəyişikliyi bütün modüllara təsir edir
- Bir modülun performance problemi digərini etkiləyir
- Modüllər arasında hidden coupling yaranır

---

### 5.2 Database Per Service

```
Order Service     Payment Service    User Service
[orders DB]       [payments DB]      [users DB]
   ↓                  ↓                 ↓
PostgreSQL-1      PostgreSQL-2       PostgreSQL-3
```

**Pros:**
- Service tam müstəqildir
- Hər service öz DB texnologiyasını seçə bilər
- Scaling ayrı-ayrı

**Cons:**
- JOIN problemi: SQL JOIN işləmir
- Distributed transaction çətin
- Data consistency eventual

**Join Probleminin Həlli:**

```php
// ❌ Microservice-lər arasında SQL JOIN mümkün deyil
// SELECT o.*, u.name FROM orders o JOIN users u ON o.user_id = u.id

// ✅ Application-level join
class OrderQueryService
{
    public function getOrdersWithUsers(): array
    {
        // Order service-dən sifariş al
        $orders = $this->orderRepository->findAll();

        // User ID-lərini topla
        $userIds = $orders->pluck('user_id')->unique()->values();

        // User service-dən batch istəklə user-ları al
        $users = $this->userServiceClient->getUsersByIds($userIds->toArray());
        $usersById = collect($users)->keyBy('id');

        // Application-level join
        return $orders->map(function ($order) use ($usersById) {
            $order->user = $usersById[$order->user_id] ?? null;
            return $order;
        })->toArray();
    }
}
```

---

## 6. Distributed Transactions: Saga Pattern

### 6.1 Choreography-based Saga

```
Hər service öz işini edir, uğursuz olsa compensation event yayır.

Order Service      Payment Service    Inventory Service
      │                   │                  │
      ├─ OrderCreated ────→│                  │
      │                   ├─ PaymentCharged ─→│
      │                   │                  ├─ InventoryReserved
      │                   │                  │
      │  ── Failure ──────────────────────────
      │                   ├─ PaymentRefunded ─→
      │←─ OrderCancelled──┤
```

*│←─ OrderCancelled──┤ üçün kod nümunəsi:*
```php
// Order service
class OrderSaga
{
    public function handle(OrderCreated $event): void
    {
        // Payment service-ə event yay
        $this->messageBus->publish('payments', new ChargPaymentCommand(
            orderId: $event->orderId,
            amount:  $event->amount,
            userId:  $event->userId,
        ));
    }
}

// Payment service — payment işləndi
class PaymentChargedListener
{
    public function handle(PaymentCharged $event): void
    {
        // Inventory service-ə event yay
        $this->messageBus->publish('inventory', new ReserveInventoryCommand(
            orderId:   $event->orderId,
            productId: $event->productId,
            quantity:  $event->quantity,
        ));
    }
}

// Inventory service — stok yoxdur
class ReserveInventoryHandler
{
    public function handle(ReserveInventoryCommand $command): void
    {
        if (! $this->canReserve($command->productId, $command->quantity)) {
            // Compensation: payment-i geri qaytart
            $this->messageBus->publish('payments', new RefundPaymentCommand(
                orderId: $command->orderId,
            ));
        }
    }
}
```

---

### 6.2 Orchestration-based Saga

```
Mərkəzi orchestrator hər addımı idarə edir.

OrderOrchestrator
    │
    ├─ 1. ChargPayment → Payment Service
    │      ↓ success
    ├─ 2. ReserveInventory → Inventory Service
    │      ↓ failure
    ├─ 3. RefundPayment (compensation) → Payment Service
    │      ↓ success
    └─ 4. CancelOrder → Order Service
```

*└─ 4. CancelOrder → Order Service üçün kod nümunəsi:*
```php
// app/Sagas/PlaceOrderSaga.php
class PlaceOrderSaga
{
    private string $state = 'started';

    public function __construct(
        private int   $orderId,
        private int   $userId,
        private float $amount
    ) {}

    public function run(): void
    {
        try {
            $this->state = 'charging_payment';
            $paymentId = $this->chargePayment();

            $this->state = 'reserving_inventory';
            $this->reserveInventory();

            $this->state = 'completed';
            $this->completeOrder();

        } catch (PaymentFailedException $e) {
            $this->state = 'failed';
            $this->cancelOrder('Payment failed: ' . $e->getMessage());

        } catch (InventoryUnavailableException $e) {
            $this->state = 'compensating';
            $this->refundPayment($paymentId ?? null);
            $this->cancelOrder('Inventory unavailable');
        }
    }

    private function chargePayment(): string
    {
        // HTTP call to payment service
        $response = Http::post('http://payment-service/charge', [
            'order_id' => $this->orderId,
            'amount'   => $this->amount,
            'user_id'  => $this->userId,
        ]);

        if (! $response->successful()) {
            throw new PaymentFailedException($response->json('message'));
        }

        return $response->json('payment_id');
    }

    private function reserveInventory(): void
    {
        $response = Http::post('http://inventory-service/reserve', [
            'order_id' => $this->orderId,
        ]);

        if (! $response->successful()) {
            throw new InventoryUnavailableException($response->json('message'));
        }
    }

    private function refundPayment(?string $paymentId): void
    {
        if (! $paymentId) return;

        Http::post('http://payment-service/refund', [
            'payment_id' => $paymentId,
        ]);
    }

    private function cancelOrder(string $reason): void
    {
        Order::find($this->orderId)->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
        ]);
    }

    private function completeOrder(): void
    {
        Order::find($this->orderId)->update(['status' => 'confirmed']);
    }
}
```

---

## 7. Laravel-də Modular Monolith — Order + Payment Module

```
app/
  Modules/
    Order/
      Actions/
        PlaceOrderAction.php
      DTOs/
        PlaceOrderDTO.php
      Events/
        OrderWasPlaced.php
      Http/
        Controllers/
          OrderController.php
        Requests/
          PlaceOrderRequest.php
        Resources/
          OrderResource.php
      Models/
        Order.php
        OrderItem.php
      Repositories/
        OrderRepository.php
        OrderRepositoryInterface.php
      Routes/
        api.php
      OrderServiceProvider.php
    Payment/
      Actions/
        ChargePaymentAction.php
      Events/
        PaymentWasCharged.php
      Listeners/
        ChargePaymentOnOrderPlaced.php
      Models/
        Payment.php
      PaymentServiceProvider.php
```

*PaymentServiceProvider.php üçün kod nümunəsi:*
```php
// app/Modules/Order/OrderServiceProvider.php
namespace App\Modules\Order;

use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\Order\Repositories\OrderRepositoryInterface::class,
            \App\Modules\Order\Repositories\OrderRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
```

*$this->loadMigrationsFrom(__DIR__ . '/Database/Migrations'); üçün kod nümunəsi:*
```php
// app/Modules/Order/Actions/PlaceOrderAction.php
namespace App\Modules\Order\Actions;

use App\Modules\Order\DTOs\PlaceOrderDTO;
use App\Modules\Order\Events\OrderWasPlaced;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Repositories\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PlaceOrderAction
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {}

    public function execute(PlaceOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $order = $this->orderRepository->create([
                'user_id'    => $dto->userId,
                'total'      => $dto->total,
                'status'     => 'pending',
                'items'      => $dto->items,
            ]);

            // Domain event — Payment module-u dinləyir
            event(new OrderWasPlaced(
                orderId:    $order->id,
                userId:     $dto->userId,
                total:      $dto->total,
                occurredAt: now(),
            ));

            return $order;
        });
    }
}
```

*həll yanaşmasını üçün kod nümunəsi:*
```php
// app/Modules/Payment/Listeners/ChargePaymentOnOrderPlaced.php
namespace App\Modules\Payment\Listeners;

use App\Modules\Order\Events\OrderWasPlaced;
use App\Modules\Payment\Actions\ChargePaymentAction;

class ChargePaymentOnOrderPlaced
{
    public function __construct(
        private ChargePaymentAction $chargeAction
    ) {}

    public function handle(OrderWasPlaced $event): void
    {
        // Eyni transaction-da (modular monolith üstünlüyü!)
        $this->chargeAction->execute(
            orderId: $event->orderId,
            userId:  $event->userId,
            amount:  $event->total,
        );
    }
}
```

*amount:  $event->total, üçün kod nümunəsi:*
```php
// app/Modules/Order/Events/OrderWasPlaced.php — EventServiceProvider-da qeydiyyat
// app/Providers/EventServiceProvider.php
protected $listen = [
    \App\Modules\Order\Events\OrderWasPlaced::class => [
        \App\Modules\Payment\Listeners\ChargePaymentOnOrderPlaced::class,
        \App\Modules\Notification\Listeners\SendOrderConfirmation::class,
    ],
];
```

---

## 8. Laravel ilə HTTP Microservice Client (Retry, Circuit Breaker, Timeout)

*8. Laravel ilə HTTP Microservice Client (Retry, Circuit Breaker, Timeout) üçün kod nümunəsi:*
```php
<?php
// app/Services/PaymentServiceClient.php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PaymentServiceClient
{
    private string $baseUrl;
    private int    $timeoutSeconds  = 5;
    private int    $retryTimes      = 3;
    private int    $retryDelayMs    = 100;

    public function __construct()
    {
        $this->baseUrl = config('services.payment.url', 'http://payment-service');
    }

    public function charge(int $orderId, float $amount, int $userId): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->retry(
                    times: $this->retryTimes,
                    sleepMilliseconds: $this->retryDelayMs,
                    when: fn(\Exception $e) => $e instanceof ConnectionException
                )
                ->withHeaders([
                    'X-Service-Name' => 'order-service',
                    'X-Request-ID'   => (string) \Str::uuid(),
                ])
                ->post("{$this->baseUrl}/api/charges", [
                    'order_id' => $orderId,
                    'amount'   => $amount,
                    'user_id'  => $userId,
                ]);

            if ($response->serverError()) {
                throw new \RuntimeException(
                    "Payment service server error: {$response->status()}"
                );
            }

            $response->throw(); // 4xx-ə exception at

            return $response->json();

        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                "Payment service is unavailable: {$e->getMessage()}"
            );
        }
    }
}
```

**Circuit Breaker ilə:**

```php
<?php
// app/Services/CircuitBreaker.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private const STATE_CLOSED   = 'closed';   // Normal — requests keçir
    private const STATE_OPEN     = 'open';     // Broken — requests keçmir
    private const STATE_HALF_OPEN = 'half_open'; // Test — bəzi requests keçir

    public function __construct(
        private string $serviceName,
        private int    $failureThreshold = 5,    // 5 uğursuzluqdan sonra aç
        private int    $recoveryTime     = 60,   // 60 saniyə sonra half-open
    ) {}

    public function call(callable $service): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            throw new \RuntimeException(
                "Circuit breaker is OPEN for {$this->serviceName}. Service unavailable."
            );
        }

        try {
            $result = $service();
            $this->onSuccess();
            return $result;

        } catch (\Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function getState(): string
    {
        $failures    = Cache::get($this->failureKey(), 0);
        $lastFailure = Cache::get($this->lastFailureKey());

        if ($failures >= $this->failureThreshold) {
            // Recovery time keçibmi?
            if ($lastFailure && now()->diffInSeconds($lastFailure) > $this->recoveryTime) {
                return self::STATE_HALF_OPEN;
            }
            return self::STATE_OPEN;
        }

        return self::STATE_CLOSED;
    }

    private function onSuccess(): void
    {
        Cache::forget($this->failureKey());
        Cache::forget($this->lastFailureKey());
    }

    private function onFailure(): void
    {
        Cache::increment($this->failureKey());
        Cache::put($this->lastFailureKey(), now(), 3600);
    }

    private function failureKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:failures";
    }

    private function lastFailureKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:last_failure";
    }
}
```

*return "circuit_breaker:{$this->serviceName}:last_failure"; üçün kod nümunəsi:*
```php
// İstifadə:
class PaymentServiceClient
{
    private CircuitBreaker $breaker;

    public function __construct()
    {
        $this->breaker = new CircuitBreaker('payment-service');
    }

    public function charge(int $orderId, float $amount): array
    {
        return $this->breaker->call(function () use ($orderId, $amount) {
            return Http::timeout(5)
                ->post("{$this->baseUrl}/api/charges", compact('orderId', 'amount'))
                ->throw()
                ->json();
        });
    }
}
```

---

## 9. Strangler Fig Pattern — Monolith-dən Microservice-ə

Martin Fowler-in adlandırdığı bu pattern: köhnə sistemi yavaş-yavaş əvəzlə.

```
Phase 1: Monolith bütün traffic-i alır
  Client → Monolith → All features

Phase 2: Proxy əlavə et
  Client → API Gateway/Proxy → Monolith (default)
                             ↘ New Service (selected routes)

Phase 3: Feature-ları köçür
  Client → API Gateway → New UserService   (/api/users)
                       → New OrderService  (/api/orders)
                       → Monolith          (köhnə features)

Phase 4: Monolith tamamilə əvəzləndi
  Client → API Gateway → Microservices
```

**Laravel-də Strangler Fig Proxy:**

```php
<?php
// app/Http/Controllers/StranglerProxyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StranglerProxyController extends Controller
{
    /**
     * Hansı route-ların yeni service-ə yönləndirildiyi.
     */
    private array $extractedRoutes = [
        'GET /api/users'      => 'http://user-service',
        'POST /api/users'     => 'http://user-service',
        'GET /api/users/{id}' => 'http://user-service',
    ];

    /**
     * Hər request gəldikdə: yeni service-ə yönləndir, yoxsa köhnə koda ver.
     */
    public function proxy(Request $request): \Illuminate\Http\Response
    {
        $routeKey = $request->method() . ' ' . $request->path();

        // Yeni service-ə köçürülmüş route-durmu?
        $newServiceUrl = $this->findNewService($routeKey);

        if ($newServiceUrl) {
            // Proxy: yeni service-ə yönləndir
            return $this->forwardToNewService($request, $newServiceUrl);
        }

        // Köhnə monolith kodu çağır
        return $this->handleWithMonolith($request);
    }

    private function forwardToNewService(Request $request, string $serviceUrl): \Illuminate\Http\Response
    {
        $response = Http::withHeaders($request->headers->all())
            ->withBody($request->getContent(), $request->header('Content-Type', 'application/json'))
            ->{strtolower($request->method())}(
                $serviceUrl . '/' . $request->path(),
                $request->query()
            );

        return response(
            $response->body(),
            $response->status(),
            $response->headers()
        );
    }

    private function findNewService(string $routeKey): ?string
    {
        foreach ($this->extractedRoutes as $pattern => $url) {
            if ($this->matchesPattern($routeKey, $pattern)) {
                return $url;
            }
        }
        return null;
    }

    private function matchesPattern(string $route, string $pattern): bool
    {
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        return (bool) preg_match('#^' . $regex . '$#', $route);
    }

    private function handleWithMonolith(Request $request): \Illuminate\Http\Response
    {
        // Köhnə Laravel controller-ə yönləndir
        return app()->handle($request);
    }
}
```

---

## 10. Conway's Law + Inverse Conway Maneuver

**Conway's Law:**
> "Organizations which design systems are constrained to produce designs which are copies of the communication structures of these organizations."

Yəni: əgər 3 team varsa, 3 komponentli sistem yaranacaq.

```
Team A (Frontend)    Team B (Backend)    Team C (Database)
      ↓                    ↓                    ↓
   Frontend             Backend              Database
   Layer                Layer                Layer

Monolithic layered architecture → Conway's Law nəticəsi
```

**Inverse Conway Maneuver:**
İstədiyin arxitektüraya uyğun team strukturu yarat:

```
Team A (Orders)    Team B (Payments)    Team C (Users)
      ↓                   ↓                   ↓
 Order Service      Payment Service      User Service

Microservices → əvvəlcə team strukturunu dəyiş
```

---

## 11. Testing Fərqi

```
              Modular Monolith          Microservices
              ──────────────────────    ──────────────────────
Unit Tests    Modulun özü mock-la       Service öz mock-la
Integration   DB ilə (eyni process)     WireMock/test doubles
Contract      Çox lazım deyil           Pact (consumer-driven)
E2E           Bir app başlat            Bütün services başlat
Mutation      Asandır                   Çox yavaş
```

**Contract Testing (Pact) Nümunəsi:**

```php
// Order service (consumer)
// Pact: Payment service-dən bu format gözlənilir

$builder = new InteractionBuilder($config);
$builder
    ->uponReceiving('a charge request')
    ->with(new ConsumerRequest(
        method: 'POST',
        path:   '/api/charges',
        body:   ['order_id' => 1, 'amount' => 100.00],
    ))
    ->willRespondWith(new ProviderResponse(
        status: 201,
        body:   ['payment_id' => '123', 'status' => 'charged'],
    ));

// Bu test: payment service-in bu contract-a uyğun davranmasını yoxlayır
```

---

## 12. Qərar Çərçivəsi: Nə Vaxt Hansını Seçmək

```
                        Modular Monolith seç    Microservices seç
                        ────────────────────    ─────────────────
Team ölçüsü             < 15 nəfər              > 15 nəfər, birdən çox team
Domain mürəkkəbliyi     Orta                    Yüksək, aydın bounded context
Scale tələbi            Uniform scale kifayət   Hər component fərqli scale
Release cadence         Birlikdə deploy OK      Müstəqil deploy lazımdır
Operational yetkinlik   DevOps/k8s bilgisi az   Güclü DevOps/platform team
Product yetkinliyi      MVP / Startup           Yetkin, stabil domain
Data isolation          Paylaşılan DB OK        Strict isolation lazım
Budget                  Məhduddur               Cloud/infra büdcəsi var
```

**Tövsiyə:**
- **Startup / MVP:** Modular Monolith ilə başla
- **Growth:** Bounded context-lər aydın olduqda extract et
- **Scale:** Yalnız ölçülə bilən bottleneck varsa microservice et

---

## 13. Operational Complexity

**Microservices tələbləri:**

```
Service Discovery:
  - Consul, Kubernetes Service
  - "payment-service nə IP-dadır?" sualına cavab

Load Balancing:
  - Nginx, HAProxy, Kubernetes Ingress
  - Sağlam instance-lara traffic yönləndir

Health Checks:
  GET /health → { "status": "ok", "db": "ok", "redis": "ok" }

Distributed Tracing:
  - Jaeger, Zipkin
  - Request A → Service B → Service C — hamısını trace et

Centralized Logging:
  - ELK Stack (Elasticsearch, Logstash, Kibana)
  - Bütün service log-larını bir yerdə gör

Configuration Management:
  - Consul KV, Kubernetes ConfigMap
  - Hər service öz config-ini bu-dan alır
```

*- Hər service öz config-ini bu-dan alır üçün kod nümunəsi:*
```php
// Laravel health check endpoint
Route::get('/health', function () {
    $checks = [
        'database' => $this->checkDatabase(),
        'redis'    => $this->checkRedis(),
        'queue'    => $this->checkQueue(),
    ];

    $healthy = collect($checks)->every(fn($v) => $v === 'ok');

    return response()->json(
        ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
        $healthy ? 200 : 503
    );
});
```

---

## 14. Service Mesh (Istio) və API Gateway

```
API Gateway (edge):
  Client → API Gateway → Services
  
  Funksiya:
  - Authentication / Authorization
  - Rate limiting
  - SSL termination
  - Request routing
  - Request/Response transformation

Service Mesh (sidecar proxy, məs. Istio):
  
  Service A → [Envoy Proxy] ──────→ [Envoy Proxy] → Service B
  
  Funksiya (application kod dəyişmədən):
  - mTLS (mutual TLS — service-to-service)
  - Circuit breaking
  - Retry logic
  - Distributed tracing
  - Traffic shaping (canary deploy)
```

---

## 15. Real-World Migration Story

**Scenario:** E-commerce monolith → modular monolith → microservices

```
Phase 1 (Year 1): Big Ball of Mud Monolith
  app/
    Controllers/ (400+ controllers)
    Models/      (100+ models)
    Services/    (məntiqsiz yerləşdirilmiş)

Phase 2 (Year 2): Modular Monolith
  → Domain-ları müəyyən et: Order, Payment, Catalog, User, Notification
  → Ayrı module qovluqları yarat
  → Modul boundaries enforce et (architecture tests)
  → Shared database — hələ paylaşılır

Phase 3 (Year 3): Extract hotspots
  → Catalog service: çox read, az write → CDN cache ilə ayrıl
  → Notification service: async, ayrı scale
  → Payment service: compliance tələb edir, PCI DSS

Phase 4 (Year 4+): Selective microservices
  → Core Order + User hələ monolith-dədir (dəyişiklik tez-tez)
  → Extracted: Catalog, Notification, Payment, Search

Lesson: Hər şeyi microservice etmə. Yalnız real tələb olduqda.
```

---

## 16. İntervyu Sualları

**Sual 1:** Modular monolith ilə microservices arasındakı əsas fərq nədir?

**Cavab:** Modular monolith tək deployment unit-dir — modullar in-process kommunikasiya edir, ACID transaction-lar işləyir. Microservices-də hər service ayrı deploy edilir, network üzərindən kommunikasiya edir, shared memory yoxdur. Monolith developer experience-ı asanlaşdırır, microservices isə independent scaling və deployment verir.

---

**Sual 2:** Domain Event ilə Integration Event fərqi nədir?

**Cavab:** Domain Event bir bounded context-in daxilindədir, in-process ola bilər. Integration Event bounded context-lər arasında xəbərləşmədir, message broker (RabbitMQ, Kafka) vasitəsilə async göndərilir. Integration Event public contract-dır, versioning tələb edir.

---

**Sual 3:** Saga pattern nədir, choreography ilə orchestration fərqi?

**Cavab:** Saga — distributed transaction-ı local transaction-ların ardıcıllığı ilə əvəzləyən pattern-dir. Choreography-da hər service event dinləyir, öz compensation-ını özü edir — mərkəzi nöqtə yoxdur. Orchestration-da mərkəzi orchestrator hər addımı idarə edir, uğursuzluqda compensation çağırır.

---

**Sual 4:** Circuit Breaker pattern nə üçün lazımdır?

**Cavab:** Bir service down olduqda ona davamlı istəklər göndərmək timeout-lara yol açır, çoxlu thread-lər bloklanır, cascading failure başlayır. Circuit breaker failure threshold keçdikdə istəkləri bloklamağa başlayır. Recovery time-dan sonra half-open state-ə keçir, test istəyi göndərir. Uğurlu olsa closed-a qayıdır.

---

**Sual 5:** Database per service pattern-in join problemini necə həll edərsiniz?

**Cavab:** 1) Application-level join — hər service-dən ayrı sorğu, PHP-də birləşdir; 2) API Composition — aggregate service çox service-dən data toplayır; 3) CQRS + Read model — projection yaradıb denormalized view saxla; 4) Event-driven sync — service B, service A-nın data-nı event vasitəsilə öz DB-sinə kopyalayır.

---

**Sual 6:** Conway's Law nədir?

**Cavab:** Sistemin arxitekturası onu yaradan organizasiyanın kommunikasiya strukturunu əks etdirir. 3 team varsa, 3 komponentli sistem yaranır. Inverse Conway Maneuver: istədiyin arxitekturaya uyğun team strukturu yarat — microservices üçün əvvəlcə team boundaries-i düzəlt.

---

**Sual 7:** Strangler Fig pattern nədir?

**Cavab:** Köhnə sistemi bir dəfəlik deyil, yavaş-yavaş əvəzləmək üçün pattern. Proxy/API Gateway əlavə edilir. Yeni feature-lar yeni service-ə yazılır. Köhnə feature-lar köçürüldükcə monolith kiçilir. Sonda monolith tamamilə əvəzlənir. Risk azdır çünki rollback asandır.

---

**Sual 8:** Microservices nə vaxt seçilməməlidir?

**Cavab:** Startup/MVP mərhələsindədirsinizsə, domain hələ aydın deyilsə, team kiçikdirsə (< 15 nəfər), operational yetkinlik yoxdursa (k8s, service mesh, distributed tracing) — microservices seçmə. Modular monolith ilə başla, domain aydınlaşınca extract et.

---

**Sual 9:** Eventual consistency nədir, problem yaradırmı?

**Cavab:** Distributed sistemlərdə bütün node-lar eyni vaxtda eyni data-nı görməyə bilər, lakin müəyyən vaxtdan sonra hamısı uyğunlaşır. Problem: payment charged, inventory reserved olana qədər order "pending" görünür. Həll: UI-da "processing" state göstər, compensation logic yaz, idempotency key istifadə et.

---

**Sual 10:** gRPC-nin REST üzərindəki üstünlükləri nələrdir?

**Cavab:** Protobuf binary protokol HTTP/2 üzərindən işləyir — JSON-dan 3-5x kiçik, daha sürətli. Strongly typed schema — runtime deyil, compile time xətaları. Code generation — server/client stub-lar avtomatik yaranır. Bidirectional streaming dəstəyi. Dezavantaj: browser birbaşa dəstəkləmir, debug çətindir.

---

**Sual 11:** Microservices-ə keçiddə nə vaxt hazır olduğunuzu anlayarsınız?

**Cavab:** Bounded context-lər aydın müəyyən edilib; deployment çox tez-tez olur və konflikt yaranır; müxtəlif komponentlər tamamilə fərqli scale tələb edir; team-lər bir-birinə bloklanır; DevOps infrastruktur (k8s, CI/CD, monitoring) mövcuddur.

---

**Sual 12:** Microservices-in ən böyük çətinliyi nədir?

**Cavab:** Distributed transactions — ACID-i itirirsən. Network failures — timeout, partial failure. Distributed tracing — debugging çətindir. Data consistency — eventual consistency ilə yaşamaq lazımdır. Operational overhead — hər service-in health check, logging, deploy pipeline-ı. Test mürəkkəbliyi — contract tests, E2E test-lər çox service-ə ehtiyac duyur.

---

## Anti-patternlər

**1. Distributed monolith yaratmaq**
Microservices-ə keçid adı altında servisləri bir-birinə synchronous HTTP ilə sıx bağlamaq — hər servis digərini bilir, birinin uğursuzluğu hamını çökdürür, deployment ardıcıllığı tələb edir. Gerçək microservices loose coupling tələb edir: async messaging, event-driven kommunikasiya.

**2. Komanda yetkinliyi olmadan microservices-ə keçmək**
DevOps infrastruktur, CI/CD pipeline, distributed tracing, container orchestration hazır olmadan servislərə bölmək — operational overhead komandanı əzir, feature delivery yavaşlayır. Əvvəl modular monolith qur, sonra lazım olduqda xidmət ayır.

**3. Modüller arasında birbaşa DB paylaşımı**
`Order` modulu `User` cədvəlinə birbaşa `JOIN` ilə müraciət etmək — modüllər DB səviyyəsindən sıx bağlanır, bir modülün sxem dəyişikliyi digərini sındırır. Hər modülün öz DB sxemi ya da table prefix-i olsun, modüllər arası kommunikasiya interface ya da event ilə həll edilsin.

**4. Bounded context-i düzgün müəyyən etmədən servislər ayırmaq**
Anemic domain-lər yaratmaq — hər xidmət o qədər kiçikdir ki, real iş üçün çox sayda servislə danışmaq lazım gəlir, network hop-ları artır. DDD bounded context-lərini aydın müəyyən et, servis granularlığını iş domeninə görə seç.

**5. Microservices-də distributed transaction əvəzinə 2PC işlətmək**
Fərqli servislərin DB-lərini spanning edən two-phase commit istifadə etmək — servislər bir-birinə lock-la bağlanır, coordinator çöksə bütün sistem donur, scalability aradan qalxır. Saga pattern, Outbox pattern kimi eventual consistency yanaşmalarını tətbiq et.

**6. Hər servis üçün ayrı texnoloji stack seçmək**
Hər yeni servisə fərqli proqramlaşdırma dili, fərqli DB, fərqli framework seçmək — operational knowledge fragmentasiyası baş verir, komanda hər servisin texnoloji detallarını öyrənməli olur. Standartlaşdırılmış "golden path" müəyyən et, fərqliliyi yalnız gerçek texniki ehtiyac olduqda tətbiq et.
