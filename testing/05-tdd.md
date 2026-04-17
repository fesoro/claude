# Test-Driven Development (TDD)

## Nədir? (What is it?)

TDD (Test-Driven Development) proqramlaşdırma metodologiyasıdır. Əsas prinsipi:
əvvəl test yaz, sonra kodu yaz. Bu, "Red-Green-Refactor" dövrü adlanır.

Kent Beck tərəfindən populyarlaşdırılıb. TDD yalnız testing strategiyası deyil,
dizayn strategiyasıdır. Test yazarkən kodun API-sini düşünürsünüz, bu da daha
yaxşı interfeyslərə gətirib çıxarır.

TDD-nin əsas ideyası: "Əgər kodu test etmək çətindirsə, dizayn yaxşı deyil."

## Əsas Konseptlər (Key Concepts)

### Red-Green-Refactor Dövrü

```
   ┌─────────────┐
   │   RED        │  ← Uğursuz test yaz
   │  (Write test)│
   └──────┬───────┘
          │
   ┌──────▼───────┐
   │   GREEN      │  ← Testi keçir (minimum kod)
   │  (Make pass) │
   └──────┬───────┘
          │
   ┌──────▼───────┐
   │  REFACTOR    │  ← Kodu təmizlə (testlər hələ keçir)
   │  (Clean up)  │
   └──────┬───────┘
          │
          └────────── Təkrarla
```

**Red**: Test yaz. Test uğursuz olmalıdır. Əgər test keçirsə, test yanlışdır.

**Green**: Testi keçirmək üçün ən sadə kodu yaz. Gözəl olmaq lazım deyil,
sadəcə işləsin.

**Refactor**: Kodu təmizlə - dublikat sil, adlandırmanı yaxşılaşdır, strukturu
düzəlt. Testlər hələ keçməlidir.

### Three Laws of TDD (Robert C. Martin)

1. Production kodu yazmadan əvvəl uğursuz unit test yazmalısan
2. Uğursuz olmaq üçün kifayət qədər test yaz (compile error da uğursuzluqdur)
3. Cari testi keçirmək üçün kifayət qədər production kodu yaz

### TDD-nin Faydaları

- **Daha yaxşı dizayn**: Testable kod = loosely coupled kod
- **Regression safety**: Hər dəyişiklikdən sonra testlər çalışır
- **Documentation**: Testlər kodun necə işlədiyini göstərir
- **Confidence**: Refactoring etməyə cəsarət
- **Less debugging**: Problemlər tez tapılır

### TDD-nin Çətinlikləri

- Öyrənmə əyrisi dik
- Əvvəlcə yavaş görünür
- Legacy kodda tətbiqi çətin
- Database/UI kimi xarici asılılıqlarla çətin
- Komanda dəstəyi lazımdır

## Praktiki Nümunələr (Practical Examples)

### TDD Kata: String Calculator

Addım-addım TDD nümunəsi:

**Addım 1: Boş string 0 qaytarır (RED)**
```php
class StringCalculatorTest extends TestCase
{
    public function test_empty_string_returns_zero(): void
    {
        $calc = new StringCalculator();
        $this->assertEquals(0, $calc->add(''));
    }
}
// Test FAIL edir - StringCalculator sinifi yoxdur
```

**Addım 1: GREEN**
```php
class StringCalculator
{
    public function add(string $numbers): int
    {
        return 0;
    }
}
// Test PASS edir
```

**Addım 2: Tək rəqəm (RED)**
```php
public function test_single_number_returns_itself(): void
{
    $calc = new StringCalculator();
    $this->assertEquals(5, $calc->add('5'));
}
// Test FAIL edir
```

**Addım 2: GREEN**
```php
public function add(string $numbers): int
{
    if ($numbers === '') return 0;
    return (int) $numbers;
}
```

**Addım 3: İki rəqəm (RED)**
```php
public function test_two_numbers_returns_sum(): void
{
    $calc = new StringCalculator();
    $this->assertEquals(8, $calc->add('3,5'));
}
```

