# Multi-step Checkout Flow

## Problem necə yaranır?

Checkout bir neçə mərhələdən ibarətdir: cart → address → payment → confirmation. Bu prosesdə bir sıra kritik problemlər yaranır:

1. **Invalid state transition:** Confirmed sifariş yenidən payment almağa çalışılır. Ya da address seçilmədən payment başlanır.
2. **Concurrent request-lər:** User düyməyə iki dəfə basar — eyni checkout üçün iki payment cəhdi.
3. **Partial failure:** Payment uğurlu olur, lakin inventory reservation crash-dən sonra tamamlanmır. Pul alındı, stok azalmadı.
4. **Double charge:** Network timeout-dan sonra client eyni payment-i yenidən göndərir.

---

## State Machine

State machine olmadan hər yerdə `if status == 'paid'` şərtləri yayılır, logic duplicate olur, invalid transition-lar aşkarlanmır.

```
CART → ADDRESS_SELECTED → PAYMENT_PENDING → CONFIRMED
                                  ↓
                           PAYMENT_FAILED → (retry → PAYMENT_PENDING)
                                  ↓
                              CANCELLED
```

Hər transition explicit metodla idarə olunur. `assertStatus` qeyd-şərtsiz yoxlayır — yanlış state-dən çağırılsa exception.

---

## İmplementasiya

*Bu kod checkout state machine-ni, ödəniş prosesini və idempotency ilə atomik sifariş yaradılmasını göstərir:*

```php
enum CheckoutStatus: string
{
    case CART             = 'cart';
    case ADDRESS_SELECTED = 'address_selected';
    case PAYMENT_PENDING  = 'payment_pending';
    case CONFIRMED        = 'confirmed';
    case PAYMENT_FAILED   = 'payment_failed';
    case CANCELLED        = 'cancelled';
}

class Checkout extends Model
{
    protected $casts = [
        'status' => CheckoutStatus::class,
        'data'   => 'array',
    ];

    public function selectAddress(Address $address): void
    {
        $this->assertStatus(CheckoutStatus::CART);
        $this->update([
            'status'           => CheckoutStatus::ADDRESS_SELECTED,
            'shipping_address' => $address->toArray(),
        ]);
    }

    public function initiatePayment(): void
    {
        $this->assertStatus(CheckoutStatus::ADDRESS_SELECTED);
        $this->update(['status' => CheckoutStatus::PAYMENT_PENDING]);
    }

    public function confirm(string $paymentId): void
    {
        $this->assertStatus(CheckoutStatus::PAYMENT_PENDING);
        $this->update([
            'status'     => CheckoutStatus::CONFIRMED,
            'payment_id' => $paymentId,
        ]);
    }

    public function failPayment(string $reason): void
    {
        $this->assertStatus(CheckoutStatus::PAYMENT_PENDING);
        $this->update(['status' => CheckoutStatus::PAYMENT_FAILED, 'failure_reason' => $reason]);
    }

    public function retryPayment(): void
    {
        $this->assertStatus(CheckoutStatus::PAYMENT_FAILED);
        $this->update(['status' => CheckoutStatus::PAYMENT_PENDING]);
    }

    private function assertStatus(CheckoutStatus $expected): void
    {
        if ($this->status !== $expected) {
            throw new InvalidCheckoutStateException(
                "Expected {$expected->value}, got {$this->status->value}"
            );
        }
    }
}

class CheckoutService
{
    public function processPayment(string $checkoutId, array $paymentData): array
    {
        $checkout = Checkout::findOrFail($checkoutId);
        $checkout->initiatePayment();

        // checkout_id-dən idempotency key — eyni checkout üçün double charge olmaz
        $idempotencyKey = "checkout-payment-{$checkoutId}";

        try {
            $payment = $this->paymentGateway->charge([
                ...$paymentData,
                'amount'          => $checkout->total,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Confirm + order + inventory — hamısı atomik
            DB::transaction(function () use ($checkout, $payment) {
                $checkout->confirm($payment['id']);
                $this->createOrder($checkout);        // Order yaradılır
                $this->reserveInventory($checkout);   // Stok azaldılır
            });

            return ['status' => 'confirmed', 'order_id' => $checkout->order_id];

        } catch (PaymentDeclinedException $e) {
            $checkout->failPayment($e->getMessage());
            return ['status' => 'payment_failed', 'reason' => $e->getMessage()];
        }
    }
}
```

---

## Partial Failure — Niyə Yaranır?

Payment uğurlu olur (external API cavabı alındı), lakin `DB::transaction` içindəki `reserveInventory` exception atır. Transaction rollback olur — checkout CONFIRMED deyil, lakin pul gateway-də alınıb.

**Həll:**
1. Payment gateway-ə idempotency key göndər — retry edildikdə eyni nəticəni qaytarır
2. Checkout state: `PAYMENT_PENDING` qalır — retry mümkündür
3. Gateway-dən ödənişi geri al (refund) ya da async reconciliation ilə düzəlt
4. Outbox pattern: DB commit olsa order/inventory event-ləri mütləq işlənir

---

## Concurrent Request Problemi

User düyməyə iki dəfə basar → iki paralel `processPayment` çağırışı.

İlk çağırış `initiatePayment()` çağırır — status `ADDRESS_SELECTED → PAYMENT_PENDING`.
İkinci çağırış da `initiatePayment()` çağırır — status artıq `PAYMENT_PENDING`, `assertStatus(ADDRESS_SELECTED)` exception atır.

Əlavə qoruma üçün: DB-dəki checkout-u `lockForUpdate()` ilə al — race condition tamamilə önlənir.

---

## Anti-patterns

