# Spring for Apache Kafka vs Laravel Kafka — Dərin Müqayisə

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Apache Kafka — log-əsaslı distributed event streaming platform. Enterprise-də event-driven architecture, microservice-lər arası asenxron ünsiyyət, CDC, log aggregation, real-time analytics üçün standart seçimdir. Kafka-nın əsas konseptləri: **topic** (adlanan partition-lardan ibarət log), **partition** (order + parallelism vahidi), **consumer group** (iştirakçı consumer-lər arasında partition bölüşdürülür), **offset** (hansı mesaj oxunub).

**Spring for Apache Kafka** (`spring-kafka`) — Java-da Kafka ilə işləməyin standart yoludur. `KafkaTemplate` (producer), `@KafkaListener` (consumer), `ConcurrentKafkaListenerContainerFactory` (concurrency), `KafkaTransactionManager` (exactly-once), Schema Registry inteqrasiyası, retry topic, dead letter topic — hamısı built-in.

**Laravel**-də isə Kafka client built-in deyil. PHP üçün `php-rdkafka` C extension (librdkafka wrapper) lazımdır. Laravel camelotuncu üçün ən çox istifadə olunan paket **mateus-junges/laravel-kafka**-dır — producer/consumer API verir. Alternativ: `enqueue/rdkafka`, `laravel-kafka-consumer`, və Kafka-ni Laravel queue driver kimi istifadə edən community paketlər.

Bu sənəddə event-driven order processing-i hər iki framework-də quracayıq: producer, consumer group, error handler, retry topic, DLT.

---

## Spring-də istifadəsi

### 1) Dependency və konfigurasiya

```xml
<!-- pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.kafka</groupId>
        <artifactId>spring-kafka</artifactId>
        <version>3.2.4</version>
    </dependency>
    <dependency>
        <groupId>io.confluent</groupId>
        <artifactId>kafka-avro-serializer</artifactId>
        <version>7.6.1</version>
    </dependency>
    <dependency>
        <groupId>org.springframework.kafka</groupId>
        <artifactId>spring-kafka-test</artifactId>
        <scope>test</scope>
    </dependency>
</dependencies>
```

```yaml
# application.yml
spring:
  kafka:
    bootstrap-servers: kafka:9092
    producer:
      acks: all                                         # exactly-once üçün "all"
      retries: 10
      enable-idempotence: true                          # idempotent producer
      key-serializer: org.apache.kafka.common.serialization.StringSerializer
      value-serializer: org.springframework.kafka.support.serializer.JsonSerializer
      properties:
        max.in.flight.requests.per.connection: 5
        linger.ms: 20
        compression.type: lz4
    consumer:
      group-id: order-service
      auto-offset-reset: earliest
      enable-auto-commit: false                         # manual ack
      key-deserializer: org.apache.kafka.common.serialization.StringDeserializer
      value-deserializer: org.springframework.kafka.support.serializer.JsonDeserializer
      properties:
        spring.json.trusted.packages: "com.example.events"
        max.poll.records: 500
        isolation.level: read_committed                 # transactional producer ilə
    listener:
      ack-mode: manual_immediate
      concurrency: 3
      type: single                                      # batch üçün "batch"
```

### 2) Producer — `KafkaTemplate`

