# Testing (Test yazmaq)

> **Seviyye:** Intermediate ⭐⭐

## Giris

Test yazmaq muasir proqram teminatinin keyfiyyet temin etmenin en vacib usullarindan biridir. Testler koddaki xetalari erkenden tapir, refactoring zamani mohkemlik verir ve dokumentasiya rolu oynayir.

Spring ekosisteminde JUnit 5, MockMvc, `@SpringBootTest`, `@MockBean` kimi vasiteler istifade olunur. Laravel-de ise PHPUnit uzerinde qurulmus Feature ve Unit testler, `RefreshDatabase`, mocking imkanlari ve browser testleri ucun Dusk istifade olunur.

## Spring-de istifadesi

### Unit Test (JUnit 5)

Xarici asılılıqlar olmadan sinifin mentiqini test etmek:

```java
// src/test/java/com/example/service/PriceCalculatorTest.java
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.CsvSource;

import static org.junit.jupiter.api.Assertions.*;

class PriceCalculatorTest {

    private PriceCalculator calculator;

    @BeforeEach
    void setUp() {
        calculator = new PriceCalculator();
    }

    @Test
    @DisplayName("Endirim olmadan qiymeti duzgun hesablamali")
    void shouldCalculatePriceWithoutDiscount() {
        BigDecimal result = calculator.calculate(
            new BigDecimal("100.00"), 0);
        assertEquals(new BigDecimal("100.00"), result);
    }

    @Test
    @DisplayName("10% endirimle qiymeti duzgun hesablamali")
    void shouldApplyDiscountCorrectly() {
        BigDecimal result = calculator.calculate(
            new BigDecimal("100.00"), 10);
        assertEquals(new BigDecimal("90.00"), result);
    }

    @Test
    @DisplayName("Menfi qiymet exception atmalı")
    void shouldThrowExceptionForNegativePrice() {
        assertThrows(IllegalArgumentException.class, () ->
            calculator.calculate(new BigDecimal("-10"), 0));
    }

    @ParameterizedTest
    @CsvSource({
        "100.00, 10, 90.00",
        "200.00, 25, 150.00",
        "50.00,  0,  50.00",
        "100.00, 100, 0.00"
    })
    @DisplayName("Muxtelif qiymet ve endirim kombinasiyalari")
    void shouldCalculateCorrectly(
            String price, int discount, String expected) {
        BigDecimal result = calculator.calculate(
            new BigDecimal(price), discount);
        assertEquals(new BigDecimal(expected), result);
    }
}
```

### Mockito ile Unit Test

```java
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.extension.ExtendWith;
import org.mockito.InjectMocks;
import org.mockito.Mock;
import org.mockito.junit.jupiter.MockitoExtension;

import static org.mockito.Mockito.*;
import static org.junit.jupiter.api.Assertions.*;

@ExtendWith(MockitoExtension.class)
class OrderServiceTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private PaymentService paymentService;

    @Mock
    private EmailService emailService;

    @InjectMocks
    private OrderService orderService;

    @Test
    @DisplayName("Sifaris ugurla yaradilmali")
    void shouldCreateOrderSuccessfully() {
        // Arrange (Hazirliq)
        OrderDto dto = new OrderDto("user-1", List.of(
            new OrderItemDto("prod-1", 2, new BigDecimal("25.00"))
        ));

        Order expectedOrder = new Order();
        expectedOrder.setId(1L);
        expectedOrder.setStatus(OrderStatus.CREATED);

        when(orderRepository.save(any(Order.class)))
            .thenReturn(expectedOrder);
        when(paymentService.charge(any()))
            .thenReturn(PaymentResult.success("txn-123"));

        // Act (Icra)
        Order result = orderService.createOrder(dto);

        // Assert (Yoxlama)
        assertNotNull(result);
        assertEquals(1L, result.getId());
        assertEquals(OrderStatus.CREATED, result.getStatus());

        // Metodlarin cagirildiqini yoxla
        verify(orderRepository).save(any(Order.class));
        verify(paymentService).charge(any());
        verify(emailService).sendOrderConfirmation(any());

        // Hec vaxt cagirilmadiqini yoxla
        verify(emailService, never()).sendCancellationEmail(any());
    }

    @Test
    @DisplayName("Odeme ugursuz olduqda exception atmalı")
    void shouldThrowWhenPaymentFails() {
        OrderDto dto = new OrderDto("user-1", List.of());

        when(orderRepository.save(any())).thenReturn(new Order());
        when(paymentService.charge(any()))
            .thenReturn(PaymentResult.failure("Insufficient funds"));

        assertThrows(PaymentException.class, () ->
            orderService.createOrder(dto));

        // Email gonderilmemeli
        verify(emailService, never()).sendOrderConfirmation(any());
    }
}
```

