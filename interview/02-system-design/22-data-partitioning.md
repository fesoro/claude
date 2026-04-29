# Data Partitioning Strategies (Lead ⭐⭐⭐⭐)

## İcmal
Data partitioning, böyük verilənlər bazasını daha kiçik, idarə edilə bilən parçalara bölmə strategiyasıdır. Horizontal partitioning (sharding), vertical partitioning, functional decomposition — hər birinin fərqli use case-i, trade-off-u və implementation complexity-si var. Bu mövzu system design interview-larında tez-tez çıxır, çünki scale etmək lazım olan demək olar ki, hər sistemdə partitioning qərarı verilməlidir.

## Niyə Vacibdir
Tək bir database serveri bir müəyyən nöqtəyə qədər scale edə bilər — sonra ya RAM, ya disk I/O, ya da CPU bottleneck olur. Vertical scaling (daha güclü hardware) bahalıdır və son nöqtəsı var. Partitioning isə yükü bir neçə node arasında paylaşdırır. Lead mühəndis yalnız "sharding istifadə edirəm" deməklə kifayətlənmir — hansı partition key, necə rebalance, cross-partition query-lər necə idarə olunur, bu sualları cavablandırmalıdır.

## Əsas Anlayışlar

### 1. Partitioning Növləri
```
Horizontal Partitioning (Sharding):
  Eyni sxemli table-ları fərqli node-lara böl
  User table → Shard 1: user_id 1-1M
               Shard 2: user_id 1M-2M
               Shard 3: user_id 2M-3M

Vertical Partitioning:
  Eyni cədvəlin müxtəlif sütunlarını ayır
  users table → users_core (id, email, name)
              + users_profile (id, bio, avatar, preferences)
              + users_billing (id, card_last4, billing_addr)

  Cold vs Hot columns:
    Hot: Hər query-də lazımdır (id, name, email)
    Cold: Nadir oxunur (bio, extended_profile)
    Ayrılması: I/O azalır, cache efficiency artır

Functional Partitioning:
  Business domain-ə görə ayır
  Monolith → Order DB, User DB, Inventory DB, Payment DB
  (Microservice database pattern)
```

### 2. Sharding Strategiyaları

**Range-based Sharding:**
```
Partition key-in dəyər aralığına görə böl:

user_id 1-1,000,000    → Shard 1
user_id 1,000,001-2M   → Shard 2
user_id 2,000,001-3M   → Shard 3

Pros:
  Range query-lər effektivdir
  "user_id BETWEEN 500K AND 700K" → sadəcə Shard 1
  Simple routing logic

Cons:
  Hotspot riski: Son yaradılan user-lər hər zaman son shard-a düşür
  Shard 3 hər zaman daha yüklüdür (recent activity)
  Auto-increment ID → time-based skew

Use case:
  Time-series data (date range shard)
  Geospatial (region range)
  Ordered data
```

**Hash-based Sharding:**
```
Partition key-in hash-i əsasında böl:

shard_id = hash(user_id) % num_shards

user_id = 12345 → hash → 3 → Shard 3
user_id = 67890 → hash → 1 → Shard 1

Pros:
  Uniform distribution (hotspot yoxdur)
  Predictable routing

Cons:
  Range query-lər ineffektivdir (bütün shard-lara get)
  Shard sayı dəyişdikdə rehash lazımdır
  num_shards = 4 → num_shards = 5 → 80% data moves!

Fix: Consistent Hashing (virtual nodes)
  → Data movement minimum olur
  (bax: 11-consistent-hashing.md)
```

**Directory-based Sharding:**
```
Lookup table shard mapping saxlayır:

Directory Service:
  user_id → shard_id

  user 1001 → shard_3
  user 1002 → shard_1
  user 1003 → shard_3

Pros:
  Flexible: İstənilən mapping mümkündür
  Migration asandır: Sadəcə directory yenilə

Cons:
  Directory = single point of failure
  Directory cache olmasa → hər query üçün lookup overhead
  Directory consistency problem

Use case:
  Complex sharding logic lazım olduqda
  Irregular data distribution
```

**Geographic Sharding:**
```
User location-a görə:
  EU users     → EU Shard (GDPR compliance)
  US users     → US Shard
  APAC users   → APAC Shard

Pros:
  Data residency requirements (GDPR, CCPA)
  Low latency: User öz bölgəsindəki shard-a yaxın

Cons:
  Cross-region query-lər yavaşdır
  Uneven distribution (bir bölgə böyüyürsə)

Use case:
  Multi-region systems
  Compliance requirements
  Latency-sensitive global applications
```

