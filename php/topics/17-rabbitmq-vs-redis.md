# RabbitMQ və Redis — Müqayisəli Bələdçi

## 1. Message Queue / Message Broker Nədir?

**Message Queue** — producer (göndərən) və consumer (qəbul edən) arasında asinxron mesaj mübadiləsi təmin edən vasitədir. Producer mesajı queue-ya göndərir, consumer isə queue-dan mesajı alır və emal edir.

**Message Broker** — mesajları yönləndirən, çevirən və idarə edən orta sloy (middleware). RabbitMQ bir message broker-dir.

### Niyə Message Queue Lazımdır?

```
Message Queue olmadan (tight coupling):
User Request -> Web Server -> Send Email (3 saniyə gözlə)
                           -> Generate PDF (5 saniyə gözlə)
                           -> Resize Image (2 saniyə gözlə)
                           = 10 saniyə response time

Message Queue ilə (loose coupling):
User Request -> Web Server -> Queue: Send Email
                           -> Queue: Generate PDF
                           -> Queue: Resize Image
                           = 50ms response time (queue-ya göndərmə)
                           
Background Workers -> Queue-dan al -> Asinxron emal et
```

**Faydaları:**
- **Decoupling** — Producer və consumer bir-birindən müstəqildir
- **Async processing** — Ağır işlər background-da
- **Load leveling** — Pik yükü buferləyir
- **Reliability** — Mesaj itirilmir, retry mexanizmi var
- **Scalability** — Lazım olduqda daha çox consumer əlavə et

---

## 2. RabbitMQ Nədir?

**RabbitMQ** — AMQP (Advanced Message Queuing Protocol) əsaslı, açıq mənbəli message broker-dir. Erlang dilində yazılıb. Mesajların etibarlı şəkildə çatdırılmasını təmin edir.

### RabbitMQ Arxitekturası

```
Producer -> Exchange -> Binding -> Queue -> Consumer
              |                     |
         Routing rules         Message storage
```

### AMQP Protokolu

AMQP (Advanced Message Queuing Protocol) — open standard messaging protocol. Mesaj formatı, routing, queuing və delivery qarantiyalarını müəyyən edir.

**Əsas xüsusiyyətlər:**
- Binary protocol (HTTP-dən sürətli)
- Reliable delivery (acknowledgment mechanism)
- Flexible routing (exchange types)
- Security (TLS, SASL authentication)
- Flow control

```
AMQP Frame Structure:
+----------+----------+-----------+----------+
|  Type    | Channel  |   Size    |  Payload |
| (1 byte) | (2 byte) | (4 bytes) | (N bytes)|
+----------+----------+-----------+----------+
```

---

## 3. RabbitMQ Konseptləri

### 3.1 Exchange

Producer mesajı birbaşa queue-ya göndərmir, exchange-ə göndərir. Exchange mesajı routing qaydalarına əsasən müvafiq queue-lara yönləndirir.

### 3.2 Queue

Mesajların saxlandığı yer. FIFO (First In, First Out) prinsipilə işləyir.

### 3.3 Binding

Exchange ilə Queue arasındakı əlaqə. Routing key və ya header əsasında hansı mesajların hansı queue-ya getdiyini müəyyən edir.

### 3.4 Routing Key

Mesajla birlikdə göndərilən string. Exchange bu key-ə əsasən mesajı yönləndirir.

### 3.5 Virtual Host

Logik ayrılıq. Hər vhost-un öz exchange-ləri, queue-ları və permission-ları var. Multi-tenant setup üçün.

```
vhost: /production
  ├── exchange: orders
  ├── queue: email-notifications
  └── queue: sms-notifications

vhost: /staging
  ├── exchange: orders
  └── queue: email-notifications
```

---

## 4. Exchange Types

### 4.1 Direct Exchange

Routing key **tam uyğunluq** ilə mesajı queue-ya yönləndirir.

```
Producer --[routing_key: "email"]--> Direct Exchange
                                       |
                            routing_key = "email" -> Queue: emails (match!)
                            routing_key = "sms"   -> Queue: sms (no match)
```

*routing_key = "sms"   -> Queue: sms (no match) üçün kod nümunəsi:*
```php
// PHP ilə RabbitMQ (php-amqplib)
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Bağlantı
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Direct Exchange
$channel->exchange_declare('notifications', 'direct', false, true, false);

// Queue-lar
$channel->queue_declare('email-queue', false, true, false, false);
$channel->queue_declare('sms-queue', false, true, false, false);

// Binding
$channel->queue_bind('email-queue', 'notifications', 'email');
$channel->queue_bind('sms-queue', 'notifications', 'sms');

// Mesaj göndər
$message = new AMQPMessage(json_encode([
    'to' => 'user@example.com',
    'subject' => 'Order Confirmed',
    'body' => 'Your order has been confirmed.',
]), [
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'content_type' => 'application/json',
]);

$channel->basic_publish($message, 'notifications', 'email'); // email queue-ya gedir
$channel->basic_publish($message, 'notifications', 'sms');   // sms queue-ya gedir
```

### 4.2 Fanout Exchange

Routing key-ə baxmadan mesajı **bütün bağlı queue-lara** göndərir. Broadcasting üçün.

```
Producer --[any key]--> Fanout Exchange
                           |
                           ├--> Queue: email-notifications
                           ├--> Queue: push-notifications
                           └--> Queue: audit-log
```

