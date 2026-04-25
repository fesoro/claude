# 93 — AssertJ — Geniş İzah

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [AssertJ nədir?](#assertj-nədir)
2. [Əsas assertion-lar](#əsas-assertion-lar)
3. [Collection assertion-ları](#collection-assertion-ları)
4. [Exception assertion-ları](#exception-assertion-ları)
5. [Soft Assertions](#soft-assertions)
6. [Custom Assertions](#custom-assertions)
7. [Object comparison](#object-comparison)
8. [İntervyu Sualları](#intervyu-sualları)

---

## AssertJ nədir?

**AssertJ** — fluent (zəncir) assertion API. JUnit-in `assertEquals()` üslubundan daha oxunaqlı, daha ifadəli xəta mesajları.

```xml
<!-- Spring Boot Starter Test ilə avtomatik gəlir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-test</artifactId>
    <scope>test</scope>
</dependency>
```

```java
// JUnit assertions vs AssertJ müqayisəsi:

// JUnit — az ifadəli
assertEquals("PENDING", order.getStatus().name());
assertTrue(order.getItems().size() > 0);
assertNotNull(order.getId());

// AssertJ — fluent, oxunaqlı
assertThat(order.getStatus()).isEqualTo(OrderStatus.PENDING);
assertThat(order.getItems()).isNotEmpty();
assertThat(order.getId()).isNotNull();

// Xəta mesajı fərqi:
// JUnit: expected: <"CONFIRMED"> but was: <"PENDING">
// AssertJ: expected: "CONFIRMED"  but was: "PENDING"
//          at: OrderServiceTest.shouldConfirmOrder(OrderServiceTest.java:42)
```

---

## Əsas assertion-lar

```java
import static org.assertj.core.api.Assertions.*;

class BasicAssertionsTest {

    // ─── Object ───────────────────────────────────────
    @Test
    void objectAssertions() {
        Order order = orderService.findById(1L).orElseThrow();

        assertThat(order).isNotNull();
        assertThat(order).isInstanceOf(Order.class);
        assertThat(order).isEqualTo(expectedOrder);
        assertThat(order).isSameAs(singletonOrder); // reference eq
        assertThat(order).isNotSameAs(anotherOrder);
    }

    // ─── String ───────────────────────────────────────
    @Test
    void stringAssertions() {
        String orderId = "ORD-2026-001";

        assertThat(orderId).isNotNull();
        assertThat(orderId).isNotEmpty();
        assertThat(orderId).isNotBlank();
        assertThat(orderId).startsWith("ORD");
        assertThat(orderId).endsWith("001");
        assertThat(orderId).contains("2026");
        assertThat(orderId).hasSize(11);
        assertThat(orderId).matches("ORD-\\d{4}-\\d{3}");
        assertThat(orderId).doesNotContain("INVALID");
        assertThat(orderId).isEqualToIgnoringCase("ord-2026-001");
        assertThat(orderId).isUpperCase(); // yalnız uppercase hərflər üçün
    }

    // ─── Number ───────────────────────────────────────
    @Test
    void numberAssertions() {
        BigDecimal price = new BigDecimal("149.99");

        assertThat(price).isPositive();
        assertThat(price).isGreaterThan(BigDecimal.ZERO);
        assertThat(price).isGreaterThanOrEqualTo(new BigDecimal("100"));
        assertThat(price).isLessThan(new BigDecimal("200"));
        assertThat(price).isBetween(new BigDecimal("100"), new BigDecimal("200"));

        // Double precision
        double result = 0.1 + 0.2;
        assertThat(result).isCloseTo(0.3, within(0.0001));
        assertThat(result).isCloseTo(0.3, offset(0.0001));
    }

    // ─── Boolean ──────────────────────────────────────
    @Test
    void booleanAssertions() {
        assertThat(order.isActive()).isTrue();
        assertThat(order.isCancelled()).isFalse();
    }

    // ─── Optional ─────────────────────────────────────
    @Test
    void optionalAssertions() {
        Optional<Order> found = orderService.findById(1L);

        assertThat(found).isPresent();
        assertThat(found).hasValue(expectedOrder);
        assertThat(found).isNotEmpty();

        Optional<Order> missing = orderService.findById(9999L);

        assertThat(missing).isEmpty();
        assertThat(missing).isNotPresent();
    }

    // ─── Instant / Date ───────────────────────────────
    @Test
    void temporalAssertions() {
        Instant createdAt = order.getCreatedAt();

        assertThat(createdAt).isNotNull();
        assertThat(createdAt).isBefore(Instant.now());
        assertThat(createdAt).isAfter(Instant.parse("2026-01-01T00:00:00Z"));
        assertThat(createdAt).isBetween(
            Instant.parse("2026-01-01T00:00:00Z"),
            Instant.now()
        );
    }
}
```

---

## Collection assertion-ları

```java
class CollectionAssertionsTest {

    @Test
    void listAssertions() {
        List<Order> orders = orderService.findAll();

        assertThat(orders).isNotNull();
        assertThat(orders).isNotEmpty();
        assertThat(orders).hasSize(3);
        assertThat(orders).hasSizeGreaterThan(0);
        assertThat(orders).hasSizeLessThanOrEqualTo(100);

        // Element yoxlama
        assertThat(orders).contains(order1, order2);
        assertThat(orders).containsExactly(order1, order2, order3); // sıra ilə
        assertThat(orders).containsExactlyInAnyOrder(order2, order1, order3); // sırasız
        assertThat(orders).containsOnly(order1, order2, order3); // başqası olmamalı

        assertThat(orders).doesNotContain(cancelledOrder);
        assertThat(orders).doesNotContainNull();

        // İlk/son element
        assertThat(orders).first().isEqualTo(order1);
        assertThat(orders).last().isEqualTo(order3);
        assertThat(orders).element(1).isEqualTo(order2);
    }

    @Test
    void filteringAndExtraction() {
        List<Order> orders = List.of(
            Order.builder().status(OrderStatus.PENDING).customerId("c1").totalAmount(new BigDecimal("100")).build(),
            Order.builder().status(OrderStatus.CONFIRMED).customerId("c2").totalAmount(new BigDecimal("200")).build(),
            Order.builder().status(OrderStatus.PENDING).customerId("c3").totalAmount(new BigDecimal("300")).build()
        );

        // Filtirləmə
        assertThat(orders)
            .filteredOn(o -> o.getStatus() == OrderStatus.PENDING)
            .hasSize(2);

        assertThat(orders)
            .filteredOn("status", OrderStatus.PENDING)
            .extracting("customerId")
            .containsExactlyInAnyOrder("c1", "c3");

        // Field extraction
        assertThat(orders)
            .extracting(Order::getCustomerId)
            .containsExactly("c1", "c2", "c3");

        assertThat(orders)
            .extracting(Order::getCustomerId, Order::getStatus)
            .containsExactly(
                tuple("c1", OrderStatus.PENDING),
                tuple("c2", OrderStatus.CONFIRMED),
                tuple("c3", OrderStatus.PENDING)
            );
    }

    @Test
    void allMatchAndAnyMatch() {
        List<Order> orders = orderService.findByStatus(OrderStatus.PENDING);

        // Hamısı şərti ödəyir
        assertThat(orders).allMatch(o -> o.getStatus() == OrderStatus.PENDING);
        assertThat(orders).allSatisfy(o -> {
            assertThat(o.getStatus()).isEqualTo(OrderStatus.PENDING);
            assertThat(o.getCreatedAt()).isNotNull();
        });

        // Ən az biri şərti ödəyir
        assertThat(orders).anyMatch(o -> o.getTotalAmount().compareTo(BigDecimal.ZERO) > 0);
        assertThat(orders).anySatisfy(o ->
            assertThat(o.getCustomerId()).startsWith("VIP")
        );

        // Heç biri şərti ödəmir
        assertThat(orders).noneMatch(o -> o.getStatus() == OrderStatus.CANCELLED);
    }

    @Test
    void mapAssertions() {
        Map<String, Integer> itemCounts = Map.of(
            "product-1", 3,
            "product-2", 1,
            "product-3", 5
        );

        assertThat(itemCounts).isNotNull();
        assertThat(itemCounts).hasSize(3);
        assertThat(itemCounts).containsKey("product-1");
        assertThat(itemCounts).containsKeys("product-1", "product-2");
        assertThat(itemCounts).containsValue(5);
        assertThat(itemCounts).containsEntry("product-1", 3);
        assertThat(itemCounts).doesNotContainKey("product-99");
    }
}
```

---

## Exception assertion-ları

```java
class ExceptionAssertionsTest {

    @Test
    void shouldThrowException() {
        assertThatThrownBy(() -> orderService.createOrder(null))
            .isInstanceOf(IllegalArgumentException.class)
            .hasMessage("Request null ola bilməz")
            .hasMessageContaining("null")
            .hasNoCause();
    }

    @Test
    void shouldThrowWithCause() {
        assertThatThrownBy(() -> orderService.createOrder(invalidRequest()))
            .isInstanceOf(OrderCreationException.class)
            .hasMessageContaining("yaradıla bilmədi")
            .hasCauseInstanceOf(DataIntegrityViolationException.class)
            .rootCause()
            .hasMessageContaining("constraint violation");
    }

    @Test
    void assertThatExceptionVariant() {
        assertThatExceptionOfType(OrderNotFoundException.class)
            .isThrownBy(() -> orderService.findById(9999L))
            .withMessage("Order tapılmadı: 9999")
            .withMessageContaining("9999");
    }

    @Test
    void specificExceptionTypes() {
        // Hazır xüsusi assertion-lar
        assertThatIllegalArgumentException()
            .isThrownBy(() -> new Order(null, List.of()))
            .withMessageContaining("customerId");

        assertThatNullPointerException()
            .isThrownBy(() -> orderService.process(null));

        assertThatIllegalStateException()
            .isThrownBy(() -> cancelledOrder.cancel());
    }

    @Test
    void shouldNotThrow() {
        assertThatNoException()
            .isThrownBy(() -> orderService.createOrder(validRequest()));

        // Ya da:
        assertThatCode(() -> orderService.createOrder(validRequest()))
            .doesNotThrowAnyException();
    }

    @Test
    void catchThrowablePattern() {
        Throwable thrown = catchThrowable(
            () -> orderService.createOrder(null));

        assertThat(thrown)
            .isInstanceOf(IllegalArgumentException.class)
            .hasMessage("Request null ola bilməz");
    }
}
```

---

## Soft Assertions

```java
class SoftAssertionsTest {

    @Test
    void softAssertionsExample() {
        Order order = orderService.findById(1L).orElseThrow();

        // Normal: biri fail olduqda dayanır
        // SoftAssertions: hamısı yoxlanır, sonda hamısı raporlanır

        SoftAssertions softly = new SoftAssertions();

        softly.assertThat(order.getId()).isEqualTo(1L);
        softly.assertThat(order.getCustomerId()).isEqualTo("customer-1");
        softly.assertThat(order.getStatus()).isEqualTo(OrderStatus.PENDING);
        softly.assertThat(order.getTotalAmount()).isGreaterThan(BigDecimal.ZERO);
        softly.assertThat(order.getCreatedAt()).isNotNull();

        softly.assertAll(); // Bütün xətaları bir yerdə göstər
    }

    @Test
    void softAssertionsWithLambda() {
        Order order = orderService.findById(1L).orElseThrow();

        assertSoftly(softly -> {
            softly.assertThat(order.getId()).isEqualTo(1L);
            softly.assertThat(order.getStatus()).isEqualTo(OrderStatus.PENDING);
            softly.assertThat(order.getItems()).hasSize(3);
        });
    }

    // JUnit 5 Extension ilə @InjectSoftAssertions
    @ExtendWith(SoftAssertionsExtension.class)
    class SoftAssertionExtensionTest {

        @InjectSoftAssertions
        SoftAssertions softly;

        @Test
        void injectedSoftAssertions() {
            Order order = orderService.findById(1L).orElseThrow();

            softly.assertThat(order.getId()).isNotNull();
            softly.assertThat(order.getStatus()).isEqualTo(OrderStatus.PENDING);
            // assertAll() avtomatik çağırılır test bitdikdə
        }
    }
}
```

---

## Custom Assertions

```java
// ─── Custom assertion sinfi ───────────────────────────
public class OrderAssert extends AbstractAssert<OrderAssert, Order> {

    public OrderAssert(Order actual) {
        super(actual, OrderAssert.class);
    }

    public static OrderAssert assertThat(Order order) {
        return new OrderAssert(order);
    }

    public OrderAssert isPending() {
        isNotNull();
        if (actual.getStatus() != OrderStatus.PENDING) {
            failWithMessage("Order PENDING olmalıdır, amma <%s>-dir",
                actual.getStatus());
        }
        return this;
    }

    public OrderAssert isConfirmed() {
        isNotNull();
        if (actual.getStatus() != OrderStatus.CONFIRMED) {
            failWithMessage("Order CONFIRMED olmalıdır, amma <%s>-dir",
                actual.getStatus());
        }
        return this;
    }

    public OrderAssert belongsToCustomer(String customerId) {
        isNotNull();
        if (!customerId.equals(actual.getCustomerId())) {
            failWithMessage("Order <%s> müştərisinə aid olmalıdır, amma <%s>-dir",
                customerId, actual.getCustomerId());
        }
        return this;
    }

    public OrderAssert hasItems(int count) {
        isNotNull();
        int actualCount = actual.getItems().size();
        if (actualCount != count) {
            failWithMessage("Order <%d> item olmalıdır, amma <%d>-dir",
                count, actualCount);
        }
        return this;
    }

    public OrderAssert hasTotalAmountGreaterThan(BigDecimal amount) {
        isNotNull();
        if (actual.getTotalAmount().compareTo(amount) <= 0) {
            failWithMessage("Total amount <%s>-dən böyük olmalıdır, amma <%s>-dir",
                amount, actual.getTotalAmount());
        }
        return this;
    }
}

// İstifadə:
class CustomAssertTest {

    @Test
    void shouldCreateValidOrder() {
        Order order = orderService.createOrder(validRequest());

        // Fluent, oxunaqlı
        OrderAssert.assertThat(order)
            .isNotNull()
            .isPending()
            .belongsToCustomer("customer-1")
            .hasItems(2)
            .hasTotalAmountGreaterThan(BigDecimal.ZERO);
    }
}
```

---

## Object comparison

```java
class ObjectComparisonTest {

    @Test
    void recursiveComparison() {
        OrderRequest request = validOrderRequest();
        Order created = orderService.createOrder(request);

        // Bütün field-lər recursive müqayisə edilir
        assertThat(created)
            .usingRecursiveComparison()
            .ignoringFields("id", "createdAt", "updatedAt") // ID, timestamp keç
            .isEqualTo(Order.builder()
                .customerId("customer-1")
                .status(OrderStatus.PENDING)
                .items(List.of(
                    OrderItem.builder().productId("p1").quantity(2).build()
                ))
                .build());
    }

    @Test
    void recursiveComparisonWithIgnoreCollectionOrder() {
        List<Order> actual = orderService.findAll();
        List<Order> expected = buildExpectedOrders();

        assertThat(actual)
            .usingRecursiveComparison()
            .ignoringFields("id", "createdAt")
            .ignoringCollectionOrder()
            .isEqualTo(expected);
    }

    @Test
    void comparingByFields() {
        Order order1 = buildOrder(1L, "c1", OrderStatus.PENDING);
        Order order2 = buildOrder(2L, "c1", OrderStatus.PENDING);

        // ID fərqli, amma məzmun eyni
        assertThat(order1)
            .usingComparatorForType(BigDecimal::compareTo, BigDecimal.class)
            .usingRecursiveComparison()
            .ignoringFields("id")
            .isEqualTo(order2);
    }

    @Test
    void usingComparatorForFields() {
        Order order = orderService.findById(1L).orElseThrow();

        assertThat(order)
            .usingRecursiveComparison()
            .withComparatorForFields(
                (a, b) -> ((String) a).equalsIgnoreCase((String) b) ? 0 : 1,
                "customerId"
            )
            .isEqualTo(expectedOrder);
    }
}
```

---

## İntervyu Sualları

### 1. AssertJ-nin JUnit assertions-dan üstünlüyü?
**Cavab:** Fluent API — zəncir assertion-lar (`assertThat(x).isNotNull().isEqualTo(...)`). Daha oxunaqlı xəta mesajları — faktiki vs gözlənilən aydın göstərilir. `extracting()`, `filteredOn()` — collection manipulation. `SoftAssertions` — hamısı yoxlanır. `usingRecursiveComparison()` — deep object comparison. Custom assertion-lar — domain specific oxunaqlı test-lər.

### 2. SoftAssertions nədir?
**Cavab:** Normal assertion fail olduqda test dayandırılır. `SoftAssertions` bütün assertion-ları icra edir, sonda hamısının nəticəsini bir arada raporlayır. Bir obyektin çoxlu field-lərini eyni anda yoxlamaq üçün faydalıdır — hansı field-lərin yanlış olduğunu bir anda görürsən. `assertAll()` ilə ya da `SoftAssertionsExtension` ilə avtomatik istifadə edilir.

### 3. usingRecursiveComparison() nə edir?
**Cavab:** Obyektləri bütün field-ləri ilə dərinliyinə müqayisə edir. `equals()` metodu çağırmır — field-ləri birbaşa müqayisə edir. `ignoringFields("id", "createdAt")` — bəzi field-ləri keç. `ignoringCollectionOrder()` — list sırası əhəmiyyətsizdir. DTO → Entity kimi fərqli tip müqayisəsini dəstəkləyir.

### 4. Custom assertion yaratmaq nə vaxt lazımdır?
**Cavab:** Bir domain obyektini dəfələrlə müxtəlif cəhətdən yoxlayırsansa (`isPending()`, `belongsToCustomer()`, `hasItems()`). Test kodu daha oxunaqlı, domain-specific terminologiyada ifadə olunur. `AbstractAssert<SELF, ACTUAL>` extend edilir, metod adları domain dilinə uyğun seçilir. Xəta mesajları `failWithMessage()` ilə domain-specific yazılır.

### 5. assertThatThrownBy vs assertThatExceptionOfType fərqi?
**Cavab:** `assertThatThrownBy(() -> code)` — kodu çalışdırır, atılan exception üzərində assertion. `assertThatExceptionOfType(XxxException.class).isThrownBy(() -> code)` — əvvəlcə exception tipi göstərilir, sonra kod. Funksional ekvivalentdir, üslub seçimidir. `assertThatIllegalArgumentException()`, `assertThatNullPointerException()` — tez-tez istifadə olunan tiplər üçün shortcut.

*Son yenilənmə: 2026-04-10*
