# Lombok annotasiyaları Spring-də: Spring vs Laravel

> **Seviyye:** Beginner ⭐

## Giriş

Java-nın şikayət olunan cəhətlərindən biri "boilerplate" koddur - getter, setter, constructor, toString, equals, hashCode yazmaq çox vaxt aparır. **Lombok** bu problemi annotation processor ilə həlli edir: annotation yazırsan, compile zamanı Lombok kodu avtomatik yaradır. Spring Boot layihələrinin çoxusunda default istifadə olunur.

Laravel-də PHP-nin dinamik xüsusiyyəti səbəbiylə bu problem yoxdur: `__get`, `__set` magic metodları var, PHP 8 constructor promotion, traits ilə kod paylaşılır. Bu faylda Lombok-un əsas annotasiyaları, onların təhlükələri və Laravel-də müqayisəsi var.

## Spring/Java-də

### Lombok nədir?

Lombok bir kiçik kütübxanədir, amma adi dependency deyil - **annotation processor**-dur. Compile zamanı javac-a birləşir və annotation-lara baxıb kodu avtomatik yaradır. Runtime-da heç bir reflection yoxdur, performance impact yoxdur.

**Setup:**

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.projectlombok</groupId>
    <artifactId>lombok</artifactId>
    <version>1.18.30</version>
    <scope>provided</scope>
</dependency>
```

**IntelliJ IDEA:** Lombok plugin lazımdır (Settings -> Plugins -> Lombok). Sonra `Settings -> Build -> Compiler -> Annotation Processors -> Enable` yandırmaq lazımdır.

**Test:** Kodu yaz, `mvn compile` et, sonra `target/classes/User.class` faylına `javap` ilə bax - generated metodlar görünəcək.

### @Getter, @Setter

Ən sadə iki annotasiya. Bütün field-lərə getter/setter yaradır.

```java
import lombok.Getter;
import lombok.Setter;

@Getter
@Setter
public class User {
    private Long id;
    private String name;
    private String email;
    private String password;
}

// Lombok compile zamanı bu kodu yaradır (gözə görsənməz):
// public Long getId() { return id; }
// public void setId(Long id) { this.id = id; }
// public String getName() { return name; }
// public void setName(String name) { this.name = name; }
// ... (bütün field-lər üçün)

// İstifadə
User user = new User();
user.setName("Orxan");
System.out.println(user.getName()); // "Orxan"
```

**Field üzərində də istifadə etmək olar:**

```java
public class User {
    @Getter @Setter private Long id;
    @Getter private String name;  // setter yox, yalnız getter
    @Getter(AccessLevel.PROTECTED) private String secret; // protected getter
    private String password;       // heç biri yox
}
```

### @ToString

`toString()` metodunu avtomatik yaradır.

```java
@Getter
@Setter
@ToString
public class User {
    private Long id;
    private String name;
    private String email;
    private String password;
}

// Generated:
// public String toString() {
//     return "User(id=" + id + ", name=" + name +
//            ", email=" + email + ", password=" + password + ")";
// }

User user = new User();
user.setId(1L);
user.setName("Orxan");
System.out.println(user);
// User(id=1, name=Orxan, email=null, password=null)
```

**Həssas field-ləri xaric etmək:**

```java
@ToString(exclude = {"password", "creditCard"})
public class User {
    private Long id;
    private String name;
    private String password;     // toString-də yoxdur
    private String creditCard;   // toString-də yoxdur
}

// Və ya yalnız bəzi field-ləri daxil etmək:
@ToString(onlyExplicitlyIncluded = true)
public class User {
    @ToString.Include private Long id;
    @ToString.Include private String name;
    private String password;  // default xaricdir
}
```

### @EqualsAndHashCode

`equals()` və `hashCode()` avtomatik yaradır.

```java
@EqualsAndHashCode
public class User {
    private Long id;
    private String email;
}

// Generated equals() bütün field-ləri müqayisə edir
// Generated hashCode() bütün field-lərin hash-ini birləşdirir

User u1 = new User();
u1.setEmail("a@b.com");

User u2 = new User();
u2.setEmail("a@b.com");

