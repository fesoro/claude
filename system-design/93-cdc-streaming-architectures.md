# CDC Streaming Architectures (Architect)

Change Data Capture (CDC) — DB-dən **real-time change stream**-lər çıxarmaq üçün platformadır. File 46 CDC + outbox pattern-inə toxunur; bu fayl full streaming pipeline arxitekturasını (Debezium → Kafka → sinks), schema evolution-u, delivery semantics və production pitfall-larını dərinliklə araşdırır.


## Niyə Vacibdir

Database log-un event stream-ə çevrilməsi real-time data pipeline-ların əsasıdır. Schema registry, DLQ, exactly-once semantics, slot retention — production Debezium deployment-inin qarşısına çıxan real problemlərdir. Mikroservis inteqrasiyasının ən reliable yolu CDC-dir.

## CDC növləri — müqayisə

### 1. Query-based (polling)

```sql
SELECT * FROM orders WHERE updated_at > :last_seen ORDER BY updated_at;
```

**Tətbiq:** Airbyte incremental, Kafka Connect JDBC source.

**Problem:**
- **Delete-lər itir** (row yox olunca görünməz)
- **Polling interval** → latency (1s–1h)
- **DB load** — hər polling sorğu
- **Clock skew** — `updated_at` monotonic olmaya bilər

**Yaxşıdır:** basit, read-only replika, no special DB privilege lazım.

### 2. Trigger-based

```sql
CREATE TRIGGER audit_orders
AFTER INSERT OR UPDATE OR DELETE ON orders
FOR EACH ROW EXECUTE PROCEDURE log_to_audit();
```

**Tətbiq:** Postgres audit triggers, Oracle GoldenGate (historical).

**Problem:**
- **Write amplification** — hər yazı 2x (original + trigger)
- **Schema change** — trigger də yenilənməlidir
- **Transaction lock** — trigger içində slow op → transaction slow
- **Upgrade risk** — DB schema migration zamanı trigger-lər qırılır

**Yaxşıdır:** kiçik table-lar, auditing legacy sistem.

### 3. Log-based (preferred)

DB-nin yazı jurnalını (WAL / binlog / redo log) oxu — no app change, no trigger.

**Tətbiq:** Debezium (Kafka Connect), AWS DMS, Oracle GoldenGate, Maxwell.

**Advantage:**
- **Zero DB load** (log zaten yazılır replication üçün)
- **Complete** — insert/update/delete + schema DDL
- **Ordered** — exactly in transaction commit order
- **Low latency** — sub-second

Ən yaygın modern yanaşma.

## Debezium architecture

```
┌──────────────┐    ┌───────────────────┐    ┌─────────────┐
│ Postgres/    │────▶ Debezium Connector│────▶   Kafka     │
│ MySQL/       │WAL │ (Kafka Connect)   │    │  (topic per │
│ SQL Server/  │    │                   │    │   table)    │
│ Mongo/Oracle │    └───────────────────┘    └─────────────┘
└──────────────┘                                    │
                                                    ▼
                                     ┌──────────────────────────┐
                                     │ Sink Connectors          │
                                     │  - Elasticsearch         │
                                     │  - Snowflake / BigQuery  │
                                     │  - S3 (Iceberg)          │
                                     │  - Postgres (replica)    │
                                     │  - Cache (Redis)         │
                                     └──────────────────────────┘
```

### Postgres logical replication

Debezium Postgres connector logical decoding istifadə edir:

```sql
-- DB config
ALTER SYSTEM SET wal_level = 'logical';
SELECT pg_create_logical_replication_slot('debezium_slot', 'pgoutput');

-- Publication (hansı table-lar?)
CREATE PUBLICATION my_publication FOR TABLE orders, users;
```

Debezium slot-u oxuyur, WAL record-larını `INSERT/UPDATE/DELETE` event-lərinə çevirir.

### Event envelope

```json
{
  "schema": { ... Avro schema ... },
  "payload": {
    "before": { "id": 42, "status": "pending" },
    "after":  { "id": 42, "status": "shipped" },
    "source": {
      "version": "2.5.0",
      "connector": "postgresql",
      "name": "orders-server",
      "ts_ms": 1704067200000,
      "snapshot": false,
      "db": "prod",
      "schema": "public",
      "table": "orders",
      "lsn": 1234567890
    },
    "op": "u",     // c=create, u=update, d=delete, r=read(snapshot)
    "ts_ms": 1704067200100
  }
}
```

