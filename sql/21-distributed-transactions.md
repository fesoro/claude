# Distributed Transactions & Saga Pattern

## Problem: Microservice-lerde Transaction

Monolith-de bir database var, `DB::transaction()` ile her seyi wrap edirik. Amma microservice-lerde **her servisin oz database-i** var. Bir transaction birden cox servisi ehate edende ne ederik?

**Misal:** E-commerce sifaris:
1. Order Service - sifaris yaradir
2. Payment Service - odenis edir
3. Inventory Service - stoku azaldir
4. Notification Service - email gonderir

Eger payment ugurlu oldu amma inventory-de stok yoxdursa?

## Two-Phase Commit (2PC)

Distributed transaction-lerin klassik helli. Bir **coordinator** butun istirakci node-lara sual verir.

### Nece isleyir?

**Phase 1 - Prepare (Voting):**
```
Coordinator → Node A: "Hazirsanmi?"  → Node A: "Beli, hazir"
Coordinator → Node B: "Hazirsanmi?"  → Node B: "Beli, hazir"
Coordinator → Node C: "Hazirsanmi?"  → Node C: "Beli, hazir"
```

**Phase 2 - Commit:**
```
Coordinator → All: "COMMIT et"
Butun node-lar commit edir
```

**Eger biri "Xeyr" dese:**
```
Coordinator → Node A: "Hazirsanmi?"  → Node A: "Beli"
Coordinator → Node B: "Hazirsanmi?"  → Node B: "XEY, problem var"
Coordinator → All: "ROLLBACK et"
```

### SQL-de 2PC

```sql
-- MySQL-de XA Transactions (2PC destekleyir)

-- Node 1: Order Database
XA START 'order-txn-123';
INSERT INTO orders (id, user_id, total) VALUES (1, 42, 150.00);
XA END 'order-txn-123';
XA PREPARE 'order-txn-123';

-- Node 2: Payment Database
XA START 'payment-txn-123';
INSERT INTO payments (order_id, amount, status) VALUES (1, 150.00, 'completed');
XA END 'payment-txn-123';
XA PREPARE 'payment-txn-123';

-- Her ikisi PREPARE ugurlu oldusa:
XA COMMIT 'order-txn-123';
XA COMMIT 'payment-txn-123';

-- Biri ugursuz oldusa:
XA ROLLBACK 'order-txn-123';
XA ROLLBACK 'payment-txn-123';
```

### 2PC-nin Problemleri

| Problem | Izah |
|---------|------|
| **Blocking** | Coordinator crash olsa, butun node-lar lock-da qalir |
| **Single Point of Failure** | Coordinator tek noqutedir |
| **Performance** | Butun node-larin cavab vermesini gozleyir - yavas |
| **Scalability** | Cox node olduqda meqsede catmaq cetinlesir |

> **Praktikada:** 2PC cox nadir istifade olunur. Evezine **Saga Pattern** tercih edilir.

## Saga Pattern

Long-running transaction-leri bir nece kicik, lokal transaction-lere bolur. Her biri ugursuz olarsa, **compensating transaction** (geri qaytarma emeliyyati) icra olunur.

### Saga Nece Isleyir?

```
Ugurlu axin:
T1 → T2 → T3 → T4 (Tamam!)

Ugursuz axin (T3 ugursuz oldu):
T1 → T2 → T3(fail) → C2 → C1 (Geri qaytarildi!)

T = Transaction, C = Compensating Transaction
```

### Choreography vs Orchestration

#### 1. Choreography (Event-Driven)

Her servis oz isini gorur ve event publish edir. Merkezi controller yoxdur.

```
Order Service: Sifaris yaratdi → "OrderCreated" event publish etdi
     ↓
Payment Service: Eventi esitdi → Odenis etdi → "PaymentCompleted" publish etdi
     ↓
Inventory Service: Eventi esitdi → Stoku azaltdi → "StockReserved" publish etdi
     ↓
Notification Service: Eventi esitdi → Email gonderdi
```

