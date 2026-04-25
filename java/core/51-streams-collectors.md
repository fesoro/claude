# 51 — Streams — Collectors

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Collectors nədir?](#collectors-nədir)
2. [Əsas Collectors](#əsas-collectors)
3. [groupingBy()](#groupingby)
4. [partitioningBy()](#partitioningby)
5. [joining()](#joining)
6. [counting(), summingInt(), summarizingInt()](#statistik-collectors)
7. [mapping() və collectingAndThen()](#transformasiya-collectors)
8. [toMap()](#tomap)
9. [Custom Collector](#custom-collector)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Collectors nədir?

`Collector` — stream elementlərini bir konteynerə toplayan strategiya. `java.util.stream.Collectors` utility class-ı hazır implementasiyalar təqdim edir.

```java
import java.util.*;
import java.util.stream.*;
import java.util.function.*;

// Collector-un quruluşu
// Collector<T, A, R>
// T — stream element tipi
// A — akkumulyator tipi (ara saxlama)
// R — nəticə tipi

// Collectors.toList() — Collector<T, List<T>, List<T>> qaytarır
List<String> siyahı = Stream.of("a", "b", "c")
    .collect(Collectors.toList());
```

---

## Əsas Collectors

### toList(), toSet(), toUnmodifiableList()

```java
List<String> sözlər = List.of("alma", "armud", "gilas", "alma");

// Dəyişdirilə bilən siyahı
List<String> siyahı = sözlər.stream()
    .collect(Collectors.toList());
siyahı.add("yeni"); // Mümkündür

// Dəyişdirilə bilməyən siyahı (Java 10+)
List<String> immutable = sözlər.stream()
    .collect(Collectors.toUnmodifiableList());
// immutable.add("yeni"); // UnsupportedOperationException

// Java 16+ — toList() (dəyişdirilə bilməyən)
List<String> modern = sözlər.stream().toList();

// Set — dublikatlar silinir
Set<String> çoxluq = sözlər.stream()
    .collect(Collectors.toSet());
System.out.println(çoxluq); // [alma, armud, gilas] (sıra dəyişir)

// TreeSet — sıralı set
Set<String> sıralıSet = sözlər.stream()
    .collect(Collectors.toCollection(TreeSet::new));
System.out.println(sıralıSet); // [alma, armud, gilas] — alfabetik
```

---

## groupingBy()

`groupingBy()` — elementləri açar funksiyasına görə qruplaşdırır. `Map<K, List<V>>` qaytarır.

### Sadə groupingBy

```java
record İşçi(String ad, String şöbə, double maaş, int yaş) {}

List<İşçi> işçilər = List.of(
    new İşçi("Əli", "IT", 2500, 28),
    new İşçi("Aysel", "HR", 1800, 32),
    new İşçi("Murad", "IT", 3200, 35),
    new İşçi("Leyla", "Maliyyə", 2100, 27),
    new İşçi("Orxan", "IT", 2800, 30),
    new İşçi("Nərmin", "HR", 2200, 29)
);

// Şöbəyə görə qruplaşdır
Map<String, List<İşçi>> şöbəyəGörə = işçilər.stream()
    .collect(Collectors.groupingBy(İşçi::şöbə));

şöbəyəGörə.forEach((şöbə, işçiSiyahısı) -> {
    System.out.println(şöbə + ": " + işçiSiyahısı.stream()
        .map(İşçi::ad).collect(Collectors.joining(", ")));
});
// HR: Aysel, Nərmin
// IT: Əli, Murad, Orxan
// Maliyyə: Leyla
```

### groupingBy — downstream collector ilə

```java
// İkinci parametr — downstream collector (qrup içindəki əməliyyat)

// Şöbə başına işçi sayı
Map<String, Long> şöbəSayı = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.counting()  // Downstream: say
    ));
System.out.println(şöbəSayı); // {HR=2, IT=3, Maliyyə=1}

// Şöbə başına orta maaş
Map<String, Double> orta Maaş = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.averagingDouble(İşçi::maaş)
    ));
System.out.println(ortaMaaş); // {HR=2000.0, IT=2833.33, Maliyyə=2100.0}

// Şöbə başına yalnız adlar
Map<String, List<String>> şöbəAdları = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.mapping(İşçi::ad, Collectors.toList()) // Downstream: mapping
    ));
System.out.println(şöbəAdları);
// {HR=[Aysel, Nərmin], IT=[Əli, Murad, Orxan], Maliyyə=[Leyla]}

// Şöbə başına ən yüksək maaş
Map<String, Optional<İşçi>> ənYüksək = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.maxBy(Comparator.comparingDouble(İşçi::maaş))
    ));
ənYüksək.forEach((şöbə, opt) ->
    opt.ifPresent(i -> System.out.println(şöbə + ": " + i.ad())));
```

### Çox səviyyəli groupingBy

```java
// Şöbəyə, sonra yaş qrupuna görə qruplaşdır
Map<String, Map<String, List<İşçi>>> ikisəviyyəli = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,           // Əsas qruplaşdırma
        Collectors.groupingBy( // Daxili qruplaşdırma
            i -> i.yaş() < 30 ? "gənc" : "təcrübəli"
        )
    ));

ikisəviyyəli.forEach((şöbə, yaşQrupları) -> {
    System.out.println(şöbə + ":");
    yaşQrupları.forEach((qrup, insanlar) ->
        System.out.println("  " + qrup + ": " +
            insanlar.stream().map(İşçi::ad).collect(Collectors.joining(", "))));
});
/* Çıxış:
HR:
  gənc: Nərmin
  təcrübəli: Aysel
IT:
  gənc: Əli
  təcrübəli: Murad, Orxan
Maliyyə:
  gənc: Leyla
*/
```

### groupingBy — TreeMap ilə sıralı nəticə

```java
// Default HashMap-dir — sıra müəyyən deyil
// TreeMap istifadə edərək sıralı nəticə
Map<String, Long> sıralıNəticə = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        TreeMap::new,          // Map factory — TreeMap
        Collectors.counting()
    ));
// TreeMap — açarlara görə sıralıdır
System.out.println(sıralıNəticə); // {HR=2, IT=3, Maliyyə=1} — sıralı!
```

---

## partitioningBy()

`partitioningBy()` — elementləri iki qrupa böldür: `true` və `false`. `Map<Boolean, List<T>>` qaytarır.

```java
// Cüt/tək bölmə
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

Map<Boolean, List<Integer>> cütTək = rəqəmlər.stream()
    .collect(Collectors.partitioningBy(n -> n % 2 == 0));

System.out.println("Cütlər: " + cütTək.get(true));  // [2, 4, 6, 8, 10]
System.out.println("Təklər: " + cütTək.get(false)); // [1, 3, 5, 7, 9]

// İşçi nümunəsi — yüksək maaşlı/aşağı maaşlı
Map<Boolean, List<İşçi>> maaşBölgüsü = işçilər.stream()
    .collect(Collectors.partitioningBy(i -> i.maaş() >= 2500));

System.out.println("Yüksək maaşlılar:");
maaşBölgüsü.get(true).forEach(i ->
    System.out.println("  " + i.ad() + ": " + i.maaş()));

System.out.println("Aşağı maaşlılar:");
maaşBölgüsü.get(false).forEach(i ->
    System.out.println("  " + i.ad() + ": " + i.maaş()));

// partitioningBy + downstream
Map<Boolean, Long> sayBölgüsü = işçilər.stream()
    .collect(Collectors.partitioningBy(
        i -> i.maaş() >= 2500,
        Collectors.counting()  // Hər qrupdakı say
    ));
System.out.println("Yüksək: " + sayBölgüsü.get(true));  // 3
System.out.println("Aşağı: " + sayBölgüsü.get(false)); // 3
```

### partitioningBy vs groupingBy

```java
// partitioningBy — yalnız 2 qrup (true/false), boolean Predicate
// groupingBy — istənilən sayda qrup, istənilən açar funksiyası

// Həmişə iki açar mövcuddur (true, false) — boş olsa belə
Map<Boolean, List<String>> bölünmüş = Stream.<String>empty()
    .collect(Collectors.partitioningBy(s -> s.length() > 3));
System.out.println(bölünmüş.containsKey(true));  // true (boş siyahı)
System.out.println(bölünmüş.containsKey(false)); // true (boş siyahı)

// groupingBy — yalnız mövcud qruplar
Map<String, List<String>> qruplaşmış = Stream.<String>empty()
    .collect(Collectors.groupingBy(String::valueOf));
System.out.println(qruplaşmış.isEmpty()); // true (heç bir açar yoxdur)
```

---

## joining()

```java
List<String> şəhərlər = List.of("Bakı", "Gəncə", "Sumqayıt", "Lənkəran");

// Sadə birləşdirmə
String sadə = şəhərlər.stream()
    .collect(Collectors.joining());
System.out.println(sadə); // BakıGəncəSumqayıtLənkəran

// Ayırıcı ilə
String ayırıcılı = şəhərlər.stream()
    .collect(Collectors.joining(", "));
System.out.println(ayırıcılı); // Bakı, Gəncə, Sumqayıt, Lənkəran

// Ayırıcı, prefix, suffix ilə
String tam = şəhərlər.stream()
    .collect(Collectors.joining(", ", "[", "]"));
System.out.println(tam); // [Bakı, Gəncə, Sumqayıt, Lənkəran]

// SQL kimi istifadə
String sql = şəhərlər.stream()
    .map(s -> "'" + s + "'")
    .collect(Collectors.joining(", ", "IN (", ")"));
System.out.println(sql); // IN ('Bakı', 'Gəncə', 'Sumqayıt', 'Lənkəran')

// CSV sətiri
record Məhsul(String ad, double qiymət, int stok) {}
List<Məhsul> məhsullar = List.of(
    new Məhsul("Alma", 1.5, 100),
    new Məhsul("Armud", 2.0, 50),
    new Məhsul("Gilas", 3.5, 30)
);

String csv = məhsullar.stream()
    .map(m -> m.ad() + "," + m.qiymət() + "," + m.stok())
    .collect(Collectors.joining("\n", "ad,qiymət,stok\n", ""));
System.out.println(csv);
// ad,qiymət,stok
// Alma,1.5,100
// Armud,2.0,50
// Gilas,3.5,30
```

---

## Statistik Collectors

### counting()

```java
// Ümumi say
long say = şəhərlər.stream()
    .collect(Collectors.counting());

// groupingBy ilə
Map<Integer, Long> uzunluğaGörə = şəhərlər.stream()
    .collect(Collectors.groupingBy(
        String::length,
        Collectors.counting()
    ));
```

### summingInt(), summingLong(), summingDouble()

```java
// İşçilərin ümumi maaş fondu
int cəmMaaş = işçilər.stream()
    .collect(Collectors.summingInt(i -> (int) i.maaş()));

double cəmDouble = işçilər.stream()
    .collect(Collectors.summingDouble(İşçi::maaş));
System.out.println("Cəm maaş: " + cəmDouble); // 14600.0

// Şöbə başına cəm maaş
Map<String, Double> şöbəMaaş = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.summingDouble(İşçi::maaş)
    ));
```

### summarizingInt()

```java
// Bütün statistikalar bir anda
IntSummaryStatistics maaşStat = işçilər.stream()
    .collect(Collectors.summarizingInt(i -> (int) i.maaş()));

System.out.println("Say: " + maaşStat.getCount());     // 6
System.out.println("Cəm: " + maaşStat.getSum());       // 14600
System.out.println("Min: " + maaşStat.getMin());       // 1800
System.out.println("Maks: " + maaşStat.getMax());      // 3200
System.out.println("Orta: " + maaşStat.getAverage());  // 2433.33

// averagingInt, averagingDouble
double orta = işçilər.stream()
    .collect(Collectors.averagingDouble(İşçi::maaş));

// minBy, maxBy
Optional<İşçi> ənAzMaaşlı = işçilər.stream()
    .collect(Collectors.minBy(Comparator.comparingDouble(İşçi::maaş)));
ənAzMaaşlı.ifPresent(i -> System.out.println("Ən az: " + i.ad())); // Aysel
```

---

## Transformasiya Collectors

### mapping()

```java
// Toplamadan əvvəl elementləri çevir
// groupingBy-dan sonra obyekt əvəzinə ad almaq

// Şöbə → adlar siyahısı
Map<String, List<String>> şöbəAdları = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.mapping(İşçi::ad, Collectors.toList())
    ));
System.out.println(şöbəAdları);

// Şöbə → adlar Set-i (dublikat olmaz)
Map<String, Set<String>> şöbəAdSet = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.mapping(İşçi::ad, Collectors.toSet())
    ));

// Şöbə → adlar cümləsi
Map<String, String> şöbəCümlə = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.mapping(İşçi::ad, Collectors.joining(", "))
    ));
System.out.println(şöbəCümlə);
// {HR=Aysel, Nərmin, IT=Əli, Murad, Orxan, Maliyyə=Leyla}
```

### collectingAndThen()

```java
// Toplayandan sonra əlavə çevirmə

// Siyahı → immutable siyahı
List<String> immutable = işçilər.stream()
    .map(İşçi::ad)
    .collect(Collectors.collectingAndThen(
        Collectors.toList(),           // Əvvəlcə topla
        Collections::unmodifiableList  // Sonra çevir
    ));

// Set → List (sıralı)
List<String> sıralıAdlar = işçilər.stream()
    .map(İşçi::ad)
    .collect(Collectors.collectingAndThen(
        Collectors.toSet(),
        set -> {
            List<String> list = new ArrayList<>(set);
            Collections.sort(list);
            return list;
        }
    ));

// Count → boolean (5-dən çoxdur?)
boolean çoxMu = işçilər.stream()
    .collect(Collectors.collectingAndThen(
        Collectors.counting(),
        count -> count > 5
    ));
System.out.println("5-dən çox: " + çoxMu); // true (6 işçi var)

// groupingBy + collectingAndThen
Map<String, İşçi> şöbəBaşçısı = işçilər.stream()
    .collect(Collectors.groupingBy(
        İşçi::şöbə,
        Collectors.collectingAndThen(
            Collectors.maxBy(Comparator.comparingDouble(İşçi::maaş)),
            Optional::get // Optional-dan çıxar
        )
    ));
şöbəBaşçısı.forEach((şöbə, baş) ->
    System.out.println(şöbə + " başçısı: " + baş.ad()));
```

---

## toMap()

```java
// toMap(keyMapper, valueMapper)
Map<String, Double> adMaaş = işçilər.stream()
    .collect(Collectors.toMap(
        İşçi::ad,    // Açar: ad
        İşçi::maaş  // Dəyər: maaş
    ));
System.out.println(adMaaş);
// {Əli=2500.0, Aysel=1800.0, ...}

// Eyni açar olduqda — merge funksiyası lazımdır
List<İşçi> eyniAdlılar = List.of(
    new İşçi("Əli", "IT", 2500, 28),
    new İşçi("Əli", "HR", 1800, 32) // Eyni ad!
);

// YANLIŞ — eyni açar olduqda IllegalStateException
// eyniAdlılar.stream().collect(Collectors.toMap(İşçi::ad, İşçi::maaş));

// DOĞRU — merge funksiyası ilə
Map<String, Double> cəmMaaş = eyniAdlılar.stream()
    .collect(Collectors.toMap(
        İşçi::ad,
        İşçi::maaş,
        Double::sum // Eyni açar olduqda maaşları cəmlə
    ));

// 4-cü parametr — Map növü
Map<String, Double> treeMap = işçilər.stream()
    .collect(Collectors.toMap(
        İşçi::ad,
        İşçi::maaş,
        (m1, m2) -> m1, // Merge: birincini saxla
        TreeMap::new     // TreeMap istifadə et
    ));

// ID → Obyekt map-i (ən çox istifadə olunan pattern)
record Məhsul2(int id, String ad, double qiymət) {}
List<Məhsul2> məhsullar = List.of(
    new Məhsul2(1, "Alma", 1.5),
    new Məhsul2(2, "Armud", 2.0),
    new Məhsul2(3, "Gilas", 3.5)
);

Map<Integer, Məhsul2> idMap = məhsullar.stream()
    .collect(Collectors.toMap(Məhsul2::id, Function.identity()));

Məhsul2 məhsul = idMap.get(2);
System.out.println(məhsul.ad()); // Armud
```

---

## Custom Collector

`Collector.of()` ilə öz collector-unuzu yarada bilərsiniz.

```java
import java.util.stream.Collector;

// Orta dəyəri hesablayan custom collector
// Məqsəd: öyrənmə məqsədi ilə — praktikada averagingDouble istifadə et

Collector<Integer, int[], Double> ortaCollector = Collector.of(
    () -> new int[2],              // Supplier — akkumulyator yarat [cəm, say]
    (acc, val) -> {                // BiConsumer — akkumulasiya et
        acc[0] += val;             // Cəmə əlavə et
        acc[1]++;                  // Sayı artır
    },
    (acc1, acc2) -> {              // BinaryOperator — paralel birləşdirməsi
        acc1[0] += acc2[0];
        acc1[1] += acc2[1];
        return acc1;
    },
    acc -> acc[1] == 0 ? 0.0 : (double) acc[0] / acc[1] // Finisher
);

Double orta = Stream.of(1, 2, 3, 4, 5)
    .collect(ortaCollector);
System.out.println("Orta: " + orta); // 3.0
```

### Custom Collector — daha faydalı nümunə

```java
// Siyahını n-lik qruplara böl
public static <T> Collector<T, ?, List<List<T>>> bölüştür(int ölçü) {
    return Collector.of(
        () -> {                           // Akkumulyator: [mövcud qrup, bütün qruplar]
            List<Object> state = new ArrayList<>();
            state.add(new ArrayList<T>());  // Mövcud qrup
            state.add(new ArrayList<List<T>>()); // Bütün qruplar
            return state;
        },
        (state, element) -> {             // Akkumulasiya
            @SuppressWarnings("unchecked")
            List<T> cari = (List<T>) state.get(0);
            @SuppressWarnings("unchecked")
            List<List<T>> hamısı = (List<List<T>>) state.get(1);

            cari.add(element);
            if (cari.size() == ölçü) {
                hamısı.add(new ArrayList<>(cari));
                cari.clear();
            }
        },
        (s1, s2) -> {                    // Birləşdirmə (paralel üçün)
            throw new UnsupportedOperationException("Paralel dəstəklənmir");
        },
        state -> {                        // Finisher
            @SuppressWarnings("unchecked")
            List<T> cari = (List<T>) state.get(0);
            @SuppressWarnings("unchecked")
            List<List<T>> hamısı = (List<List<T>>) state.get(1);
            if (!cari.isEmpty()) hamısı.add(cari);
            return hamısı;
        }
    );
}

// İstifadə
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
List<List<Integer>> qruplar = rəqəmlər.stream()
    .collect(bölüştür(3));
System.out.println(qruplar); // [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10]]
```

---

## İntervyu Sualları

**S: `groupingBy()` ilə `partitioningBy()` arasındakı fərq nədir?**
C: `groupingBy()` — istənilən sayda qrup, istənilən açar funksiyası, `Map<K, List<V>>` qaytarır. `partitioningBy()` — yalnız 2 qrup (true/false), boolean Predicate tələb edir, `Map<Boolean, List<T>>` qaytarır. `partitioningBy()` həmişə hər iki açarı (true/false) ehtiva edir, boş olsa belə.

**S: `toMap()` istifadəsində duplicate key xətasını necə həll edirik?**
C: Üçüncü parametr kimi merge funksiyası verirək: `Collectors.toMap(keyMapper, valueMapper, (v1, v2) -> v1)`. Bu funksiya eyni açar üçün dəyərləri birləşdirir. Olmadan `IllegalStateException` atılır.

**S: `collectingAndThen()` nə üçün lazımdır?**
C: Toplayıcının nəticəsini əlavə çevirməyə imkan verir. Nümunə: `toList()` → `unmodifiableList()`, `counting()` → `boolean`, `maxBy()` → Optional-dan çıxarmaq. Bir əməliyyatla toplayıb çevirmək üçün istifadə edilir.

**S: Custom Collector yaratmaq üçün nə lazımdır?**
C: `Collector.of()` metoduna 4 şey lazımdır: 1) `Supplier` — akkumulyator yaratmaq, 2) `BiConsumer` — elementi akkumulyatora əlavə etmək, 3) `BinaryOperator` — paralel stream-lər üçün iki akkumulyatoru birləşdirmək, 4) `Function` (finisher) — akkumulyatoru nəticəyə çevirmək.

**S: `groupingBy()` nəticəsi hansı Map növüdür?**
C: Default olaraq `HashMap` — sıra zəmanəti yoxdur. Sıralı nəticə üçün üçüncü parametr kimi `TreeMap::new` veririk: `groupingBy(key, TreeMap::new, downstream)`.

**S: `mapping()` collector nə üçündür?**
C: `groupingBy()` ilə birlikdə istifadə edilir — qruplaşdırılmış elementlər üzərində əvvəlcə mapping, sonra digər collector. Məsələn, şöbə → işçi adları siyahısı üçün: `groupingBy(şöbə, mapping(İşçi::ad, toList()))`.
