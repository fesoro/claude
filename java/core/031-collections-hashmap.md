# 031 — HashMap — Daxili Quruluş və Mexanizmi
**Səviyyə:** Orta


## Mündəricat
- [HashMap nədir?](#hashmap-nədir)
- [Hashing mexanizmi](#hashing-mexanizmi)
- [Bucket massivi](#bucket-massivi)
- [Collision (toqquşma) idarəsi](#collision-toqquşma-idarəsi)
- [Linked List → Tree (Java 8+)](#linked-list--tree-java-8)
- [Load Factor və Resize/Rehash](#load-factor-və-resizerehash)
- [hashCode + equals müqaviləsi](#hashcode--equals-müqaviləsi)
- [HashMap vs Hashtable](#hashmap-vs-hashtable)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## HashMap nədir?

`HashMap<K,V>` — hashing əsasında işləyən key-value saxlama strukturudur. Ortalama O(1) get/put əməliyyatları təmin edir.

```java
import java.util.*;

public class HashMapGiriş {
    public static void main(String[] args) {
        // Yaratma üsulları
        Map<String, Integer> map1 = new HashMap<>();              // boş, capacity=16
        Map<String, Integer> map2 = new HashMap<>(32);           // ilkin capacity=32
        Map<String, Integer> map3 = new HashMap<>(32, 0.5f);    // capacity + loadFactor
        Map<String, Integer> map4 = new HashMap<>(map1);         // kopyala

        // Java 9+ — dəyişilməz map
        Map<String, Integer> immutable = Map.of("A", 1, "B", 2, "C", 3);

        // Əsas əməliyyatlar
        Map<String, Integer> ehtiyat = new HashMap<>();
        ehtiyat.put("kitab", 5);          // əlavə/yenilə — O(1) ortalama
        ehtiyat.put("qələm", 10);
        
        int say = ehtiyat.get("kitab");   // oxu — O(1) ortalama
        ehtiyat.remove("qələm");          // sil — O(1) ortalama
        boolean var = ehtiyat.containsKey("kitab"); // yoxla — O(1) ortalama

        System.out.println(ehtiyat); // {kitab=5}
    }
}
```

---

## Hashing mexanizmi

`put(key, value)` çağırıldığında HashMap aşağıdakı addımları atır:

```
1. key.hashCode() çağır
2. Hash-i spread et (internal hash funksiyası)
3. Bucket indeksini hesabla: index = hash & (capacity - 1)
4. O bucket-da saxla
```

```java
// HashMap-in daxili hash funksiyası (JDK mənbəyi)
// hashCode-u daha yaxşı "yaymaq" üçün — yuxarı bitləri aşağı bitsə XOR edir
static final int hash(Object key) {
    int h;
    // null key → hash 0 (bucket 0-da saxlanır)
    return (key == null) ? 0 : (h = key.hashCode()) ^ (h >>> 16);
}

// Bucket indeksi hesablanması (capacity 2^n olduğu üçün % əvəzinə & işlənir)
// index = hash & (n - 1)  // n = capacity
// Bu, hash % n ilə ekvivalentdir (capacity 2-nin qüvvəti olduqda)
```

```java
public class HashingNümunə {
    public static void main(String[] args) {
        // String.hashCode() necə işləyir
        String s = "Java";
        // hashCode = 'J'*31^3 + 'a'*31^2 + 'v'*31 + 'a'
        // = 74*29791 + 97*961 + 118*31 + 97
        System.out.println("Java".hashCode());   // 2301506
        System.out.println("Java".hashCode());   // 2301506 — həmişə eyni!

        // Eyni dəyər — eyni hashCode
        String s1 = new String("test");
        String s2 = new String("test");
        System.out.println(s1 == s2);             // false (fərqli obyektlər)
        System.out.println(s1.equals(s2));        // true
        System.out.println(s1.hashCode() == s2.hashCode()); // true — olmalıdır!

        // Bucket indeksi hesablaması (capacity=16)
        int capacity = 16;
        int hash = hash("Java"); // daxili hash funksiyası
        int index = hash & (capacity - 1); // bucket indeksi
        System.out.println("Bucket indeksi: " + index);
    }

    // HashMap-in daxili hash funksiyasını simulyasiya edirik
    static int hash(Object key) {
        int h = key.hashCode();
        return h ^ (h >>> 16);
    }
}
```

---

## Bucket massivi

HashMap daxilində `Node<K,V>[]` massivi var — hər element bir bucket-dır:

```java
// HashMap-in daxili strukturu (sadələşdirilmiş)
public class HashMapDaxili<K, V> {

    // Başlanğıc capacity — 2-nin qüvvəti olmalıdır
    static final int DEFAULT_INITIAL_CAPACITY = 16; // 1 << 4

    // Maksimum capacity
    static final int MAXIMUM_CAPACITY = 1 << 30;

    // Default load factor
    static final float DEFAULT_LOAD_FACTOR = 0.75f;

    // Bucket massivi (table)
    Node<K,V>[] table;

    // Faktiki element sayı
    int size;

    // Növbəti resize-a qədər element sayı (capacity * loadFactor)
    int threshold;

    // Node strukturu — linked list üçün
    static class Node<K,V> implements Map.Entry<K,V> {
        final int hash;   // hash dəyəri (hesablanmış)
        final K key;      // açar
        V value;          // dəyər
        Node<K,V> next;   // eyni bucket-dakı növbəti node

        Node(int hash, K key, V value, Node<K,V> next) {
            this.hash = hash;
            this.key = key;
            this.value = value;
            this.next = next;
        }
    }
}
```

### Görsel Təsvir

```
table[] (capacity=16):
  [0]  → null
  [1]  → Node{key="Bakı", val=1, next=→} → Node{key="Gəncə", val=2, next=null}
  [2]  → null
  [3]  → Node{key="Şuşa", val=3, next=null}
  ...
  [15] → null

"Bakı" və "Gəncə" eyni bucket-dadır → collision!
```

---

## Collision (toqquşma) idarəsi

İki fərqli key eyni bucket-a düşdükdə **collision** baş verir. Java HashMap-i **Separate Chaining** istifadə edir:

```java
import java.util.*;

public class CollisionNümunə {
    // Bu sinifdə bütün obyektlər eyni hashCode qaytarır
    static class BadHashKey {
        private final String dəyər;

        BadHashKey(String dəyər) { this.dəyər = dəyər; }

        @Override
        public int hashCode() {
            return 42; // ❌ YANLIŞ — bütün obyektlər eyni bucket-a düşür!
        }

        @Override
        public boolean equals(Object obj) {
            if (this == obj) return true;
            if (!(obj instanceof BadHashKey)) return false;
            return dəyər.equals(((BadHashKey) obj).dəyər);
        }
    }

    public static void main(String[] args) {
        Map<BadHashKey, String> map = new HashMap<>();

        // Hamısı bucket 42%16=10-a düşür — linked list (və ya tree) olur
        map.put(new BadHashKey("A"), "Dəyər A");
        map.put(new BadHashKey("B"), "Dəyər B");
        map.put(new BadHashKey("C"), "Dəyər C");

        // get("B") — bucket 10-a gedər, linked list boyunca "B"-ni axtarar
        // O(1) deyil, O(n) olur! Pis hashCode → pis performans
        System.out.println(map.get(new BadHashKey("B"))); // "Dəyər B"
    }
}
```

### put() əməliyyatının daxili axını

```java
// put(key, value) sadələşdirilmiş implementasiyası
final V putVal(int hash, K key, V value) {
    Node<K,V>[] tab;
    Node<K,V> p;
    int n, i;

    // 1. Cədvəl boşdursa, resize et (ilk put)
    if ((tab = table) == null || (n = tab.length) == 0)
        n = (tab = resize()).length;

    // 2. Bucket indeksini hesabla
    i = (n - 1) & hash;
    p = tab[i];

    // 3. Bucket boşdursa, birbaşa yaz
    if (p == null) {
        tab[i] = newNode(hash, key, value, null);
    } else {
        // 4. Collision var — bucket-a bax
        Node<K,V> e = null;

        // 4a. Bucket-ın birinci elementi eyni key-dir?
        if (p.hash == hash && (p.key == key || (key != null && key.equals(p.key)))) {
            e = p;
        }
        // 4b. TreeNode-dusa (Java 8+, threshold=8 keçildikdə)
        else if (p instanceof TreeNode) {
            e = ((TreeNode<K,V>)p).putTreeVal(this, tab, hash, key, value);
        }
        // 4c. Linked list-də axtar
        else {
            for (int binCount = 0; ; ++binCount) {
                if ((e = p.next) == null) {
                    // Sonda əlavə et
                    p.next = newNode(hash, key, value, null);
                    // Threshold keçilsə tree-yə çevir (Java 8+)
                    if (binCount >= TREEIFY_THRESHOLD - 1) // 8-1=7
                        treeifyBin(tab, hash);
                    break;
                }
                // Eyni key tapıldı
                if (e.hash == hash && (e.key == key || (key != null && key.equals(p.key))))
                    break;
                p = e;
            }
        }

        // 5. Mövcud key-in dəyərini yenilə
        if (e != null) {
            V oldValue = e.value;
            e.value = value;
            return oldValue;
        }
    }

    // 6. Threshold keçilsə resize et
    if (++size > threshold)
        resize();

    return null;
}
```

---

## Linked List → Tree (Java 8+)

```java
// Java 8-də əlavə edilmiş optimallaşdırma:
static final int TREEIFY_THRESHOLD = 8;  // Bu qədər elementdən sonra tree olur
static final int UNTREEIFY_THRESHOLD = 6; // Bu qədərə düşsə yenə list olur
static final int MIN_TREEIFY_CAPACITY = 64; // Tree üçün min table capacity

// Bir bucket-da 8+ element olduqda LinkedList → Red-Black Tree
// Bu zaman axtarış O(n)-dən O(log n)-ə yaxşılaşır
// Amma: yaxşı hashCode ilə bu heç vaxt baş verməz!
```

```java
public class TreeifyNümunə {
    // Eyni hash-ə sahib key-lər
    static class SameHashKey {
        final int id;
        SameHashKey(int id) { this.id = id; }

        @Override
        public int hashCode() { return 1; } // hamısı bucket 1-ə

        @Override
        public boolean equals(Object o) {
            return o instanceof SameHashKey && ((SameHashKey)o).id == id;
        }
    }

    public static void main(String[] args) {
        Map<SameHashKey, String> map = new HashMap<>();

        // 8 elementdən sonra bucket linked list-dən tree-yə keçir
        for (int i = 0; i < 12; i++) {
            map.put(new SameHashKey(i), "val" + i);
        }
        // İndi bucket 1-dəki structure Red-Black Tree-dir
        // get() O(log 12) işləyir, O(12) deyil
        System.out.println(map.get(new SameHashKey(7))); // "val7"
    }
}
```

---

## Load Factor və Resize/Rehash

```java
// Load Factor = size / capacity
// DEFAULT_LOAD_FACTOR = 0.75
// Threshold = capacity * loadFactor

// Nümunə:
// capacity=16, loadFactor=0.75
// threshold = 16 * 0.75 = 12
// 13-cü element əlavə edildikdə resize baş verir!

// Resize zamanı:
// 1. Yeni massiv (capacity * 2) yaradılır
// 2. Bütün elementlər yenidən hash edilir (rehash)
// 3. Yeni bucket indeksi hesablanır
```

```java
import java.util.*;

public class LoadFactorDemo {
    public static void main(String[] args) {
        // ── Müxtəlif Load Factor ssenariləri ──

        // Yüksək load factor (məsələn 0.9):
        // + Az resize → az yaddaş istifadəsi
        // - Daha çox collision → yavaş axtarış
        Map<String, Integer> yüksəkLF = new HashMap<>(16, 0.9f);

        // Aşağı load factor (məsələn 0.5):
        // + Az collision → sürətli axtarış
        // - Çox resize → çox yaddaş israfı
        Map<String, Integer> aşağıLF = new HashMap<>(16, 0.5f);

        // Default 0.75 — yaxşı balans
        Map<String, Integer> optimalLF = new HashMap<>();

        // Əvvəlcədən capacity hesablamaq:
        // Məsələn 100 element saxlamaq istəyirsən, default LF = 0.75
        // capacity = ceil(100 / 0.75) = 134, amma 2-nin qüvvəti → 256
        // Daha asan: new HashMap<>(128) — 128 * 0.75 = 96 < 100 → resize olar
        // Düzgün: new HashMap<>(256) — heç vaxt resize olmaz
        int ehtiyacEdilənElement = 100;
        int optimalCapacity = (int) Math.ceil(ehtiyacEdilənElement / 0.75) * 2;
        // Amma praktikada bu hesabı etmək çox nadir lazım olur
        Map<String, Integer> optimal = new HashMap<>(optimalCapacity);
    }
}
```

---

## hashCode + equals müqaviləsi

Bu müqavilə HashMap-in düzgün işləməsi üçün **vacibdir**:

```
Qayda 1: a.equals(b) → a.hashCode() == b.hashCode()  (MÜTLƏQ)
Qayda 2: a.hashCode() == b.hashCode() ↛ a.equals(b)  (ola bilər — collision)
Qayda 3: hashCode sabit olmalıdır (key-in hashCode-u dəyişməməlidir)
```

```java
import java.util.*;

public class HashCodeEqualsContract {

    // ❌ YANLIŞ: hashCode var, equals yoxdur
    static class YanlışSinif1 {
        int id;
        YanlışSinif1(int id) { this.id = id; }

        @Override
        public int hashCode() { return id; }
        // equals yoxdur — Object.equals() istifadə edilir (referans müqayisəsi)
        // İki fərqli obyek eyni id-ə malik olsa belə equals false qaytarır!
    }

    // ❌ YANLIŞ: equals var, hashCode yoxdur
    static class YanlışSinif2 {
        int id;
        YanlışSinif2(int id) { this.id = id; }

        @Override
        public boolean equals(Object o) {
            return o instanceof YanlışSinif2 && ((YanlışSinif2)o).id == id;
        }
        // hashCode yoxdur — Object.hashCode() istifadə edilir (sistem adresi)
        // Bərabər obyektlər fərqli bucket-a düşür — HashMap xarab işləyir!
    }

    // ✅ DOĞRU: hashCode VƏ equals birlikdə
    static class DüzgünSinif {
        final int id;
        final String ad;

        DüzgünSinif(int id, String ad) {
            this.id = id;
            this.ad = ad;
        }

        @Override
        public boolean equals(Object o) {
            if (this == o) return true;              // eyni referans
            if (!(o instanceof DüzgünSinif)) return false; // tip yoxlama
            DüzgünSinif other = (DüzgünSinif) o;
            return id == other.id && Objects.equals(ad, other.ad);
        }

        @Override
        public int hashCode() {
            // Objects.hash() — rahat helper metod
            return Objects.hash(id, ad);
        }
    }

    // Java 16+ — Record (avtomatik equals/hashCode)
    record Məhsul(int id, String ad) {}
    // Record avtomatik olaraq equals, hashCode, toString yaradır

    public static void main(String[] args) {
        // ❌ YanlışSinif2 ilə problem
        Map<YanlışSinif2, String> xarabMap = new HashMap<>();
        YanlışSinif2 k1 = new YanlışSinif2(1);
        xarabMap.put(k1, "dəyər");
        
        YanlışSinif2 k2 = new YanlışSinif2(1); // eyni məzmun
        System.out.println(k1.equals(k2));      // true
        System.out.println(xarabMap.get(k2));   // null!! — fərqli bucket-da axtarır

        // ✅ DüzgünSinif ilə
        Map<DüzgünSinif, String> düzgünMap = new HashMap<>();
        DüzgünSinif dk1 = new DüzgünSinif(1, "Java");
        düzgünMap.put(dk1, "dəyər");

        DüzgünSinif dk2 = new DüzgünSinif(1, "Java");
        System.out.println(dk1.equals(dk2));      // true
        System.out.println(düzgünMap.get(dk2));   // "dəyər" ✅

        // ✅ Record ilə
        Map<Məhsul, Integer> məhsulMap = new HashMap<>();
        məhsulMap.put(new Məhsul(1, "Laptop"), 5);
        System.out.println(məhsulMap.get(new Məhsul(1, "Laptop"))); // 5 ✅

        // ⚠️ Mutable key — VERY DANGEROUS
        Map<List<Integer>, String> tehlikeliMap = new HashMap<>();
        List<Integer> key = new ArrayList<>(List.of(1, 2, 3));
        tehlikeliMap.put(key, "dəyər");
        key.add(4); // ❌ hashCode dəyişdi! Map xarab oldu
        System.out.println(tehlikeliMap.get(key)); // null — tapılmır!
    }
}
```

---

## HashMap vs Hashtable

| Xüsusiyyət | HashMap | Hashtable |
|------------|---------|-----------|
| Thread safety | Deyil | Bəli (synchronized) |
| null key | 1 ədəd | İcazə verilmir |
| null value | İcazə verilir | İcazə verilmir |
| Performans | Sürətli | Yavaş (lock) |
| Iterator | fail-fast | fail-fast (Enumeration da var) |
| Miras | AbstractMap | Dictionary (köhnə) |
| Java versiyası | Java 2+ | Java 1 (köhnə) |

```java
import java.util.*;
import java.util.concurrent.*;

public class HashMapVsHashtable {
    public static void main(String[] args) {
        // ❌ YANLIŞ: Hashtable istifadə etmək (deprecated sayılır)
        Hashtable<String, Integer> hashtable = new Hashtable<>();
        // hashtable.put(null, 1);    // NullPointerException!
        // hashtable.put("key", null); // NullPointerException!

        // ✅ DOĞRU: Thread-safe lazımdırsa ConcurrentHashMap istifadə et
        Map<String, Integer> concurrentMap = new ConcurrentHashMap<>();
        concurrentMap.put("key", 1);
        // concurrentMap.put(null, 1); // NullPointerException (null key yoxdur)

        // ✅ DOĞRU: Thread-safe lazım deyilsə HashMap istifadə et
        Map<String, Integer> hashMap = new HashMap<>();
        hashMap.put(null, 1);   // null key qəbul edir
        hashMap.put("k", null); // null value qəbul edir

        // ✅ DOĞRU: Sadəcə sync lazımdırsa (amma ConcurrentHashMap daha yaxşı)
        Map<String, Integer> syncMap = Collections.synchronizedMap(new HashMap<>());
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;

public class HashMapYanlisDoğru {

    // ❌ YANLIŞ: entrySet əvəzinə keySet + get istifadəsi
    void yanlisIterasiya(Map<String, Integer> map) {
        for (String key : map.keySet()) {
            Integer val = map.get(key); // əlavə axtarış — O(1) amma lazımsız
            System.out.println(key + "=" + val);
        }
    }

    // ✅ DOĞRU: entrySet birbaşa key və value verir
    void dogruIterasiya(Map<String, Integer> map) {
        for (Map.Entry<String, Integer> entry : map.entrySet()) {
            System.out.println(entry.getKey() + "=" + entry.getValue());
        }
        // Java 8+ forEach:
        map.forEach((k, v) -> System.out.println(k + "=" + v));
    }

    // ❌ YANLIŞ: containsKey + put iki ayrı əməliyyat
    void yanlisConditionalPut(Map<String, Integer> sayac, String söz) {
        if (!sayac.containsKey(söz)) {
            sayac.put(söz, 0);
        }
        sayac.put(söz, sayac.get(söz) + 1);
    }

    // ✅ DOĞRU: getOrDefault və ya merge istifadəsi
    void dogruConditionalPut(Map<String, Integer> sayac, String söz) {
        // getOrDefault
        sayac.put(söz, sayac.getOrDefault(söz, 0) + 1);

        // merge — daha elegantdır
        sayac.merge(söz, 1, Integer::sum);

        // computeIfAbsent — daha mürəkkəb dəyərlər üçün
        // sayac.computeIfAbsent(söz, k -> new ArrayList<>()).add("dəyər");
    }

    // ❌ YANLIŞ: Mutable sinfi key kimi istifadə
    void yanlisMutableKey() {
        Map<StringBuilder, String> map = new HashMap<>();
        StringBuilder sb = new StringBuilder("key");
        map.put(sb, "dəyər");
        sb.append("_dəyişdi"); // hashCode dəyişdi → map xarab oldu!
        System.out.println(map.get(sb)); // null — tapılmır
    }

    // ✅ DOĞRU: İmmutable key istifadəsi
    void dogruImmutableKey() {
        Map<String, String> map = new HashMap<>();
        String key = "key"; // String immutable-dır
        map.put(key, "dəyər");
        // key dəyişə bilməz — hashCode sabitdir
        System.out.println(map.get(key)); // "dəyər" ✅
    }
}
```

---

## İntervyu Sualları

**S1: HashMap necə işləyir?**

put(k,v) çağırıldığında: 1) k.hashCode() çağrılır, 2) daxili hash funksiyası ilə yayılır, 3) `index = hash & (capacity-1)` ilə bucket tapılır, 4) o bucket-a node əlavə edilir. Collision-da linked list (Java 8+: 8+ elementdə tree) istifadə edilir.

**S2: Default capacity və load factor nədir?**

Capacity = 16, Load Factor = 0.75. Threshold = 16 × 0.75 = 12. 13-cü elementdə capacity 32-yə iki qat artır və bütün elementlər rehash edilir.

**S3: Java 8-də HashMap-ə nə əlavə edildi?**

Bir bucket-da 8+ element olduqda (və table size ≥ 64) linked list Red-Black Tree-yə çevrilir. Bu, ən pis halda O(n) əvəzinə O(log n) təmin edir.

**S4: null key HashMap-də harada saxlanır?**

Bucket 0-da. `hash(null)` 0 qaytarır.

**S5: hashCode müqaviləsini pozsaq nə baş verir?**

`equals()`-i true qaytarır amma `hashCode()`-lar fərqlidirsə — bərabər obyektlər fərqli bucket-lara düşür. `map.get(k2)` tapılmır, `containsKey(k2)` false qaytarır. HashMap düzgün işləmir.

**S6: HashMap-i ConcurrentHashMap ilə nə vaxt əvəz etmək lazımdır?**

Bir neçə thread eyni vaxtda Map-ə yazırsa. HashMap thread-safe deyil — data corruption və infinite loop (Java 7-də resize zamanı) baş verə bilər.

**S7: HashMap-in ən pis halda mürəkkəbliyi nədir?**

Java 7-də O(n) (bütün elementlər bir bucket-da). Java 8+-da O(log n) (tree threshold-dan sonra).

**S8: HashMap sıranı qoruyurmu?**

Xeyr. Daxiletmə sırası qorunmur. Sıralı saxlamaq üçün `LinkedHashMap` (daxiletmə sırası) və ya `TreeMap` (key sırası) istifadə et.