### @SpringBootTest -- Integration Test

Butun Spring kontekstini qaldiraq test:

```java
@SpringBootTest
@AutoConfigureMockMvc
@Transactional // Her testden sonra rollback olunur
class OrderControllerIntegrationTest {

    @Autowired
    private MockMvc mockMvc;

    @Autowired
    private ObjectMapper objectMapper;

    @Autowired
    private OrderRepository orderRepository;

    @MockBean // Spring kontekstinde mock ile evez et
    private PaymentService paymentService;

    @Test
    @DisplayName("POST /api/orders -- ugurlu sifaris")
    void shouldCreateOrder() throws Exception {
        // Mock hazirligi
        when(paymentService.charge(any()))
            .thenReturn(PaymentResult.success("txn-123"));

        OrderDto dto = new OrderDto();
        dto.setUserId(1L);
        dto.setItems(List.of(
            new OrderItemDto("Laptop", 1, new BigDecimal("999.99"))
        ));

        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(objectMapper.writeValueAsString(dto)))
            .andExpect(status().isCreated())
            .andExpect(jsonPath("$.id").exists())
            .andExpect(jsonPath("$.status").value("CREATED"))
            .andExpect(jsonPath("$.total").value(999.99));

        // Database-de yaradildiqini yoxla
        assertEquals(1, orderRepository.count());
    }

    @Test
    @DisplayName("GET /api/orders/{id} -- movcud sifaris")
    void shouldReturnOrder() throws Exception {
        Order order = orderRepository.save(
            createTestOrder(1L, new BigDecimal("100.00")));

        mockMvc.perform(get("/api/orders/{id}", order.getId()))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.id").value(order.getId()))
            .andExpect(jsonPath("$.total").value(100.00));
    }

    @Test
    @DisplayName("GET /api/orders/{id} -- tapilmayan sifaris")
    void shouldReturn404WhenOrderNotFound() throws Exception {
        mockMvc.perform(get("/api/orders/{id}", 99999))
            .andExpect(status().isNotFound())
            .andExpect(jsonPath("$.message")
                .value("Sifaris tapilmadi: 99999"));
    }

    @Test
    @DisplayName("POST /api/orders -- validasiya xetasi")
    void shouldReturn400WhenValidationFails() throws Exception {
        OrderDto dto = new OrderDto(); // Bosh DTO

        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(objectMapper.writeValueAsString(dto)))
            .andExpect(status().isBadRequest())
            .andExpect(jsonPath("$.errors").isArray());
    }
}
```

### MockMvc ile tefsirli test

```java
@WebMvcTest(ProductController.class)
class ProductControllerTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean
    private ProductService productService;

    @Test
    void shouldSearchProducts() throws Exception {
        List<Product> products = List.of(
            new Product(1L, "iPhone 15", new BigDecimal("999")),
            new Product(2L, "iPhone 14", new BigDecimal("799"))
        );
        when(productService.search("iPhone"))
            .thenReturn(products);

        mockMvc.perform(get("/api/products")
                .param("q", "iPhone")
                .accept(MediaType.APPLICATION_JSON))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$", hasSize(2)))
            .andExpect(jsonPath("$[0].name").value("iPhone 15"))
            .andExpect(jsonPath("$[1].price").value(799))
            .andDo(print()); // Request/response-u konsola yazdir
    }

    @Test
    @WithMockUser(roles = "ADMIN")
    void adminShouldDeleteProduct() throws Exception {
        mockMvc.perform(delete("/api/products/{id}", 1))
            .andExpect(status().isNoContent());

        verify(productService).deleteById(1L);
    }

    @Test
    void unauthenticatedShouldNotDeleteProduct() throws Exception {
        mockMvc.perform(delete("/api/products/{id}", 1))
            .andExpect(status().isUnauthorized());
    }
}
```

