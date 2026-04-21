# JVM Internals — JIT və GC (Java vs PHP)

## Giriş

Hər iki dil "bytecode + runtime" modeli ilə işləyir, amma daxilində çox fərqlidir. **JVM** uzun-ömürlü runtime-dır — bir dəfə start olur, saatlarla işləyir, bytecode-u kəşf edir, optimize edir, cache edir. **PHP Zend engine** isə tarix boyu "hər sorğu üçün təzədən" prinsipi ilə yaradılıb — opcode-lar yaranır, istifadə olunur, atılır.

JVM-də **JIT compiler** (C1, C2) ilk dəqiqələrdə kodu profil edir, hot method-ları native machine code-a çevirir. **Garbage Collector** (G1, ZGC, Shenandoah) heap-i təmizləyir — pause vaxtını millisaniyələrlə ölçürük. PHP-də isə **OPcache** opcode-ları RAM-da saxlayır, **PHP 8.0 JIT** hot kod üçün tracing JIT əlavə edir, **reference counting** GC obyektləri silir.

Bu fayl göstərir: JVM arxitekturası, class loader hierarchy, JIT tiered compilation, GC seçimləri (G1 default, ZGC Java 21+ generational), memory region-lar (Young/Old/Metaspace), JFR ilə profiling. PHP tərəfdə: Zend engine lifecycle, OPcache preload, PHP JIT trace vs function, reference counting + cycle collector, Blackfire/XHProf.

---

## Java-da istifadəsi

### 1) JVM arxitekturası — ümumi baxış

JVM beş əsas runtime region-dan ibarətdir: **Method Area** (class metadata, Metaspace), **Heap** (obyektlər), **Stack** (hər thread üçün frame), **PC Register** (icra olunan bytecode offset), **Native Stack** (JNI üçün).

```
┌──────────────────────────────────────────┐
│              Class Loader                │
│  (Bootstrap → Platform → Application)    │
└──────────────┬───────────────────────────┘
               ▼
┌──────────────────────────────────────────┐
│           Runtime Data Area              │
│  ┌──────────┐  ┌────────────────────┐    │
│  │ Method   │  │       Heap         │    │
│  │ Area     │  │  ┌──────────────┐  │    │
│  │ (Meta    │  │  │    Young     │  │    │
│  │  space)  │  │  │  ┌─────────┐ │  │    │
│  └──────────┘  │  │  │  Eden   │ │  │    │
│                │  │  ├─────────┤ │  │    │
│  ┌──────────┐  │  │  │ S0 | S1 │ │  │    │
│  │  Stack   │  │  │  └─────────┘ │  │    │
│  │ per      │  │  └──────────────┘  │    │
│  │ thread   │  │  ┌──────────────┐  │    │
│  └──────────┘  │  │     Old      │  │    │
│                │  └──────────────┘  │    │
│  ┌──────────┐  └────────────────────┘    │
│  │ PC Reg   │  ┌────────────────────┐    │
│  └──────────┘  │   Native Stack     │    │
│                └────────────────────┘    │
└──────────────────────────────────────────┘
               ▼
┌──────────────────────────────────────────┐
│       Execution Engine                   │
│  (Interpreter + JIT: C1 → C2)            │
│              + GC (G1, ZGC, Shenandoah)  │
└──────────────────────────────────────────┘
```

Tətbiq start olanda, `main` class-ın bytecode-u class loader tərəfindən Method Area-ya yüklənir. Obyektlər Heap-də allocate olunur. Hər thread-in ayrı Stack-i var — method çağırışlarında frame push/pop olunur.

### 2) Class Loader hierarchy — Bootstrap / Platform / Application

Java 9-dan sonra class loader iyerarxiyası belədir:

```java
// Bootstrap class loader — C++ ilə yazılıb, java.base modulunu yükləyir
// (String, Object, List kimi core class-lar)
ClassLoader bootstrap = String.class.getClassLoader();
// null qaytarır (native loader)

// Platform class loader (Java 9+, əvvəl "Extension" adlanırdı)
// javax.sql, java.xml, java.crypto kimi platform modulları
ClassLoader platform = ClassLoader.getPlatformClassLoader();

// Application class loader — user code (CLASSPATH-dən yüklənir)
ClassLoader app = ClassLoader.getSystemClassLoader();

// Custom class loader — plugin, hot-reload üçün
URLClassLoader pluginLoader = new URLClassLoader(
    new URL[]{new File("plugin.jar").toURI().toURL()},
    app
);
Class<?> pluginClass = pluginLoader.loadClass("com.example.Plugin");
```

