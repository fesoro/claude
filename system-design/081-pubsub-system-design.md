# Pub/Sub System Design (Lead)

## İcmal

Pub/Sub (Publish-Subscribe) mesajlaşma modeldir ki, burada publisher mesajı **topic**-ə göndərir,
bir və ya bir neçə subscriber isə həmin topic-dəki **subscription**-dan mesajı oxuyur. Publisher
subscriber-in kim olduğunu bilmir - bu isə producer və consumer-i tam decouple edir.

Bu fayl 05 (Message Queues) və 11 (Event-Driven Architecture) ilə complementary-dir. Burada
diqqət **managed pub/sub service** (Google Pub/Sub, AWS SNS, Kafka-as-PubSub) dizaynına
yönəldilib - yəni biz belə bir service-i necə qurarıq.

```
                 ┌─────────────────────────────────┐
                 │           TOPIC: orders          │
                 └─────────────────────────────────┘
                     ▲                 │       │
   publish event     │                 ▼       ▼
                     │        ┌─────────────┐ ┌─────────────┐
  ┌──────────┐       │        │ Sub: email  │ │ Sub: search │
  │Publisher │───────┘        │ (push)      │ │ (pull)      │
  │(OrderSvc)│                └─────────────┘ └─────────────┘
  └──────────┘                       │              │
                                     ▼              ▼
                              EmailWorker     SearchIndexer
```


## Niyə Vacibdir

Kafka, Google Pub/Sub, AWS SNS/SQS arasında seçim delivery semantics, ordering, replay, fan-out tələblərinə görə dəyişir. At-least-once vs exactly-once — production sisteminin reliability-sini müəyyən edir. Pub/Sub olmadan event-driven arxitektura qurulmur.

## Queue vs Pub/Sub vs Hybrid (Comparison)

```
Queue (SQS, RabbitMQ classic):
  [Producer] -> [Queue] -> Consumer A (yalnız biri mesajı alır)
                        \-> Consumer B
  Competing consumers - hər mesaj yalnız BIR consumer tərəfindən emal olunur.

Pub/Sub (SNS, Google Pub/Sub fan-out):
  [Publisher] -> [Topic] -> Sub A -> Consumer A (mesajı alır)
                         -> Sub B -> Consumer B (eyni mesajı alır)
                         -> Sub C -> Consumer C (eyni mesajı alır)
  Broadcast - hər subscription bütün mesajları görür.

Hybrid (Kafka consumer groups):
  [Producer] -> [Topic] -> Consumer Group X (A və B yarı-yarıya böləcək)
                        -> Consumer Group Y (hamısını görəcək)
  Subscription daxilində competing consumers + topic səviyyəsində fan-out.
```

Qısa yadda saxlamaq üçün: **Queue = work sharing, Pub/Sub = event broadcasting**.

## Tələblər

- Publisher API - `Publish(topic, message, attributes)` → `message_id`
- Subscribe API - topic-ə subscription yarat, push/pull mode seç
- Consumer API - `Pull()` və ya `Push` callback; `Acknowledge(ack_id)`
- Filtering - subscription səviyyəsində attribute-lara görə
- Dead Letter Topic - neçə dəfə redeliver-dən sonra poison mesajı harasa göndər

## Non-Funksional Tələblər (Non-Functional)

- At-least-once delivery (default) - mesaj itməməlidir
- High throughput - milyonlarla msg/sec
- Low latency - p99 publish-to-delivery < 100ms
- Durable - disk-ə replicate olunur, node düşsə belə itmir
- Ordering (optional) - ordering_key ilə per-key qayda
- Ack deadline - consumer ack etməsə, mesaj yenidən çatdırılır

## Əsas Konseptlər (Core Concepts)

- **Topic** - named logical channel (məs. `orders`)
- **Subscription** - topic-dən oxumaq üçün state (cursor, config)
- **Message** - payload + attributes (key-value)
- **ack_id** - hər pull-da unikal, ack üçün istifadə olunur
- **ack deadline** - default 10-60s, consumer bu müddətdə ack etməlidir
- **ordering_key** - eyni key-li mesajlar ardıcıl çatdırılır

## Arxitektura

```
  Publishers
      │
      ▼
  [ LB ] -> [ Publish API ] -> [ Durable Log (partitioned, replicated) ]
                                         │
                             ┌───────────┴───────────┐
                             ▼                       ▼
                        Delivery Pusher           Pull API
                        (push subscribers)        (pull subscribers)
```

