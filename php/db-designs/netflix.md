# Netflix — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    Netflix Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL                │ User accounts, billing, subscriptions    │
│ Apache Cassandra     │ Watch history, user activity, ratings    │
│ CockroachDB          │ Global distributed SQL (yeni sistemlər)  │
│ Amazon S3            │ Video files (petabytes)                  │
│ Elasticsearch        │ Content search, catalog                  │
│ Apache Kafka         │ Event streaming (playback events)        │
│ Redis                │ Session cache, API cache                 │
│ Amazon DynamoDB      │ Distributed config, feature flags        │
│ Apache Iceberg       │ Data lake (analytics)                    │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Cassandra — Netflix-in Əsas DB-si

```
Niyə Cassandra?

Netflix problemi (2011):
  SimpleDB (Amazon) → scale problemi
  
  "Cassandra bizim ikinci evimizdir" — Netflix Engineering Blog
  
İstifadə sahələri:
  ✓ Watch history: 200M+ user × 10K+ content = 2 trillion rows!
  ✓ User activity: play, pause, search, rate events
  ✓ Viewing position: "Filmi haradan davam etdirəm?"
  ✓ Personalization data: recommendation signals
  ✓ A/B test assignments

Cassandra-nın Netflix üçün üstünlükləri:
  ✓ Linear scale: node əlavə et → capacity artır
  ✓ Multi-region: US-East, EU-West, AP-Southeast
  ✓ Always writable: AP system (availability > consistency)
  ✓ Zero downtime deployment
  ✓ Tunable consistency: watch history üçün ONE ok
```

---

## Netflix Cassandra Schema

```cql
-- Watch History (ən çox istifadə olunan)
-- 200M+ user, milyardlarla row
CREATE TABLE watch_history (
    user_id         UUID,
    video_id        UUID,
    watched_at      TIMESTAMP,
    progress_ms     BIGINT,        -- millisaniyə
    total_ms        BIGINT,
    watch_percentage FLOAT,
    completed       BOOLEAN,
    device_type     TEXT,          -- 'tv', 'mobile', 'web', 'tablet'
    country         TEXT,
    PRIMARY KEY (user_id, watched_at, video_id)
) WITH CLUSTERING ORDER BY (watched_at DESC)
  AND default_time_to_live = 7776000;  -- 90 gün

-- Continue Watching (resume position)
-- Query: "Bu filmi nə qədər izlədim?"
CREATE TABLE viewing_position (
    user_id    UUID,
    video_id   UUID,
    profile_id UUID,     -- Netflix multi-profile
    position_ms BIGINT,
    updated_at TIMESTAMP,
    PRIMARY KEY ((user_id, profile_id), video_id)
);

-- User Content Interactions (recommendation signals)
CREATE TABLE user_interactions (
    user_id        UUID,
    interaction_at TIMESTAMP,
    video_id       UUID,
    interaction    TEXT,   -- 'thumbs_up', 'thumbs_down', 'not_interested'
    PRIMARY KEY (user_id, interaction_at)
) WITH CLUSTERING ORDER BY (interaction_at DESC);

-- Push notification state
CREATE TABLE push_notifications (
    user_id         UUID,
    notification_id TIMEUUID,
    type            TEXT,
    payload         TEXT,   -- JSON
    sent_at         TIMESTAMP,
    is_read         BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (user_id, notification_id)
) WITH CLUSTERING ORDER BY (notification_id DESC);
```

---

## MySQL — Billing & Accounts

```sql
-- User accounts (ACID kritik)
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    country         CHAR(2) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Multi-profile (ailə üzvləri)
CREATE TABLE profiles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(50) NOT NULL,
    avatar_url  VARCHAR(500),
    is_kids     BOOLEAN DEFAULT FALSE,
    language    VARCHAR(5) DEFAULT 'en',
    maturity    VARCHAR(10) DEFAULT 'ALL',
    INDEX idx_user (user_id)
);

-- Subscriptions
CREATE TABLE subscriptions (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    plan           ENUM('basic', 'standard', 'premium', 'ads') NOT NULL,
    status         ENUM('active', 'cancelled', 'paused', 'trial') NOT NULL,
    billing_cycle  ENUM('monthly', 'annual') NOT NULL,
    price          DECIMAL(8,2) NOT NULL,
    currency       CHAR(3) NOT NULL,
    started_at     DATETIME NOT NULL,
    renews_at      DATETIME,
    cancelled_at   DATETIME,
    UNIQUE INDEX idx_user_active (user_id, status)
);

-- Payment methods
CREATE TABLE payment_methods (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    type         VARCHAR(20) NOT NULL,   -- 'card', 'paypal'
    last_four    CHAR(4),
    token        VARCHAR(255) NOT NULL,  -- Stripe/Braintree token
    is_default   BOOLEAN DEFAULT FALSE,
    INDEX idx_user (user_id)
);
```

