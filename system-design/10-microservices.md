# Microservices (Middle)

## İcmal

Microservices arxitekturası böyük bir tətbiqi kiçik, müstəqil xidmətlərə (services) ayıran
dizayn yanaşmasıdır. Hər bir service öz business logic-ini, verilənlər bazasını və deployment
prosesini müstəqil idarə edir. Bu, monolit arxitekturanın əksidir - burada bütün funksionallıq
tək bir tətbiqdə cəmlənir.

Sadə dillə: monolit bir fabrik kimidir - hər şey bir binada. Microservices isə
ixtisaslaşmış emalatxanalar şəbəkəsidir - hər biri öz işini görür.

```
Monolit:                          Microservices:
┌──────────────────────┐         ┌─────────┐ ┌─────────┐ ┌─────────┐
│  User Module         │         │  User   │ │  Order  │ │ Payment │
│  Order Module        │         │ Service │ │ Service │ │ Service │
│  Payment Module      │   →     │  [DB1]  │ │  [DB2]  │ │  [DB3]  │
│  Notification Module │         └─────────┘ └─────────┘ └─────────┘
│  [Shared Database]   │         ┌─────────┐ ┌─────────┐
└──────────────────────┘         │  Notif  │ │ Catalog │
                                 │ Service │ │ Service │
                                 │  [DB4]  │ │  [DB5]  │
                                 └─────────┘ └─────────┘
```


## Niyə Vacibdir

Monolith böyüdükcə deploy çətinləşir, yavaş build dövrü yaranır, team autonomiyası itirilir. Mikroservislər müstəqil deploy, technology heterogeneity, fault isolation imkanı verir — lakin distributed systems kompleksliyini əlavə edir. Bu trade-off-u bilmədən arxitektura qərarı vermək olmaz.

## Əsas Anlayışlar

### Monolith vs Microservices

**Monolit Üstünlükləri:**
- Sadə development və deployment
- Cross-cutting concerns asandır (logging, auth)
- Performans - in-process calls daha sürətlidir
- Data consistency - tək database, ACID transactions
- Debugging və testing asandır

**Monolit Çatışmazlıqları:**
- Böyüdükcə complexity artır
- Tək bir bug bütün sistemi çökdürə bilər
- Scaling çətindir (bütün tətbiqi scale etməlisən)
- Technology lock-in - bir stack ilə bağlısan
- Deployment riski yüksəkdir

**Microservices Üstünlükləri:**
- Müstəqil deployment və scaling
- Technology diversity (hər service fərqli dildə ola bilər)
- Fault isolation - bir service çöksə digərləri işləyir
- Team autonomy - kiçik komandalar müstəqil işləyir
- Daha yaxşı scalability

**Microservices Çatışmazlıqları:**
- Distributed system complexity
- Network latency və reliability
- Data consistency çətinliyi
- Operational overhead (monitoring, deployment)
- Service discovery, load balancing lazımdır

### Service Boundaries (Bounded Context)

Domain-Driven Design (DDD) prinsipləri ilə service sərhədlərini təyin edirik:

```
E-Commerce Domain:

┌─────────────────────────────────────────┐
│              Bounded Contexts           │
│                                         │
│  ┌──────────┐  ┌──────────┐           │
│  │  Catalog  │  │  Order   │           │
│  │ Context   │  │ Context  │           │
│  │           │  │          │           │
│  │ - Product │  │ - Order  │           │
│  │ - Category│  │ - LineItem│          │
│  │ - Price   │  │ - Status │           │
│  └──────────┘  └──────────┘           │
│                                         │
│  ┌──────────┐  ┌──────────┐           │
│  │ Payment   │  │ Shipping │           │
│  │ Context   │  │ Context  │           │
│  │           │  │          │           │
│  │ - Payment │  │ - Shipment│          │
│  │ - Refund  │  │ - Tracking│          │
│  │ - Invoice │  │ - Address │          │
│  └──────────┘  └──────────┘           │
└─────────────────────────────────────────┘
```

### Communication Patterns

**Synchronous (Sync) Communication:**
- REST API, gRPC
- Service-to-service direct call
- Real-time cavab lazım olanda

