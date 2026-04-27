# Outbox Pattern (Lead)

## İcmal

Transactional Outbox Pattern, database yazma əməliyyatı ilə message broker-ə event publish etməni atomik şəkildə həyata keçirmək üçün istifadə olunan bir pattern-dir. Problem sadədir: ya hər ikisi uğurlu olmalıdır, ya da heç biri. Lakin database transaction ilə Kafka/RabbitMQ publish əməliyyatını eyni transaction-a daxil etmək mümkün deyil.

**Əsas fikir**: eventi birbaşa broker-ə göndərmək əvəzinə, onu eyni database transaction-ı içində `outbox` cədvəlinə yaz. Sonra ayrı bir process həmin cədvəli oxuyub broker-ə göndərir.

## Niyə Vacibdir

**Problem ssenarisi:**

```
BEGIN TRANSACTION
  INSERT INTO orders (id, status) VALUES (...)   ✓
  kafka.Publish("order.created", event)          ✗ (network xətası)
COMMIT
```

Bu halda order database-ə yazıldı, lakin event publish edilmədi. Sistem inconsistent vəziyyətdədir. Digər microservice-lər `order.created` event-ini heç vaxt almayacaq.

**Alternativ cəhd:**

```
kafka.Publish("order.created", event)            ✓
BEGIN TRANSACTION
  INSERT INTO orders (id, status) VALUES (...)   ✗ (DB xətası)
COMMIT
```

İndi isə event göndərildi, lakin order yaradılmadı. Yenə inconsistency.

Outbox Pattern bu problemi həll edir: hər iki yazma eyni database transaction-ı içindədir.

## Əsas Anlayışlar

### Outbox Cədvəli

```sql
CREATE TABLE outbox_events (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    aggregate_type  VARCHAR(100) NOT NULL,  -- "Order", "Payment"
    aggregate_id    VARCHAR(100) NOT NULL,  -- entity ID
    event_type      VARCHAR(100) NOT NULL,  -- "OrderCreated", "PaymentProcessed"
    payload         JSONB        NOT NULL,  -- event data
    published_at    TIMESTAMPTZ,            -- NULL = hələ göndərilməyib
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_outbox_unpublished ON outbox_events (created_at)
    WHERE published_at IS NULL;
```

### Outbox Publisher Strategiyaları

**Polling Publisher**: Background goroutine müntəzəm olaraq `published_at IS NULL` olan event-ləri oxuyur və broker-ə göndərir.

**CDC (Change Data Capture)**: Debezium kimi bir tool database WAL (Write-Ahead Log) log-larını izləyir. Outbox cədvəlinə yeni sətir əlavə olunduqda avtomatik broker-ə göndərir.

### At-Least-Once Delivery

Outbox Pattern **at-least-once** delivery zəmanəti verir — eyni event birdən çox göndərilə bilər. Consumer-lər **idempotent** olmalıdır.

## Praktik Baxış

### Polling vs CDC

| Xüsusiyyət      | Polling Publisher      | CDC (Debezium)            |
|-----------------|------------------------|---------------------------|
| Latency         | N saniyə (poll interval) | Demək olar ki, real-time |
| Mürəkkəblik     | Sadə                   | Əlavə infrastructure      |
| DB load         | Poll sorğuları          | WAL oxuma (aşağı load)   |
| Ordering        | created_at ilə         | WAL ilə dəqiq ordering   |
| Başlamaq üçün   | İdeal                  | Yüksək həcmdə lazım olsa |

### Nə zaman istifadə etməli

- DB yazma ilə event publish atomik olmalıdırsa
- Microservice-lar arası eventual consistency lazımdırsa
- Message broker geçici olaraq əlçatmaz ola bilərsə

### Anti-pattern-lər

- Outbox-u ayrı transaction-da yazmaq (pattern-i məhv edir)
- Çox böyük payload-lar (JSONB-də KB-larla data saxlamaq)
- Published event-ləri silməmək (cədvəl şişir)
- Consumer-ləri idempotent etməmək

## Nümunələr

### Ümumi Nümunə

```
1. HTTP Request gəlir: "Order yarat"
2. Database transaction açılır:
   a. orders cədvəlinə INSERT
   b. outbox_events cədvəlinə INSERT (published_at = NULL)
   c. COMMIT
3. HTTP Response: "201 Created"
4. [Ayrı goroutine] OutboxPublisher:
   a. SELECT * FROM outbox_events WHERE published_at IS NULL
   b. Kafka-ya göndər
   c. UPDATE outbox_events SET published_at = NOW()
5. Consumer event alır
```

### Kod Nümunəsi

**Outbox repository:**

