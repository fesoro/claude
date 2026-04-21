# Records və Data Classes (Java Records vs PHP Readonly Classes)

## Giriş

Data class — bu, əsas məqsədi **data daşımaq** olan class-dır. DTO, value object, API response, config object — hamısı data class-lardır. Belə class-larda adətən bu kod çox təkrar olunur: constructor, getter-lər, `equals()`, `hashCode()`, `toString()`.

**Java** uzun müddət boilerplate-lə tanınırdı. Developerlər Lombok istifadə edirdi (`@Data`, `@Value`) və ya IDE generator-lara arxalanırdı. **Java 14** preview olaraq `record` açar sözünü təqdim etdi, **Java 16**-da stable oldu. Record — özü immutable data class-dır, bütün boilerplate-i avtomatik yaradır.

**PHP** tarix boyu property-ləri manual yazırdı. **PHP 8.0** constructor property promotion verdi, **PHP 8.1** `readonly` property-ləri, **PHP 8.2** `readonly` class-ları, **PHP 8.4** isə asymmetric visibility-ni (məs. `public private(set)`) gətirdi. Bu addımlar PHP-ni Java Record-una yaxınlaşdırır, amma tam eyni deyil.

---

## Java-da istifadəsi

### 1) Klassik POJO (Record-dan əvvəl)

```java
public final class User {
    private final Long id;
    private final String email;
    private final String name;

    public User(Long id, String email, String name) {
        this.id = id;
        this.email = email;
        this.name = name;
    }

    public Long getId() { return id; }
    public String getEmail() { return email; }
    public String getName() { return name; }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof User u)) return false;
        return Objects.equals(id, u.id)
            && Objects.equals(email, u.email)
            && Objects.equals(name, u.name);
    }

    @Override
    public int hashCode() {
        return Objects.hash(id, email, name);
    }

    @Override
    public String toString() {
        return "User{id=" + id + ", email=" + email + ", name=" + name + "}";
    }
}

// 35+ sətir boilerplate 3 field üçün
```

### 2) Lombok ilə qısaldılmış versiya

```java
import lombok.Value;

@Value                       // final class + final field-lər + getter + equals + hashCode + toString
public class User {
    Long id;
    String email;
    String name;
}

// Və ya daha elastik
@Data                        // mutable, setter-lər də var
@AllArgsConstructor
@NoArgsConstructor
@Builder
public class UserDto {
    private Long id;
    private String email;
    private String name;
}
```

Lombok compile zamanı bytecode-a boilerplate əlavə edir. Amma IDE konfiqurasiyası, annotation processor, debugger problemləri var.

### 3) Record — Java 16 (stable)

```java
public record User(Long id, String email, String name) {}

// Bu qədər. Avtomatik yaradılanlar:
// - private final field-lər: id, email, name
// - public constructor: new User(Long, String, String)
// - accessor metodlar: user.id(), user.email(), user.name()   — NOT getId()
// - equals() — bütün field-ləri müqayisə edir
// - hashCode() — bütün field-lərdən
// - toString() — "User[id=1, email=a@b.com, name=Orkhan]"
// - class özü implicitly final

User u = new User(1L, "orkhan@example.com", "Orkhan");
System.out.println(u.email());         // orkhan@example.com
System.out.println(u);                  // User[id=1, email=orkhan@example.com, name=Orkhan]
```

Diqqət: Record-da getter `id()`-dir, `getId()` deyil. JavaBeans konvensiyasına uyğun gəlmir — bu bəzi framework-lərdə problem yarada bilər.

### 4) Compact constructor — validasiya

```java
public record Email(String value) {
    // compact constructor — parametrlər avtomatik bind olunur
    public Email {
        if (value == null || !value.contains("@")) {
            throw new IllegalArgumentException("Yanlış email: " + value);
        }
        value = value.toLowerCase().trim();    // normalize — field-ə yazılır
    }
}

Email e = new Email("  ORKHAN@EXAMPLE.COM  ");
System.out.println(e.value());              // orkhan@example.com
```

Burada compact constructor body-sində `this.value = value` YOX — avtomatikdir. Sən sadəcə yoxlama və normalize edirsən.

