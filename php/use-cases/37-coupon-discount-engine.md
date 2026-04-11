# Coupon & Discount Engine

## Problem necə yaranır?

1. **Race condition (limit aşılması):** 100 istifadəçi eyni anda son kuponu istifadə etməyə çalışır. Hər biri `uses_count < max_uses` yoxlayır — hamısı true alır. Hamısı decrement edir → limit aşılır, 100 ödəniş əvəzinə 1 yer üçün.
2. **Validation bypass:** Kuponun limitini yoxlayıb, sonra ayrı əməliyyatda increment etmək (check-then-act anti-pattern) — aradakı window-da başqası ötüb keçə bilər.
3. **Stacking abuse:** İki 50% kupon birlikdə istifadə edilsə — tam pulsuz alış-veriş. Qaydalar olmadan arbitraj mümkündür.
4. **Negative total:** `$10` kupon `$5`-lik sifarişə tətbiq edilsə `final_total = -$5`. `max(0, total)` zəruridir.

---

## Discount növləri

```
PERCENTAGE  — 20% endirim (max_discount cap-i ola bilər: max $50)
FIXED       — $10 endirim (sifariş məbləğindən az olmalıdır)
FREE_SHIP   — pulsuz çatdırılma
BOGO        — al 2, öd 1 (item-level hesab)

Stacking:
  ❌ İki percentage birlikdə: double dipping
  ✅ Percentage + FREE_SHIP: fərqli kateqoriya
  ✅ Loyalty points + kupon: fərqli sistem
```

---

## İmplementasiya

*Bu kod kupon validasiyasını, stacking qaydalarını, endirim hesablamasını və race condition-dan qorunan atomik redeem əməliyyatını göstərir:*

```php
class Coupon extends Model
{
    public function isValid(User $user, Cart $cart): CouponValidationResult
    {
        if ($this->expires_at?->isPast()) {
            return CouponValidationResult::fail('Kupon müddəti bitib');
        }

        if ($this->starts_at?->isFuture()) {
            return CouponValidationResult::fail('Kupon hələ aktiv deyil');
        }

        // uses_count yoxlaması approximate-dir — real limit DB-level atomic increment-dədir
        if ($this->max_uses && $this->uses_count >= $this->max_uses) {
            return CouponValidationResult::fail('Kupon limiti dolub');
        }

        $userUses = CouponUsage::where('coupon_id', $this->id)
            ->where('user_id', $user->id)
            ->count();

        if ($userUses >= ($this->max_uses_per_user ?? 1)) {
            return CouponValidationResult::fail('Bu kuponu artıq istifadə etmisiniz');
        }

        if ($this->min_order_amount && $cart->total < $this->min_order_amount) {
            return CouponValidationResult::fail("Minimum sifariş: {$this->min_order_amount}");
        }

        if ($this->user_id && $this->user_id !== $user->id) {
            return CouponValidationResult::fail('Bu kupon sizə aid deyil');
        }

        return CouponValidationResult::success();
    }
}

class DiscountEngine
{
    public function apply(Cart $cart, array $couponCodes, User $user): DiscountResult
    {
        $coupons = Coupon::whereIn('code', $couponCodes)->get();
        $errors  = [];

        // Stacking validation: iki percentage birlikdə işləmir
        if ($coupons->where('type', 'percentage')->count() > 1) {
            return DiscountResult::fail('Birdən çox faizli kupon istifadə edilə bilməz');
        }

        $discounts = [];
        foreach ($coupons as $coupon) {
            $validation = $coupon->isValid($user, $cart);
            if (!$validation->passed) {
                $errors[] = "{$coupon->code}: {$validation->message}";
                continue;
            }
            $discounts[] = $this->calculateDiscount($coupon, $cart);
        }

        $totalDiscount = collect($discounts)->sum('amount');

        return new DiscountResult(
            discounts:     $discounts,
            totalDiscount: $totalDiscount,
            finalTotal:    max(0, $cart->total - $totalDiscount), // Negative total önlənir
            errors:        $errors,
        );
    }

    private function calculateDiscount(Coupon $coupon, Cart $cart): array
    {
        $amount = match($coupon->type) {
            'percentage' => (int) ($cart->total * $coupon->value / 100),
            'fixed'      => min($coupon->value, $cart->total), // Sifariş məbləğini keçə bilməz
            'free_ship'  => $cart->shipping_cost,
            default      => 0,
        };

        // max_discount cap: 20% kupon, lakin max $50 endirim
        if ($coupon->max_discount) {
            $amount = min($amount, $coupon->max_discount);
        }

        return ['coupon_id' => $coupon->id, 'code' => $coupon->code, 'amount' => $amount];
    }
}

// Race condition həlli — atomic WHERE + increment
class CouponRedeemService
{
    public function redeem(Coupon $coupon, int $userId, int $orderId): void
    {
        DB::transaction(function () use ($coupon, $userId, $orderId) {
            // WHERE şərti + INCREMENT eyni SQL-də: limit keçilsə 0 row affected
            // Bu iki ayrı SELECT+UPDATE-dən fərqli olaraq atomic-dir
            $updated = DB::table('coupons')
                ->where('id', $coupon->id)
                ->where(function ($q) {
                    $q->whereNull('max_uses')
                      ->orWhereRaw('uses_count < max_uses');
                })
                ->increment('uses_count');

            if (!$updated) {
                throw new CouponLimitReachedException('Kupon limiti dolub');
            }

            CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id'   => $userId,
                'order_id'  => $orderId,
                'used_at'   => now(),
            ]);
        });
    }
}
```