**Delegation prinsipi:** class yükləmək tələbi gəldikdə, loader əvvəl parent-dən soruşur — Bootstrap → Platform → Application. Əgər parent tapmadısa, özü axtarır. Bu, `java.lang.String` kimi class-ların şəxsi versiyasının yüklənməsinin qarşısını alır.

### 3) Class loading prosesi — Load, Link, Initialize

Class üç addımda yüklənir:

**1. Load (yüklə):** `.class` fayl disk/network/jar-dan oxunur, bytecode Method Area-ya yerləşir.

**2. Link (əlaqələndir):**
- **Verify:** bytecode düzgünlüyü yoxlanılır (stack balance, type check, access control)
- **Prepare:** static field-lər default dəyərlərlə yaradılır (0, null, false)
- **Resolve:** symbolic reference-lər (string-də class adları) real reference-lərə çevrilir

**3. Initialize:** static initializer-lər (`<clinit>`) işə salınır, static field-lərə real dəyərlər verilir.

```java
public class Config {
    // Prepare mərhələsində 0-dır, Initialize mərhələsində 42 olur
    public static final int MAX_SIZE = 42;

    static {
        System.out.println("Config initialize olundu");
        // <clinit>() method-una tərcümə olunur
    }
}

// İlk dəfə Config.MAX_SIZE istifadə ediləndə, initialize işə düşür
int x = Config.MAX_SIZE;
// Konsola: "Config initialize olundu"
```

Lazy loading: class yalnız lazım olanda initialize olur — `Class.forName("com.example.X")` və ya first static access zamanı.

### 4) Bytecode və `javap` ilə baxmaq

`.java` faylları `.class` faylına compile olunur — bu isə stack-based bytecode-dur.

```java
// Hello.java
public class Hello {
    public static int add(int a, int b) {
        return a + b;
    }
}
```

```bash
javac Hello.java
javap -c Hello
```

```
public static int add(int, int);
  Code:
     0: iload_0        // lokal dəyişən a-nı stack-ə yüklə
     1: iload_1        // lokal dəyişən b-ni stack-ə yüklə
     2: iadd           // iki int-i topla
     3: ireturn        // nəticəni qaytar
```

JVM stack-based maşındır — registerlər yoxdur, hər əməliyyat operand stack ilə işləyir.

### 5) JIT — Tiered Compilation (C1 + C2)

JVM bytecode-u iki yolla icra edir:
- **Interpreter:** bytecode-u bir-bir oxuyur, yavaş amma start-up tez
- **JIT (Just-In-Time) compiler:** hot method-ları native machine code-a çevirir

Java 8-dən etibarən **Tiered Compilation** default-dur — kod aşağıdakı mərhələlərdən keçir:

```
Tier 0: Interpreter (profiling olmadan)
   ↓ (method 10K dəfə çağırıldı)
Tier 1-3: C1 compiler (sürətli compile, az optimize, profile yığır)
   ↓ (kifayət qədər profile yığıldı)
Tier 4: C2 compiler (aqressiv optimize, inlining, escape analysis)
```

```java
// Hot method nümunəsi
public long sumSquares(int n) {
    long sum = 0;
    for (int i = 1; i <= n; i++) {
        sum += (long) i * i;   // tez-tez çağırılacaq
    }
    return sum;
}
```

```bash
# JIT compilation log göstər
java -XX:+PrintCompilation -XX:+UnlockDiagnosticVMOptions \
     -XX:+PrintInlining Main

# Nümunə output:
#     52   1       3       Main::sumSquares (15 bytes)
#    180   1       4       Main::sumSquares (15 bytes)
#    180   1       3       Main::sumSquares (15 bytes)   made not entrant
```

Ikinci sütun: kompilyasiya ID, üçüncü: tier (3=C1, 4=C2), "made not entrant" — köhnə versiya ləğv oldu.

### 6) Method Inlining və Escape Analysis

**Inlining:** kiçik method-lar çağırış yerində əvəz olunur — overhead yox olur.

