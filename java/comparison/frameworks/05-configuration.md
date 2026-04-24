# Konfiqurasiya

> **Seviyye:** Beginner ⭐

## Giris

Her application ferqli muhitlerde (development, staging, production) ferqli konfiqurasiyalarla isleyir. Database melumatlari, API acarlari, cache parametrleri ve s. muhite gore deyisir. Spring `application.properties` / `application.yml` ve profiller sistemi istifade edir. Laravel ise `.env` fayllar ve `config/` qovlugu ile isleyir. Her iki framework sirlari (secrets) koddan ayirmag meqsedi gududur, amma yanasmalar ferqlidir.

## Spring-de istifadesi

### application.properties

```properties
# src/main/resources/application.properties

# Server konfiqurasiyasi
server.port=8080
server.servlet.context-path=/api

# Database
spring.datasource.url=jdbc:postgresql://localhost:5432/myapp
spring.datasource.username=postgres
spring.datasource.password=secret123
spring.datasource.driver-class-name=org.postgresql.Driver

# JPA / Hibernate
spring.jpa.hibernate.ddl-auto=validate
spring.jpa.show-sql=false
spring.jpa.properties.hibernate.format_sql=true
spring.jpa.properties.hibernate.dialect=org.hibernate.dialect.PostgreSQLDialect

# Logging
logging.level.root=INFO
logging.level.com.myapp=DEBUG
logging.level.org.springframework.security=DEBUG

# Custom properties
app.name=MyApplication
app.upload.max-file-size=10MB
app.upload.allowed-types=jpg,png,pdf
app.jwt.secret=my-secret-key-123
app.jwt.expiration=86400000
```

### application.yml (YAML formati)

Eyni konfiqurasiya YAML formatinda:

```yaml
# src/main/resources/application.yml

server:
  port: 8080
  servlet:
    context-path: /api

spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/myapp
    username: postgres
    password: secret123
    driver-class-name: org.postgresql.Driver

  jpa:
    hibernate:
      ddl-auto: validate
    show-sql: false
    properties:
      hibernate:
        format_sql: true
        dialect: org.hibernate.dialect.PostgreSQLDialect

  mail:
    host: smtp.gmail.com
    port: 587
    username: noreply@myapp.com
    password: mail-password
    properties:
      mail.smtp.auth: true
      mail.smtp.starttls.enable: true

logging:
  level:
    root: INFO
    com.myapp: DEBUG

app:
  name: MyApplication
  upload:
    max-file-size: 10MB
    allowed-types: jpg,png,pdf
  jwt:
    secret: my-secret-key-123
    expiration: 86400000
```

### @Value ile deyer almaq

```java
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

@Service
public class FileUploadService {

    @Value("${app.upload.max-file-size}")
    private String maxFileSize;

    @Value("${app.upload.allowed-types}")
    private String allowedTypes;

    @Value("${app.name:DefaultApp}")  // Default deyer
    private String appName;

    @Value("${server.port}")
    private int serverPort;

    public boolean isAllowedType(String type) {
        return List.of(allowedTypes.split(",")).contains(type);
    }
}
```

### @ConfigurationProperties ile tipli konfiqurasiya

Daha tehlukesiz ve strukturlasdirilmis yanasmadır:

```java
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.stereotype.Component;
import java.util.List;

@Component
@ConfigurationProperties(prefix = "app")
public class AppProperties {

    private String name;
    private Upload upload = new Upload();
    private Jwt jwt = new Jwt();

    // Inner class-lar
    public static class Upload {
        private String maxFileSize;
        private List<String> allowedTypes;

        // Getters ve Setters
        public String getMaxFileSize() { return maxFileSize; }
        public void setMaxFileSize(String maxFileSize) { this.maxFileSize = maxFileSize; }
        public List<String> getAllowedTypes() { return allowedTypes; }
        public void setAllowedTypes(List<String> allowedTypes) { this.allowedTypes = allowedTypes; }
    }

    public static class Jwt {
        private String secret;
        private long expiration;

        public String getSecret() { return secret; }
        public void setSecret(String secret) { this.secret = secret; }
        public long getExpiration() { return expiration; }
        public void setExpiration(long expiration) { this.expiration = expiration; }
    }

    // Getters ve Setters
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public Upload getUpload() { return upload; }
    public void setUpload(Upload upload) { this.upload = upload; }
    public Jwt getJwt() { return jwt; }
    public void setJwt(Jwt jwt) { this.jwt = jwt; }
}
```

```java
// Istifade
@Service
public class JwtService {

    private final AppProperties appProperties;

    public JwtService(AppProperties appProperties) {
        this.appProperties = appProperties;
    }

    public String generateToken(String username) {
        String secret = appProperties.getJwt().getSecret();
        long expiration = appProperties.getJwt().getExpiration();
        // ...
    }
}
```

