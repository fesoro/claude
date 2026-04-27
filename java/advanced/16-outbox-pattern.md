# Outbox Pattern — Java ilə Geniş İzah (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
1. [Outbox Pattern nədir?](#outbox-pattern-nədir)
2. [Əl ilə tətbiq](#əl-ilə-tətbiq)
3. [Transactional Outbox ilə Kafka](#transactional-outbox-ilə-kafka)
4. [Debezium (CDC) ilə Outbox](#debezium-cdc-ilə-outbox)
5. [İdempotent Consumer](#idempotent-consumer)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Outbox Pattern nədir?

**Outbox Pattern** — DB write və message broker-ə göndərmə əməliyyatlarını atomik etmək üçün pattern.

```
Problem:
  @Transactional
  public void createOrder(Order order) {
    orderRepository.save(order);      // DB OK
    kafkaTemplate.send("order-created", order); // Kafka FAIL → mesaj itdi!
    // Ya da:
    kafkaTemplate.send(...) // OK
    orderRepository.save(order); // DB FAIL → duplicate mesaj!
  }

Outbox həll:
  @Transactional
  public void createOrder(Order order) {
    orderRepository.save(order);              // DB OK
    outboxRepository.save(outboxMessage);     // Eyni DB, eyni TX → atomik!
    // Kafka-ya deyil, DB-yə yaz
  }
  
  // Ayrıca process/scheduler:
  // DB outbox-dan oxu → Kafka-ya göndər → outbox-dan sil
```

---

## Əl ilə tətbiq

```java
// Outbox Entity
@Entity
@Table(name = "outbox_messages")
public class OutboxMessage {

    @Id
    private String id;           // UUID

    private String aggregateId;  // Order ID
    private String aggregateType; // "Order"
    private String eventType;    // "OrderCreated"
    private String topic;        // Kafka topic

    @Column(columnDefinition = "TEXT")
    private String payload;      // JSON

    private String status;       // PENDING, SENT, FAILED

    private int retryCount;
    private Instant createdAt;
    private Instant processedAt;
    private String errorMessage;

    @Version
    private Long version;        // Concurrent processing qarşısı
}

// Order Service — DB-yə yaz
@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final OutboxRepository outboxRepository;
    private final ObjectMapper objectMapper;

    @Transactional
    public Order createOrder(OrderRequest request) {
        // 1. Domain əməliyyatı
        Order order = Order.create(request.customerId(), request.items());
        Order saved = orderRepository.save(order);

        // 2. Outbox-a yaz (eyni transaction!)
        OutboxMessage outboxMessage = OutboxMessage.builder()
            .id(UUID.randomUUID().toString())
            .aggregateId(saved.getId().toString())
            .aggregateType("Order")
            .eventType("OrderCreated")
            .topic("order-events")
            .payload(objectMapper.writeValueAsString(new OrderCreatedEvent(saved)))
            .status("PENDING")
            .createdAt(Instant.now())
            .build();

        outboxRepository.save(outboxMessage);
        // Kafka yox — sadəcə DB

        return saved;
    }
}

// Outbox Poller — DB-dən oxu, Kafka-ya göndər
@Component
public class OutboxPoller {

    private final OutboxRepository outboxRepository;
    private final KafkaTemplate<String, String> kafkaTemplate;

    @Scheduled(fixedDelay = 1000) // Hər saniyə
    @Transactional
    public void processOutbox() {
        List<OutboxMessage> pending = outboxRepository
            .findTop100ByStatusOrderByCreatedAtAsc("PENDING");

        for (OutboxMessage message : pending) {
            try {
                // Kafka-ya göndər
                kafkaTemplate.send(
                    message.getTopic(),
                    message.getAggregateId(),
                    message.getPayload()
                ).get(5, TimeUnit.SECONDS); // Sync wait

                message.setStatus("SENT");
                message.setProcessedAt(Instant.now());

            } catch (Exception e) {
                message.setStatus("FAILED");
                message.setRetryCount(message.getRetryCount() + 1);
                message.setErrorMessage(e.getMessage());

                if (message.getRetryCount() >= 5) {
                    message.setStatus("DEAD"); // Manual review lazımdır
                }
            }

            outboxRepository.save(message);
        }
    }
}

// Concurrent polling — multiple instance-da lock
@Scheduled(fixedDelay = 1000)
public void processOutboxWithLock() {
    // SKIP LOCKED — başqa instance-ın işlədiyini keç
    List<OutboxMessage> pending = outboxRepository
        .findPendingWithLock(PageRequest.of(0, 100));

    // ...
}

// Repository
public interface OutboxRepository extends JpaRepository<OutboxMessage, String> {

    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @QueryHints(@QueryHint(name = "javax.persistence.lock.timeout", value = "0"))
    @Query("""
        SELECT o FROM OutboxMessage o
        WHERE o.status = 'PENDING'
        ORDER BY o.createdAt ASC
    """)
    List<OutboxMessage> findPendingWithLock(Pageable pageable);
}
```

---

## Transactional Outbox ilə Kafka

```java
// Kafka Transaction ilə (exactly-once semantics)
@Service
public class TransactionalOutboxProcessor {

    private final OutboxRepository outboxRepository;
    private final KafkaTemplate<String, String> kafkaTemplate;
    private final PlatformTransactionManager transactionManager;

    @Scheduled(fixedDelay = 500)
    public void processWithKafkaTransaction() {
        List<OutboxMessage> messages = outboxRepository.findTop50Pending();

        if (messages.isEmpty()) return;

        try {
            // Kafka transaction
            kafkaTemplate.executeInTransaction(operations -> {
                messages.forEach(msg -> {
                    operations.send(msg.getTopic(), msg.getAggregateId(), msg.getPayload());
                });
                return true;
            });

            // DB-də SENT olaraq işarələ
            messages.forEach(msg -> {
                msg.setStatus("SENT");
                msg.setProcessedAt(Instant.now());
            });
            outboxRepository.saveAll(messages);

        } catch (Exception e) {
            log.error("Outbox processing failed", e);
            // Retry — növbəti dövrdə yenidən cəhd ediləcək
        }
    }
}
```

---

## Debezium (CDC) ilə Outbox

**Debezium** — DB transaction log-unu (WAL/binlog) oxuyub Kafka-ya event göndərən CDC tool-u. Polling lazım deyil.

```yaml
# docker-compose.yml
debezium:
  image: debezium/connect:2.4
  environment:
    BOOTSTRAP_SERVERS: kafka:9092
    GROUP_ID: 1
    CONFIG_STORAGE_TOPIC: connect_configs
    OFFSET_STORAGE_TOPIC: connect_offsets
```

```json
// Debezium Connector konfiqurasiyası (PostgreSQL)
{
  "name": "outbox-connector",
  "config": {
    "connector.class": "io.debezium.connector.postgresql.PostgresConnector",
    "database.hostname": "postgres",
    "database.port": "5432",
    "database.user": "postgres",
    "database.password": "password",
    "database.dbname": "mydb",
    "table.include.list": "public.outbox_messages",
    "transforms": "outbox",
    "transforms.outbox.type": "io.debezium.transforms.outbox.EventRouter",
    "transforms.outbox.table.field.event.type": "event_type",
    "transforms.outbox.route.by.field": "topic"
  }
}
```

```java
// Debezium ilə outbox — sadə POJO, polling yoxdur
@Service
public class OrderServiceWithCdc {

    @Transactional
    public Order createOrder(OrderRequest request) {
        Order saved = orderRepository.save(Order.create(request));

        // Outbox-a yaz — Debezium avtomatik Kafka-ya göndərəcək
        outboxRepository.save(OutboxMessage.builder()
            .aggregateId(saved.getId().toString())
            .aggregateType("Order")
            .eventType("OrderCreated")
            .topic("order-events")
            .payload(serialize(saved))
            .build());

        return saved;
    }
}
// Debezium PostgreSQL WAL-ı izləyir → INSERT → Kafka-ya göndərir
// Polling yoxdur, low latency (<100ms)
```

---

## İdempotent Consumer

```java
// Consumer idempotent olmalıdır — eyni mesaj bir neçə dəfə gələ bilər

@Entity
@Table(name = "processed_messages")
public class ProcessedMessage {
    @Id
    private String messageId;     // Kafka offset yaxud custom ID
    private String topic;
    private Instant processedAt;
}

@Component
public class IdempotentOrderConsumer {

    private final ProcessedMessageRepository processedRepository;
    private final OrderService orderService;

    @KafkaListener(topics = "order-events")
    @Transactional
    public void handleOrderEvent(ConsumerRecord<String, String> record,
                                  Acknowledgment ack) {
        String messageId = record.topic() + "-" + record.partition() + "-" + record.offset();

        // Artıq işlenmiş mesaj?
        if (processedRepository.existsById(messageId)) {
            log.warn("Duplicate mesaj keç: {}", messageId);
            ack.acknowledge();
            return;
        }

        try {
            // Emal et
            OrderCreatedEvent event = deserialize(record.value());
            orderService.processCreatedOrder(event);

            // İşlənmiş kimi işarələ
            processedRepository.save(new ProcessedMessage(messageId, record.topic()));
            ack.acknowledge();

        } catch (Exception e) {
            log.error("Mesaj emal xətası", e);
            // Acknowledge etmə — DLT-yə keçəcək (retry sonra)
        }
    }
}
```

---

## İntervyu Sualları

### 1. Outbox Pattern hansı problemi həll edir?
**Cavab:** DB yazma ilə Kafka-ya göndərməni atomik etmək problemi. Normal halda DB commit olub Kafka fail olarsa mesaj itirilir; ya da Kafka OK amma DB rollback olarsa duplicate mesaj olur. Outbox: hər ikisini eyni DB transaction-da saxlayır, ayrıca process göndərir — atomicity zəmanəti.

### 2. Debezium CDC-nin Polling-dən fərqi?
**Cavab:** Polling — scheduler hər N saniyədə DB-ni sorğulayır, gecikmə var, DB yükü yaranır. CDC (Debezium) — DB transaction log-unu (WAL/binlog) real-time oxuyur, millisaniyə gecikmə, DB yükü yoxdur. Debezium daha etibarlı, amma əlavə infrastructure tələb edir.

### 3. İdempotent consumer niyə lazımdır?
**Cavab:** Kafka at-least-once delivery zəmanəti verir — mesaj bir neçə dəfə gələ bilər (network retry, consumer restart). Outbox-dan göndərilən mesaj da retry edilə bilər. İdempotent consumer eyni mesajı bir neçə dəfə işlətdikdə eyni nəticəni verir. `processedMessages` table-ı bu zəmanəti təmin edir.

### 4. Outbox-da "DEAD" status nə deməkdir?
**Cavab:** 5 retry-dan sonra avtomatik göndərilə bilməyən mesaj. Manual müdaxilə tələb edir (alert, manual replay, bug fix). Dead Letter Table kimi işləyir — itirilmir, amma avtomatik process olunmur.

### 5. Microservice mühitdə SKIP LOCKED nə üçündür?
**Cavab:** Bir neçə Outbox processor instance eyni anda çalışırsa eyni mesajı parallel götürə bilər. `SELECT ... FOR UPDATE SKIP LOCKED` — başqa transaction-ın lock etdiyi row-ları keçir, duplicate processing olmur. PostgreSQL bu feature-ı dəstəkləyir.

*Son yenilənmə: 2026-04-10*
