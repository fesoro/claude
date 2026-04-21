# Testing İdiomları (JUnit/Mockito vs PHPUnit/Pest)

## Giriş

Test yazmaq hər iki dildə mümkündür, amma "idiomatik test necə görünür?" sualına cavab fərqlidir. Java tərəfdə **JUnit 5** + **Mockito** + **AssertJ** + **Testcontainers** + **WireMock** standart stack-dir. PHP-də isə **PHPUnit** (klassik) və ya **Pest** (müasir, expressive) + **Mockery** + **Testcontainers-PHP** + **HTTP fake** istifadə olunur.

Bu fəsildə unit vs integration test, mock vs fake, test data factory/fixture, DB reset strategiyaları (transaction rollback vs truncation vs migrate-fresh) və snapshot test-lər müqayisə olunur.

---

## Java-da istifadəsi

### 1) JUnit 5 əsasları

```java
import org.junit.jupiter.api.*;
import static org.assertj.core.api.Assertions.*;

@DisplayName("Order hesablama xidməti")
class OrderCalculatorTest {

    private OrderCalculator calculator;

    @BeforeEach
    void setUp() {
        calculator = new OrderCalculator();
    }

    @Test
    @DisplayName("Boş sifariş sıfır qaytarır")
    void emptyOrderReturnsZero() {
        Order order = new Order(List.of());
        assertThat(calculator.total(order)).isZero();
    }

    @ParameterizedTest
    @CsvSource({
        "10.00, 2, 20.00",
        "15.50, 3, 46.50",
        "100.00, 0, 0.00"
    })
    void calculatesTotal(BigDecimal price, int qty, BigDecimal expected) {
        Order order = new Order(List.of(new Item("x", price, qty)));
        assertThat(calculator.total(order)).isEqualByComparingTo(expected);
    }

    @Test
    void throwsOnNullOrder() {
        assertThatThrownBy(() -> calculator.total(null))
            .isInstanceOf(IllegalArgumentException.class)
            .hasMessageContaining("null");
    }

    @Nested
    @DisplayName("Endirim tətbiqi")
    class Discount {
        @Test void percentageDiscount() { ... }
        @Test void fixedDiscount() { ... }
    }

    @Test
    @Timeout(value = 500, unit = TimeUnit.MILLISECONDS)
    void completesQuickly() { ... }

    @RepeatedTest(5)
    void runsFiveTimes() { ... }

    @Disabled("Flaky, bug #42")
    @Test
    void disabledForNow() { ... }
}
```

### 2) AssertJ — zəngin fluent assertion

```java
assertThat(user)
    .isNotNull()
    .extracting("email", "age")
    .containsExactly("a@b.com", 25);

assertThat(orders)
    .hasSize(3)
    .extracting(Order::getId)
    .containsExactlyInAnyOrder("A", "B", "C");

assertThat(result)
    .usingRecursiveComparison()
    .ignoringFields("createdAt", "id")
    .isEqualTo(expected);

assertThat(map)
    .containsEntry("key", "value")
    .doesNotContainKey("foo");
```

### 3) Mockito — mock framework

```java
@ExtendWith(MockitoExtension.class)
class UserServiceTest {

    @Mock UserRepository userRepo;
    @Mock EmailClient emailClient;
    @InjectMocks UserService service;

    @Captor ArgumentCaptor<User> userCaptor;

    @Test
    void registersUserAndSendsEmail() {
        // given
        when(userRepo.findByEmail("a@b.com")).thenReturn(Optional.empty());
        when(userRepo.save(any(User.class))).thenAnswer(inv -> {
            User u = inv.getArgument(0);
            u.setId(1L);
            return u;
        });

        // when
        User created = service.register("a@b.com", "John");

        // then
        verify(userRepo).save(userCaptor.capture());
        User saved = userCaptor.getValue();
        assertThat(saved.getEmail()).isEqualTo("a@b.com");

        verify(emailClient).sendWelcome("a@b.com");
        verifyNoMoreInteractions(emailClient);
    }

    @Test
    void throwsWhenEmailExists() {
        when(userRepo.findByEmail("a@b.com"))
            .thenReturn(Optional.of(new User()));

        assertThatThrownBy(() -> service.register("a@b.com", "John"))
            .isInstanceOf(DuplicateEmailException.class);

        verifyNoInteractions(emailClient);
    }

    @Test
    void retriesOnFailure() {
        when(emailClient.send(any()))
            .thenThrow(new IOException())
            .thenThrow(new IOException())
            .thenReturn(true);                 // 3-cüdə uğurlu

        service.sendWithRetry("msg");

        verify(emailClient, times(3)).send(any());
    }
}
```

