# Kompleks Payment Flow Dizaynı

## Problem Statement

E-commerce platformunda multi-step payment prosesi var: cart → checkout → payment → confirmation. Bundan əlavə:
- Partial payments (hissəli ödəniş)
- Installments (taksit)
- Mixed payment methods (kart + wallet + voucher birlikdə)
- 3DS authentication
- Webhook ilə state transitions
- Compensating transactions (ödəniş uğursuz olarsa rollback)

Bu mürəkkəb flow-u necə dizayn edərsiniz?

### Problem niyə yaranır?

Sadə `charge → create_order` akışı multi-step payment-də çatışmır. Ssenari: user wallet-dən 20 AZN + kartdan 80 AZN ödədi. Kart uğursuz oldu — wallet artıq debit edilmişdi. Sistem inconsistent state-dədir: ödəniş tam deyil, amma pul çıxıb. Buna **partial failure** deyilir. Hər step-in compensation (geri qaytarma) əməliyyatı olmadan bu hallarda manual müdaxilə lazım olur. Əlavə olaraq: 3DS redirect-i zamanı user browser-i bağlayıb qayıda bilər — saga state persist olmasa, hansı step-lərin tamamlandığını bilmək mümkün deyil.

---

## Arxitektura Overview

```
Cart → CheckoutSession → PaymentOrchestrator → [PaymentSteps] → Confirmation
                                ↓
                        SagaStateachine
                                ↓
                    CompensationHandler (on failure)
```

---

## 1. Domain Models

*Bu kod multi-step payment üçün əsas PaymentOrder və PaymentSplit modellərini göstərir:*

```php
// app/Models/PaymentOrder.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    protected $fillable = [
        'user_id', 'cart_id', 'total_amount', 'currency',
        'status', 'idempotency_key', 'saga_state', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'total_amount' => 'decimal:2',
    ];

    // Status-lar: pending, processing, partially_paid, paid, failed, refunded, cancelled
    const STATUS_PENDING     = 'pending';
    const STATUS_PROCESSING  = 'processing';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID        = 'paid';
    const STATUS_FAILED      = 'failed';
    const STATUS_REFUNDED    = 'refunded';

    public function splits()
    {
        return $this->hasMany(PaymentSplit::class);
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function sagaEvents()
    {
        return $this->hasMany(SagaEvent::class);
    }
}
```

*return $this->hasMany(SagaEvent::class); üçün kod nümunəsi:*
```php
// app/Models/PaymentSplit.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSplit extends Model
{
    protected $fillable = [
        'payment_order_id', 'method', 'amount', 'status',
        'provider_reference', 'due_date', 'paid_at', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // method: card, wallet, voucher, installment
    public function paymentOrder()
    {
        return $this->belongsTo(PaymentOrder::class);
    }
}
```

---

## 2. PaymentStep Interface + Concrete Steps

*Bu kod hər ödəniş addımının execute, compensate və idempotency metodlarını tələb edən PaymentStep interface-ini göstərir:*

```php
// app/Payment/Contracts/PaymentStep.php
<?php

namespace App\Payment\Contracts;

use App\Models\PaymentOrder;

interface PaymentStep
{
    /**
     * Step-i icra et
     */
    public function execute(PaymentOrder $order, array $context): array;

    /**
     * Step adı - loglama və debug üçün
     */
    public function getName(): string;

    /**
     * Bu step uğursuz olarsa compensation icra et
     */
    public function compensate(PaymentOrder $order, array $context): void;

    /**
     * Step idempotent-dirmi?
     */
    public function isIdempotent(): bool;
}
```

*Bu kod cart məzmununu və qiymətini yoxlayan, uğursuz olarsa compensation-u olmayan birinci ödəniş addımını göstərir:*

