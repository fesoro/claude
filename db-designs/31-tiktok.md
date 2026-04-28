# TikTok — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     TikTok Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL / TiDB         │ User accounts, video metadata            │
│ Apache Cassandra     │ Comments, activity logs                  │
│ ClickHouse           │ Real-time analytics (views, likes)       │
│ ByteHouse            │ ByteDance's ClickHouse fork (analytics)  │
│ Elasticsearch        │ Video/user search                        │
│ Redis                │ Feed cache, sessions, counters           │
│ Apache Kafka         │ Event streaming                          │
│ HDFS + Spark         │ ML training data                         │
│ Custom CDN           │ Video distribution (ByteDance's own CDN) │
└──────────────────────┴──────────────────────────────────────────┘

ByteDance (TikTok-un sahibi):
  ClickHouse heavy user
  Custom forks + contributions
  "ClickHouse is our core analytics DB"
```

---

## TiDB: ByteDance-in MySQL Alternative

```
TiDB = Distributed SQL (MySQL compatible)
  Creator: PingCAP (China-based)
  Inspired by Google Spanner + F1

ByteDance TiDB istifadəsi:
  MySQL → TiDB migration
  Reason: horizontal scale + ACID + MySQL syntax
  
TiDB arxitekturası:
  TiDB layer: SQL parser, query planner
  TiKV layer: distributed KV storage (RocksDB-based)
  PD (Placement Driver): metadata, scheduling
  
Üstünlüklər:
  ✓ MySQL protocol compatible (app code dəyişmir)
  ✓ Horizontal scale (like Cassandra)
  ✓ ACID transactions (like PostgreSQL)
  ✓ Online DDL (ALTER TABLE without downtime)
  
ByteDance scale:
  Largest TiDB deployment in the world
  Thousands of TiDB nodes
  Petabytes of data
```

---

## ClickHouse: Real-Time Analytics

```
ClickHouse = columnar OLAP database
  Creator: Yandex (2016, open-source)
  
TikTok / ByteDance major use:
  Video view counts
  Creator analytics dashboard
  Ad performance metrics
  Real-time trending
  
ClickHouse-un üstünlüyü:
  Column-oriented → analytics sürətli
  Vectorized execution → SIMD instructions
  Compression → 10x less storage
  
  INSERT: 100M rows/second
  SELECT: 1B rows in seconds
  
ByteHouse:
  ByteDance-in ClickHouse fork-u
  Extra features: multi-tenant, cloud-native
  
Example queries:
  -- "Bu videonu bu həftə neçə nəfər izlədi?"
  SELECT
    toDate(viewed_at) AS day,
    uniq(viewer_id)   AS unique_viewers,
    count()           AS total_views
  FROM video_events
  WHERE video_id = 'xxx' AND viewed_at >= today() - 7
  GROUP BY day
  ORDER BY day;
  
  -- Trending (son 1 saatda ən çox görülən)
  SELECT video_id, count() as views
  FROM video_events
  WHERE viewed_at >= now() - INTERVAL 1 HOUR
  GROUP BY video_id
  ORDER BY views DESC
  LIMIT 100;
```

---

## Schema Design

```sql
-- ==================== USERS ====================
-- MySQL / TiDB
CREATE TABLE users (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    username        VARCHAR(24) UNIQUE NOT NULL,  -- @handle
    
    -- Profile
    nickname        VARCHAR(30),
    bio             VARCHAR(80),
    avatar_url      TEXT,
    
    -- Stats (denormalized)
    following_count INT DEFAULT 0,
    follower_count  INT DEFAULT 0,
    like_count      BIGINT DEFAULT 0,
    video_count     INT DEFAULT 0,
    
    -- Verification
    is_verified     BOOLEAN DEFAULT FALSE,
    
    -- Account status
    is_private      BOOLEAN DEFAULT FALSE,
    
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== VIDEOS ====================
CREATE TABLE videos (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id         BIGINT NOT NULL,
    
    -- Content
    description     VARCHAR(2200),
    
    -- Duration
    duration_sec    SMALLINT NOT NULL,
    
    -- Storage
    video_url       TEXT NOT NULL,    -- CDN URL
    cover_url       TEXT,             -- thumbnail
    
    -- Music
    sound_id        BIGINT,           -- background audio
    sound_name      VARCHAR(100),
    
    -- Status
    status          ENUM('processing', 'active', 'deleted', 'reviewing') DEFAULT 'processing',
    
    -- Privacy
    privacy_level   ENUM('public', 'friends', 'private') DEFAULT 'public',
    allow_duet      BOOLEAN DEFAULT TRUE,
    allow_stitch    BOOLEAN DEFAULT TRUE,
    allow_comments  BOOLEAN DEFAULT TRUE,
    
    -- Stats (approximate — exact in ClickHouse)
    view_count      BIGINT DEFAULT 0,
    like_count      INT DEFAULT 0,
    comment_count   INT DEFAULT 0,
    share_count     INT DEFAULT 0,
    
    -- Tags
    hashtags        JSON,
    
    -- Coordinates
    lat             DECIMAL(9,6),
    lng             DECIMAL(9,6),
    
    published_at    DATETIME,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user    (user_id, published_at DESC),
    INDEX idx_status  (status, published_at DESC)
) ENGINE=InnoDB;

-- ==================== SOCIAL GRAPH ====================
CREATE TABLE follows (
    follower_id BIGINT NOT NULL,
    followed_id BIGINT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    INDEX idx_followed (followed_id, created_at DESC)
) ENGINE=InnoDB;

-- ==================== LIKES ====================
CREATE TABLE likes (
    user_id    BIGINT NOT NULL,
    video_id   BIGINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, video_id),
    INDEX idx_video (video_id, created_at DESC)
) ENGINE=InnoDB;
```

---

## ClickHouse Events Tables

```sql
-- ClickHouse: video events (denormalized for analytics)
CREATE TABLE video_events (
    event_time    DateTime,
    event_date    Date MATERIALIZED toDate(event_time),
    
    event_type    Enum8('view'=1, 'like'=2, 'share'=3, 'comment'=4,
                         'follow_from_video'=5, 'duet'=6),
    
    video_id      Int64,
    user_id       Int64,   -- viewer/actor
    creator_id    Int64,   -- video creator
    
    -- View specific
    watch_duration_ms  Int32,
    is_completed       UInt8,   -- watched >80%?
    
    -- Device context
    platform      LowCardinality(String),   -- 'ios', 'android', 'web'
    country       LowCardinality(FixedString(2)),
    
    -- Source
    source        LowCardinality(String),  -- 'for_you', 'following', 'search', 'profile'
    
) ENGINE = MergeTree()
  PARTITION BY (event_date)
  ORDER BY (video_id, event_time)
  TTL event_date + INTERVAL 90 DAY;

-- Materialized view: hourly video stats
CREATE MATERIALIZED VIEW video_hourly_stats
ENGINE = SummingMergeTree()
ORDER BY (video_id, hour)
AS SELECT
    video_id,
    toStartOfHour(event_time) AS hour,
    countIf(event_type = 'view') AS views,
    countIf(event_type = 'like') AS likes,
    countIf(event_type = 'share') AS shares,
    sumIf(watch_duration_ms, event_type = 'view') AS total_watch_ms
FROM video_events
GROUP BY video_id, hour;
```

---

## For You Page (FYP): Recommendation Engine

```
TikTok-un ən güclü silahı: FYP algoritması

Siqnallar:
  Strong positive:
  - Video izləndi (>80% watch rate) ← ən güclü siqnal
  - Like edildi
  - Comment yazıldı
  - Paylaşıldı
  - Profil ziyarət edildi
  - Replay edildi
  
  Strong negative:
  - Erkən skip (ilk 2 saniyədə)
  - "Not interested" seçildi
  - Creator blocked
  
  Neutral:
  - Video izləndi (50-80%)

ML Model:
  Input: {user features} + {video features} + {context}
  Output: engagement probability score
  
  User features (Bigtable/Redis):
    - Recent watch history (embeddings)
    - Like patterns
    - Follow graph signals
    
  Video features:
    - Audio features
    - Visual features (computer vision)
    - Text (description, hashtags)
    - Early engagement signals (first 1 hour stats)
    
  Two-tower model:
    User tower → user embedding
    Video tower → video embedding
    Dot product → relevance score

FYP generation:
  Candidate retrieval → Scoring → Diversity filtering → Serving
  
  1. Candidate pool: 500-1000 videos
     (collaborative filtering, trending, geo, new creators)
  2. Scoring: ML model scores each
  3. Diversity: avoid 5 consecutive same-creator videos
  4. Serve: top 10-20 to client
```

---

## Live Streaming DB Design

```
TikTok LIVE: real-time video broadcast

MySQL / TiDB:
CREATE TABLE live_streams (
    id          BIGINT PRIMARY KEY AUTO_INCREMENT,
    creator_id  BIGINT NOT NULL,
    title       VARCHAR(100),
    status      ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
    started_at  DATETIME,
    ended_at    DATETIME,
    peak_viewers INT DEFAULT 0,
    total_viewers BIGINT DEFAULT 0,
    
    -- Monetization
    gifts_received BIGINT DEFAULT 0,  -- TikTok coins
    
    stream_key  VARCHAR(100) UNIQUE,  -- RTMP stream key
    playback_url TEXT   -- HLS URL for viewers
);

-- Live comments (high volume)
-- Cassandra (not MySQL — too much write):
CREATE TABLE live_comments (
    stream_id   BIGINT,
    comment_id  TIMEUUID,
    user_id     BIGINT,
    content     TEXT,
    PRIMARY KEY (stream_id, comment_id)
) WITH CLUSTERING ORDER BY (comment_id DESC)
  AND default_time_to_live = 86400;  -- 24 saat

-- Redis: current viewer count
INCR live:viewers:{stream_id}
DECR live:viewers:{stream_id}
GET  live:viewers:{stream_id}

-- Redis: gifts stream (real-time animation)
PUBLISH live:gifts:{stream_id} {gift_json}
```

---

## Scale Faktları

```
Numbers (2023):
  1B+ monthly active users
  150M+ daily active users (US alone)
  ~3.5M videos uploaded per day
  
  Engagement:
  Average session: 95 minutes/day
  Average video watch: 17 seconds
  
  ByteDance (parent company):
  50K+ engineers
  ClickHouse: largest deployment globally
  TiDB: largest deployment globally
  
  CDN:
  Custom ByteDance CDN + major CDN partners
  Global PoP: 200+ locations
```

---

## TikTok-dan Öyrəniləcəklər

```
1. Watch time > Likes:
   Core metric: "did they watch to the end?"
   Not binary (like/dislike) but continuous signal
   Better engagement signal

2. ClickHouse for real-time analytics:
   100M+ events/day → sub-second queries
   Materialized views → pre-aggregate
   TTL → auto data lifecycle

3. TiDB for MySQL scale:
   MySQL compatible + horizontal scale
   No application rewrite
   ACID preserved

4. Cold start problem (new creators):
   New video → test on 200-500 random users
   If engagement high → wider distribution
   "Viral potential detection early"

5. FYP = personalization, not social:
   Instagram: people you follow (social graph)
   TikTok: what you like (interest graph)
   Interest graph → better discovery
```
