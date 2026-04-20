# Webhook Delivery System Design

## Giriş (Introduction)

**Webhook** — server-in müəyyən event baş verəndə üçüncü tərəfin URL-inə HTTP POST göndərərək xəbər verməsidir. Bu "inverted API" modelidir: client server-i soruşmur (polling), server özü client-ə xəbər verir (push).

Məsələn, Stripe-da ödəniş uğurlu olanda sənin server-inə `POST /webhooks/stripe` gəlir — `{type: "payment.succeeded", ...}`. Sən polling etmirsən, Stripe özü bildirir.

Böyük webhook sistemləri: **Stripe**, **GitHub**, **Slack**, **Twilio**, **Shopify**. Hər gün milyardlarla webhook göndərirlər — reliable delivery mühüm məsələdir.

---

## Tələblər (Requirements)

### Funksional (Functional)

- Istifadəçi endpoint qeydiyyatdan keçirə bilməlidir (URL, hansı event-lər, secret)
- Event baş verəndə bütün subscribed endpoint-lərə çatdırılmalıdır (fan-out)
- Receiver cavab verməyəndə retry (təkrar cəhd)
- Signature ilə security (spoofing-dən qorumaq)
- Delivery status UI — hansı webhook getdi, hansı uğursuz oldu

### Qeyri-funksional (Non-functional)

- **Reliable** — 5xx və timeout olanda retry
- **Ordered** (optional) — bəzi hallarda sıra vacibdir
- **Observable** — hər attempt log-lanmalı, owner görə bilməlidir
- **Scalable** — bir event minlərlə endpoint-ə fan-out ola bilər

---

## Arxitektura (Architecture)

```
+----------+      +-----------+      +--------+      +------------+
|  App     |----->| Event bus |----->|Dispatch|----->| Delivery Q |
| (producer)      | (Kafka)   |      | er     |      | (per endpt)|
+----------+      +-----------+      +--------+      +------------+
                                           |               |
                                           v               v
                                     +----------+    +----------+
                                     | Endpoint |    |  Worker  |
                                     | Registry |    | (HTTP)   |
                                     +----------+    +----------+
                                                          |
                                                          v
                                                   +------------+
                                                   | 3rd-party  |
                                                   |  endpoint  |
                                                   +------------+
                                                          |
                                                          v
                                                   +------------+
                                                   |Delivery log|
                                                   | (attempts) |
                                                   +------------+
```

### Komponentlər (Components)

1. **Event Producer** — əsas app, event-lər emit edir (`order.created`, `payment.succeeded`)
2. **Endpoint Registry** — kim hansı URL-də hansı event-lərə subscribe olub
3. **Dispatcher** — event-i oxuyur, subscribed endpoint-ləri tapır, hər biri üçün delivery record yaradır
4. **Delivery Worker** — HTTP POST göndərir, nəticəni log edir, lazımsa retry queue-ya qoyur
5. **Delivery Log** — hər attempt üçün: status, response code, body, duration

---

## Data Model

```sql
-- Event: immutable, producer tərəfindən yazılır
events (
  id UUID PRIMARY KEY,
  type VARCHAR(100),          -- 'order.created'
  payload JSONB,
  user_id BIGINT,             -- tenant
  created_at TIMESTAMP
);

-- Endpoint: receiver-in qeydiyyatı
endpoints (
  id UUID PRIMARY KEY,
  user_id BIGINT,
  url TEXT,
  secret VARCHAR(64),         -- HMAC üçün
  enabled_events TEXT[],      -- ['order.*', 'payment.succeeded']
  state VARCHAR(20),          -- active, paused, disabled
  created_at TIMESTAMP
);

-- Delivery: hər endpoint üçün hər event bir row
deliveries (
  id UUID PRIMARY KEY,
  event_id UUID,
  endpoint_id UUID,
  state VARCHAR(20),          -- pending, success, retrying, failed
  attempts INT DEFAULT 0,
  next_retry_at TIMESTAMP,
  last_status_code INT,
  last_error TEXT,
  created_at TIMESTAMP,
  completed_at TIMESTAMP,
  INDEX (state, next_retry_at)
);

-- Attempt: hər cəhd üçün detal log (retention 30-90 gün)
delivery_attempts (
  id BIGSERIAL PRIMARY KEY,
  delivery_id UUID,
  attempted_at TIMESTAMP,
  status_code INT,
  response_body TEXT,
  duration_ms INT
);
```

---

## Dispatch (Fan-out)

Event gəlir → subscribed endpoint-lər tapılır → hər biri üçün ayrıca delivery row + queue job.

