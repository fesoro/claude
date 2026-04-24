# 012 — Spring Bean Scopes
**Səviyyə:** Orta


## Mündəricat
1. [Bean Scope nədir?](#bean-scope-nedir)
2. [Singleton Scope (default)](#singleton-scope)
3. [Prototype Scope](#prototype-scope)
4. [Request Scope](#request-scope)
5. [Session Scope](#session-scope)
6. [Application Scope](#application-scope)
7. [WebSocket Scope](#websocket-scope)
8. [Prototype Bean in Singleton Bean problemi](#prototype-in-singleton)
9. [Lookup Method Injection](#lookup-method-injection)
10. [ObjectProvider həlli](#objectprovider)
11. [İntervyu Sualları](#intervyu-suallar)

---

## Bean Scope nədir?

Bean scope — Spring container-in bean instance-larını necə yaratdığını və idarə etdiyini müəyyən edir. Scope bean-in ömrünü (lifecycle) təyin edir.

```java
// Scope annotasiyası ilə təyin etmək
import org.springframework.context.annotation.Scope;
import org.springframework.stereotype.Component;

@Component
@Scope("prototype") // Scope adını string ilə
public class MyComponent {}

// Tip-güvənli sabitlərlə
import org.springframework.beans.factory.config.ConfigurableBeanFactory;
import org.springframework.web.context.WebApplicationContext;

@Component
@Scope(ConfigurableBeanFactory.SCOPE_SINGLETON)   // "singleton"
@Scope(ConfigurableBeanFactory.SCOPE_PROTOTYPE)   // "prototype"
@Scope(WebApplicationContext.SCOPE_REQUEST)       // "request"
@Scope(WebApplicationContext.SCOPE_SESSION)       // "session"
@Scope(WebApplicationContext.SCOPE_APPLICATION)   // "application"
```

---

## Singleton Scope (default)

Container boyunca yalnız **bir instance** yaradılır. Bütün inject edilmə yerlərindən eyni obyektə müraciət edilir.

```java
import org.springframework.stereotype.Service;

// @Scope yazılmadıqda avtomatik singleton
@Service
public class UserService {

    // Bu sinif yalnız bir dəfə yaradılır
    // Container boyunca eyni instance paylaşılır

    private int requestCount = 0; // YANLIŞ: Thread-safe deyil!

    public User findUser(Long id) {
        requestCount++; // Race condition var — concurrent sorğularda problem yaranır
        return userRepository.findById(id).orElseThrow();
    }
}

// DOĞRU: Singleton bean-lər STATELESS olmalıdır
@Service
public class UserService {

    private final UserRepository userRepository; // Dependency inject edilir

    public UserService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    // State saxlamırıq — thread-safe
    public User findUser(Long id) {
        return userRepository.findById(id).orElseThrow();
    }
}
```

### Singleton Bean-in xüsusiyyətləri

```java
@SpringBootApplication
public class SingletonDemo {

    public static void main(String[] args) {
        ApplicationContext ctx = SpringApplication.run(SingletonDemo.class, args);

        // Eyni sinfi iki dəfə alırıq
        UserService s1 = ctx.getBean(UserService.class);
        UserService s2 = ctx.getBean(UserService.class);

        // Eyni instance-dır!
        System.out.println(s1 == s2); // true
        System.out.println(s1.hashCode() == s2.hashCode()); // true
    }
}
```

### Singleton — Thread Safety

```java
// Singleton bean-lər default olaraq thread-safe DEYİL
// Müştərək mutable state saxlamaq YANLIŞ-dır

// YANLIŞ: Mutable state olan singleton
@Service
public class BadCounterService {
    private int count = 0; // Paylaşılan mutable state — race condition!

    public int increment() {
        return ++count; // Thread-safe deyil
    }
}

// DOĞRU 1: Atomic istifadə et
@Service
public class GoodCounterService {
    private final AtomicInteger count = new AtomicInteger(0);

    public int increment() {
        return count.incrementAndGet(); // Thread-safe
    }
}

// DOĞRU 2: Stateless et
@Service
public class StatelessService {
    // Heç bir instance state yoxdur
    public int calculate(int a, int b) {
        return a + b; // Yalnız local variablelər
    }
}
```

---

## Prototype Scope

Hər `getBean()` çağırışında (hər inject edilmədə) **yeni instance** yaradılır.

```java
import org.springframework.context.annotation.Scope;
import org.springframework.beans.factory.config.ConfigurableBeanFactory;

@Component
@Scope(ConfigurableBeanFactory.SCOPE_PROTOTYPE)
public class ReportGenerator {

    // Hər inject edilmədə yeni instance yaradılır
    // Mutable state saxlamaq MÜMKÜNDÜR — hər istifadəçi öz instance-ına malik olur

    private final List<String> reportLines = new ArrayList<>();
    private String reportTitle;

    public void setTitle(String title) {
        this.reportTitle = title;
    }

    public void addLine(String line) {
        reportLines.add(line);
    }

    public String generate() {
        StringBuilder sb = new StringBuilder();
        sb.append("=== ").append(reportTitle).append(" ===\n");
        reportLines.forEach(line -> sb.append(line).append("\n"));
        return sb.toString();
    }
}

// İstifadə
@Service
public class ReportService {

    // YANLIŞ: Prototype-u birbaşa inject etmək — yalnız BİR dəfə inject edilir!
    @Autowired
    private ReportGenerator reportGenerator; // Singleton kimi davranır!

    // DOĞRU: ApplicationContext vasitəsilə hər dəfə yeni instance almaq
    private final ApplicationContext applicationContext;

    public ReportService(ApplicationContext applicationContext) {
        this.applicationContext = applicationContext;
    }

    public String createReport(String title, List<String> lines) {
        // Hər çağırışda YENİ instance yaradılır
        ReportGenerator generator = applicationContext.getBean(ReportGenerator.class);
        generator.setTitle(title);
        lines.forEach(generator::addLine);
        return generator.generate();
    }
}
```

### Prototype-un lifecycle fərqi

```java
// VACIB: Spring prototype bean-lərin destroy lifecycle-ını idarə ETMIR!
// @PreDestroy çağırılmır — developer özü idarə etməlidir

@Component
@Scope(ConfigurableBeanFactory.SCOPE_PROTOTYPE)
public class ResourceHolder {

    private Connection dbConnection;

    @PostConstruct
    public void init() {
        // Bu çağırılır
        this.dbConnection = createConnection();
    }

    @PreDestroy
    public void cleanup() {
        // Bu ÇAĞIRILMIR — prototype-un destroy lifecycle-ı Spring tərəfindən idarə edilmir!
        // Resource leak yarana bilər!
        dbConnection.close();
    }

    // Developer özü cleanup etməlidir
    public void close() {
        if (dbConnection != null) {
            dbConnection.close();
        }
    }
}
```

---

## Request Scope

Hər HTTP sorğusu üçün **bir instance** yaradılır. Sorğu bitdikdə bean məhv edilir.

```java
import org.springframework.web.context.annotation.RequestScope;

// Hər HTTP request üçün yeni instance
@Component
@RequestScope
// Bu annotasiya @Scope(WebApplicationContext.SCOPE_REQUEST) ilə ekvivalentdir
public class RequestContext {

    private String requestId;
    private String userId;
    private long startTime;

    @PostConstruct
    public void init() {
        // Request başladıqda çağırılır
        this.requestId = UUID.randomUUID().toString();
        this.startTime = System.currentTimeMillis();
    }

    public String getRequestId() { return requestId; }
    public void setUserId(String userId) { this.userId = userId; }
    public String getUserId() { return userId; }

    public long getElapsedTime() {
        return System.currentTimeMillis() - startTime;
    }
}

// Controller-də istifadə
@RestController
@RequestMapping("/api")
public class ApiController {

    private final RequestContext requestContext;
    private final UserService userService;

    public ApiController(RequestContext requestContext, UserService userService) {
        this.requestContext = requestContext;
        this.userService = userService;
    }

    @GetMapping("/data")
    public ResponseEntity<Data> getData(@RequestHeader("X-User-Id") String userId) {
        requestContext.setUserId(userId); // Request-spesifik context
        Data data = userService.getData(userId);
        // Log: requestContext.getRequestId() hər request üçün fərqlidir
        return ResponseEntity.ok(data);
    }
}
```

---

## Session Scope

Hər HTTP sessiyası üçün **bir instance** yaradılır. Sessiya bitdikdə bean məhv edilir.

```java
import org.springframework.web.context.annotation.SessionScope;
import java.io.Serializable;

// Hər HTTP session üçün bir instance
// Serializable olmalıdır — session clustering üçün
@Component
@SessionScope
public class ShoppingCart implements Serializable {

    private static final long serialVersionUID = 1L;

    // Session boyunca qalan state
    private final List<CartItem> items = new ArrayList<>();
    private String userId;

    public void addItem(CartItem item) {
        items.add(item);
    }

    public void removeItem(Long productId) {
        items.removeIf(item -> item.getProductId().equals(productId));
    }

    public List<CartItem> getItems() {
        return Collections.unmodifiableList(items);
    }

    public BigDecimal getTotalPrice() {
        return items.stream()
            .map(item -> item.getPrice().multiply(BigDecimal.valueOf(item.getQuantity())))
            .reduce(BigDecimal.ZERO, BigDecimal::add);
    }

    public void clear() {
        items.clear();
    }
}

// Controller-də istifadə
@RestController
@RequestMapping("/api/cart")
public class CartController {

    private final ShoppingCart shoppingCart; // Session-a aid — hər user öz cart-ına sahib

    public CartController(ShoppingCart shoppingCart) {
        this.shoppingCart = shoppingCart;
    }

    @PostMapping("/items")
    public ResponseEntity<Void> addToCart(@RequestBody CartItem item) {
        shoppingCart.addItem(item);
        return ResponseEntity.ok().build();
    }

    @GetMapping
    public ResponseEntity<CartSummary> getCart() {
        return ResponseEntity.ok(new CartSummary(
            shoppingCart.getItems(),
            shoppingCart.getTotalPrice()
        ));
    }
}
```

---

## Application Scope

`ServletContext` boyunca **bir instance** — singleton-a bənzər, lakin `WebApplicationContext`-ə bağlıdır.

```java
import org.springframework.web.context.annotation.ApplicationScope;

// Bütün istifadəçilər tərəfindən paylaşılan application-wide state
@Component
@ApplicationScope
public class ApplicationMetrics {

    // Bütün istifadəçilər üçün ortaq sayğac
    private final AtomicLong totalRequests = new AtomicLong(0);
    private final AtomicLong totalErrors = new AtomicLong(0);
    private final Map<String, AtomicLong> endpointHits =
        new ConcurrentHashMap<>();

    public void recordRequest(String endpoint) {
        totalRequests.incrementAndGet();
        endpointHits.computeIfAbsent(endpoint, k -> new AtomicLong(0))
            .incrementAndGet();
    }

    public void recordError() {
        totalErrors.incrementAndGet();
    }

    public Map<String, Object> getStats() {
        return Map.of(
            "totalRequests", totalRequests.get(),
            "totalErrors", totalErrors.get(),
            "endpointHits", new HashMap<>(endpointHits)
        );
    }
}
```

### Singleton vs Application Scope fərqi

```java
// Singleton — ApplicationContext-ə bağlı (test-də reload olunar)
@Component
// @Scope("singleton") — default
public class SingletonConfig {}

// Application Scope — ServletContext-ə bağlı
// Web application context restart etdikdə sıfırlanır
// Integration test-lərdə context reload olunanda sıfırlanmır
@Component
@ApplicationScope
public class AppScopeConfig {}
```

---

## WebSocket Scope

Hər WebSocket sessiyası üçün **bir instance** yaradılır.

```java
import org.springframework.web.socket.config.annotation.EnableWebSocket;

// WebSocket scope üçün konfiqurasiya
@Configuration
@EnableWebSocket
public class WebSocketConfig implements WebSocketConfigurer {

    @Override
    public void registerWebSocketHandlers(WebSocketHandlerRegistry registry) {
        registry.addHandler(webSocketHandler(), "/ws");
    }

    @Bean
    public WebSocketHandler webSocketHandler() {
        return new MyWebSocketHandler();
    }
}

// WebSocket session-ına bağlı bean
@Component
@Scope("websocket") // WebSocket scope
public class WebSocketSession {

    private String sessionId;
    private String userId;
    private final List<String> messageHistory = new ArrayList<>();

    @PostConstruct
    public void init() {
        this.sessionId = UUID.randomUUID().toString();
    }

    public void addMessage(String message) {
        messageHistory.add(message);
    }

    // Getters/setters
}
```

---

## Prototype Bean in Singleton Bean problemi

Bu ən çox rast gəlinən scope problemidir. Singleton bean-ə prototype bean inject ediləndə, prototype **yalnız bir dəfə** inject edilir — sonra singleton kimi davranır.

```java
@Component
@Scope(ConfigurableBeanFactory.SCOPE_PROTOTYPE)
public class PrototypeBean {
    private final String id = UUID.randomUUID().toString();

    public String getId() { return id; }
}

// PROBLEM: Singleton içindəki prototype bir dəfə inject edilir
@Service
public class SingletonServiceWithProblem {

    @Autowired
    private PrototypeBean prototypeBean; // YANLIŞ — bu yalnız bir dəfə inject edilir!

    public String getPrototypeId() {
        // Hər çağırışda eyni id qaytarılır — prototype kimi davranmır!
        return prototypeBean.getId();
    }
}
```

---

## Lookup Method Injection

Spring `@Lookup` annotasiyası ilə hər çağırışda yeni prototype instance qaytarır.

```java
// Lookup Method Injection həlli
@Service
public abstract class SingletonServiceWithLookup {

    // Spring bu abstract metodu override edir
    // Hər çağırışda yeni PrototypeBean qaytarır
    @Lookup
    public abstract PrototypeBean getPrototypeBean();

    public String getPrototypeId() {
        // Hər çağırışda YENİ instance qaytarılır
        return getPrototypeBean().getId();
    }
}

// Non-abstract sinif ilə @Lookup
@Service
public class SingletonServiceWithLookupConcrete {

    // CGLIB subclass yaradaraq bu metodu override edir
    @Lookup
    public PrototypeBean getPrototypeBean() {
        // Bu gövdə heç vaxt icra edilmir — Spring override edir
        return null;
    }

    public String getPrototypeId() {
        return getPrototypeBean().getId(); // Hər dəfə yeni instance
    }
}
```

---

## ObjectProvider həlli

`ObjectProvider` — prototype bean-lərə lazy, on-demand giriş üçün ən müasir həlldir.

```java
import org.springframework.beans.factory.ObjectProvider;

@Service
public class SingletonServiceWithObjectProvider {

    // ObjectProvider — lazy bean resolution
    private final ObjectProvider<PrototypeBean> prototypeBeanProvider;

    public SingletonServiceWithObjectProvider(
            ObjectProvider<PrototypeBean> prototypeBeanProvider) {
        this.prototypeBeanProvider = prototypeBeanProvider;
    }

    public String getPrototypeId() {
        // getObject() hər çağırışda YENİ prototype instance yaradır
        PrototypeBean prototypeBean = prototypeBeanProvider.getObject();
        return prototypeBean.getId();
    }

    public String getPrototypeIdWithArgs() {
        // Constructor arqumentləri ilə prototype yaratmaq
        PrototypeBean bean = prototypeBeanProvider.getObject("arg1", 42);
        return bean.getId();
    }
}

// ObjectProvider-in digər metodları
@Service
public class AdvancedObjectProviderDemo {

    private final ObjectProvider<OptionalService> optionalServiceProvider;

    public AdvancedObjectProviderDemo(
            ObjectProvider<OptionalService> optionalServiceProvider) {
        this.optionalServiceProvider = optionalServiceProvider;
    }

    public void demo() {
        // Bean mövcud deyilsə null qaytarır (exception atmır)
        OptionalService service = optionalServiceProvider.getIfAvailable();

        // Bean mövcud deyilsə default dəyər qaytarır
        OptionalService serviceOrDefault =
            optionalServiceProvider.getIfAvailable(() -> new DefaultOptionalService());

        // Bean mövcud deyilsə null qaytarır (üstünlüklü variantda)
        OptionalService serviceIfUnique = optionalServiceProvider.getIfUnique();

        // Stream olaraq bütün matching bean-lər
        optionalServiceProvider.stream().forEach(s -> s.doSomething());
    }
}
```

### Müqayisə — Həll yolları

```java
// Üsul 1: ApplicationContext.getBean() — istifadə oluna bilər amma anti-pattern
@Service
public class Solution1 {
    @Autowired
    private ApplicationContext ctx;

    public void process() {
        PrototypeBean bean = ctx.getBean(PrototypeBean.class); // Hər dəfə yeni
    }
}

// Üsul 2: @Lookup — abstract sinif lazımdır, CGLIB proxy lazımdır
@Service
public abstract class Solution2 {
    @Lookup
    protected abstract PrototypeBean createBean();

    public void process() {
        PrototypeBean bean = createBean(); // Hər dəfə yeni
    }
}

// Üsul 3: ObjectProvider — tövsiyə olunan müasir həll
@Service
public class Solution3 {
    private final ObjectProvider<PrototypeBean> provider;

    public Solution3(ObjectProvider<PrototypeBean> provider) {
        this.provider = provider;
    }

    public void process() {
        PrototypeBean bean = provider.getObject(); // Hər dəfə yeni
    }
}

// Üsul 4: javax.inject.Provider (JSR-330)
@Service
public class Solution4 {
    @Autowired
    private Provider<PrototypeBean> provider; // javax.inject.Provider

    public void process() {
        PrototypeBean bean = provider.get(); // Hər dəfə yeni
    }
}
```

---

## İntervyu Sualları

**S: Spring-in default bean scope-u nədir?**
C: Singleton. Container başladıqda bütün singleton bean-lər yaradılır (eager initialization) və container boyunca yalnız bir instance mövcud olur.

**S: Singleton ilə Application scope arasındakı fərq nədir?**
C: Singleton `ApplicationContext`-ə bağlıdır — context restart etdikdə yeni instance yaradılır. Application scope `ServletContext`-ə bağlıdır — yalnız web environment-də mövcuddur. Test-lərdə context reload olunanda singleton sıfırlanır, application scope isə `ServletContext` restart olmadıqca qalır.

**S: Prototype bean-in @PreDestroy metodu çağırılırmı?**
C: Xeyr. Spring prototype bean-lərin destroy lifecycle-ını idarə etmir. `@PostConstruct` çağırılır, lakin `@PreDestroy` çağırılmır. Developer özü cleanup idarə etməlidir.

**S: Singleton bean-ə prototype bean inject etdikdə nə baş verir?**
C: Prototype bean yalnız bir dəfə inject edilir — singleton yaradılanda. Sonrakı çağırışlarda həmişə eyni instance istifadə edilir. Bunun həlli üçün `@Lookup`, `ObjectProvider` və ya `ApplicationContext.getBean()` istifadə edilir.

**S: ObjectProvider nədir və nə vaxt istifadə edilir?**
C: `ObjectProvider<T>` — bean-ə lazy, on-demand giriş təmin edir. Prototype bean-ləri singleton-dan düzgün istifadə etmək, optional dependency-ləri idarə etmək üçün istifadə edilir. `getObject()`, `getIfAvailable()`, `getIfUnique()` metodları var.

**S: Request scope olan bean necə singleton bean-ə inject edilir?**
C: Birbaşa inject etmək olmaz. `@Scope(proxyMode = ScopedProxyMode.TARGET_CLASS)` ilə scoped proxy yaradılır. Singleton bean proxye müraciət edir, proxy isə request-specific bean-ə yönləndirir.
