# Background Jobs Patterns (Senior)

## İcmal

Background job — istifadəçi request-indən kənar, ayrı thread-də işləyən tapşırıqdır. Spring Boot-da bunu həyata keçirməyin bir neçə yolu var: sadə `@Scheduled`-dan başlayaraq Kafka consumer-ə qədər.

Hər pattern öz use-case-inə uyğundur. Yanlış seçim ya performance problemlərinə, ya da data itkisinə gətirib çıxarır.

---

## Niyə Vacibdir

Bəzi əməliyyatlar request-response cycle-da işlənməməlidir:
- Email göndərmək (500ms+ gözləmə)
- PDF generate etmək (CPU-intensive)
- Xarici API çağırışları (network latency)
- Hesabat yaratmaq (uzun sürən DB sorğular)
- Toplu data emalı (batch processing)

Bu tapşırıqları background-da icra etmək UX-i yaxşılaşdırır və sistemin ölçəklənməsinə imkan verir.

---

## Əsas Anlayışlar

**4 əsas pattern:**

| Pattern | Persistence | Scale | Complexity | Use-case |
|---|---|---|---|---|
| `@Scheduled` | Yox | Single instance | Aşağı | Cleanup, report |
| `@Async` | Yox | Shared thread pool | Aşağı | Fire-and-forget |
| Persistent Queue (DB/JobRunr) | Var | Limited | Orta | Retry, durability |
| Kafka Consumer | Var | Horizontal | Yüksək | High throughput |

---

## Praktik Baxış

**Trade-off-lar:**
- `@Scheduled` — sadədir amma cluster-da hər instance çalışdırır (ShedLock lazımdır)
- `@Async` — restart zamanı in-flight task-lar itirilir
- Persistent Queue — durability var, lakin DB-yə yük artır
- Kafka — ən güclü, amma operational overhead böyükdür

**Common mistakes:**
- `@Scheduled` ilə cluster-da duplicate execution
- `@Async`-dan exception qayıtmasını handle etməmək
- Thread pool-u düzgün konfiqurasiya etməmək (default 8 thread bəzən yetmir)
- Graceful shutdown-u nəzərə almamaq (deploy zamanı running job-u kill etmək)

---

## Nümunələr

### Ümumi Nümunə

E-commerce sistemi: gecə 2-də bütün expired session-ları sil (`@Scheduled`), sifariş verildikdə confirmation email göndər (`@Async`), ödəniş prosesi uğursuz olarsa exponential backoff ilə retry et (Persistent Queue).

### Kod Nümunəsi

#### Pattern 1 — @Scheduled ilə ShedLock

**Problem:** Cluster-da (3 instance) `@Scheduled` metod hər instance-da işləyir → 3 dəfə çalışır.

**Həll:** ShedLock — DB və ya Redis-də distributed lock istifadə edir.

```xml
<!-- pom.xml -->
<dependency>
    <groupId>net.javacrumbs.shedlock</groupId>
    <artifactId>shedlock-spring</artifactId>
    <version>5.13.0</version>
</dependency>
<dependency>
    <groupId>net.javacrumbs.shedlock</groupId>
    <artifactId>shedlock-provider-redis-spring</artifactId>
    <version>5.13.0</version>
</dependency>
```

**ShedLock konfiqurasiyası:**

```java
@Configuration
@EnableSchedulerLock(defaultLockAtMostFor = "10m")
public class ShedLockConfig {

    @Bean
    public LockProvider lockProvider(RedisConnectionFactory connectionFactory) {
        return new RedisLockProvider(connectionFactory, "production");
    }
}
```

**Scheduled metod:**

