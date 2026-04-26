# Test Doubles (Middle)
## İcmal

Test double, test zamanı real obyektin yerini tutan əvəzedici obyektdir. Gerard
Meszaros tərəfindən "xUnit Test Patterns" kitabında təsvir edilib. Film dublyorları
(stunt double) kimi, test double-lar da real obyektin yerini tutur.

Beş növ test double var: Dummy, Stub, Spy, Mock, Fake. Hər birinin öz məqsədi
və istifadə yeri var. Çox vaxt "mock" sözü bütün test double-lara aid edilir,
amma texniki olaraq bunlar fərqli anlayışlardır.

## Niyə Vacibdir

- **Doğru növ seçimi**: Mock lazım olan yerdə Stub istifadə etmək yanlış assertion-lara aparır — fərqi bilmək test keyfiyyətini birbaşa artırır
- **Maintainability**: Düzgün test double seçilmədikdə testlər implementasiya dəyişdikdə qırılır
- **Communication**: Test double növü testin niyyətini oxucuya çatdırır — stub cavab test edir, mock davranış test edir
- **Real project-lərdə**: Laravel Fakes (Queue, Mail, Event) hər biri müxtəlif test double tipini təmsil edir

## Əsas Anlayışlar

### Test Double Növləri - Müqayisə

```
Sadəlikdən Mürəkkəbliyə:

Dummy → Stub → Spy → Mock → Fake
  │       │      │      │      │
  │       │      │      │      └─ Real implementasiyanın sadə versiyası
  │       │      │      └──────── Davranış yoxlayır (behavior verification)
  │       │      └─────────────── Çağırışları qeyd edir
  │       └────────────────────── Əvvəlcədən təyin edilmiş cavab qaytarır
  └────────────────────────────── Heç nə etmir, yalnız yer tutur
```

| Növ | Dəyər qaytarır? | Çağırışları yoxlayır? | Real məntiq var? |
|-----|------------------|-----------------------|-------------------|
| Dummy | Xeyr | Xeyr | Xeyr |
| Stub | Bəli | Xeyr | Xeyr |
| Spy | Bəli (optional) | Sonradan bəli | Xeyr |
| Mock | Bəli | Əvvəlcədən bəli | Xeyr |
| Fake | Bəli | Xeyr | Bəli (sadə) |

### State vs Behavior Verification

**State Verification**: Əməliyyatdan sonra vəziyyəti yoxlayır.
```php
$cart->addItem($product);
$this->assertEquals(1, $cart->itemCount()); // State yoxlanır
```

**Behavior Verification**: Düzgün metodların çağırıldığını yoxlayır.
```php
$emailService->shouldReceive('send')->once(); // Behavior yoxlanır
$orderService->complete($order);
```

Stub və Fake state verification üçün, Mock behavior verification üçün istifadə olunur.
Spy hər ikisi üçün istifadə oluna bilər.

## Praktik Baxış

### Best Practices
- Hər test double növünü düzgün məqsəd üçün istifadə edin
- Interface-lər üzərindən mock/stub yaradın
- Fake-ləri reusable yazın (test utility)
- Spy-ı AAA pattern ilə istifadə edin
- Mock setup-ı sadə saxlayın

### Anti-Patterns
- **Wrong double type**: Stub lazım olan yerdə Mock istifadə etmək
- **Mock everything**: Hər asılılığı mock etmək
- **Complex fakes**: Fake real implementasiyaya bərabərdir
- **Fragile mocks**: Hər dəfə implementasiya dəyişdikdə mock-lar sınır
- **Verify everything**: Hər metod çağırışını yoxlamaq
- **Mock what you don't own**: Third-party kodu mock əvəzinə wrapper yazın

## Nümunələr

### 1. Dummy

Heç vaxt istifadə edilməyən, yalnız parametr tələbini ödəyən obyekt.

```php
interface Logger
{
    public function info(string $message): void;
    public function error(string $message): void;
}

class OrderProcessor
{
    public function __construct(
        private PaymentGateway $gateway,
        private Logger $logger, // Bəzi testlərdə lazım deyil
    ) {}

    public function calculateTotal(array $items): float
    {
        return array_sum(array_column($items, 'price'));
    }
}

// Test - Logger dummy olaraq ötürülür
class OrderProcessorTest extends TestCase
{
    public function test_calculate_total(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $dummyLogger = Mockery::mock(Logger::class); // Heç çağırılmayacaq

        $processor = new OrderProcessor($gateway, $dummyLogger);

        $items = [
            ['name' => 'A', 'price' => 10],
            ['name' => 'B', 'price' => 20],
        ];

        $this->assertEquals(30, $processor->calculateTotal($items));
    }
}
```

### 2. Stub

Əvvəlcədən proqramlaşdırılmış cavab qaytarır.

