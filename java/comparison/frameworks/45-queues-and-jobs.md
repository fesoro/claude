# Queue ve Jobs (Noveeler ve Tapshiriqlar)

> **Seviyye:** Intermediate ⭐⭐

## Giris

Muasir tetbiqlerde bezi emeliyyatlar uzun cekir -- email gondermek, video emal etmek, hesabat yaratmaq ve s. Bu emeliyyatlari istifadecinin gozlemesine ehtiyac olmadan arxa fonda icra etmek ucun queue (novbe) sistemi istifade olunur.

Spring ekosisteminde novbe ishleri ucun RabbitMQ, Apache Kafka kimi xarici message broker-lerden istifade olunur, hemcinin `@Async` ve `CompletableFuture` ile asinxron emeliyyatlar aparilir. Laravel ise queue sistemini framework daxilinde teqdim edir -- queue driver-leri, job-lar, failed job-lar, batching ve chaining kimi imkanlar var.

## Spring-de istifadesi

### @Async ile asinxron emeliyyatlar

En sade yanaşma -- metodu asinxron etmek:

```java
@Configuration
@EnableAsync
public class AsyncConfig {

    @Bean
    public Executor taskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(5);
        executor.setMaxPoolSize(10);
        executor.setQueueCapacity(25);
        executor.setThreadNamePrefix("async-");
        executor.initialize();
        return executor;
    }
}

@Service
public class EmailService {

    @Async
    public void sendWelcomeEmail(String email) {
        // Bu metod ayri thread-de icra olunur
        // Cagiranin gozlemesi lazim deyil
        log.info("Email gonderilir: {}", email);
        mailSender.send(createWelcomeEmail(email));
        log.info("Email gonderildi: {}", email);
    }

    @Async
    public CompletableFuture<Boolean> sendEmailWithResult(String email) {
        // Netice lazim olanda CompletableFuture istifade olunur
        try {
            mailSender.send(createEmail(email));
            return CompletableFuture.completedFuture(true);
        } catch (Exception e) {
            return CompletableFuture.completedFuture(false);
        }
    }
}
```

### CompletableFuture ile murekkeb asinxron emeliyyatlar

```java
@Service
public class OrderService {

    @Autowired
    private PaymentService paymentService;
    @Autowired
    private InventoryService inventoryService;
    @Autowired
    private NotificationService notificationService;

    public OrderResult processOrder(Order order) {
        // Paralel emeliyyatlar
        CompletableFuture<PaymentResult> paymentFuture =
            CompletableFuture.supplyAsync(() ->
                paymentService.charge(order));

        CompletableFuture<Boolean> inventoryFuture =
            CompletableFuture.supplyAsync(() ->
                inventoryService.reserve(order));

        // Her ikisi bitene qeder gozle
        CompletableFuture.allOf(paymentFuture, inventoryFuture).join();

        PaymentResult payment = paymentFuture.join();
        boolean reserved = inventoryFuture.join();

        if (payment.isSuccess() && reserved) {
            // Bildirishi asinxron gonder, gozleme
            CompletableFuture.runAsync(() ->
                notificationService.notifyOrderComplete(order));

            return OrderResult.success(order);
        }

        return OrderResult.failure("Sifaris ugursuz oldu");
    }

    // Zincirleme (chaining)
    public CompletableFuture<OrderResult> processOrderAsync(Order order) {
        return CompletableFuture
            .supplyAsync(() -> paymentService.charge(order))
            .thenApply(paymentResult -> {
                if (!paymentResult.isSuccess()) {
                    throw new PaymentException("Odeme ugursuz");
                }
                return inventoryService.reserve(order);
            })
            .thenApply(reserved -> {
                if (!reserved) {
                    throw new InventoryException("Stok yoxdur");
                }
                return OrderResult.success(order);
            })
            .exceptionally(ex -> {
                log.error("Sifaris xetasi: {}", ex.getMessage());
                return OrderResult.failure(ex.getMessage());
            });
    }
}
```

### RabbitMQ ile message queue

**Dependency:**

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-amqp</artifactId>
</dependency>
```

**Konfiqurasiya:**

```java
@Configuration
public class RabbitMQConfig {

    public static final String ORDER_QUEUE = "order-queue";
    public static final String ORDER_EXCHANGE = "order-exchange";
    public static final String ORDER_ROUTING_KEY = "order.created";

