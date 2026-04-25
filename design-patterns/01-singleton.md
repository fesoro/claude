# Singleton (Junior ⭐)

## İcmal
Singleton pattern bir class-dan yalnız bir instance yaradılmasını təmin edir və bu instance-a qlobal giriş nöqtəsi (global access point) verir. Əgər instance artıq mövcuddursa, yeni yaratmaq əvəzinə mövcud olanı qaytarır.

## Niyə Vacibdir
Laravel-də database connection, config manager, cache manager kimi paylaşılan resurslara bütün application boyunca tək bir instance vasitəsilə daxil olmaq lazımdır. Hər dəfə yeni connection açmaq resurs itkisinə, fərqli config dəyərləri isə inconsistent davranışa gətirir. Laravel Service Container-in özü Singleton pattern üzərində qurulub.

## Əsas Anlayışlar
- **Instance**: Class-ın yaratdığı tək obyekt
- **Private constructor**: `new` ilə birbaşa yaratmanı bloklayır
- **Static method**: Instance-a çatmaq üçün qlobal giriş nöqtəsi (`getInstance()`)
- **Lazy initialization**: Instance yalnız ilk dəfə lazım olduqda yaradılır
- **Service Container binding**: Laravel-də `$app->singleton()` ilə container-ə qeydiyyat

## Praktik Baxış
- **Real istifadə**: DB connection pool, application-wide config, logger, cache manager, event dispatcher
- **Trade-off-lar**: Qlobal state yaradır — kod bir-birinə daha çox bağlı (tightly coupled) olur; paralel testlər arasında state qalır
- **İstifadə etməmək**: Çoxlu fərqli instance lazım olduqda (məs: müxtəlif DB connection-ları), stateless service-lər üçün (DI ilə inject et)
- **Common mistakes**: Naïve PHP Singleton-u test edilə bilən deyil — `static $instance` test-lər arasında qalır; həmişə interface + container binding istifadə et

## Nümunələr

### Ümumi Nümunə
Düşün ki, application-da konfiqurasiya dəyərlərini idarə edən bir `ConfigManager` var. Əgər hər dəfə yeni `ConfigManager` yaratsan, hər birinin fərqli vəziyyəti ola bilər. Singleton bu problemi həll edir: yalnız bir `ConfigManager` mövcud olur və hər yerdən eyni dəyərlərə çatılır.

### PHP/Laravel Nümunəsi

```php
// ===== Naïve PHP Singleton (test etmək çətindir — mümkünsə istifadə etmə) =====
class ConfigManager
{
    private static ?self $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->config = require base_path('config/app.php');
    }

    // Clone və unserialize-ı bloklayır
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton.');
    }

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

// İstifadə
$config = ConfigManager::getInstance();
$config->get('app_name'); // hər yerdən eyni instance


// ===== Laravel Service Container ilə Singleton (tövsiyə olunan yol) =====

// 1. Interface təyin et
interface CurrencyRateServiceInterface
{
    public function getRate(string $currency): float;
}

// 2. Concrete implementation
class ExchangeRateService implements CurrencyRateServiceInterface
{
    private array $cachedRates = [];

    public function getRate(string $currency): float
    {
        if (!isset($this->cachedRates[$currency])) {
            // API call — bir dəfə çağırılır, sonra cache-dən oxunur
            $this->cachedRates[$currency] = $this->fetchFromApi($currency);
        }
        return $this->cachedRates[$currency];
    }

    private function fetchFromApi(string $currency): float
    {
        // real API call...
        return 1.85;
    }
}

// 3. AppServiceProvider-də qeydiyyat
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bütün application boyunca eyni instance istifadə olunur
        $this->app->singleton(CurrencyRateServiceInterface::class, ExchangeRateService::class);
    }
}

// 4. Inject et (test edilə bilən)
class OrderController extends Controller
{
    public function __construct(
        private readonly CurrencyRateServiceInterface $rates
    ) {}

    public function create(): JsonResponse
    {
        $usdRate = $this->rates->getRate('USD'); // həmişə eyni instance
        // ...
    }
}

// 5. Test-də mock et — container binding sayəsinde asandır
class OrderControllerTest extends TestCase
{
    public function test_order_uses_correct_rate(): void
    {
        $this->app->singleton(CurrencyRateServiceInterface::class, function () {
            $mock = Mockery::mock(CurrencyRateServiceInterface::class);
            $mock->shouldReceive('getRate')->with('USD')->andReturn(2.0);
            return $mock;
        });

        // test...
    }
}
```

## Praktik Tapşırıqlar
1. Laravel-in öz `config()` helper-ini trace et: `Config` Facade → Container-dən nə alır? `singleton` binding haradadır?
2. Bir `AuditLogger` service yaz: bütün application boyunca eyni instance, `AppServiceProvider`-də `singleton` kimi bind et, controller-ə inject et
3. Naïve Singleton yazan kod tap (real layihədə və ya açıq mənbəli paketdə) — test etmə problemini sübut et: eyni test class-ında iki ayrı test yaz, birinci test-in qoyduğu state-in ikincini necə pozdugunu göstər

## Əlaqəli Mövzular
- [02-facade.md](02-facade.md) — Laravel Facades container singleton-ları üzərindədir
- [14-repository-pattern.md](14-repository-pattern.md) — Repository-lər adətən singleton kimi bind edilir
- [15-service-layer.md](15-service-layer.md) — Service class-lar da singleton binding alır
