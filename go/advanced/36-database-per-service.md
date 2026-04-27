# Database per Service (Lead)

## İcmal

Database per Service, microservice arxitekturasının fundamental pattern-lərindən biridir: hər microservice **yalnız öz database-inə** malik olur və heç bir başqa servis onun database-inə birbaşa çata bilməz. Servislərarası data mübadiləsi yalnız API və ya event-lər vasitəsilə baş verir.

Bu pattern microservice arxitekturasını həqiqətən müstəqil edir — lakin yeni problemlər gətirir: cross-service sorğular, distributed transactions, eventual consistency.

## Niyə Vacibdir

**Shared database anti-pattern-i:**

```
Order Service  ──┐
Payment Service ──┼──► Shared PostgreSQL ← bu shared DB-dir
User Service    ──┘
```

Problemlər:
- Schema dəyişikliyi bir servisin digərlərini sındıra bilər
- Bir servisin yük artması DB-nin hamısını yavaşladır
- Bütün servisləri eyni DB texnologiyasına məhkum edir
- Independent deploy mümkün olmur

**Database per Service:**

```
Order Service   ──► PostgreSQL (transactional)
Product Service ──► Elasticsearch (full-text search)
Session Service ──► Redis (in-memory, fast expiry)
Analytics       ──► ClickHouse (OLAP, columnar)
```

Hər servis öz işinə ən uyğun database-i seçir. Bir servisin database-i danarsa yalnız o servis təsirlənir.

## Əsas Anlayışlar

### DB Seçimi — Servis Tipinə Görə

| Servis           | DB Tipi        | Səbəb                              |
|------------------|----------------|------------------------------------|
| Order Service    | PostgreSQL     | ACID transactions, relational data |
| Product Catalog  | Elasticsearch  | Full-text search, faceted filtering|
| Session Service  | Redis          | In-memory, TTL, ultra-fast reads   |
| Analytics        | ClickHouse     | OLAP, columnar, aggregations       |
| Media Metadata   | MongoDB        | Flexible schema, nested documents  |
| Graph Relations  | Neo4j          | Graph traversal (friends of friends)|
| Time-series Logs | InfluxDB       | Time-series optimized              |

### Eventual Consistency

Shared DB olmadığı üçün data consistency nə zaman əldə edilir? **Eventual** olaraq — event-lər vasitəsilə. Bu, strong consistency-dən fərqlidir və bunu qəbul etmək lazımdır.

Nümunə: User silinir → `UserDeleted` event publish edilir → Order Service dinləyir → öz DB-sindəki user məlumatını güncəlləyir. Bu anlıq deyil, bir neçə millisaniyə-saniyə gec ola bilər.

### Cross-Service Queries Problemi

Monolith-də: `SELECT o.*, u.name FROM orders o JOIN users u ON o.user_id = u.id`

Microservice-lərdə bu mümkün deyil — `orders` və `users` fərqli database-lərdədir.

Həllər:
1. **API Composition**: Aggregator servis hər iki servisə ayrı-ayrı sorğu atır, nəticəni birləşdirir
2. **CQRS Read Model**: Read üçün denormalized kopyalar saxla
3. **Event-driven sync**: Servis B, Servis A-nın lazımlı datanı öz DB-sinə kopyalayır

## Praktik Baxış

### Data Migration Strategy

Hər servis öz migration-larını idarə edir:

```go
// Order Service öz migration-larını saxlayır
// migrations/001_create_orders.sql
// migrations/002_add_status_index.sql

// golang-migrate ilə
import "github.com/golang-migrate/migrate/v4"

func runMigrations(db *sql.DB) error {
    m, err := migrate.NewWithDatabaseInstance(
        "file://migrations",
        "postgres",
        driver,
    )
    if err != nil {
        return err
    }
    return m.Up()
}
```

### Monitoring Per Service

Hər servis öz DB metrics-lərini izləyir:

```go
// pgxpool ilə connection pool stats
stats := pool.Stat()
prometheus.MustRegister(prometheus.NewGaugeFunc(
    prometheus.GaugeOpts{Name: "db_open_connections"},
    func() float64 { return float64(stats.TotalConns()) },
))
```

### Trade-off-lar

| Üstünlük                          | Çatışmazlıq                           |
|-----------------------------------|---------------------------------------|
| Independent scaling               | Cross-service queries mürəkkəbdir     |
| Polglot persistence               | Distributed transactions çətin        |
| Fault isolation                   | Eventual consistency qəbul etmək lazım|
| Independent deployment            | Operational mürəkkəblik artır         |
| Schema freedom                    | Data duplication olur                 |

## Nümunələr

### Ümumi Nümunə

