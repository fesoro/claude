# gRPC Internal Service Communication (Lead)

## Ssenari

Mikroservislər arasında yüksək performanslı internal communication. PHP gRPC client, proto definition, service-to-service call, streaming.

---

## Niyə gRPC?

```
REST/JSON:           gRPC/Protobuf:
  {"order_id":123,     Binary (70% kiçik)
   "status":"paid",    HTTP/2 multiplexing
   "total":1500}       Strong typing
                       Streaming
                       ~5x sürətli parse

Internal services üçün gRPC ideal:
  → Browser lazım deyil
  → Type safety (proto schema)
  → High throughput (payments, inventory check)
  → Streaming (live order updates)
```

---

## Proto Definition

*Bu kod Inventory servisinin unary, server streaming və client streaming RPC metodlarını müəyyən edən proto faylını göstərir:*

```protobuf
// proto/inventory_service.proto
syntax = "proto3";
package inventory;

option php_namespace = "App\\Grpc\\Inventory";

service InventoryService {
    // Unary: tək sorğu, tək cavab
    rpc CheckStock(CheckStockRequest) returns (StockStatus);
    rpc ReserveStock(ReserveStockRequest) returns (ReservationResult);
    rpc ReleaseReservation(ReleaseRequest) returns (ReleaseResult);
    
    // Server streaming: real-time stock updates
    rpc WatchStock(WatchStockRequest) returns (stream StockUpdate);
    
    // Client streaming: bulk check
    rpc BulkCheckStock(stream CheckStockRequest) returns (BulkStockStatus);
}

message CheckStockRequest {
    string product_id = 1;
    int32  quantity   = 2;
}

message StockStatus {
    string product_id = 1;
    int32  available  = 2;
    bool   in_stock   = 3;
    string reserved_until = 4;  // ISO 8601
}

message ReserveStockRequest {
    string order_id    = 1;
    string product_id  = 2;
    int32  quantity    = 3;
    int32  ttl_seconds = 4;  // Reservation expiry
}

message ReservationResult {
    bool   success        = 1;
    string reservation_id = 2;
    string error_message  = 3;
    
    enum FailureReason {
        NONE            = 0;
        OUT_OF_STOCK    = 1;
        ALREADY_RESERVED = 2;
        INVALID_PRODUCT = 3;
    }
    FailureReason failure_reason = 4;
}

message ReleaseRequest {
    string reservation_id = 1;
}

message ReleaseResult {
    bool success = 1;
}

message WatchStockRequest {
    repeated string product_ids = 1;
}

message StockUpdate {
    string product_id = 1;
    int32  available  = 2;
    string updated_at = 3;
}

message BulkStockStatus {
    repeated StockStatus items = 1;
}
```

---

## PHP gRPC Client

*Bu kod PHP gRPC asılılıqlarının quraşdırılmasını və proto faylından PHP kodunun generasiyasını göstərir:*

```bash
# PHP extensions + packages
apt install php-grpc php-protobuf
composer require grpc/grpc google/protobuf

# Proto faylından PHP kodu generate et
protoc --proto_path=proto \
       --php_out=generated \
       --grpc_out=generated \
       --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
       proto/inventory_service.proto
```

*Bu kod stok yoxlama, rezerv etmə, server streaming və client streaming çağırışlarını idarə edən gRPC client wrapper-ı göstərir:*

