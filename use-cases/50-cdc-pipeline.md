# CDC Pipeline (Lead)

## Problem
- MySQL-də `products` cədvəli (1M row, 100 update/sec)
- Elasticsearch search index real-time sync
- Cache invalidation Redis-də
- Data warehouse (BigQuery) günlük export

Klassik yanaşma: hər model save-də manual Elasticsearch index, manual cache forget. Tight coupling, fail mode-lar çox.

---

## Həll: Debezium CDC + Kafka

```
                      ┌─ binlog ─→ Debezium ─→ Kafka ─→ ES indexer
MySQL (master) ───────┤
                      ├─ binlog ─→ Debezium ─→ Kafka ─→ Cache invalidator
                      │
                      └─ binlog ─→ Debezium ─→ Kafka ─→ BigQuery sink
```

---

## 1. MySQL setup

```sql
-- /etc/mysql/my.cnf
[mysqld]
log-bin = mysql-bin
binlog_format = ROW
binlog_row_image = FULL
binlog_expire_logs_seconds = 604800   -- 7 gün
server-id = 1
gtid_mode = ON
enforce_gtid_consistency = ON

-- Debezium üçün user
CREATE USER 'debezium'@'%' IDENTIFIED BY 'dbz-pass';
GRANT SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT
    ON *.* TO 'debezium'@'%';
FLUSH PRIVILEGES;
```

---

## 2. Kafka Connect + Debezium

```yaml
# docker-compose.yml
services:
  kafka:
    image: confluentinc/cp-kafka:latest
    environment:
      KAFKA_NODE_ID: 1
      KAFKA_PROCESS_ROLES: broker,controller
      KAFKA_CONTROLLER_QUORUM_VOTERS: "1@kafka:9093"
  
  kafka-connect:
    image: debezium/connect:latest
    environment:
      BOOTSTRAP_SERVERS: kafka:9092
      GROUP_ID: 1
      CONFIG_STORAGE_TOPIC: connect_configs
      OFFSET_STORAGE_TOPIC: connect_offsets
    depends_on: [kafka]
```

```bash
# Connector deploy
curl -X POST http://kafka-connect:8083/connectors \
  -H "Content-Type: application/json" \
  -d '{
    "name": "products-cdc",
    "config": {
      "connector.class": "io.debezium.connector.mysql.MySqlConnector",
      "tasks.max": "1",
      "database.hostname": "mysql.internal",
      "database.port": "3306",
      "database.user": "debezium",
      "database.password": "dbz-pass",
      "database.server.id": "184054",
      "topic.prefix": "shop",
      "database.include.list": "shop",
      "table.include.list": "shop.products",
      "schema.history.internal.kafka.bootstrap.servers": "kafka:9092",
      "schema.history.internal.kafka.topic": "schema-changes.shop",
      "snapshot.mode": "initial",
      "transforms": "unwrap",
      "transforms.unwrap.type": "io.debezium.transforms.ExtractNewRecordState",
      "transforms.unwrap.drop.tombstones": "false",
      "transforms.unwrap.delete.handling.mode": "rewrite"
    }
  }'

# Status check
curl http://kafka-connect:8083/connectors/products-cdc/status
```

---

## 3. PHP Consumer (Elasticsearch indexer)

```php
<?php
namespace App\Consumers;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Elastic\Elasticsearch\ClientBuilder;

class ProductElasticsearchSync
{
    private $kafkaContext;
    private $esClient;
    
    public function __construct()
    {
        $factory = new RdKafkaConnectionFactory([
            'global' => [
                'group.id'                => 'es-product-sync',
                'metadata.broker.list'    => env('KAFKA_BROKER'),
                'auto.offset.reset'       => 'earliest',
                'enable.auto.commit'      => 'false',
            ],
        ]);
        $this->kafkaContext = $factory->createContext();
        $this->esClient = ClientBuilder::create()->setHosts([env('ES_URL')])->build();
    }
    
    public function run(): void
    {
        $topic = $this->kafkaContext->createTopic('shop.shop.products');
        $consumer = $this->kafkaContext->createConsumer($topic);
        
        while (true) {
            $message = $consumer->receive(5000);   // 5s timeout
            if (!$message) continue;
            
            $event = json_decode($message->getBody(), true);
            
            try {
                $this->handleEvent($event);
                $consumer->acknowledge($message);   // commit offset
            } catch (\Throwable $e) {
                error_log("Failed to process: " . $e->getMessage());
                // Don't ack — Kafka will retry
                // After max retries, move to DLQ topic
            }
        }
    }
    
    private function handleEvent(array $event): void
    {
        $op = $event['__op'] ?? $event['op'] ?? null;
        $product = $event['after'] ?? $event;
        
        match ($op) {
            'c', 'r' => $this->index($product),     // create / read (snapshot)
            'u'      => $this->index($product),     // update
            'd'      => $this->delete($event['before']['id']),
            default  => null,
        };
    }
    
    private function index(array $product): void
    {
        $this->esClient->index([
            'index' => 'products',
            'id'    => $product['id'],
            'body'  => [
                'name'        => $product['name'],
                'description' => $product['description'],
                'price'       => (float) $product['price'],
                'category_id' => $product['category_id'],
                'in_stock'    => (bool) $product['in_stock'],
                'updated_at'  => $product['updated_at'],
            ],
        ]);
    }
    
    private function delete(int $id): void
    {
        try {
            $this->esClient->delete(['index' => 'products', 'id' => $id]);
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            if ($e->getCode() !== 404) throw $e;   // 404 = artıq silinib, OK
        }
    }
}

// CLI runner
$sync = new ProductElasticsearchSync();
$sync->run();
```