```java
@Configuration
public class KafkaProducerConfig {

    @Bean
    public NewTopic ordersTopic() {
        return TopicBuilder.name("orders.created")
            .partitions(6)
            .replicas(3)
            .config(TopicConfig.MIN_IN_SYNC_REPLICAS_CONFIG, "2")
            .config(TopicConfig.RETENTION_MS_CONFIG, "604800000")   // 7 gün
            .build();
    }

    @Bean
    public NewTopic ordersRetryTopic() {
        return TopicBuilder.name("orders.created.retry")
            .partitions(6)
            .replicas(3)
            .build();
    }

    @Bean
    public NewTopic ordersDltTopic() {
        return TopicBuilder.name("orders.created.dlt")
            .partitions(6)
            .replicas(3)
            .build();
    }
}

@Service
public class OrderEventPublisher {

    private static final Logger log = LoggerFactory.getLogger(OrderEventPublisher.class);
    private final KafkaTemplate<String, OrderEvent> kafkaTemplate;

    public OrderEventPublisher(KafkaTemplate<String, OrderEvent> kafkaTemplate) {
        this.kafkaTemplate = kafkaTemplate;
    }

    public void publish(OrderEvent event) {
        // Key → order.customerId: eyni customer eyni partition-a
        CompletableFuture<SendResult<String, OrderEvent>> future =
            kafkaTemplate.send("orders.created", String.valueOf(event.customerId()), event);

        future.whenComplete((result, ex) -> {
            if (ex == null) {
                RecordMetadata md = result.getRecordMetadata();
                log.info("Event sent: topic={} partition={} offset={}",
                    md.topic(), md.partition(), md.offset());
            } else {
                log.error("Send failed for key={}", event.customerId(), ex);
                // Outbox pattern-ə yaz və ya alert
            }
        });
    }

    // Headers ilə
    public void publishWithHeaders(OrderEvent event, String tenantId) {
        ProducerRecord<String, OrderEvent> record = new ProducerRecord<>(
            "orders.created",
            null,
            String.valueOf(event.customerId()),
            event
        );
        record.headers().add("tenant-id", tenantId.getBytes(StandardCharsets.UTF_8));
        record.headers().add("event-version", "v2".getBytes(StandardCharsets.UTF_8));
        kafkaTemplate.send(record);
    }
}

public record OrderEvent(
    Long orderId,
    Long customerId,
    BigDecimal amount,
    String status,
    Instant occurredAt
) {}
```

### 3) Consumer — `@KafkaListener`

```java
@Component
public class OrderEventConsumer {

    private static final Logger log = LoggerFactory.getLogger(OrderEventConsumer.class);
    private final InventoryService inventoryService;

    public OrderEventConsumer(InventoryService inventoryService) {
        this.inventoryService = inventoryService;
    }

    @KafkaListener(
        topics = "orders.created",
        groupId = "inventory-service",
        concurrency = "3"
    )
    public void onOrderCreated(
            @Payload OrderEvent event,
            @Header(KafkaHeaders.RECEIVED_PARTITION) int partition,
            @Header(KafkaHeaders.OFFSET) long offset,
            @Header(value = "tenant-id", required = false) String tenantId,
            Acknowledgment ack) {

        log.info("Received orderId={} partition={} offset={} tenant={}",
            event.orderId(), partition, offset, tenantId);

        try {
            inventoryService.reserveStock(event);
            ack.acknowledge();
        } catch (RetriableException e) {
            // Retry topic-a göndər — error handler üçün throw
            throw e;
        } catch (Exception e) {
            log.error("Permanent error for orderId={}", event.orderId(), e);
            throw new RuntimeException(e);   // DLT-yə get
        }
    }

    // Batch listener
    @KafkaListener(
        topics = "orders.created",
        groupId = "analytics-service",
        containerFactory = "batchContainerFactory"
    )
    public void onOrderBatch(List<OrderEvent> events, Acknowledgment ack) {
        log.info("Batch size: {}", events.size());
        analyticsService.bulkInsert(events);
        ack.acknowledge();
    }
}
```

### 4) ConcurrentKafkaListenerContainerFactory + error handler

