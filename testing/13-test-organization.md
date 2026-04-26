# Test Organization (Middle)
## İcmal

Test organization, testlərin effektiv şəkildə strukturlaşdırılması, adlandırılması,
qruplaşdırılması və idarə edilməsi prosesidir. Yaxşı təşkil olunmuş test suite-i oxumaq,
saxlamaq və işlətmək asandır. Pis təşkil olunmuş testlər isə vaxt itkisi, karışıqlıq
və yavaş feedback loop-a səbəb olur.

Laravel/PHPUnit ekosistemində test organization phpunit.xml konfiqurasiyası, qovluq
strukturu, naming convention-lar və test suite-lərin düzgün bölünməsini əhatə edir.

### Niyə Test Organization Vacibdir?

1. **Sürətli naviqasiya** - Lazımi testi tez tapmaq
2. **Seçici icra** - Yalnız lazımi testləri işlətmək
3. **Paralel icra** - Müstəqil testləri eyni anda işlətmək
4. **Bakım asanlığı** - Yeni developer-lərin test strukturunu anlaması
5. **CI/CD optimallaşdırma** - Fast feedback üçün test prioritizasiyası

## Niyə Vacibdir

- **Böyüyən kod bazasında naviqasiya sürəti** — Yüzlərlə test faylı olan layihədə düzgün qovluq strukturu və adlandırma olmadan lazımi testi tapmaq dəqiqələr aparır; yaxşı organizasiya bunu saniyələrə endirir.
- **CI/CD feedback loop-un optimallaşdırılması** — Test suite-ləri düzgün bölündükdə sürətli unit testlər əvvəl, yavaş integration testlər sonra işlənir; developer-lər xəta haqqında daha tez xəbər tutur.
- **Komanda miqyaslılığı** — Yeni developer test strukturunu başa düşdükdə test yazmağa başlamaq asanlaşır; inconsistent adlandırma konfransiyası isə review prosesini ləngidir.
- **Paralel icra imkanı** — Müstəqil test suite-ləri ayrı process-lərdə eyni anda işlənə bilər; CI/CD vaxtı 4-8x azalır, bu isə böyük layihələrdə kritikdir.
- **Seçici icra ilə inkişaf sürəti** — `--testsuite=Unit` və ya `--filter=OrderService` kimi əmrlərlə yalnız işlədiyiniz hissəni test edərək daha sürətli iterasiya etmək mümkün olur.

## Əsas Anlayışlar

### Laravel Test Qovluq Strukturu

```
tests/
├── Unit/                        # Unit testlər (database/framework yox)
│   ├── Services/
│   │   ├── OrderServiceTest.php
│   │   ├── PricingServiceTest.php
│   │   └── PaymentServiceTest.php
│   ├── Models/
│   │   ├── UserTest.php
│   │   └── PostTest.php
│   └── ValueObjects/
│       ├── MoneyTest.php
│       └── EmailTest.php
├── Feature/                     # Feature testlər (HTTP, database)
│   ├── Api/
│   │   ├── PostApiTest.php
│   │   ├── UserApiTest.php
│   │   └── AuthApiTest.php
│   ├── Web/
│   │   ├── PostWebTest.php
│   │   └── DashboardTest.php
│   └── Console/
│       └── PruneCommandTest.php
├── Browser/                     # Dusk testlər
│   ├── LoginTest.php
│   └── CheckoutTest.php
├── Integration/                 # Integration testlər
│   ├── PaymentGatewayTest.php
│   └── EmailServiceTest.php
├── Support/                     # Test helper-ləri
│   ├── Traits/
│   │   └── CreatesTestUsers.php
│   └── Factories/
│       └── TestDataBuilder.php
├── TestCase.php
├── CreatesApplication.php
└── DuskTestCase.php
```

### Naming Conventions

```php
// ---- Test Class Adlandırma ----
// Pattern: {TestEdilənClass}Test
class OrderServiceTest extends TestCase {}
class UserApiTest extends TestCase {}
class PostControllerTest extends TestCase {}

// ---- Test Method Adlandırma ----

// Style 1: it_does_something (tövsiyə olunan)
/** @test */
public function it_creates_an_order_with_valid_data(): void {}

/** @test */
public function it_rejects_empty_orders(): void {}

// Style 2: test prefix (annotation lazım deyil)
public function test_creates_an_order_with_valid_data(): void {}

// Style 3: should format
/** @test */
public function user_should_be_able_to_login(): void {}

// ---- PIS adlandırma nümunələri ----
public function testOrder(): void {}            // Çox ümumi
public function test1(): void {}                 // Mənasız
public function testStuff(): void {}             // Qeyri-müəyyən
```

### Test Groups və Tags

