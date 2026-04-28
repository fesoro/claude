# YouTube — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack (Google/YouTube)

```
┌─────────────────────────────────────────────────────────────────┐
│                    YouTube Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL (Vitess)       │ Video metadata, users, channels          │
│ Google Bigtable      │ View counts, watch history, comments     │
│ Google Spanner       │ Global consistent data (newer systems)   │
│ Google Colossus/GCS  │ Video files (raw + encoded)              │
│ Elasticsearch        │ Video search, suggestions                │
│ Redis / Memcached    │ Cache, session, hot video data           │
│ Apache Kafka         │ View events, recommendation pipeline     │
│ BigQuery             │ Analytics, ad targeting data             │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Vitess: YouTube-un MySQL Həlli

```
2010: YouTube MySQL single server → scale problemi
  Video upload artdı
  Comment sayı milyardlara çatdı
  MySQL sharding manual → çox mürəkkəb

2012: Vitess yaradıldı (YouTube Engineering)
  MySQL üzərində sharding middleware
  Application MySQL-ə qoşulur, Vitess arxada shard idarə edir
  
2015: Open-source (CNCF project oldu)

Vitess nədir?
  MySQL-compatible query routing
  Automatic resharding
  Connection pooling
  Schema change management
  
İstifadə edənlər:
  YouTube, Slack, GitHub, Shopify, PlanetScale
  "The MySQL scale layer"

Vitess sharding:
  videos table → shard by channel_id % 256
  users table  → shard by user_id % 256
  Cross-shard query: Vitess scatter-gather
