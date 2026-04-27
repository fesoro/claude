# Spotify — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    Spotify Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ User accounts, playlists, subscriptions  │
│ Apache Cassandra     │ Listening history, user events           │
│ Google BigQuery      │ Analytics data warehouse                 │
│ Google Bigtable      │ ML feature store, recommendation data    │
│ Google GCS           │ Audio files (tracks, podcasts)           │
│ Redis                │ Session, cache, rate limiting            │
│ Elasticsearch        │ Track/artist/podcast search              │
│ Apache Kafka         │ Event streaming (plays, skips, etc.)     │
│ Hadoop + Spark       │ Offline ML training                      │
└──────────────────────┴──────────────────────────────────────────┘

Spotify 2016: Google Cloud-a köçdü (AWS-dən)
  "One of the largest cloud migrations at the time"
  All infrastructure → GCP
```

---

## Spotify-in DB Tarixi

```
2006-2010: On-premise, single region
  Sweden data center
  PostgreSQL + custom audio storage
  
2011-2015: Scale challenges
  100M+ users
  Cassandra: listening history
  Multi-region: US, EU expansion
  
2016: Google Cloud migration
  GCP: BigQuery, Bigtable, GCS
  Reason: ML/AI capabilities, BigQuery analytics
  
  "Wrapped" data (year-end): BigQuery-də hesablanır
  
2019+: Podcast acquisition
  Anchor, Gimlet, Ringer acquisitions
  Separate podcast infrastructure (initially)
  Gradually merged

ML-first company:
  Recommendation = core product
  Discover Weekly, Daily Mix, Blend
  GCP AI/ML tools deep integration
```

---

## PostgreSQL Schema

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    username        VARCHAR(30) UNIQUE,
    
    -- Profile
    display_name    VARCHAR(100),
    birth_date      DATE,
    country         CHAR(2),
    
    -- Subscription
    product         ENUM('free', 'premium', 'premium_duo',
                         'premium_family', 'student') DEFAULT 'free',
    premium_expires TIMESTAMPTZ,
    
    -- Preferences
    explicit_content BOOLEAN DEFAULT TRUE,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== TRACKS ====================
CREATE TABLE tracks (
    id          VARCHAR(22) PRIMARY KEY,  -- Spotify ID (base62)
    isrc        VARCHAR(12),              -- International Standard Recording Code
    
    name        VARCHAR(500) NOT NULL,
    duration_ms INT NOT NULL,
    
    -- Audio features (stored for recommendations)
    acousticness    NUMERIC(4,3),  -- 0.0-1.0
    danceability    NUMERIC(4,3),
    energy          NUMERIC(4,3),
    instrumentalness NUMERIC(4,3),
    liveness        NUMERIC(4,3),
    loudness        NUMERIC(6,3),  -- dB
    speechiness     NUMERIC(4,3),
    tempo           NUMERIC(6,3),  -- BPM
    valence         NUMERIC(4,3),  -- positivity
    key             SMALLINT,
    mode            SMALLINT,      -- 0: minor, 1: major
    time_signature  SMALLINT,
    
    -- Restrictions
    explicit        BOOLEAN DEFAULT FALSE,
    is_playable     BOOLEAN DEFAULT TRUE,
    
    -- Storage
    audio_gcs_uri   TEXT,  -- gs://bucket/tracks/{id}/128k.ogg
    
    preview_url     TEXT,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== ARTISTS ====================
CREATE TABLE artists (
    id          VARCHAR(22) PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    genres      TEXT[],
    popularity  SMALLINT DEFAULT 0,  -- 0-100
    follower_count INT DEFAULT 0,
    image_url   TEXT,
    
    -- Social
    spotify_url TEXT,
    
    INDEX idx_genre  (genres),
    INDEX idx_popularity (popularity DESC)
);

-- ==================== ALBUMS ====================
CREATE TABLE albums (
    id          VARCHAR(22) PRIMARY KEY,
    name        VARCHAR(500) NOT NULL,
    album_type  ENUM('album', 'single', 'ep', 'compilation') NOT NULL,
    
    release_date DATE,
    total_tracks SMALLINT,
    
    image_url   TEXT,
    label       VARCHAR(255),
    
    copyrights  JSONB,
    genres      TEXT[]
);

-- Junctions
CREATE TABLE album_artists (
    album_id    VARCHAR(22) NOT NULL,
    artist_id   VARCHAR(22) NOT NULL,
    role        VARCHAR(50) DEFAULT 'main',  -- main, featured
    position    SMALLINT DEFAULT 0,
    PRIMARY KEY (album_id, artist_id)
);

CREATE TABLE track_artists (
    track_id    VARCHAR(22) NOT NULL,
    artist_id   VARCHAR(22) NOT NULL,
    role        VARCHAR(50) DEFAULT 'main',
    position    SMALLINT DEFAULT 0,
    PRIMARY KEY (track_id, artist_id)
);

-- ==================== PLAYLISTS ====================
CREATE TABLE playlists (
    id          VARCHAR(22) PRIMARY KEY,
    owner_id    BIGINT NOT NULL REFERENCES users(id),
    
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(300),
    
    is_public   BOOLEAN DEFAULT TRUE,
    is_collaborative BOOLEAN DEFAULT FALSE,
    
    image_url   TEXT,
    
    -- Stats
    follower_count INT DEFAULT 0,
    
    -- Snapshot for change tracking
    snapshot_id VARCHAR(22),  -- changes when playlist modified
    
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE playlist_tracks (
    playlist_id VARCHAR(22) NOT NULL,
    track_id    VARCHAR(22) NOT NULL,
    position    INT NOT NULL,
    added_by    BIGINT REFERENCES users(id),
    added_at    TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (playlist_id, track_id),
    INDEX idx_position (playlist_id, position)
);

-- ==================== FOLLOWS ====================
CREATE TABLE user_follows_artist (
    user_id     BIGINT NOT NULL,
    artist_id   VARCHAR(22) NOT NULL,
    followed_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, artist_id)
);

CREATE TABLE user_follows_playlist (
    user_id     BIGINT NOT NULL,
    playlist_id VARCHAR(22) NOT NULL,
    followed_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, playlist_id)
);

CREATE TABLE user_follows_user (
    follower_id BIGINT NOT NULL,
    followed_id BIGINT NOT NULL,
    followed_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (follower_id, followed_id)
);

-- ==================== SAVED / LIKED ====================
CREATE TABLE saved_tracks (
    user_id    BIGINT NOT NULL,
    track_id   VARCHAR(22) NOT NULL,
    saved_at   TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, track_id)
);

CREATE TABLE saved_albums (
    user_id    BIGINT NOT NULL,
    album_id   VARCHAR(22) NOT NULL,
    saved_at   TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, album_id)
);
```

