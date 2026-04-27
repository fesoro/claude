# Saga Pattern (Architect ⭐⭐⭐⭐⭐)

## İcmal
Saga Pattern — microservices arxitekturasında distributed transactions-ı idarə etmək üçün istifadə olunan pattern-dir. Hər service öz local transaksiyasını yerinə yetirir, uğursuzluq halında compensating transaction-larla geri qaytarılır. İki növ var: Choreography (event-based) və Orchestration (central coordinator). ACID garantiyası olmayan mühitdə eventual consistency-ni idarə etmək üçün vacibdir.

## Niyə Vacibdir
Microservices-də klassik database transaction mümkün deyil — hər service öz DB-sinə malikdir. 2PC (Two-Phase Commit) distributed lock-lar yaradır, availability azalır. Saga bu problemi compensating transaction-larla həll edir. E-commerce-də sifariş, ödəniş, inventory rezervasiyası fərqli service-lərdədir — bunları koordinasiya etmək Saga ilə mümkün olur. Bu mövzunu bilmək distributed systems-də mürəkkəb transaksiya problemlərini həll edə biləcəyinizi göstərir.

## Əsas Anlayışlar

- **Local Transaction**: Hər microservice-in öz DB-sindəki atomic əməliyyat
- **Compensating Transaction**: Əvvəlki transaksiya uğursuz olduqda onu "geri qaytaran" əks əməliyyat — məsələn, `CancelReservation` ← `Reserve` kompensasiyası
- **Choreography Saga**: Mərkəzi koordinator yoxdur — hər service öz event-ini publish edir, digər service-lər subscribe olur
- **Orchestration Saga**: Mərkəzi Saga Orchestrator var — bütün axışı o idarə edir, hər step-i o başladır
- **Choreography üstünlüyü**: Loose coupling, simple service-lər, single point of failure yoxdur
- **Choreography çatışmazlığı**: Mürəkkəb axışı izləmək çətin, debugging çətin, cycle riski
- **Orchestration üstünlüyü**: Axışı izləmək asandır, error handling mərkəzidir, testing asandır
- **Orchestration çatışmazlığı**: God service riski — orchestrator çox məntiq saxlayır, single point of failure
- **Idempotency**: Eyni event iki dəfə gəlsə eyni nəticə — at-least-once delivery zamanı vacib
- **Semantic rollback**: Bəzi əməliyyatları texniki cəhətdən geri qaytarmaq mümkün deyil (email göndərildi) — kompensasiya məntiqi ilə idarə olunur
- **Saga execution log**: Orchestrator saga-nın cari vəziyyətini saxlayır — restart edilə bilər
- **Pivot transaction**: Saqa-nın geri qayıtma nöqtəsi — bu uğurlu olsa sonrakılar da uğurlu olmalıdır
- **Process Manager vs Saga**: Process Manager state machine, Saga bir workflow — oxşardırlar
- **Outbox Pattern**: Event publish etmə ilə DB yazmanı atomic etmək üçün — Saga-da vacib

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Saga mövzusunda əvvəlcə problemi izah edin (distributed ACID mümkün deyil), sonra Choreography vs Orchestration müqayisəsi. Kompensasiya nümunəsi verin. Idempotency-ni unutmayın.

**Follow-up suallar:**
- "Kompensasiya da uğursuz olsa nə baş verir?"
- "Saga-nın 2PC-dən üstünlüyü nədir?"
- "Choreography vs Orchestration nə vaxt seçilir?"
- "Idempotency-ni necə təmin edirsiniz?"

**Ümumi səhvlər:**
- Saga = rollback düşünmək — Saga semantic compensation edir, klassik rollback deyil
- Compensating transaction-ı idempotent etməmək
- Orchestrator-a biznes məntiq toplamaq — orchestrator yalnız axışı idarə etməlidir
- Saga execution log-u saxlamamaq — restart edilə bilmir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab kompensasiya zəncirinin tərsinə işlədiyini (LIFO order), Outbox Pattern-in rolunu, konkret e-commerce nümunəsini izah edə bilir.

## Nümunələr

### Tipik Interview Sualı
"Microservices-də sifariş verilməsi prosesini (Order → Payment → Inventory) transaction ilə necə idarə edərdiniz?"

