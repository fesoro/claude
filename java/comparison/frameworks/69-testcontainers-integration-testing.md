# Testcontainers / Integration Testing — Spring vs Laravel

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Integration test — tətbiqi real infrastruktur (DB, cache, broker, external API) ilə ayaq-ayağa test etmək deməkdir. Uzun müddət bu çətin idi: mock-lara güvənmək lazım gəlirdi, ya da CI-da manual olaraq Postgres/Redis qaldırılırdı. **Testcontainers** (Java-da 2015-də çıxıb) bu problemi həll etdi — test başlayanda Docker container spin-up olunur, test bitəndə silinir. Bu yanaşma indi bütün ekosistemlərdə standartdır.

**Spring Boot**-da test primitive-ləri ətrafı zəngindir:
- `@SpringBootTest` — tam application context
- `@DataJpaTest`, `@WebMvcTest`, `@WebFluxTest`, `@JsonTest` — test slice-lar
- `@Testcontainers` + `@Container` + `@DynamicPropertySource` — real infrastructure
- `MockMvc`, `TestRestTemplate`, `WebTestClient` — HTTP
- `MockBean` (indi `@MockitoBean`), `@SpyBean`
- WireMock (external HTTP stub), ArchUnit (arxitektura yoxlama), Pitest (mutation testing)

**Laravel 11/12 + Pest 3**-də test ekosistemi sadə və Laravel-specific:
- `RefreshDatabase`, `DatabaseTransactions`, `DatabaseMigrations` trait-ləri
- Sail (Docker Compose) ilə MySQL/Postgres/Redis qaldırmaq
- SQLite in-memory çox sürətli unit test üçün
- `Http::fake()`, `Queue::fake()`, `Event::fake()`, `Storage::fake()`, `Mail::fake()`
- Pest 3 — test suite, datasets, architecture tests, mutation testing
- Eloquent factories + seeders

Bu sənəddə tam integration test quracayıq: API sorğu → service → DB → queue job → webhook (external API) — hər iki framework-də.

---

## Spring-də istifadəsi

### 1) Dependency

```xml
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-test</artifactId>
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
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-testcontainers</artifactId>
        <scope>test</scope>
    </dependency>
    <dependency>
        <groupId>com.github.tomakehurst</groupId>
        <artifactId>wiremock-jre8-standalone</artifactId>
        <scope>test</scope>
    </dependency>
    <dependency>
        <groupId>com.tngtech.archunit</groupId>
        <artifactId>archunit-junit5</artifactId>
        <scope>test</scope>
    </dependency>
    <dependency>
        <groupId>org.pitest</groupId>
        <artifactId>pitest-maven</artifactId>
        <scope>test</scope>
    </dependency>
</dependencies>

<build>
    <plugins>
        <plugin>
            <groupId>org.pitest</groupId>
            <artifactId>pitest-maven</artifactId>
            <version>1.17.0</version>
            <configuration>
                <targetClasses><param>com.example.*</param></targetClasses>
                <targetTests><param>com.example.*Test</param></targetTests>
                <mutationThreshold>75</mutationThreshold>
            </configuration>
        </plugin>
    </plugins>
</build>
```

### 2) Testcontainers base class

