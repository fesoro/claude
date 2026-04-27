# gRPC ilə Java/Spring — Geniş İzah (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
1. [gRPC nədir?](#grpc-nədir)
2. [Protocol Buffers (.proto)](#protocol-buffers-proto)
3. [Spring Boot gRPC Server](#spring-boot-grpc-server)
4. [gRPC Client](#grpc-client)
5. [Streaming](#streaming)
6. [İntervyu Sualları](#intervyu-sualları)

---

## gRPC nədir?

**gRPC** — Google tərəfindən hazırlanmış, HTTP/2 üzərindəki yüksək performanslı RPC (Remote Procedure Call) framework-ü. Protocol Buffers (protobuf) istifadə edir — JSON-dan çox daha sürətli serialization.

```
REST vs gRPC müqayisəsi:

REST:
  POST /api/orders
  Content-Type: application/json
  {"customerId": "123", "items": [...]}
  → JSON serialize/deserialize (yavaş)
  → HTTP/1.1 text-based

gRPC:
  OrderService.CreateOrder(CreateOrderRequest)
  → Protobuf binary (sürətli, kiçik)
  → HTTP/2 multiplexing, streaming
  → Strongly typed (compile-time yoxlama)
  → Code generation (client/server stub-lar)
```

**Nə zaman gRPC:**
- Service-to-service kommunikasiya
- Yüksək throughput, aşağı latency
- Streaming (bidirectional)
- Strongly-typed contract

**Nə zaman REST:**
- Public API (browser, mobile)
- HTTP/JSON geniş dəstək
- Human-readable

---

## Protocol Buffers (.proto)

```protobuf
// src/main/proto/order_service.proto
syntax = "proto3";

package com.example.grpc;

option java_package = "com.example.grpc.proto";
option java_multiple_files = true;

// Enum
enum OrderStatus {
  ORDER_STATUS_UNSPECIFIED = 0;
  ORDER_STATUS_PENDING = 1;
  ORDER_STATUS_CONFIRMED = 2;
  ORDER_STATUS_SHIPPED = 3;
  ORDER_STATUS_DELIVERED = 4;
  ORDER_STATUS_CANCELLED = 5;
}

// Message
message OrderItem {
  string product_id = 1;
  int32 quantity = 2;
  double unit_price = 3;
}

message CreateOrderRequest {
  string customer_id = 1;
  repeated OrderItem items = 2;
  string delivery_address = 3;
}

message CreateOrderResponse {
  string order_id = 1;
  OrderStatus status = 2;
  double total_amount = 3;
  string created_at = 4;
}

message GetOrderRequest {
  string order_id = 1;
}

message GetOrderResponse {
  string order_id = 1;
  string customer_id = 2;
  OrderStatus status = 3;
  repeated OrderItem items = 4;
  double total_amount = 5;
}

message ListOrdersRequest {
  string customer_id = 1;
  int32 page_size = 2;
  string page_token = 3;
}

message ListOrdersResponse {
  repeated GetOrderResponse orders = 1;
  string next_page_token = 2;
  int32 total_count = 3;
}

// Service definition
service OrderService {
  // Unary RPC
  rpc CreateOrder (CreateOrderRequest) returns (CreateOrderResponse);
  rpc GetOrder (GetOrderRequest) returns (GetOrderResponse);
  rpc ListOrders (ListOrdersRequest) returns (ListOrdersResponse);

  // Server streaming
  rpc WatchOrder (GetOrderRequest) returns (stream GetOrderResponse);

  // Client streaming
  rpc BulkCreateOrders (stream CreateOrderRequest) returns (CreateOrderResponse);

  // Bidirectional streaming
  rpc OrderChat (stream CreateOrderRequest) returns (stream GetOrderResponse);
}
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>net.devh</groupId>
    <artifactId>grpc-spring-boot-starter</artifactId>
    <version>3.0.0</version>
</dependency>
<dependency>
    <groupId>io.grpc</groupId>
    <artifactId>grpc-stub</artifactId>
</dependency>

<plugin>
    <groupId>org.xolstice.maven.plugins</groupId>
    <artifactId>protobuf-maven-plugin</artifactId>
    <!-- Proto fayllarından Java kodu generasiya edir -->
</plugin>
```

---

## Spring Boot gRPC Server

```java
// gRPC Service implementasiyası
@GrpcService
public class OrderGrpcService extends OrderServiceGrpc.OrderServiceImplBase {

    private final OrderService orderService;
    private final OrderGrpcMapper mapper;

    // Unary RPC
    @Override
    public void createOrder(CreateOrderRequest request,
                             StreamObserver<CreateOrderResponse> responseObserver) {
        try {
            // Protobuf → Domain
            CreateOrderCommand command = mapper.toCommand(request);

            // Business logic
            Order order = orderService.createOrder(command);

            // Domain → Protobuf
            CreateOrderResponse response = mapper.toCreateResponse(order);

            responseObserver.onNext(response);
            responseObserver.onCompleted();

        } catch (ValidationException e) {
            responseObserver.onError(
                Status.INVALID_ARGUMENT
                    .withDescription(e.getMessage())
                    .asRuntimeException()
            );
        } catch (Exception e) {
            log.error("Order yaradıla bilmədi", e);
            responseObserver.onError(
                Status.INTERNAL
                    .withDescription("Daxili xəta")
                    .asRuntimeException()
            );
        }
    }

    @Override
    public void getOrder(GetOrderRequest request,
                          StreamObserver<GetOrderResponse> responseObserver) {
        try {
            Order order = orderService.findById(OrderId.of(request.getOrderId()))
                .orElseThrow(() -> new OrderNotFoundException(request.getOrderId()));

            responseObserver.onNext(mapper.toGetResponse(order));
            responseObserver.onCompleted();

        } catch (OrderNotFoundException e) {
            responseObserver.onError(
                Status.NOT_FOUND
                    .withDescription("Sifariş tapılmadı: " + request.getOrderId())
                    .asRuntimeException()
            );
        }
    }

    // Server streaming — order dəyişikliklərini real-time göndər
    @Override
    public void watchOrder(GetOrderRequest request,
                            StreamObserver<GetOrderResponse> responseObserver) {
        String orderId = request.getOrderId();

        // EventListener-ə qeydiyyat et
        orderEventService.subscribe(orderId, event -> {
            if (responseObserver.isReady()) {
                responseObserver.onNext(mapper.toGetResponse(event.getOrder()));
            }
        });

        // Client bağlandıqda subscription-ı ləğv et
        // (Bu simple demo — production-da daha mürəkkəb idarə)
    }
}

// application.yml
// grpc:
//   server:
//     port: 9090
```

---

## gRPC Client

```java
// gRPC Client konfiqurasiyası
@Configuration
public class GrpcClientConfig {

    @Bean
    public OrderServiceGrpc.OrderServiceBlockingStub orderServiceBlockingStub() {
        ManagedChannel channel = ManagedChannelBuilder
            .forAddress("order-service", 9090)
            .usePlaintext() // TLS deaktiv (dev üçün)
            // .useTransportSecurity() // TLS aktiv (prod)
            .intercept(new AuthClientInterceptor())
            .build();

        return OrderServiceGrpc.newBlockingStub(channel)
            .withDeadlineAfter(5, TimeUnit.SECONDS); // Timeout
    }

    @Bean
    public OrderServiceGrpc.OrderServiceFutureStub orderServiceFutureStub() {
        ManagedChannel channel = ManagedChannelBuilder
            .forAddress("order-service", 9090)
            .usePlaintext()
            .build();

        return OrderServiceGrpc.newFutureStub(channel);
    }
}

// grpc-spring-boot-starter ilə sadə client
@Service
public class OrderGrpcClient {

    @GrpcClient("order-service") // application.yml-dəki ad
    private OrderServiceGrpc.OrderServiceBlockingStub orderServiceStub;

    public CreateOrderResponse createOrder(Order order) {
        CreateOrderRequest request = CreateOrderRequest.newBuilder()
            .setCustomerId(order.getCustomerId().toString())
            .addAllItems(order.getItems().stream()
                .map(item -> OrderItem.newBuilder()
                    .setProductId(item.getProductId().toString())
                    .setQuantity(item.getQuantity())
                    .setUnitPrice(item.getUnitPrice().doubleValue())
                    .build())
                .collect(Collectors.toList()))
            .build();

        try {
            return orderServiceStub.createOrder(request);
        } catch (StatusRuntimeException e) {
            if (e.getStatus().getCode() == Status.Code.NOT_FOUND) {
                throw new ResourceNotFoundException("...");
            } else if (e.getStatus().getCode() == Status.Code.INVALID_ARGUMENT) {
                throw new ValidationException(e.getStatus().getDescription());
            }
            throw new ServiceException("gRPC xəta: " + e.getStatus().getDescription());
        }
    }
}

# application.yml
grpc:
  client:
    order-service:
      address: "static://order-service:9090"
      negotiation-type: plaintext
  server:
    port: 9090
```

---

## Streaming

```java
// Client streaming — çoxlu order göndər
@GrpcService
public class OrderGrpcService extends OrderServiceGrpc.OrderServiceImplBase {

    @Override
    public StreamObserver<CreateOrderRequest> bulkCreateOrders(
            StreamObserver<CreateOrderResponse> responseObserver) {

        List<Order> processedOrders = new ArrayList<>();

        // Client-dən gələn mesajları qəbul et
        return new StreamObserver<>() {

            @Override
            public void onNext(CreateOrderRequest request) {
                // Hər gələn request-i emal et
                Order order = orderService.createOrder(mapper.toCommand(request));
                processedOrders.add(order);
            }

            @Override
            public void onError(Throwable t) {
                log.error("Bulk create stream xətası", t);
            }

            @Override
            public void onCompleted() {
                // Bütün mesajlar gəldikdə cavab ver
                CreateOrderResponse response = CreateOrderResponse.newBuilder()
                    .setOrderId(String.valueOf(processedOrders.size()) + " sifariş yaradıldı")
                    .build();
                responseObserver.onNext(response);
                responseObserver.onCompleted();
            }
        };
    }
}

// Interceptor — auth, logging
@GrpcGlobalServerInterceptor
public class AuthServerInterceptor implements ServerInterceptor {

    @Override
    public <Q, R> ServerCall.Listener<Q> interceptCall(
            ServerCall<Q, R> call,
            Metadata headers,
            ServerCallHandler<Q, R> next) {

        String token = headers.get(
            Metadata.Key.of("authorization", Metadata.ASCII_STRING_MARSHALLER));

        if (token == null || !validateToken(token)) {
            call.close(Status.UNAUTHENTICATED
                .withDescription("Token tələb olunur"), new Metadata());
            return new ServerCall.Listener<>() {};
        }

        return next.startCall(call, headers);
    }
}
```

---

## İntervyu Sualları

### 1. gRPC-nin REST üzərindəki üstünlükləri?
**Cavab:** (1) Protobuf — JSON-dan 3-10x daha kiçik, 5-10x daha sürətli. (2) HTTP/2 — multiplexing, header compression, bidirectional streaming. (3) Strongly typed — compile-time yoxlama, code generation. (4) Bidirectional streaming — WebSocket-ə alternativ. (5) Service definition (proto) — living documentation.

### 2. Protocol Buffers nə üçündür?
**Cavab:** Language-neutral, platform-neutral serialization format. `.proto` faylında message/service müəyyən edilir, `protoc` compiler Java, Go, Python, JavaScript stub-ları generasiya edir. JSON-a nisbətən binary format — daha kiçik, daha sürətli, schema evolution (backward/forward compat).

### 3. gRPC-nin 4 kommunikasiya növü nədir?
**Cavab:** (1) **Unary** — bir request, bir response (klassik). (2) **Server streaming** — bir request, çoxlu response (live updates). (3) **Client streaming** — çoxlu request, bir response (file upload). (4) **Bidirectional streaming** — çoxlu request, çoxlu response (chat, game).

### 4. gRPC error handling REST-dən nə ilə fərqlənir?
**Cavab:** HTTP status code (200, 404, 500) əvəzinə gRPC `Status` kodu istifadə edir: `OK`, `NOT_FOUND`, `INVALID_ARGUMENT`, `INTERNAL`, `UNAUTHENTICATED`, `PERMISSION_DENIED`, vb. `StatusRuntimeException` ilə tutulur. `Metadata` ilə əlavə məlumat göndərilə bilər.

### 5. gRPC nə zaman istifadə olunmaz?
**Cavab:** Public API-lər — browser native gRPC dəstəkləmir (grpc-web proxy lazımdır). Aşağı latency tələb olmayan sadə API-lər. REST ecosystem-i (OpenAPI, Postman) ilə inteqrasiya. Firewall/proxy-lər HTTP/2-ni bloka edə bilər. Team protobuf bilmirsə — öyrənmə əyrisi var.

*Son yenilənmə: 2026-04-10*