```php
// Generated client wrapper
class InventoryGrpcClient
{
    private InventoryServiceClient $client;
    
    public function __construct()
    {
        $this->client = new InventoryServiceClient(
            config('services.inventory.grpc_address'),  // 'inventory-service:50051'
            [
                'credentials' => $this->buildCredentials(),
                'timeout'     => 5 * 1000 * 1000,  // 5s in microseconds
            ]
        );
    }
    
    public function checkStock(string $productId, int $quantity): StockStatus
    {
        $request = new CheckStockRequest();
        $request->setProductId($productId);
        $request->setQuantity($quantity);
        
        [$response, $status] = $this->client->CheckStock($request)->wait();
        
        $this->handleStatus($status);
        
        return $response;
    }
    
    public function reserveStock(
        string $orderId,
        string $productId,
        int $quantity,
        int $ttlSeconds = 900
    ): ReservationResult {
        $request = new ReserveStockRequest();
        $request->setOrderId($orderId);
        $request->setProductId($productId);
        $request->setQuantity($quantity);
        $request->setTtlSeconds($ttlSeconds);
        
        [$response, $status] = $this->client->ReserveStock($request)->wait();
        
        $this->handleStatus($status);
        
        if (!$response->getSuccess()) {
            throw match($response->getFailureReason()) {
                ReservationResult\FailureReason::OUT_OF_STOCK     => new OutOfStockException($productId),
                ReservationResult\FailureReason::ALREADY_RESERVED => new AlreadyReservedException($productId),
                default => new InventoryException($response->getErrorMessage()),
            };
        }
        
        return $response;
    }
    
    // Server streaming — real-time stock updates
    public function watchStock(array $productIds): \Generator
    {
        $request = new WatchStockRequest();
        $request->setProductIds($productIds);
        
        $stream = $this->client->WatchStock($request);
        
        foreach ($stream->responses() as $update) {
            yield [
                'product_id' => $update->getProductId(),
                'available'  => $update->getAvailable(),
                'updated_at' => $update->getUpdatedAt(),
            ];
        }
    }
    
    // Client streaming — bulk check
    public function bulkCheckStock(array $items): array
    {
        [$call, $deserializer] = $this->client->BulkCheckStock();
        
        foreach ($items as $item) {
            $request = new CheckStockRequest();
            $request->setProductId($item['product_id']);
            $request->setQuantity($item['quantity']);
            $call->write($request);
        }
        
        [$response, $status] = $call->wait();
        $this->handleStatus($status);
        
        return array_map(
            fn($s) => ['product_id' => $s->getProductId(), 'in_stock' => $s->getInStock()],
            iterator_to_array($response->getItems())
        );
    }
    
    private function handleStatus(\stdClass $status): void
    {
        if ($status->code !== \Grpc\STATUS_OK) {
            throw match($status->code) {
                \Grpc\STATUS_NOT_FOUND      => new ProductNotFoundException($status->details),
                \Grpc\STATUS_UNAVAILABLE    => new ServiceUnavailableException('Inventory service mövcud deyil'),
                \Grpc\STATUS_DEADLINE_EXCEEDED => new TimeoutException('Inventory service timeout'),
                default                     => new GrpcException($status->details, $status->code),
            };
        }
    }
    
    private function buildCredentials(): \Grpc\ChannelCredentials
    {
        if (app()->isProduction()) {
            // mTLS (mutual TLS)
            return \Grpc\ChannelCredentials::createSsl(
                file_get_contents(config('grpc.ca_cert')),
                file_get_contents(config('grpc.client_key')),
                file_get_contents(config('grpc.client_cert'))
            );
        }
        
        return \Grpc\ChannelCredentials::createInsecure();
    }
}
```

---

## Interceptors (Middleware)

*Bu kod gRPC çağırışına trace ID əlavə edib müddəti ölçən interceptor-u göstərir:*

```php
// gRPC Interceptor — tracing, auth, retry
class TracingInterceptor
{
    public function interceptUnaryUnary(
        $method,
        $argument,
        $deserialize,
        $continuation,
        array $metadata = [],
        array $options = []
    ) {
        // Trace ID ötür
        $metadata['x-trace-id'] = [request()->header('X-Trace-Id', Str::uuid())];
        $metadata['x-service-name'] = ['php-app'];
        
        $start = microtime(true);
        
        try {
            $result = $continuation($method, $argument, $deserialize, $metadata, $options);
            
            $duration = (microtime(true) - $start) * 1000;
            Log::debug("gRPC call", ['method' => $method, 'duration_ms' => $duration]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("gRPC error", ['method' => $method, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

---

## Order Service-də İstifadə

*Bu kod gRPC ilə stok yoxlayıb, sifariş yaradıb rezervasiyalar edən, xəta halında əvvəlki rezervasiyaları buraxan order servisini göstərir:*

```php
class OrderApplicationService
{
    public function __construct(
        private InventoryGrpcClient $inventory,
        private OrderRepository $orders,
    ) {}
    
