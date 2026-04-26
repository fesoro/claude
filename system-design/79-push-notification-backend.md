# Push Notification Backend (Senior)

> Interview hazırlığı — mobile push notification backend (APNs, FCM, Web Push).
> 13 nömrəli fayla (notification system) tamamlayıcıdır — burada fokus mobile push-dur.

---


## Niyə Vacibdir

APNs/FCM üzərindən milyonlarla cihaza notification göndərmək fan-out, priority queue, delivery tracking tələb edir. Silent push, notification grouping, user preference idarəsi — real app-in production problemidir. Laravel Notification-ın arxasındakı infra belə işləyir.

## Tələblər

### Funksional (Functional)

- Push göndər: iOS (APNs), Android (FCM), Web (Web Push), Huawei (HMS).
- Campaign: 100k — 10M cihaz, bir neçə dəqiqə içində çatdırılma.
- Prioritet mesajlar (chat, 2FA, alert) — sürətli, 1 saniyədən az.
- Token registry — device token-lərini saxla, yenilə, etibarsızları təmizlə.
- Topic broadcast — "news", "sports" kimi subset-ə göndər.
- User preference-ləri — quiet hours, channel-lar (promo vs transactional).

### Qeyri-funksional (Non-functional)

- Miqyas: 100M user × 2 cihaz = 200M token.
- Throughput: 10M push / bir neçə dəqiqə = ~50k-100k push/san.
- Provider rate limit-ləri — APNs ~9000/san/connection, FCM 600k/dəq/project.
- Delivery etibarlılığı — 5xx-də retry, 4xx-ə hörmət et (invalid token).
- Deliverability monitoring — error rate alarmı.

---

## Platformalar (Platforms)

### APNs (Apple Push Notification service)

- Protokol: **HTTP/2**, binary JSON payload.
- Auth: **token əsaslı (JWT, ES256)** — modern, stateless; və ya certificate.
- Endpoint: `https://api.push.apple.com/3/device/{token}`.
- Multiplexing: connection başına 1000+ stream.
- Limitlər: payload 4KB (normal), 5KB (VoIP).

### FCM (Firebase Cloud Messaging)

- Protokol: **HTTP v1 API** (REST + JSON).
- Auth: **OAuth2 Bearer token** (qısa ömürlü, hər saat refresh).
- Endpoint: `https://fcm.googleapis.com/v1/projects/{project}/messages:send`.
- Relay: Google Play Services (cihazda GCM və ya FCM servisi).
- Limitlər: 600k mesaj/dəq, payload 4KB.

### Web Push

- Standard: **VAPID** (Voluntary Application Server Identification) + W3C Push API.
- Browser service worker → push server (Chrome=FCM, Firefox=Mozilla autopush).
- HTTPS və user icazəsi tələb edir.

### Huawei Push (HMS)

- Google xidmətləri olmayan Huawei cihazları üçün (Çin bazarı).
- FCM-ə bənzər REST API.

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
              (Postgres + Redis)      etibarsızları təmizlə

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

- FCM token-ləri vaxtaşırı yeniləyir — app yenidən qeydiyyatdan keçməlidir.
- APNs reinstall, restore, OS update zamanı rotate edir.
- Server qaydası: duplicate olduqda ən son `last_seen` ilə üzərinə yaz.

### Cleanup

- Invalid token cavabı (`Unregistered`, `BadDeviceToken`) → ölü kimi işarələ → sil.
- 90 gündən artıq qeyri-aktiv → çıxar (bounce rate-i azaldır).
- Scheduled job: hər gecə cleanup + metrika.

---

## Əsas Pipeline (Fan-out)

### Campaign axını

1. Product komandası API vasitəsilə campaign yaradır: `{audience_filter, content, schedule}`.
2. Dispatcher audience-i sorğulayır — `SELECT token, platform FROM ... WHERE filter`.
3. Segmenter token-ləri platforma görə Kafka topic-lərinə stream edir.
4. Worker consume edir, provider-ə çağırış edir, nəticəni qeyd edir.
5. 5xx-də retry (exponential backoff), 4xx-də drop, `(campaign_id, token)` üzrə dedup.

