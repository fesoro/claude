# Mocking Strategies (Senior ⭐⭐⭐)

## İcmal
Mocking — test zamanı real dependency-ləri idarə olunan test double-larla əvəz etmə texnikasıdır. Düzgün mock strategiyası testləri sürətli, reliable və maintainable saxlayır; yanlış istifadəsi isə false confidence yaradan, refactoring-i çətinləşdirən brittle test suite-lərə gətirir. Senior interview-larda bu mövzu tez-tez "testlərinizdə mock-dan nə vaxt istifadə edirsiniz?" şəklində gəlir.

## Niyə Vacibdir
Mock-ları düzgün istifadə etmək test engineering-in ən incə bacarıqlarından biridir. "Hər şeyi mock et" düşüncəsi implementation-coupled testlərə aparır. "Mock etmə" düşüncəsi isə yavaş, flaky integration testlərə. Interviewer bu balansı başa düşüb-düşmədiyinizi yoxlayır. Real senarioda "bu testi niyə mock etdiniz?" sualına konkret cavab vermək lazımdır.

## Əsas Anlayışlar

### Test Double Növləri (Martin Fowler təsnifatı):

**Dummy Object:**
- Heç istifadə edilmir, yalnız parameter slot-unu doldurur
- Nümunə: `createUser(name, email, logger)` — logger test üçün lazım deyilsə `null` keç
- Nə zaman: Parametr tələb olunur, amma test loqikasında rolu yoxdur

**Stub:**
- Öncədən müəyyən olunmuş dəyər qaytarır
- Verify etmir — yalnız data təmin edir
- Nümunə: `$repo->find(1)` → həmişə eyni `User` obyektini qaytar
- Nə zaman: Test üçün spesifik input lazımdır, amma call count vacib deyil

**Mock:**
- Gözlənilən çağırışları izləyir və verify edir
- "Bu method dəqiqən bir dəfə çağırılmalıdır" assertiyonu
- Nümunə: `$mailer->send()` metodunun dəqiqən bir dəfə çağırıldığını yoxla
- Nə zaman: Side effect-in baş verdiyini (email göndərildi, event yayımlandı) test etmək üçün

**Spy:**
- Real implementasiyanın üzərinə çağırışları qeyd edir
- Mock-dan fərqi: real behavior saxlanır, ancaq çağırışlar izlənir
- Nümunə: Real cache-i sarıb `get` neçə dəfə çağırıldığını izlə
- Nə zaman: Real implementasiya lazımdır, amma interaction da vacibdir

**Fake:**
- Sadə, işləyən alternativ implementasiya
- Production-da istifadəyə yararsız, amma test üçün realistic
- Nümunə: In-memory repository, in-memory cache, in-memory message bus
- Nə zaman: Bir çox test eyni "fake" implementasiyadan istifadə edəcəksə

### Mock vs Stub — Vacib Fərq:
```
Stub   → State verification: Sonunda sistem hansı vəziyyətdədir?
Mock   → Behavior verification: Düzgün metodlar çağırıldımı?
```

### Over-Mocking Anti-Pattern:

**Əlamətlər:**
- Hər class constructor-da 5+ mock
- Test içindəki `->expects()` zəncirləri setup kodu 30 sətri aşır
- Refactoring etdikdə test mock chain-i "dəqiq bilir" — test qırılır
- Testi oxumaqla "nə test edir" aydın olmur

**Nəticələri:**
- Implementation details-ə bağlı testlər
- Refactoring-i çətinləşdirir (test coverage var, amma dəyişiklik qorxusu var)
- False confidence: Mock-lar düzgün davranmadığı üçün real sistem fərqli davranır

**Həll:**
- Yüksək fan-out olan test double-ları Fake ilə əvəz et
- `Fake*` class-lar yaradaraq layihədə paylaş
- Mock yalnız "truly external" — email, SMS, payment gateway — üçün

### Mock-dan Qaçınma Texnikaları:

**In-Memory Fake Repository:**
```php
class FakeUserRepository implements UserRepositoryInterface
{
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function find(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }
}
```

**Builder Pattern ilə test data:**
```php
$user = UserBuilder::aUser()
    ->withEmail('test@example.com')
    ->withRole('admin')
    ->build();
```

**Object Mother Pattern:**
```php
class UserMother
{
    public static function admin(): User { ... }
    public static function guest(): User { ... }
    public static function suspended(): User { ... }
}
```

### Mock Framework-lər (PHP kontekstində):
- **PHPUnit built-in**: `$this->createMock()`, `$this->createStub()`
- **Mockery**: Daha expressivə syntax, partial mock dəstəği
- **Prophecy** (PHPSpec): Magical prophecy API
- **Laravel Facades**: `Mail::fake()`, `Queue::fake()`, `Event::fake()`

### Laravel Fake Helpers:
```php
// Event fake — real event dispatch olmaması
Event::fake();
// ... action
Event::assertDispatched(UserRegistered::class);

// Queue fake
Queue::fake();
// ... action
Queue::assertPushed(SendWelcomeEmail::class);

// HTTP fake
Http::fake([
    'api.stripe.com/*' => Http::response(['id' => 'cus_test'], 200),
]);
```

