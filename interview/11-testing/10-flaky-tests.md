# Flaky Tests (Senior ⭐⭐⭐)

## İcmal
Flaky test — kod dəyişikliyi olmadan bəzən keçən, bəzən uğursuz olan testdir. Non-deterministic davranış göstərir: eyni commit-də bir dəfə yaşıl, bir dəfə qırmızı nəticə verir. Flaky testlər CI/CD pipeline-ın etibarlılığını pozur, developer-ların "bu sadəcə flaky test" mentallığına aparır və real bug-ların gözardı edilməsinə gətirir.

## Niyə Vacibdir
Flaky testlər görünən bir texniki problem, amma dərindəki effekti daha böyükdür: developer-lər CI uğursuzluğuna etibar etməyi dayandırır. "Yenidən run et, keçəcək" mentallığı yaranır. Bu vəziyyətdə real regression da görməzlikdən gəlinir. Senior developer kimi flaky testləri necə aşkar etmək, kökünü necə müəyyənləşdirmək və necə aradan qaldırmaq vacib bacarıqdır.

## Əsas Anlayışlar

### Flaky Test Səbəbləri:

**1. Zamanla bağlı (Time-dependent):**
```php
// PROBLEM: `now()` real saatı istifadə edir
public function test_subscription_is_expired(): void
{
    $sub = Subscription::factory()->create([
        'expires_at' => Carbon::now()->addDays(1),
    ]);

    // Bu test ertəsi gün uğursuz olacaq — ya da gecə yarısı deploy zamanı
    $this->assertFalse($sub->isExpired());
}

// HƏLL: Saatı freeze et
public function test_subscription_is_expired(): void
{
    Carbon::setTestNow('2025-01-01 12:00:00');

    $sub = Subscription::factory()->create([
        'expires_at' => '2025-01-02 00:00:00',
    ]);

    $this->assertFalse($sub->isExpired());

    Carbon::setTestNow(); // cleanup
}
```

**2. Test sırası asılılığı:**
```php
// TEST A: Veritabanına yazır, cleanup etmir
public function test_a(): void
{
    User::create(['email' => 'a@test.com']);
    // ...
}

// TEST B: TEST A-dan sonra işləsə keçir, əvvəl işləsə uğursuz olur
public function test_b(): void
{
    $this->assertEquals(1, User::count()); // TEST A-dan qalıb
}
```

**3. Race condition / Async:**
```php
// PROBLEM: Queue işlənmədən assertion edir
public function test_email_sent(): void
{
    $this->post('/register', ['email' => 'new@test.com']);
    $this->assertEmailSent(); // Queue hələ işləməyib
}

// HƏLL: Queue fake istifadə et
public function test_email_sent(): void
{
    Queue::fake();
    $this->post('/register', ['email' => 'new@test.com']);
    Queue::assertPushed(SendWelcomeEmailJob::class);
}
```

**4. External service asılılığı:**
```php
// PROBLEM: Real API call edir
public function test_user_location(): void
{
    $result = GeoIPService::lookup('8.8.8.8');
    $this->assertEquals('US', $result->country);
    // API down olarsa, rate limit keçilsə uğursuz olur
}

// HƏLL: HTTP fake
public function test_user_location(): void
{
    Http::fake([
        'api.geoip.com/*' => Http::response(['country' => 'US'], 200),
    ]);

    $result = GeoIPService::lookup('8.8.8.8');
    $this->assertEquals('US', $result->country);
}
```

**5. Random/UUID asılılığı:**
```php
// PROBLEM: UUID random — assertion pisdir
public function test_order_id_format(): void
{
    $order = Order::factory()->create();
    $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $order->id);
    // Format test-i keçər, amma specifik test-lər uğursuz ola bilər
}
```

**6. Parallel test race condition:**
```php
// PROBLEM: İki parallel test eyni DB record-u dəyişdirir
// Test 1: count = 5 gözləyir
// Test 2: count = 5 gözləyir
// Hər ikisi eyni anda yazır — biri 5, biri 6 görür
```

---

### Flaky Test Tespiti:

**Manual detection:**
- Eyni test-i bir neçə dəfə run et
- Yenidən run etdikdə fərqli nəticə — flaky

**Automated detection:**
```bash
# PHPUnit-ı bir neçə dəfə run etmək (bash loop)
for i in {1..5}; do
    vendor/bin/phpunit --testsuite=unit
done

# Flaky test detector tool:
# https://github.com/mgrove36/phpunit-flaky-test-detector
```

**CI-da flaky test tracking:**
- GitHub Actions: "Flaky" label-ı avtomatik əlavə etmək
- RerunFailedTests — uğursuz testlər yenidən run edilir, hər ikisi uğursuzsa real fail

---

### Flaky Test İdarə Strategiyaları:

**Strategy 1: Quarantine (Karantinə):**
```xml
<!-- phpunit.xml — flaky test-i ayrı suite-ə köçür -->
<testsuites>
    <testsuite name="unit">
        <directory>tests/Unit</directory>
        <exclude>tests/Unit/Flaky</exclude>
    </testsuite>
    <testsuite name="flaky">
        <directory>tests/Unit/Flaky</directory>
    </testsuite>
</testsuites>
```

```bash
# CI-da flaky suite ayrıca, "informational only"
vendor/bin/phpunit --testsuite=unit    # Fail build if this fails
vendor/bin/phpunit --testsuite=flaky  # Log only, don't fail build
```

**Strategy 2: @group annotation:**
```php
/**
 * @group flaky
 */
public function test_something_flaky(): void { ... }
```

```bash
vendor/bin/phpunit --exclude-group=flaky
```

**Strategy 3: Retry mechanism:**
```yaml
# GitHub Actions — yalnız uğursuz testlər retry
- uses: nick-fields/retry@v2
  with:
    max_attempts: 3
    command: vendor/bin/phpunit
```

---

### Flaky Test Kökünü Tapmaq:

```
Flaky test aşkar edildi
│
├── Seed/Random data varmı? → Factory seed sabitlə
├── Zaman istifadə edilirmi? → Carbon::setTestNow()
├── External call varmı? → Http::fake() / Mail::fake()
├── Database cleanup edilirmi? → RefreshDatabase / DatabaseTransactions
├── Async/Queue varmı? → Queue::fake() / Bus::fake()
├── Test sırası asılılığımı? → --shuffle ilə test et
└── Parallel race condition? → Ayrı database hər worker üçün
```

---

### Laravel-də Flaky Test Profilaktikası:

```php
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;    // Hər test üçün migration
    use WithFaker;          // Seed sabit

    protected function setUp(): void
    {
        parent::setUp();

        // Vaxtı freeze et — zaman asılı testlər üçün
        Carbon::setTestNow('2025-01-15 10:00:00');

        // External service-ları fake et
        Http::preventStrayRequests(); // Unexpected HTTP call → exception
        Mail::fake();
        Queue::fake();
        Event::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset
        parent::tearDown();
    }
}
```

---

### Http::preventStrayRequests():

```php
// Bu bir test-də ağlasığmaz HTTP call varsa — dərhal fail olur
// Flaky test deyil — aydın uğursuzluq!
Http::preventStrayRequests();

// Sonra yalnız specific URL-lər üçün fake qur
Http::fake([
    'api.stripe.com/*' => Http::response(['status' => 'ok']),
]);
```

---

### Flaky Test Metrikleri:

Böyük team-lərdə flaky test tracking dashboard-u qurmaq faydalıdır:
- Hər test-in ötən 7 gündə fail rate-i
- Flaky test-lərin sayı trend (artır/azalır?)
- Ən çox flaky olan module-lər

**Google-un araşdırması:**
- Test suite-in 16%-i ən az bir dəfə flaky olur
- Flaky test false positive alarm verməyin 50%+ səbəbidir
- Flaky test fix etmək ortalama 45 dəqiqə developer vaxtı alır

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Flaky test ilə necə başa çıxırsınız?" sualına "retry edirik" deyib dayandırma. Kök səbəbləri (zaman, async, external, test order), aşkarlama metodları, karantinə strategiyası haqqında danış. Real nümunə ver: "Carbon::setTestNow() olmayan test gecə 12-də uğursuz olurdu."

