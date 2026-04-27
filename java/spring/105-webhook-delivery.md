# Webhook Delivery Patterns (Senior)

## İcmal

Webhook — bir sistemdə hadisə baş verdikdə digər sistemə HTTP POST göndərən event notification mexanizmidir. Polling yerinə push model istifadə edir: "nə vaxt X baş verərsə, bu URL-ə zəng et."

Spring Boot-da webhook-un iki tərəfi var:
- **Sender tərəfi**: öz sistemindən xarici client-lərə webhook göndərmək (Stripe, GitHub kimi)
- **Receiver tərəfi**: xarici sistemdən gələn webhook-ları qəbul etmək

---

## Niyə Vacibdir

Polling sistemlər üçün ölçəklənmə problemi yaradır: 10,000 client hər 10 saniyədən bir sorğu göndərirsə, 1M sorğu/dəqiqə olur. Webhook ilə yalnız event baş verdikdə sorğu gəlir.

**Real dünya nümunələri:**
- Stripe — ödəniş tamamlandıqda merchant-ə POST
- GitHub — PR açıldıqda CI/CD sistemə POST
- Shopify — sifariş gəldikdə ERP sistemə POST
- Twilio — SMS status yeniləndikdə app-ə POST

---

## Əsas Anlayışlar

**HMAC Signature:** Payload-u shared secret ilə imzalamaq. Client imzanı verify edərək mesajın həqiqətən sizdən gəldiyini yoxlayır.

**Idempotency:** Eyni webhook birdən çox göndərilə bilər (retry). Client hər delivery-nin unikal ID-si ilə duplikat-ı aşkar etməlidir.

**Delivery Lifecycle:**
```
PENDING → PROCESSING → DELIVERED
                    ↓
                  FAILED → (retry) → FAILED_PERMANENTLY
```

**Exponential Backoff:**
- 1. cəhd: dərhal
- 2. cəhd: 1 dəqiqə sonra
- 3. cəhd: 5 dəqiqə sonra
- 4. cəhd: 30 dəqiqə sonra
- 5. cəhd: 2 saat sonra
- Son cəhd: 24 saat sonra → FAILED_PERMANENTLY

---

## Praktik Baxış

**Trade-off-lar:**
- Webhook sadədir, lakin delivery guarantee-si zəifdir — retry mexanizmi şərtdir
- Client-in endpoint-i down ola bilər — delivery tracking vacibdir
- Replay attack-lardan qorunmaq üçün timestamp-ı signature-a daxil et

**Common mistakes:**
- Webhook-u synchronous göndərmək — main thread bloklanır
- Retry olmadan göndərmək — client-in 500 verdiyi zaman data itirilir
- Signature verify etməmək — security vulnerability
- Client-in cavabını gözləmək — client yavaş cavab verərsə timeout

---

## Nümunələr

### Ümumi Nümunə

E-commerce platforması: `order.shipped` eventi baş verdikdə, bu event-ə subscribe olan bütün merchant-lərə webhook göndərilir. Delivery async işlənir, uğursuz cəhdlər exponential backoff ilə retry olunur.

### Kod Nümunəsi

#### SENDER tərəfi

**Entity-lər:**

```java
@Entity
@Table(name = "webhook_subscriptions")
public class WebhookSubscription {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String clientUrl;           // POST göndəriləcək URL
    private String secret;              // HMAC signing secret
    private boolean active;

    @ElementCollection
    @CollectionTable(name = "webhook_subscription_events")
    private Set<String> events;         // ["order.shipped", "order.cancelled"]

    // getters/setters
}

@Entity
@Table(name = "webhook_deliveries")
public class WebhookDelivery {

    @Id
    @GeneratedValue(strategy = GenerationType.UUID)
    private String id;                  // Delivery unikal ID (idempotency key)

    private Long subscriptionId;
    private String eventType;

    @Column(columnDefinition = "TEXT")
    private String payload;             // JSON payload

    @Enumerated(EnumType.STRING)
    private DeliveryStatus status;      // PENDING, PROCESSING, DELIVERED, FAILED, FAILED_PERMANENTLY

    private int attemptCount;
    private Instant nextRetryAt;
    private Instant createdAt;
    private Instant deliveredAt;

    @Column(columnDefinition = "TEXT")
    private String lastError;           // Son xəta mesajı
}
```