---

## CockroachDB — Global Distributed SQL

```
Netflix 2020-ci ildən CockroachDB istifadə edir

Niyə CockroachDB?
  Yeni region genişlənməsi üçün
  Global ACID transactions
  PostgreSQL uyğun syntax
  
İstifadə sahəsi:
  View count tracking (global)
  Content licensing data
  Cross-region consistent operations

CockroachDB üstünlükləri Netflix üçün:
  ✓ Multi-region ACID
  ✓ Automatic failover
  ✓ PostgreSQL syntax → az migration effort
  ✗ Latency: cross-region commit → ~100-200ms
```

---

## S3 + CloudFront Architecture

```
Video Storage:
  ┌──────────────┐
  │  S3 Buckets  │
  │  per region  │
  │              │
  │  /movies/    │
  │   /480p/     │ ← Codec: H.264
  │   /1080p/    │ ← Codec: H.265/HEVC
  │   /4K/       │ ← Codec: AV1 (yeni)
  │  /subtitles/ │
  │  /audio/     │
  └──────┬───────┘
         │ CDN replication
  ┌──────▼───────────────────────────────┐
  │  CloudFront CDN (190+ PoP locations) │
  │  Edge server-lər global              │
  │  Istanbul user → Frankfurt edge      │
  └──────────────────────────────────────┘

Video Encoding Pipeline:
  Upload → Transcoding farm (ffmpeg)
         → 6 keyfiyyət × 3 codec = 18 fərqli fayl
         → S3-ə yüklə
         → CDN-ə propagate

Adaptive Bitrate Streaming:
  MPEG-DASH / HLS
  Player şəbəkəyə görə keyfiyyət dəyişdirir
  Manifest file: available segments list
```

---

## Netflix Engineering Qərarları

```
1. Cassandra multi-datacenter:
   US-East (master)
   US-West (replica)
   EU-West (replica)
   
   User US-dən EU-yə köçsə: EU replica-dan oxu
   Eventually consistent: 1-2 saniyə gecikmə qəbuledir

2. "Chaos Monkey" — DB resiliency:
   Random Cassandra node-larını öldürür
   Sistem yenə işləyirmi? Test
   "If you can't kill it, you can't run it in production"

3. Data tiering:
   Hot data (son 90 gün): Cassandra
   Warm data (90 gün-2 il): Apache Iceberg (S3-based)
   Cold data (2+ il): S3 Glacier

4. Content catalog MySQL-dən Elasticsearch-ə:
   50M+ content items
   Complex faceted search
   Personalized ranking

5. Zuul API Gateway:
   Netflix-in open-source Gateway
   All traffic → Cassandra + MySQL routing

Açıq Kaynak Töhfələri:
  Cassandra → Netflix major contributor
  Hystrix    → Circuit breaker library
  Eureka     → Service discovery
  Zuul       → API Gateway
  Conductor  → Workflow orchestration
  Hollow     → In-memory dataset
```

---

## Scale Faktları

```
Numbers (2023):
  238M+ paid subscribers
  190+ countries
  Gündə 100M+ viewing hours
  6,000+ Cassandra instances
  Petabytes of video data (S3)
  
  Peak traffic: Stranger Things season 4 → 1.15B hours/28 days
  
Encoding:
  Hər film → 1,200+ encode job
  Shot-based encoding (Shotgun)
  Kontenta görə uyğun encoding seçilir
```

---

## Dərslər

```
Netflix-dən öyrəniləcəklər:

1. Evolutions mümkündür:
   SimpleDB → Cassandra (2011)
   Oracle → MySQL (billing)
   MySQL → CockroachDB (global)
   Dəyişmək mümkündür, amma planlanmalıdır

2. Write-heavy = Cassandra:
   100M+ günlük activity events
   Relational DB-də imkansız

3. Chaos Engineering:
   Resiliency test etmək üçün prod-da failure inject et
   "Netflix Simian Army"

4. Data Mesh:
   Hər team öz data pipeline-nı idarə edir
   Centralized data warehouse əvəzinə

5. Multi-region is hard:
   Clock skew, network partitions
   Conflict resolution strategiyası lazımdır
```
