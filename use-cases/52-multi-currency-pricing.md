# Multi-currency Pricing & FX Conversion (Senior)

## Problem
- E-commerce platform: USD, EUR, AZN, RUB
- User-in seçdiyi currency-də qiymət göstər
- Cart total currency-aware hesabla
- Order finalize-də exchange rate "lock" edilməlidir
- Refund original currency-də

---

## Həll: Money value object + Rate provider + DB schema

```
Frontend ──→ Currency seç (cookie/session)
                ↓
Product.toMoney(currency) → Display price
                ↓
Cart total → Sum in selected currency
                ↓
Order finalize → Snapshot rate (fx_rate column)
                ↓
Refund → Reverse using snapshotted rate
```

---

## 1. Money value object

```php
<?php
namespace App\Domain;

final class Money
{
    public function __construct(
        public readonly int $cents,    // 100 = $1.00 (avoid float bugs)
        public readonly Currency $currency,
    ) {
        if ($cents < 0) throw new \DomainException('Negative money');
    }
    
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }
    
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        if ($other->cents > $this->cents) {
            throw new \DomainException('Negative result');
        }
        return new self($this->cents - $other->cents, $this->currency);
    }
    
    public function multiply(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }
    
    public function convert(Currency $to, float $rate): self
    {
        if ($this->currency === $to) return $this;
        return new self((int) round($this->cents * $rate), $to);
    }
    
    public function format(): string
    {
        $amount = number_format($this->cents / 100, 2);
        return "{$this->currency->symbol()}{$amount}";
    }
    
    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException(
                "Currency mismatch: {$this->currency->value} vs {$other->currency->value}"
            );
        }
    }
}

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case AZN = 'AZN';
    case RUB = 'RUB';
    
    public function symbol(): string
    {
        return match($this) {
            self::USD => '$',
            self::EUR => '€',
            self::AZN => '₼',
            self::RUB => '₽',
        };
    }
}
```

---

## 2. FX Rate provider (cached)

```php
<?php
namespace App\Services;

class FxRateProvider
{
    public function __construct(
        private HttpClient $http,
        private CacheInterface $cache,
    ) {}
    
    public function rate(Currency $from, Currency $to, ?\DateTime $at = null): float
    {
        if ($from === $to) return 1.0;
        
        $at ??= new \DateTime();
        $dateKey = $at->format('Y-m-d');
        $cacheKey = "fx:$dateKey:{$from->value}:{$to->value}";
        
        return $this->cache->remember($cacheKey, 3600, function () use ($from, $to, $at) {
            return $this->fetchFromProvider($from, $to, $at);
        });
    }
    
    private function fetchFromProvider(Currency $from, Currency $to, \DateTime $at): float
    {
        // Provider: openexchangerates.org, exchangerate-api.com, fixer.io
        $response = $this->http->get('https://api.exchangerate.host/historical', [
            'date' => $at->format('Y-m-d'),
            'base' => $from->value,
            'symbols' => $to->value,
        ]);
        
        $data = $response->json();
        return (float) $data['rates'][$to->value];
    }
}
```

```php
<?php
// Cron — daily rate fetch (rate provider failure-ə qarşı)
Schedule::call(function (FxRateProvider $fx) {
    foreach (Currency::cases() as $from) {
        foreach (Currency::cases() as $to) {
            $rate = $fx->rate($from, $to);
            DB::table('fx_rates')->updateOrInsert(
                ['from_ccy' => $from->value, 'to_ccy' => $to->value, 'date' => today()],
                ['rate' => $rate]
            );
        }
    }
})->dailyAt('06:00');
```

---

## 3. Product display

```php
<?php
class Product extends Model
{
    protected $casts = [
        'price_cents' => 'int',
        'base_currency' => Currency::class,
    ];
    
    public function priceIn(Currency $currency, FxRateProvider $fx): Money
    {
        $base = new Money($this->price_cents, $this->base_currency);
        $rate = $fx->rate($this->base_currency, $currency);
        return $base->convert($currency, $rate);
    }
}

// Controller
class ProductController
{
    public function show(Product $product, FxRateProvider $fx, Request $req): JsonResponse
    {
        $userCcy = Currency::from($req->session('currency', 'USD'));
        
        $price = $product->priceIn($userCcy, $fx);
        
        return response()->json([
            'id'    => $product->id,
            'name'  => $product->name,
            'price' => [
                'cents'    => $price->cents,
                'currency' => $price->currency->value,
                'formatted'=> $price->format(),
            ],
        ]);
    }
}
```

---

## 4. Order finalize (rate snapshot)

