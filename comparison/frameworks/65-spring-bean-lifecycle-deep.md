# Spring Bean Lifecycle vs Laravel Service Container — Dərin Müqayisə

## Giriş

Hər iki framework-un ürəyində **container** dayanır: Spring-də `ApplicationContext`, Laravel-də `Application` (Service Container). Container obyektlərin necə yaradılacağını, necə inject ediləcəyini və necə məhv ediləcəyini idarə edir. Spring bu sahədə daha geniş və daha "formal" API təklif edir — bean-ın həyat dövrü (lifecycle) bir neçə dəqiq mərhələdən ibarətdir və hər mərhələyə callback bağlamaq mümkündür. Laravel isə daha sadə və dinamik yanaşma seçir: `bind`, `singleton`, `scoped`, `instance`, `resolving` callback-ları kifayət edir.

Bu sənəddə Spring bean-ın tam həyat dövrünü (instantiation → populate → aware → BeanPostProcessor before → init → BeanPostProcessor after → destroy), `BeanFactory` və `ApplicationContext` fərqini, scope-ları (singleton, prototype, request, session, application, websocket, custom), proxy növlərini (JDK dynamic vs CGLIB), `@Configuration` full və lite mode-larını, circular dependency həllini analiz edirik. Laravel tərəfində isə Service Container binding növlərini, `booting`/`booted` callback-larını, facade-ları, tagged binding-ləri və `resolving`/`afterResolving` callback-larını göstəririk.

---

## Spring-də istifadəsi

### 1) BeanFactory vs ApplicationContext

`BeanFactory` — Spring container-in əsas interface-i. Sadəcə bean yaradır, inject edir, scope-u idarə edir. Çox az yaddaş yeyir, amma zəngin funksiya yoxdur.

`ApplicationContext` — `BeanFactory`-ni geniş­ləndirir. Hadisə yayımı (event publishing), internationalization (i18n), resource loading (`ResourceLoader`), `Environment` API — hamısı burada. Real tətbiqlərdə həmişə `ApplicationContext` istifadə olunur.

```java
// Sadə BeanFactory — demək olar ki heç vaxt istifadə etmirik
DefaultListableBeanFactory factory = new DefaultListableBeanFactory();
GenericBeanDefinition def = new GenericBeanDefinition();
def.setBeanClass(UserService.class);
factory.registerBeanDefinition("userService", def);
UserService svc = factory.getBean(UserService.class);

// Real dünyada ApplicationContext
ApplicationContext ctx = SpringApplication.run(MyApp.class, args);
UserService svc = ctx.getBean(UserService.class);
```

### 2) Bean yaradılma mərhələləri — tam siyahı

Spring bir bean yaradanda aşağıdakı ardıcıllıq gedir:

```
1.  Instantiation (new UserService())
2.  Populate properties (@Autowired, @Value sahələrini doldur)
3.  BeanNameAware.setBeanName("userService")
4.  BeanClassLoaderAware.setBeanClassLoader(...)
5.  BeanFactoryAware.setBeanFactory(...)
6.  ApplicationContextAware.setApplicationContext(ctx)
7.  BeanPostProcessor.postProcessBeforeInitialization(bean, name)
8.  @PostConstruct metodu
9.  InitializingBean.afterPropertiesSet()
10. Custom init-method (@Bean(initMethod = "start"))
11. BeanPostProcessor.postProcessAfterInitialization(bean, name)  ← proxy burada yaradılır
12. Bean hazırdır, inject olunur, istifadə edilir
...
13. Container bağlananda: @PreDestroy
14. DisposableBean.destroy()
15. Custom destroy-method (@Bean(destroyMethod = "stop"))
```

Nümunə:

