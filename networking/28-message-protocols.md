# Message Protocols - AMQP, MQTT, STOMP (Middle)

## İcmal

Message protocols distributed system-lərdə komponentlər arasında asynchronous kommunikasiya üçün istifadə olunan standartlardır. Message broker (RabbitMQ, Kafka) vasitəsilə producer message göndərir, consumer isə alır. Decoupling, scalability, reliability təmin edir.

Əsas protokollar:
- **AMQP** (Advanced Message Queuing Protocol): RabbitMQ-nin native protokolu
- **MQTT** (Message Queuing Telemetry Transport): IoT üçün lightweight
- **STOMP** (Simple Text Oriented Messaging Protocol): Text-based, WebSocket üstündə
- **Kafka Protocol**: Binary, log-structured messaging

Serialization formatları:
- **JSON**: Human-readable, verbose
- **Protobuf**: Google-un binary, schema-based
- **Avro**: Apache, schema evolution dostluğu

```
Basic messaging flow:

Producer                 Message Broker              Consumer
   |                         |                          |
   |-- publish message ----> |                          |
   |                         |-- queue/topic -----------|
   |                         |<-- poll / push ----------|
   |                         |<-- ack -------------------|
```

## Niyə Vacibdir

Monolitik tətbiqdə belə, uzun sürən işləri (email göndərmək, hesabat yaratmaq, üçüncü tərəf API çağırışları) queue-a atmaq responsiveness-i artırır. Microservice arxitekturasında isə message broker servisləri decoupled saxlayır — bir servis down olsa digəri işləməyə davam edir. RabbitMQ Laravel ekosistemindəki ən çox istifadə edilən message broker-dir; Kafka isə yüksək həcmli event streaming-də standartdır.

## Əsas Anlayışlar

### 1. AMQP (RabbitMQ)

```
AMQP 0.9.1 — Full-featured message protocol.

Komponentlər:
  Producer  --> Exchange --> Queue(s) --> Consumer

Exchange types:

1. Direct Exchange
   Routing key exact match:
     routing_key="order.created" --> Queue "orders"

2. Fanout Exchange
   Broadcast — all bound queues:
     [exchange] --> [queue1, queue2, queue3]

3. Topic Exchange
   Pattern matching (wildcards):
     * = one word, # = zero or more words
     "order.*.created" matches "order.payment.created"
     "user.#" matches "user.login", "user.profile.updated"

4. Headers Exchange
   Match on headers, not routing key.

Flow example:
  1. Exchange declare: "orders" (topic)
  2. Queue declare: "new_orders"
  3. Bind queue to exchange: routing_key "order.created"
  4. Publish message: routing_key="order.created"
  5. Route to "new_orders" queue
  6. Consumer subscribes, processes, sends ACK

Features:
  - Acknowledgments (at-least-once delivery)
  - Persistent messages (disk)
  - Dead letter exchange (failed messages)
  - TTL (message expiration)
  - Priority queues
  - Publisher confirms
```

### 2. MQTT (IoT)

```
MQTT: Lightweight, publish-subscribe, IoT-oriented.
Binary header ~2 bytes (HTTP-a nisbətən çox kiçik).

Topic hierarchy:
  home/livingroom/temperature
  home/bedroom/light/state

Wildcards (subscribe only):
  +  = single level  (home/+/temperature)
  #  = multi-level   (home/#)

QoS levels:
  0 - At most once (fire and forget)
  1 - At least once (acknowledged delivery)
  2 - Exactly once (4-way handshake)

Features:
  - Lightweight (2-byte header)
  - Low bandwidth (sensors, mobile)
  - Retained messages (latest state available)
  - Last Will and Testament (LWT) — disconnect notification
  - Session persistence
```

### 3. STOMP

