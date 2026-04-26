# Testing Best Practices (Senior)
## İcmal

Testing best practices, illər ərzində yığılmış təcrübədən formalaşmış qaydalar toplusudur.
Bu qaydalar test-in **etibarlı (reliable)**, **oxunaqlı (readable)** və **davamlı
(maintainable)** olmasını təmin edir. Yaxşı test-lər yalnız bug tapmır — həmçinin
codebase-in documentation-u və refactoring üçün safety net-dir.

Əsas guideline: **FIRST principles** (Fast, Independent, Repeatable, Self-validating,
Timely) və dəstəkləyici praktikalar.

### Niyə Best Practices Vacibdir?

1. **Test debt** - Pis yazılmış test-lər refactoring-i dayandırır
2. **Flakiness** - Flaky test CI-ni bloklayır, developer-ləri yormur
3. **Maintenance cost** - Hər feature dəyişikliyi 100 test qırırsa, ya test, ya feature inkişaf etmir
4. **Confidence** - Yaxşı test → deploy-a inam

## Niyə Vacibdir

- **Uzunmüddətli dəyər**: Best practices olmadan yazılan testlər 6 aydan sonra maintainability problemi yaradır — dəyişdirmək production koddan çətin olur
- **Komanda mədəniyyəti**: FIRST prinsipləri komanda daxilindəki test keyfiyyəti standartını müəyyən edir
- **CI etibarlılığı**: Brittle testlər CI pipeline-ı etibarsız edir — komanda nəticələri ignore etməyə başlayır
- **Onboarding**: Yaxşı yazılmış testlər yeni developer-ə kodun necə işlədiyini göstərir

## Əsas Anlayışlar

### FIRST Principles

```
F - Fast          → Test-lər sürətli olsun (ms, saniyə yox)
I - Independent   → Test-lər bir-birindən asılı deyil
R - Repeatable    → Hər environment-də eyni nəticə
S - Self-validating → Pass/Fail avtomatik, manual yoxlama yoxdur
T - Timely        → Code-dan əvvəl və ya eyni anda yazılır
```

### Test Pyramid Yenidən

```
      E2E (5%)           → Slow, expensive, confidence
   Integration (15%)     → Medium speed, covers boundaries
    Unit Tests (80%)     → Fast, isolated, granular
```

### Test Readability: AAA + Given-When-Then

```
// AAA Pattern
Arrange → Setup (test data, mocks)
Act     → Execute (the thing being tested)
Assert  → Verify (expected outcome)

// BDD Style
Given a logged-in admin
When they delete a user
Then the user should be soft-deleted
```

### Test Naming Conventions

```php
// Pattern: test_<unit>_<scenario>_<expected_result>
public function test_user_cannot_login_with_invalid_password(): void

// Or descriptive "/** @test */" annotation
/** @test */
public function guest_cannot_access_admin_panel(): void
```

## Praktik Baxış

### Best Practices

- **Bir konsept = bir test** - Failure-də tez lokalize edirsən
- **Descriptive test names** - `test_refund_fails_for_shipped_order` > `test_refund`
- **AAA / Given-When-Then** - Struktur hər test-də eyni
- **Factories > Hardcoded data** - Refactoring-də az dəyişiklik
- **Fake external services** - Fast, reliable, deterministic
- **Test the interface, not implementation** - Refactoring test-ləri qırmamalıdır
- **Time freezing** - `$this->travelTo()` time-dependent test-lər üçün
- **Custom assertions** - Təkrarlanan yoxlamalar helper-ə çıxarılır
- **Separate slow/fast suites** - Unit = tez feedback, integration = CI
- **Test data builders** - Object Mother, Factory trait-ləri
- **Regular mutation testing** - Coverage-in real keyfiyyətini ölçür
- **Test review checklist** - Kod review-da test keyfiyyəti də yoxlanır

### Anti-Patterns