### Güclü Cavab
"Hər üç service ayrı DB-dədir, klassik ACID transaction mümkün deyil. Saga Pattern istifadə edərdim. Orchestration variantını seçərdim — çünki axış mürəkkəbdir, debug etmək asandır. OrderSaga orkestratorunu yaradardım: 1) Order yaratır, 2) Payment Service-ə ödəniş sorğusu göndərir, 3) ödəniş uğurlu olsa Inventory Service-ə rezervasiya sorğusu göndərir. Hər hansı addım uğursuz olsa compensating transaction-lar əks istiqamətdə işlədilir: inventory cancel → payment refund → order cancel. Hər event idempotent-dir — eyni event iki dəfə gəlsə problem olmur."

### Kod / Konfiqurasiya Nümunəsi

```php
// ============================================================
// ORCHESTRATION SAGA
// ============================================================

// Saga States
enum OrderSagaState: string
{
    case STARTED            = 'started';
    case ORDER_CREATED      = 'order_created';
    case PAYMENT_CHARGED    = 'payment_charged';
    case INVENTORY_RESERVED = 'inventory_reserved';
    case COMPLETED          = 'completed';
    case COMPENSATING       = 'compensating';
    case PAYMENT_REFUNDED   = 'payment_refunded';
    case ORDER_CANCELLED    = 'order_cancelled';
    case FAILED             = 'failed';
}

// Saga Execution Log — state machine
class OrderSagaExecution extends Model
{
    protected $fillable = ['saga_id', 'order_id', 'state', 'payload', 'error'];
    protected $casts    = ['payload' => 'array', 'state' => OrderSagaState::class];
}

// Saga Orchestrator
class PlaceOrderSaga
{
    public function __construct(
        private OrderServiceClient $orderService,
        private PaymentServiceClient $paymentService,
        private InventoryServiceClient $inventoryService,
        private OrderSagaExecution $execution
    ) {}

    public function start(PlaceOrderCommand $command): string
    {
        $sagaId = (string) Str::uuid();

        $this->execution->create([
            'saga_id'  => $sagaId,
            'order_id' => $command->orderId,
            'state'    => OrderSagaState::STARTED,
            'payload'  => $command->toArray(),
        ]);

        // Step 1: Order yarat
        $this->step1_createOrder($sagaId, $command);

        return $sagaId;
    }

    private function step1_createOrder(string $sagaId, PlaceOrderCommand $command): void
    {
        try {
            $orderId = $this->orderService->create([
                'customer_id' => $command->customerId,
                'items'       => $command->items,
            ]);

            $this->transition($sagaId, OrderSagaState::ORDER_CREATED, [
                'order_id' => $orderId,
            ]);

            $this->step2_chargePayment($sagaId, $command, $orderId);

        } catch (\Exception $e) {
            $this->fail($sagaId, 'order_creation_failed', $e->getMessage());
        }
    }

    private function step2_chargePayment(
        string $sagaId,
        PlaceOrderCommand $command,
        string $orderId
    ): void {
        try {
            $chargeId = $this->paymentService->charge([
                'order_id'       => $orderId,
                'amount_cents'   => $command->totalCents,
                'payment_method' => $command->paymentMethod,
                'idempotency_key' => $sagaId . '_charge',  // idempotency
            ]);

            $this->transition($sagaId, OrderSagaState::PAYMENT_CHARGED, [
                'charge_id' => $chargeId,
            ]);

            $this->step3_reserveInventory($sagaId, $command, $orderId, $chargeId);

        } catch (\Exception $e) {
            // Ödəniş uğursuz → order-ı ləğv et
            $this->compensate_cancelOrder($sagaId, $orderId);
        }
    }

    private function step3_reserveInventory(
        string $sagaId,
        PlaceOrderCommand $command,
        string $orderId,
        string $chargeId
    ): void {
        try {
            $this->inventoryService->reserve([
                'order_id'        => $orderId,
                'items'           => $command->items,
                'idempotency_key' => $sagaId . '_reserve',
            ]);

            $this->transition($sagaId, OrderSagaState::INVENTORY_RESERVED, []);
            $this->transition($sagaId, OrderSagaState::COMPLETED, []);

        } catch (\Exception $e) {
            // Inventory uğursuz → ödənişi iade et, sifarişi ləğv et
            $this->compensate_refundPayment($sagaId, $chargeId, $orderId);
        }
    }

    // Compensating Transactions — LIFO order
    private function compensate_refundPayment(
        string $sagaId,
        string $chargeId,
        string $orderId
    ): void {
        $this->transition($sagaId, OrderSagaState::COMPENSATING, []);

        try {
            $this->paymentService->refund([
                'charge_id'       => $chargeId,
                'idempotency_key' => $sagaId . '_refund',
            ]);
            $this->transition($sagaId, OrderSagaState::PAYMENT_REFUNDED, []);
        } catch (\Exception $e) {
            Log::critical('Saga compensation failed - manual intervention required', [
                'saga_id'  => $sagaId,
                'step'     => 'refund',
                'error'    => $e->getMessage(),
            ]);
            // Dead letter queue-ya göndər, ops team notification
        }

        $this->compensate_cancelOrder($sagaId, $orderId);
    }

    private function compensate_cancelOrder(string $sagaId, string $orderId): void
    {
        try {
            $this->orderService->cancel([
                'order_id'        => $orderId,
                'reason'          => 'saga_compensation',
                'idempotency_key' => $sagaId . '_cancel',
            ]);
            $this->transition($sagaId, OrderSagaState::ORDER_CANCELLED, []);
        } catch (\Exception $e) {
            Log::critical('Cannot cancel order during compensation', [
                'saga_id'  => $sagaId,
                'order_id' => $orderId,
            ]);
        }

        $this->transition($sagaId, OrderSagaState::FAILED, []);
    }

    private function transition(string $sagaId, OrderSagaState $newState, array $data): void
    {
        OrderSagaExecution::where('saga_id', $sagaId)->update([
            'state'   => $newState,
            'payload' => array_merge(
                OrderSagaExecution::where('saga_id', $sagaId)->value('payload') ?? [],
                $data
            ),
        ]);
    }

    private function fail(string $sagaId, string $reason, string $error): void
    {
        $this->transition($sagaId, OrderSagaState::FAILED, [
            'reason' => $reason,
            'error'  => $error,
        ]);
    }
}

// ============================================================
// CHOREOGRAPHY SAGA — event-based
// ============================================================

// Order Service event publish edir
class OrderService
{
    public function create(array $data): string
    {
        $order = Order::create($data);

        // Outbox pattern ilə atomic event publish
        OutboxMessage::create([
            'event_type' => 'order.created',
            'payload'    => json_encode([
                'order_id'   => $order->id,
                'items'      => $data['items'],
                'total'      => $order->total,
            ]),
        ]);

        return $order->id;
    }
}

// Payment Service event-ə subscribe olub
class PaymentServiceEventHandler
{
    public function onOrderCreated(array $event): void
    {
        // Idempotency check
        if (Payment::where('order_id', $event['order_id'])->exists()) {
            return; // artıq işlənib
        }

        try {
            $charge = $this->charge($event);

            OutboxMessage::create([
                'event_type' => 'payment.charged',
                'payload'    => json_encode([
                    'order_id'  => $event['order_id'],
                    'charge_id' => $charge->id,
                ]),
            ]);
        } catch (\Exception $e) {
            OutboxMessage::create([
                'event_type' => 'payment.failed',
                'payload'    => json_encode([
                    'order_id' => $event['order_id'],
                    'reason'   => $e->getMessage(),
                ]),
            ]);
        }
    }
}
```

## Praktik Tapşırıqlar

- E-commerce Saga-sını Orchestration ilə implement edin — state machine yazın
- Compensating transaction-ları da uğursuz olsa nə etmək lazımdır? (Dead letter queue)
- Idempotency key-ləri haraya ötürmək lazımdır?
- Choreography Saga-nın cycle riskini necə detect edərdiniz?
- Saga execution log-dan monitoring dashboard qurun

## Əlaqəli Mövzular

- `05-event-sourcing.md` — Saga + Event Sourcing
- `06-cqrs-architecture.md` — Command pattern ilə Saga
- `01-monolith-vs-microservices.md` — Distributed transactions problemi
- `04-hexagonal-architecture.md` — Saga service-lərdə Ports & Adapters
