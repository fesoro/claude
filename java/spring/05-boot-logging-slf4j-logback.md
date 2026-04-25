# 05 — Logging (SLF4J + Logback)

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Niyə logging? `println` niyə bəs etmir?](#niye-logging)
2. [Logging facade — SLF4J nədir?](#slf4j)
3. [Log implementation seçimləri — Logback vs Log4j2](#implementations)
4. [Log səviyyələri hierarxiyası](#levels)
5. [Logger almaq — 3 üsul](#get-logger)
6. [Parameterized logging vs String concat](#parameterized)
7. [application.properties-də log səviyyələri](#levels-props)
8. [Log format (pattern) fərdiləşdirmə](#pattern)
9. [Fayla log yazmaq](#file-log)
10. [logback-spring.xml — tam konfiqurasiya](#logback-xml)
11. [Profile-ə görə fərqli logging](#profile-logging)
12. [MDC — request tracing](#mdc)
13. [Microservice-lərdə traceId/spanId](#tracing)
14. [Structured JSON logs](#json-logs)
15. [ELK/Loki və log aggregation](#elk)
16. [Security: nə log EDİLMƏMƏLİDİR](#security)
17. [Log sampling və performans](#sampling)
18. [Ümumi Səhvlər](#umumi-sehvler)
19. [İntervyu Sualları](#intervyu)

---

## 1. Niyə logging? `println` niyə bəs etmir? {#niye-logging}

### `System.out.println` ilə problem

```java
// YANLIŞ — production kodunda println
public User findUser(Long id) {
    System.out.println("Finding user " + id);  // stdout-a yazır
    User user = repository.findById(id);
    System.out.println("Found: " + user);
    return user;
}
```

**Problemlər:**

| Problem | İzah |
|---|---|
| Səviyyə yoxdur | DEBUG vs ERROR ayırd etmək mümkün deyil |
| Filterlənmir | Production-da hər şey çap olunur |
| Format fərdiləşmir | Timestamp, thread adı, class adı əl ilə |
| Performans | `System.out` synchronised — blok edir |
| Fayla yazılmır | Yalnız ekran/konsol |
| Xaricə göndərilmir | ELK, Grafana Loki-yə inteqrasiya yoxdur |
| Rotation yoxdur | Fayl böyüyür, heç silinmir |

### Logging framework-u ilə

```java
@Slf4j  // Lombok — log dəyişəni yaradır
public class UserService {

    public User findUser(Long id) {
        log.debug("Finding user {}", id);
        User user = repository.findById(id);
        log.info("Found user: {}", user.getEmail());
        return user;
    }
}
```

- **Səviyyə**-lər var: TRACE/DEBUG/INFO/WARN/ERROR
- Production-da yalnız INFO+ görünür
- Timestamp, thread, class avtomatik
- Faylda, konsolda, JSON-da eyni anda
- Mikrosaniyə dəqiqlikli

---

## 2. Logging facade — SLF4J nədir? {#slf4j}

**SLF4J** (Simple Logging Facade for Java) — bir neçə logging implementation-u üçün **ümumi interface**dir.

```
       Kod sizin kontrolunuzda
       ─────────────────────
        UserService
            │
            ▼ (log.info(...) çağırır)
       ─────────────────────
        SLF4J API (facade)       ← bu interface-dir
       ─────────────────────
            │
   ┌────────┼────────┬───────┐
   ▼        ▼        ▼       ▼
 Logback  Log4j2  java.util  Simple
(default)         logging    (for tests)
```

### Facade niyə yaxşıdır?

Sizin kod SLF4J API-yə yazır. İmplementation-u dəyişmək istəsəniz (Logback → Log4j2) — **kodda heç nə dəyişmir**, yalnız dependency dəyişir.

```java
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class UserService {
    // API — hər yerdə eyni
    private static final Logger log = LoggerFactory.getLogger(UserService.class);

    public void doSomething() {
        log.info("Hello");
    }
}
```

### Spring Boot-da default

Spring Boot starter-lərində **Logback** default-dur. SLF4J API + Logback implementation.

---

## 3. Log implementation seçimləri — Logback vs Log4j2 {#implementations}

| Xüsusiyyət | Logback | Log4j2 |
|---|---|---|
| Spring Boot default | Bəli | Yox (əlavə etmək lazım) |
| Performans | Yaxşı | Daha yaxşı (async appender) |
| Konfiqurasiya | XML / Groovy | XML / JSON / YAML / Properties |
| Avtomatik reload | Bəli | Bəli |
| Async logging | `AsyncAppender` | LMAX Disruptor (çox sürətli) |
| Olgunluq | 2006-dan | 2014-dən |
| Məşhurluq | Widespread | Artmaqdadır |

### Logback seçim (default)

```xml
<!-- pom.xml-də heç nə etmək lazım deyil -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <!-- Logback buradadır, avtomatik gəlir -->
</dependency>
```

### Log4j2 seçmək

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <exclusions>
        <exclusion>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-logging</artifactId>
        </exclusion>
    </exclusions>
</dependency>

<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-log4j2</artifactId>
</dependency>
```

**Tövsiyə:** yeni başlayanlar üçün Logback (default), çox yüksək load-da Log4j2 async.

---

## 4. Log səviyyələri hierarxiyası {#levels}

```
TRACE  (ən detallı)
  ↓
DEBUG  (inkişaf / troubleshooting)
  ↓
INFO   (normal hadisələr — tətbiq başladı, request qəbul olundu)
  ↓
WARN   (qeyri-adi hadisə, amma kritik deyil)
  ↓
ERROR  (xəta — nəsə uğursuz oldu)
  ↓
OFF    (heç nə loglanmır)
```

**Vacib qayda:** müəyyən səviyyəni seçsəniz, ondan **yuxarı** olan bütün səviyyələr log olunur.

| Aktiv səviyyə | Nə loglanır? |
|---|---|
| TRACE | Hər şey |
| DEBUG | DEBUG, INFO, WARN, ERROR |
| INFO | INFO, WARN, ERROR |
| WARN | WARN, ERROR |
| ERROR | Yalnız ERROR |
| OFF | Heç nə |

### Hansı səviyyəni nə vaxt istifadə etmək?

```java
@Service
@Slf4j
public class OrderService {

    public Order create(OrderRequest req) {
        // TRACE — çox detallı, iterasiya daxili
        log.trace("Processing item: {}", req.getItem());

        // DEBUG — inkişafda faydalı, production-da söndür
        log.debug("Calculating total for {} items", req.getItems().size());

        // INFO — biznes hadisə, audit üçün
        log.info("Order created: id={}, user={}", order.getId(), user.getId());

        // WARN — gözlənilməz vəziyyət, amma işləyir
        if (stock < 10) {
            log.warn("Low stock for product {}: {} left", product.getId(), stock);
        }

        // ERROR — xəta, alert tələb edə bilər
        try {
            paymentService.charge(order);
        } catch (PaymentException e) {
            log.error("Payment failed for order {}", order.getId(), e);
            throw e;
        }

        return order;
    }
}
```

### Production-da standart səviyyələr

- **Root:** `INFO`
- **Sizin kod:** `INFO` və ya `DEBUG` (troubleshooting üçün)
- **Framework (Spring, Hibernate):** `WARN`

---

## 5. Logger almaq — 3 üsul {#get-logger}

### Üsul 1 — Klassik SLF4J

```java
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class UserService {
    private static final Logger log = LoggerFactory.getLogger(UserService.class);

    public void doWork() {
        log.info("Working...");
    }
}
```

- **`private static final`** — tək instansı bir dəfə yaradılır.
- **`Class.class`** — logger adı sinfin tam adı olur: `com.example.UserService`.

### Üsul 2 — Lombok `@Slf4j`

```java
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class UserService {
    public void doWork() {
        log.info("Working...");
    }
}
```

Lombok kompilyasiya zamanı:
```java
private static final Logger log = LoggerFactory.getLogger(UserService.class);
```

sətrini avtomatik əlavə edir. Daha az boilerplate.

### Üsul 3 — Java 17+ manual

```java
public class UserService {
    // Java 17+ static factory method çağırışı
    private static final Logger log = LoggerFactory.getLogger(MethodHandles.lookup().lookupClass());
}
```

Refactoring zamanı sinif adını dəyişdirsəniz, logger adı özü ilə gedir.

### Tövsiyə

Spring Boot + Lombok layihələrində: **`@Slf4j`**. Ən qısa, standart.

---

## 6. Parameterized logging vs String concat {#parameterized}

Bu mövzu performans üçün kritikdir.

### YANLIŞ — String concat

```java
log.debug("User " + userId + " logged in at " + timestamp);
```

**Niyə pisdir?**
Hətta DEBUG söndürülsə də, `"User " + userId + ...` string-i yaradılır — prosessor boşa vaxt sərf edir.

### DOĞRU — parameterized logging

```java
log.debug("User {} logged in at {}", userId, timestamp);
```

**Necə işləyir?**
- `{}` placeholder-dir.
- SLF4J əvvəl səviyyəni yoxlayır (DEBUG aktivdir?).
- Aktiv olmasa, heç nə formatlaşdırmır — sürətli.
- Aktiv olsa, `toString()` çağırır və formatlaşdırır.

### Müqayisə

```java
// Performans test — 1 milyon iterasiya
// DEBUG söndürülmüş

log.debug("User " + user.toString() + " did " + expensiveOperation());
// 🐌 ~2 saniyə — hər dəfə toString və expensive çağırılır

log.debug("User {} did {}", user, expensiveOperation());
// 🐌 ~2 saniyə — expensiveOperation arqument kimi çağırılır

log.debug("User {} did {}", user, () -> expensiveOperation());
// ⚡ ~10 ms — lambda yalnız DEBUG aktiv olduqda çağırılır
```

### Exception loglamaq

```java
try {
    // ...
} catch (Exception e) {
    // YANLIŞ — stack trace görünməz:
    log.error("Failed: " + e.getMessage());

    // DOĞRU — exception-u axırıncı arqument kimi ötür
    log.error("Failed to process request", e);

    // Parametr + exception
    log.error("Failed to process user {}", userId, e);
}
```

SLF4J exception-u axırıncı arqument olaraq tanıyır, stack trace-i tam yazır.

---

## 7. application.properties-də log səviyyələri {#levels-props}

### Ümumi (root) səviyyə

```properties
logging.level.root=INFO
```

### Paket səviyyəsində

```properties
logging.level.com.example=DEBUG
logging.level.com.example.service=TRACE
logging.level.org.springframework=WARN
logging.level.org.hibernate=WARN
logging.level.org.hibernate.SQL=DEBUG               # SQL sorğularını göstər
logging.level.org.hibernate.type.descriptor.sql=TRACE  # SQL parametrləri göstər
```

### Tipik ssenarilər

**Inkişaf (dev) üçün:**
```properties
logging.level.root=INFO
logging.level.com.example=DEBUG
logging.level.org.hibernate.SQL=DEBUG
```

**Production üçün:**
```properties
logging.level.root=WARN
logging.level.com.example=INFO
logging.level.org.springframework=ERROR
```

### YAML variantı

```yaml
logging:
  level:
    root: INFO
    com.example: DEBUG
    org.springframework: WARN
    org.hibernate.SQL: DEBUG
```

### Qruplaşdırılmış logger-lər

```properties
# Qrup tərif et
logging.group.tomcat=org.apache.catalina,org.apache.coyote

# Qrup üçün səviyyə
logging.level.tomcat=ERROR
```

---

## 8. Log format (pattern) fərdiləşdirmə {#pattern}

### Default Spring Boot format

```
2026-04-24T10:30:15.123+04:00  INFO 12345 --- [nio-8080-exec-1] c.e.demo.UserController : User 42 logged in
        ↑                       ↑     ↑          ↑                ↑                        ↑
    timestamp                level  PID     thread adı    class adı               mesaj
```

### Konsol formatını dəyişmək

```properties
logging.pattern.console=%d{yyyy-MM-dd HH:mm:ss} %-5level [%thread] %logger{36} - %msg%n
```

### Ən çox istifadə edilən pattern-lər

| Pattern | Nə göstərir? |
|---|---|
| `%d{yyyy-MM-dd HH:mm:ss.SSS}` | Timestamp |
| `%-5level` | Log səviyyəsi (5 simvol sahə) |
| `%thread` | Thread adı |
| `%logger{36}` | Class adı (36 simvol-a qədər) |
| `%msg` | Log mesajı |
| `%n` | Yeni sətir |
| `%ex` | Exception stack trace |
| `%X{key}` | MDC dəyəri |
| `%clr(%msg){red}` | Rəngli (konsolda) |

### Spring Boot-un rəngli formatı

```properties
logging.pattern.console=%clr(%d{HH:mm:ss.SSS}){faint} %clr(%-5level) %clr(%-40.40logger{39}){cyan} : %msg%n
```

### Fayl üçün ayrıca format

```properties
logging.pattern.file=%d{yyyy-MM-dd HH:mm:ss} [%thread] %-5level %logger{36} - %msg%n
```

Fayl formatı rəng kodlarını daxil **etmə** (fayl oxunuşu çətinləşir).

---

## 9. Fayla log yazmaq {#file-log}

### Sadə üsul

```properties
# Konkret fayl adı
logging.file.name=logs/app.log

# Yalnız qovluq (fayl adı: spring.log)
logging.file.path=/var/log/myapp

# İkisindən yalnız biri istifadə edilir
```

### Rolling file policy

Fayl çox böyüyəndə yeniyə keçir (rotation):

```properties
# Maksimum fayl ölçüsü
logging.logback.rollingpolicy.max-file-size=10MB

# Saxlanılacaq arxiv sayı
logging.logback.rollingpolicy.max-history=30

# Total disk məhdudiyyəti
logging.logback.rollingpolicy.total-size-cap=1GB

# Arxiv adı pattern-i
logging.logback.rollingpolicy.file-name-pattern=logs/app-%d{yyyy-MM-dd}.%i.log.gz

# Başlanğıcda rotate et
logging.logback.rollingpolicy.clean-history-on-start=true
```

Nəticədə disk-də görəcəksən:
```
logs/
├── app.log                         # cari
├── app-2026-04-23.0.log.gz         # dünən
├── app-2026-04-22.0.log.gz
└── app-2026-04-22.1.log.gz         # 10MB-dan sonra ikinci fayl
```

---

## 10. logback-spring.xml — tam konfiqurasiya {#logback-xml}

`application.properties` sadə hallar üçün yetərlidir. Lakin daxili mexanizmləri tam idarə etmək üçün `src/main/resources/logback-spring.xml` faylı yaradırıq.

**Vacib:** `logback.xml` YOX, **`logback-spring.xml`** olmalıdır — Spring-in `<springProfile>` tag-ini dəstəkləmək üçün.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>

    <!-- Spring Boot defaults daxil et -->
    <include resource="org/springframework/boot/logging/logback/defaults.xml"/>

    <!-- Log faylları yerləşəcəyi qovluq -->
    <property name="LOG_PATH" value="${LOG_PATH:-logs}"/>
    <property name="LOG_FILE" value="${LOG_PATH}/app.log"/>

    <!-- 1) KONSOL APPENDER -->
    <appender name="CONSOLE" class="ch.qos.logback.core.ConsoleAppender">
        <encoder>
            <pattern>
                %d{HH:mm:ss.SSS} %-5level [%thread] %logger{36} - %msg%n
            </pattern>
        </encoder>
    </appender>

    <!-- 2) FAYL APPENDER (rolling) -->
    <appender name="FILE" class="ch.qos.logback.core.rolling.RollingFileAppender">
        <file>${LOG_FILE}</file>

        <rollingPolicy class="ch.qos.logback.core.rolling.SizeAndTimeBasedRollingPolicy">
            <fileNamePattern>${LOG_PATH}/app-%d{yyyy-MM-dd}.%i.log.gz</fileNamePattern>
            <maxFileSize>10MB</maxFileSize>
            <maxHistory>30</maxHistory>
            <totalSizeCap>1GB</totalSizeCap>
        </rollingPolicy>

        <encoder>
            <pattern>
                %d{yyyy-MM-dd HH:mm:ss.SSS} %-5level [%thread] %logger{36} - %msg%n
            </pattern>
        </encoder>
    </appender>

    <!-- 3) JSON APPENDER (production üçün) -->
    <appender name="JSON" class="ch.qos.logback.core.rolling.RollingFileAppender">
        <file>${LOG_PATH}/app.json</file>

        <rollingPolicy class="ch.qos.logback.core.rolling.SizeAndTimeBasedRollingPolicy">
            <fileNamePattern>${LOG_PATH}/app-%d{yyyy-MM-dd}.%i.json.gz</fileNamePattern>
            <maxFileSize>50MB</maxFileSize>
            <maxHistory>15</maxHistory>
        </rollingPolicy>

        <encoder class="net.logstash.logback.encoder.LogstashEncoder">
            <includeMdcKeyName>traceId</includeMdcKeyName>
            <includeMdcKeyName>spanId</includeMdcKeyName>
            <includeMdcKeyName>userId</includeMdcKeyName>
        </encoder>
    </appender>

    <!-- 4) ASYNC wrapper — performansı artırır -->
    <appender name="ASYNC_FILE" class="ch.qos.logback.classic.AsyncAppender">
        <appender-ref ref="FILE"/>
        <queueSize>512</queueSize>
        <discardingThreshold>0</discardingThreshold>
        <includeCallerData>false</includeCallerData>
    </appender>

    <!-- Profile-ə görə fərqli konfiqurasiya -->
    <springProfile name="dev">
        <root level="INFO">
            <appender-ref ref="CONSOLE"/>
        </root>
        <logger name="com.example" level="DEBUG"/>
    </springProfile>

    <springProfile name="prod">
        <root level="WARN">
            <appender-ref ref="ASYNC_FILE"/>
            <appender-ref ref="JSON"/>
        </root>
        <logger name="com.example" level="INFO"/>
    </springProfile>

    <!-- Default (profil yoxdursa) -->
    <springProfile name="default">
        <root level="INFO">
            <appender-ref ref="CONSOLE"/>
            <appender-ref ref="FILE"/>
        </root>
    </springProfile>

</configuration>
```

### JSON encoder üçün dependency

```xml
<dependency>
    <groupId>net.logstash.logback</groupId>
    <artifactId>logstash-logback-encoder</artifactId>
    <version>7.4</version>
</dependency>
```

---

## 11. Profile-ə görə fərqli logging {#profile-logging}

### `application.properties`-də

```properties
# application-dev.properties
logging.level.root=DEBUG
logging.level.com.example=TRACE
logging.file.name=logs/dev.log

# application-prod.properties
logging.level.root=WARN
logging.level.com.example=INFO
logging.file.name=/var/log/app/prod.log
```

### `logback-spring.xml`-də

```xml
<springProfile name="dev">
    <logger name="com.example" level="DEBUG"/>
    <logger name="org.hibernate.SQL" level="DEBUG"/>
</springProfile>

<springProfile name="prod">
    <logger name="com.example" level="INFO"/>
    <logger name="org.hibernate.SQL" level="WARN"/>
</springProfile>

<springProfile name="dev | staging">
    <!-- İki profil üçün -->
</springProfile>

<springProfile name="!prod">
    <!-- prod OLMAYAN mühitlərdə -->
</springProfile>
```

---

## 12. MDC — request tracing {#mdc}

**MDC (Mapped Diagnostic Context)** — hər thread-ə bağlanmış açar-dəyər saxlama yeridir. Request başına user ID, request ID əlavə etməyə imkan verir.

### İstifadə

```java
import org.slf4j.MDC;

public class RequestFilter implements Filter {
    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain)
            throws IOException, ServletException {

        String requestId = UUID.randomUUID().toString();

        try {
            MDC.put("requestId", requestId);
            MDC.put("userId", extractUserId(request));

            chain.doFilter(request, response);
        } finally {
            MDC.clear();  // MÜHÜM — leak-i önlə
        }
    }
}
```

### Log pattern-də MDC-ni göstər

```xml
<pattern>
    %d{HH:mm:ss.SSS} [%X{requestId}] [%X{userId}] %-5level %logger - %msg%n
</pattern>
```

### Nəticə

```
10:30:15.123 [a1b2c3d4] [user-42] INFO  c.e.UserService - Finding user
10:30:15.145 [a1b2c3d4] [user-42] DEBUG c.e.UserRepo    - Query executed
10:30:15.150 [a1b2c3d4] [user-42] INFO  c.e.UserService - User found
10:30:16.001 [x9y8z7w6] [user-99] INFO  c.e.UserService - Finding user   ← fərqli request
```

Eyni `requestId`-yə görə loglarda bir request-in bütün addımlarını izləmək mümkündür.

### Spring-də asan yol

```java
@Component
public class MdcFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest request, HttpServletResponse response, FilterChain chain)
            throws ServletException, IOException {

        try {
            String requestId = request.getHeader("X-Request-ID");
            if (requestId == null) {
                requestId = UUID.randomUUID().toString();
            }
            MDC.put("requestId", requestId);

            response.setHeader("X-Request-ID", requestId);  // client-ə geri qaytar

            chain.doFilter(request, response);
        } finally {
            MDC.clear();
        }
    }
}
```

---

## 13. Microservice-lərdə traceId/spanId {#tracing}

Bir request bir neçə mikroservisdən keçir. Hamısını izləmək üçün **distributed tracing** lazımdır.

### Spring Boot 3 — Micrometer Tracing

```xml
<dependency>
    <groupId>io.micrometer</groupId>
    <artifactId>micrometer-tracing-bridge-brave</artifactId>
</dependency>

<dependency>
    <groupId>io.zipkin.reporter2</groupId>
    <artifactId>zipkin-reporter-brave</artifactId>
</dependency>
```

### Default log pattern-inə avtomatik əlavə

```properties
management.tracing.sampling.probability=1.0
```

Log-da avtomatik görünəcək:
```
2026-04-24 10:30:15.123 INFO [my-app,a1b2c3d4e5,f6g7h8i9] - User created
                              ^        ^          ^
                           app adı  traceId    spanId
```

### Mikroservislər arası yayılma

Service A → Service B çağırışında `traceId` HTTP header (`traceparent`) vasitəsilə ötürülür. Hər service eyni `traceId`, lakin fərqli `spanId` istifadə edir.

### Bütün servislərdə logları birləşdirmək

Zipkin, Jaeger və ya Grafana Tempo `traceId`-yə görə bütün service loglarını cəmləyir:

```
trace a1b2c3d4e5
├── span f6g7h8i9 — api-gateway (5ms)
├── span j1k2l3m4 — user-service (20ms)
├── span n5o6p7q8 — notification-service (15ms)
└── span r9s0t1u2 — email-service (200ms)
```

---

## 14. Structured JSON logs {#json-logs}

Production-da logları **JSON formatında** yazmaq vacibdir — ELK, Loki, CloudWatch bu formatı yaxşı oxuyur.

### Setup

```xml
<!-- pom.xml -->
<dependency>
    <groupId>net.logstash.logback</groupId>
    <artifactId>logstash-logback-encoder</artifactId>
    <version>7.4</version>
</dependency>
```

### logback-spring.xml

```xml
<appender name="JSON" class="ch.qos.logback.core.ConsoleAppender">
    <encoder class="net.logstash.logback.encoder.LogstashEncoder">
        <!-- MDC dəyərlərini daxil et -->
        <includeMdcKeyName>requestId</includeMdcKeyName>
        <includeMdcKeyName>userId</includeMdcKeyName>
        <includeMdcKeyName>traceId</includeMdcKeyName>

        <!-- Əlavə custom sahələr -->
        <customFields>{"service":"user-service","env":"prod","version":"1.2.3"}</customFields>

        <!-- Timestamp formatı -->
        <timeZone>UTC</timeZone>
    </encoder>
</appender>
```

### Nəticə — hər log bir JSON sətir

```json
{
  "@timestamp": "2026-04-24T10:30:15.123Z",
  "@version": "1",
  "message": "User 42 logged in",
  "logger_name": "com.example.UserController",
  "thread_name": "http-nio-8080-exec-1",
  "level": "INFO",
  "level_value": 20000,
  "requestId": "a1b2c3d4",
  "userId": "42",
  "traceId": "xyz789",
  "service": "user-service",
  "env": "prod",
  "version": "1.2.3"
}
```

### Niyə JSON?

- Parsing asandır (grep/jq ilə işləmək əvəzinə).
- Elastic/Loki avtomatik indekslər sahələri.
- Axtarış: `level:ERROR AND userId:42`.
- Aggregation: "son 5 dəqiqədə ERROR sayı".

---

## 15. ELK/Loki və log aggregation {#elk}

Microservice arxitekturasında hər servis öz logunu yazır. Bunları cəmləmək üçün:

### ELK Stack

```
Tətbiq logs (JSON) → Filebeat → Logstash → Elasticsearch → Kibana (UI)
```

- **Filebeat** — fayl-ları oxuyub şəbəkə ilə göndərir.
- **Logstash** — parsing və zənginləşdirmə.
- **Elasticsearch** — indekslənmiş anbar.
- **Kibana** — vizuallaşdırma və axtarış.

### Grafana Loki

```
Tətbiq logs → Promtail → Loki → Grafana (UI)
```

- Daha sadə və ucuz (indexing yalnız label-lara).
- Prometheus-a bənzər sorğu dili (LogQL).

### CloudWatch (AWS)

```
Tətbiq logs (stdout) → Fluent Bit → CloudWatch Logs → CloudWatch Insights
```

Konteyner və Lambda üçün avtomatik.

### Ən vacib tövsiyə

**Docker/Kubernetes-də** stdout-a log yaz, fayla YOX:
- Platformaya log-un idarəsini ötür.
- Sidecar (Fluent Bit, Filebeat) stdout-dan oxuyur.

```xml
<!-- Container-lər üçün -->
<root level="INFO">
    <appender-ref ref="CONSOLE_JSON"/>  <!-- stdout -->
</root>
```

---

## 16. Security: nə log EDİLMƏMƏLİDİR {#security}

Log faylı adətən kənar yerə göndərilir, backup-da saxlanılır. Bəzi məlumatlar **heç vaxt** loglanmamalıdır.

### QADAĞA olanlar (PII, secrets)

| Kateqoriya | Nümunə |
|---|---|
| Parol | `log.info("Login: {}, password: {}", user, password)` ❌ |
| Token/API key | `log.debug("Auth: {}", authHeader)` ❌ |
| Kredit kartı | `log.info("Card: {}", cardNumber)` ❌ |
| SSN / ID nömrəsi | `log.info("SSN: {}", ssn)` ❌ |
| Tam email | Bəzi ölkələrdə PII — maskala: `a***@gmail.com` |
| Tam ad | Kontekstdən asılı — maskala |
| Session ID | Tam yox, hash-la |
| Medical data | HIPAA-ya görə qadağandır |

### Nümunə — təhlükəsiz loglamaq

```java
// YANLIŞ
log.info("User created: {}", user);
// user.toString() → bütün sahələr (şifrə daxil)!

// DOĞRU
log.info("User created: id={}, email={}", user.getId(), mask(user.getEmail()));
// mask("ali@example.com") → "a**@example.com"

private String mask(String email) {
    int at = email.indexOf('@');
    if (at <= 1) return "***";
    return email.charAt(0) + "***" + email.substring(at);
}
```

### `toString()`-i override et

```java
public class User {
    private Long id;
    private String email;
    private String password;

    @Override
    public String toString() {
        return "User{id=" + id + ", email=" + mask(email) + "}";
        // password HEÇ VAXT daxil edilməməlidir
    }
}
```

### Lombok `@ToString` ilə sahələri çıxart

```java
@ToString(exclude = {"password", "creditCard", "ssn"})
public class User {
    private Long id;
    private String email;
    private String password;
    private String creditCard;
    private String ssn;
}
```

---

## 17. Log sampling və performans {#sampling}

Yüksək load-da hər request-i loglamaq performansı aşağı sala bilər.

### Problem

```
1000 req/s × 10 log sətri / req = 10,000 log/s
→ fayl I/O blok edir
→ latency artır
```

### Həll 1 — Async appender

```xml
<appender name="ASYNC" class="ch.qos.logback.classic.AsyncAppender">
    <appender-ref ref="FILE"/>
    <queueSize>1024</queueSize>
    <discardingThreshold>0</discardingThreshold>
    <neverBlock>true</neverBlock>    <!-- queue dolu olsa, imtina et -->
</appender>
```

### Həll 2 — Sampling

Yalnız hər 10-cu və ya 100-cü DEBUG log-u yaz:

```java
public class SamplingLogger {
    private final Logger log;
    private final AtomicLong counter = new AtomicLong();
    private final int sampleRate;  // məsələn, 100

    public void debug(String msg, Object... args) {
        if (counter.incrementAndGet() % sampleRate == 0) {
            log.debug(msg, args);
        }
    }
}
```

### Həll 3 — Filter ilə spam-ı dayandır

```xml
<appender name="FILE" class="ch.qos.logback.core.rolling.RollingFileAppender">
    <filter class="ch.qos.logback.classic.filter.ThresholdFilter">
        <level>INFO</level>   <!-- DEBUG-u bu appender üçün söndür -->
    </filter>
    ...
</appender>
```

### Həll 4 — Bulk logging

```java
// YANLIŞ — hər iterasiyada log
for (User user : users) {
    log.debug("Processing user {}", user.getId());
}

// DOĞRU — cəmi bir mesaj
log.debug("Processing {} users, ids: {}", users.size(), ids);
```

---

## 18. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: Production-da TRACE/DEBUG açıq buraxmaq

Disk dolur, performance düşür. Production-da `INFO`+ kifayətdir.

### Səhv 2: Exception-u `e.getMessage()` ilə loglamaq

```java
log.error("Failed: " + e.getMessage());   // stack trace ITIR
log.error("Failed", e);                    // DOĞRU — tam stack trace
```

### Səhv 3: MDC-ni `clear()` etməmək

Thread pool istifadə edilir — eyni thread başqa request-ə düşəndə köhnə MDC dəyərləri qalır.

```java
try {
    MDC.put("userId", id);
    // ...
} finally {
    MDC.clear();   // MƏCBURI
}
```

### Səhv 4: `logback.xml` yaratmaq, `logback-spring.xml` əvəzinə

Spring-in `<springProfile>` tag-i yalnız `logback-spring.xml`-də işləyir.

### Səhv 5: Fayla log yazmaq, konteynerdə

Docker/Kubernetes-də stdout-a yaz — fayl konteyner silindikdə itir.

### Səhv 6: Sensitiv məlumatı loglamaq

Parol, token, kart nömrəsi log-a düşsə, bütün sistem təhlükədədir.

### Səhv 7: String concat DEBUG-da

```java
log.debug("Data: " + expensiveObject.toString());
// DEBUG söndürülü olsa da, toString() çağırılır!

log.debug("Data: {}", expensiveObject);   // DOĞRU
```

---

## 19. İntervyu Sualları {#intervyu}

**S: SLF4J nədir və niyə vacibdir?**
C: SLF4J — Java üçün logging facade (interface) kitabxanasıdır. Tətbiq kodu SLF4J API-yə yazır; implementation (Logback, Log4j2) dəyişdirmək üçün kod dəyişdirilmir — yalnız dependency dəyişdirilir. Spring Boot SLF4J + Logback default-u istifadə edir.

**S: Log səviyyələri necə sıralanır?**
C: Aşağıdan yuxarıya: TRACE < DEBUG < INFO < WARN < ERROR < OFF. Müəyyən səviyyə seçiləndə, ondan yuxarı olan bütün səviyyələr log olunur. Production-da adətən `INFO`, inkişafda `DEBUG`/`TRACE` istifadə edilir.

**S: Parameterized logging niyə vacibdir?**
C: `log.debug("User " + user + " did X")` ifadəsi hətta DEBUG söndürülü olsa belə `user.toString()` çağırır və string yaradır — performans itkisi. `log.debug("User {} did X", user)` isə əvvəl səviyyəni yoxlayır, aktiv olmasa heç nə formatlaşdırmır. Yüksək load-da fərq nəzərə çarpandır.

**S: MDC nədir və nə üçündür?**
C: MDC (Mapped Diagnostic Context) — hər thread-ə bağlı açar-dəyər map-dir. Request ID, user ID, correlation ID kimi kontekst dəyərlərini log pattern-ə avtomatik daxil etməyə imkan verir. `%X{requestId}` sintaksisi ilə pattern-də görünür. Mikrosaniyə dəqiqliklə bir request-ə aid bütün logları izləmək mümkündür. Thread pool istifadə edildiyindən `MDC.clear()` mütləq çağırılmalıdır.

**S: `logback.xml` vs `logback-spring.xml` fərqi nədir?**
C: `logback.xml` sırf Logback tərəfindən emal edilir, Spring-in xüsusiyyətləri mövcud deyil. `logback-spring.xml` Spring Boot tərəfindən emal edilir — `<springProfile>`, `<springProperty>` tag-ləri, Spring environment-ə giriş mümkündür. Spring Boot layihələrində həmişə `logback-spring.xml` istifadə edin.

**S: `application.properties`-də logging səviyyəsini necə təyin edirsiniz?**
C: `logging.level.<paket>=<LEVEL>`. Məsələn `logging.level.root=INFO`, `logging.level.com.example=DEBUG`, `logging.level.org.hibernate.SQL=DEBUG`. YAML-də hierarxik yazılır. Daha dəqiq paket daha az dəqiqdən üstün gəlir.

**S: Distributed tracing nədir?**
C: Mikroservislərdə bir request bir neçə servisdən keçir; hər biri öz logunu yazır. Distributed tracing — `traceId` (bütün request üçün ortaq) və `spanId` (hər servis üçün unikal) ilə bütün logları əlaqələndirir. Spring Boot 3 Micrometer Tracing istifadə edir, Zipkin/Jaeger/Tempo ilə vizuallaşdırılır.

**S: Production-da log faylı üçün hansı rolling policy tövsiyə olunur?**
C: Size və time əsaslı birləşmə: `SizeAndTimeBasedRollingPolicy`. Hər gün yeni fayl, 10 MB-dan sonra yeni hissə, 30 gündən sonra silmə, ümumi 1 GB-lıq limit, gzip sıxılma. Əsas sənəd: `logging.logback.rollingpolicy.*` property-ləri.

**S: JSON structured logging niyə istifadə olunur?**
C: Plain text log-u parse etmək çətin və kövrəkdir. JSON format hər log-u strukturlaşdırılmış obyektə çevirir — ELK, Loki, CloudWatch avtomatik indeksləyir və sahələr üzrə axtarış/filtrləmə mümkündür. Production mikroservislərində standartdır. `logstash-logback-encoder` ilə asanlıqla tətbiq edilir.

**S: Asinxron log-un üstünlüyü və riski?**
C: Üstünlük — I/O-nu əsas thread-dən ayırır, request latency artmır. Risk — queue dolursa loglar itirilə bilər (`discardingThreshold=0` ilə TRACE/DEBUG-u ilk əvvəl buraxır); tətbiq crash olsa buffer-dəki loglar disk-ə düşmür. Kritik audit loglarını sinxron saxlayın, operational loglar üçün async istifadə edin.

**S: Hansı məlumatı loglamaq OLMAZ?**
C: Parollar, API token, session ID, kredit kartı, SSN, tam medical data, GDPR-altındakı PII. `toString()`-i override edərək həssas sahələri çıxarın. Lombok-da `@ToString(exclude = {"password"})` istifadə edin. Email kimi sahələri maskalayın (`a***@gmail.com`).
