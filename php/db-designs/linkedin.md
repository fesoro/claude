# LinkedIn — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                   LinkedIn Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ Espresso             │ Member profiles, connections (custom NoSQL)│
│ Kafka                │ Event streaming (LinkedIn created Kafka!) │
│ Voldemort            │ Distributed KV store (LinkedIn created)  │
│ MySQL                │ Legacy data, some services               │
│ Ambry                │ Media storage (LinkedIn's blob store)    │
│ Pinot (Apache)       │ Real-time analytics (LinkedIn created)   │
│ Samza (Apache)       │ Stream processing (LinkedIn created)     │
│ Galene               │ Search engine (built on Lucene)          │
│ Venice               │ Feature store for ML                     │
└──────────────────────┴──────────────────────────────────────────┘

LinkedIn = Open Source factory:
  Kafka, Samza, Pinot, Voldemort, Helix, Brooklin...
  "Build for yourself, open source it, become standard"
```

---

## LinkedIn-in Tarixi

```
2003: MySQL monolith
  First DB: MySQL
  Ruby on Rails (early version)
  
2008-2010: Scale problemləri
  300M+ users
  MySQL sharding mürəkkəbdi
  
2010: Espresso development başladı
  Custom NoSQL over MySQL
  Document store semantics + MySQL storage
  
2011: Apache Kafka yaradıldı
  Problem: "How to connect 300+ data systems?"
  Pub/Sub → unified event log
  2011: open-source → Apache
  
2014: Pinot (real-time analytics)
  "Who viewed my profile?" - real-time
  OLAP on event streams
  
2015: Samza (Apache)
  Stream processing
  "Kafka + stateful processing"
```

---

## Espresso: LinkedIn-in Custom NoSQL

```
Espresso = Document store on top of MySQL

Niyə custom?
  MySQL sharding manual → ops nightmare
  Need: Document semantics + MySQL durability + horizontal scale
  
Arxitektura:
  ┌────────────────────────────────────────┐
  │  Espresso (routing + document layer)   │
  │  - JSON document storage              │
  │  - Routing (consistent hashing)       │
  │  - Secondary indexes                  │
  └───────────────────┬────────────────────┘
                      │
         ┌────────────┼─────────────┐
         ▼            ▼             ▼
    MySQL shard 1  MySQL shard 2  MySQL shard 3
    
Üstünlükləri:
  ✓ MySQL-in ACID-i qorunur
  ✓ Horizontal scale (sharding transparent)
  ✓ Document semantics (JSON)
  ✓ Secondary indexes
  ✓ Online schema change
  
İstifadə sahəsi:
  Member profiles (900M+ members)
  Connections graph
  InMail messages
  Job applications
```

---

## Voldemort: Distributed KV Store

```
LinkedIn 2009: Voldemort (named after Harry Potter villain!)
  Open-source: github.com/voldemort/voldemort
  
Dynamo paper-dən ilham aldı:
  Consistent hashing
  Vector clocks (conflict resolution)
  Eventual consistency
  
İstifadə sahəsi LinkedIn-də:
  Feed computations
  Recommendation results
  Session data
  
Sonradan Venice-ə köçdü:
  Venice: newer KV store for ML feature serving
  "Voldemort was good, Venice is better"

Voldemort legacy:
  Martin Kleppmann "Designing Data-Intensive Applications"
  kitabında vector clock nümunəsi olaraq göstərilir
```

---

## Apache Kafka: LinkedIn-in Ən Böyük Töhfəsi

```
2011: Kafka yaradıldı, 2012: Apache
  "The unified log" — Jay Kreps (LinkedIn VP Engineering)
  
LinkedIn-in problemi:
  ETL pipelines: A → B, A → C, A → D...
  N data sources × M data sinks = N×M pipelines!
  
  300+ services
  Hundreds of data flows
  "Spaghetti data pipelines"

Kafka həlli:
  Unified event log
  Every service PUBLISHES to Kafka
  Every service CONSUMES from Kafka
  N + M (not N×M)

LinkedIn-in Kafka istifadəsi:
  Activity tracking: 7T+ events/day
  Metrics pipeline: infrastructure monitoring
  Site event tracking: page views, clicks
  Stream processing: Samza jobs
  
Dünya:
  Airbnb, Netflix, Uber, Twitter, Goldman Sachs...
  Kafka = de facto event streaming standard
  "LinkedIn's most impactful contribution to tech"
```

---

## MySQL Schema (Simplified Profiles)

```sql
-- LinkedIn-in Espresso-nun altında MySQL saxlanır
-- Bu simplified version

-- ==================== MEMBERS ====================
CREATE TABLE members (
    id              BIGINT PRIMARY KEY,  -- Espresso assigns
    public_id       VARCHAR(36) UNIQUE,  -- URL slug: "ali-mammadov-123"
    
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    headline        VARCHAR(220),
    summary         TEXT,
    
    -- Location
    country         CHAR(2),
    city            VARCHAR(100),
    
    -- Profile
    profile_pic_url TEXT,
    background_url  TEXT,
    
    -- Industry
    industry_id     INT,
    
    -- Followers (creator mode)
    follower_count  INT DEFAULT 0,
    connection_count INT DEFAULT 0,
    
    -- Premium
    premium_type    VARCHAR(20),  -- 'career', 'business', 'recruiter'
    
    is_open_link    BOOLEAN DEFAULT FALSE,  -- Anyone can message
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CONNECTIONS ====================
-- Undirected graph: A connects B = B connects A
CREATE TABLE connections (
    member_id_1     BIGINT NOT NULL,  -- always: member_id_1 < member_id_2
    member_id_2     BIGINT NOT NULL,
    
    -- Source
    initiator_id    BIGINT NOT NULL,  -- kim istek göndərdi
    
    connected_at    TIMESTAMPTZ DEFAULT NOW(),
    
    PRIMARY KEY (member_id_1, member_id_2)
);

-- For lookup both directions
CREATE INDEX idx_member2 ON connections(member_id_2, member_id_1);

-- ==================== DEGREES (1st, 2nd, 3rd) ====================
-- "2nd degree: You → A → B"
-- Pre-computed, stored in Espresso/Venice
-- Real-time BFS too expensive for 900M nodes

-- ==================== POSTS / CONTENT ====================
CREATE TABLE posts (
    id          BIGINT PRIMARY KEY,
    author_id   BIGINT NOT NULL REFERENCES members(id),
    
    type        ENUM('text', 'article', 'poll', 'video', 'document'),
    content     TEXT,
    
    -- Visibility
    visibility  ENUM('public', 'connections', 'followers') DEFAULT 'public',
    
    -- Stats
    reaction_count  INT DEFAULT 0,
    comment_count   INT DEFAULT 0,
    repost_count    INT DEFAULT 0,
    
    published_at    TIMESTAMPTZ DEFAULT NOW()
);
```

---

## "People You May Know" Algorithm

```
PYMK = People You May Know

Features (stored in Venice feature store):
  - Common connections count
  - Same company (current or past)
  - Same school
  - Same industry
  - Profile views (they viewed me)
  - Contact import match
  - Geographic proximity
  - Skills overlap

Pipeline:
  Kafka events → Samza stream processing
  → Feature computation → Venice KV store
  → ML model scoring → Redis cache
  → API → User sees recommendations

Graph traversal:
  2nd degree connections: BFS on connection graph
  Espresso stores pre-computed adjacency
  
Scale:
  900M members
  BFS on full graph: impractical (too large)
  Sampling: take subset of connections → approximate 2nd degree
  "Good enough" recommendations vs exact
```

---

## Apache Pinot: "Who Viewed My Profile?"

```
LinkedIn-in real-time analytics:
  "Bu profilim bu həftə neçə dəfə görüldü?"
  "Axtarış nəticəsindən neçə nəfər gəldi?"

Problem:
  100M+ profile views/day
  "Recent 90 days" aggregate query
  Real-time (not batch)
  
Pinot (OLAP):
  Kafka-dan real-time ingest
  Columnar storage
  Sub-second query latency
  
Query:
  SELECT COUNT(*) FROM profile_views
  WHERE profile_id = :id
    AND viewed_at >= NOW() - INTERVAL '7 days'
    AND viewer_id != :id
  
Pinot table:
  CREATE TABLE profile_views (
    profile_id    BIGINT,
    viewer_id     BIGINT,
    viewed_at     LONG,
    source        STRING,  -- 'search', 'recommendation', 'direct'
    viewer_title  STRING
  ) ...;

Redis cache:
  SET profile_views:{id}:7d {count} EX 3600
```

---

## Scale Faktları

```
Numbers (2023):
  950M+ registered members
  200+ countries
  65M+ job listings
  3M+ company pages
  
  Kafka: 7 trillion+ events per day
  Espresso: petabytes of member data
  Pinot: 100B+ events indexed
  
  PYMK: served to hundreds of millions daily
  
Engineering:
  ~20,000 engineers (Microsoft acquisition 2016)
  Open source contributions: 20+ Apache projects
```

---

## LinkedIn-dən Öyrəniləcəklər

```
1. Kafka = game changer:
   N×M → N+M data pipeline problem
   Unified event log pattern
   Every major company uses Kafka now

2. Custom NoSQL (Espresso):
   MySQL durability + document flexibility
   "Build on top of battle-tested storage"

3. Degree of connection pre-computation:
   Real-time BFS: impossible at 900M nodes
   Batch pre-compute → cache → serve

4. Venice feature store:
   ML features centralized
   Models share same feature computation
   Avoid duplicate work

5. Open source strategy:
   Build for internal use
   Open source → community improves it
   Talent magnet (engineers want to work on popular tools)
```