```php
<?php

// PHPUnit Group Annotation
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
#[Group('integration')]
class PaymentGatewayTest extends TestCase
{
    #[Group('critical')]
    public function test_payment_processing(): void {}
}

// Yalnız müəyyən grupu işlət
// vendor/bin/phpunit --group=critical
// vendor/bin/phpunit --exclude-group=slow
```

## Praktik Baxış

### Best Practices

1. **Mirror source structure** - app/Services/ → tests/Unit/Services/
2. **Consistent naming** - Komandada bir naming convention seçin
3. **Fast tests first** - CI/CD-də Unit → Feature → Integration sırası
4. **Helper traits yazın** - Təkrarlanan test setup-ı trait-ə çıxarın
5. **DataProvider istifadə edin** - Eyni test, fərqli data
6. **Group/tag istifadə edin** - Slow, critical, external qrupları

### Anti-Patterns

1. **God test class** - Bir class-da 50+ test method
2. **Testlər arası asılılıq** - Test A-nın pass olması Test B-yə lazımdır
3. **Shared mutable state** - Static property-lər test-lər arası paylaşılır
4. **Unclear naming** - test1, test2, testStuff
5. **setUp-da çox iş görmək** - Yalnız ortaq setup setUp-da olsun
6. **Bütün testləri bir suite-də saxlamaq** - Seçici icra mümkün olmur

## Nümunələr

### phpunit.xml Tam Konfiqurasiya

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Providers</directory>
        </exclude>
    </source>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>

    <groups>
        <exclude>
            <group>slow</group>
            <group>external</group>
        </exclude>
    </groups>
</phpunit>
```

### Paralel Test İcrası

```bash
# Laravel Parallel Testing (built-in)
php artisan test --parallel

# Process sayını təyin et
php artisan test --parallel --processes=8

# ParaTest (standalone)
composer require --dev brianium/paratest
vendor/bin/paratest --processes=4

# Yalnız unit testləri paralel
vendor/bin/paratest --testsuite=Unit --processes=8

# PHPUnit ilə
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
```

## Praktik Tapşırıqlar

### Base TestCase Konfiqurasiyası

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Bütün testlər üçün ümumi setup
    }

    /**
     * Helper: Authenticated user yaratmaq
     */
    protected function signIn(?User $user = null): User
    {
        $user = $user ?? User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    /**
     * Helper: Admin user yaratmaq
     */
    protected function signInAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        return $admin;
    }

    /**
     * Helper: API authentication
     */
    protected function apiSignIn(?User $user = null): User
    {
        $user = $user ?? User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }
}
```

### Test Trait-ləri

```php
<?php

namespace Tests\Support\Traits;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;

trait CreatesTestPosts
{
    protected function createPublishedPost(array $attributes = []): Post
    {
        return Post::factory()
            ->published()
            ->create($attributes);
    }

    protected function createPostWithComments(int $commentCount = 3): Post
    {
        return Post::factory()
            ->has(Comment::factory()->count($commentCount))
            ->create();
    }

    protected function createUserWithPosts(int $postCount = 5): User
    {
        return User::factory()
            ->has(Post::factory()->count($postCount))
            ->create();
    }
}
```

```php
<?php

// Trait istifadəsi
namespace Tests\Feature\Api;

use Tests\TestCase;
use Tests\Support\Traits\CreatesTestPosts;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PostApiTest extends TestCase
{
    use RefreshDatabase, CreatesTestPosts;

    /** @test */
    public function it_lists_published_posts(): void
    {
        $post = $this->createPublishedPost();

        $this->apiSignIn();
        $response = $this->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $post->id]);
    }
}
```

### Data Providers

```php
<?php

namespace Tests\Unit;

use App\Services\ValidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidationServiceTest extends TestCase
{
    #[DataProvider('validEmailProvider')]
    public function test_valid_emails_pass_validation(string $email): void
    {
        $service = new ValidationService();
        $this->assertTrue($service->isValidEmail($email));
    }

    public static function validEmailProvider(): array
    {
        return [
            'standard email' => ['user@example.com'],
            'with subdomain' => ['user@mail.example.com'],
            'with plus' => ['user+tag@example.com'],
            'with dots' => ['first.last@example.com'],
        ];
    }

    #[DataProvider('invalidEmailProvider')]
    public function test_invalid_emails_fail_validation(string $email): void
    {
        $service = new ValidationService();
        $this->assertFalse($service->isValidEmail($email));
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'no at sign' => ['userexample.com'],
            'no domain' => ['user@'],
            'spaces' => ['user @example.com'],
            'double at' => ['user@@example.com'],
        ];
    }

    #[DataProvider('discountProvider')]
    public function test_discount_calculation(
        float $total,
        string $type,
        float $expected
    ): void {
        $service = new ValidationService();
        $this->assertEquals($expected, $service->calculateDiscount($total, $type));
    }

    public static function discountProvider(): array
    {
        return [
            'vip 100' => [100, 'vip', 20.0],
            'vip 200' => [200, 'vip', 40.0],
            'regular under threshold' => [50, 'regular', 0.0],
            'regular over threshold' => [150, 'regular', 7.5],
        ];
    }
}
```

