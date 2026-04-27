# Stream Gatherers (Java 22+) (Lead)

## İcmal

`Gatherer` — Stream pipeline-ına **custom stateful intermediate operation** əlavə etmək üçün Java 22+ API-dir (JEP 461 preview → **JEP 473 Java 23 final**). `Collector` terminal operation üçün nə edirirsə, `Gatherer` intermediate üçün onu edir: əvvəlki elementləri yaddaşda saxlaya bilən, short-circuit edə bilən, paralel stream-i dəstəkləyən bir transformasiya qapısı.

---

## Niyə Vacibdir

Stream API-nin ən böyük boşluğu həmişə **stateful intermediate** əməliyyatlardı. `filter`, `map`, `flatMap` state saxlamır. `distinct`, `sorted`, `limit` daxili stateful-dur amma genişləndirilə bilmir. Custom sliding window, batch emit, running total kimi əməliyyatlar üçün:

- Java 22 əvvəl: ya `Spliterator` impl (100+ sətir), ya da stream-i `List`-ə yığıb for-loop ilə işlətmək lazım idi.
- Java 22 sonra: `stream.gather(myGatherer)` — 20-30 sətir, stream semantikası tam qorunur, paralel dəstəklənir.

Produksiya use-case-ləri: event batching (100-lük qruplarda API çağırışı), sliding window analytics (moving average), windowed rate limiting, stateful CSV parse.

---

## Əsas Anlayışlar

### Pipeline-da Yeri

```
stream.filter(...)
      .map(...)
      .gather(myGatherer)   ← intermediate (yeni)
      .map(...)
      .collect(...)
```

`gather()` başqa intermediate metodlar kimi chain-ə girə bilər — bir pipeline-da bir neçə `gather()` mümkündür.

### `Gatherer<T, A, R>` Interface

```
T — input element tipi (upstream-dən gəlir)
A — mutable state tipi (accumulator/buffer)
R — output element tipi (downstream-ə göndərilir)
```

Interface dörd komponentdən ibarətdir:

| Komponent | İmza | Məqsəd |
|-----------|------|--------|
| `initializer` | `Supplier<A>` | Hər pipeline işləmədən öncə mutable state yaradır |
| `integrator` | `(A state, T element, Downstream<R> out) → boolean` | Hər element üçün çağırılır; `false` qaytarmaq stream-i short-circuit edir |
| `combiner` | `BinaryOperator<A>` | Paralel stream-də iki state-i birləşdirir |
| `finisher` | `(A state, Downstream<R> out) → void` | Stream bitdikdə yerdə qalan buffer-ı flush edir |

`initializer()` və `finisher()` optional-dır (default: no-op). `combiner()` olmasa stream **sequential-only** kimi davranır.

### Downstream Interface

`integrator` daxilindən `out.push(R element)` ilə downstream-ə element göndərilir. Bir `T` input üçün sıfır, bir və ya bir neçə `R` output emitlənə bilər — bu `flatMap` semantikasını mümkün edir.

### Built-in `Gatherers` Factory

`java.util.stream.Gatherers` (Java 22+) hazır implementasiyalar verir:

| Metod | Davranış |
|-------|----------|
| `windowFixed(n)` | `n` elementlik `List<T>` pəncərələr emit edir; son pəncərə kiçik ola bilər |
| `windowSliding(n)` | Hər yeni elementdə sürüşən `n` elementlik `List<T>` emit edir |
| `scan(identity, accumulator)` | Running aggregate — hər `accumulate(state, elem)` nəticəsini emitləyir |
| `fold(identity, folder)` | `reduce`-ə bənzər amma bir element emit edir (terminal deyil, intermediate-dir) |
| `mapConcurrent(limit, mapper)` | Virtual thread-lərdə async mapping, max `limit` paralel tapşırıq |

---

## Praktik Baxış

### `windowFixed` vs `windowSliding` fərqi

```
Input:  [1, 2, 3, 4, 5, 6, 7]

windowFixed(3):
  [1,2,3] → [4,5,6] → [7]         // son pəncərə natamam ola bilər

windowSliding(3):
  [1,2,3] → [2,3,4] → [3,4,5] → [4,5,6] → [5,6,7]  // overlap var
```

### `scan` — running state

```
Input:  [1, 2, 3, 4, 5]
scan(0, Integer::sum):
  0+1=1 → 1+2=3 → 3+3=6 → 6+4=10 → 10+5=15
Output: [1, 3, 6, 10, 15]  ← cumulative sum, hər addım emit olunur
```

Reducer-dən fərqi: `reduce` bir nəticə verir, `scan` hər akkumulasiya addımını stream-ə ötürür.

