# Spring Cloud Stream vs Laravel Messaging — Dərin Müqayisə

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Spring Cloud Stream — Spring ekosisteminin **messaging abstraction layer**-ıdır. Məqsəd sadədir: kodunu yaz, sonra config dəyişərək Kafka, RabbitMQ, Pulsar, AWS Kinesis və ya Solace ilə işlət. Aradakı körpü **Binder** adlanır. Sənin `Consumer<Message>` bean-in eyni qalır, amma `spring-cloud-stream-binder-kafka` asılılığını `spring-cloud-stream-binder-rabbit` ilə əvəz edəndə işlər RabbitMQ-ya keçir.

Laravel-də belə bir abstraction yoxdur. **Queue** sistemi (Redis, SQS, Beanstalkd, Database, RabbitMQ paketi ilə) var, amma bu **iş növbəsi** (job queue) modelidir — bir istehsalçı bir istehlakçı. Əsl **pub/sub** (bir hadisə → çoxlu dinləyən) üçün Laravel-də `mateusjunges/laravel-kafka`, `vladimir-yuldashev/laravel-queue-rabbitmq` və ya AWS SNS+SQS SDK lazımdır. Real-time WebSocket üçün isə Broadcasting (Pusher, Reverb) işlədilir. Hər biri ayrıca API-yə malikdir.

---

## Spring-də istifadəsi

### 1) Asılılıqlar və binder seçimi

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-stream-kafka</artifactId>
</dependency>
<!-- və ya RabbitMQ üçün -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-stream-rabbit</artifactId>
</dependency>
```

Binder dəyişəndə kod dəyişmir — yalnız asılılıq və config dəyişir. Bu Spring Cloud Stream-in ən vacib vədidir.

### 2) Functional model — Consumer, Function, Supplier

Spring Boot 3-də legacy `@StreamListener` silinib. Yeni model **java.util.function** bean-lərinə əsaslanır:

```java
@Configuration
public class UserRegisteredFlow {

    // Supplier — broker-a mesaj yazır
    @Bean
    public Supplier<UserRegisteredEvent> userRegisteredProducer() {
        return () -> null; // manual dispatch StreamBridge ilə
    }

    // Consumer — broker-dan oxuyur
    @Bean
    public Consumer<Message<UserRegisteredEvent>> sendWelcomeEmail(EmailService emails) {
        return message -> {
            UserRegisteredEvent event = message.getPayload();
            String traceId = (String) message.getHeaders().get("traceId");
            emails.sendWelcome(event.email(), event.name(), traceId);
        };
    }

    @Bean
    public Consumer<Message<UserRegisteredEvent>> trackAnalytics(AnalyticsService analytics) {
        return message -> analytics.track("user_registered", message.getPayload());
    }

    // Function — oxu, çevir, yaz
    @Bean
    public Function<UserRegisteredEvent, WelcomeBonus> issueBonus(BonusCalculator calc) {
        return event -> calc.calculate(event.userId(), event.country());
    }
}
```

### 3) `application.yml` — bindings, destination, group

```yaml
spring:
  application:
    name: user-service
  cloud:
    stream:
      default:
        contentType: application/json
      function:
        definition: sendWelcomeEmail;trackAnalytics;issueBonus
      bindings:
        sendWelcomeEmail-in-0:
          destination: user.registered          # topic/exchange adı
          group: email-service                  # consumer group
          consumer:
            max-attempts: 5
            back-off-initial-interval: 1000
            back-off-multiplier: 2.0
            back-off-max-interval: 30000
            concurrency: 4
        trackAnalytics-in-0:
          destination: user.registered
          group: analytics-service              # ayrıca group — hər ikisi oxuyur
          consumer:
            concurrency: 8
        issueBonus-in-0:
          destination: user.registered
          group: bonus-service
        issueBonus-out-0:
          destination: user.bonus-issued
          producer:
            partition-key-expression: payload.userId
            partition-count: 8

      kafka:
        binder:
          brokers: kafka-1:9092,kafka-2:9092
          auto-create-topics: false
          required-acks: all
        bindings:
          sendWelcomeEmail-in-0:
            consumer:
              enable-dlq: true
              dlq-name: user.registered.dlq
              configuration:
                isolation.level: read_committed
