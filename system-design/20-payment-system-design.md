# Payment System Design

## Nədir? (What is it?)

Payment system istifadəçilərdən pul qəbul etmək, emal etmək və köçürmək üçün
arxitekturadır. Ödəniş gateway-ləri (Stripe, PayPal), idempotency, double-spending
prevention, webhook-lar, və refund prosesləri əhatə edir. Etibarlılıq və
təhlükəsizlik kritik vacibdir - bir qəpik belə itməməlidir.

Sadə dillə: online kassa sistemi - müştəri kartını "çəkir", pul hesabına düşür,
receipt alır. Amma arxa planda onlarla addım baş verir.

```
Customer → [Checkout] → [Payment Service] → [Stripe/PayPal]
                              │                    │
                              │   ← webhook ───────┘
                              ▼
                    [Order Service] → [Notification]
```

## Əsas Konseptlər (Key Concepts)

### Payment Flow

```
1. Customer adds items to cart
2. Customer enters payment details
3. Frontend tokenizes card (Stripe.js) - card data never touches server
4. Backend creates payment intent
5. 3D Secure authentication (if required)
6. Payment gateway charges card
7. Gateway sends webhook (async confirmation)
8. Backend updates order status
9. Customer receives confirmation

┌────────┐   ┌────────┐   ┌─────────┐   ┌────────┐
│Customer│──▶│Frontend│──▶│ Backend │──▶│ Stripe │
│        │   │        │   │         │   │        │
│ Card   │   │Tokenize│   │ Create  │   │Process │
│ Details│   │(Stripe │   │ Payment │   │Payment │
│        │   │  .js)  │   │ Intent  │   │        │
└────────┘   └────────┘   └────┬────┘   └───┬────┘
                               │             │
                               │  Webhook    │
                               │◀────────────┘
                               │
                          ┌────┴────┐
                          │ Update  │
                          │ Order   │
                          └─────────┘
```

### Idempotency

Eyni request-i bir neçə dəfə göndərmək eyni nəticə verməlidir:

```
Scenario: Network timeout, client retries payment

Without Idempotency:
  Request 1: Charge $50 → Success (but client doesn't know)
  Request 2: Charge $50 → Success → Customer charged $100!

With Idempotency:
  Request 1: Charge $50, key="abc123" → Success
  Request 2: Charge $50, key="abc123" → Returns same result, no double charge
```

### Double-Spending Prevention

```
Problem: User clicks "Pay" button twice fast

Solutions:
1. Frontend: Disable button after first click
2. Backend: Idempotency key per order
3. Database: Unique constraint on (order_id, status='paid')
4. Distributed lock: Redis lock on order_id during payment
5. Optimistic locking: Version column check

CREATE TABLE payments (
    id BIGINT PRIMARY KEY,
    order_id BIGINT UNIQUE,          -- one payment per order
    idempotency_key VARCHAR(64) UNIQUE,
    amount DECIMAL(10,2),
    status ENUM('pending','completed','failed','refunded'),
    ...
);
```

### Webhook Handling

```
Payment Gateway → Your Server (POST /webhooks/stripe)

Challenges:
  - Webhook can arrive before your API call returns
  - Webhooks can be duplicated
  - Webhooks can arrive out of order
  - Your server might be down when webhook arrives

Solutions:
  - Idempotent webhook processing
  - Verify webhook signature
  - Store and process asynchronously
  - Retry mechanism (gateway retries on failure)
```

### Payment States

```
┌─────────┐    ┌───────────┐    ┌───────────┐
│ Created │───▶│  Pending  │───▶│ Completed │
└─────────┘    └─────┬─────┘    └─────┬─────┘
                     │                │
                     ▼                ▼
               ┌───────────┐    ┌───────────┐
               │  Failed   │    │ Refunded  │
               └───────────┘    └─────┬─────┘
                                      │
                                      ▼
                                ┌───────────┐
                                │ Partially │
                                │ Refunded  │
                                └───────────┘
```

## Arxitektura (Architecture)

### Payment System Architecture

```
┌──────────────────────────────────────────────────┐
│                    Frontend                       │
│  ┌──────────────────────────────────────┐        │
│  │  Stripe.js / PayPal SDK              │        │
│  │  (Card tokenization - PCI compliant) │        │
│  └────────────────────┬─────────────────┘        │
└───────────────────────┼──────────────────────────┘
                        │ token
                 ┌──────┴──────┐
                 │ API Gateway │
                 └──────┬──────┘
                        │
                 ┌──────┴──────┐
                 │   Payment   │
                 │   Service   │
                 │             │
                 │ - Validate  │
                 │ - Idempotency│
                 │ - Process   │
                 └──────┬──────┘
                        │
          ┌─────────────┼──────────────┐
          │             │              │
   ┌──────┴────┐  ┌────┴─────┐  ┌────┴─────┐
   │  Payment  │  │  Order   │  │  Ledger  │
   │    DB     │  │ Service  │  │ Service  │
   │           │  │          │  │ (Double  │
   │ payments  │  │ update   │  │  Entry)  │
   │ table     │  │ status   │  │          │
   └───────────┘  └──────────┘  └──────────┘
                        │
                 ┌──────┴──────┐
                 │  Webhook    │
                 │  Listener   │
                 └─────────────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Cashier (Stripe)

```php
// composer require laravel/cashier

