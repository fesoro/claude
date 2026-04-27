# Spring Cloud Nədir və Ekosistem (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
- [Spring Cloud Nədir?](#spring-cloud-nədir)
- [Spring Cloud Ekosistemi](#spring-cloud-ekosistemi)
- [12-Factor App Prinsipləri](#12-factor-app-prinsipləri)
- [Cloud-Native Prinsiplər](#cloud-native-prinsiplər)
- [Spring Cloud vs Kubernetes Native](#spring-cloud-vs-kubernetes-native)
- [Version Uyğunluğu](#version-uyğunluğu)
- [Sadə Layihə Quruluşu](#sadə-layihə-quruluşu)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Spring Cloud Nədir?

Spring Cloud — distributed sistemlər üçün ümumi şablonları (patterns) həyata keçirən alətlər toplusudur. Microservice arxitekturasının tələb etdiyi:
- **Service Discovery** (xidmətlərin bir-birini tapması)
- **Centralized Configuration** (mərkəzi konfiqurasiya)
- **Load Balancing** (yük balanslaşdırma)
- **Circuit Breaking** (dövrə açarı)
- **Distributed Tracing** (paylanmış izləmə)

kimi problemləri həll edir.

### Microservice Problemləri

Monolitik tətbiqdən microservice arxitekturasına keçdikdə aşağıdakı problemlər yaranır:

```
Monolit:
┌─────────────────────────────────┐
│  Order | Payment | Inventory    │
│  User  | Notification | Report  │
└─────────────────────────────────┘
         │
         ↓
Microservices:
┌─────────┐  ┌─────────┐  ┌─────────┐
│  Order  │  │ Payment │  │Inventory│
│ Service │  │ Service │  │ Service │
└─────────┘  └─────────┘  └─────────┘

Problemlər:
- Servislər bir-birini necə tapar? (Discovery)
- Konfiqurasiya necə idarə edilir? (Config)
- Xarici sorğular necə yönləndirilir? (Gateway)
- Xidmət çöküşü sistemi necə əhatə edir? (Resilience)
- Sorğu axını necə izlənir? (Tracing)
```

---

## Spring Cloud Ekosistemi

### 1. Spring Cloud Gateway
Reaktiv API gateway — bütün xarici sorğular burada qəbul edilir.

```java
// application.yml
spring:
  cloud:
    gateway:
      routes:
        - id: order-service
          uri: lb://ORDER-SERVICE    // Eureka-dan yük balanslaşdırma
          predicates:
            - Path=/api/orders/**
          filters:
            - StripPrefix=1
```

### 2. Spring Cloud Netflix Eureka
Service Registry — hər servis buraya qeydiyyatdan keçir.

```java
// Eureka Server
@SpringBootApplication
@EnableEurekaServer
public class DiscoveryServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(DiscoveryServerApplication.class, args);
    }
}

// Eureka Client
@SpringBootApplication
@EnableDiscoveryClient
public class OrderServiceApplication {
    public static void main(String[] args) {
        SpringApplication.run(OrderServiceApplication.class, args);
    }
}
```

### 3. Spring Cloud Config
Mərkəzi konfiqurasiya serveri — bütün servislərin konfiqurasiyasını bir yerdə saxlayır.

```yaml
# Config Server - application.yml
spring:
  cloud:
    config:
      server:
        git:
          uri: https://github.com/myorg/config-repo
          search-paths: '{application}'
```

### 4. Spring Cloud OpenFeign
Declarative HTTP client — REST çağırışlarını interface kimi yazırıq.

```java
// YANLIŞ — manual RestTemplate
@Service
public class OrderService {
    private RestTemplate restTemplate = new RestTemplate();

    public Payment getPayment(Long id) {
        // URL hard-coded, error handling yoxdur
        return restTemplate.getForObject(
            "http://localhost:8082/payments/" + id,
            Payment.class
        );
    }
}

// DOĞRU — OpenFeign ilə
@FeignClient(name = "payment-service")
public interface PaymentClient {
    @GetMapping("/payments/{id}")
    Payment getPayment(@PathVariable Long id);
}
```

### 5. Resilience4j
Circuit Breaker, Retry, Rate Limiter — xidmət dayanıqlığı üçün.

```java
@CircuitBreaker(name = "paymentService", fallbackMethod = "fallbackPayment")
@Retry(name = "paymentService")
public Payment processPayment(Long orderId) {
    return paymentClient.processPayment(orderId);
}

// Fallback metod — əsas servis çöküşündə işə düşür
public Payment fallbackPayment(Long orderId, Exception e) {
    return Payment.pending(orderId); // Gözləmədə cavab
}
```

### 6. Micrometer Tracing (Spring Boot 3.x)
Spring Boot 3-də Sleuth əvəzinə Micrometer Tracing istifadə olunur.

```java
// Trace ID və Span ID avtomatik əlavə edilir
// Log formatı: [app-name,traceId,spanId]
// [order-service,65d3a7f2b1c0e8a9,4f1a2b3c] INFO - Sifariş yaradıldı
```

### 7. Spring Cloud Stream
Message broker abstraction — Kafka/RabbitMQ üzərindən event-driven arxitektura.

```java
@Bean
public Consumer<OrderEvent> processOrder() {
    return orderEvent -> {
        // Kafka/RabbitMQ mesajını emal et
        log.info("Sifariş alındı: {}", orderEvent.getOrderId());
    };
}
```

---

## 12-Factor App Prinsipləri

[12factor.net](https://12factor.net) tərəfindən müəyyən edilmiş cloud-native tətbiq üçün 12 prinsip:

| # | Prinsip | Spring Cloud Həlli |
|---|---------|-------------------|
| 1 | **Codebase** — Bir kod bazası, çoxlu deploy | Git |
| 2 | **Dependencies** — Açıq dependency bəyanatı | Maven/Gradle |
| 3 | **Config** — Konfiqurasiya mühitdə saxla | Spring Cloud Config |
| 4 | **Backing Services** — Xidmətlərə URL ilə bağlan | Environment variables |
| 5 | **Build/Release/Run** — Mərhələləri ayır | CI/CD pipeline |
| 6 | **Processes** — Stateless proseslər | Spring stateless beans |
| 7 | **Port Binding** — Port bağlamaqla xidmət et | Spring Boot embedded server |
| 8 | **Concurrency** — Prosesləri üfüqi genişləndir | Kubernetes scaling |
| 9 | **Disposability** — Sürətli başlatma/dayandırma | Spring Boot fast startup |
| 10 | **Dev/Prod Parity** — Mühitlər eyni olsun | Docker, Spring Profiles |
| 11 | **Logs** — Logları event axını kimi gör | Logback + ELK Stack |
| 12 | **Admin Processes** — Admin tapşırıqlarını bir dəfəlik proses kimi idar et | Spring Batch |

```java
// Faktor 3 — Konfiqurasiya mühitdə (environment variable)
// YANLIŞ — konfiqurasiya kod içindədir
@Service
public class PaymentService {
    private String apiKey = "sk-prod-1234567890"; // Kod içindədir!
    private String dbUrl = "jdbc:mysql://prod-db:3306/payments";
}

// DOĞRU — konfigurasiya xaricdən inject edilir
@Service
public class PaymentService {
    @Value("${payment.api.key}")  // Config Server-dən gəlir
    private String apiKey;

    @Value("${spring.datasource.url}")  // Mühit dəyişəni
    private String dbUrl;
}
```

---

## Cloud-Native Prinsiplər

```
Cloud-Native Xüsusiyyətlər:
┌────────────────────────────────────────────────────┐
│  1. Microservices  — Kiçik, müstəqil xidmətlər    │
│  2. Containers     — Docker ilə paketlənmiş        │
│  3. Orchestration  — Kubernetes ilə idarə          │
│  4. DevOps         — CI/CD avtomatlaşması          │
│  5. Observability  — Metrics, Logs, Traces         │
└────────────────────────────────────────────────────┘
```

### Spring Boot 3.x Native Image Support

```xml
<!-- pom.xml — GraalVM Native Image dəstəyi -->
<plugin>
    <groupId>org.graalvm.buildtools</groupId>
    <artifactId>native-maven-plugin</artifactId>
</plugin>
```

```bash
# Native image yaratma (Startup time: ~50ms, Bellek: ~50MB)
./mvnw native:compile -Pnative

# Adi JAR (Startup time: ~3-5s, Bellek: ~200MB)
./mvnw package
```

---

## Spring Cloud vs Kubernetes Native

| Xüsusiyyət | Spring Cloud | Kubernetes Native |
|------------|-------------|-------------------|
| **Service Discovery** | Eureka | Kubernetes Service + DNS |
| **Load Balancing** | Spring Cloud LoadBalancer | kube-proxy |
| **Config Management** | Spring Cloud Config | ConfigMap + Secret |
| **API Gateway** | Spring Cloud Gateway | Ingress / API Gateway |
| **Circuit Breaker** | Resilience4j | Istio Service Mesh |
| **Tracing** | Micrometer + Zipkin | Jaeger (Istio) |

### Nə zaman hansını seçmək?

```
Spring Cloud seçin:
✅ Java/Spring ekosistemi ilə tam inteqrasiya lazımdır
✅ Kubernetes olmayan mühitdə (VM, bare metal)
✅ Kod səviyyəsində nəzarət lazımdır
✅ Kiçik/orta komanda

Kubernetes Native seçin:
✅ Çoxdilli (polyglot) microservice mühiti
✅ Platform müstəqilliyi tələb olunur
✅ DevOps komandası güclüdür
✅ Service Mesh (Istio) istifadə ediləcək
```

### Hibrid Yanaşma (Tövsiyə olunan)

```java
// Spring Cloud Gateway + Kubernetes Service Discovery
spring:
  cloud:
    gateway:
      discovery:
        locator:
          enabled: true  // Kubernetes service-lərini avtomatik kəşf et
    kubernetes:
      discovery:
        enabled: true    // Kubernetes-dən servis siyahısı al
```

---

## Version Uyğunluğu

Spring Boot 3.x ilə Spring Cloud 2023.x istifadə etmək lazımdır:

| Spring Boot | Spring Cloud | Java |
|-------------|-------------|------|
| 3.3.x | 2023.0.x (Leyton) | 17+ |
| 3.2.x | 2023.0.x (Leyton) | 17+ |
| 3.1.x | 2022.0.x (Kilburn) | 17+ |
| 2.7.x | 2021.0.x (Jubilee) | 11+ |

```xml
<!-- pom.xml — Spring Boot 3.x + Spring Cloud 2023.x -->
<parent>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-parent</artifactId>
    <version>3.3.0</version>
</parent>

<properties>
    <spring-cloud.version>2023.0.1</spring-cloud.version>
    <java.version>21</java.version>
</properties>

<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>org.springframework.cloud</groupId>
            <artifactId>spring-cloud-dependencies</artifactId>
            <version>${spring-cloud.version}</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>
    </dependencies>
</dependencyManagement>

<dependencies>
    <!-- Gateway -->
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-gateway</artifactId>
    </dependency>

    <!-- Eureka Client -->
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-netflix-eureka-client</artifactId>
    </dependency>

    <!-- Config Client -->
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-config</artifactId>
    </dependency>

    <!-- OpenFeign -->
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-openfeign</artifactId>
    </dependency>

    <!-- Resilience4j -->
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-circuitbreaker-resilience4j</artifactId>
    </dependency>

    <!-- Micrometer Tracing + Zipkin -->
    <dependency>
        <groupId>io.micrometer</groupId>
        <artifactId>micrometer-tracing-bridge-brave</artifactId>
    </dependency>
    <dependency>
        <groupId>io.zipkin.reporter2</groupId>
        <artifactId>zipkin-reporter-brave</artifactId>
    </dependency>
</dependencies>
```

---

## Sadə Layihə Quruluşu

```
microservice-demo/
├── discovery-server/          # Eureka Server
│   └── src/main/java/
│       └── DiscoveryServerApplication.java
│
├── config-server/             # Spring Cloud Config
│   └── src/main/java/
│       └── ConfigServerApplication.java
│
├── api-gateway/               # Spring Cloud Gateway
│   └── src/main/java/
│       └── ApiGatewayApplication.java
│
├── order-service/             # Business Service 1
│   └── src/main/java/
│       ├── OrderServiceApplication.java
│       └── client/PaymentClient.java  # OpenFeign
│
├── payment-service/           # Business Service 2
│   └── src/main/java/
│       └── PaymentServiceApplication.java
│
└── config-repo/               # Git: Konfiqurasiya faylları
    ├── application.yml        # Ümumi konfiq
    ├── order-service.yml      # Order servisi konfiq
    └── payment-service.yml    # Payment servisi konfiq
```

### Başlatma Ardıcıllığı

```
1. Config Server    (8888 port)  — İlk başlar
2. Eureka Server    (8761 port)  — İkinci başlar
3. API Gateway      (8080 port)  — Config + Eureka-ya qoşulur
4. Order Service    (8081 port)  — Config + Eureka-ya qoşulur
5. Payment Service  (8082 port)  — Config + Eureka-ya qoşulur
```

```yaml
# order-service — bootstrap.yml (Spring Cloud Config üçün)
spring:
  application:
    name: order-service
  config:
    import: optional:configserver:http://localhost:8888
  cloud:
    config:
      fail-fast: true          # Config server olmasa başlama
      retry:
        max-attempts: 6
        initial-interval: 1000
```

---

## İntervyu Sualları

**S: Spring Cloud ilə Kubernetes-in fərqi nədir?**
C: Spring Cloud kod səviyyəsinde (Java librarylar ilə) həllər təqdim edir. Kubernetes isə infrastruktur səviyyəsində (container orchestration) həllər verir. İkisi bir-birini əvəz deyil, tamamlayır. Məsələn, Resilience4j (kod) + Kubernetes HPA (infrastruktur) birlikdə işləyə bilər.

**S: 12-Factor App nədir?**
C: Heroku tərəfindən hazırlanmış, cloud-native tətbiqlər üçün 12 prinsip toplusudur. Ən vacibləri: konfiqurasiyaı mühitdə saxla (faktor 3), stateless proseslər (faktor 6), logları event axını kimi gör (faktor 11).

**S: Spring Cloud 2023.x hansı Spring Boot versiyası ilə işləyir?**
C: Spring Cloud 2023.0.x (kod adı: Leyton) Spring Boot 3.1.x və 3.2.x/3.3.x ilə uyğundur. Java 17 minimum tələbdir.

**S: Spring Cloud Sleuth nəyə görə aradan çıxdı?**
C: Spring Boot 3.x-dən etibarən Sleuth Micrometer Tracing ilə əvəz olundu. Micrometer Tracing daha geniş vendor dəstəyi (Brave, OpenTelemetry) təqdim edir və Micrometer metrics abstraction ilə inteqre olub.

**S: Microservice arxitekturasında hansı əsas problemlər yaranır?**
C: Service discovery (xidmətlər bir-birini tapmır), distributed transactions (ACID yoxdur), network latency (şəbəkə gecikmə), distributed tracing (sorğuyu izləmək çətin), configuration management (hər servisin öz konfiqurasyonu), cascade failures (bir servis çökdükdə digərlərini əhatə edir).
