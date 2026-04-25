# 03 — Spring Cloud Eureka

> **Seviyye:** Expert ⭐⭐⭐⭐


## Mündəricat
- [Eureka Nədir?](#eureka-nədir)
- [Eureka Server](#eureka-server)
- [Eureka Client](#eureka-client)
- [Self-Registration və Heartbeat](#self-registration-və-heartbeat)
- [InstanceInfo](#instanceinfo)
- [Zone-Aware Load Balancing](#zone-aware-load-balancing)
- [Peer-to-Peer Replikasiya](#peer-to-peer-replikasiya)
- [Health Check İnteqrasiyası](#health-check-i̇nteqrasiyası)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Eureka Nədir?

Eureka — Netflix tərəfindən hazırlanmış, Spring Cloud tərəfindən inteqre edilmiş **service registry** sistemidir.

```
Eureka olmadan — Statik URL
┌──────────────┐        ┌──────────────────┐
│ Order Service│───────▶│ http://pay:8082  │  ← Hard-coded URL
└──────────────┘        └──────────────────┘
   Problemlər: IP/port dəyişərsə, scale-up olunsa — xəta!

Eureka ilə — Dinamik Service Discovery
┌──────────────┐   1.Qeydiyyat   ┌──────────────────┐
│ Pay Service  │───────────────▶ │   Eureka Server  │
└──────────────┘                 │   (Registry)     │
                                 └──────────────────┘
┌──────────────┐   2.Axtarış            ▲
│ Order Service│───────────────────────▶│
└──────────────┘   3.Pay URL alır       │
                   4.Birbaşa çağırır ───┘
```

**Eureka əsas komponentlər:**
- **Eureka Server** — Servis reyestri (registry)
- **Eureka Client** — Öz-özünü qeydiyyatdan keçirən servis
- **Service Instance** — Qeydiyyatdakı hər servis nümunəsi

---

## Eureka Server

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-netflix-eureka-server</artifactId>
</dependency>
```

```java
@SpringBootApplication
@EnableEurekaServer    // Eureka Server aktivləşdirmə annotasiyası
public class DiscoveryServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(DiscoveryServerApplication.class, args);
    }
}
```

```yaml
# application.yml — Eureka Server konfiqurasiyası
server:
  port: 8761

spring:
  application:
    name: eureka-server

eureka:
  instance:
    hostname: localhost
  client:
    # Server özü Eureka client deyil — özünü qeydiyyatdan keçirmir
    registerWithEureka: false
    fetchRegistry: false
    serviceUrl:
      defaultZone: http://${eureka.instance.hostname}:${server.port}/eureka/

  server:
    # Gözləmə dövrü — prod-da söndürülməsin!
    enable-self-preservation: true
    # Self-preservation threshold (default 85%)
    renewal-percent-threshold: 0.85
    # Eviction interval — istifadəsiz instance-ları nə qədər tez-tez sil (ms)
    eviction-interval-timer-in-ms: 60000
```

### Eureka Dashboard

```
http://localhost:8761
```

Eureka Server standart olaraq web UI təqdim edir. Orada görürsünüz:
- Qeydiyyatdakı servis instance-ları
- Status (UP/DOWN/OUT_OF_SERVICE)
- Metadata (IP, port, health URL)
- Renewal threshold

### Self-Preservation Mode

```
⚠️ Self-Preservation nədir?

Eureka server müəyyən müddət ərzində heartbeat sayı gözlənilən
həddən aşağı düşərsə — "şəbəkə bölünməsi" fərz edir və
bütün qeydiyyatları silmir.

YANLIŞ iş rejimi:
- Self-preservation: OFF → Servis donursa (network issue), Eureka onu silir
- Yeni sorğular olmayan servisə getdikcə xəta!

DOĞRU iş rejimi:
- Self-preservation: ON (default) → Şəbəkə problemi olsa belə,
  köhnə qeydiyyatlar saxlanılır
```

---

## Eureka Client

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-netflix-eureka-client</artifactId>
</dependency>
```

```java
@SpringBootApplication
@EnableDiscoveryClient   // Eureka kəşf müştərisi aktivləşdirmə
public class OrderServiceApplication {
    public static void main(String[] args) {
        SpringApplication.run(OrderServiceApplication.class, args);
    }
}
```

```yaml
# application.yml — Eureka Client konfiqurasiyası
server:
  port: 8081

spring:
  application:
    name: order-service    # Eureka-dakı servis adı (BÖYÜKHərfə çevrilir)

eureka:
  client:
    serviceUrl:
      defaultZone: http://localhost:8761/eureka/
    # Eureka-dan registry cache yenilənmə intervalı (saniyə)
    registry-fetch-interval-seconds: 30
    # Eureka-ya qeydiyyat aktivdir (default true)
    register-with-eureka: true
    # Digər servisləri kəşf et (default true)
    fetch-registry: true

  instance:
    # Heartbeat göndərmə intervalı (default 30 saniyə)
    lease-renewal-interval-in-seconds: 30
    # Bu qədər heartbeat olmasa — silinir (default 90 saniyə)
    lease-expiration-duration-in-seconds: 90
    # IP adresi istifadə et (hostname əvəzinə — container mühitlərində lazım)
    prefer-ip-address: true
    # Status page URL
    status-page-url-path: /actuator/info
    # Health check URL
    health-check-url-path: /actuator/health
    # Instance ID — unikal (eyni servisin çoxlu instance-ı üçün)
    instance-id: ${spring.application.name}:${server.port}:${random.value}
```

### DiscoveryClient ilə Servis Axtarışı

```java
@Service
@Slf4j
public class ServiceDiscoveryService {

    private final DiscoveryClient discoveryClient;

    public ServiceDiscoveryService(DiscoveryClient discoveryClient) {
        this.discoveryClient = discoveryClient;
    }

    // Servis instance-larını əldə et
    public List<ServiceInstance> getOrderServiceInstances() {
        List<ServiceInstance> instances = discoveryClient.getInstances("ORDER-SERVICE");
        instances.forEach(instance ->
            log.info("Instance tapıldı: {}:{}", instance.getHost(), instance.getPort())
        );
        return instances;
    }

    // Bütün qeydiyyatdakı xidmətlər
    public List<String> getAllServices() {
        return discoveryClient.getServices();
    }
}
```

### LoadBalancerClient

```java
@Service
public class PaymentService {

    private final LoadBalancerClient loadBalancerClient;
    private final RestTemplate restTemplate;

    public PaymentService(LoadBalancerClient loadBalancerClient,
                          RestTemplate restTemplate) {
        this.loadBalancerClient = loadBalancerClient;
        this.restTemplate = restTemplate;
    }

    public Payment getPayment(Long id) {
        // Eureka-dan servis instance-ı seç (Round Robin)
        ServiceInstance instance = loadBalancerClient.choose("PAYMENT-SERVICE");

        if (instance == null) {
            throw new ServiceUnavailableException("Payment servisi əlçatmaz");
        }

        String url = instance.getUri() + "/payments/" + id;
        return restTemplate.getForObject(url, Payment.class);
    }
}

// RestTemplate-ə load balancer əlavə etmə (daha asan yol)
@Configuration
public class RestTemplateConfig {

    @Bean
    @LoadBalanced    // Bu annotasiya — lb://SERVICE-NAME işlədilməsini aktivləşdirir
    public RestTemplate restTemplate() {
        return new RestTemplate();
    }
}

@Service
public class PaymentServiceSimpler {
    private final RestTemplate restTemplate;

    public Payment getPayment(Long id) {
        // lb:// — avtomatik load balancing
        return restTemplate.getForObject(
            "http://PAYMENT-SERVICE/payments/" + id,
            Payment.class
        );
    }
}
```

---

## Self-Registration və Heartbeat

```
Eureka Qeydiyyat Axışı:
                    ┌─────────────────────────────┐
                    │        Eureka Server         │
                    │  ┌───────────────────────┐  │
                    │  │     Registry           │  │
                    │  │  ORDER-SERVICE:        │  │
                    │  │    - host:8081 → UP    │  │
                    │  │  PAYMENT-SERVICE:      │  │
                    │  │    - host:8082 → UP    │  │
                    │  └───────────────────────┘  │
                    └─────────────────────────────┘
                           ▲              ▲
              1. Register  │              │ 1. Register
              2. Heartbeat │              │ 2. Heartbeat
              (30s)        │              │ (30s)
                    ┌──────┘              └──────┐
                    │                            │
             ┌──────────────┐          ┌──────────────┐
             │ Order Service│          │ Pay Service  │
             │   :8081      │          │   :8082      │
             └──────────────┘          └──────────────┘

Eviction prosesi:
- Heartbeat 90 saniyə gəlməsə → instance "expired" sayılır
- Eviction timer (60s) işə düşəndə → expired instance silinir
```

### Heartbeat Parametrləri

| Parametr | Default | Açıqlama |
|---------|---------|----------|
| `lease-renewal-interval-in-seconds` | 30 | Heartbeat göndərmə intervalı |
| `lease-expiration-duration-in-seconds` | 90 | Bu qədər heartbeat olmasa — timeout |
| `eviction-interval-timer-in-ms` | 60000 | Expired instance-ları silmə intervalı |
| `registry-fetch-interval-seconds` | 30 | Client-in registry cache yeniləmə intervalı |

```yaml
# Development mühiti — daha sürətli kəşf
eureka:
  instance:
    lease-renewal-interval-in-seconds: 5    # Dev-də daha sürətli
    lease-expiration-duration-in-seconds: 15
  client:
    registry-fetch-interval-seconds: 5
  server:
    eviction-interval-timer-in-ms: 5000

# Production mühiti — default dəyərlər tövsiyə olunur
eureka:
  instance:
    lease-renewal-interval-in-seconds: 30
    lease-expiration-duration-in-seconds: 90
```

---

## InstanceInfo

InstanceInfo — Eureka-dakı hər servis instance-ının metadata-sıdır.

```java
@Service
public class InstanceMetadataService {

    private final ApplicationInfoManager applicationInfoManager;
    private final EurekaClient eurekaClient;

    public void printInstanceInfo() {
        InstanceInfo myInfo = applicationInfoManager.getInfo();

        // Instance məlumatları
        log.info("App adı: {}", myInfo.getAppName());
        log.info("Instance ID: {}", myInfo.getInstanceId());
        log.info("Host: {}", myInfo.getHostName());
        log.info("IP: {}", myInfo.getIPAddr());
        log.info("Port: {}", myInfo.getPort());
        log.info("Status: {}", myInfo.getStatus());
        log.info("Health URL: {}", myInfo.getHealthCheckUrl());

        // Metadata
        Map<String, String> metadata = myInfo.getMetadata();
        log.info("Metadata: {}", metadata);
    }

    // Başqa servisin instance məlumatlarını al
    public InstanceInfo getPaymentServiceInfo() {
        Application app = eurekaClient.getApplication("PAYMENT-SERVICE");
        return app.getInstances().get(0); // İlk instance
    }
}
```

### Custom Metadata

```yaml
eureka:
  instance:
    metadata-map:
      version: "1.2.0"
      environment: "production"
      region: "az-east"
      team: "payments"
```

```java
// Client-dən metadata oxu
ServiceInstance instance = discoveryClient.getInstances("PAYMENT-SERVICE").get(0);
String version = instance.getMetadata().get("version");
String env = instance.getMetadata().get("environment");
```

---

## Zone-Aware Load Balancing

Zone-Aware — eyni zona/data center-dəki servislərə üstünlük ver.

```yaml
# Order Service — az-east zona
eureka:
  instance:
    metadata-map:
      zone: az-east

# Payment Service instance 1 — az-east zona
eureka:
  instance:
    metadata-map:
      zone: az-east

# Payment Service instance 2 — az-west zona
eureka:
  instance:
    metadata-map:
      zone: az-west
```

```yaml
# Load balancer konfiqurasiyası — zona üstünlüyü
spring:
  cloud:
    loadbalancer:
      zone: az-east    # Bu servis az-east zonasındadır
      # Əvvəl az-east-dəki instance-ları seç, yalnız olmadıqda digərini
```

---

## Peer-to-Peer Replikasiya

Production mühitdə Eureka Server tek nöqtə çöküşü (SPOF) olmaması üçün cluster qurulur.

```yaml
# eureka-server-1 (application-peer1.yml)
server:
  port: 8761
spring:
  application:
    name: eureka-server
eureka:
  instance:
    hostname: eureka-peer1
  client:
    register-with-eureka: true    # Digər peer-ə qeydiyyat
    fetch-registry: true
    serviceUrl:
      defaultZone: http://eureka-peer2:8762/eureka/,http://eureka-peer3:8763/eureka/

---
# eureka-server-2 (application-peer2.yml)
server:
  port: 8762
spring:
  application:
    name: eureka-server
eureka:
  instance:
    hostname: eureka-peer2
  client:
    register-with-eureka: true
    fetch-registry: true
    serviceUrl:
      defaultZone: http://eureka-peer1:8761/eureka/,http://eureka-peer3:8763/eureka/
```

```yaml
# Client — bütün peer-ləri göstər
eureka:
  client:
    serviceUrl:
      defaultZone: >
        http://eureka-peer1:8761/eureka/,
        http://eureka-peer2:8762/eureka/,
        http://eureka-peer3:8763/eureka/
```

```
Peer Replikasiya:
┌──────────────┐    sync    ┌──────────────┐
│  Eureka #1   │◄──────────▶│  Eureka #2   │
│  :8761       │            │  :8762       │
└──────────────┘            └──────────────┘
       ▲                           ▲
       │          sync             │
       └──────────────────────────┘
              ┌──────────────┐
              │  Eureka #3   │
              │  :8763       │
              └──────────────┘

İstənilən peer-ə qeydiyyat → bütün peer-lər öyrənir
```

---

## Health Check İnteqrasiyası

```xml
<!-- Health check üçün Actuator lazımdır -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>
```

```yaml
# application.yml
management:
  endpoints:
    web:
      exposure:
        include: health, info, metrics
  endpoint:
    health:
      show-details: always

eureka:
  instance:
    health-check-url-path: /actuator/health
    status-page-url-path: /actuator/info

  client:
    # Eureka-ya health status-u actuator-dan al
    healthcheck:
      enabled: true    # Bu aktivləşdirildikdə actuator/health-ə baxır
```

### Custom Health Indicator

```java
@Component
public class DatabaseHealthIndicator implements HealthIndicator {

    private final DataSource dataSource;

    public DatabaseHealthIndicator(DataSource dataSource) {
        this.dataSource = dataSource;
    }

    @Override
    public Health health() {
        try (Connection conn = dataSource.getConnection()) {
            if (conn.isValid(1)) {
                return Health.up()
                    .withDetail("db", "Verilənlər bazası əlçatan")
                    .withDetail("url", conn.getMetaData().getURL())
                    .build();
            }
        } catch (SQLException e) {
            return Health.down()
                .withDetail("error", e.getMessage())
                .build();
        }
        return Health.down().build();
    }
}
```

```java
// Eureka instance status-u proqram vasitəsilə dəyişdirmə
@Service
public class ServiceStatusManager {

    private final ApplicationInfoManager applicationInfoManager;

    // Servisi müvəqqəti olaraq xidmətdən çıxar (maintenance)
    public void takeOutOfService() {
        applicationInfoManager.setInstanceStatus(InstanceInfo.InstanceStatus.OUT_OF_SERVICE);
        log.info("Servis xidmətdən çıxarıldı");
    }

    // Servisi yenidən xidmətə qaytar
    public void bringBackIntoService() {
        applicationInfoManager.setInstanceStatus(InstanceInfo.InstanceStatus.UP);
        log.info("Servis yenidən xidmətə qayıdıldı");
    }
}
```

---

## Eureka vs Diğər Service Discovery Sistemləri

| Xüsusiyyət | Eureka | Consul | Zookeeper |
|------------|--------|--------|-----------|
| Consistency | AP (Available) | CP (Consistent) | CP |
| Health Check | Pull (client-based) | Push + TTL | Session-based |
| Multi-datacenter | Zone support | Built-in | Manual |
| Key-Value Store | Yox | Var | Var |
| Spring inteqrasiya | Mükəmməl | Yaxşı | Orta |
| Kubernetes-ə uyğun | Orta | Yaxşı | Yaxşı |

**CAP Theorem:**
- Eureka **AP** seçir: Şəbəkə bölünməsi zamanı köhnə data saxlanılır (Availability > Consistency)
- Consul/Zookeeper **CP** seçir: Şəbəkə bölünməsi zamanı cavab vermir (Consistency > Availability)

---

## İntervyu Sualları

**S: Eureka self-preservation mode nədir?**
C: Eureka server müəyyən müddət ərzində gözlənilən heartbeat sayının 85%-dən az alınırsa, şəbəkə bölünməsi fərz edir və instance-ları registry-dən silmir. Bu, yanlış silinmənin qarşısını alır. Ancaq dev mühitdə bəzən çaşdırıcı ola bilər.

**S: Eureka AP mi, CP mi?**
C: Eureka AP (Available + Partition Tolerant) — CAP teoremine görə. Şəbəkə bölünməsi zamanı köhnə registry məlumatlarını saxlayır, əlçatanlığı qoruyur. Consul isə CP — şəbəkə bölünməsində cluster leader olmadan cavab vermir.

**S: `register-with-eureka: false` nə vaxt istifadə olunur?**
C: Eureka Server-in özü üçün — server özünü qeydiyyatdan keçirməsin deyə. Eyni zamanda yalnız Eureka-dan məlumat oxuyan (load balancer kimi işləyən) servislər üçün istifadə oluna bilər.

**S: Client registry-ni neçə saniyəyə bir yeniləyir?**
C: Default: 30 saniyə (`registry-fetch-interval-seconds: 30`). Bu o deməkdir ki, yeni servis qeydiyyatdan keçsə, mövcud client-lər onu 30 saniyə gecikməklə görə bilər. Dev mühitdə 5-ə endirə bilərik.

**S: Eureka cluster necə qurulur?**
C: Hər Eureka server digər peer-ləri `serviceUrl.defaultZone`-da göstərir. Server həm `register-with-eureka: true` həm də `fetch-registry: true` olur. Bu şəkildə bütün serverlər bir-birini tanıyır və registry-ni sinxronizasiya edir.

**S: Instance ID nəyə lazımdır?**
C: Eyni servisin çoxlu instance-ı (scale-out) olduğunda hər birini fərqləndirmək üçün. Default format: `hostname:app-name:port`. Kubernetes-də `${spring.application.name}:${random.value}` tövsiyə olunur.
