# CQRS + Event Sourcing — Bank Account Sistemi (Architect)

**Səviyyə:** ⭐⭐⭐⭐⭐ Architect

---

## Layihə Haqqında

Bu layihə **pure Spring Boot** ilə CQRS (Command Query Responsibility Segregation) və Event Sourcing pattern-lərini tətbiq edir. Axon Framework, EventStoreDB kimi xüsusi alətlər **işlədilmir** — məqsəd pattern-lərin necə işlədiyini göstərməkdir.

**Domain:** Bank Account sistemi — hesab açmaq, pul yatırmaq, pul çıxarmaq, hesabı bağlamaq.

### Bu nədir, nə deyil

| Bu layihə | Bu layihə deyil |
|-----------|-----------------|
| Pure Spring Boot (educational) | Axon/EventStoreDB tutorial |
| Pattern internals — öyrənmək üçün | Production-ready bank sistemi |
| Write/Read side-ın tam ayrılması | Distributed event streaming (Kafka) |
| Event replay mexanizmi | Full audit compliance sistemi |

---

## Nə Öyrənəcəksiniz

- **CQRS:** Command side (write) vs Query side (read) tam ayrılması
- **Event Sourcing:** State-i state kimi yox, event-lər kimi saxlamaq
- **Aggregate pattern:** Domain logic-in encapsulation-u, invariant-ların qorunması
- **Event Projection:** Event-lərdən read model qurulması
- **Event Replay:** Read modeli sıfırdan yenidən qurmaq
- **Optimistic locking:** Concurrent write-lara qarşı qorunma
- **Sealed interfaces + Records:** Java 21 pattern matching ilə domain model

---

## Sistem Arxitekturası

```
┌─────────────────────────────────────────────────────────────┐
│                        REST API                             │
│         Commands                       Queries              │
│   POST /accounts                  GET /accounts/{id}        │
│   POST /accounts/{id}/deposits    GET /accounts/{id}/history│
│   POST /accounts/{id}/withdrawals                           │
│   DELETE /accounts/{id}                                     │
└────────────┬─────────────────────────────┬──────────────────┘
             │                             │
             ▼                             ▼
   ┌──────────────────┐         ┌──────────────────┐
   │  Command Handler  │         │   Query Service   │
   │  (Write Side)     │         │   (Read Side)     │
   └────────┬─────────┘         └────────▲──────────┘
            │                            │
            │ 1. Load events             │ reads
            │ 2. Rebuild aggregate       │
            │ 3. Execute business logic  │
            │ 4. Save new events         │
            ▼                            │
   ┌──────────────────┐         ┌────────┴─────────┐
   │   Event Store    │ publish │  Account Summary  │
   │  (event_store)   │────────►│  (Projection DB)  │
   │  append-only     │ events  │  account_summary  │
   └──────────────────┘         └──────────────────┘
         PostgreSQL                   PostgreSQL
```

**Key insight:** Event store-a yalnız **append** edilir, heç vaxt update/delete edilmir. Read model isə event-lərdən türəyir və istənilən vaxt yenidən qurula bilər.

---

## Texniki Stack

| Komponent | Texnologiya |
|-----------|-------------|
| Framework | Spring Boot 3.3+ |
| Java | 21 (Records, Sealed interfaces, Pattern matching) |
| Database | PostgreSQL 16 |
| ORM | Spring Data JPA + Hibernate |
| Serialization | Jackson (JSONB event data) |
| Infrastructure | Docker Compose |
| Test | JUnit 5, Mockito, @DataJpaTest, @SpringBootTest |

---

## Qovluq Strukturu

```
bank-account/
├── pom.xml
├── docker-compose.yml
├── src/
│   └── main/
│       ├── java/com/example/bankaccount/
│       │   ├── BankAccountApplication.java
│       │   ├── domain/
│       │   │   ├── event/
│       │   │   │   ├── DomainEvent.java          # sealed interface
│       │   │   │   ├── AccountOpened.java
│       │   │   │   ├── MoneyDeposited.java
│       │   │   │   ├── MoneyWithdrawn.java
│       │   │   │   └── AccountClosed.java
│       │   │   ├── aggregate/
│       │   │   │   ├── BankAccount.java           # aggregate root
│       │   │   │   └── AccountStatus.java
│       │   │   ├── command/
│       │   │   │   ├── OpenAccountCommand.java
│       │   │   │   ├── DepositMoneyCommand.java
│       │   │   │   ├── WithdrawMoneyCommand.java
│       │   │   │   └── CloseAccountCommand.java
│       │   │   └── exception/
│       │   │       ├── AccountNotFoundException.java
│       │   │       ├── InsufficientFundsException.java
│       │   │       └── AccountClosedException.java
│       │   ├── infrastructure/
│       │   │   ├── eventstore/
│       │   │   │   ├── EventStoreEntity.java      # JPA entity
│       │   │   │   ├── EventStoreRepository.java  # JPA repository
│       │   │   │   └── EventStoreService.java     # serialize/deserialize
│       │   │   └── projection/
│       │   │       ├── AccountSummaryEntity.java  # JPA entity
│       │   │       ├── AccountSummaryRepository.java
│       │   │       └── AccountProjection.java     # @EventListener
│       │   ├── application/
│       │   │   ├── command/
│       │   │   │   └── BankAccountCommandHandler.java
│       │   │   └── query/
│       │   │       └── AccountQueryService.java
│       │   └── api/
│       │       ├── AccountCommandController.java
│       │       ├── AccountQueryController.java
│       │       ├── dto/
│       │       │   ├── OpenAccountRequest.java
│       │       │   ├── DepositRequest.java
│       │       │   ├── WithdrawRequest.java
│       │       │   └── AccountSummaryResponse.java
│       │       └── AdminController.java
│       └── resources/
│           ├── application.yml
│           └── db/migration/
│               └── V1__create_tables.sql
└── src/test/java/com/example/bankaccount/
    ├── domain/
    │   └── BankAccountAggregateTest.java
    ├── infrastructure/
    │   └── EventStoreServiceTest.java
    └── integration/
        └── BankAccountIntegrationTest.java
```

---

## Domain Model

### Event-lər — sistemdə baş verən hər şey

```
AccountOpened       → yeni hesab açıldı
MoneyDeposited      → pul yatırıldı
MoneyWithdrawn      → pul çıxarıldı
AccountClosed       → hesab bağlandı
```

