# Protocol Buffers (Protobuf)

## Nədir? (What is it?)

**Protocol Buffers** (protobuf) — Google tərəfindən 2001-də daxili istifadə üçün yaradılmış, 2008-də open-source edilmiş **binary serialization format** və **schema definition language**. Strukturlu məlumatı kompakt, sürətli şəkildə encode/decode etmək üçündür.

**Əsas xüsusiyyətlər:**
- **Schema-based:** `.proto` faylında message strukturları təyin edilir.
- **Code generation:** schema-dan hədəf dil üçün class/struct generasiya olunur (protoc compiler).
- **Binary wire format:** JSON-dan 3-10x kiçik, 20-100x sürətli parse.
- **Backward/forward compatibility:** schema-ya yeni field əlavə etmək köhnə client-ləri qırmır.
- **Cross-language:** C++, Java, Python, Go, Rust, PHP, C#, Ruby, Swift, Dart və s.

**İstifadə sahələri:**
- gRPC (default serialization)
- Microservice-lər arası kommunikasiya (yüksək trafikdə JSON əvəzi)
- Mobile app ↔ server (kiçik payload, aşağı battery)
- Storage format (Kafka topic, disk cache)
- Config files (BINARY versus text `.textproto`)

```
JSON:                    Protobuf:
{"name":"Ali","age":30}  0A 03 41 6C 69 10 1E
24 bytes                 7 bytes
```

**Versiyalar:**
- **proto2** (2008) — zəruri/isteğe bağlı fields (optional/required keyword-ları).
- **proto3** (2016) — sadələşdirilmiş, default "optional" (yoxdursa default dəyər), bütün dillərdə yaxşı dəstək.

## Necə İşləyir? (How does it work?)

### 1. .proto Schema Definition

```protobuf
// order.proto
syntax = "proto3";

package ecommerce.v1;

option php_namespace = "App\\Proto\\Ecommerce\\V1";
option php_metadata_namespace = "App\\Proto\\Ecommerce\\V1\\Meta";
option go_package = "example.com/proto/ecommerce/v1;ecommercev1";

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
  ORDER_STATUS_UNSPECIFIED = 0;   // required for proto3
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

### 2. Code Generation

```bash
# Install protoc
apt install protobuf-compiler   # Linux
brew install protobuf           # macOS

# Install PHP plugin for protoc
# (ships with Google's protobuf package)
composer require google/protobuf

# Generate PHP classes
protoc --php_out=./app/Proto \
       --proto_path=./protos \
       ./protos/ecommerce/v1/order.proto

# For gRPC-Web or gRPC server stubs:
protoc --php_out=./app/Proto \
       --grpc_out=./app/Proto \
       --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin \
       --proto_path=./protos \
       ./protos/ecommerce/v1/order.proto
```

Generated PHP usage:

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

// Serialize to binary
$binary = $order->serializeToString();
file_put_contents('/tmp/order.bin', $binary);

// Deserialize
$order2 = new Order();
$order2->mergeFromString($binary);
echo $order2->getCustomerEmail(); // ali@example.com
```

### 3. Wire Format

Protobuf wire format tək byte-dan ibarətdir: **tag** (field number + wire type) və sonra **payload**.

```
Tag byte:  [field_number << 3 | wire_type]
           Wire types:
             0 = Varint (int32, int64, uint32, uint64, bool, enum)
             1 = 64-bit (fixed64, double)
             2 = Length-delimited (string, bytes, embedded messages, repeated)
             5 = 32-bit (fixed32, float)

Example encoding of: Order{id=42, customer_email="Ali"}

Field 1 (id=42, varint):
  Tag:  (1 << 3) | 0 = 0x08
  Value: 42 = 0x2A
  Bytes: 08 2A

Field 2 (customer_email="Ali", length-delimited):
  Tag:  (2 << 3) | 2 = 0x12
  Length: 3 = 0x03
  Data: "Ali" = 0x41 0x6C 0x69
  Bytes: 12 03 41 6C 69

Total: 08 2A 12 03 41 6C 69  (7 bytes)
```

