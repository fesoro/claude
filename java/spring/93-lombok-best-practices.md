# 93 — Lombok — Spring Layihələrində Best Practices

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Lombok nədir?](#lombok-nədir)
2. [Əsas annotasiyalar](#əsas-annotasiyalar)
3. [Spring ilə istifadə](#spring-ilə-istifadə)
4. [JPA Entity ilə diqqət](#jpa-entity-ilə-diqqət)
5. [Lombok vs Records](#lombok-vs-records)
6. [Antipattern-lər](#antipattern-lər)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Lombok nədir?

Lombok — Java boilerplate kodunu (getter, setter, constructor, equals, toString) annotation ilə generate edən library. PHP-də bu metodları yazmaq lazım deyil — Java-da isə class-a hər dəfə əl ilə yazırsınız. Lombok bunu aradan qaldırır.

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.projectlombok</groupId>
    <artifactId>lombok</artifactId>
    <optional>true</optional>
</dependency>
```

```java
// Lombok olmadan:
public class User {
    private Long id;
    private String name;
    private String email;

    public User() {}
    public User(Long id, String name, String email) { ... }
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    // ... bütün getter/setter-lər
    @Override public boolean equals(Object o) { ... }
    @Override public int hashCode() { ... }
    @Override public String toString() { ... }
}

// Lombok ilə:
@Data
@NoArgsConstructor
@AllArgsConstructor
public class User {
    private Long id;
    private String name;
    private String email;
}
```

---

## Əsas annotasiyalar

### @Getter / @Setter

```java
// Bütün field-lər üçün:
@Getter
@Setter
public class User {
    private Long id;
    private String name;
    private String email;
}

// Yalnız müəyyən field üçün:
public class User {
    @Getter @Setter
    private Long id;

    @Getter  // yalnız getter, setter yoxdur
    private String name;

    private String password; // nə getter, nə setter
}
```

### @ToString

```java
@ToString
public class User {
    private Long id;
    private String name;
    @ToString.Exclude  // password toString-ə daxil etmə
    private String password;
}

// Nəticə: User(id=1, name=Ali)
```

### @EqualsAndHashCode

```java
@EqualsAndHashCode(onlyExplicitlyIncluded = true)
public class User {
    @EqualsAndHashCode.Include
    private Long id;   // yalnız id ilə compare

    private String name;
    private String email;
}
```

### @Data — hamısı birlikdə

```java
// @Getter + @Setter + @ToString + @EqualsAndHashCode + @RequiredArgsConstructor
@Data
public class UserDto {
    private Long id;
    private String name;
    private String email;
}
```

### @Builder — Builder pattern

```java
@Builder
@Getter
public class CreateOrderRequest {
    private Long userId;
    private List<OrderItem> items;
    private String shippingAddress;
    @Builder.Default
    private OrderStatus status = OrderStatus.PENDING;
}

// İstifadə:
CreateOrderRequest request = CreateOrderRequest.builder()
    .userId(42L)
    .items(List.of(new OrderItem(1L, 2)))
    .shippingAddress("Baku, Azerbaijan")
    .build();
```

### @NoArgsConstructor / @AllArgsConstructor / @RequiredArgsConstructor

```java
@Getter
@NoArgsConstructor          // User()
@AllArgsConstructor         // User(id, name, email)
@RequiredArgsConstructor    // User(final fields + @NonNull fields)
public class User {
    @NonNull
    private String name;    // RequiredArgsConstructor bunu alır
    private String email;   // optional field
}
```

### @Slf4j — Logging

```java
@Slf4j  // private static final Logger log = LoggerFactory.getLogger(...)
@Service
public class OrderService {

    public void process(Order order) {
        log.debug("Processing order: {}", order.getId());
        log.info("Order {} placed by user {}", order.getId(), order.getUserId());
        log.error("Failed to process order {}", order.getId(), exception);
    }
}
```

### @Value — Immutable class

```java
// @Value = @Getter + @ToString + @EqualsAndHashCode + @AllArgsConstructor + final fields
@Value
public class Money {
    BigDecimal amount;
    String currency;
}

// Dəyişdirilə bilməz (immutable value object)
Money price = new Money(new BigDecimal("99.99"), "USD");
```

---

## Spring ilə istifadə

### Service class:

```java
@Slf4j
@Service
@RequiredArgsConstructor  // ← Spring DI üçün ideal
public class OrderService {

    // Spring @RequiredArgsConstructor ilə inject edir:
    private final OrderRepository orderRepo;
    private final UserRepository userRepo;
    private final NotificationService notificationService;

    public OrderDto createOrder(CreateOrderRequest request) {
        log.info("Creating order for user: {}", request.getUserId());

        User user = userRepo.findById(request.getUserId())
            .orElseThrow(() -> new UserNotFoundException(request.getUserId()));

        Order order = Order.builder()
            .user(user)
            .items(request.getItems())
            .status(OrderStatus.PENDING)
            .build();

        Order saved = orderRepo.save(order);
        log.info("Order created: {}", saved.getId());
        return OrderDto.fromEntity(saved);
    }
}
```

### DTO-lar:

```java
// Request DTO:
@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class CreateUserRequest {
    @NotBlank
    private String name;

    @Email
    @NotBlank
    private String email;

    @NotNull
    @Min(0)
    private Integer age;
}

// Response DTO:
@Value  // immutable, yalnız getter
@Builder
public class UserResponse {
    Long id;
    String name;
    String email;
    LocalDateTime createdAt;

    public static UserResponse fromEntity(User user) {
        return UserResponse.builder()
            .id(user.getId())
            .name(user.getName())
            .email(user.getEmail())
            .createdAt(user.getCreatedAt())
            .build();
    }
}
```

---

## JPA Entity ilə diqqət

JPA Entity-lərdə Lombok istifadəsinin spesifik qaydaları var:

```java
// YANLIŞ — @Data JPA entity-lərdə:
@Data // ← PROBLEMDİR!
@Entity
public class Order {
    @Id
    @GeneratedValue
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    private User user;
    // @Data-nın toString() lazy field-i trigger edir → LazyInitializationException
    // @Data-nın equals() ID-siz entity-ləri compare edir → spring-data problemi
}

// DÜZGÜN — JPA entity üçün:
@Entity
@Getter
@Setter
@NoArgsConstructor
@ToString(exclude = "user")  // lazy relation-ı exclude et
@EqualsAndHashCode(onlyExplicitlyIncluded = true)
public class Order {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @EqualsAndHashCode.Include  // yalnız ID ilə equals
    private Long id;

    @Setter(AccessLevel.NONE)  // read-only field
    @Column(nullable = false, updatable = false)
    private LocalDateTime createdAt;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "user_id")
    private User user;

    @PrePersist
    protected void onCreate() {
        createdAt = LocalDateTime.now();
    }
}
```

**Qaydalar:**
- `@Data` JPA entity-lərdə işlətmə
- `@ToString` lazy relation-ları `exclude` et
- `@EqualsAndHashCode` yalnız `@Id` field-ini include et
- `@Builder`-i `@NoArgsConstructor` + `@AllArgsConstructor` ilə birlikdə istifadə et (JPA tələb edir)

---

## Lombok vs Records

Java 16+ Records Lombok-un bəzi funksiyalarını əvəz edir:

```java
// Lombok @Value (immutable DTO):
@Value
@Builder
public class UserResponse {
    Long id;
    String name;
    String email;
}

// Java Record (eyni şey, daha az kod):
public record UserResponse(Long id, String name, String email) {}

// Record ilə Builder yoxdur → @Builder-dən yararlanın:
@Builder
public record UserResponse(Long id, String name, String email) {
    // Compact constructor — validation:
    public UserResponse {
        Objects.requireNonNull(id, "id cannot be null");
    }
}
```

| Xüsusiyyət | Lombok @Value | Record |
|-----------|--------------|--------|
| Immutable | Bəli | Bəli |
| Builder | @Builder ilə | Lombok @Builder ilə |
| Inheritance | Bəli | Xeyr (implicit final) |
| Custom logic | Asandır | Compact constructor |
| JPA Entity | Xeyr | Xeyr |

**Tövsiyə:** Yeni layihələrdə DTO üçün Record, Entity üçün Lombok.

---

## Antipattern-lər

### @Data JPA entity-lərə:

```java
@Data @Entity // ← PİS
public class User { ... }
// toString lazy-i trigger edər, equals ID-siz işləməz
```

### @Builder tək başına:

```java
@Builder  // tək başına
public class User {
    private String name;
}

User user = new User(); // ← compile xətası! @NoArgsConstructor lazımdır
```

```java
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class User { ... } // ✅
```

### Lombok ilə override conflict:

```java
@Data
public class User {
    @Override
    public String toString() {
        return "Custom: " + name; // @Data-nın toString-ini override edir, OK
    }
    // Amma @EqualsAndHashCode ilə conflict ola bilər
}
```

---

## İntervyu Sualları

**S: @Data JPA entity-lərdə niyə istifadə edilmir?**
C: `@Data`-nın generate etdiyi `toString()` lazy-loaded relation-ları trigger edir → `LazyInitializationException`. `equals()`/`hashCode()` ID olmadan bütün field-ləri compare edir → yeni (persist olmamış) entity-lərlə problem yaranır.

**S: @RequiredArgsConstructor Spring DI üçün niyə ideal?**
C: `final` field-lər üçün constructor generate edir. Spring constructor injection üçün `@Autowired` olmadan da işləyir. Immutability + testability + circular dep detection — üç faydası var.

**S: @Builder + JPA entity problemi nədir?**
C: `@Builder` yalnız all-args constructor əlavə edir. JPA-nın no-args constructor-a ehtiyacı var. Həll: `@Builder` + `@NoArgsConstructor` + `@AllArgsConstructor` birlikdə.

**S: @Value vs @Data fərqi?**
C: `@Value` — immutable class (bütün field-lər final, yalnız getter). `@Data` — mutable class (getter + setter). DTO/Value Object üçün `@Value`, mutable entity üçün ayrı-ayrı annotasiyalar.
