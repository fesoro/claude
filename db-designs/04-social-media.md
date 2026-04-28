# Social Media App — DB Design (Senior ⭐⭐⭐)

## Tövsiyə olunan DB Stack
```
Core:         PostgreSQL        (users, posts metadata, relationships)
Feed:         Redis             (pre-computed feeds, counters)
Media:        S3 + CDN          (şəkillər, videolar)
Activity:     Cassandra         (notifications, activity log — time-series)
Search:       Elasticsearch     (user search, hashtag search)
Graph (opt):  Neo4j             (follow recommendations, mutual friends)
```

---

## Niyə Bu Stack?

```
PostgreSQL (core data):
  ✓ Users, posts, relationships → ACID lazım
  ✓ Complex queries (mutual friends, suggested follows)
  ✓ Full-text search (hashtags, post search — kiçik miqyas)

Redis (feed & counters):
  ✓ Like/comment sayları → INCR atomic
  ✓ Pre-computed feed → sorted set (score=timestamp)
  ✓ Trending hashtags → sorted set (score=count)
  ✓ Online status → SETEX

Cassandra (activity):
  ✓ Notifications: write-heavy, time-ordered
  ✓ Activity log: hər action qeyd edilir
  ✓ Hot partitions olmaz (user_id partition key)

Elasticsearch:
  ✓ User search: "@username" axtarışı
  ✓ Hashtag search: "#travel" trending
  ✓ Post full-text axtarış
```

---

## Schema Design

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    username        VARCHAR(50) UNIQUE NOT NULL,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    display_name    VARCHAR(100),
    bio             TEXT,
    avatar_url      TEXT,
    website_url     TEXT,
    is_verified     BOOLEAN DEFAULT FALSE,  -- mavi tick
    is_private      BOOLEAN DEFAULT FALSE,  -- özəl hesab
    status          VARCHAR(20) DEFAULT 'active',
    -- Denormalized counters (UPDATE trigger ilə)
    follower_count  INT DEFAULT 0,
    following_count INT DEFAULT 0,
    post_count      INT DEFAULT 0,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_users_username ON users(username);

-- ==================== SOCIAL GRAPH ====================
-- Follow əlaqələri
CREATE TABLE follows (
    follower_id UUID NOT NULL REFERENCES users(id),
    followed_id UUID NOT NULL REFERENCES users(id),
    status      VARCHAR(20) DEFAULT 'accepted',  -- accepted, pending (özəl hesab üçün)
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (follower_id, followed_id),
    CHECK (follower_id != followed_id)
);

CREATE INDEX idx_follows_followed ON follows(followed_id, follower_id);
-- Niyə iki index? "Kim izləyir" (follower_id) + "Kim izlənilir" (followed_id)

-- Block
CREATE TABLE blocks (
    blocker_id UUID NOT NULL REFERENCES users(id),
    blocked_id UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (blocker_id, blocked_id)
);

-- ==================== POSTS ====================
CREATE TABLE posts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id),
    type            VARCHAR(20) NOT NULL,  -- 'photo', 'video', 'carousel', 'text', 'story'
    caption         TEXT,
    location        VARCHAR(255),
    is_archived     BOOLEAN DEFAULT FALSE,
    is_pinned       BOOLEAN DEFAULT FALSE,
    -- Denormalized counters (Redis backup)
    like_count      INT DEFAULT 0,
    comment_count   INT DEFAULT 0,
    share_count     INT DEFAULT 0,
    view_count      BIGINT DEFAULT 0,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_posts_user_created ON posts(user_id, created_at DESC)
    WHERE is_archived = FALSE;

-- Post media (carousel üçün multiple)
CREATE TABLE post_media (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id     UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    media_url   TEXT NOT NULL,
    media_type  VARCHAR(10) NOT NULL,  -- 'image', 'video'
    thumbnail_url TEXT,
    width       INT,
    height      INT,
    duration    INT,    -- video üçün (saniyə)
    sort_order  SMALLINT DEFAULT 0
);

