# Outbox Pattern, Inbox Pattern, Dual Write Problem (Senior)

## Mündəricat
1. [Dual Write Problemi](#dual-write-problemi)
2. [Outbox Pattern](#outbox-pattern)
3. [PHP/MySQL Implementasiyası](#phpmysql-implementasiyası)
4. [Message Relay](#message-relay)
5. [Inbox Pattern](#inbox-pattern)
6. [At-least-once vs Exactly-once](#at-least-once-vs-exactly-once)
7. [Tam Nümunə: Order → RabbitMQ → Inventory](#tam-nümunə)
8. [Uğursuzluq Ssenariləri](#uğursuzluq-ssenariləri)
9. [Performance Mülahizələri](#performance-mülahizələri)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Dual Write Problemi

```
// Adi kod — görünüşdə sadə, əslində problematik:
DB::transaction(function () use ($order) {
    $order->save();                    // DB-ə yaz ✅
});
$this->messageBus->publish($event);    // Queue-ya göndər ← PROBLEM!
```

**Nə baş verə bilər:**

```
Ssenari 1: DB uğurlu, Queue uğursuz
  → Order DB-dədir, amma event heç vaxt göndərilmir
  → Inventory servis xəbər tutmur → inkonsentent vəziyyət!

Ssenari 2: Queue uğurlu, DB uğursuz (nadir)
  → Event göndərildi, amma DB-də order yoxdur
  → Inventory rezerv etdi, sifariş yoxdur!

Ssenari 3: Server crash — DB commit-dən sonra, Queue-dan əvvəl
  → Eyni Ssenari 1 kimi
```

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Service   │───►│  Database   │    │  Message    │
│             │    │             │    │   Broker    │
│             │───►│             │    │  (Rabbit)   │
└─────────────┘    └─────────────┘    └─────────────┘
       │                                      │
       └──── Bu ikisi atomik deyil! ──────────┘
```

---

## Outbox Pattern

**Həll:** DB-yə order ilə birlikdə eyni transaction-da "outbox" cədvəlinə event yaz. Sonra ayrı proses bu cədvəli oxuyub message broker-ə göndərir.

```
┌─────────────┐    ┌──────────────────────────────────┐
│   Service   │───►│           Database               │
│             │    │  ┌──────────┐  ┌───────────────┐ │
│ TRANSACTION │    │  │  orders  │  │  outbox_msgs  │ │
│             │    │  │  table   │  │  (new event)  │ │
│             │    │  └──────────┘  └───────────────┘ │
└─────────────┘    └──────────────────┬───────────────┘
                                       │ (same transaction!)
                   ┌───────────────────▼───────────────┐
                   │         Message Relay             │
                   │   (poll outbox → publish → mark) │
                   └───────────────────┬───────────────┘
                                       │
                   ┌───────────────────▼───────────────┐
                   │          Message Broker           │
                   │          (RabbitMQ/Kafka)         │
                   └───────────────────────────────────┘
```

**Açar prinsip:** Order və event **eyni DB transaction-ında** yazılır. Ya ikisi birlikdə commit olur, ya da ikisi birlikdə rollback olur.

---

## PHP/MySQL Implementasiyası

**Outbox cədvəli:**

```sql
CREATE TABLE outbox_messages (
    id          CHAR(36) PRIMARY KEY,
    aggregate_type VARCHAR(100) NOT NULL,  -- 'Order'
    aggregate_id   VARCHAR(100) NOT NULL,  -- order ID
    event_type     VARCHAR(200) NOT NULL,  -- 'OrderPlaced'
    payload        JSON NOT NULL,
    status         ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at        TIMESTAMP NULL,
    retry_count    INT DEFAULT 0,
    INDEX idx_status (status, created_at)
);
```

**Transaction daxilində event yazma:**

```php
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            // 1. Order yarat
            $order = Order::create([
                'customer_id' => $data->customerId,
                'total'       => $data->total,
                'status'      => 'pending',
            ]);
            
            // 2. Eyni transaction-da outbox-a yaz
            OutboxMessage::create([
                'id'             => Str::uuid(),
                'aggregate_type' => 'Order',
                'aggregate_id'   => $order->id,
                'event_type'     => 'OrderPlaced',
                'payload'        => json_encode([
                    'order_id'    => $order->id,
                    'customer_id' => $order->customer_id,
                    'total'       => $order->total,
                    'occurred_at' => now()->toIso8601String(),
                ]),
                'status' => 'pending',
            ]);
            
            return $order;
            // Transaction commit: HƏR İKİSİ commit olur
            // Transaction rollback: HƏR İKİSİ rollback olur
        });
    }
}
```

---

## Message Relay

Outbox cədvəlini polling edib event-ləri message broker-ə göndərən proses:

*Outbox cədvəlini polling edib event-ləri message broker-ə göndərən pro üçün kod nümunəsi:*
```php
class OutboxMessageRelay
{
    public function __construct(
        private MessageBus $messageBus,
        private int $batchSize = 100
    ) {}
    
    public function publishPendingMessages(): void
    {
        // Pending message-ları al (pessimistic lock ilə — race condition önlə)
        $messages = OutboxMessage::where('status', 'pending')
            ->orderBy('created_at')
            ->limit($this->batchSize)
            ->lockForUpdate()  // SELECT ... FOR UPDATE
            ->get();
        
        foreach ($messages as $message) {
            $this->publishMessage($message);
        }
    }
    
    private function publishMessage(OutboxMessage $message): void
    {
        try {
            // "Processing" kimi işarələ (idempotency)
            $message->update(['status' => 'processing']);
            
            // Message broker-ə göndər
            $this->messageBus->publish(
                exchange: $message->aggregate_type,
                routingKey: $message->event_type,
                body: $message->payload,
                messageId: $message->id  // idempotency key
            );
            
            // Uğurlu — "sent" kimi işarələ
            $message->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Uğursuz — retry üçün "pending"ə qaytar
            $message->increment('retry_count');
            $message->update([
                'status' => $message->retry_count >= 5 ? 'failed' : 'pending',
            ]);
            
            Log::error("Outbox relay xəta: {$e->getMessage()}", [
                'message_id' => $message->id,
                'retry_count' => $message->retry_count,
            ]);
        }
    }
}

// Laravel Scheduler ilə:
class OutboxRelayCommand extends Command
{
    protected $signature = 'outbox:relay';
    
    public function handle(OutboxMessageRelay $relay): void
    {
        $relay->publishPendingMessages();
    }
}

// Kernel.php
$schedule->command('outbox:relay')->everyFiveSeconds();
```

**CDC (Change Data Capture) alternativ:**

```
MySQL binlog → Debezium → Kafka
  (Transaction log-u oxuyur, polling lazım deyil)
  Daha effektiv amma infrastruktur tələb edir

CDC üstünlükləri:
  ✅ Near real-time (polling gecikmesi yox)
  ✅ DB-yə əlavə sorğu yoxdur
  ✅ Outbox table-ı oxumaq lazım deyil — birbaşa binlog-dan oxuyur
  
CDC çatışmazlıqları:
  ❌ Debezium/Kafka infrastrukturu lazımdır
  ❌ MySQL binlog_format=ROW tələb olunur
  ❌ Schema dəyişiklikləri CDC connector-u sındıra bilər
```

---

## Inbox Pattern

**Problem:** Message broker eyni message-ı bir neçə dəfə göndərə bilər (at-least-once delivery). Consumer idempotent olmalıdır.

**Həll: Inbox cədvəli:**

```sql
CREATE TABLE inbox_messages (
    message_id  CHAR(36) PRIMARY KEY,  -- Message broker-dən gələn ID
    event_type  VARCHAR(200) NOT NULL,
    payload     JSON NOT NULL,
    processed_at TIMESTAMP NULL,
    INDEX idx_processed (processed_at)
);
```

*INDEX idx_processed (processed_at) üçün kod nümunəsi:*
```php
class InventoryConsumer
{
    public function handleOrderPlaced(array $message): void
    {
        $messageId = $message['message_id'];
        
        DB::transaction(function () use ($messageId, $message) {
            // Artıq işlənib? (idempotency check)
            if (InboxMessage::where('message_id', $messageId)->exists()) {
                Log::info("Dublikat message atlandı: $messageId");
                return;
            }
            
            // Inbox-a yaz (eyni transaction-da)
            InboxMessage::create([
                'message_id'   => $messageId,
                'event_type'   => $message['event_type'],
                'payload'      => json_encode($message['payload']),
                'processed_at' => now(),
            ]);
            
            // İşi gör
            $this->reserveStock($message['payload']);
        });
    }
}
```

---

## At-least-once vs Exactly-once

```
At-least-once delivery:
  ✅ Mesaj ən azı bir dəfə çatdırılır
  ❌ Duplikat ola bilər
  → Consumer idempotent olmalıdır (Inbox pattern)

At-most-once delivery:
  ✅ Duplikat yoxdur
  ❌ Mesaj itirilə bilər
  → Kritik əməliyyatlar üçün uyğun deyil

Exactly-once delivery:
  ✅ Nə duplikat, nə itirilmə
  ❌ Çox çətin, yavaş, bəzən mümkün deyil
  → Outbox + Inbox birlikdə ~ exactly-once semantics verir
```

---

## Tam Nümunə

```
Order Service                 RabbitMQ            Inventory Service
     │                            │                       │
  [Order + Outbox TX]             │                       │
     │                            │                       │
  [Outbox Relay]                  │                       │
     │─── OrderPlaced ───────────►│                       │
     │                            │─── OrderPlaced ──────►│
     │                            │              [Inbox check]
     │                            │              [Reserve stock TX]
     │                            │              [Inbox insert TX]
     │                            │                       │
     │                            │◄─── StockReserved ────│
     │◄─── StockReserved ─────────│                       │
```

---

## Uğursuzluq Ssenariləri

```
Ssenari 1: Relay crash — message göndərilməmişdir
  → Restart: pending message-lar yenidən göndərilir ✅

Ssenari 2: Message göndərildi, "sent" yazılmadı
  → Restart: eyni message yenidən göndərilir (at-least-once)
  → Consumer Inbox pattern ilə duplikatı tanır ✅

Ssenari 3: Consumer işlədi, amma ACK göndərmədi
  → Broker message-ı yenidən göndərir
  → Inbox idempotency key ilə dublikatı bloklayır ✅

Ssenari 4: Outbox cədvəli çox böyüyür
  → Sent message-ları 7 gündən sonra arxivlə/sil ✅
```

---

## Performance Mülahizələri

*Performance Mülahizələri üçün kod nümunəsi:*
```php
// Outbox cədvəlinə index əlavə et
Schema::table('outbox_messages', function (Blueprint $table) {
    $table->index(['status', 'created_at']);  // Relay üçün
    $table->index(['aggregate_id', 'aggregate_type']);  // Lookup üçün
});

// Batch publish — bir neçə message-ı birlikdə göndər
public function publishBatch(Collection $messages): void
{
    $this->messageBus->batch(function (BatchPublisher $batch) use ($messages) {
        foreach ($messages as $message) {
            $batch->add(
                exchange: $message->aggregate_type,
                routingKey: $message->event_type,
                body: $message->payload,
                messageId: $message->id
            );
        }
    });
    
    OutboxMessage::whereIn('id', $messages->pluck('id'))
        ->update(['status' => 'sent', 'sent_at' => now()]);
}
```

---

## İntervyu Sualları

**1. Dual write problemi nədir?**
DB-yə yazma və message broker-ə event göndərma atomik deyil. Server crash olsa, DB commit-dən sonra broker-ə göndərə bilmirsə, event itirilir — servisler arası inkonsistentlik baş verir.

**2. Outbox pattern-i necə həll edir?**
Event-i message broker-ə göndərmək əvəzinə, business entity ilə eyni transaction-da DB-dəki outbox cədvəlinə yazılır. Ayrı bir relay prosesi cədvəli polling edib broker-ə göndərir. Transaction semantics sayəsində ya hər ikisi commit olur, ya da heç biri.

**3. Inbox pattern nə üçün lazımdır?**
Message broker at-least-once delivery zəmanəti verir — eyni message bir neçə dəfə çata bilər. Inbox cədvəli message ID-ni saxlayır; dublikat gəldikdə transaction-ı skip edir. Bu consumer-i idempotent edir.

**4. Polling relay vs CDC (Change Data Capture) fərqi nədir?**
Polling: outbox cədvəlini müntəzəm sorğulayır, sadə amma gecikməlidir. CDC (Debezium): MySQL binlog-u real vaxtda oxuyur, daha az gecikdirmə, amma infrastruktur tələb edir (Kafka, Debezium connector).

**5. Outbox cədvəli çox böyüyərsə nə etmək lazımdır?**
`sent` statuslu köhnə mesajları arxivlə yaxud sil (məs: 7 gündən köhnələr). Partition by `created_at` ilə DB partitioning istifadə et. Monitoring: `pending` count-u yüksəlirsə relay geridə qalıb.

**6. "Outbox + Inbox" birlikdə exactly-once semantics verirmi?**
Dəqiq yox — at-least-once-a yaxın exactly-once semantics verir. Outbox at-least-once delivery zəmanəti verir (relay retry edə bilər). Inbox idempotency key ilə dublikat processing-i bloklayır. Nəticə: mesaj ən azı bir dəfə çatır, amma consumer-da yalnız bir dəfə emal edilir.

**7. Relay prosesini `SELECT FOR UPDATE SKIP LOCKED` ilə niyə istifadə edirlər?**
Bir neçə relay instansiyası paralel işlədikdə eyni mesajı iki dərfə götürməsin deyə. `FOR UPDATE` row-u lock edir. `SKIP LOCKED` lock-lu row-ları keçir — gözləmir, növbəti azad mesaja keçir. Bu sayədə horizontal scale edilmiş relay worker-ləri üst-üstə düşmür.

---

## Anti-patternlər

**1. Dual write problemini bilərəkdən ignore etmək**
DB-yə yazıb sonra birbaşa `Event::dispatch()` ya da `$queue->push()` çağırmaq — server DB commit-dən sonra, amma message göndərmədən çöksə event itirilir, servislərarası inkonsistentlik baş verir. Outbox pattern-ini tətbiq et: event-i business entity ilə eyni transaction-da outbox cədvəlinə yaz.

**2. Outbox relay-ini çox yüksək tezlikdə polling etmək**
Hər 100ms-də outbox cədvəlini sorğulamaq — DB-yə lazımsız yük yaranır. Alternativ olaraq polling tezliyini çox aşağı saxlamaq — event gecikməsi artır. CDC (Change Data Capture) ilə Debezium işlət, ya da polling intervalını trafik modelinə görə tənzimlə.

**3. Outbox mesajlarını heç vaxt silməmək**
Göndərilmiş mesajları outbox cədvəlindən təmizlənməmək — cədvəl sonsuz böyüyür, sorğular yavaşlayır, disk dolar. Göndərilmiş mesajları müəyyən vaxtdan sonra (məs: 7 gün) arxivlə ya da sil, `status='sent'` + `created_at` üzərindən index qur.

**4. Inbox pattern olmadan consumer-i idempotent saymaq**
Broker-in at-least-once delivery zəmanətini nəzərə almadan message-i hər dəfə emal etmək — dublikat message dublikat əməliyyata (ikinci ödəniş, ikinci email) səbəb olur. Inbox cədvəlinə `message_id` saxla, dublikat gəldikdə transaction-ı skip et.

**5. Outbox relay-ini tək nöqtə uğursuzluğu (SPOF) kimi qurmaq**
Yalnız bir relay prosesi işlətmək — proses çöksə outbox mesajları yığılır, broker-ə çatmır, event gecikməsi artır. Relay-i birden çox instance-da işlət, `SELECT FOR UPDATE SKIP LOCKED` ilə mesajları paylaş, monitoring ilə `pending` count-u izlə.

**6. Outbox cədvəlini əsas iş cədvəlləri ilə eyni transaction olmadan yazmaq**
Business data-nı bir DB connection-da, outbox event-ini başqa connection-da yazmaq — iki ayrı əməliyyat atomik deyil, dual write problemi həll edilmir. Hər ikisi mütləq eyni DB transaction-ı içərisindəki eyni connection-da yazılmalıdır.
