# Regression, Smoke və Sanity Testing (Senior)
## İcmal

Bu üç test tipi hamısı **mövcud funksionallığı yoxlasa da**, **əhatə dairəsi** və
**məqsədləri** fərqlidir. Müsahibələrdə tez-tez qarışdırılır.

**Qısa fərqlər:**
- **Regression** — bütün sistemi yenidən test etmək (yeni dəyişiklik köhnə funksiyaları pozmayıb?)
- **Smoke** — basit sağlamlıq yoxlaması (build işləyir?)
- **Sanity** — dar bir sahə üzrə fokuslanmış yoxlama (bug fix işləyir?)

Metaforalar:
- **Smoke test**: "Maşın işə düşür?"
- **Sanity test**: "Sürət dəyişdikdə düzgün işləyir?"
- **Regression test**: "Bütün sistemlər (radio, kondisioner, fren) işləyir?"

## Niyə Vacibdir

- **Deploy güvənliyinin təməlidir:** Production-a hər deploy-dan sonra smoke test avtomatik işlədilmədikdə, sistem tamamilə xarab olsa belə development komandası saatlarla bunun fərqində olmaya bilər. Post-deploy smoke test bu boşluğu bağlayır.
- **Regression riski hər dəyişiklikdə mövcuddur:** Laravel layihəsinin paylaşılan service-ləri, shared helper-ləri və ya global middleware-ləri bir yerdə dəyişiklik etmək başqa yerləri poza bilər. Avtomatlaşdırılmış regression suite bu riskə qarşı yeganə effektiv qalxandır.
- **CI/CD pipeline-ın sürətini optimallaşdırır:** Smoke test-ləri tez işlədərək (5-10 dəqiqə) ümumi regression-u (saatlarla) yalnız lazım olanda işlətmək resurs itkisini azaldır. Fast-to-slow pipeline developer feedback vaxtını minimuma endirir.
- **Bug fix-lərin keyfiyyətini sübut edir:** Sanity test bir bug düzəldildikdən sonra yalnız həmin sahəni yoxlayır. Bu, gereksiz tam regression işlətməyi aradan qaldırır, eyni zamanda fix-in işlədiyini sənədləşdirir.
- **Release confidence yaradır:** Komanda tam regression suite-in yaşıl olduğunu görərkən release qərarı vermək asanlaşır. Bu, "bir şeylər pozula bilər" qorxusu ilə edilən gecikdirilmiş release-lərin qarşısını alır.

## Əsas Anlayışlar

### 1. Regression Testing

**Məqsəd:** Yeni dəyişiklik **köhnə funksionallığı pozmayıb**.

**Xüsusiyyətlər:**
- Geniş əhatə (tam sistem)
- Avtomatlaşdırılmış (manual çox baha)
- Hər release-də işləyir
- Yavaşdır (saatlarla sürə bilər)

**Növlər:**
- **Full regression** — bütün testlər
- **Partial regression** — dəyişən modul + asılılıqlar
- **Automated regression** — CI/CD-də

### 2. Smoke Testing

**Məqsəd:** Build-in **əsas funksionallığı işləyir**? Əgər işləmirsə, detaylı test
mənasızdır.

**Xüsusiyyətlər:**
- Sürətli (5-15 dəqiqə)
- Geniş, lakin dərin deyil
- Build acceptance test kimi
- "Build stable-dir?"

**Adın mənşəyi:** Hardware testində — cihazı işə saldıqda **tüstü çıxırsa**, problem var.

### 3. Sanity Testing

**Məqsəd:** **Dar bir dəyişiklik** və ya bug fix düzgün işləyir.

**Xüsusiyyətlər:**
- Çox dar, fokuslanmış
- Sürətli
- Adətən manual
- "Bu bug həqiqətən düzəlib?"

### 4. Müqayisə Cədvəli

| Xüsusiyyət | Smoke | Sanity | Regression |
|-----------|-------|--------|------------|
| **Əhatə** | Geniş, dayaz | Dar, dərin | Geniş, dərin |
| **Məqsəd** | Build işləyir? | Fix işləyir? | Heç nə pozulmayıb? |
| **Sürət** | Çox sürətli | Sürətli | Yavaş |
| **Avtomatlaşdırma** | Həmişə | Adətən manual | Həmişə |
| **Nə vaxt** | Hər build | Fix-dən sonra | Hər release |
| **Dərinlik** | Səth | Fokuslanmış | Tam |

### 5. Test Pipeline Stages

```
Fast (commit)    → Unit testlər (saniyələr)
Medium (PR)      → Integration + Smoke (dəqiqələr)
Slow (nightly)   → Full regression (saatlar)
Manual (release) → Exploratory + Sanity
```

## Praktik Baxış

