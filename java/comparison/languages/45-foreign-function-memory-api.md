# Foreign Function & Memory API (Java FFM vs PHP FFI)

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Native kitabxanalarla işləmək — C/C++ ilə yazılmış kodu yüksək səviyyəli dildən çağırmaq — həmişə lazım olub. Kripto kitabxanaları (OpenSSL), image processing (libpng, libjpeg), DB drayverləri (SQLite), şəbəkə alətləri (libcurl) — hamısı C-dədir. Proqramçı bu kodu dildən istifadə etmək üçün "körpü" (bridge) qurmalıdır.

**Java** uzun müddət **JNI (Java Native Interface)** istifadə edib — 1997-dən bəri. JNI mürəkkəb, səhv-prone, performans problemləri olan API idi. 2014-də **Project Panama** başladı və 2023-də (Java 22) **Foreign Function & Memory API (FFM)** — **JEP 454** — stable oldu. FFM artıq JNI-nin əvəzinə tövsiyə edilir.

**PHP**-də 7.4-də (2019) **FFI (Foreign Function Interface)** əlavə olundu. PHP FFI C kitabxanalarını birbaşa PHP-dən çağırmaq imkanı verir. Əvvəllər yeganə yol **PHP extension** yazmaqdı — bu da C biliyi və PHP SAPI başa düşməsi tələb edirdi. İndi **ext-php-rs** ilə Rust-da da extension yazmaq mümkündür.

---

## Java-da istifadəsi

### 1) Köhnə yol — JNI (nə üçün FFM gəldi?)

JNI ilə ən sadə "salam dünya":

```java
// Java tərəfi
public class HelloJNI {
    static { System.loadLibrary("hello"); }
    public native String sayHello(String name);

    public static void main(String[] args) {
        System.out.println(new HelloJNI().sayHello("Orxan"));
    }
}
```

Header generate et:

```bash
javac -h . HelloJNI.java
# HelloJNI.h əmələ gəlir
```

C tərəfi (`hello.c`):

```c
#include <jni.h>
#include <string.h>
#include "HelloJNI.h"

JNIEXPORT jstring JNICALL Java_HelloJNI_sayHello
  (JNIEnv *env, jobject obj, jstring name) {

    const char *nativeName = (*env)->GetStringUTFChars(env, name, 0);
    char buffer[256];
    snprintf(buffer, sizeof(buffer), "Salam, %s!", nativeName);
    (*env)->ReleaseStringUTFChars(env, name, nativeName);
    return (*env)->NewStringUTF(env, buffer);
}
```

Compile və istifadə:

```bash
gcc -shared -fPIC -I$JAVA_HOME/include -I$JAVA_HOME/include/linux \
    -o libhello.so hello.c
java -Djava.library.path=. HelloJNI
```

**JNI problemləri:**
- C kodu yazmaq lazımdır (`.c` fayl + header)
- Manual memory management (`ReleaseStringUTFChars` unudulsa — leak)
- Crash Java-nı da öldürür (heç bir izolyasiya yoxdur)
- Struct-larla işləmək çox mürəkkəbdir
- Performans cərimsi var (JNI transition)

### 2) Yeni yol — FFM API (JEP 454, Java 22)

FFM ilə eyni iş:

```java
import java.lang.foreign.*;
import java.lang.invoke.MethodHandle;

public class HelloFFM {
    public static void main(String[] args) throws Throwable {
        // C standart kitabxanası
        Linker linker = Linker.nativeLinker();
        SymbolLookup stdlib = linker.defaultLookup();

        // printf funksiyasının ünvanını tap
        MemorySegment printfAddr = stdlib.find("printf").orElseThrow();

        // Funksiya imzası: int printf(const char *fmt, ...)
        FunctionDescriptor printfDesc = FunctionDescriptor.of(
            ValueLayout.JAVA_INT,              // qaytarır
            ValueLayout.ADDRESS                // pointer arg
        );

        MethodHandle printf = linker.downcallHandle(printfAddr, printfDesc);

        // String-i native yaddaşa köçür
        try (Arena arena = Arena.ofConfined()) {
            MemorySegment message = arena.allocateUtf8String("Salam FFM!\n");
            printf.invoke(message);
        }
        // Arena close olduqda yaddaş avtomatik azad olunur
    }
}
```