### Aggregate state (yalnız memory-də, DB-də yoxdur)

```
accountId   : String (UUID)
ownerName   : String
balance     : BigDecimal
status      : OPEN | CLOSED
version     : long (neçə event apply edilib)
```

**Kritik qayda:** `account_summary` tablosunda olan `balance` — state-in özü deyil, event-lərdən türəyən **projeksiyadır**. Event store həmişə source of truth-dur.

---

## Addım-addım İmplementasiya

### Addım 1: Event Store (PostgreSQL)

**`src/main/resources/db/migration/V1__create_tables.sql`**

```sql
CREATE TABLE event_store (
    id              BIGSERIAL PRIMARY KEY,
    aggregate_id    VARCHAR(36)  NOT NULL,
    sequence_number BIGINT       NOT NULL,
    event_type      VARCHAR(100) NOT NULL,
    event_data      JSONB        NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    UNIQUE (aggregate_id, sequence_number)   -- optimistic locking
);

CREATE INDEX idx_event_store_aggregate_id ON event_store (aggregate_id, sequence_number);

CREATE TABLE account_summary (
    account_id  VARCHAR(36)    PRIMARY KEY,
    owner_name  VARCHAR(255)   NOT NULL,
    balance     DECIMAL(15, 2) NOT NULL,
    status      VARCHAR(20)    NOT NULL,
    version     BIGINT         NOT NULL,
    updated_at  TIMESTAMP      NOT NULL DEFAULT NOW()
);
```

`UNIQUE (aggregate_id, sequence_number)` — bu constraint optimistic locking-i təmin edir. İki eyni vaxtda eyni aggregate-ə yazanda biri constraint violation alacaq.

**`EventStoreEntity.java`**

```java
package com.example.bankaccount.infrastructure.eventstore;

import jakarta.persistence.*;
import java.time.Instant;

@Entity
@Table(name = "event_store")
public class EventStoreEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "aggregate_id", nullable = false, length = 36)
    private String aggregateId;

    @Column(name = "sequence_number", nullable = false)
    private Long sequenceNumber;

    @Column(name = "event_type", nullable = false, length = 100)
    private String eventType;

    @Column(name = "event_data", nullable = false, columnDefinition = "jsonb")
    private String eventData;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    protected EventStoreEntity() {}

    public EventStoreEntity(String aggregateId, Long sequenceNumber,
                            String eventType, String eventData) {
        this.aggregateId = aggregateId;
        this.sequenceNumber = sequenceNumber;
        this.eventType = eventType;
        this.eventData = eventData;
    }

    // Getters
    public String getAggregateId()    { return aggregateId; }
    public Long getSequenceNumber()   { return sequenceNumber; }
    public String getEventType()      { return eventType; }
    public String getEventData()      { return eventData; }
    public Instant getCreatedAt()     { return createdAt; }
}
```

**`EventStoreRepository.java`**

```java
package com.example.bankaccount.infrastructure.eventstore;

import org.springframework.data.jpa.repository.JpaRepository;
import java.util.List;

public interface EventStoreRepository extends JpaRepository<EventStoreEntity, Long> {

    List<EventStoreEntity> findByAggregateIdOrderBySequenceNumberAsc(String aggregateId);

    // Event replay üçün — bütün event-lər sıralı
    List<EventStoreEntity> findAllByOrderByIdAsc();

    long countByAggregateId(String aggregateId);
}
```

**`AccountSummaryEntity.java`**

```java
package com.example.bankaccount.infrastructure.projection;

import jakarta.persistence.*;
import java.math.BigDecimal;
import java.time.Instant;

@Entity
@Table(name = "account_summary")
public class AccountSummaryEntity {

    @Id
    @Column(name = "account_id", length = 36)
    private String accountId;

    @Column(name = "owner_name", nullable = false)
    private String ownerName;

    @Column(name = "balance", nullable = false, precision = 15, scale = 2)
    private BigDecimal balance;

    @Column(name = "status", nullable = false, length = 20)
    private String status;

    @Column(name = "version", nullable = false)
    private Long version;

    @Column(name = "updated_at", nullable = false)
    private Instant updatedAt = Instant.now();

    protected AccountSummaryEntity() {}

    public AccountSummaryEntity(String accountId, String ownerName) {
        this.accountId = accountId;
        this.ownerName = ownerName;
        this.balance = BigDecimal.ZERO;
        this.status = "OPEN";
        this.version = 1L;
    }

    // Getters + setters
    public String getAccountId()   { return accountId; }
    public String getOwnerName()   { return ownerName; }
    public BigDecimal getBalance() { return balance; }
    public String getStatus()      { return status; }
    public Long getVersion()       { return version; }

    public void setBalance(BigDecimal balance)   { this.balance = balance; }
    public void setStatus(String status)         { this.status = status; }
    public void setVersion(Long version)         { this.version = version; }
    public void setUpdatedAt(Instant updatedAt)  { this.updatedAt = updatedAt; }
}
```

---

### Addım 2: Domain Events (Records + Sealed Interface)

**`DomainEvent.java`**

```java
package com.example.bankaccount.domain.event;

import java.time.Instant;

public sealed interface DomainEvent
        permits AccountOpened, MoneyDeposited, MoneyWithdrawn, AccountClosed {

    String aggregateId();
    Instant occurredAt();
}
```

`sealed interface` — yalnız `permits` siyahısındakı class-lar bu interface-i implement edə bilər. Compiler bilir ki, başqa implementation yoxdur — `switch` expression-larda exhaustive pattern matching mümkün olur.

**`AccountOpened.java`**

```java
package com.example.bankaccount.domain.event;

import java.time.Instant;

public record AccountOpened(
        String aggregateId,
        String ownerName,
        Instant occurredAt
) implements DomainEvent {}
```

**`MoneyDeposited.java`**

```java
package com.example.bankaccount.domain.event;

import java.math.BigDecimal;
import java.time.Instant;

public record MoneyDeposited(
        String aggregateId,
        BigDecimal amount,
        Instant occurredAt
) implements DomainEvent {}
```

**`MoneyWithdrawn.java`**

```java
package com.example.bankaccount.domain.event;

import java.math.BigDecimal;
import java.time.Instant;

public record MoneyWithdrawn(
        String aggregateId,
        BigDecimal amount,
        Instant occurredAt
) implements DomainEvent {}
```

