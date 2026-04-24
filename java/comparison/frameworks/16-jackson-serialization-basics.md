# Jackson serialization əsasları: Spring vs Laravel

> **Seviyye:** Beginner ⭐

## Giriş

Hər REST API JSON ilə işləyir. Java obyektini JSON-a çevirmək (**serialization**) və JSON-u Java obyektinə çevirmək (**deserialization**) üçün Spring Boot default olaraq **Jackson** kütübxanəsini istifadə edir. Jackson bilməsən, bəsit görünən şeylər çətinlik yaradır: tarix formatı düzgün deyil, field adı snake_case yerinə camelCase çıxır, null field JSON-da görünür.

Laravel-də bu iş Eloquent model-in `toArray()` və `$casts`/`$hidden` xüsusiyyətləri ilə, ya da API Resource sinifləri ilə həll olunur. Bu faylda Jackson-un əsas annotasiyaları, Spring Boot ilə Jackson konfiqurasiyası və Laravel-in yanaşması müqayisə olunur.

## Spring/Java-də

### Jackson nədir?

Jackson Java-nın ən məşhur JSON kütübxanəsidir (`com.fasterxml.jackson.core`). Üç hissədən ibarətdir:
- **Core** (`JsonParser`, `JsonGenerator`) - low-level JSON oxu/yaz.
- **Databind** (`ObjectMapper`) - obyekt ↔ JSON çevirmə.
- **Annotations** - davranışı configure etmək (`@JsonProperty`, `@JsonIgnore`).

Spring Boot `spring-boot-starter-web` ilə Jackson-u avtomatik əlavə edir. Controller-də obyekt qaytaranda Jackson onu JSON-a çevirir - heç bir əlavə kod lazım deyil.

### ObjectMapper - əsas API

```java
import com.fasterxml.jackson.databind.ObjectMapper;

ObjectMapper mapper = new ObjectMapper();

// Obyekt -> JSON (serialize)
User user = new User(1L, "Orxan", "a@b.com");
String json = mapper.writeValueAsString(user);
// {"id":1,"name":"Orxan","email":"a@b.com"}

// JSON -> Obyekt (deserialize)
String jsonInput = "{\"id\":1,\"name\":\"Orxan\"}";
User parsed = mapper.readValue(jsonInput, User.class);

// Pretty print
String pretty = mapper.writerWithDefaultPrettyPrinter().writeValueAsString(user);
// {
//   "id" : 1,
//   "name" : "Orxan"
// }
```

Spring-də `ObjectMapper` avtomatik Bean kimi yaranır, `@Autowired` ilə inject etmək olar:

```java
@Service
@RequiredArgsConstructor
public class SomeService {
    private final ObjectMapper objectMapper;

    public void example() throws Exception {
        String json = objectMapper.writeValueAsString(user);
    }
}
```

### @JsonProperty - field adi dəyişdirmək

```java
public class User {
    private Long id;

    @JsonProperty("full_name")  // JSON-da full_name kimi görünür
    private String name;

    @JsonProperty("email_address")
    private String email;
}

// JSON output:
// {
//   "id": 1,
//   "full_name": "Orxan",
//   "email_address": "a@b.com"
// }
```

### @JsonIgnore - field gizlətmək

```java
public class User {
    private Long id;
    private String name;
    private String email;

    @JsonIgnore  // JSON-da görünməyəcək
    private String password;

    @JsonIgnore
    private String internalToken;
}

// JSON-da password və internalToken yoxdur
```

**Write-only / Read-only:**

```java
public class User {
    private Long id;
    private String name;

    // Yalnız request-də qəbul olunur, response-da görünməz
    @JsonProperty(access = JsonProperty.Access.WRITE_ONLY)
    private String password;

    // Yalnız response-da görünür, request-də ignore olunur
    @JsonProperty(access = JsonProperty.Access.READ_ONLY)
    private LocalDateTime createdAt;
}
```

### @JsonFormat - tarix formatı

```java
public class User {
    private Long id;
    private String name;

    @JsonFormat(pattern = "yyyy-MM-dd")
    private LocalDate birthDate;

    @JsonFormat(pattern = "yyyy-MM-dd HH:mm:ss", timezone = "Asia/Baku")
    private LocalDateTime createdAt;
}

// JSON:
// {
//   "id": 1,
//   "name": "Orxan",
//   "birthDate": "1995-03-15",
//   "createdAt": "2026-04-24 14:30:00"
// }
```

