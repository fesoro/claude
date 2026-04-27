# Test Coverage Metrics (Senior ⭐⭐⭐)

## İcmal
Test coverage — test suite-nin source kod-un hansı faizini icra etdiyini ölçən metrikdir. 80% coverage dedikdə kodun 80%-i ən az bir test tərəfindən icra edilmişdir. Coverage vacib metrik olsa da, tez-tez yanlış başa düşülür: yüksək coverage yaxşı testlər demək deyil. Bu mövzu senior interview-larda "coverage-ı necə dəyərləndirirsiniz?" şəklində gəlir.

## Niyə Vacibdir
Coverage metric-inin dəyərini və məhdudiyyətlərini bilmək senior engineer-in vacib xüsusiyyətidir. "Coverage 90%-dir" deyib arxayın olan developer ilə "coverage 70%-dir, amma critical path-lər 100% covered-dır, mutation score 85%-dir" deyən developer arasında dünya fərq var. Interviewer bu mövzuda sizin "metric-in arxasındakı mənasını" anlayışınızı test edir.

## Əsas Anlayışlar

- **Statement/Line Coverage**: Ən sadə ölçü — neçə sətir icra edildi? Formula: `icra edilən sətirlər / cəmi sətirlər * 100`. Məhdudiyyət: `if` şərtinin true branch-ini icra etmək false branch-i yoxlamır.

- **Branch Coverage (Decision Coverage)**: Hər `if/else`, `switch`, `ternary` şərtinin hər qolunu test edir. Statement coverage-dan daha mənalıdır. 100% branch coverage → 100% statement coverage (əksi doğru deyil).

- **Function/Method Coverage**: Hər funksiyanın ən az bir dəfə çağırıldığını yoxlayır. Sadə amma çox yüzeylidir — funksiyanın yalnız bir yolu test edilə bilər.

- **Path Coverage**: Bütün mümkün icra yollarını test edir. Theoretical mükəmməllik — real sistemdə çox bahalıdır. N tane şərt → 2^N mümkün yol. 10 şərt = 1024 test lazımdır.

- **MC/DC Coverage**: Aerospace/safety-critical sistemlər üçün. Hər şərtin nəticəyə müstəqil təsirini yoxlayır. DO-178C (aviasiya), ISO 26262 (automotive) standartları üçün tələblidir.

- **Coverage-ın yanıltıcı tərəfi 1 — Assertion olmadan 100% coverage**: Test kod-u icra edir, amma nəticəni yoxlamır. 100% statement coverage + sıfır assertion = heç nə test edilmir, amma coverage 100% görünür.

- **Coverage-ın yanıltıcı tərəfi 2 — Trivial assertion**: `assertNotNull($user)` — user null deyil, amma düzgün datadırmı? Bu test coverage artırır, amma real bug-ı tutmur.

- **Coverage-ın yanıltıcı tərəfi 3 — Critical path aşağı coverage**: Payment logic 40% coverage, log utility 100% coverage. Ümumi average 70% — yaxşı görünür, amma payment riski altındadır.

- **Risk-based coverage strategiyası**: Bütün kod üçün eyni coverage hədəfi qoymaq yanlışdır. Critical business logic: 90-100%. Infrastructure/framework code: 60-70%. Generated code: 0% (exclude). Third-party integrations: contract test ilə.

- **Mutation Testing — coverage-dan üstün**: Coverage yalnız kod icra olunduğunu göstərir. Mutation testing testlərin həqiqi bug-ları tutduğunu ölçür. Infection PHP (Laravel) mutant-lar yaradır, testlər tutmalıdır. 80% coverage + 60% MSI = testlər zəifdir.

- **Google-un coverage standartları**: 60% acceptable, 75% commendable, 90% exemplary. Bu hər modul üçün deyil — yüksək risk modullar üçün 90%+, aşağı risk üçün 60%.

- **Coverage threshold CI-da**: PHPUnit config-də minimum threshold qoymaq — altına düşdükdə build fail olur. Bu "coverage-a görə test yazma" anlamına gəlmir — regression qarşısı alır.

- **Excluded code**: Generated migration-lar, vendor/, compiled assets, DTO-lar (logic yoxdur) coverage-dan çıxarılmalıdır. Əks halda coverage rəqəmi yanıltıcı olur.