**Compensation (Payment ugursuz olsa):**
```
Payment Service: Odenis ugursuz → "PaymentFailed" publish etdi
     ↓
Order Service: Eventi esitdi → Sifarisi legv etdi
```

```php
// Laravel - Choreography ile Saga

// Order Service
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create([
            'user_id' => $request->user_id,
            'total' => $request->total,
            'status' => 'pending',
        ]);

        // Event publish et (RabbitMQ/Kafka vasitesile)
        event(new OrderCreated($order));

        return response()->json($order, 201);
    }
}

// Payment Service - OrderCreated eventini dinleyir
class ProcessPaymentListener
{
    public function handle(OrderCreated $event)
    {
        try {
            $payment = PaymentGateway::charge(
                $event->order->user_id,
                $event->order->total
            );

            event(new PaymentCompleted($event->order->id, $payment->id));
        } catch (\Exception $e) {
            event(new PaymentFailed($event->order->id, $e->getMessage()));
        }
    }
}

// Order Service - PaymentFailed eventini dinleyir (Compensation)
class CancelOrderListener
{
    public function handle(PaymentFailed $event)
    {
        $order = Order::findOrFail($event->orderId);
        $order->update(['status' => 'cancelled']);

        // Eger stock artiq reserve olunubsa, onu da geri qaytar
        event(new OrderCancelled($order));
    }
}
```

#### 2. Orchestration (Central Coordinator)

Bir **Saga Orchestrator** butun prosesi idare edir.

```php
// Saga Orchestrator
class OrderSagaOrchestrator
{
    private array $completedSteps = [];

    public function execute(array $orderData): bool
    {
        try {
            // Step 1: Sifaris yarat
            $order = $this->createOrder($orderData);
            $this->completedSteps[] = 'order';

            // Step 2: Odenis et
            $payment = $this->processPayment($order);
            $this->completedSteps[] = 'payment';

            // Step 3: Stoku reserve et
            $this->reserveStock($order);
            $this->completedSteps[] = 'stock';

            // Step 4: Bildirisi gonder
            $this->sendNotification($order);

            return true;
        } catch (\Exception $e) {
            $this->compensate($order ?? null);
            return false;
        }
    }

    private function compensate(?Order $order): void
    {
        // Tamamlanmis addimlar ters sirada geri qaytarilir
        $steps = array_reverse($this->completedSteps);

        foreach ($steps as $step) {
            match ($step) {
                'stock'   => $this->releaseStock($order),
                'payment' => $this->refundPayment($order),
                'order'   => $this->cancelOrder($order),
            };
        }
    }

    private function createOrder(array $data): Order
    {
        return Order::create([...$data, 'status' => 'pending']);
    }

    private function processPayment(Order $order): Payment
    {
        // Payment service-e HTTP call
        $response = Http::post('http://payment-service/api/payments', [
            'order_id' => $order->id,
            'amount' => $order->total,
        ]);

        if ($response->failed()) {
            throw new PaymentFailedException($response->body());
        }

        return new Payment($response->json());
    }

    // Compensation methods
    private function cancelOrder(Order $order): void
    {
        $order->update(['status' => 'cancelled']);
    }

    private function refundPayment(Order $order): void
    {
        Http::post("http://payment-service/api/payments/{$order->id}/refund");
    }

    private function releaseStock(Order $order): void
    {
        Http::post("http://inventory-service/api/stock/release", [
            'order_id' => $order->id,
        ]);
    }
}
```

### Choreography vs Orchestration Muqayise

| Xususiyyet | Choreography | Orchestration |
|------------|-------------|---------------|
| **Coupling** | Loose (asili deyil) | Orchestrator-a baglidir |
| **Complexity** | Basit saga-lar ucun yaxsi | Complex saga-lar ucun yaxsi |
| **Visibility** | Axini gormek cetindir | Merkezi yerden gorunur |
| **Single Point of Failure** | Yoxdur | Orchestrator |
| **Debug** | Cetindir | Asandir |

