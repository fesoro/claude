# DB Seçim Bələdçisi

## Hansı DB-ni nə vaxt seçmək lazımdır?

---

## DB Kateqoriyaları

```
┌─────────────────────────────────────────────────────────────────┐
│                    DB Ekosistemi                                 │
├──────────────────┬──────────────────────────────────────────────┤
│ Relational (SQL) │ PostgreSQL, MySQL, Oracle, SQL Server        │
│ Document         │ MongoDB, CouchDB, Firestore                  │
│ Key-Value        │ Redis, DynamoDB, etcd                        │
│ Wide-Column      │ Cassandra, HBase, Bigtable                   │
│ Graph            │ Neo4j, Amazon Neptune, ArangoDB              │
│ Time-Series      │ InfluxDB, TimescaleDB, Prometheus            │
│ Search           │ Elasticsearch, OpenSearch, Solr              │
│ Vector           │ Pinecone, Qdrant, pgvector                   │
│ NewSQL           │ CockroachDB, TiDB, Spanner                   │
└──────────────────┴──────────────────────────────────────────────┘
```

---

## Qərar Ağacı

```
Suallar:

1. ACID transaction lazımdır?
   Bəli → PostgreSQL / MySQL
   Xeyr → davam et

2. Data strukturu?
   Schema-free (dəyişkən) → MongoDB
   Zaman seriyası → InfluxDB / TimescaleDB
   Graf (əlaqələr) → Neo4j
   Sadə key-value → Redis / DynamoDB
   Geniş sütunlar → Cassandra

3. Ölçü (scale)?
   Kiçik-orta → PostgreSQL / MySQL
   Böyük (TB+), yazma ağır → Cassandra
   Global, multi-region → CockroachDB / Spanner

4. Oxuma/Yazma nisbəti?
   Oxuma ağır (10:1+) → Read replicas + Redis cache
   Yazma ağır (insert-heavy) → Cassandra / TimeSeries DB
   Bərabər → PostgreSQL + connection pooling

5. Query mürəkkəbliyi?
   Sadə get/set → Redis / DynamoDB
   Mürəkkəb join-lər → PostgreSQL
   Full-text search → Elasticsearch
   Analytics → ClickHouse / Redshift
```

---

## DB Xüsusiyyətləri Müqayisəsi

```
┌──────────────┬──────────┬──────────┬──────────┬──────────┬──────────┐
│              │ PostgreSQL│ MongoDB  │ Cassandra│  Redis   │ClickHouse│
├──────────────┼──────────┼──────────┼──────────┼──────────┼──────────┤
│ ACID         │ ✓✓✓      │ ✓ (single)│ ✗ (tunable)│ ✓ (single)│ ✗     │
│ Scale-out    │ ✗ (hard) │ ✓✓       │ ✓✓✓      │ ✓✓       │ ✓✓✓     │
│ Flexible schema│ ✗ (jsonb)│ ✓✓✓    │ ✗        │ ✓        │ ✗       │
│ Complex query│ ✓✓✓      │ ✓✓       │ ✗        │ ✗        │ ✓✓✓     │
│ Write speed  │ ✓✓       │ ✓✓       │ ✓✓✓      │ ✓✓✓      │ ✓✓✓     │
│ Read speed   │ ✓✓ (index)│ ✓✓      │ ✓✓✓(key) │ ✓✓✓      │ ✓✓✓(col)│
│ Joins        │ ✓✓✓      │ ✗ (embed)│ ✗        │ ✗        │ ✗       │
│ Geospatial   │ ✓✓✓(PostGIS)│ ✓✓   │ ✗        │ ✓✓(GEO)  │ ✗       │
│ Full-text    │ ✓ (tsvector)│ ✓     │ ✗        │ ✗        │ ✗       │
└──────────────┴──────────┴──────────┴──────────┴──────────┴──────────┘
```

---

## Polyglot Persistence

```
Real dünyada bir sistem çox vaxt birdən çox DB istifadə edir:

E-commerce nümunəsi:
  PostgreSQL  → Products, Orders, Users (ACID, complex queries)
  Redis       → Session, Cart, Cache, Rate limiting
  Elasticsearch → Product search, autocomplete
  S3          → Product images, files

WhatsApp nümunəsi:
  Mnesia      → User sessions, online status (in-memory)
  MySQL       → User accounts, contacts
  Message Store → Custom distributed KV store

Netflix nümunəsi:
  MySQL       → User accounts, billing
  Cassandra   → Watch history, user activity (write-heavy)
  CockroachDB → View count, global distributed
  S3          → Video files
  Redis       → Sessions, cache

Qayda: "Doğru iş üçün doğru alət"
```

---

## PostgreSQL vs MySQL

```
PostgreSQL seçin:
  ✓ JSONB columns (semi-structured data)
  ✓ Full-text search (tsvector)
  ✓ Window functions, CTEs (mürəkkəb analitik)
  ✓ PostGIS (geospatial)
  ✓ pgvector (vector search)
  ✓ LISTEN/NOTIFY (pub/sub)
  ✓ DDD pattern-lər (ENUM types, custom types)

MySQL seçin:
  ✓ Sadə, sürətli read-heavy workload
  ✓ Daha geniş hosting dəstəyi
  ✓ Legacy sistem uyğunluğu
  ✓ Galera Cluster (multi-master)
  ✓ TokuDB engine (compression)

Hər iki halda:
  ✓ Connection pooling (PgBouncer / ProxySQL)
  ✓ Read replicas
  ✓ Proper indexing
  ✓ EXPLAIN ANALYZE
```

