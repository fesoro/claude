# 65. Spring Profiles

## Mündəricat
1. [@Profile annotation](#profile-annotation)
2. [Profile aktivasiyası](#profile-aktivasiyası)
3. [Profile-specific properties](#profile-specific-properties)
4. [@ActiveProfiles testlərdə](#activeprofiles-testlərdə)
5. [Default profile](#default-profile)
6. [Proqramatik aktivasiya](#proqramatik-aktivasiya)
7. [Multi-profile beans](#multi-profile-beans)
8. [İntervyu Sualları](#intervyu-sualları)

---

## @Profile annotation

`@Profile` — bean-ın yalnız müəyyən profil aktiv olanda yaranmasını təmin edir.

```java
// Development verilənlər bazası
@Configuration
@Profile("dev")
public class DevDatabaseConfig {

    @Bean
    public DataSource dataSource() {
        // H2 in-memory — development üçün
        return new EmbeddedDatabaseBuilder()
            .setType(EmbeddedDatabaseType.H2)
            .addScript("classpath:schema.sql")
            .addScript("classpath:data.sql") // Test data
            .build();
    }
}

// Production verilənlər bazası
@Configuration
@Profile("prod")
public class ProdDatabaseConfig {

    @Value("${spring.datasource.url}")
    private String url;

    @Value("${spring.datasource.username}")
    private String username;

    @Value("${spring.datasource.password}")
    private String password;

    @Bean
    public DataSource dataSource() {
        // PostgreSQL — production üçün
        HikariConfig config = new HikariConfig();
        config.setJdbcUrl(url);
        config.setUsername(username);
        config.setPassword(password);
        config.setMaximumPoolSize(50);
        return new HikariDataSource(config);
    }
}

// Servis səviyyəsində @Profile
public interface PaymentGateway {
    PaymentResult charge(String customerId, BigDecimal amount);
}

@Service
@Profile("prod")
public class StripePaymentGateway implements PaymentGateway {
    @Override
    public PaymentResult charge(String customerId, BigDecimal amount) {
        // Real Stripe API
        return stripeClient.charge(customerId, amount);
    }
}

@Service
@Profile({"dev", "test"})
public class MockPaymentGateway implements PaymentGateway {
    @Override
    public PaymentResult charge(String customerId, BigDecimal amount) {
        // Fake uğurlu ödəniş — development/test üçün
        return PaymentResult.success("mock-transaction-" + UUID.randomUUID());
    }
}
```

### @Profile məntiqi operatorlar (Spring 5.1+)

```java
// NOT operatoru — production deyilsə
@Bean
@Profile("!prod")
public DataSource mockDataSource() {
    return new EmbeddedDatabaseBuilder()
        .setType(EmbeddedDatabaseType.H2)
        .build();
}

// AND operatoru — həm dev, həm da cloud aktiv olmalıdır
@Bean
@Profile("dev & cloud")
public CloudDevelopmentService cloudDevService() {
    return new CloudDevelopmentService();
}

// OR operatoru — ya dev, ya da staging
@Bean
@Profile("dev | staging")
public DebugService debugService() {
    return new DebugService();
}

// Mürəkkəb ifadə
@Bean
@Profile("(dev | staging) & !mock")
public RealApiService realApiService() {
    return new RealApiService();
}
```

---

## Profile aktivasiyası

### application.properties / application.yml ilə

```properties
# application.properties
spring.profiles.active=dev

# Birdən çox profil
spring.profiles.active=dev,swagger,metrics
```

```yaml
# application.yml
spring:
  profiles:
    active: dev
    # Birdən çox profil
    # active: dev,swagger,metrics
```

### JVM argument ilə

```bash
# Command line
java -Dspring.profiles.active=prod -jar app.jar

# Maven Wrapper
./mvnw spring-boot:run -Dspring-boot.run.profiles=dev

# Gradle
./gradlew bootRun --args='--spring.profiles.active=dev'
```

### Environment variable ilə

```bash
# Linux/Mac
export SPRING_PROFILES_ACTIVE=prod
java -jar app.jar

# Docker
docker run -e SPRING_PROFILES_ACTIVE=prod myapp

# Docker Compose
services:
  app:
    image: myapp
    environment:
      - SPRING_PROFILES_ACTIVE=prod,monitoring
```

### spring.profiles.include

```yaml
# application-dev.yml
spring:
  profiles:
    include:
      - logging     # application-logging.yml da yüklənir
      - swagger     # application-swagger.yml da yüklənir

# Nəticə: dev + logging + swagger profillər aktiv olur
```

---

## Profile-specific properties

```
src/main/resources/
├── application.yml              # Ümumi parametrlər (həmişə yüklənir)
├── application-dev.yml          # spring.profiles.active=dev olduqda
├── application-test.yml         # spring.profiles.active=test olduqda
├── application-staging.yml      # spring.profiles.active=staging olduqda
├── application-prod.yml         # spring.profiles.active=prod olduqda
└── application-local.yml        # Local development (git-ignore)
```

```yaml
# application.yml — bütün profillər üçün ümumi
spring:
  application:
    name: myapp
  
  jpa:
    open-in-view: false

app:
  name: MyApplication
  
logging:
  pattern:
    console: "%d{HH:mm:ss} [%thread] %-5level %logger{36} - %msg%n"
```

```yaml
# application-dev.yml — development
spring:
  datasource:
    url: jdbc:h2:mem:devdb
    driver-class-name: org.h2.Driver
    username: sa
    password:
  
  h2:
    console:
      enabled: true
      path: /h2-console
  
  jpa:
    show-sql: true
    hibernate:
      ddl-auto: create-drop
    properties:
      hibernate:
        format_sql: true

logging:
  level:
    com.example: DEBUG
    org.springframework.web: DEBUG
    org.hibernate.SQL: DEBUG

app:
  base-url: http://localhost:8080
```

```yaml
# application-prod.yml — production
spring:
  datasource:
    url: ${DATABASE_URL}
    username: ${DATABASE_USERNAME}
    password: ${DATABASE_PASSWORD}
    hikari:
      maximum-pool-size: 50
      minimum-idle: 10
      connection-timeout: 30000
      idle-timeout: 600000
      max-lifetime: 1800000
  
  jpa:
    show-sql: false
    hibernate:
      ddl-auto: validate

logging:
  level:
    root: WARN
    com.example: INFO

server:
  tomcat:
    threads:
      max: 200

management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics
```

---

## @ActiveProfiles testlərdə

```java
// Test profili aktivasiyası
@SpringBootTest
@ActiveProfiles("test")
class UserServiceTest {

    @Autowired
    private UserService userService;

    // Test profili aktiv — MockPaymentGateway inject edilir
    @Autowired
    private PaymentGateway paymentGateway; // MockPaymentGateway olacaq

    @Test
    void shouldCreateUser() {
        User user = userService.create("test@example.com");
        assertNotNull(user.getId());
    }
}

// Birdən çox profil
@SpringBootTest
@ActiveProfiles({"test", "mock-email"})
class OrderServiceIntegrationTest {

    @Autowired
    private OrderService orderService;

    @Test
    void shouldPlaceOrder() {
        Order order = orderService.place(testCart());
        assertEquals(OrderStatus.CONFIRMED, order.getStatus());
    }
}

// @TestPropertySource ilə kombinasiya
@SpringBootTest
@ActiveProfiles("test")
@TestPropertySource(properties = {
    "app.payment.enabled=false",
    "app.email.dry-run=true"
})
class PaymentTest {
    // Test-specific override-lar
}
```

### Test konfiqurasiya sinifləri

```java
// Yalnız test-də istifadə olunan konfiqurasiya
@TestConfiguration
public class TestConfig {

    @Bean
    @Primary
    public EmailService mockEmailService() {
        // Real email göndərmək yerinə mock
        return new MockEmailService();
    }

    @Bean
    public WireMockServer wireMockServer() {
        // HTTP mock server
        WireMockServer server = new WireMockServer(8089);
        server.start();
        return server;
    }
}

@SpringBootTest
@Import(TestConfig.class) // Test konfiqurasiyasını import et
class ExternalApiTest {

    @Autowired
    private ExternalApiClient apiClient;

    @Autowired
    private WireMockServer wireMockServer;

    @Test
    void shouldCallExternalApi() {
        // WireMock ilə HTTP stub
        wireMockServer.stubFor(
            get(urlEqualTo("/api/users"))
                .willReturn(aResponse()
                    .withStatus(200)
                    .withBody("[{\"id\":1,\"name\":\"Ali\"}]"))
        );

        List<User> users = apiClient.getUsers();
        assertEquals(1, users.size());
    }
}
```

---

## Default profile

```java
// Default profil — heç bir profil aktiv deyilsə istifadə olunur
@Configuration
@Profile("default")
public class DefaultConfig {

    @Bean
    public DataSource dataSource() {
        // Heç bir profil yoxdursa — H2 istifadə et
        return new EmbeddedDatabaseBuilder()
            .setType(EmbeddedDatabaseType.H2)
            .build();
    }
}

// spring.profiles.default dəyişdirmək
spring.profiles.default=dev
```

---

## Proqramatik aktivasiya

```java
// SpringApplication ilə
@SpringBootApplication
public class MyApplication {

    public static void main(String[] args) {
        SpringApplication app = new SpringApplication(MyApplication.class);

        // Mühitə görə proqramatik seçim
        String env = System.getenv("APP_ENV");
        if (env != null) {
            app.setAdditionalProfiles(env);
        } else {
            app.setAdditionalProfiles("local");
        }

        app.run(args);
    }
}

// ApplicationContextInitializer ilə
public class ProfileInitializer
        implements ApplicationContextInitializer<ConfigurableApplicationContext> {

    @Override
    public void initialize(ConfigurableApplicationContext context) {
        ConfigurableEnvironment env = context.getEnvironment();

        // Cloud mühitini yoxlamaq
        if (isRunningOnAWS()) {
            env.addActiveProfile("aws");
            env.addActiveProfile("prod");
        } else if (isRunningOnKubernetes()) {
            env.addActiveProfile("kubernetes");
        } else {
            env.addActiveProfile("local");
        }
    }

    private boolean isRunningOnAWS() {
        return System.getenv("AWS_EXECUTION_ENV") != null;
    }

    private boolean isRunningOnKubernetes() {
        return System.getenv("KUBERNETES_SERVICE_HOST") != null;
    }
}

// Qeydiyyat
// application.properties:
// context.initializer.classes=com.example.ProfileInitializer
```

---

## Multi-profile beans

```java
@Configuration
public class MultiProfileBeanConfig {

    // Yalnız dev profili
    @Bean
    @Profile("dev")
    public DataInitializer devDataInitializer() {
        return new DevDataInitializer(); // Test data yükləyir
    }

    // dev VƏ test profillərindən birinin aktiv olması lazımdır
    @Bean
    @Profile({"dev", "test"})
    public MockExternalService mockExternalService() {
        return new MockExternalService();
    }

    // prod deyilsə
    @Bean
    @Profile("!prod")
    public SwaggerConfig swaggerConfig() {
        return new SwaggerConfig(); // Swagger yalnız non-prod-da
    }

    // Production üçün
    @Bean
    @Profile("prod")
    public SslConfig sslConfig() {
        return new SslConfig();
    }
}

// @Profile ilə @Component
@Component
@Profile("dev")
public class DevHealthIndicator implements HealthIndicator {

    @Override
    public Health health() {
        return Health.up()
            .withDetail("mode", "development")
            .withDetail("database", "H2 in-memory")
            .build();
    }
}

@Component
@Profile("prod")
public class ProdHealthIndicator implements HealthIndicator {

    @Autowired
    private DataSource dataSource;

    @Override
    public Health health() {
        try (Connection conn = dataSource.getConnection()) {
            return Health.up()
                .withDetail("mode", "production")
                .withDetail("db-status", "connected")
                .build();
        } catch (SQLException e) {
            return Health.down(e).build();
        }
    }
}
```

---

## Tam nümunə: Multi-environment konfigurasiya

```java
// Servis interfeysi
public interface StorageService {
    String upload(String fileName, byte[] content);
    byte[] download(String fileKey);
    void delete(String fileKey);
}

// Local development — fayl sistemi
@Service
@Profile({"dev", "local"})
@Slf4j
public class LocalFileStorageService implements StorageService {

    private final Path storageDir = Path.of("/tmp/app-storage");

    @PostConstruct
    public void init() throws IOException {
        Files.createDirectories(storageDir);
        log.info("Local storage: {}", storageDir);
    }

    @Override
    public String upload(String fileName, byte[] content) {
        try {
            Path filePath = storageDir.resolve(fileName);
            Files.write(filePath, content);
            return filePath.toString();
        } catch (IOException e) {
            throw new StorageException("Upload uğursuz", e);
        }
    }

    @Override
    public byte[] download(String fileKey) {
        try {
            return Files.readAllBytes(Path.of(fileKey));
        } catch (IOException e) {
            throw new StorageException("Download uğursuz", e);
        }
    }

    @Override
    public void delete(String fileKey) {
        try {
            Files.deleteIfExists(Path.of(fileKey));
        } catch (IOException e) {
            throw new StorageException("Silmə uğursuz", e);
        }
    }
}

// Test — in-memory
@Service
@Profile("test")
public class InMemoryStorageService implements StorageService {

    private final Map<String, byte[]> storage = new ConcurrentHashMap<>();

    @Override
    public String upload(String fileName, byte[] content) {
        storage.put(fileName, content);
        return fileName;
    }

    @Override
    public byte[] download(String fileKey) {
        byte[] content = storage.get(fileKey);
        if (content == null) throw new StorageException("Fayl tapılmadı: " + fileKey);
        return content;
    }

    @Override
    public void delete(String fileKey) {
        storage.remove(fileKey);
    }
}

// Production — AWS S3
@Service
@Profile("prod")
@RequiredArgsConstructor
@Slf4j
public class S3StorageService implements StorageService {

    private final S3Client s3Client;

    @Value("${aws.s3.bucket-name}")
    private String bucketName;

    @Override
    public String upload(String fileName, byte[] content) {
        s3Client.putObject(
            PutObjectRequest.builder()
                .bucket(bucketName)
                .key(fileName)
                .build(),
            RequestBody.fromBytes(content)
        );
        log.info("S3-ə yükləndi: {}/{}", bucketName, fileName);
        return "s3://" + bucketName + "/" + fileName;
    }

    @Override
    public byte[] download(String fileKey) {
        ResponseBytes<GetObjectResponse> response = s3Client.getObjectAsBytes(
            GetObjectRequest.builder()
                .bucket(bucketName)
                .key(fileKey)
                .build()
        );
        return response.asByteArray();
    }

    @Override
    public void delete(String fileKey) {
        s3Client.deleteObject(
            DeleteObjectRequest.builder()
                .bucket(bucketName)
                .key(fileKey)
                .build()
        );
    }
}

// Profilidən asılı olmayaraq eyni servis istifadə edilir
@Service
@RequiredArgsConstructor
public class DocumentService {

    // Profile-a görə LocalFileStorageService, InMemoryStorageService,
    // ya da S3StorageService inject edilir
    private final StorageService storageService;

    public String saveDocument(Document document) {
        byte[] content = document.toBytes();
        return storageService.upload(document.getName(), content);
    }
}
```

---

## İntervyu Sualları

**S: Spring Profile-lar nə üçün istifadə olunur?**
C: Müxtəlif mühitlər (dev, test, prod) üçün müxtəlif bean-ları aktivləşdirmək. Məsələn, development-da H2 database, production-da PostgreSQL; development-da mock payment, production-da real Stripe API.

**S: Profile-ı necə aktivləşdirmək olar?**
C: 1) application.properties/yml: spring.profiles.active=dev, 2) JVM argument: -Dspring.profiles.active=prod, 3) Environment variable: SPRING_PROFILES_ACTIVE=prod, 4) SpringApplication.setAdditionalProfiles(), 5) @ActiveProfiles test annotasiyası.

**S: spring.profiles.include nə edir?**
C: Profile aktivasiya zamanı əlavə profillər də aktivləşdirir. Məsələn, dev profili aktiv olanda logging və swagger profillərini də aktiv etmək üçün: `spring.profiles.include: logging,swagger`.

**S: Default profile nədir?**
C: Heç bir profil aktiv olmadıqda "default" adlı profil aktiv sayılır. `@Profile("default")` annotasiyası ilə yalnız bu vəziyyətdə yaranacaq bean-lar yaratmaq olar.

**S: Testlərdə profile-ı necə idarə edirik?**
C: @ActiveProfiles("test") — test profili aktiv edir. @TestConfiguration — yalnız testlərdə istifadə olunan bean-lar. @MockBean — Spring bean-ını Mockito mock ilə əvəz edir. @TestPropertySource — test üçün property override.

**S: @Profile ilə @Conditional fərqi nədir?**
C: @Profile — spring.profiles.active dəyərinə əsaslanır, sadə. @Conditional — istənilən şərtə əsaslanır (class mövcudluğu, bean mövcudluğu, property dəyəri, vs.). @Profile əslində @Conditional-ın xüsusi halıdır.
