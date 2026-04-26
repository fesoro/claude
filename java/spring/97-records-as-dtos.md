# 97 — Records — Spring REST API-lərdə DTO Pattern

> **Seviyye:** Senior ⭐⭐⭐

## Mündəricat
1. [Record nədir?](#record-nədir)
2. [Records vs Lombok @Value](#records-vs-lombok-value)
3. [Jackson ilə Serialization](#jackson-ilə-serialization)
4. [Validation ilə birlikdə](#validation-ilə-birlikdə)
5. [MapStruct ilə mapping](#mapstruct-ilə-mapping)
6. [Generic Records](#generic-records)
7. [Anti-pattern-lər](#anti-pattern-lər)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Record nədir?

Java 16-da stable gəldi. Immutable data class-ı üçün kısa syntax.

```java
// Klassik DTO — 30+ sətir:
public final class UserResponse {
    private final Long id;
    private final String name;
    private final String email;

    public UserResponse(Long id, String name, String email) {
        this.id = id;
        this.name = name;
        this.email = email;
    }

    public Long getId() { return id; }
    public String getName() { return name; }
    public String getEmail() { return email; }

    @Override public boolean equals(Object o) { ... }
    @Override public int hashCode() { ... }
    @Override public String toString() { ... }
}

// Record ilə — 1 sətir:
public record UserResponse(Long id, String name, String email) {}
```

**Record avtomatik generate edir:**
- `final` fields (hər component üçün)
- All-args constructor
- Getter-lər (`id()`, `name()`, `email()` — `getId()` yox!)
- `equals()`, `hashCode()`, `toString()`

---

## Records vs Lombok @Value

```java
// Lombok @Value:
@Value
@Builder
public class UserResponse {
    Long id;
    String name;
    String email;
}

// Java Record:
public record UserResponse(Long id, String name, String email) {}
```

| Xüsusiyyət | Record | Lombok @Value |
|-----------|--------|--------------|
| Boilerplate | 1 sətir | 3-4 annotation |
| Getter adı | `id()` | `getId()` |
| Builder | Lombok @Builder ilə | @Builder |
| Inheritance | Yox (implicit final class) | Var |
| Custom logic | Compact constructor | Adı metod |
| Reflection-friendly | Bəli | Bəli |
| Java versiyon | 16+ | Hər versiyada |

**Tövsiyə:** Yeni layihələrdə Record; köhnə codebase-lərdə Lombok.

---

## Jackson ilə Serialization

Spring Boot Jackson Record-ları **default olaraq** dəstəkləyir (Spring Boot 2.7+, Jackson 2.12+).

```java
// Record:
public record UserResponse(
    Long id,
    String name,
    String email,
    LocalDateTime createdAt
) {}

// Controller:
@GetMapping("/{id}")
public UserResponse getUser(@PathVariable Long id) {
    return userRepo.findById(id)
        .map(u -> new UserResponse(u.getId(), u.getName(), u.getEmail(), u.getCreatedAt()))
        .orElseThrow();
}

// JSON output:
// {"id": 1, "name": "Ali", "email": "ali@example.com", "createdAt": "2024-01-15T10:30:00"}
```

### Jackson annotasiyaları Record-larda:

```java
public record UserResponse(
    @JsonProperty("user_id")          // JSON key-ni dəyiş
    Long id,

    String name,

    @JsonIgnore                        // JSON-a daxil etmə
    String internalCode,

    @JsonFormat(pattern = "dd-MM-yyyy")
    LocalDate birthDate
) {}

// Deserializer (Request body üçün):
// Record JSON-dan construct olunur — @JsonCreator lazım deyil
// Spring Boot avtomatik işldir:
public record CreateUserRequest(
    String name,
    @Email String email
) {}

// POST /api/users body: {"name": "Ali", "email": "ali@example.com"}
// → CreateUserRequest(name="Ali", email="ali@example.com")
```

### Nested records:

```java
public record AddressDto(
    String street,
    String city,
    String country
) {}

public record UserDetailResponse(
    Long id,
    String name,
    AddressDto address,          // nested record
    List<String> roles           // collection
) {}

// JSON output:
// {
//   "id": 1,
//   "name": "Ali",
//   "address": {"street": "İstiqlaliyyət", "city": "Baku", "country": "AZ"},
//   "roles": ["ADMIN", "USER"]
// }
```

---

## Validation ilə birlikdə

Compact constructor ilə validation:

```java
public record CreateUserRequest(
    @NotBlank(message = "Name is required")
    String name,

    @Email(message = "Invalid email format")
    @NotBlank
    String email,

    @NotNull
    @Min(value = 18, message = "Must be 18 or older")
    Integer age,

    @Size(min = 8, message = "Password must be at least 8 characters")
    String password
) {
    // Compact constructor — əlavə business validation:
    public CreateUserRequest {
        // null check (Bean Validation-dan əlavə):
        Objects.requireNonNull(name, "name must not be null");

        // Business logic:
        if (email != null && !email.endsWith("@company.com")) {
            throw new IllegalArgumentException("Only company emails allowed");
        }

        // Normalize:
        name = name.trim(); // component-i dəyişmək olar
        email = email != null ? email.toLowerCase() : null;
    }
}

// Controller-də:
@PostMapping
public ResponseEntity<UserResponse> createUser(
        @Valid @RequestBody CreateUserRequest req) {
    // @Valid Bean Validation annotation-larını işə salır
    // Compact constructor business validation edir
    User user = userService.create(req);
    return ResponseEntity.status(201).body(UserResponse.fromEntity(user));
}
```

---

## MapStruct ilə mapping

```java
// Entity:
@Entity
public class User {
    @Id Long id;
    String name;
    String email;
    String passwordHash;
    LocalDateTime createdAt;
}

// Response DTO Record:
public record UserResponse(Long id, String name, String email, LocalDateTime createdAt) {}

// Request DTO Record:
public record CreateUserRequest(String name, String email, String password) {}

// MapStruct mapper:
@Mapper(componentModel = "spring")
public interface UserMapper {

    UserResponse toResponse(User user); // → UserResponse record

    @Mapping(target = "id", ignore = true)
    @Mapping(target = "passwordHash", ignore = true)
    @Mapping(target = "createdAt", ignore = true)
    User toEntity(CreateUserRequest request);

    List<UserResponse> toResponseList(List<User> users);
}

// Service:
@Service
@RequiredArgsConstructor
public class UserService {

    private final UserRepository userRepo;
    private final UserMapper mapper;
    private final PasswordEncoder encoder;

    public UserResponse createUser(CreateUserRequest req) {
        User user = mapper.toEntity(req);
        user.setPasswordHash(encoder.encode(req.password()));
        return mapper.toResponse(userRepo.save(user));
    }

    public List<UserResponse> getAllUsers() {
        return mapper.toResponseList(userRepo.findAll());
    }
}
```

---

## Generic Records

```java
// Generic API response wrapper:
public record ApiResponse<T>(
    boolean success,
    T data,
    String message,
    LocalDateTime timestamp
) {
    // Static factory methods:
    public static <T> ApiResponse<T> ok(T data) {
        return new ApiResponse<>(true, data, null, LocalDateTime.now());
    }

    public static <T> ApiResponse<T> error(String message) {
        return new ApiResponse<>(false, null, message, LocalDateTime.now());
    }
}

// Paginated response:
public record PageResponse<T>(
    List<T> content,
    int pageNumber,
    int pageSize,
    long totalElements,
    int totalPages,
    boolean last
) {
    public static <T> PageResponse<T> from(Page<T> page) {
        return new PageResponse<>(
            page.getContent(),
            page.getNumber(),
            page.getSize(),
            page.getTotalElements(),
            page.getTotalPages(),
            page.isLast()
        );
    }
}

// Controller:
@GetMapping
public ApiResponse<PageResponse<UserResponse>> getUsers(Pageable pageable) {
    Page<UserResponse> page = userRepo.findAll(pageable)
        .map(mapper::toResponse);
    return ApiResponse.ok(PageResponse.from(page));
}
```

---

## Anti-pattern-lər

### JPA Entity kimi Record istifadə:

```java
// YANLIŞ — Record JPA Entity ola bilməz:
@Entity
public record User(Long id, String name) {} // ← Compile xətası!
// Səbəb: Entity no-args constructor tələb edir; Record-ların final fields-i var
```

### Mutable state saxlamaq:

```java
// PİS — List mutable-dır, record immutable görünür amma deyil:
public record UserRequest(List<String> roles) {}

UserRequest req = new UserRequest(new ArrayList<>());
req.roles().add("ADMIN"); // ← state dəyişdi!

// YAXŞI — immutable list:
public record UserRequest(List<String> roles) {
    public UserRequest {
        roles = List.copyOf(roles); // defensive copy
    }
}
```

### Getter adlarını unutmaq:

```java
public record UserResponse(Long id, String name) {}

// YANLIŞ:
response.getId(); // ← method yoxdur!

// DÜZGÜN:
response.id();    // ← Record getter syntax
response.name();
```

### Jackson @JsonProperty olmadan dəyişiklik:

```java
// JSON key-nin adı component adı olur:
public record UserResponse(Long userId) {}
// → {"userId": 1}

// Dəyişmək üçün:
public record UserResponse(@JsonProperty("user_id") Long userId) {}
// → {"user_id": 1}
```

---

## İntervyu Sualları

**S: Record vs class fərqi nədir?**
C: Record immutable data class-ı üçün shorthand. Avtomatik: final fields, all-args constructor, `equals/hashCode/toString`, getter-lər (`field()` formatında). Miras almaq olmur (implicit final class).

**S: Record niyə JPA Entity ola bilmir?**
C: JPA Entity-lər no-args constructor tələb edir (reflection ilə lazımdır). Record-ların bütün field-ləri final-dır — JPA onları set edə bilmir. Proxy yaratmaq da mümkün deyil (class final-dır).

**S: Compact constructor nədir?**
C: Record-ların öz constructor syntax-ı. Parametr siyahısı yazılmır (komponent adları eynidir). Validation, normalization, defensive copy üçün istifadə olunur. `this.field = ...` əl ilə yazılmır, komponent assignment avtomatikdir.

**S: Record-la validation Jackson ilə birlikdə işləyirmi?**
C: Bəli, Spring Boot 2.7+ ilə. `@Valid @RequestBody CreateUserRequest req` — Bean Validation annotation-ları record component-lərindədir. Compact constructor əlavə business validation üçündür.
