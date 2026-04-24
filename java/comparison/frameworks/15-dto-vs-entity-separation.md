# DTO vs Entity ayrılığı: Spring vs Laravel

> **Seviyye:** Beginner ⭐

## Giriş

API yazanda iki növ obyekt ilə qarşılaşırıq: **Entity** (database cədvəli ilə bağlıdır) və **DTO** (Data Transfer Object, şəbəkədə məlumat daşıyır). Spring-də bu iki obyekt ayrılmalıdır - entity-ni birbaşa JSON kimi qaytarmaq təhlükəlidir. Laravel-də isə Eloquent model həm entity, həm də API response rolunu oynayır, amma bu yanaşmanın da problemləri var.

Bu fayl beginner üçündür. Entity nədir, DTO nədir, niyə entity-ni controller-dən birbaşa qaytarmaq olmaz, Spring-də necə DTO yaradılır - sadə misallar ilə izah edilir.

## Spring/Java-də

### Entity nədir?

Entity - database cədvəlinə uyğun Java sinfidir. JPA (Java Persistence API) entity-ləri idarə edir (load, save, update, delete). Entity-nin xüsusiyyəti: DB ilə bağlıdır, `EntityManager` və ya Hibernate tərəfindən "managed" (idarə edilir).

```java
@Entity
@Table(name = "users")
public class User {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, length = 100)
    private String name;

    @Column(nullable = false, unique = true)
    private String email;

    // Bu sahə təhlükəlidir - JSON-da görsənməməli
    @Column(nullable = false)
    private String passwordHash;

    // Internal field - istifadəçi görməməlidir
    @Column(name = "internal_ref_code")
    private String internalRefCode;

    // Lazy relation - JSON serialization zamanı problem yaradır
    @OneToMany(mappedBy = "user", fetch = FetchType.LAZY)
    private List<Order> orders;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "role_id")
    private Role role;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    // Getters, setters...
}
```

### DTO nədir?

DTO (Data Transfer Object) - sadəcə məlumat daşıyan obyektdir. DB ilə bağlı deyil. API-də input (Request DTO) və ya output (Response DTO) rolunu oynayır. Java 16+ `record` DTO üçün ideal seçimdir - az kod, immutable (dəyişməz), compiler avtomatik `equals`, `hashCode`, `toString` yaradır.

```java
// Response DTO - client-ə qayıdır
public record UserResponse(
    Long id,
    String name,
    String email,
    String roleName,
    LocalDateTime createdAt
) {
    // Static factory method - entity-dən DTO yaratmaq
    public static UserResponse from(User user) {
        return new UserResponse(
            user.getId(),
            user.getName(),
            user.getEmail(),
            user.getRole() != null ? user.getRole().getName() : null,
            user.getCreatedAt()
        );
    }
}

// Request DTO - client-dən gəlir
public record CreateUserRequest(
    @NotBlank(message = "Ad boş ola bilməz")
    String name,

    @NotBlank
    @Email(message = "Email düzgün formatda olmalıdır")
    String email,

    @NotBlank
    @Size(min = 8, message = "Şifrə ən az 8 simvol olmalıdır")
    String password
) {}
```

### Niyə entity-ni REST API-də qaytarmaq təhlükəlidir?

**Problem 1: LazyInitializationException**

Lazy loading dedikdə - əlaqəli məlumat (məsələn, `user.getOrders()`) yalnız soruşanda DB-dən gəlir. Amma controller-də transaction bitibsə, Jackson (JSON library) lazy field-i serialize etmək istəyir və exception atır.

```java
// PIS misal - entity-ni birbaşa qaytarır
@GetMapping("/{id}")
public User getUser(@PathVariable Long id) {
    User user = userRepository.findById(id).orElseThrow();
    // Burada transaction bitir
    return user;
    // Jackson user.orders-i serialize etmək istəyəndə:
    // org.hibernate.LazyInitializationException: could not initialize proxy - no Session
}
```