### Varint Encoding

Varint = variable-length integer encoding. Kiçik rəqəmlər az byte istifadə edir.

```
Encoding rule:
  - Each byte has 7 data bits + 1 continuation bit (MSB)
  - MSB=1 means "more bytes follow"
  - MSB=0 means "last byte"
  - Little-endian group order

Example: 300 = 0b100101100

Group into 7-bit chunks (from LSB):
  0101100  0000010

Add continuation bits:
  10101100  00000010   (first has MSB=1, last has MSB=0)
  0xAC      0x02

So 300 encoded as: AC 02  (2 bytes, vs 4 bytes for int32)

Negative numbers (int32/int64) use 10 bytes because sign extends.
Use sint32/sint64 with zigzag encoding for small negatives.
```

## Əsas Konseptlər (Key Concepts)

### Field Numbers & Schema Evolution

```protobuf
// V1
message User {
  int64 id = 1;
  string name = 2;
  string email = 3;
}

// V2 - SAFE changes:
message User {
  int64 id = 1;
  string name = 2;
  string email = 3;
  int32 age = 4;            // NEW field, new tag
  reserved 10 to 15;        // reserve for future
  reserved "old_field";     // reserve deleted names
}

// UNSAFE (breaks compatibility):
//   - Changing field number (tag) of existing field
//   - Changing type of existing field
//   - Reusing a tag number previously used
//   - Removing a required field (proto2)
```

**Rule of thumb:** `field_number` unique və dəyişməz olmalıdır. 1-15 arası number-lar 1 byte tag verir (istifadə tez-tez olunanlar üçün); 16-2047 arası 2 byte.

### Default Values (proto3)

```
No explicit optional/required in proto3. Field absent → default:
  int32/int64/uint32/uint64 = 0
  bool = false
  string = ""
  bytes = b""
  enum = 0 (first value)
  message = null (not present)
  repeated = []

In proto3.15+, `optional` keyword reintroduced for presence tracking:
  optional string middle_name = 5;  // can detect "set to empty string" vs "not set"
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
  google.protobuf.Timestamp created_at = 1;   // UTC nanoseconds
  google.protobuf.Duration  delivery_eta = 2; // seconds + nanos
  google.protobuf.StringValue coupon = 3;     // nullable string
}
```

### Protobuf vs JSON

```
              Protobuf          JSON
Size          ~3-10x smaller    Larger (text)
Speed         ~20-100x faster   Slow (text parsing)
Human-read    No                Yes
Schema        Required          Optional
Evolution     Structured        Ad-hoc
Tooling       protoc needed     Built-in everywhere
Debugging     Harder (binary)   Easy (curl)
Browser       Limited (gRPC-Web) Native

Use cases:
  Protobuf: microservices, mobile, high-throughput, strict contracts
  JSON:     public REST API, browser, debugging, ad-hoc integration
```

### Protobuf vs Avro

```
              Protobuf                     Avro
Schema        Static, compiled in          Stored with data or registry
Schema evo    Field numbers, reserved      Schema resolution at read time
Tooling       protoc                       avro-tools
Use case      RPC, service contracts       Big data, Kafka, Hadoop
Registry      Buf Schema Registry          Confluent Schema Registry
Wire format   Tag+value                    Binary, schema-dependent
```

Avro wire format daha kompakt ola bilər (schema məlumdur, field name/tag deyil), amma hər mesaj üçün schema lazımdır (registry ID ilə). Protobuf-da schema compile-time məlumdur.

### gRPC ilə Əlaqə

gRPC default olaraq protobuf istifadə edir. `.proto` faylında `service` definition server+client stub yaradır, message-lər RPC argument/return kimi istifadə olunur. HTTP/2 üzərində binary frame-lər göndərilir.

### Buf — Modern Protobuf Tooling

