# Serializasiya Dərin (Java vs PHP)

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Serializasiya — obyekti disk/şəbəkə üçün byte axınına çevirməkdir. İki böyük istifadə var: **persistence** (cache, DB blob, session store) və **wire format** (HTTP body, RPC, message queue).

**Java**-da iki nəsil var. **Native Java Serialization** (`Serializable` interface) 1997-dən bəri var və indi zərərli sayılır — "Ən pis Java feature-i" deyirlər. Oracle onu aradan qaldırmaq istəyir (JEP 411, JEP 290). Müasir Java-da **Jackson (JSON)**, **Protobuf**, **Avro**, **MessagePack** istifadə olunur.

**PHP**-də də `serialize()`/`unserialize()` var və eyni problemləri var (deserialization attack). Yaxşısı `json_encode()`, Symfony Serializer, Laravel API Resources, Spatie Data, Protobuf extension-dır.

Bu fayl serializasiyanın bütün aspektlərini — təhlükəsizlik, schema evolution, performans, format seçimi — əhatə edir.

---

## Java-da istifadəsi

### 1) Native Java Serialization — Serializable

```java
import java.io.*;

public class User implements Serializable {
    private static final long serialVersionUID = 1L;

    private int id;
    private String name;
    private transient String password;          // serialize edilmir
    private String email;

    // getter/setter
}

// Serialize
try (ObjectOutputStream oos = new ObjectOutputStream(new FileOutputStream("user.ser"))) {
    User user = new User(1, "Ali", "secret", "a@x.com");
    oos.writeObject(user);
}

// Deserialize
try (ObjectInputStream ois = new ObjectInputStream(new FileInputStream("user.ser"))) {
    User user = (User) ois.readObject();
    // user.password == null (transient)
}
```

### 2) serialVersionUID — version mismatch

```java
public class User implements Serializable {
    // Əgər uid yoxdursa compiler avtomatik generate edir
    // (bütün field-lərə əsaslanaraq) — field əlavə edəndə dəyişir!
    private static final long serialVersionUID = 1L;

    private int id;
    private String name;
    // Yeni field əlavə ettiyimizi deyək:
    private String phone;     // köhnə data-da yoxdur → null olar
}
```

Mütləq əl ilə `serialVersionUID` təyin et — əks halda hər class dəyişikliyi "InvalidClassException" verər.

### 3) Externalizable — tam kontrol

```java
public class Point implements Externalizable {
    private int x, y;

    public Point() {}    // no-arg constructor ŞƏRTDIR

    @Override
    public void writeExternal(ObjectOutput out) throws IOException {
        out.writeInt(x);
        out.writeInt(y);
    }

    @Override
    public void readExternal(ObjectInput in) throws IOException {
        x = in.readInt();
        y = in.readInt();
    }
}
```

`Serializable` avtomatik reflection istifadə edir, `Externalizable` isə hər field-i əl ilə yazdırır — daha sürətli, amma boilerplate.

### 4) writeReplace, readResolve — custom object

```java
public class Singleton implements Serializable {
    public static final Singleton INSTANCE = new Singleton();
    private Singleton() {}

    // Deserialize-dən sonra həqiqi singleton-u qaytar
    private Object readResolve() {
        return INSTANCE;
    }
}

public class Secret implements Serializable {
    private String value;

    // Serialize öncəsi başqa obyekt yaz
    private Object writeReplace() {
        return new SerializedProxy(value);
    }
}
```

### 5) Deserialization attack — təhlükəli hissə

Native Java Serialization deserialize zamanı obyektin constructor-unu və `readObject()`-unu çağırır. Hücumçu xüsusi hazırlanmış byte stream ilə **arbitrary code execution** əldə edə bilər:

```java
// Apache Commons Collections, Spring, Hibernate kimi library-lər
// "gadget chain" təqdim edir — zəncir çağırışla RCE

// Məsələn classi: InvokerTransformer
// Deserialize zamanı Runtime.exec("rm -rf /") işə düşər
```

Məşhur hücumlar:
- WebLogic RCE (CVE-2015-4852)
- JBoss Seam (CVE-2013-2165)
- Apache Commons Collections (CVE-2015-6420)

### 6) Serialization filter — JEP 290

Java 9+ deserialize zamanı class filter qurmaq olar:

```java
// JVM səviyyəsində
// -Djdk.serialFilter=java.base/*;com.myapp.**;!*

// Programatik
ObjectInputFilter filter = ObjectInputFilter.Config.createFilter(
    "com.myapp.**;java.base/*;!*"
);

try (ObjectInputStream ois = new ObjectInputStream(input)) {
    ois.setObjectInputFilter(filter);
    Object obj = ois.readObject();
}

// Java 17+ Context-Specific Deserialization Filters
ObjectInputFilter.Config.setSerialFilterFactory(...);
```

**Best practice:** Ümumiyyətlə Java Serialization istifadə etmə. Lazım gələndə filter qur.

### 7) Jackson — JSON modern standartı

```xml
<dependency>
    <groupId>com.fasterxml.jackson.core</groupId>
    <artifactId>jackson-databind</artifactId>
    <version>2.17.0</version>
</dependency>
```

```java
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.annotation.*;

public record User(
    @JsonProperty("user_id") int id,
    @JsonProperty("full_name") String name,
    @JsonIgnore String password,
    String email,
    @JsonFormat(shape = JsonFormat.Shape.STRING, pattern = "yyyy-MM-dd")
    LocalDate birthday
) {}

ObjectMapper mapper = new ObjectMapper();
mapper.registerModule(new JavaTimeModule());
mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS);

// Serialize
User u = new User(1, "Ali", "secret", "a@x.com", LocalDate.of(1990, 1, 1));
String json = mapper.writeValueAsString(u);
// {"user_id":1,"full_name":"Ali","email":"a@x.com","birthday":"1990-01-01"}

// Deserialize
User back = mapper.readValue(json, User.class);

// Polymorphic — abstract tip
@JsonTypeInfo(use = JsonTypeInfo.Id.NAME, property = "type")
@JsonSubTypes({
    @JsonSubTypes.Type(value = Dog.class, name = "dog"),
    @JsonSubTypes.Type(value = Cat.class, name = "cat"),
})
sealed interface Animal permits Dog, Cat {}

record Dog(String name, String breed) implements Animal {}
record Cat(String name, boolean indoor) implements Animal {}

String json2 = mapper.writeValueAsString(new Dog("Rex", "Lab"));
// {"type":"dog","name":"Rex","breed":"Lab"}

Animal a = mapper.readValue(json2, Animal.class);   // Dog
```

### 8) Jackson — @JsonCreator

```java
public class Money {
    private final long amount;
    private final String currency;

    @JsonCreator
    public Money(
        @JsonProperty("amount") long amount,
        @JsonProperty("currency") String currency
    ) {
        this.amount = amount;
        this.currency = currency;
    }

    // getter-lər
}

// Java 17+ record-da avtomatik @JsonCreator
public record Money(long amount, String currency) {}
```

### 9) Jackson Tree Model və Streaming API

```java
// Tree model — dinamik JSON
String json = "{\"a\":{\"b\":[1,2,3]}}";
JsonNode root = mapper.readTree(json);
int first = root.get("a").get("b").get(0).asInt();   // 1

// Streaming — böyük fayl üçün yaddaş-dostu
try (JsonParser parser = mapper.getFactory().createParser(new File("huge.json"))) {
    if (parser.nextToken() == JsonToken.START_ARRAY) {
        while (parser.nextToken() == JsonToken.START_OBJECT) {
            User u = mapper.readValue(parser, User.class);
            process(u);    // hər element ayrı-ayrı
        }
    }
}
```

### 10) Schema evolution — add/rename/remove

JSON elastikdir — yeni field əlavə etmək köhnə consumer-ı qırmır:

```java
// V1
public record User(int id, String name) {}

// V2 — yeni field
public record User(int id, String name, String email) {}

// V1 client V2 JSON oxuyanda email bilinməz — ignore et
mapper.configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);

// Rename ilə backward compatibility
public record User(
    int id,
    @JsonAlias({"name", "full_name"}) String fullName     // köhnə field də işləsin
) {}
```

### 11) Protobuf — binary, schema-first

```protobuf
// user.proto
syntax = "proto3";
option java_package = "com.myapp.proto";

message User {
  int32 id = 1;
  string name = 2;
  string email = 3;
  optional string phone = 4;
}
```

```java
// Generated class
User user = User.newBuilder()
    .setId(1)
    .setName("Ali")
    .setEmail("a@x.com")
    .build();

byte[] bytes = user.toByteArray();             // binary
User back = User.parseFrom(bytes);

// JSON view
String json = JsonFormat.printer().print(user);
```