```go
package outbox

import (
    "context"
    "database/sql"
    "encoding/json"
    "time"

    "github.com/google/uuid"
)

type OutboxEvent struct {
    ID            string
    AggregateType string
    AggregateID   string
    EventType     string
    Payload       json.RawMessage
    PublishedAt   *time.Time
    CreatedAt     time.Time
}

type OutboxRepository interface {
    Save(ctx context.Context, tx *sql.Tx, event *OutboxEvent) error
    GetUnpublished(ctx context.Context, limit int) ([]*OutboxEvent, error)
    MarkAsPublished(ctx context.Context, ids []string) error
}

type postgresOutboxRepository struct {
    db *sql.DB
}

func NewPostgresOutboxRepository(db *sql.DB) OutboxRepository {
    return &postgresOutboxRepository{db: db}
}

// Save — mövcud transaction daxilində outbox event-ini saxlayır
func (r *postgresOutboxRepository) Save(ctx context.Context, tx *sql.Tx, event *OutboxEvent) error {
    event.ID = uuid.New().String()
    event.CreatedAt = time.Now()

    _, err := tx.ExecContext(ctx, `
        INSERT INTO outbox_events (id, aggregate_type, aggregate_id, event_type, payload, created_at)
        VALUES ($1, $2, $3, $4, $5, $6)
    `, event.ID, event.AggregateType, event.AggregateID, event.EventType, event.Payload, event.CreatedAt)

    return err
}

// GetUnpublished — hələ göndərilməmiş event-ləri gətirir
func (r *postgresOutboxRepository) GetUnpublished(ctx context.Context, limit int) ([]*OutboxEvent, error) {
    rows, err := r.db.QueryContext(ctx, `
        SELECT id, aggregate_type, aggregate_id, event_type, payload, created_at
        FROM outbox_events
        WHERE published_at IS NULL
        ORDER BY created_at ASC
        LIMIT $1
        FOR UPDATE SKIP LOCKED
    `, limit)
    if err != nil {
        return nil, err
    }
    defer rows.Close()

    var events []*OutboxEvent
    for rows.Next() {
        e := &OutboxEvent{}
        if err := rows.Scan(&e.ID, &e.AggregateType, &e.AggregateID, &e.EventType, &e.Payload, &e.CreatedAt); err != nil {
            return nil, err
        }
        events = append(events, e)
    }
    return events, rows.Err()
}

// MarkAsPublished — göndərilmiş event-ləri işarələyir
func (r *postgresOutboxRepository) MarkAsPublished(ctx context.Context, ids []string) error {
    if len(ids) == 0 {
        return nil
    }
    // PostgreSQL ANY() ilə batch update
    _, err := r.db.ExecContext(ctx, `
        UPDATE outbox_events
        SET published_at = NOW()
        WHERE id = ANY($1)
    `, ids) // lib/pq string array-i qəbul edir
    return err
}
```

**Atomik business əməliyyatı:**

```go
package order

import (
    "context"
    "database/sql"
    "encoding/json"

    "github.com/myapp/outbox"
)

type OrderService struct {
    db        *sql.DB
    orderRepo OrderRepository
    outboxRepo outbox.OutboxRepository
}

type OrderCreatedPayload struct {
    OrderID   string  `json:"order_id"`
    UserID    string  `json:"user_id"`
    ProductID string  `json:"product_id"`
    Amount    float64 `json:"amount"`
}

func (s *OrderService) CreateOrder(ctx context.Context, req CreateOrderRequest) (*Order, error) {
    // Transaction aç
    tx, err := s.db.BeginTx(ctx, nil)
    if err != nil {
        return nil, err
    }
    defer tx.Rollback() // err olmasa COMMIT sonrası bu ignored olur

    // 1. Order-i saxla
    order := &Order{
        ID:        generateID(),
        UserID:    req.UserID,
        ProductID: req.ProductID,
        Amount:    req.Amount,
        Status:    "pending",
    }
    if err := s.orderRepo.SaveTx(ctx, tx, order); err != nil {
        return nil, err
    }

    // 2. Outbox event-ini EYNI transaction-da saxla
    payload, _ := json.Marshal(OrderCreatedPayload{
        OrderID:   order.ID,
        UserID:    order.UserID,
        ProductID: order.ProductID,
        Amount:    order.Amount,
    })

    outboxEvent := &outbox.OutboxEvent{
        AggregateType: "Order",
        AggregateID:   order.ID,
        EventType:     "OrderCreated",
        Payload:       payload,
    }
    if err := s.outboxRepo.Save(ctx, tx, outboxEvent); err != nil {
        return nil, err
    }

    // 3. Commit — hər iki əməliyyat atomikdir
    if err := tx.Commit(); err != nil {
        return nil, err
    }

    return order, nil
}
```

**Outbox Publisher — background goroutine:**

