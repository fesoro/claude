# Outbox Pattern (Senior ⭐⭐⭐)

## İcmal

Outbox Pattern — DB write əməliyyatı ilə message broker-ə event göndərməyi atomik etməyin yeganə etibarlı yoludur. Event-i birbaşa broker-ə göndərmək əvəzinə, business entity ilə eyni DB transaction-ında `outbox` cədvəlinə yazılır. Ayrı bir relay prosesi cədvəli polling edib broker-ə göndərir. Dual write problemi bu şəkildə həll edilir.

## Niyə Vacibdir

Əksər developer-lər `$order->save(); $bus->publish($event);` yazır — görünüşdə sadə, əslində ciddi risk. Server DB commit-dən sonra, broker-ə göndərməzdən çöksə event itirilir. Order DB-dədir, lakin inventory servis xəbər tutmur — sistemlər arasında inkonsistentlik baş verir. Outbox bu iki əməliyyatı tək DB transaction-ına sıxışdırır: ya ikisi birlikdə, ya heç biri.

## Əsas Anlayışlar

- **Dual write problem**: DB write + broker publish atomik deyil; server crash ikisini ayıra bilər
- **Outbox table**: business entity ilə eyni transaction-da yazılan `pending` event-lər cədvəli
- **Message Relay**: outbox-u polling edib event-ləri broker-ə göndərən ayrı proses
- **Inbox pattern**: consumer tərəfdə idempotency — eyni message iki dəfə gəlsə bir dəfə işlənsin
- **At-least-once delivery**: relay retry edə bilər → consumer idempotent olmalıdır
- **CDC (Change Data Capture)**: DB transaction log-unu oxuyub outbox-u polling etmədən event publish edən alternativ (Debezium)

## Praktik Baxış

- **Real istifadə**: microservice-lər arası event publish (Order → Payment, Order → Inventory), saga başlatmaq, notification trigger, audit log
- **Trade-off-lar**: at-least-once zəmanəti (exactly-once üçün Inbox lazım); polling gecikmə yaradır (CDC ilə aradan qalxır); outbox cədvəli böyüyür (cleanup lazım); relay SPOF ola bilər (birden çox instance + `SKIP LOCKED`)
- **İstifadə etməmək**: tək servis daxilindəki sync əməliyyatlar üçün; event-lərin itirilməsi kritik deyilsə; eventual consistency qəbul edilmirsə
- **Common mistakes**: outbox-u business entity-dən ayrı transaction-da yazmaq; relay-i tək instance çalışdırmaq; sent message-ları heç silməmək

## Anti-Pattern Nə Zaman Olur?

**Polling interval çox kiçik — DB pressure:**
Hər 100ms-də `SELECT ... WHERE status='pending'` — DB-yə lazımsız yük yaranır, connection pool tükənir. Polling intervalını trafik modelinə görə tənzimlə (5s–30s), ya da CDC (Debezium) ilə binlog-dan real-time oxu.

**Idempotency olmadan duplicate processing:**
Relay retry edib eyni message-ı iki dəfə broker-ə göndərə bilər. Consumer-də inbox cədvəli (message_id PK) olmasa: ikinci ödəniş, ikinci email, ikinci stok azalması baş verə bilər. Outbox + Inbox birlikdə işlənməlidir.

**Relay-i SPOF kimi qurmaq:**
Yalnız bir relay instansiyası işlədikdə — proses çöksə outbox message-ları yığılır. `SELECT FOR UPDATE SKIP LOCKED` ilə bir neçə relay worker-i paralel işlədə bilərsiniz, race condition olmadan.

**Outbox cədvəlini heç təmizləməmək:**
`status='sent'` olan milyonlarla sıradan polling sorğuları yavaşlayır, disk dolar. Göndərilmiş message-ları 7 gündən sonra arxivlə/sil, `(status, created_at)` index qur.

## Nümunələr

### Ümumi Nümunə

Order service sifarişi yaradır: `orders` cədvəlinə `INSERT`, eyni transaction-da `outbox_messages` cədvəlinə `OrderPlaced` event-ini yaz. Relay prosesi (cron/daemon) outbox-u polling edib RabbitMQ-ya göndərir, sonra `status='sent'` edir. Inventory service RabbitMQ-dan oxuyur, inbox cədvəlindəki `message_id`-ni yoxlayır — dublikat gəlsə skip edir.

### PHP/Laravel Nümunəsi

```php
<?php

// Outbox cədvəli — migration
// Schema::create('outbox_messages', function (Blueprint $table) {
//     $table->char('id', 36)->primary();
//     $table->string('aggregate_type', 100);
//     $table->string('aggregate_id', 100);
//     $table->string('event_type', 200);
//     $table->json('payload');
//     $table->enum('status', ['pending', 'processing', 'sent', 'failed'])->default('pending');
//     $table->unsignedSmallInteger('retry_count')->default(0);
//     $table->timestamp('created_at')->useCurrent();
//     $table->timestamp('sent_at')->nullable();
//     $table->index(['status', 'created_at']);
// });

class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            // 1. Business entity yarat
            $order = Order::create([
                'customer_id' => $data->customerId,
                'total'       => $data->total,
                'status'      => 'pending',
            ]);

            // 2. Eyni transaction-da outbox-a yaz — ATOMIK
            // WHY: ya ikisi birlikdə commit olur, ya heç biri
            DB::table('outbox_messages')->insert([
                'id'             => (string) \Illuminate\Support\Str::uuid(),
                'aggregate_type' => 'Order',
                'aggregate_id'   => $order->id,
                'event_type'     => 'OrderPlaced',
                'payload'        => json_encode([
                    'order_id'    => $order->id,
                    'customer_id' => $order->customer_id,
                    'total'       => $order->total,
                    'occurred_at' => now()->toIso8601String(),
                ]),
                'status'     => 'pending',
                'created_at' => now(),
            ]);

            return $order;
        });
    }
}
```

