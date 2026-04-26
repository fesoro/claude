# Video Streaming App — DB Design (Netflix-style)

## Tövsiyə olunan DB Stack
```
User Accounts:   MySQL / PostgreSQL  (ACID — subscription, billing)
Content Catalog: MySQL + Elasticsearch (film metadata, search)
User Activity:   Cassandra           (watch history — write-heavy, scale)
Recommendations: Cassandra + Spark   (user-content relationships)
Sessions:        Redis               (session, auth token)
Media:           S3 + CDN (CloudFront) (video faylları)
Analytics:       ClickHouse / Redshift (content analytics)
```

---

## Niyə Cassandra Watch History üçün?

```
Problem:
  Netflix: 200M+ istifadəçi, gündə 100M+ seans
  Hər seans: position, progress, completed events
  
  PostgreSQL ilə:
  200M user × 1000 video = 200B row!
  Single node PostgreSQL → imkansız

Cassandra üstünlükləri:
  ✓ Horizontal scale: node əlavə etmək asandır
  ✓ Write-optimized (LSM tree)
  ✓ Partition key = user_id → bir istifadəçinin bütün history eyni node
  ✓ TTL: köhnə activity-ni avtomatik sil
  ✓ Tunable consistency: eventual ok (izləmə tarixi üçün)

Query pattern:
  "User A-nın son izlədikləri" → user_id partition key
  Cassandra bu query-ni bir partition-a gəlir
```

---

## Schema Design

```sql
-- ==================== USER ACCOUNTS (MySQL/PostgreSQL) ====================
CREATE TABLE users (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email             VARCHAR(255) UNIQUE NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    country           CHAR(2) NOT NULL,
    preferred_language VARCHAR(5) DEFAULT 'az',
    plan_id           UUID REFERENCES subscription_plans(id),
    status            VARCHAR(20) DEFAULT 'active',
    created_at        TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE subscription_plans (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name         VARCHAR(50) NOT NULL,    -- 'Basic', 'Standard', 'Premium'
    monthly_price NUMERIC(8,2) NOT NULL,
    max_screens  SMALLINT NOT NULL,       -- paralel yayım sayı
    max_quality  VARCHAR(20) NOT NULL,    -- '480p', '1080p', '4K'
    has_downloads BOOLEAN DEFAULT FALSE,
    currency     CHAR(3) DEFAULT 'USD'
);

CREATE TABLE subscriptions (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id        UUID NOT NULL REFERENCES users(id),
    plan_id        UUID NOT NULL REFERENCES subscription_plans(id),
    status         VARCHAR(20) NOT NULL,  -- active, cancelled, paused, trial
    started_at     TIMESTAMPTZ NOT NULL,
    expires_at     TIMESTAMPTZ,
    cancelled_at   TIMESTAMPTZ,
    payment_method VARCHAR(30),
    created_at     TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CONTENT CATALOG (MySQL/PostgreSQL) ====================
CREATE TABLE content (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type           VARCHAR(20) NOT NULL,   -- 'movie', 'series', 'documentary', 'short'
    title          VARCHAR(500) NOT NULL,
    original_title VARCHAR(500),
    slug           VARCHAR(500) UNIQUE NOT NULL,
    description    TEXT,
    tagline        TEXT,
    
    -- Klassifikasiya
    release_year   SMALLINT,
    maturity_rating VARCHAR(10),  -- 'G', 'PG', 'PG-13', 'R', '18+'
    duration_min   INT,            -- seriyal üçün NULL
    country        CHAR(2),
    
    -- Media
    poster_url     TEXT,
    backdrop_url   TEXT,
    trailer_url    TEXT,
    logo_url       TEXT,
    
    -- Reytinq
    imdb_id        VARCHAR(20),
    imdb_rating    NUMERIC(3,1),
    our_rating     NUMERIC(3,1),
    vote_count     INT DEFAULT 0,
    
    -- Status
    status         VARCHAR(20) DEFAULT 'draft',  -- draft, published, removed
    available_from TIMESTAMPTZ,
    available_until TIMESTAMPTZ,
    
    -- Metadata
    tags           JSONB DEFAULT '[]',  -- ["action", "thriller"]
    cast_ids       JSONB DEFAULT '[]',
    
    -- Full-text search
    search_vector  TSVECTOR,
    
    created_at     TIMESTAMPTZ DEFAULT NOW(),
    updated_at     TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_content_type_status ON content(type, status, available_from DESC)
    WHERE status = 'published';
CREATE INDEX idx_content_search ON content USING GIN (search_vector);
CREATE INDEX idx_content_tags ON content USING GIN (tags);

-- Series → Seasons → Episodes hierarchy
CREATE TABLE seasons (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    series_id   UUID NOT NULL REFERENCES content(id),
    number      SMALLINT NOT NULL,
    title       VARCHAR(255),
    episode_count INT DEFAULT 0,
    UNIQUE (series_id, number)
);

CREATE TABLE episodes (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    season_id    UUID NOT NULL REFERENCES seasons(id),
    number       SMALLINT NOT NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT,
    duration_min INT NOT NULL,
    thumbnail_url TEXT,
    UNIQUE (season_id, number)
);

-- Genres
CREATE TABLE genres (
    id   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE content_genres (
    content_id UUID REFERENCES content(id),
    genre_id   UUID REFERENCES genres(id),
    PRIMARY KEY (content_id, genre_id)
);

-- ==================== VIDEO FILES ====================
-- Hər content çox keyfiyyətdə (480p, 720p, 1080p, 4K) saxlanılır
CREATE TABLE video_files (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    content_id   UUID REFERENCES content(id),
    episode_id   UUID REFERENCES episodes(id),
    quality      VARCHAR(10) NOT NULL,   -- '480p', '720p', '1080p', '4K'
    codec        VARCHAR(20),            -- 'H.264', 'H.265', 'AV1'
    format       VARCHAR(10) NOT NULL,   -- 'mp4', 'webm'
    storage_key  TEXT NOT NULL,          -- S3 key
    cdn_url      TEXT,                   -- CloudFront URL
    duration_sec INT,
    file_size    BIGINT,                 -- bytes
    bitrate_kbps INT,
    language     VARCHAR(5) DEFAULT 'az',
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

-- Subtitles
CREATE TABLE subtitles (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    content_id   UUID REFERENCES content(id),
    episode_id   UUID REFERENCES episodes(id),
    language     VARCHAR(5) NOT NULL,   -- 'az', 'en', 'ru'
    format       VARCHAR(10) NOT NULL,  -- 'srt', 'vtt', 'ass'
    storage_key  TEXT NOT NULL,
    is_forced    BOOLEAN DEFAULT FALSE,  -- hearing impaired
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

-- Dubbing / Audio tracks
CREATE TABLE audio_tracks (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    content_id  UUID REFERENCES content(id),
    episode_id  UUID REFERENCES episodes(id),
    language    VARCHAR(5) NOT NULL,
    storage_key TEXT NOT NULL,
    is_original BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

```cql
-- ==================== USER ACTIVITY (Cassandra) ====================

