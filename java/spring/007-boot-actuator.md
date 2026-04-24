# 007 — Spring Boot Actuator
**Səviyyə:** Orta


## Mündəricat
1. [Actuator nədir?](#nedir)
2. [Quraşdırma](#qurashma)
3. [Daxili endpoint-lər](#endpoints)
4. [Endpoint exposure — hansını açmaq?](#exposure)
5. [Custom Endpoint — @Endpoint](#custom-endpoint)
6. [HealthIndicator — öz sağlamlıq yoxlaması](#health)
7. [InfoContributor — /info endpoint-inə məlumat əlavə et](#info)
8. [Metrics — Micrometer inteqrasiyası](#metrics)
9. [Təhlükəsizlik](#security)
10. [İntervyu Sualları](#intervyu)

---

## 1. Actuator nədir? {#nedir}

**Spring Boot Actuator** — tətbiqin sağlamlığını, metrikasını, konfiqurasiyasını
və daxili vəziyyətini izləmək üçün HTTP (və JMX) endpoint-ləri təqdim edir.

```xml
<!-- pom.xml — bir asılılıq kifayətdir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>
```

---

## 2. Quraşdırma {#qurashma}

```properties
# application.properties

# Bütün endpoint-ləri HTTP üzərindən açıq et
management.endpoints.web.exposure.include=*

# Yalnız lazımi endpoint-ləri aç (tövsiyə olunur):
management.endpoints.web.exposure.include=health,info,metrics,loggers

# Aktuator üçün ayrıca port (əsas tətbiqtən ayır — təhlükəsizlik üçün)
management.server.port=8081

# Endpoint-lərin bazə path-i
management.endpoints.web.base-path=/actuator

# Sağlamlıq detalları
management.endpoint.health.show-details=always
# always | never | when-authorized
```

---

## 3. Daxili endpoint-lər {#endpoints}

### /health — sağlamlıq yoxlaması

```
GET /actuator/health

{
  "status": "UP",
  "components": {
    "db": {
      "status": "UP",
      "details": {
        "database": "PostgreSQL",
        "validationQuery": "isValid()"
      }
    },
    "diskSpace": {
      "status": "UP",
      "details": {
        "total": 499963174912,
        "free": 152688025600,
        "threshold": 10485760
      }
    },
    "redis": {
      "status": "UP",
      "details": {
        "version": "7.0.5"
      }
    }
  }
}
```

### /info — tətbiq məlumatları

```properties
# application.properties-ə məlumat əlavə et:
info.app.name=My Spring Boot App
info.app.version=1.0.0
info.app.description=Spring Boot nümunə tətbiqi

# Build məlumatları (Maven plugin ilə avtomatik):
spring.info.build.location=classpath:META-INF/build-info.properties
```

### /metrics — ölçümlər

```
GET /actuator/metrics
→ mövcud metrika adlarının siyahısı

GET /actuator/metrics/jvm.memory.used
→ JVM yaddaş istifadəsi

GET /actuator/metrics/http.server.requests
→ HTTP sorğu statistikaları

GET /actuator/metrics/http.server.requests?tag=uri:/api/users&tag=status:200
→ Filtrlənmiş metrika
```

### /env — mühit dəyişənləri

```
GET /actuator/env
→ bütün property mənbələri və dəyərləri

GET /actuator/env/server.port
→ yalnız bir property
```

### /beans — Spring bean-larının siyahısı

```
GET /actuator/beans
→ bütün yaradılmış bean-lar, asılılıqları, mənbələri
```

### /mappings — HTTP endpoint xəritəsi

```
GET /actuator/mappings
→ bütün @RequestMapping, @GetMapping və s. endpoint-lər
```

### /loggers — log səviyyəsini dəyiş

```
GET /actuator/loggers/com.example
→ cari log səviyyəsi

POST /actuator/loggers/com.example
Content-Type: application/json
{"configuredLevel": "DEBUG"}
→ runtime-da log səviyyəsini dəyişdir (restart lazım deyil!)
```

### /threaddump — thread vəziyyəti

```
GET /actuator/threaddump
→ bütün thread-lərin vəziyyəti (RUNNABLE, WAITING, BLOCKED...)
→ deadlock aşkarlamaq üçün faydalıdır
```

### /heapdump — JVM heap snapshot

```
GET /actuator/heapdump
→ heap dump faylını yükləyir (VisualVM ilə analiz et)
→ memory leak aşkarlamaq üçün
```

### /conditions — condition qiymətləndirmə hesabatı

```
GET /actuator/conditions
→ hansı auto-config-lərin niyə aktiv/deaktiv olduğu
→ --debug flag-ı ilə eyni məlumat
```

---

## 4. Endpoint exposure — hansını açmaq? {#exposure}

```properties
# YANLIŞ — istehsalda bütün endpoint-ləri açmaq təhlükəlidir:
management.endpoints.web.exposure.include=*

# DOĞRU — yalnız lazımi endpoint-ləri aç:
management.endpoints.web.exposure.include=health,info,metrics

# Müəyyən endpoint-ləri bağla:
management.endpoints.web.exposure.exclude=env,beans,heapdump

# Profil əsaslı konfiqurasiya:
# application-dev.properties:
management.endpoints.web.exposure.include=*
# application-prod.properties:
management.endpoints.web.exposure.include=health,info,metrics
```

### Endpoint-ləri tamamilə deaktiv et:

```properties
# Müəyyən endpoint-i deaktiv et:
management.endpoint.heapdump.enabled=false
management.endpoint.shutdown.enabled=false  # tətbiqi HTTP ilə söndürür — XƏTA!

# Bütün endpoint-ləri deaktiv et, sonra lazımiləri aç:
management.endpoints.enabled-by-default=false
management.endpoint.health.enabled=true
management.endpoint.info.enabled=true
```

---

## 5. Custom Endpoint — @Endpoint {#custom-endpoint}

```java
@Component
@Endpoint(id = "cache-stats")  // URL: /actuator/cache-stats
public class CacheStatsEndpoint {

    private final CacheManager cacheManager;

    public CacheStatsEndpoint(CacheManager cacheManager) {
        this.cacheManager = cacheManager;
    }

    // GET /actuator/cache-stats
    @ReadOperation
    public Map<String, Object> cacheStats() {
        Map<String, Object> stats = new LinkedHashMap<>();

        cacheManager.getCacheNames().forEach(cacheName -> {
            Cache cache = cacheManager.getCache(cacheName);
            Map<String, Object> cacheInfo = new HashMap<>();

            if (cache instanceof CaffeineCacheManager) {
                // Caffeine cache statistikaları əldə et
                cacheInfo.put("type", "caffeine");
            }

            stats.put(cacheName, cacheInfo);
        });

        return stats;
    }

    // GET /actuator/cache-stats/{cacheName}
    @ReadOperation
    public Map<String, Object> cacheStatsByName(@Selector String cacheName) {
        Map<String, Object> info = new HashMap<>();
        Cache cache = cacheManager.getCache(cacheName);

        if (cache == null) {
            info.put("error", "Cache tapılmadı: " + cacheName);
            return info;
        }

        info.put("name", cacheName);
        info.put("exists", true);
        return info;
    }

    // DELETE /actuator/cache-stats/{cacheName}
    @DeleteOperation
    public Map<String, String> clearCache(@Selector String cacheName) {
        Cache cache = cacheManager.getCache(cacheName);
        if (cache != null) {
            cache.clear();  // cache-i təmizlə
            return Map.of("status", "cleared", "cache", cacheName);
        }
        return Map.of("status", "not-found", "cache", cacheName);
    }

    // POST /actuator/cache-stats
    @WriteOperation
    public Map<String, String> clearAllCaches() {
        cacheManager.getCacheNames().forEach(name -> {
            Cache cache = cacheManager.getCache(name);
            if (cache != null) cache.clear();
        });
        return Map.of("status", "all-caches-cleared");
    }
}
```

### Yalnız Web endpoint-i:

```java
@Component
@WebEndpoint(id = "app-status")  // yalnız HTTP, JMX yox
public class AppStatusWebEndpoint {

    @ReadOperation
    @Produces(MediaType.APPLICATION_JSON_VALUE)
    public WebEndpointResponse<Map<String, Object>> appStatus() {
        Map<String, Object> status = new HashMap<>();
        status.put("version", "1.0.0");
        status.put("uptime", ManagementFactory.getRuntimeMXBean().getUptime());
        status.put("environment", System.getenv("APP_ENV"));

        return new WebEndpointResponse<>(status, 200);
    }
}
```

---

## 6. HealthIndicator — öz sağlamlıq yoxlaması {#health}

```java
// Xarici API sağlamlıq yoxlaması
@Component
public class ExternalApiHealthIndicator implements HealthIndicator {

    private final RestTemplate restTemplate;
    private final String apiUrl;

    public ExternalApiHealthIndicator(RestTemplate restTemplate,
                                      @Value("${external.api.url}") String apiUrl) {
        this.restTemplate = restTemplate;
        this.apiUrl = apiUrl;
    }

    @Override
    public Health health() {
        try {
            // Xarici API-yə ping at
            ResponseEntity<String> response = restTemplate
                .getForEntity(apiUrl + "/health", String.class);

            if (response.getStatusCode().is2xxSuccessful()) {
                return Health.up()
                    .withDetail("api-url", apiUrl)
                    .withDetail("response-time", "fast")
                    .withDetail("status-code", response.getStatusCodeValue())
                    .build();
            } else {
                return Health.down()
                    .withDetail("api-url", apiUrl)
                    .withDetail("status-code", response.getStatusCodeValue())
                    .withDetail("reason", "Uğursuz status kodu")
                    .build();
            }
        } catch (Exception e) {
            return Health.down()
                .withDetail("api-url", apiUrl)
                .withDetail("error", e.getMessage())
                .withException(e)
                .build();
        }
    }
}
```

### ReactiveHealthIndicator (WebFlux üçün):

```java
@Component
public class DatabaseReactiveHealthIndicator implements ReactiveHealthIndicator {

    private final R2dbcEntityTemplate template;

    public DatabaseReactiveHealthIndicator(R2dbcEntityTemplate template) {
        this.template = template;
    }

    @Override
    public Mono<Health> health() {
        return template
            .getDatabaseClient()
            .sql("SELECT 1")
            .fetch()
            .one()
            .map(result -> Health.up()
                .withDetail("database", "reactive-postgres")
                .build())
            .onErrorResume(e -> Mono.just(
                Health.down()
                    .withDetail("error", e.getMessage())
                    .build()
            ));
    }
}
```

### Qrup health indicators:

```properties
# Liveness probe — tətbiq işləyirmi?
management.endpoint.health.group.liveness.include=livenessState,diskSpace

# Readiness probe — trafik qəbul etməyə hazırdırmı?
management.endpoint.health.group.readiness.include=readinessState,db,redis

# Kubernetes üçün:
# GET /actuator/health/liveness
# GET /actuator/health/readiness
```

---

## 7. InfoContributor — /info endpoint-inə məlumat əlavə et {#info}

```java
@Component
public class AppInfoContributor implements InfoContributor {

    private final DataSource dataSource;

    public AppInfoContributor(DataSource dataSource) {
        this.dataSource = dataSource;
    }

    @Override
    public void contribute(Info.Builder builder) {
        // Tətbiq məlumatları əlavə et
        builder.withDetail("application", Map.of(
            "name", "My Spring Boot App",
            "version", "2.1.0",
            "description", "Spring Boot nümunə tətbiqi",
            "contact", "dev-team@example.com"
        ));

        // Verilənlər bazası məlumatı əlavə et
        try (Connection conn = dataSource.getConnection()) {
            DatabaseMetaData meta = conn.getMetaData();
            builder.withDetail("database", Map.of(
                "product", meta.getDatabaseProductName(),
                "version", meta.getDatabaseProductVersion(),
                "url", meta.getURL()
            ));
        } catch (SQLException e) {
            builder.withDetail("database", Map.of("error", e.getMessage()));
        }

        // Sistem məlumatı
        Runtime runtime = Runtime.getRuntime();
        builder.withDetail("jvm", Map.of(
            "version", System.getProperty("java.version"),
            "vendor", System.getProperty("java.vendor"),
            "max-memory-mb", runtime.maxMemory() / 1024 / 1024,
            "processors", runtime.availableProcessors()
        ));
    }
}
```

---

## 8. Custom Metrics — Micrometer {#metrics}

```java
@Service
public class OrderService {

    // Micrometer metric-ləri
    private final Counter orderCreatedCounter;
    private final Counter orderFailedCounter;
    private final Timer orderProcessingTimer;
    private final AtomicLong activeOrdersGauge;

    public OrderService(MeterRegistry meterRegistry) {
        // Sayğac — neçə sifariş yaradıldı
        this.orderCreatedCounter = Counter.builder("orders.created")
            .description("Yaradılan sifarişlərin sayı")
            .tag("app", "my-store")
            .register(meterRegistry);

        // Uğursuz sayğac
        this.orderFailedCounter = Counter.builder("orders.failed")
            .description("Uğursuz sifarişlərin sayı")
            .register(meterRegistry);

        // Vaxt ölçücü — sifariş emalının vaxtı
        this.orderProcessingTimer = Timer.builder("orders.processing.time")
            .description("Sifariş emalı müddəti")
            .register(meterRegistry);

        // Anlıq dəyər — aktiv sifariş sayı
        this.activeOrdersGauge = new AtomicLong(0);
        Gauge.builder("orders.active", activeOrdersGauge, AtomicLong::get)
            .description("Cari aktiv sifarişlər")
            .register(meterRegistry);
    }

    public Order createOrder(OrderRequest request) {
        activeOrdersGauge.incrementAndGet(); // aktiv sayı artır

        return orderProcessingTimer.record(() -> {
            try {
                Order order = processOrder(request);
                orderCreatedCounter.increment();  // uğurlu sayğacı artır
                return order;
            } catch (Exception e) {
                orderFailedCounter.increment();   // uğursuz sayğacı artır
                throw e;
            } finally {
                activeOrdersGauge.decrementAndGet(); // aktiv sayı azalt
            }
        });
    }

    private Order processOrder(OrderRequest request) {
        // sifariş emalı məntiq
        return new Order();
    }
}
```

---

## 9. Təhlükəsizlik {#security}

```java
@Configuration
public class ActuatorSecurityConfig {

    @Bean
    public SecurityFilterChain actuatorSecurityFilterChain(HttpSecurity http) throws Exception {
        http
            .securityMatcher("/actuator/**")
            .authorizeHttpRequests(auth -> auth
                // /health və /info hər kəsə açıq
                .requestMatchers("/actuator/health", "/actuator/info").permitAll()
                // Digərləri yalnız ADMIN rolu ilə
                .requestMatchers("/actuator/**").hasRole("ADMIN")
            )
            .httpBasic(Customizer.withDefaults());

        return http.build();
    }
}
```

```properties
# İstehsalda aktuatoru ayrıca portda saxla
management.server.port=8081

# IP ünvan məhdudiyyəti (yalnız daxili şəbəkə)
management.server.address=127.0.0.1
```

---

## İntervyu Sualları {#intervyu}

**S: Spring Boot Actuator nədir?**
C: Tətbiqin sağlamlığını, metrikasını, konfiqurasiyasını HTTP/JMX endpoint-ləri vasitəsilə izləmək üçün Spring Boot modulu. `/health`, `/metrics`, `/info` kimi endpoint-lər təqdim edir.

**S: /health endpoint-inin show-details seçimlərini izah edin.**
C: `never` — yalnız UP/DOWN statusu; `always` — bütün komponent detalları; `when-authorized` — yalnız autentifikasiya olunmuş istifadəçilərə detallar göstər. İstehsalda `when-authorized` tövsiyə olunur.

**S: Custom HealthIndicator necə yaradılır?**
C: `HealthIndicator` interfeysini implement edərək `health()` metodunu override etmək. `Health.up()` və ya `Health.down()` ilə `withDetail()` əlavə edərək `build()` çağırılır.

**S: Runtime-da log səviyyəsini necə dəyişmək olar?**
C: `POST /actuator/loggers/{logger-name}` endpoint-inə `{"configuredLevel": "DEBUG"}` göndərmək. Tətbiqi restart etmədən ani effekt verir.

**S: Actuator endpoint-lərini istehsalda necə qorumaq lazımdır?**
C: Ayrıca management port istifadə etmək (`management.server.port=8081`), Spring Security ilə `ADMIN` rolu tələb etmək, `/health` və `/info`-nu ictimai saxlamaq, `/env`, `/heapdump`, `/threaddump`-ı bağlamaq.

**S: @Endpoint annotasiyası ilə @WebEndpoint arasındakı fərq nədir?**
C: `@Endpoint` həm HTTP, həm JMX üçün; `@WebEndpoint` yalnız HTTP üçün. `@JmxEndpoint` yalnız JMX üçün.
