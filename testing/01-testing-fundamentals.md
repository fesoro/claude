# Testing Fundamentals

## Nədir? (What is it?)

Software testing, proqram təminatının düzgün işlədiyini yoxlamaq prosesidir. Əsas məqsəd
bug-ları production-a çatmadan əvvəl tapmaq, keyfiyyəti təmin etmək və proqramın
gözlənilən davranışını sənədləşdirməkdir.

Testing olmadan developer-lər hər dəyişiklikdən sonra manual olaraq bütün sistemi yoxlamalıdırlar.
Bu həm vaxt itkisi, həm də insan xətalarına açıq yoldur. Automated testing bu prosesi
proqramlaşdırılmış qaydada həll edir.

### Niyə Test Yazmalıyıq?

1. **Bug-ların erkən tapılması** - Production-da bug tapmaq development-dəkindən 100x baha başa gəlir
2. **Refactoring rahatlığı** - Testlər dəyişiklikllərin mövcud funksionallığı pozmadığını təmin edir
3. **Documentation** - Testlər kodun necə işləməli olduğunu izah edir
4. **Dizayn keyfiyyəti** - Test yazmaq daha yaxşı kod arxitekturasına sövq edir
5. **Komanda güvəni** - Yeni developer-lər testlər sayəsində dəyişiklik etməyə cəsarət edir

## Əsas Konseptlər (Key Concepts)

### Testing Pyramid (Test Piramidası)

```
        /  E2E  \          ← Az sayda, yavaş, bahalı
       /----------\
      / Integration \      ← Orta sayda
     /----------------\
    /    Unit Tests     \  ← Çox sayda, sürətli, ucuz
   /____________________\
```

**Unit Tests (70%)**: Tək bir funksiyanı və ya metodu test edir. Ən sürətli və ucuzdur.
**Integration Tests (20%)**: Komponentlər arası qarşılıqlı əlaqəni test edir.
**E2E Tests (10%)**: Bütün sistemi istifadəçi perspektivindən test edir.

### Cost of Bugs (Bug-ların Dəyəri)

| Mərhələ | Nisbi Dəyər |
|---------|-------------|
| Requirements | 1x |
| Design | 5x |
| Coding | 10x |
| Testing | 20x |
| Production | 100x |

Bug nə qədər gec tapılsa, düzəltmək bir o qədər bahalıdır. Requirement mərhələsində
tapılan bug 1 saat vaxt alırsa, production-da tapılan eyni bug günlərlə vaxt, pul
və müştəri itkisi demək ola bilər.

### Test Coverage (Test Əhatəsi)

Test coverage kodun nə qədər hissəsinin testlərlə əhatə olunduğunu göstərir.

```
Coverage = (Test edilən kod sətirləri / Ümumi kod sətirləri) × 100
```

**Diqqət**: 100% coverage bug olmadığı mənasına gəlmir. Coverage yalnız kodun icra
edildiyini göstərir, məntiqi düzgünlüyü deyil.

Ümumi hədəflər:
- **80%** - Çox layihələr üçün yaxşı hədəf
- **90%+** - Kritik sistemlər (maliyyə, tibb)
- **100%** - Adətən praktik deyil, diminishing returns

### Test-First vs Test-Last

**Test-First (TDD)**:
1. Əvvəl test yaz (Red)
2. Testi keçir (Green)
3. Refactor et

**Test-Last**:
1. Əvvəl kodu yaz
2. Sonra test yaz

Test-First yanaşması adətən daha yaxşı dizayna gətirib çıxarır, çünki test yazarkən
API-nın necə görünməli olduğunu düşünürsünüz.

## Testing Terminologiyası

### SUT (System Under Test)

Test edilən komponent və ya sinifdir. Hər testin bir SUT-u olmalıdır.

```php
// SUT = Calculator sinifi
class CalculatorTest extends TestCase
{
    public function test_addition(): void
    {
        $calculator = new Calculator(); // SUT
        $result = $calculator->add(2, 3);
        $this->assertEquals(5, $result);
    }
}
```

### Mock

Davranışı proqramlaşdırılmış test dublyorudur. Həm dəyər qaytarır, həm də çağırışların
düzgün edildiyini yoxlayır (verification).

```php
$paymentGateway = Mockery::mock(PaymentGateway::class);
$paymentGateway->shouldReceive('charge')
    ->once()
    ->with(100)
    ->andReturn(true);
```

### Stub

Yalnız əvvəlcədən təyin edilmiş dəyər qaytaran test dublyorudur. Verification etmir.

```php
$userRepository = Mockery::mock(UserRepository::class);
$userRepository->shouldReceive('find')
    ->andReturn(new User(['name' => 'Test']));
```

### Spy

Çağırışları qeyd edir, sonra yoxlama imkanı verir. Mock-dan fərqi odur ki, əvvəlcə
gözləntilər təyin etmək lazım deyil.

```php
$spy = Mockery::spy(EventDispatcher::class);

// Kodu icra et
$service->process();

// Sonra yoxla
$spy->shouldHaveReceived('dispatch')->with(OrderCreated::class);
```

### Fake

Real implementasiyanın sadələşdirilmiş versiyasıdır. Məsələn, in-memory database
əsl database əvəzinə.

