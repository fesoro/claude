# 12. ArrayList və LinkedList — Daxili Quruluş və Müqayisə

## Mündəricat
- [ArrayList — Daxili Quruluş](#arraylist--daxili-quruluş)
- [ArrayList Böyümə Mexanizmi](#arraylist-böyümə-mexanizmi)
- [LinkedList — Daxili Quruluş](#linkedlist--daxili-quruluş)
- [Big-O Müqayisəsi](#big-o-müqayisəsi)
- [Yaddaş istifadəsi](#yaddaş-istifadəsi)
- [RandomAccess interfeysi](#randomaccess-interfeysi)
- [Nə vaxt hansını seçmeli?](#nə-vaxt-hansını-seçmeli)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## ArrayList — Daxili Quruluş

`ArrayList` daxilində sadəcə bir `Object[]` (massiv) saxlayır. Bu massivin ölçüsü **capacity** adlanır — aktual element sayından (size) böyük ola bilər.

```java
// ArrayList-in sadələşdirilmiş daxili strukturu (JDK mənbəyi əsasında)
public class ArrayListDaxili<E> {

    private static final int DEFAULT_CAPACITY = 10; // başlanğıc tutum

    Object[] elementData; // elementlər bu massivdə saxlanır
    private int size;     // faktiki element sayı (capacity deyil!)

    // Boş konstruktor — ilk add-da massiv yaradılır (lazy init)
    public ArrayListDaxili() {
        this.elementData = new Object[0]; // başda boş massiv
    }

    // Capacity ilə konstruktor
    public ArrayListDaxili(int initialCapacity) {
        this.elementData = new Object[initialCapacity];
    }

    @SuppressWarnings("unchecked")
    public E get(int index) {
        // Yoxlama + massiv elementi qaytarma — O(1)
        Objects.checkIndex(index, size);
        return (E) elementData[index];
    }
}
```

```java
import java.util.ArrayList;
import java.lang.reflect.Field;

public class ArrayListYaddash {
    public static void main(String[] args) throws Exception {
        ArrayList<Integer> list = new ArrayList<>();

        // Reflection ilə daxili massivi görmək
        Field field = ArrayList.class.getDeclaredField("elementData");
        field.setAccessible(true);

        System.out.println("Boş list — capacity: " +
            ((Object[]) field.get(list)).length); // 0 (Java 8+, lazy init)

        list.add(1); // İlk əlavədə capacity 10 olur
        System.out.println("1 element — capacity: " +
            ((Object[]) field.get(list)).length); // 10

        // 10 element əlavə et
        for (int i = 2; i <= 10; i++) list.add(i);
        System.out.println("10 element — capacity: " +
            ((Object[]) field.get(list)).length); // 10

        list.add(11); // 11-ci elementdə resize baş verir
        System.out.println("11 element — capacity: " +
            ((Object[]) field.get(list)).length); // 15 (10 * 1.5)
    }
}
```

---

## ArrayList Böyümə Mexanizmi

ArrayList dolu olduqda yeni, daha böyük massiv yaradır və köhnə elementləri köçürür:

```java
// JDK-dakı grow() metodunun sadələşdirilmiş versiyası
private Object[] grow(int minCapacity) {
    int oldCapacity = elementData.length;
    
    if (oldCapacity > 0) {
        // Yeni capacity = köhnə capacity + köhnə capacity / 2
        // Yəni: yeni = köhnə * 1.5
        int newCapacity = oldCapacity + (oldCapacity >> 1); // >> 1 == / 2
        
        // Yeni massiv yarat
        Object[] newArray = new Object[newCapacity];
        
        // Köhnə elementləri köçür — System.arraycopy istifadə edir
        System.arraycopy(elementData, 0, newArray, 0, size);
        
        elementData = newArray;
    }
    return elementData;
}
```

### Böyümə ardıcıllığı

```
Başlanğıc:  0
1-ci add:  10
11-ci add: 15
16-ci add: 22
23-cü add: 33
34-cü add: 49
...
```

```java
import java.util.*;

public class ArrayListBoyume {
    public static void main(String[] args) throws Exception {
        // İlkin capacity təyin etmək — resize-ı azaldır, performans artır
        // ❌ YANLIŞ — neçə element gözlədiyini bilirsənsə, defolt istifadə etmə
        List<Integer> yavaş = new ArrayList<>();
        for (int i = 0; i < 10_000; i++) yavaş.add(i); // çoxlu resize baş verir

        // ✅ DOĞRU — capacity-ni əvvəlcədən təyin et
        List<Integer> sürətli = new ArrayList<>(10_000);
        for (int i = 0; i < 10_000; i++) sürətli.add(i); // resize baş vermir

        // trimToSize() — istifadə edilməyən yaddaşı azaltmaq üçün
        ArrayList<Integer> optimizasiya = new ArrayList<>(100);
        optimizasiya.add(1);
        optimizasiya.add(2);
        optimizasiya.trimToSize(); // capacity 2-yə endər (yaddaş qənaəti)
        
        // ensureCapacity() — toplu əlavə etmədən əvvəl
        ArrayList<String> bulk = new ArrayList<>();
        bulk.ensureCapacity(50_000); // əvvəlcədən yer ayır
        for (int i = 0; i < 50_000; i++) {
            bulk.add("Element-" + i);
        }
    }
}
```

---

## LinkedList — Daxili Quruluş

`LinkedList` **ikitərəfli bağlı siyahı** (doubly-linked list) istifadə edir. Hər element bir `Node` obyektindədir:

```java
// LinkedList-in daxili Node strukturu (JDK mənbəyi)
private static class Node<E> {
    E item;       // faktiki dəyər
    Node<E> next; // növbəti node-a göstərici
    Node<E> prev; // əvvəlki node-a göstərici

    Node(Node<E> prev, E element, Node<E> next) {
        this.item = element;
        this.next = next;
        this.prev = prev;
    }
}

// LinkedList-in daxili strukturu
public class LinkedListDaxili<E> {
    Node<E> first; // ilk node (head)
    Node<E> last;  // son node (tail)
    int size;      // element sayı

    // Əvvələ əlavə etmə — O(1)
    private void linkFirst(E e) {
        Node<E> f = first;
        Node<E> newNode = new Node<>(null, e, f); // prev=null, next=köhnə first
        first = newNode;
        if (f == null)
            last = newNode; // siyahı boş idi
        else
            f.prev = newNode; // köhnə ilk elementin prev-ini yenilə
        size++;
    }

    // Sona əlavə etmə — O(1)
    private void linkLast(E e) {
        Node<E> l = last;
        Node<E> newNode = new Node<>(l, e, null); // prev=köhnə last, next=null
        last = newNode;
        if (l == null)
            first = newNode; // siyahı boş idi
        else
            l.next = newNode; // köhnə son elementin next-ini yenilə
        size++;
    }
}
```

### Görsel Təsvir

```
LinkedList: [A] ↔ [B] ↔ [C] ↔ [D]

first ──→ Node{prev=null, item="A", next=→}
              ↕
          Node{prev=←, item="B", next=→}
              ↕
          Node{prev=←, item="C", next=→}
              ↕
          Node{prev=←, item="D", next=null} ←── last
```

```java
import java.util.*;

public class LinkedListNümunə {
    public static void main(String[] args) {
        LinkedList<String> list = new LinkedList<>();

        // Əvvələ əlavə — O(1)
        list.addFirst("B");
        list.addFirst("A"); // [A, B]

        // Sona əlavə — O(1)
        list.addLast("C");
        list.addLast("D"); // [A, B, C, D]

        // get(index) — O(n/2) — ortaya qədər gedir
        String ücüncü = list.get(2); // "C" — 2 addım gedir

        // Əvvəldən silmə — O(1)
        String birinci = list.removeFirst(); // "A"

        // Sondan silmə — O(1)
        String sonuncu = list.removeLast(); // "D"

        System.out.println(list); // [B, C]

        // Stack kimi istifadə
        Deque<String> stack = new LinkedList<>();
        stack.push("birinci");  // addFirst
        stack.push("ikinci");   // addFirst
        System.out.println(stack.pop()); // "ikinci" — LIFO

        // Queue kimi istifadə
        Queue<String> queue = new LinkedList<>();
        queue.offer("birinci"); // addLast
        queue.offer("ikinci");  // addLast
        System.out.println(queue.poll()); // "birinci" — FIFO
    }
}
```

---

## Big-O Müqayisəsi

| Əməliyyat | ArrayList | LinkedList | Qeyd |
|-----------|-----------|------------|------|
| `get(i)` | **O(1)** | O(n) | ArrayList massiv indeksləməsi edir |
| `add(e)` (sona) | O(1) amortized | **O(1)** | ArrayList bəzən resize edir |
| `add(i, e)` (ortaya) | O(n) | O(n) | LL axtarış, AL sürüşmə |
| `remove(i)` | O(n) | O(n) | LL axtarış, AL sürüşmə |
| `removeFirst()` | O(n) | **O(1)** | AL bütün elementləri sürüşdürür |
| `removeLast()` | **O(1)** | **O(1)** | Hər ikisi son elementi bilir |
| `contains(o)` | O(n) | O(n) | Hər ikisi lineer axtarış |
| `size()` | O(1) | O(1) | Hər ikisi sayı saxlayır |
| `Iterator.next()` | O(1) | O(1) | Hər ikisi sürətli iterate |

```java
import java.util.*;

public class PerformansTest {
    public static void main(String[] args) {
        int N = 100_000;

        // ── ArrayList ──
        List<Integer> arrayList = new ArrayList<>();

        // Sona əlavə — ArrayList sürətli
        long basla = System.nanoTime();
        for (int i = 0; i < N; i++) arrayList.add(i);
        System.out.printf("ArrayList sona əlavə: %,d ns%n",
            System.nanoTime() - basla);

        // Əvvələ əlavə — ArrayList YAVAŞ (bütün elementlər sürüşür)
        basla = System.nanoTime();
        for (int i = 0; i < 1000; i++) arrayList.add(0, i);
        System.out.printf("ArrayList əvvələ əlavə (1000): %,d ns%n",
            System.nanoTime() - basla);

        // ── LinkedList ──
        List<Integer> linkedList = new LinkedList<>();

        // Sona əlavə
        basla = System.nanoTime();
        for (int i = 0; i < N; i++) linkedList.add(i);
        System.out.printf("LinkedList sona əlavə: %,d ns%n",
            System.nanoTime() - basla);

        // Əvvələ əlavə — LinkedList sürətli (yalnız pointer dəyişir)
        basla = System.nanoTime();
        for (int i = 0; i < 1000; i++) ((LinkedList<Integer>)linkedList).addFirst(i);
        System.out.printf("LinkedList əvvələ əlavə (1000): %,d ns%n",
            System.nanoTime() - basla);

        // get(i) — ArrayList ÇOXLU daha sürətli
        basla = System.nanoTime();
        for (int i = 0; i < N; i++) arrayList.get(i);
        System.out.printf("ArrayList get: %,d ns%n", System.nanoTime() - basla);

        basla = System.nanoTime();
        // LinkedList üçün get çox yavaşdır — istifadə etmə!
        for (int i = 0; i < 1000; i++) linkedList.get(i); // yalnız 1000 dəfə
        System.out.printf("LinkedList get (1000): %,d ns%n",
            System.nanoTime() - basla);
    }
}
```

---

## Yaddaş istifadəsi

```java
// ArrayList yaddaş istifadəsi:
// ──────────────────────────────
// ArrayList obyekti: ~32 bytes
// elementData massivi: 16 + n * 4 bytes (referans)
// Hər element (Integer): ~16 bytes
// Boş yer (capacity - size): israf olunur

// LinkedList yaddaş istifadəsi:
// ────────────────────────────────
// LinkedList obyekti: ~32 bytes
// Hər Node: ~24 bytes (item ref + prev ref + next ref)
// Hər element (Integer): ~16 bytes
// Node overhead: ÇOX (hər element üçün əlavə 24 bytes Node)

// Nümunə: 1 milyon Integer saxlamaq
// ArrayList: ~4 MB (referanslar) + ~16 MB (Integer obyektlər) ≈ 20 MB
// LinkedList: ~24 MB (Nodes) + ~16 MB (Integer obyektlər) ≈ 40 MB
// → ArrayList ~2x az yaddaş istifadə edir!

public class YaddashMüqayisəsi {
    public static void main(String[] args) {
        Runtime runtime = Runtime.getRuntime();

        // ArrayList
        runtime.gc();
        long before = runtime.totalMemory() - runtime.freeMemory();
        List<Integer> arrayList = new ArrayList<>(1_000_000);
        for (int i = 0; i < 1_000_000; i++) arrayList.add(i);
        long afterAL = runtime.totalMemory() - runtime.freeMemory();
        System.out.println("ArrayList yaddaş: " + (afterAL - before) / 1024 + " KB");

        arrayList = null;
        runtime.gc();

        // LinkedList
        before = runtime.totalMemory() - runtime.freeMemory();
        List<Integer> linkedList = new LinkedList<>();
        for (int i = 0; i < 1_000_000; i++) linkedList.add(i);
        long afterLL = runtime.totalMemory() - runtime.freeMemory();
        System.out.println("LinkedList yaddaş: " + (afterLL - before) / 1024 + " KB");
        // LinkedList çox daha çox yaddaş istifadə edir!
    }
}
```

---

## RandomAccess interfeysi

`RandomAccess` — marker interface (metodu yoxdur), "bu kolleksiya tez indeks girişini dəstəkləyir" deməkdir:

```java
import java.util.*;

public class RandomAccessDemo {
    public static void main(String[] args) {
        List<Integer> arrayList = new ArrayList<>(List.of(1, 2, 3, 4, 5));
        List<Integer> linkedList = new LinkedList<>(List.of(1, 2, 3, 4, 5));

        // ArrayList RandomAccess implement edir
        System.out.println(arrayList instanceof RandomAccess); // true
        // LinkedList RandomAccess implement etmir
        System.out.println(linkedList instanceof RandomAccess); // false

        // Alqoritmlər bu interfeysi yoxlayır:
        iterasiyaEt(arrayList);  // for dövrəsi istifadə eder (sürətli)
        iterasiyaEt(linkedList); // Iterator istifadə edir (sürətli)
    }

    static void iterasiyaEt(List<Integer> list) {
        if (list instanceof RandomAccess) {
            // İndeks dövrəsi — RandomAccess üçün optimal
            for (int i = 0; i < list.size(); i++) {
                int val = list.get(i); // ArrayList üçün O(1)
            }
        } else {
            // Iterator — RandomAccess olmayan üçün optimal
            for (Integer val : list) { // LinkedList üçün O(1) per step
                // işlə
            }
        }
    }
}
```

> **Diqqət:** `Collections.binarySearch()` RandomAccess yoxlaması edir. ArrayList-də O(log n), LinkedList-də O(n) işləyir.

---

## Nə vaxt hansını seçmeli?

```java
import java.util.*;

public class NəVaxtHansı {

    // ✅ ArrayList seç:
    // 1. Tez-tez get(i) istifadə edirsən
    // 2. Əsasən sona əlavə edirsən
    // 3. Yaddaş qənaəti vacibdir
    // 4. İterate edirsən (cache-friendly — ardıcıl yaddaş)
    void arrayListHalları() {
        List<String> products = new ArrayList<>();
        // API-dən data alıb oxuyursan
        products.add("Laptop");
        products.add("Telefon");
        String ikinci = products.get(1); // tez-tez belə edirsən
        
        // Collections.sort ArrayList-lə daha sürətli işləyir
        Collections.sort(products);
    }

    // ✅ LinkedList seç (nadir hallarda):
    // 1. Tez-tez əvvəldən element əlavə/silmə edirsən
    // 2. Deque/Queue kimi istifadə edirsən
    // 3. get(i) demək olar ki, işlətmirsən
    void linkedListHalları() {
        // Queue kimi — amma ArrayDeque daha yaxşıdır!
        Deque<String> queue = new LinkedList<>();
        queue.addLast("İş 1");
        queue.addLast("İş 2");
        String növbəti = queue.removeFirst();

        // Stack kimi — amma ArrayDeque daha yaxşıdır!
        Deque<String> stack = new LinkedList<>();
        stack.addFirst("Birinci");
        stack.addFirst("İkinci");
        String üst = stack.removeFirst();
    }

    // ✅ ArrayDeque seç (LinkedList-in əksər halları üçün):
    // LinkedList-dən daha az yaddaş, daha sürətli
    void arrayDequeHalları() {
        Deque<String> deque = new ArrayDeque<>();
        deque.addFirst("Əvvəl");
        deque.addLast("Son");
        deque.removeFirst(); // sürətli
        deque.removeLast();  // sürətli
    }
}
```

### Yekun Qərar Ağacı

```
Kolleksiya lazımdır
        │
        ▼
İndeks ilə giriş lazımdır?
   Bəli → ArrayList ✅
   Xeyr ↓
        │
Əvvəl/axır dəyişiklik çoxdur?
   Bəli → ArrayDeque ✅ (LinkedList-dən yaxşı)
   Xeyr ↓
        │
Ümumi məqsədli siyahı?
   → ArrayList ✅ (default seçim)
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;

public class YanlisDoğru {

    // ❌ YANLIŞ: LinkedList-i get(i) ilə iterate etmək
    void yanlisIteration() {
        List<String> list = new LinkedList<>(Collections.nCopies(10_000, "x"));
        for (int i = 0; i < list.size(); i++) {
            String s = list.get(i); // Hər get(i) O(n) — toplam O(n²)!
        }
    }

    // ✅ DOĞRU: LinkedList-i Iterator/for-each ilə iterate etmək
    void dogruIteration() {
        List<String> list = new LinkedList<>(Collections.nCopies(10_000, "x"));
        for (String s : list) { // Iterator istifadə edir — O(n) toplam
            // işlə
        }
    }

    // ❌ YANLIŞ: Hər dəfə list.size() çağırmaq (ArrayList üçün OK amma alışqanlıq)
    void yanlisSize(List<String> list) {
        for (int i = 0; i < list.size(); i++) { // list.size() hər dəfə çağırılır
            System.out.println(list.get(i));
        }
    }

    // ✅ DOĞRU: size-ı saxlamaq (LinkedList üçün fərq yoxdur amma yaxşı alışqanlıq)
    void dogruSize(List<String> list) {
        int n = list.size(); // bir dəfə saxla
        for (int i = 0; i < n; i++) {
            System.out.println(list.get(i));
        }
    }

    // ❌ YANLIŞ: Çox element əlavə edəcəksə capacity verməmək
    void yanlisCapacity() {
        List<Integer> list = new ArrayList<>(); // capacity yox — çox resize
        for (int i = 0; i < 1_000_000; i++) list.add(i);
    }

    // ✅ DOĞRU: Bilirsənsə capacity ver
    void dogruCapacity() {
        List<Integer> list = new ArrayList<>(1_000_000); // bir dəfə massiv yaranır
        for (int i = 0; i < 1_000_000; i++) list.add(i);
    }

    // ❌ YANLIŞ: Queue/Stack üçün LinkedList seçmək
    void yanlisQueueStack() {
        Queue<String> queue = new LinkedList<>(); // daha çox yaddaş, yavaş
        Deque<String> stack = new LinkedList<>(); // daha çox yaddaş, yavaş
    }

    // ✅ DOĞRU: Queue/Stack üçün ArrayDeque seçmək
    void dogruQueueStack() {
        Queue<String> queue = new ArrayDeque<>();  // az yaddaş, sürətli
        Deque<String> stack = new ArrayDeque<>();  // az yaddaş, sürətli
    }
}
```

---

## İntervyu Sualları

**S1: ArrayList-in daxilində nə saxlanır?**

`Object[]` massivi. `size` faktiki element sayıdır, `elementData.length` isə capacity-dir. Java 8+-da boş ArrayList-in capacity-si 0-dır (lazy initialization).

**S2: ArrayList neçə dəfə böyüyür?**

1.5 dəfə (yəni `newCapacity = oldCapacity + oldCapacity / 2`). Java 6-da 2 dəfə idi.

**S3: LinkedList get(i) niyə O(n)-dir?**

LinkedList-də elementlər ardıcıl yaddaqda deyil, hər biri ayrı Node-dadır. i-ci elementə çatmaq üçün first/last-dan başlayıb n/2 addım gəzmək lazımdır.

**S4: LinkedList-i nə zaman ArrayList-ə üstün tutarsan?**

Praktiki olaraq demək olar ki, heç vaxt. Əksər hallarda `ArrayList` daha sürətli və az yaddaş istifadə edir. Əvvəl/axır əməliyyatları üçün `ArrayDeque` LinkedList-dən daha yaxşıdır.

**S5: RandomAccess interfeysi nə işə yarayır?**

Marker interface-dir — metodu yoxdur. `Collections.binarySearch()` kimi alqoritmlər bu interfeysi yoxlayır: `RandomAccess` varsa indeks dövrəsi, yoxsa Iterator istifadə edir.

**S6: ArrayList-i thread-safe etmək üçün nə etmək olar?**

```java
List<String> safe = Collections.synchronizedList(new ArrayList<>());
// Və ya
List<String> copyOnWrite = new CopyOnWriteArrayList<>();
```

**S7: `trimToSize()` nə edir?**

ArrayList-in capacity-sini faktiki size-a endirər. Böyük ArrayList-i doldurub bitirdikdən sonra yaddaşı azaltmaq üçün istifadə edilir.

**S8: ArrayList-in `subList()` metodu nə qaytarır?**

Original list-in bir hissəsinə baxan (backing store olaraq istifadə edən) bir `view` qaytarır. Sublist-ə edilən dəyişikliklər original list-ə əks olunur.