**Açar konseptlər:**

- `Linker` — Java↔native funksiya bağlayıcısı.
- `SymbolLookup` — kitabxanadan funksiya ünvanlarını tapır.
- `MemorySegment` — native yaddaş bloku (pointer + ölçü + sahib Arena).
- `Arena` — yaddaş sahibi, `close()` ilə bütün yaddaşı azad edir.
- `ValueLayout` — C tipi necə oxunur (byte sırası, hizalanma).
- `FunctionDescriptor` — C funksiyasının imzası.
- `MethodHandle` — Java-dan çağırış üçün callable obyekt.

### 3) `Arena` növləri — yaddaş idarəetməsi

```java
// Confined Arena — tək thread-də istifadə olunur
try (Arena arena = Arena.ofConfined()) {
    MemorySegment buf = arena.allocate(1024);
    // yalnız bu thread çata bilər
}  // auto-close, yaddaş azad

// Shared Arena — çox thread-də paylaşılır
try (Arena arena = Arena.ofShared()) {
    MemorySegment buf = arena.allocate(1024);
    executor.submit(() -> { /* başqa thread */ });
}

// Auto Arena — GC idarə edir (köhnə API-nin ekvivalenti)
Arena auto = Arena.ofAuto();
MemorySegment buf = auto.allocate(1024);
// buf-a artıq keçid yoxdursa, GC yaddaşı azad edir

// Global Arena — heç vaxt azad olunmur
Arena global = Arena.global();
MemorySegment forever = global.allocate(1024);
```

### 4) C struct ilə işləmə

Məsələn, `struct Point { int x; int y; }` oxumaq:

```java
// C-dəki struct-un layout-u
StructLayout POINT = MemoryLayout.structLayout(
    ValueLayout.JAVA_INT.withName("x"),
    ValueLayout.JAVA_INT.withName("y")
).withName("Point");

// Field-lərə çatmaq üçün VarHandle
VarHandle xHandle = POINT.varHandle(MemoryLayout.PathElement.groupElement("x"));
VarHandle yHandle = POINT.varHandle(MemoryLayout.PathElement.groupElement("y"));

try (Arena arena = Arena.ofConfined()) {
    MemorySegment point = arena.allocate(POINT);
    xHandle.set(point, 0L, 10);
    yHandle.set(point, 0L, 20);

    System.out.println("x = " + xHandle.get(point, 0L));
    System.out.println("y = " + yHandle.get(point, 0L));
}
```

### 5) `jextract` — header-dən bindings generate et

`jextract` alət C header fayllarından Java binding-ləri avtomatik düzəldir.

```bash
# jextract quraşdır (OpenJDK repo-dan)
sdk install jextract

# SQLite bindings generate et
jextract \
    --output src/main/java \
    --target-package org.sqlite \
    --library sqlite3 \
    /usr/include/sqlite3.h
```

Nəticə Java sinifləri — birbaşa istifadə olunur:

```java
import org.sqlite.*;
import static org.sqlite.sqlite3_h.*;

public class SqliteExample {
    public static void main(String[] args) {
        try (Arena arena = Arena.ofConfined()) {
            MemorySegment dbPtr = arena.allocate(ValueLayout.ADDRESS);
            MemorySegment path = arena.allocateUtf8String("test.db");

            int rc = sqlite3_open(path, dbPtr);
            if (rc != SQLITE_OK()) {
                throw new RuntimeException("Open failed");
            }

            MemorySegment db = dbPtr.get(ValueLayout.ADDRESS, 0);
            MemorySegment sql = arena.allocateUtf8String(
                "CREATE TABLE IF NOT EXISTS users (id INT, name TEXT)");

            sqlite3_exec(db, sql, MemorySegment.NULL, MemorySegment.NULL, MemorySegment.NULL);
            sqlite3_close(db);
        }
    }
}
```