```

### 4) StreamBridge — manual publish

Controller-dən mesaj göndərmək lazım olanda:

```java
@RestController
@RequiredArgsConstructor
public class RegistrationController {

    private final StreamBridge streamBridge;
    private final UserService userService;

    @PostMapping("/register")
    public ResponseEntity<UserDto> register(@RequestBody @Valid RegisterRequest req) {
        User user = userService.register(req);

        UserRegisteredEvent event = new UserRegisteredEvent(
            user.getId(), user.getEmail(), user.getName(), user.getCountry(), Instant.now()
        );

        Message<UserRegisteredEvent> msg = MessageBuilder.withPayload(event)
            .setHeader("traceId", MDC.get("traceId"))
            .setHeader(KafkaHeaders.KEY, user.getId().toString())
            .build();

        streamBridge.send("user.registered", msg);

        return ResponseEntity.status(201).body(UserDto.from(user));
    }
}
```

### 5) Partitioning və consumer groups

```yaml
spring:
  cloud:
    stream:
      bindings:
        issueBonus-out-0:
          producer:
            partition-key-expression: payload.userId
            partition-count: 12
        sendWelcomeEmail-in-0:
          consumer:
            partitioned: true
            instance-count: 3          # 3 pod
            instance-index: ${POD_INDEX:0}
```

Eyni `userId` həmişə eyni partisiyaya gedir — user üzrə sıralılığı qoruyur. `instance-count` və `instance-index` Kubernetes StatefulSet ilə pair olunur.

### 6) Dead Letter Queue + retry

```yaml
spring:
  cloud:
    stream:
      bindings:
        sendWelcomeEmail-in-0:
          consumer:
            max-attempts: 5
            back-off-initial-interval: 2000
            back-off-multiplier: 2.0
      kafka:
        bindings:
          sendWelcomeEmail-in-0:
            consumer:
              enable-dlq: true
              dlq-name: user.registered.email.dlq
              dlq-producer-properties:
                compression.type: lz4
```

```java
@Component
public class DlqInspector {
    @KafkaListener(topics = "user.registered.email.dlq", groupId = "dlq-inspector")
    public void inspectDlq(ConsumerRecord<String, byte[]> record) {
        Header reason = record.headers().lastHeader("x-exception-message");
        log.error("DLQ msg key={} reason={}", record.key(),
            reason != null ? new String(reason.value()) : "unknown");
    }
}
```

### 7) Spring Cloud Function inteqrasiyası (serverless)

Eyni `Function` bean-i AWS Lambda, Azure Functions, Google Cloud Functions-da işlədilə bilər:

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-function-adapter-aws</artifactId>
</dependency>
```

```java
@Bean
public Function<UserRegisteredEvent, WelcomeBonus> issueBonus() {
    return event -> new WelcomeBonus(event.userId(), BigDecimal.valueOf(10));
}
```

Eyni kod broker-də Consumer kimi, Lambda-da HTTP handler kimi işləyir.

### 8) Kafka Streams binder — stateful processing

Aggregation, join, windowing üçün:

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-stream-binder-kafka-streams</artifactId>
</dependency>
```

```java
@Bean
public Function<KStream<String, OrderEvent>, KStream<String, OrderStats>> orderStats() {
    return input -> input
        .groupByKey()
        .windowedBy(TimeWindows.ofSizeWithNoGrace(Duration.ofMinutes(5)))
        .aggregate(
            OrderStats::new,
            (key, order, stats) -> stats.add(order),
            Materialized.with(Serdes.String(), new JsonSerde<>(OrderStats.class))
        )
        .toStream()
        .map((windowedKey, stats) -> new KeyValue<>(windowedKey.key(), stats));
}
```

### 9) Schema evolution

```yaml
spring:
  cloud:
    stream:
      kafka:
        binder:
          producer-properties:
            schema.registry.url: http://schema-registry:8081
            value.subject.name.strategy: io.confluent.kafka.serializers.subject.TopicNameStrategy
            auto.register.schemas: false
