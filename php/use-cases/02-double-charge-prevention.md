# Payment-lərdə Double Charge Problemi və Həlli

## Problem Təsviri

E-commerce və ya istənilən payment sistemi olan application-da **double charge** (ikili ödəniş) ciddi problemdir. Bu problem aşağıdakı hallarda yaranır:

1. **User "Pay" düyməsini 2 dəfə basır** — network yavaşdır, user səbirsizlikdən təkrar klikləyir
2. **Network retry** — client timeout alır, amma server sorğunu almışdı. Client yenidən göndərir
3. **Webhook duplicate** — payment provider eyni webhook-u bir neçə dəfə göndərir
4. **Browser refresh** — payment form submit edildikdən sonra user səhifəni yeniləyir (POST re-submit)
5. **Mobile app retry** — network connectivity problemləri, app avtomatik retry edir

```
User → "Pay $100" klik → Request 1 → Server → Stripe charge → $100 çıxdı
     → "Pay $100" klik → Request 2 → Server → Stripe charge → $100 yenə çıxdı!
                                                               PROBLEM: $200 çıxıb!
```

### Problem niyə yaranır?

Developer ödəniş endpoint-ini sadəcə "giriş yoxla → charge et → sifariş yarat" kimi yazır. Network layer-ında isə müştəri tərəfinin timeout aldığı, amma server-in sorğunu artıq emal etdiyi vəziyyət baş verir. Client-in retry mexanizmi yenidən eyni sorğunu göndərir — server artıq emal edilmiş bir sorğunu yenidən emal edir. İkinci problem: user "Pay" düyməsini iki dəfə basır (şəbəkə yavaş olduqda), iki müstəqil request paralel server-ə çatır — hər ikisi eyni anda stock/ödəniş yoxlamasından keçir, hər ikisi charge edir.

### Nəticələri
- **Müştəri etibarı itirilir**
- **Refund prosesi lazım olur** (vaxt, əmək, komissiya itkisi)
- **Hüquqi problemlər** yarana bilər
- **Mühasibat uyğunsuzluqları**

---

## Həll 1: Idempotency Key Konsepti

### Nədir?

**Idempotency** — eyni əməliyyatı bir neçə dəfə icra etmək, nəticəni dəyişmir. Hər sorğuya unikal bir key (idempotency key) əlavə edirik. Server bu key-i görübsə, əvvəlki nəticəni qaytarır, yeni əməliyyat etmir.

```
Request 1: POST /pay {idempotency_key: "abc123", amount: 100} → Charge edilir → 200 OK
Request 2: POST /pay {idempotency_key: "abc123", amount: 100} → Əvvəlki nəticə qaytarılır → 200 OK (charge yox!)
```

### Idempotency Middleware

*Bu kod eyni idempotency key ilə təkrar gələn sorğuları tutub əvvəlki cavabı qaytaran middleware-i göstərir:*

```php
// app/Http/Middleware/IdempotencyMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * İdempotent key-ə görə dublikat sorğuları tutur.
     * Yalnız state dəyişdirən method-lara (POST, PUT, PATCH) tətbiq olunur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Yalnız state-dəyişdirən method-lar üçün
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (empty($idempotencyKey)) {
            // Key yoxdursa, normal davam edir (və ya 400 qaytara bilər)
            return $next($request);
        }

        // User-ə məxsus key yaradırıq (fərqli user-lər eyni key istifadə edə bilər)
        $userId = $request->user()?->id ?? $request->ip();
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        // Lock key — eyni anda eyni key ilə 2 sorğunun race condition-unu önləmək
        $lockKey = "idempotency_lock:{$userId}:{$idempotencyKey}";

        // Əvvəlcə cache-ə bax — əvvəl icra olunub?
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // Əvvəlki nəticəni qaytarırıq
            return new JsonResponse(
                data: $cached['body'],
                status: $cached['status'],
                headers: array_merge($cached['headers'], [
                    'X-Idempotent-Replayed' => 'true',
                ])
            );
        }

        // Lock al — eyni key ilə paralel sorğuları sırala
        $lock = Cache::lock($lockKey, 30);

        if (!$lock->get()) {
            // Lock ala bilmədik — eyni sorğu artıq icra olunur
            return new JsonResponse([
                'error' => 'Eyni sorğu artıq icra edilir. Zəhmət olmasa gözləyin.',
            ], 409); // 409 Conflict
        }

        try {
            // Lock aldıqdan sonra yenidən cache-ə bax (double-check)
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return new JsonResponse(
                    data: $cached['body'],
                    status: $cached['status'],
                    headers: array_merge($cached['headers'], [
                        'X-Idempotent-Replayed' => 'true',
                    ])
                );
            }

            // Sorğunu icra et
            /** @var JsonResponse $response */
            $response = $next($request);

            // Uğurlu cavabları cache-ə yaz (24 saat)
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                Cache::put($cacheKey, [
                    'body' => json_decode($response->getContent(), true),
                    'status' => $response->getStatusCode(),
                    'headers' => ['Content-Type' => 'application/json'],
                ], now()->addHours(24));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
```

