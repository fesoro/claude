# Push Notification Backend (APNs / FCM)

> Interview prep — mobile push notification backend (APNs, FCM, Web Push).
> Complementary to file 13 (notification system) — here fokus mobile push.

---

## Tələblər (Requirements)

### Funksional (Functional)

- Send push to iOS (APNs), Android (FCM), Web (Web Push), Huawei (HMS).
- Campaign: 100k — 10M devices, deliver in minutes.
- Priority messages (chat, 2FA, alert) — fast, under 1 second.
- Token registry — store device tokens, rotate, cleanup invalid.
- Topic broadcast — "news", "sports" kimi subset-ə göndər.
- User preferences — quiet hours, channels (promo vs transactional).

### Qeyri-funksional (Non-functional)

- Scale: 100M users × 2 devices = 200M tokens.
- Throughput: 10M push / few minutes = ~50k-100k push/sec.
- Provider rate limits — APNs ~9000/sec/connection, FCM 600k/min/project.
- Delivery reliability — retry on 5xx, respect 4xx (invalid token).
- Deliverability monitoring — error rate alarm.

---

## Platformalar (Platforms)

### APNs (Apple Push Notification service)

- Protocol: **HTTP/2**, binary JSON payload.
- Auth: **token-based (JWT, ES256)** — modern, stateless; or certificate.
- Endpoint: `https://api.push.apple.com/3/device/{token}`.
- Multiplexing: 1000+ streams per connection.
- Limits: payload 4KB (normal), 5KB (VoIP).

### FCM (Firebase Cloud Messaging)

- Protocol: **HTTP v1 API** (REST + JSON).
- Auth: **OAuth2 Bearer token** (short-lived, refresh hər saat).
- Endpoint: `https://fcm.googleapis.com/v1/projects/{project}/messages:send`.
- Relay: Google Play Services (GCM veya FCM servisi on device).
- Limits: 600k messages/min, payload 4KB.

### Web Push

- Standard: **VAPID** (Voluntary Application Server Identification) + W3C Push API.
- Browser service worker → push server (Chrome=FCM, Firefox=Mozilla autopush).
- Requires HTTPS + user permission.

### Huawei Push (HMS)

- For Huawei devices without Google services (China market).
- REST API similar to FCM.

---

## ASCII Diaqram (Architecture)

```
                 [Campaign API]
                      |
                      v
             [Segment / Audience Query]
                (SQL or Data Warehouse)
                      |
                      v
               [Fan-out Dispatcher]
                      |
        +-------------+-------------+
        |             |             |
        v             v             v
  [Kafka topic]  [Kafka topic]  [Kafka topic]
   ios.push       android.push   web.push
        |             |             |
        v             v             v
  [APNs worker]  [FCM worker]   [Web Push worker]
   (HTTP/2)       (HTTP v1)      (VAPID)
        |             |             |
        v             v             v
    [Apple]       [Google]      [Browser vendor]
      APNs          FCM           push server
        |             |             |
        v             v             v
     iPhone        Android        Browser

              [Token Registry]  <---- register/refresh
              (Postgres + Redis)      cleanup invalid

              [Delivery Result DB]    <--- status, error code
```

---

## Token Registry

### Schema

```sql
CREATE TABLE device_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    platform VARCHAR(20) NOT NULL,   -- ios | android | web | huawei
    token TEXT NOT NULL,
    app_version VARCHAR(20),
    os_version VARCHAR(20),
    locale VARCHAR(10),
    timezone VARCHAR(50),
    last_seen TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL,
    UNIQUE (platform, token)
);

CREATE INDEX idx_user_platform ON device_tokens(user_id, platform);
```

### Token rotation

- FCM refreshes tokens periodically — app must re-register.
- APNs rotates on reinstall, restore, OS update.
- Server policy: on duplicate, overwrite with latest `last_seen`.

### Cleanup

- Invalid token response (`Unregistered`, `BadDeviceToken`) → mark dead → delete.
- Inactive > 90 days → remove (reduces bounce rate).
- Scheduled job: nightly cleanup + metrics.

