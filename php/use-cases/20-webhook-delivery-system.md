# Webhook Delivery System

## Ssenari

E-commerce platforması merchant-lərə order events haqqında webhook göndərməlidir. Sistem reliable, retry-lı, HMAC-imzalı olmalı, DLQ-ya sahib olmalıdır.

---

## Tələblər

```
Funksional:
  ✅ Order events (created, paid, shipped, cancelled) → webhook
  ✅ Merchant-lər öz endpoint-lərini qeydiyyat etdirir
  ✅ HMAC-SHA256 signature (authenticity)
  ✅ Retry (exponential backoff)
  ✅ Dead Letter Queue (neçə uğursuzluqdan sonra)
  ✅ Idempotent delivery

Non-funksional:
  ✅ At-least-once delivery
  ✅ Delivery timeout: 30s
  ✅ Log every attempt
  ✅ Dashboard: delivery status
```

---

## Arxitektura

```
Order Service                                         Merchant
     │                                                    │
  [OrderPaid]                                             │
     │                                                    │
     ▼                                                    │
┌──────────────┐    ┌────────────────┐    ┌──────────────▼──┐
│   Webhook    │    │  Webhook Queue │    │  Merchant Endpt │
│  Dispatcher  │───►│  (Redis/SQS)  │───►│  https://...    │
└──────────────┘    └────────────────┘    └─────────────────┘
                           │
                    ┌──────▼──────┐
                    │    DLQ      │ ← max retry keçdikdə
                    │ (dead msgs) │
                    └─────────────┘
```

---

## DB Schema

*Bu kod webhook endpoint-ləri, delivery cəhdləri və attempt log-larını saxlayan verilənlər bazası strukturunu yaradır:*

```sql
CREATE TABLE webhook_endpoints (
    id           CHAR(36) PRIMARY KEY,
    merchant_id  INT NOT NULL,
    url          VARCHAR(500) NOT NULL,
    secret       VARCHAR(100) NOT NULL,  -- HMAC secret
    events       JSON NOT NULL,           -- ['order.paid', 'order.shipped']
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merchant (merchant_id),
    INDEX idx_active (is_active)
);

CREATE TABLE webhook_deliveries (
    id              CHAR(36) PRIMARY KEY,
    endpoint_id     CHAR(36) NOT NULL,
    event_type      VARCHAR(100) NOT NULL,
    payload         JSON NOT NULL,
    idempotency_key VARCHAR(100) UNIQUE,
    status          ENUM('pending','delivering','success','failed','dead') DEFAULT 'pending',
    attempts        INT DEFAULT 0,
    max_attempts    INT DEFAULT 5,
    next_attempt_at TIMESTAMP NULL,
    last_attempt_at TIMESTAMP NULL,
    last_error      TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_next (status, next_attempt_at),
    INDEX idx_endpoint (endpoint_id)
);

CREATE TABLE webhook_attempt_logs (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    delivery_id     CHAR(36) NOT NULL,
    attempt_number  INT NOT NULL,
    http_status     INT NULL,
    response_body   TEXT NULL,
    duration_ms     INT NULL,
    error           TEXT NULL,
    attempted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_delivery (delivery_id)
);
```

---

## İmplementasiya

*Bu kod webhook endpoint modelini, HMAC imzalamasını, dispatcher-i və exponential backoff-lu delivery job-unu göstərir:*

