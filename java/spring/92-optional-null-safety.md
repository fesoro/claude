# 92 — Optional və Null Safety

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Null problemi Java-da](#null-problemi-java-da)
2. [Optional — əsas API](#optional--əsas-api)
3. [Spring/JPA ilə Optional](#springjpa-ilə-optional)
4. [Anti-pattern-lər](#anti-pattern-lər)
5. [@NonNull / @Nullable annotasiyaları](#nonnull--nullable-annotasiyaları)
6. [Laravel ilə müqayisə](#laravel-ilə-müqayisə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Null problemi Java-da

Java-da null reference Tony Hoare tərəfindən "billion dollar mistake" adlandırılıb. PHP-dən fərqli olaraq Java-da tip sistemi null-u görmir — runtime-da `NullPointerException` verir.

```java
// Klassik NPE problemi:
User user = userRepo.findById(id);  // null ola bilər!
String name = user.getName();       // NPE → crash!

// Köhnə yanaşma — defensive:
User user = userRepo.findById(id);
if (user != null) {
    String name = user.getName();
    if (name != null) {
        System.out.println(name.toUpperCase());
    }
}
// "Null check hell" → oxunmaz kod
```

`Optional<T>` Java 8-də bu problemi həll etmək üçün gəldi.

---

## Optional — əsas API

### Yaratmaq

```java
// 1. Dəyər kesinliklə mövcuddur:
Optional<String> opt1 = Optional.of("Ali");
Optional.of(null); // ← NullPointerException! of() null qəbul etmir

// 2. Dəyər mövcud olmaya bilər (null ola bilər):
Optional<String> opt2 = Optional.ofNullable(user); // user null ola bilər
Optional<String> opt3 = Optional.ofNullable(null); // → Optional.empty()

// 3. Boş Optional:
Optional<String> opt4 = Optional.empty();
```

### Dəyər almaq

```java
Optional<User> userOpt = userRepo.findById(id);

// 1. get() — istifadə etməyin, NoSuchElementException
String name = userOpt.get().getName(); // ← PISLDIR!

// 2. isPresent() / isEmpty():
if (userOpt.isPresent()) {
    String name = userOpt.get().getName();
}
// Köhnə üsuldur, aşağıdakılar daha yaxşıdır

// 3. ifPresent() — dəyər varsa bir iş gör:
userOpt.ifPresent(user -> System.out.println(user.getName()));

// 4. orElse() — default dəyər (həmişə hesablanır!):
User user = userOpt.orElse(new User("Anonymous"));

// 5. orElseGet() — lazy, yalnız empty-də hesablanır:
User user = userOpt.orElseGet(() -> createDefaultUser());

// 6. orElseThrow() — empty-də exception:
User user = userOpt.orElseThrow(() -> new UserNotFoundException(id));

// 7. ifPresentOrElse() — Java 9+:
userOpt.ifPresentOrElse(
    user -> System.out.println(user.getName()),
    () -> System.out.println("User not found")
);
```

### Transform etmək

```java
Optional<User> userOpt = userRepo.findById(id);

// map() — daxildəki dəyəri transform edir:
Optional<String> nameOpt = userOpt.map(User::getName);
Optional<String> upperOpt = userOpt.map(User::getName)
                                    .map(String::toUpperCase);

// flatMap() — Optional qaytaran method-larla:
// Əgər User.getAddress() → Optional<Address> qaytarırsa:
Optional<String> cityOpt = userOpt
    .flatMap(User::getAddress)
    .map(Address::getCity);

// filter() — condition yoxlamaq:
Optional<User> activeUser = userOpt
    .filter(user -> user.isActive())
    .filter(user -> user.getAge() >= 18);

// or() — Java 9+, alternative Optional:
Optional<User> result = userOpt
    .or(() -> backupRepo.findById(id));
```

### Real nümunə — zəncirvari:

```java
// Köhnə (null check hell):
public String getUserCity(Long userId) {
    User user = userRepo.findById(userId);
    if (user != null) {
        Address address = user.getAddress();
        if (address != null) {
            return address.getCity();
        }
    }
    return "Unknown";
}

// Optional ilə (clean):
public String getUserCity(Long userId) {
    return userRepo.findOptionalById(userId)
        .flatMap(User::getOptionalAddress)
        .map(Address::getCity)
        .orElse("Unknown");
}
```

---

## Spring/JPA ilə Optional

### Repository-də Optional:

```java
// Spring Data JPA:
public interface UserRepository extends JpaRepository<User, Long> {
    Optional<User> findById(Long id);           // ← JpaRepository-dən
    Optional<User> findByEmail(String email);   // ← Custom query
    Optional<User> findFirstByNameOrderByCreatedAtDesc(String name);

    // Custom JPQL:
    @Query("SELECT u FROM User u WHERE u.email = :email AND u.active = true")
    Optional<User> findActiveByEmail(@Param("email") String email);
}
```

### Service-də istifadə:

```java
@Service
public class UserService {

    @Autowired
    private UserRepository userRepo;

    public UserDto getUser(Long id) {
        return userRepo.findById(id)
            .map(UserDto::fromEntity)
            .orElseThrow(() -> new UserNotFoundException("User not found: " + id));
    }

    public String getUserEmail(Long id) {
        return userRepo.findById(id)
            .map(User::getEmail)
            .orElse(null); // null qaytar (bəzən lazım olur)
    }

    public void updateUserName(Long id, String newName) {
        User user = userRepo.findById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
        user.setName(newName);
        userRepo.save(user);
    }
}
```

### Controller-də:

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public ResponseEntity<UserDto> getUser(@PathVariable Long id) {
        return userRepo.findById(id)
            .map(UserDto::fromEntity)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }
}
```

---

## Anti-pattern-lər

### 1. Optional.get() istifadə etmək

```java
// PİS:
Optional<User> opt = userRepo.findById(id);
User user = opt.get(); // NoSuchElementException riski

// YAXŞI:
User user = userRepo.findById(id)
    .orElseThrow(() -> new UserNotFoundException(id));
```

### 2. Optional-ı method parametri kimi istifadə etmək

```java
// PİS — Optional method parameter kimi:
public void createUser(Optional<String> name) {
    String userName = name.orElse("Anonymous");
}

// YAXŞI — nullable parameter:
public void createUser(@Nullable String name) {
    String userName = name != null ? name : "Anonymous";
}

// VƏ YA — overloading:
public void createUser() { createUser("Anonymous"); }
public void createUser(String name) { ... }
```

### 3. Optional field kimi:

```java
// PİS — entity field-lərini Optional edə bilməzsiniz:
@Entity
public class User {
    private Optional<String> nickname; // ← Serializable deyil
}

// YAXŞI:
@Entity
public class User {
    @Column
    private String nickname; // null ola bilər, ok

    public Optional<String> getNickname() {
        return Optional.ofNullable(nickname);
    }
}
```

### 4. orElse vs orElseGet

```java
// orElse() — həmişə hesablanır (expensive ola bilər):
User user = userOpt.orElse(createExpensiveDefaultUser()); // ← hər zaman çağrılır!

// orElseGet() — yalnız empty-də:
User user = userOpt.orElseGet(() -> createExpensiveDefaultUser()); // ✅ lazy
```

---

## @NonNull / @Nullable annotasiyaları

Spring Framework öz annotasiyalarını təqdim edir:

```java
import org.springframework.lang.NonNull;
import org.springframework.lang.Nullable;

@Service
public class UserService {

    // Parametr null ola bilməz:
    public UserDto getUser(@NonNull Long id) {
        // Spring/IDE null pass etməyə warning verər
        return userRepo.findById(id)
            .map(UserDto::fromEntity)
            .orElseThrow();
    }

    // Return value null ola bilər:
    @Nullable
    public User findByEmail(String email) {
        return userRepo.findByEmail(email).orElse(null);
    }
}
```

**Record ilə null olmayan DTO:**

```java
// Records default @NonNull kimidir:
public record CreateUserRequest(
    @NotBlank String name,
    @Email String email,
    @NotNull Integer age
) {
    // Compact constructor — validation:
    public CreateUserRequest {
        if (age < 0) throw new IllegalArgumentException("Age cannot be negative");
    }
}
```

---

## Laravel ilə müqayisə

```php
// PHP nullable:
function findUser(?int $id): ?User {
    return User::find($id); // null ola bilər
}

$user = findUser(42);
$name = $user?->getName(); // null-safe operator
$city = $user?->getAddress()?->getCity() ?? 'Unknown';
```

```java
// Java Optional:
Optional<User> findUser(Long id) {
    return userRepo.findById(id);
}

String city = findUser(42L)
    .flatMap(User::getOptionalAddress)
    .map(Address::getCity)
    .orElse("Unknown");
```

| Xüsusiyyət | PHP | Java Optional |
|-----------|-----|---------------|
| Null check | `?->` null-safe | `.map()` chain |
| Default dəyər | `??` operator | `.orElse()` |
| Exception | `$user ?? throw new ...` | `.orElseThrow()` |
| Filter | `if ($user && $user->active)` | `.filter()` |

---

## İntervyu Sualları

**S: Optional.of() vs Optional.ofNullable() fərqi?**
C: `of()` — dəyər kesinliklə mövcuddur, null pass etsəniz NPE. `ofNullable()` — null ola bilər, null-sa empty Optional qaytarır.

**S: orElse() vs orElseGet() fərqi nədir?**
C: `orElse(value)` — default dəyər həmişə hesablanır, Optional dolu olsa belə. `orElseGet(supplier)` — yalnız Optional empty olduqda çağrılır. Expensive əməliyyat üçün orElseGet daha yaxşıdır.

**S: Optional-ı nəyə görə field kimi istifadə etmirsiniz?**
C: Optional `Serializable` deyil, JPA entity field-i kimi işlənmir, Jackson default olaraq düzgün serialize etmir. Field nullable saxlamaq, getter-də Optional qaytarmaq düzgündür.

**S: flatMap nə zaman lazım olur?**
C: Optional qaytaran method-larla zəncir yaratmaqda. `map()` Optional içindəki dəyəri transform edir; `flatMap()` isə Optional qaytaran function ilə nested Optional yaratmaqdan qaçır.