```php
// app/Payment/Steps/ValidateCartStep.php
<?php

namespace App\Payment\Steps;

use App\Models\PaymentOrder;
use App\Payment\Contracts\PaymentStep;
use App\Repositories\CartRepository;
use App\Exceptions\Payment\CartValidationException;

class ValidateCartStep implements PaymentStep
{
    public function __construct(
        private CartRepository $cartRepository
    ) {}

    public function execute(PaymentOrder $order, array $context): array
    {
        $cart = $this->cartRepository->findOrFail($order->cart_id);

        // Stock yoxla
        foreach ($cart->items as $item) {
            if ($item->quantity > $item->product->stock) {
                throw new CartValidationException(
                    "Məhsul stokda yoxdur: {$item->product->name}"
                );
            }
        }

        // Qiyməti yoxla - cart və order məbləği uyğun olmalıdır
        if (abs($cart->total - $order->total_amount) > 0.01) {
            throw new CartValidationException(
                "Məbləğ dəyişib. Yenidən checkout edin."
            );
        }

        return array_merge($context, [
            'cart_validated' => true,
            'cart_items' => $cart->items->toArray(),
        ]);
    }

    public function getName(): string
    {
        return 'validate_cart';
    }

    public function compensate(PaymentOrder $order, array $context): void
    {
        // Validation step-in compensation-u yoxdur (heç bir side effect yoxdur)
    }

    public function isIdempotent(): bool
    {
        return true;
    }
}
```

*Bu kod inventar rezervasiyasını edən və uğursuzluqda rezervi azad edən compensation metodunu göstərir:*

```php
// app/Payment/Steps/ReserveInventoryStep.php
<?php

namespace App\Payment\Steps;

use App\Models\PaymentOrder;
use App\Payment\Contracts\PaymentStep;
use App\Services\InventoryService;

class ReserveInventoryStep implements PaymentStep
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function execute(PaymentOrder $order, array $context): array
    {
        $reservationId = $this->inventoryService->reserve(
            $context['cart_items'],
            $order->idempotency_key . '_inventory'  // idempotency
        );

        return array_merge($context, [
            'inventory_reservation_id' => $reservationId,
        ]);
    }

    public function getName(): string
    {
        return 'reserve_inventory';
    }

    public function compensate(PaymentOrder $order, array $context): void
    {
        // Əgər inventory rezerv edilmişdisə, azad et
        if (isset($context['inventory_reservation_id'])) {
            $this->inventoryService->release($context['inventory_reservation_id']);
        }
    }

    public function isIdempotent(): bool
    {
        return true; // idempotency_key ilə təkrar çağırış eyni nəticə verir
    }
}
```

*Bu kod voucher tətbiqetmə addımını göstərir — voucher yoxdursa keçir, uğursuzluqda geri alır:*

```php
// app/Payment/Steps/ApplyVoucherStep.php
<?php

namespace App\Payment\Steps;

use App\Models\PaymentOrder;
use App\Payment\Contracts\PaymentStep;
use App\Services\VoucherService;
use App\Exceptions\Payment\VoucherException;

class ApplyVoucherStep implements PaymentStep
{
    public function __construct(
        private VoucherService $voucherService
    ) {}

    public function execute(PaymentOrder $order, array $context): array
    {
        if (empty($context['voucher_code'])) {
            return $context; // Voucher yoxdur, keç
        }

        $discount = $this->voucherService->apply(
            $context['voucher_code'],
            $order->user_id,
            $order->total_amount,
            $order->idempotency_key . '_voucher'
        );

        return array_merge($context, [
            'voucher_applied' => true,
            'voucher_discount' => $discount->amount,
            'remaining_amount' => $order->total_amount - $discount->amount,
        ]);
    }

    public function getName(): string
    {
        return 'apply_voucher';
    }

    public function compensate(PaymentOrder $order, array $context): void
    {
        if (!empty($context['voucher_applied'])) {
            $this->voucherService->rollback($context['voucher_code'], $order->user_id);
        }
    }

    public function isIdempotent(): bool
    {
        return true;
    }
}
```

*Bu kod wallet-dən məbləğ çıxaran və uğursuzluqda refund edən charge wallet addımını göstərir:*