---

## 4. Cache invalidator consumer

```php
<?php
class ProductCacheInvalidator
{
    private $kafkaContext;
    private \Redis $redis;
    
    public function run(): void
    {
        // Same Kafka subscribe pattern
        // ...
        
        foreach ($messages as $msg) {
            $event = json_decode($msg->getBody(), true);
            $productId = $event['after']['id'] ?? $event['before']['id'];
            
            // Invalidate cache keys
            $this->redis->del([
                "product:$productId",
                "product:$productId:related",
                "category:" . ($event['after']['category_id'] ?? 0) . ":products",
            ]);
            
            // Tag-based invalidation
            $this->redis->sRem('cache:tag:product', "product:$productId");
        }
    }
}
```

---

## 5. Outbox pattern variant

```sql
-- Outbox cədvəli — Debezium CDC bu cədvəli oxuyur
CREATE TABLE outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    aggregate_id VARCHAR(255) NOT NULL,
    aggregate_type VARCHAR(255) NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);
```

```php
<?php
// Service-də outbox + business write
DB::transaction(function () use ($orderData) {
    $order = Order::create($orderData);
    
    DB::table('outbox_events')->insert([
        'aggregate_id'   => $order->id,
        'aggregate_type' => 'Order',
        'event_type'     => 'OrderCreated',
        'payload'        => json_encode([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'total' => $order->total,
        ]),
    ]);
});

// Atomic — order + event birgə commit.
// Debezium outbox_events cədvəlini Kafka-ya stream edir.
// Outbox event router transformation event_type-a görə düzgün topic-ə göndərir.
```

---

## 6. Monitoring

```yaml
# Kafka Connect metrics — Prometheus
# /metrics endpoint Kafka Connect-də (JMX exporter)

# Key metric-lər:
- connector_status (running/failed)
- source_lag_seconds (binlog gecikməsi)
- consumer_lag (Kafka consumer offset behind)
- error_count / restart_count
- bytes_in / bytes_out per topic

# Grafana alert
- connector down > 5 min → page
- source_lag > 60s → warning
- consumer_lag > 10000 message → warning
```

---

## 7. Failure scenarios

```
Scenario 1: Debezium crashed
  - Container restart
  - Last committed binlog position-dan davam edir (no data loss)

Scenario 2: Kafka down
  - Debezium produce edə bilmir
  - Binlog-dan oxumağı dayandırır (offset commit etmir)
  - Kafka qayıdanda davam edir
  - Risk: binlog retention bitsə (7 gün) — snapshot lazım

Scenario 3: Consumer crashed
  - Offset commit olunmayıb → restart-da yenidən oxuyur
  - At-least-once delivery — idempotent consumer lazım

Scenario 4: Schema dəyişikliyi (yeni column)
  - Debezium auto-detect (DDL event)
  - Consumer-də field yoxdur → null/default
  - ES mapping update etmək lazım

Scenario 5: Hard delete (compliance)
  - DELETE binlog-da var → event göndərilir
  - ES-dən silinir → cache invalidate
  - AMMA Kafka topic-də event qalır (retention boyu)
  - GDPR üçün: encryption key delete pattern və ya log compaction
```

---

## 8. Performance

```
Throughput:
  MySQL writes: 100/sec
  Debezium lag: ~50ms
  Kafka topic: 100 msg/sec (negligible)
  ES indexer: 100/sec (single consumer)
  
Scaling:
  Kafka topic partition: 4
  Consumer group: 4 worker → parallel processing
  Hər partition bir worker (key-based — same product ID həmişə eyni partition)
```
