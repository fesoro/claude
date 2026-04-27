# Message Queues (Lead ⭐⭐⭐⭐)

## İcmal
Message queue-lar, sistemin komponentlərini asinxron olaraq əlaqələndirən middleware-dir. Producer mesajı göndərir, Consumer isə öz sürətinə görə emal edir. Bu decoupling, sistemin hissələrini müstəqil scale etmək, fault tolerance artırmaq və spike-ları absorb etmək imkanı verir. Kafka, RabbitMQ, SQS — hər birinin fərqli güclü tərəfi var.

## Niyə Vacibdir
Netflix, Uber, LinkedIn, Airbnb — hər böyük sistem real-time event processing üçün message queue istifadə edir. Lead mühəndis yalnız "Kafka istifadə edək" demir — hansı queue seçiminin niyə uyğun olduğunu, delivery guarantee-lərini, consumer group-ları, exactly-once semantics-i izah edə bilir. Bu mövzu sistem dizaynının "async" hissəsini tam anlamaq üçün vacibdir.

## Əsas Anlayışlar

### 1. Message Queue vs Event Streaming

**Traditional Message Queue (RabbitMQ, SQS)**
```
Producer → Queue → Consumer
- Mesaj bir dəfə consume edilir (point-to-point)
- Consume olunduqdan sonra silinir
- Task distribution
- Use case: Email sending, order processing, job queue
```

**Event Streaming (Kafka, Kinesis)**
```
Producer → Topic (Partition) → Consumer Group A
                              → Consumer Group B
- Mesaj silinmir (retention period)
- Fərqli consumer group-lar eyni mesajı oxuya bilər
- Event sourcing, audit log
- Use case: Analytics, CDC, event sourcing, real-time pipelines
```

### 2. Kafka Arxitekturası

**Əsas komponentlər:**
```
Producer → Broker Cluster → Consumer Group
                │
           ZooKeeper / KRaft (metadata)

Broker Cluster:
  Broker 1 (Leader for Partition 0, 2)
  Broker 2 (Leader for Partition 1, Follower for 0, 2)
  Broker 3 (Follower for 1)

Topic: orders
  Partition 0: [msg1, msg4, msg7] → Leader: Broker1
  Partition 1: [msg2, msg5, msg8] → Leader: Broker2
  Partition 2: [msg3, msg6, msg9] → Leader: Broker1
```

**Partition key — kritik seçim:**
```
orders_topic, partition_key = customer_id
→ Eyni customer-in sifarişləri ardıcıl emal olunur
→ Fərqli customer-lər paralel emal olunur
```

### 3. Consumer Groups
```
Topic: payments (3 partitions)
Consumer Group A (payment-processor):
  Consumer 1 → Partition 0
  Consumer 2 → Partition 1
  Consumer 3 → Partition 2
  → Hər partition 1 consumer tərəfindən emal olunur
  → Paralel processing

Consumer Group B (audit-logger):
  Consumer 1 → Partition 0, 1, 2 (digər group müstəqil)
  → Eyni mesajları ayrı məqsəd üçün emal edir
```

**Qayda:** Consumer sayı partition sayından çox ola bilməz (artıq consumer-lər idle qalır).

### 4. Delivery Guarantees

**At-most-once (hərdən itirilə bilər)**
```
Producer fire-and-forget
Consumer mesajı almadan ack göndərir
Crash olsa mesaj itirilir
Use case: Metrics, logs (itirilsə problem deyil)
```

**At-least-once (duplikat ola bilər)**
```
Consumer mesajı emal edir, sonra ack
Emal olundu, ack göndərilmədi → mesaj yenidən gəlir
Idempotent consumer lazımdır
Use case: Email (deduplicate ilə), orders (idempotency key)
```

**Exactly-once (nə itirilir, nə duplikat)**
```
Kafka Transactions + Idempotent Producer
- Producer: enable.idempotence=true
- Transaction: BEGIN → produce → commit
- Consumer: read_committed isolation
Baha, latency artır
Use case: Financial transfers, inventory updates
```

### 5. RabbitMQ vs Kafka vs SQS

| Feature | RabbitMQ | Kafka | Amazon SQS |
|---------|----------|-------|------------|
| Model | Push | Pull | Pull |
| Retention | Until consumed | Log-based (7 day+) | 1-14 days |
| Throughput | ~50K/sec | ~1M+/sec | ~3K/sec (standard) |
| Ordering | Per-queue | Per-partition | FIFO queue |
| Consumer groups | Exchange/Routing | Consumer Groups | No (each msg to 1) |
| Replay | No | Yes (offset reset) | No |
| Setup | Self-hosted | Self-hosted/Confluent | Managed (serverless) |

**Seç Kafka əgər:**
- High throughput (>100K msg/sec)
- Event replay lazımdır
- Multiple consumer groups
- Event sourcing / CDC

**Seç RabbitMQ əgər:**
- Complex routing (topic exchange, fanout)
- Priority queue
- Per-message TTL
- Low latency task queue

**Seç SQS əgər:**
- AWS ecosystem
- Serverless, managed
- Simple task queue
- FIFO guarantee lazımdır

### 6. Dead Letter Queue (DLQ)
```
Normal flow:
Queue → Consumer → Success → ACK

Failure flow:
Queue → Consumer → Fail → Retry (3x) → DLQ

DLQ:
- Xətalı mesajları saxlayır
- Manual investigation + reprocess
- Alert trigger
- Poison pill mesajları bloklamamır
```

### 7. Message Ordering
**Problem:** Distributed queue-da order garantiya edilmir.

