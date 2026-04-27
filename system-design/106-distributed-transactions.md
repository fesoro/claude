# Distributed Transactions Alternatives (Senior)

## Mündəricat
1. 2PC problemləri
2. TCC (Try-Confirm-Cancel) dərin baxış
3. 2PC vs Saga vs TCC müqayisəsi
4. PHP İmplementasiyası
5. İntervyu Sualları

---

## 2PC Problemləri

Two-Phase Commit bütün iştirakçıları koordinasiya edir, amma ciddi çatışmazlıqları var:

```
2PC Faza 1 — Prepare:
  Coordinator → Participant A: "Hazırsan?"
  Coordinator → Participant B: "Hazırsan?"
  Coordinator → Participant C: "Hazırsan?"

  A: "Bəli (vote commit)"
  B: "Bəli (vote commit)"
  C: "Bəli (vote commit)"

2PC Faza 2 — Commit:
  Coordinator → A, B, C: "Commit!"
  A, B, C: Commit icra edir
```

**Problem 1: Coordinator crash**

```
t=0: Coordinator → A, B: "Commit!"
t=1: A: committed
t=2: Coordinator CRASH ← burada
t=3: B: gözləyir... gözləyir... BLOCKED!
     B bilmir: commit etsin yoxsa rollback?
     B lock-u tutmaqda davam edir
```

**Problem 2: Blocking locks**

```
Prepare fazasından commit-ə qədər bütün resurslar lock-dadır:
  Order DB    → row locked
  Inventory DB → row locked
  Payment DB  → row locked

Network gecikmələri varsa bu saniyələrlə ölçülə bilər
```

**Problem 3: Network partitioning**

```
Coordinator → A: Commit (OK)
Coordinator → B: Commit (network error)
Coordinator → C: Commit (OK)

A, C: committed
B: hələ prepared
→ B qərar gözləyir, resurslar blokludur
```

**2PC-nin istifadə yeri:** Eyni data center-də, qısa müddətli transaction-lar (XA).

---

## TCC Pattern — Dərin Baxış

TCC Distributed transaction-ı 3 fazaya böldür, hər faza ayrı API endpoint-dir:

```
TCC Faza 1 — Try (Rezerv et):
  Resursu tam götürmə, yalnız ayır (reserve/tentative)

TCC Faza 2 — Confirm (Təsdiqlə):
  Rezervi real əməliyyata çevir

TCC Faza 3 — Cancel (Ləğv et):
  Rezervi burax, əvvəlki vəziyyətə qayıt
```

### Ödəniş nümunəsi ilə TCC:

```
Sifariş: User 100 AZN ödəsin, 2 ədəd məhsul alsın

TRY fazası:
  OrderService.try():    order_id=1 → status=PENDING
  PaymentService.try():  100 AZN → reserved (balance azaldılmır, sadəcə hold)
  InventoryService.try(): 2 ədəd → reserved (stock azaldılmır, hold)

Hamı OK? →

CONFIRM fazası:
  OrderService.confirm():    order_id=1 → status=CONFIRMED
  PaymentService.confirm():  hold → actual debit (balance azalır)
  InventoryService.confirm(): reserved → actual decrement

Bir servisdə xəta? →

CANCEL fazası:
  OrderService.cancel():    order_id=1 → status=CANCELLED
  PaymentService.cancel():  hold → release (balance qayıdır)
  InventoryService.cancel(): reserved → release (stock qayıdır)
```

### TCC Timeline:

```
t=0:  Coordinator.try(orderId=1)
t=1:    → OrderService.try()      → OK, confirmationId=A1
t=2:    → PaymentService.try()    → OK, confirmationId=B1
t=3:    → InventoryService.try()  → OK, confirmationId=C1

t=4:  Hamı OK → confirm fazasına keç
t=5:    → OrderService.confirm(A1)    → OK
t=6:    → PaymentService.confirm(B1)  → FAIL!

t=7:  Xəta var → cancel fazasına keç
t=8:    → OrderService.cancel(A1)    → OK
t=9:    → PaymentService.cancel(B1)  → OK (idempotent)
t=10:   → InventoryService.cancel(C1) → OK
```

### TCC vs Saga:

```
TCC:                          Saga:
─────────────────────         ──────────────────────
Try → Confirm/Cancel          Step 1 → Step 2 → ...
                              Xəta → Compensation

Isolation:  Yüksək           Düşük (ara vəziyyət görünür)
Latency:    Az (2 round)     Çox (N round)
Kompleks:   Yüksək           Orta
Rollback:   Ani (Cancel)     Gecikmeli (compensation)
```

---

## 2PC vs Saga vs TCC Müqayisəsi

