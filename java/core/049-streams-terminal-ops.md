# 049 — Streams — Terminal Əməliyyatlar (Terminal Operations)
**Səviyyə:** Orta


## Mündəricat
1. [Terminal Operations haqqında](#terminal-operations)
2. [collect()](#collect)
3. [reduce()](#reduce)
4. [count(), sum(), min(), max()](#sayma-hesab)
5. [findFirst() və findAny()](#find-operations)
6. [anyMatch(), allMatch(), noneMatch()](#match-operations)
7. [forEach() və forEachOrdered()](#foreach)
8. [toArray()](#toarray)
9. [Short-Circuiting Operations](#short-circuiting)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Terminal Operations haqqında

Terminal əməliyyatlar — stream pipeline-ı işə salır, nəticə qaytarır, stream-i bağlayır.

```
Pipeline: mənbə → [filter] → [map] → TERMINAL
                  (lazy)    (lazy)   (işləyir!)
```

**Xüsusiyyətləri:**
- Stream-i tükədir — sonra yenidən istifadə olmur
- Bütün ara əməliyyatları işə salır
- Nəticə qaytarır: List, Optional, primitiv, void...
- Short-circuit ola bilər (hamısını emal etmir)

---

## collect()

`collect(Collector)` — stream elementlərini kolleksiyaya və ya başqa nəticəyə toplayır.

```java
import java.util.*;
import java.util.stream.*;

List<String> sözlər = List.of("alma", "armud", "gilas", "üzüm", "portağal");

// Siyahıya topla
List<String> siyahı = sözlər.stream()
    .filter(s -> s.length() > 4)
    .collect(Collectors.toList());
System.out.println(siyahı); // [armud, gilas, portağal]

// Java 16+ — toList() metodu (immutable)
List<String> siyahı2 = sözlər.stream()
    .filter(s -> s.length() > 4)
    .toList(); // Collectors.toList() əvəzinə

// Set-ə topla (dublikatlar silinir)
Set<String> çoxluq = sözlər.stream()
    .collect(Collectors.toSet());

// String birləşdirmə
String birləşmiş = sözlər.stream()
    .collect(Collectors.joining(", "));
System.out.println(birləşmiş); // alma, armud, gilas, üzüm, portağal

String formatlanmış = sözlər.stream()
    .collect(Collectors.joining(", ", "[", "]"));
System.out.println(formatlanmış); // [alma, armud, gilas, üzüm, portağal]
```

---

## reduce()

`reduce()` — stream elementlərini bir dəyərə birləşdirir (azaldır).

### reduce(identity, BinaryOperator)

```java
// Cəm hesablama
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5);

int cəm = rəqəmlər.stream()
    .reduce(0, Integer::sum); // 0 — başlanğıc dəyər
System.out.println(cəm); // 15

// Eyni əməliyyat manual:
// 0 + 1 = 1
// 1 + 2 = 3
// 3 + 3 = 6
// 6 + 4 = 10
// 10 + 5 = 15

// Hasılat
int hasılat = rəqəmlər.stream()
    .reduce(1, (a, b) -> a * b);
System.out.println(hasılat); // 120 (5!)

// Maximum
int maks = rəqəmlər.stream()
    .reduce(Integer.MIN_VALUE, Integer::max);
System.out.println(maks); // 5

// String birləşdirmə
List<String> sözlər = List.of("Java", " ", "Stream", " ", "API");
String nəticə = sözlər.stream()
    .reduce("", String::concat);
System.out.println(nəticə); // Java Stream API
```

### reduce(BinaryOperator) — Optional qaytarır

```java
// Başlanğıc dəyər olmadan — boş stream üçün Optional.empty()
Optional<Integer> cəm = Stream.<Integer>empty()
    .reduce(Integer::sum);
System.out.println(cəm.isPresent()); // false

Optional<Integer> maks = List.of(3, 1, 4, 1, 5, 9).stream()
    .reduce(Integer::max);
maks.ifPresent(m -> System.out.println("Maks: " + m)); // 9
```

### reduce() — xüsusi akkumulyasiya

```java
record Məhsul(String ad, double qiymət, int miqdar) {}

List<Məhsul> səbət = List.of(
    new Məhsul("Alma", 1.5, 3),
    new Məhsul("Armud", 2.0, 2),
    new Məhsul("Gilas", 3.5, 1)
);

// Ümumi məbləği hesabla
double cəm = səbət.stream()
    .mapToDouble(m -> m.qiymət() * m.miqdar())
    .reduce(0.0, Double::sum);

System.out.printf("Cəm: %.2f AZN%n", cəm); // 12.00 AZN
// 1.5*3=4.5, 2.0*2=4.0, 3.5*1=3.5, cəm=12.0
```

---

## Sayma və Hesab

### count()

```java
List<String> sözlər = List.of("alma", "armud", "gilas", "üzüm", "portağal");

// Ümumi say
long say = sözlər.stream().count();
System.out.println("Say: " + say); // 5

// Şərtə uyğunların sayı
long uzunSözlər = sözlər.stream()
    .filter(s -> s.length() > 4)
    .count();
System.out.println("Uzun sözlər: " + uzunSözlər); // 3
```

### sum() — primitiv stream-lərdə

```java
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5);

// IntStream.sum()
int cəm = rəqəmlər.stream()
    .mapToInt(Integer::intValue)
    .sum();
System.out.println(cəm); // 15

// Yaxud Collectors.summingInt
int cəm2 = rəqəmlər.stream()
    .collect(Collectors.summingInt(Integer::intValue));
```

### min() və max()

```java
List<Integer> rəqəmlər = List.of(3, 1, 4, 1, 5, 9, 2, 6);

// Stream.min/max — Comparator tələb edir, Optional qaytarır
Optional<Integer> min = rəqəmlər.stream()
    .min(Comparator.naturalOrder());
min.ifPresent(m -> System.out.println("Min: " + m)); // 1

Optional<Integer> maks = rəqəmlər.stream()
    .max(Comparator.naturalOrder());
maks.ifPresent(m -> System.out.println("Maks: " + m)); // 9

// IntStream.min/max — OptionalInt qaytarır
OptionalInt intMin = rəqəmlər.stream()
    .mapToInt(Integer::intValue)
    .min();
System.out.println("IntStream Min: " + intMin.getAsInt()); // 1

// Obyekt üzrə min/max
record İşçi(String ad, double maaş) {}

List<İşçi> işçilər = List.of(
    new İşçi("Əli", 2500),
    new İşçi("Aysel", 3200),
    new İşçi("Murad", 1800)
);

Optional<İşçi> ənYüksəkMaaşlı = işçilər.stream()
    .max(Comparator.comparingDouble(İşçi::maaş));
ənYüksəkMaaşlı.ifPresent(i ->
    System.out.println("Ən yüksək maaş: " + i.ad())); // Aysel
```

---

## find Operations

### findFirst()

```java
List<Integer> rəqəmlər = List.of(5, 3, 8, 1, 9, 2);

// Şərtə uyan ilk elementi tap
Optional<Integer> ilkCüt = rəqəmlər.stream()
    .filter(n -> n % 2 == 0)
    .findFirst();
ilkCüt.ifPresent(n -> System.out.println("İlk cüt: " + n)); // 8

// Boş olduğu hal
Optional<Integer> yoxdur = rəqəmlər.stream()
    .filter(n -> n > 100)
    .findFirst();
System.out.println(yoxdur.isPresent()); // false
```

### findAny()

```java
// findAny() — sıralamaya baxmır, hər hansı birini tapır
// Ardıcıl stream-lərdə adətən findFirst() kimi işləyir
// Paralel stream-lərdə daha effektivdir

Optional<Integer> hər hansı = rəqəmlər.stream()
    .filter(n -> n % 2 == 0)
    .findAny();
hər hansı.ifPresent(n -> System.out.println("Hər hansı cüt: " + n));

// Paralel — findAny() daha sürətli
Optional<Integer> paralel = rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .findAny(); // Sıra müəyyən deyil
```

### findFirst() vs findAny()

```java
// Ardıcıl stream — hər ikisi eyni davranış
List<String> sözlər = List.of("alma", "armud", "gilas");

Optional<String> first = sözlər.stream().findFirst(); // "alma"
Optional<String> any = sözlər.stream().findAny();     // Adətən "alma"

// Paralel stream — findAny() daha sürətli (sıra önəmli deyilsə)
Optional<String> parallelFirst = sözlər.parallelStream().findFirst(); // "alma" (sıra saxlanır)
Optional<String> parallelAny = sözlər.parallelStream().findAny();    // İstənilən biri
```

---

## match Operations

### anyMatch() — hər hansı biri uyğunsa true

```java
List<Integer> rəqəmlər = List.of(1, 3, 5, 7, 9);

// Cüt varmı?
boolean cütVarmı = rəqəmlər.stream()
    .anyMatch(n -> n % 2 == 0);
System.out.println("Cüt varmı: " + cütVarmı); // false

// 5-dən böyük varmı?
boolean böyükVarmı = rəqəmlər.stream()
    .anyMatch(n -> n > 5);
System.out.println("5-dən böyük varmı: " + böyükVarmı); // true (7, 9)
```

### allMatch() — hamısı uyğunsa true

```java
// Hamısı müsbətdir?
boolean hamısıMüsbət = rəqəmlər.stream()
    .allMatch(n -> n > 0);
System.out.println("Hamısı müsbət: " + hamısıMüsbət); // true

// Hamısı cütdür?
boolean hamısıCüt = rəqəmlər.stream()
    .allMatch(n -> n % 2 == 0);
System.out.println("Hamısı cüt: " + hamısıCüt); // false

// Boş stream — allMatch() həmişə true qaytarır!
boolean boşNəticə = Stream.empty().allMatch(x -> false);
System.out.println("Boş stream allMatch: " + boşNəticə); // true (vacuous truth)
```

### noneMatch() — heç biri uyğun deyilsə true

```java
// Mənfi rəqəm yoxdur?
boolean mənfiYoxdur = rəqəmlər.stream()
    .noneMatch(n -> n < 0);
System.out.println("Mənfi yoxdur: " + mənfiYoxdur); // true

// Boş stream — noneMatch() həmişə true qaytarır
boolean boşNoneMatch = Stream.empty().noneMatch(x -> true);
System.out.println("Boş stream noneMatch: " + boşNoneMatch); // true
```

### Match əməliyyatları real nümunədə

```java
record Sifariş(String id, String status, double məbləğ) {}

List<Sifariş> sifarişlər = List.of(
    new Sifariş("S001", "tamamlandı", 150.0),
    new Sifariş("S002", "gözlənilir", 200.0),
    new Sifariş("S003", "tamamlandı", 75.0),
    new Sifariş("S004", "ləğv edildi", 300.0)
);

// Gözlənilən sifariş varmı?
boolean gözlənilənVar = sifarişlər.stream()
    .anyMatch(s -> "gözlənilir".equals(s.status()));
System.out.println("Gözlənilən var: " + gözlənilənVar); // true

// Hamısı tamamlandı?
boolean hamısıTamamlandı = sifarişlər.stream()
    .allMatch(s -> "tamamlandı".equals(s.status()));
System.out.println("Hamısı tamamlandı: " + hamısıTamamlandı); // false

// Heç biri ləğv edilməyib?
boolean ləğvYoxdur = sifarişlər.stream()
    .noneMatch(s -> "ləğv edildi".equals(s.status()));
System.out.println("Ləğv yoxdur: " + ləğvYoxdur); // false
```

---

## forEach()

```java
List<String> sözlər = List.of("alma", "armud", "gilas");

// Hər biri üçün çap et
sözlər.stream()
      .forEach(System.out::println);

// Filter + forEach
sözlər.stream()
      .filter(s -> s.length() > 4)
      .map(String::toUpperCase)
      .forEach(System.out::println);
// ARMUD
// GİLAS
```

### forEach vs forEachOrdered

```java
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5);

// Ardıcıl stream — hər ikisi eyni sırada
rəqəmlər.stream().forEach(System.out::print);        // 12345
rəqəmlər.stream().forEachOrdered(System.out::print); // 12345

// Paralel stream — forEach sıranı qorumur!
System.out.println("\nParalel forEach:");
rəqəmlər.parallelStream().forEach(System.out::print);        // Sırasız: 31245 (dəyişkən)

System.out.println("\nParalel forEachOrdered:");
rəqəmlər.parallelStream().forEachOrdered(System.out::print); // Həmişə: 12345
// Amma paralel üstünlüyü itirilir!
```

### YANLIŞ — forEach ilə yan effekt

```java
// YANLIŞ — stream xaricindəki dəyişəni dəyişdirmək
List<String> nəticə = new ArrayList<>();
sözlər.stream()
      .filter(s -> s.length() > 4)
      .forEach(nəticə::add); // Thread-safe deyil paralel-də!

// DOĞRU — collect() istifadə et
List<String> nəticəDoğru = sözlər.stream()
    .filter(s -> s.length() > 4)
    .collect(Collectors.toList());
```

---

## toArray()

```java
List<String> sözlər = List.of("alma", "armud", "gilas");

// Object[] olaraq
Object[] massiv1 = sözlər.stream()
    .toArray();

// String[] olaraq — generator verilir
String[] massiv2 = sözlər.stream()
    .toArray(String[]::new);

System.out.println(Arrays.toString(massiv2)); // [alma, armud, gilas]

// Filtrədən keçirib massivə
int[] cütlər = IntStream.rangeClosed(1, 10)
    .filter(n -> n % 2 == 0)
    .toArray();
System.out.println(Arrays.toString(cütlər)); // [2, 4, 6, 8, 10]
```

---

## Short-Circuiting Operations

Bu əməliyyatlar bütün elementləri emal etmir — şərt ödənildikdə dayanır.

| Əməliyyat | Short-circuit olur? | Nə zaman dayanır? |
|-----------|---------------------|-------------------|
| `findFirst()` | Bəli | İlk elementi tapdıqda |
| `findAny()` | Bəli | Hər hansı birini tapdıqda |
| `anyMatch()` | Bəli | `true` tapdıqda |
| `allMatch()` | Bəli | `false` tapdıqda |
| `noneMatch()` | Bəli | `true` tapdıqda |
| `limit()` | Bəli (ara) | n-ci elementdən sonra |
| `count()` | Xeyr | Bütün elementlər |
| `collect()` | Xeyr | Bütün elementlər |
| `reduce()` | Xeyr | Bütün elementlər |
| `forEach()` | Xeyr | Bütün elementlər |

```java
// Short-circuit nümunəsi
List<Integer> böyükSiyahı = new ArrayList<>();
for (int i = 0; i < 1_000_000; i++) {
    böyükSiyahı.add(i);
}

// findFirst — ilk tapılanda dayanır
long başlangıc = System.nanoTime();
Optional<Integer> ilk = böyükSiyahı.stream()
    .filter(n -> {
        // Bu sayı tutmaq üçün — neçə element emal edildi?
        return n == 500;
    })
    .findFirst(); // 501 element emal edilir (0-dan 500-ə)
long vaxt = System.nanoTime() - başlangıc;
System.out.println("findFirst vaxtı: " + vaxt / 1_000 + " mks");

// anyMatch — 5-dən böyük tapdı mı?
boolean var = böyükSiyahı.stream()
    .anyMatch(n -> n > 5); // Yalnız 7 element emal edilir (0-6)
// 6 > 5 tapır, dayanır!

// Sonsuz stream ilə short-circuit
long say = Stream.iterate(1, n -> n + 1) // Sonsuz
    .filter(n -> n % 7 == 0)
    .limit(10)           // SHORT CIRCUIT — yalnız 10 ədəd
    .count();
System.out.println("7-yə bölünən ilk 10: " + say); // 10
```

### Short-circuit praktiki faydası

```java
// Böyük fayl siyahısında ilk uyğunu tap
import java.nio.file.*;
import java.io.*;

// Yüksək performanslı axtarış
public static Optional<Path> tapFayl(Path qovluq, String prefix)
        throws IOException {
    try (Stream<Path> axın = Files.walk(qovluq)) {
        return axın
            .filter(Files::isRegularFile)
            .filter(p -> p.getFileName().toString().startsWith(prefix))
            .findFirst(); // İlk tapılanda DAYANIR — hamısını oxumur!
    }
}

// 1000 fayl arasında ilk uyğun tapılır — hamısını emal etmir
```

---

## Terminal Operations — Müqayisə Cədvəli

```java
// Bütün terminal əməliyyatların tez xülasəsi

List<Integer> rəqəmlər = List.of(3, 1, 4, 1, 5, 9, 2, 6, 5, 3, 5);

// count
long say = rəqəmlər.stream().count();             // 11

// sum (primitiv stream lazımdır)
int cəm = rəqəmlər.stream().mapToInt(i -> i).sum(); // 44

// min / max
Optional<Integer> min = rəqəmlər.stream().min(Comparator.naturalOrder()); // 1
Optional<Integer> maks = rəqəmlər.stream().max(Comparator.naturalOrder()); // 9

// findFirst / findAny
Optional<Integer> first = rəqəmlər.stream().findFirst(); // 3
Optional<Integer> any = rəqəmlər.stream().findAny();     // Adətən 3

// anyMatch / allMatch / noneMatch
boolean any5 = rəqəmlər.stream().anyMatch(n -> n == 5);   // true
boolean allPos = rəqəmlər.stream().allMatch(n -> n > 0);  // true
boolean noneNeg = rəqəmlər.stream().noneMatch(n -> n < 0); // true

// reduce
Optional<Integer> product = rəqəmlər.stream().reduce((a, b) -> a * b);

// collect
List<Integer> list = rəqəmlər.stream().collect(Collectors.toList());
Set<Integer> set = rəqəmlər.stream().collect(Collectors.toSet()); // {1,2,3,4,5,6,9}

// toArray
Integer[] arr = rəqəmlər.stream().toArray(Integer[]::new);

// forEach
rəqəmlər.stream().forEach(n -> System.out.print(n + " "));
```

---

## İntervyu Sualları

**S: `findFirst()` ilə `findAny()` arasındakı fərq nədir?**
C: `findFirst()` — həmişə stream-in ilk uyğun elementini qaytarır (sıralı). `findAny()` — istənilən uyğun elementi qaytarır, paralel stream-lərdə daha effektivdir (sıra önəmli deyilsə). Ardıcıl stream-lərdə hər ikisi adətən eyni nəticəni verir.

**S: `anyMatch()`, `allMatch()`, `noneMatch()` boş stream üçün nə qaytarır?**
C: `anyMatch()` → `false` (heç bir element şərtə uymur); `allMatch()` → `true` (vacuous truth — heç bir element şərti pozmir); `noneMatch()` → `true` (heç bir element şərtə uymuyor). Boş stream-in allMatch/noneMatch-in true qaytarması intuitive deyil — bilmək lazımdır.

**S: `reduce()` ilə `collect()` arasındakı fərq nədir?**
C: `reduce()` — dəyərməz (immutable) birləşdirmə üçün, hər addımda yeni dəyər yaradılır (cəm, hasılat). `collect()` — mutable konteynerə toplama üçün (List, Map, StringBuilder). Performans: `collect()` List yaratmaqda daha effektivdir çünki bir kolleksiyaya əlavə edir.

**S: `forEach()` ilə `forEachOrdered()` fərqi nədir?**
C: Ardıcıl stream-lərdə heç bir fərq yoxdur. Paralel stream-lərdə: `forEach()` sıranı qorumur (thread-lər istənilən sırada işləyir), `forEachOrdered()` sıranı qoruyur amma paralel üstünlüyünü itirir.

**S: Short-circuiting əməliyyatlar hansılardır?**
C: `findFirst()`, `findAny()` — ilk uyğunu tapdıqda; `anyMatch()` — true tapdıqda; `allMatch()` — false tapdıqda; `noneMatch()` — true tapdıqda; `limit()` — n elementdən sonra (ara əməliyyat). Bu əməliyyatlar bütün stream-i emal etmir — böyük/sonsuz stream-lərdə vacibdir.

**S: `toArray()` istifadə edərkən niyə generator verilir?**
C: `toArray()` — `Object[]` qaytarır (type-safe deyil). `toArray(String[]::new)` — düzgün tipli massiv yaradır. Generator — massiv ölçüsünü alan funksiya: `n -> new String[n]` yaxud `String[]::new`. Bu olmadan runtime-da cast problemi yarana bilər.
