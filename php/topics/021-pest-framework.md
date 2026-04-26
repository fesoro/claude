# Pest Framework (Junior)

## Mündəricat
1. [Pest nədir?](#pest-nədir)
2. [PHPUnit vs Pest](#phpunit-vs-pest)
3. [Quraşdırma](#quraşdırma)
4. [Test syntax](#test-syntax)
5. [Expectations API](#expectations-api)
6. [Datasets](#datasets)
7. [Higher Order tests](#higher-order-tests)
8. [Architectural Testing](#architectural-testing)
9. [Plugins](#plugins)
10. [Laravel ilə inteqrasiya](#laravel-ilə-inteqrasiya)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Pest nədir?

```
Pest — PHP üçün modern, expressive testing framework.
PHPUnit üzərində qurulmuşdur (yəni alt qatda PHPUnit işləyir),
amma daha qısa, daha "natural language" syntax təqdim edir.

Yaranma: 2021, Nuno Maduro tərəfindən (Laravel team).
PEST = "PHP Expressive Stress Testing" oyununda

Niyə populyar oldu?
  - Boilerplate az
  - Closure-based test (class lazım deyil)
  - Higher-order test
  - Architectural testing built-in
  - Laravel-friendly
```

---

## PHPUnit vs Pest

```php
<?php
// PHPUnit
class UserTest extends TestCase
{
    public function test_user_can_be_created(): void
    {
        $user = new User('Ali', 'ali@example.com');
        
        $this->assertEquals('Ali', $user->name);
        $this->assertEquals('ali@example.com', $user->email);
    }
}
```

```php
<?php
// Pest — eyni test
test('user can be created', function () {
    $user = new User('Ali', 'ali@example.com');
    
    expect($user->name)->toBe('Ali');
    expect($user->email)->toBe('ali@example.com');
});

// Daha qısa: it() syntax
it('creates a user', function () {
    $user = new User('Ali', 'ali@example.com');
    expect($user->name)->toBe('Ali');
});
```

```
Müqayisə:
  PHPUnit   class + method, $this->assertX
  Pest      function + closure, expect()->toX

Hər ikisi:
  - Eyni runner, eyni report
  - PHPUnit-də olan hər şey Pest-də işləyir
  - Mixed mode (eyni layihədə hər ikisi)
```

---

## Quraşdırma

```bash
composer require pestphp/pest --dev --with-all-dependencies
./vendor/bin/pest --init

# Laravel
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

```php
<?php
// tests/Pest.php — global config
uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature/Api');

// Custom expectation
expect()->extend('toBeValidEmail', function () {
    return $this->toMatch('/^[^@]+@[^@]+\.[^@]+$/');
});

// Global helper
function login(?User $user = null): User
{
    $user ??= User::factory()->create();
    test()->actingAs($user);
    return $user;
}
```

---

## Test syntax

```php
<?php
// tests/Unit/CalculatorTest.php

beforeEach(function () {
    $this->calc = new Calculator();
});

afterEach(function () {
    // cleanup
});

beforeAll(function () { /* */ });
afterAll(function () { /* */ });

it('adds two numbers', function () {
    $result = $this->calc->add(2, 3);
    expect($result)->toBe(5);
});

it('throws on division by zero', function () {
    $this->calc->divide(1, 0);
})->throws(DivisionByZeroError::class);

it('skips on Windows', function () {
    expect(true)->toBeTrue();
})->skip(PHP_OS_FAMILY === 'Windows', 'Linux/Mac only');

it('runs only in CI', function () {
    expect(true)->toBeTrue();
})->skip(! getenv('CI'), 'CI only');

// Group tests
it('handles edge cases', function () { /* */ })
    ->group('edge-cases', 'critical');

// Run: vendor/bin/pest --group=critical
```

---

## Expectations API

```php
<?php
// expect() — chainable, expressive
expect($value)
    ->toBe('expected')             // strict equal ===
    ->toEqual('expected')          // loose equal ==
    ->toBeNull()
    ->toBeTrue()
    ->toBeFalse()
    ->toBeEmpty()
    ->toBeArray()
    ->toBeString()
    ->toBeInt()
    ->toBeFloat()
    ->toBeNumeric()
    ->toBeBool()
    ->toBeCallable()
    ->toBeIterable()
    ->toBeInstanceOf(User::class)
    ->toBeBetween(1, 10)
    ->toBeGreaterThan(5)
    ->toBeLessThan(100)
    ->toContain('substring')
    ->toContainOnlyInstancesOf(Order::class)
    ->toHaveCount(3)
    ->toHaveKey('name')
    ->toHaveKeys(['id', 'name', 'email'])
    ->toHaveProperty('id')
    ->toMatch('/regex/')
    ->toMatchArray(['name' => 'Ali'])
    ->toMatchObject(['name' => 'Ali'])
    ->toStartWith('hello')
    ->toEndWith('world')
    ->toBeUuid()
    ->toBeJson();

// Inversion
expect($value)->not->toBeNull();
expect($value)->not->toContain('error');

// Multiple expectations
expect($user)
    ->name->toBe('Ali')
    ->email->toBeValidEmail()
    ->createdAt->toBeInstanceOf(DateTime::class);

// Each — collection
expect([1, 2, 3])->each->toBeInt();
expect($users)->each(fn($u) => $u->toBeInstanceOf(User::class));

// Sequence — sıralı
expect([1, 2, 3])->sequence(
    fn($v) => $v->toBe(1),
    fn($v) => $v->toBe(2),
    fn($v) => $v->toBe(3),
);
```

---

## Datasets

```php
<?php
// Data provider Pest-də — sadə array
it('validates email', function (string $email, bool $valid) {
    expect(filter_var($email, FILTER_VALIDATE_EMAIL))
        ->{$valid ? 'not->toBeFalse' : 'toBeFalse'}();
})->with([
    ['ali@example.com', true],
    ['invalid', false],
    ['@invalid.com', false],
    ['ali@example', false],
]);

// Named datasets — cleaner output
it('calculates discount', function (string $tier, float $expected) {
    expect(getDiscount($tier))->toBe($expected);
})->with([
    'guest'   => ['guest', 0.0],
    'member'  => ['member', 0.05],
    'vip'     => ['vip', 0.15],
    'premium' => ['premium', 0.25],
]);

// Reusable dataset (Pest.php-də)
dataset('emails', [
    'valid'   => 'test@example.com',
    'invalid' => 'not-email',
]);

it('handles emails', function (string $email) {
    // ...
})->with('emails');

// Closure-based dataset (lazy)
dataset('users', function () {
    return [
        User::factory()->admin()->create(),
        User::factory()->guest()->create(),
    ];
});
```

---

## Higher Order tests

```php
<?php
// Pest unique feature — chained tests on objects

it('creates a user')
    ->expect(fn() => User::create(['name' => 'Ali']))
    ->name->toBe('Ali')
    ->id->not->toBeNull();

// Tests as expressions
test('config has correct values')
    ->expect(config('app.name'))
    ->toBe('Laravel')
    ->and(config('app.env'))
    ->toBe('testing');

// Higher-order on collections
test('all users have email')
    ->expect(User::all())
    ->each->email->not->toBeEmpty();
```

---

## Architectural Testing

```php
<?php
// tests/Architecture/ArchTest.php
// Pest 2+ unique feature — kod struktur testləri

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toBeClasses()
    ->toExtend('App\Http\Controllers\Controller')
    ->not->toUse('Illuminate\Database\Eloquent\Model');  // Controller Model-i birbaşa istifadə etməsin

arch('models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('value objects')
    ->expect('App\Domain\ValueObjects')
    ->toBeReadonly()
    ->toBeFinal();

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug functions')
    ->expect(['dd', 'dump', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('domain layer purity')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate\\',     // Domain Laravel-dən asılı olmamalı
        'Symfony\\',
    ]);

arch('exceptions naming')
    ->expect('App\Exceptions')
    ->toExtend('Exception')
    ->toHaveSuffix('Exception');

// Run: vendor/bin/pest --filter=arch
```

---

## Plugins

```bash
# Mutation testing (kodun test coverage-i)
composer require pestphp/pest-plugin-mutate --dev
vendor/bin/pest --mutate

# Stressless — load testing built-in
composer require pestphp/pest-plugin-stressless --dev
# stress('https://example.com/api')->for(10)->seconds()

# Type coverage — kod nə qədər type-hint olunmuşdur
composer require pestphp/pest-plugin-type-coverage --dev
vendor/bin/pest --type-coverage --min=95

# Drift — snapshot testing
composer require pestphp/pest-plugin-drift --dev

# Browser testing (Dusk wrapper)
composer require pestphp/pest-plugin-browser --dev
visit('/login')
    ->fill('email', 'test@test.com')
    ->fill('password', 'password')
    ->press('Login')
    ->assertSee('Dashboard');
```

---

## Laravel ilə inteqrasiya

```php
<?php
// tests/Feature/UserControllerTest.php
use function Pest\Laravel\{get, post, actingAs};

it('lists users', function () {
    User::factory()->count(3)->create();
    
    get('/api/users')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('requires auth', function () {
    get('/api/profile')->assertRedirect('/login');
});

it('creates user', function () {
    $admin = User::factory()->admin()->create();
    
    actingAs($admin)
        ->post('/api/users', [
            'name'  => 'New User',
            'email' => 'new@example.com',
        ])
        ->assertCreated();
    
    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
});

// Database
it('uses transaction', function () {
    $count = User::count();
    User::factory()->create();
    expect(User::count())->toBe($count + 1);
})->uses(RefreshDatabase::class);

// Eloquent factories
it('creates users with factories', function () {
    $user = User::factory()
        ->has(Post::factory()->count(3))
        ->create();
    
    expect($user->posts)->toHaveCount(3);
});

// Mocking
use Illuminate\Support\Facades\Http;

it('calls external API', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['ok' => true], 200),
    ]);
    
    $service = new ExternalApiService();
    expect($service->ping())->toBeTrue();
    
    Http::assertSent(fn($r) => $r->url() === 'https://api.example.com/ping');
});
```

---

## İntervyu Sualları

- Pest PHPUnit-dən nə ilə fərqlənir? Eyni şeylərdir, yoxsa əvəz edir?
- Higher-order test nədir, hansı boilerplate-i azaldır?
- Architectural test nə üçündür? Nümunə verin.
- `it()` və `test()` arasında praktiki fərq?
- Datasets necə yazılır? Named dataset niyə üstündür?
- `expect()->each` collection üçün necə işləyir?
- Pest mutation testing nədir?
- Type coverage plugin niyə faydalıdır?
- `arch()->not->toUse()` niyə qiymətli yoxlamadır?
- Laravel test-lərində Pest CI sürətinə təsir edirmi?
- Custom expectation necə yazılır?
- `beforeEach` ilə `setUp` arasında fərq?
