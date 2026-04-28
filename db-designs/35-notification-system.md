# Notification System — DB Design (Senior ⭐⭐⭐)

## İcmal

Bildiriş sistemi hər böyük tətbiqin kritik komponentidir. Push (FCM/APNs), email, SMS, in-app bildirişlər fərqli kanallar üzərindən çatdırılır. DB dizaynı delivery tracking, preferences, rate limiting və fan-out strategiyasını əhatə edir.

---

## Tövsiyə olunan DB Stack

```
Primary:    PostgreSQL    (notifications, preferences, templates)
Queue:      Redis         (delivery queue, deduplication)
Events:     Kafka         (notification events fan-out)
Cache:      Redis         (unread count, user preferences)
```

---

## Bildiriş Növləri

```
┌────────────────┬──────────────────┬─────────────────────────────┐
│ Tip            │ Provider         │ Use case                    │
├────────────────┼──────────────────┼─────────────────────────────┤
│ Push (mobile)  │ FCM (Android)    │ Real-time alerts, messages  │
│                │ APNs (iOS)       │                             │
├────────────────┼──────────────────┼─────────────────────────────┤
│ Email          │ SendGrid/SES/    │ Transactional, marketing    │
│                │ Postmark         │                             │
├────────────────┼──────────────────┼─────────────────────────────┤
│ SMS            │ Twilio/Vonage    │ OTP, urgent alerts          │
├────────────────┼──────────────────┼─────────────────────────────┤
│ In-app         │ Custom DB        │ Activity feeds, @mentions   │
├────────────────┼──────────────────┼─────────────────────────────┤
│ WebSocket      │ Laravel Echo /   │ Real-time UI update         │
│                │ Pusher           │                             │
└────────────────┴──────────────────┴─────────────────────────────┘
```

---

## Core Schema