### Testcontainers ile database test

```java
@SpringBootTest
@Testcontainers
class OrderRepositoryTest {

    @Container
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15")
            .withDatabaseName("testdb")
            .withUsername("test")
            .withPassword("test");

    @DynamicPropertySource
    static void configureProperties(
            DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username",
                      postgres::getUsername);
        registry.add("spring.datasource.password",
                      postgres::getPassword);
    }

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldFindOrdersByUserId() {
        orderRepository.save(createOrder(1L));
        orderRepository.save(createOrder(1L));
        orderRepository.save(createOrder(2L));

        List<Order> orders = orderRepository.findByUserId(1L);

        assertEquals(2, orders.size());
    }
}
```

## Laravel-de istifadesi

### Unit Test

```php
// tests/Unit/PriceCalculatorTest.php
namespace Tests\Unit;

use App\Services\PriceCalculator;
use PHPUnit\Framework\TestCase; // Laravel TestCase deyil!

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PriceCalculator();
    }

    public function test_endirim_olmadan_qiymeti_duzgun_hesablayir(): void
    {
        $result = $this->calculator->calculate(100.00, 0);
        $this->assertEquals(100.00, $result);
    }

    public function test_10_faiz_endirimle_qiymeti_hesablayir(): void
    {
        $result = $this->calculator->calculate(100.00, 10);
        $this->assertEquals(90.00, $result);
    }

    public function test_menfi_qiymetde_exception_atir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculate(-10, 0);
    }

    /**
     * @dataProvider priceDiscountProvider
     */
    public function test_muxtelif_kombinasiyalar(
        float $price,
        int $discount,
        float $expected
    ): void {
        $result = $this->calculator->calculate($price, $discount);
        $this->assertEquals($expected, $result);
    }

    public static function priceDiscountProvider(): array
    {
        return [
            'endirimsiz' => [100.00, 0, 100.00],
            '25% endirim' => [200.00, 25, 150.00],
            '100% endirim' => [100.00, 100, 0.00],
        ];
    }
}
```

### Feature Test

```php
// tests/Feature/OrderControllerTest.php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase; // Her testden evvel DB-ni sifirla

    public function test_ugurlu_sifaris_yaradilir(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 99.99,
            'stock' => 10,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'status' => 'created',
                'total' => 199.98,
            ])
            ->assertJsonStructure([
                'id',
                'status',
                'total',
                'items' => [
                    '*' => ['product_id', 'quantity', 'price'],
                ],
            ]);

        // Database-de yaradildiqini yoxla
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'created',
            'total' => 199.98,
        ]);

        // Stok azaldiqini yoxla
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);
    }

    public function test_avtorizasiya_olmadan_sifaris_vermek_olmaz(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_validasiya_xetasi(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                // Bosh items
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_movcud_olmayan_sifarish(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/orders/99999');

        $response->assertStatus(404);
    }

    public function test_sifaris_siyahisi_pagination_ile(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(25)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/orders?page=1&per_page=10');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3);
    }
}
```

### Mocking

