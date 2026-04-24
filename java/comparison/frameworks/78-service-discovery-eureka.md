# Service Discovery (Eureka, Consul, K8s) vs Laravel — Dərin Müqayisə

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Mikroservis mühitində xidmətlər bir-biri ilə danışır. Sual: "order-service payment-service-i harada tapsın?" Cavab iki pattern-dəndir:

- **Client-side discovery** — client (caller) service registry-dən adresləri çəkir və özü load-balance edir. Eureka, Consul (client library ilə) belə işləyir.
- **Server-side discovery** — client yalnız bir ünvan bilir (load balancer); LB registry-dən oxuyub routing edir. Kubernetes Service, AWS ELB, Nginx + Consul-template — belə modeldir.

**Spring ecosystem-də Netflix Eureka** ən klassik client-side discovery həllidir — Spring Cloud Eureka Server və Client dependency-lər ilə bir neçə sətrdə qalxır. Consul (Spring Cloud Consul) və Kubernetes (Spring Cloud Kubernetes) alternativlərdir.

**Laravel-də built-in service discovery yoxdur.** Adətən Kubernetes DNS (`payment-service.default.svc.cluster.local`) və ya env-based URL (`PAYMENT_SERVICE_URL=http://...`) istifadə olunur. Consul-PHP SDK ilə registry-ə yazmaq/oxumaq mümkündür. Service mesh (Istio, Linkerd) sidecar kimi hər iki tərəfi əhatə edir.

---

## Spring-də istifadəsi

### 1) Netflix Eureka Server

```xml
<!-- eureka-server/pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-netflix-eureka-server</artifactId>
    </dependency>
</dependencies>
```

```java
@SpringBootApplication
@EnableEurekaServer
public class EurekaServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(EurekaServerApplication.class, args);
    }
}
```

```yaml
# eureka-server/application.yml
server:
  port: 8761

spring:
  application:
    name: eureka-server
  security:
    user:
      name: eureka
      password: ${EUREKA_PASSWORD:s3cret}

eureka:
  instance:
    hostname: eureka
  client:
    register-with-eureka: false    # server özünü qeydiyyatdan keçirmir
    fetch-registry: false
  server:
    enable-self-preservation: true
    eviction-interval-timer-in-ms: 30000
    renewal-percent-threshold: 0.85
```

### 2) Eureka Server HA (peer-aware cluster)

```yaml
# peer-1 (eureka-1)
server:
  port: 8761

spring:
  profiles: peer1

eureka:
  instance:
    hostname: eureka-1
  client:
    service-url:
      defaultZone: http://eureka:s3cret@eureka-2:8762/eureka/,http://eureka:s3cret@eureka-3:8763/eureka/
    register-with-eureka: true
    fetch-registry: true
```

```yaml
# peer-2 (eureka-2)
server:
  port: 8762

spring:
  profiles: peer2

eureka:
  instance:
    hostname: eureka-2
  client:
    service-url:
      defaultZone: http://eureka:s3cret@eureka-1:8761/eureka/,http://eureka:s3cret@eureka-3:8763/eureka/
```

Hər peer digər peer-lərə register olur və registry replikasiya edir. Bir peer düşsə, qalan ikisi ilə sistem davam edir.

### 3) Eureka Client — xidməti qeydiyyata al

```xml
<!-- order-service/pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-netflix-eureka-client</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-loadbalancer</artifactId>
    </dependency>
</dependencies>
```

```java
@SpringBootApplication
@EnableDiscoveryClient     // Spring Boot 3.x-də bu annotation opsionaldır, auto-config kifayətdir
public class OrderServiceApplication {
    public static void main(String[] args) {
        SpringApplication.run(OrderServiceApplication.class, args);
    }
}
```

```yaml
# order-service/application.yml
spring:
  application:
    name: order-service

server:
  port: 0                        # random port — bir neçə instance eyni maşında

eureka:
  client:
    service-url:
      defaultZone: http://eureka:s3cret@eureka:8761/eureka
    register-with-eureka: true
    fetch-registry: true
    registry-fetch-interval-seconds: 30
  instance:
    prefer-ip-address: true
    instance-id: ${spring.application.name}:${spring.application.instance_id:${random.value}}
    lease-renewal-interval-in-seconds: 10
    lease-expiration-duration-in-seconds: 30
    metadata-map:
      version: '1.4.2'
      zone: 'eu-central-1a'
```

