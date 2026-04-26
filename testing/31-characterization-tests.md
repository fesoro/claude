# Characterization Tests (Senior)
## İcmal

**Characterization Tests** (həmçinin **Golden Master Tests** və ya **Pinning Tests** adlanır) - **mövcud legacy kodun cari davranışını sənədləşdirmək və qorumaq** üçün yazılan testlərdir. Məqsəd: kod "doğru"dur demək deyil, sadəcə **hal-hazırda necə davranır** qeyd etmək və refactor zamanı bu davranışın dəyişməməsini təmin etmək.

**Michael Feathers** bu konsepti məşhur kitabı **"Working Effectively with Legacy Code"** (2004) -da təqdim edib.

**Əsas fərq:**

| Unit Test | Characterization Test |
|-----------|----------------------|
| Gözlənilən davranışı təsdiq edir | Cari davranışı sənədləşdirir |
| Requirement-dən yaranır | Koddan yaranır |
| "Necə olmalıdır?" | "Necə işləyir?" |
| Bug fix üçün refactor lazımdır | Safety net kimi istifadə olunur |

**Michael Feathers-ın tərifi:**

> "Legacy code - testləri olmayan koddur."

## Niyə Vacibdir

- **Legacy kod refactoring-i mümkün edir:** Real layihələrdə köhnə, sənədləşdirilməmiş kod hər zaman mövcuddur. Characterization test olmadan refactoring həmişə regression riski daşıyır — bu testlər ilk dəfə qoruyucu şəbəkə yaradır.
- **Qorxusuzca dəyişdirmə imkanı:** Legacy controller və ya service-i dəyişdirərkən test suite yaşıl qaldıqca heç bir regression olmadığına əmin olursunuz. Bu, yavaş və ehtiyatlı iş əvəzinə sürətli refactoring imkanı verir.
- **Production bug-larının dokumentasiyası:** Buggy davranışı "expected" kimi qeyd etmək, sonradan nə dəyişdirildiyi barədə tarix yaradır. Bug düzəldildikdə test-lər dəyişir — bu, audit trail rolunu oynayır.
- **Sprout method ilə təhlükəsiz genişləndirmə:** Mövcud legacy metodları dəyişdirmədən, yeni funksionallığı ayrıca test edilə bilən method kimi yazmaq mümkün olur. Bu, legacy sistemi qırmadan yeni feature əlavə etməyin ən praktik yoludur.
- **Yeni komandalarda bilik transferi:** Characterization test-lər "kod necə işləyir?" sualına cavab verir. Yeni developer-lər bu testlər vasitəsilə mövcud davranışı tez başa düşür, sənədləşdirilməmiş kodun mənasını kəşf edir.

## Əsas Anlayışlar

### 1. Characterization Test Yaratma Prosesi

**Addım-addım:**

1. Kodu run et və çıxışını qeyd et (print, log, dump)
2. Bu çıxışı "expected" kimi test-ə qoy
3. Test pass olursa - **snapshot alındı**
4. Əgər çıxış gözlənilməzdirsə belə, olduğu kimi qeyd et
5. Bug-ları **sonra** düzəlt - əvvəlcə safety net qur

```php
public function testLegacyCalculation(): void
{
    $result = legacyCalculate(100, 0.15);
    // Actual output-u qeyd et, mənasız olsa belə
    $this->assertEquals(114.9999999998, $result);
}
```

### 2. Pinning Tests

**Pin** - kodun cari davranışını **yerində sabitləmək** deməkdir. Pinning test-lər refactoring-dən əvvəl yazılır ki, dəyişikliklər davranışı dəyişdirsə dərhal görünsün.

### 3. Safety Net

Characterization test-lər **təhlükəsizlik şəbəkəsi** yaradır:

- Refactor zamanı hər hansı regression dərhal görünür
- Qorxusuzca kodu dəyişdirə bilərsiniz
- Test green qaldıqca davranış dəyişməyib

### 4. Seam (Birləşmə nöqtəsi)

**Seam** - kodda bir yer ki, davranışı **dəyişmədən** dəyişdirə bilərsiniz. Seam-lər əsasən dependency inject etmək üçün istifadə olunur.

**Seam növləri:**

- **Preprocessor Seam:** `#ifdef` (C/C++)
- **Link Seam:** kitabxana dəyişdirmək (PHP autoload)
- **Object Seam:** polymorphism ilə dəyişdirmək
- **Language Seam:** global function override

**PHP-də Object Seam nümunəsi:**

```php
// Əvvəl (test edə bilmirik)
class OrderService {
    public function send() {
        $api = new StripeApi();  // Hard dependency
        $api->charge();
    }
}

// Sonra (Object Seam)
class OrderService {
    public function __construct(private PaymentApi $api) {}

    public function send() {
        $this->api->charge();  // Inject-ed, mock-lana bilər
    }
}
```