**Schema evolution** Protobuf-un gücüdür:
- Yeni field əlavə et (optional olur default) — köhnə client ignore edər
- Field nömrəsi heç vaxt dəyişməsin
- Field silinmişsə `reserved 4;` qoy

### 12) Kryo — JVM fast binary

```xml
<dependency>
    <groupId>com.esotericsoftware</groupId>
    <artifactId>kryo</artifactId>
    <version>5.6.0</version>
</dependency>
```

```java
Kryo kryo = new Kryo();
kryo.register(User.class);

try (Output output = new Output(new FileOutputStream("user.bin"))) {
    kryo.writeObject(output, user);
}

try (Input input = new Input(new FileInputStream("user.bin"))) {
    User back = kryo.readObject(input, User.class);
}
```

Kryo Java Serialization-dan 5-10x sürətli, 3-5x kiçik. Yalnız JVM arası — dil-lə bağlıdır.

### 13) Avro — schema evolution master

```java
Schema schema = new Schema.Parser().parse("""
    {
      "type": "record",
      "name": "User",
      "fields": [
        {"name": "id", "type": "int"},
        {"name": "name", "type": "string"},
        {"name": "email", "type": ["null", "string"], "default": null}
      ]
    }
""");

GenericRecord user = new GenericData.Record(schema);
user.put("id", 1);
user.put("name", "Ali");
user.put("email", "a@x.com");

// Binary serialize
DatumWriter<GenericRecord> writer = new GenericDatumWriter<>(schema);
ByteArrayOutputStream out = new ByteArrayOutputStream();
Encoder encoder = EncoderFactory.get().binaryEncoder(out, null);
writer.write(user, encoder);
encoder.flush();
byte[] bytes = out.toByteArray();
```

Avro Kafka ekosistemində standart-dır — Schema Registry ilə evolution təmin olunur.

### 14) Binary vs JSON — nə vaxt nə?

| Meyar | JSON (Jackson) | Protobuf | Avro | Kryo |
|---|---|---|---|---|
| Oxunaqlıq | var | yox | yox | yox |
| Schema tələbi | yox | var (.proto) | var | yox |
| Dil dəstəyi | hər dil | 30+ dil | 20+ dil | JVM only |
| Ölçü | böyük | kiçik | kiçik | çox kiçik |
| Sürət | orta | sürətli | sürətli | ən sürətli |
| Evolution | zəif | yaxşı | ən yaxşı | zəif |
| API boundary | yaxşı | yaxşı (gRPC) | Kafka | cache only |

**İstifadə halları:**
- Public REST API → JSON
- gRPC microservice → Protobuf
- Kafka pipeline → Avro
- Cache (Redis) → Kryo / Protobuf
- Config file → JSON/YAML

---

## PHP-də istifadəsi

### 1) Native serialize()/unserialize()

```php
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

$user = new User(1, 'Ali', 'a@x.com');

// Serialize
$data = serialize($user);
// O:4:"User":3:{s:2:"id";i:1;s:4:"name";s:3:"Ali";s:5:"email";s:7:"a@x.com";}

file_put_contents('/tmp/user.ser', $data);

// Deserialize
$restored = unserialize(file_get_contents('/tmp/user.ser'));
echo $restored->name;    // 'Ali'
```

### 2) Magic metodlar — __sleep, __wakeup

```php
class Session
{
    private ?\PDO $db = null;    // resource — serialize edilə bilməz
    public string $userId;
    public array $cart;

    public function __sleep(): array
    {
        // Hansı property-lər serialize ediləcək
        return ['userId', 'cart'];
    }

    public function __wakeup(): void
    {
        // Deserialize-dən sonra çağırılır — resource-ları yenilə
        $this->db = new \PDO('mysql:host=localhost', 'user', 'pass');
    }
}
```

### 3) __serialize və __unserialize (PHP 7.4+)

Köhnə `__sleep`/`__wakeup` bəzi halları (read-only property) idarə etmirdi. Yeni API daha güclüdür:

```php
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}

    public function __serialize(): array
    {
        // İstənilən array qaytar
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => 2,
        ];
    }

    public function __unserialize(array $data): void
    {
        // Constructor çağırılmır — property-ləri biz təyin edirik
        // readonly property-yə bir dəfə yazmaq olar
        (function () use ($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
        })->call($this);
    }
}
```

