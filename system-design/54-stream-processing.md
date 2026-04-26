# Stream Processing (Lead)

## İcmal

Stream processing — davamlı gələn **unbounded** event axınını (clickstream, IoT sensor, transaction, log) **near-real-time** emal etməkdir. Batch processing isə **bounded** sabit data üzərində işləyir (gecə ETL job-u, saatlıq aggregation).

| Xüsusiyyət | Batch | Stream |
|------------|-------|--------|
| Data | Bounded (fixed) | Unbounded (continuous) |
| Latency | dəqiqə / saat | millisaniyə / saniyə |
| Throughput | çox yüksək | yüksək |
| State | adətən stateless | stateful (window, aggregation) |
| Nümunələr | Hadoop, Spark batch, nightly ETL | Flink, Kafka Streams, Kinesis |
| Reprocessing | rerun job | event log replay |

Stream processing müasir data-intensive sistemlərin əsasıdır — real-time dashboard, fraud detection, alerting, ML feature pipeline.


## Niyə Vacibdir

Batch processing saatlarla gözlətdirir; stream processing real-time insight verir. Lambda vs Kappa arxitekturası seçimi infra kompleksliyini müəyyən edir; exactly-once semantics guarantee etmək çətin, lakin kritikdir. Flink, Kafka Streams — real-time analitikanın standart alətlərdir.

## Use Case-lər (Use Cases)

- **Real-time analytics** — hər dəqiqə aktiv user, top N səhifə
- **Fraud detection** — transaction-u 200ms-də flag etmək
- **Alerting** — metric threshold aşanda PagerDuty trigger
- **ETL into DW** — OLTP → Snowflake/BigQuery axın
- **CDC pipelines** — binlog → Kafka → Elasticsearch
- **ML feature generation** — son 1 saatın aggregated feature-ləri
- **Log enrichment** — raw log + GeoIP parse → index

## Lambda Architecture

Lambda — **batch** və **speed** layer-lərini paralel saxlayıb nəticəni serving layer-də birləşdirən arxitekturadır. Nathan Marz (Twitter) təklif etmişdi.

```
                   ┌─────────────────────────┐
                   │   Immutable Event Log   │ (Kafka, S3, HDFS)
                   └────────┬────────────────┘
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
      ┌───────────────┐          ┌────────────────┐
      │  Batch Layer  │          │  Speed Layer   │
      │ Spark/Hadoop  │          │ Flink/Storm    │
      │ (hours)       │          │ (seconds)      │
      └──────┬────────┘          └────────┬───────┘
             │                            │
             ▼                            ▼
      ┌───────────────┐          ┌────────────────┐
      │ Batch Views   │          │ Realtime Views │
      │ (accurate)    │          │ (approximate)  │
      └──────┬────────┘          └────────┬───────┘
             └────────────┬───────────────┘
                          ▼
                 ┌────────────────┐
                 │ Serving Layer  │ (merge view)
                 └────────────────┘
```

**Artıları:**
- Batch layer-dən tam reprocessing — bug fix olduqda düzgün view yenidən qurulur
- Speed layer approximate olsa da, batch təkmil dəqiq nəticə verir
- Separation: batch optimized for throughput, stream optimized for latency

**Mənfiləri:**
- **İki kod bazası** — eyni logic həm Spark, həm Flink-də yazılır (drift)
- Operational cost — iki ayrı cluster
- Merge logic complex — batch view-u speed view-u ilə birləşdirmək

## Kappa Architecture

Kappa — Jay Kreps (LinkedIn, Kafka) təklif etdi. **Yalnız streaming layer**. Batch yox — reprocessing lazım olanda event log-dan başdan replay edilir.

```
 ┌──────────────────────┐
 │  Event Log (Kafka)   │ long retention (30-90 gün)
 │  partitioned, replayable │
 └──────┬───────────────┘
        │
        ▼
  ┌─────────────────┐
  │ Stream Processor│ (Flink / Kafka Streams)
  │ (single code)   │
  └────────┬────────┘
           │
           ▼
   ┌────────────────┐
   │ Serving Store  │ (Elasticsearch / Cassandra)
   └────────────────┘
```