### 4) Spy vs Mock vs Fake

```java
// Mock — hər şey null/default; when() ilə stub edirsən
UserRepo mock = mock(UserRepo.class);

// Spy — real obyekt, lazımı metodu stub edirsən
List<String> spy = spy(new ArrayList<>());
spy.add("real");
when(spy.size()).thenReturn(100);

// Fake — real implementasiya, sadələşdirilmiş (məs: in-memory DB)
class FakeUserRepo implements UserRepository {
    private final Map<Long, User> users = new HashMap<>();
    @Override public Optional<User> findById(Long id) { return Optional.ofNullable(users.get(id)); }
    @Override public User save(User u) { users.put(u.getId(), u); return u; }
}
```

### 5) Spring Boot integration test

```java
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
@AutoConfigureMockMvc
@Testcontainers
class OrderControllerIT {

    @Container
    static PostgreSQLContainer<?> postgres = new PostgreSQLContainer<>("postgres:16-alpine")
        .withDatabaseName("test")
        .withUsername("test")
        .withPassword("test");

    @DynamicPropertySource
    static void props(DynamicPropertyRegistry r) {
        r.add("spring.datasource.url", postgres::getJdbcUrl);
        r.add("spring.datasource.username", postgres::getUsername);
        r.add("spring.datasource.password", postgres::getPassword);
    }

    @Autowired MockMvc mvc;
    @Autowired OrderRepository orderRepo;

    @Test
    @Transactional                    // test sonunda rollback
    void createsOrder() throws Exception {
        mvc.perform(post("/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content("""
                    {"userId": 1, "items": [{"sku": "A", "qty": 2}]}
                """))
            .andExpect(status().isCreated())
            .andExpect(jsonPath("$.id").exists())
            .andExpect(jsonPath("$.status").value("PENDING"));

        assertThat(orderRepo.count()).isEqualTo(1);
    }
}
```

### 6) Testcontainers — real DB/Redis/Kafka

```java
@Testcontainers
class ProductRepositoryIT {

    @Container
    static PostgreSQLContainer<?> pg = new PostgreSQLContainer<>("postgres:16");

    @Container
    static GenericContainer<?> redis = new GenericContainer<>("redis:7-alpine")
        .withExposedPorts(6379);

    @Container
    static KafkaContainer kafka = new KafkaContainer(DockerImageName.parse("confluentinc/cp-kafka:7.5.0"));
}
```

Testcontainers real servisləri Docker-də qaldırır — sürətli və real-dır. **H2 in-memory** istifadə etməkdən fərqli olaraq, prod DB-də olan SQL feature-lər burada da var.

### 7) WireMock — xarici HTTP servisləri mock etmək

```java
@ExtendWith(WireMockExtension.class)
class PaymentClientTest {

    @RegisterExtension
    static WireMockExtension wm = WireMockExtension.newInstance()
        .options(wireMockConfig().dynamicPort())
        .build();

    @Test
    void handlesPaymentSuccess() {
        wm.stubFor(post(urlEqualTo("/charge"))
            .withHeader("Authorization", matching("Bearer .*"))
            .withRequestBody(matchingJsonPath("$.amount", equalTo("100")))
            .willReturn(aResponse()
                .withStatus(200)
                .withHeader("Content-Type", "application/json")
                .withBody("""
                    {"transactionId": "tx-123", "status": "SUCCESS"}
                """)));

        PaymentResult result = client.charge(100.0);

        assertThat(result.transactionId()).isEqualTo("tx-123");

        wm.verify(postRequestedFor(urlEqualTo("/charge"))
            .withRequestBody(matchingJsonPath("$.amount", equalTo("100"))));
    }

    @Test
    void retriesOn5xx() {
        wm.stubFor(post("/charge")
            .inScenario("retry")
            .whenScenarioStateIs(STARTED)
            .willReturn(aResponse().withStatus(500))
            .willSetStateTo("attempt-2"));

        wm.stubFor(post("/charge")
            .inScenario("retry")
            .whenScenarioStateIs("attempt-2")
            .willReturn(aResponse().withStatus(200).withBody("{\"ok\": true}")));

        assertThat(client.chargeWithRetry(100.0).isOk()).isTrue();
        wm.verify(2, postRequestedFor(urlEqualTo("/charge")));
    }
}
```