### 3. Partition Key Seçimi
```
Yaxşı partition key kriterlər:
  1. High cardinality: Az unikal dəyər → hotspot
     BAD:  status (active/inactive)
     GOOD: user_id

  2. Even distribution: Bütün partition-lar bərabər yük
     BAD:  created_at (yeni data hər zaman son partition-a)
     GOOD: user_id modulo

  3. Query pattern alignment: Ən çox istifadə olunan filter
     E-commerce: order_id (sifariş lookup)
     Social:     user_id (user-ın postları)
     IoT:        device_id (sensor history)

  4. Immutability: Partition key dəyişməməlidir
     BAD:  email (user onu dəyişə bilər)
     GOOD: user_id (ümumiyyətlə dəyişmir)

Composite partition key:
  (tenant_id, user_id) — multi-tenant sistemdə
  tenant_id: coarse partition
  user_id: fine-grained within tenant
```

### 4. Cross-Partition Query Problemi
```
Sadə query (single partition):
  SELECT * FROM orders WHERE user_id = 1001
  → Shard routing: hash(1001) % 4 = shard_2
  → Tək shard-a query → fast

Cross-partition query (problematik):
  SELECT COUNT(*) FROM orders WHERE status = 'pending'
  → Bütün shard-lara query göndər
  → Nəticələri aggregate et (scatter-gather)
  → N shard → N parallel query → latency

Scatter-gather:
  Coordinator → Shard 1, 2, 3, 4 (parallel)
  Wait for all responses
  Aggregate: SUM(count_from_each_shard)

  Latency: max(slowest_shard_response)
  Bottleneck: Coordinator merge overhead

Alternativlər:
  1. Denormalize: Read-optimized aggregate table (ayrı cədvəl)
  2. CQRS: Read model-i ayrıca maintain et
  3. Dedicated analytics DB: OLAP (Snowflake, BigQuery)
  4. Pre-compute: Background job aggregate hesablar
```

### 5. Hotspot Problemi
```
Write hotspot:
  Auto-increment ID + range shard → son shard hər zaman yüklü
  UUID v4: Random → hash-based shard → even distribution
  ULID / UUID v7: Sortable + distributed → time-based hot spot aradan qalxır

Read hotspot:
  "Celebrity problem": Məşhur user-in profili hər zaman daha çox oxunur
  Twitter: Selena Gomez-in 300M follower-i var → onun tweet-i hot shard-da
  
  Fix:
  1. Cache layer (CDN / Redis): hot key-ləri cache et
  2. Read replicas: Hot shard-lar üçün əlavə replika
  3. Split hot user: Ayrı shard-a köç et (directory-based)

Time-series hotspot:
  Log data: Son time-bucket hər zaman write-heavy
  
  Fix:
  1. Time + hash composite key: (timestamp_bucket, device_id_hash)
  2. Per-time-range partition → old partitions read-only
```

### 6. Shard Rebalancing
```
Nə zaman rebalance lazımdır:
  Shard biri digərindən çox böyüyür
  Yeni shard əlavə olunur
  Shard aradan götürülür

Naive rebalance (hash shard sayı dəyişir):
  4 shard → 5 shard
  hash(key) % 4 → hash(key) % 5
  ~80% data fərqli shard-a düşür → massive data migration!

Consistent hashing rebalance:
  4 shard → 5 shard
  Yalnız ~20% data köçür
  Gradual migration mümkündür

Online migration stratejiyası:
  1. "Double write": Yeni data həm köhnə, həm yeni shard-a yazılır
  2. Background migration: Köhnə data tədricən yeni shard-a köçür
  3. Verification: Data consistency check
  4. Switch: Read/write yalnız yeni shard-a keçir
  5. Cleanup: Köhnə shard-dan data silinir

Zero-downtime shard split:
  Shard 2 → Shard 2a (user_id even) + Shard 2b (user_id odd)
  1. Shard 2-nin replikasını yarat
  2. Replica 2a: Odd user-ları sil
  3. Replica 2b: Even user-ları sil
  4. DNS/routing switch
```

