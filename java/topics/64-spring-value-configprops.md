# 64. Spring @Value və @ConfigurationProperties

## Mündəricat
1. [@Value annotation](#value-annotation)
2. [SpEL ilə @Value](#spel-ilə-value)
3. [@ConfigurationProperties](#configurationproperties)
4. [Relaxed Binding](#relaxed-binding)
5. [Validation](#validation)
6. [application.yml vs application.properties](#applicationyml-vs-applicationproperties)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @Value annotation

```java
// application.properties:
# server.port=8080
# app.name=MyApplication
# app.timeout=30
# app.feature.enabled=true
# app.allowed-origins=http://localhost:3000,http://localhost:4200

@Component
public class AppProperties {

    // Sadə string
    @Value("${app.name}")
    private String appName;

    // Integer
    @Value("${server.port}")
    private int serverPort;

    // Boolean
    @Value("${app.feature.enabled}")
    private boolean featureEnabled;

    // Default dəyər — property tapılmasa istifadə olunur
    @Value("${app.timeout:30}")
    private int timeout;

    // Mövcud olmayan property — default ilə
    @Value("${app.max-connections:100}")
    private int maxConnections;

    // List
    @Value("${app.allowed-origins}")
    private List<String> allowedOrigins;

    // Array
    @Value("${app.allowed-origins}")
    private String[] allowedOriginsArray;

    // System property
    @Value("${user.home}")
    private String userHome;

    // Environment variable
    @Value("${JAVA_HOME:#{null}}")
    private String javaHome;
}
```

### Constructor və @Bean metodlarında @Value

```java
@Service
public class EmailService {

    private final String smtpHost;
    private final int smtpPort;
    private final String fromEmail;

    // Constructor injection ilə @Value
    public EmailService(
            @Value("${mail.smtp.host}") String smtpHost,
            @Value("${mail.smtp.port:587}") int smtpPort,
            @Value("${mail.from:noreply@example.com}") String fromEmail) {
        this.smtpHost = smtpHost;
        this.smtpPort = smtpPort;
        this.fromEmail = fromEmail;
    }
}

@Configuration
public class Config {

    // @Bean metodunda @Value
    @Bean
    public RestTemplate restTemplate(
            @Value("${api.timeout:5000}") int timeout) {
        RestTemplate template = new RestTemplate();
        // Timeout konfiqurasiyası
        HttpComponentsClientHttpRequestFactory factory =
            new HttpComponentsClientHttpRequestFactory();
        factory.setConnectTimeout(timeout);
        template.setRequestFactory(factory);
        return template;
    }
}
```

---

## SpEL ilə @Value

Spring Expression Language (SpEL) — güclü ifadə dili.

```java
@Component
public class SpelExamples {

    // Literal dəyər
    @Value("#{42}")
    private int number;

    // Riyazi ifadə
    @Value("#{10 * 2 + 5}")
    private int calculated;

    // Başqa bean-in metodu
    @Value("#{systemProperties['user.name']}")
    private String systemUser;

    // System environment
    @Value("#{environment['HOSTNAME']}")
    private String hostname;

    // Şərti ifadə (ternary)
    @Value("#{${app.debug:false} ? 'DEBUG' : 'PRODUCTION'}")
    private String mode;

    // Bean-in metodu çağırmaq
    @Value("#{appConfig.getDatabaseUrl().toUpperCase()}")
    private String dbUrlUpper;

    // Kombinasiya — property + SpEL
    @Value("#{${app.base-url} + '/api/v1'}")
    private String apiUrl;

    // List yaratmaq
    @Value("#{'${app.roles}'.split(',')}")
    private List<String> roles;

    // Map yaratmaq
    @Value("#{${app.config:{key1:'val1',key2:'val2'}}}")
    private Map<String, String> configMap;
}
```

---

## @ConfigurationProperties

**Tövsiyə olunan yanaşma** — type-safe, structured konfiqurasiya.

```yaml
# application.yml
app:
  name: MyApplication
  version: 1.0.0
  mail:
    host: smtp.gmail.com
    port: 587
    username: app@gmail.com
    password: secret
    properties:
      auth: true
      starttls: true
  database:
    url: jdbc:postgresql://localhost:5432/mydb
    max-pool-size: 20
    min-idle: 5
    connection-timeout: 30000
  security:
    jwt-secret: mySecretKey
    token-expiration: 86400
    allowed-origins:
      - http://localhost:3000
      - http://localhost:4200
```

```java
// @ConfigurationProperties sinifi
@ConfigurationProperties(prefix = "app")
@Validated // Bean Validation aktivdir
public class AppConfig {

    @NotBlank
    private String name;

    private String version;

    @Valid // Nested obyekti də validate et
    private Mail mail = new Mail();

    @Valid
    private Database database = new Database();

    @Valid
    private Security security = new Security();

    // Getter/setter — binding üçün lazımdır
    // Lombok @Data ilə qısaltmaq olar

    @Data
    public static class Mail {
        @NotBlank
        private String host;

        @Min(1) @Max(65535)
        private int port = 587;

        private String username;
        private String password;
        private Map<String, String> properties = new HashMap<>();
    }

    @Data
    public static class Database {
        @NotBlank
        private String url;

        @Positive
        private int maxPoolSize = 10;

        @PositiveOrZero
        private int minIdle = 2;

        private long connectionTimeout = 30000;
    }

    @Data
    public static class Security {
        @NotBlank
        private String jwtSecret;

        @Positive
        private long tokenExpiration = 86400;

        private List<String> allowedOrigins = new ArrayList<>();
    }
}
```

### @EnableConfigurationProperties

```java
// Yanaşma 1 — @Component ilə
@ConfigurationProperties(prefix = "app")
@Component // Avtomatik bean kimi qeydiyyat
@Validated
public class AppConfig { ... }

// Yanaşma 2 — @EnableConfigurationProperties ilə
@ConfigurationProperties(prefix = "app")
@Validated
public class AppConfig { ... } // @Component yoxdur

@Configuration
@EnableConfigurationProperties(AppConfig.class) // Bunu bean kimi qeydiyyat et
public class AppConfiguration { ... }

// Yanaşma 3 — Spring Boot 2.2+ @ConfigurationPropertiesScan
@SpringBootApplication
@ConfigurationPropertiesScan("com.example.config") // Paketi scan et
public class MyApp { ... }
```

### Record ilə @ConfigurationProperties (Java 16+)

```java
// Immutable konfigurasiya — record ilə
@ConfigurationProperties(prefix = "app.mail")
public record MailConfig(
    @NotBlank String host,
    @Min(1) @Max(65535) int port,
    String username,
    String password
) {
    // Compact constructor — default dəyərlər
    public MailConfig {
        if (port == 0) port = 587;
    }
}

// İstifadə
@Service
@RequiredArgsConstructor
public class EmailService {
    private final MailConfig mailConfig;

    public void sendEmail(String to, String subject, String body) {
        // mailConfig.host(), mailConfig.port() istifadə et
        System.out.printf("Email %s:%d vasitəsilə göndərilir%n",
            mailConfig.host(), mailConfig.port());
    }
}
```

---

## Relaxed Binding

Spring Boot properties binding üçün flexibel format dəstəkləyir:

```yaml
# Bu formatların hamısı eyni sahəyə bind olur
app:
  max-pool-size: 20      # kebab-case (tövsiyə olunan YAML üçün)
  maxPoolSize: 20        # camelCase
  max_pool_size: 20      # underscore
  MAX_POOL_SIZE: 20      # UPPER_CASE (environment variable üçün)
```

```java
@ConfigurationProperties(prefix = "app")
public class AppConfig {
    // Yuxarıdakı formatların hamısı bu sahəyə bind olur
    private int maxPoolSize;
    // ...
}
```

```bash
# Environment variable — maksimum uyğunluq
export APP_MAX_POOL_SIZE=50
export APP_MAIL_HOST=smtp.gmail.com

# System property
java -Dapp.max-pool-size=50 -jar app.jar

# Command line argument
java -jar app.jar --app.max-pool-size=50
```

---

## Validation

```java
// pom.xml — spring-boot-starter-validation lazımdır
// <dependency>
//     <groupId>org.springframework.boot</groupId>
//     <artifactId>spring-boot-starter-validation</artifactId>
// </dependency>

@ConfigurationProperties(prefix = "payment")
@Validated
public class PaymentConfig {

    @NotBlank(message = "API key boş ola bilməz")
    private String apiKey;

    @URL(message = "Düzgün URL daxil edin")
    private String gatewayUrl;

    @Min(value = 1, message = "Retry sayı ən az 1 olmalıdır")
    @Max(value = 10, message = "Retry sayı 10-dan çox ola bilməz")
    private int retryCount = 3;

    @NotNull
    @Valid
    private Timeout timeout = new Timeout();

    @Data
    public static class Timeout {
        @Positive
        private int connect = 5000;

        @Positive
        private int read = 10000;
    }
}

// Validation xətası — startup-da aşkar olunur
// BindValidationException: 
//   - payment.api-key: API key boş ola bilməz
//   - payment.gateway-url: Düzgün URL daxil edin
```

---

## application.yml vs application.properties

### application.properties

```properties
# Sadə key=value formatı
spring.datasource.url=jdbc:postgresql://localhost:5432/mydb
spring.datasource.username=postgres
spring.datasource.password=secret

# Array
app.allowed-origins[0]=http://localhost:3000
app.allowed-origins[1]=http://localhost:4200

# Map
app.headers.X-API-Key=abc123
app.headers.X-App-Version=1.0
```

### application.yml

```yaml
# Hierarxik, oxumaq daha rahat
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/mydb
    username: postgres
    password: secret

app:
  # Array — daha oxunaqlı
  allowed-origins:
    - http://localhost:3000
    - http://localhost:4200
  
  # Map
  headers:
    X-API-Key: abc123
    X-App-Version: "1.0"
  
  # Multiline string
  description: |
    Bu çox sətirli
    bir mətndir.
```

### Profile-specific fayllar

```
src/main/resources/
├── application.yml          # Ümumi (bütün profillər)
├── application-dev.yml      # Development
├── application-prod.yml     # Production
├── application-test.yml     # Test
└── application-local.yml    # Local development
```

```yaml
# application.yml — ümumi parametrlər
app:
  name: MyApp

spring:
  jpa:
    show-sql: false

---
# application-dev.yml — development üçün
spring:
  datasource:
    url: jdbc:h2:mem:devdb
    driver-class-name: org.h2.Driver
  
  jpa:
    show-sql: true
    hibernate:
      ddl-auto: create-drop
  
  h2:
    console:
      enabled: true

logging:
  level:
    com.example: DEBUG

---
# application-prod.yml — production üçün
spring:
  datasource:
    url: jdbc:postgresql://prod-db:5432/mydb
    username: ${DB_USERNAME}    # Environment variable
    password: ${DB_PASSWORD}
    hikari:
      maximum-pool-size: 50
  
  jpa:
    hibernate:
      ddl-auto: validate

logging:
  level:
    com.example: WARN
    root: ERROR
```

---

## Tam nümunə: Type-safe konfiqurasiya sistemi

```java
// Tətbiqin tam konfiqurasiyası
@ConfigurationProperties(prefix = "myapp")
@Validated
@Data
public class MyAppProperties {

    @Valid
    @NotNull
    private Server server = new Server();

    @Valid
    @NotNull
    private Database database = new Database();

    @Valid
    private Cache cache = new Cache();

    @Valid
    private External external = new External();

    @Data
    public static class Server {
        @Positive
        private int port = 8080;

        @NotBlank
        private String contextPath = "/";

        private boolean sslEnabled = false;
    }

    @Data
    public static class Database {
        @NotBlank
        private String url;

        @NotBlank
        private String username;

        private String password;

        @Positive
        private int poolSize = 10;

        @Pattern(regexp = "validate|update|create|create-drop|none")
        private String ddlAuto = "validate";
    }

    @Data
    public static class Cache {
        private boolean enabled = true;

        @Positive
        private long ttlSeconds = 600;

        @Positive
        private long maxSize = 1000;
    }

    @Data
    public static class External {
        private Map<String, ApiConfig> apis = new HashMap<>();

        @Data
        public static class ApiConfig {
            @URL
            private String baseUrl;

            @NotBlank
            private String apiKey;

            @Positive
            private int timeoutMs = 5000;
        }
    }
}

// application.yml
/*
myapp:
  server:
    port: 8080
    context-path: /api
    ssl-enabled: false
  
  database:
    url: jdbc:postgresql://localhost:5432/mydb
    username: postgres
    password: ${DB_PASSWORD}
    pool-size: 20
    ddl-auto: validate
  
  cache:
    enabled: true
    ttl-seconds: 300
    max-size: 5000
  
  external:
    apis:
      stripe:
        base-url: https://api.stripe.com
        api-key: ${STRIPE_API_KEY}
        timeout-ms: 10000
      sendgrid:
        base-url: https://api.sendgrid.com
        api-key: ${SENDGRID_API_KEY}
        timeout-ms: 5000
*/

// İstifadə
@Service
@RequiredArgsConstructor
@Slf4j
public class ApplicationStartupService {

    private final MyAppProperties props;

    @PostConstruct
    public void logConfig() {
        log.info("Tətbiq başladı:");
        log.info("  Port: {}", props.getServer().getPort());
        log.info("  DB Pool: {}", props.getDatabase().getPoolSize());
        log.info("  Cache TTL: {}s", props.getCache().getTtlSeconds());
        log.info("  External APIs: {}", props.getExternal().getApis().keySet());
    }
}
```

---

## İntervyu Sualları

**S: @Value vs @ConfigurationProperties fərqi nədir?**
C: @Value — tək property üçün, sadə, SpEL dəstəkləyir. @ConfigurationProperties — qrup property üçün, type-safe, validation dəstəkli, nested siniflər, IDE auto-completion. Bir-neçə related property varsa @ConfigurationProperties tövsiyə olunur.

**S: Relaxed binding nədir?**
C: Spring Boot konfigurasiya property-lərini müxtəlif formatlarda qəbul edir: kebab-case (max-pool-size), camelCase (maxPoolSize), underscore (max_pool_size), UPPER_CASE (MAX_POOL_SIZE). Hamısı eyni sahəyə bind olur.

**S: @ConfigurationProperties-də validation necə işləyir?**
C: `@Validated` əlavə etmək lazımdır + Bean Validation annotasiyaları (@NotBlank, @Min, @Max). Validation startup zamanı işləyir — yanlış konfiqurasiya tətbiqi başlatmır. spring-boot-starter-validation dependency lazımdır.

**S: application.yml faydaları nələrdir?**
C: Hierarxik struktur, oxumaq asandır, array/map daha güclü, multiline string dəstəyi. properties faylı daha sadədir amma böyük konfiqurasiyanı idarə etmək çətindir.

**S: Environment variable-dan konfiqurasiya necə alınır?**
C: `${DB_PASSWORD}` — properties faylında. Ya da relaxed binding ilə: `APP_DATABASE_URL` → `app.database.url`. Docker/Kubernetes environment-da bu yanaşma tövsiyə olunur, credentials-ları faylla paylaşmamaq üçün.

**S: SpEL @Value-da nə üçün istifadə olunur?**
C: Məntiqi ifadələr, başqa bean metodlarını çağırmaq, şərti dəyərlər, sistem property-lərini oxumaq. `#{}` — SpEL ifadəsi, `${}` — property placeholder.