### 8) Data factory — test obyektləri

```java
// Manual factory
public class UserFactory {
    public static User defaultUser() {
        return new User(1L, "test@example.com", "John", "Doe", LocalDate.now());
    }

    public static User withEmail(String email) {
        User u = defaultUser();
        u.setEmail(email);
        return u;
    }
}

// Instancio və ya Podam ilə random data
User user = Instancio.create(User.class);

// Builder pattern
User u = User.builder().email("a@b.com").age(25).build();
```

### 9) DB reset — transaction rollback

```java
@SpringBootTest
@Transactional           // hər test sonu rollback — DB təmizlənir
class OrderServiceIT { ... }
```

---

## PHP-də istifadəsi

### 1) PHPUnit — klassik

```php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, DataProvider, Group};

class OrderCalculatorTest extends TestCase
{
    private OrderCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new OrderCalculator();
    }

    #[Test]
    public function empty_order_returns_zero(): void
    {
        $order = new Order([]);
        $this->assertSame(0.0, $this->calculator->total($order));
    }

    #[Test]
    #[DataProvider('provideOrders')]
    public function calculates_total(float $price, int $qty, float $expected): void
    {
        $order = new Order([new Item('x', $price, $qty)]);
        $this->assertSame($expected, $this->calculator->total($order));
    }

    public static function provideOrders(): array
    {
        return [
            [10.0, 2, 20.0],
            [15.5, 3, 46.5],
            [100.0, 0, 0.0],
        ];
    }

    #[Test]
    public function throws_on_null_order(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null');

        $this->calculator->total(null);
    }
}
```

### 2) Pest — müasir syntax

```php
// tests/Unit/OrderCalculatorTest.php
use App\OrderCalculator;

beforeEach(function () {
    $this->calc = new OrderCalculator();
});

it('returns zero for empty order', function () {
    expect($this->calc->total(new Order([])))->toBe(0.0);
});

it('calculates total correctly', function (float $price, int $qty, float $expected) {
    $order = new Order([new Item('x', $price, $qty)]);
    expect($this->calc->total($order))->toBe($expected);
})->with([
    [10.0, 2, 20.0],
    [15.5, 3, 46.5],
    [100.0, 0, 0.0],
]);

it('throws on null', function () {
    expect(fn() => $this->calc->total(null))
        ->toThrow(\InvalidArgumentException::class, 'null');
});

// Arch test (Pest 2 xüsusi)
arch('controllers extend base')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller');

arch('models have no dependencies on controllers')
    ->expect('App\Models')
    ->not->toUse('App\Http\Controllers');
```

### 3) Mockery — PHPUnit daha çevik mock

```php
use Mockery as m;

#[Test]
public function registers_user_and_sends_email(): void
{
    $repo = m::mock(UserRepository::class);
    $mailer = m::mock(EmailClient::class);

    $repo->shouldReceive('findByEmail')
        ->with('a@b.com')
        ->once()
        ->andReturn(null);

    $repo->shouldReceive('save')
        ->once()
        ->with(m::on(fn(User $u) => $u->email === 'a@b.com'))
        ->andReturnUsing(fn(User $u) => tap($u, fn($u) => $u->id = 1));

    $mailer->shouldReceive('sendWelcome')->once()->with('a@b.com');

    $service = new UserService($repo, $mailer);
    $user = $service->register('a@b.com', 'John');

    $this->assertSame(1, $user->id);
}

protected function tearDown(): void
{
    m::close();
}
```

### 4) Pest expectations

```php
expect($value)
    ->toBe($expected)                    // ===
    ->toEqual($expected)                 // ==
    ->toBeString()
    ->toBeInt()
    ->toBeArray()
    ->toBeInstanceOf(User::class)
    ->toBeNull()
    ->toBeTrue() / toBeFalse()
    ->toBeGreaterThan(5)
    ->toBeBetween(1, 10)
    ->toContain('substring')
    ->toHaveCount(3)
    ->toHaveKey('email')
    ->toMatchArray(['email' => 'a@b.com'])
    ->toMatchSnapshot();

// Chained
expect($user)
    ->id->toBe(1)
    ->email->toBe('a@b.com')
    ->createdAt->toBeInstanceOf(Carbon::class);

// Higher-order
expect($users)->each->toBeInstanceOf(User::class);
```

