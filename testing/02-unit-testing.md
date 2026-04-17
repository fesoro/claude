# Unit Testing

## Nədir? (What is it?)

Unit testing proqramın ən kiçik test edilə bilən hissəsini (unit) izolə şəkildə
yoxlamaq prosesidir. Bir unit adətən bir metod və ya funksiyadır. Unit testlər
xarici asılılıqlardan (database, API, file system) izolə olmalıdır.

Unit testlər testing piramidanın əsasını təşkil edir. Ən çox sayda unit test
olmalıdır, çünki onlar ən sürətli işləyir, ən ucuzdur və ən dəqiq feedback verir.

Bir bug tapıldıqda unit test dəqiq hansı metodda problem olduğunu göstərir.
Integration test isə yalnız "bir yerdə problem var" deyir.

## Əsas Konseptlər (Key Concepts)

### Yaxşı Unit Testin Xüsusiyyətləri

1. **Fast (Sürətli)** - Millisaniyələr ərzində bitməlidir
2. **Isolated (İzolə)** - Xarici asılılıqlar yoxdur
3. **Repeatable (Təkrarlanabilən)** - Hər zaman eyni nəticə
4. **Self-validating (Öz-özünü yoxlayan)** - Pass/Fail nəticəsi aydındır
5. **Thorough (Ətraflı)** - Edge case-lər daxildir

### AAA Pattern (Arrange-Act-Assert)

Hər test üç hissədən ibarətdir:

```php
public function test_discount_calculation(): void
{
    // Arrange - Test üçün lazım olan hər şeyi hazırla
    $calculator = new PriceCalculator();
    $originalPrice = 100;
    $discountPercent = 20;

    // Act - Test edilən əməliyyatı icra et
    $finalPrice = $calculator->applyDiscount($originalPrice, $discountPercent);

    // Assert - Nəticəni yoxla
    $this->assertEquals(80, $finalPrice);
}
```

### Test Isolation (İzolasiya)

Unit testlər xarici sistemlərdən asılı olmamalıdır:

```php
// YANLIŞ - Database-dən asılıdır
public function test_user_full_name(): void
{
    $user = User::find(1); // Database lazımdır!
    $this->assertEquals('John Doe', $user->fullName());
}

// DOĞRU - İzolə edilmiş
public function test_user_full_name(): void
{
    $user = new User(['first_name' => 'John', 'last_name' => 'Doe']);
    $this->assertEquals('John Doe', $user->fullName());
}
```

### Naming Conventions (Adlandırma Konvensiyaları)

**Snake_case üsulu (PHPUnit-da geniş yayılmış):**
```php
public function test_it_calculates_total_with_tax(): void
public function test_empty_cart_returns_zero(): void
public function test_negative_quantity_throws_exception(): void
```

**Annotation ilə:**
```php
/** @test */
public function it_calculates_total_with_tax(): void
```

**Strukturlaşdırılmış ad:**
```
test_[unit]_[scenario]_[expected_result]
test_calculator_with_negative_numbers_throws_exception
test_user_without_email_fails_validation
```

## Praktiki Nümunələr (Practical Examples)

### Sadə Sinif və Test

```php
// app/Services/ShippingCalculator.php
class ShippingCalculator
{
    public function calculate(float $weight, string $zone): float
    {
        $baseRate = match ($zone) {
            'local' => 5.0,
            'national' => 10.0,
            'international' => 25.0,
            default => throw new InvalidArgumentException("Unknown zone: {$zone}")
        };

        if ($weight <= 0) {
            throw new InvalidArgumentException('Weight must be positive');
        }

        $weightCharge = ceil($weight) * 2;

        return $baseRate + $weightCharge;
    }
}
```

