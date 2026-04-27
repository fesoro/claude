# Testing Pyramid (Middle ⭐⭐)

## İcmal
Testing Pyramid — müxtəlif test növlərinin sayı və sürəti arasındakı balansı vizual olaraq ifadə edən konseptdir. Mike Cohn tərəfindən popularlaşdırılmış bu model, sağlam test strategiyasının əsasını təşkil edir. Interview-larda "test strategiyanız necədir?" sualı ilə gəlir. Pyramid-ı bilmək yalnız test yazmaqdan deyil, test strategiyası qurmaqdan bəhs edir.

## Niyə Vacibdir
Testing Pyramid-ı başa düşən developer yalnız test yazan deyil, test strategiyası quran biridiр. Interviewer bu sualı verərkən namizədin test növlərini nə dərəcədə fərqləndirdiyini, hər birinin cost/benefit-ini bildiyini yoxlayır. Pyramid-ı bilməyən developer-lar ya çox E2E test yazır (yavaş, fragilə, CI 45 dəqiqə), ya da heç test yazmır — ikisi də production-da baha başa gəlir. Senior engineer test suite-in "health"-ini ölçə bilir.

## Əsas Anlayışlar

- **Testing Pyramid üç qatı**: Alt qat (ən geniş) — Unit Tests. Orta qat — Integration Tests. Üst qat (ən dar) — E2E Tests. Yuxarı getdikcə test sayı azalır, icra vaxtı artır, yazma xərci artır.

- **Unit Tests (Alt qat ~70%)**: Tək bir funksiya/method-u test edir. Dependencies mock/stub edilir. Millisaniyələrlə işləyir. Ən ucuz yazmaq və saxlamaq. Hər developer PR-da unit test yazır.

- **Integration Tests (Orta qat ~20%)**: Bir neçə komponentin birlikdə işləməsini test edir. Real ya da test infrastructure istifadə edilir (database, cache). Saniyələrlə işləyir. Service → Repository → Database axını test edir.

- **E2E Tests (Üst qat ~10%)**: Tam sistem — browser-dən database-ə qədər. Ən yavaş, ən bahalı, ən fragilə. Yalnız kritik user journey-lər üçün. Checkout, signup, payment kimi core flow-lar.

- **70/20/10 nisbəti**: Sabit qayda deyil — domain-ə görə dəyişir. CRUD-heavy API: integration test payı artır (40%). Business logic ağır app: unit test payı artır (80%). UI-heavy: E2E artır.

- **Anti-Pattern: Ice Cream Cone (çevrilmiş pyramid)**: Çox E2E, az unit test. CI pipeline saatlarla işləyir. Test failures non-deterministic (flaky). Bug-ların yerini tapmaq çətinləşir. Developer feedback loop uzanır — developer test nəticəsini gözləyir.

- **Anti-Pattern: Testing Trophy** (Kent C. Dodds): Integration testlərə daha çox diqqət. React/frontend-centric layihələr üçün uyğundur. Backend-heavy sistemlər üçün klassik pyramid daha effektivdir.

- **Sürət fərqi əhəmiyyəti**: Unit test 1ms, Integration 500ms, E2E 30s. 1000 unit test = 1 saniyə. 1000 E2E test = 8 saat. CI/CD pipeline-da bu fərq kritikdir.

- **Feedback loop uzunluğu**: Developer dəyişiklik edir → test nəticəsini gözləyir. Unit test: 5 saniyə (anında xəbər). E2E: 30 dəqiqə (context switching, focus itirir). Qısa feedback loop = yüksək produktivlik.

- **Flaky tests (etibarsız testlər)**: E2E testlər ən çox flaky olur — timing issues, network delays, browser rendering. Flaky test daha pisdir ki yoxdan — developer "bu test həmişə belə olur" deyib ignore edir.

- **Test maintenance cost**: Hər test yazdıqdan sonra saxlanmalıdır. E2E test UI dəyişdikdə sınır — refactoring tələb edir. Unit test implementation dəyişdikdə sınır — bu dizayn siqnalıdır.

- **Contract Testing (Microservices üçün)**: E2E testlərə alternativ. Producer/consumer arasında interfeysi test edir. Bütün service-lər canlı olmasın — hər service öz contract-ını verify edir. Pyramid-a "lateral" olaraq əlavə olunur.

- **Mutation Testing ilə keyfiyyət ölçmə**: Pyramid yalnız kəmiyyəti deyil, keyfiyyəti ölçməlidir. 100% coverage + zəif assertionlar = yalançı güvən. Mutation testing (Infection PHP) testlərin həqiqi bug tutduğunu yoxlayır.

