# Spring Profiles & Environment vs Laravel Config — Dərin Müqayisə

## Giriş

Hər tətbiqin bir neçə mühiti olur: local, dev, staging, prod. Hər mühitdə DB URL, API key, log səviyyə, feature flag fərqli ola bilər. **Spring Profiles** və Spring-in `Environment` abstraction-u bu problemi formal şəkildə həll edir: bean-ları yalnız müəyyən profil aktiv olanda qeyd etmək, property-ləri prioritetə görə seçmək, tip-güvənli `@ConfigurationProperties` binding etmək. **Laravel** isə daha sadə yol seçir: `.env` faylı, `config/*.php` array-ları, `APP_ENV` dəyişəni və `config:cache` artisan əmri.

Bu sənəddə `@Profile` annotation-unun bütün sintaksisini (sadə, OR `|`, NOT `!`, AND `&`), `spring.profiles.active` / `default` / `include` fərqini, Boot 2.4+ profile group-larını, property resolution sırasını (cmd → env → YAML profiles → YAML default), `@PropertySource`, `@ConfigurationProperties` + validation, `@Value` vs `@ConfigurationProperties` seçimini, Environment abstraction-unu araşdırırıq. Laravel tərəfində `.env`, `env()` vs `config()` fərqini, `config:cache` effect-ini, `App::environment()` checker-ini, paket config merging-ini göstəririk.

---

## Spring-də istifadəsi

### 1) @Profile annotation — sintaksis

```java
// Sadə — yalnız "dev" profili aktiv olanda
@Configuration
@Profile("dev")
public class DevDatabaseConfig {
    @Bean
    public DataSource dataSource() {
        return new EmbeddedDatabaseBuilder().setType(H2).build();
    }
}

// OR — dev VƏ YA staging
@Profile("dev | staging")
public class NonProdConfig { }

// NOT — prod-dan başqa hamı
@Profile("!prod")
public class DebugToolsConfig { }

// AND — dev VƏ debug eyni zamanda aktiv
@Profile("dev & debug")
public class DevDebugConfig { }

// Mürəkkəb ifadə
@Profile("(dev | staging) & !ci")
public class PreviewConfig { }
```

`@Profile` metod səviyyəsində də istifadə oluna bilər:

```java
@Configuration
public class CacheConfig {

    @Bean
    @Profile("prod")
    public CacheManager prodCache() {
        return new RedisCacheManager(redisConnectionFactory());
    }

    @Bean
    @Profile("!prod")
    public CacheManager localCache() {
        return new ConcurrentMapCacheManager();
    }
}
```

### 2) Profile aktivləşdirmə

Üç yol var:

```bash
# 1. Komanda xətti
java -Dspring.profiles.active=prod,metrics -jar app.jar

# 2. Environment variable
export SPRING_PROFILES_ACTIVE=prod,metrics
java -jar app.jar

# 3. application.yml
spring:
  profiles:
    active: prod,metrics
```

**`default` profili** — heç bir profil aktiv olmayanda avtomatik aktiv olur:

```yaml
spring:
  profiles:
    default: local           # `-Dspring.profiles.active=...` verilməyibsə
```

**`include`** — aktiv profilə əlavə profil(lər) qoşur:

```yaml
spring:
  profiles:
    active: prod
    include:                 # prod aktivdirsə, bunlar da aktivləşir
      - metrics
      - auditing
```

### 3) Profile groups (Boot 2.4+)

Bir profil aktivləşəndə başqalarını da aktivləşdirmək üçün qrup:

```yaml
spring:
  profiles:
    group:
      prod:                  # `prod` aktiv olanda, bunlar da aktiv olur
        - metrics
        - auditing
        - cloud
      dev:
        - debug
        - h2
```

```bash
java -Dspring.profiles.active=prod -jar app.jar
# Aktiv: prod, metrics, auditing, cloud
```

### 4) Profile-specific configuration files

Spring Boot avtomatik olaraq `application-{profile}.yml` fayllarını yükləyir:

```
src/main/resources/
├── application.yml          ← həmişə yüklənir
├── application-dev.yml      ← dev profili aktivdirsə
├── application-staging.yml
├── application-prod.yml
└── application-prod-metrics.yml   ← prod + metrics hər ikisi aktivdirsə
```