### Snapshot phase

İlk connector-ın işə salınması zamanı:

```
1. Read CDC slot position (current LSN)
2. Snapshot: SELECT * FROM each_table (consistent read)
3. Emit snapshot events (op=r)
4. Switch to streaming from saved LSN
```

Böyük table-lar üçün **incremental snapshot** (signal table ilə).

## Kafka topic strategy

Standart pattern — `<server>.<schema>.<table>`:

```
orders-server.public.orders
orders-server.public.order_items
orders-server.public.users
```

### Partitioning

```yaml
transforms: reroute
transforms.reroute.topic.regex: "orders-server.public.(.+)"
transforms.reroute.topic.replacement: "cdc.$1"

# Partition key — primary key
transforms.reroute.key.field.regex: "(.*)"
transforms.reroute.key.field.replacement: "$1"
```

**Partition by PK** → eyni row üçün bütün dəyişikliklər eyni partition-da → **ordering guarantee**.

## Schema Registry

Avro / Protobuf schema — Confluent / Apicurio registry-də saxlanır.

```
Producer (Debezium):
  1. Serialize event with schema
  2. Register schema → get schema_id
  3. Message = [schema_id (4 bytes), avro-binary-payload]

Consumer:
  1. Read schema_id from message
  2. Fetch schema from registry (cached)
  3. Deserialize
```

### Compatibility modes

```
BACKWARD   — new schema can read old data (add optional fields)
FORWARD    — old schema can read new data (remove fields)
FULL       — both
NONE       — anything goes (dangerous)
```

Producer-consumer deploy qaydası:
- BACKWARD: deploy consumers first, producers second
- FORWARD: deploy producers first

### Schema evolution

**OK (backward compatible):**
- Add optional field with default
- Remove optional field
- Rename via alias

**BREAK:**
- Add required field without default
- Rename without alias
- Change type (int → string)

## Sink connectors

### Elasticsearch

```json
{
  "connector.class": "io.confluent.connect.elasticsearch.ElasticsearchSinkConnector",
  "topics": "cdc.orders",
  "connection.url": "http://elasticsearch:9200",
  "type.name": "_doc",
  "key.ignore": false,
  "schema.ignore": true,
  "behavior.on.null.values": "DELETE",
  "write.method": "UPSERT"
}
```

**CDC → ES flow:**
- `op=c` or `u` → ES index `doc_id = PK`
- `op=d` → ES delete `doc_id`
- Uses PK as doc ID → idempotent upsert

**Consistency trick:** `after` field-i flatten et (single transform):

```yaml
transforms: unwrap
transforms.unwrap.type: io.debezium.transforms.ExtractNewRecordState
transforms.unwrap.drop.tombstones: false
transforms.unwrap.delete.handling.mode: rewrite  # adds __deleted field
```

### S3 / Iceberg

```yaml
connector.class: io.confluent.connect.s3.S3SinkConnector
s3.bucket.name: data-lake
s3.part.size: 268435456   # 256 MB
format.class: io.confluent.connect.s3.format.parquet.ParquetFormat
flush.size: 100000
rotate.interval.ms: 300000
partitioner.class: io.confluent.connect.storage.partitioner.TimeBasedPartitioner
path.format: "'year'=YYYY/'month'=MM/'day'=dd/'hour'=HH"
```

Sonuç — Athena/Trino/Spark-dan query olunan data lake tables.

### Snowflake

```yaml
connector.class: com.snowflake.kafka.connector.SnowflakeSinkConnector
snowflake.topic2table.map: "cdc.orders:raw_orders"
snowflake.database.name: ANALYTICS
snowflake.schema.name: CDC_RAW
```

CDC stream → raw staging → dbt merge → dimensional model.

## Delivery semantics

### At-least-once (default)

```
Producer (Debezium):
  1. Write event to Kafka
  2. Await ack
  3. Commit LSN to DB (confirmation)
  4. Crash between 2 and 3 → replay on restart → duplicate event
```

**Consumer must be idempotent.**

### Exactly-once

Kafka 0.11+ — transactional producer + idempotent consumer:

```yaml
# Debezium config
producer.override.enable.idempotence: true
producer.override.acks: all
producer.override.transactional.id: debezium-postgres-1
```

Sink side — Kafka transactional read:
```yaml
consumer.isolation.level: read_committed
```

**Catch:** exactly-once yalnız **Kafka boyunca**. External system-ə yazı (ES, DB) hələ də at-least-once — sink idempotency yaratmaq lazımdır (PK-based upsert).

### End-to-end idempotency (PHP consumer örnəyi)

```php
class OrderEventConsumer
{
    public function handle(array $event): void
    {
        $eventId = $event['source']['lsn'] . ':' . $event['source']['ts_ms'];
        
        // Deduplication check
        if (ProcessedEvent::where('event_id', $eventId)->exists()) {
            return;  // already processed
        }

        DB::transaction(function () use ($event, $eventId) {
            $this->applyEvent($event);
            ProcessedEvent::create(['event_id' => $eventId, 'processed_at' => now()]);
        });
    }
}
```

## Dead Letter Queue (DLQ)

### Niyə lazımdır?

- Corrupt event (schema mismatch)
- Sink temporarily down (longer than retry)
- Business logic error (validation fail)

Bunları həzm edə bilməyən sink **infinite retry** edərsə → entire stream halt.

### Debezium / Kafka Connect DLQ

```yaml
errors.tolerance: all
errors.deadletterqueue.topic.name: cdc.dlq.orders
errors.deadletterqueue.topic.replication.factor: 3
errors.deadletterqueue.context.headers.enable: true
errors.log.enable: true
errors.log.include.messages: true
```

DLQ topic-ində:
- `__connect.errors.topic` — original topic
- `__connect.errors.partition`
- `__connect.errors.offset`
- `__connect.errors.exception.class.name`
- `__connect.errors.exception.message`

### DLQ re-processing

Ayrı job:
1. DLQ-dan message oxu
2. Error səbəbini araşdır
3. Schema fix / bugfix
4. DLQ message-ı original topic-ə `produce` et (replay)
5. Consume et

## Outbox pattern — niyə CDC ilə kombinasiya?

File 46-da detallı; qısaca:

```
BEGIN TX
  INSERT INTO orders (...) VALUES (...);
  INSERT INTO outbox (aggregate, event_type, payload) VALUES (...);
COMMIT

Debezium watches outbox table → emits events
```

Niyə direct table CDC yerinə outbox?
- **Event schema control** — table schema-sı domain event ilə eyni olmaya bilər
- **Event types** — birdən çox event növü (OrderPlaced, PaymentCaptured) bir table-dan
- **Data hiding** — internal column-ları emit etmə
- **Idempotency key** — outbox.id → event_id

## Reactor pattern

Event consumer-lar **non-blocking** olmalıdır. Reactor pattern:

```
[Kafka Consumer] → [Ring Buffer] → [Worker pool]
                                      ↓
                            DB / ES / HTTP sink
```

### Backpressure

Consumer çox sürətli → DB-ni boğur. Solutions:
- Consumer lag monitor + alert
- `max.poll.records` limit
- Work queue bounded; pause Kafka consumer when full

## Postgres-specific pitfall — slot retention

**Ən vacib məsələ** Postgres CDC-də:

```
Debezium creates replication slot
→ Postgres keeps ALL WAL from slot's position
→ If Debezium down for a day, WAL grows GB per hour
→ Disk fills → DB CRASH
```

### Monitor

```sql
SELECT slot_name,
       pg_size_pretty(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)) AS lag
FROM pg_replication_slots;
```

### Mitigation

1. **Alert on slot lag > 10 GB** — Debezium down?
2. **max_slot_wal_keep_size** (PG 13+) — limit WAL retention per slot (Debezium breaks, but DB survives)
3. **Drop orphan slots** immediately — test slot removed after test env decommission
4. **Multiple slots** = multiple WAL copies → size multiplier

## MySQL binlog specifics

```
binlog_format = ROW
binlog_row_image = FULL
expire_logs_days = 7
```

Debezium MySQL:
- Reads binlog position `(file, offset)` instead of LSN
- Can read from **replica** (not master) — reduces production load
- GTID support for failover

## Schema DDL capture

```sql
ALTER TABLE orders ADD COLUMN gift_code TEXT;
```