---

## Core Pipeline (Fan-out)

### Campaign flow

1. Product team creates campaign via API: `{audience_filter, content, schedule}`.
2. Dispatcher queries audience — `SELECT token, platform FROM ... WHERE filter`.
3. Segmenter streams tokens to Kafka topics per platform.
4. Worker consumes, calls provider, records result.
5. Retry on 5xx (exponential backoff), drop on 4xx, dedup on `(campaign_id, token)`.

### Dedup / idempotency

- Key: `sha256(campaign_id + token)` → Redis SETNX with TTL 24h.
- Prevents duplicate send on worker retry.

---

## Fan-out Strategies

| Strategy         | Pros                           | Cons                          | Use case          |
| ---------------- | ------------------------------ | ----------------------------- | ----------------- |
| Push (eager)     | Precise targeting, trackable   | Expensive fan-out             | Marketing campaign|
| Pull             | No fan-out cost                | Delay, battery drain          | Rare              |
| Topic-based      | O(1) broadcast on provider     | Limited targeting             | News, sports      |

**FCM topics** — device subscribes to topic; single API call broadcasts to all.
**APNs topics** — slightly different (relates to bundle ID), use with care.

---

## HTTP/2 Multiplexing

- APNs: one connection can handle 1000+ concurrent streams.
- Reuse connection per worker process — connection pool.
- Benefit: no TCP/TLS handshake overhead, higher throughput.

```
Worker process
  |
  +-- HTTP/2 connection to APNs
        |-- stream 1: send to token A
        |-- stream 2: send to token B
        |-- stream 3: send to token C
        |... (up to 1000 concurrent)
```

---

## Rate Limiting və Backpressure

- APNs: ~9000/sec per connection; open multiple connections to scale.
- FCM: 600k/min per project; batch and throttle.
- Use token-bucket per provider per worker.
- If provider returns `429 Too Many Requests` → backoff, reduce QPS.

---

## Priority Handling

### Critical (transactional)

- 2FA code, chat message, ride accepted.
- Bypass campaign queue → direct send.
- APNs: `apns-priority: 10`, FCM: `priority: "high"`.
- SLA: < 1 second delivery.

### Normal (marketing)

- Promotion, newsletter.
- Scheduled send, rate-limited.
- APNs: `apns-priority: 5`, FCM: `priority: "normal"`.
- OS may batch/delay.

---

## Silent və Rich Push

- **Silent push** — no UI, triggers background fetch.
  APNs: `content-available: 1`, FCM: `data`-only payload.
  Use case: sync mailbox, update chat state.

- **Rich push** — image, video, action buttons.
  APNs: Notification Service Extension downloads attachment.
  FCM: `notification.image` or custom handler.

---

## PHP/Laravel Nümunə (Example)

### FCM HTTP v1 — Google OAuth2

```php
namespace App\Services\Push;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;

class FcmSender
{
    private string $projectId;
    private GoogleClient $google;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->google = new GoogleClient();
        $this->google->setAuthConfig(config('services.fcm.credentials'));
        $this->google->addScope('https://www.googleapis.com/auth/firebase.messaging');
    }

    public function send(string $token, array $notification, array $data = []): array
    {
        $accessToken = $this->google->fetchAccessTokenWithAssertion()['access_token'];

        $response = Http::withToken($accessToken)
            ->timeout(5)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => $notification,
                    'data' => $data,
                    'android' => ['priority' => 'HIGH'],
                    'apns' => ['headers' => ['apns-priority' => '10']],
                ],
            ]);

        if ($response->status() === 404) {
            // Token invalid — remove from registry
            DeviceToken::where('token', $token)->delete();
        }

        return $response->json();
    }
}
```

### APNs — JWT token auth (HTTP/2)

