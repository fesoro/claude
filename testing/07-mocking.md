# Mocking (Middle)
## İcmal

Mocking, test zamanı real obyektləri simulated (saxta) obyektlərlə əvəz etmə texnikasıdır.
Xarici asılılıqları (database, API, email servisi) izolə etmək üçün istifadə olunur.

Mocking sayəsində:
- Testlər sürətli işləyir (real API çağırılmır)
- Testlər etibarlı olur (xarici servisin downtime-ı təsir etmir)
- Edge case-ləri simulyasiya etmək olur (API 500 qaytarır)
- Testlər izolə olur (bir-birinə təsir etmir)

Mock termin olaraq bəzən bütün test double-ları əhatə edir amma texniki olaraq
mock yalnız bir növ test double-dır.

## Niyə Vacibdir

- **Test sürəti**: Real API, database, email servisləri olmadan testlər millisaniyələrdə işləyir
- **İzolasiya**: Xarici servis down olsa belə testlər keçir — CI pipeline etibarlı qalır
- **Edge case simulyasiyası**: API-nin 500 qaytarması, network timeout kimi nadir vəziyyətlər asanlıqla test edilir
- **Dizayn siqnalı**: Mock etmək çətin olan sinif pis dizaynın əlamətidir — refactoring tələb edir

## Əsas Anlayışlar

### Mock vs Stub vs Spy vs Fake

**Stub**: Əvvəlcədən proqramlaşdırılmış cavab qaytarır. Verification etmir.
```php
$stub = Mockery::mock(WeatherApi::class);
$stub->shouldReceive('getTemperature')->andReturn(25);
// Yalnız dəyər qaytarır, çağırılıb-çağırılmadığını yoxlamır
```

**Mock**: Cavab qaytarır VƏ çağırışları yoxlayır (behavior verification).
```php
$mock = Mockery::mock(PaymentGateway::class);
$mock->shouldReceive('charge')
    ->once()                    // Dəqiq 1 dəfə çağırılmalıdır
    ->with(100, 'USD')         // Bu parametrlərlə
    ->andReturn(true);
```

**Spy**: Çağırışları qeyd edir, sonra yoxlamaq olur.
```php
$spy = Mockery::spy(Logger::class);
$service->process();
$spy->shouldHaveReceived('info')->with('Processing started');
```

**Fake**: Real implementasiyanın sadələşdirilmiş versiyası.
```php
// Laravel-də Storage::fake() - real filesystem əvəzinə memory istifadə edir
Storage::fake('public');
```

### Nə Vaxt Mock Etməli?

Mock etməli:
- Xarici API çağırışları
- Email/SMS göndərmə
- Payment gateway
- File system əməliyyatları
- Cache/Queue əməliyyatları
- Time-dependent operations

Mock etməməli:
- Value objects
- Domain model-ləri
- Sadə data structures
- Test edilən sinifin özü

### Over-Mocking Anti-Pattern

```php
// YANLIŞ - hər şey mock edilib, heç nə test edilmir
public function test_order_total(): void
{
    $order = Mockery::mock(Order::class);
    $order->shouldReceive('total')->andReturn(100);

    $this->assertEquals(100, $order->total()); // Nəyi test edirik?!
}

// DOĞRU - yalnız xarici asılılıqlar mock edilir
public function test_order_total_includes_tax(): void
{
    $taxService = Mockery::mock(TaxService::class);
    $taxService->shouldReceive('calculate')->with(100)->andReturn(18);

    $order = new Order($taxService);
    $order->addItem(new Item('Laptop', 100));

    $this->assertEquals(118, $order->total());
}
```

## Praktik Baxış

### Best Practices
- Yalnız xarici asılılıqları mock edin
- Mockery::close() tearDown-da çağırın
- Http::preventStrayRequests() istifadə edin
- Argument matchers ilə flexible yoxlama edin
- Mock-ları interface üzərindən yaradın