**Problem 2: Həssas field-lərin sızması**

Entity-də `passwordHash`, `internalRefCode`, `resetToken` kimi field-lər ola bilər. Birbaşa qaytaranda bütün bunlar JSON-a düşür.

```json
// PIS - client bunu görür
{
  "id": 1,
  "name": "Orxan",
  "email": "orxan@example.com",
  "passwordHash": "$2a$10$abc...",
  "internalRefCode": "INT-4567-XYZ"
}
```

**Problem 3: API DB schema-ya bağlı qalır**

Entity-ni qaytaranda API response formatı DB cədvəli ilə eyni olur. Sabah `first_name` və `last_name` ayrı sütunlara ayırsan, API istifadəçiləri sınır. Bu "tight coupling" problemi adlanır.

**Problem 4: N+1 query serialization zamanı**

Jackson entity-dəki bütün relation-ları serialize etmək istəyir. Hər relation üçün yeni query gedir.

```java
// 100 user var, hər user üçün role varsa:
// 1 query user-ləri gətirir + 100 query hər user-in role-u üçün = 101 query
return userRepository.findAll();  // N+1 fəlakəti
```

### DTO ilə düzgün yanaşma

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserService userService;

    public UserController(UserService userService) {
        this.userService = userService;
    }

    @GetMapping("/{id}")
    public UserResponse getUser(@PathVariable Long id) {
        User user = userService.findById(id);
        return UserResponse.from(user);  // DTO-ya çevrilir
    }

    @GetMapping
    public List<UserResponse> listUsers() {
        return userService.findAll()
            .stream()
            .map(UserResponse::from)
            .toList();
    }

    @PostMapping
    public ResponseEntity<UserResponse> createUser(
            @Valid @RequestBody CreateUserRequest request) {
        User user = userService.create(request);
        return ResponseEntity
            .status(HttpStatus.CREATED)
            .body(UserResponse.from(user));
    }
}
```

### DTO variantları

**Request DTO** (input üçün):

```java
public record CreateOrderRequest(
    @NotNull Long productId,
    @Min(1) @Max(100) Integer quantity,
    @NotBlank String shippingAddress,
    @Size(max = 500) String note  // optional
) {}
```

**Response DTO** (output üçün):

```java
public record OrderResponse(
    Long id,
    String orderNumber,
    String status,
    BigDecimal total,
    CustomerInfo customer,  // nested DTO
    List<OrderItemResponse> items,
    LocalDateTime createdAt
) {
    // Nested record
    public record CustomerInfo(Long id, String name, String email) {}
}
```

**Update DTO** (patch üçün):

```java
public record UpdateUserRequest(
    @Size(min = 2, max = 100) String name,  // nullable olsa da lazım olanda yoxlayır
    @Email String email
) {
    // Sadəcə null olmayan field-ləri yeniləmək
    public boolean hasName() { return name != null; }
    public boolean hasEmail() { return email != null; }
}
```

### Mapping strategiyaları

**1. Manual mapping (ən çox control, uzun kod)**

```java
public static UserResponse from(User user) {
    return new UserResponse(
        user.getId(),
        user.getName(),
        user.getEmail(),
        user.getRole() != null ? user.getRole().getName() : null,
        user.getCreatedAt()
    );
}
```

**2. MapStruct (compile-time code generator, sürətli)**

```java
// pom.xml-də dependency + annotation processor lazımdır

@Mapper(componentModel = "spring")
public interface UserMapper {

    @Mapping(target = "roleName", source = "role.name")
    UserResponse toResponse(User user);

    List<UserResponse> toResponseList(List<User> users);

    User toEntity(CreateUserRequest request);
}

// İstifadəsi - Spring bean kimi inject olunur
@Service
public class UserService {
    private final UserMapper mapper;
    private final UserRepository repository;

