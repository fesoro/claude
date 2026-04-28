# Recommendation System — DB Design (Senior ⭐⭐⭐)

## İcmal

Recommendation sistemi istifadəçiyə "sənə uyğun məzmun" göstərir. Netflix-in 80%-i, Amazon satışlarının 35%-i tövsiyə motorundan gəlir. Əsas problemlər: cold start, real-time vs batch, embedding storage, scalable similarity search.

---

## Tövsiyə olunan DB Stack

```
Interactions:  PostgreSQL    (user-item events — source of truth)
Embeddings:    pgvector       (vector similarity search)
Cache:         Redis          (precomputed recommendations, user profiles)
Feature store: PostgreSQL     (ML features, precomputed signals)
Analytics:     ClickHouse     (click-through, conversion tracking)
Streaming:     Kafka          (real-time interaction events)
```

---

## Recommendation Növləri

```
┌─────────────────────┬──────────────────────────────────────────────────┐
│ Növ                 │ Məntiq                                           │
├─────────────────────┼──────────────────────────────────────────────────┤
│ Collaborative       │ "Sənə oxşar user-lər bunu bəyəndi"              │
│ Filtering           │ User-item matrix → similarity                    │
├─────────────────────┼──────────────────────────────────────────────────┤
│ Content-Based       │ "Baxdığına oxşar məzmun"                        │
│                     │ Item features → item similarity                  │
├─────────────────────┼──────────────────────────────────────────────────┤
│ Hybrid              │ CF + CB birlikdə (Netflix, Spotify)              │
├─────────────────────┼──────────────────────────────────────────────────┤
│ Knowledge-Based     │ "30-35 yaş, Bakı, tech worker üçün..."          │
│                     │ Rule-based (cold start üçün)                     │
├─────────────────────┼──────────────────────────────────────────────────┤
│ Session-Based       │ "Bu session-da baxdıqlarına görə"               │
│                     │ RNN / transformer, short-term preference         │
└─────────────────────┴──────────────────────────────────────────────────┘
```

---

## Core Schema

```sql
-- ==================== USER-ITEM INTERACTIONS ====================
-- Bütün recommendation-ın əsası: kim nəyə necə reaksiya verdi
CREATE TABLE interactions (
    id          BIGSERIAL,
    user_id     BIGINT NOT NULL,
    item_id     BIGINT NOT NULL,
    item_type   VARCHAR(30) NOT NULL,  -- 'movie', 'product', 'article', 'song'

    -- Interaction type
    event_type  VARCHAR(30) NOT NULL,
    -- 'view', 'click', 'like', 'dislike', 'purchase', 'share',
    -- 'add_to_cart', 'play', 'skip', 'bookmark', 'rating'

    -- Explicit rating (optional: 1-5 stars)
    rating      NUMERIC(2, 1),         -- NULL = implicit signal

    -- Implicit signals
    watch_pct   SMALLINT,              -- video-da neçə % izləndi
    dwell_ms    INTEGER,               -- neçə ms-dəki qaldı (article)

    -- Context
    source      VARCHAR(50),           -- 'home_feed', 'search', 'email', 'recommendation'
    position    SMALLINT,              -- recommendation list-də neçənci idi

    session_id  VARCHAR(100),

    created_at  TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (created_at);

CREATE INDEX idx_interactions_user ON interactions(user_id, created_at DESC);
CREATE INDEX idx_interactions_item ON interactions(item_id, event_type);

-- ==================== ITEMS ====================
CREATE TABLE items (
    id          BIGSERIAL PRIMARY KEY,
    type        VARCHAR(30) NOT NULL,   -- 'movie', 'product', 'song'
    title       VARCHAR(500) NOT NULL,
    description TEXT,

    -- Content features (structured)
    genre       VARCHAR(50)[],          -- ['action', 'comedy']
    tags        VARCHAR(100)[],
    language    CHAR(2),
    year        SMALLINT,
    duration_s  INTEGER,                -- seconds (video/audio)

    -- Popularity signals (precomputed, updated periodically)
    view_count    BIGINT DEFAULT 0,
    like_count    BIGINT DEFAULT 0,
    avg_rating    NUMERIC(3, 2),
    trending_score FLOAT DEFAULT 0,

    -- Vector embedding (content-based similarity)
    embedding   vector(1536),           -- pgvector: OpenAI/custom model

    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- pgvector index: approximate nearest neighbor
CREATE INDEX idx_items_embedding ON items
    USING ivfflat (embedding vector_cosine_ops)
    WITH (lists = 100);

-- ==================== USER PROFILES (Feature Store) ====================
CREATE TABLE user_profiles (
    user_id     BIGINT PRIMARY KEY,

    -- Precomputed preferences (ML features)
    genre_prefs JSONB DEFAULT '{}',
    -- {"action": 0.8, "comedy": 0.3, "drama": 0.6}

    -- User embedding (collaborative filtering)
    embedding   vector(128),            -- user latent vector

    -- Behavioral signals
    avg_session_duration_s INTEGER,
    preferred_time_of_day  SMALLINT,    -- 0-23 saat
    device_pref VARCHAR(20),            -- 'mobile', 'tv', 'desktop'

    -- Diversity preference
    exploration_rate FLOAT DEFAULT 0.2, -- 0=exploit, 1=explore

    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== PRECOMPUTED RECOMMENDATIONS ====================
-- Batch job-dan hazır nəticələr (Redis-ə paralleldə cache olunur)
CREATE TABLE user_recommendations (
    user_id     BIGINT NOT NULL,
    context     VARCHAR(50) NOT NULL,   -- 'home', 'email', 'push', 'similar'
    item_id     BIGINT NOT NULL,

    score       FLOAT NOT NULL,         -- ranking score
    rank        SMALLINT NOT NULL,      -- 1 = best
    reason      VARCHAR(100),           -- 'because_you_watched_X', 'trending'

    model_version VARCHAR(20),          -- 'cf_v3', 'hybrid_v5'

    -- Validity window
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ DEFAULT NOW(),

    PRIMARY KEY (user_id, context, rank)
);

CREATE INDEX idx_recs_user_context ON user_recommendations(user_id, context, expires_at);
```

