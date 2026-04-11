# 88. Spring Data Projections

## Mündəricat
1. [Projeksiyanın Faydaları](#projeksiyanın-faydaları)
2. [Interface Projection](#interface-projection)
3. [Nested Projections](#nested-projections)
4. [SpEL @Value Interface Projection-da](#spel-value-interface-projectionda)
5. [Class-based Projection (DTO Constructor)](#class-based-projection-dto-constructor)
6. [Dynamic Projection](#dynamic-projection)
7. [YANLIŞ vs DOĞRU Patterns](#yanliş-vs-doğru-patterns)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Projeksiyanın Faydaları

Proyeksiya (Projection) — verilənlər bazasından yalnız lazım olan sütunları seçmək texnikasıdır. Tam entity yükləmək əvəzinə yalnız tələb olunan field-ləri gətirir.

```java
// Proyeksiya olmadan - tam entity yüklənir
@Entity
public class User {
    private Long id;
    private String username;
    private String email;
    private String password;      // Gizli!
    private String firstName;
    private String lastName;
    private LocalDate birthDate;
    private String address;
    private String phone;
    private byte[] profilePhoto;   // Böyük data!
    private LocalDateTime createdAt;
    // ... daha çox field
}

// Sadəcə ad göstərmək üçün bütün user yüklənirsə:
// SELECT id, username, email, password, first_name, last_name,
//        birth_date, address, phone, profile_photo, created_at
// FROM users WHERE id = ?
// → Çox artıq data ötürülür!

// Proyeksiya ilə - yalnız lazım olanlar:
// SELECT username, first_name, last_name FROM users WHERE id = ?
```

**Üstünlüklər:**
1. Daha az data ötürülür (SELECT yalnız lazım olan sütunlar)
2. Daha sürətli sorğular
3. Sensitive data-nın (password, token) gizli qalması
4. DTO-ya çevirmə əziyyəti azalır

---

## Interface Projection

Ən sadə proyeksiya növü. Java interfeysi yaratmaq kifayətdir — Spring Data implementasiyanı proxy ilə yaradır.

```java
// Interface projection təyin etmək
public interface UserSummary {
    Long getId();
    String getUsername();
    String getFirstName();
    String getLastName();
    // password, profilePhoto, vs. - bunlar YOX
}

// Repository metodunda istifadə
@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    // Bütün user-lər üçün yalnız id, username, firstName, lastName seçilir
    List<UserSummary> findAllProjectedBy();

    // Şərtlə birlikdə
    List<UserSummary> findByActiveTrue();

    // Custom @Query ilə
    @Query("SELECT u FROM User u WHERE u.role = :role")
    List<UserSummary> findByRole(@Param("role") String role);

    // Tək entity - Optional<Projection>
    Optional<UserSummary> findProjectedById(Long id);
}
```

```java
// İstifadə
@Service
@RequiredArgsConstructor
public class UserService {

    private final UserRepository userRepository;

    // Yalnız lazım olan field-lər yüklənir
    public List<UserSummary> getUserList() {
        return userRepository.findAllProjectedBy();
        // SQL: SELECT u.id, u.username, u.first_name, u.last_name FROM users u
    }

    public UserSummary getUserSummary(Long id) {
        return userRepository.findProjectedById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
    }
}

// Controller-də birbaşa proyeksiya qaytarmaq
@RestController
@RequiredArgsConstructor
@RequestMapping("/api/users")
public class UserController {

    private final UserService userService;

    @GetMapping
    public List<UserSummary> getUsers() {
        return userService.getUserList(); // Entity-ə çevirmə lazım deyil
    }
}
```

### Projection Field Adlarının Uyğunluğu

```java
// Entity
@Entity
public class Product {
    @Column(name = "product_name")
    private String name;         // Java field adı: name

    @Column(name = "unit_price")
    private BigDecimal price;    // Java field adı: price
}

// Interface Projection - Java field adlarına görə (sütun adı yox!)
public interface ProductInfo {
    Long getId();
    String getName();       // name field-inə uyğundur (product_name sütunu deyil)
    BigDecimal getPrice();  // price field-inə uyğundur
}
```

---

## Nested Projections

Əlaqəli entity-lərin field-lərini də proyeksiyaya daxil etmək üçün istifadə edilir.

```java
// Entity-lər
@Entity
public class Order {
    @Id
    private Long id;
    private BigDecimal total;
    private String status;

    @ManyToOne(fetch = FetchType.LAZY)
    private Customer customer;

    @OneToMany(mappedBy = "order")
    private List<OrderItem> items;
}

@Entity
public class Customer {
    @Id
    private Long id;
    private String name;
    private String email;
    private String phone;   // Lazım deyil
}

@Entity
public class OrderItem {
    @Id
    private Long id;
    private int quantity;
    private BigDecimal unitPrice;

    @ManyToOne
    private Product product;
}
```

```java
// Nested Interface Projection
public interface OrderSummary {
    Long getId();
    BigDecimal getTotal();
    String getStatus();

    // Nested projection - Customer-in yalnız id və name-i
    CustomerInfo getCustomer();

    // Nested list projection
    List<ItemInfo> getItems();

    // Nested nested projection
    interface CustomerInfo {
        Long getId();
        String getName();
        String getEmail();
        // phone - yüklənmir
    }

    interface ItemInfo {
        Long getId();
        int getQuantity();
        BigDecimal getUnitPrice();

        // Daha dərin nested
        ProductBasicInfo getProduct();

        interface ProductBasicInfo {
            Long getId();
            String getName();
        }
    }
}
```

```java
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    List<OrderSummary> findByStatus(String status);

    Optional<OrderSummary> findProjectedById(Long id);
}
```

**Diqqət:** Nested projection-lar JOIN edir — LazyInitializationException olmaz.

```java
// SQL nümunəsi (nested projection üçün Hibernate yaradır):
// SELECT o.id, o.total, o.status,
//        c.id, c.name, c.email,
//        oi.id, oi.quantity, oi.unit_price,
//        p.id, p.name
// FROM orders o
// LEFT JOIN customers c ON o.customer_id = c.id
// LEFT JOIN order_items oi ON oi.order_id = o.id
// LEFT JOIN products p ON oi.product_id = p.id
// WHERE o.status = ?
```

---

## SpEL @Value Interface Projection-da

Spring Expression Language (SpEL) ilə proyeksiyada hesablanmış field-lər yaratmaq mümkündür.

```java
// SpEL ilə Interface Projection
public interface UserDisplayInfo {
    Long getId();
    String getUsername();
    String getFirstName();
    String getLastName();

    // SpEL ilə hesablanmış dəyər
    // target → proyeksiya mənbəyi (User entity)
    @Value("#{target.firstName + ' ' + target.lastName}")
    String getFullName();

    // Boolean hesablama
    @Value("#{target.role == 'ADMIN'}")
    boolean isAdmin();

    // Conditional
    @Value("#{target.active ? 'Aktiv' : 'Passiv'}")
    String getStatusLabel();

    // Nested field
    @Value("#{target.address?.city}")  // null-safe navigation
    String getCity();

    // Formatlanmış dəyər (SpEL T() ilə)
    @Value("#{T(java.lang.String).format('%.2f AZN', target.balance)}")
    String getFormattedBalance();
}
```

```java
@Repository
public interface UserRepository extends JpaRepository<User, Long> {
    List<UserDisplayInfo> findByActiveTrue();
}

// İstifadə
@Service
@RequiredArgsConstructor
public class UserService {
    private final UserRepository userRepository;

    public void demonstrateSpEl() {
        List<UserDisplayInfo> users = userRepository.findByActiveTrue();
        for (UserDisplayInfo user : users) {
            System.out.println(user.getFullName());    // "John Doe"
            System.out.println(user.isAdmin());         // true/false
            System.out.println(user.getStatusLabel());  // "Aktiv"
        }
    }
}
```

**Diqqət:** `@Value` ilə SpEL istifadə edildikdə, Spring Data bütün entity-ni yükləyir (optimizasiya olmur). Yalnız hesablama lazım olduqda istifadə edin.

---

## Class-based Projection (DTO Constructor)

Java sinfi ilə proyeksiya — immutable, tipli DTO-lar üçün.

```java
// DTO sinfi - record (Java 16+) ilə
public record ProductDTO(
    Long id,
    String name,
    BigDecimal price,
    String categoryName
) {}

// DTO sinfi - adi class ilə
public class ProductDTO {
    private final Long id;
    private final String name;
    private final BigDecimal price;
    private final String categoryName;

    // Bu constructor JPQL tərəfindən çağırılır
    public ProductDTO(Long id, String name, BigDecimal price, String categoryName) {
        this.id = id;
        this.name = name;
        this.price = price;
        this.categoryName = categoryName;
    }

    // getter-lər
    public Long getId() { return id; }
    public String getName() { return name; }
    public BigDecimal getPrice() { return price; }
    public String getCategoryName() { return categoryName; }
}
```

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // JPQL constructor expression - class-based projection
    @Query("SELECT new com.example.dto.ProductDTO(" +
           "p.id, p.name, p.price, p.category.name) " +
           "FROM Product p " +
           "WHERE p.active = true")
    List<ProductDTO> findActiveProductDTOs();

    // Şərtlə
    @Query("SELECT new com.example.dto.ProductDTO(" +
           "p.id, p.name, p.price, p.category.name) " +
           "FROM Product p " +
           "WHERE p.category.id = :categoryId")
    List<ProductDTO> findDTOsByCategory(@Param("categoryId") Long categoryId);

    // Sayım daxil
    @Query("SELECT new com.example.dto.CategoryStatsDTO(" +
           "p.category.name, COUNT(p), AVG(p.price), MIN(p.price), MAX(p.price)) " +
           "FROM Product p GROUP BY p.category.name")
    List<CategoryStatsDTO> findCategoryStats();
}
```

### Interface Projection vs Class-based DTO

| Xüsusiyyət | Interface Projection | Class-based DTO |
|------------|---------------------|-----------------|
| Sintaksis | Sadə — interfeys yarat | Constructor tələb edir |
| Nested support | Bəli (nested interfeys) | Mürəkkəb |
| SpEL dəstəyi | Bəli | Xeyr |
| Immutability | Xeyr (proxy) | Bəli (final fields) |
| Performance | Çox yaxşı | Yaxşı |
| Type safety | Orta | Yüksək |
| Custom query tələbi | Xeyr (method name kifayət) | Bəli (@Query lazımdır) |

---

## Dynamic Projection

Eyni repository metodundan müxtəlif proyeksiya tiplərini qaytarmaq üçün generic tip parametri istifadə edilir.

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Generic T - çağırılan yerdə tip müəyyən edilir
    <T> List<T> findByCategory(Category category, Class<T> type);

    <T> Optional<T> findProjectedById(Long id, Class<T> type);

    <T> Page<T> findByActiveTrue(Pageable pageable, Class<T> type);

    // Şərtlə birlikdə
    <T> List<T> findByPriceGreaterThan(BigDecimal price, Class<T> type);
}
```

```java
// Proyeksiya interfeysləri
public interface ProductSummary {
    Long getId();
    String getName();
}

public interface ProductDetail {
    Long getId();
    String getName();
    BigDecimal getPrice();
    String getCategoryName();
    String getDescription();
}

// DTO class
public record ProductExport(Long id, String name, BigDecimal price) {}
```

```java
// Dynamic projection istifadəsi
@Service
@RequiredArgsConstructor
public class ProductService {

    private final ProductRepository productRepository;
    private final CategoryRepository categoryRepository;

    // Yalnız adlar lazım olduqda
    public List<ProductSummary> getProductNames(Long categoryId) {
        Category category = categoryRepository.getReferenceById(categoryId);
        return productRepository.findByCategory(category, ProductSummary.class);
    }

    // Tam məlumat lazım olduqda
    public List<ProductDetail> getProductDetails(Long categoryId) {
        Category category = categoryRepository.getReferenceById(categoryId);
        return productRepository.findByCategory(category, ProductDetail.class);
    }

    // Tam entity lazım olduqda
    public List<Product> getFullProducts(Long categoryId) {
        Category category = categoryRepository.getReferenceById(categoryId);
        return productRepository.findByCategory(category, Product.class); // Entity.class
    }

    // Export üçün
    public List<ProductExport> getExportData(Long categoryId) {
        Category category = categoryRepository.getReferenceById(categoryId);
        // DTO ilə dynamic projection nativeQuery olmadan işləmir
        // DTO üçün @Query lazımdır
        return productRepository.findByCategory(category, ProductExport.class);
    }

    // API response-a görə dinamik seçim
    public <T> List<T> getProducts(Long categoryId, Class<T> projectionType) {
        Category category = categoryRepository.getReferenceById(categoryId);
        return productRepository.findByCategory(category, projectionType);
    }
}
```

### Dynamic Projection ilə Controller

```java
@RestController
@RequiredArgsConstructor
@RequestMapping("/api/products")
public class ProductController {

    private final ProductService productService;

    // Müxtəlif endpoint-lər - eyni repository metodu
    @GetMapping("/summary")
    public List<ProductSummary> getSummary(@RequestParam Long categoryId) {
        return productService.getProducts(categoryId, ProductSummary.class);
    }

    @GetMapping("/detail")
    public List<ProductDetail> getDetail(@RequestParam Long categoryId) {
        return productService.getProducts(categoryId, ProductDetail.class);
    }
}
```

---

## YANLIŞ vs DOĞRU Patterns

### 1. Tam Entity qaytarmaq - lazımsız data

```java
// YANLIŞ - siyahı üçün tam entity yükləmək
@GetMapping("/users")
public List<User> getUsers() {
    return userRepository.findAll();
    // password, profilePhoto, sensitiveField-lər də göndərilir!
    // Bütün sütunlar yüklənir
}

// DOĞRU - yalnız lazım olanları proyeksiya ilə
public interface UserListItem {
    Long getId();
    String getUsername();
    String getFullName();
    String getEmail();
}

@GetMapping("/users")
public List<UserListItem> getUsers() {
    return userRepository.findAllProjectedBy();
    // SQL: SELECT id, username, full_name, email FROM users
}
```

### 2. SpEL-i aşırı istifadə etmək

```java
// YANLIŞ - SpEL hər field üçün entity-ni tam yükləyir
public interface ProductProjection {
    @Value("#{target.id}")       // SpEL - tam entity yüklənir
    Long getId();
    @Value("#{target.name}")     // SpEL - optimizasiya YOX
    String getName();
    @Value("#{target.price}")    // SpEL - bütün field-lər yüklənir
    BigDecimal getPrice();
}

// DOĞRU - getter metodları istifadə et (optimizasiya var)
public interface ProductProjection {
    Long getId();        // SpEL yox - yalnız bu sütun seçilir
    String getName();
    BigDecimal getPrice();
}

// SpEL yalnız HESABLANMIŞ field-lər üçün
public interface ProductProjection {
    Long getId();
    String getName();
    BigDecimal getPrice();
    @Value("#{target.name + ' - ' + target.price}") // Yalnız bu üçün SpEL
    String getLabel();
}
```

### 3. Yanlış getter adı

```java
// Entity field: firstName
@Entity
public class User {
    private String firstName;
}

// YANLIŞ - getter adı field adı ilə uyğun gəlmir
public interface UserInfo {
    String getFirst_name();  // Xəta! field adı firstName-dir, first_name yox
    String firstname();       // Xəta! get prefiksi lazımdır
}

// DOĞRU
public interface UserInfo {
    String getFirstName();   // firstName field-inə uyğun
}
```

### 4. DTO constructor-un yanlış parametr sayı

```java
// JPQL constructor expression
@Query("SELECT new com.example.dto.ProductDTO(p.id, p.name, p.price) FROM Product p")
List<ProductDTO> findDTOs();

// YANLIŞ - constructor parametr sayı uyğun deyil
public class ProductDTO {
    public ProductDTO(Long id, String name) { // 2 parametr, amma JPQL 3 göndərir
        // QueryException atılır!
    }
}

// DOĞRU - constructor tam uyğun olmalıdır (sayı VƏ TİPLƏR)
public class ProductDTO {
    public ProductDTO(Long id, String name, BigDecimal price) { // 3 parametr
        this.id = id;
        this.name = name;
        this.price = price;
    }
}
```

### 5. Native query ilə interface projection - alias problemi

```java
// YANLIŞ - alias yoxdur, getter ilə uyğunlaşmır
@Query(value = "SELECT p.id, p.product_name, p.unit_price FROM products p",
       nativeQuery = true)
List<ProductSummary> findSummaries();

public interface ProductSummary {
    Long getId();
    String getName();    // 'product_name' alias-ı yox - null qaytarır!
    BigDecimal getPrice(); // 'unit_price' alias-ı yox - null qaytarır!
}

// DOĞRU - alias adları getter adları ilə uyğun olmalıdır
@Query(value = "SELECT p.id, p.product_name AS name, p.unit_price AS price " +
               "FROM products p",
       nativeQuery = true)
List<ProductSummary> findSummaries();
// AS name → getName(), AS price → getPrice()
```

---

## İntervyu Sualları

**S: Spring Data-da proyeksiya növləri hansılardır?**

C: 3 növ var: 1) **Interface projection** — Java interfeysi yaratmaq kifayətdir, Spring Data proxy yaradır. Nested projection-ları dəstəkləyir. 2) **Class-based projection (DTO)** — JPQL `new ClassName(...)` constructor expression ilə. 3) **Dynamic projection** — Generic `<T>` parametri ilə, çağırılan yerdə tip müəyyən edilir.

**S: Interface projection ilə class-based DTO arasındakı əsas fərq nədir?**

C: Interface projection-da Spring Data proxy yaradır, method name-ə görə field-ləri seçir — `@Query` lazım deyil. Class-based DTO-da JPQL `SELECT new Dto(...)` constructor expression istifadə edilir — `@Query` məcburidir, lakin daha tipli (type-safe) və immutable-dır.

**S: SpEL `@Value` interface projection-da nə vaxt optimizasiyasını pozur?**

C: Hər hansı bir getter-dən `@Value` SpEL istifadə edildikdə, Spring Data bütün entity-ni yükləyir — yalnız seçilmiş sütunlar deyil. Ona görə SpEL yalnız həqiqətən hesablama lazım olduqda (birləşdirilmiş ad, şərtli dəyər) istifadə edilməlidir. Adi field-lər üçün `@Value` olmadan getter-dən istifadə edin.

**S: Dynamic projection nə üçün faydalıdır?**

C: Eyni repository metodundan müxtəlif proyeksiya tiplərini qaytarmaq imkanı verir. Məsələn, siyahı üçün yalnız id+name, detal üçün bütün field-lər, export üçün başqa format — hamısı eyni `findByCategory(category, ProjectionType.class)` metodu ilə. Kod təkrarını azaldır.

**S: Native query ilə interface projection işlədərkən null dəyər problemi niyə baş verir?**

C: Interface projection getter adlarına uyğun SQL alias-lar axtarır. Məsələn `getName()` → SQL-də `name` alias-ı. Əgər sütun adı `product_name`-dirsə amma alias yoxdursa, `getName()` null qaytarır. Həll: `SELECT product_name AS name` şəklində alias vermək.
