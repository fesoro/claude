# Dead Letter Queue (DLQ) və Poison Message Handling

## DLQ nədir?

Dead Letter Queue — işlənə bilməyən, uğursuz olan və ya müəyyən vaxt ərzində consume edilməyən mesajların yönləndirildiyi xüsusi queue-dur. Normal queue-dan fərqli olaraq DLQ-ya mesajlar birbaşa publisher tərəfindən deyil, broker tərəfindən avtomatik köçürülür.

**Niyə lazımdır?** Əgər consumer mesajı emal edə bilmirsə (exception, format xətası, business logic uğursuzluğu), mesaj queue-da qalır və dəfələrlə retry olunur. Bu sonsuz dövrə (infinite retry loop) sistemi bloklayır, resursları israf edir və digər mesajların işlənməsinə mane olur.

```
Normal axın:
┌──────────┐     ┌─────────┐     ┌──────────┐
│ Producer │────>│  Queue   │────>│ Consumer │──── OK ────> İşləndi ✅
└──────────┘     └─────────┘     └──────────┘

DLQ axını:
┌──────────┐     ┌─────────┐     ┌──────────┐
│ Producer │────>│  Queue   │────>│ Consumer │──── FAIL (3x retry) ──┐
└──────────┘     └─────────┘     └──────────┘                       │
                                                                     │
                 ┌─────────┐     ┌───────────────┐                  │
                 │   DLQ   │<────│ Broker/Worker  │<────────────────┘
                 └─────────┘     └───────────────┘
                      │
                      ▼
               Manual Review /
               Reprocessing /
               Alerting
```

**DLQ-ya mesajın düşmə səbəbləri:**

| Səbəb | Açıqlama | Nümunə |
|-------|----------|--------|
| Retry limitinin aşılması | Mesaj N dəfə retry olunub uğursuz | Consumer exception atır |
| TTL-in dolması | Mesaj müəyyən vaxtda consume edilməyib | Queue çox dolu, consumer yavaş |
| Queue ölçü limiti | Queue max uzunluğa çatıb | Overflow policy: reject-publish |
| Manual reject | Consumer mesajı qəsdən reject edib | Validation uğursuz |
| Routing uğursuzluğu | Mesaj heç bir queue-ya route olmayıb | Exchange-də uyğun binding yoxdur |

---

## Poison Message nədir?

Poison Message — nə qədər retry etsən də heç vaxt uğurlu işlənə bilməyən mesajdır. Bu mesajlar sistemdə "zəhər" kimi yayılır: queue-nu bloklayır, consumer-ləri məşğul edir və digər normal mesajların işlənməsini gecikdirir.

```
Poison Message dövrəsi (DLQ olmadan):

    ┌──────────────────────────────────────┐
    │                                      │
    ▼                                      │
┌─────────┐     ┌──────────┐              │
│  Queue   │────>│ Consumer │── Exception ─┘
└─────────┘     └──────────┘     (NACK + requeue)
    │
    │  Mesaj queue-nun başına qayıdır
    │  Sonsuz dövrə: CPU, memory israfı
    │  Digər mesajlar gözləyir
    ▼
  PROBLEM! ❌
```

**Poison Message-in tipik səbəbləri:**

1. **Malformed data** — JSON parse olunmur, schema uyğun deyil
2. **Missing dependency** — mesajda reference olunan entity DB-də yoxdur
3. **Business rule violation** — iş məntiqi icazə vermir (məsələn, silinmiş user-ə notification)
4. **Version mismatch** — producer yeni format göndərir, consumer köhnə format gözləyir
5. **Data corruption** — serialization/deserialization xətası
6. **Size limit** — mesaj çox böyükdür, consumer yaddaşı çatmır

```php
// Poison Message nümunəsi:
// Producer göndərir:
$message = json_encode([
    'user_id' => 'not-a-number',  // ← integer olmalı idi
    'action' => 'charge',
    'amount' => -500,              // ← mənfi məbləğ
    'currency' => null,            // ← null olmamalı
]);

// Consumer qəbul edir:
public function handle(ChargeUserMessage $message): void
{
    $user = User::findOrFail($message->user_id);  // ← Exception: string id
    
    if ($message->amount <= 0) {
        throw new InvalidArgumentException('Amount must be positive');
    }
    
    // Bu mesaj heç vaxt uğurlu olmayacaq
    // Retry etməyin mənası yoxdur
}
```

**Poison Message vs Transient Failure fərqi:**

| Xüsusiyyət | Poison Message | Transient Failure |
|-------------|---------------|-------------------|
| Retry faydalıdır? | Xeyr | Bəli |
| Səbəb | Data/logic xətası | İnfrastruktur xətası |
| Nümunə | Yanlış format | DB timeout |
| Həll | DLQ + manual fix | Retry + backoff |
| Tezlik | Nadir | Tez-tez |

---

## DLQ Strategiyaları

### Strategiya 1: Sadə Retry → DLQ

Ən geniş yayılmış yanaşma: mesajı N dəfə retry et, uğursuz olarsa DLQ-ya köçür.

```
Retry strategiyası:

  Mesaj gəlir
      │
      ▼
  ┌────────┐   Uğurlu    ┌──────┐
  │Consumer│────────────>│ Done │
  └────────┘             └──────┘
      │
      │ Uğursuz (Exception)
      ▼
  ┌─────────────┐
  │ Retry #1    │  (1 saniyə sonra)
  │ delay: 1s   │
  └─────────────┘
      │ Uğursuz
      ▼
  ┌─────────────┐
  │ Retry #2    │  (5 saniyə sonra)
  │ delay: 5s   │
  └─────────────┘
      │ Uğursuz
      ▼
  ┌─────────────┐
  │ Retry #3    │  (30 saniyə sonra)
  │ delay: 30s  │
  └─────────────┘
      │ Uğursuz
      ▼
  ┌─────────┐     ┌────────────────┐
  │   DLQ   │────>│ Manual Review  │
  └─────────┘     └────────────────┘
```

### Strategiya 2: Immediate DLQ (Poison Detection)

Bəzi xətalar retry-a dəyməz. Onları dərhal DLQ-ya göndərmək daha səmərəlidir.

```php
public function handle(object $message): void
{
    try {
        $this->process($message);
    } catch (ValidationException $e) {
        // Retry etmənin mənası yoxdur — dərhal DLQ
        throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
    } catch (TemporaryException $e) {
        // Bu retry oluna bilər — exception rethrow
        throw $e;
    }
}
```

### Strategiya 3: Graduated DLQ (Çox Səviyyəli)

Mürəkkəb sistemlərdə mesajlar fərqli DLQ-lara yönləndirilir:

```
                        ┌──────────────────┐
                        │  Main Queue      │
                        └────────┬─────────┘
                                 │
                    ┌────────────┼────────────┐
                    │            │             │
                    ▼            ▼             ▼
            ┌───────────┐ ┌───────────┐ ┌───────────┐
            │ Retry     │ │ DLQ       │ │ Poison    │
            │ Queue     │ │ (review)  │ │ Queue     │
            │           │ │           │ │ (discard) │
            │ transient │ │ business  │ │ malformed │
            │ errors    │ │ errors    │ │ data      │
            └───────────┘ └───────────┘ └───────────┘
                 │              │              │
                 ▼              ▼              ▼
            Auto-retry    Human review    Log + Delete
            (backoff)     (dashboard)     (metrics)
```

### Strategiya 4: DLQ + Parking Lot

Çox uzun müddət DLQ-da qalan mesajlar "parking lot"-a köçürülür. Bu mesajlar artıq aktual olmaya bilər.

```
Main Queue → retry → DLQ → (7 gün sonra) → Parking Lot
                              │
                              ├── Alert göndərilir
                              ├── Dashboard-da görünür
                              └── Manual reprocess mümkündür
```

---

## RabbitMQ DLQ Implementation

RabbitMQ-da DLQ mexanizmi `dead-letter-exchange` (DLX) vasitəsilə işləyir. Mesaj reject olunanda və ya TTL bitəndə broker onu avtomatik DLX-ə yönləndirir.

### Əsas Konseptlər

```
RabbitMQ DLQ Arxitekturası:

Producer
   │
   ▼
┌──────────────────┐
│  Main Exchange   │
│  (direct/topic)  │
└────────┬─────────┘
         │ routing_key: "orders"
         ▼
┌──────────────────────────────────────────────┐
│  Main Queue: "orders_queue"                  │
│                                              │
│  Arguments:                                  │
│    x-dead-letter-exchange: "dlx_exchange"    │
│    x-dead-letter-routing-key: "orders.dead"  │
│    x-message-ttl: 60000 (60 saniyə)         │
│    x-max-length: 10000                       │
└────────┬─────────────────────────┬───────────┘
         │                         │
         │ Normal consume          │ NACK / TTL expired /
         ▼                         │ Queue full
    ┌──────────┐                   ▼
    │ Consumer │          ┌──────────────────┐
    └──────────┘          │  DLX Exchange    │
                          │  "dlx_exchange"  │
                          └────────┬─────────┘
                                   │ routing_key: "orders.dead"
                                   ▼
                          ┌──────────────────┐
                          │  DLQ             │
                          │  "orders_dlq"    │
                          └──────────────────┘
                                   │
                                   ▼
                          DLQ Consumer / Dashboard
```

### RabbitMQ Queue Declaration (PHP)

```php
<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQDLQSetup
{
    private AMQPStreamConnection $connection;
    private \PhpAmqpLib\Channel\AMQPChannel $channel;

    public function __construct(
        string $host = 'localhost',
        int $port = 5672,
        string $user = 'guest',
        string $password = 'guest'
    ) {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->channel = $this->connection->channel();
    }

    /**
     * DLX Exchange və DLQ yaradılması
     */
    public function setupDeadLetterInfrastructure(): void
    {
        // 1. DLX Exchange yaradılması
        $this->channel->exchange_declare(
            exchange: 'dlx_exchange',
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // 2. DLQ yaradılması (mesajlar burada yığılacaq)
        $this->channel->queue_declare(
            queue: 'orders_dlq',
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: new AMQPTable([
                // DLQ-dakı mesajlar da TTL ilə idarə oluna bilər
                'x-message-ttl' => 604800000, // 7 gün (ms)
                'x-max-length'  => 50000,     // max 50k mesaj
            ])
        );

        // 3. DLQ-nu DLX Exchange-ə bind etmə
        $this->channel->queue_bind(
            queue: 'orders_dlq',
            exchange: 'dlx_exchange',
            routing_key: 'orders.dead'
        );
    }

    /**
     * Main Queue yaradılması (DLX konfiqurasiya ilə)
     */
    public function setupMainQueue(): void
    {
        // Main exchange
        $this->channel->exchange_declare(
            exchange: 'main_exchange',
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Main queue — DLX parametrləri ilə
        $this->channel->queue_declare(
            queue: 'orders_queue',
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: new AMQPTable([
                // Reject/NACK olunan mesajlar bu exchange-ə gedəcək
                'x-dead-letter-exchange'    => 'dlx_exchange',
                'x-dead-letter-routing-key' => 'orders.dead',

                // Optional: TTL (mesaj bu qədər müddətdə consume olunmazsa DLQ-ya düşür)
                'x-message-ttl' => 60000, // 60 saniyə

                // Optional: Queue max uzunluğu (aşıldıqda köhnə mesajlar DLQ-ya düşür)
                'x-max-length'  => 10000,

                // Optional: Overflow davranışı
                'x-overflow' => 'reject-publish-dlx',
            ])
        );

        // Main queue-nu exchange-ə bind etmə
        $this->channel->queue_bind(
            queue: 'orders_queue',
            exchange: 'main_exchange',
            routing_key: 'orders'
        );
    }

    /**
     * Retry Queue ilə Delayed Retry mexanizmi
     *
     * Mesaj → Main Queue → FAIL → Retry Queue (TTL) → Main Queue → ...
     * N retry-dan sonra → DLQ
     */
    public function setupRetryMechanism(): void
    {
        // Retry exchange
        $this->channel->exchange_declare(
            exchange: 'retry_exchange',
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Retry queue — TTL bitəndə mesajı main queue-ya qaytarır
        $this->channel->queue_declare(
            queue: 'orders_retry_queue',
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: new AMQPTable([
                // TTL bitəndə mesaj main exchange-ə qayıdır
                'x-dead-letter-exchange'    => 'main_exchange',
                'x-dead-letter-routing-key' => 'orders',
                'x-message-ttl'             => 5000, // 5 saniyə gözlə
            ])
        );

        $this->channel->queue_bind(
            queue: 'orders_retry_queue',
            exchange: 'retry_exchange',
            routing_key: 'orders.retry'
        );
    }

    public function close(): void
    {
        $this->channel->close();
        $this->connection->close();
    }
}
```