```java
@Configuration
@EnableKafka
public class KafkaConsumerConfig {

    @Bean
    public ConcurrentKafkaListenerContainerFactory<String, OrderEvent> kafkaListenerContainerFactory(
            ConsumerFactory<String, OrderEvent> consumerFactory,
            KafkaTemplate<Object, Object> kafkaTemplate) {

        ConcurrentKafkaListenerContainerFactory<String, OrderEvent> factory =
            new ConcurrentKafkaListenerContainerFactory<>();
        factory.setConsumerFactory(consumerFactory);
        factory.setConcurrency(3);
        factory.getContainerProperties().setAckMode(ContainerProperties.AckMode.MANUAL_IMMEDIATE);

        // DefaultErrorHandler — retry + DLT
        DefaultErrorHandler errorHandler = new DefaultErrorHandler(
            new DeadLetterPublishingRecoverer(kafkaTemplate,
                (record, ex) -> new TopicPartition(record.topic() + ".dlt", record.partition())),
            new FixedBackOff(1000L, 3L)
        );

        // Hansı exception-lar retry edilmir (fatal)
        errorHandler.addNotRetryableExceptions(
            DeserializationException.class,
            IllegalArgumentException.class
        );

        factory.setCommonErrorHandler(errorHandler);
        return factory;
    }

    @Bean
    public ConcurrentKafkaListenerContainerFactory<String, OrderEvent> batchContainerFactory(
            ConsumerFactory<String, OrderEvent> consumerFactory) {
        ConcurrentKafkaListenerContainerFactory<String, OrderEvent> factory =
            new ConcurrentKafkaListenerContainerFactory<>();
        factory.setConsumerFactory(consumerFactory);
        factory.setBatchListener(true);
        return factory;
    }
}
```

### 5) Retry Topic pattern — `@RetryableTopic`

Spring Kafka-da retry topic avtomatik qurulur:

```java
@Component
public class RetryableOrderConsumer {

    @RetryableTopic(
        attempts = "4",
        backoff = @Backoff(delay = 1000, multiplier = 2.0, maxDelay = 30000),
        topicSuffixingStrategy = TopicSuffixingStrategy.SUFFIX_WITH_INDEX_VALUE,
        dltStrategy = DltStrategy.FAIL_ON_ERROR,
        exclude = { IllegalArgumentException.class, DeserializationException.class }
    )
    @KafkaListener(topics = "orders.created", groupId = "notification-service")
    public void onOrderCreated(OrderEvent event) {
        notificationService.send(event);
    }

    @DltHandler
    public void onDlt(OrderEvent event, @Header(KafkaHeaders.ORIGINAL_TOPIC) String topic) {
        log.error("DLT: topic={} event={}", topic, event);
        alertService.raiseOpsAlert(event);
    }
}
```

Bu avtomatik yaradacaq: `orders.created-retry-0`, `orders.created-retry-1`, …, `orders.created-dlt`.

### 6) Kafka transactions (exactly-once)

Consumer oxuduğunu DB-yə yazır və yeni event publish edir — bu 3 addım atomik olmalıdır:

```java
@Configuration
public class KafkaTransactionConfig {

    @Bean
    public KafkaTransactionManager<String, OrderEvent> kafkaTransactionManager(
            ProducerFactory<String, OrderEvent> pf) {
        return new KafkaTransactionManager<>(pf);
    }

    @Bean
    public ChainedKafkaTransactionManager<Object, Object> chainedTx(
            JpaTransactionManager jpaTm,
            KafkaTransactionManager<Object, Object> kafkaTm) {
        return new ChainedKafkaTransactionManager<>(kafkaTm, jpaTm);
    }
}

@Service
public class OrderProcessor {

    @Transactional("chainedTx")
    @KafkaListener(topics = "orders.created")
    public void process(OrderEvent event) {
        Order saved = orderRepository.save(new Order(event));       // DB
        kafkaTemplate.send("orders.processed", saved.getId().toString(), saved);   // Kafka
        // Hər iki əməliyyat bir transaction-da
    }
}
```

Producer konfigurasiyasında `transactional.id` lazımdır:

```yaml
spring:
  kafka:
    producer:
      transaction-id-prefix: order-tx-
```

### 7) Serializers — JSON və Avro

```java
// JSON — default
@Bean
public ProducerFactory<String, OrderEvent> jsonProducerFactory() {
    Map<String, Object> config = new HashMap<>();
    config.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, "kafka:9092");
    config.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class);
    config.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, JsonSerializer.class);
    return new DefaultKafkaProducerFactory<>(config);
}

// Avro + Schema Registry
@Bean
public ProducerFactory<String, OrderEventAvro> avroProducerFactory() {
    Map<String, Object> config = new HashMap<>();
    config.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, "kafka:9092");
    config.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class);
    config.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, KafkaAvroSerializer.class);
    config.put("schema.registry.url", "http://schema-registry:8081");
    config.put("auto.register.schemas", false);   // Prod-da CI-da register et
    return new DefaultKafkaProducerFactory<>(config);
}
```