```php
namespace App\Services\Push;

use Firebase\JWT\JWT;

class ApnsSender
{
    public function send(string $deviceToken, array $payload): array
    {
        $jwt = $this->generateJwt();
        $url = "https://api.push.apple.com/3/device/{$deviceToken}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "authorization: bearer {$jwt}",
                'apns-topic: com.example.app',
                'apns-push-type: alert',
                'apns-priority: 10',
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body];
    }

    private function generateJwt(): string
    {
        $privateKey = file_get_contents(config('services.apns.key_path'));
        $payload = [
            'iss' => config('services.apns.team_id'),
            'iat' => time(),
        ];
        $header = ['alg' => 'ES256', 'kid' => config('services.apns.key_id')];
        return JWT::encode($payload, $privateKey, 'ES256', null, $header);
    }
}
```

### Laravel Notification channel

```php
class OrderShipped extends Notification
{
    public function via($notifiable): array
    {
        return ['fcm', 'apns'];  // custom channels
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => 'Order shipped',
            'body' => "Your order #{$this->order->id} is on the way",
        ];
    }
}

// Dispatch via Horizon queue (batch fan-out)
Bus::batch($users->map(fn ($u) => new SendPushJob($u, $campaign)))
    ->name("campaign-{$campaign->id}")
    ->dispatch();
```

---

## Segmentation və A/B testing

- Precompute audience: `SELECT user_id, token FROM users JOIN tokens WHERE locale='az' AND country='AZ'`.
- Materialize to S3 or Kafka for worker input.
- A/B: hash `user_id % 100 < 50` → group A, else group B; compare open rate via deep link param.

---

## User Preferences və Privacy

- Quiet hours — user sets "do not disturb 22:00-08:00" in their TZ.
- Channels — DB table `notification_preferences(user_id, channel, enabled)`.
- Transactional push bypasses marketing preferences.
- PII in payload — encrypt sensitive content; use APNs Notification Service Extension to decrypt on device.

---

## Data Model

```sql
CREATE TABLE campaigns (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(200),
    audience_filter JSONB,
    content JSONB,
    state VARCHAR(20),  -- draft | scheduled | running | done | failed
    scheduled_at TIMESTAMP,
    created_at TIMESTAMP
);

CREATE TABLE push_jobs (
    id BIGSERIAL PRIMARY KEY,
    campaign_id BIGINT,
    token_id BIGINT,
    platform VARCHAR(20),
    status VARCHAR(20),      -- pending | sent | failed | invalid
    provider_msg_id VARCHAR(100),
    error_code VARCHAR(50),
    sent_at TIMESTAMP,
    INDEX idx_campaign_status (campaign_id, status)
);
```

---

## Deliverability Monitoring

- Metrics per provider: send rate, success %, error breakdown.
- Alarm: error rate > 5% for 5 min → page on-call.
- Per-platform retention: OS may drop if user hasn't opened app in N days.
- Dashboard: Grafana panel for APNs/FCM/Web Push success rates.

---

## Abuse və Throttling

- Max 10 marketing push per user per day.
- OS-level throttling: iOS may reduce priority if user ignores.
- Spam protection — content review for campaigns.

---

## Real-world Nümunələr

