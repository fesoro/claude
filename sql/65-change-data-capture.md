# Change Data Capture (CDC)

> **Seviyye:** Advanced ⭐⭐⭐

## CDC Nedir?

Change Data Capture - database-deki deyisiklikleri **real-time** olaraq tutur ve diger system-lere oturur. `INSERT`, `UPDATE`, `DELETE` emeliyyatlarini izleyir.

**Niye lazimdir?**
- Microservice-ler arasi data sync
- Real-time analytics/reporting
- Search index yenilemesi (Elasticsearch)
- Cache invalidation
- Audit log

## CDC Yanasmalar

### 1. Trigger-Based CDC

Database trigger-leri ile deyisiklikleri ayri table-a yazir.

```sql
-- Changelog table
CREATE TABLE order_changelog (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    operation ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_data JSON,
    new_data JSON,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trigger
DELIMITER //
CREATE TRIGGER orders_after_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    INSERT INTO order_changelog (order_id, operation, old_data, new_data)
    VALUES (
        NEW.id,
        'UPDATE',
        JSON_OBJECT('status', OLD.status, 'total', OLD.total),
        JSON_OBJECT('status', NEW.status, 'total', NEW.total)
    );
END //
DELIMITER ;
```

**Problemler:** Performance overhead, trigger maintenance, database-e asilidir.

### 2. Query-Based CDC (Polling)

Periyodik olaraq `updated_at` column-a baxaraq deyisiklikleri tapir.

```php
// Laravel - Polling ile CDC
class PollChangesCommand extends Command
{
    protected $signature = 'cdc:poll';

    public function handle()
    {
        $lastCheck = Cache::get('cdc:last_check', now()->subMinute());

        $changedOrders = Order::where('updated_at', '>', $lastCheck)->get();

        foreach ($changedOrders as $order) {
            // Deyisikligi diger servise gonder
            event(new OrderChanged($order));
        }

        Cache::put('cdc:last_check', now());
    }
}
```

```sql
-- Polling ucun index
CREATE INDEX idx_orders_updated_at ON orders (updated_at);
```

**Problemler:** DELETE-leri tutmur (soft delete lazimdir), real-time deyil, `updated_at` mutleq olmalidir.

### 3. Log-Based CDC (Binlog / WAL)

Database-in **transaction log**-unu oxuyaraq deyisiklikleri tutur. En etibarlı yanasmadır.

**MySQL:** Binary Log (Binlog)
**PostgreSQL:** Write-Ahead Log (WAL)

```sql
-- MySQL: Binlog-u yoxla
SHOW VARIABLES LIKE 'log_bin';           -- ON olmalidir
SHOW VARIABLES LIKE 'binlog_format';     -- ROW olmalidir

-- Binlog formatini deyis
SET GLOBAL binlog_format = 'ROW';

-- Binlog event-lerini gor
SHOW BINLOG EVENTS IN 'mysql-bin.000001' LIMIT 20;
```

```sql
-- PostgreSQL: WAL sazlamalari
-- postgresql.conf:
-- wal_level = logical        -- logical replication ucun
-- max_replication_slots = 4

-- Logical replication slot yarat
SELECT pg_create_logical_replication_slot('my_slot', 'pgoutput');

-- Deyisiklikleri oxu
SELECT * FROM pg_logical_slot_get_changes('my_slot', NULL, NULL);
```

## Debezium

Log-based CDC ucun en populyar aciq-menbe alat. Database binlog/WAL-i oxuyur ve Kafka-ya gonderir.

### Debezium Arxitekturasi

```
Database (Binlog/WAL) → Debezium Connector → Kafka → Consumer-lar
                                                       ├── Elasticsearch
                                                       ├── Cache (Redis)
                                                       ├── Analytics DB
                                                       └── Diger Servisler
```

### Debezium Docker ile Qurasdirilma

```yaml
# docker-compose.yml
version: '3'
services:
  zookeeper:
    image: confluentinc/cp-zookeeper:7.5.0
    environment:
      ZOOKEEPER_CLIENT_PORT: 2181

  kafka:
    image: confluentinc/cp-kafka:7.5.0
    depends_on:
      - zookeeper
    environment:
      KAFKA_BROKER_ID: 1
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181
      KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://kafka:9092

  debezium:
    image: debezium/connect:2.4
    depends_on:
      - kafka
    ports:
      - "8083:8083"
    environment:
      BOOTSTRAP_SERVERS: kafka:9092
      GROUP_ID: 1

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
    command: >
      --server-id=1
      --log-bin=mysql-bin
      --binlog-format=ROW
      --binlog-row-image=FULL
      --gtid-mode=ON
      --enforce-gtid-consistency=ON
```

