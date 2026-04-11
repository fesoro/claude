# 42. JVM Arxitekturası

## Mündəricat
1. [JVM nədir?](#jvm-nədir)
2. [JDK vs JRE vs JVM](#jdk-vs-jre-vs-jvm)
3. [ClassLoader Subsistemi](#classloader-subsistemi)
4. [Runtime Data Areas](#runtime-data-areas)
5. [Execution Engine](#execution-engine)
6. [Bytecode və .class faylı](#bytecode-və-class-faylı)
7. [JVM dilləri](#jvm-dilləri)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## JVM nədir?

**JVM (Java Virtual Machine)** — Java bytecode-u icra edən virtual maşındır. JVM sayəsində Java proqramları müxtəlif əməliyyat sistemlərində dəyişiklik olmadan işləyə bilir. Bu prinsip **"Write Once, Run Anywhere"** (WORA) adlanır.

```
Java Kodu (.java)
      ↓  javac (compiler)
Bytecode (.class)
      ↓  JVM
Native Machine Code (OS spesifik)
```

JVM bir **spesifikasiya**dır — müxtəlif şirkətlər öz JVM implementasiyalarını yarada bilər:
- **HotSpot JVM** — Oracle/OpenJDK (ən geniş yayılmış)
- **GraalVM** — Oracle (polyglot, native image)
- **OpenJ9** — Eclipse/IBM
- **Azul Zing/Zulu** — Azul Systems

---

## JDK vs JRE vs JVM

```
┌─────────────────────────────────────────┐
│                  JDK                    │
│  ┌───────────────────────────────────┐  │
│  │              JRE                  │  │
│  │  ┌─────────────────────────────┐  │  │
│  │  │           JVM               │  │  │
│  │  │  ClassLoader + Runtime Data │  │  │
│  │  │  Areas + Execution Engine   │  │  │
│  │  └─────────────────────────────┘  │  │
│  │  Java Class Libraries (rt.jar)    │  │  
│  └───────────────────────────────────┘  │
│  javac, javadoc, jdb, jmap, jstat ...   │
└─────────────────────────────────────────┘
```

| Komponent | Məzmun | İstifadə |
|-----------|--------|----------|
| **JVM** | Virtual maşın, bytecode executor | Proqram icrasının nüvəsi |
| **JRE** | JVM + standart kitabxanalar | Java proqramı işlətmək |
| **JDK** | JRE + development tools | Java proqramı yazmaq və qurmaq |

> Java 11-dən etibarən JRE ayrıca distribute edilmir — JDK birbaşa istifadə olunur.

---

## ClassLoader Subsistemi

ClassLoader `.class` fayllarını oxuyub JVM-ə yükləyir. Üç əsas mərhələdən ibarətdir:

```
Loading → Linking (Verification + Preparation + Resolution) → Initialization
```

### ClassLoader İerarxiyası

```
Bootstrap ClassLoader (C++ ilə yazılmış, JVM-in özü)
        ↑ parent delegation
Extension/Platform ClassLoader
        ↑ parent delegation  
Application/System ClassLoader
        ↑ parent delegation
Custom ClassLoader (istifadəçi tərəfindən)
```

```java
public class ClassLoaderDemo {
    public static void main(String[] args) {
        // String sinfi Bootstrap ClassLoader tərəfindən yüklənir
        Class<String> stringClass = String.class;
        System.out.println("String loader: " + stringClass.getClassLoader());
        // null çıxır — Bootstrap ClassLoader Java-da null kimi görünür

        // ArrayList sinfi Platform ClassLoader ilə yüklənir
        Class<java.util.ArrayList> listClass = java.util.ArrayList.class;
        System.out.println("ArrayList loader: " + listClass.getClassLoader());

        // Öz yazdığımız sinif Application ClassLoader ilə yüklənir
        Class<ClassLoaderDemo> myClass = ClassLoaderDemo.class;
        System.out.println("MyClass loader: " + myClass.getClassLoader());
        // sun.misc.Launcher$AppClassLoader@... çıxır
    }
}
```

---

## Runtime Data Areas

JVM-in yaddaş bölgələri:

```
┌──────────────────────────────────────────────────────┐
│                    JVM Memory                        │
│  ┌────────────────────┐  ┌────────────────────────┐  │
│  │       HEAP         │  │      METASPACE         │  │
│  │  ┌──────────────┐  │  │  (sinif metadata)      │  │
│  │  │  Young Gen   │  │  │  Java 8+ da PermGen-i  │  │
│  │  │  Eden+S0+S1  │  │  │  əvəz edir             │  │
│  │  ├──────────────┤  │  └────────────────────────┘  │
│  │  │   Old Gen    │  │  ┌────────────────────────┐  │
│  │  │  (Tenured)   │  │  │     CODE CACHE         │  │
│  │  └──────────────┘  │  │  (JIT compiled code)   │  │
│  └────────────────────┘  └────────────────────────┘  │
│                                                      │
│  Her thread üçün ayrıca:                             │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐ │
│  │  STACK   │  │PC Reg.   │  │Native Method Stack │ │
│  │ (frames) │  │          │  │                    │ │
│  └──────────┘  └──────────┘  └────────────────────┘ │
└──────────────────────────────────────────────────────┘
```

### Heap
- **Young Generation**: Yeni yaradılan obyektlər burada başlayır
  - **Eden Space**: Obyektlər ilk burada yaranır
  - **Survivor S0/S1**: Minor GC-dən sağ çıxan obyektlər
- **Old Generation (Tenured)**: Uzunmüddətli obyektlər

### Stack
- Hər thread-in öz stack-i var
- **Stack Frame**: Hər metod çağırışı üçün yaranır
- Frame içərisində: local variables, operand stack, frame data

### Metaspace
- Siniflərin metadata-sı (adı, metodları, sahələri)
- Java 8-dən PermGen-i əvəz etdi
- Native yaddaşdan istifadə edir (heap-dən kənar)

---

## Execution Engine

```
┌─────────────────────────────────────────┐
│           Execution Engine              │
│  ┌─────────────┐  ┌───────────────────┐ │
│  │ Interpreter │  │   JIT Compiler    │ │
│  │             │  │  ┌─────────────┐  │ │
│  │ Bytecode-u  │  │  │ C1 (Client) │  │ │
│  │ sətir-sətir │  │  ├─────────────┤  │ │
│  │ təfsir edir │  │  │ C2 (Server) │  │ │
│  └─────────────┘  │  └─────────────┘  │ │
│                   └───────────────────┘ │
│  ┌─────────────────────────────────────┐│
│  │      Garbage Collector (GC)         ││
│  └─────────────────────────────────────┘│
└─────────────────────────────────────────┘
```

### Interpreter
- Bytecode instruction-larını bir-bir oxuyub icra edir
- Tez başlayır, amma yavaş icra edir
- Her dəfə eyni kodu yenidən interpret edir

### JIT Compiler (Just-In-Time)
- Tez-tez çağırılan "hot" metodları native koda çevirir
- İlk çağırışda yavaş, sonra çox sürətli
- **C1**: Sürətli compile, az optimizasiya (client tərəflər üçün)
- **C2**: Yavaş compile, güclü optimizasiya (server tərəflər üçün)
- **Tiered Compilation**: Əvvəl C1, sonra C2 (Java 8+ default)

### Garbage Collector
- İstifadə olunmayan obyektləri avtomatik silir
- Müxtəlif alqoritmlər: Serial, Parallel, G1, ZGC, Shenandoah

---

## Bytecode və .class faylı

Java source kodu `javac` ilə compile olunduqda `.class` faylı yaranır. Bu fayl **platform-müstəqil** bytecode ehtiva edir.

```java
// Sadə sinif
public class Hello {
    public static void main(String[] args) {
        System.out.println("Salam, Dünya!");
    }
}
```

```bash
# Compile etmək
javac Hello.java

# Bytecode-u oxumaq (javap — disassembler)
javap -c Hello

# Nəticə:
# public static void main(java.lang.String[]);
#   Code:
#      0: getstatic     #7   // Field java/lang/System.out
#      3: ldc           #13  // String Salam, Dünya!
#      5: invokevirtual #15  // Method java/io/PrintStream.println
#      8: return
```

### .class Fayl Strukturu

```
magic number:      0xCAFEBABE  (hər .class faylı belə başlayır)
minor_version:     0
major_version:     61          (Java 17 = 61, Java 11 = 55, Java 8 = 52)
constant_pool:     [sabitlər cədvəli — string-lər, sinif adları, ...]
access_flags:      ACC_PUBLIC, ACC_SUPER
this_class:        #indexi
super_class:       #indexi
interfaces:        [...]
fields:            [sahə təsvirləri]
methods:           [metod bytecode-u]
attributes:        [SourceFile, LineNumberTable, ...]
```

```java
// .class faylının magic number-ini yoxlamaq
import java.io.*;

public class ClassFileInspector {
    public static void main(String[] args) throws IOException {
        // Hello.class faylını oxuyuruq
        try (FileInputStream fis = new FileInputStream("Hello.class")) {
            byte[] magic = new byte[4];
            fis.read(magic);
            // 0xCA 0xFE 0xBA 0xBE çıxmalıdır
            System.out.printf("Magic: %02X %02X %02X %02X%n",
                magic[0] & 0xFF, magic[1] & 0xFF,
                magic[2] & 0xFF, magic[3] & 0xFF);

            int minor = (fis.read() << 8) | fis.read();
            int major = (fis.read() << 8) | fis.read();
            System.out.println("Version: " + major + "." + minor);
            // Java 17 üçün: 61.0
        }
    }
}
```

---

## JVM Dilləri

JVM-in bytecode-u icra etməsi yalnız Java ilə məhdudlaşmır. Başqa dillər də JVM üçün bytecode generasiya edə bilər:

| Dil | Xüsusiyyət | İstifadə Sahəsi |
|-----|-----------|-----------------|
| **Kotlin** | Java ilə tam interoperability, null-safety, coroutines | Android, backend |
| **Scala** | Funksional + OOP, güclü tip sistemi | Big Data (Spark), akademik |
| **Groovy** | Dinamik tipləmə, DSL yaratmaq | Build scripts (Gradle), scripting |
| **Clojure** | Lisp dialekti, immutable data | Funksional proqramlaşdırma |
| **JRuby** | Ruby-nin JVM versiyası | Ruby proqramlarını JVM-də işlətmək |
| **Jython** | Python-un JVM versiyası | Python + Java inteqrasiyası |

```kotlin
// Kotlin kodu — JVM bytecode-a compile olur
// Java ilə tam uyğundur
fun main() {
    val list = listOf("Java", "Kotlin", "Scala")
    list.filter { it.length > 4 }
        .map { it.uppercase() }
        .forEach { println(it) }
}
```

```groovy
// Groovy — Gradle build script nümunəsi
plugins {
    id 'java'
}

dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web:3.2.0'
    testImplementation 'org.junit.jupiter:junit-jupiter:5.10.0'
}
```

---

## JVM-in Tam Arxitektura Diaqramı

```
┌─────────────────────────────────────────────────────────────────┐
│                        JVM                                      │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                ClassLoader Subsystem                     │  │
│  │   Bootstrap → Platform → Application → Custom            │  │
│  │   Loading → Linking (Verify+Prepare+Resolve) → Init      │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                Runtime Data Areas                        │  │
│  │   ┌────────────┐  ┌───────────┐  ┌──────────────────┐   │  │
│  │   │    Heap    │  │ Metaspace │  │   Code Cache     │   │  │
│  │   └────────────┘  └───────────┘  └──────────────────┘   │  │
│  │   ┌────────────┐  ┌───────────┐  ┌──────────────────┐   │  │
│  │   │ JVM Stack  │  │ PC Reg.   │  │ Native Mth Stack │   │  │
│  │   │ (per thrd) │  │(per thrd) │  │   (per thread)   │   │  │
│  │   └────────────┘  └───────────┘  └──────────────────┘   │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                 Execution Engine                         │  │
│  │   ┌─────────────┐  ┌──────────────┐  ┌──────────────┐   │  │
│  │   │ Interpreter │  │ JIT Compiler │  │     GC       │   │  │
│  │   └─────────────┘  └──────────────┘  └──────────────┘   │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Native Method Interface (JNI)               │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                           ↓
              Operating System + Hardware
```

---

## İntervyu Sualları

**S: JVM, JRE və JDK arasındakı fərq nədir?**
C: JVM bytecode-u icra edən virtual maşındır. JRE = JVM + standart kitabxanalar (proqram işlətmək üçün). JDK = JRE + development tools (javac, jdb, jmap) — proqram yazmaq üçün lazımdır.

**S: "Write Once, Run Anywhere" necə işləyir?**
C: Java source kodu platform-müstəqil bytecode-a compile olunur. Bu bytecode istənilən əməliyyat sistemindəki JVM tərəfindən icra oluna bilir. JVM isə hər platforma üçün ayrıca implementasiya olunur.

**S: Interpreter ilə JIT compiler arasındakı fərq?**
C: Interpreter bytecode-u sətir-sətir təfsir edərək icra edir — tez başlayır, yavaş işləyir. JIT tez-tez çağırılan "hot" metodları native machine code-a compile edir — ilk çağırışda yavaş, sonra çox sürətli.

**S: Metaspace nədir? PermGen-dən fərqi?**
C: Hər ikisi sinif metadata-sını saxlayır. PermGen Java heap-in hissəsi idi (fixed size, `OutOfMemoryError: PermGen space`). Metaspace Java 8-dən gəldi, native memory-dən istifadə edir, default olaraq avtomatik böyüyə bilir.

**S: JVM-də kaç yaddaş sahəsi var?**
C: Heap (Young+Old Gen), Metaspace, Code Cache (heap-dən kənarda — shared); hər thread üçün: Stack, PC Register, Native Method Stack.

**S: .class faylının ilk 4 byte-ı nədir?**
C: `0xCAFEBABE` — bu "magic number" hər valid Java class faylında olmalıdır. JVM faylı yükləməzdən əvvəl bunu yoxlayır.

**S: Hansı başqa dillər JVM-də işləyir?**
C: Kotlin, Scala, Groovy, Clojure, JRuby, Jython. Bunların hamısı öz source kodlarını JVM bytecode-una compile edir.

**S: ClassLoader-in vəzifəsi nədir?**
C: .class fayllarını tapıb yükləmək (load), bytecode-u verify etmək, static sahələr üçün yaddaş ayırmaq (prepare), sinfi initialize etmək (static blokları icra etmək).
