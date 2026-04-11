# Google — DB Design & Technology Stack

## Google-un Database Ekosistemi

```
┌─────────────────────────────────────────────────────────────────┐
│                    Google Database Stack                         │
├──────────────────────┬──────────────────────────────────────────┤
│ Bigtable             │ Web index, Maps, Analytics, Gmail        │
│ Spanner              │ Google Finance, Ads, global ACID SQL     │
│ Firestore            │ Firebase apps, mobile backends           │
│ BigQuery             │ Analytics data warehouse (petabytes)     │
│ Memorystore (Redis)  │ Cache layer                              │
│ Colossus (GFS)       │ Distributed file system (internal)       │
│ Dremel               │ BigQuery engine (columnar)               │
│ Pub/Sub              │ Event streaming (Kafka alternative)      │
└──────────────────────┴──────────────────────────────────────────┘

Google DB-ləri sənayeni şəkilləndirdi:
  2003: GFS paper → Hadoop HDFS yarandı
  2004: MapReduce paper → Hadoop MapReduce yarandı
  2006: Bigtable paper → Cassandra, HBase yarandı
  2012: Spanner paper → CockroachDB, YugabyteDB yarandı
  2010: Dremel paper → Apache Drill, Presto, BigQuery yarandı
```

---

## Bigtable: Google-un Wide-Column Store

```
2004: Bigtable (internal), 2006: paper published

İstifadə sahəsi:
  Google Search index
  Google Maps tile data
  Gmail message storage
  Google Analytics
  YouTube watch history

Struktur:
  Row key → sorted lexicographically
  Column families → group of columns
  Cells → (row, column, timestamp) → value

Nümunə: Web crawl data
  Row key: reversed URL  (com.google.www)
    → Eyni domain-in URLleri sıralı olur

  Column families:
    anchor: {anchor:cnnsi.com = "CNN", anchor:my.look.ca = "..."}
    contents: {contents: = "<html>..."}
    
  Timestamp: versioning
    contents@1699000000 = "<html>v2>"
    contents@1698000000 = "<html>v1>"

Google Cloud Bigtable (2015):
  Public cloud version
  HBase API compatible
  Single-digit millisecond latency
  Linear scale
```

---

## Cloud Spanner: Global ACID SQL

```
2012: Spanner paper (Google internal)
2017: Cloud Spanner (public)

"The first globally distributed database
 that provides ACID transactions and SQL semantics"

Problem Spanner həll edir:
  Global scale + ACID transactions (normally impossible)
  CAP theorem: "Consistency AND Availability under Partition"
  
  Spanner-in cavabı:
  TrueTime API: atomic clock + GPS
  Clock uncertainty window: ε ≈ 7ms
  External consistency: hətta partition zamanı
  
TrueTime API:
  TT.now() → {earliest: t-ε, latest: t+ε}
  Commit wait: t + ε keçənə qədər gözlə
  → Bütün server-lərdə commit order qorunur

Schema example:
  CREATE TABLE Users (
    UserId INT64 NOT NULL,
    Name   STRING(MAX),
    Email  STRING(MAX)
  ) PRIMARY KEY (UserId);
  
  -- Interleaved (co-located) tables
  CREATE TABLE Albums (
    UserId  INT64 NOT NULL,
    AlbumId INT64 NOT NULL,
    Title   STRING(MAX)
  ) PRIMARY KEY (UserId, AlbumId),
    INTERLEAVE IN PARENT Users ON DELETE CASCADE;
  -- → User + Albums eyni server-də saxlanır!

İstifadə:
  Google Ads (global, billions of $)
  Google Finance
  Google Play Store
  
Public users:
  Shopify, DoorDash, Mercado Libre, Snap
```

---

## BigQuery: Serverless Analytics

```
2010: Dremel paper
2011: BigQuery (public)

"Query 1TB in seconds, petabytes in minutes"

Arxitektura:
  Columnar storage (Capacitor format)
  Separation of storage and compute
  Dremel engine: distributed query tree
  
Storage:
  Google Colossus (distributed file system)
  Automatic replication (3 copies)
  Column encoding + compression

Dremel query tree:
  Root server
  ├── Intermediate servers (fan-out)
  │   ├── Leaf servers (read actual data)
  │   └── Leaf servers
  └── Intermediate servers
  
  Parallel scan → aggregate up → result

Pricing:
  Storage: $0.02/GB/month
  Queries: $5/TB scanned (or flat-rate)

Nümunə:
  SELECT country, COUNT(*) as searches
  FROM `bigquery-public-data.google_trends.top_terms`
  WHERE week = '2024-01-01'
  GROUP BY country
  ORDER BY searches DESC;
  -- Scans TBs in <10 seconds
```

---

## Firebase / Firestore

```
Firebase (2011, Google acquired 2014)

Firestore: document store (MongoDB-like)
  Real-time listeners (WebSocket)
  Offline support (mobile)
  Automatic multi-region replication

Use case:
  Mobile apps
  Real-time collaborative apps
  Small-medium scale web apps

Schema (NoSQL document):
  Collection: users
    Document: {uid}
      name: "Ali"
      email: "ali@example.com"
      
      Sub-collection: orders
        Document: {order_id}
          items: [...]
          total: 99.99

Limitations:
  Complex queries limited (no JOINs)
  Not for analytics workloads
  Cost can escalate with reads

Firebase Realtime Database (older):
  JSON tree structure
  WebSocket sync
  Replaced mostly by Firestore
```

---

## Spanner vs PostgreSQL vs BigQuery

```
                Spanner      PostgreSQL    BigQuery
Use case:       OLTP global  OLTP          OLAP analytics
Consistency:    External     Strong        Eventual (reads)
Scale:          Global       Single region Multi-region
Transactions:   ✓ ACID       ✓ ACID        Read-only
SQL:            ✓            ✓             ✓ (dialect)
Latency:        5-10ms       1-5ms         Seconds (scans)
Cost:           High         Low           Pay per query
Schema change:  Online DDL   ✓             ✓

When to use:
  Spanner:    Multi-region financial apps, global inventory
  PostgreSQL: Standard transactional apps
  BigQuery:   Analytics, reporting, ML datasets
```

---

## Google-un Töhfələri

```
Papers that changed the industry:

2003: The Google File System (GFS)
  → Apache Hadoop HDFS

2004: MapReduce
  → Apache Hadoop MapReduce

2006: Bigtable
  → Apache Cassandra (Facebook engineers)
  → Apache HBase

2010: Dremel
  → Apache Drill, Presto, Amazon Athena

2012: Spanner
  → CockroachDB, YugabyteDB, TiDB

2013: F1 (SQL over Spanner)
  → Distributed SQL movement

Lesson:
  Google-un internal problemlər → industry papers
  Community builds open-source versions
  Google sonra cloud-a çıxarır
  "Research → Paper → Open Source → Product"
```

---

## Google-dan Öyrəniləcəklər

```
1. Separate storage from compute:
   BigQuery, Spanner, Bigtable: ayrı scale
   Storage cheap, compute elastic
   Modern cloud DB paradigm

2. TrueTime = innovation:
   Hardware (atomic clock) + software = ACID globally
   "Most databases assume clocks are wrong; we make them right"

3. Interleaved tables (Spanner):
   Parent-child co-location
   JOINs between co-located data: fast
   Cross-shard JOINs: expensive

4. Papers before products:
   Publish research → community builds open source
   → Ecosystem grows → Google benefits too

5. Columnar for analytics:
   Row storage: OLTP (PostgreSQL)
   Column storage: OLAP (BigQuery)
   Wrong storage type = 10-100x slower queries
```