```java
// src/test/java/com/example/IntegrationTestBase.java
@Testcontainers
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
@ActiveProfiles("test")
public abstract class IntegrationTestBase {

    // Singleton — bütün test-lər arasında eyni container
    static final PostgreSQLContainer<?> POSTGRES = new PostgreSQLContainer<>("postgres:16-alpine")
        .withDatabaseName("testdb")
        .withUsername("test")
        .withPassword("test")
        .withReuse(true);

    static final GenericContainer<?> REDIS = new GenericContainer<>(DockerImageName.parse("redis:7-alpine"))
        .withExposedPorts(6379)
        .withReuse(true);

    static final KafkaContainer KAFKA = new KafkaContainer(DockerImageName.parse("confluentinc/cp-kafka:7.6.1"))
        .withReuse(true);

    static {
        POSTGRES.start();
        REDIS.start();
        KAFKA.start();
    }

    @DynamicPropertySource
    static void overrideProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", POSTGRES::getJdbcUrl);
        registry.add("spring.datasource.username", POSTGRES::getUsername);
        registry.add("spring.datasource.password", POSTGRES::getPassword);
        registry.add("spring.redis.host", REDIS::getHost);
        registry.add("spring.redis.port", () -> REDIS.getMappedPort(6379));
        registry.add("spring.kafka.bootstrap-servers", KAFKA::getBootstrapServers);
    }
}
```

`~/.testcontainers.properties`-də `testcontainers.reuse.enable=true` qoymaq tövsiyə olunur — container-lar test run-lar arasında yenidən istifadə olunur.

### 3) ServiceConnection — Spring Boot 3.1+ qısa yol

```java
@Testcontainers
@SpringBootTest(webEnvironment = RANDOM_PORT)
public abstract class IntegrationTestBase {

    @Container
    @ServiceConnection
    static final PostgreSQLContainer<?> POSTGRES = new PostgreSQLContainer<>("postgres:16-alpine");

    @Container
    @ServiceConnection(name = "redis")
    static final GenericContainer<?> REDIS = new GenericContainer<>(DockerImageName.parse("redis:7-alpine"))
        .withExposedPorts(6379);

    @Container
    @ServiceConnection
    static final KafkaContainer KAFKA = new KafkaContainer(DockerImageName.parse("confluentinc/cp-kafka:7.6.1"));
}
```

`@ServiceConnection` avtomatik `DynamicPropertySource` yazır.

### 4) Full integration test — API → DB → Queue

```java
class OrderControllerTest extends IntegrationTestBase {

    @Autowired
    private TestRestTemplate restTemplate;

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private KafkaTemplate<String, OrderEvent> kafkaTemplate;

    @MockitoBean
    private PaymentGatewayClient paymentGateway;

    @BeforeEach
    void cleanup() {
        orderRepository.deleteAll();
    }

    @Test
    void shouldCreateOrderAndPublishEvent() {
        // given
        when(paymentGateway.authorize(any())).thenReturn(PaymentResult.success("tx-123"));

        var request = new CreateOrderRequest(100L, new BigDecimal("99.99"), "USD");

        // when
        ResponseEntity<OrderResponse> response = restTemplate.postForEntity(
            "/orders", request, OrderResponse.class);

        // then — HTTP
        assertThat(response.getStatusCode()).isEqualTo(HttpStatus.CREATED);
        assertThat(response.getBody().id()).isNotNull();

        // then — DB
        Order saved = orderRepository.findById(response.getBody().id()).orElseThrow();
        assertThat(saved.getAmount()).isEqualByComparingTo("99.99");
        assertThat(saved.getStatus()).isEqualTo("CONFIRMED");

        // then — Kafka event published
        Awaitility.await()
            .atMost(Duration.ofSeconds(5))
            .untilAsserted(() -> {
                var consumer = createKafkaConsumer();
                ConsumerRecords<String, OrderEvent> records = consumer.poll(Duration.ofSeconds(1));
                assertThat(records).isNotEmpty();
                OrderEvent event = records.iterator().next().value();
                assertThat(event.orderId()).isEqualTo(saved.getId());
            });

        // then — external service called
        verify(paymentGateway).authorize(argThat(p -> p.amount().equals(new BigDecimal("99.99"))));
    }
}
```

### 5) Test slice — `@WebMvcTest`

Yalnız web layer test et (service/repository mock):