// app/Models/User.php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}

// Subscription management
class SubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
            'payment_method' => 'required|string',
        ]);

        $user = auth()->user();

        $user->newSubscription('default', $this->getPriceId($request->plan))
            ->create($request->payment_method);

        return response()->json([
            'message' => 'Subscribed successfully',
            'subscription' => $user->subscription('default'),
        ]);
    }

    public function cancel(): JsonResponse
    {
        auth()->user()->subscription('default')->cancel();

        return response()->json(['message' => 'Subscription cancelled']);
    }

    private function getPriceId(string $plan): string
    {
        return match ($plan) {
            'basic' => config('stripe.prices.basic'),
            'pro' => config('stripe.prices.pro'),
            'enterprise' => config('stripe.prices.enterprise'),
        };
    }
}
```

### Custom Payment Service

```php
class PaymentService
{
    public function __construct(
        private StripeClient $stripe,
        private PaymentRepository $payments,
        private IdempotencyService $idempotency
    ) {}

    public function processPayment(ProcessPaymentRequest $request): Payment
    {
        $idempotencyKey = $request->idempotency_key;

        // Check idempotency - return existing result if duplicate
        $existing = $this->idempotency->check($idempotencyKey);
        if ($existing) {
            return $existing;
        }

        // Acquire distributed lock
        $lock = Cache::lock("payment:{$request->order_id}", 30);
        if (!$lock->get()) {
            throw new PaymentInProgressException('Payment already being processed');
        }

        try {
            // Check order not already paid
            $existingPayment = $this->payments->findByOrderId($request->order_id);
            if ($existingPayment && $existingPayment->status === 'completed') {
                return $existingPayment;
            }

            // Create payment record
            $payment = $this->payments->create([
                'order_id' => $request->order_id,
                'user_id' => auth()->id(),
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'usd',
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
            ]);

            // Create Stripe payment intent
            $intent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($request->amount * 100), // cents
                'currency' => $request->currency ?? 'usd',
                'payment_method' => $request->payment_method_id,
                'confirm' => true,
                'metadata' => [
                    'order_id' => $request->order_id,
                    'payment_id' => $payment->id,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            // Update payment with gateway response
            $payment->update([
                'gateway_id' => $intent->id,
                'status' => $this->mapStripeStatus($intent->status),
            ]);

            // Store idempotency result
            $this->idempotency->store($idempotencyKey, $payment);

            return $payment;
        } finally {
            $lock->release();
        }
    }

    public function refund(int $paymentId, ?float $amount = null): Refund
    {
        $payment = $this->payments->findOrFail($paymentId);

        if ($payment->status !== 'completed') {
            throw new InvalidPaymentStateException('Can only refund completed payments');
        }

        $refundAmount = $amount ?? $payment->amount;

        if ($refundAmount > $payment->refundable_amount) {
            throw new RefundExceedsPaymentException();
        }

        $stripeRefund = $this->stripe->refunds->create([
            'payment_intent' => $payment->gateway_id,
            'amount' => (int) ($refundAmount * 100),
        ]);

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'amount' => $refundAmount,
            'gateway_refund_id' => $stripeRefund->id,
            'status' => 'completed',
        ]);

        $newStatus = $refundAmount >= $payment->amount ? 'refunded' : 'partially_refunded';
        $payment->update(['status' => $newStatus]);

        return $refund;
    }

    private function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'completed',
            'processing' => 'pending',
            'requires_action' => 'requires_action',
            default => 'failed',
        };
    }
}
```

### Webhook Handler

```php
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Verify webhook signature
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::error('Invalid webhook signature', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        // Idempotent processing - check if event already processed
        if (WebhookEvent::where('event_id', $event->id)->exists()) {
            return response('Already processed', 200);
        }

        // Store event for audit
        WebhookEvent::create([
            'event_id' => $event->id,
            'type' => $event->type,
            'payload' => $payload,
        ]);

        // Process event
        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSuccess($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailure($event->data->object),
            'charge.refunded' => $this->handleRefund($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionCancelled($event->data->object),
            default => Log::info("Unhandled webhook type: {$event->type}"),
        };

        return response('OK', 200);
    }

    private function handlePaymentSuccess(object $paymentIntent): void
    {
        $payment = Payment::where('gateway_id', $paymentIntent->id)->first();
        if (!$payment) return;

        $payment->update(['status' => 'completed']);

        // Update order
        $payment->order->update(['status' => 'paid']);

        // Send confirmation
        $payment->user->notify(new PaymentConfirmation($payment));

        // Record in ledger
        LedgerEntry::record($payment, 'credit');
    }

    private function handlePaymentFailure(object $paymentIntent): void
    {
        $payment = Payment::where('gateway_id', $paymentIntent->id)->first();
        if (!$payment) return;

        $payment->update([
            'status' => 'failed',
            'failure_reason' => $paymentIntent->last_payment_error?->message,
        ]);

        $payment->user->notify(new PaymentFailed($payment));
    }
}
```

### Idempotency Service

```php
class IdempotencyService
{
    public function check(string $key): ?Payment
    {
        $cached = Cache::get("idempotency:{$key}");
        if ($cached) {
            return Payment::find($cached);
        }

        $payment = Payment::where('idempotency_key', $key)->first();
        if ($payment) {
            Cache::put("idempotency:{$key}", $payment->id, now()->addHours(24));
        }

        return $payment;
    }

