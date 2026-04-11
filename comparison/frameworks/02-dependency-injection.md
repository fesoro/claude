# Dependency Injection: Spring IoC vs Laravel Service Container

## Giris

Dependency Injection (DI) muasir framework-larin en muhum konsepsiyalarindan biridir. Esas fikir beledur: bir sinif oz asililigini ozuu yaratmir, xaricden alir. Bu, kodu test olunabilir, modulyar ve deyisdirile bilen edir.

Hem Spring, hem de Laravel bu meqsed ucun oz "container" sistemlerine malikdir. Spring-de buna **IoC Container** (Inversion of Control), Laravel-de ise **Service Container** deyilir. Her ikisi eyni problemi hell edir, amma ferqli yanasmalarla.

## Inversion of Control (IoC) Nedir?

Normal proqramlasdirmada sinif oz asililigini ozuu yaradir:

```java
// SEHV yanasma - siki baglilik (tight coupling)
public class OrderService {
    private final EmailService emailService = new EmailService(); // ozuu yaradir
    private final PaymentGateway gateway = new StripeGateway();   // ozuu yaradir

    public void placeOrder(Order order) {
        gateway.charge(order.getTotal());
        emailService.sendConfirmation(order);
    }
}
```

IoC ile biz "kontrolu terspine cevirrik" - sinif asiliqlari ozuu yaratmir, xaricden verilir:

```java
// DUZGUN yanasma - serbest baglilik (loose coupling)
public class OrderService {
    private final EmailService emailService;
    private final PaymentGateway gateway;

    // Asiliglar xaricden verilir
    public OrderService(EmailService emailService, PaymentGateway gateway) {
        this.emailService = emailService;
        this.gateway = gateway;
    }
}
```

## Spring-de Istifadesi

### IoC Container ve Bean-ler

Spring-de IoC Container butun obyektleri ("bean"-leri) idariye edir. Siz annotasiyalar ile sinfi bean kimi qeyd edirsiniz, Spring isə onu yaradir, asiliglarini hell edir ve lazim olan yere verir.

### Komponent annotasiyalari

```java
// @Component - umumi komponent
@Component
public class EmailNotifier {
    public void send(String to, String message) {
        // e-poct gondermek
    }
}

// @Service - biznes meantiq tebeqesi
@Service
public class OrderService {
    private final OrderRepository orderRepository;
    private final EmailNotifier emailNotifier;

    // Constructor Injection - en tovsiye olunan usul
    public OrderService(OrderRepository orderRepository, EmailNotifier emailNotifier) {
        this.orderRepository = orderRepository;
        this.emailNotifier = emailNotifier;
    }

    public Order createOrder(OrderRequest request) {
        Order order = new Order(request.getItems());
        Order saved = orderRepository.save(order);
        emailNotifier.send(request.getEmail(), "Sifarisiz qebul olundu!");
        return saved;
    }
}

// @Repository - data erisim tebeqesi
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {
    List<Order> findByStatus(OrderStatus status);
}

// @Controller / @RestController - web tebeqesi
@RestController
@RequestMapping("/api/orders")
public class OrderController {
    private final OrderService orderService;

    public OrderController(OrderService orderService) {
        this.orderService = orderService;
    }

    @PostMapping
    public ResponseEntity<Order> createOrder(@RequestBody OrderRequest request) {
        return ResponseEntity.status(201).body(orderService.createOrder(request));
    }
}
```

### @Component, @Service, @Repository ferqi

Texniki cehetden her ucuu eyni isi gorur - sinfi bean kimi qeydiyyatdan kecirir. Amma semantik menalari var:

```java
@Component   // Umumi komponent - helper, utility sinifler ucun
@Service     // Biznes meantiq - service layer ucun
@Repository  // Data erisim - exception translation elave edir
@Controller  // Web tebeqesi - HTTP sorularini qebul edir
```

