# Graceful Shutdown (Senior)

> **Seviyye:** Senior ⭐⭐⭐

## İcmal

**Graceful Shutdown** — tətbiqin dayandırılması zamanı aktiv sorğuların tamamlanmasını gözləmək, yeni sorğuları qəbul etməmək, cleanup əməliyyatlarını (DB connection pool, Kafka producer, file handles) düzgün bağlamaq.

---

## Niyə Vacibdir

```
Graceful shutdown olmadan:
  SIGTERM gəlir → JVM ani dayandırılır
  → 50 aktiv HTTP sorğudan 30-u yarımçıq qalır
  → DB transaction-lar rollback olmur (connection zorla bağlanır)
  → Kafka mesajları göndərilmir (producer buffer-dədir)
  → İstifadəçi "Connection reset" xətası görür

Kubernetes/Docker-da:
  Pod update zamanı SIGTERM → pod ölür → yeni pod başlayır
  → Yüklənmə balansçısı hələ köhnə pod-a sorğu göndərə bilər
  → Graceful shutdown yoxdursa data corruption risqi
```

---

## Spring Boot Konfiqurasiyası

```yaml
# application.yml
server:
  shutdown: graceful          # Default: immediate

spring:
  lifecycle:
    timeout-per-shutdown-phase: 30s  # Maksimum gözləmə müddəti
```

Bu iki sətir ilə Spring Boot:
1. `SIGTERM` alanda yeni sorğu qəbulunu dayandırır
2. Aktiv sorğuların tamamlanmasını 30 saniyəyə qədər gözləyir
3. 30 saniyə keçsə tamamlanmamışları kəsir
4. Bütün Spring bean-ları düzgün ardıcıllıqla bağlayır

---

## Lifecycle Hooks

```java
// @PreDestroy — shutdown zamanı çağrılır
@Service
public class ReportService {

    private final ScheduledExecutorService scheduler = Executors.newSingleThreadScheduledExecutor();

    @PreDestroy
    public void cleanup() {
        log.info("ReportService bağlanır...");
        scheduler.shutdown();
        try {
            if (!scheduler.awaitTermination(10, TimeUnit.SECONDS)) {
                scheduler.shutdownNow();
            }
        } catch (InterruptedException e) {
            scheduler.shutdownNow();
            Thread.currentThread().interrupt();
        }
        log.info("ReportService bağlandı");
    }
}
```

```java
// SmartLifecycle — daha çox nəzarət
@Component
public class KafkaProducerLifecycle implements SmartLifecycle {

    private final KafkaProducer<String, String> producer;
    private volatile boolean running = false;

    @Override
    public void start() {
        running = true;
        log.info("KafkaProducer başladı");
    }

    @Override
    public void stop(Runnable callback) {
        log.info("KafkaProducer flush başladı...");
        producer.flush();    // Buffer-dəki bütün mesajları göndər
        producer.close(Duration.ofSeconds(10));
        running = false;
        callback.run();      // Spring-ə "bitdi" siqnalı
        log.info("KafkaProducer bağlandı");
    }

    @Override
    public boolean isRunning() {
        return running;
    }

    @Override
    public int getPhase() {
        return Integer.MAX_VALUE - 100; // Daha əvvəl dayanır (yüksək = əvvəl)
    }

    @Override
    public boolean isAutoStartup() {
        return true;
    }
}
```

### Fase sıralaması (Phase)

```
Shutdown phase sırası (yüksək phase → əvvəl dayanır):
  Phase MAX_VALUE     → Web server (yeni sorğu qəbulunu dayan)
  Phase MAX_VALUE-100 → Message consumers (yeni mesaj qəbulunu dayan)
  Phase MAX_VALUE-200 → Kafka producer (flush et, bağla)
  Phase 0             → Application bean-ları
  Phase MIN_VALUE     → Database connection pool (ən son bağla)
```

---

## Kubernetes inteqrasiyası

```yaml
# deployment.yaml
spec:
  template:
    spec:
      terminationGracePeriodSeconds: 60  # Kubernetes-in gözləmə müddəti

      containers:
        - name: app
          lifecycle:
            preStop:
              exec:
                # Pod-u Kubernetes endpoint-lər siyahısından çıxar
                # Yük balansçısı artıq bu pod-a sorğu göndərmir
                command: ["sleep", "10"]
```

```yaml
# application.yml
server:
  shutdown: graceful

spring:
  lifecycle:
    timeout-per-shutdown-phase: 45s  # terminationGracePeriodSeconds-dan az olmalı

management:
  endpoint:
    health:
      probes:
        enabled: true  # liveness/readiness probe-ları aktiv et
  health:
    livenessState:
      enabled: true
    readinessState:
      enabled: true
```

```
Kubernetes shutdown axını:
  1. Pod "Terminating" state-inə keçir
  2. preStop hook → "sleep 10" → yük balansçısı sorğu göndərməyi dayandırır
  3. SIGTERM → Spring graceful shutdown başlayır
  4. Aktiv sorğular tamamlanır (45s)
  5. SIGKILL → terminationGracePeriodSeconds (60s) keçsə
```

