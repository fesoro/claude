# 002 — Spring Boot Auto-Configuration
**Səviyyə:** İrəli


## Mündəricat
1. [@SpringBootApplication annotasiyası](#springbootapplication)
2. [@EnableAutoConfiguration necə işləyir](#enableautoconfiguration)
3. [META-INF/spring/AutoConfiguration.imports — Spring Boot 3](#metainf)
4. [Auto-configuration exclusion](#exclusion)
5. [Auto-configuration ordering](#ordering)
6. [Debug rejimi və ConditionEvaluationReport](#debug)
7. [Öz auto-configuration-unuzu yazmaq](#custom)
8. [İntervyu Sualları](#intervyu)

---

## 1. @SpringBootApplication annotasiyası {#springbootapplication}

`@SpringBootApplication` — üç annotasiyanın birləşməsidir:

```java
// @SpringBootApplication aşağıdakıların ekvivalentidir:
@SpringBootConfiguration   // @Configuration-un alias-ı — bu sinif bean mənbəyidir
@EnableAutoConfiguration   // auto-config mexanizmini aktivləşdirir
@ComponentScan             // cari paketi və alt paketləri skan edir
public class MyApplication {
    public static void main(String[] args) {
        SpringApplication.run(MyApplication.class, args);
    }
}
```

### Hər birinin rolu:

| Annotasiya | Məqsəd |
|---|---|
| `@SpringBootConfiguration` | Sinfi Spring konfiqurasiya sinfi kimi işarələyir |
| `@EnableAutoConfiguration` | Classpath-ə baxaraq avtomatik bean-lar yaradır |
| `@ComponentScan` | `@Component`, `@Service`, `@Repository` və s. axtarır |

### Komponent skanının fərdiləşdirilməsi:

```java
@SpringBootApplication(
    // yalnız müəyyən paketləri skan et
    scanBasePackages = {"com.example.service", "com.example.repository"},
    // bəzi auto-config-ləri söndür
    exclude = {DataSourceAutoConfiguration.class}
)
public class MyApplication {
    public static void main(String[] args) {
        SpringApplication.run(MyApplication.class, args);
    }
}
```

---

## 2. @EnableAutoConfiguration necə işləyir {#enableautoconfiguration}

Auto-configuration mexanizmi **şərti bean yaratma** prinsipinə əsaslanır.
Spring Boot classpath-ə baxır, hansı kitabxanaların mövcud olduğunu müəyyən edir
və lazımi bean-ları avtomatik yaradır.

### İş prinsipi (ardıcıllıqla):

```
1. @SpringBootApplication işə düşür
2. @EnableAutoConfiguration aktiv olur
3. META-INF/spring/AutoConfiguration.imports oxunur
4. Hər auto-config sinfi üçün şərtlər yoxlanılır (@ConditionalOn...)
5. Şərtlər ödənilibsə — bean-lar yaradılır
6. İstifadəçinin öz bean-ları auto-config bean-larını override edir
```

### Şərti annotasiyalar nümunəsi:

```java
@AutoConfiguration
@ConditionalOnClass(DataSource.class)          // classpath-də DataSource varsa
@ConditionalOnMissingBean(DataSource.class)    // istifadəçi öz DataSource-unu yaratmayıbsa
@EnableConfigurationProperties(DataSourceProperties.class)
public class DataSourceAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean
    public DataSource dataSource(DataSourceProperties properties) {
        // avtomatik DataSource yarat — istifadəçi override etməyibsə
        return properties.initializeDataSourceBuilder().build();
    }
}
```

---

## 3. META-INF/spring/AutoConfiguration.imports (Spring Boot 3) {#metainf}

**Spring Boot 2.x** — `META-INF/spring.factories` (köhnə format)
**Spring Boot 3.x** — `META-INF/spring/org.springframework.boot.autoconfigure.AutoConfiguration.imports` (yeni format)

### Spring Boot 3.x formatı:

```
# Fayl: src/main/resources/META-INF/spring/
#        org.springframework.boot.autoconfigure.AutoConfiguration.imports
# Hər sətir bir auto-configuration sinfidir

com.example.MyFirstAutoConfiguration
com.example.MySecondAutoConfiguration
com.example.database.DatabaseAutoConfiguration
```

### Spring Boot 2.x (köhnə) formatı:

```properties
# Fayl: META-INF/spring.factories
org.springframework.boot.autoconfigure.EnableAutoConfiguration=\
  com.example.MyFirstAutoConfiguration,\
  com.example.MySecondAutoConfiguration
```

### @AutoConfiguration annotasiyası (Spring Boot 3.x):

```java
// YANLIŞ — Spring Boot 3.x-də köhnə üsul:
@Configuration
public class OldStyleAutoConfiguration {
    // spring.factories-ə əlavə edilməliydi
}

// DOĞRU — Spring Boot 3.x üsulu:
@AutoConfiguration  // @Configuration + auto-config metadata birlikdə
@ConditionalOnClass(SomeLibrary.class)
public class NewStyleAutoConfiguration {
    // AutoConfiguration.imports faylına əlavə et
}
```

### Niyə yeni format daha yaxşıdır?

- `spring.factories` bir çox məqsəd üçün istifadə olunurdu → qarışıqlıq
- Yeni format yalnız auto-configuration üçündür → aydınlıq
- Daha sürətli classpath skanı
- AOT (Ahead-of-Time) kompilyasiyasında daha yaxşı dəstək

---

## 4. Auto-configuration exclusion {#exclusion}

Bəzən bəzi auto-configuration-ları deaktiv etmək lazım olur.

### Üsul 1 — @SpringBootApplication-da (tövsiyə olunur):

```java
@SpringBootApplication(exclude = {
    DataSourceAutoConfiguration.class,        // DB auto-config-i söndür
    SecurityAutoConfiguration.class,          // Security auto-config-i söndür
    FlywayAutoConfiguration.class             // Flyway migration-u söndür
})
public class MyApplication {
    public static void main(String[] args) {
        SpringApplication.run(MyApplication.class, args);
    }
}
```

### Üsul 2 — application.properties-də:

```properties
# Tam sinif adı ilə exclude
spring.autoconfigure.exclude=\
  org.springframework.boot.autoconfigure.jdbc.DataSourceAutoConfiguration,\
  org.springframework.boot.autoconfigure.security.servlet.SecurityAutoConfiguration
```

### Üsul 3 — Test mühitində:

```java
// Test zamanı real DB lazım deyil — deaktiv et
@SpringBootTest
@SpringBootApplication(exclude = {
    DataSourceAutoConfiguration.class,
    JpaRepositoriesAutoConfiguration.class
})
class MyServiceUnitTest {
    @MockBean
    private UserRepository userRepository; // real DB əvəzinə mock

    @Autowired
    private UserService userService;

    @Test
    void testUserCreation() {
        // yalnız service logic test edilir
    }
}
```

---

## 5. Auto-configuration ordering {#ordering}

Bəzən auto-configuration sinifləri müəyyən sırada yüklənməlidir.

### @AutoConfigureBefore və @AutoConfigureAfter:

```java
// YANLIŞ — sıra təmin edilmir, DataSource olmadan cache işləməyə bilər:
@AutoConfiguration
public class CacheAutoConfiguration {
    @Bean
    public CacheManager cacheManager(DataSource dataSource) {
        return new JdbcCacheManager(dataSource); // DataSource mövcud deyilsə — XƏTA!
    }
}

// DOĞRU — DataSource konfiqurasiyasından SONRA yüklənir:
@AutoConfiguration
@AutoConfigureAfter(DataSourceAutoConfiguration.class)
public class CacheAutoConfiguration {

    @Bean
    @ConditionalOnBean(DataSource.class)  // DataSource mövcuddursa əlavə şərt
    public CacheManager cacheManager(DataSource dataSource) {
        // DataSource artıq hazırdır — təhlükəsiz
        return new JdbcCacheManager(dataSource);
    }
}
```

### @AutoConfigureBefore istifadəsi:

```java
// Bu auto-config DataSourceAutoConfiguration-dan ƏVVƏL yüklənir
@AutoConfiguration
@AutoConfigureBefore(DataSourceAutoConfiguration.class)
@ConditionalOnClass(EmbeddedDatabaseType.class)
public class EmbeddedDataSourceAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean
    public DataSource embeddedDataSource() {
        // Test üçün H2 in-memory DB — real DataSource-dan əvvəl
        return new EmbeddedDatabaseBuilder()
            .setType(EmbeddedDatabaseType.H2)
            .addScript("schema.sql")
            .build();
    }
}
```

### @AutoConfigureOrder (ədədi prioritet):

```java
@AutoConfiguration
@AutoConfigureOrder(Ordered.HIGHEST_PRECEDENCE)  // ən əvvəl yüklən
public class CriticalInfrastructureAutoConfiguration {
    // ...
}

@AutoConfiguration
@AutoConfigureOrder(Ordered.LOWEST_PRECEDENCE)   // ən axırda yüklən
public class OptionalFeatureAutoConfiguration {
    // ...
}
```

---

## 6. Debug rejimi və ConditionEvaluationReport {#debug}

### --debug flag ilə başlatmaq:

```bash
# Jar faylını debug rejimində işə sal
java -jar myapp.jar --debug

# Maven ilə:
mvn spring-boot:run -Dspring-boot.run.arguments=--debug

# Gradle ilə:
./gradlew bootRun --args='--debug'
```

### application.properties-də:

```properties
# Auto-configuration condition report-unu aktivləşdir
debug=true

# Yalnız logging səviyyəsini debug et (fərqlidir!):
logging.level.org.springframework.boot.autoconfigure=DEBUG
logging.level.org.springframework.boot.context.condition=DEBUG
```

### ConditionEvaluationReport çıxışının nümunəsi:

```
============================
CONDITIONS EVALUATION REPORT
============================

Positive matches (aktiv olan auto-config-lər):
-----------------
   DataSourceAutoConfiguration matched:
      - @ConditionalOnClass found required class 'javax.sql.DataSource'
      - @ConditionalOnMissingBean (types: javax.sql.DataSource) did not find any beans

   JacksonAutoConfiguration matched:
      - @ConditionalOnClass found required class 'com.fasterxml.jackson.databind.ObjectMapper'

Negative matches (deaktiv olan auto-config-lər):
-----------------
   ActiveMQAutoConfiguration:
      - @ConditionalOnClass did not find required class 'jakarta.jms.ConnectionFactory'

   RabbitAutoConfiguration:
      - @ConditionalOnClass did not find required class 'com.rabbitmq.client.Channel'

Exclusions (istifadəçi tərəfindən deaktiv edilənlər):
----------
   org.springframework.boot.autoconfigure.security.servlet.SecurityAutoConfiguration
```

### Proqramlı şəkildə report almaq:

```java
@Component
public class AutoConfigDebugLogger implements ApplicationRunner {

    @Autowired
    private ConfigurableApplicationContext context;

    @Override
    public void run(ApplicationArguments args) {
        // ConditionEvaluationReport-u birbaşa application context-dən al
        ConditionEvaluationReport report =
            ConditionEvaluationReport.get(context.getBeanFactory());

        System.out.println("=== AKTİV AUTO-CONFİGURATİON-LAR ===");
        report.getConditionAndOutcomesBySource().forEach((source, outcomes) -> {
            if (outcomes.isFullMatch()) {
                System.out.println("✓ " + source);
            }
        });

        System.out.println("\n=== DEAKTİV AUTO-CONFİGURATİON-LAR ===");
        report.getConditionAndOutcomesBySource().forEach((source, outcomes) -> {
            if (!outcomes.isFullMatch()) {
                System.out.println("✗ " + source);
                outcomes.forEach(outcome ->
                    System.out.println("  Səbəb: " + outcome.getOutcome().getMessage())
                );
            }
        });
    }
}
```

---

## 7. Öz auto-configuration-unuzu yazmaq {#custom}

### Addım 1: Properties sinifi

```java
// application.properties-dən konfiqurasiya oxu
@ConfigurationProperties(prefix = "mylib")
public class MyLibraryProperties {
    private String apiUrl = "https://default-api.example.com"; // default dəyər
    private int timeout = 5000;   // millisaniyə
    private String apiKey;        // məcburi — istifadəçi təyin etməlidir
    private boolean enabled = true;

    // getter/setter-lər
    public String getApiUrl() { return apiUrl; }
    public void setApiUrl(String apiUrl) { this.apiUrl = apiUrl; }
    public int getTimeout() { return timeout; }
    public void setTimeout(int timeout) { this.timeout = timeout; }
    public String getApiKey() { return apiKey; }
    public void setApiKey(String apiKey) { this.apiKey = apiKey; }
    public boolean isEnabled() { return enabled; }
    public void setEnabled(boolean enabled) { this.enabled = enabled; }
}
```

### Addım 2: Auto-configuration sinifi

```java
@AutoConfiguration
@ConditionalOnClass(MyLibraryClient.class)  // kitabxana classpath-də mövcuddur
@ConditionalOnProperty(
    prefix = "mylib",
    name = "enabled",
    havingValue = "true",
    matchIfMissing = true   // property olmasa da aktiv et
)
@EnableConfigurationProperties(MyLibraryProperties.class)
public class MyLibraryAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean   // istifadəçi öz bean-ını yaratmayıbsa
    public MyLibraryClient myLibraryClient(MyLibraryProperties properties) {
        return MyLibraryClient.builder()
            .apiUrl(properties.getApiUrl())
            .timeout(Duration.ofMillis(properties.getTimeout()))
            .apiKey(properties.getApiKey())
            .build();
    }

    @Bean
    @ConditionalOnMissingBean
    @ConditionalOnBean(MyLibraryClient.class)  // client mövcuddursa health check əlavə et
    public MyLibraryHealthIndicator myLibraryHealthIndicator(MyLibraryClient client) {
        return new MyLibraryHealthIndicator(client);
    }
}
```

### Addım 3: İmport faylı

```
# src/main/resources/META-INF/spring/
# org.springframework.boot.autoconfigure.AutoConfiguration.imports

com.example.mylibrary.autoconfigure.MyLibraryAutoConfiguration
```

### Addım 4: İstifadəçi tərəfindən konfiqurasiya

```properties
# application.properties
mylib.api-url=https://my-custom-api.com
mylib.api-key=secret-key-123
mylib.timeout=3000
```

```java
// İstifadəçi heç nə etmədən client inject edilir
@Service
public class MyService {
    private final MyLibraryClient client;

    public MyService(MyLibraryClient client) {
        this.client = client; // avtomatik inject
    }

    public String fetchData() {
        return client.getData();
    }
}
```

---

## 8. Şərti annotasiyaların tam siyahısı

| Annotasiya | Şərt |
|---|---|
| `@ConditionalOnClass` | Sinif classpath-də mövcuddur |
| `@ConditionalOnMissingClass` | Sinif classpath-də yoxdur |
| `@ConditionalOnBean` | Müəyyən tip bean artıq mövcuddur |
| `@ConditionalOnMissingBean` | Həmin tip bean hələ yaradılmayıb |
| `@ConditionalOnProperty` | Property müəyyən dəyərə malikdir |
| `@ConditionalOnResource` | Resurs fayl classpath-də mövcuddur |
| `@ConditionalOnWebApplication` | Web application kontekstidir |
| `@ConditionalOnNotWebApplication` | Web application konteksti deyil |
| `@ConditionalOnExpression` | SpEL ifadəsi `true` qaytarır |
| `@ConditionalOnJava` | Java versiyası şərtə uyğundur |
| `@ConditionalOnSingleCandidate` | Yalnız bir bean kandidatı var |

---

## İntervyu Sualları {#intervyu}

**S: @SpringBootApplication hansı annotasiyalardan ibarətdir?**
C: Üç annotasiyanın birləşməsidir: `@SpringBootConfiguration` (bu sinif konfiqurasiya mənbəyidir), `@EnableAutoConfiguration` (classpath-ə görə avtomatik bean yaradır), `@ComponentScan` (`@Component` və s. annotasiyalı sinifləri axtarır).

**S: Auto-configuration Spring Boot 2.x və 3.x-də necə fərqlənir?**
C: Spring Boot 2.x-də `META-INF/spring.factories` faylının `EnableAutoConfiguration` açarı altında; Spring Boot 3.x-də isə `META-INF/spring/org.springframework.boot.autoconfigure.AutoConfiguration.imports` faylında qeyd olunur. `@AutoConfiguration` annotasiyası da Spring Boot 3-də gəldi.

**S: @ConditionalOnMissingBean nədir və niyə vacibdir?**
C: Əgər istifadəçi özü eyni tipli bean yaratmayıbsa, auto-configuration default bean yaradır. Bu "convention over configuration" prinsipidir — heç nə etməsən default işləyir, istəsən override edə bilərsən.

**S: Auto-configuration-u necə debug edə bilərsiniz?**
C: `--debug` flag-ı ilə tətbiqi başlatmaq və ya `application.properties`-ə `debug=true` əlavə etmək. Nəticədə `ConditionEvaluationReport` çıxarılır: hansı auto-config-lərin aktiv olduğunu, hansıların olmadığını və səbəbini göstərir.

**S: @AutoConfigureBefore/@AutoConfigureAfter nə üçün lazımdır?**
C: Auto-configuration sinifləri arasında yükləmə sırasını müəyyən etmək üçün. Məsələn, Cache konfiqurasiyası DataSource-dan sonra yüklənməlidir — `@AutoConfigureAfter(DataSourceAutoConfiguration.class)`.

**S: Spring Boot auto-configuration-un "magic"ini override etmək üçün nə etmək lazımdır?**
C: Sadəcə həmin tipdə öz bean-ınızı yaratmaq kifayətdir. `@ConditionalOnMissingBean` sayəsində auto-configuration sizin bean-ınızı görüb özününü yaratmayacaq.