**Reprocessing ssenarisi:** bug tapıldı → yeni versiya consumer deploy → offset 0-dan oxumağa başla → yeni serving table doldur → traffic switch.

**Artıları:**
- **Bir kod bazası** — maintenance asan
- Operational simplicity
- Kafka retention + compaction kifayətdir

**Mənfiləri:**
- Event log-da retention uzun olmalıdır (disk cost)
- Çox illik tarixi data üçün batch hələ lazımdır
- Reprocessing müddətində double infrastructure

Müasir sistemlərdə Kappa default seçimdir; Lambda yalnız dəqiqlik çox kritik olan finance/compliance sahəsində.

## Əsas Anlayışlar

### 1. Event Time vs Processing Time

- **Event time** — event-in mənbədə yarandığı vaxt (mobile app-də click 10:00:00)
- **Ingestion time** — event broker-ə çatdığı vaxt
- **Processing time** — stream processor event-i emal etdiyi vaxt

Mobile offline ola bilər → event 2 saat sonra gəlir (**late-arriving**). Network gecikir → event-lər **out-of-order** gəlir. Düzgün analytics üçün **event time** istifadə olunmalıdır.

```
Event time:     10:00    10:01    10:02    10:00 (late!)
Processing:     10:01    10:02    10:03    10:15
                                            ↑ 15 min late
```

### 2. Watermarks

Watermark — "bu timestamp-dan köhnə event daha gəlməyəcək" threshold-udur. Stream processor watermark-ı istifadə edərək window-u **finalize** edir.

```
events:     [10:00] [10:01] [10:02] [10:04]   watermark=10:03
window [10:00-10:03) → bağlanır, emit nəticə
                              [10:02 late] → too-late, ya atılır ya side output
```

Watermark generation strategiyaları:
- **Bounded out-of-orderness:** max lateness = 5 dəqiqə, watermark = maxEventTime - 5min
- **Punctuated:** xüsusi marker event watermark yaradır
- **Periodic:** hər N saniyə watermark advance

### 3. Windowing Tipləri

**a) Tumbling Window** (non-overlapping, fixed size):
```
[0-5)  [5-10)  [10-15)  [15-20)
 ───    ───     ───      ───
```
Hər event düz bir window-a düşür. Nümunə: hər 1 dəqiqəlik click sayı.

**b) Sliding Window** (overlapping, fixed size + slide interval):
```
size=10, slide=5
[0-10)
     [5-15)
          [10-20)
```
Nümunə: son 10 dəqiqənin ortalaması, hər 5 dəqiqə yenilənir.

**c) Session Window** (gap-based, dynamic):
```
gap=30s
event stream:  e1 e2 e3 ........(40s pause)....... e4 e5
               └─ session 1 ─┘                     └ session 2 ┘
```
User behavior, web session tracking.

**d) Global Window** (manual trigger):
Bütün data bir window-da, custom trigger (count-based, timer) ilə emit.

### 4. State Management

Stream processor aggregation, join, dedup üçün **state** saxlayır:
- **Local state** — task-a yapışan embedded store (Flink: RocksDB, Kafka Streams: RocksDB)
- **Checkpointing** — vaxtaşırı state DFS-ə (S3, HDFS) yazılır
- **Chandy-Lamport algorithm** — distributed consistent snapshot
- Fail olduqda → son checkpoint-dan recovery

```
t=0      t=1 (checkpoint)     t=2 (fail)       t=3 (recovery)
 state₀  ──► state₁ ────►  state₂ lost  ──► restore state₁ + replay offset
```

### 5. Delivery Semantics

