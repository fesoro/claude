# Loyalty Points & Credit System

## Problem necə yaranır?

1. **Race condition (overdraft):** İstifadəçinin 100 point-i var. İki parallel request eyni anda burn edir. Hər ikisi balansı 100 oxuyur, hər ikisi 100 çıxır. Nəticə: -100 balance — overdraft.
2. **Floating point xətaları:** `$29.99 * 10 = 299.9000...1` — pul hesablamada floating point yox, integer cents/points istifadə edilməlidir.
3. **Expiry tracking:** Hansı point-lar expire olub, hansı olmayıb? Sadə `balance` column-u tarix saxlamır.
4. **Audit trail yoxluğu:** Mutable balance column-u — "bu point haradan gəldi?" cavablanmır, fraud aşkarlanmır.

---

## Ledger (Dəftər) Yanaşması

Balance column-u əvəzinə hər dəyişiklik yeni transaction kimi yazılır. Balance = ledger-dəki son `balance_after` dəyəri.

```
user_id | type   | points | balance_after | created_at
1       | earn   | +100   | 100           | 2024-01-01
1       | earn   | +50    | 150           | 2024-01-15
1       | burn   | -80    | 70            | 2024-02-01
1       | expire | -30    | 40            | 2025-01-01  ← expired earn
```

Bu yanaşma audit trail, fraud detection, refund/rollback üçün əsasdır.

---

## İmplementasiya

*Bu kod ledger əsaslı sadiqlik xalları servisini, overdraft önləyən lock mexanizmini və süresi keçmiş xalları ləğv edən job-u göstərir:*

```php
class LoyaltyService
{
    public function earn(int $userId, int $points, string $referenceType, int $referenceId): PointsTransaction
    {
        return DB::transaction(function () use ($userId, $points, $referenceType, $referenceId) {
            // SELECT FOR UPDATE: concurrent earn/burn-da race condition önlənir
            // Eyni user üçün paralel transaction gözləyər
            $balance = $this->getBalanceForUpdate($userId);

            return PointsTransaction::create([
                'user_id'        => $userId,
                'type'           => 'earn',
                'points'         => $points,
                'balance_after'  => $balance + $points,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'expires_at'     => now()->addYear(),
            ]);
        });
    }

    public function burn(int $userId, int $points, string $referenceType, int $referenceId): PointsTransaction
    {
        return DB::transaction(function () use ($userId, $points, $referenceType, $referenceId) {
            $balance = $this->getBalanceForUpdate($userId);

            if ($balance < $points) {
                throw new InsufficientPointsException("Balance: {$balance}, Required: {$points}");
            }

            return PointsTransaction::create([
                'user_id'       => $userId,
                'type'          => 'burn',
                'points'        => -$points,
                'balance_after' => $balance - $points,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
            ]);
        });
    }

    // Balance: ledger-dəki son transaction-ın balance_after-i
    public function getBalance(int $userId): int
    {
        return PointsTransaction::where('user_id', $userId)
            ->latest('id')
            ->value('balance_after') ?? 0;
    }

    // FOR UPDATE: bu user üçün başqa transaction gözləyər — overdraft önlənir
    private function getBalanceForUpdate(int $userId): int
    {
        return PointsTransaction::where('user_id', $userId)
            ->lockForUpdate()
            ->latest('id')
            ->value('balance_after') ?? 0;
    }

    // Refund: order iade edildikdə burn edilmiş point-lar geri qaytarılır
    public function refund(int $userId, int $points, int $originalBurnTransactionId): PointsTransaction
    {
        return DB::transaction(function () use ($userId, $points, $originalBurnTransactionId) {
            $balance = $this->getBalanceForUpdate($userId);

            return PointsTransaction::create([
                'user_id'       => $userId,
                'type'          => 'refund',
                'points'        => $points,
                'balance_after' => $balance + $points,
                'reference_type' => 'burn_transaction',
                'reference_id'   => $originalBurnTransactionId,
            ]);
        });
    }
}

// Expiry Job — expire olan earn transaction-lar üçün compensating "expire" transaction yazır
class ExpirePointsJob implements ShouldQueue
{
    public function handle(): void
    {
        PointsTransaction::where('type', 'earn')
            ->where('expires_at', '<=', now())
            ->whereNotExists(function ($q) {
                // Artıq expire edilmiş earn-ları keç
                $q->select(DB::raw(1))
                    ->from('points_transactions as pt2')
                    ->whereColumn('pt2.reference_id', 'points_transactions.id')
                    ->where('pt2.type', 'expire');
            })
            ->chunk(100, function ($transactions) {
                foreach ($transactions as $txn) {
                    // Hər expire ayrı ledger transaction — audit trail tam qalır
                    app(LoyaltyService::class)->expireEarnTransaction($txn);
                }
            });
    }
}

// Checkout-da points istifadəsi — max limit tətbiq edilir
class CheckoutService
{
    public function applyPoints(int $userId, int $orderTotal, int $pointsToUse): array
    {
        $balance     = app(LoyaltyService::class)->getBalance($userId);
        $pointsToUse = min($pointsToUse, $balance); // Balansdan çox istifadə edilə bilməz

        $pointsValue = (int) ($pointsToUse / 100);             // 100 point = $1
        $maxDiscount = (int) ($orderTotal * 0.3);              // Max %30 endirim
        $discount    = min($pointsValue, $maxDiscount);

        return [
            'points_used' => $discount * 100,
            'discount'    => $discount,
            'final_total' => $orderTotal - $discount,
        ];
    }
}
```