*└--> Queue: audit-log üçün kod nümunəsi:*
```php
// Fanout Exchange
$channel->exchange_declare('order-events', 'fanout', false, true, false);

$channel->queue_bind('email-notifications', 'order-events');
$channel->queue_bind('push-notifications', 'order-events');
$channel->queue_bind('audit-log', 'order-events');

// Mesaj göndər - routing key-in əhəmiyyəti yoxdur
$channel->basic_publish($message, 'order-events', '');
// Hər 3 queue-ya çatdırılacaq
```

### 4.3 Topic Exchange

Routing key-də **pattern matching** ilə yönləndirir. Wildcard-lar: `*` (bir söz), `#` (sıfır və ya daha çox söz).

```
Routing key format: <entity>.<event>.<priority>

Producer --[order.created.high]--> Topic Exchange
                                      |
                  order.*.*        -> Queue: all-orders (match!)
                  order.created.*  -> Queue: new-orders (match!)
                  *.*.high         -> Queue: priority (match!)
                  payment.#        -> Queue: payments (no match)
```

*payment.#        -> Queue: payments (no match) üçün kod nümunəsi:*
```php
// Topic Exchange
$channel->exchange_declare('events', 'topic', false, true, false);

$channel->queue_bind('all-orders', 'events', 'order.*.*');
$channel->queue_bind('new-orders', 'events', 'order.created.*');
$channel->queue_bind('high-priority', 'events', '*.*.high');
$channel->queue_bind('all-events', 'events', '#');  // Hər şeyi alır

// Mesaj göndər
$channel->basic_publish($orderMessage, 'events', 'order.created.high');
// all-orders, new-orders, high-priority, all-events queue-larına gedir

$channel->basic_publish($paymentMessage, 'events', 'payment.completed.normal');
// yalnız all-events queue-suna gedir
```

### 4.4 Headers Exchange

Routing key əvəzinə mesaj header-larına əsasən yönləndirir.

*Routing key əvəzinə mesaj header-larına əsasən yönləndirir üçün kod nümunəsi:*
```php
// Headers Exchange
$channel->exchange_declare('matching', 'headers', false, true, false);

// Binding with headers
$channel->queue_bind('premium-email', 'matching', '', false, new AMQPTable([
    'x-match' => 'all',           // 'all' = bütün header-lər uyğun olmalı
    'type' => 'notification',
    'priority' => 'high',
]));

$channel->queue_bind('all-notifications', 'matching', '', false, new AMQPTable([
    'x-match' => 'any',           // 'any' = ən azı bir header uyğun olmalı
    'type' => 'notification',
]));

// Mesaj göndər
$message = new AMQPMessage($body, [
    'application_headers' => new AMQPTable([
        'type' => 'notification',
        'priority' => 'high',
    ]),
]);
$channel->basic_publish($message, 'matching', '');
```

---

## 5. Message Acknowledgment

### 5.1 ACK / NACK

Consumer mesajı uğurla emal etdiyini (ACK) və ya emal edə bilmədiyini (NACK) bildirir.

*Consumer mesajı uğurla emal etdiyini (ACK) və ya emal edə bilmədiyini  üçün kod nümunəsi:*
```php
// Consumer
$channel->basic_qos(null, 1, null); // Bir dəfəyə 1 mesaj al (prefetch)

$callback = function (AMQPMessage $msg) {
    $data = json_decode($msg->body, true);

    try {
        // Mesajı emal et
        $this->processOrder($data);

        // ACK - uğurlu emal
        $msg->ack();
    } catch (\Exception $e) {
        // NACK - emal uğursuz
        // requeue: true = queue-ya geri qoy, false = sil/DLX-ə göndər
        $msg->nack(requeue: false);
    }
};

$channel->basic_consume('orders', '', false, false, false, false, $callback);
//                                          ^
//                                    no_ack = false (manual ack tələb olunur)
```

### 5.2 Auto ACK vs Manual ACK

*5.2 Auto ACK vs Manual ACK üçün kod nümunəsi:*
```php
// Auto ACK (təhlükəli - mesaj itə bilər)
$channel->basic_consume('orders', '', false, true, false, false, $callback);
//                                          ^^^^
//                                    no_ack = true (avtomatik acknowledge)
// Mesaj consumer-ə çatdırılan kimi silinir, crash olsa itirilir

// Manual ACK (təhlükəsiz)
$channel->basic_consume('orders', '', false, false, false, false, $callback);
//                                           ^^^^^
//                                    no_ack = false (əl ilə acknowledge lazım)
// Mesaj consumer ACK göndərənə qədər queue-da qalır
```

---

## 6. Dead Letter Exchange (DLX)

Emal edilə bilməyən mesajların göndərildiyi xüsusi exchange. Retry, error handling üçün vacibdir.

```
Main Queue --[reject/expire]--> Dead Letter Exchange --> Dead Letter Queue
                                                              |
                                                     Error analysis / Retry
```

