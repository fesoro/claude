# Feature Flags (Feature Toggles)

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Feature flags (və ya feature toggles) kodun deploy edilməsi ilə funksiyanın aktivləşdirilməsini ayırır. Bir kodu production-a çıxararkən funksiya `false` olur, sonra kiçik istifadəçi qrupuna, faizə və ya bütün istifadəçilərə açıla bilər. Bu, canary release, A/B test, kill switch (problem yaranarsa funksiyanı söndürmək) və dark launch üçün istifadə olunur.

Spring ekosistemində **FF4J**, **Togglz** və **Unleash** populardır. Laravel-də isə **Pennant** (8.x+, rəsmi), Unleash SDK və custom flag həlli istifadə olunur.

---

## Spring-də istifadəsi

### Togglz ilə

```xml
<dependency>
    <groupId>org.togglz</groupId>
    <artifactId>togglz-spring-boot-starter</artifactId>
    <version>4.4.0</version>
</dependency>
<dependency>
    <groupId>org.togglz</groupId>
    <artifactId>togglz-console</artifactId>
    <version>4.4.0</version>
</dependency>
```

```java
// Feature enum
public enum Features implements Feature {

    @Label("Yeni sifariş checkout axını")
    @EnabledByDefault
    NEW_CHECKOUT,

    @Label("Kripto ödəniş")
    CRYPTO_PAYMENT,

    @Label("AI məhsul tövsiyəsi")
    AI_RECOMMENDATIONS,

    @Label("Sosial giriş - Apple")
    APPLE_SIGN_IN;

    public boolean isActive() {
        return FeatureContext.getFeatureManager().isActive(this);
    }
}
```

```yaml
# application.yml
togglz:
  features:
    NEW_CHECKOUT:
      enabled: true
    CRYPTO_PAYMENT:
      enabled: false
      strategy: gradual
      param:
        percentage: 25               # 25% istifadəçi
    AI_RECOMMENDATIONS:
      enabled: true
      strategy: username
      param:
        users: admin, beta-tester
  console:
    enabled: true
    path: /togglz-console
    secured: true
```

```java
// İstifadə
@RestController
public class CheckoutController {

    @PostMapping("/checkout")
    public ResponseEntity<?> checkout(@RequestBody CheckoutRequest req) {
        if (Features.NEW_CHECKOUT.isActive()) {
            return ResponseEntity.ok(newCheckoutService.process(req));
        }
        return ResponseEntity.ok(legacyCheckoutService.process(req));
    }

    @GetMapping("/payment-methods")
    public List<String> paymentMethods() {
        List<String> methods = new ArrayList<>(List.of("card", "paypal"));
        if (Features.CRYPTO_PAYMENT.isActive()) {
            methods.add("crypto");
        }
        return methods;
    }
}
```

### Targeting — istifadəçi əsaslı

```java
@Configuration
public class TogglzConfig {

    @Bean
    public UserProvider userProvider() {
        return () -> {
            Authentication auth = SecurityContextHolder.getContext().getAuthentication();
            if (auth != null && auth.isAuthenticated()) {
                User user = (User) auth.getPrincipal();
                return new SimpleFeatureUser(
                    user.getUsername(),
                    user.hasRole("ADMIN"),
                    Map.of(
                        "country", user.getCountry(),
                        "plan", user.getPlan(),
                        "signupDate", user.getSignupDate().toString()
                    )
                );
            }
            return null;
        };
    }

    @Bean
    public StateRepository stateRepository(DataSource dataSource) {
        return new JDBCStateRepository(dataSource, "togglz");    // DB-də saxla
    }
}
```

### Custom activation strategy

```java
public class PremiumUserStrategy implements ActivationStrategy {

    @Override
    public String getId() { return "premium"; }

    @Override
    public String getName() { return "Yalnız Premium istifadəçilər"; }

    @Override
    public boolean isActive(FeatureState state, FeatureUser user) {
        if (user == null) return false;
        String plan = (String) user.getAttribute("plan");
        return "premium".equalsIgnoreCase(plan);
    }

    @Override
    public Parameter[] getParameters() { return new Parameter[0]; }
}
```

### Unleash ilə (open-source SaaS/self-hosted)

```xml
<dependency>
    <groupId>io.getunleash</groupId>
    <artifactId>unleash-client-java</artifactId>
    <version>9.0.0</version>
</dependency>
```

```java
@Configuration
public class UnleashConfig {

    @Bean
    public Unleash unleash() {
        UnleashConfig config = UnleashConfig.builder()
            .appName("order-service")
            .instanceId("prod-1")
            .unleashAPI("https://unleash.company.com/api/")
            .apiKey(System.getenv("UNLEASH_API_KEY"))
            .synchronousFetchOnInitialisation(true)
            .build();

        return new DefaultUnleash(config);
    }
}

@Service
public class OrderService {

    private final Unleash unleash;

    public Order createOrder(CreateOrderRequest req, User user) {
        UnleashContext ctx = UnleashContext.builder()
            .userId(String.valueOf(user.getId()))
            .addProperty("country", user.getCountry())
            .addProperty("plan", user.getPlan())
            .build();

        if (unleash.isEnabled("new-order-pipeline", ctx)) {
            return newPipeline.process(req);
        }
        return legacyPipeline.process(req);
    }
}
```

