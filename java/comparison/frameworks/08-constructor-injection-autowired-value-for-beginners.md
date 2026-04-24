# Constructor Injection, @Autowired, @Value: Yeni Başlayanlar üçün

> **Seviyye:** Beginner ⭐

## Giriş

Spring Boot-da dependency injection (DI) öyrənəndə sənə 3 yol göstərəcəklər: **constructor injection**, **setter injection**, **field injection**. Sual: hansını istifadə etməli?

Qısa cavab: **həmişə constructor injection**. Bu fayl onun niyə ən yaxşı seçim olduğunu, `@Autowired` annotasiyasının nə vaxt lazım olduğunu, `@Value` ilə konfiqurasiya dəyərlərini necə injection etməyi və Laravel-də oxşar mexanizmləri göstərir.

Laravel-dən gəlirsənsə, Laravel Service Container-i konstruktor-da tip-hint görəndə avtomatik obyekt yaradıb verir. Spring də eyni şeyi edir, amma bir neçə əlavə annotasiya (`@Autowired`, `@Qualifier`, `@Primary`) ilə daha çox nəzarət verir.

## Spring/Java-də istifadəsi

### 1. 3 injection növü — ümumi baxış

```java
// 1. CONSTRUCTOR INJECTION — ən yaxşısı
@Service
public class OrderService {
    private final PaymentGateway gateway;

    public OrderService(PaymentGateway gateway) {
        this.gateway = gateway;
    }
}

// 2. SETTER INJECTION — nadir istifadə olunur
@Service
public class OrderService {
    private PaymentGateway gateway;

    @Autowired
    public void setGateway(PaymentGateway gateway) {
        this.gateway = gateway;
    }
}

// 3. FIELD INJECTION — pisdir, istifadə etmə
@Service
public class OrderService {
    @Autowired
    private PaymentGateway gateway;
}
```

### 2. Constructor Injection — ən yaxşı seçim

```java
package com.example.demo.service;

import org.springframework.stereotype.Service;

@Service
public class UserService {

    private final UserRepository repository;
    private final EmailService emailService;
    private final PasswordEncoder encoder;

    // Spring bu konstruktoru gorur və asılılıqlari avtomatik verir
    public UserService(UserRepository repository,
                       EmailService emailService,
                       PasswordEncoder encoder) {
        this.repository = repository;
        this.emailService = emailService;
        this.encoder = encoder;
    }

    public User register(RegisterRequest request) {
        User user = new User();
        user.setEmail(request.getEmail());
        user.setPassword(encoder.encode(request.getPassword()));

        User saved = repository.save(user);
        emailService.sendWelcome(saved.getEmail());
        return saved;
    }
}
```

### 3. Constructor Injection-un ustunlukleri

**1. Immutability (deyismezlik)**

```java
private final UserRepository repository;  // final - bir defe set olunur
```

`final` field sayesinde obyekt yaradildiqdan sonra asılılıq deyise bilmez. Bu, thread-safety və predictability verir.

**2. Fail-fast davranis**

```java
// SEHV halda Spring konteyner `UserRepository` tapa bilmese, tetbiq BASLAMAYACAQ
// Field injection-da ise tetbiq baslayacaq, amma istifadeda NullPointerException verecek
```

Konstruktor injection-da problem dərhal — tətbiq start olanda — aşkar olur. Runtime-da yox.

**3. Test etmek asandir**

```java
// Unit test - Spring-siz
class UserServiceTest {
    @Test
    void shouldRegisterUser() {
        UserRepository mockRepo = mock(UserRepository.class);
        EmailService mockEmail = mock(EmailService.class);
        PasswordEncoder mockEncoder = mock(PasswordEncoder.class);

        // Asanlikla mock-lari veririk
        UserService service = new UserService(mockRepo, mockEmail, mockEncoder);

        // Test ...
    }
}
```

Field injection-da bu cox cetin olur - `@Autowired private` field-lərə Spring olmadan deyer vermək üçün reflection lazimdir.