```java
@WebMvcTest(OrderController.class)
class OrderControllerSliceTest {

    @Autowired
    private MockMvc mockMvc;

    @MockitoBean
    private OrderService orderService;

    @Test
    void shouldReturn400ForInvalidInput() throws Exception {
        mockMvc.perform(post("/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content("""
                    { "customerId": null, "amount": -10 }
                    """))
            .andExpect(status().isBadRequest())
            .andExpect(jsonPath("$.errors.customerId").exists())
            .andExpect(jsonPath("$.errors.amount").exists());
    }

    @Test
    void shouldReturnOrder() throws Exception {
        when(orderService.findById(1L)).thenReturn(
            new OrderDto(1L, 100L, new BigDecimal("99.99"), "CONFIRMED"));

        mockMvc.perform(get("/orders/1"))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.id").value(1))
            .andExpect(jsonPath("$.status").value("CONFIRMED"));
    }
}
```

### 6) Test slice — `@DataJpaTest`

Yalnız JPA təsbəqəsini test et:

```java
@DataJpaTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
@Testcontainers
class OrderRepositoryTest {

    @Container
    @ServiceConnection
    static PostgreSQLContainer<?> postgres = new PostgreSQLContainer<>("postgres:16-alpine");

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private TestEntityManager em;

    @Test
    void shouldFindPendingOrdersOlderThan() {
        Order old = new Order(1L, new BigDecimal("50"), "USD");
        old.setCreatedAt(LocalDateTime.now().minusDays(10));
        old.setStatus("PENDING");
        em.persistAndFlush(old);

        Order recent = new Order(2L, new BigDecimal("60"), "USD");
        recent.setStatus("PENDING");
        em.persistAndFlush(recent);

        List<Order> found = orderRepository.findPendingOlderThan(LocalDateTime.now().minusDays(7));

        assertThat(found).hasSize(1);
        assertThat(found.get(0).getId()).isEqualTo(old.getId());
    }
}
```

### 7) Test slice — `@JsonTest`

JSON serialization testləri:

```java
@JsonTest
class OrderResponseJsonTest {

    @Autowired
    private JacksonTester<OrderResponse> json;

    @Test
    void shouldSerialize() throws Exception {
        var response = new OrderResponse(1L, 100L, new BigDecimal("99.99"), "USD");

        assertThat(json.write(response))
            .extractingJsonPathNumberValue("$.id").isEqualTo(1);
        assertThat(json.write(response))
            .extractingJsonPathStringValue("$.currency").isEqualTo("USD");
    }

    @Test
    void shouldDeserialize() throws Exception {
        String content = """
            {"id":1,"customerId":100,"amount":99.99,"currency":"USD"}
            """;

        assertThat(json.parseObject(content).amount()).isEqualByComparingTo("99.99");
    }
}
```

### 8) WireMock — external API stub

```java
@SpringBootTest(webEnvironment = RANDOM_PORT)
@AutoConfigureWireMock(port = 0)
class PaymentIntegrationTest extends IntegrationTestBase {

    @Value("${wiremock.server.port}")
    private int wireMockPort;

    @Autowired
    private PaymentGatewayClient paymentGateway;

    @DynamicPropertySource
    static void overridePaymentUrl(DynamicPropertyRegistry registry) {
        registry.add("payment.gateway.url", () -> "http://localhost:" + wireMockPort);
    }

    @Test
    void shouldCallPaymentGateway() {
        WireMock.stubFor(post(urlEqualTo("/v1/charge"))
            .willReturn(aResponse()
                .withStatus(200)
                .withHeader("Content-Type", "application/json")
                .withBody("""
                    {"transactionId":"tx-abc","status":"AUTHORIZED","amount":9999}
                    """)));

        PaymentResult result = paymentGateway.authorize(
            new PaymentRequest(new BigDecimal("99.99"), "USD", "card-123"));

        assertThat(result.transactionId()).isEqualTo("tx-abc");

        WireMock.verify(postRequestedFor(urlEqualTo("/v1/charge"))
            .withRequestBody(matchingJsonPath("$.amount", equalTo("99.99"))));
    }

    @Test
    void shouldRetryOn5xx() {
        WireMock.stubFor(post(urlEqualTo("/v1/charge"))
            .inScenario("retry")
            .whenScenarioStateIs(Scenario.STARTED)
            .willReturn(aResponse().withStatus(503))
            .willSetStateTo("second"));

        WireMock.stubFor(post(urlEqualTo("/v1/charge"))
            .inScenario("retry")
            .whenScenarioStateIs("second")
            .willReturn(okJson("""
                {"transactionId":"tx-def","status":"AUTHORIZED"}
                """)));

        PaymentResult result = paymentGateway.authorize(new PaymentRequest(...));
        assertThat(result.transactionId()).isEqualTo("tx-def");
    }
}
```