**HMAC Signature:**

```java
@Component
public class WebhookSigner {

    private static final String ALGORITHM = "HmacSHA256";

    /**
     * Payload-u secret ilə imzala.
     * Format: sha256=<hex_signature>
     */
    public String sign(String payload, String secret) {
        try {
            Mac mac = Mac.getInstance(ALGORITHM);
            SecretKeySpec keySpec = new SecretKeySpec(
                secret.getBytes(StandardCharsets.UTF_8), ALGORITHM);
            mac.init(keySpec);

            byte[] hash = mac.doFinal(payload.getBytes(StandardCharsets.UTF_8));
            return "sha256=" + HexFormat.of().formatHex(hash);

        } catch (NoSuchAlgorithmException | InvalidKeyException e) {
            throw new RuntimeException("Failed to sign webhook payload", e);
        }
    }

    /**
     * Gələn signature-ı verify et (timing-safe müqayisə).
     */
    public boolean verify(String payload, String secret, String receivedSignature) {
        String expectedSignature = sign(payload, secret);
        // MessageDigest.isEqual — timing-safe comparison (length leak yoxdur)
        return MessageDigest.isEqual(
            expectedSignature.getBytes(StandardCharsets.UTF_8),
            receivedSignature.getBytes(StandardCharsets.UTF_8)
        );
    }
}
```

**WebhookService — event publish et:**

```java
@Service
@Slf4j
public class WebhookService {

    private final WebhookSubscriptionRepository subscriptionRepo;
    private final WebhookDeliveryRepository deliveryRepo;
    private final WebhookSender sender;
    private final ObjectMapper objectMapper;

    /**
     * Event baş verdikdə bu metodu çağır.
     * Hamısı async işlənir — bu metod bloklanmır.
     */
    @Async("webhookTaskExecutor")
    public void publishEvent(String eventType, Object eventData) {
        // Bu event-ə subscribe olan aktiv subscription-ları tap
        List<WebhookSubscription> subscriptions =
            subscriptionRepo.findByEventAndActive(eventType, true);

        for (WebhookSubscription subscription : subscriptions) {
            createAndSendDelivery(subscription, eventType, eventData);
        }
    }

    private void createAndSendDelivery(
            WebhookSubscription subscription,
            String eventType,
            Object eventData) {
        try {
            String payload = objectMapper.writeValueAsString(Map.of(
                "id", UUID.randomUUID().toString(),
                "event", eventType,
                "timestamp", Instant.now().toString(),
                "data", eventData
            ));

            WebhookDelivery delivery = new WebhookDelivery();
            delivery.setSubscriptionId(subscription.getId());
            delivery.setEventType(eventType);
            delivery.setPayload(payload);
            delivery.setStatus(DeliveryStatus.PENDING);
            delivery.setAttemptCount(0);
            delivery.setCreatedAt(Instant.now());

            deliveryRepo.save(delivery);

            // Dərhal birinci cəhdi et
            sender.attempt(delivery, subscription);

        } catch (JsonProcessingException e) {
            log.error("Failed to serialize webhook payload for event: {}", eventType, e);
        }
    }
}
```

**WebhookSender — HTTP POST göndər:**

