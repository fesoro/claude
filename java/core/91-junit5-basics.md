# 91 — JUnit 5 Basics — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [JUnit 5 nədir?](#junit-5-nədir)
2. [Əsas annotasiyalar](#əsas-annotasiyalar)
3. [Assertions](#assertions)
4. [Test lifecycle](#test-lifecycle)
5. [Assumptions](#assumptions)
6. [Parameterized tests](#parameterized-tests)
7. [İntervyu Sualları](#intervyu-sualları)

---

## JUnit 5 nədir?

**JUnit 5** — Java üçün ən geniş yayılmış test framework-ü. Üç moduldan ibarətdir:
- **JUnit Platform** — test launcher, IDE/build tool inteqrasiyası
- **JUnit Jupiter** — yeni annotasiyalar və assertions API
- **JUnit Vintage** — JUnit 4 testlərini dəstəkləmək üçün

```xml
<!-- Spring Boot Starter ilə avtomatik gəlir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-test</artifactId>
    <scope>test</scope>
</dependency>
```

---

## Əsas annotasiyalar

```java
class OrderServiceTest {

    // @Test — test metodu
    @Test
    void shouldCreateOrder() {
        // ...
    }

    // @DisplayName — test adını oxunaqlı et
    @Test
    @DisplayName("Boş sifariş yaradıldıqda exception atılmalıdır")
    void shouldThrowWhenOrderIsEmpty() {
        // ...
    }

    // @Disabled — testi deaktiv et
    @Test
    @Disabled("Bug fix gözlənilir — JIRA-123")
    void temporarilyDisabled() {
        // ...
    }

    // @Tag — testləri qruplaşdır
    @Test
    @Tag("slow")
    @Tag("integration")
    void slowIntegrationTest() {
        // ...
    }

    // @Timeout — müddət limiti
    @Test
    @Timeout(value = 500, unit = TimeUnit.MILLISECONDS)
    void shouldCompleteWithin500ms() {
        // ...
    }

    // @RepeatedTest — bir neçə dəfə icra et
    @RepeatedTest(value = 5, name = "Cəhd {currentRepetition}/{totalRepetitions}")
    void repeatedTest(RepetitionInfo info) {
        log.info("Cəhd: {}", info.getCurrentRepetition());
    }
}
```

---

## Assertions

```java
import static org.junit.jupiter.api.Assertions.*;

class AssertionsTest {

    @Test
    void basicAssertions() {
        // Bərabərlik
        assertEquals(42, calculator.add(20, 22));
        assertEquals("Ali", user.getName());

        // Null yoxlama
        assertNotNull(order.getId());
        assertNull(order.getDeletedAt());

        // Boolean
        assertTrue(user.isActive());
        assertFalse(order.isCancelled());

        // Eyni reference
        assertSame(singleton1, singleton2);

        // Array bərabərliyi
        assertArrayEquals(new int[]{1, 2, 3}, result);

        // İstisna
        Exception ex = assertThrows(
            IllegalArgumentException.class,
            () -> orderService.create(null)
        );
        assertEquals("Request null ola bilməz", ex.getMessage());

        // İstisna atmama
        assertDoesNotThrow(() -> orderService.create(validRequest));
    }

    @Test
    void groupedAssertions() {
        // assertAll — bütün yoxlamalar icra olunur (biri fail olsa belə)
        Order order = orderService.findById(1L).orElseThrow();

        assertAll("order fields",
            () -> assertEquals(1L, order.getId()),
            () -> assertEquals(OrderStatus.PENDING, order.getStatus()),
            () -> assertNotNull(order.getCreatedAt()),
            () -> assertEquals(3, order.getItems().size())
        );
    }

    @Test
    void iterableAssertions() {
        List<String> actual = List.of("Ali", "Vəli", "Rəhim");

        assertIterableEquals(
            List.of("Ali", "Vəli", "Rəhim"),
            actual
        );
    }

    @Test
    void exceptionMessage() {
        IllegalArgumentException ex = assertThrows(
            IllegalArgumentException.class,
            () -> new Order(null, List.of())
        );

        assertAll(
            () -> assertTrue(ex.getMessage().contains("customerId")),
            () -> assertFalse(ex.getMessage().isEmpty())
        );
    }
}
```

---

## Test lifecycle

```java
class OrderServiceLifecycleTest {

    private OrderService orderService;
    private OrderRepository mockRepository;

    // Bütün testlərdən ƏVVƏL — bir dəfə (static)
    @BeforeAll
    static void setUpAll() {
        System.out.println("Test sinfi başlayır");
        // DB bağlantısı aç, test server başlat
    }

    // Bütün testlərdən SONRA — bir dəfə (static)
    @AfterAll
    static void tearDownAll() {
        System.out.println("Test sinfi bitmişdir");
        // DB bağlantısını bağla
    }

    // Hər testdən ƏVVƏL
    @BeforeEach
    void setUp() {
        mockRepository = mock(OrderRepository.class);
        orderService = new OrderService(mockRepository);
    }

    // Hər testdən SONRA
    @AfterEach
    void tearDown() {
        // Test datalarını təmizlə
    }

    @Test
    void test1() { /* setUp çağırılıb */ }

    @Test
    void test2() { /* setUp yenidən çağırılıb — təmiz vəziyyət */ }
}

// @TestInstance — instansiyanın həyat dövrü
@TestInstance(TestInstance.Lifecycle.PER_CLASS) // Default: PER_METHOD
class SingleInstanceTest {

    private int counter = 0;

    @BeforeAll
    void setUpAll() {  // static OLMAYA bilər (PER_CLASS ilə)
        counter = 10;
    }

    @Test
    void test1() { counter++; } // counter = 11

    @Test
    void test2() { counter++; } // counter = 12 (state paylaşılır!)
}
```

---

## Assumptions

```java
class AssumptionsTest {

    @Test
    void onlyOnLinux() {
        // Şərt yerinə gəlmirsə — test SKIP olur (FAIL deyil)
        assumeTrue(System.getProperty("os.name").contains("Linux"),
            "Yalnız Linux-da işləyir");

        // Şərt ödənilirsə davam et
        assertTrue(fileService.canCreateSymlink());
    }

    @Test
    void onlyInCiEnvironment() {
        assumeTrue("CI".equals(System.getenv("ENVIRONMENT")),
            "Yalnız CI mühitdə işləyir");

        integrationService.runFullSuite();
    }

    @Test
    void assumeThatExample() {
        String os = System.getProperty("os.name");

        assumingThat(os.contains("Windows"),
            () -> assertEquals("\\", File.separator)); // Yalnız Windows-da

        // Bu hissə həmişə işləyir
        assertNotNull(os);
    }
}
```

---

## Parameterized tests

```java
class ParameterizedTest {

    // @ValueSource — sadə dəyərlər
    @ParameterizedTest
    @ValueSource(strings = {"Ali", "Vəli", "Rəhim"})
    void shouldAcceptValidNames(String name) {
        assertTrue(validator.isValidName(name));
    }

    @ParameterizedTest
    @ValueSource(ints = {-1, 0, -100})
    void shouldRejectNegativeAges(int age) {
        assertThrows(IllegalArgumentException.class,
            () -> new User("test", age));
    }

    // @NullAndEmptySource
    @ParameterizedTest
    @NullAndEmptySource
    @ValueSource(strings = {" ", "  "})
    void shouldRejectBlankNames(String name) {
        assertThrows(IllegalArgumentException.class,
            () -> new User(name, 25));
    }

    // @EnumSource
    @ParameterizedTest
    @EnumSource(OrderStatus.class)
    void shouldHandleAllStatuses(OrderStatus status) {
        assertDoesNotThrow(() -> orderService.getByStatus(status));
    }

    @ParameterizedTest
    @EnumSource(value = OrderStatus.class,
                names = {"PENDING", "CONFIRMED"})
    void shouldSendNotificationForActiveOrders(OrderStatus status) {
        // Yalnız PENDING və CONFIRMED
    }

    // @CsvSource — bir neçə parametr
    @ParameterizedTest
    @CsvSource({
        "100, 10, 90",
        "200, 20, 160",
        "500, 50, 450"
    })
    void shouldCalculateDiscount(double price, double discount, double expected) {
        assertEquals(expected, calculator.applyDiscount(price, discount), 0.01);
    }

    // @CsvFileSource — CSV faylından
    @ParameterizedTest
    @CsvFileSource(resources = "/test-data/users.csv", numLinesToSkip = 1)
    void shouldCreateUsersFromCsv(String name, String email, int age) {
        User user = userService.create(name, email, age);
        assertNotNull(user.getId());
    }

    // @MethodSource — metod-dan data
    @ParameterizedTest
    @MethodSource("provideOrderRequests")
    void shouldCreateOrders(OrderRequest request, OrderStatus expectedStatus) {
        Order order = orderService.create(request);
        assertEquals(expectedStatus, order.getStatus());
    }

    static Stream<Arguments> provideOrderRequests() {
        return Stream.of(
            Arguments.of(validRequest(), OrderStatus.PENDING),
            Arguments.of(prepaidRequest(), OrderStatus.CONFIRMED),
            Arguments.of(expressRequest(), OrderStatus.PROCESSING)
        );
    }
}
```

---

## İntervyu Sualları

### 1. JUnit 4 ilə JUnit 5 fərqi nədir?
**Cavab:** JUnit 5 üç moduldan ibarətdir (Platform, Jupiter, Vintage). `@Test` artıq `org.junit.jupiter.api`-dədir. `@Before`/`@After` → `@BeforeEach`/`@AfterEach`. `@BeforeClass`/`@AfterClass` → `@BeforeAll`/`@AfterAll` (static olmamalıdır `PER_CLASS` ilə). Extension modeli `@Rule` əvəzinə. `@RunWith` → `@ExtendWith`.

### 2. assertAll nə üçündür?
**Cavab:** Normal halda bir assertion fail olduqda test dayanır. `assertAll` bütün assertion-ları icra edir, hamısının nəticəsini bir arada göstərir. Bu, daha sürətli debug imkanı verir — hansı field-lərin yanlış olduğunu bir anda görürsən.

### 3. @ParameterizedTest nə vaxt istifadə olunur?
**Cavab:** Eyni test məntiqini fərqli dəyərlərlə icra etmək lazım olduqda. Kod təkrarını azaldır. `@ValueSource`, `@CsvSource`, `@MethodSource` kimi data source-lar dəstəklənir. Boundary value analysis, equivalence partitioning üçün idealdır.

### 4. Assumptions nə fərqi var assertion-dan?
**Cavab:** Assertion fail olduqda test `FAILED` olur. Assumption fail olduqda test `SKIPPED` (ABORTED) olur — xəta sayılmır. Yalnız müəyyən mühitdə (CI, Linux, production DB) işləməli testlər üçün assumptions istifadə edilir.

### 5. @BeforeAll static olmalıdırmı?
**Cavab:** Default (`PER_METHOD` lifecycle) — bəli, static olmalıdır, çünki hər test üçün yeni sinif instance-ı yaradılır. `@TestInstance(PER_CLASS)` ilə sinif bir dəfə instantiate olunur — `@BeforeAll` static olmaya bilər. Bu shared state lazım olduqda faydalıdır.

*Son yenilənmə: 2026-04-10*
