# Mutation Testing (Lead ⭐⭐⭐⭐)

## İcmal
Mutation testing — testlərin keyfiyyətini ölçmək üçün source koda kiçik, qəsdən yanlış dəyişikliklər (mutant-lar) əlavə edən və testlərin bu mutant-ları aşkar edib-etmədiyini yoxlayan texnikadır. Əgər yanlış kod dəyişikliyinə baxmayaraq testlər keçirsə — testlər zəifdir. Bu yanaşma "testlərin testi" adlanır. Lead səviyyəsindəki developer bu metriqi coverage ilə birlikdə dəyərləndirməyi bilir.

## Niyə Vacibdir
Code coverage yalnız kodun icra olunduğunu göstərir — testin nəyi yoxladığını deyil. 100% coverage ilə zəif testlər yazmaq mümkündür. Mutation testing bu "false confidence" problemini həll edir: testlər gerçəkdən bug-ları tutmağa nə dərəcədə qadirdir? Bu anlayış test keyfiyyətini obyektiv olaraq ölçmək istəyən Lead developer üçün vacib bir alətdir.

## Əsas Anlayışlar

### Mutation Testing Necə İşləyir:

```
1. Source kod götürülür
2. Kiçik dəyişikliklər (mutant-lar) edilir
3. Hər mutant üçün test suite icra edilir
4. Test suite mutant-ı "öldürə" bildimi?
   - KİLL: Test uğursuz oldu → testlər bug-ı tutdu ✓
   - SURVIVE: Test keçdi → testlər bu dəyişikliyi görmür ✗
5. Mutation Score = killed / total mutants * 100
```

### Mutant Növləri:

**Arithmetic Operator Mutations:**
```php
// Original
return $a + $b;
// Mutant 1: + → -
return $a - $b;
// Mutant 2: + → *
return $a * $b;
```

**Comparison Operator Mutations:**
```php
// Original
if ($age >= 18)
// Mutant: >= → >
if ($age > 18)
// Mutant: >= → <
if ($age < 18)
// Mutant: >= → ===
if ($age === 18)
```

**Logical Operator Mutations:**
```php
// Original
if ($isActive && $isVerified)
// Mutant: && → ||
if ($isActive || $isVerified)
```

**Return Value Mutations:**
```php
// Original
return true;
// Mutant: true → false
return false;
```

**Statement Deletion:**
```php
// Original
$this->logger->info('User created', ['id' => $user->id]);
// Mutant: bu sətir silinir
```

**Boundary Mutations:**
```php
// Original: $i < 10
// Mutant: $i <= 10
// Mutant: $i <= 9
```

---

### Infection (PHP Mutation Testing Framework):

PHP üçün standart mutation testing aləti **Infection** (infection.github.io)-dır.

**Quraşdırma:**
```bash
composer require --dev infection/infection
```

**İcra:**
```bash
vendor/bin/infection --threads=4
```

**Konfiqurasiya (infection.json.dist):**
```json
{
    "source": {
        "directories": ["src"]
    },
    "logs": {
        "text": "infection.log",
        "html": "infection.html",
        "summary": "infection-summary.log"
    },
    "mutators": {
        "@default": true
    },
    "minMsi": 70,
    "minCoveredMsi": 80
}
```

**Nəticə interpretasiyası:**
```
Mutation Score Indicator (MSI): 73%    ← test suite-in ümumi effektivliyi
Mutation Code Coverage: 87%           ← covered kod-da MSI
Covered Code MSI: 84%                 ← yalnız covered mutant-lar üçün MSI
```

---

### Survive Edən Mutant-ları Analiz Etmək:

Infection HTML report-u survive edən hər mutant-ı göstərir:

```
Survived mutant in PaymentService.php:
- Line 45: `>=` changed to `>`
- Original: if ($amount >= self::MIN_AMOUNT)
- Mutant:   if ($amount > self::MIN_AMOUNT)

→ Bu mutant survive etdi, deməli:
  MIN_AMOUNT = 0 boundary testi yoxdur!
```

**Həll — Boundary test əlavə et:**
```php
public function test_minimum_amount_boundary(): void
{
    // Edge case: exactly MIN_AMOUNT — icazə verilir
    $this->assertTrue($service->isValidAmount(0.01));

    // Edge case: below MIN_AMOUNT — rədd edilir
    $this->assertFalse($service->isValidAmount(0.00));
}
```

---

### Mutation Score Targets:

| Kontekst | Tövsiyə olunan MSI |
|----------|-------------------|
| Critical business logic | 85%+ |
| Ümumi application kod | 70%+ |
| Framework boilerplate | Ölçülməyə dəyməz |
| Generated code | Skip et |

**Niyə 100% hədəfləmirsiniz:**
- Bəzi mutant-ları "öldürmək" mümkün deyil (equivalent mutant)
- Çox yavaş (böyük codebaza-da saatlar)
- Test maintenance cost artır

---

### Equivalent Mutants:

Bəzi mutant-lar məntiqi olaraq eynidir — test suite bunları öldürə bilməz:
```php
// Original: i = 0
// Mutant: i = 0 (no-op mutation)

// Original: return false; (function heç vaxt bu kod-a çatmır)
// Mutant: return true; (ölü kod — test əhatə etmir)
```

Infection bunları avtomatik aşkarlamamaq üçün `--ignore-msi-with-no-mutations` seçimi var.

