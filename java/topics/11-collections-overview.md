# 11. Java Collections Framework — Ümumi Baxış

## Mündəricat
- [Collections Framework nədir?](#collections-framework-nədir)
- [Hierarchy (İyerarxiya)](#hierarchy)
- [Iterable vs Iterator](#iterable-vs-iterator)
- [for-each loop daxili işləməsi](#for-each-loop-daxili-işləməsi)
- [Collection interfeysi](#collection-interfeysi)
- [List, Set, Queue müqayisəsi](#list-set-queue-müqayisəsi)
- [Map interfeysi](#map-interfeysi)
- [Düzgün kolleksiya seçimi](#düzgün-kolleksiya-seçimi)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Collections Framework nədir?

Java Collections Framework (JCF) — məlumatları saxlamaq, idarə etmək və manipulyasiya etmək üçün vahid arxitektura təqdim edən interfeyslər və siniflərin toplusudur.

**Niyə lazımdır?**
- Array-lərin statik ölçüsü problemi həll olunur (dinamik ölçü)
- Hazır alqoritmlər (sort, search, shuffle)
- Tip təhlükəsizliyi (Generics ilə)
- Kod təkrarını azaldır
- Standart API — bütün developerlər eyni metodları bilir

```java
// Collections Framework olmadan — əvvəlki Java dövrü
// Massiv ölçüsü əvvəlcədən bilinməlidir
String[] names = new String[10]; // sabit ölçü
int count = 0;
names[count++] = "Orkhan";
// Əgər 11-ci element əlavə etsək — ArrayIndexOutOfBoundsException!

// Collections Framework ilə — müasir yanaşma
List<String> nameList = new ArrayList<>(); // dinamik ölçü
nameList.add("Orkhan");
nameList.add("Anar");
nameList.add("Leyla");
// Avtomatik böyüyür, tip təhlükəsizdir
```

---

## Hierarchy

```
java.lang.Iterable<T>
    └── java.util.Collection<T>
            ├── java.util.List<T>
            │       ├── ArrayList
            │       ├── LinkedList
            │       ├── Vector (köhnə, deprecated)
            │       └── Stack (köhnə, deprecated)
            │
            ├── java.util.Set<T>
            │       ├── HashSet
            │       ├── LinkedHashSet
            │       └── SortedSet<T>
            │               └── NavigableSet<T>
            │                       └── TreeSet
            │
            └── java.util.Queue<T>
                    ├── PriorityQueue
                    ├── ArrayDeque
                    └── Deque<T>
                            ├── ArrayDeque
                            └── LinkedList

java.util.Map<K,V>   ← Collection-dan MIRAS ALMAZ!
        ├── HashMap
        ├── LinkedHashMap
        ├── Hashtable (köhnə, thread-safe amma yavaş)
        └── SortedMap<K,V>
                └── NavigableMap<K,V>
                        └── TreeMap
```

> **Vacib qeyd:** `Map` interfeysi `Collection`-dan miras almır. Çünki Map key-value cütləri saxlayır, Collection isə tək elementlər saxlayır.

---

## Iterable vs Iterator

### Iterable interfeysi

```java
// java.lang.Iterable<T> interfeysi
public interface Iterable<T> {
    Iterator<T> iterator(); // tək abstrakt metod

    // Java 8+ əlavə edildi (default metodlar)
    default void forEach(Consumer<? super T> action) { ... }
    default Spliterator<T> spliterator() { ... }
}
```

### Iterator interfeysi

```java
// java.util.Iterator<T> interfeysi
public interface Iterator<T> {
    boolean hasNext(); // növbəti element varmı?
    T next();         // növbəti elementi qaytar
    
    // Java 8+ default metod
    default void remove() {
        throw new UnsupportedOperationException("remove");
    }
}
```

### Praktiki nümunə

```java
import java.util.*;

public class IterableVsIterator {
    public static void main(String[] args) {
        List<String> sehirler = new ArrayList<>(
            List.of("Bakı", "Gəncə", "Sumqayıt", "Lənkəran")
        );

        // Iterator əl ilə istifadəsi
        Iterator<String> iterator = sehirler.iterator();
        while (iterator.hasNext()) {
            String seher = iterator.next();
            System.out.println("Şəhər: " + seher);
        }

        // Öz Iterable sinifimiz
        NumberRange range = new NumberRange(1, 5);
        for (int num : range) { // for-each işləyir çünki Iterable implement edilib
            System.out.print(num + " "); // 1 2 3 4 5
        }
    }
}

// Özümüzün Iterable sinifi
class NumberRange implements Iterable<Integer> {
    private final int start;
    private final int end;

    public NumberRange(int start, int end) {
        this.start = start;
        this.end = end;
    }

    @Override
    public Iterator<Integer> iterator() {
        return new Iterator<>() {
            private int current = start;

            @Override
            public boolean hasNext() {
                return current <= end;
            }

            @Override
            public Integer next() {
                if (!hasNext()) throw new NoSuchElementException();
                return current++;
            }
        };
    }
}
```

---

## for-each loop daxili işləməsi

`for-each` sintaksisi kompilyator tərəfindən `Iterator`-a çevrilir:

```java
List<String> meyveler = List.of("Alma", "Armud", "Gilas");

// Yazdığımız kod:
for (String meyve : meyveler) {
    System.out.println(meyve);
}

// Kompilyatorun çevirdiyi kod (bytecode-a baxsaq):
Iterator<String> iter = meyveler.iterator();
while (iter.hasNext()) {
    String meyve = iter.next();
    System.out.println(meyve);
}
```

### Array üçün for-each fərqi

```java
int[] ededler = {10, 20, 30, 40, 50};

// Array üçün for-each Iterator istifadə etmir!
// Sadə indeks əsaslı dövrəyə çevrilir:
for (int i = 0; i < ededler.length; i++) {
    int eded = ededler[i];
    System.out.println(eded);
}
```

---

## Collection interfeysi

`Collection<E>` interfeysi bütün kolleksiyalar üçün əsas əməliyyatları müəyyən edir:

```java
import java.util.*;

public class CollectionMethodleri {
    public static void main(String[] args) {
        Collection<String> kolleksiya = new ArrayList<>();

        // ── Əlavə etmə ──
        kolleksiya.add("Java");          // tək element əlavə et
        kolleksiya.addAll(List.of("Python", "Go", "Rust")); // çox element

        // ── Ölçü ──
        int ölçü = kolleksiya.size();       // 4
        boolean boşdur = kolleksiya.isEmpty(); // false

        // ── Axtarış ──
        boolean var = kolleksiya.contains("Java");           // true
        boolean hamısıVar = kolleksiya.containsAll(List.of("Java", "Go")); // true

        // ── Silmə ──
        kolleksiya.remove("Python");
        kolleksiya.removeAll(List.of("Go", "Rust")); // hamısını sil
        // kolleksiya.clear(); // hamısını sil

        // ── Çevirmə ──
        Object[] massiv = kolleksiya.toArray();
        String[] stringMassiv = kolleksiya.toArray(new String[0]);

        // ── Kəsişmə ──
        Collection<String> diger = new ArrayList<>(List.of("Java", "Kotlin"));
        kolleksiya.retainAll(diger); // yalnız ümumi elementlər qalır

        System.out.println(kolleksiya); // [Java]
    }
}
```

---

## List, Set, Queue müqayisəsi

| Xüsusiyyət | List | Set | Queue |
|------------|------|-----|-------|
| Sıralama | Daxiletmə sırası qorunur | Ümumiyyətlə yox (TreeSet — bəli) | FIFO (PriorityQueue — prioritet) |
| Dublikatlar | İcazə verilir | İcazə verilmir | İcazə verilir |
| null dəyər | İcazə verilir | 1 null (HashSet) | Bəzən yox |
| İndeks | get(i) var | get(i) yox | peek/poll ilə |
| İstifadə | Sıralı siyahı | Unikal elementlər | İş növbəsi |

```java
import java.util.*;

public class ListSetQueueFerq {
    public static void main(String[] args) {
        // LIST — dublikat və sıra saxlayır
        List<String> list = new ArrayList<>();
        list.add("A"); list.add("B"); list.add("A");
        System.out.println("List: " + list); // [A, B, A] — sıra qorunur, dublikat var

        // SET — dublikat yoxdur
        Set<String> set = new HashSet<>();
        set.add("A"); set.add("B"); set.add("A");
        System.out.println("Set: " + set); // [A, B] — dublikat yox (sıra dəyişə bilər)

        // QUEUE — FIFO
        Queue<String> queue = new LinkedList<>();
        queue.offer("Birinci");
        queue.offer("İkinci");
        queue.offer("Üçüncü");
        System.out.println("Poll: " + queue.poll()); // Birinci — başdan götürür
        System.out.println("Peek: " + queue.peek()); // İkinci — götürmədən baxır
    }
}
```

---

## Map interfeysi

`Map<K,V>` key-value cütləri saxlayır, hər key unikaldır:

```java
import java.util.*;

public class MapMethodleri {
    public static void main(String[] args) {
        Map<String, Integer> yaşlar = new HashMap<>();

        // ── Əlavə etmə ──
        yaşlar.put("Orkhan", 25);
        yaşlar.put("Anar", 30);
        yaşlar.put("Leyla", 28);

        // ── Oxuma ──
        int yaş = yaşlar.get("Orkhan");         // 25
        int defYaş = yaşlar.getOrDefault("Kamran", 0); // 0 — tapılmasa default
        
        // ── Yoxlama ──
        boolean keyVar = yaşlar.containsKey("Anar");     // true
        boolean deyerVar = yaşlar.containsValue(30);     // true

        // ── Yeniləmə ──
        yaşlar.put("Orkhan", 26);           // köhnəni əvəz edir
        yaşlar.putIfAbsent("Orkhan", 99);   // artıq varsa dəyişmir
        yaşlar.replace("Anar", 31);         // yalnız varsa dəyişir

        // ── Silmə ──
        yaşlar.remove("Leyla");
        yaşlar.remove("Anar", 999); // yalnız dəyər uyğunsa sil

        // ── Dövrə ──
        for (Map.Entry<String, Integer> giriş : yaşlar.entrySet()) {
            System.out.println(giriş.getKey() + " → " + giriş.getValue());
        }

        // Java 8+ forEach
        yaşlar.forEach((ad, yaşDeyeri) ->
            System.out.println(ad + ": " + yaşDeyeri));

        // ── merge və compute ──
        // Varsa topla, yoxsa əlavə et
        yaşlar.merge("Orkhan", 1, Integer::sum); // Orkhan: 26+1=27
        
        // Hesabla və yenilə
        yaşlar.compute("Orkhan", (k, v) -> v == null ? 1 : v + 10);
        
        // View-lar
        Set<String> keyler = yaşlar.keySet();
        Collection<Integer> deyerler = yaşlar.values();
        Set<Map.Entry<String, Integer>> girişler = yaşlar.entrySet();
    }
}
```

---

## Düzgün kolleksiya seçimi

```java
import java.util.*;
import java.util.concurrent.*;

public class KolleksiyaSecimi {

    // SSENARIY 1: Sıralı siyahı, indeks ilə giriş lazımdır
    // → ArrayList seç
    List<String> kitablar = new ArrayList<>();

    // SSENARIY 2: Tez-tez əvvəl/axırdan əlavə/silmə
    // → ArrayDeque (stack/queue kimi) və ya LinkedList
    Deque<String> növbə = new ArrayDeque<>();

    // SSENARIY 3: Unikal elementlər, sıra önəmsizdir
    // → HashSet seç (O(1) add/contains)
    Set<String> unikal = new HashSet<>();

    // SSENARIY 4: Unikal, sıralı elementlər
    // → TreeSet seç (O(log n))
    Set<String> siralanmis = new TreeSet<>();

    // SSENARIY 5: Unikal, daxiletmə sırası qorunsun
    // → LinkedHashSet seç
    Set<String> daxiletmeSirasi = new LinkedHashSet<>();

    // SSENARIY 6: Key-value, tez axtarış
    // → HashMap seç (O(1) ortalama)
    Map<String, Integer> tezMap = new HashMap<>();

    // SSENARIY 7: Key-value, key-lər sıralı olsun
    // → TreeMap seç (O(log n))
    Map<String, Integer> siraliMap = new TreeMap<>();

    // SSENARIY 8: Key-value, daxiletmə sırası qorunsun
    // → LinkedHashMap seç
    Map<String, Integer> siraliDaxiletme = new LinkedHashMap<>();

    // SSENARIY 9: Prioritet növbə
    // → PriorityQueue seç
    Queue<Integer> prioritetNovbe = new PriorityQueue<>();

    // SSENARIY 10: Çox-threadli mühit
    // → ConcurrentHashMap, CopyOnWriteArrayList seç
    Map<String, Integer> threadSafe = new ConcurrentHashMap<>();
    List<String> cowList = new CopyOnWriteArrayList<>();
}
```

### Seçim qaydaları cədvəli

| Ssenari | Tövsiyə olunan |
|---------|---------------|
| Tez-tez oxuma, az dəyişiklik | `ArrayList` |
| Tez-tez əvvəl/axır dəyişiklik | `ArrayDeque` |
| Unikal, sırasız | `HashSet` |
| Unikal, sıralı | `TreeSet` |
| Unikal, daxiletmə sırası | `LinkedHashSet` |
| Key-value, tez | `HashMap` |
| Key-value, key sıralı | `TreeMap` |
| Prioritet növbə | `PriorityQueue` |
| Thread-safe Map | `ConcurrentHashMap` |
| Thread-safe List | `CopyOnWriteArrayList` |
| Dəyişilməz kolleksiya | `List.of()`, `Set.of()`, `Map.of()` |

---

## YANLIŞ vs DOĞRU Nümunələr

```java
// ❌ YANLIŞ: Raw tip istifadəsi
List list = new ArrayList();
list.add("string");
list.add(123); // int əlavə etmək olar — tip təhlükəsizliyi yoxdur
String s = (String) list.get(1); // ClassCastException runtime-da!

// ✅ DOĞRU: Generic tip
List<String> safeList = new ArrayList<>();
safeList.add("string");
// safeList.add(123); // Kompilyasiya xətası — compile-time-da tutulur!
String s2 = safeList.get(0); // Cast lazım deyil

// ❌ YANLIŞ: Konkret sinifə bağlılıq
ArrayList<String> concreteList = new ArrayList<>(); // dəyişmək çətin olur

// ✅ DOĞRU: İnterfeysdən istifadə
List<String> interfaceList = new ArrayList<>(); // asanlıqla LinkedList-ə keçmək olar

// ❌ YANLIŞ: for-each ilə silmə
List<String> items = new ArrayList<>(List.of("A", "B", "C"));
for (String item : items) {
    if (item.equals("B")) {
        items.remove(item); // ConcurrentModificationException!
    }
}

// ✅ DOĞRU: Iterator.remove() istifadəsi
Iterator<String> it = items.iterator();
while (it.hasNext()) {
    if (it.next().equals("B")) {
        it.remove(); // təhlükəsiz silmə
    }
}

// ✅ DOĞRU: removeIf (Java 8+)
items.removeIf(item -> item.equals("B"));
```

---

## İntervyu Sualları

**S1: Collection və Collections arasındakı fərq nədir?**

`Collection` — interfeys (List, Set, Queue-nin valideyn interfeysi).
`Collections` — utility sinif (sort, shuffle, unmodifiableList kimi statik metodlar).

**S2: Map niyə Collection-dan miras almır?**

Map key-value cütləri saxlayır (2 element birlikdə), Collection isə tək elementlər saxlayır. Bu fərqli konseptual model sayəsində Map ayrı iyerarxiyada yerləşdirilmişdir.

**S3: Iterable və Iterator arasındakı fərq nədir?**

`Iterable` — "mən iterate edilə bilərəm" deməkdir, `iterator()` metodunu qaytarır. `Iterator` — aktiv gəzinti alətdir, `hasNext()` və `next()` metodları var. Hər `Iterable` öz `Iterator`-ını yaradır.

**S4: for-each loop hansı şərtlərdə işləyir?**

Sinif `Iterable<T>` interfeysini implement etməlidirsə və ya massiv olmalıdır.

**S5: null elementi hansı kolleksiyalarda saxlamaq olar?**

- `ArrayList`, `LinkedList` — bəli, null saxlaya bilər
- `HashSet`, `LinkedHashSet` — bəli, bir null saxlaya bilər
- `TreeSet`, `TreeMap` — xeyr (compareTo null-a qarşı işləmir)
- `PriorityQueue` — xeyr
- `Hashtable`, `ConcurrentHashMap` — xeyr
- `HashMap` — bir null key, çox null value saxlaya bilər

**S6: List.of() və new ArrayList() arasındakı fərq nədir?**

`List.of()` — dəyişilməz (immutable), null qəbul etmir. `new ArrayList()` — dəyişən (mutable), null qəbul edir.

**S7: Hansı kolleksiya thread-safe deyil?**

`ArrayList`, `HashMap`, `HashSet`, `TreeMap`, `LinkedList` — bunların hamısı thread-safe deyil. Thread-safe variantlar: `ConcurrentHashMap`, `CopyOnWriteArrayList`, `Collections.synchronizedList()`.
