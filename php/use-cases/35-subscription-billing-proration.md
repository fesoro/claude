# Subscription Billing & Proration

## Problem necə yaranır?

SaaS platformasında istifadəçi ay ortasında plan dəyişdirəndə mühasibat mürəkkəbləşir:

1. **Proration hesablanmaması:** İstifadəçi Starter plan üçün ödəyib, ay ortasında Pro-ya keçir, tam Pro qiyməti alınır — ədalətsiz.
2. **Renewal race condition:** İki renewal job eyni subscription üçün eyni anda işləyir → double charge.
3. **Failed payment infinity loop:** Ödəniş uğursuz olur, dərhal retry → yenidən fail → servis bank-ı spam edir, kart bloklanır.
4. **Idempotency olmadan renewal:** Server crash olsa yenidən işləyən renewal job ikinci charge yaradar.

---

## Billing Model

```
Starter: $29/ay → Pro: $99/ay

Ay ortasında (15/30 gün keçib) upgrade:
  Starter-dən istifadə edildi:    $29 × 15/30 = $14.50
  Starter krediti (unused):       $29 × 15/30 = $14.50
  Pro qalan günlər üçün:          $99 × 15/30 = $49.50
  Ödəniləcək:                     $49.50 - $14.50 = $35.00
```

---

## İmplementasiya

*Bu kod plan yüksəltmə zamanı proration hesablayan Subscription modelini və idempotency key ilə renewal job-unu göstərir:*

```php
class Subscription extends Model
{
    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'trial_ends_at'        => 'datetime',
    ];

    public function upgrade(Plan $newPlan): void
    {
        if ($newPlan->price <= $this->plan->price) {
            throw new \DomainException('Bu downgrade əməliyyatıdır');
        }

        DB::transaction(function () use ($newPlan) {
            $proration = $this->calculateProration($newPlan);

            if ($proration > 0) {
                Invoice::create([
                    'subscription_id' => $this->id,
                    'amount'          => $proration,
                    'type'            => 'proration',
                    'description'     => "Upgrade: {$this->plan->name} → {$newPlan->name}",
                    'due_at'          => now(),
                ]);
            }

            $this->update(['plan_id' => $newPlan->id]);
        });
    }

    // Proration hesablaması: qalan günlər nisbəti üzrə yeni plan - köhnə plan krediti
    public function calculateProration(Plan $newPlan): int
    {
        $today         = now();
        $periodEnd     = $this->current_period_end;
        $periodStart   = $this->current_period_start;
        $daysInPeriod  = $periodStart->diffInDays($periodEnd);
        $daysRemaining = $today->diffInDays($periodEnd);

        $unusedCredit = (int) ($this->plan->price * $daysRemaining / $daysInPeriod * 100);
        $newCharge    = (int) ($newPlan->price * $daysRemaining / $daysInPeriod * 100);

        return max(0, $newCharge - $unusedCredit);
    }
}

// Renewal job — idempotency key ilə double charge önlənir
class ProcessRenewalJob implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private int $subscriptionId) {}

    public function handle(): void
    {
        $sub = Subscription::findOrFail($this->subscriptionId);

        try {
            // Idempotency key: subscription + period — eyni ay üçün iki charge olmaz
            $payment = app(PaymentService::class)->charge(
                userId:          $sub->user_id,
                amount:          $sub->plan->price * 100,
                paymentMethodId: $sub->payment_method_id,
                idempotencyKey:  "renewal-{$sub->id}-{$sub->current_period_end->format('Ymd')}",
            );

            $sub->update([
                'current_period_start' => $sub->current_period_end,
                'current_period_end'   => $sub->current_period_end->addMonth(),
                'status'               => 'active',
            ]);

            Invoice::create([
                'subscription_id' => $sub->id,
                'payment_id'      => $payment->id,
                'amount'          => $sub->plan->price * 100,
                'type'            => 'renewal',
                'paid_at'         => now(),
            ]);

        } catch (PaymentFailedException $e) {
            $this->handleFailedPayment($sub, $e);
        }
    }

    private function handleFailedPayment(Subscription $sub, \Exception $e): void
    {
        $retryCount = $sub->payment_retry_count + 1;

        if ($retryCount >= 4) {
            // 4 cəhddən sonra cancel — daha çox retry bank-ı qıcıqlandırır
            $sub->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $sub->user->notify(new SubscriptionCancelledNotification($sub));
            return;
        }

        // Grace period: retry-lar arasında gün fasilə — aggressive retry önlənir
        $delays    = [3, 5, 7]; // gün
        $nextRetry = now()->addDays($delays[$retryCount - 1]);

        $sub->update([
            'status'              => 'past_due',
            'payment_retry_count' => $retryCount,
            'next_retry_at'       => $nextRetry,
        ]);

        $sub->user->notify(new PaymentFailedNotification($sub, $nextRetry));

        // Delayed retry — 3 gün sonra yenidən cəhd
        ProcessRenewalJob::dispatch($sub->id)->delay($nextRetry);
    }
}
```

