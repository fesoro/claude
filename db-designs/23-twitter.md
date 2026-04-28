# Twitter/X — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     Twitter Database Stack                       │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL                │ User accounts, tweets (sharded)          │
│ Manhattan            │ Custom distributed KV store (Twitter)    │
│ Apache Cassandra     │ Direct messages, notifications (past)    │
│ Redis                │ Timeline cache, trending, rate limiting  │
│ Memcached            │ Object cache (tweet, user objects)       │
│ Elasticsearch        │ Full-text search, Lucene-based           │
│ Apache Kafka         │ Event streaming, timeline fanout         │
│ Hadoop + HDFS        │ Analytics, ML training data              │
│ Vertica              │ Analytics SQL (columnar)                 │
│ FlockDB              │ Social graph (custom, graph DB)          │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Twitter-in DB Tarixi

```
2006-2008: Ruby on Rails + MySQL
  "The Fail Whale" dövrü
  MySQL tək server → scale problemi
  Hər yeni feature → "over capacity" xətaları

2009-2012: Sharding + Cache
  MySQL sharding (TweetIDs hashed)
  Memcached aggressive caching
  "Sharded Counters" for followers/following
  
  Snowflake ID generation (2010):
    64-bit unique IDs
    Distributed, no central coordinator

2012-2015: Manhattan
  Custom distributed KV store
  Twitter-in öz infrastructure-u
  MySQL-i əvəz etmək üçün
  
2015-sonra: Hybrid
  Manhattan: tweets, timelines
  MySQL: hələ user data üçün
  Kafka: real-time pipeline

2022+: Elon Musk era
  Aggressive infrastructure reduction
  "Half the servers"
  Rebranding: Twitter → X
```

---

## Snowflake: Twitter-in ID Generation

```
Problem:
  Distributed sistem: merkezi auto_increment işləmir
  UUID: string, sort edilmir, böyük (16 bytes)
  
Twitter Snowflake (2010, open-source):
  64-bit integer
  
Struktur:
  ┌──────────────────┬────────────┬────────────────┐
  │ 41 bits          │ 10 bits    │ 12 bits        │
  │ timestamp (ms)   │ machine ID │ sequence       │
  └──────────────────┴────────────┴────────────────┘
  
  - 41 bits timestamp: ~69 il (custom epoch: 2006)
  - 10 bits machine: 1024 machine (5 datacenter × 32 worker)
  - 12 bits sequence: 4096 per ms per machine
  
  = 4096 × 1024 machines = 4M+ IDs per millisecond

Üstünlüklər:
  ✓ Time-sortable (chronological order preserved)
  ✓ No central coordinator
  ✓ 64-bit integer (compact, B-tree friendly)
  ✓ Embeds timestamp (no extra column needed)
  
Nəticə:
  Sentry, Discord, Instagram, LinkedIn... hamı oxşar istifadə edir
  "Snowflake pattern" → industry standard oldu
```

---

## MySQL Schema: Core Data

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id            BIGINT UNSIGNED PRIMARY KEY,  -- Snowflake ID
    username      VARCHAR(15) UNIQUE NOT NULL,   -- @handle (max 15 char)
    display_name  VARCHAR(50) NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    phone         VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    bio           VARCHAR(160),   -- Twitter bio limit
    location      VARCHAR(30),
    website       VARCHAR(100),
    profile_image_url TEXT,
    header_image_url  TEXT,
    
    -- Status
    is_verified   BOOLEAN DEFAULT FALSE,   -- blue/gold checkmark
    is_protected  BOOLEAN DEFAULT FALSE,   -- private account
    status        ENUM('active', 'suspended', 'deactivated') DEFAULT 'active',
    
    -- Denormalized counters (high read, Redis-backed)
    followers_count INT UNSIGNED DEFAULT 0,
    following_count INT UNSIGNED DEFAULT 0,
    tweet_count     INT UNSIGNED DEFAULT 0,
    
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- ==================== TWEETS ====================
-- Sharded by tweet_id % N
CREATE TABLE tweets (
    id            BIGINT UNSIGNED PRIMARY KEY,  -- Snowflake ID
    user_id       BIGINT UNSIGNED NOT NULL,
    
    -- Content
    content       VARCHAR(280) NOT NULL,    -- 280 char limit (originally 140)
    lang          VARCHAR(5),               -- detected language
    
    -- Reply chain
    reply_to_tweet_id   BIGINT UNSIGNED,
    reply_to_user_id    BIGINT UNSIGNED,
    conversation_id     BIGINT UNSIGNED,    -- root tweet ID of thread
    
    -- Retweet/Quote
    retweet_of_id       BIGINT UNSIGNED,
    quoted_tweet_id     BIGINT UNSIGNED,
    
    -- Media
    has_media     BOOLEAN DEFAULT FALSE,
    
    -- Metrics (denormalized for reads)
    retweet_count  INT UNSIGNED DEFAULT 0,
    like_count     INT UNSIGNED DEFAULT 0,
    reply_count    INT UNSIGNED DEFAULT 0,
    quote_count    INT UNSIGNED DEFAULT 0,
    bookmark_count INT UNSIGNED DEFAULT 0,
    
    -- Geo
    coordinates_lat DECIMAL(9,6),
    coordinates_lng DECIMAL(9,6),
    place_id       VARCHAR(20),
    
    source         VARCHAR(100),  -- 'Twitter Web App', 'Twitter for iPhone'
    is_deleted     BOOLEAN DEFAULT FALSE,
    
    posted_at     DATETIME NOT NULL,
    
    INDEX idx_user_time (user_id, posted_at DESC),
    INDEX idx_conversation (conversation_id),
    INDEX idx_reply (reply_to_tweet_id)
) ENGINE=InnoDB;

