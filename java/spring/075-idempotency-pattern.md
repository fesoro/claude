# 075 — Idempotency Pattern — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Idempotency nədir?](#idempotency-nədir)
2. [Idempotency Key strategiyası](#idempotency-key-strategiyası)
3. [Spring Boot-da Idempotency tətbiqi](#spring-boot-da-idempotency-tətbiqi)
4. [Database-based Idempotency](#database-based-idempotency)
5. [Redis-based Idempotency](#redis-based-idempotency)
6. [Outbox Pattern ilə Idempotency](#outbox-pattern-ilə-idempotency)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Idempotency nədir?

**Idempotency** — eyni əməliyyatın bir dəfə və ya çox dəfə icra edilməsinin eyni nəticəni verməsi.

```
Nümunə — ödəniş sistemi:
  Client → POST /payments {amount: 100}
  Network xətası → Client bilmir nə baş verdi
  Client → POST /payments {amount: 100}  ← yenidən göndərir
  
  Idempotency olmadan:
    → İki dəfə 100 silinir → müştəri 200 itirdi!
  
  Idempotency ilə:
    → İlk sorğu işləndi → nəticə saxlanıldı
    → İkinci sorğu → "bu artıq işlənib" → saxlanılan nəticəni qaytarır
    → Müştəri yalnız 100 itirdi ✅

Idempotent vs Non-idempotent HTTP metodları:
  Idempotent (standart):
    GET  → hər zaman eyni data oxuyur
    PUT  → resurs yaradır/əvəz edir (eyni nəticə)
    DELETE → resurs silinib, yenidən silmək 404 ya 200 (eyni son vəziyyət)

  Non-idempotent:
    POST → hər dəfə yeni resurs yarada bilər!
    PATCH → bəzən idempotent deyil (increment əməliyyatı)
```

---

## Idempotency Key strategiyası

```
Idempotency Key necə işləyir:
  1. Client unikal key yaradır (UUID)
  2. Hər sorğuda header-da göndərir: Idempotency-Key: uuid
  3. Server ilk dəfə:
       → Key yoxdur → əməliyyatı icra et
       → Nəticəni key ilə saxla
       → Response qaytar
  4. Server ikinci dəfə (eyni key):
       → Key var → saxlanılan nəticəni qaytar
       → Yeni əməliyyat icra etmə!

Key generasiyası — client tərəfindəki:
  String idempotencyKey = UUID.randomUUID().toString();
  // Hər unikal biznes əməliyyatı üçün bir key
  // Retry-da eyni key istifadə et!
  // Yeni əməliyyat üçün yeni key yarat

TTL (Time To Live):
  Key nə qədər saxlanılsın?
  → Çox qısa: client timeout-dan uzun olmalı (min 24s)
  → Praktika: 24 saat - 7 gün
  → Stripe: 24 saat
  → Stripe sandbox: sonsuz (test üçün)
```

---

## Spring Boot-da Idempotency tətbiqi

```java
// ─── Idempotency Filter — bütün POST sorğuları üçün ───
@Component
@Order(1)
public class IdempotencyFilter extends OncePerRequestFilter {

    private final IdempotencyService idempotencyService;
    private final ObjectMapper objectMapper;

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain chain) throws IOException, ServletException {

        // Yalnız POST, PATCH metodları üçün
        if (!requiresIdempotency(request)) {
            chain.doFilter(request, response);
            return;
        }

        String idempotencyKey = request.getHeader("Idempotency-Key");

        // Key yoxdursa — icazə ver (ya da error qaytar — siyasətdən asılı)
        if (idempotencyKey == null || idempotencyKey.isBlank()) {
            chain.doFilter(request, response);
            return;
        }

        // Əvvəlki nəticə varmı?
        Optional<IdempotencyRecord> existing =
            idempotencyService.findByKey(idempotencyKey);

        if (existing.isPresent()) {
            // Saxlanılan cavabı qaytar
            IdempotencyRecord record = existing.get();
            response.setStatus(record.getStatusCode());
            response.setContentType(MediaType.APPLICATION_JSON_VALUE);
            response.addHeader("Idempotency-Replayed", "true");
            response.getWriter().write(record.getResponseBody());
            return;
        }

        // Response-u cache-ə almaq üçün wrapper
        CachingResponseWrapper responseWrapper = new CachingResponseWrapper(response);

        try {
            chain.doFilter(request, responseWrapper);
        } finally {
            // Uğurlu cavabı saxla (2xx)
            int status = responseWrapper.getStatus();
            if (status >= 200 && status < 300) {
                String responseBody = responseWrapper.getCapturedResponse();
                idempotencyService.save(idempotencyKey, status, responseBody);
            }

            // Real response-a yaz
            responseWrapper.copyBodyToResponse();
        }
    }

    private boolean requiresIdempotency(HttpServletRequest request) {
        String method = request.getMethod();
        return "POST".equals(method) || "PATCH".equals(method);
    }
}

// ─── Response wrapper — response body cache-ə almaq üçün ─
public class CachingResponseWrapper extends HttpServletResponseWrapper {

    private final ByteArrayOutputStream buffer = new ByteArrayOutputStream();
    private final PrintWriter writer = new PrintWriter(buffer, true);

    public CachingResponseWrapper(HttpServletResponse response) {
        super(response);
    }

    @Override
    public PrintWriter getWriter() {
        return writer;
    }

    @Override
    public ServletOutputStream getOutputStream() {
        return new ServletOutputStream() {
            @Override
            public void write(int b) {
                buffer.write(b);
            }

            @Override
            public boolean isReady() { return true; }

            @Override
            public void setWriteListener(WriteListener listener) {}
        };
    }

    public String getCapturedResponse() {
        writer.flush();
        return buffer.toString(StandardCharsets.UTF_8);
    }

    public void copyBodyToResponse() throws IOException {
        super.getResponse().getWriter().write(getCapturedResponse());
    }
}

// ─── Idempotency Service ──────────────────────────────────
@Service
@Transactional
public class IdempotencyService {

    private final IdempotencyRepository repository;

    public Optional<IdempotencyRecord> findByKey(String key) {
        return repository.findByIdempotencyKey(key);
    }

    public void save(String key, int statusCode, String responseBody) {
        IdempotencyRecord record = IdempotencyRecord.builder()
            .idempotencyKey(key)
            .statusCode(statusCode)
            .responseBody(responseBody)
            .createdAt(Instant.now())
            .expiresAt(Instant.now().plus(24, ChronoUnit.HOURS))
            .build();
        repository.save(record);
    }
}
```

---

## Database-based Idempotency

```java
// ─── Idempotency Record Entity ────────────────────────────
@Entity
@Table(
    name = "idempotency_records",
    indexes = {
        @Index(name = "idx_idempotency_key", columnList = "idempotency_key", unique = true),
        @Index(name = "idx_expires_at", columnList = "expires_at")
    }
)
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class IdempotencyRecord {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "idempotency_key", nullable = false, unique = true, length = 255)
    private String idempotencyKey;

    @Column(name = "status_code", nullable = false)
    private int statusCode;

    @Column(name = "response_body", columnDefinition = "TEXT")
    private String responseBody;

    @Column(name = "created_at", nullable = false)
    private Instant createdAt;

    @Column(name = "expires_at", nullable = false)
    private Instant expiresAt;
}

// ─── Repository ───────────────────────────────────────────
@Repository
public interface IdempotencyRepository extends JpaRepository<IdempotencyRecord, Long> {

    Optional<IdempotencyRecord> findByIdempotencyKey(String key);

    // Vaxtı keçmiş qeydləri sil (scheduled cleanup)
    @Modifying
    @Query("DELETE FROM IdempotencyRecord r WHERE r.expiresAt < :now")
    int deleteExpired(@Param("now") Instant now);
}

// ─── Scheduled cleanup ────────────────────────────────────
@Component
public class IdempotencyCleanupTask {

    private final IdempotencyRepository repository;

    @Scheduled(cron = "0 0 * * * *") // Hər saat
    @Transactional
    public void cleanupExpired() {
        int deleted = repository.deleteExpired(Instant.now());
        log.info("Vaxtı keçmiş {} idempotency qeydi silindi", deleted);
    }
}

// ─── SQL Migration ────────────────────────────────────────
/*
-- V5__create_idempotency_records.sql
CREATE TABLE idempotency_records (
    id            BIGSERIAL PRIMARY KEY,
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    status_code   INTEGER NOT NULL,
    response_body TEXT,
    created_at    TIMESTAMP WITH TIME ZONE NOT NULL,
    expires_at    TIMESTAMP WITH TIME ZONE NOT NULL
);

CREATE INDEX idx_idempotency_expires_at ON idempotency_records (expires_at);
*/

// ─── Database-level unikal constraint ilə idempotency ────
// Yanaşma 2: Biznes səviyyəsindəki unikal constraint

@Entity
@Table(
    name = "payments",
    uniqueConstraints = {
        @UniqueConstraint(
            name = "uk_payments_idempotency",
            columnNames = "idempotency_key"
        )
    }
)
public class Payment {

    @Id
    @GeneratedValue(strategy = GenerationType.UUID)
    private String id;

    @Column(name = "idempotency_key", unique = true)
    private String idempotencyKey;

    private BigDecimal amount;
    private String currency;
    private String status;
}

@Service
public class PaymentService {

    public PaymentResponse processPayment(PaymentRequest request, String idempotencyKey) {
        // Unikal constraint violation → əvvəlki nəticəni qaytar
        try {
            Payment payment = Payment.builder()
                .idempotencyKey(idempotencyKey)
                .amount(request.amount())
                .currency(request.currency())
                .status("PENDING")
                .build();
            payment = paymentRepository.save(payment);

            // Xarici ödəniş gateway-i çağır
            GatewayResponse gatewayResponse = paymentGateway.charge(payment);
            payment.setStatus(gatewayResponse.status());
            payment = paymentRepository.save(payment);

            return PaymentResponse.from(payment);

        } catch (DataIntegrityViolationException e) {
            // Eyni idempotency key ilə ödəniş artıq var
            return paymentRepository.findByIdempotencyKey(idempotencyKey)
                .map(PaymentResponse::from)
                .orElseThrow(() -> new IllegalStateException("Constraint violation amma qeyd tapılmadı"));
        }
    }
}
```

---

## Redis-based Idempotency

```java
// ─── Redis ilə sürətli idempotency ───────────────────────
@Service
public class RedisIdempotencyService {

    private final RedisTemplate<String, String> redisTemplate;
    private final ObjectMapper objectMapper;

    private static final String KEY_PREFIX = "idempotency:";
    private static final Duration TTL = Duration.ofHours(24);
    private static final String PROCESSING = "PROCESSING";

    // Lua script — atomic check-and-set
    private static final String SET_IF_NOT_EXISTS_SCRIPT = """
        local key = KEYS[1]
        local value = ARGV[1]
        local ttl = tonumber(ARGV[2])
        
        local existing = redis.call('GET', key)
        if existing then
            return existing
        end
        
        redis.call('SETEX', key, ttl, value)
        return nil
        """;

    /**
     * Əməliyyatı başlat — processing marker qoy
     * @return true əgər bu sorğu ilk dəfədirsə, false əgər artıq işlənibsə
     */
    public IdempotencyResult tryAcquire(String idempotencyKey) {
        String redisKey = KEY_PREFIX + idempotencyKey;

        DefaultRedisScript<String> script = new DefaultRedisScript<>(
            SET_IF_NOT_EXISTS_SCRIPT, String.class);

        String existing = redisTemplate.execute(script,
            List.of(redisKey),
            PROCESSING,
            String.valueOf(TTL.toSeconds())
        );

        if (existing == null) {
            // İlk sorğu — işlə
            return IdempotencyResult.firstRequest();
        }

        if (PROCESSING.equals(existing)) {
            // Hələ işlənir — gözlə
            return IdempotencyResult.processing();
        }

        // Artıq tamamlanıb — saxlanılan nəticəni qaytar
        try {
            CachedResponse cached = objectMapper.readValue(existing, CachedResponse.class);
            return IdempotencyResult.alreadyProcessed(cached);
        } catch (JsonProcessingException e) {
            throw new IdempotencyException("Redis-dən cavab oxuna bilmədi", e);
        }
    }

    /**
     * Əməliyyat tamamlandıqda nəticəni saxla
     */
    public void complete(String idempotencyKey, int statusCode, Object responseBody) {
        String redisKey = KEY_PREFIX + idempotencyKey;
        try {
            CachedResponse cached = new CachedResponse(statusCode,
                objectMapper.writeValueAsString(responseBody));
            String serialized = objectMapper.writeValueAsString(cached);
            redisTemplate.opsForValue().set(redisKey, serialized, TTL);
        } catch (JsonProcessingException e) {
            throw new IdempotencyException("Nəticəni serialize etmək mümkün olmadı", e);
        }
    }

    /**
     * Əməliyyat xəta ilə bitdikdə key-i sil (retry mümkün olsun)
     */
    public void release(String idempotencyKey) {
        redisTemplate.delete(KEY_PREFIX + idempotencyKey);
    }
}

// ─── Idempotency Result sealed class ─────────────────────
public sealed interface IdempotencyResult
    permits IdempotencyResult.FirstRequest,
            IdempotencyResult.Processing,
            IdempotencyResult.AlreadyProcessed {

    record FirstRequest() implements IdempotencyResult {}
    record Processing() implements IdempotencyResult {}
    record AlreadyProcessed(CachedResponse response) implements IdempotencyResult {}

    static IdempotencyResult firstRequest() { return new FirstRequest(); }
    static IdempotencyResult processing() { return new Processing(); }
    static IdempotencyResult alreadyProcessed(CachedResponse r) {
        return new AlreadyProcessed(r);
    }
}

// ─── Redis Idempotency Annotation + AOP ──────────────────
@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
public @interface Idempotent {
    String keyHeader() default "Idempotency-Key";
}

@Aspect
@Component
public class IdempotencyAspect {

    private final RedisIdempotencyService idempotencyService;

    @Around("@annotation(idempotent)")
    public Object around(ProceedingJoinPoint joinPoint, Idempotent idempotent) throws Throwable {
        // HttpServletRequest-dən key əldə et
        HttpServletRequest request = ((ServletRequestAttributes)
            RequestContextHolder.currentRequestAttributes()).getRequest();

        String key = request.getHeader(idempotent.keyHeader());

        if (key == null) {
            // Key yoxdur → normal icra
            return joinPoint.proceed();
        }

        IdempotencyResult result = idempotencyService.tryAcquire(key);

        return switch (result) {
            case IdempotencyResult.FirstRequest fr -> {
                try {
                    Object response = joinPoint.proceed();
                    // Nəticəni saxla
                    idempotencyService.complete(key, 200, response);
                    yield response;
                } catch (Exception e) {
                    // Xəta oldu → key-i sil ki retry olsun
                    idempotencyService.release(key);
                    throw e;
                }
            }
            case IdempotencyResult.AlreadyProcessed ap ->
                // Saxlanılan nəticəni qaytar
                ap.response().body();
            case IdempotencyResult.Processing p ->
                throw new IdempotencyConflictException("Sorğu hələ işlənir, bir az gözləyin");
        };
    }
}

// ─── Controller-də istifadə ──────────────────────────────
@RestController
@RequestMapping("/api/payments")
public class PaymentController {

    @PostMapping
    @Idempotent
    public ResponseEntity<PaymentResponse> createPayment(
            @RequestBody @Valid PaymentRequest request) {
        PaymentResponse response = paymentService.process(request);
        return ResponseEntity.ok(response);
    }
}
```

---

## Outbox Pattern ilə Idempotency

```java
// ─── Outbox Pattern — exactly-once message delivery ──────
// Problem: DB save + Kafka publish = 2 phase commit lazım
// Həll: Outbox table-a yaz → poller publish edir

@Entity
@Table(name = "outbox_events")
public class OutboxEvent {

    @Id
    @GeneratedValue(strategy = GenerationType.UUID)
    private String id;

    @Column(name = "aggregate_id", nullable = false)
    private String aggregateId;

    @Column(name = "aggregate_type", nullable = false)
    private String aggregateType;

    @Column(name = "event_type", nullable = false)
    private String eventType;

    @Column(name = "payload", columnDefinition = "TEXT", nullable = false)
    private String payload;

    @Column(name = "idempotency_key", unique = true, nullable = false)
    private String idempotencyKey; // Duplicate publish-i önlər

    @Enumerated(EnumType.STRING)
    private OutboxStatus status; // PENDING, PUBLISHED, FAILED

    @Column(name = "created_at")
    private Instant createdAt;

    @Column(name = "published_at")
    private Instant publishedAt;
}

@Service
@Transactional
public class OrderService {

    private final OrderRepository orderRepository;
    private final OutboxEventRepository outboxRepository;
    private final ObjectMapper objectMapper;

    public Order createOrder(CreateOrderRequest request, String idempotencyKey) {
        // Idempotency check — outbox table üzərindən
        if (outboxRepository.existsByIdempotencyKey(idempotencyKey)) {
            // Eyni key ilə event artıq var
            return orderRepository.findByIdempotencyKey(idempotencyKey)
                .orElseThrow(() -> new NotFoundException("Order tapılmadı"));
        }

        // Order yarat
        Order order = Order.builder()
            .customerId(request.customerId())
            .idempotencyKey(idempotencyKey)
            .status(OrderStatus.PENDING)
            .build();
        order = orderRepository.save(order);

        // Outbox-a event yaz (eyni transaction-da!)
        OrderCreatedEvent event = new OrderCreatedEvent(order.getId(),
            order.getCustomerId(), order.getCreatedAt());

        OutboxEvent outbox = OutboxEvent.builder()
            .aggregateId(order.getId())
            .aggregateType("Order")
            .eventType("OrderCreated")
            .payload(objectMapper.writeValueAsString(event))
            .idempotencyKey(idempotencyKey)
            .status(OutboxStatus.PENDING)
            .createdAt(Instant.now())
            .build();
        outboxRepository.save(outbox);

        // Commit → DB-ə yazıldı. Kafka-ya publish ayrı poller edir.
        return order;
    }
}

// ─── Outbox Poller — SKIP LOCKED ilə concurrent-safe ────
@Component
public class OutboxPoller {

    private final OutboxEventRepository outboxRepository;
    private final KafkaTemplate<String, String> kafkaTemplate;

    @Scheduled(fixedDelay = 1000)
    @Transactional
    public void pollAndPublish() {
        // SKIP LOCKED → çoxlu pod-da eyni event 2x publish olmaz
        List<OutboxEvent> events = outboxRepository
            .findPendingWithLock(Pageable.ofSize(50));

        for (OutboxEvent event : events) {
            try {
                kafkaTemplate.send(
                    event.getEventType(),
                    event.getAggregateId(),
                    event.getPayload()
                ).get(5, TimeUnit.SECONDS);

                event.setStatus(OutboxStatus.PUBLISHED);
                event.setPublishedAt(Instant.now());
            } catch (Exception e) {
                log.error("Event publish xətası: {}", event.getId(), e);
                event.setStatus(OutboxStatus.FAILED);
            }
        }
    }
}

// ─── SKIP LOCKED Query ────────────────────────────────────
@Repository
public interface OutboxEventRepository extends JpaRepository<OutboxEvent, String> {

    @Query(value = """
        SELECT * FROM outbox_events
        WHERE status = 'PENDING'
        ORDER BY created_at
        LIMIT :#{#pageable.pageSize}
        FOR UPDATE SKIP LOCKED
        """, nativeQuery = true)
    List<OutboxEvent> findPendingWithLock(Pageable pageable);
}

// ─── Consumer tərəfindəki idempotency ─────────────────────
@KafkaListener(topics = "OrderCreated")
public void handleOrderCreated(
        @Payload String payload,
        @Header(KafkaHeaders.RECEIVED_KEY) String key) {

    // Message key = idempotency key (aggregateId + eventId)
    if (processedEventRepository.existsByEventKey(key)) {
        log.info("Artıq işlənmiş event, skip edildi: {}", key);
        return;
    }

    OrderCreatedEvent event = objectMapper.readValue(payload, OrderCreatedEvent.class);
    // Biznes məntiqi icra et
    inventoryService.reserve(event);

    // İşlənmiş kimi qeyd et
    processedEventRepository.save(new ProcessedEvent(key, Instant.now()));
}
```

---

## İntervyu Sualları

### 1. Idempotency nədir və niyə lazımdır?
**Cavab:** Eyni əməliyyatın bir dəfə ya daha çox icra edilməsinin nəticəsinin eyni olması. Lazım olma səbəbləri: (1) **Şəbəkə xətaları** — client bilmir server sorğunu aldımı, retry edir; (2) **Timeout** — server sorğunu işlədi amma cavab çatmadı; (3) **Retry mexanizmləri** — Spring Retry, exponential backoff — eyni sorğu birdən çox gedə bilər. Ödəniş, email göndərmə, inventar azaltma kimi əməliyyatlarda duplicate kritik problemdir.

### 2. Idempotency Key necə işləyir?
**Cavab:** Client hər unikal biznes əməliyyatı üçün UUID yaradır → `Idempotency-Key: <uuid>` header-da göndərir. Server ilk aldıqda: əməliyyatı icra edir, nəticəni key ilə saxlayır. Eyni key ilə ikinci sorğuda: əməliyyatı icra etmir, saxlanılan nəticəni qaytarır. Müddət dolduqdan sonra (24 saat) key silinir. Retry edərkən client eyni key-i istifadə etməlidir — yeni key yeni əməliyyat deməkdir.

### 3. Database vs Redis Idempotency fərqi?
**Cavab:** **Database** — persistent, restart-safe; amma DB-yə yazma yavaşdır, UNIQUE constraint race condition-a qarşı qoruyur; retry-larda reliable. **Redis** — çox sürətli (memory), SET NX (Set if Not Exists) ilə atomic; amma Redis restart-da itir (persistence konfiqurasiyasından asılı). Hybrid: Redis ilk check (sürət), DB fallback (etibarlılıq). TTL: Redis-də daha sadə. Production-da kritik ödəniş kimi sistemlər üçün DB; API rate limiting kimi sürət tələb olunanda Redis.

### 4. Outbox Pattern idempotency ilə necə əlaqəlidir?
**Cavab:** Outbox Pattern — "exactly once" mesaj çatdırılmasının həlli. Problem: DB-yə yaz + Kafka-ya publish = 2 separate system, distributed transaction yoxdur. Həll: (1) Order-i DB-ə yaz; (2) Eyni transaction-da outbox_events table-a event yaz; (3) Poller outbox-u oxuyur, Kafka-ya publish edir, `SKIP LOCKED` ilə concurrent publish-dən qorunur; (4) Consumer tərəfindəki idempotency — işlənmiş event ID-lərini saxla, duplicate-ı skip et. Bu kombinasiya at-most-once + at-least-once = exactly-once verir.

### 5. POST vs PUT idempotency fərqi?
**Cavab:** **PUT** HTTP standartına görə idempotent-dir — `PUT /orders/123 {status: CONFIRMED}` dəfələrlə çağırılsa eyni nəticə (order 123 CONFIRMED olur). **POST** idempotent deyil — `POST /orders` hər dəfə yeni order yarada bilər. Buna görə Idempotency Key yanaşması POST üçün lazımdır. **PATCH** bəzən idempotent deyil: `PATCH /account {balance: +50}` — bu hər dəfə 50 əlavə edir! Amma `PATCH /account {balance: 150}` — idempotent (nəticə sabitdir). Qayda: absolut dəyər set etmək idempotent, nisbi dəyişiklik idempotent deyil.

*Son yenilənmə: 2026-04-10*
