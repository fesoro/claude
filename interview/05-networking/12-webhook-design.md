# Webhook Design (Senior ⭐⭐⭐)

## İcmal

Webhook — bir sistemin digər sistemə HTTP POST ilə event bildirməsi mexanizmidir. "Don't call us, we'll call you" prinsipi — polling əvəzinə server event baş verdikdə özü bildiriş göndərir. Stripe payment processed, GitHub push event, Slack message, Shopify order created — bunların hamısı webhook-dur. Backend developer kimi həm webhook provider (göndərən), həm consumer (qəbul edən) tərəfin dizaynını bilmək vacibdir. Interview-larda bu mövzu event-driven architecture, async processing, reliability sualları ilə birlikdə gəlir.

## Niyə Vacibdir

Webhook sadə görünür — "HTTP POST göndər". Lakin production-da reliability, security, idempotency, retry, ordering, fan-out — bunların hər biri ayrıca mürəkkəblik yaradır. Interviewer bu sualda yoxlayır: "Webhook delivery failure halında nə olar? HMAC signature niyə vacibdir? Receiver-in yavaş olması göndərəni necə təsir edir? Fan-out arxitekturası necə qurulur?" Bu suallara cavab verə bilmək event-driven sistem dizaynı biliyini göstərir.

## Əsas Anlayışlar

**Webhook provider (göndərən) tərəfi:**
- Event baş verir → database-ə event yazılır → async queue (Kafka, RabbitMQ, SQS) → webhook delivery worker → HTTP POST to subscriber URL
- **Async delivery**: Event handler-da birbaşa HTTP call etmək yanlışdır — subscriber yavaş olsa event handler blok olunur, transaction uzanır
- **Retry logic**: Delivery uğursuz olduqda exponential backoff ilə yenidən cəhd: 1min, 5min, 30min, 2h, 24h. HTTP 5xx, timeout → retry. HTTP 2xx → success. HTTP 4xx (403, 404) → retry etmə, endpoint yanlışdır
- **Retry sonrası failure**: Dead letter queue (DLQ), subscriber-ə email notification, dashboard-da "failed" status, auto-deactivation
- **Ordering guarantee**: Eyni entity üçün eventlər sırayla çatdırılmalıdır (payment.created → payment.succeeded → payment.refunded). Kafka partition key (entity ID) ilə ordering təmin olunur
- **Fan-out**: Bir event üçün çox subscriber. Hər subscriber üçün ayrı delivery attempt. Kafka consumer group + parallel workers
- **Payload dizaynı**: Event type, event ID (UUID), timestamp, entity ID, data, API version. Thin vs Fat trade-off var
- **Delivery timeout**: Subscriber-in cavab verməsi üçün timeout (10-30 saniyə). Timeout = failure kimi sayılır, retry başlayır
- **Per-subscriber rate limiting**: Bir subscriber-in kapasitəsi məhduddursa, ona delivery-ni tamamlamaq lazımdır

**Webhook security — HMAC signature:**
- Provider payload-ı shared secret key ilə SHA-256 HMAC hesablayır: `HMAC-SHA256(timestamp + "." + payload, secret_key)`
- `X-Webhook-Signature: sha256=abc123def` header-ında göndərilir (Stripe-ın yanaşması)
- Receiver eyni hesablamanı edir, uyğun gəlirsə etibarlıdır, uyğunsuzluqda 401
- **Timing attack**: String comparison `===` əvəzinə `hash_equals()` istifadə edin — constant-time comparison. Time-based fərqdən imza öyrənilməsin
- **Replay attack**: Timestamp payload-da saxlanılır. Receiver son 5 dəqiqədən köhnə event-ləri rədd edir — attacker köhnə valid webhook-u yenidən göndərə bilməsin
- **IP whitelist**: Provider-in statik IP range-i whitelist-ə alınır (amma cloud provider-lərdə dinamik IP-lər çətin)
- **mTLS**: Provider client certificate ilə authenticate olur — en güclü, amma konfiqurasiya mürəkkəbdir

**Webhook consumer (qəbul edən) tərəfi:**
- **Tez cavab ver**: Webhook endpoint-i 5-10 saniyə içərisində 200 OK qaytarmalıdır. Uzun işləri async queue-ya ötür
- **Idempotency**: Eyni event-i iki dəfə almaq mümkündür (retry səbəbindən). `event_id` ilə deduplicate edin — `processed_webhooks` cədvəlinə insert, duplicate KEY exception = already processed
- **Signature verification**: Hər webhook-u HMAC ilə doğrula — tam trust etmə, source IP-sini yoxla
- **Event ordering**: Provider ordering zəmanət vermirsə, event-i timestamp/sequence ilə sırala, ya da idempotent handler yaz
- **Circuit breaker**: Webhook endpoint-in consistent failure-ı varsa, provider delivery-ni azaldır/dayandırır. Consumer-in recovery-si provider-i avtomatik detect etməlidir

