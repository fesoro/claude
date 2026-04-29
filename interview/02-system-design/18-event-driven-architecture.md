# Event-Driven Architecture (Architect ⭐⭐⭐⭐⭐)

## İcmal
Event-Driven Architecture (EDA), sistemin komponentlərinin sinxron sorğular əvəzinə, event-lər (hadisələr) vasitəsilə kommunikasiya etdiyi arxitektura stiilidir. Producer event publish edir, consumer-lər maraqlandıqları event-ləri consume edir. Bu decoupling, service-lərin müstəqil deploy olmasına, elastik scale etməsinə və yüksək availability-yə imkan verir.

## Niyə Vacibdir
Netflix, Uber, LinkedIn — hər böyük platform event-driven arxitektura əsasında qurulub. "Order placed" event-i onlarla downstream service-i trigger edə bilər — real-time inventory update, email notification, analytics, fraud detection, loyalty points — hamısı eyni event-dən. Architect EDA-nın trade-off-larını, event sourcing-i, CQRS ilə birlikdə istifadəsini, eventual consistency management-ini bilməlidir.

## Əsas Anlayışlar

### 1. EDA Əsas Konseptləri

**Event:** Keçmişdə baş vermiş bir hadisədir.
```json
{
  "event_id": "evt_abc123",
  "event_type": "order.placed",
  "aggregate_id": "ord_456",
  "timestamp": "2026-04-26T10:00:00Z",
  "version": 1,
  "payload": {
    "order_id": "ord_456",
    "user_id": "usr_789",
    "items": [{"product_id": "prod_1", "quantity": 2}],
    "total": 199.99
  }
}
```

**Immutability:** Event dəyişdirilmir, yalnız yeni event əlavə olunur.
**Temporal ordering:** Event-lər vaxt sırasına görə.

### 2. EDA vs Request-Response

**Request-Response (Sync):**
```
Order Service → Inventory Service: "Reserve item 123" → Response: "Reserved"
Order Service → Payment Service: "Charge $100" → Response: "Charged"
Order Service → Notification Service: "Send email" → Response: "Sent"

Latency: Hər call gözlənilir (accumulative latency)
Coupling: Order Service hər downstream service-i bilir
Failure: Hər service available olmalıdır
```

**Event-Driven (Async):**
```
Order Service: order.placed event → Kafka
→ Inventory Service: order.placed listens → reserves item
→ Payment Service: order.placed listens → charges
→ Notification Service: order.placed listens → sends email
→ Fraud Service: order.placed listens → analyzes
→ Analytics Service: order.placed listens → updates metrics

Latency: Order Service dərhal cavab qaytarır
Coupling: Order Service yalnız event schema bilir
Failure: Downstream unavailable → event queue-da gözləyir
Scale: Hər consumer müstəqil scale edir
```

### 3. Event Types

**Domain Events:**
```
order.placed, order.confirmed, order.shipped, order.delivered
payment.charged, payment.refunded, payment.failed
user.registered, user.deactivated
product.updated, product.out_of_stock
```

**Integration Events:**
Fərqli context-lər arasında kommunikasiya üçün:
```
order_placed_integration_event (external format)
vs
OrderPlacedDomainEvent (internal rich object)
```

**System Events:**
```
service.started, service.stopped
health.check.failed
threshold.exceeded
```

### 4. Event Schema Design
```json
// Event envelope (bütün event-lər üçün eyni structure)
{
  "spec_version": "1.0",        // CloudEvents standard
  "id": "uuid-v4",              // unique event ID
  "source": "order-service",    // producer identifier
  "type": "com.example.order.placed",  // namespaced type
  "time": "2026-04-26T10:00:00Z",
  "data_content_type": "application/json",
  "schema_url": "https://registry.example.com/schemas/order-placed/v1",
  "data": {
    // payload
  }
}
```

**Schema Registry (Avro, Protobuf, JSON Schema):**
```
Producer: Schema-nı registry-yə qeydiyyat edir
Consumer: Schema-nı registry-dən alır, deserialize edir
Schema evolution: Backward compatible changes only
Breaking change: New schema version (consumers migrated)
```

### 5. Event Streaming Patterns

**Event Notification:**
```
"Order placed" event → Consumer-lər bilir bir şey baş verdi
Consumer: Detallar lazımdırsa → Order Service-ə sorğu atır
Minimal payload: Yalnız ID

order.placed: {order_id: "ord_456"}
→ Payment Service: GET /orders/ord_456 (order detallarını çək)
```