### 5. Sprout Method və Sprout Class

Feathers-ın texnikaları:

- **Sprout Method:** yeni funksionallıq üçün yeni method yarat və onu test et, sonra legacy metoddan çağır
- **Sprout Class:** yeni class yarat, legacy-də istifadə et
- **Wrap Method:** mövcud method-u wrap edib əlavə davranış əlavə et

## Praktik Baxış

### Best Practices

1. **Əvvəlcə characterization, sonra refactor** - güvənli safety net qurun
2. **Bug-ları qeyd edin** - şərhlə "known bug" yazın, sonra düzəldin
3. **Kiçik seam yaradın** - böyük rewrite əvəzinə incremental
4. **Hər input combination-ı test edin** - data provider-lərdən istifadə
5. **Log-based testing** - side effect-lər üçün event log yaradın
6. **Golden master** - kompleks output üçün

### Anti-Patterns

1. **Legacy kodu test yazmadan refactor etmək** - regression qaçılmazdır
2. **"Yaxşı bug" düzəltmə** - characterization yaratmadan
3. **Bir neçə günə hər şeyi rewrite** - klassik failure pattern
4. **Sadəcə happy path test** - edge case-lər bug yuvasıdır
5. **Mocking everything** - legacy kodun real davranışı itir

### Feathers-ın Algoritmi

1. Dəyişiklik nöqtələrini tap (change points)
2. Test point-ləri tap (test points)
3. Dependency-ləri sındır (break dependencies)
4. Testləri yaz
5. Refactor və change et

### Vacib Kitablar

- **Working Effectively with Legacy Code** - Michael Feathers (2004)
- **Refactoring** - Martin Fowler (2nd ed, 2018)
- **Refactoring to Patterns** - Joshua Kerievsky

## Nümunələr

### Legacy Function-a Characterization Test

```php
<?php

namespace Legacy;

function calculateShipping(array $order): float
{
    $total = 0;
    foreach ($order['items'] as $item) {
        $total += $item['price'] * $item['qty'];
    }

    if ($total > 100) {
        $total *= 0.9;
    } elseif ($total > 50) {
        $total *= 0.95;
    }

    if ($order['country'] === 'US') {
        $shipping = 5;
    } elseif (in_array($order['country'], ['UK', 'DE', 'FR'])) {
        $shipping = 15;
    } else {
        $shipping = 25;
    }

    if ($total > 200) {
        $shipping = 0;
    }

    return $total + $shipping;
}
```

```php
<?php

namespace Tests\Characterization;

use PHPUnit\Framework\TestCase;
use function Legacy\calculateShipping;

class ShippingCharacterizationTest extends TestCase
{
    /**
     * @dataProvider shippingScenarios
     */
    public function testCalculateShippingCurrentBehavior(
        array $order,
        float $expected
    ): void {
        $result = calculateShipping($order);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public static function shippingScenarios(): array
    {
        return [
            'US small order' => [
                ['items' => [['price' => 10, 'qty' => 2]], 'country' => 'US'],
                25.0,
            ],
            'UK medium order (95% discount)' => [
                ['items' => [['price' => 60, 'qty' => 1]], 'country' => 'UK'],
                72.0,
            ],
            'DE large order (90% discount + free shipping)' => [
                ['items' => [['price' => 300, 'qty' => 1]], 'country' => 'DE'],
                270.0,
            ],
            'Other country empty order' => [
                ['items' => [], 'country' => 'AZ'],
                25.0,
            ],
            'Boundary at 50' => [
                ['items' => [['price' => 50, 'qty' => 1]], 'country' => 'US'],
                55.0,
            ],
            'Boundary at 100' => [
                ['items' => [['price' => 100, 'qty' => 1]], 'country' => 'US'],
                100.0,
            ],
        ];
    }
}
```

## Praktik Tapşırıqlar

### Legacy Controller-i Pin Etmək

```php
<?php

namespace App\Http\Controllers;

class LegacyReportController
{
    public function generate(Request $request)
    {
        $userId = $request->get('user_id');
        $type = $request->get('type', 'monthly');

        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            return response('User not found', 404);
        }

        $startDate = $type === 'monthly'
            ? now()->subMonth()
            : now()->subYear();

        $orders = DB::table('orders')
            ->where('user_id', $userId)
            ->where('created_at', '>', $startDate)
            ->get();

        $total = 0;
        $count = 0;
        foreach ($orders as $order) {
            if ($order->status === 'completed') {
                $total += $order->amount;
                $count++;
            }
        }

        return [
            'user' => $user->name,
            'period' => $type,
            'orders_count' => $count,
            'total' => $total,
            'average' => $count > 0 ? $total / $count : 0,
        ];
    }
}
```