```

Avro/Protobuf ilə istifadə edildikdə Schema Registry versiya yoxlaması edir — geriyə uyğunluq pozulanda publish edə bilməyəcəksən.

---

## Laravel-də istifadəsi

### 1) Queue (iş növbəsi) vs Pub/Sub fərqi

Laravel-in əsas mesaj sistemi **Queue**-dur — bir job, bir handler. Pub/sub üçün (bir hadisə, çoxlu abunəçi) üç variant var:
1. Hər abunəçi üçün ayrıca job dispatch edirsən (daxili pub/sub əvəzləyicisi).
2. Broadcasting (real-time WebSocket clients üçün).
3. Xarici broker paketləri (Kafka, RabbitMQ fanout exchange).

### 2) User registered → email + analytics (Queue ilə)

```php
// app/Events/UserRegistered.php
class UserRegistered
{
    use Dispatchable, SerializesModels;
    public function __construct(public User $user) {}
}

// app/Listeners/SendWelcomeEmail.php
class SendWelcomeEmail implements ShouldQueue
{
    public string $queue = 'emails';
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeMail($event->user));
    }
}

// app/Listeners/TrackRegistration.php
class TrackRegistration implements ShouldQueue
{
    public string $queue = 'analytics';

    public function handle(UserRegistered $event, AnalyticsClient $analytics): void
    {
        $analytics->track('user_registered', [
            'user_id' => $event->user->id,
            'country' => $event->user->country,
        ]);
    }
}

// app/Providers/EventServiceProvider.php
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        TrackRegistration::class,
    ],
];

// Controller
UserRegistered::dispatch($user);
```

Bu **daxili** pub/sub-dir — hər listener ayrı job kimi queue-ya gedir. Amma əsl broker yoxdur, xarici servis bu hadisəni oxuya bilməz.

### 3) Kafka ilə pub/sub — `mateusjunges/laravel-kafka`

```bash
composer require mateusjunges/laravel-kafka
```

```php
// config/kafka.php
return [
    'brokers' => env('KAFKA_BROKERS', 'kafka-1:9092,kafka-2:9092'),
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP', 'user-service'),
    'auto_commit' => false,
    'sasl' => [
        'mechanisms' => env('KAFKA_SASL_MECHANISM'),
        'username' => env('KAFKA_USERNAME'),
        'password' => env('KAFKA_PASSWORD'),
    ],
];
```

Producer:

```php
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class RegistrationController extends Controller
{
    public function store(RegisterRequest $request, UserService $users): JsonResponse
    {
        $user = $users->register($request->validated());

        Kafka::publish('kafka-1:9092')
            ->onTopic('user.registered')
            ->withKafkaKey((string) $user->id)
            ->withBodyKey('user_id', $user->id)
            ->withBodyKey('email', $user->email)
            ->withBodyKey('country', $user->country)
            ->withBodyKey('ts', now()->toIso8601String())
            ->withHeaders(['traceId' => request()->header('X-Trace-Id')])
            ->send();

        return response()->json(UserResource::make($user), 201);
    }
}
```

Consumer (email service):

```php
// app/Console/Commands/ConsumeUserRegistered.php
class ConsumeUserRegistered extends Command
{
    protected $signature = 'kafka:consume-user-registered';

    public function handle(): int
    {
        $consumer = Kafka::createConsumer(['user.registered'])
            ->withConsumerGroupId('email-service')
            ->withAutoCommit(false)
            ->withMaxMessages(0)
            ->withHandler(function (\Junges\Kafka\Contracts\ConsumerMessage $message) {
                $body = $message->getBody();
                Mail::to($body['email'])->send(new WelcomeMail($body));
            })
            ->withMaxCommitRetries(3)
            ->build();

        $consumer->consume();
        return self::SUCCESS;
    }
}
```

Analytics service ayrıca command + ayrıca consumer group:

```php
$consumer = Kafka::createConsumer(['user.registered'])
    ->withConsumerGroupId('analytics-service')
    ->withHandler(fn ($m) => app(AnalyticsClient::class)->track('user_registered', $m->getBody()))
    ->build();
```

Hər iki consumer eyni topic-dən oxuyur, amma ayrı group-dadır — hər biri öz surətini alır. Bu, Spring Cloud Stream-dəki consumer group məntiqinin eynidir.

### 4) RabbitMQ paketi

```bash
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

```php
// config/queue.php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'hosts' => [[
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => 5672,
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => '/',
    ]],
    'queue' => env('RABBITMQ_QUEUE', 'default'),
    'options' => [
        'exchange' => [
            'name' => 'user.registered',
            'declare' => true,
            'type' => 'fanout',
        ],
    ],
],
```