System.out.println(u1.equals(u2)); // true
```

**Yalnız bəzi field-lər ilə:**

```java
@EqualsAndHashCode(onlyExplicitlyIncluded = true)
public class User {
    @EqualsAndHashCode.Include private String email; // yalnız bu
    private String name;
    private String password;
}
```

**callSuper ilə inheritance:**

```java
@EqualsAndHashCode(callSuper = true)
public class Admin extends User {
    private String adminLevel;
}
// Admin-in equals-i User field-lərini də nəzərə alır
```

### @Data - hamısı bir yerdə

`@Data` aşağıdakı annotasiyaların birləşməsidir:
- `@Getter` (bütün field-lərə)
- `@Setter` (non-final field-lərə)
- `@ToString`
- `@EqualsAndHashCode`
- `@RequiredArgsConstructor`

```java
import lombok.Data;

@Data
public class User {
    private Long id;
    private String name;
    private String email;
}

// Demək olar ki 50+ sətir kod avtomatik yaradılır
```

**Qeyd:** `@Data` POJO/DTO üçün yaxşı seçimdir, amma **JPA Entity-lərdə təhlükəlidir** (aşağıda izah ediləcək).

### @Value - immutable @Data

`@Value` = immutable variant. Bütün field-lər `final`, class da `final`, yalnız getter (setter yoxdur).

```java
import lombok.Value;

@Value
public class UserDto {
    Long id;        // default - private final
    String name;
    String email;
}

// Generated:
// public final class UserDto {
//     private final Long id;
//     private final String name;
//     private final String email;
//     // Only getters, no setters, + equals/hashCode/toString
// }

UserDto dto = new UserDto(1L, "Orxan", "a@b.com");
// dto.setName("..."); - SEHV, setter yoxdur!
System.out.println(dto.getName()); // OK
```

**Qeyd:** Java 16+ Record varsa, `@Value` ehtiyacı azalır. Record built-in immutable tipdir.

### Constructor annotasiyaları

```java
// @NoArgsConstructor - parametrsiz
@NoArgsConstructor
public class User {
    private Long id;
    private String name;
}
// Generated: public User() {}

// @AllArgsConstructor - bütün field-lər üçün
@AllArgsConstructor
public class User {
    private Long id;
    private String name;
    private String email;
}
// Generated: public User(Long id, String name, String email) { ... }
// İstifadə: new User(1L, "Orxan", "a@b.com")

// @RequiredArgsConstructor - yalnız final və @NonNull field-lər
@RequiredArgsConstructor
public class UserService {
    private final UserRepository userRepository;  // daxildir
    private final EmailService emailService;      // daxildir
    private String cache;                         // daxil deyil (non-final)
}
// Generated constructor: public UserService(UserRepository, EmailService)
```

**Spring-də ən çox `@RequiredArgsConstructor` istifadə olunur** - constructor injection üçün ideal:

```java
@Service
@RequiredArgsConstructor  // constructor yaratmaq lazım deyil
public class UserService {
    private final UserRepository userRepository;
    private final PasswordEncoder passwordEncoder;
    private final EmailService emailService;

    public User register(CreateUserRequest request) {
        // userRepository, passwordEncoder, emailService hazırdır
        // Spring onları constructor ilə inject etdi
    }
}
```

### @Builder - Builder pattern

Builder pattern obyekt yaratmağın rahat üsuludur, xüsusən çox parametr olanda.

```java
import lombok.Builder;

@Builder
@Getter
public class User {
    private Long id;
    private String name;
    private String email;
    private int age;
    private boolean active;
}

// İstifadə:
User user = User.builder()
    .name("Orxan")
    .email("a@b.com")
    .age(30)
    .active(true)
    .build();
```

**@Builder.Default:**

```java
@Builder
public class Config {
    @Builder.Default
    private int timeout = 30;        // Default 30

    @Builder.Default
    private List<String> tags = new ArrayList<>();  // Default empty list

    private String name;
}

Config c = Config.builder().name("prod").build();
// timeout = 30, tags = [] avtomatik
```

**@Singular for collections:**

```java
@Builder
public class Team {
    private String name;

    @Singular
    private List<String> members;
}

Team team = Team.builder()
    .name("Backend")
    .member("Ali")      // bir-bir əlavə
    .member("Aysel")
    .member("Orxan")
    .build();