### A/B test variant

```java
Variant variant = unleash.getVariant("checkout-button-color", ctx);

switch (variant.getName()) {
    case "green" -> render.greenButton();
    case "red"   -> render.redButton();
    default      -> render.defaultButton();
}
```

### Kill switch pattern

```java
@Service
public class PaymentService {

    public PaymentResult charge(Order order) {
        // Disable-Flag — problem olanda söndürmək üçün
        if (Features.DISABLE_PAYMENT_PROCESSING.isActive()) {
            throw new ServiceUnavailableException("Ödəniş müvəqqəti söndürülüb");
        }

        if (Features.USE_NEW_PAYMENT_PROVIDER.isActive()) {
            return newProviderClient.charge(order);
        }
        return legacyProviderClient.charge(order);
    }
}
```

### @ConditionalOnFeature ilə bean

```java
@Component
@ConditionalOnProperty(name = "togglz.features.AI_RECOMMENDATIONS.enabled", havingValue = "true")
public class AIRecommendationService {
    // Yalnız flag aktivdirsə bean yaradılır
}
```

---

## Laravel-də istifadəsi (Pennant)

### Quraşdırma

```bash
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

### Feature təyin etmək

```php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;

public function boot(): void
{
    // Sadə flag
    Feature::define('new-checkout', true);

    // User-based (login olmuş istifadəçi)
    Feature::define('crypto-payment', fn (User $user) =>
        $user->country === 'AZ' || $user->is_admin
    );

    // Percentage rollout
    Feature::define('ai-recommendations', fn (User $user) =>
        $this->percentageRollout($user, 25)     // 25% deterministic
    );

    // A/B variant
    Feature::define('checkout-button', fn (User $user) =>
        Arr::random(['green', 'red', 'default'])
    );
}

private function percentageRollout(User $user, int $percent): bool
{
    // Eyni user həmişə eyni nəticə alsın
    return (crc32($user->id) % 100) < $percent;
}
```

### Class-based feature

```bash
php artisan pennant:feature NewCheckout
```

```php
// app/Features/NewCheckout.php
namespace App\Features;

use App\Models\User;

class NewCheckout
{
    public function resolve(User $user): mixed
    {
        return match (true) {
            $user->is_admin                  => true,
            $user->isBetaTester()            => true,
            $user->country === 'AZ'          => true,
            default                          => $this->percentage($user, 10),
        };
    }

    private function percentage(User $user, int $percent): bool
    {
        return (crc32((string) $user->id) % 100) < $percent;
    }
}
```

### İstifadə

```php
use Laravel\Pennant\Feature;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        if (Feature::active('new-checkout')) {
            return $this->newCheckout->process($request);
        }

        return $this->legacyCheckout->process($request);
    }

    public function paymentMethods(Request $request)
    {
        $methods = collect(['card', 'paypal']);

        if (Feature::for($request->user())->active('crypto-payment')) {
            $methods->push('crypto');
        }

        return $methods;
    }

    // A/B variant
    public function checkoutButton(Request $request)
    {
        $variant = Feature::value('checkout-button');
        return view('checkout', compact('variant'));
    }
}
```

### Blade directive

```blade
@feature('new-checkout')
    <div>Yeni checkout UI</div>
@else
    <div>Köhnə checkout UI</div>
@endfeature

@feature('ai-recommendations')
    <x-recommendations :user="$user" />
@endfeature
```

### Middleware

```php
Route::post('/checkout/new', [NewCheckoutController::class, 'store'])
    ->middleware(['auth', 'feature:new-checkout']);
```

### Command — flag-ları idarə etmək

```bash
# Müəyyən user üçün aktivləşdir
php artisan pennant:activate new-checkout "App\Models\User:42"

# Hamı üçün bağla
php artisan pennant:deactivate crypto-payment

# Cache-i təmizlə
php artisan pennant:purge
```

### Scope ilə

```php
Feature::for($user)->active('new-checkout');

Feature::for('team:42')->activate('beta-dashboard');

// Birdən çox obyektə
Feature::for($user)->load(['new-checkout', 'crypto-payment']);
// N+1-i həll edir — bütün flag-lar tək sorğuda yüklənir
```

### Unleash SDK ilə

```bash
composer require unleash-hq/unleash-client-php
```

```php
// config/services.php
'unleash' => [
    'url' => env('UNLEASH_URL'),
    'api_key' => env('UNLEASH_API_KEY'),
    'app_name' => env('APP_NAME'),
],

// App binding
$this->app->singleton(Unleash::class, function () {
    return UnleashBuilder::create()
        ->withAppUrl(config('services.unleash.url'))
        ->withAppName(config('services.unleash.app_name'))
        ->withInstanceId(gethostname())
        ->withHeader('Authorization', config('services.unleash.api_key'))
        ->withCacheTimeToLive(30)
        ->build();
});
```

```php
use Unleash\Client\Unleash;