### 5) Laravel HTTP test

```php
it('creates an order', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'items' => [
            ['sku' => 'A', 'qty' => 2],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['id', 'status', 'total']]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});
```

### 6) Http::fake() — HTTP client mock

```php
use Illuminate\Support\Facades\Http;

it('handles payment success', function () {
    Http::fake([
        'payment.api/charge' => Http::response([
            'transactionId' => 'tx-123',
            'status' => 'SUCCESS',
        ], 200),
    ]);

    $result = app(PaymentService::class)->charge($order);

    expect($result->transactionId)->toBe('tx-123');

    Http::assertSent(fn ($request) =>
        $request->url() === 'https://payment.api/charge'
        && $request['amount'] === 100
    );
});

it('retries on 5xx', function () {
    Http::fake([
        'payment.api/charge' => Http::sequence()
            ->push(['error' => 'server'], 500)
            ->push(['error' => 'server'], 500)
            ->push(['ok' => true], 200),
    ]);

    $result = app(PaymentService::class)->chargeWithRetry($order);

    expect($result->isOk())->toBeTrue();
    Http::assertSentCount(3);
});
```

### 7) Factory və Seeder

```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('secret'),
            'created_at' => now(),
        ];
    }

    // State
    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}

// İstifadə
$users = User::factory()->count(10)->admin()->create();

// Relation ilə
$user = User::factory()
    ->has(Order::factory()->count(3))
    ->create();
```

### 8) DB reset strategiyaları (Laravel)

```php
// 1) RefreshDatabase — transaction rollback (ən sürətli, default)
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// 2) DatabaseTruncation — hər test arası truncate (transaction problemləri olanda)
uses(\Illuminate\Foundation\Testing\DatabaseTruncation::class);

// 3) DatabaseMigrations — hər test əvvəli migrate:fresh (ən yavaş, ən təmiz)
uses(\Illuminate\Foundation\Testing\DatabaseMigrations::class);

// 4) LazilyRefreshDatabase — yalnız DB-yə toxunan testlərdə refresh
uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

// 5) :memory: SQLite — sürətli, amma prod DB-dən fərqlidir
// phpunit.xml:
// <env name="DB_CONNECTION" value="sqlite"/>
// <env name="DB_DATABASE" value=":memory:"/>
```

### 9) Testcontainers-PHP (yeni)

```php
use Testcontainers\Container\PostgreSqlContainer;

beforeEach(function () {
    $this->postgres = (new PostgreSqlContainer('postgres:16'))
        ->withDatabase('test')
        ->withUsername('test')
        ->withPassword('test');
    $this->postgres->run();

    config(['database.connections.pgsql.host' => $this->postgres->getHost()]);
    config(['database.connections.pgsql.port' => $this->postgres->getMappedPort(5432)]);

    Artisan::call('migrate');
});

afterEach(function () {
    $this->postgres->stop();
});
```

Testcontainers-PHP Java-dakı qədər populyar deyil — Laravel icmasında adətən SQLite in-memory + production-də ayrı test DB istifadə olunur.

### 10) Snapshot test (Pest)

```php
it('generates invoice PDF metadata', function () {
    $invoice = Invoice::factory()->create(['total' => 100]);
    $metadata = InvoiceGenerator::metadata($invoice);

    expect($metadata)->toMatchSnapshot();
});
// İlk run: .pest/snapshots/InvoiceGenerator.json yaradılır
// Sonrakı run: fərq varsa test fail, `--update-snapshots` ilə yenilə
```

---

## Əsas fərqlər

| Xüsusiyyət | Java (JUnit/Mockito) | PHP (PHPUnit/Pest) |
|---|---|---|
| Test framework | JUnit 5 (de-facto) | PHPUnit (klassik) və ya Pest (müasir) |
| Syntax | `@Test`, class-based | Pest-də function-based (`it`, `expect`) |
| Assertion | AssertJ (fluent) | PHPUnit `assertX` və ya Pest `expect()` |
| Mocking | Mockito (`@Mock`, `when`, `verify`) | Mockery (`shouldReceive`), PHPUnit built-in |
| Parameterized | `@ParameterizedTest` + provider | Pest `with([...])`, PHPUnit `#[DataProvider]` |
| Integration test | `@SpringBootTest` + Testcontainers | Laravel HTTP test + factory |
| HTTP mock | WireMock (stub endpoint) | `Http::fake()` facade |
| Real DB | Testcontainers (Docker) | SQLite :memory: (sürət), Testcontainers-PHP (rear) |
| DB reset | `@Transactional` rollback | `RefreshDatabase` trait |
| Test data | Instancio, Podam, manual factory | Eloquent factories + Faker |
| Snapshot | Spock / approvaltests-java | Pest `toMatchSnapshot()` |
| Test speed | Orta (JVM warmup) | Sürətli (interpretator) |
| Parallel | JUnit 5 paralel | Pest `--parallel`, PHPUnit ParaTest |
| Mutation testing | PIT | Infection |
| Coverage | JaCoCo | PHPUnit + Xdebug/PCOV |

