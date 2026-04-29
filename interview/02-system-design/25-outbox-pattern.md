# Outbox Pattern (Lead ⭐⭐⭐⭐)

## İcmal
Outbox pattern, database-ə yazı ilə event publishing-i atomik şəkildə birləşdirən reliability patternidir. "DB-ə yazdım, amma Kafka-ya göndərə bilmədim" — klassik dual-write problemi. Outbox pattern bu problemi eyni database transaction içində həll edir: event-i DB-ə yaz, ayrı bir relay prosesi Kafka-ya publish edir. Bu mövzu event-driven arxitektura, microservices, CDC mövzularıyla birlikdə interview-da tez-tez çıxır.

## Niyə Vacibdir
Distributed sistemlərdə "exactly-once" semantics çox istənilir amma çox çətindir. Outbox pattern "at-least-once" delivery ilə "idempotent consumer" kombinasiyasında praktik həll verir. Fintech, e-commerce, hər event-driven sistem bu pattern olmadan ya event itirir (DB ok, Kafka fail), ya da double publish edir (Kafka ok, DB fail). Lead mühəndis bu pattern-ı yalnız bilməklə deyil — CDC ilə implementasiyasını, polling relay-in trade-off-larını, idempotency ilə birlikdə istifadəsini izah edə bilməlidir.

## Əsas Anlayışlar

### 1. Dual-Write Problemi
```
Naive approach (problematik):
  BEGIN transaction
    UPDATE orders SET status = 'paid'
  COMMIT

  kafka.produce("order.paid", order_id=123)  ← Bu fail olsa?

Scenariolar:

  DB ok, Kafka fail:
    Order "paid" state-indədir
    Event itib → Downstream service-lər bilmirlər
    Inventory azalmadı, email getmədi, shipping başlamadı
    → Inconsistency (silent, çətin tapılan)

  Kafka ok, DB fail:
    Event gəldi → Inventory azaldıldı
    DB rollback → Order still "pending"
    → Ghost event (DB-dəki state ilə uyğunsuz event)

  Process crash (DB ok, Kafka göndərilmədi):
    Application crash → Kafka produce çağrılmadı
    Event itirildi, heç bir log yoxdur
```

### 2. Outbox Pattern Əsas İdeyası
```
Həll:
  Kafka produce-u tamamilə aradan götür
  Bunun əvəzinə: Event-i eyni DB transaction-ında outbox table-a yaz

  BEGIN transaction
    UPDATE orders SET status = 'paid'
    INSERT INTO outbox (event_type, payload, status)
      VALUES ('order.paid', '{"order_id":123}', 'PENDING')
  COMMIT

  Ayrı process (Relay):
    SELECT * FROM outbox WHERE status = 'PENDING'
    kafka.produce(event)
    UPDATE outbox SET status = 'PUBLISHED'

Nəticə:
  DB + Outbox: Atomic (eyni transaction)
  Relay: Ayrıca async
  
  DB fail → Outbox da fail (rollback)
  DB ok → Outbox mütləq var
  Relay fail → PENDING events qalır → Retry
  → At-least-once delivery guarantee
```

### 3. Outbox Table Design
```sql
CREATE TABLE outbox (
    id              BIGSERIAL PRIMARY KEY,
    event_id        UUID NOT NULL DEFAULT gen_random_uuid(),
    event_type      VARCHAR(255) NOT NULL,
    aggregate_type  VARCHAR(100) NOT NULL,  -- 'order', 'user'
    aggregate_id    VARCHAR(100) NOT NULL,  -- order_id
    payload         JSONB NOT NULL,
    status          VARCHAR(20) DEFAULT 'PENDING',
                    -- PENDING, PUBLISHED, FAILED
    created_at      TIMESTAMP DEFAULT NOW(),
    published_at    TIMESTAMP,
    retry_count     INT DEFAULT 0,
    next_retry_at   TIMESTAMP,
    error_message   TEXT
);

-- Performance index:
CREATE INDEX idx_outbox_pending
    ON outbox (status, created_at)
    WHERE status = 'PENDING';

-- Cleanup old published events:
CREATE INDEX idx_outbox_published_at
    ON outbox (published_at)
    WHERE status = 'PUBLISHED';
```