```php
<?php

// Message Relay — outbox cədvəlini polling edib broker-ə göndərir
class OutboxMessageRelay
{
    public function __construct(
        private MessageBus $messageBus,
        private int $batchSize = 100
    ) {}

    public function publishPendingMessages(): void
    {
        // SKIP LOCKED — paralel relay worker-ləri üçün race condition önlənir
        DB::transaction(function () {
            $messages = DB::table('outbox_messages')
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit($this->batchSize)
                ->lockForUpdate()      // SELECT ... FOR UPDATE SKIP LOCKED
                ->get();

            foreach ($messages as $message) {
                $this->publishMessage($message);
            }
        });
    }

    private function publishMessage(object $message): void
    {
        try {
            DB::table('outbox_messages')
                ->where('id', $message->id)
                ->update(['status' => 'processing']);

            $this->messageBus->publish(
                exchange:   $message->aggregate_type,
                routingKey: $message->event_type,
                body:       $message->payload,
                messageId:  $message->id   // idempotency key broker-ə
            );

            DB::table('outbox_messages')
                ->where('id', $message->id)
                ->update(['status' => 'sent', 'sent_at' => now()]);

        } catch (\Exception $e) {
            $retryCount = $message->retry_count + 1;
            DB::table('outbox_messages')
                ->where('id', $message->id)
                ->update([
                    'status'      => $retryCount >= 5 ? 'failed' : 'pending',
                    'retry_count' => $retryCount,
                ]);

            \Log::error("Outbox relay xəta: {$e->getMessage()}", [
                'message_id'  => $message->id,
                'retry_count' => $retryCount,
            ]);
        }
    }
}

// Artisan command — Scheduler ilə çağrılır
class OutboxRelayCommand extends \Illuminate\Console\Command
{
    protected $signature = 'outbox:relay';

    public function handle(OutboxMessageRelay $relay): void
    {
        $relay->publishPendingMessages();
    }
}

// Kernel.php
// $schedule->command('outbox:relay')->everyFiveSeconds();
```

```php
<?php

// Inbox Pattern — consumer tərəfdə idempotency
// Schema::create('inbox_messages', function (Blueprint $table) {
//     $table->char('message_id', 36)->primary();  // dublikat PK conflict atar
//     $table->string('event_type', 200);
//     $table->json('payload');
//     $table->timestamp('processed_at')->useCurrent();
// });

class InventoryConsumer
{
    public function handleOrderPlaced(array $message): void
    {
        $messageId = $message['message_id'];

        DB::transaction(function () use ($messageId, $message) {
            // Artıq işlənib? — inbox cədvəlində varsa skip et
            if (DB::table('inbox_messages')->where('message_id', $messageId)->exists()) {
                \Log::info("Dublikat message atlandı: {$messageId}");
                return;
            }

            // Inbox-a yaz (eyni transaction-da)
            DB::table('inbox_messages')->insert([
                'message_id'   => $messageId,
                'event_type'   => $message['event_type'],
                'payload'      => json_encode($message['payload']),
                'processed_at' => now(),
            ]);

            // Əsl iş
            $this->reserveStock($message['payload']);
        });
    }
}
```

## Praktik Tapşırıqlar

1. `outbox_messages` migration yaradın; `OrderService.placeOrder()` metodunu transaction içinə alın; outbox-a event yazın; PHPUnit test: server crash simulyasiyası — order var, event var; rollback — heç biri yoxdur
2. `OutboxMessageRelay` yazın: `pending` mesajları `SELECT FOR UPDATE SKIP LOCKED` ilə al; broker-ə göndər; `sent` kimi işarələ; retry logic — 5 dəfədən sonra `failed`
3. Inbox pattern tətbiq edin: `inbox_messages` cədvəli, PK `message_id`; consumer eyni message ikinci dəfə gəldikdə skip etsin; test: relay eyni message-ı iki dəfə göndərir, `reserveStock` yalnız bir dəfə çağrılır
4. Cleanup job yazın: 7 gündən köhnə `status='sent'` mesajları sil; cron ilə gecə işlətsin; monitoring: `pending` sayı artırsa alert

## Əlaqəli Mövzular

- [Saga Pattern](03-saga-pattern.md) — saga addımları outbox ilə reliable event publish edir
- [Two-Phase Commit](05-two-phase-commit.md) — outbox-un alternativ; strong consistency, amma blocking
- [Event Sourcing](02-event-sourcing.md) — event store append-only; outbox ilə sinerjik
- [Domain Events](../ddd/05-ddd-domain-events.md) — outbox-da yazılan event-lər domain event-lərdir
- [Event Listener](../laravel/05-event-listener.md) — Laravel event system ilə outbox inteqrasiyası
