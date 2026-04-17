# Message Protocols

## Nədir? (What is it?)

Message protocols distributed system-lərdə komponentlər arasında asynchronous komunikasiya üçün istifade olunan standartlardır. Məsləhətlə message broker (RabbitMQ, Kafka) istifadə olunur, producer message göndərir, consumer isə alır. Decoupling, scalability, reliability təmin edir.

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
   |                         |                          |
   |                         |<-- poll / push ----------|
   |                         |                          |
   |                         |<-- ack -------------------|
```

## Necə İşləyir? (How does it work?)

### 1. AMQP (RabbitMQ)

```
AMQP 0.9.1 / 1.0 - Full-featured message protocol.

Komponentlər:
  Producer  --> Exchange --> Queue(s) --> Consumer

Exchange types:

1. Direct Exchange
   Routing key exact match:
     routing_key="order.created" --> Queue "orders"

2. Fanout Exchange
   Broadcast - all bound queues:
     [exchange] --> [queue1, queue2, queue3]

3. Topic Exchange
   Pattern matching (wildcards):
     * = one word, # = zero or more words
     "order.*.created" matches "order.payment.created"
     "user.#" matches "user.login", "user.profile.updated"

4. Headers Exchange
   Match on headers, not routing key.

Flow example:

C: [connect to rabbitmq:5672]
C: Protocol handshake
C: Authenticate
C: Open channel
C: Declare exchange "orders" (topic)
C: Declare queue "new_orders"
C: Bind queue to exchange with routing_key "order.created"
C: Publish message
   routing_key="order.created"
   payload={"order_id":123,"amount":5000}
S: Routes to "new_orders" queue
C: [another client] Subscribe to "new_orders"
S: Delivers message
C: Process, send ACK
S: Removes from queue

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

Connection:
  C: CONNECT (client_id, username, password, keep_alive)
  S: CONNACK (session_present, return_code)

Subscribe:
  C: SUBSCRIBE topic="home/+/temp" qos=1
  S: SUBACK

Publish:
  C: PUBLISH topic="home/kitchen/temp" qos=1 payload="22.5"
  S: PUBACK (if qos >= 1)

Features:
  - Lightweight (2-byte header)
  - Low bandwidth (sensors, mobile)
  - Retained messages (latest state available)
  - Last Will and Testament (LWT) - disconnect notification
  - Session persistence
```

### 3. STOMP

```
STOMP: Simple Text Oriented Messaging Protocol.
Human-readable, HTTP-like frames.

Frame structure:
  COMMAND
  header1:value1
  header2:value2

  body^@

Example CONNECT:
  CONNECT
  accept-version:1.2
  host:broker.example.com
  login:user
  passcode:password

  ^@

Server response:
  CONNECTED
  version:1.2
  heart-beat:10000,10000

  ^@

Publish:
  SEND
  destination:/queue/orders
  content-type:application/json

  {"order_id":123}^@

Subscribe:
  SUBSCRIBE
  id:sub-1
  destination:/queue/orders
  ack:client

  ^@

Receive:
  MESSAGE
  destination:/queue/orders
  message-id:abc
  subscription:sub-1

  {"order_id":123}^@

ACK:
  ACK
  id:abc

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
  Partition 0, offset 0, 1, 2, ...

Producer:
  ProduceRequest(topic, partition, records)
    -> Broker appends to log
  ProduceResponse(offset)

Consumer:
  FetchRequest(topic, partition, offset)
    -> Broker returns records from offset
  FetchResponse(records)

Consumer group:
  Multiple consumers, each gets a subset of partitions.
  Partition 0 -> Consumer A
  Partition 1 -> Consumer B
  Partition 2 -> Consumer C

Features:
  - Append-only log (very fast)
  - Retention (days/weeks)
  - Replay (re-consume old messages)
  - Exactly-once semantics (idempotent producer + transactions)
  - High throughput (millions/sec)
```

### 5. JSON Serialization

```json
{
  "order_id": 123,
  "user_id": 456,
  "items": [
    {"product_id": 789, "quantity": 2}
  ],
  "total": 5000
}

Size: ~120 bytes
Parsing: Slow (text)
Schema: None (runtime validation)

Pros:
  - Human-readable
  - Universal support
  - Debug-friendly

Cons:
  - Verbose
  - No type safety
  - Slow parsing
  - No schema evolution
