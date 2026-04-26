# Change Data Capture (CDC) & Outbox Pattern (Lead)

## İcmal

Microservices və event-driven sistemlərdə tez-tez rast gəlinən problem — **dual-write problem**. Service bir əməliyyatda həm DB-yə yazmalı, həm də Kafka/RabbitMQ-ya event publish etməlidir. Bu iki yazını **atomik** etmək mümkün deyil — biri uğurlu, digəri fail ola bilər. Nəticə: data inconsistency.

**Həll yolları:**
- **Change Data Capture (CDC)** — DB-nin row-level dəyişikliklərini oxuyub consumer-lərə stream etmək
- **Outbox Pattern** — domain data və event-i eyni DB transaction-da yazmaq, sonra ayrı relay prosesi ilə broker-ə ötürmək

Bu iki texnika reliability və eventual consistency-ni təmin edir.


## Niyə Vacibdir

'DB-ni yenilə və Kafka-ya event göndər' — bunları bir transaction-da etmək mümkün deyilsə outbox pattern həll yoludur. CDC (Debezium) DB log-u oxuyaraq event stream yaradır; data pipeline-ların əsasıdır. Microservice event-driven inteqrasiya üçün kritik pattern-dir.

## Dual-Write Problem

```php
// ❌ YANLIŞ — atomik deyil
DB::transaction(function () use ($order) {
    Order::create($order);         // 1. DB-yə yazılır
    Kafka::publish('order.created', $order); // 2. Kafka-ya gedir
});
```

Ssenarilər:
- DB commit oldu, Kafka fail oldu → event itdi, consumer-lər xəbərsiz
- Kafka publish oldu, DB rollback oldu → event var amma order yoxdur
- Network timeout — hansı tərəfin uğurlu olduğu bilinmir

**DB transaction Kafka-nı əhatə etmir.** İki fərqli sistem arasında 2PC (two-phase commit) çox bahalıdır və production-da qaçırılır.

## Əsas Anlayışlar

### 1. Change Data Capture (CDC)

CDC — database-dəki INSERT/UPDATE/DELETE əməliyyatlarını real-time izləyib event stream kimi yaymaq prosesidir. Application kodu dəyişmir — DB log-ları oxunur.

**CDC axını:**
```
App → MySQL → binlog → Debezium → Kafka → Consumers
                  ↑
           CDC reads here
```

### 2. CDC İmplementasiya Tipləri

**a) Query-based CDC (polling):**
- `updated_at` sütununa görə `SELECT * WHERE updated_at > ?` query
- **Artı:** sadə, hər DB-də işləyir
- **Mənfi:** DELETE-ləri görmür, DB-yə yüklənmə, soft-delete lazım, polling interval = latency

**b) Log-based CDC:**
- MySQL **binlog**, PostgreSQL **WAL (Write-Ahead Log)**, MongoDB **oplog**
- **Artı:** bütün dəyişiklikləri (o cümlədən DELETE) görür, aşağı overhead, ardıcıl
- **Mənfi:** DB-specific konfiqurasiya, replication privilege tələb edir

**c) Trigger-based CDC:**
- Hər cədvəldə AFTER INSERT/UPDATE/DELETE trigger
- Ayrı `audit_log` cədvəlinə yazır
- **Artı:** portativ, DB versionundan asılı deyil
- **Mənfi:** hər write-a overhead, trigger maintenance ağır

Production-da **log-based** ən çox istifadə olunan variantdır.

### 3. CDC Tool-ları

| Tool | Əsas | Qeyd |
|------|------|------|
| **Debezium** | Log-based | Kafka Connect üzərində, MySQL/PG/Mongo/SQL Server |
| **Maxwell** | MySQL binlog | JSON event-lər, yüngül |
| **AWS DMS** | Managed | AWS ekosistemi üçün |
| **Google Datastream** | GCP | BigQuery-ə axın |
| **Airbyte** | ELT | Batch + CDC |

### 4. CDC Use Case-ləri

- **Cache invalidation** — DB dəyişəndə Redis-i yeniləmək
- **Search index sync** — MySQL → Elasticsearch reindex
- **Data warehouse** — OLTP → Snowflake/BigQuery analytics
- **Microservice decoupling** — monolith DB-dən event stream
- **Audit log** — compliance üçün bütün dəyişiklik tarixi
- **Event sourcing bridge** — legacy CRUD sistemindən event store-a keçid

