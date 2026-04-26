# System Design: Webhook Delivery System (Architect)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Webhook endpoint qeydiyyat (URL, secret, events)
  Event baş verdikdə subscriber-lərə HTTP POST
  Retry: çatdırılmayan webhook-ları yenidən cəhd et
  Dashboard: çatdırılma tarixi, uğursuzluqlar
  Signature: HMAC imzası ilə autentifikasiya

Qeyri-funksional:
  At-least-once delivery
  Subscriber timeout-ı sistem-i bloklamasın
  Yüksək throughput: 1M webhook/saat
  Retry max 3 gün

Nümunə istifadəçilər:
  Stripe: ödəniş hadisələri
  GitHub: push, PR events
  Shopify: order, inventory events
```

---

## Yüksək Səviyyəli Dizayn

```
Event Axını:
  Application event baş verir → Webhook Service
  Webhook Service:
    1. Hansı subscriber bu event-ə subscribe?
    2. Hər subscriber üçün delivery job yarat
    3. Queue-ya göndər
  
  Delivery Workers:
    1. Job götür
    2. HTTP POST subscriber URL-inə
    3. 2xx → success
    4. 5xx/timeout → retry

┌──────────┐  event  ┌──────────────┐  job  ┌──────────┐
│   App    │────────►│Webhook Service│──────►│  Queue   │
└──────────┘         └──────────────┘       └────┬─────┘
                                                  │
                                      ┌───────────▼────────────┐
                                      │   Delivery Workers     │
                                      │   (horizontal scale)   │
                                      └───────────┬────────────┘
                                                  │ HTTP POST
                                      ┌───────────▼────────────┐
                                      │   Subscriber Endpoints │
                                      └────────────────────────┘
                                      
DB:
  webhooks:  id, user_id, url, secret, events[], active
  deliveries: id, webhook_id, event_type, payload, status,
              attempt_count, next_retry, last_error
```

---

## Komponent Dizaynı

```
Retry Strategy:
  Exponential backoff with jitter:
  Attempt 1: 30s
  Attempt 2: 5m
  Attempt 3: 1h
  Attempt 4: 5h
  Attempt 5: 10h
  Max 3 gün, sonra failed
  
  Jitter: ±10% random → retry storm önlənir

Signature (HMAC):
  secret = subscriber-in gizli açarı
  payload = JSON body
  signature = HMAC-SHA256(secret, payload)
  Header: X-Webhook-Signature: sha256=abc123...
  
  Subscriber yoxlayır:
  expected = HMAC-SHA256(my_secret, request_body)
  received = request.headers['X-Webhook-Signature']
  hash_equals(expected, received) → etibarlı

Timeout:
  Subscriber 30 saniyə cavab vermirsə → timeout
  Yeni connection-lar üçün timeout: 5 saniyə
  Parallel delivery-lər bir-birini bloklamasın

Backpressure:
  Subscriber daim fail edir → circuit breaker
  50 consecutive fail → subscriber deactivate
  Admin alert + email

Idempotency:
  Hər delivery-nin unique ID-si var
  Subscriber eyni ID-ni iki dəfə alsada idempotent emal etməlidir
  X-Webhook-ID header ilə göndər
```

---

## PHP İmplementasiyası

```php
<?php
// Webhook Event Publisher
namespace App\Webhook;

class WebhookEventPublisher
{
    public function __construct(
        private WebhookRepository $webhooks,
        private DeliveryQueue     $queue,
    ) {}

    public function publish(string $eventType, array $payload): void
    {
        // Bu event-ə subscribe olan active webhook-ları tap
        $subscribers = $this->webhooks->findActiveByEvent($eventType);

        foreach ($subscribers as $webhook) {
            $deliveryId = bin2hex(random_bytes(16));

            // Delivery record yarat
            $this->webhooks->createDelivery([
                'id'          => $deliveryId,
                'webhook_id'  => $webhook->getId(),
                'event_type'  => $eventType,
                'payload'     => json_encode($payload),
                'status'      => 'pending',
                'next_retry'  => new \DateTimeImmutable(),
                'attempt'     => 0,
            ]);

            // Queue-ya at
            $this->queue->publish(new DeliverWebhookJob($deliveryId));
        }
    }
}
```

```php
<?php
// Webhook Delivery Worker
class WebhookDeliveryWorker
{
    private const RETRY_DELAYS = [30, 300, 3600, 18000, 36000]; // seconds
    private const MAX_ATTEMPTS = 5;
    private const TIMEOUT      = 30; // seconds