### Health Probe-lar

```yaml
# Kubernetes probe konfiqurasiyası
livenessProbe:
  httpGet:
    path: /actuator/health/liveness
    port: 8080
  initialDelaySeconds: 30
  periodSeconds: 10
  failureThreshold: 3

readinessProbe:
  httpGet:
    path: /actuator/health/readiness
    port: 8080
  initialDelaySeconds: 10
  periodSeconds: 5
  failureThreshold: 3
```

```java
// Shutdown zamanı readiness probe-u manual "DOWN" et
@Component
public class ShutdownReadinessIndicator implements ApplicationListener<ContextClosedEvent> {

    @Autowired
    private ApplicationContext context;

    @Override
    public void onApplicationEvent(ContextClosedEvent event) {
        ReadinessState readinessState = (ReadinessState) context
            .getBean(ApplicationAvailability.class)
            .getState(ReadinessState.class);

        // Artıq yeni sorğu göndərməyin siqnalı
        AvailabilityChangeEvent.publish(context, ReadinessState.REFUSING_TRAFFIC);
        log.info("Readiness probe DOWN — yeni sorğu qəbul edilmir");
    }
}
```

---

## Praktik Baxış

**Yoxlanılmalı:**
```bash
# Tətbiqi yavaş şəkildə dayandır və test et
curl -X POST http://localhost:8080/api/slow-endpoint &  # Uzun sürən sorğu
kill -TERM <pid>                                        # SIGTERM göndər

# Gözləntili nəticə:
# → slow-endpoint cavabı verir (graceful)
# → Yeni sorğular connection refused alır
```

**Ümumi xətalar:**
```java
// ❌ shutdown hook-da log yazmaq — logger artıq bağlana bilər
Runtime.getRuntime().addShutdownHook(new Thread(() -> {
    log.info("Bağlanıram");  // Logger bağlı ola bilər!
}));

// ✅ @PreDestroy və ya SmartLifecycle istifadə et
// Spring öz bean lifecycle-ını logger-dan əvvəl idarə edir

// ❌ @PreDestroy-da uzun blocking əməliyyat timeout olmadan
@PreDestroy
public void cleanup() {
    externalService.flush(); // 60 saniyə gözləyə bilər → Spring timeout aşa bilər
}

// ✅ Timeout ilə
@PreDestroy
public void cleanup() {
    try {
        externalService.flushWithTimeout(Duration.ofSeconds(15));
    } catch (TimeoutException e) {
        log.warn("Flush timeout — devam edilir");
    }
}
```

**Monitoring:**
```java
// Shutdown zamanı aktiv sorğu sayını izlə
@Component
public class RequestCounterFilter implements Filter {

    private final AtomicInteger activeRequests = new AtomicInteger(0);

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
                         FilterChain chain) throws IOException, ServletException {
        activeRequests.incrementAndGet();
        try {
            chain.doFilter(request, response);
        } finally {
            activeRequests.decrementAndGet();
        }
    }

    // Actuator ilə göstər
    @Bean
    public Gauge activeRequestsGauge(MeterRegistry registry) {
        return Gauge.builder("http.requests.active", activeRequests, AtomicInteger::get)
            .register(registry);
    }
}
```

---

## İntervyu Sualları

### 1. `server.shutdown=graceful` nə edir?
**Cavab:** Tomcat/Jetty/Undertow-a SIGTERM siqnalında dərhal dayandırma əvəzinə, aktiv sorğuları tamamlamasını bildirir. Yeni sorğular üçün server 503 qaytarır (yük balansçısı başqa pod-a keçir). `spring.lifecycle.timeout-per-shutdown-phase` müddəti keçsə tamamlanmamış sorğular kəsilir. Default `immediate` — ani dayandırma.

### 2. Kubernetes terminationGracePeriodSeconds ilə Spring timeout münasibəti?
**Cavab:** `terminationGracePeriodSeconds` Kubernetes səviyyəsindədir — bu müddət keçsə SIGKILL göndərir. Spring-in `timeout-per-shutdown-phase` bundan az olmalıdır ki Spring öz cleanup-ını tamamlasın. Adətən: `terminationGracePeriodSeconds: 60`, `timeout-per-shutdown-phase: 45s`. preStop hook əlavə vaxt alır yük balansçısının pod-u siyahıdan çıxarmasına görə.

### 3. SmartLifecycle vs @PreDestroy fərqi?
**Cavab:** `@PreDestroy` — sadə cleanup, phase nəzarəti yoxdur, async callback dəstəkləmir. `SmartLifecycle` — `getPhase()` ilə shutdown ardıcıllığını nəzarət edə bilərsən, `stop(Runnable callback)` ilə async shutdown mümkündür, `isRunning()` ilə state idarə olunur. Kafka/RabbitMQ kimi mesajlaşma sistemləri üçün `SmartLifecycle` tövsiyə edilir.

*Son yenilənmə: 2026-04-27*
