# Serialization (Serializasiya / Melumat formatlama)

## Giris

API-ler melumat mubadilesinde Java/PHP obyektlerini JSON formatina cevirmeli (serialization) ve eksinne JSON-dan obyekte cevirmeli (deserialization). Bu proses API-nin nece goruneceyini, hansi sahelerin gosterileceyini ve melumantin nece formatlanacagini mueyyen edir. Spring Jackson kutubxanesi ile isleyir, Laravel ise API Resources ve Eloquent model xususiyyetleri ile bu meseleyi hell edir.

## Spring-de istifadesi

### Jackson - default serializer

Spring Boot avtomatik olaraq Jackson istifade edir. Hec bir konfiqurasiya olmadan Java obyektleri JSON-a cevrilir:

```java
// Entity
@Entity
public class User {
    private Long id;
    private String name;
    private String email;
    private String password;
    private LocalDateTime createdAt;
    private List<Role> roles;

    // Getters, setters
}

// Controller-de qaytaranda avtomatik JSON olur:
// { "id": 1, "name": "Orxan", "email": "...", "password": "...", ... }
// PROBLEM: password da gorsenir!
```

### @JsonIgnore ve @JsonProperty

```java
public class User {

    private Long id;

    @JsonProperty("full_name")  // JSON-da sahe adini deyismek
    private String name;

    private String email;

    @JsonIgnore  // Bu saheni JSON-da gosterme
    private String password;

    @JsonIgnore
    private String activationToken;

    @JsonFormat(pattern = "yyyy-MM-dd HH:mm:ss")
    private LocalDateTime createdAt;

    @JsonProperty(access = JsonProperty.Access.WRITE_ONLY)
    // Yalniz deserializasiyada (request-de) istifade olunur, response-da gorsenmez
    private String passwordConfirmation;

    @JsonProperty(access = JsonProperty.Access.READ_ONLY)
    // Yalniz serialzasiyada (response-da) gorsenir, request-de qebul olunmaz
    private LocalDateTime lastLoginAt;
}
```

### DTO ile serialzasiya (tovsiye olunan)

```java
// Response DTO
public record UserResponse(
    Long id,
    String name,
    String email,
    String avatarUrl,
    LocalDateTime createdAt,
    List<String> roles
) {
    public static UserResponse from(User user) {
        return new UserResponse(
            user.getId(),
            user.getName(),
            user.getEmail(),
            user.getAvatarUrl(),
            user.getCreatedAt(),
            user.getRoles().stream()
                .map(Role::getName)
                .toList()
        );
    }
}

// Siyahi ucun
public record UserListResponse(
    List<UserResponse> users,
    long total
) {}

// Request DTO
public record CreateUserRequest(
    @NotBlank String name,
    @Email String email,
    @Size(min = 8) String password
) {}
```

### ObjectMapper konfiqurasiyasi

```java
@Configuration
public class JacksonConfig {

    @Bean
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();

        // Namelum saheleri ignore et
        mapper.configure(
            DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);

        // Bos saheleri JSON-a yazma
        mapper.setSerializationInclusion(JsonInclude.Include.NON_NULL);

        // Java 8 date/time destekli
        mapper.registerModule(new JavaTimeModule());
        mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS);

        // snake_case formatinda JSON yaratmaq
        mapper.setPropertyNamingStrategy(
            PropertyNamingStrategies.SNAKE_CASE);

        return mapper;
    }
}

// Yaxud application.yml ile:
// spring:
//   jackson:
//     property-naming-strategy: SNAKE_CASE
//     serialization:
//       write-dates-as-timestamps: false
//     default-property-inclusion: non_null
```

### Xususi Serializer ve Deserializer

```java
// Xususi serializer
public class MoneySerializer extends JsonSerializer<BigDecimal> {

    @Override
    public void serialize(BigDecimal value, JsonGenerator gen,
                          SerializerProvider serializers) throws IOException {
        gen.writeString(value.setScale(2, RoundingMode.HALF_UP) + " AZN");
    }
}

// Xususi deserializer
public class MoneyDeserializer extends JsonDeserializer<BigDecimal> {

    @Override
    public BigDecimal deserialize(JsonParser p,
                                   DeserializationContext ctxt) throws IOException {
        String value = p.getValueAsString().replace(" AZN", "").trim();
        return new BigDecimal(value);
    }
}

// Istifadesi
public class Product {
    private String name;

    @JsonSerialize(using = MoneySerializer.class)
    @JsonDeserialize(using = MoneyDeserializer.class)
    private BigDecimal price;
    // JSON: { "name": "Laptop", "price": "1500.00 AZN" }
}
```

### Polimorfik serialzasiya