*Error analysis / Retry üçün kod nümunəsi:*
```php
// Dead Letter Exchange setup
$channel->exchange_declare('dlx', 'direct', false, true, false);
$channel->queue_declare('dead-letter-queue', false, true, false, false);
$channel->queue_bind('dead-letter-queue', 'dlx', 'failed');

// Main queue DLX ilə
$channel->queue_declare('orders', false, true, false, false, false, new AMQPTable([
    'x-dead-letter-exchange' => 'dlx',
    'x-dead-letter-routing-key' => 'failed',
    'x-message-ttl' => 60000,           // 60 saniyə TTL
    'x-max-length' => 10000,            // Maksimum 10000 mesaj
]));

// Consumer - reject olunmuş mesajlar DLX-ə gedir
$callback = function (AMQPMessage $msg) {
    try {
        $this->process($msg);
        $msg->ack();
    } catch (\Exception $e) {
        // requeue: false = DLX-ə göndər
        $msg->nack(requeue: false);
    }
};
```

### Retry with DLX

*Retry with DLX üçün kod nümunəsi:*
```php
// Retry pattern: Main Queue <-> Wait Queue (DLX)

// Wait queue (mesaj burada gözləyir, TTL bitdikdə main queue-ya qayıdır)
$channel->queue_declare('orders-retry', false, true, false, false, false, new AMQPTable([
    'x-dead-letter-exchange' => '',              // Default exchange
    'x-dead-letter-routing-key' => 'orders',     // Main queue-ya qayıt
    'x-message-ttl' => 30000,                    // 30 saniyə gözlə
]));

// Main queue
$channel->queue_declare('orders', false, true, false, false, false, new AMQPTable([
    'x-dead-letter-exchange' => '',
    'x-dead-letter-routing-key' => 'orders-retry', // Fail olsa retry queue-ya
]));

// Consumer
$callback = function (AMQPMessage $msg) {
    $headers = $msg->get('application_headers')?->getNativeData() ?? [];
    $retryCount = $headers['x-retry-count'] ?? 0;

    if ($retryCount >= 3) {
        // 3 dəfə cəhd olunub, final dead letter queue-ya göndər
        $msg->nack(requeue: false);
        return;
    }

    try {
        $this->process($msg);
        $msg->ack();
    } catch (\Exception $e) {
        // Retry count artır
        $msg->get('application_headers')->set('x-retry-count', $retryCount + 1);
        $msg->nack(requeue: false); // retry queue-ya gedir
    }
};
```

---

## 7. Message TTL və Priority Queues

### Message TTL

*Message TTL üçün kod nümunəsi:*
```php
// Queue-level TTL (bütün mesajlar üçün)
$channel->queue_declare('temp-queue', false, true, false, false, false, new AMQPTable([
    'x-message-ttl' => 60000, // 60 saniyə
]));

// Per-message TTL
$message = new AMQPMessage($body, [
    'expiration' => '30000', // 30 saniyə (string olmalıdır)
]);
```

### Priority Queues

*Priority Queues üçün kod nümunəsi:*
```php
// Priority queue yarat (max priority: 10)
$channel->queue_declare('priority-queue', false, true, false, false, false, new AMQPTable([
    'x-max-priority' => 10,
]));

// Yüksək prioritetli mesaj
$highPriority = new AMQPMessage($body, [
    'priority' => 9,
    'delivery_mode' => 2,
]);

// Aşağı prioritetli mesaj
$lowPriority = new AMQPMessage($body, [
    'priority' => 1,
    'delivery_mode' => 2,
]);

$channel->basic_publish($lowPriority, '', 'priority-queue');
$channel->basic_publish($highPriority, '', 'priority-queue');
// Consumer əvvəl yüksək prioritetli mesajı alacaq
```

---

## 8. RabbitMQ Clustering və High Availability

### 8.1 Clustering

```
Node 1 (disk) <-> Node 2 (ram) <-> Node 3 (disk)
     |                |                |
   Queue A         Queue B          Queue C
```

Cluster-da metadata (exchange, queue konfiqurasiyası) bütün node-larda replika olunur, amma mesajlar default olaraq yalnız bir node-da saxlanılır.

### 8.2 Quorum Queues (RabbitMQ 3.8+)

Classic Mirrored Queue-ların əvəzinə tövsiyə olunur. Raft consensus protocol istifadə edir.

*Classic Mirrored Queue-ların əvəzinə tövsiyə olunur. Raft consensus pr üçün kod nümunəsi:*
```php
// Quorum queue yarat
$channel->queue_declare('orders', false, true, false, false, false, new AMQPTable([
    'x-queue-type' => 'quorum',
    'x-quorum-initial-group-size' => 3, // 3 node-da replika
]));
```

**Quorum Queue üstünlükləri:**
- Raft consensus — data itkisi riskini minimuma endirir
- Automatic leader election
- Poison message handling (delivery limit)
- Daha yaxşı performans (mirrored queue-lardan)

### 8.3 Classic Mirrored Queues (köhnə, deprecated)

*8.3 Classic Mirrored Queues (köhnə, deprecated) üçün kod nümunəsi:*
```bash
# Policy ilə mirror qurulması
rabbitmqctl set_policy ha-all ".*" '{"ha-mode":"all"}' --apply-to queues
rabbitmqctl set_policy ha-two "^important\." '{"ha-mode":"exactly","ha-params":2}' --apply-to queues
```

---

## 9. Redis as Message Broker

Redis-i message broker kimi istifadə etməyin 3 yolu var:

### 9.1 Redis Pub/Sub

*9.1 Redis Pub/Sub üçün kod nümunəsi:*
```php
// Publisher
Redis::publish('notifications', json_encode([
    'type' => 'order_shipped',
    'order_id' => 123,
    'user_id' => 456,
]));

// Subscriber (ayrı proses)
Redis::subscribe(['notifications'], function (string $message) {
    $data = json_decode($message, true);
    // Emal et
});
```