### Profiles (muhit ayrilmasi)

Spring profiller vasitesile muhite gore ferqli konfiqurasiyalar isledir:

```yaml
# src/main/resources/application.yml (umumi)
spring:
  profiles:
    active: dev  # default profil

app:
  name: MyApplication
```

```yaml
# src/main/resources/application-dev.yml
server:
  port: 8080

spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/myapp_dev
    username: postgres
    password: postgres
  jpa:
    hibernate:
      ddl-auto: update
    show-sql: true

logging:
  level:
    com.myapp: DEBUG
```

```yaml
# src/main/resources/application-prod.yml
server:
  port: 8443

spring:
  datasource:
    url: jdbc:postgresql://prod-db:5432/myapp_prod
    username: ${DB_USERNAME}
    password: ${DB_PASSWORD}
  jpa:
    hibernate:
      ddl-auto: validate
    show-sql: false

logging:
  level:
    com.myapp: WARN
```

```yaml
# src/main/resources/application-test.yml
spring:
  datasource:
    url: jdbc:h2:mem:testdb
    driver-class-name: org.h2.Driver
  jpa:
    hibernate:
      ddl-auto: create-drop
```

Profili secmek ucun:

```bash
# Environment variable ile
export SPRING_PROFILES_ACTIVE=prod

# JVM argumenti ile
java -jar app.jar --spring.profiles.active=prod

# application.yml-de
spring.profiles.active=dev
```

### Profil-e gore bean yaratmaq

```java
@Configuration
public class StorageConfig {

    @Bean
    @Profile("dev")
    public StorageService localStorageService() {
        return new LocalStorageService("/tmp/uploads");
    }

    @Bean
    @Profile("prod")
    public StorageService s3StorageService() {
        return new S3StorageService();
    }
}
```

### Environment variable-ler

```yaml
# application.yml - env variable istifade etmek
spring:
  datasource:
    url: ${DATABASE_URL:jdbc:postgresql://localhost:5432/myapp}
    username: ${DB_USERNAME:postgres}
    password: ${DB_PASSWORD:secret}

app:
  jwt:
    secret: ${JWT_SECRET}
```

`${JWT_SECRET}` yazdigda Spring evvelce environment variable-lere, sonra system properties-e baxir.

## Laravel-de istifadesi

### .env fayili

Laravel-de muhit konfiqurasiyalari `.env` faylinda saxlanilir:

```env
# .env (root qovlugda)

APP_NAME=MyApplication
APP_ENV=local
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=myapp
DB_USERNAME=postgres
DB_PASSWORD=secret123

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@myapp.com
MAIL_PASSWORD=mail-password
MAIL_ENCRYPTION=tls

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Custom
JWT_SECRET=my-secret-key-123
JWT_EXPIRATION=86400
UPLOAD_MAX_SIZE=10240
```

`.env` fayili git-e elave olunmur. Bunun evezine `.env.example` fayili numune kimi saxlanilir.

### config/ faylları

Laravel-de `config/` qovlugunda PHP fayllar var. Bu fayllar `.env` deyerlerini istifade edir:

```php
<?php
// config/app.php

return [
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Baku',
    'locale' => 'az',
    'fallback_locale' => 'en',
];
```

```php
<?php
// config/database.php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => database_path('database.sqlite'),
            'prefix' => '',
        ],
    ],
];
```

### Oz config fayili yaratmaq

```php
<?php
// config/upload.php

return [
    'max_file_size' => env('UPLOAD_MAX_SIZE', 10240), // KB
    'allowed_types' => ['jpg', 'png', 'pdf', 'docx'],
    'storage_disk' => env('UPLOAD_DISK', 'local'),
    'path' => 'uploads',
];
```

```php
<?php
// config/jwt.php

return [
    'secret' => env('JWT_SECRET'),
    'expiration' => env('JWT_EXPIRATION', 86400),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),
];
```

### config() helper ile deyer almaq

```php
// Deyer oxumaq
$appName = config('app.name');                    // 'MyApplication'
$dbHost = config('database.connections.pgsql.host'); // '127.0.0.1'
$maxSize = config('upload.max_file_size');          // 10240

// Default deyer ile
$value = config('app.custom_key', 'default');

// Runtime-da deyer deyismek
config(['app.debug' => false]);

// env() birbaşa istifade (yalniz config fayllarinda tovsiye olunur)
$debug = env('APP_DEBUG');
```

### Environment detection

```php
// Cari muhiti yoxlamaq
if (app()->environment('production')) {
    // Production konfiqurasiyasi
}

if (app()->environment('local', 'staging')) {
    // Development ve ya staging
}

// ve ya App facade ile
if (App::environment('production')) {
    // ...
}

// .env-de APP_ENV deyerine gore
// APP_ENV=local    -> development
// APP_ENV=staging  -> staging
// APP_ENV=production -> production
```