### 9) ArchUnit — arxitektura testləri

```java
@AnalyzeClasses(packages = "com.example", importOptions = ImportOption.DoNotIncludeTests.class)
class ArchitectureTest {

    @ArchTest
    static final ArchRule controllers_only_depend_on_services =
        classes().that().resideInAPackage("..controller..")
            .should().onlyDependOnClassesThat()
            .resideInAnyPackage("..controller..", "..service..", "..dto..", "java..", "org.springframework..");

    @ArchTest
    static final ArchRule services_do_not_depend_on_controllers =
        noClasses().that().resideInAPackage("..service..")
            .should().dependOnClassesThat().resideInAPackage("..controller..");

    @ArchTest
    static final ArchRule repositories_should_be_interfaces =
        classes().that().resideInAPackage("..repository..")
            .and().haveSimpleNameEndingWith("Repository")
            .should().beInterfaces();

    @ArchTest
    static final ArchRule no_generic_exceptions =
        noMethods().should().declareThrowableOfType(Exception.class);

    @ArchTest
    static final ArchRule hexagonal_layers =
        layeredArchitecture()
            .consideringAllDependencies()
            .layer("Controller").definedBy("..controller..")
            .layer("Service").definedBy("..service..")
            .layer("Repository").definedBy("..repository..")
            .whereLayer("Controller").mayNotBeAccessedByAnyLayer()
            .whereLayer("Service").mayOnlyBeAccessedByLayers("Controller")
            .whereLayer("Repository").mayOnlyBeAccessedByLayers("Service");
}
```

### 10) Pitest — mutation testing

```bash
./mvnw test pitest:mutationCoverage
```

Pitest kodu "mutate" edir (operator-ləri dəyişir, şərtləri tərs çevirir) və testlərin bu mutasiyaları tutub-tutmadığını yoxlayır. Nəticə: `target/pit-reports/index.html`.

### 11) Testing profil və konfigurasiya

```yaml
# src/test/resources/application-test.yml
spring:
  jpa:
    hibernate:
      ddl-auto: create-drop
    show-sql: false
  flyway:
    enabled: true
    locations: classpath:db/migration,classpath:db/testdata
  kafka:
    consumer:
      group-id: test-${random.uuid}
logging:
  level:
    org.hibernate.SQL: WARN
    com.example: DEBUG
```

---

## Laravel-də istifadəsi

### 1) composer və Pest

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.5",
        "pestphp/pest-plugin-laravel": "^3.0",
        "pestphp/pest-plugin-arch": "^3.0",
        "pestphp/pest-plugin-mutate": "^3.0",
        "laravel/sail": "^1.30",
        "mockery/mockery": "^1.6",
        "fakerphp/faker": "^1.23"
    }
}
```

```bash
composer require pestphp/pest --dev --with-all-dependencies
php artisan pest:install
```

### 2) Pest konfigurasiyası

```php
// tests/Pest.php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)->in('Feature');

expect()->extend('toBeActive', fn () => $this->toBe(true));