-- Tweet media
CREATE TABLE tweet_media (
    id          BIGINT UNSIGNED PRIMARY KEY,
    tweet_id    BIGINT UNSIGNED NOT NULL,
    type        ENUM('photo', 'video', 'gif', 'audio') NOT NULL,
    url         TEXT NOT NULL,
    width       SMALLINT,
    height      SMALLINT,
    duration_ms INT,
    sort_order  TINYINT DEFAULT 0,
    INDEX idx_tweet (tweet_id)
) ENGINE=InnoDB;

-- ==================== SOCIAL GRAPH ====================
-- FlockDB (Twitter's graph DB) → MySQL migration
CREATE TABLE follows (
    follower_id BIGINT UNSIGNED NOT NULL,
    followed_id BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    INDEX idx_followed (followed_id, created_at DESC)
) ENGINE=InnoDB;
-- Sharded: shard by follower_id OR followed_id
-- Problem: celebrity → millions of followed rows

-- ==================== LIKES ====================
CREATE TABLE likes (
    tweet_id   BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tweet_id, user_id),
    INDEX idx_user (user_id, created_at DESC)
) ENGINE=InnoDB;

-- ==================== HASHTAGS ====================
CREATE TABLE hashtags (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tag        VARCHAR(139) UNIQUE NOT NULL,
    tweet_count BIGINT DEFAULT 0
);

CREATE TABLE tweet_hashtags (
    tweet_id   BIGINT UNSIGNED NOT NULL,
    hashtag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tweet_id, hashtag_id),
    INDEX idx_hashtag (hashtag_id, tweet_id DESC)
);
```

---

## Manhattan: Twitter-in Custom KV Store

```
Twitter 2014: Manhattan — internal distributed KV store

Niyə?
  MySQL sharding mürəkkəb
  Cassandra eventual consistency Twitter üçün problematik
  Öz sistemi istədi: strong consistency + horizontal scale

Manhattan xüsusiyyətləri:
  ✓ Strongly consistent (Paxos-based)
  ✓ Multi-tenancy (bir cluster, çox use case)
  ✓ Configurable consistency level per operation
  ✓ Built-in TTL support
  ✓ Range queries
  ✓ B-tree based storage

İstifadə sahəsi:
  Tweet storage: tweet_id → tweet data
  User timelines: user_id → [tweet_ids] (sorted)
  Direct Messages
  Notifications

Timeline storage in Manhattan:
  Key:   user_id
  Value: sorted list of tweet_ids (newest first)
         [9876543210, 9876543100, 9876540000, ...]
  Max:   800 entries per user (soft limit)
  
  Fanout yazanda:
    Kuzey yıldızı tweet göndərir
    Bütün follower timeline-larına tweet_id yazılır
    
  Timeline oxuyanda:
    Manhattan-dan tweet_id-lər alınır
    Memcached/Redis-dən tweet object-lər hydrate edilir
```

---

## Redis: Timeline Cache & Trending

```
# Home timeline (pre-computed, hot users)
ZADD timeline:{user_id} {tweet_id_as_score} {tweet_id}
ZREVRANGE timeline:{user_id} 0 99   -- son 100 tweet

# Tweet counter cache
HINCRBY tweet:counters:{tweet_id} likes 1
HINCRBY tweet:counters:{tweet_id} retweets 1
HGETALL tweet:counters:{tweet_id}