---

## FIFO Expiry

Hansı point-lar əvvəl expire olmalıdır? FIFO: ən köhnə earn transaction-lar əvvəl expire olur. Bu istifadəçi üçün ədalətli — köhnə qazandığı point-lar expire olmadan əvvəl istifadə etməyə imkan verir.

---

## Anti-patterns

- **Mutable balance column:** `UPDATE users SET points = points - 80` — tarix itirilir, race condition var, rollback mümkün deyil.
- **Cache-dən balance oxumaq:** Stale data → overdraft. Balance həmişə DB-dən, `lockForUpdate` ilə.
- **Float istifadəsi:** `0.1 + 0.2 ≠ 0.3` float-da. Points integer saxlanmalıdır (cents kimi: 100 point = $1.00).

---

## İntervyu Sualları

**1. Ledger yanaşması niyə daha yaxşıdır?**
Mutable balance race condition yaradır (overdraft), tarix itirilir, fraud aşkarlanmır. Ledger: hər dəyişiklik append-only — audit trail tam, rollback compensating transaction ilə mümkün, concurrent-safe (FOR UPDATE ilə).

**2. Race condition necə önlənir?**
`SELECT ... FOR UPDATE` + DB transaction. Eyni user üçün paralel burn cəhdi bloklanır. Lock release olana qədər ikinci transaction gözləyir. Sonra fresh balance oxuyur — overdraft mümkün deyil.

**3. Expiry necə işləyir, niyə "expire" transaction yazılır?**
Expire = mutable silmə deyil, compensating transaction. Ledger integrity qorunur — əgər expire "silinsəydi" tarix pozulardı. Expire transaction: hansı earn expire oldu, nə qədər balance azaldı — audit trail-dədir.

---

## Points Earning Qaydaları

*Bu kod sifariş məbləği və istifadəçi səviyyəsinə görə xal hesablayan kalkulyatoru, event-driven xal qazanımını və idempotent `earn` metodunu göstərir:*

