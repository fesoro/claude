# Protocol Buffers (Middle)

## İcmal

**Protocol Buffers** (protobuf) — Google tərəfindən 2001-də daxili istifadə üçün yaradılmış, 2008-də open-source edilmiş **binary serialization format** və **schema definition language**. Strukturlu məlumatı kompakt, sürətli şəkildə encode/decode etmək üçündür.

```
JSON:                    Protobuf:
{"name":"Ali","age":30}  0A 03 41 6C 69 10 1E
24 bytes                 7 bytes
```

**Əsas xüsusiyyətlər:**
- **Schema-based:** `.proto` faylında message strukturları təyin edilir
- **Code generation:** schema-dan hədəf dil üçün class/struct generasiya olunur (`protoc`)
- **Binary wire format:** JSON-dan 3-10x kiçik, 20-100x sürətli parse
- **Backward/forward compatibility:** yeni field əlavə etmək köhnə client-ləri qırmır
- **Cross-language:** PHP, Go, Java, Python, Rust, C++, Swift və s.

## Niyə Vacibdir

Yüksək throughput microservice kommunikasiyasında, Kafka topic-lərində, mobile API-larda JSON-un payload ölçüsü və parse overhead-i performans problem yaradır. gRPC default olaraq protobuf istifadə edir. Laravel developer-i üçün əhəmiyyət: Redis/SQS queue-da compact storage, gRPC server/client, B2B binary API.

## Əsas Anlayışlar

### .proto Schema

```protobuf
// order.proto
syntax = "proto3";

package ecommerce.v1;

option php_namespace = "App\\Proto\\Ecommerce\\V1";

message Order {
  int64  id = 1;
  string customer_email = 2;
  double total = 3;
  OrderStatus status = 4;
  repeated OrderItem items = 5;
  google.protobuf.Timestamp created_at = 6;
}

message OrderItem {
  int64 product_id = 1;
  int32 quantity = 2;
  double price = 3;
}

enum OrderStatus {
  ORDER_STATUS_UNSPECIFIED = 0;   // proto3-də 0 məcburidir
  ORDER_STATUS_PENDING = 1;
  ORDER_STATUS_PAID = 2;
  ORDER_STATUS_SHIPPED = 3;
  ORDER_STATUS_CANCELLED = 4;
}

service OrderService {
  rpc GetOrder(GetOrderRequest) returns (Order);
  rpc ListOrders(ListOrdersRequest) returns (ListOrdersResponse);
}

message GetOrderRequest {
  int64 id = 1;
}

message ListOrdersRequest {
  int32 page_size = 1;
  string page_token = 2;
}

message ListOrdersResponse {
  repeated Order orders = 1;
  string next_page_token = 2;
}
```

### Wire Format

```
Tag byte: [field_number << 3 | wire_type]
          Wire types:
            0 = Varint (int32, int64, bool, enum)
            1 = 64-bit (fixed64, double)
            2 = Length-delimited (string, bytes, embedded messages, repeated)
            5 = 32-bit (fixed32, float)

Nümunə: Order{id=42, customer_email="Ali"}

Field 1 (id=42, varint):
  Tag:   (1 << 3) | 0 = 0x08
  Value: 42 = 0x2A
  Bytes: 08 2A

Field 2 (customer_email="Ali", length-delimited):
  Tag:    (2 << 3) | 2 = 0x12
  Length: 3 = 0x03
  Data:   "Ali" = 0x41 0x6C 0x69
  Bytes:  12 03 41 6C 69

Cəmi: 08 2A 12 03 41 6C 69  (7 byte)
JSON: {"id":42,"customer_email":"Ali"} = 36 byte
```

### Varint Encoding