- **Ingest tier**: stateless, load balancer arxasında
- **Durable log**: Kafka-oxşar partitioned log, append-only, N replica
- **Subscription state**: hər sub-ın cursor/offset-i saxlanılır
- **Delivery layer**: push üçün aktif göndərir, pull üçün API serve edir

## Delivery Modelləri (Delivery Models)

### Push Delivery

Provider HTTPS endpoint-ə POST göndərir, endpoint 200 OK qaytarmalıdır.

```
 PubSub ──POST /webhook──> Your API ──200 OK──> (ack)
```

- Pros: minimal infra, real-time
- Cons: endpoint down olsa retry storm, back-pressure çətin
- Retry: exponential backoff (1s, 2s, 4s, 8s...)

### Pull Delivery

Consumer `ReceiveMessage` API çağırır, özü tempo təyin edir.

```
 Worker ──Pull(max=100)──> PubSub ──[msg1..N]──> emal ──Ack(ack_id)──>
```

- Pros: client rate-i idarə edir, batch-friendly
- Cons: worker həmişə running olmalıdır
- Use case: heavy processing, data pipeline

## Ordering Guarantees (Ordering)

```
1. No ordering (default fan-out, SNS) - istənilən qaydada çatır
2. Per-key ordering (Google ordering_key, Kafka partition key):
   userA üçün ev1→ev2 ardıcıl, userB paralel
3. Global ordering - single partition → throughput limitli, nadirən lazımdır
```

## Delivery Semantics

- **At-most-once** - ack-dən əvvəl mesaj silinir; network fail olsa itə bilər. Metrics, logs üçün.
- **At-least-once** (default) - ack gələnə qədər saxlanılır; retry zamanı duplicate ola bilər. Consumer idempotent olmalıdır (bax fayl 28).
- **Exactly-once** - Kafka transactional producer + idempotent consumer offset-i. Bahalıdır, yalnız kritik use case-də.

## Ack və Redelivery (Ack and Redelivery)

```
Mesaj deliver olunur ──> ack deadline (30s) başlayır
  │
  ├── 30s-dən əvvəl Ack(ack_id) → mesaj silinir
  ├── ModifyAckDeadline(extend) → deadline uzadılır
  └── Deadline keçir → mesaj yenidən queue-ya qayıdır

max_delivery_attempts (məs. 5) ötəndən sonra
  → Dead Letter Topic-ə göndərilir
```

Dead Letter Topic (DLT / DLQ) - "poison message"-lar üçün (bax fayl 05). Operator sonra
manual review edib retry edə bilər.

## Backlog Management (Flow Control)

Consumer slow olanda backlog böyüyür. Həll:
- **max_outstanding_messages** - client-də eyni anda neçə unacked mesaj ola bilər
- **max_outstanding_bytes** - RAM limit
- **Flow control** - limit doldusa, client pull etmir

```php
// Google Pub/Sub flow control
$subscription->pull([
    'maxMessages' => 100,
    'returnImmediately' => false, // long polling
]);
```

## Subscription Filtering (Server-Side Filter)

Subscription yaradanda filter expression qeyd et - yalnız uyğun mesajlar çatdırılır.

```
Topic: user-events
  Subscription "premium-only" filter: attributes.tier = "premium"
  Subscription "all-events": filter yoxdur

Publish(user-events, {...}, {tier: "free"})     → yalnız "all-events" alır
Publish(user-events, {...}, {tier: "premium"})  → hər ikisi alır
```

Üstünlük: client-də filter etmirsən, bandwidth və CPU qənaəti.

## Scaling (Horizontal Scaling)

```
Topic "orders" (10 partition, shard by hash(user_id))
  Subscription "email-service":
    Worker 1 → P0,P1,P2   Worker 2 → P3,P4,P5   Worker 3 → P6..P9
```

Partition count = max parallelism. Worker crash olsa, coordinator rebalance edir.

## Fan-Out Math (Fan-Out Math)

```
1 publish × N subscription = N delivery attempt
Storage yalnız 1 dəfə yazılır (shared durable log).

Məsələn: 100k msg/sec × 5 sub = 500k delivery/sec, storage write 100k/sec.
```

Pub/Sub-ın gücü budur - publish cost sabit, fan-out ucuz.

## Durability (Durability)

- Mesaj 3 node-a replicate olunur (quorum write)
- Leader failure → Raft/ISR ilə yeni leader seçilir
- Retention: məs. Google Pub/Sub default 7 gün; Kafka config-dən asılıdır

## Pricing Model (Managed Service)