### 6) `libcurl` ilə HTTP istəyi

```java
import java.lang.foreign.*;
import java.lang.invoke.MethodHandle;

public class CurlExample {
    static final Linker LINKER = Linker.nativeLinker();
    static final SymbolLookup CURL = SymbolLookup.libraryLookup("curl", Arena.global());

    static final MethodHandle curl_easy_init = LINKER.downcallHandle(
        CURL.find("curl_easy_init").orElseThrow(),
        FunctionDescriptor.of(ValueLayout.ADDRESS)
    );

    static final MethodHandle curl_easy_setopt = LINKER.downcallHandle(
        CURL.find("curl_easy_setopt").orElseThrow(),
        FunctionDescriptor.of(
            ValueLayout.JAVA_INT,
            ValueLayout.ADDRESS,
            ValueLayout.JAVA_INT,
            ValueLayout.ADDRESS
        )
    );

    static final MethodHandle curl_easy_perform = LINKER.downcallHandle(
        CURL.find("curl_easy_perform").orElseThrow(),
        FunctionDescriptor.of(ValueLayout.JAVA_INT, ValueLayout.ADDRESS)
    );

    static final MethodHandle curl_easy_cleanup = LINKER.downcallHandle(
        CURL.find("curl_easy_cleanup").orElseThrow(),
        FunctionDescriptor.ofVoid(ValueLayout.ADDRESS)
    );

    static final int CURLOPT_URL = 10002;

    public static void main(String[] args) throws Throwable {
        try (Arena arena = Arena.ofConfined()) {
            MemorySegment curl = (MemorySegment) curl_easy_init.invoke();
            if (curl.address() == 0) throw new RuntimeException("init failed");

            MemorySegment url = arena.allocateUtf8String("https://api.github.com");
            curl_easy_setopt.invoke(curl, CURLOPT_URL, url);

            int result = (int) curl_easy_perform.invoke(curl);
            System.out.println("result = " + result);

            curl_easy_cleanup.invoke(curl);
        }
    }
}
```

### 7) Upcall — C-dən Java funksiyası çağır

`libcurl` callback-lər istəyir — cavabı oxumaq üçün. Bu halda C bizim Java metodumuzu çağıracaq.

```java
// Callback imzası: size_t callback(char*, size_t, size_t, void*)
FunctionDescriptor callbackDesc = FunctionDescriptor.of(
    ValueLayout.JAVA_LONG,
    ValueLayout.ADDRESS,
    ValueLayout.JAVA_LONG,
    ValueLayout.JAVA_LONG,
    ValueLayout.ADDRESS
);

// Java metod-u
static long writeCallback(MemorySegment buf, long size, long nmemb, MemorySegment userData) {
    long total = size * nmemb;
    byte[] data = buf.reinterpret(total).toArray(ValueLayout.JAVA_BYTE);
    System.out.print(new String(data));
    return total;
}

// Method handle əldə et
MethodHandle cbHandle = MethodHandles.lookup().findStatic(
    CurlExample.class,
    "writeCallback",
    MethodType.methodType(long.class, MemorySegment.class, long.class, long.class, MemorySegment.class)
);

// Upcall stub yarat
try (Arena arena = Arena.ofConfined()) {
    MemorySegment cbStub = LINKER.upcallStub(cbHandle, callbackDesc, arena);
    curl_easy_setopt.invoke(curl, CURLOPT_WRITEFUNCTION, cbStub);
    curl_easy_perform.invoke(curl);
}
```

### 8) Vector API (JEP 508, Java 25) — SIMD

Vector API Project Panama-nın digər hissəsidir — CPU-nun SIMD (vector) instruksiyalarından istifadə edir.