### 5) Canonical constructor-u override etmək

```java
public record Range(int min, int max) {
    // canonical constructor — tam versiyası
    public Range(int min, int max) {
        if (min > max) {
            throw new IllegalArgumentException("min > max");
        }
        this.min = min;
        this.max = max;
    }

    // əlavə (secondary) constructor — canonical-ı çağırmalıdır
    public Range(int max) {
        this(0, max);
    }
}
```

### 6) Static factory metodlar və əlavə metodlar

```java
public record Money(BigDecimal amount, Currency currency) {

    // static factory
    public static Money zero(Currency currency) {
        return new Money(BigDecimal.ZERO, currency);
    }

    public static Money of(String amount, String currencyCode) {
        return new Money(new BigDecimal(amount), Currency.getInstance(currencyCode));
    }

    // business metod
    public Money add(Money other) {
        if (!currency.equals(other.currency)) {
            throw new IllegalArgumentException("Fərqli valyuta");
        }
        return new Money(amount.add(other.amount), currency);
    }

    public boolean isZero() {
        return amount.compareTo(BigDecimal.ZERO) == 0;
    }
}

Money price = Money.of("99.99", "USD");
Money tax   = Money.of("8.50", "USD");
Money total = price.add(tax);        // yeni Record — immutable
```

### 7) Record interface implement edə bilər (amma extends yox)

```java
public interface Identifiable {
    Long id();
}

public interface Timestamped {
    Instant createdAt();
}

public record Order(Long id, String status, Instant createdAt)
    implements Identifiable, Timestamped {

    // default metodlar interface-dən gəlir
    public boolean isFresh() {
        return Duration.between(createdAt, Instant.now()).toMinutes() < 5;
    }
}
```

Record `final` implicit-dir — `extends` edə bilmirsən. Bu dizayn qərarıdır: data class-ın inheritance-i mürəkkəb `equals()` probleminə gətirir.

### 8) Record patterns — Java 21 (stable)

```java
record Point(int x, int y) {}
record Rectangle(Point topLeft, Point bottomRight) {}

Object o = new Rectangle(new Point(0, 0), new Point(10, 20));

// Deconstruction
if (o instanceof Rectangle(Point(int x1, int y1), Point(int x2, int y2))) {
    System.out.println("Eni: " + (x2 - x1) + ", Hündürlüyü: " + (y2 - y1));
}

// Switch-də də işləyir
String describe = switch (o) {
    case Rectangle(Point(var x1, var y1), Point(var x2, var y2))
        when x1 == x2 && y1 == y2 -> "Nöqtə";
    case Rectangle(Point p1, Point p2) -> "Düzbucaqlı " + p1 + "-dən " + p2 + "-ə";
    default -> "Məlum deyil";
};
```

### 9) Spring Boot + Jackson ilə Record

```java
// Request DTO
public record CreateUserRequest(
    @NotBlank @Email String email,
    @NotBlank @Size(min = 2, max = 50) String name,
    @Min(18) int age
) {}

// Response DTO
public record UserResponse(Long id, String email, String name, Instant createdAt) {}

@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserService service;

    public UserController(UserService service) {
        this.service = service;
    }

    @PostMapping
    public ResponseEntity<UserResponse> create(@Valid @RequestBody CreateUserRequest req) {
        User saved = service.create(req.email(), req.name(), req.age());
        UserResponse body = new UserResponse(saved.getId(), saved.getEmail(),
                                             saved.getName(), saved.getCreatedAt());
        return ResponseEntity.status(HttpStatus.CREATED).body(body);
    }
}
```

Jackson Java 16+-da Record-u avtomatik dəstəkləyir — `@JsonCreator` gərək deyil.

### 10) JPA ilə problem

```java
// BU İŞLƏMİR — JPA entity record ola bilməz
@Entity
public record UserEntity(@Id Long id, String email) {}
// JPA default constructor tələb edir + field-lər mutable olmalıdır (dirty tracking üçün)
```

Record JPA entity olmaq üçün uyğun DEYİL. JPA proxy yaratmaq, lazy loading, dirty checking üçün mutable field-lərə ehtiyac duyur. Record immutable-dir.