### 8) Kafka Streams — stream processing

```java
@Configuration
@EnableKafkaStreams
public class KafkaStreamsConfig {

    @Bean(name = KafkaStreamsDefaultConfiguration.DEFAULT_STREAMS_CONFIG_BEAN_NAME)
    public KafkaStreamsConfiguration streamsConfig() {
        Map<String, Object> props = new HashMap<>();
        props.put(StreamsConfig.APPLICATION_ID_CONFIG, "order-stream");
        props.put(StreamsConfig.BOOTSTRAP_SERVERS_CONFIG, "kafka:9092");
        props.put(StreamsConfig.DEFAULT_KEY_SERDE_CLASS_CONFIG, Serdes.String().getClass());
        props.put(StreamsConfig.PROCESSING_GUARANTEE_CONFIG, StreamsConfig.EXACTLY_ONCE_V2);
        return new KafkaStreamsConfiguration(props);
    }

    @Bean
    public KStream<String, OrderEvent> kStream(StreamsBuilder builder) {
        KStream<String, OrderEvent> orders = builder.stream("orders.created",
            Consumed.with(Serdes.String(), new JsonSerde<>(OrderEvent.class)));

        // Hər customer üçün ümumi məbləği hesabla (window 1 dəq)
        orders
            .filter((k, v) -> v.amount().compareTo(BigDecimal.ZERO) > 0)
            .groupByKey()
            .windowedBy(TimeWindows.ofSizeWithNoGrace(Duration.ofMinutes(1)))
            .aggregate(
                () -> BigDecimal.ZERO,
                (k, v, agg) -> agg.add(v.amount()),
                Materialized.as("customer-amount-store")
            )
            .toStream()
            .to("customer-totals", Produced.with(...));

        return orders;
    }
}
```

### 9) Tombstone mesajları (log compaction)

```java
// null value = tombstone — key-i log-dan sil (compacted topic-də)
kafkaTemplate.send("users.profile", userId, null);
```

### 10) Testing — EmbeddedKafka / Testcontainers

```java
@SpringBootTest
@EmbeddedKafka(partitions = 3, topics = {"orders.created"})
class OrderEventConsumerTest {

    @Autowired
    private KafkaTemplate<String, OrderEvent> kafkaTemplate;

    @MockBean
    private InventoryService inventoryService;

    @Test
    void shouldConsumeOrderEvent() throws Exception {
        OrderEvent event = new OrderEvent(1L, 100L, new BigDecimal("99.99"),
                                          "CREATED", Instant.now());

        kafkaTemplate.send("orders.created", "100", event).get();

        verify(inventoryService, timeout(5000)).reserveStock(event);
    }
}

// Testcontainers variant
@SpringBootTest
@Testcontainers
class OrderConsumerIntegrationTest {
    @Container
    static KafkaContainer kafka = new KafkaContainer(DockerImageName.parse("confluentinc/cp-kafka:7.6.1"));

    @DynamicPropertySource
    static void overrideProps(DynamicPropertyRegistry registry) {
        registry.add("spring.kafka.bootstrap-servers", kafka::getBootstrapServers);
    }
}
```

---

## Laravel-də istifadəsi

### 1) composer və konfigurasiya

```json
{
    "require": {
        "php": "^8.3",
        "ext-rdkafka": "*",
        "laravel/framework": "^12.0",
        "mateusjunges/laravel-kafka": "^2.4"
    }
}
```

```bash
# librdkafka və PHP extension
apt-get install librdkafka-dev
pecl install rdkafka
```