```java
import jdk.incubator.vector.*;

static final VectorSpecies<Float> SPECIES = FloatVector.SPECIES_PREFERRED;

public static void addArrays(float[] a, float[] b, float[] c) {
    int i = 0;
    int bound = SPECIES.loopBound(a.length);

    for (; i < bound; i += SPECIES.length()) {
        FloatVector va = FloatVector.fromArray(SPECIES, a, i);
        FloatVector vb = FloatVector.fromArray(SPECIES, b, i);
        FloatVector vc = va.add(vb);
        vc.intoArray(c, i);
    }

    // Qalan elementlər
    for (; i < a.length; i++) {
        c[i] = a[i] + b[i];
    }
}

// Adi for-loop-dan 4-8 dəfə sürətli (AVX-2/AVX-512 istifadə edir)
```

### 9) Off-heap böyük yaddaş

`ByteBuffer.allocateDirect()` 2 GB-la məhdudlaşır. FFM bu limiti aşır:

```java
try (Arena arena = Arena.ofConfined()) {
    long size = 16L * 1024 * 1024 * 1024;   // 16 GB
    MemorySegment huge = arena.allocate(size);

    huge.set(ValueLayout.JAVA_LONG, 0, 42L);
    huge.set(ValueLayout.JAVA_LONG, size - 8, 100L);

    // Heap-dan kənardadır, GC ona toxunmur
}
```

### 10) Pitfalls — yaddaş təhlükəsizliyi

```java
// SƏHV: Arena close olduqdan sonra istifadə
MemorySegment leaked;
try (Arena arena = Arena.ofConfined()) {
    leaked = arena.allocate(1024);
    leaked.set(ValueLayout.JAVA_INT, 0, 42);
}
// leaked.set(...) — IllegalStateException: already closed

// SƏHV: confined arena-nı başqa thread-dən istifadə
try (Arena arena = Arena.ofConfined()) {
    MemorySegment seg = arena.allocate(1024);
    Thread.startVirtualThread(() -> {
        seg.get(ValueLayout.JAVA_INT, 0);   // WrongThreadException
    }).join();
}

// DOĞRU: shared arena və ya struktur həll
try (Arena arena = Arena.ofShared()) {
    MemorySegment seg = arena.allocate(1024);
    Thread.startVirtualThread(() -> {
        seg.get(ValueLayout.JAVA_INT, 0);   // OK
    }).join();
}
```

Üstünlük: FFM **kompilə və run-time səviyyəsində yoxlanır**. JNI-də bu səhvlər JVM crash verərdi, FFM-də isə Java exception.

---

## PHP-də istifadəsi

### 1) PHP FFI əsasları (PHP 7.4+)

PHP FFI extension `ext-ffi` standart olaraq gəlir (PHP 7.4+), amma `php.ini`-də aktivləşdirilməlidir:

```ini
; php.ini
extension=ffi
ffi.enable=true
; Preload faylında istifadə üçün:
; opcache.preload=preload.php
```

Sadə C funksiya çağırışı:

```php
<?php
// libc-dən printf çağır
$ffi = FFI::cdef("
    int printf(const char *format, ...);
", "libc.so.6");

$ffi->printf("Salam, %s! Sənin yaşın %d.\n", "Orxan", 30);
```

### 2) String və memory management

```php
<?php
$ffi = FFI::cdef("
    void* malloc(size_t size);
    void free(void *ptr);
    void* memcpy(void *dest, const void *src, size_t n);
    size_t strlen(const char *s);
", "libc.so.6");

// Yaddaş ayır
$buf = $ffi->malloc(1024);

// C string yarat
$str = FFI::new("char[32]");
FFI::memcpy($str, "Salam dünya", 11);

echo $ffi->strlen($str);    // 11

// Yaddaşı azad et
$ffi->free($buf);
```

### 3) Struct ilə işləmə

```php
<?php
$ffi = FFI::cdef("
    typedef struct {
        int x;
        int y;
    } Point;

    double distance(Point a, Point b);
", "libmygeometry.so");

$a = $ffi->new('Point');
$a->x = 0;
$a->y = 0;

$b = $ffi->new('Point');
$b->x = 3;
$b->y = 4;

$d = $ffi->distance($a, $b);
echo $d;    // 5.0
```

