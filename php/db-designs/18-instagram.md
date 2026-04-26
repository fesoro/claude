# Instagram — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                   Instagram Database Stack                       │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Users, posts, media metadata (core data) │
│ Cassandra            │ Feed, notifications, activity log        │
│ Redis                │ Feed cache, counters, session            │
│ Amazon S3            │ Photos, videos (exabytes)                │
│ Memcached            │ Application-level cache (L2)             │
│ Elasticsearch        │ Search (users, hashtags, places)         │
│ Apache Kafka         │ Event streaming                          │
│ Hadoop/Hive          │ Analytics (offline)                      │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Tarixi — PostgreSQL-dən Başladı

```
2010 (Launch):
  PostgreSQL tək DB
  2 engineers, 1 server
  
  İlk həftə: 25,000 user
  İlk il: 14M user
  
  "Move fast and don't break things" — Mike Krieger

2011-2012 (Scale Problem):
  PostgreSQL vertical scale limiti
  Sharding başladı
  Django + PostgreSQL → multiple shards

2012 (Facebook acquisition):
  Facebook infrastructure-ına keçid başladı
  TAO (social graph) → follow relationships
  Haystack (photo storage) → S3 əvəzinə

2013-sonra:
  Cassandra → feed, notifications
  Memcached L2 cache
  Multiple PostgreSQL shards
```

---

## PostgreSQL Sharding Strategiyası

```
Instagram 2012: Logical Sharding
  Database → 1000 logical shard
  Physical server: N server, her biri M shard
  User-based sharding: user_id % 1000

  user_id = 123456789
  shard   = 123456789 % 1000 = 789
  server  = shard_to_server_map[789]

Niyə bu?
  Shard sayı sabit (1000), server sayı artırıla bilər
  Rebalancing mümkün
  
Schema across shards:
  Hər shard: users, posts, follows, likes cədvəlləri
  Cross-shard query: application layer-da merge
  
Instagram-ın açıq kaynak PgBouncer config:
  Connection pooling (1000+ connection problemi)
```

---

## Schema Design

