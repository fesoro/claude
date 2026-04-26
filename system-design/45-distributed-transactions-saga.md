# Distributed Transactions & Saga Pattern (Lead)

## İcmal

Monolit tətbiqdə bir `DB::transaction()` bütün dəyişiklikləri ACID qaydası ilə idarə edir — ya hamısı commit olur, ya hamısı rollback. Mikroservis memarlığında isə hər servisin öz DB-si var. `Order` servisi MySQL-dədir, `Payment` servisi PostgreSQL-də, `Inventory` Redis+Mongo-da. Bir biznes əməliyyatı (məsələn, order yerləşdirmək) bu üç servisi də dəyişməlidir. Amma **distributed `COMMIT` yoxdur** — şəbəkə vasitəsilə atomic commit etmək praktik deyil.

**Distributed transaction** — bir neçə müstəqil sistemdə ardıcıl dəyişikliklərin koordinasiyasıdır. Yaxınlaşmalar: 2PC, 3PC (klassik, blocking), **Saga** (müasir, non-blocking, eventual consistency).


## Niyə Vacibdir

Mikroservislər arasında ACID transaction mümkün deyil — hər servisin öz DB-si var. Saga pattern eventual consistency ilə distributed iş axını koordinasiya edir; 2PC bloklanma problemi həll olunur. E-commerce order, payment, inventory — hamısı saga tələb edir.

## Əsas Anlayışlar

### 1. Problem — ACID Mikroservisdə Qırılır

Monolitdə:
```php
DB::transaction(function () {
    Order::create([...]);
    Inventory::decrement('stock', 1);
    Payment::charge($user, 100);
});
// Hamısı bir DB-də, bir transaction
```

Mikroservisdə:
- `OrderService` → Order DB
- `InventoryService` → Inventory DB (başqa host)
- `PaymentService` → Stripe API

Bunları bir transaction-a yığmaq üçün **Two-Phase Commit** tarixən istifadə olunub, amma müasir sistemlər **Saga**-ya keçib.

### 2. Two-Phase Commit (2PC)

**Coordinator** (Transaction Manager) iki fazalı protokol işlədir:

**Phase 1 — Prepare:**
- Coordinator bütün participant-lara `PREPARE` mesajı göndərir
- Hər participant lokal olaraq əməliyyatı hazırlayır (lock resurs, WAL yazır), amma commit etmir
- `YES` (ready) və ya `NO` (abort) qaytarır

**Phase 2 — Commit/Abort:**
- Bütün `YES` gələrsə → Coordinator `COMMIT` göndərir
- Ən azı biri `NO` deyərsə → `ABORT` göndərir
- Participant-lar lokal transaction-ı tamamlayır

**Problem:**
- **Blocking protocol** — participant `YES` dedikdən sonra coordinator-dan cavab gələnə qədər resurs lock qalır
- **Coordinator failure** — prepare-dən sonra coordinator çökərsə, participant-lar sonsuz gözləyə bilər (in-doubt state)
- **Yüksək latency** — hər əməliyyat iki round-trip
- **Tight coupling** — bütün participant-lar eyni anda up olmalıdır
- **Scalability azdır** — 10+ servis varsa, hər birinin hər zaman hazır olması lazım

### 3. Three-Phase Commit (3PC)

2PC-ni yaxşılaşdırmaq üçün əlavə faza:
1. **CanCommit** — soruş, hazırdırmı
2. **PreCommit** — commit niyyətini yay
3. **DoCommit** — actual commit

PreCommit fazası participant-lara timeout-da müstəqil qərar qəbul etmə imkanı verir (coordinator down olsa da). Lakin:
- **Network partition** zamanı hələ də split-brain problemi var
- Praktik implementation az — mürəkkəbdir, faydası məhduddur
- Real-world-də demək olar istifadə olunmur

### 4. Niyə 2PC/3PC Müasir Mikroservislərdə İstifadə Olunmur?

- **Availability əldən gedir** — istənilən participant down olsa transaction dayanır
- **Latency yüksək** — 2-3 round-trip hər əməliyyat üçün
- **Cloud-native uyğunsuz** — AWS, Stripe, Kafka API-ləri 2PC dəstəkləmir
- **HTTP stateless** — 2PC stateful session tələb edir

Modern memarlıqda Saga seçilir.

### 5. Saga Pattern — Əsas İdeya

