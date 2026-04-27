# Event Sourcing (Architect)

## İcmal

Event Sourcing — cari state-i saxlamaq əvəzinə, o state-i yaradan **event ardıcıllığını** saxlamaq yanaşmasıdır. Bankda balans `1000` saxlamaq əvəzinə: `AccountOpened(500)` + `Deposited(700)` + `Withdrawn(200)` = `1000`. Hər event immutable-dir, append-only EventStore-a yazılır. Cari state-i almaq üçün bütün event-lər replay edilir. Go-da bu pattern struct composition, interface və functional `Apply()` metodu ilə elegantlıqla ifadə olunur.

## Niyə Vacibdir

Fintech, healthcare, audit-tələb edən sistemlər üçün tam tarixçə vacibdir. "Nə vaxt, nə dəyişdi, kim dəyişdi" sualını ənənəvi DB-da cavablandırmaq çətin olur (audit log əlavə etmək lazımdır, tez-tez unudulur). Event Sourcing-də bu avtomatikdir: hər dəyişiklik event kimi saxlanır. Bundan başqa event replay ilə keçmişdəki istənilən state-i yenidən qurmaq, yeni projection yaratmaq, bug-ı trace etmək mümkündür.

## Əsas Anlayışlar

- **Event** — baş vermiş bir fakt; immutable; keçmiş zamanda adlandırılır: `AccountOpened`, `MoneyDeposited`
- **EventStore** — append-only event log; events heç vaxt silinmir, yalnız əlavə edilir
- **Aggregate** — event-ləri emit edən domain object; `LoadFromHistory()` ilə yenidən qurulur
- **Apply()** — bir event-i aggregate state-ə tətbiq edən metod; side effect yoxdur, deterministik
- **Projection** — event-lərdən read model qurmaq; CQRS query side-ı üçün
- **Snapshot** — N event-dən sonra cari state-i saxlamaq; replay performansını artırır
- **Event Version** — aggregate dəyişiklikləri üçün optimistic locking; version konflikti → retry
- **Event Schema Evolution** — köhnə event-ləri yeni versiyada oxumaq; upcasting pattern
- **Aggregate Version** — neçənci event olduğunu bildirir; concurrent write-ları aşkar etmək üçün

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Bank hesabı: hər transaction event-dir; mühasibat sistemi event-lərdən hesabat çıxarır
- E-commerce order: state machine event-lər vasitəsilə irəliləyir; hər keçid event-dir
- Healthcare: patient record dəyişiklikləri tam audit trail tələb edir

**Trade-off-lar:**
- Pro: tam audit log, time travel (keçmiş state-ə qayıtmaq), event replay, decoupled consumers
- Pro: bug trace — "nə baş verdi" sualına dəqiq cavab
- Con: eventual consistency — read model sinxronlaşma gecikmə
- Con: mürəkkəb query — `SELECT * FROM orders WHERE status='placed'` birbaşa işləmir; projection lazımdır
- Con: event schema evolution — köhnə event-ləri yeni kod versiyasında oxumaq diqqət tələb edir
- Con: yüksək event sayında replay yavaşdır — snapshot ilə həll olunur

**Nə vaxt istifadə etməmək lazımdır:**
- Reporting ağır sistemlər — event sourcing write üçündür, query üçün CQRS projection lazımdır
- Sadə CRUD — event sourcing overkill; bir user yaratmaq üçün EventStore qurmaq mənasızdır
- Komanda pattern-ə alışmayıbsa — EventStore, snapshot, projection — öyrənmə əyrisi yüksəkdir
- Güclü consistency tələbi olan sistemlər — eventual consistency qəbul edilmirirsə