### 5. Transactional Outbox Pattern

Outbox — **application-level reliability** pattern-idir. Domain data və "event" eyni DB transaction-da yazılır. Sonra ayrı relay prosesi outbox cədvəlini oxuyub broker-ə publish edir.

```
┌──────────────────────────────┐
│  DB Transaction              │
│  ┌────────┐  ┌────────────┐  │
│  │ orders │  │ outbox     │  │
│  └────────┘  └────────────┘  │
└──────────┬───────────────────┘
           │
           ▼
     Relay (worker)
           │
           ▼
         Kafka
```

**Niyə işləyir?** Hər iki INSERT eyni ACID transaction daxilindədir. Ya hər ikisi commit olur, ya da hər ikisi rollback — dual-write problem yoxa çıxır.

### 6. Outbox Table Schema

```sql
CREATE TABLE outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id CHAR(36) UNIQUE NOT NULL,      -- UUID, idempotency üçün
    aggregate_type VARCHAR(64) NOT NULL,     -- "Order", "User"
    aggregate_id VARCHAR(64) NOT NULL,       -- partition key
    event_type VARCHAR(64) NOT NULL,         -- "OrderCreated"
    payload JSON NOT NULL,
    headers JSON NULL,
    created_at TIMESTAMP NOT NULL,
    sent_at TIMESTAMP NULL,
    INDEX idx_unsent (sent_at, id)
);
```

### 7. Relay Implementasiyaları

**a) Polling worker** — hər N saniyə `SELECT ... WHERE sent_at IS NULL LIMIT 100`
**b) CDC on outbox** — Debezium outbox cədvəlini oxuyur (hybrid — ən güclü variant)
**c) PostgreSQL LISTEN/NOTIFY** — trigger notify göndərir, worker push alır

### 8. Outbox vs CDC vs Hybrid

- **Outbox tək** — app payload-u özü formalaşdırır, polling relay
- **CDC tək** — bütün cədvəllərdən row-level event-lər, schema leak riski
- **Outbox + CDC (hybrid)** — app outbox-a yazır, Debezium outbox-u oxuyur → Kafka. Hər iki dünyanın üstünlüyü: dəqiq domain event-lər + aşağı latency.

### 9. Ordering və Idempotency

- **Ordering:** Kafka-da `aggregate_id` partition key olur — eyni order-in bütün event-ləri eyni partition-da, ardıcıl
- **Idempotency:** consumer `event_id`-yə görə dedup edir (processed_events cədvəli)
- **At-least-once** delivery — duplicate qaçılmazdır, consumer idempotent olmalıdır

### 10. Inbox Pattern

Outbox-un əksi — consumer tərəfdə. Event gələndə əvvəlcə `inbox_events` cədvəlinə `event_id` ilə yazılır (UNIQUE constraint). Duplicate gələrsə constraint violation — atılır. Business logic yalnız yeni event üçün işə düşür.

```sql
CREATE TABLE inbox_events (
    event_id CHAR(36) PRIMARY KEY,
    processed_at TIMESTAMP NOT NULL
);
```

## Nümunələr

### Outbox Migration

```php
Schema::create('outbox_events', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->uuid('event_id')->unique();
    $table->string('aggregate_type', 64);
    $table->string('aggregate_id', 64);
    $table->string('event_type', 64);
    $table->json('payload');
    $table->json('headers')->nullable();
    $table->timestamp('created_at');
    $table->timestamp('sent_at')->nullable();
    $table->index(['sent_at', 'id'], 'idx_unsent');
    $table->index(['aggregate_type', 'aggregate_id']);
});
```

### Service — Domain + Outbox in One Transaction

```php
class OrderService
{
    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // 1. Domain write
            $order = Order::create($data);

            // 2. Outbox write — eyni transaction!
            OutboxEvent::create([
                'event_id'       => (string) Str::uuid(),
                'aggregate_type' => 'Order',
                'aggregate_id'   => (string) $order->id,
                'event_type'     => 'OrderCreated',
                'payload'        => json_encode([
                    'order_id'   => $order->id,
                    'user_id'    => $order->user_id,
                    'total'      => $order->total,
                    'created_at' => $order->created_at->toIso8601String(),
                ]),
                'created_at' => now(),
            ]);

            return $order;
        });
    }
}
```

**Vacib:** Outbox write-ı `DB::transaction()` daxilindədir. Kafka publish bu anda EDİLMİR.

