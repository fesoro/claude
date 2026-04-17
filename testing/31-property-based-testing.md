# Property-Based Testing

## Nədir? (What is it?)

**Property-Based Testing (PBT)** - testing yanaşmasıdır ki, konkret input/output misalları yoxlamaq əvəzinə, **funksiyanın ümumi xassələrini (properties)** yoxlayır. Framework avtomatik olaraq yüzlərlə təsadüfi input generasiya edir və bu input-lar üçün property-nin doğru qaldığını yoxlayır.

**Example-based testing vs Property-based testing:**

```php
// Example-based (ənənəvi)
test('sum of 2 and 3 is 5', function () {
    expect(sum(2, 3))->toBe(5);
});

// Property-based
test('sum is commutative', function () {
    forAll(integers(), integers())
        ->then(fn ($a, $b) => expect(sum($a, $b))->toBe(sum($b, $a)));
});
// Framework 100 müxtəlif (a, b) cütlüyü generasiya edəcək
```

**Əsas ideya:** QuickCheck (Haskell) tərəfindən populyarlaşdırılıb. Bug-ları tapmaq üçün developer-in düşünmədiyi edge case-ləri avtomatik kəşf edir.

## Əsas Konseptlər (Key Concepts)

### 1. Generators (Generatorlar)

Generatorlar təsadüfi test data istehsal edir:

- **Primitive:** `integers()`, `strings()`, `floats()`, `booleans()`
- **Collections:** `arrays()`, `sequences()`, `maps()`
- **Custom:** öz data tipləriniz üçün (User, Order və s.)
- **Constrained:** `between(1, 100)`, `nonEmptyStrings()`

### 2. Properties (Xassələr)

Property - inputdan asılı olmayan həmişə doğru olan ifadədir:

- **Commutativity:** `f(a, b) == f(b, a)`
- **Associativity:** `f(f(a, b), c) == f(a, f(b, c))`
- **Idempotence:** `f(f(x)) == f(x)` (məs: `abs()`)
- **Invariants:** reverse(reverse(list)) == list
- **Round-trip:** `decode(encode(x)) == x`
- **Oracle:** simple-but-slow vs fast implementation eyni nəticə

### 3. Shrinking (Kiçiltmə)

Test sıradıqda, framework **minimal failing case** tapmağa çalışır:

```
Original failure: [42, -17, 1000, -5, 99, 0, -1]  (7 element)
After shrinking:  [-1]                             (1 element)
```

Shrinking sayəsində bug-ın əsl səbəbini tez anlayırıq.

### 4. Invariants

Sistem vəziyyətində həmişə doğru qalan şərtlər:

- User-in `total_orders` sahəsi heç vaxt mənfi olmasın
- Shopping cart-ın total-ı `sum(items.price * quantity)` ilə bərabərdir
- Balance dəyişikliyindən sonra ledger balans qalır

## Praktiki Nümunələr (Practical Examples)

### Eris Library (PHP)

**Eris** - PHP üçün əsas property-based testing kitabxanasıdır.

**Quraşdırma:**

```bash
composer require --dev giorgiosironi/eris
```

### PHP/Laravel ilə Tətbiq

```php
<?php

namespace Tests\Unit;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

class MathOperationsPropertyTest extends TestCase
{
    use TestTrait;

    public function testAdditionIsCommutative(): void
    {
        $this->forAll(
            Generator\int(),
            Generator\int()
        )->then(function ($a, $b) {
            $this->assertEquals($a + $b, $b + $a);
        });
    }

    public function testAdditionIsAssociative(): void
    {
        $this->forAll(
            Generator\int(),
            Generator\int(),
            Generator\int()
        )->then(function ($a, $b, $c) {
            $this->assertEquals(($a + $b) + $c, $a + ($b + $c));
        });
    }

    public function testSortingIsIdempotent(): void
    {
        $this->forAll(
            Generator\seq(Generator\int())
        )->then(function (array $list) {
            $sortedOnce = $list;
            sort($sortedOnce);

            $sortedTwice = $sortedOnce;
            sort($sortedTwice);

            $this->assertEquals($sortedOnce, $sortedTwice);
        });
    }

    public function testReverseRoundTrip(): void
    {
        $this->forAll(
            Generator\seq(Generator\int())
        )->then(function (array $list) {
            $this->assertEquals(
                $list,
                array_reverse(array_reverse($list))
            );
        });
    }
}
```

### String Encoding Round-Trip

```php
public function testBase64EncodingRoundTrip(): void
{
    $this->forAll(
        Generator\string()
    )->then(function (string $original) {
        $encoded = base64_encode($original);
        $decoded = base64_decode($encoded);

        $this->assertEquals($original, $decoded);
    });
}

public function testJsonEncodeDecodeRoundTrip(): void
{
    $this->forAll(
        Generator\associative([
            'name' => Generator\string(),
            'age' => Generator\choose(0, 150),
            'active' => Generator\bool(),
        ])
    )->then(function (array $data) {
        $json = json_encode($data);
        $decoded = json_decode($json, true);

        $this->assertEquals($data, $decoded);
    });
}
```