---

## Renewal Idempotency — Niyə Vacibdir?

`renewal-{subscription_id}-{period_end_date}` key-i ilə:
- Server crash → yenidən işləyən job eyni key ilə gateway-ə gedir → gateway eyni nəticəni qaytarır, ikinci charge olmur
- Paralel iki renewal job → eyni idempotency key → gateway biri rədd edir

DB-də `invoices` cədvəlindəki UNIQUE constraint `(subscription_id, period_end_date, type='renewal')` əlavə zəmanət verir.

---

## Downgrade Strategiyası

Downgrade zamanı proration adətən istifadə edilmir — növbəti period-dan tətbiq olunur. Səbəb: artıq ödənilmiş pulu geri qaytarmaq (refund) daha mürəkkəb muhasibat yaradır. Stripe-ın standart davranışı da budur.

---

## Anti-patterns

- **Renewal-da idempotency key olmamaq:** Job retry-da double charge.
- **Failed payment-də dərhal retry:** Aggressive retry bank-ı spam edir, kart bloklanır, kreditə təsir edir. Grace period mütləqdir.
- **Subscription cancel etmədən sonsuz past_due saxlamaq:** 4 cəhddən sonra cancel + notify standart praktikadır.

---

## İntervyu Sualları

**1. Proration necə hesablanır?**
Qalan gün nisbəti əsasında: (yeni plan × qalan günlər/ümumi günlər) − (köhnə plan unused credit). Məsələn ay ortasında upgrade: $35 proration charge. Downgrade adətən növbəti period-dan tətbiq edilir.

**2. Renewal idempotency necə sağlanır?**
Idempotency key: `renewal-{subscription_id}-{period_end_date}`. Server restart, duplicate job halında gateway eyni nəticəni qaytarır. DB invoice UNIQUE constraint əlavə qoruma.

**3. Failed payment retry strategiyası nədir?**
Graceful retry: 3, 5, 7 gün intervalı ilə. Subscription `past_due` statusda aktiv qalır (grace period). 4 uğursuz cəhd: cancel + user notification. Immediate retry anti-pattern — bank spam, blok riski.

---

## Trial Period və Upgrade İmtiyazları

*Bu kod trial dövründə plan yüksəltməni, növbəti period-a qədər təxirə salınan plan endirimini göstərir:*

```php
class Subscription extends Model
{
    // Trial dövründə upgrade: proration 0 — istifadəçi heç nə ödəmir
    public function upgradeDuringTrial(Plan $newPlan): void
    {
        if ($this->isOnTrial()) {
            // Trial bitəndə yeni plan qiyməti tətbiq olunur
            $this->update(['plan_id' => $newPlan->id]);
            return;
        }

        $this->upgrade($newPlan);
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    // Downgrade — növbəti period-dan tətbiq et
    public function downgrade(Plan $newPlan): void
    {
        if ($newPlan->price >= $this->plan->price) {
            throw new \DomainException('Bu upgrade əməliyyatıdır');
        }

        // Downgrade dərhal deyil, növbəti period-dan
        $this->update([
            'pending_plan_id' => $newPlan->id,
            // Renewal zamanı pending_plan_id → plan_id olur
        ]);
    }

    // Renewal zamanı pending downgrade tətbiq et
    public function applyPendingDowngrade(): void
    {
        if ($this->pending_plan_id) {
            $this->update([
                'plan_id'         => $this->pending_plan_id,
                'pending_plan_id' => null,
            ]);
        }
    }
}
```

---

## Invoice Audit Trail