**Thin webhook vs Fat webhook — trade-off:**
- Thin (pull model): Yalnız event type + entity ID göndərilir. Receiver API-dən tam data çəkir. Üstünlük: payload kiçik, data həmişə fresh. Çatışmazlıq: hər webhook üçün API call
- Fat (push model): Tam data payload göndərilir. Receiver API call etmir. Üstünlük: az API call, offline processing mümkün. Çatışmazlıq: böyük payload, stale data riski (event delay-i varsa), sensitive data exposure
- Praktikada: Balanslaşdırılmış — kritik fields + full resource URL. Məs: `{event: "order.paid", order_id: "123", amount: 99.99, status: "paid", resource_url: "/api/orders/123"}`

**Webhook vs Polling vs SSE:**
- Polling: Hər N saniyədən bir "yeni bir şey varmı?" — wasteful, lakin sadə, receiver always available
- Webhook: Event olduqda bildiriş — efficient, lakin receiver əlçatan olmalıdır, complex retry logic
- SSE: Server-initiated stream — browser-based, tek yönlü. Webhook-dan fərqli olaraq persistent connection

**Event versioning:**
- Payload structure-u dəyişdikdə `event_schema_version: "2"` field-i əlavə edin
- Consumer eski versiyaları da handle etməlidir — rolling upgrade zamanı

## Praktik Baxış

**Interview-da yanaşma:**
Webhook dizaynını "sadə HTTP POST" kimi başlatıb tədricən mürəkkəbləşdirin: delivery reliability → security → idempotency → fan-out arxitekturası. Bu progression interviewer-ə sistem dizayn düşüncənizi göstərir.

**Follow-up suallar (top companies-da soruşulur):**
- "Subscriber saatlarla offline olsa nə baş verir?" → Retry queue-da birikirsə, max TTL-dən sonra DLQ-ya. Subscriber geri qayıdanda `/missed-events` endpoint-i ilə catch-up edə bilər (cursor-based pagination ilə)
- "Webhook idempotency Redis vs DB?" → DB `event_id` UNIQUE constraint daha reliable, Redis TTL-lə deduplicate ola bilər lakin TTL expire olarsa duplicate processing
- "1 milyon subscriber-a webhook göndərmək üçün arxitektura?" → Kafka fan-out: bir topic, çox consumer group. Per-subscriber queue. Parallel workers. Rate limiting per subscriber. DLQ. Backoff per subscriber
- "Webhook delivery order zəmanəti?" → Kafka partition key = entity ID, consumer sırayla işləyir. Lakin retry-lar ordering-i poza bilər — consumer-in ordering enforce etməsi lazımdır
- "Webhook endpoint 10 saniyəlik işi görürsə nə edərsiniz?" → Endpoint dərhal queue-ya push etsin, job background-da işlənsin, 200 OK tez qayıtdırılsın
- "Webhook-ı test etmək necə?" → ngrok/localtunnel ilə local-ı public URL ilə expose edin. Stripe CLI webhook-ları local-a forward edir. Webhook.site ilə request-ləri inspect edin
- "Webhook-da versioning necə həll olunur?" → `api_version` field payload-da, ya da URL-də (`/v2/webhook`). Consumer-i backward compatible yazın

**Ümumi səhvlər (candidate-ların etdiyi):**
- Webhook handler-da sinxron uzun processing etmək (timeout riski — provider failure hesab edir, retry başlayır)
- HMAC signature doğrulamamaq — istənilən kəs endpoint-ə POST edə bilər
- Idempotency handle etməmək — double charge, double email kimi duplicate processing
- Retry olmadan webhook göndərmək — bir failure bütün event-i itirmək
- Fan-out-u single thread-də handle etmək — 10000 subscriber = 10000 HTTP call seriyayla

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Replay attack prevention-ı (timestamp validation), fan-out arxitekturasını (Kafka partition + parallel workers), circuit breaker per subscriber-i, ya da thin vs fat webhook trade-off-larını dərinliklə izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Design a webhook system for a payment platform. When a payment is processed, we need to notify thousands of merchants in real-time. What are the key design considerations?"