```php
// tests/Unit/Services/ShippingCalculatorTest.php
class ShippingCalculatorTest extends TestCase
{
    private ShippingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ShippingCalculator();
    }

    public function test_local_shipping_for_one_kg(): void
    {
        $cost = $this->calculator->calculate(1.0, 'local');
        $this->assertEquals(7.0, $cost); // 5 base + 2 weight
    }

    public function test_national_shipping_for_three_kg(): void
    {
        $cost = $this->calculator->calculate(3.0, 'national');
        $this->assertEquals(16.0, $cost); // 10 base + 6 weight
    }

    public function test_international_shipping_rounds_weight_up(): void
    {
        $cost = $this->calculator->calculate(2.3, 'international');
        $this->assertEquals(31.0, $cost); // 25 base + ceil(2.3)*2 = 6
    }

    public function test_zero_weight_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be positive');

        $this->calculator->calculate(0, 'local');
    }

    public function test_negative_weight_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(-5, 'local');
    }

    public function test_unknown_zone_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown zone: mars');

        $this->calculator->calculate(1, 'mars');
    }
}
```

### Data Provider ilə Test

```php
class DiscountCalculatorTest extends TestCase
{
    /**
     * @dataProvider discountDataProvider
     */
    public function test_discount_calculation(
        float $price,
        float $discount,
        float $expected
    ): void {
        $calculator = new DiscountCalculator();
        $result = $calculator->apply($price, $discount);
        $this->assertEquals($expected, $result);
    }

    public static function discountDataProvider(): array
    {
        return [
            'no discount' => [100, 0, 100],
            '10% discount' => [100, 10, 90],
            '50% discount' => [200, 50, 100],
            '100% discount' => [100, 100, 0],
            'small amount' => [9.99, 10, 8.99],
        ];
    }
}
```

### Exception Testing

```php
class ValidatorTest extends TestCase
{
    public function test_empty_email_throws_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email is required');
        $this->expectExceptionCode(422);

        $validator = new EmailValidator();
        $validator->validate('');
    }

    public function test_invalid_email_format(): void
    {
        $validator = new EmailValidator();

        try {
            $validator->validate('not-an-email');
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('invalid format', $e->getMessage());
            $this->assertEquals(422, $e->getCode());
        }
    }
}
```

### Dependency Injection ilə Test

```php
// Asılılığı olan sinif
class OrderService
{
    public function __construct(
        private TaxCalculator $taxCalculator,
        private DiscountService $discountService,
    ) {}

    public function calculateTotal(Order $order): float
    {
        $subtotal = $order->subtotal();
        $discount = $this->discountService->getDiscount($order);
        $afterDiscount = $subtotal - $discount;
        $tax = $this->taxCalculator->calculate($afterDiscount);

        return $afterDiscount + $tax;
    }
}

// Unit test - asılılıqlar mock edilir
class OrderServiceTest extends TestCase
{
    public function test_total_with_discount_and_tax(): void
    {
        // Arrange
        $taxCalculator = Mockery::mock(TaxCalculator::class);
        $taxCalculator->shouldReceive('calculate')
            ->with(90.0)
            ->andReturn(16.2); // 18% tax

        $discountService = Mockery::mock(DiscountService::class);
        $discountService->shouldReceive('getDiscount')
            ->andReturn(10.0);

        $order = new Order(['subtotal' => 100.0]);

        $service = new OrderService($taxCalculator, $discountService);

        // Act
        $total = $service->calculateTotal($order);

        // Assert
        $this->assertEquals(106.2, $total);
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### PHPUnit Əsasları

```php
use PHPUnit\Framework\TestCase;

class MathTest extends TestCase
{
    // Əsas assertions
    public function test_assertions_overview(): void
    {
        // Bərabərlik
        $this->assertEquals(4, 2 + 2);
        $this->assertNotEquals(5, 2 + 2);

        // Dəqiq bərabərlik (type da yoxlanır)
        $this->assertSame(4, 2 + 2);       // int === int
        $this->assertNotSame('4', 2 + 2);  // string !== int

        // Boolean
        $this->assertTrue(true);
        $this->assertFalse(false);

        // Null
        $this->assertNull(null);
        $this->assertNotNull('value');

        // Array
        $this->assertCount(3, [1, 2, 3]);
        $this->assertContains(2, [1, 2, 3]);
        $this->assertArrayHasKey('name', ['name' => 'John']);
        $this->assertEmpty([]);

        // String
        $this->assertStringContainsString('world', 'hello world');
        $this->assertStringStartsWith('hello', 'hello world');
        $this->assertMatchesRegularExpression('/^\d+$/', '123');

        // Type
        $this->assertInstanceOf(Collection::class, collect());

        // Comparison
        $this->assertGreaterThan(5, 10);
        $this->assertLessThanOrEqual(10, 10);
    }
}
```

### Laravel Unit Test Nümunəsi

```php
// tests/Unit/Models/UserTest.php
namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase; // Laravel TestCase deyil!