### RabbitMQ Consumer — Retry + DLQ

```php
<?php

use PhpAmqpLib\Message\AMQPMessage;

class OrderConsumerWithDLQ
{
    private const MAX_RETRIES = 3;
    private \PhpAmqpLib\Channel\AMQPChannel $channel;

    public function consume(): void
    {
        $callback = function (AMQPMessage $msg) {
            $headers = $msg->has('application_headers')
                ? $msg->get('application_headers')->getNativeData()
                : [];

            $retryCount = $this->getRetryCount($msg);
            $messageId  = $msg->has('message_id') ? $msg->get('message_id') : uniqid();

            echo "[Consumer] Message received (ID: {$messageId}, retry: {$retryCount})\n";

            try {
                $payload = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);

                $this->processOrder($payload);

                // Uğurlu — ACK
                $msg->ack();
                echo "[Consumer] Message processed successfully ✓\n";

            } catch (\JsonException $e) {
                // JSON parse xətası — poison message, retry etmənin mənası yoxdur
                echo "[Consumer] Poison message detected: {$e->getMessage()}\n";
                $msg->nack(requeue: false); // DLQ-ya göndər
                $this->logPoisonMessage($messageId, $msg->getBody(), $e);

            } catch (ValidationException $e) {
                // Validation xətası — retry etmə, birbaşa DLQ
                echo "[Consumer] Validation error: {$e->getMessage()}\n";
                $msg->nack(requeue: false);
                $this->logPoisonMessage($messageId, $msg->getBody(), $e);

            } catch (TemporaryException $e) {
                // Müvəqqəti xəta — retry etmək mümkündür
                if ($retryCount < self::MAX_RETRIES) {
                    echo "[Consumer] Temporary error, sending to retry queue (attempt {$retryCount}/" . self::MAX_RETRIES . ")\n";
                    $this->sendToRetryQueue($msg, $retryCount + 1);
                    $msg->ack(); // Original mesajı ACK et
                } else {
                    echo "[Consumer] Max retries exceeded, sending to DLQ\n";
                    $msg->nack(requeue: false); // DLQ-ya göndər
                    $this->alertMaxRetriesExceeded($messageId, $e);
                }

            } catch (\Throwable $e) {
                // Gözlənilməz xəta
                if ($retryCount < self::MAX_RETRIES) {
                    $this->sendToRetryQueue($msg, $retryCount + 1);
                    $msg->ack();
                } else {
                    $msg->nack(requeue: false);
                    $this->alertMaxRetriesExceeded($messageId, $e);
                }
            }
        };

        $this->channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);
        $this->channel->basic_consume(
            queue: 'orders_queue',
            consumer_tag: '',
            no_local: false,
            no_ack: false,   // Manual ACK/NACK
            exclusive: false,
            nowait: false,
            callback: $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * x-death header-dən retry sayını oxu
     * RabbitMQ hər DLX keçidində x-death header əlavə edir
     */
    private function getRetryCount(AMQPMessage $msg): int
    {
        if (!$msg->has('application_headers')) {
            return 0;
        }

        $headers = $msg->get('application_headers')->getNativeData();

        // Custom retry header
        if (isset($headers['x-retry-count'])) {
            return (int) $headers['x-retry-count'];
        }

        // RabbitMQ-nun öz x-death header-i
        if (isset($headers['x-death'])) {
            $totalCount = 0;
            foreach ($headers['x-death'] as $death) {
                $totalCount += $death['count'] ?? 0;
            }
            return $totalCount;
        }

        return 0;
    }

    /**
     * Mesajı retry queue-ya göndər (delay ilə)
     */
    private function sendToRetryQueue(AMQPMessage $originalMsg, int $retryCount): void
    {
        $headers = new \PhpAmqpLib\Wire\AMQPTable([
            'x-retry-count'    => $retryCount,
            'x-original-queue' => 'orders_queue',
            'x-first-failure'  => $headers['x-first-failure'] ?? date('c'),
            'x-last-failure'   => date('c'),
        ]);

        $retryMsg = new AMQPMessage($originalMsg->getBody(), [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id'          => $originalMsg->has('message_id')
                                       ? $originalMsg->get('message_id')
                                       : uniqid('retry_'),
            'application_headers' => $headers,
        ]);

        $this->channel->basic_publish(
            msg: $retryMsg,
            exchange: 'retry_exchange',
            routing_key: 'orders.retry'
        );
    }

    private function processOrder(array $payload): void
    {
        // Business logic...
        if (empty($payload['order_id'])) {
            throw new ValidationException('order_id is required');
        }

        // DB əməliyyatı — müvəqqəti xəta ola bilər
        // $order = Order::findOrFail($payload['order_id']);
    }

    private function logPoisonMessage(string $messageId, string $body, \Throwable $e): void
    {
        error_log(sprintf(
            "[POISON] Message ID: %s, Error: %s, Body: %s",
            $messageId,
            $e->getMessage(),
            substr($body, 0, 500)
        ));
    }

    private function alertMaxRetriesExceeded(string $messageId, \Throwable $e): void
    {
        error_log(sprintf(
            "[DLQ_ALERT] Message ID: %s exceeded max retries. Error: %s",
            $messageId,
            $e->getMessage()
        ));
        // Slack/PagerDuty/email notification göndər
    }
}
```

### Exponential Backoff ilə Retry

```php
<?php

/**
 * Hər retry üçün fərqli TTL olan queue-lar:
 * Retry 1: 1s delay
 * Retry 2: 5s delay
 * Retry 3: 30s delay
 * Retry 4: 120s delay
 * Retry 5+: DLQ
 */
class ExponentialBackoffRetrySetup
{
    private const RETRY_DELAYS = [
        1 => 1000,    // 1 saniyə
        2 => 5000,    // 5 saniyə
        3 => 30000,   // 30 saniyə
        4 => 120000,  // 2 dəqiqə
    ];

    public function setup(\PhpAmqpLib\Channel\AMQPChannel $channel): void
    {
        // Hər delay üçün ayrı retry queue yaradılır
        foreach (self::RETRY_DELAYS as $level => $delayMs) {
            $queueName = "orders_retry_level_{$level}";

            $channel->queue_declare(
                queue: $queueName,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
                nowait: false,
                arguments: new \PhpAmqpLib\Wire\AMQPTable([
                    'x-dead-letter-exchange'    => 'main_exchange',
                    'x-dead-letter-routing-key' => 'orders',
                    'x-message-ttl'             => $delayMs,
                    // Bu queue-dan heç kim consume etmir
                    // TTL bitəndə mesaj avtomatik main queue-ya qayıdır
                ])
            );

            $channel->queue_bind(
                queue: $queueName,
                exchange: 'retry_exchange',
                routing_key: "orders.retry.level.{$level}"
            );
        }
    }

    public function getRoutingKeyForRetry(int $retryCount): ?string
    {
        if (isset(self::RETRY_DELAYS[$retryCount])) {
            return "orders.retry.level.{$retryCount}";
        }

        // Max retry aşılıb — DLQ-ya göndər (null qaytarırıq)
        return null;
    }
}
```

### Per-Message TTL ilə Delayed Retry (Plugin-siz)

```php
<?php

/**
 * Hər mesaj üçün fərqli TTL təyin etmə.
 * Bir ümumi retry queue istifadə olunur, amma mesajın özündə expiration header qoyulur.
 *
 * DİQQƏT: RabbitMQ mesajları queue-nun başından yoxlayır.
 * Əgər birinci mesajın TTL-i 60s, ikincinin 5s olarsa,
 * ikinci mesaj birincidən əvvəl expire olmaz (head-of-line blocking).
 * Buna görə fərqli TTL-lər üçün ayrı queue-lar tövsiyə olunur.
 */
class PerMessageTTLRetry
{
    private const BACKOFF_TABLE = [
        1 => 1,     // 1 saniyə
        2 => 5,     // 5 saniyə
        3 => 30,    // 30 saniyə
        4 => 120,   // 2 dəqiqə
        5 => 600,   // 10 dəqiqə
    ];

    public function sendToRetry(
        \PhpAmqpLib\Channel\AMQPChannel $channel,
        AMQPMessage $originalMsg,
        int $retryCount
    ): bool {
        $maxRetries = count(self::BACKOFF_TABLE);

        if ($retryCount > $maxRetries) {
            return false; // DLQ-ya göndərilməli
        }

        $delaySeconds = self::BACKOFF_TABLE[$retryCount];

        $headers = new \PhpAmqpLib\Wire\AMQPTable([
            'x-retry-count'   => $retryCount,
            'x-delay-seconds' => $delaySeconds,
            'x-original-exchange'    => 'main_exchange',
            'x-original-routing-key' => 'orders',
        ]);

        $msg = new AMQPMessage($originalMsg->getBody(), [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'expiration'          => (string) ($delaySeconds * 1000), // ms
            'application_headers' => $headers,
            'message_id'          => $originalMsg->has('message_id')
                                       ? $originalMsg->get('message_id')
                                       : uniqid(),
        ]);

        $channel->basic_publish(
            msg: $msg,
            exchange: 'retry_exchange',
            routing_key: 'orders.retry'
        );

        return true;
    }
}
```

---

## Laravel/PHP İmplementasiyası

### Laravel failed_jobs Cədvəli

Laravel-in built-in queue sistemi artıq DLQ konseptini dəstəkləyir. `failed_jobs` cədvəli əslində DLQ-nun DB-based versiyasıdır.

```
Laravel Queue Lifecycle:

  Job dispatch
      │
      ▼
  ┌─────────┐     ┌────────┐     ┌──────────┐
  │  Queue   │────>│ Worker │────>│ Job::     │── OK ──> Silinir
  │ (Redis/  │     │        │     │ handle() │
  │  SQS/    │     └────────┘     └──────────┘
  │  RabbitMQ)│                        │
  └─────────┘                         │ Exception
                                       ▼
                                  ┌──────────┐
                                  │ Retry    │ (--tries=3)
                                  └──────────┘
                                       │ Bütün retry-lar uğursuz
                                       ▼
                                  ┌──────────────┐
                                  │ failed()     │ method çağırılır
                                  │ failed_jobs  │ cədvəlinə yazılır
                                  └──────────────┘
                                       │
                                       ▼
                                  Manual review:
                                  php artisan queue:retry {id}
                                  php artisan queue:retry all
```

```bash
# failed_jobs cədvəli yaradılması
php artisan queue:failed-table
php artisan migrate

# Worker işlədilməsi (retry parametrləri ilə)
php artisan queue:work redis \
    --tries=3 \
    --backoff=5,30,120 \
    --max-time=3600 \
    --memory=256
```

### Laravel Job — Retry və DLQ Konfiqurasiyası