```php
// app/Payment/Steps/ChargeWalletStep.php
<?php

namespace App\Payment\Steps;

use App\Models\PaymentOrder;
use App\Payment\Contracts\PaymentStep;
use App\Services\WalletService;

class ChargeWalletStep implements PaymentStep
{
    public function __construct(
        private WalletService $walletService
    ) {}

    public function execute(PaymentOrder $order, array $context): array
    {
        $walletAmount = $context['wallet_amount'] ?? 0;
        if ($walletAmount <= 0) {
            return $context;
        }

        $transactionId = $this->walletService->debit(
            $order->user_id,
            $walletAmount,
            $order->idempotency_key . '_wallet'
        );

        return array_merge($context, [
            'wallet_charged' => true,
            'wallet_transaction_id' => $transactionId,
            'remaining_amount' => ($context['remaining_amount'] ?? $order->total_amount) - $walletAmount,
        ]);
    }

    public function getName(): string
    {
        return 'charge_wallet';
    }

    public function compensate(PaymentOrder $order, array $context): void
    {
        if (!empty($context['wallet_transaction_id'])) {
            $this->walletService->refund(
                $context['wallet_transaction_id'],
                $order->idempotency_key . '_wallet_refund'
            );
        }
    }

    public function isIdempotent(): bool
    {
        return true;
    }
}
```

*Bu kod kart ödənişini edən, 3DS tələb olduqda xüsusi exception atan charge card addımını göstərir:*

```php
// app/Payment/Steps/ChargeCardStep.php
<?php

namespace App\Payment\Steps;

use App\Models\PaymentOrder;
use App\Payment\Contracts\PaymentStep;
use App\Services\PaymentGatewayService;
use App\Exceptions\Payment\ThreeDSRequiredException;

class ChargeCardStep implements PaymentStep
{
    public function __construct(
        private PaymentGatewayService $gateway
    ) {}

    public function execute(PaymentOrder $order, array $context): array
    {
        $cardAmount = $context['remaining_amount'] ?? $order->total_amount;
        if ($cardAmount <= 0) {
            return $context;
        }

        $result = $this->gateway->charge([
            'amount'          => $cardAmount,
            'currency'        => $order->currency,
            'card_token'      => $context['card_token'],
            'idempotency_key' => $order->idempotency_key . '_card',
            'metadata'        => ['order_id' => $order->id],
        ]);

        // 3DS tələb olunursa, flow-u kəs
        if ($result->requires3DS()) {
            throw new ThreeDSRequiredException(
                $result->getRedirectUrl(),
                $result->getPaymentIntentId()
            );
        }

        return array_merge($context, [
            'card_charged' => true,
            'card_transaction_id' => $result->getTransactionId(),
        ]);
    }

    public function getName(): string
    {
        return 'charge_card';
    }

    public function compensate(PaymentOrder $order, array $context): void
    {
        if (!empty($context['card_transaction_id'])) {
            $this->gateway->refund(
                $context['card_transaction_id'],
                $order->idempotency_key . '_card_refund'
            );
        }
    }

    public function isIdempotent(): bool
    {
        return true;
    }
}
```

---

## 3. Saga Pattern — PaymentOrchestrator

*Bu kod hər addımı icra edən, 3DS redirect-i tutan və uğursuzluqda compensation chain-i işlədən Saga orchestrator-u göstərir:*

