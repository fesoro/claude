# 083 — Java Sequenced Collections — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Sequenced Collections nədir?](#sequenced-collections-nədir)
2. [SequencedCollection interface](#sequencedcollection-interface)
3. [SequencedSet interface](#sequencedset-interface)
4. [SequencedMap interface](#sequencedmap-interface)
5. [Praktiki istifadə nümunələri](#praktiki-istifadə-nümunələri)
6. [Collections utility metodları](#collections-utility-metodları)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Sequenced Collections nədir?

```
Java 21 — JEP 431: Sequenced Collections

Problem (Java 21 əvvəl):
  Sıralı kolleksiyalarda ilk/son elementin əldə edilməsi
  hər kolleksiya tipi üçün fərqli üsullarla edilirdi:

  List:
    ilk: list.get(0)
    son: list.get(list.size() - 1)

  Deque:
    ilk: deque.getFirst() / deque.peekFirst()
    son: deque.getLast() / deque.peekLast()

  SortedSet:
    ilk: sortedSet.first()
    son: sortedSet.last()

  LinkedHashSet:
    ilk: linkedHashSet.iterator().next()   ← bu nə?!
    son: ??? (birbaşa yol yoxdur!)

  Bu uyumsuzluq generic kod yazmağı çətinləşdirirdi!

Həll — Sequenced Collections:
  Yeni 3 interface hierarchy:
  
  Iterable
    └── Collection
          └── SequencedCollection  ← YENİ
                ├── List
                ├── Deque
                └── SequencedSet  ← YENİ
                      ├── SortedSet
                      └── LinkedHashSet (artıq)

  Map
    └── SequencedMap  ← YENİ
          ├── SortedMap
          └── LinkedHashMap (artıq)
```

---

## SequencedCollection interface

```java
// ─── SequencedCollection — yeni metodlar ─────────────────
// java.util.SequencedCollection<E>

public interface SequencedCollection<E> extends Collection<E> {

    // YENİ metodlar:
    E getFirst();                    // İlk element (NoSuchElementException if empty)
    E getLast();                     // Son element
    void addFirst(E e);              // Əvvələ əlavə et (optional — bəziləri throw)
    void addLast(E e);               // Sona əlavə et (optional)
    E removeFirst();                 // İlk elementi sil və qaytar
    E removeLast();                  // Son elementi sil və qaytar
    SequencedCollection<E> reversed(); // Tərsinə — view (yeni kolleksiya deyil!)
}

// ─── List ilə istifadə ────────────────────────────────────
List<String> fruits = new ArrayList<>(List.of("alma", "armud", "gilas", "üzüm"));

String first = fruits.getFirst();   // "alma"    ← köhnə: fruits.get(0)
String last  = fruits.getLast();    // "üzüm"    ← köhnə: fruits.get(fruits.size()-1)

fruits.addFirst("çiyələk");         // ["çiyələk", "alma", "armud", "gilas", "üzüm"]
fruits.addLast("şeftəli");          // [..., "şeftəli"]

String removed = fruits.removeFirst(); // "çiyələk" silindi
String removedLast = fruits.removeLast(); // "şeftəli" silindi

// reversed() — tərsinə çevrilmiş view
List<String> reversed = fruits.reversed();
System.out.println(reversed);      // ["üzüm", "gilas", "armud", "alma"]

// reversed() — view-dur, orijinalı dəyişir!
reversed.addFirst("ananas");       // orijinal list-in sonuna əlavə edilir!
System.out.println(fruits);        // ["alma", "armud", "gilas", "üzüm", "ananas"]

// ─── LinkedList (Deque) ilə ──────────────────────────────
LinkedList<Integer> deque = new LinkedList<>(List.of(1, 2, 3, 4, 5));

deque.getFirst();    // 1 — köhnə: deque.peekFirst() / getFirst()
deque.getLast();     // 5 — köhnə: deque.peekLast() / getLast()

// LinkedList artıq SequencedCollection implement edir
// əvvəlki metodlar (getFirst, getLast) uyğunlaşdırıldı

// ─── Generic kod — indi mümkün! ──────────────────────────
// Köhnə: hər tip üçün ayrı kod
// Yeni: generic SequencedCollection-la işlə

public static <E> E getFirstOrDefault(SequencedCollection<E> col, E defaultValue) {
    if (col.isEmpty()) return defaultValue;
    return col.getFirst();
}

public static <E> void printFirstAndLast(SequencedCollection<E> col) {
    if (col.isEmpty()) {
        System.out.println("Boş kolleksiya");
        return;
    }
    System.out.println("İlk: " + col.getFirst());
    System.out.println("Son: " + col.getLast());
}

// İstifadə — hamısı işləyir!
printFirstAndLast(new ArrayList<>(List.of(1, 2, 3)));
printFirstAndLast(new LinkedList<>(List.of("a", "b", "c")));
printFirstAndLast(new ArrayDeque<>(List.of(10, 20, 30)));
```

---

## SequencedSet interface

```java
// ─── SequencedSet — sıralı, unikal elementlər ────────────
public interface SequencedSet<E>
    extends SequencedCollection<E>, Set<E> {

    @Override
    SequencedSet<E> reversed();   // SequencedSet qaytarır
}

// ─── LinkedHashSet — artıq SequencedSet implement edir ───
LinkedHashSet<String> linkedSet = new LinkedHashSet<>();
linkedSet.add("alma");
linkedSet.add("armud");
linkedSet.add("gilas");
linkedSet.add("üzüm");

// Köhnə — ilk elementi əldə etmək çətin idi:
// String first = linkedSet.iterator().next();   ← çirkin!

// YENİ — sadə:
String first = linkedSet.getFirst();  // "alma"
String last  = linkedSet.getLast();   // "üzüm"

linkedSet.addFirst("çiyələk");  // Əvvələ əlavə et
linkedSet.addLast("şeftəli");   // Sona əlavə et

// Duplicate əlavə edilsə — Set qaydası: əlavə edilmir (sıra dəyişməz)
linkedSet.addFirst("alma");  // "alma" artıq var → heç nə dəyişmir!

// reversed() — tərsinə sıralı view
SequencedSet<String> reversedSet = linkedSet.reversed();
reversedSet.getFirst();  // orijinalın son elementi

// ─── TreeSet — SortedSet, SequencedSet implement edir ────
TreeSet<Integer> treeSet = new TreeSet<>(List.of(5, 2, 8, 1, 9, 3));

treeSet.getFirst();  // 1 — ən kiçik (köhnə: treeSet.first())
treeSet.getLast();   // 9 — ən böyük  (köhnə: treeSet.last())

// TreeSet-də addFirst/addLast throw eder!
// TreeSet sıralanmışdır — əvvəl/son mənası yoxdur
try {
    treeSet.addFirst(0);  // UnsupportedOperationException!
} catch (UnsupportedOperationException e) {
    System.out.println("TreeSet-ə addFirst olmaz");
}

// reversed() — descending order view
SequencedSet<Integer> desc = treeSet.reversed();
desc.getFirst(); // 9 (ən böyük)
desc.getLast();  // 1 (ən kiçik)
```

---

## SequencedMap interface

```java
// ─── SequencedMap — sıralı açar-dəyər cütləri ────────────
public interface SequencedMap<K, V> extends Map<K, V> {

    // YENİ metodlar:
    Map.Entry<K, V> firstEntry();               // İlk entry
    Map.Entry<K, V> lastEntry();                // Son entry
    Map.Entry<K, V> pollFirstEntry();           // İlk entry-ni sil və qaytar
    Map.Entry<K, V> pollLastEntry();            // Son entry-ni sil və qaytar
    void putFirst(K k, V v);                    // Əvvələ əlavə et
    void putLast(K k, V v);                     // Sona əlavə et
    SequencedMap<K, V> reversed();              // Tərsinə view

    // Sequenced view-lar:
    SequencedSet<K> sequencedKeySet();          // Key-lər (sıralı)
    SequencedCollection<V> sequencedValues();   // Dəyərlər (sıralı)
    SequencedSet<Map.Entry<K, V>> sequencedEntrySet(); // Entry-lər (sıralı)
}

// ─── LinkedHashMap ilə istifadə ──────────────────────────
LinkedHashMap<String, Integer> scores = new LinkedHashMap<>();
scores.put("Əli", 95);
scores.put("Aynur", 88);
scores.put("Murad", 92);
scores.put("Gülnar", 97);

// Köhnə — ilk/son entry əldə etmək:
// Map.Entry<String, Integer> first = scores.entrySet().iterator().next(); ← çirkin!

// YENİ:
Map.Entry<String, Integer> firstEntry = scores.firstEntry();
System.out.println(firstEntry.getKey() + ": " + firstEntry.getValue()); // Əli: 95

Map.Entry<String, Integer> lastEntry = scores.lastEntry();
System.out.println(lastEntry.getKey() + ": " + lastEntry.getValue());   // Gülnar: 97

// Əvvələ əlavə et
scores.putFirst("Zeynəb", 100);
scores.firstEntry(); // Zeynəb: 100

// Son entry sil və qaytar
Map.Entry<String, Integer> removed = scores.pollLastEntry(); // Gülnar silindi

// Tərsinə view
SequencedMap<String, Integer> reversedScores = scores.reversed();
reversedScores.firstEntry(); // orijinalın son entry-si

// Sequenced key/value view-lar
for (String key : scores.sequencedKeySet()) {
    System.out.println(key);  // Insertion order-da
}

for (Integer score : scores.sequencedValues().reversed()) {
    System.out.println(score); // Tərsinə dəyərlər
}

// ─── TreeMap — SortedMap, SequencedMap implement edir ────
TreeMap<String, Integer> treeMap = new TreeMap<>();
treeMap.put("banana", 3);
treeMap.put("apple", 5);
treeMap.put("cherry", 1);

treeMap.firstEntry();  // apple: 5 (əlifba sırası) — köhnə: treeMap.firstEntry()
treeMap.lastEntry();   // cherry: 1

// TreeMap-də putFirst/putLast throw eder!
```

---

## Praktiki istifadə nümunələri

```java
// ─── LRU Cache — LinkedHashMap + SequencedMap ────────────
public class LruCache<K, V> {
    private final int capacity;
    private final LinkedHashMap<K, V> cache;

    public LruCache(int capacity) {
        this.capacity = capacity;
        // accessOrder=true: get() zamanı order güncəllənir
        this.cache = new LinkedHashMap<>(capacity, 0.75f, true);
    }

    public V get(K key) {
        return cache.getOrDefault(key, null);
    }

    public void put(K key, V value) {
        if (cache.size() >= capacity) {
            // Ən köhnə (LRU) elementi sil
            cache.pollFirstEntry();   // SequencedMap metodu!
        }
        cache.put(key, value);
    }

    public K getMostRecentKey() {
        Map.Entry<K, V> last = cache.lastEntry(); // SequencedMap!
        return last != null ? last.getKey() : null;
    }

    public K getLeastRecentKey() {
        Map.Entry<K, V> first = cache.firstEntry(); // SequencedMap!
        return first != null ? first.getKey() : null;
    }
}

// ─── Transaction history — insertion order ────────────────
public class TransactionHistory {
    private final LinkedHashSet<String> processed = new LinkedHashSet<>();
    private final LinkedHashMap<String, Transaction> transactions =
        new LinkedHashMap<>();

    public void addTransaction(Transaction tx) {
        if (processed.contains(tx.id())) {
            return; // Duplicate, skip
        }
        processed.add(tx.id());
        transactions.put(tx.id(), tx);
    }

    public Optional<Transaction> getLatest() {
        Map.Entry<String, Transaction> last = transactions.lastEntry();
        return Optional.ofNullable(last).map(Map.Entry::getValue);
    }

    public Optional<Transaction> getEarliest() {
        Map.Entry<String, Transaction> first = transactions.firstEntry();
        return Optional.ofNullable(first).map(Map.Entry::getValue);
    }

    // Son N transaction
    public List<Transaction> getLastN(int n) {
        List<Transaction> result = new ArrayList<>();
        SequencedCollection<Transaction> values = transactions.sequencedValues();
        SequencedCollection<Transaction> reversed = values.reversed();

        int count = 0;
        for (Transaction tx : reversed) {
            if (count++ >= n) break;
            result.add(tx);
        }
        return result;
    }
}

// ─── Sliding window — SequencedCollection ─────────────────
public class SlidingWindowMetrics {
    private final int windowSize;
    private final ArrayDeque<Double> window;

    public SlidingWindowMetrics(int windowSize) {
        this.windowSize = windowSize;
        this.window = new ArrayDeque<>(windowSize);
    }

    public void add(double value) {
        if (window.size() >= windowSize) {
            window.removeFirst();  // SequencedCollection metodu
        }
        window.addLast(value);     // SequencedCollection metodu
    }

    public double getMin() {
        return window.stream().mapToDouble(Double::doubleValue).min().orElse(0);
    }

    public double getAverage() {
        return window.stream().mapToDouble(Double::doubleValue).average().orElse(0);
    }

    public double getLatest() {
        return window.getLast();   // SequencedCollection metodu
    }
}
```

---

## Collections utility metodları

```java
// ─── Collections.unmodifiable* — immutable wrapping ───────
List<String> mutableList = new ArrayList<>(List.of("a", "b", "c"));

// Köhnə:
List<String> immutableOld = Collections.unmodifiableList(mutableList);

// YENİ — SequencedCollection saxlayır:
SequencedCollection<String> immutableSeq =
    Collections.unmodifiableSequencedCollection(mutableList);

SequencedSet<String> immutableLinkedSet =
    Collections.unmodifiableSequencedSet(new LinkedHashSet<>(List.of("x", "y", "z")));

SequencedMap<String, Integer> immutableLinkedMap =
    Collections.unmodifiableSequencedMap(
        new LinkedHashMap<>(Map.of("a", 1, "b", 2))
    );

// ─── Reversed view-lar ────────────────────────────────────
List<Integer> numbers = new ArrayList<>(List.of(1, 2, 3, 4, 5));

// Reversed for-each
for (Integer n : numbers.reversed()) {
    System.out.print(n + " ");  // 5 4 3 2 1
}

// Reversed stream (Java 21)
numbers.reversed().stream()
    .filter(n -> n % 2 == 0)
    .forEach(System.out::println);  // 4, 2

// Binary search reversed sorted list
List<Integer> sorted = new ArrayList<>(List.of(1, 2, 3, 4, 5));
List<Integer> reversedSorted = sorted.reversed();
// reversedSorted: [5, 4, 3, 2, 1]

// ─── Backward compatible — köhnə kod işləyir ─────────────
// Java 21-ə upgrade edəndə köhnə kod qırılmır
// LinkedHashMap/LinkedHashSet/ArrayDeque əvvəlki metodları saxlayır
// YENİ metodlar əlavə edilib, köhnə silinməyib

// Before Java 21:
LinkedList<String> ll = new LinkedList<>(List.of("a", "b", "c"));
String firstOld = ll.getFirst();   // Bu köhnə LinkedList metodu
String lastOld  = ll.getLast();    // Bu da köhnə

// After Java 21:
// Eyni metodlar — amma artıq SequencedCollection-dan gəlir (override)
// Köhnə + yeni metodlar eyni nəticəni verir → backward compatible
```

---

## İntervyu Sualları

### 1. Sequenced Collections niyə əlavə edildi?
**Cavab:** Java 21-dən əvvəl sıralı kolleksiyalarda ilk/son element əldə etmək hər tip üçün fərqli idi: List üçün `get(0)`, Deque üçün `peekFirst()`, SortedSet üçün `first()`, LinkedHashSet üçün `iterator().next()`. Bu uyumsuzluq generic kod yazmağı çətinləşdirirdi. **JEP 431** `SequencedCollection`, `SequencedSet`, `SequencedMap` interfeyslərini əlavə etdi — vahid API ilə ilk/son əməliyyatlar, `reversed()` view, `addFirst/addLast/removeFirst/removeLast`.

### 2. reversed() necə işləyir?
**Cavab:** `reversed()` yeni kolleksiya yaratmır — orijinal kolleksiyanın **view**-unu qaytarır. View vasitəsilə edilən dəyişikliklər orijinala əks istiqamətdə tətbiq olunur: `reversed().addFirst(x)` → orijinalın sonuna əlavə edir. Performans: O(1) — yalnız adapter object yaradılır. Stream ilə: `list.reversed().stream()` — iterasiya tərsinə olur, kopyalama yoxdur.

### 3. LinkedHashMap ilə SequencedMap-ın yeni metodları hansılardır?
**Cavab:** `firstEntry()`, `lastEntry()` — ilk/son entry (köhnə: `entrySet().iterator().next()`); `pollFirstEntry()`, `pollLastEntry()` — sil+qaytar; `putFirst()`, `putLast()` — sıraya uyğun əlavə; `sequencedKeySet()`, `sequencedValues()`, `sequencedEntrySet()` — sıra saxlayan view-lar; `reversed()` — tərsinə view. TreeMap-da `putFirst`/`putLast` `UnsupportedOperationException` atır çünkü sıra açar sıralamasına görədir.

### 4. addFirst() TreeSet-də niyə işləmir?
**Cavab:** TreeSet natural ordering (Comparable) ya da Comparator ilə sıralanır — elementlər dəyərinə görə avtomatik yerləşdirilir. "Əvvəl" ya "son" yerləşdirmək anlamsızdır: 5-i "əvvələ" əlavə etmək istəsən, TreeSet onu doğru sıra mövqeyinə qoyur (3-ün önünə, 7-nin arxasına). Buna görə `addFirst`/`addLast` `UnsupportedOperationException` atır. `getFirst()`/`getLast()` isə works — ən kiçik/ən böyük element qaytarır.

### 5. Sequenced Collections köhnə kodla backward compatible-dırmı?
**Cavab:** Bəli, tam backward compatible. LinkedHashMap, LinkedHashSet, ArrayDeque, LinkedList — bunlar artıq SequencedCollection/SequencedMap implement edir. Köhnə metodlar (`getFirst()` LinkedList-də) silinmədi — override edildi. Köhnə kod dəyişiklik olmadan Java 21-də çalışır. Yeni metodlar (`firstEntry()` LinkedHashMap-da) əlavə edildi. Mövcud API-lar (`iterator().next()`) hələ işləyir amma yeni metodlar daha oxunaqlı alternativ təqdim edir.

*Son yenilənmə: 2026-04-10*
