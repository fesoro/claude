# Webhook Dizayn Patterns (Middle)

## Mündəricat
1. [Webhook vs Polling](#webhook-vs-polling)
2. [Delivery Zəmanətləri](#delivery-zəmanətləri)
3. [Retry with Exponential Backoff](#retry-with-exponential-backoff)
4. [Signature Verification (HMAC)](#signature-verification-hmac)
5. [Fan-out Pattern](#fan-out-pattern)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Webhook vs Polling

```
Polling (Sorğulama):
  Client hər N saniyədə "yenilik var?" deyə soruşur.

  ┌────────┐ ──GET /events?since=T──► ┌────────┐
  │ Client │ ◄─── [] (boş) ─────────  │ Server │
  │        │ ──GET /events?since=T──► │        │
  │        │ ◄─── [] (boş) ─────────  │        │
  │        │ ──GET /events?since=T──► │        │
  │        │ ◄─── [event!] ──────────  │        │
  └────────┘                          └────────┘

  ✓ Sadə, firewall friendly
  ✗ Gecikmiş bildirim (polling interval qədər)
  ✗ Boş sorğularla server yüklənir
  ✗ N client varsa → N * polling_rate sorğu

Webhook (Push):
  Server event baş verdikdə client-ə bildirir.

  ┌────────┐                           ┌────────┐
  │ Client │◄── POST /webhook ──────── │ Server │
  │        │    {event: "payment.paid"}│        │
  └────────┘                           └────────┘

  ✓ Real-time (event anında)
  ✓ Server kaynağı qənaət edilir
  ✗ Client-in public URL lazımdır
  ✗ Delivery zəmanəti problemi
  ✗ Client offline olsa event itir
```

---

## Delivery Zəmanətləri

```
Webhook at-least-once delivery:
  Server webhook göndərir.
  200 OK almasa → retry edir.
  Client 200 OK göndərsə belə şəbəkə xətası olub server görmürsə → retry!
  → Eyni webhook 2 dəfə gələ bilər.

Client idempotent olmalıdır!
  event_id saxla → eyni event_id gəlsə skip et.

┌──────────┐  POST /webhook          ┌──────────┐
│  Server  │ ─────────────────────►  │  Client  │
│          │                         │  (işləndi)│
│          │ ◄──── 200 OK ─────────  │          │
│          │     (şəbəkə xətası)     │          │
│ timeout! │                         │          │
│ retry!   │  POST /webhook (again)  │          │
│          │ ─────────────────────►  │          │
│          │                         │ event_id  │
│          │                         │ dublikat! │
│          │ ◄──── 200 OK ─────────  │ skip et  │
└──────────┘                         └──────────┘
```

---

## Retry with Exponential Backoff

```
Sabit interval:                  Exponential backoff:
  1. cəhd → 1s gözlə            1. cəhd → 1s gözlə
  2. cəhd → 1s gözlə            2. cəhd → 2s gözlə
  3. cəhd → 1s gözlə            3. cəhd → 4s gözlə
  ...                            4. cəhd → 8s gözlə
  Yenilən server üzərinə         5. cəhd → 16s gözlə
  eyni load!                     → Server-ə "nəfəs" verir

Jitter (random) əlavəsi:
  4s + random(0, 2) → 4-6s arası
  Thundering herd: hamı eyni anda retry etməsin!

Stripe webhook retry sxemi:
  1h, 2h, 4h, 8h, 24h, 3d, 5d, 7d, ...
  72 saat ərzində çatdırılmazsa → dead

Max retry sonrası:
  1. Dead letter queue-ya at
  2. Admin-ə xəbər ver
  3. Manual replay imkanı
```

---

## Signature Verification (HMAC)

```
Problem: İstənilən kəs webhook endpoint-inə POST edə bilər.
Həll: HMAC imzası.

Server (göndərən):
  1. Secret key paylaşılır (bir dəfə, setup zamanı)
  2. Hər webhook-da: HMAC-SHA256(secret, body) hesabla
  3. Header-ə əlavə et: X-Signature: sha256={hash}

Client (alan):
  1. Header-dən signature oxu
  2. Öz secret-i ilə body-ni hash-lə
  3. Müqayisə et — uyğunsuzluq varsa 401 qaytar

┌──────────┐  POST /webhook              ┌──────────┐
│  Server  │  X-Signature: sha256=abc123 │  Client  │
│          │ ─────────────────────────►  │          │
│          │  body: {"event": "paid"}    │ 1. hash hesabla│
│          │                             │ 2. abc123 ilə  │
│          │                             │    müqayisə    │
│          │ ◄──── 200 OK ─────────────  │ 3. ✅ match    │
└──────────┘                             └──────────┘

Timestamp replay attack qarşısı:
  X-Timestamp: 1698765432
  Yoxla: |now - timestamp| < 5 dəqiqə
  → Köhnə webhook-u "replay" etmək olmaz
```

---

## Fan-out Pattern

```
Bir event → çox subscriber

Event: payment.completed
  ├── EmailService.sendReceipt()
  ├── InventoryService.decreaseStock()
  ├── AnalyticsService.trackConversion()
  └── LoyaltyService.addPoints()

Düzgün fan-out (queue vasitəsilə):
  Event → Message Queue
    → Consumer 1 (email)
    → Consumer 2 (inventory)
    → Consumer 3 (analytics)
    → Consumer 4 (loyalty)

Bir consumer xəta versə digərlərinə təsir yoxdur!

Subscriber registry:
  DB-də webhook subscription-lar saxlanır.
  Event baş verəndə bütün subscriber-lara göndərilir.
  Hər göndəriş ayrıca retry/log saxlanır.
```

---

## PHP İmplementasiyası

```php
<?php
// Webhook Sender — signature ilə
class WebhookSender
{
    public function __construct(
        private HttpClientInterface $http,
        private WebhookRepository $webhookRepo,
    ) {}

    public function send(string $event, array $payload): void
    {
        $subscribers = $this->webhookRepo->getSubscribersForEvent($event);

        foreach ($subscribers as $subscriber) {
            $this->dispatch($subscriber, $event, $payload);
        }
    }

    private function dispatch(
        WebhookSubscriber $subscriber,
        string $event,
        array $payload
    ): void {
        $body      = json_encode(['event' => $event, 'data' => $payload]);
        $timestamp = time();
        $signature = $this->sign($body, $subscriber->getSecret(), $timestamp);

        $delivery = new WebhookDelivery($subscriber->getId(), $event);
        $this->webhookRepo->saveDelivery($delivery);

        try {
            $response = $this->http->post($subscriber->getUrl(), [
                'body'    => $body,
                'timeout' => 10,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'X-Timestamp'   => $timestamp,
                    'X-Signature'   => 'sha256=' . $signature,
                    'X-Event-ID'    => $delivery->getId(),
                ],
            ]);

            $delivery->markDelivered($response->getStatusCode());
        } catch (\Throwable $e) {
            $delivery->markFailed($e->getMessage());
            $this->scheduleRetry($delivery);
        }

        $this->webhookRepo->updateDelivery($delivery);
    }

    private function sign(string $body, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    private function scheduleRetry(WebhookDelivery $delivery): void
    {
        $attempt  = $delivery->getAttempt();
        $delaySeconds = min(pow(2, $attempt) + random_int(0, 10), 86400);
        // 2^1=2, 2^2=4, ..., max 24 saat

        dispatch(new RetryWebhookJob($delivery->getId()))
            ->delay(now()->addSeconds($delaySeconds));
    }
}
```

```php
<?php
// Webhook Receiver — signature verify + idempotency
class WebhookController
{
    public function handle(Request $request): Response
    {
        // 1. Signature verify
        if (!$this->verifySignature($request)) {
            return new Response('Invalid signature', 401);
        }

        // 2. Timestamp replay attack check
        $timestamp = (int) $request->header('X-Timestamp');
        if (abs(time() - $timestamp) > 300) { // 5 dəqiqə
            return new Response('Stale request', 400);
        }

        // 3. Idempotency check
        $eventId = $request->header('X-Event-ID');
        if ($this->events->wasProcessed($eventId)) {
            return new Response('Already processed', 200); // 200 qaytarmalıyıq!
        }

        // 4. Process
        $payload = $request->json();
        $this->processEvent($payload['event'], $payload['data']);
        $this->events->markProcessed($eventId);

        return new Response('OK', 200);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $body      = $request->getContent();
        $secret    = config('webhooks.secret');

        $expected  = 'sha256=' . hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return hash_equals($expected, $signature ?? '');
    }
}
```

---

## İntervyu Sualları

- Webhook at-least-once delivery problemi nədir? Necə həll edərsiniz?
- HMAC signature niyə lazımdır? SSL/TLS yetərli deyilmi?
- Timestamp replay attack-ı nədir? Webhook-da necə önlənir?
- Webhook receiver 500 qaytarsa server nə etməlidir?
- Fan-out pattern-də bir subscriber yavaş işləyirsə digərlərini bloklamasın — necə?
- Webhook delivery-ni debug etmək üçün nə izləmək lazımdır?
- Webhook-un polling-dən üstün olmadığı ssenarilər hansılardır?