```
Ssenari: "User X-in bütün sifarişlərini göstər" (order + user data birlikdə)

Shared DB ilə (köhnə yol):
SELECT o.*, u.name, u.email
FROM orders o
JOIN users u ON o.user_id = u.id
WHERE o.user_id = $1

Database per Service ilə:
1. Order Service → öz DB-sindən sifarişləri gətir
2. User Service → user_id ilə user məlumatını gətir
3. API Gateway / BFF → iki nəticəni birləşdir

Bu "API Composition" pattern-dir.
```

### Kod Nümunəsi

**Order Service — PostgreSQL ilə:**

```go
package order

import (
    "context"
    "database/sql"
    "encoding/json"
    "time"
)

type Order struct {
    ID        string    `json:"id"`
    UserID    string    `json:"user_id"`
    ProductID string    `json:"product_id"`
    Amount    float64   `json:"amount"`
    Status    string    `json:"status"`
    CreatedAt time.Time `json:"created_at"`
}

type OrderRepository struct {
    db *sql.DB
}

func (r *OrderRepository) FindByUserID(ctx context.Context, userID string) ([]*Order, error) {
    rows, err := r.db.QueryContext(ctx,
        `SELECT id, user_id, product_id, amount, status, created_at
         FROM orders WHERE user_id = $1 ORDER BY created_at DESC`,
        userID,
    )
    if err != nil {
        return nil, err
    }
    defer rows.Close()

    var orders []*Order
    for rows.Next() {
        o := &Order{}
        if err := rows.Scan(&o.ID, &o.UserID, &o.ProductID, &o.Amount, &o.Status, &o.CreatedAt); err != nil {
            return nil, err
        }
        orders = append(orders, o)
    }
    return orders, rows.Err()
}

// OrderCreated event publish edir — digər servisler dinləyir
func (s *OrderService) CreateOrder(ctx context.Context, req CreateOrderRequest) (*Order, error) {
    order := &Order{
        ID:        generateID(),
        UserID:    req.UserID,
        ProductID: req.ProductID,
        Amount:    req.Amount,
        Status:    "pending",
    }

    if err := s.repo.Save(ctx, order); err != nil {
        return nil, err
    }

    // Event publish et — Outbox pattern ilə (atomic)
    payload, _ := json.Marshal(order)
    s.eventPublisher.Publish(ctx, "order.created", order.ID, payload)

    return order, nil
}
```

**Notification Service — MongoDB ilə (denormalized copy):**

```go
package notification

import (
    "context"
    "go.mongodb.org/mongo-driver/bson"
    "go.mongodb.org/mongo-driver/mongo"
)

// Notification Service öz DB-sindəki denormalized kopyada user məlumatını saxlayır
type UserSnapshot struct {
    UserID    string `bson:"user_id"`
    Email     string `bson:"email"`
    Name      string `bson:"name"`
    UpdatedAt int64  `bson:"updated_at"`
}

type UserSnapshotRepository struct {
    collection *mongo.Collection
}

// UserUpdated event-i gəldikdə snapshot-u güncəllə
func (r *UserSnapshotRepository) Upsert(ctx context.Context, snapshot *UserSnapshot) error {
    filter := bson.M{"user_id": snapshot.UserID}
    update := bson.M{"$set": snapshot}
    opts := options.Update().SetUpsert(true)
    _, err := r.collection.UpdateOne(ctx, filter, update, opts)
    return err
}

// Notification göndərərkən öz DB-sindəki kopyanı istifadə edir
// — User Service-ə əlavə sorğu atmaq lazım deyil
func (s *NotificationService) SendOrderConfirmation(ctx context.Context, orderID, userID string) error {
    user, err := s.userSnapshotRepo.FindByID(ctx, userID)
    if err != nil {
        return err
    }
    // user.Email — öz DB-sindən gəlir
    return s.emailSender.Send(user.Email, "Order confirmed: "+orderID)
}
```

**UserUpdated event consumer:**

```go
// Notification Service, User Service-in event-lərini dinləyir
func (c *NotificationEventConsumer) HandleUserUpdated(ctx context.Context, event UserUpdatedEvent) error {
    snapshot := &UserSnapshot{
        UserID:    event.UserID,
        Email:     event.Email,
        Name:      event.Name,
        UpdatedAt: time.Now().Unix(),
    }
    return c.userSnapshotRepo.Upsert(ctx, snapshot)
}
```

**API Composition Pattern — BFF Handler:**