    public function __construct(
        private WebhookRepository $webhooks,
        private \GuzzleHttp\Client $http,
    ) {}

    public function deliver(DeliverWebhookJob $job): void
    {
        $delivery = $this->webhooks->findDelivery($job->deliveryId);
        $webhook  = $this->webhooks->findById($delivery->getWebhookId());

        try {
            $payload   = $delivery->getPayload();
            $signature = $this->sign($webhook->getSecret(), $payload);

            $response = $this->http->post($webhook->getUrl(), [
                'headers' => [
                    'Content-Type'           => 'application/json',
                    'X-Webhook-ID'           => $delivery->getId(),
                    'X-Webhook-Event'        => $delivery->getEventType(),
                    'X-Webhook-Signature'    => "sha256={$signature}",
                    'X-Webhook-Timestamp'    => time(),
                ],
                'body'    => $payload,
                'timeout' => self::TIMEOUT,
                'connect_timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                // Uğurlu
                $this->webhooks->markDeliverySuccess(
                    $delivery->getId(),
                    $statusCode,
                    substr((string) $response->getBody(), 0, 1000),
                );
                return;
            }

            // 4xx/5xx → retry
            throw new WebhookDeliveryFailedException("HTTP {$statusCode}");

        } catch (\Throwable $e) {
            $this->handleFailure($delivery, $webhook, $e);
        }
    }

    private function handleFailure(
        Delivery $delivery,
        Webhook  $webhook,
        \Throwable $e,
    ): void {
        $attempt = $delivery->getAttempt() + 1;

        if ($attempt >= self::MAX_ATTEMPTS) {
            // Max cəhd keçdi
            $this->webhooks->markDeliveryFailed($delivery->getId(), $e->getMessage());

            // Çox uğursuzluq → webhook deactivate
            $this->checkAndDeactivateWebhook($webhook);
            return;
        }

        // Retry schedule et
        $delay   = self::RETRY_DELAYS[$attempt - 1] ?? 36000;
        $jitter  = random_int(-$delay / 10, $delay / 10);
        $retryAt = (new \DateTimeImmutable())->modify('+' . ($delay + $jitter) . ' seconds');

        $this->webhooks->scheduleRetry(
            $delivery->getId(),
            $attempt,
            $retryAt,
            $e->getMessage(),
        );
    }

    private function sign(string $secret, string $payload): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    private function checkAndDeactivateWebhook(Webhook $webhook): void
    {
        $recentFailures = $this->webhooks->countRecentFailures($webhook->getId(), hours: 1);

        if ($recentFailures >= 10) {
            $webhook->deactivate();
            $this->webhooks->save($webhook);
            $this->notifyAdmin($webhook, "Çox sayda çatdırılma uğursuzluğu");
        }
    }
}
```

```php
<?php
// Subscriber tərəfindəki imza yoxlama
class WebhookReceiver
{
    public function receive(Request $request): Response
    {
        $body      = (string) $request->getBody();
        $signature = $request->getHeaderLine('X-Webhook-Signature');
        $webhookId = $request->getHeaderLine('X-Webhook-ID');

        // Signature yoxla
        if (!$this->verifySignature($body, $signature)) {
            return new Response(401, [], 'Invalid signature');
        }

        // Idempotency — eyni webhook ikinci dəfə
        if ($this->isAlreadyProcessed($webhookId)) {
            return new Response(200, [], 'Already processed');
        }

        $event = json_decode($body, true);
        $this->processEvent($event);
        $this->markProcessed($webhookId);

        return new Response(200, [], 'OK');
    }

    private function verifySignature(string $body, string $receivedSig): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $body, $this->secret);
        // Constant-time comparison (timing attack önlər)
        return hash_equals($expected, $receivedSig);
    }
}
```

---

## İntervyu Sualları

- At-least-once vs exactly-once webhook delivery fərqi nədir?
- HMAC imzası niyə lazımdır?
- Subscriber endpoint daim timeout verərsə nə etmək lazımdır?
- Retry storm-u exponential backoff + jitter necə önləyir?
- Webhook idempotency subscriber tərəfindən necə tətbiq edilir?
- Dashboard üçün delivery history neçə müddət saxlanılmalıdır?