### 4. Polling Relay Implementation
```php
// Outbox relay: polling-based

class OutboxRelayWorker
{
    public function run(): void
    {
        while (true) {
            $events = $this->fetchPendingEvents(batchSize: 100);

            if (empty($events)) {
                sleep(1);  // No events, wait
                continue;
            }

            foreach ($events as $event) {
                $this->processEvent($event);
            }
        }
    }

    private function fetchPendingEvents(int $batchSize): array
    {
        // SELECT ... FOR UPDATE SKIP LOCKED
        // Multiple relay instances: SKIP LOCKED prevents double processing
        return DB::select(
            "SELECT * FROM outbox
             WHERE status = 'PENDING'
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY created_at ASC
             LIMIT ?
             FOR UPDATE SKIP LOCKED",
            [$batchSize]
        );
    }

    private function processEvent(object $event): void
    {
        try {
            $this->kafka->produce(
                topic: $this->topicFor($event->event_type),
                key:   $event->aggregate_id,
                value: $event->payload,
                headers: [
                    'event_id'   => $event->event_id,
                    'event_type' => $event->event_type,
                ],
            );

            DB::update(
                "UPDATE outbox
                 SET status = 'PUBLISHED', published_at = NOW()
                 WHERE id = ?",
                [$event->id]
            );

        } catch (\Exception $e) {
            $retryAt = now()->addSeconds(
                min(300, pow(2, $event->retry_count))  // Exponential backoff
            );

            DB::update(
                "UPDATE outbox
                 SET retry_count = retry_count + 1,
                     next_retry_at = ?,
                     error_message = ?
                 WHERE id = ?",
                [$retryAt, $e->getMessage(), $event->id]
            );
        }
    }
}
```

### 5. CDC (Change Data Capture) ilə Relay
```
Polling relay problemi:
  DB polling: Hər saniyə SELECT → DB yükü
  Yüksək throughput: 10K event/s → polling yetişmir
  Latency: Polling interval = minimum latency

CDC Approach (Debezium):
  DB transaction log-unu oxuyur (WAL/binlog)
  Outbox table-a insert → Debezium dərhal görür
  Debezium → Kafka-ya publish edir
  
  Polling relay-dən üstünlükləri:
    Near real-time (milliseconds)
    DB poll yoxdur (WAL stream)
    Daha az DB yükü

  PostgreSQL WAL:
    Debezium PostgreSQL connector:
    wal_level = logical  ← postgresql.conf
    CREATE PUBLICATION outbox_publication FOR TABLE outbox;
    
    Debezium: Replication slot yaradır
    INSERT into outbox → WAL entry → Debezium → Kafka

  MySQL Binlog:
    binlog_format = ROW
    Debezium MySQL connector
    Outbox INSERT → binlog → Debezium → Kafka

CDC Trade-offs:
  Pros: Low latency, no poll overhead
  Cons: Debezium complexity, connector config, schema change sensitivity
       Replication slot: Unused → WAL accumulates (disk!)
```

### 6. Outbox + Idempotent Consumer
```
At-least-once delivery:
  Relay fail → Retry → Duplicate event mümkündür
  Consumer: Eyni event-i 2 dəfə alsın → Idempotent olmalıdır

Consumer idempotency:

1. Event ID check:
   processed_events table:
     event_id UUID PRIMARY KEY,
     processed_at TIMESTAMP

   Consumer:
     IF event_id in processed_events → SKIP
     ELSE → Process + INSERT into processed_events

2. Upsert / Idempotent operation:
   INSERT ... ON CONFLICT (order_id) DO NOTHING
   UPDATE inventory SET stock = stock - 1 WHERE order_id = :id
     AND NOT EXISTS (SELECT 1 FROM reservations WHERE order_id = :id)

3. Version check:
   UPDATE orders SET status = 'shipped', version = version + 1
   WHERE id = :id AND version = :expected_version

  Version mismatch → Already processed → Ignore

Exactly-once semantics:
  DB: Outbox event_id PRIMARY KEY → Duplicate insert = error
  Consumer: event_id check → Skip duplicate
  Result: Effectively exactly-once (at-least-once + idempotent)
```

### 7. Cleanup ve Retention
```
Outbox table böyüyür:
  High volume: 10K events/s → 864M events/day
  Disk fill risk

Cleanup strategies:

1. Time-based cleanup:
   DELETE FROM outbox
   WHERE status = 'PUBLISHED'
     AND published_at < NOW() - INTERVAL '7 days'

   Schedule: Daily off-peak hours
   Partition by created_at: Old partition DROP TABLE (instant)

2. Partition pruning:
   CREATE TABLE outbox PARTITION BY RANGE (created_at);
   CREATE TABLE outbox_2024_01 PARTITION OF outbox
     FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
   
   Old partitions: DROP PARTITION (milliseconds, no bloat)

3. Archive before delete:
   PUBLISHED events → S3/cold storage (audit trail)
   Then delete from outbox

Retention SLA:
  PENDING events: Until delivered (no cleanup)
  FAILED events: 30 days (investigation)
  PUBLISHED events: 7 days (replay window)
```