```php
// Webhook Endpoint qeydiyyatı
class WebhookEndpoint extends Model
{
    protected $casts = [
        'events' => 'array',
    ];
    
    public static function create(
        int $merchantId,
        string $url,
        array $events
    ): self {
        return static::query()->create([
            'id'          => Str::uuid(),
            'merchant_id' => $merchantId,
            'url'         => $url,
            'secret'      => Str::random(32),
            'events'      => $events,
        ]);
    }
    
    public function isSubscribedTo(string $eventType): bool
    {
        return in_array($eventType, $this->events) || in_array('*', $this->events);
    }
}

// HMAC Signature
class WebhookSigner
{
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
    
    public function verify(string $payload, string $signature, string $secret): bool
    {
        $expected = $this->sign($payload, $secret);
        return hash_equals($expected, $signature);
    }
    
    public function buildHeaders(string $payload, string $secret, string $deliveryId): array
    {
        $timestamp = time();
        $signature = $this->sign("$timestamp.$payload", $secret);
        
        return [
            'X-Webhook-Signature'  => "sha256=$signature",
            'X-Webhook-Timestamp'  => (string) $timestamp,
            'X-Webhook-Delivery'   => $deliveryId,
            'Content-Type'         => 'application/json',
        ];
    }
}

// Event dispatcher
class WebhookDispatcher
{
    public function __construct(
        private WebhookSigner $signer
    ) {}
    
    public function dispatch(string $eventType, array $payload): void
    {
        // Bu event-ə subscribe olan bütün endpoint-ləri tap
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->get()
            ->filter(fn($ep) => $ep->isSubscribedTo($eventType));
        
        foreach ($endpoints as $endpoint) {
            $idempotencyKey = "$eventType:{$payload['id']}:{$endpoint->id}";
            
            // Idempotency: artıq göndərilibmi?
            if (WebhookDelivery::where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }
            
            $delivery = WebhookDelivery::create([
                'id'              => Str::uuid(),
                'endpoint_id'     => $endpoint->id,
                'event_type'      => $eventType,
                'payload'         => json_encode($payload),
                'idempotency_key' => $idempotencyKey,
                'status'          => 'pending',
                'next_attempt_at' => now(),
            ]);
            
            // Queue-a at
            SendWebhookJob::dispatch($delivery->id);
        }
    }
}

// Delivery Job
class SendWebhookJob implements ShouldQueue
{
    public int $tries   = 1;  // Retry logic özümüz idarə edirik
    public int $timeout = 35; // 30s HTTP timeout + 5s overhead
    
    public function __construct(private string $deliveryId) {}
    
    public function handle(WebhookSigner $signer): void
    {
        $delivery = WebhookDelivery::with('endpoint')->findOrFail($this->deliveryId);
        $endpoint = $delivery->endpoint;
        
        if ($delivery->status === 'success') {
            return;  // Artıq uğurlu
        }
        
        $delivery->update([
            'status'          => 'delivering',
            'attempts'        => $delivery->attempts + 1,
            'last_attempt_at' => now(),
        ]);
        
        $startTime = microtime(true);
        $httpStatus = null;
        $responseBody = null;
        $error = null;
        
        try {
            $headers = $signer->buildHeaders(
                $delivery->payload,
                $endpoint->secret,
                $delivery->id
            );
            
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->retry(0)  // Retry biz idarə edirik
                ->post($endpoint->url, json_decode($delivery->payload, true));
            
            $httpStatus   = $response->status();
            $responseBody = substr($response->body(), 0, 1000);
            
            if ($response->successful()) {
                $delivery->update(['status' => 'success']);
            } else {
                throw new \RuntimeException("HTTP {$httpStatus}: $responseBody");
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->handleFailure($delivery, $e);
        } finally {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            WebhookAttemptLog::create([
                'delivery_id'    => $delivery->id,
                'attempt_number' => $delivery->attempts,
                'http_status'    => $httpStatus,
                'response_body'  => $responseBody,
                'duration_ms'    => $durationMs,
                'error'          => $error,
            ]);
        }
    }
    
    private function handleFailure(WebhookDelivery $delivery, \Exception $e): void
    {
        if ($delivery->attempts >= $delivery->max_attempts) {
            // DLQ — artıq daha çox retry etmə
            $delivery->update([
                'status'     => 'dead',
                'last_error' => $e->getMessage(),
            ]);
            
            Log::warning('Webhook dead', [
                'delivery_id' => $delivery->id,
                'attempts'    => $delivery->attempts,
                'error'       => $e->getMessage(),
            ]);
            
            // Alert: merchant-ə notify et, ops-a alert
            event(new WebhookDeliveryFailed($delivery->id));
            return;
        }
        
        // Exponential backoff: 1m, 5m, 30m, 2h, 24h
        $delays = [60, 300, 1800, 7200, 86400];
        $nextDelay = $delays[$delivery->attempts - 1] ?? 86400;
        
        $delivery->update([
            'status'          => 'pending',
            'last_error'      => $e->getMessage(),
            'next_attempt_at' => now()->addSeconds($nextDelay),
        ]);
        
        // Növbəti cəhdi schedule et
        SendWebhookJob::dispatch($delivery->id)
            ->delay(now()->addSeconds($nextDelay));
    }
}
```