```php
// Real: Redis cache
// Fake: Array cache
class FakeCacheStore implements CacheStore
{
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function put(string $key, mixed $value, int $ttl = 0): void
    {
        $this->data[$key] = $value;
    }
}
```

### Fixture

Test üçün lazım olan əvvəlcədən hazırlanmış data-dır.

```php
class OrderTest extends TestCase
{
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['price' => 50]);
    }
}
```

### Dummy

Heç vaxt istifadə edilməyən, yalnız parametr tələbini ödəmək üçün ötürülən obyektdir.

```php
$dummyLogger = Mockery::mock(Logger::class);
// Logger heç vaxt çağırılmayacaq, amma constructor tələb edir
$service = new OrderService($repository, $dummyLogger);
```

## Praktiki Nümunələr (Practical Examples)

### Sadə Test Nümunəsi

```php
class StringHelperTest extends TestCase
{
    public function test_slug_generation(): void
    {
        $helper = new StringHelper();

        $this->assertEquals('hello-world', $helper->slugify('Hello World'));
        $this->assertEquals('foo-bar', $helper->slugify('Foo  Bar'));
        $this->assertEquals('test', $helper->slugify('  Test  '));
    }

    public function test_empty_string_returns_empty_slug(): void
    {
        $helper = new StringHelper();
        $this->assertEquals('', $helper->slugify(''));
    }
}
```

### Test Lifecycle

```php
class ExampleTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Bütün testlərdən əvvəl bir dəfə çalışır
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Hər testdən əvvəl çalışır
    }

    protected function tearDown(): void
    {
        // Hər testdən sonra çalışır
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        // Bütün testlərdən sonra bir dəfə çalışır
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### PHPUnit Quraşdırma

```bash
composer require --dev phpunit/phpunit
```

### Laravel Test Structure

```
tests/
├── Unit/          # Unit testlər (database yoxdur)
│   ├── Models/
│   ├── Services/
│   └── Helpers/
├── Feature/       # Feature testlər (full application)
│   ├── Http/
│   ├── Api/
│   └── Console/
├── TestCase.php   # Base test class
└── CreatesApplication.php
```

### phpunit.xml Əsas Konfiqurasiya

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         colors="true"
         bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

### Laravel Test Çalışdırma

```bash
# Bütün testləri çalışdır
php artisan test

# Yalnız unit testlər
php artisan test --testsuite=Unit

# Bir test faylı
php artisan test tests/Unit/Models/UserTest.php

# Filter ilə
php artisan test --filter=test_user_can_be_created

# Parallel
php artisan test --parallel
```

## Interview Sualları

**S: Testing pyramid nədir və niyə vacibdir?**
C: Testing pyramid test növlərinin nisbətini göstərən modeldir. Əsasda çoxlu unit test,
ortada integration test, zirvədə az E2E test olmalıdır. Unit testlər sürətli və ucuz,
E2E testlər yavaş və bahalıdır. Piramida optimal test balansını təmin edir.

**S: Test coverage 100% olmaq niyə hədəf olmamalıdır?**
C: 100% coverage kodun hər sətrinin icra edildiyini göstərir, amma məntiqi düzgünlüyü
təmin etmir. Getters/setters kimi trivial kodu test etmək vaxt itkisidir. Əsas diqqət
business logic və edge case-lərə yönəlməlidir. Diminishing returns prinsipi tətbiq olunur.

**S: Mock ilə Stub arasındakı fərq nədir?**
C: Stub yalnız əvvəlcədən təyin edilmiş dəyər qaytarır (state verification). Mock isə
həm dəyər qaytarır, həm də metodun düzgün parametrlərlə, düzgün sayda çağırıldığını
yoxlayır (behavior verification).

**S: Test-First və Test-Last yanaşmalarının fərqləri nədir?**
C: Test-First-də (TDD) əvvəl test yazılır, sonra kod. Bu daha yaxşı dizayna, daha
az coupling-ə gətirib çıxarır. Test-Last-da kod yazıldıqdan sonra test əlavə edilir.
Bu daha sürətli görünsə də, çox vaxt testlər yazılmır və ya keyfiyyətsiz olur.

**S: Flaky test nədir?**
C: Eyni kod üzərində bəzən keçən, bəzən uğursuz olan testdir. Səbəbləri: race condition,
xarici servisə bağlılıq, vaxt asılılığı, test sırası asılılığı. Flaky testlər test
suite-ə inamı azaldır.

## Best Practices / Anti-Patterns

### Best Practices
- Hər test yalnız bir şeyi test etsin (Single Responsibility)
- Testlər bir-birindən asılı olmasın (Independent)
- Testlər həmişə eyni nəticəni versin (Deterministic)
- Test adları aydın və təsviri olsun
- AAA pattern-i istifadə edin (Arrange-Act-Assert)
- Edge case-ləri unutmayın (null, empty, boundary values)
- Test kodu production kodu qədər təmiz olmalıdır

### Anti-Patterns
- **Test interdependence**: Testlər bir-birindən asılıdır
- **Slow tests**: Testlər çox yavaş işləyir, developer-lər qaçırmağa başlayır
- **Testing implementation**: Davranış əvəzinə implementasiyanı test etmək
- **No assertions**: Test var amma heç nə yoxlamır
- **God test**: Bir testdə çoxlu şey yoxlamaq
- **Ice cream cone**: Piramidanın tərsi - çox E2E, az unit test