- **Flaky tests** - Rastgəle pass/fail
- **Test interdependence** - A test-i B-dən əvvəl işləməlidir
- **Ice cream cone** - Çox E2E, az unit
- **Over-mocking** - Hər şey mock, real kod test olunmur
- **Assertion-free tests** - Test işləyir, amma heç nə yoxlamır
- **Gigantic test methods** - 200 sətir setup, 3 assertion
- **Hardcoded waits** - `sleep(5)` - brittle
- **Copy-paste tests** - Tiny variations, böyük maintenance cost
- **Testing framework/library** - Laravel Eloquent-in işləməsini test etmə
- **Snapshot testing without review** - Snapshot-u görmədən kor-koranə qəbul etmə
- **Dead code coverage** - Unreachable branch-lərin coverage-i hesablanır
- **Comments-based grouping** - `// === happy path ===` əvəzinə ayrı test class-ları
- **Yalnız happy path** - Production bug-ların 80%-i edge case-dir
- **No CI execution** - Local-da keçən test CI-də qırılır

## Nümunələr

### FIRST Principle in Action

```php
// GOOD: Fast, Independent, Repeatable
public function test_calculates_tax_correctly(): void
{
    $calculator = new TaxCalculator(rate: 0.18);
    $this->assertSame(18.0, $calculator->apply(100));
}

// BAD: Slow, dependent on external state
public function test_calculates_tax(): void
{
    sleep(2);
    $rate = Http::get('https://tax-api.com/rate')->json('rate');
    $this->assertSame(18.0, (new TaxCalculator($rate))->apply(100));
}
```

### Descriptive Test Name

```php
// BAD
public function testUser(): void {}

// GOOD
public function test_registering_with_existing_email_returns_validation_error(): void {}
```

## Praktik Tapşırıqlar

### 1. Test Organization Best Practices

```php
namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    // Shared setup
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
    }

    // ============= Happy path =============
    public function test_authenticated_user_can_place_order(): void {}
    public function test_order_total_calculated_correctly(): void {}
    public function test_order_confirmation_event_dispatched(): void {}

    // ============= Validation =============
    public function test_cannot_order_without_products(): void {}
    public function test_cannot_order_more_than_stock(): void {}

    // ============= Authorization =============
    public function test_guest_cannot_place_order(): void {}
    public function test_banned_user_cannot_place_order(): void {}

    // ============= Edge cases =============
    public function test_zero_quantity_rejected(): void {}
    public function test_negative_price_product_rejected(): void {}
}
```

### 2. Test Data Builders (Factory Pattern)

```php
// BAD: Hardcoded, fragile
$user = new User;
$user->name = 'Ali';
$user->email = 'ali@example.com';
$user->password = bcrypt('123');
$user->save();

// GOOD: Factory with minimal overrides
$user = User::factory()->create();                       // default
$admin = User::factory()->admin()->create();             // trait/state
$verified = User::factory()->verified()->create();       // state

// Even better: only override what matters
$user = User::factory()->create(['email' => 'specific@test.com']);
```

### 3. Arrange-Act-Assert Clarity

```php
// BAD: Mixed sections
public function test_refund(): void
{
    $order = Order::factory()->paid()->create();
    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/orders/{$order->id}/refund");
    $this->assertSame('refunded', $order->fresh()->status);
    $response->assertOk();
    Mail::assertSent(RefundIssued::class);
}

// GOOD: Clear sections
public function test_admin_can_refund_paid_order(): void
{
    // Arrange
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->paid()->create();

    // Act
    $response = $this->actingAs($admin)
        ->postJson("/api/orders/{$order->id}/refund");

    // Assert
    $response->assertOk();
    $this->assertSame('refunded', $order->fresh()->status);
    Mail::assertSent(RefundIssued::class);
}
```

### 4. One Assertion Per Concept (Not Per Line)

