# Producer-Consumer Pattern (Senior ⭐⭐⭐)

## İcmal
Producer-Consumer — data istehsal edən (producer) və istehlak edən (consumer) component-ləri ayrı thread-lərə böləərək aralarına buffer (queue) qoyan klassik concurrency pattern-idir. Bu pattern system decoupling-in, backpressure-un, və async iş növbəsinin əsasında dayanır. Senior interview-larda həm nəzəriyyə, həm Java BlockingQueue, həm də real message broker dizaynı ilə soruşulur.

## Niyə Vacibdir
Message queue-lar (RabbitMQ, Kafka, Redis Queue) əslində distributed Producer-Consumer implementasiyasıdır. İnterviewer bu sualla sizin thread coordination-ı, bounded vs unbounded queue seçimini, backpressure mexanizmini, və real sistemlərdə bu pattern-in necə tətbiq edildiyini bildiyinizi yoxlayır. Laravel Queue, Horizon, Job batching — hamısı bu modelin üzərindədir.

## Əsas Anlayışlar

- **Producer:** Data yaradan thread — işi queue-ya əlavə edir, consumer-ı bilmir
- **Consumer:** Data işləyən thread — queue-dan götürür, producer-ı bilmir
- **Buffer (Queue):** Producer-Consumer arasındakı sinxronizasiya nöqtəsi
- **Decoupling:** Producer sürəti ≠ Consumer sürəti — queue fərqi absorb edir
- **Bounded Queue:** Maksimum həcmi var — dolduqda producer block olur (backpressure)
- **Unbounded Queue:** Həcm limiti yoxdur — memory leak riski; production-da tərdinlər
- **Backpressure:** Consumer yavaşsa, producer-ı yavaşlatmaq — overflow qarşısını alır
- **Blocking Put/Take:** `queue.put()` — dolu queue-da bloklanır; `queue.take()` — boş queue-da bloklanır
- **BlockingQueue (Java):** `ArrayBlockingQueue`, `LinkedBlockingQueue` — thread-safe, blocking semantics
- **wait/notify:** Low-level Java sinxronizasiyası — bounded queue-u manually implement etmək üçün
- **Condition Variable:** `Lock` + `Condition` — wait/notify-ın müasir alternativdir
- **Multi-Producer / Multi-Consumer:** Bir neçə producer + bir neçə consumer — real sistemlər
- **Poison Pill:** Consumer-ı dayandırmaq üçün xüsusi marker mesaj göndərmək
- **Work Stealing:** Consumer boşsa, digər consumer-ın queue-sundan iş oğurlayır — `ForkJoinPool`
- **Fan-out:** Bir producer → bir neçə consumer növbə — Pub/Sub əsası
- **Dead Letter Queue (DLQ):** Uğursuz işlənmiş mesajlar üçün ayrı queue
- **Laravel Queue:** `dispatch(new Job())` — producer; `php artisan queue:work` — consumer

## Praktik Baxış

**Interview-da yanaşma:**
- Əvvəlcə thread coordination problemini izah edin: "producer sürəti > consumer → memory dolar"
- Bounded queue + backpressure dedikdə niyə bounded? — System stability
- Java BlockingQueue-nun internal mexanizmini soruşsalar: `ReentrantLock` + iki `Condition`

**Follow-up suallar:**
- "Unbounded queue-nun nə problemi var?" — Memory exhaustion, backpressure yoxdur
- "Poison pill pattern nədir?" — Consumer-ı graceful shutdown etmək
- "Kafka producer-consumer ilə bu pattern-in fərqi?" — Persistence, replay, consumer group

**Ümumi səhvlər:**
- Bounded queue-nu bilməmək — "queue dolsa nə olur?" sualına cavab verə bilməmək
- Single producer/consumer düşünmək — real sistemdə concurrent producer-lar var
- Graceful shutdown-u (poison pill) qeyd etməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Backpressure mexanizmini izah etmək — producer-ı throttle etmək
- `wait/notify` low-level implementasiyanı göstərib sonra `BlockingQueue`-nun bunu abstrakt etdiyini demək
- Laravel Horizon worker concurrency ilə real dünya bağlantısı qurmaq