**Default davranış (annotasiya yoxdur):**

```java
// Default olaraq LocalDateTime JSON-da belə görünür:
// "createdAt": [2026, 4, 24, 14, 30, 0]
// (array kimi - çirkin!)
```

Buna görə `jackson-datatype-jsr310` və `JavaTimeModule` konfiqurasiyası lazımdır - aşağıda izah edilir.

### @JsonInclude - null field-ləri gizlətmək

```java
@JsonInclude(JsonInclude.Include.NON_NULL)
public class User {
    private Long id;
    private String name;
    private String email;
    private String phone;  // null-durusa JSON-da görünməz
}

User u = new User();
u.setId(1L);
u.setName("Orxan");
// email, phone = null

// JSON: {"id":1,"name":"Orxan"}
// email və phone YOXDUR (null olduğuna görə)
```

**Variantlar:**
- `Include.ALWAYS` - default, hər zaman daxil et (null belə)
- `Include.NON_NULL` - null olan field-ləri xaric et
- `Include.NON_EMPTY` - null, boş string, boş collection xaric et
- `Include.NON_DEFAULT` - default dəyərli field-ləri xaric et (0, false, null)

### @JsonCreator və @JsonProperty - custom constructor

Jackson default parametrsiz constructor və setter-lər ilə obyekt yaradır. Immutable class-lar (Record, final field) üçün custom constructor lazımdır:

```java
public class UserDto {
    private final Long id;
    private final String name;

    @JsonCreator
    public UserDto(
            @JsonProperty("id") Long id,
            @JsonProperty("name") String name) {
        this.id = id;
        this.name = name;
    }

    public Long getId() { return id; }
    public String getName() { return name; }
}

// Jackson bu constructor-u istifadə edərək JSON-dan obyekt yaradır
```

### Record + Jackson - Java 16+

Record Jackson ilə default olaraq işləyir (Jackson 2.12+):

```java
public record UserDto(Long id, String name, String email) {}

ObjectMapper mapper = new ObjectMapper();

// Serialize
UserDto dto = new UserDto(1L, "Orxan", "a@b.com");
String json = mapper.writeValueAsString(dto);
// {"id":1,"name":"Orxan","email":"a@b.com"}

// Deserialize
String input = "{\"id\":1,\"name\":\"Orxan\",\"email\":\"a@b.com\"}";
UserDto parsed = mapper.readValue(input, UserDto.class);
// @JsonCreator lazım deyil - avtomatik işləyir
```

Record DTO + Jackson - ən təmiz kombinasiyadır.

### @JsonIgnoreProperties - nəməlum field-ləri ignore et

```java
// Client extra field göndərsə, Jackson default Exception atır
// Bunun qarşısını almaq üçün:

@JsonIgnoreProperties(ignoreUnknown = true)
public class UserDto {
    private Long id;
    private String name;
    // extraField JSON-da olsa belə ignore olunur
}
```

Global konfigure etmək olar:

```java
@Bean
public ObjectMapper objectMapper() {
    ObjectMapper mapper = new ObjectMapper();
    mapper.configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);
    return mapper;
}
```

### application.yml - Spring Boot Jackson konfiqurasiyası

Kod yazmadan, `application.yml`-də Jackson-u konfigure etmək olar:

```yaml
spring:
  jackson:
    # snake_case formatinda JSON (full_name not fullName)
    property-naming-strategy: SNAKE_CASE

    # Null field-leri JSON-da daxil etme
    default-property-inclusion: non_null

    # Tarix-i timestamp kimi yazma (default), ISO string kimi yaz
    serialization:
      write-dates-as-timestamps: false
      indent-output: true

    deserialization:
      fail-on-unknown-properties: false
      accept-single-value-as-array: true

    # Tarix/zaman formatı
    date-format: yyyy-MM-dd HH:mm:ss
    time-zone: Asia/Baku
```

**Effekt:** Bütün controller-də JSON avtomatik bu qaydalarla yaradılır.

### LocalDateTime problem - jackson-datatype-jsr310

Java 8+ `LocalDate`, `LocalDateTime`, `LocalTime` - Jackson default-da yaxşı işləmir.

**Problem:**