    public UserResponse findById(Long id) {
        User user = repository.findById(id).orElseThrow();
        return mapper.toResponse(user);
    }
}
```

MapStruct compile zamanında kodu generate edir - runtime-da əlavə xərc yoxdur. Sürəti manual mapping ilə eynidir.

**3. ModelMapper (runtime reflection, yavaşdır)**

```java
@Autowired
private ModelMapper modelMapper;

public UserResponse toResponse(User user) {
    return modelMapper.map(user, UserResponse.class);
}
```

ModelMapper reflection ilə işləyir - yavaşdır, səhvləri runtime-da tapılır. Kiçik layihədə olur, böyük layihədə MapStruct seçin.

**4. Builder pattern (dto yaradarkən asan)**

```java
@Builder
public class UserResponse {
    private Long id;
    private String name;
    private String email;
    // ...
}

UserResponse dto = UserResponse.builder()
    .id(user.getId())
    .name(user.getName())
    .email(user.getEmail())
    .build();
```

### JPA Projection (DTO alternative)

Bəzən DTO üçün ayrıca sinif yaratmaq lazım deyil. JPA `Projection` interface-i birbaşa query-dən DTO qaytarır:

```java
// Interface projection
public interface UserSummary {
    Long getId();
    String getName();
    String getEmail();
}

@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    // Query sadəcə id, name, email gətirir - başqa sütunlar yox
    List<UserSummary> findAllProjectedBy();

    @Query("SELECT u.id AS id, u.name AS name, u.email AS email FROM User u WHERE u.active = true")
    List<UserSummary> findActiveUsersSummary();
}
```

Faydaları: query DB-dən daha az data gətirir, JSON-a çevirmək avtomatikdir.

### Validation: DTO-da vs Entity-də

**DTO-da validation** - API boundary-də input yoxlanılır:

```java
public record CreateUserRequest(
    @NotBlank @Size(max = 100) String name,
    @NotBlank @Email String email,
    @NotBlank @Size(min = 8) String password
) {}
```

**Entity-də constraint** - DB-level integrity:

```java
@Entity
public class User {
    @Column(nullable = false, length = 100)
    private String name;

    @Column(nullable = false, unique = true)
    private String email;
}
```

Fərq: DTO validation 400 Bad Request qaytarır (istifadəçi səhvi), entity constraint DB exception atır (proqramçının səhvi - DTO validation uçmuşdur).

## Laravel/PHP-də

### Eloquent Model

Laravel-də `Model` sinfi Active Record pattern-dir - həm entity, həm də "DTO-ya yaxın" bir şeydir. Birbaşa JSON-a çevrilir.

```php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    // JSON-da gizlədiləcək sahələr
    protected $hidden = [
        'password', 'remember_token', 'internal_ref_code',
    ];

    // Avtomatik tip çevirmələri
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
```

### Problem: Model-i birbaşa qaytaranda risk

```php
// PIS misal - bütün field-lər görsənir
public function show(User $user)
{
    return response()->json($user);
    // $hidden olmasaydı, password_hash da JSON-da olardı
}
```

Laravel `$hidden` ilə kritik field-ləri gizləyir, amma yenə də bu yanaşmada problemlər var:
- Model-in formatı = API-in formatı (coupling)
- Yeni field əlavə edəndə API-yə avtomatik çıxır (təhlükəli)
- Relation-ları manual yükləmək lazımdır

### Form Request (Request DTO analoqu)

Laravel-də validation üçün `FormRequest` sinfi istifadə olunur. Bu, Spring-dəki Request DTO-nun analoqudur.

```bash
php artisan make:request StoreUserRequest
```

```php
// app/Http/Requests/StoreUserRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Bu email artıq qeydiyyatdadır.',
            'password.min' => 'Şifrə ən az 8 simvol olmalıdır.',
        ];
    }
}
```

Controller-də istifadəsi:

```php
class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        // Validation avtomatik olur
        $user = User::create($request->validated());
        return new UserResource($user);
    }
}
```

### API Resource (Response DTO analoqu)

Laravel-də `JsonResource` sinfi Response DTO rolunu oynayır. Entity-dən ayrı format yaratmaq üçün istifadə olunur.

```bash
php artisan make:resource UserResource
```

```php
// app/Http/Resources/UserResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role_name' => $this->whenLoaded('role', fn () => $this->role->name),
            'created_at' => $this->created_at->toIso8601String(),
            // password_hash, internal_ref_code - buraya yazmırıq, görsənmir
        ];
    }
}
```

Controller-də:

```php
class UserController extends Controller
{
    public function show(User $user)
    {
        $user->load('role');
        return new UserResource($user);
    }

