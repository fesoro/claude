# gRPC & Protocol Buffers

## Mündəricat
1. [gRPC nədir?](#grpc-nədir)
2. [Protocol Buffers (Protobuf)](#protocol-buffers-protobuf)
3. [gRPC Servis Növləri](#grpc-servis-növləri)
4. [gRPC vs REST vs GraphQL](#grpc-vs-rest-vs-graphql)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## gRPC nədir?

```
gRPC — Google-un açıq mənbəli RPC framework-ü.
HTTP/2 üzərində işləyir, Protocol Buffers istifadə edir.

Ənənəvi REST:           gRPC:
┌────────────────────┐  ┌────────────────────────────────┐
│ POST /orders       │  │ OrderService.CreateOrder(      │
│ Content-Type: JSON │  │   CreateOrderRequest{...}      │
│ {"item": "book"}   │  │ ) → CreateOrderResponse        │
└────────────────────┘  └────────────────────────────────┘
   Text, Human-readable    Binary, Schema-defined

gRPC-nin üstünlükləri:
  + HTTP/2: multiplexing, header compression, binary framing
  + Protobuf: JSON-dan 3-10x daha kiçik, daha sürətli serialize
  + Strongly-typed: .proto faylından kod generate edilir
  + Bidirectional streaming
  + Deadline/timeout daxili dəstəyi
  + Çox dil dəstəyi (PHP, Go, Java, Python, ...)

Çatışmazlıqları:
  - Browser dəstəyi limitlidir (gRPC-Web lazımdır)
  - Human-readable deyil (debug çətin)
  - .proto fayl idarəsi lazımdır
  - Load balancer dəstəyi (HTTP/2 aware olmalıdır)
```

---

## Protocol Buffers (Protobuf)

```
Binary serialization format — JSON alternativ.

JSON:                         Protobuf (binary):
{                             [field:1=1][field:2="John Doe"]
  "id": 1,                    [field:3=25]
  "name": "John Doe",         → 8 bytes (JSON: 40 bytes)
  "age": 25
}

.proto şəması:
  message User {
    int32  id   = 1;   // field number (binary-də istifadə edilir)
    string name = 2;
    int32  age  = 3;
  }

Field numbers:
  1-15  → 1 byte encoding (tez-tez işlənən sahələr üçün)
  16-2047 → 2 byte encoding
  Sabit qalmalıdır — dəyişdirmək backward incompatible!

Backward compatibility:
  Sahə əlavə etmək → OK (köhnə client görməz)
  Sahə silmək      → OK (field number rezerv et!)
  Type dəyişmək    → DANGEROUS
  Field number dəyişmək → BREAKING CHANGE
```

---

## gRPC Servis Növləri

```
1. Unary (Tək-tək):
   Client bir request → Server bir response
   
   rpc GetUser(GetUserRequest) returns (User);
   
   ↔ REST GET /users/1 ilə eyni semantika

2. Server Streaming:
   Client bir request → Server çoxlu response
   
   rpc ListOrders(ListOrdersRequest) returns (stream Order);
   
   Nümunə: Real-time price updates

3. Client Streaming:
   Client çoxlu request → Server bir response
   
   rpc UploadChunks(stream Chunk) returns (UploadResult);
   
   Nümunə: Böyük fayl upload

4. Bidirectional Streaming:
   Client ↔ Server — hər ikisi eyni anda stream
   
   rpc Chat(stream ChatMessage) returns (stream ChatMessage);
   
   Nümunə: Real-time chat, live collaboration

HTTP/2 multiplexing sayəsində bütün bu növlər
eyni TCP connection üzərindən işləyir.
```

---

## gRPC vs REST vs GraphQL

```
┌──────────────┬───────────────┬───────────────┬───────────────┐
│              │     REST      │     gRPC      │   GraphQL     │
├──────────────┼───────────────┼───────────────┼───────────────┤
│ Protocol     │ HTTP/1.1+     │ HTTP/2        │ HTTP/1.1+     │
│ Format       │ JSON/XML      │ Protobuf      │ JSON          │
│ Schema       │ Optional(OAS) │ Required(.proto)│ Required    │
│ Streaming    │ SSE/WS        │ Native        │ Subscription  │
│ Browser      │ ✅ Native     │ ⚠️ gRPC-Web   │ ✅ Native    │
│ Performance  │ Medium        │ High          │ Medium        │
│ Type safety  │ Optional      │ Strong        │ Strong        │
│ Discoverability│ Swagger    │ Reflection    │ Introspection │
│ Learning curve│ Low          │ Medium        │ Medium-High   │
└──────────────┴───────────────┴───────────────┴───────────────┘

Nə vaxt gRPC:
  ✓ Microservice-lər arası (internal API)
  ✓ Performance kritikdir
  ✓ Streaming lazımdır
  ✓ Çox dil, strongly-typed contract

Nə vaxt REST:
  ✓ Public API (browser, mobile)
  ✓ Sadə CRUD
  ✓ HTTP caching lazımdır

Nə vaxt GraphQL:
  ✓ Client-driven queries (mobile bandwidth)
  ✓ Aggregating multiple services (BFF pattern)
  ✓ Rapidly evolving API
```

---

## PHP İmplementasiyası

```protobuf
// order.proto
syntax = "proto3";

package order;

message CreateOrderRequest {
  int32  customer_id = 1;
  repeated OrderItem items = 2;
  string currency = 3;
}

message OrderItem {
  int32 product_id = 1;
  int32 quantity   = 2;
  int64 price_cents = 3;
}

message CreateOrderResponse {
  int32  order_id    = 1;
  string status      = 2;
  int64  total_cents = 3;
}

service OrderService {
  rpc CreateOrder(CreateOrderRequest) returns (CreateOrderResponse);
  rpc ListOrders(ListOrdersRequest)   returns (stream Order);
}
```

```php
<?php
// gRPC Server (PHP - grpc extension lazımdır)
class OrderServiceImpl extends \Order\OrderServiceStub
{
    public function CreateOrder(
        \Order\CreateOrderRequest $request,
        \Grpc\ServerContext $context
    ): \Order\CreateOrderResponse {

        $customerId = $request->getCustomerId();
        $items = [];

        foreach ($request->getItems() as $item) {
            $items[] = [
                'product_id' => $item->getProductId(),
                'quantity'   => $item->getQuantity(),
                'price'      => $item->getPriceCents(),
            ];
        }

        $order = $this->orderService->create($customerId, $items);

        $response = new \Order\CreateOrderResponse();
        $response->setOrderId($order->getId());
        $response->setStatus($order->getStatus());
        $response->setTotalCents($order->getTotalCents());

        return $response;
    }
}

// gRPC Client
$channel = new \Grpc\Channel('order-service:50051', [
    'credentials' => \Grpc\ChannelCredentials::createInsecure(),
]);

$client = new \Order\OrderServiceClient('order-service:50051', [
    'credentials' => \Grpc\ChannelCredentials::createInsecure(),
]);

$request = new \Order\CreateOrderRequest();
$request->setCustomerId(42);

$item = new \Order\OrderItem();
$item->setProductId(1);
$item->setQuantity(2);
$item->setPriceCents(9900);
$request->setItems([$item]);

[$response, $status] = $client->CreateOrder($request)->wait();

if ($status->code !== \Grpc\STATUS_OK) {
    throw new \RuntimeException("gRPC error: " . $status->details);
}

echo "Order created: " . $response->getOrderId();
```

---

## İntervyu Sualları

- gRPC HTTP/2-nin hansı xüsusiyyətlərindən istifadə edir?
- Protobuf JSON-dan niyə daha sürətli/kiçikdir?
- `.proto` faylında field number niyə vacibdir? Dəyişmək nə ilə nəticələnər?
- Bidirectional streaming REST ilə mümkündürmü?
- Microservice-lərdə gRPC vs REST seçimini necə edərdiniz?
- gRPC deadline/timeout mexanizmi REST-dən nə ilə fərqlənir?
- Browser gRPC-ni niyə birbaşa dəstəkləmir?