Saga — bir sıra **lokal transaction**-lardır. Hər servis öz DB-sində öz transaction-ını commit edir. Addım uğursuz olarsa, əvvəlki addımlar **compensating transaction** ilə geri qaytarılır.

```
Step 1: createOrder()    → OK
Step 2: reserveStock()   → OK
Step 3: chargeCard()     → FAIL
Compensate: releaseStock() → cancelOrder()
```

Əsas fərq 2PC-dən: rollback yox, **semantic undo**. `DELETE` yox, `REFUND`. `stock--` üçün kompensasiya `stock++`.

### 6. Orchestration-based Saga

Mərkəzi **orchestrator** (saga coordinator) saga-nın bütün addımlarını idarə edir. Hər addımın nəticəsinə görə növbəti command-i göndərir.

```
[Orchestrator] → OrderService: CreateOrder → OrderCreated
               → InventoryService: ReserveStock → StockReserved
               → PaymentService: Charge → PaymentFailed → Compensate All
```

**Tools:** Temporal, Camunda, AWS Step Functions, Netflix Conductor.

**Üstünlüklər:**
- Mərkəzi logika — saga axını bir yerdə görünür
- Debug və monitoring asan
- Business rule dəyişikliyi tək yerdə
- Retry, timeout, versioning aydın

**Çatışmazlıqlar:**
- Orchestrator single point of failure (əslində distributed işlədilməlidir)
- Servislər orchestrator-a asılı olur

### 7. Choreography-based Saga

Mərkəzi idarəçi yoxdur. Hər servis event publish edir, digərləri dinləyib reaksiya verir.

```
OrderService → publish OrderCreated
    ↓
InventoryService → listen, reserve, publish StockReserved
    ↓
PaymentService → listen, charge, publish PaymentCharged
    ↓
ShippingService → listen, ship
```

Hansısa addım fail olsa, `*Failed` event publish olunur və əvvəlki servislər onu dinləyib kompensasiya edir.

**Üstünlüklər:**
- Loosely coupled — servislər bir-birini birbaşa tanımır
- Tək single point yoxdur
- Yeni servis əlavə etmək asandır (sadəcə event-ə abunə olur)

**Çatışmazlıqlar:**
- Saga axını dağınıq — debug çətin
- Cyclic dependency təhlükəsi
- 4-5-dən çox addımda idarəetmə dağılır

**Tipik qayda:** 2-4 addım → choreography, 5+ addım → orchestration.

### 8. Compensating Transactions

Kompensasiya **semantic undo**-dur, rollback yox.

| Forward | Compensation |
|---------|--------------|
| `CreateOrder` | `CancelOrder` (status=cancelled) |
| `ReserveStock` | `ReleaseStock` |
| `ChargeCard` | `RefundPayment` |
| `SendEmail` | `SendCancellationEmail` (undo mümkün deyil) |

**Xüsusiyyətlər:**
- **Idempotent** olmalıdır — retry zamanı ikiqat iş görməməli
- **Commutative** ideal — sıra fərqi olmamalı
- **Əlçatan** olmalıdır — kompensasiya heç vaxt fail etməməlidir (və ya manual intervention-a çıxmalıdır)

### 9. Saga State Machine

Saga state-ini DB-də saxlamaq lazımdır ki, crash-dan sonra davam edə bilsin.

```
PENDING → ORDER_CREATED → STOCK_RESERVED → PAYMENT_CHARGED → COMPLETED
              ↓                ↓                  ↓
          COMPENSATING → ... → FAILED
```

### 10. İsolation Problem — Saga ACID Deyil

Saga Atomicity və Durability verir (eventual), amma **Isolation** itirir. Addımlar arasında başqa transaction-lar ara data görə bilər.

- **Semantic lock** — "pending" status digər saga-ları bloklayır
- **Commutative updates** — sıra fərq etməyən əməliyyatlar (increment)
- **By value pattern** — critical data-nı saga state-də saxla
- **Reread value** — kompensasiya zamanı son dəyəri oxu

## Nümunələr

### E-commerce Order Saga

```
1. OrderCreated      → insert order (status=pending)
2. StockReserved     → stock -= qty
3. PaymentCharged    → Stripe charge
4. OrderConfirmed    → status=confirmed, notify user
5. ShippingScheduled → create shipment

Fail → reverse compensation: Refund → Release stock → Cancel order
```

### Laravel — Saga State Table

