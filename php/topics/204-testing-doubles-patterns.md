# Testing Doubles Patterns (Middle)

## İcmal
Testing double — real obyektin yerini tutmaq üçün testdə istifadə edilən saxta obyektdir. Gerard Meszaros-un terminologiyasına görə 5 növü var: Dummy, Stub, Fake, Mock, Spy. Bunları bir-birindən fərqləndirmək testləri düzgün qurmaq üçün vacibdir.

## Niyə Vacibdir
External API, database, mail sistemi, queue — bunları test zamanı real çağırmaq testləri yavaş, qeyri-sabit, baha edir. Testing double-lar izolasiya yaradır: yalnız sınanan kod test edilir. Lakin yanlış double seçimi testlərin həqiqi xətanı tutmamasına gətirib çıxarır.

## Əsas Anlayışlar

### 5 Növ Testing Double

| Növ | Nə edir | Nə vaxt istifadə |
|-----|---------|-----------------|
| **Dummy** | Heç bir iş görmür, yalnız parametr kimi keçirilir | Sadəcə interfeysi doldurmaq üçün |
| **Stub** | Sabit nəticə qaytarır | Müəyyən vəziyyət simulyasiyası (e.g., API 404 qaytarır) |
| **Fake** | Sadələşdirilmiş real implementasiya | Database, cache, queue-nun yüngül versiyası |
| **Mock** | Çağırışları doğrulayır (assertion) | Metod çağırıldı mı, neçə dəfə, hansı argumentlə? |
| **Spy** | Real işi görür + çağırışları qeydə alır | Metod çağırıldı mı (post-hoc yoxlama) |

### Dummy
```php
// Log interface-i implementation etmirsə belə keçirilməlidir
class OrderServiceTest extends TestCase
{
    public function test_calculates_total(): void
    {
        $dummyLogger = $this->createMock(LoggerInterface::class);
        // dummyLogger heç vaxt çağırılmır, sadəcə dependency tələbini ödəyir
        
        $service = new OrderService($dummyLogger);
        $total   = $service->calculateTotal(100, 2);
        
        $this->assertEquals(200, $total);
    }
}
```

### Stub — Sabit Cavab
```php
class PaymentServiceTest extends TestCase
{
    public function test_marks_order_as_paid_when_payment_succeeds(): void
    {
        $paymentGateway = $this->createStub(PaymentGatewayInterface::class);
        $paymentGateway->method('charge')
                       ->willReturn(new PaymentResult(success: true, transactionId: 'txn_123'));
        
        $service = new OrderService($paymentGateway);
        $order   = $service->processPayment($order, 5000);
        
        $this->assertEquals('paid', $order->status);
    }
    
    public function test_marks_order_as_failed_when_payment_fails(): void
    {
        $paymentGateway = $this->createStub(PaymentGatewayInterface::class);
        $paymentGateway->method('charge')
                       ->willReturn(new PaymentResult(success: false));
        
        $service = new OrderService($paymentGateway);
        $order   = $service->processPayment($order, 5000);
        
        $this->assertEquals('payment_failed', $order->status);
    }
}
```

### Fake — Yüngül Real İmplementasiya
```php
// Fake implementation
class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];
    
    public function save(User $user): void
    {
        $this->users[$user->id] = $user;
    }
    
    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }
    
    public function findByEmail(string $email): ?User
    {
        return collect($this->users)->first(fn(User $u) => $u->email === $email);
    }
}

// Testdə istifadə
$repository = new InMemoryUserRepository();
$service    = new UserService($repository);

$service->register('test@example.com', 'password');

$user = $repository->findByEmail('test@example.com');
$this->assertNotNull($user);
```

### Mock — Çağırış Doğrulaması
```php
class NotificationServiceTest extends TestCase
{
    public function test_sends_welcome_email_after_registration(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        
        // Assertion: send() MÜTLƏQ bir dəfə çağırılmalıdır
        $mailer->expects($this->once())
               ->method('send')
               ->with(
                   $this->isInstanceOf(WelcomeEmail::class),
                   $this->equalTo('user@example.com')
               );
        
        $service = new RegistrationService($mailer);
        $service->register('user@example.com', 'password');
        // Test bitəndə mock avtomatik assert edir
    }
}
```

### Spy — Post-hoc Yoxlama
```php
class AuditServiceTest extends TestCase
{
    public function test_logs_payment_attempt(): void
    {
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        
        // Spy kimi: çağırışları topla, sonra yoxla
        $calls = [];
        $auditLogger->method('log')
                    ->willReturnCallback(function (string $event, array $data) use (&$calls) {
                        $calls[] = ['event' => $event, 'data' => $data];
                    });
        
        $service = new PaymentService($auditLogger);
        $service->charge(5000);
        
        $this->assertCount(1, $calls);
        $this->assertEquals('payment_attempted', $calls[0]['event']);
    }
}
```

## Laravel Built-in Fakes

