# Code Coverage (Middle)
## İcmal

Code coverage, test suite-in mənbə kodunun nə qədər hissəsini icra etdiyini ölçən metrikdir.
Faiz olaraq ifadə edilir - 80% coverage o deməkdir ki, kodun 80%-i testlər tərəfindən
ən az bir dəfə icra olunub. Bu, test suite-in tam olub-olmadığını qiymətləndirmək üçün
istifadə olunan əsas göstəricidir.

Amma vacib bir nüans var: code coverage yalnız kodun *icra edildiyini* göstərir, *düzgün
test edildiyini* yox. Assert-siz test yüksək coverage verə bilər amma heç bir bug tapmaz.
Coverage zəruri amma kifayət deyil.

### Niyə Code Coverage Vacibdir?

1. **Test boşluqlarını tapır** - Heç test edilməmiş kod hissələrini göstərir
2. **Keyfiyyət ölçüsü** - Test suite-in tam olub-olmadığını qiymətləndirməyə kömək edir
3. **Refactoring güvəni** - Yüksək coverage refactoring-ə cəsarət verir
4. **Standart tələbi** - Çox şirkətlər minimum coverage tələb edir (70-80%)
5. **CI/CD gate** - Pipeline-da coverage düşərsə build fail edir

## Niyə Vacibdir

- **Test boşluqlarını görünən etmək** — Kodun hansı hissəsinin heç test edilmədiyini fayl və sətir səviyyəsində göstərir; manual review ilə tapılması çətin olan boş sahələri üzə çıxarır.
- **CI/CD keyfiyyət gate-i kimi istifadə** — Coverage minimum həddinin altına düşəndə pipeline-ı fail etmək, yeni kod üçün testsiz merge-in qarşısını alır; texniki borcu azaldır.
- **Refactoring zamanı güvən yaratmaq** — Yüksək coverage-ı olan kod bazasında daxili strukturu dəyişdirmək daha təhlükəsizdir; renyesiyanı test suite aşkar edir.
- **Yeni developer onboarding** — Hansı kod hissələrinin test edildiyi aydın olduqda yeni komanda üzvü mövcud testsiz code path-lərini görür və coverage-ı qoruyaraq işə başlayır.
- **Branch coverage ilə edge case-lərin tapılması** — Yalnız line coverage deyil, branch coverage da izlənəndə if/else-in hər iki nəticəsi test olunmağa məcbur olur; gizli logic xətaları üzə çıxır.

## Əsas Anlayışlar

### Coverage Növləri

```
1. Line Coverage (Statement Coverage)
   → Hər kod sətrinin ən az 1 dəfə icra edilməsi
   → Ən sadə və ən çox istifadə olunan
   → "Bu sətir icra olundu?"

2. Branch Coverage (Decision Coverage)
   → Hər if/else şərtinin hər iki nəticəsinin test edilməsi
   → Line coverage-dan daha güclü
   → "if-in true VƏ false halı test olundu?"

3. Path Coverage
   → Bütün mümkün icra yollarının test edilməsi
   → Ən güclü, amma praktikada çətin
   → "Bütün kombinasiyalar test olundu?"

4. Function/Method Coverage
   → Hər funksiyanın ən az 1 dəfə çağırılması
   → Ən zəif ölçü
   → "Bu funksiya çağırıldı?"

5. Condition Coverage
   → Compound condition-ların hər hissəsinin test edilməsi
   → if ($a && $b) → $a=T/$b=T, $a=T/$b=F, $a=F/$b=T, $a=F/$b=F
```

### Coverage Nümunəsi

```php
function processOrder(Order $order): string    // Line 1
{                                               // Line 2
    if ($order->total > 100) {                  // Line 3 - Branch
        $discount = $order->total * 0.1;        // Line 4
        $order->applyDiscount($discount);       // Line 5
    }                                           // Line 6

    if ($order->isPriority()) {                 // Line 7 - Branch
        $order->expediteShipping();             // Line 8
    }                                           // Line 9

    return $order->complete();                  // Line 10
}

// Test: processOrder(total=200, priority=false)
// Line coverage: 8/10 = 80% (line 8,9 icra olunmadı)
// Branch coverage: 2/4 = 50% (if true test olundu, if false test olunmadı yalnız 1-ci üçün)

// Test əlavə: processOrder(total=50, priority=true)
// İndi Line coverage: 10/10 = 100%
// Branch coverage: 4/4 = 100%
```

