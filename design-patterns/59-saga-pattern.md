# Saga Pattern (Senior)

## Mündəricat
1. [Problem: Distributed Transactions](#problem-distributed-transactions)
2. [Saga nədir?](#saga-nədir)
3. [Choreography-based Saga](#choreography-based-saga)
4. [Orchestration-based Saga](#orchestration-based-saga)
5. [Compensating Transactions](#compensating-transactions)
6. [Real Nümunə: E-commerce Sifariş](#real-nümunə-e-commerce-sifariş)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [Idempotency in Sagas](#idempotency-in-sagas)
9. [Saga vs 2PC](#saga-vs-2pc)
10. [Ümumi Pitfall-lar](#ümumi-pitfall-lar)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Distributed Transactions

Bir neçə servisdə atomik əməliyyat aparmaq lazımdırsa:

```
❌ Bu işləmir (fərqli servislərdə bir transaction):

BEGIN TRANSACTION;
  OrderService.createOrder(...)      // Order DB
  PaymentService.chargeCard(...)     // Payment DB
  InventoryService.reserveStock(...) // Inventory DB
COMMIT;

→ Bu mümkün deyil! Hər servisin öz ayrı DB-si var.
```

**2PC (Two-Phase Commit) problemi:**
- Bloklaşdıran protokol
- Koordinator çöksə, hər şey kilidlənir
- Mikroservislərdə impraktikdir

---

## Saga nədir?

Saga — ardıcıl local transaction-lardan ibarət uzun müddətli iş prosesidir. Hər addım öz local DB-nə yazar. Addım uğursuz olarsa, əvvəlki addımları geri alan **compensating transaction-lar** icra edilir.

```
Normal axın:
T1 → T2 → T3 → T4 → Uğur ✅

Uğursuzluq (T3-də):
T1 → T2 → T3(FAIL) → C2 → C1 → Geri alındı ⬅️

C1, C2 — T1, T2-nin compensating transaction-larıdır
```

**Əsas xüsusiyyətlər:**
- Hər addım lokal transaction-dır (atomikdir)
- Eventual consistency (dərhal deyil, vaxtla)
- Compensating transaction-larla rollback

---

## Choreography-based Saga

Mərkəzi koordinator yoxdur. Hər servis event-lərə reaksiya verir.

```
     Order Service          Payment Service      Inventory Service
          │                       │                      │
  [OrderPlaced event]             │                      │
          │──────────────────────►│                      │
          │               [PaymentProcessed event]       │
          │                       │─────────────────────►│
          │                       │             [StockReserved event]
          │◄──────────────────────┼──────────────────────│
     [OrderConfirmed]             │                      │

Uğursuzluq:
          │               [PaymentFailed event]          │
          │◄──────────────│                              │
    [OrderCancelled]      │                              │
```

**Üstünlüklər:**
- Loose coupling — servislər bir-birini bilmir
- Koordinator SPOF yoxdur
- Sadə addımlar üçün uyğun

**Çatışmazlıqlar:**
- Debug etmək çətin (axın dağınıqdır)
- Cycle risk (A→B→A)
- Centralized monitoring çətin

*- Centralized monitoring çətin üçün kod nümunəsi:*
```php
// Choreography nümunəsi — Laravel Events

// Order Service
class OrderService
{
    public function createOrder(array $data): Order
    {
        $order = Order::create($data);
        
        // Event publish et — PaymentService dinləyir
        event(new OrderCreated($order->id, $order->amount, $order->payment_info));
        
        return $order;
    }
    
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        Order::where('id', $event->orderId)->update(['status' => 'cancelled']);
        event(new OrderCancelled($event->orderId));
    }
}

// Payment Service
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

---

## Orchestration-based Saga

Mərkəzi orchestrator bütün addımları idarə edir.

```
                    ┌─────────────────┐
                    │  Saga Orchestr. │
                    └────────┬────────┘
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
          ▼                  ▼                  ▼
  [OrderService]    [PaymentService]   [InventoryService]
  createOrder()     chargeCard()       reserveStock()

Orchestrator:
1. OrderService.createOrder()      → Uğur → 2-yə keç
2. PaymentService.chargeCard()     → Uğur → 3-yə keç
3. InventoryService.reserveStock() → Uğursuz → Compensation başlat
   ↓
   PaymentService.refundCard()     → Compensation
   OrderService.cancelOrder()      → Compensation
```

**Üstünlüklər:**
- Axın aydın görünür
- Debug etmək asan
- Centralized monitoring
- Mürəkkəb iş axınları üçün uyğun

**Çatışmazlıqlar:**
- Orchestrator bottleneck ola bilər
- Servislər orchestrator-a asılıdır

---

## Compensating Transactions

Hər addımın "geri alınması" üçün compensating transaction lazımdır:

```
Addım                    Compensating
──────────────────────────────────────────
createOrder()         →  cancelOrder()
chargeCard()          →  refundCard()
reserveStock()        →  releaseStock()
scheduleDelivery()    →  cancelDelivery()
sendConfirmationEmail →  sendCancellationEmail
```

**Diqqət:** Bəzi əməliyyatlar compensate edilə bilmir (məs: email artıq göndərilib). Buna görə **pivotPoint** tərifi vacibdir — bu nöqtədən sonra compensate edilə bilmir, yalnız retry.

---

## Real Nümunə: E-commerce Sifariş

```
Addım 1: Order yaradılır (status: PENDING)
    │
    ▼
Addım 2: Kart yoxlanılır və charge edilir
    │
    ▼
Addım 3: Anbar rezerv edilir
    │
    ▼
Addım 4: Çatdırılma planlanır
    │
    ▼
Addım 5: Order təsdiqlənir (status: CONFIRMED)

Uğursuzluq ssenariləri:
- Addım 2 uğursuz: Order ləğv edilir (C1: cancelOrder)
- Addım 3 uğursuz: Kart geri qaytarılır (C2: refund), order ləğv (C1)
- Addım 4 uğursuz: Anbar azad (C3: release), kart geri qaytarılır (C2), order ləğv (C1)
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Saga state
enum SagaStatus: string
{
    case STARTED = 'started';
    case COMPENSATING = 'compensating';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}

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
        $charge = $this->paymentService->charge(
            $context->get('paymentInfo'),
            $context->get('amount')
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

---

## Idempotency in Sagas

Saga addımları idempotent olmalıdır (eyni addım bir neçə dəfə icra edilərsə eyni nəticə verməlidir):

*Saga addımları idempotent olmalıdır (eyni addım bir neçə dəfə icra edi üçün kod nümunəsi:*
```php
class ChargePaymentStep implements SagaStep
{
    public function execute(SagaContext $context): void
    {
        $idempotencyKey = "saga-{$context->get('sagaId')}-payment";
        
        // Əvvəllər charge edilib?
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
}
```

---

## Saga Durability — State Persistence

Saga həm orchestration, həm choreography-də state-i persist etməlidir:

*Saga həm orchestration, həm choreography-də state-i persist etməlidir üçün kod nümunəsi:*
```php
// Saga state cədvəli
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

**Countermeasures — Saga izolasiya problemlərini azaltmaq:**

```
Semantic Lock: Saga başlayanda "PENDING" flag qoy, başqalarına "bu proses davam edir" siqnalı ver
Commutative Updates: Sıra fərq etməyən əməliyyatlar (balance += X)
Pessimistic View: Saga addımı başlamazdan əvvəl kompensasiyaya hazır ol
Reread Value: Compensation zamanı cari dəyəri yenidən oxu, stale data ilə işləmə
Version File: Aggregate-in version-unu yoxla, yarışma vəziyyəti varsa retry et
```

---

## Saga vs 2PC

```
┌───────────────────┬────────────────────┬──────────────────────┐
│                   │       Saga         │        2PC            │
├───────────────────┼────────────────────┼──────────────────────┤
│ Consistency       │ Eventual           │ Strong (immediate)    │
│ Availability      │ Yüksək             │ Aşağı (blocking)      │
│ Complexity        │ Orta-Yüksək        │ Orta                  │
│ Performance       │ Yüksək             │ Aşağı (locks)         │
│ Isolation         │ Zəif (dirty reads) │ Güclü                 │
│ Microservice uyğ. │ ✅                 │ ❌                    │
│ Coordinator SPOF  │ Orchestr-da var    │ ✅ Var                │
│ Compensate lazım  │ ✅                 │ ❌ (rollback var)     │
└───────────────────┴────────────────────┴──────────────────────┘
```

---

## Ümumi Pitfall-lar

**1. Compensation-ı unutmaq:** Hər addım üçün compensation tərif edilməlidir.

**2. Dirty reads:** Saga izolasiya vermir. A addımı tamamlanıb, B addımı hələ tamamlanmayıb — bu arada başqa process A-nın məlumatını görə bilər.

**3. Compensate edilə bilməyən addımlar:** Email göndərmə, SMS — bunları sonraya burax (pivot point-dən sonra).

**4. State itirilməsi:** Orchestrator çöksə saga state-i DB-də saxlanmalıdır ki, restart edilə bilsin.

**5. Infinite compensation loop:** Compensation da uğursuz ola bilər — bu hallar üçün manual müdaxilə mexanizmi olmalıdır.

---

## İntervyu Sualları

**1. Saga pattern niyə distributed transaction üçün lazımdır?**
Fərqli servislərin ayrı DB-ləri olduğunda, bir transaction-da atomik əməliyyat mümkün deyil. Saga hər servisin öz local transaction-ını tamamlamasını, uğursuzluq halında isə compensating transaction-larla geri almasını təmin edir.

**2. Choreography vs Orchestration fərqi nədir?**
Choreography: event-driven, mərkəzi koordinator yoxdur, servislər bir-birinə event göndərir. Orchestration: mərkəzi orchestrator bütün addımları çağırır. Orchestration debug etmək asandır, choreography daha loose coupled-dır.

**3. Compensating transaction ilə DB rollback fərqi nədir?**
DB rollback əməliyyatı ləğv edir, sanki baş verməmişdir. Compensating transaction əvvəlki əməliyyatı geri alan yeni əməliyyatdır (məs: refund). Compensating transaction audit trail-i saxlayır.

**4. Saga-da dirty read problemi nədir?**
Saga eventual consistency verir. Addım 1 tamamlanıb, addım 2 hələ davam edərkən, başqa proses addım 1-in nəticəsini görə bilər — amma saga uğursuz olsa bu data "yanlış" ola bilər. Saga isolation vermir.

**5. Saga state niyə DB-də saxlanmalıdır?**
Orchestrator çöksə saga state-i yaddaşdan itir. DB-də saxlanıb restart edilə bilsə, saga kəsildiyi yerdən davam edə bilər. Bu at-least-once execution üçün vacibdir.

**6. Saga-da "lost update" (yarışma) problemi necə həll olunur?**
Saga izolasiya vermir — bir saga-nın tamamlanmamış dəyişiklikləri digər proseslərə görünür. Bunu azaltmaq üçün: semantic lock (proses flag-i ilə xəbər ver), optimistic locking (version field), yaxud kommutativ əməliyyatlar (toplama kimi sıra-independent update-lər).

**7. Choreography-based Saga-da hansı tool-lar istifadə edilir?**
Event-driven choreography üçün: RabbitMQ, Apache Kafka, AWS SQS/SNS, Google Pub/Sub. Kafka-nın consumer group-ları ilə exactly-once semantics daha yaxına çatmaq mümkündür. Laravel-də `ShouldQueue` + queue driver kombinasiyası sadə choreography üçün kifayətdir.

---

## Anti-patternlər

**1. Saga-da compensating transaction yazmamaq**
Hər addım üçün geri alma əməliyyatı hazırlamadan Saga tətbiq etmək — addım uğursuz olduqda sistemin vəziyyəti yarımçıq qalır, manual müdaxilə tələb olunur, data inconsistency baş verir. Hər forward əməliyyat üçün müvafiq compensating transaction planla və test et.

**2. Choreography Saga-da event zəncirlərini izləməmək**
Event-driven Saga-da hansı servisin hansı eventə subscribe olduğunu sənədləşdirməmək — sistem böyüdükcə event axını anlaşılmazdır, debugging kabusa çevrilir, yanlışlıqla loop yaranır. Choreography Saga-sı üçün event flow diaqramı saxla, ya da mürəkkəb axışlar üçün Orchestration seç.

**3. Saga state-ini yalnız yaddaşda saxlamaq**
Orchestrator class-ının state-ini `$this->state` kimi property-də tutmaq — servis restart olduqda bütün aktiv Saga-lar itirilir, hansı addımın tamamlandığı bilinmir. Saga state-ini mütləq persistent store-da (DB, Redis) saxla, restart halında kəsildiyi yerdən davam etsin.

**4. Saga addımlarını idempotent etməmək**
At-least-once message delivery sayəsindən gələ biləcək dublikat event-lərini idarə etməmək — eyni ödəniş iki dəfə alına, eyni email iki dəfə göndərilə bilər. Hər Saga addımını idempotency key ilə qoru, dublikat event-i tanı və skip et.

**5. Compensation uğursuzluğu halında plan etməmək**
"Compensation mütləq işləyir" fərziyyəsi ilə dizayn etmək — şəbəkə problemi, servis çöküşü zamanı compensation da uğursuz ola bilər, sistem limbo-da qalır. Uğursuz compensation-lar üçün retry mexanizmi, manual müdaxilə üçün admin panel, dead letter queue hazırla.

**6. Saga-nı sadə request-response axışları üçün tətbiq etmək**
Tək servis əməliyyatı üçün Saga tətbiq etmək — lazımsız mürəkkəblik, əlavə latency, debugging çətinliyi yaranır. Saga-nı yalnız çox servisin daxil olduğu, uzun müddətli distributed workflow-larda istifadə et; tək servis həllindən mümkün olan yerlərdə qaç.
