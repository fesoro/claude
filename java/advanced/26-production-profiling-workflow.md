# Production Profiling Workflow (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐

## İcmal

Bu fayl simptomdan başlayıb kök səbəbə çatmaq üçün addım-addım production profiling playbook-udur. `core/79-jvm-profiling-tools.md` tool referansını tamamlayır — burada fokus "nə görürsən → nə etməlisinisən" üzərindədir.

---

## Simptom 1: Yüksək CPU İstifadəsi

```bash
# Addım 1: Hansı Java prosesi CPU yeyir?
top -p $(pgrep java)
# yaxud
ps aux --sort=-%cpu | grep java

# Addım 2: Thread-lərin CPU istifadəsini gör
top -H -p <pid>   # -H = thread mode
# Thread PID-lərini qeyd et (onları decimal-dan hex-ə çevir)

# Addım 3: Thread dump al
jcmd <pid> Thread.print > /tmp/thread-dump.txt

# Addım 4: CPU-da olan thread-i tap
# top -H-dən tid=12345 → hex: 0x3039
grep -A 20 "nid=0x3039" /tmp/thread-dump.txt

# Addım 5: Flamegraph ilə hotspot tap
./profiler.sh -d 30 -f /tmp/cpu-flame.html <pid>
# Browser-da aç, ən geniş hissə = bottleneck
```

**Tez-tez görülən səbəblər:**

```
Flamegraph-da geniş JSON parse metodu → serializasiya bottleneck
  → Həll: Jackson ObjectMapper-i @Bean et (reuse), parallel parsing

Garbage Collector-un özü CPU-da (GC thread-lər)
  → Həll: jstat -gcutil <pid> → Old Gen 90%+ → heap artır, leak var
  → bax: Simptom 3 (Memory Leak)

Regex matching
  → Həll: Pattern.compile() static-da cache et
  → java.util.regex.Pattern iç görünürsə flamegraph-da

String concatenation loop-da
  → Həll: StringBuilder istifadə et
```

---

## Simptom 2: Yüksək Latency / Yavaş Response

```bash
# Addım 1: Hansı endpoint yavaşdır?
# Spring Boot Actuator metrics
curl http://localhost:8080/actuator/metrics/http.server.requests

# Konkret endpoint
curl "http://localhost:8080/actuator/metrics/http.server.requests?\
tag=uri:/api/orders&tag=method:GET"

# Addım 2: Thread dump — thread-lər nədə gözləyir?
jcmd <pid> Thread.print | grep -A 5 "WAITING\|TIMED_WAITING\|BLOCKED"

# Addım 3: DB query-ləri yavaşdırırmı?
# HikariCP metrics
curl "http://localhost:8080/actuator/metrics/hikaricp.connections.pending"
curl "http://localhost:8080/actuator/metrics/hikaricp.connections.acquire"

# Addım 4: Async Profiler — wall clock mode (I/O dahil)
./profiler.sh -e wall -d 30 -f /tmp/wall-flame.html <pid>
# CPU mode-dan fərqli: I/O, sleep, lock wait da görünür
```

**Checklist:**

```
□ N+1 query? → Hibernate statistics-i aktiv et
  spring.jpa.properties.hibernate.generate_statistics=true
  logging.level.org.hibernate.stat=DEBUG

□ DB connection pool dolu?
  hikaricp.connections.pending > 0 → pool size artır yaxud query-lər yavaş

□ Cache miss çox?
  → Cache hit rate metrics-ə bax
  → Cache eviction sürətlə baş verirmi?

□ External API call-lar?
  → WebClient/RestClient timeout qurulubmu?
  → Circuit breaker açılıbmı?
```

---

## Simptom 3: Memory Artımı / Leak Şübhəsi

```bash
# Addım 1: Heap trend-ini izlə
jstat -gcutil <pid> 5000 60   # 5s-dən bir, 60 dəfə

# Output:
# S0  S1  E   O     M     YGC  YGCT  FGC  FGCT
# 0   48  62  85.2  97.1    4  0.123  1   1.234
#                   ↑↑↑
# Old Gen 85% → GC-dən sonra azalmırsa → leak!

# Addım 2: Heap dump al (pik anında)
jcmd <pid> GC.heap_dump /tmp/heap.hprof
# yaxud Actuator vasitəsilə (production-da daha rahat)
curl -O http://localhost:8080/actuator/heapdump

# Addım 3: Allocation profiling — nə yaradan çoxdur?
./profiler.sh -e alloc -d 60 -f /tmp/alloc-flame.html <pid>

# Addım 4: Eclipse MAT analizi
# mat.sh /tmp/heap.hprof
# → "Leak Suspects" report → avtomatik ən ehtimal olunan leak
# → "Dominator Tree" → ən çox heap tutan obyektlər
```

**Tez-tez görülən leak-lər:**