```yaml
# application.yml (ortaq)
spring:
  application:
    name: order-service
  datasource:
    username: app
server:
  port: 8080

# application-dev.yml
spring:
  datasource:
    url: jdbc:h2:mem:test
    password: dev
logging:
  level:
    com.example: DEBUG

# application-prod.yml
spring:
  datasource:
    url: jdbc:postgresql://db.prod.local:5432/orders
    password: ${DB_PASSWORD}          # env var
logging:
  level:
    com.example: INFO
```

### 5) Property resolution sırası (yuxarı qalib gəlir)

Spring Boot yüzlərlə property source-u dəyərləndirir. Əsas prioritet (yüksəkdən aşağıya):

```
1. @TestPropertySource (test-də)
2. Komanda xətti arqumentləri (--server.port=9000)
3. JAVA_OPTS / SPRING_APPLICATION_JSON
4. Servlet konfiqurasiya parametrləri
5. OS environment variables (SERVER_PORT=9000)
6. Java System properties (System.getProperty)
7. JNDI properties
8. application-{profile}.yml (profil-spesifik, xaricdəki)
9. application.yml (xaricdəki)
10. application-{profile}.yml (jar daxilində)
11. application.yml (jar daxilində)
12. @PropertySource annotation-ları
13. Default properties (SpringApplication.setDefaultProperties)
```

Bu deməkdir ki, `SERVER_PORT=9000` environment variable-ı `application.yml`-dəki `server.port: 8080`-i əvəz edir. Environment variable-lar kebab-case → `.` çevirməyə icazə verir: `SERVER_PORT` = `server.port`, `SPRING_DATASOURCE_URL` = `spring.datasource.url`.

### 6) @Value ilə property oxumaq

```java
@Component
public class MailService {

    @Value("${mail.smtp.host}")
    private String smtpHost;

    @Value("${mail.smtp.port:25}")            // default 25
    private int smtpPort;

    @Value("${mail.from}")
    private String fromAddress;

    @Value("#{'${mail.admins}'.split(',')}")  // SpEL ilə list
    private List<String> admins;
}
```

`@Value` sadədir, amma zəiflikləri:
- Hər property üçün ayrıca annotation
- Validation yoxdur
- Tiplər runtime-da konvert olunur
- Class bütöv olaraq configuration-u təmsil etmir

### 7) @ConfigurationProperties — tip-güvənli

```java
@ConfigurationProperties(prefix = "mail")
@Validated
public class MailProperties {

    @NotBlank
    private String host;

    @Min(1) @Max(65535)
    private int port = 25;

    @NotBlank @Email
    private String from;

    private List<@Email String> admins = new ArrayList<>();

    private Smtp smtp = new Smtp();

    // getter/setter...

    public static class Smtp {
        private boolean starttls = true;
        private Duration timeout = Duration.ofSeconds(10);
        // getter/setter...
    }
}
```

Qeydiyyat:

```java
@Configuration
@EnableConfigurationProperties(MailProperties.class)
public class MailConfig { }

// və ya main class üzərində
@SpringBootApplication
@ConfigurationPropertiesScan
public class App { }
```

application.yml:

```yaml
mail:
  host: smtp.gmail.com
  port: 587
  from: noreply@example.com
  admins:
    - alice@example.com
    - bob@example.com
  smtp:
    starttls: true
    timeout: 30s              # Duration — "30s", "5m", "PT1H"
```

### 8) Record kimi @ConfigurationProperties (Boot 3)

Boot 3-də immutable record-lar `@ConfigurationProperties` kimi yazıla bilər:

```java
@ConfigurationProperties(prefix = "stripe")
public record StripeProperties(
    @NotBlank String apiKey,
    @NotBlank String webhookSecret,
    Duration timeout,
    Map<String, String> tagMapping,
    List<Plan> plans
) {
    public record Plan(String id, int priceCents) { }
}
```

application.yml:

```yaml
stripe:
  api-key: sk_test_xxx
  webhook-secret: whsec_yyy
  timeout: 15s
  tag-mapping:
    premium: gold
    basic: silver
  plans:
    - id: plan_mo
      price-cents: 1999
    - id: plan_yr
      price-cents: 19999
```

### 9) @PropertySource — ayrıca fayl

```java
@Configuration
@PropertySource("classpath:integration.properties")
@PropertySource(value = "file:/etc/myapp/override.properties",
                ignoreResourceNotFound = true)
public class IntegrationConfig { }
```