```bash
# Install buf
brew install bufbuild/buf/buf

# Lint proto files
buf lint

# Detect breaking changes
buf breaking --against '.git#branch=main'

# Format
buf format -w

# Generate code (buf.gen.yaml config)
buf generate
```

`buf.yaml` və `buf.gen.yaml` protoc-dan daha yaxşı idarəetmə verir, Buf Schema Registry-yə push edilə bilər (npm/maven kimi protobuf package manager).

## PHP/Laravel ilə İstifadə

### Install Protobuf PHP Runtime

```bash
# C extension version (faster, 5-10x):
pecl install protobuf
# Add to php.ini: extension=protobuf.so

# Pure PHP fallback:
composer require google/protobuf
```

### Protobuf as HTTP Request Format in Laravel

```php
// routes/api.php
Route::post('/orders', [OrderController::class, 'store'])
     ->middleware('protobuf');

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

### Controller with Protobuf

```php
// app/Http/Controllers/Api/OrderController.php
namespace App\Http\Controllers\Api;

use App\Models\Order as OrderModel;
use App\Proto\Ecommerce\V1\Order;
use App\Proto\Ecommerce\V1\OrderItem;
use App\Proto\Ecommerce\V1\OrderStatus;
use Google\Protobuf\Timestamp;
use Illuminate\Http\Request;
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
        $proto->setStatus(match($orderModel->status) {
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

    public function store(Request $request)
    {
        /** @var Order $proto */
        $proto = $request->input('proto');

        $order = OrderModel::create([
            'customer_email' => $proto->getCustomerEmail(),
            'total'          => $proto->getTotal(),
            'status'         => 'pending',
        ]);

        foreach ($proto->getItems() as $i) {
            $order->items()->create([
                'product_id' => $i->getProductId(),
                'quantity'   => $i->getQuantity(),
                'price'      => $i->getPrice(),
            ]);
        }

        return response()->noContent(201);
    }
}
```

### Protobuf in Kafka / Redis Queue (Compact Storage)

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

        // process $order
        logger()->info("Processing order #{$order->getId()}");
    }
}

// Dispatch with pre-serialized proto
$order = new Order();
$order->setId(42);
ProcessOrderProto::dispatch($order->serializeToString());
```

Bu yanaşma Redis/SQS payload ölçüsünü JSON-dan 3-5x azaldır, xüsusilə yüksək throughput queue-da.

### Convert Between Protobuf and JSON

```php
use Google\Protobuf\Internal\Message;

$order = new Order();
$order->setId(42);
$order->setCustomerEmail('ali@example.com');

// To JSON (useful for logging, debugging)
$json = $order->serializeToJsonString();
// {"id":"42","customerEmail":"ali@example.com"}

// From JSON
$order2 = new Order();
$order2->mergeFromJsonString($json);
```

### Schema Evolution Test

```php
// tests/Feature/ProtobufCompatibilityTest.php
namespace Tests\Feature;

use App\Proto\Ecommerce\V1\Order;
use Tests\TestCase;

class ProtobufCompatibilityTest extends TestCase
{
    public function test_old_client_can_read_new_server_response(): void
    {
        // Server adds a new field (e.g., discount_amount)
        // Old client's generated code doesn't know about it
        // But still deserializes successfully (ignores unknown fields)

        $newOrderBinary = $this->buildNewerVersionBinary();

        $oldOrder = new Order();
        $oldOrder->mergeFromString($newOrderBinary);

        $this->assertEquals(42, $oldOrder->getId());
        // unknown field data retained in `unknown fields` set,
        // will be preserved if re-serialized.
    }
}
```

## Interview Sualları (Q&A)

### 1. Protobuf niyə JSON-dan sürətlidir və kompaktdır?

**Cavab:**
- **Binary format:** rəqəmlər raw byte kimi, tekst kodlaşdırılmadan. "42" JSON-da 2 byte (ascii), varint-də 1 byte.
- **Varint encoding:** kiçik rəqəmlər az byte. 0-127 arası 1 byte.
- **No field names on wire:** JSON `{"customer_email":"x"}` — 17 byte field name. Protobuf yalnız **tag** (1-2 byte) yazır.
- **Schema compile-time:** parser deserialize edərkən field tapmaq üçün axtarmağa ehtiyac yoxdur — field_number ilə lookup.
- **No whitespace, no quotes, no brackets:** JSON-da hər object 2 byte `{}`, hər string 2 byte `""`, hər sahə arası `,`.
Nəticədə: real-world payload-lar 3-10x kiçik, parse 20-100x sürətli.

### 2. Field number niyə vacibdir?

**Cavab:** Field number wire format-ın əsasıdır — field name deyil, **tag** ilə identifikasiya. Əgər dəyişsəniz, köhnə client yanlış field oxuyacaq (data corruption). Qaydalar:
- Field number həmişə unique və dəyişməz.
- Sildiyiniz field-in nömrəsini `reserved` etmək lazımdır ki, səhvən təkrar istifadə olunmasın.
- 1-15 arası nömrələr 1 byte tag (sıx istifadə olunan sahələr üçün), 16-2047 arası 2 byte.
- 19000-19999 protobuf internal üçün rezerv olunub.

### 3. proto2 və proto3 fərqi?

**Cavab:**
- **proto2:** `required`, `optional`, `repeated` keyword-ları. `required` field olmasa, message invalid. Default dəyər təyin edilə bilər (`[default = 5]`).
- **proto3:** sadələşdirildi. `required` silindi (çox backward-compat problemi yaradırdı). Hamısı implicitly optional. Default dəyərlər dildə hard-coded (0, "", false). 3.15+ ilə `optional` keyword qayıtdı presence tracking üçün.
- **Enum:** proto3-də ilk value 0 olmalıdır (unspecified default).
Hazırda yeni layihələr üçün proto3 standartdır.

### 4. Schema evolution — backward və forward compatibility?

**Cavab:**
- **Backward compatible:** yeni kod köhnə data oxuya bilir.
- **Forward compatible:** köhnə kod yeni data oxuya bilir (unknown field-ləri görməməzdən gəlir).
Protobuf hər ikisini dəstəkləyir:
- Yeni field əlavə → köhnə kod ignore edir (forward).
- Silinən field → köhnə data-da dəyər hələ də mövcuddur, yeni kod ignore edir (backward).
- **Şərtlər:** field number dəyişməməli, type dəyişməməli (bəzi safe type change-lər var: int32→int64, uint32→uint64), tag silinmiş field `reserved` olmalıdır.

### 5. Varint encoding necə işləyir?

**Cavab:** Variable-length integer. Hər byte 7 data bit + 1 continuation bit (MSB). MSB=1 "daha byte var", MSB=0 "son byte". Kiçik rəqəm az byte. Nümunə: 300 = `0b100101100`. 7-bit chunk-lara bölünür (LSB-dən): `0101100`, `0000010`. Continuation bit əlavə olunur: `10101100 00000010` = `0xAC 0x02`. Beləliklə 300 → 2 byte (int32-in 4 baytı yerinə). **Problem:** negative int32 10 byte olur (sign-extension). Kiçik negative-lər üçün `sint32` (zigzag encoding) istifadə edilməlidir.

### 6. Protobuf vs Avro — hansını seçək?

**Cavab:**
- **Protobuf:** schema compile-time bilinir, kod generasiya olunur. RPC (gRPC), service contract-ları, mobile API üçün ideal. Static typing.
- **Avro:** schema runtime-da resolve edilir (schema registry). Big data (Kafka, Hadoop), data lake formatları üçün. Schema evolution daha gücludur (schema-aware compatibility check).
Seçim meyarı: **RPC/microservices → Protobuf**, **streaming/analytics pipeline → Avro**. Confluent ekosistemində Avro qalıb dominant, Kubernetes/cloud-native tools-da Protobuf.

### 7. gRPC-də niyə Protobuf default-dur?

**Cavab:** (1) Binary format HTTP/2 frame-lərinə təbii uyğundur, (2) Schema-dan avtomatik client+server stub generasiya — bütün dillərdə type-safe, (3) Compact wire format mobile və low-bandwidth-ə uyğundur, (4) Google-un daxili sistemlərində milyonlarla RPC üçün battle-tested. gRPC JSON-u da dəstəkləyir (gRPC-JSON Transcoding), amma performance dəyəri var.

### 8. Protobuf-u Laravel API-də necə istifadə edərsən?

**Cavab:** (1) `.proto` schema yaz, `protoc` ilə PHP class generasiya et. (2) Middleware qur — `Content-Type: application/x-protobuf` olan request-ləri binary kimi parse et. (3) Controller-də Eloquent model → Proto message-ə map et, binary response qaytar. (4) Use cases: mobile app backend (aşağı battery), high-throughput internal API, microservice-lər arası kommunikasiya. REST-dən migration zamanı eyni endpoint-ə `Accept` header əsasında ya JSON ya protobuf qaytarmaq olar.

### 9. Protobuf-da required olmadıqda, "əsas məlumat yoxdur" vəziyyətini necə yoxlayırıq?

**Cavab:** proto3-də default dəyərlər implicit qaytarılır — `""` string və `0` int fərqləndirilə bilmir "absent"-dan. Həll yolları:
1. **`optional` keyword (3.15+):** `optional string middle_name = 5;` — `hasMiddleName()` metodu yaranır.
2. **Wrapper types:** `google.protobuf.StringValue`, `Int32Value` — nullable versiyası.
3. **Business logic validation:** tətbiq səviyyəsində "empty string ⇒ invalid" qaydası qoy.
Enterprise API-da adətən wrapper types və ya optional istifadə olunur ki, "unset" fərqləndirilsin.

### 10. Protobuf debugging necə edilir? Binary-i necə oxuyuruq?

**Cavab:**
- **protoc decode:** `protoc --decode=Order order.proto < data.bin`
- **grpcurl:** gRPC üçün `grpcurl -d '{"id": 42}' host:port package.Service/GetOrder`
- **Convert to JSON:** `Message::serializeToJsonString()` — log-da oxunaqlı forma.
- **Wireshark:** gRPC dissector var.
- **Buf CLI:** `buf convert --type=ecommerce.v1.Order --from=bin --to=json data.bin`
Production-da debugging üçün JSON log, trace-də proto-nu JSON-a çevirib yaz. Binary olaraq production-da yalnız wire-da saxla.

## Best Practices

1. **Field number rezerv et silməzdən əvvəl** — `reserved 3, 5, 10 to 20;` və `reserved "old_name";` ilə gələcək səhvləri önlə.
2. **Enum-da 0 = UNSPECIFIED qoy** — proto3 default 0 olduğundan, "unset" vəziyyəti aydın olsun.
3. **Package və versioned API istifadə et** — `ecommerce.v1`, `ecommerce.v2` package-ları yan-yana yaşasın. Breaking change yeni package-da.
4. **Buf istifadə et protoc əvəzinə** — modern tooling, `buf breaking` ilə CI-da backward compatibility yoxla.
5. **Public API-da JSON offer et** — protobuf performant amma debugging çətin. Content negotiation ilə hər ikisini dəstəklə.
6. **Small field_numbers frequently-used üçün** — 1-15 arası 1 byte tag verir, ən çox istifadə olunan sahələrə ayır.
7. **Well-known types istifadə et** — `Timestamp`, `Duration`, `Any`, `Empty` — təkərin yenidən icadından qaç.
8. **C extension quraşdır (`pecl install protobuf`)** — pure PHP runtime 5-10x yavaş, production-da native extension.
9. **Schema-nı ayrı repo-da saxla** — monorepo yoxdursa, `protos/` repo-su, hər service artifact kimi import edir (npm, composer-də).
10. **Compatibility test yaz** — köhnə binary + yeni kod, yeni binary + köhnə kod — hər ikisi işləməlidir. CI-da bunu avtomatlaşdır.