    public function index()
    {
        return UserResource::collection(
            User::with('role')->paginate(15)
        );
    }
}
```

### $fillable, $hidden, $casts

```php
class User extends Model
{
    // Yalnız bu sahələr `User::create([...])` ilə dəyişə bilər
    protected $fillable = ['name', 'email', 'password'];

    // JSON-da görsənməyəcək sahələr
    protected $hidden = ['password', 'remember_token'];

    // Avtomatik tip çevirmələri
    protected $casts = [
        'email_verified_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];
}
```

Bu mexanizm sadə hallarda işləyir. Amma mürəkkəb API-də `JsonResource` daha yaxşıdır.

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| **Entity sinfi** | `@Entity` JPA sinfi | Eloquent `Model` |
| **Response DTO** | Record / class (ayrı) | `JsonResource` |
| **Request DTO** | Record / class + `@Valid` | `FormRequest` |
| **Birbaşa entity qaytarma** | Təhlükəli (LazyInit, leak) | Mümkündür (`$hidden` ilə) |
| **Field gizlətmə** | DTO-da yazmırsan | `$hidden` massivi |
| **Tip çevirməsi** | Jackson annotations | `$casts` massivi |
| **Immutability** | Record avtomatik immutable | Model mutable |
| **Mapping** | Manual, MapStruct, ModelMapper | Avtomatik (property access) |
| **Validation yeri** | DTO annotations (`@NotNull`) | FormRequest `rules()` |
| **Nested relations** | DTO-da manual yaz | `whenLoaded()` |
| **Projection** | JPA interface projection | `select()` + Resource |

## Niyə belə fərqlər var?

### Spring-in yanaşması

Java statik tipli dildir. Entity və DTO ayrı siniflərdir - compiler görür hansı istifadə olunur. DTO-nu ayrı yazmaq "boilerplate" kimi görsənə bilər, amma uzunmüddətli faydası var:
- API contract (DTO) ayrıdır, DB schema (entity) ayrıdır
- DB-ni dəyişəndə API sınmır
- Security (password hash görsənmir, çünki DTO-ya yazılmayıb)

Java record-lar (Java 16+) DTO yazmanı asanlaşdırıb - 3 sətirdə tam DTO.

### Laravel-in yanaşması

PHP dinamik dildir. Laravel "rapid development" fəlsəfəsinə uyğundur - model birbaşa JSON-a çevrilir, az kod yazırsan. Amma bu asanlıq risk gətirir:
- Yeni field əlavə edəndə avtomatik API-yə çıxır
- Password leak bir səhv addım uzaqdadır

Ona görə Laravel-də "serious" API-lər `JsonResource` və `FormRequest` istifadə edir - əsasında bu Spring DTO-larının Laravel versiyasıdır.

### Active Record vs Data Mapper

- **Laravel (Active Record)**: Model həm data daşıyır, həm DB-ni bilir (`$user->save()`)
- **Spring (Data Mapper)**: Entity sadəcə data, ayrı `Repository` DB-ni idarə edir

Bu fəlsəfi fərqdir. Hər ikisinin artısı-əksisi var.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Spring-də

- **Record DTO**: Immutable data class 3 sətirdə - Java feature-sidir
- **JPA Projection interface**: Query səviyyəsində az data gətirmək
- **MapStruct**: Compile-time code generator, sıfır runtime xərci
- **Bean Validation (`@NotNull`, `@Email`)**: Standart JSR-303 annotations
- **`@JsonView`**: Eyni DTO-dan fərqli görünüşlər

### Yalnız Laravel-də

- **`$hidden` / `$fillable`**: Model səviyyəsində field control
- **`$casts`**: DB-dən çıxanda avtomatik tip çevirməsi
- **`whenLoaded()`**: Resource-da relation yalnız yüklənibsə göstər
- **`$appends`**: Virtual attribute JSON-a əlavə
- **Route model binding**: Controller argument-ində avtomatik model load

## Ümumi səhvlər (Beginner traps)

**1. Spring-də entity-ni birbaşa qaytarmaq**

```java
// PIS
@GetMapping("/{id}")
public User get(@PathVariable Long id) {
    return repository.findById(id).orElseThrow();
}

// YAXSHI
@GetMapping("/{id}")
public UserResponse get(@PathVariable Long id) {
    return UserResponse.from(repository.findById(id).orElseThrow());
}
```

**2. Laravel-də `$hidden` unudub password-u çıxarmaq**

```php
// PIS - $hidden yoxdur, password_hash JSON-da görsənir
class User extends Model {
    // ...
}

// YAXSHI
class User extends Model {
    protected $hidden = ['password', 'remember_token'];
}
```

**3. DTO-ya bütün entity field-lərini kopya etmək**

DTO sadəcə API-in lazım olduğu field-ləri saxlamalıdır. Internal ID, created_by, audit_log - bunlar DTO-da olmamalı.

**4. Lazy relation-i DTO-ya göndərmək**

```java
// PIS - relations hələ yüklənməyib
@Transactional(readOnly = true)
public UserResponse get(Long id) {
    User user = repository.findById(id).orElseThrow();
    return new UserResponse(user.getId(), user.getOrders());  // LazyInit!
}

// YAXSHI - query-də fetch join
@Query("SELECT u FROM User u LEFT JOIN FETCH u.orders WHERE u.id = :id")
Optional<User> findByIdWithOrders(@Param("id") Long id);
```

**5. Laravel-də `$user->toArray()` ilə `UserResource` qarışdırmaq**

`$user->toArray()` model field-lərini verir (hidden-dən başqa). `UserResource` isə controlled format verir. API üçün həmişə Resource istifadə et.

## Mini müsahibə sualları

**Sual 1**: Niyə Spring-də `@Entity` sinfini birbaşa REST API-dən qaytarmaq tövsiyə olunmur? 3 səbəb ayrılır.

*Cavab*: (1) `LazyInitializationException` - lazy relation-lar transaction bitdikdən sonra serialize olur. (2) Həssas field-lərin sızması (password hash, internal ID). (3) API DB schema-ya tight coupling - DB dəyişəndə API sınır. Əlavə olaraq N+1 query riski var.

**Sual 2**: Laravel-də `FormRequest` və `JsonResource` Spring-də nəyə uyğun gəlir?

*Cavab*: `FormRequest` Spring-in Request DTO-suna uyğundur (validation annotations ilə), `JsonResource` isə Response DTO-suna uyğundur. Hər ikisi entity ilə API contract-ı ayırmaq üçün istifadə olunur.

**Sual 3**: Java 16+ record DTO üçün niyə yaxşı seçimdir?

*Cavab*: Record avtomatik `equals`, `hashCode`, `toString`, constructor və accessor method-ları yaradır. Immutable-dir (field-lər final-dır). Az kod yazılır. DTO tələblərinin hamısına uyğundur - sadəcə data daşımaq, mutation olmamaq.

**Sual 4**: MapStruct vs ModelMapper - hansını seçmək?

*Cavab*: MapStruct daha yaxşıdır. Compile-time kod yaradır, runtime-da reflection yoxdur, sürəti manual mapping ilə eynidir. Səhvlər compile zamanı görsənir. ModelMapper runtime reflection-dir, yavaşdır, səhvlər runtime-da çıxır.