**4. Circular dependency-ni askarlayir**

```java
@Service
public class ServiceA {
    public ServiceA(ServiceB b) { ... }
}

@Service
public class ServiceB {
    public ServiceB(ServiceA a) { ... }  // Dairevi asiliq!
}
```

Constructor injection ilə Spring tətbiqi başlatmayacaq — "BeanCurrentlyInCreationException" verəcək. Field injection-la bu gizli qalar və ya runtime-da NPE-lər verə bilər.

### 4. Single constructor Spring 4.3+ @Autowired tələb etmir

Əvvəllər belə yazmaq lazim idi:

```java
@Service
public class UserService {
    private final UserRepository repository;

    @Autowired  // Spring 4.3-dən əvvəl mecburi idi
    public UserService(UserRepository repository) {
        this.repository = repository;
    }
}
```

Spring 4.3+ ilə (hazirda ən aşağı Spring Boot 2.0+) single constructor-da `@Autowired` **yaziLMAYA biler**:

```java
@Service
public class UserService {
    private final UserRepository repository;

    // @Autowired ihtiyac yoxdur - tek konstruktor var, Spring ozu tapir
    public UserService(UserRepository repository) {
        this.repository = repository;
    }
}
```

Eger iki konstruktor varsa, Spring-ə hansını istifadə edəcəyini bildirmək lazimdir:

```java
@Service
public class UserService {
    private final UserRepository repository;
    private String defaultRole;

    @Autowired  // Bu konstruktor istifade olunacaq
    public UserService(UserRepository repository) {
        this.repository = repository;
        this.defaultRole = "USER";
    }

    // Test-də istifadə ucun
    public UserService(UserRepository repository, String defaultRole) {
        this.repository = repository;
        this.defaultRole = defaultRole;
    }
}
```

### 5. Lombok @RequiredArgsConstructor

Constructor yazmaq bezen uzun olur — xususile 5-6 field varsa. Lombok bu boilerplate-i yox edir:

```java
package com.example.demo.service;

import lombok.RequiredArgsConstructor;
import org.springframework.stereotype.Service;

@Service
@RequiredArgsConstructor  // final field-lər üçün konstruktor avtomatik yaradir
public class UserService {
    private final UserRepository repository;
    private final EmailService emailService;
    private final PasswordEncoder encoder;

    // Konstruktor yazmaga ehtiyac yoxdur!

    public User register(RegisterRequest request) {
        // repository, emailService, encoder istifade edə bilər
    }
}
```

Lombok compile zamani belə konstruktor yaradir:

```java
public UserService(UserRepository repository, EmailService emailService, PasswordEncoder encoder) {
    this.repository = repository;
    this.emailService = emailService;
    this.encoder = encoder;
}
```

### 6. Setter Injection — nə vaxt istifadə olunur

Setter injection opsional asılılıq üçün istifadə olunur:

```java
@Service
public class NotificationService {
    private EmailClient emailClient;  // final deyil

    @Autowired(required = false)
    public void setEmailClient(EmailClient emailClient) {
        this.emailClient = emailClient;
    }

    public void notify(String message) {
        if (emailClient != null) {
            emailClient.send(message);
        } else {
            System.out.println("E-poct deaktivdir, mesaj: " + message);
        }
    }
}
```

Ve ya `Optional<T>` ile:

```java
@Service
public class NotificationService {
    private final Optional<EmailClient> emailClient;

    public NotificationService(Optional<EmailClient> emailClient) {
        this.emailClient = emailClient;
    }

    public void notify(String message) {
        emailClient.ifPresentOrElse(
            client -> client.send(message),
            () -> System.out.println("E-poct deaktivdir: " + message)
        );
    }
}
```

### 7. Field Injection — niye pisdir

```java
@Service
public class BadUserService {

    @Autowired
    private UserRepository repository;  // BAD

    @Autowired
    private EmailService emailService;  // BAD
}
```

