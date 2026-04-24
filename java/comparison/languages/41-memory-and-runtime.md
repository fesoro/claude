# Memory ve Runtime — Java vs PHP

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giris

Java ve PHP-nin esas arxitektura ferqi onlarin **runtime modeli**nde gizlenir. Java uzun muddət ishleyen (long-running) bir proses olaraq JVM uzerinde isleyir. PHP ise her sorgu ucun bashlayib biten (request-based), **shared-nothing** bir model istifade edir. Bu ferq yaddash idareetmesi, garbage collection, performans ve olceklenme strategiyalarini koklunden deyishir.

---

## Java-da istifadesi

### JVM Arxitekturasi

Java kodu birbashe emeliyyat sistemi terefinden icra olunmur. Evvelce **bytecode**-a kompilyasiya olunur, sonra **JVM** (Java Virtual Machine) terefinden icra edilir:

```
Java Menbe Kodu (.java)
        ↓  javac (kompilyator)
Bytecode (.class)
        ↓
    JVM (Java Virtual Machine)
        ↓
  ┌─────────────────────────────────┐
  │  Class Loader → Bytecode yükle  │
  │         ↓                       │
  │  Bytecode Verifier → yoxla      │
  │         ↓                       │
  │  Interpreter → icra et          │
  │         ↓                       │
  │  JIT Compiler → hot code-u      │
  │  native koda cevir              │
  │         ↓                       │
  │  Native Kod → birbashe CPU-da   │
  └─────────────────────────────────┘
```

### JIT (Just-In-Time) Compilation

JVM bytecode-u evvelce interpretasiya edir, amma cox caghirilan ("hot") metodlari native mashin koduna cevirir:

```java
public class JitExample {
    // Bu metod milyonlarla defe caghirilirsa,
    // JVM onu native koda cevirecek (JIT compile)
    public static int fibonacci(int n) {
        if (n <= 1) return n;
        return fibonacci(n - 1) + fibonacci(n - 2);
    }

    public static void main(String[] args) {
        // Ilk caghirishlar yavash (interpreted)
        // Sonraki caghirishlar suretli (JIT compiled)
        for (int i = 0; i < 1_000_000; i++) {
            fibonacci(20);
        }
    }
}
```

JVM-in JIT compiler xususiyyetleri:
- **C1 Compiler** (Client) — sürətli kompilyasiya, az optimizasiya
- **C2 Compiler** (Server) — yavash kompilyasiya, cox optimizasiya
- **Tiered Compilation** — evvelce C1, sonra hot methodlar ucun C2
- **GraalVM** — daha muasir JIT ve AOT (Ahead-of-Time) kompilyasiya

### Heap ve Stack yaddashi

```
JVM Yaddash Modeli:
┌──────────────────────────────────────────────┐
│                   JVM                         │
│                                               │
│  ┌─────────────────────────────────────────┐  │
│  │              HEAP (paylashilan)         │  │
│  │  ┌──────────┐  ┌───────────────────┐   │  │
│  │  │  Young    │  │      Old          │   │  │
│  │  │Generation │  │   Generation      │   │  │
│  │  │┌────┐┌──┐│  │                   │   │  │
│  │  ││Eden││S1││  │  (uzun omurlu     │   │  │
│  │  │└────┘│S2││  │   obyektler)      │   │  │
│  │  │      └──┘│  │                   │   │  │
│  │  └──────────┘  └───────────────────┘   │  │
│  └─────────────────────────────────────────┘  │
│                                               │
│  ┌────────┐  ┌────────┐  ┌────────┐          │
│  │Stack-1 │  │Stack-2 │  │Stack-3 │          │
│  │(Thread1)│  │(Thread2)│  │(Thread3)│         │
│  │┌──────┐│  │┌──────┐│  │┌──────┐│          │
│  ││Frame ││  ││Frame ││  ││Frame ││          │
│  ││Frame ││  ││Frame ││  ││Frame ││          │
│  ││Frame ││  ││      ││  ││      ││          │
│  │└──────┘│  │└──────┘│  │└──────┘│          │
│  └────────┘  └────────┘  └────────┘          │
│                                               │
│  ┌─────────────────────────────────────────┐  │
│  │  Metaspace (sinif metadata, Java 8+)    │  │
│  └─────────────────────────────────────────┘  │
└──────────────────────────────────────────────┘
```

**Heap** — butun obyektler burada saxlanir, butun thread-ler paylashir:

```java
public class MemoryExample {

    public static void main(String[] args) {
        // "salam" String obyekti HEAP-de yaranir
        // str1 referansi STACK-de saxlanir
        String str1 = new String("salam");

        // arr referansi STACK-de, array obyekti HEAP-de
        int[] arr = new int[1000];

        // Primitiv tipler STACK-de saxlanir
        int x = 42;        // STACK
        double y = 3.14;   // STACK
        boolean z = true;  // STACK
    }

    // Her metod caghirishi STACK-de yeni frame yaradir
    public static int calculate(int a, int b) {
        int result = a + b; // a, b, result — hamisi STACK-de
        return result;
    }   // metod bitdikde frame silinir
}
```