### 4) Deserialization attack — PHP-də də təhlükəli

PHP `unserialize()` magic metodları avtomatik çağırır. Hücumçu xüsusi hazırlanmış string-lə RCE əldə edə bilər:

```php
// Hədəf class:
class Logger
{
    public string $logFile;

    public function __destruct()
    {
        file_put_contents($this->logFile, 'log');    // arbitrary file write!
    }
}

// Hücumçu:
$payload = serialize(new Logger);
// Modify: logFile = '/var/www/html/shell.php'
$evil = 'O:6:"Logger":1:{s:7:"logFile";s:24:"/var/www/html/shell.php";}';
unserialize($evil);    // __destruct işə düşür, fayl yaranır!
```

### 5) Təhlükəsiz unserialize — allowed_classes

```php
// Yalnız müəyyən class-lar
$data = unserialize($input, ['allowed_classes' => [User::class, Order::class]]);

// Heç bir class (safe)
$data = unserialize($input, ['allowed_classes' => false]);

// Best: unserialize istifadə etmə, JSON seç
```

### 6) JSON — json_encode və json_decode

```php
$user = ['id' => 1, 'name' => 'Ali', 'email' => 'a@x.com'];
$json = json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Deserialize — assoc array
$back = json_decode($json, true);

// Deserialize — stdClass
$obj = json_decode($json);

// Xəta yoxlama
$decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
// Xəta halında JsonException atılır
```

### 7) JsonSerializable interface

```php
final class Money implements \JsonSerializable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => sprintf('%.2f %s', $this->amount / 100, $this->currency),
        ];
    }
}

echo json_encode(new Money(1000, 'USD'));
// {"amount":1000,"currency":"USD","formatted":"10.00 USD"}
```

### 8) Symfony Serializer component

```bash
composer require symfony/serializer symfony/property-access symfony/property-info
```

```php
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final class User
{
    public function __construct(
        #[Groups(['public', 'admin'])]
        public int $id,

        #[SerializedName('full_name')]
        #[Groups(['public', 'admin'])]
        public string $name,

        #[Groups(['admin'])]    // yalnız admin group-da
        public string $email,

        #[Groups(['admin'])]
        public string $role,
    ) {}
}

$serializer = new Serializer(
    [new ObjectNormalizer()],
    [new JsonEncoder()]
);

$user = new User(1, 'Ali', 'a@x.com', 'admin');

// Public view
echo $serializer->serialize($user, 'json', ['groups' => 'public']);
// {"id":1,"full_name":"Ali"}

// Admin view
echo $serializer->serialize($user, 'json', ['groups' => 'admin']);
// {"id":1,"full_name":"Ali","email":"a@x.com","role":"admin"}

// Deserialize
$restored = $serializer->deserialize($json, User::class, 'json');
```

### 9) Laravel API Resource

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->when($request->user()?->isAdmin(), $this->email),
            'created_at' => $this->created_at->toIso8601String(),
            'orders'     => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}

// Controller-də
public function show(User $user): UserResource
{
    return new UserResource($user->load('orders'));
}

// Collection
public function index(): AnonymousResourceCollection
{
    return UserResource::collection(User::paginate(20));
}
```

### 10) Laravel Arrayable və Jsonable

```php
namespace App\Models;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

final class Cart implements Arrayable, Jsonable
{
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {}

    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'count' => count($this->items),
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
```

### 11) Spatie/Laravel-data — modern DTO

```bash
composer require spatie/laravel-data
```

```php
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\{Email, Min, Required};
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class UserData extends Data
{
    public function __construct(
        public int $id,

        #[Required, Min(3)]
        public string $fullName,

        #[Required, Email]
        public string $email,

        #[MapOutputName('registered_at')]
        public ?\DateTime $createdAt = null,
    ) {}
}

// Deserialize (HTTP request → DTO)
$data = UserData::from([
    'id' => 1,
    'full_name' => 'Ali',      // snake_case → fullName
    'email' => 'a@x.com',
]);

// Validation avtomatik
$validated = UserData::validate($request->all());

// Serialize
return response()->json($data);   // {"id":1,"full_name":"Ali",...}

// Collection
$users = UserData::collect(User::all());
```

### 12) google/protobuf PHP

```bash
# C extension (performans üçün)
pecl install protobuf

# Və ya pure PHP
composer require google/protobuf
```

```protobuf
// user.proto
syntax = "proto3";
message User {
  int32 id = 1;
  string name = 2;
  string email = 3;
}
```

```php
// Generated class
$user = new User();
$user->setId(1);
$user->setName('Ali');
$user->setEmail('a@x.com');

$bytes = $user->serializeToString();    // binary

$restored = new User();
$restored->mergeFromString($bytes);
echo $restored->getName();    // 'Ali'

// JSON
$json = $user->serializeToJsonString();
$restored2 = new User();
$restored2->mergeFromJsonString($json);
```

### 13) msgpack extension

```bash
pecl install msgpack
```

```php
$data = ['id' => 1, 'name' => 'Ali'];
$packed = msgpack_pack($data);      // binary, JSON-dan 30% kiçik
$back = msgpack_unpack($packed);
```

Redis cache üçün msgpack JSON-dan sürətli və kiçikdir.

### 14) Real API resursu — versioned

```php
namespace App\Http\Resources\V2;

final class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            // V1: 'name', V2: 'first_name' + 'last_name'
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            // Yeni field V2-də
            'avatar_url' => $this->avatar_url ?? null,
        ];
    }
}