---

## Cassandra: Listening History

```cql
-- Hər user-in dinləmə tarixi
CREATE TABLE listening_history (
    user_id     BIGINT,
    played_at   TIMESTAMP,
    track_id    TEXT,
    
    -- Context
    context_type  TEXT,   -- 'playlist', 'album', 'artist', 'search'
    context_uri   TEXT,
    
    -- Playback
    ms_played     INT,
    skipped       BOOLEAN,
    shuffle       BOOLEAN,
    
    -- Device
    device_type   TEXT,  -- 'computer', 'smartphone', 'tablet', 'speaker'
    platform      TEXT,  -- 'web', 'ios', 'android', 'desktop'
    
    PRIMARY KEY (user_id, played_at)
) WITH CLUSTERING ORDER BY (played_at DESC)
  AND default_time_to_live = 7776000;  -- 90 days hot

-- "Recently played" (last 50)
CREATE TABLE recently_played (
    user_id   BIGINT,
    played_at TIMESTAMP,
    context_uri TEXT,
    track_id  TEXT,
    PRIMARY KEY (user_id, played_at)
) WITH CLUSTERING ORDER BY (played_at DESC);

-- Kafka → Cassandra stream (real-time write)
-- Spark job → BigQuery (batch analytics)
```

---

## Spotify Wrapped: BigQuery

```
Spotify Wrapped = yıl sonu statistics
  "Your top 5 songs of 2024"
  "You spent 8,432 minutes listening"
  
Pipeline:
  Cassandra → daily Spark export → BigQuery
  
BigQuery queries:
  SELECT track_id, COUNT(*) as plays, SUM(ms_played) as total_ms
  FROM listening_history
  WHERE user_id = :user_id
    AND DATE(played_at) BETWEEN '2024-01-01' AND '2024-12-31'
  GROUP BY track_id
  ORDER BY plays DESC
  LIMIT 5;
  
  -- Bu query: 1 user üçün 1 yılın datası
  -- BigQuery: petabytes across all users parallel
  
Timing:
  Early December: BigQuery jobs run
  ~1 week to process 600M+ users
  Dec 4-5: Release date
  Pre-computed results → Redis/CDN → instant load
```

---

## Discover Weekly: ML Pipeline

```
Discover Weekly = 30 song personalized playlist (every Monday)

Data sources:
  - Listening history (Cassandra → Bigtable features)
  - Playlist context (what playlists tracks appear in)
  - Audio features (acousticness, danceability, etc.)
  - Social signals (friends listen to)
  - Skip patterns

Collaborative filtering:
  "Users like you also liked X"
  Matrix factorization on 600M× millions matrix
  Spark ML on GCP (GPU clusters)
  
NLP on playlists:
  Playlist titles/descriptions → word2vec
  "workout", "chill", "focus" → semantic clusters
  Tracks in similar playlists → related tracks

Pipeline:
  Weekly batch: Spark on GCP
  Model → Bigtable feature store
  Monday morning: playlist generation job
  Results → PostgreSQL (user's playlists)
  User opens app → Discover Weekly ready
```

---

## Scale Faktları

```
Numbers (2023):
  600M+ monthly active users
  230M+ premium subscribers
  100M+ tracks
  5M+ podcasts
  4B+ playlists
  
  Listening events: billions per day
  Kafka: 2M+ events/second
  
  Wrapped 2023:
  600M users processed
  ~1 week compute time
  
  Infrastructure (GCP):
  Petabytes of audio files
  Bigtable: ML features for 600M users
  BigQuery: exabytes of analytics data
```

---

## Spotify-dən Öyrəniləcəklər

```
1. Cloud-first strategy:
   Full GCP migration → ML capabilities
   "Reinforce what makes you special" (ML/rec)

2. Audio features as data:
   Acousticness, danceability stored in DB
   Powers recommendations without user behavior

3. Wrapped = pre-computation:
   Don't compute on demand (too slow)
   Batch compute → cache → serve instantly

4. Music vs Podcast:
   Different content types → different metadata schemas
   Gradual unification

5. Playlist snapshot_id:
   Changes when playlist modified
   Client can detect "playlist changed" without refetch

6. Collaborative filtering at scale:
   600M users × millions of tracks
   Weekly batch is OK (not real-time)
   "Good enough" recommendations > perfect but slow
```
