# 54 — Generics — Wildcard-lar

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Wildcard nədir?](#wildcard-nədir)
2. [Unbounded Wildcard — `<?>`](#unbounded-wildcard)
3. [Upper Bounded Wildcard — `<? extends T>`](#upper-bounded-wildcard)
4. [Lower Bounded Wildcard — `<? super T>`](#lower-bounded-wildcard)
5. [PECS Prinsipi](#pecs-prinsipi)
6. [Real Nümunələr](#real-nümunələr)
7. [Wildcard vs Tip Parametri](#wildcard-vs-tip-parametri)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Wildcard nədir?

Wildcard (`?`) — generic tiplərdə naməlum tipi bildirir. Generics-in çevikliyini artırmaq üçün istifadə edilir.

### Problem — niyə wildcard lazımdır?

```java
// Generics invariant-dır (dəyişməzdir)!
// String → Object olsa da, List<String> → List<Object> DEYİL!

List<String> sözlər = new ArrayList<>();
// List<Object> obyektlər = sözlər; // COMPILE XƏTA!

// Niyə? Əgər icazə verilsəydı:
// List<Object> obyektlər = sözlər; // fərz edək ki, icazəlidir
// obyektlər.add(42); // Integer əlavə edə bilərdik!
// String s = sözlər.get(0); // Amma ClassCastException!
```

```java
// Bu metodun problemi var:
public static double hamısınıCəmlə(List<Number> siyahı) {
    double cəm = 0;
    for (Number n : siyahı) {
        cəm += n.doubleValue();
    }
    return cəm;
}

List<Integer> tam = List.of(1, 2, 3);
// hamısınıCəmlə(tam); // COMPILE XƏTA! List<Integer> ≠ List<Number>
```

Wildcard bu problemi həll edir.

---

## Unbounded Wildcard

`<?>` — istənilən tipi qəbul edir. `List<?>` — hər cür siyahını qəbul edən parametr.

```java
// Hər cür siyahının ölçüsünü çap edən metod
public static void ölçüCəhd(List<?> siyahı) {
    System.out.println("Ölçü: " + siyahı.size());

    // Oxuma mümkündür — amma yalnız Object kimi
    for (Object element : siyahı) {
        System.out.println(element);
    }

    // Yazmaq mümkün DEYİL (null istisna olmaqla)
    // siyahı.add("yeni"); // COMPILE XƏTA
    // siyahı.add(42);     // COMPILE XƏTA
    siyahı.add(null);      // Bunu edə bilərik, amma faydasız
}

// İstifadə — hər cür siyahı ilə işləyir
ölçüCəhd(List.of("a", "b", "c"));    // String siyahısı
ölçüCəhd(List.of(1, 2, 3));           // Integer siyahısı
ölçüCəhd(List.of(3.14, 2.71));        // Double siyahısı
ölçüCəhd(new ArrayList<>());           // Boş siyahı
```

### Unbounded Wildcard nə vaxt istifadə edilir?

```java
// 1. Metod yalnız Object metodlarına ehtiyac duyduqda
public static void hamısınıCəhd(List<?> siyahı) {
    for (Object o : siyahı) {
        System.out.println(o.toString()); // Object metodları işləyir
    }
}

// 2. Tip parametrindən asılı olmayan əməliyyatlar
public static int sayı(List<?> siyahı) {
    return siyahı.size();
}

public static boolean boşdur(List<?> siyahı) {
    return siyahı.isEmpty();
}

public static void təmizlə(List<?> siyahı) {
    siyahı.clear(); // Bu işləyir — tipi bilmək lazım deyil
}
```

### `List<Object>` vs `List<?>`

```java
// List<Object> — yalnız Object siyahısını qəbul edir
public static void hər şeyiCəhd(List<Object> siyahı) {
    siyahı.add("Yeni element"); // Bunu edə bilərik
}

List<String> sözlər = new ArrayList<>();
// hər şeyiCəhd(sözlər); // COMPILE XƏTA!

// List<?> — hər cür siyahını qəbul edir
public static void hər şeyiGöstər(List<?> siyahı) {
    // siyahı.add("Yeni"); // COMPILE XƏTA — yazmaq olmaz
    for (Object o : siyahı) System.out.println(o); // Oxumaq olar
}

hər şeyiGöstər(sözlər); // İşləyir!
```

---

## Upper Bounded Wildcard

`<? extends T>` — T tipini və ya T-nin alt siniflərini qəbul edir.

```java
// Number və ya onun alt sinifləri: Integer, Double, Long, Float...
public static double cəmlə(List<? extends Number> siyahı) {
    double nəticə = 0;
    for (Number n : siyahı) {      // Number kimi oxunur
        nəticə += n.doubleValue();
    }
    return nəticə;
    // siyahı.add(new Integer(1)); // COMPILE XƏTA — yazmaq olmaz!
}

// İndi hər sayı siyahısı ilə işləyir:
System.out.println(cəmlə(List.of(1, 2, 3)));          // Integer — 6.0
System.out.println(cəmlə(List.of(1.5, 2.5, 3.0)));    // Double — 7.0
System.out.println(cəmlə(List.of(100L, 200L)));         // Long — 300.0
```

### Upper Bounded ilə oxuma

```java
// Producer kimi işləyir — elementlər istehsal edir (oxunur)
public static void üstBoundedGöstər(List<? extends Number> siyahı) {
    for (Number n : siyahı) {
        // Number tipinin metodlarını çağıra bilərik
        System.out.printf("int: %d, double: %.2f%n",
            n.intValue(), n.doubleValue());
    }
}

// Niyə yazmaq olmur?
// Kompilyator bilmir ki, siyahı List<Integer>-dirmi, List<Double>-mı?
// Əgər List<Integer>-dirsə — Double əlavə etmək olmaz
// Əgər List<Double>-dırsa — Integer əlavə etmək olmaz
// Buna görə heç nə əlavə etmək olmur (null istisna olmaqla)
```

### Upper Bounded — real nümunə

```java
import java.util.List;

// Shape iyerarxiyası
abstract class Forma {
    abstract double sahə();
}

class Dairə extends Forma {
    double radius;
    Dairə(double radius) { this.radius = radius; }

    @Override
    double sahə() { return Math.PI * radius * radius; }
}

class Düzbucaqlı extends Forma {
    double en, hündürlük;
    Düzbucaqlı(double en, double hündürlük) {
        this.en = en;
        this.hündürlük = hündürlük;
    }

    @Override
    double sahə() { return en * hündürlük; }
}

// Forma və ya onun alt sinifləri ilə işləyir
public static double ümumSahə(List<? extends Forma> formalar) {
    double cəm = 0;
    for (Forma f : formalar) {
        cəm += f.sahə(); // Forma metodunu çağırırıq
    }
    return cəm;
}

// İstifadə
List<Dairə> dairələr = List.of(new Dairə(5), new Dairə(3));
List<Düzbucaqlı> düzbucaqlılar = List.of(new Düzbucaqlı(4, 6));

System.out.println(ümumSahə(dairələr));      // Dairə siyahısı
System.out.println(ümumSahə(düzbucaqlılar)); // Düzbucaqlı siyahısı
```

---

## Lower Bounded Wildcard

`<? super T>` — T tipini və ya T-nin üst siniflərini qəbul edir.

```java
// Integer, Number, Object — hamısını qəbul edir
public static void rəqəmləriƏlavəEt(List<? super Integer> siyahı) {
    // Integer əlavə edə bilirik
    for (int i = 1; i <= 5; i++) {
        siyahı.add(i); // Integer əlavə etmək mümkündür
    }

    // Oxumaq mümkün deyil (yaxşı şəkildə)
    // Integer n = siyahı.get(0); // COMPILE XƏTA
    Object o = siyahı.get(0);   // Yalnız Object kimi oxunur
}

List<Integer> tamlar = new ArrayList<>();
rəqəmləriƏlavəEt(tamlar); // İşləyir

List<Number> rəqəmlər = new ArrayList<>();
rəqəmləriƏlavəEt(rəqəmlər); // İşləyir — Number, Integer-in üst sinfidir

List<Object> obyektlər = new ArrayList<>();
rəqəmləriƏlavəEt(obyektlər); // İşləyir — Object hamının üst sinfidir
```

### Lower Bounded — Consumer kimi

```java
// TreeSet-ə elementlər köçürmək
public static <T> void köçür(List<? extends T> mənbə,
                               List<? super T> hədəf) {
    for (T element : mənbə) {
        hədəf.add(element);
    }
}

// İstifadə
List<Integer> tamlar = List.of(1, 2, 3, 4, 5);
List<Number> rəqəmlər = new ArrayList<>();
List<Object> obyektlər = new ArrayList<>();

köçür(tamlar, rəqəmlər);  // List<Integer> → List<Number>
köçür(tamlar, obyektlər); // List<Integer> → List<Object>
```

---

## PECS Prinsipi

**PECS = Producer Extends, Consumer Super**

Joshua Bloch-un "Effective Java" kitabından gələn prinsip:
- Əgər parametr **istehsalçıdırsa** (oxuyursunuz) — **`extends`** istifadə edin
- Əgər parametr **istehlakçıdırsa** (yazırsınız) — **`super`** istifadə edin

```java
// PECS nümunəsi — Collections.copy() metoduna baxaq:
// public static <T> void copy(List<? super T> dest, List<? extends T> src)
//                                    ^CONSUMER^         ^PRODUCER^
// src — məlumatı istehsal edir (oxunur) → extends
// dest — məlumatı istehlak edir (yazılır) → super

// Öz nümunəmiz:
public static <T> void köçürPECS(
        List<? extends T> istehsalçı,  // Producer — buradan oxuyuruq
        List<? super T> istehlakçı) {  // Consumer — bura yazırıq
    for (T element : istehsalçı) {
        istehlakçı.add(element);
    }
}

// Praktiki nümunə — ştamplama operasiyası
public static <T> void doldur(
        List<? super T> siyahı,        // Consumer — bura T yazırıq
        T element,
        int sayı) {
    for (int i = 0; i < sayı; i++) {
        siyahı.add(element);
    }
}

List<Object> obyektlər = new ArrayList<>();
doldur(obyektlər, "Salam", 3); // ["Salam", "Salam", "Salam"]
```

### PECS — vizual şərh

```
Tip iyerarxiyası:
        Object
          |
        Number
       /      \
  Integer    Double

List<? extends Number> — Producer:
  ✓ List<Number>   ← qəbul edir
  ✓ List<Integer>  ← qəbul edir
  ✓ List<Double>   ← qəbul edir
  → Oxuma: Number  ← yuxarı (Number kimi oxunur)
  → Yazma: ❌ olmaz (tipi bilmirik)

List<? super Integer> — Consumer:
  ✓ List<Integer>  ← qəbul edir
  ✓ List<Number>   ← qəbul edir
  ✓ List<Object>   ← qəbul edir
  → Oxuma: Object  ← yuxarı (Object kimi oxunur)
  → Yazma: Integer ✓ (Integer əlavə edə bilərik)
```

---

## Real Nümunələr

### Java Collections API-dən nümunələr

```java
import java.util.*;

// Collections.sort() — Comparable tələb edir
// public static <T extends Comparable<? super T>> void sort(List<T> list)
// ? super T — T-nin üst sinifləri də müqayisəli ola bilər

// Collections.max() — Producer
// public static <T extends Object & Comparable<? super T>> T max(Collection<? extends T> coll)

// Collections.copy()
// public static <T> void copy(List<? super T> dest, List<? extends T> src)

// Nümunə:
List<Integer> mənbə = new ArrayList<>(List.of(3, 1, 4, 1, 5, 9));
List<Number> hədəf = new ArrayList<>(Collections.nCopies(6, 0));

Collections.copy(hədəf, mənbə); // List<Integer> → List<Number>
System.out.println(hədəf); // [3, 1, 4, 1, 5, 9]
```

### Öz utility metodlarımız

```java
public class SiyahıUtils {

    // Bütün elementlərin cəmi — PECS: extends (producer)
    public static double cəmlə(List<? extends Number> siyahı) {
        return siyahı.stream()
                     .mapToDouble(Number::doubleValue)
                     .sum();
    }

    // Elementləri filtrə et və yenisini doldur — PECS: super (consumer)
    public static <T> void filtrlə(
            List<? extends T> mənbə,
            List<? super T> hədəf,
            java.util.function.Predicate<? super T> şərt) {
        for (T element : mənbə) {
            if (şərt.test(element)) {
                hədəf.add(element);
            }
        }
    }

    // Ən böyük elementi tap — extends (producer)
    public static <T extends Comparable<? super T>> T ən böyük(
            List<? extends T> siyahı) {
        if (siyahı.isEmpty()) throw new NoSuchElementException("Siyahı boşdur");
        T maks = siyahı.get(0);
        for (T element : siyahı) {
            if (element.compareTo(maks) > 0) {
                maks = element;
            }
        }
        return maks;
    }
}

// İstifadə
List<Integer> tamlar = List.of(3, 7, 1, 9, 2);
System.out.println(SiyahıUtils.cəmlə(tamlar));       // 22.0
System.out.println(SiyahıUtils.ən böyük(tamlar));    // 9

List<Double> onluqlar = List.of(1.5, 3.7, 2.2);
System.out.println(SiyahıUtils.cəmlə(onluqlar));     // 7.4

// Filtrləmə
List<Integer> cüt = new ArrayList<>();
SiyahıUtils.filtrlə(tamlar, cüt, n -> n % 2 == 0);
System.out.println(cüt); // [2]
```

### Wildcard ilə generik siyahı köçürücü

```java
// Mürəkkəb amma güclü nümunə
public class Köçürücü {

    // Çoxlu mənbədən bir hədəfə köçür
    @SafeVarargs
    public static <T> void hamısınıKöçür(
            List<? super T> hədəf,
            List<? extends T>... mənbələr) {
        for (List<? extends T> mənbə : mənbələr) {
            hədəf.addAll(mənbə);
        }
    }
}

// İstifadə
List<Object> hər şey = new ArrayList<>();
List<String> sözlər = List.of("bir", "iki", "üç");
List<Integer> rəqəmlər = List.of(1, 2, 3);

// Hər ikisini Object siyahısına köçür
Köçürücü.hamısınıKöçür(hər şey, sözlər, rəqəmlər);
System.out.println(hər şey); // [bir, iki, üç, 1, 2, 3]
```

---

## Wildcard vs Tip Parametri

Nə vaxt wildcard, nə vaxt tip parametri seçməli?

```java
// YANLIŞ istifadə — wildcard lazım deyil
public static <T> void çap1(List<T> siyahı) {
    for (T element : siyahı) {
        System.out.println(element);
    }
}

// DOĞRU — wildcard daha sadə
public static void çap2(List<?> siyahı) {
    for (Object element : siyahı) {
        System.out.println(element);
    }
}
```

```java
// Tip parametri lazım olan hal — iki siyahı eyni tipli olmalıdır
// YANLIŞ — bunu wildcard ilə ifadə etmək çətindir
public static boolean birindəVarmı(List<?> siyahı, List<?> hədəf) {
    // Bunlar fərqli tiplər ola bilər — problem!
    return siyahı.stream().anyMatch(hədəf::contains);
}

// DOĞRU — tip parametri əlaqəni ifadə edir
public static <T> boolean birindəVarmı(List<T> siyahı, List<T> hədəf) {
    return siyahı.stream().anyMatch(hədəf::contains);
}
// İndi hər iki siyahı eyni tipli olmalıdır
```

```java
// Tip parametri ilə wildcard birlikdə
public static <T extends Comparable<? super T>> void sırala(List<T> siyahı) {
    Collections.sort(siyahı);
    // T — List-i dəyişdiririk, ona görə wildcard olmur
    // ? super T — Comparable üçün alt siniflərin üst siniflərinin müqayisəsinə icazə veririk
}
```

---

## İntervyu Sualları

**S: `<?>`, `<? extends T>` və `<? super T>` arasındakı fərqi izah edin.**
C: `<?>` — unbounded, istənilən tipi qəbul edir, yalnız oxumaq mümkündür (Object kimi); `<? extends T>` — upper bounded, T və onun alt sinifləri, yalnız oxumaq mümkündür (T kimi); `<? super T>` — lower bounded, T və onun üst sinifləri, T əlavə etmək mümkündür, yalnız Object kimi oxunur.

**S: PECS nədir?**
C: Producer Extends, Consumer Super. Parametr məlumat istehsal edirsə (oxunursa) `extends`, istehlak edirsə (yazılırsa) `super` istifadə edilir. `Collections.copy(dest, src)` — dest consumer (`? super T`), src producer (`? extends T`).

**S: `List<String>`-i `List<Object>`-ə niyə mənimsədə bilmirik?**
C: Generics invariantdır — `String extends Object` olsa da, `List<String>` `List<Object>`-in alt tipi deyil. Əgər icazə verilsəydi, `List<Object>`-ə Integer əlavə edib `List<String>`-dən String kimi oxuya bilərdik — ClassCastException baş verərdi.

**S: Wildcard capture nədir?**
C: Wildcard tipli parametrlə işləyərkən compiler tipi "tutur" — adını bilmədən işləyə bilir. Bəzən helper metod lazım olur: `public static <T> void helper(List<T> list)` metodunu `List<?>` ilə çağıranda compiler T-ni wildcard tipinə bağlayır.

**S: `List<?>` ilə `List<Object>` fərqi nədir?**
C: `List<?>` — hər cür siyahını qəbul edir amma yazmaq olmaz (null istisna); `List<Object>` — yalnız `List<Object>` qəbul edir amma istənilən Object əlavə etmək olar.