### 4) Preload — performans üçün

Hər sorğuda `FFI::cdef` yükləmək bahadır. Preload ilə bir dəfə yüklənir:

```php
<?php
// preload.php
FFI::load(__DIR__ . '/libs/sqlite3.h');
```

```ini
; php.ini
opcache.preload=/var/www/preload.php
opcache.preload_user=www-data
```

İstifadə:

```php
<?php
$ffi = FFI::scope('sqlite3');   // preload-dan götürür, sürətli
```

`sqlite3.h` faylı xüsusi formatda olmalıdır:

```c
#define FFI_LIB "libsqlite3.so.0"
#define FFI_SCOPE "sqlite3"

typedef struct sqlite3 sqlite3;
int sqlite3_open(const char *filename, sqlite3 **ppDb);
int sqlite3_exec(sqlite3 *db, const char *sql,
    int (*callback)(void*, int, char**, char**),
    void *userData, char **errmsg);
int sqlite3_close(sqlite3 *db);
```

### 5) SQLite ilə real nümunə

```php
<?php
$ffi = FFI::load(__DIR__ . '/sqlite3.h');

$dbPtr = $ffi->new('sqlite3*[1]');
$rc = $ffi->sqlite3_open('test.db', $dbPtr);

if ($rc !== 0) {
    throw new RuntimeException('SQLite open failed');
}

$db = $dbPtr[0];
$sql = "CREATE TABLE IF NOT EXISTS users (id INT, name TEXT)";

$errmsg = $ffi->new('char*[1]');
$ffi->sqlite3_exec($db, $sql, null, null, $errmsg);

if ($errmsg[0] !== null) {
    echo "Error: " . FFI::string($errmsg[0]);
}

$ffi->sqlite3_close($db);
```

### 6) libcurl ilə HTTP istəyi

```php
<?php
$ffi = FFI::cdef("
    void* curl_easy_init();
    int curl_easy_setopt(void *curl, int option, ...);
    int curl_easy_perform(void *curl);
    void curl_easy_cleanup(void *curl);
", "libcurl.so.4");

$curl = $ffi->curl_easy_init();
if ($curl === null) {
    throw new RuntimeException('curl init failed');
}

$CURLOPT_URL = 10002;
$url = "https://api.github.com";
$ffi->curl_easy_setopt($curl, $CURLOPT_URL, $url);

$result = $ffi->curl_easy_perform($curl);
echo "result = $result\n";

$ffi->curl_easy_cleanup($curl);
```

Təcrübədə PHP-də `curl_*` funksiyaları `ext-curl` extension-ı vasitəsilə daha rahat işləyir — FFI nümunəsi yalnız maarifləndirmək üçündür.

### 7) libuuid ilə UUID generate et

```php
<?php
$ffi = FFI::cdef("
    typedef unsigned char uuid_t[16];
    void uuid_generate(uuid_t out);
    void uuid_unparse(const uuid_t uu, char *out);
", "libuuid.so.1");

$uuid = $ffi->new('uuid_t');
$ffi->uuid_generate($uuid);

$str = $ffi->new('char[37]');
$ffi->uuid_unparse($uuid, $str);

echo FFI::string($str);     // 550e8400-e29b-41d4-a716-446655440000
```

### 8) Java FFM ilə müqayisəli eyni iş (libuuid)

```java
// Java FFM
import java.lang.foreign.*;
import java.lang.invoke.MethodHandle;

public class UuidExample {
    public static void main(String[] args) throws Throwable {
        Linker linker = Linker.nativeLinker();
        SymbolLookup uuidLib = SymbolLookup.libraryLookup("uuid", Arena.global());

        MethodHandle uuid_generate = linker.downcallHandle(
            uuidLib.find("uuid_generate").orElseThrow(),
            FunctionDescriptor.ofVoid(ValueLayout.ADDRESS)
        );

        MethodHandle uuid_unparse = linker.downcallHandle(
            uuidLib.find("uuid_unparse").orElseThrow(),
            FunctionDescriptor.ofVoid(ValueLayout.ADDRESS, ValueLayout.ADDRESS)
        );

        try (Arena arena = Arena.ofConfined()) {
            MemorySegment uuid = arena.allocate(16);
            MemorySegment str  = arena.allocate(37);

            uuid_generate.invoke(uuid);
            uuid_unparse.invoke(uuid, str);

            System.out.println(str.getUtf8String(0));
        }
    }
}
```

