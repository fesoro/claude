# 003 — application.properties və application.yml (Detallı)
**Səviyyə:** Orta


## Mündəricat
1. [Konfiqurasiya fayllarının məqsədi](#meqsed)
2. [Yerləşmə və axtarış qaydası](#yerlesme)
3. [Properties vs YAML sintaksisi](#properties-vs-yaml)
4. [Ən çox istifadə edilən property-lər](#common-props)
5. [Relaxed Binding (yumşaq uyğunlaşma)](#relaxed-binding)
6. [Profiles — mühitə görə konfiqurasiya](#profiles)
7. [Profile-ları aktivləşdirmək](#activate-profile)
8. [Multi-document YAML ilə profillər](#multi-doc-yaml)
9. [Property Source prioriteti](#priority)
10. [@Value ilə property oxumaq](#value)
11. [@ConfigurationProperties — type-safe binding](#config-props)
12. [Environment variable binding](#env-vars)
13. [Placeholder expansion və default dəyərlər](#placeholders)
14. [Xarici konfiqurasiya yükləmək](#external)
15. [spring.config.import](#config-import)
16. [Jasypt — şifrələnmiş property-lər](#jasypt)
17. [Ümumi Səhvlər](#umumi-sehvler)
18. [İntervyu Sualları](#intervyu)

---

## 1. Konfiqurasiya fayllarının məqsədi {#meqsed}

Spring Boot tətbiqinin **dəyişə bilən** parametrlərini kod kənarında saxlayırıq:
server portu, DB URL-i, API açarları, log səviyyələri...

```java
// YANLIŞ — parametrləri kodun içinə yazmaq
@RestController
public class UserController {
    private final String dbUrl = "jdbc:mysql://prod-db:3306/users";  // hardcode!
    // Hər mühit üçün kodu dəyişməliyik və yenidən kompilyasiya etməliyik
}

// DOĞRU — parametrlər application.properties-dədir
@RestController
public class UserController {
    @Value("${spring.datasource.url}")
    private String dbUrl;
    // Mühit dəyişir, kod yox
}
```

### "12-factor app" prinsipi

Konfiqurasiya və kod ayrılmalıdır — kod bir dəfə yazılır, konfiqurasiya hər mühitdə fərqli olur.

---

## 2. Yerləşmə və axtarış qaydası {#yerlesme}

Spring Boot konfiqurasiya faylını bir neçə yerdən axtarır:

```
1. ./config/application.properties            (cari qovluqdakı config/)
2. ./application.properties                   (cari qovluq)
3. classpath:/config/application.properties   (JAR içindəki config/)
4. classpath:/application.properties          (JAR içində kök)
```

Eyni sıra `application.yml` üçün də tətbiq olunur. **Aşağı nömrə → yüksək prioritet**.

### İlk layihədə standart yer:

```
src/
└── main/
    └── resources/
        └── application.properties   ← buraya yaz
```

### `application.properties` və `application.yml` eyni anda olsa?

Hər ikisi oxunur, **properties fayl YAML-dən yüksək prioritetə** malikdir. Amma tövsiyə: **yalnız birini seç** — qarışıqlıq olmasın.

---

## 3. Properties vs YAML sintaksisi {#properties-vs-yaml}

### `application.properties` formatı

```properties
# Şərh (comment)
server.port=8080
server.servlet.context-path=/api

spring.datasource.url=jdbc:mysql://localhost:3306/mydb
spring.datasource.username=root
spring.datasource.password=secret

spring.jpa.hibernate.ddl-auto=update
spring.jpa.show-sql=true

logging.level.root=INFO
logging.level.com.example=DEBUG

# Siyahı (list)
app.allowed-origins[0]=http://localhost:3000
app.allowed-origins[1]=https://example.com
```

### Eyni konfiqurasiya `application.yml` formatında

```yaml
# Şərh (comment)
server:
  port: 8080
  servlet:
    context-path: /api

spring:
  datasource:
    url: jdbc:mysql://localhost:3306/mydb
    username: root
    password: secret
  jpa:
    hibernate:
      ddl-auto: update
    show-sql: true

logging:
  level:
    root: INFO
    com.example: DEBUG

app:
  allowed-origins:
    - http://localhost:3000
    - https://example.com
```

### Müqayisə cədvəli:

| Meyar | Properties | YAML |
|---|---|---|
| Sintaksis | Düz, sadə | Hierarxik, daha oxunaqlı |
| Təkrar prefiks | Hər sətirdə yazılır | Bir dəfə yazılır |
| Siyahı (list) | `[0]=...` | `- ...` |
| Səhv riski | Azdır | Məsafə (indentation) həssasdır |
| Spring Boot dəstəyi | Tam | Tam |
| Comment | `#` | `#` |

**Tövsiyə:** kiçik layihələr üçün `.properties`, böyük/çoxprofilli üçün `.yml`.

---

## 4. Ən çox istifadə edilən property-lər {#common-props}

### Server

```properties
server.port=8080
server.servlet.context-path=/api/v1
server.servlet.session.timeout=30m
server.error.include-message=always
server.error.include-stacktrace=never
server.compression.enabled=true
server.forward-headers-strategy=framework
```

### Datasource (MySQL/PostgreSQL)

```properties
spring.datasource.url=jdbc:mysql://localhost:3306/mydb
spring.datasource.username=root
spring.datasource.password=password
spring.datasource.driver-class-name=com.mysql.cj.jdbc.Driver

# Connection pool (HikariCP)
spring.datasource.hikari.maximum-pool-size=10
spring.datasource.hikari.minimum-idle=2
spring.datasource.hikari.idle-timeout=30000
spring.datasource.hikari.connection-timeout=20000
```

### JPA / Hibernate

```properties
# Cədvəlləri necə idarə et
# none | validate | update | create | create-drop
spring.jpa.hibernate.ddl-auto=update

# SQL-i log-a yaz
spring.jpa.show-sql=true
spring.jpa.properties.hibernate.format_sql=true

# Dialect
spring.jpa.properties.hibernate.dialect=org.hibernate.dialect.MySQLDialect

# Batch size (performance üçün)
spring.jpa.properties.hibernate.jdbc.batch_size=20
```

### Logging

```properties
# Ümumi səviyyə
logging.level.root=INFO

# Paket səviyyəsində
logging.level.org.springframework.web=DEBUG
logging.level.org.hibernate.SQL=DEBUG
logging.level.com.example=TRACE

# Fayla yaz
logging.file.name=logs/app.log
logging.pattern.file=%d{yyyy-MM-dd HH:mm:ss} - %msg%n
```

### Jackson (JSON)

```properties
spring.jackson.date-format=yyyy-MM-dd'T'HH:mm:ss
spring.jackson.time-zone=UTC
spring.jackson.serialization.indent-output=true
spring.jackson.default-property-inclusion=non_null
```

### Actuator

```properties
management.endpoints.web.exposure.include=health,info,metrics
management.endpoint.health.show-details=always
```

---

## 5. Relaxed Binding (yumşaq uyğunlaşma) {#relaxed-binding}

Spring Boot property adlarını oxuyarkən **yumşaq** davranır. Aşağıdakı variantlar eyni şeyi göstərir:

```properties
# Kebab-case (TÖVSİYƏ OLUNUR)
app.service-url=https://api.example.com

# camelCase
app.serviceUrl=https://api.example.com

# Underscore
app.service_url=https://api.example.com

# UPPER CASE (environment variables üçün)
APP_SERVICE_URL=https://api.example.com
```

Hamısı eyni Java sahəsinə bağlanır:

```java
@ConfigurationProperties(prefix = "app")
public class AppProperties {
    private String serviceUrl;  // kebab-case → camelCase avtomatik
    // getter/setter...
}
```

### Kebab-case niyə tövsiyə olunur?

| Format | Niyə? |
|---|---|
| `service-url` (kebab) | Oxunaqlı, standart, properties faylında gözəl görünür |
| `serviceUrl` (camel) | YAML-də istifadə olunarsa səhv etmək asandır |
| `SERVICE_URL` (upper) | Yalnız environment variable-larda istifadə olunur |

---

## 6. Profiles — mühitə görə konfiqurasiya {#profiles}

Hər mühit (dev, staging, prod) fərqli konfiqurasiyaya ehtiyac duyur:

- **dev** — lokal DB, ətraflı log
- **staging** — test server, real ilə oxşar
- **prod** — istehsalat DB, minimal log

### Fayl strukturu:

```
src/main/resources/
├── application.properties           # default (həmişə yüklənir)
├── application-dev.properties       # yalnız "dev" profili aktiv olanda
├── application-staging.properties   # yalnız "staging"
└── application-prod.properties      # yalnız "prod"
```

### `application.properties` (default)

```properties
# Hər mühitdə ortaq
server.port=8080
spring.application.name=my-app
```

### `application-dev.properties`

```properties
spring.datasource.url=jdbc:h2:mem:devdb
spring.datasource.username=sa
spring.datasource.password=

logging.level.root=DEBUG
spring.jpa.show-sql=true
```

### `application-prod.properties`

```properties
spring.datasource.url=jdbc:postgresql://prod-db:5432/app
spring.datasource.username=${DB_USER}
spring.datasource.password=${DB_PASSWORD}

logging.level.root=WARN
spring.jpa.show-sql=false
```

### Logika:

```
1. application.properties yüklənir  (həmişə)
2. Aktiv profil müəyyən edilir       (dev/staging/prod)
3. application-{profil}.properties yüklənir
4. Eyni açar varsa, profil faylı default-u ÜSTÜN BAĞLAMA EDİR
```

---

## 7. Profile-ları aktivləşdirmək {#activate-profile}

### Üsul 1 — `application.properties`-də

```properties
spring.profiles.active=dev
```

### Üsul 2 — Command line argument

```bash
java -jar app.jar --spring.profiles.active=prod
```

### Üsul 3 — Environment variable

```bash
export SPRING_PROFILES_ACTIVE=prod
java -jar app.jar
```

### Üsul 4 — JVM argumenti

```bash
java -Dspring.profiles.active=staging -jar app.jar
```

### Üsul 5 — IntelliJ Run Configuration

```
Run → Edit Configurations → Environment variables:
SPRING_PROFILES_ACTIVE=dev
```

### Birdən çox profil birləşdirmək

```bash
java -jar app.jar --spring.profiles.active=prod,monitoring,caching
```

Profillər sırayla tətbiq olunur — sonrakı əvvəlkini üstün bağlama edir.

### Default profili təyin etmək

```properties
# Heç bir aktiv profil göstərilməyibsə, bunu istifadə et
spring.profiles.default=dev
```

---

## 8. Multi-document YAML ilə profillər {#multi-doc-yaml}

YAML-də bir faylda bütün profilləri saxlaya bilərik. `---` ilə bölürük:

```yaml
# Default (bütün mühitlər üçün)
spring:
  application:
    name: my-app

server:
  port: 8080

---
# DEV profili
spring:
  config:
    activate:
      on-profile: dev
  datasource:
    url: jdbc:h2:mem:devdb
    username: sa

logging:
  level:
    root: DEBUG

---
# PROD profili
spring:
  config:
    activate:
      on-profile: prod
  datasource:
    url: jdbc:postgresql://prod-db:5432/app
    username: ${DB_USER}
    password: ${DB_PASSWORD}

logging:
  level:
    root: WARN
```

### Üstünlüklər:

- Bir faylda hər şey — paylaşmaq asan.
- Default və profil dəyərləri yan-yana görünür.
- IDE tam faylı göstərir, ayrı fayllara baxmaq lazım deyil.

### Qeyd

**Köhnə sintaksis** (Spring Boot 2.3-dən əvvəl):
```yaml
spring:
  profiles: dev    # YANLIŞ (köhnə)
```

**Yeni sintaksis** (Spring Boot 2.4+):
```yaml
spring:
  config:
    activate:
      on-profile: dev  # DOĞRU
```

---

## 9. Property Source prioriteti {#priority}

Əgər eyni property bir neçə yerdə varsa, hansı qalib gəlir?

**Yüksəkdən aşağıya prioritet sırası:**

```
1. Command line argument       (--server.port=9090)
2. Java System property        (-Dserver.port=9090)
3. OS environment variable     (SERVER_PORT=9090)
4. application-{profile}.yml   (profil-specific)
5. application.yml             (default profilsiz)
6. application-{profile}.properties
7. application.properties
8. @PropertySource ilə yüklənmiş
9. Default dəyər (@Value-da `:default`)
```

### Nümunə:

`application.properties`:
```properties
server.port=8080
```

Environment variable:
```bash
export SERVER_PORT=8081
```

Command line:
```bash
java -jar app.jar --server.port=8082
```

Nəticə: **8082** (command line ən yüksək prioritet).

### Niyə belə sıra?

- **Command line** — istifadəçi birbaşa verir, ən yüksək niyyət.
- **Environment variable** — mühitdən asılıdır (production/staging).
- **Faylın özü** — default konfiqurasiya.

### Tam property source-ları görmək

```java
@Autowired
private ConfigurableEnvironment env;

public void showSources() {
    env.getPropertySources().forEach(source ->
        System.out.println(source.getName())
    );
}
```

---

## 10. @Value ilə property oxumaq {#value}

```properties
app.name=My Awesome App
app.max-users=100
app.features.enabled=true
app.api-url=https://api.example.com
```

```java
@Component
public class AppConfig {

    @Value("${app.name}")
    private String appName;

    @Value("${app.max-users}")
    private int maxUsers;

    @Value("${app.features.enabled}")
    private boolean featuresEnabled;

    // Default dəyər ilə (property yoxdursa)
    @Value("${app.timeout:30}")
    private int timeout;  // default 30

    // String list (vergüllə ayrılır)
    @Value("${app.allowed-origins:http://localhost}")
    private String[] allowedOrigins;

    // Konstruktor içində (tövsiyə olunur — immutable)
    private final String version;

    public AppConfig(@Value("${app.version:1.0.0}") String version) {
        this.version = version;
    }
}
```

### @Value məhdudiyyətləri

```java
// Bu işləməz — field injection property dəyəri default-dan SONRA set olunur:
@Component
public class BadExample {
    @Value("${app.name}")
    private String name;

    public BadExample() {
        System.out.println(name);  // null — konstruktor `name`-dən əvvəl işləyir!
    }
}

// Konstruktorda @Value istifadə et:
@Component
public class GoodExample {
    private final String name;

    public GoodExample(@Value("${app.name}") String name) {
        this.name = name;  // düzgün — inject zamanı
        System.out.println(name);
    }
}
```

---

## 11. @ConfigurationProperties — type-safe binding {#config-props}

Çox property varsa, `@Value`-nu hər biri üçün yazmaq yorucudur. `@ConfigurationProperties` qrupu obyektə bağlayır.

### Addım 1 — Properties sinifi

```java
package com.example.demo.config;

import org.springframework.boot.context.properties.ConfigurationProperties;
import java.util.List;

@ConfigurationProperties(prefix = "app")
public class AppProperties {

    private String name;
    private int maxUsers;
    private String apiUrl;
    private List<String> allowedOrigins;
    private Security security = new Security();   // nested obyekt

    // Getter/setter-lər (Lombok istifadə edilsə `@Data` kifayətdir)
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }

    public int getMaxUsers() { return maxUsers; }
    public void setMaxUsers(int maxUsers) { this.maxUsers = maxUsers; }

    public String getApiUrl() { return apiUrl; }
    public void setApiUrl(String apiUrl) { this.apiUrl = apiUrl; }

    public List<String> getAllowedOrigins() { return allowedOrigins; }
    public void setAllowedOrigins(List<String> origins) { this.allowedOrigins = origins; }

    public Security getSecurity() { return security; }
    public void setSecurity(Security security) { this.security = security; }

    // Nested class
    public static class Security {
        private String secretKey;
        private int tokenExpiryMinutes = 60;

        public String getSecretKey() { return secretKey; }
        public void setSecretKey(String secretKey) { this.secretKey = secretKey; }

        public int getTokenExpiryMinutes() { return tokenExpiryMinutes; }
        public void setTokenExpiryMinutes(int m) { this.tokenExpiryMinutes = m; }
    }
}
```

### Addım 2 — Aktivləşdir

Əsas sinifdə:

```java
@SpringBootApplication
@EnableConfigurationProperties(AppProperties.class)
public class DemoApplication {
    public static void main(String[] args) {
        SpringApplication.run(DemoApplication.class, args);
    }
}
```

Və ya properties sinfini `@Component` ilə işarələ:

```java
@Component
@ConfigurationProperties(prefix = "app")
public class AppProperties { ... }
```

### Addım 3 — `application.yml`-də dəyərləri təyin et

```yaml
app:
  name: My Awesome App
  max-users: 100
  api-url: https://api.example.com
  allowed-origins:
    - http://localhost:3000
    - https://example.com
  security:
    secret-key: ${JWT_SECRET:default-secret}
    token-expiry-minutes: 120
```

### Addım 4 — İstifadə

```java
@Service
public class UserService {

    private final AppProperties props;

    public UserService(AppProperties props) {
        this.props = props;
    }

    public void printConfig() {
        System.out.println("App: " + props.getName());
        System.out.println("Max users: " + props.getMaxUsers());
        System.out.println("Token expiry: " + props.getSecurity().getTokenExpiryMinutes());
    }
}
```

### Validation əlavə etmək

```java
import jakarta.validation.constraints.*;
import org.springframework.validation.annotation.Validated;

@ConfigurationProperties(prefix = "app")
@Validated
public class AppProperties {

    @NotBlank
    private String name;

    @Min(1) @Max(10000)
    private int maxUsers;

    @Pattern(regexp = "^https?://.+")
    private String apiUrl;

    // getter/setter...
}
```

Dəyər düzgün olmasa tətbiq başlamayacaq — erkən uğursuzluq.

### @Value vs @ConfigurationProperties müqayisəsi

| Meyar | @Value | @ConfigurationProperties |
|---|---|---|
| Bir property | Uyğundur | Çox olur |
| Çox property | Yorucudur | İdealdır |
| Nested obyekt | Mümkün deyil | Var |
| Validation | Mümkün deyil | `@Validated` ilə var |
| Relaxed binding | Məhdud | Tam |
| List/Map | Çətindir | Asandır |
| IDE autocomplete | Zəif | Güclü |

---

## 12. Environment variable binding {#env-vars}

Production-da parol və gizli açarları **environment variable**-larla vermək standartdır.

### Avtomatik konversiya qaydaları:

| Property adı | Environment variable |
|---|---|
| `spring.datasource.url` | `SPRING_DATASOURCE_URL` |
| `server.port` | `SERVER_PORT` |
| `app.service-url` | `APP_SERVICE_URL` |
| `app.security.secret-key` | `APP_SECURITY_SECRET_KEY` |

**Qaydalar:**
- Nöqtələr → alt xətt (`_`)
- Defislər silinir
- Böyük hərflərə çevrilir

### Nümunə

`application.yml`:
```yaml
spring:
  datasource:
    url: jdbc:mysql://localhost:3306/mydb
    username: root
    password: changeme
```

Production-da (Linux):
```bash
export SPRING_DATASOURCE_URL=jdbc:mysql://prod-db:3306/app
export SPRING_DATASOURCE_USERNAME=app_user
export SPRING_DATASOURCE_PASSWORD=${SECURE_DB_PASSWORD}

java -jar app.jar
```

Docker `docker-compose.yml`:
```yaml
services:
  app:
    image: my-app:1.0
    environment:
      SPRING_DATASOURCE_URL: jdbc:postgresql://db:5432/app
      SPRING_DATASOURCE_USERNAME: app_user
      SPRING_DATASOURCE_PASSWORD_FILE: /run/secrets/db_password
    secrets:
      - db_password
```

Kubernetes `deployment.yaml`:
```yaml
env:
  - name: SPRING_DATASOURCE_URL
    value: jdbc:postgresql://db-service:5432/app
  - name: SPRING_DATASOURCE_PASSWORD
    valueFrom:
      secretKeyRef:
        name: db-credentials
        key: password
```

---

## 13. Placeholder expansion və default dəyərlər {#placeholders}

### Sadə placeholder

```properties
app.name=MyApp
app.greeting=Salam, ${app.name}!
```

Nəticə: `app.greeting=Salam, MyApp!`

### Default dəyər

```properties
server.port=${PORT:8080}          # PORT env yoxdursa, 8080
app.debug=${DEBUG_MODE:false}
app.timeout=${TIMEOUT:30}
```

### Nested placeholder

```properties
app.db.host=localhost
app.db.port=3306
app.db.name=mydb
spring.datasource.url=jdbc:mysql://${app.db.host}:${app.db.port}/${app.db.name}
```

### Environment + default kombinasiyası

```properties
# DB_HOST env var yoxdursa, "localhost" istifadə et
spring.datasource.url=jdbc:postgresql://${DB_HOST:localhost}:5432/app

# Bütün istisna açarlar
app.api-key=${API_KEY:${DEFAULT_API_KEY:no-key}}
```

### Random dəyər

```properties
# Təsadüfi UUID
app.instance-id=${random.uuid}

# Təsadüfi int
app.random-port=${random.int[1024,65535]}

# Təsadüfi string
app.secret=${random.value}
```

---

## 14. Xarici konfiqurasiya yükləmək {#external}

Bəzən JAR-ın içindəki faylı deyil, xaricdən gələn faylı yükləmək lazımdır.

### `spring.config.location`

```bash
# JAR-ı xarici fayl ilə işə sal
java -jar app.jar --spring.config.location=file:/etc/myapp/custom.yml

# Birdən çox fayl
java -jar app.jar \
  --spring.config.location=classpath:/application.yml,file:/etc/myapp/override.yml
```

### `spring.config.additional-location`

Default lokasiyaları saxlayıb əlavə yer göstərir:

```bash
java -jar app.jar \
  --spring.config.additional-location=file:/etc/myapp/secrets.properties
```

### Tipik production struktur

```
/opt/myapp/
├── myapp.jar
└── config/
    ├── application.yml           # ümumi
    └── application-prod.yml      # profil-specific
```

İşə salmaq:
```bash
cd /opt/myapp
java -jar myapp.jar --spring.profiles.active=prod
```

Spring Boot avtomatik `./config/` qovluğunu axtarır (prioritetdə yüksəkdir).

---

## 15. spring.config.import {#config-import}

Spring Boot 2.4+ yeni mexanizm — bir fayldan digərini import etmək.

### Başqa property faylını import et

```yaml
# application.yml
spring:
  config:
    import:
      - optional:classpath:db-config.yml
      - optional:file:/etc/myapp/secrets.yml

app:
  name: MyApp
```

`optional:` prefiksi — fayl yoxdursa xəta atma.

### Environment variable-ları fayldan

```yaml
spring:
  config:
    import: optional:file:.env[.properties]
```

`.env` faylı:
```
DB_PASSWORD=secret
API_KEY=xyz123
```

### ConfigTree — Kubernetes secrets

Kubernetes secret-lər fayl şəklində qoşulur:
```
/run/secrets/
├── db-password   # məzmun: "secret123"
└── api-key       # məzmun: "xyz"
```

```yaml
spring:
  config:
    import: configtree:/run/secrets/
```

Sonra:
```java
@Value("${db-password}")
private String dbPassword;
```

### Vault-dan import (Spring Cloud)

```yaml
spring:
  config:
    import: vault://secret/myapp
```

---

## 16. Jasypt — şifrələnmiş property-lər {#jasypt}

Bəzən parolu şifrələnmiş şəkildə saxlamaq istəyirik.

### Bağlılıq

```xml
<dependency>
    <groupId>com.github.ulisesbocchio</groupId>
    <artifactId>jasypt-spring-boot-starter</artifactId>
    <version>3.0.5</version>
</dependency>
```

### İstifadə

```properties
# Master password environment variable-dan gəlir
# ENC(...) formatı şifrələnmiş dəyər göstərir

spring.datasource.password=ENC(iE2Bwz4Fz8Xm9a==)
app.api-key=ENC(Lk7fG3hP8q==)
```

İşə salmaq:
```bash
java -Djasypt.encryptor.password=masterPass -jar app.jar
```

Və ya environment variable ilə:
```bash
export JASYPT_ENCRYPTOR_PASSWORD=masterPass
java -jar app.jar
```

### Xəbərdarlıq

Jasypt geniş istifadə olunsa da, müasir tövsiyə: **Vault** və ya **cloud secret manager** (AWS Secrets Manager, GCP Secret Manager) istifadə edin.

---

## 17. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: Eyni property-ni həm `properties` həm də `yml`-də yazmaq

İki fayl olsa, `.properties` üstün gəlir — `.yml`-dəki dəyər ignored olur. Seçim zamanı qarışıqlığa yol verir.

### Səhv 2: Profile faylının adını səhv yazmaq

```
application-prod.properties   ← DOĞRU
application_prod.properties   ← YANLIŞ (underscore)
applicationProd.properties    ← YANLIŞ (camelCase)
```

### Səhv 3: YAML-də tab istifadə etmək

```yaml
server:
	port: 8080   # YANLIŞ — TAB çarpışma
```

```yaml
server:
  port: 8080    # DOĞRU — 2 boşluq
```

### Səhv 4: Parolu GitHub-a commit etmək

```properties
# application.properties
spring.datasource.password=supersecret123   # HƏR KƏS GÖRÜR!
```

**Həll:**
- `.gitignore`-a əlavə et: `application-local.properties`
- Production-da environment variable istifadə et
- Git history-dən təmizlə (BFG Repo-Cleaner)

### Səhv 5: `@Value` konstruktordan əvvəl istifadə etmək

```java
@Component
public class Bad {
    @Value("${app.name}")
    private String name;

    public Bad() {
        System.out.println(name);  // null — inject hələ olmayıb
    }
}
```

Həll: `@PostConstruct` və ya konstruktor parametri kimi.

### Səhv 6: `spring.profiles.active` property-sini kod içindən dəyişməyə çalışmaq

```java
// YANLIŞ — artıq gec, tətbiq başladı:
System.setProperty("spring.profiles.active", "prod");
```

Profil **tətbiq başlamazdan əvvəl** təyin edilməlidir.

### Səhv 7: Gizli property-ləri loglamaq

```java
log.info("Config: {}", appProperties);  // password log-a düşə bilər!
```

Həll:
```java
@Override
public String toString() {
    return "AppProperties{name=" + name + ", password=***}";
}
```

---

## 18. İntervyu Sualları {#intervyu}

**S: `application.properties` və `application.yml` arasında fərq nədir?**
C: İkisi də eyni konfiqurasiyanı saxlaya bilər. `.properties` — düz açar-dəyər sintaksisi (`server.port=8080`); `.yml` — hierarxik, iç-içə strukturlu (indentation həssasdır). YAML daha oxunaqlıdır, xüsusən profilləri bir faydda birləşdirmək üçün; properties daha sadədir, səhv riskli deyil. Eyni faylda hər ikisi olsa, properties üstün gəlir.

**S: Spring Boot property source-larının prioritet sırası necədir?**
C: Yuxarıdan aşağı: (1) command line argumentləri (`--key=value`), (2) Java System property (`-Dkey=value`), (3) OS environment variable (`KEY=value`), (4) `application-{profile}` faylı, (5) `application.properties/yml`, (6) `@PropertySource`, (7) default dəyər. Yuxarıdakı aşağıdakını üstün bağlama edir.

**S: Relaxed Binding nədir?**
C: Spring Boot property adlarının müxtəlif yazılış formalarını qəbul etməsidir. `app.service-url`, `app.serviceUrl`, `APP_SERVICE_URL` hamısı eyni sahəyə bağlanır. Bu, environment variable-lardan property-lərə keçidi asanlaşdırır. Tövsiyə edilən format isə kebab-case-dir.

**S: `@Value` və `@ConfigurationProperties` arasında fərq nədir?**
C: `@Value("${key}")` tək property oxumaq üçün yaxşıdır, lakin nested obyektlər və validation dəstəkləmir. `@ConfigurationProperties(prefix = "app")` bütün "app.*" qrupu POJO-ya bağlayır — type-safe, IDE autocomplete, `@Validated` ilə yoxlama dəstəyi var. Çox parametr olduqda həmişə `@ConfigurationProperties` seç.

**S: Spring profili necə aktivləşdirilir?**
C: 5 yolla: (1) `application.properties`-də `spring.profiles.active=dev`, (2) command line `--spring.profiles.active=prod`, (3) environment variable `SPRING_PROFILES_ACTIVE=prod`, (4) JVM arg `-Dspring.profiles.active=staging`, (5) proqramlı olaraq `SpringApplication.setAdditionalProfiles(...)`. Bir neçə profil birləşdirmək mümkündür (vergüllə).

**S: Multi-document YAML nədir?**
C: Bir YAML faylında `---` ayırıcı ilə bir neçə sənəd yazmaqdır. Hər bir blokun `spring.config.activate.on-profile` ilə hansı profilə aid olduğunu göstərmək olar. Beləliklə, bütün profil konfiqurasiyası bir faydda cəmləşir — idarəetmə asandır.

**S: Environment variable Spring property-yə necə bağlanır?**
C: `SERVER_PORT` → `server.port`: alt xətt nöqtəyə, böyük hərflər kiçiyə çevrilir. Bu "relaxed binding"-in hissəsidir. Bu sayədə kodu dəyişmədən Docker/Kubernetes mühitində environment variable ilə konfiqurasiya etmək mümkündür.

**S: `spring.config.import` nə üçündür?**
C: Spring Boot 2.4+ gəldi. Bir konfiqurasiya faylından digərini, environment fayllarını, Kubernetes secret-ləri, Vault və Consul-u import etmək üçündür. `optional:` prefiksi ilə fayl olmasa xəta atılmır. Məsələn: `spring.config.import: optional:configtree:/run/secrets/`.

**S: `spring.jpa.hibernate.ddl-auto` dəyərləri nədir?**
C: `none` — heç bir dəyişiklik; `validate` — sxemi yoxla, dəyişmə; `update` — mövcud cədvəllərə əlavə sütun əlavə et (köhnələri silmir); `create` — hər başlanğıcda cədvəlləri yenidən yarat; `create-drop` — yarat + tətbiq bağlanınca sil. Production-da `validate` və ya `none` tövsiyə olunur, Flyway/Liquibase ilə migration.

**S: Parolu `application.properties`-də saxlamaq olar?**
C: İnkişaf üçün olar, lakin GitHub-a commit EDİLMƏMƏLİDİR. Production-da tövsiyə olunur: (1) environment variable; (2) Kubernetes secret / Docker secret; (3) HashiCorp Vault / cloud secret manager; (4) Jasypt ilə şifrələnmə (son çarə). `@Value`-u `ENC(...)` dəyərləri açmaq üçün istifadə edin.
