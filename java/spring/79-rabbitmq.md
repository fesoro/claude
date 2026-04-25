# 79 — Spring RabbitMQ — Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [RabbitMQ anlayışları](#rabbitmq-anlayışları)
2. [Spring AMQP quraşdırması](#spring-amqp-quraşdırması)
3. [Exchange və Queue konfiqurasiyası](#exchange-və-queue-konfiqurasiyası)
4. [Mesaj göndərmə (Producer)](#mesaj-göndərmə-producer)
5. [@RabbitListener (Consumer)](#rabbitlistener-consumer)
6. [Error handling və DLX](#error-handling-və-dlx)
7. [İntervyu Sualları](#intervyu-sualları)

---

## RabbitMQ anlayışları

```
Producer → Exchange → Queue → Consumer

Exchange növləri:
  Direct  — Routing key tam uyğunluğu
  Topic   — Pattern matching (order.*, *.created)
  Fanout  — Bütün queue-lara göndər (broadcast)
  Headers — Message header-ları ilə routing

Message lifecycle:
  Producer → Exchange (routing) → Queue (buffer) → Consumer
  ACK/NACK → Queue-dan sil/geri qaytar
```

---

## Spring AMQP quraşdırması

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-amqp</artifactId>
</dependency>
```

```yaml
spring:
  rabbitmq:
    host: localhost
    port: 5672
    username: ${RABBITMQ_USERNAME:guest}
    password: ${RABBITMQ_PASSWORD:guest}
    virtual-host: /
    listener:
      simple:
        acknowledge-mode: manual     # manual/auto/none
        prefetch: 10                 # Bir consumer-ın eyni anda saxladığı mesaj sayı
        retry:
          enabled: true
          max-attempts: 3
          initial-interval: 1000ms
          multiplier: 2.0
    template:
      retry:
        enabled: true
        max-attempts: 3
```

---

## Exchange və Queue konfiqurasiyası

```java
@Configuration
public class RabbitMQConfig {

    // Queue adları
    public static final String ORDER_QUEUE = "order.queue";
    public static final String ORDER_DLQ = "order.queue.dlq";
    public static final String PAYMENT_QUEUE = "payment.queue";
    public static final String NOTIFICATION_QUEUE = "notification.queue";

    // Exchange adları
    public static final String ORDER_EXCHANGE = "order.exchange";
    public static final String DEAD_LETTER_EXCHANGE = "dlx.exchange";

    // ===== Exchange-lər =====

    // Direct Exchange
    @Bean
    public DirectExchange orderExchange() {
        return ExchangeBuilder.directExchange(ORDER_EXCHANGE)
            .durable(true)
            .build();
    }

    // Topic Exchange
    @Bean
    public TopicExchange notificationExchange() {
        return ExchangeBuilder.topicExchange("notification.exchange")
            .durable(true)
            .build();
    }

    // Fanout Exchange
    @Bean
    public FanoutExchange broadcastExchange() {
        return ExchangeBuilder.fanoutExchange("broadcast.exchange")
            .durable(true)
            .build();
    }

    // Dead Letter Exchange
    @Bean
    public DirectExchange deadLetterExchange() {
        return ExchangeBuilder.directExchange(DEAD_LETTER_EXCHANGE)
            .durable(true)
            .build();
    }

    // ===== Queue-lar =====

    // Order Queue — DLX ilə
    @Bean
    public Queue orderQueue() {
        return QueueBuilder.durable(ORDER_QUEUE)
            .withArgument("x-dead-letter-exchange", DEAD_LETTER_EXCHANGE)
            .withArgument("x-dead-letter-routing-key", "order.dead")
            .withArgument("x-message-ttl", 300000) // 5 dəqiqə TTL
            .build();
    }

    // Dead Letter Queue
    @Bean
    public Queue orderDeadLetterQueue() {
        return QueueBuilder.durable(ORDER_DLQ).build();
    }

    // Payment Queue
    @Bean
    public Queue paymentQueue() {
        return QueueBuilder.durable(PAYMENT_QUEUE)
            .withArgument("x-max-priority", 10) // Priority queue
            .build();
    }

    // ===== Binding-lər =====

    // Order queue → Order exchange (routing key: "order.created")
    @Bean
    public Binding orderBinding(Queue orderQueue, DirectExchange orderExchange) {
        return BindingBuilder.bind(orderQueue)
            .to(orderExchange)
            .with("order.created");
    }

    // DLQ → DLX
    @Bean
    public Binding deadLetterBinding(Queue orderDeadLetterQueue,
                                      DirectExchange deadLetterExchange) {
        return BindingBuilder.bind(orderDeadLetterQueue)
            .to(deadLetterExchange)
            .with("order.dead");
    }

    // Topic binding — pattern matching
    @Bean
    public Binding notificationBinding(Queue notificationQueue,
                                        TopicExchange notificationExchange) {
        return BindingBuilder.bind(notificationQueue)
            .to(notificationExchange)
            .with("notification.#"); // notification ilə başlayan hər routing key
    }

    // ===== Message Converter =====
    @Bean
    public MessageConverter jsonMessageConverter() {
        return new Jackson2JsonMessageConverter();
    }

    @Bean
    public RabbitTemplate rabbitTemplate(ConnectionFactory connectionFactory,
                                          MessageConverter messageConverter) {
        RabbitTemplate template = new RabbitTemplate(connectionFactory);
        template.setMessageConverter(messageConverter);
        template.setConfirmCallback((correlationData, ack, cause) -> {
            if (!ack) {
                log.error("Mesaj broker-ə çatmadı: {}", cause);
            }
        });
        template.setReturnsCallback(returned -> {
            log.error("Mesaj queue-ya route edilmədi: {}", returned.getMessage());
        });
        return template;
    }
}
```

---

## Mesaj göndərmə (Producer)

```java
@Service
public class OrderMessageProducer {

    private final RabbitTemplate rabbitTemplate;

    // Sadə mesaj
    public void sendOrderCreated(Order order) {
        rabbitTemplate.convertAndSend(
            RabbitMQConfig.ORDER_EXCHANGE,
            "order.created",
            order
        );
        log.info("Order mesajı göndərildi: {}", order.getId());
    }

    // MessagePostProcessor ilə header əlavə et
    public void sendOrderWithHeaders(Order order) {
        rabbitTemplate.convertAndSend(
            RabbitMQConfig.ORDER_EXCHANGE,
            "order.created",
            order,
            message -> {
                message.getMessageProperties().setCorrelationId(
                    UUID.randomUUID().toString());
                message.getMessageProperties().setHeader("source", "order-service");
                message.getMessageProperties().setHeader("version", "1.0");
                message.getMessageProperties().setPriority(5);
                message.getMessageProperties().setExpiration("60000"); // 60s TTL
                return message;
            }
        );
    }

    // Reply (RPC pattern)
    public OrderStatus checkOrderStatus(Long orderId) {
        return (OrderStatus) rabbitTemplate.convertSendAndReceive(
            RabbitMQConfig.ORDER_EXCHANGE,
            "order.status.check",
            orderId
        );
    }

    // Fanout — bütün subscriber-lara
    public void broadcast(SystemAlert alert) {
        rabbitTemplate.convertAndSend("broadcast.exchange", "", alert);
    }
}
```

---

## @RabbitListener (Consumer)

```java
@Component
public class OrderMessageConsumer {

    // Sadə listener
    @RabbitListener(queues = RabbitMQConfig.ORDER_QUEUE)
    public void handleOrder(Order order) {
        log.info("Order qəbul edildi: {}", order.getId());
        processOrder(order);
    }

    // Manual acknowledge
    @RabbitListener(queues = RabbitMQConfig.ORDER_QUEUE,
                    ackMode = "MANUAL")
    public void handleOrderManual(Order order,
                                   Channel channel,
                                   @Header(AmqpHeaders.DELIVERY_TAG) long deliveryTag) {
        try {
            processOrder(order);
            channel.basicAck(deliveryTag, false); // Uğurlu — sil
        } catch (BusinessException e) {
            // Yenidən queue-ya qaytar (requeue=true)
            channel.basicNack(deliveryTag, false, true);
        } catch (Exception e) {
            // DLQ-ya göndər (requeue=false)
            channel.basicNack(deliveryTag, false, false);
        }
    }

    // Message ilə tam məlumat
    @RabbitListener(queues = RabbitMQConfig.PAYMENT_QUEUE)
    public void handlePayment(Message message, Channel channel,
                               @Header(AmqpHeaders.DELIVERY_TAG) long deliveryTag) {
        String correlationId = message.getMessageProperties().getCorrelationId();
        String source = (String) message.getMessageProperties().getHeader("source");
        byte[] body = message.getBody();

        PaymentEvent event = objectMapper.readValue(body, PaymentEvent.class);
        processPayment(event);

        channel.basicAck(deliveryTag, false);
    }

    // RPC — cavab qaytar
    @RabbitListener(queues = "order.status.queue")
    public OrderStatus handleStatusCheck(Long orderId) {
        return orderRepository.findStatusById(orderId);
        // RabbitTemplate cavabı reply-to queue-ya göndərir
    }
}
```

---

## Error handling və DLX

```java
@Component
public class DeadLetterQueueConsumer {

    @RabbitListener(queues = RabbitMQConfig.ORDER_DLQ)
    public void handleDeadLetter(Message message) {
        Map<String, Object> headers = message.getMessageProperties().getHeaders();

        String reason = (String) headers.get("x-death[0].reason"); // rejected/expired
        String originalQueue = (String) headers.get("x-death[0].queue");
        Long count = (Long) headers.get("x-death[0].count");

        log.error("DLQ mesajı: queue={}, reason={}, count={}",
            originalQueue, reason, count);

        // DB-yə yaz
        failedMessageRepository.save(new FailedMessage(
            new String(message.getBody()),
            originalQueue,
            reason,
            LocalDateTime.now()
        ));

        // Opsional: retry after fix
        // rabbitTemplate.send(originalQueue, message);
    }
}

// Retry konfiqurasiyası (SimpleRabbitListenerContainerFactory)
@Bean
public SimpleRabbitListenerContainerFactory rabbitListenerContainerFactory(
        ConnectionFactory connectionFactory,
        MessageConverter messageConverter) {

    SimpleRabbitListenerContainerFactory factory =
        new SimpleRabbitListenerContainerFactory();
    factory.setConnectionFactory(connectionFactory);
    factory.setMessageConverter(messageConverter);
    factory.setAcknowledgeMode(AcknowledgeMode.MANUAL);
    factory.setPrefetchCount(10);

    // Retry
    RetryInterceptorBuilder<?> retry = RetryInterceptorBuilder.stateless()
        .maxAttempts(3)
        .backOffOptions(1000, 2.0, 10000)
        .recoverer(new RejectAndDontRequeueRecoverer()); // Üçüncü xətada DLQ-ya
    factory.setAdviceChain(retry.build());

    return factory;
}
```

---

## İntervyu Sualları

### 1. RabbitMQ vs Kafka fərqi nədir?
**Cavab:** RabbitMQ — message broker, smart broker/dumb consumer model, mesaj emaldan sonra silinir, push-based. Kafka — distributed log, dumb broker/smart consumer model, mesajlar saxlanılır (retention), pull-based. RabbitMQ task queue, request-response üçün; Kafka event streaming, log aggregation üçün daha uyğundur.

### 2. Direct vs Topic Exchange fərqi nədir?
**Cavab:** `Direct` — routing key tam uyğunluğu (`order.created` → yalnız `order.created`). `Topic` — wildcard pattern matching (`order.*` → `order.created`, `order.updated`; `#.created` → istənilən şeylə bitmə). `Fanout` — routing key olmadan bütün binding-lərə göndər.

### 3. Dead Letter Exchange nədir?
**Cavab:** Rejected, expired, yaxud maksimum retry-ı bitmiş mesajların yönləndirildiyi exchange. Queue-da `x-dead-letter-exchange` argument-i ilə konfiqurasiya edilir. DLQ consumer-ı bu mesajları emal edir (manual review, alert, retry after fix).

### 4. prefetch nədir?
**Cavab:** Consumer-ın ACK gözləməkdən öncə saxlaya biləcəyi mesaj sayı. `prefetch=1` — bir mesaj emal etmədən növbəti almır (fair dispatch). `prefetch=10` — 10 mesajı buffer-a alır, throughput artır. Uzun emal müddəti varsa kiçik prefetch daha ədalətli paylaşım verir.

### 5. Publish confirm (Publisher Confirms) nədir?
**Cavab:** Producer-ın mesajın broker-ə çatıb-çatmadığını öyrənməsi. `ConfirmCallback` — broker-in ACK/NACK-ı. `ReturnsCallback` — mesajın heç bir queue-ya route edilmədikdə qaytarılması. Bu olmadan göndərmə "fire and forget" — xəta baş verərsə bilmirsən.

*Son yenilənmə: 2026-04-10*