**Ümumi səhvlər:**
- `Apply()` metodunda side effect: Apply deterministik olmalıdır — yalnız state dəyişikliyi
- Event-ləri mutable etmək: event yazıldıqdan sonra dəyişilməməlidir
- Event-ləri silmək: EventStore append-only-dir; köhnə event-i silmək bütün replay-i pozur
- Snapshot-sız çox event: 100,000 event-i hər dəfə replay etmək performans problemidir
- Domain entity-ləri event olmadan birbaşa DB-ya yazmaq: event sourcing aggregate state-i yalnız event-lər vasitəsilə dəyişilməlidir

## Nümunələr

### Ümumi Nümunə

```
Write Flow:
  BankAccount.Deposit(500)
       │ domain business rule yoxlayır
       │ yeni event yaradır
       ▼
  MoneyDeposited{Amount: 500, OccurredAt: ...}
       │ aggregate uncommitted events-ə əlavə edir
       ▼
  EventStore.Append(accountID, events, expectedVersion)
       │ optimistic locking: version uyğun gəlmirsə → error
       ▼
  events table: [AccountOpened, Deposited, Deposited, Withdrawn, ...]

Read Flow (State Reconstruction):
  EventStore.Load(accountID) → []Event
       │
       ▼
  BankAccount.LoadFromHistory(events)
       │ hər event üçün Apply() çağırılır
       ▼
  BankAccount{Balance: 1000, Status: Active}

Snapshot Flow (Performance):
  Son snapshot yükle + sonrakı event-ləri replay et
  1000 event → snapshot(800. event) + 200 event replay
```

### Kod Nümunəsi

**Domain Events — immutable struct-lar:**

```go
// internal/domain/bank_events.go
package domain

import "time"

// EventType — event adları
type EventType string

const (
    EventAccountOpened  EventType = "account.opened"
    EventMoneyDeposited EventType = "money.deposited"
    EventMoneyWithdrawn EventType = "money.withdrawn"
    EventAccountClosed  EventType = "account.closed"
)

// Event base interface
type Event interface {
    EventType() EventType
    OccurredAt() time.Time
    AggregateID() string
}

// AccountOpened event
type AccountOpened struct {
    ID            string
    OwnerID       string
    InitialAmount int64
    Currency      string
    occurredAt    time.Time
}

func (e AccountOpened) EventType() EventType { return EventAccountOpened }
func (e AccountOpened) OccurredAt() time.Time { return e.occurredAt }
func (e AccountOpened) AggregateID() string   { return e.ID }

// MoneyDeposited event
type MoneyDeposited struct {
    AccountID  string
    Amount     int64
    occurredAt time.Time
}

func (e MoneyDeposited) EventType() EventType  { return EventMoneyDeposited }
func (e MoneyDeposited) OccurredAt() time.Time { return e.occurredAt }
func (e MoneyDeposited) AggregateID() string   { return e.AccountID }

// MoneyWithdrawn event
type MoneyWithdrawn struct {
    AccountID  string
    Amount     int64
    occurredAt time.Time
}

func (e MoneyWithdrawn) EventType() EventType  { return EventMoneyWithdrawn }
func (e MoneyWithdrawn) OccurredAt() time.Time { return e.occurredAt }
func (e MoneyWithdrawn) AggregateID() string   { return e.AccountID }

// AccountClosed event
type AccountClosed struct {
    AccountID  string
    occurredAt time.Time
}

func (e AccountClosed) EventType() EventType  { return EventAccountClosed }
func (e AccountClosed) OccurredAt() time.Time { return e.occurredAt }
func (e AccountClosed) AggregateID() string   { return e.AccountID }
```

**BankAccount Aggregate:**