```java
// Default
public class User {
    private LocalDateTime createdAt;
}

// JSON çıxış:
// "createdAt": [2026, 4, 24, 14, 30]  // ARRAY!
```

**Həlli: jackson-datatype-jsr310:**

Spring Boot-da `spring-boot-starter-web` ilə bu dependency avtomatik yüklənir. Amma `JavaTimeModule` register olmalıdır:

```xml
<dependency>
    <groupId>com.fasterxml.jackson.datatype</groupId>
    <artifactId>jackson-datatype-jsr310</artifactId>
</dependency>
```

```java
@Configuration
public class JacksonConfig {

    @Bean
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();
        mapper.registerModule(new JavaTimeModule());
        mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS);
        return mapper;
    }
}
```

İndi:
```json
"createdAt": "2026-04-24T14:30:00"
```

Spring Boot avtomatik bu konfiqurasiyanı edir - əksər layihədə əlavə kod lazım olmur.

### @JsonView - fərqli görüntülər

```java
public class Views {
    public static class Public {}
    public static class Internal extends Public {}
}

public class User {
    @JsonView(Views.Public.class)
    private Long id;

    @JsonView(Views.Public.class)
    private String name;

    @JsonView(Views.Internal.class)
    private String email;       // yalnız Internal view-də

    @JsonView(Views.Internal.class)
    private String phone;
}

@RestController
public class UserController {
    @GetMapping("/public/{id}")
    @JsonView(Views.Public.class)
    public User getPublic(@PathVariable Long id) {
        return userService.findById(id);  // yalnız id, name görünür
    }

    @GetMapping("/internal/{id}")
    @JsonView(Views.Internal.class)
    public User getInternal(@PathVariable Long id) {
        return userService.findById(id);  // hamısı görünür
    }
}
```

Detallar üçün `27-serialization.md` faylına bax.

### Custom Serializer/Deserializer

Standart dəyişdirmələr kifayət etməyəndə:

```java
// BigDecimal-i "100.00 AZN" formatinda serialize
public class MoneySerializer extends JsonSerializer<BigDecimal> {
    @Override
    public void serialize(BigDecimal value, JsonGenerator gen,
                          SerializerProvider serializers) throws IOException {
        gen.writeString(value.setScale(2, RoundingMode.HALF_UP) + " AZN");
    }
}

public class Product {
    private String name;

    @JsonSerialize(using = MoneySerializer.class)
    private BigDecimal price;
}

// JSON: {"name":"Laptop","price":"1500.00 AZN"}
```

### Polymorphic serialization - @JsonTypeInfo

Inheritance olan obyektləri JSON-a çevirmək:

```java
@JsonTypeInfo(use = JsonTypeInfo.Id.NAME, property = "type")
@JsonSubTypes({
    @JsonSubTypes.Type(value = EmailNotif.class, name = "email"),
    @JsonSubTypes.Type(value = SmsNotif.class, name = "sms")
})
public abstract class Notification {
    private String message;
}

public class EmailNotif extends Notification {
    private String subject;
}

// JSON:
// {"type":"email", "message":"Hi", "subject":"Welcome"}
// {"type":"sms", "message":"Hi"}
```

Detallar üçün `27-serialization.md` faylına bax.

### Circular reference - infinite loop problemi

JPA bidirectional əlaqə Jackson-u sonsuz dövrə salır:

```java
@Entity
public class User {
    @Id
    private Long id;
    private String name;

    @OneToMany(mappedBy = "user")
    private List<Order> orders;
}

@Entity
public class Order {
    @Id
    private Long id;

    @ManyToOne
    private User user;
}

// Jackson user-i serialize edir -> orders -> hər order-də user -> orders -> ...
// StackOverflowError ya da infinite JSON!
```

**Həll 1: @JsonManagedReference / @JsonBackReference:**

```java
public class User {
    @OneToMany(mappedBy = "user")
    @JsonManagedReference  // "parent" tərəfi - serialize olunur
    private List<Order> orders;
}

public class Order {
    @ManyToOne
    @JsonBackReference  // "child" tərəfi - serialize olunmur
    private User user;
}
```

**Həll 2: @JsonIgnore bir tərəfdə:**

```java
public class Order {
    @ManyToOne
    @JsonIgnore
    private User user;
}
```

