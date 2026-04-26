# 49 — Streams — Ara Əməliyyatlar (Intermediate Operations)

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [filter()](#filter)
2. [map()](#map)
3. [flatMap()](#flatmap)
4. [distinct()](#distinct)
5. [sorted()](#sorted)
6. [peek()](#peek)
7. [limit() və skip()](#limit-skip)
8. [mapToInt / mapToLong / mapToDouble](#primitiv-map)
9. [Method References](#method-references)
10. [Sıralama Önəmlidir](#sıralama-önəmlidir)
11. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## filter()

`filter(Predicate<T>)` — şərtə uyan elementləri saxlayır, digərlərini atar.

```java
import java.util.*;
import java.util.stream.*;

List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

// Cüt rəqəmlər
List<Integer> cütlər = rəqəmlər.stream()
    .filter(n -> n % 2 == 0)
    .collect(Collectors.toList());
System.out.println(cütlər); // [2, 4, 6, 8, 10]

// Tək rəqəmlər
List<Integer> təklər = rəqəmlər.stream()
    .filter(n -> n % 2 != 0)
    .collect(Collectors.toList());

// 5-dən böyüklər
List<Integer> böyüklər = rəqəmlər.stream()
    .filter(n -> n > 5)
    .collect(Collectors.toList());
System.out.println(böyüklər); // [6, 7, 8, 9, 10]
```

### filter() ilə mürəkkəb şərtlər

```java
record İşçi(String ad, String şöbə, double maaş) {}

List<İşçi> işçilər = List.of(
    new İşçi("Əli", "IT", 2500),
    new İşçi("Aysel", "HR", 1800),
    new İşçi("Murad", "IT", 3200),
    new İşçi("Leyla", "Maliyyə", 2100),
    new İşçi("Orxan", "IT", 2800)
);

// IT şöbəsindən maaşı 2500-dən çox olanlar
List<İşçi> ITAlı = işçilər.stream()
    .filter(i -> "IT".equals(i.şöbə()))
    .filter(i -> i.maaş() > 2500)
    .collect(Collectors.toList());

// Eyni şey — AND şərtlə
List<İşçi> ITAlı2 = işçilər.stream()
    .filter(i -> "IT".equals(i.şöbə()) && i.maaş() > 2500)
    .collect(Collectors.toList());

// Predicate birləşdirmə — daha oxunaqlı
Predicate<İşçi> ITşöbəsi = i -> "IT".equals(i.şöbə());
Predicate<İşçi> yüksəkMaaş = i -> i.maaş() > 2500;

List<İşçi> nəticə = işçilər.stream()
    .filter(ITşöbəsi.and(yüksəkMaaş))
    .collect(Collectors.toList());

// Null dəyərləri filtrləmək
List<String> nulllıSiyahı = Arrays.asList("alma", null, "armud", null, "gilas");
List<String> nullsuz = nulllıSiyahı.stream()
    .filter(Objects::nonNull) // null-ları çıxar
    .collect(Collectors.toList());
System.out.println(nullsuz); // [alma, armud, gilas]
```

---

## map()

`map(Function<T, R>)` — hər elementi çevirir, yeni stream yaradır.

```java
List<String> sözlər = List.of("alma", "armud", "gilas");

// Böyük hərflə
List<String> böyükHərflər = sözlər.stream()
    .map(String::toUpperCase)
    .collect(Collectors.toList());
System.out.println(böyükHərflər); // [ALMA, ARMUD, GİLAS]

// Uzunluqlarını al
List<Integer> uzunluqlar = sözlər.stream()
    .map(String::length)
    .collect(Collectors.toList());
System.out.println(uzunluqlar); // [4, 5, 5]

// Obyekt → Obyekt çevirməsi
record Şəhər(String ad, int əhali) {}
record ŞəhərDto(String ad, String əhali) {}

List<Şəhər> şəhərlər = List.of(
    new Şəhər("Bakı", 2_300_000),
    new Şəhər("Gəncə", 330_000),
    new Şəhər("Sumqayıt", 350_000)
);

List<ŞəhərDto> dtolar = şəhərlər.stream()
    .map(ş -> new ŞəhərDto(ş.ad(), String.format("%,d nəfər", ş.əhali())))
    .collect(Collectors.toList());

dtolar.forEach(d -> System.out.println(d.ad() + ": " + d.əhali()));
// Bakı: 2,300,000 nəfər
// Gəncə: 330,000 nəfər
```

### map() — çoxlu çevirmə

```java
List<String> rəqəmSözlər = List.of("1", "2", "3", "4", "5");

// String → Integer → Integer (kare)
List<Integer> kvadratlar = rəqəmSözlər.stream()
    .map(Integer::parseInt)    // String → Integer
    .map(n -> n * n)           // Integer → Integer (kare)
    .collect(Collectors.toList());
System.out.println(kvadratlar); // [1, 4, 9, 16, 25]
```

---

## flatMap()

`flatMap(Function<T, Stream<R>>)` — hər elementi stream-ə çevirir, sonra hamısını birləşdirir (düzləndirir).

```java
// map() vs flatMap() fərqi

// Sözlər siyahısı
List<String> cümlələr = List.of("Salam dünya", "Java öyrənirəm", "Stream çox gözəldir");

// map() — stream of streams yaradır
Stream<String[]> mapNəticə = cümlələr.stream()
    .map(cümlə -> cümlə.split(" "));
// Stream<String[]> — massivlər stream-i

// flatMap() — düzləndirir
List<String> bütünSözlər = cümlələr.stream()
    .flatMap(cümlə -> Arrays.stream(cümlə.split(" ")))
    .collect(Collectors.toList());
System.out.println(bütünSözlər);
// [Salam, dünya, Java, öyrənirəm, Stream, çox, gözəldir]
```

### flatMap() — siyahıların siyahısı

```java
// Siyahıların siyahısını düzləndir
List<List<Integer>> ədədSiyahıları = List.of(
    List.of(1, 2, 3),
    List.of(4, 5, 6),
    List.of(7, 8, 9)
);

// flatMap ilə bütün elementlər bir siyahıda
List<Integer> hamısı = ədədSiyahıları.stream()
    .flatMap(Collection::stream) // Hər siyahını stream-ə çevir
    .collect(Collectors.toList());
System.out.println(hamısı); // [1, 2, 3, 4, 5, 6, 7, 8, 9]

// Cəm
int cəm = ədədSiyahıları.stream()
    .flatMapToInt(siyahı -> siyahı.stream().mapToInt(Integer::intValue))
    .sum();
System.out.println("Cəm: " + cəm); // 45
```

### flatMap() — real nümunə

```java
record Sifariş(String müştəri, List<String> məhsullar) {}

List<Sifariş> sifarişlər = List.of(
    new Sifariş("Əli", List.of("alma", "armud")),
    new Sifariş("Aysel", List.of("gilas", "üzüm", "portağal")),
    new Sifariş("Murad", List.of("alma", "gilas"))
);

// Bütün sifarişlərdəki bənzərsiz məhsullar
Set<String> bütünMəhsullar = sifarişlər.stream()
    .flatMap(s -> s.məhsullar().stream()) // Hər sifarişin məhsullarını düzləndir
    .collect(Collectors.toSet());
System.out.println(bütünMəhsullar); // [alma, armud, gilas, üzüm, portağal]

// Alma sifariş edən müştərilər
List<String> almaMüştəriləri = sifarişlər.stream()
    .filter(s -> s.məhsullar().contains("alma"))
    .map(Sifariş::müştəri)
    .collect(Collectors.toList());
System.out.println(almaMüştəriləri); // [Əli, Murad]
```

---

## distinct()

`distinct()` — eyni elementləri bir dəfə saxlayır (equals() əsasında).

```java
List<Integer> dublikatlar = List.of(1, 2, 2, 3, 3, 3, 4, 4, 4, 4);

List<Integer> bənzərsizlər = dublikatlar.stream()
    .distinct()
    .collect(Collectors.toList());
System.out.println(bənzərsizlər); // [1, 2, 3, 4]

// String-lərlə
List<String> sözlər = List.of("alma", "armud", "alma", "gilas", "armud");
sözlər.stream()
      .distinct()
      .forEach(System.out::println); // alma, armud, gilas

// Filter + distinct birlikdə
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 2, 4, 6);
long unikal cüt = rəqəmlər.stream()
    .filter(n -> n % 2 == 0)
    .distinct()
    .count();
System.out.println("Unikal cüt: " + unikal cüt); // 5 (2,4,6,8,10)
```

---

## sorted()

`sorted()` — təbii sıralama (Comparable); `sorted(Comparator)` — xüsusi sıralama.

```java
List<Integer> rəqəmlər = List.of(5, 3, 8, 1, 9, 2, 7, 4, 6);

// Artan sıralama (default)
List<Integer> artan = rəqəmlər.stream()
    .sorted()
    .collect(Collectors.toList());
System.out.println(artan); // [1, 2, 3, 4, 5, 6, 7, 8, 9]

// Azalan sıralama
List<Integer> azalan = rəqəmlər.stream()
    .sorted(Comparator.reverseOrder())
    .collect(Collectors.toList());
System.out.println(azalan); // [9, 8, 7, 6, 5, 4, 3, 2, 1]
```

### sorted() — mürəkkəb Comparator

```java
record Kitab(String ad, String müəllif, int il, double qiymət) {}

List<Kitab> kitablar = List.of(
    new Kitab("Java", "Gosling", 2020, 45.0),
    new Kitab("Python", "van Rossum", 2019, 35.0),
    new Kitab("C++", "Stroustrup", 2020, 50.0),
    new Kitab("Rust", "Matsakis", 2021, 40.0),
    new Kitab("Go", "Pike", 2019, 35.0)
);

// İl üzrə sırala, eyni il varsa qiymətə görə
List<Kitab> sıralıKitablar = kitablar.stream()
    .sorted(Comparator.comparingInt(Kitab::il)
                      .thenComparingDouble(Kitab::qiymət)
                      .thenComparing(Kitab::ad))
    .collect(Collectors.toList());

sıralıKitablar.forEach(k ->
    System.out.printf("%s (%d) - %.1f AZN%n", k.ad(), k.il(), k.qiymət()));

// Azalan qiymət
kitablar.stream()
    .sorted(Comparator.comparingDouble(Kitab::qiymət).reversed())
    .map(Kitab::ad)
    .forEach(System.out::println);
// C++, Java, Rust, Python, Go
```

---

## peek()

`peek(Consumer<T>)` — hər elementə baxır (yan effekt), dəyişdirmir. Əsasən **debug** üçündür.

```java
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5);

// Debug üçün peek — pipeline-ın hər mərhələsini izlə
List<Integer> nəticə = rəqəmlər.stream()
    .peek(n -> System.out.println("Before filter: " + n))
    .filter(n -> n % 2 == 0)
    .peek(n -> System.out.println("After filter: " + n))
    .map(n -> n * n)
    .peek(n -> System.out.println("After map: " + n))
    .collect(Collectors.toList());

/* Çıxış:
Before filter: 1
Before filter: 2
After filter: 2
After map: 4
Before filter: 3
Before filter: 4
After filter: 4
After map: 16
Before filter: 5
*/
System.out.println(nəticə); // [4, 16]
```

### YANLIŞ — peek() yan effekt üçün

```java
// YANLIŞ — peek() ilə məlumat dəyişdirmək!
List<StringBuilder> sözlər = new ArrayList<>();
sözlər.add(new StringBuilder("alma"));
sözlər.add(new StringBuilder("armud"));

// Bu işləyir amma PƏIS praktikadır
List<StringBuilder> dəyişdirilmiş = sözlər.stream()
    .peek(sb -> sb.append("!")) // Mütasiya edir — YANLIŞ
    .collect(Collectors.toList());

// DOĞRU — map() ilə yeni obyekt
List<String> düzgün = sözlər.stream()
    .map(sb -> sb.toString() + "!") // Yeni string yaradır
    .collect(Collectors.toList());
```

---

## limit() və skip()

```java
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

// İlk 5 element
List<Integer> ilkBeş = rəqəmlər.stream()
    .limit(5)
    .collect(Collectors.toList());
System.out.println(ilkBeş); // [1, 2, 3, 4, 5]

// İlk 3-ü keç, qalanını al
List<Integer> üçdənSonra = rəqəmlər.stream()
    .skip(3)
    .collect(Collectors.toList());
System.out.println(üçdənSonra); // [4, 5, 6, 7, 8, 9, 10]

// Səhifələmə (pagination)
int səhifəÖlçüsü = 3;
int səhifə = 2; // 2-ci səhifə (0-dan başlayır)

List<Integer> səhifəNəticəsi = rəqəmlər.stream()
    .skip((long) səhifə * səhifəÖlçüsü) // İlk n səhifəni keç
    .limit(səhifəÖlçüsü)                 // Yalnız bir səhifə al
    .collect(Collectors.toList());
System.out.println(səhifəNəticəsi); // [7, 8, 9]

// Sonsuz stream ilə limit
Stream.iterate(1, n -> n + 1)  // Sonsuz: 1, 2, 3...
    .filter(n -> isPrime(n))    // Sadə ədədlər
    .limit(10)                  // İlk 10 sadə ədəd
    .forEach(System.out::println); // 2, 3, 5, 7, 11, 13, 17, 19, 23, 29

static boolean isPrime(int n) {
    if (n < 2) return false;
    return IntStream.rangeClosed(2, (int) Math.sqrt(n))
                    .allMatch(i -> n % i != 0);
}
```

---

## Primitiv Map

`mapToInt()`, `mapToLong()`, `mapToDouble()` — boxing olmadan primitiv stream-ə çevirmə.

```java
List<String> sözlər = List.of("alma", "armud", "gilas", "üzüm");

// String → int (uzunluq) — boxing yoxdur
IntStream uzunluqlar = sözlər.stream()
    .mapToInt(String::length);

System.out.println("Cəm: " + uzunluqlar.sum()); // 4+5+5+4 = 18

// Orta uzunluq
OptionalDouble orta = sözlər.stream()
    .mapToInt(String::length)
    .average();
orta.ifPresent(a -> System.out.printf("Orta: %.1f%n", a));

// mapToLong
List<String> dosyaÖlçüləri = List.of("1024", "2048", "4096");
long cəmBayt = dosyaÖlçüləri.stream()
    .mapToLong(Long::parseLong)
    .sum();
System.out.println("Cəm: " + cəmBayt + " bayt"); // 7168 bayt

// mapToDouble
List<Integer> qiymətlər = List.of(100, 200, 150, 300);
double ortaQiymət = qiymətlər.stream()
    .mapToDouble(Integer::doubleValue)
    .average()
    .orElse(0.0);
System.out.println("Orta: " + ortaQiymət); // 187.5

// asDoubleStream, asLongStream — bir primitiv tipdan digərinə
IntStream intStream = IntStream.range(1, 6);
DoubleStream doubleStream = intStream.asDoubleStream();
LongStream longStream = IntStream.range(1, 6).asLongStream();
```

---

## Method References

Method reference — lambda-nın qısaldılmış forması. 4 növü var:

```java
// 1. Statik metod referansı: ClassName::staticMethod
Stream.of("1", "2", "3")
    .map(Integer::parseInt)    // s -> Integer.parseInt(s)
    .forEach(System.out::println);

// 2. Instance metod referansı (xüsusi instans): instance::method
String prefix = "Salam ";
Stream.of("Əli", "Aysel", "Murad")
    .map(prefix::concat)       // s -> prefix.concat(s)
    .forEach(System.out::println);

// 3. Instance metod referansı (ixtiyari instans): ClassName::instanceMethod
Stream.of("alma", "ARMUD", "Gilas")
    .map(String::toLowerCase)  // s -> s.toLowerCase()
    .forEach(System.out::println);

// 4. Constructor referansı: ClassName::new
Stream.of("alma", "armud", "gilas")
    .map(StringBuilder::new)   // s -> new StringBuilder(s)
    .forEach(System.out::println);
```

### Method references praktiki nümunələr

```java
import java.util.*;
import java.util.stream.*;

record Şəxs(String ad, int yaş) {
    String adıVer() { return ad; }
    static Şəxs tap(String ad) { return new Şəxs(ad, 0); }
}

List<Şəxs> şəxslər = List.of(
    new Şəxs("Əli", 25),
    new Şəxs("Aysel", 30),
    new Şəxs("Murad", 28)
);

// Instance metod referansı (ixtiyari instans)
List<String> adlar = şəxslər.stream()
    .map(Şəxs::adıVer)          // ş -> ş.adıVer()
    .collect(Collectors.toList());

// Comparator ilə
şəxslər.stream()
    .sorted(Comparator.comparing(Şəxs::yaş))  // Yaşa görə sırala
    .forEach(ş -> System.out.println(ş.ad() + ": " + ş.yaş()));

// Constructor referansı
List<String> adSiyahısı = List.of("Elçin", "Nərmin", "Rauf");
List<Şəxs> yeniŞəxslər = adSiyahısı.stream()
    .map(Şəxs::tap)             // ad -> Şəxs.tap(ad)
    .collect(Collectors.toList());

// System.out::println — çox istifadə olunan
şəxslər.stream()
    .map(Şəxs::ad)
    .forEach(System.out::println);
```

---

## Sıralama Önəmlidir

Ara əməliyyatların sırası performansa ciddi təsir edir!

```java
List<String> sözlər = new ArrayList<>();
for (int i = 0; i < 1000; i++) {
    sözlər.add("söz" + i);
}

// ===== YANLIŞ SIRA =====
// map() əvvəl, filter() sonra — bütün elementlər çevrilir!
long başlangıc1 = System.nanoTime();
long say1 = sözlər.stream()
    .map(String::toUpperCase)      // 1000 element üçün
    .filter(s -> s.startsWith("SÖZ5")) // Sonra filtrə
    .count();
long vaxt1 = System.nanoTime() - başlangıc1;

// ===== DOĞRU SIRA =====
// filter() əvvəl — yalnız lazımlılar çevrilir!
long başlangıc2 = System.nanoTime();
long say2 = sözlər.stream()
    .filter(s -> s.startsWith("söz5")) // Əvvəlcə filtrə
    .map(String::toUpperCase)           // Yalnız filtrədən keçənlər
    .count();
long vaxt2 = System.nanoTime() - başlangıc2;

System.out.println("Yanlış sıra: " + vaxt1 + " ns");
System.out.println("Doğru sıra: " + vaxt2 + " ns");
// Doğru sıra daha sürətlidir!
```

### sorted() mövqeyi

```java
// YANLIŞ — sorted() erkən çağırılır
Stream.of(5, 3, 1, 4, 2)
    .sorted()                   // Hamısını sırala (beş element)
    .filter(n -> n > 2)         // Filtrə
    .limit(2)                   // Yalnız 2
    .collect(Collectors.toList()); // Nəticə: [3, 4]

// DAHA YAXŞI — limit mümkünsə sorted-dan əvvəl
// (bu nümunədə mümkün deyil, çünki sorted+filter birlikdə lazımdır)

// Amma bu hal üçün:
Stream.of(5, 3, 1, 4, 2)
    .filter(n -> n > 2)         // Əvvəlcə filtrə
    .sorted()                   // Sonra sırala (yalnız 3 element)
    .limit(2)
    .collect(Collectors.toList()); // Nəticə: [3, 4]
```

### distinct() mövqeyi

```java
List<Integer> rəqəmlər = List.of(1, 2, 2, 3, 3, 3, 4);

// YANLIŞ — distinct() sonra filter-dan
long say1 = rəqəmlər.stream()
    .distinct()      // 4 element: [1,2,3,4]
    .filter(n -> n > 2) // 2 element: [3,4]
    .count(); // 2

// DAHA İYİ — filter əvvəl
long say2 = rəqəmlər.stream()
    .filter(n -> n > 2)  // 4 element: [3,3,3,4]
    .distinct()           // 2 element: [3,4]
    .count(); // 2
// Eyni nəticə, amma distinct() az elementlə işlədi
```

---

## İntervyu Sualları

**S: `map()` ilə `flatMap()` arasındakı fərq nədir?**
C: `map()` — hər elementi bir nəticəyə çevirir, `Stream<R>` qaytarır. `flatMap()` — hər elementi stream-ə çevirir, sonra bütün stream-ləri birləşdirir (düzləndirir). `List<List<T>>` → `List<T>` çevirməsi üçün `flatMap()` istifadə edilir.

**S: `peek()` nə üçündür? Niyə production kodunda istifadə etməməliyik?**
C: Əsasən debug üçündür — pipeline-ın hər mərhələsini izləmək. Production-da: 1) yan effektlər yaratmaq üçün nəzərdə tutulmayıb, 2) parallel stream-lərdə sıralama qeyri-müəyyəndir, 3) lazy olduğu üçün terminal op olmasa çağırılmır.

**S: Stream-də əməliyyatların sırası niyə önəmlidir?**
C: Performansa təsir edir. `filter()` əvvəl gəlməlidir — sonrakı əməliyyatlar az elementlə işləyir. `sorted()` bütün stream-i buffer-a alır — mümkün qədər gec, `limit()`-dən əvvəl. Yanlış sıra eyni nəticəni verir amma daha yavaş işləyir.

**S: Method reference-ın 4 növünü izah edin.**
C: 1) Statik: `Integer::parseInt` = `s -> Integer.parseInt(s)`; 2) Xüsusi instans: `obj::method` = `x -> obj.method(x)`; 3) İxtiyari instans: `String::toUpperCase` = `s -> s.toUpperCase()`; 4) Constructor: `StringBuilder::new` = `s -> new StringBuilder(s)`.

**S: `mapToInt()` nə üçün istifadə edilir?**
C: `Stream<Integer>` əvəzinə `IntStream` yaradır — boxing/unboxing olmur, daha effektivdir. Əlavə olaraq `sum()`, `average()`, `summaryStatistics()` kimi hesab metodları var. Böyük məlumat topluları üçün performance fərqi əhəmiyyətlidir.

**S: `distinct()` necə işləyir?**
C: `equals()` metoduna əsaslanır — hər element üçün daha əvvəl görülüb-görülmədiyini yoxlayır. Sıralı stream-lərdə daha effektivdir (ardıcıl eyni elementləri tutur). Unsorted stream-lərdə HashSet kimi struktur istifadə edir.