```java
@Component
public class PaymentGateway implements
    BeanNameAware, ApplicationContextAware, InitializingBean, DisposableBean {

    @Autowired
    private HttpClient httpClient;       // 2: populate

    private String beanName;
    private ApplicationContext ctx;

    @Override
    public void setBeanName(String name) {
        this.beanName = name;             // 3
        log.info("Step 3: bean name = {}", name);
    }

    @Override
    public void setApplicationContext(ApplicationContext ctx) {
        this.ctx = ctx;                   // 6
        log.info("Step 6: context set");
    }

    @PostConstruct
    public void init() {
        log.info("Step 8: @PostConstruct");
        // Burada bütün dependency-lər hazırdır
    }

    @Override
    public void afterPropertiesSet() {
        log.info("Step 9: afterPropertiesSet");
    }

    @PreDestroy
    public void shutdown() {
        log.info("Step 13: @PreDestroy");
    }

    @Override
    public void destroy() {
        log.info("Step 14: DisposableBean.destroy()");
    }
}
```

### 3) BeanPostProcessor — bütün bean-ları dəyişdir

`BeanPostProcessor` hər bean üçün iki dəfə çağırılır: init-dən əvvəl və sonra. Bu, AOP proxy-lərin, `@Transactional`, `@Async`, `@Cacheable` funksiyalarının necə işlədiyinin əsasıdır.

```java
@Component
public class AuditingPostProcessor implements BeanPostProcessor {

    @Override
    public Object postProcessBeforeInitialization(Object bean, String name) {
        // init-dən əvvəl — bean hələ orijinaldır
        return bean;
    }

    @Override
    public Object postProcessAfterInitialization(Object bean, String name) {
        // init-dən sonra — proxy yarada bilərik
        if (bean.getClass().isAnnotationPresent(Audited.class)) {
            return Proxy.newProxyInstance(
                bean.getClass().getClassLoader(),
                bean.getClass().getInterfaces(),
                new AuditingHandler(bean)
            );
        }
        return bean;
    }
}
```

### 4) BeanFactoryPostProcessor — definition-ları dəyişdir

`BeanFactoryPostProcessor` bean yaradılmadan əvvəl işləyir və `BeanDefinition`-ı dəyişdirir (class, scope, property-lər). Nümunə: `PropertySourcesPlaceholderConfigurer` `${...}` yer tutucuları burada həll edir.

```java
@Component
public class TenantBeanDefinitionModifier implements BeanFactoryPostProcessor {

    @Override
    public void postProcessBeanFactory(ConfigurableListableBeanFactory factory) {
        BeanDefinition def = factory.getBeanDefinition("dataSource");
        def.setScope("tenant");                     // Custom scope
        def.getPropertyValues().add("url", "jdbc:...");
    }
}
```

**Fərq:** `BeanFactoryPostProcessor` definition-ı dəyişir (yaradılmadan əvvəl). `BeanPostProcessor` instance-ı dəyişir (yaradıldıqdan sonra).

### 5) FactoryBean — öz yaratma məntiqin

`FactoryBean<T>` xüsusi yaratma məntiqi olan bean-lar üçündür. Spring `RedisTemplate`, `JmsTemplate`, `MongoClient` kimi şeyləri bu yolla yaradır.

```java
@Component
public class RedisTemplateFactoryBean implements FactoryBean<RedisTemplate<String, Object>> {

    private final RedisConnectionFactory connectionFactory;

    public RedisTemplateFactoryBean(RedisConnectionFactory cf) {
        this.connectionFactory = cf;
    }

    @Override
    public RedisTemplate<String, Object> getObject() {
        RedisTemplate<String, Object> tpl = new RedisTemplate<>();
        tpl.setConnectionFactory(connectionFactory);
        tpl.setKeySerializer(new StringRedisSerializer());
        tpl.setValueSerializer(new GenericJackson2JsonRedisSerializer());
        tpl.afterPropertiesSet();
        return tpl;
    }

    @Override
    public Class<?> getObjectType() { return RedisTemplate.class; }

    @Override
    public boolean isSingleton() { return true; }
}
```

Dikkat: `ctx.getBean("redisTemplateFactoryBean")` — `RedisTemplate` qaytarır. `ctx.getBean("&redisTemplateFactoryBean")` — `FactoryBean`-in özünü.

### 6) Scope-lar