    public function placeOrder(PlaceOrderCommand $cmd): Order
    {
        // 1. Stock yoxla (gRPC)
        foreach ($cmd->items as $item) {
            $stock = $this->inventory->checkStock($item['product_id'], $item['quantity']);
            
            if (!$stock->getInStock()) {
                throw new InsufficientStockException($item['product_id']);
            }
        }
        
        // 2. Order yarat
        $order = Order::create([...]);
        
        // 3. Stock rezerv et (gRPC)
        $reservations = [];
        foreach ($cmd->items as $item) {
            try {
                $result = $this->inventory->reserveStock(
                    $order->id,
                    $item['product_id'],
                    $item['quantity'],
                    ttlSeconds: 900  // 15 dəq
                );
                $reservations[] = $result->getReservationId();
            } catch (OutOfStockException $e) {
                // Əvvəlki rezervasiyaları burax
                foreach ($reservations as $reservationId) {
                    $this->inventory->releaseReservation($reservationId);
                }
                throw $e;
            }
        }
        
        return $order;
    }
}
```

---

## İntervyu Sualları

**1. gRPC niyə internal microservice kommunikasiyası üçün uygundur?**
Binary protobuf: JSON-dan ~70% kiçik, ~5x sürətli parse. HTTP/2: 1 connection-da paralel sorğular (multiplexing). Strong typing: .proto contract, compile-time yoxlama. Streaming: unary, server/client/bidirectional. Polyglot: Java, Go, PHP, Python eyni proto.

**2. 4 gRPC call növünü izah et.**
Unary: 1 request → 1 response (ən sadə, REST kimi). Server streaming: 1 request → N response (real-time updates). Client streaming: N request → 1 response (bulk upload). Bidirectional: N request ↔ N response (chat, live dashboard).

**3. mTLS nədir, niyə lazımdır?**
Mutual TLS: həm server, həm client sertifikat təqdim edir. Server: "Mən kimim" + Client: "Mən kimim". Yalnız tanınan servislər kommunikasiya edə bilər. Internal network-də bile man-in-the-middle qarşısını alır. Service mesh (Istio) bunu avtomatik idarə edir.

**4. Protobuf versioning necə idarə edilir?**
Field nömrələri sabitdir — heç vaxt dəyişdirilmir. Yeni field → yeni nömrə əlavə et (backward compatible). Köhnə field → deprecated işarələ, silmə. Breaking change: yeni service version (`/v2`). Proto-da `reserved` keyword — silinmiş field nömrələrini qoru.

---

## Retry və Circuit Breaker

*Bu kod müəyyən sayda xətadan sonra circuit-i açıb servisi müvəqqəti söndürən circuit breaker implementasiyasını göstərir:*

```php
// gRPC servis down olduqda cascading failure önlənir
class ResilientInventoryClient
{
    private int $failureCount = 0;
    private ?Carbon $openedAt = null;
    private const THRESHOLD   = 5;   // 5 xətadan sonra circuit açılır
    private const TIMEOUT_SEC = 30;  // 30 saniyə açıq qalır