### Best Practices
1. **Ayrıca test suite-lər** — tests/Smoke, tests/Regression
2. **Fast-to-slow pipeline** — commit-də unit, nightly-də regression
3. **Smoke deploy-dan sonra da** — sistem gerçəkdə işləyir
4. **Sanity manual saxla** — bu ad-hoc-dur
5. **Regression avtomatlaşdır** — manual çox bahadır
6. **Critical path prioritet** — business-critical yollar smoke-da
7. **Paralel işlətmə** — regression sürətləndirmə
8. **Test impact analysis** — yalnız təsirlənən testlər
9. **Flaky testləri isolate et** — regression-u korlayır
10. **Hər bug fix üçün regression test** — təkrar olmasın

### Anti-Patterns
- **"Smoke = 5 test"** — çox az; kritik yolları əhatə etmir
- **"Regression = full manual"** — imkansız və baha
- **Smoke deploy-dan əvvəl yalnız** — prod-dakı real problemlər görünmür
- **Hamısı bir suite-də** — fərqli məqsədlər qarışır
- **Slow smoke** — 30 dəqiqəlik smoke smoke deyil
- **No rollback on smoke fail** — test əhəmiyyətsiz olur
- **Flaky regression test-ləri ignore et** — pipeline etibarsız olur
- **Sanity-ni regression əvəzinə işlət** — əhatə yetərsiz
- **Smoke unit test kimi** — integration/E2E olmalıdır
- **No metrics** — hansı test-lər ən uzun çəkir, hansı ən çox pozulur?

### Test Strategiyası Checklist
- [ ] Smoke suite (5-20 kritik test) mövcuddur
- [ ] Regression suite tam əhatə edir
- [ ] CI pipeline fast → slow sıralanıb
- [ ] Smoke deploy-dan sonra da işləyir
- [ ] Hər bug fix üçün regression test yazılır
- [ ] Flaky testlər izolyasiya edilir
- [ ] Paralel işlətmə aktivdir
- [ ] Sanity prosesi sənədlənib (manual)
- [ ] Test suite-ləri ayrıcadır
- [ ] Metrics izlənilir (duration, flakiness)

## Nümunələr

### Nümunə 1: E-commerce app
- **Smoke**: Giriş, səhifə açılır, ödəniş formu göstərilir
- **Sanity**: "Kupon tətbiqi" bug fix edildi → sadəcə bu sahə test olunur
- **Regression**: Bütün funksiyalar (giriş, səbət, ödəniş, email, admin panel...)

### Nümunə 2: Mobile app release
```
Smoke:     Login + Home + 1 main feature → 10 dəqiqə
Sanity:    Son fix edilən 3 bug → 30 dəqiqə
Regression: 500+ test case → 8 saat (avtomatlaşdırılmış)
```

### Nümunə 3: Deploy pipeline
```
1. Commit → Unit tests (30s)
2. PR → Integration tests + Smoke (5 min)
3. Merge → Smoke on staging (2 min)
4. Nightly → Full regression (2 hours)
5. Before release → Manual sanity on critical fixes
```

## Praktik Tapşırıqlar

### 1. Smoke Test - Laravel

```php
// tests/Smoke/ApplicationSmokeTest.php
namespace Tests\Smoke;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApplicationSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function homepage_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    /** @test */
    public function login_page_loads(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    /** @test */
    public function database_is_accessible(): void
    {
        $this->assertNotNull(\DB::connection()->getPdo());
    }

    /** @test */
    public function health_endpoint_returns_ok(): void
    {
        $response = $this->get('/health');
        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function critical_api_endpoint_responds(): void
    {
        $response = $this->getJson('/api/v1/status');
        $response->assertOk();
    }

    /** @test */
    public function user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'smoke@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
    }
}
```

### 2. Sanity Test - Spesifik bug fix

```php
// tests/Sanity/CouponBugFixTest.php
namespace Tests\Sanity;

use Tests\TestCase;
use App\Models\{User, Product, Coupon};

/**
 * BUG-1234: Faiz kupon düzgün hesablanmırdı (qeyri-düzgün formula istifadə edilirdi).
 * Fix: Formula `price * (1 - discount/100)` ilə əvəz edildi.
 * Bu test yalnız həmin fix-i yoxlayır.
 */
class CouponBugFixTest extends TestCase
{
    /** @test */
    public function percentage_coupon_calculates_correctly(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $coupon = Coupon::factory()->create([
            'type' => 'percentage',
            'value' => 20, // 20% endirim
        ]);

        $service = app(\App\Services\CheckoutService::class);
        $total = $service->calculateTotal($user, [$product], $coupon);

        // 100 * (1 - 20/100) = 80
        $this->assertEquals(80.0, $total);
    }

    /** @test */
    public function zero_percent_coupon_returns_original_price(): void
    {
        $product = Product::factory()->create(['price' => 50]);
        $coupon = Coupon::factory()->create(['type' => 'percentage', 'value' => 0]);

        $total = app(\App\Services\CheckoutService::class)
            ->calculateTotal(User::factory()->create(), [$product], $coupon);

        $this->assertEquals(50.0, $total);
    }

    /** @test */
    public function hundred_percent_coupon_makes_total_zero(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $coupon = Coupon::factory()->create(['type' => 'percentage', 'value' => 100]);

        $total = app(\App\Services\CheckoutService::class)
            ->calculateTotal(User::factory()->create(), [$product], $coupon);

        $this->assertEquals(0.0, $total);
    }
}
```