---

## Collaborative Filtering: Data Model

```
User-Item Matrix:

         Movie1  Movie2  Movie3  Movie4
User A:    5       4       ?       1
User B:    4       ?       4       2
User C:    ?       5       4       ?

"?" = unknown → model predict edir

Matrix Factorization (ALS / SVD):
  User embedding: [0.8, -0.3, 0.5, ...]  (128 dim)
  Item embedding: [0.7, -0.2, 0.6, ...]  (128 dim)
  
  Predicted rating = user_vec · item_vec (dot product)
  
PostgreSQL + pgvector:
  -- "User A üçün ən oxşar user-lər" (User-User CF)
  SELECT user_id, embedding <=> :user_a_embedding AS distance
  FROM user_profiles
  WHERE user_id != :user_a_id
  ORDER BY distance
  LIMIT 50;
  
  -- "Bu filmə ən oxşar filmlər" (Item-Item CF)
  SELECT id, title, embedding <=> :movie_embedding AS distance
  FROM items
  WHERE id != :movie_id AND is_active = TRUE
  ORDER BY distance
  LIMIT 20;
```

---

## Recommendation Pipeline

```
2 yanaşma:

1. Batch (offline) — əsas yanaşma:
   Hər gün gecə: model train et
   Hər saat: top-N recommendation precompute et
   → user_recommendations table-a yaz
   → Redis-ə cache et
   
   User request: Redis-dən oxu (< 1ms)
   
2. Real-time (online) — tamamlayıcı:
   User yeni video baxdı → session-based update
   Redis-də session context-i yenilə
   Next request: session context + precomputed blend

Tipik hybrid pipeline:
  Kafka: user interaction event gəlir
  
  Stream processor (Spark/Flink):
  → Feature update: user_profiles.genre_prefs update
  → Trending score update: items.trending_score
  
  Batch job (günlük):
  → Matrix factorization model train
  → User + item embeddings yenilə
  → Top-100 recommendations precompute
  → PostgreSQL + Redis yenilə
```

---

## Redis Cache Design