### Relay Worker — Laravel Job

```php
class OutboxRelayJob implements ShouldQueue
{
    public function handle(KafkaProducer $producer): void
    {
        // Batch oxu, lock et ki başqa worker eyni row-u almasın
        $events = DB::transaction(function () {
            $rows = OutboxEvent::whereNull('sent_at')
                ->orderBy('id')
                ->limit(200)
                ->lockForUpdate()
                ->get();

            return $rows;
        });

        foreach ($events as $event) {
            try {
                $producer->publish(
                    topic: 'orders',
                    key: $event->aggregate_id,   // partition key
                    value: $event->payload,
                    headers: [
                        'event_id'   => $event->event_id,
                        'event_type' => $event->event_type,
                    ],
                );

                $event->update(['sent_at' => now()]);
            } catch (Throwable $e) {
                Log::error('Outbox publish failed', [
                    'event_id' => $event->event_id,
                    'error'    => $e->getMessage(),
                ]);
                // sent_at null qalır — növbəti run retry edər
                break; // ordering-i qoru
            }
        }
    }
}
```

### Scheduler — Hər 5 saniyədə

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->job(new OutboxRelayJob())
        ->everyFiveSeconds()
        ->withoutOverlapping(60);
}
```

### Eski Event-lərin Təmizlənməsi

```php
// sent_at > 7 gün əvvəl olanları sil
OutboxEvent::whereNotNull('sent_at')
    ->where('sent_at', '<', now()->subDays(7))
    ->delete();
