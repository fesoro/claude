# Facade (Middle ⭐⭐)

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
- **Subsystem**: Facade-nin arxasında gizlənən bir və ya daha çox class-ın məcmusu

## Praktik Baxış
- **Real istifadə**: `Cache::remember()`, `Queue::push()`, `Mail::send()`, `Storage::put()`, `Log::info()` — hər biri bir Facade
- **Trade-off-lar**: Statik syntax IDE autocomplete-i çətinləşdirir (bəzi IDE-lər Laravel plugin olmadan anlamır); dependency-lər aşkar (explicit) deyil — class constructor-a baxanda bilmirsən nəyi istifadə edir; testability bir az indirekt (real DI-dan fərqli olaraq `shouldReceive` lazımdır)
- **İstifadə etməmək**: Library/package yazan zaman — Facade Laravel-ə bağlayır; inject edilə bilən interface daha yaxşıdır; çox az yerdə istifadə olunacaqsa interface injection daha aydındır
- **Common mistakes**: `Cache::shouldReceive()` işləyir amma əsl statik metodları mock etmək mümkünsüz — test-lərdə Facade istifadə olunmuş kod test edilə bilir, naïve `ClassName::staticMethod()` deyil; Facade-i container qeydiyyatı olmadan istifadə etməyə çalışmaq (runtime error verir)

### Anti-Pattern Nə Zaman Olur?
Facade **God Object** olduqda anti-pattern-ə çevrilir:

- **Çox çox metod**: `OrderFacade::create()`, `OrderFacade::pay()`, `OrderFacade::notify()`, `OrderFacade::generateInvoice()`, `OrderFacade::updateInventory()` — bütün order workflow-u bir Facade-ə sıxışdırılır. Bu artıq Facade deyil, God Object-dir.
- **Biznes məntiqinin gizlənməsi**: Facade subsistemi sadələşdirməlidir, özü iş görməməlidir. Əgər Facade-in özündə `if/else`, DB query, hesablamalar varsa — yanlış yoldur.
- **Test edilə bilməmək**: Facade-in arxasında 8 fərqli service varsa və hamısını mock etmək lazımdırsa — subsystem-i sadələşdirmirsiniz, gizlədirsiniz.
- **Düzgün siqnal**: Facade arxasındakı subsystem-ə doğridan başa düşmədən Facade-i istifadə edə bilirsiniz? Əgər "xeyr"-dirsə, Facade öz işini görmür.

## Nümunələr

### Ümumi Nümunə
Fərz et ki, `PaymentService` 5 fərqli class ilə işləyir: gateway, logger, receipt generator, notification sender, audit trail. Facade bu 5-ini arxada saxlayıb, developer-a `Payment::charge($amount)` kimi sadə bir API göstərir. Client bu 5 class-ı bilmir, qurmur, inject etmir.

### PHP/Laravel Nümunəsi