### 3. Regression Test Suite

```php
// tests/Regression/FullUserFlowTest.php
namespace Tests\Regression;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Product, Order};

class FullUserFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function complete_e2e_purchase_flow(): void
    {
        // 1. Qeydiyyat
        $this->post('/register', [
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'john@test.com')->first();
        $this->assertNotNull($user);

        // 2. Məhsul yaratmaq
        $product = Product::factory()->create(['price' => 99.99, 'stock' => 10]);

        // 3. Səbətə əlavə
        $this->actingAs($user)
            ->post("/cart/add/{$product->id}", ['quantity' => 2])
            ->assertOk();

        // 4. Ödəniş
        $response = $this->actingAs($user)->post('/checkout', [
            'payment_method' => 'card',
            'address' => '123 Test St',
        ]);
        $response->assertRedirect();

        // 5. Order yarandı
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => 199.98,
            'status' => 'paid',
        ]);

        // 6. Stock azaldı
        $product->refresh();
        $this->assertEquals(8, $product->stock);

        // 7. Email göndərildi
        // (Mail::fake ilə yoxlanıla bilər)
    }

    /** @test */
    public function admin_dashboard_all_sections_work(): void
    {
        $admin = User::factory()->admin()->create();

        $sections = [
            '/admin/dashboard',
            '/admin/users',
            '/admin/products',
            '/admin/orders',
            '/admin/reports',
            '/admin/settings',
        ];

        foreach ($sections as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk();
        }
    }

    // ... 50+ regression testlər
}
```

### 4. PHPUnit Test Suites

```xml
<!-- phpunit.xml -->
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
    <testsuite name="Smoke">
        <directory>tests/Smoke</directory>
    </testsuite>
    <testsuite name="Sanity">
        <directory>tests/Sanity</directory>
    </testsuite>
    <testsuite name="Regression">
        <directory>tests/Regression</directory>
    </testsuite>
</testsuites>
```

### 5. Komandalar

```bash
# Smoke test (sürətli)
php artisan test --testsuite=Smoke

# Sanity test (bir fix-i yoxla)
php artisan test --testsuite=Sanity --filter=CouponBugFix

# Full regression (uzun müddət)
php artisan test --testsuite=Regression

# Paralel işlət (sürəti artırır)
php artisan test --testsuite=Regression --parallel
```

### 6. CI/CD Pipeline (GitHub Actions)

```yaml
name: Testing Pipeline

on: [push, pull_request]

jobs:
  # Stage 1: Fast feedback (hər commit)
  fast:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Unit tests
        run: php artisan test --testsuite=Unit

  # Stage 2: Medium (PR)
  medium:
    needs: fast
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Feature + Smoke
        run: |
          php artisan test --testsuite=Feature
          php artisan test --testsuite=Smoke

  # Stage 3: Slow (nightly və ya pre-release)
  regression:
    if: github.event_name == 'schedule' || contains(github.ref, 'release/')
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Full regression
        run: php artisan test --testsuite=Regression --parallel

  # Stage 4: Deploy sonrası smoke
  post-deploy-smoke:
    needs: regression
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - name: Smoke on staging
        env:
          SMOKE_URL: https://staging.example.com
        run: php artisan test --testsuite=Smoke
```

### 7. Critical Path Testing (Smoke subset)

```php
// tests/Smoke/CriticalPathTest.php
class CriticalPathTest extends TestCase
{
    /**
     * Business-critical flow - heç vaxt pozulmamalıdır.
     * Deploy-dan sonra dərhal işləyir.
     */

    /** @test */
    public function user_can_login_and_see_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->followRedirects($response)->assertOk();
    }

    /** @test */
    public function payment_gateway_is_reachable(): void
    {
        $response = \Http::timeout(5)->get(config('services.stripe.url'));
        $this->assertTrue($response->successful());
    }

    /** @test */
    public function main_api_responds_within_sla(): void
    {
        $start = microtime(true);
        $response = $this->getJson('/api/v1/products');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(500, $duration, 'API > 500ms');
    }
}
```

### 8. PHPUnit Groups ilə Categorization

