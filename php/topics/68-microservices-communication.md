# Microservices Communication — gRPC, REST, Events

## Mündəricat
1. [Sync vs Async Communication](#sync-vs-async-communication)
2. [REST](#rest)
3. [gRPC](#grpc)
4. [Event-driven Communication](#event-driven-communication)
5. [Timeout Hierarchy](#timeout-hierarchy)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [Nə Zaman Hansını Seçmək?](#nə-zaman-hansını-seçmək)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Sync vs Async Communication

```
// Bu kod sinxron (REST/gRPC) və asinxron (event-driven) kommunikasiya növlərini müqayisə edir
Synchronous (Müstəqim):
  Caller → Service → Cavab gözlə → Cavab al
  
  ✅ Sadə, anlıqlı cavab
  ✅ Error handling birbaşa
  ❌ Temporal coupling (hər ikisi online olmalıdır)
  ❌ Cascading failure riski
  Nümunə: REST, gRPC

Asynchronous (Event-driven):
  Caller → Mesajı queue-ya at → Davam et
              Queue → Consumer → İşlə
  
  ✅ Loose coupling
  ✅ Resilient (consumer offline olsa mesaj gözləyir)
  ✅ Load leveling (spike-ları buffer et)
  ❌ Eventual consistency
  ❌ Complex error handling
  Nümunə: RabbitMQ, Kafka events
```

---

## REST

```
// Bu kod REST API-nin üstünlüklərini, məhdudiyyətlərini və HTTP method konvensiyalarını göstərir
HTTP/1.1 + JSON:

Üstünlüklər:
  ✅ Universal (browser-dən çağırıla bilər)
  ✅ Human-readable (debug asan)
  ✅ Caching (GET request-lər)
  ✅ Tooling (Postman, curl, browser)
  ✅ Versioning (URL, header)

Məhdudiyyətlər:
  ❌ JSON parsing overhead
  ❌ HTTP/1.1: hər request yeni TCP connection
  ❌ Weak typing (schema yoxdur, default)
  ❌ Real-time üçün uyğun deyil

REST best practices:
  GET    /orders       → list
  GET    /orders/123   → get one
  POST   /orders       → create
  PUT    /orders/123   → full update
  PATCH  /orders/123   → partial update
  DELETE /orders/123   → delete
  
  Status codes:
    200 OK, 201 Created, 204 No Content
    400 Bad Request, 401 Unauthorized, 403 Forbidden
    404 Not Found, 409 Conflict, 422 Unprocessable
    429 Too Many Requests, 500 Internal Error, 503 Unavailable
```

---

## gRPC

```
// Bu kod gRPC-nin protobuf, HTTP/2 üstünlüklərini və streaming növlərini izah edir
Google Remote Procedure Call:
  Protocol Buffers (protobuf) — binary serialization
  HTTP/2 — multiplexing, header compression
  Strong typing — .proto schema

protobuf vs JSON:
  JSON:    {"orderId": 123, "status": "confirmed", "total": 1500}
  protobuf: binary, ~70% kiçik, ~5x sürətli parse

gRPC stream növləri:
  Unary:           Client 1 req → Server 1 resp
  Server Streaming: Client 1 req → Server N resp (real-time)
  Client Streaming: Client N req → Server 1 resp (upload)
  Bidirectional:   N req ↔ N resp (chat, live data)

HTTP/2 üstünlükləri:
  Multiplexing: 1 TCP connection-da paralel sorğular
  Header compression
  Server push
  Binary protocol
```

*Header compression üçün kod nümunəsi:*
```protobuf
// Bu kod gRPC servis definisiyasını OrderService üçün protobuf formatında göstərir
// order_service.proto
syntax = "proto3";
package order;

service OrderService {
    rpc GetOrder(GetOrderRequest) returns (Order);
    rpc CreateOrder(CreateOrderRequest) returns (Order);
    rpc StreamOrderUpdates(GetOrderRequest) returns (stream OrderUpdate);
}

message GetOrderRequest {
    string order_id = 1;
}

message Order {
    string id       = 1;
    string status   = 2;
    int64  total    = 3;
    string customer_id = 4;
    repeated OrderItem items = 5;
}

message OrderItem {
    string product_id = 1;
    int32  quantity   = 2;
    int64  price      = 3;
}

message CreateOrderRequest {
    string customer_id  = 1;
    repeated OrderItem items = 2;
}

message OrderUpdate {
    string order_id = 1;
    string status   = 2;
    int64  timestamp = 3;
}
```

---

## Event-driven Communication

```
// Bu kod choreography (event-driven) və orchestration (command-driven) Saga pattern-lərini müqayisə edir
Choreography (Event-driven):
  Servislər event-lərə reaksiya verir, mərkəz yoxdur

Order Service → "OrderPlaced" event
  ├── Inventory Service dinləyir → stok rezerv et
  ├── Email Service dinləyir → email göndər
  └── Analytics Service dinləyir → stat yenilə

✅ Loose coupling
✅ Easy to add new consumer
❌ Flow-u izləmək çətin
❌ Eventual consistency

Orchestration (Command-driven):
  Mərkəzi orchestrator hər addımı komanda ilə çağırır

Saga Orchestrator:
  → "ReserveInventory" command → Inventory Service
  → "ChargePayment" command → Payment Service
  → "ScheduleDelivery" command → Delivery Service

✅ Flow aydındır
✅ Debug asan
❌ Orchestrator bottleneck
❌ Services depend on orchestrator
```

---

## Timeout Hierarchy

```
// Bu kod cascading failure-ın qarşısını almaq üçün timeout hierarchy-nin düzgün qurulmasını göstərir
Hər tier üçün timeout müəyyən et:

Client (Browser/Mobile)
  timeout: 30s
       │
API Gateway
  timeout: 25s (client-dən az!)
       │
Service A
  timeout: 20s
       │
Service B (downstream call)
  timeout: 5s
       │
Database
  timeout: 3s

Qayda: Her tier downstream-dən az timeout olmalıdır!
  Client 30s > Gateway 25s > ServiceA 20s > ServiceB 5s > DB 3s

Niyə vacibdir?
  ServiceA timeout = 20s
  ServiceB timeout = 20s (səhv!)
  
  ServiceB 20s gözləyir → ServiceA 20s gözləyir → Gateway gözləyir
  → Thread pool-lar tükənir → Cascading failure!
  
  ServiceB timeout = 5s:
  5s sonra ServiceA ServiceB-dən xəta alır
  Circuit breaker aktiv ola bilər
  Resources azad olur
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod PHP-də gRPC client, timeout-lu REST client və paralel HTTP sorğularını göstərir
// gRPC PHP client (grpc/grpc + protobuf)

class OrderGrpcClient
{
    private OrderServiceClient $client;
    
    public function __construct()
    {
        $this->client = new OrderServiceClient(
            'order-service:50051',
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        );
    }
    
    public function getOrder(string $orderId): ?Order
    {
        $request = new GetOrderRequest();
        $request->setOrderId($orderId);
        
        [$response, $status] = $this->client->GetOrder($request)->wait();
        
        if ($status->code !== \Grpc\STATUS_OK) {
            throw new GrpcException($status->details, $status->code);
        }
        
        return $response;
    }
    
    public function streamOrderUpdates(string $orderId): \Generator
    {
        $request = new GetOrderRequest();
        $request->setOrderId($orderId);
        
        $stream = $this->client->StreamOrderUpdates($request);
        
        foreach ($stream->responses() as $update) {
            yield $update;
        }
    }
}

// REST client with timeout hierarchy
class PaymentServiceClient
{
    public function charge(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'X-Trace-Id' => request()->header('X-Trace-Id'),
                'Authorization' => 'Bearer ' . config('services.payment.token'),
            ])
            ->timeout(5)       // Service-level timeout
            ->retry(2, 100)    // 2 retry, 100ms delay
            ->post(config('services.payment.url') . '/charges', $data);
            
            if ($response->status() === 422) {
                throw new PaymentValidationException($response->json('message'));
            }
            
            if ($response->serverError()) {
                throw new PaymentServiceException('Payment service xəta');
            }
            
            return $response->json();
            
        } catch (ConnectionException $e) {
            throw new PaymentServiceUnavailableException('Payment service mövcud deyil');
        }
    }
}

// Async parallel calls (HTTP/2 + concurrent requests)
class DashboardAggregator
{
    public function aggregate(int $userId): array
    {
        // Laravel HTTP client pool — paralel sorğular
        $responses = Http::pool(fn ($pool) => [
            $pool->as('orders')->get("/api/orders?user=$userId"),
            $pool->as('profile')->get("/api/users/$userId"),
            $pool->as('notifications')->get("/api/notifications?user=$userId"),
        ]);
        
        return [
            'orders'        => $responses['orders']->json(),
            'profile'       => $responses['profile']->json(),
            'notifications' => $responses['notifications']->json(),
        ];
    }
}
```

---

## Nə Zaman Hansını Seçmək?

```
// Bu kod REST, gRPC, event-driven və GraphQL kommunikasiya seçimlərinin nə zaman istifadə ediləcəyini göstərir
REST:
  → Public API (external clients)
  → Browser-dən çağırılır
  → Caching lazımdır
  → Simple CRUD

gRPC:
  → Internal service-to-service
  → High throughput (binary, az bandwidth)
  → Strong typing lazımdır
  → Streaming (real-time updates)
  → Polyglot (müxtəlif dillər)

Event-driven (Async):
  → Servislərin loose coupling-i lazımdır
  → Consumer offline ola bilər
  → Fire-and-forget (email, notification)
  → Long-running process (order processing pipeline)
  → Audit log, analytics
  
GraphQL:
  → Flexible queries (mobile, BFF)
  → Hər client fərqli data lazımdır
  → API aggregation
```

---

## İntervyu Sualları

**1. gRPC-nin REST üzərindəki üstünlükləri nələrdir?**
Binary protobuf: JSON-dan ~70% kiçik, ~5x sürətli parse. HTTP/2: multiplexing (1 connection-da paralel sorğular), header compression. Strong typing: .proto contract, compile-time yoxlama. Streaming: server/client/bidirectional. Internal service communication üçün ideal.

**2. Choreography vs Orchestration nə zaman seçilir?**
Choreography: sadə flow, loose coupling vacibdir, yeni consumer asanlıqla əlavə olunur. Orchestration: mürəkkəb iş axını, debug vacibdir, compensation (Saga) lazımdır, flow centralized olmalıdır.

**3. Timeout hierarchy niyə vacibdir?**
Downstream timeout həmişə upstream-dən az olmalıdır. Əks halda: downstream 30s gözləyir, upstream da 30s gözləyir → thread pool-lar tükənir → cascading failure. Hər tier az timeout: fast fail, resources tez azad olur.

**4. gRPC streaming nə zaman istifadə edilir?**
Server streaming: real-time order status updates, live prices, log streaming. Client streaming: large file upload in chunks. Bidirectional: chat, collaborative editing, real-time dashboards. WebSocket-in gRPC alternativi (internal services üçün).

**5. Async communication-ın əsas tradeoff-ları nədir?**
Üstünlük: temporal decoupling (consumer offline ola bilər), load leveling, loose coupling. Çatışmazlıq: eventual consistency, complex error handling (compensation), harder to debug (distributed trace lazımdır), message ordering problemi.

**6. Circuit Breaker pattern nədir?**
Dövr qoruyucusu — xətalı servisə davamlı sorğu atmağı önləyir. 3 vəziyyət: CLOSED (normal), OPEN (threshold keçilib, dərhal fail), HALF-OPEN (test sorğusu göndər, sağlam olubsa CLOSED-a qayıt). PHP: `ezimkungfu/circuit-breaker` ya da custom implementation. Cascading failure-ın əsas qarşısını alan pattern.

**7. Saga pattern nədir?**
Distributed transaction-lar üçün həll. Hər addım lokal transaction, uğursuzluqda compensating transaction çalışır. Choreography Saga: hər servis event atır, növbəti servis dinləyir. Orchestration Saga: mərkəzi Saga orchestrator hər addımı command ilə çağırır. 2PC (two-phase commit) alternatividir — daha az coupling, eventual consistency.

**8. Idempotency key nədir, niyə lazımdır?**
Retry zamanı əməliyyatın bir dəfə icra edilməsini zəmanətləyir. Client tərəfindən unikal UUID göndərilir (`Idempotency-Key` header). Server bu key-i DB-də saxlayır — eyni key-li request gəlsə əvvəlki cavabı qaytarır, yenidən icra etmir. Ödəniş, sifariş kimi kritik əməliyyatlar üçün vacib.

---

## Anti-patternlər

**1. Bütün servis-to-servis kommunikasiyanı sinxron HTTP ilə etmək**
Order servisi ödəniş, inventar, bildiriş servislərinə ardıcıl HTTP çağırışı edir — biri yavaşlasa hamı yavaşlayır, biri down olsa order uğursuz olur, latency toplanır. Temporal decoupling lazım olan yerlərdə async mesajlaşma istifadə edin; yalnız ani cavab tələb olunanda sinxron HTTP seçin.

**2. Downstream timeout-u upstream-dən böyük qoymaq**
API Gateway 30s timeout, backend servis də 30s timeout — backend cavab verməsə gateway 30s gözləyir, thread pool tükənir. Timeout hierarchy qurun: hər downstream timeout upstream-dən kiçik olsun; fast fail ilə resurslar tez azad edilsin.

**3. Retry ilə idempotency-ni birlikdə düşünməmək**
Network xətasında HTTP POST-u retry etmək — server request-i aldı amma cavab göndərə bilmədisə retry ikinci ödəniş yaradır. POST sorğuları idempotency key ilə göndərin (`Idempotency-Key` header); server eyni key-li request-i ikinci dəfə icra etməsin.

**4. gRPC-dən istifadə edərkən .proto contract versioning-i planlaşdırmamaq**
`proto` faylı dəyişir, köhnə client-lər uyuşmazlıq yaşayır — bütün servisler eyni anda deploy edilməlidir. Backward compatible dəyişikliklər edin: yeni sahə əlavə et (sil, adını dəyişmə); `.proto` faylını versiyalandırın, köhnə versiyanı müəyyən müddət dəstəkləyin.

**5. Choreography-də event chain-i izləməmək**
Servis A event atır → B işləyir → C işləyir → D işləyir — bir addım uğursuz olunca hansı servisin nə etdiyini anlamaq mümkün olmur. Hər event-ə `correlation_id` əlavə edin; distributed tracing (Tempo) qurun, event chain-i başdan sona izlənilsin.

**6. Async kommunikasiyada error handling-i unuda-unuda getmək**
Mesaj göndərilir, consumer xəta atır, DLQ yoxdur, mesaj itirilir — nə baş verdiyini heç kim bilmir. Hər async iş axını üçün DLQ konfiqurasiya edin; retry strategiyası müəyyən edin; DLQ-da biriken mesajlar üçün alert qurun.
