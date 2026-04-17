# Mutation Testing

## Nədir? (What is it?)

Mutation testing, test suite-in keyfiyyətini ölçmək üçün istifadə olunan bir texnikadır.
Əsas fikir belədir: kodda kiçik dəyişikliklər (mutasiyalar) edilir və testlərin bu
dəyişiklikləri tutub-tutmadığı yoxlanır. Əgər test suite yaxşıdırsa, mutasiya edilmiş
kod testləri fail etdirməlidir.

Code coverage yalnız kodun icra edildiyini göstərir, amma düzgün assert edilib-edilmədiyini
göstərmir. Mutation testing bu boşluğu doldurur - testlərin həqiqətən bug-ları tapa
biləcəyini yoxlayır.

### Niyə Mutation Testing Vacibdir?

1. **Test keyfiyyəti** - Code coverage-dan daha dəqiq keyfiyyət ölçüsüdür
2. **Zəif testləri tapır** - Assert-siz və ya zəif assert-li testləri üzə çıxarır
3. **False confidence əngəlləyir** - 100% coverage amma zəif testlər olduğunu göstərir
4. **Test suite gücləndirir** - Hansı testlərin əlavə olunmalı olduğunu göstərir
5. **Code review yardımı** - Kritik kodun test coverage-ını yoxlayır

## Əsas Konseptlər (Key Concepts)

### Mutation Testing Prosesi

```
1. Orijinal kodu götür
2. Kiçik dəyişiklik (mutasiya) et → "Mutant" yaranır
3. Bütün testləri mutant üzərində işlət
4. Nəticəni yoxla:
   ├── Test FAIL → Mutant "killed" (YAXŞI - test tutdu)
   └── Test PASS → Mutant "survived" (PIS - test tutmadı)
5. Mutation Score hesabla
```

### Mutator Növləri

| Mutator | Orijinal | Mutasiya | Açıqlama |
|---------|----------|----------|----------|
| Plus → Minus | `$a + $b` | `$a - $b` | Arifmetik operator dəyişir |
| True → False | `return true` | `return false` | Boolean dəyişir |
| > → >= | `$a > $b` | `$a >= $b` | Müqayisə dəyişir |
| && → \|\| | `$a && $b` | `$a \|\| $b` | Məntiqi dəyişir |
| Remove method | `$this->save()` | `` (silinir) | Method call silinir |
| Return value | `return $x` | `return null` | Qaytarılan dəyər dəyişir |

### Mutation Score

```
Mutation Score Indicator (MSI):

MSI = (Killed Mutants / Total Mutants) × 100

Nümunə:
  Total Mutants: 100
  Killed: 75
  Survived: 20
  Errors/Timeouts: 5

  MSI = (75 / 100) × 100 = 75%

Hədəflər:
  > 80% → Yaxşı test suite
  > 60% → Orta, təkmilləşdirilməli
  < 60% → Zəif, ciddi problemlər var
```

### Survived Mutants Nə Deməkdir?

```
Survived mutant = Test suite bug-u tapa bilmir

Nümunə:
  // Orijinal kod
  function discount(int $total): int {
      if ($total > 100) {
          return $total * 0.1;
      }
      return 0;
  }

  // Mutant: > → >=
  function discount(int $total): int {
      if ($total >= 100) {    // Dəyişiklik
          return $total * 0.1;
      }
      return 0;
  }

  // Əgər testdə yalnız discount(200) test edilibsə,
  // hər iki versiya eyni nəticə verir → mutant survived
  // discount(100) test edilsə, fərq görünər → mutant killed
```

## Praktiki Nümunələr (Practical Examples)

### Zəif Test Nümunəsi

```php
<?php

// Kodumuz
class Calculator
{
    public function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new \DivisionByZeroError('Cannot divide by zero');
        }
        return $a / $b;
    }
}

// Zəif test - mutation testing bunu göstərəcək
class CalculatorTest extends TestCase
{
    /** @test */
    public function it_divides_two_numbers(): void
    {
        $calculator = new Calculator();
        $result = $calculator->divide(10, 2);
        // Assert yoxdur! Bu test həmişə keçəcək
        $this->assertTrue(true);
    }
}

// Güclü test - mutant-ları öldürəcək
class CalculatorStrongTest extends TestCase
{
    /** @test */
    public function it_divides_two_numbers(): void
    {
        $calculator = new Calculator();
        $this->assertEquals(5.0, $calculator->divide(10, 2));
    }

    /** @test */
    public function it_throws_on_division_by_zero(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        (new Calculator())->divide(10, 0);
    }

    /** @test */
    public function it_handles_boundary_values(): void
    {
        $calculator = new Calculator();
        $this->assertEquals(0.0, $calculator->divide(0, 5));
        $this->assertEquals(-2.5, $calculator->divide(-5, 2));
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Infection PHP Quraşdırma

```bash
composer require --dev infection/infection