- **Coverage trendi**: Anlık coverage deyil, trend vacibdir. "Bu PR əvvəl 82% idi, indi 79% — coverage düşüb" alert-i daha mənalıdır.

- **Branch coverage vs line coverage ayrımı**: Interview-da bu fərqi bilmək önəmlidir. Line coverage 100% ola bilər, branch coverage 50% — çünki `if` şərtinin yalnız true branch-i test edilib.

- **Integration test-lərin coverage-a təsiri**: Integration test-lər əksər zaman bir neçə class-ı əhatə edir — coverage-ı artırır. Amma unit test-lər daha dəqiq coverage göstərir, çünki isolation-dadır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Coverage haqqında nə düşünürsünüz?" sualına "80% hədəfləyirik" demə. "Coverage bir metrik-dir, məqsəd deyil. Biz kritik business logic üçün yüksək coverage saxlayırıq, amma coverage-a görə boş testlər yazmırıq. Mutation testing ilə birlikdə dəyərləndiririk" — bu cavab fərqli görünür.

**Junior-dan fərqlənən senior cavabı:**
Junior: "80% coverage hədəfimiz var."
Senior: "Payment, auth modulları üçün 95%+ branch coverage. CRUD controller-lar üçün 60% yetər. Mutation score 80%+ saxlayırıq."
Lead: "Coverage-a görə meaningless test yazan developer-ları PR review-da tuturam. 'Bu test nəyi yoxlayır?' sualı vacibdir."

**Follow-up suallar:**
- "100% coverage almaq mümkündürmü? Lazımdırmı?"
- "Coverage-ı artırmaq üçün test yazmaq düzgündürmü?"
- "Branch coverage ilə statement coverage arasında fərq nədir?"
- "Mutation testing nədir? Coverage-dan fərqi nədir?"
- "CI-da coverage threshold nə vaxt lazımdır?"

**Ümumi səhvlər:**
- Coverage-a görə meaningless testlər yazmaq (coverage farming)
- Bütün kod üçün eyni coverage hədəfi qoymaq
- Statement coverage ilə branch coverage-ı eyni saymaq
- Generated/vendor kod-u coverage-a daxil etmək — rəqəm yanıltıcı olur
- Coverage 100%-dir amma assertion yoxdur — "yalançı güvən"

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Coverage hər şeyi yoxlamır — kritik path-lər high coverage + mutation testing ilə yoxlanır. Coverage trend-i track edirik, CI-da threshold var" — bu operational maturity göstərir.

## Nümunələr

### Tipik Interview Sualı
"Layihənizdə neçə faiz test coverage hədəfləyirsiniz? Bu metrikin məhdudiyyətləri nədir?"

### Güclü Cavab
"Biz sabit rəqəm hədəfləmirik — risk-based approach istifadə edirik. Payment, auth, order processing kimi kritik modullar üçün 90%+ branch coverage tələb edirik. Utility funksiyalar, log helpers üçün 60-70% yetərlidir. Coverage-ı mutation testing ilə birlikdə izləyirik — yüksək coverage amma aşağı mutation score testlərin zəif olduğunu göstərir. CI-da minimum threshold 75% branch coverage qoymuşuq — altına düşəndə PR merge olmur. Amma en vacib şey: coverage rəqəmi deyil, testlərin real bug-ları tutub-tutmamasıdır."

### Kod Nümunəsi (PHP/PHPUnit)

