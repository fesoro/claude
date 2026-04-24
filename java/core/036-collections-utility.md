# 036 — Collections və Arrays Utility Sinifləri
**Səviyyə:** Orta


## Mündəricat
- [Collections sinfi](#collections-sinfi)
- [sort, shuffle, reverse](#sort-shuffle-reverse)
- [min/max, frequency, disjoint](#minmax-frequency-disjoint)
- [unmodifiableList, synchronizedList](#unmodifiablelist-synchronizedlist)
- [nCopies, singleton, emptyList](#ncopies-singleton-emptylist)
- [Arrays sinfi](#arrays-sinfi)
- [Collections.emptyList vs List.of](#collectionsemptylist-vs-listof)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Collections sinfi

`java.util.Collections` — kolleksiyalar üzərində işləyən **statik metodlar** toplusudur. Bu sinifin bir instansiyası yaradılmır.

```java
import java.util.*;

public class CollectionsSinifinəGiriş {
    public static void main(String[] args) {
        // Collections heç vaxt instantiate edilmir
        // Bütün metodlar static-dir:
        // Collections.sort(list);
        // Collections.shuffle(list);
        // Collections.reverse(list);
        // ...

        List<Integer> siyahı = new ArrayList<>(Arrays.asList(3, 1, 4, 1, 5, 9, 2, 6));
        System.out.println("Orijinal: " + siyahı);
    }
}
```

---

## sort, shuffle, reverse

```java
import java.util.*;

public class SortShuffleReverse {
    public static void main(String[] args) {
        List<Integer> ədədlər = new ArrayList<>(Arrays.asList(5, 2, 8, 1, 9, 3));

        // ── sort ──
        Collections.sort(ədədlər); // natural order (Comparable)
        System.out.println("Sıralı: " + ədədlər); // [1, 2, 3, 5, 8, 9]

        // Comparator ilə sıralama
        Collections.sort(ədədlər, Comparator.reverseOrder());
        System.out.println("Tərsinə: " + ədədlər); // [9, 8, 5, 3, 2, 1]

        // String siyahısı
        List<String> sözlər = new ArrayList<>(Arrays.asList("Banana", "Alma", "Gilas"));
        Collections.sort(sözlər); // əlifba sırası
        System.out.println("Sözlər: " + sözlər); // [Alma, Banana, Gilas]

        // Case-insensitive sıralama
        Collections.sort(sözlər, String.CASE_INSENSITIVE_ORDER);

        // ── shuffle ──
        List<Integer> kart = new ArrayList<>(List.of(1,2,3,4,5,6,7,8,9,10));
        Collections.shuffle(kart);
        System.out.println("Qarışdırılmış: " + kart); // təsadüfi sıra

        // Sabit seed ilə — eyni nəticə
        Random rnd = new Random(42);
        Collections.shuffle(kart, rnd);
        System.out.println("Seed(42): " + kart); // həmişə eyni nəticə

        // ── reverse ──
        List<String> hərflər = new ArrayList<>(Arrays.asList("A", "B", "C", "D"));
        Collections.reverse(hərflər);
        System.out.println("Tərsinə: " + hərflər); // [D, C, B, A]

        // ── rotate ──
        List<Integer> rot = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5));
        Collections.rotate(rot, 2); // 2 mövqe sağa sür
        System.out.println("rotate(2): " + rot); // [4, 5, 1, 2, 3]

        Collections.rotate(rot, -1); // 1 mövqe sola
        System.out.println("rotate(-1): " + rot); // [5, 1, 2, 3, 4]

        // ── swap ──
        List<String> swap = new ArrayList<>(Arrays.asList("A", "B", "C", "D"));
        Collections.swap(swap, 0, 3); // 0 və 3 indekslərini dəyişdir
        System.out.println("swap(0,3): " + swap); // [D, B, C, A]

        // ── fill ──
        List<String> doldur = new ArrayList<>(Arrays.asList("A", "B", "C"));
        Collections.fill(doldur, "X"); // hamısını X ilə əvəz et
        System.out.println("fill(X): " + doldur); // [X, X, X]
    }
}
```

---

## min/max, frequency, disjoint

```java
import java.util.*;

public class MinMaxFrequency {
    public static void main(String[] args) {
        List<Integer> ədədlər = Arrays.asList(3, 1, 4, 1, 5, 9, 2, 6, 5, 3, 5);

        // ── min / max ──
        int minimum = Collections.min(ədədlər); // 1
        int maksimum = Collections.max(ədədlər); // 9
        System.out.println("Min: " + minimum + ", Max: " + maksimum);

        // Comparator ilə
        List<String> sözlər = Arrays.asList("Banana", "Alma", "Gilas", "Erik");
        String qısasSöz = Collections.min(sözlər, Comparator.comparingInt(String::length));
        String uzunSöz = Collections.max(sözlər, Comparator.comparingInt(String::length));
        System.out.println("Qısa: " + qısasSöz + ", Uzun: " + uzunSöz);
        // Qısa: Alma, Uzun: Banana (və ya Gilas — eyni uzunluqda)

        // ── frequency ──
        int beşinSayı = Collections.frequency(ədədlər, 5);
        System.out.println("5-in sayı: " + beşinSayı); // 3

        int birSayı = Collections.frequency(ədədlər, 1);
        System.out.println("1-in sayı: " + birSayı); // 2

        // ── disjoint — iki kolleksiya ümumi element paylaşırmı? ──
        List<Integer> a = Arrays.asList(1, 2, 3);
        List<Integer> b = Arrays.asList(4, 5, 6);
        List<Integer> c = Arrays.asList(3, 4, 5);

        System.out.println("a,b disjoint: " + Collections.disjoint(a, b)); // true (ümumi yoxdur)
        System.out.println("a,c disjoint: " + Collections.disjoint(a, c)); // false (3 var)

        // ── binarySearch ──
        List<Integer> sirali = new ArrayList<>(Arrays.asList(1, 2, 3, 5, 8, 9));
        int indeks = Collections.binarySearch(sirali, 5);
        System.out.println("5-in indeksi: " + indeks); // 3
        // ⚠️ Siyahı əvvəlcədən SIRALANMIŞ olmalıdır!

        int tapilmayan = Collections.binarySearch(sirali, 4);
        System.out.println("4 tapılmır: " + tapilmayan); // mənfi rəqəm (-(insertion point) - 1)
        // -4 qaytarır — 4 yerləşəcəyi mövqe = 3, -(3+1) = -4
    }
}
```

---

## unmodifiableList, synchronizedList

```java
import java.util.*;
import java.util.concurrent.*;

public class UnmodifiableSynchronized {
    public static void main(String[] args) {

        // ── unmodifiableList ──
        List<String> original = new ArrayList<>(Arrays.asList("A", "B", "C"));
        List<String> dəyişilməz = Collections.unmodifiableList(original);

        // Oxuma əməliyyatları işləyir
        System.out.println(dəyişilməz.get(0)); // "A"
        System.out.println(dəyişilməz.size());  // 3

        // Yazma cəhdi — UnsupportedOperationException
        try {
            dəyişilməz.add("D"); // ❌
        } catch (UnsupportedOperationException e) {
            System.out.println("Dəyişdirmək olmaz!");
        }

        // ⚠️ DİQQƏT: Orijinal dəyişirsə, view da dəyişir!
        original.add("D");
        System.out.println(dəyişilməz); // [A, B, C, D] — orijinal dəyişdi!
        // List.of() — həqiqi immutable, orijinala bağlı deyil:
        List<String> trulyImmutable = List.of("A", "B", "C"); // orijinal yoxdur

        // Bütün növlər üçün:
        Set<String> unmodSet = Collections.unmodifiableSet(new HashSet<>(Set.of("A","B")));
        Map<String,Integer> unmodMap = Collections.unmodifiableMap(new HashMap<>(Map.of("A",1)));
        Collection<String> unmodCol = Collections.unmodifiableCollection(original);
        SortedSet<String> unmodSortedSet = Collections.unmodifiableSortedSet(new TreeSet<>());
        SortedMap<String,Integer> unmodSortedMap = Collections.unmodifiableSortedMap(new TreeMap<>());

        // ── synchronizedList ──
        List<String> syncList = Collections.synchronizedList(new ArrayList<>());
        syncList.add("Thread1");
        syncList.add("Thread2");

        // ⚠️ İterasiya zamanı əl ilə kilid lazımdır!
        synchronized (syncList) { // unudsaq ConcurrentModificationException
            for (String s : syncList) {
                System.out.println(s);
            }
        }

        // Bütün növlər üçün:
        Set<String> syncSet = Collections.synchronizedSet(new HashSet<>());
        Map<String,Integer> syncMap = Collections.synchronizedMap(new HashMap<>());

        // ✅ Tövsiyə: synchronizedList əvəzinə CopyOnWriteArrayList
        // synchronizedMap əvəzinə ConcurrentHashMap
        List<String> betterSync = new CopyOnWriteArrayList<>();
        Map<String,Integer> betterSyncMap = new ConcurrentHashMap<>();
    }
}
```

---

## nCopies, singleton, emptyList

```java
import java.util.*;

public class FactoryMetodlar {
    public static void main(String[] args) {

        // ── nCopies — n ədəd eyni elementin dəyişilməz siyahısı ──
        List<String> beşAlma = Collections.nCopies(5, "Alma");
        System.out.println(beşAlma); // [Alma, Alma, Alma, Alma, Alma]
        System.out.println(beşAlma.size()); // 5

        // nCopies dəyişilməzdir
        try {
            beşAlma.add("Armud"); // UnsupportedOperationException
        } catch (UnsupportedOperationException e) {
            System.out.println("nCopies dəyişilməzdir");
        }

        // ArrayList-ə köçürmək lazımdırsa:
        List<Integer> sıfırlar = new ArrayList<>(Collections.nCopies(10, 0));
        sıfırlar.set(5, 42); // indi dəyişmək olar

        // ── singleton — tək elementli dəyişilməz kolleksiyalar ──
        List<String> tekList = Collections.singletonList("Yalnız mən");
        Set<Integer> tekSet = Collections.singleton(42);
        Map<String,Integer> tekMap = Collections.singletonMap("bir", 1);

        System.out.println(tekList); // [Yalnız mən]
        System.out.println(tekSet);  // [42]
        System.out.println(tekMap);  // {bir=1}

        // Dəyişilməzdir:
        try {
            tekList.add("başqa"); // UnsupportedOperationException
        } catch (UnsupportedOperationException e) {
            System.out.println("singleton dəyişilməzdir");
        }

        // ── emptyList / emptySet / emptyMap ──
        List<String> boşList = Collections.emptyList();
        Set<Integer> boşSet = Collections.emptySet();
        Map<String,Integer> boşMap = Collections.emptyMap();

        System.out.println(boşList.size()); // 0
        System.out.println(boşSet.isEmpty()); // true

        // Bunlar singleton — hər çağırışda eyni obyekt qaytarılır
        System.out.println(Collections.emptyList() == Collections.emptyList()); // true
        System.out.println(List.of() == List.of()); // false — List.of yeni instans yarada bilər

        // Tip parametri ilə:
        List<String> tipliBoş = Collections.<String>emptyList(); // generic type witness
    }
}
```

---

## Arrays sinfi

`java.util.Arrays` — massivlər üzərində işləyən statik metodlar toplusu:

```java
import java.util.*;
import java.util.stream.*;

public class ArraysSinfi {
    public static void main(String[] args) {

        // ── sort ──
        int[] ədədlər = {5, 2, 8, 1, 9, 3};
        Arrays.sort(ədədlər); // in-place, natural order
        System.out.println("Sıralı: " + Arrays.toString(ədədlər)); // [1, 2, 3, 5, 8, 9]

        // Aralıq sıralama
        int[] partialSort = {5, 2, 8, 1, 9, 3};
        Arrays.sort(partialSort, 1, 4); // [1, 4) indeksini sırala
        System.out.println("Aralıq sıralama: " + Arrays.toString(partialSort)); // [5, 1, 2, 8, 9, 3]

        // Object massivi — Comparator ilə
        String[] sözlər = {"Banana", "Alma", "Gilas", "Erik"};
        Arrays.sort(sözlər, Comparator.comparingInt(String::length));
        System.out.println("Uzunluğa görə: " + Arrays.toString(sözlər));
        // [Alma, Erik, Gilas, Banana] — parallelSort da var

        // Parallel sort (Java 8+) — böyük massivlər üçün
        int[] böyükMassiv = new int[100_000];
        Arrays.fill(böyükMassiv, 5);
        Arrays.parallelSort(böyükMassiv); // Fork/Join pool istifadə edir

        // ── binarySearch ──
        int[] sirali = {1, 2, 3, 5, 8, 9};
        int idx = Arrays.binarySearch(sirali, 5);
        System.out.println("5-in yeri: " + idx); // 3
        // ⚠️ Massiv SIRALANMIŞ olmalıdır!

        int notFound = Arrays.binarySearch(sirali, 4);
        System.out.println("4 yoxdur: " + notFound); // mənfi rəqəm

        // ── copyOf ──
        int[] original = {1, 2, 3, 4, 5};
        int[] kopyaKiçik = Arrays.copyOf(original, 3);    // [1, 2, 3]
        int[] kopyaBöyük = Arrays.copyOf(original, 7);    // [1, 2, 3, 4, 5, 0, 0]
        System.out.println("Kiçik kopya: " + Arrays.toString(kopyaKiçik));
        System.out.println("Böyük kopya: " + Arrays.toString(kopyaBöyük));

        // copyOfRange — aralıq kopya
        int[] araliqKopya = Arrays.copyOfRange(original, 1, 4); // [2, 3, 4]
        System.out.println("Aralıq kopya: " + Arrays.toString(araliqKopya));

        // ── fill ──
        int[] dolu = new int[5];
        Arrays.fill(dolu, 7);
        System.out.println("fill(7): " + Arrays.toString(dolu)); // [7, 7, 7, 7, 7]

        Arrays.fill(dolu, 1, 4, 9); // [1, 4) aralığı 9 ilə doldur
        System.out.println("fill[1,4](9): " + Arrays.toString(dolu)); // [7, 9, 9, 9, 7]

        // ── equals ──
        int[] arr1 = {1, 2, 3};
        int[] arr2 = {1, 2, 3};
        int[] arr3 = {1, 2, 4};

        System.out.println(Arrays.equals(arr1, arr2)); // true
        System.out.println(Arrays.equals(arr1, arr3)); // false
        // arr1.equals(arr2) — false! (Object.equals — referans müqayisəsi)

        // deepEquals — çoxölçülü massivlər
        int[][] matrix1 = {{1, 2}, {3, 4}};
        int[][] matrix2 = {{1, 2}, {3, 4}};
        System.out.println(Arrays.deepEquals(matrix1, matrix2)); // true

        // ── toString / deepToString ──
        System.out.println(Arrays.toString(arr1));         // [1, 2, 3]
        System.out.println(Arrays.deepToString(matrix1));  // [[1, 2], [3, 4]]

        // ── asList ──
        String[] massiv = {"A", "B", "C"};
        List<String> list = Arrays.asList(massiv); // fixed-size list!

        list.set(1, "X"); // dəyişmək olar
        // list.add("D"); // UnsupportedOperationException — ölçüsü dəyişilmir!
        System.out.println(list); // [A, X, C]

        // Dəyişən siyahı lazımdırsa:
        List<String> dəyişən = new ArrayList<>(Arrays.asList(massiv));
        dəyişən.add("D"); // ✅

        // ── stream ──
        IntStream stream = Arrays.stream(ədədlər);
        int cəm = stream.sum();
        System.out.println("Cəm: " + cəm);

        // Aralıq stream
        IntStream araliqStream = Arrays.stream(ədədlər, 1, 4);

        // String massivi stream
        Stream<String> strStream = Arrays.stream(sözlər);
        strStream.filter(s -> s.length() > 4).forEach(System.out::println);
    }
}
```

---

## Collections.emptyList vs List.of

```java
import java.util.*;

public class EmptyListVsListOf {
    public static void main(String[] args) {

        // ── Collections.emptyList() ──
        List<String> empty1 = Collections.emptyList();
        // + Singleton — hər çağırışda eyni obyekt (yaddaş qənaəti)
        // + Java 1.5+-dan mövcuddur
        // - Null-ə "zidd" alternativ kimi köhnə API-lərdə

        // ── List.of() (Java 9+) ──
        List<String> empty2 = List.of();
        // + Daha müasir, oxunaqlı
        // + İstənilən sayda element ilə: List.of("A", "B", "C")
        // + null element qəbul etmir (NPE atar)
        // - Java 9+ tələb edir

        // Hər ikisi dəyişilməzdir
        try { empty1.add("A"); } catch (UnsupportedOperationException e) { System.out.println("empty1 dəyişilməz"); }
        try { empty2.add("A"); } catch (UnsupportedOperationException e) { System.out.println("empty2 dəyişilməz"); }

        // Hər ikisi equals
        System.out.println(empty1.equals(empty2)); // true — hər ikisi boş List

        // ── Fərqlər ──
        // Collections.emptyList() null element dəstəkləyir (set/add yoxdur, amma...
        //   contains(null) false qaytarır — OK
        // List.of() null ilə heç bir əməliyyat işləmir:
        try {
            List.of("A", null); // NullPointerException!
        } catch (NullPointerException e) {
            System.out.println("List.of null qəbul etmir");
        }

        // ── List.of vs Arrays.asList ──
        List<String> asList = Arrays.asList("A", "B", "C");
        // Arrays.asList:
        // + set() dəstəkləyir (elementlər dəyişilə bilər)
        // - add/remove dəstəkləmir (fixed-size)
        // - null qəbul edir
        asList.set(0, "X"); // ✅
        try { asList.add("D"); } catch (UnsupportedOperationException e)
            { System.out.println("asList.add() olmaz"); }

        // List.of:
        // - heç bir dəyişiklik olmur (tam immutable)
        // - null qəbul etmir
        List<String> listOf = List.of("A", "B", "C");
        try { listOf.set(0, "X"); } catch (UnsupportedOperationException e)
            { System.out.println("List.of.set() olmaz"); }

        // ── Praktiki tövsiyə ──
        // Boş List lazımdırsa → List.of() (Java 9+) tövsiyə olunan
        // Sabit elementli List → List.of("A", "B", "C")
        // Sonradan dəyişə biləcək List → new ArrayList<>(List.of(...))
        // Thread üçün null lazy initialization → Collections.emptyList() (singleton olduğu üçün)

        // ── Set.of() və Map.of() ──
        Set<String> emptySet = Set.of();
        Set<String> fixedSet = Set.of("A", "B", "C");
        // ⚠️ Set.of() elementlərin sırasını qarantı vermir (iteration sırası dəyişə bilər)

        Map<String, Integer> emptyMap = Map.of();
        Map<String, Integer> fixedMap = Map.of("bir", 1, "iki", 2, "üç", 3);
        // Map.of() 10 cüt-ə qədər alır
        // Daha çox üçün: Map.ofEntries(Map.entry("a",1), Map.entry("b",2)...)

        Map<String, Integer> böyükMap = Map.ofEntries(
            Map.entry("A", 1),
            Map.entry("B", 2),
            Map.entry("C", 3)
            // istənilən sayda...
        );
        System.out.println(böyükMap);
    }
}
```

---

## Tam Xülasə Nümunəsi

```java
import java.util.*;
import java.util.stream.*;

public class UtilityXülasə {
    public static void main(String[] args) {

        // SSENARIY: İmtahan nəticələrini emal et
        List<Integer> ballar = new ArrayList<>(Arrays.asList(75, 42, 88, 55, 92, 68, 42, 88));

        // 1. Unikal balları sıralı al
        List<Integer> uniqlBallar = ballar.stream()
            .distinct()
            .sorted()
            .collect(Collectors.toList());
        System.out.println("Unikal sıralı ballar: " + uniqlBallar);

        // 2. Min/Max
        int ən_aşağı = Collections.min(ballar); // 42
        int ən_yuxarı = Collections.max(ballar); // 92
        System.out.println("Min: " + ən_aşağı + ", Max: " + ən_yuxarı);

        // 3. 88 balı neçə tələbə aldı?
        int həmBal88 = Collections.frequency(ballar, 88); // 2
        System.out.println("88 balı: " + həmBal88 + " tələbə");

        // 4. Siyahını qarışdır (imtahan variantı seçmək kimi)
        List<String> suallar = new ArrayList<>(Arrays.asList("S1","S2","S3","S4","S5"));
        Collections.shuffle(suallar, new Random(12345));
        System.out.println("Qarışdırılmış suallar: " + suallar);

        // 5. Yuxarıdan 3 ən yüksək bal
        List<Integer> ən_yüksək_3 = ballar.stream()
            .sorted(Collections.reverseOrder())
            .limit(3)
            .toList();
        System.out.println("Top 3: " + ən_yüksək_3); // [92, 88, 88]

        // 6. Bütün balları 2 dəfə artırmaq (nCopies misal)
        List<Integer> başlanğıcBallar = new ArrayList<>(Collections.nCopies(10, 0));
        System.out.println("Başlanğıc: " + başlanğıcBallar);

        // 7. Binary search
        List<Integer> sırali = new ArrayList<>(ballar);
        Collections.sort(sırali);
        int mövqe = Collections.binarySearch(sırali, 88);
        System.out.println("88-in mövqeyi: " + mövqe);

        // 8. Dəyişilməz siyahı qaytarma (API üçün)
        List<Integer> qaytarılacaq = Collections.unmodifiableList(ballar);
        // İstifadəçi dəyişə bilmir, amma orijinal list dəyişə bilər

        // Tam immutable üçün:
        List<Integer> tamImmutable = List.copyOf(ballar); // Java 10+
        System.out.println("Kopya (immutable): " + tamImmutable);
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;

public class UtilityYanlisDoğru {

    // ❌ YANLIŞ: Arrays.asList() dəyişən siyahı kimi istifadəsi
    void yanlisAsList() {
        List<String> list = Arrays.asList("A", "B", "C");
        // list.add("D"); // UnsupportedOperationException!
        // list.remove(0); // UnsupportedOperationException!
        // Amma set() işləyir — çaşdırıcı!
        list.set(0, "X"); // OK
    }

    // ✅ DOĞRU: Dəyişən list lazımdırsa new ArrayList<>() ilə əhat et
    void dogruAsList() {
        List<String> list = new ArrayList<>(Arrays.asList("A", "B", "C"));
        list.add("D"); // ✅
        list.remove(0); // ✅
    }

    // ❌ YANLIŞ: unmodifiableList-in orijinala bağlı olduğunu unutmaq
    List<String> yanlisUnmodifiable() {
        List<String> internal = new ArrayList<>(List.of("A", "B", "C"));
        List<String> exposed = Collections.unmodifiableList(internal);
        internal.add("D"); // Bu dəyişiklik exposed-a da əks olunur!
        return exposed;
    }

    // ✅ DOĞRU: List.copyOf() — həqiqi snapshot
    List<String> dogruCopyOf() {
        List<String> internal = new ArrayList<>(List.of("A", "B", "C"));
        return List.copyOf(internal); // tam kopyası — orijinaldan asılı deyil
    }

    // ❌ YANLIŞ: Sıralanmamış siyahıda binarySearch
    void yanlisBinarySearch() {
        List<Integer> list = new ArrayList<>(Arrays.asList(5, 2, 8, 1));
        int idx = Collections.binarySearch(list, 5); // ❌ sıralanmamış!
        // Nəticə qeyri-müəyyəndir!
    }

    // ✅ DOĞRU: Əvvəlcə sırala, sonra binarySearch
    void dogruBinarySearch() {
        List<Integer> list = new ArrayList<>(Arrays.asList(5, 2, 8, 1));
        Collections.sort(list); // əvvəlcə sırala
        int idx = Collections.binarySearch(list, 5); // ✅
        System.out.println("İndeks: " + idx);
    }

    // ❌ YANLIŞ: Massivləri == ilə müqayisə
    void yanlisMassivMüqayisəsi() {
        int[] arr1 = {1, 2, 3};
        int[] arr2 = {1, 2, 3};
        System.out.println(arr1 == arr2);     // false — referans müqayisəsi!
        System.out.println(arr1.equals(arr2)); // false — Object.equals, referans müqayisəsi!
    }

    // ✅ DOĞRU: Arrays.equals() istifadəsi
    void dogruMassivMüqayisəsi() {
        int[] arr1 = {1, 2, 3};
        int[] arr2 = {1, 2, 3};
        System.out.println(Arrays.equals(arr1, arr2)); // true ✅

        int[][] m1 = {{1,2},{3,4}};
        int[][] m2 = {{1,2},{3,4}};
        System.out.println(Arrays.deepEquals(m1, m2)); // true ✅ çoxölçülü
    }
}
```

---

## İntervyu Sualları

**S1: Collections.sort() ilə List.sort() arasındakı fərq nədir?**

`Collections.sort(list)` — static metod, `List.sort(comparator)` isə Java 8-dən əlavə edilmiş instance metod. `List.sort(null)` natural ordering istifadə edir. Hər ikisi TimSort alqoritmi istifadə edir, O(n log n).

**S2: Arrays.asList() niyə add() dəstəkləmir?**

`Arrays.asList()` — massivi "sarır" (wraps), yeni List yaratmır. Daxili massiv fixed-size-dır, ona görə `add`/`remove` dəstəklənmir. Lakin `set()` işləyir çünki massiv elementlərini dəyişmək olar. Həmçinin massiv dəyişiklikləri list-ə, list dəyişiklikləri massivə əks olunur.

**S3: Collections.unmodifiableList() ilə List.of() fərqi nədir?**

`unmodifiableList` — orijinal list-ə baxan view. Orijinal list dəyişsə, view da dəyişir. `List.of()` — həqiqi immutable, heç bir orijinal list yoxdur. `List.copyOf()` — mövcud kolleksiyanın tam immutable surəti.

**S4: Collections.emptyList() singleton nədir?**

`Collections.emptyList()` hər çağırışda eyni `EMPTY_LIST` sabit obyektini qaytarır. Bu yaddaş qənaətidir — yüzlərlə boş list lazım olduqda bir obyekt yetər.

**S5: binarySearch mənfi rəqəm qaytarırsa nə deməkdir?**

Element tapılmayıb. Qaytarılan dəyər `-(insertion_point) - 1`. Beləliklə `insertion_point = -(result) - 1`. Bu, elementin harada olacağını göstərir (sorted siyahıya insert üçün).

**S6: Collections.frequency() nə edir?**

Verilən elementın kolleksiyada neçə dəfə olduğunu sayır. `equals()` ilə müqayisə edir. O(n) mürəkkəbliyi var. Alternativ: `Collections.frequency(list, elem)` əvəzinə stream: `list.stream().filter(e -> e.equals(elem)).count()`.

**S7: Arrays.sort() vs Collections.sort() alqoritmi nədir?**

Hər ikisi **TimSort** istifadə edir (O(n log n), ən pis halda da). Primitive massivlər üçün `Arrays.sort()` **Dual-Pivot Quicksort** istifadə edir (ortalama daha sürətli). Object massivləri üçün TimSort (stabil sıralama).

**S8: Collections.disjoint() nə edir?**

İki kolleksiyada heç bir ümumi element olmadığını yoxlayır. True qaytarırsa — kəsişmə yoxdur (disjoint sets). İmplementasiya: kiçik kolleksiyadan hər elementi böyük kolleksiyada axtarır. Biri `Set`-dirsə O(n), ikisi `List`-dirsə O(n²).