```sql
-- orders table
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    
    -- Original (snapshot zamanı)
    base_currency VARCHAR(3),         -- USD (kataloq currency)
    base_amount_cents BIGINT,          -- USD-də cəm
    
    -- User-in ödədiyi
    paid_currency VARCHAR(3),         -- AZN
    paid_amount_cents BIGINT,         -- AZN-də cəm
    fx_rate DECIMAL(15, 8),            -- 1 USD = 1.7 AZN (snapshot)
    
    status VARCHAR(20),
    created_at TIMESTAMP,
    INDEX (user_id, created_at)
);
```

```php
<?php
class CheckoutService
{
    public function finalize(Cart $cart, Currency $payCurrency, FxRateProvider $fx): Order
    {
        DB::beginTransaction();
        try {
            $baseTotal = $cart->totalIn(Currency::USD);   // catalog default
            $rate = $fx->rate(Currency::USD, $payCurrency);
            $paidTotal = $baseTotal->convert($payCurrency, $rate);
            
            $order = Order::create([
                'user_id'           => $cart->user_id,
                'base_currency'     => 'USD',
                'base_amount_cents' => $baseTotal->cents,
                'paid_currency'     => $payCurrency->value,
                'paid_amount_cents' => $paidTotal->cents,
                'fx_rate'           => $rate,
                'status'            => 'pending',
            ]);
            
            // Charge user in their currency
            $payment = $this->paymentGateway->charge(
                $paidTotal->cents,
                $payCurrency->value
            );
            
            $order->update(['status' => 'paid', 'payment_id' => $payment->id]);
            DB::commit();
            
            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

---

## 5. Refund (snapshot rate istifadə)

```php
<?php
class RefundService
{
    public function refund(Order $order, ?int $amountCents = null): Refund
    {
        // Default: full refund
        $refundCents = $amountCents ?? $order->paid_amount_cents;
        
        // CRITICAL: original rate ilə refund (today rate ilə deyil!)
        // User AZN ödəyib, AZN qaytaracağıq
        // Yeni rate-də biz mənfi ola bilərik
        
        $payment = $this->paymentGateway->refund(
            $order->payment_id,
            $refundCents,    // paid currency-də
            $order->paid_currency
        );
        
        return Refund::create([
            'order_id'        => $order->id,
            'amount_cents'    => $refundCents,
            'currency'        => $order->paid_currency,
            'payment_id'      => $payment->id,
            'fx_rate_at_refund' => $order->fx_rate,  // snapshot
        ]);
    }
}
```

---

## 6. Display historical orders (user own currency)

```php
<?php
// User AZN-də ödəyib, AZN-də göstər
public function showOrder(Order $order): View
{
    return view('orders.show', [
        'order' => $order,
        'amount' => new Money($order->paid_amount_cents, Currency::from($order->paid_currency)),
        // 199.50 AZN
    ]);
}

// Hesabat üçün USD-yə convert
public function adminReport(\DateTime $from, \DateTime $to): array
{
    // Bütün order-lar USD-də
    $orders = Order::whereBetween('created_at', [$from, $to])->get();
    
    $totalUsd = $orders->sum(fn($o) => $o->base_amount_cents);
    
    return [
        'total_usd_cents' => $totalUsd,
        'count' => $orders->count(),
    ];
}
```

---

## 7. Pitfalls

```
❌ Float for money
   $price = 0.1 + 0.2;  // 0.30000000000000004 — bug!
   ✓ Integer cents (100 = $1.00)

❌ Today rate for old refund
   User 100 AZN ödəyib (1 USD = 1.7), bir ay sonra refund (1 USD = 2.0)
   Today rate ilə: 50 USD * 2.0 = 100 AZN ✓ (lucky)
   Amma rate kəsk dəyişərsə (1.5): 50 USD * 1.5 = 75 AZN — user 25 AZN itirir!
   ✓ Snapshot rate at order time

❌ Cart total mixed currency
   User 1 product USD, 1 product EUR seçib
   ✓ Convert all to single currency at cart level

❌ FX provider down → checkout fail
   ✓ Fallback: cached rate, last known good
   ✓ Daily DB-də saxla

❌ Currency rounding errors
   $0.005 → ($0.00 və ya $0.01?)
   ✓ Banker's rounding (round half to even)
   ✓ "Total = sum(rounded)" not "rounded(sum)"

❌ No currency support outside catalog list
   User Bitcoin-də ödəmək istəyir → not supported
   ✓ Whitelist enum + clear error

❌ Currency in URL/cache key unutmaq
   Cache: "product:42" → user A USD görür, user B AZN görür → conflict
   ✓ "product:42:USD" cache key
```

---

## 8. Performance

```
Rate fetch: cached daily (1 query per day per pair)
Hot path overhead: ~1µs (Redis cache)
Storage: 16 currencies × 16 currencies = 256 pairs × 365 days = 93k row/year
```
