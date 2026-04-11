# Mockito Basics — Geniş İzah

## Mündəricat
1. [Mockito nədir?](#mockito-nədir)
2. [Mock yaratmaq](#mock-yaratmaq)
3. [Stubbing — davranış müəyyən etmək](#stubbing--davranış-müəyyən-etmək)
4. [Verify — çağırışları yoxlamaq](#verify--çağırışları-yoxlamaq)
5. [ArgumentMatchers](#argumentmatchers)
6. [Spy — real obyekt izləmək](#spy--real-obyekt-izləmək)
7. [Annotation-based mocking](#annotation-based-mocking)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Mockito nədir?

**Mockito** — Java üçün ən populyar mocking framework. Test zamanı real dependency-ləri (DB, HTTP client, email service) saxta (mock) obyektlərlə əvəz edir.

```
Real test problemi:
  OrderService → OrderRepository (PostgreSQL)
                → EmailService (SMTP server)
                → PaymentService (bank API)

Mock ilə:
  OrderService → MockOrderRepository (in-memory)
               → MockEmailService (heç nə göndərmir)
               → MockPaymentService (uğurlu cavab qaytarır)
```

```xml
<!-- Spring Boot Starter Test ilə avtomatik gəlir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-test</artifactId>
    <scope>test</scope>
</dependency>
```

---

## Mock yaratmaq

```java
// ─── Proqramatik yaratma ──────────────────────────────
class OrderServiceTest {

    private OrderRepository orderRepository;
    private EmailService emailService;
    private OrderService orderService;

    @BeforeEach
    void setUp() {
        // mock() — interfeys ya da konkret sinif mock-u
        orderRepository = mock(OrderRepository.class);
        emailService = mock(EmailService.class);

        orderService = new OrderService(orderRepository, emailService);
    }

    @Test
    void shouldCreateOrder() {
        // ...
    }
}

// ─── @Mock annotasiyası (MockitoExtension ilə) ────────
@ExtendWith(MockitoExtension.class)
class OrderServiceTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private EmailService emailService;

    @InjectMocks  // Mock-ları avtomatik inject edir
    private OrderService orderService;

    @Test
    void shouldCreateOrder() {
        // setUp() lazım deyil — avtomatik inject
    }
}

// ─── Mock davranışı (default) ─────────────────────────
// Stubbing olmadan mock-lar default qaytarır:
// - Object → null
// - int/long/double → 0
// - boolean → false
// - Collection → boş collection (List.of(), Set.of())
// - Optional → Optional.empty()
```

---

## Stubbing — davranış müəyyən etmək

```java
@ExtendWith(MockitoExtension.class)
class StubbingExamplesTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private PaymentService paymentService;

    // ─── when().thenReturn() ──────────────────────────
    @Test
    void whenThenReturnExample() {
        Order order = Order.builder()
            .id(1L)
            .status(OrderStatus.PENDING)
            .build();

        // findById(1L) çağırıldıqda order qaytarsın
        when(orderRepository.findById(1L))
            .thenReturn(Optional.of(order));

        // findById(99L) çağırıldıqda empty qaytarsın
        when(orderRepository.findById(99L))
            .thenReturn(Optional.empty());

        // Ardıcıl qaytarma — hər çağırışda fərqli nəticə
        when(orderRepository.count())
            .thenReturn(1L)
            .thenReturn(2L)
            .thenReturn(3L);

        // Test
        assertEquals(1L, orderRepository.count()); // 1
        assertEquals(2L, orderRepository.count()); // 2
        assertEquals(3L, orderRepository.count()); // 3
        assertEquals(3L, orderRepository.count()); // son dəyər təkrarlannır
    }

    // ─── when().thenThrow() ───────────────────────────
    @Test
    void whenThenThrowExample() {
        when(orderRepository.findById(anyLong()))
            .thenThrow(new RuntimeException("DB bağlantısı yoxdur"));

        assertThrows(RuntimeException.class,
            () -> orderRepository.findById(1L));
    }

    // ─── when().thenAnswer() — dinamik cavab ─────────
    @Test
    void whenThenAnswerExample() {
        // Gələn argumenti emal edib qaytarır
        when(orderRepository.save(any(Order.class)))
            .thenAnswer(invocation -> {
                Order arg = invocation.getArgument(0);
                // ID set et (DB kimi davran)
                arg.setId(System.currentTimeMillis());
                return arg;
            });

        Order newOrder = Order.builder()
            .customerId("customer-1")
            .build();

        Order saved = orderRepository.save(newOrder);

        assertNotNull(saved.getId()); // thenAnswer ID set etdi
    }

    // ─── doReturn() — void metodlar üçün ─────────────
    @Test
    void doReturnExample() {
        // void metod stub-u:
        // when(emailService.send(...)).thenReturn() işləmir — void!
        doNothing().when(emailService).sendOrderConfirmation(any());

        // Ya da exception atmaq:
        doThrow(new EmailException("SMTP xətası"))
            .when(emailService).sendOrderConfirmation(any());

        assertThrows(EmailException.class,
            () -> emailService.sendOrderConfirmation(new Order()));
    }

    // ─── Gerçek metodu çağır ─────────────────────────
    @Test
    void thenCallRealMethodExample() {
        // Mock-da real implementasiya çağırılsın
        when(paymentService.calculateFee(any()))
            .thenCallRealMethod();
    }
}
```

---

## Verify — çağırışları yoxlamaq

```java
@ExtendWith(MockitoExtension.class)
class VerifyExamplesTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private EmailService emailService;

    @InjectMocks
    private OrderService orderService;

    @Test
    void shouldSaveOrderAndSendEmail() {
        when(orderRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));

        orderService.createOrder(validRequest());

        // save() bir dəfə çağırıldı
        verify(orderRepository).save(any(Order.class));
        verify(orderRepository, times(1)).save(any(Order.class));

        // email bir dəfə göndərildi
        verify(emailService).sendOrderConfirmation(any(Order.class));
    }

    @Test
    void shouldNotSendEmailWhenOrderFails() {
        when(orderRepository.save(any()))
            .thenThrow(new DataIntegrityViolationException("duplicate"));

        assertThrows(Exception.class,
            () -> orderService.createOrder(validRequest()));

        // Email heç vaxt göndərilməməlidir
        verify(emailService, never()).sendOrderConfirmation(any());
    }

    @Test
    void verifyCallCount() {
        orderService.processItems(List.of(item1(), item2(), item3()));

        // Tam 3 dəfə
        verify(orderRepository, times(3)).save(any());

        // Ən az 2 dəfə
        verify(orderRepository, atLeast(2)).save(any());

        // Ən çox 5 dəfə
        verify(orderRepository, atMost(5)).save(any());
    }

    @Test
    void verifyOrder() {
        orderService.createAndConfirmOrder(validRequest());

        // Çağırış sırası doğrudur?
        InOrder inOrder = inOrder(orderRepository, emailService);
        inOrder.verify(orderRepository).save(any()); // Əvvəlcə save
        inOrder.verify(emailService).sendOrderConfirmation(any()); // Sonra email
    }

    @Test
    void verifyNoMoreInteractions() {
        when(orderRepository.findById(1L)).thenReturn(Optional.of(order()));

        orderService.findOrder(1L);

        verify(orderRepository).findById(1L);

        // Başqa heç bir metod çağırılmamalı
        verifyNoMoreInteractions(orderRepository);
        verifyNoInteractions(emailService); // emailService heç çağırılmadı
    }

    // ─── ArgumentCaptor — çağırılan argumenti yoxla ──
    @Test
    void shouldSaveOrderWithCorrectStatus() {
        when(orderRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));

        orderService.createOrder(validRequest());

        ArgumentCaptor<Order> captor = ArgumentCaptor.forClass(Order.class);
        verify(orderRepository).save(captor.capture());

        Order capturedOrder = captor.getValue();
        assertEquals(OrderStatus.PENDING, capturedOrder.getStatus());
        assertNotNull(capturedOrder.getCreatedAt());
    }

    @Test
    void shouldSendEmailWithCorrectDetails() {
        when(orderRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));

        orderService.createOrder(validRequest());

        ArgumentCaptor<EmailRequest> captor = ArgumentCaptor.forClass(EmailRequest.class);
        verify(emailService).send(captor.capture());

        EmailRequest email = captor.getValue();
        assertEquals("order-confirmation@example.com", email.getFrom());
        assertTrue(email.getSubject().contains("Sifariş Təsdiqləndi"));
    }
}
```

---

## ArgumentMatchers

```java
class ArgumentMatchersTest {

    @Mock
    private OrderRepository orderRepository;

    @Test
    void exactValueMatcher() {
        when(orderRepository.findById(1L))
            .thenReturn(Optional.of(order()));

        // Yalnız 1L üçün işləyir
        orderRepository.findById(1L); // OK
        orderRepository.findById(2L); // → null (stub yoxdur)
    }

    @Test
    void wildcardMatchers() {
        // any() — istənilən argument
        when(orderRepository.save(any())).thenReturn(order());
        when(orderRepository.save(any(Order.class))).thenReturn(order());

        // anyLong(), anyString(), anyInt(), anyBoolean()
        when(orderRepository.findById(anyLong()))
            .thenReturn(Optional.of(order()));

        // isNull() / isNotNull()
        when(orderRepository.findByCustomerId(isNull()))
            .thenReturn(List.of());

        when(orderRepository.findByCustomerId(isNotNull()))
            .thenReturn(List.of(order()));
    }

    @Test
    void stringMatchers() {
        // contains, startsWith, endsWith, matches
        when(orderRepository.searchByNote(contains("urgent")))
            .thenReturn(List.of(urgentOrder()));

        when(orderRepository.searchByNote(startsWith("VIP")))
            .thenReturn(List.of(vipOrder()));

        when(orderRepository.searchByNote(matches("^[A-Z].*")))
            .thenReturn(List.of());
    }

    @Test
    void comparisonMatchers() {
        // gt, lt, geq, leq
        when(orderRepository.findByAmountGreaterThan(gt(100.0)))
            .thenReturn(List.of(bigOrder()));

        // Öz comparator-un ilə
        when(orderRepository.findByStatus(eq(OrderStatus.PENDING)))
            .thenReturn(List.of(pendingOrder()));
    }

    // ─── Qarışıq istifadə YANLIŞ ─────────────────────
    @Test
    void mixedMatchersWRONG() {
        // YANLIŞ: bəzi matcher, bəzi literal
        // when(repo.findByCustomerAndAmount("customer-1", gt(100.0)))
        //     .thenReturn(...); // MissingMethodInvocationException!
    }

    // ─── Qarışıq istifadə DOĞRU ──────────────────────
    @Test
    void mixedMatchersCORRECT() {
        // DOĞRU: hamısı matcher olmalı
        when(orderRepository.findByCustomerAndAmount(
                eq("customer-1"),
                gt(100.0)))
            .thenReturn(List.of(order()));
    }
}
```

---

## Spy — real obyekt izləmək

```java
class SpyExamplesTest {

    // ─── Spy vs Mock ──────────────────────────────────
    // Mock  → bütün metodlar stub, default qaytarır
    // Spy   → real metodlar çağırılır, istənilənlər stub edilə bilər

    @Test
    void spyExample() {
        List<String> realList = new ArrayList<>();
        List<String> spyList = spy(realList);

        // Real metod çağırılır
        spyList.add("element1");
        spyList.add("element2");

        assertEquals(2, spyList.size()); // Real size()

        // Bəzi metodları stub et
        doReturn(100).when(spyList).size();

        assertEquals(100, spyList.size()); // Stub nəticəsi

        verify(spyList).add("element1");
        verify(spyList).add("element2");
    }

    @Test
    void partialMockWithSpy() {
        OrderService realOrderService = new OrderService(
            mock(OrderRepository.class),
            mock(EmailService.class)
        );
        OrderService spyOrderService = spy(realOrderService);

        // Yalnız bu metodu stub et
        doReturn(true).when(spyOrderService).isUserEligible(any());

        // Digər metodlar real implementasiya ilə işləyir
        Order order = spyOrderService.createOrder(validRequest());
        assertNotNull(order);
    }

    // ─── @Spy annotasiyası ────────────────────────────
    @ExtendWith(MockitoExtension.class)
    class SpyAnnotationTest {

        @Spy
        private List<String> spyList = new ArrayList<>();

        @Test
        void shouldUseSpyList() {
            spyList.add("test");
            assertEquals(1, spyList.size()); // Real
        }
    }

    // ─── Spy ilə YANLIŞ istifadə ─────────────────────
    @Test
    void spyWrongUsage() {
        List<String> spyList = spy(new ArrayList<>());

        // YANLIŞ: real metoddan keç, stub qaytarsın
        // when(spyList.get(0)).thenReturn("hello"); // IndexOutOfBoundsException!

        // DOĞRU: doReturn istifadə et
        doReturn("hello").when(spyList).get(0);
        assertEquals("hello", spyList.get(0));
    }
}
```

---

## Annotation-based mocking

```java
// ─── @ExtendWith(MockitoExtension.class) ──────────────
@ExtendWith(MockitoExtension.class)
class MockitoAnnotationTest {

    @Mock
    private OrderRepository orderRepository;    // Interface mock

    @Mock
    private EmailService emailService;          // Concrete class mock

    @Spy
    private List<Order> orderList = new ArrayList<>(); // Real + izlənir

    @Captor
    private ArgumentCaptor<Order> orderCaptor;  // Argument captor

    @InjectMocks
    private OrderService orderService;          // Mock-ları inject et

    @Test
    void shouldInjectMocksCorrectly() {
        assertNotNull(orderRepository);
        assertNotNull(emailService);
        assertNotNull(orderService);
    }

    @Test
    void shouldCaptureWithAnnotation() {
        when(orderRepository.save(any())).thenAnswer(inv -> inv.getArgument(0));

        orderService.createOrder(validRequest());

        verify(orderRepository).save(orderCaptor.capture());
        assertEquals(OrderStatus.PENDING, orderCaptor.getValue().getStatus());
    }
}

// ─── @InjectMocks davranışı ───────────────────────────
// Constructor injection cəhd edir
// Setter injection cəhd edir
// Field injection (private) cəhd edir

// Prioritet: constructor → setter → field

class OrderService {
    private final OrderRepository repo;
    private final EmailService email;

    // Constructor injection — Mockito bunu istifadə edər
    public OrderService(OrderRepository repo, EmailService email) {
        this.repo = repo;
        this.email = email;
    }
}

// ─── MockitoAnnotations.openMocks() — manual init ────
class ManualAnnotationTest {

    @Mock
    private OrderRepository orderRepository;

    @InjectMocks
    private OrderService orderService;

    @BeforeEach
    void setUp() {
        // @ExtendWith olmadan — manual açmaq lazımdır
        MockitoAnnotations.openMocks(this);
    }
}
```

---

## İntervyu Sualları

### 1. Mock vs Spy fərqi nədir?
**Cavab:** `mock()` — obyektin bütün metodları stub olur; stubbing olmadan default qaytarır (null, 0, false, boş collection). `spy()` — real obyekti bürüyür; stubbing edilməmiş metodlar real implementasiyaya dəlir. Spy çox az istifadə edilir — real implementasiyanı test etmək istədikdə, bəzi metodları isə override etmək lazım olduqda.

### 2. when().thenReturn() vs doReturn().when() fərqi?
**Cavab:** `when(mock.method()).thenReturn(value)` — void metod üçün işləmir, spy-da real metod çağırılır (side effect ola bilər). `doReturn(value).when(mock).method()` — void metodlar üçün işləyir, spy-da real metod çağırılmır. Void metodlar üçün `doNothing()`, `doThrow()`, `doAnswer()` istifadə edilir.

### 3. ArgumentCaptor nə üçündür?
**Cavab:** Mock-a ötürülən argumentin dəyərini tutmaq üçün. `verify(repo).save(captor.capture())` → `captor.getValue()` ilə çağırılan argument əldə edilir. Mürəkkəb obyektlərin field-lərini yoxlamaq üçün faydalıdır (məs: save edilən Order-in status-unu yoxlamaq).

### 4. @InjectMocks necə işləyir?
**Cavab:** Mockito test sinfindəki `@Mock`/`@Spy` annotasiyalı sahələri `@InjectMocks` annotasiyalı obyektə inject edir. Birinci constructor injection cəhd edilir, sonra setter injection, ən sonda field injection. Constructor parametr sayı uyğun gəlməsə ya da mock bulamazsa injection uğursuz ola bilər — bu halda `NullPointerException` ya da gözlənilməz davranış olur.

### 5. verifyNoMoreInteractions() nə vaxt istifadə etmək lazımdır?
**Cavab:** Testin yerinə yetirdikdən sonra mock-da heç bir başqa çağırış olmadığını zəmanət etmək üçün. Hər testdə istifadə tövsiyə edilmir — testlər şişir və yeni kod əlavə edildikdə mövcud testlər kırılır. Yalnız kritik ssenarilərdə (məs: billing servisi yalnız bir dəfə çağırılmalıdır) faydalıdır.

*Son yenilənmə: 2026-04-10*