```php
<?php

namespace App\Jobs;

use App\Events\OrderFailed;
use App\Exceptions\UnrecoverableOrderException;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksimum retry sayı
     */
    public int $tries = 5;

    /**
     * Exponential backoff (saniyə)
     * Retry 1: 10s, Retry 2: 30s, Retry 3: 60s, Retry 4: 120s, Retry 5: 300s
     */
    public array $backoff = [10, 30, 60, 120, 300];

    /**
     * Job timeout (saniyə)
     */
    public int $timeout = 60;

    /**
     * Job-un ümumi yaşama müddəti (saniyə)
     * Bu müddətdən sonra artıq retry edilmir
     */
    public int $retryUntil = 3600; // 1 saat

    /**
     * Job unique ID — eyni order üçün dublikat job-ların qarşısını alır
     */
    public function uniqueId(): string
    {
        return "order_payment_{$this->orderId}";
    }

    public function __construct(
        private readonly int $orderId,
        private readonly float $amount,
        private readonly string $currency = 'USD'
    ) {}

    /**
     * Job emalı
     */
    public function handle(PaymentService $paymentService): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            // Order silinib — retry etmənin mənası yoxdur
            Log::warning("Order not found, releasing job", ['order_id' => $this->orderId]);
            $this->delete();
            return;
        }

        if ($order->isPaid()) {
            // Artıq ödənilib — idempotency
            Log::info("Order already paid, skipping", ['order_id' => $this->orderId]);
            $this->delete();
            return;
        }

        try {
            $result = $paymentService->charge(
                orderId: $this->orderId,
                amount: $this->amount,
                currency: $this->currency,
                idempotencyKey: "order_{$this->orderId}_payment"
            );

            $order->markAsPaid($result->transactionId);

            Log::info("Payment processed", [
                'order_id'       => $this->orderId,
                'transaction_id' => $result->transactionId,
            ]);

        } catch (InsufficientFundsException $e) {
            // İstifadəçinin balansı yoxdur — retry etmə
            Log::notice("Insufficient funds", ['order_id' => $this->orderId]);
            $this->fail($e); // Dərhal failed_jobs-a yaz
            event(new OrderFailed($this->orderId, 'insufficient_funds'));

        } catch (InvalidCardException $e) {
            // Kart etibarsızdır — retry etmə
            $this->fail($e);
            event(new OrderFailed($this->orderId, 'invalid_card'));

        } catch (PaymentGatewayTimeoutException $e) {
            // Gateway timeout — retry et
            Log::warning("Payment gateway timeout, will retry", [
                'order_id' => $this->orderId,
                'attempt'  => $this->attempts(),
            ]);
            throw $e; // Laravel avtomatik retry edəcək

        } catch (PaymentGatewayException $e) {
            // Ümumi gateway xətası — retry et
            throw $e;
        }
    }

    /**
     * Middleware: eyni order üçün paralel payment-ın qarşısını al
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->orderId),
        ];
    }

    /**
     * Bütün retry-lar uğursuz olduqda çağırılır (DLQ handler)
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessOrderPayment permanently failed", [
            'order_id'  => $this->orderId,
            'amount'    => $this->amount,
            'currency'  => $this->currency,
            'exception' => $exception->getMessage(),
            'attempts'  => $this->attempts(),
        ]);

        // Order statusunu yenilə
        Order::where('id', $this->orderId)->update([
            'status'       => 'payment_failed',
            'failure_reason' => substr($exception->getMessage(), 0, 500),
            'failed_at'    => now(),
        ]);

        // Notification göndər
        event(new OrderFailed($this->orderId, 'max_retries_exceeded'));

        // Slack alert
        // Notification::route('slack', config('services.slack.alert_channel'))
        //     ->notify(new PaymentFailedNotification($this->orderId, $exception));
    }

    /**
     * Job-un retry olunmalı olub-olmadığını yoxla
     * true qaytarsa retry olunacaq, false qaytarsa dərhal fail
     */
    public function shouldRetry(Throwable $exception): bool
    {
        // Bu xətalar üçün retry etmə
        $unrecoverable = [
            InsufficientFundsException::class,
            InvalidCardException::class,
            UnrecoverableOrderException::class,
        ];

        return !in_array(get_class($exception), $unrecoverable);
    }
}
```

### Custom DLQ Handler — Xüsusi DLQ Emalı

```php
<?php

namespace App\Services\Queue;

use App\Models\DeadLetterMessage;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DLQAlertNotification;

class DeadLetterQueueHandler
{
    private array $alertThresholds = [
        'warning'  => 10,   // 10 mesaj — warning
        'critical' => 50,   // 50 mesaj — critical
        'emergency' => 200, // 200 mesaj — emergency
    ];

    /**
     * Laravel event listener olaraq qeydiyyat
     */
    public function register(): void
    {
        Event::listen(JobFailed::class, [$this, 'handleFailedJob']);
    }

    /**
     * Failed job-u DLQ cədvəlinə yaz və alert göndər
     */
    public function handleFailedJob(JobFailed $event): void
    {
        $job = $event->job;
        $exception = $event->exception;

        // DB-yə ətraflı məlumat yaz
        $dlqMessage = DeadLetterMessage::create([
            'job_uuid'       => $job->uuid(),
            'queue'          => $job->getQueue(),
            'connection'     => $job->getConnectionName(),
            'job_class'      => $this->extractJobClass($job->payload()),
            'payload'        => json_encode($job->payload()),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_trace'   => substr($exception->getTraceAsString(), 0, 5000),
            'attempts'       => $job->attempts(),
            'failed_at'      => now(),
            'category'       => $this->categorizeError($exception),
            'is_poison'      => $this->isPoisonMessage($exception),
            'metadata'       => json_encode([
                'memory_usage' => memory_get_peak_usage(true),
                'php_version'  => PHP_VERSION,
                'hostname'     => gethostname(),
            ]),
        ]);

        Log::channel('dlq')->error("Job failed and moved to DLQ", [
            'dlq_id'    => $dlqMessage->id,
            'job_class' => $dlqMessage->job_class,
            'queue'     => $dlqMessage->queue,
            'category'  => $dlqMessage->category,
            'is_poison' => $dlqMessage->is_poison,
        ]);

        // DLQ dərinliyinə görə alert
        $this->checkAndAlert($dlqMessage->queue);
    }

    /**
     * Xətanı kateqoriyalara ayır
     */
    private function categorizeError(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \JsonException,
            $exception instanceof \TypeError,
            $exception instanceof \InvalidArgumentException
                => 'data_error',

            $exception instanceof \Illuminate\Database\QueryException,
            $exception instanceof \PDOException
                => 'database_error',

            $exception instanceof \Illuminate\Http\Client\ConnectionException,
            $exception instanceof \GuzzleHttp\Exception\ConnectException
                => 'network_error',

            $exception instanceof \Illuminate\Auth\AuthenticationException,
            $exception instanceof \Illuminate\Auth\Access\AuthorizationException
                => 'auth_error',

            $exception instanceof \OutOfMemoryError
                => 'resource_error',

            default => 'unknown',
        };
    }

    /**
     * Poison message-i təyin et
     */
    private function isPoisonMessage(\Throwable $exception): bool
    {
        $poisonExceptions = [
            \JsonException::class,
            \TypeError::class,
            \InvalidArgumentException::class,
            \LogicException::class,
        ];

        foreach ($poisonExceptions as $poisonClass) {
            if ($exception instanceof $poisonClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * DLQ dərinliyini yoxla və lazım olduqda alert göndər
     */
    private function checkAndAlert(string $queue): void
    {
        $depth = DeadLetterMessage::where('queue', $queue)
            ->where('resolved_at', null)
            ->count();

        $level = null;
        foreach (array_reverse($this->alertThresholds) as $alertLevel => $threshold) {
            if ($depth >= $threshold) {
                $level = $alertLevel;
                break;
            }
        }

        if ($level) {
            Notification::route('slack', config('services.slack.dlq_channel'))
                ->notify(new DLQAlertNotification($queue, $depth, $level));
        }
    }

    private function extractJobClass(array $payload): string
    {
        $command = unserialize($payload['data']['command'] ?? '');
        return $command ? get_class($command) : ($payload['displayName'] ?? 'Unknown');
    }
}
```