// Helpers
function login(?User $user = null): User
{
    $user = $user ?? User::factory()->create();
    test()->actingAs($user);
    return $user;
}
```

```xml
<!-- phpunit.xml -->
<phpunit>
    <testsuites>
        <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
        <testsuite name="Feature"><directory>tests/Feature</directory></testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

### 3) Sail + Docker Compose — Testcontainers alternativi

Laravel Sail Docker Compose-u istifadə edir — real MySQL/Postgres/Redis test üçün:

```yaml
# docker-compose.testing.yml
services:
  mysql-test:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: testing
    ports:
      - "33061:3306"
    tmpfs:
      - /var/lib/mysql      # RAM-da — sürətli
  redis-test:
    image: redis:7-alpine
    ports:
      - "63791:6379"
```

```bash
docker compose -f docker-compose.testing.yml up -d
APP_ENV=testing php artisan test
```

### 4) Full feature test — API → Service → DB → Queue

```php
// tests/Feature/OrderTest.php
use App\Jobs\ProcessOrderWebhook;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('creates order, charges payment, and queues webhook', function () {
    // Arrange
    $user = login();

    Queue::fake();
    Http::fake([
        'https://payments.example.com/v1/charge' => Http::response([
            'transactionId' => 'tx-123',
            'status' => 'AUTHORIZED',
        ], 200),
    ]);

    // Act
    $response = $this->postJson('/api/orders', [
        'customer_id' => $user->id,
        'amount' => 99.99,
        'currency' => 'USD',
    ]);

    // Assert — HTTP
    $response->assertCreated()
        ->assertJsonStructure(['id', 'status', 'amount'])
        ->assertJson(['status' => 'CONFIRMED']);

    // Assert — DB
    $this->assertDatabaseHas('orders', [
        'customer_id' => $user->id,
        'amount' => 9999,                  // cents
        'currency' => 'USD',
        'status' => 'CONFIRMED',
    ]);

    $order = Order::latest()->first();
    expect($order->transaction_id)->toBe('tx-123');

    // Assert — external HTTP called
    Http::assertSent(fn ($request) =>
        $request->url() === 'https://payments.example.com/v1/charge'
        && $request['amount'] === 99.99
    );

    // Assert — Queue job dispatched
    Queue::assertPushed(ProcessOrderWebhook::class, fn ($job) =>
        $job->orderId === $order->id
    );
});

it('validates input', function () {
    login();

    $this->postJson('/api/orders', ['amount' => -10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'amount', 'currency']);
});

it('requires authentication', function () {
    $this->postJson('/api/orders', [])
        ->assertUnauthorized();
});
```

### 5) Unit test

```php
// tests/Unit/OrderPriceCalculatorTest.php
use App\Services\OrderPriceCalculator;

beforeEach(function () {
    $this->calculator = new OrderPriceCalculator();
});

it('calculates total with tax', function () {
    $result = $this->calculator->calculate(
        subtotal: 100.00,
        taxRate: 0.20,
        discount: 10.00
    );

    expect($result->subtotal)->toBe(100.00)
        ->and($result->tax)->toBe(18.00)       // (100 - 10) * 0.20
        ->and($result->total)->toBe(108.00);
});

dataset('invalid_prices', [
    'negative subtotal' => [-10, 0.20, 0, 'subtotal must be non-negative'],
    'tax over 1' => [100, 1.5, 0, 'tax rate must be between 0 and 1'],
    'discount over subtotal' => [50, 0.20, 100, 'discount exceeds subtotal'],
]);

it('rejects invalid input', function ($subtotal, $tax, $discount, $expected) {
    expect(fn () => $this->calculator->calculate($subtotal, $tax, $discount))
        ->toThrow(InvalidArgumentException::class, $expected);
})->with('invalid_prices');
```

### 6) Mail fake

```php
use App\Mail\OrderConfirmed;
use Illuminate\Support\Facades\Mail;

it('sends confirmation email', function () {
    Mail::fake();
    $user = login();

    $this->postJson('/api/orders', [/*...*/])->assertCreated();

    Mail::assertSent(OrderConfirmed::class, fn ($mail) =>
        $mail->hasTo($user->email)
        && $mail->order->customer_id === $user->id
    );
});
```