**Həll 3 (EN IDEAL): DTO istifadə et** - Entity-ni birbaşa JSON-a çevirmə. `84-dto-vs-entity-separation.md` faylına bax.

## Laravel/PHP-də

Laravel-də Jackson ekvivalenti **PHP-nin daxili `json_encode()`** və Eloquent-in built-in JSON xüsusiyyətləridir.

### Model toArray() / toJson()

Eloquent model avtomatik JSON-a çevrilə bilər:

```php
class User extends Model
{
    protected $fillable = ['name', 'email'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];
}

$user = User::find(1);
$array = $user->toArray();
$json = $user->toJson();
// və ya
return response()->json($user);
```

### $hidden / $visible - @JsonIgnore qarşılığı

```php
class User extends Model
{
    // Bu field-lər JSON-da görünməz (@JsonIgnore kimi)
    protected $hidden = [
        'password',
        'remember_token',
        'activation_code',
    ];

    // Və ya əksinə - yalnız bu field-lər görünür
    // protected $visible = ['id', 'name', 'email'];
}

// Dinamik dəyişdirmək
$user->makeVisible('password');    // Password-u göstər
$user->makeHidden('email');        // Email-i gizlət
```

### $casts - @JsonFormat qarşılığı

```php
class Product extends Model
{
    protected $casts = [
        // Tarix
        'created_at' => 'datetime:Y-m-d H:i:s',
        'published_at' => 'datetime',

        // Tip
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'quantity' => 'integer',

        // Kompleks
        'metadata' => 'array',            // JSON sütun -> array
        'settings' => AsArrayObject::class,
        'status' => ProductStatus::class, // PHP enum
    ];
}

$product = Product::find(1);
echo $product->is_active;    // true (boolean, not "1")
echo $product->price;        // "100.00" (string, 2 decimal)
```

### $appends - computed field

```php
class User extends Model
{
    // Bu method virtual attribute kimi işləyir
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // JSON-a əlavə et
    protected $appends = ['full_name'];
}

$user->toJson();
// {"id":1,"first_name":"Orxan","last_name":"Ali","full_name":"Orxan Ali"}
```

Spring-də bu DTO-nun computed field-i kimidir:
```java
public record UserDto(Long id, String firstName, String lastName) {
    public String fullName() {
        return firstName + " " + lastName;
    }
}
```

### snake_case default - Spring camelCase default

```php
// Laravel default - DB sütun adı = JSON field adı (snake_case)
$user->first_name  // DB-də first_name
// JSON: {"first_name":"Orxan"}
```

```java
// Spring default - Java field adı (camelCase)
private String firstName;
// JSON: {"firstName":"Orxan"}

// snake_case-ə çevirmək üçün:
// application.yml: spring.jackson.property-naming-strategy: SNAKE_CASE
```

### API Resource - custom JSON strukturu

`84-dto-vs-entity-separation.md` faylında ətraflı - Laravel-in DTO-su.

```php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at->toIso8601String(),
            'is_new' => $this->created_at->isAfter(now()->subDays(7)),
        ];
    }
}
```

## Əsas fərqlər

| Xüsusiyyət | Spring (Jackson) | Laravel |
|---|---|---|
| **Field gizlətmək** | `@JsonIgnore` | `$hidden` massivi |
| **Field adı dəyişdirmək** | `@JsonProperty("new_name")` | DB sütun adı = JSON adı |
| **Tarix formatı** | `@JsonFormat(pattern=...)` | `$casts = [... => 'datetime:Y-m-d']` |
| **Null field xaric** | `@JsonInclude(NON_NULL)` | Laravel null-u göndərir default |
| **Computed field** | Method in record/class | `$appends` + `getXxxAttribute` |
| **Naming strategy** | camelCase default | snake_case default |
| **Ignore unknown** | `@JsonIgnoreProperties(ignoreUnknown = true)` | `$fillable` ilə filter |
| **Custom format** | `JsonSerializer<T>` | Accessor/Mutator, Custom Cast |
| **Polymorphic** | `@JsonTypeInfo`, `@JsonSubTypes` | Manual discriminator |
| **Views** | `@JsonView` | Ayrı API Resource-lar |
| **Global config** | `application.yml`, `@Bean ObjectMapper` | Model-də `$casts`, `$hidden` |
| **Circular reference** | `@JsonManagedReference`/`@JsonBackReference` | `whenLoaded()` ilə avtomatik qarşısı alınır |