### Mail::fake()
```php
it('sends invoice email', function () {
    Mail::fake();
    
    $this->post('/orders/1/pay');
    
    Mail::assertSent(InvoicePaid::class);
    Mail::assertSent(InvoicePaid::class, fn(InvoicePaid $m) => $m->hasTo('user@example.com'));
    Mail::assertNotSent(PaymentFailed::class);
    Mail::assertNothingOutgoing();
});
```

### Queue::fake()
```php
it('dispatches process job', function () {
    Queue::fake();
    
    $this->post('/uploads', ['file' => UploadedFile::fake()->image('photo.jpg')]);
    
    Queue::assertPushed(ProcessUploadedImage::class);
    Queue::assertPushed(ProcessUploadedImage::class, 1);
    Queue::assertPushedOn('images', ProcessUploadedImage::class);
    Queue::assertNotPushed(SendEmail::class);
});
```

### Event::fake()
```php
it('fires order placed event', function () {
    Event::fake();
    
    $this->post('/orders', $orderData);
    
    Event::assertDispatched(OrderPlaced::class);
    Event::assertDispatched(OrderPlaced::class, fn(OrderPlaced $e) => $e->order->total === 5000);
    Event::assertListening(OrderPlaced::class, SendOrderConfirmation::class);
});
```

### Notification::fake()
```php
it('sends password reset notification', function () {
    Notification::fake();
    
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);
    
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        fn(ResetPassword $n) => strlen($n->token) === 64
    );
});
```

### Http::fake() — External API
```php
it('creates payment via stripe', function () {
    Http::fake([
        'api.stripe.com/*' => Http::response([
            'id'     => 'ch_123',
            'status' => 'succeeded',
        ], 200),
        'api.other.com/*' => Http::response(['error' => 'Not found'], 404),
        '*' => Http::response('Server Error', 500),  // default
    ]);
    
    $result = app(StripeGateway::class)->charge(5000);
    
    expect($result->transactionId)->toBe('ch_123');
    Http::assertSent(fn(Request $request) => $request->url() === 'https://api.stripe.com/charges');
});
```

### Storage::fake()
```php
it('uploads profile photo', function () {
    Storage::fake('public');
    
    $file = UploadedFile::fake()->image('avatar.jpg');
    $this->post('/profile/photo', ['photo' => $file]);
    
    Storage::disk('public')->assertExists('avatars/' . $file->hashName());
});
```

## Praktik Baxış

### Mock vs Fake: Nə vaxt nə?

| Sual | Cavab |
|------|-------|
| Sadəcə "göndərildi mi?" yoxlayıram? | Fake (Mail::fake) |
| Metod hansı argumentlə çağırıldı? | Mock + expects() |
| Real state lazımdır? | Fake (InMemoryRepo) |
| External API response simulyasiyası? | Http::fake() / Stub |

### "Mock what you own" qaydası
- Özünüzə aid olmayan kod-u (external library, framework) birbaşa mock etmə
- Onun üstündə öz interface-ni yaz, həmin interface-i mock et
```php
// YANLIŞ: Stripe SDK-nı birbaşa mock et
$stripe = $this->createMock(\Stripe\StripeClient::class);

// DÜZGÜN: Öz interface-ni mock et
interface PaymentGatewayInterface {
    public function charge(int $amount): PaymentResult;
}
$gateway = $this->createMock(PaymentGatewayInterface::class);
```

### Over-mocking Anti-pattern
```php
// YANLIŞ: hər şeyi mock et
$repo     = $this->createMock(UserRepository::class);
$hasher   = $this->createMock(Hasher::class);
$notifier = $this->createMock(Notifier::class);
$logger   = $this->createMock(Logger::class);
$cache    = $this->createMock(Cache::class);

// Bu test implementasiya detallarını sınayır, davranışı yox.
// Bir refactor testlərin hamısını qırır.
```

### Trade-off-lar
- **Mock**: Çağırış detalları doğrulanır, amma implementation-a sıx bağlı olur — refactor-da test qırılır.
- **Fake**: Daha real davranış, amma saxlamaq lazım gəlir (InMemoryRepo-nu actual interface ilə sinxron saxla).
- **Laravel Fakes (Mail::fake)**: Ən rahat yol, amma yalnız Laravel komponentlərini əhatə edir.

## Praktik Tapşırıqlar

1. `InMemoryOrderRepository` fake yaz: `save()`, `findById()`, `findPending()` — real interface implementasiyası
2. `Http::fake()` ilə xarici ödəniş gateway testlərini yaz: uğurlu ödəniş, rədd edilmiş kart, timeout
3. `Mail::fake()` + `Queue::fake()` birlikdə: sifarişdən sonra mail queue-ya atıldı mı?
4. Spy pattern: `AuditLog::fake()` olmadığını fərz edərək öz Spy-ını yaz
5. Over-mocking nümunəsi tap, Fake ilə refactor et

## Əlaqəli Mövzular
- [Pest Framework](021-pest-framework.md)
- [TDD](086-tdd.md)
- [Database Testing Strategies](052-database-testing-strategies.md)
- [Integration & Contract Testing](079-integration-and-contract-testing.md)
- [Mutation Testing](206-mutation-testing-infection.md)
