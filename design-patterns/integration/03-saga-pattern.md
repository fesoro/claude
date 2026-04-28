# Saga Pattern (Senior ⭐⭐⭐)

## İcmal

Saga pattern — fərqli servislərdə atomik görünən distributed transaction-ı ardıcıl lokal transaction-larla həyata keçirir. Hər addım öz DB-sinə yazır; addım uğursuz olduqda əvvəlki addımları geri alan compensating transaction-lar icra edilir. 2PC-nin bloklaşdırıcı protokolunun əvəzinə eventual consistency ilə yüksək availability təmin edir.

## Niyə Vacibdir

Microservice-lərdə hər servis öz DB-sinə sahibdir — klassik `BEGIN TRANSACTION ... COMMIT` birdən çox servisin DB-sini əhatə edə bilmir. E-commerce sifariş: Order → Payment → Inventory → Delivery — dördü ayrı servisdir. Biri uğursuz olduqda əvvəlkilər geri alınmalıdır. 2PC bu miqyasda impraktikdir (lock-lar, SPOF). Saga hər addımı müstəqil, idempotent, compensation-a hazır edir.

## Əsas Anlayışlar

- **Local transaction**: hər Saga addımı yalnız öz servisinin DB-sinə yazır
- **Compensating transaction**: uğursuzluq halında əvvəlki addımı geri alan yeni əməliyyat (DB rollback deyil — yeni write)
- **Choreography**: mərkəzi koordinator yoxdur; servislər event-lərə reaksiya verir
- **Orchestration**: mərkəzi Saga orchestrator hər addımı çağırır, state-i izləyir
- **Idempotency**: eyni Saga addımı bir neçə dəfə icra edilsə eyni nəticə olmalıdır
- **Pivot point**: bu nöqtədən sonra kompensasiya edilə bilmir, yalnız retry mümkündür

Hər addımın "geri alınması" üçün compensating transaction lazımdır:

```
Addım                    Compensating
──────────────────────────────────────────
createOrder()         →  cancelOrder()
chargeCard()          →  refundCard()
reserveStock()        →  releaseStock()
scheduleDelivery()    →  cancelDelivery()
sendConfirmationEmail →  sendCancellationEmail (mümkün deyil!)
```

## Praktik Baxış

- **Real istifadə**: e-commerce sifariş (Order + Payment + Inventory + Delivery), travel booking (uçuş + otel + transfer), bank köçürmə (debit + credit fərqli sistemlərdə)
- **Trade-off-lar**: yüksək availability, deadlock yoxdur, mikroservislərə uyğundur; lakin eventual consistency (dirty reads mümkündür), hər addım üçün compensation yazılmalıdır, debug çətin (xüsusilə choreography-də)
- **İstifadə etməmək**: tək servis daxilindəki əməliyyatlar üçün; strong consistency kritik olan yerlər üçün (banking real-time balance); sadə workflow üçün (overkill)
- **Common mistakes**: compensation-ı unutmaq; Saga state-ini yalnız yaddaşda saxlamaq; addımları idempotent etməmək; çox uzun Saga zənciri (15+ addım — debugging kabusuna çevrilir)

## Anti-Pattern Nə Zaman Olur?

**Compensation logic olmadan Saga:**
Hər forward addım üçün compensating transaction planlanmamışdırsa, uğursuzluq halında sistem yarımçıq vəziyyətdə qalır. `createOrder()` var, `cancelOrder()` yoxdur → manual müdaxilə lazım olur.

**Çox uzun Saga zənciri:**
15+ addımlı Saga həm debug etmək çətindir, həm də "dirty read" pencərəsi çox genişlənir. 5+ addım varsa Orchestration seç; 10+ addım varsa workflow-u kiçik Saga-lara böl.

**Saga state-ini yalnız yaddaşda saxlamaq:**
Orchestrator restart olduqda bütün aktiv Saga-lar itirilir. DB-də `saga_states` cədvəli olmadan durability yoxdur.