```php
// Order Service -> Payment Service (sync REST call)
class PaymentClient
{
    public function __construct(
        private HttpClient $http,
        private string $paymentServiceUrl
    ) {}

    public function charge(string $orderId, float $amount): PaymentResult
    {
        $response = $this->http->post("{$this->paymentServiceUrl}/api/payments", [
            'json' => [
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => 'USD',
            ],
            'timeout' => 5,
        ]);

        return PaymentResult::fromResponse($response);
    }
}
```

**Asynchronous (Async) Communication:**
- Message queues (RabbitMQ, Kafka, SQS)
- Event-driven, fire-and-forget
- Loose coupling təmin edir

```php
// Order Service emits event, Payment Service consumes it
// Publisher (Order Service)
class OrderCreatedPublisher
{
    public function publish(Order $order): void
    {
        $message = [
            'event' => 'order.created',
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'total' => $order->total,
            'items' => $order->items->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];

        RabbitMQ::publish('orders.exchange', 'order.created', $message);
    }
}

// Consumer (Payment Service)
class OrderCreatedConsumer
{
    public function handle(array $message): void
    {
        $payment = Payment::create([
            'order_id' => $message['order_id'],
            'amount' => $message['total'],
            'status' => 'pending',
        ]);

        $this->paymentGateway->charge($payment);
    }
}
```

### Saga Pattern

Distributed transactions üçün istifadə olunur. Hər service öz local transaction-ını
icra edir, uğursuz olarsa compensating transactions çağırılır.

**Choreography-based Saga:**
```
Order Created → Payment Charged → Inventory Reserved → Shipping Started
     ↑               ↑                  ↑                    ↑
     │          Payment Failed      Out of Stock        Shipping Failed
     │               │                  │                    │
     ↓               ↓                  ↓                    ↓
Cancel Order    Refund Payment    Release Inventory    Cancel Shipping
```

**Orchestration-based Saga:**
```php
class OrderSagaOrchestrator
{
    private array $completedSteps = [];

    public function execute(Order $order): void
    {
        try {
            // Step 1: Reserve inventory
            $this->inventoryService->reserve($order->items);
            $this->completedSteps[] = 'inventory_reserved';

            // Step 2: Charge payment
            $this->paymentService->charge($order->id, $order->total);
            $this->completedSteps[] = 'payment_charged';

            // Step 3: Create shipment
            $this->shippingService->createShipment($order);
            $this->completedSteps[] = 'shipment_created';

            $order->update(['status' => 'confirmed']);
        } catch (\Exception $e) {
            $this->compensate($order);
            throw $e;
        }
    }

    private function compensate(Order $order): void
    {
        foreach (array_reverse($this->completedSteps) as $step) {
            match ($step) {
                'shipment_created' => $this->shippingService->cancel($order->id),
                'payment_charged' => $this->paymentService->refund($order->id),
                'inventory_reserved' => $this->inventoryService->release($order->items),
            };
        }
    }
}
```

### Service Mesh

Service-to-service communication-u idarə edən infrastructure layer:

```
┌─────────────────────────────────────────────┐
│                Service Mesh                  │
│                                              │
│  ┌─────────┐          ┌─────────┐          │
│  │ Service A│          │ Service B│          │
│  │         │          │         │          │
│  │ ┌──────┐│  mTLS    │ ┌──────┐│          │
│  │ │Sidecar││ ──────── │ │Sidecar││          │
│  │ │Proxy  ││          │ │Proxy  ││          │
│  │ └──────┘│          │ └──────┘│          │
│  └─────────┘          └─────────┘          │
│                                              │
│  Control Plane (Istio/Linkerd):             │
│  - Traffic management                        │
│  - mTLS encryption                          │
│  - Observability                            │
│  - Circuit breaking                         │
└─────────────────────────────────────────────┘
```

### Data Management

**Database per Service Pattern:**
```
┌──────────┐    ┌──────────┐    ┌──────────┐
│  User    │    │  Order   │    │ Payment  │
│ Service  │    │ Service  │    │ Service  │
└────┬─────┘    └────┬─────┘    └────┬─────┘
     │               │               │
┌────┴─────┐    ┌────┴─────┐    ┌────┴─────┐
│ PostgreSQL│    │  MySQL   │    │  MongoDB │
│ (Users)  │    │ (Orders) │    │(Payments)│
└──────────┘    └──────────┘    └──────────┘
```

## Arxitektura

### Typical Microservices Architecture

