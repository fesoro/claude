# 038 — Concurrent Collections — Paralel Mühit üçün Kolleksiyalar
**Səviyyə:** İrəli


## Mündəricat
- [Niyə concurrent collections lazımdır?](#niyə-concurrent-collections-lazımdır)
- [ConcurrentHashMap — Segment locking → CAS](#concurrenthashmap--segment-locking--cas)
- [CopyOnWriteArrayList — Snapshot Iterator](#copyonwritearraylist--snapshot-iterator)
- [BlockingQueue — Bloklayan Növbə](#blockingqueue--bloklayan-növbə)
- [Producer-Consumer Nümunəsi](#producer-consumer-nümunəsi)
- [ConcurrentLinkedQueue](#concurrentlinkedqueue)
- [Digər Concurrent Kolleksiyalar](#digər-concurrent-kolleksiyalar)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Niyə concurrent collections lazımdır?

```java
import java.util.*;
import java.util.concurrent.*;

public class ThreadSafetyProblemi {
    public static void main(String[] args) throws InterruptedException {

        // ❌ PROBLEM: Normal HashMap çox-threadli mühitdə
        Map<Integer, Integer> xarabMap = new HashMap<>();

        Runnable yaz = () -> {
            for (int i = 0; i < 1000; i++) {
                xarabMap.put(i, i); // ❌ Thread-safe deyil!
            }
        };

        Thread t1 = new Thread(yaz);
        Thread t2 = new Thread(yaz);
        t1.start(); t2.start();
        t1.join(); t2.join();

        // Nəticə qeyri-müəyyəndir:
        // - Data corruption (yanlış dəyərlər)
        // - Map-in daxili strukturu xarab ola bilər
        // - Java 7-də infinite loop (resize zamanı)
        System.out.println("HashMap ölçüsü (qeyri-müəyyən): " + xarabMap.size());

        // ✅ HƏLL: ConcurrentHashMap istifadəsi
        Map<Integer, Integer> təhlükəsizMap = new ConcurrentHashMap<>();
        Runnable təhlükəsizYaz = () -> {
            for (int i = 0; i < 1000; i++) {
                təhlükəsizMap.put(i, i); // Thread-safe! ✅
            }
        };

        Thread t3 = new Thread(təhlükəsizYaz);
        Thread t4 = new Thread(təhlükəsizYaz);
        t3.start(); t4.start();
        t3.join(); t4.join();
        System.out.println("ConcurrentHashMap ölçüsü: " + təhlükəsizMap.size()); // 1000
    }
}
```

---

## ConcurrentHashMap — Segment locking → CAS

### Java 7: Segment Locking (ReentrantLock)

```
Java 7 ConcurrentHashMap:
━━━━━━━━━━━━━━━━━━━━━━━━
Segment[0] — ReentrantLock
  ├── Bucket[0..15]
  └── Locked ayrıca

Segment[1] — ReentrantLock
  ├── Bucket[16..31]
  └── ...

16 Segment (default) → 16 thread eyni vaxtda yazır
```

### Java 8+: CAS + synchronized (Bucket-level locking)

```
Java 8+ ConcurrentHashMap:
━━━━━━━━━━━━━━━━━━━━━━━━━
Node[] table (hər bucket ayrı)
  ├── Boş bucket: CAS (Compare-And-Swap) — lock yoxdur!
  ├── Dolu bucket: synchronized(ilk_node) — yalnız o bucket
  └── Ağac bucket: TreeBin lock

Yüz minlərlə thread eyni vaxtda fərqli bucket-lara yazır!
```

```java
import java.util.*;
import java.util.concurrent.*;
import java.util.concurrent.atomic.*;

public class ConcurrentHashMapDerinlik {
    public static void main(String[] args) throws InterruptedException {

        ConcurrentHashMap<String, Integer> map = new ConcurrentHashMap<>();

        // ── Adi əməliyyatlar ──
        map.put("A", 1);
        map.putIfAbsent("B", 2);          // varsa dəyişmə — atomic!
        map.replace("A", 1, 10);           // yalnız köhnə dəyər uyğunsa — atomic!
        map.remove("A", 10);               // yalnız dəyər uyğunsa — atomic!

        // ── Atomik yığımlı əməliyyatlar ──
        // compute — dəyəri hesabla, atomic!
        map.compute("sayac", (k, v) -> v == null ? 1 : v + 1);
        map.compute("sayac", (k, v) -> v == null ? 1 : v + 1);
        System.out.println("sayac: " + map.get("sayac")); // 2

        // computeIfAbsent — yoxdursa hesabla
        map.computeIfAbsent("siyahı", k -> 0);

        // merge — birləşdir
        String[] sözlər = {"java", "python", "java", "go", "java"};
        ConcurrentHashMap<String, Integer> söz_sayacı = new ConcurrentHashMap<>();
        for (String söz : sözlər) {
            söz_sayacı.merge(söz, 1, Integer::sum); // atomic!
        }
        System.out.println(söz_sayacı); // {java=3, python=1, go=1}

        // ── Paralel əməliyyatlar (Java 8+) ──
        ConcurrentHashMap<String, Integer> böyükMap = new ConcurrentHashMap<>();
        for (int i = 0; i < 10_000; i++) böyükMap.put("key" + i, i);

        // forEach — paralel icra
        böyükMap.forEach(1000, (k, v) -> {
            // parallelism threshold = 1000
            // Hər 1000 elementdən çox olduqda paralel işlər
        });

        // reduce — paralel yığım
        long cəm = böyükMap.reduceValues(1000, Integer::sum);
        System.out.println("Cəm: " + cəm);

        // search — paralel axtarış
        String tapılan = böyükMap.search(1000, (k, v) -> v == 5000 ? k : null);
        System.out.println("Tapılan: " + tapılan); // "key5000"

        // ── mappingCount — size() əvəzinə (long qaytarır) ──
        long say = böyükMap.mappingCount(); // long — 2 milyarddan çox üçün
        System.out.println("Say: " + say);

        // ── Performans müqayisəsi ──
        int THREAD_SAYI = 10;
        int HER_THREAD = 100_000;

        // ConcurrentHashMap
        ConcurrentHashMap<Integer, Integer> chm = new ConcurrentHashMap<>();
        long t = System.nanoTime();
        List<Thread> threads = new ArrayList<>();
        for (int i = 0; i < THREAD_SAYI; i++) {
            final int start = i * HER_THREAD;
            Thread th = new Thread(() -> {
                for (int j = start; j < start + HER_THREAD; j++) chm.put(j, j);
            });
            threads.add(th);
            th.start();
        }
        for (Thread th : threads) th.join();
        System.out.printf("ConcurrentHashMap (%d threads, %d ops): %dms%n",
            THREAD_SAYI, THREAD_SAYI * HER_THREAD, (System.nanoTime() - t) / 1_000_000);
    }
}
```

---

## CopyOnWriteArrayList — Snapshot Iterator

`CopyOnWriteArrayList` — hər yazma əməliyyatında **yeni massiv kopyası** yaradır:

```java
// CopyOnWriteArrayList-in daxili yazma mexanizmi (sadələşdirilmiş)
public boolean add(E e) {
    synchronized (lock) { // yazma qilidalıdır
        Object[] oldArray = getArray();
        int len = oldArray.length;

        // YENİ massiv yarat (köhnə + 1 element)
        Object[] newArray = Arrays.copyOf(oldArray, len + 1);
        newArray[len] = e;

        // Atomik şəkildə yeni massivi qur
        setArray(newArray);
        return true;
    }
}
// Oxuma QILID YOXDUR — həmişə mövcud massivi oxuyur
```

```java
import java.util.*;
import java.util.concurrent.*;

public class CopyOnWriteNümunə {
    public static void main(String[] args) throws InterruptedException {

        CopyOnWriteArrayList<String> list = new CopyOnWriteArrayList<>();
        list.add("A");
        list.add("B");
        list.add("C");

        // Snapshot Iterator — iterasiya başlayanda massivin kopyasını alır
        // Sonradan edilən dəyişikliklər iteratora görünmür!
        Iterator<String> iter = list.iterator();

        list.add("D"); // Iterator artıq yaradılıb — D görünmür
        list.remove("A"); // A silinir amma iterator görə bilmir

        System.out.print("Iterator nəticəsi: ");
        while (iter.hasNext()) {
            System.out.print(iter.next() + " ");
        }
        System.out.println(); // A B C — D yoxdur, A hələ var!

        System.out.println("Actual list: " + list); // [B, C, D]

        // Iterator.remove() dəstəklənmir!
        Iterator<String> iter2 = list.iterator();
        try {
            iter2.next();
            iter2.remove(); // UnsupportedOperationException!
        } catch (UnsupportedOperationException e) {
            System.out.println("Iterator.remove() dəstəklənmir!");
        }

        // ── Nə zaman istifadə etməli? ──
        // ✅ Oxuma əməliyyatları ÇOX, yazma ÇOX AZ olduqda
        // ✅ Event listener siyahıları
        // ✅ Observer pattern

        // Praktiki nümunə: Listener siyahısı
        CopyOnWriteArrayList<Runnable> listeners = new CopyOnWriteArrayList<>();

        // Thread 1: listener əlavə edir
        new Thread(() -> listeners.add(() -> System.out.println("Listener 1"))).start();

        // Thread 2: eventləri işlədir (iterasiya)
        new Thread(() -> {
            for (Runnable listener : listeners) { // ConcurrentModificationException yoxdur!
                listener.run();
            }
        }).start();

        Thread.sleep(100);

        // ❌ YANLIŞ: çox yazma olduqda CopyOnWriteArrayList
        // Hər add() yeni massiv kopyası yaradır — O(n) — çox bahalı!
    }
}
```

---

## BlockingQueue — Bloklayan Növbə

`BlockingQueue` — `Queue`-nu genişləndirir. **Thread-safe** və **bloklanma** imkanı təqdim edir:

```java
// BlockingQueue əsas metodları
public interface BlockingQueue<E> extends Queue<E> {

    // Blocking put — yer boşalana qədər GÖZLƏR
    void put(E e) throws InterruptedException;

    // Timeout ilə put
    boolean offer(E e, long timeout, TimeUnit unit) throws InterruptedException;

    // Blocking take — element gələnə qədər GÖZLƏR
    E take() throws InterruptedException;

    // Timeout ilə poll
    E poll(long timeout, TimeUnit unit) throws InterruptedException;

    int remainingCapacity(); // qalan yer
    int drainTo(Collection<? super E> c); // hamısını başqa kolleksiyaya köçür
}
```

### ArrayBlockingQueue vs LinkedBlockingQueue

```java
import java.util.concurrent.*;

public class BlockingQueueMüqayisəsi {
    public static void main(String[] args) {

        // ArrayBlockingQueue — sabit capacity, FAIR seçimi var
        BlockingQueue<String> arrayBQ = new ArrayBlockingQueue<>(
            100,  // sabit capacity — mütləq verilməlidir
            true  // fair=true — FIFO sıra ilə thread-lər gözləyir
        );

        // LinkedBlockingQueue — opsional capacity, daha yüksək throughput
        BlockingQueue<String> linkedBQ = new LinkedBlockingQueue<>(1000);
        // BlockingQueue<String> sonsuzBQ = new LinkedBlockingQueue<>(); // Integer.MAX_VALUE

        // Fərqlər:
        // ArrayBlockingQueue:  1 lock (put+take) — sadə, az yaddaş
        // LinkedBlockingQueue: 2 lock (ayrı put lock, ayrı take lock)
        //                       → put və take eyni vaxtda işləyə bilər → daha sürətli

        System.out.println("Array remainingCapacity: " + arrayBQ.remainingCapacity()); // 100
    }
}
```

---

## Producer-Consumer Nümunəsi

```java
import java.util.*;
import java.util.concurrent.*;
import java.util.concurrent.atomic.*;

public class ProducerConsumer {

    static final int QUEUE_CAPACITY = 10;
    static final int PRODUCER_SAYI = 3;
    static final int CONSUMER_SAYI = 2;
    static final int HER_PRODUCER_ELEM = 5;

    public static void main(String[] args) throws InterruptedException {
        BlockingQueue<String> növbə = new LinkedBlockingQueue<>(QUEUE_CAPACITY);
        AtomicInteger counter = new AtomicInteger(0);
        CountDownLatch latch = new CountDownLatch(PRODUCER_SAYI);

        // ── Producers ──
        for (int i = 0; i < PRODUCER_SAYI; i++) {
            final int id = i;
            new Thread(() -> {
                try {
                    for (int j = 0; j < HER_PRODUCER_ELEM; j++) {
                        String məhsul = "Məhsul-" + id + "-" + j;
                        növbə.put(məhsul); // növbə dolu olduqda GÖZLƏR
                        System.out.println("[PRODUCER-" + id + "] istehsal etdi: " + məhsul);
                        Thread.sleep(50); // istehsal vaxtı simulyasiyası
                    }
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                } finally {
                    latch.countDown(); // bu producer bitdi
                }
            }, "Producer-" + i).start();
        }

        // ── Consumers ──
        for (int i = 0; i < CONSUMER_SAYI; i++) {
            final int id = i;
            new Thread(() -> {
                try {
                    while (true) {
                        // 100ms gözlə, boşdursa null qaytarır
                        String məhsul = növbə.poll(100, TimeUnit.MILLISECONDS);
                        if (məhsul == null && latch.getCount() == 0 && növbə.isEmpty()) {
                            break; // producer-lər bitdi, növbə boş
                        }
                        if (məhsul != null) {
                            System.out.println("[CONSUMER-" + id + "] istehlak etdi: " + məhsul);
                            counter.incrementAndGet();
                            Thread.sleep(100); // istehlak vaxtı
                        }
                    }
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            }, "Consumer-" + id).start();
        }

        // Bütün producer-lərin bitməsini gözlə
        latch.await();
        Thread.sleep(2000); // consumer-lər qalan elementləri işləsin

        System.out.println("\nÜmumi istehlak edilən: " + counter.get());
        System.out.println("Növbədə qalan: " + növbə.size());
    }
}
```

### Poison Pill Pattern

```java
import java.util.concurrent.*;

/**
 * Poison Pill — consumer-ə "bitdi" siqnalı göndərmək üçün xüsusi sentinel dəyər
 */
public class PoisonPillNümunə {

    static final String POISON_PILL = "___STOP___"; // sentinel

    public static void main(String[] args) throws InterruptedException {
        BlockingQueue<String> növbə = new LinkedBlockingQueue<>();
        int CONSUMER_SAYI = 3;

        // Consumer-lər
        for (int i = 0; i < CONSUMER_SAYI; i++) {
            final int id = i;
            new Thread(() -> {
                try {
                    while (true) {
                        String elem = növbə.take(); // bloklar
                        if (POISON_PILL.equals(elem)) {
                            System.out.println("Consumer-" + id + " dayandı");
                            break; // bitdi
                        }
                        System.out.println("Consumer-" + id + " işlətdi: " + elem);
                    }
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            }).start();
        }

        // Producer
        for (int i = 0; i < 10; i++) {
            növbə.put("Tapşırıq-" + i);
            Thread.sleep(50);
        }

        // Hər consumer üçün poison pill göndər
        for (int i = 0; i < CONSUMER_SAYI; i++) {
            növbə.put(POISON_PILL);
        }
    }
}
```

---

## ConcurrentLinkedQueue

`ConcurrentLinkedQueue` — lock-free, CAS əsaslı thread-safe queue:

```java
import java.util.*;
import java.util.concurrent.*;

public class ConcurrentLinkedQueueNümunə {
    public static void main(String[] args) throws InterruptedException {

        // Lock-free — CAS (Compare-And-Swap) istifadə edir
        // BlockingQueue-dan fərqli olaraq bloklama yoxdur
        ConcurrentLinkedQueue<Integer> clq = new ConcurrentLinkedQueue<>();

        // Çox thread eyni vaxtda əlavə edə bilər
        List<Thread> threads = new ArrayList<>();
        for (int i = 0; i < 5; i++) {
            final int id = i;
            Thread t = new Thread(() -> {
                for (int j = 0; j < 100; j++) {
                    clq.offer(id * 100 + j); // non-blocking offer
                }
            });
            threads.add(t);
            t.start();
        }
        for (Thread t : threads) t.join();

        System.out.println("ConcurrentLinkedQueue ölçüsü: " + clq.size());
        // size() O(n) — hesablanmalıdır (ConcurrentHashMap.mappingCount() kimi deyil)

        // ── BlockingQueue vs ConcurrentLinkedQueue ──
        // BlockingQueue (ArrayBlockingQueue, LinkedBlockingQueue):
        //   - Bloklanma dəstəkləyir (put/take gözləyir)
        //   - Producer-Consumer pattern üçün ideal
        //   - Capacity məhdudiyyəti qoya bilərsən

        // ConcurrentLinkedQueue:
        //   - Non-blocking (CAS)
        //   - Capacity yoxdur — sonsuz böyüyür
        //   - Yüksək concurrency, az latency
        //   - Producer-Consumer üçün məncə BlockingQueue daha uyğundur
    }
}
```

---

## Digər Concurrent Kolleksiyalar

```java
import java.util.*;
import java.util.concurrent.*;

public class DigerConcurrentCollections {
    public static void main(String[] args) {

        // ── ConcurrentSkipListMap — Thread-safe SortedMap (TreeMap analoquu) ──
        ConcurrentSkipListMap<String, Integer> skipMap = new ConcurrentSkipListMap<>();
        skipMap.put("C", 3); skipMap.put("A", 1); skipMap.put("B", 2);
        System.out.println(skipMap); // {A=1, B=2, C=3} — sıralı
        System.out.println(skipMap.firstKey()); // A
        System.out.println(skipMap.ceilingKey("B")); // B

        // ── ConcurrentSkipListSet — Thread-safe SortedSet (TreeSet analoquu) ──
        ConcurrentSkipListSet<Integer> skipSet = new ConcurrentSkipListSet<>();
        skipSet.add(5); skipSet.add(2); skipSet.add(8);
        System.out.println(skipSet); // [2, 5, 8] — sıralı

        // ── SynchronousQueue — capacity=0 növbə, el-to-el ötürmə ──
        // Producer gözləyir consumer hazır olana qədər
        // Consumer gözləyir producer element verənə qədər
        SynchronousQueue<String> syncQ = new SynchronousQueue<>();
        new Thread(() -> {
            try {
                String elem = syncQ.take(); // producer qoyuncaya qədər gözlər
                System.out.println("Consumer aldı: " + elem);
            } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
        }).start();
        new Thread(() -> {
            try {
                syncQ.put("Salam"); // consumer alana qədər gözlər
                System.out.println("Producer qoydu");
            } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
        }).start();

        // ── DelayQueue — gecikmə ilə element çıxarma ──
        // Yalnız vaxtı keçmiş (delay bitmiş) elementlər poll edilə bilər
        // Scheduled task-lar üçün istifadəli

        // ── PriorityBlockingQueue — Thread-safe PriorityQueue ──
        PriorityBlockingQueue<Integer> pbq = new PriorityBlockingQueue<>();
        pbq.offer(5); pbq.offer(1); pbq.offer(3);
        System.out.println(pbq.poll()); // 1 — ən kiçik, thread-safe
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;
import java.util.concurrent.*;

public class ConcurrentYanlisDoğru {

    // ❌ YANLIŞ: synchronized HashMap istifadəsi (Hashtable analoqudur, köhnədir)
    Map<String, Integer> yanlis1 = Collections.synchronizedMap(new HashMap<>());
    // Bütün əməliyyatlar bir lock — ConcurrentHashMap-dən çox yavaş

    // ✅ DOĞRU: ConcurrentHashMap — bucket-level locking, daha sürətli
    Map<String, Integer> dogru1 = new ConcurrentHashMap<>();

    // ❌ YANLIŞ: synchronized list + iteration
    void yanlisIteration() throws Exception {
        List<String> syncList = Collections.synchronizedList(new ArrayList<>());
        syncList.add("A"); syncList.add("B");

        // İterasiya zamanı əl ilə kilid lazımdır — çox çətin!
        synchronized (syncList) { // unutsaq → ConcurrentModificationException
            for (String s : syncList) {
                System.out.println(s);
            }
        }
    }

    // ✅ DOĞRU: CopyOnWriteArrayList — kilid lazım deyil
    void dogruIteration() {
        CopyOnWriteArrayList<String> cowList = new CopyOnWriteArrayList<>();
        cowList.add("A"); cowList.add("B");

        for (String s : cowList) { // heç vaxt ConcurrentModificationException
            System.out.println(s);
        }
    }

    // ❌ YANLIŞ: ConcurrentHashMap-də compound əməliyyat
    void yanlisCompound(ConcurrentHashMap<String, Integer> map, String key) {
        // Bu iki əməliyyat atomik deyil!
        if (!map.containsKey(key)) {  // Başqa thread burada əlavə edə bilər
            map.put(key, 1);          // Race condition!
        }
    }

    // ✅ DOĞRU: Atomik compound əməliyyat
    void dogruCompound(ConcurrentHashMap<String, Integer> map, String key) {
        map.putIfAbsent(key, 1); // Atomik! Thread-safe!
        // Və ya:
        map.computeIfAbsent(key, k -> 1); // Atomik!
    }

    // ❌ YANLIŞ: Çox yazma olan yerdə CopyOnWriteArrayList
    void yanlisHighWrite() {
        CopyOnWriteArrayList<Integer> list = new CopyOnWriteArrayList<>();
        for (int i = 0; i < 100_000; i++) {
            list.add(i); // Hər dəfə yeni massiv kopyası — O(n) — çox yavaş!
        }
    }

    // ✅ DOĞRU: Çox yazma üçün Collections.synchronizedList və ya başqa struct
    void dogruHighWrite() {
        List<Integer> list = Collections.synchronizedList(new ArrayList<>());
        for (int i = 0; i < 100_000; i++) {
            list.add(i); // Daha sürətli
        }
    }
}
```

---

## İntervyu Sualları

**S1: ConcurrentHashMap Java 7 vs Java 8 fərqi nədir?**

Java 7: 16 Segment, hər Segment `ReentrantLock` — 16 thread eyni vaxtda yazır. Java 8+: Segment yoxdur. Boş bucket-ə CAS ilə yazır (lock yoxdur). Dolu bucket-ə `synchronized(ilk_node)` — yalnız o bucket kilidlənir. Çox daha yüksək concurrency.

**S2: CopyOnWriteArrayList-in iterator-ı niyə ConcurrentModificationException atmır?**

Iterator yaradıldığı anda massivin kopyasını (snapshot) alır. Sonradan edilən dəyişikliklər yeni massivdədir — iterator köhnə massivi görür. Həmişə consistent view təqdim edir.

**S3: BlockingQueue-nun put() və offer() arasındakı fərq nədir?**

`put(e)` — növbə dolu olduqda yer boşalana qədər **bloklayır**. `offer(e, timeout, unit)` — müəyyən vaxt gözlər, sonra false qaytarır. `offer(e)` — əgər yer yoxdursa dərhal false qaytarır (bloklama yoxdur).

**S4: ConcurrentLinkedQueue BlockingQueue-dan nə ilə fərqlənir?**

ConcurrentLinkedQueue: Lock-free (CAS), non-blocking, sonsuz capacity. BlockingQueue: Bloklanma dəstəkləyir (put/take gözləyir), capacity məhdudiyyəti. Producer-Consumer üçün BlockingQueue daha uyğundur. Yüksək concurrency, az latency lazımdırsa ConcurrentLinkedQueue.

**S5: ConcurrentHashMap-də null key/value niyə qadağandır?**

HashMap-də `map.get(key)` null qaytarırsa — ya key yoxdur, ya da dəyəri null. Tək-threadli mühitdə `containsKey()` ilə yoxlamaq olar. Amma çox-threadli mühitdə bu iki əməliyyat arasında dəyişiklik ola bilər — ambiguity. Buna görə null qadağandır.

**S6: CopyOnWriteArrayList-i nə vaxt istifadə etmək olmaz?**

Çox yazma olduqda — hər `add()` O(n) massiv kopyası yaradır. Böyük siyahılarda çox yaddaş və CPU istifadə edir. Yalnız oxuma sıx, yazma nadir olduqda idealdır (listener-lər, observer-lər).

**S7: Semaphore nədir, BlockingQueue ilə əlaqəsi?**

Semaphore resource sayını məhdudlaşdırır. `acquire()` sayaçı azaldır (0-da bloklayır), `release()` artırır. BlockingQueue-nun capacity mexanizmi daxilən Semaphore-a bənzəyir. ThreadPool-un işçi sayını məhdudlaşdırmaq üçün Semaphore istifadə olunur.