### Test Isolation Prinsipləri:
- Hər test öz test double-larını yaratmalıdır (shared state problemi)
- `setUp()` metodunda ümumi fixture-lar, test metod içində spesifik davranışlar
- Mock-ların default davranışını açıq şəkildə müəyyənləşdir

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Mock-u nə vaxt istifadə edirsiniz?" sualına "həmişə" ya da "heç vaxt" demə. Konkret kriteriyaları söylə: "External service, filesystem, clock — bunlar üçün mock/fake istifadə edirəm. Database üçün isə in-memory fake ya da real test DB tercih edirəm. Mock yalnız interaction verify etmək lazım olduqda."

**Follow-up suallar:**
- "Mock vs Fake arasında nə vaxt seçim edirsiniz?"
- "Over-mocking problemi ilə rastlaşdınızmı?"
- "Laravel-də `Mail::fake()` nədir?"

**Ümumi səhvlər:**
- Mock edə-edə "testable code" yaratmaq yerinə, dependency injection-ı düzgün qurmamaq
- Mock assertiyonlarını test-in sonuna qoymamaq (AAA pattern pozulur)
- Verify etmək lazım olmayan mock-ları mock etmək (stub yetər)
- Interface olmadan class-ı mock etmək (tight coupling)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Mock, Stub, Fake fərqini bilmək" yaxşı cavabdır. "Nə vaxt Fake, nə vaxt Mock seçərəm və niyə over-mocking problemi yaradır" isə əla cavabdır.

## Nümunələr

### Tipik Interview Sualı
"Unit test-lərdə database-i mock etmək düzgündürmü?"

### Güclü Cavab
"Database-i mock etmək mümkündür, amma çox vaxt tövsiyə edilmir. Birincisi, database SQL dialektinə, constraint-lərə, transaction semantikasına görə davranır — mock bu detalları qaçırır. İkincisi, mock etmək ORM query interfeysinə bağlı edir — refactoring-də testlər qırılır. Mən database üçün ya real test database ilə integration test yazıram (RefreshDatabase), ya da in-memory Fake repository implementasiyası yaradıram. Mock-ı isə gerçəkdən external olan şeylər üçün saxlayıram: email, SMS, third-party payment API."

### Kod Nümunəsi (PHP)

```php
// YANLISH: Database-i mock etmək — çox brittle
class OrderServiceTest extends TestCase
{
    public function test_order_created_WRONG(): void
    {
        $db = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        // Bu mock, real SQL davranışını əks etdirmir
        $db->expects($this->once())
           ->method('prepare')
           ->willReturn($stmt);

        // Refactoring-də dərhal qırılır
        $stmt->expects($this->once())->method('execute');
    }
}

// DOĞRU: Fake Repository + Mock yalnız real external üçün
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(int $id): ?Order;
}

class FakeOrderRepository implements OrderRepositoryInterface
{
    private array $orders = [];
    private int $nextId = 1;

    public function save(Order $order): void
    {
        if (!$order->id) {
            $order->id = $this->nextId++;
        }
        $this->orders[$order->id] = $order;
    }

    public function findById(int $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function count(): int
    {
        return count($this->orders);
    }
}

class OrderServiceTest extends TestCase
{
    private FakeOrderRepository $repository;
    private OrderService $service;

    protected function setUp(): void
    {
        $this->repository = new FakeOrderRepository();
        // PaymentGateway — external service, mock məqsədəuyğundur
        $paymentGateway = $this->createStub(PaymentGatewayInterface::class);
        $paymentGateway->method('charge')->willReturn(new PaymentResult(success: true));

        $this->service = new OrderService($this->repository, $paymentGateway);
    }

    public function test_order_saved_after_successful_payment(): void
    {
        $this->service->placeOrder(userId: 1, amount: 99.99);

        $this->assertEquals(1, $this->repository->count());
    }

    public function test_order_not_saved_when_payment_fails(): void
    {
        $failingGateway = $this->createStub(PaymentGatewayInterface::class);
        $failingGateway->method('charge')->willReturn(new PaymentResult(success: false));

        $service = new OrderService($this->repository, $failingGateway);

        try {
            $service->placeOrder(userId: 1, amount: 99.99);
        } catch (PaymentFailedException) {}

        $this->assertEquals(0, $this->repository->count());
    }
}
```

## Praktik Tapşırıqlar
- Mövcud test suite-də over-mocking nümunəsi tap: Fake ilə əvəz et
- Bir external dependency üçün Fake implementasiyası yaz (məs: FakeEmailService)
- Laravel-də `Event::fake()`, `Queue::fake()`, `Mail::fake()` istifadə edən testlər yaz
- Mock-la yazılmış bir testi refactor et — Stub istifadə etmək mümkündürmü?

## Əlaqəli Mövzular
- [02-unit-integration-e2e.md](02-unit-integration-e2e.md) — Mock hansı test növündə istifadə olunur
- [03-tdd-approach.md](03-tdd-approach.md) — TDD-də mock-un rolu
- [10-flaky-tests.md](10-flaky-tests.md) — Yanlış mock-ların flaky test yaratması
- [07-contract-testing.md](07-contract-testing.md) — Mock əvəzinə contract testing