---

## Validation vs Redeem ayrılığı

`isValid()` — approximate yoxlama (sürətli, cache-lənə bilər). Actual redeem zamanı atomic DB check aparılır. Bu iki mərhələli yanaşma:
- Checkout prosesinin əvvəlində `isValid()` ilə UI-da error göstər
- Sifariş confirm edilərkən `redeem()` ilə atomic limit yoxlama + increment

Aradakı window-da limit dolsa: `redeem()` exception atır, user xəta görür — amma charge edilmir.

---

## Anti-patterns

- **Check-then-act (non-atomic):** `if ($coupon->uses_count < $coupon->max_uses) { increment() }` — arada başqası keçər. WHERE şərtli atomic increment mütləqdir.
- **Validation-da uses_count-a güvənmək:** Cache stale ola bilər, real limit DB atomic increment-dədir.
- **Negative total-ı nəzərə almamaq:** `$10` kupon `$5`-lik sifarişdə `final_total = -$5` → pul qaytarılır.

---

## İntervyu Sualları

**1. Kupon limit race condition necə önlənir?**
Check-then-act anti-pattern: SELECT (check) + UPDATE (increment) ayrı — arada başqası ötər. Həll: WHERE şərti + INCREMENT eyni atomic SQL — `uses_count < max_uses` şərti UPDATE-in özündədir, 0 row affected = limit dolub.

**2. Stacking qaydaları necə dizayn edilir?**
Type-based rule: iki percentage bir arada olmaz. Kombinasiya matrix: `allowed_combinations` cədvəlində hansi type-lar birlikdə işləyər. Apply-dan əvvəl validate — partial discount vermə.

**3. max_discount cap niyə lazımdır?**
20% kupon böyük sifarişdə çox böyük endirim verə bilər: $1000 sifariş → $200 endirim. `max_discount: 50` ilə cap: sifariş nə qədər böyük olsa da max $50 endirim.

---

## Kupon Növləri və DB Schema

*Bu kod kupon və kupon istifadə jurnalı üçün DB schema strukturunu göstərir:*

```php
// Schema — kuponun bütün parametrlərini saxlayan flexible dizayn
Schema::create('coupons', function (Blueprint $table) {
    $table->id();
    $table->string('code', 50)->unique();      // SAVE20, WELCOME10
    $table->enum('type', ['percentage', 'fixed', 'free_ship', 'bogo']);
    $table->integer('value');                  // 20 (faiz üçün), 1000 (sent, fixed üçün)
    $table->integer('max_discount')->nullable(); // Faiz kupon üçün max cap (sent)
    $table->integer('min_order_amount')->nullable(); // Minimum sifariş (sent)
    $table->integer('max_uses')->nullable();         // Ümumi limit
    $table->integer('uses_count')->default(0);
    $table->integer('max_uses_per_user')->default(1);
    $table->unsignedBigInteger('user_id')->nullable(); // Personal kupon
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->json('applicable_categories')->nullable(); // Yalnız bu kateqoriyalara
    $table->json('applicable_products')->nullable();   // Yalnız bu məhsullara
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users');
    $table->index(['code', 'expires_at']);
});

// coupon_usages — kim, nə vaxt, hansı sifarişdə istifadə etdi
Schema::create('coupon_usages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coupon_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('order_id')->constrained();
    $table->integer('discount_amount'); // Faktiki endirim məbləği (sent)
    $table->timestamp('used_at');
    $table->unique(['coupon_id', 'order_id']); // Bir sifarişdə eyni kupon bir dəfə
});
```

---

## Category/Product-spesifik Kuponlar

