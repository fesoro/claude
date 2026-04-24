# 032 — LinkedHashMap və TreeMap — Sıralı Map-lər
**Səviyyə:** Orta


## Mündəricat
- [LinkedHashMap — Daxili Quruluş](#linkedhashmap--daxili-quruluş)
- [Daxiletmə sırası vs Giriş sırası](#daxiletmə-sırası-vs-giriş-sırası)
- [LRU Cache implementasiyası](#lru-cache-implementasiyası)
- [TreeMap — Red-Black Tree](#treemap--red-black-tree)
- [SortedMap və NavigableMap](#sortedmap-və-navigablemap)
- [ceiling/floor/headMap/tailMap əməliyyatları](#ceilingfloorheadmaptailmap-əməliyyatları)
- [LinkedHashMap vs TreeMap müqayisəsi](#linkedhashmap-vs-treemap-müqayisəsi)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## LinkedHashMap — Daxili Quruluş

`LinkedHashMap` — HashMap-i genişləndirir (miras alır) və əlavə olaraq **ikitərəfli əlaqəli siyahı** saxlayır. Bu siyahı ya daxiletmə sırasını, ya da giriş sırasını qoruyur.

```java
// LinkedHashMap-in daxili Node strukturu
// HashMap.Node-dan miras alır + prev/after əlavə edir
static class Entry<K,V> extends HashMap.Node<K,V> {
    Entry<K,V> before;  // əvvəlki node (daxiletmə/giriş sırası üzrə)
    Entry<K,V> after;   // növbəti node (daxiletmə/giriş sırası üzrə)

    Entry(int hash, K key, V value, Node<K,V> next) {
        super(hash, key, value, next);
    }
}

// LinkedHashMap əlavə olaraq:
transient LinkedHashMap.Entry<K,V> head; // ikiqat siyahının başı (ən köhnə)
transient LinkedHashMap.Entry<K,V> tail; // ikiqat siyahının sonu (ən yeni)
final boolean accessOrder;              // false=daxiletmə sırası, true=giriş sırası
```

```
HashMap bucket massivi:
  [0] → null
  [3] → Entry{key="B"} ←──────────────────────── before/after göstəriciləri
  [7] → Entry{key="A"} ←──────────────┐            ilə əlaqəli siyahı:
  [11]→ Entry{key="C"} ←──────┐       │
                               │       │
Daxiletmə sırası siyahısı:     │       │
head → [A] ↔ [B] ↔ [C] → tail
```

---

## Daxiletmə sırası vs Giriş sırası

```java
import java.util.*;

public class LinkedHashMapNümunə {
    public static void main(String[] args) {

        // ── Daxiletmə sırası (default, accessOrder=false) ──
        Map<String, Integer> daxiletmeSirasi = new LinkedHashMap<>();
        daxiletmeSirasi.put("Banana", 2);
        daxiletmeSirasi.put("Alma", 1);
        daxiletmeSirasi.put("Gilas", 3);

        // get() sıranı dəyişmir
        daxiletmeSirasi.get("Alma");
        daxiletmeSirasi.get("Banana");

        System.out.println("Daxiletmə sırası: " + daxiletmeSirasi);
        // {Banana=2, Alma=1, Gilas=3} — daxiletmə sırası qorunur

        // ── Giriş sırası (accessOrder=true) ──
        Map<String, Integer> girisSirasi = new LinkedHashMap<>(16, 0.75f, true);
        girisSirasi.put("Banana", 2);
        girisSirasi.put("Alma", 1);
        girisSirasi.put("Gilas", 3);

        System.out.println("Girişdən əvvəl: " + girisSirasi);
        // {Banana=2, Alma=1, Gilas=3}

        girisSirasi.get("Banana"); // Banana-ya giriş — sona keçir
        System.out.println("Banana-ya girdikdən sonra: " + girisSirasi);
        // {Alma=1, Gilas=3, Banana=2} — Banana sona keçdi (ən son istifadə edilən)

        girisSirasi.get("Alma");   // Alma-ya giriş — sona keçir
        System.out.println("Alma-ya girdikdən sonra: " + girisSirasi);
        // {Gilas=3, Banana=2, Alma=1}

        // put() da sona köçürür (accessOrder=true-da)
        girisSirasi.put("Banana", 10); // yeniləmə — Banana sona keçir
        System.out.println("Banana yeniləndikdən sonra: " + girisSirasi);
        // {Gilas=3, Alma=1, Banana=10}
    }
}
```

---

## LRU Cache implementasiyası

**LRU (Least Recently Used) Cache** — ən az istifadə edilən elementləri çıxarır.

### Metod 1: LinkedHashMap ilə (tövsiyə olunan)

```java
import java.util.*;

/**
 * LRU Cache — LinkedHashMap əsasında
 * accessOrder=true → ən son istifadə edilən sona keçir
 * removeEldestEntry() → baş elementin (ən köhnə) silinməsinə qərar verir
 */
public class LRUCache<K, V> extends LinkedHashMap<K, V> {

    private final int maksimumÖlçü; // maksimum element sayı

    public LRUCache(int maksimumÖlçü) {
        super(
            maksimumÖlçü,   // initialCapacity
            0.75f,           // loadFactor
            true             // accessOrder=true — giriş sırası
        );
        this.maksimumÖlçü = maksimumÖlçü;
    }

    /**
     * Bu metod hər put() əməliyyatından sonra çağırılır.
     * true qaytarırsa, ən köhnə element (head) silinir.
     */
    @Override
    protected boolean removeEldestEntry(Map.Entry<K, V> eldest) {
        return size() > maksimumÖlçü; // həddən artıq element varsa sil
    }

    public static void main(String[] args) {
        LRUCache<Integer, String> cache = new LRUCache<>(3);

        cache.put(1, "Bir");
        cache.put(2, "İki");
        cache.put(3, "Üç");
        System.out.println("Başlanğıc: " + cache); // {1=Bir, 2=İki, 3=Üç}

        cache.get(1); // 1-ə giriş — 1 sona keçir
        System.out.println("get(1) sonra: " + cache); // {2=İki, 3=Üç, 1=Bir}

        cache.put(4, "Dörd"); // 4-cü element — 2 silinir (ən köhnə)
        System.out.println("put(4) sonra: " + cache); // {3=Üç, 1=Bir, 4=Dörd}

        System.out.println("2 varmı? " + cache.containsKey(2)); // false
        System.out.println("3 varmı? " + cache.containsKey(3)); // true
    }
}
```

### Metod 2: Thread-safe LRU Cache

```java
import java.util.*;
import java.util.concurrent.locks.*;

/**
 * Thread-safe LRU Cache
 * Çox-threadli mühit üçün ReadWriteLock istifadə edir
 */
public class ThreadSafeLRUCache<K, V> {

    private final Map<K, V> cache;
    private final ReadWriteLock lock = new ReentrantReadWriteLock();
    private final Lock readLock = lock.readLock();
    private final Lock writeLock = lock.writeLock();

    public ThreadSafeLRUCache(int capacity) {
        this.cache = new LinkedHashMap<>(capacity, 0.75f, true) {
            @Override
            protected boolean removeEldestEntry(Map.Entry<K, V> eldest) {
                return size() > capacity;
            }
        };
    }

    public V get(K key) {
        readLock.lock();
        try {
            return cache.get(key);
        } finally {
            readLock.unlock();
        }
    }

    public void put(K key, V value) {
        writeLock.lock();
        try {
            cache.put(key, value);
        } finally {
            writeLock.unlock();
        }
    }

    public boolean containsKey(K key) {
        readLock.lock();
        try {
            return cache.containsKey(key);
        } finally {
            readLock.unlock();
        }
    }

    public static void main(String[] args) throws InterruptedException {
        ThreadSafeLRUCache<String, String> cache = new ThreadSafeLRUCache<>(100);

        // Çox thread eyni vaxtda işləyir
        Thread t1 = new Thread(() -> {
            for (int i = 0; i < 50; i++) cache.put("key" + i, "val" + i);
        });
        Thread t2 = new Thread(() -> {
            for (int i = 0; i < 50; i++) cache.get("key" + (i % 10));
        });

        t1.start(); t2.start();
        t1.join(); t2.join();
        System.out.println("Thread-safe LRU Cache işlədi");
    }
}
```

---

## TreeMap — Red-Black Tree

`TreeMap` — key-ləri **Red-Black Tree** (balanslaşdırılmış ikili axtarış ağacı) strukturunda saxlayır. Bütün əməliyyatlar O(log n)-dir.

```java
// Red-Black Tree xüsusiyyətləri:
// 1. Hər node ya qırmızı, ya qara
// 2. Kök həmişə qaradır
// 3. Heç bir iki ardıcıl qırmızı node yoxdur
// 4. Hər path-da eyni sayda qara node var
// → Ağac həmişə balanslaşdırılmışdır: hündürlük O(log n)

// TreeMap-in daxili Entry strukturu
static final class Entry<K,V> implements Map.Entry<K,V> {
    K key;
    V value;
    Entry<K,V> left;   // sol övlad
    Entry<K,V> right;  // sağ övlad
    Entry<K,V> parent; // valideyn
    boolean color;     // qara=true, qırmızı=false
}
```

```java
import java.util.*;

public class TreeMapNümunə {
    public static void main(String[] args) {
        // Natural ordering — String-lər əlifba sırası ilə
        TreeMap<String, Integer> şəhərEhali = new TreeMap<>();
        şəhərEhali.put("Sumqayıt", 350_000);
        şəhərEhali.put("Bakı", 2_300_000);
        şəhərEhali.put("Gəncə", 340_000);
        şəhərEhali.put("Lənkəran", 110_000);
        şəhərEhali.put("Mingəçevir", 100_000);

        // İterate edəndə əlifba sırası ilə gəlir!
        System.out.println(şəhərEhali);
        // {Bakı=2300000, Gəncə=340000, Lənkəran=110000, Mingəçevir=100000, Sumqayıt=350000}

        // Xüsusi Comparator — əhali azalan sırası ilə
        TreeMap<String, Integer> əhaliSırası = new TreeMap<>(
            Comparator.comparingInt(String::length).thenComparing(Comparator.naturalOrder())
        );
        əhaliSırası.putAll(şəhərEhali);
        System.out.println(əhaliSırası);
        // Qısa addan uzun ada sıralanır

        // Integer key — ədədi sıra
        TreeMap<Integer, String> rütbə = new TreeMap<>();
        rütbə.put(3, "Gümüş");
        rütbə.put(1, "Qızıl");
        rütbə.put(2, "Bürünc");

        System.out.println(rütbə); // {1=Qızıl, 2=Bürünc, 3=Gümüş} — sıralı!
    }
}
```

---

## SortedMap və NavigableMap

```
Map<K,V>
    └── SortedMap<K,V>      — sıralı əməliyyatlar
            └── NavigableMap<K,V>  — naviqasiya əməliyyatları
                    └── TreeMap<K,V>
```

```java
import java.util.*;

public class SortedNavigableDemo {
    public static void main(String[] args) {
        TreeMap<Integer, String> ballar = new TreeMap<>();
        ballar.put(45, "Zəif");
        ballar.put(60, "Kafi");
        ballar.put(75, "Yaxşı");
        ballar.put(85, "Əla");
        ballar.put(95, "Mükəmməl");

        // ── SortedMap metodları ──
        System.out.println("İlk key: " + ballar.firstKey()); // 45
        System.out.println("Son key: " + ballar.lastKey());  // 95

        // headMap — verilən key-dən kiçik elementlər (exclusive)
        SortedMap<Integer, String> aşağıBallar = ballar.headMap(75);
        System.out.println("75-dən kiçik: " + aşağıBallar); // {45=Zəif, 60=Kafi}

        // tailMap — verilən key-dən böyük/bərabər elementlər (inclusive)
        SortedMap<Integer, String> yüksəkBallar = ballar.tailMap(75);
        System.out.println("75 və yuxarı: " + yüksəkBallar); // {75=Yaxşı, 85=Əla, 95=Mükəmməl}

        // subMap — aralıq [from, to)
        SortedMap<Integer, String> ortaBallar = ballar.subMap(60, 85);
        System.out.println("60-85 arası: " + ortaBallar); // {60=Kafi, 75=Yaxşı}

        // ── NavigableMap metodları ──
        // ceilingKey — verilən dəyərə bərabər və ya böyük ən kiçik key
        System.out.println("70-ə ceiling: " + ballar.ceilingKey(70)); // 75
        System.out.println("75-ə ceiling: " + ballar.ceilingKey(75)); // 75

        // floorKey — verilən dəyərə bərabər və ya kiçik ən böyük key
        System.out.println("70-ə floor: " + ballar.floorKey(70));   // 60
        System.out.println("75-ə floor: " + ballar.floorKey(75));   // 75

        // higherKey — verilən dəyərdən BÖYÜK ən kiçik key (exclusive)
        System.out.println("75-dən higher: " + ballar.higherKey(75)); // 85

        // lowerKey — verilən dəyərdən KİÇİK ən böyük key (exclusive)
        System.out.println("75-dən lower: " + ballar.lowerKey(75));   // 60

        // Entry variantları
        Map.Entry<Integer, String> ceilingEntry = ballar.ceilingEntry(70);
        System.out.println("ceiling entry: " + ceilingEntry); // 75=Yaxşı

        // ── Ters sıra ──
        NavigableMap<Integer, String> tersSıra = ballar.descendingMap();
        System.out.println("Ters sıra: " + tersSıra);
        // {95=Mükəmməl, 85=Əla, 75=Yaxşı, 60=Kafi, 45=Zəif}

        // ── headMap/tailMap inclusive variantları ──
        // headMap(key, inclusive) — NavigableMap metodu
        NavigableMap<Integer, String> headInclusive = ballar.headMap(75, true); // 75 daxil
        System.out.println("75-ə qədər (daxil): " + headInclusive);
        // {45=Zəif, 60=Kafi, 75=Yaxşı}

        NavigableMap<Integer, String> subInclusive = ballar.subMap(60, true, 85, true);
        System.out.println("[60,85]: " + subInclusive); // {60=Kafi, 75=Yaxşı, 85=Əla}

        // ── Polling ──
        TreeMap<Integer, String> temp = new TreeMap<>(ballar);
        Map.Entry<Integer, String> ilk = temp.pollFirstEntry(); // çıxarır + silir
        System.out.println("İlk çıxarıldı: " + ilk);          // 45=Zəif
        Map.Entry<Integer, String> son = temp.pollLastEntry();  // son çıxarılır
        System.out.println("Son çıxarıldı: " + son);           // 95=Mükəmməl
    }
}
```

---

## ceiling/floor/headMap/tailMap əməliyyatları

### Praktiki ssenarilər

```java
import java.util.*;
import java.time.*;

public class TreeMapPraktikNümunə {

    public static void main(String[] args) {
        // SSENARIY 1: Tarix əsasında axtarış
        TreeMap<LocalDate, String> tədbirlər = new TreeMap<>();
        tədbirlər.put(LocalDate.of(2024, 3, 20), "Novruz");
        tədbirlər.put(LocalDate.of(2024, 5, 28), "Respublika Günü");
        tədbirlər.put(LocalDate.of(2024, 6, 15), "Milli Qurtuluş");
        tədbirlər.put(LocalDate.of(2024, 10, 18), "İstiqlal Günü");
        tədbirlər.put(LocalDate.of(2024, 11, 12), "Konstitusiya Günü");

        LocalDate bu gün = LocalDate.of(2024, 6, 1);

        // Növbəti tədbirlər
        Map.Entry<LocalDate, String> növbətiTədbit = tədbirlər.ceilingEntry(bu gün);
        System.out.println("Növbəti tədbit: " + növbətiTədbit); // 2024-06-15=Milli Qurtuluş

        // Son keçmiş tədbit
        Map.Entry<LocalDate, String> keçmiş = tədbirlər.floorEntry(bu gün);
        System.out.println("Son tədbit: " + keçmiş); // 2024-05-28=Respublika Günü

        // Gələcək tədbirlər
        NavigableMap<LocalDate, String> gələcək = tədbirlər.tailMap(bu gün, false);
        System.out.println("Gələcək: " + gələcək);

        // SSENARIY 2: Qiymət aralığı axtarışı
        TreeMap<Double, String> qiymətler = new TreeMap<>();
        qiymətler.put(100.0, "Ucuz");
        qiymətler.put(500.0, "Orta");
        qiymətler.put(1000.0, "Bahalı");
        qiymətler.put(5000.0, "Premium");
        qiymətler.put(10000.0, "Lüks");

        double büdcə = 800.0;
        // Büdcəyə uyğun ən yaxşı məhsul
        Map.Entry<Double, String> uyğun = qiymətler.floorEntry(büdcə);
        System.out.println("800 AZN büdcəyə uyğun: " + uyğun); // 500.0=Orta

        // 200-800 aralığındakılar
        NavigableMap<Double, String> aralıq = qiymətler.subMap(200.0, true, 800.0, true);
        System.out.println("200-800 AZN: " + aralıq); // {500.0=Orta}

        // SSENARIY 3: Telefon rehberi — prefix axtarışı
        TreeMap<String, String> rehber = new TreeMap<>();
        rehber.put("Ali", "+994501234567");
        rehber.put("Anar", "+994551234567");
        rehber.put("Aysel", "+994701234567");
        rehber.put("Bəhruz", "+994991234567");
        rehber.put("Cavid", "+994771234567");

        // "An" ilə başlayan bütün adlar
        String prefix = "An";
        // prefix-dən "prefix + \uffff" aralığı — bütün "An..." adları
        NavigableMap<String, String> prefixNəticəsi = rehber.subMap(
            prefix, true,
            prefix + "\uffff", true
        );
        System.out.println("'An' ilə başlayanlar: " + prefixNəticəsi);
        // {Anar=+994551234567}
    }
}
```

---

## LinkedHashMap vs TreeMap müqayisəsi

| Xüsusiyyət | LinkedHashMap | TreeMap |
|------------|---------------|---------|
| Əsas struktur | HashMap + ikiqat siyahı | Red-Black Tree |
| Sıralama | Daxiletmə və ya giriş sırası | Natural/Custom comparator |
| put/get/remove | O(1) ortalama | O(log n) |
| firstKey/lastKey | O(n) | **O(log n)** |
| ceiling/floor | Dəstəklənmir | **O(log n)** |
| headMap/tailMap | Dəstəklənmir | **O(log n)** |
| null key | 1 ədəd (HashMap kimi) | **Yox** (compareTo null-a qarşı) |
| Yaddaş | Həm bucket, həm siyahı | Yalnız tree node-ları |
| İstifadə | Sıranı qoru, tez giriş | Aralıq/naviqasiya axtarışı |

```java
import java.util.*;

public class LinkedHashMapVsTreeMap {
    public static void main(String[] args) {
        // LinkedHashMap — daxiletmə sırasını qoru
        Map<String, Integer> linked = new LinkedHashMap<>();
        linked.put("C", 3);
        linked.put("A", 1);
        linked.put("B", 2);
        System.out.println("LinkedHashMap: " + linked); // {C=3, A=1, B=2} — daxiletmə sırası

        // TreeMap — əlifba sırası
        Map<String, Integer> tree = new TreeMap<>();
        tree.put("C", 3);
        tree.put("A", 1);
        tree.put("B", 2);
        System.out.println("TreeMap: " + tree); // {A=1, B=2, C=3} — sıralı

        // Performans müqayisəsi
        int N = 100_000;
        Map<Integer, String> lhm = new LinkedHashMap<>(N);
        Map<Integer, String> tm = new TreeMap<>();

        long t1 = System.nanoTime();
        for (int i = 0; i < N; i++) lhm.put(i, "v" + i);
        System.out.println("LinkedHashMap put: " + (System.nanoTime() - t1) / 1_000_000 + "ms");

        t1 = System.nanoTime();
        for (int i = 0; i < N; i++) tm.put(i, "v" + i);
        System.out.println("TreeMap put: " + (System.nanoTime() - t1) / 1_000_000 + "ms");
        // TreeMap adətən 2-3x yavaş olur (log n factor)
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;

public class YanlisDoğru {

    // ❌ YANLIŞ: Sıralı Map lazımdırsa HashMap istifadə etmək
    void yanlisMap() {
        Map<String, Integer> map = new HashMap<>();
        map.put("Bakı", 1);
        map.put("Gəncə", 2);
        map.put("Sumqayıt", 3);
        // Sıra qarantı deyil!
        System.out.println(map); // {Gəncə=2, Sumqayıt=3, Bakı=1} — sırasız
    }

    // ✅ DOĞRU: Daxiletmə sırası lazımdırsa LinkedHashMap
    void dogruDaxiletmeSirasi() {
        Map<String, Integer> map = new LinkedHashMap<>();
        map.put("Bakı", 1);
        map.put("Gəncə", 2);
        map.put("Sumqayıt", 3);
        System.out.println(map); // {Bakı=1, Gəncə=2, Sumqayıt=3} ✅
    }

    // ✅ DOĞRU: Sıralı key lazımdırsa TreeMap
    void dogruSiraliKey() {
        Map<String, Integer> map = new TreeMap<>();
        map.put("Sumqayıt", 3);
        map.put("Bakı", 1);
        map.put("Gəncə", 2);
        System.out.println(map); // {Bakı=1, Gəncə=2, Sumqayıt=3} — əlifba sırası ✅
    }

    // ❌ YANLIŞ: TreeMap-də null key istifadəsi
    void yanlisNullKey() {
        TreeMap<String, Integer> map = new TreeMap<>();
        // map.put(null, 1); // NullPointerException!
        // Comparator null ilə compareTo edə bilmir
    }

    // ❌ YANLIŞ: Mutable obyekti TreeMap key-i kimi istifadə
    void yanlisMutableTreeKey() {
        TreeMap<List<Integer>, String> map = new TreeMap<>(
            Comparator.comparingInt(List::size)
        );
        List<Integer> key = new ArrayList<>(List.of(1, 2));
        map.put(key, "dəyər");
        key.add(3); // key dəyişdi → tree xarab oldu!
    }

    // ✅ DOĞRU: Immutable key istifadəsi
    void dogruImmutableKey() {
        TreeMap<String, String> map = new TreeMap<>();
        map.put("sabit_key", "dəyər"); // String immutable-dır ✅
    }

    // ❌ YANLIŞ: NavigableMap xüsusiyyəti lazımsa amma HashMap seçmək
    void yanlisRange(Map<Integer, String> prices) {
        // HashMap-lə aralıq axtarışı O(n):
        prices.entrySet().stream()
            .filter(e -> e.getKey() >= 100 && e.getKey() <= 500)
            .forEach(e -> System.out.println(e));
    }

    // ✅ DOĞRU: TreeMap ilə aralıq axtarışı O(log n)
    void dogruRange(TreeMap<Integer, String> prices) {
        prices.subMap(100, true, 500, true)
              .forEach((k, v) -> System.out.println(k + "=" + v));
    }
}
```

---

## İntervyu Sualları

**S1: LinkedHashMap HashMap-dən necə fərqlənir?**

LinkedHashMap HashMap-dən miras alır və əlavə olaraq ikiqat bağlı siyahı saxlayır. Bu siyahı ya daxiletmə sırasını (default), ya da giriş sırasını (accessOrder=true) qoruyur. Bütün HashMap əməliyyatları O(1) olaraq qalır, amma hər əlavə/silmə siyahını da yeniləyir.

**S2: LRU Cache necə implementasiya edilir?**

`LinkedHashMap(capacity, 0.75f, true)` — accessOrder=true ilə yaradılır. `removeEldestEntry()` override edilir ki, `size() > capacity` olduqda `true` qaytarsın. Bu zaman ən az istifadə edilən (siyahının başındakı) element silinir.

**S3: TreeMap nə üçün null key qəbul etmir?**

TreeMap key-ləri müqayisə etmək üçün `compareTo()` və ya `Comparator.compare()` istifadə edir. `null.compareTo(x)` `NullPointerException` atır. Buna görə null key qəbul edilmir.

**S4: TreeMap-in Big-O mürəkkəbliyi nədir?**

put, get, remove, containsKey — O(log n). firstKey, lastKey — O(log n). headMap, tailMap, subMap — O(log n) view yaradır, amma iterate O(k) burada k element sayı.

**S5: ceiling vs floor vs higher vs lower fərqi nədir?**

- `ceilingKey(k)` — k-ya bərabər VƏ ya böyük ən kiçik key (inclusive)
- `floorKey(k)` — k-ya bərabər VƏ ya kiçik ən böyük key (inclusive)
- `higherKey(k)` — k-dan BÖYÜK ən kiçik key (exclusive, k daxil deyil)
- `lowerKey(k)` — k-dan KİÇİK ən böyük key (exclusive, k daxil deyil)

**S6: TreeMap necə sıralanmış qalır?**

Red-Black Tree strukturu sayəsində — insert/delete zamanı ağac rotasiya əməliyyatları ilə yenidən balanslaşdırılır. Balanslaşdırılmış ağacda in-order traversal (sol → kök → sağ) sıralı sıra verir.

**S7: subMap view-u dəyişilərsə nə baş verir?**

`subMap()`, `headMap()`, `tailMap()` — orijinal TreeMap-ə baxan view-lar qaytarır. View-da edilən dəyişikliklər (put, remove) orijinal map-ə əks olunur. View-un hüdudlarından kənara çıxmaq `IllegalArgumentException` atır.
