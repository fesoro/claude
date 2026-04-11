# System Design: Real-Time Analytics Pipeline

## Mündəricat
1. [Tələblər](#tələblər)
2. [Lambda vs Kappa Arxitektura](#lambda-vs-kappa-arxitektura)
3. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional (E-Commerce Analytics):
  Real-time dashboard: aktiv istifadəçilər, son sifarişlər
  Saatlıq/günlük/aylıq hesabatlar
  Funnel analizi: məhsul görüntüləmə → səbət → ödəniş
  Anomaly detection: qeyri-adi trafik

Qeyri-funksional:
  Real-time latency: < 5 saniyə (dashboard)
  Historical query: < 10 saniyə (son 1 il)
  Ingestion: 100,000 event/saniyə
  Durability: event-lər itirilməsin

Data növləri:
  Clickstream (page view, click)
  Transaction (order, payment)
  User behavior (search, add-to-cart)
```

---

## Lambda vs Kappa Arxitektura

```
Lambda Architecture:
  Batch Layer:   Hamı data → HDFS/S3 → Spark/Hadoop → batch view
  Speed Layer:   Yeni data → Stream processing → real-time view
  Serving Layer: Batch + Speed birliyini serve edir
  
  Problem: İki sistem = iki kod = iki debug

  Batch Layer  →  Batch Views  ──┐
                                  ├──► Serving Layer → Query
  Speed Layer  →  RT Views   ───┘

Kappa Architecture:
  Yalnız stream processing
  Kafka log = single source of truth
  Reprocessing: stream-i başdan replay et
  
  Kafka → Stream Processing (Flink/Spark Streaming) → Views
  
  Sadə: bir sistem, bir kod
  Modern seçim (Lambda-nı əvəz etdi)

Bizim seçim: Kappa
  Kafka: durable event log
  ClickHouse: real-time OLAP
  Stream processor: PHP worker + Flink
```

---

## Yüksək Səviyyəli Dizayn

```
Data Ingestion:
  App servers → Kafka producers (async, fire-forget)
  
  Kafka Topology:
    events.raw         (bütün raw events)
    events.clickstream (ayrıca click events)
    events.orders      (order events)

Stream Processing:
  Kafka Consumer → Enrichment → Aggregation → ClickHouse

Real-time Aggregation (ClickHouse):
  Materialized views: önce hesablanmış aggregat-lar
  INSERT trigger edir → materialized view güncəllənir
  Dashboard sorğusu: əvvəlcədən aggregat edilmiş data

┌──────────┐  ┌───────┐  ┌──────────────┐  ┌─────────────┐
│App Server│─►│Kafka  │─►│Stream Worker │─►│ClickHouse   │
│(Events)  │  │(Log)  │  │(PHP+Flink)   │  │(OLAP)       │
└──────────┘  └───────┘  └──────────────┘  └──────┬──────┘
                                                    │
                                           ┌────────▼──────┐
                                           │   Dashboard   │
                                           │   (Grafana)   │
                                           └───────────────┘

Cold Storage:
  Kafka → S3 (raw data, 7 il)
  ClickHouse → köhnə data tiered storage
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Event Producer — Application side
namespace App\Analytics;

class AnalyticsEventProducer
{
    public function __construct(
        private \RdKafka\Producer $producer,
        private string            $topic = 'events.raw',
    ) {}

    public function track(string $eventType, array $data, string $userId = null): void
    {
        $event = [
            'event_type'     => $eventType,
            'user_id'        => $userId,
            'session_id'     => session_id() ?: null,
            'properties'     => $data,
            'timestamp'      => microtime(true) * 1000, // milliseconds
            'server_time'    => (new \DateTimeImmutable())->format(\DateTime::ATOM),
        ];

        $topic   = $this->producer->newTopic($this->topic);
        $payload = json_encode($event);

        // Async, non-blocking
        $topic->produce(
            \RD_KAFKA_PARTITION_UA, // Auto partition
            0,
            $payload,
            $userId ?? session_id(), // Key — same user → same partition (ordering)
        );

        $this->producer->poll(0); // Non-blocking flush
    }
}

// Controller-da istifadə:
// $analytics->track('product.viewed', ['product_id' => '123', 'category' => 'electronics']);
// $analytics->track('order.placed',   ['order_id' => '456', 'total' => 99.99], $userId);
```

```php
<?php
// 2. Stream Worker — Kafka consumer → ClickHouse
class AnalyticsStreamWorker
{
    private \RdKafka\KafkaConsumer $consumer;
    private ClickHouseClient       $clickhouse;

    public function __construct()
    {
        $conf = new \RdKafka\Conf();
        $conf->set('group.id',            'analytics-worker');
        $conf->set('bootstrap.servers',   'kafka:9092');
        $conf->set('auto.offset.reset',   'earliest');
        $conf->set('enable.auto.commit',  'false'); // Manual commit

        $this->consumer = new \RdKafka\KafkaConsumer($conf);
        $this->consumer->subscribe(['events.raw']);

        $this->clickhouse = new ClickHouseClient('http://clickhouse:8123');
    }

    public function run(): void
    {
        $batch = [];

        while (true) {
            $message = $this->consumer->consume(1000); // 1s timeout

            if ($message->err === \RD_KAFKA_RESP_ERR_NO_ERROR) {
                $event   = json_decode($message->payload, true);
                $batch[] = $this->enrich($event, $message);

                // Batch insert (100 event)
                if (count($batch) >= 100) {
                    $this->flushBatch($batch);
                    $this->consumer->commit($message);
                    $batch = [];
                }
            } elseif (!empty($batch)) {
                // Timeout — əlimizdəkiləri yaz
                $this->flushBatch($batch);
                $this->consumer->commit();
                $batch = [];
            }
        }
    }

    private function enrich(array $event, $message): array
    {
        return array_merge($event, [
            'kafka_offset'    => $message->offset,
            'kafka_partition' => $message->partition,
            'processed_at'    => (new \DateTimeImmutable())->format(\DateTime::ATOM),
            // GeoIP enrichment
            'country'         => $this->geoip->getCountry($event['ip'] ?? ''),
            // User agent parsing
            'device_type'     => $this->userAgentParser->getDevice($event['user_agent'] ?? ''),
        ]);
    }

    private function flushBatch(array $events): void
    {
        $this->clickhouse->insert('analytics.events', $events);
    }
}
```

```sql
-- ClickHouse schema — real-time OLAP
CREATE TABLE analytics.events (
    event_type   LowCardinality(String),
    user_id      Nullable(String),
    session_id   Nullable(String),
    country      LowCardinality(String),
    device_type  LowCardinality(String),
    properties   String,  -- JSON
    timestamp    DateTime64(3),
    date         Date DEFAULT toDate(timestamp)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (event_type, date, user_id)
TTL date + INTERVAL 2 YEAR;

-- Materialized View: hourly aggregation (real-time güncəllənir)
CREATE MATERIALIZED VIEW analytics.hourly_events
ENGINE = SummingMergeTree()
ORDER BY (event_type, hour)
AS SELECT
    event_type,
    toStartOfHour(timestamp) AS hour,
    count() AS event_count,
    uniqExact(user_id) AS unique_users,
    uniqExact(session_id) AS unique_sessions
FROM analytics.events
GROUP BY event_type, hour;

-- Dashboard sorğusu (çox sürətli):
SELECT hour, event_count, unique_users
FROM analytics.hourly_events
WHERE event_type = 'order.placed'
  AND hour >= now() - INTERVAL 24 HOUR
ORDER BY hour;
```

---

## İntervyu Sualları

- Lambda vs Kappa arxitektura fərqi nədir?
- Niyə analytics üçün ClickHouse PostgreSQL-dən daha uyğundur?
- Kafka-da ordering necə təmin edilir?
- Materialized view real-time analytics üçün niyə lazımdır?
- Stream processing worker fail olsa nə baş verir?
- Backpressure analytics pipeline-da necə idarə edilir?
