# 70 — Spring @Async — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [@Async nədir?](#async-nədir)
2. [ThreadPoolTaskExecutor konfiqurasiyası](#threadpooltaskexecutor-konfiqurasiyası)
3. [CompletableFuture ilə](#completablefuture-ilə)
4. [Exception idarəetməsi](#exception-idarəetməsi)
5. [Praktik nümunələr](#praktik-nümunələr)
6. [İntervyu Sualları](#intervyu-sualları)

---

## @Async nədir?

**@Async** — metodun ayrı thread-də işləməsini təmin edən Spring annotasiyası. Uzun süren əməliyyatları (mail, notification, hesabat) caller thread-i bloklamadan icra etmək üçün istifadə olunur.

```java
@SpringBootApplication
@EnableAsync // ← Aktivləşdirmək üçün
public class App { }

@Service
public class NotificationService {

    // YANLIŞ — caller thread bloklanır
    public void sendNotificationSync(String userId, String message) {
        // 2 saniyə ləngiməni düşünün
        emailService.send(userId, message);
        smsService.send(userId, message);
        pushService.send(userId, message);
    }

    // DOĞRU — ayrı thread-də işləyir
    @Async
    public void sendNotification(String userId, String message) {
        emailService.send(userId, message);
    }
}

// Controller
@PostMapping("/orders")
public ResponseEntity<Order> placeOrder(@RequestBody OrderRequest request) {
    Order order = orderService.create(request);
    notificationService.sendNotification(order.getUserId(), "Sifariş qəbul edildi");
    // Notification gözlənilmir — dərhal cavab verilir
    return ResponseEntity.ok(order);
}
```

**Məhdudiyyətlər:**
- Eyni sinif daxilindən çağırma işləmir (self-invocation — AOP proxy problemi)
- `void` ya da `Future`/`CompletableFuture` qaytarmalıdır
- `private` metodlarda işləmir

---

## ThreadPoolTaskExecutor konfiqurasiyası

```java
@Configuration
@EnableAsync
public class AsyncConfig implements AsyncConfigurer {

    // Default executor
    @Override
    @Bean(name = "taskExecutor")
    public Executor getAsyncExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();

        // Core thread-lər həmişə aktiv
        executor.setCorePoolSize(5);

        // Maksimum thread sayı (queue dolduqda)
        executor.setMaxPoolSize(20);

        // Queue — core thread-lər məşğul olduqda
        executor.setQueueCapacity(100);

        // Thread adı prefix (debug üçün)
        executor.setThreadNamePrefix("async-");

        // Queue dolub, max thread-ə çatdıqda davranış
        // CallerRunsPolicy — caller thread-i çağırır (yavaşlama)
        // AbortPolicy — exception atır
        executor.setRejectedExecutionHandler(new ThreadPoolExecutor.CallerRunsPolicy());

        // Graceful shutdown — işdə olan task-ların bitməsini gözlə
        executor.setWaitForTasksToCompleteOnShutdown(true);
        executor.setAwaitTerminationSeconds(30);

        executor.initialize();
        return executor;
    }

    // Async exception handler
    @Override
    public AsyncUncaughtExceptionHandler getAsyncUncaughtExceptionHandler() {
        return new CustomAsyncExceptionHandler();
    }
}

// Fərqli executor-lar — müxtəlif task tipləri üçün
@Configuration
public class MultipleExecutorConfig {

    @Bean(name = "emailExecutor")
    public TaskExecutor emailExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(2);
        executor.setMaxPoolSize(5);
        executor.setThreadNamePrefix("email-");
        executor.initialize();
        return executor;
    }

    @Bean(name = "reportExecutor")
    public TaskExecutor reportExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(1);
        executor.setMaxPoolSize(3);
        executor.setThreadNamePrefix("report-");
        executor.initialize();
        return executor;
    }
}

// İstifadəsi
@Service
public class EmailService {

    @Async("emailExecutor") // Spesifik executor
    public void sendEmail(String to, String subject) {
        // emailExecutor thread-pool-unda işləyir
    }
}

@Service
public class ReportService {

    @Async("reportExecutor") // Spesifik executor
    public void generateReport(Long reportId) {
        // reportExecutor thread-pool-unda işləyir
    }
}
```

---

## CompletableFuture ilə

```java
@Service
public class DataAggregationService {

    private final UserService userService;
    private final OrderService orderService;
    private final ReviewService reviewService;

    // Nəticə gözlənildikdə CompletableFuture istifadə et
    @Async
    public CompletableFuture<User> getUserAsync(Long userId) {
        User user = userService.findById(userId);
        return CompletableFuture.completedFuture(user);
    }

    @Async
    public CompletableFuture<List<Order>> getOrdersAsync(Long userId) {
        List<Order> orders = orderService.findByUserId(userId);
        return CompletableFuture.completedFuture(orders);
    }

    @Async
    public CompletableFuture<List<Review>> getReviewsAsync(Long userId) {
        List<Review> reviews = reviewService.findByUserId(userId);
        return CompletableFuture.completedFuture(reviews);
    }

    // Paralel icra — 3 sorğu eyni anda
    public UserProfileDto getUserProfile(Long userId) throws Exception {
        CompletableFuture<User> userFuture = getUserAsync(userId);
        CompletableFuture<List<Order>> ordersFuture = getOrdersAsync(userId);
        CompletableFuture<List<Review>> reviewsFuture = getReviewsAsync(userId);

        // Hamısının bitməsini gözlə
        CompletableFuture.allOf(userFuture, ordersFuture, reviewsFuture).join();

        User user = userFuture.get();
        List<Order> orders = ordersFuture.get();
        List<Review> reviews = reviewsFuture.get();

        return new UserProfileDto(user, orders, reviews);
        // Serial: 3*500ms = 1500ms
        // Parallel: max(500ms, 500ms, 500ms) = 500ms
    }
}
```

---

## Exception idarəetməsi

```java
// void metodlar üçün — AsyncUncaughtExceptionHandler
@Component
public class CustomAsyncExceptionHandler implements AsyncUncaughtExceptionHandler {

    private static final Logger log =
        LoggerFactory.getLogger(CustomAsyncExceptionHandler.class);

    @Override
    public void handleUncaughtException(Throwable ex,
                                         Method method,
                                         Object... params) {
        log.error("Async metod xətası: {}.{}",
            method.getDeclaringClass().getSimpleName(),
            method.getName(), ex);

        // Alert göndər
        alertService.sendAlert("Async xəta: " + ex.getMessage());
    }
}

// CompletableFuture metodlar üçün — exceptionally()
@Service
public class SafeAsyncService {

    @Async
    public CompletableFuture<String> riskyOperation(String input) {
        // Exception CompletableFuture-da saxlanılır
        if (input == null) {
            return CompletableFuture.failedFuture(
                new IllegalArgumentException("Input null ola bilməz"));
        }
        return CompletableFuture.completedFuture(process(input));
    }

    // Çağıran tərəfdə idarə etmək
    public void callRisky(String input) {
        riskyOperation(input)
            .thenAccept(result -> log.info("Nəticə: {}", result))
            .exceptionally(ex -> {
                log.error("Xəta: {}", ex.getMessage());
                return null;
            });
    }
}
```

---

## Praktik nümunələr

```java
@Service
public class OrderProcessingService {

    @Transactional
    public Order placeOrder(OrderRequest request) {
        // 1. Synchronous — database əməliyyatı
        Order order = orderRepository.save(buildOrder(request));

        // 2. Async — transaction commit-dən sonra
        // TransactionalEventListener istifadə et ki, transaction tam bitsin
        applicationEventPublisher.publishEvent(new OrderPlacedEvent(order));

        return order;
    }
}

@Component
public class OrderEventHandler {

    @Async
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void handleOrderPlaced(OrderPlacedEvent event) {
        Order order = event.getOrder();

        // Transaction commit-dən sonra async notification
        emailService.sendOrderConfirmation(order);
        inventoryService.reserveItems(order.getItems());
        analyticsService.trackOrder(order);
    }
}
```

```java
// Batch emal — async paralel işləmə
@Service
public class BatchProcessingService {

    @Async
    public CompletableFuture<BatchResult> processChunk(List<Record> chunk) {
        int successCount = 0;
        int failureCount = 0;

        for (Record record : chunk) {
            try {
                processRecord(record);
                successCount++;
            } catch (Exception e) {
                log.error("Record emal xətası: {}", record.getId(), e);
                failureCount++;
            }
        }

        return CompletableFuture.completedFuture(
            new BatchResult(successCount, failureCount));
    }

    public void processAllRecords(List<Record> allRecords) {
        // 1000-lik chunk-lara böl
        List<List<Record>> chunks = partition(allRecords, 1000);

        List<CompletableFuture<BatchResult>> futures = chunks.stream()
            .map(this::processChunk)
            .collect(Collectors.toList());

        CompletableFuture.allOf(futures.toArray(new CompletableFuture[0]))
            .thenRun(() -> {
                BatchResult total = futures.stream()
                    .map(f -> f.join())
                    .reduce(BatchResult::merge)
                    .orElse(BatchResult.empty());
                log.info("Batch tamamlandı: {}", total);
            });
    }
}
```

---

## İntervyu Sualları

### 1. @Async işləmədiyi zaman nə yoxlamalıdır?
**Cavab:** (1) `@EnableAsync` konfiqurasiyada varmı? (2) Self-invocation — eyni sinifdən çağırılırmı (proxy keçmir)? (3) `private` metoddurmu (CGLIB proxy edə bilmir)? (4) `@Async` annotasiyası metodun özündəmi? Həll: ayrı `@Service`-ə köçürmək.

### 2. void vs CompletableFuture — hansı zaman istifadə edilir?
**Cavab:** `void` — nəticəni gözləmək lazım olmadıqda (mail, notification, audit). `CompletableFuture<T>` — nəticə gözlənildikdə yaxud paralel əməliyyatları birləşdirmək lazım olduqda. `CompletableFuture` xəta idarəetməsi imkanı da verir.

### 3. Thread pool parametrləri (corePoolSize, maxPoolSize, queueCapacity) necə işləyir?
**Cavab:** (1) Aktiv thread sayı < corePoolSize → yeni thread yaradılır. (2) corePoolSize dolu → queue-ya əlavə olunur. (3) Queue dolu → maxPoolSize-a qədər yeni thread. (4) maxPoolSize dolu → RejectedExecutionHandler (CallerRunsPolicy, AbortPolicy). Boş core thread-lər silinmir.

### 4. @Async ilə @Transactional birlikdə işləyirmi?
**Cavab:** Bəli, amma ayrı transaction-lar. `@Async` metod ayrı thread-də işləyir = ayrı transaction. Caller-ın transaction-u async metodda görünmür. `@TransactionalEventListener(AFTER_COMMIT)` ilə transaction commit-dən sonra async işə salmaq daha doğru yanaşmadır.

### 5. Graceful shutdown nə üçündür?
**Cavab:** App dayandırıldıqda işdə olan async task-ların bitməsini gözləmək. `setWaitForTasksToCompleteOnShutdown(true)` + `setAwaitTerminationSeconds(30)` — app 30 saniyə gözləyir ki, aktiv thread-lər tamamlansın. Bu olmadan data itkisi riski var.

*Son yenilənmə: 2026-04-10*