```java
// Əvvəl
public int compute(int x) {
    return square(x) + 1;
}

private int square(int x) { return x * x; }

// C2 inline etdikdən sonra (JIT-in daxili qərarı)
public int compute(int x) {
    return x * x + 1;   // square() çağırışı yoxdur
}
```

**Escape Analysis:** JIT analiz edir ki, obyekt method-dan kənara "qaçır"mı. Əgər qaçmırsa, heap-də allocate olmur — stack-də və ya scalar replacement-lə yaradılır.

```java
public int distance(int x1, int y1, int x2, int y2) {
    Point p1 = new Point(x1, y1);    // bu Point method-dan çıxmır
    Point p2 = new Point(x2, y2);    // bu da
    int dx = p2.x - p1.x;
    int dy = p2.y - p1.y;
    return (int) Math.sqrt(dx * dx + dy * dy);
}
// C2 bu obyektləri stack-ə yerləşdirir — GC basqısı yox olur
```

JIT diagnostic-i ilə görmək:

```bash
java -XX:+PrintCompilation \
     -XX:+UnlockDiagnosticVMOptions \
     -XX:+PrintEscapeAnalysis \
     -XX:+PrintEliminateAllocations \
     Main
```

### 7) Garbage Collectors — G1, ZGC, Shenandoah, Parallel, Serial

Java 9+ default GC **G1** (Garbage-First)-dir. Java 21+ ZGC generational oldu — kiçik pause-larla böyük heap idarə edə bilir.

| GC | Hədəf | Pause | Heap ölçüsü |
|---|---|---|---|
| Serial | Kiçik heap, single thread | >100ms | <100MB |
| Parallel | Throughput | 100-200ms | <4GB |
| G1 | Balanced, default (Java 9+) | 10-50ms | 4-32GB |
| ZGC (Java 21+ gen) | Ultra-low pause | <1ms | 8GB-16TB |
| Shenandoah | Low pause, Red Hat | <10ms | 4GB-1TB |

```bash
# G1 (default) — pause target 200ms
java -XX:+UseG1GC -XX:MaxGCPauseMillis=200 -Xmx4g Main

# ZGC — Java 21-də generational, Java 24-də daha da yaxşılaşdı
java -XX:+UseZGC -XX:+ZGenerational -Xmx16g Main

# Shenandoah
java -XX:+UseShenandoahGC -Xmx8g Main

# Parallel (throughput-heavy)
java -XX:+UseParallelGC -Xmx4g Main

# Serial (container-də ən balaca tətbiqlər üçün)
java -XX:+UseSerialGC -Xmx512m Main
```

### 8) Memory Region-lar — Young, Old, Metaspace

**Young Generation** yeni obyektlər üçündür:
- **Eden:** yeni obyektlər burada yaranır
- **Survivor 0 / Survivor 1:** minor GC-dən sağ çıxan obyektlər bura köçürülür (copying GC)

**Old Generation (Tenured):** uzun-ömürlü obyektlər (~N minor GC-dən sağ çıxanlar) bura köçürülür.

**Metaspace** (Java 8+): class metadata (əvvəl PermGen idi) — native memory-də yerləşir.

```
Heap (Xmx=4g):
  Young (~1.3g):
    Eden (~1g)
    Survivor 0 (~150m)
    Survivor 1 (~150m)
  Old (~2.7g)

Metaspace (native, default sınırsız):
  Class metadata, method tables, konstant pool
```

Lifecycle:

```
1. new Object() → Eden
2. Minor GC → sağ qalanlar → Survivor 0
3. Növbəti minor GC → Survivor 0 obyektləri → Survivor 1
4. 15 dəfə (default MaxTenuringThreshold) sağ qaldıqdan sonra → Old
5. Old doldu → Major GC (Full GC)
```

### 9) GC tuning və heap sizing

```bash
# Heap sizing
-Xms2g               # başlanğıc heap
-Xmx8g               # maksimum heap
-Xmn2g               # Young size (ya da -XX:NewRatio=2)

# G1 tuning
-XX:MaxGCPauseMillis=100          # pause target
-XX:G1HeapRegionSize=16m          # region size (1-32m)
-XX:InitiatingHeapOccupancyPercent=45   # concurrent cycle nə vaxt başlasın

# Metaspace
-XX:MetaspaceSize=256m            # başlanğıc
-XX:MaxMetaspaceSize=512m         # maksimum (default sınırsız — OOM riski)

# GC log (Java 9+)
-Xlog:gc*:file=gc.log:time,uptime:filecount=5,filesize=10m
```