**`AccountClosed.java`**

```java
package com.example.bankaccount.domain.event;

import java.time.Instant;

public record AccountClosed(
        String aggregateId,
        Instant occurredAt
) implements DomainEvent {}
```

---

### Addım 3: BankAccount Aggregate

Aggregate — domain logic-in yeganə sahibidir. Heç bir business qaydası aggregate-dən kənarda yazılmır.

**`AccountStatus.java`**

```java
package com.example.bankaccount.domain.aggregate;

public enum AccountStatus {
    OPEN, CLOSED
}
```

**`BankAccount.java`**

```java
package com.example.bankaccount.domain.aggregate;

import com.example.bankaccount.domain.event.*;
import com.example.bankaccount.domain.exception.*;

import java.math.BigDecimal;
import java.time.Instant;
import java.util.*;

public class BankAccount {

    private String accountId;
    private String ownerName;
    private BigDecimal balance;
    private AccountStatus status;
    private long version;

    // Yeni commit edilməmiş event-lər — save ediləndən sonra clear olur
    private final List<DomainEvent> uncommittedEvents = new ArrayList<>();

    // JPA-dan fərqli olaraq burada private constructor — aggregate yalnız
    // factory method və ya loadFromHistory ilə yaradılır
    private BankAccount() {}

    // ─── Factory methods ───────────────────────────────────────────────────

    /**
     * Yeni hesab açır. DB-yə yazmır — yalnız event yaradır.
     */
    public static BankAccount open(String ownerName) {
        if (ownerName == null || ownerName.isBlank()) {
            throw new IllegalArgumentException("Owner name cannot be blank");
        }
        BankAccount account = new BankAccount();
        String accountId = UUID.randomUUID().toString();
        account.raiseEvent(new AccountOpened(accountId, ownerName, Instant.now()));
        return account;
    }

    /**
     * Event tarixçəsindən aggregate-i yenidən qurur (replay).
     */
    public static BankAccount loadFromHistory(List<DomainEvent> events) {
        if (events == null || events.isEmpty()) {
            throw new IllegalArgumentException("Cannot load aggregate from empty history");
        }
        BankAccount account = new BankAccount();
        for (DomainEvent event : events) {
            account.applyEvent(event, false); // replay — uncommittedEvents-ə əlavə etmə
        }
        return account;
    }

    // ─── Business methods ──────────────────────────────────────────────────

    public void deposit(BigDecimal amount) {
        requireOpen();
        if (amount == null || amount.compareTo(BigDecimal.ZERO) <= 0) {
            throw new IllegalArgumentException("Deposit amount must be positive");
        }
        raiseEvent(new MoneyDeposited(accountId, amount, Instant.now()));
    }

    public void withdraw(BigDecimal amount) {
        requireOpen();
        if (amount == null || amount.compareTo(BigDecimal.ZERO) <= 0) {
            throw new IllegalArgumentException("Withdrawal amount must be positive");
        }
        if (balance.compareTo(amount) < 0) {
            throw new InsufficientFundsException(accountId, balance, amount);
        }
        raiseEvent(new MoneyWithdrawn(accountId, amount, Instant.now()));
    }

    public void close() {
        requireOpen();
        raiseEvent(new AccountClosed(accountId, Instant.now()));
    }

    // ─── Event application ─────────────────────────────────────────────────

    /**
     * Yeni event yaradır: state-i dəyişdirir + uncommittedEvents-ə əlavə edir.
     */
    private void raiseEvent(DomainEvent event) {
        applyEvent(event, true);
    }

    /**
     * Event-i tətbiq edir. isNew=true olduqda uncommittedEvents-ə əlavə edir.
     * Bu metod həm yeni event-lər, həm də replay zamanı çağırılır.
     */
    private void applyEvent(DomainEvent event, boolean isNew) {
        switch (event) {
            case AccountOpened e -> {
                this.accountId = e.aggregateId();
                this.ownerName = e.ownerName();
                this.balance = BigDecimal.ZERO;
                this.status = AccountStatus.OPEN;
            }
            case MoneyDeposited e ->
                this.balance = this.balance.add(e.amount());

            case MoneyWithdrawn e ->
                this.balance = this.balance.subtract(e.amount());

            case AccountClosed e ->
                this.status = AccountStatus.CLOSED;
        }
        this.version++;
        if (isNew) {
            uncommittedEvents.add(event);
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private void requireOpen() {
        if (status == AccountStatus.CLOSED) {
            throw new AccountClosedException(accountId);
        }
    }

    // ─── Accessors ─────────────────────────────────────────────────────────

    public String getAccountId()   { return accountId; }
    public String getOwnerName()   { return ownerName; }
    public BigDecimal getBalance() { return balance; }
    public AccountStatus getStatus() { return status; }
    public long getVersion()       { return version; }

    public List<DomainEvent> getUncommittedEvents() {
        return Collections.unmodifiableList(uncommittedEvents);
    }

    public void clearUncommittedEvents() {
        uncommittedEvents.clear();
    }
}
```

**Niyə `applyEvent(event, isNew)` pattern?**

Eyni `switch` — həm yeni event-ləri, həm replay-i idarə edir. Replay zamanı `isNew=false` olduğu üçün event-lər ikinci dəfə `uncommittedEvents`-ə əlavə edilmir. Bu "apply method" pattern Event Sourcing-in əsasını təşkil edir.

---

### Addım 4: Command Objects

```java
package com.example.bankaccount.domain.command;

import jakarta.validation.constraints.*;
import java.math.BigDecimal;

public record OpenAccountCommand(
        @NotBlank String ownerName
) {}

public record DepositMoneyCommand(
        @NotBlank String accountId,
        @NotNull @Positive BigDecimal amount
) {}

public record WithdrawMoneyCommand(
        @NotBlank String accountId,
        @NotNull @Positive BigDecimal amount
) {}

public record CloseAccountCommand(
        @NotBlank String accountId
) {}
```

Command-lər sadə data holder-lardır. Business logic yoxdur — yalnız intent (niyyət) bildirir.

---

### Addım 5: EventStore Service (Serialization)

Bu — ən kritik infrastructure hissəsidir. Domain event-lər (Java objects) JSON-a çevrilərək `event_data` sütununa yazılır, oxunanda isə `event_type` sütununa görə düzgün Java class-a deserialize edilir.