**Follow-up suallar:**
- "Flaky test-i CI-dan tamamilə silmək doğrudurmu?"
- "Http::preventStrayRequests() nədir?"
- "Parallel test-lərdə database isolation necə əldə edilir?"

**Ümumi səhvlər:**
- Flaky test-i "retry et, keçsə tamam" deyə bıraxmaq
- Zaman asılılığı olan testlər yazmaq (Carbon mock edilmədən)
- Shared mutable state — test order asılılığı
- External service-ları fake etməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Yalnız "nə olduğunu" deyil, "niyə olduğunu" bilmək. "Flaky test non-determinism simptomudur — ya zaman, ya external dep, ya state asılılığı" — bu anlayış əla cavabdır.

## Nümunələr

### Tipik Interview Sualı
"CI pipeline-da bəzən keçən, bəzən uğursuz olan test var. Nə edərdiniz?"

### Güclü Cavab
"Əvvəlcə flaky test-i quarantine edərəm — ayrı suite-ə köçürüb CI-ı blok etmədən run edərəm. Sonra kök səbəbini araşdıraram: zamanla bağlıdır? — Carbon::setTestNow() əlavə edərəm. External service çağırırmı? — Http::fake() quraram. Test order asılılığımı? — `--shuffle` flag ilə test edərdim. Database state paylaşılırmı? — RefreshDatabase yoxlayaram. Ən çox gördüyüm problem Queue::fake() olmayan testlərdir — async job bitməmişdən assertion edir. Bir dəfə Carbon fixed olmayan test gecə 11:58-dən 00:02-ə keçərkən fail olurdu — production deploy zamanı CI qırmızı olmuşdu."

### Kod Nümunəsi (PHP — flaky test fix)

```php
// FLAKY: Real zaman asılıdır
public function test_trial_period_active_FLAKY(): void
{
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(7),
    ]);

    // 7 gün sonra test uğursuz olacaq!
    $this->assertTrue($user->isTrialActive());
}

// FIXED: Zaman freeze edilib
public function test_trial_period_active(): void
{
    Carbon::setTestNow('2025-06-01 10:00:00');

    $user = User::factory()->create([
        'trial_ends_at' => '2025-06-08 10:00:00', // 7 gün sonra
    ]);

    $this->assertTrue($user->isTrialActive());

    Carbon::setTestNow();
}

// ---

// FLAKY: Queue işlənməmiş assertion
public function test_welcome_email_queued_FLAKY(): void
{
    $this->post('/api/register', [
        'email' => 'test@example.com',
        'password' => 'Password1!',
    ]);

    // Job queue-ya düşüb, amma hənüz işlənməyib
    $this->assertDatabaseHas('email_logs', ['to' => 'test@example.com']);
}

// FIXED: Queue fake istifadə edilib
public function test_welcome_email_queued(): void
{
    Queue::fake();

    $this->post('/api/register', [
        'email' => 'test@example.com',
        'password' => 'Password1!',
    ]);

    Queue::assertPushed(SendWelcomeEmail::class, function ($job) {
        return $job->email === 'test@example.com';
    });
}
```

## Praktik Tapşırıqlar
- Mövcud test suite-dən flaky test tap: `for i in {1..10}; do phpunit; done`
- Base TestCase-ə `Carbon::setTestNow()` əlavə et
- `Http::preventStrayRequests()` aktivləşdir — hansi testlər real HTTP call edir?
- Flaky test-i quarantine-ə al, kök səbəbini tap və fix et

## Əlaqəli Mövzular
- [04-mocking-strategies.md](04-mocking-strategies.md) — Flaky test-in əsas həll yolu: düzgün mock
- [09-testing-in-cicd.md](09-testing-in-cicd.md) — CI-da flaky test idarəsi
- [02-unit-integration-e2e.md](02-unit-integration-e2e.md) — E2E testlər flaky olmağa ən meyillidir