```java
@Component
@Scope("singleton")                  // Default — bütün tətbiq boyu tək instance
public class AppConfig { }

@Component
@Scope("prototype")                  // Hər getBean() yeni instance
public class Report { }

@Component
@Scope(value = "request", proxyMode = ScopedProxyMode.TARGET_CLASS)
public class RequestContext { }      // Hər HTTP sorğu üçün yeni

@Component
@Scope(value = "session", proxyMode = ScopedProxyMode.TARGET_CLASS)
public class UserCart { }            // Hər HTTP session üçün yeni

@Component
@Scope(value = "application")        // ServletContext səviyyəsində tək
public class GlobalCounter { }

@Component
@Scope(value = "websocket")          // WebSocket session üçün
public class ChatState { }
```

**Proxy mode**: `request`/`session` scope singleton-a inject olunanda problem olur — singleton bir dəfə yaradılır, amma request hər dəfə dəyişir. Həll: `proxyMode = TARGET_CLASS` — singleton əslində proxy alır, proxy hər metod çağırışında aktual request bean-ı tapır.

Custom scope nümunəsi (tenant):

```java
public class TenantScope implements Scope {
    private final Map<String, Map<String, Object>> tenantBeans = new ConcurrentHashMap<>();

    @Override
    public Object get(String name, ObjectFactory<?> factory) {
        String tenant = TenantContext.getCurrentTenant();
        return tenantBeans
            .computeIfAbsent(tenant, k -> new ConcurrentHashMap<>())
            .computeIfAbsent(name, k -> factory.getObject());
    }

    @Override
    public Object remove(String name) { /* ... */ return null; }

    @Override public void registerDestructionCallback(String name, Runnable c) {}
    @Override public Object resolveContextualObject(String key) { return null; }
    @Override public String getConversationId() { return TenantContext.getCurrentTenant(); }
}

@Configuration
public class TenantScopeConfig {
    @Bean
    public static CustomScopeConfigurer tenantScopeConfigurer() {
        CustomScopeConfigurer cfg = new CustomScopeConfigurer();
        cfg.addScope("tenant", new TenantScope());
        return cfg;
    }
}

@Component
@Scope(value = "tenant", proxyMode = ScopedProxyMode.TARGET_CLASS)
public class TenantSettings { }
```

### 7) Proxy növləri — JDK dynamic vs CGLIB

Spring AOP proxy yaradarkən iki variant var:

**JDK dynamic proxy** — target class bir interface implement edirsə, interface-əsaslı proxy yaradılır.

```java
public interface UserService { User findById(Long id); }

@Service
public class UserServiceImpl implements UserService {
    @Transactional
    public User findById(Long id) { ... }
}

// Spring JDK proxy yaradır:
// UserService proxy = (UserService) Proxy.newProxyInstance(...);
```

**CGLIB proxy** — interface yoxdursa, target class-ın subclass-ı yaradılır. `final` class olmaz, `final` metod proxy-lənməz.

```java
@Service
public class OrderService {             // Interface yoxdur
    @Transactional
    public void place(Order o) { ... }
}

// Spring CGLIB proxy yaradır:
// class OrderService$$EnhancerBySpringCGLIB$$abc extends OrderService
```

Boot 2.x-dən bəri `spring.aop.proxy-target-class=true` default-dur — həmişə CGLIB istifadə olunur (daha stabil, interface olsa da class-ı proxy-ləyir).

### 8) @Configuration full-mode (CGLIB) vs lite-mode

```java
@Configuration
public class AppConfig {

    @Bean
    public DataSource dataSource() { return new HikariDataSource(); }

    @Bean
    public JdbcTemplate jdbcTemplate() {
        return new JdbcTemplate(dataSource());   // ← dataSource() çağırışı
    }
}
```

`@Configuration` class-ı CGLIB ilə proxy olunur. `dataSource()` çağırışı həqiqətən yeni instance yaratmır — container-dən alır. Buna görə `jdbcTemplate` və başqa bean-lar eyni `DataSource`-u alır. Bu **full mode**-dur.

**Lite mode** — `@Configuration(proxyBeanMethods = false)`:

```java
@Configuration(proxyBeanMethods = false)     // Boot 2.2+
public class AppConfig {
    @Bean
    public DataSource dataSource() { return new HikariDataSource(); }

    @Bean
    public JdbcTemplate jdbcTemplate(DataSource ds) {
        return new JdbcTemplate(ds);          // Parametr kimi al — inter-bean call yox
    }
}
```