Boot vaxtı order-service Eureka-ya POST `/apps/ORDER-SERVICE` göndərir. Hər 10 saniyədə heartbeat (`PUT /apps/ORDER-SERVICE/{instance-id}`). 30 saniyə ərzində heartbeat gəlməsə, instance evict olunur.

### 4) DiscoveryClient API — xidmətləri proqramatik tap

```java
@RestController
@RequiredArgsConstructor
public class DiscoveryController {

    private final DiscoveryClient discoveryClient;

    @GetMapping("/services")
    public Map<String, List<ServiceInstance>> services() {
        return discoveryClient.getServices().stream()
            .collect(Collectors.toMap(
                Function.identity(),
                name -> discoveryClient.getInstances(name)
            ));
    }

    @GetMapping("/payment-instance")
    public ServiceInstance payment() {
        List<ServiceInstance> list = discoveryClient.getInstances("payment-service");
        if (list.isEmpty()) throw new RuntimeException("No instances");
        return list.get(new Random().nextInt(list.size()));
    }
}
```

### 5) Spring Cloud LoadBalancer — Ribbon-un yerinə

```java
@Configuration
@LoadBalancerClient(name = "payment-service", configuration = PaymentLbConfig.class)
public class LbConfig { }

public class PaymentLbConfig {
    @Bean
    public ServiceInstanceListSupplier paymentInstanceSupplier(ConfigurableApplicationContext ctx) {
        return ServiceInstanceListSupplier.builder()
            .withDiscoveryClient()
            .withZonePreference()         // eyni zone instance-ları üstün tut
            .withHealthChecks()
            .withCaching()
            .build(ctx);
    }

    @Bean
    public ReactorLoadBalancer<ServiceInstance> roundRobin(
            Environment env,
            LoadBalancerClientFactory factory) {
        String name = env.getProperty(LoadBalancerClientFactory.PROPERTY_NAME);
        return new RoundRobinLoadBalancer(
            factory.getLazyProvider(name, ServiceInstanceListSupplier.class),
            name
        );
    }
}
```

```java
@Configuration
public class RestClientConfig {

    @Bean
    @LoadBalanced
    public RestClient.Builder restClientBuilder() {
        return RestClient.builder();
    }

    @Bean
    public PaymentClient paymentClient(RestClient.Builder builder) {
        return new PaymentClient(builder.baseUrl("http://payment-service").build());
    }
}

@Service
@RequiredArgsConstructor
public class PaymentClient {
    private final RestClient rc;

    public PaymentResult charge(ChargeRequest req) {
        return rc.post()
            .uri("/charges")
            .contentType(MediaType.APPLICATION_JSON)
            .body(req)
            .retrieve()
            .body(PaymentResult.class);
    }
}
```

`http://payment-service` Spring tərəfindən intercept olunur — `payment-service` adını DiscoveryClient-dən resolve edib real instance-a yönləndirir.

### 6) Consul Service Discovery

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-consul-discovery</artifactId>
</dependency>
```

```yaml
spring:
  application:
    name: order-service
  cloud:
    consul:
      host: consul
      port: 8500
      discovery:
        enabled: true
        service-name: ${spring.application.name}
        instance-id: ${spring.application.name}:${random.value}
        health-check-path: /actuator/health
        health-check-interval: 10s
        health-check-critical-timeout: 30s
        prefer-ip-address: true
        tags:
          - version=1.4.2
          - zone=eu-central-1a
```

Consul həm service registry, həm də KV store, həm də mesh-dir. Spring Cloud Consul eyni API ilə (`DiscoveryClient`, `@LoadBalanced`) işləyir — Eureka-dan keçmək üçün yalnız dependency dəyişir.

### 7) Kubernetes native discovery

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-kubernetes-client-discovery</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-kubernetes-client-loadbalancer</artifactId>
</dependency>
```