## Nümunələr

### Tipik Interview Sualı
"Producer-Consumer pattern nədir? Bounded buffer ilə necə implementasiya edərdiniz? Backpressure necə həll edərsiniz?"

### Güclü Cavab
Producer-Consumer — işi yaradanı işləyəndən ayıran, aralarına thread-safe queue qoyan pattern-dir. Əsas problem: producer sürəti consumer-dan yüksək olduqda ya memory dolar, ya da data itkisi olur. Həll: bounded queue. Producer queue dolu olanda block olur — bu özü backpressure mexanizmidir: consumer yavaşdıqda producer avtomatik yavaşlayır, system stable qalır. Java-da `ArrayBlockingQueue(capacity)` bu semantikanı hazır verir. Multi-producer/multi-consumer ssenarilərdə `BlockingQueue` thread-safe-dir. Graceful shutdown üçün consumer-a "poison pill" göndərilir — xüsusi marker obyekt. Real sistemdə bu Kafka, RabbitMQ, Laravel Queue ilə implementasiya olunur; distributed backpressure daha mürəkkəbdir.

### Kod Nümunəsi
```java
// Java: BlockingQueue ilə Producer-Consumer
import java.util.concurrent.*;

// İş vahidi
record Task(int id, String data) {}

// Producer
class Producer implements Runnable {
    private final BlockingQueue<Task> queue;
    private final int count;

    Producer(BlockingQueue<Task> queue, int count) {
        this.queue = queue;
        this.count = count;
    }

    @Override
    public void run() {
        try {
            for (int i = 0; i < count; i++) {
                Task task = new Task(i, "data-" + i);
                queue.put(task);  // Queue DOLU olarsa — BLOCK OLUR (backpressure)
                System.out.println("Produced: " + task.id());
            }
            // Poison pill — consumer-a "işin bitdi" siqnalı
            queue.put(new Task(-1, "STOP"));
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}

// Consumer
class Consumer implements Runnable {
    private final BlockingQueue<Task> queue;

    Consumer(BlockingQueue<Task> queue) {
        this.queue = queue;
    }

    @Override
    public void run() {
        try {
            while (true) {
                Task task = queue.take();  // Queue BOŞ olarsa — BLOCK OLUR
                if (task.id() == -1) break;  // Poison pill — çıx
                process(task);
            }
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }

    private void process(Task task) throws InterruptedException {
        Thread.sleep(10);  // İş simulyasiyası
        System.out.println("Consumed: " + task.id());
    }
}

// Main
public class ProducerConsumerDemo {
    public static void main(String[] args) throws InterruptedException {
        // Bounded queue — maksimum 100 element
        BlockingQueue<Task> queue = new ArrayBlockingQueue<>(100);

        Thread producer = new Thread(new Producer(queue, 1000));
        Thread consumer = new Thread(new Consumer(queue));

        consumer.start();
        producer.start();

        producer.join();
        consumer.join();

        System.out.println("Done. Queue size: " + queue.size());
    }
}
```

```java
// Multi-Producer / Multi-Consumer + ThreadPool
public class MultiPCDemo {
    private static final int QUEUE_CAPACITY = 500;
    private static final int NUM_PRODUCERS = 4;
    private static final int NUM_CONSUMERS = 8;
    private static final Task POISON_PILL = new Task(-1, "STOP");

    public static void main(String[] args) throws InterruptedException {
        BlockingQueue<Task> queue = new ArrayBlockingQueue<>(QUEUE_CAPACITY);
        ExecutorService producers = Executors.newFixedThreadPool(NUM_PRODUCERS);
        ExecutorService consumers = Executors.newFixedThreadPool(NUM_CONSUMERS);

        // NUM_CONSUMERS qədər poison pill — hər consumer bir dənə alacaq
        // Bu multi-consumer graceful shutdown-un əsası
        Runnable producerTask = () -> {
            for (int i = 0; i < 250; i++) {
                try {
                    queue.put(new Task(i, Thread.currentThread().getName()));
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            }
        };

        Runnable consumerTask = () -> {
            try {
                while (true) {
                    Task t = queue.take();
                    if (t == POISON_PILL) {
                        queue.put(POISON_PILL); // Digər consumer-lar üçün geri qoy
                        break;
                    }
                    process(t);
                }
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        };

        for (int i = 0; i < NUM_PRODUCERS; i++) producers.submit(producerTask);
        for (int i = 0; i < NUM_CONSUMERS; i++) consumers.submit(consumerTask);

        producers.shutdown();
        producers.awaitTermination(30, TimeUnit.SECONDS);

        queue.put(POISON_PILL); // İlk poison pill — cascades

        consumers.shutdown();
        consumers.awaitTermination(30, TimeUnit.SECONDS);
    }

    static void process(Task t) { /* ... */ }
}
```

