# 25. Streams — Əsaslar

## Mündəricat
1. [Stream nədir?](#stream-nədir)
2. [Lazy Evaluation](#lazy-evaluation)
3. [Stream Pipeline](#stream-pipeline)
4. [Stream Yaratmaq](#stream-yaratmaq)
5. [Streams vs Data Structures](#streams-vs-data-structures)
6. [Stream-i Yenidən İstifadə etmək](#stream-reuse)
7. [Primitiv Streams](#primitiv-streams)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Stream nədir?

Stream — məlumatların ardıcıl emal edilməsi üçün Java 8-də təqdim edilmiş API-dir. Stream **məlumat strukturu deyil** — mövcud məlumatları emal edən bir boru xətti (pipeline) kimidir.

```
Kolleksiya/Massiv → Stream → Emal → Nəticə
```

### Stream-in əsas xüsusiyyətləri:

1. **Məlumat saxlamır** — yalnız məlumatı emal edir
2. **Mənbəni dəyişdirmir** — orijinal kolleksiya toxunulmaz qalır
3. **Lazy-dir** — yalnız nəticə lazım olduqda işləyir
4. **Bir dəfə istifadə olunur** — tükəndikdən sonra yenidən istifadə olmur
5. **Birləşdirilib** — metodlar zəncir şəklində çağırıla bilər

```java
import java.util.*;
import java.util.stream.*;

// Ənənəvi yanaşma vs Stream yanaşması
List<String> sözlər = List.of("alma", "armud", "gilas", "üzüm", "portağal");

// KÖHNƏ yanaşma — imperative
List<String> uzunSözlər = new ArrayList<>();
for (String söz : sözlər) {
    if (söz.length() > 4) {
        uzunSözlər.add(söz.toUpperCase());
    }
}
Collections.sort(uzunSözlər);
System.out.println(uzunSözlər);

// STREAM yanaşması — declarative
List<String> uzunSözlər2 = sözlər.stream()
    .filter(s -> s.length() > 4)      // filtrə
    .map(String::toUpperCase)          // çevir
    .sorted()                          // sırala
    .collect(Collectors.toList());     // yığ

System.out.println(uzunSözlər2);
// Her iki nəticə eynidir
```

---

## Lazy Evaluation

Stream-lər **tənbəldir** — terminal əməliyyat çağırılana qədər heç bir iş görülmür.

```java
import java.util.stream.*;
import java.util.*;

// Lazy evaluation nümunəsi
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

System.out.println("Stream yaradılır...");

Stream<Integer> stream = rəqəmlər.stream()
    .filter(n -> {
        System.out.println("filter: " + n); // Nə vaxt çağırılır?
        return n % 2 == 0;
    })
    .map(n -> {
        System.out.println("map: " + n);    // Nə vaxt çağırılır?
        return n * n;
    });

System.out.println("Terminal əməliyyat başlayır...");
List<Integer> nəticə = stream.collect(Collectors.toList());
// YALNIZ BU ANDA filter və map çağırılır!

System.out.println(nəticə); // [4, 16, 36, 64, 100]

/* Çıxış:
Stream yaradılır...
Terminal əməliyyat başlayır...
filter: 1
filter: 2
map: 2
filter: 3
filter: 4
map: 4
... */
```

### Short-circuiting ilə lazy-nin gücü

```java
// Sonsuz stream + laziness = hər şey işləyir!
Stream.iterate(1, n -> n + 1)  // Sonsuz: 1, 2, 3, 4, ...
    .filter(n -> n % 3 == 0)   // 3-ə bölünənlər
    .limit(5)                   // Yalnız 5 ədəd — SHORT CIRCUIT!
    .forEach(System.out::println);
// 3, 6, 9, 12, 15
// limit() olmasa sonsuz dövrə olardı!
```

```java
// Lazy-nin praktiki faydasını göstərən nümunə
List<String> böyüksiyahı = new ArrayList<>();
for (int i = 0; i < 1_000_000; i++) {
    böyüksiyahı.add("element" + i);
}

// İlk 5 uzun sözü tap — LAZY olduğu üçün hamısını emal etmir!
long başlangıc = System.currentTimeMillis();

Optional<String> ilkUzun = böyüksiyahı.stream()
    .filter(s -> s.length() > 8)     // Lazım olduğu qədər emal edir
    .findFirst();                     // İlk tapılanda DAYANIR

long son = System.currentTimeMillis();
System.out.println("Tapıldı: " + ilkUzun.orElse("yoxdur"));
System.out.println("Vaxt: " + (son - başlangıc) + "ms");
```

---

## Stream Pipeline

Hər stream pipeline üç hissədən ibarətdir:

```
[SOURCE] → [INTERMEDIATE OPS] → [TERMINAL OP]
Mənbə   →  Ara əməliyyatlar  →  Son əməliyyat
```

### 1. Source (Mənbə)

```java
// Kolleksiyadan
Collection<String> kolleksiya = List.of("a", "b", "c");
Stream<String> s1 = kolleksiya.stream();

// Massivdən
int[] massiv = {1, 2, 3, 4, 5};
IntStream s2 = Arrays.stream(massiv);

// Stream.of() ilə
Stream<String> s3 = Stream.of("x", "y", "z");

// Generasiya ilə
Stream<Double> s4 = Stream.generate(Math::random).limit(5);
Stream<Integer> s5 = Stream.iterate(0, n -> n + 1).limit(10);
```

### 2. Intermediate Operations (Ara əməliyyatlar)

Yeni stream qaytarır — lazy-dir, zəncir qurulur:

```java
stream
    .filter(predicate)   // Şərtə uyğunları saxla
    .map(function)       // Çevir
    .flatMap(function)   // Düzləndir və çevir
    .distinct()          // Dublikatları sil
    .sorted()            // Sırala
    .sorted(comparator)  // Müqayisəçi ilə sırala
    .peek(consumer)      // Bax, dəyişdirmə (debug üçün)
    .limit(n)            // İlk n elementi saxla
    .skip(n)             // İlk n elementi keç
```

### 3. Terminal Operations (Son əməliyyatlar)

Nəticə qaytarır, stream-i bağlayır:

```java
stream
    .collect(collector)  // Toplama
    .forEach(consumer)   // Hər biri üçün
    .toArray()           // Massivə çevir
    .reduce(...)         // Azalt/birləşdir
    .count()             // Say
    .min(comparator)     // Minimum
    .max(comparator)     // Maksimum
    .findFirst()         // İlk element
    .findAny()           // Hər hansı element
    .anyMatch(pred)      // Hər hansı uyğundur?
    .allMatch(pred)      // Hamısı uyğundur?
    .noneMatch(pred)     // Heç biri uyğun deyil?
```

---

## Stream Yaratmaq

### Collection.stream() və parallelStream()

```java
List<String> siyahı = List.of("Bakı", "Gəncə", "Sumqayıt");

// Ardıcıl stream
Stream<String> stream = siyahı.stream();

// Paralel stream
Stream<String> paralel = siyahı.parallelStream();

// Set-dən
Set<Integer> çoxluq = Set.of(1, 2, 3);
Stream<Integer> setStream = çoxluq.stream();

// Map-dən — entry-lər kimi
Map<String, Integer> xəritə = Map.of("a", 1, "b", 2);
Stream<Map.Entry<String, Integer>> entryStream = xəritə.entrySet().stream();
Stream<String> açarlar = xəritə.keySet().stream();
Stream<Integer> dəyərlər = xəritə.values().stream();
```

### Arrays.stream()

```java
// Primitiv massiv
int[] tamlar = {1, 2, 3, 4, 5};
IntStream intStream = Arrays.stream(tamlar); // IntStream (not Stream<Integer>)

double[] onluqlar = {1.1, 2.2, 3.3};
DoubleStream doubleStream = Arrays.stream(onluqlar);

// Referans massiv
String[] sözlər = {"alma", "armud", "gilas"};
Stream<String> strStream = Arrays.stream(sözlər);

// Hissəli — başlangıc (inclusive) dan son (exclusive) a
IntStream hissə = Arrays.stream(tamlar, 1, 4); // indeks 1-dən 3-ə
```

### Stream.of()

```java
// Dəyərlərdən
Stream<String> s1 = Stream.of("a", "b", "c");

// Tək elementli
Stream<String> s2 = Stream.of("yalnız bir");

// Boş stream
Stream<String> s3 = Stream.empty();

// Nullable — Java 9+
String dəyər = null;
Stream<String> s4 = Stream.ofNullable(dəyər); // Boş stream (NPE yox)
Stream<String> s5 = Stream.ofNullable("dəyər"); // Bir elementli stream
```

### Stream.generate() və Stream.iterate()

```java
// generate() — Supplier-dan sonsuz stream
Stream<Double> təsadüfi = Stream.generate(Math::random);
Stream<String> sabitlər = Stream.generate(() -> "Salam");

// Limitlə istifadə
Stream.generate(Math::random)
      .limit(5)
      .forEach(d -> System.out.printf("%.3f%n", d));

// iterate() — başlangıc + əməliyyat
// Java 8 — sonsuz
Stream<Integer> cüt = Stream.iterate(0, n -> n + 2);
cüt.limit(5).forEach(System.out::println); // 0, 2, 4, 6, 8

// Java 9 — şərtli (for loopuna bənzər)
Stream<Integer> məhdud = Stream.iterate(0, n -> n < 10, n -> n + 2);
// Başlangıc: 0, Şərt: n<10, Addım: n+2
məhdud.forEach(System.out::println); // 0, 2, 4, 6, 8
```

### Digər mənbələr

```java
// String-dən simvol stream (Java 9+)
"Salam".chars()                     // IntStream (char kodları)
       .mapToObj(c -> (char) c)     // char-a çevir
       .forEach(System.out::println); // S, a, l, a, m

// Files — fayl sətirləri
import java.nio.file.*;
try (Stream<String> sətir = Files.lines(Path.of("fayl.txt"))) {
    sətir.filter(s -> !s.isEmpty())
         .forEach(System.out::println);
}
// try-with-resources — stream bağlanır

// Pattern.splitAsStream() — Java 8+
import java.util.regex.Pattern;
Pattern.compile(",")
       .splitAsStream("alma,armud,gilas")
       .forEach(System.out::println); // alma, armud, gilas

// IntStream.range() / IntStream.rangeClosed()
IntStream.range(0, 5)      // 0, 1, 2, 3, 4 (5 daxil deyil)
         .forEach(System.out::println);

IntStream.rangeClosed(1, 5) // 1, 2, 3, 4, 5 (5 daxildir)
         .forEach(System.out::println);
```

---

## Streams vs Data Structures

Stream **məlumat strukturu deyil!** Bu fərqi anlamaq çox vacibdir.

```java
// Kolleksiya — məlumat saxlayır
List<String> siyahı = new ArrayList<>();
siyahı.add("a");
siyahı.add("b");
// Hər zaman mövcuddur, dəfələrlə istifadə olunur

// Stream — məlumatı emal edir
Stream<String> stream = siyahı.stream();
// Yalnız bir dəfə istifadə edilir, saxlamır
```

| Xüsusiyyət | Kolleksiya | Stream |
|------------|-----------|--------|
| Məlumat saxlayır? | Bəli | Xeyr |
| Dəfələrlə istifadə? | Bəli | Xeyr (bir dəfə) |
| Ölçüsü? | Sonlu | Sonlu/Sonsuz |
| Emal vaxtı? | Əlavə zamanı | Terminal op zamanı |
| Mənbəni dəyişdirir? | - | Xeyr |

```java
// Stream mənbəni dəyişdirmir
List<String> orijinal = new ArrayList<>(List.of("c", "a", "b"));

List<String> sıralı = orijinal.stream()
    .sorted()
    .collect(Collectors.toList());

System.out.println(orijinal); // [c, a, b] — dəyişmədi!
System.out.println(sıralı);   // [a, b, c] — yeni siyahı

// Yanlış düşüncə — stream-in özü məlumat saxlayır
// Stream yalnız pipeline-dır — məlumat kolleksiyadadır
```

---

## Stream-i Yenidən İstifadə etmək

Stream **bir dəfə** istifadə olunur. Tükəndikdən sonra yenidən istifadə etmək `IllegalStateException` verir.

### YANLIŞ — stream-i yenidən istifadə

```java
// YANLIŞ — stream tükənib
Stream<String> stream = Stream.of("a", "b", "c");

// Birinci istifadə
long say = stream.count(); // Stream tükəndi!
System.out.println("Say: " + say);

// İkinci istifadə — XƏTA!
stream.forEach(System.out::println); // IllegalStateException!
// "stream has already been operated upon or closed"
```

### DOĞRU — hər dəfə yeni stream

```java
// DOĞRU 1 — hər dəfə yeni stream al
List<String> mənbə = List.of("a", "b", "c");

long say = mənbə.stream().count(); // Yeni stream
mənbə.stream().forEach(System.out::println); // Yeni stream

// DOĞRU 2 — Supplier ilə
Supplier<Stream<String>> streamSupplier = () -> mənbə.stream();

long say2 = streamSupplier.get().count(); // Yeni stream hər çağırışda
streamSupplier.get().forEach(System.out::println); // Yeni stream

// DOĞRU 3 — nəticəni yadda saxla
List<String> nəticə = mənbə.stream()
    .filter(s -> !s.isEmpty())
    .collect(Collectors.toList()); // Bir dəfə stream, nəticəni saxla

// İndi nəticə ilə istədiyini et
System.out.println(nəticə.size());
System.out.println(nəticə);
```

---

## Primitiv Streams

`Stream<Integer>` boxing/unboxing tələb edir. Primitiv stream-lər daha effektivdir:

```java
// IntStream, LongStream, DoubleStream — primitiv versiyalar
IntStream intStream = IntStream.of(1, 2, 3, 4, 5);
LongStream longStream = LongStream.of(100L, 200L, 300L);
DoubleStream doubleStream = DoubleStream.of(1.1, 2.2, 3.3);

// Effektivlik fərqi
// Stream<Integer> — hər element Integer obyektidir (heap-da)
// IntStream — primitiv int (stack-da) — daha sürətli, daha az yaddaş
```

### Primitiv Stream metodları

```java
IntStream rəqəmlər = IntStream.rangeClosed(1, 10);

// Statistik metodlar
System.out.println(rəqəmlər.sum());    // 55
// (Bir dəfə istifadədir, yenidən lazım olsa:)
IntStream.rangeClosed(1, 10).average(); // OptionalDouble
IntStream.rangeClosed(1, 10).min();     // OptionalInt
IntStream.rangeClosed(1, 10).max();     // OptionalInt
IntStream.rangeClosed(1, 10).count();   // long

// summaryStatistics() — hamısını birlikdə
IntSummaryStatistics statistika = IntStream.rangeClosed(1, 10)
                                            .summaryStatistics();
System.out.println("Say: " + statistika.getCount());   // 10
System.out.println("Cəm: " + statistika.getSum());     // 55
System.out.println("Orta: " + statistika.getAverage()); // 5.5
System.out.println("Min: " + statistika.getMin());     // 1
System.out.println("Maks: " + statistika.getMax());    // 10
```

### Primitiv ↔ Referans çevirmə

```java
// int → Integer (boxing)
Stream<Integer> boxed = IntStream.range(1, 6).boxed();

// Integer → int (unboxing)
IntStream unboxed = Stream.of(1, 2, 3).mapToInt(Integer::intValue);
// Yaxud daha qısa:
IntStream unboxed2 = Stream.of(1, 2, 3).mapToInt(i -> i);

// mapToInt / mapToLong / mapToDouble
Stream<String> sözlər = Stream.of("alma", "armud", "gilas");
IntStream uzunluqlar = sözlər.mapToInt(String::length);
System.out.println(uzunluqlar.sum()); // 4+5+5 = 14

// mapToObj — primitiv streamdən referans streamə
Stream<String> numericStrings = IntStream.range(1, 6)
                                          .mapToObj(Integer::toString);
```

---

## İntervyu Sualları

**S: Stream nədir? Kolleksiyadan fərqi nədir?**
C: Stream — məlumat emalı üçün ardıcıl əməliyyatlar boru xətti. Fərqlər: 1) məlumat saxlamır, 2) bir dəfə istifadə olunur, 3) lazy-dir (terminal op-a qədər işləmir), 4) mənbəni dəyişdirmir, 5) sonsuz ola bilər.

**S: Lazy evaluation nədir? Niyə faydalıdır?**
C: Ara əməliyyatlar terminal əməliyyat çağırılana qədər icra edilmir. Faydası: 1) lazımsız işdən qaçınılır, 2) short-circuit əməliyyatlar (findFirst, anyMatch) bütün elementləri emal etmir, 3) sonsuz streamlər mümkündür.

