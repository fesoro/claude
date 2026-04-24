# Database Knowledge for Senior Software Engineers

Bu folder Senior Software Engineer olaraq bilməli olduğun bütün database mövzularını əhatə edir.

**Toplam: 62 mövzu** — sadədən mürəkkəbə doğru sıralanmışdır.

## Seviyyə göstəriciləri

Hər fayl içində **`> **Seviyyə:** X ⭐`** etiketi vardır. Oxumağa başlamazdan əvvəl seviyyəni görə biləcəksən.

| Göstərici | Kimə uyğundur |
|-----------|--------------|
| ⭐ **Beginner** | Database ilə yeni tanış olan, junior developer |
| ⭐⭐ **Intermediate** | 1-3 illik təcrübə, işləyən mühəndis (mid-level) |
| ⭐⭐⭐ **Advanced** | 3-5+ il, senior mühəndis |
| ⭐⭐⭐⭐ **Expert** | Staff / principal səviyyə, DB internals ekspertliyi |

---

## ⭐ Beginner — Əsaslar

DB-yə yeni başlayanlar üçün fundamental anlayışlar. Hər senior bunları möhkəm bilməlidir.

1. [ACID & Transactions](01-acid-and-transactions.md)
2. [Normalization & Denormalization](06-normalization.md)
3. [Migrations & Schema Management](16-migrations.md)
4. [N+1 Problem](10-n-plus-one-problem.md)
5. [MySQL vs PostgreSQL (müqayisə)](14-mysql-vs-postgresql.md)

---

## ⭐⭐ Intermediate — Gündəlik İş

Mid-level developer-in hər gün istifadə etdiyi mövzular: indexing, query optimization, lock-lar, ORM patternləri.

### SQL Core & Query Language
6. [Window Functions & CTEs](36-window-functions-and-cte.md)
7. [JOIN Types & Algorithms](29-join-algorithms.md)
8. [Set Operations & Advanced GROUP BY (ROLLUP, CUBE, GROUPING SETS)](59-set-operations-advanced-group-by.md)
9. [JSON, Full-Text Search & Advanced Features](20-advanced-features.md)
10. [Stored Procedures, Triggers & Views](11-stored-procedures-triggers-views.md)
11. [Character Encoding & Collation (utf8mb4, UTF8, collation pitfalls)](60-character-encoding-and-collation.md)
12. [Hierarchical Data / Trees in SQL (Adjacency, Nested Set, Closure)](61-hierarchical-data-trees-in-sql.md)

### Indexing & Query Performance
13. [Indexing & Index Algorithms (B-Tree, LSM-Tree)](03-indexing.md)
14. [Query Optimization & EXPLAIN](04-query-optimization.md)
15. [Performance Tuning](18-performance-tuning.md)
16. [Pagination Patterns (Offset, Cursor, Keyset/Seek)](37-pagination-patterns.md)
17. [Bulk Operations & UPSERT Patterns](38-bulk-operations-and-upsert.md)
18. [Cursor Operations & Streaming Large Datasets](27-cursors-and-streaming.md)

### Concurrency & Locking
19. [Isolation Levels](02-isolation-levels.md)
20. [Locking & Deadlocks](05-locking-and-deadlocks.md)
21. [Optimistic Locking & Version Columns](39-optimistic-locking.md)

### Data Management
22. [Data Modeling & Schema Design](26-data-modeling.md)
23. [Database Design Patterns](19-design-patterns.md)
24. [ID Generation Strategies (UUID, ULID, Snowflake)](42-id-generation-strategies.md)
25. [Foreign Keys Deep Dive](57-foreign-keys-deep.md)
26. [Soft Deletes Patterns & Pitfalls](40-soft-deletes-patterns.md)
27. [Temporal Data & Slowly Changing Dimensions](24-temporal-data.md)
28. [Idempotency Keys & Dedupe Patterns](56-idempotency-keys.md)

### Infrastructure & Operations
29. [Connection Pooling (PgBouncer, ProxySQL)](09-connection-pooling.md)
30. [Backup & Recovery](17-backup-and-recovery.md)
31. [Database Monitoring & Observability](25-monitoring-observability.md)
32. [Database Security](15-database-security.md)
33. [Database Testing](23-database-testing.md)