// team.getMembers() = ["Ali", "Aysel", "Orxan"]
```

### @Slf4j - logger avtomatik

Hər class-da `Logger log = LoggerFactory.getLogger(...)` yazmaq yerinə:

```java
import lombok.extern.slf4j.Slf4j;

@Slf4j
@Service
public class UserService {

    public User findById(Long id) {
        log.debug("Finding user with id={}", id);  // hazır log obyekti

        try {
            return userRepository.findById(id).orElseThrow();
        } catch (Exception e) {
            log.error("User not found: {}", id, e);
            throw e;
        }
    }
}

// Generated kod:
// private static final Logger log = LoggerFactory.getLogger(UserService.class);
```

Variantlar: `@Log4j2`, `@CommonsLog`, `@JBossLog`, `@Log` - müxtəlif logging kütübxanələri üçün.

### @SneakyThrows (mübahisəli)

Checked exception-ları "gizlətmək" üçün. Exception-i catch etmək lazım olmur.

```java
@SneakyThrows
public String readFile(String path) {
    return Files.readString(Paths.get(path));
    // Normalda: throws IOException yazmaq lazım idi
    // @SneakyThrows onu "swallows" edir - compile-time check by-pass
}
```

**Niyə mübahisəli?** Checked exception-ları Java səbəbdən qoyub - developer bilərək həll etsin. `@SneakyThrows` bunu pozur. Library code-da OK, business logic-də pis praktikadır.

### @Cleanup (tövsiyə olunmur)

Resource bağlamaq üçün. Amma Java 7+ try-with-resources daha yaxşıdır.

```java
// @Cleanup istifadəsi
@Cleanup InputStream in = new FileInputStream("file.txt");
// Block bitəndən sonra in.close() avtomatik

// Modern - try-with-resources elə eyni işi görür, daha açıq
try (InputStream in = new FileInputStream("file.txt")) {
    // istifadə
} // avtomatik close
```

Yazılı kod ilə try-with-resources üstünlük ver.

### Delombok - Lombok kodunu görüntülə

Lombok "magic" görünür. Bəzən real generated kod görmək lazım olur.

```bash
mvn lombok:delombok
# Lombok annotasiyalarının yerinə real yazılmış kodu yaradır
# target/generated-sources/delombok/ folder-ində
```

Tutuşdurmaq: `javap -p target/classes/User.class` - compiled bytecode-də metod siyahısı göstərir.

### @Data və JPA Entity - BÖYÜK PROBLEM

Bu Lombok-un ən çox səhv istifadə olunduğu yerdir.

```java
// SEHV - @Data JPA Entity-de
@Entity
@Data   // TEHLUKELI!
public class User {
    @Id @GeneratedValue
    private Long id;

    private String name;

    @OneToMany(mappedBy = "user", fetch = FetchType.LAZY)
    private List<Order> orders;

    @ManyToOne
    private Role role;
}
```

**Problem 1: toString() lazy trigger**

`@ToString` default bütün field-ləri daxil edir. `orders` və `role` lazy-dir. `user.toString()` çağırılanda lazy load başladılır - `LazyInitializationException` (transaction yoxdur) ya da N+1 query.

**Problem 2: equals/hashCode id-yə əsaslanmalıdır (JPA best practice)**

JPA entity-si id-si set olunmaya-da ola bilər (save-dən əvvəl). `@EqualsAndHashCode` bütün field-ləri istifadə edir. Bu, entity-nin HashMap-da davranışını sıxıntıya salır:

```java
Set<User> users = new HashSet<>();
User u = new User();
u.setName("Orxan");
users.add(u);
// Sonra set et:
userRepository.save(u);  // id = 5 dəyişdi
// İndi u-nun hashCode-u dəyişdi -> u HashSet-də yoxdur kimi!
users.contains(u); // false!
```

**Problem 3: Bidirectional StackOverflow**

```java
@Entity @Data
public class User {
    @OneToMany(mappedBy = "user")
    private List<Order> orders;
}

@Entity @Data
public class Order {
    @ManyToOne
    private User user;
}

user.toString() -> orders.toString() -> each order.toString() -> user.toString() -> ...
// StackOverflowError!

user.equals(other) -> orders.equals(...) -> user.equals(...) -> ...
// StackOverflowError!
```

**Düzgün yanaşma:**

```java
@Entity
@Getter @Setter
@ToString(onlyExplicitlyIncluded = true)
@EqualsAndHashCode(onlyExplicitlyIncluded = true)
public class User {