**Diqqət:** `@PropertySource` YAML dəstəkləmir (properties fayl lazımdır). YAML üçün `application.yml` və profile faylları kifayətdir.

### 10) Environment abstraction

```java
@Component
public class FeatureService {

    private final Environment env;

    public FeatureService(Environment env) {
        this.env = env;
    }

    public boolean isFeatureEnabled(String feature) {
        return env.getProperty("features." + feature, Boolean.class, false);
    }

    public String[] activeProfiles() {
        return env.getActiveProfiles();
    }

    public boolean isProd() {
        return env.acceptsProfiles(Profiles.of("prod"));
    }
}
```

### 11) @Value vs @ConfigurationProperties — nə vaxt hansı?

| `@Value` | `@ConfigurationProperties` |
|---|---|
| Bir-iki property | Bir qrup bağlı property |
| SpEL dəstəkləyir | Yalnız placeholder |
| Validation yox | `@Validated`, `@NotNull`, `@Email` |
| Binding relaxed deyil | Relaxed binding (my-prop = myProp = my_prop) |
| Test-də override çətin | `@TestPropertySource` ilə asan |
| Mutable | Record ilə immutable mümkün |

**Qayda:** 2-dən çox property varsa və ya structure-lu (nested) dəyişən varsa `@ConfigurationProperties` istifadə et.

### 12) Placeholder ilə property expansion

```yaml
app:
  version: 1.2.3
  name: ${spring.application.name}     # digər property-dən al
  greeting: Hello, ${user.name:Guest}  # default Guest
  db-url: ${DB_URL:jdbc:h2:mem:test}   # env var və ya default
```

### 13) Real production application.yml

```yaml
# application.yml
spring:
  application:
    name: order-service
  profiles:
    active: ${APP_ENV:local}
    group:
      prod:
        - metrics
        - auditing
  datasource:
    hikari:
      maximum-pool-size: ${DB_POOL_MAX:10}
      connection-timeout: 5000

app:
  order:
    max-items: 100
    currency: USD
    default-ttl: 24h

# application-local.yml
spring:
  datasource:
    url: jdbc:h2:mem:orders
    username: sa
    password: ""
logging:
  level:
    com.example.orders: DEBUG

# application-prod.yml
spring:
  datasource:
    url: jdbc:postgresql://db.prod:5432/orders
    username: ${DB_USER}
    password: ${DB_PASSWORD}
logging:
  level:
    root: INFO
    com.example.orders: INFO

# application-metrics.yml (grupun bir hissəsi)
management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,prometheus
  metrics:
    export:
      prometheus:
        enabled: true
```

### 14) Profile-aware bean factories

```java
@Configuration
public class StorageConfig {

    @Bean
    @Profile("!cloud")
    public Storage localStorage() {
        return new LocalFileStorage("/var/app/uploads");
    }

    @Bean
    @Profile("cloud")
    public Storage s3Storage(S3Properties props) {
        return new S3Storage(props.bucket(), props.region());
    }
}
```

---

## Laravel-də istifadəsi

### 1) .env faylı — environment variables

Laravel-də hər mühit üçün `.env` faylı var. Bu fayl git-ə commit edilmir (yalnız `.env.example` commit olunur).

```dotenv
# .env
APP_NAME="Order Service"
APP_ENV=local                  # Spring `spring.profiles.active`-ə bənzər
APP_KEY=base64:xxx
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=orders
DB_USERNAME=root
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_yyy
```

`.env.production`, `.env.staging` kimi ayrıca fayllar istifadə oluna bilər, amma adətən deployment-da düzgün `.env` konteyner-ə inject olunur.

### 2) env() helper vs config() helper

**`env()`** — yalnız `config/*.php` fayllarında istifadə etməli!

```php
// config/database.php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
        ],
    ],
];
```

**`config()`** — hər yerdə istifadə oluna bilər:

```php
// Controller, service, job və s.
$dbHost = config('database.connections.mysql.host');
$stripeKey = config('services.stripe.key');

// Nested key
$timeout = config('services.stripe.timeout', 30);   // default 30
```

**Niyə belə qayda?** Çünki production-da `php artisan config:cache` əmri `.env` fayllarını oxumur — bütün config array-ları bir cache faylına yığılır. `env()` yalnız cache yox halda işləyir. `config()` isə cache oxunsa da, oxunmasa da işləyir.

### 3) config:cache effect

```bash
php artisan config:cache      # prod-da deploy zamanı
php artisan config:clear      # dev-də
```