### Anti-Patterns
- **Over-mocking**: Hər şeyi mock etmək
- **Mocking the SUT**: Test edilən sinifi mock etmək
- **Mocking value objects**: Sadə data obyektlərini mock etmək
- **Implementation coupling**: Mock-lar implementasiya detallarına bağlıdır
- **Missing tearDown**: Mockery::close() çağırmamaq
- **Complex mock setup**: 20 sətirlik mock setup - dizayn probleminə işarədir

## Nümunələr

### Mockery Əsasları

```php
use Mockery;
use Mockery\MockInterface;

class PaymentServiceTest extends TestCase
{
    // Sadə mock
    public function test_process_payment(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->with(Mockery::on(fn($amount) => $amount > 0), 'USD')
            ->andReturn(new PaymentResult(true, 'txn_123'));

        $service = new PaymentService($gateway);
        $result = $service->processPayment(100);

        $this->assertTrue($result->success);
    }

    // Ardıcıl cavablar
    public function test_retry_on_failure(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->times(3)
            ->andReturn(
                new PaymentResult(false, null),  // 1-ci: fail
                new PaymentResult(false, null),  // 2-ci: fail
                new PaymentResult(true, 'txn_1') // 3-cü: success
            );

        $service = new PaymentService($gateway);
        $result = $service->processWithRetry(100, maxRetries: 3);

        $this->assertTrue($result->success);
    }

    // Exception mock
    public function test_handles_gateway_exception(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->andThrow(new GatewayException('Connection timeout'));

        $service = new PaymentService($gateway);
        $result = $service->processPayment(100);

        $this->assertFalse($result->success);
        $this->assertEquals('Gateway error', $result->message);
    }

    protected function tearDown(): void
    {
        Mockery::close(); // Mockery təmizlə
        parent::tearDown();
    }
}
```

### Partial Mock

```php
class NotificationServiceTest extends TestCase
{
    public function test_sends_email_for_important_events(): void
    {
        // Partial mock - bəzi metodlar real, bəziləri mock
        $service = Mockery::mock(NotificationService::class)->makePartial();
        $service->shouldReceive('sendEmail')
            ->once()
            ->with('admin@test.com', Mockery::any());

        $service->notify(new ImportantEvent());
    }
}
```

### Argument Matchers

```php
$mock->shouldReceive('method')
    ->with(Mockery::any())           // Hər hansı argument
    ->with(Mockery::type('string'))  // String type
    ->with(Mockery::on(function ($arg) {
        return $arg > 0 && $arg < 100; // Custom matcher
    }))
    ->with(Mockery::contains('item'))    // Array-da var
    ->with(Mockery::hasKey('name'))      // Array-da key var
    ->with(Mockery::subset(['key' => 'value'])); // Array subset
```

## Praktik Tapşırıqlar

### Laravel Facade Mocking

```php
class ReportServiceTest extends TestCase
{
    public function test_report_is_cached(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('monthly_report', 3600, Mockery::type('Closure'))
            ->andReturn(['total' => 1000]);

        $service = app(ReportService::class);
        $report = $service->getMonthlyReport();

        $this->assertEquals(1000, $report['total']);
    }
}
```

### Http::fake()

```php
class WeatherServiceTest extends TestCase
{
    public function test_get_current_weather(): void
    {
        Http::fake([
            'api.weather.com/*' => Http::response([
                'temperature' => 25,
                'condition' => 'sunny',
            ], 200),
        ]);

        $service = new WeatherService();
        $weather = $service->getCurrent('Baku');

        $this->assertEquals(25, $weather->temperature);
        $this->assertEquals('sunny', $weather->condition);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'city=Baku');
        });
    }

    public function test_handles_api_error(): void
    {
        Http::fake([
            'api.weather.com/*' => Http::response(null, 500),
        ]);

        $service = new WeatherService();

        $this->expectException(WeatherApiException::class);
        $service->getCurrent('Unknown');
    }

    public function test_prevent_real_http_calls(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'api.weather.com/*' => Http::response(['temp' => 20]),
        ]);

        // Əgər başqa URL-ə request getsa, test fail edəcək
        $service = new WeatherService();
        $service->getCurrent('Baku');
    }
}
```