**`EventStoreService.java`**

```java
package com.example.bankaccount.infrastructure.eventstore;

import com.example.bankaccount.domain.event.*;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.datatype.jsr310.JavaTimeModule;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.Map;

@Service
public class EventStoreService {

    private final EventStoreRepository repository;
    private final ObjectMapper objectMapper;

    // Event type adı → Java class mapping
    private static final Map<String, Class<? extends DomainEvent>> EVENT_REGISTRY = Map.of(
            "AccountOpened",   AccountOpened.class,
            "MoneyDeposited",  MoneyDeposited.class,
            "MoneyWithdrawn",  MoneyWithdrawn.class,
            "AccountClosed",   AccountClosed.class
    );

    public EventStoreService(EventStoreRepository repository) {
        this.repository = repository;
        this.objectMapper = new ObjectMapper()
                .registerModule(new JavaTimeModule());
    }

    /**
     * Aggregate-in event-lərini yükləyir.
     */
    public List<DomainEvent> loadEvents(String aggregateId) {
        return repository
                .findByAggregateIdOrderBySequenceNumberAsc(aggregateId)
                .stream()
                .map(this::deserialize)
                .toList();
    }

    /**
     * Yeni event-ləri event store-a yazır.
     * expectedVersion — optimistic locking üçün.
     */
    public void saveEvents(List<DomainEvent> events, long expectedVersion) {
        long sequenceNumber = expectedVersion - events.size() + 1;
        for (DomainEvent event : events) {
            EventStoreEntity entity = new EventStoreEntity(
                    event.aggregateId(),
                    sequenceNumber++,
                    event.getClass().getSimpleName(),
                    serialize(event)
            );
            repository.save(entity);
            // UNIQUE constraint (aggregate_id, sequence_number) burada qoruyur.
            // Concurrent write-da DataIntegrityViolationException atılır.
        }
    }

    /**
     * Bütün event-ləri sıralı şəkildə yükləyir — projection rebuild üçün.
     */
    public List<DomainEvent> loadAllEvents() {
        return repository.findAllByOrderByIdAsc()
                .stream()
                .map(this::deserialize)
                .toList();
    }

    // ─── Private helpers ───────────────────────────────────────────────────

    private String serialize(DomainEvent event) {
        try {
            return objectMapper.writeValueAsString(event);
        } catch (Exception e) {
            throw new RuntimeException("Failed to serialize event: " + event, e);
        }
    }

    private DomainEvent deserialize(EventStoreEntity entity) {
        Class<? extends DomainEvent> eventClass = EVENT_REGISTRY.get(entity.getEventType());
        if (eventClass == null) {
            throw new IllegalStateException("Unknown event type: " + entity.getEventType());
        }
        try {
            return objectMapper.readValue(entity.getEventData(), eventClass);
        } catch (Exception e) {
            throw new RuntimeException("Failed to deserialize event: " + entity.getEventType(), e);
        }
    }
}
```

**Event versioning qeydi:** Yeni field əlavə etsəniz (məs, `MoneyDeposited`-ə `currency` field), köhnə event-lər null alacaq. Upcaster yazılır: deserialize zamanı köhnə formatı yeniyə çevirən mapper. Bu production-da mühüm məsələdir.

---

### Addım 6: Command Handler (Write Side)

```java
package com.example.bankaccount.application.command;

import com.example.bankaccount.domain.aggregate.BankAccount;
import com.example.bankaccount.domain.command.*;
import com.example.bankaccount.domain.event.DomainEvent;
import com.example.bankaccount.domain.exception.AccountNotFoundException;
import com.example.bankaccount.infrastructure.eventstore.EventStoreService;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
@Transactional
public class BankAccountCommandHandler {

    private final EventStoreService eventStoreService;
    private final ApplicationEventPublisher eventPublisher; // Spring internal events

    public BankAccountCommandHandler(EventStoreService eventStoreService,
                                     ApplicationEventPublisher eventPublisher) {
        this.eventStoreService = eventStoreService;
        this.eventPublisher = eventPublisher;
    }

    public String handle(OpenAccountCommand cmd) {
        BankAccount account = BankAccount.open(cmd.ownerName());
        saveAndPublish(account);
        return account.getAccountId();
    }

    public void handle(DepositMoneyCommand cmd) {
        BankAccount account = loadAggregate(cmd.accountId());
        account.deposit(cmd.amount());
        saveAndPublish(account);
    }

    public void handle(WithdrawMoneyCommand cmd) {
        BankAccount account = loadAggregate(cmd.accountId());
        account.withdraw(cmd.amount());
        saveAndPublish(account);
    }

    public void handle(CloseAccountCommand cmd) {
        BankAccount account = loadAggregate(cmd.accountId());
        account.close();
        saveAndPublish(account);
    }

    // ─── Private helpers ───────────────────────────────────────────────────

    private BankAccount loadAggregate(String accountId) {
        List<DomainEvent> events = eventStoreService.loadEvents(accountId);
        if (events.isEmpty()) {
            throw new AccountNotFoundException(accountId);
        }
        return BankAccount.loadFromHistory(events);
    }

    private void saveAndPublish(BankAccount account) {
        List<DomainEvent> newEvents = account.getUncommittedEvents();

        // 1. Event-ləri persist et (UNIQUE constraint burada qoruyur)
        eventStoreService.saveEvents(newEvents, account.getVersion());

        // 2. Spring ApplicationEvents vasitəsilə projection-ları xəbərdar et
        //    Bu synchronous-dur — eyni transaction daxilindədir.
        //    Production-da Kafka/RabbitMQ olsaydı, burada publish edilərdi.
        newEvents.forEach(eventPublisher::publishEvent);

        // 3. Uncommitted event-ləri təmizlə
        account.clearUncommittedEvents();
    }
}
```

**Niyə `ApplicationEventPublisher`?**

Bu layihədə Spring-in daxili event mexanizmindən istifadə olunur — eyni JVM prosesi daxilindədir. Projection update **synchronous**-dur: command transaction bitənə qədər projection da yenilənir. Bu sadədir, amma real distributed sistemdə (mikroservislər) Kafka/RabbitMQ kimi message broker lazım olur.

---

### Addım 7: Event Projection (Read Model)