```php
// app/Payment/PaymentOrchestrator.php
<?php

namespace App\Payment;

use App\Models\PaymentOrder;
use App\Models\SagaEvent;
use App\Payment\Contracts\PaymentStep;
use App\Exceptions\Payment\ThreeDSRequiredException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentOrchestrator
{
    /** @var PaymentStep[] */
    private array $steps = [];

    /** Tamamlanan step-lər (compensation üçün) */
    private array $completedSteps = [];

    public function __construct(array $steps)
    {
        $this->steps = $steps;
    }

    public function execute(PaymentOrder $order, array $context = []): PaymentResult
    {
        $this->completedSteps = [];

        // Saga başladı
        $this->recordSagaEvent($order, 'saga_started', $context);

        try {
            foreach ($this->steps as $step) {
                // Artıq tamamlanmış step-i skip et (idempotency + resume)
                if ($this->isStepAlreadyCompleted($order, $step->getName())) {
                    Log::info("Step already completed, skipping: {$step->getName()}");
                    continue;
                }

                $context = $step->execute($order, $context);
                $this->completedSteps[] = $step;
                $this->recordSagaEvent($order, "step_completed_{$step->getName()}", $context);
            }

            $this->recordSagaEvent($order, 'saga_completed', $context);

            return new PaymentResult(success: true, context: $context);

        } catch (ThreeDSRequiredException $e) {
            // 3DS üçün xüsusi handling — saga paused
            $this->recordSagaEvent($order, 'saga_paused_3ds', [
                'redirect_url'      => $e->getRedirectUrl(),
                'payment_intent_id' => $e->getPaymentIntentId(),
                'completed_steps'   => array_map(fn($s) => $s->getName(), $this->completedSteps),
            ]);

            return new PaymentResult(
                success: false,
                requiresRedirect: true,
                redirectUrl: $e->getRedirectUrl(),
                context: $context
            );

        } catch (\Throwable $e) {
            Log::error("Payment saga failed", [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
                'step'     => end($this->completedSteps)?->getName(),
            ]);

            $this->recordSagaEvent($order, 'saga_failed', ['error' => $e->getMessage()]);

            // Compensate: tamamlanan step-ləri tərsinə icra et
            $this->compensate($order, $context);

            return new PaymentResult(success: false, error: $e->getMessage(), context: $context);
        }
    }

    /**
     * Compensating transactions — uğurlu step-ləri tərsinə icra et
     */
    private function compensate(PaymentOrder $order, array $context): void
    {
        $stepsToCompensate = array_reverse($this->completedSteps);

        foreach ($stepsToCompensate as $step) {
            try {
                $step->compensate($order, $context);
                $this->recordSagaEvent($order, "compensated_{$step->getName()}", $context);
            } catch (\Throwable $e) {
                // Compensation uğursuz olursa — manual intervention lazımdır
                Log::critical("Compensation failed for step {$step->getName()}", [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                // Dead letter queue-ya göndər
                dispatch(new \App\Jobs\ManualCompensationRequired($order, $step->getName(), $e->getMessage()));
            }
        }
    }

    private function isStepAlreadyCompleted(PaymentOrder $order, string $stepName): bool
    {
        return $order->sagaEvents()
            ->where('event', "step_completed_{$stepName}")
            ->exists();
    }

    private function recordSagaEvent(PaymentOrder $order, string $event, array $payload): void
    {
        SagaEvent::create([
            'payment_order_id' => $order->id,
            'event'            => $event,
            'payload'          => $payload,
            'occurred_at'      => now(),
        ]);
    }
}
```

---

## 4. Payment Split (Hissəli Ödəniş)

*Bu kod ödənişi 2 hissəyə bölən (deferred) və ya taksit planı yaradan split manager-i göstərir:*

```php
// app/Payment/PaymentSplitManager.php
<?php

namespace App\Payment;

use App\Models\PaymentOrder;
use App\Models\PaymentSplit;
use App\Jobs\ProcessScheduledPayment;

class PaymentSplitManager
{
    /**
     * 50% indi, 50% 30 gün sonra
     */
    public function createDeferredSplit(PaymentOrder $order): void
    {
        $halfAmount = round($order->total_amount / 2, 2);
        $remainder  = $order->total_amount - $halfAmount;

        // İlk hissə — indi
        PaymentSplit::create([
            'payment_order_id' => $order->id,
            'method'           => 'card',
            'amount'           => $halfAmount,
            'status'           => 'pending',
            'due_date'         => now(),
        ]);

        // İkinci hissə — 30 gün sonra
        PaymentSplit::create([
            'payment_order_id' => $order->id,
            'method'           => 'card',
            'amount'           => $remainder,
            'status'           => 'scheduled',
            'due_date'         => now()->addDays(30),
        ]);
    }

    /**
     * Installment (taksit) — 3 ay bərabər hissə
     */
    public function createInstallmentPlan(PaymentOrder $order, int $months = 3): void
    {
        $installmentAmount = round($order->total_amount / $months, 2);

        for ($i = 0; $i < $months; $i++) {
            // Son taksitdə yuvarlama fərqini düzəlt
            $amount = ($i === $months - 1)
                ? $order->total_amount - ($installmentAmount * ($months - 1))
                : $installmentAmount;

            PaymentSplit::create([
                'payment_order_id' => $order->id,
                'method'           => 'installment',
                'amount'           => $amount,
                'status'           => $i === 0 ? 'pending' : 'scheduled',
                'due_date'         => now()->addMonths($i),
                'metadata'         => ['installment_number' => $i + 1, 'total_installments' => $months],
            ]);
        }

        // Scheduled payment job-ları dispatch et
        foreach ($order->splits()->where('status', 'scheduled')->get() as $split) {
            ProcessScheduledPayment::dispatch($split)
                ->delay($split->due_date);
        }
    }
}
```