### 100% Coverage Mifi

```
100% code coverage BUG OLMAMASI deməkdir?
→ XEYR!

Səbəblər:
1. Coverage assert-ləri ölçmür
   → test() { calculate(5); assertTrue(true); } → 100% coverage, 0 bug tapır

2. Edge case-lər miss ola bilər
   → Yalnız $total=200 test edilsə, $total=0 test edilmir

3. Integration bug-ları
   → Hər unit 100% coverage ola bilər, amma birlikdə xəta verər

4. Concurrency issues
   → Single-thread testdə race condition görünmür

5. Business logic errors
   → Yanlış tələb doğru implement oluna bilər

Realist hədəflər:
  70-80% → Çox proyekt üçün yaxşı
  80-90% → Kritik sistemlər üçün
  90%+   → Yalnız çox kritik kodda (ödəniş, təhlükəsizlik)
```

## Praktik Baxış

### Best Practices

1. **Branch coverage izləyin** - Line coverage-dan daha dəyərlidir
2. **Coverage gate CI/CD-yə qoyun** - Coverage düşərsə build fail etsin
3. **Trend izləyin** - Zaman keçdikcə coverage artmalıdır, azalmamalı
4. **Yeni kod üçün yüksək coverage** - Minimum 80% yeni kod üçün
5. **PCOV istifadə edin** - CI/CD-də Xdebug-dan 10x sürətli
6. **HTML report-u baxın** - Hansı sətirlərin test edilmədiyini vizual görün

### Anti-Patterns

1. **Coverage üçün keyfiyyətsiz test yazmaq** - `assertTrue(true)` coverage artırır amma dəyərsizdir
2. **100% hədəfləmək** - Diminishing returns, vaxt itkisi
3. **Coverage-ı yeganə metrik kimi istifadə etmək** - Mutation testing, code review də lazımdır
4. **@codeCoverageIgnore-u sui-istifadə etmək** - Hər yerdə istifadə etməyin
5. **Yalnız ümumi faizə baxmaq** - Fayl/method səviyyəsində analiz edin
6. **Legacy koda yüksək coverage tələb etmək** - Tədricən artırın, birdən yox

## Nümunələr

### PHPUnit Coverage Report

```bash
# HTML report (ən detallı)
vendor/bin/phpunit --coverage-html coverage-report/

# Text report (terminal-da)
vendor/bin/phpunit --coverage-text

# Clover XML (CI/CD üçün)
vendor/bin/phpunit --coverage-clover coverage.xml

# Minimum coverage tələbi
vendor/bin/phpunit --coverage-text --coverage-min=80
```

### phpunit.xml Coverage Konfiqurasiyası

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Console</directory>
            <directory>app/Providers</directory>
            <file>app/Http/Kernel.php</file>
        </exclude>
    </source>

    <coverage>
        <report>
            <html outputDirectory="coverage-report"/>
            <clover outputFile="coverage.xml"/>
            <text outputFile="php://stdout" showOnlySummary="true"/>
        </report>
    </coverage>
</phpunit>
```

## Praktik Tapşırıqlar

### Xdebug və PCOV Müqayisəsi

```bash
# Xdebug - daha çox feature, daha yavaş
# php.ini
# zend_extension=xdebug.so
# xdebug.mode=coverage

# PCOV - yalnız coverage, çox sürətli (10x faster)
# php.ini
# extension=pcov.so
# pcov.enabled=1

# PCOV tövsiyə olunur (yalnız coverage lazımdırsa)
pecl install pcov
```

### Coverage ilə Test Yazma Strategiyası

```php
<?php

namespace App\Services;

class OrderService
{
    public function __construct(
        private PaymentGateway $paymentGateway,
        private InventoryService $inventory,
        private NotificationService $notifications,
    ) {}