```go
// internal/domain/bank_account.go
package domain

import (
    "errors"
    "fmt"
    "time"
)

type AccountStatus string

const (
    AccountStatusActive AccountStatus = "active"
    AccountStatusClosed AccountStatus = "closed"
)

// BankAccount — Event Sourcing Aggregate
type BankAccount struct {
    id       string
    ownerID  string
    balance  int64
    currency string
    status   AccountStatus
    version  int // event version — optimistic locking üçün

    // Commit olunmamış event-lər
    uncommitted []Event
}

// Getters
func (a *BankAccount) ID() string            { return a.id }
func (a *BankAccount) Balance() int64        { return a.balance }
func (a *BankAccount) Currency() string      { return a.currency }
func (a *BankAccount) Status() AccountStatus { return a.status }
func (a *BankAccount) Version() int          { return a.version }
func (a *BankAccount) UncommittedEvents() []Event { return a.uncommitted }
func (a *BankAccount) ClearUncommitted()     { a.uncommitted = nil }

// record — event-i uncommitted list-ə əlavə edir
func (a *BankAccount) record(e Event) {
    a.uncommitted = append(a.uncommitted, e)
    a.apply(e)  // dərhal state-ə tətbiq et
    a.version++
}

// apply — event-i state-ə tətbiq edir; deterministik, side effect yoxdur
func (a *BankAccount) apply(e Event) {
    switch evt := e.(type) {
    case AccountOpened:
        a.id = evt.ID
        a.ownerID = evt.OwnerID
        a.balance = evt.InitialAmount
        a.currency = evt.Currency
        a.status = AccountStatusActive

    case MoneyDeposited:
        a.balance += evt.Amount

    case MoneyWithdrawn:
        a.balance -= evt.Amount

    case AccountClosed:
        a.status = AccountStatusClosed
    }
}

// LoadFromHistory — event-lərdən aggregate yenidən qurur
func (a *BankAccount) LoadFromHistory(events []Event) {
    for _, e := range events {
        a.apply(e)
        a.version++
    }
}

// ─── Domain Commands (behavior) ──────────────────────────────────────────────

// OpenAccount — yeni hesab açır
func OpenAccount(id, ownerID string, initialAmount int64, currency string) (*BankAccount, error) {
    if initialAmount < 0 {
        return nil, errors.New("initial amount cannot be negative")
    }
    if currency == "" {
        return nil, errors.New("currency is required")
    }

    acc := &BankAccount{}
    acc.record(AccountOpened{
        ID:            id,
        OwnerID:       ownerID,
        InitialAmount: initialAmount,
        Currency:      currency,
        occurredAt:    time.Now(),
    })
    return acc, nil
}

// Deposit — pul əlavə edir
func (a *BankAccount) Deposit(amount int64) error {
    if a.status == AccountStatusClosed {
        return errors.New("cannot deposit to closed account")
    }
    if amount <= 0 {
        return fmt.Errorf("deposit amount must be positive, got %d", amount)
    }

    a.record(MoneyDeposited{
        AccountID:  a.id,
        Amount:     amount,
        occurredAt: time.Now(),
    })
    return nil
}

// Withdraw — pul çıxarır; business rule: mənfi balans yoxdur
func (a *BankAccount) Withdraw(amount int64) error {
    if a.status == AccountStatusClosed {
        return errors.New("cannot withdraw from closed account")
    }
    if amount <= 0 {
        return fmt.Errorf("withdrawal amount must be positive, got %d", amount)
    }
    if a.balance < amount {
        return fmt.Errorf("insufficient funds: balance %d, requested %d", a.balance, amount)
    }

    a.record(MoneyWithdrawn{
        AccountID:  a.id,
        Amount:     amount,
        occurredAt: time.Now(),
    })
    return nil
}

// Close — hesabı bağlayır
func (a *BankAccount) Close() error {
    if a.status == AccountStatusClosed {
        return errors.New("account is already closed")
    }
    if a.balance != 0 {
        return fmt.Errorf("cannot close account with balance %d", a.balance)
    }

    a.record(AccountClosed{
        AccountID:  a.id,
        occurredAt: time.Now(),
    })
    return nil
}
```

**EventStore Interface və PostgreSQL implementasiyası:**