`config:cache` nə edir:
1. `config/*.php` fayllarını yığır
2. Nəticəni `bootstrap/cache/config.php` faylına yazır
3. `.env` faylı yenidən oxunmur

```php
// BAD — config:cache-dən sonra həmişə null qaytarır!
class OrderService {
    public function __construct() {
        $this->apiKey = env('STRIPE_SECRET');   // YANLIŞ
    }
}

// GOOD
class OrderService {
    public function __construct() {
        $this->apiKey = config('services.stripe.secret');   // DÜZGÜN
    }
}
```

### 4) APP_ENV və App::environment()

```php
use Illuminate\Support\Facades\App;

if (App::environment('production')) {
    // yalnız prod-da
}

if (App::environment(['local', 'staging'])) {
    // local və ya staging
}

if (App::isLocal()) { /* ... */ }
if (App::isProduction()) { /* ... */ }

// Aktiv mühit
$env = App::environment();      // "local", "production" və s.
```

Spring-də analoji: `env.acceptsProfiles(Profiles.of("prod"))`.

### 5) config/services.php — əlaqəli API-lər

```php
// config/services.php
return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'slack' => [
        'webhook' => env('SLACK_WEBHOOK'),
    ],
];
```

### 6) Conditional config based on environment

Laravel-də "profile" yoxdur, amma conditional config yazmaq olar:

```php
// config/cache.php
return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
        'array' => [
            'driver' => 'array',
        ],
    ],
];

// config/app.php
return [
    'debug' => env('APP_DEBUG', false),

    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,

        // Yalnız local-da
        ...(env('APP_ENV') === 'local' ? [
            App\Providers\DevToolsServiceProvider::class,
        ] : []),
    ])->toArray(),
];
```

### 7) Per-env service providers

AppServiceProvider-də mühitə görə bind et:

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    if ($this->app->environment('local')) {
        $this->app->register(TelescopeServiceProvider::class);
    }

    $this->app->singleton(PaymentGateway::class, function ($app) {
        return match ($app->environment()) {
            'production', 'staging' => new StripeGateway(
                config('services.stripe.secret')
            ),
            default => new FakeGateway(),     // local, testing
        };
    });
}
```

### 8) Type-safe config via DTOs (custom pattern)

Laravel `@ConfigurationProperties`-a tam ekvivalent verir, amma əl ilə yazmaq mümkündür:

```php
namespace App\Config;

final readonly class StripeConfig
{
    public function __construct(
        public string $apiKey,
        public string $webhookSecret,
        public int $timeoutSeconds,
        public array $plans,
    ) {}

    public static function fromConfig(): self
    {
        $cfg = config('services.stripe');

        return new self(
            apiKey: $cfg['secret'] ?? throw new \RuntimeException('STRIPE_SECRET missing'),
            webhookSecret: $cfg['webhook_secret'] ?? '',
            timeoutSeconds: (int) ($cfg['timeout'] ?? 30),
            plans: $cfg['plans'] ?? [],
        );
    }
}

// AppServiceProvider::register
$this->app->singleton(StripeConfig::class, fn () => StripeConfig::fromConfig());
```

**`spatie/laravel-data`** paketi bunu daha asanlaşdırır.

### 9) Package config merging

Paket yazan tərtibatçı default config verir:

```php
// packages/my-package/src/MyServiceProvider.php
class MyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Paket default-larını user config-lə birləşdir
        $this->mergeConfigFrom(
            __DIR__ . '/../config/my-package.php',
            'my-package'
        );
    }

    public function boot(): void
    {
        // User `php artisan vendor:publish` edəndə kopyalansın
        $this->publishes([
            __DIR__ . '/../config/my-package.php' => config_path('my-package.php'),
        ], 'my-package-config');
    }
}
```

Spring-də bunun ekvivalenti auto-configuration + `@ConditionalOnProperty` + default application.yml-dir.

### 10) .env.testing və testing mühiti

PHPUnit testlərində Laravel `APP_ENV=testing` avtomatik təyin edir. `.env.testing` faylı varsa, onu istifadə edir.

```dotenv
# .env.testing
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
```

### 11) Config validation (paket lazımdır)

Laravel default-da config validation təklif etmir. `laravel/framework` 11-dən `decrypt()` və `encrypt()` environment üçün artıb, amma strong validation üçün paket:

```bash
composer require spatie/laravel-data
```

```php
use Spatie\LaravelData\Data;