`@Repository`-nin xususi bir ustunluyu var: verilener bazasi xetalarini Spring-in `DataAccessException`-ina cevrir.

### @Autowired ve injection usullari

```java
@Service
public class PaymentService {

    // 1. Constructor Injection (EN YAXSI usul)
    private final PaymentGateway gateway;

    public PaymentService(PaymentGateway gateway) {
        this.gateway = gateway;
    }

    // 2. Field Injection (@Autowired ile) - TOVSIYE OLUNMUR
    // @Autowired
    // private PaymentGateway gateway;

    // 3. Setter Injection - nader hallarda istifade olunur
    // private PaymentGateway gateway;
    // @Autowired
    // public void setGateway(PaymentGateway gateway) {
    //     this.gateway = gateway;
    // }
}
```

Constructor injection niye en yaxsisidir:
- `final` field istifade oluna bilir (immutability)
- Asiliqlarsiz obyekt yaradila bilmez (null olmasi mumkun deyil)
- Test zamani mocklari asanliqla vermek olur
- Siki asililiqlar goze carpir (coxlu parametr = coxlu asililiq = refaktoring lazimdir)

### @Bean ve @Configuration

Bezen ucuncu teref kitabxanalarin siniflerine annotasiya elave ede bilmirik. Bu halda `@Bean` istifade olunur:

```java
@Configuration
public class AppConfig {

    // RestTemplate bean-i yaradir
    @Bean
    public RestTemplate restTemplate() {
        RestTemplate template = new RestTemplate();
        template.setConnectTimeout(Duration.ofSeconds(5));
        return template;
    }

    // Ferqli implementasiya secimi
    @Bean
    @Profile("production")
    public PaymentGateway stripeGateway() {
        return new StripePaymentGateway(stripeApiKey);
    }

    @Bean
    @Profile("development")
    public PaymentGateway fakeGateway() {
        return new FakePaymentGateway();
    }

    // ObjectMapper-i ozellesdirmek
    @Bean
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();
        mapper.registerModule(new JavaTimeModule());
        mapper.configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);
        return mapper;
    }
}
```

### Interface esasli DI

```java
// Interface tanimlayiriq
public interface NotificationService {
    void send(String recipient, String message);
}

// Bir nece implementasiya
@Service
@Primary  // Defolt implementasiya
public class EmailNotificationService implements NotificationService {
    @Override
    public void send(String recipient, String message) {
        // e-poct gonder
    }
}

@Service
public class SmsNotificationService implements NotificationService {
    @Override
    public void send(String recipient, String message) {
        // SMS gonder
    }
}

// Istifade - @Primary sayesinde EmailNotificationService gelir
@Service
public class OrderService {
    private final NotificationService notificationService;

    public OrderService(NotificationService notificationService) {
        this.notificationService = notificationService; // EmailNotificationService
    }
}

// Xususi implementasiyanin secimi ucun @Qualifier
@Service
public class UrgentOrderService {
    private final NotificationService notificationService;

    public UrgentOrderService(@Qualifier("smsNotificationService") NotificationService notificationService) {
        this.notificationService = notificationService; // SmsNotificationService
    }
}
```

### Scope (omur muddeti)

```java
@Component
@Scope("singleton")    // Defolt - tek instans butun tetbiq boyunca
public class AppCache { }

@Component
@Scope("prototype")    // Her defe yeni instans yaradilir
public class RequestHandler { }

@Component
@Scope("request")      // Her HTTP sorgusu ucun bir instans (web scope)
public class RequestContext { }

@Component
@Scope("session")      // Her HTTP sessiya ucun bir instans (web scope)
public class UserSession { }
```

## Laravel-de Istifadesi

### Service Container

Laravel-in Service Container-i framework-un ureyi hesab olunur. O, sinfleri yaratmagi, asiliqlari hell etmeyei ve omur muddetini idarye etmeyi ohdeline goturur.

### Avtomatik Resolution (zero-configuration)

