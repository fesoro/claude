# Database Knowledge for Senior Backend Developers

Bu folder Senior Backend Developer (PHP / Laravel və s.) olaraq bilməli olduğun bütün database mövzularını əhatə edir. DBA / pure SQL developer mövzuları (OLAP tuning, gh-ost tooling, query planner hint internals, graph/geospatial niche) buradan çıxarılıb — yalnız backend dev-in sistem dizaynı və query yazarkən faydalanacağı mövzular saxlanılıb.

**Toplam: 79 mövzu** — sadədən mürəkkəbə doğru sıralanıb. Fayl nömrələri level-ə görə artır.

## Seviyyə göstəriciləri

Hər faylın başlığında **`# Mövzu adı (Level)`** formatı ilə göstərilir.

| Göstərici | Kimə uyğundur |
|-----------|--------------|
| ⭐ **Junior** | DB ilə yeni tanış olan / junior developer |
| ⭐⭐ **Middle** | 1-3 illik təcrübə / mid-level developer |
| ⭐⭐⭐ **Senior** | 3-5+ il / senior backend developer |
| ⭐⭐⭐⭐ **Lead** | Staff / principal səviyyə |

---

## ⭐ Junior (01–25) — Query Language & Fundamental-lar

### SQL Keyword-lər & Query Writing

Backend dev-in gündə yazdığı `SELECT/INSERT/UPDATE/DELETE`, JOIN-lər, filter-lər, funksiyalar.

1. [SELECT & Projection Basics](01-select-and-projections.md)
2. [WHERE Clause & Filter Operators](02-where-and-filter-operators.md)
3. [ORDER BY, LIMIT & OFFSET](03-order-by-limit-offset.md)
4. [Aggregate Functions, GROUP BY & HAVING](04-aggregate-group-by-having.md)
5. [JOIN Types & Practical Usage](05-join-types-usage.md)
6. [Subqueries, EXISTS, ANY, ALL](06-subqueries-and-exists.md)
7. [UNION, INTERSECT, EXCEPT](07-union-intersect-except.md)
8. [CASE Expressions](08-case-expressions.md)
9. [NULL Handling, COALESCE, NULLIF](09-null-handling-coalesce.md)

### Data Types & Functions

10. [Data Types Overview](10-data-types-overview.md)
11. [Type Casting & CAST](11-type-casting-and-cast.md)
12. [String Functions](12-string-functions.md)
13. [Date/Time Functions & Intervals](13-date-time-functions.md)

### DML & DDL

14. [INSERT Statement](14-insert-statement.md)
15. [UPDATE Statement](15-update-statement.md)
16. [DELETE & TRUNCATE](16-delete-and-truncate.md)
17. [Constraints (PK, FK, UNIQUE, CHECK, NOT NULL, DEFAULT)](17-constraints.md)
18. [DDL: CREATE / ALTER / DROP TABLE](18-ddl-create-alter-drop-table.md)
19. [Transaction Commands (BEGIN / COMMIT / ROLLBACK / SAVEPOINT)](19-transaction-commands.md)
20. [Sequences & AUTO_INCREMENT](20-sequences-auto-increment.md)

### Əsas Konseptlər

21. [ACID & Transactions](21-acid-and-transactions.md)
22. [Normalization & Denormalization](22-normalization.md)
23. [Migrations & Schema Management](23-migrations.md)
24. [N+1 Problem](24-n-plus-one-problem.md)
25. [MySQL vs PostgreSQL (müqayisə)](25-mysql-vs-postgresql.md)

---

## ⭐⭐ Middle (26–57) — Gündəlik İş

Mid-level developer-in hər gün istifadə etdiyi mövzular: indexing, query optimization, lock-lar, ORM patternləri.

### Indexing & Query Performance

26. [CREATE INDEX Syntax Basics](26-create-index-syntax-basics.md)
27. [Indexing & Index Algorithms (B-Tree, LSM-Tree)](27-indexing.md)
28. [Query Optimization & EXPLAIN](28-query-optimization.md)
29. [Performance Tuning](29-performance-tuning.md)

### Advanced SQL Features

30. [Window Functions & CTEs](30-window-functions-and-cte.md)
31. [JOIN Algorithms (Nested Loop, Hash, Merge)](31-join-algorithms.md)
32. [Set Operations & Advanced GROUP BY (ROLLUP, CUBE, GROUPING SETS)](32-set-operations-advanced-group-by.md)
33. [JSON, Full-Text Search & Advanced Features](33-advanced-features.md)
34. [Stored Procedures, Triggers & Views](34-stored-procedures-triggers-views.md)
35. [Character Encoding & Collation (utf8mb4, pitfalls)](35-character-encoding-and-collation.md)
36. [Hierarchical Data / Trees in SQL (Adjacency, Nested Set, Closure)](36-hierarchical-data-trees-in-sql.md)

### Pagination & Bulk

37. [Pagination Patterns (Offset, Cursor, Keyset/Seek)](37-pagination-patterns.md)
38. [Bulk Operations & UPSERT Patterns](38-bulk-operations-and-upsert.md)
39. [Cursor Operations & Streaming Large Datasets](39-cursors-and-streaming.md)

### Concurrency & Locking

