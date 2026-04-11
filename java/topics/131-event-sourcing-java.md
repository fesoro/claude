# Event Sourcing — Java ilə Geniş İzah

## Mündəricat
1. [Event Sourcing nədir?](#event-sourcing-nədir)
2. [Event Store](#event-store)
3. [Aggregate rehydration](#aggregate-rehydration)
4. [Projection (Read model)](#projection-read-model)
5. [Spring ilə tətbiq](#spring-ilə-tətbiq)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Event Sourcing nədir?

**Event Sourcing** — cari vəziyyəti saxlamaq əvəzinə, baş verən hadisələri (events) saxlamaq. Cari vəziyyət event-lərin yenidən oynatılması (replay) ilə alınır.

```
Traditional DB:
  orders table: {id: 1, status: "SHIPPED", amount: 100}
  (Niyə SHIPPED? Kim dəyişdi? Keçmiş nə idi? — BİLMİRİK)

Event Sourcing:
  event_store:
    OrderPlaced    {orderId: 1, customerId: 5, amount: 100}  → t=10:00
    OrderConfirmed {orderId: 1, confirmedBy: "admin"}        → t=10:05
    OrderShipped   {orderId: 1, trackingNumber: "ABC123"}    → t=14:00

  Cari vəziyyət = bütün event-lərin replay-i
```

**Üstünlüklər:**
- Tam audit trail — nə, nə vaxt, kim
- Keçmiş vəziyyəti bərpa etmək mümkün
- Debug imkanı artır
- Event replay ilə yeni projection-lar yaratmaq

**Çatışmazlıqlar:**
- Complexity yüksəkdir
- Event schema dəyişikliyi çətin (upcasting lazımdır)
- Read ilk anda çətindir (projection lazımdır)

---

## Event Store

```java
// Domain Event interface
public interface DomainEvent {
    UUID aggregateId();
    long sequenceNumber();
    Instant occurredOn();
    String eventType();
}

// Event Store record
@Entity
@Table(name = "event_store")
public class EventRecord {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private UUID aggregateId;
    private String aggregateType;
    private long sequenceNumber;
    private String eventType;

    @Column(columnDefinition = "TEXT")
    private String payload;         // JSON

    private Instant occurredOn;
    private String occurredBy;      // User ID
    private String correlationId;

    @Version
    private Long version;           // Optimistic locking
}

// Event Store Service
@Service
public class EventStoreService {

    private final EventRecordRepository eventRecordRepository;
    private final ObjectMapper objectMapper;

    @Transactional
    public void append(UUID aggregateId, String aggregateType,
                       long expectedVersion, List<DomainEvent> events) {
        // Optimistic concurrency check
        long lastVersion = eventRecordRepository
            .findLastSequenceNumber(aggregateId)
            .orElse(-1L);

        if (lastVersion != expectedVersion) {
            throw new OptimisticLockException(
                "Aggregate concurrently modified. Expected: " + expectedVersion +
                ", Found: " + lastVersion);
        }

        long sequenceStart = lastVersion + 1;
        List<EventRecord> records = new ArrayList<>();

        for (int i = 0; i < events.size(); i++) {
            DomainEvent event = events.get(i);
            EventRecord record = new EventRecord();
            record.setAggregateId(aggregateId);
            record.setAggregateType(aggregateType);
            record.setSequenceNumber(sequenceStart + i);
            record.setEventType(event.getClass().getSimpleName());
            record.setPayload(serialize(event));
            record.setOccurredOn(Instant.now());
            records.add(record);
        }

        eventRecordRepository.saveAll(records);
    }

    public List<DomainEvent> loadEvents(UUID aggregateId) {
        return eventRecordRepository
            .findByAggregateIdOrderBySequenceNumber(aggregateId)
            .stream()
            .map(this::deserialize)
            .collect(Collectors.toList());
    }

    public List<DomainEvent> loadEventsSince(UUID aggregateId, long fromSequence) {
        return eventRecordRepository
            .findByAggregateIdAndSequenceNumberGreaterThan(aggregateId, fromSequence)
            .stream()
            .map(this::deserialize)
            .collect(Collectors.toList());
    }

    private String serialize(DomainEvent event) {
        try {
            return objectMapper.writeValueAsString(event);
        } catch (JsonProcessingException e) {
            throw new RuntimeException("Event serialize edilə bilmədi", e);
        }
    }

    private DomainEvent deserialize(EventRecord record) {
        // Event type-a görə class seç
        Class<? extends DomainEvent> eventClass = eventTypeRegistry
            .getClass(record.getEventType());
        try {
            return objectMapper.readValue(record.getPayload(), eventClass);
        } catch (JsonProcessingException e) {
            throw new RuntimeException("Event deserialize edilə bilmədi", e);
        }
    }
}
```

---

## Aggregate rehydration

```java
// Event-Sourced Aggregate
public class BankAccount {

    private UUID id;
    private String ownerId;
    private Money balance;
    private AccountStatus status;
    private long version = -1L; // Optimistic locking üçün

    private final List<DomainEvent> pendingEvents = new ArrayList<>();

    // ===== Factory (yeni hesab) =====
    public static BankAccount open(String ownerId, Money initialBalance) {
        BankAccount account = new BankAccount();
        account.apply(new AccountOpenedEvent(
            UUID.randomUUID(), ownerId, initialBalance));
        return account;
    }

    // ===== Commands =====
    public void deposit(Money amount) {
        if (status != AccountStatus.ACTIVE) {
            throw new IllegalStateException("Hesab aktiv deyil");
        }
        apply(new MoneyDepositedEvent(id, amount));
    }

    public void withdraw(Money amount) {
        if (status != AccountStatus.ACTIVE) {
            throw new IllegalStateException("Hesab aktiv deyil");
        }
        if (balance.compareTo(amount) < 0) {
            throw new InsufficientFundsException("Balans kifayət deyil");
        }
        apply(new MoneyWithdrawnEvent(id, amount));
    }

    public void close(String reason) {
        if (balance.isPositive()) {
            throw new IllegalStateException("Balansı sıfırlamadan hesabı bağlamaq olmaz");
        }
        apply(new AccountClosedEvent(id, reason));
    }

    // ===== apply — event-i tətbiq et =====
    private void apply(DomainEvent event) {
        when(event);              // State-i yenilə
        pendingEvents.add(event); // Pending list-ə əlavə et
        version++;
    }

    // ===== when — state mutation =====
    public void when(DomainEvent event) {
        switch (event) {
            case AccountOpenedEvent e -> {
                this.id = e.aggregateId();
                this.ownerId = e.ownerId();
                this.balance = e.initialBalance();
                this.status = AccountStatus.ACTIVE;
            }
            case MoneyDepositedEvent e -> {
                this.balance = balance.add(e.amount());
            }
            case MoneyWithdrawnEvent e -> {
                this.balance = balance.subtract(e.amount());
            }
            case AccountClosedEvent e -> {
                this.status = AccountStatus.CLOSED;
            }
            default -> throw new UnknownEventException(event.getClass());
        }
        this.version = event.sequenceNumber();
    }

    // ===== Rehydration — event-lərdən yenidən yarat =====
    public static BankAccount rehydrate(List<DomainEvent> events) {
        if (events.isEmpty()) throw new IllegalArgumentException("Event-lər boşdur");

        BankAccount account = new BankAccount();
        events.forEach(account::when);
        return account;
    }

    public List<DomainEvent> getPendingEvents() {
        return Collections.unmodifiableList(pendingEvents);
    }

    public void markEventsAsCommitted() {
        pendingEvents.clear();
    }
}
```

---

## Projection (Read model)

```java
// Projection — event-lərdən read model yarat
@Component
public class AccountSummaryProjection {

    private final AccountSummaryRepository readRepository;

    @EventListener
    @Transactional
    public void on(AccountOpenedEvent event) {
        AccountSummary summary = new AccountSummary();
        summary.setId(event.aggregateId().toString());
        summary.setOwnerId(event.ownerId());
        summary.setBalance(event.initialBalance().amount());
        summary.setCurrency(event.initialBalance().currency());
        summary.setStatus("ACTIVE");
        summary.setOpenedAt(event.occurredOn());
        readRepository.save(summary);
    }

    @EventListener
    @Transactional
    public void on(MoneyDepositedEvent event) {
        readRepository.findById(event.aggregateId().toString())
            .ifPresent(summary -> {
                summary.setBalance(summary.getBalance().add(event.amount().amount()));
                summary.setLastTransactionAt(event.occurredOn());
                readRepository.save(summary);
            });
    }

    @EventListener
    @Transactional
    public void on(MoneyWithdrawnEvent event) {
        readRepository.findById(event.aggregateId().toString())
            .ifPresent(summary -> {
                summary.setBalance(summary.getBalance().subtract(event.amount().amount()));
                summary.setLastTransactionAt(event.occurredOn());
                readRepository.save(summary);
            });
    }
}

// Event replay — yeni projection yaratmaq
@Service
public class ProjectionRebuildService {

    private final EventStoreService eventStoreService;
    private final AccountSummaryProjection projection;

    public void rebuildAccountProjection(UUID accountId) {
        List<DomainEvent> events = eventStoreService.loadEvents(accountId);
        // Read model-i sıfırla
        readRepository.deleteById(accountId.toString());
        // Bütün event-ləri yenidən tətbiq et
        events.forEach(event -> {
            if (event instanceof AccountOpenedEvent e) projection.on(e);
            else if (event instanceof MoneyDepositedEvent e) projection.on(e);
            else if (event instanceof MoneyWithdrawnEvent e) projection.on(e);
        });
    }
}
```

---

## Spring ilə tətbiq

```java
// Event-Sourced Repository
@Component
public class BankAccountRepository {

    private final EventStoreService eventStoreService;

    public void save(BankAccount account) {
        List<DomainEvent> pendingEvents = account.getPendingEvents();
        if (pendingEvents.isEmpty()) return;

        eventStoreService.append(
            account.getId(),
            "BankAccount",
            account.getVersion() - pendingEvents.size(),
            pendingEvents
        );

        account.markEventsAsCommitted();
    }

    public Optional<BankAccount> findById(UUID accountId) {
        List<DomainEvent> events = eventStoreService.loadEvents(accountId);
        if (events.isEmpty()) return Optional.empty();
        return Optional.of(BankAccount.rehydrate(events));
    }
}

// Snapshot — performans üçün
@Service
public class SnapshotService {

    private final SnapshotRepository snapshotRepository;
    private final EventStoreService eventStoreService;

    // Hər 50 event-dən bir snapshot yarat
    public BankAccount loadWithSnapshot(UUID accountId) {
        Optional<Snapshot> snapshot = snapshotRepository.findLatest(accountId);

        if (snapshot.isEmpty()) {
            // Snapshot yoxdur — bütün event-ləri replay et
            List<DomainEvent> events = eventStoreService.loadEvents(accountId);
            return BankAccount.rehydrate(events);
        }

        // Snapshot-dan başla, yalnız sonrakı event-ləri yüklə
        BankAccount account = deserializeSnapshot(snapshot.get());
        List<DomainEvent> newEvents = eventStoreService.loadEventsSince(
            accountId, snapshot.get().getVersion());
        newEvents.forEach(account::when);

        return account;
    }
}
```

---

## İntervyu Sualları

### 1. Event Sourcing-in əsas ideyası nədir?
**Cavab:** Cari vəziyyəti saxlamaq əvəzinə, baş vermiş hadisələri ardıcıl saxlamaq. Cari vəziyyət event-lərin replay-i ilə alınır. Bu tam audit trail verir, keçmiş vəziyyəti bərpa etmək mümkündür, yeni projection-lar yaratmaq olar.

### 2. Snapshot nə üçün istifadə edilir?
**Cavab:** Aggregate çox event-ə malik olduqda hər dəfə hamısını replay etmək yavaşdır. Snapshot cari vəziyyəti müəyyən nöqtədə saxlayır. Reload zamanı snapshot-dan başlanır, yalnız sonrakı event-lər tətbiq edilir. Hər N event-dən bir snapshot tövsiyə olunur.

### 3. Event schema dəyişikliyi necə idarə olunur (upcasting)?
**Cavab:** Event-lər dəyişdirilmir — immutable-dır. Yeni versiyanı oxuyarkən köhnə formatı yeniyə çevirmək üçün Upcaster istifadə edilir. `EventRecord`-da `eventType` yaxud `schemaVersion` saxlanılır. Deserializasiya zamanı uyğun converter tətbiq olunur.

### 4. Event Sourcing + CQRS necə birlikdə işləyir?
**Cavab:** Write side: Command → Aggregate (events produce edir) → Event Store. Event Store-a yazıldıqdan sonra Projection handler-lar event-ləri alır → Read model (DB cədvəlləri, Elasticsearch) yenilənir. Query side bu read model-dən oxuyur. Eventual consistency var.

### 5. Event Sourcing hər layihəyə uyğundurmu?
**Cavab:** Xeyr. Üstünlüklər: audit trail, temporal query, debug. Çatışmazlıqlar: yüksək complexity, eventual consistency, schema evolution çətin. Uyğun hallar: bank hesabı, inventory, iş axını sistemi. Sadə CRUD, reporting-only app üçün overkill-dir.

*Son yenilənmə: 2026-04-10*
