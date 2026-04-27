# Integration Testing və Contract Testing (Middle)

## Mündəricat
1. [Test Pyramid](#test-pyramid)
2. [Integration Testing Nədir](#integration-testing-needir)
3. [Laravel Test Növləri](#laravel-test-novleri)
4. [RefreshDatabase vs DatabaseTransactions](#database-strategies)
5. [HTTP Integration Tests](#http-integration-tests)
6. [External Service Integration Tests](#external-service)
7. [Contract Testing](#contract-testing)
8. [Pact Framework](#pact-framework)
9. [OpenAPI/Swagger as Contract](#openapi)
10. [Test Doubles — Mock, Stub, Fake, Spy, Dummy](#test-doubles)
11. [Http::fake() ilə External API Mocking](#http-fake)
12. [Laravel Fake Facade-lar](#laravel-fakes)
13. [Mutation Testing — Infection PHP](#mutation-testing)
14. [Model Factory States və Sequences](#model-factories)
15. [Test Data Management](#test-data)
16. [Parallel Testing](#parallel-testing)
17. [İntervyu Sualları](#intervyu-suallari)

---

## Test Pyramid {#test-pyramid}

Test pyramid — test strategiyasının vizual modelidir. Aşağıdan yuxarıya: Unit → Integration → E2E.

```
        /\
       /  \
      / E2E \          ← Az say, yavaş, bahalı, lakin tam ssenari
     /--------\
    /          \
   / Integration \     ← Orta say, real komponentlər arası
  /--------------\
 /                \
/   Unit Tests     \   ← Çox say, sürətli, izolə edilmiş
/____________________\
```

| Xüsusiyyət | Unit | Integration | E2E |
|---|---|---|---|
| Sürət | Çox sürətli (ms) | Orta (s) | Yavaş (min) |
| İzolasiya | Tam | Qismən | Yoxdur |
| DB istifadəsi | Yox | Bəli | Bəli |
| Real HTTP | Yox | Mock/Real | Real |
| Sınma halı | Bir modul | Modullar arası | Tam ssenari |
| Sayı | Çox (70%) | Orta (20%) | Az (10%) |

**Nə zaman hansı test növü:**

```
Unit Test:
  ✓ Biznes məntiq (calculation, transformation, validation)
  ✓ Model metodları
  ✓ Service class-lar
  ✓ Value Object-lər
  ✓ Utility funksiyalar
  ✗ DB sorğuları — mock et

Integration Test:
  ✓ API endpoint-lər (request → response)
  ✓ DB ilə model əməliyyatları
  ✓ Queue job-ları
  ✓ Mail/Notification göndərmə
  ✓ Cache əməliyyatları
  ✗ Tam istifadəçi ssenarisi — E2E-ə burax

E2E Test:
  ✓ Kritik istifadəçi axınları (qeydiyyat, ödəniş, onboarding)
  ✓ Cross-browser yoxlama
  ✓ Visual regression
  ✗ Hər edge case — unit-ə burax
```

---

## Integration Testing Nədir {#integration-testing-needir}

**Unit test** bir komponenti izolə edərək yoxlayır — bütün xarici asılılıqlar mock-lanır.

**Integration test** bir neçə komponentin birlikdə düzgün işlədiyini yoxlayır — real DB, real servis qatı.

***Integration test** bir neçə komponentin birlikdə düzgün işlədiyini y üçün kod nümunəsi:*
```php
// UNIT TEST — izolə edilmiş, DB yoxdur
class OrderCalculatorTest extends TestCase
{
    public function test_calculates_total_with_tax(): void
    {
        $calculator = new OrderCalculator(taxRate: 0.18);

        $total = $calculator->calculate(items: [
            new OrderItem(price: 100, quantity: 2),
            new OrderItem(price: 50,  quantity: 1),
        ]);

        $this->assertEquals(295.0, $total); // (200 + 50) * 1.18
    }
}

// INTEGRATION TEST — real DB, real service chain
class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_order_with_inventory_deduction(): void
    {
        // Real DB-də məlumat yarat
        $product = Product::factory()->create(['stock' => 10]);
        $user    = User::factory()->create();

        // Tam service chain-i işə sal
        $orderService = app(OrderService::class);
        $order = $orderService->create($user, [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        // DB-dəki real nəticəni yoxla
        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
        $this->assertDatabaseHas('order_items', [
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // Stock-un azaldığını yoxla
        $this->assertEquals(7, $product->fresh()->stock);
    }
}
```

---

## Laravel Test Növləri {#laravel-test-novleri}

*Laravel Test Növləri {#laravel-test-novleri} üçün kod nümunəsi:*
```bash
# Laravel test qovluq strukturu
tests/
├── Unit/                  # PHPUnit Unit testlər — app() container yoxdur
│   ├── Models/
│   ├── Services/
│   └── ValueObjects/
├── Feature/               # Laravel Feature testlər — tam framework
│   ├── Api/
│   ├── Auth/
│   └── Http/
└── Integration/           # Açıq integration testlər (konvensiya)
    ├── Repositories/
    └── ExternalServices/
```

**Unit Test — minimal Laravel:**

```php
// tests/Unit/Services/PriceCalculatorTest.php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase; // Laravel TestCase deyil!

class PriceCalculatorTest extends TestCase
{
    // app() container mövcud deyil
    // DB yoxdur
    // Çox sürətli

    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PriceCalculator();
    }

    public function test_applies_percentage_discount(): void
    {
        $price = $this->calculator->applyDiscount(
            originalPrice: 1000,
            discountPercent: 20
        );

        $this->assertEquals(800.0, $price);
    }

    public function test_throws_exception_for_negative_price(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Qiymət mənfi ola bilməz');

        $this->calculator->applyDiscount(-100, 10);
    }
}
```

**Feature Test — tam Laravel:**

```php
// tests/Feature/Api/UserRegistrationTest.php
namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    // app() container mövcuddur
    // DB migration işə düşür
    // HTTP request simulyasiyası

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Əli Həsənov',
            'email'                 => 'ali@example.com',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'created_at'],
                'token',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ali@example.com']);

        $response = $this->postJson('/api/register', [
            'email' => 'ali@example.com',
            // ...
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
```

---

## RefreshDatabase vs DatabaseTransactions {#database-strategies}

*RefreshDatabase vs DatabaseTransactions {#database-strategies} üçün kod nümunəsi:*
```php
// RefreshDatabase — hər test-dən əvvəl migration işə salır
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;
    // ✓ Hər test üçün təmiz DB
    // ✓ Migration dəyişikliklərini yoxlayır
    // ✗ Yavaş — tam migration hər test-dən əvvəl
    // İstifadə: Migration dəyişikliyi varsa, tam izolasiya lazımdırsa
}

// DatabaseTransactions — test transaction-da işləyir, sonra rollback
use Illuminate\Foundation\Testing\DatabaseTransactions;

class OrderTest extends TestCase
{
    use DatabaseTransactions;
    // ✓ Sürətli — rollback ucuzdur
    // ✓ Test-lər arasında DB sıfırlanır
    // ✗ Transaction-daxili hadisələri test edə bilməz
    // ✗ Nested transaction-larda problem ola bilər
    // İstifadə: Sürət vacibdirsə, sadə CRUD testlərində
}

// Fərq — praktiki nümunə:
class RefreshVsTransactionTest extends TestCase
{
    use RefreshDatabase; // vs DatabaseTransactions

    public function test_scenario_a(): void
    {
        User::factory()->create(['email' => 'a@test.com']);
        // RefreshDatabase: növbəti test üçün migration yenidən işləyir
        // DatabaseTransactions: transaction rollback olunur
    }

    public function test_scenario_b(): void
    {
        // Hər iki halda a@test.com artıq yoxdur
        $this->assertDatabaseMissing('users', ['email' => 'a@test.com']);
    }
}

// Xüsusi hal — After Commit Listeners
// DatabaseTransactions ilə after_commit listener-lər işləMİR!
class PaymentTest extends TestCase
{
    use RefreshDatabase; // DatabaseTransactions deyil!

    public function test_sends_receipt_after_payment(): void
    {
        Mail::fake();

        // dispatch(new SendReceiptJob(...)) işi commit-dən sonra dispatch edilir
        // DatabaseTransactions-da commit olmur → event işləmir
        // RefreshDatabase-də real commit var → event işləyir

        $paymentService->process($order);

        Mail::assertQueued(PaymentReceiptMail::class);
    }
}
```

**DatabaseMigrations trait (köhnə yanaşma):**

```php
// Köhnə, az istifadə edilir
use Illuminate\Foundation\Testing\DatabaseMigrations;

// RefreshDatabase ilə eyni, lakin daha az ağıllıdır
// RefreshDatabase migration state-i cache edir — daha sürətlidir
```

---

## HTTP Integration Tests {#http-integration-tests}

*HTTP Integration Tests {#http-integration-tests} üçün kod nümunəsi:*
```php
// tests/Feature/Api/PostControllerTest.php
class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // GET /api/posts — siyahı
    public function test_returns_paginated_posts(): void
    {
        Post::factory()->count(15)->for($this->user)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?per_page=10');

        $response
            ->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'slug', 'author', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.current_page', 1);
    }

    // POST /api/posts — yaratma
    public function test_authenticated_user_can_create_post(): void
    {
        $payload = [
            'title'   => 'Test Məqaləsi',
            'body'    => 'Məzmun burada...',
            'status'  => 'draft',
            'tags'    => ['php', 'laravel'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/posts', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Test Məqaləsi')
            ->assertJsonPath('data.author.id', $this->user->id);

        $this->assertDatabaseHas('posts', [
            'title'   => 'Test Məqaləsi',
            'user_id' => $this->user->id,
        ]);
    }

    // PUT /api/posts/{id} — yalnız öz postunu yeniləyə bilir
    public function test_cannot_update_another_users_post(): void
    {
        $anotherUser = User::factory()->create();
        $post = Post::factory()->for($anotherUser)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/posts/{$post->id}", ['title' => 'Oğurlanmış başlıq']);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('posts', ['title' => 'Oğurlanmış başlıq']);
    }

    // DELETE — soft delete yoxlaması
    public function test_soft_deletes_post(): void
    {
        $post = Post::factory()->for($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id]); // Hələ DB-dədir
    }

    // Validation test
    public function test_create_post_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/posts', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body'])
            ->assertJsonStructure([
                'message',
                'errors' => ['title', 'body'],
            ]);
    }

    // assertJson — qismən uyğunluq
    public function test_response_contains_expected_fields(): void
    {
        $post = Post::factory()->for($this->user)->published()->create([
            'title' => 'Gözlənilən Başlıq',
        ]);

        $this->getJson("/api/posts/{$post->id}")
            ->assertJson([
                'data' => [
                    'title'  => 'Gözlənilən Başlıq',
                    'status' => 'published',
                ],
            ]); // Digər sahələrin mövcudluğuna baxmır
    }
}
```

---

## External Service Integration Tests {#external-service}

**Strategiyalar:**

```
1. Mock/Stub yanaşması — xarici servis fake ilə əvəzlənir (əksər hallarda)
2. Test double → contract test  (provider tərəfdə)
3. VCR (Video Cassette Recorder) — real cavablar cassette-ə yazılır
4. Sandbox/Test environment — payment gateway test mode
```

*4. Sandbox/Test environment — payment gateway test mode üçün kod nümunəsi:*
```php
// app/Services/StripePaymentService.php
class StripePaymentService implements PaymentServiceInterface
{
    public function __construct(
        private readonly \Stripe\StripeClient $stripe
    ) {}

    public function charge(int $amountCents, string $token): ChargeResult
    {
        $charge = $this->stripe->charges->create([
            'amount'   => $amountCents,
            'currency' => 'usd',
            'source'   => $token,
        ]);

        return new ChargeResult(
            id:      $charge->id,
            success: $charge->status === 'succeeded',
        );
    }
}

// tests/Integration/Payment/StripePaymentServiceTest.php
class StripePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_charges_card_successfully(): void
    {
        // Stripe client-i mock et
        $stripeMock = Mockery::mock(\Stripe\StripeClient::class);
        $stripeMock->charges = Mockery::mock();
        $stripeMock->charges
            ->shouldReceive('create')
            ->once()
            ->with([
                'amount'   => 5000,
                'currency' => 'usd',
                'source'   => 'tok_test_visa',
            ])
            ->andReturn((object)[
                'id'     => 'ch_test_123',
                'status' => 'succeeded',
            ]);

        $service = new StripePaymentService($stripeMock);
        $result  = $service->charge(5000, 'tok_test_visa');

        $this->assertTrue($result->success);
        $this->assertEquals('ch_test_123', $result->id);
    }

    public function test_handles_card_declined(): void
    {
        $stripeMock = Mockery::mock(\Stripe\StripeClient::class);
        $stripeMock->charges = Mockery::mock();
        $stripeMock->charges
            ->shouldReceive('create')
            ->andThrow(new \Stripe\Exception\CardException('Kart rədd edildi'));

        $service = new StripePaymentService($stripeMock);

        $this->expectException(PaymentDeclinedException::class);
        $service->charge(5000, 'tok_declined');
    }
}
```

---

## Contract Testing {#contract-testing}

**Consumer-Driven Contract Testing nədir:**

```
Problem:
  Service A (Consumer) → API → Service B (Provider)
  Service B API dəyişir → Service A sınır
  İki team ayrı-ayrı test edir → birlikdə test etmir

Consumer-Driven Contract Testing:
  1. Consumer (A) öz gözləntilərini "contract" kimi yazır
  2. Contract Pact Broker-ə yüklənir
  3. Provider (B) bu contract-ı öz tərəfindən yoxlayır
  4. Hər dəploy-da yoxlanır

Faydalar:
  ✓ E2E test olmadan servis uyumluluğu yoxlanır
  ✓ Breaking change-lər erkən aşkarlanır
  ✓ Test mühiti lazım deyil
  ✓ Async — hər team öz sürətindədir
```

---

## Pact Framework {#pact-framework}

*Pact Framework {#pact-framework} üçün kod nümunəsi:*
```bash
# PHP üçün
composer require pact-foundation/pact-php --dev
```

**Consumer tərəfi (Service A — məsələn frontend/BFF):**

```php
// tests/Contract/Consumer/UserServiceConsumerTest.php
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Matcher\Matcher;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockService;
use PhpPact\Standalone\MockService\MockServiceConfig;

class UserServiceConsumerTest extends TestCase
{
    private static MockService $mockService;
    private static InteractionBuilder $builder;

    public static function setUpBeforeClass(): void
    {
        $config = new MockServiceConfig();
        $config
            ->setConsumer('frontend-app')
            ->setProvider('user-service')
            ->setPactDir(__DIR__ . '/../../pacts')
            ->setHost('localhost')
            ->setPort(7200);

        self::$mockService = new MockService($config);
        self::$builder     = new InteractionBuilder($config);
    }

    public function test_get_user_returns_correct_structure(): void
    {
        $matcher = new Matcher();

        // Gözlənilən contract-ı müəyyən et
        $request = new ConsumerRequest();
        $request
            ->setMethod('GET')
            ->setPath('/api/users/1')
            ->addHeader('Authorization', 'Bearer token123')
            ->addHeader('Accept', 'application/json');

        $response = new ProviderResponse();
        $response
            ->setStatus(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'data' => [
                    'id'    => $matcher->integer(1),        // integer olmalıdır
                    'name'  => $matcher->string('Əli'),     // string olmalıdır
                    'email' => $matcher->email('a@b.com'), // email formatı
                    'role'  => $matcher->regex('admin|user|editor', 'user'),
                ],
            ]);

        self::$builder
            ->given('User with ID 1 exists')
            ->uponReceiving('a request for user 1')
            ->with($request)
            ->willRespondWith($response);

        // Consumer kodunu real olaraq icra et (Mock Service-ə qarşı)
        $client   = new UserApiClient('http://localhost:7200');
        $user     = $client->getUser(1, 'token123');

        $this->assertEquals(1, $user->id);
        $this->assertIsString($user->name);

        // Pact faylını yaz
        self::$mockService->verifyInteractions();
    }

    public static function tearDownAfterClass(): void
    {
        self::$mockService->getPactJson(); // pacts/ qovluğuna yazır
    }
}
```

**Provider tərəfi (Service B — user-service):**

```php
// tests/Contract/Provider/UserServiceProviderTest.php
use PhpPact\Standalone\ProviderVerifier\Model\VerifierConfig;
use PhpPact\Standalone\ProviderVerifier\ProviderVerifier;

class UserServiceProviderTest extends TestCase
{
    public function test_honours_consumer_contracts(): void
    {
        // Test server-i qaldır
        $serverProcess = new Process(['php', 'artisan', 'serve', '--port=8001']);
        $serverProcess->start();
        sleep(2);

        $config = new VerifierConfig();
        $config
            ->setProviderName('user-service')
            ->setProviderVersion('1.0.0')
            ->setProviderBaseUrl('http://localhost:8001')
            ->setPactBrokerUri('https://pact-broker.example.com')
            ->setPactBrokerToken(env('PACT_BROKER_TOKEN'))
            ->setPublishResults(true)
            ->setProviderStatesSetupUrl('http://localhost:8001/pact/provider-states');

        $verifier = new ProviderVerifier();
        $result   = $verifier->verify($config);

        $this->assertTrue($result, 'Provider contract yoxlaması uğursuz oldu');

        $serverProcess->stop();
    }
}

// routes/pact.php — yalnız test mühitindədir
Route::post('/pact/provider-states', function (Request $request) {
    $state = $request->input('state');

    match ($state) {
        'User with ID 1 exists' => User::factory()->create(['id' => 1]),
        'No users exist'         => User::query()->delete(),
        default                  => null,
    };

    return response()->json(['result' => true]);
});
```

---

## OpenAPI/Swagger as Contract {#openapi}

*OpenAPI/Swagger as Contract {#openapi} üçün kod nümunəsi:*
```php
// composer require spectator/spectator

// Spectator — OpenAPI sxemini test zamanı yoxlayır
// tests/Feature/Api/PostApiTest.php
use Spectator\Spectator;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Spectator::using('api.yaml'); // openapi spec faylı
    }

    public function test_get_posts_matches_openapi_spec(): void
    {
        Post::factory()->count(3)->create();

        $this->getJson('/api/posts')
            ->assertValidRequest()   // Request spec-ə uyğundur
            ->assertValidResponse(); // Response spec-ə uyğundur (schema validation)
    }

    public function test_create_post_validates_against_spec(): void
    {
        $this->postJson('/api/posts', [
            'title' => 'Test',
            'body'  => 'Content here',
        ])
            ->assertValidRequest()
            ->assertValidResponse(201);
    }
}

// openapi/api.yaml (qısa nümunə)
// openapi: 3.0.0
// paths:
//   /api/posts:
//     get:
//       responses:
//         '200':
//           content:
//             application/json:
//               schema:
//                 type: object
//                 required: [data, meta]
//                 properties:
//                   data:
//                     type: array
//                     items:
//                       $ref: '#/components/schemas/Post'
```

---

## Test Doubles — Mock, Stub, Fake, Spy, Dummy {#test-doubles}

Test double — test zamanı real obyekti əvəz edən test köməkçisidir.

### Dummy

Sadəcə yer doldurmaq üçün — heç vaxt çağırılmır.

*Sadəcə yer doldurmaq üçün — heç vaxt çağırılmır üçün kod nümunəsi:*
```php
// Bu kod Mock ilə unit test-də xarici asılılıqların simulyasiyasını göstərir
class OrderTest extends TestCase
{
    public function test_order_total_calculation(): void
    {
        // LoggerInterface lazımdır amma bu test-də çağırılmır
        $dummyLogger = $this->createMock(LoggerInterface::class);
        // Heç bir expectation yoxdur

        $order = new Order($dummyLogger);
        $total = $order->calculateTotal([
            new OrderItem(price: 100, qty: 2),
        ]);

        $this->assertEquals(200, $total);
    }
}
```

### Stub

Sabit cavab qaytarır — hərəkəti idarə edir, yoxlamır.

*Sabit cavab qaytarır — hərəkəti idarə edir, yoxlamır üçün kod nümunəsi:*
```php
// Bu kod Stub ilə test-də sabit cavab qaytarılmasını göstərir
class ProductServiceTest extends TestCase
{
    public function test_creates_product_with_generated_slug(): void
    {
        // SlugGenerator həmişə eyni dəyər qaytarır
        $stubSlugGenerator = $this->createStub(SlugGeneratorInterface::class);
        $stubSlugGenerator
            ->method('generate')
            ->willReturn('test-mehsul'); // Sabit cavab — "stub"

        $service = new ProductService($stubSlugGenerator);
        $product = $service->create('Test Məhsul');

        $this->assertEquals('test-mehsul', $product->slug);
    }
}
```

### Mock

Davranış gözləntiləri var — çağırılıb-çağırılmadığı yoxlanır.

*Davranış gözləntiləri var — çağırılıb-çağırılmadığı yoxlanır üçün kod nümunəsi:*
```php
// Bu kod Spy ilə metodun çağırılıb-çağırılmadığını yoxlamanı göstərir
class NotificationServiceTest extends TestCase
{
    public function test_sends_welcome_email_on_registration(): void
    {
        // Mock — gözlənti müəyyən edilir
        $mockMailer = $this->createMock(MailerInterface::class);
        $mockMailer
            ->expects($this->once())              // Tam 1 dəfə çağırılmalıdır
            ->method('send')
            ->with(
                $this->equalTo('ali@example.com'), // İlk arqument
                $this->isInstanceOf(WelcomeMail::class) // İkinci arqument
            );

        $service = new UserRegistrationService($mockMailer);
        $service->register('Əli', 'ali@example.com', 'password');
        // Əgər send() çağırılmasa — test fail olur
    }
}

// Mockery ilə daha oxunaqlı
public function test_charges_correct_amount(): void
{
    $mockStripe = Mockery::mock(StripeInterface::class);
    $mockStripe
        ->shouldReceive('charge')
        ->once()
        ->with(5000, Mockery::type('string'))
        ->andReturn(new ChargeResult(success: true));

    $service = new PaymentService($mockStripe);
    $service->processPayment(amount: 50.00);
}
```

### Fake

Real implementasiya amma yaddaşda/sadələşdirilmiş — production-a getmir.

*Real implementasiya amma yaddaşda/sadələşdirilmiş — production-a getmi üçün kod nümunəsi:*
```php
// Fake implementasiya — real interfeysə uyğun, lakin yaddaşda işləyir
class FakeEmailService implements EmailServiceInterface
{
    private array $sentEmails = [];

    public function send(string $to, string $subject, string $body): void
    {
        // Real SMTP göndərmir — sadəcə saxlayır
        $this->sentEmails[] = compact('to', 'subject', 'body');
    }

    public function assertSentTo(string $email): void
    {
        $found = collect($this->sentEmails)->contains('to', $email);
        PHPUnit\Framework\Assert::assertTrue(
            $found,
            "'{$email}' ünvanına e-poçt göndərilmədi"
        );
    }

    public function assertNothingSent(): void
    {
        PHPUnit\Framework\Assert::assertEmpty(
            $this->sentEmails,
            'Gözlənilmədən e-poçt göndərildi'
        );
    }
}

// Testin özü
class UserServiceTest extends TestCase
{
    public function test_sends_welcome_email(): void
    {
        $fakeEmail = new FakeEmailService();
        $service   = new UserService($fakeEmail);

        $service->register('ali@example.com');

        $fakeEmail->assertSentTo('ali@example.com');
    }
}
```

### Spy

Real obyekt kimi işləyir amma çağırışları qeydə alır.

*Real obyekt kimi işləyir amma çağırışları qeydə alır üçün kod nümunəsi:*
```php
// Bu kod Dummy obyektlə test üçün lazımsız parametrlərin ötürülməsini göstərir
class AnalyticsTest extends TestCase
{
    public function test_tracks_page_view(): void
    {
        // Spy — real işləyir + çağırışları izləyir
        $spy = Mockery::spy(AnalyticsService::class);

        $pageService = new PageService($spy);
        $pageService->viewPage('home');

        // Sonra yoxla
        $spy->shouldHaveReceived('track')
            ->once()
            ->with('page_view', ['page' => 'home']);
    }
}
```

**Fərqlər cədvəli:**

| Test Double | Gözlənti | Davranış | İstifadə |
|---|---|---|---|
| Dummy | Yox | Yox | Parametr doldurmaq |
| Stub | Yox | Sabit cavab | State idarəsi |
| Mock | Bəli (əvvəlcədən) | Konfiqurasiya edilmiş | Davranış yoxlaması |
| Fake | Yox | Sadə real impl. | Mürəkkəb asılılıqlar |
| Spy | Bəli (sonradan) | Real | Çağırış izləmə |

---

## Http::fake() ilə External API Mocking {#http-fake}

*Http::fake() ilə External API Mocking {#http-fake} üçün kod nümunəsi:*
```php
// tests/Feature/Integration/GithubServiceTest.php
use Illuminate\Support\Facades\Http;

class GithubServiceTest extends TestCase
{
    public function test_fetches_user_repos(): void
    {
        // Fake response müəyyən et
        Http::fake([
            'api.github.com/users/*/repos' => Http::response([
                ['id' => 1, 'name' => 'laravel', 'stars' => 100],
                ['id' => 2, 'name' => 'pest',    'stars' => 50],
            ], 200),
        ]);

        $service = app(GithubService::class);
        $repos   = $service->getUserRepos('laravelphp');

        $this->assertCount(2, $repos);
        $this->assertEquals('laravel', $repos[0]['name']);

        // Hansı request-lərin göndərildiyini yoxla
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'laravelphp/repos')
                && $request->hasHeader('Authorization');
        });
    }

    public function test_handles_github_api_error(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(
                ['message' => 'API rate limit exceeded'],
                403
            ),
        ]);

        $this->expectException(GithubApiException::class);

        app(GithubService::class)->getUserRepos('user');
    }

    public function test_retries_on_server_error(): void
    {
        // Ardıcıl cavablar — ilk dəfə 500, sonra 200
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push(['error' => 'Server Error'], 500)
                ->push(['error' => 'Still Error'],  500)
                ->push([['name' => 'repo']],         200),
        ]);

        $service = app(GithubService::class); // retry() konfiqurasiya edilib
        $repos   = $service->getUserRepos('user');

        Http::assertSentCount(3); // 2 retry + 1 uğurlu
        $this->assertNotEmpty($repos);
    }

    public function test_does_not_hit_real_api_in_tests(): void
    {
        Http::fake(); // Bütün HTTP bloklanır

        app(GithubService::class)->getUserRepos('user');

        // Real heç bir HTTP sorğusu gedmir
        Http::assertNothingSent();
    }

    // Müxtəlif URL pattern-ləri
    public function test_multiple_external_services(): void
    {
        Http::fake([
            'api.stripe.com/*'  => Http::response(['id' => 'ch_123'], 200),
            'api.twilio.com/*'  => Http::response(['sid' => 'SM123'], 201),
            'api.sendgrid.com/*' => Http::response(null, 202),
            '*'                 => Http::response(['error' => 'Unmocked URL'], 500),
        ]);

        // Test kodu...
    }
}
```

---

## Laravel Fake Facade-lar {#laravel-fakes}

### Event::fake()

*Event::fake() üçün kod nümunəsi:*
```php
// Bu kod Laravel-də Event::fake() ilə event-lərin göndərilməsini test etməyi göstərir
use Illuminate\Support\Facades\Event;

class OrderServiceTest extends TestCase
{
    public function test_dispatches_order_placed_event(): void
    {
        Event::fake([OrderPlaced::class]); // Yalnız bu event fake edilir

        $service = app(OrderService::class);
        $order   = $service->place(User::factory()->create(), $this->cartData());

        Event::assertDispatched(OrderPlaced::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });

        Event::assertNotDispatched(OrderFailed::class);
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }
}
```

### Mail::fake()

*Mail::fake() üçün kod nümunəsi:*
```php
// Bu kod Mail::fake() ilə email göndərilməsinin test edilməsini göstərir
use Illuminate\Support\Facades\Mail;

class InvoiceControllerTest extends TestCase
{
    public function test_sends_invoice_email_on_payment(): void
    {
        Mail::fake();

        $this->actingAs($this->user)
            ->postJson('/api/invoices/' . $this->invoice->id . '/pay');

        Mail::assertQueued(InvoicePaidMail::class, function ($mail) {
            return $mail->hasTo($this->user->email)
                && $mail->invoice->id === $this->invoice->id;
        });

        Mail::assertNotSent(PaymentFailedMail::class);
    }
}
```

### Queue::fake()

*Queue::fake() üçün kod nümunəsi:*
```php
// Bu kod Queue::fake() ilə job-ların növbəyə əlavə edilməsini test etməyi göstərir
use Illuminate\Support\Facades\Queue;

class VideoUploadTest extends TestCase
{
    public function test_dispatches_processing_job(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->postJson('/api/videos', ['file' => $this->videoFile()]);

        Queue::assertPushed(ProcessVideoJob::class, function ($job) {
            return $job->queue === 'videos'
                && $job->delay >= 0;
        });

        Queue::assertPushedOn('videos', ProcessVideoJob::class);
        Queue::assertNothingPushed(); // Artıq test-lərdə
    }
}
```

### Notification::fake()

*Notification::fake() üçün kod nümunəsi:*
```php
// Bu kod Notification::fake() ilə notification göndərilməsini test etməyi göstərir
use Illuminate\Support\Facades\Notification;

class PasswordResetTest extends TestCase
{
    public function test_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ali@test.com']);

        $this->postJson('/api/forgot-password', ['email' => 'ali@test.com']);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function ($notification) {
                return strlen($notification->token) === 60;
            }
        );

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }
}
```

### Storage::fake()

*Storage::fake() üçün kod nümunəsi:*
```php
// Bu kod Storage::fake() ilə fayl yükləmə əməliyyatlarının test edilməsini göstərir
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class AvatarUploadTest extends TestCase
{
    public function test_uploads_avatar(): void
    {
        Storage::fake('s3'); // Real S3-ə getmir

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($this->user)
            ->postJson('/api/users/avatar', ['avatar' => $file]);

        $response->assertStatus(200);

        // Faylın saxlanıldığını yoxla
        Storage::disk('s3')->assertExists('avatars/' . $this->user->id . '/avatar.jpg');
        Storage::disk('s3')->assertMissing('avatars/other/avatar.jpg');
    }

    public function test_rejects_non_image_files(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $this->actingAs($this->user)
            ->postJson('/api/users/avatar', ['avatar' => $file])
            ->assertStatus(422);

        Storage::disk('s3')->assertDirectoryEmpty('avatars');
    }
}
```

---

## Mutation Testing — Infection PHP {#mutation-testing}

**Mutation testing nədir:**

```
İdea: Kodun bir hissəsini dəyişdir (mutant yarat) → Test-lər hələ keçirsə — test zəifdir

Mutasiya növləri:
  - True → False
  - > → >=
  - + → -
  - return null → return ''
  - if($x) → if(!$x)

Nəticə:
  ✓ Killed (öldürülmüş)   — Test mutantı aşkar etdi — yaxşı!
  ✗ Escaped (qaçmış)      — Test mutantı görmədi — test zəifdir!
  ○ Uncovered             — Bu kod heç test edilmir

Mutation Score Indicator (MSI) = Killed / Total * 100
Hədəf: MSI > 80%
```

*Hədəf: MSI > 80% üçün kod nümunəsi:*
```bash
# Qurulum
composer require infection/infection --dev

# infection.json5 konfiqurasiya
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": ["app/Services", "app/Domain"]
    },
    "testFramework": "phpunit",
    "testFrameworkOptions": "--testsuite=Unit",
    "minMsi": 80,
    "minCoveredMsi": 90,
    "mutators": {
        "@default": true,
        "TrueValue": true,
        "FalseValue": true,
        "GreaterThan": true,
        "GreaterThanOrEqualTo": true
    }
}

# İşə sal
./vendor/bin/infection --threads=4 --min-msi=80

# CI-da
./vendor/bin/infection --min-msi=80 --min-covered-msi=90 --no-progress
```

**Praktiki nümunə — mutation-ı aşkar edən test:**

```php
// app/Services/DiscountService.php
class DiscountService
{
    public function isEligible(User $user): bool
    {
        // Mutation: > dəyişib >= olacaq
        return $user->orders()->count() > 5
            && $user->created_at < now()->subMonths(3);
    }
}

// ZƏIF TEST — mutantı görmür
public function test_user_is_eligible(): void
{
    $user = User::factory()->hasOrders(6)->create();
    $this->assertTrue(app(DiscountService::class)->isEligible($user));
    // > 5 də, >= 5 də true qaytarır — mutant qaçır!
}

// GÜCLÜ TEST — mutantı görür (boundary test)
public function test_exactly_5_orders_is_not_eligible(): void
{
    $user = User::factory()->hasOrders(5)->create(); // Tam 5 — >5 false, >=5 true
    $this->assertFalse(app(DiscountService::class)->isEligible($user));
    // İndi mutant >= 5 olsa test fail edir → mutant killed!
}

public function test_6_orders_is_eligible(): void
{
    $user = User::factory()->hasOrders(6)->create();
    $this->assertTrue(app(DiscountService::class)->isEligible($user));
}
```

---

## Model Factory States və Sequences {#model-factories}

*Model Factory States və Sequences {#model-factories} üçün kod nümunəsi:*
```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'user',
            'status'            => 'active',
        ];
    }

    // State: admin rolu
    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    // State: suspended hesab
    public function suspended(): static
    {
        return $this->state([
            'status'        => 'suspended',
            'suspended_at'  => now(),
            'suspended_by'  => User::factory()->admin(),
        ]);
    }

    // State: e-poçtu təsdiqlənməmiş
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    // State: premium üzv
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'plan'          => 'premium',
                'plan_expires'  => now()->addYear(),
                'billing_email' => $attributes['email'], // Mövcud attribut istifadəsi
            ];
        });
    }

    // After creating — əlaqəli məlumat yarat
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->role === 'admin') {
                $user->permissions()->attach(Permission::all());
            }
        });
    }
}

// database/factories/PostFactory.php
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'      => fake()->sentence(),
            'slug'       => fake()->unique()->slug(),
            'body'       => fake()->paragraphs(3, true),
            'status'     => 'draft',
            'user_id'    => User::factory(),
            'views'      => 0,
            'created_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status'       => 'published',
            'published_at' => now()->subDays(rand(1, 30)),
        ]);
    }

    public function trending(): static
    {
        return $this->state([
            'status' => 'published',
            'views'  => fake()->numberBetween(1000, 10000),
        ]);
    }
}

// Sequence — ardıcıl dəyişən dəyərlər
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status'  => 'pending',
            'user_id' => User::factory(),
        ];
    }
}

// Testin özündə factory istifadəsi
class FactoryUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_examples(): void
    {
        // Sadə yaratma
        $user = User::factory()->create();

        // State zənciri
        $admin = User::factory()->admin()->premium()->create();

        // Çoxlu yaratma
        $users = User::factory()->count(10)->create();

        // Müxtəlif statuslu postlar (Sequence)
        $posts = Post::factory()
            ->count(6)
            ->sequence(
                ['status' => 'draft'],
                ['status' => 'published'],
                ['status' => 'archived'],
            )
            ->create();
        // draft, published, archived, draft, published, archived

        // Ardıcıl e-poçt ünvanları
        $team = User::factory()
            ->count(3)
            ->sequence(
                ['email' => 'ali@company.com',   'role' => 'admin'],
                ['email' => 'leyla@company.com', 'role' => 'editor'],
                ['email' => 'murad@company.com', 'role' => 'viewer'],
            )
            ->create();

        // Müəyyən user-ə aid postlar
        $userWithPosts = User::factory()
            ->has(Post::factory()->count(5)->published(), 'posts')
            ->create();

        // Nested əlaqələr
        $orderWithItems = Order::factory()
            ->has(
                OrderItem::factory()
                    ->count(3)
                    ->state(new Sequence(
                        ['product_id' => 1],
                        ['product_id' => 2],
                        ['product_id' => 3],
                    )),
                'items'
            )
            ->create();

        // Magic methods ilə daha oxunaqlı
        $userWithOrders = User::factory()
            ->hasOrders(5)  // OrderFactory
            ->create();

        $this->assertCount(5, $userWithOrders->orders);
    }
}
```

---

## Test Data Management {#test-data}

*Test Data Management {#test-data} üçün kod nümunəsi:*
```php
// Seeder — development məlumatı
// database/seeders/TestDataSeeder.php
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::factory()->admin()->create([
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        // Kateqoriyalar
        $categories = Category::factory()->count(5)->create();

        // Hər kateqoriyaya 10 post
        $categories->each(function ($category) use ($admin) {
            Post::factory()
                ->count(10)
                ->for($admin)
                ->for($category)
                ->sequence(
                    fn($seq) => ['status' => $seq->index < 7 ? 'published' : 'draft']
                )
                ->create();
        });
    }
}

// Base TestCase ilə shared setup
// tests/TestCase.php
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseTestDataSeeder::class);
    }

    // Helper metodlar
    protected function actingAsAdmin(): static
    {
        $admin = User::factory()->admin()->create();
        return $this->actingAs($admin, 'sanctum');
    }

    protected function actingAsUser(?User $user = null): static
    {
        $user ??= User::factory()->create();
        return $this->actingAs($user, 'sanctum');
    }
}

// Test-lərdə istifadə
class AdminPanelTest extends TestCase
{
    public function test_admin_can_see_all_users(): void
    {
        User::factory()->count(10)->create();

        $this->actingAsAdmin()
            ->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonCount(11, 'data'); // 10 + 1 admin
    }
}

// Object Mother pattern — standart test obyektləri
class UserMother
{
    public static function premiumUser(): User
    {
        return User::factory()->premium()->create([
            'name'  => 'Premium İstifadəçi',
            'email' => 'premium@test.com',
        ]);
    }

    public static function suspendedUser(): User
    {
        return User::factory()->suspended()->create();
    }

    public static function newUser(): User
    {
        return User::factory()->unverified()->create([
            'created_at' => now(),
        ]);
    }
}
```

---

## Parallel Testing {#parallel-testing}

*Parallel Testing {#parallel-testing} üçün kod nümunəsi:*
```bash
# Parallel test dəstəyi — Laravel 8+
php artisan test --parallel

# Thread sayını təyin et
php artisan test --parallel --processes=8

# Specific test-lər
php artisan test --parallel tests/Feature/

# CI-da
php artisan test --parallel --processes=$(nproc)
```

**Parallel test konfiqurasiyası:**

```php
// tests/CreatesApplication.php
trait CreatesApplication
{
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }
}

// Parallel test üçün ayrı DB
// phpunit.xml
<env name="DB_DATABASE" value="testing"/>

// Hər process öz DB-sini istifadə edir
// Laravel avtomatik olaraq test_0, test_1 ... test_N DB-ləri yaradır
// (ParallelTesting trait ilə)

// config/database.php — parallel test üçün avtomatik DB seçimi
'database' => env('DB_DATABASE', 'laravel') . (
    isset($_SERVER['TEST_TOKEN']) ? '_' . $_SERVER['TEST_TOKEN'] : ''
),
```

**Parallel test lifecycle hook-ları:**

```php
// tests/TestCase.php
use Illuminate\Support\Facades\ParallelTesting;

class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        ParallelTesting::callSetUpProcessCallbacks();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Hər test üçün unikal prefix
        $token = ParallelTesting::token();
        // cache key-lərini, file adlarını unikal et
    }
}
```

**Race condition-dan qorunma:**

```php
// Parallel test-lərdə shared state problemi
// YANLIŞ — paylaşılan statik dəyişən
class Config
{
    private static array $cache = []; // Parallel test-lərdə conflict!
}

// DÜZGÜN — hər test özünün instance-nı yaradır
// Test isolation — RefreshDatabase + factory ilə
public function test_concurrent_order_creation(): void
{
    // Hər test öz DB transaction-nda işləyir
    $user = User::factory()->create(); // Unikal user
    // ...
}
```

---

## İntervyu Sualları {#intervyu-suallari}

**1. Unit test ilə integration test arasındakı əsas fərq nədir?**

Unit test bir komponenti izolə edərək yoxlayır — bütün asılılıqlar mock-lanır, DB yoxdur, çox sürətlidir. Integration test bir neçə komponentin birlikdə işini yoxlayır — real DB, real service chain. Unit test "bu funksiya düzgün işləyir?" sualını, integration test "komponentlər birlikdə düzgün işləyir?" sualını cavablandırır.

**2. RefreshDatabase vs DatabaseTransactions — nə zaman hansı seçirsiniz?**

`RefreshDatabase` — tam izolasiya lazımdırsa, migration dəyişikliyi varsa, `after_commit` listener-lar test ediləndə. `DatabaseTransactions` — sürət vacibdirsə, sadə CRUD testlərindədir. Əsas fərq: `DatabaseTransactions` real commit etmir — commit-dən sonra işlədilən əməliyyatlar (queue, event listener) işləmir.

**3. Mock, Stub, Fake, Spy fərqləri nələrdir?**

Stub sabit cavab qaytarır, gözlənti yoxdur. Mock gözləntiləri əvvəlcədən müəyyən edir — çağırılıb-çağırılmadığını yoxlayır. Fake real implementasiya amma sadələşdirilmiş (yaddaşda). Spy real işləyir ama çağırışları sonradan izləmək üçün. Dummy heç vaxt çağırılmayan yer doldurucudur.

**4. Contract testing nədir? Nə üçün lazımdır?**

Microservice mühitdə consumer (A servisi) öz gözləntiləri əsasında "contract" yazır. Provider (B servisi) bu contract-ı öz tərəfindən yoxlayır. E2E test mühiti olmadan servislərin uyumluluğu yoxlanılır. Pact ən məşhur framework-dür. Breaking change-lər deploy-dan əvvəl aşkarlanır.

**5. Http::fake() necə işləyir?**

`Http::fake()` Laravel-in HTTP client-ini intercept edir — real HTTP sorğuları göndərilmir. URL pattern-ə görə cavabları konfiqurasiya etmək olur, sequence ilə ardıcıl cavablar, `Http::assertSent()` ilə göndərilən sorğuları yoxlamaq olur. Test mühitinin sürəti artır, external service-ə asılılıq yox olur.

**6. Pact broker nədir?**

Consumer tərəfindən yazılan contract fayllarını saxlayan mərkəzi servis. Provider bu broker-dən öz contract-larını çəkir və yoxlayır. CI/CD-yə inteqrasiya edilir. can-i-deploy komandası ilə deploy-dan əvvəl uyumluluq yoxlanılır.

**7. Mutation testing nədir? MSI nədir?**

Infection PHP kodu avtomatik olaraq dəyişdirir (mutant yaradır) — `>` → `>=`, `true` → `false` kimi. Əgər testlər bu mutantı aşkar etsə "killed", etməsə "escaped" sayılır. MSI (Mutation Score Indicator) = Killed / Total × 100. 80%+ MSI yaxşı hesab edilir.

**8. Factory state nədir? Necə istifadə edirsiniz?**

Factory state müəyyən vəziyyətdə olan model yaratmaq üçün predefined konfiqurasiyalardır. `User::factory()->admin()->premium()->create()` kimi state-ləri zəncirlə istifadə etmək olur. Kod təkrarlanması azalır, test məqsədi aydın görünür.

**9. Parallel testing zamanı hansı problemlər yarana bilər?**

DB konfliktləri — Laravel hər process üçün ayrı DB yaradır (`testing_1`, `testing_2`). Paylaşılan fayl sistemi — `Storage::fake()` istifadə etmək lazımdır. Cache konfliktləri — test-ə unikal prefix əlavə etmək lazımdır. `TEST_TOKEN` environment variable-i process-i identifikasiya edir.

**10. Event::fake() harada istifadə edirsiz? Məhdudiyyəti varmı?**

`Event::fake()` event dispatch-ləri fake edir — listener-lər işləmir. Bununla event-in dispatch edildiyini yoxlayırıq. Məhdudiyyət: listener-in əsl işini yoxlamaq olmur — bunun üçün ayrıca listener testi lazımdır. Sadəcə `OrderPlaced` dispatch edilib-edilmədiyini yoxlamaq üçün idealdır.

**11. OpenAPI spec-i contract kimi necə istifadə edirsiniz?**

Spectator paketi test zamanı hər request/response-u OpenAPI sxeminə qarşı yoxlayır. `assertValidRequest()` + `assertValidResponse()` ilə endpoint spec-ə uyğun işləyir. API dəyişikliyi spec-ə uyğun olmasa test fail olur. Bu yanaşma documentation-ın həmişə actual olmasını təmin edir.

**12. Test data management üçün hansı pattern-lər var?**

Object Mother — standart test obyektlərini centralizasiya edir. Factory states — predefined vəziyyətlər. Test seeder-lər — development data. `tests/TestCase.php`-də shared setUp() — kod təkrarlanmasını azaldır. Test-lərdə hardcoded ID-lərdən qaçmaq — factory-yə etibar et.

---

## Anti-patternlər

**1. Integration test-ləri real xarici API-lərə qarşı çalışdırmaq**
Test suitini real ödəniş gateway-inə, real SMS xidmətinə bağlamaq — test-lər flaky olur, xarici servis aşağı olduqda CI pipeline sınır, test etmək üçün pul xərclənir. HTTP Client fake, WireMock, ya da contract test (Pact) istifadə et.

**2. Contract testi olmadan API consumer-producer arasındakı dəyişiklikləri idarə etmək**
Producer team-i API cavab strukturunu dəyişdirir, consumer test-ləri sınır amma kimse bilmir — production-da breaking change ancaq real xəta ilə aşkar olunur. Pact kimi consumer-driven contract testing tətbiq et, CI-da contract verification məcburi et.

**3. Test-lərdə hardcoded ID-lər işlətmək**
`User::find(1)` kimi sabit ID-lər işlətmək — paralel test icrasında konfliktlər yaranır, test database-i yenidən seed edildikdə test-lər sınır. Factory-lər işlət, `$user->id`-ni dinamik al.

**4. Hər integration test-i üçün bütün database-i seed etmək**
Hər test-dən əvvəl bütün `DatabaseSeeder`-i çalışdırmaq — test suitinin icra vaxtı dəfələrlə uzanır. `RefreshDatabase` trait-i istifadə et, yalnız test üçün lazım olan minimal datanı factory ilə yarat.

**5. Mock-ların doğruluğunu heç vaxt əsl implementasiya ilə yoxlamamaq**
Xidmət interfeysi dəyişdikdə mock-ları güncəlləməmək — test-lər yaşıl keçir amma real servis çağırışları uğursuz olur. Contract testlər ya da integration test-lər ilə mock-ların reallığı müntəzəm yoxlanılmalıdır.

**6. `Event::fake()` işlətdikdən sonra listener davranışını test etməmək**
Event dispatch edildiyini yoxlayıb listener-in əsl işini test etməmək — event göndərilir, amma listener yanlış işləyə bilər, bu aşkar olunmaz. Listener-lər üçün ayrıca unit test yaz, event payload-ını da doğrula.