---

## Merchant Tərəfindən Signature Verification

*Bu kod merchant tərəfindən HMAC imzasını, timestamp-i və idempotency-ni yoxlayan webhook receiver-i göstərir:*

```php
// Merchant-in endpoint-inin verification kodu (PHP nümunəsi)
class WebhookReceiver
{
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $deliveryId = $request->header('X-Webhook-Delivery');
        
        // Timestamp-i yoxla (replay attack-ı önlə)
        if (abs(time() - (int) $timestamp) > 300) {  // 5 dəqiqə
            return response()->json(['error' => 'Expired'], 400);
        }
        
        // Signature yoxla
        $payload = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', "$timestamp.$payload", config('webhook.secret'));
        
        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        // Idempotency: artıq emal edilib?
        if (ProcessedWebhook::where('delivery_id', $deliveryId)->exists()) {
            return response()->json(['status' => 'already processed']);
        }
        
        // Emal et
        $event = json_decode($payload, true);
        ProcessedWebhook::create(['delivery_id' => $deliveryId]);
        
        dispatch(new ProcessWebhookEvent($event));
        
        return response()->json(['status' => 'accepted']);
    }
}
```

---

## DLQ Monitoring və Replay

*Bu kod uğursuz webhook-ları sıralamaq, tək-tək və ya toplu şəkildə yenidən göndərməyə hazırlamaq üçün DLQ controller-ını göstərir:*

```php
// DLQ görüntüsü
class DeadWebhookController extends Controller
{
    public function index(): JsonResponse
    {
        $dead = WebhookDelivery::where('status', 'dead')
            ->with('endpoint')
            ->latest()
            ->paginate(50);
        
        return response()->json($dead);
    }
    
    public function replay(string $deliveryId): JsonResponse
    {
        $delivery = WebhookDelivery::where('status', 'dead')->findOrFail($deliveryId);
        
        $delivery->update([
            'status'          => 'pending',
            'attempts'        => 0,
            'next_attempt_at' => now(),
        ]);
        
        SendWebhookJob::dispatch($deliveryId);
        
        return response()->json(['status' => 'queued for retry']);
    }
    
    public function replayAll(Request $request): JsonResponse
    {
        $count = WebhookDelivery::where('status', 'dead')
            ->when($request->endpoint_id, fn($q, $id) => $q->where('endpoint_id', $id))
            ->each(function ($delivery) {
                $delivery->update(['status' => 'pending', 'attempts' => 0]);
                SendWebhookJob::dispatch($delivery->id);
            });
        
        return response()->json(['status' => 'queued']);
    }
}
```

---

## İntervyu Sualları

**S: Webhook delivery-ni reliable etmək üçün nə lazımdır?**
C: Persistent queue (Redis/SQS), at-least-once delivery semantikası, exponential backoff ilə retry (1m→5m→30m→2h→24h), max attempts + DLQ, hər cəhd üçün attempt log, idempotency key (eyni event iki dəfə göndərilməsin), HMAC signature (authenticity).

**S: Problem niyə yaranır — sinxron webhook göndərmək niyə pisdir?**
C: Merchant-in endpoint-i yavaş cavab verirsə (10-30 saniyə) ya da məmur deyilsə, bizim HTTP request-imiz bloklanır. Bu müddətdə PHP-FPM prosesi tutulur, yeni request-lər gözləyir. Bir neçə yavaş merchant bir neçə saniyəyə bütün worker pool-unu tükəndirə bilər. Həll: webhook delivery-ni həmişə queue-ya at, HTTP request-dən ayır.

**S: HMAC signature nədir, niyə lazımdır?**
C: Shared secret ilə payload imzalanır: `HMAC-SHA256(timestamp + "." + payload, secret)`. Merchant signature-ı verify edərək request-in həqiqətən bizdən gəldiyini (authenticity) təsdiq edir. Timestamp əlavəsi replay attack-ı önləyir — 5 dəqiqədən köhnə timestamp-ı rədd et. `hash_equals()` istifadə et, `===` deyil (timing attack önlər).

