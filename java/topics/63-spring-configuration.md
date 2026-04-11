# 63. Spring @Configuration

## Mündəricat
1. [@Configuration nədir?](#configuration-nədir)
2. [CGLIB Proxy — Full Mode](#cglib-proxy-full-mode)
3. [@Bean metodları](#bean-metodları)
4. [@Import və @ImportResource](#import-və-importresource)
5. [Lite Configuration](#lite-configuration)
6. [@DependsOn və @Lazy on @Bean](#dependson-və-lazy-on-bean)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @Configuration nədir?

`@Configuration` — Spring-ə bean-ların necə yaradılacağını bildirən Java sinifidir. XML `applicationContext.xml`-in Java alternatividir.

```java
// YANLIŞ — @Configuration olmadan @Bean metodu işləyir,
// amma CGLIB proxy yoxdur — singleton qorunmur (lite mode)
@Component
public class BadConfig {
    @Bean
    public ServiceA serviceA() {
        return new ServiceA(serviceB()); // serviceB() hər dəfə yeni obyekt yarada bilər!
    }

    @Bean
    public ServiceB serviceB() {
        return new ServiceB();
    }
}

// DOĞRU — @Configuration ilə CGLIB proxy aktiv olur
@Configuration
public class AppConfig {
    @Bean
    public ServiceA serviceA() {
        return new ServiceA(serviceB()); // Eyni singleton ServiceB qaytarılır
    }

    @Bean
    public ServiceB serviceB() {
        return new ServiceB();
    }
}
```

---

## CGLIB Proxy — Full Mode

`@Configuration` sinifləri Spring tərəfindən **CGLIB ilə subclass-ı** yaradılır. Bu o deməkdir ki, `@Bean` metodları birbaşa çağırılmır — proxy vasitəsilə çağırılır və Spring singleton-u qaytarır.

```java
@Configuration
public class DatabaseConfig {

    // Bu metod birbaşa çağırılmır — CGLIB proxy interceptor işləyir
    @Bean
    public DataSource dataSource() {
        HikariDataSource ds = new HikariDataSource();
        ds.setJdbcUrl("jdbc:postgresql://localhost:5432/mydb");
        ds.setUsername("postgres");
        ds.setPassword("password");
        ds.setMaximumPoolSize(10);
        return ds;
    }

    @Bean
    public JdbcTemplate jdbcTemplate() {
        // dataSource() çağırılır, amma Spring eyni bean-ı qaytarır
        return new JdbcTemplate(dataSource());
    }

    @Bean
    public TransactionManager transactionManager() {
        // Yenə dataSource() çağırılır — eyni singleton qaytarılır!
        DataSourceTransactionManager tm = new DataSourceTransactionManager();
        tm.setDataSource(dataSource());
        return tm;
    }
}

// Proxyni yoxlamaq
@SpringBootTest
class ConfigProxyTest {

    @Autowired
    private ApplicationContext context;

    @Test
    void configClassIsProxied() {
        DatabaseConfig config = context.getBean(DatabaseConfig.class);
        // CGLIB proxy — real sinif deyil
        System.out.println(config.getClass().getName());
        // Output: com.example.DatabaseConfig$$SpringCGLIB$$0
        assertTrue(config.getClass().getName().contains("CGLIB"));
    }
}
```

### proxyBeanMethods=false — Lite Mode

```java
// Performans üçün CGLIB proxy-ni deaktiv etmək
@Configuration(proxyBeanMethods = false)
public class LightweightConfig {

    // CGLIB yoxdur — bean metodları birbaşa çağırılır
    // Bean-lar arasında method-level dependency olmamalıdır!
    @Bean
    public ServiceA serviceA(ServiceB serviceB) {
        // Parametr injection — DOĞRU yol (CGLIB lazım deyil)
        return new ServiceA(serviceB);
    }

    @Bean
    public ServiceB serviceB() {
        return new ServiceB();
    }
}
```

---

## @Bean metodları

```java
@Configuration
public class BeanConfig {

    // Sadə bean
    @Bean
    public UserRepository userRepository() {
        return new JpaUserRepository();
    }

    // Dependency injection — parametr vasitəsilə
    @Bean
    public UserService userService(UserRepository userRepository,
                                   EmailService emailService) {
        return new UserService(userRepository, emailService);
    }

    // Bean adını dəyişmək
    @Bean(name = "primaryEmailService")
    public EmailService emailService() {
        return new SmtpEmailService("smtp.gmail.com", 587);
    }

    // Alias əlavə etmək
    @Bean(name = {"cacheManager", "defaultCacheManager", "appCacheManager"})
    public CacheManager cacheManager() {
        return new ConcurrentMapCacheManager("users", "products");
    }

    // Init və destroy metodları
    @Bean(initMethod = "start", destroyMethod = "shutdown")
    public ConnectionPool connectionPool() {
        return new ConnectionPool("jdbc:postgresql://localhost/db");
    }

    // External kitabxana bean-ı — @Component istifadə edə bilmirik
    @Bean
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();
        mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS);
        mapper.registerModule(new JavaTimeModule());
        return mapper;
    }

    // Şərti bean
    @Bean
    @ConditionalOnMissingBean(DataSource.class)
    public DataSource defaultDataSource() {
        return new EmbeddedDatabaseBuilder()
            .setType(EmbeddedDatabaseType.H2)
            .build();
    }
}
```

### @Bean vs @Component

```java
// @Bean — konfiqurasiya sinfində, tam nəzarət
@Configuration
public class Config {
    @Bean
    public ThirdPartyService thirdPartyService() {
        // Xarici kitabxananın sinfi — source koduna çata bilmirik
        ThirdPartyService service = new ThirdPartyService();
        service.setApiKey("abc123");
        service.setRetryCount(3);
        return service;
    }
}

// @Component — öz siniflərimiz üçün, komponent skan ilə tapılır
@Service // @Component-in ixtisaslaşmış forması
public class MyService {
    // Spring avtomatik tapır və yaradır
}
```

---

## @Import və @ImportResource

### @Import — başqa konfiqurasiya siniflərini yükləmək

```java
@Configuration
public class DatabaseConfig {
    @Bean
    public DataSource dataSource() { ... }
}

@Configuration
public class CacheConfig {
    @Bean
    public CacheManager cacheManager() { ... }
}

@Configuration
public class SecurityConfig {
    @Bean
    public SecurityManager securityManager() { ... }
}

// Hamısını birləşdirmək
@Configuration
@Import({DatabaseConfig.class, CacheConfig.class, SecurityConfig.class})
public class AppConfig {
    // Bütün yuxarıdakı bean-lar bu context-ə daxil olur
}

// Spring Boot — main sinif
@SpringBootApplication // @Import əvəzinə @ComponentScan istifadə edir
public class MyApp {
    public static void main(String[] args) {
        SpringApplication.run(MyApp.class, args);
    }
}
```

### @Import ilə ImportSelector

```java
// Dinamik konfiqurasiya seçimi
public class DatabaseConfigSelector implements ImportSelector {

    @Override
    public String[] selectImports(AnnotationMetadata metadata) {
        // Hansı konfiqurasiyanın yüklənəcəyini məntiqlə seç
        String dbType = System.getProperty("db.type", "postgresql");

        return switch (dbType) {
            case "mysql" -> new String[]{"com.example.MySqlConfig"};
            case "mongodb" -> new String[]{"com.example.MongoConfig"};
            default -> new String[]{"com.example.PostgreSqlConfig"};
        };
    }
}

@Configuration
@Import(DatabaseConfigSelector.class)
public class AppConfig {
    // db.type system property-ə görə database konfiqurasiyası seçilir
}
```

### @ImportResource — XML bean-ları import etmək

```java
// Köhnə XML konfiqurasiyaları olan layihələrdə
@Configuration
@ImportResource({
    "classpath:legacy-beans.xml",
    "classpath:security-context.xml"
})
public class MixedConfig {
    // XML bean-ları bu context-ə daxil olur

    // Java ilə yeni bean-lar əlavə etmək olar
    @Bean
    public NewService newService() {
        return new NewService();
    }
}
```

---

## Lite Configuration

`@Component` içindəki `@Bean` metodları **lite mode**-da işləyir — CGLIB proxy yoxdur.

```java
// Lite mode — @Component içindəki @Bean
@Component
public class RepositoryConfig {

    // CGLIB proxy yoxdur!
    @Bean
    public UserRepository userRepository() {
        return new JpaUserRepository();
    }

    @Bean
    public OrderRepository orderRepository() {
        return new JpaOrderRepository();
    }

    // YANLIŞ — Bu singleton qaytarmır, hər dəfə yeni instance yaranır!
    @Bean
    public ServiceA serviceA() {
        return new ServiceA(userRepository()); // Yeni UserRepository yaranır!
    }
}

// DOĞRU — Lite mode-da parametr injection istifadə et
@Component
public class RepositoryConfigFixed {

    @Bean
    public UserRepository userRepository() {
        return new JpaUserRepository();
    }

    @Bean
    public ServiceA serviceA(UserRepository userRepository) {
        // Spring singleton UserRepository-ni inject edir
        return new ServiceA(userRepository);
    }
}
```

---

## @DependsOn və @Lazy on @Bean

### @DependsOn — sıra təmin etmək

```java
@Configuration
public class InfrastructureConfig {

    // DatabaseMigration bean-ı əvvəl yaranmalıdır
    @Bean
    public DatabaseMigration databaseMigration() {
        DatabaseMigration migration = new DatabaseMigration();
        migration.runMigrations(); // Flyway/Liquibase kimi
        return migration;
    }

    // Bu bean yaranmazdan əvvəl databaseMigration işləməlidir
    @Bean
    @DependsOn("databaseMigration")
    public UserRepository userRepository(DataSource dataSource) {
        // Migration tamamlanandan sonra yaranır
        return new JpaUserRepository(dataSource);
    }

    @Bean
    @DependsOn({"databaseMigration", "cacheWarmer"})
    public ApplicationReadyService applicationReadyService() {
        return new ApplicationReadyService();
    }
}
```

### @Lazy on @Bean

```java
@Configuration
public class LazyBeanConfig {

    // Yalnız ilk istifadədə yaranır
    @Bean
    @Lazy
    public HeavyComputationService heavyService() {
        System.out.println("HeavyComputationService başladılır — çox vaxt aparır!");
        // Uzun initialization
        return new HeavyComputationService();
    }

    // Normal bean — startup-da yaranır
    @Bean
    public LightService lightService() {
        return new LightService();
    }
}

// Lazy bean-i inject edəndə proxy alırıq
@Service
public class AppService {

    @Lazy // Burada da @Lazy — proxy inject edilir
    @Autowired
    private HeavyComputationService heavyService;

    public void doWork() {
        // İlk çağırışda real bean yaranır
        heavyService.compute();
    }
}
```

---

## Tam nümunə: Modular konfiqurasiya

```java
// Verilənlər bazası konfiqurasiyası
@Configuration
@ConditionalOnProperty(name = "db.type", havingValue = "postgresql")
public class PostgreSqlConfig {

    @Value("${spring.datasource.url}")
    private String url;

    @Value("${spring.datasource.username}")
    private String username;

    @Value("${spring.datasource.password}")
    private String password;

    @Bean
    @Primary
    public DataSource postgresDataSource() {
        HikariConfig config = new HikariConfig();
        config.setJdbcUrl(url);
        config.setUsername(username);
        config.setPassword(password);
        config.setDriverClassName("org.postgresql.Driver");
        config.setMaximumPoolSize(20);
        config.setMinimumIdle(5);
        config.setConnectionTimeout(30000);
        return new HikariDataSource(config);
    }
}

// Cache konfiqurasiyası
@Configuration
@EnableCaching
public class CacheConfig {

    @Bean
    public CacheManager cacheManager() {
        CaffeineCacheManager manager = new CaffeineCacheManager();
        manager.setCaffeine(
            Caffeine.newBuilder()
                .maximumSize(1000)
                .expireAfterWrite(10, TimeUnit.MINUTES)
        );
        return manager;
    }
}

// Async konfiqurasiyası
@Configuration
@EnableAsync
public class AsyncConfig implements AsyncConfigurer {

    @Bean(name = "taskExecutor")
    public Executor taskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(5);
        executor.setMaxPoolSize(20);
        executor.setQueueCapacity(100);
        executor.setThreadNamePrefix("async-");
        executor.initialize();
        return executor;
    }

    @Override
    public Executor getAsyncExecutor() {
        return taskExecutor();
    }
}

// Ana konfiqurasiya — hamısını birləşdirir
@Configuration
@Import({
    PostgreSqlConfig.class,
    CacheConfig.class,
    AsyncConfig.class
})
@ComponentScan(basePackages = "com.example")
@PropertySource("classpath:application.properties")
public class AppConfig {
    // Modular konfiqurasiya — hər şey ayrı siniflərdə
}
```

---

## İntervyu Sualları

**S: @Configuration ilə @Component arasındakı fərq nədir?**
C: @Configuration — full mode, CGLIB proxy ilə işləyir, @Bean metodları arası çağırışlar singleton qaytarır. @Component içindəki @Bean — lite mode, CGLIB yoxdur, metodlar birbaşa çağırılır, yeni instance qaytarır. @Configuration bean inter-dependencies üçün lazımdır.

**S: CGLIB proxy @Configuration-da nə üçün lazımdır?**
C: @Bean metodları bir-birini çağıran zaman Spring singleton-u qaytarsın deyə. Məsələn, `serviceA()` içindən `serviceB()` çağırılanda, CGLIB proxy Spring-in singleton `serviceB` bean-ını qaytarır, yeni instance yaratmır.

**S: proxyBeanMethods=false nə vaxt istifadə etmək lazımdır?**
C: @Bean metodları bir-birini çağırmıyanda, performans optimizasiyası üçün. Spring Boot auto-configuration sinifləri bu yanaşmadan istifadə edir.

**S: @Import nə zaman istifadə edirik?**
C: 1) Böyük konfiqurasiyanı modullar üzrə bölmək üçün, 2) Şərti konfiqurasiya üçün (ImportSelector), 3) Xarici kitabxananın konfiqurasiyasını yükləmək üçün. @ComponentScan-dan fərqli olaraq, dəqiq sinifləri göstəririk.

**S: @DependsOn nə vaxt istifadə etmək lazımdır?**
C: Bean-ların yaranma sırası vacib olanda — məsələn, verilənlər bazası migration-ı tamamlanmadan repository bean-ları yaranmamalıdır. Amma bu nadir hal olmalıdır — adətən Spring asılılıqları özü idarə edir.

**S: @Bean metodunda init/destroy metodu necə göstərərik?**
C: `@Bean(initMethod = "start", destroyMethod = "shutdown")`. Alternativ olaraq bean sinifində `@PostConstruct` / `@PreDestroy` istifadə etmək daha yaxşıdır.