| Semantic | Təsvir | Nümunə |
|----------|--------|--------|
| **At-most-once** | Event 0 və ya 1 dəfə. Duplicate yox, itki var | Metric approximate |
| **At-least-once** | Event ≥1 dəfə. Duplicate mümkün, itki yox | Idempotent consumer |
| **Exactly-once** | Event tam 1 dəfə (effect-wise) | Finance, counter |

Flink exactly-once-u **two-phase commit sink** + **Chandy-Lamport snapshots** ilə təmin edir:
1. **Pre-commit:** sink barrier aldığında external transaction başladır
2. **Commit:** checkpoint complete olduqda transaction commit
3. Fail olduqda → uncommitted transaction rollback, replay

Kafka 0.11+ transactional producer + idempotent producer exactly-once-a imkan verir.

### 6. Frameworks

| Framework | Tip | Xüsusiyyət | Best for |
|-----------|-----|-----------|----------|
| **Kafka Streams** | Library (JVM) | Kafka-ya tight, no cluster | Kafka-first app |
| **Apache Flink** | True streaming | Event-time first class, exactly-once, CEP | Mission-critical, complex windowing |
| **Spark Streaming** | Micro-batch | Batch API reuse, 100ms-1s latency | Batch+stream unified, ML |
| **Kinesis / Dataflow** | Managed | Serverless, auto-scale | AWS/GCP ekosistem |
| **Apache Beam** | Portable API | Run on Flink/Dataflow/Spark | Framework-agnostic |

### 7. Join Tipləri

- **Stream-Stream join** — windowed (`orders` + `payments` last 10min)
- **Stream-Table join** — enrichment (click stream + user profile dim table)
- **Table-Table join** — changelog stream-lərin join-u, materialized view

```
orders ─┐
        ├──► windowed join [5min] ──► enriched_orders
clicks ─┘
```

### 8. Backpressure

Producer consumer-dən sürətli olduqda sistem necə davranır?
- **Flink:** built-in backpressure — downstream yavaşlayanda upstream buffer dolur, source slow down edir
- **Kafka:** consumer lag metric — offset lag yüksələndə consumer scale-out lazımdır
- **Kinesis:** shard limit, `ProvisionedThroughputExceededException`

## Real-World Pipeline Nümunələri

### 1. Clickstream Analytics
```
Web/Mobile → Kafka(clicks) → Flink(session window, 30min gap)
           → aggregate by user → Redis (realtime) + S3 (cold)
           → Dashboard
```

### 2. Real-time Fraud Scoring
```
Transaction → Kafka → Flink CEP(pattern: 5 tx/10s, >$1000)
           → ML model scoring → if risk>0.9 → block service + alert
```

### 3. Log Enrichment + Elasticsearch
```
App logs → Fluentd → Kafka → Flink(parse + GeoIP + UA) → Elasticsearch → Kibana
```

## Nümunələr

PHP "true" stream processor deyil (Flink/Kafka Streams JVM-dir), lakin **Kafka consumer** kimi işləyə bilər. `rdkafka` extension + Laravel queue ilə event-driven mikroservis qura bilərik.

### Kafka Producer (Laravel)

```php
use RdKafka\Producer;
use RdKafka\Conf;

class KafkaEventProducer
{
    private Producer $producer;

    public function __construct()
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', config('kafka.brokers'));
        $conf->set('enable.idempotence', 'true');   // exactly-once producer
        $conf->set('acks', 'all');
        $conf->set('compression.type', 'snappy');
        $this->producer = new Producer($conf);
    }

    public function publish(string $topic, string $key, array $payload): void
    {
        $t = $this->producer->newTopic($topic);
        $t->producev(RD_KAFKA_PARTITION_UA, 0, json_encode($payload), $key,
            ['event_time' => (string) now()->valueOf()]);
        $this->producer->poll(0);
        $this->producer->flush(5000);
    }
}
```

### Kafka Consumer Daemon

