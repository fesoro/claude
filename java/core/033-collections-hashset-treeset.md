# 033 — HashSet, TreeSet və LinkedHashSet
**Səviyyə:** Orta


## Mündəricat
- [Set semantikası](#set-semantikası)
- [HashSet — HashMap əsaslı](#hashset--hashmap-əsaslı)
- [equals/hashCode müqaviləsi Set üçün](#equalshashcode-müqaviləsi-set-üçün)
- [LinkedHashSet — daxiletmə sırası](#linkedhashset--daxiletmə-sırası)
- [TreeSet — Red-Black Tree](#treeset--red-black-tree)
- [Set əməliyyatları (Kəsişmə, Birləşmə, Fərq)](#set-əməliyyatları)
- [Nə vaxt hansını seçmeli?](#nə-vaxt-hansını-seçmeli)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Set semantikası

`Set<E>` — **dublikatsız** elementlər saxlayan kolleksiyadır. Eyni element iki dəfə əlavə edilə bilməz.

```java
import java.util.*;

public class SetSemantikası {
    public static void main(String[] args) {
        Set<String> set = new HashSet<>();

        // Unikal elementlər
        boolean e1 = set.add("Java");   // true — əlavə edildi
        boolean e2 = set.add("Python"); // true — əlavə edildi
        boolean e3 = set.add("Java");   // FALSE — artıq var, əlavə edilmədi!

        System.out.println(set);     // [Java, Python] — sıra dəyişə bilər
        System.out.println(set.size()); // 2, 3 deyil!

        // List-dəki dublikatları təmizləmək üçün Set-dən istifadə
        List<Integer> dublikatli = new ArrayList<>(Arrays.asList(1, 2, 3, 2, 1, 4, 3));
        Set<Integer> unikal = new LinkedHashSet<>(dublikatli); // sıranı qoruyur
        List<Integer> temizlenmis = new ArrayList<>(unikal);

        System.out.println("Əvvəl: " + dublikatli);   // [1, 2, 3, 2, 1, 4, 3]
        System.out.println("Sonra: " + temizlenmis);  // [1, 2, 3, 4]
    }
}
```

---

## HashSet — HashMap əsaslı

`HashSet` daxilində sadəcə bir `HashMap` saxlanır. Elementlər bu HashMap-in **key-ləri** kimi saxlanır, **dəyər** isə sabit bir `PRESENT` obyektidir:

```java
// HashSet-in daxili strukturu (JDK mənbəyi)
public class HashSetDaxili<E> {

    // Daxildə HashMap saxlayır
    private transient HashMap<E, Object> map;

    // Bütün key-lər üçün eyni dummy dəyər
    private static final Object PRESENT = new Object();

    public HashSetDaxili() {
        map = new HashMap<>();
    }

    public boolean add(E e) {
        // HashMap-ə key=e, value=PRESENT əlavə et
        return map.put(e, PRESENT) == null; // null dönürsə yeni element idi
    }

    public boolean remove(Object o) {
        return map.remove(o) == PRESENT;
    }

    public boolean contains(Object o) {
        return map.containsKey(o);
    }

    public int size() {
        return map.size();
    }
}
```

```java
import java.util.*;

public class HashSetNümunə {
    public static void main(String[] args) {
        Set<String> meyveler = new HashSet<>();

        // Əlavə etmə — O(1) ortalama
        meyveler.add("Alma");
        meyveler.add("Armud");
        meyveler.add("Gilas");
        meyveler.add("Alma"); // dublikat — əlavə edilmir

        System.out.println("Ölçü: " + meyveler.size()); // 3

        // Axtarış — O(1) ortalama
        System.out.println("Alma var? " + meyveler.contains("Alma"));   // true
        System.out.println("Üzüm var? " + meyveler.contains("Üzüm")); // false

        // Silmə — O(1) ortalama
        boolean silindi = meyveler.remove("Armud"); // true
        System.out.println("Silindi? " + silindi);

        // İterate — sıra qarantı deyil!
        for (String meyve : meyveler) {
            System.out.println(meyve); // sıra HashMap bucket sırası ilə
        }

        // Set-ə null əlavə edilə bilər (bir dəfə)
        meyveler.add(null);
        System.out.println("null var? " + meyveler.contains(null)); // true
        meyveler.remove(null);
    }
}
```

---

## equals/hashCode müqaviləsi Set üçün

Set elementlərinin düzgün işləməsi üçün `equals()` və `hashCode()` düzgün implement edilməlidir:

```java
import java.util.*;

public class SetEqualsHashCode {

    // ❌ YANLIŞ: equals/hashCode implement edilməyib
    static class YanlışTələbə {
        String ad;
        int şəhadətnaməNo;

        YanlışTələbə(String ad, int şəhadətnaməNo) {
            this.ad = ad;
            this.şəhadətnaməNo = şəhadətnaməNo;
        }
        // Object.equals() — referans müqayisəsi
        // Object.hashCode() — sistem adresi
    }

    // ✅ DOĞRU: hər ikisi implement edilib
    static class DüzgünTələbə {
        final String ad;
        final int şəhadətnaməNo;

        DüzgünTələbə(String ad, int şəhadətnaməNo) {
            this.ad = ad;
            this.şəhadətnaməNo = şəhadətnaməNo;
        }

        @Override
        public boolean equals(Object o) {
            if (this == o) return true;
            if (!(o instanceof DüzgünTələbə other)) return false;
            return şəhadətnaməNo == other.şəhadətnaməNo
                && Objects.equals(ad, other.ad);
        }

        @Override
        public int hashCode() {
            return Objects.hash(ad, şəhadətnaməNo);
        }

        @Override
        public String toString() {
            return ad + "#" + şəhadətnaməNo;
        }
    }

    // Java 16+ — Record avtomatik olaraq equals/hashCode yaradır
    record Tələbə(String ad, int şəhadətnaməNo) {}

    public static void main(String[] args) {
        // ❌ YanlışTələbə ilə Set düzgün işləmir
        Set<YanlışTələbə> yanlışSet = new HashSet<>();
        yanlışSet.add(new YanlışTələbə("Orkhan", 1001));
        yanlışSet.add(new YanlışTələbə("Orkhan", 1001)); // dublikat? XEYR — fərqli referans!
        System.out.println("Yanlış Set ölçüsü: " + yanlışSet.size()); // 2 — YANLIŞ!

        // ✅ DüzgünTələbə ilə Set düzgün işləyir
        Set<DüzgünTələbə> düzgünSet = new HashSet<>();
        düzgünSet.add(new DüzgünTələbə("Orkhan", 1001));
        düzgünSet.add(new DüzgünTələbə("Orkhan", 1001)); // dublikat — əlavə edilmir
        System.out.println("Düzgün Set ölçüsü: " + düzgünSet.size()); // 1 ✅

        // ✅ Record ilə
        Set<Tələbə> recordSet = new HashSet<>();
        recordSet.add(new Tələbə("Orkhan", 1001));
        recordSet.add(new Tələbə("Orkhan", 1001));
        System.out.println("Record Set ölçüsü: " + recordSet.size()); // 1 ✅

        // Axtarış da düzgün işləyir
        System.out.println(düzgünSet.contains(new DüzgünTələbə("Orkhan", 1001))); // true ✅
    }
}
```

---

## LinkedHashSet — daxiletmə sırası

`LinkedHashSet` daxilində `LinkedHashMap` saxlayır. Elementlər daxiletmə sırasında qalır:

```java
import java.util.*;

public class LinkedHashSetNümunə {
    public static void main(String[] args) {
        // HashSet — sıra qarantı yoxdur
        Set<String> hashSet = new HashSet<>();
        hashSet.add("C");
        hashSet.add("A");
        hashSet.add("B");
        System.out.println("HashSet: " + hashSet); // [A, B, C] — sıra HashMap bucket-ından asılı

        // LinkedHashSet — daxiletmə sırası qorunur
        Set<String> linkedSet = new LinkedHashSet<>();
        linkedSet.add("C");
        linkedSet.add("A");
        linkedSet.add("B");
        System.out.println("LinkedHashSet: " + linkedSet); // [C, A, B] — daxiletmə sırası ✅

        // Dublikat əlavə edilsə sıra dəyişmir
        linkedSet.add("A"); // artıq var — əlavə edilmir, sıra dəyişmir
        System.out.println("A əlavə sonra: " + linkedSet); // [C, A, B]

        // Praktiki istifadə: dublikatları sil, sıranı qoru
        List<String> log = Arrays.asList("login", "action1", "login", "action2", "action1");
        Set<String> uniqlLog = new LinkedHashSet<>(log);
        System.out.println("Unikal log: " + uniqlLog); // [login, action1, action2]

        // Performans: HashSet ilə eyni — O(1) add/contains/remove
        long t = System.nanoTime();
        Set<Integer> lhs = new LinkedHashSet<>(100_000);
        for (int i = 0; i < 100_000; i++) lhs.add(i);
        System.out.println("LinkedHashSet fill: " + (System.nanoTime() - t) / 1_000_000 + "ms");
    }
}
```

---

## TreeSet — Red-Black Tree

`TreeSet` daxilində `TreeMap` saxlayır. Elementlər həmişə sıralı qaydada saxlanır:

```java
import java.util.*;

public class TreeSetNümunə {
    public static void main(String[] args) {
        // Natural ordering (Comparable)
        TreeSet<Integer> ededler = new TreeSet<>();
        ededler.add(5);
        ededler.add(2);
        ededler.add(8);
        ededler.add(1);
        ededler.add(9);

        System.out.println(ededler); // [1, 2, 5, 8, 9] — avtomatik sıralı!

        // NavigableSet metodları
        System.out.println("İlk: " + ededler.first());      // 1
        System.out.println("Son: " + ededler.last());       // 9
        System.out.println("5-dən aşağı: " + ededler.headSet(5));    // [1, 2]
        System.out.println("5 və yuxarı: " + ededler.tailSet(5));    // [5, 8, 9]
        System.out.println("2-8 arası: " + ededler.subSet(2, 8));   // [2, 5]
        System.out.println("[2,8] arası: " + ededler.subSet(2, true, 8, true)); // [2, 5, 8]

        System.out.println("5-ə ceiling: " + ededler.ceiling(4));   // 5
        System.out.println("5-ə floor: " + ededler.floor(6));       // 5
        System.out.println("5-dən higher: " + ededler.higher(5));   // 8
        System.out.println("5-dən lower: " + ededler.lower(5));     // 2

        // Ters sıra
        System.out.println("Ters: " + ededler.descendingSet()); // [9, 8, 5, 2, 1]

        // Xüsusi Comparator — uzunluğa görə sıralama
        TreeSet<String> sözler = new TreeSet<>(
            Comparator.comparingInt(String::length).thenComparing(Comparator.naturalOrder())
        );
        sözler.add("Java");
        sözler.add("Go");
        sözler.add("Python");
        sözler.add("C");
        sözler.add("Rust");
        System.out.println(sözler); // [C, Go, Java, Rust, Python]

        // ⚠️ TreeSet-də null əlavə edilə bilmir!
        try {
            ededler.add(null);
        } catch (NullPointerException e) {
            System.out.println("Null əlavə edilə bilmir!"); // bu baş verir
        }
    }
}
```

---

## Set əməliyyatları

Set riyazi əməliyyatlarını dəstəkləyir — **union (birləşmə), intersection (kəsişmə), difference (fərq)**:

```java
import java.util.*;
import java.util.stream.*;

public class SetƏməliyyatları {
    public static void main(String[] args) {
        Set<Integer> A = new HashSet<>(Set.of(1, 2, 3, 4, 5));
        Set<Integer> B = new HashSet<>(Set.of(3, 4, 5, 6, 7));

        // ── Birləşmə (Union): A ∪ B ──
        Set<Integer> union = new HashSet<>(A);
        union.addAll(B); // A-nın kopyasına B-ni əlavə et
        System.out.println("A ∪ B = " + union); // [1, 2, 3, 4, 5, 6, 7]

        // Stream ilə birləşmə
        Set<Integer> unionStream = Stream.concat(A.stream(), B.stream())
            .collect(Collectors.toSet());

        // ── Kəsişmə (Intersection): A ∩ B ──
        Set<Integer> intersection = new HashSet<>(A);
        intersection.retainAll(B); // B-də olmayanları sil
        System.out.println("A ∩ B = " + intersection); // [3, 4, 5]

        // Stream ilə kəsişmə
        Set<Integer> intersectionStream = A.stream()
            .filter(B::contains)
            .collect(Collectors.toSet());

        // ── Fərq (Difference): A \ B ──
        Set<Integer> differenceAB = new HashSet<>(A);
        differenceAB.removeAll(B); // B-dəkiləri A-dan çıxart
        System.out.println("A \\ B = " + differenceAB); // [1, 2]

        Set<Integer> differenceBA = new HashSet<>(B);
        differenceBA.removeAll(A);
        System.out.println("B \\ A = " + differenceBA); // [6, 7]

        // ── Simmetrik Fərq (Symmetric Difference): A △ B ──
        // (A ∪ B) \ (A ∩ B) — yalnız birində olanlar
        Set<Integer> symDiff = new HashSet<>(union);
        symDiff.removeAll(intersection);
        System.out.println("A △ B = " + symDiff); // [1, 2, 6, 7]

        // ── Alt çoxluq yoxlama (Subset): A ⊆ B ──
        Set<Integer> C = Set.of(3, 4);
        System.out.println("C ⊆ A: " + A.containsAll(C)); // true — C, A-nın alt çoxluğudur

        // ── Disjoint yoxlama (ümumi element yoxdur) ──
        Set<Integer> D = Set.of(10, 11, 12);
        System.out.println("A ∩ D boşdur: " + Collections.disjoint(A, D)); // true

        // Praktiki nümunə: icazə sistemi
        Set<String> tələbOlunanIcazeler = Set.of("READ", "WRITE", "DELETE");
        Set<String> istifadəçiİcazələri = new HashSet<>(Set.of("READ", "WRITE", "ADMIN"));

        boolean hamısıVar = istifadəçiİcazələri.containsAll(tələbOlunanIcazeler);
        System.out.println("Bütün icazələr var: " + hamısıVar); // false (DELETE yoxdur)

        Set<Integer> eksikIcazeler = new HashSet<>(tələbOlunanIcazeler);
        // eksikIcazeler.removeAll(istifadəçiİcazələri);
        // System.out.println("Çatışmayan: " + eksikIcazeler); // [DELETE]
    }
}
```

---

## Set-in Praktiki İstifadə Nümunələri

```java
import java.util.*;
import java.util.stream.*;

public class SetPraktikNümunə {

    // 1. Söz sayacı — unikal sözlər
    static long uniqlSözSayı(String mətn) {
        return Arrays.stream(mətn.split("\\s+"))
            .map(String::toLowerCase)
            .collect(Collectors.toSet())
            .size();
    }

    // 2. Qrafda ziyarət edilmiş node-lar
    static void bfs(Map<Integer, List<Integer>> qraf, int başlanğıc) {
        Set<Integer> ziyarətEdilmiş = new HashSet<>();
        Queue<Integer> növbə = new LinkedList<>();

        növbə.offer(başlanğıc);
        ziyarətEdilmiş.add(başlanğıc);

        while (!növbə.isEmpty()) {
            int node = növbə.poll();
            System.out.print(node + " ");

            for (int qonşu : qraf.getOrDefault(node, List.of())) {
                if (ziyarətEdilmiş.add(qonşu)) { // əlavə etdisə (yeni node)
                    növbə.offer(qonşu);
                }
            }
        }
    }

    // 3. İki list arasında ümumi elementlər
    static <T> Set<T> ümumi(List<T> list1, List<T> list2) {
        Set<T> set1 = new HashSet<>(list1);
        return list2.stream()
            .filter(set1::contains) // O(1) per check — O(n) total
            .collect(Collectors.toSet());
        // ❌ YANLIŞ: list1.contains() — O(n) per check → O(n²) total
    }

    // 4. Anagram yoxlama
    static boolean anagramdır(String s1, String s2) {
        if (s1.length() != s2.length()) return false;

        Map<Character, Integer> sayac = new HashMap<>();
        for (char c : s1.toCharArray()) sayac.merge(c, 1, Integer::sum);
        for (char c : s2.toCharArray()) {
            sayac.merge(c, -1, Integer::sum);
            if (sayac.get(c) < 0) return false;
        }
        return true;
    }

    public static void main(String[] args) {
        System.out.println("Unikal söz sayı: " + uniqlSözSayı("java java python go java"));
        // 3

        System.out.println("Anagram: " + anagramdır("listen", "silent")); // true

        List<Integer> l1 = List.of(1, 2, 3, 4, 5);
        List<Integer> l2 = List.of(3, 4, 5, 6, 7);
        System.out.println("Ümumi: " + ümumi(l1, l2)); // [3, 4, 5]
    }
}
```

---

## Nə vaxt hansını seçmeli?

```java
import java.util.*;

public class SetSecimi {

    // HashSet seç:
    // ✅ Sıra önəmsizdir
    // ✅ O(1) add/contains/remove lazımdır
    // ✅ Null element lazım ola bilər
    // Nümunə: ziyarət edilmiş node-lar, cache, dublikat yoxlama
    Set<String> visitedUrls = new HashSet<>();

    // LinkedHashSet seç:
    // ✅ Daxiletmə sırası qorunmalıdır
    // ✅ O(1) performans lazımdır (HashSet kimi)
    // Nümunə: dublikatları sil amma sıranı qoru
    Set<String> uniqueLog = new LinkedHashSet<>();

    // TreeSet seç:
    // ✅ Elementlər sıralı olmalıdır
    // ✅ firstKey/lastKey/ceiling/floor lazımdır
    // ✅ Aralıq sorğular (subSet/headSet/tailSet) lazımdır
    // ❌ null element saxlaya bilmir
    // Nümunə: sıralı unikal sözlər, prioritet siyahı
    Set<Integer> sortedScores = new TreeSet<>();

    void nümunə() {
        // Dublikat yoxlama — HashSet ən sürətli
        List<String> items = List.of("A", "B", "A", "C");
        boolean dublikatVar = items.stream()
            .anyMatch(item -> !new HashSet<String>().add(item));
        // Daha yaxşı versiya:
        Set<String> görülmüş = new HashSet<>();
        boolean hasDup = items.stream().anyMatch(s -> !görülmüş.add(s));

        // Sıralı sözlük
        Set<String> lüğət = new TreeSet<>(String.CASE_INSENSITIVE_ORDER);
        lüğət.add("Banana");
        lüğət.add("apple");
        lüğət.add("Cherry");
        System.out.println(lüğət); // [apple, Banana, Cherry] — case-insensitive sıra
    }
}
```

| Xüsusiyyət | HashSet | LinkedHashSet | TreeSet |
|------------|---------|---------------|---------|
| Əsas struktur | HashMap | LinkedHashMap | TreeMap |
| add/contains/remove | O(1) | O(1) | O(log n) |
| Sıralama | Yox | Daxiletmə sırası | Natural/Comparator |
| null element | 1 ədəd | 1 ədəd | **Yox** |
| firstElement() | O(n) | O(n) | **O(log n)** |
| ceiling/floor | Yox | Yox | **O(log n)** |
| Yaddaş | Az | Orta (siyahı pointer) | Orta (tree node) |

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;
import java.util.stream.*;

public class YanlisDoğru {

    // ❌ YANLIŞ: Dublikat yoxlamaq üçün List.contains() istifadəsi — O(n²)
    List<String> yanlisUniql(List<String> items) {
        List<String> nəticə = new ArrayList<>();
        for (String item : items) {
            if (!nəticə.contains(item)) { // her defe O(n) axtarış — O(n²) total!
                nəticə.add(item);
            }
        }
        return nəticə;
    }

    // ✅ DOĞRU: Set istifadəsi — O(n)
    List<String> dogruUniql(List<String> items) {
        return new ArrayList<>(new LinkedHashSet<>(items)); // O(n), sıranı qoruyur
    }

    // ❌ YANLIŞ: Set-i indekslə dövrə etmək cəhdi
    void yanlisIndex(Set<String> set) {
        // for (int i = 0; i < set.size(); i++) {
        //     set.get(i); // KOMPILASIYA XƏTASI — Set-in get(i) metodu yoxdur!
        // }
    }

    // ✅ DOĞRU: for-each və ya Iterator istifadəsi
    void dogruIndex(Set<String> set) {
        for (String elem : set) { // for-each
            System.out.println(elem);
        }
        // Və ya stream
        set.stream().forEach(System.out::println);
    }

    // ❌ YANLIŞ: iki listin kəsişməsini tapan zaman hər ikisini List saxlamaq
    List<Integer> yanlisKəsişmə(List<Integer> l1, List<Integer> l2) {
        return l1.stream()
            .filter(l2::contains) // l2.contains() — O(n) — total O(n²)!
            .collect(Collectors.toList());
    }

    // ✅ DOĞRU: Bir-ni Set-ə çevirmək
    List<Integer> dogruKəsişmə(List<Integer> l1, List<Integer> l2) {
        Set<Integer> set2 = new HashSet<>(l2); // O(n)
        return l1.stream()
            .filter(set2::contains) // O(1) per check — total O(n)
            .collect(Collectors.toList());
    }

    // ❌ YANLIŞ: Mutable sinifin Set-ə qoyulması
    void yanlisMutableSet() {
        Set<List<Integer>> set = new HashSet<>();
        List<Integer> list = new ArrayList<>(List.of(1, 2, 3));
        set.add(list);
        list.add(4); // hashCode dəyişdi → set xarab oldu!
        System.out.println(set.contains(list)); // false — öz elementini tapa bilmir!
    }

    // ✅ DOĞRU: Immutable elementlər Set-ə qoy
    void dogruImmutableSet() {
        Set<List<Integer>> set = new HashSet<>();
        set.add(List.of(1, 2, 3)); // List.of() immutable-dır ✅
        System.out.println(set.contains(List.of(1, 2, 3))); // true ✅
    }
}
```

---

## İntervyu Sualları

**S1: HashSet daxilində nə saxlanır?**

`HashMap<E, Object>` saxlanır. Elementlər HashMap-in key-ləridirlər, dəyər isə `static final Object PRESENT = new Object()` adlı sabit dummy obyektdir.

**S2: Set elementinin dublikat olub-olmadığını necə yoxlayır?**

`add(e)` çağırıldığında: 1) `e.hashCode()` hesablanır, 2) bucket tapılır, 3) eyni bucket-dakı elementlərlə `equals()` müqayisə edilir. `equals()` true qaytararsa dublikatdır.

**S3: TreeSet-də null element saxlamaq mümkündürmü?**

Xeyr. TreeSet elementləri müqayisə etmək üçün `compareTo()` istifadə edir. `null.compareTo(x)` `NullPointerException` atır. Lakin `Comparator.nullsFirst()` ilə xüsusi Comparator versə null saxlamaq mümkündür.

```java
TreeSet<String> set = new TreeSet<>(Comparator.nullsFirst(Comparator.naturalOrder()));
set.add(null); // İndi işləyir!
set.add("Java");
System.out.println(set); // [null, Java]
```

**S4: LinkedHashSet HashSet-dən nə ilə fərqlənir?**

Daxiletmə sırasını qoruyur. Daxilindəki `LinkedHashMap` əlavə olaraq ikiqat bağlı siyahı saxlayır. Performans HashSet ilə eynidir — O(1), amma bir az daha çox yaddaş istifadə edir.

**S5: retainAll() nə edir?**

Cari Set-dən parametr kolleksiyasında olmayan bütün elementləri silir. Effektiv olaraq kəsişmə (intersection) əməliyyatıdır.

**S6: Set-ə eyni elementin iki dəfə qoyulması nə qaytarır?**

`add()` metodu `boolean` qaytarır: əgər element yeni idi — `true`, əgər artıq var idi — `false`. Element əlavə edilmir.

**S7: HashSet thread-safe-dirmi?**

Xeyr. Çox-threadli mühit üçün `Collections.synchronizedSet(new HashSet<>())` və ya `ConcurrentHashMap.newKeySet()` istifadə edin.

**S8: EnumSet nədir?**

`EnumSet` — Enum növləri üçün yüksək performanslı Set implementasiyası. Daxilində bit vektoru (long) saxlayır — hər enum sabitini bir bit kimi. Çox sürətlidir amma yalnız Enum tipləri ilə işləyir.

```java
enum İzin { READ, WRITE, DELETE, ADMIN }
Set<İzin> icazeler = EnumSet.of(İzin.READ, İzin.WRITE);
Set<İzin> hamısı = EnumSet.allOf(İzin.class);
Set<İzin> heçbiri = EnumSet.noneOf(İzin.class);
```