    @Bean
    public Queue orderQueue() {
        return QueueBuilder.durable(ORDER_QUEUE)
                .withArgument("x-dead-letter-exchange", "dlx-exchange")
                .withArgument("x-dead-letter-routing-key", "dlx.order")
                .build();
    }

    @Bean
    public TopicExchange orderExchange() {
        return new TopicExchange(ORDER_EXCHANGE);
    }

    @Bean
    public Binding orderBinding(Queue orderQueue,
                                 TopicExchange orderExchange) {
        return BindingBuilder
                .bind(orderQueue)
                .to(orderExchange)
                .with(ORDER_ROUTING_KEY);
    }

    @Bean
    public Jackson2JsonMessageConverter messageConverter() {
        return new Jackson2JsonMessageConverter();
    }
}
```

**Message gondermek (Producer):**

```java
@Service
public class OrderProducer {

    @Autowired
    private RabbitTemplate rabbitTemplate;

    public void sendOrderCreatedEvent(OrderEvent event) {
        rabbitTemplate.convertAndSend(
            RabbitMQConfig.ORDER_EXCHANGE,
            RabbitMQConfig.ORDER_ROUTING_KEY,
            event
        );
        log.info("Sifaris eventi novbeye gonderildi: {}", event.getId());
    }
}
```

**Message qebul etmek (Consumer):**

```java
@Component
public class OrderConsumer {

    @RabbitListener(queues = RabbitMQConfig.ORDER_QUEUE)
    public void handleOrderCreated(OrderEvent event) {
        log.info("Sifaris eventi alindi: {}", event.getId());

        try {
            // Sifarishi emal et
            processOrder(event);
        } catch (Exception e) {
            log.error("Sifaris emali ugursuz: {}", e.getMessage());
            // Message reject olunur ve DLQ-ya dushur
            throw new AmqpRejectAndDontRequeueException(e);
        }
    }

    // Retry mexanizmi
    @RabbitListener(queues = ORDER_QUEUE)
    @Retryable(maxAttempts = 3,
               backoff = @Backoff(delay = 1000, multiplier = 2))
    public void handleWithRetry(OrderEvent event) {
        processOrder(event);
    }
}
```

### Apache Kafka ile

```java
// Konfiqurasiya (application.yml)
// spring.kafka.bootstrap-servers: localhost:9092

@Service
public class KafkaOrderProducer {

    @Autowired
    private KafkaTemplate<String, OrderEvent> kafkaTemplate;

    public void sendOrderEvent(OrderEvent event) {
        kafkaTemplate.send("orders", event.getId().toString(), event)
            .addCallback(
                result -> log.info("Kafka-ya gonderildi: {}",
                    result.getRecordMetadata().offset()),
                ex -> log.error("Kafka gondermesi ugursuz: {}",
                    ex.getMessage())
            );
    }
}

@Component
public class KafkaOrderConsumer {