*Bu kod kuponu yalnız uyğun kateqoriya/məhsula tətbiq edən discount engine metodunu göstərir:*

```php
class DiscountEngine
{
    private function calculateDiscount(Coupon $coupon, Cart $cart): array
    {
        // Kupon yalnız müəyyən kateqoriyalara aiddirsə
        $eligibleTotal = $this->getEligibleTotal($coupon, $cart);

        $amount = match($coupon->type) {
            'percentage' => (int) ($eligibleTotal * $coupon->value / 100),
            'fixed'      => min($coupon->value, $eligibleTotal),
            'free_ship'  => $cart->shipping_cost,
            default      => 0,
        };

        if ($coupon->max_discount) {
            $amount = min($amount, $coupon->max_discount);
        }

        return ['coupon_id' => $coupon->id, 'code' => $coupon->code, 'amount' => $amount];
    }

    private function getEligibleTotal(Coupon $coupon, Cart $cart): int
    {
        if (empty($coupon->applicable_categories) && empty($coupon->applicable_products)) {
            return $cart->total; // Hamısına aiddir
        }

        return $cart->items
            ->filter(function ($item) use ($coupon) {
                $categoryMatch = empty($coupon->applicable_categories)
                    || in_array($item->category_id, $coupon->applicable_categories);
                $productMatch = empty($coupon->applicable_products)
                    || in_array($item->product_id, $coupon->applicable_products);
                return $categoryMatch && $productMatch;
            })
            ->sum('subtotal');
    }
}
```

---

## İntervyu Sualları

**4. Kupon redeem atomic-liyi necə sağlanır?**
Check-then-act anti-pattern: `SELECT uses_count` + `if < max` + `UPDATE uses_count++` — iki ayrı SQL, arada başqası keçər. Atomic həll: `UPDATE coupons SET uses_count = uses_count + 1 WHERE id = ? AND (max_uses IS NULL OR uses_count < max_uses)`. Affected rows = 0 → limit dolub. Bu bir SQL əməliyyatı, atomic-dir.

**5. Personal kupon nədir, necə implementasiya olunur?**
`user_id` sütunu NULL olmayan kupan yalnız həmin user-ə aiddir. Validation: `if ($coupon->user_id && $coupon->user_id !== $user->id)` → fail. İstifadə halları: referral kodu, ilk alış kuponu, müştəri kompensasiyası. Personal kuponun `max_uses_per_user = 1` olması standartdır.

---

## Anti-patternlər

**1. Kupon kodunu case-sensitive müqayisə etmək**
`WHERE code = 'SAVE20'` — müştəri `save20` yazdıqda kod tapılmır, support ticket açılır. Kupon kodları DB-də uppercase saxlanmalı, input `strtoupper()` ilə normalize edilməlidir.

**2. Endirimi sifariş cəminə deyil, hər məhsula ayrıca tətbiq etmək**
`$item->price * 0.8` hər item üçün fərdi hesablamaq — rounding xətaları toplanır, real endirim gözlənilən məbləğdən fərqlənir. Endirim `subtotal`-a bir dəfə tətbiq edilməli, sonra məhsullara proporsional paylanmalıdır.

**3. Kupon istifadəsini asinxron/queue ilə artırmaq**
`uses_count` artımını background job-a göndərmək — job geciksə eyni kuponu limit dolmadan onlarla nəfər istifadə edə bilər. `uses_count` artımı atomic DB əməliyyatı ilə sinxron aparılmalıdır.

**4. `max_discount` cap-i olmayan faiz kuponları**
`20% endirim` kuponu məbləği məhdudlaşdırmadan tətbiq etmək — $10,000-lik sifarişdə $2,000 endirim gedir. Bütün faiz kuponlarında `max_discount_amount` sahəsi məcburi olmalı, hesablama `min($calculated, $max)` ilə cap edilməlidir.

**5. Kupon validation-ı payment-dən çox əvvəl bir dəfə etmək**
Kupon əlavə edildikdə validate edib payment zamanı yenidən yoxlamamaq — aradakı zamanda kupon expire ola bilər, limiti dola bilər. Endirim məbləği payment initiation-da yenidən hesablanmalı, uyğunsuzluq varsa istifadəçiyə bildirilməlidir.

**6. Stacking qaydaları olmadan çoxlu kupon qəbul etmək**
İstifadəçinin istədiyi qədər kupon tətbiq etməsinə icazə vermək — iki 50% kupon sıfır məbləğ verə bilər. Kupon tipləri (percentage, fixed, shipping) üçün stacking qaydaları müəyyən edilməli, hər yeni kupon əlavə edilərkən kombinasiya validasiyası keçirilməlidir.