class MailConfigData extends Data {
    public function __construct(
        #[Required, StringType]
        public string $host,

        #[Required, IntegerType, Between(1, 65535)]
        public int $port,

        #[Required, Email]
        public string $from,
    ) {}
}

// AppServiceProvider
$this->app->singleton(MailConfigData::class, function () {
    return MailConfigData::from(config('mail'));
});
```

### 12) Tam nümunə — StripeService mühitə görə

```php
// config/services.php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'timeout' => env('STRIPE_TIMEOUT', 30),
],

// app/Services/PaymentService.php
class PaymentService
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly bool $debug,
    ) {}

    public function charge(int $cents, string $token): string
    {
        if ($this->debug) {
            logger()->debug('Charging', ['cents' => $cents]);
        }
        return $this->client->charges->create([
            'amount' => $cents,
            'source' => $token,
        ])->id;
    }
}

// AppServiceProvider::register
$this->app->singleton(PaymentService::class, function ($app) {
    return new PaymentService(
        client: new StripeClient(config('services.stripe.secret')),
        debug: $app->environment('local', 'staging'),
    );
});
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Mühit adı | Profile (çoxsaylı, aktiv olar) | `APP_ENV` (tək dəyər) |
| Aktivləşdirmə | `spring.profiles.active=prod,metrics` | `APP_ENV=production` |
| Çoxlu profil | Bəli, siyahı | Xeyr, tək dəyər |
| Conditional bean | `@Profile("prod")` | `if ($this->app->environment(...))` |
| Config fayl | `application-{profile}.yml` | `config/*.php` statik |
| Config cache | Həmişə yaddaşda | `config:cache` açıq deyilir |
| Type-safe config | `@ConfigurationProperties` | Əl ilə DTO / `spatie/data` |
| Validation | `@Validated` + JSR-303 | Default yoxdur |
| Relaxed binding | `MY_VAR` = `my.var` = `myVar` | Yox — environ açıq olaraq açılır |
| Placeholder | `${my.var:default}` | `env('MY_VAR', 'default')` |
| Environment API | `Environment.getProperty()` | `config()`, `env()` |
| Default profile | `spring.profiles.default` | Yoxdur |
| Profile groups | `spring.profiles.group.prod=metrics,auditing` | Yoxdur |
| Profile expression | `dev | staging`, `!prod`, `dev & debug` | Yalnız `if/match` |
| Package config | `@AutoConfiguration` + default YAML | `mergeConfigFrom()` + `publishes()` |
| Hot reload | `spring-boot-devtools` | Yox (config:clear lazımdır) |
| Test override | `@TestPropertySource`, `@DynamicPropertySource` | `.env.testing`, `Config::set()` |

---

## Niyə belə fərqlər var?

**Çoxlu profil vs tək env.** Spring bean-ları compile-time-da yazıb runtime-da profilə görə seçir. Bir anda `prod + metrics + auditing` ola bilər — hər biri ayrıca "axını" bean-lar gətirir. Laravel bu modelə ehtiyac hiss etmir: ya `local`, ya `production` — başqa "layer"-lər açıq `if`-lə yazılır.

**Config cache.** PHP hər sorğu üçün yenidən boot olur (Octane olmasa). Config-i hər dəfə disk-dən oxumaq yavaşdır — buna görə `config:cache` var. Spring-də bu problem yoxdur: tətbiq bir dəfə boot olunur, yaddaşda qalır.

**`env()` qaydası.** `env()` yalnız `config/*.php`-də işləyir çünki prod-da `.env` oxunmur. Bu sual çox soruşulur — unutmaq həqiqi bug yaradır. Spring-də bütün property source-lar həmişə aktivdir, belə "trap" yoxdur.

**Type-safe config.** Spring `@ConfigurationProperties` + validation Java tip sistemindən asanlıqla istifadə edir. PHP 8.2 readonly classes gəldikdən sonra Laravel də immutable DTO yaza bilir, amma framework default-da yoxdur.

**Relaxed binding.** `SERVER_PORT=9000` = `server.port=9000` Spring-də avtomatik. Laravel-də isə `.env`-də `SERVER_PORT` açıq şəkildə `config()` array-ına map olunmalıdır.

**Profile expression gücü.** `@Profile("(dev | staging) & !ci")` — bu kompleksliyi Laravel-də closure və `if`-lə yazmaq olar, amma Spring annotation kimi elan edici idiom verir.