```sql
-- ==================== USERS ====================
-- Instagram UUID formatı: 64-bit integer (custom)
-- id = timestamp_41bits + shard_id_13bits + sequence_10bits

CREATE TABLE users (
    id            BIGINT PRIMARY KEY,   -- custom UUID (sharding friendly)
    username      VARCHAR(30) UNIQUE NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    phone         VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    bio           VARCHAR(150),
    website       VARCHAR(200),
    full_name     VARCHAR(150),
    profile_pic_url TEXT,
    is_private    BOOLEAN DEFAULT FALSE,
    is_verified   BOOLEAN DEFAULT FALSE,  -- mavi tick
    status        VARCHAR(20) DEFAULT 'active',
    
    -- Denormalized counters (trigger ilə)
    follower_count  INT DEFAULT 0,
    following_count INT DEFAULT 0,
    post_count      INT DEFAULT 0,
    
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== POSTS ====================
CREATE TABLE media (
    id            BIGINT PRIMARY KEY,   -- custom UUID
    user_id       BIGINT NOT NULL,
    type          VARCHAR(10) NOT NULL,  -- 'photo', 'video', 'carousel'
    caption       VARCHAR(2200),
    location_id   BIGINT,
    
    -- Engagement counters (denormalized)
    like_count    INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    view_count    BIGINT DEFAULT 0,
    
    -- Status
    status        VARCHAR(20) DEFAULT 'active',  -- active, archived, deleted
    
    -- Metadata
    filter        VARCHAR(50),
    usertags      BIGINT[],     -- @mention edilən user ID-ləri
    
    taken_at      TIMESTAMPTZ,  -- şəklin çəkildiyi tarix (EXIF)
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- Media assets (carousel üçün multiple)
CREATE TABLE media_assets (
    id         BIGINT PRIMARY KEY,
    media_id   BIGINT NOT NULL,
    type       VARCHAR(10) NOT NULL,  -- 'image', 'video'
    
    -- S3 keys (fərqli ölçülər)
    original_key VARCHAR(500),
    thumbnail_key VARCHAR(500),
    
    -- Metadata
    width      INT,
    height     INT,
    duration   INT,        -- video üçün
    size_bytes BIGINT,
    sort_order SMALLINT DEFAULT 0
);

-- ==================== SOCIAL GRAPH ====================
-- Instagram-ın ən kritik cədvəli
-- 2019: 1 milyard istifadəçi × ortalama 200 follow = 200 milyard row!

CREATE TABLE follows (
    follower_id BIGINT NOT NULL,
    followed_id BIGINT NOT NULL,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (follower_id, followed_id)
);

-- İki istiqamət üçün iki index
CREATE INDEX idx_follows_follower ON follows(follower_id);
CREATE INDEX idx_follows_followed ON follows(followed_id);

-- Özəl hesab follow request
CREATE TABLE follow_requests (
    requester_id BIGINT NOT NULL,
    target_id    BIGINT NOT NULL,
    status       VARCHAR(20) DEFAULT 'pending',  -- pending, approved, declined
    created_at   TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (requester_id, target_id)
);

-- ==================== ENGAGEMENT ====================
CREATE TABLE likes (
    media_id   BIGINT NOT NULL,
    user_id    BIGINT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (media_id, user_id)
);

-- User-ın bəyəndiyi postlar (reverse lookup)
CREATE INDEX idx_likes_user ON likes(user_id, created_at DESC);

CREATE TABLE comments (
    id         BIGINT PRIMARY KEY,
    media_id   BIGINT NOT NULL,
    user_id    BIGINT NOT NULL,
    parent_id  BIGINT,     -- reply
    body       VARCHAR(2200) NOT NULL,
    like_count INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- ==================== HASHTAGS ====================
CREATE TABLE hashtags (
    id         BIGINT PRIMARY KEY,
    name       VARCHAR(500) UNIQUE NOT NULL,
    post_count BIGINT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE media_hashtags (
    media_id   BIGINT NOT NULL,
    hashtag_id BIGINT NOT NULL,
    PRIMARY KEY (media_id, hashtag_id)
);

CREATE INDEX idx_media_hashtags_tag ON media_hashtags(hashtag_id);

-- ==================== LOCATIONS ====================
CREATE TABLE locations (
    id        BIGINT PRIMARY KEY,
    name      VARCHAR(255) NOT NULL,
    latitude  NUMERIC(9,6),
    longitude NUMERIC(9,6),
    city      VARCHAR(100),
    country   CHAR(2)
);
```

---

## Cassandra: Feed & Notifications

```cql
-- User Feed (pre-computed)
-- "Sənin feed-in" — follow etdiklərinin postları
CREATE TABLE user_feed (
    user_id     BIGINT,
    post_time   TIMESTAMP,
    media_id    BIGINT,
    author_id   BIGINT,
    PRIMARY KEY (user_id, post_time, media_id)
) WITH CLUSTERING ORDER BY (post_time DESC);
-- Max 1000 post per user (ALLOW FILTERING olmadan)

-- Notifications
CREATE TABLE notifications (
    user_id         BIGINT,
    notification_id TIMEUUID,
    type            TEXT,   -- 'like', 'comment', 'follow', 'mention', 'tagged'
    actor_id        BIGINT,
    resource_id     BIGINT,
    is_read         BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (user_id, notification_id)
) WITH CLUSTERING ORDER BY (notification_id DESC);

-- Stories (24 saatlıq TTL)
CREATE TABLE stories (
    user_id    BIGINT,
    story_id   TIMEUUID,
    media_key  TEXT,
    type       TEXT,  -- 'image', 'video'
    PRIMARY KEY (user_id, story_id)
) WITH CLUSTERING ORDER BY (story_id DESC)
  AND default_time_to_live = 86400;  -- 24 saat
```

---

## Redis: Counters & Cache