### 7. Distributed Joins
```
Problem:
  orders tablosu → Order Shard (order_id-ə görə)
  users tablosu  → User Shard (user_id-ə görə)

  SELECT u.name, o.total
  FROM users u JOIN orders o ON u.id = o.user_id
  WHERE u.country = 'AZ'

  → User data ayrı shard, Order data ayrı shard
  → Cross-shard join = VERY expensive

Həll yolları:

1. Colocation (Same Shard Key):
   user_id həm User, həm Order shard key-dir
   → Eyni user-in bütün data-sı eyni shard-da
   → Join local olur

2. Denormalization:
   orders tablosuna user_name, user_country sahəsini əlavə et
   → Join lazım deyil

3. Application-level join:
   User Service-dən user-ları çək (HTTP)
   Order Service-dən order-ları çək (HTTP)
   Application-da merge et
   → N+1 problem riski (batch with IN clause)

4. Analytics DB:
   ETL ile User + Order data-sını ayrı OLAP DB-ə köç et
   Dort complex join burada
```

### 8. Partitioning vs Sharding vs Replication
```
Partitioning (logical):
  Eyni DB-in içindəki data bölgüsü
  PostgreSQL table partitioning (same instance)
  → Single DB, multiple tables

Sharding (horizontal partitioning, physical):
  Data fərqli physical node-lara bölünür
  → Multiple DB instances, distributed

Replication:
  Eyni data-nın kopyası multiple node-da
  → High availability, read scaling
  → Data bölünmür, çoxaldılır

Tipik arxitektura:
  Sharding + Replication:
  Shard 1: Primary + 2 replicas
  Shard 2: Primary + 2 replicas
  Shard 3: Primary + 2 replicas
  → Write: Shard primary-ə
  → Read: Replikalara (load balance)
```

### 9. PostgreSQL Table Partitioning
```sql
-- Range Partitioning (date-based)
CREATE TABLE orders (
    id          BIGSERIAL,
    user_id     BIGINT,
    created_at  TIMESTAMP,
    total       DECIMAL(10,2)
) PARTITION BY RANGE (created_at);

CREATE TABLE orders_2024_q1 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-04-01');

CREATE TABLE orders_2024_q2 PARTITION OF orders
    FOR VALUES FROM ('2024-04-01') TO ('2024-07-01');

-- Hash Partitioning (even distribution)
CREATE TABLE users (
    id       BIGSERIAL,
    email    VARCHAR(255),
    name     VARCHAR(255)
) PARTITION BY HASH (id);

CREATE TABLE users_0 PARTITION OF users
    FOR VALUES WITH (MODULUS 4, REMAINDER 0);

CREATE TABLE users_1 PARTITION OF users
    FOR VALUES WITH (MODULUS 4, REMAINDER 1);

-- Partition pruning (automatic):
-- SELECT * FROM orders WHERE created_at > '2024-04-01'
-- → Yalnız orders_2024_q2 scan edilir
```

### 10. Trade-offs Cədvəli
```
Strategy         | Distribution | Range Query | Rebalance | Complexity
Range-based      | Uneven risk  | Excellent   | Easy      | Low
Hash-based       | Even         | Poor        | Hard      | Medium
Consistent hash  | Even         | Poor        | Easy      | High
Directory-based  | Flexible     | Flexible    | Easy      | High
Geographic       | By region    | By region   | Medium    | Medium
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Əvvəlcə partitioning-in niyə lazım olduğunu motivasiya et: "Tək node daha scale edə bilmir"
2. Partition key seçimini izah et: "Query pattern-a baxıram, distribution-ı yoxlayıram, immutability lazımdır"
3. Seçilmiş strategiyanın trade-off-larını sırala
4. Cross-partition query probleminə həll təklif et
5. Hotspot-a qarşı mexanizm qeyd et

### Follow-up Suallar
- "Shard sayını ikiqat artırsan nə olar?" → Consistent hashing cavabı
- "Cross-shard transaction lazım olsa?" → Saga/2PC cavabı
- "Partition key yanlış seçilsə?" → Hotspot, rebalance, migration
- "Single-tenant vs multi-tenant necə fərqlənir?" → Tenant-per-shard vs shared shard

### Ümumi Namizəd Səhvləri
- Partition key seçimini əsaslandırmadan "user_id istifadə edirəm" demək
- Cross-partition query-ləri nəzərə almamaq
- Hotspot problemini görməmək
- Shard rebalancing-in nə qədər mürəkkəb olduğunu bilməmək
- Partitioning ilə replication-ı qarışdırmaq

### Senior vs Architect Fərqi
**Senior**: Hash vs range sharding seçir, partition key əsaslandırır, hotspot-ı tanıyır, consistent hashing-i bilir.

**Architect**: Partition key seçimini query pattern analizi ilə justify edir (access logs-a baxır), cross-partition query-lər üçün read model arxitekturası dizayn edir (CQRS, denormalization), online rebalancing strategiyası planlaşdırır (zero downtime), multi-tenant partitioning-i tenant isolation + cost allocation ilə birlikdə dizayn edir, partition-level compliance (GDPR right-to-erasure — partition drop ilə effektiv erasure).

## Nümunələr

### Tipik Interview Sualı
"Design the data layer for a multi-tenant SaaS application with 10,000 tenants, where the largest tenant has 100x more data than the smallest."

### Güclü Cavab
```
Multi-tenant SaaS partitioning:

Problem analizi:
  10,000 tenants — uneven data distribution
  Large tenant: 100x daha çox data
  Compliance: EU tenants → data EU-da olmalıdır

Partitioning strategiyası:
  Tier 1 — Large tenants (top 100):
    Dedicated shard per tenant
    Tenant ID = shard key
    Tam izolasiya, compliance asandır

  Tier 2 — Medium tenants (1,000):
    Group sharding: 10 tenant per shard
    Shard = consistent hash(tenant_id)

  Tier 3 — Small tenants (8,900):
    Shared shard: 100+ tenant per shard
    Shard = hash(tenant_id) % 20

Directory service:
  tenant_id → shard_id (cache with TTL 5 min)
  Redis cluster: tenant routing table

Schema:
  Hər table-da tenant_id sütunu (row-level isolation)
  Composite index: (tenant_id, primary_key)
  PostgreSQL RLS (Row Level Security) + tenant context

Cross-tenant query (yalnız admin):
  Scatter-gather: Bütün shard-lara parallel query
  Result stream: Lazy aggregate

Geographic shard placement:
  EU tenants → EU shard cluster
  US tenants → US shard cluster
  Tenant on-boarding: tenant metadata-da region flag

Hotspot handling:
  Large tenant spike → Tenant-ın replica artırılır
  KEDA + HPA: Tenant-level read replica autoscaling
  Cache: Large tenant-in hot data-sı Redis-ə

Migration (small → large tier):
  Tenant grows → Directory service yenilənir
  Background migration: Old shard → Dedicated shard
  Double-write period → Consistency check → Switch
```

### Arxitektura Nümunəsi
```
                    ┌─────────────────┐
                    │  API Gateway    │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Tenant Router   │
                    │ (Directory Svc) │
                    └──┬──────────┬───┘
                       │          │
          ┌────────────▼─┐    ┌───▼──────────────┐
          │ Large Tenant │    │  Shared Shards   │
          │ Dedicated    │    │                  │
          │ Shards (100) │    │  Shard Pool (20) │
          │              │    │  Small+Medium    │
          │ EU: shard-eu │    │  Tenants (9,900) │
          │ US: shard-us │    │                  │
          └──────────────┘    └──────────────────┘
                  │                    │
          ┌───────▼────────────────────▼───────┐
          │         Read Replicas               │
          │   (per-shard, auto-scale)           │
          └─────────────────────────────────────┘
```

## Praktik Tapşırıqlar
- PostgreSQL table partitioning: Hash + Range partitioning qurun, EXPLAIN ilə partition pruning yoxlayın
- Consistent hashing simulator yazın: Node əlavə/silinməsindən neçə key hərəkət etdiyini ölçün
- Multi-tenant schema dizayn edin: Row-level security + tenant_id composite index
- Cross-shard aggregate benchmark: 4 shard, COUNT(*) scatter-gather latency ölçün
- Hotspot simulation: Auto-increment ID vs UUID v7 insert distribution müqayisə edin

## Əlaqəli Mövzular
- [07-database-sharding.md](07-database-sharding.md) — Sharding deep dive, replication
- [11-consistent-hashing.md](11-consistent-hashing.md) — Shard routing, rebalancing
- [12-cap-theorem-practice.md](12-cap-theorem-practice.md) — Consistency model partition ilə
- [06-database-selection.md](06-database-selection.md) — DB seçimi partition-a görə
- [19-cqrs-practice.md](19-cqrs-practice.md) — Read model cross-partition query üçün