```php
<?php

namespace Tests\Characterization;

use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testMonthlyReportForUserWithOrders(): void
    {
        $this->travelTo('2024-06-15 12:00:00');

        $user = User::factory()->create(['id' => 1, 'name' => 'John Doe']);

        Order::factory()->create([
            'user_id' => 1,
            'amount' => 100,
            'status' => 'completed',
            'created_at' => '2024-06-01',
        ]);

        Order::factory()->create([
            'user_id' => 1,
            'amount' => 200,
            'status' => 'completed',
            'created_at' => '2024-06-10',
        ]);

        Order::factory()->create([
            'user_id' => 1,
            'amount' => 50,
            'status' => 'pending',
            'created_at' => '2024-06-05',
        ]);

        $response = $this->get('/report?user_id=1&type=monthly');

        $response->assertExactJson([
            'user' => 'John Doe',
            'period' => 'monthly',
            'orders_count' => 2,
            'total' => 300,
            'average' => 150,
        ]);
    }

    public function testReportForNonexistentUser(): void
    {
        $response = $this->get('/report?user_id=999');

        $response->assertStatus(404);
        $this->assertEquals('User not found', $response->content());
    }

    public function testReportWithNoOrders(): void
    {
        User::factory()->create(['id' => 2, 'name' => 'Jane']);

        $response = $this->get('/report?user_id=2');

        $response->assertExactJson([
            'user' => 'Jane',
            'period' => 'monthly',
            'orders_count' => 0,
            'total' => 0,
            'average' => 0,
        ]);
    }
}
```

### Golden Master Technique

```php
<?php

namespace Tests\Characterization;

use PHPUnit\Framework\TestCase;

class GoldenMasterTest extends TestCase
{
    private const GOLDEN_DIR = __DIR__ . '/golden';

    /**
     * @dataProvider inputCombinations
     */
    public function testLegacyCalculatorOutput(int $a, int $b, string $op): void
    {
        $goldenFile = self::GOLDEN_DIR . "/{$a}_{$b}_{$op}.txt";

        $actual = legacyCalculator($a, $b, $op);

        if (!file_exists($goldenFile)) {
            if (!is_dir(self::GOLDEN_DIR)) {
                mkdir(self::GOLDEN_DIR, 0755, true);
            }
            file_put_contents($goldenFile, (string)$actual);
            $this->markTestSkipped("Created golden file: {$goldenFile}");
        }

        $expected = file_get_contents($goldenFile);
        $this->assertEquals($expected, (string)$actual);
    }

    public static function inputCombinations(): array
    {
        $cases = [];
        foreach ([-10, -1, 0, 1, 10, 100] as $a) {
            foreach ([-5, 0, 5] as $b) {
                foreach (['add', 'sub', 'mul', 'div'] as $op) {
                    $cases["a={$a} b={$b} op={$op}"] = [$a, $b, $op];
                }
            }
        }
        return $cases;
    }
}
```

### Sprout Method Pattern

```php
<?php

class LegacyOrderProcessor
{
    public function process(array $orderData): array
    {
        // 500 sətirlik kompleks legacy kod...
        $validation = $this->validateLegacy($orderData);
        if (!$validation['ok']) {
            return ['error' => $validation['msg']];
        }

        // YENİ FUNKSİONALLIQ - Sprout Method
        $this->logAuditEvent($orderData);

        // Davam edən legacy kod...
        return ['status' => 'processed'];
    }

    // Bu yeni method TESTED yazılır
    public function logAuditEvent(array $orderData): void
    {
        AuditLog::create([
            'event' => 'order.process',
            'data' => json_encode($orderData),
            'timestamp' => now(),
        ]);
    }
}
```

```php
public function testSproutMethodIsTestable(): void
{
    $processor = new LegacyOrderProcessor();
    $processor->logAuditEvent(['id' => 1, 'amount' => 100]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'order.process',
    ]);
}
```

### Seam Yaratmaq

**Əvvəl (test edə bilmirik):**

```php
class EmailSender
{
    public function send(string $to, string $message): bool
    {
        $smtp = new \SMTPClient('mail.example.com', 587);
        $smtp->login('user', 'pass');
        return $smtp->send($to, $message);
    }
}
```

**Sonra (Seam əlavə edildi):**

```php
class EmailSender
{
    public function __construct(
        private SMTPClientFactory $factory
    ) {}

    public function send(string $to, string $message): bool
    {
        $smtp = $this->factory->create();
        return $smtp->send($to, $message);
    }
}

// Factory - seam
class SMTPClientFactory
{
    public function create(): SMTPClient
    {
        return new SMTPClient('mail.example.com', 587);
    }
}

// Test
public function testEmailSender(): void
{
    $mockFactory = $this->createMock(SMTPClientFactory::class);
    $mockClient = $this->createMock(SMTPClient::class);
    $mockClient->expects($this->once())->method('send')->willReturn(true);
    $mockFactory->method('create')->willReturn($mockClient);

    $sender = new EmailSender($mockFactory);
    $result = $sender->send('test@example.com', 'Hello');

    $this->assertTrue($result);
}
```