Laravel-in en guclu xususiyyetlerinden biri odur ki, coxu halda hec bir konfiqurasiya lazim deyil:

```php
// Laravel tip-hint-e esasen asiliqlari avtomatik hell edir
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EmailNotifier $emailNotifier
    ) {}

    public function createOrder(array $data): Order
    {
        $order = $this->orderRepository->create($data);
        $this->emailNotifier->send($order->user->email, 'Sifarisiz qebul olundu!');
        return $order;
    }
}

// Controller-de istifade - Laravel ozuu OrderService-i yaradir
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $order = $this->orderService->createOrder($request->validated());
        return response()->json($order, 201);
    }
}
```

Hec bir yerde `bind()` ve ya `register` cagirilmadi - Laravel constructor-daki tip-hint-lere baxaraq asiliqlari ozuu tapir ve yaradir.

### bind() ve singleton()

Bezen ozellesdirilmis baglama lazim olur:

```php
// app/Providers/AppServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bind() - her defe yeni instans yaradir
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return new StripePaymentGateway(
                config('services.stripe.key'),
                config('services.stripe.secret')
            );
        });

        // singleton() - tek instans, sonra keslenir
        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                config('cache.default'),
                config('cache.stores')
            );
        });

        // Interface-i implementasiyaya baglama
        $this->app->bind(
            NotificationInterface::class,
            EmailNotification::class
        );

        // Sadece sinif adi ile
        $this->app->bind(ReportGenerator::class, function ($app) {
            return new ReportGenerator(
                $app->make(DataSource::class),
                $app->make(PdfRenderer::class)
            );
        });
    }
}
```

### Kontekstual Baglama (Contextual Binding)

Ferqli sinifler eyni interface-in ferqli implementasiyalarini ala biler:

```php
// AppServiceProvider.php

public function register(): void
{
    // OrderService EmailNotification alsin
    $this->app->when(OrderService::class)
        ->needs(NotificationInterface::class)
        ->give(EmailNotification::class);

    // UrgentOrderService SmsNotification alsin
    $this->app->when(UrgentOrderService::class)
        ->needs(NotificationInterface::class)
        ->give(SmsNotification::class);

    // Primitiv deyerlerin verilmesi
    $this->app->when(StripePaymentGateway::class)
        ->needs('$apiKey')
        ->give(config('services.stripe.key'));

    // Closure ile murekkeb meantiq
    $this->app->when(ReportService::class)
        ->needs(DataSource::class)
        ->give(function ($app) {
            return app()->environment('testing')
                ? new FakeDataSource()
                : new DatabaseDataSource();
        });
}
```

### Service Provider-ler

Laravel-de container-e baglama islemleri Service Provider-lerde aparilir:

```php
// app/Providers/PaymentServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * register() - yalniz container-e baglama et.
     * Baska service-leri istifade etme, cunki onlar henuz yuklenmeye biler.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            $driver = config('payment.default');

            return match ($driver) {
                'stripe' => new StripeGateway(config('payment.stripe.key')),
                'paypal' => new PayPalGateway(config('payment.paypal.client_id')),
                default => throw new \InvalidArgumentException("Namelum odenis sistemi: {$driver}"),
            };
        });

        $this->app->bind(RefundService::class, function ($app) {
            return new RefundService(
                $app->make(PaymentGatewayInterface::class),
                $app->make(OrderRepository::class)
            );
        });
    }

    /**
     * boot() - butun provider-ler register olunduqdan sonra cagirilir.
     * Burada diger service-lerden istifade etmek olar.
     */
    public function boot(): void
    {
        // Event listener qeyd etmek, route-lar yuklemek ve s.
    }
}
```

Provider-i qeydiyyatdan kecirmek ucun `config/app.php`-de elave olunur:

```php
// config/app.php
'providers' => [
    // Framework provider-leri
    Illuminate\Auth\AuthServiceProvider::class,
    // ...

    // Tetbiq provider-leri
    App\Providers\AppServiceProvider::class,
    App\Providers\PaymentServiceProvider::class,  // bizim provider
],
```

