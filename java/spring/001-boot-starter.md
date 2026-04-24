# 001 — Spring Boot Starter
**Səviyyə:** Orta


## Mündəricat
1. [Starter nədir?](#nedir)
2. [Mövcud starter-ların nümunələri](#movcut)
3. [Custom starter yaratma — struktur](#struktur)
4. [Autoconfigure modulu](#autoconfigure)
5. [Starter modulu](#starter-modul)
6. [spring-boot-autoconfigure-processor](#processor)
7. [@ConditionalOnMissingBean pattern](#conditional)
8. [spring.provides faylı](#provides)
9. [Adlandırma konvensiyaları](#naming)
10. [İntervyu Sualları](#intervyu)

---

## 1. Starter nədir? {#nedir}

**Spring Boot Starter** — bir funksiyanı aktivləşdirmək üçün lazım olan bütün asılılıqları bir yerdə toplayan **convenience dependency aggregator** (rahatlıq asılılıq toplayan)-dır.

### Problem: Starter olmadan

```xml
<!-- YANLIŞ üsul — hər asılılığı əl ilə əlavə etmək lazımdır: -->
<dependencies>
    <!-- Spring MVC üçün lazım olan asılılıqlar: -->
    <dependency>
        <groupId>org.springframework</groupId>
        <artifactId>spring-webmvc</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework</groupId>
        <artifactId>spring-context</artifactId>
    </dependency>
    <dependency>
        <groupId>jakarta.servlet</groupId>
        <artifactId>jakarta.servlet-api</artifactId>
    </dependency>
    <dependency>
        <groupId>com.fasterxml.jackson.core</groupId>
        <artifactId>jackson-databind</artifactId>
    </dependency>
    <!-- ... daha çox asılılıq ... -->
</dependencies>
```

```xml
<!-- DOĞRU üsul — bir starter hər şeyi əhatə edir: -->
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-web</artifactId>
        <!-- Bu starter içəridə bütün lazımi asılılıqları əhatə edir -->
    </dependency>
</dependencies>
```

### Starter-ın əhatə etdiyi şeylər:

1. **Asılılıqlar** — lazımi kitabxanaların düzgün versiyaları
2. **Auto-configuration** — bean-ların avtomatik yaradılması
3. **Default konfiqurasiya** — ağıllı default dəyərlər

---

## 2. Mövcud starter-ların nümunələri {#movcut}

| Starter | Funksiya |
|---|---|
| `spring-boot-starter-web` | REST API, Spring MVC, Embedded Tomcat |
| `spring-boot-starter-data-jpa` | JPA, Hibernate, JDBC |
| `spring-boot-starter-security` | Spring Security |
| `spring-boot-starter-test` | JUnit, Mockito, AssertJ |
| `spring-boot-starter-actuator` | Monitoring endpoint-ləri |
| `spring-boot-starter-cache` | Caching abstraction |
| `spring-boot-starter-mail` | JavaMail |
| `spring-boot-starter-thymeleaf` | Thymeleaf şablonları |
| `spring-boot-starter-amqp` | RabbitMQ |
| `spring-boot-starter-kafka` | Apache Kafka |

---

## 3. Custom Starter yaratma — struktur {#struktur}

Custom starter iki ayrı Maven/Gradle modulu ilə yaradılır:

```
my-library-starter/
├── my-library-autoconfigure/     ← Auto-configuration modulu
│   ├── pom.xml
│   └── src/main/
│       ├── java/com/example/mylibrary/
│       │   ├── MyLibraryClient.java          ← Əsas kitabxana sinfi
│       │   ├── MyLibraryProperties.java       ← ConfigurationProperties
│       │   └── MyLibraryAutoConfiguration.java ← Auto-config
│       └── resources/META-INF/spring/
│           └── org.springframework.boot.autoconfigure.AutoConfiguration.imports
│
└── my-library-starter/           ← Starter modulu (yalnız pom.xml)
    └── pom.xml
```

### Niyə iki modul?

- **autoconfigure** — logic var, asılılıqlar optional
- **starter** — yalnız asılılıqlar, öz kodu yoxdur
- İstifadəçi starter-ı əlavə edir → autoconfigure avtomatik gəlir
- Kitabxana müəllifləri yalnız autoconfigure-u istifadə edə bilər (starter olmadan)

---

## 4. Autoconfigure modulu {#autoconfigure}

### pom.xml (autoconfigure modulu):

```xml
<project>
    <artifactId>my-library-autoconfigure</artifactId>

    <dependencies>
        <!-- Spring Boot auto-configuration dəstəyi -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-autoconfigure</artifactId>
            <!-- scope: compile — auto-config üçün lazımdır -->
        </dependency>

        <!-- Annotation processor — metadata yaradır -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-autoconfigure-processor</artifactId>
            <optional>true</optional>  <!-- compile-time lazımdır, runtime-da yox -->
        </dependency>

        <!-- Əsas kitabxana — optional! (istifadəçi əlavə etməsə de olur) -->
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-library-core</artifactId>
            <optional>true</optional>  <!-- @ConditionalOnClass üçün -->
        </dependency>
    </dependencies>
</project>
```

### Properties sinifi:

```java
@ConfigurationProperties(prefix = "mylib")
public class MyLibraryProperties {

    /**
     * API endpoint URL-i
     */
    private String apiUrl = "https://api.example.com";

    /**
     * Sorğu timeout-u (millisaniyə)
     */
    private int timeout = 5000;

    /**
     * API açarı — məcburidir
     */
    private String apiKey;

    /**
     * Bağlantı havuzu ölçüsü
     */
    private int connectionPoolSize = 10;

    // getter/setter-lər
    public String getApiUrl() { return apiUrl; }
    public void setApiUrl(String apiUrl) { this.apiUrl = apiUrl; }
    public int getTimeout() { return timeout; }
    public void setTimeout(int timeout) { this.timeout = timeout; }
    public String getApiKey() { return apiKey; }
    public void setApiKey(String apiKey) { this.apiKey = apiKey; }
    public int getConnectionPoolSize() { return connectionPoolSize; }
    public void setConnectionPoolSize(int size) { this.connectionPoolSize = size; }
}
```

### Auto-configuration sinifi:

```java
@AutoConfiguration
@ConditionalOnClass(MyLibraryClient.class)   // kitabxana classpath-dədir?
@ConditionalOnProperty(
    prefix = "mylib",
    name = "api-key"                          // api-key konfiqurasiya edilibsə aktiv et
)
@EnableConfigurationProperties(MyLibraryProperties.class)
public class MyLibraryAutoConfiguration {

    // Əsas client bean-ı
    @Bean
    @ConditionalOnMissingBean(MyLibraryClient.class)  // istifadəçi yaratmayıbsa
    public MyLibraryClient myLibraryClient(MyLibraryProperties props) {
        return MyLibraryClient.builder()
            .apiUrl(props.getApiUrl())
            .timeout(Duration.ofMillis(props.getTimeout()))
            .apiKey(props.getApiKey())
            .connectionPoolSize(props.getConnectionPoolSize())
            .build();
    }

    // Health indicator — Actuator ilə inteqrasiya
    @Bean
    @ConditionalOnMissingBean
    @ConditionalOnClass(name = "org.springframework.boot.actuate.health.HealthIndicator")
    public MyLibraryHealthIndicator myLibraryHealthIndicator(MyLibraryClient client) {
        return new MyLibraryHealthIndicator(client);
    }
}
```

### AutoConfiguration.imports faylı:

```
# src/main/resources/META-INF/spring/
# org.springframework.boot.autoconfigure.AutoConfiguration.imports

com.example.mylibrary.autoconfigure.MyLibraryAutoConfiguration
```

---

## 5. Starter modulu {#starter-modul}

Starter modulunun yalnız `pom.xml` faylı var — öz Java kodu olmur:

```xml
<project>
    <artifactId>my-library-spring-boot-starter</artifactId>
    <!-- QEYD: starter adının sonu "-spring-boot-starter" olmalıdır -->

    <dependencies>
        <!-- Autoconfigure modulunu əlavə et -->
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-library-autoconfigure</artifactId>
        </dependency>

        <!-- Spring Boot Starter baza asılılığı -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter</artifactId>
        </dependency>

        <!-- Əsas kitabxana — istifadəçiyə lazım olan -->
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-library-core</artifactId>
        </dependency>

        <!-- Əlavə lazımi asılılıqlar -->
        <dependency>
            <groupId>com.fasterxml.jackson.core</groupId>
            <artifactId>jackson-databind</artifactId>
        </dependency>
    </dependencies>
</project>
```

### İstifadəçi tərəfindən:

```xml
<!-- İstifadəçi yalnız bu bir asılılığı əlavə edir: -->
<dependency>
    <groupId>com.example</groupId>
    <artifactId>my-library-spring-boot-starter</artifactId>
    <version>1.0.0</version>
</dependency>
```

```properties
# application.properties
mylib.api-url=https://production-api.example.com
mylib.api-key=prod-secret-key
mylib.timeout=3000
mylib.connection-pool-size=20
```

```java
// Avtomatik inject — əlavə konfiqurasiya lazım deyil
@Service
public class DataService {
    private final MyLibraryClient client;

    public DataService(MyLibraryClient client) {
        this.client = client;
    }

    public List<Item> fetchItems() {
        return client.getItems();
    }
}
```

---

## 6. spring-boot-autoconfigure-processor {#processor}

Bu annotation processor compile vaxtında metadata faylı yaradır:

```xml
<!-- pom.xml-ə əlavə et: -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-autoconfigure-processor</artifactId>
    <optional>true</optional>  <!-- yalnız compile vaxtında lazımdır -->
</dependency>
```

### Nə yaradır?

```
# target/classes/META-INF/spring-autoconfigure-metadata.properties
# Compile vaxtında yaradılır — runtime-da şərtlər daha sürətli yoxlanılır

com.example.MyLibraryAutoConfiguration.ConditionalOnClass=\
  com.example.mylibrary.MyLibraryClient
com.example.MyLibraryAutoConfiguration.ConditionalOnProperty=\
  mylib.api-key
```

### Faydası:

- Auto-configuration sinifləri classpath skanı olmadan tez yüklənir
- Tətbiq başlama vaxtı azalır
- Spring Boot-un özü bu mexanizmdən geniş istifadə edir

---

## 7. @ConditionalOnMissingBean pattern {#conditional}

Bu pattern custom starter-ın ən vacib dizayn qərarıdır:

```java
// YANLIŞ — istifadəçi override edə bilmir:
@AutoConfiguration
public class BadAutoConfiguration {

    @Bean
    public MyService myService() {
        // istifadəçi öz MyService bean-ını yaratsa belə,
        // bu da yaranacaq → DuplicateBean xətası!
        return new MyService("default-config");
    }
}

// DOĞRU — istifadəçi asanlıqla override edə bilir:
@AutoConfiguration
public class GoodAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean(MyService.class)  // yalnız yoxdursa yarat
    public MyService myService(MyLibraryProperties props) {
        return new MyService(props.getApiUrl());
    }
}
```

### İstifadəçi öz implementasiyasını yaradır:

```java
@Configuration
public class MyCustomConfig {

    // Bu bean @ConditionalOnMissingBean-ı tetikləyir
    // Auto-configuration default MyService-i yaratmayacaq
    @Bean
    public MyService customMyService() {
        MyService service = new MyService("custom-url");
        service.setRetryCount(5);
        service.setCustomHeader("X-My-Header", "value");
        return service;
    }
}
```

---

## 8. spring.provides faylı {#provides}

Bu fayl IDE-lərə (məsələn, IntelliJ IDEA) starter-ın nə təmin etdiyini bildirir:

```
# src/main/resources/META-INF/spring.provides

# Bu starter-ın təqdim etdiyi artifact-lar:
provides: my-library-core,my-library-autoconfigure
```

### Nə üçün lazımdır?

- IDE-lər bu faylı oxuyaraq kod tamamlama təklif edir
- `application.properties`-də `mylib.*` property-ləri üçün auto-complete işləyir
- Spring Initializr bu məlumatı istifadə edir

---

## 9. Adlandırma konvensiyaları {#naming}

### Rəsmi Spring Boot qaydaları:

```
# Üçüncü tərəf starter-lar (siz yaradırsınız):
{your-name}-spring-boot-starter

# Spring Boot-un öz starter-ları:
spring-boot-starter-{feature}
```

### Nümunələr:

```
# DOĞRU — üçüncü tərəf:
my-library-spring-boot-starter
acme-cache-spring-boot-starter
my-company-auth-spring-boot-starter

# YANLIŞ — Spring adını istifadə etmə:
spring-boot-starter-my-library  ← yalnız Spring özü belə adlandırır

# Autoconfigure modulu:
my-library-autoconfigure        ← "-autoconfigure" son eki

# Starter modulu:
my-library-spring-boot-starter  ← "-spring-boot-starter" son eki
```

### Paket adlandırması:

```java
// DOĞRU — öz domain adınız altında:
package com.mycompany.mylibrary.autoconfigure;

// YANLIŞ — Spring paketini işğal etmə:
package org.springframework.boot.autoconfigure.mylibrary;
```

---

## 10. Tam nümunə — SMS Starter

```java
// SmsProperties.java
@ConfigurationProperties(prefix = "sms")
public class SmsProperties {
    private String provider = "twilio";   // default provider
    private String accountSid;
    private String authToken;
    private String fromNumber;

    public String getProvider() { return provider; }
    public void setProvider(String provider) { this.provider = provider; }
    public String getAccountSid() { return accountSid; }
    public void setAccountSid(String accountSid) { this.accountSid = accountSid; }
    public String getAuthToken() { return authToken; }
    public void setAuthToken(String authToken) { this.authToken = authToken; }
    public String getFromNumber() { return fromNumber; }
    public void setFromNumber(String fromNumber) { this.fromNumber = fromNumber; }
}
```

```java
// SmsSender.java — interfeys
public interface SmsSender {
    void send(String to, String message);
}
```

```java
// SmsAutoConfiguration.java
@AutoConfiguration
@ConditionalOnClass(SmsSender.class)
@ConditionalOnProperty(prefix = "sms", name = {"account-sid", "auth-token"})
@EnableConfigurationProperties(SmsProperties.class)
public class SmsAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean(SmsSender.class)
    @ConditionalOnProperty(prefix = "sms", name = "provider", havingValue = "twilio",
                           matchIfMissing = true)
    public SmsSender twilioSmsSender(SmsProperties props) {
        // Twilio client yarat
        return new TwilioSmsSender(
            props.getAccountSid(),
            props.getAuthToken(),
            props.getFromNumber()
        );
    }

    @Bean
    @ConditionalOnMissingBean(SmsSender.class)
    @ConditionalOnProperty(prefix = "sms", name = "provider", havingValue = "nexmo")
    public SmsSender nexmoSmsSender(SmsProperties props) {
        // Nexmo client yarat
        return new NexmoSmsSender(props.getAccountSid(), props.getAuthToken());
    }
}
```

---

## İntervyu Sualları {#intervyu}

**S: Spring Boot Starter nədir?**
C: Bir funksiyanı aktivləşdirmək üçün lazım olan bütün asılılıqları bir yerdə toplayan convenience dependency aggregator-dır. Kitabxana asılılıqları + auto-configuration + default konfiqurasiyadan ibarətdir.

**S: Niyə starter iki modul (autoconfigure + starter) ilə yaradılır?**
C: Autoconfigure modulu logic içərir, starter isə yalnız asılılıqları birləşdirir. Bu ayrılıq sayəsində kitabxana müəllifləri yalnız autoconfigure modulundan istifadə edə bilər, istifadəçilər isə starter-ı əlavə edərək hər şeyi avtomatik alar.

**S: @ConditionalOnMissingBean niyə vacibdir?**
C: Bu pattern sayəsində auto-configuration "ağıllı default" verir: istifadəçi heç nə etmədən kitabxana işləyir, lakin istəsə öz implementasiyasını yaradaraq default-u override edə bilər. Onsuz DuplicateBean xətası olar.

**S: spring-boot-autoconfigure-processor nə edir?**
C: Compile vaxtında `spring-autoconfigure-metadata.properties` faylı yaradır. Bu fayl sayəsində Spring Boot runtime-da şərtləri daha tez yoxlaya bilir — tətbiq daha sürətli başlayır.

**S: Üçüncü tərəf starter-ın adı necə olmalıdır?**
C: `{name}-spring-boot-starter` formatında olmalıdır (məsələn, `acme-cache-spring-boot-starter`). `spring-boot-starter-{name}` formatı yalnız rəsmi Spring Boot starter-ları üçündür.