-- İzləmə tarixi
-- Query: "Bu istifadəçinin son izlədikləri"
CREATE TABLE watch_history (
    user_id      UUID,
    watched_at   TIMESTAMP,     -- sort üçün
    content_id   UUID,
    episode_id   UUID,          -- seriyal üçün
    progress_sec INT,           -- nə qədər izlənib (saniyə)
    total_sec    INT,
    completed    BOOLEAN,
    device_type  TEXT,          -- 'tv', 'mobile', 'web'
    PRIMARY KEY (user_id, watched_at, content_id)
) WITH CLUSTERING ORDER BY (watched_at DESC, content_id ASC)
  AND default_time_to_live = 7776000;  -- 90 gün

-- İzləmə davam etdirmə
-- Query: "Bu filmi nə qədər izlədim? Haradan davam etdirəm?"
CREATE TABLE watch_progress (
    user_id     UUID,
    content_id  UUID,
    episode_id  UUID,
    progress_sec INT,
    total_sec    INT,
    last_watched TIMESTAMP,
    PRIMARY KEY ((user_id, content_id), episode_id)
);

-- "Siyahıma əlavə et" (My List)
CREATE TABLE user_lists (
    user_id    UUID,
    added_at   TIMESTAMP,
    content_id UUID,
    PRIMARY KEY (user_id, added_at, content_id)
) WITH CLUSTERING ORDER BY (added_at DESC);

-- Content reytinqləri (istifadəçi tərəfindən)
CREATE TABLE user_ratings (
    user_id    UUID,
    content_id UUID,
    rating     TINYINT,   -- 1-5
    rated_at   TIMESTAMP,
    PRIMARY KEY ((user_id), content_id)
);
```

---

## Redis Dizaynı

```
# Session + JWT token
SET session:{token} {user_json} EX 86400  -- 24 saat