### Log-Based Characterization

```php
<?php

namespace Tests\Characterization;

class LogBasedCharacterizationTest extends TestCase
{
    public function testCapturesAllSideEffects(): void
    {
        $events = [];
        $capture = function ($event) use (&$events) {
            $events[] = $event;
        };

        $service = new LegacyService();
        $service->onEvent($capture);

        $service->doComplexOperation(['input' => 'data']);

        $this->assertEquals([
            ['type' => 'started', 'input' => 'data'],
            ['type' => 'validated'],
            ['type' => 'transformed', 'output' => 'DATA'],
            ['type' => 'saved', 'id' => 1],
            ['type' => 'completed'],
        ], $events);
    }
}
```

## Ətraflı Qeydlər

### 1. Characterization test nədir və ənənəvi unit test-dən fərqi?

Characterization test **mövcud kodun cari davranışını** sənədləşdirir, requirement-dən yaranmır. Məqsəd davranışı "pin" etmək və refactor zamanı regression-u yoxlamaqdır. Traditional test "necə olmalıdır" yoxlayır, characterization "necə işləyir" qeyd edir.

### 2. Legacy kod nə deməkdir (Michael Feathers-a görə)?

Feathers-ın məşhur tərifi: **"Legacy code - testləri olmayan koddur."** Yaş və ya texnologiya fərqi yoxdur. 3 gün əvvəl test yazılmadan yazılmış kod da legacy-dir.

### 3. Seam nədir və hansı növləri var?

**Seam** - davranışı dəyişmədən kodu dəyişdirə biləcəyin yerdir. Növlər: **Preprocessor Seam** (C/C++), **Link Seam** (kitabxana əvəzləmə), **Object Seam** (polymorphism), **Language Seam** (function override). Test yazmaq üçün seam axtarırıq.

### 4. Pinning test nə üçün lazımdır?

Refactor-dan **əvvəl** yazılır ki, dəyişikliklərin davranışa təsir etmədiyini yoxlasın. Təhlükəsizlik şəbəkəsi yaradır. Test passdursa refactor təhlükəsizdir.

### 5. Sprout Method texnikası nədir?

Legacy metoda yeni funksionallıq əlavə edəndə, **yeni kod ayrıca method**-da yazılır (test oluna bilən) və legacy metoddan çağırılır. Bu yolla yeni kod hissəsi TDD ilə yazıla bilir.

### 6. Golden Master nədir?

Kompleks legacy kodun **bir çox inputlarla çıxışını faylda saxlamaq** və sonrakı run-larda müqayisə etmək texnikasıdır. İlk run-da "golden master" yaranır, sonrakı run-larda bu baseline ilə müqayisə edilir.

### 7. Bug-lı legacy kodu characterization test-ə necə yazırıq?

**Bug-lı davranışı olduğu kimi qeyd edin**, sonra düzəldin. Test-ə `// BUG: should be X, but is Y` şərhi yazın. Əvvəlcə safety net qurun, sonra bug-ları düzəldin. Test əvvəl pass olmalıdır, sonra fix-lə dəyişməlidir.

### 8. Characterization test-lər nə qədər saxlanmalıdır?

- Əgər kod hələ legacy-dirsə: **qalsın**
- Kod refactor edilib normal test-lər yazılıbsa: **silin və əvəz edin**
- Bəzi characterization test-lər qalıcı integration test-ə çevrilir

### 9. Testable olmayan kodu necə test edərik?

- **Seam tap** (constructor injection, factory method)
- **Static-ləri avoid et** - wrap in instance method
- **Globals-i inject et**
- **Subclass yarat** testing üçün (Extract and Override)
- **Sprout method** yeni davranış üçün

### 10. "Working Effectively with Legacy Code" kitabı niyə vacibdir?

Michael Feathers bu kitabda **testi olmayan kodla işləmə metodologiyasını** formal həll edir. Seam, sprout method, characterization test kimi konseptlər burada yaradılıb. Legacy kodla işləyən hər developer üçün essential.

## Əlaqəli Mövzular

- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
- [Test Patterns (Senior)](26-test-patterns.md)
- [Approval Testing (Senior)](32-approval-testing.md)
- [Mocking (Middle)](07-mocking.md)
- [Snapshot Testing (Senior)](25-snapshot-testing.md)
- [Continuous Testing (Senior)](23-continuous-testing.md)