### DeadLetterMessage Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class DeadLetterMessage extends Model
{
    protected $table = 'dead_letter_messages';

    protected $fillable = [
        'job_uuid',
        'queue',
        'connection',
        'job_class',
        'payload',
        'exception_class',
        'exception_message',
        'exception_trace',
        'attempts',
        'failed_at',
        'category',
        'is_poison',
        'metadata',
        'resolved_at',
        'resolved_by',
        'resolution_note',
    ];

    protected $casts = [
        'payload'     => 'array',
        'metadata'    => 'array',
        'is_poison'   => 'boolean',
        'failed_at'   => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // ----- Scopes -----

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopePoison(Builder $query): Builder
    {
        return $query->where('is_poison', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', $queue);
    }

    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('failed_at', '<', now()->subDays($days));
    }

    // ----- Actions -----

    public function markResolved(string $resolvedBy, string $note = ''): void
    {
        $this->update([
            'resolved_at'     => now(),
            'resolved_by'     => $resolvedBy,
            'resolution_note' => $note,
        ]);
    }

    public function retry(): bool
    {
        try {
            $payload = $this->payload;
            $command = unserialize($payload['data']['command']);

            dispatch($command)->onQueue($this->queue)->onConnection($this->connection);

            $this->markResolved('system', 'Retried automatically');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ----- Statistics -----

    public static function statistics(): array
    {
        return [
            'total_unresolved' => static::unresolved()->count(),
            'poison_count'     => static::unresolved()->poison()->count(),
            'by_category'      => static::unresolved()
                ->selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'by_queue'         => static::unresolved()
                ->selectRaw('queue, count(*) as count')
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray(),
            'by_job_class'     => static::unresolved()
                ->selectRaw('job_class, count(*) as count')
                ->groupBy('job_class')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'job_class')
                ->toArray(),
            'oldest_unresolved' => static::unresolved()
                ->orderBy('failed_at')
                ->value('failed_at'),
        ];
    }
}
```

### Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dead_letter_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_uuid')->nullable()->index();
            $table->string('queue')->index();
            $table->string('connection');
            $table->string('job_class')->index();
            $table->longText('payload');
            $table->string('exception_class')->index();
            $table->text('exception_message');
            $table->text('exception_trace')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('failed_at')->index();
            $table->string('category')->default('unknown')->index();
            $table->boolean('is_poison')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->string('resolved_by')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['queue', 'resolved_at']);
            $table->index(['category', 'resolved_at']);
            $table->index(['is_poison', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_letter_messages');
    }
};
```

### Artisan Komandaları — DLQ İdarəetmə

```php
<?php

namespace App\Console\Commands;

use App\Models\DeadLetterMessage;
use Illuminate\Console\Command;

class DLQStatusCommand extends Command
{
    protected $signature = 'dlq:status {--queue= : Xüsusi queue üçün filter}';
    protected $description = 'DLQ statistikasını göstər';

    public function handle(): int
    {
        $stats = DeadLetterMessage::statistics();

        $this->info("=== Dead Letter Queue Status ===\n");

        $this->table(
            ['Metrik', 'Dəyər'],
            [
                ['Total unresolved', $stats['total_unresolved']],
                ['Poison messages', $stats['poison_count']],
                ['Oldest unresolved', $stats['oldest_unresolved'] ?? 'N/A'],
            ]
        );

        $this->info("\nBy Category:");
        $this->table(
            ['Kateqoriya', 'Say'],
            collect($stats['by_category'])->map(fn($count, $cat) => [$cat, $count])->values()->toArray()
        );

        $this->info("\nBy Queue:");
        $this->table(
            ['Queue', 'Say'],
            collect($stats['by_queue'])->map(fn($count, $q) => [$q, $count])->values()->toArray()
        );

        $this->info("\nTop Job Classes:");
        $this->table(
            ['Job Class', 'Say'],
            collect($stats['by_job_class'])->map(fn($count, $cls) => [$cls, $count])->values()->toArray()
        );

        return Command::SUCCESS;
    }
}
```

```php
<?php

namespace App\Console\Commands;

use App\Models\DeadLetterMessage;
use Illuminate\Console\Command;

class DLQRetryCommand extends Command
{
    protected $signature = 'dlq:retry
        {--id= : Xüsusi mesajın ID-si}
        {--queue= : Bütün queue-dakı mesajları retry et}
        {--category= : Kateqoriyaya görə retry}
        {--exclude-poison : Poison mesajları retry etmə}
        {--limit=100 : Maksimum retry sayı}
        {--dry-run : Nə olacağını göstər, amma retry etmə}';

    protected $description = 'DLQ-dakı mesajları yenidən emal et';

    public function handle(): int
    {
        $query = DeadLetterMessage::unresolved();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if ($queue = $this->option('queue')) {
            $query->where('queue', $queue);
        }

        if ($category = $this->option('category')) {
            $query->where('category', $category);
        }

        if ($this->option('exclude-poison')) {
            $query->where('is_poison', false);
        }

        $messages = $query->limit((int) $this->option('limit'))->get();

        if ($messages->isEmpty()) {
            $this->info('Retry ediləcək mesaj tapılmadı.');
            return Command::SUCCESS;
        }

        $this->info("Tapıldı: {$messages->count()} mesaj");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Queue', 'Job Class', 'Category', 'Failed At'],
                $messages->map(fn($m) => [
                    $m->id,
                    $m->queue,
                    class_basename($m->job_class),
                    $m->category,
                    $m->failed_at->diffForHumans(),
                ])->toArray()
            );
            $this->warn('Dry run — heç nə retry olunmadı.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($messages->count());
        $success = 0;
        $failed = 0;

        foreach ($messages as $message) {
            if ($message->retry()) {
                $success++;
            } else {
                $failed++;
                $this->warn("\nFailed to retry ID: {$message->id}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Nəticə: {$success} uğurlu, {$failed} uğursuz");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
```

```php
<?php

namespace App\Console\Commands;

use App\Models\DeadLetterMessage;
use Illuminate\Console\Command;

class DLQPurgeCommand extends Command
{
    protected $signature = 'dlq:purge
        {--days=30 : Bu qədər gündən köhnə mesajları sil}
        {--resolved-only : Yalnız resolved mesajları sil}
        {--poison-only : Yalnız poison mesajları sil}
        {--force : Təsdiq soruşma}';

    protected $description = 'Köhnə DLQ mesajlarını təmizlə';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $query = DeadLetterMessage::olderThan($days);

        if ($this->option('resolved-only')) {
            $query->whereNotNull('resolved_at');
        }

        if ($this->option('poison-only')) {
            $query->poison();
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('Silinəcək mesaj yoxdur.');
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("{$count} mesaj silinəcək. Davam edək?")) {
                $this->info('Ləğv edildi.');
                return Command::SUCCESS;
            }
        }

        $deleted = $query->delete();
        $this->info("{$deleted} mesaj silindi.");

        return Command::SUCCESS;
    }
}
```

---

## Redis-based DLQ

Redis-dən queue kimi istifadə edəndə DLQ mexanizmini özümüz qurmalıyıq. Redis-in native DLQ dəstəyi yoxdur, amma list və sorted set strukturları ilə effektiv DLQ yaratmaq mümkündür.

```
Redis DLQ Arxitekturası:

  LPUSH (producer)
      │
      ▼
  ┌──────────────────┐        BRPOPLPUSH         ┌───────────────┐
  │ queue:orders     │ ──────────────────────────>│ queue:orders: │
  │ (LIST)           │                            │ processing    │
  └──────────────────┘                            │ (LIST)        │
                                                  └───────┬───────┘
                                                          │
                                               Consumer işləyir
                                                          │
                                        ┌─────────────────┼──────────────┐
                                        │                 │              │
                                     Uğurlu           Uğursuz       Timeout
                                        │                 │              │
                                        ▼                 ▼              ▼
                                     LREM             Retry?         LREM +
                                  processing         sayını yoxla    DLQ-ya köçür
                                     list                │
                                        │         ┌──────┴──────┐
                                        ▼         │             │
                                      Done      < max         >= max
                                                   │             │
                                                   ▼             ▼
                                                RPUSH         LPUSH
                                              queue:orders   queue:orders:dlq
                                              (retry)        (dead letter)
```

### Redis DLQ İmplementasiyası

```php
<?php

namespace App\Services\Queue;

use Predis\Client as RedisClient;

class RedisDeadLetterQueue
{
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_VISIBILITY_TIMEOUT = 60; // saniyə
    private const DLQ_SUFFIX = ':dlq';
    private const PROCESSING_SUFFIX = ':processing';
    private const RETRY_COUNT_PREFIX = 'dlq:retries:';
    private const METADATA_PREFIX = 'dlq:meta:';

    public function __construct(
        private readonly RedisClient $redis,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        private readonly int $visibilityTimeout = self::DEFAULT_VISIBILITY_TIMEOUT,
    ) {}

    /**
     * Mesajı queue-ya əlavə et
     */
    public function push(string $queue, array $payload): string
    {
        $messageId = $this->generateMessageId();

        $message = json_encode([
            'id'         => $messageId,
            'payload'    => $payload,
            'created_at' => microtime(true),
            'attempts'   => 0,
        ]);

        $this->redis->lpush($queue, [$message]);

        return $messageId;
    }

    /**
     * Mesajı queue-dan al (atomic, processing list-ə köçür)
     */
    public function pop(string $queue, int $timeout = 5): ?array
    {
        $processingQueue = $queue . self::PROCESSING_SUFFIX;

        // BRPOPLPUSH: queue-dan alır, processing-ə qoyur (atomic)
        $raw = $this->redis->brpoplpush($queue, $processingQueue, $timeout);

        if ($raw === null) {
            return null;
        }

        $message = json_decode($raw, true);

        // Visibility timeout — processing-dən avtomatik silinmə üçün
        $this->redis->zadd(
            "{$queue}:visibility",
            [
                $raw => time() + $this->visibilityTimeout,
            ]
        );

        return $message;
    }

    /**
     * Mesajı uğurla emal et — processing list-dən sil
     */
    public function acknowledge(string $queue, array $message): void
    {
        $raw = json_encode($message);
        $processingQueue = $queue . self::PROCESSING_SUFFIX;

        $this->redis->lrem($processingQueue, 1, $raw);
        $this->redis->zrem("{$queue}:visibility", $raw);

        // Retry counter sil
        $this->redis->del(self::RETRY_COUNT_PREFIX . $message['id']);
    }

    /**
     * Mesajı reject et — retry və ya DLQ
     */
    public function reject(string $queue, array $message, ?\Throwable $exception = null): void
    {
        $raw = json_encode($message);
        $processingQueue = $queue . self::PROCESSING_SUFFIX;
        $dlqQueue = $queue . self::DLQ_SUFFIX;

        // Processing list-dən sil
        $this->redis->lrem($processingQueue, 1, $raw);
        $this->redis->zrem("{$queue}:visibility", $raw);

        // Retry sayını artır
        $retryKey = self::RETRY_COUNT_PREFIX . $message['id'];
        $retryCount = (int) $this->redis->incr($retryKey);
        $this->redis->expire($retryKey, 86400); // 24 saat TTL

        if ($retryCount >= $this->maxRetries) {
            // Max retry aşılıb — DLQ-ya göndər
            $this->sendToDLQ($dlqQueue, $message, $retryCount, $exception);
            $this->redis->del($retryKey);
        } else {
            // Yenidən queue-ya qoy (retry)
            $message['attempts'] = $retryCount;
            $message['last_error'] = $exception?->getMessage();
            $message['last_retry_at'] = microtime(true);

            $this->redis->lpush($queue, [json_encode($message)]);
        }
    }

    /**
     * Mesajı DLQ-ya göndər
     */
    private function sendToDLQ(
        string $dlqQueue,
        array $message,
        int $retryCount,
        ?\Throwable $exception
    ): void {
        $dlqMessage = [
            'original_message' => $message,
            'retry_count'      => $retryCount,
            'failed_at'        => microtime(true),
            'exception_class'  => $exception ? get_class($exception) : null,
            'exception_message' => $exception?->getMessage(),
        ];

        // DLQ-ya əlavə et
        $this->redis->lpush($dlqQueue, [json_encode($dlqMessage)]);

        // DLQ metadata (statistika üçün)
        $metaKey = self::METADATA_PREFIX . $dlqQueue;
        $this->redis->hincrby($metaKey, 'total_count', 1);
        $this->redis->hset($metaKey, 'last_added_at', (string) microtime(true));

        if ($exception) {
            $this->redis->hincrby(
                $metaKey,
                'exception:' . get_class($exception),
                1
            );
        }
    }

    /**
     * DLQ-dan mesajları oxu (destructive deyil — yalnız baxış)
     */
    public function peekDLQ(string $queue, int $offset = 0, int $limit = 10): array
    {
        $dlqQueue = $queue . self::DLQ_SUFFIX;
        $items = $this->redis->lrange($dlqQueue, $offset, $offset + $limit - 1);

        return array_map(fn($item) => json_decode($item, true), $items);
    }

    /**
     * DLQ-dakı mesajı yenidən main queue-ya göndər
     */
    public function reprocessFromDLQ(string $queue, int $count = 1): int
    {
        $dlqQueue = $queue . self::DLQ_SUFFIX;
        $reprocessed = 0;

        for ($i = 0; $i < $count; $i++) {
            $raw = $this->redis->rpop($dlqQueue);
            if ($raw === null) {
                break;
            }

            $dlqMessage = json_decode($raw, true);
            $originalMessage = $dlqMessage['original_message'];

            // Reset attempts
            $originalMessage['attempts'] = 0;
            $originalMessage['reprocessed_from_dlq'] = true;
            $originalMessage['reprocessed_at'] = microtime(true);

            // Retry counter-i sıfırla
            $this->redis->del(self::RETRY_COUNT_PREFIX . $originalMessage['id']);

            // Main queue-ya yenidən əlavə et
            $this->redis->lpush($queue, [json_encode($originalMessage)]);
            $reprocessed++;
        }

        return $reprocessed;
    }

    /**
     * DLQ dərinliyi (monitoring üçün)
     */
    public function getDLQDepth(string $queue): int
    {
        return (int) $this->redis->llen($queue . self::DLQ_SUFFIX);
    }

    /**
     * DLQ statistikaları
     */
    public function getDLQStats(string $queue): array
    {
        $dlqQueue = $queue . self::DLQ_SUFFIX;
        $metaKey = self::METADATA_PREFIX . $dlqQueue;

        $meta = $this->redis->hgetall($metaKey);
        $depth = $this->getDLQDepth($queue);

        $exceptions = [];
        foreach ($meta as $key => $value) {
            if (str_starts_with($key, 'exception:')) {
                $exceptions[substr($key, 10)] = (int) $value;
            }
        }

        return [
            'depth'         => $depth,
            'total_ever'    => (int) ($meta['total_count'] ?? 0),
            'last_added_at' => isset($meta['last_added_at'])
                ? date('Y-m-d H:i:s', (int) $meta['last_added_at'])
                : null,
            'exceptions'    => $exceptions,
        ];
    }

    /**
     * Visibility timeout keçmiş mesajları yenidən queue-ya qoy
     * (Consumer crash olubsa mesaj processing-də qalır)
     */
    public function recoverStaleMessages(string $queue): int
    {
        $visibilityKey = "{$queue}:visibility";
        $processingQueue = $queue . self::PROCESSING_SUFFIX;

        // Timeout keçmiş mesajları tap
        $staleMessages = $this->redis->zrangebyscore(
            $visibilityKey,
            '-inf',
            (string) time()
        );

        $recovered = 0;
        foreach ($staleMessages as $raw) {
            // Processing-dən sil
            $removed = $this->redis->lrem($processingQueue, 1, $raw);

            if ($removed > 0) {
                // Main queue-ya yenidən qoy (retry kimi davran)
                $message = json_decode($raw, true);
                $this->reject($queue, $message, new \RuntimeException('Visibility timeout exceeded'));
                $recovered++;
            }

            $this->redis->zrem($visibilityKey, $raw);
        }

        return $recovered;
    }

    /**
     * DLQ-nu tamamilə təmizlə
     */
    public function purgeDLQ(string $queue): int
    {
        $dlqQueue = $queue . self::DLQ_SUFFIX;
        $count = (int) $this->redis->llen($dlqQueue);
        $this->redis->del($dlqQueue);

        return $count;
    }

    private function generateMessageId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

### Redis DLQ Consumer

```php
<?php

namespace App\Services\Queue;

use Psr\Log\LoggerInterface;

class RedisQueueConsumer
{
    public function __construct(
        private readonly RedisDeadLetterQueue $queue,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Queue-nu consume et
     */
    public function consume(string $queueName, callable $handler): void
    {
        $this->logger->info("Starting consumer for queue: {$queueName}");

        while (true) {
            // Stale mesajları recover et (hər dövrdə)
            $recovered = $this->queue->recoverStaleMessages($queueName);
            if ($recovered > 0) {
                $this->logger->info("Recovered {$recovered} stale messages");
            }

            // Mesaj al
            $message = $this->queue->pop($queueName);

            if ($message === null) {
                continue; // Timeout — yenidən cəhd et
            }

            $messageId = $message['id'] ?? 'unknown';
            $this->logger->info("Processing message", ['id' => $messageId]);

            try {
                $handler($message['payload']);

                $this->queue->acknowledge($queueName, $message);
                $this->logger->info("Message processed", ['id' => $messageId]);

            } catch (\Throwable $e) {
                $this->logger->warning("Message processing failed", [
                    'id'    => $messageId,
                    'error' => $e->getMessage(),
                ]);

                $this->queue->reject($queueName, $message, $e);
            }

            // Memory leak prevention
            if (memory_get_usage(true) > 128 * 1024 * 1024) {
                $this->logger->warning("Memory limit approaching, stopping consumer");
                break;
            }
        }
    }
}
```

### Redis Lua Script ilə Atomic DLQ

```php
<?php

/**
 * Redis Lua script-ləri ilə atomic əməliyyatlar.
 * Race condition-ların qarşısını alır.
 */
class AtomicRedisDLQ
{
    private \Predis\Client $redis;

    /**
     * Atomic pop + move to processing
     * BRPOPLPUSH əvəzinə Lua script (daha çox kontrol)
     */
    public function atomicPop(string $queue): ?array
    {
        $script = <<<'LUA'
            local queue = KEYS[1]
            local processing = KEYS[2]
            local visibility = KEYS[3]
            local timeout = tonumber(ARGV[1])

            local message = redis.call('RPOP', queue)
            if message == nil then
                return nil
            end

            redis.call('LPUSH', processing, message)
            redis.call('ZADD', visibility, os.time() + timeout, message)

            return message
        LUA;

        $result = $this->redis->eval(
            $script,
            3, // key count
            $queue,
            "{$queue}:processing",
            "{$queue}:visibility",
            60 // visibility timeout
        );

        return $result ? json_decode($result, true) : null;
    }

    /**
     * Atomic acknowledge — processing-dən sil, counter-ləri yenilə
     */
    public function atomicAck(string $queue, string $messageRaw): void
    {
        $script = <<<'LUA'
            local processing = KEYS[1]
            local visibility = KEYS[2]
            local stats = KEYS[3]
            local message = ARGV[1]

            redis.call('LREM', processing, 1, message)
            redis.call('ZREM', visibility, message)
            redis.call('HINCRBY', stats, 'processed', 1)
            redis.call('HSET', stats, 'last_processed_at', tostring(os.time()))

            return 1
        LUA;

        $this->redis->eval(
            $script,
            3,
            "{$queue}:processing",
            "{$queue}:visibility",
            "{$queue}:stats",
            $messageRaw
        );
    }

    /**
     * Atomic reject — retry və ya DLQ (bir Lua script-də)
     */
    public function atomicReject(
        string $queue,
        string $messageRaw,
        int $maxRetries,
        ?string $errorMessage = null
    ): string {
        $script = <<<'LUA'
            local queue = KEYS[1]
            local processing = KEYS[2]
            local dlq = KEYS[3]
            local visibility = KEYS[4]
            local retry_key = KEYS[5]
            local stats = KEYS[6]
            local max_retries = tonumber(ARGV[1])
            local message_raw = ARGV[2]
            local error_msg = ARGV[3]
            local now = tostring(os.time())

            -- Processing-dən sil
            redis.call('LREM', processing, 1, message_raw)
            redis.call('ZREM', visibility, message_raw)

            -- Retry sayını artır
            local retry_count = redis.call('INCR', retry_key)
            redis.call('EXPIRE', retry_key, 86400)

            if retry_count >= max_retries then
                -- DLQ-ya göndər
                local dlq_message = cjson.encode({
                    original = message_raw,
                    retry_count = retry_count,
                    failed_at = now,
                    error = error_msg
                })
                redis.call('LPUSH', dlq, dlq_message)
                redis.call('DEL', retry_key)
                redis.call('HINCRBY', stats, 'dead_lettered', 1)
                return 'DLQ'
            else
                -- Queue-ya yenidən qoy
                redis.call('LPUSH', queue, message_raw)
                redis.call('HINCRBY', stats, 'retried', 1)
                return 'RETRY'
            end
        LUA;

        $message = json_decode($messageRaw, true);
        $messageId = $message['id'] ?? 'unknown';

        return $this->redis->eval(
            $script,
            6,
            $queue,
            "{$queue}:processing",
            "{$queue}:dlq",
            "{$queue}:visibility",
            "dlq:retries:{$messageId}",
            "{$queue}:stats",
            $maxRetries,
            $messageRaw,
            $errorMessage ?? ''
        );
    }
}
```

---

## Monitoring və Alerting

DLQ monitorinqi sistemin sağlamlığını göstərən vacib metrikdir. DLQ-da mesajların yığılması potensial problemi işarə edir.

```
DLQ Monitoring Dashboard:

┌─────────────────────────────────────────────────────────┐
│  Dead Letter Queue Dashboard                            │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  DLQ Depth (son 24 saat):                              │
│                                                         │
│  250 │                                    ╭──╮          │
│  200 │                               ╭────╯  │          │
│  150 │                          ╭────╯       │          │
│  100 │              ╭──────────╯             ╰──╮       │
│   50 │    ╭────────╯                            ╰───    │
│    0 │────╯                                             │
│      └──────────────────────────────────────────────    │
│       00:00  04:00  08:00  12:00  16:00  20:00          │
│                                                         │
│  ┌─────────────┬────────┬─────────┬──────────┐         │
│  │ Queue       │ Depth  │ Rate/h  │ Status   │         │
│  ├─────────────┼────────┼─────────┼──────────┤         │
│  │ orders      │ 23     │ 2.3     │ WARNING  │         │
│  │ payments    │ 156    │ 15.6    │ CRITICAL │         │
│  │ emails      │ 3      │ 0.1     │ OK       │         │
│  │ notifications│ 0     │ 0       │ OK       │         │
│  └─────────────┴────────┴─────────┴──────────┘         │
│                                                         │
│  Top Exceptions:                                        │
│  ├── PaymentGatewayException     (78)                  │
│  ├── ConnectionTimeoutException  (45)                  │
│  ├── ValidationException         (23)                  │
│  └── JsonException               (10)                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Prometheus Metrics

```php
<?php

namespace App\Services\Monitoring;

use App\Models\DeadLetterMessage;
use App\Services\Queue\RedisDeadLetterQueue;
use Predis\Client as RedisClient;

class DLQMetricsCollector
{
    private array $queues = ['orders', 'payments', 'emails', 'notifications'];

    public function __construct(
        private readonly RedisClient $redis,
        private readonly RedisDeadLetterQueue $dlq,
    ) {}

    /**
     * Prometheus format-da metriklər
     * /metrics endpoint-dən istifadə olunur
     */
    public function collectPrometheusMetrics(): string
    {
        $output = '';

        // DLQ depth (gauge)
        $output .= "# HELP dlq_depth Current number of messages in dead letter queue\n";
        $output .= "# TYPE dlq_depth gauge\n";
        foreach ($this->queues as $queue) {
            $depth = $this->dlq->getDLQDepth($queue);
            $output .= "dlq_depth{queue=\"{$queue}\"} {$depth}\n";
        }

        // DLQ total messages ever (counter)
        $output .= "\n# HELP dlq_messages_total Total messages ever sent to DLQ\n";
        $output .= "# TYPE dlq_messages_total counter\n";
        foreach ($this->queues as $queue) {
            $stats = $this->dlq->getDLQStats($queue);
            $total = $stats['total_ever'];
            $output .= "dlq_messages_total{queue=\"{$queue}\"} {$total}\n";
        }

        // Processing queue depth
        $output .= "\n# HELP queue_processing_depth Messages currently being processed\n";
        $output .= "# TYPE queue_processing_depth gauge\n";
        foreach ($this->queues as $queue) {
            $depth = (int) $this->redis->llen("{$queue}:processing");
            $output .= "queue_processing_depth{queue=\"{$queue}\"} {$depth}\n";
        }

        // DB-based DLQ stats (əgər istifadə olunursa)
        $output .= "\n# HELP dlq_db_unresolved Unresolved messages in DB DLQ\n";
        $output .= "# TYPE dlq_db_unresolved gauge\n";
        $unresolvedByQueue = DeadLetterMessage::unresolved()
            ->selectRaw('queue, count(*) as count')
            ->groupBy('queue')
            ->pluck('count', 'queue');
        foreach ($unresolvedByQueue as $queue => $count) {
            $output .= "dlq_db_unresolved{queue=\"{$queue}\"} {$count}\n";
        }

        // Poison message count
        $output .= "\n# HELP dlq_poison_messages Number of poison messages\n";
        $output .= "# TYPE dlq_poison_messages gauge\n";
        $poisonCount = DeadLetterMessage::unresolved()->poison()->count();
        $output .= "dlq_poison_messages {$poisonCount}\n";

        return $output;
    }
}
```

### Scheduled Monitoring (Laravel)

```php
<?php

namespace App\Console\Commands;

use App\Models\DeadLetterMessage;
use App\Notifications\DLQAlertNotification;
use App\Services\Queue\RedisDeadLetterQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class DLQMonitorCommand extends Command
{
    protected $signature = 'dlq:monitor';
    protected $description = 'DLQ dərinliyini yoxla və lazım olduqda alert göndər';

    private array $thresholds = [
        'orders'        => ['warning' => 10, 'critical' => 50],
        'payments'      => ['warning' => 5,  'critical' => 20],
        'emails'        => ['warning' => 50, 'critical' => 200],
        'notifications' => ['warning' => 100, 'critical' => 500],
    ];

    public function __construct(
        private readonly RedisDeadLetterQueue $dlq,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        foreach ($this->thresholds as $queue => $limits) {
            $depth = $this->dlq->getDLQDepth($queue);
            $rate = $this->calculateRate($queue, $depth);

            $this->info("{$queue}: depth={$depth}, rate={$rate}/hour");

            if ($depth >= $limits['critical']) {
                $this->sendAlert($queue, $depth, $rate, 'critical');
            } elseif ($depth >= $limits['warning']) {
                $this->sendAlert($queue, $depth, $rate, 'warning');
            }

            // Spike detection — ani artım
            if ($rate > 0) {
                $previousRate = (float) Cache::get("dlq:rate:{$queue}:previous", 0);
                if ($rate > $previousRate * 3 && $rate > 5) {
                    $this->sendAlert($queue, $depth, $rate, 'spike');
                }
            }

            // Rəqəmləri cache-lə (növbəti müqayisə üçün)
            Cache::put("dlq:depth:{$queue}:previous", $depth, 300);
            Cache::put("dlq:rate:{$queue}:previous", $rate, 300);
        }

        // Stale messages yoxla (24 saatdan köhnə, hələ resolved deyil)
        $staleCount = DeadLetterMessage::unresolved()
            ->olderThan(1)
            ->count();

        if ($staleCount > 0) {
            $this->warn("Stale DLQ messages (>24h): {$staleCount}");
            $this->sendAlert('all', $staleCount, 0, 'stale');
        }

        return Command::SUCCESS;
    }

    /**
     * Mesaj axınının sürətini hesabla (mesaj/saat)
     */
    private function calculateRate(string $queue, int $currentDepth): float
    {
        $previousDepth = (int) Cache::get("dlq:depth:{$queue}:previous", $currentDepth);
        $intervalMinutes = 5; // Bu command hər 5 dəqiqədə işləyir

        $diff = $currentDepth - $previousDepth;
        return max(0, round($diff * (60 / $intervalMinutes), 1));
    }

    private function sendAlert(string $queue, int $depth, float $rate, string $severity): void
    {
        // Dublikat alert-lərin qarşısını al (cooldown)
        $cooldownKey = "dlq:alert:{$queue}:{$severity}";
        $cooldownMinutes = match ($severity) {
            'critical' => 15,
            'spike'    => 30,
            'stale'    => 60,
            default    => 30,
        };

        if (Cache::has($cooldownKey)) {
            return; // Cooldown dövründəyik
        }

        Cache::put($cooldownKey, true, $cooldownMinutes * 60);

        Notification::route('slack', config('services.slack.alerts_channel'))
            ->notify(new DLQAlertNotification($queue, $depth, $severity, $rate));

        $this->error("[{$severity}] Queue: {$queue}, Depth: {$depth}, Rate: {$rate}/h");
    }
}
```

```php
// Kernel.php — Schedule qeydiyyatı
protected function schedule(Schedule $schedule): void
{
    // Hər 5 dəqiqədə DLQ monitorinqi
    $schedule->command('dlq:monitor')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->runInBackground();

    // Hər gün köhnə resolved mesajları təmizlə
    $schedule->command('dlq:purge --days=30 --resolved-only --force')
        ->daily()
        ->at('03:00');

    // Hər saat stale processing mesajlarını recover et
    $schedule->command('dlq:recover-stale')
        ->hourly();
}
```

### Health Check Endpoint

```php
<?php

namespace App\Http\Controllers;

use App\Services\Queue\RedisDeadLetterQueue;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function dlqHealth(RedisDeadLetterQueue $dlq): JsonResponse
    {
        $queues = ['orders', 'payments', 'emails'];
        $status = 'healthy';
        $details = [];

        foreach ($queues as $queue) {
            $depth = $dlq->getDLQDepth($queue);
            $stats = $dlq->getDLQStats($queue);

            $queueStatus = match (true) {
                $depth > 100 => 'critical',
                $depth > 20  => 'degraded',
                default      => 'healthy',
            };

            if ($queueStatus === 'critical') {
                $status = 'critical';
            } elseif ($queueStatus === 'degraded' && $status === 'healthy') {
                $status = 'degraded';
            }

            $details[$queue] = [
                'depth'      => $depth,
                'status'     => $queueStatus,
                'total_ever' => $stats['total_ever'],
                'last_added' => $stats['last_added_at'],
            ];
        }

        $httpStatus = match ($status) {
            'critical' => 503,
            'degraded' => 200,
            default    => 200,
        };

        return response()->json([
            'status'  => $status,
            'queues'  => $details,
            'checked_at' => now()->toIso8601String(),
        ], $httpStatus);
    }
}
```

---

## Reprocessing Strategiyaları

DLQ-dakı mesajların yenidən emal olunması (replay) diqqətli planlaşdırma tələb edir. Yanlış reprocessing mövcud problemi daha da böyüdə bilər.

```
Reprocessing Qaydaları:

  DLQ-da mesaj var
        │
        ▼
  ┌──────────────────────┐
  │ Mesajı analiz et     │
  │ (səbəbi nədir?)      │
  └──────────┬───────────┘
             │
     ┌───────┼──────────┬──────────────┐
     │       │          │              │
     ▼       ▼          ▼              ▼
  Transient  Business   Data          Code
  Error      Logic      Corruption    Bug
     │       │          │              │
     ▼       ▼          ▼              ▼
  Birbaşa   Əvvəlcə   Fix data,     Deploy fix,
  retry     rule-u     sonra retry   sonra retry
  et        düzəlt                    et
             │          │              │
             ▼          ▼              ▼
        ┌────────────────────────────────────┐
        │         Reprocess Pipeline         │
        │                                    │
        │  1. Throttle — yavaş-yavaş göndər  │
        │  2. Validate — reprocess-dən əvvəl  │
        │  3. Monitor — uğur/uğursuzluq izlə │
        │  4. Rollback — problem varsa dayandır│
        └────────────────────────────────────┘
```

### Reprocessing Service

```php
<?php

namespace App\Services\Queue;

use App\Models\DeadLetterMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class DLQReprocessor
{
    public function __construct(
        private readonly RedisDeadLetterQueue $queue,
    ) {}

    /**
     * Throttled reprocessing — DLQ-dakı mesajları yavaş-yavaş main queue-ya qaytarır
     *
     * @param string $queueName Queue adı
     * @param int $batchSize Hər batch-da neçə mesaj
     * @param int $delayBetweenBatches Batch-lar arası gözləmə (saniyə)
     * @param callable|null $filter Mesajı filter etmək üçün callback
     * @return array Reprocessing nəticəsi
     */
    public function reprocessWithThrottle(
        string $queueName,
        int $batchSize = 10,
        int $delayBetweenBatches = 5,
        ?callable $filter = null
    ): array {
        $stats = [
            'total'      => 0,
            'reprocessed' => 0,
            'skipped'    => 0,
            'errors'     => 0,
        ];

        $dlqDepth = $this->queue->getDLQDepth($queueName);
        $stats['total'] = $dlqDepth;

        Log::info("Starting DLQ reprocessing", [
            'queue'      => $queueName,
            'dlq_depth'  => $dlqDepth,
            'batch_size' => $batchSize,
        ]);

        $processed = 0;
        while ($processed < $dlqDepth) {
            // Rate limiting — sistemə həddindən artıq yük verməmək üçün
            if (RateLimiter::tooManyAttempts("dlq:reprocess:{$queueName}", 100)) {
                Log::warning("Rate limit hit, pausing reprocessing");
                sleep($delayBetweenBatches);
                RateLimiter::clear("dlq:reprocess:{$queueName}");
                continue;
            }

            // Batch oxu
            $messages = $this->queue->peekDLQ($queueName, 0, $batchSize);

            if (empty($messages)) {
                break;
            }

            foreach ($messages as $message) {
                RateLimiter::hit("dlq:reprocess:{$queueName}");

                // Filter (əgər varsa)
                if ($filter && !$filter($message)) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $count = $this->queue->reprocessFromDLQ($queueName, 1);

                    if ($count > 0) {
                        $stats['reprocessed']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error("Reprocess error", [
                        'queue' => $queueName,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processed++;
            }

            // Batch arası gözləmə
            if ($delayBetweenBatches > 0) {
                sleep($delayBetweenBatches);
            }
        }

        Log::info("DLQ reprocessing completed", $stats);

        return $stats;
    }

    /**
     * Selektiv reprocessing — yalnız müəyyən xəta tipli mesajları retry et
     */
    public function reprocessByExceptionType(
        string $queueName,
        string $exceptionClass,
        int $limit = 100
    ): array {
        $filter = function (array $dlqMessage) use ($exceptionClass): bool {
            return ($dlqMessage['exception_class'] ?? '') === $exceptionClass;
        };

        return $this->reprocessWithThrottle($queueName, 10, 2, $filter);
    }

    /**
     * DB-based DLQ-dan reprocessing (Laravel failed_jobs)
     */
    public function reprocessFromDatabase(
        ?string $queue = null,
        ?string $category = null,
        int $limit = 50,
        bool $excludePoison = true
    ): array {
        $stats = ['attempted' => 0, 'success' => 0, 'failed' => 0];

        $query = DeadLetterMessage::unresolved();

        if ($queue) {
            $query->byQueue($queue);
        }

        if ($category) {
            $query->byCategory($category);
        }

        if ($excludePoison) {
            $query->where('is_poison', false);
        }

        $messages = $query->limit($limit)->get();

        foreach ($messages as $message) {
            $stats['attempted']++;

            if ($message->retry()) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Dry-run reprocessing — heç nə etmədən nəticəni göstər
     */
    public function dryRunReprocess(string $queueName, int $limit = 50): array
    {
        $messages = $this->queue->peekDLQ($queueName, 0, $limit);

        $analysis = [
            'total_in_dlq' => $this->queue->getDLQDepth($queueName),
            'sample_size'  => count($messages),
            'by_exception' => [],
            'by_age'       => ['<1h' => 0, '1-6h' => 0, '6-24h' => 0, '>24h' => 0],
            'reprocessable' => 0,
            'poison'        => 0,
        ];

        foreach ($messages as $msg) {
            $exception = $msg['exception_class'] ?? 'Unknown';
            $analysis['by_exception'][$exception] = ($analysis['by_exception'][$exception] ?? 0) + 1;

            $failedAt = $msg['failed_at'] ?? time();
            $ageHours = (time() - $failedAt) / 3600;

            if ($ageHours < 1) $analysis['by_age']['<1h']++;
            elseif ($ageHours < 6) $analysis['by_age']['1-6h']++;
            elseif ($ageHours < 24) $analysis['by_age']['6-24h']++;
            else $analysis['by_age']['>24h']++;

            // Poison detection
            $poisonExceptions = [\JsonException::class, \TypeError::class, \InvalidArgumentException::class];
            if (in_array($exception, $poisonExceptions)) {
                $analysis['poison']++;
            } else {
                $analysis['reprocessable']++;
            }
        }

        return $analysis;
    }
}
```

### Circuit Breaker ilə Reprocessing

```php
<?php

namespace App\Services\Queue;

/**
 * DLQ reprocessing zamanı əgər çox mesaj uğursuz olursa
 * Circuit Breaker reprocessing-i dayandırır.
 */
class ReprocessingCircuitBreaker
{
    private int $failureCount = 0;
    private int $successCount = 0;
    private string $state = 'closed'; // closed, open, half-open

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $successThreshold = 3,
        private readonly int $cooldownSeconds = 60,
        private ?float $openedAt = null,
    ) {}

    public function canProcess(): bool
    {
        if ($this->state === 'closed') {
            return true;
        }

        if ($this->state === 'open') {
            // Cooldown bitibmi?
            if (microtime(true) - $this->openedAt >= $this->cooldownSeconds) {
                $this->state = 'half-open';
                return true;
            }
            return false;
        }

        // half-open — bir mesaj burax
        return true;
    }

    public function recordSuccess(): void
    {
        if ($this->state === 'half-open') {
            $this->successCount++;
            if ($this->successCount >= $this->successThreshold) {
                $this->state = 'closed';
                $this->failureCount = 0;
                $this->successCount = 0;
            }
        } else {
            $this->failureCount = max(0, $this->failureCount - 1);
        }
    }

    public function recordFailure(): void
    {
        $this->failureCount++;
        $this->successCount = 0;

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'open';
            $this->openedAt = microtime(true);
        }
    }

    public function getState(): string
    {
        return $this->state;
    }
}
```

---

## PHP İmplementasiyası — Tam Nümunələr

### Amazon SQS DLQ Konfiqurasiyası

```php
<?php

namespace App\Services\Queue;

use Aws\Sqs\SqsClient;

class SqsDLQManager
{
    private SqsClient $sqs;
    private string $region;
    private string $accountId;

    public function __construct(string $region = 'us-east-1')
    {
        $this->region = $region;
        $this->sqs = new SqsClient([
            'version' => 'latest',
            'region'  => $region,
        ]);
    }

    /**
     * Main queue + DLQ yaradılması
     */
    public function createQueueWithDLQ(
        string $queueName,
        int $maxReceiveCount = 3,
        int $dlqRetentionDays = 14
    ): array {
        // 1. Əvvəlcə DLQ yaradılır
        $dlqResult = $this->sqs->createQueue([
            'QueueName' => "{$queueName}-dlq",
            'Attributes' => [
                'MessageRetentionPeriod' => (string) ($dlqRetentionDays * 86400),
                'VisibilityTimeout'      => '300',
            ],
        ]);

        $dlqUrl = $dlqResult['QueueUrl'];

        // DLQ ARN-ini al
        $dlqAttributes = $this->sqs->getQueueAttributes([
            'QueueUrl'       => $dlqUrl,
            'AttributeNames' => ['QueueArn'],
        ]);
        $dlqArn = $dlqAttributes['Attributes']['QueueArn'];

        // 2. Main queue yaradılır (redrive policy ilə)
        $mainResult = $this->sqs->createQueue([
            'QueueName' => $queueName,
            'Attributes' => [
                'VisibilityTimeout'      => '60',
                'MessageRetentionPeriod' => '345600', // 4 gün
                'RedrivePolicy'          => json_encode([
                    'deadLetterTargetArn' => $dlqArn,
                    'maxReceiveCount'     => $maxReceiveCount,
                ]),
            ],
        ]);

        return [
            'main_queue_url' => $mainResult['QueueUrl'],
            'dlq_url'        => $dlqUrl,
            'dlq_arn'        => $dlqArn,
        ];
    }

    /**
     * DLQ-dakı mesajları main queue-ya qaytarma
     * (SQS "redrive" əməliyyatı)
     */
    public function redriveDLQ(string $dlqUrl, string $mainQueueUrl, int $limit = 100): array
    {
        $stats = ['moved' => 0, 'errors' => 0];

        for ($i = 0; $i < $limit; $i++) {
            // DLQ-dan mesaj al
            $result = $this->sqs->receiveMessage([
                'QueueUrl'            => $dlqUrl,
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds'     => 1,
            ]);

            if (empty($result['Messages'])) {
                break; // DLQ boşdur
            }

            $message = $result['Messages'][0];

            try {
                // Main queue-ya göndər
                $this->sqs->sendMessage([
                    'QueueUrl'    => $mainQueueUrl,
                    'MessageBody' => $message['Body'],
                    'MessageAttributes' => [
                        'ReprocessedFromDLQ' => [
                            'DataType'    => 'String',
                            'StringValue' => 'true',
                        ],
                        'OriginalMessageId' => [
                            'DataType'    => 'String',
                            'StringValue' => $message['MessageId'],
                        ],
                    ],
                ]);

                // DLQ-dan sil
                $this->sqs->deleteMessage([
                    'QueueUrl'      => $dlqUrl,
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ]);

                $stats['moved']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * DLQ dərinliyini yoxla
     */
    public function getDLQDepth(string $dlqUrl): int
    {
        $result = $this->sqs->getQueueAttributes([
            'QueueUrl'       => $dlqUrl,
            'AttributeNames' => [
                'ApproximateNumberOfMessages',
                'ApproximateNumberOfMessagesNotVisible',
            ],
        ]);

        return (int) $result['Attributes']['ApproximateNumberOfMessages']
             + (int) $result['Attributes']['ApproximateNumberOfMessagesNotVisible'];
    }
}
```

### Kafka DLQ Pattern

```php
<?php

namespace App\Services\Queue;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use RdKafka\TopicConf;
use Psr\Log\LoggerInterface;

/**
 * Kafka-da native DLQ yoxdur.
 * DLQ pattern-i consumer səviyyəsində implementasiya olunur:
 * Uğursuz mesajlar ayrı topic-ə (DLT — Dead Letter Topic) publish olunur.
 */
class KafkaDLQConsumer
{
    private KafkaConsumer $consumer;
    private Producer $dlqProducer;
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly string $bootstrapServers,
        private readonly string $groupId,
        private readonly LoggerInterface $logger,
    ) {
        $this->initConsumer();
        $this->initDLQProducer();
    }

    private function initConsumer(): void
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->bootstrapServers);
        $conf->set('group.id', $this->groupId);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'false'); // Manual commit

        $this->consumer = new KafkaConsumer($conf);
    }

    private function initDLQProducer(): void
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->bootstrapServers);
        $conf->set('acks', 'all'); // DLQ mesajları itirməmək üçün

        $this->dlqProducer = new Producer($conf);
    }

    /**
     * Topic-i consume et, uğursuz mesajları DLT-yə göndər
     */
    public function consumeWithDLQ(string $topic, callable $handler): void
    {
        $dlqTopic = "{$topic}.dlq"; // Convention: {topic}.dlq

        $this->consumer->subscribe([$topic]);
        $this->logger->info("Subscribed to {$topic}, DLQ topic: {$dlqTopic}");

        while (true) {
            $message = $this->consumer->consume(5000); // 5s timeout

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->processMessage($message, $handler, $dlqTopic);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // Normal — mesaj yoxdur
                    break;

                default:
                    $this->logger->error("Kafka error: {$message->errstr()}");
                    break;
            }
        }
    }

    private function processMessage(
        \RdKafka\Message $message,
        callable $handler,
        string $dlqTopic
    ): void {
        $headers = $message->headers ?? [];
        $retryCount = (int) ($headers['x-retry-count'] ?? 0);

        try {
            $payload = json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR);
            $handler($payload, $message);

            // Uğurlu — commit offset
            $this->consumer->commit($message);
            $this->logger->debug("Message processed", [
                'topic'     => $message->topic_name,
                'partition' => $message->partition,
                'offset'    => $message->offset,
            ]);

        } catch (\JsonException $e) {
            // Poison message — birbaşa DLQ
            $this->sendToDLQ($dlqTopic, $message, $retryCount, $e, 'poison');
            $this->consumer->commit($message);

        } catch (\Throwable $e) {
            if ($retryCount >= self::MAX_RETRIES) {
                // Max retry — DLQ
                $this->sendToDLQ($dlqTopic, $message, $retryCount, $e, 'max_retries');
                $this->consumer->commit($message);
            } else {
                // Retry — eyni topic-ə yenidən göndər (header ilə)
                $this->sendToRetry($message->topic_name, $message, $retryCount + 1);
                $this->consumer->commit($message);
            }
        }
    }

    private function sendToDLQ(
        string $dlqTopic,
        \RdKafka\Message $originalMessage,
        int $retryCount,
        \Throwable $exception,
        string $reason
    ): void {
        $topic = $this->dlqProducer->newTopic($dlqTopic);

        $dlqPayload = json_encode([
            'original_payload' => $originalMessage->payload,
            'original_topic'   => $originalMessage->topic_name,
            'original_partition' => $originalMessage->partition,
            'original_offset'  => $originalMessage->offset,
            'original_key'     => $originalMessage->key,
            'retry_count'      => $retryCount,
            'exception_class'  => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'reason'           => $reason,
            'failed_at'        => date('c'),
            'consumer_group'   => $this->groupId,
        ]);

        $topic->produce(
            RD_KAFKA_PARTITION_UA,
            0,
            $dlqPayload,
            $originalMessage->key // Eyni key saxla (partitioning üçün)
        );

        $this->dlqProducer->flush(5000);

        $this->logger->warning("Message sent to DLQ", [
            'dlq_topic' => $dlqTopic,
            'reason'    => $reason,
            'error'     => $exception->getMessage(),
        ]);
    }

    private function sendToRetry(
        string $topic,
        \RdKafka\Message $originalMessage,
        int $retryCount
    ): void {
        $retryTopic = $this->dlqProducer->newTopic($topic);

        // Header-ə retry count əlavə et
        $retryTopic->producev(
            RD_KAFKA_PARTITION_UA,
            0,
            $originalMessage->payload,
            $originalMessage->key,
            [
                'x-retry-count'  => (string) $retryCount,
                'x-first-failed' => date('c'),
            ]
        );

        $this->dlqProducer->flush(5000);
    }
}
```

### DLQ Dashboard Controller (Laravel)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\DeadLetterMessage;
use App\Services\Queue\DLQReprocessor;
use App\Services\Queue\RedisDeadLetterQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DLQDashboardController extends Controller
{
    public function __construct(
        private readonly RedisDeadLetterQueue $dlq,
        private readonly DLQReprocessor $reprocessor,
    ) {}

    /**
     * DLQ ümumi statistikası
     */
    public function index(): JsonResponse
    {
        $queues = ['orders', 'payments', 'emails', 'notifications'];
        $data = [];

        foreach ($queues as $queue) {
            $data[$queue] = [
                'redis_depth' => $this->dlq->getDLQDepth($queue),
                'redis_stats' => $this->dlq->getDLQStats($queue),
            ];
        }

        $dbStats = DeadLetterMessage::statistics();

        return response()->json([
            'redis_queues' => $data,
            'db_stats'     => $dbStats,
            'health'       => $this->calculateHealth($data),
        ]);
    }

    /**
     * Xüsusi queue-nun DLQ mesajları
     */
    public function show(string $queue, Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        // Redis DLQ
        $redisMessages = $this->dlq->peekDLQ($queue, ($page - 1) * $perPage, $perPage);

        // DB DLQ
        $dbMessages = DeadLetterMessage::byQueue($queue)
            ->unresolved()
            ->orderByDesc('failed_at')
            ->paginate($perPage);

        return response()->json([
            'redis_messages' => $redisMessages,
            'db_messages'    => $dbMessages,
        ]);
    }

    /**
     * Mesajı retry et
     */
    public function retry(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|in:redis,database',
            'queue'  => 'required|string',
            'id'     => 'nullable|integer', // DB id
            'count'  => 'nullable|integer|min:1|max:1000',
        ]);

        if ($request->source === 'redis') {
            $count = $request->input('count', 1);
            $moved = $this->dlq->reprocessFromDLQ($request->queue, $count);

            return response()->json([
                'source'      => 'redis',
                'reprocessed' => $moved,
            ]);
        }

        // Database
        if ($request->id) {
            $message = DeadLetterMessage::findOrFail($request->id);
            $success = $message->retry();

            return response()->json([
                'source'  => 'database',
                'success' => $success,
            ]);
        }

        $stats = $this->reprocessor->reprocessFromDatabase(
            queue: $request->queue,
            limit: $request->input('count', 10)
        );

        return response()->json([
            'source' => 'database',
            'stats'  => $stats,
        ]);
    }

    /**
     * DLQ mesajını resolve et (silmədən)
     */
    public function resolve(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $message = DeadLetterMessage::findOrFail($id);
        $message->markResolved(
            resolvedBy: $request->user()->name ?? 'admin',
            note: $request->input('note', '')
        );

        return response()->json(['resolved' => true]);
    }

    /**
     * Dry-run analiz
     */
    public function analyze(string $queue): JsonResponse
    {
        $analysis = $this->reprocessor->dryRunReprocess($queue);

        return response()->json($analysis);
    }

    private function calculateHealth(array $data): string
    {
        $totalDepth = array_sum(array_column(
            array_column($data, 'redis_depth'),
            null
        ));

        // Sadəcə Redis depth-dən hesablayırıq
        $totalDepth = 0;
        foreach ($data as $queueData) {
            $totalDepth += $queueData['redis_depth'];
        }

        return match (true) {
            $totalDepth > 500 => 'critical',
            $totalDepth > 100 => 'degraded',
            default           => 'healthy',
        };
    }
}
```

### Tam Test Nümunəsi

```php
<?php

namespace Tests\Unit\Services\Queue;

use App\Services\Queue\RedisDeadLetterQueue;
use Predis\Client as RedisClient;
use PHPUnit\Framework\TestCase;

class RedisDeadLetterQueueTest extends TestCase
{
    private RedisClient $redis;
    private RedisDeadLetterQueue $dlq;
    private string $testQueue = 'test_queue';

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = new RedisClient(['database' => 15]); // Test database
        $this->redis->flushdb();

        $this->dlq = new RedisDeadLetterQueue($this->redis, maxRetries: 3);
    }

    protected function tearDown(): void
    {
        $this->redis->flushdb();
        parent::tearDown();
    }

    public function test_message_pushed_and_popped(): void
    {
        $id = $this->dlq->push($this->testQueue, ['order_id' => 123]);

        $message = $this->dlq->pop($this->testQueue, 1);

        $this->assertNotNull($message);
        $this->assertEquals(123, $message['payload']['order_id']);
        $this->assertEquals($id, $message['id']);
    }

    public function test_acknowledged_message_removed_from_processing(): void
    {
        $this->dlq->push($this->testQueue, ['test' => true]);
        $message = $this->dlq->pop($this->testQueue, 1);

        $this->dlq->acknowledge($this->testQueue, $message);

        $processingLen = $this->redis->llen("{$this->testQueue}:processing");
        $this->assertEquals(0, $processingLen);
    }

    public function test_rejected_message_retried_before_dlq(): void
    {
        $this->dlq->push($this->testQueue, ['test' => true]);

        // İlk reject — retry olmalıdır
        $message = $this->dlq->pop($this->testQueue, 1);
        $this->dlq->reject($this->testQueue, $message, new \RuntimeException('fail'));

        // Mesaj main queue-da olmalıdır
        $queueLen = $this->redis->llen($this->testQueue);
        $this->assertEquals(1, $queueLen);

        // DLQ boş olmalıdır
        $dlqLen = $this->dlq->getDLQDepth($this->testQueue);
        $this->assertEquals(0, $dlqLen);
    }

    public function test_message_moved_to_dlq_after_max_retries(): void
    {
        $this->dlq->push($this->testQueue, ['test' => true]);

        // 3 dəfə reject et (maxRetries = 3)
        for ($i = 0; $i < 3; $i++) {
            $message = $this->dlq->pop($this->testQueue, 1);
            $this->assertNotNull($message, "Pop #{$i} should return message");
            $this->dlq->reject($this->testQueue, $message, new \RuntimeException("fail #{$i}"));
        }

        // Main queue boş olmalıdır
        $queueLen = $this->redis->llen($this->testQueue);
        $this->assertEquals(0, $queueLen);

        // DLQ-da 1 mesaj olmalıdır
        $dlqLen = $this->dlq->getDLQDepth($this->testQueue);
        $this->assertEquals(1, $dlqLen);
    }

    public function test_reprocess_from_dlq(): void
    {
        // DLQ-ya mesaj göndər (3 dəfə reject)
        $this->dlq->push($this->testQueue, ['order_id' => 456]);
        for ($i = 0; $i < 3; $i++) {
            $message = $this->dlq->pop($this->testQueue, 1);
            $this->dlq->reject($this->testQueue, $message, new \RuntimeException('fail'));
        }

        $this->assertEquals(1, $this->dlq->getDLQDepth($this->testQueue));
        $this->assertEquals(0, $this->redis->llen($this->testQueue));

        // DLQ-dan reprocess et
        $count = $this->dlq->reprocessFromDLQ($this->testQueue, 1);

        $this->assertEquals(1, $count);
        $this->assertEquals(0, $this->dlq->getDLQDepth($this->testQueue));
        $this->assertEquals(1, $this->redis->llen($this->testQueue));
    }

    public function test_dlq_stats(): void
    {
        // 2 mesajı DLQ-ya göndər
        for ($j = 0; $j < 2; $j++) {
            $this->dlq->push($this->testQueue, ['n' => $j]);
            for ($i = 0; $i < 3; $i++) {
                $message = $this->dlq->pop($this->testQueue, 1);
                $this->dlq->reject($this->testQueue, $message, new \RuntimeException('test error'));
            }
        }

        $stats = $this->dlq->getDLQStats($this->testQueue);

        $this->assertEquals(2, $stats['depth']);
        $this->assertEquals(2, $stats['total_ever']);
        $this->assertNotNull($stats['last_added_at']);
        $this->assertArrayHasKey('RuntimeException', $stats['exceptions']);
    }

    public function test_peek_dlq_does_not_remove_messages(): void
    {
        $this->dlq->push($this->testQueue, ['test' => true]);
        for ($i = 0; $i < 3; $i++) {
            $message = $this->dlq->pop($this->testQueue, 1);
            $this->dlq->reject($this->testQueue, $message, new \RuntimeException('fail'));
        }

        // Peek
        $messages = $this->dlq->peekDLQ($this->testQueue, 0, 10);
        $this->assertCount(1, $messages);

        // DLQ dərinliyi dəyişməməlidir
        $this->assertEquals(1, $this->dlq->getDLQDepth($this->testQueue));
    }

    public function test_purge_dlq(): void
    {
        // 5 mesajı DLQ-ya göndər
        for ($j = 0; $j < 5; $j++) {
            $this->dlq->push($this->testQueue, ['n' => $j]);
            for ($i = 0; $i < 3; $i++) {
                $message = $this->dlq->pop($this->testQueue, 1);
                $this->dlq->reject($this->testQueue, $message, new \RuntimeException('fail'));
            }
        }

        $this->assertEquals(5, $this->dlq->getDLQDepth($this->testQueue));

        $purged = $this->dlq->purgeDLQ($this->testQueue);

        $this->assertEquals(5, $purged);
        $this->assertEquals(0, $this->dlq->getDLQDepth($this->testQueue));
    }
}
```

---

## Best Practices

### DLQ Dizayn Qaydaları

```
DLQ Best Practices Checklist:

✅ Hər queue üçün ayrı DLQ olmalıdır
   orders_queue → orders_dlq
   payments_queue → payments_dlq

✅ DLQ mesajlarında kontekst saxla
   ├── Original mesaj body
   ├── Exception class və message
   ├── Retry sayı
   ├── İlk və son failure vaxtı
   ├── Consumer hostname
   └── Stack trace (truncated)

✅ Poison message-ləri tez aşkarla
   ├── Validation xətaları → dərhal DLQ
   ├── Parse xətaları → dərhal DLQ
   └── Transient xətalar → retry, sonra DLQ

✅ Monitoring qur
   ├── DLQ depth gauge
   ├── DLQ message rate
   ├── Alert threshold-lar
   └── Dashboard

✅ Retention policy təyin et
   ├── DLQ mesajları 7-30 gün saxla
   ├── Resolved mesajları 7 gün sonra sil
   └── Poison mesajları log-layıb sil

✅ Reprocessing prosesi
   ├── Throttled reprocessing
   ├── Circuit breaker
   ├── Dry-run analiz
   └── Selective retry (kateqoriya/tip üzrə)

❌ Anti-patterns:
   ├── DLQ-suz queue (sonsuz retry dövrəsi)
   ├── DLQ-nu ignore etmək (mesajlar yığılır)
   ├── Bütün DLQ-nu kütləvi retry (sistemi yükləyir)
   ├── DLQ mesajlarında kontekst saxlamamaq
   └── Poison message-ləri retry etmək