**Həll 1: Single partition (Kafka)**
- Bütün mesajlar tək partition → tam ardıcıllıq
- Throughput limiti var (1 consumer per partition)

**Həll 2: SQS FIFO queue**
- MessageGroupId ilə partition benzəri
- Group daxilinde FIFO

**Həll 3: Sequence number**
- Producer mesaja sequence number əlavə edir
- Consumer sort edib emal edir

**Həll 4: Application-level ordering**
- "Order event-ləri order_id ilə partition et"
- Eyni order-in event-ləri həmişə eyni partition-da

### 8. Backpressure
Consumer, producer-dan daha yavaş işlədikdə:
```
Queue dolar → Producer block olur (pressure back)
```

**Kafka-da backpressure:**
- Consumer lag monitoru: lag artırsa consumer-lər yetişmir
- Həll: Consumer sayını artır (partition sayına qədər)
- Həll: Processing-i optimize et

**RabbitMQ-da backpressure:**
- Queue size limit: x-max-length
- Publisher confirm ilə flow control

### 9. Kafka Topic Design
```
topics/
  orders.created       (new orders)
  orders.confirmed     (payment success)
  orders.shipped       (shipped)
  orders.delivered     (delivered)
  payments.processed   (payment events)
  inventory.updated    (stock changes)

Vs. Single topic:
  events               (all events, type field)
  → Messy, consumer filter etməli, throughput inefficient
```

**Topic naming convention:**
`{domain}.{entity}.{event_type}` → `orders.shipment.dispatched`

### 10. Kafka Partitions — Sayı Necə Seçilir
```
Target throughput: 1M messages/sec
Per-partition throughput: 10MB/sec (producers)
Message size: 1KB → 10K msg/sec per partition
Partitions needed: 1M / 10K = 100 partitions

Replication:
replication.factor = 3 (tolerate 2 broker failure)
min.insync.replicas = 2 (acks=all + min.insync=2)
```

**Qayda:** Partition sayını azaltmaq çətindir — artırmaq asandır. Konservativ başla, böyüt.

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Async pattern-in niyə lazım olduğunu əsaslandır (spike, decoupling, reliability)
2. Queue vs Streaming — fərqi izah et
3. Delivery guarantee seçimini əsaslandır
4. Consumer group dizaynını izah et
5. DLQ və monitoring haqqında danış

### Ümumi Namizəd Səhvləri
- "Kafka əlavə edək" demək, niyə Kafka-nı izah etməmək
- Exactly-once-un baha olduğunu unutmaq
- Partition sayı ilə consumer sayı əlaqəsini bilməmək
- DLQ haqqında danışmamaq
- Message ordering problemini göz ardı etmək
- Consumer lag monitoring-i qeyd etməmək

### Senior vs Architect Fərği
**Senior**: Queue/stream seçimini edir, consumer groups konfiqurasiya edir, delivery guarantees müəyyən edir, DLQ qurur.

**Architect**: Event-driven arxitektura üçün topic taxonomy dizayn edir, Kafka cluster sizing (broker sayı, partition sayı, replication factor) planlaşdırır, exactly-once semantics-in performance cost-unu qiymətləndirir, schema registry (Avro/Protobuf) tətbiq edir, retention policy-ni compliance ilə uyğunlaşdırır.

## Nümunələr

### Tipik Interview Sualı
"Design the order processing pipeline for an e-commerce platform handling Black Friday: 10K orders/sec."

### Güclü Cavab
```
Order processing pipeline:

Step 1: Order placement (sync)
Client → API Gateway → Order Service → orders.created topic
Response: 200 Accepted, order_id returned immediately

Step 2: Async pipeline (Kafka)
orders.created topic (20 partitions, key=customer_id)
  │
  ├── Payment Service (Consumer Group: payment-svc)
  │     Payment successful → orders.confirmed
  │     Payment failed    → orders.failed
  │
  ├── Inventory Service (Consumer Group: inventory-svc)
  │     Reserve stock → inventory.reserved
  │
  └── Notification Service (Consumer Group: notif-svc)
        Email/SMS confirmation

Delivery Guarantee:
- orders.created: at-least-once (payment idempotent)
- inventory.reserved: exactly-once (stock can't go negative)
- notifications: at-least-once (duplicate email acceptable)

Black Friday spike:
- Normal: 500 orders/sec
- Peak: 10K orders/sec (20x spike)
- Kafka absorbs spike in topic
- Consumer-lər öz sürətinə görə emal edir
- AutoScaling consumer pods (Kubernetes KEDA)

DLQ:
- orders.created.dlq (max 3 retry)
- Alert: PagerDuty on DLQ message
- Manual replay after fix

Monitoring:
- Consumer lag per partition
- DLQ message count
- Processing latency (p99)
- Error rate
```

### Kafka Topic Flow
```
Client ──► [Order Service] ──► orders.created
                                    │
           ┌────────────────────────┤
           │                        │
   [Payment Svc]             [Inventory Svc]
        │                           │
orders.confirmed              inventory.reserved
        │
[Notification Svc]
        │
   (email/SMS sent)
```

## Praktik Tapşırıqlar
- Kafka producer + consumer implement edin (Java/Python)
- Consumer group rebalancing-i simulate edin
- DLQ flow qurun: fail → retry → DLQ → alert
- Consumer lag monitoring Grafana dashboard
- Exactly-once Kafka transaction implement edin

## Əlaqəli Mövzular
- [18-event-driven-architecture.md] — Event-driven patterns
- [21-backpressure.md] — Backpressure management
- [13-idempotency-design.md] — Idempotent consumers
- [25-outbox-pattern.md] — Reliable event publishing
- [17-distributed-transactions.md] — Saga pattern with queues