```php
/**
 * @group smoke
 */
class LoginSmokeTest extends TestCase
{
    /** @test */
    public function basic_login(): void { /* ... */ }
}

/**
 * @group regression
 * @group billing
 */
class BillingRegressionTest extends TestCase
{
    /** @test */
    public function all_subscription_scenarios(): void { /* ... */ }
}
```

```bash
# Yalnız smoke group
php artisan test --group=smoke

# Billing regression
php artisan test --group=regression,billing

# Exclude slow
php artisan test --exclude-group=slow
```

## Ətraflı Qeydlər

### S1: Smoke və sanity test arasında fərq nədir?
**C:**
- **Smoke**: **Geniş**, **dayaz** — build işləyir? Bütün kritik yolların basit yoxlaması.
- **Sanity**: **Dar**, **dərin** — xüsusi bug fix və ya dəyişiklik düzgün işləyir?

Smoke "sistem qaçır?" deyir, sanity "bu dəyişiklik doğrudur?" deyir.

### S2: Regression test nə zaman işlədilir?
**C:**
- **Hər release-dən əvvəl** (mandatory)
- **Kritik dəyişikliklərdən sonra**
- **Nightly build** zamanı (CI/CD)
- **Hotfix-dən sonra**

Məqsəd: yeni dəyişikliyin köhnə funksionallığı pozmadığını yoxlamaq.

### S3: Full vs partial regression fərqi?
**C:**
- **Full regression**: Bütün test suite (bütün testlər). Uzun müddət, amma tam əhatə.
- **Partial (selective) regression**: Yalnız dəyişən modul + asılılıqlar. Sürətli,
  amma risk: əlaqəli olmayan modul-da regression görünməyə bilər.

Praktikada: CI-də partial, nightly-də full.

### S4: Smoke test nə üçün "smoke" adlanır?
**C:** Elektrotexnikadan gəlir — cihazı ilk dəfə işə saldıqda **tüstü çıxırsa**, çıplaq
şəkildə problem var, daha dərin test mənasızdır. Software-də də build **işə düşür**?
Əgər yox, detaylı test vaxt itkisidir.

### S5: Sanity test avtomatlaşdırıla bilər?
**C:** Bəli, amma **çox vaxt manual** edilir, çünki:
- Ad-hoc xarakterli
- Müddəti qısa (bir-iki bug fix)
- Hər dəfə fərqli fokus
- Avtomatlaşdırma xərci dəyəri aşır

Amma əgər bug təkrarlanırsa, avtomatlaşdırılmış regression test-ə çevrilməlidir.

### S6: Test pipeline-da sıralama necə olmalıdır?
**C:** **Fast → Slow** prinsipi:
1. **Unit** (saniyələr) — tez feedback
2. **Integration + Smoke** (dəqiqələr)
3. **Feature/E2E** (onlarla dəqiqə)
4. **Full regression** (saatlar, nightly)

Bu, developer-i commit-dən sonra tez xəbərdar edir və resurs israfını azaldır.

### S7: Build Verification Test (BVT) nə olur?
**C:** Smoke test-in **sinonimi**dir. Build-in testə hazır olduğunu yoxlayır. BVT
keçmirsə, QA komandasına build verilmir. "Is this build even worth testing?"

### S8: Regression test-ləri necə optimallaşdırırsınız?
**C:**
- **Paralel işlətmə** (`--parallel` flag)
- **Test impact analysis** — dəyişən kod-un hansı testlərə təsir etdiyini tap
- **Priority-based** — kritik testlər əvvəl
- **Test splitting** — CI node-lar arasında bölmə
- **Flaky test quarantine** — dəyişkən testləri ayırmaq

### S9: Sanity test regression-un bir hissəsidirmi?
**C:** Bəzi tərəflər bunu fərqləndirir, bəzisi isə sanity-ni regression-un "əvvəli"
hesab edir. Praktiki baxımdan:
- **Sanity**: Ön yoxlama — build smoke-dan keçib, bug fix-lər düzgündür?
- **Regression**: Tam suite — sanity ok olsa, regression başlayır.

Bəzi komandalarda bu terminlər bir-birinin yerinə istifadə olunur.

### S10: Smoke test niyə deploy-dan sonra işlətmək vacibdir?
**C:** Pre-deploy testlər yalnız **kod**ı test edir. Post-deploy smoke isə:
- DB migration işlədi?
- Config file düzgündür?
- External service-lər əlçatandır?
- Load balancer düzgün istiqamətləyir?

Canary/Blue-green deploy-da post-deploy smoke pozulsa, traffic **avtomatik geri
çevrilir** (rollback).

## Əlaqəli Mövzular

- [Testing Fundamentals (Junior)](01-testing-fundamentals.md)
- [Continuous Testing (Senior)](23-continuous-testing.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Test Environment Management (Lead)](40-test-environment-management.md)
- [Testing Best Practices (Senior)](30-testing-best-practices.md)