```go
// internal/eventstore/store.go
package eventstore

import (
    "context"
    "encoding/json"
    "database/sql"
    "fmt"
    "time"

    "github.com/yourorg/app/internal/domain"
)

// EventStore interface
type EventStore interface {
    // Append — optimistic locking: expectedVersion uyğun gəlmirsə xəta
    Append(ctx context.Context, aggregateID string, events []domain.Event, expectedVersion int) error
    // Load — bütün event-ləri yükləyir
    Load(ctx context.Context, aggregateID string) ([]domain.Event, error)
    // LoadFromVersion — snapshot-dan sonrakı event-lər üçün
    LoadFromVersion(ctx context.Context, aggregateID string, fromVersion int) ([]domain.Event, error)
}

// StoredEvent — DB-da saxlanılan format
type StoredEvent struct {
    AggregateID string
    EventType   string
    Payload     []byte
    Version     int
    OccurredAt  time.Time
}

// PostgresEventStore — PostgreSQL implementasiyası
type PostgresEventStore struct {
    db *sql.DB
}

func NewPostgresEventStore(db *sql.DB) *PostgresEventStore {
    return &PostgresEventStore{db: db}
}

func (s *PostgresEventStore) Append(
    ctx context.Context,
    aggregateID string,
    events []domain.Event,
    expectedVersion int,
) error {
    tx, err := s.db.BeginTx(ctx, nil)
    if err != nil {
        return err
    }
    defer tx.Rollback()

    // Optimistic locking: cari versiyonu yoxla
    var currentVersion int
    err = tx.QueryRowContext(ctx,
        `SELECT COALESCE(MAX(version), -1) FROM events WHERE aggregate_id = $1`,
        aggregateID,
    ).Scan(&currentVersion)
    if err != nil {
        return err
    }

    if currentVersion != expectedVersion-1 {
        return fmt.Errorf("version conflict: expected %d, got %d", expectedVersion-1, currentVersion)
    }

    // Event-ləri əlavə et
    for i, event := range events {
        payload, err := json.Marshal(event)
        if err != nil {
            return fmt.Errorf("marshaling event: %w", err)
        }

        _, err = tx.ExecContext(ctx,
            `INSERT INTO events (aggregate_id, event_type, payload, version, occurred_at)
             VALUES ($1, $2, $3, $4, $5)`,
            aggregateID,
            string(event.EventType()),
            payload,
            expectedVersion+i,
            event.OccurredAt(),
        )
        if err != nil {
            return fmt.Errorf("inserting event: %w", err)
        }
    }

    return tx.Commit()
}

func (s *PostgresEventStore) Load(
    ctx context.Context,
    aggregateID string,
) ([]domain.Event, error) {
    return s.LoadFromVersion(ctx, aggregateID, 0)
}

func (s *PostgresEventStore) LoadFromVersion(
    ctx context.Context,
    aggregateID string,
    fromVersion int,
) ([]domain.Event, error) {
    rows, err := s.db.QueryContext(ctx,
        `SELECT event_type, payload, version, occurred_at
         FROM events
         WHERE aggregate_id = $1 AND version >= $2
         ORDER BY version ASC`,
        aggregateID, fromVersion,
    )
    if err != nil {
        return nil, err
    }
    defer rows.Close()

    var events []domain.Event
    for rows.Next() {
        var eventType string
        var payload []byte
        var version int
        var occurredAt time.Time

        if err := rows.Scan(&eventType, &payload, &version, &occurredAt); err != nil {
            return nil, err
        }

        event, err := deserializeEvent(eventType, payload, occurredAt)
        if err != nil {
            return nil, fmt.Errorf("deserializing event %s v%d: %w", eventType, version, err)
        }
        events = append(events, event)
    }
    return events, nil
}

// deserializeEvent — event type-a görə JSON-u struct-a çevirir
func deserializeEvent(eventType string, payload []byte, occurredAt time.Time) (domain.Event, error) {
    switch domain.EventType(eventType) {
    case domain.EventAccountOpened:
        var e domain.AccountOpened
        if err := json.Unmarshal(payload, &e); err != nil {
            return nil, err
        }
        return e, nil

    case domain.EventMoneyDeposited:
        var e domain.MoneyDeposited
        if err := json.Unmarshal(payload, &e); err != nil {
            return nil, err
        }
        return e, nil

    case domain.EventMoneyWithdrawn:
        var e domain.MoneyWithdrawn
        if err := json.Unmarshal(payload, &e); err != nil {
            return nil, err
        }
        return e, nil

    case domain.EventAccountClosed:
        var e domain.AccountClosed
        if err := json.Unmarshal(payload, &e); err != nil {
            return nil, err
        }
        return e, nil

    default:
        return nil, fmt.Errorf("unknown event type: %s", eventType)
    }
}
```

