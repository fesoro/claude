# 76 — JVM GC Alqoritmləri

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Serial GC](#serial-gc)
2. [Parallel GC](#parallel-gc)
3. [G1GC (Garbage-First)](#g1gc-garbage-first)
4. [ZGC](#zgc)
5. [Shenandoah GC](#shenandoah-gc)
6. [Epsilon GC](#epsilon-gc)
7. [Hansını Seçməli?](#hansını-seçməli)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Serial GC

**Serial GC** — ən sadə GC implementasiyası. Tək thread-də işləyir.

```
Minor GC:  [STW ──── single thread GC ────] resume
Major GC:  [STW ──── single thread GC ──────────────] resume
```

### Xüsusiyyətlər
- Yalnız 1 GC thread-i
- Minor GC: Young Gen-i Copying alqoritmi ilə
- Major GC: Old Gen-i Mark-Sweep-Compact ilə
- Sadə, az overhead
- Çox thread-li mühitdə pisdir

```bash
# Serial GC aktivləşdirmək
java -XX:+UseSerialGC MyApp

# Nə zaman istifadə etməli?
# - Tək CPU-lu mühitlər
# - Kiçik heap (< 100MB)
# - Embedded sistemlər
# - Mikrokontrolerlər
```

```java
public class SerialGCDemo {
    public static void main(String[] args) {
        // Serial GC-nin davranışını göstərmək üçün
        // java -XX:+UseSerialGC -Xmx64m -verbose:gc SerialGCDemo

        for (int i = 0; i < 100; i++) {
            // Kiçik obyektlər yarat — Eden-i doldur
            byte[] data = new byte[512 * 1024]; // 512KB
            // data referansı döngünün sonunda keçərsiz olur
        }
        System.out.println("Tamamlandı");
    }
}
```

---

## Parallel GC

**Parallel GC** — çoxlu GC thread-ləri, maksimum throughput.

```
Minor GC:  [STW ─┬─ GC Thread 1 ─┬─] resume
                 ├─ GC Thread 2 ─┤
                 ├─ GC Thread 3 ─┤
                 └─ GC Thread 4 ─┘

Major GC:  [STW ─┬─ GC Thread 1 ──────┬─] resume
                 ├─ GC Thread 2 ──────┤
                 └─ GC Thread N ──────┘
```

### Xüsusiyyətlər
- Çoxlu GC thread-ləri (default: CPU sayı)
- Serial GC-dən daha qısa pauzalar
- Throughput üçün optimallaşdırılmış
- Serial kimi — STW pauzaları uzun ola bilər
- Java 8-də default GC idi

```bash
# Parallel GC aktivləşdirmək
java -XX:+UseParallelGC MyApp

# GC thread sayını dəyişmək
java -XX:+UseParallelGC -XX:ParallelGCThreads=8 MyApp

# Throughput məqsədi (GC-nin vaxtının maksimum bu qədər faiz olması)
java -XX:+UseParallelGC -XX:GCTimeRatio=99 MyApp
# GC max %1 vaxt sərf etsin (throughput = %99)

# Nə zaman istifadə etməli?
# - Batch processing
# - CPU-intensive tətbiqlər
# - Throughput > latency olduqda
```

```java
import java.util.ArrayList;
import java.util.List;

public class ParallelGCBenchmark {
    public static void main(String[] args) {
        // Parallel GC throughput baxımından yaxşıdır
        // java -XX:+UseParallelGC -Xmx512m ParallelGCBenchmark

        long start = System.currentTimeMillis();

        List<List<Integer>> bigData = new ArrayList<>();
        for (int i = 0; i < 1000; i++) {
            List<Integer> chunk = new ArrayList<>();
            for (int j = 0; j < 10000; j++) {
                chunk.add(i * j); // Çox obyekt yarat
            }
            bigData.add(chunk);
            if (i % 100 == 99) {
                bigData.clear(); // GC üçün uyğun hala gətir
            }
        }

        long elapsed = System.currentTimeMillis() - start;
        System.out.println("Vaxt: " + elapsed + "ms");
    }
}
```

---

## G1GC (Garbage-First)

**G1GC** — Java 9-dan bəri default GC. Böyük heap-lər üçün nəzərdə tutulub. Heap-i bərabər ölçülü **region**-lara bölür.

### Region-lara Bölünmə

```
G1GC Heap (məsələn 4GB, region ölçüsü 2MB → 2048 region):

┌─────────────────────────────────────────────────────────┐
│  E  │  E  │  S  │  O  │  O  │  O  │  F  │  F  │  H  ...│
│ Eden│ Eden│Surv.│ Old │ Old │ Old │Free │Free │Hum. ...│
├─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────   │
│  O  │  F  │  E  │  O  │  S  │  F  │  O  │  H  │  H  ...│
│ Old │Free │Eden │ Old │Surv.│Free │ Old │Hum. │Hum. ...│
└─────────────────────────────────────────────────────────┘

E = Eden region
S = Survivor region
O = Old region
F = Free region
H = Humongous region (çox böyük obyektlər üçün)
```

### G1GC-nin Fazaları

**Young-only Phase:**
```
Eden regionları dolduqda:
→ Concurrent Marking başlayır (parallel, application thread-lərlə)
→ STW Evacuation: Eden + Survivor → Yeni Survivor + Old
```

**Mixed Phase:**
```
→ Old regionların da temizləndiyi aşama
→ En "garbage-dolu" Old regionlar seçilir (Garbage-First!)
→ STW: Seçilmiş Old + Young regionlar evacuate edilir
```

```java
// G1GC-yi konfiqurasiya etmək
public class G1GCConfig {
    /*
     * Əsas JVM flag-ləri:
     *
     * java -XX:+UseG1GC \
     *      -Xms2g \
     *      -Xmx8g \
     *      -XX:MaxGCPauseMillis=200 \         // Hədəf maks pauz (ms)
     *      -XX:G1HeapRegionSize=4m \           // Region ölçüsü (1-32MB, 2^n)
     *      -XX:G1NewSizePercent=5 \            // Min Young Gen faizi
     *      -XX:G1MaxNewSizePercent=60 \        // Max Young Gen faizi
     *      -XX:G1MixedGCCountTarget=8 \        // Mixed GC sayı
     *      -XX:InitiatingHeapOccupancyPercent=45 \ // Concurrent marking başlama %
     *      MyApp
     */
    public static void main(String[] args) {
        System.out.println("G1GC konfiqurasiyası");
    }
}
```

### Humongous Objects

G1GC-də region ölçüsünün yarısından böyük obyektlər **humongous** sayılır:

```java
public class HumongousObjectDemo {

    // G1GC region ölçüsü default olaraq heap-ə görə hesablanır
    // 2GB heap → 1MB region → 512KB-dan böyük = humongous

    public static void main(String[] args) {
        // Bu HUMONGOUS obyektdir — G1GC-də xüsusi davranış
        // Birbaşa Old Gen-ə gedir (Young Gen-i bypass edir)
        // GC sırasında daha az effektivdir
        byte[] bigArray = new byte[2 * 1024 * 1024]; // 2MB

        // DOĞRU yanaşma — böyük massivlər üçün pool istifadə et
        // Və ya massivi hissələrə böl

        // Humongous object threshold-u görmək:
        // -Xlog:gc+heap=debug ilə "Humongous" sözünü axtar
        System.out.println("Humongous object yaradıldı: " + bigArray.length + " bytes");
    }
}
```

### G1GC Mixed Collections

```
Concurrent Marking Cycle:
1. Initial Mark (STW, ~1ms) — GC Root-ları işarələ
2. Root Region Scan (concurrent) — Survivor regionları tara
3. Concurrent Mark (concurrent) — Tüm heap-i işarələ
4. Remark (STW, ~10ms) — Son işarələmə (SATB — Snapshot At The Beginning)
5. Cleanup (concurrent + STW) — Boş regionları müəyyən et

Sonra: Mixed GC cycles
— Seçilmiş Old regionları Young GC ilə birlikdə temizlə
```

---

## ZGC

**ZGC** — Ultra aşağı latency GC. Java 15-dən production-ready.

### Əsas Xüsusiyyətlər
- STW pauzaları **10ms-dən az** (Java 16+: sub-millisecond!)
- Heap ölçüsünə görə scale olur (8MB - 16TB)
- **Concurrent**: Mark, Relocate, Relocation Set Selection — hamısı concurrent
- **Colored Pointers**: Referansların pointer bit-lərindən istifadə

### Colored Pointers

```
64-bit pointer:
┌──────────────────────────────────────────────────────────────────────┐
│ 18 bit unused │ 4 bit metadata │ 42 bit object address              │
│               │                │                                    │
│               │ ↑ GC üçün bits │                                    │
│               │ Marked0        │                                    │
│               │ Marked1        │                                    │
│               │ Remapped       │                                    │
│               │ Finalizable    │                                    │
└──────────────────────────────────────────────────────────────────────┘
```

```bash
# ZGC aktivləşdirmək
java -XX:+UseZGC MyApp                    # Java 15+
java -XX:+UseZGC -XX:+ZGenerational MyApp # Java 21+ (Generational ZGC)

# ZGC tuning (minimal — əsasən özü idarə edir)
java -XX:+UseZGC \
     -Xms4g -Xmx16g \
     -XX:ConcGCThreads=4 \          # Concurrent GC thread sayı
     -XX:ZCollectionInterval=120 \  # Periodik GC intervali (saniyə)
     MyApp

# ZGC statistikaları
java -XX:+UseZGC -Xlog:gc*:file=zgc.log MyApp
```

```java
public class ZGCDemo {
    /*
     * ZGC uyğun olduğu hallar:
     * - Çox böyük heap (10GB - yüzlərlə GB)
     * - Aşağı latency tələb olunan sistemlər (trading, real-time)
     * - Web server-lər (99. percentil latency əhəmiyyətli)
     *
     * ZGC-nin çatışmazlıqları:
     * - Parallel/G1-dən bir az az throughput
     * - Multi-mapping üçün daha çox virtual memory tələb edir
     * - Java 15-dən production-ready (köhnə versiyalarda experimental)
     */
    public static void main(String[] args) throws InterruptedException {
        // ZGC ilə bu proqram demək olar ki, heç pause olmadan işləyir
        long[] timestamps = new long[1000];

        for (int i = 0; i < 1000; i++) {
            long before = System.nanoTime();

            // 1MB-lıq massiv yarat (GC tetikləyir)
            byte[] data = new byte[1024 * 1024];
            data[0] = 42; // Optimizer-in silməməsi üçün

            long after = System.nanoTime();
            timestamps[i] = after - before;

            Thread.sleep(10);
        }

        // Maksimum pauz tapıq
        long maxPause = 0;
        for (long t : timestamps) {
            maxPause = Math.max(maxPause, t);
        }
        System.out.printf("Maks latency: %.3f ms%n", maxPause / 1_000_000.0);
    }
}
```

---

## Shenandoah GC

**Shenandoah** — Red Hat tərəfindən hazırlanmış, concurrent compaction edən GC.

### G1GC vs Shenandoah

| Xüsusiyyət | G1GC | Shenandoah |
|-----------|------|------------|
| Evacuation | STW | Concurrent! |
| Pauz ölçüsü | O(heap) | O(1) — heap-dən müstəqil |
| Throughput | Yaxşı | Bir az az |
| Latency | Orta | Çox aşağı |

### Brooks Pointer

Shenandoah concurrent kompaktlaşdırma üçün **Brooks Pointer** istifadə edir:

```
Hər obyektin əvvəlindəki əlavə söz (word):

[Brooks Ptr │ Object Header │ Object Data]
     ↓
Köhnə yer:  self-referans (başqa yerdə deyilsə)
Köçürüldükdən sonra: yeni yerə istinad

Application thread obyektə daxil olduqda:
→ Brooks Ptr-ı yoxla
→ Köçürülübsə yeni yerə yönləndir (load barrier)
```

```bash
# Shenandoah aktivləşdirmək
java -XX:+UseShenandoahGC MyApp

# Shenandoah heuristic-ləri
java -XX:+UseShenandoahGC \
     -XX:ShenandoahGCHeuristics=adaptive \  # adaptive (default), static, compact, aggressive
     -XX:ShenandoahUncommitDelay=1000 \     # Yaddaşı OS-ə qaytarma gecikməsi (ms)
     MyApp
```

---

## Epsilon GC

**Epsilon GC** — "No-op" GC. Yaddaş ayırır, amma heç vaxt toplamır.

```
java -XX:+UseEpsilonGC MyApp

Heap dolduqda → OutOfMemoryError (heç bir GC yoxdur)
```

### Nə üçün lazımdır?

```java
// Nə zaman Epsilon GC istifadə etmək lazımdır:

// 1. Performance testing — GC overhead olmadan benchmark
// Həqiqi GC overhead-i ölçmək üçün Epsilon ilə müqayisə et

// 2. Qısamüddətli proqramlar — heap dolmazdan bitən
// Məsələn: command-line tools, AWS Lambda (qısa tələblər)

// 3. GC-free zone — müəyyən kritik əməliyyatlarda GC olmasın
// Real-time audio/video processing

// 4. Memory allocation analizi
// -Xlog:gc ilə nə qədər yaddaş ayrıldığını görmək

public class EpsilonGCDemo {
    public static void main(String[] args) {
        // java -XX:+UseEpsilonGC -Xmx64m EpsilonGCDemo
        long count = 0;
        while (true) {
            new byte[1024]; // 1KB yarat, amma heç vaxt toplanmır
            count++;
            if (count % 10000 == 0) {
                System.out.println("Ayrılmış: " + (count * 1024 / 1024 / 1024) + " GB");
            }
            // Tezliklə OutOfMemoryError: Java heap space
        }
    }
}
```

---

## Hansını Seçməli?

```
Qərar diaqramı:

Heap ölçüsü < 1GB?
├── Bəli → Serial GC (-XX:+UseSerialGC)
│          Tək CPU-lu mühitlər, embedded sistemlər
└── Xeyr
    │
    Batch processing / Throughput önəmlidir?
    ├── Bəli → Parallel GC (-XX:+UseParallelGC)
    │          Hadoop, Spark, toplu hesablamalar
    └── Xeyr
        │
        Latency önəmlidir?
        ├── < 200ms target → G1GC (-XX:+UseG1GC) [default Java 9+]
        │                    Ümumi məqsəd, web server-lər
        └── < 10ms target
            ├── Java 11+: ZGC (-XX:+UseZGC)
            │            Ultra-aşağı latency, böyük heap
            └── ZGC alternativi: Shenandoah (-XX:+UseShenandoahGC)
                                 Red Hat OpenJDK ilə gəlir
```

| GC | Java Versiya | Default? | Ən Yaxşı Olduğu Yer |
|----|-------------|----------|---------------------|
| Serial | Bütün | ≤ Java 8 (tək CPU) | Kiçik heap, embedded |
| Parallel | Bütün | Java 8 | Throughput, batch |
| G1GC | Java 7+ | Java 9+ | Ümumi məqsəd |
| ZGC | Java 11+ (exp), 15+ (prod) | - | Aşağı latency, böyük heap |
| Shenandoah | Java 12+ | - | Aşağı latency, alternativ |
| Epsilon | Java 11+ | - | Testing, analiz |

```java
// Runtime-da hansı GC istifadə olunduğunu tapmaq
import java.lang.management.*;
import java.util.List;

public class DetectGC {
    public static void main(String[] args) {
        List<GarbageCollectorMXBean> gcBeans =
            ManagementFactory.getGarbageCollectorMXBeans();

        System.out.println("Aktiv GC kollektorları:");
        for (GarbageCollectorMXBean gc : gcBeans) {
            System.out.println("  " + gc.getName());
        }
        // Nümunə çıxışlar:
        // G1GC:          "G1 Young Generation", "G1 Old Generation"
        // Parallel GC:   "PS Scavenge", "PS MarkSweep"
        // ZGC:           "ZGC Cycles", "ZGC Pauses"
        // Shenandoah:    "Shenandoah Cycles", "Shenandoah Pauses"
        // Serial:        "Copy", "MarkSweepCompact"
        // Epsilon:       "Epsilon"
    }
}
```

### Realist Müqayisə

```bash
# Eyni tətbiq, fərqli GC-lər:
#
# Parallel GC:
#   Throughput: 99.5%    Maks Pauz: 2000ms   P99 Latency: 1800ms
#
# G1GC (-XX:MaxGCPauseMillis=200):
#   Throughput: 98.5%    Maks Pauz: 250ms    P99 Latency: 200ms
#
# ZGC:
#   Throughput: 97%      Maks Pauz: 5ms      P99 Latency: 3ms
#
# Shenandoah:
#   Throughput: 97.5%    Maks Pauz: 8ms      P99 Latency: 5ms
#
# Seçim: tətbiqin SLA-sına görə!
```

---

## İntervyu Sualları

**S: Java-nın default GC-si hansıdır?**
C: Java 8-də Parallel GC. Java 9-dan etibarən G1GC default-dur.

**S: G1GC Heap-i necə idarə edir?**
C: G1GC heap-i bərabər ölçülü region-lara (1-32MB) bölür. Region-lar dinamik olaraq Eden, Survivor, Old, Humongous rolunu alır. "Garbage-First" — ən çox zibil olan region-ları əvvəl temizləyir.

**S: ZGC niyə bu qədər aşağı latency verir?**
C: ZGC demək olar ki, bütün işi concurrent edir. Colored pointers (64-bit pointer-da metadata bitləri) istifadə edərək load barrier vasitəsilə mark/relocate fazalarını application thread-ləri ilə paralel aparır. STW pausu yalnız bir neçə ms olur.

**S: Humongous objects nədir?**
C: G1GC-də region ölçüsünün yarısından böyük obyektlər. Bunlar birbaşa Old Gen-ə gedir, Young GC-ni bypass edir. Çox yarananda performans problemi yarada bilər.

**S: Epsilon GC nə üçündür?**
C: "No-op" GC — yaddaş ayırır, amma heç vaxt toplamır. Performance benchmarking (GC overhead-siz), qısamüddətli proqramlar, GC-free zone-lar üçün istifadə olunur. Heap dolduqda OutOfMemoryError atır.

**S: Serial GC nə zaman istifadə edilir?**
C: Tək CPU-lu mühitlər, kiçik heap (< 100MB), embedded sistemlər, container-lər üçün. Çox thread-li server mühitlərində Parallel/G1GC daha yaxşıdır.

**S: G1GC-nin Concurrent Marking Cycle hansı fazalardan ibarətdir?**
C: Initial Mark (STW), Root Region Scan, Concurrent Mark, Remark (STW, SATB), Cleanup. Sonra Mixed GC collections başlayır — seçilmiş Old regionlar Young GC ilə birlikdə temizlənir.