Həll: Entity adi class olsun, DTO record olsun.

```java
@Entity
public class UserEntity {
    @Id private Long id;
    private String email;
    // ... getter/setter, default constructor
}

public record UserDto(Long id, String email) {
    public static UserDto from(UserEntity e) {
        return new UserDto(e.getId(), e.getEmail());
    }
}

// JPA projection — Record birbaşa
public interface UserRepository extends JpaRepository<UserEntity, Long> {
    @Query("select new com.example.UserDto(u.id, u.email) from UserEntity u")
    List<UserDto> findAllDtos();
}
```

### 11) Record vs Class vs Interface vs Enum

| Xüsusiyyət | Record | Class | Interface | Enum |
|---|---|---|---|---|
| Immutable | Bəli (default) | Seçim | N/A | Bəli |
| Field | Yalnız constructor-da | İstənilən | Yalnız `static final` | Instance-da |
| Inheritance | `extends` YOX | `extends` BİR | `extends` çoxlu | Yox |
| `implements` | Bəli | Bəli | Bəli | Bəli |
| `equals/hashCode` | Avtomatik | Manual | N/A | Reference |
| Constructor | Canonical + Compact | İstənilən | Yox | private |
| Əsas istifadə | DTO, VO | Domain logic | Abstraction | Sabit dəst |

---

## PHP-də istifadəsi

### 1) Klassik PHP 7 stili

```php
<?php
class User
{
    private int $id;
    private string $email;
    private string $name;

    public function __construct(int $id, string $email, string $name)
    {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
    }

    public function getId(): int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }

    public function equals(User $other): bool
    {
        return $this->id === $other->id
            && $this->email === $other->email
            && $this->name === $other->name;
    }
}
```

### 2) Constructor property promotion (PHP 8.0)

```php
<?php
class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
    ) {}
}

$u = new User(1, 'orkhan@example.com', 'Orkhan');
echo $u->email;           // orkhan@example.com

// Hələ də mutable — field-ləri dəyişmək olar
$u->email = 'new@example.com';       // icazə var
```

Qısaldılıb, amma hələ də mutable-dir. Əsl immutability yoxdur.

### 3) `readonly` property (PHP 8.1)

```php
<?php
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
    ) {}
}

$u = new User(1, 'orkhan@example.com', 'Orkhan');
echo $u->email;                        // oxumaq olur
$u->email = 'new@example.com';         // Error: Cannot modify readonly property
```

Hər field üçün `readonly` yazmaq lazımdır. Boilerplate azalmış olsa da, Java Record qədər təmiz deyil.

### 4) `readonly` class (PHP 8.2)

```php
<?php
readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
    ) {}
}

// Bütün property-lər avtomatik readonly oldu
$u = new User(1, 'orkhan@example.com', 'Orkhan');
$u->email = 'x';                       // Error
```

Bu Java Record-una ən yaxın variantdır. Amma fərqlər var:
- `equals()`, `toString()` avtomatik yaradılmır
- Getter metodları yoxdur — property birbaşa public-dir
- Inheritance qaydaları fərqlidir

### 5) Əlavə metodlar və validasiya

```php
<?php
readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Yanlış email: {$value}");
        }
    }

    public function domain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function equals(Email $other): bool
    {
        return strcasecmp($this->value, $other->value) === 0;
    }
}

$e = new Email('orkhan@example.com');
echo $e->domain();                     // example.com
```

### 6) Static factory və value object

```php
<?php
readonly class Money
{
    public function __construct(
        public string $amount,             // string — precision itirməmək üçün
        public string $currency,
    ) {}

    public static function zero(string $currency): self
    {
        return new self('0.00', $currency);
    }

    public static function of(string $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Fərqli valyuta');
        }
        return new self(bcadd($this->amount, $other->amount, 2), $this->currency);
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 2) === 0;
    }
}

$price = Money::of('99.99', 'USD');
$tax   = Money::of('8.50', 'USD');
$total = $price->add($tax);            // yeni Money — immutable
```

### 7) Asymmetric visibility (PHP 8.4)