Container-də JVM avtomatik cgroup limit-ini oxuyur (Java 10+):

```bash
java -XX:MaxRAMPercentage=75 -jar app.jar
# Container limit 4GB-dirsə, heap 3GB
```

### 10) JFR — Java Flight Recorder

JFR aşağı overhead-li profiler-dir — production-da istifadə oluna bilər (~1-2% overhead).

```bash
# JFR record başlat (60 saniyə)
jcmd <pid> JFR.start duration=60s filename=recording.jfr

# Status
jcmd <pid> JFR.check

# Dayandır
jcmd <pid> JFR.stop name=<recording-id>

# Və ya start-up-da
java -XX:StartFlightRecording=duration=60s,filename=startup.jfr,settings=profile Main
```

JFR fayl JDK Mission Control (JMC) GUI ilə açılır — method hotspot, GC pause, thread state, allocation rate göstərir.

### 11) Diagnostic alətləri — jcmd, jstat, jmap, jstack

```bash
# Run olunan JVM-lərin siyahısı
jps -l

# Heap statistika (sütun: S0, S1, Eden, Old, Metaspace, minor GC sayı, ...)
jstat -gc <pid> 1000 10
#  S0C    S1C    S0U    S1U      EC       EU        OC         OU       MC     MU    CCSC   CCSU   YGC     YGCT    FGC    FGCT     GCT
# 8192.0 8192.0  0.0    0.0    65536.0  32768.0   147456.0   12288.0  21120.0 20456 2432.0 2222.2   12    0.182     1    0.052    0.234

# Heap dump (OOM analysis üçün)
jmap -dump:live,format=b,file=heap.hprof <pid>

# Obyekt statistika
jmap -histo:live <pid> | head -20

# Thread dump
jstack <pid> > threads.txt

# Class loading info
jcmd <pid> VM.classloaders

# GC-i əl ilə tetiklə (test üçün)
jcmd <pid> GC.run

# Native memory tracking
java -XX:NativeMemoryTracking=summary -jar app.jar
jcmd <pid> VM.native_memory summary
```

### 12) Production nümunəsi — Spring Boot tuning

```bash
java \
  -server \
  -XX:+UseG1GC \
  -XX:MaxGCPauseMillis=100 \
  -XX:MaxRAMPercentage=75 \
  -XX:+HeapDumpOnOutOfMemoryError \
  -XX:HeapDumpPath=/var/log/app/heap.hprof \
  -XX:+ExitOnOutOfMemoryError \
  -Xlog:gc*:file=/var/log/app/gc.log:time,uptime:filecount=5,filesize=10m \
  -XX:StartFlightRecording=duration=5m,filename=/var/log/app/startup.jfr,settings=default \
  -jar myapp.jar
```

---

## PHP-də istifadəsi

### 1) Zend Engine lifecycle — hər sorğu üçün

PHP-FPM-də hər HTTP sorğu üçün Zend engine bu addımları edir:

```
1. Lexing: .php fayl → token stream
2. Parsing: tokens → AST (Abstract Syntax Tree)
3. Compilation: AST → opcode (Zend bytecode)
4. Execution: opcode-lar interpret olunur
5. Shutdown: bütün yaddaş təmizlənir (request-scoped)
```

Nümunə opcode baxmaq üçün **VLD** extension və ya **OPcache** ilə:

```bash
php -d vld.active=1 -d vld.execute=0 script.php
```

```php
// script.php
<?php
$x = 10;
$y = 20;
echo $x + $y;
```

```
line  #* E I O op           fetch      ext  return  operands
-------------------------------------------------------------
   3     0  E >   ASSIGN                                  !0, 10
   4     1        ASSIGN                                  !1, 20
   5     2        ADD                              ~4     !0, !1
         3        ECHO                                    ~4
         4      > RETURN                                   1
```

### 2) OPcache — opcode-ları RAM-da saxla