**Stack** — her thread-in oz stack-i var:
- Metod caghirishlari (frame-ler)
- Lokal deyishenler
- Primitiv tipler
- Obyekt referanslari (amma obyektin ozu heap-dedir)

### Garbage Collection

Java-da yaddash avtomatik idare olunur. Proqramci `free()` ve ya `delete` caghirmir:

```java
public class GarbageCollectionExample {

    public void process() {
        // 1. Obyekt yaranir — heap-de yer ayrilir
        StringBuilder sb = new StringBuilder("Salam");

        // 2. Obyektle ish gorulur
        sb.append(" Dunya");

        // 3. Referans itir — obyekt "garbage" olur
        sb = null;
        // ve ya metod bitdikde sb avtomatik scope-dan cixir

        // 4. GC mueyyen vaxtda bu obyekti silecek
        // Biz bunu kontrol ede bilmerik (yalniz tevsiye ede bilerik):
        System.gc(); // GC-ye TEVSIYE — mecburi deyil
    }
}
```

Esas GC alqoritmleri:

```
GC Noevleri:
┌─────────────────────────────────────────────────┐
│ Serial GC         — tek thread, kichik tetbiqler│
│ Parallel GC       — multi-thread, throughput    │
│ G1 GC (default)   — balansli, boyuk heap        │
│ ZGC               — cox ashagi pause time       │
│ Shenandoah        — ashagi pause time           │
└─────────────────────────────────────────────────┘

// JVM parametrleri ile tenzimlenebilir:
// java -XX:+UseG1GC -Xms512m -Xmx4g MyApp
// -Xms: baslangic heap olcusu
// -Xmx: maksimum heap olcusu
```

### Java — Long-running Proses

```java
// Spring Boot tetbiqi — bir defe bashlayir, saatlarla/gunlerle isleyir
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
        // JVM prosesi QALIR — sorguları gozleyir
        // Butun bean-ler, connection pool-lar yaddashda qalir
    }
}

// Ustuunlukleri:
// - Connection pool — database baglantilari tekrar istifade olunur
// - Keshlenmiş data — yaddashda saxlanir
// - JIT optimizasiya — vaxt kecdikce daha suretli isleyir
// - Warm-up — ilk sorgu yavash, sonrakilar suretli

// Catishmazliqlari:
// - Memory leak riski — obyektler silinmese yaddash dolar
// - GC pause — garbage collection prosesi tetbiqi qisa muddet dayandira biler
// - Yuksek baslangic vaxti — JVM bashlama 1-5 saniye ceke biler
```

---

## PHP-de istifadesi

### PHP Request Lifecycle (sorgu omru)

PHP-nin esas modeli belodir: **her sorgu ayri dunyaqdir**.

```
HTTP Sorgusu → Web Server (Nginx/Apache)
                      ↓
               PHP-FPM Worker procesi
                      ↓
        ┌─────────────────────────────┐
        │  1. PHP engine bashlayir     │
        │  2. Script parse olunur      │
        │  3. OPcode-a kompilyasiya    │
        │  4. Emeliyyatlar icra olunur │
        │  5. Cavab gonderilir        │
        │  6. BUTUN yaddash silinir   │
        └─────────────────────────────┘
                      ↓
               HTTP Cavab ← istifadeciye
```

```php
<?php

// Her sorquda bu SIFIRDAN bashlayir:
$users = [];                        // yaddash ayrilir
$db = new PDO('mysql:host=...');    // database baglantisi acilir
$result = $db->query('SELECT ...');  // sorgu icra olunur
$users = $result->fetchAll();        // neticeler yaddasha yuklenir

echo json_encode($users);           // cavab gonderilir

// SCRIPT BITER — butun yaddash avtomatik temizlenir
// $users, $db, $result — hamisi silinir
// Database baglantisi baglanir
// Hech bir manual temizlik lazim deyil
```

### Shared-Nothing Architecture

```php
<?php

// PROBLEM: Sorqular arasi melumat paylashila bilmir (yaddashda)
// Sorgu 1:
$cache['user_1'] = getUserFromDb(1);
// Sorgu biter, $cache silinir

// Sorgu 2:
// $cache bosh — evvelki sorgunun melumati YOXDUR
// Yeniden database-e muraciet lazimdir

// HELL: Xarici saxlama istifade et
// Redis, Memcached, database, fayl sistemi
$redis = new Redis();
$redis->set('user_1', serialize($user));  // Sorgu 1
$user = unserialize($redis->get('user_1')); // Sorgu 2 — Redis-den oxuyur
```