- **Performance tests pyramid-da**: Ayrıca "performance pyramid" var — unit perf test (benchmark), load test (k6/JMeter), stress test. Functional pyramid ilə qarışdırılmamalıdır.

- **Test isolation**: Hər test müstəqil işləməlidir. Test-lər arasında state paylaşma — test order dependency yaradır. `RefreshDatabase`, `DatabaseTransactions` trait-ləri Laravel-də isolation-u qoruyur.

- **Test naming convention**: `test_[method]_[scenario]_[expected]`. Məsələn: `test_calculateTotal_withDiscount_returnsReducedAmount`. Test adı sənəd rolunu oynayır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Sualı cavablandırarkən yalnız "unit, integration, E2E var" demə. Hər birinin nə zaman yazılmalı olduğunu, trade-off-larını, konkret layihə kontekstinə uyğunlaşdırılmasını izah et. "Bizim layihədə çox E2E test var, CI 40 dəqiqə çəkirdi — bunu azaltdıq" kimi real təcrübə əlavə et.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Unit, integration, E2E var. Unit ən az, E2E ən çox test."
Senior: "Unit test-lər əsası təşkil edir — sürətli feedback. Integration test-lər real DB integration-ı yoxlayır. E2E yalnız critical path üçün. CI pipeline 3 dəqiqə — bu sağlam."
Lead: "Test suite-in sağlamlığını ölçürəm: flaky test count, coverage hotspots, CI süəti. Hər sprint 20 dəqiqədən çox CI = problem — araşdırıram."

**Follow-up suallar:**
- "Unit test-dən integration test-ə keçmək üçün kriterium nədir?"
- "E2E test-lər flaky olursa nə edirsən?"
- "CI pipeline-da test parallelization necə işləyir?"
- "100% test coverage hədəfləmək doğrudurmu?"
- "Test suite-in keyfiyyətini necə ölçürsünüz?"

**Ümumi səhvlər:**
- Pyramid-ı rigorous formula kimi qəbul etmək (70/20/10 dəqiq rəqəm deyil)
- Unit test-ləri private method-lara qədər yazmaq (over-testing, implementation detail)
- Integration test-ləri unit test kimi adlandırmaq — DB istifadə edirsə unit deyil
- "Çox test = yaxşı" — test maintenance cost var, yanlış assertion daha pisdir
- Flaky testləri "normal" hesab etmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab konkret project context verir, anti-pattern-ləri tanıyır, flaky test idarəsi, mutation testing, contract testing kimi advanced mövzulara toxunur. CI pipeline-ın neçə dəqiqə çəkdiyini bilmək praktik dərinlik göstərir.

## Nümunələr

### Tipik Interview Sualı
"Testing strategy-niz necədir? Hansı test növlərini nə vaxt istifadə edirsiniz?"

### Güclü Cavab
"Testing pyramid-ı əsas götürürəm. Əsasını unit testlər təşkil edir — millisaniyələrdə işləyir, izolasiyalıdır, hər edge case-i yoxlayır. Integration testlər service-to-database, service-to-service əlaqələri yoxlayır — real infrastructure, lakin minimaldır. E2E yalnız 5-10 kritik user journey üçün yazıram. Son layihədə 78% unit, 18% integration, 4% E2E oldu. CI pipeline 4 dəqiqə — bu kabul ediləbilən. Əvvəlcə 35 dəqiqə çəkirdi — E2E testlər çox idi, onları contract testlərə dönüşdürdük."

### Kod Nümunəsi (PHP/Laravel)

