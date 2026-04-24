# 051 — Streams — Parallel Streams
**Səviyyə:** İrəli


## Mündəricat
1. [Parallel Stream nədir?](#parallel-stream-nədir)
2. [ForkJoinPool](#forkjoinpool)
3. [Nə vaxt paralel sürətli?](#nə-vaxt-sürətli)
4. [Nə vaxt paralel istifadə ETMƏMƏLİ?](#nə-vaxt-istifadə-etməmək)
5. [Thread-Safety Problemləri](#thread-safety)
6. [Performans ölçümü](#performans)
7. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Parallel Stream nədir?

Parallel stream — elementləri birdən çox thread-də eyni anda emal edən stream. `ForkJoin` çərçivəsindən istifadə edir.

```java
import java.util.*;
import java.util.stream.*;

List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

// Ardıcıl (sequential) — bir thread
List<Integer> ardıcıl = rəqəmlər.stream()
    .filter(n -> n % 2 == 0)
    .collect(Collectors.toList());

// Paralel — birdən çox thread
List<Integer> paralel = rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .collect(Collectors.toList());

// Mövcud stream-i paralel et
List<Integer> paralel2 = rəqəmlər.stream()
    .parallel()               // Ardıcıldan paralel keçid
    .filter(n -> n % 2 == 0)
    .collect(Collectors.toList());

// Yenidən ardıcıl et
List<Integer> ardıcıl2 = rəqəmlər.parallelStream()
    .sequential()             // Paraleldan ardıcıla keçid
    .filter(n -> n % 2 == 0)
    .collect(Collectors.toList());
```

### Thread-ləri görmək

```java
// Hansı thread-lərin işlədiyini izlə
List<Integer> rəqəmlər = new ArrayList<>();
for (int i = 0; i < 8; i++) rəqəmlər.add(i);

System.out.println("=== Ardıcıl ===");
rəqəmlər.stream()
    .map(n -> {
        System.out.println(n + " → " + Thread.currentThread().getName());
        return n * n;
    })
    .forEach(System.out::println);

System.out.println("\n=== Paralel ===");
rəqəmlər.parallelStream()
    .map(n -> {
        System.out.println(n + " → " + Thread.currentThread().getName());
        return n * n;
    })
    .forEach(System.out::println);
// Müxtəlif thread adları: main, ForkJoinPool.commonPool-worker-1, ...
```

---

## ForkJoinPool

Java-nın paralel stream-ləri **ForkJoinPool.commonPool()** istifadə edir.

```java
import java.util.concurrent.ForkJoinPool;

// Default thread sayı — CPU core sayı - 1
int paralelliyəSəviyyəsi = ForkJoinPool.commonPool().getParallelism();
System.out.println("Paralel thread sayı: " + paralelliyəSəviyyəsi);
// Məsələn: 7 (8 core - 1 = 7)

// JVM parametri ilə dəyişmək:
// -Djava.util.concurrent.ForkJoinPool.common.parallelism=4
```

### Öz ForkJoinPool-u istifadə etmək

```java
// Default commonPool əvəzinə öz pool-unu istifadə etmək
// Bu, paralel stream-lərin digər taskları bloklamasının qarşısını alır

ForkJoinPool özünPool = new ForkJoinPool(4); // 4 thread

try {
    List<Integer> nəticə = özünPool.submit(() ->
        rəqəmlər.parallelStream()
            .filter(n -> n % 2 == 0)
            .collect(Collectors.toList())
    ).get(); // Nəticəni gözlə

    System.out.println(nəticə);
} catch (Exception e) {
    e.printStackTrace();
} finally {
    özünPool.shutdown(); // Pool-u bağla
}
```

### ForkJoin mexanizmi

```
Böyük tapşırıq
     ↓ fork (böl)
[1-250] [251-500] [501-750] [751-1000]
     ↓ hər biri öz thread-ında işlənir
[nəticə1] [nəticə2] [nəticə3] [nəticə4]
     ↓ join (birləşdir)
     Yekun nəticə
```

---

## Nə vaxt paralel sürətli?

### Paralelin effektiv olduğu hallar

**1. Böyük məlumat topluları:**

```java
import java.util.concurrent.TimeUnit;

int BÖYÜK_SAY = 10_000_000;
List<Integer> böyükSiyahı = new ArrayList<>(BÖYÜK_SAY);
for (int i = 0; i < BÖYÜK_SAY; i++) {
    böyükSiyahı.add(i);
}

// Ardıcıl
long başlangıc = System.nanoTime();
long ardıcılCəm = böyükSiyahı.stream()
    .mapToLong(Integer::longValue)
    .sum();
long ardıcılVaxt = System.nanoTime() - başlangıc;

// Paralel
başlangıc = System.nanoTime();
long paralelCəm = böyükSiyahı.parallelStream()
    .mapToLong(Integer::longValue)
    .sum();
long paralelVaxt = System.nanoTime() - başlangıc;

System.out.printf("Ardıcıl: %d ms%n", TimeUnit.NANOSECONDS.toMillis(ardıcılVaxt));
System.out.printf("Paralel: %d ms%n", TimeUnit.NANOSECONDS.toMillis(paralelVaxt));
System.out.printf("Sürətlənmə: %.1fx%n", (double) ardıcılVaxt / paralelVaxt);
// Paralel ~3-7x daha sürətli (core sayından asılı)
```

**2. CPU-bound əməliyyatlar:**

```java
// CPU-bound — hesab ağır, IO yoxdur
private static boolean sadəSayMı(int n) {
    if (n < 2) return false;
    for (int i = 2; i <= Math.sqrt(n); i++) {
        if (n % i == 0) return false;
    }
    return true;
}

// 1-1000000 arasında sadə sayları tap
List<Integer> rəqəmlər = IntStream.rangeClosed(2, 1_000_000)
    .boxed().collect(Collectors.toList());

// Ardıcıl
long ardıcıl = rəqəmlər.stream()
    .filter(Main::sadəSayMı)
    .count();

// Paralel — CPU-bound olduğu üçün əhəmiyyətli sürətlənmə!
long paralel = rəqəmlər.parallelStream()
    .filter(Main::sadəSayMı)
    .count();
```

**3. Müstəqil əməliyyatlar:**

```java
// Hər elementin emalı müstəqildir — paralel ideal!
List<String> URLs = List.of("url1", "url2", "url3", "url4");

// Hər URL-i müstəqil emal et (fərz edək HTTP sorğu simulyasiyası)
List<String> nəticələr = URLs.parallelStream()
    .map(url -> emalEt(url)) // Müstəqil əməliyyat
    .collect(Collectors.toList());
```

---

## Nə vaxt paralel İSTİFADƏ ETMƏMƏLİ?

### 1. Kiçik kolleksiyalar

```java
// YANLIŞ — kiçik siyahı üçün paralel yavaşdır!
List<Integer> kiçikSiyahı = List.of(1, 2, 3, 4, 5);

// Thread yaratmaq + birləşdirmə xərci > hesabın xərci
long paralel = kiçikSiyahı.parallelStream()
    .mapToLong(Integer::longValue)
    .sum(); // Ardıcıldan YAVAŞ!

// DOĞRU
long ardıcıl = kiçikSiyahı.stream()
    .mapToLong(Integer::longValue)
    .sum();
```

### 2. IO-bound əməliyyatlar

```java
// YANLIŞ — IO-bound əməliyyatlarda paralel stream fayda vermir
// ForkJoinPool CPU hesabı üçün optimallaşdırılıb

// IO-bound üçün CompletableFuture + ExecutorService daha yaxşıdır
List<String> URLs = List.of("url1", "url2", "url3");

// YANLIŞ yanaşma — ForkJoinPool thread-ləri bloklayır
List<String> nəticələr = URLs.parallelStream()
    .map(url -> httpGet(url)) // IO — thread bloklanır
    .collect(Collectors.toList());

// DOĞRU — IO üçün virtual thread (Java 21+) yaxud CompletableFuture
ExecutorService executor = Executors.newVirtualThreadPerTaskExecutor();
List<CompletableFuture<String>> futures = URLs.stream()
    .map(url -> CompletableFuture.supplyAsync(() -> httpGet(url), executor))
    .collect(Collectors.toList());

List<String> ioNəticələr = futures.stream()
    .map(CompletableFuture::join)
    .collect(Collectors.toList());
```

### 3. Sıra önəmli əməliyyatlar

```java
// YANLIŞ — paralel stream-də sıra zəmanəti yoxdur
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5);

// forEach sıranı qorumur paralel-də
rəqəmlər.parallelStream()
    .forEach(System.out::println); // Sıra: 3,1,4,2,5 (dəyişkən)

// DOĞRU — sıra lazımdırsa forEachOrdered
rəqəmlər.parallelStream()
    .forEachOrdered(System.out::println); // 1,2,3,4,5 — amma paralel üstünlüyü itirilir

// Daha yaxşı — ardıcıl stream istifadə et
rəqəmlər.stream()
    .forEach(System.out::println); // 1,2,3,4,5
```

### 4. Sıralı mənbə + limit

```java
// LinkedList — paralel üçün pis mənbə (ardıcıl giriş tələb edir)
List<Integer> linkedList = new LinkedList<>(List.of(1, 2, 3, 4, 5));

// Paralel stream LinkedList-i bölüşdürmək üçün hamısını keçməlidir
// ArrayList daha yaxşıdır (random access)

// sorted() + limit() paralel-də çox yavaşdır
List<Integer> böyükSiyahı = new ArrayList<>();
for (int i = 1_000_000; i >= 0; i--) böyükSiyahı.add(i);

// YANLIŞ — sorted() paralel-də bütün elementləri toplayır
böyükSiyahı.parallelStream()
    .sorted()                        // Hamısını topla + sırala
    .limit(10)                       // Sonra yalnız 10 al
    .collect(Collectors.toList());   // Çox yavaş!

// DOĞRU
böyükSiyahı.stream()
    .sorted()
    .limit(10)
    .collect(Collectors.toList());
```

---

## Thread-Safety Problemləri

### YANLIŞ — shared mutable state

```java
// YANLIŞ — thread-safe olmayan əməliyyat
List<Integer> rəqəmlər = new ArrayList<>();
for (int i = 0; i < 1000; i++) rəqəmlər.add(i);

List<Integer> nəticə = new ArrayList<>(); // Thread-safe deyil!

rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .forEach(nəticə::add); // DATA RACE! ArrayList thread-safe deyil

System.out.println("Ölçü: " + nəticə.size()); // 500 deyil! (daha az ola bilər)
// ArrayList.add() thread-safe deyil — elementlər itirilir!
```

### DOĞRU — collect() istifadə et

```java
// DOĞRU — collect() thread-safe toplayıcı istifadə edir
List<Integer> cütlər = rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .collect(Collectors.toList()); // Thread-safe!

System.out.println("Ölçü: " + cütlər.size()); // Həmişə 500
```

### YANLIŞ — paylaşılan dəyişən

```java
// YANLIŞ — shared counter
int[] sayac = {0}; // Bəzi proqramçılar belə edir — YANLIŞ!

rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .forEach(n -> sayac[0]++); // RACE CONDITION!

System.out.println(sayac[0]); // 500 deyil!

// DOĞRU — count() istifadə et
long say = rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .count(); // Thread-safe!

// Yaxud AtomicInteger
AtomicInteger atomicSay = new AtomicInteger(0);
rəqəmlər.parallelStream()
    .filter(n -> n % 2 == 0)
    .forEach(n -> atomicSay.incrementAndGet()); // Thread-safe
```

### YANLIŞ — yan effektli əməliyyatlar

```java
// YANLIŞ — xarici strukturu dəyişdirmək
Map<String, List<Integer>> qruplar = new HashMap<>(); // Thread-safe deyil

rəqəmlər.parallelStream()
    .forEach(n -> {
        String açar = n % 2 == 0 ? "cüt" : "tək";
        qruplar.computeIfAbsent(açar, k -> new ArrayList<>()).add(n);
        // computeIfAbsent thread-safe deyil HashMap-də!
    });

// DOĞRU — groupingBy istifadə et
Map<String, List<Integer>> doğruQruplar = rəqəmlər.parallelStream()
    .collect(Collectors.groupingBy(n -> n % 2 == 0 ? "cüt" : "tək"));
// Thread-safe collector!
```

### Stateless vs Stateful əməliyyatlar

```java
// Stateless (vəziyyətsiz) — paralel üçün ideal
rəqəmlər.parallelStream()
    .filter(n -> n > 5)        // Stateless
    .map(n -> n * 2)            // Stateless
    .collect(Collectors.toList());

// Stateful (vəziyyətli) — paralel-də problem ola bilər
// sorted(), distinct(), limit(), skip() — stateful intermediate ops
rəqəmlər.parallelStream()
    .sorted()    // Stateful — bütün elementləri toplamalıdır
    .distinct()  // Stateful — görülmüş elementləri yadda saxlamalıdır
    .collect(Collectors.toList());
// Bu işləyir, amma paralel üstünlüyü azalır
```

---

## Performans ölçümü

### Düzgün benchmark — JMH

```java
// Production-da JMH (Java Microbenchmark Harness) istifadə et
// Sadə System.currentTimeMillis() yeterince dəqiq deyil — JVM warmup lazımdır

// Sadə test (illüstrativ):
public static void benchmark(String ad, Runnable test) {
    // JVM warm-up
    for (int i = 0; i < 5; i++) test.run();

    // Ölçüm
    long start = System.nanoTime();
    for (int i = 0; i < 10; i++) test.run();
    long elapsed = System.nanoTime() - start;

    System.out.printf("%s: %d ms (orta)%n", ad, elapsed / 10 / 1_000_000);
}

List<Integer> data = IntStream.range(0, 1_000_000).boxed().collect(Collectors.toList());

benchmark("Ardıcıl cəm",
    () -> data.stream().mapToLong(Integer::longValue).sum());

benchmark("Paralel cəm",
    () -> data.parallelStream().mapToLong(Integer::longValue).sum());
```

### N-split qaydası

Paralelin faydalı olması üçün çox kobud qayda:
- Elementlər sayı ≥ `10,000` — paralel düşünmək olar
- Hər elementin emal vaxtı əhəmiyyətli olmalıdır
- Birləşdirmə (merge) ucuz olmalıdır

```java
// Paralel stream seçimini köməkçi funksiya ilə qərar ver
public static <T, R> R stream(
        Collection<T> kolleksiya,
        Function<Stream<T>, R> əməliyyat) {

    Stream<T> stream = kolleksiya.size() > 10_000
        ? kolleksiya.parallelStream()    // Böyük → paralel
        : kolleksiya.stream();           // Kiçik → ardıcıl

    return əməliyyat.apply(stream);
}

// İstifadə
List<Integer> məlumat = getDataFromDB(); // Ölçüsü bilinmir
long say = stream(məlumat, s -> s.filter(n -> n > 100).count());
```

---

## İntervyu Sualları

**S: Paralel stream nə vaxt ardıcıldan yavaş ola bilər?**
C: 1) Kiçik kolleksiyalarda — thread yaratma xərci hesab xərcdən çoxdur; 2) Overhead — parçalama (splitting) və birləşdirmə (merging) xərci; 3) IO-bound əməliyyatlarda — thread-lər bloklanır; 4) Sıralı əməliyyatlarda (sorted + limit); 5) Thread contention — shared state varsa.

**S: Paralel stream-də `forEach` ilə `forEachOrdered` fərqi nədir?**
C: `forEach` paralel stream-də sıranı zəmanət etmir — thread-lər istənilən sırada çap edir. `forEachOrdered` sıranı qoruyur amma paralel üstünlüyünü itirir (thread-lər gözləyir). Sıra lazımdırsa ardıcıl stream daha məntiqlidir.

**S: Paralel stream-də thread-safety necə təmin edilir?**
C: 1) `collect()` istifadə et — `forEach` ilə xarici siyahıya əlavə etmə; 2) Paylaşılan mutable state istifadə etmə; 3) `AtomicInteger`, `LongAdder` kimi thread-safe siniflərdən istifadə et; 4) Stateless lambda-lar yaz (xarici dəyişənlərə münasibət qurma).