Problemler:
- **Test etmek cetin**: Reflection olmadan mock vermek olmur.
- **`final` olmur**: Field-e sonra-sonra deyer verile biler.
- **Gizli asılılıqlar**: Sinfin asılılıqlarını görmək ücün butun sinfi oxumaq lazim, konstruktora bir baxis kifayet etmir.
- **Spring-siz istifade olunmaz**: Eger bu sinfi Spring-dan xaric istifadə etmek istesen, field-ler null qalir.

### 8. @Qualifier və @Primary — birdən çox implementasiya

Eger bir interface-in bir neçə implementasiyasi var, Spring hansını sececeyini bilmir:

```java
public interface PaymentGateway {
    void charge(BigDecimal amount);
}

@Service
public class StripeGateway implements PaymentGateway { ... }

@Service
public class PayPalGateway implements PaymentGateway { ... }

// Spring Error: "No qualifying bean, 2 match: stripeGateway, payPalGateway"
@Service
public class OrderService {
    public OrderService(PaymentGateway gateway) { ... }  // Hansi?
}
```

Hell 1: `@Primary` ile default sec:

```java
@Service
@Primary  // Default bu olacaq
public class StripeGateway implements PaymentGateway { ... }

@Service
public class PayPalGateway implements PaymentGateway { ... }

// OrderService avtomatik StripeGateway alir
```

Hell 2: `@Qualifier` ile dəqiq sec:

```java
@Service
public class OrderService {
    public OrderService(@Qualifier("payPalGateway") PaymentGateway gateway) {
        // PayPalGateway gelir
    }
}
```

### 9. @Value — konfiqurasiya deyerleri injection

`@Value` `application.properties`-dan deyerleri sinfe yukleyir:

```properties
# application.properties
app.name=DemoApp
app.version=1.0.0
app.admin.email=admin@example.com
app.max-upload-size=10485760
app.feature.dark-mode=true
```

```java
@Service
public class AppInfoService {

    @Value("${app.name}")
    private String appName;

    @Value("${app.version}")
    private String version;

    @Value("${app.admin.email}")
    private String adminEmail;

    @Value("${app.max-upload-size}")
    private long maxUploadSize;

    @Value("${app.feature.dark-mode}")
    private boolean darkModeEnabled;

    public String describe() {
        return String.format("%s v%s, admin: %s", appName, version, adminEmail);
    }
}
```

Default deyer vermək mumkundur — eger property yoxdursa:

```java
@Value("${app.port:8080}")  // Property yoxdursa, 8080 istifade olunur
private int port;

@Value("${app.title:My Application}")
private String title;
```

### 10. @Value constructor ilə

Field injection-ın tək qəbul olunan yeri bəzən `@Value` sayılır, çünki config dəyərləri dəyişməz olur. Amma yenə də constructor daha yaxşıdır:

```java
@Service
public class EmailService {

    private final String smtpHost;
    private final int smtpPort;
    private final String fromAddress;

    public EmailService(@Value("${mail.smtp.host}") String smtpHost,
                        @Value("${mail.smtp.port}") int smtpPort,
                        @Value("${mail.from}") String fromAddress) {
        this.smtpHost = smtpHost;
        this.smtpPort = smtpPort;
        this.fromAddress = fromAddress;
    }
}
```

### 11. @Value ilə SpEL (Spring Expression Language)

`${...}` property-dən dəyər alır, `#{...}` isə ifadə (expression) hesablayır:

```java
// Ifadəli SpEL
@Value("#{systemProperties['user.home']}")
private String userHome;

@Value("#{T(java.lang.Math).random() * 100}")
private double randomNumber;

@Value("#{'${app.supported.locales}'.split(',')}")
private List<String> supportedLocales;

@Value("#{${app.max-connections} * 2}")
private int doubledConnections;
```

### 12. @ConfigurationProperties — tovsiye olunan yol

Çoxlu property üçün `@Value` əvəzinə `@ConfigurationProperties` daha yaxşıdır:

```properties
# application.properties
app.mail.host=smtp.gmail.com
app.mail.port=587
app.mail.username=user@gmail.com
app.mail.password=secret
app.mail.from=noreply@example.com
```

