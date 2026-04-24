# 077 — Spring Kafka Consumer — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [@KafkaListener nədir?](#kafkalistener-nədir)
2. [ConsumerConfig](#consumerconfig)
3. [Error handling](#error-handling)
4. [Dead Letter Topic (DLT)](#dead-letter-topic-dlt)
5. [Concurrency və partitioning](#concurrency-və-partitioning)
6. [İntervyu Sualları](#intervyu-sualları)

---

## @KafkaListener nədir?

**@KafkaListener** — Kafka topic-lərindən mesaj oxuyan Spring annotasiyası. Mesaj gəldikdə metodun avtomatik çağırılmasını təmin edir.

```java
@Component
public class OrderEventConsumer {

    // Sadə listener
    @KafkaListener(topics = "order-events",
                   groupId = "order-processing-service")
    public void handleOrderEvent(Order order) {
        log.info("Order event qəbul edildi: {}", order.getId());
        processOrder(order);
    }

    // ConsumerRecord ilə — metadata da lazımdır
    @KafkaListener(topics = "order-events", groupId = "audit-service")
    public void handleWithMetadata(ConsumerRecord<String, Order> record) {
        log.info("Topic={}, Partition={}, Offset={}, Key={}, Timestamp={}",
            record.topic(), record.partition(), record.offset(),
            record.key(), record.timestamp());

        Order order = record.value();

        // Header-ları oxumaq
        Header correlationIdHeader = record.headers().lastHeader("correlationId");
        if (correlationIdHeader != null) {
            String correlationId = new String(correlationIdHeader.value());
            MDC.put("correlationId", correlationId);
        }

        processAudit(order);
    }

    // @Header annotasiyası ilə
    @KafkaListener(topics = "payment-events")
    public void handlePayment(
            @Payload PaymentEvent event,
            @Header(KafkaHeaders.RECEIVED_PARTITION) int partition,
            @Header(KafkaHeaders.OFFSET) long offset,
            @Header(KafkaHeaders.RECEIVED_TOPIC) String topic) {

        log.info("Payment: partition={}, offset={}", partition, offset);
        processPayment(event);
    }

    // Batch listener
    @KafkaListener(topics = "bulk-events", groupId = "bulk-processor",
                   batch = "true")
    public void handleBatch(List<ConsumerRecord<String, Event>> records) {
        log.info("{} mesaj batch emal edilir", records.size());
        records.forEach(record -> processEvent(record.value()));
    }
}
```

---

## ConsumerConfig

```yaml
spring:
  kafka:
    consumer:
      group-id: my-service
      auto-offset-reset: earliest   # earliest/latest/none
      enable-auto-commit: false      # Manual commit tövsiyə olunur
      key-deserializer: org.apache.kafka.common.serialization.StringDeserializer
      value-deserializer: org.springframework.kafka.support.serializer.JsonDeserializer
      properties:
        spring.json.trusted.packages: "com.example.event"
        max.poll.records: 500          # Bir poll-da maksimum mesaj
        max.poll.interval.ms: 300000   # 5 dəqiqə — uzun emal üçün artırın
        session.timeout.ms: 30000      # Broker-ə heartbeat timeout
        heartbeat.interval.ms: 10000
```

```java
@Configuration
public class KafkaConsumerConfig {

    @Bean
    public ConsumerFactory<String, Object> consumerFactory() {
        Map<String, Object> config = new HashMap<>();
        config.put(ConsumerConfig.BOOTSTRAP_SERVERS_CONFIG, bootstrapServers);
        config.put(ConsumerConfig.GROUP_ID_CONFIG, "my-service");
        config.put(ConsumerConfig.KEY_DESERIALIZER_CLASS_CONFIG,
                   StringDeserializer.class);
        config.put(ConsumerConfig.VALUE_DESERIALIZER_CLASS_CONFIG,
                   JsonDeserializer.class);
        config.put(ConsumerConfig.ENABLE_AUTO_COMMIT_CONFIG, false);
        config.put(ConsumerConfig.AUTO_OFFSET_RESET_CONFIG, "earliest");
        config.put(JsonDeserializer.TRUSTED_PACKAGES, "com.example.event");

        return new DefaultKafkaConsumerFactory<>(config);
    }

    @Bean
    public ConcurrentKafkaListenerContainerFactory<String, Object>
           kafkaListenerContainerFactory() {

        ConcurrentKafkaListenerContainerFactory<String, Object> factory =
            new ConcurrentKafkaListenerContainerFactory<>();

        factory.setConsumerFactory(consumerFactory());

        // Manual commit
        factory.getContainerProperties()
               .setAckMode(ContainerProperties.AckMode.MANUAL_IMMEDIATE);

        // Concurrency — partition sayından çox olmaz
        factory.setConcurrency(3);

        // Error handler
        factory.setCommonErrorHandler(errorHandler());

        return factory;
    }
}

// Manual acknowledge
@KafkaListener(topics = "critical-events")
public void handleCritical(ConsumerRecord<String, Event> record,
                            Acknowledgment acknowledgment) {
    try {
        processEvent(record.value());
        acknowledgment.acknowledge(); // Uğurlu emaldan sonra commit
    } catch (Exception e) {
        log.error("Emal uğursuz: {}", e.getMessage());
        // Acknowledge etmə — mesaj yenidən gəlir
        // (yaxud DLT-yə göndər)
    }
}
```

---

## Error handling

```java
@Configuration
public class KafkaErrorHandlerConfig {

    @Bean
    public DefaultErrorHandler errorHandler(KafkaTemplate<String, Object> template) {

        // Dead letter publisher
        DeadLetterPublishingRecoverer recoverer =
            new DeadLetterPublishingRecoverer(template,
                (record, ex) -> new TopicPartition(
                    record.topic() + ".DLT",
                    record.partition()
                )
            );

        // Retry policy — 3 cəhd, 1 saniyə aralıq
        ExponentialBackOffWithMaxRetries backOff =
            new ExponentialBackOffWithMaxRetries(3);
        backOff.setInitialInterval(1000L);
        backOff.setMultiplier(2.0);
        backOff.setMaxInterval(10000L);

        DefaultErrorHandler errorHandler =
            new DefaultErrorHandler(recoverer, backOff);

        // Retry edilməyəcək exception-lar
        errorHandler.addNotRetryableExceptions(
            DeserializationException.class,
            IllegalArgumentException.class
        );

        return errorHandler;
    }
}
```

---

## Dead Letter Topic (DLT)

```java
// DLT listener — uğursuz mesajları emal etmək
@Component
public class DltConsumer {

    @KafkaListener(topics = "order-events.DLT",
                   groupId = "dlt-processor")
    public void handleDlt(ConsumerRecord<String, Order> record,
                          @Header(KafkaHeaders.DLT_EXCEPTION_MESSAGE) String errorMessage,
                          @Header(KafkaHeaders.DLT_ORIGINAL_TOPIC) String originalTopic,
                          @Header(KafkaHeaders.DLT_ORIGINAL_OFFSET) long originalOffset) {

        log.error("DLT mesajı: topic={}, offset={}, error={}",
            originalTopic, originalOffset, errorMessage);

        // Manual review üçün DB-yə yaz
        failedMessageRepository.save(new FailedMessage(
            record.key(),
            objectMapper.writeValueAsString(record.value()),
            originalTopic,
            originalOffset,
            errorMessage,
            LocalDateTime.now()
        ));

        // Alert göndər
        alertService.sendDltAlert(originalTopic, record.key(), errorMessage);
    }
}

// @RetryableTopic annotasiyası (daha asan konfiqurasiya)
@Component
public class RetryableOrderConsumer {

    @RetryableTopic(
        attempts = "4",           // 1 ilkin + 3 retry
        backoff = @Backoff(delay = 1000, multiplier = 2.0),
        dltStrategy = DltStrategy.FAIL_ON_ERROR,
        autoCreateTopics = "false"
    )
    @KafkaListener(topics = "order-events")
    public void handleOrder(Order order) {
        // Xəta olarsa avtomatik retry-1, retry-2, retry-3 topic-lərinə keçir
        processOrder(order);
    }

    @DltHandler
    public void handleDlt(Order order,
                           @Header(KafkaHeaders.RECEIVED_TOPIC) String topic) {
        log.error("DLT handler: topic={}, order={}", topic, order.getId());
    }
}
```

---

## Concurrency və partitioning

```java
// Concurrency = parallel consumer sayı
// Her consumer fərqli partition-u oxuyur
// concurrency <= topic partition sayı

@KafkaListener(topics = "order-events",
               groupId = "order-service",
               concurrency = "6")   // 6 thread, 6 partition üçün
public void handleOrder(Order order) {
    processOrder(order);
}

// Spesifik partition-ları oxumaq
@KafkaListener(
    topicPartitions = @TopicPartition(
        topic = "order-events",
        partitionOffsets = {
            @PartitionOffset(partition = "0", initialOffset = "0"),
            @PartitionOffset(partition = "1", initialOffset = "0")
        }
    )
)
public void handleSpecificPartitions(ConsumerRecord<String, Order> record) {
    // Yalnız 0 və 1 nömrəli partition-ları oxuyur
}

// Listener-i dinamik başlat/dayandır
@Autowired
private KafkaListenerEndpointRegistry registry;

public void pauseConsumer() {
    registry.getListenerContainer("myListenerId").pause();
}

public void resumeConsumer() {
    registry.getListenerContainer("myListenerId").resume();
}

@KafkaListener(id = "myListenerId", topics = "order-events")
public void handler(Order order) {
    processOrder(order);
}
```

---

## İntervyu Sualları

### 1. Consumer Group nədir?
**Cavab:** Eyni `group-id`-ə malik consumer-ların toplusu. Kafka bir topic-in mesajlarını group daxilindəki consumer-lar arasında bölür (hər partition yalnız bir consumer-da). Fərqli group-lar eyni topic-i müstəqil oxuya bilir. Bu sayədə bir mesaj həm `order-service`, həm `audit-service` tərəfindən emal edilə bilər.

### 2. enable-auto-commit false niyə tövsiyə olunur?
**Cavab:** Auto-commit offset-i vaxtaşırı commit edir — mesaj emal edilmədən commit ola bilər (crash olarsa itirilir). Manual commit (`Acknowledgment.acknowledge()`) yalnız uğurlu emaldan sonra offset commit edir, bu data itkisinin qarşısını alır.

### 3. at-least-once vs exactly-once nədir?
**Cavab:** `at-least-once` — mesaj ən azı bir dəfə çatdırılır (restart olduqda yenidən gələ bilər). `exactly-once` — mesaj tam bir dəfə çatdırılır (Kafka transactions + idempotent consumer). Əksər sistemlər `at-least-once` + idempotent processing istifadə edir.

### 4. auto.offset.reset dəyərləri nədir?
**Cavab:** `earliest` — heç bir offset yoxdursa, topic-in əvvəlindən oxu (bütün mesajlar). `latest` — heç bir offset yoxdursa, yalnız yeni gələn mesajları oxu (default). `none` — offset yoxdursa exception. Consumer group ilk dəfə başladıqda vacibdir.

### 5. max.poll.interval.ms niyə vacibdir?
**Cavab:** Consumer poll() çağırmalar arasındakı maksimum müddət. Bu müddəti keçsə, broker consumer-ı "dead" hesab edib rebalance başladır. Uzun emal (5+ dəqiqə) olarsa bu dəyəri artırmaq lazımdır. Alternativ: `@KafkaListener` metodunda uzun əməliyyatı async et, dərhal acknowledge et.

*Son yenilənmə: 2026-04-10*
