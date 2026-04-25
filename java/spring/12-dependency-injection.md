# 12 — Spring Dependency Injection

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [DI növləri](#di-növləri)
2. [Constructor Injection](#constructor-injection)
3. [Setter Injection](#setter-injection)
4. [Field Injection](#field-injection)
5. [@Autowired, @Qualifier, @Primary](#autowired-qualifier-primary)
6. [Circular Dependency problemi](#circular-dependency-problemi)
7. [@Lazy ilə həll](#lazy-ilə-həll)
8. [İntervyu Sualları](#intervyu-sualları)

---

## DI növləri

Spring üç növ Dependency Injection dəstəkləyir:
1. **Constructor Injection** — tövsiyə olunan
2. **Setter Injection** — optional asılılıqlar üçün
3. **Field Injection** — tövsiyə olunmur

---

## Constructor Injection

**Tövsiyə olunan yanaşmadır.** Asılılıqlar immutable olur, test etmək asandır.

```java
// DOĞRU — Constructor injection
@Service
public class OrderService {

    // final — immutable asılılıqlar
    private final PaymentService paymentService;
    private final InventoryService inventoryService;
    private final NotificationService notificationService;

    // Spring tək constructor-u avtomatik tapır
    // @Autowired yazmağa ehtiyac yoxdur (Spring 4.3+)
    public OrderService(
            PaymentService paymentService,
            InventoryService inventoryService,
            NotificationService notificationService) {
        // null yoxlaması — fail-fast
        this.paymentService = Objects.requireNonNull(paymentService);
        this.inventoryService = Objects.requireNonNull(inventoryService);
        this.notificationService = Objects.requireNonNull(notificationService);
    }

    public Order createOrder(Cart cart) {
        // Asılılıqlardan istifadə
        inventoryService.reserve(cart.getItems());
        Order order = new Order(cart);
        paymentService.charge(order);
        notificationService.sendConfirmation(order);
        return order;
    }
}

// Lombok ilə — @RequiredArgsConstructor
@Service
@RequiredArgsConstructor // final sahələr üçün constructor yaradır
public class ProductService {

    private final ProductRepository productRepository;
    private final CategoryRepository categoryRepository;
    private final PriceCalculator priceCalculator;

    // Lombok yuxarıdakı constructor-u avtomatik yaradır:
    // public ProductService(ProductRepository pr, CategoryRepository cr, PriceCalculator pc) { ... }

    public Product findById(Long id) {
        return productRepository.findById(id)
            .orElseThrow(() -> new ProductNotFoundException("Məhsul tapılmadı: " + id));
    }
}
```

### Constructor injection-un üstünlükləri

```java
// Test etmək son dərəcə asandır — Spring lazım deyil!
class OrderServiceTest {

    private OrderService orderService;
    private PaymentService mockPaymentService;
    private InventoryService mockInventoryService;
    private NotificationService mockNotificationService;

    @BeforeEach
    void setUp() {
        // Mock-ları inject edirik
        mockPaymentService = mock(PaymentService.class);
        mockInventoryService = mock(InventoryService.class);
        mockNotificationService = mock(NotificationService.class);

        // Spring olmadan test — sadə Java
        orderService = new OrderService(
            mockPaymentService,
            mockInventoryService,
            mockNotificationService
        );
    }

    @Test
    void shouldCreateOrderSuccessfully() {
        Cart cart = new Cart();
        Order result = orderService.createOrder(cart);
        assertNotNull(result);
        verify(mockPaymentService).charge(any(Order.class));
    }
}
```

---

## Setter Injection

**Optional asılılıqlar üçün** istifadə olunur.

```java
@Service
public class ReportService {

    private final DataSource primaryDataSource; // Məcburi
    private EmailService emailService;          // Optional

    // Məcburi asılılıq — constructor injection
    public ReportService(DataSource primaryDataSource) {
        this.primaryDataSource = primaryDataSource;
    }

    // Optional asılılıq — setter injection
    @Autowired(required = false) // Bean tapılmasa da xəta vermir
    public void setEmailService(EmailService emailService) {
        this.emailService = emailService;
    }

    public Report generateReport(ReportRequest request) {
        Report report = buildReport(request);

        // Null yoxlaması — optional asılılıq
        if (emailService != null) {
            emailService.sendReport(report, request.getEmail());
        }

        return report;
    }

    private Report buildReport(ReportRequest request) {
        // Hesabat qurma məntiqi
        return new Report();
    }
}
```

---

## Field Injection

**Tövsiyə olunmur** — amma mövcuddur.

```java
// YANLIŞ — Field injection problematikdir
@Service
public class UserService {

    @Autowired // Spring container olmadan inject edilə bilməz
    private UserRepository userRepository;

    @Autowired
    private EmailService emailService;

    // Problemlər:
    // 1. Test üçün Spring container lazımdır (yaxud reflection)
    // 2. final işarətləmək olmur — immutability yoxdur
    // 3. NullPointerException riski constructor-da
    // 4. Asılılıqlar gizlənir — "hidden dependencies"
}

// DOĞRU — Constructor injection
@Service
@RequiredArgsConstructor
public class UserService {

    private final UserRepository userRepository; // final, immutable
    private final EmailService emailService;      // final, immutable

    // Test üçün Spring lazım deyil, plain Java konstruktoru işləyir
}
```

### Field injection-u məcbur edən hallar

```java
// Test siniflərində field injection məqbuldur
@SpringBootTest
class UserServiceIntegrationTest {

    @Autowired // Test sinifini Spring idarə etmir, buna görə OK
    private UserService userService;

    @MockBean
    private EmailService emailService;

    @Test
    void shouldRegisterUser() {
        User user = userService.register("test@example.com");
        assertNotNull(user.getId());
    }
}
```

---

## @Autowired, @Qualifier, @Primary

### @Autowired

```java
@Service
public class NotificationManager {

    // Tək implementation varsa — avtomatik inject edilir
    @Autowired
    private NotificationService notificationService;

    // Collection inject etmək — bütün implementasiyaları alır
    @Autowired
    private List<NotificationService> allNotificationServices;

    // Map inject etmək — bean adı → bean
    @Autowired
    private Map<String, NotificationService> notificationServiceMap;
}
```

### Çoxlu implementation — @Qualifier

```java
// İki fərqli implementation
@Service("emailNotification")
public class EmailNotificationService implements NotificationService {
    @Override
    public void notify(String message) {
        System.out.println("Email: " + message);
    }
}

@Service("smsNotification")
public class SmsNotificationService implements NotificationService {
    @Override
    public void notify(String message) {
        System.out.println("SMS: " + message);
    }
}

// @Qualifier ilə konkret bean seçmək
@Service
public class AlertService {

    private final NotificationService emailService;
    private final NotificationService smsService;

    public AlertService(
            @Qualifier("emailNotification") NotificationService emailService,
            @Qualifier("smsNotification") NotificationService smsService) {
        this.emailService = emailService;
        this.smsService = smsService;
    }

    public void sendCriticalAlert(String message) {
        // Hər iki kanaldan göndər
        emailService.notify(message);
        smsService.notify(message);
    }
}
```

### @Primary — default bean seçimi

```java
@Service
@Primary // Başqaları @Qualifier göstərməsə bu seçilir
public class EmailNotificationService implements NotificationService {
    // ...
}

@Service
public class SmsNotificationService implements NotificationService {
    // ...
}

@Service
public class UserService {

    // @Qualifier yoxdur → @Primary olan EmailNotificationService inject edilir
    private final NotificationService notificationService;

    public UserService(NotificationService notificationService) {
        this.notificationService = notificationService;
    }
}
```

### Custom @Qualifier annotation

```java
// Custom qualifier yaratmaq
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.METHOD})
@Retention(RetentionPolicy.RUNTIME)
@Qualifier
public @interface FastNotification {}

@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.METHOD})
@Retention(RetentionPolicy.RUNTIME)
@Qualifier
public @interface ReliableNotification {}

// İstifadə
@Service
@FastNotification
public class SmsNotificationService implements NotificationService { ... }

@Service
@ReliableNotification
public class EmailNotificationService implements NotificationService { ... }

@Service
public class OrderNotifier {

    public OrderNotifier(
            @FastNotification NotificationService fastService,
            @ReliableNotification NotificationService reliableService) {
        // ...
    }
}
```

---

## Circular Dependency problemi

### Problem nədir?

```java
// PROBLEM — Circular dependency
@Service
public class ServiceA {
    private final ServiceB serviceB; // ServiceB lazımdır

    public ServiceA(ServiceB serviceB) {
        this.serviceB = serviceB;
    }
}

@Service
public class ServiceB {
    private final ServiceA serviceA; // ServiceA lazımdır!

    public ServiceB(ServiceA serviceA) {
        this.serviceA = serviceA;
    }
}

// Spring xətası:
// BeanCurrentlyInCreationException:
// Error creating bean with name 'serviceA':
// Requested bean is currently in creation: Is there an unresolvable circular reference?
```

### Həll 1 — Dizaynı yenidən düşünmək (ən yaxşı həll)

```java
// Ümumi funksionallığı ayrı service-ə çıxarmaq
@Service
public class CommonService {
    // A və B-nin ortaq istifadə etdiyi funksionallıq
    public void sharedOperation() { ... }
}

@Service
@RequiredArgsConstructor
public class ServiceA {
    private final CommonService commonService; // Artıq B-yə ehtiyac yoxdur
}

@Service
@RequiredArgsConstructor
public class ServiceB {
    private final CommonService commonService; // Artıq A-ya ehtiyac yoxdur
}
```

### Həll 2 — Setter injection ilə

```java
@Service
public class ServiceA {
    private ServiceB serviceB;

    // Constructor-da deyil, setter ilə inject — circular dependency həll olur
    @Autowired
    public void setServiceB(ServiceB serviceB) {
        this.serviceB = serviceB;
    }
}

@Service
@RequiredArgsConstructor
public class ServiceB {
    private final ServiceA serviceA; // Constructor injection qalır
}
```

### Həll 3 — @Lazy annotation

```java
@Service
public class ServiceA {
    private final ServiceB serviceB;

    // @Lazy — ServiceB proxy yaradır, real bean lazım olanda yaranır
    public ServiceA(@Lazy ServiceB serviceB) {
        this.serviceB = serviceB;
    }
}

@Service
public class ServiceB {
    private final ServiceA serviceA;

    public ServiceB(ServiceA serviceA) {
        this.serviceA = serviceA;
    }
}
```

### Həll 4 — ApplicationContext ilə (son çarə)

```java
@Service
public class ServiceA implements ApplicationContextAware {

    private ApplicationContext context;

    // ServiceB-ni lazy şəkildə alırıq
    private ServiceB getServiceB() {
        return context.getBean(ServiceB.class);
    }

    @Override
    public void setApplicationContext(ApplicationContext ctx) {
        this.context = ctx;
    }

    public void doWork() {
        getServiceB().someMethod(); // Lazım olanda alırıq
    }
}
```

---

## @Lazy ilə həll

```java
// @Lazy — Bean yalnız ilk istifadədə yaranır
@Service
public class ExpensiveService {

    public ExpensiveService() {
        // Ağır initialiation — database connection, cache loading, etc.
        System.out.println("ExpensiveService başladılır...");
    }
}

@Service
public class MainService {

    private final ExpensiveService expensiveService;

    // Startup-da yaranmır, ilk çağırışda yaranır
    public MainService(@Lazy ExpensiveService expensiveService) {
        this.expensiveService = expensiveService;
    }

    public void doWork() {
        // Bu metod çağırılanda ExpensiveService yaranır
        expensiveService.process();
    }
}

// ObjectProvider — daha çevik lazy injection
@Service
public class FlexibleService {

    // ObjectProvider — lazy + optional injection
    private final ObjectProvider<ExpensiveService> expensiveServiceProvider;

    public FlexibleService(ObjectProvider<ExpensiveService> expensiveServiceProvider) {
        this.expensiveServiceProvider = expensiveServiceProvider;
    }

    public void conditionalWork(boolean needsExpensive) {
        if (needsExpensive) {
            // Yalnız lazım olanda alınır
            ExpensiveService service = expensiveServiceProvider.getObject();
            service.process();
        }
    }

    public void safeWork() {
        // Bean yoxdursa null qaytarır (NoSuchBeanDefinitionException atmır)
        ExpensiveService service = expensiveServiceProvider.getIfAvailable();
        if (service != null) {
            service.process();
        }
    }
}
```

---

## Praktiki nümunə: E-commerce sistemi

```java
// Domain model
public record Product(Long id, String name, BigDecimal price) {}
public record Order(Long id, List<Product> products, BigDecimal total) {}

// Repository layer
public interface ProductRepository extends JpaRepository<Product, Long> {}
public interface OrderRepository extends JpaRepository<Order, Long> {}

// Service layer — constructor injection ilə
@Service
@RequiredArgsConstructor
@Slf4j
public class OrderService {

    private final OrderRepository orderRepository;
    private final ProductRepository productRepository;
    private final PaymentGateway paymentGateway;
    private final InventoryService inventoryService;

    @Transactional
    public Order placeOrder(List<Long> productIds, String customerId) {
        // Məhsulları tap
        List<Product> products = productRepository.findAllById(productIds);

        if (products.size() != productIds.size()) {
            throw new ProductNotFoundException("Bəzi məhsullar tapılmadı");
        }

        // Stok yoxla
        inventoryService.checkAvailability(products);

        // Cəmi hesabla
        BigDecimal total = products.stream()
            .map(Product::price)
            .reduce(BigDecimal.ZERO, BigDecimal::add);

        // Sifariş yarat
        Order order = new Order(null, products, total);
        Order savedOrder = orderRepository.save(order);

        // Ödəniş et
        paymentGateway.charge(customerId, total);

        log.info("Sifariş yaradıldı: {}, Məbləğ: {}", savedOrder.id(), total);
        return savedOrder;
    }
}

// PaymentGateway — çoxlu implementation
public interface PaymentGateway {
    void charge(String customerId, BigDecimal amount);
}

@Service("stripeGateway")
@Primary
public class StripePaymentGateway implements PaymentGateway {
    @Override
    public void charge(String customerId, BigDecimal amount) {
        // Stripe API çağırışı
        log.info("Stripe: {} üçün {} ödəniş alındı", customerId, amount);
    }
}

@Service("paypalGateway")
public class PayPalPaymentGateway implements PaymentGateway {
    @Override
    public void charge(String customerId, BigDecimal amount) {
        // PayPal API çağırışı
        log.info("PayPal: {} üçün {} ödəniş alındı", customerId, amount);
    }
}

// OrderService @Primary olan StripePaymentGateway alacaq
// PayPal istifadə etmək üçün:
@Service
@RequiredArgsConstructor
public class PayPalOrderService {

    @Qualifier("paypalGateway")
    private final PaymentGateway paymentGateway; // PayPal seçilir
}
```

---

## İntervyu Sualları

**S: Constructor injection niyə tövsiyə olunur?**
C: 1) Immutability — final sahələr, 2) Null-safety — constructor-da yoxlama mümkün, 3) Testability — Spring olmadan mock inject etmək olar, 4) Fail-fast — asılılıq çatışmırsa startup-da xəta verir, 5) Hidden dependencies yoxdur — sinif nəyə ehtiyacı olduğunu açıq göstərir.

**S: Field injection-u niyə tövsiyə etmirlər?**
C: 1) final işarətləmək olmur — immutable deyil, 2) Test üçün reflection lazımdır, 3) NullPointerException riski, 4) Spring-dən asılılıq artır — "hidden coupling", 5) Circular dependency-ni gizlədir.

**S: @Primary vs @Qualifier fərqi nədir?**
C: @Primary — default bean seçimi, başqaları @Qualifier göstərməsə bu seçilir. @Qualifier — konkret bean adını/annotasiyasını göstərir. @Qualifier @Primary-i üstələyir.

**S: Circular dependency nədir və necə həll etmək olar?**
C: İki bean bir-birini inject etməyə çalışanda baş verir. Həlllər: 1) Dizaynı yenidən düşünmək (ən yaxşı), 2) Setter injection istifadə etmək, 3) @Lazy annotation, 4) ObjectProvider. Spring Boot 2.6+ default olaraq circular dependency-ni qadağan edir.

**S: @Autowired required=false nə edir?**
C: Bean tapılmasa xəta atmır, null qalır. Optional asılılıqlar üçün istifadə olunur. Müasir yanaşma — `Optional<Service>` inject etmək və ya `ObjectProvider<Service>` istifadə etmək.

**S: Birdən çox eyni tipin bean-ı inject etmək necə?**
C: `List<Service>` inject etmək — bütün implementasiyaları alır. `Map<String, Service>` — bean adı ilə map. `@Order` annotasiyası ilə sıranı idarə etmək olar.