```yaml
spring:
  application:
    name: order-service
  cloud:
    kubernetes:
      discovery:
        all-namespaces: false
        namespaces: [default, payments]
        service-labels:
          env: prod
      loadbalancer:
        mode: SERVICE              # və ya POD
```

Spring Cloud Kubernetes K8s API-sini oxuyur — `kubectl get endpoints` ilə görünənləri `DiscoveryClient` verir. SERVICE mode-da ClusterIP istifadə olunur (K8s DNS), POD mode-da pod IP-lər birbaşa — client-side LB.

### 8) Zone awareness

```yaml
eureka:
  instance:
    metadata-map:
      zone: eu-central-1a

spring:
  cloud:
    loadbalancer:
      configurations: zone-preference
```

Caller və callee eyni zone-dadırsa, LB üstün tutur. Cross-zone trafik latency artırır və AWS-də pul tələb edir.

### 9) Graceful shutdown + deregister

```yaml
eureka:
  client:
    should-unregister-on-shutdown: true

server:
  shutdown: graceful

spring:
  lifecycle:
    timeout-per-shutdown-phase: 30s
```

Bu sayədə pod öldürüləndən əvvəl Eureka-dan silinir və yeni sorğu gəlmir. Amma registry cache-ə görə 30 saniyə qədər gecikə bilər — `preStop` hook əlavə et:

```yaml
# k8s deployment
lifecycle:
  preStop:
    exec:
      command: ["sh", "-c", "curl -X PUT http://eureka:s3cret@eureka:8761/eureka/apps/ORDER-SERVICE/$HOSTNAME/status?value=OUT_OF_SERVICE && sleep 30"]
```

### 10) Docker-compose — Eureka + 2 xidmət

```yaml
version: '3.9'
services:
  eureka:
    image: mycompany/eureka-server:latest
    ports: ["8761:8761"]
    environment:
      EUREKA_PASSWORD: s3cret
    healthcheck:
      test: curl -u eureka:s3cret -f http://localhost:8761/actuator/health || exit 1

  order-service:
    image: mycompany/order-service:latest
    depends_on:
      eureka: { condition: service_healthy }
    environment:
      EUREKA_CLIENT_SERVICEURL_DEFAULTZONE: http://eureka:s3cret@eureka:8761/eureka
    deploy:
      replicas: 3

  payment-service:
    image: mycompany/payment-service:latest
    depends_on:
      eureka: { condition: service_healthy }
    environment:
      EUREKA_CLIENT_SERVICEURL_DEFAULTZONE: http://eureka:s3cret@eureka:8761/eureka
    deploy:
      replicas: 2
```

---

## Laravel-də istifadəsi

### 1) Ənənəvi yanaşma — env-based URL

Laravel production-un 80%-i sadəcə env dəyişən ilə işləyir.

```bash
# .env
ORDER_SERVICE_URL=http://order-service.default.svc.cluster.local:8081
PAYMENT_SERVICE_URL=http://payment-service.default.svc.cluster.local:8082
```

```php
// config/services.php
return [
    'order' => [
        'base_url' => env('ORDER_SERVICE_URL'),
        'timeout' => (int) env('ORDER_SERVICE_TIMEOUT', 5),
    ],
    'payment' => [
        'base_url' => env('PAYMENT_SERVICE_URL'),
        'timeout' => (int) env('PAYMENT_SERVICE_TIMEOUT', 5),
    ],
];
```

```php
// app/Clients/PaymentClient.php
class PaymentClient
{
    public function charge(array $data): array
    {
        return Http::baseUrl(config('services.payment.base_url'))
            ->timeout(config('services.payment.timeout'))
            ->retry(2, 200)
            ->acceptJson()
            ->post('/charges', $data)
            ->throw()
            ->json();
    }
}
```

Kubernetes mühitində `payment-service.default.svc.cluster.local` DNS kube-proxy tərəfindən Service-in ClusterIP-inə çevrilir — load balancing avtomatik iptables/IPVS ilə olur. Bu server-side discovery-dir.

