# 77 — Spring Kafka Producer — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Kafka Producer nədir?](#kafka-producer-nədir)
2. [KafkaTemplate](#kafkatemplate)
3. [ProducerConfig](#producerconfig)
4. [Transaction support](#transaction-support)
5. [Error handling və retry](#error-handling-və-retry)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Kafka Producer nədir?

**Kafka Producer** — Kafka topic-lərinə mesaj göndərən komponent. Spring Kafka `KafkaTemplate` class-ı ilə producer əməliyyatlarını sadələşdirir.

```xml
<dependency>
    <groupId>org.springframework.kafka</groupId>
    <artifactId>spring-kafka</artifactId>
</dependency>
```

```yaml
# application.yml
spring:
  kafka:
    bootstrap-servers: localhost:9092
    producer:
      key-serializer: org.apache.kafka.common.serialization.StringSerializer
      value-serializer: org.springframework.kafka.support.serializer.JsonSerializer
      acks: all                    # Bütün replica-lar təsdiqləməlidir
      retries: 3                   # Xəta halında retry sayı
      batch-size: 16384            # Batch ölçüsü (bytes)
      linger-ms: 5                 # Batch toplamaq üçün gözləmə
      compression-type: snappy     # Sıxışdırma (none/gzip/snappy/lz4)
      properties:
        enable.idempotence: true   # Duplicate mesaj göndərmənin qarşısı
        max.in.flight.requests.per.connection: 5
```

---

## KafkaTemplate

```java
@Service
public class OrderEventProducer {

    private final KafkaTemplate<String, Object> kafkaTemplate;

    private static final String ORDER_TOPIC = "order-events";
    private static final String PAYMENT_TOPIC = "payment-events";

    // Sadə mesaj göndərmə
    public void sendOrderCreated(Order order) {
        kafkaTemplate.send(ORDER_TOPIC, order.getId().toString(), order);
        log.info("Order event göndərildi: {}", order.getId());
    }

    // Partition və timestamp ilə
    public void sendToSpecificPartition(Order order, int partition) {
        kafkaTemplate.send(ORDER_TOPIC, partition, null,
                          order.getId().toString(), order);
    }

    // Callback ilə (async)
    public void sendWithCallback(Order order) {
        CompletableFuture<SendResult<String, Object>> future =
            kafkaTemplate.send(ORDER_TOPIC, order.getId().toString(), order);

        future.whenComplete((result, ex) -> {
            if (ex != null) {
                log.error("Mesaj göndərilmədi: {}", ex.getMessage());
                // Retry yaxud dead letter queue
            } else {
                RecordMetadata metadata = result.getRecordMetadata();
                log.info("Mesaj göndərildi: topic={}, partition={}, offset={}",
                    metadata.topic(),
                    metadata.partition(),
                    metadata.offset());
            }
        });
    }

    // ProducerRecord ilə tam nəzarət
    public void sendWithHeaders(Order order) {
        ProducerRecord<String, Object> record = new ProducerRecord<>(
            ORDER_TOPIC,
            null,    // partition
            System.currentTimeMillis(), // timestamp
            order.getId().toString(),   // key
            order,                      // value
            List.of(
                new RecordHeader("source", "order-service".getBytes()),
                new RecordHeader("version", "1.0".getBytes()),
                new RecordHeader("correlationId",
                    UUID.randomUUID().toString().getBytes())
            )
        );

        kafkaTemplate.send(record);
    }
}
```

---

## ProducerConfig

```java
@Configuration
public class KafkaProducerConfig {

    @Value("${spring.kafka.bootstrap-servers}")
    private String bootstrapServers;

    @Bean
    public ProducerFactory<String, Object> producerFactory() {
        Map<String, Object> config = new HashMap<>();

        config.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, bootstrapServers);
        config.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG,
                   StringSerializer.class);
        config.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG,
                   JsonSerializer.class);

        // Güvenilirlik
        config.put(ProducerConfig.ACKS_CONFIG, "all");
        config.put(ProducerConfig.ENABLE_IDEMPOTENCE_CONFIG, true);
        config.put(ProducerConfig.RETRIES_CONFIG, Integer.MAX_VALUE);
        config.put(ProducerConfig.MAX_IN_FLIGHT_REQUESTS_PER_CONNECTION, 5);

        // Performans
        config.put(ProducerConfig.BATCH_SIZE_CONFIG, 32768);
        config.put(ProducerConfig.LINGER_MS_CONFIG, 10);
        config.put(ProducerConfig.COMPRESSION_TYPE_CONFIG, "snappy");
        config.put(ProducerConfig.BUFFER_MEMORY_CONFIG, 33554432);

        // JsonSerializer konfiqurasiyası
        config.put(JsonSerializer.ADD_TYPE_INFO_HEADERS, false);
        config.put(JsonSerializer.TYPE_MAPPINGS,
                   "order:com.example.event.OrderEvent");

        return new DefaultKafkaProducerFactory<>(config);
    }

    @Bean
    public KafkaTemplate<String, Object> kafkaTemplate() {
        KafkaTemplate<String, Object> template =
            new KafkaTemplate<>(producerFactory());

        // Default topic
        template.setDefaultTopic("default-topic");

        // Observation (Micrometer tracing)
        template.setObservationEnabled(true);

        return template;
    }
}
```

**Fərqli serializer-lər üçün multiple template:**
```java
@Configuration
public class MultipleKafkaTemplateConfig {

    // String producer
    @Bean("stringKafkaTemplate")
    public KafkaTemplate<String, String> stringKafkaTemplate() {
        Map<String, Object> config = new HashMap<>();
        config.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, bootstrapServers);
        config.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class);
        config.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class);

        return new KafkaTemplate<>(new DefaultKafkaProducerFactory<>(config));
    }

    // Avro producer
    @Bean("avroKafkaTemplate")
    public KafkaTemplate<String, GenericRecord> avroKafkaTemplate() {
        Map<String, Object> config = new HashMap<>();
        config.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, bootstrapServers);
        config.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class);
        config.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, KafkaAvroSerializer.class);
        config.put("schema.registry.url", schemaRegistryUrl);

        return new KafkaTemplate<>(new DefaultKafkaProducerFactory<>(config));
    }
}
```

---

## Transaction support

```java
@Configuration
public class KafkaTransactionConfig {

    @Bean
    public ProducerFactory<String, Object> transactionalProducerFactory() {
        DefaultKafkaProducerFactory<String, Object> factory =
            new DefaultKafkaProducerFactory<>(producerConfig());

        factory.setTransactionIdPrefix("order-tx-");
        return factory;
    }

    @Bean
    public KafkaTransactionManager<String, Object> kafkaTransactionManager(
            ProducerFactory<String, Object> producerFactory) {
        return new KafkaTransactionManager<>(producerFactory);
    }
}

@Service
public class TransactionalOrderService {

    private final KafkaTemplate<String, Object> kafkaTemplate;
    private final OrderRepository orderRepository;

    // DB və Kafka eyni transaction-da (exactly-once)
    @Transactional("kafkaTransactionManager")
    public void placeOrder(Order order) {
        // DB-yə yaz
        orderRepository.save(order);

        // Kafka-ya göndər — həm DB həm Kafka ya commit, ya rollback
        kafkaTemplate.send("order-events", order.getId().toString(), order);
        kafkaTemplate.send("inventory-events", order.getId().toString(),
                          new InventoryReserveEvent(order));
    }

    // ChainedTransactionManager (DB + Kafka birlikdə)
    @Transactional
    public void placeOrderWithDb(Order order) {
        orderRepository.save(order);

        // executeInTransaction — Kafka transactional context
        kafkaTemplate.executeInTransaction(operations -> {
            operations.send("order-events", order.getId().toString(), order);
            return true;
        });
    }
}
```

---

## Error handling və retry

```java
@Configuration
public class KafkaErrorHandlingConfig {

    @Bean
    public KafkaTemplate<String, Object> kafkaTemplate(
            ProducerFactory<String, Object> pf) {

        KafkaTemplate<String, Object> template = new KafkaTemplate<>(pf);

        // Global producer listener
        template.setProducerListener(new ProducerListener<>() {
            @Override
            public void onSuccess(ProducerRecord<String, Object> record,
                                  RecordMetadata metadata) {
                log.debug("Mesaj göndərildi: topic={}, offset={}",
                    metadata.topic(), metadata.offset());
            }

            @Override
            public void onError(ProducerRecord<String, Object> record,
                                RecordMetadata metadata,
                                Exception exception) {
                log.error("Mesaj göndərilmədi: key={}, error={}",
                    record.key(), exception.getMessage());
                // Alert, metric
            }
        });

        return template;
    }
}

// Outbox Pattern ilə reliable messaging
@Service
public class ReliableOrderProducer {

    private final OutboxRepository outboxRepository;
    private final KafkaTemplate<String, Object> kafkaTemplate;

    @Transactional
    public void sendOrderEvent(Order order) {
        // DB-yə outbox-a yaz (DB transaction ilə)
        OutboxMessage message = new OutboxMessage(
            "order-events",
            order.getId().toString(),
            objectMapper.writeValueAsString(order)
        );
        outboxRepository.save(message);
        // Kafka failure olsa belə, DB-də var
    }

    // Scheduler ilə outbox-u Kafka-ya göndər
    @Scheduled(fixedDelay = 5000)
    public void processOutbox() {
        List<OutboxMessage> pending = outboxRepository.findPending();
        for (OutboxMessage message : pending) {
            try {
                kafkaTemplate.send(message.getTopic(),
                                  message.getKey(),
                                  message.getPayload())
                    .get(5, TimeUnit.SECONDS); // Sync wait
                message.markAsSent();
                outboxRepository.save(message);
            } catch (Exception e) {
                log.error("Outbox mesaj göndərilmədi: {}", message.getId(), e);
            }
        }
    }
}
```

---

## İntervyu Sualları

### 1. acks=all nə deməkdir?
**Cavab:** Producer mesaj göndərdikdə ISR (In-Sync Replicas) dəstəsindəki bütün replica-ların mesajı qeyd etdiyini təsdiqləməsini gözləyir. `acks=0` — heç gözləmir (ən sürətli, ən az güvənli), `acks=1` — yalnız leader (kompromis), `acks=all` — bütün ISR (ən yavaş, ən güvənli).

### 2. enable.idempotence nədir?
**Cavab:** Producer-ın retry etdikdə duplicate mesaj göndərməsinin qarşısını alır. Hər mesaja unique sequence number əlavə edir. Kafka broker duplicate-ları rədd edir. `acks=all`, `retries > 0`, `max.in.flight.requests ≤ 5` tələb edir.

### 3. Kafka-ya göndərməni necə atomic etmək olar?
**Cavab:** Kafka transactions ilə — `setTransactionIdPrefix` ilə transactional producer yaradılır. `@Transactional("kafkaTransactionManager")` ilə həm DB, həm Kafka əməliyyatı atomicdir. Alternativ: **Outbox Pattern** — DB-yə outbox table-a yaz, scheduler Kafka-ya göndərsin.

### 4. linger.ms nədir?
**Cavab:** Producer batch-ləri Kafka-ya göndərmədən əvvəl bu qədər gözləyir (ms). `linger.ms=0` — mesajı dərhal göndər. `linger.ms=10` — 10ms gözlə, bu müddətdə başqa mesajlar gəlirsə batch-ə əlavə et. Throughput artırır, latency bir az artır.

### 5. Outbox Pattern nə üçündür?
**Cavab:** DB transaction ilə Kafka mesajı göndərməni atomic etmək üçün. Kafka-ya birbaşa göndərmə uğursuz olarsa, mesaj itirilir. Outbox Pattern-də əvvəlcə DB-yə yazılır (DB transaction-un bir hissəsi), sonra scheduler bu mesajları Kafka-ya göndərir. Kafka failure olsa belə mesaj DB-də qalır.

*Son yenilənmə: 2026-04-10*