**S: Stream-i iki dəfə istifadə etmək olarmı?**
C: Xeyr. Terminal əməliyyat çağırıldıqdan sonra stream tükənir — yenidən istifadə `IllegalStateException` verir. Həll: hər dəfə yeni stream yaratmaq (`collection.stream()`) və ya `Supplier<Stream<T>>` istifadə etmək.

**S: `Stream<Integer>` ilə `IntStream` arasındakı fərq nədir?**
C: `Stream<Integer>` — boxing/unboxing tələb edir (Integer obyektlər heap-da). `IntStream` — primitiv int saxlayır, daha effektivdir. Əlavə olaraq `IntStream`-də `sum()`, `average()`, `summaryStatistics()` kimi hesab metodları var.

**S: `Stream.generate()` ilə `Stream.iterate()` fərqi nədir?**
C: `generate(Supplier)` — hər çağırışda Supplier-dan yeni dəyər alır, əvvəlki dəyərdən asılı deyil. `iterate(seed, f)` — əvvəlki dəyərə funksiya tətbiq edir (seed, f(seed), f(f(seed))...). Java 9-dan `iterate(seed, predicate, f)` ilə şərtli sonlu stream də yaratmaq olar.

**S: Stream pipeline-ı necə işləyir?**
C: Mənbə → Ara əməliyyatlar (zəncir, lazy) → Terminal əməliyyat. Terminal çağırılana qədər heç nə icra edilmir. Terminal çağırıldıqda hər element bütün pipeline-dan keçir (element-by-element, not stage-by-stage), short-circuit olana qədər.