```php
// ===== Laravel Facade necə işləyir (daxili mexanizm) =====

// Illuminate\Support\Facades\Cache source (sadələşdirilmiş)
abstract class Facade
{
    protected static $app; // Service Container

    public static function __callStatic(string $method, array $args): mixed
    {
        // Statik çağırış → container-dən real instance al → həmin metodu çağır
        $instance = static::getFacadeRoot();
        return $instance->$method(...$args);
    }

    protected static function getFacadeRoot(): mixed
    {
        // getFacadeAccessor() string key-i container-dən resolve edir
        return static::$app->make(static::getFacadeAccessor());
    }

    abstract protected static function getFacadeAccessor(): string;
}

class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache'; // container alias — CacheManager-ə işarə edir
    }
}

// Cache::get('user:1') əslində bunun nəticəsidir:
// app('cache')->get('user:1')
// Fərq yoxdur, amma DX (developer experience) daha yaxşıdır


// ===== Custom Facade yaratmaq (addım-addım) =====

// 1. Service class — real işi burada görürsünüz
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
        // Hər nömrəyə ayrıca SMS göndər, nəticələri topla
        return array_map(fn($n) => $this->send($n, $message), $numbers);
    }
}

// 2. ServiceProvider-də qeydiyyat — dependency injection burada konfiqurasiya olunur
class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // singleton: hər request üçün bir instance — config bir dəfə oxunur
        $this->app->singleton('sms', function ($app) {
            return new SmsService(
                apiKey: config('services.twilio.key'),
                from: config('services.twilio.from'),
            );
        });
    }
}

// 3. Facade class — sadəcə alias, iş görmür
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms'; // ServiceProvider-dəki alias ilə eyni olmalıdır
    }
}

// 4. config/app.php-də alias əlavə et
// 'aliases' => [
//     'Sms' => App\Facades\Sms::class,
// ]

// 5. İstifadə — sadə statik sintaksis, amma arxada DI işləyir
Sms::send('+994501234567', 'Sifarişiniz hazırdır');
Sms::sendBulk(['+994501234567', '+994551234567'], 'Kampaniya başladı');

// 6. Test-də mock et — real SMS göndərilmədən test
class OrderServiceTest extends TestCase
{
    public function test_sms_sent_on_order_created(): void
    {
        // Facade mock — heç bir real SMS göndərilmir
        Sms::shouldReceive('send')
            ->once()
            ->with('+994501234567', Mockery::type('string'))
            ->andReturn(true);

        // OrderService-i trigger et — Sms::send() içəridə çağırılır
        app(OrderService::class)->create($orderData);
    }
}


// ===== Real-time Facade (ayrıca class yazmadan) =====

// Normal constructor injection
use App\Services\PaymentService;

class OrderController extends Controller
{
    public function __construct(private PaymentService $payment) {}
}

// Real-time Facade — use ifadəsinin önünə "Facades\" əlavə et
// Laravel avtomatik olaraq container-dən resolve edir
use Facades\App\Services\PaymentService;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // PaymentService container-dən alınır, singleton kimi davranır
        // Test-də PaymentService::shouldReceive() istifadə edilə bilər
        $result = PaymentService::charge($request->amount);
        // ...
    }
}


// ===== Pis nümunə: God Object Facade (anti-pattern) =====

// BU YANLIŞDIR — bir Facade-ə çox məsuliyyət yüklənib
class OrderFacade extends Facade
{
    protected static function getFacadeAccessor(): string { return 'order'; }
}

// OrderService içəridə bunları edir:
// - DB query (order yaratmaq)
// - Payment charge etmək
// - Email notification göndərmək
// - Invoice generate etmək
// - Inventory azaltmaq
// - Audit log yazmaq
// Bu, Facade deyil — God Object-dir. Hər məsuliyyəti ayrı service-ə ayırın.
```

## Praktik Tapşırıqlar
1. `Cache::get()` çağırışını IDE-də "Go to definition" ilə izlə: `Cache` Facade → `getFacadeAccessor()` → container-dən nə alınır → real `get()` metodu haradadır? Bu izləmə Facade mexanizmini əyani göstərir.
2. Özünün `Pdf` Facade-ini yaz: `PdfService` class (`generate(string $html): string`), ServiceProvider-də `singleton` kimi qeydiyyat, `Pdf` Facade class, `config/app.php`-də alias — `Pdf::generate($html)` ilə istifadə et.
3. `PdfService`-i test et: `Pdf::shouldReceive('generate')->once()->andReturn('pdf-content')` ilə Facade-i mock edərək controller test yaz. Real PDF engine çalışmır — test izolə olunmuşdur.

## Əlaqəli Mövzular
- [../creational/01-singleton.md](../creational/01-singleton.md) — Facade-lər container singleton-ları üzərindədir
- [02-adapter.md](02-adapter.md) — Facade sadələşdirir, Adapter interface dəyişdirir
- [03-decorator.md](03-decorator.md) — Decorator funksionallıq əlavə edir; Facade isə mürəkkəbliyi gizlədir
- [04-proxy.md](04-proxy.md) — Laravel Facade statik proxy-dir; Proxy vs Facade fərqini anlamaq üçün
- [../laravel/01-repository-pattern.md](../laravel/01-repository-pattern.md) — Repository-lər tez-tez Facade arxasında gizlənir
- [../laravel/02-service-layer.md](../laravel/02-service-layer.md) — Service class-lar Facade arxasında gizlənə bilər
- [../laravel/06-di-vs-service-locator.md](../laravel/06-di-vs-service-locator.md) — Facade vs constructor injection fərqini müqayisə edir
- [../behavioral/01-observer.md](../behavioral/01-observer.md) — Facade arxasındakı service-lər event fire edə bilər
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Facade Single Responsibility-i qorumaqda kömək edir; God Object anti-pattern ilə əlaqə
- [../architecture/05-hexagonal-architecture.md](../architecture/05-hexagonal-architecture.md) — Hexagonal arxitekturada Facade port kimi çıxış nöqtəsi ola bilər