Debezium emits **schema change event** to `db-history-topic`:
```json
{
  "ts_ms": 1704067200000,
  "databaseName": "prod",
  "ddl": "ALTER TABLE orders ADD COLUMN gift_code TEXT",
  "tableChanges": [ ... ]
}
```

Schema registry auto-evolves (new Avro version).

## Reactive pipeline nümunəsi

Order event → search index + cache + analytics:

```
Postgres orders table
    ↓ (logical replication)
Debezium → Kafka "cdc.orders"
                    ↓
         ┌──────────┼──────────┬──────────────┐
         ↓          ↓          ↓              ↓
   ES connector  Redis       Snowflake     Kafka Streams
   (upsert)     invalidate   (analytics)   (aggregation)
                                                 ↓
                                         "orders.summary" topic
                                                 ↓
                                         Materialized view → BI
```

## Monitoring

Key metrics:
- **Source lag** — Debezium processed LSN vs current WAL
- **Sink lag** — Kafka consumer lag per partition
- **DLQ rate** — messages/min going to DLQ
- **Throughput** — events/sec per topic
- **Schema errors** — registry compat failures

Alert:
- Source lag > 1 min
- DLQ rate > 0
- Consumer lag > 10k messages

## Real-world

### Shopify
- Postgres → Debezium → Kafka → Elasticsearch (product search)
- 10B+ events/day
- Outbox pattern for domain events

### Netflix
- Keystone pipeline — Flink + Kafka + S3 (Iceberg)
- CDC for membership data
- Bespoke DBLog for MySQL

### LinkedIn
- Databus (original inspiration for CDC tools)
- Kafka + Brooklin
- Reverses: CDC from Kafka → materialized Pinot views

## Anti-patterns

1. **Trigger-based CDC at scale** — write amplification
2. **Polling CDC with wide windows** — miss data + stale
3. **No schema registry** — consumer breakage on schema change
4. **Single-partition topic** — ordering OK but throughput capped
5. **Infinite retry without DLQ** — stream halt
6. **Sink without idempotency** — duplicate apply (double charge?)
7. **Dropping orphan replication slots not monitored** — DB crash
8. **Debezium and transactional outbox both in same flow** — decide one, not both

## Security

- **Encryption in transit** — Kafka SSL/TLS
- **ACL per topic** — minimum privilege
- **PII masking** in transforms:
  ```yaml
  transforms: maskPII
  transforms.maskPII.type: org.apache.kafka.connect.transforms.MaskField$Value
  transforms.maskPII.fields: email,ssn,phone
  ```
- **Registry auth** — RBAC

## Back-of-envelope

**E-commerce platform CDC:**
- 500k orders/day, 5 events each → 2.5M events/day = ~30/sec (avg), 500/sec peak
- Event size ~1.5 KB Avro → 45 MB/day compressed
- Kafka retention 7 days → 315 MB per topic
- Multiple sinks, no amplification at Kafka

**Large social network:**
- 1B events/day, 2 KB/event = 2 TB/day
- Kafka cluster ~20 brokers (3x replication)
- Snapshot time for new connector on 10 TB table → 4-12 hours

## Ətraflı Qeydlər

CDC streaming arxitektura modern data platform-larının damarı. Log-based CDC (Debezium) eldə edilən production-sensitive solution-dur. Ən kritik risk — Postgres replication slot monitoring (unattended slot → DB ölümü). Kafka Schema Registry schema evolution-u emniyyətli edir. DLQ + idempotent sink-lər retry-leri qorxmadan icazə verir. Exactly-once yalnız Kafka boyunca mümkündür — sink-ə end-to-end exactly-once üçün idempotent upsert lazımdır. Outbox pattern + CDC birlikdə domain event-ləri DB consistency-si ilə birlikdə emit etmək üçün ən etibarlı tandem-dir.


## Əlaqəli Mövzular

- [CDC & Outbox](46-cdc-outbox-pattern.md) — CDC-nin application pattern ilə kombinasiyası
- [Stream Processing](54-stream-processing.md) — CDC stream-i işləmək
- [Pub/Sub](81-pubsub-system-design.md) — CDC event delivery platform
- [Elasticsearch Internals](90-elasticsearch-internals.md) — DB-dən ES-ə CDC sync
- [Event-Driven Architecture](11-event-driven-architecture.md) — CDC event-driven pattern