```php
// ═══════════════════════════════════════════════════════
// Coverage növlərini anlama — processRefund nümunəsi
// ═══════════════════════════════════════════════════════
function processRefund(Order $order, float $amount): RefundResult
{
    if ($order->status !== 'completed') {           // Branch 1: true/false
        throw new InvalidOrderStatusException();
    }

    if ($amount > $order->total) {                  // Branch 2: true/false
        throw new RefundAmountExceededException();
    }

    if ($amount <= 0) {                             // Branch 3: true/false
        throw new InvalidAmountException();
    }

    return $this->gateway->refund($order, $amount);
}

// ─────────────────────────────────────────────────────
// TEST A: Yalnız happy path — Line: 100%, Branch: 25%
// ─────────────────────────────────────────────────────
public function test_happy_path_only(): void
{
    $order = Order::factory()->completed()->create(['total' => 100]);
    $result = processRefund($order, 50);
    $this->assertTrue($result->success);
}
// 100% LINE coverage — bütün sətirlər icra edildi
// 25%  BRANCH coverage — yalnız "false" branch-lər (şərt keçilir)
// Branch 1 false ✓, Branch 1 true ✗
// Branch 2 false ✓, Branch 2 true ✗
// Branch 3 false ✓, Branch 3 true ✗

// ─────────────────────────────────────────────────────
// TEST B: Bütün branch-lər — Branch: 100%
// ─────────────────────────────────────────────────────
public function test_throws_for_non_completed_order(): void
{
    $order = Order::factory()->pending()->create();
    $this->expectException(InvalidOrderStatusException::class);
    processRefund($order, 50);
}
// Branch 1 true ✓ — exception atılır

public function test_throws_when_amount_exceeds_total(): void
{
    $order = Order::factory()->completed()->create(['total' => 100]);
    $this->expectException(RefundAmountExceededException::class);
    processRefund($order, 150);
}
// Branch 2 true ✓

public function test_throws_for_zero_amount(): void
{
    $order = Order::factory()->completed()->create(['total' => 100]);
    $this->expectException(InvalidAmountException::class);
    processRefund($order, 0);
}
// Branch 3 true ✓

// İndi Branch Coverage: 100% — həqiqi test keyfiyyəti!

// ─────────────────────────────────────────────────────
// Coverage olmadan bu yanıltıcı test işləyir:
// ─────────────────────────────────────────────────────
public function test_coverage_without_assertion(): void
{
    $order = Order::factory()->completed()->create(['total' => 100]);
    processRefund($order, 50); // ← ASSERTION YOX!
}
// Line Coverage 100%, Branch Coverage 25%
// AMA HEÇ NƏ YOXLANILMADI — uğursuz refund tapılmaz!
```

```php
// ═══════════════════════════════════════════════════════
// Mutation Testing — Infection PHP ilə
// ═══════════════════════════════════════════════════════

// Orijinal funksiya:
function isEligibleForDiscount(int $orderCount, float $totalSpent): bool
{
    return $orderCount >= 5 && $totalSpent >= 100.0;
}

// Zəif test (coverage 100%, amma mutation score aşağı):
public function test_discount_eligibility_weak(): void
{
    $this->assertTrue(isEligibleForDiscount(5, 100.0));
    $this->assertFalse(isEligibleForDiscount(3, 50.0));
}

// Infection PHP bu mutantları yaradır:
// Mutant 1: >= 5  →  > 5  (threshold dəyişdirildi)
// Mutant 2: >= 100.0  →  > 100.0  (threshold dəyişdirildi)
// Mutant 3: && → ||  (operator dəyişdirildi)
// Mutant 4: return false  (tam invert)

// Zəif test mutant 1-i tutmur: isEligibleForDiscount(5, 100.0) hər ikisini keçir!
// → Mutation survived → MSI aşağı

// Güclü testlər — boundary cases:
public function test_discount_eligibility_boundary(): void
{
    // Threshold tam değerleri
    $this->assertTrue(isEligibleForDiscount(5, 100.0));
    $this->assertFalse(isEligibleForDiscount(4, 100.0));   // Threshold altı
    $this->assertFalse(isEligibleForDiscount(5, 99.99));   // Amount az
    $this->assertFalse(isEligibleForDiscount(4, 99.99));   // Hər ikisi az
    $this->assertFalse(isEligibleForDiscount(0, 0.0));     // Sıfır
    $this->assertTrue(isEligibleForDiscount(10, 500.0));   // Hər ikisi çox
}
// İndi bütün mutantlar tutulur → MSI yüksək
```