```

### Error Classification Matrix

```
Xəta Klassifikasiyası:

┌────────────────────────┬───────────┬──────────┬─────────────┐
│ Xəta Tipi              │ Retry?    │ DLQ?     │ Hərəkət     │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Network timeout        │ ✅ Bəli   │ N dəfə  │ Backoff     │
│                        │           │ sonra    │ retry       │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ DB connection lost     │ ✅ Bəli   │ N dəfə  │ Backoff     │
│                        │           │ sonra    │ retry       │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Rate limit (429)       │ ✅ Bəli   │ N dəfə  │ Longer      │
│                        │           │ sonra    │ backoff     │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ JSON parse error       │ ❌ Xeyr   │ Dərhal  │ Log + DLQ   │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Validation error       │ ❌ Xeyr   │ Dərhal  │ Log + DLQ   │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Missing entity (404)   │ ⚠️ 1 dəfə │ Dərhal  │ Confirm +   │
│                        │           │         │ DLQ         │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Auth error (401/403)   │ ❌ Xeyr   │ Dərhal  │ Alert +DLQ  │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Out of memory          │ ⚠️ Ehtiyat │ Dərhal  │ Alert +     │
│                        │ ilə      │         │ investigate │
├────────────────────────┼───────────┼──────────┼─────────────┤
│ Business rule violation│ ❌ Xeyr   │ Dərhal  │ Review +DLQ │
└────────────────────────┴───────────┴──────────┴─────────────┘
```

---

## İntervyu Sualları

### 1. Dead Letter Queue nədir və nə üçün istifadə olunur?

**Cavab:** Dead Letter Queue (DLQ) uğursuz olan və normal emal prosesindən kənarlaşdırılan mesajların saxlandığı xüsusi queue-dur. DLQ-nun əsas məqsədləri:

- **Sonsuz retry dövrəsinin qarşısının alınması:** Consumer mesajı emal edə bilmədikdə mesaj dəfələrlə retry olunur. DLQ olmadan bu sonsuz dövrə sistemi bloklayır.
- **Poison message-lərin izolyasiyası:** Heç vaxt uğurla emal oluna bilməyən mesajlar (malformed data, validation xətası) DLQ-ya köçürülərək normal mesajların axınına mane olmur.
- **Debugging və analiz:** DLQ-dakı mesajlar xətanın səbəbini araşdırmaq üçün saxlanılır — exception, stack trace, retry sayı və s. kontekst ilə.
- **Manual reprocessing:** Xətanın kök səbəbi aradan qaldırıldıqdan sonra DLQ-dakı mesajlar yenidən emal oluna bilər.

RabbitMQ-da bu `dead-letter-exchange` (DLX) mexanizmi ilə, SQS-də `RedrivePolicy` ilə, Kafka-da isə ayrı `.dlq` topic-ə publish etməklə reallaşdırılır. Laravel-də isə `failed_jobs` cədvəli DLQ-nun DB-based versiyasıdır.

### 2. Poison Message nədir? Onu normal transient xətadan necə fərqləndirmək olar?

**Cavab:** Poison Message nə qədər retry etsən də heç vaxt uğurla emal olunmayacaq mesajdır. Transient xətadan fərqi ondadır ki, transient xətalar (network timeout, DB connection drop) müvəqqətidir və retry ilə həll olunur, amma poison message-in xətası mesajın özündədir.

Fərqləndirmə yolları:
- **Exception tipinə görə:** `JsonException`, `TypeError`, `InvalidArgumentException`, `ValidationException` kimi xətalar adətən poison message göstəricisidir. `ConnectionException`, `TimeoutException` isə transient-dir.
- **Retry davranışına görə:** Əgər eyni mesaj ardıcıl olaraq eyni exception ilə fail olursa, bu poison message-dir.
- **Strategiya:** Poison message aşkar edildikdə dərhal DLQ-ya göndərilməlidir (retry etmədən). Laravel-də bunu `$this->fail($exception)` ilə, Symfony-də `UnrecoverableMessageHandlingException` ilə etmək olar.

### 3. RabbitMQ-da Dead Letter Exchange (DLX) necə işləyir?

**Cavab:** RabbitMQ-da DLQ mexanizmi `x-dead-letter-exchange` queue argumenti ilə konfiqurasiya olunur. İş mexanizmi:

1. Main queue yaradılarkən `x-dead-letter-exchange` və (opsional) `x-dead-letter-routing-key` argumentləri təyin olunur.
2. Mesaj bu queue-dan aşağıdakı hallarda DLX-ə yönləndirilir:
   - Consumer mesajı `basic.nack` və ya `basic.reject` ilə `requeue=false` parametri ilə reject edəndə
   - Mesajın TTL-i (`x-message-ttl`) bitəndə
   - Queue max uzunluğa (`x-max-length`) çatanda
3. DLX adi exchange-dir — ona bind olunmuş queue-ya (DLQ) mesaj route olunur.
4. RabbitMQ mesajın `x-death` header-inə əvvəlki queue adı, reject səbəbi, retry sayı kimi məlumatları əlavə edir.

Delayed retry üçün trick: Retry queue TTL ilə yaradılır və onun DLX-i main exchange-ə yönlənir. Beləliklə mesaj retry queue-da TTL qədər gözləyir, sonra avtomatik main queue-ya qayıdır.

### 4. DLQ monitoring-i necə qurulmalıdır? Hansı metriklər izlənməlidir?

**Cavab:** DLQ monitorinqi sistemin sağlamlığının vacib göstəricisidir. İzlənməli metriklər:

- **DLQ Depth (gauge):** Hər queue-nun DLQ-sında neçə mesaj var. Artma trendi problem deməkdir.
- **DLQ Message Rate (counter):** Vahid zamanda DLQ-ya düşən mesaj sayı. Spike ani problemi göstərir.
- **Exception Distribution:** Hansı exception tipləri daha çox DLQ-ya düşürür. Bu kök səbəb analizinə kömək edir.
- **Message Age:** DLQ-dakı ən köhnə mesajın yaşı. Uzun müddət resolve olunmayan mesajlar problemdir.
- **Reprocessing Success Rate:** DLQ-dan retry olunan mesajların uğur faizi.

Alert qaydaları:
- **Warning:** DLQ depth threshold-u keçəndə (məsələn, 10 mesaj)
- **Critical:** DLQ depth yüksək olduqda (50+ mesaj) və ya rate spike olduqda
- **Stale alert:** 24 saatdan köhnə resolve olunmamış mesajlar olduqda

Prometheus + Grafana, Datadog və ya custom dashboard ilə vizualizasiya olunmalıdır. Laravel-də scheduled command ilə hər 5 dəqiqədə yoxlama aparılmalı, Slack/PagerDuty-yə alert göndərilməlidir.

### 5. DLQ-dakı mesajları yenidən emal etmənin (reprocessing) ən yaxşı yolu nədir?

**Cavab:** DLQ reprocessing diqqətli yanaşma tələb edir:

1. **Əvvəlcə analiz et:** DLQ-dakı mesajları kateqoriyalara ayır — poison (retry olunmaz), transient (retry oluna bilər), stale (artıq aktual deyil). Dry-run analiz aparılmalıdır.
2. **Kök səbəbi həll et:** Mesajlar niyə DLQ-ya düşüb? Əgər code bug-dırsa əvvəlcə fix deploy olunmalıdır.
3. **Throttled reprocessing:** Bütün DLQ-nu birdən main queue-ya qaytarma. Bu sistemi yükləyə bilər. Batch-larla (məsələn, hər 5 saniyədə 10 mesaj) göndər.
4. **Circuit breaker:** Reprocessing zamanı əgər mesajlar yenidən fail olursa, reprocessing-i avtomatik dayandır.
5. **Selective retry:** Yalnız müəyyən exception tipi və ya kateqoriyadakı mesajları retry et. Poison message-ləri retry etmə.
6. **Monitor et:** Reprocessing prosesini izlə — neçəsi uğurlu oldu, neçəsi yenidən DLQ-ya düşdü.

Laravel-də `php artisan queue:retry all` əvəzinə custom command ilə filter, throttle və circuit breaker tətbiq etmək daha təhlükəsizdir.

### 6. DLQ olmadan sistemdə nə baş verər? Hansı anti-pattern-lərdən qaçınmaq lazımdır?

**Cavab:** DLQ olmadan:

- **Sonsuz retry dövrəsi:** Poison message queue-nun başında dayanır, dəfələrlə retry olunur, bütün consumer resurslarını israf edir.
- **Head-of-line blocking:** Bir uğursuz mesaj arxasındakı minlərlə normal mesajın işlənməsini gecikdirir.
- **Resurs israfı:** CPU, yaddaş, network bandwidth boş yerə istifadə olunur.
- **Cascading failure:** Queue dolur, producer-lər mesaj göndərə bilmir, bütün sistem dayanır.

Anti-pattern-lər:
- **DLQ-nu ignore etmək:** DLQ qurulub amma heç kim mesajlara baxmır. DLQ mesajları yığılır, disk dolur.
- **Kütləvi reprocessing:** Bütün DLQ-nu birdən retry etmək — sistemə həddindən artıq yük verir, mesajlar yenidən DLQ-ya düşür.
- **DLQ-suz sonsuz retry:** `requeue=true` ilə mesajı hər dəfə queue-ya qaytarmaq. Max retry limiti olmalıdır.
- **Kontekstsiz DLQ:** DLQ-ya yalnız mesaj body yazılır, exception, retry sayı, timestamp kimi məlumatlar saxlanılmır. Bu debugging-i çətinləşdirir.
- **Tək DLQ bütün queue-lar üçün:** Fərqli queue-ların mesajları bir DLQ-da qarışır. Hər queue-nun öz DLQ-su olmalıdır.