// Route
Route::get('/api/v1/users/{user}', fn(User $u) => new V1\UserResource($u));
Route::get('/api/v2/users/{user}', fn(User $u) => new V2\UserResource($u));
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Native serialize | `Serializable` (deprecated tendency) | `serialize()` / `unserialize()` |
| Security issue | Deserialization attack (JEP 290 filter) | Object injection (allowed_classes filter) |
| Modern default | Jackson JSON | `json_encode` / Symfony / Laravel |
| Schema-first binary | Protobuf (gRPC), Avro (Kafka) | google/protobuf extension |
| Java-only binary | Kryo | Yoxdur |
| Polimorfik JSON | `@JsonTypeInfo`, `@JsonSubTypes` | Manual discriminator |
| Field rename | `@JsonProperty("...")`, `@JsonAlias` | `#[SerializedName]`, Spatie Mapper |
| Ignore field | `@JsonIgnore`, `transient` | `#[Ignore]`, `__sleep` |
| Null handling | `@JsonInclude(NON_NULL)` | `JSON_UNESCAPED_UNICODE` flags |
| Date format | `@JsonFormat` | Manual format |
| DTO validation | Jackson + Bean Validation | Spatie Data, Form Request |
| API views | Jackson @JsonView | Laravel Resource, Symfony Groups |
| Streaming parse | `JsonParser` (SAX-like) | JsonMachine library |
| Tree model | `JsonNode` | `stdClass` / assoc array |
| Version compatibility | `serialVersionUID` | Manual (version field) |

---

## Niyə belə fərqlər var?

**Java Serialization — tarixi səhv.** Java 1997-də `Serializable` gətirdi — məqsəd RMI (Remote Method Invocation) idi. Amma dizayn absurddu: reflection ilə bütün field-ləri oxuyur, constructor-u bypass edir, `readObject()` istənilən kodu run edə bilər. Bu 20 il sonra ciddi security problem-ə çevrildi. Oracle JEP 411 ilə onu deprecate etməyə başladı.

**PHP serialize — PHP-nin həm rahatlığı, həm zəifliyi.** `serialize()` sadə və sürətlidir — session storage üçün ideal. Amma eyni məntiqlə (magic metod avtomatik çağırılır) object injection hücumu mümkündür. Hər CTF-də "unsafe `unserialize()`" klassik mövzu-dur.

**JSON — ümumi dil.** Həm Java, həm PHP-də modern default JSON-dur — çünki dil/platform bağımsız, oxunaqlı, schema-siz işləyir. REST API, config fayl, log format — hər yerdə JSON.

**Binary protokol vəzifəsi — microservice performance.** Binary (Protobuf, Avro) 10x kiçik və sürətlidir. Java-nın gRPC/Kafka ekosistemi güclüdür. PHP-də bu az istifadə olunur — çünki PHP əsasən HTTP API qatıdır, binary RPC çox görünməz.

**Polymorphic serialization.** Java-da `@JsonTypeInfo` ilə discriminator field avtomatik serialize olur. PHP-də bu manuell yazılır — hər Resource/Normalizer özü idarə edir.