```java
package com.example.bankaccount.infrastructure.projection;

import com.example.bankaccount.domain.event.*;
import org.springframework.context.event.EventListener;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

import java.time.Instant;

@Component
@Transactional
public class AccountProjection {

    private final AccountSummaryRepository repository;

    public AccountProjection(AccountSummaryRepository repository) {
        this.repository = repository;
    }

    @EventListener
    public void on(AccountOpened event) {
        AccountSummaryEntity summary = new AccountSummaryEntity(
                event.aggregateId(),
                event.ownerName()
        );
        repository.save(summary);
    }

    @EventListener
    public void on(MoneyDeposited event) {
        AccountSummaryEntity summary = findOrThrow(event.aggregateId());
        summary.setBalance(summary.getBalance().add(event.amount()));
        summary.setVersion(summary.getVersion() + 1);
        summary.setUpdatedAt(Instant.now());
        repository.save(summary);
    }

    @EventListener
    public void on(MoneyWithdrawn event) {
        AccountSummaryEntity summary = findOrThrow(event.aggregateId());
        summary.setBalance(summary.getBalance().subtract(event.amount()));
        summary.setVersion(summary.getVersion() + 1);
        summary.setUpdatedAt(Instant.now());
        repository.save(summary);
    }

    @EventListener
    public void on(AccountClosed event) {
        AccountSummaryEntity summary = findOrThrow(event.aggregateId());
        summary.setStatus("CLOSED");
        summary.setVersion(summary.getVersion() + 1);
        summary.setUpdatedAt(Instant.now());
        repository.save(summary);
    }

    private AccountSummaryEntity findOrThrow(String accountId) {
        return repository.findById(accountId)
                .orElseThrow(() -> new IllegalStateException(
                        "Projection not found for account: " + accountId));
    }
}
```

`AccountSummaryRepository.java`:

```java
package com.example.bankaccount.infrastructure.projection;

import org.springframework.data.jpa.repository.JpaRepository;

public interface AccountSummaryRepository extends JpaRepository<AccountSummaryEntity, String> {}
```

---

### Addım 8: Query Service

```java
package com.example.bankaccount.application.query;

import com.example.bankaccount.domain.event.DomainEvent;
import com.example.bankaccount.domain.exception.AccountNotFoundException;
import com.example.bankaccount.infrastructure.eventstore.EventStoreService;
import com.example.bankaccount.infrastructure.projection.AccountSummaryEntity;
import com.example.bankaccount.infrastructure.projection.AccountSummaryRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
@Transactional(readOnly = true)
public class AccountQueryService {

    private final AccountSummaryRepository summaryRepository;
    private final EventStoreService eventStoreService;

    public AccountQueryService(AccountSummaryRepository summaryRepository,
                               EventStoreService eventStoreService) {
        this.summaryRepository = summaryRepository;
        this.eventStoreService = eventStoreService;
    }

    /**
     * Cari balansı projection-dan oxuyur — sürətli.
     */
    public AccountSummaryEntity getAccountSummary(String accountId) {
        return summaryRepository.findById(accountId)
                .orElseThrow(() -> new AccountNotFoundException(accountId));
    }

    /**
     * Tam tarixçəni event store-dan oxuyur.
     */
    public List<DomainEvent> getTransactionHistory(String accountId) {
        List<DomainEvent> events = eventStoreService.loadEvents(accountId);
        if (events.isEmpty()) {
            throw new AccountNotFoundException(accountId);
        }
        return events;
    }
}
```

---

### Addım 8: REST Controllers

**`AccountCommandController.java`**

```java
package com.example.bankaccount.api;

import com.example.bankaccount.api.dto.*;
import com.example.bankaccount.application.command.BankAccountCommandHandler;
import com.example.bankaccount.domain.command.*;
import jakarta.validation.Valid;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.net.URI;

@RestController
@RequestMapping("/api/accounts")
public class AccountCommandController {

    private final BankAccountCommandHandler commandHandler;

    public AccountCommandController(BankAccountCommandHandler commandHandler) {
        this.commandHandler = commandHandler;
    }

    @PostMapping
    public ResponseEntity<Void> openAccount(@Valid @RequestBody OpenAccountRequest req) {
        String accountId = commandHandler.handle(new OpenAccountCommand(req.ownerName()));
        return ResponseEntity.created(URI.create("/api/accounts/" + accountId)).build();
    }

    @PostMapping("/{id}/deposits")
    public ResponseEntity<Void> deposit(@PathVariable String id,
                                        @Valid @RequestBody DepositRequest req) {
        commandHandler.handle(new DepositMoneyCommand(id, req.amount()));
        return ResponseEntity.ok().build();
    }

    @PostMapping("/{id}/withdrawals")
    public ResponseEntity<Void> withdraw(@PathVariable String id,
                                         @Valid @RequestBody WithdrawRequest req) {
        commandHandler.handle(new WithdrawMoneyCommand(id, req.amount()));
        return ResponseEntity.ok().build();
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> closeAccount(@PathVariable String id) {
        commandHandler.handle(new CloseAccountCommand(id));
        return ResponseEntity.noContent().build();
    }
}
```

**`AccountQueryController.java`**

```java
package com.example.bankaccount.api;

import com.example.bankaccount.api.dto.AccountSummaryResponse;
import com.example.bankaccount.application.query.AccountQueryService;
import com.example.bankaccount.domain.event.DomainEvent;
import com.example.bankaccount.infrastructure.projection.AccountSummaryEntity;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/accounts")
public class AccountQueryController {

    private final AccountQueryService queryService;

    public AccountQueryController(AccountQueryService queryService) {
        this.queryService = queryService;
    }

    @GetMapping("/{id}")
    public ResponseEntity<AccountSummaryResponse> getAccount(@PathVariable String id) {
        AccountSummaryEntity entity = queryService.getAccountSummary(id);
        return ResponseEntity.ok(AccountSummaryResponse.from(entity));
    }

    @GetMapping("/{id}/history")
    public ResponseEntity<List<DomainEvent>> getHistory(@PathVariable String id) {
        return ResponseEntity.ok(queryService.getTransactionHistory(id));
    }
}
```

**DTO-lar:**

