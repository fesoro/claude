# 20. Fail-Fast və Fail-Safe İteratorlar

## Mündəricat
- [Fail-Fast Iterator nədir?](#fail-fast-iterator-nədir)
- [modCount mexanizmi](#modcount-mexanizmi)
- [ConcurrentModificationException](#concurrentmodificationexception)
- [Fail-Safe Iterator nədir?](#fail-safe-iterator-nədir)
- [Təhlükəsiz silmə üsulları](#təhlükəsiz-silmə-üsulları)
- [removeIf() — Java 8+](#removeif--java-8)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Fail-Fast Iterator nədir?

**Fail-fast iterator** — iterasiya zamanı kolleksiya struktural olaraq dəyişdirilsə, dərhal `ConcurrentModificationException` atır. "Tez uğursuz ol, geç deyil" prinsipidir.

```java
import java.util.*;

public class FailFastNümunə {
    public static void main(String[] args) {
        List<String> siyahı = new ArrayList<>(Arrays.asList("A", "B", "C", "D", "E"));

        // ❌ PROBLEM: for-each zamanı silmə
        try {
            for (String elem : siyahı) {
                System.out.println("Oxunur: " + elem);
                if (elem.equals("C")) {
                    siyahı.remove(elem); // ❌ ConcurrentModificationException!
                }
            }
        } catch (ConcurrentModificationException e) {
            System.out.println("XƏTA: " + e.getClass().getSimpleName());
        }

        // ❌ PROBLEM: Çox thread — bir oxuyur, biri dəyişir
        List<Integer> paylaşılan = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5));

        Thread yazanThread = new Thread(() -> {
            try {
                Thread.sleep(10);
                paylaşılan.add(6); // digər thread iterate edərkən əlavə edir
            } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
        });

        yazanThread.start();

        try {
            for (Integer num : paylaşılan) { // iterate edirik
                Thread.sleep(5); // aralıqda yatan thread yazır
                System.out.println(num);
            }
        } catch (ConcurrentModificationException e) {
            System.out.println("Çox-threadli XƏTA tutuldu");
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}
```

---

## modCount mexanizmi

Fail-fast mexanizmi `modCount` (modification count) sahəsi vasitəsilə işləyir:

```java
// ArrayList-in daxili fail-fast mexanizmi (sadələşdirilmiş)
public class ArrayListDaxili<E> extends AbstractList<E> {

    // Hər struktural dəyişiklikdə (add, remove, clear...) artır
    // AbstractList-dən miras alınır
    protected transient int modCount = 0;

    // Struktural dəyişiklik edən metodlar modCount-u artırır
    public boolean add(E e) {
        modCount++; // dəyişiklik qeyd edilir
        // ...
        return true;
    }

    public E remove(int index) {
        modCount++; // dəyişiklik qeyd edilir
        // ...
        return null;
    }

    // Iterator daxili sinfi
    private class Itr implements Iterator<E> {
        int cursor;      // növbəti elementin indeksi
        int lastRet = -1; // sonuncu qaytarılan elementin indeksi

        // Iterator yaradılanda modCount saxlanılır
        int expectedModCount = modCount;

        public boolean hasNext() {
            return cursor != size;
        }

        @SuppressWarnings("unchecked")
        public E next() {
            // Hər next() çağırışında yoxlama
            checkForComodification();
            // ...
            return null;
        }

        // Əsas yoxlama məntiqi
        final void checkForComodification() {
            if (modCount != expectedModCount)
                throw new ConcurrentModificationException();
                // modCount dəyişibsə — iterator yaradılandan bəri struktural dəyişiklik olub
        }
    }
}
```

```java
import java.util.*;
import java.lang.reflect.Field;

public class ModCountDemo {
    public static void main(String[] args) throws Exception {
        ArrayList<String> list = new ArrayList<>(Arrays.asList("A", "B", "C"));

        // Reflection ilə modCount-u görmək
        Field modCountField = AbstractList.class.getDeclaredField("modCount");
        modCountField.setAccessible(true);

        System.out.println("Başlanğıc modCount: " + modCountField.get(list)); // 1 (ArrayList init)

        list.add("D");
        System.out.println("add() sonra: " + modCountField.get(list)); // 2

        list.remove(0);
        System.out.println("remove() sonra: " + modCountField.get(list)); // 3

        list.set(0, "X"); // set() struktural dəyişiklik DEYİL
        System.out.println("set() sonra: " + modCountField.get(list)); // 3 — dəyişmədi!

        // set() modCount-u artırmır → ConcurrentModificationException atmır
        List<String> testList = new ArrayList<>(Arrays.asList("A", "B", "C"));
        for (String s : testList) {
            // Burada set() etmək MÜMKÜNDÜR (struktural deyil)
            // testList.set(0, "X"); // ✅ xəta atmır (modCount artmır)
            // testList.add("D"); // ❌ xəta atır (modCount artır)
        }

        // Hansı əməliyyatlar modCount artırır (struktural):
        // add(), addAll(), remove(), removeAll(), retainAll(), clear(), sort()
        // Hansılar artırmır (struktural deyil):
        // get(), set(), size(), contains(), indexOf()
    }
}
```

---

## ConcurrentModificationException

```java
import java.util.*;

public class CMExceptionNümunələr {
    public static void main(String[] args) {

        // ── Nümunə 1: Şərti silmə ──
        List<Integer> ədədlər = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8));

        // ❌ YANLIŞ
        try {
            for (Integer n : ədədlər) {
                if (n % 2 == 0) ədədlər.remove(n); // ConcurrentModificationException
            }
        } catch (ConcurrentModificationException e) {
            System.out.println("Cüt silmə uğursuz oldu");
        }
        System.out.println("Sonra: " + ədədlər); // qeyri-müəyyən vəziyyət

        // ── Nümunə 2: Maraqlı kənar hal — son elementdən ƏVVƏL silmə ──
        List<String> list = new ArrayList<>(Arrays.asList("A", "B", "C"));
        try {
            for (String s : list) {
                if (s.equals("B")) {
                    list.remove(s); // "B" silinir — bu halda exception atmaya bilər!
                    // Çünki: remove sonra hasNext() false qaytarır (size-1 == cursor)
                    // Bu implementation detail-dir — GÜVƏNMƏ!
                }
            }
        } catch (ConcurrentModificationException e) {
            System.out.println("Exception atıldı");
        }
        System.out.println("List: " + list); // [A, C] — "C" görünmədi!
        // ❌ "C" görünmədi — iterator "B" silinəndə cursor mövqeyini keçdi
        // Bu böyük bir BÖCƏK-dir!

        // ── Nümunə 3: Map-da iterasiya zamanı dəyişiklik ──
        Map<String, Integer> map = new HashMap<>(Map.of("A", 1, "B", 2, "C", 3));
        try {
            for (String key : map.keySet()) {
                if (key.equals("B")) {
                    map.remove(key); // ConcurrentModificationException
                }
            }
        } catch (ConcurrentModificationException e) {
            System.out.println("Map silmə xətası");
        }
    }
}
```

---

## Fail-Safe Iterator nədir?

**Fail-safe iterator** — iterasiya zamanı kolleksiya dəyişsə belə, `ConcurrentModificationException` atmır. Bunun əvəzinə, iterasiyanın başladığı andakı **snapshot** (anlıq kopyası) üzərində işləyir.

```java
import java.util.*;
import java.util.concurrent.*;

public class FailSafeNümunə {
    public static void main(String[] args) {

        // ── CopyOnWriteArrayList — Fail-Safe ──
        CopyOnWriteArrayList<String> cowList = new CopyOnWriteArrayList<>(
            Arrays.asList("A", "B", "C", "D")
        );

        for (String elem : cowList) {
            System.out.println("Oxunur: " + elem);
            if (elem.equals("B")) {
                cowList.add("E");    // ✅ Xəta atmır!
                cowList.remove("C"); // ✅ Xəta atmır!
            }
        }
        // Iterator başladığı andakı snapshotdan oxuyur
        // Dəyişikliklər ("E" əlavəsi, "C" silməsi) iterasiyada görünmür
        System.out.println("Sonra: " + cowList); // [A, B, D, E]
        System.out.println("İterasiyada görülən: A, B, C, D (dəyişikliklər görünmədi)");

        // ── ConcurrentHashMap — Fail-Safe (zəif uyğunluq) ──
        ConcurrentHashMap<String, Integer> chm = new ConcurrentHashMap<>();
        chm.put("A", 1);
        chm.put("B", 2);
        chm.put("C", 3);

        for (Map.Entry<String, Integer> entry : chm.entrySet()) {
            System.out.println(entry.getKey() + "=" + entry.getValue());
            if (entry.getKey().equals("A")) {
                chm.put("D", 4);    // ✅ Xəta atmır
                chm.remove("B");    // ✅ Xəta atmır
            }
            // "D" görünə bilər, "B" görünməyə bilər — qarantı yoxdur
            // "Weakly consistent" — zəif uyğun
        }
        System.out.println("Sonra: " + chm);

        // ── Fail-fast vs Fail-safe müqayisəsi ──
        System.out.println("\n=== Fail-Fast (ArrayList) ===");
        List<String> failFast = new ArrayList<>(Arrays.asList("A", "B", "C"));
        Iterator<String> ffIter = failFast.iterator();
        failFast.add("D"); // iterator yaradıldıqdan sonra dəyişiklik
        try {
            while (ffIter.hasNext()) {
                System.out.println(ffIter.next()); // ❌ ConcurrentModificationException
            }
        } catch (ConcurrentModificationException e) {
            System.out.println("Fail-Fast: Exception tutuldu!");
        }

        System.out.println("\n=== Fail-Safe (CopyOnWriteArrayList) ===");
        CopyOnWriteArrayList<String> failSafe = new CopyOnWriteArrayList<>(Arrays.asList("A","B","C"));
        Iterator<String> fsIter = failSafe.iterator();
        failSafe.add("D"); // iterator yaradıldıqdan sonra dəyişiklik
        while (fsIter.hasNext()) {
            System.out.println(fsIter.next()); // ✅ A, B, C — D görünmür (snapshot)
        }
    }
}
```

### Fail-Fast vs Fail-Safe cədvəli

| Xüsusiyyət | Fail-Fast | Fail-Safe |
|------------|-----------|-----------|
| Exception | `ConcurrentModificationException` | Atmır |
| Mexanizm | `modCount` yoxlaması | Snapshot / CAS |
| Dəyişikliyi görür? | Xeyr (exception atır) | Bəzən (weakly consistent) |
| Yaddaş | Az | Çox (kopya saxlayır) |
| Nümunələr | `ArrayList`, `HashMap`, `HashSet` | `CopyOnWriteArrayList`, `ConcurrentHashMap` |
| Iterator.remove() | Dəstəklənir | CopyOnWrite üçün yox |

---

## Təhlükəsiz silmə üsulları

```java
import java.util.*;
import java.util.stream.*;

public class TəhlükəsizSilmə {
    public static void main(String[] args) {
        List<Integer> list = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8));

        // ── ÜSUL 1: Iterator.remove() ──
        Iterator<Integer> iter = list.iterator();
        while (iter.hasNext()) {
            int n = iter.next();
            if (n % 2 == 0) {
                iter.remove(); // ✅ Təhlükəsiz silmə — expectedModCount də yenilənir
            }
        }
        System.out.println("Iterator.remove(): " + list); // [1, 3, 5, 7]

        // ── ÜSUL 2: removeIf() — Java 8+ (ən tövsiyə olunan) ──
        List<Integer> list2 = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8));
        list2.removeIf(n -> n % 2 == 0); // ✅ internal iterator.remove() istifadə edir
        System.out.println("removeIf(): " + list2); // [1, 3, 5, 7]

        // ── ÜSUL 3: Stream filter — yeni list yaradır ──
        List<Integer> list3 = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8));
        List<Integer> cüt_olmayan = list3.stream()
            .filter(n -> n % 2 != 0)
            .collect(Collectors.toList());
        System.out.println("Stream filter: " + cüt_olmayan); // [1, 3, 5, 7]
        System.out.println("Orijinal dəyişmədi: " + list3); // [1, 2, 3, 4, 5, 6, 7, 8]

        // ── ÜSUL 4: Əks istiqamətdə for dövrəsi ──
        List<Integer> list4 = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8));
        for (int i = list4.size() - 1; i >= 0; i--) { // arxadan əvvələ
            if (list4.get(i) % 2 == 0) {
                list4.remove(i); // ✅ Sürüşmə irəli elementləri təsir etmir
            }
        }
        System.out.println("Tərsinə silmə: " + list4); // [1, 3, 5, 7]

        // ── ÜSUL 5: subList().clear() ──
        // Xüsusi hallarda — məsələn, aralığı sil
        List<Integer> list5 = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5));
        list5.subList(1, 4).clear(); // indeks [1,4) sil
        System.out.println("subList clear: " + list5); // [1, 5]

        // ── ÜSUL 6: removeAll() ──
        List<Integer> list6 = new ArrayList<>(Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8));
        List<Integer> silinecekler = List.of(2, 4, 6, 8);
        list6.removeAll(silinecekler); // ✅
        System.out.println("removeAll(): " + list6); // [1, 3, 5, 7]

        // ── Map-da təhlükəsiz silmə ──
        Map<String, Integer> map = new HashMap<>(Map.of("A", 1, "B", 2, "C", 3, "D", 4));

        // entrySet().removeIf() — ✅ Java 8+
        map.entrySet().removeIf(entry -> entry.getValue() % 2 == 0);
        System.out.println("Map removeIf: " + map); // {A=1, C=3}

        // keySet iterator
        Map<String, Integer> map2 = new HashMap<>(Map.of("A", 1, "B", 2, "C", 3, "D", 4));
        Iterator<Map.Entry<String, Integer>> mapIter = map2.entrySet().iterator();
        while (mapIter.hasNext()) {
            Map.Entry<String, Integer> entry = mapIter.next();
            if (entry.getValue() % 2 == 0) {
                mapIter.remove(); // ✅ Map iterator.remove() dəstəkləyir
            }
        }
        System.out.println("Map iterator.remove: " + map2);
    }
}
```

---

## removeIf() — Java 8+

`removeIf()` — `Collection` interfeysinin default metodudur. Predikata uyğun elementləri silir:

```java
import java.util.*;
import java.util.function.*;

public class RemoveIfDerinlik {
    public static void main(String[] args) {

        // ── Sadə istifadə ──
        List<String> sözlər = new ArrayList<>(Arrays.asList(
            "Java", "", "Python", null, "Go", "  ", "Rust"
        ));

        // Boş və null olan sözləri sil
        sözlər.removeIf(s -> s == null || s.isBlank());
        System.out.println(sözlər); // [Java, Python, Go, Rust]

        // ── Predicate kombinasiyası ──
        List<Integer> ədədlər = new ArrayList<>(Arrays.asList(1, -2, 3, -4, 5, -6, 0));

        Predicate<Integer> mənfidir = n -> n < 0;
        Predicate<Integer> sıfırdır = n -> n == 0;

        ədədlər.removeIf(mənfidir.or(sıfırdır)); // mənfi VƏ ya sıfır olanları sil
        System.out.println(ədədlər); // [1, 3, 5]

        // ── removeIf daxili işləməsi (ArrayList üçün) ──
        // ArrayList.removeIf() — bitset əsaslı optimallaşdırılmış implementasiya:
        // 1. Elementləri gəz, silinəcəkləri BitSet-də işarə et
        // 2. Bir keçiddə silinəcəkləri atlayaraq massivi sıxıştır
        // Adi iterator.remove()-dan daha sürətli (az kopyalama)

        // ── Set və Map üçün ──
        Set<Integer> set = new HashSet<>(Arrays.asList(1, 2, 3, 4, 5, 6));
        set.removeIf(n -> n % 3 == 0); // 3-ə bölünənləri sil
        System.out.println("Set: " + set); // [1, 2, 4, 5]

        Map<String, Integer> map = new HashMap<>();
        map.put("A", 10); map.put("B", 5); map.put("C", 15); map.put("D", 3);

        // Map.entrySet().removeIf()
        map.entrySet().removeIf(e -> e.getValue() < 10);
        System.out.println("Map: " + map); // {A=10, C=15}

        // ── replaceAll() — silmə deyil, dəyişdirmə ──
        List<String> adlar = new ArrayList<>(Arrays.asList("orkhan", "anar", "leyla"));
        adlar.replaceAll(String::toUpperCase); // hamısını böyük hərfə çevir
        System.out.println("replaceAll: " + adlar); // [ORKHAN, ANAR, LEYLA]

        // Map üçün replaceAll
        Map<String, Integer> ballar = new HashMap<>(Map.of("A", 80, "B", 70, "C", 90));
        ballar.replaceAll((k, v) -> v + 5); // hamısına 5 əlavə et
        System.out.println("Map replaceAll: " + ballar);
    }
}
```

---

## Iterator.remove() daxili işləməsi

```java
// ArrayList.Itr.remove() — sadələşdirilmiş JDK mənbəyi
public void remove() {
    if (lastRet < 0)
        throw new IllegalStateException(); // next() çağırılmayıb

    checkForComodification(); // modCount yoxla

    try {
        ArrayList.this.remove(lastRet); // faktiki silmə
        cursor = lastRet; // cursor-u geri qay (silmədən sonra sürüşmə baş verir)
        lastRet = -1;
        expectedModCount = modCount; // ← Bu çox vacibdir!
        // modCount artdı (remove etdi), amma expectedModCount-u yenilədi
        // Beləliklə növbəti checkForComodification() xəta atmayacaq
    } catch (IndexOutOfBoundsException ex) {
        throw new ConcurrentModificationException();
    }
}
```

```java
import java.util.*;

public class IteratorRemoveDetay {
    public static void main(String[] args) {

        // ❌ remove() çağırmadan əvvəl next() çağırılmalıdır
        List<String> list = new ArrayList<>(Arrays.asList("A", "B", "C"));
        Iterator<String> iter = list.iterator();
        try {
            iter.remove(); // IllegalStateException — next() çağırılmayıb!
        } catch (IllegalStateException e) {
            System.out.println("İlk next() lazımdır!");
        }

        // ❌ Eyni elementdə iki dəfə remove()
        try {
            iter.next();
            iter.remove();
            iter.remove(); // IllegalStateException — lastRet = -1-dir artıq
        } catch (IllegalStateException e) {
            System.out.println("İki dəfə remove() olmaz!");
        }

        // ✅ Düzgün istifadə
        List<String> dogruList = new ArrayList<>(Arrays.asList("A", "B", "C", "D"));
        Iterator<String> dogruIter = dogruList.iterator();
        while (dogruIter.hasNext()) {
            String s = dogruIter.next();   // əvvəlcə next()
            if (s.equals("B") || s.equals("D")) {
                dogruIter.remove();        // sonra remove()
            }
        }
        System.out.println("Düzgün silmə: " + dogruList); // [A, C]

        // ── ListIterator — əlavə imkanlar ──
        List<String> li = new ArrayList<>(Arrays.asList("A", "B", "C"));
        ListIterator<String> listIter = li.listIterator();

        while (listIter.hasNext()) {
            String s = listIter.next();
            if (s.equals("B")) {
                listIter.remove();        // sil
                listIter.add("X");        // əlavə et (silinmiş yerə)
            } else {
                listIter.set(s + "!");    // dəyişdir
            }
        }
        System.out.println("ListIterator: " + li); // [A!, X, C!]

        // Tərsinə gəzmək
        while (listIter.hasPrevious()) {
            System.out.print(listIter.previous() + " "); // C! X A!
        }
        System.out.println();
    }
}
```

---

## Bütün Üsulların Müqayisəsi

```java
import java.util.*;
import java.util.stream.*;

public class SilməÜsullarıMüqayisəsi {

    record Nəticə(String üsul, List<Integer> nəticə, long vaxt) {}

    public static void main(String[] args) {
        int N = 100_000;
        List<Integer> test = new ArrayList<>();
        for (int i = 0; i < N; i++) test.add(i);

        // Üsul 1: iterator.remove()
        List<Integer> l1 = new ArrayList<>(test);
        long t = System.nanoTime();
        Iterator<Integer> it = l1.iterator();
        while (it.hasNext()) {
            if (it.next() % 2 == 0) it.remove();
        }
        System.out.printf("iterator.remove(): %dms, ölçü=%d%n",
            (System.nanoTime()-t)/1_000_000, l1.size());

        // Üsul 2: removeIf()
        List<Integer> l2 = new ArrayList<>(test);
        t = System.nanoTime();
        l2.removeIf(n -> n % 2 == 0);
        System.out.printf("removeIf(): %dms, ölçü=%d%n",
            (System.nanoTime()-t)/1_000_000, l2.size());

        // Üsul 3: stream filter + collect
        List<Integer> l3 = new ArrayList<>(test);
        t = System.nanoTime();
        List<Integer> r3 = l3.stream().filter(n -> n % 2 != 0).collect(Collectors.toList());
        System.out.printf("stream filter: %dms, ölçü=%d%n",
            (System.nanoTime()-t)/1_000_000, r3.size());

        // Üsul 4: tərsinə for loop
        List<Integer> l4 = new ArrayList<>(test);
        t = System.nanoTime();
        for (int i = l4.size()-1; i >= 0; i--) {
            if (l4.get(i) % 2 == 0) l4.remove(i);
        }
        System.out.printf("tərsinə for: %dms, ölçü=%d%n",
            (System.nanoTime()-t)/1_000_000, l4.size());

        // Tövsiyə:
        // removeIf() — ən sürətli (ArrayList üçün BitSet optimallaşdırması)
        // iterator.remove() — universal, yaxşı seçim
        // stream filter — yeni list lazımdırsa
        // tərsinə for — sorted list-lərdə faydalı

        System.out.println("\n=== Tövsiyə sırası ===");
        System.out.println("1. removeIf()          — ən sürətli, oxunaqlı");
        System.out.println("2. iterator.remove()   — klassik, universal");
        System.out.println("3. stream filter       — functional, yeni list");
        System.out.println("4. tərsinə for loop    — sorted listlər üçün");
        System.out.println("❌ for-each + remove() — HEÇ VAXT istifadə etmə!");
    }
}
```

---

## YANLIŞ vs DOĞRU Nümunələr

```java
import java.util.*;
import java.util.stream.*;

public class FailFastYanlisDoğru {

    // ❌ YANLIŞ: for-each ilə silmə
    void yanlisForEach(List<String> list) {
        for (String s : list) {
            if (s.startsWith("A")) {
                list.remove(s); // ConcurrentModificationException!
            }
        }
    }

    // ✅ DOĞRU: removeIf()
    void dogruRemoveIf(List<String> list) {
        list.removeIf(s -> s.startsWith("A")); // ✅
    }

    // ❌ YANLIŞ: Iterator.remove() əvəzinə birbaşa remove()
    void yanlisIteratorRemove(List<String> list) {
        Iterator<String> it = list.iterator();
        while (it.hasNext()) {
            String s = it.next();
            if (s.startsWith("A")) {
                list.remove(s); // ❌ — list.remove() deyil, it.remove() lazımdır!
            }
        }
    }

    // ✅ DOĞRU: it.remove() istifadəsi
    void dogruIteratorRemove(List<String> list) {
        Iterator<String> it = list.iterator();
        while (it.hasNext()) {
            String s = it.next();
            if (s.startsWith("A")) {
                it.remove(); // ✅
            }
        }
    }

    // ❌ YANLIŞ: Əvvəldən gəzərək remove(index) — skip problem
    void yanlisForLoop(List<String> list) {
        for (int i = 0; i < list.size(); i++) {
            if (list.get(i).startsWith("A")) {
                list.remove(i); // ❌ Silmədən sonra elementlər sürüşür
                // i artır, amma bir element atlanır!
            }
        }
    }

    // ✅ DOĞRU: Arxadan əvvələ gəz
    void dogruForLoop(List<String> list) {
        for (int i = list.size() - 1; i >= 0; i--) {
            if (list.get(i).startsWith("A")) {
                list.remove(i); // ✅ Silmə yalnız i-dən BÖYÜK indeksləri sürüşdürür
            }
        }
    }

    // ❌ YANLIŞ: ConcurrentHashMap-in iterator sırasına güvənmək
    void yanlisChm(java.util.concurrent.ConcurrentHashMap<String, Integer> map) {
        for (String key : map.keySet()) {
            // Bu iteration zamanı başqa thread map dəyişə bilər
            // Görülən elementlər tam deyil (weakly consistent)
            // Buna güvənən məntiqdən qaçın
        }
    }

    public static void main(String[] args) {
        List<String> test = new ArrayList<>(Arrays.asList("Apple", "Banana", "Avocado", "Cherry"));

        // ❌ Yanlış — elementlər atlanır
        List<String> copy1 = new ArrayList<>(test);
        for (int i = 0; i < copy1.size(); i++) {
            if (copy1.get(i).startsWith("A")) copy1.remove(i);
        }
        System.out.println("Yanlış (skip): " + copy1); // [Banana, Avocado, Cherry] — Avocado qaldı!

        // ✅ Düzgün — arxadan
        List<String> copy2 = new ArrayList<>(test);
        for (int i = copy2.size()-1; i >= 0; i--) {
            if (copy2.get(i).startsWith("A")) copy2.remove(i);
        }
        System.out.println("Düzgün (tərsinə): " + copy2); // [Banana, Cherry] ✅

        // ✅ Ən yaxşı — removeIf
        List<String> copy3 = new ArrayList<>(test);
        copy3.removeIf(s -> s.startsWith("A"));
        System.out.println("removeIf: " + copy3); // [Banana, Cherry] ✅
    }
}
```

---

## İntervyu Sualları

**S1: ConcurrentModificationException nədir və niyə baş verir?**

Bir iterator aktiv olarkən kolleksiya struktural olaraq dəyişdirildikdə atılır. `modCount` mexanizmi sayəsinde aşkarlanır: iterator `expectedModCount` saxlayır, hər `next()`-də `modCount` ilə müqayisə edir. Fərq varsa — exception.

**S2: fail-fast və fail-safe arasındakı fərq nədir?**

Fail-fast: iterasiya zamanı dəyişiklik olduqda dərhal `ConcurrentModificationException` atır (`ArrayList`, `HashMap`). Fail-safe: snapshot üzərində işləyir, exception atmır (`CopyOnWriteArrayList`), ya da weakly consistent-dir (`ConcurrentHashMap`).

**S3: Iterator.remove() niyə təhlükəsizdir, list.remove() isə yox?**

`Iterator.remove()` — silmə əməliyyatından sonra `expectedModCount = modCount` edir. Yəni iterator öz dəyişikliyini "bilir". `list.remove()` isə `modCount`-u artırır amma iterator-un `expectedModCount`-unu yeniləmir — mismatch baş verir.

**S4: for-each dövrəsinin "son elementin silinməsi" kənar halı nədir?**

Bəzi hallarda son elementdən əvvəlki elementi silsək, `hasNext()` false qaytarır (cursor == size-1). Buna görə `checkForComodification()` çağırılmır. Exception atmır, amma son element görünmür. Bu implementation detail-dir — GÜVƏNMƏ!

**S5: removeIf() niyə daha sürətli?**

ArrayList-in `removeIf()` optimizasiyası: 1) predikata uyğun elementləri BitSet-də işarə edir, 2) bir keçiddə silinməli olmayan elementləri ötürür (sıxışdırır). Normal `iterator.remove()` hər silmədə System.arraycopy çağırır — daha çox kopyalama.

**S6: ConcurrentHashMap-in iterator-u fail-safe-dirmi?**

"Weakly consistent" — tam fail-safe deyil. `ConcurrentModificationException` atmır, amma iterasiya başladıqdan sonra edilən dəyişiklikləri görüb-görməyəcəyi qarantı deyil. Bəziləri görünür, bəziləri görünmür.

**S7: Çox thread eyni ArrayList-i oxuyursa problem varmı?**

Yalnız oxuma (get, size, contains) — `modCount` dəyişmir — thread-safe oxuma. Bir thread yazarsa (add, remove) — digər thread-lər üçün memory visibility problem var (Java Memory Model). `volatile` və ya synchronization lazımdır. Amma ConcurrentModificationException yalnız iterator aktiv ikən yazma baş versə atılır.

**S8: ListIterator fail-fast-dirmi?**

Bəli, `ListIterator` da fail-fast-dir. Lakin `ListIterator.set()` və `ListIterator.add()` — bunlar expectedModCount-u yenilədiyindən exception atmır (öz dəyişiklikləri sayılmır).