### Method Injection

Laravel controller metodlarinda da DI isleyir:

```php
class ReportController extends Controller
{
    // Metod seviyyesinde injection
    public function generate(Request $request, ReportGenerator $generator): JsonResponse
    {
        // $generator avtomatik yaradilir ve verilir
        $report = $generator->generate($request->input('type'));
        return response()->json($report);
    }

    // Route model binding ile birlikde
    public function show(Order $order, InvoiceService $invoiceService): JsonResponse
    {
        // $order DB-den avtomatik tapilir, $invoiceService container-den gelir
        $invoice = $invoiceService->getInvoice($order);
        return response()->json($invoice);
    }
}
```

### Facade-lar

Laravel-e xas olan Facade sistemi, static sintaksis ile container-deki service-lere muraciet etmeye imkan verir:

```php
// Facade istifadesi - static gorunur, amma dalda container-den goturur
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function processOrder(Order $order): void
    {
        // Cache::get() dalda CacheManager-in get() metodunu cagirir
        $cachedPrice = Cache::get("price_{$order->product_id}");

        Log::info('Sifaris islenilir', ['order_id' => $order->id]);

        Mail::to($order->user)->send(new OrderConfirmation($order));
    }
}

// Eyni seyi DI ile yazmaq:
class OrderService
{
    public function __construct(
        private readonly CacheManager $cache,
        private readonly Logger $logger,
        private readonly Mailer $mailer
    ) {}

    public function processOrder(Order $order): void
    {
        $cachedPrice = $this->cache->get("price_{$order->product_id}");
        $this->logger->info('Sifaris islenilir', ['order_id' => $order->id]);
        $this->mailer->to($order->user)->send(new OrderConfirmation($order));
    }
}
```

## Esas Ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Container adi** | IoC Container / ApplicationContext | Service Container |
| **Komponent askarlanmasi** | Annotasiyalar (`@Component`, `@Service`) | Avtomatik resolution + ServiceProvider |
| **Defolt scope** | Singleton | Her defe yeni (bind), ve ya singleton |
| **Interface baglama** | `@Primary`, `@Qualifier` | `bind()`, kontekstual baglama |
| **Konfiqurasiya yeri** | `@Configuration` sinfleri | ServiceProvider-ler |
| **Injection usullari** | Constructor, field, setter | Constructor, method, Facade |
| **Compile zamani yoxlama** | Beli (Java compiler) | Yox (runtime-da xeta cixir) |
| **Scope novleri** | singleton, prototype, request, session | bind (yeni), singleton, scoped |
| **Static erisim** | Yox (anti-pattern sayilir) | Facade-lar (static proxy) |
| **Lazy loading** | `@Lazy` annotasiyasi | Defolt olaraq lazy |

## Niye Bele Ferqler Var?

### Spring-in yanasmasi

1. **Type safety**: Java statik tipli dildir, ona gore Spring compile zamaninda bir coxu xetani tapir. Eger interface-in implementasiyasi tapilmasa, tetbiq ise dusmeyecek. Bu, boyu layihelerde etibarliliq verir.

2. **Annotasiya medeniyyeti**: Java-da annotasiyalar metadata kimi isleyir. `@Service`, `@Repository` kimi annotasiyalar sinifin rolunu bildrir. Spring bu annotasiyalari component scanning zamani tapir ve bean-ler yaradir.

3. **Singleton defolt**: Spring-de butun bean-ler defolt olaraq singleton-dur. Bunun sebeb-i performance-dir - Java-da obyekt yaratmaq PHP-ye nisbeten daha "agir" sayilir, ve tetbiq uzun muddet isleyir (server daima aktivdir).

4. **Sert struktur**: Spring sinif hierarxiyasini, annotasiyalari ve interface-leri mecburi edir. Bu, boyuk komandalarda kod keyfiyyetini saxlamaga komek edir.

