# Spring Boot Test — Geniş İzah

## Mündəricat
1. [@SpringBootTest — tam kontekst](#springboottest--tam-kontekst)
2. [Test dilimləri (Test Slices)](#test-dilimləri-test-slices)
3. [@MockBean və @SpyBean](#mockbean-və-spybean)
4. [TestRestTemplate və WebTestClient](#testresttemplate-və-webtestclient)
5. [Test konfiqurasiyası](#test-konfiqurasiyası)
6. [Test property-ləri](#test-property-ləri)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @SpringBootTest — tam kontekst

```java
// ─── @SpringBootTest — bütün ApplicationContext yüklənir ─
@SpringBootTest
class OrderServiceIntegrationTest {

    @Autowired
    private OrderService orderService;

    @Autowired
    private OrderRepository orderRepository;

    @Test
    @Transactional // Test sonunda rollback
    void shouldCreateOrderInDatabase() {
        OrderRequest request = new OrderRequest("customer-1", List.of(
            new OrderItem("product-1", 2, new BigDecimal("49.99"))
        ));

        Order order = orderService.createOrder(request);

        assertNotNull(order.getId());
        assertTrue(orderRepository.existsById(order.getId()));
    }
}

// ─── WebEnvironment seçimləri ────────────────────────
// NONE (default) — servlet container yoxdur, only ApplicationContext
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.NONE)
class ServiceLayerTest {
    @Autowired
    private OrderService orderService;
}

// RANDOM_PORT — real server, təsadüfi port
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
class FullIntegrationTest {

    @LocalServerPort
    private int port;

    @Autowired
    private TestRestTemplate restTemplate;

    @Test
    void shouldReturnOrders() {
        ResponseEntity<List> response = restTemplate
            .getForEntity("http://localhost:" + port + "/api/orders", List.class);

        assertEquals(HttpStatus.OK, response.getStatusCode());
    }
}

// DEFINED_PORT — application.yml-dəki port
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.DEFINED_PORT)
class DefinedPortTest { }

// MOCK — mock servlet environment (MockMvc üçün)
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.MOCK)
class MockServletTest { }
```

---

## Test dilimləri (Test Slices)

Spring Boot test dilimləri — yalnız lazım olan hissəni yüklər (daha sürətli).

```java
// ─── @WebMvcTest — yalnız MVC layer ─────────────────
@WebMvcTest(OrderController.class)
class OrderControllerTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean  // Spring context-ə mock bean əlavə edir
    private OrderService orderService;

    @Test
    void shouldReturnOrderById() throws Exception {
        Order order = Order.builder()
            .id(1L)
            .status(OrderStatus.PENDING)
            .build();

        when(orderService.findById(1L)).thenReturn(Optional.of(order));

        mockMvc.perform(get("/api/orders/1")
                .contentType(MediaType.APPLICATION_JSON))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.id").value(1))
            .andExpect(jsonPath("$.status").value("PENDING"));
    }
}

// ─── @DataJpaTest — yalnız JPA layer ─────────────────
@DataJpaTest
class OrderRepositoryTest {

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private TestEntityManager entityManager;

    @Test
    void shouldFindByCustomerId() {
        // Persist et
        Order order = Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build();
        entityManager.persistAndFlush(order);

        // Test
        List<Order> orders = orderRepository.findByCustomerId("customer-1");

        assertFalse(orders.isEmpty());
        assertEquals("customer-1", orders.get(0).getCustomerId());
    }
}

// ─── @JsonTest — JSON serialization/deserialization ──
@JsonTest
class OrderJsonTest {

    @Autowired
    private JacksonTester<Order> json;

    @Test
    void shouldSerializeOrder() throws Exception {
        Order order = Order.builder()
            .id(1L)
            .status(OrderStatus.PENDING)
            .build();

        JsonContent<Order> result = json.write(order);

        assertThat(result).extractingJsonPathNumberValue("$.id")
            .isEqualTo(1);
        assertThat(result).extractingJsonPathStringValue("$.status")
            .isEqualTo("PENDING");
    }

    @Test
    void shouldDeserializeOrder() throws Exception {
        String content = """
            {
              "id": 1,
              "customerId": "customer-1",
              "status": "PENDING"
            }
            """;

        Order order = json.parseObject(content);

        assertEquals(1L, order.getId());
        assertEquals("customer-1", order.getCustomerId());
    }
}

// ─── @RestClientTest — HTTP client testləri ──────────
@RestClientTest(OrderApiClient.class)
class OrderApiClientTest {

    @Autowired
    private OrderApiClient client;

    @Autowired
    private MockRestServiceServer server;

    @Test
    void shouldFetchOrderFromApi() throws Exception {
        server.expect(requestTo("/api/orders/1"))
            .andExpect(method(HttpMethod.GET))
            .andRespond(withSuccess(
                """
                {"id":1,"status":"PENDING"}
                """,
                MediaType.APPLICATION_JSON));

        Order order = client.getOrder(1L);

        assertEquals(1L, order.getId());
        assertEquals(OrderStatus.PENDING, order.getStatus());
    }
}
```

---

## @MockBean və @SpyBean

```java
// ─── @MockBean — real bean-i mock ilə əvəz et ────────
@SpringBootTest
class OrderServiceWithMockBeanTest {

    @MockBean  // PaymentService Spring bean-ini mock ilə əvəz edir
    private PaymentService paymentService;

    @MockBean
    private EmailService emailService;

    @Autowired
    private OrderService orderService;  // Real bean, amma mock dependency-lər ilə

    @Test
    void shouldCreateOrderWithMockedPayment() {
        when(paymentService.processPayment(any()))
            .thenReturn(PaymentResult.success("tx-123"));

        Order order = orderService.createOrder(validRequest());

        assertNotNull(order);
        verify(paymentService).processPayment(any());
        verify(emailService).sendConfirmation(any());
    }
}

// ─── @SpyBean — real bean-i izlə, bəzi metodları override et
@SpringBootTest
class OrderServiceWithSpyBeanTest {

    @SpyBean  // Real EmailService-i bürüyür
    private EmailService emailService;

    @Autowired
    private OrderService orderService;

    @Test
    void shouldVerifyEmailSent() {
        orderService.createOrder(validRequest());

        // Real email göndərildi? Yox (test), amma çağırış oldu?
        verify(emailService).sendConfirmation(any());
    }

    @Test
    void shouldNotSendEmailWhenDisabled() {
        // Yalnız bu test üçün override
        doNothing().when(emailService).sendConfirmation(any());

        orderService.createOrder(validRequest());

        verify(emailService).sendConfirmation(any()); // Çağırıldı amma heç nə etmədi
    }
}

// ─── @MockBean vs @Mock fərqi ─────────────────────────
// @Mock — Mockito; Spring context-i bilmir; @ExtendWith(MockitoExtension.class) ilə
// @MockBean — Spring Boot Test; Spring ApplicationContext-ə əlavə edilir;
//             ApplicationContext-i refresh edir (yavaş!)
//             @SpringBootTest ilə istifadə edilir
```

---

## TestRestTemplate və WebTestClient

```java
// ─── TestRestTemplate — synchronous, servlet container tələb edir
@SpringBootTest(webEnvironment = RANDOM_PORT)
class OrderApiTest {

    @Autowired
    private TestRestTemplate restTemplate;

    @Test
    void shouldCreateOrder() {
        OrderRequest request = new OrderRequest(
            "customer-1",
            List.of(new OrderItem("product-1", 2, new BigDecimal("49.99")))
        );

        ResponseEntity<OrderResponse> response = restTemplate.postForEntity(
            "/api/orders",
            request,
            OrderResponse.class
        );

        assertEquals(HttpStatus.CREATED, response.getStatusCode());
        assertNotNull(response.getBody());
        assertNotNull(response.getBody().id());
    }

    @Test
    void shouldGetOrderById() {
        OrderResponse response = restTemplate.getForObject(
            "/api/orders/{id}", OrderResponse.class, 1L);

        assertNotNull(response);
    }

    @Test
    void shouldReturn404ForMissingOrder() {
        ResponseEntity<ProblemDetail> response = restTemplate.getForEntity(
            "/api/orders/9999",
            ProblemDetail.class
        );

        assertEquals(HttpStatus.NOT_FOUND, response.getStatusCode());
    }

    // ─── Auth ilə TestRestTemplate ────────────────────
    @Test
    void shouldAuthenticateWithBasicAuth() {
        TestRestTemplate authTemplate = restTemplate.withBasicAuth("user", "password");

        ResponseEntity<String> response = authTemplate
            .getForEntity("/api/admin/orders", String.class);

        assertEquals(HttpStatus.OK, response.getStatusCode());
    }
}

// ─── WebTestClient — reactive, fluent API ─────────────
@SpringBootTest(webEnvironment = RANDOM_PORT)
class OrderApiReactiveTest {

    @Autowired
    private WebTestClient webTestClient;

    @Test
    void shouldCreateOrderFluently() {
        OrderRequest request = validOrderRequest();

        webTestClient.post()
            .uri("/api/orders")
            .contentType(MediaType.APPLICATION_JSON)
            .bodyValue(request)
            .exchange()
            .expectStatus().isCreated()
            .expectBody(OrderResponse.class)
            .value(response -> {
                assertNotNull(response.id());
                assertEquals("PENDING", response.status());
            });
    }

    @Test
    void shouldGetAllOrders() {
        webTestClient.get()
            .uri("/api/orders")
            .exchange()
            .expectStatus().isOk()
            .expectBodyList(OrderResponse.class)
            .hasSize(3);
    }

    @Test
    void shouldValidateErrorResponse() {
        webTestClient.post()
            .uri("/api/orders")
            .contentType(MediaType.APPLICATION_JSON)
            .bodyValue("{\"customerId\": null}")
            .exchange()
            .expectStatus().isBadRequest()
            .expectBody()
            .jsonPath("$.title").isEqualTo("Bad Request")
            .jsonPath("$.errors[0].field").isEqualTo("customerId");
    }
}
```

---

## Test konfiqurasiyası

```java
// ─── @TestConfiguration — test-only bean-lər ─────────
@TestConfiguration
public class TestConfig {

    @Bean
    @Primary  // Real bean-i override edir
    public EmailService testEmailService() {
        return new NoOpEmailService(); // Email göndərmir
    }

    @Bean
    public WireMockServer wireMockServer() {
        WireMockServer server = new WireMockServer(8089);
        server.start();
        return server;
    }
}

// İstifadə:
@SpringBootTest
@Import(TestConfig.class)
class OrderServiceTest {
    @Autowired
    private EmailService emailService; // → NoOpEmailService
}

// ─── @ContextConfiguration ───────────────────────────
@SpringBootTest
@ContextConfiguration(classes = {
    OrderService.class,
    OrderRepository.class,
    TestConfig.class
})
class SelectiveContextTest {
    // Yalnız seçilmiş bean-lər yüklənir
}

// ─── Test fixture sinifləri ───────────────────────────
public class OrderTestFixtures {

    public static OrderRequest validOrderRequest() {
        return new OrderRequest(
            "customer-1",
            List.of(
                new OrderItem("product-1", 2, new BigDecimal("49.99")),
                new OrderItem("product-2", 1, new BigDecimal("29.99"))
            )
        );
    }

    public static Order pendingOrder() {
        return Order.builder()
            .id(1L)
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .createdAt(Instant.now())
            .build();
    }

    public static Order confirmedOrder() {
        return pendingOrder().toBuilder()
            .status(OrderStatus.CONFIRMED)
            .build();
    }
}
```

---

## Test property-ləri

```java
// ─── application-test.yml ─────────────────────────────
// src/test/resources/application-test.yml

// application-test.yml:
// spring:
//   datasource:
//     url: jdbc:h2:mem:testdb
//     driver-class-name: org.h2.Driver
//   jpa:
//     hibernate:
//       ddl-auto: create-drop
//   kafka:
//     bootstrap-servers: localhost:9092
// external:
//   payment-service-url: http://localhost:8089

@SpringBootTest
@ActiveProfiles("test")
class ProfileTest {
    // application-test.yml yüklənir
}

// ─── @TestPropertySource ──────────────────────────────
@SpringBootTest
@TestPropertySource(properties = {
    "spring.datasource.url=jdbc:h2:mem:testdb",
    "external.payment-service.url=http://localhost:8089",
    "feature.new-checkout=true"
})
class PropertyOverrideTest {
    @Value("${feature.new-checkout}")
    private boolean newCheckout;

    @Test
    void shouldUseTestProperties() {
        assertTrue(newCheckout);
    }
}

// ─── @DynamicPropertySource — runtime property ────────
// TestContainers ilə birlikdə istifadə edilir
@SpringBootTest
class DynamicPropertyTest {

    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15")
            .withDatabaseName("testdb");

    @BeforeAll
    static void startContainer() {
        postgres.start();
    }

    @DynamicPropertySource
    static void configureProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username", postgres::getUsername);
        registry.add("spring.datasource.password", postgres::getPassword);
    }

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldConnectToRealDatabase() {
        Order saved = orderRepository.save(Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build());

        assertNotNull(saved.getId());
    }
}
```

---

## İntervyu Sualları

### 1. @SpringBootTest ilə unit test fərqi?
**Cavab:** Unit test — yalnız bir sinif, mock dependency-lər, ApplicationContext yoxdur, çox sürətli. `@SpringBootTest` — bütün ya da bir hissə ApplicationContext yüklənir, real dependency-lər (ya da `@MockBean`), daha yavaş (context startup). Test piramidası: çoxlu unit test, az `@SpringBootTest`. `@SpringBootTest` həqiqi inteqrasiya testləri, E2E ssenariləri üçündür.

### 2. Test dilimləri (@WebMvcTest, @DataJpaTest) niyə istifadə edilir?
**Cavab:** Tam ApplicationContext yükləmək yavaşdır. Test dilimləri yalnız lazım olan layer-i yükləyir: `@WebMvcTest` → controller + MVC konfigurasiya; `@DataJpaTest` → JPA + embedded DB; `@JsonTest` → Jackson. Bu, testləri sürətləndirir. Hər slicin `@MockBean` ilə çatışmayan dependency-lər əvəz edilir.

### 3. @MockBean vs @Mock fərqi?
**Cavab:** `@Mock` — Mockito annotation, Spring-dən müstəqil, `@ExtendWith(MockitoExtension.class)` ilə. `@MockBean` — Spring Boot annotation, Spring ApplicationContext-ə mock bean əlavə edir, mövcud bean-i override edir. `@MockBean` istifadəsi ApplicationContext-i yenidən yükləyir (yavaş) — buna görə mümkün olduqda `@Mock` üstün tutulur.

### 4. TestRestTemplate vs WebTestClient?
**Cavab:** `TestRestTemplate` — synchronous, servlet tabanlı (Tomcat), köhnə Spring MVC layihələr. `WebTestClient` — reactive, fluent API, həm servlet həm reactive stack-ı dəstəkləyir, daha güclü assertion API (`expectBody().jsonPath()`), Spring 5+. Yeni layihələrdə `WebTestClient` tövsiyə edilir.

### 5. @DynamicPropertySource nə üçündür?
**Cavab:** TestContainers kimi runtime-da başlayan infrastruktur üçün. Container başlayana qədər port/URL məlum deyil — `@DynamicPropertySource` bu məlumatı Spring'in property registry-sinə runtime-da əlavə edir. Alternativ: `@TestPropertySource` (statik), `application-test.yml` (statik). `@DynamicPropertySource` dinamik/random port vəziyyətlər üçündür.

*Son yenilənmə: 2026-04-10*