```java
// OpenAccountRequest.java
public record OpenAccountRequest(@NotBlank String ownerName) {}

// DepositRequest.java
public record DepositRequest(@NotNull @Positive BigDecimal amount) {}

// WithdrawRequest.java
public record WithdrawRequest(@NotNull @Positive BigDecimal amount) {}

// AccountSummaryResponse.java
public record AccountSummaryResponse(
        String accountId,
        String ownerName,
        BigDecimal balance,
        String status,
        Long version
) {
    public static AccountSummaryResponse from(AccountSummaryEntity entity) {
        return new AccountSummaryResponse(
                entity.getAccountId(),
                entity.getOwnerName(),
                entity.getBalance(),
                entity.getStatus(),
                entity.getVersion()
        );
    }
}
```

**Exception handler:**

```java
package com.example.bankaccount.api;

import com.example.bankaccount.domain.exception.*;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;

@RestControllerAdvice
public class GlobalExceptionHandler {

    @ExceptionHandler(AccountNotFoundException.class)
    public ResponseEntity<ProblemDetail> handleNotFound(AccountNotFoundException ex) {
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(HttpStatus.NOT_FOUND, ex.getMessage());
        return ResponseEntity.status(HttpStatus.NOT_FOUND).body(pd);
    }

    @ExceptionHandler(InsufficientFundsException.class)
    public ResponseEntity<ProblemDetail> handleInsufficientFunds(InsufficientFundsException ex) {
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(HttpStatus.UNPROCESSABLE_ENTITY, ex.getMessage());
        return ResponseEntity.status(HttpStatus.UNPROCESSABLE_ENTITY).body(pd);
    }

    @ExceptionHandler(AccountClosedException.class)
    public ResponseEntity<ProblemDetail> handleClosed(AccountClosedException ex) {
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(HttpStatus.CONFLICT, ex.getMessage());
        return ResponseEntity.status(HttpStatus.CONFLICT).body(pd);
    }
}
```

**`AdminController.java`**

```java
package com.example.bankaccount.api;

import com.example.bankaccount.application.command.ProjectionRebuildService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/api/admin")
public class AdminController {

    private final ProjectionRebuildService rebuildService;

    public AdminController(ProjectionRebuildService rebuildService) {
        this.rebuildService = rebuildService;
    }

    @PostMapping("/rebuild-projections")
    public ResponseEntity<String> rebuildProjections() {
        rebuildService.rebuildAll();
        return ResponseEntity.ok("Projection rebuild completed");
    }
}
```

---

### Addım 9: Event Replay (Projection Rebuild)

Bu — Event Sourcing-in ən güclü xüsusiyyətidir. Read model pozuldu, silinldi, yaxud yeni projection field əlavə etdiniz? Bütün event-ləri yenidən işlədib read modeli sıfırdan qurursunuz.

```java
package com.example.bankaccount.application.command;

import com.example.bankaccount.domain.event.DomainEvent;
import com.example.bankaccount.infrastructure.eventstore.EventStoreService;
import com.example.bankaccount.infrastructure.projection.AccountSummaryRepository;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
public class ProjectionRebuildService {

    private final EventStoreService eventStoreService;
    private final AccountSummaryRepository summaryRepository;
    private final ApplicationEventPublisher eventPublisher;

    public ProjectionRebuildService(EventStoreService eventStoreService,
                                    AccountSummaryRepository summaryRepository,
                                    ApplicationEventPublisher eventPublisher) {
        this.eventStoreService = eventStoreService;
        this.summaryRepository = summaryRepository;
        this.eventPublisher = eventPublisher;
    }

    @Transactional
    public void rebuildAll() {
        // 1. Mövcud projection-u təmizlə
        summaryRepository.deleteAll();

        // 2. Bütün event-ləri tarix sırası ilə yüklə
        List<DomainEvent> allEvents = eventStoreService.loadAllEvents();

        // 3. Hər event-i projection-a yenidən göndər
        //    AccountProjection @EventListener-ları işləyəcək
        allEvents.forEach(eventPublisher::publishEvent);
    }
}
```

**Diqqət:** Böyük sistemlərdə `deleteAll()` + full replay çox vaxt apara bilər. Production-da:
- Yeni projection table yarat (v2)
- Background-da rebuild et
- Traffic-i yeniyə yönləndir
- Köhnəni sil

---

## Tam Kod Nümunələri

### `pom.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0 https://maven.apache.org/xsd/maven-4.0.0.xsd">
    <modelVersion>4.0.0</modelVersion>

    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.3.5</version>
    </parent>

    <groupId>com.example</groupId>
    <artifactId>bank-account</artifactId>
    <version>0.0.1-SNAPSHOT</version>

    <properties>
        <java.version>21</java.version>
    </properties>

    <dependencies>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-validation</artifactId>
        </dependency>
        <dependency>
            <groupId>org.postgresql</groupId>
            <artifactId>postgresql</artifactId>
            <scope>runtime</scope>
        </dependency>
        <dependency>
            <groupId>com.fasterxml.jackson.datatype</groupId>
            <artifactId>jackson-datatype-jsr310</artifactId>
        </dependency>
        <dependency>
            <groupId>org.flywaydb</groupId>
            <artifactId>flyway-core</artifactId>
        </dependency>
        <dependency>
            <groupId>org.flywaydb</groupId>
            <artifactId>flyway-database-postgresql</artifactId>
        </dependency>

        <!-- Test -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>
        <dependency>
            <groupId>org.testcontainers</groupId>
            <artifactId>postgresql</artifactId>
            <scope>test</scope>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-testcontainers</artifactId>
            <scope>test</scope>
        </dependency>
    </dependencies>

    <build>
        <plugins>
            <plugin>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-maven-plugin</artifactId>
            </plugin>
        </plugins>
    </build>
</project>
```

### `docker-compose.yml`

```yaml
services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: bankaccount
      POSTGRES_USER: bank
      POSTGRES_PASSWORD: bank
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
```

### `application.yml`

```yaml
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/bankaccount
    username: bank
    password: bank
    driver-class-name: org.postgresql.Driver
  jpa:
    hibernate:
      ddl-auto: validate
    show-sql: false
    properties:
      hibernate:
        dialect: org.hibernate.dialect.PostgreSQLDialect
        format_sql: true
  flyway:
    enabled: true
    locations: classpath:db/migration

server:
  port: 8080
```

### Domain exceptions

```java
// AccountNotFoundException.java
public class AccountNotFoundException extends RuntimeException {
    public AccountNotFoundException(String accountId) {
        super("Account not found: " + accountId);
    }
}