```java
@JsonTypeInfo(
    use = JsonTypeInfo.Id.NAME,
    property = "type"
)
@JsonSubTypes({
    @JsonSubTypes.Type(value = EmailNotification.class, name = "email"),
    @JsonSubTypes.Type(value = SmsNotification.class, name = "sms"),
    @JsonSubTypes.Type(value = PushNotification.class, name = "push"),
})
public abstract class Notification {
    private String message;
}

public class EmailNotification extends Notification {
    private String subject;
    private String recipient;
}

public class SmsNotification extends Notification {
    private String phoneNumber;
}

// JSON:
// { "type": "email", "message": "...", "subject": "...", "recipient": "..." }
// { "type": "sms", "message": "...", "phoneNumber": "..." }
```

### @JsonView ile ferqli goruntular

```java
public class Views {
    public static class Summary {}
    public static class Detail extends Summary {}
    public static class Admin extends Detail {}
}

public class User {
    @JsonView(Views.Summary.class)
    private Long id;

    @JsonView(Views.Summary.class)
    private String name;

    @JsonView(Views.Detail.class)
    private String email;

    @JsonView(Views.Detail.class)
    private LocalDateTime createdAt;

    @JsonView(Views.Admin.class)
    private boolean active;

    @JsonView(Views.Admin.class)
    private List<Role> roles;
}

@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping
    @JsonView(Views.Summary.class)
    public List<User> listUsers() {
        // Yalniz id ve name qaytarilir
        return userService.findAll();
    }

    @GetMapping("/{id}")
    @JsonView(Views.Detail.class)
    public User getUser(@PathVariable Long id) {
        // id, name, email, createdAt qaytarilir
        return userService.findById(id);
    }

    @GetMapping("/{id}/admin")
    @JsonView(Views.Admin.class)
    public User getUserAdmin(@PathVariable Long id) {
        // Butun saheler qaytarilir
        return userService.findById(id);
    }
}
```

## Laravel-de istifadesi

### Model uzerinde $hidden ve $visible

```php
class User extends Model
{
    // Bu saheler JSON-da GORSENMEYECEK
    protected $hidden = [
        'password',
        'remember_token',
        'activation_token',
    ];

    // Alternativ: yalniz bu saheler gorsenecek
    // protected $visible = ['id', 'name', 'email'];

    // Tip cevirmeleri
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
        'balance' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    // Computed attribute
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->first_name . ' ' . $this->last_name,
        );
    }

    // Append computed attributes
    protected $appends = ['full_name'];
}

// Dinamik olaraq gizlemek/gostermek
$user->makeVisible('password'); // Gizli saheni goster
$user->makeHidden('email');     // Saheni gizle

// Collection ucun
$users = User::all()->makeHidden(['email', 'created_at']);
```

### API Resources (tovsiye olunan)

```bash
php artisan make:resource UserResource
php artisan make:resource UserCollection
```

```php
// app/Http/Resources/UserResource.php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at->toISOString(),

            // Sertli saheler
            'email_verified' => $this->when(
                $request->user()?->isAdmin(),
                $this->email_verified_at !== null
            ),

            // Elaqeli melumatlar (yalniz yuklenibse)
            'roles' => RoleResource::collection(
                $this->whenLoaded('roles')
            ),

            'orders_count' => $this->whenCounted('orders'),

            // Hesaplanmis deyerler
            'is_new' => $this->created_at->isAfter(now()->subDays(7)),
        ];
    }

    // Elaqeli meta melumat
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => 'v1',
            ],
        ];
    }
}
```

```php
// app/Http/Resources/UserCollection.php
class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
            ],
        ];
    }
}
```

### Controller-de istifade

```php
class UserController extends Controller
{
    // Tek resurs
    public function show(User $user)
    {
        $user->load('roles'); // Elaqeleri yukle
        return new UserResource($user);
    }

    // Siyahi
    public function index()
    {
        $users = User::with('roles')->paginate(15);
        return UserResource::collection($users);
    }

    // Yaratma - 201 status ile
    public function store(CreateUserRequest $request)
    {
        $user = User::create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}

// Cavab formati:
// {
//   "data": {
//     "id": 1,
//     "name": "Orxan",
//     "email": "orxan@example.com",
//     ...
//   },
//   "meta": {
//     "api_version": "v1"
//   }
// }
```

### Ic-ice Resources

```php
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'total' => number_format($this->total, 2) . ' AZN',

            // Ic-ice resource
            'customer' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            // Sertli ic-ice resource
            'shipping' => $this->when(
                $this->status !== 'pending',
                fn () => new ShippingResource($this->whenLoaded('shipping'))
            ),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'unit_price' => number_format($this->unit_price, 2) . ' AZN',
            'subtotal' => number_format($this->quantity * $this->unit_price, 2) . ' AZN',
        ];
    }
}
```

