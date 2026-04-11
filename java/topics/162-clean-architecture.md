# Clean Architecture — Geniş İzah

## Mündəricat
1. [Clean Architecture nədir?](#clean-architecture-nədir)
2. [Qatlar — Layers](#qatlar--layers)
3. [Spring Boot-da Clean Architecture tətbiqi](#spring-boot-da-clean-architecture-tətbiqi)
4. [Use Case (Application Service) layer](#use-case-application-service-layer)
5. [Dependency Inversion — Port & Adapter](#dependency-inversion--port--adapter)
6. [Clean Architecture vs Layered Architecture](#clean-architecture-vs-layered-architecture)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Clean Architecture nədir?

```
Robert C. Martin (Uncle Bob) — 2012

Məqsəd:
  → Framework-dən müstəqil (Spring, Hibernate silinə bilər)
  → UI-dən müstəqil (REST, gRPC, CLI — dəyişə bilər)
  → DB-dən müstəqil (PostgreSQL, MongoDB, in-memory)
  → Test edilə bilən (dependency olmadan)

Əsas qayda — Dependency Rule:
  → Asılılıqlar yalnız daxardan içəriyə!
  → Xarici qatlar daxili qatlara bilir
  → Daxili qatlar xarici qatları bilmir

Dairəvi qatlar (ən içdən xariyə):
  [Entities] → [Use Cases] → [Interface Adapters] → [Frameworks & Drivers]

Alternativ adlar eyni konsept üçün:
  Hexagonal Architecture (Alistair Cockburn)
  Ports & Adapters
  Onion Architecture
```

---

## Qatlar — Layers

```
─────────────────────────────────────────────
          Frameworks & Drivers (xarici)
    Controllers, DB, UI, Web, Devices
─────────────────────────────────────────────
         Interface Adapters (adapter)
    Presenters, Gateways, Controllers
─────────────────────────────────────────────
          Use Cases (application)
    Application Business Rules
─────────────────────────────────────────────
            Entities (domain)
    Enterprise Business Rules
─────────────────────────────────────────────

Entities (Domain):
  → Biznes obyektləri: Order, Customer, Product
  → Biznes qaydaları: "Sifariş ləğv edilə bilməsi üçün 24 saat keçməmiş olmalı"
  → Spring, DB, framework bilmir
  → POJO/Record → sadə Java class-ları

Use Cases (Application):
  → Tətbiq iş axını: "Sifariş Yarat", "Ödəniş Et"
  → Entity-ləri orqestrasiya edir
  → Port (interface) vasitəsilə infrastruktura danışır
  → Spring bilmir (ya minimal bilir)

Interface Adapters:
  → Controller, Presenter, Gateway
  → Use case-ə request-i ötürür
  → Response-u UI formatına çevirir
  → Spring MVC/Spring Data burada

Frameworks & Drivers:
  → Spring Boot, JPA, Kafka, Redis
  → Ən xarici qat — hamısı bu qatda
```

---

## Spring Boot-da Clean Architecture tətbiqi

```java
// ─── Paket strukturu ─────────────────────────────────────
/*
com.example.shop/
├── domain/                          ← Entities
│   ├── model/
│   │   ├── Order.java
│   │   ├── OrderItem.java
│   │   └── Customer.java
│   ├── valueobject/
│   │   ├── Money.java
│   │   └── OrderStatus.java
│   └── exception/
│       └── OrderNotFoundException.java
│
├── application/                     ← Use Cases
│   ├── port/
│   │   ├── in/                      ← Input Ports (Use Case interfaces)
│   │   │   ├── CreateOrderUseCase.java
│   │   │   └── CancelOrderUseCase.java
│   │   └── out/                     ← Output Ports (Repository, Service interfaces)
│   │       ├── OrderRepository.java
│   │       ├── CustomerRepository.java
│   │       └── PaymentGateway.java
│   └── service/
│       ├── CreateOrderService.java  ← Use Case implementation
│       └── CancelOrderService.java
│
├── adapter/                         ← Interface Adapters
│   ├── in/
│   │   └── web/
│   │       ├── OrderController.java
│   │       ├── dto/
│   │       │   ├── CreateOrderRequest.java
│   │       │   └── OrderResponse.java
│   │       └── mapper/
│   │           └── OrderWebMapper.java
│   └── out/
│       ├── persistence/
│       │   ├── OrderJpaRepository.java    ← Spring Data interface
│       │   ├── OrderJpaEntity.java        ← @Entity class
│       │   ├── OrderPersistenceAdapter.java ← Port implementation
│       │   └── OrderPersistenceMapper.java
│       └── payment/
│           └── StripePaymentAdapter.java
│
└── config/                          ← Spring konfigurasyonu
    ├── BeanConfig.java
    └── SecurityConfig.java
*/

// ─── Domain Entity ────────────────────────────────────────
// Heç bir framework annotasiyası yoxdur!
public class Order {

    private final OrderId id;
    private final CustomerId customerId;
    private final List<OrderItem> items;
    private OrderStatus status;
    private final Instant createdAt;

    // Constructor
    public Order(OrderId id, CustomerId customerId, List<OrderItem> items) {
        if (items == null || items.isEmpty()) {
            throw new IllegalArgumentException("Sifariş ən az bir məhsul içərməlidir");
        }
        this.id = id;
        this.customerId = customerId;
        this.items = new ArrayList<>(items);
        this.status = OrderStatus.PENDING;
        this.createdAt = Instant.now();
    }

    // ─── Biznes qaydaları domain-də ───────────────────────
    public void cancel() {
        if (status != OrderStatus.PENDING) {
            throw new OrderCannotBeCancelledException(
                "Yalnız PENDING sifarişlər ləğv edilə bilər, cari status: " + status);
        }
        if (isOlderThan(Duration.ofHours(24))) {
            throw new OrderCannotBeCancelledException(
                "24 saatdan köhnə sifarişlər ləğv edilə bilməz");
        }
        this.status = OrderStatus.CANCELLED;
    }

    public Money calculateTotal() {
        return items.stream()
            .map(OrderItem::subtotal)
            .reduce(Money.ZERO, Money::add);
    }

    private boolean isOlderThan(Duration duration) {
        return Instant.now().isAfter(createdAt.plus(duration));
    }

    // Getters (no setters — immutable where possible)
    public OrderId getId() { return id; }
    public CustomerId getCustomerId() { return customerId; }
    public List<OrderItem> getItems() { return Collections.unmodifiableList(items); }
    public OrderStatus getStatus() { return status; }
}

// ─── Value Objects ────────────────────────────────────────
public record OrderId(String value) {
    public OrderId {
        Objects.requireNonNull(value, "OrderId boş ola bilməz");
    }
    public static OrderId generate() { return new OrderId(UUID.randomUUID().toString()); }
}

public record Money(BigDecimal amount, String currency) {
    public static final Money ZERO = new Money(BigDecimal.ZERO, "AZN");

    public Money add(Money other) {
        if (!this.currency.equals(other.currency)) {
            throw new IllegalArgumentException("Fərqli valyutaları toplamaq olmaz");
        }
        return new Money(this.amount.add(other.amount), this.currency);
    }
}
```

---

## Use Case (Application Service) layer

```java
// ─── Input Port — Use Case Interface ─────────────────────
public interface CreateOrderUseCase {

    OrderResult createOrder(CreateOrderCommand command);

    record CreateOrderCommand(
        String customerId,
        List<OrderItemDto> items,
        String idempotencyKey
    ) {}

    record OrderItemDto(String productId, int quantity, BigDecimal unitPrice) {}

    record OrderResult(String orderId, String status, BigDecimal total) {}
}

// ─── Output Ports ─────────────────────────────────────────
// Heç bir Spring, JPA annotasiyası yoxdur — sadə interface!
public interface OrderRepository {
    Order save(Order order);
    Optional<Order> findById(OrderId id);
    boolean existsByIdempotencyKey(String key);
}

public interface CustomerRepository {
    Optional<Customer> findById(CustomerId id);
}

public interface PaymentGateway {
    PaymentResult charge(CustomerId customerId, Money amount);
}

// ─── Use Case Implementation ──────────────────────────────
@UseCase  // Custom annotation ya da @Service
@Transactional
public class CreateOrderService implements CreateOrderUseCase {

    private final OrderRepository orderRepository;
    private final CustomerRepository customerRepository;
    private final PaymentGateway paymentGateway;

    // Constructor injection — Spring bilmir, amma Spring inject edir
    public CreateOrderService(OrderRepository orderRepository,
                               CustomerRepository customerRepository,
                               PaymentGateway paymentGateway) {
        this.orderRepository = orderRepository;
        this.customerRepository = customerRepository;
        this.paymentGateway = paymentGateway;
    }

    @Override
    public OrderResult createOrder(CreateOrderCommand command) {
        // Idempotency check
        if (orderRepository.existsByIdempotencyKey(command.idempotencyKey())) {
            throw new DuplicateOrderException("Bu sifariş artıq yaradılıb");
        }

        // Customer mövcuddur?
        Customer customer = customerRepository.findById(
            new CustomerId(command.customerId()))
            .orElseThrow(() -> new CustomerNotFoundException(command.customerId()));

        // Domain entity yarat
        List<OrderItem> orderItems = command.items().stream()
            .map(dto -> new OrderItem(
                new ProductId(dto.productId()),
                dto.quantity(),
                new Money(dto.unitPrice(), "AZN")
            ))
            .toList();

        Order order = new Order(
            OrderId.generate(),
            customer.getId(),
            orderItems
        );

        // Ödəniş — Output Port vasitəsilə
        Money total = order.calculateTotal();
        PaymentResult payment = paymentGateway.charge(customer.getId(), total);

        if (!payment.isSuccessful()) {
            throw new PaymentFailedException("Ödəniş uğursuz oldu: " + payment.message());
        }

        // Domain metodunu çağır (biznes qaydası domain-dədir)
        order.markAsPaid(payment.transactionId());

        // Persist — Output Port vasitəsilə
        Order saved = orderRepository.save(order);

        return new OrderResult(
            saved.getId().value(),
            saved.getStatus().name(),
            saved.calculateTotal().amount()
        );
    }
}

// ─── Custom Stereotype Annotation ────────────────────────
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Component  // Spring component-i kimi qeyd et
public @interface UseCase {}
```

---

## Dependency Inversion — Port & Adapter

```java
// ─── Output Adapter: Persistence ─────────────────────────
// Application qatından asılılığı ters çevirir

// JPA Entity — framework-specific (adapter qatında)
@Entity
@Table(name = "orders")
class OrderJpaEntity {

    @Id
    private String id;

    @Column(name = "customer_id")
    private String customerId;

    @Enumerated(EnumType.STRING)
    private OrderStatus status;

    @Column(name = "idempotency_key", unique = true)
    private String idempotencyKey;

    @CreationTimestamp
    private Instant createdAt;

    @OneToMany(cascade = CascadeType.ALL, orphanRemoval = true)
    private List<OrderItemJpaEntity> items;
}

// Spring Data Repository (adapter qatında)
interface OrderJpaRepository extends JpaRepository<OrderJpaEntity, String> {
    boolean existsByIdempotencyKey(String key);
}

// Adapter — Domain Port-unu implement edir
@Component  // Spring bean
class OrderPersistenceAdapter implements OrderRepository {

    private final OrderJpaRepository jpaRepository;
    private final OrderPersistenceMapper mapper;

    @Override
    public Order save(Order order) {
        OrderJpaEntity entity = mapper.toJpaEntity(order);
        OrderJpaEntity saved = jpaRepository.save(entity);
        return mapper.toDomainEntity(saved);
    }

    @Override
    public Optional<Order> findById(OrderId id) {
        return jpaRepository.findById(id.value())
            .map(mapper::toDomainEntity);
    }

    @Override
    public boolean existsByIdempotencyKey(String key) {
        return jpaRepository.existsByIdempotencyKey(key);
    }
}

// ─── Output Adapter: External Payment ────────────────────
@Component
class StripePaymentAdapter implements PaymentGateway {

    private final StripeClient stripeClient;

    @Override
    public PaymentResult charge(CustomerId customerId, Money amount) {
        try {
            StripeCharge charge = stripeClient.charge(
                customerId.value(),
                amount.amount(),
                amount.currency()
            );
            return PaymentResult.success(charge.getId());
        } catch (StripeException e) {
            log.error("Stripe ödəniş xətası", e);
            return PaymentResult.failure(e.getMessage());
        }
    }
}

// ─── Input Adapter: Web Controller ───────────────────────
@RestController
@RequestMapping("/api/orders")
class OrderController {

    private final CreateOrderUseCase createOrderUseCase;
    private final OrderWebMapper mapper;

    @PostMapping
    public ResponseEntity<OrderResponse> createOrder(
            @RequestBody @Valid CreateOrderRequest request,
            @RequestHeader("Idempotency-Key") String idempotencyKey) {

        // Request → Command (DTO → Application command)
        CreateOrderUseCase.CreateOrderCommand command = mapper.toCommand(request, idempotencyKey);

        // Use case-i çağır
        CreateOrderUseCase.OrderResult result = createOrderUseCase.createOrder(command);

        // Result → Response (Application result → DTO)
        OrderResponse response = mapper.toResponse(result);

        return ResponseEntity.status(HttpStatus.CREATED).body(response);
    }
}

// ─── Dependency Inversion görsel ─────────────────────────
/*
  [OrderController]  →  <<interface>> CreateOrderUseCase
                                              ↑ implements
                                    [CreateOrderService]
                                              ↓ depends on
                        <<interface>> OrderRepository  PaymentGateway
                                              ↑ implements  ↑ implements
                         [OrderPersistenceAdapter]  [StripePaymentAdapter]
                                  ↓                          ↓
                          [JpaRepository]              [StripeClient]

  Daxili qatlar (interface) → Xarici qatlar (implementation)
  Asılılıq: Xarici → Daxili (Dependency Rule qorunur ✅)
*/
```

---

## Clean Architecture vs Layered Architecture

```java
// ─── Klassik Layered Architecture ────────────────────────
// Controller → Service → Repository → DB

// Problem: Service-dən birbaşa JPA Repository-ə asılılıq
@Service
public class OrderServiceLayered {
    private final OrderJpaRepository jpaRepository; // JPA birbaşa service-də!

    public Order createOrder(CreateOrderRequest request) {
        OrderEntity entity = new OrderEntity(...);  // Entity → DTO qarışıqlığı
        return jpaRepository.save(entity);
    }
}

// ─── Clean Architecture ───────────────────────────────────
// Service Port-a (interface-ə) asılıdır, JPA yox!

@UseCase
public class CreateOrderService implements CreateOrderUseCase {
    private final OrderRepository repo; // Interface! JPA deyil!
    // ...
}

/*
Müqayisə:
─────────────────────────────────────────────
                 Layered      Clean
─────────────────────────────────────────────
Mürəkkəblik      Sadə         Yüksək
Test edilə bilmə Orta         Çox yüksək
Framework dəyişmə Çətin        Asan
DB dəyişmə       Çətin        Asan
Mikro xidmət     Orta         Uyğun
Böyük komanda    Problem      İdeal
Kiçik layihə     İdeal        Over-engineering
─────────────────────────────────────────────

Nə zaman Clean Architecture?
  ✅ Mürəkkəb biznes məntiqi
  ✅ Böyük komandalar
  ✅ Uzun ömürlü layihə
  ✅ Tez-tez dəyişən framework/DB
  ✅ Yüksək test əhatəsi lazımdır

Nə zaman Layered:
  ✅ Kiçik CRUD layihəsi
  ✅ Prototyping
  ✅ Kiçik komanda
  ✅ Biznes məntiqi az
*/

// ─── Testing — Framework olmadan ─────────────────────────
class CreateOrderServiceTest {

    // Mock implementation — Spring yoxdur!
    private final OrderRepository orderRepository = new InMemoryOrderRepository();
    private final CustomerRepository customerRepository = new InMemoryCustomerRepository();
    private final PaymentGateway paymentGateway = new FakePaymentGateway();

    private final CreateOrderService service = new CreateOrderService(
        orderRepository, customerRepository, paymentGateway);

    @Test
    void shouldCreateOrderSuccessfully() {
        // Given
        customerRepository.save(new Customer(new CustomerId("c1"), "John Doe"));

        var command = new CreateOrderUseCase.CreateOrderCommand(
            "c1",
            List.of(new CreateOrderUseCase.OrderItemDto("p1", 2, new BigDecimal("50.00"))),
            "idempotency-key-1"
        );

        // When
        var result = service.createOrder(command);

        // Then
        assertThat(result.status()).isEqualTo("PAID");
        assertThat(result.total()).isEqualByComparingTo("100.00");
    }
}

// In-memory test implementation
class InMemoryOrderRepository implements OrderRepository {
    private final Map<OrderId, Order> store = new HashMap<>();

    @Override
    public Order save(Order order) { store.put(order.getId(), order); return order; }

    @Override
    public Optional<Order> findById(OrderId id) { return Optional.ofNullable(store.get(id)); }

    @Override
    public boolean existsByIdempotencyKey(String key) {
        return store.values().stream()
            .anyMatch(o -> key.equals(o.getIdempotencyKey()));
    }
}
```

---

## İntervyu Sualları

### 1. Clean Architecture-in əsas prinsipi nədir?
**Cavab:** **Dependency Rule** — asılılıqlar yalnız xarici qatlardan daxili qatlara. Domain (entities) heç bir framework, DB, UI bilmir. Use Case layer yalnız domain-ə asılıdır. Interface Adapters use case-ə, Frameworks & Drivers adapterə asılıdır. Bu qaydaya görə framework (Spring) silinə bilər, DB (PostgreSQL → MongoDB) dəyişdirilə bilər, domain məntiqi toxunulmaz qalır.

### 2. Port & Adapter (Hexagonal) nədir?
**Cavab:** Port — interface, Adapter — implementation. **Input Port**: Use Case interface (CreateOrderUseCase) — xarici dünya tətbiqi necə çağıracaq. **Input Adapter**: Controller — HTTP request-i Use Case command-ına çevirir. **Output Port**: Repository interface (OrderRepository) — Use Case infrastrukturdan nə tələb edir. **Output Adapter**: JPA implementation (OrderPersistenceAdapter) — port-u konkret texnologiya ilə implement edir. Dependency Inversion: Use Case output port-a (interface-ə) asılıdır, konkret implementasiyaya yox.

### 3. Domain Entity vs JPA Entity fərqi?
**Cavab:** **Domain Entity** — biznes məntiqi daşıyır, framework annotasiyası yoxdur (@Entity, @Column yoxdur), Java POJO. **JPA Entity** — DB persistence üçün, @Entity, @Column, @Table annotasiyaları var, mapper adapter qatında. Clean Architecture-da bu iki qat ayrı olmalıdır: JPA Entity-ni domain-ə buraxmamaq. OrderPersistenceMapper — domain entity ↔ JPA entity arası çevirmə edir.

### 4. Clean Architecture tətbiqinin çatışmazlıqları?
**Cavab:** (1) **Mürəkkəblik** — sadə CRUD üçün over-engineering, əlavə interface, mapper, paket; (2) **Boilerplate** — hər entity üçün domain model + JPA entity + mapper lazımdır; (3) **Öyrənmə əyrisi** — komanda üçün daha uzun öyrənmə; (4) **Mapping xərci** — domain ↔ persistence ↔ DTO çevirmə; (5) **Qərar çətinliyi** — hansı məntiqi domain-də, hansını use case-də qoymaq. Sadə layihə üçün Layered Architecture daha praktikdir.

### 5. Spring Boot-da Use Case layer Spring-dən asılı olmalıdırmı?
**Cavab:** Ideal olaraq **yox** — Use Case pure Java, Spring annotation-sız. Amma praktikada `@Transactional`, `@UseCase` (@Component wrapper) istifadə olunur — bu minimal asılılıqdır. Kritik olan: Use Case `@Repository`, `@Entity`, JPA interface-lərini bilməməlidir. Port (interface) vasitəsilə əlaqə qurulur. Use Case-in unit test-ləri Spring olmadan, mock ya fake implementations ilə çalışmalıdır — bu asılılığın düzgün tərsinə qoyulduğunu sübut edir.

*Son yenilənmə: 2026-04-10*
