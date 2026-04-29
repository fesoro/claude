# CQRS in Practice (Architect ⭐⭐⭐⭐⭐)

## İcmal
CQRS (Command Query Responsibility Segregation), read (query) və write (command) əməliyyatlarını tamamilə ayrı modellərə, hətta ayrı database-lərə bölən arxitektura pattern-dır. Greg Young tərəfindən formalaşdırılmış bu pattern, read-ağır sistemlərdə performance, DDD (Domain-Driven Design) tətbiqlərində domain model aydınlığı, event sourcing ilə birlikdə güclü audit trail üçün istifadə olunur. Çox güclü amma hər yerdə lazım deyil.

## Niyə Vacibdir
Amazon-un product catalog (milyardlarla oxuma, yüz milyonlarla yazma), LinkedIn-in news feed, banking sistemlərinin balance view — hamısı yazma və oxuma trafiki arasında kəskin asimmetriya yaşayır. CQRS bu asimmetriyanı hər tərəf üçün optimal DB seçiminə imkan verərək həll edir. Architect bu pattern-ı "hər yerdə istifadə et" yox, "nə zaman doğru seçimdir" kontekstdə izah edə bilir.

## Əsas Anlayışlar

### 1. Klassik CRUD vs CQRS Müqayisəsi

**Klassik CRUD:**
```
GET /orders/123     → Order aggregate oxunur
POST /orders        → Order aggregate yenilənir
GET /users/1/orders → N+1 problem, JOIN-lər
GET /dashboard      → Complex aggregation queries

Single model:
  - Orders table: normalized
  - Bütün sorğular eyni table-dan
  - Write model = Read model
  - Complex queries → performance problem
```

**CQRS:**
```
Write side:
  POST /orders → Command → OrderAggregate → Event
  Write DB: Normalized, optimized for writes

Read side:
  GET /orders/123     → Order Read Model (denormalized)
  GET /users/1/orders → UserOrders Read Model (pre-aggregated)
  GET /dashboard      → DashboardStats Read Model (materialized)
  
  Read DB: Denormalized, query-optimized, can be different technology
```

### 2. Command vs Query Fərqi

**Command:**
```
PlaceOrderCommand {user_id, items, payment_method}
CancelOrderCommand {order_id, reason}
UpdateShippingAddressCommand {order_id, address}

Properties:
  - State-i dəyişdirir
  - Return: success/failure (not data)
  - Validate qərarlar: "Bunu edə bilərikmi?"
  - Invariant-ları qoruyur
  - Side effects var (events emit)
```

**Query:**
```
GetOrderQuery {order_id} → OrderReadModel
ListUserOrdersQuery {user_id, page, filters} → OrderList
GetDashboardStatsQuery {date_range} → DashboardStats

Properties:
  - State-i dəyişdirmir (idempotent)
  - Return: data
  - No side effects
  - Can be cached freely
  - Eventual consistency ok
```

### 3. Simple CQRS (Same Database, Different Models)
```
Write side (Laravel Command Handler):
  class PlaceOrderHandler {
    public function handle(PlaceOrderCommand $cmd): void {
        $order = Order::create([
            'user_id' => $cmd->userId,
            'status' => 'pending',
        ]);
        
        foreach ($cmd->items as $item) {
            $order->items()->create([...]);
        }
        
        event(new OrderPlaced($order));
    }
  }

Read side (Separate Read Model):
  class OrderReadModel {
    // Denormalized view, pre-joined
    // updated by event listeners
  }

  class GetOrderHandler {
    public function handle(GetOrderQuery $query): array {
        return DB::table('order_read_models')
            ->where('order_id', $query->orderId)
            ->first();
    }
  }
```

### 4. Full CQRS (Separate Databases)
```
Write Store (PostgreSQL - normalized):
  orders: {id, user_id, status, created_at}
  order_items: {order_id, product_id, qty, price}
  
Read Store (Elasticsearch or denormalized PostgreSQL):
  order_view: {
    order_id, user_id, user_name, user_email,
    items_json, total, status,
    created_at, shipped_at,
    product_names, product_images
  }

Sync mechanism:
  Order write → event → projector → read model update
  Eventual consistency: read model lags (ms to seconds)
```

