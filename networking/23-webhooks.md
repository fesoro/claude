# Webhooks (Middle)

## İcmal

Webhook event-driven HTTP callback mexanizmidir. Müəyyən event baş verdikdə bir sistemin başqa sistemə HTTP POST request göndərməsidir. Polling yerinə push model istifadə edir — "sənə xəbər verirəm" prinsipi ilə işləyir.

```
Polling (pis):
  Sizin app hər 1 dəq: "Yeni ödəniş var?"  -> "Xeyr"
  Sizin app hər 1 dəq: "Yeni ödəniş var?"  -> "Xeyr"
  Sizin app hər 1 dəq: "Yeni ödəniş var?"  -> "Bəli!"
  (Çoxlu boşuna request)

Webhook (yaxşı):
  Stripe: "Ödəniş uğurlu oldu!" --> POST sizin-app.com/webhooks/stripe
  (Yalnız event olduqda bir request)
```

## Niyə Vacibdir

Real layihələrdə ödəniş prosessorları (Stripe, PayPal), CI/CD sistemləri (GitHub Actions), CRM-lər (HubSpot) webhook vasitəsilə sizin tətbiqinizi event-lərdən xəbərdar edir. Polling həmin məlumatı almağın ən pis yoludur — resurs israf edir, gecikmə yaranır. Webhook-u düzgün implement etmək: signature verification, idempotency, async processing — production-da mütləq lazım olan biliklərdir.

## Əsas Anlayışlar

### Webhook Flow

```
Event Source (Stripe)                Your Application
       |                                    |
       |  1. Event baş verir               |
       |     (payment.succeeded)           |
       |                                    |
       |--- 2. POST /webhooks/stripe ------>|
       |    Content-Type: application/json  |
       |    Stripe-Signature: t=...,v1=...  |
       |    {                               |
       |      "type": "payment_intent.      |
       |               succeeded",          |
       |      "data": {"amount": 5000}      |
       |    }                               |
       |                                    |
       |                                    |-- 3. Signature verify
       |                                    |-- 4. Event process
       |                                    |-- 5. Idempotency check
       |                                    |
       |<-- 6. 200 OK ---------------------|
       |                                    |
       |  (200 alınmazsa retry edər)       |
```

### Webhook Security (HMAC Signature)

```
Niyə signature lazımdır?
  Hər kəs sizin webhook URL-inizə POST göndərə bilər!
  Yalnız legit source-dan gələni qəbul etməliyik.

HMAC Verification:
  1. Provider (Stripe) shared secret ilə payload-u imzalayır:
     signature = HMAC-SHA256(payload, webhook_secret)

  2. Signature-i header-də göndərir:
     Stripe-Signature: t=timestamp,v1=signature_hash

  3. Siz öz tərəfdə eyni hesablamanı edirsiniz:
     expected = HMAC-SHA256(payload, your_copy_of_secret)
     if (expected === received_signature) -> LEGIT
     else -> REJECT
```

### Retry Strategy

```
Əgər sizin server 200 qaytarmazsa provider retry edir:

Stripe retry schedule:
  Attempt 1:  Immediately
  Attempt 2:  ~1 hour later
  Attempt 3:  ~2 hours later
  ...
  Attempt 16: ~3 days later (son cəhd)

GitHub retry:
  1 retry, 10 saniyə sonra

Tipik retry strategy:
  Exponential backoff: 1s, 2s, 4s, 8s, 16s, 32s...
  Max retries: 5-15
  Max duration: 3-7 gün
```

### Idempotency

```
Eyni event bir neçə dəfə gələ bilər (retry səbəbilə).
Məsələn: payment.succeeded 3 dəfə gəldi -> 3 dəfə ödəniş işləmək OLMAZ!

Həll yolu: Event ID ilə idempotency
  1. Hər event-in unique ID-si var
  2. Processed event ID-lərini database-də saxlayın
  3. Event gələndə: "Bu ID əvvəl işlənib? Bəli -> skip"

processed_webhooks table:
  | id | event_id          | processed_at         |
  |----|-------------------|----------------------|
  | 1  | evt_1234567890    | 2026-04-16 10:00:00  |
  | 2  | evt_0987654321    | 2026-04-16 10:01:00  |
```