# Content cache (catalog page)
SET content:{content_id} {json} EX 3600   -- 1 saat

# Trending content (zaman pəncərəsinə görə)
ZINCRBY trending:daily 1 {content_id}
ZINCRBY trending:weekly 1 {content_id}
ZREVRANGE trending:daily 0 19             -- top 20

# User-ın aktiv sessiyaları (max screen kontrolu)
SADD active_sessions:{user_id} {session_id}
SCARD active_sessions:{user_id}           -- neçə paralel session?
SREM active_sessions:{user_id} {session_id}

# Content availability check (ölkə üzrə)
SISMEMBER available:{content_id}:countries AZ  -- AZ-da mövcuddurmu?

# Buffering/stream quality adaptive
HSET stream_quality:{user_id}:{session} bandwidth 5000 quality "1080p"
```

---

## Elasticsearch: Content Search

```json
{
  "index": "content",
  "mappings": {
    "properties": {
      "title":       {"type": "text", "analyzer": "azerbaijani", "fields": {"keyword": {"type": "keyword"}}},
      "description": {"type": "text", "analyzer": "azerbaijani"},
      "genres":      {"type": "keyword"},
      "tags":        {"type": "keyword"},
      "type":        {"type": "keyword"},
      "release_year":{"type": "short"},
      "imdb_rating": {"type": "half_float"},
      "maturity_rating": {"type": "keyword"},
      "languages":   {"type": "keyword"},
      "country":     {"type": "keyword"},
      "is_available":{"type": "boolean"},
      "available_countries": {"type": "keyword"}
    }
  }
}
```

---

## Kritik Dizayn Qərarları

```
1. Video files ayrı cədvəl (adaptive bitrate):
   Bir film 6 fərqli keyfiyyətdə → 6 row
   Adaptive bitrate streaming (ABR): şəbəkəyə görə keyfiyyət dəyişir
   DASH / HLS protokolları: manifest file + segment chunks

2. Watch progress ayrı cədvəl:
   "Davam etdir" funksiyası çox oxunur
   PRIMARY KEY (user_id, content_id, episode_id) → O(1) lookup
   watch_history-dən ayrı: history append-only, progress update edilir

3. Max parallel screens Redis-də:
   "Plan: 2 screen, 3 aktiv cihaz" → 3. cihazı rədd et
   Redis SET: SCARD → O(1) sayma
   Session bitəndə SREM ilə azalt

4. Content availability (geo-restriction):
   Müxtəlif ölkələrdə müxtəlif lisenziya
   Redis SET: available:{content_id}:countries → ölkə kodları
   Sorğu: SISMEMBER → O(1)

5. Subtitle/Audio ayrı cədvəl:
   İstifadəçi dil seçir → lazımi fayl göstərilir
   CDN-dən directly serve edilir
```

---

## Best Practices

```
✓ Video faylları S3-də, DB-də yalnız metadata
✓ Watch progress vs watch history ayrı cədvəl
✓ Cassandra partition key = user_id (user sorğuları sürətli)
✓ Redis SET ile parallel screen limit
✓ Elasticsearch content search üçün (full-text, facet)
✓ CDN signed URL (video access control)
✓ Adaptive bitrate: fərqli keyfiyyət faylları S3-də
✓ Geo-restriction Redis-də (ölkə-content mapping)

Anti-patterns:
✗ Watch history PostgreSQL-də saxlamaq (scale olmaz)
✗ Video fayllarını DB-də BLOB kimi saxlamaq
✗ Session sayını DB-dən COUNT() ilə almaq
```

---

## Netflix DB Dizaynı (Actual)

```
Netflix Technology Stack:
  
  Cassandra:
    → User activity, viewing history
    → 200M+ users, petabytes of data
    → Multi-region replication (AP system)
    → Netflix Cassandra-nın major contributor-u

  MySQL:
    → User accounts, subscriptions, billing
    → ACID critical operations

  CockroachDB:
    → Global distributed SQL (yeni sistemlər)
    → Multi-region ACID

  S3 + CloudFront:
    → Video storage (perabytes)
    → 190+ PoP locations worldwide

  Elasticsearch:
    → Content search, recommendation explanation

  Atlas (MongoDB):
    → Content metadata (flexible schema)
    
  Niyə Cassandra?
    Netflix: "Cassandra bizim ikinci evimizdir"
    2011-ci ildən istifadə edir
    SimpleDB-dən Cassandra-ya keçdi
    Zero downtime, linearizable scale
```
