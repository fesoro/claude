# 26 — DTO Pattern və Mapping (MapStruct, ModelMapper)

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Niyə DTO istifadə edirik](#niye-dto)
2. [Entity vs DTO vs Domain Model](#entity-dto-domain)
3. [Request DTO və Response DTO](#request-response)
4. [DTO-da validation](#validation)
5. [Nested DTO və pagination wrapper](#nested)
6. [@Entity-ni birbaşa qaytarmamaq](#entity-return)
7. [Manual mapping — static factory metodları](#manual)
8. [MapStruct — compile-time mapping](#mapstruct)
9. [ModelMapper — runtime reflection](#modelmapper)
10. [MapStruct vs ModelMapper müqayisəsi](#muqayise)
11. [Records DTO kimi](#records)
12. [Spring Data projections](#projections)
13. [Tam real nümunə — User modulunun tam kodu](#tam-numune)
14. [Ümumi Səhvlər](#sehvler)
15. [İntervyu Sualları](#intervyu)

---

## 1. Niyə DTO istifadə edirik {#niye-dto}

**DTO — Data Transfer Object.** Şəbəkə üzərindən (HTTP, message queue) ötürülən məlumat obyekti.

### Real həyat analogiyası:

Restoranda **menyu** DTO-dur: müştəriyə yalnız lazım olanı göstərir (yemək adı, qiymət), mətbəxin daxili qeydlərini (məhsulun alış qiyməti, tədarükçü, stok miqdarı) gizlədir. `@Entity` isə — mətbəxin tam qeydi.

### DTO-nun 6 əsas səbəbi:

| Səbəb | İzahat |
|---|---|
| **Decoupling** | API kontraktı DB sxemindən asılı olmur |
| **Təhlükəsizlik** | Həssas field-lər (password, ssn) client-ə sızmır |
| **Over-posting-in qarşısını alma** | Client istədiyi field-ə yaza bilməz |
| **Lazy-loading sızması qarşısını alma** | Hibernate proxy-ləri serializasiya xətası vermir |
| **API versiyalaşdırılması** | V1 və V2 DTO-lar fərqli ola bilər, entity eyni qalır |
| **Performance** | Yalnız lazım olan field-lər ötürülür |

### Problem — entity-ni birbaşa qaytarmaq:

```java
// YANLIŞ yanaşma
@RestController
public class UserController {

    @GetMapping("/users/{id}")
    public User getUser(@PathVariable Long id) {
        return userRepository.findById(id).orElseThrow();
        // Problemlər:
        // 1) password field JSON-a düşür
        // 2) Lazy-loaded orders -> LazyInitializationException
        // 3) DB sxem dəyişsə API kontraktı sınır
        // 4) Client id-ni göndərərək update edə bilər
    }
}
```

### Həll — DTO:

```java
@RestController
public class UserController {

    @GetMapping("/users/{id}")
    public UserResponseDto getUser(@PathVariable Long id) {
        User user = userRepository.findById(id).orElseThrow();
        return UserResponseDto.from(user);  // yalnız ictimai field-lər
    }
}
```

---

## 2. Entity vs DTO vs Domain Model {#entity-dto-domain}

Üç fərqli məfhum var — qarışdırmaq olmaz:

| Konsept | Məqsəd | Annotasiyalar | Nümunə |
|---|---|---|---|
| **Entity** | DB sətrini təmsil edir | `@Entity`, `@Table`, `@Column`, `@Id` | `UserEntity` |
| **Domain Model** | Biznes məntiqi | çox vaxt annotasiyasız | `User` (POJO) |
| **DTO** | API-da ötürülür | `@JsonProperty`, `@NotNull` | `UserResponseDto` |

```java
// 1) Entity — DB-ə yazılır
@Entity
@Table(name = "users")
public class UserEntity {
    @Id @GeneratedValue
    private Long id;

    @Column(unique = true)
    private String email;

    @Column(name = "password_hash")
    private String passwordHash;

    @CreationTimestamp
    private LocalDateTime createdAt;

    @OneToMany(mappedBy = "user", fetch = FetchType.LAZY)
    private List<OrderEntity> orders;
}

// 2) Domain Model — biznes məntiqi (opsional, hər layihədə lazım deyil)
public class User {
    private final Long id;
    private final Email email;     // value object
    private final List<Order> orders;

    public boolean canPlaceOrder() {
        return orders.size() < 10;
    }
}

// 3) Response DTO — client-ə göndərilir
public record UserResponseDto(
    Long id,
    String email,
    LocalDateTime createdAt
) {}
// password yox, orders yox — yalnız ictimai məlumat
```

---

## 3. Request DTO və Response DTO {#request-response}

Request və response DTO-ları fərqli olmalıdır. Client-ın göndərdiyi field-lər (input) server-in qaytardıqları (output) ilə üst-üstə düşmür.

```java
// Request DTO — client bunu göndərir
public record CreateUserRequestDto(
    @NotBlank @Email String email,
    @NotBlank @Size(min = 8) String password,
    @NotBlank String fullName
) {}
// id YOXDUR — server təyin edir
// createdAt YOXDUR — server təyin edir

// Response DTO — server bunu qaytarır
public record UserResponseDto(
    Long id,
    String email,
    String fullName,
    LocalDateTime createdAt
) {}
// password YOXDUR — heç vaxt qaytarılmır

// Update Request DTO — tez-tez daha məhduddur
public record UpdateUserRequestDto(
    @Size(min = 1) String fullName   // yalnız adı dəyişə bilər
) {}
```

### Controller-də istifadə:

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserService userService;

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public UserResponseDto create(@Valid @RequestBody CreateUserRequestDto req) {
        return userService.create(req);
    }

    @GetMapping("/{id}")
    public UserResponseDto get(@PathVariable Long id) {
        return userService.findById(id);
    }

    @PatchMapping("/{id}")
    public UserResponseDto update(
        @PathVariable Long id,
        @Valid @RequestBody UpdateUserRequestDto req
    ) {
        return userService.update(id, req);
    }
}
```

---

## 4. DTO-da validation {#validation}

`spring-boot-starter-validation` əlavə etmək kifayətdir.

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-validation</artifactId>
</dependency>
```

### Əsas validation annotasiyaları:

```java
public record CreateProductRequestDto(

    @NotBlank(message = "Ad boş ola bilməz")
    @Size(max = 100, message = "Ad 100 simvoldan çox ola bilməz")
    String name,

    @NotNull
    @DecimalMin(value = "0.01", message = "Qiymət 0.01-dən kiçik ola bilməz")
    @Digits(integer = 10, fraction = 2)
    BigDecimal price,

    @Min(0) @Max(10000)
    Integer stock,

    @Email
    String contactEmail,

    @Pattern(regexp = "^[A-Z]{3}$", message = "Valyuta 3 hərfli kod olmalıdır")
    String currency,

    @Past
    LocalDate productionDate,

    @Future
    LocalDate expirationDate
) {}
```

### Controller-də aktivləşdirmək:

```java
@PostMapping
public ProductResponseDto create(
    @Valid @RequestBody CreateProductRequestDto req  // @Valid mütləqdir
) {
    return productService.create(req);
}
```

### Validation xətasını idarə et:

```java
@RestControllerAdvice
public class ValidationExceptionHandler {

    @ExceptionHandler(MethodArgumentNotValidException.class)
    @ResponseStatus(HttpStatus.BAD_REQUEST)
    public Map<String, String> handle(MethodArgumentNotValidException ex) {
        Map<String, String> errors = new HashMap<>();
        ex.getBindingResult().getFieldErrors().forEach(fe ->
            errors.put(fe.getField(), fe.getDefaultMessage())
        );
        return errors;
    }
}

// Response nümunəsi:
// {"name": "Ad boş ola bilməz", "price": "Qiymət 0.01-dən kiçik ola bilməz"}
```

---

## 5. Nested DTO və pagination wrapper {#nested}

### Nested DTO:

```java
public record OrderResponseDto(
    Long id,
    BigDecimal total,
    AddressDto shippingAddress,         // nested
    List<OrderItemDto> items            // nested collection
) {}

public record AddressDto(
    String city,
    String street,
    String zipCode
) {}

public record OrderItemDto(
    Long productId,
    String productName,
    Integer quantity,
    BigDecimal price
) {}
```

### Pagination wrapper:

```java
// Spring Data-nın Page<T>-sini birbaşa qaytarmayın — fərqli versiyalarda format dəyişir
public record PageResponse<T>(
    List<T> content,
    int page,
    int size,
    long totalElements,
    int totalPages,
    boolean hasNext
) {
    public static <T> PageResponse<T> from(Page<T> page) {
        return new PageResponse<>(
            page.getContent(),
            page.getNumber(),
            page.getSize(),
            page.getTotalElements(),
            page.getTotalPages(),
            page.hasNext()
        );
    }
}

// İstifadə
@GetMapping
public PageResponse<UserResponseDto> list(Pageable pageable) {
    Page<UserResponseDto> page = userService.findAll(pageable);
    return PageResponse.from(page);
}
```

---

## 6. @Entity-ni birbaşa qaytarmamaq {#entity-return}

### 5 səbəb niyə entity-ni controller-dən qaytarmamalıyıq:

```java
// 1) Lazy-loading sızması
@Entity
public class User {
    @OneToMany(fetch = FetchType.LAZY)
    private List<Order> orders;
}

// Controller-dən qaytarsaq: LazyInitializationException
// (çünki transaction artıq bağlanıb)


// 2) Həssas məlumat sızması
@Entity
public class User {
    private String passwordHash;   // JSON-a düşür!
    private String creditCardToken;
}


// 3) Circular reference
@Entity public class User { @OneToMany List<Order> orders; }
@Entity public class Order { @ManyToOne User user; }
// Jackson StackOverflowError atır


// 4) Over-posting hücumu
@PostMapping("/users")
public User create(@RequestBody User u) {
    return userRepo.save(u);
    // Client bunu göndərə bilər:
    // {"email":"a@b.com","role":"ADMIN","verified":true}
    // role və verified field-ləri serverə yazılır — təhlükəsizlik deliyi!
}


// 5) API-nin DB sxeminə bağlanması
// DB-də "user_name" field-ini "full_name" adlandırsaq,
// bütün API istifadəçiləri sınır
```

---

## 7. Manual mapping — static factory metodları {#manual}

Kiçik layihələrdə manual mapping kifayətdir.

### Statik fabrika metodu ilə:

```java
public record UserResponseDto(
    Long id,
    String email,
    String fullName,
    LocalDateTime createdAt
) {
    // Entity -> DTO
    public static UserResponseDto from(UserEntity e) {
        return new UserResponseDto(
            e.getId(),
            e.getEmail(),
            e.getFullName(),
            e.getCreatedAt()
        );
    }

    // Siyahı mapping
    public static List<UserResponseDto> from(List<UserEntity> entities) {
        return entities.stream()
            .map(UserResponseDto::from)
            .toList();
    }
}
```

### CreateRequestDto -> Entity:

```java
public record CreateUserRequestDto(
    String email,
    String password,
    String fullName
) {
    public UserEntity toEntity(PasswordEncoder encoder) {
        UserEntity e = new UserEntity();
        e.setEmail(email);
        e.setPasswordHash(encoder.encode(password));
        e.setFullName(fullName);
        return e;
    }
}
```

### Service-də istifadə:

```java
@Service
@RequiredArgsConstructor
public class UserService {

    private final UserRepository repo;
    private final PasswordEncoder encoder;

    public UserResponseDto create(CreateUserRequestDto req) {
        UserEntity saved = repo.save(req.toEntity(encoder));
        return UserResponseDto.from(saved);
    }

    public UserResponseDto findById(Long id) {
        return repo.findById(id)
            .map(UserResponseDto::from)
            .orElseThrow(() -> new NotFoundException("User " + id));
    }
}
```

### Problem — böyük layihədə çox boilerplate:

```java
// 20 sahəli entity üçün 20 sətir mapping kodu
// 50 entity üçün — minlərlə sətir təkrar kod
// Burada mapping kitabxanaları köməyə gəlir
```

---

## 8. MapStruct — compile-time mapping {#mapstruct}

**MapStruct** — annotation processor-dur, kompilyasiya zamanı mapping kodu GENERASİYA edir. Runtime-da reflection yoxdur — ən sürətli yoldur.

### Maven quraşdırma:

```xml
<dependencies>
    <dependency>
        <groupId>org.mapstruct</groupId>
        <artifactId>mapstruct</artifactId>
        <version>1.6.0</version>
    </dependency>
</dependencies>

<build>
    <plugins>
        <plugin>
            <groupId>org.apache.maven.plugins</groupId>
            <artifactId>maven-compiler-plugin</artifactId>
            <configuration>
                <annotationProcessorPaths>
                    <path>
                        <groupId>org.mapstruct</groupId>
                        <artifactId>mapstruct-processor</artifactId>
                        <version>1.6.0</version>
                    </path>
                    <!-- Lombok istifadə edirsənsə sıralama önəmlidir -->
                    <path>
                        <groupId>org.projectlombok</groupId>
                        <artifactId>lombok</artifactId>
                    </path>
                </annotationProcessorPaths>
            </configuration>
        </plugin>
    </plugins>
</build>
```

### Gradle:

```groovy
dependencies {
    implementation 'org.mapstruct:mapstruct:1.6.0'
    annotationProcessor 'org.mapstruct:mapstruct-processor:1.6.0'
}
```

### Ən sadə mapper:

```java
@Mapper(componentModel = "spring")  // Spring bean kimi qeyd edilir
public interface UserMapper {

    // Eyni adlı field-lər avtomatik mapping
    UserResponseDto toDto(UserEntity entity);

    // Siyahı mapping də avtomatik
    List<UserResponseDto> toDtoList(List<UserEntity> entities);
}

// İstifadə — Spring inject edir:
@Service
@RequiredArgsConstructor
public class UserService {
    private final UserMapper mapper;
    private final UserRepository repo;

    public UserResponseDto findById(Long id) {
        return mapper.toDto(repo.findById(id).orElseThrow());
    }
}
```

### MapStruct-un generasiya etdiyi kod:

```java
// target/generated-sources/annotations/.../UserMapperImpl.java
@Component
public class UserMapperImpl implements UserMapper {

    @Override
    public UserResponseDto toDto(UserEntity entity) {
        if (entity == null) return null;
        return new UserResponseDto(
            entity.getId(),
            entity.getEmail(),
            entity.getFullName(),
            entity.getCreatedAt()
        );
    }

    @Override
    public List<UserResponseDto> toDtoList(List<UserEntity> entities) {
        if (entities == null) return null;
        List<UserResponseDto> list = new ArrayList<>(entities.size());
        for (UserEntity e : entities) {
            list.add(toDto(e));
        }
        return list;
    }
}
```

### @Mapping — ad uyğunsuzluğu:

```java
@Mapper(componentModel = "spring")
public interface UserMapper {

    @Mapping(source = "fullName", target = "name")       // ad dəyişir
    @Mapping(source = "email", target = "contact.email") // nested target
    @Mapping(target = "password", ignore = true)         // ignore
    UserResponseDto toDto(UserEntity entity);
}
```

### Expression və constant:

```java
@Mapper(componentModel = "spring")
public interface UserMapper {

    @Mapping(target = "fullName",
             expression = "java(entity.getFirstName() + \" \" + entity.getLastName())")
    @Mapping(target = "source", constant = "WEB")
    @Mapping(target = "createdAt",
             expression = "java(java.time.LocalDateTime.now())")
    UserResponseDto toDto(UserEntity entity);
}
```

### Custom method (@Named):

```java
@Mapper(componentModel = "spring")
public interface UserMapper {

    @Mapping(source = "birthDate", target = "age", qualifiedByName = "calcAge")
    UserResponseDto toDto(UserEntity entity);

    @Named("calcAge")
    default int calculateAge(LocalDate birthDate) {
        return Period.between(birthDate, LocalDate.now()).getYears();
    }
}
```

### Update mapping (@MappingTarget):

```java
@Mapper(componentModel = "spring")
public interface UserMapper {

    // Mövcud entity-ni partial update et
    void updateEntity(
        UpdateUserRequestDto dto,
        @MappingTarget UserEntity entity  // bu obyekt yenilənir
    );
}

// İstifadə
@Transactional
public UserResponseDto update(Long id, UpdateUserRequestDto req) {
    UserEntity entity = repo.findById(id).orElseThrow();
    mapper.updateEntity(req, entity);  // yalnız null olmayan field-lər yenilənir
    return mapper.toDto(entity);       // @Transactional — save avtomatik
}

// Null field-ləri ignore et
@BeanMapping(nullValuePropertyMappingStrategy = NullValuePropertyMappingStrategy.IGNORE)
void updateEntity(UpdateUserRequestDto dto, @MappingTarget UserEntity entity);
```

### Nested obyekt mapping:

```java
// Entity
@Entity
public class Order {
    @Id private Long id;
    private BigDecimal total;

    @ManyToOne
    private User user;   // nested entity
}

// DTO
public record OrderDto(Long id, BigDecimal total, UserDto user) {}
public record UserDto(Long id, String name) {}

// Mapper
@Mapper(componentModel = "spring", uses = UserMapper.class)  // başqa mapper istifadə edir
public interface OrderMapper {
    OrderDto toDto(Order order);
}
// MapStruct avtomatik UserMapper-i nested mapping üçün çağırır
```

---

## 9. ModelMapper — runtime reflection {#modelmapper}

**ModelMapper** — runtime-da reflection ilə işləyir. Quraşdırılması daha sadədir, amma daha yavaşdır və kompilyasiya yoxlaması yoxdur.

### Quraşdırma:

```xml
<dependency>
    <groupId>org.modelmapper</groupId>
    <artifactId>modelmapper</artifactId>
    <version>3.2.0</version>
</dependency>
```

### Bean konfiqurasiyası:

```java
@Configuration
public class MapperConfig {

    @Bean
    public ModelMapper modelMapper() {
        ModelMapper mapper = new ModelMapper();
        mapper.getConfiguration()
            .setMatchingStrategy(MatchingStrategies.STRICT)
            .setFieldMatchingEnabled(true)
            .setFieldAccessLevel(AccessLevel.PRIVATE);
        return mapper;
    }
}
```

### İstifadə:

```java
@Service
@RequiredArgsConstructor
public class UserService {

    private final ModelMapper mapper;
    private final UserRepository repo;

    public UserResponseDto findById(Long id) {
        UserEntity entity = repo.findById(id).orElseThrow();
        return mapper.map(entity, UserResponseDto.class);  // runtime reflection
    }

    public List<UserResponseDto> findAll() {
        return repo.findAll().stream()
            .map(e -> mapper.map(e, UserResponseDto.class))
            .toList();
    }
}
```

### Custom TypeMap:

```java
@Configuration
public class MapperConfig {

    @Bean
    public ModelMapper modelMapper() {
        ModelMapper mapper = new ModelMapper();

        // User -> UserDto üçün xüsusi qaydalar
        mapper.typeMap(UserEntity.class, UserResponseDto.class)
            .addMappings(m -> {
                m.map(src -> src.getFirstName() + " " + src.getLastName(),
                      UserResponseDto::setFullName);
                m.skip(UserResponseDto::setPassword);
            });

        return mapper;
    }
}
```

---

## 10. MapStruct vs ModelMapper müqayisəsi {#muqayise}

| Xüsusiyyət | MapStruct | ModelMapper |
|---|---|---|
| **Mexanizm** | Compile-time kod generasiyası | Runtime reflection |
| **Performans** | Çox sürətli (plain Java kodu) | ~10-50x daha yavaş |
| **Type safety** | Kompilyasiyada yoxlanır | Yalnız runtime-da |
| **Debug** | Generasiya olunan kodu görmək olur | "Magic" — daha çətin |
| **Setup** | Maven/Gradle annotation processor | Sadə dependency |
| **Learning curve** | Orta (annotasiyalar) | Kiçik (avtomatik) |
| **IDE dəstəyi** | Əla (generasiya olunan kod görünür) | Zəif |
| **Tövsiyə** | **Production — bu seçilməlidir** | Prototype, kiçik layihələr |

### Performans test nəticəsi (10M mapping):

```
MapStruct:     380 ms
ModelMapper:   14_200 ms   (~37x daha yavaş)
Manual mapping: 340 ms
```

### MapStruct-un digər üstünlükləri:

- Null-safe kod generasiya edir (əvvəl hər field üçün null yoxlaması)
- Generasiya olunan kod tamamilə "oxunaqlı" plain Java-dır
- IntelliJ-də "Go to declaration" generasiya olunmuş faylı açır
- Unused field xəbərdarlığı verir (`@Mapping` ignore etdiyin field-ləri göstərir)

---

## 11. Records DTO kimi {#records}

Java 14+ `record` — DTO-lar üçün mükəmməl seçimdir.

```java
// Java 14+-dan əvvəl
public class UserResponseDto {
    private final Long id;
    private final String email;
    // 40 sətir: constructor, getter, equals, hashCode, toString
}

// Java 14+ record ilə — 1 sətir!
public record UserResponseDto(Long id, String email) {}
// Avtomatik: constructor, accessor (id(), email()), equals, hashCode, toString
```

### Record-un üstünlükləri:

- **Immutable** — field-lər final, mutasiya yoxdur
- **Qısa** — 40 sətir -> 1 sətir
- **Jackson dəstəyi** — 2.12+ avtomatik `@JsonCreator` qəbul edir
- **Validation dəstəyi** — `@NotBlank` record parametrlərində işləyir

```java
public record CreateUserRequestDto(
    @NotBlank @Email String email,
    @NotBlank @Size(min = 8) String password,
    @NotBlank String fullName
) {
    // Əlavə validasiya (kompakt konstruktor)
    public CreateUserRequestDto {
        if (password.equals(email)) {
            throw new IllegalArgumentException("Parol email-dən fərqli olmalıdır");
        }
    }

    // Statik fabrika metodu
    public UserEntity toEntity(PasswordEncoder encoder) {
        UserEntity e = new UserEntity();
        e.setEmail(email);
        e.setPasswordHash(encoder.encode(password));
        e.setFullName(fullName);
        return e;
    }
}
```

### Record-un məhdudiyyətləri:

- Field əlavə etmək olmur (immutable)
- Extend etmək olmur (implicit final)
- JPA entity olaraq istifadə etmək olmur (mutable olmalıdır Hibernate üçün)

---

## 12. Spring Data projections {#projections}

Read-only DTO-lar üçün alternativ — Spring Data özü mapping edir, DB-dən yalnız lazım olan sütunları oxuyur.

### Interface projection:

```java
// DTO interface kimi
public interface UserSummary {
    Long getId();
    String getEmail();
    String getFullName();
}

// Repository
public interface UserRepository extends JpaRepository<UserEntity, Long> {

    // Yalnız 3 sütun DB-dən gətirilir
    List<UserSummary> findAllByActiveTrue();

    Optional<UserSummary> findSummaryById(Long id);
}
```

### SQL:

```sql
-- Spring Data bu SQL-i generasiya edir:
SELECT u.id, u.email, u.full_name FROM users u WHERE u.active = true;
-- password_hash və digər field-lər gətirilmir!
```

### Class-based projection (DTO class):

```java
public class UserSummaryDto {
    private final Long id;
    private final String email;

    public UserSummaryDto(Long id, String email) {
        this.id = id;
        this.email = email;
    }
    // getter-lər
}

// @Query ilə
@Query("select new com.example.dto.UserSummaryDto(u.id, u.email) from UserEntity u")
List<UserSummaryDto> findAllSummaries();
```

### Dinamik projection:

```java
public interface UserRepository extends JpaRepository<UserEntity, Long> {

    <T> Optional<T> findById(Long id, Class<T> type);
}

// İstifadə
UserSummary summary = repo.findById(1L, UserSummary.class).orElseThrow();
UserEntity full = repo.findById(1L, UserEntity.class).orElseThrow();
```

---

## 13. Tam real nümunə — User modulunun tam kodu {#tam-numune}

### 1) Entity:

```java
@Entity
@Table(name = "users")
@Getter @Setter
public class UserEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, unique = true)
    private String email;

    @Column(name = "password_hash", nullable = false)
    private String passwordHash;

    @Column(name = "full_name", nullable = false)
    private String fullName;

    @CreationTimestamp
    @Column(name = "created_at", updatable = false)
    private LocalDateTime createdAt;

    @UpdateTimestamp
    @Column(name = "updated_at")
    private LocalDateTime updatedAt;

    @Column(nullable = false)
    private Boolean active = true;
}
```

### 2) DTO-lar:

```java
public record CreateUserRequestDto(
    @NotBlank @Email String email,
    @NotBlank @Size(min = 8, max = 100) String password,
    @NotBlank @Size(max = 200) String fullName
) {}

public record UpdateUserRequestDto(
    @Size(max = 200) String fullName
) {}

public record UserResponseDto(
    Long id,
    String email,
    String fullName,
    LocalDateTime createdAt,
    Boolean active
) {}
```

### 3) MapStruct mapper:

```java
@Mapper(componentModel = "spring")
public interface UserMapper {

    UserResponseDto toDto(UserEntity entity);

    List<UserResponseDto> toDtoList(List<UserEntity> entities);

    @Mapping(target = "id", ignore = true)
    @Mapping(target = "passwordHash", ignore = true)   // service-də əl ilə set olunur
    @Mapping(target = "createdAt", ignore = true)
    @Mapping(target = "updatedAt", ignore = true)
    @Mapping(target = "active", constant = "true")
    UserEntity toEntity(CreateUserRequestDto dto);

    @BeanMapping(nullValuePropertyMappingStrategy = NullValuePropertyMappingStrategy.IGNORE)
    @Mapping(target = "id", ignore = true)
    @Mapping(target = "passwordHash", ignore = true)
    @Mapping(target = "email", ignore = true)          // email dəyişmir
    @Mapping(target = "createdAt", ignore = true)
    @Mapping(target = "updatedAt", ignore = true)
    @Mapping(target = "active", ignore = true)
    void updateEntity(UpdateUserRequestDto dto, @MappingTarget UserEntity entity);
}
```

### 4) Repository:

```java
public interface UserRepository extends JpaRepository<UserEntity, Long> {
    boolean existsByEmail(String email);
}
```

### 5) Service:

```java
@Service
@RequiredArgsConstructor
@Transactional
public class UserService {

    private final UserRepository repo;
    private final UserMapper mapper;
    private final PasswordEncoder encoder;

    public UserResponseDto create(CreateUserRequestDto req) {
        if (repo.existsByEmail(req.email())) {
            throw new ConflictException("Email artıq mövcuddur");
        }

        UserEntity entity = mapper.toEntity(req);
        entity.setPasswordHash(encoder.encode(req.password()));
        UserEntity saved = repo.save(entity);
        return mapper.toDto(saved);
    }

    @Transactional(readOnly = true)
    public UserResponseDto findById(Long id) {
        return repo.findById(id)
            .map(mapper::toDto)
            .orElseThrow(() -> new NotFoundException("User " + id));
    }

    public UserResponseDto update(Long id, UpdateUserRequestDto req) {
        UserEntity entity = repo.findById(id)
            .orElseThrow(() -> new NotFoundException("User " + id));
        mapper.updateEntity(req, entity);   // null-safe update
        return mapper.toDto(entity);        // @Transactional save
    }

    @Transactional(readOnly = true)
    public List<UserResponseDto> findAll() {
        return mapper.toDtoList(repo.findAll());
    }
}
```

### 6) Controller:

```java
@RestController
@RequestMapping("/api/users")
@RequiredArgsConstructor
public class UserController {

    private final UserService service;

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public UserResponseDto create(@Valid @RequestBody CreateUserRequestDto req) {
        return service.create(req);
    }

    @GetMapping("/{id}")
    public UserResponseDto get(@PathVariable Long id) {
        return service.findById(id);
    }

    @PatchMapping("/{id}")
    public UserResponseDto update(
        @PathVariable Long id,
        @Valid @RequestBody UpdateUserRequestDto req
    ) {
        return service.update(id, req);
    }

    @GetMapping
    public List<UserResponseDto> list() {
        return service.findAll();
    }
}
```

---

## Ümumi Səhvlər {#sehvler}

### 1. Entity-ni birbaşa controller-dən qaytarmaq

```java
// YANLIŞ
@GetMapping("/{id}")
public UserEntity get(@PathVariable Long id) {
    return userRepo.findById(id).orElseThrow();
}

// DOĞRU
@GetMapping("/{id}")
public UserResponseDto get(@PathVariable Long id) {
    return userService.findById(id);  // DTO qaytarır
}
```

### 2. DTO-da setter-lər (mutable DTO)

```java
// YANLIŞ — mutable DTO test zamanı state-i sızdıra bilər
public class UserDto {
    private Long id;
    private String email;
    // getter + setter
}

// DOĞRU — immutable (record və ya final field-lər)
public record UserDto(Long id, String email) {}
```

### 3. DTO-da circular reference

```java
public class UserDto {
    private List<OrderDto> orders;
}
public class OrderDto {
    private UserDto user;  // dövr!
}
// JSON serializasiyasında StackOverflowError

// DOĞRU: bir tərəfə reference-ı çıxar, ya da yalnız id saxla
public class OrderDto {
    private Long userId;  // sadəcə id
}
```

### 4. @Valid unutmaq

```java
// YANLIŞ — validation işləmir
@PostMapping
public UserDto create(@RequestBody CreateUserRequestDto req) { }

// DOĞRU
@PostMapping
public UserDto create(@Valid @RequestBody CreateUserRequestDto req) { }
```

### 5. MapStruct-da componentModel qoymamaq

```java
// YANLIŞ — Spring inject edə bilməz
@Mapper
public interface UserMapper { }
// İstifadə: UserMapper mapper = Mappers.getMapper(UserMapper.class);

// DOĞRU
@Mapper(componentModel = "spring")
public interface UserMapper { }
// İstifadə: @Autowired UserMapper mapper;
```

### 6. Update-də null field-lərin overwrite edilməsi

```java
// YANLIŞ — null olan field DB-də null qoyur
@Mapping(target = "fullName", source = "fullName")
void update(UpdateDto dto, @MappingTarget UserEntity entity);

// DOĞRU — null-ları ignore et
@BeanMapping(nullValuePropertyMappingStrategy = NullValuePropertyMappingStrategy.IGNORE)
void update(UpdateDto dto, @MappingTarget UserEntity entity);
```

### 7. Yalnız bir DTO hər şey üçün

```java
// YANLIŞ — eyni UserDto həm request, həm response
public class UserDto {
    private Long id;          // create-də lazım deyil
    private String password;  // response-da lazım deyil
    private LocalDateTime createdAt;  // create-də lazım deyil
}

// DOĞRU — hər ssenari üçün ayrı DTO
public record CreateUserRequestDto(String email, String password) {}
public record UpdateUserRequestDto(String fullName) {}
public record UserResponseDto(Long id, String email, LocalDateTime createdAt) {}
```

---

## İntervyu Sualları {#intervyu}

**S: DTO nədir və niyə istifadə edirik?**
C: DTO (Data Transfer Object) — şəbəkə üzərindən ötürülmək üçün nəzərdə tutulmuş məlumat obyekti. DTO istifadə edirik: (1) API kontraktını DB sxemindən ayırmaq, (2) həssas field-ləri (password) gizlətmək, (3) over-posting hücumunu qarşısını almaq, (4) Hibernate lazy-loading sızmasını önləmək, (5) API versiyalaşdırması rahat olsun deyə.

**S: Entity və DTO arasındakı fərq nədir?**
C: Entity — DB sətrini təmsil edir, `@Entity`, `@Column` annotasiyaları var, Hibernate tərəfindən idarə olunur. DTO — API ötürmə üçündür, adətən immutable (record), heç bir DB annotasiyası yoxdur, yalnız client-ə lazım olan field-ləri saxlayır.

**S: Niyə controller-dən entity-ni birbaşa qaytarmamalıyıq?**
C: 5 əsas səbəb: (1) lazy-loaded field-lər `LazyInitializationException` atır, (2) həssas field-lər (password_hash) JSON-a sızır, (3) `@OneToMany`/`@ManyToOne` döngüsü `StackOverflowError` verir, (4) over-posting — client `role=ADMIN` göndərə bilər, (5) DB sxem dəyişikliyi API-nı sındırır.

**S: MapStruct və ModelMapper arasında hansı fərq var?**
C: MapStruct — compile-time kod generasiyası (annotation processor), runtime-da reflection yoxdur, 10-50x daha sürətli, kompilyasiya zamanı type-safety yoxlanır. ModelMapper — runtime reflection ilə işləyir, setup sadədir, amma daha yavaşdır və xətalar yalnız runtime-da görünür. Production-da MapStruct tövsiyə olunur.

**S: MapStruct-da @MappingTarget nədir?**
C: Mövcud obyekti yeniləmək üçün istifadə olunur. Yeni obyekt yaratmaq əvəzinə metod parametri kimi keçirilən obyektin field-lərini yeniləyir. Partial update ssenarilərində çox faydalıdır — məsələn PATCH endpoint-də yalnız null olmayan field-ləri yeniləmək üçün `@BeanMapping(nullValuePropertyMappingStrategy = IGNORE)` ilə birlikdə istifadə olunur.

**S: Record-ları DTO kimi istifadə etməyin üstünlükləri nələrdir?**
C: (1) Boilerplate azalır — 40 sətir yerinə 1 sətir, (2) immutable — field-lər final, dəyişdirilə bilməz, (3) avtomatik `equals`/`hashCode`/`toString` — test üçün əlverişli, (4) Jackson 2.12+ record-lara avtomatik `@JsonCreator` qoyur, (5) validation annotasiyaları record parametrlərində işləyir. Tək çatışmayan — JPA entity kimi istifadə etmək olmur (Hibernate mutable obyekt tələb edir).

**S: Request və Response DTO-nu niyə ayırmaq lazımdır?**
C: Çünki client-ın göndərdiyi və serverin qaytardığı field-lər fərqlidir. Request-də id yoxdur (server təyin edir), password var (create üçün); response-da id var (server təyin edib), password yoxdur (heç vaxt qaytarılmır). Tək DTO istifadə etmək istifadəçiyə lazımsız field-lər göstərir və validation qarışıq olur.

**S: DTO-da validation necə edilir?**
C: `spring-boot-starter-validation` dependency əlavə olunur. DTO field-lərinə `@NotBlank`, `@Email`, `@Size`, `@Min` və s. annotasiyaları qoyulur. Controller-də DTO parametrindən əvvəl `@Valid` yazılır. Validation xətası `MethodArgumentNotValidException` atır; `@RestControllerAdvice` ilə tutulub 400 Bad Request kimi qaytarılır.

**S: Spring Data projection nə vaxt DTO-dan yaxşıdır?**
C: Read-only ssenarilərdə. Projection istifadə etdikdə Spring Data yalnız interface-də göstərilən sütunları DB-dən gətirir — performance təkmilləşir. DTO ilə isə bütün sütunlar gətirilib sonra mapping edilir. Amma projection yazma əməliyyatları üçün uyğun deyil və validation dəstəyi yoxdur.

**S: MapStruct-un generasiya etdiyi kodu haradan görmək olar?**
C: Maven üçün `target/generated-sources/annotations/` qovluğunda, Gradle üçün `build/generated/sources/annotationProcessor/java/main/` qovluğunda. IntelliJ-də mapper interfeysində "Go to implementation" (`Ctrl+Alt+B`) generasiya olunan sinfi açır. Debug zamanı bu kodda breakpoint qoymaq olar.