// InsufficientFundsException.java
public class InsufficientFundsException extends RuntimeException {
    public InsufficientFundsException(String accountId, BigDecimal balance, BigDecimal amount) {
        super(String.format("Insufficient funds for account %s: balance=%.2f, requested=%.2f",
                accountId, balance, amount));
    }
}

// AccountClosedException.java
public class AccountClosedException extends RuntimeException {
    public AccountClosedException(String accountId) {
        super("Account is already closed: " + accountId);
    }
}
```

---

## Test Strategiyası

### Unit Tests — BankAccount Aggregate

```java
package com.example.bankaccount.domain;

import com.example.bankaccount.domain.aggregate.*;
import com.example.bankaccount.domain.event.*;
import com.example.bankaccount.domain.exception.*;
import org.junit.jupiter.api.*;

import java.math.BigDecimal;

import static org.assertj.core.api.Assertions.*;

class BankAccountAggregateTest {

    @Test
    void shouldOpenAccount() {
        BankAccount account = BankAccount.open("Elşən Məmmədov");

        assertThat(account.getOwnerName()).isEqualTo("Elşən Məmmədov");
        assertThat(account.getBalance()).isEqualByComparingTo(BigDecimal.ZERO);
        assertThat(account.getStatus()).isEqualTo(AccountStatus.OPEN);
        assertThat(account.getVersion()).isEqualTo(1);
        assertThat(account.getUncommittedEvents()).hasSize(1);
        assertThat(account.getUncommittedEvents().getFirst())
                .isInstanceOf(AccountOpened.class);
    }

    @Test
    void shouldDepositMoney() {
        BankAccount account = BankAccount.open("Test User");
        account.clearUncommittedEvents();

        account.deposit(new BigDecimal("500.00"));

        assertThat(account.getBalance()).isEqualByComparingTo("500.00");
        assertThat(account.getUncommittedEvents()).hasSize(1);
        assertThat(account.getUncommittedEvents().getFirst())
                .isInstanceOf(MoneyDeposited.class);
    }

    @Test
    void shouldWithdrawMoney() {
        BankAccount account = BankAccount.open("Test User");
        account.deposit(new BigDecimal("1000.00"));
        account.clearUncommittedEvents();

        account.withdraw(new BigDecimal("300.00"));

        assertThat(account.getBalance()).isEqualByComparingTo("700.00");
    }

    @Test
    void shouldThrowWhenInsufficientFunds() {
        BankAccount account = BankAccount.open("Test User");
        account.deposit(new BigDecimal("100.00"));

        assertThatThrownBy(() -> account.withdraw(new BigDecimal("200.00")))
                .isInstanceOf(InsufficientFundsException.class);
    }

    @Test
    void shouldThrowWhenDepositingToClosedAccount() {
        BankAccount account = BankAccount.open("Test User");
        account.close();

        assertThatThrownBy(() -> account.deposit(new BigDecimal("100.00")))
                .isInstanceOf(AccountClosedException.class);
    }

    @Test
    void shouldRebuildFromHistory() {
        // Arrange: simulate saved events
        String accountId = "test-uuid";
        var events = java.util.List.of(
                new AccountOpened(accountId, "Leyla Əliyeva", java.time.Instant.now()),
                new MoneyDeposited(accountId, new BigDecimal("1000.00"), java.time.Instant.now()),
                new MoneyWithdrawn(accountId, new BigDecimal("250.00"), java.time.Instant.now())
        );

        // Act: rebuild from history
        BankAccount account = BankAccount.loadFromHistory(events);

        // Assert
        assertThat(account.getBalance()).isEqualByComparingTo("750.00");
        assertThat(account.getVersion()).isEqualTo(3);
        assertThat(account.getUncommittedEvents()).isEmpty(); // replay deyil, yeni event yoxdur
    }
}
```

### Integration Test — Full Flow

```java
package com.example.bankaccount.integration;

import com.example.bankaccount.api.dto.*;
import com.example.bankaccount.infrastructure.projection.AccountSummaryRepository;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.testcontainers.service.connection.ServiceConnection;
import org.springframework.http.MediaType;
import org.springframework.test.web.servlet.MockMvc;
import org.testcontainers.containers.PostgreSQLContainer;
import org.testcontainers.junit.jupiter.Container;
import org.testcontainers.junit.jupiter.Testcontainers;
import com.fasterxml.jackson.databind.ObjectMapper;

import java.math.BigDecimal;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest
@AutoConfigureMockMvc
@Testcontainers
class BankAccountIntegrationTest {

    @Container
    @ServiceConnection
    static PostgreSQLContainer<?> postgres = new PostgreSQLContainer<>("postgres:16-alpine");

    @Autowired MockMvc mockMvc;
    @Autowired ObjectMapper objectMapper;
    @Autowired AccountSummaryRepository summaryRepository;

    @Test
    void fullBankAccountFlow() throws Exception {
        // 1. Hesab aç
        String response = mockMvc.perform(post("/api/accounts")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                            {"ownerName": "Rəşad Hüseynov"}
                        """))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getHeader("Location");

        String accountId = response.substring(response.lastIndexOf('/') + 1);

        // 2. Pul yatır
        mockMvc.perform(post("/api/accounts/{id}/deposits", accountId)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                            {"amount": 1000.00}
                        """))
                .andExpect(status().isOk());