```java
@Component
@ConfigurationProperties(prefix = "app.mail")
public class MailProperties {
    private String host;
    private int port;
    private String username;
    private String password;
    private String from;

    // getter/setter
}

@Service
public class MailService {
    private final MailProperties properties;

    public MailService(MailProperties properties) {
        this.properties = properties;
    }

    public void send(String to, String subject) {
        System.out.println("SMTP: " + properties.getHost() + ":" + properties.getPort());
    }
}
```

Bu yanaşma daha type-safe və test etməsi asandır.

## Laravel/PHP-de istifadesi

### 1. Laravel Constructor Injection

Laravel Service Container-i də Spring kimi constructor injection-u dəstəkləyir. Hətta daha sadə — annotasiya lazım deyil:

```php
<?php
// app/Services/OrderService.php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Services\PaymentGateway;
use App\Services\EmailService;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly PaymentGateway $gateway,
        private readonly EmailService $emailService
    ) {}

    public function placeOrder(array $data): Order
    {
        $order = $this->repository->create($data);
        $this->gateway->charge($order->total);
        $this->emailService->send($order->email, 'Sifariş təsdiqləndi');
        return $order;
    }
}
```

`readonly` keyword PHP 8.1+-dan gəlir — Java-dakı `final` kimi işləyir.

### 2. Laravel-də config injection

Laravel-də `@Value` əvəzinə `config()` helper-i var:

```php
class EmailService
{
    private string $host;
    private int $port;
    private string $from;

    public function __construct()
    {
        $this->host = config('mail.host');
        $this->port = config('mail.port');
        $this->from = config('mail.from.address');
    }
}
```

Yaxud konfiq obyekti olaraq constructor-a verilir:

```php
// config/services.php
return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
];

// AppServiceProvider
$this->app->bind(StripeGateway::class, function ($app) {
    return new StripeGateway(
        config('services.stripe.key'),
        config('services.stripe.secret')
    );
});
```

### 3. Bir neçə implementation

Spring-deki `@Primary` / `@Qualifier`-in Laravel ekvivalenti:

```php
// AppServiceProvider::register()

// Default bind
$this->app->bind(PaymentGateway::class, StripeGateway::class);

// Kontekstual bind - OrderService ucun PayPal
$this->app->when(OrderService::class)
    ->needs(PaymentGateway::class)
    ->give(PayPalGateway::class);
```

### 4. Method injection

Laravel controller metodlarinda da DI isleyir — bu Spring-də yoxdur:

```php
class ReportController extends Controller
{
    public function generate(Request $request, ReportService $service): JsonResponse
    {
        // $service avtomatik yaradilir ve verilir
        return response()->json($service->generate($request->input('type')));
    }
}
```

### 5. app() və resolve() helper-leri

Laravel-de container-e istənilən yerden erisim var:

```php
$service = app(UserService::class);
$service = resolve(UserService::class);

// Parametri ilə
$gateway = app()->makeWith(PaymentGateway::class, ['apiKey' => 'xxx']);
```

Spring-də bu `ApplicationContext.getBean(UserService.class)` ilə mümkündür, amma tövsiyə olunmur.

## Esas Ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Constructor injection** | Beli, tovsiye olunur | Beli, defolt yol |
| **Annotasiya lazim?** | Spring 4.3+ single constructor-da yox | Heç bir annotasiya yox |
| **Field injection** | Mümkun (`@Autowired`), amma anti-pattern | PHP-də mümkün deyil (konstruktor vacib) |
| **Setter injection** | Mumkun (`@Autowired` setter-də) | Mümkün ama nadir |
| **Method injection** | Yox | Beli (controller metodunda) |
| **`final` / `readonly`** | `final` (Java keyword) | `readonly` (PHP 8.1+) |
| **Config inject** | `@Value("${prop}")` | `config('prop')` helper |
| **Grup config** | `@ConfigurationProperties` | Array with `config()` |
| **Birden cox implementasiya** | `@Primary`, `@Qualifier` | Contextual binding `when()->needs()->give()` |
| **Lombok** | `@RequiredArgsConstructor` boilerplate azaldir | `constructor promotion` (PHP 8+) |
| **Compile-time yoxlama** | Beli (Java type-safety) | Yox (runtime error) |
| **Optional asılılıq** | `Optional<T>`, `@Autowired(required=false)` | Null-check, `?Type` |