```php
interface UserRepository
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
}

// Manual Stub
class StubUserRepository implements UserRepository
{
    private array $users;

    public function __construct(array $users = [])
    {
        $this->users = $users;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) return $user;
        }
        return null;
    }
}

// Test ilə manual stub
class GreetingServiceTest extends TestCase
{
    public function test_greet_existing_user(): void
    {
        $user = new User(['id' => 1, 'name' => 'John']);
        $repo = new StubUserRepository([1 => $user]);

        $service = new GreetingService($repo);

        $this->assertEquals('Hello, John!', $service->greet(1));
    }

    public function test_greet_unknown_user(): void
    {
        $repo = new StubUserRepository([]);
        $service = new GreetingService($repo);

        $this->assertEquals('Hello, Guest!', $service->greet(999));
    }
}

// Mockery ilə stub
class PricingServiceTest extends TestCase
{
    public function test_get_price_with_exchange_rate(): void
    {
        $exchangeRate = Mockery::mock(ExchangeRateApi::class);
        $exchangeRate->shouldReceive('getRate')
            ->with('USD', 'EUR')
            ->andReturn(0.85); // Stub - sabit dəyər qaytarır

        $service = new PricingService($exchangeRate);
        $price = $service->convertPrice(100, 'USD', 'EUR');

        $this->assertEquals(85, $price);
    }
}
```

### 3. Spy

Çağırışları qeyd edir, sonradan yoxlama imkanı verir.

```php
// Manual Spy
class SpyEmailService implements EmailService
{
    public array $sentEmails = [];

    public function send(string $to, string $subject, string $body): void
    {
        $this->sentEmails[] = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ];
    }
}

class OrderServiceTest extends TestCase
{
    public function test_sends_confirmation_email(): void
    {
        $emailSpy = new SpyEmailService();
        $service = new OrderService($emailSpy);

        $order = new Order(['user_email' => 'john@test.com', 'total' => 100]);
        $service->complete($order);

        // Sonradan yoxla
        $this->assertCount(1, $emailSpy->sentEmails);
        $this->assertEquals('john@test.com', $emailSpy->sentEmails[0]['to']);
        $this->assertStringContainsString('confirmation', $emailSpy->sentEmails[0]['subject']);
    }
}

// Mockery Spy
class AuditServiceTest extends TestCase
{
    public function test_logs_user_actions(): void
    {
        $spy = Mockery::spy(AuditLogger::class);

        $service = new UserService($spy);
        $service->updateProfile($user, ['name' => 'New Name']);

        // Sonradan yoxla
        $spy->shouldHaveReceived('log')
            ->once()
            ->with('profile_updated', Mockery::type('array'));
    }
}
```

### 4. Mock

Əvvəlcədən gözləntiləri təyin edir, çağırışları yoxlayır.

```php
class PaymentProcessorTest extends TestCase
{
    public function test_charges_correct_amount(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);

        // Gözlənti: charge metodu dəqiq 1 dəfə, 99.99 ilə çağırılmalıdır
        $gateway->shouldReceive('charge')
            ->once()
            ->with(99.99, 'USD', Mockery::type('string'))
            ->andReturn(new PaymentResult(true));

        $processor = new PaymentProcessor($gateway);
        $result = $processor->process(new Order(['total' => 99.99]));

        $this->assertTrue($result->success);
        // Test bitdikdə Mockery gözləntiləri yoxlayır
    }

    public function test_does_not_charge_zero_amount(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldNotReceive('charge'); // Heç çağırılmamalıdır

        $processor = new PaymentProcessor($gateway);

        $this->expectException(InvalidAmountException::class);
        $processor->process(new Order(['total' => 0]));
    }
}
```

### 5. Fake

Real implementasiyanın sadələşdirilmiş versiyası.

```php
// Fake In-Memory Repository
class FakeUserRepository implements UserRepository
{
    private array $users = [];
    private int $nextId = 1;

    public function save(User $user): User
    {
        if (!$user->id) {
            $user->id = $this->nextId++;
        }
        $this->users[$user->id] = $user;
        return $user;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) return $user;
        }
        return null;
    }

    public function delete(int $id): void
    {
        unset($this->users[$id]);
    }

    public function count(): int
    {
        return count($this->users);
    }
}

// Test ilə Fake
class UserRegistrationTest extends TestCase
{
    public function test_register_creates_user(): void
    {
        $repo = new FakeUserRepository();
        $service = new RegistrationService($repo);

        $user = $service->register('John', 'john@test.com', 'secret');

        $this->assertNotNull($user->id);
        $this->assertEquals(1, $repo->count());
        $this->assertNotNull($repo->findByEmail('john@test.com'));
    }

    public function test_cannot_register_duplicate_email(): void
    {
        $repo = new FakeUserRepository();
        $repo->save(new User(['email' => 'john@test.com']));

        $service = new RegistrationService($repo);

        $this->expectException(DuplicateEmailException::class);
        $service->register('Jane', 'john@test.com', 'secret');
    }
}

// Fake Cache
class FakeCacheStore implements CacheStore
{
    private array $store = [];

    public function get(string $key): mixed
    {
        $item = $this->store[$key] ?? null;
        if ($item && $item['expires_at'] && $item['expires_at'] < time()) {
            unset($this->store[$key]);
            return null;
        }
        return $item['value'] ?? null;
    }

    public function put(string $key, mixed $value, int $ttl = 0): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }

    public function flush(): void
    {
        $this->store = [];
    }
}
```

