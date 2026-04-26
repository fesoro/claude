# 56 — Generics — Type Erasure (Tip Silinməsi)

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Type Erasure nədir?](#type-erasure-nədir)
2. [Compile vs Runtime davranışı](#compile-vs-runtime)
3. [Niyə `new T()` olmur?](#niyə-new-t-olmur)
4. [instanceof ilə Generics](#instanceof-ilə-generics)
5. [Heap Pollution](#heap-pollution)
6. [@SuppressWarnings("unchecked")](#suppresswarnings)
7. [Reifiable Types](#reifiable-types)
8. [Erasure-un praktiki nəticələri](#erasure-nəticələri)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Type Erasure nədir?

Type Erasure — Java kompilyatorunun generic tip parametrlərini **silməsi** prosesidir. Bu, Java 5-də geriyə uyğunluq (backward compatibility) üçün edilib.

**Nə baş verir:**
- Compile time-da tip parametrləri yoxlanılır
- Bytecode-a çevirilərkən tip parametrləri silinir
- Runtime-da JVM generic tiplər haqqında məlumat saxlamır

```
SOURCE CODE          → COMPILER →  BYTECODE (JVM görür)
List<String>                        List
Box<T>                              Box
<T extends Number>                  Number (upper bound)
<T>                                 Object (bound yoxdur)
```

### Sadə nümunə

```java
// Yazılan kod
public class Qab<T> {
    private T dəyər;

    public T götür() { return dəyər; }
    public void qoy(T dəyər) { this.dəyər = dəyər; }
}

// Kompilyatorun gördüyü (erasure sonrası):
public class Qab {
    private Object dəyər; // T → Object oldu

    public Object götür() { return dəyər; }
    public void qoy(Object dəyər) { this.dəyər = dəyər; }
}
```

```java
// Yazılan kod
public class Müqayisəli<T extends Comparable<T>> {
    private T dəyər;

    public boolean böyükdür(T digər) {
        return dəyər.compareTo(digər) > 0;
    }
}

// Erasure sonrası — T → Comparable (upper bound):
public class Müqayisəli {
    private Comparable dəyər; // T → Comparable (üst hədd)

    public boolean böyükdür(Comparable digər) {
        return dəyər.compareTo(digər) > 0;
    }
}
```

---

## Compile vs Runtime

### Compile time — tip yoxlanılır

```java
List<String> sözlər = new ArrayList<>();
sözlər.add("Salam");
// sözlər.add(42);  // COMPILE XƏTA — typ yoxlaması işləyir

String söz = sözlər.get(0); // Cast lazım deyil
```

### Runtime — tip parametri yoxdur

```java
import java.util.*;

List<String> siyahı1 = new ArrayList<>();
List<Integer> siyahı2 = new ArrayList<>();

// Runtime-da eyni class!
System.out.println(siyahı1.getClass() == siyahı2.getClass()); // true
System.out.println(siyahı1.getClass().getName()); // java.util.ArrayList

// Instanceof ilə tip parametri yoxlamaq olmaz
// if (siyahı1 instanceof List<String>) { } // COMPILE XƏTA

// Yalnız raw type ilə olar (xəbərdarlıq verir)
if (siyahı1 instanceof List<?>) {  // OK — amma faydasız
    System.out.println("Siyahıdır");
}
```

### Bridge metodlar

Erasure nəticəsində compiler "körpü" metodlar yaradır:

```java
// Yazdığımız kod
public class MyComparable implements Comparable<String> {
    @Override
    public int compareTo(String other) {
        return 0;
    }
}

// Compiler əslində iki metod yaradır:
// 1. Bizim metod:
//    public int compareTo(String other) { return 0; }
// 2. Bridge metod (Comparable<Object> üçün):
//    public int compareTo(Object other) {
//        return compareTo((String) other); // cast əlavə edir
//    }
```

---

## Niyə `new T()` olmur?

Type erasure səbəbindən runtime-da T tipini bilmək mümkün deyil, ona görə `new T()` icazəli deyil.

### YANLIŞ — new T() cəhdi

```java
// YANLIŞ — compile xəta verir
public class Fabrika<T> {
    public T yarat() {
        return new T(); // COMPILE XƏTA: Cannot instantiate type T
    }
}
```

### DOĞRU — alternativ həllər

**1. Class token ötürmək:**

```java
// Class<T> ötürüb reflection istifadə etmək
public class Fabrika<T> {
    private final Class<T> tip;

    public Fabrika(Class<T> tip) {
        this.tip = tip;
    }

    public T yarat() {
        try {
            return tip.getDeclaredConstructor().newInstance();
        } catch (Exception e) {
            throw new RuntimeException("Yaratmaq alınmadı: " + tip.getName(), e);
        }
    }
}

// İstifadə
Fabrika<StringBuilder> fabrika = new Fabrika<>(StringBuilder.class);
StringBuilder sb = fabrika.yarat();
```

**2. Supplier<T> istifadə etmək:**

```java
import java.util.function.Supplier;

// Supplier — obyekt yaradan funksiya interfeysi
public class GenericFabrika<T> {
    private final Supplier<T> istehsalçı;

    public GenericFabrika(Supplier<T> istehsalçı) {
        this.istehsalçı = istehsalçı;
    }

    public T yarat() {
        return istehsalçı.get();
    }

    // Çox sayda yarat
    public List<T> yaratBir neçə(int sayı) {
        List<T> nəticə = new ArrayList<>();
        for (int i = 0; i < sayı; i++) {
            nəticə.add(istehsalçı.get());
        }
        return nəticə;
    }
}

// İstifadə
GenericFabrika<String> strFabrika = new GenericFabrika<>(String::new);
GenericFabrika<ArrayList> listFabrika = new GenericFabrika<>(ArrayList::new);

String s = strFabrika.yarat(); // ""
List<ArrayList> lists = listFabrika.yaratBir neçə(3);
```

**3. Generic massiv problemi:**

```java
// YANLIŞ — generic massiv yaratmaq olmaz
public class GenericSiyahı<T> {
    private T[] elementlər;

    public GenericSiyahı(int ölçü) {
        // elementlər = new T[ölçü]; // COMPILE XƏTA
    }
}

// DOĞRU 1 — Object[] istifadə et
public class GenericSiyahı<T> {
    private Object[] elementlər;

    public GenericSiyahı(int ölçü) {
        elementlər = new Object[ölçü]; // OK
    }

    @SuppressWarnings("unchecked")
    public T götür(int i) {
        return (T) elementlər[i]; // Unsafe cast — amma daxili istifadə üçün OK
    }

    public void qoy(int i, T element) {
        elementlər[i] = element;
    }
}

// DOĞRU 2 — Class token ilə
public class GenericSiyahı2<T> {
    private T[] elementlər;

    @SuppressWarnings("unchecked")
    public GenericSiyahı2(Class<T> tip, int ölçü) {
        // Reflection ilə generic massiv yaratmaq
        elementlər = (T[]) java.lang.reflect.Array.newInstance(tip, ölçü);
    }
}
```

---

## instanceof ilə Generics

Type erasure səbəbindən `instanceof` ilə parametrli tip yoxlamaq olmur.

### YANLIŞ

```java
Object obj = new ArrayList<String>();

// YANLIŞ — compile xəta
if (obj instanceof List<String>) { } // Compile xəta!

// YANLIŞ — runtime-da mənasız (həmişə true)
// List<String> və List<Integer> runtime-da eyni görünür
```

### DOĞRU

```java
Object obj = new ArrayList<String>();

// DOĞRU — raw type ilə yoxlama
if (obj instanceof List) { // OK, amma raw type xəbərdarlığı
    System.out.println("Siyahıdır");
}

// DOĞRU — wildcard ilə
if (obj instanceof List<?>) { // OK, xəbərdarlıq yoxdur
    List<?> siyahı = (List<?>) obj;
    System.out.println("Ölçü: " + siyahı.size());
}

// Java 16+ — pattern matching ilə
if (obj instanceof List<?> siyahı) { // Daha müasir yazı
    System.out.println("Ölçü: " + siyahı.size());
}
```

### Tip yoxlamasının alternativi

```java
// Elementlərin tipini yoxlamaq lazımdırsa
public static boolean hamısıStringdir(List<?> siyahı) {
    return siyahı.stream().allMatch(e -> e instanceof String);
}

// Daha güvənli cast
public static <T> Optional<List<T>> tipliSiyahı(
        List<?> siyahı, Class<T> tip) {
    if (siyahı.stream().allMatch(tip::isInstance)) {
        @SuppressWarnings("unchecked")
        List<T> nəticə = (List<T>) siyahı;
        return Optional.of(nəticə);
    }
    return Optional.empty();
}

// İstifadə
List<?> qarışıq = List.of("a", "b", "c");
tipliSiyahı(qarışıq, String.class)
    .ifPresent(s -> System.out.println("String siyahısı: " + s));
```

---

## Heap Pollution

Heap pollution — heap-dəki obyektin faktiki tipi ilə onun statik tipinin uyğun gəlməməsi vəziyyəti.

```java
// Heap pollution yaradan nümunə
@SuppressWarnings("unchecked")
public static void heapPollutionYarat() {
    List<String> sözlər = new ArrayList<>();
    List raw = sözlər; // Raw type — xəbərdarlıq

    raw.add(42); // Integer əlavə edirik — heap pollution!

    // İndi sözlər içində Integer var, amma tipi List<String>-dir
    String söz = sözlər.get(0); // ClassCastException runtime-da!
}
```

### Varargs ilə Heap Pollution

```java
// Bu metod heap pollution risklidir
// YANLIŞ — generic varargs xətərlidi
public static <T> List<T> siyahıYarat(T... elementlər) {
    // Varargs generic tip ilə istifadə — heap pollution riski
    return Arrays.asList(elementlər);
}

// DOĞRU — @SafeVarargs annotation ilə
@SafeVarargs
public static <T> List<T> güvənliYarat(T... elementlər) {
    // Bu annotation-dan istifadə üçün:
    // 1. Metodun varargs massivi dəyişdirilməməlidir
    // 2. Massiv başqa metodlara ötürülməməlidir
    return Arrays.asList(elementlər);
}

// @SafeVarargs yalnız bunlarda istifadə edilə bilər:
// - static metodlar
// - final metodlar
// - private metodlar
// - constructorlar
```

### Heap Pollution-dan necə qorunmaq

```java
// 1. Raw type istifadə etməmək
// 2. @SuppressWarnings("unchecked") yalnız lazım olduqda
// 3. Cast-ı yerelleştir (lokal saxla)
// 4. @SafeVarargs yalnız güvənli metodlarda

public class TəhlükəsizAnbar<T> {
    private final List<Object> elementlər = new ArrayList<>();
    private final Class<T> tip;

    public TəhlükəsizAnbar(Class<T> tip) {
        this.tip = tip;
    }

    public void əlavəEt(T element) {
        elementlər.add(tip.cast(element)); // Tip yoxlaması ilə
    }

    public T götür(int i) {
        return tip.cast(elementlər.get(i)); // Güvənli cast
    }
}
```

---

## @SuppressWarnings("unchecked")

Bu annotation unsafe cast xəbərdarlıqlarını susdurmaq üçün istifadə edilir.

### Nə vaxt istifadə etmək lazımdır?

```java
// 1. Yalnız MÜTLƏQ lazım olduqda istifadə et
// 2. Mümkün qədər kiçik scope-da tətbiq et
// 3. Həmişə şərh yaz — niyə güvənlidir

// YANLIŞ — geniş scope-da
@SuppressWarnings("unchecked") // Bütün metod üçün — pis!
public static <T> T ilkElement(List<?> siyahı) {
    // Metod içindəki bütün xəbərdarlıqlar susur
    List<T> tipli = (List<T>) siyahı;
    return tipli.isEmpty() ? null : tipli.get(0);
}

// DOĞRU — mümkün qədər kiçik scope
public static <T> T ilkElement(List<?> siyahı) {
    if (siyahı.isEmpty()) return null;
    // Bu cast güvənlidir çünki: biz yalnız oxuyuruq,
    // siyahıda olan elementlər T tipi kimi istifadə olunacaq
    @SuppressWarnings("unchecked")
    T element = (T) siyahı.get(0);
    return element;
}
```

### Həqiqi kod nümunəsi — ArrayList daxili implementasiyası

```java
// ArrayList.java-nın sadələşdirilmiş versiyası
public class MyArrayList<E> {
    private Object[] elementData; // Generic massiv əvəzinə Object[]
    private int size;

    public MyArrayList(int capacity) {
        elementData = new Object[capacity];
    }

    public void add(E element) {
        elementData[size++] = element;
    }

    // Cast güvənlidir — biz yalnız add() ilə E tipli elementlər əlavə edirik
    @SuppressWarnings("unchecked")
    public E get(int index) {
        return (E) elementData[index]; // Unchecked cast — amma güvənlidir
    }
}
```

---

## Reifiable Types

Reifiable type — runtime-da tam tip məlumatı mövcud olan tipdir.

### Reifiable types bunlardır:

```java
// 1. Primitiv tiplər
int, double, boolean // tam məlumat var

// 2. Parametrsiz (raw) tiplər
List, Map, ArrayList

// 3. Unbounded wildcard
List<?>, Map<?, ?>

// 4. Parametrsiz class/interface
String, Integer, Object

// 5. Massivlər (reifiable elementlər ilə)
String[], int[], List<?>[]
```

### Non-reifiable types (runtime-da məlumat itir):

```java
// Bu tiplər runtime-da görünmür:
List<String>     // → List
List<Integer>    // → List
Map<String, Integer> // → Map
<T>              // → Object (yaxud bound)
<T extends Number> // → Number
```

### Reifiable olmayan tip massivi

```java
// YANLIŞ — generic massiv olmaz
List<String>[] siyahıMassivi = new List<String>[10]; // COMPILE XƏTA

// Niyə? Massivlər reifiable tələb edir
// List<String> reifiable deyil (runtime-da List kimi görünür)

// DOĞRU alternativlər:
List<String>[] massiv1 = new List[10]; // Raw type — xəbərdarlıq
List<?>[] massiv2 = new List<?>[10];   // Wildcard — OK
List<List<String>> siyahıSiyahısı = new ArrayList<>(); // Ən yaxşısı
```

---

## Erasure-un Praktiki Nəticələri

### 1. Method Overloading problemi

```java
// YANLIŞ — erasure sonrası eyni imza
public class Problem {
    // Bu iki metod erasure sonrası eyni olur: process(List)
    public void process(List<String> siyahı) { } // COMPILE XƏTA
    public void process(List<Integer> siyahı) { } // eyni erasure!
}

// DOĞRU — fərqli adlar istifadə et
public class Həll {
    public void sözləriİşlə(List<String> siyahı) { }
    public void rəqəmləriİşlə(List<Integer> siyahı) { }

    // Yaxud generic metod
    public <T> void işlə(List<T> siyahı) { }
}
```

### 2. Static kontekstdə tip parametri

```java
// YANLIŞ — static sahə tip parametri istifadə edə bilməz
public class Qab<T> {
    // private static T instance; // COMPILE XƏTA

    // YANLIŞ — static metod class tip parametri istifadə edə bilməz
    // public static T getDefault() { return null; } // COMPILE XƏTA
}

// DOĞRU
public class Qab<T> {
    private static Object instance; // Object istifadə et

    // Static metodun öz tip parametri ola bilər
    public static <E> E boşDəyər() { return null; }
}
```

### 3. Exceptions ilə generics

```java
// YANLIŞ — generic exception yaratmaq olmaz
// public class GenericException<T> extends Exception { } // COMPILE XƏTA

// YANLIŞ — generic tipi catch-ə almaq olmaz
// try { ... } catch (SomeException<String> e) { } // COMPILE XƏTA

// DOĞRU — throws bəyanatında istifadə etmək olar
public <T extends Exception> void riskliBəlgə(T exception) throws T {
    throw exception;
}

// İstifadə
try {
    riskliBəlgə(new IOException("IO xətası"));
} catch (IOException e) {
    System.out.println("IO xəta: " + e.getMessage());
}
```

### 4. Runtime tip məlumatı əldə etmək

```java
// TypeToken pattern — Guava kitabxanasından ilham alınıb
// Bu yanaşma anonim alt sinif yaradaraq generic tipi saxlayır

import java.lang.reflect.*;

public abstract class TypeToken<T> {
    private final Type tip;

    // Anonim alt sinif yaradılarkən generic məlumat saxlanılır
    protected TypeToken() {
        Type üst sinif = getClass().getGenericSuperclass();
        if (üst sinif instanceof ParameterizedType pt) {
            this.tip = pt.getActualTypeArguments()[0];
        } else {
            throw new IllegalArgumentException("TypeToken-i düzgün istifadə et");
        }
    }

    public Type tipAl() { return tip; }
}

// İstifadə
TypeToken<List<String>> token = new TypeToken<List<String>>() {};
System.out.println(token.tipAl()); // java.util.List<java.lang.String>
// Runtime-da generic tip məlumatı saxlanıldı!
```

---

## İntervyu Sualları

**S: Type erasure nədir və niyə Java-da tətbiq edilib?**
C: Type erasure — kompilyatorun generic tip parametrlərini bytecode-dan silməsi prosesidir. Java 5-də generics əlavə edilərkən geriyə uyğunluq üçün tətbiq edildi — köhnə JVM-lər generic bytecode tanımırdı. Compile time-da tip yoxlaması aparılır, sonra tiplər silinir (T→Object, T extends Number→Number).

**S: Runtime-da generic tip parametrini necə əldə etmək olar?**
C: Birbaşa mümkün deyil — type erasure silib. Alternativlər: 1) `Class<T>` token ötürmək, 2) `TypeToken` pattern (anonim alt sinif ilə), 3) Reflection ilə field/method imzasından oxumaq (`getGenericType()`).

**S: Heap pollution nədir?**
C: Heap-dəki obyektin faktiki tipi statik tipi ilə uyğun gəlmədikdə yaranır. Raw type istifadəsi, unsafe cast, generic varargs səbəb ola bilər. Runtime-da `ClassCastException`-a gətirib çıxara bilər. `@SafeVarargs` ilə varargs heap pollution-dan qorunmaq olar.

**S: `@SuppressWarnings("unchecked")` nə zaman istifadə edilir?**
C: Unchecked cast xəbərdarlığını susdurmaq üçün — yalnız castin düzgünlüyünə əmin olduqda. Minimum scope-da tətbiq edilməli (metod deyil, bəyanat səviyyəsindədir) və şərh yazılmalıdır. Əgər şərh yaza bilmirsinizsə — güvənli deyil.

**S: Reifiable type nədir? Nümunə verin.**
C: Runtime-da tam tip məlumatı olan tiplərdir. Nümunələr: `String`, `int[]`, `List<?>`, `List` (raw). Non-reifiable nümunələr: `List<String>`, `Map<K,V>`, `T` (type parameter). Generic massiv (`List<String>[]`) olmur çünki `List<String>` reifiable deyil.

**S: Niyə `new T()` yazmaq olmur?**
C: Type erasure səbəbindən runtime-da T tipini bilmək olmur. Alternativlər: `Class<T>` token + reflection, `Supplier<T>`, factory method pattern.
