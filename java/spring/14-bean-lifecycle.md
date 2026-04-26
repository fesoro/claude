# 14 — Spring Bean Lifecycle

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Bean Lifecycle-a ümumi baxış](#lifecycle-overview)
2. [Instantiation — obyekt yaradılması](#instantiation)
3. [Property Population — dependency injection](#property-population)
4. [Aware Interfaces](#aware-interfaces)
5. [BeanPostProcessor.postProcessBeforeInitialization](#bpp-before)
6. [@PostConstruct / InitializingBean.afterPropertiesSet](#post-construct)
7. [init-method](#init-method)
8. [BeanPostProcessor.postProcessAfterInitialization](#bpp-after)
9. [@PreDestroy / DisposableBean.destroy](#pre-destroy)
10. [destroy-method](#destroy-method)
11. [Lifecycle Callback müqayisəsi](#lifecycle-comparison)
12. [İntervyu Sualları](#intervyu-suallar)

---

## Bean Lifecycle-a ümumi baxış

Spring bean-in həyat dövrü aşağıdakı mərhələlərdən ibarətdir:

```
1.  Bean Definition oxunması (@Component, @Bean, XML)
2.  Bean Instantiation (constructor çağırılır)
3.  Property Population (dependency injection)
4.  BeanNameAware.setBeanName()
5.  BeanClassLoaderAware.setBeanClassLoader()
6.  BeanFactoryAware.setBeanFactory()
7.  EnvironmentAware.setEnvironment()
8.  ApplicationContextAware.setApplicationContext()
9.  BeanPostProcessor.postProcessBeforeInitialization()
10. @PostConstruct (JSR-250)
11. InitializingBean.afterPropertiesSet()
12. @Bean(initMethod="...") / init-method="..."
13. BeanPostProcessor.postProcessAfterInitialization()
    --- Bean istifadəyə hazırdır ---
14. @PreDestroy (JSR-250)
15. DisposableBean.destroy()
16. @Bean(destroyMethod="...") / destroy-method="..."
```

---

## Instantiation — obyekt yaradılması

```java
import org.springframework.stereotype.Service;

@Service
public class UserService {

    private final UserRepository userRepository;

    // 1. Constructor çağırılır — instantiation mərhələsi
    public UserService(UserRepository userRepository) {
        System.out.println("1. UserService constructor çağırıldı");
        // QEYD: Bu nöqtədə dependency inject edilib
        // Constructor injection-da dependency bu nöqtədə hazırdır
        this.userRepository = userRepository;
    }
}
```

### BeanDefinition-dan Bean yaradılması

```java
// Spring daxilən bu prosesi aparır:
// 1. BeanDefinition oxunur
// 2. Constructor seçilir (refleksiya ilə)
// 3. Constructor çağırılır
// 4. Bean instance yaradılır

// Bunu demonstrasiya etmək üçün
@Configuration
public class LifecycleConfig {

    @Bean
    public UserService userService(UserRepository userRepository) {
        System.out.println("@Bean metodu çağırıldı — instantiation");
        // Spring bu metodu çağırır, nəticəni container-ə verir
        return new UserService(userRepository);
    }
}
```

---

## Property Population — dependency injection

```java
@Service
public class OrderService {

    // Field injection — property population mərhələsində inject edilir
    @Autowired
    private UserService userService;

    // Setter injection — property population mərhələsində çağırılır
    @Autowired
    public void setEmailService(EmailService emailService) {
        System.out.println("2. Setter injection — property population");
        this.emailService = emailService;
    }

    private EmailService emailService;

    // Constructor — instantiation mərhələsindədir
    // Constructor injection-da property population ilə birləşir
    public OrderService() {
        System.out.println("1. Constructor çağırıldı");
    }
}
```

---

## Aware Interfaces

Aware interface-lər Spring container-in daxili komponentlərinə giriş imkanı verir.

```java
import org.springframework.beans.factory.BeanNameAware;
import org.springframework.beans.factory.BeanFactoryAware;
import org.springframework.context.ApplicationContextAware;
import org.springframework.beans.factory.BeanClassLoaderAware;

@Service
public class AwareDemo implements
        BeanNameAware,
        BeanClassLoaderAware,
        BeanFactoryAware,
        ApplicationContextAware,
        EnvironmentAware {

    private String beanName;
    private ClassLoader classLoader;
    private BeanFactory beanFactory;
    private ApplicationContext applicationContext;
    private Environment environment;

    // Mərhələ 4: BeanNameAware
    @Override
    public void setBeanName(String name) {
        System.out.println("3. setBeanName: " + name);
        this.beanName = name; // Container-dəki bean adı
    }

    // Mərhələ 5: BeanClassLoaderAware
    @Override
    public void setBeanClassLoader(ClassLoader classLoader) {
        System.out.println("4. setBeanClassLoader çağırıldı");
        this.classLoader = classLoader;
    }

    // Mərhələ 6: BeanFactoryAware
    @Override
    public void setBeanFactory(BeanFactory beanFactory) {
        System.out.println("5. setBeanFactory çağırıldı");
        this.beanFactory = beanFactory;
    }

    // Mərhələ 8: ApplicationContextAware
    @Override
    public void setApplicationContext(ApplicationContext applicationContext) {
        System.out.println("6. setApplicationContext çağırıldı");
        this.applicationContext = applicationContext;
    }

    // EnvironmentAware
    @Override
    public void setEnvironment(Environment environment) {
        this.environment = environment;
    }
}
```

---

## BeanPostProcessor.postProcessBeforeInitialization

```java
import org.springframework.beans.factory.config.BeanPostProcessor;
import org.springframework.stereotype.Component;

// Bütün bean-lər üçün initialization-dan ƏVVƏL çağırılır
@Component
public class LoggingBeanPostProcessor implements BeanPostProcessor {

    @Override
    public Object postProcessBeforeInitialization(Object bean, String beanName) {
        // initialization mərhələsindən ƏVVƏL çağırılır
        // (@PostConstruct, InitializingBean.afterPropertiesSet-dən əvvəl)
        System.out.println("Before Init — Bean: " + beanName +
            ", Type: " + bean.getClass().getSimpleName());

        // Bean-i dəyişdirmək mümkündür — wrapper/proxy qaytarmaq olar
        // null qaytarmaq olmaz (Spring ignora edir, amma davranış qeyri-müəyyəndir)
        return bean; // Bean-i dəyişmədən qaytarırıq
    }

    @Override
    public Object postProcessAfterInitialization(Object bean, String beanName) {
        // initialization mərhələsindən SONRA çağırılır
        return bean;
    }
}

// Praktiki nümunə: @Autowired annotation-ını BeanPostProcessor emal edir
// AutowiredAnnotationBeanPostProcessor — Spring-in daxili BeanPostProcessor-u
```

---

## @PostConstruct / InitializingBean.afterPropertiesSet

### @PostConstruct (tövsiyə olunan)

```java
import jakarta.annotation.PostConstruct;
import jakarta.annotation.PreDestroy;

@Service
public class CacheService {

    private final UserRepository userRepository;
    private Map<Long, User> userCache;

    public CacheService(UserRepository userRepository) {
        this.userRepository = userRepository;
        // YANLIŞ: Constructor-da initialization — dependency hələ tam hazır olmaya bilər
        // this.userCache = loadCache(); // Potensial problem
    }

    // DOĞRU: @PostConstruct — bütün dependency-lər inject edildikdən sonra
    @PostConstruct
    public void initCache() {
        System.out.println("9. @PostConstruct çağırıldı — cache yüklənir");
        // Bütün dependency-lər hazırdır
        this.userCache = userRepository.findAll()
            .stream()
            .collect(Collectors.toMap(User::getId, u -> u));
        System.out.println("Cache " + userCache.size() + " user ilə yükləndi");
    }

    @PreDestroy
    public void clearCache() {
        System.out.println("@PreDestroy — cache təmizlənir");
        userCache.clear();
    }
}
```

### InitializingBean (köhnə üsul)

```java
import org.springframework.beans.factory.InitializingBean;
import org.springframework.beans.factory.DisposableBean;

@Service
public class ConnectionPoolService implements InitializingBean, DisposableBean {

    private List<Connection> connectionPool;
    private int poolSize = 10;

    // InitializingBean.afterPropertiesSet() — @PostConstruct-dan SONRA çağırılır
    @Override
    public void afterPropertiesSet() throws Exception {
        System.out.println("10. afterPropertiesSet çağırıldı");
        // Connection pool yaradılır
        this.connectionPool = new ArrayList<>();
        for (int i = 0; i < poolSize; i++) {
            connectionPool.add(createConnection());
        }
    }

    // DisposableBean.destroy() — @PreDestroy-dan SONRA çağırılır
    @Override
    public void destroy() throws Exception {
        System.out.println("15. DisposableBean.destroy çağırıldı");
        connectionPool.forEach(this::closeConnection);
        connectionPool.clear();
    }

    private Connection createConnection() { return null; } // Sadələşdirilmiş
    private void closeConnection(Connection c) {} // Sadələşdirilmiş
}
```

---

## init-method

```java
// @Bean annotasiyasında init metodu
@Configuration
public class AppConfig {

    @Bean(initMethod = "initialize", destroyMethod = "shutdown")
    public DatabaseMigrationService migrationService() {
        return new DatabaseMigrationService();
    }
}

// XML-dən gələn "init-method" ilə ekvivalent sinif
public class DatabaseMigrationService {

    // @Bean(initMethod="initialize") bu metodu çağırır
    // afterPropertiesSet-dən SONRA çağırılır
    public void initialize() {
        System.out.println("11. init-method çağırıldı — DB migration başlayır");
        // Flyway/Liquibase işə düşür
    }

    // @Bean(destroyMethod="shutdown") bu metodu çağırır
    public void shutdown() {
        System.out.println("16. destroy-method çağırıldı");
    }
}
```

---

## BeanPostProcessor.postProcessAfterInitialization

```java
@Component
public class ProxyCreatingBeanPostProcessor implements BeanPostProcessor {

    @Override
    public Object postProcessAfterInitialization(Object bean, String beanName) {
        // initialization-dan SONRA çağırılır
        // AOP proxy-ləri BU mərhələdə yaradılır!
        // @Transactional, @Cacheable, @Async — bunların proxy-ləri burada yaranır

        System.out.println("13. postProcessAfterInitialization: " + beanName);

        // Proxy yaratmaq nümunəsi
        if (bean instanceof LoggableService) {
            // JDK dynamic proxy ilə bütün metodları log et
            return Proxy.newProxyInstance(
                bean.getClass().getClassLoader(),
                bean.getClass().getInterfaces(),
                (proxy, method, args) -> {
                    System.out.println("Metod çağırıldı: " + method.getName());
                    return method.invoke(bean, args);
                }
            );
        }
        return bean;
    }
}
```

---

## @PreDestroy / DisposableBean.destroy

```java
@Service
public class ResourceManager {

    private ScheduledExecutorService scheduler;
    private List<AutoCloseable> resources = new ArrayList<>();

    @PostConstruct
    public void start() {
        System.out.println("ResourceManager başlatıldı");
        this.scheduler = Executors.newScheduledThreadPool(4);
        // Scheduled task-lar qeydiyyatdan keçir
    }

    // Container bağlandıqda (JVM shutdown, context.close())
    @PreDestroy
    public void cleanup() {
        System.out.println("@PreDestroy — resurslar bağlanır");

        // Executor-u dayandır
        scheduler.shutdown();
        try {
            if (!scheduler.awaitTermination(5, TimeUnit.SECONDS)) {
                scheduler.shutdownNow();
            }
        } catch (InterruptedException e) {
            scheduler.shutdownNow();
            Thread.currentThread().interrupt();
        }

        // Bütün resursları bağla
        resources.forEach(r -> {
            try {
                r.close();
            } catch (Exception e) {
                // Log et
            }
        });
    }
}
```

---

## destroy-method

```java
@Configuration
public class DataSourceConfig {

    // HikariCP avtomatik closeOnExit detect edir
    // Amma explicit destroy-method daha güvənlidir
    @Bean(destroyMethod = "close")
    public DataSource dataSource() {
        HikariDataSource ds = new HikariDataSource();
        ds.setJdbcUrl("jdbc:postgresql://localhost:5432/mydb");
        ds.setMaximumPoolSize(10);
        return ds;
    }

    // destroyMethod = "" — avtomatik close() çağırılmasını ləğv etmək
    @Bean(destroyMethod = "")
    public DataSource externalDataSource() {
        // Xarici data source — Spring idarə etməməlidir
        return ExternalDataSourceManager.getDataSource();
    }
}
```

---

## Tam Lifecycle Demo

```java
import jakarta.annotation.PostConstruct;
import jakarta.annotation.PreDestroy;
import org.springframework.beans.factory.BeanNameAware;
import org.springframework.beans.factory.DisposableBean;
import org.springframework.beans.factory.InitializingBean;

@Component("fullLifecycleBean")
public class FullLifecycleBean implements
        BeanNameAware,
        InitializingBean,
        DisposableBean {

    private final DependencyService dependency;
    private String beanName;

    // Mərhələ 1: Constructor — Instantiation
    public FullLifecycleBean(DependencyService dependency) {
        System.out.println("1. CONSTRUCTOR: Bean yaradıldı");
        this.dependency = dependency;
    }

    // Mərhələ 2: Property Population (field/setter injection burada)

    // Mərhələ 3: BeanNameAware
    @Override
    public void setBeanName(String name) {
        System.out.println("3. BeanNameAware.setBeanName: " + name);
        this.beanName = name;
    }

    // Mərhələ 4: BeanPostProcessor.postProcessBeforeInitialization
    // (BeanPostProcessor sinifindədir — burada göstərilmir)

    // Mərhələ 5: @PostConstruct
    @PostConstruct
    public void postConstruct() {
        System.out.println("5. @PostConstruct: " + beanName + " initialization");
        // Cache yüklə, connection pool aç, scheduler başlat
    }

    // Mərhələ 6: InitializingBean.afterPropertiesSet
    @Override
    public void afterPropertiesSet() throws Exception {
        System.out.println("6. afterPropertiesSet: əlavə initialization");
    }

    // Mərhələ 7: @Bean(initMethod) — @Configuration sinifindədir

    // Mərhələ 8: BeanPostProcessor.postProcessAfterInitialization
    // (AOP proxy-lər burada yaradılır)

    // =================== BEAN HAZIRDIR VƏ İSTİFADƏYƏ VERİLİR ===================

    public String doWork() {
        return "İş görüldü — bean: " + beanName;
    }

    // =================== DESTROY MƏRHƏLƏSI ===================

    // Mərhələ 14: @PreDestroy
    @PreDestroy
    public void preDestroy() {
        System.out.println("14. @PreDestroy: cleanup başlayır");
        // Resursları azad et
    }

    // Mərhələ 15: DisposableBean.destroy
    @Override
    public void destroy() throws Exception {
        System.out.println("15. DisposableBean.destroy: son cleanup");
    }

    // Mərhələ 16: @Bean(destroyMethod) — @Configuration sinifindədir
}
```

---

## Lifecycle Callback müqayisəsi

```java
// Üç üsulun müqayisəsi:

// Üsul 1: @PostConstruct / @PreDestroy (tövsiyə olunan)
@Service
public class BestPracticeService {
    @PostConstruct
    public void init() {
        // JSR-250 — platform-independent
        // Spring-ə bağımlılıq yoxdur
        // Kod oxunaqlıdır
    }

    @PreDestroy
    public void cleanup() {}
}

// Üsul 2: InitializingBean / DisposableBean (Spring-specific)
@Service
public class SpringSpecificService implements InitializingBean, DisposableBean {
    @Override
    public void afterPropertiesSet() {
        // Spring interfeysinə bağımlıdır
        // Test-lərdə çağırılmır (@PostConstruct kimi)
    }

    @Override
    public void destroy() {}
}

// Üsul 3: @Bean(initMethod, destroyMethod) (xarici library üçün ideal)
@Configuration
public class ExternalConfig {
    @Bean(initMethod = "start", destroyMethod = "stop")
    public ThirdPartyService thirdPartyService() {
        // Spring annotasiyası olmayan sinif üçün
        // Xarici library-ləri konfiqurasiya etmək üçün ən yaxşı
        return new ThirdPartyService();
    }
}
```

### Çağırış sırası

```
@PostConstruct → afterPropertiesSet() → init-method
@PreDestroy → destroy() → destroy-method
```

---

## İntervyu Sualları

**S: Spring bean lifecycle-ın mərhələlərini sadalayın.**
C: Instantiation → Property Population → BeanNameAware/BeanFactoryAware/ApplicationContextAware → BeanPostProcessor.postProcessBeforeInitialization → @PostConstruct → InitializingBean.afterPropertiesSet → init-method → BeanPostProcessor.postProcessAfterInitialization → [Bean hazırdır] → @PreDestroy → DisposableBean.destroy → destroy-method.

**S: @PostConstruct ilə constructor arasındakı fərq nədir?**
C: Constructor — bean yaradılarkən çağırılır, dependency-lər hələ inject edilməmiş ola bilər (setter/field injection üçün). `@PostConstruct` — bütün dependency-lər inject edildikdən sonra çağırılır. Initialization logic üçün `@PostConstruct` tövsiyə olunur.

**S: Prototype bean-in @PreDestroy-u çağırılırmı?**
C: Xeyr. Spring yalnız singleton bean-lərin destroy lifecycle-ını idarə edir. Prototype bean-lər üçün developer özü cleanup idarə etməlidir.

**S: BeanPostProcessor nə edir?**
C: Bütün bean-lər üçün initialization-dan əvvəl və sonra çağırılan hook-dur. Spring-in özü `@Autowired`-i `AutowiredAnnotationBeanPostProcessor` ilə emal edir. AOP proxy-lər `postProcessAfterInitialization`-da yaradılır.

**S: InitializingBean ilə @PostConstruct-dan hansı əvvəl çağırılır?**
C: `@PostConstruct` əvvəl, sonra `InitializingBean.afterPropertiesSet()`, daha sonra `init-method` çağırılır.

**S: ApplicationContextAware nə vaxt istifadə edilməlidir?**
C: Spring bean-i ApplicationContext-ə birbaşa giriş lazım olduqda. Amma bu anti-pattern sayılır — mümkün qədər constructor injection istifadə edilməlidir. Legacy kod ilə inteqrasiya və ya xüsusi use-case-lərdə istifadəli ola bilər.