## Niyə belə fərqlər var?

**Spring + Jackson fəlsəfəsi:**

1. **Type-safe serialization:** Java güclü tip sistemi olduğuna görə Jackson generic ilə işləyir (`<T>`). Hər field-in tipi compile zamanı bilinir, bu da deserialization-da yanlışlığı azaldır.

2. **Annotation-based:** Java-da annotation ekosistemi çox güclüdür - Jackson-un bütün xüsusiyyətləri annotation ilə konfigure olunur. Kod təmizdir, amma annotation qalağı uzundur.

3. **Explicit DTO:** Spring dünyasında Entity-ni birbaşa serialize etmək pis praktikadır. Ona görə Jackson annotation-larının əsas istifadə yeri DTO-lardır.

4. **ObjectMapper universal:** Jackson Spring-dən asılı deyil - Android, Micronaut, Quarkus, hər yerdə istifadə olunur. Bu sayədə ekosistem genişdir.

**Laravel fəlsəfəsi:**

1. **Active Record = JSON:** Eloquent model özü JSON-a çevrilə bilər. Bu, Laravel-in "developer happiness" fəlsəfəsinin təzahürüdür.

2. **Konvensiya - snake_case:** DB sütun adı birbaşa JSON field adı olur. Ara qatda çevirmək lazım deyil.

3. **Array/Cast magic:** `$casts` avtomatik tip çevirmələri edir. Developer heç bir annotation qoymamalıdır.

4. **Resource - opsional DTO:** Sadə hallar üçün model kifayətdir. Kompleks API-lər üçün Resource sinifləri var.

## Ümumi səhvlər (Beginner traps)

### 1. Default `LocalDateTime` JSON-u kimi array çıxır

```json
"createdAt": [2026, 4, 24, 14, 30]
```

Bu çirkindir. Həll: `JavaTimeModule` register et (Spring Boot avtomatik edir), və ya `@JsonFormat(pattern=...)` əlavə et.

### 2. `Date` vs `LocalDate` vs `LocalDateTime` qarışdırmaq

```java
// Yanlış - `java.util.Date` köhnə, timezone problemi var
private Date createdAt;

// Daha yaxşı - Java 8+
private LocalDateTime createdAt;      // tarix + zaman
private LocalDate birthDate;          // yalnız tarix
private LocalTime meetingTime;        // yalnız zaman
private ZonedDateTime eventTime;      // timezone-lu
private Instant timestamp;            // UTC moment
```

**Tövsiyə:** Database və DTO-da:
- Yalnız tarix → `LocalDate`
- Tarix + zaman, timezone yox → `LocalDateTime`
- Timezone var → `ZonedDateTime` ya `Instant`

### 3. Circular reference - stack overflow

```java
@Entity
public class User {
    @OneToMany private List<Order> orders;
}
@Entity
public class Order {
    @ManyToOne private User user;
}

return userRepository.findById(1L).get();
// StackOverflowError! ya çox böyük JSON
```

Həll: DTO istifadə et. Ya `@JsonBackReference`.

### 4. Private field serialize olmur (default)

```java
public class User {
    private Long id;
    private String name;
    // getter yoxdur!
}

// Default Jackson yalnız getter-i istifadə edir - field görməz
// JSON: {} (empty!)
```

**Həll:** Getter əlavə et ya `ObjectMapper`-i konfigure et:

```java
mapper.setVisibility(PropertyAccessor.FIELD, Visibility.ANY);
// İndi private field-lər də görünür
```

Daha yaxşı - Record istifadə et: field + accessor avtomatikdir.

### 5. camelCase vs snake_case qarışdırmaq

```java
// Java class
public class User {
    private String firstName;
}

// Default JSON: {"firstName":"Orxan"}
// Client snake_case istəyir: {"first_name":"Orxan"}
```

Həll:
```yaml
spring:
  jackson:
    property-naming-strategy: SNAKE_CASE
```

Ya `@JsonProperty("first_name")` field-də.

### 6. JSON-da extra field - default exception

```java
// JSON request
{"id":1,"name":"Orxan","extraField":"whatever"}

public class UserDto {
    private Long id;
    private String name;
}

// Default Jackson exception atır:
// UnrecognizedPropertyException: Unrecognized field "extraField"
```