### Debezium Connector Yaratmaq

```json
// POST http://localhost:8083/connectors
{
    "name": "orders-connector",
    "config": {
        "connector.class": "io.debezium.connector.mysql.MySqlConnector",
        "database.hostname": "mysql",
        "database.port": "3306",
        "database.user": "debezium",
        "database.password": "secret",
        "database.server.id": "1",
        "topic.prefix": "myapp",
        "database.include.list": "ecommerce",
        "table.include.list": "ecommerce.orders,ecommerce.payments",
        "schema.history.internal.kafka.bootstrap.servers": "kafka:9092",
        "schema.history.internal.kafka.topic": "schema-changes"
    }
}
```

### Debezium Event Formati

Kafka-ya gelen mesaj formati:

```json
{
    "before": {
        "id": 1,
        "status": "pending",
        "total": 150.00
    },
    "after": {
        "id": 1,
        "status": "paid",
        "total": 150.00
    },
    "source": {
        "connector": "mysql",
        "db": "ecommerce",
        "table": "orders",
        "ts_ms": 1704067200000
    },
    "op": "u",
    "ts_ms": 1704067200500
}
```

`op` field-leri: `c` = create, `u` = update, `d` = delete, `r` = read (snapshot)

### PHP ile Kafka Consumer

```php
// Debezium event-lerini consume eden worker
class CdcConsumer extends Command
{
    protected $signature = 'cdc:consume';

    public function handle()
    {
        $conf = new \RdKafka\Conf();
        $conf->set('group.id', 'order-search-sync');
        $conf->set('bootstrap.servers', 'kafka:9092');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe(['myapp.ecommerce.orders']);

        while (true) {
            $message = $consumer->consume(1000);

            if ($message->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
                $event = json_decode($message->payload, true);
                $this->processEvent($event);
            }
        }
    }

    private function processEvent(array $event): void
    {
        match ($event['op']) {
            'c', 'u' => $this->upsertToElasticsearch($event['after']),
            'd'      => $this->deleteFromElasticsearch($event['before']['id']),
            default  => null,
        };
    }

    private function upsertToElasticsearch(array $data): void
    {
        // Elasticsearch-e yaz
        $client = \Elastic\Elasticsearch\ClientBuilder::create()->build();
        $client->index([
            'index' => 'orders',
            'id' => $data['id'],
            'body' => $data,
        ]);
    }
}
```

## CDC Yanasmalar Muqayise

| Xususiyyet | Trigger | Polling | Log-Based |
|------------|---------|---------|-----------|
| **Real-time** | Beli | Xeyr | Beli |
| **Performance** | Yuksek overhead | Asagi | Minimal |
| **DELETE tutma** | Beli | Xeyr (soft delete lazim) | Beli |
| **Schema change** | Manual update | Yoxdur | Avtomatik |
| **Complexity** | Asagi | Asagi | Yuksek |
| **Production ucun** | Kicik proyektler | Sadece | Beli (Debezium) |

## CDC Use Case-leri

### 1. Search Index Sync

```
Orders table → CDC → Elasticsearch Index
```

### 2. Cache Invalidation

```
Products table → CDC → Redis cache key delete
```

### 3. Cross-Service Data Sync

```
User Service DB → CDC → Order Service (user info copy)
```

### 4. Real-time Analytics

```
Orders table → CDC → Analytics DB (aggregated data)
```

## Interview Suallari

1. **CDC nedir ve niye lazimdir?**
   - Database deyisikliklerini real-time tutur ve diger system-lere oturur. Microservice data sync, search index update, audit log ucun istifade olunur.

2. **Log-based CDC-nin trigger-based-den ustunluyu nedir?**
   - Performance overhead yoxdur (database-e elave yuk qoymur), DELETE-leri tutur, schema change-leri avtomatik handle edir.

3. **Debezium nedir?**
   - Aciq-menbe CDC platformasi. Database binlog/WAL oxuyur, Kafka-ya event gonderir. MySQL, PostgreSQL, MongoDB destekleyir.

4. **CDC ile event sourcing arasinda ferq?**
   - CDC: Movcud database-den deyisiklikleri capture edir. Event Sourcing: Her deyisiklik event olaraq saxlanilir ve state event-lerden qurulur.

5. **Outbox Pattern ile CDC nece islenir?**
   - Outbox table-a yazilan event-ler Debezium ile capture olunur ve Kafka-ya gonderilir. At-least-once delivery temin olunur.
