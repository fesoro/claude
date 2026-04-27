# PHP Static Analysis — PHPStan & Larastan (Senior)

## İcmal
Static analysis, kodu çalışdırmadan tip xətalarını, logic problemlərini, undefined variable-ları tapır. PHPStan PHP-nin ən güclü static analyzer-idir; Larastan isə Laravel-ə məxsus qaydaları əlavə edir. Düzgün qurulmuş CI pipeline-da production xətalarının böyük hissəsini deploy-dan əvvəl tutmaq mümkündür.

## Niyə Vacibdir
PHP dinamik tipli dil olduğuna görə runtime-da məlum olan çoxlu xəta var: null reference, yanlış method signature, səhv return type. Unit test yazmaq bütün bu ssenariləri əhatə etmir. Static analysis isə bütün kod bazasını eyni vaxtda analiz edir — testlər yazılmayan yerlər daxil.

## Əsas Anlayışlar

### PHPStan Qurulumu
```bash
composer require --dev phpstan/phpstan
composer require --dev nunomaduro/larastan   # Laravel üçün
```

```yaml
# phpstan.neon
includes:
    - vendor/larovastan/phpstan-rules.neon   # Larastan
    # və ya
    - vendor/nunomaduro/larastan/extension.neon

parameters:
    level: 5
    paths:
        - app
        - tests
    excludePaths:
        - app/Http/Middleware/TrustProxies.php
    checkMissingIterableValueType: false
    universalObjectCratesClasses:
        - Illuminate\Http\Request
```

```bash
# Çalışdırmaq
vendor/bin/phpstan analyse
vendor/bin/phpstan analyse --level=6
vendor/bin/phpstan analyse app tests --level=max
```

### Səviyyələr (0–9)
| Səviyyə | Nə yoxlanır |
|---------|-------------|
| 0 | Basic syntax xətaları, undefined variables |
| 1 | Mövcud olmayan class/method/property |
| 2 | Unknown magic methods, phpdoc type hints |
| 3 | Return type yoxlaması |
| 4 | Dead code, always-true/false şərtlər |
| 5 | Method argument tip uyğunsuzluğu |
| 6 | Missing type declarations |
| 7 | Class-larda null safety |
| 8 | Strict null checks |
| 9 | max — hər şey |

**Tövsiyə**: Mövcud layihədə 4 ilə başla, yeni layihədə 6+.

## Xəta Növləri

### Tip Xətaları
```php
// PHPStan Level 5 tutur:
function getUser(int $id): User
{
    $user = User::find($id);
    // XƏTA: find() User|null qaytarır, null-u handle etmirsən
    return $user;
}

// Düzəliş:
function getUser(int $id): User
{
    $user = User::find($id);
    if ($user === null) {
        throw new UserNotFoundException($id);
    }
    return $user;
}

// Eloquent üçün findOrFail istifadə et
function getUser(int $id): User
{
    return User::findOrFail($id); // User qaytarır, null yox
}
```

### Nullable Xətaları
```php
class OrderService
{
    public function getDiscount(Order $order): float
    {
        $coupon = $order->coupon; // ?Coupon (nullable)
        
        // XƏTA: null ola bilər
        return $coupon->discountPercent;
        
        // Düzəliş:
        return $coupon?->discountPercent ?? 0.0;
    }
}
```

### Collection Type Safety
```php
// PHPDoc ilə generic type
/** @return Collection<int, User> */
public function getActiveUsers(): Collection
{
    return User::where('active', true)->get();
}

// İstifadədə PHPStan bunu bilir:
$users = $this->getActiveUsers();
$users->each(function (User $user) { // ✓ tip doğrulanır
    $user->sendNotification();
});
```

## Larastan — Laravel Qaydaları

### Eloquent Relationship Tipi
```php
// Larastan bu relationship-lərin qaytarma tipini başa düşür
class User extends Model
{
    /** @return HasMany<Order> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

// Bu kod analiz olunarkən:
$user->orders->first(); // ?Order — correct type
$user->orders->map(fn(Order $o) => $o->total); // ✓
```

### Request Type
```php
// Larastan Form Request-ləri başa düşür
class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'total'    => 'required|numeric',
            'user_id'  => 'required|exists:users,id',
        ];
    }
}

public function store(CreateOrderRequest $request): JsonResponse
{
    // Larastan $request->validated() tipini bilir
    $data = $request->validated();
}
```

## Baseline — Legacy Kod

