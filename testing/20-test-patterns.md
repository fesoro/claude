# Test Patterns

## Nədir? (What is it?)

Test patterns, testləri daha oxunaqlı, bakımı asan və təkrarlanabilir yazmaq üçün
istifadə olunan dizayn şablonlarıdır. Mürəkkəb test data hazırlama, setup kodu təkrarı
və test oxunaqlığı problemlərini həll edir.

Düzgün pattern-lər test suite-i professional və scalable edir. Laravel ekosistemində
Factory pattern, Builder pattern, Object Mother və parametrized test kimi pattern-lər
geniş istifadə olunur.

### Niyə Test Patterns Vacibdir?

1. **DRY tələbi** - Test setup-da kod təkrarını azaldır
2. **Oxunaqlıq** - Test-in nə test etdiyini aydın göstərir
3. **Bakım asanlığı** - Data model dəyişdikdə yalnız bir yerdə yenilənir
4. **Sürətli test yazmaq** - Yeni test yazmaq sürətlənir
5. **Konsistentlik** - Komandada eyni yanaşma istifadə olunur

## Əsas Konseptlər (Key Concepts)

### Test Pattern Kateqoriyaları

```
1. Test Data Patterns
   ├── Factory Pattern (Laravel factories)
   ├── Builder Pattern (test data builder)
   ├── Object Mother (named constructors)
   └── Fixture (static test data)

2. Test Structure Patterns
   ├── Arrange-Act-Assert (AAA)
   ├── Given-When-Then (BDD)
   └── Setup-Exercise-Verify-Teardown

3. Test Organization Patterns
   ├── Parameterized Tests (DataProvider)
   ├── Test Traits (shared behavior)
   └── Custom Assertions

4. Test Isolation Patterns
   ├── Fresh Fixture (hər testdə yeni)
   ├── Shared Fixture (setUp)
   └── Lazy Setup (lazımi anda yarat)
```

### AAA Pattern (Arrange-Act-Assert)

```php
/** @test */
public function it_applies_discount_for_vip_customers(): void
{
    // Arrange - Test data hazırla
    $customer = Customer::factory()->vip()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 100.00,
    ]);

    // Act - Testi icra et
    $result = $this->pricingService->applyDiscount($order);

    // Assert - Nəticəni yoxla
    $this->assertEquals(80.00, $result->finalPrice);
    $this->assertEquals(20.00, $result->discountAmount);
}
```

## Praktiki Nümunələr (Practical Examples)

### Builder Pattern (Test Data Builder)

```php
<?php

namespace Tests\Support\Builders;

use App\Models\Order;
use App\Models\User;
use App\Models\Product;

class OrderBuilder
{
    private ?User $customer = null;
    private array $items = [];
    private string $status = 'pending';
    private ?string $couponCode = null;
    private ?float $shippingCost = null;
    private string $shippingMethod = 'standard';

    public static function anOrder(): self
    {
        return new self();
    }

    public function forCustomer(User $customer): self
    {
        $clone = clone $this;
        $clone->customer = $customer;
        return $clone;
    }

    public function withItem(Product $product, int $quantity = 1): self
    {
        $clone = clone $this;
        $clone->items[] = ['product' => $product, 'quantity' => $quantity];
        return $clone;
    }

    public function withStatus(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withCoupon(string $code): self
    {
        $clone = clone $this;
        $clone->couponCode = $code;
        return $clone;
    }

    public function withExpressShipping(): self
    {
        $clone = clone $this;
        $clone->shippingMethod = 'express';
        $clone->shippingCost = 15.00;
        return $clone;
    }

    public function withFreeShipping(): self
    {
        $clone = clone $this;
        $clone->shippingCost = 0.00;
        return $clone;
    }

    public function build(): Order
    {
        $customer = $this->customer ?? User::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => $this->status,
            'coupon_code' => $this->couponCode,
            'shipping_cost' => $this->shippingCost ?? 5.00,
            'shipping_method' => $this->shippingMethod,
        ]);

        foreach ($this->items as $item) {
            $order->items()->create([
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
                'price' => $item['product']->price,
            ]);
        }

        return $order->fresh(['items']);
    }
}
```

```php
<?php

// İstifadəsi
/** @test */
public function it_calculates_total_with_express_shipping(): void
{
    $product = Product::factory()->create(['price' => 50.00]);

    $order = OrderBuilder::anOrder()
        ->withItem($product, 2)
        ->withExpressShipping()
        ->build();

    $this->assertEquals(115.00, $order->calculateTotal());
    // (50 * 2) + 15 shipping = 115
}

/** @test */
public function it_applies_coupon_discount(): void
{
    $product = Product::factory()->create(['price' => 100.00]);

    $order = OrderBuilder::anOrder()
        ->withItem($product)
        ->withCoupon('SAVE20')
        ->withFreeShipping()
        ->build();

    $result = $this->orderService->processOrder($order);

    $this->assertEquals(80.00, $result->finalPrice);
}
```

### Object Mother Pattern