**S: ForkJoinPool nədir?**
C: Java-nın iş ödəmə (work-stealing) alqoritmi əsasında işləyən thread pool-u. Böyük tapşırıqları rekursiv olaraq kiçik tapşırıqlara bölür (fork), nəticələri birləşdirir (join). Paralel stream-lər `ForkJoinPool.commonPool()` istifadə edir — default olaraq `CPU core sayı - 1` thread.

**S: Hansı kolleksiya növləri paralel üçün daha əlverişlidir?**
C: `ArrayList`, massivlər — random access dəstəkləndiyi üçün yaxşı bölünür. `LinkedList` — ardıcıl giriş lazımdır, pis bölünür. `HashSet`, `TreeSet` — orta. Spliterator-ların `SIZED` + `SUBSIZED` xüsusiyyəti varsa yaxşı işləyir.

**S: Parallel stream-i nə vaxt istifadə etmək lazımdır?**
C: Yalnız ölçüldükdən sonra! Kriteriyalar: 1) Böyük məlumat toplusu (≥10,000 element), 2) CPU-bound əməliyyatlar (çətin hesab), 3) Müstəqil əməliyyatlar (bir elementin nəticəsi digərindən asılı deyil), 4) Stateless lambdalar, 5) Benchmark göstərir ki, paralel daha sürətlidir.
