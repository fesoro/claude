# Database per Service Pattern — Geniş İzah (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
1. [Database per Service nədir?](#database-per-service-nədir)
2. [Data isolation strategiyaları](#data-isolation-strategiyaları)
3. [Cross-service queries — həll yolları](#cross-service-queries--həll-yolları)
4. [Saga Pattern — distributed transactions](#saga-pattern--distributed-transactions)
5. [CQRS ilə birlikdə](#cqrs-ilə-birlikdə)
6. [Spring Boot-da tətbiq](#spring-boot-da-tətbiq)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Database per Service nədir?

```
Monolit — bir DB:
  ┌─────────────────────────────┐
  │         Monolith App        │
  └───────────────┬─────────────┘
                  │
         ┌────────▼────────┐
         │   Shared DB     │
         │ orders, payments│
         │ users, products │
         └─────────────────┘

  Problem:
  → DB schema coupling: bir servis dəyişir → hamı təsirlənir
  → DB single point of failure
  → Tek DB-yə migrate çətindir (PostgreSQL → MongoDB)
  → Horizontal scaling çətin

Microservices — Database per Service:
  ┌──────────────┐  ┌───────────────┐  ┌─────────────────┐
  │ Order Service│  │Payment Service│  │ User Service    │
  └──────┬───────┘  └───────┬───────┘  └────────┬────────┘
         │                  │                   │
  ┌──────▼──────┐  ┌────────▼──────┐  ┌─────────▼───────┐
  │ Orders DB   │  │ Payments DB   │  │ Users DB        │
  │ (PostgreSQL)│  │ (PostgreSQL)  │  │ (PostgreSQL)    │
  └─────────────┘  └───────────────┘  └─────────────────┘

Üstünlüklər:
  ✅ Loose coupling — schema dəyişiklikləri izolə
  ✅ Polyglot persistence — hər servis öz DB tipini seçir
  ✅ Independent scaling — Order DB ayrı scale
  ✅ Independent deployment — DB migration ayrı

Çatışmazlıqlar:
  ❌ Cross-service JOIN yoxdur
  ❌ Distributed transactions çətin
  ❌ Data consistency mürəkkəb
  ❌ Operational overhead (çox DB idarə etmək)
```

---

## Data isolation strategiyaları

```
3 əsas izolasiya səviyyəsi:

1. Tam ayrı DB server:
   Order Service → orders-db.internal:5432
   Payment Service → payments-db.internal:5432
   En güclü izolasiya, ən yüksək xərc

2. Eyni server, ayrı database:
   postgresql://db.internal/orders_db
   postgresql://db.internal/payments_db
   Balanslaşdırılmış yanaşma

3. Eyni database, ayrı schema:
   public.orders (Order Service)
   payments.transactions (Payment Service)
   Ən az izolasiya, asan başlamaq üçün

Development-dən Production-a keçid:
  Dev: schema-per-service (asan idarə)
  Staging: database-per-service
  Prod: server-per-service (tam izolasiya)
```

```yaml
# Order Service — öz datasource-u
# application.yml (order-service)
spring:
  datasource:
    url: jdbc:postgresql://orders-db:5432/orders_db
    username: ${ORDER_DB_USER}
    password: ${ORDER_DB_PASSWORD}
  jpa:
    hibernate:
      ddl-auto: validate
  flyway:
    locations: classpath:db/migration/orders

---
# Payment Service — öz datasource-u
# application.yml (payment-service)
spring:
  datasource:
    url: jdbc:postgresql://payments-db:5432/payments_db
    username: ${PAYMENT_DB_USER}
    password: ${PAYMENT_DB_PASSWORD}
  jpa:
    hibernate:
      ddl-auto: validate
  flyway:
    locations: classpath:db/migration/payments
```

---

## Cross-service queries — həll yolları

```java
// ─── Problem: Join lazımdır ───────────────────────────────
// "Son 30 gündə ödənişi olan sifarişləri göstər"
// → orders DB + payments DB — birbaşa JOIN yoxdur

// ─── Həll 1: API Composition pattern ─────────────────────
// Gateway ya da ayrı servis hər ikisini çağırıb birləşdirir

@Service
public class OrderDashboardService {

    private final OrderServiceClient orderClient;
    private final PaymentServiceClient paymentClient;

    public List<OrderWithPaymentDto> getOrdersWithPayments(String customerId) {
        // Hər iki servisi çağır
        List<OrderDto> orders = orderClient.getOrdersByCustomer(customerId);
        List<String> orderIds = orders.stream()
            .map(OrderDto::id)
            .toList();

        // Ödəniş statusunu batch-lə al
        Map<String, PaymentDto> payments = paymentClient
            .getPaymentsByOrderIds(orderIds)
            .stream()
            .collect(Collectors.toMap(PaymentDto::orderId, p -> p));

        // Application level-da birləşdir (JOIN əvəzi)
        return orders.stream()
            .map(order -> new OrderWithPaymentDto(
                order,
                payments.get(order.id())
            ))
            .toList();
    }
}

// ─── Həll 2: Event-driven denormalization ────────────────
// Hər servis lazımlı data-nı özündə saxlayır

// Payment Service, Customer məlumatını öz DB-sində saxlayır
@Entity
@Table(name = "payment_customers")  // Payments DB-də!
public class PaymentCustomer {
    @Id
    private String customerId;
    private String name;
    private String email;
    // → User Service-dən event gəldikdə güncəllənir
}

// User Service customer yeniləndikdə event publish edir
@Service
public class UserService {
    public void updateCustomer(String id, UpdateCustomerRequest request) {
        Customer customer = customerRepository.findById(id).orElseThrow();
        customer.update(request);
        customerRepository.save(customer);

        // Event publish — bütün servislər öz kopyasını güncəlləyir
        eventPublisher.publish(new CustomerUpdatedEvent(
            customer.getId(),
            customer.getName(),
            customer.getEmail()
        ));
    }
}

// Payment Service event-i alır
@KafkaListener(topics = "customer.updated")
public void handleCustomerUpdated(CustomerUpdatedEvent event) {
    PaymentCustomer cached = paymentCustomerRepository
        .findById(event.customerId())
        .orElse(new PaymentCustomer(event.customerId()));

    cached.setName(event.name());
    cached.setEmail(event.email());
    paymentCustomerRepository.save(cached);
}

// ─── Həll 3: CQRS — Read Model ───────────────────────────
// Ayrı "oxuma modeli" DB-si bütün servislərin data-sını saxlayır

@Entity
@Table(name = "order_payment_view")  // Ayrı read DB!
public class OrderPaymentView {
    @Id
    private String orderId;
    private String customerId;
    private String customerName;
    private BigDecimal orderTotal;
    private String orderStatus;
    private String paymentStatus;
    private Instant paidAt;
    // Hər iki servisin event-lərindən dolur
}
```

---

## Saga Pattern — distributed transactions

```java
// ─── Problem: 2-Phase Commit yoxdur microservice-də ──────
// "Sifariş yarat" əməliyyatı:
//   1. Inventory-dən məhsul rezerv et
//   2. Payment al
//   3. Order yarat
// Biri uğursuz olsa → hamısını geri al!

// ─── Choreography-based Saga ─────────────────────────────
// Hər servis event dinləyir, cavab event publish edir

// Step 1: Order Service — sifarişi başlat
@Service
public class OrderSagaService {

    @Transactional
    public void createOrder(CreateOrderRequest request) {
        Order order = new Order(request);
        order.setStatus(OrderStatus.PENDING);
        orderRepository.save(order);

        // Saga başladı — Inventory-yə event göndər
        eventPublisher.publish(new InventoryReservationRequestedEvent(
            order.getId(),
            request.items()
        ));
    }

    // Step 4: Ödəniş uğurlu → sifarişi tamamla
    @KafkaListener(topics = "payment.completed")
    @Transactional
    public void handlePaymentCompleted(PaymentCompletedEvent event) {
        Order order = orderRepository.findById(event.orderId()).orElseThrow();
        order.setStatus(OrderStatus.CONFIRMED);
        orderRepository.save(order);
        eventPublisher.publish(new OrderConfirmedEvent(event.orderId()));
    }

    // Compensating transaction: ödəniş uğursuz → inventory geri al
    @KafkaListener(topics = "payment.failed")
    @Transactional
    public void handlePaymentFailed(PaymentFailedEvent event) {
        Order order = orderRepository.findById(event.orderId()).orElseThrow();
        order.setStatus(OrderStatus.CANCELLED);
        orderRepository.save(order);

        // Inventory-yə kompensasiya event-i göndər
        eventPublisher.publish(new InventoryReleaseRequestedEvent(
            event.orderId(), event.items()));
    }
}

// Step 2: Inventory Service
@Service
public class InventoryService {

    @KafkaListener(topics = "inventory.reservation.requested")
    @Transactional
    public void handleReservation(InventoryReservationRequestedEvent event) {
        try {
            reserveItems(event.orderId(), event.items());
            // Uğurlu → Payment-ə davam et
            eventPublisher.publish(new InventoryReservedEvent(
                event.orderId(), event.items()));
        } catch (InsufficientStockException e) {
            // Uğursuz → Saga dayandır
            eventPublisher.publish(new InventoryReservationFailedEvent(
                event.orderId(), e.getMessage()));
        }
    }

    @KafkaListener(topics = "inventory.release.requested")
    @Transactional
    public void handleRelease(InventoryReleaseRequestedEvent event) {
        // Compensating transaction — məhsulları geri qoy
        releaseItems(event.orderId(), event.items());
        log.info("Inventory geri alındı: orderId={}", event.orderId());
    }
}

// Step 3: Payment Service
@Service
public class PaymentService {

    @KafkaListener(topics = "inventory.reserved")
    @Transactional
    public void handleInventoryReserved(InventoryReservedEvent event) {
        try {
            PaymentResult result = paymentGateway.charge(
                event.orderId(), event.amount());

            if (result.isSuccessful()) {
                eventPublisher.publish(new PaymentCompletedEvent(
                    event.orderId(), result.transactionId()));
            } else {
                eventPublisher.publish(new PaymentFailedEvent(
                    event.orderId(), event.items(), result.message()));
            }
        } catch (Exception e) {
            eventPublisher.publish(new PaymentFailedEvent(
                event.orderId(), event.items(), e.getMessage()));
        }
    }
}

// ─── Orchestration-based Saga ─────────────────────────────
// Mərkəzi Saga Orchestrator bütün addımları idarə edir

@Service
public class CreateOrderSagaOrchestrator {

    private final OrderService orderService;
    private final InventoryClient inventoryClient;
    private final PaymentClient paymentClient;
    private final SagaStateRepository sagaStateRepository;

    @Transactional
    public void execute(CreateOrderCommand command) {
        SagaState saga = SagaState.start(command.orderId());
        sagaStateRepository.save(saga);

        try {
            // Step 1
            saga.setStep("RESERVE_INVENTORY");
            inventoryClient.reserve(command.orderId(), command.items());

            // Step 2
            saga.setStep("PROCESS_PAYMENT");
            PaymentResult payment = paymentClient.charge(
                command.customerId(), command.totalAmount());

            if (!payment.isSuccessful()) {
                throw new PaymentFailedException(payment.message());
            }

            // Step 3
            saga.setStep("CONFIRM_ORDER");
            orderService.confirm(command.orderId());

            saga.setStatus(SagaStatus.COMPLETED);

        } catch (Exception e) {
            log.error("Saga uğursuz oldu, kompensasiya başlayır", e);
            compensate(saga, command);
            saga.setStatus(SagaStatus.FAILED);
        } finally {
            sagaStateRepository.save(saga);
        }
    }

    private void compensate(SagaState saga, CreateOrderCommand command) {
        // Hər addım üçün geri alma
        if (saga.reachedStep("PROCESS_PAYMENT")) {
            try { paymentClient.refund(command.orderId()); }
            catch (Exception e) { log.error("Refund uğursuz", e); }
        }
        if (saga.reachedStep("RESERVE_INVENTORY")) {
            try { inventoryClient.release(command.orderId()); }
            catch (Exception e) { log.error("Inventory release uğursuz", e); }
        }
        orderService.cancel(command.orderId());
    }
}
```

---

## CQRS ilə birlikdə

```java
// ─── CQRS + Database per Service ─────────────────────────
// Write model: hər servis öz DB-si
// Read model: ayrı oxuma DB (denormalized, fast queries)

// Order Service Write side (öz DB-si)
@Service
public class OrderCommandService {
    private final OrderRepository orderRepository; // PostgreSQL

    public void createOrder(CreateOrderCommand cmd) {
        Order order = new Order(cmd);
        orderRepository.save(order);
        eventPublisher.publish(new OrderCreatedEvent(order));
    }
}

// Event handler → Read model güncəllər
@Component
public class OrderReadModelProjection {

    private final OrderSummaryRepository readRepository; // Ayrı DB!

    @EventHandler
    @Transactional
    public void on(OrderCreatedEvent event) {
        OrderSummary summary = OrderSummary.builder()
            .orderId(event.orderId())
            .customerId(event.customerId())
            .customerName(event.customerName()) // Denormalized
            .status(event.status())
            .total(event.total())
            .createdAt(event.createdAt())
            .build();
        readRepository.save(summary);
    }

    @EventHandler
    @Transactional
    public void on(PaymentCompletedEvent event) {
        // Payment event-i gəldi → oxuma modelini güncəllə
        readRepository.findById(event.orderId()).ifPresent(summary -> {
            summary.setPaymentStatus("PAID");
            summary.setPaidAt(event.paidAt());
            readRepository.save(summary);
        });
    }
}

// Query side (oxuma modeli)
@Service
public class OrderQueryService {
    private final OrderSummaryRepository readRepository; // Read-optimized DB

    public Page<OrderSummary> getOrdersByCustomer(String customerId, Pageable pageable) {
        // Cross-service JOIN olmadan sürətli sorğu
        return readRepository.findByCustomerId(customerId, pageable);
    }
}
```

---

## Spring Boot-da tətbiq

```java
// ─── Multi-datasource konfiqurasiyası ─────────────────────
// Bir Spring Boot app-da iki DB (nadir amma mümkün)

@Configuration
@EnableTransactionManagement
public class DataSourceConfig {

    // Primary datasource
    @Primary
    @Bean(name = "ordersDataSource")
    @ConfigurationProperties("spring.datasource.orders")
    public DataSource ordersDataSource() {
        return DataSourceBuilder.create().build();
    }

    // Secondary datasource (read model)
    @Bean(name = "readModelDataSource")
    @ConfigurationProperties("spring.datasource.read-model")
    public DataSource readModelDataSource() {
        return DataSourceBuilder.create().build();
    }

    @Primary
    @Bean(name = "ordersEntityManagerFactory")
    public LocalContainerEntityManagerFactoryBean ordersEntityManagerFactory(
            @Qualifier("ordersDataSource") DataSource dataSource,
            EntityManagerFactoryBuilder builder) {
        return builder
            .dataSource(dataSource)
            .packages("com.example.order.domain")
            .persistenceUnit("orders")
            .build();
    }

    @Bean(name = "readModelEntityManagerFactory")
    public LocalContainerEntityManagerFactoryBean readModelEntityManagerFactory(
            @Qualifier("readModelDataSource") DataSource dataSource,
            EntityManagerFactoryBuilder builder) {
        return builder
            .dataSource(dataSource)
            .packages("com.example.order.readmodel")
            .persistenceUnit("readModel")
            .build();
    }
}

// ─── Service-specific Repository ─────────────────────────
// @Repository məxsus datasource-a istifadə edir

@Repository
@Qualifier("orders")
public interface OrderRepository extends JpaRepository<OrderEntity, String> {
    // orders DB
}

@Repository
@Qualifier("readModel")
public interface OrderSummaryRepository extends JpaRepository<OrderSummary, String> {
    // read-model DB
}
```

---

## İntervyu Sualları

### 1. Database per Service prinsipi nədir?
**Cavab:** Hər microservice yalnız öz DB-sinə çatmalıdır — digər servisin DB-sinə birbaşa müraciət etməməlidir. Məqsəd: loose coupling — bir servisin schema dəyişikliyi digərini etkiləmir; polyglot persistence — Order Service PostgreSQL, Product Service Elasticsearch istifadə edə bilər; independent scaling. Çatışmazlıq: cross-service JOIN yoxdur, distributed transactions mürəkkəbdir.

### 2. Shared Database anti-pattern nədir?
**Cavab:** Çoxlu servisin eyni DB-ni paylaşması. Problemlər: (1) **Schema coupling** — bir servis table dəyişdirsə, hamı deploy lazımdır; (2) **DB single point of failure** — DB çöksə hamı çöküb; (3) **Performance coupling** — bir servis DB-ni aşırı yükləsə, hamı yavaşlayır; (4) **DB migration çətin** — PostgreSQL → MongoDB heç bir servis üçün mümkün deyil. Database per Service bu problemləri həll edir.

### 3. Cross-service sorğuları necə həll etmək olar?
**Cavab:** (1) **API Composition** — gateway ya da servis hər ikisini çağırıb application-levelda birləşdirir; N+1 problemi olabilir. (2) **Event-driven denormalization** — hər servis lazımlı data-nı öz DB-sində saxlayır, event-lərlə güncəllənir; eventual consistency. (3) **CQRS Read Model** — bütün servislərin event-lərindən doldurulan ayrı oxuma DB-si; karmaşık sorğular üçün ideal. Seçim: eventual consistency qəbul edilə bilənmidir?

### 4. Choreography vs Orchestration Saga fərqi?
**Cavab:** **Choreography** — hər servis event dinləyir, cavab event publish edir; mərkəzi koordinator yoxdur. Üstün: loose coupling, sadə başlanğıc. Problem: "Saga axışı" paylı, anlamaq çətin, debug çətin. **Orchestration** — mərkəzi Saga Orchestrator bütün addımları çağırır, kompensasiyaları idarə edir; axış bir yerdə görünür. Üstün: anlaşılan iş axışı. Problem: orchestrator single point of dependency. Böyük sistemlər üçün orchestration daha idarə ediləndir.

### 5. Eventual Consistency nədir və niyə qəbul edilməlidir?
**Cavab:** Distributed sistemdə bütün nodlar eyni anda eyni data-ya sahib olmaya bilər, amma müəyyən vaxtdan sonra konsistent olurlar. Database per Service-də: Order yaradıldıqda anında Payment Service onu bilmir — event çatana qədər. Bu "window" eventual consistency-dir. Qəbul edilməsi: (1) Çox biznes prosesi güclü konsistensiya tələb etmir; (2) Strong consistency (2PC) distributed sistemlərdə performans problemi yaradır; (3) Amazon, Netflix kimi böyük şirkətlər eventual consistency qəbul edir. Kritik əməliyyatlar üçün (bank transfer) idempotency + saga ilə eventual consistency idarə edilə bilər.

*Son yenilənmə: 2026-04-10*