    public function processOrder(Order $order): OrderResult
    {
        // Branch 1: Validation
        if ($order->items->isEmpty()) {
            throw new EmptyOrderException('Order has no items');
        }

        // Branch 2: Inventory check
        foreach ($order->items as $item) {
            if (!$this->inventory->isAvailable($item->product_id, $item->quantity)) {
                return new OrderResult(false, "Product {$item->product_id} out of stock");
            }
        }

        // Branch 3: Payment
        $total = $order->calculateTotal();
        $paymentResult = $this->paymentGateway->charge($order->customer, $total);

        if (!$paymentResult->success) {
            return new OrderResult(false, 'Payment failed: ' . $paymentResult->message);
        }

        // Branch 4: Inventory reservation
        foreach ($order->items as $item) {
            $this->inventory->reserve($item->product_id, $item->quantity);
        }

        // Branch 5: Notification
        $this->notifications->sendOrderConfirmation($order);

        return new OrderResult(true, 'Order processed successfully');
    }
}
```

```php
<?php

namespace Tests\Unit;

use App\Services\OrderService;
use App\Services\PaymentGateway;
use App\Services\InventoryService;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private PaymentGateway $paymentGateway;
    private InventoryService $inventory;
    private NotificationService $notifications;

    protected function setUp(): void
    {
        $this->paymentGateway = $this->createMock(PaymentGateway::class);
        $this->inventory = $this->createMock(InventoryService::class);
        $this->notifications = $this->createMock(NotificationService::class);

        $this->service = new OrderService(
            $this->paymentGateway,
            $this->inventory,
            $this->notifications,
        );
    }

    /** @test */
    public function empty_order_throws_exception(): void
    {
        // Branch 1 coverage
        $order = $this->createOrderWithItems([]);

        $this->expectException(EmptyOrderException::class);
        $this->service->processOrder($order);
    }

    /** @test */
    public function out_of_stock_item_returns_failure(): void
    {
        // Branch 2 coverage
        $order = $this->createOrderWithItems([
            ['product_id' => 1, 'quantity' => 5],
        ]);

        $this->inventory->method('isAvailable')->willReturn(false);

        $result = $this->service->processOrder($order);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('out of stock', $result->message);
    }

    /** @test */
    public function failed_payment_returns_failure(): void
    {
        // Branch 3 coverage (failure path)
        $order = $this->createOrderWithItems([
            ['product_id' => 1, 'quantity' => 1],
        ]);

        $this->inventory->method('isAvailable')->willReturn(true);
        $this->paymentGateway->method('charge')
            ->willReturn(new PaymentResult(false, 'Card declined'));

        $result = $this->service->processOrder($order);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Payment failed', $result->message);
    }

    /** @test */
    public function successful_order_processes_completely(): void
    {
        // Branch 3 (success path), Branch 4, Branch 5 coverage
        $order = $this->createOrderWithItems([
            ['product_id' => 1, 'quantity' => 2],
        ]);

        $this->inventory->method('isAvailable')->willReturn(true);
        $this->paymentGateway->method('charge')
            ->willReturn(new PaymentResult(true, 'OK'));

        $this->inventory->expects($this->once())
            ->method('reserve')
            ->with(1, 2);

        $this->notifications->expects($this->once())
            ->method('sendOrderConfirmation');

        $result = $this->service->processOrder($order);

        $this->assertTrue($result->success);
    }

    // 4 test ilə bütün branch-lar cover olundu
}
```

### Coverage Report Nümunəsi (Text)

```
Code Coverage Report:
  2024-01-15 10:30:00

 Summary:
  Classes: 85.00% (17/20)
  Methods: 78.26% (90/115)
  Lines:   82.14% (598/728)

 App\Services\OrderService
  Methods: 100.00% (5/5)  Lines: 95.24% (40/42)
  
 App\Services\PaymentService
  Methods:  80.00% (4/5)  Lines: 72.00% (18/25)
  
 App\Models\User
  Methods:  66.67% (4/6)  Lines: 60.00% (12/20)