### Laravel Model Property Test

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTotalPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    public function testOrderTotalIsSumOfLineItems(): void
    {
        $this->forAll(
            Generator\seq(
                Generator\associative([
                    'price' => Generator\choose(100, 10000),
                    'quantity' => Generator\choose(1, 10),
                ])
            )
        )->then(function (array $items) {
            if (empty($items)) {
                return;
            }

            $order = Order::factory()->create(['total' => 0]);

            $expectedTotal = 0;
            foreach ($items as $item) {
                $order->items()->create([
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                ]);
                $expectedTotal += $item['price'] * $item['quantity'];
            }

            $order->recalculateTotal();

            $this->assertEquals($expectedTotal, $order->fresh()->total);
            $this->assertGreaterThanOrEqual(0, $order->fresh()->total);
        });
    }

    public function testDiscountNeverExceedsTotal(): void
    {
        $this->forAll(
            Generator\choose(100, 100000),
            Generator\choose(0, 100)
        )->then(function (int $total, int $discountPercent) {
            $order = Order::factory()->create(['total' => $total]);
            $order->applyDiscount($discountPercent);

            $this->assertGreaterThanOrEqual(0, $order->final_amount);
            $this->assertLessThanOrEqual($total, $order->final_amount);
        });
    }
}
```

### Custom Generator

```php
use Eris\Generator;

// Valid email generator
$emailGenerator = Generator\map(
    fn ($parts) => $parts[0] . '@' . $parts[1] . '.com',
    Generator\tuple(
        Generator\string(),
        Generator\elements(['example', 'test', 'demo'])
    )
);

public function testUserRegistration(): void
{
    $this->forAll(
        Generator\string(),
        $this->emailGenerator()
    )->then(function ($name, $email) {
        $user = User::create(['name' => $name, 'email' => $email]);
        $this->assertNotNull($user->id);
        $this->assertStringContainsString('@', $user->email);
    });
}
```

### Shrinking Demo

```php
public function testBuggyFunction(): void
{
    $this->forAll(
        Generator\seq(Generator\choose(-100, 100))
    )->then(function (array $numbers) {
        $result = buggyAverage($numbers);
        // Bug: zero-division, Eris kiçik failing case tapacaq: []
        $this->assertIsFloat($result);
    });
}
```

## Interview Sualları (Q&A)

### 1. Property-based testing ilə example-based testing arasında fərq nədir?

**Example-based** - developer konkret input-output cütlükləri yazır. **Property-based** - framework random input-lar generasiya edir və universal xassələri yoxlayır. PBT edge case-ləri kəşf etməkdə üstündür, ancaq hər zaman uyğun deyil.

### 2. Shrinking nədir və nə üçün vacibdir?

Shrinking - test sındıqda **minimal failing input** tapma prosesidir. Məsələn, 1000 elementli array-də bug varsa, framework azaldaraq 1-2 elementə endirəcək. Bu, debug vaxtını dramatik azaldır.

### 3. Hansı xassə (property) növləri var?

- Commutativity, Associativity
- Idempotence (`f(f(x)) == f(x)`)
- Inverse/Round-trip (`decode(encode(x)) == x`)
- Invariants (həmişə doğru olan şərtlər)
- Oracle (reference implementation ilə müqayisə)

### 4. PBT-nin məhdudiyyətləri nədir?

- **Deterministik deyil** - random seed-dən asılıdır
- **Slower** - minlərlə test case icra olunur
- **Hard to write** - yaxşı property tapmaq çətindir
- **Flaky ola bilər** - nadir fail-lər olursa

### 5. Property-ləri necə tapırıq?

- **Algebraic laws:** riyazi qanunlar (commutativity)
- **Round-trip:** serialize/deserialize, encode/decode
- **Oracle:** sadə-amma-yavaş implementation ilə müqayisə
- **Invariants:** business rules (balance >= 0)
- **Idempotence:** cache.set(x); cache.set(x) == cache.set(x)

### 6. Eris-də generator necə yaradılır?

`Generator\map()`, `Generator\tuple()`, `Generator\associative()` ilə kompleks generator-lar qurulur. Məsələn email generator: `map(fn($s) => $s.'@test.com', string())`.

### 7. PBT-ni nə zaman istifadə etməliyik?

- Pure function-lar (matematik, string əməliyyatları)
- Serialization/parsing kodu
- Data transformation pipeline-ları
- Algorithmic kod (sort, search)
- Business invariants yoxlaması

### 8. Flaky PBT test-ləri necə həll etmək olar?

- Fixed seed istifadə etmək (`$this->seed(42)`)
- Test case sayını azaltmaq CI-da
- Shrinker-in tapdığı case-i regression test kimi əlavə etmək

## Best Practices / Anti-Patterns

### Best Practices

1. **Pure function-lar üçün ideal** - side-effect-siz kodda PBT güclüdür
2. **Example-based ilə birlikdə** - PBT + konkret misallar = yaxşı coverage
3. **Minimal reproducible case qeyd edin** - shrinker tapdıqda regression test yaradın
4. **Generator-ları reuse edin** - domain-spesifik generatorları təkrar istifadə üçün ayırın
5. **Assume-ları istifadə edin** - invalid input-ları filter etmək üçün

### Anti-Patterns

1. **Test-ləri implementation-a bağlamaq** - property implementation detallarını əks etdirməməlidir
2. **Overly weak properties** - `assertTrue(true)` kimi mənasız yoxlamalar
3. **Non-deterministic test** - random generator seed-i qeyd etməmək
4. **Çox dar generator-lar** - yalnız 1-10 arası rəqəm generasiya edib edge case-ləri miss etmək
5. **Implementation-ı dublikat etmək** - test kodu production kodu təkrar yazmamalı

### Vacib İpuçları

- PBT **bug tapır** amma **bug-suzluğu sübut etmir**
- Coverage guided PBT-ni mutation testing ilə birləşdirin
- Shrinker-in yarı-avtomatik olduğunu unutmayın - bəzən əl ilə minimizasiya edin
- CI-da az test (məs 20), local-da çox (500+)