`proxyBeanMethods = false` — CGLIB proxy yaradılmır, startup daha sürətli, yaddaş daha az. Spring Boot-un daxili auto-configuration class-larında bu işarə default-dur.

### 9) Circular dependency — A → B → A

Constructor injection-da circular dependency xətadır:

```java
@Component
public class A {
    public A(B b) { ... }
}

@Component
public class B {
    public B(A a) { ... }     // Exception: BeanCurrentlyInCreationException
}
```

Həll yolları:

**Setter injection** — Spring əvvəl A-nı yaradır (hələ yarımçıq), B-yə inject edir, sonra B-ni A-ya inject edir.

```java
@Component
public class A {
    private B b;
    @Autowired
    public void setB(B b) { this.b = b; }
}
```

**`@Lazy` annotation** — proxy inject olunur, həqiqi bean ilk istifadədə həll olunur.

```java
@Component
public class A {
    public A(@Lazy B b) { ... }      // B proxy-dir, ilk metod çağırışında həll olunur
}
```

**Ən yaxşı:** circular dependency design bug-dur — refactoring ilə qır.

### 10) application.yml tam nümunə

```yaml
spring:
  main:
    lazy-initialization: false
    allow-circular-references: false     # Boot 2.6+ default

  aop:
    proxy-target-class: true             # CGLIB default

logging:
  level:
    org.springframework.beans.factory: DEBUG
    org.springframework.context: DEBUG
```

---

## Laravel-də istifadəsi

### 1) Service Container binding növləri

Laravel-in Service Container-i daha dinamikdir — hər şey runtime-da həll olunur.

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    // bind — hər resolve() yeni instance
    $this->app->bind(PaymentGateway::class, StripeGateway::class);

    // singleton — tətbiq boyu tək instance
    $this->app->singleton(CacheManager::class, function ($app) {
        return new CacheManager($app['config']['cache.default']);
    });

    // scoped — hər HTTP sorğu / job / octane tick üçün tək
    $this->app->scoped(RequestContext::class, function ($app) {
        return new RequestContext($app->make('request'));
    });

    // instance — konkret obyekti ver
    $logger = new Logger('app');
    $this->app->instance('logger', $logger);

    // Interface → implementation
    $this->app->bind(
        \App\Contracts\MailSender::class,
        \App\Services\SmtpMailSender::class
    );
}
```

**Scope cədvəli:**

| Laravel | Spring ekvivalenti |
|---|---|
| `bind` | `@Scope("prototype")` |
| `singleton` | `@Scope("singleton")` |
| `scoped` | `@Scope("request")` / Octane tick |
| `instance` | `ctx.getBeanFactory().registerSingleton(...)` |

### 2) Contextual binding — when/needs/give

Eyni interface üçün fərqli context-də fərqli implementation:

```php
$this->app->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(function () {
        return Storage::disk('s3');
    });

$this->app->when(VideoController::class)
    ->needs(Filesystem::class)
    ->give(fn () => Storage::disk('gcs'));

// Primitive dəyər
$this->app->when(TweetService::class)
    ->needs('$maxLength')
    ->give(280);
```

Spring-də oxşar: `@Qualifier` + `@Primary` və ya `@Profile`.

### 3) Extend — decoration

Mövcud bean-ı "örtmək" (decorate) üçün `extend`:

```php
$this->app->extend(CacheManager::class, function (CacheManager $cache, $app) {
    return new LoggingCacheManager($cache, $app['log']);
});
```

Spring ekvivalenti: `BeanPostProcessor.postProcessAfterInitialization` — orijinal bean-ı proxy ilə qaytar.

### 4) Service Provider lifecycle — register vs boot

Laravel-də service provider iki mərhələdən keçir:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    // 1-ci mərhələ: yalnız bind et, resolve ETMƏ
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            return new StripeGateway($app['config']['services.stripe.key']);
        });
    }

    // 2-ci mərhələ: bütün provider-lər register olub. Burada resolve etmək olar.
    public function boot(): void
    {
        // View composer, route macro, event listener və s.
        View::composer('payment.*', PaymentComposer::class);
        Event::listen(OrderPlaced::class, ChargeCustomer::class);
    }
}
```