```php
class ClickstreamConsumer extends Command
{
    protected $signature = 'kafka:consume-clicks';

    public function handle(): void
    {
        $conf = new \RdKafka\Conf();
        $conf->set('group.id', 'clickstream-analytics');
        $conf->set('metadata.broker.list', config('kafka.brokers'));
        $conf->set('enable.auto.commit', 'false');   // manual commit
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe(['clicks']);

        while (!$this->shouldStop()) {
            $msg = $consumer->consume(1000);
            if ($msg->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
                $this->process($msg, $consumer);
            }
        }
    }

    private function process($msg, $consumer): void
    {
        $event = json_decode($msg->payload, true);

        // Idempotency — inbox pattern
        if (ProcessedEvent::where('event_id', $event['event_id'])->exists()) {
            $consumer->commit($msg);
            return;
        }

        DB::transaction(function () use ($event) {
            $this->updateWindowedAggregate($event);
            ProcessedEvent::create(['event_id' => $event['event_id']]);
        });

        $consumer->commit($msg);
    }
}
```

### Windowed Aggregate — Redis Sorted Set

PHP-də "tumbling 1-min click count per user" Redis ilə:

```php
public function updateWindowedAggregate(array $event): void
{
    $userId      = $event['user_id'];
    $eventTime   = (int) ($event['event_time'] / 1000);
    $windowStart = $eventTime - ($eventTime % 60);   // 1-min tumbling
    $key         = "clicks:window:{$windowStart}";

    Redis::pipeline(function ($pipe) use ($key, $userId) {
        $pipe->zincrby($key, 1, $userId);
        $pipe->expire($key, 600);   // 10 min TTL (late events buffer)
    });
}

// Watermark-a çatanda window-u emit et
public function flushWindow(int $windowStart): void
{
    $key = "clicks:window:{$windowStart}";
    foreach (Redis::zrevrange($key, 0, 99, 'WITHSCORES') as $userId => $count) {
        ClickAggregate::create([
            'window_start' => Carbon::createFromTimestamp($windowStart),
            'user_id'      => $userId,
            'count'        => (int) $count,
        ]);
    }
    Redis::del($key);
}
```

### Backpressure Handling in PHP Consumer

PHP single-threaded olduğu üçün consumer-i downstream yavaşlayanda qorumalıyıq. Adaptive batch size — downstream latency-ə görə batch artır/azalır:

```php
public function consumeBatch(KafkaConsumer $consumer): void
{
    $batch = [];
    for ($i = 0; $i < $this->batchSize; $i++) {
        $msg = $consumer->consume(50);
        if ($msg->err === RD_KAFKA_RESP_ERR_NO_ERROR) $batch[] = $msg;
    }

    $start = microtime(true);
    $this->bulkInsertElasticsearch($batch);
    $elapsed = (microtime(true) - $start) * 1000;

    if ($elapsed > 500) {
        $this->batchSize = max(10, (int) ($this->batchSize * 0.8));  // throttle
    } elseif ($elapsed < 250) {
        $this->batchSize = min(1000, (int) ($this->batchSize * 1.2));
    }

    foreach ($batch as $msg) $consumer->commit($msg);
}
```

Əlavə tədbirlər:
- **Horizontal scale** — partition sayı qədər consumer pod
- **Pause/resume API** — downstream alert olanda consumer pause
- **Circuit breaker** — sink down olanda DLQ-ya yaz
- **Lag monitoring** — Burrow/Kafka exporter + Grafana alert (lag > 10k)
- **Supervisor** — `numprocs=4`, autorestart, graceful SIGTERM

## Praktik Tapşırıqlar

**1. Lambda və Kappa arxitekturası arasında fərq nədir?**
Lambda paralel batch + speed layer saxlayır, reprocessing batch-dən gəlir — iki kod bazası, operational overhead. Kappa yalnız streaming saxlayır, reprocessing event log-u replay etməklə olur — bir kod bazası, daha sadə. Müasir sistemlərdə Kappa default.

