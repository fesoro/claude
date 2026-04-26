# 18 — Spring @Conditional

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [@Conditional əsasları](#conditional-əsasları)
2. [@ConditionalOnClass / @ConditionalOnMissingClass](#conditionalonclass)
3. [@ConditionalOnBean / @ConditionalOnMissingBean](#conditionalonbean)
4. [@ConditionalOnProperty](#conditionalonproperty)
5. [@ConditionalOnExpression](#conditionalonexpression)
6. [@ConditionalOnWebApplication](#conditionalonwebapplication)
7. [Custom @Conditional](#custom-conditional)
8. [İntervyu Sualları](#intervyu-sualları)

---

## @Conditional əsasları

`@Conditional` — müəyyən şərt ödəniləndə bean yaradılmasını təmin edən mexanizmdir. Spring Boot auto-configuration bu mexanizm üzərindən qurulub.

```java
// Condition interface
public interface Condition {
    boolean matches(ConditionContext context, AnnotatedTypeMetadata metadata);
}

// Sadə nümunə — Linux-da işləyirsə
public class LinuxCondition implements Condition {

    @Override
    public boolean matches(ConditionContext context, AnnotatedTypeMetadata metadata) {
        // OS yoxla
        String os = context.getEnvironment().getProperty("os.name", "");
        return os.toLowerCase().contains("linux");
    }
}

@Configuration
public class OsConfig {

    @Bean
    @Conditional(LinuxCondition.class)
    public FileWatcher linuxFileWatcher() {
        return new InotifyFileWatcher(); // Linux-a xas
    }

    @Bean
    @Conditional(MacCondition.class)
    public FileWatcher macFileWatcher() {
        return new FsEventsFileWatcher(); // macOS-a xas
    }
}
```

### ConditionContext nə verir?

```java
public class AdvancedCondition implements Condition {

    @Override
    public boolean matches(ConditionContext context, AnnotatedTypeMetadata metadata) {
        // Bean Factory
        ConfigurableListableBeanFactory factory = context.getBeanFactory();

        // Environment (properties, profiles)
        Environment env = context.getEnvironment();

        // Resource loader
        ResourceLoader resourceLoader = context.getResourceLoader();

        // ClassLoader
        ClassLoader classLoader = context.getClassLoader();

        // BeanDefinitionRegistry
        BeanDefinitionRegistry registry = context.getRegistry();

        // Nümunə: Redis class mövcuddursa VƏ redis.enabled=true isə
        boolean redisClassPresent = isClassPresent(
            "org.springframework.data.redis.core.RedisTemplate", classLoader);
        boolean redisEnabled = env.getProperty("redis.enabled", Boolean.class, false);

        return redisClassPresent && redisEnabled;
    }

    private boolean isClassPresent(String className, ClassLoader classLoader) {
        try {
            Class.forName(className, false, classLoader);
            return true;
        } catch (ClassNotFoundException e) {
            return false;
        }
    }
}
```

---

## @ConditionalOnClass / @ConditionalOnMissingClass

```java
// Spring Data Redis mövcuddursa
@Configuration
@ConditionalOnClass(RedisTemplate.class)
public class RedisAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean(RedisTemplate.class)
    public RedisTemplate<String, Object> redisTemplate(
            RedisConnectionFactory connectionFactory) {
        RedisTemplate<String, Object> template = new RedisTemplate<>();
        template.setConnectionFactory(connectionFactory);
        template.setKeySerializer(new StringRedisSerializer());
        template.setValueSerializer(new GenericJackson2JsonRedisSerializer());
        return template;
    }

    @Bean
    @ConditionalOnMissingBean
    public StringRedisTemplate stringRedisTemplate(
            RedisConnectionFactory connectionFactory) {
        return new StringRedisTemplate(connectionFactory);
    }
}

// Kafka mövcud deyilsə — sadə queue istifadə et
@Configuration
@ConditionalOnMissingClass("org.apache.kafka.clients.producer.KafkaProducer")
public class SimpleQueueConfig {

    @Bean
    public MessageQueue inMemoryQueue() {
        return new InMemoryMessageQueue();
    }
}

// Birdən çox sinif şərti
@Configuration
@ConditionalOnClass({DataSource.class, JdbcTemplate.class})
public class JdbcConfig {
    // JDBC mövcuddursa konfiqurasiya
}
```

---

## @ConditionalOnBean / @ConditionalOnMissingBean

```java
// Yalnız DataSource bean-ı varsa
@Configuration
@ConditionalOnBean(DataSource.class)
public class JpaAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean(JpaTransactionManager.class)
    public JpaTransactionManager transactionManager(EntityManagerFactory emf) {
        return new JpaTransactionManager(emf);
    }
}

// CacheManager yoxdursa — default yarat
@Configuration
public class CacheConfiguration {

    @Bean
    @ConditionalOnMissingBean(CacheManager.class)
    public CacheManager defaultCacheManager() {
        // İstifadəçi öz CacheManager-ini yaratmayıbsa, bu istifadə olunur
        return new ConcurrentMapCacheManager();
    }
}

// Annotation mövcuddursa
@Configuration
@ConditionalOnBean(annotation = EnableJms.class)
public class JmsConfiguration {
    // @EnableJms annotasiyası varsa
}

// Bean adına görə
@Bean
@ConditionalOnBean(name = "customDataSource")
public QueryOptimizer queryOptimizer() {
    return new QueryOptimizer();
}
```

---

## @ConditionalOnProperty

```java
// application.properties:
# app.cache.enabled=true
# app.cache.type=redis
# app.feature.beta=true

@Configuration
public class CacheConfig {

    // app.cache.enabled=true olduqda
    @Bean
    @ConditionalOnProperty(name = "app.cache.enabled", havingValue = "true")
    public CacheManager redisCacheManager(RedisConnectionFactory factory) {
        return RedisCacheManager.builder(factory).build();
    }

    // app.cache.enabled=false YAXUD property yoxdursa
    @Bean
    @ConditionalOnProperty(
        name = "app.cache.enabled",
        havingValue = "false",
        matchIfMissing = true // Property tapılmasa bu bean yaranır
    )
    public CacheManager noOpCacheManager() {
        return new NoOpCacheManager();
    }
}

// matchIfMissing nümunəsi
@Configuration
public class FeatureConfig {

    // app.swagger.enabled property yoxdursa DEFAULT aktiv
    @Bean
    @ConditionalOnProperty(
        prefix = "app.swagger",
        name = "enabled",
        matchIfMissing = true
    )
    public OpenAPI swaggerConfig() {
        return new OpenAPI()
            .info(new Info().title("API Docs").version("1.0"));
    }

    // app.feature.beta=true yalnız beta aktiv olanda
    @Bean
    @ConditionalOnProperty("app.feature.beta")
    public BetaFeatureService betaFeature() {
        return new BetaFeatureService();
    }
}

// Prefix ilə
@Configuration
@ConditionalOnProperty(prefix = "spring.redis", name = "host")
public class RedisConfig {
    // spring.redis.host property varsa
}
```

---

## @ConditionalOnExpression

SpEL ifadəsi ilə şərt — daha mürəkkəb vəziyyətlər üçün.

```java
// SpEL ilə mürəkkəb şərtlər
@Configuration
public class AdvancedConditionalConfig {

    // İki property-nin həm true olması
    @Bean
    @ConditionalOnExpression(
        "${app.cache.enabled:false} && ${app.redis.enabled:false}"
    )
    public RedisCache redisCache() {
        return new RedisCache();
    }

    // Mühit yoxlaması
    @Bean
    @ConditionalOnExpression(
        "'${spring.profiles.active:default}'.contains('dev')"
    )
    public DevTools devTools() {
        return new DevTools();
    }

    // Ədədi müqayisə
    @Bean
    @ConditionalOnExpression("${app.workers:1} > 1")
    public LoadBalancer loadBalancer() {
        return new RoundRobinLoadBalancer();
    }

    // Mürəkkəb OR şərti
    @Bean
    @ConditionalOnExpression(
        "${feature.new-ui:false} || '${app.version}'.startsWith('2.')"
    )
    public NewUiService newUiService() {
        return new NewUiService();
    }
}
```

---

## @ConditionalOnWebApplication

```java
// Yalnız web tətbiqində
@Configuration
@ConditionalOnWebApplication
public class WebMvcConfig implements WebMvcConfigurer {

    @Bean
    public CorsFilter corsFilter() {
        // Web tətbiqi olmadıqda bu bean yaranmır
        UrlBasedCorsConfigurationSource source = new UrlBasedCorsConfigurationSource();
        CorsConfiguration config = new CorsConfiguration();
        config.setAllowedOrigins(List.of("*"));
        source.registerCorsConfiguration("/**", config);
        return new CorsFilter(source);
    }
}

// Yalnız SERVLET web tətbiqində (Tomcat/Jetty)
@Configuration
@ConditionalOnWebApplication(type = ConditionalOnWebApplication.Type.SERVLET)
public class ServletConfig {
    // Spring MVC konfiqurasiyası
}

// Yalnız REACTIVE web tətbiqində (Netty/WebFlux)
@Configuration
@ConditionalOnWebApplication(type = ConditionalOnWebApplication.Type.REACTIVE)
public class ReactiveConfig {
    // Spring WebFlux konfiqurasiyası
}

// Web tətbiqi DEYİLSƏ (batch, CLI tətbiqləri)
@Configuration
@ConditionalOnNotWebApplication
public class BatchJobConfig {
    @Bean
    public Job myBatchJob() {
        return new SimpleJob();
    }
}
```

---

## Custom @Conditional

### Sadə nümunə: Docker-də işləyirsə

```java
// Condition implementasiyası
public class DockerCondition implements Condition {

    @Override
    public boolean matches(ConditionContext context, AnnotatedTypeMetadata metadata) {
        // /.dockerenv faylı mövcuddursa Docker container-dəyik
        return new File("/.dockerenv").exists();
    }
}

// Meta-annotation kimi
@Target({ElementType.TYPE, ElementType.METHOD})
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Conditional(DockerCondition.class)
public @interface ConditionalOnDocker {}

// İstifadə
@Bean
@ConditionalOnDocker
public DockerMetricsCollector dockerMetrics() {
    return new DockerMetricsCollector();
}
```

### Daha güclü nümunə: Database tip yoxlaması

```java
// Konfiqurasiya annotation-ı
@Target({ElementType.TYPE, ElementType.METHOD})
@Retention(RetentionPolicy.RUNTIME)
@Conditional(OnDatabaseTypeCondition.class)
public @interface ConditionalOnDatabaseType {
    DatabaseType value();
}

public enum DatabaseType {
    POSTGRESQL, MYSQL, H2, MONGODB
}

// Condition implementasiyası
public class OnDatabaseTypeCondition implements Condition {

    @Override
    public boolean matches(ConditionContext context, AnnotatedTypeMetadata metadata) {
        // Annotation attribute-larını al
        Map<String, Object> attributes = metadata
            .getAnnotationAttributes(ConditionalOnDatabaseType.class.getName());

        if (attributes == null) return false;

        DatabaseType requiredType = (DatabaseType) attributes.get("value");

        // Property-dən database tipini oxu
        String dbUrl = context.getEnvironment()
            .getProperty("spring.datasource.url", "");

        return switch (requiredType) {
            case POSTGRESQL -> dbUrl.contains("postgresql") || dbUrl.contains("postgres");
            case MYSQL -> dbUrl.contains("mysql");
            case H2 -> dbUrl.contains("h2");
            case MONGODB -> context.getEnvironment()
                .containsProperty("spring.data.mongodb.uri");
        };
    }
}

// İstifadə
@Configuration
@ConditionalOnDatabaseType(DatabaseType.POSTGRESQL)
public class PostgreSqlOptimizations {

    @Bean
    public PostgreSqlDialect dialect() {
        return new PostgreSqlDialect();
    }

    @Bean
    public UUIDGenerator uuidGenerator() {
        return new PostgreSqlUUIDGenerator(); // PostgreSQL UUID tipini istifadə edir
    }
}

@Configuration
@ConditionalOnDatabaseType(DatabaseType.H2)
public class H2Config {

    @Bean
    public H2Console h2Console() {
        return new H2Console();
    }
}
```

### SpringBootCondition — daha ətraflı loglama

```java
// SpringBootCondition — daha yaxşı debug məlumatı
public class CloudReadinessCondition extends SpringBootCondition {

    @Override
    public ConditionOutcome getMatchOutcome(
            ConditionContext context,
            AnnotatedTypeMetadata metadata) {

        ConditionMessage.Builder message = ConditionMessage
            .forCondition("Cloud Readiness");

        Environment env = context.getEnvironment();

        // Kubernetes yoxla
        if (env.containsProperty("KUBERNETES_SERVICE_HOST")) {
            return ConditionOutcome.match(
                message.found("environment variable")
                    .items("KUBERNETES_SERVICE_HOST")
            );
        }

        // AWS yoxla
        if (env.containsProperty("AWS_EXECUTION_ENV")) {
            return ConditionOutcome.match(
                message.found("environment variable")
                    .items("AWS_EXECUTION_ENV")
            );
        }

        return ConditionOutcome.noMatch(
            message.didNotFind("cloud environment indicators").atAll()
        );
    }
}

@Target({ElementType.TYPE, ElementType.METHOD})
@Retention(RetentionPolicy.RUNTIME)
@Conditional(CloudReadinessCondition.class)
public @interface ConditionalOnCloud {}

// İstifadə
@Service
@ConditionalOnCloud
public class CloudMonitoringService {

    @PostConstruct
    public void init() {
        System.out.println("Cloud monitoring aktiv edildi");
    }
}
```

---

## Auto-configuration ilə inteqrasiya

```java
// Spring Boot auto-configuration yazmaq
// META-INF/spring/org.springframework.boot.autoconfigure.AutoConfiguration.imports:
// com.example.MyAutoConfiguration

@AutoConfiguration
@ConditionalOnClass(MyService.class)
@EnableConfigurationProperties(MyServiceProperties.class)
public class MyAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean(MyService.class)
    public MyService myService(MyServiceProperties properties) {
        return new DefaultMyService(
            properties.getApiKey(),
            properties.getTimeout()
        );
    }

    @Bean
    @ConditionalOnProperty(
        prefix = "myservice",
        name = "metrics-enabled",
        havingValue = "true"
    )
    public MyServiceMetrics myServiceMetrics() {
        return new MyServiceMetrics();
    }
}

@ConfigurationProperties(prefix = "myservice")
public class MyServiceProperties {
    private String apiKey;
    private int timeout = 5000;
    private boolean metricsEnabled = false;
    // getter/setter
}
```

---

## İntervyu Sualları

**S: @Conditional nə üçün istifadə olunur?**
C: Bean-ların şərtli yaradılması üçün — müəyyən class mövcud olanda, property müəyyən dəyər aldıqda, başqa bean mövcud olanda. Spring Boot auto-configuration tamamilə bu mexanizm üzərindədir.

**S: @ConditionalOnMissingBean niyə vacibdir?**
C: İstifadəçiyə öz bean-ını yaratmaq imkanı verir — əgər yaratmayıbsa, default bean yaranır. Auto-configuration-ın əsas prinsipidir: "istifadəçinin konfiqurasiyası auto-config-dən üstündür."

**S: @ConditionalOnProperty vs @Profile fərqi nədir?**
C: @Profile — spring.profiles.active-ə baxır, mühit bazalı. @ConditionalOnProperty — istənilən property dəyərinə baxır, daha çevik. Hər ikisi şərtli konfiqurasiya üçün, amma @ConditionalOnProperty daha fine-grained.

**S: Custom @Conditional annotation necə yaradılır?**
C: 1) Condition interface-ni implement et (matches metodu), 2) @Conditional meta-annotasiyası ilə öz annotasiyanı yarat, 3) İstifadə et. Daha yaxşı debug üçün SpringBootCondition-dan extend etmək tövsiyə olunur.

**S: @ConditionalOnClass niyə auto-configuration-da əvvəl gəlir?**
C: Əgər class classpath-də yoxdursa, konfigurasiya tamamilə atlanır — class yükləməyə belə cəhd edilmir. Bu, optional dependency-ləri idarə etməyin ən effektiv yoludur.

**S: ConditionContext nə verir?**
C: BeanFactory, Environment (properties/profiles), ResourceLoader, ClassLoader, BeanDefinitionRegistry. Bunlar vasitəsilə hər cür şərt yoxlanıla bilər.