```php
// BAD: Tests multiple concepts
public function test_order_creation(): void
{
    $response = $this->postJson('/api/orders', [...]);
    $response->assertCreated();
    $this->assertDatabaseHas('orders', [...]);
    $this->assertSame(1, Order::count());
    Mail::assertSent(OrderConfirmation::class);
    Event::assertDispatched(OrderPlaced::class);
    Queue::assertPushed(ProcessOrderJob::class);
    $this->assertNotNull(Order::first()->confirmation_code);
    // 7 concepts in 1 test — hard to locate failure
}

// GOOD: Focused tests
public function test_order_creation_returns_201(): void {}
public function test_order_is_persisted(): void {}
public function test_confirmation_email_sent(): void {}
public function test_order_placed_event_fires(): void {}
public function test_processing_job_queued(): void {}
```

### 5. Test Only Public Behavior

```php
// BAD: Testing private method (via reflection)
public function test_private_calculate_tax_works(): void
{
    $reflection = new ReflectionClass(OrderService::class);
    $method = $reflection->getMethod('calculateTax');
    $method->setAccessible(true);
    $this->assertSame(18.0, $method->invoke(new OrderService, 100));
}

// GOOD: Test public behavior that uses private method
public function test_order_total_includes_tax(): void
{
    $order = (new OrderService)->place(products: [['price' => 100, 'qty' => 1]]);
    $this->assertSame(118.0, $order->total); // 100 + 18 tax
}
```

### 6. Avoiding Flaky Tests

```php
// BAD: Depends on current time
public function test_order_created_today(): void
{
    $order = Order::factory()->create();
    $this->assertSame(date('Y-m-d'), $order->created_at->toDateString());
    // Fails at midnight boundary
}

// GOOD: Fix time
public function test_order_created_with_correct_timestamp(): void
{
    $this->travelTo(Carbon::parse('2026-04-17 10:00:00'));

    $order = Order::factory()->create();

    $this->assertSame('2026-04-17', $order->created_at->toDateString());
}

// BAD: Order-dependent
public function test_users_sorted(): void
{
    User::factory()->create(['name' => 'Bob']);
    User::factory()->create(['name' => 'Ali']);
    $users = User::orderBy('name')->get();
    $this->assertSame('Ali', $users[0]->name); // Might fail on different DB
}

// GOOD: Explicit
public function test_users_sorted_alphabetically(): void
{
    $bob = User::factory()->create(['name' => 'Bob']);
    $ali = User::factory()->create(['name' => 'Ali']);

    $users = User::orderBy('name')->get();

    $this->assertTrue($users->first()->is($ali));
    $this->assertTrue($users->last()->is($bob));
}
```

### 7. DRY vs DAMP in Tests

```php
// BAD: Over-DRY → Logic hidden in helpers, hard to read
public function test_refund(): void
{
    $this->setupRefundScenario();
    $this->performRefund();
    $this->assertRefundCompleted();
}

// GOOD: DAMP (Descriptive And Meaningful Phrases) → Test reads like a story
public function test_paid_order_can_be_refunded_by_admin(): void
{
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->paid()->for($customer = User::factory()->create())->create();

    $this->actingAs($admin)->postJson("/api/orders/{$order->id}/refund");

    $this->assertSame('refunded', $order->fresh()->status);
    $this->assertSame(-$order->total, $customer->fresh()->balance);
}
```

### 8. Test Review Checklist

```php
/*
 * Checklist for code review:
 *
 * [ ] Test name describes scenario and expected outcome
 * [ ] AAA structure visible (blank lines between sections)
 * [ ] No `sleep()`, no random data without seed
 * [ ] No reliance on test order
 * [ ] Mocks justified (not mocking the thing being tested)
 * [ ] Single concept per test
 * [ ] Negative/edge cases covered
 * [ ] Database state cleaned (RefreshDatabase or tearDown)
 * [ ] External services (HTTP, Mail, Queue) faked
 * [ ] Assertion messages helpful when fails
 * [ ] Test runs in < 1s (unit) / < 5s (integration)
 */
```

### 9. Custom Assertions for Readability

```php
// tests/TestCase.php
protected function assertOrderIsRefunded(Order $order): void
{
    $order->refresh();

    $this->assertSame('refunded', $order->status,
        "Expected order {$order->id} to be refunded, got: {$order->status}");
    $this->assertNotNull($order->refunded_at);
}

// Usage
public function test_refund_completes(): void
{
    $order = Order::factory()->paid()->create();

    RefundService::process($order);

    $this->assertOrderIsRefunded($order);
}
```