**Məhdudiyyətlər:**
- Fire-and-forget (offline subscriber mesajı almaz)
- Persistence yoxdur
- Acknowledgment yoxdur
- Mesaj itkisi riski var

### 9.2 Redis Streams

*9.2 Redis Streams üçün kod nümunəsi:*
```php
// Producer
Redis::xadd('orders', '*', [
    'order_id' => 123,
    'action' => 'created',
    'amount' => 150.00,
]);

// Consumer Group yarat
Redis::xgroup('CREATE', 'orders', 'order-processors', '0', true);

// Consumer
$messages = Redis::xreadgroup('order-processors', 'worker-1', ['orders' => '>'], 10, 5000);
// COUNT: 10 mesaj, BLOCK: 5000ms gözlə

foreach ($messages['orders'] ?? [] as $id => $data) {
    try {
        $this->processOrder($data);
        Redis::xack('orders', 'order-processors', $id); // Acknowledge
    } catch (\Exception $e) {
        // Pending qalacaq, başqa worker claim edə bilər
    }
}
```

**Stream üstünlükləri (Pub/Sub-dan):**
- Persistent (mesajlar disk-ə yazılır)
- Consumer Groups (Kafka-ya bənzər)
- Acknowledgment mexanizmi
- Message replay (köhnə mesajları yenidən oxuya bilər)

### 9.3 Redis List (LPUSH/BRPOP Pattern)

*9.3 Redis List (LPUSH/BRPOP Pattern) üçün kod nümunəsi:*
```php
// Producer
Redis::rpush('queue:emails', json_encode([
    'to' => 'user@example.com',
    'subject' => 'Welcome',
    'body' => 'Welcome to our platform!',
]));

// Consumer (blocking pop)
while (true) {
    $result = Redis::brpop('queue:emails', 30); // 30 saniyə gözlə
    if ($result) {
        [$queue, $message] = $result;
        $data = json_decode($message, true);
        $this->sendEmail($data);
    }
}
```

**Laravel Queue bu pattern-i istifadə edir (Redis driver).**

---

## 10. RabbitMQ vs Redis Müqayisəsi

### 10.1 Detallı Müqayisə Cədvəli

| Xüsusiyyət | RabbitMQ | Redis (as Broker) |
|-------------|----------|-------------------|
| **Protokol** | AMQP, MQTT, STOMP | RESP (Redis Protocol) |
| **Dil** | Erlang | C |
| **Message Routing** | Çox güclü (4 exchange tipi) | Məhdud (Pub/Sub, Streams) |
| **Message Durability** | Bəli (disk persistence) | Məhdud (AOF/RDB) |
| **Guaranteed Delivery** | Bəli (ack/nack, confirms) | Streams-də bəli, Pub/Sub-da yox |
| **Message Ordering** | Queue daxilində FIFO | Bəli |
| **Dead Letter** | DLX built-in | Manual implementation |
| **Priority Queues** | Built-in | Sorted Set ilə manual |
| **TTL** | Per-message və per-queue | Per-message |
| **Clustering** | Built-in (Quorum queues) | Redis Cluster (sharding) |
| **Performance** | ~50K msg/s | ~100K+ msg/s |
| **Latency** | ~1ms | ~0.1ms |
| **Memory** | Yüksək (Erlang VM) | Aşağı |
| **Management UI** | Built-in web UI | Yoxdur (3rd party tools) |
| **Consumer Groups** | Yoxdur (amma eyni queue-dan çox consumer) | Streams-də var |
| **Message Replay** | Yoxdur (consume olunduqda silinir) | Streams-də var |
| **Complexity** | Orta-yüksək | Aşağı |
| **Use Case** | Enterprise messaging, complex routing | Simple queuing, caching + queuing |

### 10.2 Message Durability

**RabbitMQ:**
```php
// Persistent mesaj + durable queue = mesaj itirilməz
$channel->queue_declare('orders', false, true, false, false); // durable: true

$message = new AMQPMessage($body, [
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 2
]);

// Publisher confirms
$channel->confirm_select();
$channel->basic_publish($message, '', 'orders');
$channel->wait_for_pending_acks(); // Broker-in disk-ə yazdığını təsdiq et
```

**Redis:**
```php
// Redis Streams - AOF ilə persistent
// redis.conf: appendonly yes, appendfsync everysec
Redis::xadd('orders', '*', ['data' => $body]);
// Ən çox 1 saniyə data itkisi mümkündür
```

### 10.3 Message Routing

**RabbitMQ (çox güclü):**
```php
// Topic exchange ilə complex routing
$channel->basic_publish($msg, 'events', 'order.created.premium');
// Bu mesaj aşağıdakı queue-lara çata bilər:
// - order.* bindingli queue (order.created matchdir)
// - *.created.* bindingli queue
// - order.# bindingli queue
// - #.premium bindingli queue
```

**Redis (məhdud):**
```php
// Redis-də routing yoxdur
// Pub/Sub-da channel adı ilə "routing" edə bilərsiniz
Redis::publish('order.created.premium', $data);
// Consumer-lər: PSUBSCRIBE order.* ilə subscribe ola bilər
// Amma bu RabbitMQ-nin routing gücünə çatmır
```

### 10.4 Performance