    @Id @GeneratedValue
    @ToString.Include
    @EqualsAndHashCode.Include
    private Long id;

    @ToString.Include
    private String name;

    // orders və role toString/equals-də iştirak etmir
    @OneToMany(mappedBy = "user", fetch = FetchType.LAZY)
    private List<Order> orders;
}
```

### @Data nə vaxt yaxşıdır?

- **DTO/POJO üçün**: `@Data` idealdır - data daşıyan obyekt, əlaqə yoxdur.
- **Config obyekt üçün**: `@Data` ilə `@ConfigurationProperties`.
- **Test fixture üçün**: `@Data` ilə test data yaratmaq asan.

```java
// Yaxşı istifadə - DTO
@Data
public class CreateUserRequest {
    private String name;
    private String email;
    private String password;
}
```

### Lombok nə vaxt istifadə etməməli?

1. **JPA Entity-lərdə `@Data`** - yuxarıdakı səbəblərə görə. `@Getter @Setter` və əldə yazılmış equals/hashCode daha yaxşıdır.
2. **Java 16+ Record varsa** - immutable DTO üçün Record daha təmizdir, Lombok ehtiyacı yoxdur.
3. **Serialization/reflection-a əsaslanan library-lər** ilə bəzən problem olur - Jackson daha düşmən deyil, amma exotic library-lər ilə sınaqdan keçirmək lazımdır.

## Laravel/PHP-də

Lombok-un heç bir birbaşa equivalent-i PHP-də yoxdur, çünki PHP-nin özü dinamik dildir. Amma PHP-in müstəqil xüsusiyyətləri Lombok-un etdiyi işlərin çoxunu örtür.

### PHP 8 Constructor Property Promotion - @RequiredArgsConstructor qarşılığı

Kod azaltmaq üçün PHP 8-in ən güclü özəlliyidir.

```php
// Əski PHP
class UserService {
    private UserRepository $userRepository;
    private EmailService $emailService;

    public function __construct(UserRepository $repo, EmailService $email) {
        $this->userRepository = $repo;
        $this->emailService = $email;
    }
}

// PHP 8 Constructor Promotion
class UserService {
    public function __construct(
        private UserRepository $userRepository,
        private EmailService $emailService,
    ) {}
}
// Kod 3 dəfə qısa! Field-lər avtomatik yaradılır və set olunur
```

### Magic metodlar - @Getter/@Setter qarşılığı

```php
class User {
    private string $name;
    private string $email;

    public function __get($property) {
        return $this->$property;   // hər field-ə avtomatik giriş
    }

    public function __set($property, $value) {
        $this->$property = $value; // hər field-ə avtomatik set
    }
}

$user = new User();
$user->name = 'Orxan';         // __set çağırılır
echo $user->name;              // __get çağırılır
```

Laravel-in Eloquent Model-ı bu prinsip üzərində qurulub - `$user->name` DB sütunundan oxuyur, `__get` ilə.

### PHP 8.4 Property Hooks - gələcək

PHP 8.4 (2024 noyabr) ilə property hooks gəldi - Lombok-un getter/setter-i ilə çox yaxın:

```php
class User {
    public string $fullName {
        get => "{$this->firstName} {$this->lastName}";
        set => strtoupper($value);
    }

    public string $firstName = '';
    public string $lastName = '';
}

$u = new User();
$u->firstName = 'Orxan';
$u->lastName = 'Ali';
echo $u->fullName;  // "Orxan Ali" (get hook)
$u->fullName = 'orxan ali';  // set hook - özəl məntiq işləyə bilər
```

### Traits - kod paylaşımı (Lombok-da yoxdur)

```php
trait HasTimestamps {
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
}

class User {
    use HasTimestamps; // createdAt/updatedAt və metodlar burdan gəlir
    private string $name;
}

class Post {
    use HasTimestamps; // eyni kod, yeni class-da
    private string $title;
}
```

Trait-lər Lombok-da yoxdur - Java-da interface default metodları ilə hissəcə əvəz olunur.

### Laravel-də Lombok-bənzər helper-lər

```php
// Eloquent model - Active Record, getter/setter magic ilə
class User extends Model {
    protected $fillable = ['name', 'email'];
    // $user->name avtomatik işləyir - Model __get/__set istifadə edir
}

