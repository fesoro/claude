# Database Selection Criteria (Senior ⭐⭐⭐)

## İcmal
Düzgün database seçimi sistem dizaynının ən kritik qərarlarından biridir. Yanlış seçim sonradan böyük migration xərci, performance problemi və ya data integrity itkisi ilə nəticələnir. Interview-larda "Hansı database istifadə edərdiniz?" sualına "Depends..." ilə başlamaq — əsaslandırmaq şərti ilə — düzgün yanaşmadır.

## Niyə Vacibdir
Her şirkət bu qərarı verir. "MySQL vs PostgreSQL", "SQL vs NoSQL", "Redis vs Memcached" — bunlar real dünyada həftələr müzakirə olunan qərarlardır. Senior mühəndis yalnız "PostgreSQL istifadə et" demir, niyə PostgreSQL-i seçdiyini, alternativlərin nə zaman daha uyğun olduğunu izah edə bilir. Bu mövzu Google, Amazon, Stripe, Square interview-larında mütləq çıxır.

## Əsas Anlayışlar

### 1. Relational (SQL) Databases
**PostgreSQL**
- ACID tam dəstəklənir
- Güclü JSON/JSONB dəstəyi (hybrid SQL+NoSQL)
- Advanced indexing: GiST, GIN, BRIN, partial indexes
- Window functions, CTEs, complex joins
- PostGIS (geospatial), pgvector (AI/ML vectors)
- Logical replication, streaming replication
- Use case: Financial systems, user data, complex relations

**MySQL / MariaDB**
- Daha geniş yayılmış, daha çox hosting support
- InnoDB: ACID, MVCC
- MySQL 8.0: JSON, window functions
- Use case: Web apps, read-heavy workloads

**Seç PostgreSQL əgər:**
- Complex queries, aggregations
- JSONB hybrid data
- Advanced analytics lazımdırsa
- Strict data integrity

**Seç MySQL əgər:**
- Legacy system compatibility
- Hosted MySQL (RDS, PlanetScale) yaxşı dəstəklənir
- Read-heavy, simple queries

### 2. Document Stores (MongoDB, Firestore)
```
Document nümunəsi:
{
  "user_id": "u123",
  "name": "Ali",
  "addresses": [
    {"type": "home", "city": "Baku"},
    {"type": "work", "city": "Sumgayit"}
  ],
  "preferences": {"theme": "dark", "lang": "az"}
}
```

**Nə zaman Document DB:**
- Schema çox dəyişir (product catalog, CMS)
- Nested/hierarchical data
- Rapid prototyping
- Per-tenant schema variation (multi-tenant SaaS)

**Nə zaman Document DB istifadə ETMƏMƏLİ:**
- Complex multi-document transactions
- Strong consistency across collections
- Aggregation-ağır analytics (SQL daha yaxşıdır)

### 3. Key-Value Stores (Redis, DynamoDB, Riak)
**Redis:**
- In-memory, sub-millisecond latency
- Data structures: String, List, Set, Sorted Set, Hash, Stream
- Persistence: RDB snapshots, AOF log
- Use case: Caching, session, rate limiting, pub/sub, job queue, leaderboards

**DynamoDB:**
- Serverless, managed, auto-scaling
- Single-digit millisecond at any scale
- Partition key + sort key model
- Global Tables (multi-region)
- Use case: Web scale apps, serverless, IoT, gaming

**Qayda:** Key-value store əgər primary key ilə access pattern-lərin hamısı müəyyəndirsə seç.

### 4. Wide-Column Stores (Cassandra, HBase, ScyllaDB)
```
Row key: user:12345
Columns: name=Ali, email=ali@..., created_at=...

Time-series:
Row key: metrics:server1:2024-01-15
Columns: 00:00=cpu:45, 00:01=cpu:47, ...
```

**Cassandra gücləri:**
- Write-optimized (LSM tree)
- Linear horizontal scalability
- Multi-datacenter replication baked-in
- No single point of failure
- Use case: Time-series, IoT, messaging, activity logs

**Cassandra zəiflikləri:**
- Eventual consistency (tunable)
- No JOIN, no complex queries
- Data model joins business logic
- Write amplification (compaction)

### 5. Search Engines (Elasticsearch, OpenSearch, Meilisearch)
- Full-text search, relevance ranking
- Inverted index
- Faceted search, aggregations
- Near real-time (NRT) indexing
- Use case: E-commerce search, log analytics, document search

**Qayda:** Elasticsearch primary database olmaz — secondary index kimi istifadə et.

### 6. Time-Series Databases (InfluxDB, TimescaleDB, Victoria Metrics)
- Insert-heavy, range query-optimized
- Automatic downsampling (retention policies)
- Continuous queries, alerting
- Use case: Metrics, monitoring, IoT sensor data, financial tick data

### 7. Graph Databases (Neo4j, Amazon Neptune, ArangoDB)
- Nodes (vertex) + Edges (relationship)
- Traversal queries: "friends of friends who live in Baku"
- Shortest path, recommendation graphs
- Use case: Social networks, fraud detection, knowledge graphs, recommendation systems

**SQL-də graph:**
```sql
-- 3-deep traversal SQL-də çirkin olur
-- Graph DB-də: MATCH (user)-[:FRIEND*1..3]->(other)
```