**Snapshot Pattern:**

```go
// internal/eventstore/snapshot.go
package eventstore

import (
    "context"
    "database/sql"
    "encoding/json"

    "github.com/yourorg/app/internal/domain"
)

const snapshotThreshold = 50 // hər 50 event-dən bir snapshot

type Snapshot struct {
    AggregateID string
    Data        []byte // serialized aggregate state
    Version     int
}

type SnapshotStore interface {
    Save(ctx context.Context, snap Snapshot) error
    Load(ctx context.Context, aggregateID string) (Snapshot, bool, error)
}

type PostgresSnapshotStore struct {
    db *sql.DB
}

func (s *PostgresSnapshotStore) Save(ctx context.Context, snap Snapshot) error {
    _, err := s.db.ExecContext(ctx,
        `INSERT INTO snapshots (aggregate_id, data, version)
         VALUES ($1, $2, $3)
         ON CONFLICT (aggregate_id) DO UPDATE SET data=$2, version=$3`,
        snap.AggregateID, snap.Data, snap.Version,
    )
    return err
}

func (s *PostgresSnapshotStore) Load(
    ctx context.Context,
    aggregateID string,
) (Snapshot, bool, error) {
    var snap Snapshot
    err := s.db.QueryRowContext(ctx,
        `SELECT aggregate_id, data, version FROM snapshots WHERE aggregate_id = $1`,
        aggregateID,
    ).Scan(&snap.AggregateID, &snap.Data, &snap.Version)

    if err == sql.ErrNoRows {
        return Snapshot{}, false, nil
    }
    if err != nil {
        return Snapshot{}, false, err
    }
    return snap, true, nil
}

// BankAccountRepository — snapshot + event sourcing birlikdə
type BankAccountRepository struct {
    eventStore    EventStore
    snapshotStore SnapshotStore
}

func NewBankAccountRepository(es EventStore, ss SnapshotStore) *BankAccountRepository {
    return &BankAccountRepository{eventStore: es, snapshotStore: ss}
}

func (r *BankAccountRepository) Save(ctx context.Context, acc *domain.BankAccount) error {
    events := acc.UncommittedEvents()
    if len(events) == 0 {
        return nil
    }

    err := r.eventStore.Append(ctx, acc.ID(), events, acc.Version()-len(events))
    if err != nil {
        return err
    }
    acc.ClearUncommitted()

    // Snapshot yaratma yoxlanışı
    if acc.Version()%snapshotThreshold == 0 {
        data, _ := json.Marshal(acc)
        r.snapshotStore.Save(ctx, Snapshot{
            AggregateID: acc.ID(),
            Data:        data,
            Version:     acc.Version(),
        })
    }

    return nil
}

func (r *BankAccountRepository) Load(
    ctx context.Context,
    id string,
) (*domain.BankAccount, error) {
    acc := &domain.BankAccount{}
    fromVersion := 0

    // Snapshot varsa yüklə
    snap, found, err := r.snapshotStore.Load(ctx, id)
    if err != nil {
        return nil, err
    }
    if found {
        json.Unmarshal(snap.Data, acc)
        fromVersion = snap.Version
    }

    // Snapshot-dan sonrakı event-ləri yüklə
    events, err := r.eventStore.LoadFromVersion(ctx, id, fromVersion)
    if err != nil {
        return nil, err
    }

    acc.LoadFromHistory(events)
    return acc, nil
}
```

