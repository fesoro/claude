# 010 — Hexagonal Architecture (Ports & Adapters) — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [Hexagonal Architecture nədir?](#hexagonal-architecture-nədir)
2. [Ports və Adapters](#ports-və-adapters)
3. [Java/Spring ilə tətbiq](#javaspring-ilə-tətbiq)
4. [Paket strukturu](#paket-strukturu)
5. [Test etmək](#test-etmək)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Hexagonal Architecture nədir?

**Hexagonal Architecture (Ports & Adapters)** — Alistair Cockburn tərəfindən təqdim edilmiş dizayn pattern. Məqsəd: domain logic-i xarici sistemlərdən (DB, UI, API, Queue) tamamilə ayırmaq.

```
                    ┌─────────────────┐
REST API ──────────►│   Application   │◄──────── Kafka Consumer
                    │   ┌─────────┐   │
Web UI ────────────►│   │ Domain  │   │◄──────── Scheduler
                    │   │ (Core)  │   │
Test ──────────────►│   └─────────┘   │──────────► Database
                    │                 │
Kafka Producer ◄────│                 │──────────► Email Service
                    └─────────────────┘
                    
Domain (core): Order, User, Payment — business rules
Port (interface): OrderRepository, NotificationPort — domain tərəfdən
Adapter (implementasiya): JpaOrderRepository, SmtpNotificationAdapter
```

**Qayda:** Domain (core) xarici sistemlərə bağımlı olmamalıdır. Yalnız interfeyslər (Ports) vasitəsilə kommunikasiya edir.

---

## Ports və Adapters

```
Port növləri:
  Inbound (Driving) Port — xaricdən domain-ə çağırış (REST → Use Case)
  Outbound (Driven) Port — domain-dən xaricə çağırış (Domain → DB)

Adapter növləri:
  Primary Adapter — Port-u çağırır (REST Controller, CLI, Test)
  Secondary Adapter — Port-u implement edir (JPA Repository, SMTP, S3)
```

---

## Java/Spring ilə tətbiq

```java
// ===== DOMAIN (Core) =====

// Domain Entity — xarici frameworkdən asılı deyil
public class Order {
    private final OrderId id;
    private final CustomerId customerId;
    private final List<OrderItem> items;
    private OrderStatus status;

    public static Order create(CustomerId customerId, List<OrderItem> items) {
        if (items.isEmpty()) throw new IllegalArgumentException("Boş sifariş");
        return new Order(OrderId.generate(), customerId, items, OrderStatus.PENDING);
    }

    public void confirm() {
        if (status != OrderStatus.PENDING) {
            throw new IllegalStateException("Yalnız PENDING sifariş təsdiqlənə bilər");
        }
        this.status = OrderStatus.CONFIRMED;
    }

    public Money calculateTotal() {
        return items.stream()
            .map(item -> item.getPrice().multiply(item.getQuantity()))
            .reduce(Money.ZERO, Money::add);
    }
}

// Value Objects
public record OrderId(UUID value) {
    public static OrderId generate() { return new OrderId(UUID.randomUUID()); }
    public static OrderId of(String value) { return new OrderId(UUID.fromString(value)); }
}

public record Money(BigDecimal amount, Currency currency) {
    public static final Money ZERO = new Money(BigDecimal.ZERO, Currency.getInstance("AZN"));

    public Money add(Money other) {
        if (!this.currency.equals(other.currency)) throw new IllegalArgumentException();
        return new Money(this.amount.add(other.amount), this.currency);
    }

    public Money multiply(int quantity) {
        return new Money(this.amount.multiply(BigDecimal.valueOf(quantity)), this.currency);
    }
}

// ===== PORTS =====

// Inbound Port — Use Case interface
public interface PlaceOrderUseCase {
    Order placeOrder(PlaceOrderCommand command);
}

public interface GetOrderUseCase {
    Order getOrder(OrderId orderId);
    List<Order> getOrdersByCustomer(CustomerId customerId);
}

public record PlaceOrderCommand(CustomerId customerId, List<OrderItemDto> items) {}

// Outbound Ports
public interface OrderRepository {
    Order save(Order order);
    Optional<Order> findById(OrderId orderId);
    List<Order> findByCustomerId(CustomerId customerId);
}

public interface NotificationPort {
    void sendOrderConfirmation(Order order);
}

public interface PaymentPort {
    PaymentResult processPayment(Order order, PaymentMethod method);
}

// ===== APPLICATION LAYER (Use Cases) =====

@Service
public class PlaceOrderService implements PlaceOrderUseCase {

    private final OrderRepository orderRepository;  // Outbound port
    private final NotificationPort notificationPort; // Outbound port
    private final PaymentPort paymentPort;           // Outbound port

    public PlaceOrderService(OrderRepository orderRepository,
                              NotificationPort notificationPort,
                              PaymentPort paymentPort) {
        this.orderRepository = orderRepository;
        this.notificationPort = notificationPort;
        this.paymentPort = paymentPort;
    }

    @Override
    @Transactional
    public Order placeOrder(PlaceOrderCommand command) {
        // Domain logic — framework-dən asılı deyil
        List<OrderItem> items = command.items().stream()
            .map(dto -> new OrderItem(
                ProductId.of(dto.productId()),
                dto.quantity(),
                Money.of(dto.price())))
            .collect(Collectors.toList());

        Order order = Order.create(command.customerId(), items);
        Order saved = orderRepository.save(order);
        notificationPort.sendOrderConfirmation(saved);

        return saved;
    }
}

// ===== ADAPTERS =====

// Primary Adapter — REST Controller
@RestController
@RequestMapping("/api/orders")
public class OrderController {

    private final PlaceOrderUseCase placeOrderUseCase;
    private final GetOrderUseCase getOrderUseCase;

    @PostMapping
    public ResponseEntity<OrderResponse> placeOrder(
            @RequestBody @Valid PlaceOrderRequest request) {

        PlaceOrderCommand command = new PlaceOrderCommand(
            CustomerId.of(request.customerId()),
            request.items()
        );

        Order order = placeOrderUseCase.placeOrder(command);
        return ResponseEntity.ok(OrderResponseMapper.toResponse(order));
    }
}

// Secondary Adapter — JPA Repository
@Component
public class JpaOrderRepository implements OrderRepository {

    private final JpaOrderEntityRepository jpa;
    private final OrderEntityMapper mapper;

    @Override
    public Order save(Order order) {
        OrderEntity entity = mapper.toEntity(order);
        OrderEntity saved = jpa.save(entity);
        return mapper.toDomain(saved);
    }

    @Override
    public Optional<Order> findById(OrderId orderId) {
        return jpa.findById(orderId.value().toString())
            .map(mapper::toDomain);
    }
}

// Secondary Adapter — Email Notification
@Component
public class SmtpNotificationAdapter implements NotificationPort {

    private final JavaMailSender mailSender;

    @Override
    public void sendOrderConfirmation(Order order) {
        // SMTP ilə mail göndər
        SimpleMailMessage message = new SimpleMailMessage();
        message.setTo(order.getCustomerEmail());
        message.setSubject("Sifariş təsdiqləndi: " + order.getId().value());
        mailSender.send(message);
    }
}
```

---

## Paket strukturu

```
src/main/java/com/example/
├── domain/                     # Domain (Core) — heç bir Spring annotasiyası yox
│   ├── model/
│   │   ├── Order.java
│   │   ├── OrderItem.java
│   │   └── OrderId.java       (Value Object)
│   ├── service/
│   │   └── PriceCalculationDomainService.java
│   └── event/
│       └── OrderPlacedEvent.java
│
├── application/                # Use Cases
│   ├── port/
│   │   ├── in/                 (Inbound Ports)
│   │   │   ├── PlaceOrderUseCase.java
│   │   │   └── GetOrderUseCase.java
│   │   └── out/                (Outbound Ports)
│   │       ├── OrderRepository.java
│   │       ├── NotificationPort.java
│   │       └── PaymentPort.java
│   └── service/
│       ├── PlaceOrderService.java
│       └── GetOrderService.java
│
└── adapter/                    # Adapters
    ├── in/
    │   └── web/
    │       ├── OrderController.java
    │       ├── PlaceOrderRequest.java
    │       └── OrderResponse.java
    └── out/
        ├── persistence/
        │   ├── JpaOrderRepository.java
        │   ├── OrderEntity.java
        │   └── OrderEntityMapper.java
        ├── notification/
        │   └── SmtpNotificationAdapter.java
        └── payment/
            └── StripePaymentAdapter.java
```

---

## Test etmək

```java
// Domain testi — Spring lazım deyil
class OrderTest {

    @Test
    void shouldCalculateTotalCorrectly() {
        Order order = Order.create(
            CustomerId.generate(),
            List.of(
                new OrderItem(ProductId.generate(), 2, Money.of(50)),
                new OrderItem(ProductId.generate(), 1, Money.of(100))
            )
        );

        assertEquals(Money.of(200), order.calculateTotal());
    }

    @Test
    void shouldNotConfirmNonPendingOrder() {
        Order order = createConfirmedOrder();
        assertThrows(IllegalStateException.class, order::confirm);
    }
}

// Use Case testi — mock outbound port
@ExtendWith(MockitoExtension.class)
class PlaceOrderServiceTest {

    @Mock
    private OrderRepository orderRepository;

    @Mock
    private NotificationPort notificationPort;

    @InjectMocks
    private PlaceOrderService service;

    @Test
    void shouldPlaceOrderSuccessfully() {
        PlaceOrderCommand command = new PlaceOrderCommand(
            CustomerId.generate(),
            List.of(new OrderItemDto("prod-1", 2, 50.0))
        );

        when(orderRepository.save(any())).thenAnswer(i -> i.getArguments()[0]);

        Order result = service.placeOrder(command);

        assertNotNull(result);
        assertEquals(OrderStatus.PENDING, result.getStatus());
        verify(notificationPort).sendOrderConfirmation(any());
    }
}
```

---

## İntervyu Sualları

### 1. Hexagonal Architecture-ın əsas ideyası nədir?
**Cavab:** Domain (business logic) xarici dünyadan (DB, UI, API) tamamilə izolə edilir. Domain yalnız interface-lər (Ports) vasitəsilə xarici sistemlərlə danışır. Bu sayədə DB dəyişdirəndə domain kodu dəyişmir; domain-i tək başına test etmək mümkündür.

### 2. Port vs Adapter fərqi nədir?
**Cavab:** `Port` — interface (müqavilə), domain tərəfindən müəyyən edilir. `Adapter` — interface-in implementasiyası, xarici dünya tərəfindən. İnbound port (Use Case interface) + Primary adapter (REST controller). Outbound port (Repository interface) + Secondary adapter (JPA implementasiyası).

### 3. Bu arxitekturanın üstünlükləri nədir?
**Cavab:** (1) Domain test edilməsi asan — Spring/DB lazım deyil. (2) Texnoloji dəyişiklik asan — JPA → MongoDB keçidi yalnız adapter dəyişir. (3) Paralel inkişaf — team üzvlər domain və adapter-ları eyni vaxtda yaza bilir. (4) Business logic-in qorunması — texniki detallar domen-ə sızmır.

### 4. Layered Architecture ilə fərqi nədir?
**Cavab:** Layered (MVC) — Controller → Service → Repository, bağımlılıq aşağıya axır, domain JPA annotasiyaları bilir. Hexagonal — Domain mərkəzdə, heç bir xarici bağımlılıq yoxdur. Layered-də DB dəyişikliyi domain-i təsir edə bilər; Hexagonal-da yox.

### 5. Hansı məsələ üçün Hexagonal daha uyğundur?
**Cavab:** Kompleks business logic olan, uzun ömürlü sistemlər üçün. Çoxlu integration point-ləri (DB, API, Queue, Email). Domain-first development. Microservice mühitdə hər service öz hexagonal strukturuna malik ola bilər. Sadə CRUD app üçün overengineering sayıla bilər.

*Son yenilənmə: 2026-04-10*