```
STOMP: Simple Text Oriented Messaging Protocol.
Human-readable, HTTP-like frames.

Frame structure:
  COMMAND
  header1:value1

  body^@

Example — Publish:
  SEND
  destination:/queue/orders
  content-type:application/json

  {"order_id":123}^@

Example — Subscribe:
  SUBSCRIBE
  id:sub-1
  destination:/queue/orders
  ack:client

  ^@

Use cases:
  - WebSocket over STOMP (browser messaging)
  - Simple integration, language-agnostic
```

### 4. Kafka Protocol

```
Kafka: Binary protocol, log-structured, high-throughput.

Topics and Partitions:

Topic "orders" with 3 partitions:
  Partition 0: [msg1, msg4, msg7, ...]
  Partition 1: [msg2, msg5, msg8, ...]
  Partition 2: [msg3, msg6, msg9, ...]

Offset: Monotonically increasing ID per partition

Consumer group:
  Multiple consumers, each gets a subset of partitions.
  Partition 0 -> Consumer A
  Partition 1 -> Consumer B
  Partition 2 -> Consumer C

Features:
  - Append-only log (very fast)
  - Retention (days/weeks)
  - Replay (re-consume old messages)
  - Exactly-once semantics
  - High throughput (millions/sec)
```

### Protocol Müqayisəsi

```
Protocol  | Type      | Header Size | Features              | Use Case
----------|-----------|-------------|----------------------|------------------
AMQP      | Binary    | Medium      | Rich (exchanges, DLX) | Enterprise msg
MQTT      | Binary    | 2 bytes     | Lightweight, QoS      | IoT, mobile
STOMP     | Text      | Medium      | Simple, HTTP-like     | WebSocket msg
Kafka     | Binary    | Medium      | High throughput, log  | Event streaming

Format    | Size      | Speed       | Schema    | Use Case
----------|-----------|-------------|-----------|----------
JSON      | Large     | Slow        | None      | REST APIs
Protobuf  | Small     | Fast        | Required  | gRPC, microservices
Avro      | Small     | Fast        | Required  | Kafka, big data
```

### At-most-once, At-least-once, Exactly-once

```
At-most-once: Message ya bir dəfə, ya da heç çatdırılmır.
  - Fast, no acknowledgments
  - Can lose messages
  - MQTT QoS 0

At-least-once: Message ən azı bir dəfə çatdırılır (duplicate mümkündür).
  - Acknowledgments required
  - Idempotent processing lazım
  - AMQP default, MQTT QoS 1

Exactly-once: Tam bir dəfə çatdırılır.
  - Ən çətin, performance cost
  - MQTT QoS 2, Kafka exactly-once
```

### Message Acknowledgment

```
Auto-ack (fire and forget):
  Consumer alır, broker dərhal queue-dan silir.
  Risk: Consumer crash olsa message itirir.

Manual ack:
  Consumer process edir, sonra ack göndərir.
  Broker ack alana kimi message-i saxlayır.
  Consumer crash olsa, message yenidən queue-ya qayıdır.

Nack (negative ack):
  Consumer "bunu process edə bilmədim" deyir.
  Requeue: yenidən bu consumer-ə göndərilir
  Dead letter: DLX-ə göndərilir
```

### Dead Letter Queue (DLQ)

```
Failed message-lər üçün ayrı queue:

Normal queue -> (N failures) -> Dead Letter Queue
                                      |
                                   Manual review
                                   Replay logic
                                   Alerting

Səbəblər:
  - Malformed message
  - Consumer persistent error
  - TTL expired
  - Queue overflow
```

### Idempotency

```
Duplicate message gəlsə, bir dəfə process olan kimi davran.

Method 1: Idempotency key
  {"order_id": "unique_uuid", ...}
  DB: UNIQUE(order_id)
  Duplicate insert fails -> skip

Method 2: Outbox pattern
  Atomic DB transaction:
    1. Insert business record
    2. Insert outbox message
  Separate process publishes outbox messages to broker

Method 3: Deduplication window
  Process message, mark ID in Redis (TTL)
  Next duplicate within window -> skip
```