Fanout exchange — bir mesaj çoxlu queue-a. Email və analytics ayrı queue-lara bind edilir, ayrı worker-lər consume edir.

### 5) AWS SNS + SQS fan-out

```php
use Aws\Sns\SnsClient;

class RegistrationController extends Controller
{
    public function store(RegisterRequest $request, SnsClient $sns): JsonResponse
    {
        $user = User::create($request->validated());

        $sns->publish([
            'TopicArn' => config('aws.sns.user_registered_arn'),
            'Message' => json_encode([
                'user_id' => $user->id,
                'email' => $user->email,
                'country' => $user->country,
            ]),
            'MessageAttributes' => [
                'event_type' => ['DataType' => 'String', 'StringValue' => 'user.registered'],
            ],
        ]);

        return response()->json($user, 201);
    }
}
```

SNS → iki SQS queue-a subscribe edilir — biri email, biri analytics. Laravel-in standart `sqs` queue driver-i SQS-dən oxuyur.

### 6) Broadcasting — WebSocket pub/sub

Real-time brauzer clients üçün:

```php
class UserRegistered implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'user.registered';
    }
}
```

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
```

Bu server-to-server pub/sub üçün yox, server-to-browser üçündür. Kafka/RabbitMQ-nin yerini tutmur.

### 7) Horizon ilə queue dashboard

```bash
composer require laravel/horizon
php artisan horizon:install
```

```php
// config/horizon.php
'environments' => [
    'production' => [
        'user-events-supervisor' => [
            'connection' => 'redis',
            'queue' => ['emails', 'analytics'],
            'balance' => 'auto',
            'maxProcesses' => 10,
            'memory' => 256,
            'timeout' => 60,
        ],
    ],
],
```

Horizon Laravel queue üçündür, Kafka consumer-ləri Horizon-da görünmür — onlar üçün `supervisord` və ya Kubernetes Deployment istifadə edilir.

### 8) Dead letter — `failed_jobs` və `failed()`

```php
class SendWelcomeEmail implements ShouldQueue
{
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeMail($event->user));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('welcome email permanently failed', [
            'user' => $this->user->id,
            'exception' => $e->getMessage(),
        ]);
    }
}
```

Kafka paketində DLQ-nu manual yazırsan — handler-də try/catch edib ayrı topic-ə göndərirsən.

---

## Əsas fərqlər

| Xüsusiyyət | Spring Cloud Stream | Laravel |
|---|---|---|
| Broker abstraction | Var (binder) | Yoxdur |
| Dəstəklənən broker | Kafka, RabbitMQ, Pulsar, Kinesis, Solace | Redis, SQS, DB, Beanstalkd; xarici paketlə Kafka/RabbitMQ |
| Pub/sub model | First-class (consumer group) | Daxili — Listener, xarici — paket |
| Manual publish API | `StreamBridge.send()` | `Event::dispatch()` və ya paket API |
| Functional model | `Consumer`, `Function`, `Supplier` bean | `Listener` class |
| Partitioning | Config ilə (`partition-key-expression`) | Paket API-siylə manual |
| DLQ | Config ilə enable | `failed_jobs` və ya manual |
| Retry + backoff | Config property | `$tries`, `$backoff` array |
| Schema Registry | Avro/Proto first-class | Manual JSON; paketlə Avro |
| Stateful streams | Kafka Streams binder | Yoxdur |
| Serverless | Spring Cloud Function adapter | Yoxdur |
| WebSocket | Yoxdur (ayrıca Spring WebSocket) | Broadcasting (Pusher/Reverb) |
| Dashboard | Micrometer + Prometheus | Horizon (yalnız Redis queue) |

---

## Niyə belə fərqlər var?

**Spring-in enterprise kökləri.** Java dünyasında JMS standartı (1998-ci ildən), MDB (Message-Driven Bean), Spring Integration var idi. Spring Cloud Stream bunların üstündə pillə kimi qoyulub — məqsəd "broker-dan asılılığı kodda sıfırlamaq".

**Laravel queue-first yanaşması.** PHP-nin request-per-process modeli queue-ya ideal uyğun gəlir — hər job ayrıca proses kimi icra olunur. Laravel əsasən monolit web app-lər üçün qurulub, həmin monolitdə daxili pub/sub kifayət edir. Microservices + broker hadisəsi Laravel üçün ikinci dərəcəlidir.

**Binder dəyəri nə zaman var?** Sənin şirkətin Kafka-dan RabbitMQ-ya və ya əksinə keçirsə, Spring Cloud Stream 2 asılılıq dəyişikliyi ilə işi həll edir. Laravel-də Kafka paketindən RabbitMQ paketinə keçmək bütün producer/consumer kodunu yenidən yazmaq deməkdir.

**Functional model seçimi.** Spring Boot 3 `Consumer<Message>` bean-lərinə keçdi ki, eyni kod Lambda-da da işləsin. Laravel-də bu ideya yoxdur — job class + listener class ayrı paradiqmdır.

**Partitioning və sıralılıq.** Kafka-da partition key ilə "eyni user-in event-ləri eyni partisiyada" qanunu Spring Cloud Stream-də bir property-dir (`partition-key-expression: payload.userId`). Laravel Kafka paketində `withKafkaKey()` metodu ilə manual edilir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Cloud Stream-də:**
- Binder abstraction — broker kodu toxunmadan dəyişir.
- Functional bean model (Consumer/Function/Supplier).
- `StreamBridge` universal publish API.
- Config-driven partitioning və DLQ.
- Kafka Streams binder ilə stateful stream processing.
- Spring Cloud Function adapter ilə Lambda-ya eyni kodu push etmək.
- Schema Registry (Confluent) inteqrasiyası.
- `application.yml` ilə retry/backoff/concurrency konfiq.

**Yalnız Laravel-də:**
- `Event::dispatch()` ilə daxili pub/sub (sadə listener modeli).
- Horizon dashboard (yalnız Redis queue üçün).
- Broadcasting — Pusher/Reverb/Ably ilə brauzerə pub/sub.
- `ShouldQueue` interface ilə async listener-ə çevirmək.
- `failed_jobs` cədvəli + Horizon UI-da retry button.
- Job middleware (`RateLimited`, `WithoutOverlapping`).

---

## Best Practices

- **Idempotency**: consumer həmişə idempotent olmalıdır. Eyni mesaj 2 dəfə gəlsə (at-least-once delivery), iki dəfə email göndərmə — `processed_events` cədvəlində message_id saxla.
- **Consumer group ayırmaq**: email service və analytics service ayrı consumer group-dadır. Eyni group olsa, biri digərinin mesajını "udar".
- **Partition key seçimi**: user-level sıralılıq istəyirsənsə `userId`, order-level `orderId`. Səhv key partition-ları balanssız edir.
- **DLQ mütləq**: 5 retry-dan sonra mesajı DLQ-ya at, manual yoxla. Sonsuz retry istehsalı blok edir.
- **Schema evolution**: yeni field həmişə optional əlavə et; məcburi field silmə. Avro/Proto istifadə edirsənsə Schema Registry versiya yoxlaması qoysun.
- **Spring-də**: functional model seç (Consumer/Function bean), `@StreamListener` köhnədir.
- **Laravel-də**: Kafka/RabbitMQ istifadə edirsənsə consumer prosesini `supervisord` və ya K8s ilə saxla, `php artisan` command-ı həmişə işlədir.
- **Header propagation**: `traceId` və `userId` header-ləri broker mesajına da əlavə et ki, distributed tracing qırılmasın.
- **Backpressure**: consumer yavaş olsa, concurrency-ni artırmadan əvvəl downstream (DB, SMTP) limiti yoxla.

---

## Yekun

Spring Cloud Stream messaging-i **deklarativ** edir — sən "bu bean user.registered-dən oxusun" yazırsan, binder və config qalanını həll edir. Kafka-dan RabbitMQ-ya keçid bir pom.xml dəyişikliyidir. Bu, broker-agnostic microservice arxitekturası üçün nadir hədiyyədir.

Laravel-də bu cür universal abstraction yoxdur. Daxili Event sistemi monolit-də çox sadədir — `ShouldQueue` əlavə edirsən, listener async olur. Microservices səviyyəsinə çıxanda isə xarici paket (laravel-kafka, laravel-queue-rabbitmq) götürürsən və həmin paketin öz API-sinə bağlı qalırsan.

Seçim tətbiqin miqyasından asılıdır: tək monolit Laravel + daxili Event kifayətdir. Onlarla microservice + çoxlu broker — Spring Cloud Stream daha az texniki borc yaradır.
