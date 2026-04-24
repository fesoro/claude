# 077 — JVM JIT Compiler
**Səviyyə:** Ekspert


## Mündəricat
1. [JIT nədir?](#jit-nədir)
2. [Interpreted vs Compiled](#interpreted-vs-compiled)
3. [C1 vs C2 Compiler](#c1-vs-c2-compiler)
4. [Tiered Compilation](#tiered-compilation)
5. [Hotspot Aşkarlanması](#hotspot-aşkarlanması)
6. [Method Inlining](#method-inlining)
7. [Escape Analysis](#escape-analysis)
8. [On-Stack Replacement (OSR)](#on-stack-replacement-osr)
9. [Deoptimization](#deoptimization)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## JIT nədir?

**JIT (Just-In-Time) Compiler** — proqram işləyərkən Java bytecode-u native machine code-a çevirən komponentdir.

```
Java Kodu
    ↓ javac (ahead-of-time)
Bytecode (.class)
    ↓ JVM
    ├── Interpreter (sətir-sətir icra)
    └── JIT Compiler (hot kod-u native-ə çevir)
              ↓
        Native Machine Code (çox sürətli)
```

JIT-in məqsədi: **Proqramın ümumi icra sürətini artırmaq** — yavaş başlanğıc (cold start) + sürətli iş (warm).

```java
public class JITDemo {
    // Bu metod tez-tez çağırılırsa, JIT onu compile edəcək
    public static int compute(int n) {
        int result = 0;
        for (int i = 0; i < n; i++) {
            result += i * i;
        }
        return result;
    }

    public static void main(String[] args) {
        // Əvvəlki çağırışlar interpreter ilə işləyir
        // ~10,000 çağırışdan sonra JIT devreye girir
        long start = System.nanoTime();
        for (int i = 0; i < 100_000; i++) {
            compute(1000);
        }
        long elapsed = System.nanoTime() - start;
        System.out.printf("Ümumi: %.2f ms%n", elapsed / 1_000_000.0);
        // JIT warm-up-dan sonra çox sürətli olur
    }
}
```

---

## Interpreted vs Compiled

### Interpretation

```
Bytecode:
  iload_0      (a-nı stack-ə yüklə)
  iload_1      (b-ni stack-ə yüklə)
  iadd         (cəmlə)
  ireturn      (qaytar)

Interpreter: Hər instruction-ı bir-bir oxuyub icra edir
→ Hər çağırışda eyni prosedur
→ Yavaş, amma başlanğıc overhead yoxdur
```

### JIT Compilation

```
Bytecode → C1/C2 compile eder → x86-64 native kod:
  mov eax, [rbp-8]    ; a-nı yüklə
  add eax, [rbp-12]   ; b ilə cəmlə
  ret                 ; qaytar

Native kod: Birbaşa CPU tərəfindən icra edilir
→ Çox sürətli
→ Compile overhead var (bir dəfə)
```

```java
// JIT-in effektini ölçmək — warm-up analizi
public class WarmupDemo {
    static long sum = 0;

    static void hotMethod(int n) {
        for (int i = 0; i < n; i++) {
            sum += i;
        }
    }

    public static void main(String[] args) {
        // İlk iterasiyalar yavaş (interpreter)
        // Sonrakılar sürətli (JIT compiled)
        for (int iteration = 0; iteration < 20; iteration++) {
            long start = System.nanoTime();
            for (int i = 0; i < 1000; i++) {
                hotMethod(1000);
            }
            long elapsed = System.nanoTime() - start;
            System.out.printf("İterasiya %2d: %.2f ms%n", iteration, elapsed / 1_000_000.0);
        }
        // Nəticə: İlk 2-3 iterasiya yavaş, sonra 5-10x sürətlənmə görünür
    }
}
```

---

## C1 vs C2 Compiler

### C1 (Client Compiler)

```
Məqsəd: Sürətli kompilasiya, orta optimizasiya
İstifadə: Tier 1, 2, 3 (bax Tiered Compilation)
Optimizasiyalar:
  - Method inlining (məhdud)
  - Constant folding
  - Null check elimination
  - Range check elimination
Compile vaxtı: Tez (~100 µs)
Code quality: Orta
```

### C2 (Server Compiler / Opto)

```
Məqsəd: Güclü optimizasiya, daha sürətli kod
İstifadə: Tier 4 (ən "hot" metodlar)
Optimizasiyalar:
  - Aggressive method inlining
  - Escape analysis
  - Scalar replacement
  - Loop unrolling
  - Dead code elimination
  - Vectorization (SIMD)
  - Speculative optimizasiyalar
Compile vaxtı: Yavaş (~10ms)
Code quality: Çox yüksək (C++ koduna yaxın)
```

```bash
# JIT statistikalarını görmək
java -XX:+PrintCompilation MyApp
# Nümunə çıxış:
#    123   45     3       java.lang.String::hashCode (67 bytes)
#    456   78     4  %    com.example.MyApp::hotLoop (150 bytes)
#                  ↑↑↑
#                  Tier 4, OSR (%), C2 compiled
#
# Kodlar: 1=C1 Tier 1, 2=C1 Tier 2, 3=C1 Tier 3, 4=C2 Tier 4
# %  = On-Stack Replacement (OSR)
# ! = Exception handler var
# s = Synchronized
# n = Native wrapper

# Daha ətraflı
java -XX:+PrintCompilation -XX:+PrintInlining MyApp
```

---

## Tiered Compilation

**Tiered Compilation** — Java 8-dən default. C1 və C2-ni birləşdirir.

```
Level 0: Interpreter
         Profiling məlumatları toplanır
         ↓ (invocation counter ≥ threshold)

Level 1: C1 — tam compile, profiling yoxdur
         Sadə metodlar üçün (profiling lazım deyil)

Level 2: C1 — tam compile, sayğac profiling
         Invocation counter ilə

Level 3: C1 — tam compile, tam profiling
         Method invocation + branch profiling
         Bu default "hot" üçün başlanğıc
         ↓ (profiling C2-nin hazır olmasını göstərir)

Level 4: C2 — ağır optimizasiya
         Profiling məlumatlarına əsaslanır
         Ən yaxşı performans
```

```
Vizual axış:

Yeni metod                          Sürətli metod (sadə)
    ↓                                     ↓
Level 0 (Interpreter)              Level 1 (C1, no profiling)
    ↓ sayğac                              
Level 3 (C1, full profiling)
    ↓ C2 queue
Level 4 (C2, optimized)
```

```bash
# Tiered compilation-ı söndürmək (nadir)
java -XX:-TieredCompilation MyApp   # Yalnız C2 istifadə et

# Compile threshold
-XX:CompileThreshold=10000  # Tək metodun neçə dəfə çağırılandan sonra compile ediləcəyi
                            # Tiered compilation ilə bu daha mürəkkəb işləyir
```

---

## Hotspot Aşkarlanması

JVM hansı kodun "hot" olduğunu iki sayğac vasitəsilə izləyir:

### İnvocation Counter
- Metodun neçə dəfə çağırıldığını sayır
- Threshold-u keçəndə JIT compile tetiklenir

### Back-edge Counter (Loop Counter)
- Döngünün neçə dəfə döndüyünü sayır
- Uzun döngülər üçün OSR tetikləyir

```java
import java.lang.management.*;
import java.util.*;

public class HotspotDetectionDemo {

    // Bu metod tez-tez çağırılırsa JIT compile edəcək
    static int hotMethod(int x) {
        return x * x + x + 1;
    }

    // Bu döngü çox dövr edərsə OSR tetiklər
    static long hotLoop(int n) {
        long sum = 0;
        for (int i = 0; i < n; i++) { // Back-edge: hər dövrdə artır
            sum += hotMethod(i);
        }
        return sum;
    }

    public static void main(String[] args) {
        // JIT warmup
        long result = 0;
        for (int i = 0; i < 10_000; i++) {
            result += hotMethod(i); // Invocation counter artır
        }
        System.out.println("Hotspot nümunəsi: " + result);

        // JIT-in compile etdiyini yoxlamaq:
        // java -XX:+PrintCompilation HotspotDetectionDemo
    }
}
```

---

## Method Inlining

**Method Inlining** — kiçik metodların çağırıldığı yerdə birbaşa inline edilməsi (metod çağırış overhead-i aradan qalxır).

```java
// Kod (mənbə):
int result = add(a, b);

int add(int x, int y) {
    return x + y;
}

// JIT-dən sonra (inline edilmiş):
int result = a + b;  // Metod çağırışı yox!
```

### Inlining Effekti

```java
public class InliningDemo {

    // Çox kiçik metod — JIT inline edəcək
    private static int square(int x) {
        return x * x;
    }

    // Getter-lər həmişə inline edilir
    private int value;
    public int getValue() { return value; }

    public static void main(String[] args) {
        long sum = 0;
        for (int i = 0; i < 10_000_000; i++) {
            // JIT: square(i) → i*i olaraq inline edir
            sum += square(i);
        }
        System.out.println(sum);
    }

    // Inline olmayan hallar:
    // 1. Çox böyük metodlar (> 35 bytecode, default threshold)
    // 2. Virtual dispatch-li metodlar (amma monomorphic call-site-larda inline olunur)
    // 3. Exception handler-i olan mürəkkəb metodlar
}
```

### Inlining Limitini Tənzimləmək

```bash
# Inlining limit-lərini dəyişmək
-XX:MaxInlineSize=35        # Maksimum inline metod ölçüsü (bytecodes)
-XX:FreqInlineSize=325      # Tez-tez çağırılan metodlar üçün limit
-XX:MaxInlineLevel=9        # Maksimum iç-içə inlining dərinliyi

# Inlining statistikaları
java -XX:+PrintCompilation -XX:+PrintInlining MyApp
```

---

## Escape Analysis

**Escape Analysis** — JIT bir obyektin metoddan kənara "qaçıb-qaçmadığını" analiz edir.

Əgər obyekt metoddan kənara çıxmırsa:
1. **Stack Allocation**: Heap əvəzinə Stack-də yarat (GC lazım deyil!)
2. **Scalar Replacement**: Obyekti sahələrinə bölüb ayrı register-lərə qoy
3. **Lock Elimination**: Synchronized block-u sil (yalnız bir thread görür)

```java
public class EscapeAnalysisDemo {

    record Point(int x, int y) {}

    // YANLIŞ anlayış — bu heap allocation etmir (JIT optimize edir)
    public static int computeDistance(int x1, int y1, int x2, int y2) {
        // JIT escape analysis ilə Point heap-ə yerləşdirilmir!
        // Stack allocation və ya scalar replacement baş verir
        Point p1 = new Point(x1, y1);
        Point p2 = new Point(x2, y2);

        int dx = p2.x() - p1.x();
        int dy = p2.y() - p1.y();
        return dx * dx + dy * dy; // sqrt atmayaq, int saxlayaq

        // JIT bunu belə görür (scalar replacement):
        // int p1_x = x1, p1_y = y1;
        // int p2_x = x2, p2_y = y2;
        // int dx = p2_x - p1_x;
        // ... heap allocation yoxdur!
    }

    // Escape olur — heap allocation lazımdır
    public static Point createPoint(int x, int y) {
        return new Point(x, y); // Metoddan qaçır (return)!
        // Heap-də yaranmalıdır
    }

    // Lock Elimination nümunəsi
    public static String buildString() {
        // StringBuffer synchronized-dir
        // Amma sb metoddan qaçmır → JIT synchronized-i silir!
        StringBuffer sb = new StringBuffer();
        sb.append("Salam");
        sb.append(" Dünya");
        return sb.toString();
        // Sanki: StringBuilder istifadə etmisən (lock overhead yoxdur)
    }

    public static void main(String[] args) {
        // Performans testi
        long start = System.nanoTime();
        long total = 0;
        for (int i = 0; i < 10_000_000; i++) {
            total += computeDistance(0, 0, i, i);
        }
        long elapsed = System.nanoTime() - start;
        System.out.printf("Nəticə: %d, Vaxt: %.2f ms%n", total, elapsed / 1_000_000.0);
    }
}
```

```bash
# Escape Analysis-i aktivləşdirmək/söndürmək
-XX:+DoEscapeAnalysis    # Default aktivdir
-XX:-DoEscapeAnalysis    # Söndür (müqayisə üçün)

# Scalar replacement
-XX:+EliminateAllocations   # Default aktivdir
```

---

## On-Stack Replacement (OSR)

**OSR** — metodun interpreter versiyasından JIT versiyasına **metod çalışarkən** keçmə mexanizmi.

Niyə lazımdır?
```java
// Bu metod yalnız BİR DƏFƏ çağırılır, amma çox uzun döngüsü var
public static void main(String[] args) {
    long sum = 0;
    for (int i = 0; i < 1_000_000_000; i++) {  // 1 milyard dövrü!
        sum += i;
    }
    System.out.println(sum);
}
// main() yalnız 1 dəfə çağırılır → invocation counter heç threshold-a çatmır
// Amma DÖNGÜ çox döndüğü üçün OSR tetiklenir
// Döngü işləyərkən JIT versiyasına keçilir!
```

```bash
# PrintCompilation çıxışında OSR görünür:
# 78    4  %  com.example.MyApp::main @ 16 (87 bytes)
#              ↑   ↑
#              OSR  byte offset (döngünün başı)
```

---

## Deoptimization

**Deoptimization** — JIT-in spekulativ optimizasiyası yanlış çıxdıqda geri qayıtmaq.

### Spekulativ Optimizasiya Nümunəsi

```java
public class DeoptimizationDemo {

    abstract static class Shape {
        abstract double area();
    }

    static class Circle extends Shape {
        double radius;
        Circle(double r) { this.radius = r; }

        @Override
        public double area() { return Math.PI * radius * radius; }
    }

    static class Square extends Shape {
        double side;
        Square(double s) { this.side = s; }

        @Override
        public double area() { return side * side; }
    }

    static double totalArea(Shape[] shapes) {
        double total = 0;
        for (Shape s : shapes) {
            // Əgər loop-da yalnız Circle obyektləri görülübsə,
            // JIT "monomorphic" optim edir: s.area() → Circle.area() inline
            total += s.area();
        }
        return total;
    }

    public static void main(String[] args) {
        // Warm-up: Yalnız Circle-lar
        Shape[] circles = new Shape[1000];
        for (int i = 0; i < circles.length; i++) circles[i] = new Circle(i);

        for (int i = 0; i < 10_000; i++) {
            totalArea(circles); // JIT: "totalArea yalnız Circle görür" → inline
        }

        // İndi Square əlavə etsək → Deoptimization!
        Shape[] mixed = new Shape[1001];
        System.arraycopy(circles, 0, mixed, 0, circles.length);
        mixed[1000] = new Square(5); // Yeni tip! JIT-in fərziyyəsi yanlışdır

        totalArea(mixed); // Deoptimization baş verir
        // JIT: "Artıq polymorphic" → compiler yenidən compile edir
        //       (indi hər ikisi üçün)
    }
}
```

### Deoptimization Növləri

```
1. Uncommon Trap (ən geniş)
   - JIT spekulasiyası yanlış çıxır
   - Exception thrown, NullPointerException, ClassCast

2. Not Entrant
   - Metod artıq "hot" sayılmır
   - Sinif hierarchy dəyişib

3. Make Zombie
   - Compiled kod tamamilə atılır (dead code)
```

```bash
# Deoptimization-ı izləmək
java -XX:+TraceDeoptimization MyApp
# Nümunə:
# Uncommon trap occurred in ..MyApp::totalArea @bci=12 reason=class_check action=maybe_recompile
```

---

## JIT Profiling İpucuları

```java
// JIT benchmark-i düzgün yazmaq — JMH istifadə et
// JMH (Java Microbenchmark Harness): openjdk.java.net/projects/code-tools/jmh/

import org.openjdk.jmh.annotations.*;
import java.util.concurrent.TimeUnit;

@BenchmarkMode(Mode.AverageTime)
@OutputTimeUnit(TimeUnit.NANOSECONDS)
@State(Scope.Thread)
@Warmup(iterations = 5, time = 1)      // 5 warmup iterasiyası (JIT üçün)
@Measurement(iterations = 10, time = 1) // 10 ölçmə iterasiyası
public class JMHBenchmark {

    private int value = 42;

    @Benchmark
    public int withBoxing() {
        Integer boxed = value;  // Autoboxing
        return boxed + 1;
    }

    @Benchmark
    public int withoutBoxing() {
        int primitive = value;  // Primitiv
        return primitive + 1;
    }

    // JMH-i run etmək:
    // mvn clean install
    // java -jar target/benchmarks.jar
}
```

```bash
# JIT compilation-ı deaktiv etmək (benchmark müqayisəsi üçün)
java -Xint MyApp    # Yalnız Interpreter (JIT yoxdur)
java -Xcomp MyApp   # Hər şeyi compile et (JIT, amma cold)

# JIT compilation baş verdikdə log
java -XX:+PrintCompilation -XX:+PrintInlining MyApp 2>&1 | grep hotMethod
```

---

## İntervyu Sualları

**S: JIT nədir və niyə lazımdır?**
C: JIT (Just-In-Time) Compiler — proqram işləyərkən tez-tez icra olunan ("hot") bytecode-u native machine code-a çevirir. Interpreter hər icrada bytecode-u yenidən təfsir edir. JIT bir dəfə compile edərək native kod yaradır — sonrakı icra çox sürətli olur.

**S: C1 və C2 compiler arasındakı fərq?**
C: C1 (Client): Sürətli compile, az optimizasiya — tez başlamaq üçün. C2 (Server): Yavaş compile, güclü optimizasiya (escape analysis, aggressive inlining, vectorization) — maksimum performans üçün. Tiered compilation hər ikisini birləşdirir.

**S: Tiered Compilation necə işləyir?**
C: Level 0 (Interpreter) → Level 3 (C1 + full profiling) → Level 4 (C2, optimized). Sadə metodlar Level 1-ə gedə bilər. C1 profiling məlumatlarını toplayır, C2 bu məlumatlarla spekulativ optimizasiyalar edir.

**S: Method Inlining nədir?**
C: Kiçik metodun çağırıldığı yerdə birbaşa inline edilməsi. Metod çağırış overhead-i (stack frame yaratmaq, parametrləri keçirmək) aradan qalxır. JIT daha dərin optimizasiya edə bilir. `-XX:MaxInlineSize=35` bytecode limit.

**S: Escape Analysis nə üstünlük verir?**
C: Obyektin metoddan kənara çıxmadığını aşkarlayır: 1) Stack Allocation (Heap əvəzinə Stack-də — GC lazım deyil), 2) Scalar Replacement (sahələrə parçala), 3) Lock Elimination (yalnız bir thread görürsə synchronized-i sil).

**S: OSR (On-Stack Replacement) nədir?**
C: Bir metod işləyərkən (döngü içindəyken) interpreter versiyasından JIT compiled versiyasına keçmə. Çox uzun döngüsü olan metodlar üçün lazımdır — metod yalnız bir dəfə çağırılır, amma döngü back-edge counter threshold-unu keçir.

**S: Deoptimization nə zaman baş verir?**
C: JIT-in spekulativ optimizasiyası yanlış çıxanda. Məsələn: JIT "bu call-site yalnız Circle görür" deyib Circle.area()-ı inline etdi, sonra Square gəldi → ClassCastException/uncommon trap → interpreter-ə qayıt → yenidən compile et.
