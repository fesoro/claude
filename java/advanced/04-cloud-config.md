# 04 — Spring Cloud Config

> **Seviyye:** Expert ⭐⭐⭐⭐


## Mündəricat
- [Config Server Nədir?](#config-server-nədir)
- [Config Server Qurulması](#config-server-qurulması)
- [Git Backend](#git-backend)
- [Filesystem Backend](#filesystem-backend)
- [Client Konfiqurasiyası](#client-konfiqurasiyası)
- [@RefreshScope](#refreshscope)
- [Spring Cloud Bus](#spring-cloud-bus)
- [Encryption / Decryption](#encryption--decryption)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Config Server Nədir?

Spring Cloud Config Server — microservice arxitekturasında bütün servislərin konfiqurasiyasını mərkəzi yerdə (Git repozitorisi və ya filesystem) saxlayan serverdir.

```
YANLIŞ — hər servisin öz application.yml faylı
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  Order Service  │  │ Payment Service │  │  User Service   │
│  application.yml│  │ application.yml │  │ application.yml │
│  db.url=...     │  │  db.url=...     │  │  db.url=...     │
│  api.key=...    │  │  api.key=...    │  │  api.key=...    │
└─────────────────┘  └─────────────────┘  └─────────────────┘
  Problemlər: eyni dəyər 3 yerdə, dəyişdirmək üçün 3 deploy lazım!

DOĞRU — mərkəzi Config Server
              ┌─────────────────────────────┐
              │     Config Server (:8888)   │
              │  Git Repo / Filesystem      │
              │  ├── application.yml        │
              │  ├── order-service.yml      │
              │  ├── payment-service.yml    │
              │  └── user-service.yml       │
              └─────────────────────────────┘
                    ▲         ▲         ▲
                    │         │         │
              Order Svc  Payment Svc  User Svc
              (startup-da konfiq alır)
```

---

## Config Server Qurulması

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-config-server</artifactId>
</dependency>
```

```java
@SpringBootApplication
@EnableConfigServer    // Config Server aktivləşdirmə
public class ConfigServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(ConfigServerApplication.class, args);
    }
}
```

---

## Git Backend

```yaml
# Config Server — application.yml
server:
  port: 8888

spring:
  application:
    name: config-server

  cloud:
    config:
      server:
        git:
          # Git repo URL-i
          uri: https://github.com/myorg/config-repo
          # Ya da SSH:
          # uri: git@github.com:myorg/config-repo.git

          # Default branch
          default-label: main

          # Repo daxilindəki axtarış yolları
          search-paths:
            - '{application}'         # servis adına görə qovluq
            - 'shared'                # paylaşılan konfiq

          # Private repo üçün autentifikasiya
          username: ${GIT_USERNAME}
          password: ${GIT_PASSWORD}

          # Hər sorğuda Git-dən yenilə (dev üçün)
          force-pull: true

          # Clone zamanı timeout (ms)
          timeout: 10

          # Local klon saxlanılan yer
          basedir: /tmp/config-repo-clone

          # Çoxlu repo konfigurasiyası
          repos:
            order-service:
              uri: https://github.com/myorg/order-config
              pattern: order-service*
            payment-service:
              uri: https://github.com/myorg/payment-config
              pattern: payment-service*
```

### Git Repo Strukturu

```
config-repo/
├── application.yml              # Bütün servislərin ümumi konfigi
├── application-production.yml   # Prod mühiti üçün ümumi konfig
├── application-development.yml  # Dev mühiti üçün ümumi konfig
│
├── order-service.yml            # Order servisi konfigi
├── order-service-production.yml # Order servisi prod konfigi
├── order-service-development.yml
│
├── payment-service.yml
├── payment-service-production.yml
│
└── user-service.yml
```

```yaml
# config-repo/application.yml — Bütün servislərin konfigi
management:
  endpoints:
    web:
      exposure:
        include: health, info, refresh, bus-refresh

logging:
  level:
    root: INFO

---
# config-repo/order-service.yml — Yalnız Order servisi üçün
spring:
  datasource:
    url: jdbc:mysql://order-db:3306/orders
    username: order_user

order:
  max-items-per-page: 20
  default-currency: AZN
```

### Config Server API

```bash
# Konfiqurasiya oxuma URL formatları:
# /{application}/{profile}[/{label}]
# /{application}-{profile}.yml
# /{label}/{application}-{profile}.yml

# Order servisinin default profili üçün konfig
GET http://localhost:8888/order-service/default

# Order servisinin production profili
GET http://localhost:8888/order-service/production

# Xüsusi branch-dən
GET http://localhost:8888/order-service/production/feature-branch

# YAML formatında
GET http://localhost:8888/order-service-production.yml

# Properties formatında
GET http://localhost:8888/order-service-production.properties
```

```json
// Response formatı
{
  "name": "order-service",
  "profiles": ["production"],
  "label": "main",
  "version": "abc123def456",
  "propertySources": [
    {
      "name": "https://github.com/myorg/config-repo/order-service-production.yml",
      "source": {
        "spring.datasource.url": "jdbc:mysql://prod-db:3306/orders",
        "order.max-items-per-page": "50"
      }
    },
    {
      "name": "https://github.com/myorg/config-repo/application.yml",
      "source": {
        "management.endpoints.web.exposure.include": "health,info"
      }
    }
  ]
}
```

---

## Filesystem Backend

Development mühiti üçün local filesystem istifadəsi:

```yaml
# Config Server — application.yml (filesystem backend)
spring:
  profiles:
    active: native    # native = filesystem backend

  cloud:
    config:
      server:
        native:
          search-locations:
            - classpath:/config      # JAR içindəki /resources/config
            - file:///opt/config     # Xarici qovluq
            - file:${user.home}/config
```

```
src/main/resources/
├── application.yml               # Config Server-in öz konfigi
└── config/
    ├── application.yml           # Bütün servislərin ümumi
    ├── order-service.yml
    └── payment-service.yml
```

---

## Client Konfiqurasiyası

```xml
<!-- pom.xml — Config Client -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-config</artifactId>
</dependency>
```

```yaml
# application.yml — Config Client
spring:
  application:
    name: order-service    # Config server-dəki fayl adı ilə uyğun olmalı

  config:
    # Spring Boot 3.x — spring.config.import istifadə et
    import: optional:configserver:http://localhost:8888
    # optional: → Config server əlçatmaz olsa belə başla
    # configserver: → Config server-dən al

  cloud:
    config:
      # Profil (default: aktiv Spring profili)
      profile: ${spring.profiles.active:default}
      # Git branch/tag
      label: main
      # Fail fast — Config server əlçatmaz olsa başlama
      fail-fast: true
      # Retry mexanizmi
      retry:
        max-attempts: 6
        initial-interval: 1500
        max-interval: 10000
        multiplier: 1.5

  profiles:
    active: ${SPRING_PROFILES_ACTIVE:development}
```

### Config Dəyərlərini İnjekt Etmə

```java
@RestController
@RequestMapping("/api/config")
public class ConfigDemoController {

    // Sadə property injection
    @Value("${order.max-items-per-page:20}")
    private int maxItemsPerPage;

    @Value("${order.default-currency:AZN}")
    private String defaultCurrency;

    @GetMapping("/info")
    public Map<String, Object> getConfigInfo() {
        return Map.of(
            "maxItemsPerPage", maxItemsPerPage,
            "defaultCurrency", defaultCurrency
        );
    }
}
```

```java
// @ConfigurationProperties ilə qrup
@Component
@ConfigurationProperties(prefix = "order")
@Data
public class OrderProperties {
    private int maxItemsPerPage = 20;    // Default dəyər
    private String defaultCurrency = "AZN";
    private boolean vatEnabled = true;
    private double vatRate = 0.18;

    // Nested properties
    private NotificationConfig notification = new NotificationConfig();

    @Data
    public static class NotificationConfig {
        private boolean emailEnabled = true;
        private boolean smsEnabled = false;
    }
}
```

---

## @RefreshScope

`@RefreshScope` — Config Server-dəki dəyişikliklər tətbiq yenidən başlamadan tətbiq olunmasını təmin edir.

```java
// YANLIŞ — @RefreshScope olmadan property dəyişmir
@Service
public class OrderService {
    @Value("${order.max-items-per-page}")
    private int maxItemsPerPage;
    // Bu dəyər application başlayanda bir dəfə oxunur, sonra dəyişmir!
}

// DOĞRU — @RefreshScope ilə
@Service
@RefreshScope    // Bu bean /actuator/refresh sonra yenidən yaradılır
public class OrderService {
    @Value("${order.max-items-per-page}")
    private int maxItemsPerPage;
    // /actuator/refresh çağrıldıqdan sonra bu dəyər yenilənir
}
```

```java
// @ConfigurationProperties + @RefreshScope
@Component
@ConfigurationProperties(prefix = "order")
@RefreshScope
@Data
public class OrderProperties {
    private int maxItemsPerPage;
    private String defaultCurrency;
}
```

### /actuator/refresh Endpoint

```bash
# Config server-dəki dəyişikliyi tətbiq et
POST http://localhost:8081/actuator/refresh
Content-Type: application/json

# Response — hansı property-lər dəyişdi
["order.max-items-per-page", "order.default-currency"]
```

```yaml
# Actuator-da refresh endpoint-i açmaq lazımdır
management:
  endpoints:
    web:
      exposure:
        include: refresh, health, info
```

---

## Spring Cloud Bus

Problem: 100 microservice instance varsa, hər birini `/actuator/refresh` ilə yeniləmək çox çətin.

Həll: Spring Cloud Bus — RabbitMQ və ya Kafka üzərindən broadcast edir.

```xml
<!-- pom.xml — Bus + RabbitMQ -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-bus-amqp</artifactId>
</dependency>

<!-- Alternativ — Kafka ilə -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-bus-kafka</artifactId>
</dependency>
```

```yaml
# Hər servisin application.yml
spring:
  rabbitmq:
    host: localhost
    port: 5672
    username: guest
    password: guest

management:
  endpoints:
    web:
      exposure:
        include: bus-refresh, bus-env, refresh, health
```

```bash
# Bütün servisləri yenilə — yalnız bir servisə POST
POST http://localhost:8081/actuator/bus-refresh

# Yalnız xüsusi servis — service:instance formatı
POST http://localhost:8081/actuator/bus-refresh/order-service:**
POST http://localhost:8081/actuator/bus-refresh/order-service:8081
```

```
Spring Cloud Bus Axışı:
         Config Server dəyişir
                │
                ▼
    POST /actuator/bus-refresh (hər hansı bir servisə)
                │
                ▼
    ┌───────────────────────┐
    │   RabbitMQ / Kafka    │  ← RefreshRemoteApplicationEvent yayımlanır
    └───────────────────────┘
           │    │    │
           ▼    ▼    ▼
       Order  Pay  User
       Svc    Svc  Svc
     (hərəsi event alır və Config Server-dən yeni konfig oxuyur)
```

---

## Encryption / Decryption

Həssas məlumatlar (şifrə, API key) şifrələnmiş formada saxlanılır.

```yaml
# Config Server — application.yml
encrypt:
  key: my-secret-symmetric-key-min-32-chars!!   # Simmetrik şifrələmə
  # Asimmetrik (RSA keystore):
  # key-store:
  #   location: classpath:/keystore.jks
  #   password: keystorePassword
  #   alias: configserver
  #   secret: keyPassword
```

```bash
# Config server vasitəsilə şifrələmə
POST http://localhost:8888/encrypt
Body: mySecretPassword123

# Response: AQA3x7... (şifrələnmiş dəyər)

# Açmaq
POST http://localhost:8888/decrypt
Body: AQA3x7...

# Response: mySecretPassword123
```

```yaml
# config-repo/order-service.yml — şifrələnmiş dəyər
spring:
  datasource:
    username: order_user
    password: '{cipher}AQA3x7kMn8P2q9R5sT1uV6wX4yZ0...'
    # {cipher} prefix — Config Server bu dəyəri avtomatik açacaq
```

```java
// Client tərəfi — heç bir şey dəyişdirmir, Config Server avtomatik açır
@Value("${spring.datasource.password}")
private String dbPassword;
// Bu artıq açılmış şifrəni alır
```

### JKS Keystore ilə Asimmetrik Şifrələmə

```bash
# Keystore yaratma
keytool -genkeypair \
  -alias config-server-key \
  -keyalg RSA \
  -keysize 4096 \
  -sigalg SHA512withRSA \
  -dname "CN=Config Server,OU=IT,O=MyOrg,L=Baku,C=AZ" \
  -keypass changeme \
  -keystore config-server.jks \
  -storepass changeme \
  -validity 3650
```

```yaml
# Config Server — JKS keystore ilə
encrypt:
  key-store:
    location: classpath:/config-server.jks
    password: changeme
    alias: config-server-key
    secret: changeme
```

---

## Webhook ilə Avtomatik Yenilənmə

```yaml
# Config Server — GitHub webhook üçün
spring:
  cloud:
    config:
      server:
        git:
          uri: https://github.com/myorg/config-repo
          # Webhook gəldikdə avtomatik pull et
          refresh-rate: 0    # 0 = yalnız sorğu gəldikdə
```

```
GitHub Webhook Axışı:
1. Developer config-repo-ya commit push edir
2. GitHub webhook → POST /monitor → Config Server
3. Config Server Git-dən yeni konfigi çəkir
4. Bus-Refresh event yayımlanır (Bus aktivdirs)
5. Bütün servislər yeni konfigi alır
```

---

## İntervyu Sualları

**S: @RefreshScope necə işləyir?**
C: @RefreshScope annotasiyalı bean-lər Spring-in xüsusi scope-unda saxlanılır. `/actuator/refresh` çağrıldıqda bu scope-dakı bütün bean-lər destroy edilir, növbəti sorğuda yenidən yaradılır (yeni property dəyərləri ilə). Bütün digər bean-lər etkilənmir.

**S: spring.config.import nə vaxt əlavə olundu?**
C: Spring Boot 2.4+ versiyasında bootstrap.yml/bootstrap.properties faylı əvəzinə `spring.config.import` əlavə olundu. Spring Boot 3.x-də bu standart yanaşmadır. Bootstrap context artıq default aktiv deyil.

**S: Config Server-in fail-fast nədir?**
C: `spring.cloud.config.fail-fast: true` — client startup zamanı Config Server-ə çata bilmirsə, tətbiq başlamır. Bu production üçün tövsiyə olunur. `optional:configserver:` prefix isə config server olmadan da başlamağa icazə verir.

**S: Spring Cloud Bus nəyə lazımdır?**
C: 100 servis instance-ı varsa, hər birini `/actuator/refresh` ilə yeniləmək mümkün deyil. Cloud Bus RabbitMQ/Kafka üzərindən broadcast edir — bir yerə POST, hamısı alır.

**S: Həssas property-lər Git-də necə saxlanılır?**
C: `{cipher}` prefix ilə şifrələnmiş formada. Config Server `encrypt.key` ilə şifrəni açır, client-ə artıq açılmış dəyər çatır. Şifrələmə/açma Config Server-də baş verir, client heç nə bilmir.

**S: Konfiqurasiya Priorireti necədir?**
C: (Yüksəkdən aşağıya): Command line args > System properties > `application-{profile}.yml` > `application.yml` > Config Server-dən `{app}-{profile}.yml` > Config Server-dən `{app}.yml` > Config Server-dən `application.yml`. Daha spesifik fayl üstünlük alır.