```
Variable-length integer. Kiçik rəqəmlər az byte istifadə edir.

Qayda:
  - Hər byte 7 data bit + 1 continuation bit (MSB)
  - MSB=1: "daha byte var"
  - MSB=0: "son byte"
  - Little-endian qrup sırası

Nümunə: 300 = 0b100101100

7-bit chunk-lara bölün (LSB-dən): 0101100, 0000010
Continuation bit əlavə et:  10101100  00000010 = 0xAC 0x02

300 → 2 byte (int32-in 4 baytı əvəzinə)

Problem: Mənfi int32/int64 10 byte olur (sign-extension).
Həll: Kiçik mənfi rəqəmlər üçün sint32/sint64 (zigzag encoding) istifadə et.
```

### Field Numbers — Schema Evolution

```protobuf
// V1
message User {
  int64  id = 1;
  string name = 2;
  string email = 3;
}

// V2 — TƏHLÜKƏSİZ dəyişikliklər:
message User {
  int64  id = 1;
  string name = 2;
  string email = 3;
  int32  age = 4;          // YENİ field, yeni tag
  reserved 10 to 15;       // gələcək üçün rezerv
  reserved "old_field";    // silinmiş ad rezerv
}

// TƏHLÜKƏLİ (uyğunsuzluq yaradır):
//   - Mövcud field-in nömrəsini dəyişmək
//   - Mövcud field-in tipini dəyişmək
//   - Əvvəl istifadə olunmuş tag nömrəsini yenidən istifadə etmək
```

**Qayda:** 1-15 arası nömrələr 1 byte tag verir (tez-tez istifadə olunanlar üçün), 16-2047 arası 2 byte.

### Default Values (proto3)

```
Field yoxdursa default:
  int32/int64/uint32/uint64 = 0
  bool = false
  string = ""
  bytes = b""
  enum = 0 (birinci dəyər)
  message = null (mövcud deyil)
  repeated = []

proto3.15+: optional keyword presence tracking üçün:
  optional string middle_name = 5;  // hasMiddleName() metodu yaranır
```

### Well-Known Types

```protobuf
import "google/protobuf/timestamp.proto";
import "google/protobuf/duration.proto";
import "google/protobuf/any.proto";
import "google/protobuf/struct.proto";
import "google/protobuf/empty.proto";
import "google/protobuf/wrappers.proto";  // nullable primitives

message Order {
  google.protobuf.Timestamp  created_at  = 1;  // UTC nanoseconds
  google.protobuf.Duration   delivery_eta = 2; // seconds + nanos
  google.protobuf.StringValue coupon     = 3;  // nullable string
}
```

### Protobuf vs JSON

```
              Protobuf          JSON
Ölçü          ~3-10x kiçik      Böyük (text)
Sürət         ~20-100x sürətli  Yavaş (text parsing)
İnsan oxuması Yox               Bəli
Schema        Məcburi           Könüllü
Evolution     Strukturlu        Ad hoc
Tooling       protoc lazımdır   Hər yerdə built-in
Debug         Çətin (binary)    Asan (curl)
Brauzer       Limitli (gRPC-Web) Native

İstifadə:
  Protobuf: microservices, mobile, yüksək throughput, ciddi contract
  JSON:     public REST API, brauzer, debug, ad hoc inteqrasiya
```

### Buf — Modern Protobuf Tooling

```bash
brew install bufbuild/buf/buf

buf lint                                    # proto fayllarını yoxla
buf breaking --against '.git#branch=main'  # breaking change aşkar et
buf format -w                               # format et
buf generate                                # kod generasiya et (buf.gen.yaml)
```

`buf.yaml` və `buf.gen.yaml` protoc-dan daha yaxşı idarəetmə verir. Buf Schema Registry-yə push etmək mümkündür.

## Praktik Baxış

- **Public API üçün JSON əlavə et:** Protobuf performantdır, amma debug çətindir. `Content-Type: application/x-protobuf` / `application/json` content negotiation qur.
- **C extension quraşdır:** Pure PHP runtime 5-10x yavaşdır. `pecl install protobuf` production-da məcburidir.
- **Schema-nı ayrı repo-da saxla:** Monorepo yoxdursa, `protos/` reposu, hər servis artifact kimi import edir.
- **Buf breaking check:** CI-da `buf breaking --against '.git#branch=main'` — backward incompatible dəyişiklik PR-da bloklanır.
- **JSON serializeToJsonString() log-da:** Binary format-ı log-da saxlama — debug üçün JSON-a çevir.