### PHP Yaddash Modeli

```php
<?php

// PHP yaddash idareetmesi — reference counting + cycle collector

// 1. Reference counting
$a = "salam";     // refcount = 1
$b = $a;           // refcount = 2 (copy-on-write — hələ kopyalanmayib)
$b = "fərqli";    // indi $a ucun refcount = 1, $b ucun yeni string yaranir

// 2. Boyuk array-lerde yaddash
$bigArray = range(1, 1_000_000); // ~32MB yaddash

echo memory_get_usage(true);      // Cari yaddash istifadesi
echo memory_get_peak_usage(true); // Pik yaddash istifadesi

// 3. Yaddash limiti
ini_set('memory_limit', '256M');  // Maksimum 256MB
// Limit ashdiqda: Fatal error: Allowed memory size exhausted

// 4. Manuel yaddash azad etme (nadir hallarda lazim olur)
unset($bigArray);                 // Referansi sil
gc_collect_cycles();              // Dovri referanslari temizle

// 5. Generator ile yaddash qenayeti
function bigRange(int $start, int $end): Generator
{
    for ($i = $start; $i <= $end; $i++) {
        yield $i;  // Yalniz 1 element yaddashda saxlanir
    }
}

// 1 milyon element — lakin yaddashda yalniz 1-i var
foreach (bigRange(1, 1_000_000) as $num) {
    // ...
}
```

### OPcache — bytecode keshlemesi

PHP scripti her sorguda yeniden parse olunur. OPcache bunu hel edir:

```
OPcache olmadan:                     OPcache ile:
┌──────────────┐                    ┌──────────────┐
│ PHP Script   │                    │ PHP Script   │
│    (.php)    │                    │    (.php)    │
└──────┬───────┘                    └──────┬───────┘
       ↓ HER SORQUDA                      ↓ YALNIZ 1 DEFE
┌──────────────┐                    ┌──────────────┐
│  Lexer/Parse │                    │  Lexer/Parse │
└──────┬───────┘                    └──────┬───────┘
       ↓                                   ↓
┌──────────────┐                    ┌──────────────┐
│  Compile to  │                    │  Compile to  │
│   OPcode     │                    │   OPcode     │
└──────┬───────┘                    └──────┬───────┘
       ↓                                   ↓
┌──────────────┐                    ┌────────────────────┐
│   Execute    │                    │ Shared Memory-de   │
└──────────────┘                    │ KESHLE             │
                                    └──────┬─────────────┘
                                           ↓ SONRAKI SORQULARDA
                                    ┌──────────────┐
                                    │ Keshden oxu  │
                                    │ ve icra et   │
                                    └──────────────┘
```

```php
<?php

// OPcache konfiqurasiyasi (php.ini)
// opcache.enable=1
// opcache.memory_consumption=256        // MB
// opcache.max_accelerated_files=20000
// opcache.validate_timestamps=0         // Production-da 0 (deploy vaxtinda restart)
// opcache.revalidate_freq=2             // Development-da 2 saniye

// OPcache statusunu yoxla
$status = opcache_get_status();
echo "Keshlenmiş fayllar: " . $status['opcache_statistics']['num_cached_scripts'];
echo "Hit rate: " . $status['opcache_statistics']['opcache_hit_rate'] . "%";

// Preloading (PHP 7.4+) — bashlangicda faylalri keshle
// opcache.preload=/app/preload.php
// preload.php:
// require_once '/app/vendor/autoload.php';
// $files = glob('/app/src/**/*.php');
// foreach ($files as $file) { opcache_compile_file($file); }
```

### JIT (PHP 8.0+)

PHP 8 ile JIT (Just-In-Time) kompilyasiya elave olundu — Java-nin JIT-ine benzer:

```
PHP 8 JIT Arxitekturasi:
┌──────────────────────────────────────────┐
│           PHP 8 Engine                    │
│                                           │
│  PHP Script → OPcache (bytecode)         │
│                    ↓                      │
│         ┌─────────┴──────────┐           │
│         │                    │           │
│    Interpreter          JIT Compiler     │
│  (adi ishleme)     (hot code → native)   │
│                                           │
│  JIT modlari:                            │
│  - tracing (1205) — en yaxshi performans │
│  - function (1235) — metod seviyyesinde  │
└──────────────────────────────────────────┘
```

