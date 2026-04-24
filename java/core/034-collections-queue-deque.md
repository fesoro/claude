# 034 — Queue və Deque — Növbə Strukturları
**Səviyyə:** Orta


## Mündəricat
- [Queue interfeysi](#queue-interfeysi)
- [offer/poll/peek vs add/remove/element](#offerpollpeek-vs-addremoveelement)
- [PriorityQueue — heap əsaslı](#priorityqueue--heap-əsaslı)
- [Xüsusi Comparator ilə PriorityQueue](#xüsusi-comparator-ilə-priorityqueue)
- [ArrayDeque — stack və queue](#arraydeque--stack-və-queue)
- [Deque metodları](#deque-metodları)
- [LinkedList vs ArrayDeque](#linkedlist-vs-arraydeque)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Queue interfeysi

`Queue<E>` — FIFO (First In, First Out) prinsipini həyata keçirən interfeys:

```
Əlavə etmə → [A, B, C, D] → Silmə/Oxuma
              tail          head
```

```java
// Queue interfeysinin metodları
public interface Queue<E> extends Collection<E> {
    // ── Əlavə etmə ──
    boolean add(E e);   // uğursuzluqda IllegalStateException atır
    boolean offer(E e); // uğursuzluqda false qaytarır (daha təhlükəsiz)

    // ── Silmə ──
    E remove();  // boş olduqda NoSuchElementException atır
    E poll();    // boş olduqda null qaytarır (daha təhlükəsiz)

    // ── Baxma (element silmədən) ──
    E element(); // boş olduqda NoSuchElementException atır
    E peek();    // boş olduqda null qaytarır (daha təhlükəsiz)
}
```

---

## offer/poll/peek vs add/remove/element

```java
import java.util.*;

public class QueueMetodları {
    public static void main(String[] args) {
        Queue<String> queue = new LinkedList<>();

        // ── add vs offer ──
        queue.add("Birinci");   // normal vəziyyətdə eyni işləyir
        queue.offer("İkinci");  // capacity-məhdud queue-da fərq var

        // Capacity-məhdud queue nümunəsi
        Queue<Integer> məhdudQueue = new ArrayBlockingQueue<>(2);
        məhdudQueue.offer(1);
        məhdudQueue.offer(2);
        boolean əlavəEdildi = məhdudQueue.offer(3); // false — dolu, xəta atmır
        System.out.println("Əlavə edildi: " + əlavəEdildi); // false
        // məhdudQueue.add(3); // IllegalStateException — dolu!

        // ── poll vs remove ──
        Queue<String> queue2 = new LinkedList<>(List.of("A", "B", "C"));

        String elem1 = queue2.poll();    // "A" — silir
        String elem2 = queue2.remove();  // "B" — silir

        queue2.clear();
        String boş1 = queue2.poll();     // null — boş queue, xəta yoxdur
        // String boş2 = queue2.remove(); // NoSuchElementException!

        // ── peek vs element ──
        Queue<String> queue3 = new LinkedList<>(List.of("X", "Y"));

        String bax1 = queue3.peek();    // "X" — silmir
        String bax2 = queue3.element(); // "X" — silmir

        queue3.clear();
        String peekBoş = queue3.peek();   // null — boş queue, xəta yoxdur
        // String elemBoş = queue3.element(); // NoSuchElementException!

        System.out.println("Tövsiyə: offer/poll/peek — daha təhlükəsizdir!");
    }
}
```

### Xülasə cədvəli

| Əməliyyat | Xəta atar | Xəta atmaz (təhlükəsiz) |
|-----------|-----------|------------------------|
| Əlavə et | `add(e)` | `offer(e)` |
| Baş elementi sil | `remove()` | `poll()` |
| Baş elementə bax | `element()` | `peek()` |

---

## PriorityQueue — heap əsaslı

`PriorityQueue` — **Min-Heap** (ikili yığın) strukturunda işləyir. `poll()` həmişə ən kiçik (prioriteti ən yüksək) elementi qaytarır:

```java
import java.util.*;

public class PriorityQueueNümunə {
    public static void main(String[] args) {
        // Default: Min-Heap — ən kiçik element prioritetlidir
        PriorityQueue<Integer> minHeap = new PriorityQueue<>();
        minHeap.offer(5);
        minHeap.offer(2);
        minHeap.offer(8);
        minHeap.offer(1);
        minHeap.offer(9);

        System.out.print("Min-Heap sırası: ");
        while (!minHeap.isEmpty()) {
            System.out.print(minHeap.poll() + " ");
        }
        // 1 2 5 8 9 — ən kiçikdən ən böyüyə

        System.out.println();

        // Max-Heap — ən böyük element prioritetlidir
        PriorityQueue<Integer> maxHeap = new PriorityQueue<>(Comparator.reverseOrder());
        maxHeap.offer(5);
        maxHeap.offer(2);
        maxHeap.offer(8);
        maxHeap.offer(1);

        System.out.print("Max-Heap sırası: ");
        while (!maxHeap.isEmpty()) {
            System.out.print(maxHeap.poll() + " ");
        }
        // 8 5 2 1 — ən böyükdən ən kiçiyə

        System.out.println();

        // ⚠️ PriorityQueue-nun iterasiyası sıralı deyil!
        PriorityQueue<Integer> pq = new PriorityQueue<>(List.of(5, 2, 8, 1));
        System.out.println("Iterator sırası: " + pq);
        // [1, 2, 8, 5] — heap strukturu, sıralı deyil!
        // poll() ilə çıxaranda sıralı olur
    }
}
```

### Heap-in daxili quruluşu

```
Min-Heap: [1, 2, 5, 8, 9] daxili massivdə:

           1          ← kök (minimum)
          / \
         2   5
        / \
       8   9

Massivdə: [1, 2, 5, 8, 9]
  - Sol övlad: index = parent*2 + 1
  - Sağ övlad: index = parent*2 + 2
  - Valideyn: index = (child-1) / 2
```

```java
// PriorityQueue performansı:
// offer() — O(log n)   — element əlavə edilir, heap property bərpa edilir
// poll()  — O(log n)   — kök çıxarılır, heap property bərpa edilir
// peek()  — O(1)       — kök (minimum/maksimum) baxılır
// contains() — O(n)   — linear axtarış (daxili massivdə)
// remove(obj) — O(n)  — əvvəlcə tap, sonra sil
```

---

## Xüsusi Comparator ilə PriorityQueue

```java
import java.util.*;

public class XüsuliPriorityQueue {

    record Tapşırıq(String ad, int prioritet, int son_tarix) {}

    public static void main(String[] args) {

        // SSENARIY 1: Tapşırıq meneceri — prioritetə görə
        PriorityQueue<Tapşırıq> tapşırıqlar = new PriorityQueue<>(
            Comparator.comparingInt(Tapşırıq::prioritet).reversed() // yüksək prioritet əvvəl
                      .thenComparingInt(Tapşırıq::son_tarix)        // sonra son tarix
        );

        tapşırıqlar.offer(new Tapşırıq("Email yaz", 2, 5));
        tapşırıqlar.offer(new Tapşırıq("Bug düzəlt", 5, 1));
        tapşırıqlar.offer(new Tapşırıq("Kod review", 3, 3));
        tapşırıqlar.offer(new Tapşırıq("Deploy et", 5, 2));

        System.out.println("İş sırası:");
        while (!tapşırıqlar.isEmpty()) {
            Tapşırıq t = tapşırıqlar.poll();
            System.out.println("  " + t.ad() + " (prioritet=" + t.prioritet() + ")");
        }
        // Bug düzəlt (p=5, tarix=1)
        // Deploy et (p=5, tarix=2)
        // Kod review (p=3)
        // Email yaz (p=2)

        // SSENARIY 2: Dijkstra üçün — məsafə əsaslı
        // (node, məsafə) cütlərini məsafəyə görə sırala
        PriorityQueue<int[]> dijkstra = new PriorityQueue<>(
            Comparator.comparingInt(arr -> arr[1]) // arr[1] = məsafə
        );
        dijkstra.offer(new int[]{0, 0});  // {node, məsafə}
        dijkstra.offer(new int[]{1, 5});
        dijkstra.offer(new int[]{2, 3});
        dijkstra.offer(new int[]{3, 1});

        System.out.println("\nDijkstra sırası:");
        while (!dijkstra.isEmpty()) {
            int[] curr = dijkstra.poll();
            System.out.println("  Node=" + curr[0] + ", Məsafə=" + curr[1]);
        }
        // Node=0, Məsafə=0
        // Node=3, Məsafə=1
        // Node=2, Məsafə=3
        // Node=1, Məsafə=5

        // SSENARIY 3: K ən böyük element tapmaq
        int[] massiv = {3, 1, 4, 1, 5, 9, 2, 6, 5, 3};
        int k = 3;

        // Min-Heap ilə k ən böyük element
        PriorityQueue<Integer> kEnBöyük = new PriorityQueue<>(k); // min-heap, ölçü k
        for (int num : massiv) {
            kEnBöyük.offer(num);
            if (kEnBöyük.size() > k) {
                kEnBöyük.poll(); // ən kiçiyi çıxar
            }
        }
        System.out.println("\n" + k + " ən böyük: " + kEnBöyük); // [5, 6, 9]
    }
}
```

---

## ArrayDeque — stack və queue

`ArrayDeque` — genişlənən circular massiv əsaslı **ikitərəfli növbə** (double-ended queue):

```java
import java.util.*;

public class ArrayDequeNümunə {
    public static void main(String[] args) {
        // ── Queue (FIFO) kimi istifadə ──
        Deque<String> queue = new ArrayDeque<>();
        queue.offerLast("Birinci");  // = offer() = addLast()
        queue.offerLast("İkinci");
        queue.offerLast("Üçüncü");

        System.out.println(queue.peekFirst());  // "Birinci" — silmədən bax
        System.out.println(queue.pollFirst()); // "Birinci" — sil
        System.out.println(queue);              // [İkinci, Üçüncü]

        // ── Stack (LIFO) kimi istifadə ──
        Deque<String> stack = new ArrayDeque<>();
        stack.push("Alt");      // = addFirst()
        stack.push("Orta");     // = addFirst()
        stack.push("Üst");      // = addFirst()

        System.out.println(stack.peek()); // "Üst" — silmədən bax
        System.out.println(stack.pop());  // "Üst" — sil (LIFO)
        System.out.println(stack.pop());  // "Orta"
        System.out.println(stack);         // [Alt]

        // ── Hər iki tərəfdən ──
        Deque<Integer> deque = new ArrayDeque<>();
        deque.addFirst(2);  // [2]
        deque.addLast(3);   // [2, 3]
        deque.addFirst(1);  // [1, 2, 3]
        deque.addLast(4);   // [1, 2, 3, 4]

        System.out.println(deque.pollFirst()); // 1
        System.out.println(deque.pollLast());  // 4
        System.out.println(deque);              // [2, 3]
    }
}
```

### Circular Massiv Quruluşu

```
ArrayDeque daxilində circular (dairəvi) massiv:

  head           tail
   ↓               ↓
[_, _, A, B, C, D, _, _]  ← massiv
         \____/
        faktiki elementlər

addFirst() → head bir sol keçir
addLast()  → tail bir sağ keçir
Massiv dolu olduqda: capacity iki qat artır
```

---

## Deque metodları

`Deque<E>` interfeysi — hər iki tərəfdən əlavə/silmə əməliyyatlarını dəstəkləyir:

```java
import java.util.*;

public class DequeMetodları {
    public static void main(String[] args) {
        Deque<String> deque = new ArrayDeque<>();

        // ── Baş tərəfdən (First/Head) ──
        deque.addFirst("A");         // xəta atar — capacity aşıldığında
        deque.offerFirst("B");       // false qaytarar — capacity aşıldığında
        deque.push("C");             // = addFirst()

        String head1 = deque.getFirst();    // bax, xəta atar əgər boşdursa
        String head2 = deque.peekFirst();   // bax, null qaytarar
        String head3 = deque.peek();        // = peekFirst()

        String rem1 = deque.removeFirst();  // sil, xəta atar
        String rem2 = deque.pollFirst();    // sil, null qaytarar
        String rem3 = deque.pop();          // = removeFirst()

        // ── Son tərəfdən (Last/Tail) ──
        deque.addLast("X");          // = add()
        deque.offerLast("Y");        // = offer()

        String tail1 = deque.getLast();     // bax, xəta atar
        String tail2 = deque.peekLast();    // bax, null qaytarar

        String rem4 = deque.removeLast();  // sil, xəta atar
        String rem5 = deque.pollLast();    // sil, null qaytarar

        // ── Axtarış ──
        deque.addLast("M");
        deque.addLast("N");
        deque.addLast("M");

        deque.removeFirstOccurrence("M"); // ilk "M"-i sil
        deque.removeLastOccurrence("M");  // son "M"-i sil

        // ── Ters iterator ──
        deque.addLast("1");
        deque.addLast("2");
        deque.addLast("3");

        Iterator<String> tersIter = deque.descendingIterator();
        while (tersIter.hasNext()) {
            System.out.print(tersIter.next() + " "); // 3 2 1
        }
        System.out.println();
    }
}
```

### Deque metod xülasəsi

| Əməliyyat | Baş (First) — xəta atar | Baş — null/false | Son (Last) — xəta atar | Son — null/false |
|-----------|------------------------|-------------------|------------------------|-----------------|
| Əlavə et | `addFirst(e)` | `offerFirst(e)` | `addLast(e)` | `offerLast(e)` |
| Sil | `removeFirst()` | `pollFirst()` | `removeLast()` | `pollLast()` |
| Bax | `getFirst()` | `peekFirst()` | `getLast()` | `peekLast()` |

---

## LinkedList vs ArrayDeque

```java
import java.util.*;

public class LinkedListVsArrayDeque {
    public static void main(String[] args) {
        int N = 1_000_000;

        // ArrayDeque — tövsiYYYə olunan Deque/Queue/Stack
        Deque<Integer> arrayDeque = new ArrayDeque<>();
        long t = System.nanoTime();
        for (int i = 0; i < N; i++) arrayDeque.addLast(i);
        for (int i = 0; i < N; i++) arrayDeque.pollFirst();
        System.out.println("ArrayDeque: " + (System.nanoTime() - t) / 1_000_000 + "ms");

        // LinkedList — hər element üçün Node yaradılır
        Deque<Integer> linkedList = new LinkedList<>();
        t = System.nanoTime();
        for (int i = 0; i < N; i++) linkedList.addLast(i);
        for (int i = 0; i < N; i++) linkedList.pollFirst();
        System.out.println("LinkedList: " + (System.nanoTime() - t) / 1_000_000 + "ms");
        // ArrayDeque adətən 2-3x daha sürətlidir!

        // Fərqlər:
        // ArrayDeque: circular massiv, cache-friendly, az yaddaş, null qəbul etmir
        // LinkedList: ikiqat bağlı siyahı, çox yaddaş, null qəbul edir
        //             eyni zamanda List interfeysini implement edir (get(i) var)

        // ArrayDeque null qəbul etmir:
        Deque<String> ad = new ArrayDeque<>();
        // ad.push(null); // NullPointerException!

        // LinkedList null qəbul edir:
        Deque<String> ll = new LinkedList<>();
        ll.push(null); // OK
    }
}
```

| Xüsusiyyət | ArrayDeque | LinkedList |
|------------|------------|------------|
| Əsas struktur | Circular massiv | Ikiqat bağlı siyahı |
| addFirst/addLast | O(1) amortized | O(1) |
| pollFirst/pollLast | O(1) | O(1) |
| get(i) | **Yox** | O(n) |
| null element | **Yox** | Bəli |
| Yaddaş | Az (massiv) | Çox (Node per element) |
| Cache-friendliness | Yüksək | Aşağı |
| List interfeysi | **Yox** | **Bəli** |
| Tövsiyə | Stack/Queue/Deque | Nadir (List+Deque lazımdırsa) |

---

## Praktiki Nümunələr

```java
import java.util.*;

public class QueuePraktik {

    // SSENARIY 1: BFS (Genişlik əvvəli axtarış)
    static void bfs(Map<Integer, List<Integer>> qraf, int başlanğıc) {
        Queue<Integer> növbə = new ArrayDeque<>();
        Set<Integer> ziyarətEdilmiş = new HashSet<>();

        növbə.offer(başlanğıc);
        ziyarətEdilmiş.add(başlanğıc);

        while (!növbə.isEmpty()) {
            int node = növbə.poll();
            System.out.print(node + " ");

            for (int qonşu : qraf.getOrDefault(node, List.of())) {
                if (!ziyarətEdilmiş.contains(qonşu)) {
                    ziyarətEdilmiş.add(qonşu);
                    növbə.offer(qonşu);
                }
            }
        }
    }

    // SSENARIY 2: Skobların yoxlanması (Stack istifadəsi)
    static boolean düzgünSkoblar(String s) {
        Deque<Character> stack = new ArrayDeque<>();
        Map<Character, Character> cütlər = Map.of(')', '(', ']', '[', '}', '{');

        for (char c : s.toCharArray()) {
            if (cütlər.containsValue(c)) {
                stack.push(c); // açıq skob — stack-ə əlavə et
            } else if (cütlər.containsKey(c)) {
                if (stack.isEmpty() || stack.pop() != cütlər.get(c)) {
                    return false; // uyğun gəlmir
                }
            }
        }
        return stack.isEmpty(); // hamısı bağlandı?
    }

    // SSENARIY 3: Sliding Window Maximum (Monotonic Deque)
    static int[] pəncərəMaksimumu(int[] nums, int k) {
        Deque<Integer> deque = new ArrayDeque<>(); // indeksləri saxlayır
        int[] nəticə = new int[nums.length - k + 1];
        int ri = 0;

        for (int i = 0; i < nums.length; i++) {
            // Pəncərədən çıxan elementləri sil
            while (!deque.isEmpty() && deque.peekFirst() < i - k + 1) {
                deque.pollFirst();
            }
            // Kiçik elementləri arxadan sil (monotonic azalan deque)
            while (!deque.isEmpty() && nums[deque.peekLast()] < nums[i]) {
                deque.pollLast();
            }
            deque.offerLast(i);

            if (i >= k - 1) {
                nəticə[ri++] = nums[deque.peekFirst()];
            }
        }
        return nəticə;
    }

    // SSENARIY 4: Median Finder (İki Heap)
    static class MedianFinder {
        PriorityQueue<Integer> aşağı = new PriorityQueue<>(Comparator.reverseOrder()); // max-heap
        PriorityQueue<Integer> yuxarı = new PriorityQueue<>();                          // min-heap

        void əlavəEt(int num) {
            aşağı.offer(num);
            yuxarı.offer(aşağı.poll()); // balanslaşdır

            if (yuxarı.size() > aşağı.size()) {
                aşağı.offer(yuxarı.poll());
            }
        }

        double medianTap() {
            if (aşağı.size() > yuxarı.size()) return aşağı.peek();
            return (aşağı.peek() + yuxarı.peek()) / 2.0;
        }
    }

    public static void main(String[] args) {
        System.out.println(düzgünSkoblar("({[]})"));  // true
        System.out.println(düzgünSkoblar("({[})"));   // false

        int[] arr = {1, 3, -1, -3, 5, 3, 6, 7};
        System.out.println(Arrays.toString(pəncərəMaksimumu(arr, 3)));
        // [3, 3, 5, 5, 6, 7]

        MedianFinder mf = new MedianFinder();
        mf.əlavəEt(1); mf.əlavəEt(2); mf.əlavəEt(3);
        System.out.println("Median: " + mf.medianTap()); // 2.0
        mf.əlavəEt(4);
        System.out.println("Median: " + mf.medianTap()); // 2.5
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;

public class YanlisDoğru {

    // ❌ YANLIŞ: Stack sinifini istifadə etmək (köhnə, deprecated)
    void yanlisStack() {
        Stack<String> stack = new Stack<>(); // Vector-dan miras alır — yavaş, thread-safe
        stack.push("A");
        stack.pop();
        // Stack synchronized metodları var — lazımsız overhead
    }

    // ✅ DOĞRU: ArrayDeque-i stack kimi istifadə etmək
    void dogruStack() {
        Deque<String> stack = new ArrayDeque<>();
        stack.push("A");  // addFirst()
        stack.pop();      // removeFirst()
        // Sürətli, thread-safe deyil amma tək-threadli üçün ideal
    }

    // ❌ YANLIŞ: Queue üçün LinkedList istifadəsi (köhnəlmiş alışqanlıq)
    void yanlisQueue() {
        Queue<String> queue = new LinkedList<>(); // çox yaddaş istifadə edir
    }

    // ✅ DOĞRU: Queue üçün ArrayDeque istifadəsi
    void dogruQueue() {
        Queue<String> queue = new ArrayDeque<>(); // az yaddaş, sürətli
    }

    // ❌ YANLIŞ: PriorityQueue-nu sıralı say kimi düşünmək
    void yanlisPQIteration() {
        PriorityQueue<Integer> pq = new PriorityQueue<>(List.of(5, 2, 8, 1));
        // for-each sıralı deyil!
        for (int num : pq) {
            System.out.print(num + " "); // 1 2 8 5 — heap sırası, sıralı deyil!
        }
    }

    // ✅ DOĞRU: PriorityQueue-dan poll() ilə sıralı çıxarış
    void dogruPQIteration() {
        PriorityQueue<Integer> pq = new PriorityQueue<>(List.of(5, 2, 8, 1));
        while (!pq.isEmpty()) {
            System.out.print(pq.poll() + " "); // 1 2 5 8 — sıralı!
        }
    }

    // ❌ YANLIŞ: add() istifadəsi capacity-məhdud queue-da
    void yanlisAdd() {
        Queue<Integer> bounded = new ArrayBlockingQueue<>(3);
        bounded.offer(1); bounded.offer(2); bounded.offer(3);
        // bounded.add(4); // IllegalStateException — istisna idarəsini çətinləşdirir
    }

    // ✅ DOĞRU: offer() istifadəsi
    void dogruOffer() {
        Queue<Integer> bounded = new ArrayBlockingQueue<>(3);
        bounded.offer(1); bounded.offer(2); bounded.offer(3);
        boolean added = bounded.offer(4); // false — sakit şəkildə rədd edildi
        if (!added) System.out.println("Queue dolu, element əlavə edilmədi");
    }
}
```

---

## İntervyu Sualları

**S1: offer() və add() arasındakı fərq nədir?**

Hər ikisi elementi queue-ya əlavə edir. `add()` — capacity aşıldığında `IllegalStateException` atır. `offer()` — `false` qaytarır (xəta atmır). Capacity-məhdud queue-larda (ArrayBlockingQueue) `offer()` tövsiyə olunur.

**S2: PriorityQueue necə işləyir?**

Daxilində min-heap (binary heap) saxlayır — massiv əsaslı. `offer()` → O(log n) — yuxarıya "swim". `poll()` → O(log n) — kök çıxarılır, son element köküə gətirilir, "sink" edilir. `peek()` → O(1) — massiv[0].

**S3: ArrayDeque niyə LinkedList-dən daha yaxşıdır?**

1. Az yaddaş — Node overhead yoxdur. 2. Cache-friendly — ardıcıl yaddaqda saxlanır. 3. Sürətli — amortized O(1) əvvəl/axır əməliyyatları. Yeganə üstünlüyü olmayan: LinkedList List interfeysini de implement edir (get(i) var).

**S4: Stack sinifini nə üçün istifadə etmək tövsiyə edilmir?**

`Stack` — `Vector`-dan miras alır. `Vector` bütün metodları `synchronized` edir — lazımsız thread-lock overhead. `ArrayDeque` həm daha sürətlidir, həm də `Deque` interfeysini implement edir.

**S5: Deque-nin iki tərəfdən metodları nədir?**

- `addFirst`/`addLast`, `offerFirst`/`offerLast`
- `removeFirst`/`removeLast`, `pollFirst`/`pollLast`
- `getFirst`/`getLast`, `peekFirst`/`peekLast`
- Əlavə: `push` (=addFirst), `pop` (=removeFirst), `peek` (=peekFirst)

**S6: PriorityQueue-nun iteration sırası sıralıdırmı?**

Xeyr! `for-each` heap-in massiv strukturunu iterate edir — bu heap sırasıdır, sıralı deyil. Sıralı çıxarış üçün `poll()` istifadə edilməlidir.

**S7: K-th largest element tapmaq üçün necə PriorityQueue istifadə edir?**

K ölçülü min-heap saxla. Hər element üçün: heap-ə əlavə et, əgər ölçü k-dan böyükdürsə `poll()` et (minimum çıxar). Sonda heap-in tepəsi (peek) k-cı ən böyük elementdir. Mürəkkəblik: O(n log k).