- Per-message cost (məs. $0.40 / million)
- Throughput (ingestion + delivery bytes)
- Retention storage (GB × gün)
- **Pub/Sub Lite** - zonal, ucuz, ordering feature-ləri azdır

## Real Use Case - Analytics Fan-Out + SNS/SQS Pattern

```
                      ┌─ Sub: analytics ─> BigQuery
  OrderPlaced event   ├─ Sub: cache-inv ─> Redis DEL
  ────────────────> ──┼─ Sub: indexer   ─> Elasticsearch
  Topic "orders"      └─ Sub: ml-features ─> Feature Store

  Bir publish → 4 sistem update. Storage 1 dəfə yazılır.
```

AWS SNS + SQS pattern: SNS topic fan-out edir hər service-in öz SQS queue-suna.
Hər service öz retry, DLQ, throughput-unu idarə edir; bir service down olsa
digərlərinə təsir etmir.

```
  Publisher -> SNS "orders" -> SQS q1 (email) -> EmailSvc
                            -> SQS q2 (billing) -> BillingSvc
                            -> SQS q3 (inventory) -> InvSvc
```

## Laravel/PHP Nümunələri (Laravel/PHP Examples)

### Google Pub/Sub - Publish

```php
use Google\Cloud\PubSub\PubSubClient;

class OrderPublisher
{
    public function publish(Order $order): string
    {
        $pubsub = new PubSubClient(['projectId' => config('services.gcp.project')]);
        $topic = $pubsub->topic('orders');

        return $topic->publish([
            'data' => json_encode([
                'order_id' => $order->id,
                'user_id'  => $order->user_id,
            ]),
            'attributes' => [
                'event_type' => 'order.placed',
                'tier'       => $order->user->tier, // server-side filter üçün
            ],
            'orderingKey' => "user-{$order->user_id}", // per-user ordering
        ]);
    }
}
```

### Google Pub/Sub - Pull Consumer

```php
class ConsumeOrdersCommand extends Command
{
    protected $signature = 'pubsub:consume-orders';

    public function handle(): void
    {
        $pubsub = new PubSubClient(['projectId' => config('services.gcp.project')]);
        $sub = $pubsub->subscription('email-service');

        while (true) {
            foreach ($sub->pull(['maxMessages' => 100]) as $msg) {
                try {
                    EmailJob::dispatch(json_decode($msg->data(), true));
                    $sub->acknowledge($msg); // DB commit-dən sonra ack
                } catch (\Throwable $e) {
                    Log::error('pubsub fail', ['err' => $e->getMessage()]);
                    // ack yox - redelivery olacaq
                }
            }
        }
    }
}
```

### AWS SNS - Publish (fan-out to SQS queues)

```php
use Aws\Sns\SnsClient;

$sns = new SnsClient(['region' => 'us-east-1', 'version' => 'latest']);
$sns->publish([
    'TopicArn' => config('services.sns.orders_topic'),
    'Message'  => json_encode(['order_id' => $order->id]),
    'MessageAttributes' => [
        'event_type' => ['DataType' => 'String', 'StringValue' => 'order.placed'],
    ],
]);
```

### Kafka as Pub/Sub (consumer group per service)

```php
// enqueue/enqueue-dev - hər service öz group.id-si olur
$factory = new RdKafkaConnectionFactory([
    'global' => [
        'group.id'             => 'email-service', // başqa service = başqa group
        'metadata.broker.list' => 'kafka:9092',
    ],
]);
$consumer = $factory->createContext()->createConsumer(
    $factory->createContext()->createQueue('orders') // Kafka topic
);
while ($msg = $consumer->receive(5000)) { $consumer->acknowledge($msg); }
```

## Platformaların Müqayisəsi (Platform Comparison)

| Platform | Model | Ordering | Retention | Güclü cəhət |
|----------|-------|----------|-----------|-------------|
| Kafka | Streaming-first | Per-partition | Konfiqurasiyadan asılı | High throughput, replay |
| Google Pub/Sub | Managed pub/sub | ordering_key | 7 gün default | Fully managed, global |
| AWS SNS | Fan-out pub/sub | FIFO topic-lə | Retention yoxdur | SQS inteqrasiyası |
| NATS + JetStream | Lightweight pub/sub | Stream-lə | Konfiqurasiyadan | Aşağı latency |
| Apache Pulsar | Unified (queue+stream) | Var | Tiered storage | Geo-replication |
| Redis Streams | In-memory stream | Stream içində | Memory-limitli | Sadəlik, sürət |

## Dizayn Pitfalls (Design Pitfalls)

