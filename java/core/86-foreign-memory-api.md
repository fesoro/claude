# 86 — Java Foreign Memory & Function API — Geniş İzah

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
1. [Foreign Memory API nədir?](#foreign-memory-api-nədir)
2. [MemorySegment — native bellek](#memorysegment--native-bellek)
3. [MemoryLayout — struct/array layout](#memorylayout--structarray-layout)
4. [Foreign Function API — native kod çağırışı](#foreign-function-api--native-kod-çağırışı)
5. [Arena — bellek idarəetmə](#arena--bellek-idarəetmə)
6. [Praktiki nümunələr](#praktiki-nümunələr)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Foreign Memory API nədir?

```
Project Panama — JEP seriyası:
  JEP 412 (Java 17) — Foreign Function & Memory API (Incubator)
  JEP 419 (Java 18) — 2nd Incubator
  JEP 424 (Java 19) — Preview
  JEP 434 (Java 20) — 2nd Preview
  JEP 442 (Java 21) — 3rd Preview
  JEP 454 (Java 22) — STANDARD (finallaşdı!) ✅

Məqsəd:
  → Heap xarici (off-heap, native) belleğə güvənli giriş
  → Native C/C++ kitabxanalarını JNI olmadan çağırmaq
  → JNI-nin çatışmazlıqlarını aradan qaldırmaq

JNI problemləri (köhnə yanaşma):
  → Çox verbose — header faylları, C wrapper kodu
  → Unsafe — native bellek overflow → JVM crash
  → Yavaş geliştirmə — compile + link + deploy
  → Debugging çətin

FFM API (Foreign Function & Memory):
  → Pure Java — native header lazım deyil
  → Güvənli — Arena (scope) ilə bellek avtomatik azad olunur
  → Performanslı — zero-copy, direct memory access
  → jextract tool — C header-dən Java binding avtomatik yaranır
```

---

## MemorySegment — native bellek

```java
// ─── MemorySegment — native bellek bölgəsi ───────────────
// java.lang.foreign.MemorySegment

// ─── Native bellek ayır ──────────────────────────────────
try (Arena arena = Arena.ofConfined()) {

    // 1024 byte native bellek ayır
    MemorySegment segment = arena.allocate(1024);

    // Byte yazmaq
    segment.set(ValueLayout.JAVA_BYTE, 0, (byte) 42);
    byte b = segment.get(ValueLayout.JAVA_BYTE, 0);
    System.out.println("Byte: " + b);  // 42

    // Int yazmaq (4 byte)
    segment.set(ValueLayout.JAVA_INT, 0, 12345);
    int i = segment.get(ValueLayout.JAVA_INT, 0);
    System.out.println("Int: " + i);   // 12345

    // Long yazmaq (8 byte)
    segment.set(ValueLayout.JAVA_LONG, 8, 9876543210L);
    long l = segment.get(ValueLayout.JAVA_LONG, 8);
    System.out.println("Long: " + l);  // 9876543210

    // Double yazmaq
    segment.set(ValueLayout.JAVA_DOUBLE, 16, 3.14159);
    double d = segment.get(ValueLayout.JAVA_DOUBLE, 16);
    System.out.println("Double: " + d); // 3.14159

} // Arena bağlananda bellek avtomatik azad olunur

// ─── String native belleğə yazmaq ────────────────────────
try (Arena arena = Arena.ofConfined()) {
    String message = "Salam Dünya!";
    MemorySegment cString = arena.allocateFrom(message); // null-terminated C string

    // C string-dən Java String-ə
    String back = cString.getString(0);
    System.out.println(back); // "Salam Dünya!"
}

// ─── Array kimi istifadə ──────────────────────────────────
try (Arena arena = Arena.ofConfined()) {
    int count = 10;
    // int array üçün 10 * 4 = 40 byte
    MemorySegment intArray = arena.allocate(ValueLayout.JAVA_INT, count);

    // Dəyər yaz
    for (int i = 0; i < count; i++) {
        intArray.setAtIndex(ValueLayout.JAVA_INT, i, i * i);
    }

    // Dəyər oxu
    for (int i = 0; i < count; i++) {
        System.out.print(intArray.getAtIndex(ValueLayout.JAVA_INT, i) + " ");
    }
    // Output: 0 1 4 9 16 25 36 49 64 81
}

// ─── Heap MemorySegment ───────────────────────────────────
// Heap array-ı MemorySegment kimi wrap et (zero-copy)
byte[] heapArray = new byte[100];
MemorySegment heapSegment = MemorySegment.ofArray(heapArray);
heapSegment.set(ValueLayout.JAVA_BYTE, 0, (byte) 99);
System.out.println(heapArray[0]); // 99 — eyni bellek!

// ─── Slice — bölmə ───────────────────────────────────────
try (Arena arena = Arena.ofConfined()) {
    MemorySegment large = arena.allocate(1024);

    // 100-dən 200-ə qədər olan bölmə
    MemorySegment slice = large.asSlice(100, 100);
    slice.set(ValueLayout.JAVA_INT, 0, 777);

    // large[100]-dən oxu
    int val = large.get(ValueLayout.JAVA_INT, 100);
    System.out.println(val); // 777
}
```

---

## MemoryLayout — struct/array layout

```java
// ─── MemoryLayout — C struct-a oxşar layout ──────────────
// C struct:
// struct Point {
//     int x;
//     int y;
// };

StructLayout pointLayout = MemoryLayout.structLayout(
    ValueLayout.JAVA_INT.withName("x"),
    ValueLayout.JAVA_INT.withName("y")
);

// Layout ölçüsü
System.out.println(pointLayout.byteSize()); // 8 (int=4, int=4)

// ─── VarHandle — struct field-lərə giriş ─────────────────
VarHandle xHandle = pointLayout.varHandle(MemoryLayout.PathElement.groupElement("x"));
VarHandle yHandle = pointLayout.varHandle(MemoryLayout.PathElement.groupElement("y"));

try (Arena arena = Arena.ofConfined()) {
    MemorySegment point = arena.allocate(pointLayout);

    // Field-lərə yaz
    xHandle.set(point, 0L, 10);
    yHandle.set(point, 0L, 20);

    // Field-ləri oxu
    int x = (int) xHandle.get(point, 0L);
    int y = (int) yHandle.get(point, 0L);
    System.out.println("Point: (" + x + ", " + y + ")"); // Point: (10, 20)
}

// ─── Daha mürəkkəb struct ─────────────────────────────────
// C struct:
// struct Person {
//     char name[64];
//     int  age;
//     double salary;
// };

StructLayout personLayout = MemoryLayout.structLayout(
    MemoryLayout.sequenceLayout(64, ValueLayout.JAVA_BYTE).withName("name"),
    ValueLayout.JAVA_INT.withName("age"),
    MemoryLayout.paddingLayout(4),    // Alignment padding
    ValueLayout.JAVA_DOUBLE.withName("salary")
);

// ─── Array layout ─────────────────────────────────────────
// C: int points[100][2]  (100 Point)
SequenceLayout pointArrayLayout = MemoryLayout.sequenceLayout(
    100, pointLayout);

VarHandle arrayXHandle = pointArrayLayout.varHandle(
    MemoryLayout.PathElement.sequenceElement(),
    MemoryLayout.PathElement.groupElement("x")
);

try (Arena arena = Arena.ofConfined()) {
    MemorySegment points = arena.allocate(pointArrayLayout);

    // points[5].x = 42
    arrayXHandle.set(points, 0L, 5L, 42);

    // points[5].x oxu
    int val = (int) arrayXHandle.get(points, 0L, 5L);
    System.out.println("points[5].x = " + val); // 42
}
```

---

## Foreign Function API — native kod çağırışı

```java
// ─── Native funksiya çağırışı ─────────────────────────────
// C: int strlen(const char *s);

// SymbolLookup — shared library-dən simvol tap
Linker linker = Linker.nativeLinker();
SymbolLookup stdlib = linker.defaultLookup();

// strlen funksiyasını tap
MemorySegment strlenSymbol = stdlib.find("strlen").orElseThrow();

// Function descriptor
FunctionDescriptor strlenDesc = FunctionDescriptor.of(
    ValueLayout.JAVA_LONG,       // Return type: long
    ValueLayout.ADDRESS           // Parameter: const char*
);

// MethodHandle yarat
MethodHandle strlen = linker.downcallHandle(strlenSymbol, strlenDesc);

// Çağır
try (Arena arena = Arena.ofConfined()) {
    MemorySegment cStr = arena.allocateFrom("Salam Dünya!");
    long length = (long) strlen.invoke(cStr);
    System.out.println("strlen = " + length); // 12
}

// ─── printf çağırışı ─────────────────────────────────────
MemorySegment printfSymbol = stdlib.find("printf").orElseThrow();

FunctionDescriptor printfDesc = FunctionDescriptor.of(
    ValueLayout.JAVA_INT,        // int qaytarır
    ValueLayout.ADDRESS          // format string
    // variadic args — əlavə edin
);

MethodHandle printf = linker.downcallHandle(printfSymbol, printfDesc,
    Linker.Option.firstVariadicArg(1) // variadic
);

try (Arena arena = Arena.ofConfined()) {
    MemorySegment format = arena.allocateFrom("Java-dan C printf: %s, say: %d\n");
    MemorySegment str    = arena.allocateFrom("Salam");
    printf.invoke(format, str, 42);
}

// ─── libc qsort çağırışı ─────────────────────────────────
// C: void qsort(void *base, size_t nmemb, size_t size,
//               int (*compar)(const void*, const void*));

MemorySegment qsortSymbol = stdlib.find("qsort").orElseThrow();

FunctionDescriptor qsortDesc = FunctionDescriptor.ofVoid(
    ValueLayout.ADDRESS,    // base
    ValueLayout.JAVA_LONG,  // nmemb
    ValueLayout.JAVA_LONG,  // size
    ValueLayout.ADDRESS     // comparator function pointer
);

MethodHandle qsort = linker.downcallHandle(qsortSymbol, qsortDesc);

// Comparator upcall — Java metodunu C-yə pointer kimi ver
FunctionDescriptor comparDesc = FunctionDescriptor.of(
    ValueLayout.JAVA_INT,
    ValueLayout.ADDRESS,
    ValueLayout.ADDRESS
);

MethodHandle comparMethod = MethodHandles.lookup()
    .findStatic(MyClass.class, "compareInts", comparDesc.toMethodType());

try (Arena arena = Arena.ofConfined()) {
    MemorySegment comparUpcall = linker.upcallStub(comparMethod, comparDesc, arena);

    int[] data = {5, 2, 8, 1, 9, 3};
    MemorySegment array = arena.allocateFrom(ValueLayout.JAVA_INT, data);

    qsort.invoke(array, (long) data.length,
        ValueLayout.JAVA_INT.byteSize(), comparUpcall);

    // Sıralanmış nəticəni oxu
    for (int i = 0; i < data.length; i++) {
        System.out.print(array.getAtIndex(ValueLayout.JAVA_INT, i) + " ");
    }
    // Output: 1 2 3 5 8 9
}

static int compareInts(MemorySegment a, MemorySegment b) {
    return Integer.compare(
        a.get(ValueLayout.JAVA_INT, 0),
        b.get(ValueLayout.JAVA_INT, 0)
    );
}
```

---

## Arena — bellek idarəetmə

```java
// ─── Arena növləri ────────────────────────────────────────

// 1. Confined Arena — tək thread, AutoCloseable
try (Arena arena = Arena.ofConfined()) {
    MemorySegment seg = arena.allocate(100);
    // Yalnız bu thread istifadə edə bilər
    // try block bitəndə → bellek azad olunur
} // → seg artıq etibarlı deyil (dangling pointer yoxdur!)

// 2. Shared Arena — çox thread
try (Arena arena = Arena.ofShared()) {
    MemorySegment seg = arena.allocate(1024);
    // Çoxlu thread-lər istifadə edə bilər
    // Thread-safe
    CompletableFuture.runAsync(() -> {
        seg.set(ValueLayout.JAVA_INT, 0, 42);
    }).join();
}

// 3. Global Arena — heç vaxt bağlanmır, manual azad olunmur
MemorySegment globalSeg = Arena.global().allocate(64);
// JVM həyatı boyu yaşayır — kritik resurslara pointer saxlamaq üçün

// 4. Auto Arena — GC tərəfindən idarə olunur (Java 22+)
// Arena arena = Arena.ofAuto();
// GC segmenti işarəsiz görəndə azad edir
// AutoCloseable deyil — manual close() yoxdur

// ─── Arena scope qoruma ──────────────────────────────────
MemorySegment leakedSeg;
try (Arena arena = Arena.ofConfined()) {
    leakedSeg = arena.allocate(64);
    leakedSeg.set(ValueLayout.JAVA_INT, 0, 99);
}
// Arena bağlandı — leakedSeg artıq geçersiz!

try {
    leakedSeg.get(ValueLayout.JAVA_INT, 0); // ← IllegalStateException!
} catch (IllegalStateException e) {
    System.out.println("Güvənli xəta: " + e.getMessage());
    // "Already closed" — JVM crash yoxdur!
}

// ─── Custom allocator ─────────────────────────────────────
// Böyük buffer bir dəfə ayır, küçük parçaları oradan ver

public class PoolAllocator {
    private final Arena arena;
    private final MemorySegment pool;
    private long offset = 0;

    public PoolAllocator(long poolSize) {
        this.arena = Arena.ofConfined();
        this.pool = arena.allocate(poolSize);
    }

    public MemorySegment allocate(long size) {
        if (offset + size > pool.byteSize()) {
            throw new OutOfMemoryError("Pool doldu");
        }
        MemorySegment slice = pool.asSlice(offset, size);
        offset += size;
        return slice;
    }

    public void close() {
        arena.close(); // Bütün pool azad olunur
    }
}
```

---

## Praktiki nümunələr

```java
// ─── Large dataset — off-heap processing ─────────────────
// 1 milyon int-i heap-dən kənar saxla

public class OffHeapIntArray {
    private final MemorySegment segment;
    private final Arena arena;
    private final int size;

    public OffHeapIntArray(int size) {
        this.size = size;
        this.arena = Arena.ofShared();
        this.segment = arena.allocate(ValueLayout.JAVA_INT, size);
    }

    public void set(int index, int value) {
        segment.setAtIndex(ValueLayout.JAVA_INT, index, value);
    }

    public int get(int index) {
        return segment.getAtIndex(ValueLayout.JAVA_INT, index);
    }

    public long sum() {
        long total = 0;
        for (int i = 0; i < size; i++) {
            total += get(i);
        }
        return total;
    }

    public void close() {
        arena.close();
    }
}

// İstifadə:
try (var arr = new OffHeapIntArray(1_000_000)) {
    for (int i = 0; i < 1_000_000; i++) {
        arr.set(i, i * 2);
    }
    System.out.println("Cəm: " + arr.sum());
}
// GC heap-ə yük yoxdur!

// ─── jextract — C library binding generator ──────────────
// jextract tool C header-dən Java binding yaradır

// Terminal:
// jextract --output src/main/java \
//          -t com.example.openssl \
//          /usr/include/openssl/sha.h

// Avtomatik yaranmış kod (jextract-dan):
// SHA256_Init, SHA256_Update, SHA256_Final funksiyaları Java-ya bind olunur

// SHA-256 hesabla (native OpenSSL):
// try (Arena arena = Arena.ofConfined()) {
//     MemorySegment ctx = arena.allocate(sha256_ctx.layout());
//     SHA256_Init(ctx);
//     MemorySegment data = arena.allocateFrom("hello");
//     SHA256_Update(ctx, data, data.byteSize() - 1);
//     MemorySegment hash = arena.allocate(32);
//     SHA256_Final(hash, ctx);
// }
```

---

## İntervyu Sualları

### 1. Foreign Memory API nədir və niyə lazımdır?
**Cavab:** Java 22-də finallaşdı (JEP 454). Heap xarici (off-heap, native) belleğə güvənli giriş və native C kitabxanalarını JNI olmadan çağırmaq imkanı. JNI problemlərini həll edir: C header, wrapper kod, verbose, unsafe (JVM crash riski). FFM API: `MemorySegment` (native bellek), `Arena` (scope-based lifecycle), `Linker` (native funksiya çağırışı), `FunctionDescriptor` (C funksiya imzası). `try-with-resources` ilə bellek avtomatik azad olunur — dangling pointer riski yoxdur.

### 2. Arena nədir və növləri hansılardır?
**Cavab:** Arena — native belleyin scope-based lifecycle idarəçisi. Növlər: **Confined** — tək thread, try-with-resources ilə bağlanır, ən güvənli; **Shared** — çox thread, thread-safe, try-with-resources; **Global** — heç vaxt bağlanmır, JVM həyatı boyu; **Auto** — GC idarə edir, AutoCloseable deyil. Arena bağlananda ondan ayrılan bütün `MemorySegment`-lər etibarsız olur — sonradan çatmağa cəhd `IllegalStateException` atır (JVM crash yox!). Bu JNI-dan əsas üstünlükdür.

### 3. MemoryLayout nə üçün istifadə edilir?
**Cavab:** C struct-larının Java-da təsviri. `StructLayout` — struct field-lər (name, type, padding). `SequenceLayout` — C array. `VarHandle` — layout vasitəsilə field-lərə type-safe giriş. Faydalar: manual offset hesablaması yoxdur (layout özü bilir), padding avtomatik idarə olunur, field adı ilə giriş. `jextract` tool C header fayllarından avtomatik `MemoryLayout` + `VarHandle` kodu yaradır.

### 4. JNI vs FFM API müqayisəsi?
**Cavab:** **JNI**: C header lazım, C wrapper kod, javah aləti, compile + link mərhələsi; unsafe — native bellek xətası → JVM crash; verbose, debugging çətin. **FFM API**: Pure Java, header lazım deyil; güvənli — Arena scope xaricindəki girişlər checked exception; `jextract` ilə avtomatik binding; MethodHandle vasitəsilə çağırış. Performans demək olar eyni (zero-overhead). FFM API JNI-nı tam əvəzləmək üçün nəzərdə tutulub.

### 5. Off-heap memory nə zaman istifadə edilir?
**Cavab:** (1) **Böyük dataset** — GC heap limitini keçən data (1GB+ cache, time-series data); GC pause yoxdur. (2) **I/O zero-copy** — `FileChannel.map()` kimi direct buffer, kernel-user space kopyası yoxdur. (3) **Native library** — C/C++ lib-ə data ötürmək (görüntü işləmə, ML, OpenSSL). (4) **Uzun ömürlü obyektlər** — GC-yə yük azalır. Dezavantaj: manual lifecycle idarəsi (Arena), Java object semantikası yoxdur (no GC, no finalizer). Arena ilə bu idarəsi Java 22-dən əvvəlindən çox asanlaşdı.

*Son yenilənmə: 2026-04-10*