// toString PHP-də __toString magic metodu ilə
class Money {
    public function __construct(private int $amount) {}

    public function __toString(): string {
        return number_format($this->amount / 100, 2) . ' AZN';
    }
}

echo new Money(1500); // "15.00 AZN"

// equals Eloquent-də:
$user1->is($user2); // primary key müqayisəsi
```

## Əsas fərqlər

| Xüsusiyyət | Spring (Lombok) | Laravel (PHP native) |
|---|---|---|
| **Getter/Setter avtomatik** | `@Getter @Setter` | `__get`/`__set` magic, PHP 8.4 hooks |
| **Constructor avtomatik** | `@RequiredArgsConstructor`, `@AllArgsConstructor` | PHP 8 constructor promotion |
| **toString avtomatik** | `@ToString` | `__toString` magic metodu |
| **equals/hashCode** | `@EqualsAndHashCode` | `==` loose, `===` strict, manual metod |
| **Full package** | `@Data` (hamısı birlikdə) | Yoxdur (bütün magic metodlar ayrı) |
| **Immutable class** | `@Value`, Record (J16+) | `readonly` class (PHP 8.2+) |
| **Builder pattern** | `@Builder` | Manual ya da library |
| **Logger** | `@Slf4j` | Laravel `Log::info(...)` facade |
| **Kod paylaşımı** | Inheritance, interface default | Traits |
| **Compile-time processing** | Lombok annotation processor | Yoxdur (PHP runtime) |

## Niyə belə fərqlər var?

**Java və Lombok fəlsəfəsi:**

1. **Statik tipli dil səbəbiylə boilerplate:** Java-da hər field-ə getter/setter lazımdır, çünki field-in tipi compile zamanı bilinir və dəyişməz. Bu güclü type safety verir, amma kod çox olur. Lombok bu "vergi"ni azaldır.

2. **Compile-time generation:** Lombok performance pul ödəmir - annotation processor kodu compile zamanı yaradır, runtime-da Lombok yoxdur.

3. **"Explicit better than implicit" ziddiyəti:** Lombok magic kimi görünür - görünməyən kod yaradır. Bəziləri bunu sevmir, ona görə Record (Java 16+) daha açıq alternativdir.

**PHP və Laravel fəlsəfəsi:**

1. **Dinamik tip - boilerplate az:** PHP-də field tipini declare etmək lazım deyil (əski versiyalar), dildə `__get`/`__set` magic metodları var. Lombok kimi tool ehtiyacı az olur.

2. **Constructor promotion və property hooks:** PHP 8+ Java-da Lombok-un etdiyi bəzi işlər dilin özünə daxil olub. Gələcəkdə hətta daha yaxın ola bilər.

3. **Traits - horizontal reuse:** PHP traits Java-da yoxdur (interface default metodları parsial əvəzdir). Traits kod paylaşmaq üçün elegant üsuldur.

## Ümumi səhvlər (Beginner traps)

### 1. JPA Entity-də `@Data`

```java
// SEHV - @Data + @Entity + bidirectional = StackOverflowError
@Entity @Data
public class User {
    @OneToMany(mappedBy = "user") private List<Order> orders;
}

// DUZGUN
@Entity
@Getter @Setter
@ToString(onlyExplicitlyIncluded = true)
@EqualsAndHashCode(onlyExplicitlyIncluded = true)
public class User {
    @Id @ToString.Include @EqualsAndHashCode.Include
    private Long id;
    // ...
}
```

### 2. IntelliJ Lombok plugin unutma

Plugin olmasa IntelliJ generated metodları "görmür" - qırmızı cızıqlar ilə göstərir. Kod compile olur, amma editor içində `user.getName()` "metod yoxdur" kimi göstərilir. Çözüm: Lombok plugin install et.

### 3. `@Data` DTO-larda OK, Entity-lərdə TEHLUKELI

DTO - data daşımaq - `@Data` ideal.
Entity - DB idarə olunan - `@Data` təhlükəli.

### 4. `@SneakyThrows` overuse

```java
// SEHV - hər yerdə @SneakyThrows
@SneakyThrows
public User findUser(Long id) {
    // checked exception gizlədilir, developer bilmir ki nə ata bilər
}

