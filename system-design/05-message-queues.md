# Message Queues

## Nədir? (What is it?)

Message queue asinxron kommunikasiya mexanizmidir. Producer mesaj göndərir, queue saxlayır,
consumer emal edir. Bu decouple edir - producer consumer-in mövcudluğunu bilmir. Yavaş
əməliyyatları (email, video processing, report generation) background-a keçirmək üçün istifadə olunur.

```
[Producer] -> [Message Queue] -> [Consumer]
  (Web App)    (RabbitMQ/Kafka)   (Worker Process)

User clicks "Export PDF"
  -> Web app queue-ya mesaj göndərir (1ms)
  -> User-ə "Processing..." cavab verilir
  -> Worker PDF yaradır (30 saniyə)
  -> User notification alır
```

## Əsas Konseptlər (Key Concepts)

### Point-to-Point vs Pub/Sub

**Point-to-Point (Queue)**
Bir mesaj yalnız bir consumer tərəfindən emal olunur.

```
Producer -> [Queue] -> Consumer A (mesajı alır)
                       Consumer B (bu mesajı almır, növbəti mesajı alır)
```

Use case: Task distribution, job processing

**Pub/Sub (Topic)**
Bir mesaj bütün subscriber-lərə çatdırılır.

```
Publisher -> [Topic] -> Subscriber A (mesajı alır)
                     -> Subscriber B (mesajı alır)
                     -> Subscriber C (mesajı alır)
```

Use case: Event notification, real-time updates

### Message Queue Providerləri

**RabbitMQ**
- AMQP protocol
- Exchange types: direct, fanout, topic, headers
- Message acknowledgment, persistence
- Dead letter exchange
- Priority queues
- Yaxşıdır: Complex routing, traditional messaging

**Apache Kafka**
- Distributed event streaming platform
- Log-based storage (append-only)
- Consumer groups, partitions
- High throughput (millions msg/sec)
- Message retention (days/weeks)
- Yaxşıdır: Event streaming, high volume, replay

**AWS SQS**
- Fully managed, serverless
- Standard queue (at-least-once, best-effort ordering)
- FIFO queue (exactly-once, strict ordering)
- Dead letter queue built-in
- Yaxşıdır: AWS ecosystem, simplicity

**Redis Streams**
- Redis-in stream data structure-u
- Consumer groups
- Yaxşıdır: Lightweight, already Redis istifadə edirsinizsə

### Delivery Guarantees

```
At-most-once:   Mesaj itirilə bilər, amma duplicate olmaz
                Fire and forget. Ən sürətli.
                Use case: Metrics, logging

At-least-once:  Mesaj mütləq çatdırılır, amma duplicate ola bilər
                Retry mexanizmi ilə. Consumer idempotent olmalıdır.
                Use case: Email, notifications (ən geniş yayılmış)

Exactly-once:   Mesaj dəqiq bir dəfə emal olunur
                Ən çətin, daha yavaş. Kafka transactions ilə.
                Use case: Financial transactions
```

### Dead Letter Queue (DLQ)

Emal oluna bilməyən mesajlar DLQ-ya göndərilir.

```
Main Queue -> Consumer (fail) -> Retry 1 -> Retry 2 -> Retry 3 -> DLQ
                                                                    |
                                                        Manual review/fix
```

### Message Ordering

```
Kafka: Partition daxilində ordering zəmanət verilir
       Fərqli partition-lar arasında ordering yoxdur
       Key-based partitioning: eyni key eyni partition-a

SQS FIFO: MessageGroupId ilə ordering
          Group daxilində strict FIFO

RabbitMQ: Tək queue, tək consumer ilə ordering
          Multiple consumer-da ordering zəmanət verilmir
```

## Arxitektura (Architecture)

### Kafka Cluster Arxitekturası

```
[Producer 1] ──┐
[Producer 2] ──┤──> [Kafka Cluster]
[Producer 3] ──┘    ├── Broker 1
                     │   ├── Topic A, Partition 0 (Leader)
                     │   └── Topic B, Partition 1 (Replica)
                     ├── Broker 2
                     │   ├── Topic A, Partition 1 (Leader)
                     │   └── Topic A, Partition 0 (Replica)
                     └── Broker 3
                         ├── Topic B, Partition 0 (Leader)
                         └── Topic A, Partition 1 (Replica)

[ZooKeeper / KRaft] - Cluster coordination

Consumer Group 1:
  Consumer A -> Partition 0
  Consumer B -> Partition 1

Consumer Group 2 (fərqli app):
  Consumer C -> Partition 0, 1 (hər ikisini oxuyur)
```

### RabbitMQ Exchange Routing

```
[Producer]
    |
[Exchange] --routing_key="order.created"--> [Queue: order-processing]
    |
    +------routing_key="order.*"---------> [Queue: order-analytics]
    |
    +------routing_key="*.created"-------> [Queue: audit-log]

Exchange Types:
  Direct:  Exact routing key match
  Fanout:  All bound queues (broadcast)
  Topic:   Pattern matching (order.*, *.created)
  Headers: Header attributes match
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Queue System

```php
// .env
QUEUE_CONNECTION=redis

// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
    'sqs' => [
        'driver' => 'sqs',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
        'queue' => env('SQS_QUEUE', 'default'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
],
```

### Job Creation

```php
// app/Jobs/ProcessOrderJob.php
namespace App\Jobs;

use App\Models\Order;
use App\Services\PaymentService;
use App\Services\InventoryService;
use App\Notifications\OrderConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;
    public int $timeout = 120;
    public array $backoff = [10, 30, 60]; // retry delays

    public function __construct(
        public Order $order
    ) {}

    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->order->id),
            new RateLimited('orders'),
        ];
    }

    public function handle(PaymentService $payment, InventoryService $inventory): void
    {
        // Payment charge
        $payment->charge($this->order);

        // Inventory reserve
        $inventory->reserve($this->order->items);

        // Notification
        $this->order->user->notify(new OrderConfirmation($this->order));

        // Update status
        $this->order->update(['status' => 'processed']);
    }

    public function failed(\Throwable $exception): void
    {
        // DLQ-ya düşdükdə
        logger()->error('Order processing failed', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);

        $this->order->update(['status' => 'failed']);
        // Admin-ə xəbər ver
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }
}
```

### Dispatching Jobs

```php
// Sadə dispatch
ProcessOrderJob::dispatch($order);

// Queue və delay ilə
ProcessOrderJob::dispatch($order)
    ->onQueue('orders')
    ->delay(now()->addMinutes(5));

// Chain - ardıcıl icra
Bus::chain([
    new ProcessPaymentJob($order),
    new UpdateInventoryJob($order),
    new SendConfirmationJob($order),
])->onQueue('orders')->dispatch();

// Batch - parallel icra
Bus::batch([
    new SendEmailJob($user1),
    new SendEmailJob($user2),
    new SendEmailJob($user3),
])->then(function (Batch $batch) {
    // Hamısı bitdi
})->catch(function (Batch $batch, \Throwable $e) {
    // Biri fail oldu
})->finally(function (Batch $batch) {
    // Hamısı bitdi (uğurlu və ya uğursuz)
})->onQueue('emails')->dispatch();
```

### Laravel Horizon

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'emails'],
            'balance' => 'auto', // auto-scaling
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-2' => [
            'connection' => 'redis',
            'queue' => ['orders'],
            'balance' => 'simple',
            'processes' => 5,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

```bash
# Horizon başlat
php artisan horizon

# Queue worker (Horizon olmadan)
php artisan queue:work redis --queue=orders,default --tries=3 --timeout=60

# Failed jobs
php artisan queue:failed
php artisan queue:retry all
php artisan queue:retry 5  # specific job ID
php artisan queue:flush    # bütün failed jobs sil
```

### Event-Driven Queue Pattern

```php
// app/Events/OrderPlaced.php
class OrderPlaced
{
    public function __construct(public Order $order) {}
}

// app/Listeners/ProcessPayment.php
class ProcessPayment implements ShouldQueue
{
    public string $queue = 'payments';

    public function handle(OrderPlaced $event): void
    {
        PaymentService::charge($event->order);
    }
}

// app/Listeners/SendOrderEmail.php
class SendOrderEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}

// EventServiceProvider
protected $listen = [
    OrderPlaced::class => [
        ProcessPayment::class,     // async, payments queue
        SendOrderEmail::class,     // async, emails queue
        UpdateInventory::class,    // async
    ],
];
```

## Real-World Nümunələr

**LinkedIn:** Kafka-nı yaratdı. Gündəlik 7 trillion+ mesaj. Activity stream, metrics,
logging, change data capture üçün istifadə edir.

**Uber:** Kafka ilə trip data streaming. Real-time pricing, ETA hesablama, driver matching.
Apache Kafka + custom consumer framework.

**Slack:** Message delivery üçün custom queue system. Hər workspace ayrı queue.
Millions of messages/second real-time çatdırılır.

**Shopify:** Background jobs üçün Kafka. Order processing, inventory sync, webhook
delivery. Black Friday-da milyonlarla job emal edir.

## Interview Sualları

**S: Niyə message queue istifadə edilir?**
C: 1) Asynchronous processing - yavaş əməliyyatları background-a keçirmək
2) Decoupling - service-lər bir-birindən asılı olmur
3) Buffering - trafik spike zamanı mesajları saxlayır
4) Reliability - mesaj itirilmir, retry mexanizmi var

**S: Kafka vs RabbitMQ fərqi?**
C: Kafka event streaming üçün, yüksək throughput, mesajlar saxlanılır (replay mümkün).
RabbitMQ traditional messaging üçün, complex routing, mesaj emal olunduqda silinir.
Kafka log-based, RabbitMQ queue-based.

**S: Exactly-once delivery necə təmin olunur?**
C: Tam exactly-once çox çətindir. Praktikada at-least-once + idempotent consumer
istifadə olunur. Kafka Transactions ilə producer/consumer arasında exactly-once
mümkündür. Consumer-da unique ID ilə duplicate check edilir.

**S: Message ordering necə təmin olunur?**
C: Kafka-da partition key istifadə etmək - eyni key eyni partition-a düşür, partition
daxilində ordering var. SQS FIFO-da MessageGroupId. Amma ordering throughput-u azaldır.

## Best Practices

1. **İdempotent consumers** - Eyni mesajı 2 dəfə emal etmək eyni nəticə verməlidir
2. **DLQ konfiqurasiya edin** - Failed mesajları itirməyin
3. **Message size kiçik olsun** - Böyük payload əvəzinə reference (S3 URL, DB ID) göndərin
4. **Monitoring** - Queue depth, consumer lag, error rate izləyin
5. **Backpressure** - Consumer yavaşlayanda producer-i limitləyin
6. **Retry with backoff** - Exponential backoff istifadə edin (1s, 2s, 4s, 8s)
7. **Poison pill handling** - Emal oluna bilməyən mesajları detect edib DLQ-ya göndərin
8. **Queue per concern** - Emails, orders, analytics üçün ayrı queue-lar
