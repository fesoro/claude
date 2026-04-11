# 43. JVM Yaddaş Sahələri

## Mündəricat
1. [Ümumi Baxış](#ümumi-baxış)
2. [Heap — Young Generation](#heap--young-generation)
3. [Heap — Old Generation](#heap--old-generation)
4. [Stack və Stack Frames](#stack-və-stack-frames)
5. [Metaspace](#metaspace)
6. [Code Cache](#code-cache)
7. [PC Register](#pc-register)
8. [Native Method Stack](#native-method-stack)
9. [Yaddaş Xətaları](#yaddaş-xətaları)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Ümumi Baxış

JVM yaddaşı iki əsas kateqoriyaya bölünür:

**Paylaşılan (bütün thread-lər üçün):**
- Heap (Young Gen + Old Gen)
- Metaspace
- Code Cache

**Thread-ə aid (hər thread üçün ayrıca):**
- JVM Stack
- PC (Program Counter) Register
- Native Method Stack

```
┌────────────────────────────────────────────────────────────┐
│                    JVM Yaddaş Sahələri                     │
│                                                            │
│  ┌─────────────────────────────┐  ┌─────────────────────┐ │
│  │           HEAP              │  │     METASPACE        │ │
│  │  ┌──────────────────────┐   │  │  (sinif metadata)   │ │
│  │  │    YOUNG GENERATION  │   │  │  Native memory-dən  │ │
│  │  │  ┌──────┬──────────┐ │   │  └─────────────────────┘ │
│  │  │  │ Eden │ S0 │ S1  │ │   │  ┌─────────────────────┐ │
│  │  │  └──────┴──────────┘ │   │  │    CODE CACHE       │ │
│  │  └──────────────────────┘   │  │  (JIT native kodu)  │ │
│  │  ┌──────────────────────┐   │  └─────────────────────┘ │
│  │  │    OLD GENERATION    │   │                          │
│  │  │    (Tenured Space)   │   │                          │
│  │  └──────────────────────┘   │                          │
│  └─────────────────────────────┘                          │
│                                                            │
│  Thread 1          Thread 2          Thread 3             │
│  ┌─────────┐       ┌─────────┐       ┌─────────┐         │
│  │  STACK  │       │  STACK  │       │  STACK  │         │
│  ├─────────┤       ├─────────┤       ├─────────┤         │
│  │PC Reg.  │       │PC Reg.  │       │PC Reg.  │         │
│  ├─────────┤       ├─────────┤       ├─────────┤         │
│  │Native   │       │Native   │       │Native   │         │
│  │Mth Stk  │       │Mth Stk  │       │Mth Stk  │         │
│  └─────────┘       └─────────┘       └─────────┘         │
└────────────────────────────────────────────────────────────┘
```

---

## Heap — Young Generation

**Young Generation** yeni yaradılan obyektlərin yerləşdiyi sahədir. JVM-in default olaraq heap-in 1/3-ni tutur.

### Eden Space
- `new` operatoru ilə yaradılan bütün obyektlər **Eden**-də başlayır
- Eden dolduqda **Minor GC** (Young GC) baş verir
- Sağ çıxan obyektlər Survivor space-ə keçir

### Survivor Spaces (S0 və S1)
- İki Survivor space mövcuddur — biri həmişə boşdur (To Space)
- Minor GC-dən sonra sağ çıxan obyektlər Eden + aktiv Survivor-dan boş Survivor-a köçürülür
- Hər sağ çıxışda obyektin **age (yaşı)** artır
- **Tenuring Threshold** (default: 15) keçildikdə obyekt Old Gen-ə köçür

```java
import java.util.ArrayList;
import java.util.List;

public class HeapDemo {
    public static void main(String[] args) throws InterruptedException {
        // Bu obyektlər Eden-də yaranır
        List<byte[]> shortLived = new ArrayList<>();

        for (int i = 0; i < 1000; i++) {
            // 1KB-lıq qısamüddətli obyekt
            byte[] data = new byte[1024];
            shortLived.add(data);

            if (i % 100 == 0) {
                // Reference-ları silmək — GC tərəfindən yığılacaq
                shortLived.clear();
            }
        }

        // JVM flags ilə yoxlamaq:
        // java -Xmx256m -Xms64m -verbose:gc HeapDemo
        System.out.println("Eden/Survivor tsikli tamamlandı");
    }
}
```

```bash
# Young Gen ölçüsünü görmək
java -XX:+PrintFlagsFinal -version | grep -E "NewSize|SurvivorRatio|NewRatio"

# Young Gen ölçüsünü təyin etmək
java -Xmn64m MyApp          # Young Gen = 64MB
java -XX:NewRatio=2 MyApp   # Old:Young = 2:1 (Young = heap/3)
java -XX:SurvivorRatio=8 MyApp  # Eden:S0:S1 = 8:1:1
```

---

## Heap — Old Generation

**Old Generation (Tenured Space)** uzunmüddətli yaşayan obyektlərin yerləşdiyi sahədir.

Obyekt Old Gen-ə keçir:
1. Tenuring threshold aşıldıqda (default: 15 GC dövrü)
2. Survivor space dolduqda (premature promotion)
3. Çox böyük obyektlər birbaşa Old Gen-ə yerləşdirilir (humongous objects — G1GC-də)

Old Gen dolduqda **Major GC** və ya **Full GC** baş verir — daha uzun **Stop-The-World** pausu.

```java
import java.util.HashMap;
import java.util.Map;

public class OldGenDemo {
    // Bu statik sahə — sinif yükləndikdə yaranır, Old Gen-ə gedir
    private static final Map<String, Object> CACHE = new HashMap<>();

    public static void main(String[] args) {
        // Cache-ə əlavə edilən obyektlər uzunmüddətli yaşayacaq
        // Survivor-ları keçib Old Gen-ə keçəcəklər
        for (int i = 0; i < 10_000; i++) {
            CACHE.put("key-" + i, new byte[512]); // 512 byte-lıq obyektlər
        }

        System.out.println("Cache ölçüsü: " + CACHE.size());
        // Bu obyektlər GC tərəfindən silinməyəcək çünki CACHE onlara istinad edir
    }
}
```

---

## Stack və Stack Frames

Hər Java thread-i öz **JVM Stack**-inə malikdir. Stack **LIFO** (Last In, First Out) prinsipilə işləyir.

### Stack Frame
Hər metod çağırışında yeni **Stack Frame** yaranır:

```
Stack (Thread 1):
┌─────────────────────────────────────┐  ← Top (current frame)
│           Frame: methodC()          │
│  local vars: [result]               │
│  operand stack: [5, 3]              │
│  frame data: return addr, ...       │
├─────────────────────────────────────┤
│           Frame: methodB()          │
│  local vars: [x, y]                 │
│  operand stack: []                  │
│  frame data: return addr, ...       │
├─────────────────────────────────────┤
│           Frame: methodA()          │
│  local vars: [this, args]           │
│  operand stack: []                  │
│  frame data: return addr, ...       │
├─────────────────────────────────────┤
│           Frame: main()             │
│  local vars: [args]                 │
│  operand stack: []                  │
│  frame data: return addr, ...       │
└─────────────────────────────────────┘  ← Bottom
```

### Stack Frame-in Hissələri

**Local Variable Array:**
- Metodun parametrləri və lokal dəyişənlər
- Instance metodlarda ilk element `this` referansıdır
- Primitiv tiplər birbaşa saxlanır
- Obyektlər üçün yalnız **heap referansı** saxlanır

**Operand Stack:**
- Hesablamalar üçün istifadə olunan müvəqqəti sahə
- Bytecode instruction-ları bu stack-ə push/pop edir

```java
public class StackFrameDemo {
    public static void main(String[] args) {
        // Stack frame: main() — local vars: [args]
        int result = add(3, 5);
        System.out.println(result);
    }

    public static int add(int a, int b) {
        // Stack frame: add() — local vars: [a=3, b=5]
        // Operand stack: iload a → iload b → iadd → ireturn
        int sum = a + b;  // sum da local vars-a əlavə olur: [a=3, b=5, sum=8]
        return sum;
        // Frame məhv edilir, return value əvvəlki frame-in operand stack-inə push olunur
    }

    public void instanceMethod(String name) {
        // Stack frame: instanceMethod() — local vars: [this, name]
        // this həmişə index 0-da olur (instance metodlarda)
        System.out.println("Salam, " + name);
    }
}
```

### Rekursiv Çağırışlar və StackOverflow

```java
public class RecursionDemo {

    // YANLIŞ — sonsuz rekursiya → StackOverflowError
    public static int badFactorial(int n) {
        return n * badFactorial(n - 1); // Baza halı yoxdur!
    }

    // DOĞRU — baza halı olan rekursiya
    public static long factorial(int n) {
        if (n <= 1) return 1; // Baza halı — rekursiya dayanır
        return n * factorial(n - 1);
    }

    // DOĞRU — çox dərin rekursiya üçün iterativ versiya
    public static long factorialIterative(int n) {
        long result = 1;
        for (int i = 2; i <= n; i++) {
            result *= i;
        }
        return result;
    }

    public static void main(String[] args) {
        try {
            badFactorial(100000); // StackOverflowError!
        } catch (StackOverflowError e) {
            System.out.println("Stack doldu: " + e.getMessage());
        }

        System.out.println(factorial(10));           // 3628800
        System.out.println(factorialIterative(20));  // 2432902008176640000
    }
}
```

```bash
# Stack ölçüsünü dəyişmək
java -Xss2m MyApp   # Hər thread üçün 2MB stack (default: 512KB-1MB)
```

---

## Metaspace

**Metaspace** sinif metadata-sını saxlayan sahədir. Java 8-dən əvvəl bu vəzifəni **PermGen (Permanent Generation)** yerinə yetirirdi.

Metaspace-də nə saxlanır:
- Sinif adları, metodlar, sahə təsvirləri
- Constant pool
- Annotation-lar
- JVM daxili məlumat strukturları

### PermGen vs Metaspace

| Xüsusiyyət | PermGen (Java ≤7) | Metaspace (Java 8+) |
|-----------|-------------------|---------------------|
| Yeri | Java Heap-in hissəsi | Native OS memory |
| Default ölçü | 64MB (sabit limit) | Yoxdur (avtomatik böyüyür) |
| OOM mesajı | `PermGen space` | `Metaspace` |
| Tənzimləmə | `-XX:MaxPermSize` | `-XX:MaxMetaspaceSize` |

```java
public class MetaspaceDemo {
    public static void main(String[] args) {
        // Runtime yaddaş məlumatları
        Runtime runtime = Runtime.getRuntime();

        System.out.println("=== Heap Məlumatları ===");
        System.out.printf("Maksimum heap: %d MB%n",
            runtime.maxMemory() / 1024 / 1024);
        System.out.printf("Cari heap: %d MB%n",
            runtime.totalMemory() / 1024 / 1024);
        System.out.printf("Boş heap: %d MB%n",
            runtime.freeMemory() / 1024 / 1024);
        System.out.printf("İstifadə olunan heap: %d MB%n",
            (runtime.totalMemory() - runtime.freeMemory()) / 1024 / 1024);
    }
}
```

```bash
# Metaspace limitini təyin etmək
java -XX:MetaspaceSize=128m -XX:MaxMetaspaceSize=256m MyApp

# Metaspace statistikasını görmək
jstat -gc <pid>
# MC = Metaspace Capacity, MU = Metaspace Used
```

---

## Code Cache

**Code Cache** JIT compiler-in generasiya etdiyi native (machine) kodu saxlayan sahədir.

- Heap-dən kənardadır (native memory)
- Default ölçü: 240MB (Java 8+, tiered compilation ilə)
- Dolduqda JIT compilation dayandırılır — proqram yavaşlayır

```bash
# Code Cache ölçüsünü artırmaq
java -XX:ReservedCodeCacheSize=512m MyApp

# Code Cache statistikasını görmək
jstat -compiler <pid>
```

---

## PC Register

**Program Counter (PC) Register** — hər thread-in hansı bytecode instruction-ını icra etdiyini göstərir.

- Hər thread-in öz PC register-i var
- Native metod icra olunanda PC register **undefined** olur
- JVM spesifikasiyasında ən kiçik yaddaş sahəsidir

---

## Native Method Stack

**Native Method Stack** Java-dan çağırılan C/C++ metodları üçün stack-dir.

- JNI (Java Native Interface) vasitəsilə çağırılan native kodlar burada işləyir
- `java.lang.Thread` sinfi, I/O əməliyyatları, OS API-ları native metodlar istifadə edir

```java
public class NativeMethodDemo {
    // Native metod elanı
    public native void nativeHello();

    static {
        // Native kitabxananı yüklemek
        System.loadLibrary("hello"); // libhello.so (Linux) / hello.dll (Windows)
    }

    // Alternativ: System.nanoTime() da native-dir
    public static void main(String[] args) {
        long start = System.nanoTime(); // Bu native metoddur
        // Bir iş gör
        long end = System.nanoTime();
        System.out.println("Vaxt: " + (end - start) + " ns");
    }
}
```

---

## Yaddaş Xətaları

### OutOfMemoryError növləri

```java
import java.util.ArrayList;
import java.util.List;

public class OOMExamples {

    // 1. Java heap space — Heap dolduqda
    public static void heapOOM() {
        List<byte[]> list = new ArrayList<>();
        while (true) {
            list.add(new byte[1024 * 1024]); // Hər dəfə 1MB əlavə et
            // java.lang.OutOfMemoryError: Java heap space
        }
    }

    // 2. Metaspace — Çox sinif yükləndikdə
    // Adətən sinif yaradan framework-lərdə baş verir (CGLIB, Javassist)
    // java.lang.OutOfMemoryError: Metaspace

    // 3. GC overhead limit exceeded
    // GC vaxtın 98%-ni yeyib, yalnız 2% heap azad edir
    // java.lang.OutOfMemoryError: GC overhead limit exceeded

    // 4. Direct buffer memory (NIO ByteBuffer.allocateDirect)
    public static void directBufferOOM() {
        List<java.nio.ByteBuffer> buffers = new ArrayList<>();
        while (true) {
            buffers.add(java.nio.ByteBuffer.allocateDirect(1024 * 1024));
            // java.lang.OutOfMemoryError: Direct buffer memory
        }
    }

    // 5. Unable to create native thread
    // Çox thread yaradıldıqda OS limit-i keçildikdə
    // java.lang.OutOfMemoryError: unable to create native thread
}
```

### StackOverflowError

```java
public class StackOverflowDemo {

    // YANLIŞ — sonsuz rekursiya
    public static void infiniteRecursion() {
        infiniteRecursion(); // java.lang.StackOverflowError
    }

    // DOĞRU — böyük rekursiya üçün iterativ yanaşma
    public static long sumIterative(long n) {
        long sum = 0;
        for (long i = 1; i <= n; i++) {
            sum += i;
        }
        return sum;
    }

    // DOĞRU — ya da tail recursion (Java-da JVM optimize etmir, amma məntiqi aydındır)
    public static long sumRecursive(long n, long accumulator) {
        if (n <= 0) return accumulator;
        return sumRecursive(n - 1, accumulator + n);
    }

    public static void main(String[] args) {
        try {
            infiniteRecursion();
        } catch (StackOverflowError e) {
            System.out.println("Stack overflow yaşandı!");
        }

        System.out.println("İterativ: " + sumIterative(1_000_000));
        System.out.println("Rekursiv: " + sumRecursive(10_000, 0));
    }
}
```

### Heap Dump Analizi

```bash
# OOM zamanı avtomatik heap dump almaq
java -XX:+HeapDumpOnOutOfMemoryError \
     -XX:HeapDumpPath=/tmp/heapdump.hprof \
     MyApp

# Manuel heap dump
jmap -dump:format=b,file=heapdump.hprof <pid>
jcmd <pid> GC.heap_dump /tmp/heapdump.hprof

# Heap dump-u analiz etmək
# Eclipse Memory Analyzer (MAT) və ya JVisualVM istifadə et
```

### Yaddaş Monitorinqi

```java
import java.lang.management.*;
import java.util.List;

public class MemoryMonitor {
    public static void main(String[] args) {
        MemoryMXBean memoryMXBean = ManagementFactory.getMemoryMXBean();

        // Heap məlumatları
        MemoryUsage heapUsage = memoryMXBean.getHeapMemoryUsage();
        System.out.println("=== Heap Yaddaşı ===");
        System.out.printf("Başlanğıc: %d MB%n", heapUsage.getInit() / 1024 / 1024);
        System.out.printf("İstifadə: %d MB%n", heapUsage.getUsed() / 1024 / 1024);
        System.out.printf("Committeed: %d MB%n", heapUsage.getCommitted() / 1024 / 1024);
        System.out.printf("Maksimum: %d MB%n", heapUsage.getMax() / 1024 / 1024);

        // Non-Heap (Metaspace + Code Cache)
        MemoryUsage nonHeapUsage = memoryMXBean.getNonHeapMemoryUsage();
        System.out.println("\n=== Non-Heap Yaddaşı (Metaspace + Code Cache) ===");
        System.out.printf("İstifadə: %d MB%n", nonHeapUsage.getUsed() / 1024 / 1024);

        // Yaddaş pool-ları
        List<MemoryPoolMXBean> pools = ManagementFactory.getMemoryPoolMXBeans();
        System.out.println("\n=== Yaddaş Pool-ları ===");
        for (MemoryPoolMXBean pool : pools) {
            System.out.printf("%-30s | İstifadə: %6d KB%n",
                pool.getName(),
                pool.getUsage().getUsed() / 1024);
        }
    }
}
```

---

## İntervyu Sualları

**S: Young Generation-da Eden və Survivor space-lərin rolu nədir?**
C: Eden-də yeni obyektlər yaranır. Minor GC-dən sonra sağ çıxan obyektlər boş Survivor space-ə keçir. Hər GC-dən sonra obiekt yaşı (age) artır. Yaş threshold-u keçdikdə (default 15) obyekt Old Gen-ə promote edilir.

**S: Stack-də nə saxlanır, Heap-də nə?**
C: Stack-də: lokal dəyişənlər, metod parametrləri, return addresses, primitiv dəyərlər birbaşa. Heap-də: `new` ilə yaradılan bütün obyektlər. Stack-dəki dəyişənlər isə heapdəki obyektlərə referans saxlayır.

**S: PermGen niyə Metaspace ilə əvəz edildi?**
C: PermGen Java heap-in hissəsiydi, sabit ölçüsü vardı, tez-tez `OutOfMemoryError: PermGen space` verirdi. Metaspace native OS yaddaşından istifadə edir, avtomatik böyüyə bilir, yalnız `-XX:MaxMetaspaceSize` limitlə məhdudlaşdırılır.

**S: StackOverflowError nə zaman baş verir?**
C: Stack-in ölçüsü aşıldıqda — adətən sonsuz rekursiyada. Hər metod çağırışı yeni Stack Frame yaradır. Stack dolduqda (default ~512KB-1MB) JVM `StackOverflowError` atır.

**S: OutOfMemoryError-ın neçə növü var?**
C: Java heap space (heap dolub), Metaspace (sinif metadata-sı üçün yer yoxdur), GC overhead limit exceeded (GC çox vaxt yeyir), Direct buffer memory (NIO), unable to create native thread (OS thread limiti).

**S: Code Cache nədir?**
C: JIT compiler-in generasiya etdiyi native kodu saxlayan native yaddaş sahəsi. Dolduqda JIT compilation dayandırılır, proqram interpreter ilə yavaş işləyir. `-XX:ReservedCodeCacheSize` ilə ölçüsü artırılır.

**S: Bir thread-in stack-i neçə MB-dır?**
C: Default olaraq 512KB-1MB (OS-ə görə dəyişir). `-Xss` flag-i ilə dəyişdirilir, məsələn `-Xss2m` hər thread üçün 2MB stack verir. Çox thread-li tətbiqlərdə bu yaddaşı hesablamaq lazımdır.