40. [Isolation Levels](40-isolation-levels.md)
41. [Locking & Deadlocks](41-locking-and-deadlocks.md)
42. [Optimistic Locking & Version Columns](42-optimistic-locking.md)

### Data Modeling & Patterns

43. [Data Modeling & Schema Design](43-data-modeling.md)
44. [Database Design Patterns](44-design-patterns.md)
45. [ID Generation Strategies (UUID, ULID, Snowflake)](45-id-generation-strategies.md)
46. [Foreign Keys Deep Dive](46-foreign-keys-deep.md)
47. [Soft Deletes Patterns & Pitfalls](47-soft-deletes-patterns.md)
48. [Temporal Data & Slowly Changing Dimensions](48-temporal-data.md)
49. [Idempotency Keys & Dedupe Patterns](49-idempotency-keys.md)

### Infrastructure & Operations

50. [Connection Pooling (PgBouncer, ProxySQL)](50-connection-pooling.md)
51. [Backup & Recovery](51-backup-and-recovery.md)
52. [Database Monitoring & Observability](52-monitoring-observability.md)
53. [Database Security](53-database-security.md)
54. [Database Testing](54-database-testing.md)

### Caching & ORM

55. [Redis & Caching Strategies](55-redis-and-caching.md)
56. [Materialized Views](56-materialized-views.md)
57. [Eloquent / ORM Internals (Hydration, Chunking, Lazy)](57-eloquent-orm-internals.md)

---

## ⭐⭐⭐ Senior (58–77) — Senior Səviyyə

Senior backend developer-in sistem dizaynı və dərin mühəndislikdə istifadə etdiyi mövzular.

### Distributed Systems & Scaling

58. [CAP Theorem & Consistency Models](58-cap-theorem.md)
59. [Replication (Master-Slave, Master-Master, Logical)](59-replication.md)
60. [Sharding & Partitioning](60-sharding-and-partitioning.md)
61. [Database Scaling Strategies](61-database-scaling.md)
62. [High Availability & Failover (Patroni, Aurora)](62-high-availability-failover.md)
63. [Distributed Transactions & Saga Pattern](63-distributed-transactions.md)
64. [Multi-Tenancy Patterns](64-multi-tenancy-patterns.md)
65. [Change Data Capture (CDC)](65-change-data-capture.md)

### Internals & Deep Concepts

66. [MVCC Deep Dive (Multi-Version Concurrency Control)](66-mvcc-deep-dive.md)
67. [Storage Internals (WAL, Buffer Pool, Pages)](67-storage-internals-wal-buffer-pool.md)
68. [VACUUM, Autovacuum & Bloat (PostgreSQL)](68-vacuum-and-bloat.md)
69. [PostgreSQL Specific Features (LISTEN/NOTIFY, Advisory Locks, RLS, Arrays)](69-postgresql-specific-features.md)

### Database-Specific Features

70. [MySQL Specific Features (Generated Columns, JSON, Roles, FTS, Performance Schema)](70-mysql-specific-features.md)
71. [Database Refactoring (Expand-Contract, Strangler)](71-database-refactoring.md)
72. [Database Anti-patterns](72-database-anti-patterns.md)

### Specialized Databases

73. [NoSQL Databases (MongoDB, Cassandra, DynamoDB, Supabase)](73-nosql-databases.md)
74. [Time-Series Databases (TimescaleDB, InfluxDB)](74-time-series-databases.md)
75. [Elasticsearch](75-elasticsearch.md)
76. [Search Engines: Meilisearch, Algolia, Typesense, Sphinx](76-search-engines.md)
77. [Cloud Databases (RDS, Aurora, Cloud SQL, PlanetScale, Neon, Supabase)](77-cloud-databases.md)

---

## ⭐⭐⭐⭐ Lead (78–79) — Staff / Principal Səviyyə

78. [NewSQL & Distributed SQL (CockroachDB, Spanner, TiDB, YugabyteDB)](78-newsql-distributed-sql.md)
79. [Vector Databases (pgvector, Pinecone, Weaviate, Qdrant, Milvus)](79-vector-databases.md)

---

## Oxuma tövsiyələri

### Junior → Middle keçid (6-12 ay hədəfi)
**Bütün Junior (01-25)** + Middle-dən: Indexing, Query Optimization, Isolation Levels, Locking, N+1, Connection Pooling, Data Modeling, Backup.

### Middle → Senior keçid (1-3 il hədəfi)
Bütün **Middle (26-57)** + Senior-dən: Replication, Sharding, CAP, MVCC, Distributed Transactions, HA/Failover, NoSQL, CDC.

### Senior → Lead (çoxillik dərinləşmə)
Bütün **Senior (58-77)** + **Lead (78-79)** + kənar materiallar (Designing Data-Intensive Applications, PostgreSQL Internals).

### Interview hazırlığı (1-2 ay)
1. **Junior (tam)** → Query yazma, DML/DDL, constraints, transactions
2. **Middle core**: Indexing, Optimization, Isolation, Locking, Pagination, UPSERT, Data Modeling, ORM, Caching
3. **Senior core**: CAP, Replication, Sharding, MVCC, Distributed Transactions, Multi-Tenancy, Anti-patterns, NoSQL
4. Hər bölmənin sonundakı **Interview sualları** bölməsini keç