```php
// config/kafka.php
return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP', 'default'),
    'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
    'sasl' => [
        'username' => env('KAFKA_SASL_USERNAME'),
        'password' => env('KAFKA_SASL_PASSWORD'),
        'mechanisms' => env('KAFKA_SASL_MECHANISMS'),
    ],
    'schema_registry' => [
        'url' => env('KAFKA_SCHEMA_REGISTRY_URL'),
    ],
    'offset_reset' => 'earliest',
    'auto_commit' => false,
];
```

### 2) Producer — KafkaProducer

```php
// app/Services/OrderEventPublisher.php
namespace App\Services;

use App\Events\OrderCreated;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class OrderEventPublisher
{
    public function publish(OrderCreated $event): void
    {
        try {
            $message = new Message(
                body: [
                    'orderId' => $event->orderId,
                    'customerId' => $event->customerId,
                    'amount' => $event->amount,
                    'status' => $event->status,
                    'occurredAt' => $event->occurredAt->toIso8601String(),
                ],
                key: (string) $event->customerId,                    // partition key
                headers: [
                    'tenant-id' => $event->tenantId,
                    'event-version' => 'v2',
                ]
            );

            Kafka::publish('kafka:9092')
                ->onTopic('orders.created')
                ->withConfigOptions([
                    'compression.type' => 'lz4',
                    'linger.ms' => '20',
                    'enable.idempotence' => 'true',
                    'acks' => 'all',
                ])
                ->withMessage($message)
                ->send();

            Log::info('Event published', ['orderId' => $event->orderId]);
        } catch (\Throwable $e) {
            Log::error('Kafka publish failed', ['error' => $e->getMessage()]);
            // Outbox cədvəlinə yaz — sonra retry olunsun
            throw $e;
        }
    }
}
```

### 3) Consumer — artisan command kimi

```php
// app/Console/Commands/ConsumeOrderEvents.php
namespace App\Console\Commands;

use App\Services\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class ConsumeOrderEvents extends Command
{
    protected $signature = 'kafka:consume:orders';
    protected $description = 'orders.created topic-indən mesaj oxu';

    public function handle(InventoryService $inventoryService): int
    {
        $consumer = Kafka::consumer(['orders.created'])
            ->withBrokers('kafka:9092')
            ->withConsumerGroupId('inventory-service')
            ->withAutoCommit(false)
            ->withOptions([
                'auto.offset.reset' => 'earliest',
                'max.poll.records' => '500',
                'isolation.level' => 'read_committed',
            ])
            ->withHandler(function (ConsumerMessage $message) use ($inventoryService) {
                $body = $message->getBody();
                $headers = $message->getHeaders();

                Log::info('Received', [
                    'orderId' => $body['orderId'],
                    'partition' => $message->getPartition(),
                    'offset' => $message->getOffset(),
                    'tenant' => $headers['tenant-id'] ?? null,
                ]);

                try {
                    $inventoryService->reserveStock($body);
                } catch (\Throwable $e) {
                    Log::error('Processing failed', [
                        'orderId' => $body['orderId'],
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;   // error handler-a get
                }
            })
            ->withMaxMessages(-1)
            ->build();

        $consumer->consume();

        return self::SUCCESS;
    }
}
```

### 4) Error handler + retry topic

Laravel-də retry topic manual qurulmalıdır:

```php
// app/Services/KafkaRetryHandler.php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class KafkaRetryHandler
{
    private const MAX_RETRIES = 3;

    public function handle(ConsumerMessage $message, \Throwable $e): void
    {
        $headers = $message->getHeaders();
        $retryCount = (int) ($headers['retry-count'] ?? 0);

        if ($this->isNonRetryable($e)) {
            $this->sendToDlt($message, $e);
            return;
        }

        if ($retryCount >= self::MAX_RETRIES) {
            $this->sendToDlt($message, $e);
            return;
        }

        // Retry topic-ə göndər (backoff delay əlavə et)
        $delay = 1000 * (2 ** $retryCount);   // exponential
        sleep((int) ($delay / 1000));

        $this->sendToRetryTopic($message, $retryCount + 1, $e);
    }

    private function sendToRetryTopic(ConsumerMessage $message, int $retryCount, \Throwable $e): void
    {
        Kafka::publish('kafka:9092')
            ->onTopic('orders.created.retry')
            ->withMessage(new Message(
                body: $message->getBody(),
                key: $message->getKey(),
                headers: array_merge($message->getHeaders(), [
                    'retry-count' => (string) $retryCount,
                    'last-error' => $e->getMessage(),
                ])
            ))
            ->send();
    }

    private function sendToDlt(ConsumerMessage $message, \Throwable $e): void
    {
        Kafka::publish('kafka:9092')
            ->onTopic('orders.created.dlt')
            ->withMessage(new Message(
                body: $message->getBody(),
                key: $message->getKey(),
                headers: array_merge($message->getHeaders(), [
                    'final-error' => $e->getMessage(),
                    'failed-at' => now()->toIso8601String(),
                ])
            ))
            ->send();

        Log::error('Message sent to DLT', [
            'key' => $message->getKey(),
            'error' => $e->getMessage(),
        ]);
    }

    private function isNonRetryable(\Throwable $e): bool
    {
        return $e instanceof \InvalidArgumentException
            || $e instanceof \JsonException;
    }
}
```

### 5) Supervisor ilə consumer işlətmək

```ini
; /etc/supervisor/conf.d/kafka-orders.conf
[program:kafka-orders-consumer]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan kafka:consume:orders
autostart=true
autorestart=true
user=www-data
numprocs=3                                ; 3 paralel consumer
redirect_stderr=true
stdout_logfile=/var/log/kafka-orders.log
stopwaitsecs=3600
```

### 6) Laravel queue driver kimi Kafka

Kafka-ni queue backend kimi də istifadə etmək olar (rekommendasiya olunmur — retry/backoff mexanizmi Kafka ilə uyğun deyil):

```php
// config/queue.php
'kafka' => [
    'driver' => 'kafka',
    'queue' => env('KAFKA_QUEUE', 'default'),
    'brokers' => env('KAFKA_BROKERS'),
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP'),
],
```

### 7) Producer — sadə event publish helper

```php
// app/Events/OrderCreated.php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $orderId,
        public int $customerId,
        public float $amount,
        public string $status,
        public \DateTimeInterface $occurredAt,
        public ?string $tenantId = null,
    ) {}
}

// app/Providers/EventServiceProvider.php
protected $listen = [
    OrderCreated::class => [
        PublishOrderToKafka::class,
    ],
];

// app/Listeners/PublishOrderToKafka.php
class PublishOrderToKafka implements ShouldQueue
{
    public function __construct(private OrderEventPublisher $publisher) {}

    public function handle(OrderCreated $event): void
    {
        $this->publisher->publish($event);
    }
}
```

### 8) Testing

`mateus-junges/laravel-kafka` fake helper verir:

```php
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Support\Testing\Fakes\KafkaFake;

public function test_order_event_is_published(): void
{
    Kafka::fake();

    $event = new OrderCreated(1, 100, 99.99, 'CREATED', now());
    app(OrderEventPublisher::class)->publish($event);

    Kafka::assertPublished();
    Kafka::assertPublishedOn('orders.created', function ($message) use ($event) {
        return $message->getKey() === (string) $event->customerId
            && $message->getBody()['orderId'] === $event->orderId;
    });
}
```

Testcontainers alternativi — `eloquent/testcontainers-php` və ya Docker Compose-da Kafka container qaldırıb inteqrasiya testi yazmaq.

### 9) Schema Registry (Avro)

Laravel-də Avro dəstəyi məhduddur. `flix-tech/avro-serde-php` paketi ilə manual serialize:

```php
use FlixTech\AvroSerdeBundle\Serde\AvroSerde;

$serde = new AvroSerde(
    schemaRegistryUrl: 'http://schema-registry:8081'
);

$bytes = $serde->serialize('orders.created-value', $event);

Kafka::publish()
    ->onTopic('orders.created')
    ->withMessage(new Message(body: $bytes))
    ->send();
```

### 10) Tombstone və log compaction