```
# Like counter (atomik)
INCR likes:{media_id}
GET  likes:{media_id}

# Like check (O(1)) — "Bu postu bəyənmişəmmi?"
SADD user:likes:{user_id} {media_id}
SISMEMBER user:likes:{user_id} {media_id}

# Follower/Following count cache
HSET user:stats:{user_id} followers 15234 following 892 posts 347
HGET user:stats:{user_id} followers

# Session
SET session:{token} {user_json} EX 7776000  -- 90 gün

# Trending hashtags
ZINCRBY hashtags:trending 1 "travel"
ZREVRANGE hashtags:trending 0 9  -- top 10

# Stories görüntüləmə
SADD story:viewers:{story_id} {viewer_id}
SMEMBERS story:viewers:{story_id}

# Feed refresh cache (pre-computed feed key-ləri)
ZADD feed:{user_id} {timestamp} {media_id}
ZREVRANGE feed:{user_id} 0 19
```

---

## Custom UUID: Instagram's Unique ID

```
Instagram 64-bit ID generation (Postgres function):

Struktur:
  41 bits: millisecond timestamp (69 il yetər)
  13 bits: shard ID (8192 shard)
  10 bits: sequence number per ms (1024/ms per shard)

CREATE OR REPLACE FUNCTION instagram_id()
RETURNS BIGINT AS $$
DECLARE
    epoch  BIGINT := 1314220021721;  -- Custom epoch
    seq    BIGINT;
    now_ms BIGINT;
    shard  BIGINT := 5;  -- Bu server-in shard ID-si
    result BIGINT;
BEGIN
    SELECT nextval('instagram_id_seq') % 1024 INTO seq;
    SELECT FLOOR(EXTRACT(EPOCH FROM NOW()) * 1000) INTO now_ms;
    result := (now_ms - epoch) << 23;
    result := result | (shard << 10);
    result := result | seq;
    RETURN result;
END;
$$ LANGUAGE plpgsql;

Üstünlüklər:
  ✓ Sharding-friendly (shard ID embedded)
  ✓ Time-sortable (chronological order)
  ✓ 64-bit integer (UUID-dən compact)
  ✓ Application-level generation (no central coordinator)
```

---

## Fan-out Strategy

```
Instagram-ın həll yolu (Hybrid):

Normal user (< 1M followers):
  Post yayımlandıqda → Kafka event
  Fan-out workers → follower feed-lərinə Cassandra WRITE
  Pre-computed feed

Celebrity (1M+ followers):
  Fan-out çox bahalıdır (1M write)
  Feed oxunanda: celebrity-nin son postları real-time əlavə edilir
  
  user_feed (Cassandra) + celebrity posts (real-time merge)

Kylie Jenner problemi (2018):
  350M+ follower
  1 post → 350M+ write → sistem yavaşladı
  Həll: celebrity = pull-based fan-out

Threshold:
  Instagram: ~1M follower-dan sonra pull-based
```

---

## Scale Faktları

```
Numbers (2023):
  2+ milyard aktiv istifadəçi
  100M+ posts per day
  500M+ stories per day
  4.2B+ likes per day
  
  Infrastructure:
  PostgreSQL: 10,000+ servers
  MySQL (Facebook internal): shared infra
  Cassandra: multiple large clusters
  
Engineering Team 2012 (acquisition):
  13 engineers
  50M users
  "Yalnız 13 engineer ilə bu scale"
```

---

## Dərslər

```
Instagram-dan öyrəniləcəklər:

1. PostgreSQL-dən başlamaq mümkündür:
   2010-da 1 PostgreSQL server
   2012-da sharding başladı (2 il sonra)
   Premature optimization lazım deyil

2. Custom UUID → sharding:
   Auto-increment shard-larda işləmir
   Instagram's 64-bit ID → sharding friendly

3. Denormalized counters:
   like_count, follower_count postgres-da
   Redis backup + batch sync
   "Real-time accuracy" > "Exact accuracy"

4. Hybrid fan-out:
   Bütün istifadəçilər üçün eyni strategiya yanlışdır
   Celebrity threshold → pull-based fan-out

5. Photo storage evolution:
   S3 → Haystack (Facebook internal)
   Haystack: small files üçün optimizə edilmiş
   Küçük faylları bir böyük faylda saxla → disk seek azal
```