## Niye Bele Ferqler Var?

### Spring-in yanasmasi

1. **Üç injection üsulu tarixi səbəblərdən**: Spring 2005-dən var və əvvəl XML konfiqurasiya istifadə olurdu. Setter injection XML-də property element ilə əlaqəli idi. Daha sonra field injection annotasiya ilə populyar oldu, amma problemleri göründükdən sonra icma constructor injection-a keçdi.

2. **Final field-lər Java-da vacibdir**: Immutability Java konkurent sistemlərdə thread-safety verir. `final` field thread-safe olduğunu garantiyalayır.

3. **`@Value` ve property system**: Spring-in property sisteminin bir növü var — profiles, property sources, SpEL. Bu Java enterprise dunyasindan gelir, orada konfiqurasiya çox mürəkkəb olur.

4. **Compile-time yoxlama**: Java statik tipli dildir. Spring-in DI sistemi compile olunmuş kodda type mismatch-leri yoxlayır — Laravel-də bu yoxdur.

### Laravel-in yanasmasi

1. **PHP-də annotasiya yeni idi**: Laravel annotasiyasiz işləyib cünki PHP annotasiya sintaksisi 8.0-a qədər yoxdu. Type-hint və reflection ilə hər şey hell olunur.

2. **Method injection**: PHP-nin HTTP handler sistemi bir metod çağırışıdır — `Controller::method($request)`. Laravel bu çağırışa əlavə parametrləri (container-dən) qoşa bilər. Spring-de bu arxitektura fərqlidir — controller metodu HTTP işçisi deyil, endpoint-dir.

3. **config() helper**: Laravel helper funksiyalar ekosistemindədir. `config('app.name')` istənilən yerden çağırıla bilər — bu dinamik dillərin üstünlüyüdür.

4. **`readonly` gec gəldi**: PHP 8.1-də `readonly` property-lər əlavə olundu. Ondan əvvəl final field konsepti yox idi, ona görə immutability PHP-də az diqqət çəkdiyi bir mövzu idi.

## Ümumi Sehvler (Beginner Traps)

### 1. Field injection ilə test etmek

```java
@Service
public class BadService {
    @Autowired
    private UserRepository repo;  // SEHV
}

// Test:
class BadServiceTest {
    @Test
    void test() {
        BadService service = new BadService();  // repo = null
        service.doSomething();  // NullPointerException!
    }
}
```

Hel yolu: constructor injection istifadə et.

### 2. `final` yazmamaq

```java
@Service
public class UserService {
    private UserRepository repository;  // final yox!

    public UserService(UserRepository repository) {
        this.repository = repository;
    }

    // Kimsə sehvən field-i deyise biler
    public void dangerous() {
        this.repository = null;  // Olar, cunki final deyil
    }
}
```

Hel yolu: Constructor injection-da hemise `final` yaz.

### 3. Iki konstruktor və `@Autowired` unutmaq

```java
@Service
public class UserService {
    private final UserRepository repo;

    // Spring hansini sececeyini bilmir!
    public UserService(UserRepository repo) {
        this.repo = repo;
    }

    public UserService(UserRepository repo, String role) {
        this.repo = repo;
    }
}
// Error: Multiple constructors, mark one with @Autowired
```

Hel yolu: Istifade olunacaq konstruktora `@Autowired` yaz və ya yalniz bir konstruktor saxla.

### 4. Circular dependency

```java
@Service
public class ServiceA {
    public ServiceA(ServiceB b) { ... }
}

@Service
public class ServiceB {
    public ServiceB(ServiceA a) { ... }
}
// BeanCurrentlyInCreationException
```

