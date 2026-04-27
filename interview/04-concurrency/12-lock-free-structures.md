# Lock-Free Data Structures (Lead ⭐⭐⭐⭐)

## İcmal
Lock-free data structure — mutex olmadan concurrent əməliyyatları dəstəkləyən, CAS (Compare-And-Swap) əsaslı data strukturdur. Ən azı bir thread həmişə irəliləyir — Mutex-in deadlock/priority inversion riskləri yoxdur. Lock-free queue, stack, linked list — concurrent sistemlərin qüvvəli alətləridir. Lead interview-larda nəzəriyyə ilə yanaşı CAS loop, ABA problemi, və real Java/Go implementasiyası soruşulur.

## Niyə Vacibdir
`ConcurrentLinkedQueue`, `ConcurrentSkipListMap`, Java `ConcurrentHashMap` — bunların daxili implementasiyası lock-free alqoritmdir. İnterviewer bu sualla sizin lock-free-nin niyə lock-based-dən üstün olduğunu (liveness, performance), niyə çətin olduğunu (ABA, memory reclamation), hazır implementasiyaları nə zaman istifadə edəcəyinizi bildiyinizi yoxlayır. Real sistemdə öz lock-free strukturunuzu nadir yazırsınız — amma necə işlədiyini bilməlisiniz.

## Əsas Anlayışlar

- **Lock-Free:** Ən azı bir thread həmişə irəliləyir — sistem starvation-sız addım atır
- **Wait-Free:** Hər thread müəyyən addımda tamamlanır — lock-free-dən daha güclü qarantiya
- **Obstruction-Free:** Ən zəif — digərləri dayanırsa thread irəliləyir
- **CAS Loop:** `while (!cas(expected, new)) { expected = reload(); }` — lock-free core pattern
- **ABA Problem:** CAS-ın əsas problemi — pointer geri gəlir, amma kontekst dəyişib
- **Hazard Pointer:** ABA + memory reclamation həlli — "bu pointer-i istifadə edirəm, silmə"
- **Epoch-Based Reclamation (EBR):** Bütün thread-lər epoch-da ayrılır, köhnə node-lar sonra silinir; RCU (Read-Copy-Update) Linux kernel-da
- **Michael-Scott Queue:** Lock-free FIFO queue — iki pointer (head, tail), CAS ilə update
- **Treiber Stack:** Lock-free LIFO stack — push/pop CAS ilə; ən sadə lock-free struktur
- **`ConcurrentLinkedQueue` (Java):** Michael-Scott Queue implementasiyası
- **`ConcurrentLinkedDeque` (Java):** Lock-free double-ended queue
- **`ConcurrentSkipListMap` (Java):** Lock-free sorted map — CAS-əsaslı skip list
- **Skip List:** Probabilistic balanced BST alternativi — lock-free implementasiyanın asan olduğu struktur
- **Sentinel Node:** Lock-free linked list-də ghost head/tail node — corner case-ləri azaldır
- **Memory Ordering:** Lock-free kod yazarkən acquire/release semantics lazımdır — compiler/CPU reorder-ə qarşı

## Praktik Baxış

**Interview-da yanaşma:**
- "Lock-free niyə daha yaxşıdır?" — deadlock yoxdur, priority inversion yoxdur, starvation azalır
- "Niyə çətin?" — ABA problemi, memory reclamation, memory ordering
- Öz lock-free strukturunu yazmaq lazımdırsa: Treiber Stack ilə başlayın — ən sadə

**Follow-up suallar:**
- "Lock-free həmişə lock-based-dən sürətlidirmi?" — Xeyr; low contention-da mutex sürətli ola bilər
- "Memory reclamation niyə çətin?" — CAS loop oxurkən başqa thread pointer-i silə bilər
- "Java ConcurrentHashMap lock-free-dirmi?" — Qismən; segment locking + CAS mix

**Ümumi səhvlər:**
- "Lock-free = no synchronization" düşünmək — hələ də atomic operations və memory barrier var
- ABA problemini qeyd etməmək
- Həmişə lock-free tövsiyə etmək — low contention-da mutex daha oxunaqlı

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Michael-Scott Queue-nun iki CAS (head, tail) ilə işlədiyini izah etmək
- Hazard pointer vs epoch-based reclamation fərqini bilmək
- Java-da `ConcurrentLinkedQueue` vs `LinkedBlockingQueue` seçim kriteriyasını izah etmək