```sql
-- ==================== NOTIFICATION TEMPLATES ====================
CREATE TABLE notification_templates (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key         VARCHAR(100) UNIQUE NOT NULL,  -- 'order_shipped', 'mention'
    name        VARCHAR(255) NOT NULL,
    
    -- Per-channel templates
    push_title     VARCHAR(100),
    push_body      VARCHAR(500),
    email_subject  VARCHAR(255),
    email_html     TEXT,          -- HTML template (Blade/Twig)
    email_text     TEXT,          -- Plain text fallback
    sms_body       VARCHAR(160),  -- 160 char limit
    in_app_body    VARCHAR(500),
    
    -- Metadata
    category    VARCHAR(50),      -- 'transactional', 'marketing', 'system'
    is_active   BOOLEAN DEFAULT TRUE,
    
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== NOTIFICATIONS ====================
CREATE TABLE notifications (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Recipient
    user_id         BIGINT NOT NULL,
    
    -- Type
    type            VARCHAR(100) NOT NULL,     -- 'order_shipped', 'new_message'
    
    -- Content (rendered or raw)
    title           VARCHAR(255),
    body            TEXT NOT NULL,
    action_url      TEXT,                      -- deep link / URL
    image_url       TEXT,
    
    -- Data payload (for push/in-app)
    data            JSONB DEFAULT '{}',        -- {order_id: 123, ...}
    
    -- Channel-specific status
    channels        VARCHAR(20)[] DEFAULT '{}', -- ['push', 'email', 'in_app']
    
    -- Read status (in-app only)
    is_read         BOOLEAN DEFAULT FALSE,
    read_at         TIMESTAMPTZ,
    
    -- Soft delete
    is_deleted      BOOLEAN DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    
    -- Priority
    priority        VARCHAR(10) DEFAULT 'normal',  -- 'high', 'normal', 'low'
    
    -- Expiry (push notifications expire)
    expires_at      TIMESTAMPTZ,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Performance indexes
CREATE INDEX idx_notifications_user    ON notifications(user_id, created_at DESC)
    WHERE is_deleted = FALSE;
CREATE INDEX idx_notifications_unread  ON notifications(user_id, is_read)
    WHERE is_read = FALSE AND is_deleted = FALSE;
CREATE INDEX idx_notifications_type    ON notifications(type, created_at DESC);

-- ==================== DELIVERY LOG ====================
CREATE TABLE notification_deliveries (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID NOT NULL REFERENCES notifications(id),
    
    channel         VARCHAR(20) NOT NULL,  -- 'push', 'email', 'sms'
    
    -- Channel-specific target
    target          VARCHAR(500) NOT NULL, -- token / email / phone
    
    status          VARCHAR(20) DEFAULT 'pending',
    -- pending, sent, delivered, failed, bounced, unsubscribed
    
    -- Provider response
    provider        VARCHAR(50),   -- 'fcm', 'apns', 'sendgrid'
    provider_msg_id VARCHAR(255),  -- external message ID for tracking
    error_message   TEXT,
    
    -- Retry
    attempt         SMALLINT DEFAULT 1,
    next_retry_at   TIMESTAMPTZ,
    
    sent_at         TIMESTAMPTZ,
    delivered_at    TIMESTAMPTZ,
    failed_at       TIMESTAMPTZ,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_deliveries_notification ON notification_deliveries(notification_id);
CREATE INDEX idx_deliveries_retry        ON notification_deliveries(next_retry_at)
    WHERE status = 'pending';

-- ==================== DEVICE TOKENS ====================
CREATE TABLE device_tokens (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     BIGINT NOT NULL,
    
    token       VARCHAR(500) UNIQUE NOT NULL,  -- FCM/APNs token
    platform    VARCHAR(10) NOT NULL,           -- 'ios', 'android', 'web'
    
    app_version VARCHAR(20),
    device_model VARCHAR(100),
    os_version   VARCHAR(20),
    
    is_active   BOOLEAN DEFAULT TRUE,
    last_used   TIMESTAMPTZ DEFAULT NOW(),
    
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_tokens_user ON device_tokens(user_id) WHERE is_active = TRUE;

-- ==================== USER PREFERENCES ====================
CREATE TABLE notification_preferences (
    user_id         BIGINT NOT NULL,
    channel         VARCHAR(20) NOT NULL,  -- 'push', 'email', 'sms'
    notification_type VARCHAR(100),        -- NULL = global setting
    
    is_enabled      BOOLEAN DEFAULT TRUE,
    
    -- Email preferences
    email_address   VARCHAR(255),          -- override default email
    
    -- Quiet hours
    quiet_start     TIME,                  -- '22:00'
    quiet_end       TIME,                  -- '08:00'
    timezone        VARCHAR(50),           -- 'Asia/Baku'
    
    updated_at      TIMESTAMPTZ DEFAULT NOW(),
    
    PRIMARY KEY (user_id, channel, COALESCE(notification_type, ''))
);

-- ==================== IDEMPOTENCY (deduplication) ====================
CREATE TABLE notification_deduplication (
    idempotency_key VARCHAR(255) PRIMARY KEY,  -- 'user:123:order_shipped:456'
    notification_id UUID NOT NULL,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- TTL: 24 saat sonra sil (cron job)
```

---

## Redis Dizaynı

```
# Unread count cache (counter — DB-yə hər istekdə getmə)
INCR  notif:unread:{user_id}
DECR  notif:unread:{user_id}         -- mark as read
GET   notif:unread:{user_id}         → integer count

# In-app notification cache (son 20 bildiriş)
LPUSH notif:feed:{user_id} {notification_json}
LTRIM notif:feed:{user_id} 0 19      -- son 20 saxla
LRANGE notif:feed:{user_id} 0 -1     -- hamsını al

# Push token cache
HSET  user:tokens:{user_id} {token} {platform}
HGETALL user:tokens:{user_id}

# Rate limiting: spam qoruma
INCR  notif:rate:{user_id}:{type}:{date}
EXPIRE notif:rate:{user_id}:{type}:{date} 86400
-- Gündə max 5 dəfə eyni tip bildiriş
```

---

## Fan-out Strategiyaları

```
Ssenari: "Yeni post paylaşıldı" — 1M follower-ə bildiriş göndər

Push fan-out (2 yanaşma):

1. Fan-out on Write (eager):
   Post → Kafka → worker: hər follower-ə notification insert
   
   Pro: oxuma sürətli (DB-də hazır)
   Con: 1M follower → 1M row insert (yavaş yazma)
   Uyğun: < 10K follower

2. Fan-out on Read (lazy):
   Post yazılır, notification yaradılmır
   Feed yüklənəndə: "Bu userin post-larından son N-i?"
   
   Pro: yazma ucuz
   Con: oxuma bahalı (hər oxumada hesablanır)
   Uyğun: çox follower-li influencer-lər

Hibrid (Twitter/Instagram approach):
   Normal user (< 10K follower): fan-out on write
   Celeb/influencer (> 10K): fan-out on read
   Merge at read time
```