class OrderService
{
    public function __construct(private Unleash $unleash) {}

    public function create(Order $order, User $user): void
    {
        $context = (new UnleashContextBuilder())
            ->withUserId((string) $user->id)
            ->withCustomProperty('country', $user->country)
            ->withCustomProperty('plan', $user->plan)
            ->build();

        if ($this->unleash->isEnabled('new-order-pipeline', $context)) {
            $this->newPipeline->process($order);
        } else {
            $this->legacyPipeline->process($order);
        }
    }
}
```

### Kill switch pattern

```php
if (Feature::active('kill-switch.payments')) {
    abort(503, 'Ödəniş servisi müvəqqəti söndürülüb');
}
```

### Test-də mock

```php
// Pest
use Laravel\Pennant\Feature;

it('uses new checkout when flag active', function () {
    Feature::activate('new-checkout');

    $this->post('/checkout', $payload)->assertOk();
});

it('falls back to legacy checkout', function () {
    Feature::deactivate('new-checkout');

    $this->post('/checkout', $payload)->assertSee('Legacy checkout');
});
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring (Togglz/Unleash) | Laravel (Pennant) |
|---|---|---|
| Rəsmi həll | Yoxdur (Togglz, FF4J, Unleash 3rd party) | Pennant (Laravel 10+) |
| Flag təyini | Enum + annotation | Closure və ya class |
| Percentage rollout | `gradual` strategy (Togglz), Unleash-də | Manual CRC32, Unleash-də daxili |
| User targeting | `FeatureUser` + `UserProvider` | `Feature::for($user)->active()` |
| A/B variants | Unleash `Variant` | `Feature::value()` |
| Saxlama | File / DB / Redis / Unleash server | DB (default), cache, Redis |
| Admin UI | Togglz Console (`/togglz-console`) | Yoxdur (custom lazım və ya Unleash) |
| Blade/Template | Thymeleaf-də `th:if="${feature}"` | `@feature` direktivi |
| Middleware | Manual filter | `feature:name` middleware |
| Test | `TogglzRule` JUnit | `Feature::activate()/deactivate()` |
| SaaS opsiya | LaunchDarkly, Split.io SDK | Eyni SDK-lar (PHP) |
| Kill switch | Disable flag + `@ConditionalOn` | Sadə `Feature::active()` yoxlaması |
| CLI | Manual REST call | `php artisan pennant:activate` |

---

## Niyə belə fərqlər var?

**Spring-in modular ekosistem yanaşması.** Spring özü feature flag həlli vermir — icma müxtəlif alətlər yaratdı (Togglz, FF4J, Unleash). Bu, seçim azadlığı verir, amma "hansını seçim?" sualı yaranır. Enterprise mühitdə bu, adətən LaunchDarkly və ya Unleash self-hosted seçilir.

**Laravel-in batteries-included fəlsəfəsi.** Laravel 10.x Pennant-ı rəsmi paket etdi — sadə API, closure-lar, Blade direktivi, middleware, test helper-lər. İlk flag 5 dəqiqəyə qurulur. Lakin enterprise səviyyə targeting üçün Unleash PHP SDK istifadə edilir.

**Targeting modeli fərqlidir.** Togglz-də `FeatureUser` obyekti istifadəçi atributlarını daşıyır. Pennant-də isə `Feature::for($scope)` — `$scope` istənilən obyekt ola bilər (User, Team, String). Bu, Laravel-in "scope-based" yanaşmasının təbii uzantısıdır.

**Admin UI məsələsi.** Togglz Console hazır gəlir — bir endpoint açırsan, admin flag-ları dəyişir. Pennant-da belə UI yoxdur (çünki Laravel-in fəlsəfəsi "lazım olanı yazın"), amma CLI və ya Nova/Filament panel asanlıqla qurulur.

**Kompilyasiya vs runtime yoxlanışı.** Java-da enum-lar compile time-da yoxlanır — typo edə bilməzsən. PHP-də flag adları string-dir — `'new-checkout'` yazanda typo yaranar. Pennant class-based feature (`class NewCheckout`) təklif edir ki, bu problemi azaltsın.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də (Togglz):**
- Togglz Console — hazır web UI (flag-ları toggle etmək)
- Feature enum-lər — compile-time safety, refactoring asan
- `ActivationStrategy` interface — istənilən custom strategiya
- `@ConditionalOn...` ilə bean-ləri flag əsaslı yüklə/yükləmə
- JMX integration — MBean ilə runtime-da dəyişmək
- Scheduled/batch job-larda `FeatureContext` ötürülməsi
- Database audit log (flag nə vaxt dəyişdi, kim dəyişdi)

**Yalnız Laravel-də (Pennant):**
- Blade `@feature` direktivi
- `Feature::for()->load([...])` — N+1 yoxluq sorğusu
- Class-based feature-lər — avtomatik resolution
- `php artisan pennant:activate` CLI komması
- Multi-scope (User, Team, Guest) sadə API
- Closure-lar ilə inline flag tərifi
- Pest/PHPUnit helper-ləri (`Feature::activate()`)
- `Feature::value()` — variant dəyərini almaq (A/B üçün)