### 9) Swoole və C extensions

Böyük performans lazımdırsa, PHP-də adətən **C extension** yazılır. Swoole bunun ən məşhur nümunəsidir.

```c
// sample_ext.c
#include "php.h"

PHP_FUNCTION(sample_hello) {
    char *name;
    size_t name_len;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "s", &name, &name_len) == FAILURE) {
        RETURN_NULL();
    }

    zend_string *result = strpprintf(0, "Salam, %s!", name);
    RETURN_STR(result);
}

static const zend_function_entry sample_functions[] = {
    PHP_FE(sample_hello, NULL)
    PHP_FE_END
};

zend_module_entry sample_module_entry = {
    STANDARD_MODULE_HEADER,
    "sample", sample_functions,
    NULL, NULL, NULL, NULL, NULL,
    "0.1",
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SAMPLE
ZEND_GET_MODULE(sample)
#endif
```

Build və quraşdır:

```bash
phpize
./configure --enable-sample
make && sudo make install
```

### 10) `ext-php-rs` — Rust ilə PHP extension

Rust C-dən daha təhlükəsizdir. `ext-php-rs` Rust-da PHP extension yazmağa imkan verir.

```rust
// src/lib.rs
use ext_php_rs::prelude::*;

#[php_function]
pub fn hello_rust(name: String) -> String {
    format!("Salam, {}! Rust-dan.", name)
}

#[php_function]
pub fn fibonacci(n: u64) -> u64 {
    fn fib(n: u64) -> u64 {
        if n < 2 { n } else { fib(n - 1) + fib(n - 2) }
    }
    fib(n)
}

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}
```

`Cargo.toml`:

```toml
[package]
name = "my_ext"
version = "0.1.0"
edition = "2021"

[lib]
crate-type = ["cdylib"]

[dependencies]
ext-php-rs = "0.12"
```

Build və istifadə:

```bash
cargo build --release
# .so faylını php ext dir-ə kopyala

# php.ini
# extension=my_ext
```

```php
<?php
echo hello_rust("Orxan");      // Salam, Orxan! Rust-dan.
echo fibonacci(10);            // 55
```

### 11) Kritik pitfalls

```php
<?php
// SƏHV: type mismatch — segfault
$ffi = FFI::cdef("
    int strlen(const char *s);   // SƏHV: size_t qaytarır, int deyil
", "libc.so.6");
// 64-bit sistemdə nəticə kəsilir, görünməz bug

// DOĞRU
$ffi = FFI::cdef("
    size_t strlen(const char *s);
", "libc.so.6");

// SƏHV: FFI::free çağırışı unutmaq — memory leak
$ptr = $ffi->malloc(1024);
// ...
// FFI::free($ptr);   // unutdular

// SƏHV: wrong null terminator
$str = FFI::new("char[5]");
FFI::memcpy($str, "salam", 5);   // 6 byte lazımdır ('\0' üçün)
echo FFI::string($str);          // undefined behavior

// DOĞRU
$str = FFI::new("char[6]");
FFI::memcpy($str, "salam\0", 6);
```

---

## Əsas fərqlər