```java
@Service
@Slf4j
public class WebhookSender {

    private final RestClient restClient;
    private final WebhookSigner signer;
    private final WebhookDeliveryRepository deliveryRepo;

    private static final int MAX_ATTEMPTS = 6;
    // Retry intervalları (saniyə)
    private static final long[] RETRY_DELAYS = {60, 300, 1800, 7200, 86400};

    public WebhookSender(RestClient.Builder restClientBuilder,
                         WebhookSigner signer,
                         WebhookDeliveryRepository deliveryRepo) {
        this.restClient = restClientBuilder
            .requestInterceptor((request, body, execution) -> {
                request.getHeaders().set("User-Agent", "MyApp-Webhook/1.0");
                return execution.execute(request, body);
            })
            .build();
        this.signer = signer;
        this.deliveryRepo = deliveryRepo;
    }

    public void attempt(WebhookDelivery delivery, WebhookSubscription subscription) {
        delivery.setStatus(DeliveryStatus.PROCESSING);
        delivery.setAttemptCount(delivery.getAttemptCount() + 1);
        deliveryRepo.save(delivery);

        try {
            String signature = signer.sign(delivery.getPayload(), subscription.getSecret());

            restClient.post()
                .uri(subscription.getClientUrl())
                .header("Content-Type", "application/json")
                .header("X-Webhook-Signature", signature)
                .header("X-Webhook-Delivery-Id", delivery.getId())
                .header("X-Webhook-Event", delivery.getEventType())
                .body(delivery.getPayload())
                .retrieve()
                .toBodilessEntity();  // 2xx → uğurlu

            // Uğurlu delivery
            delivery.setStatus(DeliveryStatus.DELIVERED);
            delivery.setDeliveredAt(Instant.now());
            deliveryRepo.save(delivery);

            log.info("Webhook delivered: deliveryId={}, url={}",
                delivery.getId(), subscription.getClientUrl());

        } catch (Exception e) {
            handleFailure(delivery, e);
        }
    }

    private void handleFailure(WebhookDelivery delivery, Exception e) {
        log.warn("Webhook delivery failed: deliveryId={}, attempt={}, error={}",
            delivery.getId(), delivery.getAttemptCount(), e.getMessage());

        delivery.setLastError(e.getMessage());

        if (delivery.getAttemptCount() >= MAX_ATTEMPTS) {
            delivery.setStatus(DeliveryStatus.FAILED_PERMANENTLY);
            log.error("Webhook permanently failed after {} attempts: deliveryId={}",
                MAX_ATTEMPTS, delivery.getId());
        } else {
            // Növbəti retry vaxtını hesabla
            int retryIndex = delivery.getAttemptCount() - 1;
            long delaySeconds = RETRY_DELAYS[Math.min(retryIndex, RETRY_DELAYS.length - 1)];

            delivery.setStatus(DeliveryStatus.FAILED);
            delivery.setNextRetryAt(Instant.now().plusSeconds(delaySeconds));
        }

        deliveryRepo.save(delivery);
    }
}
```

**Retry Scheduler:**

```java
@Component
@Slf4j
public class WebhookRetryScheduler {

    private final WebhookDeliveryRepository deliveryRepo;
    private final WebhookSubscriptionRepository subscriptionRepo;
    private final WebhookSender sender;

    // Hər dəqiqə retry lazım olan delivery-ləri yoxla
    @Scheduled(fixedDelay = 60_000)
    @Transactional
    public void retryFailedDeliveries() {
        List<WebhookDelivery> dueDeliveries =
            deliveryRepo.findByStatusAndNextRetryAtBefore(
                DeliveryStatus.FAILED,
                Instant.now()
            );

        log.info("Retrying {} failed webhook deliveries", dueDeliveries.size());

        for (WebhookDelivery delivery : dueDeliveries) {
            subscriptionRepo.findById(delivery.getSubscriptionId())
                .filter(WebhookSubscription::isActive)
                .ifPresent(sub -> sender.attempt(delivery, sub));
        }
    }
}
```

**Async Thread Pool konfiqurasiyası:**

```java
@Configuration
@EnableAsync
public class AsyncConfig {

    @Bean(name = "webhookTaskExecutor")
    public Executor webhookTaskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(5);
        executor.setMaxPoolSize(20);
        executor.setQueueCapacity(500);
        executor.setThreadNamePrefix("webhook-");
        executor.setRejectedExecutionHandler(new ThreadPoolExecutor.CallerRunsPolicy());
        executor.initialize();
        return executor;
    }
}
```

