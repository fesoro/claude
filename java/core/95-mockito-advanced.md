# 95 — Mockito Advanced — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Static metodların mock-u](#static-metodların-mock-u)
2. [Constructor mock-u](#constructor-mock-u)
3. [Strict Stubbing](#strict-stubbing)
4. [BDD style (given/when/then)](#bdd-style-givenwhenthen)
5. [Mockito ilə Async testlər](#mockito-ilə-async-testlər)
6. [Custom Answer-lər](#custom-answer-lər)
7. [Deep Stubbing](#deep-stubbing)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Static metodların mock-u

Mockito 3.4+ — `mockito-inline` ilə static metodları mock etmək mümkündür.

```xml
<!-- mockito-inline — static mock üçün -->
<dependency>
    <groupId>org.mockito</groupId>
    <artifactId>mockito-inline</artifactId>
    <scope>test</scope>
</dependency>
<!-- Spring Boot 3.x ilə mockito-inline artıq daxildir -->
```

```java
class StaticMockTest {

    // ─── UUID mock-u ──────────────────────────────────
    @Test
    void shouldMockUuidGeneration() {
        UUID fixedUuid = UUID.fromString("550e8400-e29b-41d4-a716-446655440000");

        try (MockedStatic<UUID> mockedUuid = mockStatic(UUID.class)) {
            mockedUuid.when(UUID::randomUUID).thenReturn(fixedUuid);

            Order order = orderService.createOrder(validRequest());

            assertEquals("550e8400-e29b-41d4-a716-446655440000",
                order.getId().toString());
        }
        // try-with-resources blokunu çıxdıqdan sonra mock silinir
    }

    // ─── Instant.now() mock-u ─────────────────────────
    @Test
    void shouldMockCurrentTime() {
        Instant fixedTime = Instant.parse("2026-01-15T10:00:00Z");

        try (MockedStatic<Instant> mockedInstant = mockStatic(Instant.class)) {
            mockedInstant.when(Instant::now).thenReturn(fixedTime);

            // Əlavə real metodlar üçün delegate
            mockedInstant.when(() -> Instant.parse(anyString()))
                .thenCallRealMethod();

            Order order = orderService.createOrder(validRequest());

            assertEquals(fixedTime, order.getCreatedAt());
        }
    }

    // ─── Util sinfi mock-u ────────────────────────────
    @Test
    void shouldMockUtilityClass() {
        try (MockedStatic<SecurityUtils> mockedSecurity = mockStatic(SecurityUtils.class)) {
            mockedSecurity.when(SecurityUtils::getCurrentUserId)
                .thenReturn("user-123");

            mockedSecurity.when(() -> SecurityUtils.hasRole(anyString()))
                .thenReturn(true);

            // Test
            Order order = orderService.createOrderForCurrentUser(validRequest());

            assertEquals("user-123", order.getCreatedBy());
        }
    }

    // ─── Files / Paths utility ────────────────────────
    @Test
    void shouldMockFileExists() {
        try (MockedStatic<Files> mockedFiles = mockStatic(Files.class)) {
            mockedFiles.when(() -> Files.exists(any(Path.class)))
                .thenReturn(true);

            mockedFiles.when(() -> Files.readString(any(Path.class)))
                .thenReturn("file content");

            String content = fileService.readConfig("/etc/app/config.json");
            assertEquals("file content", content);
        }
    }
}
```

---

## Constructor mock-u

```java
class ConstructorMockTest {

    // ─── new Object() çağırışını mock et ─────────────
    @Test
    void shouldMockConstructor() {
        HttpClient mockClient = mock(HttpClient.class);

        try (MockedConstruction<HttpClient> mocked =
                mockConstruction(HttpClient.class)) {

            // HttpClient.Builder().build() çağırıldıqda mock qaytarır
            when(mockClient.send(any(), any()))
                .thenReturn(HttpResponse.ok("{\"status\":\"ok\"}"));

            ExternalApiService service = new ExternalApiService();
            String result = service.callApi("/endpoint");

            assertEquals("ok", result);
        }
    }

    // ─── Constructor argumentlərini yoxla ─────────────
    @Test
    void shouldVerifyConstructorArguments() {
        try (MockedConstruction<EmailSender> mocked =
                mockConstruction(EmailSender.class, (mock, context) -> {
                    // Hər yeni instance üçün çağırılır
                    List<?> args = context.arguments();
                    assertEquals("smtp.example.com", args.get(0));
                    assertEquals(587, args.get(1));
                })) {

            EmailService emailService = new EmailService(); // new EmailSender("smtp...", 587) çağırır

            assertFalse(mocked.constructed().isEmpty());
        }
    }
}
```

---

## Strict Stubbing

```java
// ─── STRICT_STUBS — yalnız istifadə olunan stub-lar ──
// Mockito 2.x+ default strict stubbing

// YANLIŞ — istifadə olunmayan stub
@ExtendWith(MockitoExtension.class)  // Default: STRICT_STUBS
class StrictStubbingTest {

    @Mock
    private OrderRepository orderRepository;

    @InjectMocks
    private OrderService orderService;

    @Test
    void unnecessaryStubWRONG() {
        // Bu stub heç vaxt çağırılmır → UnnecessaryStubbingException!
        when(orderRepository.findById(1L)).thenReturn(Optional.of(order()));

        // Bu test findById çağırmır
        long count = orderService.countAllOrders();
        assertEquals(0, count);
    }
}

// DOĞRU — yalnız lazım olan stub
@ExtendWith(MockitoExtension.class)
class StrictStubbingCorrectTest {

    @Mock
    private OrderRepository orderRepository;

    @InjectMocks
    private OrderService orderService;

    @Test
    void onlyNecessaryStubs() {
        when(orderRepository.count()).thenReturn(5L);

        long count = orderService.countAllOrders();
        assertEquals(5, count);
    }
}

// ─── Lenient stubbing (lazım olduqda) ─────────────────
@ExtendWith(MockitoExtension.class)
class LenientStubbingTest {

    @Mock
    private OrderRepository orderRepository;

    @BeforeEach
    void setUp() {
        // Bəzi testlər istifadə etməsə də xəta verməsin
        lenient().when(orderRepository.findById(anyLong()))
            .thenReturn(Optional.of(order()));
    }

    @Test
    void testThatDoesNotUseStub() {
        // findById çağırılmır — lenient() sayəsində UnnecessaryStubbingException yoxdur
        long count = orderService.countAllOrders();
        assertEquals(0, count);
    }
}
```

---

## BDD style (given/when/then)

```java
import static org.mockito.BDDMockito.*;

// BDDMockito — Behavior-Driven Development üslubu
@ExtendWith(MockitoExtension.class)
class BDDStyleTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private NotificationService notificationService;

    @InjectMocks
    private OrderService orderService;

    @Test
    @DisplayName("Etibarlı sifariş yaradıldıqda notification göndərilir")
    void shouldSendNotificationWhenOrderCreated() {
        // GIVEN
        OrderRequest request = validOrderRequest();
        Order expectedOrder = Order.builder()
            .id(1L)
            .status(OrderStatus.PENDING)
            .build();

        given(orderRepository.save(any(Order.class)))
            .willReturn(expectedOrder);

        // WHEN
        Order actualOrder = orderService.createOrder(request);

        // THEN
        then(orderRepository).should().save(any(Order.class));
        then(notificationService).should().notifyOrderCreated(expectedOrder);
        then(orderRepository).shouldHaveNoMoreInteractions();

        assertThat(actualOrder.getStatus()).isEqualTo(OrderStatus.PENDING);
    }

    @Test
    @DisplayName("DB xətasında exception yenidən atılır")
    void shouldRethrowOnDatabaseError() {
        // GIVEN
        given(orderRepository.save(any()))
            .willThrow(new DataAccessException("Connection timeout") {});

        // WHEN / THEN
        assertThatThrownBy(() -> orderService.createOrder(validRequest()))
            .isInstanceOf(OrderCreationException.class)
            .hasMessageContaining("Sifariş yaradıla bilmədi");

        then(notificationService).should(never()).notifyOrderCreated(any());
    }

    // ─── BDDMockito ilə void metodlar ─────────────────
    @Test
    void shouldHandleVoidMethodsInBDD() {
        // GIVEN — void metod üçün willDoNothing
        willDoNothing().given(notificationService).notifyOrderCreated(any());

        // Ya da exception:
        willThrow(new NotificationException("Slack down"))
            .given(notificationService).notifyOrderCreated(any());

        // WHEN
        assertThrows(OrderCreationException.class,
            () -> orderService.createOrder(validRequest()));
    }
}
```

---

## Mockito ilə Async testlər

```java
@ExtendWith(MockitoExtension.class)
class AsyncMockTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private NotificationService notificationService;

    @InjectMocks
    private AsyncOrderService asyncOrderService;

    // ─── CompletableFuture mock-u ─────────────────────
    @Test
    void shouldMockCompletableFuture() throws Exception {
        Order expectedOrder = order();

        when(orderRepository.saveAsync(any()))
            .thenReturn(CompletableFuture.completedFuture(expectedOrder));

        CompletableFuture<Order> future = asyncOrderService.createOrderAsync(validRequest());

        Order result = future.get(5, TimeUnit.SECONDS);
        assertNotNull(result);
        assertEquals(expectedOrder.getId(), result.getId());
    }

    // ─── Async exception ──────────────────────────────
    @Test
    void shouldHandleAsyncException() {
        when(orderRepository.saveAsync(any()))
            .thenReturn(CompletableFuture.failedFuture(
                new DataAccessException("DB error") {}));

        CompletableFuture<Order> future = asyncOrderService.createOrderAsync(validRequest());

        ExecutionException ex = assertThrows(ExecutionException.class,
            () -> future.get(5, TimeUnit.SECONDS));

        assertTrue(ex.getCause() instanceof OrderCreationException);
    }

    // ─── @Async metod testi ───────────────────────────
    @Test
    void shouldCallAsyncMethod() throws Exception {
        CountDownLatch latch = new CountDownLatch(1);

        doAnswer(inv -> {
            latch.countDown();
            return null;
        }).when(notificationService).sendAsyncNotification(any());

        asyncOrderService.createAndNotify(validRequest());

        // Async-i gözlə
        assertTrue(latch.await(5, TimeUnit.SECONDS));
        verify(notificationService).sendAsyncNotification(any());
    }
}
```

---

## Custom Answer-lər

```java
class CustomAnswerTest {

    @Mock
    private OrderRepository orderRepository;

    // ─── ID generator answer ──────────────────────────
    private static final Answer<Order> ID_GENERATOR = invocation -> {
        Order order = invocation.getArgument(0);
        order.setId(ThreadLocalRandom.current().nextLong(1, 1000));
        order.setCreatedAt(Instant.now());
        return order;
    };

    @Test
    void shouldUseCustomAnswer() {
        when(orderRepository.save(any(Order.class))).then(ID_GENERATOR);

        Order saved = orderRepository.save(new Order());

        assertNotNull(saved.getId());
        assertNotNull(saved.getCreatedAt());
    }

    // ─── Delay simulation ─────────────────────────────
    private static Answer<Order> withDelay(long millis) {
        return invocation -> {
            Thread.sleep(millis);
            return invocation.getArgument(0);
        };
    }

    @Test
    void shouldHandleSlowDatabase() {
        when(orderRepository.save(any())).then(withDelay(100));

        long start = System.currentTimeMillis();
        orderRepository.save(new Order());
        long elapsed = System.currentTimeMillis() - start;

        assertTrue(elapsed >= 100);
    }

    // ─── Delegation answer ────────────────────────────
    @Test
    void shouldDelegateToRealImplementation() {
        OrderRepository realRepo = new InMemoryOrderRepository();

        when(orderRepository.save(any()))
            .thenAnswer(AdditionalAnswers.delegatesTo(realRepo));

        Order saved = orderRepository.save(Order.builder().build());
        assertNotNull(saved.getId());
    }

    // ─── ReturnsArgumentAt ────────────────────────────
    @Test
    void shouldReturnFirstArgument() {
        when(orderRepository.save(any()))
            .thenAnswer(AdditionalAnswers.returnsFirstArg());

        Order order = Order.builder().id(42L).build();
        Order result = orderRepository.save(order);

        assertSame(order, result); // Eyni reference
    }
}
```

---

## Deep Stubbing

```java
class DeepStubbingTest {

    // ─── Adi mock ilə problem ─────────────────────────
    @Test
    void deepStubWithNormalMock() {
        Order mockOrder = mock(Order.class);
        Customer mockCustomer = mock(Customer.class);
        Address mockAddress = mock(Address.class);

        // Zəncir mock-laması — çox verbose
        when(mockOrder.getCustomer()).thenReturn(mockCustomer);
        when(mockCustomer.getAddress()).thenReturn(mockAddress);
        when(mockAddress.getCity()).thenReturn("Bakı");

        assertEquals("Bakı", mockOrder.getCustomer().getAddress().getCity());
    }

    // ─── DEEP_STUBS ilə sadə yol ─────────────────────
    @Test
    void deepStubExample() {
        // RETURNS_DEEP_STUBS — zəncir çağırışları avtomatik mock edir
        Order mockOrder = mock(Order.class, RETURNS_DEEP_STUBS);

        when(mockOrder.getCustomer().getAddress().getCity())
            .thenReturn("Bakı");

        assertEquals("Bakı", mockOrder.getCustomer().getAddress().getCity());
    }

    // ─── @Mock(answer = RETURNS_DEEP_STUBS) ──────────
    @ExtendWith(MockitoExtension.class)
    class DeepStubAnnotationTest {

        @Mock(answer = RETURNS_DEEP_STUBS)
        private Order order;

        @Test
        void shouldSupportDeepChaining() {
            when(order.getCustomer().getShippingAddress().getPostalCode())
                .thenReturn("AZ1000");

            assertEquals("AZ1000",
                order.getCustomer().getShippingAddress().getPostalCode());
        }
    }

    // ─── Deep stub nə vaxt istifadə etmək LAZIM DEYİL ──
    // Law of Demeter pozulur: order.getCustomer().getAddress().getCity()
    // Belə zəncir varsa kod refaktorinq lazımdır:
    //   order.getDeliveryCity() — daha yaxşı dizayn
    //
    // Deep stub yalnız test etmə çətin olan third-party kitabxanalar
    // (HttpResponse, gRPC stub-ları) üçün tövsiyə edilir.
}
```

---

## İntervyu Sualları

### 1. Static metodları necə mock etmək olar?
**Cavab:** `mockito-inline` (Mockito 3.4+) ilə `mockStatic(ClassName.class)` istifadə edilir. `try-with-resources` bloku içərisində saxlanılır — blok bitdikdə mock avtomatik silinir (ThreadLocal-a yazılır). Həmçinin `mockConstruction()` ilə `new` operatoru mock edilir. Static mock testləri daha mürəkkəb olur — mümkünsə dependency injection ilə real mock-lama üstün tutulur.

### 2. Strict Stubbing nədir?
**Cavab:** Mockito 2.x-dən default — `UnnecessaryStubbingException` atar ki, stub yaradıldı amma test onu çağırmadı. Bu, test kodunu təmiz saxlayır (istifadə olunmayan stub-lar dead code-dur). `lenient()` ilə istisna edilə bilər — məsələn `@BeforeEach`-da bütün testlər üçün ümumi stub-lar. `@MockitoSettings(strictness = LENIENT)` sinif üçün tətbiq edilir.

### 3. BDDMockito-nun fərqi nədir?
**Cavab:** Eyni Mockito funksionallığı, BDD terminologiyasında: `given()` → `when()`, `then(mock).should()` → `verify()`. Test oxunuşunu yaxşılaşdırır — Given/When/Then strukturu ilə test ssenariisi daha aydın ifadə olunur. Heç bir texniki fərq yoxdur, stilistik seçimdir.

### 4. Deep Stubbing nə vaxt istifadə edilir?
**Cavab:** Zəncir çağırışlarını (`order.getCustomer().getAddress().getCity()`) mock etmək üçün — `RETURNS_DEEP_STUBS` hər `getXxx()` çağırışında avtomatik yeni mock qaytarır. Lakin bu, Law of Demeter pozuntusunun işarəsidir. Yalnız third-party kitabxana obyektlərini (HttpResponse, gRPC channel) test etmək üçün məqsədəuyğundur; öz kodunuzu bu cür zəncirləyirsinizsə, refaktorinq lazımdır.

### 5. thenAnswer() nə zaman lazımdır?
**Cavab:** `thenReturn()` statik dəyər qaytarır. `thenAnswer()` gələn argumentə görə dinamik cavab yaradır. Məsələn: `save(order)` çağırıldıqda həmin order-ə ID set edib qaytarmaq, delay simulation, argument asılı şəkildə müxtəlif nəticə qaytarmaq. `AdditionalAnswers.returnsFirstArg()` ən çox istifadə olunan hazır answer-dır.

*Son yenilənmə: 2026-04-10*