**DB Schema:**

```sql
-- Event Store table
CREATE TABLE events (
    id           BIGSERIAL PRIMARY KEY,
    aggregate_id VARCHAR(255) NOT NULL,
    event_type   VARCHAR(100) NOT NULL,
    payload      JSONB        NOT NULL,
    version      INTEGER      NOT NULL,
    occurred_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    -- Optimistic locking: eyni aggregate-in eyni versiyası ola bilməz
    UNIQUE (aggregate_id, version)
);

CREATE INDEX idx_events_aggregate_id ON events(aggregate_id, version);

-- Snapshot table
CREATE TABLE snapshots (
    aggregate_id VARCHAR(255) PRIMARY KEY,
    data         JSONB        NOT NULL,
    version      INTEGER      NOT NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
```

**Tam iş axını — nümunə:**

```go
func ExampleWorkflow() {
    ctx := context.Background()
    repo := NewBankAccountRepository(eventStore, snapshotStore)

    // Hesab açılır — AccountOpened event emit edilir
    acc, err := domain.OpenAccount("acc-001", "user-123", 50000, "USD")
    if err != nil {
        log.Fatal(err)
    }
    repo.Save(ctx, acc)

    // Hesab yüklənir — event-lər replay edilir
    acc, err = repo.Load(ctx, "acc-001")
    if err != nil {
        log.Fatal(err)
    }
    log.Printf("Balance after open: %d", acc.Balance()) // 50000

    // Para yatırılır
    acc.Deposit(30000)
    repo.Save(ctx, acc)

    // Para çıxarılır
    acc, _ = repo.Load(ctx, "acc-001")
    acc.Withdraw(10000)
    repo.Save(ctx, acc)

    // Son balans — bütün event-lərdən hesablanır
    acc, _ = repo.Load(ctx, "acc-001")
    log.Printf("Final balance: %d", acc.Balance()) // 70000
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Yeni event:**
`InterestApplied` event əlavə edin. `BankAccount.ApplyInterest(rate float64)` metodu yazın. Balans 1000-dirsə və rate 0.05-dirsə, 50 əlavə olunmalıdır. Test yazın.

**Tapşırıq 2 — EventStore test:**
In-memory EventStore yazın (map). Optimistic locking-i test edin: eyni version ilə iki paralel yazma — birincisi qazanır, ikincisi xəta qaytarır.

**Tapşırıq 3 — Projection:**
`account_balances` read table-ı yaradın. Event-lər gəldikdə update edən `BalanceProjection` yazın. CQRS query side üçün bu table-dan oxuyun.

**Tapşırıq 4 — Snapshot:**
`snapshotThreshold = 5` ilə test edin. 10 deposit edin. 5-ci event-dən sonra snapshot yarandığını doğrulayın. Sonra load etdikdə snapshot + sonrakı 5 event replay etdiyini test edin.

**Tapşırıq 5 — Time travel:**
`LoadAtVersion(ctx, id, version)` metodu yazın. Bu metod yalnız ilk N event-i replay edir. Keçmişdəki istənilən balansı qaytarmaq üçün istifadə edin.

## Əlaqəli Mövzular

- `31-cqrs.md` — Event Sourcing + CQRS ən güclü kombinasiya; write side event sourcing, read side projections
- `30-ddd-tactical.md` — Domain Events DDD tactical pattern-idir; Event Sourcing bu üzərində qurulur
- `15-event-bus.md` — Projection-ları update etmək üçün event bus
- `25-message-queues.md` — Event-ləri async consumer-lara çatdırmaq üçün
- `26-microservices.md` — Microservice-lər arasında event-driven communication