```java
// 1. Static cache — eviction yoxdur
private static final Map<String, byte[]> fileCache = new HashMap<>();
// → Caffeine/Guava Cache ilə əvəzlə, maximumSize + expireAfterAccess

// 2. ThreadLocal — remove edilmir
private static ThreadLocal<Connection> connHolder = new ThreadLocal<>();
// → try-finally blokda connHolder.remove()

// 3. Listener əlavə edilir, silinmir
eventBus.register(listener);    // əlavə
// eventBus.unregister(listener) unudulub

// 4. Hibernate session-da çox entity yükləyir
// "SELECT u FROM User u" → 1 milyon user ram-a
// → Pagination, projection, stream istifadə et

// 5. Log4j/SLF4J lazy string — GC friendly yox
logger.debug("Order: " + order.toString()); // hər zaman toString() çağrılır
// → logger.debug("Order: {}", order)  — yalnız debug aktiv olanda
```

---

## Simptom 4: Deadlock / Thread Contention

```bash
# Addım 1: Deadlock var mı?
jcmd <pid> Thread.print | grep -A 20 "deadlock"
# yaxud
jstack <pid> | grep "Found.*deadlock"

# Addım 2: Lock contention profiling
./profiler.sh -e lock -d 30 -f /tmp/lock-flame.html <pid>
# Ən çox gözlənilən lock-lar görünür

# Addım 3: BLOCKED thread-ləri tap
jcmd <pid> Thread.print | grep "BLOCKED" -A 10
```

**Thread dump deadlock nümunəsi:**

```
Found one Java-level deadlock:
=============================
"Thread-1":
  waiting to lock monitor 0x00007f... (object 0x... java.util.ArrayList)
  which is held by "Thread-2"
"Thread-2":
  waiting to lock monitor 0x00007f... (object 0x... java.util.HashMap)
  which is held by "Thread-1"

Analiz:
  Thread-1 ArrayList-i tutub HashMap-i gözləyir
  Thread-2 HashMap-i tutub ArrayList-i gözləyir
  → Lock order-i standartlaşdır
  → Ya da lock-ları eyni vaxtda tutma
```

---

## JFR ilə Continuous Profiling

```bash
# Production-da daima çalışan JFR (1-2% overhead)
java -XX:StartFlightRecording=\
    delay=20s,\
    duration=0,\               # sonsuz
    name=continuous,\
    settings=profile,\         # daha ətraflı
    filename=/tmp/app.jfr,\
    maxsize=500m,\             # disk limiti
    maxage=1h \                # köhnə data silinir
    -jar myapp.jar

# Hadisə baş verdikdə son 1 saatı çıxar
jcmd <pid> JFR.dump name=continuous filename=/tmp/incident.jfr maxage=30m
```

```java
// Spring Boot Actuator JFR endpoint
management:
  endpoints:
    web:
      exposure:
        include: jfr

# POST /actuator/jfr/start — recording başlat
# GET  /actuator/jfr      — dump al
```

---

## Production Profiling Qaydaları

**Nə etmə:**
```
❌ Instrumentation profiler-i production-da çalışdırma (50%+ overhead)
❌ jmap -dump olmadan jmap -histo:live çalışdırma (STW GC trigger edir)
❌ heap dump-ı application serveri üzərindən sil (disk dolursa)
❌ Production JVM-ə external javaagent attach et (restart tələb edir)
```

**Nə et:**
```
✅ Async Profiler sampling mode — ~1% overhead, production-safe
✅ JFR continuous recording — ~1-2% overhead, həmişə hazır
✅ Actuator /heapdump, /threaddump endpoint-ləri — hazır gəlir
✅ Prometheus + Grafana — anomaliya baş verəndə bildiriş al, sonra investigate et
✅ Staging-də ilk reproduce etməyə çalış — production-a toxunma
```

---

## İntervyu Sualları

### 1. Production-da yüksək CPU gördünsə nə edərsən?
**Cavab:** Triage sırası: (1) `top -H -p <pid>` — hansı thread CPU-dadır. (2) Thread dump — `jcmd <pid> Thread.print` — thread nə edir. (3) Async Profiler CPU mode — `profiler.sh -d 30 -f flame.html <pid>` — flamegraph ilə hotspot tapılır. Tez-tez: JSON serializasiya, regex, String concatenation, GC overhead (heap leak simptom olur), lock contention.

### 2. Memory leak-i production-da necə aşkar edirsən?
**Cavab:** (1) `jstat -gcutil <pid> 5000` — Old Gen GC-dən sonra azalmırsa leak var. (2) Heap dump: `curl http://localhost:8080/actuator/heapdump` — production üçün rahat. (3) Eclipse MAT — "Leak Suspects" report. (4) Async Profiler alloc mode — allocation hot-path tapılır. Common leak-lər: static Map-də eviction yox, ThreadLocal remove edilmir, listener unregister unudulur.

### 3. JFR vs Async Profiler nə zaman?
**Cavab:** JFR — JVM built-in, production continuous recording üçün (həmişə açıq), CPU + memory + GC + I/O + network bir yerdə. JDK Mission Control ilə analiz. Async Profiler — daha dəqiq CPU sampling (safepoint bias yoxdur), flamegraph formatı daha oxunaqlı, allocation profiling daha ətraflı. Adətən birlikdə: JFR həmişə çalışır, Async Profiler konkret investigation üçün.

*Son yenilənmə: 2026-04-27*