### 2) Consul integration via SDK

```bash
composer require sensiolabs/consul-php-sdk
```

```php
// app/Providers/ConsulServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SensioLabs\Consul\ServiceFactory;

class ConsulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('consul.factory', fn () =>
            new ServiceFactory(['base_uri' => config('consul.url', 'http://consul:8500')])
        );

        $this->app->singleton('consul.catalog', fn ($app) => $app->make('consul.factory')->get('catalog'));
        $this->app->singleton('consul.agent',   fn ($app) => $app->make('consul.factory')->get('agent'));
        $this->app->singleton('consul.health',  fn ($app) => $app->make('consul.factory')->get('health'));
    }
}
```

### 3) Self-register Laravel xidməti Consul-a

```php
// app/Console/Commands/ConsulRegister.php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConsulRegister extends Command
{
    protected $signature = 'consul:register';
    protected $description = 'Register this service with Consul';

    public function handle(): int
    {
        $agent = app('consul.agent');

        $service = [
            'ID'      => config('app.name') . ':' . gethostname(),
            'Name'    => config('app.name'),
            'Address' => gethostbyname(gethostname()),
            'Port'    => (int) config('app.port', 8080),
            'Tags'    => ['laravel', 'v' . config('app.version')],
            'Check'   => [
                'HTTP'     => 'http://' . gethostbyname(gethostname()) . ':' . config('app.port') . '/health',
                'Interval' => '10s',
                'Timeout'  => '2s',
                'DeregisterCriticalServiceAfter' => '1m',
            ],
        ];

        $agent->registerService($service);
        $this->info('Registered: ' . $service['ID']);
        return self::SUCCESS;
    }
}

// app/Console/Commands/ConsulDeregister.php
class ConsulDeregister extends Command
{
    protected $signature = 'consul:deregister';

    public function handle(): int
    {
        app('consul.agent')->deregisterService(config('app.name') . ':' . gethostname());
        $this->info('Deregistered');
        return self::SUCCESS;
    }
}
```

```dockerfile
# Dockerfile entrypoint
CMD ["sh", "-c", "php artisan consul:register && php artisan octane:start --host=0.0.0.0"]

# SIGTERM handler via tini və ya preStop
```

```yaml
# k8s deployment lifecycle
lifecycle:
  preStop:
    exec:
      command: ["php", "artisan", "consul:deregister"]
```

### 4) Consul-dan xidməti lookup et

```php
// app/Support/ServiceDiscovery.php
namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ServiceDiscovery
{
    public function resolve(string $name): string
    {
        return Cache::remember("svc:{$name}", 10, function () use ($name) {
            $catalog = app('consul.catalog');
            $resp = $catalog->service($name);
            $instances = json_decode($resp->getBody(), true);

            if (empty($instances)) {
                throw new \RuntimeException("No instances for {$name}");
            }

            // sadə round-robin
            $idx = Cache::increment("svc:{$name}:idx") % count($instances);
            $node = $instances[$idx];

            return "http://{$node['ServiceAddress']}:{$node['ServicePort']}";
        });
    }

    public function resolveHealthy(string $name): string
    {
        $health = app('consul.health');
        $resp = $health->service($name, '', true);  // only passing
        $nodes = json_decode($resp->getBody(), true);

        if (empty($nodes)) {
            throw new \RuntimeException("No healthy instances for {$name}");
        }

        $node = $nodes[array_rand($nodes)]['Service'];
        return "http://{$node['Address']}:{$node['Port']}";
    }
}
```

```php
// istifadə
class PaymentClient
{
    public function __construct(private ServiceDiscovery $discovery) {}

    public function charge(array $data): array
    {
        $base = $this->discovery->resolveHealthy('payment-service');

        return Http::baseUrl($base)
            ->timeout(5)
            ->retry(2, 200)
            ->post('/charges', $data)
            ->json();
    }
}
```

### 5) Redis-based custom registry (lightweight)

Consul overhead-i olmadan kiçik setup üçün Redis ilə service registry yazmaq olar.

