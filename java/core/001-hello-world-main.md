# 001 — İlk Java Proqramı və main() Metodu
**Səviyyə:** Başlanğıc


## Mündəricat
1. [Hello World — ilk proqram](#1-hello-world)
2. [Sətir-sətir izahat](#2-setir-setir)
3. [main() metodu — hər söz nə deməkdir?](#3-main-metodu)
4. [public açar sözü](#4-public)
5. [static açar sözü](#5-static)
6. [void açar sözü](#6-void)
7. [main adı və String[] args](#7-main-args)
8. [System.out.println() dərinlik](#8-system-out)
9. [javac — kompilyasiya prosesi](#9-javac)
10. [java — icra prosesi](#10-java)
11. [.java vs .class faylları](#11-java-class)
12. [Java 11+ tək fayl rejimi](#12-single-file)
13. [Command-line arqumentləri](#13-args)
14. [Ümumi Səhvlər](#14-sehvler)
15. [İntervyu Sualları](#15-intervyu)

---

## 1. Hello World — ilk proqram {#1-hello-world}

Hər proqramlaşdırma dilinin simvolik ilk proqramı:

```java
// Fayl adı: HelloWorld.java
public class HelloWorld {
    public static void main(String[] args) {
        System.out.println("Salam, dünya!");
    }
}
```

### Kompilyasiya və işə salma:

```bash
$ javac HelloWorld.java       # .java faylını .class-a çevir
$ java HelloWorld             # .class faylını işə sal
Salam, dünya!
```

Real dünyada analogiyası: `.java` — yemək reseptinin yazılı halıdır, `.class` — hazır yeməkdir, `javac` — aşpaz, `java` — yeməyi yeyən kimsədir (JVM).

---

## 2. Sətir-sətir izahat {#2-setir-setir}

Hər sətri dərindən anlayaq:

```java
public class HelloWorld {                        // 1-ci sətir
    public static void main(String[] args) {     // 2-ci sətir
        System.out.println("Salam, dünya!");     // 3-cü sətir
    }                                            // 4-cü sətir (metod bağlanır)
}                                                // 5-ci sətir (class bağlanır)
```

| Sətir | İzah |
|---|---|
| 1 | `public` — hər yerdən görünür; `class` — bu yeni sinifdir; `HelloWorld` — sinifin adı; `{` — sinfin gövdəsi başlayır |
| 2 | `public` — hər yerdən görünür; `static` — obyekt yaratmadan çağırıla bilər; `void` — geri heç nə qaytarmır; `main` — xüsusi "başlanğıc" metod; `String[] args` — command-line parametrlər |
| 3 | `System.out` — standart çıxış axını; `println` — xətti çap et + yeni sətir; `"Salam, dünya!"` — çap ediləcək mətn; `;` — sətirin sonu |
| 4 | `}` — `main` metodu bitdi |
| 5 | `}` — class bitdi |

---

## 3. main() metodu — hər söz nə deməkdir? {#3-main-metodu}

`public static void main(String[] args)` — bu imzanı **hərfi-hərfi yadda saxlamaq** lazımdır. JVM `java HelloWorld` yazdıqda məhz bu imzaya uyğun metodu axtarır.

```java
public  static  void  main  (String[] args)
  ↑       ↑      ↑     ↑          ↑
  │       │      │     │          │
  │       │      │     │          └── arqumentlər: String massivi
  │       │      │     └── metod adı: mütləq "main"
  │       │      └── return tipi: void (heç nə qaytarmır)
  │       └── obyekt yaratmadan çağırıla bilir
  └── JVM hər yerdən görə bilməlidir
```

### Hər birinin detalı:

---

## 4. public açar sözü {#4-public}

`public` — **access modifier** (giriş səviyyəsi).

```java
public class HelloWorld { }   // digər paketdəki siniflər də istifadə edə bilər
class HelloWorld { }          // yalnız eyni paket (package-private)
```

### Java-da 4 giriş səviyyəsi:

| Modifier | Eyni class | Eyni paket | Alt sinif | Hamı |
|---|---|---|---|---|
| `private` | ✓ | ✗ | ✗ | ✗ |
| *(heç nə)* | ✓ | ✓ | ✗ | ✗ |
| `protected` | ✓ | ✓ | ✓ | ✗ |
| `public` | ✓ | ✓ | ✓ | ✓ |

`main` metodu **mütləq `public`** olmalıdır, çünki JVM onu xarici sistemdən (classpath kontekstindən) çağırır.

```java
// YANLIŞ — main tapılmayacaq
class HelloWorld {
    private static void main(String[] args) { }
}
// Xəta: Main method not found in class HelloWorld
```

---

## 5. static açar sözü {#5-static}

`static` — metod **obyekt yaratmadan** çağırıla bilir. Obyektdən deyil, **sinifdən** çağırılır.

### Static olmadan:

```java
class Salam {
    void salam() {
        System.out.println("Salam!");
    }
}

// İstifadə — obyekt yaratmaq lazımdır
Salam s = new Salam();
s.salam();
```

### Static ilə:

```java
class Salam {
    static void salam() {
        System.out.println("Salam!");
    }
}

// İstifadə — birbaşa sinifdən çağır
Salam.salam();
```

### Niyə `main` static olmalıdır?

JVM hələ obyekt yarada bilmir — çünki class yeni yüklənib. Obyekt yaratmaq üçün bir metod çağırmaq lazımdır. Bu metod static olanda JVM-in obyekt yaratmağa ehtiyacı qalmır:

```java
// JVM arxa planda bunu edir:
HelloWorld.main(new String[]{});   // obyektsiz çağırış
```

---

## 6. void açar sözü {#6-void}

`void` — metod **heç nə qaytarmır**.

```java
// void — heç nə qaytarmır
public static void salamla() {
    System.out.println("Salam");
    // return; yazmaq olar ama lazım deyil
}

// int — tam ədəd qaytarır
public static int toplayan(int a, int b) {
    return a + b;    // return mütləqdir
}

// String — mətn qaytarır
public static String ad() {
    return "Əli";
}
```

`main` metodu niyə `void`-dur? JVM proqramı başlatır və `main` bitdikdə proses sonlanır. `main`-dən dəyər qaytarmaq mənasız olardı — onu kim istifadə edəcəkdi? (OS exit code-u üçün `System.exit(n)` var.)

---

## 7. main adı və String[] args {#7-main-args}

### "main" adı qanundur:

```java
// YANLIŞ — ad "main" deyil
public static void Main(String[] args) { }    // böyük M — fərqli metod
public static void start(String[] args) { }   // "start" — JVM tanımır
// Hər ikisi: "Error: Main method not found"
```

### `String[] args` nə üçündür?

Command-line arqumentlərini qəbul edir.

```java
public class ArgNumuna {
    public static void main(String[] args) {
        System.out.println("Arqument sayı: " + args.length);
        for (int i = 0; i < args.length; i++) {
            System.out.println("args[" + i + "] = " + args[i]);
        }
    }
}
```

```bash
$ javac ArgNumuna.java
$ java ArgNumuna salam dunya 123
Arqument sayı: 3
args[0] = salam
args[1] = dunya
args[2] = 123
```

### Alternativ yazılışlar (hamısı eyni işləyir):

```java
public static void main(String[] args) { }         // klassik
public static void main(String args[]) { }         // C-stili
public static void main(String... args) { }        // varargs (Java 5+)
```

---

## 8. System.out.println() dərinlik {#8-system-out}

Sadə görünür, ancaq içində 3 ayrı hissə var:

```java
System.out.println("Salam");
  ↑     ↑      ↑
  │     │      └── println — metod (newline ilə çap et)
  │     └── out — PrintStream obyekti (static field)
  └── System — java.lang paketindən class
```

### System class-ı:

```java
public final class System {
    public static final PrintStream out;    // standart çıxış (stdout)
    public static final PrintStream err;    // xəta çıxışı (stderr)
    public static final InputStream in;     // standart giriş (stdin)
}
```

### Çap metodları:

| Metod | Davranış |
|---|---|
| `println(x)` | Çap et və **yeni sətir** əlavə et |
| `print(x)` | Çap et, sətir qırma yoxdur |
| `printf(format, args)` | Formatlaşdırılmış çap |
| `println()` | Yalnız boş sətir |

```java
System.out.print("Salam ");
System.out.print("dünya");
System.out.println();                         // sətri bitir
System.out.println("Yeni sətir");

// printf — C-stili formatlama
System.out.printf("Ad: %s, yaş: %d%n", "Əli", 25);
// Ad: Əli, yaş: 25

// String.format — mətn halında
String s = String.format("%.2f", 3.14159);    // "3.14"
```

### Stderr — xəta mesajları üçün:

```java
System.out.println("Normal mesaj");     // stdout-a gedir
System.err.println("Xəta mesajı!");     // stderr-ə gedir (ayrıca axın)
```

---

## 9. javac — kompilyasiya prosesi {#9-javac}

`javac` (Java Compiler) — `.java` mətn faylını `.class` bytecode faylına çevirir.

### Addım-addım:

```bash
$ ls
HelloWorld.java

$ javac HelloWorld.java

$ ls
HelloWorld.class   HelloWorld.java
```

### Bytecode necə görünür?

```bash
$ javap -c HelloWorld       # disassemble et
Compiled from "HelloWorld.java"
public class HelloWorld {
  public HelloWorld();
    Code:
       0: aload_0
       1: invokespecial #1  // Method java/lang/Object."<init>":()V
       4: return

  public static void main(java.lang.String[]);
    Code:
       0: getstatic     #7  // Field System.out:Ljava/io/PrintStream;
       3: ldc           #13 // String Salam, dünya!
       5: invokevirtual #15 // Method PrintStream.println:(Ljava/lang/String;)V
       8: return
}
```

Bu "bytecode" heç bir prosessorun dilində deyil — yalnız JVM tərəfindən anlaşılan ara dildir.

### javac opsiyaları:

```bash
javac -d out HelloWorld.java              # .class faylını out/ qovluğuna yaz
javac -d out src/*.java                   # bütün src-dəki faylları kompil et
javac --release 21 HelloWorld.java        # Java 21 üçün kompil et
javac -classpath lib/*.jar HelloWorld.java # külçanlıq əlavə et
```

---

## 10. java — icra prosesi {#10-java}

`java` əmri JVM-i işə salır və `.class` faylını icra edir.

### Arxa planda nə baş verir?

```
1. JVM prosesi başlayır
2. ClassLoader — HelloWorld.class faylını tapır və yükləyir
3. Bytecode Verifier — bytecode-un təhlükəsiz olduğunu yoxlayır
4. JVM — HelloWorld.main(String[]) metodunu tapır
5. Execution Engine — bytecode-u interpretasiya edir və/yaxud JIT ilə native koda çevirir
6. Metod bitdikdə JVM bağlanır
```

### ASCII diaqram:

```
HelloWorld.java ──[javac]──► HelloWorld.class ──[java]──► JVM ──► OS
   (qaynaq)                      (bytecode)               (runtime)
```

### Vacib qeyd — uzantı yazmayın:

```bash
java HelloWorld          # DÜZGÜN
java HelloWorld.class    # YANLIŞ — "Could not find class"
java HelloWorld.java     # Java 11+ — faylı birbaşa işə sal (tək fayl rejimi)
```

### Classpath:

```bash
# Bir class-ın yerini göstərmək
java -cp . HelloWorld           # cari qovluqda axtar
java -cp build/classes HelloWorld
java -cp "lib/*:build/classes" com.example.App   # Linux/macOS
java -cp "lib\*;build\classes" com.example.App    # Windows
```

---

## 11. .java vs .class faylları {#11-java-class}

| Xüsusiyyət | `.java` | `.class` |
|---|---|---|
| Məzmun | İnsan tərəfindən oxuna bilən qaynaq kod | Bytecode (binary) |
| Kim yaradır? | Proqramçı | `javac` kompilyatoru |
| Kim oxuyur? | IDE, kompilyator | JVM |
| Uzantı | `.java` | `.class` |
| Yaradılma qaydası | Hər ictimai class üçün ayrıca fayl | Hər class və nested class üçün ayrı fayl |

### Nümunə:

```java
// fayl: MyApp.java
public class MyApp {
    public static void main(String[] args) { }

    static class Inner {  }    // nested class

    static class Helper { }    // başqa nested class
}

class Köməkçi { }    // package-private class (ayrı ictimai yox)
```

Kompilyasiyadan sonra:

```bash
$ ls *.class
MyApp.class
MyApp$Inner.class
MyApp$Helper.class
Köməkçi.class
```

Hər class üçün ayrıca `.class` faylı yaradılır (nested üçün `$` istifadə olunur).

---

## 12. Java 11+ tək fayl rejimi {#12-single-file}

Java 11-dən etibarən `javac` işlətmədən birbaşa icra etmək olar:

```java
// fayl: Skript.java
public class Skript {
    public static void main(String[] args) {
        System.out.println("Skript işləyir!");
    }
}
```

```bash
$ java Skript.java
Skript işləyir!
```

Arxa planda JVM yaddaşda kompilyasiya edir — disk-də `.class` yaranmır.

### Java 21+ — "instance main" və implicit class (preview):

Java 21 və 25-də yeni başlayanlar üçün daha sadə sintaksis təklif olunur:

```java
// Java 21 (preview) / Java 25+ (standart)
// fayl: Sade.java
void main() {
    System.out.println("class da lazım deyil!");
}
```

```bash
java --enable-preview --source 21 Sade.java
```

Bu xüsusiyyət tədris üçün nəzərdə tutulub. Məqsəd — Python-a bənzər sadəlik.

---

## 13. Command-line arqumentləri {#13-args}

### Tam praktik nümunə — kalkulyator:

```java
public class Kalkulator {
    public static void main(String[] args) {
        if (args.length != 3) {
            System.err.println("İstifadə: java Kalkulator <a> <+,-,*,/> <b>");
            System.exit(1);        // səhv exit code ilə çıx
        }

        double a = Double.parseDouble(args[0]);
        String op = args[1];
        double b = Double.parseDouble(args[2]);

        double netice;
        switch (op) {
            case "+" -> netice = a + b;
            case "-" -> netice = a - b;
            case "*" -> netice = a * b;
            case "/" -> {
                if (b == 0) {
                    System.err.println("Sıfıra bölmə!");
                    System.exit(2);
                }
                netice = a / b;
            }
            default -> {
                System.err.println("Bilinməyən operator: " + op);
                return;
            }
        }

        System.out.printf("%.2f %s %.2f = %.2f%n", a, op, b, netice);
    }
}
```

```bash
$ javac Kalkulator.java
$ java Kalkulator 10 + 5
10.00 + 5.00 = 15.00

$ java Kalkulator 20 / 4
20.00 / 4.00 = 5.00

$ java Kalkulator 10 % 3
Bilinməyən operator: %
```

### Arqumentləri parse etmək:

```java
public class ArgParser {
    public static void main(String[] args) {
        String ad = "Qonaq";          // default
        int yas = 0;

        for (int i = 0; i < args.length; i++) {
            switch (args[i]) {
                case "--ad", "-a" -> ad = args[++i];
                case "--yas", "-y" -> yas = Integer.parseInt(args[++i]);
                case "--help", "-h" -> {
                    System.out.println("İstifadə: --ad <ad> --yas <ədəd>");
                    return;
                }
            }
        }

        System.out.println("Salam " + ad + ", sən " + yas + " yaşındasan.");
    }
}
```

```bash
$ java ArgParser --ad "Əli" --yas 25
Salam Əli, sən 25 yaşındasan.
```

**Qeyd:** Real layihələrdə `picocli`, `JCommander` kimi kitabxanalar istifadə olunur.

---

## 14. Ümumi Səhvlər {#14-sehvler}

### Səhv 1: `main` metodunun imzası səhvdir

```java
public class Test {
    public void main(String[] args) { }          // static yoxdur
    // Xəta: Main method is not static
}
```

```java
public class Test {
    public static int main(String[] args) { }    // int qaytarır
    // Xəta: Main method must return void
}
```

```java
public class Test {
    public static void main(String args) { }     // String, massiv yox
    // Xəta: Main method not found (imza səhvdir)
}
```

### Səhv 2: Class public, fayl adı ilə uyğun deyil

```java
// fayl: salam.java
public class Salam { }   // böyük S, fayl kiçik s
```

```
error: class Salam is public, should be declared in a file named Salam.java
```

### Səhv 3: `.class` uzantısı yazmaq

```bash
$ java HelloWorld.class
Error: Could not find or load main class HelloWorld.class
```

### Səhv 4: Paketi nəzərə almamaq

```java
// fayl: com/example/App.java
package com.example;

public class App {
    public static void main(String[] args) { }
}
```

```bash
# YANLIŞ — paketsiz işə salmaq
$ cd com/example
$ java App
Error: Could not find or load main class App
# Səbəb: package com.example; var, amma kök qovluqdan olmayaraq işə salınır

# DÜZGÜN:
$ cd ../..           # kök qovluğa qayıt
$ java com.example.App
```

### Səhv 5: `System.out.println` əvəzinə `print` ilə newline yaddan çıxarmaq

```java
System.out.print("Salam");    // newline yoxdur
System.out.print("Dünya");
// Çıxış: SalamDünya
```

Həll — `println` istifadə et və ya `\n` əlavə et.

### Səhv 6: `;` unutmaq

```java
System.out.println("Salam")     // ; yoxdur
// error: ';' expected
```

---

## 15. İntervyu Sualları {#15-intervyu}

**S1: `public static void main(String[] args)` imzasının hər sözü niyə lazımdır?**
> `public` — JVM başqa kontekstdən çağırır, görünməlidir; `static` — JVM obyekt yaratmadan çağıra bilmək üçün; `void` — heç nə qaytarmır (çıxışdan sonra JVM bağlanır); `main` — JVM məhz bu adı axtarır; `String[] args` — command-line arqumentləri.

**S2: Java `main` metodu overload edilə bilərmi?**
> Bəli, `main` adlı başqa metodlar yazıla bilər, ancaq JVM məhz `public static void main(String[])` imzalı birini çağırır. Digərləri sadəcə normal metodlar kimi qalır.

**S3: Bir `.java` faylında neçə `public class` ola bilər?**
> Yalnız **bir**. Və o class-ın adı fayl adı ilə eyni olmalıdır. `.java` faylında istənilən sayda package-private (modifiersiz) class ola bilər.

**S4: `.java` faylı ilə `.class` faylı arasındakı fərq nədir?**
> `.java` — insan yazdığı qaynaq koddur (mətn). `.class` — `javac`-in yaratdığı bytecode-dur (binary). JVM yalnız `.class` faylını icra edir.

**S5: `javac` kompilyatoru `.class` faylında hansı hədəf platformanı nəzərə alır?**
> Heç birini — bytecode platforma-müstəqildir. JVM runtime-da hədəf OS/CPU-ya uyğun JIT kompilyasiya edir.

**S6: `System.out.println` niyə belə uzun yazılır?**
> `System` — class, `out` — `System`-in static field-i (PrintStream obyekti), `println` — o obyektin metodu. Python-un `print`-i Java-da qısaldılıb olmur çünki Java hər şeyi sinif içinə qoyur.

**S7: `java HelloWorld.java` ilə `javac HelloWorld.java && java HelloWorld` arasındakı fərq nədir?**
> Java 11+ birinci üsul tək faylı yaddaşda kompil edib işə salır — disk-də `.class` yaranmır. Yalnız tək fayllı skriptlər üçün istifadə olunur. Böyük layihələrdə normal `javac`/`java` cütlüyü lazımdır.

**S8: Command-line arqumentlərin tipi niyə `String[]`-dir?**
> OS komandaları həmişə mətn kimi ötürür. Əgər ədəd lazımdırsa, `Integer.parseInt(args[0])` ilə çevirmək lazımdır.

**S9: İki fərqli `.java` faylı eyni class adı istifadə edə bilərmi?**
> Eyni paketdə — xeyr, ziddiyyət olur. Fərqli paketlərdə — bəli (`com.a.User` və `com.b.User` müxtəlifdir). Paket — class-ların "soyadıdır".

**S10: `System.exit(0)` nə edir?**
> JVM-i dərhal bağlayır və OS-ə exit code qaytarır. `0` — uğur, digər ədədlər — xəta. `main` normal bitərsə eyni effekt verir, lakin `exit` istənilən yerdə çağrıla bilər.