```php
<?php
// database/migrations/xxxx_create_sagas_table.php
Schema::create('sagas', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type'); // 'order_saga'
    $table->string('state'); // 'order_created', 'stock_reserved', ...
    $table->json('payload'); // context — order_id, user_id, amount
    $table->json('completed_steps')->default('[]');
    $table->string('last_error')->nullable();
    $table->integer('attempts')->default(0);
    $table->timestamps();
});
```

### Orchestrator — Laravel Job Chain

```php
<?php
namespace App\Sagas;

use App\Models\Saga;
use App\Jobs\{ReserveStockJob, ChargePaymentJob, ConfirmOrderJob};
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class OrderSagaOrchestrator
{
    public function start(int $userId, array $items, float $amount): string
    {
        $sagaId = (string) Str::uuid();

        $saga = Saga::create([
            'id' => $sagaId,
            'type' => 'order_saga',
            'state' => 'pending',
            'payload' => compact('userId', 'items', 'amount'),
        ]);

        // Job chain — failure-da bütün chain dayanır, catch() çağırılır
        Bus::chain([
            new ReserveStockJob($sagaId),
            new ChargePaymentJob($sagaId),
            new ConfirmOrderJob($sagaId),
        ])->catch(function (\Throwable $e) use ($sagaId) {
            (new OrderSagaOrchestrator)->compensate($sagaId, $e->getMessage());
        })->dispatch();

        return $sagaId;
    }

    public function compensate(string $sagaId, string $reason): void
    {
        $saga = Saga::findOrFail($sagaId);
        $completed = $saga->completed_steps;

        // Tərsinə sıra ilə kompensasiya
        foreach (array_reverse($completed) as $step) {
            match ($step) {
                'payment_charged' => dispatch(new RefundPaymentJob($sagaId)),
                'stock_reserved' => dispatch(new ReleaseStockJob($sagaId)),
                'order_created' => dispatch(new CancelOrderJob($sagaId)),
                default => null,
            };
        }

        $saga->update(['state' => 'failed', 'last_error' => $reason]);
    }
}
```

### Saga Step Job — Idempotent

```php
<?php
namespace App\Jobs;

class ReserveStockJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 5;
    public array $backoff = [2, 5, 15, 60];

    public function __construct(public string $sagaId) {}

    public function handle(InventoryClient $inventory): void
    {
        $saga = Saga::lockForUpdate()->findOrFail($this->sagaId);

        // Idempotency — bu addım artıq tamamlanıbsa skip
        if (in_array('stock_reserved', $saga->completed_steps)) {
            return;
        }

        // Idempotency key — sagaId + step → external API-də duplicate qorur
        $reservationId = $inventory->reserve(
            items: $saga->payload['items'],
            idempotencyKey: "{$this->sagaId}:reserve",
        );

        $saga->update([
            'state' => 'stock_reserved',
            'completed_steps' => [...$saga->completed_steps, 'stock_reserved'],
            'payload' => [...$saga->payload, 'reservation_id' => $reservationId],
        ]);
    }
}
```

### Choreography — Event-Driven Saga

```php
<?php
// OrderService
class CreateOrderAction
{
    public function execute(array $data): Order
    {
        $order = Order::create($data + ['status' => 'pending']);
        event(new OrderCreated($order));
        return $order;
    }
}

// InventoryService listener — dinləyir, reaksiya verir
class ReserveStockOnOrderCreated
{
    public function handle(OrderCreated $event): void
    {
        try {
            $this->inventory->reserve($event->order->items);
            event(new StockReserved($event->order->id));
        } catch (OutOfStockException $e) {
            event(new StockReservationFailed($event->order->id, $e->getMessage()));
        }
    }
}

// OrderService — failure event-i ilə kompensasiya
class CancelOrderOnStockFailed
{
    public function handle(StockReservationFailed $event): void
    {
        Order::where('id', $event->orderId)
            ->update(['status' => 'cancelled', 'reason' => $event->reason]);
    }
}
```

### Temporal PHP SDK — Production Qeyd

Temporal kompleks saga-lar üçün ən yaxşı seçimdir — retry, timeout, versioning, durable execution built-in. `Workflow\Saga` sinfi kompensasiya-nı addım-addım `addCompensation()` ilə qeyd edir, exception zamanı `$saga->compensate()` tərsinə sıra ilə çağırır.

### Outbox Pattern — Reliable Event Publishing

Saga-nın əsas problemi: DB commit olur, amma event publish fail edir → saga qalır. Həll — **Transactional Outbox**.