---

## Niyə belə fərqlər var?

**Java-nın type safety test-də üstünlüyü.** Java-da `when(userRepo.findById(1L))` — kompiler yoxlayır ki, `findById` mövcuddur, `Long` alır, `Optional<User>` qaytarır. PHP-də `$mock->shouldReceive('findById')` — string-dir, typo edə bilərsən.

**Pest-in expressive syntax seçimi.** Pest Ruby's RSpec-dən ilham aldı — `it('does X', ...)`, `describe`, `expect()->toBe()`. Bu, sənəd kimi oxunur. PHPUnit-dən məmnun olmayanlar Pest-ə keçir. Laravel icması Pest-i geniş qəbul edib.

**Fake vs mock fəlsəfəsi.** Java-da mock üstünlük təşkil edir (Mockito çox güclü). PHP-də isə Laravel "fake"-ləri təşviq edir: `Http::fake()`, `Queue::fake()`, `Event::fake()`, `Mail::fake()`, `Storage::fake()`. Fake-lər real behavior-un bir hissəsini simulyasiya edir — daha realistik.

**DB test strategiyası.** Java-da Testcontainers ilə real Postgres qaldırmaq standart — Docker hər kompüterdə var. Transaction rollback (`@Transactional`) `@SpringBootTest`-də populyar, amma async job-larda problem yaradır. PHP-də SQLite `:memory:` default — çünki sürətli, Docker tələb etmir. Amma SQLite JSON, fullText, window function, lateral join kimi Postgres/MySQL feature-lərini dəstəkləmir. Hybrid yanaşma: unit testlərdə SQLite, kritik integration testlərdə real DB.

**Factory ecosystem.** Laravel Eloquent factories zəngin: `User::factory()->has(Order::factory()->count(3))->create()`. Java-da belə deklarativ factory yoxdur — manual factory və ya Instancio istifadə olunur.

**Arch testing.** Pest 2-də `arch()` — "heç bir Model Controller-dən asılı olmasın" kimi arxitektura qaydaları test olunur. Java-da ArchUnit eyni şeyi edir, amma Pest-dəki daha sadə DSL.

**HTTP mock yanaşması.** WireMock server qaldırır (real port), Http::fake() isə sadəcə HTTP client-i intercept edir. WireMock daha realistikdir (retry, timeout simulyasiyası), amma Http::fake() sürətli və sadədir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- Mockito spy — real obyekti qismən mock etmək
- ArgumentCaptor — metod çağırış argumentini tutub yoxlamaq
- `@InjectMocks` — avtomatik mock inject etmək
- JUnit 5 `@Nested` — test struktur hierarchy
- Testcontainers üçün 50+ hazır module (Kafka, Elasticsearch, LocalStack...)
- WireMock scenario — state machine HTTP mock
- AssertJ `usingRecursiveComparison` — dərin obyekt müqayisəsi
- PIT mutation testing (çox sürətli)
- JMH — microbenchmark framework

**Yalnız PHP-də:**
- Pest `it()`/`describe()` syntax — oxuna bilən test
- Pest `arch()` — arxitektura qaydaları test etmək
- Laravel `Http::fake()`, `Queue::fake()`, `Event::fake()` — full framework mock
- `RefreshDatabase` trait — transaction əsaslı DB reset
- Eloquent factories — `User::factory()->has(Order::factory())`
- Pest `toMatchSnapshot()` — daxili snapshot test
- Pest dataset — `it(...)->with([...])`
- Laravel `assertDatabaseHas` — DB row yoxlaması
- `$this->actingAs($user)` — auth simulation
- Pint + PHPStan + Pest — birgə Laravel "developer toolkit"