```php
use App\Services\PaymentService;
use App\Services\PaymentResult;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_odeme_servisi_mock_ile(): void
    {
        // PaymentService-i mock et
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('charge')
                ->once()
                ->with(\Mockery::on(function ($order) {
                    return $order->total > 0;
                }))
                ->andReturn(new PaymentResult(
                    success: true,
                    transactionId: 'txn-123'
                ));
        });

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_email_gonderildiyini_yoxla(): void
    {
        Mail::fake(); // Email gonderilmesinin qarsisini al

        $user = User::factory()->create();
        // ... sifaris yarat

        // Email gonderildiyini yoxla
        Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_event_fire_olundugunu_yoxla(): void
    {
        Event::fake([OrderPlaced::class]);

        // ... sifaris yarat

        Event::assertDispatched(OrderPlaced::class, function ($event) {
            return $event->order->id === 1;
        });
    }

    public function test_job_dispatch_olundugunu_yoxla(): void
    {
        Queue::fake();

        // ... emeliyyat icra et

        Queue::assertPushed(ProcessOrder::class);
        Queue::assertPushedOn('orders', ProcessOrder::class);
    }

    public function test_notification_gonderildiyini_yoxla(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        // ... emeliyyat

        Notification::assertSentTo(
            $user,
            OrderConfirmationNotification::class
        );
    }
}
```

### RefreshDatabase ve Seedler

```php
class ProductTest extends TestCase
{
    use RefreshDatabase;

    // Testden evvel seed ishlet
    protected bool $seed = true;

    // Ve ya museyyen seeder
    protected string $seeder = ProductSeeder::class;

    public function test_mehsul_siyahisi(): void
    {
        // Factory ile test datasi yarat
        Product::factory()
            ->count(5)
            ->create(['category' => 'electronics']);

        Product::factory()
            ->count(3)
            ->create(['category' => 'books']);

        $response = $this->getJson(
            '/api/products?category=electronics');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }
}

// Factory
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->productName(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'category' => fake()->randomElement([
                'electronics', 'books', 'clothing'
            ]),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }

    // State -- museyyen veziyyetde yaratmaq
    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function expensive(): static
    {
        return $this->state([
            'price' => fake()->randomFloat(2, 500, 5000),
        ]);
    }
}

// Istifade:
Product::factory()->outOfStock()->create();
Product::factory()->expensive()->count(3)->create();
```

### Laravel Dusk -- Browser Testing

```php
// tests/Browser/LoginTest.php
namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    public function test_istifadeci_giris_ede_bilir(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', 'test@example.com')
                ->type('password', 'password123')
                ->press('Daxil ol')
                ->assertPathIs('/dashboard')
                ->assertSee('Xos geldiniz');
        });
    }

    public function test_sehv_parol_ile_giris(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'wrong-password')
                ->press('Daxil ol')
                ->assertPathIs('/login')
                ->assertSee('Bu melumatlar duzgun deyil');
        });
    }

    public function test_sifaris_vermek_prosesi(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Laptop',
            'price' => 999.99,
        ]);

        $this->browse(function (Browser $browser) use ($user, $product) {
            $browser->loginAs($user)
                ->visit("/products/{$product->id}")
                ->assertSee('Laptop')
                ->assertSee('999.99')
                ->press('Sebete elave et')
                ->waitForText('Sebete elave edildi')
                ->visit('/cart')
                ->assertSee('Laptop')
                ->press('Sifarish ver')
                ->waitForLocation('/orders/*')
                ->assertSee('Sifarisiniz qebul olundu');
        });
    }
}
```

### HTTP Test Helpers