```php
// app/Support/RedisRegistry.php
use Illuminate\Support\Facades\Redis;

class RedisRegistry
{
    public function register(string $name, string $address): void
    {
        Redis::zadd("registry:{$name}", microtime(true) * 1000, $address);
        Redis::expire("registry:{$name}", 60);
    }

    public function heartbeat(string $name, string $address): void
    {
        Redis::zadd("registry:{$name}", microtime(true) * 1000, $address);
    }

    public function instances(string $name, int $ttlMs = 30000): array
    {
        $now = microtime(true) * 1000;
        Redis::zremrangebyscore("registry:{$name}", 0, $now - $ttlMs);
        return Redis::zrange("registry:{$name}", 0, -1);
    }

    public function pick(string $name): ?string
    {
        $list = $this->instances($name);
        return $list[array_rand($list)] ?? null;
    }
}

// schedule-dan heartbeat
$schedule->call(function (RedisRegistry $r) {
    $r->heartbeat('order-service', gethostname() . ':8081');
})->everyTenSeconds();
```

### 6) Kubernetes-native discovery (Laravel-in ən rahat yolu)

K8s-də Service obyekti yaradanda DNS və ClusterIP avtomatik qurulur.

```yaml
# k8s/order-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: order-service
  namespace: default
spec:
  selector:
    app: order-service
  ports:
    - port: 8081
      targetPort: 8081
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
spec:
  replicas: 3
  selector:
    matchLabels:
      app: order-service
  template:
    metadata:
      labels:
        app: order-service
    spec:
      containers:
      - name: app
        image: mycompany/order-service-laravel:1.4.2
        readinessProbe:
          httpGet: { path: /health, port: 8081 }
          initialDelaySeconds: 5
          periodSeconds: 10
        lifecycle:
          preStop:
            exec:
              command: ["sleep", "10"]
```

Laravel klient tərəfində kod dəyişmir — `http://order-service:8081` yazmaq kifayətdir. kube-proxy iptables/IPVS ilə load-balance edir.

### 7) Sidecar pattern (Istio/Linkerd)

Istio və ya Linkerd sidecar container pod-a qoyulur. Bütün trafik əvvəlcə sidecar-dan keçir — mTLS, retry, timeout, circuit breaker, observability avtomatik.

```yaml
# istio injection
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
  annotations:
    sidecar.istio.io/inject: "true"
spec:
  template:
    metadata:
      labels:
        app: order-service
    spec:
      containers:
      - name: app
        image: mycompany/order-service-laravel:1.4.2
```

```yaml
# DestinationRule — retry + circuit breaker
apiVersion: networking.istio.io/v1beta1
kind: DestinationRule
metadata:
  name: payment-service
spec:
  host: payment-service
  trafficPolicy:
    connectionPool:
      http:
        http1MaxPendingRequests: 100
        maxRequestsPerConnection: 10
    outlierDetection:
      consecutive5xxErrors: 5
      interval: 10s
      baseEjectionTime: 30s
---
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: payment-service
spec:
  hosts: [payment-service]
  http:
  - retries:
      attempts: 3
      perTryTimeout: 2s
      retryOn: 5xx,reset,connect-failure
    timeout: 10s
    route:
    - destination: { host: payment-service }
```

Laravel kodu dəyişmir — Istio sidecar `http://payment-service`-ə gedən trafiki idarə edir.

### 8) docker-compose — Consul + 2 Laravel service