### 5. Event Projections
```
Projection: Events → Read Model

class OrderProjection {
  
    public function onOrderPlaced(OrderPlacedEvent $event): void {
        DB::table('order_views')->insert([
            'order_id'   => $event->orderId,
            'user_id'    => $event->userId,
            'status'     => 'pending',
            'items_json' => json_encode($event->items),
            'total'      => $event->total,
            'created_at' => $event->timestamp,
        ]);
    }
    
    public function onOrderShipped(OrderShippedEvent $event): void {
        DB::table('order_views')->where('order_id', $event->orderId)->update([
            'status'      => 'shipped',
            'tracking_no' => $event->trackingNumber,
            'shipped_at'  => $event->timestamp,
        ]);
    }
    
    public function onOrderDelivered(OrderDeliveredEvent $event): void {
        DB::table('order_views')->where('order_id', $event->orderId)->update([
            'status'       => 'delivered',
            'delivered_at' => $event->timestamp,
        ]);
    }
}
```

### 6. Multiple Read Models
```
Eyni event-lər fərqli read model-lər üçün:

Event: OrderPlacedEvent

Read Model 1: OrderDetailView
  {order_id, items, total, status, shipping_address}
  Kullanım: User dashboard

Read Model 2: AdminOrderView
  {order_id, user_details, payment_details, risk_score}
  Kullanım: Admin panel

Read Model 3: AnalyticsOrderView
  {order_id, category, revenue, region, hour_of_day}
  Kullanım: Business analytics

Read Model 4: SearchOrderIndex
  {Elasticsearch index for full-text search}
  Kullanım: Order search

Hər read model öz optimization-ına sahib:
  OrderDetailView → PostgreSQL (relational)
  AdminOrderView → PostgreSQL (RBAC secured)
  AnalyticsView → BigQuery (columnar, fast aggregation)
  SearchIndex → Elasticsearch (full-text search)
```

### 7. CQRS + Event Sourcing
```
Write side:
  Command → Aggregate → validate → emit Events → Event Store

Read side:
  Events → Projections → Read Models

Consistency:
  Write: Strongly consistent (event store)
  Read: Eventually consistent (projection lag)

Rebuild read models:
  Event Store replay all events → rebuild any projection
  Use case: Bug fix in projection logic → rebuild from scratch
  Downtime: Depends on event count (parallel replay possible)

Snapshot:
  Every 100 events → save aggregate state snapshot
  Replay: Last snapshot + events since snapshot
  Performance: O(N events) → O(1 snapshot + N recent events)
```

### 8. Eventual Consistency Management
```
Problem:
  POST /orders → 202 Accepted
  Immediate GET /orders/123 → Not found? (read model not updated yet)
  
  User: "Bug! My order disappeared!"

Solutions:

1. Return data in command response:
   POST /orders → {order_id, status, ...}  ← denormalized data
   Client uses response data immediately
   No need to GET immediately after POST

2. Read-your-writes consistency:
   After POST, route GETs to write store (for 1-2 seconds)
   Flag: X-Read-From-Primary: true

3. Async wait pattern:
   Client polls: GET /orders/123 (retry 3x with 200ms interval)
   Or: WebSocket/SSE notification when ready

4. Optimistic UI:
   Client assumes success, shows local state immediately
   Background: Eventually consistent with server
```

### 9. CQRS Nə Zaman İstifadə ETMƏMƏLİ
```
Over-engineering olduğu hallar:

- Simple CRUD app (blog, simple inventory)
- Low traffic: 100 req/sec altında
- Small team: Operational complexity > benefit
- Simple business logic: Aggregate-lər yoxdur
- Consistency tələbi yüksəkdir: Read = Write model olmalıdır

CQRS lazım olan hallar:
- Read/Write ratio çox fərqlidir (100:1+)
- Read-lar write model-dən fərqli query-lərə ehtiyac duyur
- Multiple read models lazımdır (API, analytics, search)
- Audit trail / event sourcing tələb olunur
- Domain model kompleksdir (DDD aggregates)
- High scalability: Read cluster independently scale olmalıdır
```

### 10. CQRS Implementation Steps (Practical)

**Step 1: Identify Commands and Queries**
```
Commands: PlaceOrder, CancelOrder, UpdateAddress, ApplyDiscount
Queries: GetOrder, ListOrders, GetUserCart, GetDashboard
```

**Step 2: Separate command/query handlers**
```
app/
  Commands/
    PlaceOrderCommand.php
    PlaceOrderHandler.php
  Queries/
    GetOrderQuery.php
    GetOrderHandler.php
  ReadModels/
    OrderReadModel.php
  Projections/
    OrderProjection.php
```

**Step 3: Event bus for projection updates**
```
PlaceOrderHandler → OrderPlacedEvent → EventBus
EventBus → OrderProjection::onOrderPlaced()
OrderProjection → UPDATE order_views table
```

