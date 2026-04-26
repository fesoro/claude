# 36 — Comparable və Comparator — Sıralama İnterfeyslərı

> **Seviyye:** Middle ⭐⭐


## Mündəricat
- [Comparable — Təbii Sıralama](#comparable--təbii-sıralama)
- [compareTo müqaviləsi](#compareto-müqaviləsi)
- [Comparator — Xarici Sıralama](#comparator--xarici-sıralama)
- [Comparator.comparing() və zəncir](#comparatorcomparing-və-zəncir)
- [reversed(), nullsFirst/nullsLast](#reversed-nullsfirstnullslast)
- [Stream ilə sıralama](#stream-ilə-sıralama)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Comparable — Təbii Sıralama

`Comparable<T>` — sinifin özünün "təbii sıralamasını" müəyyən edir. Bir sinif `Comparable` implement etdikdə, `Collections.sort()`, `TreeSet`, `TreeMap`, `Arrays.sort()` avtomatik işlər:

```java
// java.lang.Comparable interfeysi
public interface Comparable<T> {
    int compareTo(T other);
    // Qaytarma dəyərinin mənası:
    // mənfi  → this < other  (this əvvəl gəlir)
    // 0      → this == other (bərabərdir)
    // müsbət → this > other  (this sonra gəlir)
}
```

```java
import java.util.*;

public class TələbəComparable implements Comparable<TələbəComparable> {

    private final String ad;
    private final int bal;
    private final int kurs;

    public TələbəComparable(String ad, int bal, int kurs) {
        this.ad = ad;
        this.bal = bal;
        this.kurs = kurs;
    }

    // Təbii sıralama: bal azalan, sonra ad artan
    @Override
    public int compareTo(TələbəComparable other) {
        // Əvvəlcə bal ilə müqayisə (azalan — buna görə other.bal - this.bal)
        int balMüqayisəsi = Integer.compare(other.bal, this.bal); // azalan sıra
        if (balMüqayisəsi != 0) return balMüqayisəsi;

        // Ballar bərabərsə ad ilə (artan)
        return this.ad.compareTo(other.ad);
    }

    @Override
    public String toString() {
        return ad + "(" + bal + ")";
    }

    public static void main(String[] args) {
        List<TələbəComparable> tələbələr = new ArrayList<>();
        tələbələr.add(new TələbəComparable("Orkhan", 85, 3));
        tələbələr.add(new TələbəComparable("Anar", 92, 2));
        tələbələr.add(new TələbəComparable("Leyla", 85, 4));
        tələbələr.add(new TələbəComparable("Cavid", 78, 1));

        Collections.sort(tələbələr); // compareTo istifadə edir
        System.out.println(tələbələr);
        // [Anar(92), Leyla(85), Orkhan(85), Cavid(78)]
        // — bal azalan, eyni balda ad artan

        // TreeSet avtomatik sıralayır (Comparable lazımdır)
        TreeSet<TələbəComparable> set = new TreeSet<>(tələbələr);
        System.out.println("İlk: " + set.first()); // Anar(92)
        System.out.println("Son: " + set.last());  // Cavid(78)
    }
}
```

---

## compareTo müqaviləsi

```java
import java.util.*;

public class CompareToQaydaları {

    // ── Qaydalar ──
    // 1. x.compareTo(y) > 0 → y.compareTo(x) < 0 (antisimmetrik)
    // 2. x.compareTo(y) > 0 && y.compareTo(z) > 0 → x.compareTo(z) > 0 (tranzitiv)
    // 3. x.compareTo(y) == 0 → x.compareTo(z) == y.compareTo(z) (konsistentlik)
    // 4. Tövsiyə: x.compareTo(y) == 0 ↔ x.equals(y) (equals ilə uyğunluq)

    // ❌ YANLIŞ: Integer overflow riski olan compareTo
    static class YanlışComparable implements Comparable<YanlışComparable> {
        int dəyər;
        YanlışComparable(int dəyər) { this.dəyər = dəyər; }

        @Override
        public int compareTo(YanlışComparable other) {
            return this.dəyər - other.dəyər; // ❌ Integer.MIN_VALUE - 1 → overflow!
        }
    }

    // ✅ DOĞRU: Integer.compare() istifadəsi
    static class DüzgünComparable implements Comparable<DüzgünComparable> {
        int dəyər;
        DüzgünComparable(int dəyər) { this.dəyər = dəyər; }

        @Override
        public int compareTo(DüzgünComparable other) {
            return Integer.compare(this.dəyər, other.dəyər); // ✅ overflow yoxdur
        }
    }

    public static void main(String[] args) {
        // Overflow nümunəsi — yanliş compareTo
        YanlışComparable a = new YanlışComparable(Integer.MIN_VALUE);
        YanlışComparable b = new YanlışComparable(1);

        // Integer.MIN_VALUE - 1 = Integer.MAX_VALUE (overflow!)
        int nəticə = a.compareTo(b);
        System.out.println("Yanliş nəticə: " + nəticə); // müsbət — YANLIŞ!
        // Integer.MIN_VALUE < 1 olmalıdır amma compareTo müsbət qaytardı!

        // Düzgün
        DüzgünComparable c = new DüzgünComparable(Integer.MIN_VALUE);
        DüzgünComparable d = new DüzgünComparable(1);
        System.out.println("Düzgün nəticə: " + c.compareTo(d)); // mənfi ✅

        // Java daxili tiplaın compareTo metodları:
        System.out.println(Integer.compare(1, 2));           // -1
        System.out.println(Double.compare(1.5, 1.5));        // 0
        System.out.println("A".compareTo("B"));              // -1
        System.out.println(Boolean.compare(true, false));    // 1
    }
}
```

---

## Comparator — Xarici Sıralama

`Comparator<T>` — sinifin özündən kənarda (xarici) sıralama məntiqi müəyyən edir. `Comparable`-dan fərqli olaraq bir sinif üçün **çoxlu** Comparator yaratmaq mümkündür:

```java
import java.util.*;

public class ComparatorNümunə {

    record Məhsul(String ad, double qiymət, int stok, String kateqoriya) {}

    public static void main(String[] args) {
        List<Məhsul> məhsullar = new ArrayList<>(List.of(
            new Məhsul("Laptop", 1500.0, 10, "Elektronika"),
            new Məhsul("Telefon", 800.0, 25, "Elektronika"),
            new Məhsul("Kitab", 15.0, 100, "Təhsil"),
            new Məhsul("Qulaqliq", 50.0, 50, "Elektronika"),
            new Məhsul("Qələm", 2.0, 500, "Təhsil")
        ));

        // ── Adi Comparator ──
        Comparator<Məhsul> qiymətComparator = new Comparator<Məhsul>() {
            @Override
            public int compare(Məhsul m1, Məhsul m2) {
                return Double.compare(m1.qiymət(), m2.qiymət());
            }
        };

        // Lambda ilə eyni şey
        Comparator<Məhsul> qiymətLambda = (m1, m2) -> Double.compare(m1.qiymət(), m2.qiymət());

        // Comparator.comparing() — daha qısa
        Comparator<Məhsul> qiymətModern = Comparator.comparingDouble(Məhsul::qiymət);

        // Sıralama
        məhsullar.sort(qiymətModern);
        System.out.println("Qiymətə görə: " + məhsullar.stream()
            .map(m -> m.ad() + "(" + m.qiymət() + ")")
            .toList());
        // [Qələm(2.0), Kitab(15.0), Qulaqliq(50.0), Telefon(800.0), Laptop(1500.0)]

        // Azalan sıra
        məhsullar.sort(Comparator.comparingDouble(Məhsul::qiymət).reversed());
        System.out.println("Qiymət azalan: " + məhsullar.stream()
            .map(Məhsul::ad).toList());
        // [Laptop, Telefon, Qulaqliq, Kitab, Qələm]
    }
}
```

---

## Comparator.comparing() və zəncir

```java
import java.util.*;

public class ComparatorZəncir {

    record İşçi(String ad, String departament, int yaş, double maaş) {}

    public static void main(String[] args) {
        List<İşçi> işçilər = List.of(
            new İşçi("Orkhan", "IT", 28, 3000),
            new İşçi("Anar", "IT", 25, 3500),
            new İşçi("Leyla", "HR", 30, 2800),
            new İşçi("Cavid", "HR", 28, 2800),
            new İşçi("Günel", "IT", 28, 3000)
        );

        // ── Sadə comparing ──
        Comparator<İşçi> adaGörə = Comparator.comparing(İşçi::ad);
        Comparator<İşçi> yaşaGörə = Comparator.comparingInt(İşçi::yaş);
        Comparator<İşçi> maaşaGörə = Comparator.comparingDouble(İşçi::maaş);

        // ── thenComparing — zəncir ──
        // Departamenta görə, sonra maaşa görə azalan, sonra ada görə
        Comparator<İşçi> mürəkkəb = Comparator
            .comparing(İşçi::departament)          // 1. Departament artan
            .thenComparingDouble(İşçi::maaş).reversed() // ❌ Diqqət! reversed() hamısını əks edir

        // Düzgün mürəkkəb sıralama:
        Comparator<İşçi> düzgünMürəkkəb = Comparator
            .comparing(İşçi::departament)                  // 1. Departament artan
            .thenComparing(Comparator.comparingDouble(İşçi::maaş).reversed()) // 2. Maaş azalan
            .thenComparing(İşçi::ad);                      // 3. Ad artan

        List<İşçi> sıralı = işçilər.stream()
            .sorted(düzgünMürəkkəb)
            .toList();

        System.out.println("Mürəkkəb sıralama:");
        sıralı.forEach(i -> System.out.printf("  %-10s %-10s %3d %6.0f%n",
            i.ad(), i.departament(), i.yaş(), i.maaş()));
        // HR: Leyla(2800), Cavid(2800)  — Cavid əvvəl çünki "C" < "L"
        // IT: Anar(3500), Günel(3000), Orkhan(3000)  — maaş azalan, eyni maaşda ad artan

        // ── comparingInt/Long/Double — boxing olmadan ──
        Comparator<İşçi> intComparator = Comparator.comparingInt(İşçi::yaş);
        // Integer boxing etmir — int → Integer → unbox yoxdur
        // Böyük siyahılarda performans fərqi görünür

        // ── Comparator.naturalOrder() / reverseOrder() ──
        List<String> sözlər = new ArrayList<>(List.of("Java", "Python", "Go", "Rust"));
        sözlər.sort(Comparator.naturalOrder());  // A-Z
        System.out.println("Natural: " + sözlər); // [Go, Java, Python, Rust]
        sözlər.sort(Comparator.reverseOrder());   // Z-A
        System.out.println("Reverse: " + sözlər); // [Rust, Python, Java, Go]
    }
}
```

---

## reversed(), nullsFirst/nullsLast

```java
import java.util.*;
import java.util.stream.*;

public class ComparatorXüsusiyyətlər {

    record Məhsul(String ad, Double qiymət, String kateqoriya) {}

    public static void main(String[] args) {
        List<Məhsul> məhsullar = new ArrayList<>(List.of(
            new Məhsul("Laptop", 1500.0, "Elektronika"),
            new Məhsul("Kitab", null, "Təhsil"),      // null qiymət
            new Məhsul("Telefon", 800.0, null),       // null kateqoriya
            new Məhsul("Qələm", 2.0, "Təhsil"),
            new Məhsul("Qulaqliq", null, "Elektronika")
        ));

        // ── nullsFirst — null dəyərlər əvvəl ──
        Comparator<Məhsul> nullsəvvəl = Comparator.comparing(
            Məhsul::qiymət,
            Comparator.nullsFirst(Comparator.naturalOrder())
        );
        məhsullar.sort(nullsəvvəl);
        System.out.println("nullsFirst:");
        məhsullar.forEach(m -> System.out.println("  " + m.ad() + " → " + m.qiymət()));
        // Kitab → null
        // Qulaqliq → null
        // Qələm → 2.0
        // Telefon → 800.0
        // Laptop → 1500.0

        // ── nullsLast — null dəyərlər sona ──
        Comparator<Məhsul> nullsona = Comparator.comparing(
            Məhsul::qiymət,
            Comparator.nullsLast(Comparator.naturalOrder())
        );
        məhsullar.sort(nullsona);
        System.out.println("\nnullsLast:");
        məhsullar.forEach(m -> System.out.println("  " + m.ad() + " → " + m.qiymət()));
        // Qələm → 2.0
        // Telefon → 800.0
        // Laptop → 1500.0
        // Kitab → null
        // Qulaqliq → null

        // ── reversed() ──
        Comparator<Məhsul> azalan = Comparator
            .comparing(Məhsul::ad)
            .reversed();
        List<Məhsul> tersAd = məhsullar.stream().sorted(azalan).toList();
        System.out.println("\nAd azalan: " + tersAd.stream().map(Məhsul::ad).toList());

        // ── Null-safe getter ilə müqayisə ──
        Comparator<Məhsul> nullSafeKateqoriya = Comparator.comparing(
            Məhsul::kateqoriya,
            Comparator.nullsLast(String::compareTo)
        );
        məhsullar.sort(nullSafeKateqoriya);
        System.out.println("\nKateqoriya (null son):");
        məhsullar.forEach(m -> System.out.println("  " + m.ad() + " → " + m.kateqoriya()));
    }
}
```

---

## Stream ilə sıralama

```java
import java.util.*;
import java.util.stream.*;

public class StreamSıralama {

    record Şəhər(String ad, String ölkə, int əhali) {}

    public static void main(String[] args) {
        List<Şəhər> şəhərlər = List.of(
            new Şəhər("Bakı", "Azərbaycan", 2_300_000),
            new Şəhər("London", "İngiltərə", 9_000_000),
            new Şəhər("Paris", "Fransa", 2_100_000),
            new Şəhər("Berlin", "Almaniya", 3_600_000),
            new Şəhər("Gəncə", "Azərbaycan", 340_000),
            new Şəhər("Lyon", "Fransa", 500_000)
        );

        // ── sorted() — Comparable istifadə edir ──
        // List<String> sözlər = List.of("C", "A", "B");
        // sözlər.stream().sorted().toList(); // natural order

        // ── sorted(comparator) — Comparator istifadə edir ──

        // 1. Əhali azalan
        List<Şəhər> əhaliAzalan = şəhərlər.stream()
            .sorted(Comparator.comparingInt(Şəhər::əhali).reversed())
            .toList();
        System.out.println("Əhali azalan:");
        əhaliAzalan.forEach(s -> System.out.println("  " + s.ad() + ": " + s.əhali()));

        // 2. Ölkəyə görə, sonra əhaliyə görə azalan
        List<Şəhər> qruplaşdırılmış = şəhərlər.stream()
            .sorted(Comparator.comparing(Şəhər::ölkə)
                .thenComparing(Comparator.comparingInt(Şəhər::əhali).reversed()))
            .toList();
        System.out.println("\nÖlkə + Əhali azalan:");
        qruplaşdırılmış.forEach(s ->
            System.out.printf("  %-15s %-15s %,d%n", s.ölkə(), s.ad(), s.əhali()));

        // 3. Sıralanmış Map (ölkə → şəhər sayı)
        Map<String, Long> ölkəSayı = şəhərlər.stream()
            .collect(Collectors.groupingBy(Şəhər::ölkə, Collectors.counting()));

        ölkəSayı.entrySet().stream()
            .sorted(Map.Entry.<String, Long>comparingByValue().reversed())
            .forEach(e -> System.out.println(e.getKey() + ": " + e.getValue()));

        // 4. min() / max() — Comparator istifadə edir
        Optional<Şəhər> ənBöyük = şəhərlər.stream()
            .max(Comparator.comparingInt(Şəhər::əhali));
        System.out.println("Ən böyük: " + ənBöyük.map(Şəhər::ad).orElse("yoxdur"));

        // 5. TreeMap-ə yığım (sıralı map)
        TreeMap<String, Integer> şəhərMap = şəhərlər.stream()
            .collect(Collectors.toMap(
                Şəhər::ad,
                Şəhər::əhali,
                (e1, e2) -> e1,
                TreeMap::new // sıralı map
            ));
        System.out.println("İlk şəhər: " + şəhərMap.firstKey()); // Bakı (əlifba)
    }
}
```

---

## Comparable vs Comparator müqayisəsi

| Xüsusiyyət | Comparable | Comparator |
|------------|------------|------------|
| İnterfeys | `java.lang.Comparable<T>` | `java.util.Comparator<T>` |
| Metod | `compareTo(T other)` | `compare(T o1, T o2)` |
| Müəyyən edilir | Sinfin özündə | Ayrı sinif/lambda |
| Sıralama sayı | 1 (təbii sıralama) | Sonsuz (çox Comparator) |
| Üçüncü tərəf | Dəyişdirmək lazımdır | Lazım deyil |
| İstifadə | `Collections.sort(list)` | `Collections.sort(list, comp)` |
| | `TreeMap`, `TreeSet` | `TreeMap(comp)`, `TreeSet(comp)` |
| Functional Interface | Xeyr | **Bəli** (lambda yazmaq olar) |

```java
import java.util.*;

public class ComparableVsComparator {

    // Comparable — sinfin özündə (natural order)
    static class Sıra implements Comparable<Sıra> {
        int ədəd;
        Sıra(int ədəd) { this.ədəd = ədəd; }

        @Override
        public int compareTo(Sıra other) {
            return Integer.compare(this.ədəd, other.ədəd);
        }
    }

    // Comparator — kənardan (custom order)
    static final Comparator<Sıra> TERS_SIRA = (a, b) -> Integer.compare(b.ədəd, a.ədəd);
    static final Comparator<Sıra> CÜT_ƏVVƏL = (a, b) -> {
        boolean aCüt = a.ədəd % 2 == 0;
        boolean bCüt = b.ədəd % 2 == 0;
        if (aCüt == bCüt) return Integer.compare(a.ədəd, b.ədəd);
        return aCüt ? -1 : 1; // cüt əvvəl
    };

    public static void main(String[] args) {
        List<Sıra> siyahı = new ArrayList<>(
            List.of(new Sıra(3), new Sıra(1), new Sıra(4), new Sıra(2))
        );

        Collections.sort(siyahı); // compareTo istifadə edir → [1, 2, 3, 4]
        System.out.println("Natural: " + siyahı.stream().map(s -> s.ədəd).toList());

        siyahı.sort(TERS_SIRA);    // [4, 3, 2, 1]
        System.out.println("Ters: " + siyahı.stream().map(s -> s.ədəd).toList());

        List<Sıra> yeni = new ArrayList<>(
            List.of(new Sıra(3), new Sıra(1), new Sıra(4), new Sıra(2))
        );
        yeni.sort(CÜT_ƏVVƏL); // [2, 4, 1, 3]
        System.out.println("Cüt əvvəl: " + yeni.stream().map(s -> s.ədəd).toList());
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;

public class SıralamaYanlisDoğru {

    // ❌ YANLIŞ: subtraction-əsaslı compareTo — integer overflow riski
    record YanlışInt(int dəyər) implements Comparable<YanlışInt> {
        @Override
        public int compareTo(YanlışInt other) {
            return this.dəyər - other.dəyər; // ❌ overflow!
        }
    }

    // ✅ DOĞRU: Integer.compare() istifadəsi
    record DüzgünInt(int dəyər) implements Comparable<DüzgünInt> {
        @Override
        public int compareTo(DüzgünInt other) {
            return Integer.compare(this.dəyər, other.dəyər); // ✅
        }
    }

    // ❌ YANLIŞ: reversed() zəncirdə yanlış yerdə
    static <T> void yanlisReversed(List<String> list) {
        // Bütün sıralamanı tərsinə çevirir — yalnız ikinci kriter deyil!
        list.sort(Comparator.comparing(String::length)
            .thenComparing(Comparator.naturalOrder())
            .reversed()); // ❌ hər ikisini tərsinə çevirir!
    }

    // ✅ DOĞRU: Hər kriter üçün ayrıca reversed()
    static void dogruReversed(List<String> list) {
        list.sort(
            Comparator.comparingInt(String::length).reversed()  // uzunluq azalan
                .thenComparing(Comparator.naturalOrder())        // ad artan
        );
    }

    // ❌ YANLIŞ: null olan sahə ilə birbaşa comparing — NullPointerException
    record NullProblem(String ad, String kateqoriya) {}
    static void yanlisNull(List<NullProblem> list) {
        list.sort(Comparator.comparing(NullProblem::kateqoriya)); // kateqoriya null ola bilər!
    }

    // ✅ DOĞRU: nullsLast/nullsFirst istifadəsi
    static void dogruNull(List<NullProblem> list) {
        list.sort(Comparator.comparing(
            NullProblem::kateqoriya,
            Comparator.nullsLast(Comparator.naturalOrder())
        ));
    }

    // ❌ YANLIŞ: Comparator-da mutable state — thread-safe deyil
    static int counter = 0;
    static Comparator<String> yanlisStateful = (a, b) -> {
        counter++; // ❌ mutable state — paralel sıralamada problem!
        return a.compareTo(b);
    };

    // ✅ DOĞRU: Stateless comparator
    static final Comparator<String> dogruStateless = String::compareTo; // ✅

    public static void main(String[] args) {
        // Overflow nümunəsi
        List<YanlışInt> yanlış = new ArrayList<>();
        yanlış.add(new YanlışInt(Integer.MIN_VALUE));
        yanlış.add(new YanlışInt(1));
        Collections.sort(yanlış); // ❌ səhv sıralama (overflow)

        List<DüzgünInt> düzgün = new ArrayList<>();
        düzgün.add(new DüzgünInt(Integer.MIN_VALUE));
        düzgün.add(new DüzgünInt(1));
        Collections.sort(düzgün); // ✅ düzgün sıralama
        System.out.println(düzgün.get(0).dəyər()); // Integer.MIN_VALUE
    }
}
```

---

## İntervyu Sualları

**S1: Comparable və Comparator arasındakı əsas fərq nədir?**

`Comparable` — sinfin öz "təbii sıralamasını" müəyyən edir (`compareTo` sinfin içindədir). Bir sinif üçün yalnız bir təbii sıralama ola bilər. `Comparator` — xarici sıralama məntiqi (ayrı sinif/lambda). Bir sinif üçün sonsuz sayda Comparator yaratmaq olar.

**S2: compareTo() müqaviləsi nədir?**

1. Antisimmetrik: `x.compareTo(y) > 0` → `y.compareTo(x) < 0`. 2. Tranzitiv. 3. Konsistentlik. 4. Tövsiyə: `compareTo() == 0` ↔ `equals() == true` (Set, Map ilə uyğunluq üçün).

**S3: compareTo-da subtraction niyə problemlidir?**

`Integer.MIN_VALUE - 1` → `Integer.MAX_VALUE` (overflow). Buna görə `Integer.compare(a, b)` istifadə etmək lazımdır. Double üçün `Double.compare()`, Long üçün `Long.compare()`.

**S4: Comparator.comparing() ilə thenComparing() necə birlikdə işləyir?**

`Comparator.comparing(f1).thenComparing(f2)` — əvvəlcə f1-ə görə müqayisə edir. Bərabər olduqda f2-yə keçir. Zəncir uzunluğu məhdudsuz ola bilər.

**S5: nullsFirst vs nullsLast nə edir?**

`Comparator.nullsFirst(comparator)` — null olan elementlər sıralamanın əvvəlinə gedir. `nullsLast` — sona gedir. Bunlar olmadan null sahə ilə `comparing()` işlətmək `NullPointerException` atır.

**S6: Comparator functional interface-dirmi?**

Bəli, `@FunctionalInterface` annotasiyası var. Bir soyut metodu `compare()` var. Lambda ilə: `(a, b) -> Integer.compare(a.val, b.val)`. Method reference ilə: `Integer::compare`.

**S7: Stream.sorted() orijinal siyahını dəyişirmi?**

Xeyr. `stream().sorted()` yeni sıralı stream qaytarır, orijinal siyahı dəyişmir. `List.sort()` isə orijinal siyahını yerindəcə dəyişir.

**S8: TreeSet-də equals/hashCode uyğun gəlməyən Comparable olduqda nə baş verir?**

TreeSet `compareTo()` istifadə edir, `equals()`-i yox. Əgər `compareTo()` 0 qaytarırsa — element eyni sayılır (dublikat). `equals()` false olsa belə, TreeSet 0 qaytaran elementdən yalnız birini saxlayır. Bu, `Set` müqaviləsini pozur.