```

### CI/CD Coverage Gate

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP with PCOV
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: pcov

      - name: Install dependencies
        run: composer install

      - name: Run tests with coverage
        run: |
          vendor/bin/phpunit \
            --coverage-clover coverage.xml \
            --coverage-text \
            --coverage-min=80

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v4
        with:
          file: ./coverage.xml
          fail_ci_if_error: true
```

### Coverage-dan İstisna Etmə

```php
<?php

namespace App\Services;

class LegacyService
{
    /**
     * @codeCoverageIgnore
     */
    public function deprecatedMethod(): void
    {
        // Köhnə kod, test yazılmır
    }

    public function importantMethod(): string
    {
        // Bu method test edilməlidir
        return 'result';
    }
}

// Annotation ilə
// @codeCoverageIgnoreStart
// ... ignore ediləcək kod ...
// @codeCoverageIgnoreEnd
```

## Ətraflı Qeydlər

### 1. Code coverage nədir və hansı növləri var?
**Cavab:** Code coverage test suite-in mənbə kodunun nə qədərini icra etdiyini ölçən metrikdir. Əsas növləri: Line coverage (hər sətir icra olunub?), Branch coverage (hər if/else test olunub?), Path coverage (bütün icra yolları?), Method coverage (hər method çağırılıb?). Branch coverage line coverage-dan daha güclüdür.

### 2. Niyə 100% code coverage hədəflənməməlidir?
**Cavab:** 100% coverage bug-suz kod demək deyil. Coverage yalnız icra-nı ölçür, doğruluğu yox. Assert-siz test yüksək coverage verir. Edge case-lər, integration bug-ları, concurrency issue-lar coverage-da görünmür. 70-80% daha realist hədəfdir. Vaxtı coverage artırmaq əvəzinə mutation testing və manual review-ə sərf etmək daha faydalıdır.

### 3. Line coverage və branch coverage arasındakı fərq nədir?
**Cavab:** Line coverage: `if ($x > 0) return "positive";` - yalnız true halda 100% line coverage. Branch coverage: həm true, həm false halı test olunmalıdır. Branch coverage daha güclüdür çünki bütün decision nöqtələrinin hər iki nəticəsini test edir. Ternary operator, switch-case, null coalescing hamısı branch-dır.

### 4. PCOV və Xdebug arasındakı fərq nədir?
**Cavab:** Xdebug debug, profiling və coverage dəstəkləyir, amma yavaşdır. PCOV yalnız coverage ölçür, 10x sürətlidir. CI/CD-də yalnız coverage lazımdırsa PCOV, development-də debug lazımdırsa Xdebug istifadə edin. İkisi eyni anda istifadə olunmaz.

### 5. Coverage report-dan hansı məlumat alınır?
**Cavab:** Ümumi coverage faizi, fayl/class/method səviyyəsində coverage, test edilməmiş sətirlər (qırmızı), test edilmiş sətirlər (yaşıl), şərti ifadələrin hansı branch-ının test olunmadığı. HTML report ən detallıdır, CLI-da text report, CI/CD üçün Clover XML istifadə olunur.

### 6. Coverage-ı necə artırarsınız?
**Cavab:** 1) Coverage report-dan test edilməmiş kodları tapın, 2) Branch coverage-a fokuslanın, 3) Edge case testlər əlavə edin, 4) Error/exception path-ları test edin, 5) Coverage gate CI/CD-yə əlavə edin ki, düşməsin, 6) Yeni kod yazdıqda mütləq test yazın. Amma keyfiyyətsiz test yazaraq coverage artırmaq əksinə zərərlidir.

### 7. @codeCoverageIgnore nə zaman istifadə olunmalıdır?
**Cavab:** Legacy kod, getters/setters, framework boilerplate, constructor-lar kimi test dəyəri az olan kodlar üçün. İstifadəni minimuma endirin - hər ignore etdiyiniz kod potensial test edilməmiş bug-dur. Team-də convention müəyyən edin, code review-da ignore istifadəsini yoxlayın.

## Əlaqəli Mövzular

- [Unit Testing (Junior)](02-unit-testing.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Pest PHP (Middle)](14-pest-php.md)
- [Mutation Testing (Senior)](22-mutation-testing.md)
- [Continuous Testing (Senior)](23-continuous-testing.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
