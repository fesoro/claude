# Webhooks

## Nədir? (What is it?)

Webhook event-driven HTTP callback mexanizmidir. Mueyyyen event bas verdikde bir sistemin basqa sisteme HTTP POST request gondermesidir. Polling yerine push model istifade edir - "sene xeber verirem" prinsipi ile isleyir.

```
Polling (pis):
  Sizin app her 1 deq: "Yeni odenis var?"  -> "Xeyr"
  Sizin app her 1 deq: "Yeni odenis var?"  -> "Xeyr"
  Sizin app her 1 deq: "Yeni odenis var?"  -> "Beli!"
  (Coxlu bosuna request)

Webhook (yaxsi):
  Stripe: "Odenis ugurlu oldu!" --> POST sizin-app.com/webhooks/stripe
  (Yalniz event olduqda bir request)
```

## Necə İşləyir? (How does it work?)

### Webhook Flow

```
Event Source (Stripe)                Your Application
       |                                    |
       |  1. Event bas verir               |
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
       |  (200 alinmazsa retry eder)       |
```

### Webhook Security (HMAC Signature)

```
Niye signature lazimdir?
  Her kes sizin webhook URL-inize POST gondere biler!
  Yalniz legit source-dan geleni qebul etmeliyik.

HMAC Verification:
  1. Provider (Stripe) shared secret ile payload-u imzalayir:
     signature = HMAC-SHA256(payload, webhook_secret)

  2. Signature-i header-de gonderir:
     Stripe-Signature: t=timestamp,v1=signature_hash

  3. Siz oz terefde eyni hesablamanı edirsiniz:
     expected = HMAC-SHA256(payload, your_copy_of_secret)
     if (expected === received_signature) -> LEGIT
     else -> REJECT
```

### Retry Strategy

```
Eger sizin server 200 qaytarmazsa provider retry edir:

Stripe retry schedule:
  Attempt 1:  Immediately
  Attempt 2:  ~1 hour later
  Attempt 3:  ~2 hours later
  ...
  Attempt 16: ~3 days later (son cehd)

GitHub retry:
  1 retry, 10 saniye sonra

Tipik retry strategy:
  Exponential backoff: 1s, 2s, 4s, 8s, 16s, 32s...
  Max retries: 5-15
  Max duration: 3-7 gun
```

### Idempotency

```
Eyni event bir nece defe gele biler (retry sebebile).
Meselen: payment.succeeded 3 defe geldi -> 3 defe odenis islemek OLMAZ!

Hell yolu: Event ID ile idempotency
  1. Her event-in unique ID-si var
  2. Processed event ID-leri database-de saxlayin
  3. Event gelende: "Bu ID evvel islenib? Beli -> skip"

processed_webhooks table:
  | id | event_id          | processed_at         |
  |----|-------------------|----------------------|
  | 1  | evt_1234567890    | 2026-04-16 10:00:00  |
  | 2  | evt_0987654321    | 2026-04-16 10:01:00  |
```

## Əsas Konseptlər (Key Concepts)

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

## PHP/Laravel ilə İstifadə

### Stripe Webhook Handler

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
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature invalid', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // 2. Idempotency check
        if (ProcessedWebhook::where('event_id', $event->id)->exists()) {
            return response()->json(['status' => 'already processed']);
        }

        // 3. Event handling
        try {
            match ($event->type) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
                'customer.subscription.created' => $this->handleSubscriptionCreated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.payment_failed' => $this->handleInvoiceFailed($event->data->object),
                default => Log::info("Unhandled Stripe event: {$event->type}"),
            };

            // 4. Processed olaraq qeyd et
            ProcessedWebhook::create([
                'source' => 'stripe',
                'event_id' => $event->id,
                'event_type' => $event->type,
                'payload' => $payload,
                'processed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing error', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            // 500 qaytarib Stripe-in retry etmesini isteyin
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handlePaymentSucceeded($paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($order) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'amount_paid' => $paymentIntent->amount / 100,
            ]);

            // Notification gonder
            $order->user->notify(new \App\Notifications\PaymentReceived($order));
        }
    }

    private function handlePaymentFailed($paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        $order?->update(['status' => 'payment_failed']);
    }

    private function handleSubscriptionCreated($subscription): void
    {
        // Subscription yaratmaq
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        // Subscription legv etmek
    }

    private function handleInvoiceFailed($invoice): void
    {
        // Invoice failure handle
    }
}
```

### GitHub Webhook Handler

```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // HMAC signature verify
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $secret = config('services.github.webhook_secret');

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $event = $request->header('X-GitHub-Event');
        $data = $request->all();

        match ($event) {
            'push' => $this->handlePush($data),
            'pull_request' => $this->handlePullRequest($data),
            'issues' => $this->handleIssue($data),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function handlePush(array $data): void
    {
        $branch = str_replace('refs/heads/', '', $data['ref']);
        $commits = $data['commits'];

        if ($branch === 'main') {
            // Auto-deploy trigger
            dispatch(new \App\Jobs\DeployApplication($data['after']));
        }
    }

    private function handlePullRequest(array $data): void
    {
        if ($data['action'] === 'opened') {
            // Auto-review, CI trigger
        }
    }

    private function handleIssue(array $data): void
    {
        // Issue tracking
    }
}
```

### Webhook Gondermek (Sizin app-dan)

```php
namespace App\Services;