```
Benchmark (approximate):

RabbitMQ:
- Simple produce/consume: ~30,000-50,000 msg/s
- With persistence: ~20,000-40,000 msg/s
- With confirms + persistence: ~10,000-20,000 msg/s

Redis:
- LPUSH/BRPOP: ~100,000+ msg/s
- Streams (XADD/XREAD): ~80,000-100,000 msg/s
- Pub/Sub: ~150,000+ msg/s

Redis 2-5x daha sürətlidir, amma RabbitMQ daha çox feature verir.
```

---

## 11. RabbitMQ vs Kafka (Qısa Müqayisə)

| Xüsusiyyət | RabbitMQ | Kafka |
|-------------|----------|-------|
| **Model** | Message Broker (smart broker, dumb consumer) | Distributed Log (dumb broker, smart consumer) |
| **Mesaj saxlama** | Consume olunduqda silinir | TTL-ə qədər saxlanır (replay mümkün) |
| **Throughput** | ~50K msg/s | ~1M+ msg/s |
| **Ordering** | Queue daxilində | Partition daxilində |
| **Consumer Groups** | Queue-based (competing consumers) | Partition-based |
| **Use Case** | Task distribution, complex routing | Event streaming, log aggregation |
| **Reprocessing** | Çətin (mesaj silinir) | Asan (offset-i geri çək) |
| **Latency** | Çox aşağı (<1ms) | Aşağı (~5-10ms) |

**Ne vaxt Kafka:**
- Event sourcing / event streaming
- Çox yüksək throughput (milyonlarla mesaj)
- Mesaj replay lazımdır
- Log aggregation

**Ne vaxt RabbitMQ:**
- Task queue / work distribution
- Complex routing lazımdır
- Aşağı latency vacibdir
- Kiçik-orta ölçülü layihələr

---

## 12. Laravel ilə RabbitMQ

### Quraşdırma (vladimir-yuldashev/laravel-queue-rabbitmq)

*Quraşdırma (vladimir-yuldashev/laravel-queue-rabbitmq) üçün kod nümunəsi:*
```bash
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

*composer require vladimir-yuldashev/laravel-queue-rabbitmq üçün kod nümunəsi:*
```php
// config/queue.php
'connections' => [
    'rabbitmq' => [
        'driver'   => 'rabbitmq',
        'host'     => env('RABBITMQ_HOST', '127.0.0.1'),
        'port'     => env('RABBITMQ_PORT', 5672),
        'user'     => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost'    => env('RABBITMQ_VHOST', '/'),
        'queue'    => env('RABBITMQ_QUEUE', 'default'),

        'options' => [
            'queue' => [
                'exchange'      => 'application',
                'exchange_type' => 'direct',
                'prioritize_delayed' => false,
                'queue_max_priority' => 10,
            ],
        ],

        'worker' => env('RABBITMQ_WORKER', 'default'),
    ],
],

// .env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=default
```

### Job Dispatch

*Job Dispatch üçün kod nümunəsi:*
```php
// Job yaratmaq
// php artisan make:job ProcessOrder

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Order $order
    ) {}

    public function handle(): void
    {
        // Sifarişi emal et
        $this->order->process();
        
        // Notification göndər
        $this->order->user->notify(new OrderProcessedNotification($this->order));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Order processing failed: {$this->order->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

// Dispatch
ProcessOrder::dispatch($order);

// Müəyyən queue-ya dispatch
ProcessOrder::dispatch($order)->onQueue('high-priority');

// Gecikmiş dispatch
ProcessOrder::dispatch($order)->delay(now()->addMinutes(5));

// Connection-u göstər
ProcessOrder::dispatch($order)->onConnection('rabbitmq');
```

### Queue Worker

*Queue Worker üçün kod nümunəsi:*
```bash
# Worker başlat
php artisan queue:work rabbitmq --queue=high-priority,default --tries=3 --backoff=30

# Müəyyən connection
php artisan queue:work rabbitmq

# Supervisor config
# /etc/supervisor/conf.d/laravel-worker.conf
```

*/etc/supervisor/conf.d/laravel-worker.conf üçün kod nümunəsi:*
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work rabbitmq --queue=high-priority,default --tries=3 --sleep=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

---

## 13. Nə Vaxt Redis, Nə Vaxt RabbitMQ?

### Redis İstifadə Edin:

```
✅ Artıq Redis istifadə edirsinizsə (cache, session) - əlavə infrastruktur lazım deyil
✅ Sadə queue ehtiyaclarınız varsa (job dispatch, email göndərmə)
✅ Yüksək sürət vacibdirsə
✅ Laravel default queue kifayətdirsə
✅ Kiçik-orta ölçülü layihələrdə
✅ Real-time features (Pub/Sub, broadcasting)
✅ Cache + Queue eyni server-də olmalıdırsa
```

*✅ Cache + Queue eyni server-də olmalıdırsa üçün kod nümunəsi:*
```php
// Laravel Redis Queue - əksər layihələr üçün kifayətdir
// .env
QUEUE_CONNECTION=redis

// Job dispatch
ProcessOrder::dispatch($order);
SendWelcomeEmail::dispatch($user);
GenerateReport::dispatch($report)->onQueue('reports');
```

### RabbitMQ İstifadə Edin:

```
✅ Complex message routing lazımdırsa (topic, headers exchange)
✅ Guaranteed delivery vacibdirsə (publisher confirms)
✅ Dead Letter Exchange lazımdırsa
✅ Müxtəlif protokollar lazımdırsa (AMQP, MQTT, STOMP)
✅ Microservice arxitekturasında
✅ Enterprise-level messaging
✅ Müxtəlif dil/platformalar arası mesajlaşma
✅ Priority queues lazımdırsa (built-in)
✅ Mesaj routing mürəkkəbdirsə
✅ Management UI lazımdırsa (built-in)
```

*✅ Management UI lazımdırsa (built-in) üçün kod nümunəsi:*
```php
// RabbitMQ ilə microservice communication
// Order Service -> Exchange -> Payment Service Queue
//                           -> Inventory Service Queue
//                           -> Notification Service Queue

// Topic exchange ilə
$channel->basic_publish($msg, 'services', 'order.created');
// payment service: order.* subscribe
// inventory service: order.created subscribe
// notification service: *.created subscribe
```

---

## 14. Real-World Nümunələr

### Nümunə 1: E-Commerce Order Processing

*Nümunə 1: E-Commerce Order Processing üçün kod nümunəsi:*
```php
// Redis Queue ilə (sadə, əksər hallar üçün kifayət)

class OrderController extends Controller
{
    public function store(OrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $order = Order::create($request->validated());
            $order->items()->createMany($request->input('items'));
            return $order;
        });

        // Asinxron job-lar
        ProcessPayment::dispatch($order);
        
        return response()->json(['order' => $order], 201);
    }
}

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(private Order $order) {}

    public function handle(PaymentService $paymentService): void
    {
        $result = $paymentService->charge($this->order);

        if ($result->successful()) {
            $this->order->markAsPaid();
            
            // Chain: payment sonrası digər job-lar
            UpdateInventory::dispatch($this->order);
            SendOrderConfirmation::dispatch($this->order);
            NotifyWarehouse::dispatch($this->order);
        } else {
            $this->order->markPaymentFailed($result->error());
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->order->markPaymentFailed($e->getMessage());
        NotifyAdmin::dispatch("Payment failed for order #{$this->order->id}");
    }
}
```

### Nümunə 2: Microservice Communication (RabbitMQ)

*Nümunə 2: Microservice Communication (RabbitMQ) üçün kod nümunəsi:*
```php
// RabbitMQ ilə microservice-lər arası mesajlaşma