### Queue::fake()

```php
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_dispatches_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $service = app(OrderService::class);
        $service->complete($order);

        Queue::assertPushed(SendOrderConfirmation::class, function ($job) use ($order) {
            return $job->order->id === $order->id;
        });

        Queue::assertPushed(UpdateInventory::class);
        Queue::assertNotPushed(RefundPayment::class);
    }

    public function test_failed_order_does_not_dispatch_confirmation(): void
    {
        Queue::fake();

        $order = Order::factory()->create();
        $service = app(OrderService::class);

        try {
            $service->fail($order);
        } catch (\Exception $e) {
            // expected
        }

        Queue::assertNotPushed(SendOrderConfirmation::class);
    }
}
```

### Event::fake()

```php
class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_fires_event(): void
    {
        Event::fake([UserRegistered::class]);

        $service = app(UserService::class);
        $user = $service->register([
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'secret',
        ]);

        Event::assertDispatched(UserRegistered::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_user_deletion_fires_event(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $service = app(UserService::class);
        $service->delete($user);

        Event::assertDispatched(UserDeleted::class);
        Event::assertNotDispatched(UserRegistered::class);
    }
}
```

### Mocking with Dependency Injection

```php
class InvoiceServiceTest extends TestCase
{
    public function test_generate_invoice_pdf(): void
    {
        // Mock bind etmək
        $pdfGenerator = Mockery::mock(PdfGenerator::class);
        $pdfGenerator->shouldReceive('generate')
            ->once()
            ->andReturn('pdf-content');

        $this->app->instance(PdfGenerator::class, $pdfGenerator);

        $service = app(InvoiceService::class);
        $pdf = $service->generatePdf($invoice);

        $this->assertEquals('pdf-content', $pdf);
    }

    // Və ya mock() helper ilə
    public function test_with_mock_helper(): void
    {
        $mock = $this->mock(PdfGenerator::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn('pdf-content');
        });

        $service = app(InvoiceService::class);
        $pdf = $service->generatePdf($invoice);

        $this->assertEquals('pdf-content', $pdf);
    }
}
```

## Ətraflı Qeydlər

**S: Mocking nədir və niyə istifadə edilir?**
C: Real obyektləri simulated obyektlərlə əvəz etmə texnikasıdır. Xarici
asılılıqları izolə etmək, testləri sürətləndirmək və edge case-ləri simulyasiya
etmək üçün istifadə olunur.

**S: Over-mocking nədir?**
C: Hər şeyi mock edərək heç nəyi test etməmək anti-pattern-idir. Mock yalnız
xarici asılılıqlar üçün olmalıdır. SUT-un özü mock edilməməlidir.

**S: Laravel-də Http::fake() necə işləyir?**
C: Http facade-ı fake edir, real HTTP request göndərmir. URL pattern-ə uyğun
cavablar proqramlaşdırıla bilər. Http::assertSent() ilə request-ləri yoxlamaq olur.

**S: shouldReceive() ilə shouldHaveReceived() fərqi nədir?**
C: shouldReceive() əvvəlcədən gözlənti təyin edir (mock). shouldHaveReceived()
isə sonradan yoxlayır (spy). Mock-da gözlənti yerinə yetirilməzsə test fail edir.

**S: Partial mock nədir?**
C: Obyektin bəzi metodlarını mock edib, qalanlarını real saxlamaqdır. makePartial()
ilə yaradılır. Yalnız shouldReceive() ilə təyin edilən metodlar mock olur.

## Əlaqəli Mövzular

- [Unit Testing (Junior)](02-unit-testing.md)
- [Test Doubles (Middle)](08-test-doubles.md)
- [Testing Third-Party Integrations (Senior)](28-testing-third-party.md)
- [Testing Events & Queues (Middle)](15-testing-events-queues.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