## Nümunələr

### Tipik Interview Sualı
"Lock-free stack necə implementasiya edərdiniz? ABA problemi burada nə yaradır?"

### Güclü Cavab
Lock-free stack (Treiber Stack) — `AtomicReference<Node>` head pointer ilə implementasiya olunur. Push: yeni node yaradılır, `next = head`, CAS ilə `head = newNode` — uğursuz olsa yenidən cəhd et. Pop: `head = head.next`, CAS ilə — uğursuz olsa yenidən. ABA problemi pop-da yaranır: A oxuyursan, B pop edir (A çıxır), C push edir (A geri girə bilər), CAS "A görürəm, head A-dır, ok" — lakin head.next artıq dəyişib, stack corrupted olur. Həll 1: `AtomicStampedReference` — hər CAS-da versiya counter artır, A geri gəlsə də stamp fərqlidir. Həll 2: ABA-nı GC əsaslı dillərdə JVM yaşayan node-ları silmir — real ABA daha az aktualdir. Production üçün `ConcurrentLinkedDeque` istifadə edin — test edilmiş implementasiya.

### Kod Nümunəsi
```java
// Treiber Stack — Lock-Free Stack
import java.util.concurrent.atomic.*;

public class LockFreeStack<T> {
    private static class Node<T> {
        final T value;
        Node<T> next;

        Node(T value) {
            this.value = value;
        }
    }

    private final AtomicReference<Node<T>> head = new AtomicReference<>();

    // Push — O(1) amortized
    public void push(T value) {
        Node<T> newNode = new Node<>(value);
        while (true) {
            Node<T> current = head.get();
            newNode.next = current;
            if (head.compareAndSet(current, newNode)) {
                return; // Uğurlu
            }
            // CAS uğursuz — başqa thread dəyişdi, yenidən cəhd et
        }
    }

    // Pop — O(1)
    public T pop() {
        while (true) {
            Node<T> current = head.get();
            if (current == null) return null; // Boş stack

            Node<T> next = current.next;
            if (head.compareAndSet(current, next)) {
                return current.value; // Uğurlu
            }
            // CAS uğursuz — yenidən cəhd et
        }
    }

    public T peek() {
        Node<T> top = head.get();
        return top == null ? null : top.value;
    }
}
```

```java
// Michael-Scott Queue — Lock-Free FIFO
// Java ConcurrentLinkedQueue-nun əsası
public class LockFreeQueue<T> {
    private static class Node<T> {
        volatile T value;
        final AtomicReference<Node<T>> next;

        Node(T value) {
            this.value = value;
            this.next = new AtomicReference<>(null);
        }
    }

    // Sentinel node — head/tail həmişə valid
    private final AtomicReference<Node<T>> head;
    private final AtomicReference<Node<T>> tail;

    public LockFreeQueue() {
        Node<T> sentinel = new Node<>(null);
        head = new AtomicReference<>(sentinel);
        tail = new AtomicReference<>(sentinel);
    }

    public void enqueue(T value) {
        Node<T> newNode = new Node<>(value);
        while (true) {
            Node<T> currentTail = tail.get();
            Node<T> tailNext = currentTail.next.get();

            if (currentTail == tail.get()) { // Consistent read
                if (tailNext == null) {
                    // Tail sonunda — yeni node əlavə et
                    if (currentTail.next.compareAndSet(null, newNode)) {
                        // Tail-ı irəli sür (uğursuz olsa da — başqa thread edəcək)
                        tail.compareAndSet(currentTail, newNode);
                        return;
                    }
                } else {
                    // Tail geri qalıb — irəli sür
                    tail.compareAndSet(currentTail, tailNext);
                }
            }
        }
    }

    public T dequeue() {
        while (true) {
            Node<T> currentHead = head.get();
            Node<T> currentTail = tail.get();
            Node<T> headNext = currentHead.next.get();

            if (currentHead == head.get()) {
                if (currentHead == currentTail) {
                    if (headNext == null) return null; // Boş queue
                    tail.compareAndSet(currentTail, headNext); // Tail geri qalıb
                } else {
                    T value = headNext.value;
                    if (head.compareAndSet(currentHead, headNext)) {
                        return value;
                    }
                }
            }
        }
    }
}
```

