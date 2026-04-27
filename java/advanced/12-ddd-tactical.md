# DDD Tactical Patterns — Java ilə Geniş İzah (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐


## Mündəricat
1. [DDD nədir?](#ddd-nədir)
2. [Entity vs Value Object](#entity-vs-value-object)
3. [Aggregate və Aggregate Root](#aggregate-və-aggregate-root)
4. [Repository pattern](#repository-pattern)
5. [Domain Events](#domain-events)
6. [Domain Service](#domain-service)
7. [İntervyu Sualları](#intervyu-sualları)

---

## DDD nədir?

**Domain-Driven Design (DDD)** — Eric Evans tərəfindən təqdim edilmiş, mürəkkəb domain-ləri modelləmək üçün yanaşma. Tactical patterns: Entity, Value Object, Aggregate, Repository, Domain Event, Domain Service.

---

## Entity vs Value Object

```java
// ===== Entity — ID ilə eyniləşdirilir =====
public class User {
    private final UserId id;      // ID dəyişmir
    private String name;          // Mutable
    private Email email;          // Mutable
    private UserStatus status;

    // equals/hashCode — ID əsasında
    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof User user)) return false;
        return id.equals(user.id);
    }

    @Override
    public int hashCode() { return id.hashCode(); }
}

// ===== Value Object — dəyərə görə müqayisə, immutable =====
public record Email(String value) {
    public Email {
        if (value == null || !value.contains("@")) {
            throw new IllegalArgumentException("Yanlış email: " + value);
        }
        value = value.toLowerCase().trim();
    }
}

public record Money(BigDecimal amount, String currency) {
    public static Money of(BigDecimal amount) {
        return new Money(amount, "AZN");
    }

    public Money add(Money other) {
        if (!this.currency.equals(other.currency)) {
            throw new IllegalArgumentException("Fərqli valyuta");
        }
        return new Money(this.amount.add(other.amount), this.currency);
    }

    public Money subtract(Money other) {
        Money result = new Money(this.amount.subtract(other.amount), this.currency);
        if (result.amount.compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Mənfi məbləğ");
        }
        return result;
    }
}

public record Address(String street, String city, String country, String zipCode) {
    // Dəyişdirilməz — yeni Address yaradılır
    public Address withCity(String newCity) {
        return new Address(street, newCity, country, zipCode);
    }
}

// İstifadəsi
User user1 = new User(UserId.of("123"), "Ali", new Email("ali@example.com"));
User user2 = new User(UserId.of("123"), "Ali Məmmədov", new Email("ali@example.com"));
user1.equals(user2); // true — eyni ID

Money price1 = Money.of(new BigDecimal("100"));
Money price2 = Money.of(new BigDecimal("100"));
price1.equals(price2); // true — eyni dəyər (record)
```

---

## Aggregate və Aggregate Root

```java
// Aggregate = bir-birinə bağlı Entity-lərin toplusu
// Aggregate Root = xarici dünyadan erişim nöqtəsi

public class Order {  // Aggregate Root

    private final OrderId id;
    private final CustomerId customerId;
    private final List<OrderItem> items; // Aggregate daxili entity
    private OrderStatus status;
    private Money totalAmount;

    // Yalnız Aggregate Root-dan dəyişiklik
    public void addItem(ProductId productId, int quantity, Money price) {
        if (status != OrderStatus.DRAFT) {
            throw new IllegalStateException("Yalnız DRAFT sifariş-ə item əlavə etmək olar");
        }

        // OrderItem-i birbaşa əlavə et — xaricdən etmə!
        OrderItem item = new OrderItem(OrderItemId.generate(),
                                       productId, quantity, price);
        this.items.add(item);
        recalculateTotal();
    }

    public void removeItem(OrderItemId itemId) {
        boolean removed = items.removeIf(item -> item.getId().equals(itemId));
        if (!removed) throw new EntityNotFoundException("Item tapılmadı");
        recalculateTotal();
    }

    public void submit() {
        if (items.isEmpty()) throw new IllegalStateException("Boş sifariş təqdim edilə bilməz");
        this.status = OrderStatus.SUBMITTED;
        // Domain event raise et
        registerEvent(new OrderSubmittedEvent(id, customerId, totalAmount));
    }

    private void recalculateTotal() {
        this.totalAmount = items.stream()
            .map(item -> item.getUnitPrice().multiply(item.getQuantity()))
            .reduce(Money.ZERO, Money::add);
    }
}

// OrderItem — Aggregate daxili Entity (xaricdən birbaşa dəyişdirilmir)
public class OrderItem {
    private final OrderItemId id;
    private final ProductId productId;
    private int quantity;
    private final Money unitPrice;

    // Package-private constructor — yalnız Order yaradır
    OrderItem(OrderItemId id, ProductId productId, int quantity, Money unitPrice) {
        this.id = id;
        this.productId = productId;
        this.quantity = quantity;
        this.unitPrice = unitPrice;
    }
}
```

**Aggregate qaydaları:**
```
1. Aggregate Root vasitəsilə erişim — OrderItem-ə birbaşa yox
2. Aggregate sərhədlərindən kənar reference yalnız ID ilə
3. Transaction = bir Aggregate — iki Aggregate-i eyni TX-də dəyişmə
4. Aggregate-ı kiçik saxla — böyük Aggregate = performans problemi
```

---

## Repository pattern

```java
// Domain tərəfindən repository interface
public interface OrderRepository {
    Order save(Order order);
    Optional<Order> findById(OrderId id);
    List<Order> findByCustomerId(CustomerId customerId);
    void delete(OrderId id);
}

// Spring Data ilə tətbiq
@Repository
public class SpringDataOrderRepository implements OrderRepository {

    private final JpaOrderEntityRepository jpa;
    private final OrderMapper mapper;

    @Override
    public Order save(Order order) {
        OrderEntity entity = mapper.toEntity(order);
        OrderEntity saved = jpa.save(entity);
        return mapper.toDomain(saved);
    }

    @Override
    public Optional<Order> findById(OrderId id) {
        return jpa.findById(id.value().toString())
            .map(mapper::toDomain);
    }
}

// JPA Entity (ayrı — domain model-dən fərqli)
@Entity
@Table(name = "orders")
public class OrderEntity {
    @Id
    private String id;
    private String customerId;
    private String status;

    @OneToMany(cascade = CascadeType.ALL, orphanRemoval = true)
    @JoinColumn(name = "order_id")
    private List<OrderItemEntity> items;
}
```

---

## Domain Events

```java
// Domain Event — domain-də baş verən hadisə
public record OrderSubmittedEvent(
        OrderId orderId,
        CustomerId customerId,
        Money totalAmount,
        Instant occurredOn) implements DomainEvent {

    public OrderSubmittedEvent(OrderId orderId,
                                CustomerId customerId,
                                Money totalAmount) {
        this(orderId, customerId, totalAmount, Instant.now());
    }
}

// Aggregate Root-da event registration
public abstract class AggregateRoot {
    private final List<DomainEvent> events = new ArrayList<>();

    protected void registerEvent(DomainEvent event) {
        events.add(event);
    }

    public List<DomainEvent> pullEvents() {
        List<DomainEvent> copy = new ArrayList<>(events);
        events.clear();
        return copy;
    }
}

public class Order extends AggregateRoot {
    public void submit() {
        this.status = OrderStatus.SUBMITTED;
        registerEvent(new OrderSubmittedEvent(id, customerId, totalAmount));
    }
}

// Event Handler (Application layer)
@Service
public class OrderEventService {

    private final ApplicationEventPublisher eventPublisher;
    private final OrderRepository repository;

    @Transactional
    public Order submitOrder(OrderId orderId) {
        Order order = repository.findById(orderId).orElseThrow();
        order.submit();
        Order saved = repository.save(order);

        // Domain event-lərini publish et
        saved.pullEvents().forEach(eventPublisher::publishEvent);

        return saved;
    }
}

// Event listener
@Component
public class OrderEventListener {

    @EventListener
    public void onOrderSubmitted(OrderSubmittedEvent event) {
        // Notification göndər, inventory azalt, vb.
        notificationService.sendOrderConfirmation(event.orderId());
        inventoryService.reserveItems(event.orderId());
    }
}
```

---

## Domain Service

```java
// Domain Service — bir neçə Aggregate-ı əhatə edən business logic
// Entity-yə aid olmayan əməliyyatlar

public class MoneyTransferDomainService {

    public TransferResult transfer(Account from, Account to, Money amount) {
        // Business rule — hər iki account iştirak edir
        if (from.getBalance().compareTo(amount) < 0) {
            throw new InsufficientFundsException("Balans kifayət deyil");
        }

        if (from.getCurrency() != to.getCurrency()) {
            throw new CurrencyMismatchException("Fərqli valyuta");
        }

        from.debit(amount);
        to.credit(amount);

        return new TransferResult(from.getId(), to.getId(), amount);
    }
}

// Application Service-dən çağırma
@Service
public class MoneyTransferService {

    private final AccountRepository accountRepository;
    private final MoneyTransferDomainService domainService;

    @Transactional
    public void transfer(AccountId fromId, AccountId toId, Money amount) {
        Account from = accountRepository.findById(fromId).orElseThrow();
        Account to = accountRepository.findById(toId).orElseThrow();

        domainService.transfer(from, to, amount); // Domain logic

        accountRepository.save(from);
        accountRepository.save(to);
    }
}
```

---

## İntervyu Sualları

### 1. Entity vs Value Object fərqi nədir?
**Cavab:** `Entity` — unikal ID-ə malikdir, dəyişdiriləbildir, eyni ID = eyni entity (fərqli attribute-larla belə). `Value Object` — ID-siz, dəyərə görə müqayisə, immutable. Pul, tarix, ünvan tipik Value Object-lərdir. Java `record` VO üçün ideal.

### 2. Aggregate Root nədir?
**Cavab:** Aggregate-ın xarici dünyaya açılan tək girişi. Aggregate daxilindəki entity-lərə birbaşa erişim yalnız Root vasitəsilə. Bu consistency boundary yaradır. Xarici sistemlər digər entity-ləri yalnız ID ilə reference edə bilər.

### 3. Repository pattern-ın məqsədi nədir?
**Cavab:** Domain-in DB implementasiyasından abstraksiya. Domain yalnız `OrderRepository` interface-ini bilir — JPA, MongoDB, yaxud in-memory olması fərq etmir. Bu domain-i test edilməsini asanlaşdırır, texnoloji keçidi mümkün edir.

### 4. Domain Event nə üçündür?
**Cavab:** Aggregate daxilindəki mühüm hadisələri (OrderSubmitted, PaymentFailed) qeyd etmək üçün. Aggregate-lar arası loose coupling — digər Aggregate event-ə abunə olur. Eventual consistency — eyni transaction-da deyil, event handler-da. Audit trail — hər şey qeydə alınır.

### 5. Application Service vs Domain Service fərqi?
**Cavab:** `Application Service` — orchestration, transaction idarəetməsi, repository çağırma, security. Spring annotasiyaları buradadır. `Domain Service` — bir neçə Aggregate-ı əhatə edən business logic, framework-dən asılı deyil, test edilməsi asan. Əgər əməliyyat bir Entity-yə aiddirsə — Entity metoduna; bir neçə Entity-yə aiddirsə — Domain Service-ə.

*Son yenilənmə: 2026-04-10*