```go
package bff

import (
    "context"
    "encoding/json"
    "net/http"
    "sync"
)

// UserOrdersResponse — iki servisdən gələn datanı birləşdirir
type UserOrdersResponse struct {
    User   *UserDTO    `json:"user"`
    Orders []*OrderDTO `json:"orders"`
}

type AggregatorHandler struct {
    userClient  UserServiceClient
    orderClient OrderServiceClient
}

func (h *AggregatorHandler) GetUserWithOrders(w http.ResponseWriter, r *http.Request) {
    userID := r.PathValue("user_id")
    ctx := r.Context()

    var (
        wg       sync.WaitGroup
        mu       sync.Mutex
        response UserOrdersResponse
        errors   []error
    )

    wg.Add(2)

    // User Service-ə sorğu
    go func() {
        defer wg.Done()
        user, err := h.userClient.GetUser(ctx, userID)
        mu.Lock()
        defer mu.Unlock()
        if err != nil {
            errors = append(errors, err)
            return
        }
        response.User = user
    }()

    // Order Service-ə sorğu
    go func() {
        defer wg.Done()
        orders, err := h.orderClient.GetOrdersByUser(ctx, userID)
        mu.Lock()
        defer mu.Unlock()
        if err != nil {
            errors = append(errors, err)
            return
        }
        response.Orders = orders
    }()

    wg.Wait()

    if len(errors) > 0 {
        http.Error(w, "Partial failure", http.StatusServiceUnavailable)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(response)
}
```

**CQRS Read Model — Event-driven sync:**

```go
// Analytics Service öz ClickHouse DB-sini saxlayır
// Order Service-dən event-ləri dinləyib öz cədvəlinə yazar

type OrderAnalyticsConsumer struct {
    clickhouse *clickhouse.Conn
}

func (c *OrderAnalyticsConsumer) HandleOrderCreated(ctx context.Context, event OrderCreatedEvent) error {
    return c.clickhouse.Exec(ctx, `
        INSERT INTO order_analytics
        (order_id, user_id, product_id, amount, created_at)
        VALUES (?, ?, ?, ?, ?)
    `, event.OrderID, event.UserID, event.ProductID, event.Amount, time.Now())
}

// Analytics sorğusu — yalnız öz DB-sinə baxır (cross-service sorğu yoxdur)
func (s *AnalyticsService) GetRevenue(ctx context.Context, from, to time.Time) (float64, error) {
    var revenue float64
    err := s.clickhouse.QueryRow(ctx, `
        SELECT sum(amount)
        FROM order_analytics
        WHERE created_at BETWEEN ? AND ?
    `, from, to).Scan(&revenue)
    return revenue, err
}
```

**docker-compose.yml — hər servisin öz DB-si:**

```yaml
services:
  order-service:
    build: ./order-service
    environment:
      - DATABASE_URL=postgres://order_user:pass@order-db:5432/orders
    depends_on:
      - order-db

  order-db:
    image: postgres:16
    environment:
      POSTGRES_DB: orders
      POSTGRES_USER: order_user
      POSTGRES_PASSWORD: pass

  product-service:
    build: ./product-service
    environment:
      - ELASTICSEARCH_URL=http://product-search:9200

  product-search:
    image: elasticsearch:8.12.0
    environment:
      - discovery.type=single-node

  session-service:
    build: ./session-service
    environment:
      - REDIS_URL=redis://session-cache:6379

  session-cache:
    image: redis:7-alpine

  notification-service:
    build: ./notification-service
    environment:
      - MONGODB_URL=mongodb://notif-db:27017/notifications

  notif-db:
    image: mongo:7
```

## Praktik Tapşırıqlar

1. **Servis Cütü**: Order Service (PostgreSQL) və Notification Service (MongoDB) implement et. `OrderCreated` event-i ilə Notification Service öz DB-sinə user snapshot saxlamalıdır.

2. **API Composition**: User + Order datanı birləşdirən BFF handler yaz. Paralel sorğu at (`sync.WaitGroup`). Bir servis uğursuz olarsa partial response qaytar.

3. **Event-driven Sync**: User Service-dən `UserUpdated` event-i publish et. Notification Service həmin event-i dinləyib öz MongoDB snapshot-unu güncəlləsin.

4. **Migration Isolation**: Hər servisin öz `migrations/` folder-i olsun. `golang-migrate` ilə migrate et. Order Service-in migration-ı Notification Service-i etkiləməməlidir.

5. **Docker Compose**: 3 servis + 3 fərqli DB-ni docker-compose ilə qur. Hər servis yalnız öz DB-sinə qoşula bilməlidir (Docker network isolation).

## Əlaqəli Mövzular

- `33-saga-pattern.md` — Servislərarası distributed transactions
- `34-outbox-pattern.md` — Atomik event publishing
- `35-api-gateway-patterns.md` — API Composition / BFF pattern
- `26-microservices.md` — Microservice arxitekturası əsasları
- `25-message-queues.md` — Event-driven communication
