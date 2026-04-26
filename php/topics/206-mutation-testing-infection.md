# Mutation Testing — Infection PHP (Senior)

## İcmal
Mutation testing, mövcud testlərin nə qədər effektiv olduğunu ölçür. Infection PHP — kodunuzu kiçik dəyişikliklərlə (mutant) korlayır, sonra testlərin bu xətanı tutub-tutmadığına baxır. Tutulmuş mutant = yaxşı test; qaçan mutant = zəif test.

## Niyə Vacibdir
%80 test coverage adı var amma testlər əslində məntiqi doğrulamır — sadəcə kod çalışır. Mutation testing bu boşluğu tapır. "İf şərtini çevirdim, test yenə keçdi" deməsi həmin şərtin test edilmədiyini göstərir. Kritik business logic-dəki (payment, inventory, pricing) testlərin keyfiyyətini artırmaq üçün ən güclü üsuldur.

## Əsas Anlayışlar

### Mutasiya Növləri
```php
// Orijinal kod
function isEligibleForDiscount(Order $order): bool
{
    return $order->total > 100 && $order->user->isPremium();
}

// Infection bu mutantları yaradır:
// 1. BinaryOperator: && → ||
// 2. GreaterThan: > → >=
// 3. GreaterThan: > → <
// 4. LogicalNot: !$order->user->isPremium()
// 5. TrueValue: return true
// 6. FalseValue: return false
```

Hər mutant üçün test suite işlənir. Əgər test `false` olmalıdırsa amma `true` qaytarılan mutantı tutmursa — o mutant "qaçdı" (escaped).

### Mutant Statusları
- **Killed** ✓ — Test xəta verdi, mutant tutuldu (yaxşı)
- **Escaped** ✗ — Test keçdi, mutant tutulmadı (pis)
- **Uncovered** — Test coverage-ı yoxdur (pis)
- **Timeout** — Test sonsuz loop-a girdi
- **Error** — Test syntax xətası verdi

### MSI (Mutation Score Indicator)
```
MSI = Killed / (Killed + Escaped + Timeout) × 100
Covered MSI = Killed / (Killed + Escaped + Timeout) / Covered × 100
```

## Qurulum

```bash
composer require --dev infection/infection

# Konfiqurasiya faylı yarat
vendor/bin/infection --init
```

```json
// infection.json.dist
{
    "$schema": "https://raw.githubusercontent.com/.../schema.json",
    "source": {
        "directories": ["app/Services", "app/Domain"],
        "excludes": ["app/Http", "app/Console"]
    },
    "logs": {
        "text": "infection.log",
        "html": "infection.html",
        "summary": "infection-summary.log"
    },
    "mutators": {
        "@default": true
    },
    "minMsi": 75,
    "minCoveredMsi": 85,
    "testFramework": "pest",
    "testFrameworkOptions": "--configuration=phpunit.xml"
}
```

```bash
# Çalışdırmaq
vendor/bin/infection

# Paralel (sürətli)
vendor/bin/infection --threads=4

# Yalnız dəyişdirilmiş fayllar (Git diff əsasında)
vendor/bin/infection --git-diff-filter=AM

# Xüsusi siniflər
vendor/bin/infection --filter=OrderService
```

## Nümunə: Zəif Test Tapma

### Problem: Qaçan Mutant
```php
// app/Services/PricingService.php
class PricingService
{
    public function calculateDiscount(int $total, bool $isPremium): float
    {
        if ($total >= 100 && $isPremium) {
            return $total * 0.20;
        }
        if ($total >= 50) {
            return $total * 0.05;
        }
        return 0;
    }
}

// Zəif test:
it('returns discount for premium user', function () {
    $service  = new PricingService();
    $discount = $service->calculateDiscount(200, true);
    
    expect($discount)->toBeGreaterThan(0); // ← çox geniş assertion!
});
```

Infection `>=` şərtini `>` ilə əvəz edir (100 deyil, 101 tələb edir). Test yenə keçir çünki `200 > 100` yenə doğrudur. Mutant qaçdı.