### Webhook vs Polling vs WebSocket

```
+------------------+-----------+-----------+-----------+
| Feature          | Webhook   | Polling   | WebSocket |
+------------------+-----------+-----------+-----------+
| Direction        | Push      | Pull      | Both      |
| Protocol         | HTTP POST | HTTP GET  | WS        |
| Realtime         | Near-RT   | Delayed   | Real-time |
| Connection       | No state  | Repeated  | Persistent|
| Server load      | Low       | High      | Medium    |
| Reliability      | Retry     | Guaranteed| Complex   |
| Use case         | Events    | Data sync | Live data |
+------------------+-----------+-----------+-----------+
```

## Praktik Baxış

**Üstünlüklər:**
- Server resursları səmərəli istifadə olunur (polling yoxdur)
- Event gəldiyi anda dərhal işlənir
- Simple HTTP — hər dildə implement etmək asan

**Trade-off-lar:**
- Endpoint public olmalıdır — security-yə diqqət lazım
- Provider-in retry etməsi idempotency tələb edir
- Webhook delivery monitoring ayrıca qurulmalıdır

**Nə vaxt istifadə edilməməlidir:**
- Real-time, 2-yönlü kommunikasiya lazım olduqda (WebSocket daha uyğun)
- Provider webhook göndərmirsə (polling-dən başqa çara yoxdur)

**Anti-pattern-lər:**
- Signature verify etməmək (security breach riski)
- Processing-i synchronous etmək (provider timeout-u trigger edir, retry başlayır)
- Idempotency yoxlamadan işləmək (duplicate ödəniş, email, etc.)
- 200 qaytarmamaq uğurlu işləmədən sonra — provider retry edir

## Nümunələr

### Ümumi Nümunə

Webhook handler-in üç əsas mərhələsi var:
1. **Signature verify** — provider-dən gəlib-gəlmədiyini yoxla
2. **Idempotency check** — bu event-i əvvəl işlədib-işlətmədiyini yoxla
3. **Async processing** — dərhal 200 qaytar, işi queue-a at

### Kod Nümunəsi

**Stripe Webhook Handler:**