// DUZGUN - explicit exception handling
public User findUser(Long id) {
    try {
        return userRepo.findById(id).orElseThrow();
    } catch (DataAccessException e) {
        throw new UserNotFoundException("User not found", e);
    }
}
```

### 5. `@Builder` ilə default field dəyər itmək

```java
// SEHV
@Builder
public class Config {
    private int timeout = 30;  // default dəyər BUILDER-də IŞLƏMİR
}
Config c = Config.builder().build();
// c.getTimeout() == 0, 30 DEYIL!

// DUZGUN
@Builder
public class Config {
    @Builder.Default
    private int timeout = 30;
}
Config c = Config.builder().build();
// c.getTimeout() == 30
```

### 6. Lombok + reflection library confusion

```java
@Data
public class User {
    private String name;
    // Lombok generated: getName(), setName()
}

// Jackson bu field-i serialize edə bilir (getter sayəsində)
// Amma bəzi reflection library-lər Lombok generated metodları tanımaya bilir
```

Hər zaman test et: DTO-nu JSON-a çevir, back-dən yenidən obyekt yarat, equal olduğuna bax.

### 7. `@RequiredArgsConstructor` `final` olmayan field ilə

```java
// SEHV - emailService inject olunmayacaq
@Service
@RequiredArgsConstructor
public class UserService {
    private final UserRepository userRepository;  // constructor-a daxildir
    private EmailService emailService;             // non-final, daxil DEYIL
}

// DUZGUN - hamısı final
@Service
@RequiredArgsConstructor
public class UserService {
    private final UserRepository userRepository;
    private final EmailService emailService;
}
```

### 8. Record vs Lombok @Value - hansını seçmək?

Java 16+ layihədə:
- **Record** - DTO, immutable data üçün default seçim.
- **Lombok @Value** - əvvəlki Java versiyası üçün, ya da inheritance lazım olanda (Record final-dir).

```java
// Modern
public record UserDto(Long id, String name, String email) {}

// Lombok @Value
@Value
public class UserDto {
    Long id;
    String name;
    String email;
}
// Eyni şeyi edir, amma Record daha idiomatikdir
```

## Mini müsahibə sualları

**Sual 1**: Lombok runtime performance-a təsir edir mi?

*Cavab*: Yox. Lombok annotation processor-dur - compile zamanı javac-a qoşulur və kodu generate edir. Runtime-da Lombok yoxdur, generated kod adi Java kodu kimidir. Heç bir reflection yoxdur.

**Sual 2**: `@Data` niyə JPA Entity-də təhlükəlidir?

*Cavab*: 3 əsas səbəb: (1) `@ToString` lazy field-lərə toxunanda `LazyInitializationException` ya da N+1 query yaradır. (2) `@EqualsAndHashCode` bütün field-ləri istifadə edir - id post-save dəyişdikdə hashCode dəyişir, HashSet-lərdə obyekt "itir". (3) Bidirectional əlaqələrdə `toString()`/`equals()` sonsuz recursion yaradır - StackOverflowError.

**Sual 3**: Java 16+ Record və Lombok `@Value` - hansını seçmək?

*Cavab*: Modern layihələrdə Record default seçimdir - dilin özünə daxildir, extra dependency yoxdur, daha açıq sintaksis. Lombok `@Value` əvvəlki Java versiyaları üçün və ya Record-in limitlərini (final class, inheritance yoxdur, no instance fields) əvəz etmək üçün lazımdır. DTO üçün hər ikisi də yaxşı işləyir, amma Record daha idiomatikdir.

**Sual 4**: Lombok-un Laravel/PHP-də analoqu nədir?

*Cavab*: Birbaşa analoq yoxdur. PHP-in özündə çoxlu xüsusiyyət var: (1) Constructor promotion (`__construct(private string $name)`) - `@RequiredArgsConstructor` qarşılığı. (2) `__get`/`__set` magic metodları - `@Getter`/`@Setter`. (3) PHP 8.4 property hooks - daha konkret getter/setter. (4) `__toString` - `@ToString`. (5) Traits - kod paylaşımı (Lombok-da yoxdur). PHP dinamik dil olduğundan Lombok kimi ayrıca tool ehtiyacı azdır.