### Casts (Tip cevirmeleri)

```php
class Product extends Model
{
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'tags' => 'collection',
        'published_at' => 'datetime',
        'settings' => AsArrayObject::class,
        'status' => ProductStatus::class, // Enum cast
    ];
}

// Xususi Cast yaratmaq
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key,
                        mixed $value, array $attributes): string
    {
        return number_format($value / 100, 2) . ' AZN';
    }

    public function set(Model $model, string $key,
                        mixed $value, array $attributes): int
    {
        // "15.50 AZN" -> 1550 (sent olaraq saxla)
        $numeric = (float) str_replace([' AZN', ','], '', $value);
        return (int) ($numeric * 100);
    }
}

// Istifadesi
class Order extends Model
{
    protected $casts = [
        'total' => MoneyCast::class,
    ];
}
```

### toArray() ve toJson()

```php
// Model birbaşa JSON-a cevirilir
$user = User::find(1);
$json = $user->toJson();
$array = $user->toArray();

// Collection
$users = User::all();
$json = $users->toJson();

// Xususi formatda
$custom = $user->only(['id', 'name', 'email']);
// ['id' => 1, 'name' => 'Orxan', 'email' => '...']

$custom = $user->except(['password', 'remember_token']);
```

## Esas ferqler

| Xususiyyet | Spring (Jackson) | Laravel |
|---|---|---|
| **Sahe gizletme** | `@JsonIgnore` | `$hidden` massivi |
| **Sahe adi deyisme** | `@JsonProperty("name")` | API Resource-da manual |
| **Tarix formati** | `@JsonFormat` | `$casts`, `toISOString()` |
| **Xususi serializer** | `JsonSerializer<T>` sinfi | Custom Cast sinfi |
| **Goruntu seciml** | `@JsonView` | Ferqli API Resource-lar |
| **API response formati** | DTO / Record | API Resource sinfi |
| **Sertli saheler** | Manual (DTO-da) | `$this->when()` |
| **Elaqeli melumat** | DTO-da manual | `$this->whenLoaded()` |
| **Qlobal konfiqurasiya** | `ObjectMapper` bean | Model `$casts`, `$hidden` |
| **Polimorfizm** | `@JsonTypeInfo` | Manual (`$this->when`) |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Jackson kutubxanesi Java ekosisteminin standart JSON kutubxanesidir ve Spring-den asili deyil. Annotasiya esasli isleyir ve coxlu esneklik verir. `@JsonView` ile eyni entity-den ferqli gorunusler yaratmaq mumkundur. DTO pattern-i Spring dunyasinda standartdir - entity birbaşa response olaraq qaytarilmir, evvelce DTO-ya cevrilir.

**Laravel-in yanasmasi:** Laravel iki seviyye teklif edir. Sade hallar ucun `$hidden`/`$visible` ve `$casts` model xususiyyetleri kifayetdir. Daha murakkeb API-ler ucun API Resource sinifleri istifade olunur. `whenLoaded()` ve `when()` kimi metodlar cox elegantdir - elaqelerin yalniz yuklendikde gosterilmesi N+1 probleminin qarsisini alir.

**Active Record vs DTO:** Laravel-de model birbaşa serialize oluna biler (`$user->toJson()`), cunki Active Record pattern-dir. Spring-de entity birbaşa serialize etmek pis praktika sayilir - DTO istifade olunur. Laravel-in API Resources eslinde DTO pattern-inin Laravel versiyasidir.

## Hansi framework-de var, hansinda yoxdur?

- **`@JsonView`** - Yalniz Spring-de. Eyni entity-den ferqli gorunusler (summary, detail, admin).
- **`@JsonTypeInfo` (polimorfik serialzasiya)** - Yalniz Spring-de. Miras iyerarxiyasini JSON-da tip melumatli ile saxlamaq.
- **`whenLoaded()`** - Yalniz Laravel-de. Elaqelinin yalniz yuklendikde JSON-da gorsenmesi.
- **`when()`** - Yalniz Laravel-de. Sertli saheleri response-a elave etmek.
- **`$hidden` / `$visible`** - Yalniz Laravel-de. Model seviyyesinde global sahe gizletme.
- **Custom Cast** - Yalniz Laravel-de. DB deyeri ile PHP deyeri arasinda avtomatik cevirmeler.
- **`makeHidden()` / `makeVisible()`** - Yalniz Laravel-de. Runtime-da gizli saheleri deyismek.
- **ObjectMapper** - Yalniz Spring-de. JSON serialization/deserialization ucun tam konfiqurasiya edilebilir engine.
- **`@JsonProperty(access)`** - Yalniz Spring-de. READ_ONLY/WRITE_ONLY ile request/response-da ferqli davranis.