### Güclü Cavab

Thousands-of-merchant webhook delivery sistemi üçün belə arxitektura quraram:

**Event pipeline:** Payment service → Kafka topic (`payment.events`) → Webhook dispatcher service.

**Fan-out:** Hər merchant üçün ayrı delivery task. Kafka consumer group ilə paralel workers. Per-merchant queue + per-merchant rate limiting.

**Reliability:** Exponential backoff retry (1min, 5min, 30min, 2h, 24h). 5 uğursuz cəhddən sonra merchant-ə email notification + dashboard "attention needed" badge. Circuit breaker: merchant endpoint 10 dəfə ardıcıl fail edərsə 1 saatlıq deaktiv, sonra probe.

**Security:** Hər merchant üçün unique secret key (key rotation dəstəyi). HMAC-SHA256 signature + timestamp. 5 dəqiqədən köhnə webhook-u reject.

**Idempotency:** Event UUID payload-da. Merchant client-ləri bu UUID-ni UNIQUE constraint ilə DB-ə insert — duplicate = already processed.

**Observability:** Hər delivery attempt log edilir (merchant_id, event_id, endpoint, status_code, duration, attempt_number). Merchant dashboard-da delivery history + retry timeline.

### Kod Nümunəsi

```php
// Webhook provider tərəfi — göndərən
class WebhookDispatcher
{
    public function dispatch(WebhookEvent $event, Subscriber $subscriber): void
    {
        $payload   = $event->toArray();
        $timestamp = time();
        $secret    = $subscriber->webhook_secret;

        // Stripe-ın signature formatı ilə uyğun
        $signedPayload = "{$timestamp}." . json_encode($payload);
        $signature     = 'sha256=' . hash_hmac('sha256', $signedPayload, $secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type'           => 'application/json',
                    'User-Agent'             => 'PaymentPlatform-Webhook/1.0',
                    'X-Webhook-Id'           => $event->id,
                    'X-Webhook-Timestamp'    => (string)$timestamp,
                    'X-Webhook-Signature'    => $signature,
                    'X-Webhook-Event'        => $event->type,
                    'X-Webhook-Api-Version'  => '2024-01',
                ])
                ->post($subscriber->webhook_url, $payload);

            WebhookDelivery::create([
                'event_id'      => $event->id,
                'subscriber_id' => $subscriber->id,
                'status_code'   => $response->status(),
                'success'       => $response->successful(),
                'duration_ms'   => (int)($response->transferStats?->getTransferTime() * 1000),
                'delivered_at'  => now(),
            ]);

            if (!$response->successful()) {
                throw new WebhookDeliveryException(
                    "HTTP {$response->status()} from {$subscriber->webhook_url}"
                );
            }

        } catch (\Exception $e) {
            WebhookDelivery::create([
                'event_id'      => $event->id,
                'subscriber_id' => $subscriber->id,
                'success'       => false,
                'error_message' => $e->getMessage(),
            ]);
            throw $e; // Queue-da retry üçün
        }
    }
}
```

```php
// Queue job — exponential backoff retry
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;

    public function backoff(): array
    {
        // 1min, 5min, 30min, 2h, 24h
        return [60, 300, 1800, 7200, 86400];
    }

    public function __construct(
        private readonly WebhookEvent $event,
        private readonly Subscriber   $subscriber
    ) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        // Circuit breaker yoxlaması
        if ($this->subscriber->isCircuitOpen()) {
            $this->release(3600); // 1 saat sonra yenidən cəhd
            return;
        }

        $dispatcher->dispatch($this->event, $this->subscriber);
    }

    public function failed(\Throwable $e): void
    {
        // Bütün retry-lar bitdi
        $this->subscriber->increment('consecutive_failures');

        if ($this->subscriber->consecutive_failures >= 5) {
            $this->subscriber->update(['circuit_open_until' => now()->addHour()]);
        }

        // Merchant-ə bildiriş
        NotifyMerchantOfWebhookFailure::dispatch($this->subscriber, $this->event);
    }
}
```

