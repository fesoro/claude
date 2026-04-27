# Kafka vs RabbitMQ (Lead)

## Mündəricat
1. [Fundamental Fərq: Log vs Queue](#fundamental-fərq-log-vs-queue)
2. [Kafka Arxitekturası](#kafka-arxitekturası)
3. [RabbitMQ Arxitekturası](#rabbitmq-arxitekturası)
4. [Müqayisə Cədvəli](#müqayisə-cədvəli)
5. [Nə vaxt hansını seçmək](#nə-vaxt-hansını-seçmək)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Fundamental Fərq: Log vs Queue

```
RabbitMQ — Message Queue (Broker):
  Mesaj gəlir → işlənir → SİLİNİR
  "Poçt qutusu" — məktub oxuyanda qutudan çıxır

  Producer → [Queue] → Consumer → ACK → Deleted

Kafka — Distributed Log (Event Stream):
  Mesaj gəlir → disk-ə yazılır → MÜDDƏTƏ QƏDƏR SAXLANILIR
  "Journal" — yazılan hər şey qalır, istənilən vaxt oxuna bilər

  Producer → [Partition Log] → Consumer (offset-i özü idarə edir)
  Mesaj silinmir — retention period sonra (7 gün default)

Bu fərq hər şeyi dəyişdirir:
  RabbitMQ: "Bu task işləndi, unut"
  Kafka:    "Bu event baş verdi, istəyən oxuya bilər"
```

---

## Kafka Arxitekturası

```
Topic → Partition-lara bölünür → Broker-larda saxlanılır.

Topic: "orders"
  ┌─────────────────────────────────────────────────────┐
  │  Partition 0:  [msg1][msg3][msg5][msg7]...          │
  │  Partition 1:  [msg2][msg4][msg6][msg8]...          │
  │  Partition 2:  [msg0][msg9][msg10]...               │
  └─────────────────────────────────────────────────────┘

Partitions:
  - Parallelism vahidi (hər partition bir consumer-a)
  - Mesajlar partition daxilında ordered
  - Partitions arası order guarantee yoxdur
  - Partition sayı = max parallellik

Consumer Group:
  Consumer Group "order-processors" (3 consumer):
    Consumer 1 → Partition 0
    Consumer 2 → Partition 1
    Consumer 3 → Partition 2
  Hər partition yalnız bir consumer-a aid
  Consumer sayı > partition sayı → artıq consumer idle qalır

Offset:
  Consumer özü nə qədər oxuduğunu izləyir.
  offset = "partition-da hansı mesaja qədər oxudum"
  Crash olub restart edərsə → son committed offset-dən davam et

Replication:
  Hər partition N replika-ya kopyalanır.
  Leader → yazılır/oxunur
  Follower → replika (failover üçün)
```

---

## RabbitMQ Arxitekturası

```
Exchange → Routing → Queue → Consumer

Exchange növləri:
  Direct:  Routing key tam uyğun
  Topic:   Wildcard routing key (*.order.*, order.#)
  Fanout:  Bütün queue-lara göndər
  Headers: Message header-ə görə route

Producer → Exchange → (Binding Key) → Queue(s) → Consumer(s)

ACK/NACK:
  Consumer mesajı işlədikdə ACK göndərir.
  ACK gələnə qədər mesaj queue-da "unacked" olaraq qalır.
  NACK: "işlənə bilmədim, qaytar" (requeue) ya at (DLQ)

Dead Letter Queue:
  Rədd edilmiş, TTL keçmiş, ya da max retry dolmuş mesajlar DLQ-ya gedir.
  Monitoring üçün vacib!

Prefetch:
  channel.basicQos(prefetchCount: 1)
  "Mənə eyni anda max 1 mesaj ver"
  → Worker işləyərkən yeni mesaj gəlmir → fair dispatch

Persistence:
  Mesajlar disk-ə yazıla bilər (durable queue + persistent message)
  Broker restart olduqda mesajlar qalır
```

---

## Müqayisə Cədvəli

```
┌──────────────────────┬─────────────────────┬─────────────────────┐
│                      │     Kafka           │     RabbitMQ        │
├──────────────────────┼─────────────────────┼─────────────────────┤
│ Model                │ Pull (consumer çəkir)│ Push (broker göndər)│
│ Message retention    │ Saatlarca/günlərcə  │ ACK-dən sonra silin │
│ Ordering             │ Partition daxilında │ Queue daxilında      │
│ Throughput           │ Çox yüksək (M/s)    │ Yüksək (100K/s)    │
│ Routing              │ Topic + partition   │ Güclü (exchange)    │
│ Consumer tracking    │ Offset (consumer)   │ ACK (broker)        │
│ Replay               │ ✅ Əvvəlki mesajlar │ ❌ Artıq silinib    │
│ Multi-consumer       │ Consumer groups     │ Competing consumers │
│ Message TTL          │ Retention policy    │ Per-message/queue   │
│ Protocol             │ Kafka binary        │ AMQP                │
│ Complexity           │ Yüksək              │ Orta                │
│ Use case             │ Event streaming     │ Task queue          │
└──────────────────────┴─────────────────────┴─────────────────────┘

Log compaction (Kafka):
  Hər key üçün yalnız son dəyəri saxla.
  "Bazanın cari vəziyyəti" event stream-dən bərpa edilə bilər.
  Köhnə event-lər silinir, son event qalır.
```

---

## Nə vaxt hansını seçmək

```
Kafka seçin:
  ✓ Event streaming / event log
  ✓ Real-time analytics (Flink, Spark Streaming)
  ✓ Audit log (hamı nə baş verdiyini bilməlidir)
  ✓ Çox consumer eyni dataı oxumalıdır
  ✓ Event replay lazımdır (yeni service köhnə event-ləri işləmək istəyir)
  ✓ Yüksək throughput (milyonlarla mesaj/saniyə)
  ✓ Microservice event bus
  
  Nümunələr:
    User activity tracking
    Financial transaction log
    Real-time recommendation engine
    Change Data Capture (CDC)

RabbitMQ seçin:
  ✓ Task queue (background jobs)
  ✓ Mürəkkəb routing (topic exchange, headers)
  ✓ Request-reply pattern
  ✓ Mesaj prioriteti lazımdır
  ✓ Sadə setup (Kafka-dan daha az operational)
  ✓ Per-message TTL, DLQ lazımdır
  
  Nümunələr:
    Email/SMS göndərmə
    Image resizing jobs
    Payment processing tasks
    Notification dispatcher
```

---

## PHP İmplementasiyası

```php
<?php
// Kafka Consumer (rdkafka extension)
$conf = new RdKafka\Conf();
$conf->set('group.id', 'order-processors');
$conf->set('metadata.broker.list', 'kafka:9092');
$conf->set('auto.offset.reset', 'earliest');

// Offset-i avtomatik commit etmə — əl ilə idarə et
$conf->set('enable.auto.commit', 'false');

$consumer = new RdKafka\KafkaConsumer($conf);
$consumer->subscribe(['orders']);

while (true) {
    $message = $consumer->consume(1000); // 1s timeout

    if ($message === null) continue;

    switch ($message->err) {
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            try {
                $payload = json_decode($message->payload, true);
                $this->processOrder($payload);

                // Uğurlu olduqda offset commit et (at-least-once)
                $consumer->commit($message);
            } catch (\Throwable $e) {
                $this->logger->error('Order processing failed', [
                    'offset' => $message->offset,
                    'error'  => $e->getMessage(),
                ]);
                // Offset commit etmirik → restart-da yenidən oxunar
            }
            break;

        case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            // Partition-ın sonuna çatdıq, yeni mesaj gözlə
            break;

        default:
            $this->logger->error('Kafka error: ' . $message->errstr());
    }
}
```

```php
<?php
// Consumer Group — partition assignment göstərmək
$conf->set('group.id', 'analytics-consumers');

// Hər consumer group eyni topic-u müstəqil oxuyur
// "order-processors" group: orders işləyir
// "analytics-consumers" group: eyni mesajları analytics üçün oxuyur
// Kafka-da replay imkanı: hər iki group müstəqil offset saxlayır

// Offset başa reset et (replay):
// kafka-consumer-groups.sh --bootstrap-server kafka:9092 \
//   --group analytics-consumers \
//   --topic orders \
//   --reset-offsets --to-earliest --execute
```

---

## İntervyu Sualları

- Kafka-nın "log" modeli RabbitMQ queue-dan nəylə fərqlənir?
- Kafka partition sayı niyə consumer sayından çox olmamalıdır?
- Consumer offset Kafka-da nədir? Niyə broker deyil, consumer saxlayır?
- RabbitMQ-da ACK olmayan mesaj nə olur?
- Yeni microservice-in köhnə event-ləri işləməsi lazımdırsa hansını seçərdiniz?
- Kafka-da log compaction nədir? Nə vaxt lazımdır?
- Kafka-nın throughput-u RabbitMQ-dan niyə yüksəkdir?