```

### 6. Protocol Buffers (Protobuf)

```
Schema first (.proto file):

message Order {
  int64 order_id = 1;
  int64 user_id = 2;
  repeated OrderItem items = 3;
  int64 total = 4;
}

message OrderItem {
  int64 product_id = 1;
  int32 quantity = 2;
}

Binary encoding (~30 bytes for same data):
  - Field numbers + wire types
  - Varint encoding for integers
  - Much smaller than JSON

Code generation:
  protoc --php_out=. order.proto
  protoc --java_out=. order.proto

Usage (PHP):
  $order = new Order();
  $order->setOrderId(123);
  $binary = $order->serializeToString();

  $order2 = new Order();
  $order2->mergeFromString($binary);

Pros:
  - Compact (3-10x smaller than JSON)
  - Fast parsing (binary)
  - Schema evolution (backward/forward compatible)
  - Strong typing

Cons:
  - Not human-readable
  - Requires code generation
  - Schema management
```

### 7. Apache Avro

```
Schema-based binary format (.avsc):

{
  "type": "record",
  "name": "Order",
  "fields": [
    {"name": "order_id", "type": "long"},
    {"name": "user_id", "type": "long"},
    {"name": "total", "type": "int"}
  ]
}

Features:
  - Schema registry (Confluent)
  - Writer vs Reader schema (evolution)
  - Dynamic typing (no code gen required)
  - Compact binary

Use case: Kafka + Schema Registry
  Producer --> Schema Registry (register schema v1)
  Message has schema ID, not full schema
  Consumer --> Schema Registry (fetch schema by ID)
```

### Comparison Table

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
MessagePack| Small    | Fast        | None      | Alternative JSON
```

## Əsas Konseptlər (Key Concepts)

### At-most-once, At-least-once, Exactly-once

```
At-most-once: Message ya bir defe, ya da hec çatdırılmır.
  - Fast, no acknowledgments
  - Can lose messages
  - MQTT QoS 0

At-least-once: Message ən azı bir defe çatdırılır (duplicate mümkündür).
  - Acknowledgments required
  - Idempotent processing lazim
  - AMQP default, MQTT QoS 1

Exactly-once: Tam bir dəfə çatdırılır.
  - Ən çətin, performance cost
  - Distributed transactions or idempotency
  - MQTT QoS 2, Kafka exactly-once
```

### Message Acknowledgment

```
Auto-ack (fire and forget):
  Consumer alir, broker derhal queue-dan silir.
  Risk: Consumer crash olsa message itir.

Manual ack:
  Consumer process edir, sonra ack gonderir.
  Broker ack alana kimi message-i saxlayir.
  Consumer crash olsa, message yenidən queue-ya qayidir.

Nack (negative ack):
  Consumer "bunu process ede bilmedim" deyir.
  Requeue: yenidən bu consumer-ə göndərilir
  Dead letter: DLX-ə göndərilir
```

### Dead Letter Queue (DLQ)

```
Failed message-lər üçün ayri queue:

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
Duplicate message gelsə, bir dəfə process olan kimi davran.

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

### Backpressure

```
Fast producer, slow consumer:

Producer: 10000 msg/sec
Consumer: 100 msg/sec

Without backpressure: Queue fills up, memory overflow.

Solutions:
  1. Bounded queue (drop new messages)
  2. Consumer-driven flow control (pull model)
  3. Rate limiting producer
  4. Scale out consumers
```

## PHP/Laravel ilə İstifadə

### RabbitMQ ile Laravel (php-amqplib)

```php
// composer require php-amqplib/php-amqplib

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Producer
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Exchange decare
$channel->exchange_declare('orders', 'topic', false, true, false);