**Test profili.** Spring `@TestPropertySource` və `@ActiveProfiles` ilə test üçün ayrıca profil aktivləşdirir. Laravel `.env.testing` və `TestCase::setUp`-də `Config::set()` istifadə edir — daha imperative.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `@Profile` ifadələri: OR, NOT, AND, mötərizə
- Profile groups (`spring.profiles.group.prod`)
- `spring.profiles.include`
- `spring.profiles.default`
- `application-{profile}.yml` avtomatik yüklənmə
- `@ConfigurationProperties` + `@Validated` + JSR-303
- Record kimi `@ConfigurationProperties` (Boot 3)
- Relaxed binding (env var → property key)
- `Duration`, `DataSize` avtomatik parse (`30s`, `5MB`)
- `Environment.acceptsProfiles(Profiles.of(...))`
- `@DynamicPropertySource` — Testcontainers ilə
- `@ConditionalOnProperty` — property-ə görə bean-ın aktivliyi
- `@ConditionalOnMissingBean`, `@ConditionalOnClass` auto-config
- `@PropertySource` — custom properties fayl
- `ApplicationListener<EnvironmentPreparedEvent>` — property-lər yüklənəndə

**Yalnız Laravel-də:**
- `.env` faylı + `.env.example` paradigması
- `php artisan config:cache` — bütün config-i tək faylda
- `php artisan config:clear`
- `env()` / `config()` ayrımı
- `App::environment()`, `App::isLocal()`, `App::isProduction()`
- `vendor:publish` — paket config-ini kopyala
- `mergeConfigFrom()` — paket default-larını birləşdir
- `.env.testing` avtomatik test-də
- Artisan `php artisan tinker` ilə runtime `config()` yoxlamaq
- Closure-based service provider bind (env-ə görə)

---

## Best Practices

1. **`env()` yalnız `config/*.php`-də.** Production-da `config:cache` edirsənsə, kod daxilində `env()` null qaytarır.
2. **`@ConfigurationProperties` üstünlük ver `@Value`-dən.** Validation, test, refactor asanlaşır.
3. **Spring-də yalnız konkret `@Profile` istifadə et.** `!prod` daha aydındır `@Profile("!prod")` kimi — `dev`, `ci`, `local` hamısını əhatə edir.
4. **Laravel-də `.env` commit etmə.** `.env.example` commit et — yeni developer hansı dəyişənlər lazım olduğunu görsün.
5. **Spring Boot-da secret-ləri env var-a qoy.** `application-prod.yml`-də `${DB_PASSWORD}` istifadə et, real parolu YAML-a yazma.
6. **Laravel-də `config/services.php`** xarici API-lər üçün — `config/app.php`-ni şişirtmə.
7. **Laravel `config:cache`** yalnız prod-da — local-da config dəyişdirəndə yorulur.
8. **Test mühitində** Spring: `@ActiveProfiles("test")` + `@TestPropertySource`. Laravel: `.env.testing` + `TestCase::setUp`-də `Config::set()`.
9. **Secret-ləri Vault-a qoy.** Spring Cloud Config Server + Vault, Laravel-də env var + K8s secret.
10. **Type-safe DTO yarada bilirsənsə yarat.** Spring: `record` + `@ConfigurationProperties`. Laravel: `readonly class` + `fromConfig()` factory.

---

## Yekun

Spring Profiles + Environment güclü, formal, yüksək tip təhlükəsizliyi ilə gələn bir sistemdir. Bir anda çoxlu profil aktiv ola bilər (`prod,metrics,auditing`), bean-lar profilə görə qeyd olunur (`@Profile`), property-lər tip-güvənli inject olunur (`@ConfigurationProperties` + `@Validated`), property source-ları dəqiq prioritet sırasında həll olunur. Laravel isə sadəlik seçir: tək `APP_ENV`, `.env` faylı, `config/*.php` array-ları, `config:cache` optimizasiyası. İkisi də öz ekosistemində yaxşı işləyir.

`env()` vs `config()` qaydası Laravel-də çox unutqan bir bug yaradır — həmişə config-dən oxu. Spring-də isə `@Value` əvəzinə `@ConfigurationProperties` üstünlük ver, validation əlavə et, test asanlaşsın. Hər iki framework-da secret-ləri environment variable və ya secret manager-a qoy — faylda saxlama.