## Praktik Baxış

**Trade-off-lar:**
- RabbitMQ — rich routing, complex workflows; Kafka — high throughput, event streaming, replay
- JSON — debug asan, amma 3-10x böyük; Protobuf — compact, fast, amma schema management
- At-least-once + idempotency praktikdə exactly-once-dan daha çox istifadə olunur

**Nə vaxt istifadə edilməməlidir:**
- Sadə request-response pattern üçün HTTP daha uyğundur (broker əlavə complexity yaradır)
- Real-time (<10ms) tələblər üçün broker latency-si yüksək ola bilər

**Anti-pattern-lər:**
- Auto-ack istifadə etmək — consumer crash-da message itirir
- DLQ konfigurasiya etməmək — failed message-lər görünməz olur
- Hər request üçün yeni connection açmaq — connection pooling istifadə edin
- JSON-u internal microservice kommunikasiyası üçün istifadə etmək (Protobuf/Avro daha uyğun)

## Nümunələr

### Ümumi Nümunə

Laravel Queue abstraction RabbitMQ üzərindən işləyə bilər. `ShouldQueue` interface-ini implement edən hər job avtomatik olaraq RabbitMQ queue-una göndərilə bilər. Kafka üçün ayrıca library lazımdır. Outbox pattern DB consistency-ni message publishing ilə birlikdə təmin edir.

### Kod Nümunəsi

**RabbitMQ ilə Laravel (php-amqplib):**

```php
// composer require php-amqplib/php-amqplib

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Producer
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel    = $connection->channel();

$channel->exchange_declare('orders', 'topic', false, true, false);

$msg = new AMQPMessage(
    json_encode(['order_id' => 123, 'amount' => 5000]),
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

$channel->basic_publish($msg, 'orders', 'order.created');

$channel->close();
$connection->close();

// Consumer
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel    = $connection->channel();

$channel->queue_declare('new_orders', false, true, false, false);
$channel->queue_bind('new_orders', 'orders', 'order.*');
$channel->basic_qos(null, 1, null); // prefetch count

$callback = function (AMQPMessage $msg) {
    $data = json_decode($msg->body, true);
    echo "Received order: {$data['order_id']}\n";

    try {
        processOrder($data);
        $msg->ack();
    } catch (\Exception $e) {
        $msg->nack(false, false); // dont requeue, go to DLQ
    }
};

$channel->basic_consume('new_orders', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
```

**Laravel Queue with RabbitMQ (vladimir-yuldashev package):**

```bash
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

```env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
```

```php
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle()
    {
        $order = Order::find($this->orderId);
        // Process
    }
}

// Dispatch
ProcessOrder::dispatch($order->id)
    ->onQueue('orders')
    ->onConnection('rabbitmq');

// Worker
// php artisan queue:work rabbitmq --queue=orders
```

**Kafka ilə Laravel (mateusjunges/laravel-kafka):**

```bash
composer require mateusjunges/laravel-kafka
```

```php
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

// Producer
Kafka::publish('localhost:9092')
    ->onTopic('orders')
    ->withMessage(new Message(
        body   : ['order_id' => 123, 'user_id' => 456],
        headers: ['source' => 'web'],
        key    : 'order-123'
    ))
    ->send();

// Consumer
$consumer = Kafka::createConsumer(['orders'])
    ->withBrokers('localhost:9092')
    ->withConsumerGroupId('order-processor')
    ->withHandler(function (ConsumerMessage $message) {
        $data = $message->getBody();
        ProcessOrder::dispatch($data['order_id']);
    })
    ->withAutoCommit()
    ->build();

$consumer->consume();
```

**MQTT ilə Laravel:**

```bash
composer require php-mqtt/client
```

```php
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

$client = new MqttClient('broker.hivemq.com', 1883, 'laravel-client');

$settings = (new ConnectionSettings())
    ->setUsername('user')
    ->setPassword('pass')
    ->setKeepAliveInterval(60);