#### Middleware qeydiyyatı

*Bu kod idempotency middleware-ini bütün API route-larına tətbiq etmək üçün qeydiyyatdan keçirir:*

```php
// bootstrap/app.php (Laravel 11)
use App\Http\Middleware\IdempotencyMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            IdempotencyMiddleware::class,
        ]);
    })
    ->create();
```

*Bu kod middleware-i ayrı-ayrı route-lara tətbiq etmənin alternativ yolunu göstərir:*

```php
// Və ya route-a tətbiq etmək (Laravel 11)
// routes/api.php
Route::middleware([IdempotencyMiddleware::class])->group(function () {
    Route::post('/payments', [PaymentController::class, 'charge']);
});
```

---

## Həll 2: Database-Level Prevention (Unique Constraint)

### Migration

*Bu kod dublikat ödənişi DB səviyyəsində önləyən unique constraint-ləri olan payments cədvəlini yaradır:*

```php
// database/migrations/2024_01_02_create_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('payment_id')->unique(); // Bizim unikal ID
            $table->string('idempotency_key')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status'); // pending, processing, completed, failed, refunded
            $table->string('payment_method'); // card, bank_transfer, wallet
            $table->string('provider'); // stripe, paypal
            $table->string('provider_transaction_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // KRITIK: eyni order üçün dublikat uğurlu ödənişi önləyir
            $table->unique(['order_id', 'status'], 'unique_successful_payment');

            // İdempotency key ilə dublikat önləmə
            $table->unique(['user_id', 'idempotency_key'], 'unique_idempotency');

            $table->index(['user_id', 'status']);
            $table->index('provider_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

> **Qeyd:** `unique(['order_id', 'status'])` constraint-i sadələşdirilmiş nümunədir. Real dünyada bunu partial unique index və ya trigger ilə həll etmək daha düzgündür — yalnız `completed` statuslu sətirlərdə unique olsun. PostgreSQL-də bunu belə edirik:

*Bu kod yalnız `completed` statuslu ödənişlər üçün PostgreSQL partial unique index-i göstərir:*

```sql
-- PostgreSQL partial unique index
CREATE UNIQUE INDEX unique_completed_payment_per_order
ON payments (order_id)
WHERE status = 'completed';
```

Laravel migration ilə raw SQL:

*Bu kod Laravel migration-da raw SQL ilə partial unique index yaratmanı göstərir:*

```php
// Migration-da raw statement
public function up(): void
{
    Schema::create('payments', function (Blueprint $table) {
        // ... digər column-lar ...
    });

    // Partial unique index — yalnız completed ödənişlər üçün
    DB::statement('
        CREATE UNIQUE INDEX unique_completed_payment_per_order
        ON payments (order_id)
        WHERE status = \'completed\'
    ');
}
```

---

## Həll 3: Redis Distributed Lock

*Bu kod Redis lock, DB transaction və idempotency key-i birləşdirərək dublikat ödənişi tam önləyən payment service-ini göstərir:*

```php
// app/Services/PaymentService.php
namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\PaymentProcessingException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Ödəniş prosesini başlat — bütün qoruma mexanizmləri ilə.
     *
     * @throws DuplicatePaymentException
     * @throws PaymentProcessingException
     */
    public function processPayment(
        Order $order,
        string $paymentMethod,
        ?string $idempotencyKey = null
    ): Payment {
        $idempotencyKey = $idempotencyKey ?? Str::uuid()->toString();
        $lockKey = "payment_lock:order:{$order->id}";

        // 1. Redis distributed lock — eyni order üçün paralel ödənişi blokla
        $lock = Cache::lock($lockKey, 30); // 30 saniyə timeout

        if (!$lock->get()) {
            throw new DuplicatePaymentException(
                'Bu order üçün ödəniş artıq icra edilir. Zəhmət olmasa gözləyin.'
            );
        }

        try {
            return DB::transaction(function () use ($order, $paymentMethod, $idempotencyKey) {

                // 2. Database-level yoxlama — artıq uğurlu ödəniş var?
                $existingPayment = Payment::where('order_id', $order->id)
                    ->where('status', PaymentStatus::COMPLETED->value)
                    ->first();

                if ($existingPayment) {
                    Log::info('Duplicate payment prevented (existing completed)', [
                        'order_id' => $order->id,
                        'existing_payment_id' => $existingPayment->payment_id,
                    ]);
                    return $existingPayment; // Mövcud uğurlu ödənişi qaytarırıq
                }

                // 3. İdempotency key yoxlaması
                $existingByKey = Payment::where('idempotency_key', $idempotencyKey)
                    ->where('user_id', $order->user_id)
                    ->first();

                if ($existingByKey) {
                    Log::info('Duplicate payment prevented (idempotency key)', [
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    return $existingByKey;
                }

                // 4. Yeni payment yaratmaq (pending status)
                $payment = Payment::create([
                    'payment_id' => Str::uuid()->toString(),
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'status' => PaymentStatus::PENDING->value,
                    'payment_method' => $paymentMethod,
                    'provider' => 'stripe',
                ]);

                // 5. Payment provider-ə sorğu göndər
                $payment->update(['status' => PaymentStatus::PROCESSING->value]);

                try {
                    $chargeResult = $this->stripeService->charge(
                        amount: $payment->amount,
                        currency: $payment->currency,
                        paymentMethod: $paymentMethod,
                        idempotencyKey: $idempotencyKey, // Stripe-a da göndəririk!
                        metadata: [
                            'order_id' => $order->id,
                            'payment_id' => $payment->payment_id,
                        ]
                    );

                    // 6. Uğurlu ödəniş
                    $payment->update([
                        'status' => PaymentStatus::COMPLETED->value,
                        'provider_transaction_id' => $chargeResult['id'],
                        'provider_response' => $chargeResult,
                        'completed_at' => now(),
                    ]);

                    $order->update(['status' => 'paid']);

                    event(new PaymentCompleted($payment));

                    return $payment;

                } catch (\Throwable $e) {
                    // 7. Uğursuz ödəniş
                    $payment->update([
                        'status' => PaymentStatus::FAILED->value,
                        'failure_reason' => $e->getMessage(),
                    ]);

                    event(new PaymentFailed($payment));

                    throw new PaymentProcessingException(
                        "Ödəniş uğursuz oldu: {$e->getMessage()}",
                        previous: $e
                    );
                }
            });
        } finally {
            $lock->release();
        }
    }
}
```

---

## Payment State Machine

### Enum

*Bu kod ödəniş statuslarını və icazə verilən keçidlər matriksini təyin edən PHP enum-u göstərir:*

```php
// app/Enums/PaymentStatus.php
namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case CANCELLED = 'cancelled';

    /**
     * Hər statusdan hansı statuslara keçid mümkündür.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::COMPLETED, self::FAILED],
            self::COMPLETED => [self::REFUNDED, self::PARTIALLY_REFUNDED],
            self::FAILED => [self::PENDING], // yenidən cəhd
            self::REFUNDED => [],
            self::PARTIALLY_REFUNDED => [self::REFUNDED],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Gözləyir',
            self::PROCESSING => 'İcra edilir',
            self::COMPLETED => 'Tamamlandı',
            self::FAILED => 'Uğursuz',
            self::REFUNDED => 'Qaytarıldı',
            self::PARTIALLY_REFUNDED => 'Qismən qaytarıldı',
            self::CANCELLED => 'Ləğv edildi',
        };
    }
}
```

### Model-də State Machine

*Bu kod Payment model-inə status keçid yoxlamasını əlavə edən state machine metodunu göstərir:*

```php
// app/Models/Payment.php (əlavələr)
namespace App\Models;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentTransitionException;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $casts = [
        'status' => PaymentStatus::class,
        'payload' => 'array',
        'provider_response' => 'array',
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /**
     * Status keçidini yoxlayaraq dəyişdirir.
     *
     * @throws InvalidPaymentTransitionException
     */
    public function transitionTo(PaymentStatus $newStatus): void
    {
        $currentStatus = $this->status;

        if (!$currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidPaymentTransitionException(
                "Keçid mümkün deyil: {$currentStatus->value} → {$newStatus->value}"
            );
        }

        $this->update(['status' => $newStatus->value]);
    }
}
```

---

## Stripe Idempotency Key İstifadəsi

*Bu kod idempotency key-i Stripe API-yə ötürən ödəniş yaratma metodunu göstərir:*

```php
// app/Services/StripeService.php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\IdempotencyException;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Stripe-da ödəniş yaratmaq — idempotency key ilə.
     *
     * Stripe eyni idempotency key ilə gələn sorğuya eyni cavabı qaytarır.
     * Bu, network retry və duplicate request problemlərini həll edir.
     */
    public function charge(
        float $amount,
        string $currency,
        string $paymentMethod,
        string $idempotencyKey,
        array $metadata = []
    ): array {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create(
                [
                    'amount' => (int) ($amount * 100), // Stripe cent ilə işləyir
                    'currency' => strtolower($currency),
                    'payment_method' => $paymentMethod,
                    'confirm' => true,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never',
                    ],
                    'metadata' => $metadata,
                ],
                [
                    // KRITIK: Idempotency key Stripe-a ötürülür
                    'idempotency_key' => $idempotencyKey,
                ]
            );

            Log::info('Stripe charge successful', [
                'payment_intent_id' => $paymentIntent->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
            ]);

            return [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
            ];

        } catch (IdempotencyException $e) {
            // Eyni idempotency key fərqli parametrlərlə göndərilib
            Log::error('Stripe idempotency conflict', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (CardException $e) {
            // Kart rədd edildi
            Log::warning('Card declined', [
                'decline_code' => $e->getDeclineCode(),
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
            ]);
            throw $e;
        }
    }
}
```

---

## Webhook Deduplication

Payment provider-lər (Stripe, PayPal və s.) webhook-ları bir neçə dəfə göndərə bilər. Biz dublikatları tutmalıyıq.

*Bu kod webhook dublikatlarını izləmək üçün `webhook_events` cədvəlini `event_id` unique constraint ilə yaradır:*

```php
// database/migrations/2024_01_03_create_webhook_events_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // stripe, paypal
            $table->string('event_id')->unique(); // provider-in event ID-si
            $table->string('event_type'); // payment_intent.succeeded
            $table->json('payload');
            $table->string('status')->default('received'); // received, processed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }
};
```

*Bu kod Stripe webhook-unu imza ilə doğrulayan və dublikat event-ləri deduplication ilə önləyən controller-i göstərir:*

```php
// app/Http/Controllers/StripeWebhookController.php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // 1. İmza doğrulaması
        try {
            $event = Webhook::constructEvent(
                payload: $request->getContent(),
                sigHeader: $request->header('Stripe-Signature'),
                secret: config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // 2. Deduplication — eyni event artıq işlənib?
        $webhookEvent = $this->getOrCreateWebhookEvent($event);

        if ($webhookEvent->status === 'processed') {
            Log::info('Duplicate webhook event ignored', ['event_id' => $event->id]);
            return response()->json(['message' => 'Already processed'], 200);
        }

        // 3. Event-i işlə
        try {
            $this->processEvent($event);

            $webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return response()->json(['message' => 'Processed'], 200);

        } catch (\Throwable $e) {
            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Webhook processing failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            // 500 qaytarırıq ki, Stripe yenidən göndərsin
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function getOrCreateWebhookEvent($event): WebhookEvent
    {
        return WebhookEvent::firstOrCreate(
            [
                'provider' => 'stripe',
                'event_id' => $event->id,
            ],
            [
                'event_type' => $event->type,
                'payload' => json_decode(json_encode($event->data), true),
                'status' => 'received',
            ]
        );
    }

    private function processEvent($event): void
    {
        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'charge.refunded' => $this->handleRefund($event->data->object),
            default => Log::info("Unhandled webhook event type: {$event->type}"),
        };
    }

    private function handlePaymentSucceeded($paymentIntent): void
    {
        $payment = Payment::where('provider_transaction_id', $paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        // İdempotent update — artıq completed-dirsə, heç nə etmə
        if ($payment->status->value === 'completed') {
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $payment->order->update(['status' => 'paid']);
        });
    }

    private function handlePaymentFailed($paymentIntent): void
    {
        $payment = Payment::where('provider_transaction_id', $paymentIntent->id)->first();

        if ($payment && $payment->status->value !== 'failed') {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $paymentIntent->last_payment_error?->message ?? 'Unknown error',
            ]);
        }
    }

    private function handleRefund($charge): void
    {
        $payment = Payment::where('provider_transaction_id', $charge->payment_intent)->first();

        if ($payment) {
            $refundedAmount = $charge->amount_refunded / 100;
            $isFullRefund = $refundedAmount >= $payment->amount;

            $payment->update([
                'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
            ]);
        }
    }
}
```

---

## Payment Controller — Tam Implementation

*Payment Controller — Tam Implementation üçün kod nümunəsi:*
```php
// app/Http/Controllers/PaymentController.php
namespace App\Http\Controllers;