### Trade-offs

| | Gatherer | Custom Spliterator | Collect to List + Loop |
|---|---------|-------------------|----------------------|
| Boilerplate | Az | Çox (100+ sətir) | Minimal |
| Laziness | Var — stream lazy qalır | Var | Yoxdur — hamısı yaddaşa |
| Paralel | `combiner()` varsa | Düzgün impl lazım | Yoxdur |
| Short-circuit | `integrator` false qaytarsa | Var | Yoxdur |
| İstifadə rahatlığı | Yüksək | Aşağı | Yüksək |

### PHP Müqayisəsi

| | PHP | Java Gatherer |
|---|-----|---------------|
| Chunking | `array_chunk($arr, 3)` | `Gatherers.windowFixed(3)` |
| Sliding window | Yoxdur — for-loop | `Gatherers.windowSliding(n)` |
| Running sum | `array_reduce` ilə hər addımı topla | `Gatherers.scan(0, Integer::sum)` |
| Custom stateful transform | for-loop + state dəyişəni | Custom `Gatherer` impl |
| Lazy evaluation | PHP generator (`yield`) | Stream default lazy-dir |
| Parallel | Yoxdur (standard) | `combiner()` ilə paralel dəstəyi |

PHP-nin `array_map`, `array_filter`, `array_chunk` birlikdə eyni işi görür amma hamısı **eager** — hər addımda tam array yaradılır. Java Stream + Gatherer pipeline-ı **lazy**: terminal operation çağırılana qədər heç bir element işlənmir.

### Nə Vaxt Gatherer Lazım Deyil

- Sadə `map/filter/flatMap` kifayət edərsə — Gatherer overkill
- State saxlamaq lazım deyilsə — standart intermediate-lər istifadə et
- Yalnız aggregate lazımdırsa (`sum`, `count`) — `Collector` yetər
- Çox kiçik stream-lər üçün (< 1000 element) — overhead fərq yaratmır, sadə kolleksiya əməliyyatı daha oxunaqlıdır

---

## Nümunələr

### Ümumi Nümunə

Event stream-indən gələn log record-larını 50-lik batch-lərə böl, hər batch üçün bir HTTP POST at. Built-in kolleksiya əməliyyatları bunu tək geçişdə, yaddaşa tam yükləmədən bacarmır. Gatherer isə 50 element toplandıqda `finisher()` ilə son batch-i flush edərək tam pipeline saxlayır.

### Kod Nümunəsi