```php
<?php

namespace Tests\Support;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;

class TestDataMother
{
    // ---- Users ----
    public static function aRegularCustomer(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'John Regular',
            'email' => 'john@example.com',
            'role' => 'customer',
        ], $overrides));
    }

    public static function aVipCustomer(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Jane VIP',
            'email' => 'jane@example.com',
            'role' => 'vip',
        ], $overrides));
    }

    public static function anAdmin(array $overrides = []): User
    {
        return User::factory()->admin()->create($overrides);
    }

    // ---- Products ----
    public static function aCheapProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'Cheap Item',
            'price' => 9.99,
        ], $overrides));
    }

    public static function anExpensiveProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'Premium Item',
            'price' => 999.99,
        ], $overrides));
    }

    // ---- Orders ----
    public static function aPendingOrder(?User $customer = null): Order
    {
        return Order::factory()->create([
            'customer_id' => ($customer ?? self::aRegularCustomer())->id,
            'status' => 'pending',
        ]);
    }

    public static function aCompletedOrder(?User $customer = null): Order
    {
        return Order::factory()->create([
            'customer_id' => ($customer ?? self::aRegularCustomer())->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
```

```php
<?php

// İstifadəsi
use Tests\Support\TestDataMother as Mother;

/** @test */
public function vip_customer_gets_priority_support(): void
{
    $customer = Mother::aVipCustomer();
    $order = Mother::aPendingOrder($customer);

    $ticket = $this->supportService->createTicket($order, 'Help!');

    $this->assertEquals('high', $ticket->priority);
}
```

### Laravel Factory Patterns (Advanced)

```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'order_number' => 'ORD-' . fake()->unique()->numerify('######'),
            'status' => 'pending',
            'total' => fake()->randomFloat(2, 10, 1000),
            'shipping_cost' => 5.00,
            'notes' => null,
        ];
    }

    // ---- State Methods ----

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => 'Customer request',
        ]);
    }

    public function withTotal(float $total): static
    {
        return $this->state(fn () => ['total' => $total]);
    }

    // ---- Relationship Methods ----

    public function withItems(int $count = 3): static
    {
        return $this->has(
            \App\Models\OrderItem::factory()->count($count),
            'items'
        );
    }

    // ---- Sequence ----

    public function inSequentialStatuses(): static
    {
        return $this->sequence(
            ['status' => 'pending'],
            ['status' => 'processing'],
            ['status' => 'shipped'],
            ['status' => 'completed'],
        );
    }

    // ---- Callback ----

    public function withCalculatedTotal(): static
    {
        return $this->afterCreating(function (Order $order) {
            $order->update([
                'total' => $order->items->sum(fn ($item) =>
                    $item->price * $item->quantity
                ),
            ]);
        });
    }
}
```

```php
<?php

// İstifadə nümunələri

// Sadə
$order = Order::factory()->create();

// State ilə
$order = Order::factory()->completed()->create();

// Relationship ilə
$order = Order::factory()->withItems(5)->create();

// Sequence ilə - 4 order fərqli status-da
$orders = Order::factory()
    ->count(4)
    ->inSequentialStatuses()
    ->create();

// Mürəkkəb ssenari
$vipUser = User::factory()->create(['role' => 'vip']);
$orders = Order::factory()
    ->count(3)
    ->for($vipUser, 'customer')
    ->withItems(2)
    ->completed()
    ->create();
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Custom Assertions

```php
<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Illuminate\Testing\TestResponse;

trait CustomAssertions
{
    protected function assertResponseHasValidPagination(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }

    protected function assertEmailSentTo(string $email, string $mailable): void
    {
        \Illuminate\Support\Facades\Mail::assertSent($mailable, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    protected function assertModelHasTimestamps($model): void
    {
        Assert::assertNotNull($model->created_at, 'created_at should not be null');
        Assert::assertNotNull($model->updated_at, 'updated_at should not be null');
    }

    protected function assertJsonApiError(TestResponse $response, int $status): void
    {
        $response->assertStatus($status)
            ->assertJsonStructure(['message']);
    }

    protected function assertUserCannot(User $user, string $ability, $model): void
    {
        Assert::assertFalse(
            $user->can($ability, $model),
            "User should not be able to {$ability}"
        );
    }
}
```

### Parameterized Tests ilə DataProvider

```php
<?php

namespace Tests\Unit;

use App\Services\SlugService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SlugServiceTest extends TestCase
{
    #[DataProvider('slugProvider')]
    public function test_generates_correct_slug(string $input, string $expected): void
    {
        $service = new SlugService();
        $this->assertEquals($expected, $service->generate($input));
    }

    public static function slugProvider(): array
    {
        return [
            'simple text' => ['Hello World', 'hello-world'],
            'special chars' => ['Hello & World!', 'hello-world'],
            'multiple spaces' => ['Hello   World', 'hello-world'],
            'unicode' => ['Ürək Sözləri', 'urek-sozleri'],
            'already slug' => ['hello-world', 'hello-world'],
            'numbers' => ['Test 123', 'test-123'],
            'mixed case' => ['HeLLo WoRLd', 'hello-world'],
            'leading trailing spaces' => ['  Hello  ', 'hello'],
            'empty string' => ['', ''],
        ];
    }

    #[DataProvider('priceProvider')]
    public function test_formats_price_correctly(
        float $amount,
        string $currency,
        string $expected
    ): void {
        $formatter = new PriceFormatter();
        $this->assertEquals($expected, $formatter->format($amount, $currency));
    }

    public static function priceProvider(): array
    {
        return [
            'usd' => [99.99, 'USD', '$99.99'],
            'eur' => [99.99, 'EUR', '€99.99'],
            'zero' => [0, 'USD', '$0.00'],
            'large' => [1234567.89, 'USD', '$1,234,567.89'],
            'round' => [100, 'USD', '$100.00'],
        ];
    }
}
```

### Test Helper Methods

```php
<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // ---- Auth Helpers ----

    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    protected function signInApi(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    protected function signInAdmin(): User
    {
        return $this->signIn(User::factory()->admin()->create());
    }

    // ---- JSON API Helpers ----

    protected function apiGet(string $uri): TestResponse
    {
        return $this->getJson("/api{$uri}");
    }

    protected function apiPost(string $uri, array $data = []): TestResponse
    {
        return $this->postJson("/api{$uri}", $data);
    }

    protected function apiPut(string $uri, array $data = []): TestResponse
    {
        return $this->putJson("/api{$uri}", $data);
    }

    protected function apiDelete(string $uri): TestResponse
    {
        return $this->deleteJson("/api{$uri}");
    }

    // ---- Time Helpers ----

    protected function freezeTime(): void
    {
        $this->travelTo(now());
    }

    protected function advanceTime(int $minutes): void
    {
        $this->travel($minutes)->minutes();
    }
}
```

## Interview Sualları

### 1. Builder pattern test-lərdə necə istifadə olunur?
**Cavab:** Builder pattern mürəkkəb test object-ləri addım-addım qurmağa imkan verir. Method chaining ilə yalnız test üçün vacib olan xüsusiyyətlər təyin edilir: `OrderBuilder::anOrder()->withItem($product)->withCoupon('SAVE20')->build()`. Bu, test-i oxunaqlı edir və yalnız relevant detail-lər göstərilir.

### 2. Object Mother və Builder arasındakı fərq nədir?
**Cavab:** Object Mother pre-configured named constructor-lar təqdim edir: `Mother::aVipCustomer()`. Builder isə step-by-step konfiqurasiya edir: `OrderBuilder::anOrder()->withExpressShipping()->build()`. Object Mother sadə ssenarilar üçün, Builder mürəkkəb ssenarilar üçün daha uyğundur. İkisi birlikdə istifadə oluna bilər.

### 3. Laravel factory state nədir?
**Cavab:** Factory state, factory-nin default dəyərlərini override edən named method-dur. `User::factory()->admin()->create()` - admin state role-u admin edir. State-lər compose oluna bilər: `Post::factory()->published()->featured()->create()`. `$this->state(fn() => [...])` ilə təyin edilir.

### 4. DataProvider pattern nə üçün istifadə olunur?
**Cavab:** Eyni test logic-ini fərqli input/output kombinasiyaları ilə işlətmək üçün. Test method-un hər data set üçün ayrıca icra olunur. Edge case-ləri asanlıqla əlavə etməyə imkan verir. Named keys fail mesajında hansı case-in fail etdiyini göstərir.

### 5. Custom assertion nə üçün yazılır?
**Cavab:** Təkrarlanan assertion pattern-lərini bir method-a yığmaq üçün. `assertResponseHasValidPagination($response)` - hər pagination testində eyni structure yoxlamasını təkrarlamaq əvəzinə. Test oxunaqlığını artırır, DRY prinsipinə uyğundur, failure message daha aydın olur.

### 6. Fresh Fixture və Shared Fixture arasındakı fərq nədir?
**Cavab:** Fresh Fixture: hər testdə yeni data yaradılır - tam izolyasiya, amma yavaş. Shared Fixture: setUp-da yaradılan data bütün testlər tərəfindən paylaşılır - sürətli, amma testlər bir-birini təsir edə bilər. Shared fixture yalnız readonly data üçün təhlükəsizdir. Laravel-da RefreshDatabase fresh fixture təmin edir.

## Best Practices / Anti-Patterns

### Best Practices

1. **Builder/Mother pattern istifadə edin** - Mürəkkəb test data üçün
2. **Factory state-ləri yazın** - Təkrarlanan konfiqurasiyalar üçün
3. **Custom assertions yaradın** - Təkrarlanan yoxlamalar üçün
4. **DataProvider istifadə edin** - Eyni test, fərqli data
5. **Helper method-lar yazın** - signIn, apiGet kimi shortcut-lar
6. **Immutable builder** - clone istifadə edin, original-ı dəyişdirməyin

### Anti-Patterns

1. **Test-lərdə copy-paste** - DRY prinsipinə əməl edin
2. **God setUp** - 20+ sətir setUp method
3. **Irrelevant detail** - Test-də lazım olmayan data təyin etmək
4. **Magic numbers** - `create(['total' => 150])` niyə 150?
5. **Mürəkkəb factory chain** - 10 state chain oxunmaz olur
6. **Over-abstraction** - Çox sadə test üçün Builder lazım deyil