### 7) Event fake

```php
use App\Events\OrderCreated;
use Illuminate\Support\Facades\Event;

it('fires OrderCreated event', function () {
    Event::fake([OrderCreated::class]);
    login();

    $this->postJson('/api/orders', [/*...*/]);

    Event::assertDispatched(OrderCreated::class, fn ($e) => $e->order->amount === 9999);
});
```

### 8) Storage fake

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads avatar', function () {
    Storage::fake('s3');
    login();

    $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    $this->postJson('/api/user/avatar', ['avatar' => $file])->assertOk();

    Storage::disk('s3')->assertExists("avatars/{$file->hashName()}");
});
```

### 9) HTTP client fake (external API)

```php
use Illuminate\Support\Facades\Http;

it('retries on 5xx', function () {
    Http::fakeSequence()
        ->push(['error' => 'server down'], 503)
        ->push(['error' => 'server down'], 503)
        ->push(['transactionId' => 'tx-retry'], 200);

    $result = app(PaymentGateway::class)->charge(99.99, 'USD');

    expect($result->transactionId)->toBe('tx-retry');
    Http::assertSentCount(3);
});

it('handles network errors', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('timeout');
    });

    expect(fn () => app(PaymentGateway::class)->charge(99.99, 'USD'))
        ->toThrow(PaymentFailedException::class);
});
```

### 10) Database — migrations və factories

```php
// database/factories/OrderFactory.php
namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'USD',
            'status' => 'PENDING',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'CONFIRMED']);
    }

    public function forCustomer(User $user): static
    {
        return $this->state(fn () => ['customer_id' => $user->id]);
    }
}

// Istifadə
$orders = Order::factory()->count(10)->confirmed()->for(User::factory())->create();
```

### 11) Architecture test — Pest Arch

```php
// tests/Architecture/LayerTest.php
arch('controllers do not depend on models')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Models');

arch('services are final')
    ->expect('App\Services')
    ->toBeFinal();

arch('no dd or dump in production code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('no facades in services')
    ->expect('App\Services')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Auth',
    ]);

arch('presets')
    ->preset()
    ->laravel()      // Laravel-specific rules
    ->security()     // security rules (no mt_rand, no eval)
    ->php();         // php best practices
```

### 12) Mutation testing — Pest Mutate

```bash
# .pest/mutate.config.php
return [
    'paths' => ['app/Services', 'app/Domain'],
    'min_msi' => 75,
    'min_covered_msi' => 80,
];
```

```bash
./vendor/bin/pest --mutate --coverage
```

Pest Mutate PHP kodu dəyişdirir (+ → -, > → >=, true → false) və test-lərin bu mutasiyaları tutub-tutmadığını hesablayır. MSI (Mutation Score Indicator) çıxır.

### 13) Integration test — Postgres + Redis (Sail ilə)

```php
// phpunit.xml-də override
<php>
    <env name="DB_CONNECTION" value="pgsql"/>
    <env name="DB_HOST" value="127.0.0.1"/>
    <env name="DB_PORT" value="55432"/>
    <env name="DB_DATABASE" value="testing"/>
    <env name="REDIS_HOST" value="127.0.0.1"/>
    <env name="REDIS_PORT" value="63791"/>