### Dedup / idempotency

- Key: `sha256(campaign_id + token)` → Redis SETNX, TTL 24 saat.
- Worker retry zamanı duplicate göndərişin qarşısını alır.

---

## Fan-out Strategiyaları

| Strategiya       | Üstünlüklər                    | Çatışmazlıqlar                | İstifadə yeri     |
| ---------------- | ------------------------------ | ----------------------------- | ----------------- |
| Push (eager)     | Dəqiq hədəfləmə, izlənə bilir  | Baha fan-out                  | Marketing campaign|
| Pull             | Fan-out xərci yoxdur           | Gecikmə, batareya tükənməsi   | Nadir             |
| Topic-based      | Provider-də O(1) broadcast     | Məhdud hədəfləmə              | News, sports      |

**FCM topics** — cihaz topic-ə abunə olur; tək API çağırışı hamısına broadcast edir.
**APNs topics** — bir az fərqlidir (bundle ID-yə bağlıdır), ehtiyatla istifadə et.

---

## HTTP/2 Multiplexing

- APNs: bir connection 1000+ paralel stream idarə edə bilir.
- Hər worker process üçün connection-u yenidən istifadə et — connection pool.
- Fayda: TCP/TLS handshake overhead-i yoxdur, yüksək throughput.

```
Worker process
  |
  +-- APNs-ə HTTP/2 connection
        |-- stream 1: token A-ya göndər
        |-- stream 2: token B-yə göndər
        |-- stream 3: token C-yə göndər
        |... (1000-ə qədər paralel)
```

---

## Rate Limiting və Backpressure

- APNs: connection başına ~9000/san; miqyas üçün bir neçə connection aç.
- FCM: project başına 600k/dəq; batch və throttle.
- Provider başına, worker başına token-bucket istifadə et.
- Provider `429 Too Many Requests` qaytarırsa → backoff, QPS-i azalt.

---

## Priority Handling

### Critical (transactional)

- 2FA kodu, chat mesajı, sifarişin qəbulu.
- Campaign queue-nu keç → birbaşa göndər.
- APNs: `apns-priority: 10`, FCM: `priority: "high"`.
- SLA: < 1 saniyə çatdırılma.

### Normal (marketing)

- Promotion, newsletter.
- Planlı göndərmə, rate-limited.
- APNs: `apns-priority: 5`, FCM: `priority: "normal"`.
- OS batch/delay edə bilər.

---

## Silent və Rich Push

- **Silent push** — UI yoxdur, background fetch-i tetikləyir.
  APNs: `content-available: 1`, FCM: yalnız `data` payload.
  İstifadə: mailbox sync, chat state yeniləmə.

- **Rich push** — şəkil, video, action düymələri.
  APNs: Notification Service Extension attachment yükləyir.
  FCM: `notification.image` və ya custom handler.

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
            // Token etibarsızdır — registry-dən sil
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
        return ['fcm', 'apns'];  // custom channel-lər
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => 'Order shipped',
            'body' => "Your order #{$this->order->id} is on the way",
        ];
    }
}

// Horizon queue ilə dispatch (batch fan-out)
Bus::batch($users->map(fn ($u) => new SendPushJob($u, $campaign)))
    ->name("campaign-{$campaign->id}")
    ->dispatch();