```java
// Java: Hazır lock-free strukturlar
import java.util.concurrent.*;

public class ConcurrentStructuresDemo {

    public static void main(String[] args) throws InterruptedException {
        // ConcurrentLinkedQueue — lock-free FIFO (Michael-Scott)
        // LinkedBlockingQueue-dan fərqi: blocking yoxdur, backpressure yoxdur
        ConcurrentLinkedQueue<String> lockFreeQueue = new ConcurrentLinkedQueue<>();
        lockFreeQueue.offer("task1");
        lockFreeQueue.offer("task2");
        String task = lockFreeQueue.poll(); // null — boşdursa

        // ConcurrentLinkedDeque — lock-free double-ended
        ConcurrentLinkedDeque<Integer> deque = new ConcurrentLinkedDeque<>();
        deque.addFirst(1);
        deque.addLast(2);
        deque.pollFirst();

        // ConcurrentSkipListMap — lock-free sorted map
        // TreeMap-ın concurrent alternativdir; O(log n) əməliyyatlar
        ConcurrentSkipListMap<String, Integer> sortedMap = new ConcurrentSkipListMap<>();
        sortedMap.put("c", 3);
        sortedMap.put("a", 1);
        sortedMap.put("b", 2);
        System.out.println(sortedMap.firstKey()); // "a" — sorted

        // ConcurrentHashMap — write-da bucket locking (not fully lock-free)
        // ama read-lar lock-free-dir
        ConcurrentHashMap<String, Integer> map = new ConcurrentHashMap<>();
        map.put("counter", 0);
        // Atomic update — compute lock-free deyil, amma bulk op imkanı verir
        map.compute("counter", (k, v) -> v == null ? 1 : v + 1);

        // LinkedBlockingQueue vs ConcurrentLinkedQueue seçimi:
        // - Blocking lazımdırsa (producer-consumer) → LinkedBlockingQueue
        // - Non-blocking, lock-free → ConcurrentLinkedQueue
        // - Memory: CLQ iterator + size() O(n) — LBQ size() O(1)

        System.out.println("Done");
    }
}
```

```go
// Go: sync/atomic ilə lock-free stack
package main

import (
    "fmt"
    "sync/atomic"
    "unsafe"
)

type node struct {
    value int
    next  unsafe.Pointer // *node
}

type LockFreeStack struct {
    head unsafe.Pointer // *node
}

func (s *LockFreeStack) Push(val int) {
    newNode := &node{value: val}
    for {
        oldHead := atomic.LoadPointer(&s.head)
        newNode.next = oldHead
        if atomic.CompareAndSwapPointer(&s.head, oldHead, unsafe.Pointer(newNode)) {
            return
        }
    }
}

func (s *LockFreeStack) Pop() (int, bool) {
    for {
        oldHead := atomic.LoadPointer(&s.head)
        if oldHead == nil {
            return 0, false
        }
        n := (*node)(oldHead)
        if atomic.CompareAndSwapPointer(&s.head, oldHead, n.next) {
            return n.value, true
        }
    }
}

func main() {
    s := &LockFreeStack{}
    s.Push(1)
    s.Push(2)
    s.Push(3)

    for {
        v, ok := s.Pop()
        if !ok {
            break
        }
        fmt.Println(v) // 3, 2, 1
    }
}

// Production-da: Go standart kitabxanasında lock-free struktur azdır
// sync.Pool — lock-free object pool (GC ilə işləyir)
// channel — Go-nun idiomatic lock-free kommunikasiyası
```

## Praktik Tapşırıqlar

- Treiber Stack-ı Java-da implementasiya edin, 100 thread ilə push/pop test edin
- `ConcurrentLinkedQueue` vs `LinkedBlockingQueue` — non-blocking workload üçün benchmark edin
- ABA problemini Treiber Stack-da reproduce etməyə cəhd edin (GC olan dildə çətin olacaq — niyə?)
- Java `ConcurrentSkipListMap` ilə `Collections.synchronizedTreeMap` performans fərqini ölçün
- Go-da lock-free stack-ı race detector ilə test edin: `go test -race`

## Əlaqəli Mövzular
- `10-atomic-operations.md` — CAS — lock-free strukturların əsası
- `11-memory-models.md` — Memory ordering lock-free kodda kritikdir
- `03-mutex-semaphore.md` — Lock-based alternativ
- `08-producer-consumer.md` — ConcurrentLinkedQueue producer-consumer-da