# Konfiqurasiya yaratmaq
vendor/bin/infection --init
```

### infection.json5 Konfiqurasiyası

```json5
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": ["app"]
    },
    "logs": {
        "text": "infection.log",
        "html": "infection.html",
        "summary": "infection-summary.log"
    },
    "mutators": {
        "@default": true,
        "TrueValue": {
            "ignoreSourceCodeByRegex": [
                ".*return true;.*"
            ]
        }
    },
    "minMsi": 70,
    "minCoveredMsi": 80,
    "phpUnit": {
        "configDir": ".",
        "customPath": "vendor/bin/phpunit"
    },
    "testFramework": "phpunit"
}
```

### Infection İşlətmək

```bash
# Əsas istifadə
vendor/bin/infection

# Yalnız dəyişən fayllara tətbiq et (sürətli)
vendor/bin/infection --git-diff-filter=AM

# Paralel işlət
vendor/bin/infection --threads=4

# Minimum MSI tələbi ilə
vendor/bin/infection --min-msi=80 --min-covered-msi=90

# Yalnız müəyyən directory
vendor/bin/infection --filter="App\\Services"

# Detallı output
vendor/bin/infection --show-mutations
```

### Praktiki Mutation Testing Nümunəsi

```php
<?php

// app/Services/PricingService.php
namespace App\Services;

class PricingService
{
    public function calculateDiscount(float $total, string $customerType): float
    {
        if ($total <= 0) {
            throw new \InvalidArgumentException('Total must be positive');
        }

        $discount = match ($customerType) {
            'vip' => 0.20,
            'premium' => 0.15,
            'regular' => $total > 100 ? 0.05 : 0.0,
            default => 0.0,
        };

        return round($total * $discount, 2);
    }

    public function calculateFinalPrice(float $total, string $customerType): float
    {
        $discount = $this->calculateDiscount($total, $customerType);
        $finalPrice = $total - $discount;

        return max($finalPrice, 0);
    }

    public function isEligibleForFreeShipping(float $total, string $customerType): bool
    {
        if ($customerType === 'vip') {
            return true;
        }

        return $total >= 50;
    }
}
```

```php
<?php

// tests/Unit/PricingServiceTest.php
namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $service;

    protected function setUp(): void
    {
        $this->service = new PricingService();
    }

    /** @test */
    public function vip_gets_20_percent_discount(): void
    {
        $discount = $this->service->calculateDiscount(100, 'vip');
        $this->assertEquals(20.0, $discount);
    }

    /** @test */
    public function premium_gets_15_percent_discount(): void
    {
        $discount = $this->service->calculateDiscount(200, 'premium');
        $this->assertEquals(30.0, $discount);
    }

    /** @test */
    public function regular_gets_5_percent_over_100(): void
    {
        $this->assertEquals(5.25, $this->service->calculateDiscount(105, 'regular'));
    }

    /** @test */
    public function regular_gets_no_discount_under_100(): void
    {
        $this->assertEquals(0.0, $this->service->calculateDiscount(50, 'regular'));
    }

    /** @test */
    public function regular_at_exactly_100_gets_no_discount(): void
    {
        // Boundary value test - mutation testing üçün vacib
        $this->assertEquals(0.0, $this->service->calculateDiscount(100, 'regular'));
    }

    /** @test */
    public function unknown_customer_type_gets_no_discount(): void
    {
        $this->assertEquals(0.0, $this->service->calculateDiscount(100, 'unknown'));
    }

    /** @test */
    public function negative_total_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateDiscount(-10, 'vip');
    }

    /** @test */
    public function zero_total_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateDiscount(0, 'vip');
    }

    /** @test */
    public function final_price_subtracts_discount(): void
    {
        $finalPrice = $this->service->calculateFinalPrice(100, 'vip');
        $this->assertEquals(80.0, $finalPrice);
    }

    /** @test */
    public function final_price_never_goes_below_zero(): void
    {
        // Edge case - çox böyük discount olsa belə
        $finalPrice = $this->service->calculateFinalPrice(1, 'vip');
        $this->assertGreaterThanOrEqual(0, $finalPrice);
    }

    /** @test */
    public function vip_always_gets_free_shipping(): void
    {
        $this->assertTrue($this->service->isEligibleForFreeShipping(10, 'vip'));
        $this->assertTrue($this->service->isEligibleForFreeShipping(0, 'vip'));
    }

    /** @test */
    public function non_vip_gets_free_shipping_over_50(): void
    {
        $this->assertTrue($this->service->isEligibleForFreeShipping(50, 'regular'));
        $this->assertTrue($this->service->isEligibleForFreeShipping(100, 'regular'));
    }

    /** @test */
    public function non_vip_no_free_shipping_under_50(): void
    {
        $this->assertFalse($this->service->isEligibleForFreeShipping(49.99, 'regular'));
    }
}
```

### Infection Output Analizi

```
Infection - PHP Mutation Testing Framework

  50 mutations were generated:
      42 mutants were killed
       5 mutants were not covered by tests
       3 mutants were escaped (survived)
       0 errors were encountered

Metrics:
  Mutation Score Indicator (MSI): 84%
  Mutation Code Coverage: 90%
  Covered Code MSI: 93%