```go
package outbox

import (
    "context"
    "log"
    "time"
)

type MessageBroker interface {
    Publish(ctx context.Context, topic string, key string, payload []byte) error
}

type OutboxPublisher struct {
    repo          OutboxRepository
    broker        MessageBroker
    pollInterval  time.Duration
    batchSize     int
}

func NewOutboxPublisher(repo OutboxRepository, broker MessageBroker) *OutboxPublisher {
    return &OutboxPublisher{
        repo:         repo,
        broker:       broker,
        pollInterval: 5 * time.Second,
        batchSize:    100,
    }
}

// Start — context ləğv edilənə kimi işləyir (graceful shutdown)
func (p *OutboxPublisher) Start(ctx context.Context) {
    ticker := time.NewTicker(p.pollInterval)
    defer ticker.Stop()

    for {
        select {
        case <-ctx.Done():
            log.Println("OutboxPublisher stopping...")
            return
        case <-ticker.C:
            if err := p.processEvents(ctx); err != nil {
                log.Printf("OutboxPublisher error: %v", err)
            }
        }
    }
}

func (p *OutboxPublisher) processEvents(ctx context.Context) error {
    events, err := p.repo.GetUnpublished(ctx, p.batchSize)
    if err != nil {
        return err
    }
    if len(events) == 0 {
        return nil
    }

    publishedIDs := make([]string, 0, len(events))

    for _, event := range events {
        topic := topicForEvent(event.AggregateType, event.EventType)

        if err := p.broker.Publish(ctx, topic, event.AggregateID, event.Payload); err != nil {
            log.Printf("Failed to publish event %s: %v", event.ID, err)
            continue // digər event-ləri cəhd et
        }

        publishedIDs = append(publishedIDs, event.ID)
        log.Printf("Published event: %s/%s (id: %s)", event.AggregateType, event.EventType, event.ID)
    }

    // Uğurlu göndərilənləri işarələ
    if len(publishedIDs) > 0 {
        return p.repo.MarkAsPublished(ctx, publishedIDs)
    }
    return nil
}

func topicForEvent(aggregateType, eventType string) string {
    // "Order" + "OrderCreated" → "order.created"
    return aggregateType + "." + eventType
}
```

**main.go-da işə salma:**

```go
func main() {
    db := setupDatabase()
    broker := setupKafka()

    outboxRepo := outbox.NewPostgresOutboxRepository(db)
    publisher := outbox.NewOutboxPublisher(outboxRepo, broker)

    ctx, cancel := context.WithCancel(context.Background())
    defer cancel()

    // Graceful shutdown
    go func() {
        sigCh := make(chan os.Signal, 1)
        signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
        <-sigCh
        cancel()
    }()

    // OutboxPublisher-i ayrı goroutine-də başlat
    go publisher.Start(ctx)

    // HTTP server-i başlat
    server.ListenAndServe(ctx)
}
```

**CDC yanaşması (Debezium ilə):**

```yaml
# debezium-connector.json
{
  "name": "outbox-connector",
  "config": {
    "connector.class": "io.debezium.connector.postgresql.PostgresConnector",
    "database.hostname": "postgres",
    "database.dbname": "myapp",
    "table.include.list": "public.outbox_events",
    "transforms": "outbox",
    "transforms.outbox.type": "io.debezium.transforms.outbox.EventRouter",
    "transforms.outbox.table.field.event.id": "id",
    "transforms.outbox.table.field.event.key": "aggregate_id",
    "transforms.outbox.table.field.event.payload": "payload",
    "transforms.outbox.route.by.field": "aggregate_type"
  }
}
```

CDC ilə polling koduna ehtiyac qalmır — Debezium WAL-dan oxuyub avtomatik Kafka-ya göndərir.

**Consumer-də idempotency:**

```go
// Consumer hər event-i unique ID ilə deduplication edir
func (c *OrderEventConsumer) HandleOrderCreated(ctx context.Context, msg KafkaMessage) error {
    eventID := msg.Headers["event-id"]

    // Bu event artıq işlənib?
    processed, err := c.dedupeStore.IsProcessed(ctx, eventID)
    if err != nil {
        return err
    }
    if processed {
        log.Printf("Duplicate event ignored: %s", eventID)
        return nil
    }

    // Business logic
    if err := c.processOrderCreated(ctx, msg.Payload); err != nil {
        return err
    }

    // İşləndi kimi işarələ
    return c.dedupeStore.MarkProcessed(ctx, eventID, 24*time.Hour)
}
```

## Praktik Tapşırıqlar

1. **Basic Implementation**: `CreateOrder` endpoint-i yaz. Order yaratdıqda eyni transaction-da outbox event-i əlavə et. OutboxPublisher-i implement et — sadə `time.Sleep` ilə poll et.

2. **Kafka Integration**: OutboxPublisher-i real Kafka ilə qoş. `segmentio/kafka-go` və ya `confluentinc/confluent-kafka-go` istifadə et. Topic routing-i event type-a görə həll et.

3. **SKIP LOCKED**: `FOR UPDATE SKIP LOCKED` SQL construct-ını test et. Birdən çox OutboxPublisher instance-ı paralel işlətdikdə eyni event-in iki dəfə göndərilmədiyini yoxla.

4. **Cleanup Job**: Köhnə (published_at > 7 gün) event-ləri sil. Ayrı goroutine kimi implement et.

5. **Metrics**: Outbox queue depth (unpublished events sayı), publish latency, publish error rate üçün Prometheus metrics əlavə et.

## Əlaqəli Mövzular

- `33-saga-pattern.md` — Saga-nın event-lərini atomik publish etmək üçün Outbox istifadə olunur
- `25-message-queues.md` — Kafka/RabbitMQ ilə işləmək
- `15-event-bus.md` — Internal event bus pattern-i
- `36-database-per-service.md` — Hər servisin öz database-i
- `24-monitoring-and-observability.md` — Outbox metrics monitorinqi
