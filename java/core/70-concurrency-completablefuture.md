# 70 — Concurrency: CompletableFuture

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [CompletableFuture vs Future](#completablefuture-vs-future)
2. [supplyAsync / runAsync](#supplyasync--runasync)
3. [thenApply / thenAccept / thenRun](#thenapply--thenaccept--thenrun)
4. [thenApplyAsync — Asinxron Davam](#thenapplyasync)
5. [thenCompose — flatMap](#thencompose--flatmap)
6. [thenCombine — İki Nəticəni Birləşdir](#thencombine)
7. [allOf / anyOf](#allof--anyof)
8. [Xəta İdarəsi](#xeta-idaresi)
9. [Praktik Nümunə](#praktik-numune)
10. [İntervyu Sualları](#intervyu-sualları)

---

## CompletableFuture vs Future

`Future` — Java 5-dən var, amma çox məhduddur:

```java
// Future — problemlər:
ExecutorService executor = Executors.newFixedThreadPool(2);
Future<String> future = executor.submit(() -> fetchData());

// 1. Blok edici — get() çağırılana qədər əsas thread gözləyir
String result = future.get(); // ← Bloklanma!

// 2. Zəncirləmə mümkün deyil — callback yoxdur
// 3. İki Future-u birləşdirmək çətin
// 4. Exception idarəsi çirkin
// 5. Manual tamamlama yoxdur
```

`CompletableFuture` — Java 8-dən, `Future` + `CompletionStage`:

```java
// CompletableFuture — üstünlüklər:
CompletableFuture
    .supplyAsync(() -> fetchUser(userId))           // Asinxron başla
    .thenApply(user -> fetchOrders(user))           // Nəticə gəldikdə çevir
    .thenAccept(orders -> displayOrders(orders))    // Son istehlak
    .exceptionally(e -> { logError(e); return null; }); // Xəta idarəsi

// Bloklanma yoxdur! Hər şey callback zənciri ilə
```

---

## supplyAsync / runAsync

```java
import java.util.concurrent.*;

public class AsyncBasics {
    public static void main(String[] args) throws Exception {
        // supplyAsync — nəticə qaytarır (Supplier<T>)
        CompletableFuture<String> supply = CompletableFuture.supplyAsync(() -> {
            System.out.println("supplyAsync: " + Thread.currentThread().getName());
            // Default: ForkJoinPool.commonPool() istifadə edir
            return "Məlumat yükləndi";
        });

        // runAsync — nəticə qaytarmır (Runnable)
        CompletableFuture<Void> run = CompletableFuture.runAsync(() -> {
            System.out.println("runAsync: " + Thread.currentThread().getName());
            // Nəticə qaytarmır
        });

        // Xüsusi executor ilə
        ExecutorService myPool = Executors.newFixedThreadPool(4);

        CompletableFuture<String> withExecutor = CompletableFuture.supplyAsync(() -> {
            System.out.println("Xüsusi pool: " + Thread.currentThread().getName());
            return "Xüsusi pool nəticəsi";
        }, myPool); // ← Xüsusi executor

        // Nəticəni al
        System.out.println(supply.get());
        System.out.println(withExecutor.get());

        myPool.shutdown();
    }
}
```

**Mühüm:** Default olaraq `ForkJoinPool.commonPool()` istifadə edilir. Production-da xüsusi executor vermək tövsiyə olunur ki, digər tapşırıqlara təsir olmasın.

---

## thenApply / thenAccept / thenRun

Bu üç metod nəticənin **eyni thread-də** emalı üçündür (sinxron davam):

```java
public class ThenMethodsDemo {
    public static void main(String[] args) throws Exception {
        // thenApply — T → U çevirmə (Function<T, U>)
        // Nəticəni alır, yeni nəticə qaytarır
        CompletableFuture<Integer> lengthFuture = CompletableFuture
            .supplyAsync(() -> "Salam Dünya")    // String qaytarır
            .thenApply(String::toUpperCase)       // String → String
            .thenApply(String::length);           // String → Integer

        System.out.println("Uzunluq: " + lengthFuture.get()); // 11

        // thenAccept — nəticəni istehlak edir, void qaytarır (Consumer<T>)
        CompletableFuture<Void> acceptFuture = CompletableFuture
            .supplyAsync(() -> fetchUserName(1))
            .thenApply(name -> "Xoş gəldiniz, " + name + "!")
            .thenAccept(message -> System.out.println(message)); // Çap edir, nəticə yoxdur

        // thenRun — nəticəyə baxmır, sadəcə sonra bir iş edir (Runnable)
        CompletableFuture<Void> runFuture = CompletableFuture
            .supplyAsync(() -> saveToDatabase("data"))
            .thenRun(() -> System.out.println("DB-yə yazma tamamlandı!")); // Nəticəni bilmir

        acceptFuture.get();
        runFuture.get();
    }

    static String fetchUserName(int id) {
        return "Orkhan"; // DB sorğusu simulyasiyası
    }

    static String saveToDatabase(String data) {
        return "OK"; // DB əməliyyatı simulyasiyası
    }
}
```

**Üç metodun müqayisəsi:**

| Metod          | Input    | Output   | İstifadə halı               |
|----------------|----------|----------|-----------------------------|
| `thenApply`    | T        | U        | Çevirmə/transformasiya      |
| `thenAccept`   | T        | void     | Son istehlak (çap, log)     |
| `thenRun`      | —        | void     | Nəticəsiz əməliyyat (bildiriş) |

---

## thenApplyAsync

`thenApply` — əvvəlki thread-də icra edir.
`thenApplyAsync` — yeni thread-də (pool-da) icra edir.

```java
public class AsyncVsSyncContinuation {
    public static void main(String[] args) throws Exception {
        ExecutorService pool1 = Executors.newFixedThreadPool(2, r -> new Thread(r, "pool1"));
        ExecutorService pool2 = Executors.newFixedThreadPool(2, r -> new Thread(r, "pool2"));

        CompletableFuture
            .supplyAsync(() -> {
                System.out.println("1. Addım: " + Thread.currentThread().getName()); // pool1
                return "data";
            }, pool1)
            .thenApply(data -> {
                // SINXRON — eyni thread-də (pool1 və ya tamamlayan thread)
                System.out.println("2. Addım (sync): " + Thread.currentThread().getName());
                return data.toUpperCase();
            })
            .thenApplyAsync(data -> {
                // ASİNXRON — ForkJoinPool.commonPool()-da
                System.out.println("3. Addım (async default): " + Thread.currentThread().getName());
                return data + "!";
            })
            .thenApplyAsync(data -> {
                // ASİNXRON — xüsusi pool-da
                System.out.println("4. Addım (async custom): " + Thread.currentThread().getName());
                return data;
            }, pool2)
            .thenAccept(System.out::println)
            .get();

        pool1.shutdown();
        pool2.shutdown();
    }
}
```

**Nə vaxt `Async` versiyasını istifadə et?**
- Uzun müddətli əməliyyatlar (IO, DB) — ki, pool thread-ini bloklasın
- Fərqli thread pool-da icra lazımdırsa
- Paralellik lazımdırsa

---

## thenCompose — flatMap

`thenApply` → `CompletableFuture<CompletableFuture<T>>` (iç-içə, pis)
`thenCompose` → `CompletableFuture<T>` (düz, yaxşı)

```java
public class ThenComposeDemo {
    public static void main(String[] args) throws Exception {
        // YANLIŞ — thenApply ilə iç-içə Future
        CompletableFuture<CompletableFuture<String>> nested =
            CompletableFuture.supplyAsync(() -> 42)
                .thenApply(userId -> fetchUserAsync(userId)); // ← iç-içə!

        // İç-içəni açmaq çirkin:
        String result1 = nested.get().get(); // iki dəfə get() — pis!

        // DOĞRU — thenCompose ilə düz zəncir
        CompletableFuture<String> flat =
            CompletableFuture.supplyAsync(() -> 42)
                .thenCompose(userId -> fetchUserAsync(userId)); // ← düz!

        String result2 = flat.get(); // bir dəfə get() — yaxşı!
        System.out.println(result2);
    }

    // CompletableFuture qaytaran metod
    static CompletableFuture<String> fetchUserAsync(int userId) {
        return CompletableFuture.supplyAsync(() -> {
            // DB sorğusu simulyasiyası
            return "İstifadəçi-" + userId;
        });
    }
}
```

**Real nümunə — zəncirli asinxron sorğular:**

```java
CompletableFuture<Order> orderFuture = CompletableFuture
    .supplyAsync(() -> getUserId(sessionToken))       // String → int
    .thenCompose(userId -> fetchUserAsync(userId))     // int → CF<User>
    .thenCompose(user -> fetchLatestOrderAsync(user))  // User → CF<Order>
    .thenCompose(order -> enrichOrderAsync(order));    // Order → CF<Order>

// Stream.flatMap() ilə eyni konsept!
```

---

## thenCombine — İki Nəticəni Birləşdir

```java
public class ThenCombineDemo {
    public static void main(String[] args) throws Exception {
        // İki müstəqil asinxron tapşırıq paralel işləyir
        CompletableFuture<String> userFuture = CompletableFuture
            .supplyAsync(() -> {
                sleep(1000);
                return "Orkhan";
            });

        CompletableFuture<Integer> ageFuture = CompletableFuture
            .supplyAsync(() -> {
                sleep(1500);
                return 28;
            });

        // İkisi də bitdikdən sonra birləşdirir
        CompletableFuture<String> combined = userFuture.thenCombine(
            ageFuture,
            (name, age) -> name + " (" + age + " yaş)" // BiFunction
        );

        System.out.println(combined.get()); // ~1.5 saniyə (paralel!) → "Orkhan (28 yaş)"

        // thenAcceptBoth — birləşdirir amma nəticə qaytarmır
        userFuture.thenAcceptBoth(ageFuture, (name, age) -> {
            System.out.println("İstifadəçi: " + name + ", Yaş: " + age);
        }).get();

        // runAfterBoth — hər ikisi bitdikdə bir iş görür
        userFuture.runAfterBoth(ageFuture, () -> {
            System.out.println("Hər iki sorğu tamamlandı!");
        }).get();
    }

    static void sleep(long ms) {
        try { Thread.sleep(ms); } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
    }
}
```

---

## allOf / anyOf

```java
public class AllOfAnyOfDemo {
    public static void main(String[] args) throws Exception {
        // allOf — HƏMİŞİ hamısı bitənə gözlə (Void qaytarır)
        CompletableFuture<String> f1 = CompletableFuture.supplyAsync(() -> { sleep(1000); return "A"; });
        CompletableFuture<String> f2 = CompletableFuture.supplyAsync(() -> { sleep(2000); return "B"; });
        CompletableFuture<String> f3 = CompletableFuture.supplyAsync(() -> { sleep(1500); return "C"; });

        CompletableFuture<Void> allDone = CompletableFuture.allOf(f1, f2, f3);
        allDone.get(); // ~2 saniyə (ən uzunu gözləyir)

        // Nəticələri toplamaq
        List<String> results = List.of(f1.get(), f2.get(), f3.get());
        System.out.println("Hamısı: " + results); // [A, B, C]

        // Daha yaxşı üsul — stream ilə
        CompletableFuture<List<String>> allResults = CompletableFuture
            .allOf(f1, f2, f3)
            .thenApply(v -> Stream.of(f1, f2, f3)
                .map(CompletableFuture::join) // join() — get() kimi amma unchecked exception
                .collect(Collectors.toList()));

        System.out.println(allResults.get()); // [A, B, C]

        // anyOf — İLK bitəni qaytar (Object qaytarır!)
        CompletableFuture<Object> anyDone = CompletableFuture.anyOf(f1, f2, f3);
        Object first = anyDone.get(); // ~1 saniyə → "A" (f1 ən sürətli)
        System.out.println("İlk nəticə: " + first);
    }

    static void sleep(long ms) {
        try { Thread.sleep(ms); } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
    }
}
```

**Diqqət:** `anyOf` `Object` qaytarır, type-safe deyil. Eyni tip future-larla istifadə et.

---

## Xəta İdarəsi

```java
public class ErrorHandlingDemo {
    public static void main(String[] args) throws Exception {

        // exceptionally — yalnız xəta olarsa çağırılır
        CompletableFuture<String> result1 = CompletableFuture
            .supplyAsync(() -> {
                if (Math.random() > 0.5) throw new RuntimeException("Şəbəkə xətası!");
                return "Uğurlu nəticə";
            })
            .exceptionally(ex -> {
                System.out.println("Xəta tutuldu: " + ex.getMessage());
                return "Default dəyər"; // Xəta olarsa bunu qaytar
            });

        System.out.println(result1.get()); // Ya "Uğurlu nəticə" ya da "Default dəyər"

        // handle — həm uğur, həm xəta hallarını idarə edir
        CompletableFuture<String> result2 = CompletableFuture
            .supplyAsync(() -> fetchData())
            .handle((data, ex) -> {
                if (ex != null) {
                    System.out.println("Xəta: " + ex.getMessage());
                    return "Ehtiyat dəyər";
                }
                return data.toUpperCase(); // Uğur halında çevir
            });

        // whenComplete — nəticəni dəyişdirmir, sadəcə yan effekt (log, metrik)
        CompletableFuture<String> result3 = CompletableFuture
            .supplyAsync(() -> "data")
            .whenComplete((data, ex) -> {
                // Nəticəni dəyişdirmir!
                if (ex != null) {
                    System.err.println("Metrik: Xəta baş verdi - " + ex.getMessage());
                } else {
                    System.out.println("Metrik: Uğurla tamamlandı - " + data);
                }
            });

        result2.get();
        result3.get();
    }

    // exceptionally vs handle vs whenComplete müqayisəsi:
    /*
        exceptionally: Yalnız xəta olarsa, nəticəni dəyişdirə bilər. (T → T)
        handle:        Həm xəta, həm uğur — nəticəni dəyişdirə bilər. (T → U)
        whenComplete:  Həm xəta, həm uğur — nəticəni dəyişdirə BİLMƏZ. Yan effekt.
    */

    static String fetchData() {
        return "raw_data";
    }
}
```

### Xəta Yayılması

```java
CompletableFuture<String> pipeline = CompletableFuture
    .supplyAsync(() -> step1())      // Xəta atır
    .thenApply(s -> step2(s))        // ← Atlanır (xəta var)
    .thenApply(s -> step3(s))        // ← Atlanır (xəta var)
    .exceptionally(ex -> {
        // step1()-in xətası buraya çatır
        return "Recovery";
    });

// Xəta zəncir boyunca yayılır, ilk exceptionally/handle-da tutulur
```

---

## Praktik Nümunə

### E-ticarət: Paralel Məlumat Yükləməsi

```java
import java.util.concurrent.*;
import java.util.stream.*;

public class EcommerceExample {
    private static final ExecutorService executor = Executors.newFixedThreadPool(10);

    record User(int id, String name) {}
    record Order(int userId, String product) {}
    record Recommendation(String item) {}

    public static void main(String[] args) throws Exception {
        int userId = 42;

        // Üç müstəqil sorğu paralel işləyir
        CompletableFuture<User> userFuture =
            CompletableFuture.supplyAsync(() -> fetchUser(userId), executor);

        CompletableFuture<List<Order>> ordersFuture =
            CompletableFuture.supplyAsync(() -> fetchOrders(userId), executor);

        CompletableFuture<List<Recommendation>> recsFuture =
            CompletableFuture.supplyAsync(() -> fetchRecommendations(userId), executor);

        // Hamısı bitdikdə səhifəni render et
        CompletableFuture<String> pageFuture = CompletableFuture
            .allOf(userFuture, ordersFuture, recsFuture)
            .thenApply(v -> {
                User user = userFuture.join();
                List<Order> orders = ordersFuture.join();
                List<Recommendation> recs = recsFuture.join();
                return renderPage(user, orders, recs);
            })
            .exceptionally(ex -> {
                System.err.println("Səhifə yüklənərkən xəta: " + ex.getMessage());
                return "<html>Xəta baş verdi</html>";
            });

        String page = pageFuture.get(5, TimeUnit.SECONDS);
        System.out.println(page);

        executor.shutdown();
    }

    static User fetchUser(int id) {
        sleep(500); // DB sorğusu
        return new User(id, "Orkhan");
    }

    static List<Order> fetchOrders(int userId) {
        sleep(800); // DB sorğusu
        return List.of(new Order(userId, "Laptop"), new Order(userId, "Siçan"));
    }

    static List<Recommendation> fetchRecommendations(int userId) {
        sleep(600); // ML modeli sorğusu
        return List.of(new Recommendation("Monitor"), new Recommendation("Klaviatura"));
    }

    static String renderPage(User user, List<Order> orders, List<Recommendation> recs) {
        return String.format("""
            <html>
              <h1>Xoş gəldiniz, %s!</h1>
              <h2>Sifarişlər: %s</h2>
              <h2>Tövsiyələr: %s</h2>
            </html>""",
            user.name(),
            orders.stream().map(Order::product).collect(Collectors.joining(", ")),
            recs.stream().map(Recommendation::item).collect(Collectors.joining(", "))
        );
    }

    static void sleep(long ms) {
        try { Thread.sleep(ms); } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
    }
}
```

---

## İntervyu Sualları

**S: `Future` ilə `CompletableFuture` arasındakı əsas fərqlər?**
C: Future — blok edici `get()`, callback yoxdur, zəncirləmə yoxdur, manual tamamlama yoxdur. CompletableFuture — callback zənciri (thenApply/thenCompose), birləşdirmə (thenCombine/allOf/anyOf), xəta idarəsi (exceptionally/handle), `complete()` ilə manual tamamlama.

**S: `thenApply` vs `thenCompose` fərqi?**
C: `thenApply` — `Function<T, U>` — adi dəyər qaytarır. `thenCompose` — `Function<T, CompletableFuture<U>>` — asinxron nəticə qaytarır, iç-içəliyi açır. Stream-də `map` vs `flatMap` kimidir.

**S: `exceptionally` vs `handle` fərqi?**
C: `exceptionally` — yalnız exception olarsa çağırılır. `handle` — həm uğur, həm xəta halında çağırılır (BiFunction<T, Throwable, U>).

**S: `thenApply` vs `thenApplyAsync` nə vaxt istifadə et?**
C: `thenApply` — əvvəlki thread-də, sürətli əməliyyatlar üçün. `thenApplyAsync` — yeni thread-də, uzun müddətli/IO əməliyyatlar üçün ki, əvvəlki thread-i bloklama.

**S: `CompletableFuture.join()` vs `get()` fərqi?**
C: `get()` — checked exception atır (InterruptedException, ExecutionException). `join()` — unchecked CompletionException atır. Stream içindəki lambda-larda `join()` rahatdır.

**S: `allOf` nəticə necə toplanır?**
C: `allOf(f1, f2, f3).thenApply(v -> Stream.of(f1, f2, f3).map(CompletableFuture::join).collect(toList()))` — çünki `allOf` `Void` qaytarır.

**S: Default executor hansıdır və niyə problem yarada bilər?**
C: `ForkJoinPool.commonPool()`. Problem: Bu pool JVM-də paylaşılır. Blok edici IO tapşırıqları bütün poolu doldura bilər, digər CompletableFuture işlərini bloklayar. Həmişə xüsusi executor ver.