```

---

## MySQL Schema (via Vitess)

```sql
-- ==================== CHANNELS ====================
CREATE TABLE channels (
    id            BIGINT UNSIGNED PRIMARY KEY,
    handle        VARCHAR(100) UNIQUE NOT NULL,  -- @username
    name          VARCHAR(100) NOT NULL,
    description   TEXT,
    
    -- Branding
    avatar_url    TEXT,
    banner_url    TEXT,
    
    -- Stats (denormalized)
    subscriber_count   BIGINT DEFAULT 0,
    video_count        INT DEFAULT 0,
    view_count         BIGINT DEFAULT 0,
    
    -- Status
    is_verified        BOOLEAN DEFAULT FALSE,
    is_monetized       BOOLEAN DEFAULT FALSE,
    
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== VIDEOS ====================
CREATE TABLE videos (
    id            VARCHAR(11) PRIMARY KEY,  -- YouTube video ID (e.g. 'dQw4w9WgXcQ')
    channel_id    BIGINT UNSIGNED NOT NULL,
    
    title         VARCHAR(100) NOT NULL,
    description   TEXT,
    
    -- Status
    status        ENUM('uploading', 'processing', 'published',
                       'private', 'unlisted', 'deleted') NOT NULL,
    privacy       ENUM('public', 'unlisted', 'private') DEFAULT 'public',
    
    -- Duration
    duration_sec  INT,
    
    -- Thumbnail
    thumbnail_url TEXT,
    
    -- Metadata
    category_id   TINYINT,
    tags          JSON,  -- ["music", "official", "mv"]
    language      VARCHAR(5),
    
    -- Geographic restrictions
    allowed_countries  JSON,  -- NULL = everywhere
    blocked_countries  JSON,
    
    -- Age restriction
    made_for_kids BOOLEAN DEFAULT FALSE,
    age_restricted BOOLEAN DEFAULT FALSE,
    
    -- Stats (approximate, exact in Bigtable)
    view_count    BIGINT DEFAULT 0,
    like_count    INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    
    published_at  DATETIME,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_channel (channel_id, published_at DESC),
    INDEX idx_status  (status, published_at DESC)
) ENGINE=InnoDB;

-- ==================== VIDEO FILES ====================
-- Hər video → çoxlu format/keyfiyyət
CREATE TABLE video_renditions (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    video_id    VARCHAR(11) NOT NULL,
    quality     ENUM('144p','240p','360p','480p','720p','1080p','1440p','2160p'),
    codec       ENUM('h264','h265','vp9','av1'),
    
    -- Storage
    gcs_uri     VARCHAR(500) NOT NULL,  -- gs://bucket/path
    file_size   BIGINT,
    bitrate_kbps INT,
    
    -- HLS/DASH segments manifest
    manifest_url TEXT,
    
    status      ENUM('processing', 'ready', 'failed') DEFAULT 'processing',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_video (video_id, quality)
) ENGINE=InnoDB;

-- ==================== SUBSCRIPTIONS ====================
CREATE TABLE subscriptions (
    subscriber_id BIGINT UNSIGNED NOT NULL,
    channel_id    BIGINT UNSIGNED NOT NULL,
    notify        BOOLEAN DEFAULT TRUE,  -- bell icon
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (subscriber_id, channel_id),
    INDEX idx_channel (channel_id)
) ENGINE=InnoDB;

-- ==================== PLAYLISTS ====================
CREATE TABLE playlists (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    channel_id  BIGINT UNSIGNED NOT NULL,
    title       VARCHAR(150) NOT NULL,
    privacy     ENUM('public', 'unlisted', 'private') DEFAULT 'public',
    video_count INT DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE playlist_videos (
    playlist_id BIGINT UNSIGNED NOT NULL,
    video_id    VARCHAR(11) NOT NULL,
    position    SMALLINT NOT NULL,
    added_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playlist_id, video_id),
    INDEX idx_position (playlist_id, position)
) ENGINE=InnoDB;
```

---

## Bigtable: View Counts & Watch History

```
Google Bigtable: wide-column store
  Row key → sorted → range scan sürətli

View Count Problem:
  500 saatlıq video hər dəqiqə yüklənir YouTube-a
  Psy - Gangnam Style: 5B+ views
  Counter: saniyəlik milyonlarla increment

Naive approach (MySQL):
  UPDATE videos SET view_count = view_count + 1 WHERE id = ?
  → Single row hotspot → deadlock, queueing

Bigtable approach:
  Row key: video_id
  Column: view#{timestamp}
  Value:  batch_count (10K views batch)
  
  Periodic aggregation:
  SELECT SUM(batch_count) WHERE row_key = video_id
  → eventual consistency OK for view count

Watch History:
  Row key: user_id#reverse_timestamp
  Columns: video_id, watched_seconds, completed, device

  Range scan: user-ın son N tarixçəsi = prefix scan
```

---

## Video Upload Pipeline

```
Upload flow:

1. Client → Resumable Upload URL
   POST /upload/initiate
   Response: upload_url (GCS signed URL)

2. Client → GCS directly (bypass YouTube servers)
   PUT {upload_url} [chunked]
   → GCS: raw-uploads/{video_id}/original

3. GCS trigger → Kafka event: "new upload"

4. Transcoding Farm (parallel):
   ┌─────────────────────────────────────┐
   │  Transcoding workers                │
   │  Original → 144p  (H.264)          │
   │  Original → 360p  (H.264)          │
   │  Original → 720p  (H.264 + VP9)    │
   │  Original → 1080p (H.264 + VP9)    │
   │  Original → 4K    (AV1)            │
   │  + thumbnail extraction             │
   │  + audio extraction                 │
   │  + subtitle auto-generation (ASR)   │
   └─────────────────────────────────────┘

5. Each rendition → GCS: videos/{video_id}/{quality}.{ext}

6. Manifest generation (HLS/DASH)
   → CDN cache warm-up

7. Status update: videos.status = 'published'

8. Search index update (Elasticsearch)

9. Subscriber notification (Kafka → Push service)

Encoding stats:
  1 video → ~100+ encode jobs
  4K HDR → 20+ different profiles
  YouTube: 500 hours uploaded per minute!
```

---

## View Counter at Scale

```
Problem: "Gangnam Style" problem
  Milyonlarla concurrent view → single row update

Həll: Sharded Counters

                     ┌── counter_shard_0: 1,234,000
                     ├── counter_shard_1: 1,198,000
video:dQw4w9WgXcQ ──├── counter_shard_2: 1,267,000
                     ├── ...
                     └── counter_shard_9: 1,251,000

Total = SUM(all shards) ≈ 12,450,000

Redis implementation:
  Write: INCR views:{video_id}:{shard_id}   (shard = random 0-9)
  Read:  GET views:{video_id}:0 + ... + GET views:{video_id}:9

Batch flush to Bigtable:
  Her 30 saniyə: Redis counter-ları Bigtable-a yaz
  Redis: fast writes
  Bigtable: persistent storage
  MySQL: approximate count (batch sync every 5 min)

YouTube view validation:
  Bot views filterlənir
  View yalnız 30+ saniyə izlədikdə sayılır
  Same IP/user: limited count per day
```

---

## Elasticsearch: Video Search

```json
{
  "index": "videos",
  "mappings": {
    "properties": {
      "video_id":    {"type": "keyword"},
      "title":       {"type": "text", "analyzer": "english",
                      "fields": {"raw": {"type": "keyword"}}},
      "description": {"type": "text", "analyzer": "english"},
      "tags":        {"type": "keyword"},
      "channel_id":  {"type": "keyword"},
      "channel_name":{"type": "text"},
      "category_id": {"type": "byte"},
      "duration_sec":{"type": "integer"},
      "view_count":  {"type": "long"},
      "like_count":  {"type": "integer"},
      "language":    {"type": "keyword"},
      "published_at":{"type": "date"},
      "thumbnail_url":{"type": "keyword", "index": false}
    }
  }
}
```

---

## Scale Faktları

```
Numbers (2023):
  2.7B+ monthly active users
  500 hours of video uploaded per minute
  1B+ hours watched per day
  800M+ videos total
  
  Infrastructure:
  ~1M servers (Google global)
  CDN: Google's own CDN + ISP caching
  
  Vitess:
  MySQL cluster managing 10,000+ shards
  
  Bigtable:
  Petabytes of watch history data
  
  Transcoding:
  Millions of encode jobs per day
  Custom silicon (TPUs for video ML)
```

---

## YouTube-dan Öyrəniləcəklər

```
1. Vitess for MySQL at scale:
   Manual sharding əvəzinə middleware
   Application kod dəyişmir
   "MySQL that scales"

2. Sharded counters:
   Single row hotspot → N shards
   Eventual consistency OK for view count

3. Upload directly to storage:
   Client → GCS signed URL (bypass app server)
   App server: metadata only
   Reduces bandwidth cost

4. Async transcoding pipeline:
   Upload endpoint returns instantly
   Background workers process
   Status polling / webhook

5. Separate hot and cold paths:
   Hot video (trending): Redis cache
   Cold video (old): GCS → CDN on demand
   Tiered storage

6. Content ID system:
   Copyright detection via fingerprinting
   Audio/video hash matching at scale
```