---

## Delivery Pipeline

```
Event → Notification System:

  Order shipped →
  1. OrderShippedEvent fire olur
  2. Kafka topic: notifications
  3. NotificationWorker consume edir:
     a. User preferences oxu (Redis cache)
     b. Preferred channels tap (push + email?)
     c. Template render et
     d. notifications table-a insert et
     e. Delivery queue-ya at (Redis/Kafka per channel)
  4. Push Worker: FCM/APNs-ə göndər
  5. Email Worker: SendGrid-ə göndər
  6. delivery status-u yenilə

Retry logic:
  Failed delivery → exponential backoff
  1st retry: 1 min
  2nd retry: 5 min
  3rd retry: 30 min
  4th retry: 2 hour
  5th retry: dead letter queue

Dead letter queue:
  5 cəhddən sonra uğursuz → DLQ
  Human review / alarm
```

---

## Quiet Hours & User Preferences

```php
// Bildiriş göndərməzdən əvvəl yoxla
class NotificationGateway
{
    public function shouldSend(int $userId, string $channel, string $type): bool
    {
        $pref = $this->getPreference($userId, $channel, $type);
        
        if (!$pref->is_enabled) {
            return false;
        }
        
        // Quiet hours check
        if ($pref->quiet_start && $pref->quiet_end) {
            $tz   = new DateTimeZone($pref->timezone ?? 'UTC');
            $now  = new DateTime('now', $tz);
            $time = $now->format('H:i');
            
            if ($this->isQuietHour($time, $pref->quiet_start, $pref->quiet_end)) {
                // Queue for delivery after quiet hours end
                $this->scheduleDelivery($userId, $pref->quiet_end, $tz);
                return false;
            }
        }
        
        return true;
    }
}
```

---

## Tanınmış Sistemlər

```
Facebook:
  Notification DB: custom (MySQL + Memcache)
  Fan-out: Kafka
  Unread count: in-memory distributed counter

Twitter:
  Push: FCM + APNs
  Timeline notifications: Fanout service
  Home timeline: Manhattan (custom KV)

Airbnb:
  Notification Center: MySQL
  Email: Amazon SES
  Push: Urban Airship / FCM
  Real-time: WebSocket (notification badges)

Discord:
  In-app: Cassandra (high write)
  Push: FCM/APNs
  Mention: fan-out on write
```

---

## Anti-Patterns

```
✗ DB-yə sync push göndərmək:
  HTTP call FCM-ə request thread-də → timeout risk
  Həmişə async queue ilə göndər

✗ Token-i validate etməmək:
  FCM "token not registered" error → tokeni sil
  Köhnə token-lər sistemdə qalır → wasted work

✗ Rate limiting yoxdur:
  Bug loop → user-ə 1000 bildiriş göndər
  Per-user, per-type rate limit mütləq lazımdır

✗ Deduplication yoxdur:
  Eyni event 2 dəfə işləndi → duplicate notification
  Idempotency key istifadə et

✗ User preferences cache-ləmədən:
  Hər bildiriş üçün DB sorğusu preferences üçün
  Redis cache: TTL 5 dəqiqə
```

---

## Praktik Tapşırıqlar

```
1. Laravel Notification system:
   - 3 channel: database, mail, push (FCM)
   - User preferences model yarat
   - Quiet hours middleware

2. Unread count real-time:
   - Redis INCR/DECR counter
   - WebSocket ilə frontend-ə push (Laravel Echo)
   - Badge number update

3. Fan-out worker:
   - Kafka consumer: "yeni post" eventi
   - 1000 follower-ə in-app notification yarat
   - Batch insert (1000 row deyil, 1 bulk INSERT)

4. Retry system:
   - Failed delivery-ləri detect et
   - Exponential backoff ilə yenidən cəhd
   - DLQ-ya yazma (5 cəhddən sonra)
```