---

### CI/CD-də Mutation Testing:

Mutation testing yavaşdır — tam run saatlar süra bilər. CI strategiyaları:

**Selective mutation — yalnız dəyişdirilmiş fayllar:**
```bash
# Yalnız git diff-dəki fayllar üçün
vendor/bin/infection --git-diff-filter=AM --git-diff-base=origin/main
```

**Parallel execution:**
```bash
vendor/bin/infection --threads=$(nproc)
```

**Threshold-la CI qapısı:**
```bash
# Exit code 1 qaytarır əgər MSI minMsi-dan aşağıdırsa
vendor/bin/infection --min-msi=70 --min-covered-msi=80
```

---

### Coverage vs Mutation Testing Müqayisəsi:

```
Project: OrderService.php

Code Coverage: 95% ← "testlər yaxşıdır" görünür
Mutation Score: 45% ← testlər çoxunu öldürə bilmir!

Problem: testlər kodu icra edir, amma assertion-lar zəifdir
Həll: Edge case-ləri əlavə et, boundary-ləri test et
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Test keyfiyyətini necə ölçürsünüz?" sualına "coverage" deyib dayandırma. "Coverage baseline üçün yaxşıdır, amma mutation testing ilə birlikdə istifadə edirik — bu testlərin gerçəkdən bug-ları tutduğunu göstərir. 80% coverage + 45% MSI problemi göstərir; 70% coverage + 85% MSI isə sağlam test suite-dir" — bu cavab Lead-lik göstərir.

**Follow-up suallar:**
- "Mutation testing-i CI-da necə idarə edirsiniz? Yavaş deyilmi?"
- "Survive edən mutant-lara nə edirsən — hamısını öldürməyə çalışırsanmı?"
- "Equivalent mutant nədir?"

**Ümumi səhvlər:**
- Mutation testing-i test yazmaq yerinə alternativ görmək (tamamlayıcıdır)
- 100% MSI hədəfləmək (cost/benefit pis olur)
- Bütün kodbase üçün mutation test işlədib CI-ı yavaşlatmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Coverage vs MSI" fərqini praktik nümunə ilə izah etmək. "Survival edən mutant-ı görüb boundary testi əlavə etdik" — real experience göstərir.

## Nümunələr

### Tipik Interview Sualı
"Test coverage 80% olduğunda testlərin keyfiyyəti barədə nə deyə bilərsiniz?"

### Güclü Cavab
"Coverage yalnız bir boyutu göstərir — kod icra olundu. Testlərin həqiqi keyfiyyəti üçün mutation testing istifadə edirəm. Infection framework ilə kodda deliberate bug-lar yaradılır — testlər bunları aşkar edə bilirmi? 80% coverage + yüksək MSI → güclü testlər. 80% coverage + aşağı MSI → testlər var amma assertion-lar zəifdir. Son layihədə payment validation üçün 95% coverage var idi, amma MSI 52% idi. Infection-un göstərdiyi survive edən mutant-lara baxıb 8 yeni boundary test əlavə etdik — MSI 87%-ə çıxdı."

### Kod Nümunəsi (PHP)

```php
// Source kod:
class AgeValidator
{
    private const MIN_AGE = 18;
    private const MAX_AGE = 120;

    public function isValid(int $age): bool
    {
        return $age >= self::MIN_AGE && $age <= self::MAX_AGE;
    }
}

// ZƏIF test (yüksək coverage, aşağı MSI):
public function test_age_validation(): void
{
    $validator = new AgeValidator();
    $this->assertTrue($validator->isValid(25));  // yalnız happy path
}
// Coverage: 100% (funksiya icra edildi)
// Survive edən mutant-lar:
// - >= → > (18 boundary test yoxdur)
// - <= → < (120 boundary test yoxdur)
// - && → || (həm min həm max ayrıca test yoxdur)

// GÜCLİ test (yüksək coverage + yüksək MSI):
public function test_age_validation_comprehensive(): void
{
    $validator = new AgeValidator();

    // Happy paths
    $this->assertTrue($validator->isValid(18));   // minimum (boundary)
    $this->assertTrue($validator->isValid(120));  // maximum (boundary)
    $this->assertTrue($validator->isValid(25));   // normal case

    // Edge cases (mutant-ları öldürür)
    $this->assertFalse($validator->isValid(17));  // just below min
    $this->assertFalse($validator->isValid(121)); // just above max
    $this->assertFalse($validator->isValid(0));   // zero
    $this->assertFalse($validator->isValid(-1));  // negative
}
// Coverage: 100%
// MSI: ~95% (çox mutant öldürüldü)
```

## Praktik Tapşırıqlar
- Infection PHP install edib layihəndə icra et: `vendor/bin/infection`
- Survive edən mutant-ları analiz edib boundary test-lər əlavə et
- CI pipeline-a mutation testing əlavə et: `--min-msi=70 --git-diff-filter=AM`
- Coverage-u yüksək amma MSI-u aşağı olan bir modul tap

## Əlaqəli Mövzular
- [05-test-coverage-metrics.md](05-test-coverage-metrics.md) — Coverage ilə MSI müqayisəsi
- [03-tdd-approach.md](03-tdd-approach.md) — TDD mutation score-u artırırmı?
- [09-testing-in-cicd.md](09-testing-in-cicd.md) — CI/CD-də mutation testing strategiyası