use App\Models\WebhookEndpoint;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    /**
     * Webhook gonder
     */
    public function dispatch(string $event, array $data): void
    {
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($endpoints as $endpoint) {
            dispatch(new \App\Jobs\SendWebhook($endpoint, $event, $data));
        }
    }

    /**
     * Webhook delivery (job-dan cagirilir)
     */
    public function send(WebhookEndpoint $endpoint, string $event, array $data): void
    {
        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'webhook_id' => $webhookId = (string) \Illuminate\Support\Str::uuid(),
        ]);

        // HMAC signature
        $signature = hash_hmac('sha256', $payload, $endpoint->secret);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-ID' => $webhookId,
                    'X-Webhook-Event' => $event,
                    'User-Agent' => 'MyApp-Webhook/1.0',
                ])
                ->post($endpoint->url, json_decode($payload, true));

            $delivery->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
            ]);
        } catch (\Exception $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e; // Job retry etsin
        }
    }
}

// Webhook gondermek
$dispatcher = app(WebhookDispatcher::class);
$dispatcher->dispatch('order.created', [
    'order_id' => $order->id,
    'total' => $order->total,
    'customer_email' => $order->customer_email,
]);
```

### Webhook Job with Retry

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

    public int $tries = 5;
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
        // Butun retry-lar ugursuz oldu
        // Admin-e bildirin, endpoint-i disable edin
        $this->endpoint->increment('consecutive_failures');
        if ($this->endpoint->consecutive_failures >= 10) {
            $this->endpoint->update(['is_active' => false]);
        }
    }
}
```

## Interview Sualları

### 1. Webhook nedir ve nece isleyir?
**Cavab:** Webhook event bas verdikde bir sistemin basqa sisteme HTTP POST gondermesidir. Meselen, Stripe odenis ugurlu olanda sizin app-in `/webhooks/stripe` endpoint-ine POST gonderir. Push model-dir - polling yerine lazim olanda xeber verir.

### 2. Webhook-u nece tehlukesiz edirsiniz?
**Cavab:** HMAC signature verification: provider shared secret ile payload-u imzalayir, siz signature-i verify edirsiniz. Timestamp check: replay attack-den qorunmaq ucun. HTTPS: traffic sifreli olmali. IP whitelist: provider-in IP-lerini whitelist edin.

### 3. Idempotency nedir ve niye vacibdir?
**Cavab:** Eyni webhook-u bir nece defe islemek eyni neticeni vermelidir. Provider retry edende eyni event tekrar gelir. Event ID-ni DB-de saxlayib dublikat event-leri skip edin. Olmasa eyni odenis 3 defe islene biler.

### 4. Webhook cavab vermirsense ne olur?
**Cavab:** Provider retry edir (exponential backoff ile). Stripe 72 saata qeder retry eder. Ardarda ugursuzluq olanda provider webhook endpoint-i disable ede biler. Buna gore tez cavab vermek (200 OK) ve processing-i queue-a gondermek lazimdir.

### 5. Webhook processing niye async olmalidir?
**Cavab:** Provider timeout limiti var (adeten 5-30 saniye). Uzun processing 504 timeout-a sebeb olur ve provider retry eder. Dogru yol: derhâl 200 OK qaytarin, processing-i queue job ile arxaplanda edin.

### 6. Sizin app-dan webhook nece gonderirsiniz?
**Cavab:** Event bas verende registered endpoint-lere HTTP POST gonderin, HMAC signature ile imzalayin, retry mexanizmi qurun (queue job + backoff), delivery log saxlayin, ardarda failure-de endpoint-i disable edin.

## Best Practices

1. **Signature verify hemise** - HMAC ile authenticity yoxlayin
2. **Idempotency** - Event ID ile dublikat processing-in qarsisini alin
3. **Async processing** - 200 qaytarin, queue-da isleyin
4. **Retry with backoff** - Exponential backoff ile retry
5. **Delivery logging** - Butun webhook deliveryleri loglayin
6. **Timeout handling** - 30 saniye timeout teyin edin
7. **Payload validation** - Schema validation tetbiq edin
8. **Endpoint monitoring** - Failure rate izleyin, auto-disable
9. **Replay protection** - Timestamp yoxlayin (5 deqiqeden kohne reject)
10. **HTTPS only** - Yalniz HTTPS endpoint-lere webhook gonderin