OPcache-siz PHP hər sorğu üçün hər faylı yenidən compile edir — 10-50ms overhead. OPcache bu opcode-ları shared memory-də saxlayır.

```ini
; php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256          ; MB
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0           ; production-da faylları yoxlama
opcache.revalidate_freq=0
opcache.save_comments=1                 ; annotation-lar üçün
opcache.fast_shutdown=1
opcache.jit_buffer_size=100M
opcache.jit=tracing                     ; PHP 8+
```

Status yoxlamaq:

```php
<?php
// opcache-status.php
$status = opcache_get_status();

echo "Hit rate: " . $status['opcache_statistics']['opcache_hit_rate'] . "%\n";
echo "Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
echo "Memory used: " . ($status['memory_usage']['used_memory'] / 1024 / 1024) . " MB\n";
```

### 3) OPcache preload — PHP 7.4+

Preload ilə bütün framework kodu JVM-də olduğu kimi RAM-a "pinned" olur — hər sorğu üçün autoload lazım olmur.

```ini
; php.ini
opcache.preload=/var/www/preload.php
opcache.preload_user=www-data
```

```php
<?php
// preload.php
$dir = __DIR__ . '/vendor/laravel/framework/src';

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir)
);

foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        opcache_compile_file($file->getPathname());
    }
}
```

Nəticə: Laravel üçün ~30-40% boot sürəti artımı.

### 4) PHP 8.0 JIT — Tracing və Function mode

PHP 8-də Zend engine-ə JIT compiler əlavə olundu — hot kod native x86_64 və ya ARM64 machine code-a çevrilir.

```ini
opcache.jit_buffer_size=256M
opcache.jit=tracing     ; 1254 — ən aqressiv (default)
; opcache.jit=function  ; 1205 — funksiya-səviyyəsində
; opcache.jit=disable
```

JIT flag formatı: `CRTO` (Compiler, Register, Trigger, Optimization):

```
opcache.jit=1254
  1: trigger on request
  2: trigger on first execution
  5: tracing JIT
  4: full optimization (type inference, inlining)
```

**Tracing JIT** hot loop-ları tapır, path-i native code-a çevirir — CPU-bound kod üçün 2-3x sürət artımı verir. Web sorğu üçün təsir az olur (I/O dominantdır).

```php
// JIT-dən fayda görəcək kod (Mandelbrot, simulyasiya, image processing)
function mandelbrot(int $maxIter, int $width, int $height): array
{
    $result = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $c_re = ($x - $width/2) * 4.0 / $width;
            $c_im = ($y - $height/2) * 4.0 / $width;
            $z_re = 0; $z_im = 0;
            $i = 0;
            while ($z_re*$z_re + $z_im*$z_im < 4.0 && $i < $maxIter) {
                $new_re = $z_re*$z_re - $z_im*$z_im + $c_re;
                $z_im = 2.0 * $z_re * $z_im + $c_im;
                $z_re = $new_re;
                $i++;
            }
            $result[$y][$x] = $i;
        }
    }
    return $result;
}

// JIT-siz: 4.2s
// JIT tracing: 1.8s (~2.3x)
```

### 5) Reference Counting GC + Cycle Collector

PHP obyektləri **reference counting** ilə idarə edir — hər obyektin `refcount` var. 0 olduqda dərhal silinir.

```php
<?php
$a = new Person();   // refcount = 1
$b = $a;             // refcount = 2
unset($a);           // refcount = 1
unset($b);           // refcount = 0 → dərhal silinir, destructor çağırılır
```

Problem: **cycle** (dövrü reference):

```php
<?php
class Node {
    public ?Node $child = null;
    public ?Node $parent = null;
}

$parent = new Node();
$child = new Node();
$parent->child = $child;
$child->parent = $parent;   // dövrü reference!

unset($parent);
unset($child);
// refcount heç zaman 0 olmur — sızır
```

PHP-də **cycle collector** dövrü periodik olaraq aşkar edir:

```php
// cycle-ları manual-tetiklə
gc_collect_cycles();

// statistika
var_dump(gc_status());
// array(5) {
//   ["runs"]=>          int(12)
//   ["collected"]=>     int(340)
//   ["threshold"]=>     int(10001)
//   ["roots"]=>         int(500)
// }

gc_disable();   // GC-ni söndür (batch job üçün faydalı)
gc_enable();
```

