# 77 — JVM GC Tuning

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
1. [Heap Ölçüləndirməsi](#heap-ölçüləndirməsi)
2. [GC Seçimi Flag-ləri](#gc-seçimi-flag-ləri)
3. [G1GC Tuning](#g1gc-tuning)
4. [GC Log Analizi](#gc-log-analizi)
5. [Memory Leak Aşkarlanması](#memory-leak-aşkarlanması)
6. [Heap Dump](#heap-dump)
7. [Monitoring Alətləri](#monitoring-alətləri)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Heap Ölçüləndirməsi

### Əsas Heap Flag-ləri

```bash
# Heap ölçüsü
-Xms512m          # Başlanğıc heap ölçüsü (min)
-Xmx2g            # Maksimum heap ölçüsü
-Xmn256m          # Young Generation ölçüsü (ms=xmn anlamında)

# Tövsiyə: -Xms = -Xmx (sabit heap — avtomatik resize overhead-i yox)
java -Xms2g -Xmx2g MyApp

# Young/Old nisbəti
-XX:NewRatio=2    # Old:Young = 2:1 → Young = heap/3 (default)
-XX:NewRatio=3    # Old:Young = 3:1 → Young = heap/4

# Survivor nisbəti
-XX:SurvivorRatio=8  # Eden:S0:S1 = 8:1:1 (default)
# Eden = Young * 8/10 = 80%

# Tenuring threshold
-XX:MaxTenuringThreshold=15  # Neçə GC-dən sonra Old Gen-ə (default: 15)
```

```java
public class HeapSizingExample {
    /*
     * Heap Ölçüsünü Necə Hesablamaq?
     *
     * 1. Live data ölçüsünü tap:
     *    - Full GC-dən sonra Old Gen-də nə qədər data var?
     *    - Bu "live set" adlanır
     *
     * 2. Qayda:
     *    - Young Gen = live set * 1-1.5x (az GC pauzası üçün)
     *    - Old Gen = live set * 3-4x (GC çox geniş vaxt tapsın)
     *    - Total Heap = Young + Old
     *
     * 3. Nümunə:
     *    Live set = 512MB
     *    Young Gen = 512MB (1x)
     *    Old Gen = 2048MB (4x)
     *    Total = 2560MB → -Xmx3g kimi round etmək
     */
    public static void main(String[] args) {
        Runtime rt = Runtime.getRuntime();
        System.out.printf("Max heap: %d MB%n", rt.maxMemory() / 1024 / 1024);
        System.out.printf("Total heap: %d MB%n", rt.totalMemory() / 1024 / 1024);
        System.out.printf("Free heap: %d MB%n", rt.freeMemory() / 1024 / 1024);
        System.out.printf("Used heap: %d MB%n",
            (rt.totalMemory() - rt.freeMemory()) / 1024 / 1024);
    }
}
```

### Stack Ölçüsü

```bash
# Hər thread üçün stack ölçüsü
-Xss512k    # 512KB (default 512KB-1MB, OS-ə görə dəyişir)
-Xss2m      # 2MB — dərin rekursiya üçün

# Thread sayı * stack ölçüsü = native memory
# 1000 thread * 1MB stack = 1GB native memory!
```

### Metaspace

```bash
# Metaspace
-XX:MetaspaceSize=128m        # İlkin Metaspace ölçüsü (GC tetikleme threshold-u)
-XX:MaxMetaspaceSize=512m     # Maksimum Metaspace ölçüsü
# Default: unlimited — problematik ola bilər!

# JVM flag-larını yoxlamaq
java -XX:+PrintFlagsFinal -version | grep MetaspaceSize
```

---

## GC Seçimi Flag-ləri

```bash
# GC seçimi
-XX:+UseSerialGC        # Serial GC
-XX:+UseParallelGC      # Parallel GC (Throughput)
-XX:+UseG1GC            # G1GC (default Java 9+)
-XX:+UseZGC             # ZGC (Java 15+ production)
-XX:+UseShenandoahGC    # Shenandoah GC
-XX:+UseEpsilonGC       # Epsilon (no-op) GC

# Hansı GC istifadə olunduğunu yoxlamaq
java -XX:+PrintFlagsFinal -version | grep -E "Use.*GC"
```

---

## G1GC Tuning

G1GC-nin tuning-i minimal olmalıdır — əvvəlcə default-larla sına!

### Əsas G1GC Flag-ləri

```bash
java -XX:+UseG1GC \
     -Xms4g \
     -Xmx8g \
     \
     # Hədəf maks pauz (ms) — ən mühim flag!
     -XX:MaxGCPauseMillis=200 \
     \
     # Region ölçüsü (1,2,4,8,16,32 MB — 2-nin qüvvəti olmalı)
     # Heap/2048 ~ optimal region ölçüsü
     # 8GB heap → 4MB region
     -XX:G1HeapRegionSize=4m \
     \
     # Concurrent marking-i başlatmaq üçün heap dolulluq faizi
     # Daha kiçik → daha erkən başlar (daha az tam GC riski)
     # Daha böyük → daha az GC, amma Full GC riski artır
     -XX:InitiatingHeapOccupancyPercent=45 \
     \
     # Mixed GC-lərin sayı
     -XX:G1MixedGCCountTarget=8 \
     \
     # GC concurrent thread-ləri
     -XX:ConcGCThreads=4 \
     \
     # Old region-ların minimum dolulluq faizi (temizlənmək üçün)
     -XX:G1HeapWastePercent=5 \
     MyApp
```

### G1GC Tuning Ssenariləri

```java
public class G1GCTuningScenarios {

    /*
     * Ssenari 1: Çox sayda Full GC
     * Problem: Old Gen çox tez dolur
     * Həll:
     *   - -Xmx artır
     *   - -XX:InitiatingHeapOccupancyPercent azalt (30-35)
     *   - Concurrent GC thread-lərini artır: -XX:ConcGCThreads=8
     *
     * Ssenari 2: Uzun STW pauzaları
     * Problem: -XX:MaxGCPauseMillis hədəfi tutulmur
     * Həll:
     *   - Young Gen ölçüsünü azalt: -XX:G1NewSizePercent=5
     *   - Mixed GC sayını artır: -XX:G1MixedGCCountTarget=16
     *
     * Ssenari 3: Çox sayda Humongous allocation
     * Problem: Region ölçüsünün yarısından böyük obyektlər
     * Həll:
     *   - Region ölçüsünü artır: -XX:G1HeapRegionSize=16m (ya da 32m)
     *   - Böyük massivlər üçün pool istifadə et
     *
     * Ssenari 4: Yüksək GC overhead
     * Problem: GC vaxtın çox hissəsini yeyir
     * Həll:
     *   - Heap artır
     *   - Qısamüddətli obyekt yaratmağı azalt
     *   - Object pooling tətbiq et
     */

    public static void main(String[] args) {
        System.out.println("G1GC Tuning Ssenarisi");
    }
}
```

---

## GC Log Analizi

```bash
# Java 11+ GC logging
java -Xlog:gc*:file=gc.log:time,uptime,level,tags:filecount=5,filesize=20m MyApp

# Müxtəlif detallar
java -Xlog:gc                           # Ən az detal
java -Xlog:gc*                          # Geniş detal
java -Xlog:gc+heap=debug                # Heap detalları
java -Xlog:gc*:stdout                   # Stdout-a çıxar (fayl əvəzinə)

# Java 8 (köhnə format)
java -XX:+PrintGCDetails \
     -XX:+PrintGCDateStamps \
     -XX:+PrintGCTimeStamps \
     -Xloggc:gc.log \
     -XX:+UseGCLogFileRotation \
     -XX:NumberOfGCLogFiles=5 \
     -XX:GCLogFileSize=20m \
     MyApp
```

### GC Log Formatını Oxumaq

```
# G1GC log nümunəsi (Java 11+):
[2024-01-15T10:30:45.123+0000][0.456s][info][gc] GC(0) Pause Young (Normal) (G1 Evacuation Pause) 64M->12M(256M) 23.456ms
                                                          ↑       ↑                                  ↑  ↑  ↑     ↑
                                                       GC nömrəsi  Tipi                            Əvv Sonra Max  Vaxt

# Açıqlama:
# GC(0)       = 0-cı GC
# Pause Young = Young GC (Minor GC)
# Normal      = Normal tetikləmə (Allocation Failure deyil)
# 64M->12M    = GC-dən əvvəl 64MB, sonra 12MB istifadə
# (256M)      = Heap ölçüsü
# 23.456ms    = GC pauz müddəti
```

```java
// GC logunu proqramatik analiz etmək
import java.io.*;
import java.util.regex.*;

public class GCLogAnalyzer {

    record GCEvent(long timestampMs, long pauseMs, long beforeMB, long afterMB) {}

    public static void main(String[] args) throws IOException {
        // Sadə regex ilə pause müddətlərini çıxart
        Pattern pattern = Pattern.compile(
            "GC\\(\\d+\\) Pause.*?(\\d+)M->(\\d+)M\\(\\d+M\\) (\\d+\\.\\d+)ms"
        );

        // Realdə GCEasy.io, GCViewer, JMC kimi alətlər daha yaxşıdır
        System.out.println("GC logunu analiz et:");
        System.out.println("- GCEasy.io (onlayn, pulsuz)");
        System.out.println("- GCViewer (açıq mənbə)");
        System.out.println("- JDK Mission Control (JMC)");
    }
}
```

### GCEasy.io ilə Analiz

```
GCEasy.io-ya gc.log yüklə:
→ Throughput (%)
→ GC pauz statistikaları (avg, max, p95, p99)
→ Heap istifadəsi qrafiki
→ GC növləri (Minor/Major/Full) sayı
→ Problemlər (memory leak indikatorları)
→ Tövsiyələr
```

---

## Memory Leak Aşkarlanması

### Memory Leak Əlamətləri

```
1. Heap istifadəsi get-gedə artır (GC-dən sonra da azalmır)
2. Full GC tezliyi artır
3. OutOfMemoryError (nəhayət)
4. Tətbiq get-gedə yavaşlayır
```

### Ümumi Memory Leak Səbəbləri

```java
import java.util.*;
import java.util.concurrent.*;

public class MemoryLeakExamples {

    // PROBLEM 1: Statik kolleksiyaya əlavə etmək, silməmək
    private static final Map<String, Object> CACHE = new HashMap<>();

    public static void badCache(String key, Object value) {
        CACHE.put(key, value); // Heç vaxt silinmir → Memory Leak!
    }

    // DOĞRU: TTL-li WeakReference cache
    private static final Map<String, java.lang.ref.WeakReference<Object>> WEAK_CACHE
        = new WeakHashMap<>();

    public static void goodCache(String key, Object value) {
        WEAK_CACHE.put(key, new java.lang.ref.WeakReference<>(value));
    }


    // PROBLEM 2: Listener-i qeydiyyatdan çıxarmamaq
    static class EventBus {
        private static final List<Runnable> listeners = new ArrayList<>();
        static void register(Runnable l) { listeners.add(l); }
        // deregister metodu yoxdur!
    }

    public static void badListener() {
        // Hər çağırışda yeni listener əlavə olunur, heç biri silinmir
        EventBus.register(() -> System.out.println("event"));
    }

    // DOĞRU: Listener-i deregister et
    static class GoodEventBus {
        private static final Set<Runnable> listeners = new HashSet<>();
        static void register(Runnable l) { listeners.add(l); }
        static void deregister(Runnable l) { listeners.remove(l); } // Vacib!
    }


    // PROBLEM 3: ThreadLocal-i təmizləməmək (thread pool ilə)
    private static final ThreadLocal<byte[]> THREAD_LOCAL = new ThreadLocal<>();

    public static void badThreadLocal() {
        THREAD_LOCAL.set(new byte[1024 * 1024]); // 1MB
        // İş bitdi, amma thread pool thread-i geri verir
        // ThreadLocal hələ dolu! Thread pool-un thread-ləri heç vaxt ölmür
    }

    public static void goodThreadLocal() {
        THREAD_LOCAL.set(new byte[1024 * 1024]);
        try {
            // İş gör
        } finally {
            THREAD_LOCAL.remove(); // Həmişə təmizlə!
        }
    }


    // PROBLEM 4: Inner class-ın outer class-a implicit istinad
    class InnerClass {
        // Inner class həmişə OuterClass-a istinad saxlayır (implicit this)
        // Əgər InnerClass uzun yaşasa, OuterClass da GC-dən xilas olur
    }

    // DOĞRU: Static nested class istifadə et
    static class StaticNestedClass {
        // Outer class-a istinad yoxdur
    }
}
```

### Heap Analizi ilə Memory Leak Tapmaq

```bash
# 1. Tətbiqin PID-ini tap
jps -l
# 12345 com.example.MyApp

# 2. Heap dump al
jmap -dump:format=b,file=heap.hprof 12345
# Ya da:
jcmd 12345 GC.heap_dump /tmp/heap.hprof

# 3. OOM zamanı avtomatik heap dump
java -XX:+HeapDumpOnOutOfMemoryError \
     -XX:HeapDumpPath=/tmp/heap_dump.hprof \
     MyApp

# 4. Eclipse MAT (Memory Analyzer Tool) ilə analiz
# - Leak Suspects Report
# - Dominator Tree (hansı obyekt ən çox yaddaş tutur)
# - Histogram (sinif bazında sayım)
```

---

## Heap Dump

```bash
# jmap ilə heap dump
jmap -dump:live,format=b,file=live_heap.hprof <pid>
# live — yalnız canlı obyektlər (GC-dən sonra)
# format=b — binary format

# jcmd ilə (daha müasir)
jcmd <pid> GC.heap_dump filename=/tmp/heap.hprof

# OOM zamanı avtomatik
-XX:+HeapDumpOnOutOfMemoryError
-XX:HeapDumpPath=/var/dumps/

# Heap statistikası (dump olmadan)
jmap -histo <pid>        # Cari histogram (bütün obyektlər)
jmap -histo:live <pid>   # GC-dən sonra canlı obyektlər
```

```java
// Kod içindən heap dump almaq (MBean vasitəsilə)
import com.sun.management.HotSpotDiagnosticMXBean;
import java.lang.management.ManagementFactory;

public class HeapDumpUtil {

    public static void dumpHeap(String filePath, boolean liveOnly) throws Exception {
        HotSpotDiagnosticMXBean mxBean = ManagementFactory.getPlatformMXBean(
            HotSpotDiagnosticMXBean.class
        );
        mxBean.dumpHeap(filePath, liveOnly);
        System.out.println("Heap dump saxlandı: " + filePath);
    }

    public static void main(String[] args) throws Exception {
        // Bəzi obyektlər yarat
        java.util.List<byte[]> list = new java.util.ArrayList<>();
        for (int i = 0; i < 100; i++) {
            list.add(new byte[1024]);
        }

        // Heap dump al
        dumpHeap("/tmp/myheap.hprof", true);
    }
}
```

---

## Monitoring Alətləri

### jstat — JVM Statistikaları

```bash
# jstat — real-time JVM statistikaları
jstat -gc <pid> 1000         # Hər 1000ms bir GC statistikası
jstat -gcutil <pid> 1000     # Faizlə GC statistikaları
jstat -gccapacity <pid>      # Heap capacity məlumatları
jstat -gcnew <pid> 1000      # Young Gen statistikaları
jstat -gcold <pid> 1000      # Old Gen statistikaları
jstat -compiler <pid>        # JIT compilation statistikaları
jstat -class <pid>           # ClassLoader statistikaları

# jstat -gcutil nümunə çıxış:
#   S0     S1     E      O      M     CCS    YGC     YGCT    FGC    FGCT     GCT
#    0.00  33.33  55.12  45.23  95.12  90.05     15    0.234     2    0.345   0.579
#    ↑      ↑     ↑      ↑      ↑      ↑       ↑     ↑       ↑     ↑       ↑
#   S0%   S1%   Eden%  Old%  Meta%  Compr.  YGC sayı YGC vaxt FGC sayı FGC vaxt Ümumi
```

### jcmd — Universal Alət

```bash
# jcmd — müasir alət, həm info həm command
jcmd <pid> help              # Mövcud komandalar
jcmd <pid> VM.version        # JVM versiyası
jcmd <pid> VM.flags          # Aktiv JVM flag-ləri
jcmd <pid> GC.run            # GC tetiklə
jcmd <pid> GC.heap_info      # Heap məlumatı
jcmd <pid> Thread.print      # Thread dump
jcmd <pid> VM.native_memory  # Native memory (NMT lazımdır)
jcmd <pid> JFR.start         # Java Flight Recorder başlat
```

### jps — JVM Prosesləri

```bash
jps -l    # Bütün Java prosesləri (PID + main class)
jps -v    # JVM arqumentləri ilə
jps -m    # Main metod arqumentləri ilə
```

### JVisualVM

```bash
# JVisualVM — qrafik interfeysi olan monitoring
# JDK ilə gəlir (Java 8 üçün), ya da https://visualvm.github.io/

jvisualvm   # Başlatmaq

# Xüsusiyyətlər:
# - Real-time heap, CPU, thread monitoring
# - Heap dump və analiz
# - CPU və Memory profiling
# - GC davranış qrafiki
# - Remote process monitoring (JMX üzərindən)
```

### JDK Mission Control (JMC) + Java Flight Recorder (JFR)

```bash
# Java Flight Recorder — aşağı overhead-li profiling (< 1% overhead)
# Prodüksiya mühitlərini real vaxtda analiz etmək üçün ideal

# JFR başlatmaq (tətbiq işləyərkən)
jcmd <pid> JFR.start duration=60s filename=/tmp/recording.jfr

# Ya da JVM başladarkən
java -XX:StartFlightRecording=duration=60s,filename=/tmp/rec.jfr MyApp

# JMC ilə .jfr faylını açmaq:
jmc   # Mission Control başlat, sonra .jfr faylını aç

# JFR nə göstərir:
# - GC events (pauz müddətləri, tövsiyələr)
# - CPU istifadəsi (method profiling)
# - I/O events
# - Lock contention
# - Object allocation (hansı sinif nə qədər)
# - Exception-lar
```

```java
// JFR-i kod içindən idarə etmək (Java 14+)
import jdk.jfr.*;
import java.nio.file.*;
import java.time.Duration;

public class JFRProgrammaticControl {

    // Özəl JFR event yaratmaq
    @Name("com.example.MyCustomEvent")
    @Label("Mənim Xüsusi Eventim")
    @Category("Tətbiq")
    static class MyCustomEvent extends Event {
        @Label("Sorğu")
        String query;

        @Label("Müddət (ms)")
        long durationMs;
    }

    public static void main(String[] args) throws Exception {
        // Recording başlatmaq
        Configuration config = Configuration.getConfiguration("default");
        try (Recording recording = new Recording(config)) {
            recording.start();

            // Xüsusi event qeyd etmək
            MyCustomEvent event = new MyCustomEvent();
            event.begin();
            Thread.sleep(100); // İş simülyasiyası
            event.query = "SELECT * FROM users";
            event.durationMs = 100;
            event.commit(); // Event-i qeyd et

            // Digər işlər...

            recording.stop();
            recording.dump(Path.of("/tmp/custom.jfr"));
        }
    }
}
```

### GC Tuning Prosesi

```
1. ÖLÇMƏ (Baseline)
   → GC log-larını aktivləşdir
   → JFR recording al (30-60 dəqiqə)
   → Metric-ləri topla: throughput, pauz müddəti, GC tezliyi

2. ANALİZ
   → GCEasy.io ilə log analizi
   → Problemi müəyyən et: Uzun pauzlar? Çox GC? Full GC?
   → Heap histogramı bax

3. DƏYİŞİKLİK
   → Bir flag dəyiş (eyni anda çox dəyişmə!)
   → -Xmx? MaxGCPauseMillis? Region size?

4. YENİDƏN ÖLÇMƏ
   → Eyni yük altında yenidən ölç
   → Əvvəlki baseline ilə müqayisə et

5. QƏRAR
   → Yaxşılaşdısa saxla
   → Xeyr isə geri qaytar
   → Növbəti dəyişikliyə keç
```

---

## İntervyu Sualları

**S: `-Xms` və `-Xmx`-i eyni dəyərə qoymaq niyə tövsiyə olunur?**
C: JVM heap-i `-Xms`-dən başlayıb ehtiyaca görə `-Xmx`-ə qədər artırır. Bu resize əməliyyatı Full GC tələb edir. Eyni dəyər verməklə bu overhead aradan qalxır. Prodüksiya serverlərində sabit heap daha yaxşıdır.

**S: G1GC-nin ən mühim tuning flag-i hansıdır?**
C: `-XX:MaxGCPauseMillis`. Bu, GC-nin hədəf aldığı maksimum pauz müddətidir. G1GC bu hədəfə çatmaq üçün Young Gen ölçüsünü, Mixed GC sıxlığını avtomatik tənzimləyir.

**S: Memory leak-i necə aşkarlarsınız?**
C: 1) Heap istifadəsi GC-dən sonra azalmır (artmağa davam edir). 2) Heap dump al (jmap/jcmd). 3) MAT ilə "Leak Suspects" analizi. 4) Dominator tree-yə bax — hansı obyekt ən çox yaddaş tutur. 5) Statik kolleksiyaları, listener-ları, ThreadLocal-ları yoxla.

**S: `jstat -gcutil` çıxışındakı sütunlar nəyi göstərir?**
C: S0/S1 (Survivor space dolulluq faizi), E (Eden faizi), O (Old Gen faizi), M (Metaspace faizi), YGC/YGCT (Minor GC sayı/vaxtı), FGC/FGCT (Full GC sayı/vaxtı), GCT (ümumi GC vaxtı).

**S: Java Flight Recorder-in üstünlüyü nədir?**
C: JFR production-grade profiling alətidir, overhead-i < 1%-dir. Method profiling, GC analizi, lock contention, I/O events, allocation tracking — hamısını toplamaq mümkündür. Tətbiqi dayandırmadan real vaxtda istifadə oluna bilər.

**S: Humongous allocation nədir və necə həll olunur?**
C: G1GC-də region ölçüsünün yarısından böyük obyektlər. Bunlar birbaşa Old Gen-ə gedir, GC-dən az effektiv istifadə edilir. Həlli: `-XX:G1HeapRegionSize`-ı artırmaq, ya da böyük massivlər üçün pool istifadə etmək.

**S: `InitiatingHeapOccupancyPercent` nədir?**
C: G1GC-nin Concurrent Marking Cycle-ı başlatmağı üçün heap dolulluq faizi (default: 45%). Azaltmaq → daha erkən marking başlar, Full GC riski azalır. Artırmaq → daha az GC, amma Old Gen daşma riski artır.