```java
import java.util.*;
import java.util.function.*;
import java.util.stream.*;

public class GatherersDemo {

    // ── 1. Built-in Gatherers ────────────────────────────────

    static void builtinExamples() {
        var numbers = IntStream.rangeClosed(1, 10).boxed().toList();

        // windowFixed — 3-lük batch-lər
        List<List<Integer>> batches = numbers.stream()
            .gather(Gatherers.windowFixed(3))
            .toList();
        System.out.println("Fixed windows: " + batches);
        // [[1,2,3], [4,5,6], [7,8,9], [10]]

        // windowSliding — sürüşən pəncərə
        List<List<Integer>> sliding = numbers.stream()
            .gather(Gatherers.windowSliding(4))
            .toList();
        System.out.println("Sliding windows: " + sliding);
        // [[1,2,3,4], [2,3,4,5], [3,4,5,6], ...]

        // scan — running sum
        List<Integer> runningSum = numbers.stream()
            .gather(Gatherers.scan(() -> 0, Integer::sum))
            .toList();
        System.out.println("Running sum: " + runningSum);
        // [1, 3, 6, 10, 15, 21, 28, 36, 45, 55]

        // fold — tek nəticə (amma intermediate kimi istifadə olunur)
        Optional<Integer> total = numbers.stream()
            .gather(Gatherers.fold(() -> 0, Integer::sum))
            .findFirst();
        System.out.println("Total: " + total.orElse(0)); // 55

        // mapConcurrent — virtual thread-lərdə parallel map
        List<String> processed = numbers.stream()
            .gather(Gatherers.mapConcurrent(4, n -> {
                // simulate slow IO — 4 thread-ə qədər paralel
                return "processed-" + n;
            }))
            .toList();
        System.out.println("Concurrent: " + processed);
    }

    // ── 2. Custom Gatherer: API Batch Caller ─────────────────
    // Problem: 1000 event var, API max 50 qəbul edir
    // Həll: Gatherers.windowFixed(50) + map ilə batch POST

    record Event(int id, String type, String payload) {}

    @FunctionalInterface
    interface BatchSender<T> {
        void send(List<T> batch);
    }

    static <T> Gatherer<T, List<T>, List<T>> batchGatherer(int batchSize) {
        return Gatherer.of(
            // initializer — boş mutable buffer
            () -> new ArrayList<T>(batchSize),

            // integrator — hər element buffer-a əlavə et
            Gatherer.Integrator.ofGreedy((buffer, element, downstream) -> {
                buffer.add(element);
                if (buffer.size() == batchSize) {
                    // Dolu batch — downstream-ə göndər, buffer-ı təmizlə
                    downstream.push(new ArrayList<>(buffer));
                    buffer.clear();
                }
                return true; // stream davam etsin
            }),

            // combiner — paralel merge (listləri birləşdir)
            (left, right) -> {
                left.addAll(right);
                return left;
            },

            // finisher — qalan elementləri flush et
            (buffer, downstream) -> {
                if (!buffer.isEmpty()) {
                    downstream.push(new ArrayList<>(buffer));
                }
            }
        );
    }

    static void batchApiCallExample() {
        var events = IntStream.rangeClosed(1, 107)
            .mapToObj(i -> new Event(i, "ORDER_PLACED", "payload-" + i))
            .toList();

        int[] batchCount = {0};
        events.stream()
            .gather(batchGatherer(10))
            .forEach(batch -> {
                batchCount[0]++;
                System.out.printf("Batch #%d: %d event (id %d..%d)%n",
                    batchCount[0], batch.size(),
                    batch.getFirst().id(), batch.getLast().id());
                // burada: httpClient.post("/events/batch", batch);
            });
        System.out.println("Cəmi " + batchCount[0] + " batch göndərildi");
        // 107 event → 10 batch (10x10 + 1x7)
    }

    // ── 3. Custom Gatherer: Sliding Moving Average ───────────
    // Time-series data üçün N-period moving average

    static Gatherer<Double, ArrayDeque<Double>, Double> movingAverage(int period) {
        return Gatherer.of(
            // initializer — sliding window buffer
            () -> new ArrayDeque<Double>(period),

            // integrator — window-a əlavə et, köhnəni sil, average emit et
            Gatherer.Integrator.ofGreedy((window, value, downstream) -> {
                window.addLast(value);
                if (window.size() > period) {
                    window.removeFirst();
                }
                if (window.size() == period) {
                    // Tam pəncərə dolduqda average hesabla
                    double avg = window.stream()
                        .mapToDouble(Double::doubleValue)
                        .average()
                        .orElse(0.0);
                    downstream.push(avg);
                }
                return true;
            })
            // combiner yoxdur → sequential-only (time-series üçün uyğundur)
        );
    }

    static void movingAverageExample() {
        var prices = List.of(10.0, 11.5, 12.0, 10.5, 13.0,
                             14.5, 13.5, 15.0, 14.0, 16.0);

        System.out.println("5-period moving average:");
        prices.stream()
            .gather(movingAverage(5))
            .forEach(avg -> System.out.printf("  %.2f%n", avg));
        // İlk 4 element skip olunur (window dolmadı)
        // 5-ci elementdən etibarən: 11.40, 12.30, 12.70, ...
    }

    // ── 4. Custom Gatherer: Stateful Deduplication ───────────
    // Yalnız consecutive duplicate-ları sil (Set-dən fərqli!)
    // [1,1,2,2,3,1,1] → [1,2,3,1]

    static <T> Gatherer<T, T[], T> distinctConsecutive() {
        return Gatherer.of(
            // initializer — son görülən element (null = hələ yoxdur)
            () -> {
                @SuppressWarnings("unchecked")
                T[] last = (T[]) new Object[]{null};
                return last;
            },

            // integrator
            Gatherer.Integrator.ofGreedy((last, element, downstream) -> {
                if (!Objects.equals(last[0], element)) {
                    last[0] = element;
                    downstream.push(element);
                }
                return true;
            })
        );
    }

    static void deduplicationExample() {
        var stream = List.of(1, 1, 2, 2, 2, 3, 1, 1, 4, 4);
        var result = stream.stream()
            .gather(distinctConsecutive())
            .toList();
        System.out.println("Consecutive dedup: " + result);
        // [1, 2, 3, 1, 4]
    }

    // ── 5. Custom Gatherer: Take Until Predicate ─────────────
    // Short-circuit: şərt ödənənə qədər element al (həmin element daxil)

    static <T> Gatherer<T, Void, T> takeUntil(Predicate<T> stopCondition) {
        return Gatherer.of(
            // initializer yoxdur (state yoxdur)
            Gatherer.defaultInitializer(),

            // integrator — element push et, şərt ödənəndə false qaytarır
            (_, element, downstream) -> {
                downstream.push(element);
                return !stopCondition.test(element); // false → short-circuit
            }
        );
    }

    static void takeUntilExample() {
        var result = IntStream.iterate(1, n -> n + 1)
            .boxed()
            .gather(takeUntil(n -> n * n > 50)) // n² > 50 olana qədər
            .toList();
        System.out.println("takeUntil(n²>50): " + result);
        // [1, 2, 3, 4, 5, 6, 7, 8] — 8²=64 > 50, 8 daxildir
    }

    // ── 6. Gatherers vs Collectors fərqi ────────────────────
    static void gatherersVsCollectors() {
        var numbers = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

        // Collector — terminal, bir nəticə
        int sum = numbers.stream().collect(
            Collectors.summingInt(Integer::intValue)
        );
        System.out.println("Collector sum: " + sum); // 55

        // Gatherer + Collector — intermediate + terminal
        List<Integer> runningSum = numbers.stream()
            .gather(Gatherers.scan(() -> 0, Integer::sum))  // intermediate
            .collect(Collectors.toList());                  // terminal
        System.out.println("Gatherer running sum: " + runningSum);
        // [1, 3, 6, 10, 15, 21, 28, 36, 45, 55]

        // windowFixed + flatMap — batch processing nəticəsini flatten et
        int batchSum = numbers.stream()
            .gather(Gatherers.windowFixed(3))    // [[1,2,3],[4,5,6],[7,8,9],[10]]
            .map(batch -> batch.stream().mapToInt(Integer::intValue).sum())
            .mapToInt(Integer::intValue)
            .sum();
        System.out.println("Batch sum: " + batchSum); // 55
    }

    public static void main(String[] args) {
        System.out.println("=== Built-in Gatherers ===");
        builtinExamples();

        System.out.println("\n=== Batch API Call ===");
        batchApiCallExample();

        System.out.println("\n=== Moving Average ===");
        movingAverageExample();

        System.out.println("\n=== Consecutive Dedup ===");
        deduplicationExample();

        System.out.println("\n=== Take Until ===");
        takeUntilExample();

        System.out.println("\n=== Gatherers vs Collectors ===");
        gatherersVsCollectors();
    }
}
```

