# Reactive Programming (Lead ⭐⭐⭐⭐)

## İcmal
Reactive programming — data axınlarını (stream) asynchronous, non-blocking şəkildə emal edən proqramlaşdırma paradiqmasıdır. "Data gələndə reaksiya ver" — push-based model. Observer pattern + Iterator pattern + async = Reactive. Java Reactor/RxJava, Go channel pipeline-ları, JavaScript RxJS, PHP ReactPHP — hamısı bu paradiqmanın implementasiyasıdır. Lead interview-larda Reactive Streams spesifikasiyası, backpressure, operator zənciri soruşulur.

## Niyə Vacibdir
Microservice arxitekturasında event-driven sistemlər, real-time data processing, high-throughput API-lar üçün reactive model vacibdir. İnterviewer bu sualla sizin imperative vs reactive fərqini, backpressure mexanizmini, cold vs hot publisher-i, Java Reactor (`Mono`/`Flux`), WebFlux, və real production ssenarini bildiyinizi yoxlayır. Spring WebFlux — reactive backend-in Java standartıdır.

## Əsas Anlayışlar

- **Reactive Manifesto:** Responsive, Resilient, Elastic, Message-driven — 4 prinsip
- **Observable / Publisher:** Data mənbəyi — subscriber-ə event göndərir
- **Observer / Subscriber:** Data alıcısı — `onNext`, `onError`, `onComplete` metodları
- **Reactive Streams Spec:** `Publisher`, `Subscriber`, `Subscription`, `Processor` — Java standartı
- **Cold Publisher:** Subscriber bağlandıqda stream başlayır — hər subscriber öz kopyasını alır (HTTP call, DB query)
- **Hot Publisher:** Stream həmişə axır — subscriber sonradan bağlanarsa əvvəlkiləri qaçırır (stock ticker, WebSocket)
- **Backpressure:** Subscriber "mən N element ala bilirəm" deyir — publisher sürəti uyğunlaşdırır
- **`Flux<T>` (Project Reactor):** 0-N element axını; `Mono<T>` — 0-1 element
- **Operator:** `map`, `filter`, `flatMap`, `zip`, `merge`, `buffer`, `window` — stream transformasiyaları
- **`flatMap` vs `concatMap`:** flatMap paralel — sıra qarantiyası yoxdur; concatMap ardıcıl — sıra qorunur
- **Scheduler:** `subscribeOn` — subscription hansı thread-də; `publishOn` — downstream hansı thread-də
- **`subscribeOn` vs `publishOn`:** subscribeOn mənbəni təyin edir; publishOn pipeline-ın qalan hissəsini
- **Error Handling:** `onErrorReturn`, `onErrorResume`, `retry`, `retryWhen` — reactive error recovery
- **`zip` / `combineLatest`:** İki stream-i birləşdirmək — zip N-ci element N-ci ilə; combineLatest ən son
- **WebFlux (Spring):** Reactive HTTP layer — Netty üzərindəki non-blocking server
- **R2DBC:** Reactive relational DB driver — JDBC-nin blocking probleminini həll edir
- **`StepVerifier` (Reactor Test):** Reactive stream-ləri test etmək üçün

## Praktik Baxış

**Interview-da yanaşma:**
- "Niyə reactive?" — I/O-intensive ssenari, az thread ilə çox concurrent request
- Backpressure olmayan reactive sistemin nə etdiyini izah edin — downstream overflow
- `flatMap` vs `concatMap` — bunu bilmək sizi fərqləndirər

**Follow-up suallar:**
- "Cold vs hot publisher fərqi?" — Cold: hər subscriber fresh; Hot: shared stream
- "WebFlux nə vaxt Spring MVC-dən üstündür?" — I/O-intensive, streaming data; compute-bound-da heç bir fayda yoxdur
- "Reactive stream-də blocking kod nə edir?" — Bütün pipelini bloklar — worker thread-ə keçir

**Ümumi səhvlər:**
- Reactive = async düşünmək — reactive backpressure əlavə edir, async bunu vermir
- Blocking kod `Mono.fromCallable` içinə qoymamaq — thread pool-u exhaust edir
- `flatMap` və `concatMap` fərqini bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Backpressure mexanizmini `Subscription.request(n)` ilə izah etmək
- `subscribeOn` vs `publishOn` fərqini düzgün izah etmək
- "WebFlux hər yerdə istifadə olunmamalıdır" demək — complexity cost var

## Nümunələr

### Tipik Interview Sualı
"Reactive programming nədir? Backpressure niyə vacibdir? Java Reactor-da Flux ilə sadə pipeline göstərin."