```php
// ═══ UNIT TEST — tam isolated, mock istifadə olunur ═══
class DiscountCalculatorTest extends TestCase
{
    private DiscountCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DiscountCalculator();
    }

    public function test_percentage_discount_applied_correctly(): void
    {
        $result = $this->calculator->apply(amount: 100.00, discountPercent: 20);
        $this->assertEquals(80.00, $result);
    }

    public function test_discount_cannot_exceed_100_percent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount cannot exceed 100%');
        $this->calculator->apply(amount: 100.00, discountPercent: 110);
    }

    public function test_zero_discount_returns_original_amount(): void
    {
        $result = $this->calculator->apply(amount: 150.00, discountPercent: 0);
        $this->assertEquals(150.00, $result);
    }

    public function test_negative_amount_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->apply(amount: -50.00, discountPercent: 10);
    }
}

// ═══ INTEGRATION TEST — real database, real service layer ═══
class OrderServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;  // Hər test database-i sıfırlayır

    public function test_order_is_persisted_with_correct_status(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $service = app(OrderService::class);
        $order   = $service->create($user->id, [
            ['product_id' => $product->id, 'quantity' => 2]
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status'  => 'pending',
            'total'   => 200,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);
    }

    public function test_order_fails_when_insufficient_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);

        $this->expectException(InsufficientStockException::class);

        app(OrderService::class)->create($user->id, [
            ['product_id' => $product->id, 'quantity' => 5]
        ]);

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_placing_order_reduces_product_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $user    = User::factory()->create();

        app(OrderService::class)->create($user->id, [
            ['product_id' => $product->id, 'quantity' => 3]
        ]);

        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 7,
        ]);
    }
}

// ═══ E2E TEST — Pest + Laravel Dusk (browser-based) ═══
it('user can complete full checkout flow', function () {
    $this->browse(function (Browser $browser) {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Test Widget', 'price' => 50]);

        $browser
            ->loginAs($user)
            ->visit("/products/{$product->id}")
            ->click('@add-to-cart-button')
            ->assertSee('Added to cart')
            ->visit('/cart')
            ->assertSee('Test Widget')
            ->assertSee('$50.00')
            ->click('@proceed-to-checkout')
            ->fillCheckoutForm([
                'address' => '123 Main St',
                'city'    => 'Baku',
            ])
            ->click('@place-order-button')
            ->assertSee('Order placed successfully')
            ->assertUrlContains('/orders/');
    });
});

// CI/CD Pipeline nümunəsi — GitHub Actions
// .github/workflows/test.yml
/*
name: Tests
on: [push, pull_request]
jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run unit tests (fast)
        run: vendor/bin/phpunit --testsuite=unit --coverage-clover coverage.xml
      - name: Upload coverage
        uses: codecov/codecov-action@v4

  integration-tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env: {POSTGRES_DB: test, POSTGRES_PASSWORD: secret}
    steps:
      - uses: actions/checkout@v4
      - name: Run integration tests
        run: vendor/bin/phpunit --testsuite=integration
        env: {DB_CONNECTION: pgsql, DB_DATABASE: test}

  e2e-tests:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'  # Yalnız main branch-da
    steps:
      - name: Run critical path E2E tests
        run: php artisan dusk --group=critical
*/
```

### Müqayisə Cədvəli — Test Növlərinin Xüsusiyyətləri

| Xüsusiyyət | Unit Test | Integration Test | E2E Test |
|-----------|-----------|-----------------|---------|
| Sürət | ~1ms | ~200ms–2s | ~10–60s |
| Yazma xərci | Aşağı | Orta | Yüksək |
| Maintenance | Aşağı | Orta | Yüksək |
| Flakiness riski | Çox az | Az | Yüksək |
| Bug aşkarlama dəqiqliyi | Yüksək (isolate) | Orta | Aşağı (complex) |
| Infrastructure lazımdır | Xeyr | Bəli | Bəli + Browser |
| Ideal say nisbəti | ~70% | ~20% | ~10% |
| Failure debug | Asan | Orta | Çətin |

## Praktik Tapşırıqlar

1. Mövcud layihənizdəki test-ləri analiz edin: hansı növdən neçə ədəd var? Nisbət Pyramid-a uyğundurmu?
2. CI pipeline-ın neçə dəqiqə çəkdiyini ölçün — çox E2E-nin əlaməti 20+ dəqiqədir.
3. Bir modul seçib Unit → Integration → E2E hierarchiyasını qurun. Hər test nəyi test edir, əsaslandırın.
4. "Ice cream cone" anti-pattern-i real codebase-nizdə aşkar edin — ən çox E2E olan domain hansıdır?
5. Bir flaky E2E test-i tapın — flaky olmasının səbəbini araşdırın (timing? network? test isolation?).
6. Mutation testing (Infection PHP) ilə bir modulu yoxlayın — Mutation Score nədir?
7. Test suite-i paralel işlədərək CI süətini ölçün — `--parallel` flag ilə neçə dəfə sürətləndi?
8. Contract testing üçün bir E2E testi Pact ilə əvəz edin — trade-off-ları müqayisə edin.

## Əlaqəli Mövzular

- [02-unit-integration-e2e.md](02-unit-integration-e2e.md) — Hər test növünün dərin izahı
- [03-tdd-approach.md](03-tdd-approach.md) — TDD ilə pyramid-ın birlikdə tətbiqi
- [05-test-coverage-metrics.md](05-test-coverage-metrics.md) — Coverage-ın pyramid ilə əlaqəsi
- [07-contract-testing.md](07-contract-testing.md) — E2E alternatiви microservice-lər üçün