### Caching & ORM
34. [Redis & Caching Strategies](13-redis-and-caching.md)
35. [Materialized Views](44-materialized-views.md)
36. [Eloquent / ORM Internals (Hydration, Chunking, Lazy)](43-eloquent-orm-internals.md)

---

## ⭐⭐⭐ Advanced — Senior Səviyyə

Senior mühəndisin bilməli olduğu dərin mövzular: distributed systems, storage internals, NoSQL, specialized DBs.

### Distributed Systems & Scaling
37. [CAP Theorem & Consistency Models](12-cap-theorem.md)
38. [Replication (Master-Slave, Master-Master, Logical)](07-replication.md)
39. [Sharding & Partitioning](08-sharding-and-partitioning.md)
40. [Database Scaling Strategies](30-database-scaling.md)
41. [High Availability & Failover (Patroni, Orchestrator, Aurora)](49-high-availability-failover.md)
42. [Distributed Transactions & Saga Pattern](21-distributed-transactions.md)
43. [Multi-Tenancy Patterns](41-multi-tenancy-patterns.md)
44. [Change Data Capture (CDC)](22-change-data-capture.md)

### Internals & Deep Concepts
45. [MVCC Deep Dive (Multi-Version Concurrency Control)](45-mvcc-deep-dive.md)
46. [Storage Internals (WAL, Buffer Pool, Pages)](58-storage-internals-wal-buffer-pool.md)
47. [VACUUM, Autovacuum & Bloat (PostgreSQL)](47-vacuum-and-bloat.md)
48. [PostgreSQL Specific Features (LISTEN/NOTIFY, Advisory Locks, RLS, Arrays)](46-postgresql-specific-features.md)
49. [Query Hints & Planner Control (MySQL hints, pg_hint_plan)](62-query-hints-and-planner-control.md)
50. [Database Refactoring (Expand-Contract, Strangler)](50-database-refactoring.md)
51. [Database Anti-patterns](55-database-anti-patterns.md)

### NoSQL & Specialized Databases
52. [NoSQL Databases (MongoDB, Cassandra, DynamoDB, Supabase)](28-nosql-databases.md)
53. [Graph Databases (Neo4j)](35-graph-databases.md)
54. [Time-Series Databases (TimescaleDB, InfluxDB)](33-time-series-databases.md)
55. [OLAP, Data Warehousing & Columnar DBs (ClickHouse)](34-olap-data-warehousing.md)
56. [Geospatial Databases (PostGIS, MySQL Spatial)](51-geospatial-postgis.md)

### Search & Cloud
57. [Elasticsearch](31-elasticsearch.md)
58. [Search Engines: Meilisearch, Algolia, Typesense, Sphinx](32-search-engines.md)
59. [Cloud Databases (RDS, Aurora, Cloud SQL, PlanetScale, Neon, Supabase)](54-cloud-databases.md)

---

## ⭐⭐⭐⭐ Expert — Staff/Principal Səviyyə

Dərin ixtisaslaşma tələb edən mövzular: zero-downtime operations, distributed SQL, vector/AI workloads.

60. [Online Schema Changes (gh-ost, pt-osc, pg_repack, zero-downtime DDL)](48-online-schema-changes.md)
61. [NewSQL & Distributed SQL (CockroachDB, Spanner, TiDB, YugabyteDB)](53-newsql-distributed-sql.md)
62. [Vector Databases (pgvector, Pinecone, Weaviate, Qdrant, Milvus)](52-vector-databases.md)

---

## Oxuma tövsiyələri

### Junior → Mid keçid (6-12 ay hədəfi)
Beginner bölməsi + Intermediate-dən: Indexing, Query Optimization, Isolation Levels, Locking, N+1, Connection Pooling, Data Modeling, Backup.

### Mid → Senior keçid (1-3 il hədəfi)
Bütün Intermediate + Advanced-dən: Replication, Sharding, CAP, MVCC, Distributed Transactions, HA/Failover, NoSQL, CDC.

### Senior → Staff (çoxillik dərinləşmə)
Bütün Advanced + Expert bölməsi + kənar materiallar (Designing Data-Intensive Applications, PostgreSQL Internals).

### Interview hazırlığı (1-2 ay)
1. Beginner (tam) → Intermediate (tam)
2. Advanced-dən: CAP, Replication, Sharding, MVCC, Distributed Transactions, Multi-Tenancy, Anti-patterns
3. Hər bölmənin sonundakı **Interview sualları** bölməsini keç.