## Nümunələr

### Ümumi Nümunə

```
Normal axın:
T1 → T2 → T3 → T4 → Uğur ✅

Uğursuzluq (T3-də):
T1 → T2 → T3(FAIL) → C2 → C1 → Geri alındı ⬅️

C1, C2 — T1, T2-nin compensating transaction-larıdır
```

### Choreography vs Orchestration

**Choreography** — mərkəzi koordinator yoxdur:
```
OrderService → [OrderPlaced event]
                    ↓
PaymentService ← dinləyir → [PaymentProcessed/Failed event]
                                 ↓
InventoryService ← dinləyir → [StockReserved event]
```

**Orchestration** — mərkəzi Saga orchestrator:
```
SagaOrchestrator → OrderService.createOrder()
SagaOrchestrator → PaymentService.chargeCard()
SagaOrchestrator → InventoryService.reserveStock() [FAIL]
SagaOrchestrator → PaymentService.refundCard()     [compensation]
SagaOrchestrator → OrderService.cancelOrder()      [compensation]
```

### PHP/Laravel Nümunəsi

**Orchestration Saga — tam implementasiya:**

```php
<?php

// Saga addımı interface
interface SagaStep
{
    public function execute(SagaContext $context): void;
    public function compensate(SagaContext $context): void;
    public function name(): string;
}

// Context — addımlar arasında data ötürmək üçün
class SagaContext
{
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}

// Orchestrator
class SagaOrchestrator
{
    private array $executedSteps = [];

    public function __construct(
        private array $steps,
        private SagaStateRepository $stateRepo
    ) {}

    public function execute(SagaContext $context): bool
    {
        $this->executedSteps = [];

        foreach ($this->steps as $step) {
            try {
                $step->execute($context);
                $this->executedSteps[] = $step;
                $this->stateRepo->recordStep($step->name(), 'completed');
            } catch (\Exception $e) {
                Log::error("Saga addımı uğursuz: {$step->name()}: {$e->getMessage()}");
                $this->compensate($context);
                return false;
            }
        }

        return true;
    }

    private function compensate(SagaContext $context): void
    {
        // Tərsi sıra ilə compensate et
        foreach (array_reverse($this->executedSteps) as $step) {
            try {
                $step->compensate($context);
                $this->stateRepo->recordStep($step->name(), 'compensated');
            } catch (\Exception $e) {
                Log::critical("Compensation uğursuz: {$step->name()}: {$e->getMessage()}");
                // Compensation uğursuz olarsa, manual müdaxilə lazımdır
            }
        }
    }
}

// Konkret addımlar
class CreateOrderStep implements SagaStep
{
    public function __construct(private OrderService $orderService) {}

    public function execute(SagaContext $context): void
    {
        $order = $this->orderService->create($context->get('orderData'));
        $context->set('orderId', $order->id);
    }

    public function compensate(SagaContext $context): void
    {
        $this->orderService->cancel($context->get('orderId'));
    }

    public function name(): string { return 'create_order'; }
}

class ChargePaymentStep implements SagaStep
{
    public function __construct(private PaymentService $paymentService) {}

    public function execute(SagaContext $context): void
    {
        // WHY: idempotency key — eyni addım iki dəfə icra edilsə dublikat charge olmur
        $idempotencyKey = "saga-{$context->get('sagaId')}-payment";

        if ($existing = $this->paymentService->findByIdempotencyKey($idempotencyKey)) {
            $context->set('chargeId', $existing->id);
            return;
        }

        $charge = $this->paymentService->charge(
            $context->get('paymentInfo'),
            $context->get('amount'),
            $idempotencyKey
        );
        $context->set('chargeId', $charge->id);
    }

    public function compensate(SagaContext $context): void
    {
        if ($chargeId = $context->get('chargeId')) {
            $this->paymentService->refund($chargeId);
        }
    }

    public function name(): string { return 'charge_payment'; }
}

// İstifadə
class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $command): void
    {
        $context = new SagaContext();
        $context->set('orderData', $command->orderData());
        $context->set('paymentInfo', $command->paymentInfo());
        $context->set('amount', $command->amount());

        $saga = new SagaOrchestrator([
            new CreateOrderStep($this->orderService),
            new ChargePaymentStep($this->paymentService),
            new ReserveInventoryStep($this->inventoryService),
            new ScheduleDeliveryStep($this->deliveryService),
        ], $this->stateRepo);

        if (!$saga->execute($context)) {
            throw new OrderPlacementFailedException('Sifariş yerləşdirilə bilmədi');
        }
    }
}
```