```php
class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_yukleme(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)
            ->postJson('/api/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertOk();

        // Fayl saxlanildiqini yoxla
        Storage::disk('public')->assertExists(
            "avatars/{$file->hashName()}"
        );
    }

    public function test_xarici_api_mock(): void
    {
        Http::fake([
            'api.payment.com/*' => Http::response([
                'status' => 'success',
                'transaction_id' => 'txn-456',
            ], 200),

            'api.shipping.com/*' => Http::response([
                'tracking' => 'TRACK-789',
            ], 200),

            // Basqa her sey 500 qaytarsin
            '*' => Http::response('Server Error', 500),
        ]);

        // Test mentiyiniz ...

        // HTTP sorgunun gonderildiyini yoxla
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.payment.com/charge'
                && $request['amount'] === 99.99;
        });
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Test framework** | JUnit 5 | PHPUnit |
| **Mock framework** | Mockito | Mockery |
| **Integration test** | `@SpringBootTest` | `Tests\TestCase` (extends) |
| **HTTP test** | MockMvc | `$this->getJson()`, `$this->postJson()` |
| **DB sifirlama** | `@Transactional` (rollback) | `RefreshDatabase` (migrate) |
| **Test datasi** | Builder pattern / manual | Factory (`User::factory()`) |
| **Mock bean** | `@MockBean` | `$this->mock()` |
| **Auth mock** | `@WithMockUser` | `$this->actingAs($user)` |
| **Browser test** | Selenium / Playwright | Laravel Dusk |
| **Container test** | Testcontainers | Yoxdur (sqlite istifade olunur) |
| **Fake servisleri** | Mock + Verify | `Mail::fake()`, `Queue::fake()` |

## Niye bele ferqler var?

**JUnit vs PHPUnit:**
Her iki framework oz dillerinin standart test framework-lerini istifade edir. JUnit 5 annotation-based yanasmadan istifade edir (`@Test`, `@BeforeEach`, `@DisplayName`). PHPUnit method naming convention istifade edir (`test_` prefiksi ve ya `@test` annotation). Ferq dillerin ozundendir.

**@SpringBootTest vs TestCase:**
Spring-de integration test ucun butun application kontekstini qaldirmaq lazimdir -- bu JVM-de uzun cekir (bezen 10-30 saniye). Buna gore Spring `@WebMvcTest`, `@DataJpaTest` kimi "slice" testler teqdim edir -- yalniz lazim olan hisseyi qaldirirlar. Laravel-de ise PHP-nin request-per-process modeli sebebile testler suretlen bashlayir ve bu problem yoxdur.

**RefreshDatabase vs @Transactional:**
Spring `@Transactional` ile testi database transaction icerisinde icra edib sonra rollback edir -- bu suretlen isleyir. Laravel `RefreshDatabase` ile her testden evvel migration isletir (ve ya transaction istifade edir). Her iki yanaşma testin izolasiyasini temin edir, amma mekanizm ferqlidir.

**Fake servisleri:**
Laravel-in `Mail::fake()`, `Queue::fake()`, `Event::fake()` kimi fake mexanizmleri cox rahatdir -- bir setirle butun servisi fake edir ve sonra assertion yazirsan. Spring-de eyni shey ucun `@MockBean` ve Mockito `verify()` istifade olunur -- daha cox kod lazimdir, amma daha çevikdir.

**Browser Testing:**
Laravel Dusk framework daxilinde gelir ve Chrome driver ile isleyir. Spring dunyasinda browser test ucun Selenium ve ya Playwright istifade olunur -- bunlar xarici alat olaraq qurulur.

## Hansi framework-de var, hansinda yoxdur?

**Yalniz Spring-de:**
- `@WebMvcTest`, `@DataJpaTest` -- slice testler (yalniz lazim olan hisseni qaldir)
- `@ParameterizedTest` ile parametrli testler (JUnit 5)
- `@DisplayName` -- test adlarini insanlar ucun oxunaqli yazmaq
- Testcontainers -- real database (PostgreSQL, MySQL) Docker container-de test
- `@DynamicPropertySource` -- test zamani property override
- `@WithMockUser` -- security test ucun mock istifadeci
- MockMvc -- servlet container olmadan controller test

**Yalniz Laravel-de:**
- `User::factory()` -- guclu factory sistemi state-ler ile
- `RefreshDatabase` trait -- avtomatik database sifirlama
- `Mail::fake()`, `Queue::fake()`, `Event::fake()` -- bir setirlik fake-ler
- `$this->actingAs($user)` -- sade auth mock
- `assertDatabaseHas()`, `assertDatabaseMissing()` -- database assertion
- `Storage::fake()` -- fayl sistemi fake
- `Http::fake()` -- xarici HTTP sorgu mock
- Laravel Dusk -- daxili browser testing
- `assertJsonStructure()`, `assertJsonPath()` -- JSON response assertion
- `UploadedFile::fake()` -- fayl yukleme test