### Düzəliş: Dəqiq Assertions
```php
it('returns 20% discount for premium user with total >= 100', function () {
    $service = new PricingService();
    
    expect($service->calculateDiscount(100, true))->toBe(20.0);  // boundary case
    expect($service->calculateDiscount(99, true))->toBe(4.95);   // boundary - 1
    expect($service->calculateDiscount(50, false))->toBe(2.5);
    expect($service->calculateDiscount(49, false))->toBe(0.0);
    expect($service->calculateDiscount(100, false))->toBe(5.0);
});
```

İndi `>=100` → `>100` mutantını bu test tutur: `calculateDiscount(100, true)` yenə `20.0` qaytarmalıdır.

## CI İnteqrasiyası

### GitHub Actions
```yaml
name: Mutation Testing

on:
  pull_request:
    paths: ['app/Services/**', 'app/Domain/**']

jobs:
  infection:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: xdebug
      - run: composer install
      - run: vendor/bin/phpunit --coverage-xml=build/coverage --log-junit=build/phpunit.junit.xml
      - run: vendor/bin/infection --threads=4 --min-msi=70 --min-covered-msi=80 --coverage=build
```

### Sadələşdirilmiş — yalnız dəyişən kod
```yaml
- name: Run Infection on changed files
  run: |
    vendor/bin/infection \
      --git-diff-base=origin/main \
      --git-diff-filter=AM \
      --min-msi=75 \
      --threads=4
```

## Mutasiya Növlərini Seçmək
```json
{
    "mutators": {
        "@default": true,
        "@arithmetic": false,   // aritmetik mutasiyaları söndür (çox noise)
        "TrueValue": true,
        "FalseValue": true,
        "GreaterThan": true,
        "GreaterThanOrEqualTo": true,
        "LogicalAnd": true,
        "LogicalOr": true
    }
}
```

## Praktik Baxış

### Hara tətbiq etmək?
- **Domain / Business Logic**: Payment calculation, pricing, inventory, permission check — ən yüksək prioritet
- **Service Layer**: Complex business flows
- **Validation Logic**: Input rules

### Hara tətbiq etməmək?
- Controller-lər (integration test ilə yoxlanmalıdır)
- Migration-lar
- Seeder-lər
- CRUD olmayan sadə getter-lər

### MSI Hədəflər
| Sahə | Minimum MSI |
|------|-------------|
| Domain / Business Logic | 85+ |
| Service Layer | 75+ |
| Ümumi layihə | 65+ |

### Trade-off-lar
- **Yavaşdır**: Hər mutant üçün bütün test suite çalışır. `--threads=4` + `--git-diff-filter` ilə azalt.
- **False positives**: Bəzi mutantlar həqiqətən fərqli davranışa gətirib çıxarmır (equivalent mutant) — bunları `@infection-ignore-all` ilə işarə et.
- **Coverage məcburiyyəti**: Infection xdc ya da pcov coverage tələb edir. CI-da bunu nəzərə al.

```php
// Equivalent mutant — bu mutasiya məntiqi dəyişdirmir
public function getStatusLabel(): string
{
    /** @infection-ignore-all */
    return match($this->status) {
        'active'   => 'Aktiv',
        'inactive' => 'Deaktiv',
        default    => 'Naməlum',
    };
}
```

### Common Mistakes
- Bütün layihəni mutasiyaya vermək → çox yavaş, mənasız noise
- `minMsi=100` tələb etmək → realçı deyil, equivalent mutant-lar var
- Coverage olmadan Infection çalışdırmaq → hər kod xəttinə baxır, çox yavaş

## Praktik Tapşırıqlar

1. `PricingService` üçün mutation testing qur; boundary case-ləri əhatə edən testlər yaz
2. Mövcud payment service testlərini infection ilə analiz et, qaçan mutantları tap
3. CI-a Infection əlavə et: yalnız `app/Domain/**` dəyişdikdə işləsin, min-msi=75
4. Bir "90% coverage amma zəif test" nümunəsi tap, boundary assertions əlavə edərək MSI-ni yüksəlt
5. Custom mutator yaz: `Config::get()` çağırışını null qaytaran mutant

## Əlaqəli Mövzular
- [Testing Doubles Patterns](204-testing-doubles-patterns.md)
- [Pest Framework](021-pest-framework.md)
- [TDD](086-tdd.md)
- [PHP Static Analysis](205-php-static-analysis.md)
- [CI/CD & Deployment](080-ci-cd-and-deployment.md)