**Ekosistem fərqi.** Java-da Jackson praktik monopoliya-dır — Spring, Quarkus, Micronaut default Jackson. PHP-də parçalanma var — Symfony Serializer, Laravel Resource, Spatie Data, hər project öz seçimini edir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `Serializable` / `Externalizable` interface
- `serialVersionUID` — version mismatch detection
- `transient` keyword
- `writeReplace` / `readResolve` — proxy pattern
- `ObjectInputFilter` (JEP 290) — class filter
- Kryo — JVM-only fast binary
- Apache Avro Java — Kafka native
- Jackson `@JsonTypeInfo` + `@JsonSubTypes` polymorphic
- Jackson `@JsonView` — view groups
- Jackson Streaming API (`JsonParser`, `JsonGenerator`)
- gRPC + Protobuf first-class support
- Bean Validation (`@NotNull`, `@Size`) + Jackson integration

**Yalnız PHP-də:**
- `__sleep` / `__wakeup` magic metodları
- `__serialize` / `__unserialize` (PHP 7.4+)
- `JsonSerializable` interface
- Laravel API Resource — request-aware conditional fields (`$this->when(...)`)
- Spatie Data — PHP attribute ilə DTO + validation + casting
- Symfony Serializer `#[Groups]` — attribute-based view
- Form Request — validate-dən sonra avtomatik inject
- `msgpack` extension — sürətli binary
- `igbinary` extension — PHP serialize əvəzedici (3x sürətli)
- `unserialize()` ilə `allowed_classes` filter
- `JSON_THROW_ON_ERROR` — flag-based error handling
- Higher-order resource collections (`UserResource::collection(...)`)

---

## Best Practices

**Java:**
- Native `Serializable` ISTIFADƏ ETMƏ — Jackson və ya Protobuf seç
- Əgər `Serializable` istifadə edirsənsə, mütləq `ObjectInputFilter` qur
- `serialVersionUID` daim əl ilə təyin et — default-a güvənmə
- `transient` ilə sensitive data (password, token) işarələ
- Jackson `ObjectMapper` singleton — hər request-də yaratma (thread-safe, bahalıdır)
- `FAIL_ON_UNKNOWN_PROPERTIES = false` — schema evolution üçün
- Polymorphic-də `@JsonTypeInfo` discriminator field `type` qoy
- Protobuf schema evolution üçün field nömrəsini heç vaxt yenidən istifadə etmə
- `@JsonCreator` record-larda avtomatik — əl ilə yazma

**PHP:**
- `unserialize()` untrusted data ilə ASLA — və ya `allowed_classes` ilə filter et
- Session storage üçün `igbinary` serializer — sürətli və kiçik
- API üçün `json_encode` + `JSON_THROW_ON_ERROR` flag
- Laravel-də API Resource istifadə et — model direct qaytarma (`Internal structure leak`)
- Symfony-də `#[Groups]` ilə view səviyyəsi ayır (public/admin)
- Spatie Data DTO-ları input validation və output serialization üçün
- Redis cache-də msgpack və ya igbinary — JSON-dan 2x sürətli
- Protobuf microservice arasında — JSON HTTP ilə sərhəd üçün
- Schema evolution: yeni field-i həmişə nullable/default-lı əlavə et

---

## Yekun

Java serializasiya iki dövrlü: köhnə `Serializable` (security problem, tendency to deprecate) və yeni (Jackson JSON, Protobuf binary, Avro Kafka). Jackson praktik monopoliya-dır, bütün annotation-lar (`@JsonProperty`, `@JsonIgnore`, `@JsonTypeInfo`, `@JsonAlias`) JSON ekosisteminin standartıdır. gRPC və Kafka kimi production-kritik sistemlərdə binary format əsasdır.

PHP-də `serialize()/unserialize()` session üçün istifadə olunur (təhlükəsiz olsa — trusted data only), amma API üçün `json_encode` standart-dır. Framework-lər — Symfony Serializer (#[Groups], attribute-based), Laravel API Resource (`$this->when()` ilə conditional), Spatie Data (validation + cast + transform) zəngin seçim verir. Binary format (Protobuf, msgpack) extension ilə mümkündür, amma yayılma Java-dakı qədər deyil.

Hər iki dildə eyni xətalar var — **deserialization attack**. Trusted data, schema-first yanaşma, explicit allow-list — bu qaydaları həm Java, həm PHP-də nəzərə almaq lazımdır. Format seçimi isə use-case-ə bağlıdır: public API JSON, RPC Protobuf, Kafka Avro, cache binary (Kryo/igbinary/msgpack).