### Güclü Cavab
Reactive programming — data stream-lərini asynchronous, non-blocking emal edən paradiqmadır. Klassik pull model: "data ver" → "al"; reactive push model: "data gəldikdə mənə xəbər ver." Backpressure reactive-in kritik fərqidir: subscriber publisher-ə "mən N element emal edə bilirəm" deyir — publisher sürəti uyğunlaşdırır. Backpressure olmayan sistemdə subscriber overflow olur, ya memory tükənir, ya da data itir. Java Reactor-da `Flux` 0-N element axınıdır: `Flux.fromIterable(list).map(transform).filter(pred).flatMap(asyncOp).subscribe(onNext, onError)`. WebFlux bu üzərindədir — Netty event loop + Reactor pipeline. Amma diqqət: reactive kod mürəkkəbdir, debugging çətindir. I/O-intensive ssenarisi olmayan yerdə Spring MVC daha sadədir.

### Kod Nümunəsi
```java
// Java Project Reactor: Flux + Mono
import reactor.core.publisher.Flux;
import reactor.core.publisher.Mono;
import reactor.core.scheduler.Schedulers;

import java.time.Duration;
import java.util.List;

public class ReactorDemo {

    public static void main(String[] args) throws InterruptedException {

        // === FLUX: 0-N element ===
        Flux<Integer> numbers = Flux.range(1, 10)
            .map(n -> n * 2)                    // 2, 4, 6, ..., 20
            .filter(n -> n % 4 == 0)            // 4, 8, 12, 16, 20
            .doOnNext(n -> System.out.println("Processed: " + n));

        numbers.subscribe(
            value -> System.out.println("Got: " + value),
            error -> System.err.println("Error: " + error),
            () -> System.out.println("Completed!")
        );

        // === MONO: 0-1 element ===
        Mono<String> userMono = Mono.just("userId-123")
            .flatMap(id -> fetchUserFromDB(id))        // Async DB call
            .map(user -> user.toUpperCase())
            .onErrorReturn("UNKNOWN_USER");             // Error handling

        userMono.subscribe(System.out::println);

        // === flatMap: PARALEL, sırasız ===
        Flux.range(1, 5)
            .flatMap(n -> Mono.just(n)
                .delayElement(Duration.ofMillis((long) (Math.random() * 100)))
                .map(val -> "task-" + val))
            .subscribe(System.out::println);
        // Çıxış: sırasız (task-3, task-1, task-5, ...)

        Thread.sleep(500);

        // === concatMap: ARDIZASlL, sıralı ===
        Flux.range(1, 5)
            .concatMap(n -> Mono.just(n)
                .delayElement(Duration.ofMillis(10))
                .map(val -> "ordered-" + val))
            .subscribe(System.out::println);
        // Çıxış: ordered-1, ordered-2, ..., ordered-5 (sıralı)

        Thread.sleep(200);
    }

    // === subscribeOn vs publishOn ===
    static void schedulerDemo() {
        Flux.range(1, 5)
            // subscribeOn: mənbə (source) hansı thread-də başlayır
            .subscribeOn(Schedulers.boundedElastic()) // DB/I/O thread pool
            .map(n -> {
                System.out.println("map1: " + Thread.currentThread().getName());
                return n * 2;
            })
            // publishOn: bundan sonraki operator-lar başqa thread-də
            .publishOn(Schedulers.parallel()) // CPU thread pool
            .map(n -> {
                System.out.println("map2: " + Thread.currentThread().getName());
                return n + 1;
            })
            .subscribe(n -> System.out.println("Result: " + n));
    }

    // === Blocking kod reactive pipeline-da ===
    static Mono<String> correctBlockingCall(String id) {
        // YANLIŞ: Birbaşa blocking
        // String result = jdbcTemplate.queryForObject(...); // Event loop-u bloklar!

        // DÜZGÜN: boundedElastic thread pool-a keçir
        return Mono.fromCallable(() -> {
            return "DB result for " + id; // Blocking I/O burada
        }).subscribeOn(Schedulers.boundedElastic());
    }

    // === Backpressure ===
    static void backpressureDemo() {
        Flux.range(1, 1000)
            .onBackpressureBuffer(100)  // 100 element buffer; dolunca drop/error
            .subscribe(
                value -> {
                    try { Thread.sleep(10); } catch (InterruptedException e) {} // Yavaş consumer
                    System.out.println("Consumed: " + value);
                },
                error -> System.err.println("Overflow! " + error)
            );
    }

    // === zip: iki stream-i birləşdir ===
    static void zipDemo() {
        Mono<String> user  = fetchUserFromDB("123");
        Mono<String> order = fetchOrderFromDB("456");

        Mono.zip(user, order)
            .map(tuple -> tuple.getT1() + " + " + tuple.getT2())
            .subscribe(System.out::println);
        // Hər ikisi paralel icra edilir; hər ikisi tamamlandıqda birləşir
    }

    // Utility methods
    static Mono<String> fetchUserFromDB(String id) {
        return Mono.just("User:" + id).delayElement(Duration.ofMillis(50));
    }

    static Mono<String> fetchOrderFromDB(String id) {
        return Mono.just("Order:" + id).delayElement(Duration.ofMillis(30));
    }
}
```