```java
@Service
@Slf4j
public class DataCleanupService {

    private final SessionRepository sessionRepo;
    private final OrderRepository orderRepo;

    // Hər gün saat 02:00-da işləyir
    // lockAtMostFor: job crash olsa belə 15 dəqiqədən artıq lock saxlamır
    // lockAtLeastFor: çox sürətli bitərsə yenə də 5 dəqiqə lock saxlayır (duplicate protection)
    @Scheduled(cron = "0 0 2 * * *")
    @SchedulerLock(
        name = "cleanExpiredSessions",
        lockAtMostFor = "15m",
        lockAtLeastFor = "5m"
    )
    public void cleanExpiredSessions() {
        log.info("Starting expired session cleanup");
        Instant cutoff = Instant.now().minus(30, ChronoUnit.DAYS);
        int deleted = sessionRepo.deleteByLastAccessBefore(cutoff);
        log.info("Deleted {} expired sessions", deleted);
    }

    @Scheduled(cron = "0 30 1 * * *")
    @SchedulerLock(name = "generateDailyReport", lockAtMostFor = "1h")
    public void generateDailyReport() {
        log.info("Generating daily sales report");
        // Report generation logic
    }
}
```

---

#### Pattern 2 — @Async ilə Thread Pool

**Thread Pool konfiqurasiyası:**

```java
@Configuration
@EnableAsync
public class AsyncConfig implements AsyncConfigurer {

    @Bean(name = "emailTaskExecutor")
    public Executor emailTaskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(5);       // Minimum thread sayı
        executor.setMaxPoolSize(15);        // Maximum thread sayı
        executor.setQueueCapacity(100);     // Queue-dan sonra yeni thread yaradılır
        executor.setThreadNamePrefix("email-");
        executor.setKeepAliveSeconds(60);   // Idle thread-lərin yaşam müddəti
        // Queue dolu, max thread-ə çatılıbsa — caller thread-də işlə
        executor.setRejectedExecutionHandler(new ThreadPoolExecutor.CallerRunsPolicy());
        executor.initialize();
        return executor;
    }

    @Bean(name = "reportTaskExecutor")
    public Executor reportTaskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(2);
        executor.setMaxPoolSize(5);
        executor.setQueueCapacity(50);
        executor.setThreadNamePrefix("report-");
        executor.initialize();
        return executor;
    }

    // Uncaught exception handler
    @Override
    public AsyncUncaughtExceptionHandler getAsyncUncaughtExceptionHandler() {
        return (throwable, method, params) -> {
            log.error("Async method '{}' threw exception: {}",
                method.getName(), throwable.getMessage(), throwable);
            // Alert göndər, metrics yaz
        };
    }
}
```

**Async service:**

```java
@Service
@Slf4j
public class NotificationService {

    private final EmailClient emailClient;
    private final SmsClient smsClient;

    // Fire-and-forget — nəticəsi lazım deyil
    @Async("emailTaskExecutor")
    public void sendOrderConfirmation(Order order) {
        log.info("Sending order confirmation email: orderId={}", order.getId());
        emailClient.send(
            order.getCustomerEmail(),
            "Order Confirmed #" + order.getId(),
            buildEmailBody(order)
        );
    }

    // Nəticəsi lazım olan async metod
    @Async("reportTaskExecutor")
    public CompletableFuture<ReportResult> generateSalesReport(LocalDate date) {
        log.info("Generating sales report for {}", date);
        try {
            ReportResult result = buildReport(date);
            return CompletableFuture.completedFuture(result);
        } catch (Exception e) {
            return CompletableFuture.failedFuture(e);
        }
    }
}
```

**Controller-də async nəticəni istifadə et:**

```java
@GetMapping("/reports/sales")
public CompletableFuture<ResponseEntity<ReportResult>> getSalesReport(
        @RequestParam LocalDate date) {
    return notificationService.generateSalesReport(date)
        .thenApply(ResponseEntity::ok)
        .exceptionally(e -> ResponseEntity.internalServerError().build());
}
```

---

#### Pattern 3 — Persistent Job Queue (JobRunr)

**JobRunr** — Java üçün en yaxşı background job library-sidir. Spring Boot integration var, dashboard daxildir, retry/scheduling dəstəkləyir.