**Addım 3: GREEN**
```php
public function add(string $numbers): int
{
    if ($numbers === '') return 0;
    $parts = explode(',', $numbers);
    return array_sum(array_map('intval', $parts));
}
```

**Addım 4: Neqativ rəqəmlər xəta verir (RED)**
```php
public function test_negative_numbers_throw_exception(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Negatives not allowed: -3');

    $calc = new StringCalculator();
    $calc->add('1,-3,5');
}
```

**Addım 4: GREEN**
```php
public function add(string $numbers): int
{
    if ($numbers === '') return 0;

    $parts = array_map('intval', explode(',', $numbers));
    $negatives = array_filter($parts, fn($n) => $n < 0);

    if (!empty($negatives)) {
        throw new InvalidArgumentException(
            'Negatives not allowed: ' . implode(', ', $negatives)
        );
    }

    return array_sum($parts);
}
```

**REFACTOR**
```php
class StringCalculator
{
    public function add(string $numbers): int
    {
        if ($numbers === '') {
            return 0;
        }

        $values = $this->parse($numbers);
        $this->validateNoNegatives($values);

        return array_sum($values);
    }

    private function parse(string $numbers): array
    {
        return array_map('intval', explode(',', $numbers));
    }

    private function validateNoNegatives(array $values): void
    {
        $negatives = array_filter($values, fn(int $n) => $n < 0);

        if (!empty($negatives)) {
            throw new InvalidArgumentException(
                'Negatives not allowed: ' . implode(', ', $negatives)
            );
        }
    }
}
```

### Outside-In TDD

Feature testdən başlayıb unit testlərə doğru getmək:

```php
// 1. Feature test yazırıq (RED)
public function test_user_can_place_order(): void
{
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
}

// 2. Route və Controller yaradırıq (GREEN for routing)
// 3. OrderService üçün unit test yazırıq (RED)
public function test_calculate_order_total(): void
{
    $service = new OrderService();
    $items = [
        ['price' => 50, 'quantity' => 2],
        ['price' => 30, 'quantity' => 1],
    ];

    $this->assertEquals(130, $service->calculateTotal($items));
}

// 4. OrderService implementasiya (GREEN)
// 5. Refactor
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### TDD ilə Laravel Service Yaratma

**Tapşırıq**: Coupon sistemi yaratmaq.

```php
// Test 1: Coupon tətbiq etmək (RED)
class CouponServiceTest extends TestCase
{
    public function test_percentage_coupon_applies_discount(): void
    {
        $service = new CouponService();
        $coupon = new Coupon([
            'type' => 'percentage',
            'value' => 20,
        ]);

        $discounted = $service->apply($coupon, 100.0);

        $this->assertEquals(80.0, $discounted);
    }
}
```

```php
// Implementation (GREEN)
class CouponService
{
    public function apply(Coupon $coupon, float $total): float
    {
        return match ($coupon->type) {
            'percentage' => $total - ($total * $coupon->value / 100),
        };
    }
}
```

```php
// Test 2: Fixed amount coupon (RED)
public function test_fixed_coupon_applies_discount(): void
{
    $service = new CouponService();
    $coupon = new Coupon(['type' => 'fixed', 'value' => 15]);

    $this->assertEquals(85.0, $service->apply($coupon, 100.0));
}
```

```php
// Updated implementation (GREEN)
public function apply(Coupon $coupon, float $total): float
{
    return match ($coupon->type) {
        'percentage' => $total - ($total * $coupon->value / 100),
        'fixed' => $total - $coupon->value,
    };
}
```

```php
// Test 3: Discount total-dan çox ola bilməz (RED)
public function test_discount_cannot_exceed_total(): void
{
    $service = new CouponService();
    $coupon = new Coupon(['type' => 'fixed', 'value' => 150]);

    $this->assertEquals(0.0, $service->apply($coupon, 100.0));
}
```

```php
// Updated implementation (GREEN)
public function apply(Coupon $coupon, float $total): float
{
    $discount = match ($coupon->type) {
        'percentage' => $total * $coupon->value / 100,
        'fixed' => (float) $coupon->value,
    };

    return max(0, $total - $discount);
}
```

```php
// Test 4: Expired coupon exception atır (RED)
public function test_expired_coupon_throws_exception(): void
{
    $this->expectException(ExpiredCouponException::class);

    $service = new CouponService();
    $coupon = new Coupon([
        'type' => 'percentage',
        'value' => 20,
        'expires_at' => now()->subDay(),
    ]);

    $service->apply($coupon, 100.0);
}
```

```php
// Final implementation (GREEN + REFACTOR)
class CouponService
{
    public function apply(Coupon $coupon, float $total): float
    {
        $this->ensureNotExpired($coupon);

        $discount = $this->calculateDiscount($coupon, $total);

        return max(0, $total - $discount);
    }