```java
// Spring WebFlux: Reactive REST API
import org.springframework.web.bind.annotation.*;
import reactor.core.publisher.Flux;
import reactor.core.publisher.Mono;

@RestController
@RequestMapping("/api")
public class ReactiveController {

    private final UserRepository userRepository; // R2DBC — reactive DB driver
    private final OrderService orderService;

    // Mono — tək resurs
    @GetMapping("/users/{id}")
    public Mono<User> getUser(@PathVariable String id) {
        return userRepository.findById(id)
            .switchIfEmpty(Mono.error(new NotFoundException("User not found: " + id)));
    }

    // Flux — koleksiya + streaming
    @GetMapping(value = "/users/stream", produces = "text/event-stream")
    public Flux<User> streamUsers() {
        return userRepository.findAll()
            .delayElements(Duration.ofMillis(100)); // Server-Sent Events
    }

    // Paralel calls — zip
    @GetMapping("/dashboard/{userId}")
    public Mono<Dashboard> getDashboard(@PathVariable String userId) {
        Mono<User> userMono   = userRepository.findById(userId);
        Mono<List<Order>> ordersMono = orderService.findByUserId(userId);

        return Mono.zip(userMono, ordersMono)
            .map(tuple -> new Dashboard(tuple.getT1(), tuple.getT2()));
    }

    // Error handling
    @ExceptionHandler(NotFoundException.class)
    public Mono<ResponseEntity<String>> handleNotFound(NotFoundException e) {
        return Mono.just(ResponseEntity.notFound().build());
    }
}
```

```go
// Go: Channel pipeline — reactive-in idiomatic Go forması
package main

import (
    "fmt"
    "sync"
)

// Generator (Publisher)
func generate(nums ...int) <-chan int {
    out := make(chan int)
    go func() {
        defer close(out)
        for _, n := range nums {
            out <- n
        }
    }()
    return out
}

// Map operator
func mapChan(in <-chan int, f func(int) int) <-chan int {
    out := make(chan int)
    go func() {
        defer close(out)
        for v := range in {
            out <- f(v)
        }
    }()
    return out
}

// Filter operator
func filterChan(in <-chan int, predicate func(int) bool) <-chan int {
    out := make(chan int)
    go func() {
        defer close(out)
        for v := range in {
            if predicate(v) {
                out <- v
            }
        }
    }()
    return out
}

// Merge (fan-in) — backpressure channel buffer ilə
func merge(channels ...<-chan int) <-chan int {
    out := make(chan int, 10) // Buffer = backpressure nöqtəsi
    var wg sync.WaitGroup
    for _, ch := range channels {
        wg.Add(1)
        go func(c <-chan int) {
            defer wg.Done()
            for v := range c {
                out <- v
            }
        }(ch)
    }
    go func() {
        wg.Wait()
        close(out)
    }()
    return out
}

func main() {
    // Pipeline: generate → filter(even) → map(*2) → consume
    nums := generate(1, 2, 3, 4, 5, 6, 7, 8, 9, 10)
    evens := filterChan(nums, func(n int) bool { return n%2 == 0 })
    doubled := mapChan(evens, func(n int) int { return n * 2 })

    for result := range doubled {
        fmt.Println(result) // 4, 8, 12, 16, 20
    }
}
```

## Praktik Tapşırıqlar

- Java Reactor-da `flatMap` vs `concatMap` ilə paralel vs ardıcıl fetch benchmark edin
- Spring WebFlux endpoint yazın: R2DBC ilə database-dən user oxuyun, `StepVerifier` ilə test edin
- Backpressure ssenarini simulasiya edin: publisher sürətli, consumer yavaş — `onBackpressureBuffer` ilə müşahidə edin
- Go-da channel pipeline yaradın: CSV-dən oxu → filter → transform → yazma
- `subscribeOn` vs `publishOn` fərqini thread name-ləri print edərək müşahidə edin

## Əlaqəli Mövzular
- `07-event-loop.md` — Reactive runtime-ın əsasıdır
- `08-producer-consumer.md` — Reactive stream = async producer-consumer
- `13-green-threads.md` — Reactor Netty goroutine/fiber üzərindədir
- `06-async-await.md` — Reaktivin imperative forması