Hel yolu: Kodu refaktor et - cox vaxt muddete ucuncu service (ServiceC) lazim olur, hem A hem B orada inject olur.

### 5. `@Value`-də property olmur

```java
@Value("${app.missing-property}")
private String value;
// Error: Could not resolve placeholder 'app.missing-property'
```

Hel yolu: default deyer ver — `@Value("${app.missing-property:defaultValue}")`.

### 6. `@Value` ile boolean sehv oxunur

```properties
app.feature.enabled=TRUE
```

```java
@Value("${app.feature.enabled}")
private Boolean enabled;  // DUZGUN - true olur
```

Amma:

```properties
app.feature.enabled=yes
```

```java
@Value("${app.feature.enabled}")
private Boolean enabled;  // false! - cunki "yes" boolean deyil
```

Hel yolu: Properties-də `true/false` istifade et.

### 7. Interface injection-da implementasiya secilmir

```java
public interface Cache { ... }

@Service
public class RedisCache implements Cache { ... }

@Service
public class InMemoryCache implements Cache { ... }

@Service
public class OrderService {
    public OrderService(Cache cache) { ... }  // Hansi?
}
// NoUniqueBeanDefinitionException
```

Hel yolu: `@Primary` və ya `@Qualifier` istifade et.

## Mini Musahibe Suallari

### 1. Niye constructor injection field injection-dan daha yaxşıdır?

Dörd səbəb:

1. **Immutability**: `final` field istifade etmek mümkündür - obyekt yaradildıqdan sonra asılılıq dəyişmir.
2. **Fail-fast**: Eger asılılıq tapılmasa, tətbiq start olarkən səhv verir - runtime-da yox.
3. **Test etmək asandır**: Spring olmadan sadəcə `new Service(mock1, mock2)` yazıb test yaza bilirsen.
4. **Gizli asılılıqlar yoxdur**: Konstruktora baxıb sinfin nəyə muhtaç olduğunu anlayırsan. Field injection-da çoxlu `@Autowired private` dəyişənləri gizlenir.

Əlavə olaraq, çox asılılıq olduqda konstruktor kobud görünür — bu, sinfin həddindən artiq məsuliyyətinin (SRP pozuntusu) əlamətidir. Field injection bu problem gizlədir.

### 2. `@Autowired` nə vaxt lazımdir və nə vaxt yox?

Spring 4.3+-dan başlayaraq:

- **Tək konstruktor**: `@Autowired` **lazım deyil** — Spring avtomatik tapır.
- **Bir neçə konstruktor**: İstifadə olunacaq birisinə `@Autowired` yaz.
- **Setter injection**: `@Autowired` setter-ə yazmaq lazimdir.
- **Field injection**: `@Autowired` field-ə mecburi (amma field injection istifade etmə).
- **Optional asılılıq**: `@Autowired(required = false)` ile null ola biler ya `Optional<T>` tipini istifade ele.

Qisaca: modern constructor injection-da `@Autowired` yazmaq old school görünür — amma işləyir.

### 3. `@Value` və `@ConfigurationProperties` arasında hansı daha yaxşıdır?

**`@Value`** — bir və ya iki property ucun, sürətli.

**`@ConfigurationProperties`** — bir neçə əlaqəli property ucun (meselen butun `app.mail.*`):

Üstunlukler `@ConfigurationProperties`-in:

- **Type-safe**: IDE-də autocomplete var, typo olsa sehv çıxır.
- **Validation**: `@Validated`-la birleşdirilende `@NotNull`, `@Min` validasiya qaydaları isləyir.
- **Metadata**: IDE-də property-lərin izahı avtomatik gorsən.
- **Test etmeyi asan**: Sadə POJO kimi test olunur.

Minus: bir neçə sinif yazmalı olursan (properties class + @EnableConfigurationProperties).

Prakikada: 1-3 property üçün `@Value`, bundan çoxu üçün `@ConfigurationProperties`.