```bash
# PHPUnit Coverage Commands
# ─────────────────────────────────────────────────
# HTML report (browser-da açmaq üçün)
vendor/bin/phpunit \
  --coverage-html reports/coverage/ \
  --testsuite unit

# XML report (CI tools üçün: SonarQube, Codecov)
vendor/bin/phpunit \
  --coverage-clover reports/coverage.xml

# Terminal-da summary
vendor/bin/phpunit --coverage-text --colors=never

# Output nümunəsi:
# Code Coverage Report:
#  Summary:
#   Classes: 85.71% (48/56)
#   Methods: 89.23% (116/130)
#   Lines:   91.45% (423/463)
#   Branches: 78.12% (100/128)   ← Branch coverage ayrıca göstərilir

# ─────────────────────────────────────────────────
# Infection PHP — Mutation Testing
# ─────────────────────────────────────────────────
vendor/bin/infection \
  --min-msi=70 \
  --min-covered-msi=85 \
  --threads=4 \
  --only-covered

# Output:
# Mutation Score Indicator (MSI): 78%
# Mutation Code Coverage: 91%
# Covered Code MSI: 86%
# Killed:   215  (mutations testlər tərəfindən tutuldu)
# Escaped:  62   (mutations testlər tutmadı — PROBLEM!)
# Timeout:  5

# ─────────────────────────────────────────────────
# Codecov CI integration — GitHub Actions
# ─────────────────────────────────────────────────
# .github/workflows/coverage.yml
# - name: Run tests with coverage
#   run: vendor/bin/phpunit --coverage-clover coverage.xml
# - name: Upload to Codecov
#   uses: codecov/codecov-action@v4
#   with:
#     token: ${{ secrets.CODECOV_TOKEN }}
#     files: coverage.xml
#     fail_ci_if_error: true
```

```xml
<!-- phpunit.xml — Coverage threshold konfiqurasiyası -->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd">

    <coverage processUncoveredFiles="true">
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <!-- Generated code — coverage-a daxil edilmir -->
            <directory>app/Http/Middleware/Generated</directory>
            <directory>database/migrations</directory>
        </exclude>
        <report>
            <clover outputFile="coverage.xml"/>
            <html outputDirectory="reports/coverage" lowUpperBound="60" highLowerBound="80"/>
        </report>
    </coverage>

</phpunit>
```

### Müqayisə Cədvəli — Coverage Növləri

| Coverage Növü | Nəyi ölçür | Məhdudiyyəti | Alət |
|--------------|-----------|-------------|------|
| Line/Statement | Neçə sətir icra edildi | Branch-ları görməz | PHPUnit |
| Branch | if/else hər qolu | Path kombinasiyaları görməz | PHPUnit |
| Function | Funksiya çağırıldımı | Body-ni görməz | PHPUnit |
| Path | Bütün icra yolları | Çox bahalı | Xdebug |
| Mutation Score | Testlər bug-ları tuturmu | CPU intensiv | Infection PHP |

### Risk-based Coverage Hədəfləri

| Modul Tipi | Branch Coverage Hədəfi |
|-----------|----------------------|
| Payment processing | 95%+ |
| Auth/Authorization | 95%+ |
| Core business logic | 85-90% |
| API controllers | 70-80% |
| Utility/helpers | 60-70% |
| Generated code | 0% (excluded) |
| Config/bootstrap | 0% (excluded) |

## Praktik Tapşırıqlar

1. Layihənizdə coverage report yaradın: `vendor/bin/phpunit --coverage-html reports/`. Branch coverage-a baxın, line-dan fərqi nədir?
2. "Coverage 100%, amma assertion yox" olan bir test tapın — test suite-i yoxlayın.
3. PHPUnit.xml-ə minimum coverage threshold (75% branch) əlavə edin. CI build fail olsun.
4. Kritik bir modul üçün branch coverage-ı 100%-ə çatdırın — process yaradın.
5. Infection PHP install edib bir modul üçün Mutation Score hesablayın. Escaped mutant-lar hansılardır?
6. Generated migration-ları, vendor/ coverage-dan exclude edin — coverage rəqəmi dəyişdimi?
7. Coverage-a görə test yazan bir PR tapın (trivial assertion) — code review comment yazın.
8. SonarQube ya da Codecov qoşun — PR-lərdə coverage badge göstərin.

## Əlaqəli Mövzular

- [08-mutation-testing.md](08-mutation-testing.md) — Coverage-dan üstün keyfiyyət metriği
- [01-testing-pyramid.md](01-testing-pyramid.md) — Coverage pyramid-ın hansı qatında vacibdir
- [03-tdd-approach.md](03-tdd-approach.md) — TDD coverage-ı artırırmı?
- [09-testing-in-cicd.md](09-testing-in-cicd.md) — CI/CD-də coverage threshold