---

#### RECEIVER tərəfi

**Webhook qəbul edən controller:**

```java
@RestController
@RequestMapping("/webhooks")
@Slf4j
public class WebhookReceiverController {

    private final WebhookSigner signer;
    private final WebhookProcessingService processingService;
    private final ProcessedWebhookRepository processedRepo;

    private static final String WEBHOOK_SECRET = "${app.webhook.stripe-secret}";

    @PostMapping("/stripe")
    public ResponseEntity<Void> receiveStripeWebhook(
            @RequestHeader("X-Webhook-Signature") String signature,
            @RequestHeader("X-Webhook-Delivery-Id") String deliveryId,
            @RequestBody String rawPayload) {

        // 1. Signature-ı dərhal verify et
        if (!signer.verify(rawPayload, WEBHOOK_SECRET, signature)) {
            log.warn("Invalid webhook signature: deliveryId={}", deliveryId);
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        // 2. Idempotency — eyni delivery-ni iki dəfə emal etmə
        if (processedRepo.existsById(deliveryId)) {
            log.info("Duplicate webhook ignored: deliveryId={}", deliveryId);
            return ResponseEntity.ok().build();  // 200 qaytar — sender retry etməsin
        }

        // 3. Delivery ID-ni işarələ
        processedRepo.save(new ProcessedWebhook(deliveryId, Instant.now()));

        // 4. Dərhal 200 qaytar, emal async
        processingService.processAsync(rawPayload);

        return ResponseEntity.ok().build();
    }
}
```

**Async emal:**

```java
@Service
@Slf4j
public class WebhookProcessingService {

    private final ObjectMapper objectMapper;
    private final OrderService orderService;

    @Async
    public void processAsync(String rawPayload) {
        try {
            JsonNode node = objectMapper.readTree(rawPayload);
            String eventType = node.get("event").asText();

            switch (eventType) {
                case "payment.completed" -> handlePaymentCompleted(node.get("data"));
                case "payment.failed" -> handlePaymentFailed(node.get("data"));
                default -> log.debug("Unhandled webhook event: {}", eventType);
            }
        } catch (Exception e) {
            log.error("Failed to process webhook payload", e);
            // Exception-u throw etmə — HTTP response artıq göndərilib
        }
    }

    private void handlePaymentCompleted(JsonNode data) {
        Long orderId = data.get("orderId").asLong();
        orderService.confirmPayment(orderId);
    }

    private void handlePaymentFailed(JsonNode data) {
        Long orderId = data.get("orderId").asLong();
        orderService.cancelOrder(orderId, "Payment failed");
    }
}
```

---

## Praktik Tapşırıqlar

1. **Stripe-modelindən ilhamlanan webhook sistem:** `user.created`, `order.shipped`, `payment.failed` event-lərini olan bir webhook sistemi yarat. Postman ilə test et.

2. **Retry mexanizmi testi:** Client URL-ə `https://httpstat.us/500` istifadə et. Exponential backoff ilə 6 cəhddən sonra `FAILED_PERMANENTLY` statusuna keçdiyini yoxla.

3. **HMAC security test:** Signature yanlış olduqda 401 qaytar. `curl -H "X-Webhook-Signature: sha256=invalid" ...` ilə test et.

4. **Dashboard:** `/admin/webhooks/deliveries` endpoint-i yarat. Status, attempt count, son xəta, növbəti retry vaxtını göstər.

5. **Idempotency test:** Eyni `X-Webhook-Delivery-Id` ilə iki request göndər. İkinci request emal olunmamalı, lakin 200 qaytarmalıdır.

---

## Əlaqəli Mövzular

- `104-sse-server-sent-events.md` — Server-dən client-ə real-time push (alternativ pattern)
- `106-background-jobs-patterns.md` — Async emal üçün job patterns
- `java/advanced/16-outbox-pattern.md` — Webhook delivery üçün Outbox pattern
- `java/advanced/06-cloud-resilience4j.md` — HTTP çağırışlarında Circuit Breaker