```yaml
version: '3.9'
services:
  consul:
    image: hashicorp/consul:latest
    ports: ["8500:8500"]
    command: agent -dev -client=0.0.0.0

  order-service:
    build: ./order-service
    environment:
      CONSUL_URL: http://consul:8500
      APP_NAME: order-service
    depends_on: [consul]
    deploy: { replicas: 3 }

  payment-service:
    build: ./payment-service
    environment:
      CONSUL_URL: http://consul:8500
      APP_NAME: payment-service
    depends_on: [consul]
    deploy: { replicas: 2 }
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Built-in discovery | Eureka/Consul/K8s starter | Yoxdur — manual |
| Auto-registration | `@EnableDiscoveryClient` | Manual artisan command |
| Heartbeat | Built-in (10s default) | Manual schedule task |
| Registry fetch | Client cache (30s) | Cache::remember() manual |
| Load balancing | Spring Cloud LoadBalancer | Kubernetes Service / Nginx upstream |
| Zone awareness | `zone` metadata + preference | Istio locality-aware LB |
| `lb://name` URI | Built-in (`@LoadBalanced`) | Manual URL resolution |
| Health check | Actuator + lease expiration | `/health` endpoint + Consul check |
| Graceful shutdown | `should-unregister-on-shutdown` | preStop hook + deregister command |
| Multi-zone/region | Eureka zone + LB | Istio MultiCluster / K8s federation |
| Service mesh integration | Optional (Spring Native+Istio) | Common (Istio/Linkerd sidecar) |
| Discovery API | `DiscoveryClient` Java interface | Consul SDK / Custom wrapper |

---

## Niyə belə fərqlər var?

**Client-side discovery Java dünyasının cavabıdır.** Netflix ilk böyük microservice player-i idi — 2012-ci ildə minlərlə Java instance-ı avtomatik idarə etmək üçün Eureka yaradıldı. Client-side LB-nin əsas üstünlüyü: hop sayısı azalır (LB keçmir, client birbaşa callee-yə gedir), cross-AZ traffic tənzimlənir. Spring Cloud bu mirası bütün Spring ekosisteminə gətirdi.

**Server-side discovery Kubernetes-in default modelidir.** K8s Service abstraksiyası iptables/IPVS səviyyəsində LB edir. Client yalnız DNS adı bilir, hər şey transparent olur. Bu yanaşma dil-agnostik-dir — Java, PHP, Go, Python hamısı eyni işləyir. Laravel bu səbəbdən K8s mühitində rahat oturur.

**PHP-nin request-per-process modeli.** Hər Laravel HTTP request-i yeni proses (və ya Octane-də yeni fiber) yaradır. Long-lived connection pool, registry cache sync və s. yoxdur — buna görə Eureka client-i Spring kimi inteqrasiya etmək mürəkkəbdir. Laravel-də registry cache Redis-də saxlanır, hər sorğu oxuyur.

**Sidecar pattern Laravel üçün ideal.** Istio / Linkerd sidecar dil-agnostik-dir. PHP tətbiqinin kodunu dəyişmədən mTLS, retry, CB, observability əldə edirsən. Java-da da işləyir, amma Spring Cloud artıq library səviyyəsində eyni şeyi verdiyi üçün bəzən duplikasiya olur.

**Eureka vs K8s Service.** Eureka heartbeat-əsaslıdır — hər instance 10 saniyədə "yaşayıram" deyir. K8s readinessProbe-əsaslıdır — kubelet yoxlayır. Eureka self-preservation mode (şəbəkə bölünməsi zamanı evict etmir) K8s-də yoxdur. Amma K8s daha sərt reality verir: readiness failing → endpoint remove → trafik getmir.

**Graceful shutdown fərqi.** Spring `should-unregister-on-shutdown=true` qoy, kifayət. Laravel-də sonlanma hookları daha çətindir — Octane worker SIGTERM-i nəzərdən keçirməli, lifecycle event-ləri ilə deregister etməlidir. K8s preStop hook bu boşluğu doldurur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring (Spring Cloud) ilə gələn:**
- `@EnableEurekaServer` built-in Eureka server
- `@EnableDiscoveryClient` auto-registration
- Eureka peer-aware cluster config
- Self-preservation mode (network partition-a qarşı)
- `DiscoveryClient` unified interface (Eureka/Consul/K8s eyni API)
- `@LoadBalanced RestClient.Builder` — URI-də `http://service-name` yazmaq
- `ReactorLoadBalancer` — reactive, zone-aware, health-filtered
- Ribbon-dan Spring Cloud LoadBalancer-ə miqrasiya
- Metadata-map (version, zone, canary tag)
- Eureka dashboard `/eureka` — browser-dən görmə