```
# Precomputed recommendations (TTL: 6 saat)
SET rec:user:{user_id}:home {json_array_of_recommendations} EX 21600

# Similar items cache
SET rec:similar:{item_id} {json_array} EX 3600

# Trending items (global, TTL: 1 saat)
SET rec:trending:{category} {json_array} EX 3600

# Session context (real-time personalization)
RPUSH rec:session:{session_id} {item_id}
EXPIRE rec:session:{session_id} 1800      -- 30 dəq

# User feature cache (embedding + prefs)
SET rec:profile:{user_id} {json} EX 3600

Cache invalidation:
  User yeni rating verdi → rec:user:{id}:home silinir
  Model yeniləndi → bütün rec:user:* flush
  Redis SCAN + DEL (production-da: versioning ilə)
```

---

## Cold Start Problem

```
Yeni user (heç interaction yoxdur):
  1. Onboarding: 3-5 sual sor (janr, dil, mövzu)
  2. Demographic-based: yaş + ölkəyə görə popular items
  3. Trending fallback: həftənin ən populyar 20-si
  4. Knowledge-based rules: "Tech category seçiblər → Python books"

Yeni item (heç rating yoxdur):
  1. Content-based: item features → embedding → similar items
  2. Explore traffic: 5% random user-ə göstər → organic signals topla
  3. Cold start threshold: 50 interaction-dan sonra CF-ə keç

DB:
  items.view_count < 50 → content-based only
  items.view_count >= 50 → hybrid (CF + CB)
```

---

## Click-Through & Conversion Tracking (ClickHouse)

```sql
CREATE TABLE recommendation_events (
    event_time      DateTime,
    event_date      Date MATERIALIZED toDate(event_time),

    user_id         Int64,
    item_id         Int64,
    context         LowCardinality(String),   -- 'home', 'similar', 'email'
    model_version   LowCardinality(String),

    event_type      LowCardinality(String),   -- 'impression', 'click', 'purchase'
    rank            UInt8,                    -- list-də mövqe
    score           Float32                   -- model score

) ENGINE = MergeTree()
  PARTITION BY event_date
  ORDER BY (event_date, context, user_id)
  TTL event_date + INTERVAL 90 DAY;

-- Click-through rate per model version
SELECT
    model_version,
    context,
    countIf(event_type = 'impression') AS impressions,
    countIf(event_type = 'click')      AS clicks,
    clicks / impressions               AS ctr,
    countIf(event_type = 'purchase')   AS purchases
FROM recommendation_events
WHERE event_date >= today() - 7
GROUP BY model_version, context
ORDER BY ctr DESC;
```

---

## Tanınmış Sistemlər

```
Netflix:
  Two-stage: candidate generation → ranking
  Candidate gen: ALS collaborative filtering (Spark)
  Ranking: deep neural network (XGBoost + DNN)
  A/B test: hər alqoritm fərqli user segment-ə
  Storage: Cassandra (interactions) + S3 (model artifacts)

Spotify Discover Weekly:
  Collaborative filtering: eyni playlist-dəki mahnılar
  Audio features: BPM, danceability, energy (numeric cols)
  Matrix factorization: implicit ALS
  Batch: hər bazar ertəsi yenilənir (600M user)
  BigQuery → Bigtable (serving)

Amazon:
  Item-to-Item collaborative filtering (2003 paper)
  "Users who bought X also bought Y"
  Real-time: session-based (last 3 clicks)
  DynamoDB: precomputed item-to-item pairs
  Feature store: SageMaker Feature Store

TikTok FYP:
  Two-tower model: user tower + video tower
  Watch completion rate > likes as signal
  Real-time: session interaction updates ranking
  Cold start: 200-500 random user test batch
```

---

## Anti-Patterns

```
✗ Yalnız explicit ratings-a arxalanmaq:
  5-10% user rating verir, 90% implicit signals buraxır
  Watch time, click, skip → daha güclü siqnallar

✗ Popularity bias:
  Yalnız popular items recommend et → long tail itirilir
  User satisfaction düşür (hamı eyni şeyi görür)
  Diversity + exploration rate lazımdır

✗ Real-time-da matrix factorization çalışdırmaq:
  MF model training: saatlar alır
  Serving: precomputed embeddings + ANN search
  Training batch, serving real-time

✗ Item embedding-i DB-də TEXT kimi saxlamaq:
  "[0.1, 0.2, ...]" string → similarity search mümkün deyil
  pgvector vector type → index + fast ANN search

✗ Cold start-da ən popular-ı göstərmək (tək yol kimi):
  Hamıya eyni → diversity yoxdur
  Onboarding + exploration + demographic segmentation lazım
```