---

## Praktik Tapşırıqlar

**1. Pagination gatherer**
`pageGatherer(int pageSize)` yaz: `windowFixed`-ə bənzər amma hər batch-ə page number əlavə et. Output tipi `Page<T>` record olsun: `record Page<T>(int number, List<T> items)`.

**2. Rate-limited stream**
`rateLimitGatherer(int maxPerSecond)` yaz: hər saniyəyə düşən element sayını `maxPerSecond` ilə məhdudlaşdır — artıq elementlər atılsın. `Gatherer.Integrator.ofGreedy` istifadə et, state-də son saniyənin sayacını saxla.

**3. Moving average benchmark**
Java 22+ JMH ilə `movingAverage(10)` gatherer-ini vs el-ilə-ArrayList sliding window implementasiyasını 100k element üzərində qarşılaşdır. `@BenchmarkMode(Mode.AverageTime)` istifadə et.

**4. Parallel batch gatherer**
`batchGatherer`-in `combiner`-ini düzgün implement et ki parallel stream-lərdə işləsin. `Stream.of(...).parallel().gather(batchGatherer(5))` ilə test et, nəticənin eyni olduğunu yoxla.

**5. CSV stateful parser**
Multi-line quoted CSV field-ləri parse edən gatherer yaz: `"field1","line1\nline2","field3"` kimi input-da `"` içindəki newline-ları birləşdir. State: `inQuotes` boolean + `currentField` buffer.

---

## Əlaqəli Mövzular

- [48-streams-basics.md](48-streams-basics.md) — Stream pipeline əsasları, lazy evaluation
- [51-streams-collectors.md](51-streams-collectors.md) — Collector API — Gatherer ilə fərqini anlamaq üçün
- [52-streams-parallel.md](52-streams-parallel.md) — Parallel stream — `combiner()` niyə lazım olduğunu anlamaq üçün
- [50-streams-terminal-ops.md](50-streams-terminal-ops.md) — `reduce`, `collect` — Gatherer-in tamamladığı terminal ops
- [85-unnamed-variables-unnamed-classes.md](85-unnamed-variables-unnamed-classes.md) — Eyni era features: Java 22-23 unnamed patterns
- [98-structured-concurrency-scoped-values.md](98-structured-concurrency-scoped-values.md) — `mapConcurrent` + virtual thread konteksti
