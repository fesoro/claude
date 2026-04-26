# Pest PHP (Middle)

## İcmal

Pest, PHP üçün müasir test framework-üdür. PHPUnit üzərində qurulub, lakin daha az boilerplate kod tələb edir, daha oxunaqlı syntax təqdim edir. Laravel 11-dən başlayaraq **default test framework** kimi gəlir.

## Niyə Vacibdir

- Laravel 11+ layihələrdə default — yeni proyektlər birbaşa Pest ilə başlayır
- PHPUnit-ə nisbətən 40-60% daha az kod
- `it()` / `test()` / `describe()` ilə daha oxunaqlı testlər
- Parallel test execution built-in
- Architecture tests, snapshot tests, mutation testing inteqrasiyası var

## Əsas Anlayışlar

### PHPUnit vs Pest fərqi

```php
// PHPUnit
class UserTest extends TestCase
{
    public function test_user_can_be_created(): void
    {
        $user = User::factory()->create(['name' => 'Orkhan']);
        $this->assertEquals('Orkhan', $user->name);
    }
}

// Pest
test('user can be created', function () {
    $user = User::factory()->create(['name' => 'Orkhan']);
    expect($user->name)->toBe('Orkhan');
});
```

### `test()` vs `it()`

```php
// test() — standalone
test('creates user successfully', function () { ... });

// it() — describe() bloku ilə istifadə olunur
describe('User', function () {
    it('can be created', function () { ... });
    it('has a valid email', function () { ... });
});
```

### `expect()` API

```php
expect($value)
    ->toBe(42)                    // strict equal ===
    ->toEqual(['a' => 1])         // loose equal ==
    ->toBeTrue()
    ->toBeFalse()
    ->toBeNull()
    ->toBeString()
    ->toBeInt()
    ->toBeArray()
    ->toBeInstanceOf(User::class)
    ->toHaveCount(3)
    ->toContain('item')
    ->toMatchArray(['key' => 'value'])
    ->toHaveKey('name')
    ->toThrow(ValidationException::class)
    ->toThrow(ValidationException::class, 'The name field is required');
```

### Chained expectations

```php
expect($user)
    ->name->toBe('Orkhan')
    ->email->toContain('@')
    ->age->toBeGreaterThan(0);

expect([1, 2, 3])
    ->toHaveCount(3)
    ->each->toBeInt();
```

## Praktik Baxış

### Laravel ilə qurulum

Laravel 11+ — artıq daxildir. Köhnə layihədə:

```bash
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

### Pest faylları

```
tests/
├── Pest.php          ← global config, uses(), helpers
├── Feature/
│   └── UserTest.php
└── Unit/
    └── OrderTest.php
```

`Pest.php` — bütün testlər üçün shared setup:

```php
// tests/Pest.php
uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

// Global helper
function actingAsAdmin(): User
{
    return test()->actingAs(User::factory()->admin()->create());
}
```

### `uses()` — Trait-ləri test-ə əlavə etmək

```php
// Faylın başında
uses(RefreshDatabase::class);
uses(RefreshDatabase::class, WithFaker::class);

// Yalnız bir test üçün
test('saves to db', function () {
    // ...
})->uses(RefreshDatabase::class);
```

### Datasets (Data Providers)

```php
// Inline dataset
test('validates email', function (string $email, bool $valid) {
    expect(filter_var($email, FILTER_VALIDATE_EMAIL))->toBe($valid);
})->with([
    ['test@example.com', true],
    ['invalid-email', false],
    ['another@test.org', true],
]);

// Named dataset (tests/Datasets/Emails.php)
dataset('invalid_emails', [
    'no-at-sign',
    'missing@domain',
    '@nodomain.com',
]);

test('rejects invalid email', function (string $email) {
    // ...
})->with('invalid_emails');
```

### Hooks

```php
beforeEach(function () {
    $this->user = User::factory()->create();
});

afterEach(function () {
    // cleanup
});

beforeAll(function () {
    // bütün testlərdən əvvəl bir dəfə
});
```

### Expectations ilə Exception test etmək

```php
test('throws when user not found', function () {
    expect(fn () => User::findOrFail(999))
        ->toThrow(ModelNotFoundException::class);
});

// Artisan Command
test('fails with invalid input', function () {
    $this->artisan('my:command --invalid')
        ->assertFailed();
});
```

### Mocking (Laravel Fakes)

```php
test('sends welcome email on registration', function () {
    Mail::fake();

    post('/register', [
        'name' => 'Orkhan',
        'email' => 'orkhan@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertRedirect('/dashboard');

    Mail::assertSent(WelcomeMail::class, fn ($mail) =>
        $mail->hasTo('orkhan@example.com')
    );
});
```

### Higher Order Tests

```php
// Bir-liner testlər
test('guest cannot access dashboard')
    ->get('/dashboard')
    ->assertRedirect('/login');

test('admin can access dashboard')
    ->actingAs(User::factory()->admin()->create())
    ->get('/dashboard')
    ->assertOk();
```

### Architecture Tests

```php
// tests/Feature/ArchTest.php
test('controllers do not use models directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Models');

test('models extend base model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

test('no debug functions')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
```

## Praktik Tapşırıqlar

### Tapşırıq 1: PHPUnit test-ini Pest-ə çevir

```php
// PHPUnit — köhnə
class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_total_is_calculated_correctly(): void
    {
        $order = Order::factory()->create();
        $order->items()->createMany([
            ['price' => 100, 'quantity' => 2],
            ['price' => 50, 'quantity' => 1],
        ]);

        $this->assertEquals(250, $order->fresh()->total);
    }
}

// Pest — yeni
uses(RefreshDatabase::class);

test('order total is calculated correctly', function () {
    $order = Order::factory()->create();
    $order->items()->createMany([
        ['price' => 100, 'quantity' => 2],
        ['price' => 50, 'quantity' => 1],
    ]);

    expect($order->fresh()->total)->toBe(250);
});
```

### Tapşırıq 2: API endpoint-i dataset ilə test et

```php
test('validates required fields', function (array $data, string $field) {
    actingAs(User::factory()->create());

    post('/api/products', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    [['name' => ''], 'name'],
    [['price' => -1], 'price'],
    [['stock' => 'abc'], 'stock'],
]);
```

### Tapşırıq 3: describe ilə qruplaşdırma

```php
describe('Product API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create();
    });

    it('returns product list', function () {
        actingAs($this->user)
            ->get('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('creates a product', function () {
        actingAs($this->user)
            ->post('/api/products', ['name' => 'Test', 'price' => 99])
            ->assertCreated();

        expect(Product::count())->toBe(2);
    });
});
```

## Əlaqəli Mövzular

- [Unit Testing (Junior)](02-unit-testing.md)
- [Feature Testing (Junior)](04-feature-testing.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Mocking (Middle)](07-mocking.md)
- [Test Doubles (Middle)](08-test-doubles.md)