```php
<?php

// php.ini JIT konfiqurasiyasi
// opcache.jit=1205
// opcache.jit_buffer_size=256M

// JIT en cox fayda verdiyi hallar:
// - Matematik hesablamalar
// - Donguler
// - CPU-intensive emeliyyatlar

// JIT az fayda verdiyi hallar (PHP-nin esas istifade sahesi):
// - I/O bound ishlər (database, fayl, HTTP)
// - String emeliyyatlari
// - Array manipulyasiyasi

// Numune: JIT ile suretlenen kod
function mandelbrot(int $size): int
{
    $sum = 0;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $cr = 2.0 * $x / $size - 1.5;
            $ci = 2.0 * $y / $size - 1.0;
            $zr = $zi = 0.0;
            $i = 0;
            while ($zr * $zr + $zi * $zi <= 4.0 && $i < 100) {
                $temp = $zr * $zr - $zi * $zi + $cr;
                $zi = 2.0 * $zr * $zi + $ci;
                $zr = $temp;
                $i++;
            }
            $sum += $i;
        }
    }
    return $sum;
}

// JIT ile bu funksiya 2-3x suretli isleyir
// Lakin database sorgusu ile isleyen tipik Laravel endpoint-inde
// JIT demek olar ki ferq yaratmir
```

---

## Esas ferqler

| Xususiyyet | Java (JVM) | PHP |
|---|---|---|
| **Runtime modeli** | Long-running proses | Request-based (bashlayir/biter) |
| **Virtual Machine** | JVM | Zend Engine |
| **Kompilyasiya** | Bytecode → JIT → Native | OPcode → (JIT PHP 8+) |
| **JIT** | En bashdan var, cox yetkin | PHP 8+ (mehdud fayda) |
| **Heap** | Konfiqurasiya olunan (Xms/Xmx) | `memory_limit` per-request |
| **Stack** | Her thread-in oz stack-i | Tek stack |
| **Garbage Collection** | G1, ZGC, Shenandoah (konfiqurasiya olunur) | Reference counting + cycle collector |
| **Yaddash paylashma** | Thread-ler arasi paylashilir | Prosesler arasi paylashilmir |
| **Memory leak riski** | Yuksek (long-running) | Ashagi (her sorquda temizlenir) |
| **Connection pooling** | Daxili (HikariCP, etc.) | Xarici (persistent connections mehdud) |
| **Baslangic vaxti** | Yavash (1-10 san) | Suretli (~ms) |
| **Ishinme (warm-up)** | JIT zamanla optimize edir | OPcache keshleyir, amma her proses soyuq bashlayir |
| **Bytecode keshlemesi** | Avtomatik (.class) | OPcache ile |

---

## Niye bele ferqler var?

### Java niye long-running prosesdir?

Java en bashdan **enterprise server tetbiqleri** ucun dizayn olunub. Bir bank tetbiqi, bir e-ticaret platformasi gunlerle, heftelerle dayanmadan islemlidir. JVM bu muddet erzinde:

1. **Connection pool** saxlayir — database-e her sorguda yeni baglanti acmaq evezine, movcud baglantilardan istifade edir
2. **JIT optimizasiya** edir — cox caghirilan metodlari native koda cevirir, tetbiq zamanla suretlenir
3. **Keshlenmish data** saxlayir — tez-tez istifade olunan melumatlari yaddashda saxlayir

Lakin bu modelin cetiinliyi var: **memory leak**. Eger obyektlere referans qalirsa ve GC onlari sile bilmirse, yaddash yavash-yavash dolar. Buna gore Java proqramcilari yaddash profilrleme ve monitoring alətlerinden istifade etmeli olurlar.

### PHP niye request-based modeldir?

PHP web sehifeler ucun yaradildi. 1995-ci ilde Rasmus Lerdorf PHP-ni "Personal Home Page Tools" olaraq yaratdi. Her sehife sorgusu musqeqil idi — girish, ishleme, cixish. Bu model son derece sade ve etibarlidi:

- **Memory leak yoxdur** — her sorquda butun yaddash silinir
- **Izolyasiya** — bir sorgunun xetasi diger sorqulara tesir etmir
- **Sade deployment** — faylalri deyishdirende server restart lazim deyil (OPcache-siz)
- **Horizontal scaling** — yeni server elave etmek asandir

### Modern yaxinlashmalar

Her iki platform bir-birinin ustuunluklerini almaga calishir:

**Java**:
- **GraalVM Native Image** — AOT kompilyasiya ile baslangic vaxtini millisaniyeye endirir (PHP kimi suretli bashlama)
- **Spring Native** — cloud-native tetbiqler ucun suretli bashlama

**PHP**:
- **Swoole/RoadRunner/FrankenPHP** — PHP-ni long-running prosese cevirir (Java kimi)
- **JIT** — performansi artirmaq ucun (Java-nin JIT-ine benzer)
- **OPcache preloading** — bashlangicda faylalri yukle (Java-nin class loading-ine benzer)

Lakin esas model qalir: Java = long-running proses, PHP = request-based. Ve bu modeller muxtelif problemler ucun muxtelif ustuunlukler verir.