$client->connect($settings, true);

// Publish
$client->publish('home/kitchen/temperature', '22.5', 1); // qos=1

// Subscribe
$client->subscribe('home/+/temperature', function ($topic, $message) {
    echo "[{$topic}] {$message}\n";
}, 1);

$client->loop(true);
$client->disconnect();
```

**Protobuf ilə PHP:**

```proto
// order.proto
syntax = "proto3";

message Order {
  int64 id = 1;
  int64 user_id = 2;
  int64 total = 3;
  repeated OrderItem items = 4;
}

message OrderItem {
  int64 product_id = 1;
  int32 quantity = 2;
}
```

```bash
protoc --php_out=./generated order.proto
```

```php
$order = new Order();
$order->setId(123);
$order->setUserId(456);
$order->setTotal(5000);

$item = new OrderItem();
$item->setProductId(789);
$item->setQuantity(2);
$order->setItems([$item]);

$binary = $order->serializeToString();
echo "Size: " . strlen($binary) . " bytes\n"; // ~20 bytes

// Deserialize
$order2 = new Order();
$order2->mergeFromString($binary);
echo $order2->getId(); // 123
```

**Laravel Outbox Pattern:**

```php
// Transaction + outbox
DB::transaction(function () use ($data) {
    // Business data
    $order = Order::create($data);

    // Outbox message
    OutboxMessage::create([
        'topic'   => 'orders',
        'payload' => json_encode([
            'event' => 'order.created',
            'data'  => $order->toArray(),
        ]),
        'status'  => 'pending',
    ]);
});

// Ayrı worker outbox message-ləri publish edir
class OutboxPublisher extends Command
{
    public function handle()
    {
        OutboxMessage::where('status', 'pending')
            ->limit(100)
            ->each(function ($msg) {
                try {
                    Kafka::publish('localhost:9092')
                        ->onTopic($msg->topic)
                        ->withMessage(new Message(body: $msg->payload))
                        ->send();

                    $msg->update(['status' => 'published']);
                } catch (\Exception $e) {
                    $msg->increment('attempts');
                    if ($msg->attempts > 5) {
                        $msg->update(['status' => 'failed']);
                    }
                }
            });
    }
}
```

## Praktik Tapşırıqlar

1. **RabbitMQ ilə Laravel Queue:** Docker-da RabbitMQ qaldırın (`rabbitmq:3-management`). `vladimir-yuldashev/laravel-queue-rabbitmq` quraşdırın. `ProcessOrder` job-unu RabbitMQ queue-una dispatch edin. RabbitMQ Management UI-da (`localhost:15672`) queue-u izləyin.

2. **Topic Exchange routing:** AMQP topic exchange qurun. `order.created`, `order.paid`, `order.shipped` routing key-ləri üçün müxtəlif queue-lar bağlayın. Hər event tipini müvafiq queue-a düzgün yönləndirildiyini yoxlayın.

3. **Dead Letter Queue:** `orders` queue-una DLX konfiqurasiya edin. Consumer-dən `nack(false, false)` göndərin. Message-in `orders.dlq` queue-una keçdiyini RabbitMQ Management UI-da yoxlayın.

4. **Outbox pattern:** `outbox_messages` cədvəlini yaradın. Order yaratmaq ilə outbox message insert-ini eyni DB transaction-a daxil edin. `OutboxPublisher` command-ı `schedule()->everyMinute()` ilə işlədin.

5. **JSON vs Protobuf benchmark:** Eyni data üçün JSON və Protobuf serialization-ı müqayisə edin. `strlen($binary)` ilə ölçü fərqini, `microtime()` ilə sürət fərqini ölçün.

## Əlaqəli Mövzular

- [WebSocket](11-websocket.md)
- [SSE](12-sse.md)
- [Webhooks](23-webhooks.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Protocol Buffers](39-protocol-buffers.md)