**Event-Carried State Transfer:**
```
Event-də bütün lazımi məlumat var
Consumer: Ayrı sorğu atmır

order.placed: {order_id, items, total, user_id, shipping_address, ...}
→ Payment Service: order detallarını event-dən istifadə edir
```

Trade-off:
- Notification: Küçük event, amma consumer-lər API call edir (tight coupling)
- State transfer: Böyük event, amma consumer-lər müstəqil (loose coupling)

### 6. Event Sourcing
```
State deyil, event-lər saxlanır:

Traditional:
  orders table: {id, status="shipped", updated_at}
  → Tarixi itir

Event Sourcing:
  events table:
    order.created at 10:00
    order.confirmed at 10:05
    order.payment_charged at 10:06
    order.shipped at 11:00

Current state = replay events:
  fold(events) → current state

Benefits:
  - Tam audit trail
  - Time travel: hər anda state-i görmək
  - Event replay (analytics, bug investigation)
  - Business insights from history

Challenges:
  - Eventually consistent read models
  - Snapshots lazımdır (N event-dən sonra state saxla)
  - Schema migration (old events + new schema)
```

### 7. CQRS + Event Sourcing Kombinasiyası
```
Write side:
  Command: PlaceOrderCommand → validate → OrderAggregate
  Aggregate: OrderPlacedEvent, PaymentChargedEvent emit edir
  Events → Event Store (append-only)

Read side:
  Event Store → Projections → Read Models
  Read Model: orders_read (denormalized, query-optimized)
  
  Projection example:
  OrderPlacedEvent → INSERT INTO orders_read (id, status='pending', ...)
  OrderShippedEvent → UPDATE orders_read SET status='shipped', shipped_at=...

API:
  POST /orders → Command → Write side
  GET /orders/123 → Read Model query (not event store)
```

### 8. Event-Driven Choreography vs Orchestration
```
Choreography (events only):
  Order.placed → triggers Inventory (auto)
  Inventory.reserved → triggers Payment (auto)
  Payment.completed → triggers Shipping (auto)
  
  No central coordinator
  Services are autonomous, react to events
  
Orchestration (saga + events):
  Order Saga Orchestrator: explicit workflow
  Sends commands, receives events
  Central visibility into flow
  
Recommendation:
  Simple flows (< 4 steps): Choreography
  Complex flows (branching, compensation): Orchestration
```

### 9. Event-Driven Challenges

**Out-of-order events:**
```
order.shipped arrives before order.confirmed
→ Shipping processor: "confirmed event yoxdur, ne etsin?"

Həll 1: Event ordering (Kafka partition by order_id)
Həll 2: Out-of-order tolerance (store event, process when ready)
Həll 3: Sequence numbers (ignore older events)
```

**Duplicate events:**
```
At-least-once delivery → duplicate events possible
Consumer must be idempotent:
  event_id deduplication (Redis/DB check)
  Conditional update (state machine check)
```

**Event schema evolution:**
```
v1: order.placed {item_id, quantity}
v2: order.placed {items: [{item_id, quantity, unit_price}]}

Breaking change!
Strategy:
  1. Dual-write: Both v1 and v2 events
  2. Consumer migration: update v2 consumers first
  3. Stop v1 after all consumers migrated
  4. Additive changes (new optional fields): backward compatible
```

**Debugging distributed flows:**
```
Correlation ID:
  Request-dən event-ə axan unique ID
  order_id + correlation_id → trace bütün event chain
  Distributed tracing: Jaeger, Zipkin
  
Event log:
  All events → centralized log (Elasticsearch)
  "order_id:ord_456" → bütün events chronologically
```

### 10. Event Store vs Message Broker

**Message Broker (Kafka, RabbitMQ):**
```
Events → broker → consumers
Retention: limited (7 days Kafka default)
Replay: Kafka offset reset, RabbitMQ yox
Read model rebuild: limited by retention
```

**Event Store (EventStoreDB, Axon):**
```
Events → event store (append-only database)
Retention: unlimited (immutable, compressed)
Replay: Full replay from beginning
Read model rebuild: any time, any projection
```