```php
// routes/api.php
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

```php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProcessedWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // 1. Signature verify
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature invalid', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // 2. Idempotency check
        if (ProcessedWebhook::where('event_id', $event->id)->exists()) {
            return response()->json(['status' => 'already processed']);
        }

        // 3. Event handling
        try {
            match ($event->type) {
                'payment_intent.succeeded'          => $this->handlePaymentSucceeded($event->data->object),
                'payment_intent.payment_failed'     => $this->handlePaymentFailed($event->data->object),
                'customer.subscription.created'     => $this->handleSubscriptionCreated($event->data->object),
                'customer.subscription.deleted'     => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.payment_failed'            => $this->handleInvoiceFailed($event->data->object),
                default => Log::info("Unhandled Stripe event: {$event->type}"),
            };

            // 4. Processed olaraq qeyd et
            ProcessedWebhook::create([
                'source'       => 'stripe',
                'event_id'     => $event->id,
                'event_type'   => $event->type,
                'payload'      => $payload,
                'processed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing error', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
            // 500 qaytarıb Stripe-in retry etməsini istəyin
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handlePaymentSucceeded($paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($order) {
            $order->update([
                'status'     => 'paid',
                'paid_at'    => now(),
                'amount_paid'=> $paymentIntent->amount / 100,
            ]);

            $order->user->notify(new \App\Notifications\PaymentReceived($order));
        }
    }

    private function handlePaymentFailed($paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        $order?->update(['status' => 'payment_failed']);
    }

    private function handleSubscriptionCreated($subscription): void {}
    private function handleSubscriptionDeleted($subscription): void {}
    private function handleInvoiceFailed($invoice): void {}
}
```

**GitHub Webhook Handler:**

```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Hub-Signature-256');
        $payload   = $request->getContent();
        $secret    = config('services.github.webhook_secret');

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $event = $request->header('X-GitHub-Event');
        $data  = $request->all();

        match ($event) {
            'push'         => $this->handlePush($data),
            'pull_request' => $this->handlePullRequest($data),
            'issues'       => $this->handleIssue($data),
            default        => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function handlePush(array $data): void
    {
        $branch  = str_replace('refs/heads/', '', $data['ref']);
        $commits = $data['commits'];

        if ($branch === 'main') {
            dispatch(new \App\Jobs\DeployApplication($data['after']));
        }
    }

    private function handlePullRequest(array $data): void
    {
        if ($data['action'] === 'opened') {
            // Auto-review, CI trigger
        }
    }

    private function handleIssue(array $data): void {}
}
```

**Webhook Göndərmək (Sizin app-dan):**

```php
namespace App\Services;

use App\Models\WebhookEndpoint;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    public function dispatch(string $event, array $data): void
    {
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($endpoints as $endpoint) {
            dispatch(new \App\Jobs\SendWebhook($endpoint, $event, $data));
        }
    }

    public function send(WebhookEndpoint $endpoint, string $event, array $data): void
    {
        $payload = json_encode([
            'event'      => $event,
            'data'       => $data,
            'timestamp'  => now()->toISOString(),
            'webhook_id' => $webhookId = (string) \Illuminate\Support\Str::uuid(),
        ]);

        $signature = hash_hmac('sha256', $payload, $endpoint->secret);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'webhook_id'          => $webhookId,
            'event'               => $event,
            'payload'             => $payload,
            'status'              => 'pending',
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-Webhook-Signature'=> $signature,
                    'X-Webhook-ID'       => $webhookId,
                    'X-Webhook-Event'    => $event,
                    'User-Agent'         => 'MyApp-Webhook/1.0',
                ])
                ->post($endpoint->url, json_decode($payload, true));

            $delivery->update([
                'status'          => $response->successful() ? 'delivered' : 'failed',
                'response_status' => $response->status(),
                'response_body'   => substr($response->body(), 0, 1000),
            ]);
        } catch (\Exception $e) {
            $delivery->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e; // Job retry etsin
        }
    }
}
```

**Webhook Job with Retry (Exponential Backoff):**

```php
namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 5;
    public array $backoff = [60, 300, 900, 3600, 7200]; // 1m, 5m, 15m, 1h, 2h

    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $data
    ) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->send($this->endpoint, $this->event, $this->data);
    }

    public function failed(\Throwable $exception): void
    {
        $this->endpoint->increment('consecutive_failures');
        if ($this->endpoint->consecutive_failures >= 10) {
            $this->endpoint->update(['is_active' => false]);
        }
    }
}
```

## Praktik Tapşırıqlar

1. **Stripe webhook qurmaq:** Stripe dashboard-da test webhook endpoint yaradın. `stripe listen --forward-to localhost:8000/webhooks/stripe` CLI-si ilə local test edin. `payment_intent.succeeded` event-ini simulate edib sisteminizin düzgün işlədiyini yoxlayın.

2. **Idempotency table:** `processed_webhooks` migration-ı yaradın. `event_id` sütununa unique index qoyun. Eyni event-i iki dəfə göndərib dublikat işləmənin baş vermədiyini yoxlayın.

3. **Async processing:** Webhook handler-dən işi `ProcessWebhookEvent` job-una köçürün. Handler yalnız signature verify + idempotency check + 200 qaytar. Job üzərinə business logic yerləşdirin. `php artisan queue:work` ilə test edin.

4. **Öz webhook sistemi:** `WebhookEndpoint` model-i yaradın. `OrderCreated` event-i baş verdikdə registered endpoint-lərə `WebhookDispatcher::dispatch()` ilə notification göndərin. Delivery log-unu saxlayın.

5. **Failure monitoring:** `consecutive_failures` sayacını artırın. 10 uğursuz delivery-dən sonra endpoint-i avtomatik deaktiv edin. Admin-ə notification göndərin.

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [API Security](17-api-security.md)
- [WebSocket](11-websocket.md)
- [SSE](12-sse.md)
- [Long Polling](13-long-polling.md)
