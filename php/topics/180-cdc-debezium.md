# Change Data Capture (CDC) & Debezium

## Mündəricat
1. [CDC nədir?](#cdc-nədir)
2. [CDC yanaşmaları](#cdc-yanaşmaları)
3. [Database replication log-ları](#database-replication-log-ları)
4. [Debezium arxitekturası](#debezium-arxitekturası)
5. [Kafka Connect inteqrasiya](#kafka-connect-inteqrasiya)
6. [Outbox + CDC pattern](#outbox--cdc-pattern)
7. [Snapshot + Streaming](#snapshot--streaming)
8. [Use cases](#use-cases)
9. [PHP consumer](#php-consumer)
10. [Pitfalls & gotchas](#pitfalls--gotchas)
11. [İntervyu Sualları](#intervyu-sualları)

---

## CDC nədir?

```
CDC (Change Data Capture) — verilənlər bazasındakı dəyişiklikləri 
REAL-TIME oxuyub digər sistemlərə yönəltmək.

Ənənəvi yanaşma (pull):
  Gecə cron: export MySQL → import DWH
  → 24 saat gecikmə

CDC (push, streaming):
  Hər INSERT/UPDATE/DELETE → event → digər sistemlərə
  → millisaniyə gecikmə

Niyə CDC?
  1. Microservice ayrılığı — "ayrı DB, ayrı event, amma data sync"
  2. Search index (Elasticsearch) yenilənməsi
  3. Cache invalidation
  4. Data warehouse / analytics (OLAP)
  5. Audit log / compliance
  6. Replication cross-region
  7. Event-driven architecture (legacy DB-dən event-lər)

Nümunə:
  MySQL orders cədvəlində INSERT → 
    ↓ CDC
    → Kafka topic "orders"
    ↓
    ├─ Elasticsearch indexer
    ├─ Cache invalidator (Redis)
    ├─ BigQuery DWH
    └─ Notification service
```

---

## CDC yanaşmaları

```
1. QUERY-BASED (polling)
   SELECT * FROM table WHERE updated_at > last_check
   
   ✓ Sadə, hər DB-də işləyir
   ✗ DELETE tuta bilməz (row yoxdur)
   ✗ Gecikmə polling intervalına bağlı
   ✗ Performance — hər poll bütün cədvəli scan
   ✗ "In-flight" dəyişikliklər missed ola bilər

2. TRIGGER-BASED
   DB trigger hər dəyişikliyi shadow table-a yazır
   
   ✓ Real-time
   ✓ DELETE tutur
   ✗ Performance cost (transaction-a trigger əlavə olunur)
   ✗ DB schema dəyişmə (trigger install)
   ✗ Maintain etmək çətin
   
3. LOG-BASED (ən yaxşı)
   DB-nin replication log-undan oxu (MySQL binlog, PG WAL)
   
   ✓ Real-time, asynchronous
   ✓ Performance impact MİNİMAL (DB log onsuz da yazır)
   ✓ Hamısını tutur (INSERT, UPDATE, DELETE, DDL)
   ✓ Exactly-once delivery mümkündür (offset tracking)
   ✗ DB-specific (MySQL/Postgres/Oracle fərqli format)
   ✗ Privileges lazım (REPLICATION CLIENT / REPLICATION SLAVE)

Log-based CDC ən populyar yanaşmadır.
Debezium, Maxwell, Oracle GoldenGate — hamısı log-based.
```

---

## Database replication log-ları

```
MySQL binlog (Binary Log):
  Bütün DML/DDL operation-ları binary formatda yazılır
  Replication-da slave bu log-u oxuyur
  Format: STATEMENT, ROW, MIXED
  CDC üçün: ROW format (full before/after image)
  
  my.cnf:
    log-bin = mysql-bin
    binlog_format = ROW
    binlog_row_image = FULL
    server-id = 1
  
  Position: (filename, offset) — hər event unique coordinate-ə sahibdir
  GTID (Global Transaction ID) — global unique identifier

PostgreSQL WAL (Write-Ahead Log):
  Hər transaction commit-dən əvvəl WAL-a yazılır
  Logical replication üçün: wal_level = logical
  Publication/subscription model (PG 10+)
  
  postgresql.conf:
    wal_level = logical
    max_wal_senders = 10
    max_replication_slots = 10
  
  Replication slot — consumer state tracking (offset)

MongoDB oplog:
  local database-də oplog.rs collection
  Replica set sizə lazımdır (standalone node-da yoxdur)
  
SQL Server CDC:
  Native CDC feature (cdc.xxx_CT tables)
  Transaction log-dan oxu
```

---

## Debezium arxitekturası

```
Debezium — open-source log-based CDC platform.
Source: MySQL, PostgreSQL, MongoDB, Oracle, SQL Server, Cassandra, Db2
Sink: Kafka (əsas), Pulsar, Kinesis, Redis Streams

                  ┌──────────────────┐
                  │  Source DB       │
                  │  (MySQL binlog)  │
                  └────────┬─────────┘
                           │ replication protocol
                           ▼
                  ┌──────────────────┐
                  │  Debezium        │
                  │  Connector       │   ─── Kafka Connect worker
                  └────────┬─────────┘
                           │ produces events
                           ▼
                  ┌──────────────────┐
                  │  Kafka Topics    │
                  │  db.schema.table │
                  └────────┬─────────┘
                           │
                           ▼
                  ┌──────────────────┐
                  │  Consumers       │
                  │  (ES, cache,     │
                  │   warehouse, PHP)│
                  └──────────────────┘

Event structure (JSON):
  {
    "schema": {...},
    "payload": {
      "before": { "id": 1, "name": "old", ... },
      "after":  { "id": 1, "name": "new", ... },
      "op": "u",    // c=create, u=update, d=delete, r=read (snapshot)
      "ts_ms": 1699000000000,
      "source": {
        "db": "shop",
        "table": "orders",
        "file": "mysql-bin.000042",
        "pos": 1234,
        "server_id": 1,
      }
    }
  }
```

---

## Kafka Connect inteqrasiya

```json
// Debezium MySQL connector config
{
  "name": "shop-connector",
  "config": {
    "connector.class": "io.debezium.connector.mysql.MySqlConnector",
    "tasks.max": "1",
    
    "database.hostname": "mysql.internal",
    "database.port": "3306",
    "database.user": "debezium",
    "database.password": "dbz",
    "database.server.id": "184054",
    "database.include.list": "shop",
    "table.include.list": "shop.orders,shop.users,shop.products",
    
    "topic.prefix": "shop",
    
    "schema.history.internal.kafka.bootstrap.servers": "kafka:9092",
    "schema.history.internal.kafka.topic": "schema-changes.shop",
    
    "snapshot.mode": "initial",
    "include.schema.changes": "true",
    
    "transforms": "unwrap",
    "transforms.unwrap.type": "io.debezium.transforms.ExtractNewRecordState",
    "transforms.unwrap.drop.tombstones": "false",
    "transforms.unwrap.delete.handling.mode": "rewrite"
  }
}
```

```bash
# Deploy connector
curl -X POST http://kafka-connect:8083/connectors \
  -H "Content-Type: application/json" \
  -d @connector.json

# Status
curl http://kafka-connect:8083/connectors/shop-connector/status

# Topics yaranır:
#   shop.shop.orders
#   shop.shop.users
#   shop.shop.products
```

---

## Outbox + CDC pattern

```
Problem: Microservice hem DB-yə yazır, hem event göndərmək istəyir.
  Dual-write: DB commit olur, sonra broker çökür → event itir.

Outbox pattern:
  1. Service DB transaction-da İKİ cədvələ yazır:
     - business_table (order)
     - outbox_events (event)
  2. Transaction atomic — data + event bir birliklə commit.
  3. Debezium outbox_events-i oxuyur → Kafka-ya göndərir.
  4. Consumer Kafka-dan event alır.

Üstünlük:
  ✓ Exactly-once publish (DB transaction garantiyası)
  ✓ Broker çöksə də event itmir (DB-də saxlanır)
  ✓ Service broker-ə birbaşa yazmır (coupling yoxdur)
```

```sql
-- Outbox cədvəli
CREATE TABLE outbox_events (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    aggregate_id VARCHAR(255) NOT NULL,
    aggregate_type VARCHAR(255) NOT NULL,
    event_type  VARCHAR(255) NOT NULL,
    payload     JSON NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
);
```

```php
<?php
// Transaction-da outbox entry əlavə et
DB::transaction(function () use ($orderData) {
    $order = Order::create($orderData);
    
    // Outbox event
    DB::table('outbox_events')->insert([
        'aggregate_id' => $order->id,
        'aggregate_type' => 'Order',
        'event_type' => 'OrderCreated',
        'payload' => json_encode([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'total' => $order->total,
        ]),
    ]);
});

// Debezium outbox_events cədvəlini CDC ilə Kafka-ya göndərir.
// Debezium "outbox event router" plugin payload-u parse edir,
// düzgün topic-ə (OrderCreated, UserRegistered və s.) göndərir.
```

```json
// Debezium Outbox Event Router config
{
  "transforms": "outbox",
  "transforms.outbox.type": "io.debezium.transforms.outbox.EventRouter",
  "transforms.outbox.route.by.field": "aggregate_type",
  "transforms.outbox.route.topic.replacement": "${routedByValue}",
  "transforms.outbox.table.field.event.id": "id",
  "transforms.outbox.table.field.event.key": "aggregate_id",
  "transforms.outbox.table.field.event.type": "event_type",
  "transforms.outbox.table.field.event.payload": "payload"
}
```

---

## Snapshot + Streaming

```
Debezium başlayanda 2 faza:

PHASE 1 — Initial snapshot
  Bütün cədvəli oxu → topic-ə göndər (op: "r" read)
  Binlog position qeyd et.
  Snapshot yavaşdır (böyük cədvəl → saatlar çəkir).

PHASE 2 — Streaming (CDC)
  Snapshot bitəndən → binlog-dan oxu (incremental)
  Hər event → topic.

Snapshot modes:
  initial        Bütün data + stream (default)
  never          Yalnız stream (data artıq var, skip snapshot)
  initial_only   Yalnız snapshot, stream yox (bir dəfəlik sync)
  schema_only    Yalnız schema, data yox
  when_needed    Restart-da state itirsə, yeni snapshot

Incremental snapshot (Debezium 1.6+):
  Cədvəli chunks-la oxu, streaming davam edir
  Snapshot zamanı yeni dəyişiklikləri itirmir
```

---

## Use cases

```
1. Microservice sync (without shared DB)
   User Service (MySQL) → Kafka → Notification Service
   User "yarandı" event auto, coupling yox.

2. Search sync (Elasticsearch)
   Orders table → Kafka → ES indexer
   Real-time search index update.

3. Cache invalidation
   Products table → Kafka → Redis invalidator
   Product dəyişəndə cache key invalidate.

4. Data warehouse (OLAP)
   OLTP MySQL → Kafka → ClickHouse / BigQuery
   Gün aralığında analytics — real-time.

5. Audit log
   User-facing DB → Kafka → Audit DB
   Sensitive action-lar immutable log-a.

6. Event sourcing migration
   Existing legacy DB → Kafka events
   Event-driven arxitekturaya keçid.

7. Cross-region replication
   EU MySQL → Kafka MirrorMaker → US MySQL
   Multi-region sync with eventual consistency.

Real companies:
  Netflix    — DBLog (custom CDC to Kafka)
  Shopify    — Debezium for microservices
  Uber       — Schemaless + log-based CDC
  LinkedIn   — Brooklin (CDC framework)
```

---

## PHP consumer

```php
<?php
// PHP Kafka consumer with enqueue/rdkafka
composer require enqueue/rdkafka

use Enqueue\RdKafka\RdKafkaConnectionFactory;

$factory = new RdKafkaConnectionFactory([
    'global' => [
        'group.id' => 'php-cdc-consumer',
        'metadata.broker.list' => 'kafka:9092',
        'auto.offset.reset' => 'earliest',
    ],
]);

$context = $factory->createContext();
$topic = $context->createTopic('shop.shop.orders');
$consumer = $context->createConsumer($topic);

while (true) {
    $message = $consumer->receive(1000);  // 1s timeout
    if ($message === null) continue;
    
    $event = json_decode($message->getBody(), true);
    
    $op = $event['op'];           // c, u, d, r
    $after = $event['after'];
    $before = $event['before'];
    
    match ($op) {
        'c' => handleCreate($after),
        'u' => handleUpdate($before, $after),
        'd' => handleDelete($before),
        'r' => handleSnapshot($after),
    };
    
    $consumer->acknowledge($message);  // offset commit
}
```

---

## Pitfalls & gotchas

```
❌ Pitfall 1: Schema evolution
   DB schema dəyişəndə consumer köhnə format gözləyir
   Həll: Schema Registry (Confluent), Avro / Protobuf

❌ Pitfall 2: DELETE + tombstone
   Debezium DELETE sonra tombstone event göndərir (value=null)
   Consumer bunu handle etməlidir (kaydın silinməsi)

❌ Pitfall 3: Ordering
   Partition key yoxdursa → event sırası pozulur
   Həll: partition.key = aggregate_id (eyni order üçün bütün event-lər eyni partition)

❌ Pitfall 4: Large transaction
   10k row UPDATE bir transaction-da → 10k event birdən Kafka-ya
   Backpressure lazımdır (kafka producer flow control)

❌ Pitfall 5: Replication lag
   Debezium bir az gecikir (ms-sec)
   "Write-then-read" pattern işləmir (real-time oxuya bilmirsiniz)

❌ Pitfall 6: Connector crash
   Kafka Connect worker çökürsə — yenidən başlat, ANCAQ duplicate event riski
   Idempotent consumer dizayn et

❌ Pitfall 7: GDPR / hard delete
   "Right to be forgotten" — Kafka-da event immutable
   Həll: Tombstone + compacted topic retention OR encryption key delete

✓ Best practices
  - Schema Registry istifadə et (Avro)
  - Partition key düzgün seç (entity ID)
  - Snapshot mode düzgün (initial vs never)
  - Monitoring (connector lag, consumer lag)
  - Heartbeat events (consumer alive yoxlaması)
  - Outbox pattern dual-write üçün
```

---

## İntervyu Sualları

- CDC nədir və niyə query-based polling-dən üstündür?
- Log-based CDC necə işləyir? Trigger-based ilə fərqi nədir?
- MySQL binlog ROW format niyə CDC üçün vacibdir?
- Debezium niyə Kafka Connect üzərində qurulur?
- Outbox pattern hansı problemi həll edir?
- Snapshot phase niyə lazımdır? Mode-ları nədir?
- Debezium event-ində "op" field-inin dəyərləri nədir?
- Partition key seçimi event ordering-ə necə təsir edir?
- Tombstone event nədir, nə vaxt yaranır?
- CDC olan sistemdə GDPR "right to erasure" necə icra olunur?
- Replication lag CDC-ni necə təsir edir?
- Microservice-lər arası data sync üçün CDC vs Event Sourcing — hansı nə vaxt?
