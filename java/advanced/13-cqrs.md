# 13 — CQRS Pattern — Java ilə Geniş İzah

> **Seviyye:** Expert ⭐⭐⭐⭐


## Mündəricat
1. [CQRS nədir?](#cqrs-nədir)
2. [Command və Query ayrımı](#command-və-query-ayrımı)
3. [Spring ilə CQRS](#spring-ilə-cqrs)
4. [Read model (Projection)](#read-model-projection)
5. [Event-driven CQRS](#event-driven-cqrs)
6. [İntervyu Sualları](#intervyu-sualları)

---

## CQRS nədir?

**CQRS (Command Query Responsibility Segregation)** — Greg Young tərəfindən populyarlaşdırılmış pattern. Məlumat yazmaq (Command) və oxumaq (Query) əməliyyatlarını tamamilə ayırır.

```
Traditional:
  OrderService.createOrder()  → DB yazma
  OrderService.getOrders()    → DB oxuma
  (eyni model)

CQRS:
  Command Side:
    PlaceOrderCommand → OrderCommandHandler → Domain → Write DB
  
  Query Side:
    GetOrdersQuery → OrderQueryHandler → Read Model → Read DB
    (denormalized, optimized views)
```

**Niyə CQRS:**
- Read/Write əməliyyatlarının fərqli optimallaşdırılması
- Read model: sürətli, denormalized
- Write model: normalizasiya, consistency
- Scale olunması fərqli (read > write adətən)

---

## Command və Query ayrımı

```java
// ===== COMMANDS (Yazma əməliyyatları) =====

// Command — niyyəti ifadə edir (nəyi etmək istəyirik)
public record PlaceOrderCommand(
        CustomerId customerId,
        List<OrderItemDto> items,
        PaymentMethod paymentMethod) implements Command {}

public record CancelOrderCommand(
        OrderId orderId,
        String reason) implements Command {}

public record UpdateDeliveryAddressCommand(
        OrderId orderId,
        Address newAddress) implements Command {}

// Command Handler — domain logic
@Service
public class OrderCommandHandler {

    private final OrderRepository orderRepository;
    private final ApplicationEventPublisher eventPublisher;

    @Transactional
    public OrderId handle(PlaceOrderCommand command) {
        // Domain əməliyyatı
        Order order = Order.create(command.customerId(), command.items());
        Order saved = orderRepository.save(order);

        // Domain event-lərini publish et
        saved.pullEvents().forEach(eventPublisher::publishEvent);

        return saved.getId();
    }

    @Transactional
    public void handle(CancelOrderCommand command) {
        Order order = orderRepository.findById(command.orderId())
            .orElseThrow(() -> new OrderNotFoundException(command.orderId()));

        order.cancel(command.reason());
        orderRepository.save(order);

        order.pullEvents().forEach(eventPublisher::publishEvent);
    }
}

// ===== QUERIES (Oxuma əməliyyatları) =====

// Query — nəyi almaq istəyirik
public record GetOrderQuery(OrderId orderId) implements Query {}
public record GetCustomerOrdersQuery(
        CustomerId customerId,
        OrderStatus statusFilter,
        Pageable pageable) implements Query {}
public record GetOrderStatisticsQuery(
        LocalDate from,
        LocalDate to) implements Query {}

// Query Handler — optimized read
@Service
@Transactional(readOnly = true)
public class OrderQueryHandler {

    private final OrderReadRepository orderReadRepository;

    public OrderDetailsDto handle(GetOrderQuery query) {
        return orderReadRepository.findDetailedById(query.orderId())
            .orElseThrow(() -> new OrderNotFoundException(query.orderId()));
    }

    public Page<OrderSummaryDto> handle(GetCustomerOrdersQuery query) {
        return orderReadRepository.findSummariesByCustomerId(
            query.customerId(),
            query.statusFilter(),
            query.pageable()
        );
    }

    public OrderStatisticsDto handle(GetOrderStatisticsQuery query) {
        return orderReadRepository.getStatistics(query.from(), query.to());
    }
}
```

---

## Spring ilə CQRS

```java
// Command Bus — command-ları handler-lara yönləndirir
@Component
public class CommandBus {

    private final Map<Class<? extends Command>, CommandHandler<?, ?>> handlers;

    @SuppressWarnings("unchecked")
    public <R> R dispatch(Command command) {
        CommandHandler<Command, R> handler =
            (CommandHandler<Command, R>) handlers.get(command.getClass());

        if (handler == null) {
            throw new CommandHandlerNotFoundException(command.getClass());
        }

        return handler.handle(command);
    }
}

// Query Bus
@Component
public class QueryBus {

    private final Map<Class<? extends Query>, QueryHandler<?, ?>> handlers;

    @SuppressWarnings("unchecked")
    public <R> R dispatch(Query query) {
        QueryHandler<Query, R> handler =
            (QueryHandler<Query, R>) handlers.get(query.getClass());

        if (handler == null) {
            throw new QueryHandlerNotFoundException(query.getClass());
        }

        return handler.handle(query);
    }
}

// Controller — Command/Query Bus istifadəsi
@RestController
@RequestMapping("/api/orders")
public class OrderController {

    private final CommandBus commandBus;
    private final QueryBus queryBus;

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public OrderIdResponse placeOrder(@RequestBody @Valid PlaceOrderRequest request) {
        PlaceOrderCommand command = new PlaceOrderCommand(
            CustomerId.of(request.customerId()),
            request.items(),
            request.paymentMethod()
        );
        OrderId orderId = commandBus.dispatch(command);
        return new OrderIdResponse(orderId.value().toString());
    }

    @GetMapping("/{id}")
    public OrderDetailsDto getOrder(@PathVariable String id) {
        return queryBus.dispatch(new GetOrderQuery(OrderId.of(id)));
    }

    @GetMapping
    public Page<OrderSummaryDto> getMyOrders(
            @RequestParam CustomerId customerId,
            @RequestParam(required = false) OrderStatus status,
            Pageable pageable) {
        return queryBus.dispatch(
            new GetCustomerOrdersQuery(customerId, status, pageable));
    }

    @DeleteMapping("/{id}")
    public void cancelOrder(@PathVariable String id,
                             @RequestBody CancelRequest request) {
        commandBus.dispatch(new CancelOrderCommand(
            OrderId.of(id), request.reason()));
    }
}
```

---

## Read model (Projection)

```java
// Read model — query üçün optimallaşdırılmış DTO/View

// Denormalized read entity
@Entity
@Table(name = "order_summary_view")  // Materialized view yaxud ayrı cədvəl
public class OrderSummaryEntity {
    @Id
    private String orderId;
    private String customerId;
    private String customerName;       // Denormalized — join lazım deyil!
    private String customerEmail;
    private String status;
    private BigDecimal totalAmount;
    private String currency;
    private int itemCount;
    private LocalDateTime createdAt;
    private LocalDateTime updatedAt;
}

// Read Repository — optimized queries
@Repository
public interface OrderReadRepository extends JpaRepository<OrderSummaryEntity, String> {

    @Query("""
        SELECT o FROM OrderSummaryEntity o
        WHERE o.customerId = :customerId
        AND (:status IS NULL OR o.status = :status)
        ORDER BY o.createdAt DESC
    """)
    Page<OrderSummaryDto> findSummaries(
        @Param("customerId") String customerId,
        @Param("status") String status,
        Pageable pageable);

    // Aggregate statistics — JOIN-siz!
    @Query("""
        SELECT new com.example.dto.OrderStatisticsDto(
            COUNT(o), SUM(o.totalAmount), AVG(o.totalAmount),
            MIN(o.totalAmount), MAX(o.totalAmount))
        FROM OrderSummaryEntity o
        WHERE o.createdAt BETWEEN :from AND :to
    """)
    OrderStatisticsDto getStatistics(
        @Param("from") LocalDateTime from,
        @Param("to") LocalDateTime to);
}
```

---

## Event-driven CQRS

```java
// Domain Event → Read model-i yenilə (Projection)
@Component
public class OrderProjection {

    private final OrderSummaryRepository readRepository;
    private final CustomerRepository customerRepository; // Read üçün

    // OrderPlacedEvent gəldikdə read model-i yarat
    @EventListener
    @Transactional
    public void on(OrderPlacedEvent event) {
        Customer customer = customerRepository.findById(event.customerId()).orElseThrow();

        OrderSummaryEntity summary = new OrderSummaryEntity();
        summary.setOrderId(event.orderId().value().toString());
        summary.setCustomerId(event.customerId().value().toString());
        summary.setCustomerName(customer.getFullName()); // Denormalize et
        summary.setCustomerEmail(customer.getEmail().value());
        summary.setStatus(OrderStatus.PENDING.name());
        summary.setTotalAmount(event.totalAmount().amount());
        summary.setItemCount(event.itemCount());
        summary.setCreatedAt(event.occurredOn().atZone(ZoneId.systemDefault()).toLocalDateTime());

        readRepository.save(summary);
    }

    @EventListener
    @Transactional
    public void on(OrderCancelledEvent event) {
        readRepository.findById(event.orderId().value().toString())
            .ifPresent(summary -> {
                summary.setStatus(OrderStatus.CANCELLED.name());
                summary.setUpdatedAt(LocalDateTime.now());
                readRepository.save(summary);
            });
    }

    @EventListener
    @Transactional
    public void on(OrderShippedEvent event) {
        readRepository.findById(event.orderId().value().toString())
            .ifPresent(summary -> {
                summary.setStatus(OrderStatus.SHIPPED.name());
                summary.setUpdatedAt(LocalDateTime.now());
                readRepository.save(summary);
            });
    }
}
```

---

## İntervyu Sualları

### 1. CQRS niyə istifadə edilir?
**Cavab:** (1) Read/Write yükü fərqlidir — read adətən daha çox. (2) Read model query-lər üçün optimallaşdırıla bilər (denormalized, precomputed). (3) Write side güclü domain model, consistency. (4) Müstəqil scale — read replica-lar əlavə edilir. (5) Event sourcing ilə birlikdə audit trail.

### 2. CQRS-in çatışmazlıqları nədir?
**Cavab:** (1) Complexity artır — iki model saxlamaq lazımdır. (2) Eventual consistency — command işlədikdən sonra read model dərhal yenilənmir. (3) Daha çox kod. Sadə CRUD app üçün overkill-dir. Kompleks domain, yüksək yük, reporting tələbləri olduqda uyğundur.

### 3. Command vs Query fərqi nədir?
**Cavab:** `Command` — sistem vəziyyətini dəyişdirir, nəticə qaytarmır (yaxud yalnız ID). `Query` — sistem vəziyyətini dəyişdirmir, data qaytarır. Bu ayrım CQS (Command Query Separation) prinsipindən gəlir. CQRS bunu arxitektura səviyyəsinə aparır.

### 4. Read model necə sinxronizasiya olunur?
**Cavab:** (1) Synchronous — eyni transaction-da (sadə, amma coupling). (2) Domain Events — write side event publish edir, projection handler read model-i yeniləyir (loose coupling, eventual consistency). (3) Change Data Capture (CDC) — DB log-u oxuyub read model-i yenilə (Debezium).

### 5. Eventual consistency problemi CQRS-də necə idarə olunur?
**Cavab:** Read model event işlədikdən millisaniyələr sonra yenilənir. Client yeni yaratdığı entity-ni dərhal görməyə bilər. Həll: (1) Command response-da ID qaytarıb client öz state-ini update etsin. (2) Optimistic UI update. (3) "Polling" — client hazır olana qədər sorğulasın. (4) WebSocket notification.

*Son yenilənmə: 2026-04-10*
