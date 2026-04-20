# Redis Streams — Deep Dive

## Mündəricat
1. [Redis Streams nədir?](#redis-streams-nədir)
2. [Stream vs Pub/Sub vs List](#stream-vs-pubsub-vs-list)
3. [XADD, XREAD əsasları](#xadd-xread-əsasları)
4. [Consumer Groups](#consumer-groups)
5. [Acknowledgement (XACK) və PEL](#acknowledgement-xack-və-pel)
6. [Trim & retention](#trim--retention)
7. [Replay & history](#replay--history)
8. [Kafka-like patterns](#kafka-like-patterns)
9. [PHP nümunəsi](#php-nümunəsi)
10. [Performance & limitations](#performance--limitations)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Redis Streams nədir?

```
Redis Streams (Redis 5.0+) — append-only log data type.
"Mini Kafka inside Redis"

Niyə əlavə olundu?
  - Redis Pub/Sub: ephemeral (sub yoxdursa message itir)
  - Redis List: queue işləyir, amma history yox, multi-consumer çətin
  - Streams: persistent, replayable, multi-consumer (consumer groups)

Use case:
  - Event log
  - Time-series data ingestion
  - Job queue (with replay)
  - Activity feed
  - Audit log
  - Sensor / IoT data
  - Sürətli message broker (Kafka-dan kiçik)
```

---

## Stream vs Pub/Sub vs List

```
Feature              | Pub/Sub      | List (LPUSH/RPOP)| Stream (XADD/XREAD)
─────────────────────────────────────────────────────────────────────────────
Persistence         | No (in-memory)| Yes              | Yes
Replay              | No           | No (consumed=gone)| Yes (full history)
Multi-consumer      | Yes (broadcast)| No (1 takes)   | Yes (groups)
Consumer offset     | No           | No                | Yes (per-group)
Acknowledgement     | No           | No                | Yes (XACK)
Backpressure        | No           | Manual            | Yes (PEL tracking)
Delivery semantic   | At-most-once | At-least-once     | At-least-once
Use case            | Notifications | Job queue         | Event sourcing, broker
```

---

## XADD, XREAD əsasları

```
# XADD stream-name id field value [field value ...]
XADD orders * customer_id 42 amount 100
# * → auto-generate ID: <millis>-<seq>
# Result: 1700000000000-0

XADD orders * customer_id 43 amount 250
# 1700000000001-0

XADD orders * customer_id 42 amount 50
# 1700000000002-0

# XLEN — stream uzunluğu
XLEN orders
# (integer) 3

# XRANGE — bir intervaldakı entry-ləri al
XRANGE orders - +    # bütün
XRANGE orders 1700000000000 1700000000001   # specific range
XRANGE orders - + COUNT 10                  # ilk 10

# XREVRANGE — tərs sırada
XREVRANGE orders + - COUNT 1   # son entry

# XREAD — stream-dən consume (offset-based)
XREAD COUNT 10 STREAMS orders 0
# Stream "orders"-dan ID > 0 olan ilk 10 entry

XREAD COUNT 10 BLOCK 5000 STREAMS orders $
# $ → "yalnız mənim XREAD-dən sonra gələnlər"
# BLOCK 5000 → 5s gözlə yeni event üçün
```

---

## Consumer Groups

```
Consumer group — multiple worker eyni stream-i bölüşdürür (Kafka kimi).
Hər message yalnız BİR consumer-ə paylanır (group daxilində).

Architecture:
                      ┌─ Consumer A (group=workers, name=w1)
                      │
  Stream "orders" ────┼─ Consumer B (group=workers, name=w2)
                      │
                      └─ Consumer C (group=analytics, name=a1)  ← ayrı group, hamısını alır
  
  workers group: load balanced (round-robin)
  analytics group: ayrı offset, hamı eyni mesajı görür
```

```
# Group yarat
XGROUP CREATE orders workers $ MKSTREAM
# $ → mövcud entry-ləri skip et, yalnız yenidə başla
# 0 → stream başından başla (replay)
# MKSTREAM → stream yoxdursa yarat

# Read as consumer
XREADGROUP GROUP workers w1 COUNT 10 BLOCK 5000 STREAMS orders >
# > → "henüz heç bir consumer almamış mesajlar"
# COUNT 10 → max 10 message
# BLOCK 5000 → 5s gözlə

# Result:
# 1) "orders"
#    1) "1700000000000-0"
#       1) "customer_id" "42"
#       2) "amount" "100"

# Process the message...

# Acknowledge (mark as processed)
XACK orders workers 1700000000000-0
```

---

## Acknowledgement (XACK) və PEL

```
PEL = Pending Entries List
Hər consumer group-da pending message-lər siyahısı:
  - XREADGROUP ilə oxunan, AMMA hələ XACK edilməmiş

Workflow:
  1. Consumer XREADGROUP → message alır
  2. PEL-ə əlavə olunur (delivery_count = 1)
  3. Consumer process edir
  4. XACK çağırır → PEL-dən silinir
  
  Əgər XACK edilmir (consumer crash):
  5. Message PEL-də qalır
  6. XAUTOCLAIM (Redis 6.2+) ilə başqa consumer "alır"
  7. Yenidən process olunur (idempotent olmalıdır)

# PEL inspect
XPENDING orders workers
# (integer) 5     ← 5 pending message
# 1700000000000-0  ← min ID
# 1700000000004-0  ← max ID
# 1) 1) "w1"   2) "3"      ← consumer w1, 3 pending
# 2) 1) "w2"   2) "2"      ← consumer w2, 2 pending

# Detailed
XPENDING orders workers - + 10 w1
# Hər message: ID, consumer, idle_time, delivery_count

# Auto claim (consumer crashed)
XAUTOCLAIM orders workers w2 30000 0
# w2 → bütün w1-in 30s+ idle olan message-lərini özünə götür
```

---

## Trim & retention

```
# Stream sonsuz böyüyər → memory dolu
# Trim qaydaları:

# 1. Length-based
XADD orders MAXLEN 1000 * field value
# Stream max 1000 entry, köhnələr atılır

XADD orders MAXLEN ~ 1000 * field value
# ~ → approximate (faster, slightly more memory)

# 2. Time-based (Redis 6.2+)
XADD orders MINID 1700000000000 * field value
# Bu ID-dən köhnələr silinir

XTRIM orders MAXLEN 1000     # explicit trim
XTRIM orders MINID 1700000000000

# 3. Manual cleanup
XDEL orders 1700000000000-0  # specific ID

Production:
  - MAXLEN ~ 100000 hər saniyədə artma sayına görə qoy
  - Disk persistence (RDB/AOF) lazımdır data loss-a qarşı
```

---

## Replay & history

```
# Streams persistent — bütün mesajları replay etmək olar
# Yeni consumer group sıfırdan başlaya bilər:

XGROUP CREATE orders new-group 0
# 0 → stream başından, bütün history-ni oxu

# Time travel (audit, debug)
XRANGE orders 1700000000000 1700000010000
# Verilmiş zaman intervalında bütün event-lər

# Specific consumer offset reset
XGROUP SETID orders workers 0    # group offset 0-a
```

---

## Kafka-like patterns

```
Redis Streams "Kafka mini" kimi istifadə olunur:

  Pattern                    | Redis Streams
  ───────────────────────────────────────────────
  Topic                      | Stream key
  Partition                  | Bir stream = 1 partition (NO multi-partition)
  Consumer group             | XGROUP
  Offset commit              | XACK
  Replay                     | XREAD STREAMS xxx 0
  Retention                  | MAXLEN / MINID

Limitations vs Kafka:
  ✗ Partitioning yox (1 stream = 1 partition)
  ✗ Cluster (Redis Cluster ilə partial dəstək)
  ✗ Throughput Kafka-dan az (~100k msg/s vs Kafka 1M+)
  ✗ Long retention (TB-larla data) → Redis memory-də problem
  
Üstünlük:
  ✓ Sadə (Redis instance kifayət, ZooKeeper yox)
  ✓ Çox sürətli (in-memory)
  ✓ Aşağı latency (~ms)
  ✓ Mövcud Redis-i istifadə et — yeni infrastruktur yox
  ✓ Kiçik-orta workload-larda Kafka-dan daha əlçatan

Use case verdict:
  Streams: <1M msg/s, kiçik retention, sadə setup
  Kafka:   yüksək throughput, gigabyte retention, ZK ekosistem
```

---

## PHP nümunəsi

```php
<?php
// phpredis extension istifadə edirik
$redis = new \Redis();
$redis->connect('localhost', 6379);

// Producer
function publishOrder(\Redis $r, array $order): string
{
    return $r->xAdd('orders', '*', [
        'customer_id' => $order['customer_id'],
        'amount'      => $order['amount'],
        'currency'    => $order['currency'],
    ], 100000, true);   // MAXLEN ~ 100000
}

publishOrder($redis, [
    'customer_id' => 42,
    'amount'      => 100,
    'currency'    => 'USD',
]);

// Consumer setup
$redis->xGroup('CREATE', 'orders', 'order-processors', '$', true);
// MKSTREAM=true → stream yoxdursa yarat

// Consumer loop
function consumeOrders(\Redis $r, string $consumerName): void
{
    while (true) {
        try {
            $messages = $r->xReadGroup(
                'order-processors',
                $consumerName,
                ['orders' => '>'],  // > = yeni mesajlar
                10,                 // count
                5000                // block 5s
            );
            
            if (empty($messages)) {
                continue;  // timeout, yenidən cəhd
            }
            
            foreach ($messages['orders'] ?? [] as $id => $data) {
                try {
                    processOrder($data);
                    
                    // Acknowledge
                    $r->xAck('orders', 'order-processors', [$id]);
                } catch (\Throwable $e) {
                    error_log("Failed to process $id: " . $e->getMessage());
                    // No XACK → message PEL-də qalır, retry mümkündür
                }
            }
        } catch (\Throwable $e) {
            error_log('Consumer error: ' . $e->getMessage());
            sleep(1);
        }
    }
}

// Run consumer
$workerName = 'worker-' . gethostname() . '-' . getmypid();
consumeOrders($redis, $workerName);
```

```php
<?php
// Crashed consumer cleanup — XAUTOCLAIM
function reclaimStaleMessages(\Redis $r): void
{
    $start = '0-0';
    
    do {
        $result = $r->rawCommand(
            'XAUTOCLAIM',
            'orders',
            'order-processors',
            'reclaim-worker',
            60000,           // 60s idle threshold
            $start,
            'COUNT', 100
        );
        
        // Result: [next_id, [reclaimed_messages]]
        $start = $result[0];
        $reclaimed = $result[1] ?? [];
        
        foreach ($reclaimed as $msg) {
            // Reprocess
        }
    } while ($start !== '0-0');
}

// Run periodically (cron, scheduler)
```

---

## Performance & limitations

```
Throughput (single Redis node, c5.2xlarge):
  Streams XADD:    ~150k op/s
  Streams XREAD:   ~100k op/s (per consumer)
  Consumer group:  ~80k msg/s aggregated (10 worker)

Memory cost:
  Hər entry ~80 bytes overhead + payload
  1M entries × 200 bytes payload = ~280 MB

Limitations:
  - 1 stream = 1 Redis key = 1 node (Redis Cluster-də)
  - Hash slot bir node-da
  - Partition yox → yatay scaling məhdud
  
Workaround for sharding:
  - Birdən çox stream key (orders:0, orders:1, orders:2)
  - Consumer client-side: hash(order_id) % shard_count
  - "Manual partitioning"

Best practices:
  ✓ MAXLEN istifadə et (memory unbounded olmasın)
  ✓ AOF persistence ON (data loss qarşı)
  ✓ XAUTOCLAIM cron (crashed consumer cleanup)
  ✓ Idempotent consumer (retry safe)
  ✓ DLQ pattern (max retry sonra ayrı stream-ə)
```

---

## İntervyu Sualları

- Redis Stream ilə Pub/Sub arasındakı fərq?
- Stream-də Consumer Group nəyə xidmət edir?
- PEL (Pending Entries List) nədir?
- XACK olmazsa nə baş verir?
- XAUTOCLAIM hansı problemi həll edir?
- Stream entry ID format necədir?
- MAXLEN trim nə vaxt istifadə olunur?
- Redis Streams Kafka-dan nə vaxt üstündür/aşağıdır?
- Stream replay necə işləyir?
- Stream-i Redis Cluster-də necə partition etmək olar?
- Idempotent consumer niyə vacibdir?
- DLQ pattern Streams-da necə implementasiya olunur?