## Outbox Pattern

Event publish ederken **database write + event publish** atomik olmalidir. Eger database yazildi amma event publish olmadisa?

```php
// PROBLEM: Atomik deyil!
DB::transaction(function () use ($order) {
    $order->save();  // Database-e yazildi
});
event(new OrderCreated($order));  // Bu ugursuz ola biler!

// HELLI: Outbox Pattern
DB::transaction(function () use ($order) {
    $order->save();

    // Eyni transaction-da outbox table-a yaz
    DB::table('outbox_messages')->insert([
        'aggregate_type' => 'Order',
        'aggregate_id' => $order->id,
        'event_type' => 'OrderCreated',
        'payload' => json_encode($order->toArray()),
        'created_at' => now(),
        'published_at' => null,  // Hele publish olunmayib
    ]);
});
```

```sql
-- Outbox table
CREATE TABLE outbox_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    aggregate_type VARCHAR(100) NOT NULL,
    aggregate_id BIGINT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    INDEX idx_unpublished (published_at, created_at)
);
```

```php
// Outbox Relay - cron/worker ile isleyir, publish olunmamis mesajlari gonderir
class OutboxRelay extends Command
{
    protected $signature = 'outbox:relay';

    public function handle()
    {
        $messages = DB::table('outbox_messages')
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        foreach ($messages as $message) {
            // Message broker-e gonder (RabbitMQ, Kafka, etc.)
            $this->publishToMessageBroker($message);

            DB::table('outbox_messages')
                ->where('id', $message->id)
                ->update(['published_at' => now()]);
        }
    }
}
```

## Idempotency (Tekrar Icra Edilebilirlik)

Distributed system-lerde eyni mesaj bir nece defe gele biler. Emeliyyat **idempotent** olmalidir - nece defe icra olunsa da netice eyni olmalidir.

```sql
-- Idempotency key table
CREATE TABLE processed_events (
    event_id VARCHAR(36) PRIMARY KEY,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

```php
class ProcessPaymentListener
{
    public function handle(OrderCreated $event)
    {
        // Artiq icra olunub?
        $exists = DB::table('processed_events')
            ->where('event_id', $event->eventId)
            ->exists();

        if ($exists) {
            Log::info("Event artiq islendi: {$event->eventId}");
            return;
        }

        DB::transaction(function () use ($event) {
            // Odenis et
            PaymentGateway::charge($event->order->total);

            // Event-i islenmis kimi qeyd et
            DB::table('processed_events')->insert([
                'event_id' => $event->eventId,
            ]);
        });
    }
}
```

## Interview Suallari

1. **2PC ile Saga Pattern arasinda ferq nedir?**
   - 2PC: Distributed lock, blocking, strong consistency. Saga: Local transactions + compensation, eventual consistency.

2. **Saga Pattern-de compensation nedir?**
   - Her ugurlu addimi geri qaytaran emeliyyatdir. Meselen: payment → refund, stock reserve → stock release.

3. **Choreography ile Orchestration hansi halda tercih edilir?**
   - 2-3 servis → Choreography. 4+ servis ve ya complex logic → Orchestration.

4. **Outbox Pattern niye lazimdir?**
   - Database write ve event publish atomik olmalidir. Outbox table ile her ikisi eyni transaction-da yazilir.

5. **Idempotency niye vacibdir distributed system-lerde?**
   - Network retry, message broker redelivery sebebile eyni mesaj bir nece defe gele biler. Idempotent olmasa, odenis 2 defe alinir.

6. **Saga Pattern-in dezavantajlari?**
   - Eventual consistency (ani tutarlilik yoxdur), compensation logic yazmaq lazimdir, debug cetindir.