```xml
<dependency>
    <groupId>org.jobrunr</groupId>
    <artifactId>jobrunr-spring-boot-3-starter</artifactId>
    <version>7.2.3</version>
</dependency>
```

```yaml
# application.yml
org:
  jobrunr:
    background-job-server:
      enabled: true
      worker-count: 10
    dashboard:
      enabled: true
      port: 8000
    database:
      skip-create: false
```

**Job enqueue et:**

```java
@Service
public class OrderService {

    private final JobScheduler jobScheduler;
    private final InvoiceService invoiceService;

    public Order placeOrder(CreateOrderRequest request) {
        Order order = createOrder(request);

        // Dərhal işlət (async)
        jobScheduler.enqueue(() -> invoiceService.generateInvoice(order.getId()));

        // 5 dəqiqə sonra işlət
        jobScheduler.schedule(
            Instant.now().plusSeconds(300),
            () -> invoiceService.sendInvoiceEmail(order.getId())
        );

        // Recurring job — hər gün saat 9-da
        jobScheduler.scheduleRecurrently(
            "daily-report",
            Cron.daily(9),
            () -> invoiceService.generateDailyReport()
        );

        return order;
    }
}
```

**Job implementation:**

```java
@Service
@Slf4j
public class InvoiceService {

    private final InvoiceRepository invoiceRepo;
    private final PdfGenerator pdfGenerator;
    private final EmailService emailService;

    // JobRunr bu metodu background-da çalışdırır
    // Fail olarsa default olaraq 10 dəfə retry edir
    public void generateInvoice(Long orderId) {
        log.info("Generating invoice for order: {}", orderId);

        Order order = orderRepo.findById(orderId)
            .orElseThrow(() -> new EntityNotFoundException("Order: " + orderId));

        byte[] pdf = pdfGenerator.generateInvoicePdf(order);

        Invoice invoice = new Invoice();
        invoice.setOrderId(orderId);
        invoice.setPdfData(pdf);
        invoice.setGeneratedAt(Instant.now());
        invoiceRepo.save(invoice);
    }
}
```

**Custom retry policy:**

```java
@Job(name = "Send Invoice Email", retries = 5)
public void sendInvoiceEmail(Long orderId) {
    // 5 retry, exponential backoff ilə
    emailService.sendInvoice(orderId);
}
```

---

#### Pattern 4 — Kafka Consumer

```java
@Service
@Slf4j
public class OrderProcessingConsumer {

    private final PaymentService paymentService;
    private final NotificationService notificationService;

    // orders topic-indəki mesajları emal et
    @KafkaListener(
        topics = "orders.created",
        groupId = "order-processor",
        concurrency = "3"   // 3 parallel consumer thread
    )
    @Transactional  // Consumer + DB update — atomik
    public void processOrder(
            @Payload OrderCreatedEvent event,
            @Header(KafkaHeaders.RECEIVED_PARTITION) int partition,
            @Header(KafkaHeaders.OFFSET) long offset,
            Acknowledgment ack) {

        log.info("Processing order: id={}, partition={}, offset={}",
            event.getOrderId(), partition, offset);

        try {
            paymentService.initiatePayment(event.getOrderId());
            notificationService.sendOrderConfirmation(event.getOrderId());

            // Manual acknowledgment — emal bitdikdən sonra commit
            ack.acknowledge();

        } catch (Exception e) {
            log.error("Failed to process order: {}", event.getOrderId(), e);
            // Acknowledge etmə → Kafka offset irəliləmir → retry baş verir
            // Dead Letter Topic konfiqurasiyası ilə max retry-dan sonra DLT-ə keçər
            throw e;
        }
    }
}
```

**Dead Letter Topic konfiqurasiyası:**