**2. Event time və processing time fərqi nədir, niyə vacibdir?**
Event time mənbədə yaranma vaxtı, processing time isə emal vaxtıdır. Mobile offline → event 2 saat sonra gəlir (late). Düzgün analytics (hər dəqiqə click sayı) event time üzərində olmalıdır, yoxsa nəticə network gecikməsindən təsirlənir.

**3. Watermark nədir və necə işləyir?**
Watermark "bu timestamp-dan köhnə event gəlməyəcək" threshold-udur. Stream processor window-u finalize etmək üçün watermark gözləyir. Adətən `maxEventTime - maxLateness` (məs. 5 min) kimi hesablanır. Watermark-dan sonra gələn event "late" — atılır və ya side output-a gedir.

**4. Tumbling, sliding və session window arasında fərq nədir?**
Tumbling — non-overlapping fixed (hər dəqiqə). Sliding — overlapping fixed (son 10 min hər 1 min). Session — gap-based dinamik, user inactivity threshold-u ilə bağlanır (web session tracking). Global window manual trigger tələb edir.

**5. Flink exactly-once semantikasını necə təmin edir?**
Chandy-Lamport distributed snapshot algoritmi + two-phase commit sink. Checkpoint barrier stream boyu axır, hər operator state-i DFS-ə yazır. Sink pre-commit (external transaction başlat), checkpoint complete olduqda commit. Fail olarsa uncommitted transaction rollback + son checkpoint-dan replay.

**6. Kafka Streams və Flink arasında seçim necə edilir?**
Kafka Streams — library, yalnız Kafka source/sink, JVM app daxilində işləyir (ayrı cluster yox). Flink — standalone cluster, çox source (Kafka, Kinesis, files), daha güclü windowing və CEP, çox yüksək throughput. Kafka-first sadə case-lərdə Streams, mürəkkəb stateful + çox-source üçün Flink.

**7. Stream processor-da state necə saxlanılır və fail-recovery necə olur?**
Local state RocksDB-də (task-a embedded). Vaxtaşırı checkpoint DFS-ə (S3/HDFS) yazılır. Fail olduqda yeni task son checkpoint-dan state restore edir və Kafka offset-indən replay başlayır. Kafka Streams-də changelog topic state-in backup-ıdır — recovery topic-i replay edir.

**8. PHP-də stream processing-in məhdudiyyətləri hansılardır?**
PHP single-threaded, shared-nothing, JVM-dəki RocksDB + checkpointing ekosistemi yoxdur. Windowed state üçün external store (Redis/DB) lazımdır — latency artır. Exactly-once çətin, əsasən at-least-once + idempotent consumer istifadə olunur. Real stream processing üçün Flink/Kafka Streams JVM servisi yazılır, PHP sadəcə producer və sadə consumer olaraq qalır.

## Praktik Baxış

1. **Event-time first** — timestamp-ı event-in özündə saxla
2. **Watermark explicit** — max lateness business requirement ilə uzlaşsın
3. **Exactly-once yalnız lazım olanda** — bahadır; at-least-once + idempotent çox halda kifayət
4. **Checkpoint interval balans** — çox qısa overhead, çox uzun recovery
5. **Schema evolution** — Avro/Protobuf + Schema Registry, breaking change etmə
6. **Partition key careful** — hot partition skew yaradır
7. **Dead letter topic** — parse edilə bilməyən event-lər üçün
8. **Monitoring** — consumer lag, checkpoint duration, watermark progress
9. **Kafka retention uzun** — Kappa reprocessing üçün 7-30 gün
10. **Idempotent sink** — stable `_id`, DB upsert, deterministik path
11. **Graceful shutdown** — SIGTERM-də commit + state flush

## Əlaqəli Mövzular

- [Event-Driven Architecture](11-event-driven-architecture.md)
- [CDC & Outbox Pattern](46-cdc-outbox-pattern.md)
- [Message Queues](05-message-queues.md)
- [Real-time Systems](17-real-time-systems.md)
- [Distributed Transactions & Saga](45-distributed-transactions-saga.md)