### Anti-patterns

- Field number-ları dəyişmək — data corruption, köhnə client yanlış field oxuyur
- Silinmiş field-in nömrəsini `reserved` etməmək — sonrakı developer eyni nömrəni istifadə edə bilər
- 0 enum dəyəri üçün real mənalı dəyər qoymaq — proto3 default 0-dur, "unspecified" olmalıdır
- Schema-sız binary istifadə etmək — `protoc --decode` olmadan debugging mümkünsüzdür

## Nümunələr

### Ümumi Nümunə

```
Schema evolution — backward + forward uyğunluq:

Server yeni field əlavə edir (V2):
  message Order {
    int64  id = 1;
    string email = 2;
    int32  priority = 4;   // YENİ
  }

Köhnə client (V1 ilə):
  - priority field-ini tanımır
  - Deserialize zamanı unknown field kimi saxlayır
  - Re-serialize etsə, priority field saxlanır
  - Data itkisi yoxdur

Yeni client (V2 ilə):
  - priority=0 (default) olaraq görür əgər köhnə data oxuyursa
  - Tam uyğunluq
```

### Kod Nümunəsi

**PHP Code Generation:**
```bash
# PHP runtime quraşdır
composer require google/protobuf

# C extension (5-10x sürətli):
pecl install protobuf
# php.ini: extension=protobuf.so

# PHP class generasiyası
protoc --php_out=./app/Proto \
       --proto_path=./protos \
       ./protos/ecommerce/v1/order.proto

# gRPC stub-ları ilə:
protoc --php_out=./app/Proto \
       --grpc_out=./app/Proto \
       --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin \
       --proto_path=./protos \
       ./protos/ecommerce/v1/order.proto
```

**Serialize / Deserialize:**
```php
use App\Proto\Ecommerce\V1\Order;
use App\Proto\Ecommerce\V1\OrderStatus;
use App\Proto\Ecommerce\V1\OrderItem;

$order = new Order();
$order->setId(42);
$order->setCustomerEmail('ali@example.com');
$order->setTotal(99.50);
$order->setStatus(OrderStatus::ORDER_STATUS_PAID);

$item = new OrderItem();
$item->setProductId(10);
$item->setQuantity(2);
$item->setPrice(49.75);
$order->setItems([$item]);

// Binary-ə serialize et
$binary = $order->serializeToString();

// Deserialize et
$order2 = new Order();
$order2->mergeFromString($binary);
echo $order2->getCustomerEmail(); // ali@example.com
```

**HTTP Request Format Middleware:**
```php
// app/Http/Middleware/ProtobufMiddleware.php
namespace App\Http\Middleware;

use App\Proto\Ecommerce\V1\Order;
use Closure;

class ProtobufMiddleware
{
    public function handle($request, Closure $next)
    {
        if ($request->header('Content-Type') === 'application/x-protobuf') {
            $order = new Order();
            $order->mergeFromString($request->getContent());
            $request->merge(['proto' => $order]);
        }
        return $next($request);
    }
}
```