### Laravel-in yanasmasi

1. **Sadelik**: Laravel-in felsefesi "developer happiness"-dir. Coxu halda hec bir konfiqurasiya lazim deyil - constructor-da tip yazirsiniz, Laravel qalanini ozuu hell edir.

2. **Facade-lar**: PHP dunyasinda static metodlar genis istifade olunur. Laravel Facade-lar vasitesile static cagiris gorunusunu saxlayir, amma dalda container-den istifade edir. Bu, kodu qisa ve oxunaqli edir, amma test zamani bezen cetinlik yarada biler.

3. **Bind defolt**: Laravel-de `bind()` her defe yeni obyekt yaradir (prototype scope). Bunun sebebi PHP-nin request-per-process modelidir - her sorgu ucun PHP prosesi baslayir ve biter, ona gore singleton-in uzunomurlu olmasi zaten mentiqsizdir (bir sorgu cərçivesinde singleton menali olur).

4. **Kontekstual baglama**: Laravel-in `when()->needs()->give()` sintaksisi Spring-in `@Qualifier`-inden daha oxunaqli ve intuitiv ola biler. Bu, Laravel-in "expressive syntax" felsefesinin tezahuurudur.

5. **register() vs boot()**: Service Provider-lerin iki fazali yuklenmesi (evvelce hamisi register, sonra hamisi boot) asiliqlarin duzgun sirada hell olunmasini temin edir.

## Hansi Framework-de Var, Hansinda Yoxdur?

### Yalniz Spring-de olan xususiyyetler

- **Compile zamani DI yoxlamasi**: Java compiler tip uygunsuzluqlari xeta verir. Eger yanlis tip inject etmeye calissaniz, tetbiq compile olmayacaq.

- **`@Conditional` annotasiyalari**: `@ConditionalOnProperty`, `@ConditionalOnClass` kimi annotasiyalar ile bean-in yaradilmasini sertlere baglamaq olur. Meselen: yalniz Redis driver movcud olanda Redis bean-i yarat.

```java
@Bean
@ConditionalOnProperty(name = "cache.type", havingValue = "redis")
public CacheManager redisCacheManager() {
    return new RedisCacheManager();
}
```

- **Bean lifecycle callback-leri**: `@PostConstruct`, `@PreDestroy` annotasiyalari ile bean yaradildiqdan sonra ve ya melv edildikden evvel kod isletmek olur.

```java
@Service
public class ConnectionPool {
    @PostConstruct
    public void init() {
        // Bean yaradildiqdan sonra
    }

    @PreDestroy
    public void cleanup() {
        // Tetbiq baglananda
    }
}
```

### Yalniz Laravel-de olan xususiyyetler

- **Facade-lar**: Static proxy pattern - `Cache::get()`, `Log::info()` kimi. Spring-de bele bir mexanizm yoxdur.

- **Kontekstual baglama (when/needs/give)**: Spring-de `@Qualifier` var, amma Laravel-in sintaksisi daha ifadeli ve oxunaqlidir.

- **Method injection**: Controller metodlarinda da tip-hint etmekle asiliqlari almaq olur. Spring-de bu yalniz constructor ve ya field seviyyesinde isleyir.

- **`app()` helper**: Istenielen yerde container-e erisim: `app(PaymentGateway::class)` ve ya `resolve(PaymentGateway::class)`. Spring-de bu `ApplicationContext.getBean()` ile mumkundur, amma istifadesi tovsiye olunmur (Service Locator anti-pattern).

- **Tagged binding**: Eyni interface-in butun implementasiyalarini etiketleme ve birlikde almaq:

```php
$this->app->tag([
    EmailNotification::class,
    SmsNotification::class,
    PushNotification::class,
], 'notifications');

// Istifade
$notifiers = app()->tagged('notifications');
foreach ($notifiers as $notifier) {
    $notifier->send($user, $message);
}
```
