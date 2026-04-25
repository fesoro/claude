# 53 — Generics — Əsaslar

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Generics nədir?](#generics-nədir)
2. [Generic Class](#generic-class)
3. [Generic Interface](#generic-interface)
4. [Generic Method](#generic-method)
5. [Tip Parametr Konvensiyaları (T, E, K, V)](#tip-parametr-konvensiyaları)
6. [Raw Types — Niyə İstifadə Etməməli?](#raw-types)
7. [Generic Method vs Generic Class](#generic-method-vs-generic-class)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Generics nədir?

Generics (ümumi tiplər) Java 5-də təqdim edilib. Məqsədi — **tip təhlükəsizliyi** (type safety) təmin etmək və kod təkrarını azaltmaq.

Generics olmadan eyni məntiqi fərqli tiplər üçün təkrar yazmaq lazım gəlirdi:

```java
// Generics olmadan — hər tip üçün ayrı class yazmalıyıq
public class IntBox {
    private int value;
    public int get() { return value; }
    public void set(int value) { this.value = value; }
}

public class StringBox {
    private String value;
    public String get() { return value; }
    public void set(String value) { this.value = value; }
}
```

Generics ilə isə bir dəfə yazıb hər yerdə istifadə edirik:

```java
// Generics ilə — universal həll
public class Box<T> {
    private T value;

    public T get() { return value; }
    public void set(T value) { this.value = value; }
}

// İstifadəsi
Box<Integer> intBox = new Box<>();
intBox.set(42);

Box<String> strBox = new Box<>();
strBox.set("Salam");
```

---

## Generic Class

Generic class — bir və ya bir neçə tip parametri olan class-dır. Tip parametrləri `<>` içində yazılır.

### Sadə Generic Class

```java
// T — istənilən tipi təmsil edir
public class Qab<T> {
    private T məzmun;

    public Qab(T məzmun) {
        this.məzmun = məzmun;
    }

    public T götür() {
        return məzmun;
    }

    public void qoy(T məzmun) {
        this.məzmun = məzmun;
    }

    @Override
    public String toString() {
        return "Qab{məzmun=" + məzmun + "}";
    }
}

// İstifadə
Qab<String> sözQabı = new Qab<>("Azərbaycan");
String söz = sözQabı.götür(); // Cast lazım deyil!

Qab<Integer> rəqəmQabı = new Qab<>(2024);
int rəqəm = rəqəmQabı.götür();
```

### İki Tip Parametrli Class (Pair / Cüt)

```java
// K — açar tipi, V — dəyər tipi
public class Cüt<K, V> {
    private final K açar;
    private final V dəyər;

    public Cüt(K açar, V dəyər) {
        this.açar = açar;
        this.dəyər = dəyər;
    }

    public K açar() { return açar; }
    public V dəyər() { return dəyər; }

    // Factory method — daha oxunaqlı yaratma üsulu
    public static <K, V> Cüt<K, V> of(K açar, V dəyər) {
        return new Cüt<>(açar, dəyər);
    }

    @Override
    public String toString() {
        return "(" + açar + ", " + dəyər + ")";
    }
}

// İstifadə
Cüt<String, Integer> yaş = Cüt.of("Əli", 25);
System.out.println(yaş); // (Əli, 25)

Cüt<String, List<Integer>> siyahı = Cüt.of("rəqəmlər", List.of(1, 2, 3));
```

### Generic Stack nümunəsi

```java
import java.util.ArrayList;
import java.util.EmptyStackException;
import java.util.List;

public class Stack<E> {
    private final List<E> elementlər = new ArrayList<>();

    // Elementi yığına əlavə et
    public void push(E element) {
        elementlər.add(element);
    }

    // Üst elementi götür və sil
    public E pop() {
        if (isEmpty()) {
            throw new EmptyStackException();
        }
        return elementlər.remove(elementlər.size() - 1);
    }

    // Üst elementi bax, silmə
    public E peek() {
        if (isEmpty()) {
            throw new EmptyStackException();
        }
        return elementlər.get(elementlər.size() - 1);
    }

    public boolean isEmpty() {
        return elementlər.isEmpty();
    }

    public int size() {
        return elementlər.size();
    }
}

// İstifadə
Stack<String> sözYığını = new Stack<>();
sözYığını.push("Birinci");
sözYığını.push("İkinci");
sözYığını.push("Üçüncü");

System.out.println(sözYığını.pop()); // Üçüncü
System.out.println(sözYığını.peek()); // İkinci (silinmir)
```

---

## Generic Interface

Generic interface-lər də eyni sintaksislə yazılır.

```java
// Generic interface
public interface Çevirici<F, T> {
    T çevir(F mənbə);
}

// Implementasiya — konkret tiplər veririk
public class SözəRəqəmÇevirici implements Çevirici<String, Integer> {
    @Override
    public Integer çevir(String mənbə) {
        return Integer.parseInt(mənbə);
    }
}

// Yaxud lambda ilə
Çevirici<String, Integer> çevirici = Integer::parseInt;
System.out.println(çevirici.çevir("42")); // 42

// Daha mürəkkəb nümunə
Çevirici<List<String>, String> birləşdirici =
    siyahı -> String.join(", ", siyahı);

System.out.println(birləşdirici.çevir(List.of("alma", "armud", "gilas")));
// alma, armud, gilas
```

### Comparable-a bənzər generic interface

```java
// Java-nın öz Comparable-ı belədir
public interface Müqayisəli<T> {
    int müqayisəEt(T digər);
}

public class Tələbə implements Müqayisəli<Tələbə> {
    private String ad;
    private double orta;

    public Tələbə(String ad, double orta) {
        this.ad = ad;
        this.orta = orta;
    }

    @Override
    public int müqayisəEt(Tələbə digər) {
        // Ortaya görə müqayisə
        return Double.compare(this.orta, digər.orta);
    }
}
```

---

## Generic Method

Generic method — öz tip parametrləri olan metod. Class generic olmasa belə, metodun özü generic ola bilər.

```java
public class YardımçıSinfim {

    // Generic metod — <T> dönüş tipindən əvvəl yazılır
    public static <T> void çap(T dəyər) {
        System.out.println("Dəyər: " + dəyər + " | Tip: " + dəyər.getClass().getSimpleName());
    }

    // Massivi siyahıya çevir
    public static <T> List<T> massivdənSiyahı(T[] massiv) {
        return new ArrayList<>(Arrays.asList(massiv));
    }

    // İki elementi dəyiş
    public static <T> void dəyiş(T[] massiv, int i, int j) {
        T müvəqqəti = massiv[i];
        massiv[i] = massiv[j];
        massiv[j] = müvəqqəti;
    }

    // Maksimum elementi tap (Comparable tələb edir)
    public static <T extends Comparable<T>> T maks(T a, T b) {
        return a.compareTo(b) >= 0 ? a : b;
    }
}

// İstifadə
YardımçıSinfim.çap("Salam");       // Dəyər: Salam | Tip: String
YardımçıSinfim.çap(42);            // Dəyər: 42 | Tip: Integer
YardımçıSinfim.çap(3.14);          // Dəyər: 3.14 | Tip: Double

System.out.println(YardımçıSinfim.maks("alma", "armud")); // armud
System.out.println(YardımçıSinfim.maks(10, 20));          // 20
```

### Tip nəticəsi (Type Inference)

```java
// Java compiler tipi avtomatik müəyyən edir
// Açıq şəkildə tip vermək lazım deyil:
List<String> siyahı = YardımçıSinfim.massivdənSiyahı(new String[]{"a", "b"});

// Amma lazım olsa, açıq tip vermək olar:
List<String> siyahı2 = YardımçıSinfim.<String>massivdənSiyahı(new String[]{"a", "b"});
```

---

## Tip Parametr Konvensiyaları

Java cəmiyyətinin qəbul etdiyi adlandırma konvensiyaları:

| Hərf | İngilis adı | Azərbaycanca | Nümunə |
|------|------------|--------------|--------|
| `T`  | Type       | Tip          | `Box<T>`, `List<T>` |
| `E`  | Element    | Element      | `Stack<E>`, `Set<E>` |
| `K`  | Key        | Açar         | `Map<K, V>` |
| `V`  | Value      | Dəyər        | `Map<K, V>` |
| `N`  | Number     | Rəqəm        | `Calculator<N extends Number>` |
| `R`  | Return     | Qaytarma     | `Function<T, R>` |
| `S, U, V` | 2nd, 3rd, 4th types | əlavə tiplər | `BiFunction<T, U, R>` |

```java
// Konvensiyalara uyğun nümunələr
public class Xəritə<K, V> { /* ... */ }
public class Siyahı<E> { /* ... */ }
public interface Funksiya<T, R> { R tətbiqEt(T t); }
public class Cüt<T, U> { /* ... */ }

// Çoxlu tip parametr
public class Üçlük<T, U, V> {
    private T birinci;
    private U ikinci;
    private V üçüncü;
    // ...
}
```

---

## Raw Types

Raw type — tip parametri verilməmiş generic tip. **Köhnə kod ilə uyğunluq üçün mövcuddur, yeni kodda İSTİFADƏ ETMƏYİN.**

### YANLIŞ — Raw Type istifadəsi

```java
// YANLIŞ: Raw type — tip yoxlaması yoxdur
List siyahı = new ArrayList(); // Raw type!
siyahı.add("Salam");
siyahı.add(42);      // Fərqli tip əlavə edə bilirik — TƏHLÜKƏLI!
siyahı.add(3.14);    // Bu da əlavə olunur!

// Runtime-da ClassCastException baş verə bilər
String s = (String) siyahı.get(1); // 42-ni String kimi almağa çalışırıq — XƏTA!
```

```java
// YANLIŞ: Raw type parametri ötürmək
public static double cəm(List siyahı) { // Raw type parametr
    double nəticə = 0;
    for (Object o : siyahı) {
        nəticə += (Double) o; // Unsafe cast — runtime xəta riski
    }
    return nəticə;
}
```

### DOĞRU — Parametrli tip istifadəsi

```java
// DOĞRU: Tip parametri verilmiş
List<String> siyahı = new ArrayList<>();
siyahı.add("Salam");
// siyahı.add(42); // Compile time xəta — təhlükəsiz!

String s = siyahı.get(0); // Cast lazım deyil
```

```java
// DOĞRU: Generic metod
public static double cəm(List<Double> siyahı) {
    double nəticə = 0;
    for (Double d : siyahı) {
        nəticə += d; // Təhlükəsiz — tip yoxlanılıb
    }
    return nəticə;
}

// Hətta daha yaxşı — wildcard ilə
public static double cəmWildcard(List<? extends Number> siyahı) {
    double nəticə = 0;
    for (Number n : siyahı) {
        nəticə += n.doubleValue();
    }
    return nəticə;
}
```

### Raw Type niyə təhlükəlidir?

```java
// Bu nümunədə problem necə yaranır:
List<String> sözlər = new ArrayList<>();
sözlər.add("Bir");
sözlər.add("İki");

List raw = sözlər;  // Raw type-a mənimsədirik — compiler xəbərdarlıq edir
raw.add(42);         // Integer əlavə edirik — compile olur!

// İndi sözlər siyahısında integer var!
for (String söz : sözlər) {  // Runtime-da ClassCastException!
    System.out.println(söz.toUpperCase());
}
```

---

## Generic Method vs Generic Class

Nə vaxt generic method, nə vaxt generic class seçməli?

```java
// ========================================
// GENERIC CLASS — state saxlandıqda
// ========================================
public class Anbar<T> {
    private List<T> əşyalar = new ArrayList<>();

    public void əlavəEt(T əşya) { əşyalar.add(əşya); }
    public T götür(int indeks) { return əşyalar.get(indeks); }
    public int say() { return əşyalar.size(); }
    // Class öz state-ini saxlayır
}

// ========================================
// GENERIC METHOD — state lazım olmadıqda
// ========================================
public class Alqoritmlər {

    // Bu metod class-ı generic etmir, yalnız özü generic-dir
    public static <T extends Comparable<T>> T ən böyük(List<T> siyahı) {
        if (siyahı.isEmpty()) throw new NoSuchElementException();
        T maks = siyahı.get(0);
        for (T element : siyahı) {
            if (element.compareTo(maks) > 0) {
                maks = element;
            }
        }
        return maks;
    }

    public static <T> void qarışdır(List<T> siyahı) {
        Collections.shuffle(siyahı);
    }

    public static <T> List<T> birləşdir(List<T> birinci, List<T> ikinci) {
        List<T> nəticə = new ArrayList<>(birinci);
        nəticə.addAll(ikinci);
        return nəticə;
    }
}

// İstifadə
List<Integer> rəqəmlər = List.of(3, 1, 4, 1, 5, 9, 2, 6);
System.out.println(Alqoritmlər.ən böyük(rəqəmlər)); // 9

List<String> sözlər1 = new ArrayList<>(List.of("alma", "armud"));
List<String> sözlər2 = new ArrayList<>(List.of("gilas", "üzüm"));
List<String> hamısı = Alqoritmlər.birləşdir(sözlər1, sözlər2);
```

### Praktiki fərq

```java
// Generic class — obyektin tipi yaradılarkən müəyyən olunur
Anbar<String> anbar = new Anbar<>(); // Tip bir dəfə müəyyən olunur

// Generic method — hər çağırışda tip müəyyən olunur
Alqoritmlər.ən böyük(List.of(1, 2, 3));   // Integer üçün
Alqoritmlər.ən böyük(List.of("a", "b"));  // String üçün
// Eyni metod, fərqli tiplər
```

---

## Generics ilə Massivlər

Generics ilə massivlər arasında mühüm fərq var:

```java
// YANLIŞ — generic massiv yaratmaq olmaz
// T[] massiv = new T[10]; // Compile xəta!

// DOĞRU — alternativ üsullar
@SuppressWarnings("unchecked")
T[] massiv = (T[]) new Object[10]; // Cast ilə (heap pollution riski var)

// Daha yaxşı — List istifadə et
List<T> siyahı = new ArrayList<>();

// Konkret tip üçün massiv
public class TipliAnbar<T> {
    private Object[] elementlər; // Object[] istifadə edirik

    public TipliAnbar(int ölçü) {
        elementlər = new Object[ölçü]; // Object[] yaradılır
    }

    @SuppressWarnings("unchecked")
    public T götür(int indeks) {
        return (T) elementlər[indeks]; // Cast edilir
    }

    public void qoy(int indeks, T dəyər) {
        elementlər[indeks] = dəyər;
    }
}
```

---

## İntervyu Sualları

**S: Generics nə üçün istifadə edilir?**
C: Üç əsas məqsəd: 1) **Tip təhlükəsizliyi** — compile time-da tip xətaları aşkar edilir, 2) **Cast ehtiyacı yoxdur** — explicit casting lazım olmur, 3) **Kod təkrarı azalır** — eyni məntiqi fərqli tiplər üçün bir dəfə yazırıq.

**S: Raw type nədir və niyə istifadə etməməliyik?**
C: Raw type — tip parametri verilməmiş generic tipdir (məs. `List` əvəzinə `List<String>`). Köhnə kod ilə uyğunluq üçün saxlanılıb. İstifadə etməmək lazımdır çünki: compile time tip yoxlaması aradan qalxır, runtime-da `ClassCastException` baş verə bilər, IDE xəbərdarlıqları siqnala çevrilir.

**S: Generic class ilə generic method arasındakı fərq nədir?**
C: Generic class-da tip parametri class səviyyəsindədir — obyekt yaradılarkən müəyyən olunur. Generic method-da isə tip parametri metod səviyyəsindədir — hər çağırışda müəyyən olunur. Class generic olmasa da metodun özü generic ola bilər.

**S: `<T>` ilə `<E>` arasında fərq varmı?**
C: Texniki baxımdan heç bir fərq yoxdur — ikisi də eyni işi görür. Fərq yalnız konvensiya üzrədir: `T` — ümumi tip, `E` — kolleksiya elementi, `K`/`V` — Map açar/dəyəri, `N` — rəqəm tipi, `R` — qaytarma tipi.

**S: Java-da generic massiv yaratmaq mümkündürmü?**
C: Birbaşa `new T[10]` yazmaq olmaz — compile xəta verir. Bunun səbəbi type erasure-dur (tip silinməsi). Alternativlər: `(T[]) new Object[n]` — unsafe cast ilə, yaxud `List<T>` istifadə etmək tövsiyə olunur.

**S: Tip nəticəsi (type inference) nədir?**
C: Compiler tip parametrini avtomatik müəyyən etməsi. Java 7-dən `<>` (diamond operator) ilə `new Box<String>()` əvəzinə `new Box<>()` yaza bilirik. Java 8-dən isə method type inference da güclənib — lambda-larda da işləyir.