// Order Service - Event publish
class OrderCreatedPublisher
{
    private AMQPChannel $channel;

    public function publish(Order $order): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => 'order.created',
            'timestamp' => now()->toISOString(),
            'data' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ])->toArray(),
                'total' => $order->total,
            ],
        ]), [
            'delivery_mode' => 2, // persistent
            'content_type' => 'application/json',
            'message_id' => (string) Str::uuid(),
            'timestamp' => time(),
        ]);

        $this->channel->basic_publish($message, 'order-events', 'order.created');
    }
}

// Payment Service - Consumer
class PaymentConsumer
{
    public function consume(): void
    {
        $this->channel->queue_bind('payment-queue', 'order-events', 'order.created');

        $callback = function (AMQPMessage $msg) {
            $data = json_decode($msg->body, true);

            try {
                $this->paymentService->processOrder($data['data']);
                $msg->ack();

                // Payment uğurlu - event publish
                $this->publishEvent('payment.completed', $data['data']);
            } catch (\Exception $e) {
                $msg->nack(requeue: false); // DLX-ə göndər
            }
        };

        $this->channel->basic_consume('payment-queue', '', false, false, false, false, $callback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }
}

// Inventory Service - Consumer
class InventoryConsumer
{
    public function consume(): void
    {
        $this->channel->queue_bind('inventory-queue', 'order-events', 'order.created');
        $this->channel->queue_bind('inventory-queue', 'order-events', 'order.cancelled');

        $callback = function (AMQPMessage $msg) {
            $data = json_decode($msg->body, true);

            match ($data['event']) {
                'order.created' => $this->reserveStock($data['data']),
                'order.cancelled' => $this->releaseStock($data['data']),
            };

            $msg->ack();
        };

        $this->channel->basic_consume('inventory-queue', '', false, false, false, false, $callback);
    }
}
```

### Nümunə 3: Notification System (Redis Pub/Sub + Queue)

*Nümunə 3: Notification System (Redis Pub/Sub + Queue) üçün kod nümunəsi:*
```php
// Hybrid: Redis Queue (reliable) + Pub/Sub (real-time)

class NotificationService
{
    /**
     * Notification göndər - queue ilə (reliable)
     */
    public function send(User $user, string $type, array $data): void
    {
        // Database-ə yaz
        $notification = $user->notifications()->create([
            'type' => $type,
            'data' => $data,
        ]);

        // Queue ilə channel-lara göndər
        SendNotification::dispatch($notification);
    }
}

class SendNotification implements ShouldQueue
{
    public function handle(): void
    {
        // Email
        if ($this->notification->user->email_notifications) {
            Mail::to($this->notification->user)->send(
                new NotificationMail($this->notification)
            );
        }

        // Push notification
        if ($this->notification->user->push_notifications) {
            $this->sendPushNotification($this->notification);
        }

        // Real-time: Redis Pub/Sub ilə broadcasting
        broadcast(new NotificationReceived($this->notification));
    }
}

// Real-time listener (JavaScript/Laravel Echo)
// Echo.private('notifications.' + userId)
//     .listen('NotificationReceived', (notification) => {
//         showToast(notification);
//     });
```

---

## 15. İntervyu Sualları və Cavabları

### S1: RabbitMQ ilə Redis arasında message broker olaraq əsas fərq nədir?

**Cavab:** RabbitMQ xüsusi olaraq message broker kimi dizayn olunub — AMQP protokolu, exchange-based routing, delivery guarantees, dead letter handling, publisher confirms kimi enterprise-level xüsusiyyətlər təqdim edir. Redis isə əsas olaraq in-memory data store-dur və message broker funksionallığı əlavə feature kimi var. Redis daha sürətlidir (2-5x), amma RabbitMQ mesaj çatdırılma qarantiyası, mürəkkəb routing və mesaj idarəsi baxımından üstündür. Sadə queue ehtiyacları üçün Redis, enterprise messaging üçün RabbitMQ seçilməlidir.

### S2: RabbitMQ-da Exchange tipləri hansılardır və nə vaxt hansını istifadə edərsiniz?

**Cavab:**
1. **Direct**: Routing key tam uyğunluq — müəyyən queue-ya birbaşa göndərmə (log routing: error -> error queue, info -> info queue)
2. **Fanout**: Bütün bağlı queue-lara broadcast — notification sistemi (bütün service-lərə eyni event)
3. **Topic**: Pattern matching (wildcard: *, #) — event-based sistem (order.created.premium -> müxtəlif consumer-lər)
4. **Headers**: Mesaj header-larına əsasən routing — nadir istifadə olunur, complex matching lazım olduqda

### S3: Dead Letter Exchange nədir və niyə vacibdir?

**Cavab:** DLX emal edilə bilməyən mesajların göndərildiyi xüsusi exchange-dir. Mesaj DLX-ə düşür əgər: consumer reject edirsə (nack, requeue=false), message TTL keçibsə, queue max length dolubsa. DLX vacibdir çünki:
- Mesaj itirilmir, analiz üçün saxlanılır
- Retry mexanizmi qurmağa imkan verir (wait queue -> main queue dövrü)
- Error monitoring və debugging asanlaşır
- Poison message-ləri izolyasiya edir

### S4: Redis Streams ilə RabbitMQ-nun fərqi nədir?

**Cavab:**
- **Redis Streams**: Kafka-ya bənzər append-only log. Consumer groups, message replay, persistent. Amma routing yoxdur, DLX yoxdur, publisher confirms yoxdur. Sadə event streaming üçün yaxşıdır.
- **RabbitMQ**: Traditional message broker. Güclü routing, DLX, priority queues, publisher confirms. Amma message replay yoxdur, consumer groups yoxdur (competing consumers var).
- Streams seçin: event sourcing, message replay lazımdırsa
- RabbitMQ seçin: complex routing, guaranteed delivery, DLX lazımdırsa

### S5: RabbitMQ-da message durability necə təmin olunur?

**Cavab:** Üç addım lazımdır:
1. **Durable Queue**: `queue_declare` zamanı `durable=true` — queue RabbitMQ restart-dan sağ qalır
2. **Persistent Message**: `delivery_mode=2` — mesaj disk-ə yazılır
3. **Publisher Confirms**: `confirm_select()` + `wait_for_pending_acks()` — broker-in mesajı aldığını və disk-ə yazdığını təsdiq edir

Bunlardan biri olmasa mesaj itirilə bilər. Məsələn, durable queue + non-persistent message = restart zamanı mesajlar itirilir.

### S6: Laravel layihəsində nə vaxt RabbitMQ-ya keçməliyik?

**Cavab:**
Redis Queue-dan RabbitMQ-ya keçmək lazımdır əgər:
- Microservice arxitekturasına keçirsinizsə (service-lər arası mesajlaşma)
- Complex message routing lazımdırsa (topic/headers exchange)
- Dead Letter Exchange ilə error handling lazımdırsa
- Müxtəlif dil/platformalar ilə inteqrasiya lazımdırsa (AMQP universal)
- Publisher confirms ilə mesaj qarantiyası vacibdirsə
- Management UI lazımdırsa (built-in web interface)

Redis Queue yetərlidir əgər:
- Monolith application-dır
- Sadə job dispatch (email, PDF, image processing)
- Artıq Redis istifadə edirsinizsə (əlavə infrastruktur istəmirsinizsə)

### S7: RabbitMQ-da mesaj sırası (ordering) necə təmin olunur?

**Cavab:** RabbitMQ bir queue daxilində FIFO sırasını qoruyur, AMA:
- Əgər bir neçə consumer varsa, mesajlar paralel emal olunur və sıra pozula bilər
- Retry/requeue olduqda mesaj queue-nun sonuna gedir
- Priority queue istifadə olunursa, yüksək prioritetli mesajlar əvvəl emal olunur

Sıranı qorumaq üçün:
1. Bir queue üçün tək consumer istifadə edin (amma throughput azalır)
2. Consistent hashing exchange ilə eyni entity-nin mesajlarını eyni queue-ya yönləndirin
3. Application-level sequencing — mesaja sequence number əlavə edin

### S8: Quorum Queue ilə Classic Mirrored Queue arasında fərq nədir?

**Cavab:**
- **Classic Mirrored Queues** (deprecated): Ring replication, split-brain riski, data itkisi mümkün, performance problemi. Artıq tövsiyə olunmur.
- **Quorum Queues** (RabbitMQ 3.8+): Raft consensus protocol, split-brain-ə davamlı, daha yaxşı performans, poison message handling (delivery limit), avtomatik leader election.

Production-da həmişə Quorum Queues istifadə edin.

### S9: Message idempotency nədir və niyə vacibdir?

**Cavab:** Eyni mesajın bir neçə dəfə emal olunmasının eyni nəticəni verməsini təmin edir. Vacibdir çünki message broker "at-least-once" delivery təmin edir — network problemi, consumer crash zamanı mesaj təkrar göndərilə bilər.

***Cavab:** Eyni mesajın bir neçə dəfə emal olunmasının eyni nəticəni v üçün kod nümunəsi:*
```php
class ProcessPayment implements ShouldQueue
{
    public function handle(): void
    {
        // Idempotency check
        $idempotencyKey = "payment:order:{$this->order->id}";
        
        if (Cache::has($idempotencyKey)) {
            return; // Artıq emal olunub
        }

        DB::transaction(function () use ($idempotencyKey) {
            $this->order->refresh();
            
            if ($this->order->isPaid()) {
                return;
            }

            $this->processPayment();
            Cache::put($idempotencyKey, true, now()->addHours(24));
        });
    }
}
```

### S10: Monitoring üçün nə istifadə edirsiniz?

**Cavab:**
**RabbitMQ:**
- Built-in Management UI (port 15672)
- `rabbitmqctl` CLI
- Prometheus + Grafana (rabbitmq_prometheus plugin)
- Metrics: queue depth, message rates, consumer count, memory usage

**Redis Queue:**
- Laravel Horizon (dashboard + metrics)
- `redis-cli INFO` stats
- Redis Exporter + Prometheus + Grafana
- `redis-cli LLEN queue:default` — queue uzunluğu
- Metrics: jobs per minute, runtime, wait time, failed jobs

Bu bələdçi RabbitMQ və Redis-in message broker olaraq müqayisəsini tam əhatə edir. Hər iki texnologiyanın güclü və zəif tərəflərini bilmək, düzgün seçim etməyə kömək edəcək.

---

## Anti-patternlər

**1. Redis-i Kritik İş Axınları üçün Message Broker Kimi İstifadə**
Ödəniş emalı, sifariş yaratma kimi itkisiz mesaj tələb edən axınlarda Redis queue istifadəsi — Redis restart zamanı `RPOPLPUSH` əsaslı queue-larda mesaj itirilə bilər, persistency zəifdir. Kritik iş axınları üçün durable queue-ları olan RabbitMQ seçin.

**2. RabbitMQ-da Mesaj Acknowledgement-i Atlamaq**
Consumer mesajı aldıqdan sonra `ack` göndərməmək — mesaj queue-da qalır, bütün consumer-lara yenidən göndərilir, sonsuz emal dövrü yaranır. Hər uğurlu emaldan sonra `ack`, xəta halında `nack` (requeue=false) + DLQ göndərin.

**3. Queue Uzunluğunu Monitorinq Etməmək**
Queue dərinliyini izləmədən istehsal mühitini idarə etmək — consumer-lar geridə qalır, mesajlar toplanır, sistemin nə vaxt çöküşə yaxınlaşdığı bilinmir. Laravel Horizon (Redis), RabbitMQ Management UI, Prometheus alerting ilə queue depth monitorinq qurun.

**4. Bir Queue-da Həm Prioritetli Həm Normal Mesajları Qarışdırmaq**
Kritik bildirişlər ilə kütləvi email göndərməni eyni queue-da emal etmək — kütləvi email dolduqda kritik mesajlar gecikir. Prioritetlərə görə ayrı queue-lar yaradın: `critical`, `default`, `bulk`; worker-ları müvafiq prioritetlə konfiqurasiya edin.

**5. Retry Strategiyasız İstehsala Çıxmaq**
Uğursuz job-lar üçün `tries`, `backoff`, `maxExceptions` konfiqurasiya etmədən deploy etmək — müvəqqəti xətalarda job dərhal fail olur, ya da sonsuz retry ilə resurs israf edilir. Exponential backoff ilə retry, maksimum cəhd sayı və DLQ mütləq konfiqurasiya edilməlidir.

**6. Mesaj Formatını Versiyalamamaq**
Queue-ya göndərilən mesajın strukturunu versiyalamadan dəyişmək — köhnə formatda queue-da gözləyən mesajlar yeni consumer tərəfindən parse edilə bilmir. Mesajlara `version` sahəsi əlavə edin; köhnə format dəstəyini tam geçiş baş verənə qədər saxlayın.

**7. Consumer Prefetch Count-u Konfiqurasiya Etməmək**
RabbitMQ-da `prefetch_count = 0` (unlimited) — consumer davamlı yavaş işləyirsə bütün mesajları öz buffer-ına çəkir, digər consumer-lar boş qalır, rebalancing olmur. `channel.basic_qos(prefetch_count=10)` kimi məqbul dəyər seçin — consumer yalnız emal edə biləcəyi qədər mesaj alsın.

**8. RabbitMQ Virtual Host İzolyasiyasını İstifadə Etməmək**
Bütün mühitlər (dev, staging, production) üçün eyni virtual host istifadə etmək — exchange/queue adları toqquşur, test mesajları production queue-na gedir. Hər mühit üçün ayrı vhost yaradın: `/dev`, `/staging`, `/production`. Credentials da mühitə görə ayrı olmalıdır.
