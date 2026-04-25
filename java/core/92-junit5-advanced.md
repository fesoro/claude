# 92 — JUnit 5 Advanced — Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [@Nested — İç içə testlər](#nested--iç-içə-testlər)
2. [JUnit 5 Extension Model](#junit-5-extension-model)
3. [Custom Extension yaratmaq](#custom-extension-yaratmaq)
4. [@TempDir və @RegisterExtension](#tempdir-və-registerextension)
5. [Test Ordering](#test-ordering)
6. [Conditional Test Execution](#conditional-test-execution)
7. [Test Interfaces](#test-interfaces)
8. [İntervyu Sualları](#intervyu-sualları)

---

## @Nested — İç içə testlər

`@Nested` — related testləri qruplaşdırmaq üçün. Bir sinif içərisində məntiqi bölmələr yaradır.

```java
@DisplayName("OrderService testləri")
class OrderServiceTest {

    private OrderService orderService;
    private OrderRepository mockRepository;

    @BeforeEach
    void setUp() {
        mockRepository = mock(OrderRepository.class);
        orderService = new OrderService(mockRepository);
    }

    // ─── Sifariş yaratma ──────────────────────────────
    @Nested
    @DisplayName("createOrder()")
    class CreateOrderTests {

        @Test
        @DisplayName("Etibarlı request ilə sifariş yaradılır")
        void shouldCreateOrderWithValidRequest() {
            OrderRequest request = validOrderRequest();
            when(mockRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));

            Order result = orderService.createOrder(request);

            assertNotNull(result.getId());
            assertEquals(OrderStatus.PENDING, result.getStatus());
        }

        @Test
        @DisplayName("Null request exception atır")
        void shouldThrowWhenRequestIsNull() {
            assertThrows(IllegalArgumentException.class,
                () -> orderService.createOrder(null));
        }

        @Test
        @DisplayName("Boş items ilə exception atır")
        void shouldThrowWhenItemsAreEmpty() {
            OrderRequest request = new OrderRequest("customer-1", List.of());
            assertThrows(IllegalArgumentException.class,
                () -> orderService.createOrder(request));
        }

        // İç içə @Nested — daha dərin qruplaşma
        @Nested
        @DisplayName("Qiymət hesablaması")
        class PriceCalculationTests {

            @Test
            @DisplayName("Discount tətbiq olunur")
            void shouldApplyDiscount() {
                // ...
            }

            @Test
            @DisplayName("Tax əlavə edilir")
            void shouldAddTax() {
                // ...
            }
        }
    }

    // ─── Sifariş tapma ────────────────────────────────
    @Nested
    @DisplayName("findById()")
    class FindByIdTests {

        @Test
        @DisplayName("Mövcud ID ilə tapılır")
        void shouldFindExistingOrder() {
            Order order = buildOrder(1L);
            when(mockRepository.findById(1L)).thenReturn(Optional.of(order));

            Optional<Order> result = orderService.findById(1L);

            assertTrue(result.isPresent());
            assertEquals(1L, result.get().getId());
        }

        @Test
        @DisplayName("Mövcud olmayan ID — empty Optional")
        void shouldReturnEmptyForNonExistingOrder() {
            when(mockRepository.findById(99L)).thenReturn(Optional.empty());

            Optional<Order> result = orderService.findById(99L);

            assertTrue(result.isEmpty());
        }
    }

    // ─── Status dəyişmə ───────────────────────────────
    @Nested
    @DisplayName("cancelOrder()")
    class CancelOrderTests {

        @Test
        @DisplayName("PENDING sifarişi ləğv edilə bilər")
        void shouldCancelPendingOrder() {
            Order order = buildOrder(OrderStatus.PENDING);
            when(mockRepository.findById(1L)).thenReturn(Optional.of(order));
            when(mockRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));

            Order cancelled = orderService.cancelOrder(1L);

            assertEquals(OrderStatus.CANCELLED, cancelled.getStatus());
        }

        @Test
        @DisplayName("SHIPPED sifarişi ləğv edilə bilməz")
        void shouldThrowWhenCancellingShippedOrder() {
            Order order = buildOrder(OrderStatus.SHIPPED);
            when(mockRepository.findById(1L)).thenReturn(Optional.of(order));

            assertThrows(OrderCannotBeCancelledException.class,
                () -> orderService.cancelOrder(1L));
        }
    }
}
```

---

## JUnit 5 Extension Model

JUnit 5 extension modeli — JUnit 4-ün `@Rule` / `@RunWith` əvəzinə. `@ExtendWith` ilə istifadə olunur.

```java
// Hazır extension-lar:

// Mockito Extension — mock-ları avtomatik inject edir
@ExtendWith(MockitoExtension.class)
class OrderServiceTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private EventPublisher eventPublisher;

    @InjectMocks
    private OrderService orderService;

    @Test
    void shouldCreateOrder() {
        // @Mock avtomatik inject olunub, setUp() lazım deyil
        when(orderRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));
        Order order = orderService.createOrder(validRequest());
        assertNotNull(order);
    }
}

// Spring Extension — Spring context ilə
@ExtendWith(SpringExtension.class)
@ContextConfiguration(classes = AppConfig.class)
class SpringIntegrationTest {

    @Autowired
    private OrderService orderService;

    @Test
    void shouldWorkWithRealBeans() {
        // ...
    }
}

// Bir neçə extension eyni anda
@ExtendWith({MockitoExtension.class, TimingExtension.class})
class MultiExtensionTest {
    // ...
}
```

---

## Custom Extension yaratmaq

```java
// ─── Timing Extension ────────────────────────────────
// Hər testin icra müddətini ölçür

public class TimingExtension
        implements BeforeEachCallback, AfterEachCallback {

    private static final String START_TIME = "startTime";
    private static final Logger log = LoggerFactory.getLogger(TimingExtension.class);

    @Override
    public void beforeEach(ExtensionContext context) {
        // Store start time in test context
        context.getStore(ExtensionContext.Namespace.GLOBAL)
            .put(START_TIME, System.currentTimeMillis());
    }

    @Override
    public void afterEach(ExtensionContext context) {
        long startTime = context.getStore(ExtensionContext.Namespace.GLOBAL)
            .remove(START_TIME, Long.class);
        long duration = System.currentTimeMillis() - startTime;

        log.info("[{}] {} ms",
            context.getDisplayName(),
            duration);
    }
}

// ─── Database Cleaner Extension ──────────────────────
// Hər testdən sonra DB-ni təmizləyir

public class DatabaseCleanerExtension
        implements BeforeEachCallback, AfterEachCallback {

    @Override
    public void beforeEach(ExtensionContext context) throws Exception {
        getDatabase(context).ifPresent(DatabaseCleaner::setUp);
    }

    @Override
    public void afterEach(ExtensionContext context) throws Exception {
        getDatabase(context).ifPresent(DatabaseCleaner::cleanAll);
    }

    private Optional<DatabaseCleaner> getDatabase(ExtensionContext context) {
        return context.getTestInstance()
            .filter(instance -> instance instanceof DatabaseTest)
            .map(instance -> ((DatabaseTest) instance).getDatabaseCleaner());
    }
}

// ─── Parameter Resolver Extension ────────────────────
// Test metoduna parametr inject edir

public class RandomUserExtension implements ParameterResolver {

    @Override
    public boolean supportsParameter(ParameterContext paramContext,
                                      ExtensionContext extContext) {
        return paramContext.getParameter().getType() == User.class
            && paramContext.isAnnotated(RandomUser.class);
    }

    @Override
    public Object resolveParameter(ParameterContext paramContext,
                                    ExtensionContext extContext) {
        return User.builder()
            .id(UUID.randomUUID())
            .name("Test User " + System.currentTimeMillis())
            .email("test" + System.currentTimeMillis() + "@example.com")
            .build();
    }
}

// Custom annotation
@Target(ElementType.PARAMETER)
@Retention(RetentionPolicy.RUNTIME)
public @interface RandomUser {}

// İstifadə:
@ExtendWith(RandomUserExtension.class)
class UserServiceTest {

    @Test
    void shouldProcessUser(@RandomUser User user) {
        // user avtomatik inject olunub
        assertNotNull(user.getId());
        assertNotNull(user.getEmail());
    }
}

// ─── Exception Interceptor Extension ─────────────────
// Gözlənilməz exception-ları handle edir

public class ExceptionLoggingExtension
        implements TestExecutionExceptionHandler {

    private static final Logger log = LoggerFactory.getLogger(ExceptionLoggingExtension.class);

    @Override
    public void handleTestExecutionException(ExtensionContext context,
                                              Throwable throwable) throws Throwable {
        log.error("Test uğursuz oldu: {} — {}",
            context.getDisplayName(),
            throwable.getMessage());

        // Exception-ı yenidən at (test fail olsun)
        throw throwable;
    }
}
```

---

## @TempDir və @RegisterExtension

```java
// ─── @TempDir ─────────────────────────────────────────
// Test üçün müvəqqəti qovluq — test bitdikdə silinir

class FileServiceTest {

    @TempDir
    Path tempDir; // JUnit avtomatik yaradıb siləcək

    @Test
    void shouldWriteAndReadFile() throws IOException {
        FileService fileService = new FileService();
        Path testFile = tempDir.resolve("test.txt");

        fileService.writeContent(testFile, "Salam Dünya");

        String content = Files.readString(testFile);
        assertEquals("Salam Dünya", content);
    }

    @Test
    void shouldListFiles(@TempDir Path dir) throws IOException { // Parametrdə də istifadə
        Files.createFile(dir.resolve("file1.txt"));
        Files.createFile(dir.resolve("file2.txt"));
        Files.createFile(dir.resolve("file3.txt"));

        List<Path> files = fileService.listFiles(dir);

        assertEquals(3, files.size());
    }

    // Static @TempDir — bütün testlər eyni qovluğu paylaşır
    @TempDir
    static Path sharedDir;

    @Test
    void test1() {
        // sharedDir istifadə edir
    }

    @Test
    void test2() {
        // eyni sharedDir — testlər arasında data paylaşıla bilər
    }
}

// ─── @RegisterExtension ───────────────────────────────
// Extension-ı proqramatik olaraq qeyd et (instance sahəsi kimi)

class OrderServiceIntegrationTest {

    // Proqramatik extensi — konfigurasi lazım olduqda
    @RegisterExtension
    static WireMockExtension wireMock = WireMockExtension.newInstance()
        .options(wireMockConfig().port(8089))
        .build();

    @RegisterExtension
    DatabaseExtension database = new DatabaseExtension("jdbc:h2:mem:test");

    @Test
    void shouldCallExternalService() {
        // WireMock istub
        wireMock.stubFor(get("/api/products/1")
            .willReturn(aResponse()
                .withBody("{\"id\":1,\"name\":\"Laptop\"}")
                .withHeader("Content-Type", "application/json")));

        // Test
        Order order = orderService.createWithProductCheck(1L);
        assertNotNull(order);

        // WireMock yoxlama
        wireMock.verify(getRequestedFor(urlEqualTo("/api/products/1")));
    }
}
```

---

## Test Ordering

```java
// Default: JUnit test sırası deterministik deyil
// @TestMethodOrder ilə sıralamaq olar

// ─── Alfabetik ────────────────────────────────────────
@TestMethodOrder(MethodOrderer.MethodName.class)
class AlphabeticOrderTest {

    @Test void aFirstTest() { }
    @Test void bSecondTest() { }
    @Test void cThirdTest() { }
    // a → b → c sırası ilə
}

// ─── @Order annotasiyası ──────────────────────────────
@TestMethodOrder(MethodOrderer.OrderAnnotation.class)
class OrderedTest {

    @Test
    @Order(1)
    void firstTest() { }

    @Test
    @Order(2)
    void secondTest() { }

    @Test
    @Order(3)
    void thirdTest() { }

    @Test
    // @Order yoxdur → sona keçir
    void unorderedTest() { }
}

// ─── Integration test sıralaması (E2E) ───────────────
@TestMethodOrder(MethodOrderer.OrderAnnotation.class)
@TestInstance(TestInstance.Lifecycle.PER_CLASS) // State paylaşmaq üçün
class OrderWorkflowIntegrationTest {

    private Long orderId;

    @Test
    @Order(1)
    @DisplayName("1. Sifariş yarat")
    void shouldCreateOrder() {
        OrderResponse response = createOrder(validRequest());
        assertNotNull(response.orderId());
        this.orderId = response.orderId(); // Növbəti test üçün state saxla
    }

    @Test
    @Order(2)
    @DisplayName("2. Sifarişi təsdiq et")
    void shouldConfirmOrder() {
        assertNotNull(orderId, "Order ID lazımdır");
        OrderResponse response = confirmOrder(orderId);
        assertEquals("CONFIRMED", response.status());
    }

    @Test
    @Order(3)
    @DisplayName("3. Sifarişi göndər")
    void shouldShipOrder() {
        assertNotNull(orderId, "Order ID lazımdır");
        OrderResponse response = shipOrder(orderId);
        assertEquals("SHIPPED", response.status());
    }
}
```

---

## Conditional Test Execution

```java
class ConditionalTests {

    // ─── OS şərtləri ──────────────────────────────────
    @Test
    @EnabledOnOs(OS.LINUX)
    void onlyOnLinux() {
        // Linux-da işləyir
    }

    @Test
    @EnabledOnOs({OS.WINDOWS, OS.MAC})
    void onWindowsOrMac() {
        // Windows ya Mac-da işləyir
    }

    @Test
    @DisabledOnOs(OS.WINDOWS)
    void notOnWindows() {
        // Windows-da işləmir
    }

    // ─── Java versiyası ───────────────────────────────
    @Test
    @EnabledOnJre(JRE.JAVA_21)
    void onlyOnJava21() {
        // Virtual threads test
        var thread = Thread.ofVirtual().start(() -> {});
        assertNotNull(thread);
    }

    @Test
    @EnabledForJreRange(min = JRE.JAVA_17, max = JRE.JAVA_21)
    void onJava17To21() {
        // ...
    }

    // ─── Environment variable ─────────────────────────
    @Test
    @EnabledIfEnvironmentVariable(named = "CI", matches = "true")
    void onlyInCiEnvironment() {
        // Yalnız CI=true olduqda
        integrationTestSuite.runAll();
    }

    @Test
    @DisabledIfEnvironmentVariable(named = "ENVIRONMENT", matches = "production")
    void notInProduction() {
        // Production-da disable
    }

    // ─── System property ──────────────────────────────
    @Test
    @EnabledIfSystemProperty(named = "test.mode", matches = "integration")
    void integrationTest() {
        // -Dtest.mode=integration ilə işləyir
    }

    // ─── Custom condition ─────────────────────────────
    @Test
    @EnabledIf("isFeatureFlagEnabled")
    void featureFlagTest() {
        // Aşağıdakı metodun nəticəsinə görə
    }

    boolean isFeatureFlagEnabled() {
        return System.getenv("FEATURE_NEW_ORDER_FLOW") != null;
    }

    // ─── Proqramatik condition ────────────────────────
    @Test
    void conditionalWithAssumption() {
        String dbUrl = System.getenv("TEST_DB_URL");
        assumeTrue(dbUrl != null, "TEST_DB_URL set olmalıdır");

        // DB test-i
    }
}
```

---

## Test Interfaces

```java
// ─── Shared test contract ─────────────────────────────
// Bir interfeys — birdən çox implementation üçün eyni testlər

interface RepositoryContractTest<T, ID> {

    T createEntity();
    ID getId(T entity);
    JpaRepository<T, ID> getRepository();

    @Test
    default void shouldSaveAndFindById() {
        T entity = createEntity();
        T saved = getRepository().save(entity);

        Optional<T> found = getRepository().findById(getId(saved));

        assertTrue(found.isPresent());
    }

    @Test
    default void shouldDeleteById() {
        T entity = createEntity();
        T saved = getRepository().save(entity);
        ID id = getId(saved);

        getRepository().deleteById(id);

        assertFalse(getRepository().existsById(id));
    }

    @Test
    default void shouldFindAll() {
        getRepository().save(createEntity());
        getRepository().save(createEntity());

        List<T> all = getRepository().findAll();

        assertFalse(all.isEmpty());
    }
}

// Order implementation
@DataJpaTest
class OrderRepositoryContractTest
        implements RepositoryContractTest<Order, Long> {

    @Autowired
    private OrderRepository orderRepository;

    @Override
    public Order createEntity() {
        return Order.builder()
            .customerId(UUID.randomUUID().toString())
            .status(OrderStatus.PENDING)
            .build();
    }

    @Override
    public Long getId(Order entity) {
        return entity.getId();
    }

    @Override
    public JpaRepository<Order, Long> getRepository() {
        return orderRepository;
    }
}

// Product implementation — eyni test-lər
@DataJpaTest
class ProductRepositoryContractTest
        implements RepositoryContractTest<Product, Long> {

    @Autowired
    private ProductRepository productRepository;

    @Override
    public Product createEntity() {
        return Product.builder()
            .name("Test Product " + System.currentTimeMillis())
            .price(new BigDecimal("99.99"))
            .build();
    }

    @Override
    public Long getId(Product entity) {
        return entity.getId();
    }

    @Override
    public JpaRepository<Product, Long> getRepository() {
        return productRepository;
    }
}

// ─── @TestInstance interfeysdə ────────────────────────
@TestInstance(TestInstance.Lifecycle.PER_CLASS)
interface DatabaseTest {

    @BeforeAll
    default void setUpDatabase() {
        // DB migrationsları çalışdır
    }

    @AfterAll
    default void tearDownDatabase() {
        // DB təmizlə
    }
}
```

---

## İntervyu Sualları

### 1. @Nested nə üçündür?
**Cavab:** Bir test sinfi içərisində məntiqi qruplar yaratmaq üçün. `@BeforeEach` iç içə sinifdə xarici sinifin `@BeforeEach`-ni miras alır. Bu, bir servisin müxtəlif metodlarını (createOrder, findById, cancelOrder) ayrı-ayrı `@Nested` siniflərdə qruplaşdırmağa imkan verir. Test output-u daha oxunaqlı olur — iyerarxik göstərilir.

### 2. JUnit 4 @Rule vs JUnit 5 Extension fərqi?
**Cavab:** JUnit 4-də `@Rule` (field/method level) məhduddur — yalnız test lifecycle-ın müəyyən nöqtələrinə hook edir. JUnit 5 Extension model daha güclüdür: `BeforeEachCallback`, `AfterEachCallback`, `ParameterResolver`, `TestExecutionExceptionHandler`, `TestInstanceFactory`, `TestWatcher` — hamısı implementasiya edilə bilər. Composition (bir neçə extension birlikdə) `@ExtendWith({A.class, B.class})` ilə sadədir.

### 3. @RegisterExtension vs @ExtendWith fərqi?
**Cavab:** `@ExtendWith` — annotasiya ilə, statik, konfigurasi mümkün deyil. `@RegisterExtension` — sahə kimi tanımlanır, proqramatik konfigurasi mümkündür (WireMock portu, DB URL, vb.). `@RegisterExtension static` → `@BeforeAll`/`@AfterAll` lifecycle. `@RegisterExtension` instance → `@BeforeEach`/`@AfterEach` lifecycle.

### 4. @TempDir nə zaman istifadə etmək lazımdır?
**Cavab:** File system ilə işləyən testlər üçün. JUnit avtomatik müvəqqəti qovluq yaradır (OS temp dir-də) və test bitdikdə silinir. Static `@TempDir` — bütün sinif boyunca eyni qovluq (bir dəfə yaradılır, bir dəfə silinir). Instance `@TempDir` — hər test üçün yeni qovluq (izolasiya).

### 5. Test sıralaması niyə lazımdır?
**Cavab:** Normal unit testlər sıra asılılığı olmamalıdır — `@Order` istifadəsi code smell sayılır. Lakin integration/E2E testlər üçün (sifariş axışı: yarat → təsdiq et → göndər) sıra məntiqlıdır. Bu halda `@TestMethodOrder(OrderAnnotation.class)` + `@TestInstance(PER_CLASS)` birlikdə işlədilir ki, state (orderId) testlər arasında paylaşılsın.

*Son yenilənmə: 2026-04-10*