</php>
```

```bash
docker compose -f docker-compose.testing.yml up -d
php artisan migrate --env=testing
./vendor/bin/pest --testsuite=Feature
```

### 14) Parallel testing

```bash
# Laravel 12 — paratest inteqrasiyası
php artisan test --parallel --processes=4
```

Laravel avtomatik hər process üçün ayrı DB yaradır (`testing_1`, `testing_2` ...).

### 15) Eloquent assertion helpers

```php
$this->assertDatabaseHas('orders', ['status' => 'CONFIRMED']);
$this->assertDatabaseMissing('orders', ['status' => 'CANCELLED']);
$this->assertDatabaseCount('orders', 5);
$this->assertModelExists($order);
$this->assertModelMissing($order->refresh());
$this->assertSoftDeleted($order);
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Boot | Laravel + Pest |
|---|---|---|
| Real DB strategy | Testcontainers + `@ServiceConnection` | Sail/Docker Compose manual + SQLite in-memory |
| Test slice | `@WebMvcTest`, `@DataJpaTest`, `@JsonTest` | Feature vs Unit directory |
| HTTP test | `MockMvc`, `TestRestTemplate`, `WebTestClient` | `$this->postJson()` |
| External API stub | WireMock | `Http::fake()` |
| Queue stub | Manual (TaskExecutor override) | `Queue::fake()` |
| Mail stub | `@MockBean` + manual verify | `Mail::fake()` |
| Event stub | `ApplicationEventPublisher` mock | `Event::fake()` |
| Storage stub | Manual | `Storage::fake()` |
| Architecture test | ArchUnit | Pest Arch |
| Mutation test | Pitest | Pest Mutate |
| Parallel test | `maven-surefire` forkCount | `--parallel` flag |
| Factory | Manual builder | Eloquent Factory + faker |
| Data seeding | Flyway migration + test data | Seeder + Factory |
| Assertion | AssertJ fluent | Pest `expect()` fluent |
| Fixture reuse | `@TestConfiguration`, beans | `beforeEach()`, datasets |
| Container reuse | `withReuse(true)` + `.testcontainers.properties` | Docker Compose persistent |
| DI container in test | Autowired | App container via `app()` |

---

## Niyə belə fərqlər var?

**Container lifetime.** JVM proses uzun olduğuna görə Testcontainers container-ı test runtime boyunca saxlayır — `@Container` static sahə bütün test class-lar arasında paylaşılır. PHP-də CLI per-process modelinə görə hər test run-a yeni container spin-up etmək bahadır — bu səbəbdən Laravel-də Sail/Docker Compose manual qaldırılır, sonra bütün testlər həmin infrastruktura hit edir.

**In-memory DB ilə sürət.** Laravel SQLite in-memory imkanı geniş istifadə edir — milyonlarla test dəqiqələrlə çalışır. Amma SQLite Postgres-dən fərqlidir (JSON, window function, ENUM tipi fərqli). Production Postgres istifadə edirsə, Feature test-lərdə Postgres vacibdir. Spring-də Testcontainers ilə hər zaman real Postgres işlənir — bu "daha dəqiq" amma "daha yavaş"dır.

**Fake helpers fəlsəfəsi.** Laravel `Http::fake()`, `Queue::fake()`, `Mail::fake()` — hamısı static class override ilə işləyir. Çox asan, çox yalnız Laravel-də mümkündür (çünki PHP-də reflect etmək asan). Spring-də `@MockBean` ilə bean override olunur — eyni məqsəd, amma daha çox boilerplate.

**Test slice.** Spring `@WebMvcTest`, `@DataJpaTest` ilə yalnız lazımi bean-ləri yükləyir — context boot zamanı azalır. Laravel-də belə konsept yoxdur: hər test tam application boot edir. Amma PHP-nin `require_once` mexanizmi sürətli olduğuna görə bu məsələ olmur.

**Architecture testing.** Java-da ArchUnit 2017-dən bəri standartdır — böyük team-lərdə layering, cycle detection. Pest Arch 2023-də gəldi, hələ gəncdir amma Laravel-specific preset-lərlə güclüdür.

**Mutation testing.** Pitest JVM bytecode-u dəyişdirir — çox sürətlidir. Pest Mutate PHP source-u AST səviyyəsində dəyişir — daha yavaş amma sadə deploy. Hər ikisi eyni məqsəd.

