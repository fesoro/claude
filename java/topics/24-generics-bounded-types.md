# 24. Generics — Bounded Types (Məhdud Tiplər)

## Mündəricat
1. [Bounded Types nədir?](#bounded-types-nədir)
2. [Single Bound — Tək Məhdudiyyət](#single-bound)
3. [Multiple Bounds — Çoxlu Məhdudiyyət](#multiple-bounds)
4. [Recursive Bounds — Özünə Göndərən Məhdudiyyət](#recursive-bounds)
5. [Bounded Wildcards in Method Signatures](#bounded-wildcards-method)
6. [Praktiki Nümunələr](#praktiki-nümunələr)
7. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Bounded Types nədir?

Bounded type — tip parametrinin hansı tiplərə məhdudlaşdırılacağını bildirir. `extends` açar sözü ilə verilir (həm class, həm interface üçün).

```java
// Məhdudsuz — istənilən tip
public class Qab<T> { }
Qab<String> s = new Qab<>();
Qab<Integer> i = new Qab<>();
Qab<Object> o = new Qab<>();

// Məhdud — yalnız Number və onun alt sinifləri
public class RəqəmQabı<T extends Number> { }
RəqəmQabı<Integer> tam = new RəqəmQabı<>();   // OK
RəqəmQabı<Double> onluq = new RəqəmQabı<>();   // OK
// RəqəmQabı<String> söz = new RəqəmQabı<>();  // COMPILE XƏTA
```

---

## Single Bound

`<T extends TipAdı>` — T yalnız TipAdı və ya onun alt sinfi ola bilər.

### Class ilə məhdudiyyət

```java
// Yalnız Number alt sinifləri qəbul olunur
public class Hesablayıcı<T extends Number> {
    private T dəyər;

    public Hesablayıcı(T dəyər) {
        this.dəyər = dəyər;
    }

    // T extends Number olduğu üçün Number metodlarına daxil ola bilirik
    public double onluqDəyər() {
        return dəyər.doubleValue(); // Number metodunu çağıra bilərik
    }

    public int tamDəyər() {
        return dəyər.intValue(); // Bu da Number metodudur
    }

    public boolean sıfırdanBöyükdür() {
        return dəyər.doubleValue() > 0;
    }
}

// İstifadə
Hesablayıcı<Integer> tamHesab = new Hesablayıcı<>(42);
System.out.println(tamHesab.onluqDəyər()); // 42.0

Hesablayıcı<Double> onluqHesab = new Hesablayıcı<>(3.14);
System.out.println(onluqHesab.tamDəyər()); // 3
```

### Interface ilə məhdudiyyət

```java
// Yalnız Comparable tiplər
public static <T extends Comparable<T>> T ən böyük(T a, T b, T c) {
    T maks = a;
    if (b.compareTo(maks) > 0) maks = b; // compareTo çağıra bilərik
    if (c.compareTo(maks) > 0) maks = c;
    return maks;
}

// İstifadə
System.out.println(ən böyük(3, 1, 2));          // 3
System.out.println(ən böyük("alma", "armud", "gilas")); // gilas
System.out.println(ən böyük(1.5, 2.7, 1.9));    // 2.7
```

### Məhdud tip ilə generic class

```java
import java.util.*;

// Yalnız Comparable elementlər saxlaya bilən sıralı anbar
public class SıralıAnbar<T extends Comparable<T>> {
    private final List<T> elementlər = new ArrayList<>();

    public void əlavəEt(T element) {
        elementlər.add(element);
        Collections.sort(elementlər); // Comparable olduğu üçün sort işləyir
    }

    public T birinci() {
        if (elementlər.isEmpty()) throw new NoSuchElementException();
        return elementlər.get(0); // Ən kiçik
    }

    public T sonuncu() {
        if (elementlər.isEmpty()) throw new NoSuchElementException();
        return elementlər.get(elementlər.size() - 1); // Ən böyük
    }

    public boolean ehtivaMi(T element) {
        return Collections.binarySearch(elementlər, element) >= 0;
    }

    public List<T> hamısı() {
        return Collections.unmodifiableList(elementlər);
    }
}

// İstifadə
SıralıAnbar<Integer> rəqəmlər = new SıralıAnbar<>();
rəqəmlər.əlavəEt(5);
rəqəmlər.əlavəEt(1);
rəqəmlər.əlavəEt(3);
System.out.println(rəqəmlər.hamısı()); // [1, 3, 5]
System.out.println(rəqəmlər.birinci()); // 1
System.out.println(rəqəmlər.sonuncu()); // 5
```

---

## Multiple Bounds

`<T extends A & B & C>` — T birdən çox tipi qarşılamalıdır.

**Qaydalar:**
- Yalnız bir class ola bilər (ilk olmalıdır)
- Sonsuz sayda interface ola bilər
- `&` ilə ayrılır (`,` deyil)

```java
// Interface-lər
interface Serializable { }
interface Cloneable { }
interface Printable {
    void çap();
}

// Class
abstract class Heyvan {
    abstract String ad();
}

// DOĞRU — bir class, sonra interface-lər
// T həm Heyvan olmalı, həm Serializable, həm Cloneable
public class <T extends Heyvan & Serializable & Cloneable> HeyvanAnbarı {
    private T heyvan;

    public HeyvanAnbarı(T heyvan) { this.heyvan = heyvan; }

    public String adGötür() {
        return heyvan.ad(); // Heyvan metoduna daxil ola bilərik
    }
}

// YANLIŞ — iki class olmaz
// <T extends String & Integer> // COMPILE XƏTA
```

### Praktiki multiple bounds nümunəsi

```java
// Java-nın özündən nümunə: Collections.sort()
// public static <T extends Comparable<? super T>> void sort(List<T> list)

// Öz nümunəmiz — serializable və comparable olan obyektlər
import java.io.Serializable;

public class TəhlükəsizKaş<T extends Comparable<T> & Serializable> {
    private T dəyər;

    public TəhlükəsizKaş(T dəyər) {
        this.dəyər = dəyər;
    }

    public boolean daha böyükdür(T digər) {
        return dəyər.compareTo(digər) > 0; // Comparable metodu
    }

    public T dəyərAl() {
        // Serializable olduğu üçün serializasiya edə bilərik
        return dəyər;
    }
}

// String həm Comparable, həm Serializable:
TəhlükəsizKaş<String> kaş = new TəhlükəsizKaş<>("Salam");
System.out.println(kaş.daha böyükdür("Xeyir")); // false (Salam < Xeyir)
```

### Çoxlu bound ilə generic metod

```java
import java.io.Serializable;
import java.util.*;

// Həm müqayisəli, həm serializable olan elementlərləri sırala
public static <T extends Comparable<T> & Serializable>
        List<T> güvənliSırala(Collection<T> kolleksiya) {
    List<T> siyahı = new ArrayList<>(kolleksiya);
    Collections.sort(siyahı); // Comparable-dan gəlir
    // Serializasiya da edə bilərdik...
    return siyahı;
}

// String — Comparable & Serializable
List<String> sıralı = güvənliSırala(Set.of("c", "a", "b"));
System.out.println(sıralı); // [a, b, c]
```

---

## Recursive Bounds

`<T extends Comparable<T>>` — T özü ilə müqayisə edilə bilən tipi bildirir.

### Niyə lazımdır?

```java
// YANLIŞ yanaşma — T extends Comparable yazırıq
public static <T extends Comparable> T maks(T a, T b) {
    // Raw type xəbərdarlığı — Comparable<T> olmalıdır
    return a.compareTo(b) >= 0 ? a : b;
}

// DOĞRU — recursive bound
public static <T extends Comparable<T>> T maks(T a, T b) {
    return a.compareTo(b) >= 0 ? a : b; // Xəbərdarlıq yoxdur
}
```

### Recursive bound — Enum nümunəsi

```java
// Java-nın Enum class-ı belə təriflənib:
// public abstract class Enum<E extends Enum<E>>
// E — özü Enum olmalıdır!

// Öz nümunəmiz:
public abstract class AbstractEntity<T extends AbstractEntity<T>>
        implements Comparable<T> {

    private Long id;

    public Long getId() { return id; }

    @Override
    public int compareTo(T digər) {
        // İd üzrə müqayisə
        if (this.id == null && digər.getId() == null) return 0;
        if (this.id == null) return -1;
        if (digər.getId() == null) return 1;
        return Long.compare(this.id, digər.getId());
    }
}

// Alt sinif
public class İstifadəçi extends AbstractEntity<İstifadəçi> {
    private String ad;

    public İstifadəçi(Long id, String ad) {
        // ...
    }
}
```

### Builder pattern ilə recursive bound

```java
// Fluent Builder — metodlar öz tipini qaytarır
public abstract class Builder<T, B extends Builder<T, B>> {

    protected String ad;
    protected int yaş;

    // Bu metod B tipini qaytarır — alt sinifdə bu tipin metodları çağırılır
    @SuppressWarnings("unchecked")
    public B adQoy(String ad) {
        this.ad = ad;
        return (B) this; // Özünü qaytarır
    }

    @SuppressWarnings("unchecked")
    public B yaşQoy(int yaş) {
        this.yaş = yaş;
        return (B) this;
    }

    public abstract T qur();
}

// İstifadəçi Builder
public class İstifadəçiBuilder extends Builder<İstifadəçi, İstifadəçiBuilder> {
    private String email;

    public İstifadəçiBuilder emailQoy(String email) {
        this.email = email;
        return this; // İstifadəçiBuilder qaytarır
    }

    @Override
    public İstifadəçi qur() {
        return new İstifadəçi(ad, yaş, email);
    }
}

// İstifadə — fluent, metod chain pozulmur
İstifadəçi istifadəçi = new İstifadəçiBuilder()
        .adQoy("Əli")      // Builder metodları
        .yaşQoy(25)         // Builder metodları
        .emailQoy("ali@example.com") // İstifadəçiBuilder metodu
        .qur();
```

---

## Bounded Wildcards in Method Signatures

```java
import java.util.*;
import java.util.function.*;

public class BoundedWildcardNümunələr {

    // 1. Upper bounded — oxuma üçün
    public static double ortaHesabla(List<? extends Number> siyahı) {
        return siyahı.stream()
                     .mapToDouble(Number::doubleValue)
                     .average()
                     .orElse(0.0);
    }

    // 2. Lower bounded — yazma üçün
    public static void doldurc(List<? super Integer> hədəf, int başlangıc, int say) {
        for (int i = başlangıc; i < başlangıc + say; i++) {
            hədəf.add(i);
        }
    }

    // 3. PECS — hər ikisi birlikdə
    public static <T extends Comparable<T>> void sıralaVəKöçür(
            List<? extends T> mənbə,     // Producer — oxunur
            List<? super T> hədəf) {     // Consumer — yazılır
        List<T> müvəqqəti = new ArrayList<>(mənbə);
        Collections.sort(müvəqqəti);
        hədəf.addAll(müvəqqəti);
    }

    // 4. Mürəkkəb: Funksiya ilə bounded wildcard
    public static <T, R extends Comparable<R>> T ən böyüyünüTap(
            List<T> elementlər,
            Function<T, R> çevirici) {
        return elementlər.stream()
                         .max(Comparator.comparing(çevirici))
                         .orElseThrow();
    }
}

// İstifadə
List<Integer> tam = List.of(3, 1, 4, 1, 5);
System.out.println(BoundedWildcardNümunələr.ortaHesabla(tam)); // 2.8

List<Number> rəqəmlər = new ArrayList<>();
BoundedWildcardNümunələr.doldurc(rəqəmlər, 1, 5);
System.out.println(rəqəmlər); // [1, 2, 3, 4, 5]

// Ən uzun sözü tap
List<String> sözlər = List.of("alma", "armud", "gilas", "üzüm");
String ən uzun = BoundedWildcardNümunələr.ən böyüyünüTap(sözlər, String::length);
System.out.println(ən uzun); // gilas
```

---

## Praktiki Nümunələr

### Min-Max tapan generic class

```java
import java.util.*;

public class MinMax<T extends Comparable<T>> {
    private T minimum;
    private T maksimum;
    private int say;

    public void əlavəEt(T dəyər) {
        if (say == 0) {
            minimum = maksimum = dəyər;
        } else {
            if (dəyər.compareTo(minimum) < 0) minimum = dəyər;
            if (dəyər.compareTo(maksimum) > 0) maksimum = dəyər;
        }
        say++;
    }

    public Optional<T> min() { return say == 0 ? Optional.empty() : Optional.of(minimum); }
    public Optional<T> maks() { return say == 0 ? Optional.empty() : Optional.of(maksimum); }
    public int say() { return say; }

    public static <T extends Comparable<T>> MinMax<T> of(Collection<T> kolleksiya) {
        MinMax<T> mm = new MinMax<>();
        kolleksiya.forEach(mm::əlavəEt);
        return mm;
    }
}

// İstifadə
MinMax<Integer> mm = MinMax.of(List.of(5, 3, 8, 1, 9, 2));
System.out.println("Min: " + mm.min().orElseThrow()); // 1
System.out.println("Maks: " + mm.maks().orElseThrow()); // 9

MinMax<String> smm = MinMax.of(List.of("banana", "alma", "üzüm"));
System.out.println("Min söz: " + smm.min().orElseThrow()); // alma
```

### Generic intervallı tip

```java
// Müqayisəli elementlər üçün aralıq (Range)
public class Aralıq<T extends Comparable<T>> {
    private final T başlangıc;
    private final T son;

    private Aralıq(T başlangıc, T son) {
        if (başlangıc.compareTo(son) > 0) {
            throw new IllegalArgumentException(
                "Başlangıc son-dan böyük ola bilməz: " + başlangıc + " > " + son);
        }
        this.başlangıc = başlangıc;
        this.son = son;
    }

    public static <T extends Comparable<T>> Aralıq<T> of(T başlangıc, T son) {
        return new Aralıq<>(başlangıc, son);
    }

    // Dəyər aralıq içindədir?
    public boolean ehtivaMi(T dəyər) {
        return dəyər.compareTo(başlangıc) >= 0
            && dəyər.compareTo(son) <= 0;
    }

    // Bu aralıq başqa aralıqla kəsişir?
    public boolean kəsişirMi(Aralıq<T> digər) {
        return başlangıc.compareTo(digər.son) <= 0
            && son.compareTo(digər.başlangıc) >= 0;
    }

    // Kəsişmə nöqtəsi
    public Optional<Aralıq<T>> kəsişmə(Aralıq<T> digər) {
        if (!kəsişirMi(digər)) return Optional.empty();

        T yeniBaşlangıc = başlangıc.compareTo(digər.başlangıc) >= 0 ? başlangıc : digər.başlangıc;
        T yeniSon = son.compareTo(digər.son) <= 0 ? son : digər.son;

        return Optional.of(new Aralıq<>(yeniBaşlangıc, yeniSon));
    }

    @Override
    public String toString() {
        return "[" + başlangıc + ", " + son + "]";
    }
}

// İstifadə
Aralıq<Integer> a1 = Aralıq.of(1, 10);
Aralıq<Integer> a2 = Aralıq.of(5, 15);

System.out.println(a1.ehtivaMi(7));   // true
System.out.println(a1.ehtivaMi(11));  // false
System.out.println(a1.kəsişirMi(a2)); // true

a1.kəsişmə(a2).ifPresent(k -> System.out.println("Kəsişmə: " + k)); // [5, 10]

// String ilə də işləyir
Aralıq<String> adAralığı = Aralıq.of("A", "M");
System.out.println(adAralığı.ehtivaMi("Əli"));   // false (Ə > M)
System.out.println(adAralığı.ehtivaMi("Bəhruz")); // true
```

### Çoxlu bound — real dünya nümunəsi

```java
import java.io.*;
import java.util.*;

// Həm müqayisəli, həm serializable, həm klonlanabilən entity
public abstract class PersistentEntity<T extends PersistentEntity<T> & Serializable & Cloneable>
        implements Comparable<T>, Serializable, Cloneable {

    private static final long serialVersionUID = 1L;
    private Long id;
    private Date yaradılmaTarixi = new Date();

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Date getYaradılmaTarixi() { return yaradılmaTarixi; }

    @Override
    public int compareTo(T digər) {
        if (this.id == null) return -1;
        if (digər.getId() == null) return 1;
        return Long.compare(this.id, digər.getId());
    }

    // Deep clone üçün
    @SuppressWarnings("unchecked")
    public T kopyala() {
        try {
            return (T) super.clone();
        } catch (CloneNotSupportedException e) {
            throw new RuntimeException(e);
        }
    }
}
```

---

## İntervyu Sualları

**S: `<T extends A & B>` sintaksisindəki `extends` nəyi bildirir?**
C: Həm class, həm interface üçün `extends` açar söz istifadə edilir (interface üçün `implements` deyil). İlk mövqe class ola bilər, qalanları interface-dir. Məsələn: `<T extends Number & Comparable<T> & Serializable>`.

**S: `<T extends Comparable<T>>` — recursive bound nə deməkdir?**
C: T tipi özü ilə müqayisə edilə bilən olmalıdır. `Comparable<T>` — T-ni T ilə müqayisə edir. Bu, `compareTo(T other)` metodunun tip-təhlükəsiz işləməsini təmin edir. Java-nın `Enum<E extends Enum<E>>` da recursive bound-dur.

**S: Multiple bounds-da class-ın birinci olması niyə şərtdir?**
C: JVM bytecode-unda erasure zamanı ilk bound saxlanılır. Class varsa o saxlanılır, yoxsa Object. Birdən çox class miras almaq mümkün olmadığı üçün (multiple inheritance yoxdur) yalnız bir class qeyd edilə bilər.

**S: Bounded type wildcard (`<? extends T>`) ilə bounded type parameter (`<T extends Bound>`) arasındakı fərq nədir?**
C: Type parameter (`<T extends Bound>`) — T-yə ad veririk, metodda/class-da T-ni bir neçə yerdə istifadə edə bilərik. Wildcard (`<? extends T>`) — adsız tipdir, yalnız bir yerdə lazım olduqda istifadə edilir, parametrlər arasında əlaqə yaratmaq olmur.

**S: Builder pattern-də recursive bound niyə lazımdır?**
C: `B extends Builder<T, B>` — alt sinifdəki Builder metodları `B` tipini (alt sinfi) qaytarır, `Builder`-i deyil. Bu, metod chain-in alt sinif metodlarına davam edə bilməsini təmin edir. Olmadan hər alt sinif metodu `Builder`-ə dönərdi və alt sinifd xüsusi metodlar çağırılmazdı.