- **State yoxlamadan payment başlatmaq:** Confirmed checkout-a yenidən charge edilə bilər.
- **Payment + DB write-ı ayrı transaction-larda etmək:** Gateway uğurlu, DB fail → pul alındı, order yoxdur.
- **Idempotency key olmadan payment göndərmək:** Timeout → retry → double charge.

---

## İntervyu Sualları

**1. Checkout state machine niyə vacibdir?**
Invalid transition-ları önləyir: confirmed sifarişə yenidən ödəniş alınmır. Concurrent request-lərdə consistency: eyni checkout iki dəfə payment pending-ə keçə bilməz. Audit trail: hər transition log-a düşür.

**2. Payment + inventory atomikliyi necə təmin edilir?**
Payment external API — DB transaction-a daxil edilə bilməz. Strategiya: önce payment, sonra DB transaction (confirm + order + inventory). DB fail olsa: checkout `PAYMENT_PENDING`-də qalır, payment gateway-dəki ödəniş ya refund edilir, ya da reconciliation job həll edir.

**3. Double charge necə önlənir?**
`checkout_id`-dən idempotency key yaradılır. Gateway eyni key ilə ikinci dəfə çağırılsa eyni nəticəni qaytarır, ikinci charge etmir. State machine: `PAYMENT_PENDING` state-dəki checkout yenidən `initiatePayment` çağırırsa exception.

---

## Soft Reserve — Stok Müvəqqəti Bloklaması

*Bu kod stoku müvəqqəti bloklayan soft reserve mexanizmini və vaxtı keçmiş checkout-ları təmizləyən scheduled command-ı göstərir:*

```php
// Checkout başladıqda stok "soft reserve" edilir (inventory azalmır, sadece bloklanır)
// TTL bitdikdə avtomatik buraxılır

class SoftReserveService
{
    private const RESERVE_TTL = 900; // 15 dəqiqə

    public function reserve(string $checkoutId, int $productId, int $qty): bool
    {
        return DB::transaction(function () use ($checkoutId, $productId, $qty) {
            $inventory = Inventory::where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyReserved = StockReservation::where('product_id', $productId)
                ->where('expires_at', '>', now())
                ->sum('quantity');

            $available = $inventory->quantity - $alreadyReserved;

            if ($available < $qty) {
                return false; // Yetərsiz stok
            }

            StockReservation::updateOrCreate(
                ['checkout_id' => $checkoutId, 'product_id' => $productId],
                [
                    'quantity'   => $qty,
                    'expires_at' => now()->addSeconds(self::RESERVE_TTL),
                ]
            );

            return true;
        });
    }

    public function release(string $checkoutId): void
    {
        // Checkout abandon edildi / expired — rezervasiyaları burax
        StockReservation::where('checkout_id', $checkoutId)->delete();
    }
}

// Abandoned checkout cleanup — scheduled command
class ExpireAbandonedCheckoutsCommand extends Command
{
    public function handle(): void
    {
        // 24 saatdan köhnə PAYMENT_PENDING checkoutları bitir
        Checkout::where('status', CheckoutStatus::PAYMENT_PENDING)
            ->where('updated_at', '<', now()->subHours(24))
            ->get()
            ->each(function (Checkout $checkout) {
                DB::transaction(function () use ($checkout) {
                    $checkout->update(['status' => CheckoutStatus::CANCELLED]);
                    app(SoftReserveService::class)->release($checkout->id);
                    // Refund if needed
                });
            });
    }
}
```

---

## Anti-patternlər

**1. Checkout state-ini DB-də yox, session-da saxlamaq**
Checkout vəziyyətini yalnız PHP session-da idarə etmək — server restart, session expire və ya load balancer fərqli node-a yönləndirdikdə state itirilir. Checkout state DB-də saxlanmalı, hər transition atomik şəkildə qeydə alınmalıdır.

**2. Payment gateway-i DB transaction-ın içinə salmaq**
`DB::transaction()` bloku içindən Stripe/PayPal API-ə çağırış etmək — gateway timeout-u (5-10s) DB lock-unu o qədər saxlayır, bütün digər checkout-lar bloklanır. Payment əvvəl gateway-ə göndərilməli, nəticə sonra DB transaction-da qeydə alınmalıdır.

**3. Hər checkout addımında stok yoxlamayıb yalnız sonda bloklamaq**
İstifadəçi bütün addımları keçib ödəmə mərhələsindəykən "stok yoxdur" xətası alırsa UX fəlakətdir. Stok availability hər kritik addımda yoxlanılmalı, checkout başladıqda isə soft-reserve tətbiq edilməlidir.

**4. Idempotency key-i checkout-dan deyil, client-dən gözləmək**
Client-dən gələn ixtiyari idempotency key-ə güvənmək — eyni user fərqli device-dan eyni key göndərə bilər. Key `checkout_id` + `attempt_number`-dən server tərəfindən generasiya edilməli, user-specific namespace ilə qorunmalıdır.

**5. Abandoned checkout-ları heç vaxt təmizləməmək**
PAYMENT_PENDING vəziyyətdə qalan checkout-ları sonsuza qədər saxlamaq — DB şişir, stok reserve-ləri sərbəst buraxılmır. Scheduled job ilə müəyyən müddət (24 saat) sonra EXPIRED state-inə keçirilməli, inventory unlock edilməlidir.

**6. Concurrent checkout request-lərini lock olmadan emal etmək**
Eyni checkout-a paralel iki request gəldikdə hər ikisi `PENDING → PAYMENT_PENDING` keçidini eyni anda etməyə çalışır — double charge riski. `SELECT ... FOR UPDATE` ilə checkout record lock-lanmalı, yalnız biri keçid edə bilməlidir.