```php
// Hər alışda neçə point qazanılır? Konfiqurasiya edilə bilən qaydalar

class PointsEarningCalculator
{
    // Sifariş məbləğinə görə point hesabla
    // Standart: hər $1 = 10 point
    public function calculate(int $orderTotalCents, string $userTier): int
    {
        $basePoints = (int) ($orderTotalCents / 100 * 10); // $1 = 10 point

        // Tier multiplier
        $multiplier = match($userTier) {
            'gold'   => 2.0,
            'silver' => 1.5,
            'bronze' => 1.0,
            default  => 1.0,
        };

        return (int) ($basePoints * $multiplier);
    }
}

// Event-driven: order confirmed → points earn
class OrderConfirmedListener
{
    public function handle(OrderConfirmed $event): void
    {
        $order = Order::find($event->orderId);
        $user  = User::find($order->user_id);

        $points = app(PointsEarningCalculator::class)
            ->calculate($order->total_cents, $user->tier);

        app(LoyaltyService::class)->earn(
            userId:        $user->id,
            points:        $points,
            referenceType: 'order',
            referenceId:   $order->id,
        );
    }
}

// Idempotency: eyni order iki dəfə earn etməsin
class LoyaltyService
{
    public function earn(int $userId, int $points, string $referenceType, int $referenceId): PointsTransaction
    {
        // Unikallıq: bir referenceType+referenceId üçün yalnız bir earn
        $existing = PointsTransaction::where([
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'type'           => 'earn',
            'user_id'        => $userId,
        ])->first();

        if ($existing) return $existing; // Idempotent

        return DB::transaction(function () use ($userId, $points, $referenceType, $referenceId) {
            $balance = $this->getBalanceForUpdate($userId);
            return PointsTransaction::create([
                'user_id'        => $userId,
                'type'           => 'earn',
                'points'         => $points,
                'balance_after'  => $balance + $points,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'expires_at'     => now()->addYear(),
            ]);
        });
    }
}
```

---

## İntervyu Sualları

**4. Points earn idempotency-si necə təmin edilir?**
Eyni order üçün event listener iki dəfə çağırılsa (at-least-once delivery) dublikat earn baş verər. Həll: `points_transactions` cədvəlində `(user_id, reference_type, reference_id, type)` UNIQUE constraint. Yaxud `earn()` metodunda əvvəlcə mövcudluq yoxla — mövcuddursa eyni nəticəni qaytar.

**5. Böyük sistemdə balance hesablamaq performanslıdır?**
`balance_after` sütunu son ledger transaction-ının balansı saxlayır — bütün tarixçəni SUM etmək lazım deyil. Yalnız ən son transaction-ın `balance_after`-ini al. Bu O(1) oxuma. Şübhə yaranarsa: `user_id` üzərindəki index + `ORDER BY id DESC LIMIT 1` çox sürətlidir.

---

## Anti-patternlər

**1. Balance-ı mutable column kimi saxlamaq**
`UPDATE users SET points = points + 100` əvəzinə birbaşa `SET points = 1500` etmək — concurrent request-lərdə race condition: iki paralel update bir-birinin dəyərini üzərinə yazır. Bütün dəyişikliklər ledger transaction-ı kimi append edilməli, balance cəm kimi hesablanmalıdır.

**2. Expiry yoxlamasını application layer-də etmək**
Expire olmuş point-ləri kod tərəfindən filtreləmək — developer bunu bir yerdə etməyi unutsa expire olmuş point-lər xərclənə bilər. Expiry `WHERE expires_at IS NULL OR expires_at > NOW()` şərti DB sorğusunun özündə olmalıdır.

**3. Burn əməliyyatında FIFO sırasını gözləməmək**
Xərclənəcək point-ləri hansısa sifarişdə seçmək — müştərinin ən tez expire olacaq point-ləri yanır, uzun ömürlü point-lər qalır, amma müştəri bunu bilmir. Burn həmişə `expires_at ASC` sırasıyla FIFO əsasında aparılmalıdır.

**4. Partial burn-ü transaction olmadan etmək**
Çox mənbədən point xərcləyərkən (3 müxtəlif earn transaction) hər birini ayrı DB sorğusunda silmək — biri fail olsa qismən deduction baş verir, balance yanlış qalır. Bütün burn transaction-ları tək DB transaction-ı içindədir.

**5. Refund zamanı orijinal point-ləri restore etməmək**
Sifarişi ləğv edərkən xərclənmiş point-ləri geri qaytarmamaq — müştəri həm pulunu, həm point-lərini itirir. Refund compensating credit transaction yazmalı, orijinal burn transaction-a `reversed_transaction_id` ilə link etməlidir.

**6. Point balansını cache-dən oxuyub lock olmadan deduct etmək**
`Cache::get('user_balance')` ilə balansı oxuyub sonra DB-dən deduct etmək — cache stale ola bilər, real balansdan çox point xərclənir (overdraft). Deduct əməliyyatı həmişə `SELECT ... FOR UPDATE` ilə DB-dən fresh balans oxuyaraq aparılmalıdır.