```php
DB::transaction(function () use ($order) {
    Order::create($order);
    // Event-i DB-yə yaz, broker-ə yox
    OutboxEvent::create([
        'type' => 'OrderCreated',
        'payload' => json_encode($order),
    ]);
});

// Ayrı worker outbox-u dinləyir, broker-ə push edir
```

Daha ətraflı: **file 46 — Outbox Pattern**.

## Praktik Tapşırıqlar

**1. 2PC niyə müasir mikroservislərdə istifadə olunmur?**
Blocking protocol-dur — coordinator və ya participant fail olarsa resurslar sonsuz lock qalır. Yüksək latency, availability aşağı. Cloud-native API-lər (Stripe, AWS) 2PC dəstəkləmir. Saga seçilir.

**2. Saga-da Isolation necə təmin olunur?**
Təmin olunmur — saga ACID-in "I"-sini qurban verir. Workaround: semantic lock (pending status), commutative updates, by-value pattern (critical data saga state-də), reread value kompensasiya zamanı.

**3. Orchestration vs Choreography — nə vaxt hansı?**
- **Choreography**: 2-4 addım, loose coupling prioritet, servislər müstəqildir
- **Orchestration**: 5+ addım, mürəkkəb branching, debug və monitoring vacib, compliance tələbləri var

**4. Kompensasiya transaksiyası nə vaxt fail olarsa nə edəsən?**
Kompensasiya **mümkün qədər sadə və reliable** olmalıdır. Retry with backoff. Son çarə — manual intervention queue-ya at, alarm göndər. Heç vaxt sessiz drop etmə.

**5. Saga step-i necə idempotent edirsən?**
Hər step-ə unique idempotency key ver (sagaId + step name). External API çağırışında bu key-i headers-də göndər. DB-də saga state yoxla — əgər bu step artıq `completed_steps`-dədirsə, skip et.

**6. Saga orchestrator crash etsə nə olur?**
State DB-də saxlanmalıdır (saga state machine table). Restart-da pending saga-ları tapıb davam etdirir. Temporal/Camunda bunu avtomatik edir. Öz həllində — cron-da stuck saga-ları poll et.

**7. "Semantic undo" nədir və adi DELETE-dən nə fərqi var?**
Əsl `DELETE` məlumatı itirir və audit trail saxlamır. Semantic undo isə iz buraxır: `status=cancelled`, `refund_at=now()`, `CancellationRecord` yaradılır. Biznes üçün tarix vacibdir, maliyyə auditi tələb edir.

**8. Saga-da event publishing reliability necə təmin olunur?**
**Outbox pattern** — DB transaction daxilində həm dəyişikliyi, həm event-i bir cədvələ yaz. Ayrı relay prosesi outbox-dan oxuyur, broker-ə göndərir, commit edir. Bu `at-least-once delivery` + consumer idempotency-dən nəticə effektiv olaraq `exactly-once` verir.

## Praktik Baxış

1. **2PC istifadə etmə** — monolit DB deyilsə, Saga seç
2. **Idempotent steps** — hər step idempotency key qəbul etməlidir
3. **Compensation əvvəlcədən dizayn et** — forward-ı yazmadan kompensasiyanı düşün
4. **State machine explicit** — DB cədvəlində saga state, completed steps, last error
5. **Outbox pattern** — event publishing reliability üçün mütləq
6. **Timeout hər step-ə** — sonsuz gözləmə saga-nı stuck qoyar
7. **Orchestrator 5+ addımda** — choreography kiçik saga-da yaxşıdır
8. **Monitoring və alerting** — stuck saga, kompensasiya failure dərhal görünsün
9. **Manual intervention queue** — kompensasiya-nın heç cür alınmadığı hallar üçün
10. **Temporal/Camunda mission-critical üçün** — öz engine yazmaq texniki borcdur
11. **Saga-lar qısa olsun** — uzun saga = çox failure point
12. **Circuit breaker** — hər external çağırışda (file 07)
13. **Distributed tracing** — trace_id bütün saga addımlarına ötür


## Əlaqəli Mövzular

- [Microservices](10-microservices.md) — saga-nın əsas istifadə yeri
- [Idempotency](28-idempotency.md) — saga addımlarının idempotentliyi
- [CDC & Outbox](46-cdc-outbox-pattern.md) — saga event-lərini reliable göndərmək
- [Consistency Patterns](32-consistency-patterns.md) — eventual consistency çərçivəsi
- [Payment System](20-payment-system-design.md) — ödəniş saga nümunəsi