    public function checkStock(string $productId, int $quantity): StockStatus
    {
        // Circuit breaker: OPEN state- də sorğu atma
        if ($this->isOpen()) {
            throw new ServiceUnavailableException('Inventory service circuit open');
        }

        try {
            $result = $this->client->checkStock($productId, $quantity);
            $this->onSuccess();
            return $result;
        } catch (TimeoutException | ServiceUnavailableException $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        if ($this->failureCount < self::THRESHOLD) return false;
        // Half-open: timeout geçibsə bir sorğuya icazə ver
        if ($this->openedAt->diffInSeconds() > self::TIMEOUT_SEC) return false;
        return true;
    }

    private function onSuccess(): void { $this->failureCount = 0; $this->openedAt = null; }
    private function onFailure(): void
    {
        $this->failureCount++;
        if ($this->failureCount === self::THRESHOLD) {
            $this->openedAt = now();
        }
    }
}
```

---

## İntervyu Sualları

**5. gRPC-də error handling necə aparılır?**
gRPC status codes: `OK`, `NOT_FOUND`, `INVALID_ARGUMENT`, `DEADLINE_EXCEEDED`, `UNAVAILABLE`, `INTERNAL`. PHP-də `$status->code` yoxlanılır. `UNAVAILABLE` retry edilə bilər, `INVALID_ARGUMENT` retry etmək mənasızdır. Status-a görə müvafiq exception throw edilir.

**6. Service discovery gRPC-də necə işləyir?**
Kubernetes-də servis adı DNS kimi işləyir: `inventory-service:50051`. Consul/etcd ilə dinamik discovery mümkündür. Load balancing: client-side (multiple addresses) və ya server-side (L4/L7 load balancer). Istio service mesh bunu şəffaf idarə edir.

---

## Anti-patternlər

**1. Proto field nömrələrini dəyişmək**
Mövcud `.proto` faylında field nömrəsini dəyişmək və ya silinmiş nömrəni yenidən başqa field üçün istifadə etmək — köhnə client yeni mesajları yanlış decode edir, data korrupsiyası yaranır. Field nömrələrini heç vaxt dəyişmə, silinmiş nömrələri `reserved` ilə qoru.

**2. gRPC-ni external (public) API üçün istifadə etmək**
Browser-lərin birbaşa çağırdığı public endpointlər üçün gRPC tətbiq etmək — brauzerlər native HTTP/2 gRPC protokolunu dəstəkləmir, gRPC-Web proxy lazım olur, ekstra mürəkkəblik yaranır. gRPC-ni yalnız internal servis-servis kommunikasiyası üçün istifadə et, xarici API-lər üçün REST/GraphQL seç.

**3. mTLS olmadan internal gRPC kommunikasiyası qurmaq**
Internal şəbəkədə gRPC servisləri arasında autentifikasiya tətbiq etməmək — "internal network güvənlidir" fərziyyəsi ilə qurulan sistem, şəbəkəyə daxil olan istənilən servis bütün gRPC endpoint-lərə çağırış edə bilir. mTLS ilə qarşılıqlı sertifikat doğrulaması tətbiq et, yalnız tanınan servislərin kommunikasiya etməsinə icazə ver.

**4. Protobuf-da breaking change-i yeni field əvəzinə mövcudu dəyişməklə etmək**
`string name = 1` field-ini `UserName name = 1` kimi tip dəyişikliyi ilə update etmək — bu backward incompatible-dır, köhnə client-lər yanlış data parse edir. Yeni field üçün yeni nömrə əlavə et, köhnəni deprecated işarələ, tam keçid sonra `reserved`-ə al.

**5. Unary RPC ilə böyük data axını ötürmək**
Yüzlərlə MB-lıq sənəd və ya event axını üçün tək unary RPC çağırışı etmək — böyük payload serialization/deserialization-da gecikmə yaradır, memory spike olur, timeout riski artır. Böyük data ötürmək üçün server-side streaming və ya bidirectional streaming RPC istifadə et.

**6. gRPC timeout və deadline-larını tətbiq etməmək**
gRPC çağırışlarına deadline və ya timeout konfiqurasiya etməmək — yavaş servis digər servisin thread-lərini sonsuz bloklaşdırır, kaskad uğursuzluq yaranır. Hər gRPC çağırışına ağlabatan deadline ver (məs. 5s), timeout-da graceful xəta qaytarmaq üçün statuscode (`DEADLINE_EXCEEDED`) handle et.