```

### Consumer Side — Inbox Dedup

```php
class OrderCreatedHandler
{
    public function handle(array $message): void
    {
        $eventId = $message['headers']['event_id'];

        DB::transaction(function () use ($eventId, $message) {
            // Unique constraint ilə dedup
            try {
                InboxEvent::create([
                    'event_id'     => $eventId,
                    'processed_at' => now(),
                ]);
            } catch (QueryException $e) {
                if ($e->errorInfo[1] === 1062) { // duplicate
                    Log::info('Event already processed', ['id' => $eventId]);
                    return;
                }
                throw $e;
            }

            // Business logic yalnız yeni event üçün
            $this->processOrder($message['payload']);
        });
    }
}
```

## Debezium PostgreSQL Connector Setup

```json
{
  "name": "outbox-connector",
  "config": {
    "connector.class": "io.debezium.connector.postgresql.PostgresConnector",
    "database.hostname": "postgres",
    "database.port": "5432",
    "database.user": "debezium",
    "database.password": "secret",
    "database.dbname": "shop",
    "plugin.name": "pgoutput",
    "table.include.list": "public.outbox_events",
    "transforms": "outbox",
    "transforms.outbox.type": "io.debezium.transforms.outbox.EventRouter",
    "transforms.outbox.route.by.field": "aggregate_type",
    "transforms.outbox.table.field.event.id": "event_id",
    "transforms.outbox.table.field.event.key": "aggregate_id",
    "transforms.outbox.table.field.event.payload": "payload"
  }
}
```

Debezium `outbox_events`-i izləyir, hər INSERT üçün Kafka topic-inə (`aggregate_type`-ə görə routing — "Order" → `orders` topic) mesaj göndərir. Bundan sonra sən `sent_at` sütununu saxlamağa da ehtiyac yoxdur — WAL offset Debezium tərəfindən idarə olunur.

## Schema Evolution

Event schema zamanla dəyişəcək. Vacib qaydalar:

- **Event version** sahəsi əlavə et: `"version": 2`
- **Schema Registry** istifadə et (Confluent Schema Registry + Avro/Protobuf)
- **Backward compatible** dəyişikliklər et — yeni sahə nullable, köhnə sahə silmə
- **Parallel run** — yeni versiya consumer-i köhnə ilə paralel işlə, sonra köhnəni söndür
- **Dead letter topic** — parse edilə bilməyən event-lər üçün

```json
{
  "event_id": "uuid",
  "event_type": "OrderCreated",
  "version": 2,
  "occurred_at": "2026-04-18T10:00:00Z",
  "data": { "order_id": 42, "total": 99.90, "currency": "AZN" }
}
```

## Real-World Nümunələr

- **Shopify** — MySQL CDC ilə search index (Elasticsearch) sync
- **Netflix** — DBLog (log-based CDC) ilə microservices arası data
- **Uber** — Schemaless + CDC → Kafka → analytics
- **LinkedIn** — Databus (ilk log-based CDC sistemlərindən)
- **Stripe** — Outbox pattern ödəniş event-ləri üçün

## Praktik Tapşırıqlar

**1. Dual-write problem nədir və necə həll olunur?**
DB və message broker arasında atomik yazı mümkün olmadığından biri uğurlu, digəri fail ola bilər. Həll: Outbox pattern (eyni DB transaction-da event yazmaq) və ya CDC (DB log-dan oxu).

**2. Log-based CDC niyə query-based-dən yaxşıdır?**
Log-based DELETE-ləri görür, DB-yə əlavə yük vermir, aşağı latency verir və ardıcıl ordering saxlayır. Query-based polling interval qədər gecikir və `updated_at`-siz cədvəllərdə işləmir.

**3. Outbox pattern ilə CDC fərqi nədir?**
Outbox — app-level, domain event-ləri üçün. CDC — DB-level, bütün dəyişiklikləri yayımlayır. Best practice: Outbox cədvəlini CDC ilə oxumaq (hybrid) — domain event semantikası + log-based reliability.

**4. Outbox-da event ordering necə təmin olunur?**
Relay `ORDER BY id` ilə oxuyur (auto-increment global ordering verir). Kafka-ya publish edərkən `aggregate_id` partition key olur — eyni aggregate-ın event-ləri eyni partition-da ardıcıl qalır.

**5. At-least-once delivery-də duplicate necə tutulur?**
Consumer tərəfdə Inbox pattern: `event_id` UNIQUE constraint ilə `inbox_events` cədvəlinə yazılır. Duplicate gələrsə constraint violation → skip. Business logic idempotent olmalıdır.

**6. Debezium-un EventRouter SMT nə edir?**
Single Message Transform — outbox cədvəlindəki row-u Kafka topic-ə routing edir. `aggregate_type`-ə görə topic, `aggregate_id`-yə görə key, `payload`-u value seçir. Outbox schema-nı Kafka event formatına çevirir.

**7. Outbox cədvəli çox böyüyürsə nə etmək lazımdır?**
Periodic cleanup job — `sent_at` köhnə olanları sil (7-30 gün arxivlə). Partition by `created_at` MONTH — köhnə partition-ları drop et. Əsas write performance-ə təsir etməsin deyə `idx_unsent` kompozit index saxla.

**8. Schema evolution zamanı consumer-lər necə qorunur?**
Event-ə `version` sahəsi əlavə et, Schema Registry ilə backward-compatible dəyişiklik et (yalnız nullable sahə əlavə, sahə silmək qadağan). Köhnə və yeni consumer paralel işləsin, traffic tədricən yeni versiyaya keçsin. Parse error-lar üçün DLQ (dead letter queue) saxla.

## Praktik Baxış

1. **Outbox-u eyni transaction-da yaz** — ayrı DB connection istifadə etmə, eyni transaction olmalıdır
2. **Idempotent consumer** — hər event ən azı bir dəfə çatacaq, handler duplicate-ə hazır olsun
3. **event_id UUID** — client-generated, partition-dan asılı olmayan unique
4. **aggregate_id partition key** — ordering bir aggregate daxilində qorunsun
5. **lockForUpdate relay worker-də** — paralel worker-lər eyni row-u almasın
6. **Cleanup job** — köhnə `sent_at` qeydlərini arxivlə/sil
7. **Monitoring** — `WHERE sent_at IS NULL COUNT` metric, lag alert
8. **Schema Registry** — Avro/Protobuf ilə schema evolution
9. **Dead Letter Queue** — parse edə bilmədiyin event-lər itməsin
10. **CDC üçün read replica** — binlog replication traffic master-dən gəlsin, production-a təsir etməsin
11. **Outbox + CDC hybrid** — domain event-lər üçün ən güclü variant
12. **Kafka retention** — topic-lər ən azı 7 gün saxlansın ki consumer recovery edə bilsin


## Əlaqəli Mövzular

- [Distributed Transactions](45-distributed-transactions-saga.md) — saga event reliable delivery
- [Event-Driven Architecture](11-event-driven-architecture.md) — outbox pattern konteksti
- [Stream Processing](54-stream-processing.md) — CDC stream-ini işləmək
- [CDC Streaming](93-cdc-streaming-architectures.md) — Debezium dərin arxitekturası
- [Message Queues](05-message-queues.md) — outbox-un çatdırdığı yer