**Choreography — Event-driven Saga:**

```php
<?php

// Order Service — event publish edir
class OrderService
{
    public function createOrder(array $data): Order
    {
        $order = Order::create($data);

        // PaymentService dinləyir
        event(new OrderCreated($order->id, $order->amount, $order->payment_info));

        return $order;
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        Order::where('id', $event->orderId)->update(['status' => 'cancelled']);
        event(new OrderCancelled($event->orderId));
    }
}

// Payment Service — event dinləyir, öz işini görür
class PaymentListener
{
    public function handle(OrderCreated $event): void
    {
        try {
            $payment = $this->paymentGateway->charge($event->amount, $event->paymentInfo);
            event(new PaymentProcessed($event->orderId, $payment->id));
        } catch (\Exception $e) {
            event(new PaymentFailed($event->orderId, $e->getMessage()));
        }
    }
}
```

**Saga state-ini persist etmək:**

```php
<?php

// CREATE TABLE saga_states (
//   saga_id     CHAR(36) PRIMARY KEY,
//   saga_type   VARCHAR(100),
//   current_step VARCHAR(100),
//   status      ENUM('started','compensating','completed','failed'),
//   context     JSON,
//   updated_at  TIMESTAMP
// );

class PersistentSagaOrchestrator extends SagaOrchestrator
{
    // Hər addımdan sonra state-i persist et
    // Restart olduqda kəsildiyi yerdən davam et
    private function saveState(string $stepName, string $status): void
    {
        DB::table('saga_states')->updateOrInsert(
            ['saga_id' => $this->sagaId],
            [
                'saga_type'    => static::class,
                'current_step' => $stepName,
                'status'       => $status,
                'context'      => json_encode($this->context->toArray()),
                'updated_at'   => now(),
            ]
        );
    }
}
```

## Praktik Tapşırıqlar

1. Orchestration Saga yazın: `CreateOrder → ChargePayment → ReserveInventory` — 3 addım, hər addımın compensation-ı; `ChargePaymentStep` uğursuz olduqda `cancelOrder()` və `refund()` çağrılsın
2. Choreography Saga yazın: `OrderCreated` event → `PaymentService` → `PaymentProcessed/Failed` event → `InventoryService`; event-ləri Laravel Event ilə test edin
3. Idempotency əlavə edin: `ChargePaymentStep`-ə `idempotency_key` əlavə edin; eyni step iki dəfə çağrılsa ikinci çağrı skip etsin; PHPUnit test yazın
4. `saga_states` cədvəli yaradın; orchestrator hər addımdan sonra state-i yazsın; restart simulyasiyası: orchestrator `kill -9` ilə dayandırın, restart etdikdə kəsildiyi yerdən davam etsin

## Əlaqəli Mövzular

- [Outbox Pattern](04-outbox-pattern.md) — Saga addımları outbox ilə reliable event publish edir
- [Two-Phase Commit](05-two-phase-commit.md) — Saga-nın alternative: strong consistency, amma blocking
- [Choreography vs Orchestration](11-choreography-vs-orchestration.md) — Saga-nın iki koordinasiya üsulu
- [CQRS](01-cqrs.md) — Saga orchestrator command-ları dispatch edir