---

## 5. 3DS Authentication Flow

*Bu kod bank 3DS callback-ini emal edib saga-nı davam etdirən və ya uğursuz olarsa compensation başladan controller-i göstərir:*

```php
// app/Http/Controllers/Payment/ThreeDSController.php
<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Payment\PaymentOrchestrator;
use App\Payment\PaymentOrchestratorFactory;
use Illuminate\Http\Request;

class ThreeDSController extends Controller
{
    public function __construct(
        private PaymentOrchestratorFactory $orchestratorFactory
    ) {}

    /**
     * Bank 3DS confirm-dən sonra bura redirect edir
     */
    public function callback(Request $request, PaymentOrder $order)
    {
        $paymentIntentId = $request->query('payment_intent');
        $status          = $request->query('redirect_status'); // 'succeeded' or 'failed'

        if ($status !== 'succeeded') {
            // 3DS failed — compensation başlat
            $this->handleThreeDSFailure($order);
            return redirect()->route('checkout.failed', $order)->with('error', '3DS doğrulama uğursuz oldu.');
        }

        // Saga-nı davam etdir — 3DS-dən sonrakı step-ləri icra et
        $sagaPausedEvent = $order->sagaEvents()
            ->where('event', 'saga_paused_3ds')
            ->latest()
            ->first();

        $context = $sagaPausedEvent->payload;
        $context['three_ds_completed'] = true;
        $context['payment_intent_id']  = $paymentIntentId;

        $orchestrator = $this->orchestratorFactory->makeForResume($order, $context['completed_steps']);
        $result = $orchestrator->execute($order, $context);

        if ($result->isSuccess()) {
            $order->update(['status' => PaymentOrder::STATUS_PAID]);
            return redirect()->route('order.confirmation', $order);
        }

        return redirect()->route('checkout.failed', $order)->with('error', $result->getError());
    }

    private function handleThreeDSFailure(PaymentOrder $order): void
    {
        $order->update(['status' => PaymentOrder::STATUS_FAILED]);
        // Compensation event yarat
        $order->sagaEvents()->create([
            'event'      => 'three_ds_failed',
            'payload'    => [],
            'occurred_at' => now(),
        ]);
    }
}
```

---

## 6. Webhook-driven State Transitions

*Bu kod payment provider webhook-larını imzası ilə doğrulayaraq saga state-ini yeniləyən controller-i göstərir:*

```php
// app/Http/Controllers/Payment/WebhookController.php
<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\WebhookEvent;
use App\Payment\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookProcessor $webhookProcessor
    ) {}

    public function handle(Request $request, string $provider)
    {
        // Signature yoxla
        if (!$this->verifySignature($request, $provider)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        // Idempotency — eyni webhook-u iki dəfə işləmə
        $eventId = $payload['id'] ?? hash('sha256', json_encode($payload));
        if (WebhookEvent::where('external_id', $eventId)->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        // Webhook-u qeyd et
        $webhookEvent = WebhookEvent::create([
            'provider'    => $provider,
            'external_id' => $eventId,
            'event_type'  => $payload['type'] ?? 'unknown',
            'payload'     => $payload,
            'status'      => 'received',
        ]);

        // Async emal et
        dispatch(new \App\Jobs\ProcessWebhookEvent($webhookEvent));

        return response()->json(['status' => 'accepted'], 202);
    }

    private function verifySignature(Request $request, string $provider): bool
    {
        return match ($provider) {
            'stripe' => $this->verifyStripeSignature($request),
            'paypal' => $this->verifyPaypalSignature($request),
            default  => false,
        };
    }

    private function verifyStripeSignature(Request $request): bool
    {
        $signature = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret
            );
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
```