**Factory vs Builder.** Laravel Eloquent Factory daha deklarativdir (`Order::factory()->count(5)->confirmed()->create()`). Spring-də manual builder və ya [Instancio](https://www.instancio.org/) kimi 3rd party istifadə olunur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Boot-da:**
- `@Testcontainers` + `@ServiceConnection` avtomatik wiring
- Test slice annotasiyaları (`@WebMvcTest`, `@DataJpaTest`, `@WebFluxTest`, `@JsonTest`)
- `MockMvc` fluent HTTP test
- `WebTestClient` reactive test
- ArchUnit zəngin API (layered, cyclic, naming)
- Pitest mutation testing (JVM bytecode)
- `@TestConfiguration` bean override
- WireMock scenario-based stubbing

**Yalnız Laravel/Pest-də:**
- `Http::fake()`, `Queue::fake()`, `Mail::fake()`, `Storage::fake()`, `Event::fake()` — sadə fake helpers
- Eloquent Factory state-ləri (`.confirmed()`, `.forCustomer()`)
- `RefreshDatabase` trait (transaction və ya migrate fresh)
- Pest `dataset()` ilə parametrized testlər
- Pest Arch preset-lər (`laravel()`, `security()`)
- `assertDatabaseHas`, `assertModelExists` helpers
- Parallel testing avtomatik DB cloning
- `$this->graphQL()`, `$this->artisan()` helpers
- Faker built-in factory-də

---

## Best Practices

**Spring Boot üçün:**
- Testcontainers singleton qur — container reuse et
- `@ServiceConnection` istifadə et (Spring Boot 3.1+)
- Feature-cücü test-lərdə `@SpringBootTest(RANDOM_PORT)` + TestRestTemplate
- Unit test-lərdə `@WebMvcTest` və ya `@DataJpaTest` slice-lər
- WireMock scenario ilə retry/circuit breaker test et
- ArchUnit bütün layihədə layered rule yaz
- Pitest CI-da 75%+ mutation threshold qoy
- `@TestExecutionListeners` ilə global setup qur

**Laravel/Pest üçün:**
- `RefreshDatabase` trait feature test üçün; unit test DB tələb etmir
- In-memory SQLite — yalnız sadə CRUD test üçün; kompleks sorğular üçün real DB
- `Http::fake()` her external API çağırışını mock et
- `Queue::fake()` job dispatch-i yoxla, amma job logic-ini unit test-də ayrıca test et
- Pest `dataset()` boilerplate-i azal
- Parallel testing: `php artisan test --parallel --processes=4`
- Arch preset-lərini (`laravel()`, `security()`) enable et
- Mutation testing yalnız domain logic üçün işlət — UI/boilerplate üçün deyil
- Factory-ləri state method-larla zəngin et

---

## Yekun

Spring Boot test ekosistemi çox geniş və explicit-dir: Testcontainers + test slice annotations + MockMvc + WireMock + ArchUnit + Pitest birlikdə enterprise-grade test pipeline qurur. Real infrastruktura qarşı test etmək standartdır — SQLite kimi in-memory DB tətbiq olunmur.

Laravel + Pest test ekosistemi sadə, deklarativ və Laravel-specific-dir. `Http::fake()`, `Queue::fake()`, Eloquent Factory — hamısı minimum boilerplate ilə test yazmaq imkanı verir. SQLite in-memory unit test-lər üçün çox sürətlidir; real infrastructure isə Sail/Docker Compose ilə qalxır. Pest 3 ilə architecture və mutation testing də artıq birinci dərəcəli.

Qısa qayda: **Spring-də Testcontainers hər zaman real DB ilə test edir — daha dəqiq, daha yavaş; Laravel-də SQLite in-memory + fake() ilə sürətli unit/feature test, real infra yalnız lazım olanda. Hər iki yanaşma production-ready, fəlsəfə fərqlidir.**