- **WhatsApp** — uses APNs for iOS, FCM for Android; critical path for messages.
- **Facebook Messenger** — custom push backend; falls back to SMS if push fails.
- **Uber** — ride notifications on critical path; priority 10 always.
- **Slack** — smart delivery (don't wake user if desktop is active).

---

## Interview Q&A

### Q1: Why use HTTP/2 for APNs instead of HTTP/1.1?

**A:** HTTP/2 supports **multiplexing** — 1000+ concurrent streams over a single TCP/TLS connection. HTTP/1.1 would need 1000 separate connections, which is expensive (handshake, file descriptors). APNs requires HTTP/2. Connection reuse is critical for throughput at scale.

### Q2: How do you handle invalid tokens?

**A:** When provider returns 4xx error codes (APNs: `BadDeviceToken`, `Unregistered`; FCM: `UNREGISTERED`, `INVALID_ARGUMENT`) — mark token as invalid in DB and remove. On next app launch, app re-registers with fresh token. Cleanup job runs nightly to remove tokens inactive > 90 days. Keeping stale tokens inflates send volume and skews metrics.

### Q3: Campaign for 10M users — how do you send in 5 minutes?

**A:** Target ~35k pushes/sec. Strategy:
1. Pre-compute audience to Kafka (streaming, not loaded to memory).
2. Fan-out workers partitioned by platform (iOS, Android, Web).
3. Each worker maintains HTTP/2 connection pool (e.g., 50 connections × 1000 streams).
4. Respect provider limits (FCM 600k/min = 10k/sec per project; open multiple projects if needed).
5. Use Horizon or similar for parallel job processing.

### Q4: Priority message (2FA) vs marketing — how are they handled differently?

**A:**
- **2FA**: bypass campaign queue, direct send, APNs `apns-priority: 10`, FCM `priority: high`. SLA < 1 sec. Skip user preferences (except explicit opt-out).
- **Marketing**: enqueue to batch worker, scheduled send, rate-limited, `priority: normal`. OS may batch. Respect quiet hours and frequency caps.

### Q5: What is silent push and when do you use it?

**A:** Silent push has no user-visible alert. APNs: `content-available: 1`, no `alert` field. FCM: `data`-only (no `notification`). Use cases: trigger background sync (new email, chat history), update badge count, refresh app state. Important: iOS throttles aggressive silent pushes, so do not abuse — may be delayed or dropped.

### Q6: How do you handle rate limiting from APNs/FCM?

**A:**
- **Proactive**: token-bucket per provider per worker. Stay under known limits (APNs ~9k/sec/conn, FCM 600k/min/project).
- **Reactive**: on `429 Too Many Requests` → exponential backoff, reduce worker QPS, emit metric.
- **Scale out**: open more HTTP/2 connections (APNs), or split across multiple FCM projects if enterprise allows.
- **Backpressure**: queue depth metric → autoscaler adds workers.

### Q7: User receives duplicate notifications — how do you debug?

**A:** Possible causes:
1. Worker retry without idempotency — add dedup key `(campaign_id, token)` in Redis with TTL.
2. Multiple tokens for same device (old not cleaned) — enforce UNIQUE on `(platform, token)`.
3. Same user has app on multiple devices — intended; add user-level "sent already today" check if needed.
4. Provider retransmits — rare; check provider-side msg ID.

Debug: trace a specific user's delivery events (campaign_id → push_jobs → provider response).

### Q8: Why not use Web Push for mobile apps?

**A:** Web Push is designed for browsers via service workers. On mobile:
- iOS Safari supports Web Push (iOS 16.4+) but only for installed PWAs.
- Android Chrome supports Web Push, but native app with FCM is more reliable (background, richer UI, no browser dependency).
- Native APNs/FCM deliver to OS notification center; Web Push requires browser open or service worker active.

For a mobile app product, use native FCM/APNs. Web Push is for website re-engagement.

---

## Best Practices

- **Token hygiene** — delete invalid immediately; cleanup > 90 day inactive.
- **HTTP/2 connection pool** — reuse connections, don't reconnect per send.
- **Idempotency key** — `(campaign_id, token)` in Redis, TTL 24h.
- **Priority split** — separate queues for transactional vs marketing.
- **Platform-specific payload** — APNs needs `aps` envelope, FCM uses `notification`+`data`.
- **Rate limit per user** — max 10 marketing/day; respect quiet hours.
- **Dedup at registry** — UNIQUE(platform, token); latest wins.
- **Deep link tracking** — every push includes campaign_id param for open-rate analytics.
- **A/B test content** — hash user_id, split groups, compare CTR.
- **Monitor error rate** — per-provider, per-platform; alarm on spike.
- **Batch fan-out** — stream audience (Kafka) rather than load to memory.
- **Separate FCM projects** — for multi-tenant or very high volume, avoid 600k/min cap.
- **Encrypt sensitive payload** — use APNs Notification Service Extension on device.
- **Test on real devices** — emulators don't receive real push; use TestFlight / Firebase Test Lab.
- **Graceful degradation** — if push fails repeatedly, fall back to SMS or email for critical.