*} catch (\Exception) { üçün kod nümunəsi:*
```php
// app/Payment/WebhookProcessor.php
<?php

namespace App\Payment;

use App\Models\PaymentOrder;
use App\Models\WebhookEvent;

class WebhookProcessor
{
    public function process(WebhookEvent $event): void
    {
        $handler = $this->getHandler($event->event_type);
        $handler($event);

        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }

    private function getHandler(string $eventType): callable
    {
        return match ($eventType) {
            'payment_intent.succeeded'          => [$this, 'handlePaymentSucceeded'],
            'payment_intent.payment_failed'     => [$this, 'handlePaymentFailed'],
            'charge.dispute.created'            => [$this, 'handleDisputeCreated'],
            'charge.refunded'                   => [$this, 'handleRefunded'],
            default                             => fn() => null, // unknown event-ləri ignore et
        };
    }

    private function handlePaymentSucceeded(WebhookEvent $event): void
    {
        $paymentIntentId = $event->payload['data']['object']['id'];

        $order = PaymentOrder::whereJsonContains('metadata->payment_intent_id', $paymentIntentId)->first();
        if (!$order) return;

        $order->update(['status' => PaymentOrder::STATUS_PAID]);
        event(new \App\Events\PaymentSucceeded($order));
    }

    private function handlePaymentFailed(WebhookEvent $event): void
    {
        $paymentIntentId = $event->payload['data']['object']['id'];
        $order = PaymentOrder::whereJsonContains('metadata->payment_intent_id', $paymentIntentId)->first();
        if (!$order) return;

        $order->update(['status' => PaymentOrder::STATUS_FAILED]);
        event(new \App\Events\PaymentFailed($order));
    }
}
```

---

## 7. Controller — Checkout Endpoint

*7. Controller — Checkout Endpoint üçün kod nümunəsi:*
```php
// app/Http/Controllers/CheckoutController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\PaymentOrder;
use App\Payment\PaymentOrchestratorFactory;
use App\Payment\PaymentSplitManager;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private PaymentOrchestratorFactory $factory,
        private PaymentSplitManager $splitManager
    ) {}

    public function store(CheckoutRequest $request)
    {
        // Idempotency key — client tərəfindən göndərilir və ya yaradılır
        $idempotencyKey = $request->header('Idempotency-Key') ?? Str::uuid();

        // Eyni key ilə mövcud order varmı?
        $existingOrder = PaymentOrder::where('idempotency_key', $idempotencyKey)->first();
        if ($existingOrder && $existingOrder->status === PaymentOrder::STATUS_PAID) {
            return response()->json(['order_id' => $existingOrder->id, 'status' => 'already_paid']);
        }

        $order = $existingOrder ?? PaymentOrder::create([
            'user_id'         => $request->user()->id,
            'cart_id'         => $request->cart_id,
            'total_amount'    => $request->total_amount,
            'currency'        => $request->currency ?? 'AZN',
            'status'          => PaymentOrder::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'metadata'        => [],
        ]);

        // Split tələb olunursa
        if ($request->payment_type === 'installment') {
            $this->splitManager->createInstallmentPlan($order, months: 3);
        } elseif ($request->payment_type === 'deferred') {
            $this->splitManager->createDeferredSplit($order);
        }

        $orchestrator = $this->factory->make();

        $result = $orchestrator->execute($order, [
            'card_token'   => $request->card_token,
            'voucher_code' => $request->voucher_code,
            'wallet_amount'=> $request->wallet_amount ?? 0,
        ]);

        if ($result->requiresRedirect()) {
            return response()->json([
                'redirect_url' => $result->getRedirectUrl(),
                'status'       => 'requires_action',
            ]);
        }

        if ($result->isSuccess()) {
            $order->update(['status' => PaymentOrder::STATUS_PAID]);
            return response()->json(['order_id' => $order->id, 'status' => 'paid'], 201);
        }

        return response()->json(['error' => $result->getError()], 422);
    }
}
```

---

## 8. Migration

*8. Migration üçün kod nümunəsi:*
```php
// database/migrations/2024_01_01_create_payment_tables.php
Schema::create('payment_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('cart_id')->constrained();
    $table->decimal('total_amount', 10, 2);
    $table->char('currency', 3)->default('AZN');
    $table->string('status')->default('pending');
    $table->string('idempotency_key')->unique();
    $table->string('saga_state')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
    $table->index('idempotency_key');
});

Schema::create('saga_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payment_order_id')->constrained();
    $table->string('event');
    $table->json('payload')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();

    $table->index(['payment_order_id', 'event']);
});

Schema::create('webhook_events', function (Blueprint $table) {
    $table->id();
    $table->string('provider');
    $table->string('external_id')->unique();
    $table->string('event_type');
    $table->json('payload');
    $table->string('status')->default('received');
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
```

---

## Error Scenarios və Recovery

| Scenario | Handling |
|---|---|
| Cart-da stok yoxdur | ValidateCartStep exception, heç bir charge olmur |
| Voucher tətbiq edilib, kart uğursuz olur | ChargeWallet compensation, voucher rollback |
| 3DS timeout | Webhook gəlmir, scheduled job 24s-dən sonra yoxlayır |
| Webhook iki dəfə gəlir | Idempotency key ilə skip |
| Compensation özü uğursuz olur | Dead letter queue, manual intervention alert |

---

## İntervyu Sualları

**S: Saga pattern nədir, nə zaman istifadə edilir?**
C: Distributed transaction-ları koordinasiya etmək üçün istifadə edilən pattern. Hər step müstəqil transaction-dır. Uğursuzluq olarsa, əvvəlki step-lər üçün compensating transactions icra edilir. 2-phase commit əvəzinə istifadə edilir, çünki distributed sistemlərdə bloklama yaratmır.

**S: Idempotency niyə vacibdir?**
C: Network xətası, retry, ya da webhook duplikasiyası zamanı eyni əməliyyatın iki dəfə icra edilməsini önləyir. Hər request-ə unikal key verilir; eyni key ilə ikinci request gəlsə, birincinin nəticəsini qaytarırıq.

**S: Compensating transaction ilə rollback-in fərqi nədir?**
C: DB rollback eyni transaction daxilindədir. Compensating transaction isə artıq commit edilmiş əməliyyatın təsirini ləğv edən ayrı bir əməliyyatdır (məs., charge → refund).

**S: 3DS flow-unda saga-nı necə pause edirik?**
C: ThreeDSRequiredException throw edirik, orchestrator bunu tutub saga event-ə yazır. User 3DS callback-dən qayıdanda, tamamlanan step-ləri skip edib (saga event-lərə baxaraq), qalan step-lərdən davam edirik.

**S: Webhook order-ı necə tapır?**
C: Ödəniş yaradanda payment_intent_id-ni metadata-ya yazırıq. Webhook gəldikdə həmin id ilə order-ı tapırıq.

---

## Anti-patterns

**1. Payment state-ni güncəlləmək üçün yalnız webhooks-a güvənmək**
Webhook çatmaya bilər, gecikmə ola bilər. Polling + webhook kombinasiyası lazımdır — yaxud timeout sonrası manual yoxlama.

**2. Compensation olmadan multi-step payment**
Step 3 fail olur, step 1-2 artıq tamamlanıb — inconsistent state. Saga pattern: hər step-in reverse action-ı (refund, release reservation) olmalıdır.

**3. Idempotency key-siz payment gateway çağırışı**
Network retry → iki charge. Payment gateway-ə göndərilən hər sorğuda idempotency key mütləqdir.

**4. Saga state-ini memory-də saxlamaq**
Worker restart → saga state itirilir. State DB-ə persist edilməlidir, hər addım checkpoint-lənməlidir.

**5. Webhook imzasını yoxlamamaq**
Fake webhook injection mümkündür — `hash_hmac` ilə provider signature mütləq yoxlanılmalıdır.

**6. Partial payment-də məbləğ validasiyasını atlama**
Wallet + voucher + kart cəmi ümumi məbləğdən az/çox ola bilər. Orchestrator hər split-in cəmini order total-ı ilə müqayisə etməlidir — uyğunsuzluq varsa checkout başlamasın.

**7. Saga compensation-larını test etməmək**
Happy path test edilir, amma step 3 fail edəndə step 1-2-nin rollback-i düzgün işləyirmi? Hər compensation action üçün ayrıca test lazımdır.**
Hər kəs webhook endpoint-inə POST göndərə bilər. `Stripe-Signature` headerini `hash_equals` ilə verify et.