```
event_id=e1, type=order.created
    |
    v  (index: event_type -> endpoint_ids)
[ep1, ep2, ep3]
    |
    v  (fan-out: 1 event → 3 delivery)
delivery(e1, ep1) → queue:webhook:ep1
delivery(e1, ep2) → queue:webhook:ep2
delivery(e1, ep3) → queue:webhook:ep3
```

Bir endpoint-in yavaş olması digər endpoint-lərə təsir etməsin — per-endpoint queue partitioning.

---

## Retry Policy

Receiver cavab verməyəndə və ya 5xx qaytaranda retry olunmalıdır.

### Exponential Backoff

```
Attempt  Delay       Cumulative
1        dərhal       0
2        1 dəq         1m
3        2 dəq         3m
4        4 dəq         7m
5        10 dəq        17m
6        30 dəq        47m
7        1 saat        1h 47m
8        2 saat        3h 47m
9        6 saat        9h 47m
10       12 saat       21h 47m
11       24 saat       45h 47m
12+      (stop)        ~72 saat total
```

### Retry qaydaları (Rules)

- **Retry on**: timeout, connection error, 5xx, 429 (rate limit), 408 (timeout)
- **Don't retry on**: 4xx (bad request — body səhvdir, retry fayda etməz)
- **Max attempts**: 10-20 arası; sonra `failed` state + owner-ə alert
- Hər attempt üçün `next_retry_at` timestamp yazılır; scheduler pending delivery-ləri çəkir

---

## Signatures (Təhlükəsizlik)

Receiver necə bilsin ki, request həqiqətən Stripe-dandır, təsadüfi hücumçu deyil? HMAC-SHA256.

### Header format (Stripe stilində)

```
X-Webhook-Signature: t=1713456789,v1=5257a869e7ecb...
X-Webhook-Event-Id: evt_abc123
```

- `t` — timestamp (replay attack-dan qorumaq üçün)
- `v1` — HMAC-SHA256(secret, `t.body`)

Receiver:
1. `t` yoxlayır — 5 dəqiqədən köhnədirsə reject
2. `HMAC(secret, t + "." + body)` hesablayır, `v1` ilə müqayisə edir (constant-time compare)

---

## Laravel: Sender Tərəf

```php
// app/Events/OrderCreated.php
class OrderCreated { public function __construct(public Order $order) {} }

// app/Listeners/DispatchWebhooks.php
class DispatchWebhooks
{
    public function handle(OrderCreated $event): void
    {
        $endpoints = Endpoint::where('user_id', $event->order->user_id)
            ->where('state', 'active')
            ->whereJsonContains('enabled_events', 'order.created')
            ->get();

        foreach ($endpoints as $endpoint) {
            $delivery = Delivery::create([
                'event_id'    => $event->order->event_id,
                'endpoint_id' => $endpoint->id,
                'state'       => 'pending',
            ]);
            SendWebhookJob::dispatch($delivery->id)
                ->onQueue("webhook:{$endpoint->id}");
        }
    }
}

// app/Jobs/SendWebhookJob.php
class SendWebhookJob implements ShouldQueue
{
    public int $tries = 1;  // retry özümüz idarə edirik

    public function __construct(public string $deliveryId) {}

    public function handle(): void
    {
        $delivery = Delivery::with('event', 'endpoint')->findOrFail($this->deliveryId);
        $endpoint = $delivery->endpoint;

        $body = json_encode([
            'id'   => $delivery->event->id,
            'type' => $delivery->event->type,
            'data' => $delivery->event->payload,
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $endpoint->secret);

        $start = microtime(true);
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'        => 'application/json',
                    'X-Webhook-Signature' => "t={$timestamp},v1={$signature}",
                    'X-Webhook-Event-Id'  => $delivery->event->id,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $duration = (int)((microtime(true) - $start) * 1000);
            $this->recordAttempt($delivery, $response->status(), $response->body(), $duration);

            if ($response->successful()) {
                $delivery->update(['state' => 'success', 'completed_at' => now()]);
                return;
            }

            // 4xx (non-retryable) → fail dərhal
            if ($response->clientError() && !in_array($response->status(), [408, 429])) {
                $delivery->update(['state' => 'failed']);
                return;
            }

            $this->scheduleRetry($delivery);
        } catch (\Throwable $e) {
            $this->recordAttempt($delivery, 0, $e->getMessage(), 0);
            $this->scheduleRetry($delivery);
        }
    }

    private function scheduleRetry(Delivery $delivery): void
    {
        $delays = [60, 120, 240, 600, 1800, 3600, 7200, 21600, 43200, 86400];
        $attempts = $delivery->attempts + 1;

        if ($attempts >= count($delays)) {
            $delivery->update(['state' => 'failed']);
            // alert endpoint owner
            return;
        }

        $delay = $delays[$attempts];
        $delivery->update([
            'state'         => 'retrying',
            'attempts'      => $attempts,
            'next_retry_at' => now()->addSeconds($delay),
        ]);
        SendWebhookJob::dispatch($delivery->id)
            ->delay($delay)
            ->onQueue("webhook:{$delivery->endpoint_id}");
    }
}
```