```
                    ┌──────────────┐
                    │   Clients    │
                    │ (Web/Mobile) │
                    └──────┬───────┘
                           │
                    ┌──────┴───────┐
                    │  API Gateway │
                    │  (Kong/Nginx)│
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
       ┌──────┴──┐  ┌─────┴───┐  ┌────┴─────┐
       │  Auth   │  │  Order  │  │  Product │
       │ Service │  │ Service │  │  Service │
       └────┬────┘  └────┬────┘  └────┬─────┘
            │            │            │
       ┌────┴────┐  ┌────┴────┐  ┌───┴──────┐
       │  Redis  │  │  MySQL  │  │ Postgres │
       └─────────┘  └─────────┘  └──────────┘
                           │
                    ┌──────┴───────┐
                    │ Message Bus  │
                    │  (RabbitMQ)  │
                    └──────────────┘
```

## Nümunələr

### Lumen Microservice Nümunəsi

```php
// routes/web.php (Lumen - Product Service)
$router->group(['prefix' => 'api/products'], function () use ($router) {
    $router->get('/', 'ProductController@index');
    $router->get('/{id}', 'ProductController@show');
    $router->post('/', 'ProductController@store');
    $router->put('/{id}', 'ProductController@update');
    $router->delete('/{id}', 'ProductController@destroy');
});

// app/Http/Controllers/ProductController.php
class ProductController extends Controller
{
    public function __construct(
        private ProductRepository $products
    ) {}

    public function show(string $id): JsonResponse
    {
        $product = $this->products->findOrFail($id);

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }
}
```

### Inter-Service Communication with Laravel HTTP Client

```php
// Service Client Base
abstract class ServiceClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $serviceToken
    ) {}

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->serviceToken)
            ->timeout(5)
            ->retry(3, 100, function ($exception) {
                return $exception instanceof ConnectionException;
            });
    }
}

// User Service Client (used by Order Service)
class UserServiceClient extends ServiceClient
{
    public function getUser(int $userId): array
    {
        $response = $this->request()->get("/api/users/{$userId}");

        if ($response->failed()) {
            throw new ServiceUnavailableException('User service unavailable');
        }

        return $response->json('data');
    }
}

// Registration in ServiceProvider
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserServiceClient::class, function () {
            return new UserServiceClient(
                baseUrl: config('services.user.url'),
                serviceToken: config('services.user.token')
            );
        });
    }
}
```

### API Gateway Pattern with Laravel

```php
// routes/api.php (Gateway Service)
Route::prefix('v1')->group(function () {
    Route::any('users/{path?}', [GatewayController::class, 'proxyToUserService'])
        ->where('path', '.*');
    Route::any('orders/{path?}', [GatewayController::class, 'proxyToOrderService'])
        ->where('path', '.*');
    Route::any('products/{path?}', [GatewayController::class, 'proxyToProductService'])
        ->where('path', '.*');
});

class GatewayController extends Controller
{
    private array $serviceMap = [
        'users' => 'USER_SERVICE_URL',
        'orders' => 'ORDER_SERVICE_URL',
        'products' => 'PRODUCT_SERVICE_URL',
    ];

    public function __call(string $method, array $args)
    {
        $service = str_replace('proxyTo', '', str_replace('Service', '', $method));
        $service = strtolower($service);

        $baseUrl = config("services.{$service}.url");
        $path = request()->path();

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(request()->headers->all())
            ->send(request()->method(), $path, [
                'query' => request()->query(),
                'json' => request()->json()->all(),
            ]);

        return response($response->body(), $response->status())
            ->withHeaders($response->headers());
    }
}
```

### Docker Compose for Microservices

```yaml
# docker-compose.yml
version: '3.8'
services:
  api-gateway:
    build: ./gateway
    ports:
      - "8080:80"
    depends_on:
      - user-service
      - order-service
      - product-service

  user-service:
    build: ./services/user
    environment:
      DB_HOST: user-db
    depends_on:
      - user-db
      - rabbitmq

  order-service:
    build: ./services/order
    environment:
      DB_HOST: order-db
      USER_SERVICE_URL: http://user-service
    depends_on:
      - order-db
      - rabbitmq

  product-service:
    build: ./services/product
    environment:
      DB_HOST: product-db
    depends_on:
      - product-db

  user-db:
    image: postgres:15
    volumes:
      - user-data:/var/lib/postgresql/data

  order-db:
    image: mysql:8
    volumes:
      - order-data:/var/lib/mysql

  product-db:
    image: postgres:15
    volumes:
      - product-data:/var/lib/postgresql/data

  rabbitmq:
    image: rabbitmq:3-management
    ports:
      - "15672:15672"

volumes:
  user-data:
  order-data:
  product-data:
```