### 8. Data Warehouse (BigQuery, Snowflake, Redshift)
- Column-oriented storage
- OLAP: aggregations, analytics on billions of rows
- Separate from OLTP (operational) database
- Batch load or CDC streaming
- Use case: Business intelligence, reporting, data analytics

### 9. Database Seçim Matrisi

| Scenario | Tövsiyə |
|----------|---------|
| User data, transactions | PostgreSQL |
| Session, cache, rate limit | Redis |
| Product catalog, CMS | MongoDB |
| IoT, metrics, time-series | InfluxDB / TimescaleDB |
| Activity feed, log | Cassandra |
| Full-text search | Elasticsearch |
| Recommendations, fraud | Neo4j |
| Analytics, BI | BigQuery / Snowflake |
| Serverless web scale | DynamoDB |

### 10. Polyglot Persistence
Bir sistem bir neçə database istifadə edə bilər:
```
E-commerce:
- PostgreSQL: Orders, inventory, users (ACID needed)
- Redis: Cart, session, rate limiting
- Elasticsearch: Product search
- S3 + CDN: Product images
- InfluxDB: Sales analytics
```

Bu arxitektura mürəkkəbdir, operational overhead artır. Yalnız skalada lazım olduqda.

### 11. CAP/PACELC Nəzərindən
```
PostgreSQL: CA (single node), CP (with replication)
MongoDB: CP (default), eventual (w:0)
Cassandra: AP (tunable consistency: QUORUM = stronger)
DynamoDB: AP (eventual default), CP mümkün
Redis: CA (single), AP (Cluster)
```

### 12. Operational Considerations
- **Managed vs Self-hosted**: RDS vs EC2 PostgreSQL
- **Cost**: DynamoDB vs PostgreSQL on-demand
- **Backup & Recovery**: RPO, RTO tələbləri
- **Compliance**: GDPR, PCI-DSS ilə uyğunluq

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Data access pattern-lərini soruşun (read-heavy? write-heavy? complex queries?)
2. Consistency tələbini soruşun (strong? eventual?)
3. Scale tələbini soruşun (1M? 1B records?)
4. "Database X seçirəm, çünki Y, alternativ Z bu sebabdən daha az uyğundur" formatı
5. İkincil database-ləri (cache, search) əlavə et

### Ümumi Namizəd Səhvləri
- Hər şey üçün MySQL istifadə etmək
- NoSQL-i "scale üçün" seçmək (əsaslandırmasız)
- PostgreSQL-in JSON dəstəyini bilməmək
- Time-series üçün MySQL istifadə etmək (anti-pattern)
- ACID tələbi olduqda Cassandra seçmək

### Senior vs Architect Fərqi
**Senior**: Əsas database seçimini əsaslandırır, secondary stores əlavə edir, polyglot persistence tətbiq edir.

**Architect**: Data migration strategiyasını planlaşdırır, vendor lock-in risk-ini qiymətləndirir, database siyasəti (standarts across organization) müəyyən edir, SLA/SLO database layer üçün hesablayır, total cost of ownership analiz edir.

## Nümunələr

### Tipik Interview Sualı
"Design the data layer for Uber-like ride-sharing app."

### Güclü Cavab
```
Uber-like app data tələbləri:
1. User/Driver profiles — structured, ACID
2. Active ride state — real-time, fast updates
3. Geolocation updates — high-frequency writes
4. Trip history — time-ordered, analytics
5. Payment records — ACID, compliance
6. Search (driver nearby) — geospatial queries

Database seçimləri:

PostgreSQL (primary):
  - Users, drivers, payments
  - Trip history (archived rides)
  - ACID, complex queries, reporting
  - PostGIS extension for geospatial
  Tables: users, drivers, trips, payments

Redis:
  - Active ride state (key: ride:{id})
  - Driver availability (Sorted Set: geo-index)
  - Session tokens
  - TTL-based cleanup

Elasticsearch:
  - Driver search by location + rating + filters
  - OR: Redis GEO commands for simpler geo queries
  - GEORADIUS: GET drivers within 5km

Cassandra (yüksək yazı həcmi):
  - Driver location updates (hər 4 saniyə, 1M driver)
  - 250K writes/sec → Cassandra ideal
  - Partition key: driver_id, Sort: timestamp

BigQuery / Redshift:
  - Analytics, reports
  - Batch ETL from PostgreSQL
  - BI dashboard-ları
```

### Data Flow
```
Driver App → Location Service → Redis GEO + Cassandra
Rider App  → Matching Service → Redis (active rides)
           → PostgreSQL (confirmed trips)
Payment    → Payment Service → PostgreSQL (ACID)
Analytics  → ETL → BigQuery
```

## Praktik Tapşırıqlar
- Sosial media üçün database dizayn edin (user, post, follow, feed)
- E-commerce üçün polyglot persistence arxitekturası qurun
- 1B row-lu time-series data üçün en uyğun DB-i seçin, əsaslandırın
- PostgreSQL JSONB vs MongoDB performans müqayisəsi
- Redis Sorted Set ilə leaderboard implementasiya edin

## Əlaqəli Mövzular
- [07-database-sharding.md] — Horizontal sharding
- [05-caching-strategies.md] — Cache layer
- [12-cap-theorem-practice.md] — Consistency guarantees
- [22-data-partitioning.md] — Partitioning strategies
- [19-cqrs-practice.md] — CQRS with multiple data stores