```php
<?php
class User
{
    public function __construct(
        public private(set) int $id,              // hər kəs oxuya bilər, yalnız class özü yaza bilər
        public protected(set) string $email,      // inherit-ə icazə
        public string $name,                      // tam açıq
    ) {}

    public function changeEmail(string $newEmail): void
    {
        $this->email = $newEmail;                 // öz class-ından icazə var
    }
}

$u = new User(1, 'a@b.com', 'Orkhan');
$u->name = 'Yeni';                               // icazə var
$u->id = 2;                                      // Error: private(set)
```

Bu feature `readonly`-dən daha elastikdir: xaricdən immutable kimi görsənir, daxildən dəyişmək olur. Laravel Model-ləri belə pattern üçün uyğun gəlir.

### 8) Laravel DTO istifadəsi

```php
<?php
// app/DTO/CreateUserRequest.php
readonly class CreateUserRequest
{
    public function __construct(
        public string $email,
        public string $name,
        public int $age,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            email: $request->validated('email'),
            name:  $request->validated('name'),
            age:   (int) $request->validated('age'),
        );
    }
}

// app/Http/Requests/CreateUserFormRequest.php
class CreateUserFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'name'  => 'required|string|min:2|max:50',
            'age'   => 'required|integer|min:18',
        ];
    }
}

// Controller
class UserController extends Controller
{
    public function store(CreateUserFormRequest $request, UserService $service)
    {
        $dto = CreateUserRequest::fromRequest($request);
        $user = $service->create($dto);

        return response()->json([
            'id'    => $user->id,
            'email' => $user->email,
        ], 201);
    }
}
```

### 9) Eloquent ilə problem

```php
<?php
// BU İŞLƏMİR — Eloquent Model readonly ola bilməz
readonly class User extends Model
{
    public function __construct(
        public int $id,
        public string $email,
    ) {}
}
// Eloquent $attributes array-ı mutable istəyir, magic __set işlətməlidir
```

Eloquent Model-i readonly edə bilmirsən. DTO üçün readonly class-lar istifadə et, Eloquent Model-i öz yerində saxla.

### 10) Spatie Data Package

```php
<?php
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
    ) {}
}

// Avtomatik: from(), toArray(), toJson(), validation rules
$user = UserData::from($request);
return $user->toArray();
```

Bu kitabxana Laravel-də DTO-ları Java Record kimi istifadə etməyə imkan verir — serialize, deserialize, validation bir yerdə.

### 11) Enum ilə immutable pattern (PHP 8.1)

```php
<?php
enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'Gözləyir',
            self::PAID      => 'Ödənib',
            self::SHIPPED   => 'Göndərilib',
            self::DELIVERED => 'Çatdırılıb',
        };
    }
}
```

Enum da data daşıyıcı kimi istifadə olunur — amma fix edilmiş dəst üçün.

---

## Əsas fərqlər

| Xüsusiyyət | Java Record | PHP readonly class |
|---|---|---|
| Versiya | 16 (stable) | 8.2 |
| Açar söz | `record` | `readonly class` |
| Immutable | Bəli | Bəli |
| `equals()` avtomatik | Bəli | Xeyr — manual |
| `hashCode()` avtomatik | Bəli | Yox (PHP-də yoxdur, `spl_object_hash()` var) |
| `toString()` avtomatik | Bəli | Xeyr — `__toString()` manual |
| Getter metodları | `field()` avtomatik | Property birbaşa public |
| Destructuring | Record patterns (21) | Yox (yalnız array) |
| `final` | Implicitly final | Manual `final` |
| Inheritance | Yalnız implements | extends + implements |
| Compact constructor | Bəli | Yox — manual yoxlama |
| ORM uyğun | JPA-yla problem | Eloquent-lə problem |
| Asimmetrik görünmə | Yox | PHP 8.4 `private(set)` |

---

## Niyə belə fərqlər var?

**Java** static tipli dildir, JVM-də bytecode icra olunur. Record-un məqsədi "nominal tuple" yaratmaq idi — field-lərin adı və tipi müqavilənin hissəsidir. Compile zamanı accessor, `equals`, `hashCode`, `toString` bytecode-a əlavə olunur. `final` məcburidir ki, equality konsepsiyası pozulmasın — inheritance data class-da mənasızdır. Record ADT (Algebraic Data Types) istiqamətində addımdır: sealed + record = sum type.