**Niyə iki mərhələ?** `register` içində başqa provider-in bind etdiyi şeyi istifadə etmək olmaz — hələ register olmamış ola bilər. `boot` içində hamısı hazırdır.

### 5) booting / booted callback-ları

Provider boot olmamışdan əvvəl və sonra callback-lar:

```php
public function register(): void
{
    $this->booting(function () {
        // Bu provider boot edilməmişdən əvvəl
    });

    $this->booted(function () {
        // Bu provider boot edildikdən sonra
        $this->app->make(EventDispatcher::class)->dispatch('app.ready');
    });
}
```

### 6) Resolving / afterResolving callback-ları

Hər bean həll olunanda işləyən callback-lar (Spring-dəki `BeanPostProcessor`-a bənzəyir):

```php
$this->app->resolving(Logger::class, function (Logger $logger, $app) {
    $logger->pushProcessor(new RequestIdProcessor());
});

$this->app->afterResolving(Mailable::class, function ($mailable, $app) {
    // Hər Mailable həll olunandan sonra
    $mailable->from(config('mail.from.address'), config('mail.from.name'));
});

// Bütün bean-lar üçün
$this->app->resolving(function ($object, $app) {
    if ($object instanceof Auditable) {
        $object->setAuditor($app['auth']->user());
    }
}); 
```

### 7) Tagged bindings

Eyni "etiketi" olan çoxlu bean-ı birgə resolve etmək:

```php
// AppServiceProvider::register
$this->app->bind(StripeReporter::class);
$this->app->bind(PayPalReporter::class);
$this->app->bind(SquareReporter::class);

$this->app->tag(
    [StripeReporter::class, PayPalReporter::class, SquareReporter::class],
    'reporters'
);

// İstifadə
$this->app->bind(ReportAggregator::class, function ($app) {
    return new ReportAggregator($app->tagged('reporters'));
});
```

Spring-də ekvivalent: `List<Reporter>` inject etmək — bütün `Reporter` bean-lar siyahıya gəlir.

### 8) Facade — static proxy

Laravel-də `Cache::get('key')` əslində `Cache` facade-dır. O, container-dən `cache` bean-ı alır və metodu ona yönləndirir.

```php
namespace Illuminate\Support\Facades;

class Cache extends Facade
{
    protected static function getFacadeAccessor() { return 'cache'; }
}

// Cache::get('key') = app('cache')->get('key')
```

Spring-də birbaşa ekvivalenti yoxdur. Spring-də `@Autowired` ilə inject edirsən, static çağırış yoxdur. Facade-lər test üçün əla: `Cache::shouldReceive('get')->with('key')->andReturn('val');`.

### 9) Eager vs lazy — deferred providers

Spring-də `@Lazy` bean var. Laravel-də **deferred provider**:

```php
class PaymentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, ...);
    }

    public function provides(): array
    {
        return [PaymentGateway::class];
    }
}
```

Bu provider yalnız `PaymentGateway` ilk dəfə resolve olunanda boot olunur. Startup sürətləndirmək üçün.

### 10) config/app.php — provider qeydiyyatı

```php
// bootstrap/providers.php (Laravel 11+)
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\PaymentServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
```

### 11) Tam nümunə — PaymentGateway