**S: Idempotency webhook-da necə işləyir?**
C: Sender tərəfindən: hər delivery üçün unique idempotency key (`event_type:resource_id:endpoint_id`) — eyni event iki dəfə dispatch olarsa, delivery artıq mövcuddur, skip et. Receiver (merchant) tərəfindən: `delivery_id` ilə processed webhook cədvəlini yoxla — eyni delivery_id görünsə "already processed" qaytar, əməliyyatı təkrarlama.

**S: DLQ (Dead Letter Queue) nədir?**
C: Max retry keçdikdən sonra mesaj DLQ-ya köçürülür (statusu `dead` olur). Avtomatik işlənmir — developer/ops müdaxiləsi tələb olunur. Debug: endpoint-in cavabları loglanıb, nə baş verdiyini görmək mümkündür. Replay: düzəldikdən sonra delivery-ni yenidən `pending`-ə qaytar, attempts-i sıfırla, job dispatch et.

**S: Daim uğursuz olan endpoint-ə qarşı circuit breaker necə işlər?**
C: Son N delivery-in hamısı fail olubsa (məs. son 10-dan 8-i), endpoint-i müvəqqəti deaktiv et (`is_active = false`). Dövri yoxlama (hər saat) ilə test ping göndər — cavab verərsə yenidən aktivləşdir. Bu yanaşma uğursuz endpoint-ə resurs israfını önləyir. Merchant-ə bildiriş göndər ki, endpoint problemi var.

**S: Webhook delivery-nin performansını necə artırmaq olar?**
C: Dedicated queue workers webhook-lar üçün (digər job-lardan ayrı). Concurrency: hər endpoint üçün ayrı queue (bir endpoint-in yavaşlığı başqasını bloklamasın). Timeout-u aşağı tut (10s maksimum). Batch endpoint lookup — çox endpoint-i bir DB sorğusunda al. Connection pool: Laravel HTTP client-in underlying Guzzle-ı keep-alive istifadə edir.

**S: At-least-once vs exactly-once delivery fərqi nədir?**
C: At-least-once: mesaj ən azından bir dəfə çatdırılır (retry var, amma duplikat mümkündür). Exactly-once: həm at-least-once, həm de-duplication (duplikat olmaz) — iki fazalı commit tələb edir, çox bahalıdır. Praktikada: at-least-once + receiver-tərəfli idempotency (delivery_id yoxlaması) = effectively exactly-once davranış.

---

## Anti-patternlər

**1. Webhook-u sinxron HTTP request-lə göndərmək**
Hər webhook-u birbaşa request lifecycle-ında göndərmək — merchant endpoint-i yavaş və ya çökmüş olarsa bizim API-miz bloklanır, timeout yaranır. Webhook delivery-ni həmişə queue ilə background-a at.

**2. Retry olmadan tək cəhd etmək**
Uğursuz delivery-ni yenidən cəhd etməmək — keçici şəbəkə xətaları, merchant server restart-ları, 502 cavabları normal haldır. Exponential backoff ilə (1s, 2s, 4s, ...) ən azı 5-10 retry konfiqurasiya et.

**3. Signature doğrulaması tətbiq etməməmək**
Webhook payload-ını HMAC imzası olmadan göndərmək — hər kəs eyni URL-ə saxta payload göndərə bilər, merchant təhlükəsiz data qəbul etdiyini düşünür. Shared secret ilə `X-Signature` header əlavə et, merchant tərəfindən verify edilsin.

**4. Uğursuz endpoint-i sonsuz retry etmək**
Daim uğursuz olan endpoint-ə max retry limit qoymamaq — queue dolar, resurslar israf olur, digər webhook-lar gecikir. Max attempt limitini keçən delivery-ləri DLQ-ya köçür, endpoint-i müvəqqəti deaktiv et.

**5. Delivery cəhdlərini loglamamamaq**
Hansı webhook-un nə vaxt, hansı cavabla göndərildiyini qeyd etməmək — debug zamanı merchant "event almadım" deyir, sən isə nə baş verdiyini bilmirsən. Hər cəhd üçün status code, response body, timestamp-i saxla.

**6. Eyni event-i deduplication olmadan bir neçə dəfə dispatch etmək**
Idempotency yoxlaması olmadan retry mexanizmi qurmaq — at-least-once delivery ilə eyni event merchant-ə çoxlu dəfə çata bilər, merchant tərəfindəki əməliyyatlar duplikat ola bilər. Hər delivery üçün unikal `idempotency_key` istifadə et.