Escaped Mutants:
  1) PricingService.php:25  Mutator: GreaterThan → GreaterThanOrEqual
     - $total > 100  →  $total >= 100
     Suggestion: Boundary value test əlavə edin ($total = 100)

  2) PricingService.php:38  Mutator: Minus → Plus
     - $total - $discount  →  $total + $discount
     Suggestion: Final price-in total-dan kiçik olduğunu assert edin

  3) PricingService.php:40  Mutator: FunctionCallRemoval
     - max($finalPrice, 0)  →  $finalPrice
     Suggestion: Negative final price ssenarisini test edin
```

### CI/CD-yə İnteqrasiya

```yaml
# .github/workflows/mutation-testing.yml
name: Mutation Testing

on:
  pull_request:
    branches: [main]

jobs:
  mutation:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: pcov

      - name: Install dependencies
        run: composer install

      - name: Run Infection on changed files
        run: |
          vendor/bin/infection \
            --git-diff-filter=AM \
            --git-diff-base=origin/main \
            --min-msi=80 \
            --min-covered-msi=90 \
            --threads=4 \
            --logger-github

      - name: Upload mutation report
        uses: actions/upload-artifact@v4
        with:
          name: infection-report
          path: infection.html
```

## Interview Sualları

### 1. Mutation testing nədir və code coverage-dan nə ilə fərqlənir?
**Cavab:** Mutation testing kodda kiçik dəyişikliklər (mutasiyalar) edib testlərin bu dəyişiklikləri tutub-tutmadığını yoxlayır. Code coverage yalnız kodun icra edildiyini ölçür, amma düzgün assert edilib-edilmədiyini göstərmir. Assert-siz test 100% coverage verə bilər, amma mutation testing bunu tutacaq.

### 2. Mutant "killed" və "survived" nə deməkdir?
**Cavab:** Killed: mutasiya edilmiş kodda test fail oldu - test bu bug-u tapa bildi (yaxşı). Survived: mutasiya edilmiş kodda test hələ də pass oldu - test bu bug-u tapa bilmədi (pis). Hədəf bütün mutant-ları "kill" etməkdir. Survived mutant zəif test-i göstərir.

### 3. Mutation Score Indicator (MSI) nədir?
**Cavab:** MSI = (killed mutants / total mutants) x 100. Test suite-in keyfiyyət göstəricisidir. 80% MSI o deməkdir ki, yaradılan mutasiyaların 80%-ni testlər tutdu. 80%+ yaxşı, 60-80% orta, 60%- zəif hesab olunur. Code coverage-dan daha dəqiq metrikdir.

### 4. Infection PHP nədir və necə istifadə olunur?
**Cavab:** Infection PHP üçün mutation testing framework-üdür. PHPUnit testlərini istifadə edir. `composer require --dev infection/infection` ilə qurulur. `vendor/bin/infection` ilə işlədilir. `--git-diff-filter=AM` ilə yalnız dəyişən faylları test edir (sürətli).

### 5. Mutation testing-in çatışmazlıqları nələrdir?
**Cavab:** Çox yavaşdır - hər mutant üçün bütün testlər işləyir. Böyük proyektlərdə saatlarla çəkə bilər. Equivalent mutants - bəzi mutasiyalar kodun davranışını dəyişdirmir, false positive yaradır. Çox resurs tələb edir. Hər commit-də deyil, PR review-da istifadə etmək daha praktikdir.

### 6. Equivalent mutant nədir?
**Cavab:** Kodun davranışını dəyişdirməyən mutasiyadır. Məsələn `$i = 0; while ($i < 10)` → `$i = 0; while ($i != 10)` - nəticə eynidir, amma tool bunu survived mutant kimi göstərir. Bu false positive-dir və mutation score-u süni olaraq aşağı salır.

## Best Practices / Anti-Patterns

### Best Practices

1. **Tədricən tətbiq edin** - Bütün proyekti deyil, kritik hissələri test edin
2. **--git-diff-filter istifadə edin** - Yalnız dəyişən fayllara tətbiq edin
3. **CI/CD-yə əlavə edin** - PR review-da avtomatik işləsin
4. **Survived mutant-ları analiz edin** - Hər survived mutant üçün test əlavə edin
5. **Realistic MSI hədəfləri qoyun** - 100% mümkün deyil, 80%+ yaxşıdır
6. **Parallel execution istifadə edin** - `--threads=4` ilə sürətləndirin

### Anti-Patterns

1. **100% MSI hədəfləmək** - Equivalent mutant-lar bunu mümkünsüz edir
2. **Bütün proyektə tətbiq etmək** - Çox yavaş olacaq, kritik hissələrdən başlayın
3. **Survived mutant-ları ignore etmək** - Hər birini analiz edin
4. **Yalnız mutation testing-ə güvənmək** - Code review, manual testing də lazımdır
5. **Hər commit-də işlətmək** - Çox yavaş, PR level-ində kifayətdir
6. **Nəticələri anlamadan MSI-ya baxmaq** - Hansı mutant-ların survived olduğunu analiz edin