---

## Laravel: Receiver Tərəf

```php
// app/Http/Middleware/VerifyWebhookSignature.php
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('X-Webhook-Signature', '');
        if (!preg_match('/t=(\d+),v1=([a-f0-9]+)/', $header, $m)) {
            abort(401, 'invalid signature header');
        }
        [$_, $timestamp, $signature] = $m;

        // Replay protection — 5 dəqiqədən köhnə reject
        if (abs(time() - (int)$timestamp) > 300) {
            abort(401, 'stale timestamp');
        }

        $secret = config('services.webhook.secret');
        $expected = hash_hmac('sha256', "{$timestamp}." . $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            abort(401, 'signature mismatch');
        }
        return $next($request);
    }
}

// Controller — idempotency
public function handle(Request $request)
{
    $eventId = $request->header('X-Webhook-Event-Id');

    // Dedup: eyni event yenidən gələndə bir dəfə process et
    if (ProcessedWebhook::where('event_id', $eventId)->exists()) {
        return response('', 200);   // already processed — OK qaytar
    }

    DB::transaction(function () use ($request, $eventId) {
        ProcessedWebhook::create(['event_id' => $eventId]);
        // ... business logic
    });

    return response('', 200);
}
```

---

## Circuit Breaker və Rate Limit

### Circuit Breaker per Endpoint

Endpoint ardıcıl 50 dəfə fail edirsə (məsələn, 10 dəqiqə ərzində), endpoint `paused` state-ə keçir:
- Yeni delivery enqueue olunmur, amma yaddaşda saxlanılır
- Owner-ə alert (email/dashboard)
- Manual resume və ya cooldown sonrası auto-resume

### Rate Limit per Endpoint

Receiver-i burst-dən qorumaq üçün — leaky bucket (məsələn, saniyədə 100 request). Worker endpoint-ə göndərməzdən əvvəl token yoxlayır.

---

## Anti-abuse və SSRF

Webhook URL istifadəçi tərəfindən verilir — hücumçu `http://169.254.169.254/` (AWS metadata) və ya `http://localhost:6379` (Redis) yaza bilər.

### Qoruma (Protection)

- **IP block list**: 127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 169.254.0.0/16, ::1
- **DNS resolve** URL-i özünüz edin, IP-ni yoxlayın, sonra bağlanın (DNS rebinding-ə qarşı)
- **HTTPS only** (optional, amma tövsiyə olunur)
- **URL validation** — yalnız http/https schema, port whitelist

---

## Scale Patterns

1. **Partition by endpoint_id** — hər endpoint öz queue-da, bir yavaş endpoint digərinə təsir etməz
2. **Priority worker pools** — critical event-lər (payment) ayrı pool, batch event-lər ayrı
3. **Dead Letter Topic** — max attempt bitib fail olanlar ayrı topic-də, manual inspection üçün
4. **Sharded dispatcher** — event bus-dan oxuyan dispatcher `event_id % N` ilə paralelləşir

---

## Ordering

Adətən strict ordering TƏLƏB olunmur — receiver event-lərdə `created_at` timestamp-ı ilə idarə etməlidir. Əgər lazımdırsa:
- **Per-entity ordering**: `order_id % N` ilə partition, per partition tək consumer
- Retry zamanı digər event-lər blok olacaq — trade-off

---

## Observability

Metric-lər (per endpoint):
- **Delivery success rate** — success / total
- **p50/p99 latency** — request müddəti
- **5xx rate, timeout rate**
- **Pending deliveries count, retry backlog**

Delivery status UI — owner öz dashboard-unda:
- Son 100 delivery-ni görə bilsin
- State-ə görə filter (success/failed/retrying)
- Manual replay düyməsi

---

## CloudEvents Standartı

**CloudEvents** (CNCF) — event format üçün standart. Payload-da `id`, `type`, `source`, `time`, `specversion` sahələri. Webhook standartlaşdırıldıqca daha çox sistem bunu qəbul edir.

```json
{
  "specversion": "1.0",
  "id": "evt_abc123",
  "type": "com.example.order.created",
  "source": "/orders/service",
  "time": "2026-04-18T10:00:00Z",
  "data": { "order_id": 42, "total": 100 }
}
```

---

## Push vs Pull (Trade-off)