```php
// app/Contracts/PaymentGateway.php
interface PaymentGateway
{
    public function charge(int $amountCents, string $currency, string $token): string;
}

// app/Services/StripeGateway.php
class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
    ) {}

    public function charge(int $amountCents, string $currency, string $token): string
    {
        $this->logger->info('Charging via Stripe', ['amount' => $amountCents]);
        // Stripe SDK çağırışı
        return 'ch_' . bin2hex(random_bytes(8));
    }
}

// app/Providers/PaymentServiceProvider.php
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            return new StripeGateway(
                apiKey: $app['config']->get('services.stripe.key'),
                logger: $app['log']->channel('payments'),
            );
        });
    }

    public function boot(): void
    {
        $this->app->resolving(PaymentGateway::class, function ($gw, $app) {
            // Hər resolve edildikdə metrika yenilə
            $app['metrics']->increment('payment_gateway_resolved');
        });
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Container interface | `ApplicationContext` / `BeanFactory` | `Illuminate\Container\Container` |
| Default scope | `singleton` | `bind` = prototype, `singleton`-u açıq deyirsən |
| Lifecycle callback | `@PostConstruct`, `@PreDestroy`, `InitializingBean` | `boot()` method, `booted()` callback |
| Post-processor | `BeanPostProcessor` | `resolving` / `afterResolving` |
| Definition modifier | `BeanFactoryPostProcessor` | `extend()` method |
| Custom factory | `FactoryBean<T>` | Closure in `bind`/`singleton` |
| Proxy | JDK dynamic / CGLIB | Facade = static proxy; AOP yoxdur |
| Request scope | `@Scope("request")` + proxyMode | `scoped()` binding |
| Session scope | `@Scope("session")` | Yoxdur (manual session facade) |
| Tagged bean | `List<Interface>` auto-wire | `tag()` + `tagged()` |
| Circular deps | Setter injection / `@Lazy` | Error — refactor lazım |
| Contextual binding | `@Qualifier` + `@Primary` | `when`/`needs`/`give` |
| Config phase | `@Configuration` + `@Bean` | `register()` method |
| Post-config phase | `ApplicationReadyEvent` | `boot()` method |
| Lazy init | `@Lazy` annotation | `DeferrableProvider` |
| Aware callbacks | 8+ aware interface | Container constructor inject |
| Destroy | `@PreDestroy` / `DisposableBean` | `terminating()` callback |

---

## Niyə belə fərqlər var?

**Java və PHP runtime model-i.** JVM uzun-ömürlü prosesdir — bean-lar start-da bir dəfə yaradılır, illər boyu yaşayır. Buna görə `@PostConstruct`, `@PreDestroy`, scope, post-processor kimi formal mərhələlər məntiqlidir. PHP-də isə (Octane olmasa) hər HTTP sorğu yeni prosesdir — tətbiq yenidən boot olunur. `register` + `boot` iki mərhələsi kifayətdir.

**Static typing və annotation.** Spring güclü tiplərə söykənir: `@Autowired`, `@Qualifier`, `@Primary` compile-time hintlər verir. Laravel PHP-nin dinamik təbiətindən istifadə edir — closure-lar və array-lar bind məntiqini təyin edir. Bu daha qısa kod, amma daha az tip təhlükəsizliyi deməkdir.

**AOP proxy vs facade.** Spring AOP (Aspect-Oriented Programming) — `@Transactional`, `@Async`, `@Cacheable` işləsin deyə bean-ları proxy-ləyir. Laravel-də AOP yoxdur; əvəzinə hadisə/listener, middleware və facade istifadə olunur. Facade static mock-u asanlaşdırır, amma runtime-da metod axtarmaq cross-cutting concerns üçün AOP qədər güclü deyil.

**Circular dependency.** Constructor injection Spring-də default-dur — immutable, təhlükəsiz, amma circular case-də xəta verir. Laravel-də closure ilə bind edilir, dinamik həll olunur — circular olsa stack overflow baş verir. Hər iki halda refactor ən yaxşı həlldir.

**FactoryBean vs closure.** Spring `FactoryBean<T>` uzun-uzadı XML dövrlərindən qalıb — indi əsasən framework daxilində (Redis, JMS) istifadə olunur. Laravel-də `bind(X::class, fn () => ...)` birbaşa closure kifayət edir.

**Scope-ların sayı.** Spring 6+ scope təklif edir (singleton, prototype, request, session, application, websocket, custom). Laravel yalnız 4: bind, singleton, scoped, instance. Bu sadəliklə iş görür — web-də çoxu vaxt singleton və ya scoped kifayətdir.

**Full vs lite configuration.** `@Configuration(proxyBeanMethods = false)` Boot 2.2-də gəldi ki, startup sürətlənsin və native image (GraalVM) mümkün olsun. Laravel-də bu problem yoxdur — PHP hər dəfə yenidən yüklənir, lite vs full ayrımı lazımsızdır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `BeanFactoryPostProcessor` — definition səviyyəsində dəyişiklik
- `BeanPostProcessor` — hər bean üçün callback
- `FactoryBean<T>` — xüsusi factory interface
- `@PostConstruct` / `@PreDestroy` JSR-250 annotation
- `InitializingBean` / `DisposableBean` lifecycle interface
- 8+ `Aware` interface (BeanNameAware, ApplicationContextAware, ...)
- Request / session / application / websocket scope built-in
- JDK dynamic və CGLIB proxy seçimi
- `@Configuration` full mode (inter-bean call proxying)
- `proxyBeanMethods = false` lite mode
- `@Lazy` proxy ilə deferred resolution
- Setter injection ilə circular dependency həlli
- `@Qualifier`, `@Primary` — çoxlu candidate seçimi
- AOP — `@Transactional`, `@Async`, `@Cacheable` proxy ilə

**Yalnız Laravel-də:**
- `resolving` / `afterResolving` callback — çoxluq və tək bean üçün
- `extend()` ilə decorator pattern tək xətdə
- `when()`/`needs()`/`give()` contextual binding
- `tag()` / `tagged()` — etiketlə qruplama
- `DeferrableProvider` — provider-i lazy etmək
- Facade — static proxy, test-friendly
- `scoped()` — Octane tick / sorğu üçün tək instance
- `booting()` / `booted()` callback hookları
- `terminating()` — sorğu bitəndən sonra callback
- Service Provider register/boot iki mərhələ
- Closure-based binding — heç bir class yaratmadan
- `app()->make()` / `resolve()` helper — runtime resolution

---

## Best Practices

1. **Constructor injection istifadə et.** Spring-də `@Autowired` field əvəzinə constructor; Laravel-də type-hint constructor.
2. **Circular dependency-dən qaç.** Design bug-dur — interface ayır, event istifadə et.
3. **Lifecycle callback-da ağır iş etmə.** `@PostConstruct` və `boot()` startup-u uzatmamalıdır. Eager cache warming varsa `ApplicationReadyEvent` istifadə et.
4. **`@Configuration(proxyBeanMethods = false)`** Spring Boot internal-lərində default-dur — sənin config-lərində də nəzərə al.
5. **Spring-də `@Lazy`** yalnız circular və ya startup optimization üçün.
6. **Laravel-də `scoped` bind** Octane-da request-per-worker model-ini qoruyur — global state yarada bilən bean üçün uyğundur.
7. **Service provider `register` metodunda resolve etmə** — yalnız bind et. Resolve `boot` metodunda.
8. **Tagged bean + Laravel tag** strategiya pattern üçün ideal — çoxlu handler bir yerə yığılır.
9. **Spring scope `request` + `proxyMode = TARGET_CLASS`** olmadan singleton-a inject etmə — köhnə data görəcəksən.
10. **Facade-dən test üçün `shouldReceive` istifadə et** — real container-i qırmadan mock qur.

---

## Yekun

Spring Bean Lifecycle zəngin və formal — instantiation, populate, aware callbacks, BeanPostProcessor before/after, `@PostConstruct`, `afterPropertiesSet`, custom init, destroy. Bu formallıq böyük enterprise tətbiqlər üçün lazımdır: AOP, transaction proxy, custom scope, FactoryBean. Laravel Service Container daha sadə və dinamik: `bind`, `singleton`, `scoped`, `instance`, `extend`, `resolving`. Java statik tiplərə və JVM uzun-ömürlü prosesinə söykənir, PHP isə hər sorğuda yenidən boot olan modelə. Hər iki sistem IoC (Inversion of Control) prinsipinə sadiqdir — fərq çıxış yolunun detalındadır.

Seçim yox — hansı framework-də olsan onun idiomlarını mənimsə: Spring-də `BeanPostProcessor` və scope-proxy, Laravel-də `resolving` callback və Facade. Sadə saxla, constructor injection-dan istifadə et, circular dependency-dən qaç.