### Test İcra Strategiyaları

```bash
# 1. Bütün testlər
vendor/bin/phpunit

# 2. Yalnız bir suite
vendor/bin/phpunit --testsuite=Unit

# 3. Yalnız bir fayl
vendor/bin/phpunit tests/Unit/Services/OrderServiceTest.php

# 4. Yalnız bir method
vendor/bin/phpunit --filter=test_creates_an_order

# 5. Yalnız bir group
vendor/bin/phpunit --group=critical

# 6. Slow testləri istisna et
vendor/bin/phpunit --exclude-group=slow

# 7. Fail olan testləri təkrar işlət
vendor/bin/phpunit --order-by=defects --stop-on-failure

# 8. Laravel artisan
php artisan test
php artisan test --filter=OrderServiceTest
php artisan test --testsuite=Unit --parallel
```

### setUp və tearDown Pattern-ləri

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hər testdən əvvəl
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        // Hər testdən sonra (nadir hallarda lazımdır)
        parent::tearDown();
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Bütün testlərdən əvvəl bir dəfə (static state)
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Bütün testlərdən sonra bir dəfə
    }
}
```

## Ətraflı Qeydlər

### 1. Test qovluqlarını necə strukturlaşdırarsınız?
**Cavab:** Laravel-da `tests/Unit` (framework-siz unit testlər), `tests/Feature` (HTTP və database testləri), `tests/Browser` (Dusk testləri) əsas qovluqlardır. Feature içində `Api/`, `Web/`, `Console/` alt-qovluqları yaradıram. Unit-da source code strukturunu mirror edirəm (Services/, Models/). Traits və helper-lər `tests/Support/` altında.

### 2. Test naming convention nə olmalıdır?
**Cavab:** Test class: `{TestedClass}Test`, test method: `it_does_something` formatı ilə `@test` annotation, və ya `test_does_something` prefix. Method adı tam cümlə kimi oxunmalıdır. Pis: `testOrder()`. Yaxşı: `it_rejects_orders_with_no_items()`. Method adı test-in nə test etdiyini izah etməlidir.

### 3. Test suite-ləri necə bölürsünüz?
**Cavab:** phpunit.xml-da testsuite təyin edirəm: Unit (sürətli, framework-siz), Feature (HTTP/DB), Integration (xarici service-lər). CI/CD-də əvvəl Unit testlər işlər (sürətli feedback), sonra Feature, sonra Integration. `--testsuite=Unit` ilə seçici icra mümkündür.

### 4. Parallel testing necə işləyir?
**Cavab:** Testlər müstəqil process-lərdə eyni anda işlədilir. Laravel `php artisan test --parallel` ilə dəstəkləyir, hər process ayrı test database istifadə edir. ParaTest package daha çox konfiqurasiya imkanı verir. Testlər bir-birindən asılı olmamalıdır - shared state olmamalıdır.

### 5. DataProvider nədir və nə üçün istifadə olunur?
**Cavab:** DataProvider eyni test-i fərqli data setləri ilə işlətməyə imkan verir. `#[DataProvider('providerName')]` attribute ilə təyin edilir. Static method array qaytarır. Hər array elementi ayrı test case-dir. Kod təkrarını azaldır, edge case-ləri asanlıqla əlavə etməyə imkan verir.

### 6. setUp və setUpBeforeClass arasındakı fərq nədir?
**Cavab:** `setUp()` hər test method-dan əvvəl çağırılır - hər test üçün fresh state verir. `setUpBeforeClass()` bütün test class-ı üçün bir dəfə çağırılır (static). setUp-da factory, fake storage yaradılır. setUpBeforeClass-da expensive one-time setup (fixture load, connection) edilir.

### 7. Flaky testlərin qarşısını necə alırsınız?
**Cavab:** 1) Test isolation - shared state olmamalı, 2) Deterministic data - random deyil, fixed test data, 3) Time-based testdən qaçınmaq - `Carbon::setTestNow()` istifadə edin, 4) External dependency mock-lamaq, 5) Explicit wait (sleep yox), 6) CI-da retry əlavə etmək son çarədir.

## Əlaqəli Mövzular

- [Testing Fundamentals (Junior)](01-testing-fundamentals.md)
- [Unit Testing (Junior)](02-unit-testing.md)
- [Code Coverage (Middle)](12-code-coverage.md)
- [Pest PHP (Middle)](14-pest-php.md)
- [Test Patterns (Senior)](26-test-patterns.md)
- [Test Data Management (Senior)](33-test-data-management.md)