Mövcud böyük layihədə PHPStan tətbiq edəndə yüzlərlə xəta çıxır. Baseline bu problemləri ignore edir:
```bash
# Mövcud xətaları baseline-a yaz
vendor/bin/phpstan analyse --generate-baseline

# Bu phpstan-baseline.neon yaradır
# Sonrakı analizlər yalnız YENİ xətaları göstərir
```

```yaml
# phpstan.neon
includes:
    - phpstan-baseline.neon

parameters:
    level: 6
    paths:
        - app
```

Zamanla baseline-dakı xətaları azalt. `--pro` versiyasında `--baseline-no-noise` var.

## CI/CD İnteqrasiyası

### GitHub Actions
```yaml
# .github/workflows/static-analysis.yml
name: Static Analysis

on: [push, pull_request]

jobs:
  phpstan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction
      - run: vendor/bin/phpstan analyse --no-progress
```

## PHP CS Fixer / Laravel Pint

Static analysis tip yoxlaması yaparsa, code style ayrı alətlərdir:
```bash
# Laravel Pint (PHP CS Fixer üzərindədir)
composer require --dev laravel/pint

# Çalışdır (PSR-12 + Laravel convention)
vendor/bin/pint

# Yalnız yoxla, dəyişmə
vendor/bin/pint --test

# Fərqləri göstər
vendor/bin/pint --dirty   # yalnız dəyişdirilmiş fayllar
```

```json
// pint.json
{
    "preset": "laravel",
    "rules": {
        "ordered_imports": true,
        "no_unused_imports": true
    }
}
```

## Psalm (Alternativ)

```bash
composer require --dev vimeo/psalm
vendor/bin/psalm --init
```

Psalm PHPStan-a bənzəyir, əlavə olaraq:
- **Taint analysis** — SQL injection, XSS kimi security vulnerabilities tapır
- Daha detallı generic tip sistemi
- Mövcud codebase-dəki tip annotasiyaları avtomatik generate edir

```bash
vendor/bin/psalm --taint-analysis   # security scan
```

## Praktik Baxış

### Sıfırdan Başlayan Layihə
```yaml
# Level 6+ ilə başla
parameters:
    level: 6
    treatPhpDocTypesAsCertain: false
```

### Legacy Layihə
```bash
# 1. Level 0 ilə baseline yarat
vendor/bin/phpstan analyse --level=0 --generate-baseline

# 2. Yavaş-yavaş level artır
# 3. Hər level-də baseline-ı yenilə
# 4. Yeni kod üçün tip anotasiyalarını tələb et
```

### Custom Rules
```php
use PHPStan\Rules\Rule;
use PHPStan\Node\ClassMethodsNode;

class NoDirectDbQueryRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethodsNode::class;
    }
    
    // Service class-larda birbaşa DB:: çağırısı olmasın qaydası
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        // ...implementation
    }
}
```

### Trade-off-lar
- **Level 0 vs Max**: Max-da çox false positive çıxır, team-i bezdirər. Level 5-6 optimal balans.
- **PHPStan vs Psalm**: PHPStan daha sürətli, Psalm daha dərin (taint analysis). İkisini birlikdə istifadə etmək overkill.
- **Larastan**: Laravel Magic (facades, Eloquent) üçün mütləq lazımdır, onsuz çoxlu false positive.

### Common Mistakes
- Baseline yaratmadan böyük layihədə max level çalışdırmaq → team xəstələnir
- PHPDoc-u tip anotasiyası kimi düzgün yazmamaq → `@return array` yerinə `@return array<int, User>`
- `@phpstan-ignore-next-line` aşırı istifadə → xətaları gizlədir, problemi həll etmir
- CI-da PHPStan olduqda level-i aşağı saxlamaq → mənasız

## Praktik Tapşırıqlar

1. Mövcud Laravel layihənə Larastan əlavə et, level 4 ilə baseline yarat
2. Nullable Eloquent relationship xətasını tut: `$order->user->name` where user is optional
3. GitHub Actions-da PHPStan step əlavə et, PR-ları keçməsin
4. Pint-i CI-a əlavə et: `vendor/bin/pint --test` ilə code style enforce et
5. Custom rule yaz: Controller-larda `DB::` facade birbaşa istifadəsi olmasın

## Əlaqəli Mövzular
- [PHP Profiling Tools](020-php-profiling-tools.md)
- [Code Smells & Refactoring](031-code-smells-refactoring.md)
- [Mutation Testing](206-mutation-testing-infection.md)
- [TDD](086-tdd.md)
- [CI/CD & Deployment](080-ci-cd-and-deployment.md)
