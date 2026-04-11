# Testcontainers — Geniş İzah

## Mündəricat
1. [Testcontainers nədir?](#testcontainers-nədir)
2. [PostgreSQL Container](#postgresql-container)
3. [Kafka Container](#kafka-container)
4. [Redis Container](#redis-container)
5. [WireMock Container](#wiremock-container)
6. [Singleton Container Pattern](#singleton-container-pattern)
7. [Spring Boot Testcontainers Support](#spring-boot-testcontainers-support)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Testcontainers nədir?

**Testcontainers** — Java test-ləri üçün Docker container-larını proqramatik idarə edən kitabxana. Real infrastruktur (PostgreSQL, Kafka, Redis, S3, vb.) test zamanı avtomatik başlayır/dayanır.

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-testcontainers</artifactId>
    <scope>test</scope>
</dependency>
<dependency>
    <groupId>org.testcontainers</groupId>
    <artifactId>junit-jupiter</artifactId>
    <scope>test</scope>
</dependency>
<dependency>
    <groupId>org.testcontainers</groupId>
    <artifactId>postgresql</artifactId>
    <scope>test</scope>
</dependency>
<dependency>
    <groupId>org.testcontainers</groupId>
    <artifactId>kafka</artifactId>
    <scope>test</scope>
</dependency>
```

```
Testcontainers iş axışı:

Test başlayır
  → Docker image pull (ilk dəfə)
  → Container başlayır
  → Random port təyin edilir
  → Health check keçir
  → @DynamicPropertySource → Spring properties update
  → Testlər çalışır
  → Container dayanır (test bitdikdə)
```

---

## PostgreSQL Container

```java
// ─── Əl ilə idarə ────────────────────────────────────
@SpringBootTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
class PostgresManualTest {

    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15-alpine")
            .withDatabaseName("testdb")
            .withUsername("testuser")
            .withPassword("testpass")
            .withInitScript("init-schema.sql")  // Başlanğıc SQL
            .withReuse(true);                    // Container-ı yenidən istifadə et

    @BeforeAll
    static void startContainer() {
        postgres.start();
    }

    @AfterAll
    static void stopContainer() {
        postgres.stop();
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
    void shouldSaveToRealPostgres() {
        Order order = orderRepository.save(Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .totalAmount(new BigDecimal("149.99"))
            .build());

        assertNotNull(order.getId());
        assertTrue(order.getId() > 0);
    }
}

// ─── @Testcontainers + @Container annotasiyaları ─────
@SpringBootTest
@Testcontainers
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
class PostgresAnnotationTest {

    @Container  // JUnit 5 Extension avtomatik start/stop idarə edir
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15");

    @DynamicPropertySource
    static void configureProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username", postgres::getUsername);
        registry.add("spring.datasource.password", postgres::getPassword);
    }

    @Autowired
    private OrderRepository orderRepository;

    @Test
    @Sql("/test-data/sample-orders.sql")
    void shouldQueryWithRealPostgresFeatures() {
        // PostgreSQL-specific: JSON query, ARRAY, vb.
        List<Order> orders = orderRepository
            .findByMetadataContaining("\"priority\":\"high\"");

        assertFalse(orders.isEmpty());
    }
}

// ─── DataJpaTest + PostgreSQL ─────────────────────────
@DataJpaTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
@Testcontainers
class DataJpaWithPostgresTest {

    @Container
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15");

    @DynamicPropertySource
    static void configureProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username", postgres::getUsername);
        registry.add("spring.datasource.password", postgres::getPassword);
    }

    @Autowired
    private TestEntityManager entityManager;

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldWorkWithRealDatabase() {
        Order order = entityManager.persistAndFlush(
            Order.builder()
                .customerId("c1")
                .status(OrderStatus.PENDING)
                .build()
        );

        entityManager.clear();
        Order found = orderRepository.findById(order.getId()).orElseThrow();
        assertEquals("c1", found.getCustomerId());
    }
}
```

---

## Kafka Container

```java
@SpringBootTest
@Testcontainers
class KafkaContainerTest {

    @Container
    static KafkaContainer kafka = new KafkaContainer(
        DockerImageName.parse("confluentinc/cp-kafka:7.4.0"));

    @DynamicPropertySource
    static void configureKafka(DynamicPropertyRegistry registry) {
        registry.add("spring.kafka.bootstrap-servers", kafka::getBootstrapServers);
    }

    @Autowired
    private KafkaTemplate<String, String> kafkaTemplate;

    @Autowired
    private OrderEventConsumer orderEventConsumer;

    @Test
    void shouldProduceAndConsumeMessage() throws Exception {
        CountDownLatch latch = new CountDownLatch(1);
        String topic = "order-events";
        String message = "{\"orderId\":\"1\",\"status\":\"CREATED\"}";

        // Consumer-ı izlə
        orderEventConsumer.setLatch(latch);

        // Mesaj göndər
        kafkaTemplate.send(topic, "order-1", message).get(10, TimeUnit.SECONDS);

        // Consumer-ın almasını gözlə
        assertTrue(latch.await(30, TimeUnit.SECONDS));
        assertEquals(message, orderEventConsumer.getLastMessage());
    }

    @Test
    void shouldHandleOrderCreatedEvent() throws Exception {
        // Producer service-i test et
        OrderRequest request = validOrderRequest();

        orderService.createOrder(request); // Kafka-ya event göndərir

        // Consumer-ın emal etməsini gözlə
        await().atMost(30, SECONDS)
            .until(() -> orderEventConsumer.getProcessedCount() > 0);

        assertEquals(1, orderEventConsumer.getProcessedCount());
    }
}

// ─── Kafka Consumer test helper ───────────────────────
@Component
public class TestOrderEventConsumer {

    private CountDownLatch latch = new CountDownLatch(1);
    private String lastMessage;
    private int processedCount;

    @KafkaListener(topics = "order-events", groupId = "test-group")
    public void consume(ConsumerRecord<String, String> record) {
        this.lastMessage = record.value();
        this.processedCount++;
        latch.countDown();
    }

    public void setLatch(CountDownLatch latch) { this.latch = latch; }
    public String getLastMessage() { return lastMessage; }
    public int getProcessedCount() { return processedCount; }
}
```

---

## Redis Container

```java
@SpringBootTest
@Testcontainers
class RedisContainerTest {

    @Container
    static GenericContainer<?> redis = new GenericContainer<>(
        DockerImageName.parse("redis:7-alpine"))
        .withExposedPorts(6379);

    @DynamicPropertySource
    static void configureRedis(DynamicPropertyRegistry registry) {
        registry.add("spring.data.redis.host", redis::getHost);
        registry.add("spring.data.redis.port",
            () -> redis.getMappedPort(6379).toString());
    }

    @Autowired
    private StringRedisTemplate redisTemplate;

    @Autowired
    private OrderCacheService orderCacheService;

    @Test
    void shouldCacheOrder() {
        Order order = Order.builder()
            .id(1L)
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build();

        orderCacheService.cacheOrder(order);

        Optional<Order> cached = orderCacheService.getCachedOrder(1L);
        assertTrue(cached.isPresent());
        assertEquals(1L, cached.get().getId());
    }

    @Test
    void shouldExpireCache() throws InterruptedException {
        orderCacheService.cacheOrderWithTtl(order(), Duration.ofSeconds(1));

        Thread.sleep(1500);

        Optional<Order> expired = orderCacheService.getCachedOrder(1L);
        assertTrue(expired.isEmpty());
    }

    @Test
    void shouldIncrementCounter() {
        redisTemplate.opsForValue().set("order:count", "0");

        redisTemplate.opsForValue().increment("order:count");
        redisTemplate.opsForValue().increment("order:count");

        String count = redisTemplate.opsForValue().get("order:count");
        assertEquals("2", count);
    }
}
```

---

## WireMock Container

```java
// ─── WireMock — HTTP stub server ──────────────────────
@SpringBootTest
@Testcontainers
class WireMockContainerTest {

    @Container
    static WireMockContainer wireMock = new WireMockContainer("wiremock/wiremock:3.3.1")
        .withMappingFromResource("order-api-stubs.json");

    @DynamicPropertySource
    static void configureWireMock(DynamicPropertyRegistry registry) {
        registry.add("external.order-api.url", wireMock::getBaseUrl);
    }

    @Autowired
    private ExternalOrderApiClient apiClient;

    @Test
    void shouldCallExternalApiWithStub() {
        // Stub: GET /external/orders/1 → 200 OK
        wireMock.stubFor(get(urlEqualTo("/external/orders/1"))
            .willReturn(aResponse()
                .withStatus(200)
                .withHeader("Content-Type", "application/json")
                .withBody("""
                    {
                      "id": 1,
                      "externalId": "EXT-001",
                      "status": "DELIVERED"
                    }
                    """)));

        ExternalOrderDto order = apiClient.getExternalOrder(1L);

        assertEquals("EXT-001", order.externalId());
        assertEquals("DELIVERED", order.status());

        wireMock.verify(getRequestedFor(urlEqualTo("/external/orders/1")));
    }

    @Test
    void shouldRetry3TimesOnServerError() {
        // İlk 2 cəhd 500 qaytarır, 3-cü uğurlu
        wireMock.stubFor(get(urlEqualTo("/external/orders/2"))
            .inScenario("Retry")
            .whenScenarioStateIs(STARTED)
            .willReturn(serverError())
            .willSetStateTo("Second attempt"));

        wireMock.stubFor(get(urlEqualTo("/external/orders/2"))
            .inScenario("Retry")
            .whenScenarioStateIs("Second attempt")
            .willReturn(serverError())
            .willSetStateTo("Third attempt"));

        wireMock.stubFor(get(urlEqualTo("/external/orders/2"))
            .inScenario("Retry")
            .whenScenarioStateIs("Third attempt")
            .willReturn(ok().withBody("{\"id\":2,\"status\":\"PENDING\"}")));

        ExternalOrderDto order = apiClient.getExternalOrder(2L);

        assertEquals("PENDING", order.status());
        wireMock.verify(3, getRequestedFor(urlEqualTo("/external/orders/2")));
    }
}
```

---

## Singleton Container Pattern

```java
// ─── Bütün testlər eyni container-ı paylaşır ─────────
// Container hər test sinfi üçün yenidən başlamır — sürət++

public abstract class BaseIntegrationTest {

    // Static + withReuse(true) — JVM boyunca bir dəfə başlayır
    static final PostgreSQLContainer<?> POSTGRES;
    static final KafkaContainer KAFKA;
    static final GenericContainer<?> REDIS;

    static {
        POSTGRES = new PostgreSQLContainer<>("postgres:15")
            .withDatabaseName("integrationdb")
            .withReuse(true);

        KAFKA = new KafkaContainer(
            DockerImageName.parse("confluentinc/cp-kafka:7.4.0"))
            .withReuse(true);

        REDIS = new GenericContainer<>("redis:7-alpine")
            .withExposedPorts(6379)
            .withReuse(true);

        // Paralel başlat
        Startables.deepStart(POSTGRES, KAFKA, REDIS).join();
    }

    @DynamicPropertySource
    static void configureProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", POSTGRES::getJdbcUrl);
        registry.add("spring.datasource.username", POSTGRES::getUsername);
        registry.add("spring.datasource.password", POSTGRES::getPassword);
        registry.add("spring.kafka.bootstrap-servers", KAFKA::getBootstrapServers);
        registry.add("spring.data.redis.host", REDIS::getHost);
        registry.add("spring.data.redis.port",
            () -> REDIS.getMappedPort(6379).toString());
    }
}

// Test sinifləri extend edir
@SpringBootTest
class OrderServiceIntegrationTest extends BaseIntegrationTest {

    @Autowired
    private OrderService orderService;

    @Test
    void shouldCreateOrderWithFullStack() {
        // PostgreSQL + Kafka + Redis hamısı real
        Order order = orderService.createOrder(validRequest());
        assertNotNull(order.getId());
    }
}

@SpringBootTest
class PaymentServiceIntegrationTest extends BaseIntegrationTest {

    @Autowired
    private PaymentService paymentService;

    @Test
    void shouldProcessPayment() {
        // Eyni container-lar, yenidən başlamır
        PaymentResult result = paymentService.process(validPayment());
        assertNotNull(result.getTransactionId());
    }
}
```

---

## Spring Boot Testcontainers Support

```java
// ─── Spring Boot 3.1+ — @ServiceConnection ────────────
@SpringBootTest
@Testcontainers
class ServiceConnectionTest {

    @Container
    @ServiceConnection  // @DynamicPropertySource lazım deyil!
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15");

    @Container
    @ServiceConnection
    static KafkaContainer kafka = new KafkaContainer(
        DockerImageName.parse("confluentinc/cp-kafka:7.4.0"));

    @Container
    @ServiceConnection(name = "redis")
    static GenericContainer<?> redis =
        new GenericContainer<>("redis:7").withExposedPorts(6379);

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldWorkWithAutoConfiguration() {
        // Heç bir @DynamicPropertySource yoxdur
        // Spring Boot avtomatik connection properties set edir
        Order saved = orderRepository.save(Order.builder()
            .customerId("c1")
            .status(OrderStatus.PENDING)
            .build());

        assertNotNull(saved.getId());
    }
}

// ─── application.properties ilə Testcontainers ────────
// src/test/resources/application-testcontainers.yml:
//
// spring:
//   datasource:
//     url: jdbc:tc:postgresql:15:///testdb  # tc: prefix = Testcontainers JDBC URL
//     driver-class-name: org.testcontainers.jdbc.ContainerDatabaseDriver

@SpringBootTest
@ActiveProfiles("testcontainers")
class JdbcUrlTest {

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldUseContainerFromJdbcUrl() {
        // spring.datasource.url = jdbc:tc:postgresql:... → avtomatik container
        orderRepository.count();
    }
}
```

---

## İntervyu Sualları

### 1. Testcontainers nədir və nə üçün lazımdır?
**Cavab:** Docker container-larını JUnit test lifecycle-ına inteqrasiya edən Java kitabxanası. PostgreSQL, Kafka, Redis kimi infrastruktur servisləri test zamanı real Docker container-da çalışır. H2 kimi embedded DB-lərin limitlərini aşır — real DB funksionallığını (JSON operators, ARRAY, vb.) test edir. `@BeforeAll`/`@AfterAll` ilə avtomatik start/stop.

### 2. @ServiceConnection nə edir?
**Cavab:** Spring Boot 3.1+ xüsusiyyəti. `@Container` + `@ServiceConnection` annotasiyaları birlikdə — `@DynamicPropertySource` əl ilə yazmaq lazım deyil. Spring Boot container tipini (PostgreSQL, Redis, Kafka) tanıyır və connection properties-i avtomatik set edir. Daha az boilerplate, daha oxunaqlı test kodu.

### 3. Singleton Container Pattern nədir?
**Cavab:** Static container-lar bir dəfə başlayır və bütün test sinifləri ilə paylaşılır. Hər test sinfi üçün yeni container başlamaq yavaşdır — JVM lifetime boyunca bir container daha sürətlidir. `withReuse(true)` — hətta birdən çox test çalışdırmasında container-ı yenidən istifadə edir (Testcontainers daemon cache-i). `BaseIntegrationTest` miras ilə paylaşma ümumi pattern-dir.

### 4. Testcontainers-in dezavantajları?
**Cavab:** Docker tələb edir — CI/CD-də Docker daemon lazımdır. İlk dəfə image pull yavaş ola bilər. Container startup zamanı testlər yavaşlayır (H2-dən daha yavaş). Lokal Docker varsa işləyir, amma bəzi CI mühitlərindəki məhdudiyyətlər (nested Docker) problem yarada bilər. `withReuse(true)` ilə zamanı azaltmaq olar.

### 5. @DynamicPropertySource niyə lazımdır?
**Cavab:** Container random port ilə başlayır — əvvəlcədən məlum deyil. `@DynamicPropertySource` static metod test başlamadan əvvəl çağırılır, container-ın URL/port məlumatını Spring Environment-ə əlavə edir. `registry.add("spring.datasource.url", postgres::getJdbcUrl)` — Supplier kimi qeyd edilir; container başladıqdan sonra dəyər alınır. `@ServiceConnection` bu prosesi avtomatlaşdırır.

*Son yenilənmə: 2026-04-10*