### Config caching

Production-da performans ucun config cache olunur:

```bash
# Butun config-leri bir fayla birlesdirmek
php artisan config:cache

# Cache-i temizlemek
php artisan config:clear

# VACIB: config:cache istifade etdikde, env() yalniz config fayllarinda isleyir!
# Controller ve ya model-de env() cagirmaq cache-den sonra islemeyecek.
```

### Ferqli muhitler ucun .env fayllar

```
.env                 # Cari muhit (git-e elave olunmur)
.env.example         # Numune fayl (git-e elave olunur)
.env.testing         # PHPUnit testler ucun
.env.staging         # Staging muhiti ucun (manual istifade)
```

```bash
# Mueyen muhiti isletmek
php artisan serve --env=testing

# Test zamani .env.testing avtomatik istifade olunur
php artisan test
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Konfiqurasiya fayili** | `application.properties` / `application.yml` | `.env` + `config/*.php` |
| **Deyer almaq** | `@Value`, `@ConfigurationProperties` | `config()`, `env()` |
| **Muhit ayirmaq** | Profiles (`application-dev.yml`) | `.env` fayilari + `APP_ENV` |
| **Format** | Properties ve ya YAML | Env (key=value) + PHP arrays |
| **Tipli konfiqurasiya** | `@ConfigurationProperties` ile guclu tipleme | Yoxdur (PHP array qaytarir) |
| **Caching** | Yoxdur (lazim deyil, compile olunur) | `config:cache` emri ile |
| **Profil-e gore bean** | `@Profile` annotasiyasi | Yoxdur (manual yoxlama lazim) |
| **Secrets** | Environment variables, Vault | `.env` fayili, environment variables |
| **Nested konfiqurasiya** | YAML ile rahat nested struktur | PHP array ile nested struktur |
| **Auto-completion** | `@ConfigurationProperties` ile IDE desteki | Yoxdur |

## Niye bele ferqler var?

### Compile-time vs Runtime

Spring application compile olunur ve JAR faylina paketlenir. Ona gore konfiqurasiya fayillari application baslayanda oxunur ve `@ConfigurationProperties` ile tip-tehlukesiz Java obyektlerine cevrilir. Bu IDE-de auto-completion ve refactoring imkani verir.

Laravel ise interpret olunan bir dildir. `.env` fayili her request-de (ve ya cache olunanda bir defe) oxunur. `config()` funksiyasi ile string key vasitesile deyer alinir. Bu daha sadedir, amma tip tehlukesizliyi yoxdur.

### Profil sistemi

Spring-in profil sistemi cox gucludur. `@Profile("prod")` ile museyyen bean-ler yalniz production-da yaranir. Bu, ferkli muhitlerde tamamen ferqli implementasiyalar istifade etmeye imkan verir (meselen, dev-de local storage, prod-da S3).

Laravel-de bele bir konsept yoxdur. Evezine, `config()` deyerlerini `.env`-den alirsiniz ve eger ferqli davranis lazim olsa, `app()->environment()` ile manual yoxlama edirsiniz.

### Ikiqatli sistem (Laravel)

Laravel-in `.env` + `config/` ikiqatli sistemi maraqlidir. `.env`-de muhite xas deyerler olur (database password), `config/` fayllarinda ise strukturlasdirilmis konfiqurasiya ve default deyerler olur. Bu ayrilma sayesinde `.env` faylini deyismekle eyni kod ferqli muhitlerde isleyir, `config/` fayllarini deyismeye ehtiyac olmur.

Spring-de bele ayrilma yoxdur - her sey bir yerde (`application.yml`) ve profil suffix-i ile ayrilir.

## Hansi framework-de var, hansinda yoxdur?

### Yalniz Spring-de (ve ya daha asandir):
- **`@ConfigurationProperties`** - Tipli, IDE-desteyi olan konfiqurasiya sinfleri
- **YAML formati** - Hiyerarsik konfiqurasiya ucun rahat format
- **`@Profile` annotasiyasi** - Muhite gore bean yaratma/yaratmama
- **`@ConditionalOnProperty`** - Mueyen property olduqda bean yaratmaq
- **Property validation** - `@Validated` ile konfiqurasiya deyerlerini yoxlamaq
- **Externalized configuration priority** - Command-line args > env vars > application.yml sirasi ile uzerleme

### Yalniz Laravel-de (ve ya daha asandir):
- **`.env` fayili** - Sade key=value formati, framework-den asili deyil
- **`config:cache`** - Butun konfiqurasiyani bir fayla birlesdirib sureti artirmaq
- **`config()` helper** - Heryerden (controller, view, middleware) rahat istifade
- **`.env.example`** - Komanda uzvleri ucun numune fayl
- **`.env.testing`** - Test muhiti ucun avtomatik yuklenme
- **`php artisan config:clear`** - Cache-i bir emrle temizlemek