    public function store(string $key, Payment $payment): void
    {
        Cache::put("idempotency:{$key}", $payment->id, now()->addHours(24));
    }
}
```

### Double-Entry Ledger

```php
class LedgerService
{
    public function recordPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Debit customer account
            LedgerEntry::create([
                'payment_id' => $payment->id,
                'account' => "customer:{$payment->user_id}",
                'type' => 'debit',
                'amount' => $payment->amount,
                'description' => "Payment for order #{$payment->order_id}",
            ]);

            // Credit merchant revenue
            LedgerEntry::create([
                'payment_id' => $payment->id,
                'account' => 'revenue:sales',
                'type' => 'credit',
                'amount' => $payment->amount,
                'description' => "Revenue from order #{$payment->order_id}",
            ]);
        });
    }

    // Total debits must always equal total credits
    public function verifyBalance(): bool
    {
        $debits = LedgerEntry::where('type', 'debit')->sum('amount');
        $credits = LedgerEntry::where('type', 'credit')->sum('amount');
        return bccomp($debits, $credits, 2) === 0;
    }
}
```

## Real-World Nümunələr

1. **Stripe** - Developer-friendly payment API, 135+ currencies
2. **PayPal** - 400M+ users, buyer/seller protection
3. **Square** - POS + online payments
4. **Shopify Payments** - E-commerce integrated payments
5. **Adyen** - Enterprise payment platform (Netflix, Uber istifadə edir)

## Interview Sualları

**S1: Idempotency payment system-də niyə kritikdir?**
C: Network failure zamanı client retry edə bilər. Idempotency olmasa eyni ödəniş
iki dəfə charge ola bilər. Idempotency key (unique per operation) ilə duplicate
request eyni nəticəni qaytarır, yeni ödəniş yaratmır.

**S2: Double-spending necə qarşısı alınır?**
C: Distributed lock (Redis), database unique constraint (order_id),
optimistic locking (version check), idempotency key, frontend button
disable. Bir neçəsini birlikdə istifadə edin (defense in depth).

**S3: Webhook-lar niyə lazımdır?**
C: Payment processing async-dir. API call "pending" qaytara bilər, nəticə
sonra webhook ilə gəlir. Network timeout olsa da webhook nəticəni çatdırır.
Webhook signature ilə authenticity yoxlanır.

**S4: PCI DSS nədir?**
C: Payment Card Industry Data Security Standard. Kart data-sını qorumaq üçün
standard. Stripe.js/Elements istifadə edəndə kart data server-ə toxunmur
(tokenization), PCI scope azalır. Heç vaxt kart nömrəsini öz DB-nizdə saxlamayın.

**S5: Partial refund necə implement olunur?**
C: Payment-in refundable_amount-ını track edin. Hər refund bu məbləği azaldır.
Stripe API partial refund dəstəkləyir. Ledger-də refund credit entry yaradılır.
Order status "partially_refunded" olur.

**S6: Payment retry strategiyası necə olmalıdır?**
C: Exponential backoff ilə retry (1s, 2s, 4s, 8s). Idempotency key ilə safe
retry. Max retry count (3-5). Fərqli failure type-lar: card declined (retry
etmə), network error (retry et), rate limit (gözlə və retry et).

## Best Practices

1. **Never Store Card Data** - Tokenization istifadə edin (Stripe.js)
2. **Idempotency Keys** - Hər payment operation-a unique key
3. **Webhook Verification** - Signature yoxlayın, replay attacks-dan qorunun
4. **Double-Entry Ledger** - Hər transaction-ı iki tərəfli qeyd edin
5. **Distributed Locks** - Concurrent payment attempts-dan qorunun
6. **Audit Trail** - Hər payment dəyişikliyini log edin
7. **Async Processing** - Webhooks ilə async confirmation
8. **Error Handling** - Graceful degradation, user-friendly error messages
9. **Testing** - Stripe test mode, mock webhooks
10. **Monitoring** - Payment success rate, latency, failure reasons track edin