```
┌─────────────────┬──────────────┬──────────────┬──────────────┐
│                 │     2PC      │     Saga     │     TCC      │
├─────────────────┼──────────────┼──────────────┼──────────────┤
│ Blocking        │ Bəli         │ Xeyr         │ Xeyr         │
│ Isolation       │ ACID         │ Zəif         │ Orta         │
│ Coordinator     │ Lazımdır     │ Opsional     │ Lazımdır     │
│ Partial failure │ Problematik  │ Compensate   │ Cancel       │
│ Throughput      │ Aşağı        │ Yüksək       │ Orta         │
│ Cross-service   │ Çətin (XA)   │ Asan         │ Orta         │
│ Dirty reads     │ Yoxdur       │ Ola bilər    │ Minimal      │
│ Implementation  │ Mürəkkəb     │ Sadə         │ Mürəkkəb     │
└─────────────────┴──────────────┴──────────────┴──────────────┘
```

**Nə zaman nəyi seçmək:**

```
2PC:   Eyni şəbəkədə, qısa transaction, güclü consistency lazımdır
       (PostgreSQL XA, Java EE JTA)

Saga:  Uzun müddətli workflow, loose coupling, eventual consistency OK
       (E-commerce checkout, travel booking)

TCC:   Yüksək throughput, minimal dirty read, retry-safe olmalı
       (Ödəniş sistemləri, rezervasiya sistemləri)
```

---

## PHP İmplementasiyası