| Aspekt | Push (Webhook) | Pull (Polling) |
|---|---|---|
| Real-time | Bəli | Interval-a bağlı |
| Receiver complexity | HTTP endpoint açmaq | API call etmək |
| Reliability | Sender retry etməlidir | Receiver nəzarət edir |
| Network efficiency | Event olanda traffic | Daim traffic |
| Firewall | Receiver public endpoint | Receiver outbound |

Hibrid: webhook xəbər verir, receiver sonra API-dən full state çəkir.

---

## Interview Q&A

**Q1: Bir event minlərlə endpoint-ə getməlidir. Necə fan-out edəcəksən?**
Dispatcher event-i oxuyur, endpoint registry-dən subscriberləri çəkir (event type ilə indexed), hər biri üçün `deliveries` cədvəlinə row insert edir və per-endpoint queue-ya job atır. Partition by endpoint_id — bir yavaş endpoint digərini blok etmir.

**Q2: Receiver 5 saat down olur. Nə baş verir?**
Exponential backoff ilə retry: 1m, 2m, 4m, 10m, 30m, 1h... Delivery `retrying` state-də qalır, `next_retry_at` planlaşdırılır. Receiver qayıdanda növbəti attempt uğurlu olur. 72 saatdan sonra `failed` state + owner alert.

**Q3: Receiver eyni webhook-u iki dəfə alır. Problem?**
Retry zamanı mümkündür (şəbəkə problemi — receiver 200 qaytardı, amma sender görmədi). Ona görə hər webhook-da `event_id` göndəririk, receiver idempotency table-da bu id-ni yoxlayır — əgər varsa, sadəcə 200 qaytarır.

**Q4: Hücumçu sənin URL-inə saxta webhook göndərə bilər?**
Bəli, ona görə HMAC-SHA256 signature. Sender secret ilə body-ni imzalayır, receiver eyni secret ilə yoxlayır. Timestamp replay-ə qarşı qoruyur (5 dəqiqədən köhnə reject). `hash_equals` ilə constant-time müqayisə timing attack-dan qoruyur.

**Q5: Istifadəçi webhook URL-i `http://localhost:6379` yazsa?**
SSRF təhlükəsi. Redis-ə command göndərə bilər. Qoruma: URL-i özümüz DNS resolve edirik, IP-ni private range-lərə qarşı yoxlayırıq (127/8, 10/8, 172.16/12, 192.168/16, 169.254/16). DNS rebinding-ə qarşı resolve edilmiş IP ilə bağlanırıq.

**Q6: Endpoint daim fail edir. Nə edirsən?**
Circuit breaker: son N attempt-dən X%-i fail olarsa, endpoint `paused` state-ə keçir. Yeni delivery-lər göndərilmir (amma saxlanılır). Owner-ə alert gedir. Manual resume və ya cooldown-dan sonra yavaş-yavaş açılır (half-open state — bir neçə test request).

**Q7: Webhook-da ordering lazımdırmı?**
Əksər hallarda yox — receiver `created_at` ilə idarə etməlidir. Lazım olarsa, per-entity ordering: `order_id` hash ilə partition, hər partition üçün tək consumer, serial emal. Retry zamanı digər event-lər gözləyəcək — latency artır.

**Q8: Push webhook vs Pull polling nə vaxt seçirsən?**
Push — real-time lazım olanda (ödəniş bildirişi, chat mesajı); receiver public endpoint aça bilirsə. Pull — receiver tam kontrol istəyirsə, ya da tez-tez offline olursa; yüksək traffic-də polling bahalıdır. Hibrid yaxşı: webhook xəbər verir, client sonra API-dən dəqiq state çəkir.

---

## Best Practices

- **Retry with exponential backoff** — 5xx və timeout-larda; 4xx-ə retry etmə
- **Sign every webhook** — HMAC-SHA256 + timestamp, replay protection 5 dəqiqə
- **Include event_id** — receiver idempotency üçün
- **Per-endpoint queue partitioning** — bir yavaş endpoint bütün sistemi batırmasın
- **Circuit breaker** — daim fail edən endpoint-i pause et, owner-ə alert
- **SSRF protection** — private IP-ləri block et, DNS rebinding-dən qoru
- **Delivery log retention 30-90 gün** — owner debug edə bilsin
- **Delivery status UI + manual replay** — owner-in özünə kömək etməsi üçün
- **Metric per endpoint** — success rate, p99 latency, 5xx rate
- **Dead letter topic** — max attempt bitənləri manual inspection üçün saxla
- **HTTPS only** (tövsiyə) — webhook-da sensitive data ola bilər
- **Timeout 10-30 saniyə** — asylı qalma, receiver uzun işi async etməlidir
- **CloudEvents format** — standart event schema istifadə et (long-term yaxşıdır)
- **Horizon + Laravel Queue** — delivery worker-lər üçün, retry backoff ilə