        // 3. Pul çıxar
        mockMvc.perform(post("/api/accounts/{id}/withdrawals", accountId)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                            {"amount": 350.00}
                        """))
                .andExpect(status().isOk());

        // 4. Balansı yoxla (projection-dan)
        mockMvc.perform(get("/api/accounts/{id}", accountId))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.balance").value(650.00))
                .andExpect(jsonPath("$.status").value("OPEN"));

        // 5. Tarixçəni yoxla (event store-dan)
        mockMvc.perform(get("/api/accounts/{id}/history", accountId))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.length()").value(3));

        // 6. Projection rebuild — nəticə dəyişməməlidir
        summaryRepository.deleteAll();
        mockMvc.perform(post("/api/admin/rebuild-projections"))
                .andExpect(status().isOk());

        mockMvc.perform(get("/api/accounts/{id}", accountId))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.balance").value(650.00));
    }
}
```

---

## Production Considerations

### 1. Optimistic Locking — Concurrent Writes

```
Thread A: loads account (version=5) → deposit → save sequence_number=6
Thread B: loads account (version=5) → deposit → save sequence_number=6  ← UNIQUE violation!
```

`UNIQUE (aggregate_id, sequence_number)` constraint Thread B-nin yazmasını bloklayır. `DataIntegrityViolationException` atılır, retry mexanizmi tətbiq olunmalıdır.

```java
// Retry wrapper (Spring Retry ilə)
@Retryable(retryFor = DataIntegrityViolationException.class, maxAttempts = 3)
public void handle(DepositMoneyCommand cmd) { ... }
```

### 2. Snapshot Pattern

10.000 event olan bir hesabı yükləmək hər dəfə 10.000 event deserialize etmək deməkdir. Snapshot pattern:

```sql
CREATE TABLE account_snapshot (
    account_id      VARCHAR(36) PRIMARY KEY,
    version         BIGINT NOT NULL,
    state_data      JSONB NOT NULL,   -- full aggregate state
    created_at      TIMESTAMP DEFAULT NOW()
);
```

```java
// Aggregate-i yükləyərkən:
// 1. Ən son snapshot-u tap (məs: version=9900)
// 2. Yalnız snapshot-dan sonrakı event-ləri yüklə (9901-10000)
// 3. Snapshot state + yeni event-ləri apply et
public BankAccount loadAggregate(String accountId) {
    Optional<Snapshot> snapshot = snapshotRepository.findLatest(accountId);
    long fromVersion = snapshot.map(Snapshot::version).orElse(0L);
    List<DomainEvent> events = eventStore.loadEventsAfterVersion(accountId, fromVersion);
    // ...
}
```

Her N event-dən bir snapshot yaz (məs: N=100 və ya N=500).

### 3. Event Versioning — Upcasting

`MoneyDeposited`-ə yeni `currency` field əlavə etdiniz:

```java
// Köhnə format (DB-də var)
{"aggregateId":"...", "amount":100.00, "occurredAt":"..."}

// Yeni format (kod gözləyir)
{"aggregateId":"...", "amount":100.00, "currency":"AZN", "occurredAt":"..."}
```

Upcaster — köhnə formatı deserialize edib yeniyə çevirən wrapper:

```java
private DomainEvent deserialize(EventStoreEntity entity) {
    if ("MoneyDeposited".equals(entity.getEventType()) && entity.getVersion() < 2) {
        // v1 → v2 upcasting
        MoneyDepositedV1 v1 = objectMapper.readValue(entity.getEventData(), MoneyDepositedV1.class);
        return new MoneyDeposited(v1.aggregateId(), v1.amount(), "AZN", v1.occurredAt());
    }
    // ...
}
```

### 4. Idempotency Keys

Şəbəkə problemi zamanı client eyni command-i iki dəfə göndərə bilər. Idempotency key:

```sql
ALTER TABLE event_store ADD COLUMN idempotency_key VARCHAR(64) UNIQUE;
```

```java
// Client header göndərir: Idempotency-Key: uuid
// Server: əgər bu key ilə artıq event var, uğurlu cavab qaytar (yenidən icra etmə)
String key = request.getHeader("Idempotency-Key");
if (eventStore.existsByIdempotencyKey(key)) {
    return existingAccountId; // cached response
}
```

### 5. Eventual Consistency — Trade-off

Bu layihədə command və query side eyni transaction-dadır — consistency anlıqdır. Distributed sistemdə (Kafka-lı) bu belə deyil:

```
POST /accounts/{id}/deposits → 202 Accepted
GET  /accounts/{id}          → balance hələ köhnə ola bilər!
```

Client-lər bu ilə işləməyi bacarmalıdır:
- Polling: "Nə vaxt yenilənəcək?"
- Optimistic UI: Client-də dərhal state-i yenilə
- Version tracking: Query response-da version qaytar, client versiyonu izləsin

---

## Laravel/PHP ilə Müqayisə

### Event Sourcing PHP-də (Spatie)

```php
// Laravel + spatie/laravel-event-sourcing
class BankAccount extends AggregateRoot
{
    private float $balance = 0;

    public function open(string $ownerName): self
    {
        $this->recordThat(new AccountOpened($ownerName));
        return $this;
    }

    public function deposit(float $amount): self
    {
        $this->recordThat(new MoneyDeposited($amount));
        return $this;
    }

    protected function applyAccountOpened(AccountOpened $event): void
    {
        $this->balance = 0;
    }

    protected function applyMoneyDeposited(MoneyDeposited $event): void
    {
        $this->balance += $event->amount;
    }
}

// İstifadə
BankAccount::retrieve($accountId)->deposit(500)->persist();
```

### Əsas Fərqlər

| Aspekt | Java (bu layihə) | PHP/Spatie |
|--------|-----------------|-----------|
| Event store | Manual PostgreSQL | Spatie avtomatik idarə edir |
| Serialization | Manual Jackson | Spatie avtomatik |
| Projection | `@EventListener` | `Projector` class |
| Event replay | Manual | `php artisan event-sourcing:replay` |
| Type safety | Sealed interface + pattern matching | PHP interface-lər (daha az strict) |
| Concurrency | DB UNIQUE constraint | Spatie snapshot + locking |

### Nə vaxt Java, nə vaxt PHP?

**Java seçin:**
- Yüksək concurrent write yükü (bank, trading)
- Type safety kritikdir
- Virtual threads ilə minlərlə aggregate paralel işləyəcək
- Long-running aggregate-lər (onillər)

**PHP/Spatie seçin:**
- Laravel ekosistemindən çıxmaq istəmirsiniz
- Team PHP-bilir, Java bilmir
- Moderate load (99% Laravel proyektlər üçün kifayət edir)
- Daha sürətli development

---

## Əlaqəli Mövzular

**Bu layihədə istifadə olunan konseptlər:**

- `java/comparison/` — `05-records.md`, `06-sealed-classes.md`, `07-pattern-matching.md`
- `java/spring/` — `06-transactions.md`, `10-spring-events.md`
- `java/topics/` — JPA entity lifecycle, Jackson serialization
- `system-design/` — `08-cqrs.md`, `09-event-sourcing.md`, `10-ddd.md`

**Növbəti addımlar:**

- **09-microservices-demo** — bu sistemin Kafka ilə distributed versiyası
- **08-ecommerce-ddd** — DDD aggregate pattern-inin daha klassik tətbiqi
- Axon Framework ilə bu layihəni yenidən qurmaq (framework necə köməkçi olur görmək üçün)