---

## Anti-Patterns

```
1. MongoDB-ni hər yerdə istifadə etmək:
   "Schema-free daha çevikdir" → relations lazım olanda problematik
   ACID lazım olanda → pain

2. Redis-i əsas DB kimi istifadə:
   Data restart-da itir (AOF konfiq etməsən)
   Backup mürəkkəb

3. Cassandra-da relational pattern:
   Cassandra JOIN bilmir → data denormalize edilməlidir
   Query-first modeling lazımdır

4. Premature sharding:
   1M record üçün sharding → unnecessary complexity
   Sharding ən son çarə

5. SELECT * istifadəsi:
   Lazımsız sütunlar → network + memory israfı
   Həmişə spesifik sütunlar seçin

6. N+1 query:
   ORM-in ən çox yayılan problemi
   Eager loading / JOIN lazımdır
```

---

## İndex Best Practices

```
Composite Index sırası:
  Equality → Range → Sort sırası
  WHERE user_id = ? AND created_at > ? ORDER BY created_at
  → INDEX(user_id, created_at)

Partial Index:
  WHERE status = 'active' olan sorğular çox:
  CREATE INDEX ON orders(user_id) WHERE status = 'active'
  → Daha kiçik, daha sürətli index

Covering Index:
  SELECT id, status FROM orders WHERE user_id = ?
  CREATE INDEX ON orders(user_id) INCLUDE (id, status)
  → Table access yoxdur (index-only scan)

Index Yanlış İstifadəsi:
  ✗ Hər sütuna index → yazma yavaşlar
  ✗ Low cardinality index (boolean) → faydasız
  ✗ Index on function: WHERE YEAR(created_at) = 2026
  ✓ WHERE created_at >= '2026-01-01' → range scan

EXPLAIN ANALYZE:
  PostgreSQL: EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) SELECT...
  MySQL: EXPLAIN FORMAT=JSON SELECT...
  Seq Scan → Index Scan: mümkün optimizasiya
```

---

## CAP Theorem — Praktiki Nümunələr

```
CAP Theorem: Distributed sistemdə 3-dən yalnız 2-ni seçə bilərsən

C — Consistency:    Hər node eyni datanı görür
A — Availability:   Hər sorğu cavab alır (error-suz)
P — Partition Tolerance: Network bölünmə zamanı sistem işləyir

Network partition real həyatda qaçılmazdır → P həmişə lazım
Buna görə əsl seçim: CP yoxsa AP?

CP sistemlər (Consistency + Partition):
  HBase, MongoDB (strong), Zookeeper, etcd
  Network partition → availability qurban verilir
  "Read your own writes" guarantee
  
  Nə zaman lazım:
  ✓ Bank balansı
  ✓ Inventory (oversell olmaz)
  ✓ Distributed lock

AP sistemlər (Availability + Partition):
  Cassandra, DynamoDB (default), CouchDB
  Network partition → consistency qurban verilir
  "Eventual consistency"
  
  Nə zaman lazım:
  ✓ Social media likes (approximate OK)
  ✓ Shopping cart (merge conflicts manageable)
  ✓ DNS (stale data OK for short time)

Real dünyadan:
  DynamoDB: AP by default, CP per-operation (strong read)
  Cassandra: quorum tunable (ONE, QUORUM, ALL)
  Zookeeper: CP (election, config)
  Redis: CP (single), AP (cluster with potential stale)
```

---

## PACELC Model

```
PACELC = CAP-in genişləndirilmiş versiyonu
  Eric Brewer-in CAP → PACELC (2012)

Normal vəziyyətdə (no partition):
  Latency (L) vs Consistency (C) trade-off

Full model:
  If Partition:    else (normal):
  A or C           L or C

Nümunələr:
  DynamoDB:        PA/EL  (partition=available, normal=low latency)
  Cassandra:       PA/EL  (same)
  HBase:           PC/EC  (partition=consistent, normal=consistent)
  MongoDB:         PC/EC  (strong mode)
  Spanner:         PC/EC  (TrueTime guarantees)
  
Trade-off in practice:
  Low latency + high consistency = impossible
  Spanner compromise: 5-10ms latency (cross-region: 100ms+)
  
Intervyuda:
  "Why Cassandra?" → "AP system, our use case tolerates staleness"
  "Why PostgreSQL?" → "EC system, financial data must be consistent"
```

---

## Consistency Models (Weak → Strong)

```
Zəifdən gücə:

1. Eventual Consistency:
   "Data eventually becomes consistent"
   DNS, CDN cache
   Cassandra default
   
2. Monotonic Read:
   "Same client always sees same or newer data"
   Read replica lag hides behind this
   
3. Read Your Own Writes:
   "You see your own updates immediately"
   Others may see stale data
   Amazon shopping cart
   
4. Causal Consistency:
   "If A causes B, everyone sees A before B"
   Instagram: if you like then comment, 
   others see like before comment
   
5. Sequential Consistency:
   "Global ordering of operations exists"
   Operations appear in same order for everyone
   
6. Linearizability (Strong Consistency):
   "Operations appear instantaneous, global order"
   Once write confirmed, all reads see it
   Zookeeper, etcd, Spanner
   Most expensive!

Rule of thumb:
  ACID SQL databases → linearizable (single node)
  Distributed SQL (Spanner, CockroachDB) → linearizable (multi-node)
  NoSQL (Cassandra, DynamoDB) → eventual by default
```