```go
// Go: Channel ilə Producer-Consumer (idiomatic)
package main

import (
    "fmt"
    "sync"
)

func producer(jobs chan<- int, count int, wg *sync.WaitGroup) {
    defer wg.Done()
    for i := 0; i < count; i++ {
        jobs <- i // Channel DOLU olarsa — goroutine block olur (backpressure)
        fmt.Printf("Produced: %d\n", i)
    }
}

func consumer(id int, jobs <-chan int, wg *sync.WaitGroup) {
    defer wg.Done()
    for job := range jobs { // Channel BAĞLANANDA döngü bitir
        fmt.Printf("Consumer %d processing job %d\n", id, job)
    }
}

func main() {
    jobs := make(chan int, 100) // Buffered channel = bounded queue
    var producerWg, consumerWg sync.WaitGroup

    // 3 producer
    for i := 0; i < 3; i++ {
        producerWg.Add(1)
        go producer(jobs, 100, &producerWg)
    }

    // 5 consumer
    for i := 0; i < 5; i++ {
        consumerWg.Add(1)
        go consumer(i, jobs, &consumerWg)
    }

    // Bütün producer-lar bitdikdə channel-ı bağla
    go func() {
        producerWg.Wait()
        close(jobs) // Consumer-lar range ilə avtomatik çıxacaq
    }()

    consumerWg.Wait()
    fmt.Println("All done")
}
```

```php
// PHP / Laravel: Queue ilə Producer-Consumer
// Producer (Controller / Service)
use App\Jobs\ProcessOrderJob;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $order = Order::create($request->validated());

        // Queue-ya göndər (async producer)
        // Bounded: Horizon-da queue size limitini set edə bilərsiniz
        dispatch(new ProcessOrderJob($order->id));

        return response()->json(['status' => 'queued'], 202);
    }
}

// Consumer (Job)
class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public readonly int $orderId) {}

    public function handle(OrderService $service): void
    {
        $service->process($this->orderId);
    }

    public function failed(\Throwable $e): void
    {
        // Dead Letter Queue əvəzinə — failed_jobs table
        Log::error("Order {$this->orderId} failed: {$e->getMessage()}");
    }
}

// Backpressure: Horizon config
// 'queue:default' => ['processes' => 8, 'balance' => 'auto', 'maxProcesses' => 20]
// 'queue:critical' → daha çox worker, normal queue-lar gözləyir
```

## Praktik Tapşırıqlar

- Java `ArrayBlockingQueue(10)` ilə producer sürəti > consumer ssenariisini reproduce edin — queue-nun producer-ı necə bloklandığını görün
- Go-da unbounded (`make(chan int)` vs `make(chan int, 100)`) channel fərqini ölçün
- Poison pill pattern-i multi-consumer ssenariidə test edin
- Laravel Horizon dashboard-da queue depth-i izləyin, worker count artırdıqda dəyişimi görün
- Dead Letter Queue ssenariisini simulasiya edin: Job 3 dəfə fail edir, `failed_jobs` cədvəlinə düşür

## Əlaqəli Mövzular
- `03-mutex-semaphore.md` — Semaphore bounded queue-nun əsasıdır
- `05-thread-pools.md` — Thread pool + queue kombinasiyası
- `09-read-write-lock.md` — Queue-ya concurrent oxuma/yazma
- `11-memory-models.md` — Queue visibility qarantiyaları
- `14-reactive-programming.md` — Reactive stream = async producer-consumer