### 10. Fast Feedback Loop

```php
// phpunit.xml - Sort slowest first, group tests
<phpunit
    cacheDirectory=".phpunit.cache"
    executionOrder="depends,defects"
    beStrictAboutCoverageMetadata="true"
    failOnWarning="true"
    failOnRisky="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <groups>
        <exclude>
            <group>slow</group>
            <group>integration</group>
        </exclude>
    </groups>
</phpunit>

// Run only unit tests during development
// php artisan test --testsuite=Unit

// Run everything in CI
// php artisan test --coverage
```

### 11. Test Maintainability Patterns

```php
// Use Object Mother pattern for complex scenarios
class OrderMother
{
    public static function paidWithRefundableItems(): Order
    {
        return Order::factory()
            ->paid()
            ->has(OrderItem::factory()->refundable()->count(2), 'items')
            ->create();
    }

    public static function shippedInternational(): Order
    {
        return Order::factory()
            ->shipped()
            ->international()
            ->create();
    }
}

// Usage
public function test_refundable_items_can_be_refunded(): void
{
    $order = OrderMother::paidWithRefundableItems();
    // ...
}
```

## Ətraflı Qeydlər

**Q1: FIRST principles nələrdir?**
A: **F**ast, **I**ndependent, **R**epeatable, **S**elf-validating, **T**imely. Test-in
keyfiyyətini ölçmək üçün istifadə olunan beş qayda.

**Q2: AAA pattern nədir?**
A: **Arrange-Act-Assert** — test-in üç hissəsi. Arrange: setup; Act: execute;
Assert: verify. Test-i oxumağı asanlaşdırır.

**Q3: DRY və DAMP arasında hansını üstün tutursunuz test-də?**
A: DAMP (Descriptive And Meaningful Phrases). Test dublikat görsə də, hər test müstəqil
oxunmalı və başa düşülməlidir. Prod kodunda DRY, test kodunda DAMP.

**Q4: Flaky test nədir və necə həll olunur?**
A: Bəzən keçir, bəzən qırılır — time, network, order asılılığına görə. Həll:
deterministik data, time freeze, test isolation, external service fake.

**Q5: Test pyramid nə üçün vacibdir?**
A: Unit (çox, sürətli) → Integration (orta) → E2E (az, yavaş). Bu balans sürətli
feedback və real confidence arasında optimal-dır.

**Q6: 100% coverage lazımdırmı?**
A: Xeyr. Critical path-lar üçün yüksək coverage, amma 100% false sense of security
yaradır. Mutation testing coverage-dən daha dəyərlidir.

**Q7: Private metodu test etmək olar?**
A: Birbaşa yox. Əgər private metodu test etmək istəyirsinizsə — yəqin ki, o metod
ayrı class-a çıxarılmalıdır (Extract Class refactoring).

**Q8: Setup-da nə qoymaq olar, nə yox?**
A: Bütün test-lərə ortaq olan, test-in məqsədindən kənar detallar (mock config, base
user). Test-in behaviour-ə təsir edən data setUp-da olmamalıdır.

**Q9: Test nə qədər tez olmalıdır?**
A: Unit: < 10 ms. Integration: < 500 ms. E2E: saniyələr. Bütün unit suite 10 saniyədən
çox olmamalıdır (ideal).

**Q10: Test review-da əsas nəyə baxırsınız?**
A: (1) Test adı aydındır? (2) Tək konsept yoxlanılır? (3) Negative path var? (4) Flaky
risk? (5) Mock-lar əsaslıdır? (6) Assertion message helpful-dir? (7) Test kodu DRY-dır,
amma oxunaqlı?

## Əlaqəli Mövzular

- [Test Patterns (Senior)](26-test-patterns.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Continuous Testing (Senior)](23-continuous-testing.md)
- [Test Data Management (Senior)](33-test-data-management.md)
