# 11 — Spring Bean Definition və Stereotype Annotasiyalar

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [@Component — əsas stereotype](#component)
2. [@Service — business layer](#service)
3. [@Repository — DAO layer](#repository)
4. [@Controller/@RestController — web layer](#controller)
5. [Annotasiyalar arasındakı fərqlər](#ferqler)
6. [Component Scan](#component-scan)
7. [Bean adlandırma](#bean-adlandirma)
8. [İntervyu Sualları](#intervyu-suallar)

---

## @Component — əsas stereotype

`@Component` — Spring-ə həmin sinfi bean kimi qeydiyyatdan keçirməyi bildirən ümumi annotasiyadır. `@Service`, `@Repository`, `@Controller` — bunlar `@Component`-in ixtisaslaşmış versiyalarıdır.

```java
import org.springframework.stereotype.Component;

// Ümumi məqsədli komponent
// Heç bir spesifik layer-ə aid olmayan utility sinifləri üçün
@Component
public class EmailValidator {

    // Email formatını yoxlayan utility sinfi
    public boolean isValid(String email) {
        return email != null && email.contains("@") && email.contains(".");
    }
}

// @Component bean adı default olaraq camelCase sinif adıdır
// Bu bean "emailValidator" adı ilə qeydiyyata düşür
```

```java
// @Component utility/helper siniflər üçün
@Component
public class JsonMapper {

    private final ObjectMapper objectMapper;

    public JsonMapper(ObjectMapper objectMapper) {
        this.objectMapper = objectMapper;
    }

    public <T> T fromJson(String json, Class<T> type) {
        try {
            return objectMapper.readValue(json, type);
        } catch (JsonProcessingException e) {
            throw new RuntimeException("JSON parse xətası", e);
        }
    }

    public String toJson(Object object) {
        try {
            return objectMapper.writeValueAsString(object);
        } catch (JsonProcessingException e) {
            throw new RuntimeException("JSON serialize xətası", e);
        }
    }
}
```

---

## @Service — business layer

`@Service` — business logic-i olan sinifləri işarələyir. Texniki cəhətdən `@Component` ilə eynidir, lakin niyyəti bildirmək üçün istifadə edilir.

```java
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

// Business logic üçün Service sinfi
@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final PaymentService paymentService;
    private final NotificationService notificationService;

    // Constructor injection — tövsiyə olunan üsul
    public OrderService(
            OrderRepository orderRepository,
            PaymentService paymentService,
            NotificationService notificationService) {
        this.orderRepository = orderRepository;
        this.paymentService = paymentService;
        this.notificationService = notificationService;
    }

    @Transactional
    public Order placeOrder(CreateOrderRequest request) {
        // Business logic burada
        Order order = Order.builder()
            .customerId(request.getCustomerId())
            .items(request.getItems())
            .status(OrderStatus.PENDING)
            .build();

        // Ödəniş emal edilir
        PaymentResult result = paymentService.process(request.getPaymentInfo());

        if (result.isSuccessful()) {
            order.setStatus(OrderStatus.CONFIRMED);
            Order saved = orderRepository.save(order);
            notificationService.sendOrderConfirmation(saved);
            return saved;
        }

        throw new PaymentFailedException("Ödəniş uğursuz oldu: " + result.getError());
    }

    public Order findOrder(Long orderId) {
        return orderRepository.findById(orderId)
            .orElseThrow(() -> new OrderNotFoundException("Sifariş tapılmadı: " + orderId));
    }
}
```

```java
// Interface ilə @Service — best practice
public interface UserService {
    User createUser(CreateUserRequest request);
    User findById(Long id);
    void deleteUser(Long id);
}

@Service
public class UserServiceImpl implements UserService {

    private final UserRepository userRepository;
    private final PasswordEncoder passwordEncoder;

    public UserServiceImpl(UserRepository userRepository, PasswordEncoder passwordEncoder) {
        this.userRepository = userRepository;
        this.passwordEncoder = passwordEncoder;
    }

    @Override
    @Transactional
    public User createUser(CreateUserRequest request) {
        // Email unikallığını yoxlayırıq
        if (userRepository.existsByEmail(request.getEmail())) {
            throw new EmailAlreadyExistsException("Bu email artıq mövcuddur");
        }

        User user = User.builder()
            .email(request.getEmail())
            .password(passwordEncoder.encode(request.getPassword()))
            .name(request.getName())
            .build();

        return userRepository.save(user);
    }

    @Override
    public User findById(Long id) {
        return userRepository.findById(id)
            .orElseThrow(() -> new UserNotFoundException("İstifadəçi tapılmadı: " + id));
    }

    @Override
    @Transactional
    public void deleteUser(Long id) {
        User user = findById(id);
        userRepository.delete(user);
    }
}
```

---

## @Repository — DAO layer

`@Repository` — data access layer-i işarələyir. `@Component`-dən fərqli olaraq **exception translation** xüsusiyyəti var: platform-spesifik exception-ları (məs: `SQLException`) Spring-in `DataAccessException` ierarxiyasına çevirir.

```java
import org.springframework.stereotype.Repository;
import org.springframework.dao.DataAccessException;

// @Repository — DAO sinifləri üçün
@Repository
public class UserRepositoryCustom {

    private final JdbcTemplate jdbcTemplate;

    public UserRepositoryCustom(JdbcTemplate jdbcTemplate) {
        this.jdbcTemplate = jdbcTemplate;
    }

    public User findByEmail(String email) {
        // SQLException avtomatik DataAccessException-a çevrilir
        // Bu @Repository-nin xüsusi xüsusiyyətidir
        String sql = "SELECT * FROM users WHERE email = ?";
        return jdbcTemplate.queryForObject(sql,
            (rs, rowNum) -> User.builder()
                .id(rs.getLong("id"))
                .email(rs.getString("email"))
                .name(rs.getString("name"))
                .build(),
            email);
    }
}

// Spring Data JPA ilə @Repository
@Repository
public interface UserRepository extends JpaRepository<User, Long> {
    // Spring Data avtomatik implementasiya yaradır
    Optional<User> findByEmail(String email);
    boolean existsByEmail(String email);
    List<User> findByNameContainingIgnoreCase(String name);

    // JPQL query
    @Query("SELECT u FROM User u WHERE u.createdAt > :date")
    List<User> findRecentUsers(@Param("date") LocalDateTime date);
}
```

### Exception Translation

```java
// YANLIŞ: @Repository olmadan exception translation işləmir
@Component // @Repository əvəzinə @Component istifadə edirik
public class BadRepository {
    public void save(Object entity) {
        // SQLException buradan çıxa bilər
        // Amma @Repository olmadığından DataAccessException-a ÇEVRILMƏYƏCƏK
        // Caller SQLException-ı tutmalı olacaq — platform-specific
    }
}

// DOĞRU: @Repository ilə exception translation avtomatik işləyir
@Repository
public class GoodRepository {
    public void save(Object entity) {
        // SQLException avtomatik DataAccessException-a çevrilir
        // Caller Spring-in unified exception ierarxiyası ilə işləyir
        // Platform dəyişdirildikdə (məs: MySQL → PostgreSQL) exception handling dəyişmir
    }
}
```

---

## @Controller/@RestController — web layer

### @Controller

```java
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;

// MVC Controller — View qaytarır (Thymeleaf, JSP, etc.)
@Controller
@RequestMapping("/users")
public class UserController {

    private final UserService userService;

    public UserController(UserService userService) {
        this.userService = userService;
    }

    // View adı qaytarılır
    @GetMapping
    public String listUsers(Model model) {
        model.addAttribute("users", userService.findAll());
        return "users/list"; // templates/users/list.html
    }

    @GetMapping("/{id}")
    public String getUser(@PathVariable Long id, Model model) {
        model.addAttribute("user", userService.findById(id));
        return "users/detail";
    }

    // @ResponseBody ilə JSON da qaytarmaq olar
    @GetMapping("/api/{id}")
    @ResponseBody
    public User getUserApi(@PathVariable Long id) {
        return userService.findById(id);
    }
}
```

### @RestController

```java
import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;

// @RestController = @Controller + @ResponseBody
// REST API üçün — JSON/XML qaytarır
@RestController
@RequestMapping("/api/v1/users")
public class UserRestController {

    private final UserService userService;

    public UserRestController(UserService userService) {
        this.userService = userService;
    }

    @GetMapping
    public ResponseEntity<List<UserDto>> getAllUsers() {
        return ResponseEntity.ok(userService.findAll());
    }

    @GetMapping("/{id}")
    public ResponseEntity<UserDto> getUser(@PathVariable Long id) {
        UserDto user = userService.findById(id);
        return ResponseEntity.ok(user);
    }

    @PostMapping
    public ResponseEntity<UserDto> createUser(@RequestBody @Valid CreateUserRequest request) {
        UserDto created = userService.createUser(request);
        return ResponseEntity.status(HttpStatus.CREATED).body(created);
    }

    @PutMapping("/{id}")
    public ResponseEntity<UserDto> updateUser(
            @PathVariable Long id,
            @RequestBody @Valid UpdateUserRequest request) {
        UserDto updated = userService.updateUser(id, request);
        return ResponseEntity.ok(updated);
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> deleteUser(@PathVariable Long id) {
        userService.deleteUser(id);
        return ResponseEntity.noContent().build();
    }
}
```

---

## Annotasiyalar arasındakı fərqlər

```java
// Texniki cəhətdən hamısı @Component-dir
// Fərqli xüsusiyyətləri var:

// @Component — ümumi məqsəd, heç bir xüsusi davranış yoxdur
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Indexed
public @interface Component { String value() default ""; }

// @Service — @Component + semantik məna (business layer)
// Exception translation YOX
// @Transactional üçün AOP proxy yaradılır
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Component
public @interface Service { String value() default ""; }

// @Repository — @Component + Exception Translation
// PersistenceExceptionTranslationPostProcessor tərəfindən işlənir
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Component
public @interface Repository { String value() default ""; }

// @Controller — @Component + MVC dispatch mechanism
// DispatcherServlet tərəfindən tanınır
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Component
public @interface Controller { String value() default ""; }

// @RestController — @Controller + @ResponseBody
// Bütün metodlar JSON/XML qaytarır
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Controller
@ResponseBody
public @interface RestController { String value() default ""; }
```

### Müqayisə cədvəli

| Annotasiya | Layer | Exception Translation | View resolution | JSON Response |
|-----------|-------|----------------------|-----------------|---------------|
| `@Component` | İstənilən | ✗ | ✗ | ✗ |
| `@Service` | Business | ✗ | ✗ | ✗ |
| `@Repository` | Data Access | ✓ | ✗ | ✗ |
| `@Controller` | Web | ✗ | ✓ | ✗ (default) |
| `@RestController` | Web/REST | ✗ | ✗ | ✓ |

---

## Component Scan

### @ComponentScan

```java
import org.springframework.context.annotation.ComponentScan;
import org.springframework.context.annotation.Configuration;
import org.springframework.context.annotation.FilterType;

// Əsas konfiqurasiya
@Configuration
@ComponentScan(basePackages = "com.example")
public class AppConfig {
    // com.example və alt paketlərdəki bütün @Component-lər tapılır
}

// Daha dəqiq konfiqurasiya
@Configuration
@ComponentScan(
    basePackages = {"com.example.service", "com.example.repository"},
    // Spesifik paketlər əvəzinə siniflər (refactoring-safe)
    basePackageClasses = {UserService.class, UserRepository.class},
    // Müəyyən annotasiyaları istisna etmək
    excludeFilters = @ComponentScan.Filter(
        type = FilterType.ANNOTATION,
        classes = Controller.class
    ),
    // Yalnız müəyyən annotasiyaları daxil etmək
    includeFilters = @ComponentScan.Filter(
        type = FilterType.ANNOTATION,
        classes = Service.class
    )
)
public class ServiceConfig {}
```

### @SpringBootApplication daxilindəki scan

```java
// @SpringBootApplication = @Configuration + @EnableAutoConfiguration + @ComponentScan
@SpringBootApplication
public class MyApplication {
    // Bu sinifin paketindən başlayaraq bütün alt paketlər skan edilir
    // com.example.myapp.* — bütün sinifləri tapır
    public static void main(String[] args) {
        SpringApplication.run(MyApplication.class, args);
    }
}

// YANLIŞ: @SpringBootApplication-ı yanlış yerdə qoymaaq
package com.example;
@SpringBootApplication
public class MyApplication {
    // com.example-dəki bütün şeyləri skan edir — çox geniş!
    // Third-party library-lərin sinifləri də scan edilə bilər
}

// DOĞRU: Spesifik paketdə saxlamaq
package com.example.myapp;
@SpringBootApplication
public class MyApplication {
    // Yalnız com.example.myapp.* skan edilir
}
```

### Xüsusi filter-lər

```java
// Custom TypeFilter
public class CustomAnnotationFilter implements TypeFilter {

    @Override
    public boolean match(MetadataReader metadataReader,
                        MetadataReaderFactory metadataReaderFactory) throws IOException {
        // Annotasiya metadata-sını yoxlayırıq
        AnnotationMetadata annotationMetadata =
            metadataReader.getAnnotationMetadata();
        return annotationMetadata.hasAnnotation("com.example.MyCustomAnnotation");
    }
}

@Configuration
@ComponentScan(
    basePackages = "com.example",
    includeFilters = @ComponentScan.Filter(
        type = FilterType.CUSTOM,
        classes = CustomAnnotationFilter.class
    )
)
public class CustomScanConfig {}
```

---

## Bean adlandırma

### Default adlandırma

```java
// Default: sinif adının ilk hərfi kiçik — camelCase
@Component
public class EmailService {} // bean adı: "emailService"

@Service
public class UserAccountService {} // bean adı: "userAccountService"

@Repository
public class UserJpaRepository {} // bean adı: "userJpaRepository"

// Xüsusi hal: ardıcıl böyük hərflər
@Component
public class XMLParser {} // bean adı: "XMLParser" (dəyişməz qalır!)

@Component
public class HTTPSClient {} // bean adı: "HTTPSClient"
```

### Xüsusi bean adı

```java
// Annotasiyada ad təyin etmək
@Component("emailValidator")
public class EmailValidationService {
    // Bean adı: "emailValidator"
}

@Service("orderSvc")
public class OrderServiceImpl implements OrderService {
    // Bean adı: "orderSvc"
}

// @Bean metodunda ad
@Configuration
public class AppConfig {

    @Bean("primaryDataSource")
    public DataSource dataSource() {
        return new HikariDataSource();
    }

    // Bir neçə alias vermək
    @Bean({"paymentGateway", "stripePayment", "defaultPayment"})
    public PaymentGateway stripeGateway() {
        return new StripePaymentGateway();
    }
}
```

### Xüsusi BeanNameGenerator

```java
// Default BeanNameGenerator-i dəyişdirmək
public class FQNBeanNameGenerator implements BeanNameGenerator {

    @Override
    public String generateBeanName(BeanDefinition definition,
                                   BeanDefinitionRegistry registry) {
        // Tam qualified sinif adını bean adı kimi istifadə etmək
        // Adlar arasındakı konfliktləri aradan qaldırır
        return definition.getBeanClassName();
    }
}

@Configuration
@ComponentScan(
    basePackages = "com.example",
    nameGenerator = FQNBeanNameGenerator.class
)
public class AppConfig {}
```

### Qualifier ilə adlandırma

```java
// Eyni interface-in bir neçə implementasiyası varsa
public interface NotificationService {
    void send(String message);
}

@Service("emailNotification")
public class EmailNotificationService implements NotificationService {
    @Override
    public void send(String message) {
        // Email göndər
    }
}

@Service("smsNotification")
public class SmsNotificationService implements NotificationService {
    @Override
    public void send(String message) {
        // SMS göndər
    }
}

// İstifadə
@Service
public class AlertService {

    private final NotificationService emailNotification;
    private final NotificationService smsNotification;

    // @Qualifier ilə konkret bean seçilir
    public AlertService(
            @Qualifier("emailNotification") NotificationService emailNotification,
            @Qualifier("smsNotification") NotificationService smsNotification) {
        this.emailNotification = emailNotification;
        this.smsNotification = smsNotification;
    }
}
```

---

## İntervyu Sualları

**S: @Component, @Service, @Repository, @Controller arasındakı fərq nədir?**
C: Hamısı `@Component`-in ixtisaslaşmış versiyalarıdır. `@Repository` fərqlidir — `PersistenceExceptionTranslationPostProcessor` vasitəsilə platform-spesifik exception-ları `DataAccessException`-a çevirir. `@Controller` `DispatcherServlet` tərəfindən tanınır. `@Service`-in texniki əlavəsi yoxdur — yalnız semantik məna daşıyır. `@RestController` = `@Controller` + `@ResponseBody`.

**S: Component scan necə işləyir?**
C: `@ComponentScan` göstərilən paketlər daxilindəki bütün sinifləri oxuyur. `@Component` (və onun törəmə annotasiyaları) olan siniflər tapılır, `BeanDefinition`-lara çevrilir və container-ə qeydiyyatdan keçirilir. Spring Boot-da `@SpringBootApplication`-ın olduğu paketin bütün alt paketləri avtomatik skan edilir.

**S: Bean adı necə müəyyən edilir?**
C: Default olaraq sinif adının ilk hərfi kiçik edilir (camelCase). Annotasiyada `value` parametri ilə (`@Component("myBean")`) xüsusi ad vermək mümkündür. `@Bean` metodunda da `name` atributu istifadə edilir. Xüsusi `BeanNameGenerator` implement edərək bu davranışı override etmək olar.

**S: @Repository olmadan Spring Data JPA repository-si işləyərmi?**
C: Bəli, Spring Data JPA `@Repository`-siz də işləyir çünki Spring Data özü exception translation mexanizmini tətbiq edir. Amma best practice olaraq `@Repository` annotasiyası yazılmalıdır — kod oxunaqlılığı və layer arxitekturasını bildirmək üçün.

**S: @RestController nədir?**
C: `@Controller` + `@ResponseBody` kombinasiyasıdır. Sinif-səviyyəsində `@ResponseBody` əlavə etdiyindən bütün handler metodları avtomatik olaraq HTTP response body-nə JSON/XML yazır. View resolution mexanizmi işə düşmür.