**Laravel-də built-in yoxdur, amma ekosistemlə əldə edilir:**
- Kubernetes Service + CoreDNS (server-side)
- Consul PHP SDK (manual)
- Istio/Linkerd sidecar (dil-agnostik)
- Redis/DB custom registry
- AWS Cloud Map + Route 53

**Hər iki ekosistemdə istifadə olunan:**
- HashiCorp Consul (registry + KV + mesh)
- Kubernetes Service + Endpoints
- Istio service mesh (mTLS, retry, CB)
- Linkerd mesh (golden metrics, latency-aware)
- AWS Service Discovery
- DNS-based discovery (CoreDNS, ExternalDNS)

---

## Best Practices

**Spring Cloud discovery üçün:**
- Eureka Server-i HA qur (3 peer minimum)
- Self-preservation mode prod-da açıq saxla — network blip-də flap olmasın
- `lease-renewal-interval=10s`, `lease-expiration-duration=30s` — balans arasında dəqiqlik və bloklanma
- `prefer-ip-address=true` ver — DNS olmayan mühitlərdə problemsiz
- Metadata-map qoy: version, zone, canary — LB filter üçün
- `should-unregister-on-shutdown=true` + graceful shutdown
- Zone-aware LB konfiqurasiya et — cross-AZ traffic azaldır
- Client-də `fetch-registry` cache-lər — network down olsa belə işləsin
- K8s-də Eureka lazım deyil — `spring-cloud-starter-kubernetes-client-discovery` istifadə et

**Laravel discovery üçün:**
- K8s mühitində sadəcə Service + DNS istifadə et — çətinləşdirmə
- Consul lazımdırsa, sidecar registrator (`gliderlabs/registrator`) istifadə et
- Self-register yerinə registrator sidecar-a həvalə et — Laravel-ə sızmasın
- Cache registry response 10–30 saniyə (Redis) — Consul-a hər sorğu getməsin
- Istio sidecar ilə retry/CB layını move et — application layer-dən çıxart
- `preStop` hook-da deregister et, `sleep 10` əlavə et — in-flight requests bitsin
- readinessProbe-u düzgün yaz — yalnız boot tamam olandan sonra ready et
- DNS TTL qısa saxla — instance dəyişəndə cache 30s-dən uzun qalmasın

**Ümumi:**
- Client-side vs server-side seçimi: K8s-də server-side kifayətdir, çoxlu DC-də client-side daha əl verir
- Discovery single point of failure olmasın — Eureka 3 peer, Consul 5 server
- mTLS-i gateway və mesh səviyyəsində qur — app səviyyəsində yox
- Canary deployment üçün metadata tag + weighted LB istifadə et
- Observability: hər service-to-service call üçün trace, latency histogram, error rate topla
- Instance tag-ları versiyalı saxla — rollback zamanı lazım olur

---

## Yekun

**Spring ekosistemi service discovery üçün ən mature alətdir.** Netflix Eureka 10+ ildir production-da, Spring Cloud Consul və Kubernetes-i eyni `DiscoveryClient` interface-i altında birləşdirib. Bir annotation və bir dependency ilə xidmət registry-ə qoşulur. Load balancer, zone awareness, retry, CB — hamısı stack-in içindədir.

**Laravel-də service discovery framework-dən kənar qalır.** Ən rahat yol — Kubernetes Service DNS. Spring-in Eureka imkanlarını Laravel-ə gətirmək üçün Consul SDK + custom wrapper + schedule task yazmaq lazımdır. Amma bu adətən overkill-dir: K8s + Istio sidecar eyni nəticəni Laravel koduna toxunmadan verir.

**Praktik seçim:** Tək Java stack varsa və Netflix OSS ənənəsini davam etdirmək istəyirsənsə, Eureka + Spring Cloud LoadBalancer. Polyglot mühit (Java + PHP + Go) və K8s-dədirsənsə, **K8s Service + Istio sidecar** ortaq həlldir — hər iki tərəf üçün eyni davranış, dil-agnostik. Laravel-only mühit və kiçik K8s cluster üçün: sadəcə Service + DNS + `Http::retry()` kifayətdir. Consul və Eureka overkill ola bilər.