$msg = new AMQPMessage(
    json_encode(['order_id' => 123, 'amount' => 5000]),
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

$channel->basic_publish($msg, 'orders', 'order.created');
echo "Published\n";

$channel->close();
$connection->close();

// Consumer
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('new_orders', false, true, false, false);
$channel->queue_bind('new_orders', 'orders', 'order.*');

$channel->basic_qos(null, 1, null); // prefetch count

$callback = function (AMQPMessage $msg) {
    $data = json_decode($msg->body, true);
    echo "Received order: {$data['order_id']}\n";

    try {
        // Process
        processOrder($data);
        $msg->ack();
    } catch (Exception $e) {
        // Retry or send to DLQ
        $msg->nack(false, false); // dont requeue, go to DLQ
    }
};

$channel->basic_consume('new_orders', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
```

### Laravel Queue with RabbitMQ (vladimir-yuldashev package)

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
// Job
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

### Kafka ile Laravel

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
        body: ['order_id' => 123, 'user_id' => 456],
        headers: ['source' => 'web'],
        key: 'order-123'
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

### MQTT ile Laravel

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

$client->loop(true); // blocking loop

$client->disconnect();
```

### Protobuf with PHP

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
# Generate PHP classes
protoc --php_out=./generated order.proto
```

```php
require 'vendor/autoload.php';
require 'generated/Order.php';
require 'generated/OrderItem.php';

// Serialize
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

// Send via message broker
Kafka::publish('localhost:9092')
    ->onTopic('orders')
    ->withMessage(new Message(body: $binary))
    ->send();

// Deserialize
$order2 = new Order();
$order2->mergeFromString($binary);
echo $order2->getId(); // 123
```

### Laravel Outbox Pattern

```php
// Transaction + outbox
DB::transaction(function () use ($data) {
    // Business data
    $order = Order::create($data);

    // Outbox message
    OutboxMessage::create([
        'topic' => 'orders',
        'payload' => json_encode([
            'event' => 'order.created',
            'data' => $order->toArray(),
        ]),
        'status' => 'pending',
    ]);
});

// Separate worker publishes outbox messages
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
                } catch (Exception $e) {
                    $msg->increment('attempts');
                    if ($msg->attempts > 5) {
                        $msg->update(['status' => 'failed']);
                    }
                }
            });
    }
}
```

## Interview Sualları

**Q1: AMQP və MQTT arasında fərq?**

**AMQP** (RabbitMQ protokolu): Full-featured, enterprise. Exchanges (direct, topic, fanout, headers), queues, routing keys, acknowledgments, DLX, priority. Binary protocol, orta header size.

**MQTT**: Lightweight, IoT üçün. Pub/sub only (no exchanges). Topic hierarchy, wildcards. QoS 0/1/2. 2-byte header - very small. Retained messages, LWT.

İstifadə:
- AMQP: Enterprise app, microservices, workflow
- MQTT: IoT sensors, mobile apps, constrained devices

**Q2: Kafka-nın RabbitMQ-dən fərqi?**

**Kafka**:
- Log-structured storage (append-only)
- Messages persistent (retention days/weeks)
- Replay mümkün (old messages re-consume)
- Very high throughput (millions/sec)
- Consumer pull model
- Partitioned topics

**RabbitMQ**:
- Traditional message queue
- Messages deleted after ack
- Complex routing (exchanges)
- Lower throughput (~50K msg/sec)
- Push or pull model
- Priority queues, DLX

İstifadə:
- Kafka: Event streaming, log aggregation, analytics pipeline
- RabbitMQ: Task queues, RPC, complex routing

**Q3: At-least-once delivery-də duplicate-lər necə handle olunur?**

**Idempotency** prinsipi:
- Unique message ID
- DB constraint (UNIQUE)
- Duplicate fails gracefully

```php
public function handle(OrderCreatedEvent $event) {
    $exists = Processed::where('event_id', $event->id)->exists();
    if ($exists) return; // duplicate, skip

    DB::transaction(function() use ($event) {
        // business logic
        Processed::create(['event_id' => $event->id]);
    });
}
```

Alternativ: Redis-də mark (TTL window), outbox + inbox pattern.

**Q4: JSON vs Protobuf - nə vaxt hansi?**

**JSON**:
- REST API-lər (browser-based)
- Debug, human-readable
- Schema yoxdur, flexibility
- Small scale (<1000 req/sec)

**Protobuf**:
- High-throughput microservices
- Mobile (bandwidth matters)
- Schema evolution lazım
- gRPC (default format)
- Internal service-to-service

Protobuf 3-10x kiçik, 5-10x sürətli. Amma schema management overhead.

**Q5: Exactly-once delivery necə mümkündür?**

İki hissə var:
1. **Publish**: Idempotent producer (Kafka producer ID + sequence number)
2. **Consume**: Transaction-al processing (message + DB commit bir atomic operation)

Kafka-da:
```
Producer: enable.idempotence=true + transactional.id
Consumer: isolation.level=read_committed
```

Alternativ: At-least-once + idempotency (daha praktik).

**Q6: Dead Letter Queue nə vaxt istifadə olunur?**

Message process edilmir (retry-dən sonra da):
- Malformed payload
- Consumer bug
- External dependency persistent fail
- TTL expired

DLQ-ya göndərilir:
- Manual review
- Replay logic
- Alerting
- Debugging

```
Queue "orders" -> max 3 retries -> DLX -> "orders.dlq"
```

**Q7: Topic vs Queue fərqi?**

**Queue**: Point-to-point. 1 message = 1 consumer (work distribution).
```
[Producer] -> [Queue] -> [Consumer A, B, C]
                         (only one receives each message)