*Bu kod abunəlik plan/status dəyişikliklərini `subscription_history` cədvəlinə avtomatik qeyd edən observer-ı göstərir:*

```php
// Hər billing event-i invoice ilə izlənilir
// subscriptions cədvəlinin özünü audit log kimi istifadə etmə

class SubscriptionHistoryObserver
{
    public function updating(Subscription $subscription): void
    {
        if ($subscription->isDirty(['plan_id', 'status'])) {
            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'event'           => 'plan_changed',
                'from_plan_id'    => $subscription->getOriginal('plan_id'),
                'to_plan_id'      => $subscription->plan_id,
                'from_status'     => $subscription->getOriginal('status'),
                'to_status'       => $subscription->status,
                'changed_at'      => now(),
                'changed_by'      => auth()->id(),
            ]);
        }
    }
}
```

---

## İntervyu Sualları

**4. Downgrade niyə növbəti period-dan tətbiq edilir?**
Müştəri yüksək plan üçün əvvəlcədən ödəyib. Dərhal downgrade etsək: ya refund vermək lazımdır (mürəkkəb muhasibat), ya da müştəri ödədiyi dövrü tam istifadə edə bilmir (ədalətsiz). Standart (Stripe, Paddle): `pending_plan_id` saxla, `current_period_end`-də tətbiq et. Müştəri xəbərdardır ki, dəyişiklik gələn dövrdən qüvvəyə minir.

**5. Grace period niyə vacibdir, neçə gün olmalıdır?**
Bank kartı expire ola bilər, yetersiz balans ola bilər — bunlar müştərinin iradəsindən asılı deyil. Dərhal cancel: user-i haqsız yerdə itirir. Grace period: müştəriyə kart yeniləmə şansı ver. Standart: 3–7 gün. Stripe default-u: 7 gün. Hər retry arasında artan interval (3, 5, 7 gün) bank-ı spam etmir.

---

## Anti-patternlər

**1. Proration-ı float arifmetikası ilə hesablamaq**
`$dailyRate * $daysRemaining` float əməliyyatları ilə hesablamaq — yığılan rounding xətaları böyük abunəçi bazasında maliyyə uyğunsuzluğu yaradır. Bütün məbləğlər integer cent-lərlə hesablanmalı (100 = $1.00), son mərhələdə formatlanmalıdır.

**2. Plan dəyişikliyini invoice yaratmadan birbaşa tətbiq etmək**
Upgrade/downgrade event-ini yalnız subscription cədvəlini yeniləyərək etmək — maliyyə auditoru üçün heç bir iz yoxdur, müştəriyə nə üçün charge edildiyini izah etmək mümkün olmur. Hər plan dəyişikliyi üçün ayrıca proration invoice yaradılmalıdır.

**3. Subscription statusunu payment nəticəsindən asılı etməmək**
Renewal payment fail olduqda subscription-ı dərhal `cancelled` etmək — istifadəçi heç bir şans olmadan xidmətdən məhrum olur. `past_due` ara status tətbiq edilməli, grace period ərzində retry şansı verilməlidir.

**4. Timezone-u nəzərə almadan billing dövrü hesablamaq**
Bütün tarix hesablamalarını UTC-də etmək, lakin müştərinin billing dövrü başlama tarixini lokal saatla göstərmək — ay ortasında upgrade edən Tokio müştərisi üçün "qalan günlər" fərqli hesablanır. Billing dövrü UTC-də saxlanmalı, yalnız UI göstərişi üçün lokal saata çevrilməlidir.

**5. Yüksəldilmiş plandan aşağıya keçişi dərhal tətbiq etmək**
Downgrade-i dərhal tətbiq etmək — müştəri ay əvvəlindən ödədiyi yüksək plan üçün refund gözləyir, ya da daha az funksiyayla qalır. Downgrade `current_period_end`-də tətbiq edilməli, müştəriyə ödədiyi dövrü tam istifadə imkanı verilməlidir.

**6. Subscription cədvəlini audit log kimi istifadə etmək**
Plan/status dəyişikliklərini `subscriptions` cədvəlinin özündə UPDATE ilə etmək — tarix itirilir, mübahisə zamanı "nə vaxt downgrade etdiniz" sualını cavablamaq mümkün olmur. `subscription_history` cədvəlində hər dəyişiklik append-only qeyd edilməlidir.