class UserTest extends TestCase
{
    public function test_full_name_returns_combined_names(): void
    {
        $user = new User();
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->assertEquals('John Doe', $user->fullName());
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_regular_user(): void
    {
        $user = new User();
        $user->role = 'user';

        $this->assertFalse($user->isAdmin());
    }

    public function test_initials(): void
    {
        $user = new User();
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->assertEquals('JD', $user->initials());
    }
}
```

### Value Object Test

```php
// app/ValueObjects/Money.php
class Money
{
    public function __construct(
        private int $amount,
        private string $currency = 'USD'
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException();
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function amount(): int { return $this->amount; }
    public function currency(): string { return $this->currency; }
    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}

// tests/Unit/ValueObjects/MoneyTest.php
class MoneyTest extends TestCase
{
    public function test_can_create_money(): void
    {
        $money = new Money(100, 'USD');
        $this->assertEquals(100, $money->amount());
        $this->assertEquals('USD', $money->currency());
    }

    public function test_negative_amount_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-1);
    }

    public function test_addition(): void
    {
        $a = new Money(100, 'USD');
        $b = new Money(50, 'USD');
        $result = $a->add($b);

        $this->assertEquals(150, $result->amount());
        $this->assertEquals('USD', $result->currency());
    }

    public function test_cannot_add_different_currencies(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        $usd = new Money(100, 'USD');
        $eur = new Money(50, 'EUR');
        $usd->add($eur);
    }

    public function test_equality(): void
    {
        $a = new Money(100, 'USD');
        $b = new Money(100, 'USD');
        $c = new Money(200, 'USD');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
```

## Interview Sualları

**S: Unit test ilə integration test arasındakı fərq nədir?**
C: Unit test tək bir metod/funksiyanı izolə şəkildə test edir, xarici asılılıqlar mock
edilir. Integration test isə bir neçə komponentin birlikdə düzgün işlədiyini yoxlayır,
real database və ya real servislər istifadə oluna bilər.

**S: AAA pattern nədir?**
C: Arrange-Act-Assert. Arrange: test data hazırlanır. Act: test edilən əməliyyat icra
olunur. Assert: nəticə yoxlanılır. Bu pattern testləri oxunaqlı və strukturlu edir.

**S: Niyə unit testlər izolə olmalıdır?**
C: İzolasiya olmadan: testlər yavaşlayır (database), testlər flaky olur (network),
test uğursuzluğunun səbəbini tapmaq çətinləşir, testlər parallel işləyə bilmir.

**S: Data Provider nə üçün istifadə olunur?**
C: Eyni test məntiqi ilə müxtəlif inputları yoxlamaq üçün. Kod dublikatını azaldır,
edge case-ləri əlavə etməyi asanlaşdırır.

**S: setUp() metodu nə üçün istifadə olunur?**
C: Hər testdən əvvəl çalışır. Ümumi test fixturelarını hazırlamaq üçündür.
Test siniflərində təkrarlanan Arrange hissəsini setUp-a çıxarmaq olar.

## Best Practices / Anti-Patterns

### Best Practices
- Hər test metodu yalnız bir davranışı test etsin
- Test adları nəyi test etdiyini aydın izah etsin
- Testlər sürətli olsun (unit test suite saniyələr ərzində bitməli)
- Public API-nı test et, private metodları deyil
- Magic number-lar əvəzinə named constants istifadə et
- Edge case-ləri test et: null, empty, boundary, overflow

### Anti-Patterns
- **Brittle tests**: Implementasiya dəyişdikdə sınan testlər
- **Testing private methods**: Reflection ilə private metodları test etmək
- **No assertion**: Test var amma assert yoxdur
- **Too many assertions**: Bir testdə 20 assert
- **Test logic**: Testdə if/else/loop istifadəsi
- **Hard-coded paths**: `/home/user/test.txt` kimi sabit yollar
