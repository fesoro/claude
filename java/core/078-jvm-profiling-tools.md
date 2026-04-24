# 078 — JVM Profiling Tools — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [JVM Profiling nədir?](#jvm-profiling-nədir)
2. [JVM built-in alətlər](#jvm-built-in-alətlər)
3. [VisualVM & JProfiler](#visualvm--jprofiler)
4. [Async Profiler](#async-profiler)
5. [Spring Boot Actuator & Micrometer](#spring-boot-actuator--micrometer)
6. [Memory leak analizi](#memory-leak-analizi)
7. [İntervyu Sualları](#intervyu-sualları)

---

## JVM Profiling nədir?

```
Profiling — application davranışını ölçmək:
  → Hansı metod ən çox CPU istehlak edir?
  → Hansı obyekt ən çox RAM tutir?
  → Thread-lər harada gözləyir?
  → GC nə qədər vaxt aparır?

Ne zaman lazımdır:
  → "Production-da yavaş amma development-da sürətli"
  → "RAM istehlakı durmadan artır" (memory leak)
  → "CPU 100%-dədir, niyə?"
  → "Response time artıb, nə dəyişdi?"

Profiling növləri:
  CPU Profiling    → Hansı kod ən çox CPU vaxtı alır?
  Memory Profiling → Heap-də nə var, kim tutur?
  Thread Profiling → Deadlock, thread contention
  I/O Profiling    → DB, network, disk bekleme

Sampling vs Instrumentation:
  Sampling:
    → Müəyyən aralıqlarla stack trace götürür (N ms-dən bir)
    → Aşağı overhead (~1-3%)
    → Statistik nəticə (tam dəqiq deyil)

  Instrumentation:
    → Hər metod girişini/çıxışını ölçür (byte code manipulation)
    → Yüksək overhead (10-50%+)
    → Dəqiq nəticə
    → Production-da istifadə edilmir!

  Async Profiler → Sampling, amma daha dəqiq (safepoint bias yoxdur)
```

---

## JVM built-in alətlər

```bash
# ─── jps — Java prosesləri siyahısı ──────────────────────
jps -l
# Output:
# 12345 com.example.Application
# 12346 org.springframework.boot.devtools.RemoteSpringApplication

# ─── jstack — Thread dump ─────────────────────────────────
jstack 12345 > thread-dump.txt
jstack -l 12345   # Long listing (lock məlumatı da)

# Thread dump analizi:
# → "BLOCKED" thread-lər → deadlock şübhəsi
# → "WAITING (on object monitor)" → lock contention
# → "TIMED_WAITING" → sleep, wait
# → "RUNNABLE" → CPU-da çalışır

# Deadlock aşkar etmək:
grep -A 10 "deadlock" thread-dump.txt

# ─── jmap — Heap dump & statistika ──────────────────────
jmap -heap 12345                    # Heap xülasəsi
jmap -histo:live 12345              # Canlı obyektlər statistikası
jmap -dump:format=b,file=heap.hprof 12345  # Tam heap dump

# Heap statistika nümunəsi:
# num     #instances         #bytes  class name
# -------------------------------------------
#   1:       1234567      987654321  byte[]
#   2:        234567       12345678  java.lang.String
#   3:         34567        5678901  com.example.OrderEntity

# ─── jstat — JVM statistika ──────────────────────────────
jstat -gc 12345 1000 10    # 1s-dən bir GC statistika, 10 dəfə
jstat -gcutil 12345        # GC utilization (%)
jstat -class 12345         # Class loading statistika

# GC output:
# S0C    S1C    S0U    S1U   EC    EU    OC    OU   MC   MU
#   Survivor0 Cap/Use, Eden Cap/Use, Old Cap/Use, Metaspace

# ─── jcmd — JVM diaqnostika ──────────────────────────────
jcmd 12345 help                          # Əmrlər siyahısı
jcmd 12345 VM.flags                      # JVM flag-ları
jcmd 12345 VM.system_properties          # System properties
jcmd 12345 Thread.print                  # Thread dump
jcmd 12345 GC.run                        # Manuel GC
jcmd 12345 GC.heap_dump /tmp/heap.hprof  # Heap dump
jcmd 12345 JFR.start name=myrecording duration=60s filename=/tmp/recording.jfr
jcmd 12345 JFR.stop name=myrecording

# ─── Java Flight Recorder (JFR) ──────────────────────────
# JDK 11+, production-safe, aşağı overhead (~1-2%)
java -XX:+FlightRecorder \
     -XX:StartFlightRecording=duration=60s,filename=myapp.jfr \
     -jar myapp.jar

# Runtime-da başlat
jcmd 12345 JFR.start \
    settings=profile \
    name=profile \
    duration=120s \
    filename=/tmp/app-profile.jfr
```

---

## VisualVM & JProfiler

```bash
# ─── VisualVM — pulsuz, JDK ilə gəlir ────────────────────
# JDK 9+ ayrı yüklənir: https://visualvm.github.io/

# JMX vasitəsilə uzaqdan bağlantı üçün JVM flags:
java -Djava.rmi.server.hostname=<server-ip> \
     -Dcom.sun.management.jmxremote \
     -Dcom.sun.management.jmxremote.port=9999 \
     -Dcom.sun.management.jmxremote.ssl=false \
     -Dcom.sun.management.jmxremote.authenticate=false \
     -jar myapp.jar

# Docker-da JMX:
JAVA_OPTS="-Djava.rmi.server.hostname=0.0.0.0 \
           -Dcom.sun.management.jmxremote \
           -Dcom.sun.management.jmxremote.port=9999 \
           -Dcom.sun.management.jmxremote.rmi.port=9999 \
           -Dcom.sun.management.jmxremote.ssl=false \
           -Dcom.sun.management.jmxremote.authenticate=false"

# ─── VisualVM imkanları ───────────────────────────────────
# Monitor tab:    CPU, Heap, Threads, Classes real-time
# Threads tab:    Thread state görselliği
# Sampler tab:    CPU/Memory sampling (overhead aşağı)
# Profiler tab:   CPU/Memory instrumentation (overhead yüksək)
# Heap Dump:      Objects arasında reference analizi
```

```java
// ─── JMX Beans — Spring Boot ─────────────────────────────
@Configuration
public class JmxConfig {

    @Bean
    @ConditionalOnProperty("management.jmx.enabled")
    public MBeanServer mbeanServer() {
        return ManagementFactory.getPlatformMBeanServer();
    }
}

// application.yml:
// management:
//   jmx:
//     enabled: true
//   endpoints:
//     jmx:
//       exposure:
//         include: "*"
```

---

## Async Profiler

```bash
# ─── Async Profiler — ən güclü Java sampler ──────────────
# https://github.com/jvm-profiling-tools/async-profiler
# Production-safe, safepoint bias yoxdur!

# Download
wget https://github.com/async-profiler/async-profiler/releases/download/v3.0/async-profiler-3.0-linux-x64.tar.gz
tar xzf async-profiler-3.0-linux-x64.tar.gz

# ─── CPU profiling ────────────────────────────────────────
./profiler.sh start <pid>                  # Başlat
./profiler.sh stop <pid>                   # Dayandır, konsola çap

# 30 saniyəlik profil, flamegraph
./profiler.sh -d 30 -f /tmp/cpu.html <pid>

# ─── Memory allocation profiling ─────────────────────────
./profiler.sh -e alloc -d 30 -f /tmp/alloc.html <pid>

# ─── Wall clock profiling (I/O dahil) ────────────────────
./profiler.sh -e wall -d 30 -f /tmp/wall.html <pid>

# ─── Lock contention profiling ───────────────────────────
./profiler.sh -e lock -d 30 -f /tmp/lock.html <pid>

# ─── JFR formatında çıxarma ──────────────────────────────
./profiler.sh -d 30 -o jfr -f /tmp/profile.jfr <pid>

# ─── Docker container-da profiling ───────────────────────
docker exec -it <container-id> sh
# Container-ın PID-ni tap:
ps aux | grep java
# Async profiler container-a kopyala və çalışdır

# ─── Flamegraph oxumaq ────────────────────────────────────
# X eksenı: metodun adı (əlifba sırasında)
# Y eksenı: call stack dərinliyi
# Genişlik: CPU vaxtının faizi
# → Ən geniş "düz" hissə → hotspot (bottleneck)!
```

```java
// ─── Async Profiler Java API ──────────────────────────────
// Maven:
// <dependency>
//     <groupId>tools.profiler</groupId>
//     <artifactId>async-profiler</artifactId>
// </dependency>

// Programmatic profiling — integration test zamanı
@Test
void profileOrderProcessing() throws Exception {
    AsyncProfiler profiler = AsyncProfiler.getInstance();

    profiler.start(Events.CPU, 10_000_000); // 10ms interval

    // Profile ediləcək kod
    for (int i = 0; i < 10000; i++) {
        orderService.processOrder(createTestOrder());
    }

    profiler.stop();
    profiler.execute("flamegraph,file=/tmp/test-profile.html");

    // /tmp/test-profile.html-i browser-da aç
}
```

---

## Spring Boot Actuator & Micrometer

```yaml
# application.yml — Actuator metrics
management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,prometheus,threaddump,heapdump,jfr
  endpoint:
    health:
      show-details: always
    heapdump:
      enabled: true      # /actuator/heapdump → heap.hprof download
    threaddump:
      enabled: true      # /actuator/threaddump → thread dump
    jfr:
      enabled: true      # /actuator/jfr → JFR recording
  metrics:
    export:
      prometheus:
        enabled: true
    tags:
      application: order-service
      environment: production
```

```java
// ─── Custom Metrics ───────────────────────────────────────
@Service
public class OrderService {

    private final MeterRegistry meterRegistry;
    private final Counter ordersCreatedCounter;
    private final Timer orderProcessingTimer;
    private final Gauge pendingOrdersGauge;

    public OrderService(MeterRegistry meterRegistry,
                        OrderRepository orderRepository) {
        this.meterRegistry = meterRegistry;

        // Counter — həmişə artan sayğac
        this.ordersCreatedCounter = Counter.builder("orders.created")
            .tag("type", "total")
            .description("Yaradılan sifarişlər")
            .register(meterRegistry);

        // Timer — əməliyyat müddəti
        this.orderProcessingTimer = Timer.builder("order.processing.time")
            .description("Sifariş işlənmə müddəti")
            .publishPercentiles(0.5, 0.95, 0.99) // P50, P95, P99
            .register(meterRegistry);

        // Gauge — anlıq dəyər
        this.pendingOrdersGauge = Gauge.builder("orders.pending",
                orderRepository, OrderRepository::countByStatus)
            .tag("status", "PENDING")
            .register(meterRegistry);
    }

    public Order createOrder(CreateOrderRequest request) {
        return orderProcessingTimer.record(() -> {
            Order order = new Order(request);
            order = orderRepository.save(order);
            ordersCreatedCounter.increment();
            return order;
        });
    }
}

// ─── @Timed annotation ────────────────────────────────────
@Service
public class PaymentService {

    @Timed(
        value = "payment.processing",
        description = "Ödəniş işlənmə müddəti",
        percentiles = {0.5, 0.95, 0.99},
        extraTags = {"type", "card"}
    )
    public PaymentResult processPayment(PaymentRequest request) {
        return paymentGateway.charge(request);
    }
}

// ─── DistributionSummary — histogram ─────────────────────
@Component
public class RequestSizeMetrics {

    private final DistributionSummary requestSizeSummary;

    public RequestSizeMetrics(MeterRegistry registry) {
        this.requestSizeSummary = DistributionSummary.builder("http.request.size")
            .description("HTTP sorğu ölçüsü (bytes)")
            .baseUnit("bytes")
            .serviceLevelObjectives(100, 1024, 10240)  // SLO buckets
            .register(registry);
    }

    public void recordRequestSize(long bytes) {
        requestSizeSummary.record(bytes);
    }
}
```

---

## Memory leak analizi

```bash
# ─── Memory leak aşkar etmək ─────────────────────────────
# 1. Heap usage artırmı izlə
jstat -gcutil <pid> 5000 60   # 5s-dən bir, 60 dəfə

# 2. Heap dump al (artımın pik anında)
jmap -dump:format=b,live,file=/tmp/heap.hprof <pid>

# 3. Heap dump analizi — Eclipse MAT (Memory Analyzer Tool)
# mat.sh /tmp/heap.hprof

# ─── Eclipse MAT ilə analiz ──────────────────────────────
# Leak Suspects Report → avtomatik leak şübhəlisi
# Dominator Tree → ən çox belleği tutan obyektlər
# Histogram → class-a görə obyekt sayı/ölçüsü
# Retained Heap → obyektin özü + tutduğu hamı

# ─── JVM GC log analizi ──────────────────────────────────
# JVM flags:
java -Xlog:gc*:file=/tmp/gc.log:time,uptime,level,tags:filecount=5,filesize=10m \
     -jar myapp.jar

# GC log analizi üçün: GCViewer, GCEasy.io

# ─── Heap dump — Actuator vasitəsilə ─────────────────────
# GET /actuator/heapdump → heap.hprof fayl download
curl -O http://localhost:8080/actuator/heapdump

# ─── Common memory leak patterns ─────────────────────────
```

```java
// ─── Memory Leak Antipatterns ─────────────────────────────

// 1. Static collection-da accumulate
class LeakyCache {
    // ❌ Yanlış: static map sonsuz böyüyür
    private static final Map<String, HeavyObject> cache = new HashMap<>();

    // ✅ Doğru: Guava Cache ya da Caffeine (eviction ilə)
    private static final Cache<String, HeavyObject> cache = Caffeine.newBuilder()
        .maximumSize(1000)
        .expireAfterWrite(1, TimeUnit.HOURS)
        .build();
}

// 2. Inner class — outer class reference tutur
class OuterClass {
    private byte[] largeArray = new byte[1024 * 1024];

    // ❌ Yanlış: Non-static inner class → outer-i tutur
    class InnerRunnable implements Runnable {
        public void run() { /* largeArray-yə çata bilər */ }
    }

    // ✅ Doğru: static inner class
    static class StaticInnerRunnable implements Runnable {
        public void run() { /* outer reference yoxdur */ }
    }
}

// 3. ThreadLocal — cleanup olmadan
class ThreadLocalLeak {
    // ❌ Yanlış: ThreadLocal-ı remove etmirəm
    private static final ThreadLocal<HeavyObject> contextHolder =
        new ThreadLocal<>();

    public void process() {
        contextHolder.set(new HeavyObject());
        try {
            doWork();
        } finally {
            contextHolder.remove(); // ✅ HƏMİŞƏ remove et!
        }
    }
}

// 4. Listener/Observer — deregister olmadan
class EventPublisher {
    private final List<EventListener> listeners = new ArrayList<>();

    // ❌ Yanlış: listener əlavə amma heç vaxt remove etmirik
    public void addListener(EventListener listener) {
        listeners.add(listener);
    }

    // ✅ Doğru: WeakReference ya da deregister imkanı
    private final List<WeakReference<EventListener>> weakListeners = new ArrayList<>();

    public void addWeakListener(EventListener listener) {
        weakListeners.add(new WeakReference<>(listener));
    }
}

// ─── Memory leak detection - Actuator ────────────────────
@Component
public class MemoryLeakDetector {

    private final MeterRegistry meterRegistry;

    @Scheduled(fixedDelay = 60_000)
    public void checkMemoryTrend() {
        Runtime runtime = Runtime.getRuntime();
        long usedMemory = runtime.totalMemory() - runtime.freeMemory();
        long maxMemory = runtime.maxMemory();
        double usagePercent = (double) usedMemory / maxMemory * 100;

        meterRegistry.gauge("jvm.memory.usage.percent", usagePercent);

        if (usagePercent > 90) {
            log.warn("Yüksək memory istifadəsi: {}%", String.format("%.1f", usagePercent));
            // Alert göndər
        }
    }
}
```

---

## İntervyu Sualları

### 1. JVM profiling alətlərini sadalayın.
**Cavab:** **Built-in**: `jps` (process list), `jstack` (thread dump), `jmap` (heap dump/stats), `jstat` (GC stats), `jcmd` (all-in-one), Java Flight Recorder (JFR, production-safe). **GUI**: VisualVM (pulsuz), JProfiler (commercial), YourKit (commercial). **Modern**: Async Profiler (sampling, production-safe, flamegraph). **APM**: Datadog, New Relic, Dynatrace (cloud-native). **Spring Boot**: Actuator endpoints — `/actuator/heapdump`, `/actuator/threaddump`, `/actuator/metrics`.

### 2. Sampling vs Instrumentation fərqi?
**Cavab:** **Sampling** — müəyyən aralıqlarla (N ms) stack trace götürür; overhead ~1-3%; statistik, tam dəqiq deyil; production-da istifadə edilə bilər. **Instrumentation** — hər metod girişi/çıxışını intercept edir; overhead 10-50%+; dəqiq nəticə; yalnız development/staging-də. Async Profiler sampling istifadə edir amma "safepoint bias" problemi yoxdur — VisualVM/JProfiler safepoint-lərdə snapshot alır, bu CPU-intensive kod-u gizlədə bilər.

### 3. Memory leak necə aşkar edilir?
**Cavab:** (1) `jstat -gcutil <pid>` ilə Old Gen-in tədricən dolduğunu izlə — GC-dən sonra bellek azalmırsa leak var. (2) Heap dump al: `jmap -dump:format=b,live,file=heap.hprof <pid>`. (3) Eclipse MAT ilə analiz: Leak Suspects Report, Dominator Tree, Histogram. (4) Common pattern-lər: static collection-da accumulate, ThreadLocal remove etmədən, listener deregister etmədən, non-static inner class. Actuator `/actuator/heapdump` endpoint-i ilə production-dan heap dump almaq mümkündür.

### 4. Java Flight Recorder (JFR) nədir?
**Cavab:** JDK 11+, OpenJDK-ya daxil edildi. Production-safe low-overhead profiling — ~1-2%. CPU, memory, GC, I/O, thread, exception, network əməliyyatları qeyd edir. `jcmd <pid> JFR.start duration=60s filename=recording.jfr` ilə başladılır. JDK Mission Control (JMC) ilə analiz edilir. Spring Boot Actuator-da `/actuator/jfr` endpoint-i ilə rahatlıqla başladılır. Safepoint-independent — Async Profiler kimi dəqiq CPU nümunələmə.

### 5. Micrometer nədir?
**Cavab:** JVM üçün application metrics façade — SLF4J-nin metrics analoqudur. Arxitektura: Micrometer API → backend adapter (Prometheus, Datadog, CloudWatch, InfluxDB). Spring Boot Actuator Micrometer ilə inteqrasiyalıdır. Əsas metric tipi: Counter (monoton artım), Gauge (anlıq dəyər), Timer (süre + P50/P95/P99), DistributionSummary (histogram). `@Timed` annotation ilə metod müddəti avtomatik ölçülür. `/actuator/prometheus` endpoint-i Prometheus scraping üçün format verir, Grafana dashboards ilə görselləşdirilir.

*Son yenilənmə: 2026-04-10*