## Praktik Tapşırıqlar

### PHPUnit createMock

```php
class ServiceTest extends TestCase
{
    public function test_with_phpunit_mock(): void
    {
        $repo = $this->createMock(UserRepository::class);

        $repo->method('findById')
            ->with(1)
            ->willReturn(new User(['name' => 'John']));

        $repo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        $service = new UserService($repo);
        $service->updateName(1, 'Jane');
    }

    public function test_with_consecutive_returns(): void
    {
        $api = $this->createMock(ExternalApi::class);

        $api->method('call')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'pending'],
                ['status' => 'processing'],
                ['status' => 'complete'],
            );

        // İlk çağırış 'pending', ikinci 'processing', üçüncü 'complete' qaytaracaq
    }

    public function test_with_callback(): void
    {
        $repo = $this->createMock(UserRepository::class);

        $repo->method('save')
            ->willReturnCallback(function (User $user) {
                $user->id = 42;
                return $user;
            });

        $service = new UserService($repo);
        $user = $service->create('John', 'john@test.com');

        $this->assertEquals(42, $user->id);
    }
}
```

### Mockery Advanced

```php
class AdvancedMockeryTest extends TestCase
{
    // Ordered expectations
    public function test_methods_called_in_order(): void
    {
        $mock = Mockery::mock(Pipeline::class);

        $mock->shouldReceive('validate')->once()->ordered();
        $mock->shouldReceive('transform')->once()->ordered();
        $mock->shouldReceive('save')->once()->ordered();

        $service = new DataProcessor($mock);
        $service->process($data);
    }

    // Default mock
    public function test_with_default_mock(): void
    {
        $mock = Mockery::mock(Repository::class)
            ->shouldReceive('find')->andReturn(null)->byDefault()
            ->shouldReceive('save')->andReturn(true)->byDefault()
            ->getMock();

        // Bu testdə find-ı override edirik
        $mock->shouldReceive('find')->with(1)->andReturn(new User());
    }

    // Named mock
    public function test_with_named_mock(): void
    {
        $mock = Mockery::namedMock('CustomGateway', PaymentGateway::class);
        $mock->shouldReceive('charge')->andReturn(true);

        $this->assertInstanceOf(PaymentGateway::class, $mock);
    }
}
```

### Laravel-in Built-in Fakes

```php
class LaravelFakesTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_storage_fake(): void
    {
        Storage::fake('public');

        // Test code...
        Storage::disk('public')->put('file.txt', 'content');

        Storage::disk('public')->assertExists('file.txt');
    }

    public function test_with_notification_fake(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->notify(new InvoicePaid($invoice));

        Notification::assertSentTo($user, InvoicePaid::class);
    }

    public function test_with_bus_fake(): void
    {
        Bus::fake([ProcessOrder::class]);

        // Dispatch job
        ProcessOrder::dispatch($order);

        Bus::assertDispatched(ProcessOrder::class, function ($job) use ($order) {
            return $job->order->id === $order->id;
        });
    }
}
```

## Ətraflı Qeydlər

**S: Test double-ın beş növünü izah edin.**
C: Dummy: parametr olaraq ötürülür, heç istifadə edilmir. Stub: sabit dəyər
qaytarır. Spy: çağırışları qeyd edir, sonra yoxlanır. Mock: əvvəlcədən
gözləntiləri təyin edir. Fake: real implementasiyanın sadə versiyası (in-memory DB).

**S: Mock ilə Stub arasındakı əsas fərq nədir?**
C: Stub state verification üçün istifadə olunur (nəticəni yoxla). Mock behavior
verification üçün istifadə olunur (düzgün metod düzgün parametrlərlə çağırıldı?).

**S: Nə vaxt Fake istifadə etməlisiniz?**
C: Real implementasiya çox yavaş və ya mürəkkəbdir, amma test üçün bəzi
funksionallıq lazımdır. Məsələn: in-memory cache (real Redis əvəzinə),
in-memory repository (real database əvəzinə).

**S: Spy nə vaxt Mock-dan üstündür?**
C: Spy arrange fazasında gözlənti yazmağa ehtiyac olmadığında yaxşıdır. Əvvəl
act, sonra assert. Spy daha oxunaqlıdır çünki AAA pattern-ə daha yaxın düşür.

**S: PHPUnit createMock ilə Mockery fərqi nədir?**
C: Mockery daha ifadəli syntax təklif edir, partial mock dəstəkləyir, daha
güclü argument matchers var. PHPUnit mock daha sadədir və əlavə dependency
tələb etmir.

## Əlaqəli Mövzular

- [Unit Testing (Junior)](02-unit-testing.md)
- [Mocking (Middle)](07-mocking.md)
- [Testing Events & Queues (Middle)](15-testing-events-queues.md)
- [Testing Email & Notifications (Middle)](16-testing-email-notifications.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