    private function ensureNotExpired(Coupon $coupon): void
    {
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw new ExpiredCouponException('Coupon has expired');
        }
    }

    private function calculateDiscount(Coupon $coupon, float $total): float
    {
        return match ($coupon->type) {
            'percentage' => $total * $coupon->value / 100,
            'fixed' => (float) $coupon->value,
            default => throw new InvalidArgumentException("Unknown coupon type: {$coupon->type}"),
        };
    }
}
```

### TDD ilə Laravel Feature Test

```php
// Tapşırıq: User profile update

// Test 1: User öz profilini yeniləyə bilər
public function test_user_can_update_profile(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/profile', [
        'name' => 'Updated Name',
        'bio' => 'New bio',
    ]);

    $response->assertRedirect('/profile');
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
    ]);
}

// Test 2: Name tələb olunur
public function test_name_is_required(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/profile', [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
}

// Test 3: Guest access yoxdur
public function test_guest_cannot_update_profile(): void
{
    $response = $this->put('/profile', ['name' => 'Test']);
    $response->assertRedirect('/login');
}
```

## Interview Sualları

**S: TDD nədir və niyə istifadə edilir?**
C: TDD əvvəl test, sonra kod yazma metodologiyasıdır. Red-Green-Refactor dövrünə
əsaslanır. Daha yaxşı dizayn, az bug, confidence refactoring, living documentation
təmin edir.

**S: Red-Green-Refactor nədir?**
C: Red: uğursuz test yaz. Green: testi keçirmək üçün minimum kod yaz. Refactor:
kodu təmizlə, testlər hələ keçsin. Bu dövr təkrarlanır.

**S: TDD nə vaxt istifadə etməməlisiniz?**
C: Prototype/spike zamanı, UI-intensive işlərdə, öyrənmə zamanı, çox tez deadline
olduqda. Amma bu, "heç vaxt test yazmayın" demək deyil - TDD olmasa da testlər
yazılmalıdır.

**S: Inside-Out vs Outside-In TDD fərqi nədir?**
C: Inside-Out (Classic/Chicago): Ən kiçik unit-dən başlayıb yuxarı doğru gedir.
Outside-In (London): Feature testdən başlayıb aşağı doğru gedir, mock-lar istifadə
edir. Inside-Out daha sadədir, Outside-In daha yaxşı API dizaynı verir.

**S: TDD test-last-dan niyə yaxşıdır?**
C: Test-first ilə: testable dizayn yaranır, bütün kod test edilir (test-last-da
çox vaxt skip olur), over-engineering azalır (yalnız tələb olunanı yazırsınız),
testlər specification kimi işləyir.

## Best Practices / Anti-Patterns

### Best Practices
- Kiçik addımlarla irəlilə (baby steps)
- Hər addımda yalnız bir test əlavə et
- Green mərhələsində ən sadə kodu yaz
- Refactor mərhələsini atlama
- Commit tez-tez et (hər Green/Refactor-dan sonra)
- Test adlarını specification kimi yaz

### Anti-Patterns
- **Big steps**: Bir dəfəyə çoxlu funksionallıq əlavə etmək
- **Skipping Red**: Test yazmadan birbaşa kod yazmaq
- **Gold plating in Green**: Green mərhələsində lazımsız optimizasiya
- **Skipping Refactor**: Kodu təmizləməyi unutmaq
- **Testing implementation**: Davranış əvəzinə implementasiya detallarını test etmək
- **Not running tests**: Testləri tez-tez çalışdırmamaq