```

**Topic**: Pub/sub. 1 message = hamı üçün.
```
[Producer] -> [Topic] -> [Subscriber A]
                      -> [Subscriber B]
                      -> [Subscriber C]
                      (hər biri öz kopyasını alır)
```

Kafka: Topic-lər var, amma consumer group-la queue semantics də əldə olunur.

**Q8: Message broker vs direct HTTP fərqi?**

**Direct HTTP (sync)**:
- Real-time response
- Tight coupling
- Caller waits (latency)
- Failure propagates
- Same availability window

**Message broker (async)**:
- Decoupling (producer/consumer independent)
- Buffer (burst handling)
- Retry logic
- Different availability (broker down-a tolerant)
- Eventual consistency

İstifadə:
- HTTP: Synchronous queries, CRUD
- Broker: Background tasks, events, workflows

**Q9: Schema Registry (Avro) nə üçündür?**

Problem: Kafka message-lər binary, schema lazımdır decode üçün. Hər message-ə schema əlavə etmək çox böyük yük.

Schema Registry həll:
1. Producer schema-nı registry-ə publish edir, ID alır
2. Message-də yalniz schema ID var (binary data + ID)
3. Consumer schema ID ilə registry-dən schema-nı çəkir
4. Schema cache-lənir

Faydalar:
- Kiçik message
- Schema evolution (backward compatible check)
- Centralized schema management
- Version tracking

**Q10: MQTT QoS 0, 1, 2 fərqi nədir?**

**QoS 0** (at most once): PUBLISH göndərilir, ack yoxdur. Message itə bilər. Fastest.

**QoS 1** (at least once): PUBLISH -> PUBACK. Ack alinana kimi retry. Duplicate mümkündür.
```
C -> PUBLISH (msg_id=1)
S -> PUBACK (msg_id=1)
```

**QoS 2** (exactly once): 4-way handshake. Guaranteed one delivery.
```
C -> PUBLISH (msg_id=1)
S -> PUBREC (msg_id=1)
C -> PUBREL (msg_id=1)
S -> PUBCOMP (msg_id=1)
```

Trade-off: Higher QoS = slower, more bandwidth.

## Best Practices

1. **Idempotent consumer design** - duplicate message-lər qaçılmaz, safely handle et.

2. **Manual ack istifadə et** - auto-ack crash zamanı message itirir.

3. **Dead letter queue configure et** - failed message-lər isolate olunsun.

4. **Message retention policy** - disk dolmasin (Kafka-da önəmli).

5. **Prefetch count tune et** - RabbitMQ-də consumer-in bir anda nə qədər alması.

6. **Persistent messages** - important data üçün (delivery_mode=2).

7. **Connection pooling** - hər request üçün yeni connection açma.

8. **Serialization format dogru seç** - JSON internal yox, Protobuf/Avro.

9. **Schema evolution** - backward compatibility qoru (Avro, Protobuf).

10. **Outbox pattern** - DB consistency message publishing ilə (transactional).

11. **Monitoring** - queue depth, consumer lag, failed messages.

12. **Circuit breaker** - external dependency down olsa retry-dən qac.

13. **Rate limiting** - producer-ın burst-i broker-i overload etməsin.

14. **Consumer group scalability** - Kafka-da partition count + consumer count.

15. **TLS encryption** - production-da plain-text connection istifadə etmə.

16. **Authentication & authorization** - ACL ilə topic/queue access control.

17. **Graceful shutdown** - consumer-da SIGTERM alanda mövcud message-i bitir.

18. **Message versioning** - payload-da version field (schema change-lərdə).

19. **Compression** - large message-lər (gzip, snappy) - Kafka-da builtin.

20. **Load testing** - production-a çıxmazdan əvvəl throughput limit-lərini bil.