```php
// null body = tombstone
$message = new Message(body: null, key: (string) $userId);
Kafka::publish()->onTopic('users.profile')->withMessage($message)->send();
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Kafka | Laravel (mateus-junges) |
|---|---|---|
| Ekosistemdə mövqe | First-class, rəsmi Spring | 3rd party paket |
| Producer API | `KafkaTemplate` (async+sync) | `Kafka::publish()` fluent |
| Consumer API | `@KafkaListener` annotation | Artisan command + handler closure |
| Concurrency | `containerFactory.concurrency=3` | Supervisor `numprocs=3` |
| Batch listener | `batchListener=true` + `List<Event>` | Manual loop |
| Retry topic | `@RetryableTopic` deklarativ | Manual — retry handler yaz |
| DLT | `DeadLetterPublishingRecoverer` | Manual |
| Transactions (EOS) | `KafkaTransactionManager` + `@Transactional` | Yox (librdkafka-dan verilmir) |
| Kafka Streams | `@EnableKafkaStreams` + StreamsBuilder | Yox |
| Schema Registry | Avro + Confluent serializer | Manual (`flix-tech/avro-serde`) |
| Idempotent producer | `enable.idempotence=true` | Eyni option librdkafka-da |
| Header | `@Header` annotation | `$message->getHeaders()` |
| Ack mode | MANUAL_IMMEDIATE, RECORD, BATCH | Manual commit library-dən |
| Testing | `@EmbeddedKafka` / Testcontainers | `Kafka::fake()` / Testcontainers |
| Connection pooling | Spring bean lifecycle | PHP proses per command |
| Long-running worker | Thread-based container | Ayrıca proses (supervisor) |

---

## Niyə belə fərqlər var?

**JVM və long-running consumer.** Kafka consumer API fundamentally "long-running poll loop" əsaslıdır. JVM-də bir thread bu loop-u sonsuza qədər aparır. Spring Kafka bu loop-u `MessageListenerContainer` ilə encapsulate edir və thread-lərin yaradılması JVM-in öz işidir. PHP-də request-per-process modelinə görə `artisan` command + supervisor istifadə olunur — ayrıca PHP prosesləri consumer kimi.

**Exactly-once semantikası.** JVM-də `KafkaTransactionManager` producer və DB transaction-u bir `ChainedKafkaTransactionManager`-da birləşdirir. PHP-də bu çətindir: librdkafka transactional producer API-si var, amma `pdo_mysql` ilə bağlamaq üçün xüsusi kod tələb olunur. Praktikada Laravel-də **outbox pattern** daha çox işlədilir: DB-yə yaz → ayrıca worker oxuyub Kafka-ya göndərir.

**Kafka Streams.** Kafka Streams JVM library-sidir — yalnız Java/Kotlin/Scala-da işləyir. PHP-də ekvivalent yoxdur. Stream processing tələb olunursa, Laravel tərəfdə Kafka-dan oxuyub Python/Java worker-ə göndərmək, və ya ksqlDB istifadə etmək adi praktikadır.

**Schema Registry + Avro.** Java-da Confluent-in `KafkaAvroSerializer`-i standartdır. PHP-də Avro dəstəyi az məşhurdur — JSON daha çox istifadə olunur. Bu Laravel-in Kafka-da ən zəif nöqtələrindən biridir.

**Retry/DLT deklarativ vs imperative.** Spring `@RetryableTopic` annotasiyası 3 sətirlə retry topic pipeline qurur. Laravel-də hər şeyi manual yazmaq lazımdır. Declarative yanaşma daha qısa, amma Spring-in Kafka konsepsiyalarını dərindən bilmək lazımdır.

**Ekosistem dəstəyi.** Kafka Confluent + LinkedIn + Spring tərəfindən Java-da yaranıb. PHP community Kafka-ni sonradan tutub — buna görə paket seçimi azdır və bəziləri maintain olunmur. Production-da böyük PHP layihələri Kafka əvəzinə Redis Streams və ya RabbitMQ seçir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Kafka-da:**
- `@KafkaListener` deklarativ consumer
- `@RetryableTopic` annotasiyası — retry topic avtomatik
- `DeadLetterPublishingRecoverer` — DLT avtomatik
- `KafkaTransactionManager` + `@Transactional` birləşmiş TX
- `ChainedKafkaTransactionManager` DB + Kafka atomik
- Kafka Streams inteqrasiyası (`@EnableKafkaStreams`)
- `ConcurrentKafkaListenerContainerFactory` — thread-based concurrency
- `EmbeddedKafka` test utility
- Confluent Avro serializer — first-class
- Header binding `@Header` annotation
- Batch listener (`List<Event>` payload)

**Yalnız Laravel-də (və ya daha asan):**
- `Kafka::fake()` mock helper — sadə test
- Artisan command interface
- Supervisor konfigurasiyası ilə proses idarəetməsi (JVM-dən daha sadə deploy)

**Kafka vs Redis queue tradeoff (Laravel üçün):**

| Tələb | Redis queue | Kafka |
|---|---|---|
| Sadə background job | ✓ | Overkill |
| Order guarantee per partition | ✗ | ✓ |
| 1M+ mesaj/saniyə | Məhdud | ✓ |
| Event replay | ✗ (silir) | ✓ (retention) |
| Multiple consumer groups | Manual (streams) | Native |
| Log compaction | ✗ | ✓ |
| Schema evolution | ✗ | ✓ (Schema Registry) |
| Deployment complexity | Düşük | Yüksək |
| Operational cost | Düşük | Yüksək (broker cluster) |

Laravel layihəsində Kafka seçimi yalnız **enterprise event-driven architecture** tələb olunanda həqiqətən faydalıdır. Əksər hallarda Redis queue və ya SQS daha sadədir.

---

## Best Practices

**Spring Kafka üçün:**
- Producer: `acks=all` + `enable.idempotence=true` + `retries=Integer.MAX_VALUE`
- Consumer: `enable-auto-commit=false`, manual ack
- `@RetryableTopic` istifadə et — manual retry loop yazma
- Key seçimi: eyni entity-ni eyni partition-a göndər (customerId, tenantId)
- `isolation.level=read_committed` transactional producer ilə
- Batch listener analytics kimi high-throughput üçün
- Deserialization exception-lar retry-da exclude et
- Schema Registry istifadə et — JSON schema-less olur

**Laravel üçün:**
- Outbox pattern istifadə et — DB və Kafka atomikliyini təmin et
- Consumer-i artisan command + supervisor ilə deploy et
- `numprocs` partition sayına bərabər və ya az olsun
- Retry topic-i manual qur — hər error type üçün siyasət müəyyən et
- `rdkafka` extension-i hər PHP image-a əlavə et (Docker)
- Redis/SQS ilə başla, Kafka real event streaming ehtiyacı olanda keç
- Schema Registry mürəkkəbliyini avtoma: `flix-tech/avro-serde` və ya JSON schema validation

---

## Yekun

Spring Kafka — Java/Kotlin enterprise-də Kafka ilə işləməyin qızıl standartıdır. `@KafkaListener`, `@RetryableTopic`, `KafkaTransactionManager` kimi deklarativ alətlər inkişafı sürətləndirir. Kafka Streams inteqrasiyası Spring-in fərqləndirici xüsusiyyətidir.

Laravel-də Kafka dəstəyi 3rd party paketlər (əsasən `mateus-junges/laravel-kafka`) vasitəsilədir. Producer/consumer mümkündür, amma retry topic, DLT, transactional producer, Kafka Streams kimi inkişaflı xüsusiyyətləri manual qurmaq lazım gəlir. PHP-nin request-per-process modelinə görə consumer-lər supervisor ilə idarə olunur.

Qısa qayda: **Kafka ekosisteminin tam gücündən istifadə etmək üçün Spring Kafka seçin. Laravel-də yalnız real event streaming tələbi varsa Kafka istifadə edin — əks halda Redis queue və ya SQS daha praktikdir.**