## Real-World Nümunələr

1. **Netflix** - 700+ microservices, Zuul API gateway, Eureka service discovery
2. **Amazon** - İlk microservices adopter-lərdən biri, "two-pizza teams" konsepti
3. **Uber** - Domain-oriented microservices architecture (DOMA)
4. **Spotify** - Squad model, hər squad öz microservice-lərini idarə edir

## Praktik Tapşırıqlar

**S1: Monolith-dən microservices-ə nə vaxt keçmək lazımdır?**
C: Əgər team böyüyürsə (10+ developer), deployment-lər risk yaradırsa, müxtəlif
hissələrin fərqli scaling tələbləri varsa, və development speed azalırsa. Strangler
Fig pattern ilə tədricən keçid etmək ən yaxşı yanaşmadır.

**S2: Saga pattern nədir və nə vaxt istifadə olunur?**
C: Distributed transactions üçün pattern-dir. Hər service öz local transaction-ını
icra edir. Uğursuz olarsa compensating transactions ilə rollback edilir.
Choreography (event-based) və Orchestration (coordinator-based) növləri var.

**S3: Service mesh nədir?**
C: Service-to-service communication-u idarə edən dedicated infrastructure layer-dir.
Sidecar proxy vasitəsilə mTLS, traffic management, observability, circuit breaking
təmin edir. Istio və Linkerd populyar implementasiyalardır.

**S4: Microservices-də data consistency necə təmin olunur?**
C: Eventual consistency yanaşması ilə. Saga pattern, event-driven architecture,
outbox pattern istifadə olunur. Strong consistency lazımdırsa, two-phase commit
(2PC) istifadə edilə bilər, amma performansa mənfi təsir edir.

**S5: Strangler Fig pattern nədir?**
C: Monolit-dən microservices-ə tədricən keçid pattern-idir. Yeni funksionallıqları
microservice kimi yaradırsınız, köhnə funksionallıqları tədricən köçürürsünüz.
Monolit tədricən "boğulur" və yox olur.

**S6: Microservices-də testing necə aparılır?**
C: Unit tests (hər service daxili), integration tests (service + database),
contract tests (services arası API razılaşması), end-to-end tests (bütün flow).
Consumer-driven contract testing (Pact) populyardır.

**S7: Database per service pattern-in çatışmazlıqları nədir?**
C: Cross-service queries çətindir, data duplication ola bilər, distributed
transactions lazım olur. JOIN əməliyyatları mümkün deyil - API composition
və ya CQRS pattern istifadə etmək lazımdır.

## Praktik Baxış

1. **Start with Monolith** - "Monolith First" yanaşması, lazım olanda decompose edin
2. **Domain-Driven Design** - Bounded Context ilə service sərhədlərini təyin edin
3. **API Versioning** - Breaking changes zamanı backward compatibility saxlayın
4. **Circuit Breaker** - Cascading failures-ın qarşısını alın
5. **Centralized Logging** - Bütün service-lərdən log-ları bir yerdə toplayın
6. **Correlation ID** - Request-ləri service-lər arasında track edin
7. **Health Checks** - Hər service /health endpoint təmin etsin
8. **Async Communication** - Mümkün olanda message queue istifadə edin
9. **Infrastructure as Code** - Terraform, Docker Compose ilə idarə edin
10. **Feature Flags** - Yeni feature-ləri tədricən açın


## Əlaqəli Mövzular

- [Distributed Systems](25-distributed-systems.md) — mikroservislərin əsası
- [Event-Driven](11-event-driven-architecture.md) — servislərarası async əlaqə
- [Service Discovery](29-service-discovery.md) — servislərin bir-birini tapması
- [Service Mesh](47-service-mesh.md) — cross-cutting concern-lərin infra-ya köçürülməsi
- [Distributed Transactions](45-distributed-transactions-saga.md) — mikroservislər arası əməliyyat