**Controller — Protobuf Response:**
```php
// app/Http/Controllers/Api/OrderController.php
namespace App\Http\Controllers\Api;

use App\Models\Order as OrderModel;
use App\Proto\Ecommerce\V1\Order;
use App\Proto\Ecommerce\V1\OrderItem;
use App\Proto\Ecommerce\V1\OrderStatus;
use Google\Protobuf\Timestamp;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function show(int $id): Response
    {
        $orderModel = OrderModel::with('items')->findOrFail($id);

        $proto = new Order();
        $proto->setId($orderModel->id);
        $proto->setCustomerEmail($orderModel->customer_email);
        $proto->setTotal((float) $orderModel->total);
        $proto->setStatus(match ($orderModel->status) {
            'pending'   => OrderStatus::ORDER_STATUS_PENDING,
            'paid'      => OrderStatus::ORDER_STATUS_PAID,
            'shipped'   => OrderStatus::ORDER_STATUS_SHIPPED,
            'cancelled' => OrderStatus::ORDER_STATUS_CANCELLED,
            default     => OrderStatus::ORDER_STATUS_UNSPECIFIED,
        });

        $items = [];
        foreach ($orderModel->items as $i) {
            $item = new OrderItem();
            $item->setProductId($i->product_id);
            $item->setQuantity($i->quantity);
            $item->setPrice((float) $i->price);
            $items[] = $item;
        }
        $proto->setItems($items);

        $ts = new Timestamp();
        $ts->setSeconds($orderModel->created_at->timestamp);
        $proto->setCreatedAt($ts);

        return response($proto->serializeToString())
            ->header('Content-Type', 'application/x-protobuf');
    }
}
```

**Kafka / Redis Queue-da Compact Storage:**
```php
// app/Jobs/ProcessOrderProto.php
namespace App\Jobs;

use App\Proto\Ecommerce\V1\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessOrderProto implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $protoBinary) {}

    public function handle(): void
    {
        $order = new Order();
        $order->mergeFromString($this->protoBinary);

        logger()->info("Processing order #{$order->getId()}");
    }
}

// Dispatch
$order = new Order();
$order->setId(42);
ProcessOrderProto::dispatch($order->serializeToString());

// Redis/SQS payload ölçüsü JSON-dan 3-5x kiçik olur
```

**Protobuf ↔ JSON çevirmə:**
```php
use Google\Protobuf\Internal\Message;

$order = new Order();
$order->setId(42);
$order->setCustomerEmail('ali@example.com');

// JSON-a (log, debug üçün)
$json = $order->serializeToJsonString();
// {"id":"42","customerEmail":"ali@example.com"}

// JSON-dan
$order2 = new Order();
$order2->mergeFromJsonString($json);
```

**Schema Evolution Testi:**
```php
// tests/Feature/ProtobufCompatibilityTest.php
class ProtobufCompatibilityTest extends TestCase
{
    public function test_old_client_can_read_new_server_response(): void
    {
        // Server yeni field (discount_amount) əlavə etdi
        // Köhnə client bu field-i tanımır
        // Amma hələ də deserialize edir (unknown field-ləri ignore edir)

        $newOrderBinary = $this->buildNewerVersionBinary();

        $oldOrder = new Order();
        $oldOrder->mergeFromString($newOrderBinary);

        $this->assertEquals(42, $oldOrder->getId());
        // Unknown field data "unknown fields" set-də saxlanılır
        // Re-serialize olunarsa, saxlanır
    }
}
```

## Praktik Tapşırıqlar

1. **İlk .proto faylı yaz:** `User` message-i — id, name, email, created_at (Timestamp) ilə. `protoc` ilə PHP class generasiya et.

2. **Serialize/Deserialize test:** Yaratdığın `User` obyektini binary-ə serialize et, `serializeToJsonString()` ilə JSON-a çevir, ölçü fərqini müqayisə et.

3. **Protobuf HTTP endpoint:** Laravel-ə `ProtobufMiddleware` əlavə et, `Content-Type: application/x-protobuf` request-ləri qəbul edib işlə.

4. **Schema evolution yoxla:** V1 binary-ni V2 schema ilə deserialize et — yeni field-in default dəyər aldığını, köhnə field-lərin düzgün oxunduğunu yoxla.

5. **Queue-da compact storage:** Redis-ə JSON əvəzinə protobuf binary push et, ölçü fərqini (payload inspect) müşahidə et.

6. **buf lint qur:** `buf.yaml` yaz, CI-da `buf lint` + `buf breaking` əlavə et.

## Əlaqəli Mövzular

- [gRPC](10-grpc.md)
- [REST API](08-rest-api.md)
- [OpenAPI & Swagger](38-openapi-swagger.md)
- [Message Protocols](28-message-protocols.md)
- [API Testing Tools](40-api-testing-tools.md)