```

---

## Segmentation və A/B testing

- Audience-i əvvəlcədən hesabla: `SELECT user_id, token FROM users JOIN tokens WHERE locale='az' AND country='AZ'`.
- S3 və ya Kafka-ya materialize et, worker input kimi.
- A/B: `user_id % 100 < 50` hash-i → qrup A, əksi → qrup B; deep link param vasitəsilə open rate-i müqayisə et.

---

## User Preferences və Privacy

- Quiet hours — user öz TZ-sində "do not disturb 22:00-08:00" təyin edir.
- Channel-lar — `notification_preferences(user_id, channel, enabled)` DB cədvəli.
- Transactional push marketing preference-lərini keçir.
- Payload-dakı PII — həssas məlumatı şifrələ; cihazda decrypt üçün APNs Notification Service Extension istifadə et.

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

- Provider başına metrikalar: send rate, success %, error breakdown.
- Alarm: error rate 5 dəqiqə ərzində > 5% → on-call-ı oyat.
- Platform başına retention: user N gün app-i açmasa OS drop edə bilər.
- Dashboard: Grafana paneli — APNs/FCM/Web Push success rate üçün.

---

## Abuse və Throttling

- User başına gündə maksimum 10 marketing push.
- OS-səviyyəsində throttling: iOS user məhəl qoymasa prioriteti azalda bilər.
- Spam müdafiəsi — campaign-lər üçün content review.

---

## Real-world Nümunələr

- **WhatsApp** — iOS üçün APNs, Android üçün FCM; mesajlar üçün critical path.
- **Facebook Messenger** — custom push backend; push uğursuzdursa SMS-ə fallback.
- **Uber** — ride notification-ları critical path-də; həmişə priority 10.
- **Slack** — smart delivery (desktop aktivdirsə user-i oyatma).

---

## Praktik Tapşırıqlar

### Q1: Niyə APNs üçün HTTP/1.1 əvəzinə HTTP/2?

**A:** HTTP/2 **multiplexing**-i dəstəkləyir — tək TCP/TLS connection üzərində 1000+ paralel stream. HTTP/1.1-də 1000 ayrı connection lazım olardı, bu bahadır (handshake, file descriptor-lar). APNs HTTP/2 tələb edir. Miqyasda throughput üçün connection yenidən istifadəsi kritikdir.

### Q2: Etibarsız token-ləri necə idarə edirsən?

**A:** Provider 4xx error kodları qaytaranda (APNs: `BadDeviceToken`, `Unregistered`; FCM: `UNREGISTERED`, `INVALID_ARGUMENT`) — token-i DB-də etibarsız kimi işarələ və sil. App növbəti dəfə açılanda yeni token ilə yenidən qeydiyyatdan keçir. Cleanup job hər gecə işləyib 90 gündən artıq qeyri-aktiv token-ləri silir. Köhnə token-ləri saxlamaq send həcmini şişirdir və metrikaları pozur.

### Q3: 10M user üçün campaign — 5 dəqiqədə necə göndərirsən?

**A:** Hədəf ~35k push/san. Strategiya:
1. Audience-i Kafka-ya pre-compute et (streaming, memory-ə yükləmədən).
2. Platforma görə fan-out worker-ləri bölüşdür (iOS, Android, Web).
3. Hər worker HTTP/2 connection pool saxlayır (məs. 50 connection × 1000 stream).
4. Provider limitlərinə hörmət (FCM 600k/dəq = project başına 10k/san; lazım olsa bir neçə project aç).
5. Paralel job processing üçün Horizon və ya oxşarını istifadə et.

### Q4: Priority mesajı (2FA) vs marketing — necə fərqli idarə olunur?

**A:**
- **2FA**: campaign queue-nu keç, birbaşa göndər, APNs `apns-priority: 10`, FCM `priority: high`. SLA < 1 san. User preference-lərini keç (explicit opt-out istisna).
- **Marketing**: batch worker-ə queue-ya at, planlı göndər, rate-limited, `priority: normal`. OS batch edə bilər. Quiet hours və frequency cap-lərə hörmət et.

### Q5: Silent push nədir və nə vaxt istifadə edirsən?

**A:** Silent push-un user-ə görünən alert-i yoxdur. APNs: `content-available: 1`, `alert` sahəsi yoxdur. FCM: yalnız `data` (`notification` yoxdur). İstifadə: background sync-i tetikləmək (yeni email, chat tarixçəsi), badge count yeniləmək, app state-i refresh etmək. Vacib: iOS aqressiv silent push-u throttle edir, sui-istifadə etmə — gecikə və ya drop oluna bilər.

### Q6: APNs/FCM-dən rate limiting-i necə idarə edirsən?

**A:**
- **Proaktiv**: provider başına, worker başına token-bucket. Məlum limitlərin altında qal (APNs ~9k/san/conn, FCM 600k/dəq/project).
- **Reaktiv**: `429 Too Many Requests`-də → exponential backoff, worker QPS-ni azalt, metrika yay.
- **Scale out**: daha çox HTTP/2 connection aç (APNs) və ya enterprise icazə verirsə bir neçə FCM project-ə böl.
- **Backpressure**: queue depth metric → autoscaler worker əlavə edir.

### Q7: User duplicate bildiriş alır — necə debug edirsən?

**A:** Mümkün səbəblər:
1. Worker idempotency olmadan retry edir — Redis-də `(campaign_id, token)` dedup key əlavə et, TTL ilə.
2. Eyni cihaz üçün bir neçə token (köhnələr silinməyib) — `(platform, token)` üzrə UNIQUE tətbiq et.
3. Eyni user-in app-i bir neçə cihazdadır — bu gözləniləndir; lazım olsa user səviyyəsində "bu gün artıq göndərildi" yoxlaması əlavə et.
4. Provider yenidən ötürür — nadirdir; provider tərəfi msg ID-ni yoxla.

Debug: konkret user-in delivery event-lərini izləyə bil (campaign_id → push_jobs → provider cavabı).

### Q8: Niyə mobile app-lər üçün Web Push istifadə etmirsən?

**A:** Web Push service worker vasitəsilə browser-lər üçün nəzərdə tutulub. Mobile-da:
- iOS Safari Web Push-u dəstəkləyir (iOS 16.4+), amma yalnız quraşdırılmış PWA-lar üçün.
- Android Chrome Web Push-u dəstəkləyir, lakin FCM ilə native app daha etibarlıdır (background, daha zəngin UI, browser-dən asılılıq yoxdur).
- Native APNs/FCM OS notification center-ə çatdırır; Web Push browser açıq və ya service worker aktiv olmağını tələb edir.

Mobile app məhsulu üçün native FCM/APNs istifadə et. Web Push website re-engagement üçündür.

---

## Praktik Baxış

- **Token gigiyenası** — etibarsızları dərhal sil; 90 gündən artıq qeyri-aktivləri təmizlə.
- **HTTP/2 connection pool** — connection-ları yenidən istifadə et, hər göndərişdə təkrar qoşulma.
- **Idempotency key** — `(campaign_id, token)` Redis-də, TTL 24 saat.
- **Priority ayrılması** — transactional vs marketing üçün ayrı queue-lar.
- **Platforma spesifik payload** — APNs `aps` envelope tələb edir, FCM `notification`+`data` istifadə edir.
- **User başına rate limit** — gündə maks 10 marketing; quiet hours-a hörmət et.
- **Registry-də dedup** — UNIQUE(platform, token); ən son qalib gəlir.
- **Deep link tracking** — hər push open-rate analitikası üçün campaign_id param ehtiva edir.
- **A/B test content** — user_id-ni hash et, qruplara böl, CTR-i müqayisə et.
- **Error rate monitor** — provider başına, platforma başına; spike-da alarm.
- **Batch fan-out** — audience-i stream et (Kafka), memory-ə yükləmədən.
- **Ayrı FCM project-lər** — multi-tenant və ya çox yüksək həcm üçün, 600k/dəq limitini keç.
- **Həssas payload şifrələnməsi** — cihazda APNs Notification Service Extension istifadə et.
- **Real cihazda test** — emulator-lar real push almır; TestFlight / Firebase Test Lab istifadə et.


## Əlaqəli Mövzular

- [Notification System](13-notification-system.md) — multi-channel notification
- [Message Queues](05-message-queues.md) — push delivery queue
- [Pub/Sub](81-pubsub-system-design.md) — fan-out delivery modeli
- [Webhook Delivery](82-webhook-delivery-system.md) — external delivery retry
- [Real-Time Systems](17-real-time-systems.md) — WebSocket alternativ