| Aspekt | Java FFM (Panama) | PHP FFI |
|---|---|---|
| Gələn versiya | Java 22 stable (JEP 454) | PHP 7.4 |
| Əvvəlki texnologiya | JNI (1997) | PHP extension (C) |
| Kompilə yoxlanışı | `FunctionDescriptor`, `ValueLayout` tam | `FFI::cdef` string parse |
| Yaddaş idarəsi | `Arena` (confined/shared/auto/global) | Manual `free`, GC |
| Thread safety | Confined arena yoxlaması | Yoxdur |
| Struct dəstəyi | `MemoryLayout.structLayout` | C typedef string |
| Auto bindings | `jextract` tool | Yoxdur (manual cdef) |
| Callback (upcall) | `Linker.upcallStub` | Limited — `FFI::closure` (PHP 8.0) |
| Preload (performance) | Yoxdur (artıq daxildir) | `opcache.preload` + `FFI::scope` |
| SIMD / Vector | Vector API (JEP 508) | Yoxdur |
| Off-heap limit | 2^64 bayt | System RAM-la məhdud |
| Rust inteqrasiyası | Tövsiyə olunan | `ext-php-rs` |
| Dev experience | `jextract` rahatdır | header manual yazılır |

---

## Niyə belə fərqlər var?

**Java JNI-nin 25 illik mirasını tərk edir.** JNI 1997-də dizayn olunub — zamanında yeni idi, indi köhnəlmiş. Ağır performans cəriməsi, manual memory management, crash-in JVM-ə sızması — hamısı problem idi. Project Panama 2014-də başladı və 2023-də FFM stable oldu. Yeni API **type-safe**, **performanslı**, **Arena ilə təhlükəsiz**, və **jextract ilə avtomatlaşdırılıb**.

**PHP FFI-i təcrübəsiz istifadəçi üçün tövsiyə etmir.** PHP FFI rəsmi olaraq "advanced feature"-dir. Səbəb: C bilməyən proqramçı asanlıqla segfault yarada bilər. Ona görə PHP hələ də **C extension** yolunu prioritetdə saxlayır — extension yaxşı test olunur, preload olur, rahatdır. FFI daha çox "eksperimentləşmə" üçündür.

**Performans fərqi:** Java FFM çox sürətlidir çünki JIT inline edə bilir. PHP FFI hər sorğuda C funksiyasını çağırır — JIT olmadığı üçün C çağırış overhead-i var. PHP 8.0+-da JIT var, amma FFI-yə hələ tam tətbiq olunmayıb.

**Ekosistem:** Java dünyasında FFM yeni Netty, yeni native crypto, sürətli JSON (simdjson) üçün istifadə olunur. PHP-də isə FFI əsasən Swoole, Psalm/PHPStan-ın dərin analizi, və eksperiment layihələrdə istifadə olunur.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**

- `Arena` — yaddaş sahibliyi modeli (confined/shared/auto/global)
- `MemoryLayout` — strukturları type-safe təsvir
- `jextract` — header-dən bindings generate etmə aləti
- Upcall stub-lar (Java metodunu C-dən çağırmaq)
- Vector API (SIMD)
- `MethodHandle` inteqrasiyası
- Thread confinement yoxlaması
- JIT ilə inline edilən native çağırışlar

**Yalnız PHP-də:**

- `FFI::cdef` — string-dən birbaşa C təyini
- `opcache.preload` + `FFI::scope` — bir dəfə yükləmə
- `ext-php-rs` — Rust-dan PHP extension
- Sadə sintaksis (`$ffi->printf(...)`)
- PECL paket sistemindən kütləvi C extension seçimi

**İkisində də var:**

- Native kitabxana yükləmə
- Struct və primitiv tiplər
- String çevirməsi (PHP `FFI::string`, Java `getUtf8String`)
- Function pointer callback (Java `upcallStub`, PHP `FFI::closure`)
- Qeyri-təhlükəli boundary (hər ikisi də crash verə bilər)

**İkisində də yoxdur və ya zəifdir:**

- Avtomatik bellek təhlükəsizliyi (Rust `Ownership`-nin ekvivalenti) — hər ikisi manual
- C++ obyekt model-i (Java FFM və PHP FFI yalnız C API ilə işləyir, C++ mangling yoxdur)

---

## Best Practices

### Java FFM