```php
<?php

// TCC Participant interface — hər servis implement etməlidir
interface TccParticipant
{
    /**
     * Resursu rezerv et, unikal confirmationId qaytar
     * İdempotent olmalıdır (eyni requestId ilə dəfələrlə çağrıla bilər)
     */
    public function tryOperation(string $requestId, array $params): TccResult;

    /**
     * Rezervi real əməliyyata çevir
     * İdempotent olmalıdır
     */
    public function confirm(string $confirmationId): bool;

    /**
     * Rezervi ləğv et, resursu burax
     * İdempotent olmalıdır (confirm-dən sonra da çağrıla bilər — no-op)
     */
    public function cancel(string $confirmationId): bool;
}

// TCC Coordinator
class TccCoordinator
{
    private PDO $db;
    private array $participants; // TccParticipant[]

    private const STATUS_TRYING    = 'trying';
    private const STATUS_CONFIRMED = 'confirmed';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_FAILED    = 'failed';

    /**
     * Bütün iştirakçıları koordinasiya edir
     */
    public function execute(string $transactionId, array $paramsPerParticipant): bool
    {
        // Transaction qeyd et
        $this->createTransaction($transactionId);

        // Faza 1: TRY
        $confirmationIds = [];

        foreach ($this->participants as $name => $participant) {
            $params = $paramsPerParticipant[$name] ?? [];

            try {
                $result = $participant->tryOperation($transactionId, $params);

                if (!$result->isSuccess()) {
                    // Try uğursuz → hər şeyi ləğv et
                    $this->cancelAll($confirmationIds);
                    $this->markTransaction($transactionId, self::STATUS_FAILED);
                    return false;
                }

                $confirmationIds[$name] = $result->getConfirmationId();
                $this->saveConfirmationId($transactionId, $name, $result->getConfirmationId());
            } catch (\Throwable $e) {
                $this->cancelAll($confirmationIds);
                $this->markTransaction($transactionId, self::STATUS_FAILED);
                throw $e;
            }
        }

        // Faza 2: CONFIRM
        foreach ($this->participants as $name => $participant) {
            try {
                $participant->confirm($confirmationIds[$name]);
            } catch (\Throwable $e) {
                // Confirm xətası: cancel cəhdi et (best-effort)
                // Burada retry logic lazımdır (saga-style compensation)
                $this->cancelAll($confirmationIds, skip: array_keys($confirmationIds));
                $this->markTransaction($transactionId, self::STATUS_FAILED);
                throw $e;
            }
        }

        $this->markTransaction($transactionId, self::STATUS_CONFIRMED);
        return true;
    }

    private function cancelAll(array $confirmationIds, array $skip = []): void
    {
        foreach ($this->participants as $name => $participant) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            if (!isset($confirmationIds[$name])) {
                continue;
            }

            try {
                $participant->cancel($confirmationIds[$name]);
            } catch (\Throwable $e) {
                // Cancel xətası: log et, retry queue-ya at
                error_log("Cancel failed for {$name}: " . $e->getMessage());
            }
        }
    }

    private function createTransaction(string $txId): void
    {
        $this->db->prepare(
            'INSERT INTO tcc_transactions (tx_id, status, created_at)
             VALUES (:id, :status, NOW())'
        )->execute([':id' => $txId, ':status' => self::STATUS_TRYING]);
    }

    private function markTransaction(string $txId, string $status): void
    {
        $this->db->prepare(
            'UPDATE tcc_transactions SET status = :status, updated_at = NOW() WHERE tx_id = :id'
        )->execute([':status' => $status, ':id' => $txId]);
    }

    private function saveConfirmationId(string $txId, string $participant, string $confId): void
    {
        $this->db->prepare(
            'INSERT INTO tcc_participants (tx_id, participant_name, confirmation_id)
             VALUES (:tx, :name, :conf)'
        )->execute([':tx' => $txId, ':name' => $participant, ':conf' => $confId]);
    }
}

// Nümunə TCC Participant: InventoryService
class InventoryTccParticipant implements TccParticipant
{
    private PDO $db;

    public function tryOperation(string $requestId, array $params): TccResult
    {
        $productId = $params['product_id'];
        $quantity  = $params['quantity'];

        $this->db->beginTransaction();

        try {
            // İdempotency: əvvəlcə yoxla
            $existing = $this->findReservation($requestId);
            if ($existing) {
                $this->db->rollBack();
                return TccResult::success($existing['confirmation_id']);
            }

            // Stok yoxla və lock al
            $stmt = $this->db->prepare(
                'SELECT stock FROM inventory WHERE product_id = ? FOR UPDATE'
            );
            $stmt->execute([$productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || $row['stock'] < $quantity) {
                $this->db->rollBack();
                return TccResult::failure('Insufficient stock');
            }

            // Rezerv yarat (stock azaltma — yalnız hold)
            $confirmationId = 'inv_' . bin2hex(random_bytes(8));

            $this->db->prepare(
                'INSERT INTO inventory_reservations
                   (confirmation_id, request_id, product_id, quantity, status, expires_at)
                 VALUES (:conf, :req, :prod, :qty, :status, NOW() + INTERVAL 15 MINUTE)'
            )->execute([
                ':conf'   => $confirmationId,
                ':req'    => $requestId,
                ':prod'   => $productId,
                ':qty'    => $quantity,
                ':status' => 'reserved',
            ]);

            $this->db->commit();
            return TccResult::success($confirmationId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function confirm(string $confirmationId): bool
    {
        $this->db->beginTransaction();

        try {
            $reservation = $this->getReservation($confirmationId);

            if (!$reservation || $reservation['status'] === 'confirmed') {
                $this->db->rollBack();
                return true; // İdempotent: artıq confirm olunub
            }

            // İndi stock-u real olaraq azalt
            $this->db->prepare(
                'UPDATE inventory SET stock = stock - :qty WHERE product_id = :prod'
            )->execute([
                ':qty'  => $reservation['quantity'],
                ':prod' => $reservation['product_id'],
            ]);

            $this->db->prepare(
                'UPDATE inventory_reservations SET status = :status WHERE confirmation_id = :id'
            )->execute([':status' => 'confirmed', ':id' => $confirmationId]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancel(string $confirmationId): bool
    {
        // İdempotent: reserved olanı ləğv et, confirmed-ə toxunma
        $affected = $this->db->prepare(
            "UPDATE inventory_reservations
             SET status = 'cancelled'
             WHERE confirmation_id = :id AND status = 'reserved'"
        );
        $affected->execute([':id' => $confirmationId]);

        return true; // Cancel həmişə uğurludur (idempotent)
    }

    private function findReservation(string $requestId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM inventory_reservations WHERE request_id = ?'
        );
        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getReservation(string $confirmationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM inventory_reservations WHERE confirmation_id = ?'
        );
        $stmt->execute([$confirmationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// İstifadə nümunəsi
function placeOrder(int $orderId, int $userId, int $productId, int $qty, float $amount): bool
{
    global $redis, $db;

    $txId = 'tx_' . bin2hex(random_bytes(12));

    $coordinator = new TccCoordinator($db, [
        'order'     => new OrderTccParticipant($db),
        'payment'   => new PaymentTccParticipant($db),
        'inventory' => new InventoryTccParticipant($db),
    ]);

    return $coordinator->execute($txId, [
        'order'     => ['order_id' => $orderId, 'user_id' => $userId],
        'payment'   => ['user_id' => $userId, 'amount' => $amount],
        'inventory' => ['product_id' => $productId, 'quantity' => $qty],
    ]);
}
```

---

## İntervyu Sualları

- 2PC-nin əsas problemi nədir? Coordinator crash olsa nə baş verir?
- TCC pattern-in 3 fazasını izah edin. Try fazasında real yazı var yoxsa yox?
- TCC-nin hər fazası niyə idempotent olmalıdır?
- Saga pattern ilə TCC arasındakı əsas fərq nədir? Dirty reads baxımından?
- Confirm fazasında bir iştirakçı xəta versə nə etmək lazımdır?
- TCC-nin "hanging transaction" problemi nədir? (Try OK, amma Confirm/Cancel gəlməyib)
- 2PC-ni nə vaxt, Saga-nı nə vaxt, TCC-ni nə vaxt seçərsiniz?
- XA transaction nədir? Hansı hallarda 2PC üçün istifadə edilir?