### 6) Memory management — request-scoped

PHP `memory_limit` sorğu başına (PHP-FPM) və ya worker başına (Octane) limit qoyur:

```ini
memory_limit = 256M
```

Hər sorğunun sonunda Zend engine bütün request-scoped yaddaşı təmizləyir — Java-dakı Old Gen kimi uzun-ömürlü yaddaş yoxdur (stay-alive runtime olmayanda).

```php
<?php
// memory izləmə
echo "Start: " . memory_get_usage() . "\n";

$big = array_fill(0, 1_000_000, 'data');

echo "After fill: " . memory_get_usage() . "\n";
echo "Peak: " . memory_get_peak_usage() . "\n";

unset($big);
gc_collect_cycles();

echo "After unset: " . memory_get_usage() . "\n";
```

### 7) Autoload vs Class loading

PHP-də JVM-dəki kimi class loader hierarchy yoxdur — sadəcə **autoload** funksiyaları var. Composer PSR-4 default-dur.

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "MyLib\\": "lib/"
        },
        "classmap": ["database/seeders"],
        "files": ["helpers.php"]
    }
}
```

```bash
# Production üçün optimized autoload (hash lookup, no filesystem check)
composer dump-autoload --optimize --classmap-authoritative
```

```php
<?php
// vendor/autoload.php daxildə:
spl_autoload_register(function ($class) {
    $file = str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

$user = new App\Models\User();   // autoload işə düşür
```

### 8) Profiling alətləri — OPcache, Blackfire, XHProf

**OPcache status:**

```php
$status = opcache_get_status();
$scripts = opcache_get_configuration();

// Hit rate, memory, script sayı
```

**XHProf** (Facebook-un aləti):

```bash
pecl install xhprof
```

```php
<?php
xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

// kod işlə...

$data = xhprof_disable();

$XHPROF_ROOT = "/var/www/xhprof";
include_once "$XHPROF_ROOT/xhprof_lib/utils/xhprof_lib.php";
include_once "$XHPROF_ROOT/xhprof_lib/utils/xhprof_runs.php";

$runs = new XHProfRuns_Default();
$runId = $runs->save_run($data, "myapp");
// http://localhost/xhprof_html/?run=$runId&source=myapp
```

**Blackfire** (production profiler):

```bash
# CLI profile
blackfire run php artisan queue:work --once
# web üzərindən trigger
```

```php
// Program kodda
$probe = \BlackfireProbe::getMainInstance();
$probe->enable();
// kod
$probe->close();
```

### 9) Zend engine vs JVM — opcodes

```php
// src.php
<?php
function add(int $a, int $b): int {
    return $a + $b;
}

echo add(10, 20);
```

Zend opcodes:

```
function add:
  0  RECV                 $a
  1  RECV                 $b
  2  ADD        ~2        !0, !1
  3  VERIFY_RETURN_TYPE   ~2
  4  RETURN               ~2

main:
  0  INIT_FCALL           'add'
  1  SEND_VAL             10
  2  SEND_VAL             20
  3  DO_UCALL             $0
  4  ECHO                 $0
  5  RETURN               1
```

JVM stack-based-dır, Zend isə register-ish (virtual register `!0`, `!1` lokal dəyişənlərdir).

### 10) Octane-də memory leaks

Stay-alive runtime-də (Octane, Swoole) Zend engine worker-də canlı qalır — memory leak mümkündür:

```php
// Problem: static siyahı artır
class UserCache
{
    private static array $cache = [];

    public function remember(int $id): User
    {
        if (!isset(self::$cache[$id])) {
            self::$cache[$id] = User::find($id);
        }
        return self::$cache[$id];
    }
}

// Hər worker üçün $cache uzun müddətdə böyüyür — OOM
```

Çözüm: `max_requests` limit (worker-i restart et):

```bash
php artisan octane:start --workers=8 --max-requests=500
```

---

## Əsas fərqlər

| Xüsusiyyət | Java (JVM) | PHP (Zend) |
|---|---|---|
| Runtime lifecycle | Uzun-ömürlü, saatlarla | Request-scoped (PHP-FPM) |
| Bytecode | `.class` fayl, disk-də | Opcodes, OPcache RAM-da |
| Class loader | Bootstrap → Platform → App hierarchy | Composer autoload (PSR-4) |
| Class linking | Verify + Prepare + Resolve | Lazy require, symbol table-də |
| JIT | C1 + C2 tiered compilation | Tracing/Function JIT (PHP 8+) |
| JIT trigger | 10K invocation (default) | `opcache.jit_hot_func` (default 127) |
| Method inlining | C2 inline kiçik method-ları | PHP JIT inline bəzi funksiyaları |
| Escape analysis | Var, scalar replacement | Yox |
| GC default | G1 (Java 9+) | Reference counting + cycle collector |
| Low-pause GC | ZGC generational (Java 21+) | Yoxdur (request-end hər şey silinir) |
| Memory regions | Young/Old/Metaspace | Request arena (birləşik) |
| Heap ölçüsü | -Xmx (GB-larla) | memory_limit per-sorğu (MB-larla) |
| Preload | AOT (GraalVM), AppCDS | OPcache preload (PHP 7.4+) |
| Profiling | JFR, Mission Control | Blackfire, XHProf, Tideways |
| Diagnostic | jcmd, jstat, jmap, jstack | opcache_get_status, Xdebug |
| Heap dump | jmap → .hprof → Eclipse MAT | Yok (memory tracking external) |
| Native compile | GraalVM native-image (AOT) | Yoxdur |
| Thread support | Platform + Virtual threads | Thread yox (Fibers single-thread) |

---

## Niyə belə fərqlər var?

**Uzun-ömürlü vs request-scoped tarix.** Java enterprise server (Tomcat, WebLogic, JBoss) üçün dizayn olunub — JVM uzun işləyir, JIT profile edir, GC tuning edir. PHP isə web shared hosting üçün yaradılıb — hər sorğu təmiz başlayır, state sızmır, fatal error digər sorğuları əzmir. Bu fərq hər şeyə təsir edir.

**JIT-in məqsədi fərqli.** JVM-də JIT sistemin qəlbidir — hər tətbiq gec-tez C2-yə çatır, CPU-bound kod nativ sürətdə işləyir. PHP-də isə JIT (8.0+) üstünlüyü əsasən CPU-bound hesablamalarda görünür — web tətbiq (95% I/O) üçün təsiri kiçikdir. PHP JIT bazar payı genişləndikcə iş görür, amma dilin "həyat tərzi" dəyişmədi.

**GC fəlsəfəsi.** JVM tracing GC istifadə edir — heap-i periodik tarar, reachable obyektləri tapır. Bu, cycle-ları avtomatik həll edir, amma pause-lar yaradır — ZGC/Shenandoah bu pause-ları millisaniyələrlə azaldır. PHP isə reference counting-i seçib — obyekt refcount sıfır olanda dərhal silinir, pause yoxdur, amma cycle-lar cycle collector tələb edir. Refcount atomic deyildir (PHP single-thread) — thread-safety problemi yoxdur.

**Class loading vs autoload.** JVM class loader hierarchy-si plugin, modul, isolation üçün güclü mexanizmdir — custom `URLClassLoader` ilə tətbiqləri tamamilə izolasiya edə bilərsiniz. PHP-də bu səviyyə yoxdur — autoload bir funksiyadır, file yükləyir, daxil edir. Simple amma məhdud.

**Memory model.** JVM heap GB-larla ola bilir (production 16GB+ adi), obyektlər bir neçə GC cycle-dan sonra Old Gen-ə köçür. PHP isə request başına bir neçə MB-lik arena-da işləyir, sorğu sonunda hər şey silinir. Octane bu modeli dəyişir — amma memory leak vasitəsi olur.

**Optimization köməkçisi.** JVM escape analysis, scalar replacement, speculative optimization edir — bunlar dinamik dilin ağır optimizasiyalarıdır. PHP 8 JIT də bu yolda — tracing JIT hot path-ları native koda çevirir, amma JVM-in on illik inkişafı hələ qabaqdadır.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- Class loader hierarchy (Bootstrap/Platform/Application/Custom)
- Tracing GC (G1, ZGC, Shenandoah) — cycle-lar avtomatik
- Generational ZGC (Java 21+)
- Tiered compilation (C1 → C2)
- Escape analysis, scalar replacement
- JFR (Java Flight Recorder) — production profiler
- `jcmd`, `jstat`, `jmap`, `jstack` diagnostic
- Heap dump + Eclipse MAT analizi
- GraalVM native-image (AOT compile)
- Metaspace (class metadata native memory)
- Native memory tracking
- Thread-local + Scoped Values (Java 21+)
- JMM (Java Memory Model) — memory visibility qaydaları
- Platform + Virtual threads

**Yalnız PHP-də:**
- OPcache preload (PHP 7.4+)
- Tracing JIT (PHP 8.0+) — opcache.jit=tracing
- Function JIT mode
- `opcache_get_status()`, `opcache_compile_file()`
- Reference counting + explicit `gc_collect_cycles()`
- Request-scoped memory arena (hər sorğu təmiz)
- Composer PSR-4 autoload (standart)
- Blackfire, XHProf, Tideways profilers
- Zend extensions (C ilə yazılmış) — Swoole, Redis, PDO
- FPM pool management (`pm.max_children`, `pm.max_requests`)
- `memory_get_usage()`, `memory_get_peak_usage()`

---

## Best Practices

**Java tərəfdə:**
- Default GC-i G1 saxlayın (Java 9+), pause target 100-200ms
- Heap 16GB+ olanda ZGC generational (Java 21+) sınayın
- `-XX:MaxRAMPercentage=75` container-də kullanın (Xmx əvəzinə)
- Production-da JFR həmişə açıq olsun (~1% overhead)
- OOM üçün `-XX:+HeapDumpOnOutOfMemoryError` set edin
- `-XX:+ExitOnOutOfMemoryError` ilə process-i dayandırın (Kubernetes restart etsin)
- Metaspace üçün `MaxMetaspaceSize` qoyun — defaultsız limit OOM olur
- Startup sürəti üçün AppCDS və ya GraalVM native-image
- JIT-i `-XX:+PrintCompilation` ilə profile edin (development)
- Thread dump `jstack` ilə production debug
- Heap histogram `jmap -histo:live` ilə memory leak tap

**PHP tərəfdə:**
- OPcache həmişə enable olsun (production-da `validate_timestamps=0`)
- OPcache preload ilə framework-u RAM-a pin (PHP 7.4+)
- JIT-i CPU-bound tətbiqlər (image, PDF, simulation) üçün sınayın
- Web tətbiq üçün JIT kiçik effekt verir — ilk prioritet OPcache-dir
- Octane workers üçün `max_requests=500` limit qoyun (memory leak)
- Memory leak axtararkən `gc_status()`, `memory_get_peak_usage()`
- Cycle `unset()` etməyin — refcount cycle detection lazımdır
- Blackfire production-da sample profiling ilə
- XHProf dev-də detaylı call graph üçün
- `composer dump-autoload --optimize` production-da
- PHP-FPM `pm.max_requests` memory leak-i reset edir

---

## Yekun

- JVM uzun-ömürlüdür — class loader hierarchy, JIT tiered compilation, tracing GC. PHP Zend engine request-scoped-dır — hər sorğu opcodes yaranır, OPcache saxlayır, sorğu sonunda yaddaş silinir
- Java class loading: Load → Link (Verify/Prepare/Resolve) → Initialize. PHP-də Composer PSR-4 autoload
- JIT: JVM-də C1 + C2 tiered, C2 escape analysis edir. PHP 8+ tracing JIT (hot path), function JIT alternative
- GC: G1 default (Java 9+), ZGC generational (Java 21+) ultra-low pause. PHP reference counting + cycle collector
- Memory: Young (Eden+Survivor) → Old → Metaspace. PHP-də request arena (birləşik yaddaş)
- Diagnostic: JVM `jcmd`, `jstat`, `jmap`, `jstack`, JFR. PHP `opcache_get_status()`, Blackfire, XHProf
- Heap dump + Eclipse MAT Java-da dominant. PHP-də bu səviyyə alət yoxdur
- Octane/Swoole PHP-i stay-alive edir, amma memory leak problemi yaradır — `max_requests` limit vacibdir
- GraalVM native-image JVM üçün AOT compile verir. PHP-də analoq yoxdur
- JVM 16GB+ heap-i idarə edə bilir (ZGC ilə), PHP request başına 256-512MB limitdə işləyir