1. **Yeni layihədə JNI yazma** — FFM istifadə et. JNI artıq deprecated yolu hesab olunur.
2. **`jextract`** böyük header-lər üçün — əl ilə `FunctionDescriptor` yazma, səhv olur.
3. **`Arena.ofConfined()`** default ol — thread-safe və təhlükəsizdir.
4. **`Arena.ofShared()`** yalnız çox thread lazımdırsa — yoxlamalar daha sərtdir.
5. **`Arena.ofAuto()`** GC-yə güvən — qısa ömürlü yaddaş üçün.
6. **`Arena.global()`** sadəcə kitabxana yüklənməsi üçün — yaddaş allocate etmə.
7. **`MemorySegment.reinterpret(long)`** ehtiyatla — ölçünü qaçırsan, segfault.
8. **Upcall stub** sızmasın — `Arena`-nı düzgün ömür-dövrə bağla.
9. **`MemoryLayout`** named field-lərlə — debug zamanı rahatdır.
10. **Native çağırış try/catch içində** — xəta istisnadır, crash deyil.

### PHP FFI

1. **Produksiya üçün `opcache.preload` + `FFI::scope`** — hər sorğuda cdef parse etmə.
2. **`FFI::load` yerine `FFI::cdef`** — inline string-lər texniki borca səbəb olur.
3. **`size_t` və `int`-i qarışdırma** — 64-bit sistemdə kritik səhv.
4. **Hər `malloc` üçün `free`** — GC C yaddaşını azad etmir.
5. **Null pointer yoxla** — `if ($ptr === null)` əvvəl.
6. **Preference: extension > FFI** — performans kritik koddursa C extension yaz.
7. **`ext-php-rs` Rust-la** — FFI-dən daha təhlükəsizdir.
8. **Unit testdə FFI mock et** — real C kitabxanasını testdə istifadə etmə.
9. **FFI istifadə etmədikdə `ini_set('ffi.enable', 0)`** — attack surface azalır.
10. **`FFI::string($ptr)`-da uzunluğu göstər** — null terminator-u qaçırmaq təhlükəsizdir.

---

## Yekun

- **Java FFM (Foreign Function & Memory API, JEP 454)** Java 22-də stable oldu. JNI-nin yeni, təhlükəsiz, sürətli əvəzidir.
- **`Linker`, `SymbolLookup`, `MemorySegment`, `Arena`, `ValueLayout`, `FunctionDescriptor`** — FFM-nin əsas anlayışlarıdır.
- **`Arena` növləri**: `ofConfined` (tək thread), `ofShared` (çox thread), `ofAuto` (GC), `global` (heç vaxt azad olunmaz).
- **`jextract`** C header-dən avtomatik Java bindings düzəldir — əl ilə yazmaq lazım deyil.
- **Vector API (JEP 508, Java 25)** — SIMD instruksiyaları ilə 4-8x performans artımı.
- **Upcall stub-lar** C-dən Java callback çağırmağa imkan verir — libcurl write callback və s.
- **JNI hələ işləyir** amma yeni kod üçün FFM tövsiyə edilir.
- **PHP FFI (PHP 7.4+)** C kitabxanalarını birbaşa PHP-dən çağırmağa imkan verir.
- **`FFI::cdef`** — string şəklində C təyini. `FFI::load` + `FFI::scope` + `opcache.preload` — produksiya performansı.
- **PHP-də C extension** hələ performans kritik kod üçün önerilir — FFI daha çox eksperimentaldır.
- **`ext-php-rs`** — Rust-da PHP extension yazmaq üçün framework. Rust təhlükəsizliyi + PHP inteqrasiyası.
- **Ümumi pitfall**: tip uyğunsuzluğu (size_t vs int), yaddaş sızıntısı (free unudulur), thread səhvi (confined arena başqa thread-də).
- **Qərar meyarı:** yüksək performans native kitabxana lazım isə — Java FFM üstündür (JIT + type safety). PHP-də sadəcə bir funksiya çağırmaq lazımsa — FFI rahatdır. Böyük C/C++ kitabxana inteqrasiyası üçün hər iki dildə də həqiqi mütəxəssis lazımdır.