**PHP** dynamic tipli idi, sonra tədricən tiplər, property, attribute, enum əlavə etdi. `readonly` və `readonly class` Java Record-una reaksiya deyil, daha çox "defensive copy əvəzinə dilin özü qoysun" cəhdidir. PHP getter avtomatik yaratmır — bu dilin fəlsəfəsinə ziddir; PHP-də property birbaşa açıq ola bilər (`public`). PHP 8.4-dəki asymmetric visibility isə başqa yol seçir: tam immutable deyil, oxuma/yazma ayrıca idarə olunur. Bu daha elastikdir, amma mürəkkəb qaydalar tələb edir.

**Ümumi səbəb:** Java ekosistemi Lombok, Jackson, Spring kimi generasiya ilə işləyən alətlərlə dolu idi. Record bu problemi dil səviyyəsində həll etdi. PHP ekosistemində Spatie Data, DTO kitabxanaları var, amma dil özü "batteries included" deyil — daha çox blok verir, sən qurursan.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `record` açar sözü və nominal tuple konsepsiyası
- Canonical + compact constructor
- Record patterns (deconstruction) — Java 21
- Nested record patterns
- Avtomatik `equals`/`hashCode`/`toString`
- Getter metodları (`field()`)
- Unnamed patterns `_` (Java 22+)

**Yalnız PHP-də:**
- Asymmetric visibility (`public private(set)`) — PHP 8.4
- `readonly class` + enum arayı seçim variantı
- Property hooks (PHP 8.4) — computed property-lər
- Magic metodlar (`__get`, `__set`, `__call`) ilə dinamik davranış

**Hər ikisində var:**
- Immutable data class-lar
- Validation in constructor
- Static factory metodlar
- Interface implement etmək
- Value object pattern

---

## Best Practices

1. **DTO və Value Object üçün Record/readonly class istifadə et.** Entity üçün yox.
2. **Compact constructor-da validasiya qoy** (Java) və ya `__construct` body-də (PHP).
3. **Record-da JPA annotation-ları qoyma** — ayrı DTO yaz.
4. **Eloquent Model-i readonly etmə** — DTO-ları readonly et.
5. **Java: Record-da business logic minimal olsun.** Əgər çoxlu metod lazımdırsa, adi class yaxşıdır.
6. **PHP 8.4-də asymmetric visibility-dən istifadə et** — tam readonly olmayan ssenarilər üçün.
7. **Jackson ilə Record: `@JsonCreator` lazım deyil** — Java 16+ avtomatik tapır.
8. **Spring Boot request DTO-ları record olsun** — validation annotation-ları ilə.
9. **Laravel-də Spatie Data istifadə et** — DTO boilerplate-i azaldır.
10. **Nested record pattern-ləri öyrən** (Java 21) — AST walking, tree processing üçün çox güclüdür.

---

## Yekun

- **Java Record** (16 stable) — DTO və value object üçün ən təmiz dil səviyyə həlli. Boilerplate-i sıfıra endirir, avtomatik `equals`/`hashCode`/`toString` verir.
- **PHP readonly class** (8.2) — ən yaxın ekvivalent, amma `equals`, getter manual yazılır.
- **Record patterns** (Java 21) — deconstruction və ADT-stil kod üçün çox güclü; PHP-də obyekt destructuring yoxdur.
- **JPA Record-la uyğun deyil** — entity üçün adi class, DTO üçün record yaz.
- **Eloquent readonly-lə uyğun deyil** — DTO üçün readonly class istifadə et.
- **PHP 8.4 asymmetric visibility** — readonly-yə alternativ, daha elastik.
- **Sealed + Record = ADT** — Java 21-dən sonra funksional-stil domain modelling mümkündür.
- **Record `final` implicit-dir** — inheritance yoxdur, bu qəsdən edilib.
- **Spring Boot DTO-ları Record olaraq yaz** — `@Valid @RequestBody` işləyir.
- **Laravel-də Spatie Data** — Java Record-a ən yaxın developer experience.