-- Hashtags
CREATE TABLE hashtags (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name       VARCHAR(100) UNIQUE NOT NULL,
    post_count INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE post_hashtags (
    post_id    UUID REFERENCES posts(id) ON DELETE CASCADE,
    hashtag_id UUID REFERENCES hashtags(id),
    PRIMARY KEY (post_id, hashtag_id)
);

CREATE INDEX idx_post_hashtags_hashtag ON post_hashtags(hashtag_id);

-- Mentions
CREATE TABLE post_mentions (
    post_id UUID REFERENCES posts(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id),
    PRIMARY KEY (post_id, user_id)
);

-- ==================== ENGAGEMENT ====================
-- Likes (çox yazma)
CREATE TABLE post_likes (
    post_id    UUID REFERENCES posts(id) ON DELETE CASCADE,
    user_id    UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (post_id, user_id)
);

CREATE INDEX idx_post_likes_user ON post_likes(user_id, created_at DESC);

-- Comments
CREATE TABLE comments (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id     UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id     UUID NOT NULL REFERENCES users(id),
    parent_id   UUID REFERENCES comments(id),  -- nested reply
    body        TEXT NOT NULL,
    like_count  INT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ  -- soft delete
);

CREATE INDEX idx_comments_post ON comments(post_id, created_at)
    WHERE deleted_at IS NULL AND parent_id IS NULL;
CREATE INDEX idx_comments_parent ON comments(parent_id, created_at)
    WHERE deleted_at IS NULL;

-- ==================== STORIES ====================
CREATE TABLE stories (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     UUID NOT NULL REFERENCES users(id),
    media_url   TEXT NOT NULL,
    media_type  VARCHAR(10) NOT NULL,
    duration    INT DEFAULT 5,   -- saniyə
    expires_at  TIMESTAMPTZ NOT NULL,  -- 24 saat sonra
    view_count  INT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Partial index: yalnız aktiv stories
CREATE INDEX idx_stories_active ON stories(user_id, created_at DESC)
    WHERE expires_at > NOW();

CREATE TABLE story_views (
    story_id   UUID REFERENCES stories(id) ON DELETE CASCADE,
    viewer_id  UUID REFERENCES users(id),
    viewed_at  TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (story_id, viewer_id)
);
```

---

## Cassandra: Notifications & Activity

```cql
-- Notifications (write-heavy, per-user)
CREATE TABLE notifications (
    user_id         UUID,
    notification_id TIMEUUID,
    type            TEXT,    -- 'like', 'comment', 'follow', 'mention'
    actor_id        UUID,
    resource_type   TEXT,    -- 'post', 'comment'
    resource_id     UUID,
    content         TEXT,
    is_read         BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP,
    PRIMARY KEY (user_id, notification_id)
) WITH CLUSTERING ORDER BY (notification_id DESC);

-- Activity log (analytics üçün)
CREATE TABLE user_activity (
    user_id     UUID,
    activity_at TIMESTAMP,
    action      TEXT,   -- 'view_post', 'like', 'comment', 'search'
    target_id   UUID,
    metadata    TEXT,   -- JSON
    PRIMARY KEY (user_id, activity_at)
) WITH CLUSTERING ORDER BY (activity_at DESC)
  AND default_time_to_live = 7776000;  -- 90 gün
```

---

## Redis: Feed, Counters, Trending

```
# User feed (Sorted Set — score=timestamp)
ZADD feed:{user_id} {timestamp} {post_id}
ZREVRANGE feed:{user_id} 0 19        -- ilk 20 post
ZCARD feed:{user_id}                 -- feed size

# Like counter (atomic)
INCR likes:{post_id}
DECR likes:{post_id}
GET  likes:{post_id}

# Like check (O(1))
SADD liked:{user_id} {post_id}
SISMEMBER liked:{user_id} {post_id}  -- liked?

# Trending hashtags (Sorted Set — score=usage_count)
ZINCRBY trending:hashtags 1 "travel"
ZREVRANGE trending:hashtags 0 9      -- top 10

# Online status
SETEX online:{user_id} 300 1         -- 5 dəqiqə

# Unread notification count
INCR notif:unread:{user_id}
GET  notif:unread:{user_id}
DEL  notif:unread:{user_id}          -- oxuduqda sıfırla
```

---

## Kritik Dizayn Qərarları

```
1. Denormalized counters (like_count, follower_count):
   Problemi: SELECT COUNT(*) FROM post_likes WHERE post_id = ? → yavaş
   Həll: Denormalized counter + Redis backup
   Trade-off: eventual consistency (counter yanlış ola bilər ±1)
   
   Update strategy:
   - Redis-də INCR/DECR (atomic, sürətli)
   - Async: DB-yə sync (batch update hər 1 dəqiqə)
   - Restart sonrası: DB-dən Redis-ə seed

2. Fan-out on Write (normal users):
   Follow etdiyin user post yazdı → sənin feed-inə əlavə edilir
   Redis: ZADD feed:{follower_id} {ts} {post_id}
   Async (Kafka worker)

3. Fan-out on Read (celebrities - 1M+ follower):
   Hybrid approach (topik 148)
   Normal: pre-computed Redis feed
   Celebrity: real-time merge

4. Soft delete:
   stories.deleted_at, comments.deleted_at
   Fiziki silmə yoxdur → referential integrity
   Partial index: WHERE deleted_at IS NULL

5. Story TTL:
   expires_at = created_at + 24 saat
   Partial index: WHERE expires_at > NOW()
   DB-dən Cassandra TTL-ə köçürmək alternativdir
```

---

## Best Practices

```
✓ Follow relationship üçün iki composite index
  (follower_id, followed_id) + (followed_id, follower_id)
✓ Like check üçün Redis SET (O(1) lookup)
✓ Counter sync: Redis → DB (batch, async)
✓ Hashtag denorm counter: post yazılarkən UPDATE
✓ Feed-i Redis-də saxla, post məzmununu DB-dən lazy yüklə
✓ Media S3-də, DB-də URL array (JSONB)
✓ Notification-lar Cassandra-da (write-heavy, time-series)

Anti-patterns:
✗ SELECT COUNT(*) FROM likes hər dəfə
✗ Like/follow JOIN-i feed sorğusunda
✗ Feed-i hər dəfə DB-dən hesablamaq
✗ Büyük sosial graph-ı relational-da saxlamaq (Neo4j daha uyğun)
```

---

## Tanınmış Sistem Nümunəsi

```
Instagram:
  PostgreSQL       → users, posts, relationships
  Cassandra        → feed, notifications, activity
  Redis            → counts, sessions, feed cache
  S3               → media
  Memcached        → query cache
  
  "Instagram at scale" blog (2013):
  PostgreSQL şaquli artımın limitinə çatdıqda
  Cassandra-ya keçiş

Twitter:
  MySQL (Manhattan) → tweets saxlama
  Redis             → timeline cache
  FlockDB (graph)   → follow relationships

TikTok:
  MySQL + Aurora    → user data
  TiDB              → activity data (NewSQL)
  Lindorm (Alibaba) → content metadata
```

---

## Content Moderation Queue

```
Problem: Harmful content (hate speech, nudity, violence, spam)

PostgreSQL:
CREATE TABLE moderation_queue (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    content_id  BIGINT NOT NULL,
    content_type ENUM('post', 'comment', 'story', 'reel') NOT NULL,
    
    -- Why flagged
    flag_source ENUM('user_report', 'auto_ml', 'keyword', 'hash_match'),
    flag_reason VARCHAR(100),  -- 'nudity', 'hate_speech', 'spam', 'violence'
    
    -- ML score (0-1)
    ml_score    NUMERIC(4,3),
    
    -- Assignment
    assigned_to BIGINT REFERENCES moderators(id),
    priority    SMALLINT DEFAULT 5,  -- 1=highest
    
    -- Decision
    status      ENUM('pending', 'reviewing', 'approved',
                     'removed', 'escalated') DEFAULT 'pending',
    decision_reason TEXT,
    decided_at  TIMESTAMPTZ,
    
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_pending  (status, priority DESC, created_at ASC)
);

-- User reports
CREATE TABLE content_reports (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    reporter_id BIGINT NOT NULL,
    content_id  BIGINT NOT NULL,
    content_type VARCHAR(20),
    reason      VARCHAR(50) NOT NULL,
    details     TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

Auto-moderation pipeline:
  Post created → Kafka → ML classifier
  Score > 0.9:  auto-remove (high confidence)
  Score 0.6-0.9: human review queue
  Score < 0.6:  publish (low risk)
  
  Hash matching:
  PhotoDNA (Microsoft): known CSAM hashes
  ImageHash: near-duplicate detection
```

---

## Shadowban Logic

```
Shadowban = kullanıcı görmədən içeriğini gizlə

PostgreSQL:
ALTER TABLE users ADD COLUMN shadowban_level SMALLINT DEFAULT 0;
-- 0: normal, 1: reduced reach, 2: own-eyes only, 3: full shadow

Implementation:
  Level 1 (reduced reach):
    Feed fan-out: skip this user's posts for non-followers
    Search: downrank
    
  Level 2 (own-eyes only):
    Posts only visible to self
    
  Level 3 (full shadow):
    Posts not indexed
    Comments hidden to others
    No notifications triggered

History (audit):
CREATE TABLE shadowban_log (
    user_id     BIGINT NOT NULL,
    old_level   SMALLINT,
    new_level   SMALLINT,
    reason      TEXT,
    applied_by  BIGINT,  -- system or moderator
    applied_at  TIMESTAMPTZ DEFAULT NOW()
);

Triggers:
  Spam behavior → auto shadowban level 1
  Multiple violations → escalate
  Appeal → human review
  
Redis flag for speed:
  SET shadowban:{user_id} {level} EX 86400
  Check in feed generation: skip level 2+ users
```

---

## Algorithm Feed vs Chronological

```
Two feed modes:

1. Chronological ("Following" feed):
   Simple: ORDER BY posted_at DESC
   WHERE author_id IN (following list)
   
   Redis pre-computed:
   ZADD feed:following:{user_id} {timestamp} {post_id}

2. Algorithm feed ("For You"):
   Ranking factors:
   - Engagement probability (like, comment, share)
   - Relationship strength (interaction history)
   - Content freshness
   - Content diversity
   - Negative signals (hide, not interested)
   
   ML model:
   Score = w1*engagement + w2*relationship + w3*freshness + ...
   
   Two-stage:
   Stage 1 (Retrieval):  candidate pool from Cassandra (fast)
   Stage 2 (Ranking):    ML model scores 200-500 candidates
   Stage 3 (Serving):    top 20, diversity check
   
PostgreSQL (user preference):
  ALTER TABLE users ADD COLUMN feed_mode ENUM('algorithm', 'chronological');
  
  "Instagram 2022: Added chronological feed back"
  Users can switch between modes
```
