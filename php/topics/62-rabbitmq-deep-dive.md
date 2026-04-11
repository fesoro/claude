# RabbitMQ — Dərin Analiz

## Mündəricat
1. [Əsas Konseptlər](#əsas-konseptlər)
2. [Exchange Növləri](#exchange-növləri)
3. [Dead Letter Queue](#dead-letter-queue)
4. [Prefetch və Consumer Scaling](#prefetch-və-consumer-scaling)
5. [Competing Consumers](#competing-consumers)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [Reliability Patterns](#reliability-patterns)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Əsas Konseptlər

```
// Bu kod RabbitMQ-nun əsas komponentlərini və mesaj axınını izah edir
Producer → Exchange → Binding → Queue → Consumer

┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Producer │───►│ Exchange │───►│  Queue   │───►│ Consumer │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
                     │
                Routing rules
                (binding key)

Komponentlər:
  Producer: mesaj göndərən
  Exchange: mesajı queue-lara yönləndirir (routing)
  Queue: mesajları saxlayır
  Consumer: mesajları oxuyur
  Binding: exchange → queue əlaqəsi (routing key ilə)
  Virtual Host (vhost): məntiqi ayrılma
  
ACK/NACK:
  Consumer mesajı işlədikdən sonra ACK göndərir
  ACK olmadan mesaj queue-da qalır
  NACK: rədd et (requeue: true/false)
```

---

## Exchange Növləri

### 1. Direct Exchange

```
// Bu kod Direct Exchange-in routing key əsaslı mesaj yönləndirilməsini göstərir
Routing key tam uyğun gəlməlidir.

Producer → Exchange(routing_key="order.paid")
  → Queue "payments" (binding_key="order.paid") ✅
  → Queue "emails"   (binding_key="order.created") ❌

┌──────────┐  order.paid   ┌────────────────┐  order.paid  ┌───────────┐
│ Producer │──────────────►│ Direct Exchange│─────────────►│ payments  │
└──────────┘               └────────────────┘              └───────────┘
                                    │
                                    │ order.shipped  ┌───────────┐
                                    └───────────────►│ shipping  │
                                                     └───────────┘
Nümunə: Task queue, spesifik handler-lar
```

### 2. Fanout Exchange

```
// Bu kod Fanout Exchange-in bütün bağlı queue-lara broadcast etməsini göstərir
Routing key ignore edilir. Bütün bağlı queue-lara göndər.

                          ┌──────────────┐
                      ┌──►│  email-queue │
Producer → Fanout ────┼──►│  sms-queue   │
                      └──►│  push-queue  │

Nümunə: Broadcast notifications, event fanout
```

### 3. Topic Exchange

```
// Bu kod Topic Exchange-in wildcard pattern matching ilə routing mexanizmini göstərir
Routing key pattern matching (wildcard):
  * — bir söz
  # — sıfır və ya bir neçə söz

Producer: routing_key = "order.paid.az"

Binding patterns:
  "order.*"     → order.paid ✅, order.created ✅, order.paid.az ❌
  "order.#"     → order.paid ✅, order.paid.az ✅, order.x.y.z ✅
  "*.paid.*"    → order.paid.az ✅, user.paid.eu ✅
  "#.az"        → order.paid.az ✅, anything.az ✅

┌──────────┐ order.paid.az ┌───────────────┐
│ Producer │──────────────►│ Topic Exchange│──► "order.#" queue ✅
└──────────┘               └───────────────┘──► "*.paid.*" queue ✅
                                           └──► "user.*" queue ❌

Nümunə: Çevikli routing, log aggregation
```

### 4. Headers Exchange

```
// Bu kod Headers Exchange-in mesaj header-larına əsaslanan routing mexanizmini izah edir
Header-lara görə routing (routing key istifadə edilmir):

Producer mesajı header ilə göndərir:
  headers: {region: "eu", type: "payment"}

Binding:
  x-match: all  → bütün header-lar uyğun gəlməlidir
  x-match: any  → biri uyğun gəlsə kifayət

Nümunə: Complex routing logic, multi-attribute filtering
```

---

## Dead Letter Queue

```
// Bu kod mesajların Dead Letter Queue-ya düşmə şərtlərini və DLQ arxitekturasını göstərir
Mesaj "dead" olur:
  1. Consumer NACK + requeue: false
  2. TTL keçdi (message expires)
  3. Queue max-length dolub (oldest dropped)

DLQ arxitekturası:
┌──────────┐    ┌────────────┐    ┌─────────────┐
│ Producer │───►│ Main Queue │───►│  Consumer   │
└──────────┘    └─────┬──────┘    └──────┬──────┘
                      │ dead               │ NACK
                      ▼                   │ (after 3 retries)
               ┌──────────────┐           │
               │   DLQ        │◄──────────┘
               │ (dead-letter)│
               └──────┬───────┘
                      │
               ┌──────▼───────┐
               │ DLQ Consumer │ (manual review / alert)
               └──────────────┘
```

*└──────────────┘ üçün kod nümunəsi:*
```php
// Bu kod DLQ konfiqurasiyasını, TTL və max-length parametrlərini göstərir
// Queue declaration with DLQ
$channel->queue_declare(
    'main-queue',
    false,  // passive
    true,   // durable
    false,  // exclusive
    false,  // auto_delete
    false,  // nowait
    new AMQPTable([
        'x-dead-letter-exchange'    => 'dlx',          // DLX exchange
        'x-dead-letter-routing-key' => 'dead-letters', // DLQ routing key
        'x-message-ttl'             => 30000,           // 30s TTL
        'x-max-length'              => 10000,           // Max 10K mesaj
    ])
);

// DLX exchange və DLQ queue
$channel->exchange_declare('dlx', 'direct', false, true);
$channel->queue_declare('dead-letters', false, true);
$channel->queue_bind('dead-letters', 'dlx', 'dead-letters');
```

---

## Prefetch və Consumer Scaling

```
// Bu kod prefetch dəyərinin consumer yük balansına təsirini izah edir
Prefetch — consumer-ın eyni anda neçə mesaj alacağı:

prefetch=1 (fair dispatch):
  Consumer mesajı bitirməmiş yenisini almır
  Ağır mesajlar bir consumer-ı bloklamır
  
  Consumer1: [msg1____slow____] [msg4]
  Consumer2: [msg2] [msg3] [msg5]  ← daha çevik
  
prefetch=10:
  Consumer eyni anda 10 mesaj alır (buffer)
  Sürətli consumer daha çox iş görür
  Lakin bir consumer çöksə 10 mesaj itirilə bilər (requeued)

prefetch=0:
  Limit yoxdur — bütün mesajlar bir consumer-a axır ❌
```

*Limit yoxdur — bütün mesajlar bir consumer-a axır ❌ üçün kod nümunəsi:*
```php
// Bu kod PHP-də channel üçün prefetch count-un təyin edilməsini göstərir
// Prefetch count set et
$channel->basic_qos(
    0,    // prefetch_size (bytes, 0=unlimited)
    1,    // prefetch_count — eyni anda 1 mesaj
    false // global (false=per consumer, true=per channel)
);
```

---

## Competing Consumers

```
// Bu kod competing consumers pattern-ini və paralel işləmənin üstünlüklərini göstərir
Eyni queue-dan bir neçə consumer oxuyur:

Queue: [msg1, msg2, msg3, msg4, msg5]

Consumer1 ──────► msg1, msg3, msg5
Consumer2 ──────► msg2, msg4

✅ Paralel processing
✅ Consumer çöksə digərləri davam edir
✅ Auto load balancing

Mühüm: Mesajlar idempotent işlənməlidir!
(Requeue zamanı eyni mesaj başqa consumer-a gedir)
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod PHP-də RabbitMQ connection, publisher və retry/DLQ mexanizmi olan consumer-ı göstərir
// composer require php-amqplib/php-amqplib

class RabbitMQConnection
{
    private static ?AMQPStreamConnection $connection = null;
    
    public static function get(): AMQPStreamConnection
    {
        if (!self::$connection || !self::$connection->isConnected()) {
            self::$connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost'),
            );
        }
        return self::$connection;
    }
}

// Publisher
class EventPublisher
{
    public function publish(string $exchange, string $routingKey, array $data): void
    {
        $connection = RabbitMQConnection::get();
        $channel    = $connection->channel();
        
        $channel->exchange_declare($exchange, 'topic', false, true, false);
        
        $message = new AMQPMessage(
            json_encode($data),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,  // Disk-ə yaz
                'message_id'    => Str::uuid()->toString(),
                'timestamp'     => time(),
                'content_type'  => 'application/json',
            ]
        );
        
        $channel->basic_publish($message, $exchange, $routingKey);
        $channel->close();
    }
}

// Consumer with retry + DLQ
class OrderEventConsumer
{
    private const MAX_RETRIES = 3;
    
    public function consume(): void
    {
        $connection = RabbitMQConnection::get();
        $channel    = $connection->channel();
        
        // Prefetch
        $channel->basic_qos(0, 1, false);
        
        // Queue with DLQ
        $channel->queue_declare('order-events', false, true, false, false, false,
            new AMQPTable([
                'x-dead-letter-exchange'    => 'dlx',
                'x-dead-letter-routing-key' => 'order-events.dead',
                'x-message-ttl'             => 60000,
            ])
        );
        
        $channel->basic_consume(
            'order-events',
            '',     // consumer tag
            false,  // no_local
            false,  // no_ack — manual ACK!
            false,  // exclusive
            false,  // nowait
            function (AMQPMessage $message) use ($channel) {
                $this->handleMessage($message, $channel);
            }
        );
        
        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }
    
    private function handleMessage(AMQPMessage $message, AMQPChannel $channel): void
    {
        $data = json_decode($message->getBody(), true);
        
        // Retry count
        $headers    = $message->get('application_headers');
        $retryCount = $headers ? ($headers->getNativeData()['x-retry-count'] ?? 0) : 0;
        
        try {
            $this->processEvent($data);
            $message->ack();  // Uğurlu
        } catch (\Exception $e) {
            Log::error('Event processing failed', [
                'error' => $e->getMessage(),
                'retry' => $retryCount,
            ]);
            
            if ($retryCount < self::MAX_RETRIES) {
                // Delay ilə yenidən queue-a at
                $this->republishWithDelay($message, $channel, $retryCount + 1);
                $message->ack();  // Orijinalı sil
            } else {
                // Max retry → DLQ-ya göndər (NACK + no requeue)
                $message->nack(false);
            }
        }
    }
    
    private function republishWithDelay(
        AMQPMessage $original,
        AMQPChannel $channel,
        int $retryCount
    ): void {
        $delay = [0, 5000, 30000, 120000][$retryCount] ?? 120000; // ms
        
        // Delay queue (TTL + DLX trick)
        $delayQueue = "order-events.delay.$delay";
        $channel->queue_declare($delayQueue, false, true, false, false, false,
            new AMQPTable([
                'x-message-ttl'             => $delay,
                'x-dead-letter-exchange'    => '',
                'x-dead-letter-routing-key' => 'order-events',
            ])
        );
        
        $newMessage = new AMQPMessage(
            $original->getBody(),
            array_merge($original->get_properties(), [
                'application_headers' => new AMQPTable(['x-retry-count' => $retryCount]),
            ])
        );
        
        $channel->basic_publish($newMessage, '', $delayQueue);
    }
}
```

---

## Reliability Patterns

```
// Bu kod publisher confirms, durable queue və quorum queue kimi etibarlılıq pattern-lərini izah edir
Publisher confirms:
  Broker mesajı aldığını confirm edir
  Yoksa producer retry edir

Consumer ACK:
  İşlədikdən sonra ACK
  Server restart olsa unACKed mesajlar requeue olur

Durable queues + persistent messages:
  Queue broker restart-da qalır
  Message disk-ə yazılır

Quorum queues (RabbitMQ 3.8+):
  Raft consensus algorithm
  N/2+1 node agree etməlidir
  Daha reliable, amma daha yavaş
```

*Daha reliable, amma daha yavaş üçün kod nümunəsi:*
```php
// Bu kod PHP-də publisher confirms callback-lərini ACK/NACK handler-larla göstərir
// Publisher confirms
$channel->confirm_select();

$channel->basic_publish($message, $exchange, $routingKey);

$channel->wait_for_pending_acks(5.0);  // 5s timeout

// Callback
$channel->set_ack_handler(function (AMQPMessage $message) {
    Log::info('Message confirmed: ' . $message->getDeliveryTag());
});

$channel->set_nack_handler(function (AMQPMessage $message) {
    Log::error('Message nacked: ' . $message->getDeliveryTag());
    // Retry logic
});
```

---

## İntervyu Sualları

**1. RabbitMQ-nun 4 exchange növünü izah et.**
Direct: routing key tam uyğunluq. Fanout: hamıya göndər, routing key yoxdur. Topic: wildcard pattern (* bir söz, # çox söz). Headers: mesaj header-larına görə routing. Ən çox istifadə: direct (task queues) və topic (event routing).

**2. DLQ nədir, nə zaman lazımdır?**
Dead Letter Queue. Mesaj işlənə bilmədikdə (NACK, TTL, max-length) DLX exchange vasitəsilə DLQ-ya köçürülür. Manual yoxlama, debug, replay. Max retry keçdikdə alert göndər.

**3. Prefetch count niyə vacibdir?**
Prefetch=0 bütün mesajları bir consumer-a göndərir — digərləri boş qalar. Prefetch=1 fair dispatch: consumer işini bitirməmiş yeni mesaj almır. Prefetch=N buffer — sürətli consumer daha çox alır. Production üçün adətən 1-10 arasında.

**4. ACK vs NACK fərqi nədir?**
ACK: mesaj uğurla işləndi, queue-dan sil. NACK(requeue=true): xəta, mesajı queue-a geri qaytar. NACK(requeue=false): mesajı DLQ-ya göndər. Mesajı ACK etmədən consumer çöksə, broker mesajı requeue edir.

**5. Publisher confirms nədir?**
Producer mesajı göndərəndən sonra broker-in ACK-ini gözləyir. ACK gəlmədən mesajın çatdırıldığına əmin olmaq olmaz. Confirm olmadan fire-and-forget — itə bilər. At-least-once delivery üçün confirms + idempotent consumer lazımdır.

**6. Quorum queue vs Classic (durable) queue fərqi nədir?**
Classic durable: disk-ə yazılır, amma master node çöksə data itirilə bilər (mirror-lanmamışsa). Quorum queue (3.8+): Raft consensus — N/2+1 node razılaşmalıdır, daha yüksək durability. Yeni layihələrdə quorum queue tövsiyə edilir; daha az throughput amma daha etibarlı.

**7. `x-message-ttl` vs per-message TTL fərqi nədir?**
`x-message-ttl` queue-ya tətbiq edilir — bütün mesajlar eyni TTL alır. Per-message TTL (`expiration` property): hər mesaj öz TTL-ini daşıyır. İkisi mövcuddursa daha kiçik olan seçilir. TTL bitən mesaj DLQ-ya köçürülür (x-dead-letter-exchange konfiqurədirsə).

**8. Virtual Host (vhost) nə üçün istifadə edilir?**
Eyni broker üzərində məntiqi izolyasiya: fərqli team-lər, fərqli mühitlər (staging/prod) ayrı vhost-da. Hər vhost öz exchange, queue, permission sisteminə sahibdir. `/` default vhost — production-da ayrı vhost istifadə edin.

---

## Anti-patternlər

**1. Publisher confirms olmadan mesaj göndərmək**
`$channel->basic_publish(...)` çağırıb təsdiqi gözləməmək — broker tam almamış network kəsilsə mesaj itirilir, producer uğurlu hesab edir. Publisher confirms aktiv edin: broker ACK verənə qədər mesajı göndərilmiş saymayın; Outbox Pattern ilə daha etibarlı edin.

**2. Consumer-ları idempotent yazmamaq**
At-least-once delivery — eyni mesaj iki dəfə gəlir, handler iki dəfə ödəniş edir ya da iki dəfə email göndərir. Hər consumer-a Inbox Pattern tətbiq edin: mesaj ID-si DB-də unikal saxlanılsın, işlənibsə skip edilsin.

**3. DLQ (Dead Letter Queue) konfiqurasiya etməmək**
İşlənə bilməyən mesaj NACK + requeue=true ilə sonsuz dövrə girir — queue dolur, işçi resurslar tükənir, sistemin qalan hissəsi yavaşlayır. Hər queue üçün DLX exchange və DLQ konfigurasiya edin; max-retry keçdikdə mesaj DLQ-ya getsin, manual analiz üçün saxlansın.

**4. Prefetch dəyərini çox yüksək qoymaq**
`prefetch=500` — yavaş consumer 500 mesajı özündə saxlayır, digər consumer-lar boş dayanır, load imbalance yaranır. `prefetch=1` ilə başlayın (fair dispatch); performans ölçümündən sonra `10-50` arasında tənzimləyin.

**5. Fanout exchange ilə kritik iş logic-ini göndərmək**
Bütün subscriber-lara eyni mesaj göndərmək, biri fail olsa retry etmək — fanout at-most-once delivery verir, müxtəlif subscriber-ların fərqli retry siyasəti olmur. Kritik iş mesajları üçün direct ya da topic exchange istifadə edin; hər queue ayrıca retry siyasəti alsın.

**6. Queue-ları `durable: false` olaraq yaratmaq**
RabbitMQ restart olunanda queue silinir, içindəki bütün mesajlar itirilir. Production queue-larını `durable: true` ilə yaradın; mesajları da `delivery_mode: 2` (persistent) ilə göndərin ki, broker restart-dan sonra da qalsın.