```java
@Bean
public DefaultErrorHandler kafkaErrorHandler(KafkaTemplate<String, Object> template) {
    DeadLetterPublishingRecoverer recoverer = new DeadLetterPublishingRecoverer(template,
        (record, ex) -> new TopicPartition(record.topic() + ".DLT",
            record.partition()));

    // 3 retry, 1s/5s/30s intervallarla
    ExponentialBackOffWithMaxRetries backOff = new ExponentialBackOffWithMaxRetries(3);
    backOff.setInitialInterval(1_000L);
    backOff.setMultiplier(5.0);
    backOff.setMaxInterval(30_000L);

    return new DefaultErrorHandler(recoverer, backOff);
}
```

---

#### Graceful Shutdown

```java
@Component
@Slf4j
public class GracefulShutdownHandler {

    private final ThreadPoolTaskExecutor emailExecutor;
    private final ThreadPoolTaskExecutor reportExecutor;

    @PreDestroy
    public void onShutdown() {
        log.info("Initiating graceful shutdown — waiting for running jobs...");

        // Yeni task qəbul etməyi dayandır, mövcud task-ların bitməsini gözlə
        emailExecutor.shutdown();
        reportExecutor.shutdown();

        try {
            if (!emailExecutor.getThreadPoolExecutor().awaitTermination(30, TimeUnit.SECONDS)) {
                log.warn("Email executor did not terminate in 30s — forcing shutdown");
                emailExecutor.getThreadPoolExecutor().shutdownNow();
            }
            if (!reportExecutor.getThreadPoolExecutor().awaitTermination(30, TimeUnit.SECONDS)) {
                log.warn("Report executor did not terminate in 30s — forcing shutdown");
                reportExecutor.getThreadPoolExecutor().shutdownNow();
            }
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }

        log.info("Graceful shutdown complete");
    }
}
```

---

#### Job Metrics — Actuator ilə expose et

```java
@Component
public class JobMetricsRegistry {

    private final Counter completedJobs;
    private final Counter failedJobs;
    private final Gauge queueSize;

    public JobMetricsRegistry(MeterRegistry registry, JobQueueRepository queueRepo) {
        this.completedJobs = Counter.builder("jobs.completed")
            .description("Total completed background jobs")
            .register(registry);

        this.failedJobs = Counter.builder("jobs.failed")
            .description("Total failed background jobs")
            .register(registry);

        this.queueSize = Gauge.builder("jobs.queue.size",
            queueRepo, r -> r.countByStatus(JobStatus.PENDING))
            .description("Current pending jobs in queue")
            .register(registry);
    }

    public void recordCompleted() { completedJobs.increment(); }
    public void recordFailed() { failedJobs.increment(); }
}
```

---

## Praktik Tapşırıqlar

1. **ShedLock test:** İki Spring Boot instance başlat. `@Scheduled` + `@SchedulerLock` əlavə et. Hər iki instance log-larına bax — yalnız bir instance metodu icra etməlidir.

2. **@Async thread pool stress testi:** `emailTaskExecutor`-un `corePoolSize=2`, `queueCapacity=5` et. 10 parallel request göndər. Thread pool davranışını müşahidə et — bəziləri caller thread-də işləyəcək (`CallerRunsPolicy`).

3. **JobRunr dashboard:** JobRunr əlavə et, dashboard-u açıq saxla (`localhost:8000`). Job enqueue et, real-time dashboard-da izlə. Bir job-u intentionally fail et — retry davranışını izlə.

4. **Kafka DLT testi:** Consumer-i daima exception atdıracaq şəkildə dəyişdir. 3 retry-dan sonra mesajın DLT topic-ə keçdiyini yoxla.

5. **Graceful shutdown:** Server shutdown zamanı 60 saniyə sürdürücü bir job işlət. 30s timeout-dan sonra executor-un forceful shutdown etdiyini log-larda göstər.

---

## Əlaqəli Mövzular

- `105-webhook-delivery.md` — Webhook retry — persistent job queue pattern nümunəsi
- `java/advanced/16-outbox-pattern.md` — Event-driven background processing
- `java/advanced/06-cloud-resilience4j.md` — Retry, Circuit Breaker
- `java/comparison/frameworks/` — Spring Batch ilə batch processing