# Trending topics (real-time)
ZINCRBY trending:hashtags:{window} 1 "#Python"
ZREVRANGE trending:hashtags:{window} 0 9   -- top 10

# Rate limiting
INCR rate:tweet:{user_id}:{minute}
EXPIRE rate:tweet:{user_id}:{minute} 60
-- Twitter: 300 tweets per 3 hours

# User session
SET session:{token} {user_id} EX 604800   -- 7 gün

# Who to follow (pre-computed recommendations)
SMEMBERS wtf:{user_id}   -- "Who to Follow"
```

---

## Fanout Strategy

```
Twitter-in fan-out problemi:

Normal user (az follower):
  Tweet yayımlandı → Kafka event
  Fanout service → hər follower timeline-ına tweet_id yaz
  Manhattan: write
  
Celebrity problem (Katy Perry: 100M+ follower):
  1 tweet → 100M write → infrastruktur çöksün!
  
Twitter-in həlli:

1. Push model (normal user):
   Tweet → follower timeline-larına YAZ (eager fanout)
   Read: Manhattan-dan al, tez
   
2. Pull model (celebrity/high-follower):
   Tweet timeline-lara yazılmır
   Read zamanı: celebrity-nin son tweetlərini real-time fetch et
   
3. Hybrid (actual):
   User açılanda: pre-computed timeline (Manhattan) + 
                  celebrity real-time posts = merge
   
Threshold:
  Twitter: ~1M+ follower → pull-based fan-out

Lady Gaga problemi (2012):
  Queen of Twitter: 30M followers
  1 tweet → sistem yavaşladı
  → celebrity exception yaradıldı
```

---

## Direct Messages: Cassandra

```cql
-- Twitter DM (Cassandra schema)
-- Twitter 2014-2019: Cassandra for DMs

CREATE TABLE conversations (
    conversation_id  UUID PRIMARY KEY,
    type            TEXT,   -- 'one_to_one', 'group'
    created_at      TIMESTAMP,
    created_by      BIGINT
);

CREATE TABLE conversation_participants (
    conversation_id UUID,
    user_id         BIGINT,
    joined_at       TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id)
);

CREATE TABLE direct_messages (
    conversation_id UUID,
    message_id      TIMEUUID,  -- time-based UUID
    sender_id       BIGINT,
    content         TEXT,
    type            TEXT,   -- 'text', 'media', 'tweet'
    is_deleted      BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (conversation_id, message_id)
) WITH CLUSTERING ORDER BY (message_id DESC);

-- User inbox (which conversations)
CREATE TABLE user_conversations (
    user_id         BIGINT,
    last_message_at TIMESTAMP,
    conversation_id UUID,
    PRIMARY KEY (user_id, last_message_at, conversation_id)
) WITH CLUSTERING ORDER BY (last_message_at DESC);
```

---

## Scale Faktları

```
Numbers (2023):
  ~350M+ monthly active users
  ~500M tweets per day
  ~200B+ impressions per day

Snowflake:
  1.5B+ IDs generated per day (at peak)
  Used across all Twitter services

Infrastructure (pre-Musk):
  ~7,500 employees
  Dozens of data centers
  Kafka: 400B+ events/day
  
Infrastructure (post-Musk 2022-23):
  ~1,500 employees (80% reduction)
  "Half the servers" goal
  Some reliability incidents followed
  
Search:
  Elasticsearch: real-time tweet indexing
  Billions of tweets indexed
  Sub-second search latency
```

---

## Twitter-dən Öyrəniləcəklər

```
1. Snowflake ID pattern:
   Time-sortable 64-bit IDs
   No central coordinator
   Industry standard oldu (Discord, Instagram, ...)

2. Fan-out trade-off:
   Push: write ağır, read asan
   Pull: write asan, read ağır
   Hybrid: celebrity threshold ilə

3. Denormalized counters:
   like_count, retweet_count tweetet-də
   Redis atomic increments + periodic DB sync
   Strong consistency lazım deyil (approximate OK)

4. Timeline as sorted list:
   tweet_id → sortable (Snowflake time-based)
   Manhattan/Redis: sorted set of IDs
   Hydration ayrı step

5. Sharding key seçimi:
   tweets: tweet_id % shards
   follows: follower_id % shards (ya da followed_id)
   Her iki direction lazımdırsa → 2 shard table

6. Conway's Law observation:
   2022 layoff: infrastructure degraded
   "Organization structure → system structure"
```