**Kombinasiya:**
```
Event Store → Event Bus (Kafka) → Consumers
Event Store: long-term storage + replay source
Kafka: real-time event streaming
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Bu addım sync mi async mi olmalıdır?" sualını soruşun
2. EDA-nın decoupling faydasını konkret göstər
3. Event schema design-ı müzakirə et (schema registry)
4. Eventual consistency trade-off-unu izah et
5. Duplicate/out-of-order event handling-i qeyd et

### Ümumi Namizəd Səhvləri
- Event-leri sadəcə "notification" kimi düşünmək
- Event schema evolution-u nəzərə almamaq
- Duplicate event handling-i unutmaq
- Event sourcing = event-driven fərqini bilməmək
- Debugging/tracing-i nəzərə almamaq

### Senior vs Architect Fərqi
**Senior**: Event-driven patternları tətbiq edir, Kafka producer/consumer yazır, at-least-once idempotency idarə edir.

**Architect**: EDA-nın organizational alignment-ini dizayn edir (domain event ownership), event schema governance (schema registry + compatibility rules), event mesh (multi-cluster, cross-cloud event routing), event-driven security (event auth, schema validation), observability (distributed event tracing, event flow visualization), strangler fig pattern ilə monolitdən EDA-ya migration strategiyası.

## Nümunələr

### Tipik Interview Sualı
"Design the order processing system for an e-commerce platform where placing an order triggers inventory reservation, payment, email notifications, fraud check, and loyalty points — all independently."

### Güclü Cavab
```
Event-driven order processing:

Core event: order.placed

Order Service (Producer):
  1. User → POST /orders
  2. Validate input
  3. BEGIN TX:
     - INSERT orders (status=PENDING)
     - INSERT outbox (order.placed event)
  4. COMMIT TX
  5. Return 202 Accepted + order_id
  
  Outbox relay: Kafka → order.placed

Consumers (all independent):

Inventory Service:
  Subscribes: order.placed
  Action: Reserve item
  Publishes: inventory.reserved / inventory.failed

Payment Service:
  Subscribes: inventory.reserved
  Action: Charge payment
  Publishes: payment.completed / payment.failed

Email Service:
  Subscribes: order.placed (immediate confirmation)
              payment.completed (receipt)
              order.shipped (tracking)
  Action: Send email

Fraud Service:
  Subscribes: order.placed
  Action: Async fraud analysis
  Publishes: fraud.approved / fraud.flagged

Loyalty Service:
  Subscribes: payment.completed
  Action: Add loyalty points
  Publishes: loyalty.credited

Event flow:
  order.placed
    → Inventory reserve (high priority)
    → Email "order received" (immediate)
    → Fraud analysis (async)
  inventory.reserved
    → Payment charge
  payment.completed
    → Email "payment confirmed"
    → Loyalty points
  payment.failed → inventory.released
  
Kafka topics:
  orders.events         (order lifecycle)
  inventory.events      (inventory changes)
  payment.events        (payment events)
  notifications.commands (email/SMS to send)

Schema Registry: Confluent Schema Registry
  Avro schemas for all events
  Backward compatibility enforced
  
Deduplication:
  Each consumer: event_id → Redis SET NX (TTL 7 days)
  Processed event: skip silently

Monitoring:
  Consumer lag per topic (Grafana)
  Event processing latency per step
  Fraud flag rate
  Payment failure rate
```

### Event Flow Diagram
```
[Order API]
    │ order.placed
    ▼
[Kafka: orders.events]
    │
    ├──► [Inventory Svc] → inventory.reserved → [Payment Svc] → payment.completed
    │                                                               │
    ├──► [Email Svc] ◄────────────────────────────────────────────┤
    │                                                               │
    ├──► [Fraud Svc] ──fraud.flagged──► [Order Svc: cancel]       │
    │                                                               │
    └──► [Loyalty Svc] ◄────────────────────────────────────────────┘
```

## Praktik Tapşırıqlar
- Event-driven order saga Kafka ilə implement edin
- Schema Registry ilə Avro serialization qurun
- Out-of-order event handling test edin
- Event sourcing toy project: bank account (balance from events)
- Distributed tracing: correlation ID bütün event chain-dən axır

## Əlaqəli Mövzular
- [08-message-queues.md](08-message-queues.md) — Kafka event streaming
- [17-distributed-transactions.md](17-distributed-transactions.md) — Saga with events
- [19-cqrs-practice.md](19-cqrs-practice.md) — CQRS + Event Sourcing
- [25-outbox-pattern.md](25-outbox-pattern.md) — Reliable event publishing
- [13-idempotency-design.md](13-idempotency-design.md) — Idempotent event consumers