use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\PaymentProcessingException;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Ödəniş etmək — bütün qoruma mexanizmləri ilə.
     */
    public function charge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|string',
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->firstOrFail();

        // İdempotency key — header-dən və ya avtomatik
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $payment = $this->paymentService->processPayment(
                order: $order,
                paymentMethod: $validated['payment_method'],
                idempotencyKey: $idempotencyKey
            );

            return response()->json([
                'message' => 'Ödəniş uğurla tamamlandı',
                'payment' => [
                    'id' => $payment->payment_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status->value,
                ],
            ]);

        } catch (DuplicatePaymentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 'DUPLICATE_PAYMENT',
            ], 409);

        } catch (PaymentProcessingException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 'PAYMENT_FAILED',
            ], 422);
        }
    }
}
```

---

## Reconciliation Process (Uyğunlaşdırma)

Payment sistemi mürəkkəbdir və bəzən data uyğunsuzluqları yaranır. Gündəlik reconciliation job bu problemləri aşkarlayır.

*Payment sistemi mürəkkəbdir və bəzən data uyğunsuzluqları yaranır. Gün üçün kod nümunəsi:*
```php
// app/Jobs/PaymentReconciliationJob.php
namespace App\Jobs;

use App\Models\Payment;
use App\Notifications\ReconciliationAlertNotification;
use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PaymentReconciliationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(StripeService $stripeService): void
    {
        $discrepancies = [];

        // Son 24 saatın ödənişlərini yoxla
        $payments = Payment::where('created_at', '>=', now()->subDay())
            ->whereIn('status', ['completed', 'processing'])
            ->cursor(); // Memory-efficient

        foreach ($payments as $payment) {
            if (!$payment->provider_transaction_id) {
                // Provider transaction ID yoxdur amma status completed — problem
                if ($payment->status->value === 'completed') {
                    $discrepancies[] = [
                        'payment_id' => $payment->payment_id,
                        'issue' => 'Completed payment without provider transaction ID',
                    ];
                }
                continue;
            }

            try {
                // Stripe-dan real statusu yoxla
                $stripePayment = $stripeService->retrieve($payment->provider_transaction_id);

                // Status uyğunsuzluğu
                if ($stripePayment['status'] === 'succeeded' && $payment->status->value !== 'completed') {
                    $discrepancies[] = [
                        'payment_id' => $payment->payment_id,
                        'issue' => "Status mismatch: Stripe={$stripePayment['status']}, Local={$payment->status->value}",
                    ];

                    // Avtomatik düzəliş
                    $payment->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }

                // Məbləğ uyğunsuzluğu
                $stripeAmount = $stripePayment['amount'] / 100;
                if (abs($stripeAmount - $payment->amount) > 0.01) {
                    $discrepancies[] = [
                        'payment_id' => $payment->payment_id,
                        'issue' => "Amount mismatch: Stripe={$stripeAmount}, Local={$payment->amount}",
                    ];
                }

            } catch (\Throwable $e) {
                Log::error('Reconciliation: Failed to check payment', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Uyğunsuzluqlar varsa, alert göndər
        if (!empty($discrepancies)) {
            Log::warning('Payment reconciliation discrepancies found', [
                'count' => count($discrepancies),
                'discrepancies' => $discrepancies,
            ]);

            // Admin-lərə notification göndər
            Notification::route('slack', config('services.slack.finance_channel'))
                ->notify(new ReconciliationAlertNotification($discrepancies));
        }
    }
}
```

#### Scheduled Command

*Scheduled Command üçün kod nümunəsi:*
```php
// app/Console/Kernel.php və ya bootstrap/app.php (Laravel 11)
use App\Jobs\PaymentReconciliationJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PaymentReconciliationJob())
    ->dailyAt('02:00')  // Hər gün saat 02:00-da
    ->onOneServer();     // Çoxlu server olduqda yalnız birində
```

---

## Frontend-də Double Click Prevention

Backend qorumasına əlavə olaraq, frontend-də də sadə qoruma tətbiq etmək vacibdir:

*Backend qorumasına əlavə olaraq, frontend-də də sadə qoruma tətbiq etm üçün kod nümunəsi:*
```javascript
// Frontend — Pay düyməsini disable etmək
const payButton = document.getElementById('pay-button');

payButton.addEventListener('click', async function() {
    // 1. Düyməni dərhal disable et
    this.disabled = true;
    this.textContent = 'İcra edilir...';

    // 2. Unikal idempotency key yarat (UUID v4)
    const idempotencyKey = crypto.randomUUID();

    try {
        const response = await fetch('/api/payments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Idempotency-Key': idempotencyKey, // Hər klik üçün yeni key
            },
            body: JSON.stringify({
                order_id: orderId,
                payment_method: selectedPaymentMethod,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            showSuccess('Ödəniş uğurlu oldu!');
            redirectToReceipt(data.payment.id);
        } else if (response.status === 409) {
            showInfo('Ödəniş artıq icra edilib.');
        } else {
            showError(data.error || 'Ödəniş uğursuz oldu');
            this.disabled = false; // Uğursuz olduqda yenidən cəhd imkanı
            this.textContent = 'Ödə';
        }
    } catch (err) {
        showError('Şəbəkə xətası. Yenidən cəhd edin.');
        this.disabled = false;
        this.textContent = 'Ödə';
    }
});
```

---

## Xülasə — Qoruma Səviyyələri

| Səviyyə | Mexanizm | Nə qoruyur |
|---------|---------|-------------|
| **Frontend** | Button disable, idempotency key | Double click |
| **API Middleware** | Idempotency middleware + Redis lock | Duplicate request |
| **Application** | PaymentService lock + status yoxlama | Race condition |
| **Database** | Unique constraint / partial index | Data integrity |
| **Provider** | Stripe idempotency key | Provider-level duplicate |
| **Webhook** | Event ID deduplication | Webhook duplicate |
| **Reconciliation** | Gündəlik job | Data uyğunsuzluğu |

---

## Interview-da Bu Sualı Necə Cavablandırmaq

1. **Problemi izah edin** — double charge nədir, hansı hallarda baş verir
2. **Multi-layer defense** — tək bir həll yetərli deyil, bir neçə səviyyədə qoruma lazımdır
3. **Idempotency key** konseptini izah edin — ən vacib pattern
4. **State machine** — payment-in lifecycle-ını izah edin
5. **Database constraint** — son qoruma xətti
6. **Reconciliation** — heç bir sistem 100% mükəmməl deyil, reconciliation lazımdır
7. **Kod yazın** — middleware, service, webhook handler

---

## Anti-patternlər

**1. Idempotency key olmadan ödəniş**
Şəbəkə kəsilsə retry edir, ikinci charge olur — müştəri şikayəti, chargeback. Hər ödəniş üçün unikal idempotency key generate edin, DB-də saxlayın.

**2. Yalnız webhook-a etibar etmək**
Payment gateway webhook göndərməyə bilər (timeout, server down). Webhook + polling (reconciliation job) birlikdə istifadə edin.

**3. Webhook imzasını yoxlamamaq**
`$_POST` data-nı birbaşa işləmək — fake webhook injection. `hash_hmac` ilə gateway signature-nı verify edin.

**4. Payment state-ini memory-də saxlamaq**
Job restart olsa state itirir — ödəniş tamamlanmadı, user charge olundu. State mütləq DB-də persist olunmalıdır.

**5. Race condition üçün lock olmamaq**
İki eyni vaxtda gələn request eyni order-ı ödəməyə çalışırsa — double charge. `DB::transaction()` + pessimistic locking (`lockForUpdate()`).

**6. Ödəniş uğursuzluğunu silent silmək**
`try/catch` içindən exception-ı udmaq — ödəniş failed, user bilmir, support bilmir. Failed ödənişləri log et, alert qur, user-a aydın xəta mesajı göstər.

**7. Idempotency key-i client-in idarəsinə tamamilə buraxmaq**
Client eyni UUID-ni fərqli məbləğlər üçün istifadə etsə — birinci məbləğ qaytarılır, ikinci əməliyyat baş vermir. Server tərəfində key + user_id + məbləğ kombinasiyasını yoxlayın; uyğunsuzluq varsa 422 qaytarın.

**8. Reconciliation job-unu işlətməmək**
Heç bir sistem 100% etibarlı deyil — webhook çatmaya bilər, job fail ola bilər. Gündəlik reconciliation job olmadan uyğunsuzluqlar həftələrlə fark edilmir. Hər gün provider API-dən transaction listini çəkin, local DB ilə müqayisə edin.