**Step 4: Read model optimization**
```
CREATE INDEX idx_order_views_user_status ON order_views(user_id, status);
Denormalize: Include user_name in order_views (avoid JOIN)
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Read vs Write ratio nədir?" soruşun
2. CQRS-i "nə zaman" seçdiyinizi əsaslandırın
3. Eventual consistency-nin istifadəçi experience-ına təsirini müzakirə et
4. Multiple read models-i nümunə ilə göstər
5. "CQRS hər sistemə lazım deyil" demək güclü siqnaldır

### Ümumi Namizəd Səhvləri
- "CQRS həmişə istifadə etmək lazımdır" düşüncəsi
- Eventual consistency-nin UX problemlərini qeyd etməmək
- Event sourcing = CQRS hesab etmək (fərqli, birlikdə istifadə olunur)
- Read model-lərin rebuild prosesini bilməmək
- Complexity cost-unu nəzərə almamaq

### Senior vs Architect Fərqi
**Senior**: CQRS pattern-ı tətbiq edir, projection yazır, read/write model-lərini ayırır.

**Architect**: CQRS-in organizational impact-ini qiymətləndirir (ayrı team-lər read/write side üçün), event sourcing ilə kombinasiyada schema evolution planlaşdırır, read model-ların partial failure-ını idarə edir (projection lag monitoring), polyglot persistence (write: PostgreSQL, read: Redis + Elasticsearch + BigQuery) arxitekturasını dizayn edir, eventual consistency-nin SLA ilə uyğunluğunu müəyyən edir.

## Nümunələr

### Tipik Interview Sualı
"Design the data layer for an e-commerce dashboard that needs real-time stats, searchable order history, and the write throughput to handle 10K orders/sec."

### Güclü Cavab
```
CQRS architecture for e-commerce:

Write side (10K orders/sec):
  Command: PlaceOrderCommand
  Handler: Validates, creates order, emits event
  Write DB: PostgreSQL (3 primary shards, normalized)
  Throughput: 10K writes/sec → 3 shards × 3.3K each

  Events via Kafka:
  - order.placed (Kafka: orders.events, partitioned by user_id)

Read side (100K reads/sec):
  Read Model 1: OrderDetailView (PostgreSQL read replica)
    GET /orders/{id}: specific order details
    Table: order_details_view (denormalized)
    Updated: Real-time via projection listener

  Read Model 2: UserOrderListView (PostgreSQL read replica)
    GET /users/{id}/orders: paginated order history
    Table: user_order_list_view (with pagination metadata)
    Index: (user_id, created_at DESC)

  Read Model 3: SearchIndex (Elasticsearch)
    GET /orders?q=...&status=...&date=...
    Full-text search on product names
    Faceted filter: status, date range, price range
    Updated: Kafka consumer → Elasticsearch bulk index

  Read Model 4: DashboardStats (Redis)
    GET /dashboard/stats
    Pre-aggregated: hourly/daily revenue, order count
    TTL: 5 minutes (acceptable staleness)
    Updated: Cron job + event triggers

Projection lag:
  OrderDetailView: < 100ms (real-time projection)
  SearchIndex: < 5s (Elasticsearch bulk ingest)
  DashboardStats: < 5 min (TTL-based)

Consistency handling:
  POST /orders → 202 Accepted + full order data in response
  Client: Uses response data, no immediate GET needed
  Background: Projection updates asynchronously

Scale independently:
  Write: 3 PostgreSQL shards (scale write)
  Read replicas: 5 read replicas (scale read)
  Elasticsearch: 3 data nodes (scale search)
  Redis: Cluster (scale stats)
```

### CQRS Layers Diagram
```
[Client]
   │ POST /orders (Command)      GET /orders/* (Query)
   │                                    │
[Command Handler]              [Query Handler]
   │                                    │
[Order Aggregate]              [Read Model]─────────────────┐
   │ validates                          │                   │
[Write DB]                    ┌─────────┼──────────┐        │
   │                          │         │          │        │
   └──event──► [Kafka]    [Order View] [Search] [Stats]  [Audit]
                   │      (PG Replica)(Elastic) (Redis) (Archive)
                   └──► [Projector Service]
```

## Praktik Tapşırıqlar
- Laravel-də CQRS Command/Query Bus implement edin
- Projection listener Kafka consumer kimi yazın
- Read model rebuild: bütün events-dən sıfırdan projeksiya
- Eventual consistency delay test: POST → immediate GET
- Multiple read models: eyni event, fərqli 3 read model

## Əlaqəli Mövzular
- [18-event-driven-architecture.md](18-event-driven-architecture.md) — Event-driven patterns
- [17-distributed-transactions.md](17-distributed-transactions.md) — Saga with CQRS
- [06-database-selection.md](06-database-selection.md) — Polyglot persistence for read models
- [23-eventual-consistency.md](23-eventual-consistency.md) — CQRS eventual consistency
- [05-caching-strategies.md](05-caching-strategies.md) — Read model caching