    @KafkaListener(topics = "orders", groupId = "order-service")
    public void handleOrderEvent(
            @Payload OrderEvent event,
            @Header(KafkaHeaders.RECEIVED_PARTITION) int partition,
            @Header(KafkaHeaders.OFFSET) long offset) {

        log.info("Kafka-dan alindi: partition={}, offset={}",
                 partition, offset);
        processOrder(event);
    }
}
```

## Laravel-de istifadesi

### Job yaratmaq

```bash
php artisan make:job ProcessOrder
```

```php
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    /**
     * Job-un icra olundugu metod
     */
    public function handle(
        PaymentService $paymentService,
        InventoryService $inventoryService
    ): void {
        // Dependency injection isleyir
        $paymentService->charge($this->order);
        $inventoryService->reserve($this->order);

        $this->order->update(['status' => 'processed']);
    }

    /**
     * Job ugursuz olduqda
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Sifaris emali ugursuz', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);

        // Istifadeciye bildiris gonder
        $this->order->user->notify(
            new OrderFailedNotification($this->order)
        );
    }
}
```

### Job-u novbeye gondermek (dispatch)

```php
class OrderController extends Controller
{
    public function store(OrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());

        // Novbeye gonder -- arxa fonda icra olunacaq
        ProcessOrder::dispatch($order);

        // Gecikmeli gonder -- 5 deqiqe sonra icra olunacaq
        ProcessOrder::dispatch($order)->delay(now()->addMinutes(5));

        // Museyyen novbeye gonder
        ProcessOrder::dispatch($order)->onQueue('orders');

        // Museyyen baglantiya gonder
        ProcessOrder::dispatch($order)->onConnection('redis');

        return response()->json([
            'message' => 'Sifaris qebul olundu, emal olunur...',
            'order' => $order,
        ], 202);
    }
}
```

### Queue Driver-leri

**.env:**

```env
QUEUE_CONNECTION=redis
```

**config/queue.php:**

```php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        // Sinxron -- novbe yoxdur, derhal icra olunur
        'sync' => [
            'driver' => 'sync',
        ],

        // Database
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        // Redis
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],

        // Amazon SQS
        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX'),
            'queue' => env('SQS_QUEUE', 'default'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

### Retry, Timeout ve Rate Limiting

```php
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Maksimum tekrar cehd sayi
    public int $tries = 3;

    // Maksimum icra mudetti (saniye)
    public int $timeout = 120;

    // Tekrar cehdler arasi gozleme (saniye)
    public int $backoff = 10;

    // Artan gozleme muddetleri
    // public array $backoff = [10, 30, 60];

    // Bu vaxtdan sonra job-u silmek olar
    public function retryUntil(): DateTime
    {
        return now()->addHours(24);
    }

    // Unique job -- eyni anda yalniz bir eded icra olunur
    public function uniqueId(): string
    {
        return $this->order->id;
    }

    public ShouldBeUnique $unique = true;

    public function handle(): void
    {
        // Rate limiting
        Redis::throttle('orders')
            ->allow(10)       // 10 job
            ->every(60)       // her 60 saniyede
            ->then(function () {
                // Job-u emal et
                $this->processOrder();
            }, function () {
                // Rate limit asildisa, novbeye qaytar
                $this->release(30);
            });
    }
}
```

### Failed Jobs

```bash
# Failed jobs cedelini yarat
php artisan queue:failed-table
php artisan migrate

# Ugursuz job-lari gor
php artisan queue:failed

# Museyyen job-u tekrar cehd et
php artisan queue:retry 5

# Butun ugursuz job-lari tekrar cehd et
php artisan queue:retry all

# Ugursuz job-u sil
php artisan queue:forget 5

# Butun ugursuz job-lari temizle
php artisan queue:flush
```

### Job Batching

Bir qrup job-u birlikde idare etmek:

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class ReportController extends Controller
{
    public function generateMonthlyReports(): JsonResponse
    {
        $users = User::all();

        $jobs = $users->map(function (User $user) {
            return new GenerateUserReport($user);
        })->toArray();

        $batch = Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // Butun job-lar ugurla bitdi
                Log::info("Batch tamamlandi: {$batch->id}");
                Notification::send(
                    Admin::all(),
                    new ReportsReadyNotification()
                );
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                // Ilk ugursuz job
                Log::error("Batch xetasi: {$e->getMessage()}");
            })
            ->finally(function (Batch $batch) {
                // Batch bitdi (ugurlu ve ya ugursuz)
                Log::info("Batch sona catdi. " .
                    "Ugurlu: {$batch->processedJobs()}, " .
                    "Ugursuz: {$batch->failedJobs}");
            })
            ->allowFailures()
            ->name('monthly-reports')
            ->onQueue('reports')
            ->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
        ]);
    }

    // Batch veziyyetini yoxla
    public function batchStatus(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        return response()->json([
            'id' => $batch->id,
            'name' => $batch->name,
            'total' => $batch->totalJobs,
            'processed' => $batch->processedJobs(),
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
        ]);
    }
}
```

### Job Chaining

Job-larin ardical icra olunmasini temin etmek:

```php
class OrderController extends Controller
{
    public function store(OrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());

        // Zincir -- her bir job evvelki bitdikden sonra bashlayir
        Bus::chain([
            new ValidateOrder($order),
            new ChargePayment($order),
            new ReserveInventory($order),
            new SendOrderConfirmation($order),
            new NotifyWarehouse($order),
        ])->onQueue('orders')
          ->catch(function (\Throwable $e) use ($order) {
              Log::error("Sifaris zinciri ugursuz: {$e->getMessage()}");
              $order->update(['status' => 'failed']);
          })
          ->dispatch();

        return response()->json($order, 202);
    }
}
```

### Queue Worker ishletmek

```bash
# Default worker
php artisan queue:work

# Museyyen novbe ve baglanti
php artisan queue:work redis --queue=orders,emails,default

# Bir job icra edib dayandirmaq
php artisan queue:work --once

# Memory limiti ile
php artisan queue:work --memory=256

# Maksimum is vaxti ile
php artisan queue:work --timeout=60

# Production ucun Supervisor konfiqurasiyasi
# /etc/supervisor/conf.d/laravel-worker.conf
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Asinxron metod** | `@Async` annotasiyasi | `dispatch()` ile job gondermek |
| **Message Broker** | RabbitMQ, Kafka (xarici) | Redis, DB, SQS (daxili driver) |
| **Job sinifi** | POJO + `@RabbitListener` | `ShouldQueue` interface |
| **Retry** | `@Retryable` / broker konfiq | `$tries`, `$backoff` property |
| **Failed Jobs** | Dead Letter Queue (DLQ) | `failed_jobs` cedveli |
| **Job Batching** | Manual implementation | `Bus::batch()` daxili |
| **Job Chaining** | `CompletableFuture.thenApply` | `Bus::chain()` daxili |
| **Rate Limiting** | Custom implementation | `Redis::throttle()` daxili |
| **Unique Jobs** | Manual lock ile | `ShouldBeUnique` interface |
| **Worker** | JVM icinde thread pool | Ayri proses (`queue:work`) |

## Niye bele ferqler var?

**Spring-in yanasmasi -- xarici broker-ler:**
Spring enterprise muhitinden gelir ve RabbitMQ, Kafka kimi production-ready message broker-leri istifade edir. Bu broker-ler olceklenebilir (scalable), etibarlı (reliable) ve muxtelif proqramlasdirma dillerinde isleyen servisler arasi rabiteni temin edir. Microservice arxitekturasinda bu cox vacibdir.

**Laravel-in yanasmasi -- daxili queue sistem:**
Laravel "hershey bir yerde" felsefesine uygun olaraq queue sistemini framework daxilinde teqdim edir. `php artisan make:job` yazib birbasah job yaratmaq, `dispatch()` ile gondermek, `queue:work` ile isletmek -- hamisi Laravel ekosisteminin icerisindedir. Bu, monolitik tetbiqlerde inisiasiya suretini artirır ve oyrenmeni asanlasdirir.

**CompletableFuture vs Job Chaining:**
Spring-de `CompletableFuture` JVM-in thread modeli uzerine quruludur -- eyni proses daxilinde paralel ve ardical emeliyyatlari idare edir. Laravel-de `Bus::chain()` ise ayri proses kimi isleyen worker-ler arasinda is bolusdurur. Spring-in yanasmasi daha cevik olsa da, Laravel-in yanasmasi daha etibarlıdır -- chunki her job ayrica izlenir, ugursuz olanda tekrar cehd olunur ve s.

**Failed Jobs:**
Spring-de ugursuz mesajlar Dead Letter Queue-ya (DLQ) dushur -- bu message broker-in ozu idare edir. Laravel-de ise `failed_jobs` cedveli yaradilir ve Artisan emurleri ile ugursuz job-lari gormek, tekrar cehd etmek mumkundur. Laravel-in yanasmasi developer-friendly-dir; Spring-in yanasmasi ise infrastructure seviyyesinde idareetmeni temin edir.

## Hansi framework-de var, hansinda yoxdur?

**Yalniz Spring-de:**
- Apache Kafka inteqrasiyasi -- yuksek throughput, event streaming
- `CompletableFuture` -- JVM seviyyesinde asinxron proqramlasdirma
- Thread pool konfiqurasiyasi -- `ThreadPoolTaskExecutor`
- Xarici message broker-ler ile zencin inteqrasiya (AMQP, JMS ve s.)

**Yalniz Laravel-de:**
- `Bus::batch()` -- job qruplarini birlikde izlemek, progress gormek
- `Bus::chain()` -- job-lari ardical zincirlemek
- `ShouldBeUnique` -- unique job mexanizmi daxili
- `Redis::throttle()` -- rate limiting daxili
- `$tries`, `$backoff`, `$timeout` -- job seviyyesinde retry konfiqurasiyasi property olaraq
- `failed_jobs` cedveli ve Artisan emurleri ile idare
- `queue:work --once` kimi is rejimleri
- Supervisor inteqrasiyasi ucun hazir konfiqurasiya
