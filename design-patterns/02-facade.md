# Facade (Junior ⭐)

## İcmal
Facade pattern mürəkkəb bir subsistemi sadə, vahid bir interface arxasında gizlədir. Laravel-də Facade-lər Service Container-dəki service-lərə statik-görünüşlü, lakin test edilə bilən proxy kimi çalışır — `Cache::get('key')` yazdıqda əslində container-dən `cache` service-ini alır və `get()` çağırır.

## Niyə Vacibdir
Laravel-in `Cache`, `Queue`, `Mail`, `Storage` kimi Facade-ləri olmadan hər controller-ə onlarla dependency inject etmək lazım olardı. Facade-lər bu mürəkkəbliyi sadə static sintaksis arxasında gizlədib developer experience-i yaxşılaşdırır, eyni zamanda container-in mock qabiliyyəti sayəsində test edilə bilir.

## Əsas Anlayışlar
- **Facade class**: `getFacadeAccessor()` ilə container-dəki service-i göstərən sinif
- **`__callStatic`**: Statik çağırışları real service instance-ına yönləndirir
- **Real-time Facade**: `use Facades\App\Services\PaymentService` — ayrıca Facade class yazmadan
- **Facade mocking**: `Cache::shouldReceive('get')->andReturn(...)` — PHPUnit test-lərində işləyir
- **Service alias**: `config/app.php`-dəki `aliases` massivi — `Cache` → `Illuminate\Support\Facades\Cache`

## Praktik Baxış
- **Real istifadə**: `Cache::remember()`, `Queue::push()`, `Mail::send()`, `Storage::put()`, `Log::info()` — hər biri bir Facade
- **Trade-off-lar**: Statik syntax IDE autocomplete-i çətinləşdirir (bəzi IDE-lər Laravel plugin olmadan anlamır); dependency-lər aşkar (explicit) deyil — class constructor-a baxanda bilmirsən nəyi istifadə edir
- **İstifadə etməmək**: Library/package yazan zaman — Facade Laravel-ə bağlayır; inject edilə bilən interface daha yaxşıdır
- **Common mistakes**: `Cache::shouldReceive()` işləyir amma əsl statik metodları mock etmək mümkünsüz — test-lərdə Facade istifadə olunmuş kod test edilə bilir, naïve `ClassName::staticMethod()` deyil

## Nümunələr

### Ümumi Nümunə
Fərz et ki, `PaymentService` 5 fərqli class ilə işləyir: gateway, logger, receipt generator, notification sender, audit trail. Facade bu 5-ini arxada saxlayıb, developer-a `Payment::charge($amount)` kimi sadə bir API göstərir.

### PHP/Laravel Nümunəsi

```php
// ===== Laravel Facade necə işləyir (daxili mexanizm) =====

// Illuminate\Support\Facades\Cache source (sadələşdirilmiş)
abstract class Facade
{
    protected static $app; // Service Container

    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot(); // container-dən instance al
        return $instance->$method(...$args);
    }

    protected static function getFacadeRoot(): mixed
    {
        return static::$app->make(static::getFacadeAccessor());
    }

    abstract protected static function getFacadeAccessor(): string;
}

class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache'; // container alias
    }
}

// Cache::get('user:1') əslində bunun nəticəsidir:
// app('cache')->get('user:1')


// ===== Custom Facade yaratmaq (addım-addım) =====

// 1. Service class
class SmsService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $from
    ) {}

    public function send(string $to, string $message): bool
    {
        // Twilio/Vonage API call...
        Log::info("SMS sent to {$to}");
        return true;
    }

    public function sendBulk(array $numbers, string $message): array
    {
        return array_map(fn($n) => $this->send($n, $message), $numbers);
    }
}

// 2. ServiceProvider-də qeydiyyat
class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sms', function ($app) {
            return new SmsService(
                apiKey: config('services.twilio.key'),
                from: config('services.twilio.from'),
            );
        });
    }
}

// 3. Facade class
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms'; // ServiceProvider-dəki alias ilə eyni
    }
}

// 4. config/app.php-də alias əlavə et
// 'aliases' => [
//     'Sms' => App\Facades\Sms::class,
// ]

// 5. İstifadə
Sms::send('+994501234567', 'Sifarişiniz hazırdır');
Sms::sendBulk(['+994501234567', '+994551234567'], 'Kampaniya başladı');

// 6. Test-də mock et
class OrderServiceTest extends TestCase
{
    public function test_sms_sent_on_order_created(): void
    {
        Sms::shouldReceive('send')
            ->once()
            ->with('+994501234567', Mockery::type('string'))
            ->andReturn(true);

        // OrderService-i trigger et...
        app(OrderService::class)->create($orderData);
    }
}


// ===== Real-time Facade (ayrıca class yazmadan) =====

// Normal injection
use App\Services\PaymentService;

class OrderController extends Controller
{
    public function __construct(private PaymentService $payment) {}
}

// Real-time Facade — use ifadəsinin önünə "Facades\" əlavə et
use Facades\App\Services\PaymentService;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // PaymentService container-dən alınır, singleton kimi
        $result = PaymentService::charge($request->amount);
        // ...
    }
}
```

## Praktik Tapşırıqlar
1. `Cache::get()` çağırışını IDE-də "Go to definition" ilə izlə: `Cache` Facade → `getFacadeAccessor()` → container-dən nə alınır → real `get()` metodu haradadır?
2. Özünün `Pdf` Facade-ini yaz: `PdfService` class (`generate(string $html): string`), ServiceProvider-də `singleton` kimi qeydiyyat, `Pdf` Facade class, `config/app.php`-də alias — `Pdf::generate($html)` ilə istifadə et
3. `PdfService`-i test et: `Pdf::shouldReceive('generate')->once()->andReturn('pdf-content')` ilə Facade-i mock edərək controller test yaz

## Əlaqəli Mövzular
- [01-singleton.md](01-singleton.md) — Facade-lər container singleton-ları üzərindədir
- [07-adapter.md](07-adapter.md) — Facade sadələşdirir, Adapter interface dəyişdirir
- [15-service-layer.md](15-service-layer.md) — Service class-lar Facade arxasında gizlənə bilər