Həll:
```java
@JsonIgnoreProperties(ignoreUnknown = true)
public class UserDto { ... }

// Ya da global:
mapper.configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);
```

### 7. `@JsonIgnore` ilə `final` field - immutable problem

```java
public class UserDto {
    private final Long id;
    private final String name;

    @JsonIgnore
    private final String secret;  // Constructor-da set olmalıdır

    @JsonCreator
    public UserDto(
            @JsonProperty("id") Long id,
            @JsonProperty("name") String name) {
        this.id = id;
        this.name = name;
        this.secret = null; // amma constructor-da set etmək lazım
    }
}
```

Daha təmiz: Record + exclude-only DTO.

### 8. Laravel ilə müqayisədə - snake_case əksikliyi

Java developer Laravel-dan gələndə snake_case gözləyə bilər, amma Spring default camelCase verir. İlk işdə application.yml-də konfigure et.

### 9. Null field JSON-da görünür - istəmədikdə

```java
public class UserDto {
    private Long id;
    private String name;
    private String optionalField;  // null olsa da görünür
}

// JSON: {"id":1,"name":"Orxan","optionalField":null}
```

Həll:
```java
@JsonInclude(JsonInclude.Include.NON_NULL)
public class UserDto { ... }

// Ya global application.yml:
// spring.jackson.default-property-inclusion: non_null
```

### 10. `@RequestBody` deserialization - default constructor lazımdır

```java
public class UserDto {
    private Long id;
    private String name;

    public UserDto(Long id, String name) {
        this.id = id;
        this.name = name;
    }
}

@PostMapping
public void create(@RequestBody UserDto dto) {
    // Jackson exception ata bilər:
    // "no default constructor"
}
```

Həll:
- Default constructor əlavə et.
- Ya `@JsonCreator + @JsonProperty` istifadə et.
- Ya Record istifadə et (Jackson 2.12+ avtomatik dəstəklənir).

## Mini müsahibə sualları

**Sual 1**: Jackson-da `@JsonProperty("name")` və `@JsonAlias({"name","title"})` arasında nə fərq var?

*Cavab*: `@JsonProperty` həm serialization (JSON-da hansı ad görünəcək), həm də deserialization üçün istifadə olunur. Yalnız bir ad təyin edir. `@JsonAlias` yalnız deserialization zamanı alternative adları qəbul edir - serialize olanda `@JsonProperty` ilə təyin olunan ad istifadə olunur. Misal: client gah `"name"`, gah `"title"` göndərir - `@JsonAlias({"name","title"})` hər ikisini qəbul edir.

**Sual 2**: `LocalDateTime` field-i default olaraq niyə çirkin array kimi serialize olunur və həlli nədir?

*Cavab*: Jackson default olaraq `LocalDateTime`-i timestamp kimi array formatında yazır (`[2026,4,24,14,30]`). Bu, ISO-8601 standartına uyğun deyil. Həll: `jackson-datatype-jsr310` dependency əlavə et və `JavaTimeModule`-u `ObjectMapper`-a register et. Sonra `mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS)` yandır. Spring Boot `spring-boot-starter-web` ilə bu avtomatik edilir.

**Sual 3**: Spring-də camelCase default-dur, Laravel-də snake_case. Spring-də JSON-u snake_case etmək necə olur?

*Cavab*: İki yol var:
1. `application.yml`-də global:
   ```yaml
   spring:
     jackson:
       property-naming-strategy: SNAKE_CASE
   ```
2. Field-də nöqtə-nöqtə: `@JsonProperty("first_name") private String firstName`.
Global konfiqurasiya daha təmizdir - bütün DTO-lar avtomatik uyğunlaşır.

**Sual 4**: Circular reference (bidirectional JPA) Jackson-u niyə dövrəyə salır və hansı üsullar var?

*Cavab*: User → orders → order → user → orders → ... sonsuz dövrə. Üç yanaşma var:
1. `@JsonManagedReference` (parent) + `@JsonBackReference` (child) - back tərəf ignore olunur.
2. `@JsonIgnore` bir tərəfdə - sadə amma məlumat itir.
3. **Ən ideal: DTO istifadə et** - Entity-nin əlaqələrini DTO-ya seçərək daxil et. Bu həmçinin N+1 və LazyInitializationException problemlərinin qarşısını alır.