- **Unbounded subscription creation** - silinməyən sub-lar lag + storage partladır
- **No DLQ** - poison mesaj sonsuz retry, throughput blok olur
- **No backoff on retry** - retry storm → öz sistemin çökür
- **Ordering misuse** - `ordering_key` + non-idempotent consumer = duplicate + wrong order
- **Commit-dən əvvəl ack** - DB-yə yazmadan ack → mesaj itir
- **Giant messages** - 1MB+ payload bahalı. Əvəzinə S3-də saxla, link göndər (claim check)

## Praktik Tapşırıqlar

**1. Queue və Pub/Sub arasında fərq nədir?**
Queue: hər mesaj yalnız BIR consumer-ə (competing consumers, iş bölgüsü). Pub/Sub:
hər subscription topic-dəki BÜTÜN mesajları görür (fan-out). Kafka hibriddir -
topic səviyyəsində fan-out, consumer group daxilində competing.

**2. At-least-once-da duplicate-ləri necə idarə edirsən?**
Consumer idempotent olmalıdır. Praktikada: `message_id`-ni `processed_events`
cədvəlinə `INSERT ... ON CONFLICT DO NOTHING` ilə yazıram, varsa skip edirəm.
Bax fayl 28 (Idempotency).

**3. Push vs Pull - hansını seçərsən?**
Push: HTTPS endpoint var, low volume, real-time. Provider auto-retry edir.
Pull: heavy processing, batch, client rate-i idarə etsin. Laravel worker-ləri
adətən pull modelində olur.

**4. Ordering lazım olanda nə edirsən?**
Global ordering çox bahadır (single partition). Adətən per-key ordering kifayətdir:
Google Pub/Sub-da `ordering_key`, Kafka-da partition key. Eyni user-ın event-ləri
ardıcıl, fərqli user-lər paralel.

**5. Dead Letter Topic nə üçün lazımdır?**
Poison mesaj (format səhv, bug-a səbəb olan) sonsuz retry throughput-u blok edir.
`max_delivery_attempts` (5-10) keçəndən sonra DLT-yə gedir. Operator review edir,
bug düzəldir, replay edir.

**6. 1M msg/sec publish edirik, necə scale edirsən?**
Topic-i 100 partition-a bölürəm. Publisher `hash(user_id) % N` ilə shard edir.
Consumer group - hər partition bir consumer-ə assign olunur. Ingest tier
stateless, horizontal scale. Replication factor 3.

**7. SNS + SQS pattern nə vaxt?**
AWS-də fan-out istəyəndə. SNS topic hər service-in öz SQS queue-suna göndərir.
Hər service öz retry/DLQ/throughput-unu idarə edir; bir service down olsa
digərlərinə təsir etmir, mesaj queue-da gözləyir.

**8. Managed Pub/Sub vs self-hosted Kafka?**
Managed (SNS, Google Pub/Sub): operasional yük yoxdur, auto-scale, SLA, amma
per-message bahalı. Kafka: yüksək throughput-da ucuz, retention/replay güclü,
amma broker/monitoring saxlamaq lazım. Startup managed başlayır, volume
böyüyəndə Kafka-ya keçir.

## Praktik Baxış

- **Idempotency məcburidir** - at-least-once default, consumer duplicate-ə hazır olmalıdır
- **Ack-i emaldan sonra et** - DB commit-dən sonra ack, əvvəl yox
- **Dead Letter Topic həmişə qur** - `max_delivery_attempts` 5-10 tipik
- **Exponential backoff + jitter** - retry storm-un qarşısını alır
- **Server-side filter** - bandwidth və CPU qənaət edir
- **ordering_key-i ağıllı seç** - cardinality yüksək olsun, parallelism itməsin
- **Monitoring**: publish rate, ack latency, oldest unacked, DLT count
- **Payload kiçik** - 1MB+ data S3-də saxla, topic-də reference (claim check)
- **Schema versioning** - `schema_version` əlavə et, breaking change olmasın
- **Unused sub-ları sil** - backlog + xərc yaradır
- **Region awareness** - locally publish, globally consume


## Əlaqəli Mövzular

- [Message Queues](05-message-queues.md) — pub/sub-un əsası
- [Event-Driven Architecture](11-event-driven-architecture.md) — pub/sub konteksti
- [Stream Processing](54-stream-processing.md) — Kafka Streams
- [Webhook Delivery](82-webhook-delivery-system.md) — outbound event fan-out
- [Notification System](13-notification-system.md) — notification fan-out