```php
// Webhook qəbul edən — consumer tərəf
class WebhookController extends Controller
{
    public function handlePaymentWebhook(Request $request): JsonResponse
    {
        // 1. Signature yoxla — MÜTLƏQ ilk addım
        $this->verifySignature($request);

        $eventId = $request->header('X-Webhook-Id');

        // 2. Idempotency check — DB-də UNIQUE constraint
        if (ProcessedWebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        // 3. Dərhal queue-ya göndər, 200 OK qaytar
        ProcessPaymentWebhook::dispatch(
            $request->all(),
            $eventId,
            $request->header('X-Webhook-Api-Version', '2024-01')
        );

        return response()->json(['status' => 'accepted'], 200);
    }

    private function verifySignature(Request $request): void
    {
        $timestamp = $request->header('X-Webhook-Timestamp');
        $signature = $request->header('X-Webhook-Signature');

        if (!$timestamp || !$signature) {
            abort(401, 'Missing signature headers');
        }

        // Replay attack: 5 dəqiqədən köhnə event rədd et
        if (abs(time() - (int)$timestamp) > 300) {
            abort(400, 'Webhook timestamp is too old (possible replay attack)');
        }

        $secret        = config('services.payment_platform.webhook_secret');
        $signedPayload = "{$timestamp}." . $request->getContent();
        $expected      = 'sha256=' . hash_hmac('sha256', $signedPayload, $secret);

        // Constant-time comparison — timing attack önləmək üçün
        if (!hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature');
        }
    }
}
```

```php
// Background job — idempotent processing
class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly array  $payload,
        private readonly string $eventId,
        private readonly string $apiVersion
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // Idempotent insert — duplicate KEY = already processed, ignore
            $inserted = ProcessedWebhookEvent::insertOrIgnore([
                'event_id'   => $this->eventId,
                'event_type' => $this->payload['type'],
                'processed_at' => now(),
            ]);

            if (!$inserted) {
                return; // Artıq işlənib
            }

            // API version-a görə handler seçmək
            $handler = match($this->apiVersion) {
                '2024-01' => new PaymentWebhookHandler2024(),
                default   => new PaymentWebhookHandlerLegacy(),
            };

            // Event tipinə görə dispatch
            match ($this->payload['type']) {
                'payment.succeeded' => $handler->handleSucceeded($this->payload),
                'payment.failed'    => $handler->handleFailed($this->payload),
                'payment.refunded'  => $handler->handleRefunded($this->payload),
                default => Log::info('Unknown webhook event type', [
                    'type'     => $this->payload['type'],
                    'event_id' => $this->eventId,
                ]),
            };
        });
    }
}
```

```
Fan-out Arxitekturası (1M+ subscriber):

Payment Service
      |
      ↓
Kafka Topic: payment.events
(partition key = payment_id, ordering zəmanəti)
      |
      ↓
Webhook Fan-out Service
(consumer group — bir event, çox subscriber)
      |
      ├── Per-subscriber SQS Queue (merchant_123)
      ├── Per-subscriber SQS Queue (merchant_456)
      └── Per-subscriber SQS Queue (merchant_789)
            |
            ↓
      Worker Pool (auto-scaling)
      - Signature generation
      - HTTP POST (15s timeout)
      - Retry with exponential backoff
      - Circuit breaker per merchant
      - Delivery log
```

## Praktik Tapşırıqlar

1. Stripe webhook verification kodunu Laravel-də implement edin (HMAC-SHA256, timestamp validation)
2. Retry with exponential backoff queue job yazın — `backoff()` array metodu ilə
3. Webhook delivery dashboard qurun: subscriber-ə göndərilən hər event-in statusu, attempt history
4. Idempotency test edin: eyni event-i iki dəfə göndərərək duplicate processing olmadığını confirm edin
5. Fan-out simulation: 100 subscriber üçün eyni event-i parallel dispatch edin, timing ölçün
6. Thin vs Fat webhook-u müqayisə edin: fan-out latency vs API call overhead — real numbers ilə
7. Circuit breaker implement edin: 3 ardıcıl failure → 30 dəqiqə deaktiv → probe → activate
8. ngrok ilə local webhook endpoint-i test edin: Stripe CLI ilə real webhook-ları local-a route edin

## Əlaqəli Mövzular

- [REST vs GraphQL vs gRPC](05-rest-graphql-grpc.md) — Webhook REST-in event-driven push variantı
- [OAuth 2.0 and JWT](11-oauth-jwt.md) — Webhook HMAC signature vs OAuth Bearer token
- [Long Polling vs SSE vs WebSocket](07-polling-sse-websocket.md) — Polling vs Webhook comparison, trade-off-lar
- [API Versioning](08-api-versioning.md) — Webhook payload versioning, breaking change idarəsi
- [DDoS Protection](15-ddos-protection.md) — Webhook endpoint-ə abuse/DDoS qoruması
