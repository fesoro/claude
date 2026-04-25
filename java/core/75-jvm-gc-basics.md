# 75 — JVM Garbage Collection — Əsaslar

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [GC nədir?](#gc-nədir)
2. [Niyə Avtomatik Yaddaş İdarəetməsi?](#niyə-avtomatik-yaddaş-i̇darəetməsi)
3. [GC Roots](#gc-roots)
4. [Reachability (Əlçatımlılıq)](#reachability-əlçatımlılıq)
5. [Mark Phase](#mark-phase)
6. [Sweep Phase](#sweep-phase)
7. [Compact Phase](#compact-phase)
8. [Minor GC vs Major GC vs Full GC](#minor-gc-vs-major-gc-vs-full-gc)
9. [Stop-The-World Pauzaları](#stop-the-world-pauzaları)
10. [GC Overhead](#gc-overhead)
11. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## GC nədir?

**Garbage Collection (Zibil Toplama)** — proqramçı tərəfindən manual olaraq silməyə ehtiyac olmadan istifadə olunmayan obyektlərin yaddaşdan avtomatik silinməsidir.

C/C++-da proqramçı özü yaddaşı idarə etməlidir:
```c
// C-də manual yaddaş idarəetməsi
int* arr = malloc(100 * sizeof(int));
// ... istifadə et ...
free(arr);  // Unutsan → memory leak!
arr = NULL; // Unutsan → dangling pointer!
```

Java-da isə GC bunu avtomatik edir:
```java
// Java-da avtomatik yaddaş idarəetməsi
public void javaExample() {
    int[] arr = new int[100]; // Heap-də yer ayrılır
    // ... istifadə et ...
    // Metod bitdikdə arr referansı stack-dən silinir
    // Heç bir referans qalmadıqda GC obyekti silir
}
```

---

## Niyə Avtomatik Yaddaş İdarəetməsi?

### Manual İdarəetmənin Problemləri

**Memory Leak** — yaddaş ayrılır, amma heç vaxt azad edilmir:
```c
// C-də memory leak
void badFunction() {
    char* buffer = malloc(1024);
    if (someCondition()) {
        return; // Unutdu! buffer heç vaxt free edilmir
    }
    free(buffer);
}
```

**Dangling Pointer** — artıq azad edilmiş yaddaşa istinad:
```c
int* ptr = malloc(sizeof(int));
*ptr = 42;
free(ptr);
*ptr = 100; // Undefined behavior! Silinmiş yaddaşa yazma
```

**Double Free** — eyni yaddaşı iki dəfə azad etmək:
```c
int* ptr = malloc(sizeof(int));
free(ptr);
free(ptr); // Crash!
```

### Java-nın Üstünlükləri

```java
public class GCBenefits {
    public static void main(String[] args) {
        // Java-da bu problemlər yoxdur:

        // 1. Memory Leak — GC referanssız obyektləri tapır
        String s = new String("Salam");
        s = null; // Referans silindi, GC obyekti yığacaq

        // 2. Dangling pointer yoxdur — referans varsa obyekt var
        StringBuilder sb = new StringBuilder("Dünya");
        String result = sb.toString();
        sb = null; // sb silindi, amma result hələ result obyektinə istinad edir

        // 3. Double free yoxdur — GC öz işini özü idarə edir
        System.out.println(result); // Dünya — hələ mövcuddur
    }
}
```

---

## GC Roots

**GC Root** — həmişə "canlı" (alive) sayılan başlanğıc nöqtələr. GC bu nöqtələrdən başlayaraq bütün əlçatımlı obyektləri tapır.

GC Root-lar:
1. **Stack-dəki dəyişənlər** — bütün thread-lərin aktiv metodlarındakı lokal dəyişənlər
2. **Statik sahələr** — siniflərin statik dəyişənləri
3. **JNI referansları** — native koddan Java obyektlərinə olan istinadlar
4. **Sinif nümunələri** — yüklənmiş siniflərin özləri (Metaspace-dəki)
5. **Synchronized monitor-lar** — `synchronized` bloklarındakı obyektlər
6. **Thread obyektləri** — aktiv thread-lər

```java
public class GCRootsDemo {

    // 1. Statik sahə — GC Root
    static String staticField = "Bu GC Root-dur";

    // 2. Statik kolleksiya — GC Root (içindəki obyektlər əlçatımlıdır)
    static java.util.List<Object> staticCache = new java.util.ArrayList<>();

    public static void main(String[] args) {
        // 3. Stack dəyişəni — GC Root (bu metodun stack frame-i aktivdir)
        String localVar = "Bu da GC Root-dur";

        Object reachableObject = new Object(); // Əlçatımlı — localVar vasitəsilə

        Object unreachableObject = new Object(); // Hələ əlçatımlı
        unreachableObject = null; // Artıq referans yoxdur → GC üçün uyğun

        // staticCache-ə əlavə edilənlər əlçatımlı qalır
        staticCache.add(new Object()); // Bu obyekt GC tərəfindən silinməyəcək

        System.out.println("GC roots nümunəsi");
    }
}
```

---

## Reachability (Əlçatımlılıq)

Bir obyekt **əlçatımlı (reachable)** sayılır əgər GC Root-lardan ona çatan ən azı bir referans zənciri varsa.

```
GC Roots
    │
    ├── A (əlçatımlı)
    │   ├── B (əlçatımlı — A vasitəsilə)
    │   │   └── C (əlçatımlı — A→B vasitəsilə)
    │   └── D (əlçatımlı — A vasitəsilə)
    │
    └── E (əlçatımlı)

X ──→ Y  (NƏTİÇƏ: X da, Y da əlçatımazdır — GC siləcək)
↑
(GC Root-a çatmır)
```

### Java Reference Növləri

```java
import java.lang.ref.*;

public class ReferenceTypesDemo {

    public static void main(String[] args) throws InterruptedException {

        Object strongObj = new Object(); // Strong Reference — GC silmir
        // GC bu obeyekti HEÇVAXT silmir (referans varsa)

        // Weak Reference — GC növbəti tsikldə silə bilər
        WeakReference<Object> weakRef = new WeakReference<>(new Object());
        System.gc();
        System.out.println("Weak ref: " + weakRef.get()); // null ola bilər

        // Soft Reference — yalnız yaddaş lazım olanda silinir
        // Cache-lər üçün ideal
        SoftReference<byte[]> softRef = new SoftReference<>(new byte[1024 * 1024]);
        System.out.println("Soft ref: " + (softRef.get() != null ? "mövcuddur" : "silinib"));

        // Phantom Reference — finalizer-dən sonra, yaddaş azad edilməzdən əvvəl
        ReferenceQueue<Object> queue = new ReferenceQueue<>();
        PhantomReference<Object> phantomRef = new PhantomReference<>(new Object(), queue);
        System.gc();
        System.out.println("Phantom ref.get(): " + phantomRef.get()); // Həmişə null!
    }
}
```

### Dövri İstinadlar (Circular References)

```java
// Java-da dövri istinadlar problem deyil — Mark & Sweep düzgün işləyir!
public class CircularReferenceDemo {

    static class Node {
        String name;
        Node next; // Dövri istinad

        Node(String name) { this.name = name; }
    }

    public static void main(String[] args) {
        // Dövri istinad yaradırıq
        Node a = new Node("A");
        Node b = new Node("B");
        a.next = b;
        b.next = a; // A → B → A → ...

        // İkisini də null edirk
        a = null;
        b = null;

        // İndi nə A-ya, nə B-yə GC Root-dan istinad var
        // GC HƏR İKİSİNİ silə bilər — dövri istinad problem deyil!
        System.gc();
        System.out.println("Dövri istinadlar GC tərəfindən idarə olundu");
    }
}
```

---

## Mark Phase

**Mark (İşarələmə)** — GC bütün əlçatımlı obyektləri tapıb işarələyir.

```
Proses:
1. GC Roots-dan başla
2. Hər əlçatımlı obyekti "canlı" kimi işarələ (object header-da bit)
3. Bütün referansları rekursiv izlə (qraf keçidi)
4. İşarələnməmiş = ölü = silinə bilər

Vizual:
Heap:
[A✓] [B✓] [C ] [D✓] [E ] [F✓]
                ↑               ↑
           Əlçatımaz      Əlçatımaz
           (silinəcək)   (silinəcək)
```

Mark phase **Stop-The-World** pauzası tələb edir (concurrent GC-lər istisna olmaqla).

---

## Sweep Phase

**Sweep (Süpürmə)** — işarələnməmiş (ölü) obyektlər silinir, yaddaş azad edilir.

```
Mark-dan sonra:
[A✓] [B✓] [   ] [D✓] [   ] [F✓]
            ↑           ↑
       Azad yaddaş  Azad yaddaş

Problem — Fragmentation (parçalanma):
[A✓] [   ] [D✓] [   ] [F✓] [   ] [G✓]
Böyük bir fasiləsiz blok yoxdur, amma ümumi boş yaddaş var!
```

---

## Compact Phase

**Compact (Sıxışdırma)** — canlı obyektlər bir tərəfə toplanır, böyük fasiləsiz sahə yaranır.

```
Sweep-dən sonra:
[A✓] [   ] [D✓] [   ] [F✓] [   ] [G✓]

Compact-dan sonra:
[A✓] [D✓] [F✓] [G✓] [               ]
                      ← Böyük fasiləsiz boş sahə →
```

**Kompaktlaşdırmanın çatışmazlığı** — çox yavaşdır (bütün obyektləri köçürmək + referansları yeniləmək lazımdır).

Buna görə GC alqoritmlər müxtəlif yanaşmalar istifadə edir:
- **Mark-Sweep**: Kompaktsız (fragmentation var)
- **Mark-Sweep-Compact**: Kompaktlıdır amma yavaş
- **Copying**: Canlı obyektlər yeni sahəyə köçürülür (Eden→Survivor)
- **Generational**: Young Gen üçün Copying, Old Gen üçün Mark-Sweep-Compact

---

## Minor GC vs Major GC vs Full GC

### Minor GC (Young GC)

```
Tetikleyici: Eden space dolduqda
Sahə: Yalnız Young Generation (Eden + Survivor)
Sürət: Sürətli (yalnız qısa müddətli obyektlər)
Pauz: Qısa (adətən < 100ms)

Eden + S0 → Mark → Canlıları S1-ə köçür → Eden + S0 təmizlə
```

```java
// Minor GC-ni izləmək
// java -verbose:gc -XX:+PrintGCDetails MyApp
// Çıxış:
// [GC (Allocation Failure) [PSYoungGen: 65536K->2048K(75776K)] ...]
//                                        ↑ Öncə    ↑ Sonra
```

### Major GC

```
Tetikleyici: Old Generation dolduqda
Sahə: Old Generation
Sürət: Minor GC-dən yavaş (daha çox data)
Pauz: Uzun (yüzlərlə ms, bəzən saniyələrlə)
```

### Full GC

```
Tetikleyici: System.gc(), Metaspace dolması, Old Gen dolması
Sahə: Heap + Metaspace (hər yer)
Sürət: Ən yavaş
Pauz: Ən uzun

Niyə Full GC pis?
- Bütün thread-lər dayanır (Stop-The-World)
- Uzun pauz = application responsiveness yox olur
```

```java
public class GCTypesDemo {
    public static void main(String[] args) throws Exception {
        // Minor GC tetikləmək
        for (int i = 0; i < 1000; i++) {
            byte[] trash = new byte[1024]; // Eden-i doldur
        }

        // Full GC-ni əl ilə çağırmaq (prodüktsionda etmə!)
        System.gc(); // JVM-ə tövsiyədir, məcburi deyil

        // Ya da Runtime üzərindən
        Runtime.getRuntime().gc();

        System.out.println("GC çağırıldı");

        // GC statistikasını kod içindən almaq
        java.lang.management.MemoryMXBean memBean =
            java.lang.management.ManagementFactory.getMemoryMXBean();
        System.out.println("Pending finalizers: " + memBean.getObjectPendingFinalizationCount());
    }
}
```

### GC Siqnallarını Öyrənmək

```bash
# GC log-larını aktivləşdirmək (Java 11+)
java -Xlog:gc*:file=gc.log:time,uptime,level,tags MyApp

# Java 8 üçün
java -XX:+PrintGCDetails -XX:+PrintGCDateStamps -Xloggc:gc.log MyApp

# Nümunə çıxış:
# [2024-01-15T10:30:45.123+0000][0.456s][info][gc] GC(0) Pause Young (Normal)
#   (G1 Evacuation Pause) 64M->12M(256M) 23.456ms
```

---

## Stop-The-World Pauzaları

**Stop-The-World (STW)** — GC zamanı bütün uygulama thread-ləri durdurulur.

```
Normal execution:
Thread 1: ████████████████████
Thread 2: ████████████████████
Thread 3: ████████████████████

GC zamanı (STW):
Thread 1: ████████████|        |████████████
Thread 2: ████████████| GC     |████████████
Thread 3: ████████████|  STW   |████████████
           ───────────  ─────  ─────────────
                        Pauz
```

### STW-nin niyə tələb olunduğu

GC heap-i analiz edərkən obyektlər dəyişdirilə bilməməlidir:

```java
// GC Mark zamanı bu baş verə bilər (STW olmasa):
// Thread 1: A → B referansını silib A → C edir
// GC: A-nı skan etdi (B işarələnib), C hələ skan edilməyib
// Nəticə: C "ölü" kimi işarələnir, amma canlıdır! → Fəlakət!
```

### Concurrent GC-lər

Modern GC-lər (G1, ZGC, Shenandoah) Mark fazasının əksəriyyətini **concurrent** (thread-lərlə paralel) edirlər:

```
ZGC ilə:
Thread 1: ████████████████████████████████████
Thread 2: ████████████████████████████████████
GC:                  [concurrent mark............][STW < 1ms]
```

---

## GC Overhead

GC-nin tətbiqə qaytardığı yüklə bağlı əsas anlayışlar:

### Throughput vs Latency

```
Throughput-əsaslı GC (Parallel GC):
- Nadir amma uzun pauzalar
- Ümumi iş görülən vaxtın payı daha yüksək
- Batch processing, HPC üçün ideal

Latency-əsaslı GC (ZGC, Shenandoah):
- Çox qısa pauzalar (< 1ms)
- Biraz daha az throughput
- Real-time, web server-lər üçün ideal
```

```java
import java.lang.management.*;
import java.util.List;

public class GCOverheadMonitor {
    public static void main(String[] args) throws InterruptedException {
        // GC statistikasını əldə etmək
        List<GarbageCollectorMXBean> gcBeans =
            ManagementFactory.getGarbageCollectorMXBeans();

        for (GarbageCollectorMXBean gcBean : gcBeans) {
            System.out.println("GC adı: " + gcBean.getName());
            System.out.println("  Kolleksiya sayı: " + gcBean.getCollectionCount());
            System.out.println("  Ümumi vaxt (ms): " + gcBean.getCollectionTime());
            System.out.println("  Yaddaş pool-ları: " +
                java.util.Arrays.toString(gcBean.getMemoryPoolNames()));
        }

        // GC nisbəti hesablamaq
        long totalTime = 0;
        long totalCount = 0;
        for (GarbageCollectorMXBean gc : gcBeans) {
            totalTime += Math.max(gc.getCollectionTime(), 0);
            totalCount += Math.max(gc.getCollectionCount(), 0);
        }

        System.out.printf("Ümumi GC vaxtı: %d ms, Sayı: %d%n", totalTime, totalCount);
    }
}
```

### GC-ni Azaltmaq üçün Best Practice-lər

```java
import java.util.ArrayList;
import java.util.List;

public class GCOptimization {

    // YANLIŞ — çox Garbage yaratmaq
    public static String badStringConcat(List<String> words) {
        String result = "";
        for (String word : words) {
            result = result + word + " "; // Hər dəfə yeni String obyekti yaranır!
        }
        return result;
    }

    // DOĞRU — StringBuilder istifadəsi
    public static String goodStringConcat(List<String> words) {
        StringBuilder sb = new StringBuilder();
        for (String word : words) {
            sb.append(word).append(' '); // Tək obyektdə dəyişiklik
        }
        return sb.toString();
    }

    // YANLIŞ — Autoboxing GC yükü
    public static long badSum(int[] numbers) {
        Long sum = 0L; // Long (boxed) — hər += yeni Long yaranır!
        for (int n : numbers) {
            sum += n; // Unbox, cəmlə, box — çox Garbage!
        }
        return sum;
    }

    // DOĞRU — primitiv istifadə
    public static long goodSum(int[] numbers) {
        long sum = 0L; // Primitiv — heap allocation yoxdur
        for (int n : numbers) {
            sum += n; // Stack-də əməliyyat
        }
        return sum;
    }

    // DOĞRU — Object Pool Pattern (baha obyektlər üçün)
    static class ConnectionPool {
        private final List<Connection> pool = new ArrayList<>();

        // Yeni Connection yaratmaq əvəzinə pool-dan al
        public Connection acquire() {
            if (!pool.isEmpty()) {
                return pool.remove(pool.size() - 1); // Yeni allocation yox!
            }
            return new Connection(); // Yalnız lazım gəldikdə yarat
        }

        public void release(Connection conn) {
            conn.reset(); // State-i sıfırla
            pool.add(conn); // Pool-a qaytar
        }
    }

    static class Connection {
        void reset() { /* state-i sıfırla */ }
    }
}
```

---

## İntervyu Sualları

**S: GC nədir və niyə lazımdır?**
C: GC — proqram tərəfindən artıq istifadə olunmayan obyektləri heap-dən avtomatik silən mexanizmdir. C/C++-dakı manual yaddaş idarəetməsinin problemlərini (memory leak, dangling pointer, double free) aradan qaldırır.

**S: GC Roots nəlardır?**
C: GC-nin başlanğıc nöqtələri: stack-dəki lokal dəyişənlər, statik sahələr, JNI referansları, aktiv thread-lər. GC bu nöqtələrdən başlayaraq bütün əlçatımlı obyektləri tapır.

**S: Mark, Sweep, Compact fazaları nə edir?**
C: Mark: GC Roots-dan başlayaraq bütün əlçatımlı obyektlər işarələnir. Sweep: işarələnməmiş (ölü) obyektlər silinir. Compact: canlı obyektlər bir tərəfə yığılır (fragmentation aradan qalxır).

**S: Minor GC, Major GC, Full GC fərqi nədir?**
C: Minor GC — Young Gen (Eden + Survivor), sürətli. Major GC — Old Gen, yavaş. Full GC — Heap + Metaspace, ən yavaş. Full GC-dən qaçmaq lazımdır — uzun STW pauzası verir.

**S: Stop-The-World nədir?**
C: GC zamanı bütün uygulama thread-lərinin dayandırılmasıdır. Bu müddətdə heç bir kod icra olunmur. Modern GC-lər (ZGC, Shenandoah) STW pauzasını 1ms-dən aşağı endirir.

**S: Dövri istinadlar (A→B→A) GC üçün problem yaradırmı?**
C: Java-da xeyr. Mark-and-Sweep alqoritmi GC Root-lardan əlçatımazdısa bunları da silir. Reference counting (Python-da) istifadə olunsa bu problem olardı, amma Java bunu istifadə etmir.

**S: `System.gc()` çağırmaq niyə pis fikirdədir?**
C: `System.gc()` yalnız JVM-ə tövsiyədir, məcburi deyil. Çağırıldıqda Full GC baş verə bilər — uzun STW pauzası yaranır. GC öz alqoritminə görə işləmə qabiliyyəti daha yaxşıdır.