### 8. Multi-Relay (Horizontal Scale)
```
Yüksək throughput:
  Single relay: 1K events/s emal edir
  10K events/s lazımdır → 10 relay instance

Concurrent relay problem:
  İki relay eyni PENDING event-i alsa → Duplicate publish

Həll: SELECT ... FOR UPDATE SKIP LOCKED
  Relay 1: Event 1-100 LOCK alır
  Relay 2: Event 1-100 LOCKED → SKIP → Event 101-200 alır
  → Paralel, overlap yoxdur

  SKIP LOCKED: PostgreSQL, MySQL 8+, MariaDB destəkləyir

Partition-based relay:
  Outbox: aggregate_id % 8 = shard
  Relay 1: shard 0, 1
  Relay 2: shard 2, 3
  ...
  → Strict ordering per aggregate guaranteed
  → Relay 1 down → Relay 3 shard 0,1 üzərinə edir (failover)
```

### 9. Observability
```
Metrics (monitor etmək lazım olan):

1. Outbox queue depth:
   SELECT COUNT(*) FROM outbox WHERE status = 'PENDING'
   Alert: depth > 1000 → Relay ləngidir
   Alert: depth > 10000 → Relay down

2. Oldest unprocessed event age:
   SELECT EXTRACT(EPOCH FROM NOW() - MIN(created_at))
   FROM outbox WHERE status = 'PENDING'
   Alert: age > 60s → Relay problem

3. Failed events:
   SELECT COUNT(*) FROM outbox WHERE status = 'FAILED'
   Alert: failed > 0 → Immediate investigation

4. Relay throughput:
   events_published_per_second
   Alert: < expected_tps → Relay slow

5. Retry rate:
   SELECT AVG(retry_count) FROM outbox WHERE status = 'PUBLISHED'
   High retry rate → Downstream (Kafka) unstable

Dashboards:
  Grafana: Outbox queue depth time-series
  Alert: PagerDuty/Slack → On-call

Dead Letter:
  retry_count > 5 → FAILED status
  FAILED events: Manual review, replay tool
  Replay: UPDATE outbox SET status='PENDING', retry_count=0 WHERE id IN (...)
```

### 10. Outbox vs Alternatives
```
Transaction Outbox:
  Pros: Simple, works with any DB, reliable
  Cons: Extra table, polling overhead (if no CDC), relay complexity

Change Data Capture (standalone):
  Debezium on regular tables (without outbox)
  Pros: No schema change needed
  Cons: Domain events = internal DB changes (tight coupling)
       Schema change → Event format change
       Outbox pattern abstracts this

Event Sourcing:
  Event = source of truth (not side effect)
  All state changes are events
  More complex, but event history built-in
  Use when: Event history/replay is primary requirement

Saga + Outbox:
  Saga step local transaction
  Outbox: Event publish (saga coordination)
  → Reliable saga event delivery

Two-Phase Commit (2PC):
  Pros: True atomicity (no relay needed)
  Cons: Blocking, low availability, cross-service only with XA
  → Outbox preferred for microservices

Verdict:
  Outbox + CDC: High-throughput event-driven microservices
  Outbox + Polling: Simpler setup, medium throughput
  2PC: Enterprise, same-org, low-volume, strict atomicity needed
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Dual-write problemini motivasiya et: "DB + Kafka atomic deyil"
2. Outbox ideysını izah et: "Event DB-ə yaz, relay Kafka-ya göndərir"
3. At-least-once semantics → Idempotent consumer lazımdır
4. CDC vs polling relay trade-off-larını müzakirə et
5. Cleanup, monitoring, multi-relay scale-i qeyd et

### Follow-up Suallar
- "Relay özü crash olsa?" → PENDING events qalır, yenidən başladıqda retry
- "10K events/s üçün necə scale edərsin?" → Multi-relay + SKIP LOCKED, ya da CDC
- "Outbox table çox böyüyürsə?" → Partitioning + periodic cleanup
- "Exactly-once lazım olsa?" → Idempotent consumer + event_id deduplication

### Ümumi Namizəd Səhvləri
- "DB yazıb, sonra Kafka-ya göndəririk" demək — dual-write problemi var
- Outbox relay-in at-least-once delivery etdiyini unutmaq
- Idempotent consumer-i qeyd etməmək
- CDC-nin nə olduğunu bilməmək
- SKIP LOCKED olmadan multi-relay yaratmaq → Duplicate events

### Senior vs Architect Fərqi
**Senior**: Outbox table dizayn edir, polling relay yazır, idempotent consumer implement edir, SKIP LOCKED bilir.

**Architect**: CDC (Debezium) ilə low-latency outbox arxitekturası dizayn edir, outbox partition strategy-si müəyyən edir (high-volume cleanup), relay throughput limitasiyasını hesablayır (polling interval × batch size), outbox failure SLO-ya (event delivery latency) effektini analiz edir, dead letter handling + replay strategy müəyyən edir, outbox-u saga orchestration-ı ilə birlikdə dizayn edir (saga step → local commit + outbox insert).

## Nümunələr

### Tipik Interview Sualı
"In your e-commerce system, when an order is placed, you need to update the database and publish an event to trigger inventory reservation, email notification, and analytics. How do you ensure reliability?"

### Güclü Cavab
```
E-commerce order event reliability:

Problem:
  Order yerləşdirildikdə 3 downstream action:
  1. Inventory reservation
  2. Email notification
  3. Analytics event

  Naive: DB write + 3 Kafka produces
  Risk: 1 Kafka fail → Partial failure, silent inconsistency

Solution: Transactional Outbox

Schema:
  -- Single outbox table for all events
  CREATE TABLE outbox (
    id             BIGSERIAL PRIMARY KEY,
    event_id       UUID NOT NULL DEFAULT gen_random_uuid(),
    event_type     VARCHAR(255) NOT NULL,
    aggregate_type VARCHAR(100) NOT NULL,
    aggregate_id   BIGINT NOT NULL,
    payload        JSONB NOT NULL,
    status         VARCHAR(20) DEFAULT 'PENDING',
    created_at     TIMESTAMP DEFAULT NOW(),
    published_at   TIMESTAMP,
    retry_count    INT DEFAULT 0,
    next_retry_at  TIMESTAMP
  );

Order placement (single transaction):
  BEGIN;
    INSERT INTO orders (user_id, items, total, status)
      VALUES (..., 'placed') RETURNING id → order_id=123;
    
    INSERT INTO outbox (event_type, aggregate_type, aggregate_id, payload)
      VALUES ('order.placed', 'order', 123,
              '{"order_id":123,"user_id":456,"items":[...],"total":99.99}');
  COMMIT;
  -- Atomic: Both succeed or both fail

Relay (Debezium CDC):
  PostgreSQL WAL → Debezium → Kafka topics:
  
  Topic routing (by event_type):
    order.placed → topic: orders.placed
    order.placed → topic: orders.placed
    (Debezium outbox router SMT: Single Message Transform)

  Kafka topics:
    orders.placed → Inventory Service
    orders.placed → Email Service
    orders.placed → Analytics Service

Idempotency per consumer:

  Inventory Service:
    processed_events: (event_id PK)
    "reserve item": INSERT INTO reservations ... ON CONFLICT DO NOTHING
    
  Email Service:
    email_log: (event_id UNIQUE)
    "send email": INSERT INTO email_log → SKIP if duplicate

  Analytics Service:
    Clickhouse: INSERT with event_id deduplication
    (Clickhouse: ReplacingMergeTree + event_id as key)

Observability:
  Debezium lag: Kafka consumer group lag on outbox topic
  Alert: lag > 5000 → CDC slow
  PENDING events: Should be 0 (CDC immediate)
  FAILED events: Manual review queue

Replay:
  Scenario: Analytics service down 2 hours
  Kafka retention: 7 days
  Analytics consumer: Rewind offset to 2 hours ago
  → Replay missed events (idempotency: no duplicate processing)
```

### Arxitektura Nümunəsi
```
  Order Service
       │
       │ BEGIN TRANSACTION
       │   INSERT orders
       │   INSERT outbox ← same transaction!
       │ COMMIT
       │
       │ WAL (Write-Ahead Log)
       ▼
  ┌────────────────┐
  │  PostgreSQL    │
  │  outbox table  │
  │  (PENDING)     │
  └────────┬───────┘
           │ CDC (WAL stream)
           ▼
  ┌────────────────┐
  │   Debezium     │
  │   Connector    │
  └────────┬───────┘
           │ publish (milliseconds)
           ▼
  ┌────────────────────────────────────┐
  │            Kafka                    │
  │  topic: orders.placed              │
  └──┬─────────────┬──────────────┬────┘
     │             │              │
     ▼             ▼              ▼
 Inventory    Email Svc      Analytics
 Service      (idempotent)   (idempotent)
 (idempotent)
```

## Praktik Tapşırıqlar
- Laravel-də outbox table yaradın, polling relay implement edin (queue worker kimi)
- SKIP LOCKED test edin: 3 parallel relay instance, duplicate yoxlayın
- Debezium + PostgreSQL CDC qurun (Docker Compose ilə local)
- Idempotent consumer: event_id table ilə duplicate handling test edin
- Cleanup job: Partitioned outbox table, partition drop performance ölçün

## Əlaqəli Mövzular
- [17-distributed-transactions.md](17-distributed-transactions.md) — Saga + Outbox reliable event delivery
- [08-message-queues.md](08-message-queues.md) — Kafka topic design, consumer groups
- [13-idempotency-design.md](13-idempotency-design.md) — Idempotent consumer implementation
- [18-event-driven-architecture.md](18-event-driven-architecture.md) — Event-driven systems
- [23-eventual-consistency.md](23-eventual-consistency.md) — At-least-once + idempotency = eventually consistent
