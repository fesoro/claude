# Database Sharding (Lead ⭐⭐⭐⭐)

## İcmal
Database sharding, böyük dataset-ləri bir neçə database instance arasında horizontal olaraq bölmə texnikasıdır. Hər shard məlumatın bir hissəsini saxlayır. Sharding yalnız digər bütün scale strategiyaları (caching, read replicas, indexing, vertical scaling) tükənəndən sonra nəzərə alınmalıdır — çünki əlavə olunan mürəkkəblik əhəmiyyətli dərəcədə böyükdür.

## Niyə Vacibdir
Facebook-un MySQL sharding-i, Pinterest-in Cassandra partition-ları, Stripe-ın PostgreSQL sharding-i — scale etmiş hər şirkət bu problemlə üzləşir. Lead mühəndis sharding qərarını zamanında vermək, doğru shard key seçmək, hotspot-lardan qaçınmaq bacarığını nümayiş etdirməlidir. Bu mövzu Senior+ interview-larında həmişə gəlir.

## Əsas Anlayışlar

### 1. Sharding nədir və nə zaman lazımdır
**Sharding Olmadan Scale Səviyyələri:**
1. Indexing + Query optimization
2. Vertical scaling (daha güclü server)
3. Read replicas (read yükünü paylaşdır)
4. Caching (Redis, Memcached)
5. ← Bunlar bitmişsə sharding nəzərə al

**Sharding tələb edən işarələr:**
- Single DB instance write throughput limitinə çatır
- Dataset RAM-dan böyük, query performance düşür
- Backup/restore çox uzun çəkir
- Ölkə/region data sovereignty tələbləri

### 2. Sharding Strategiyaları

**Range-based Sharding**
```
User ID 1-1M     → Shard 1
User ID 1M-2M   → Shard 2
User ID 2M-3M   → Shard 3
```
- Pros: Range queries effektiv (1-100K user seç)
- Cons: Hotspot riski (yeni users həmişə son shard-da)

**Hash-based Sharding**
```
Shard = hash(user_id) % num_shards
user_id=1234 → hash → Shard 2
user_id=5678 → hash → Shard 0
```
- Pros: Bərabər paylanma
- Cons: Range query inefficient; shard sayı dəyişdikdə bütün data köç edir

**Consistent Hashing**
```
Hash ring (0 - 2^32)
Shard A: 0     - 85B
Shard B: 85B  - 170B
Shard C: 170B - 255B

Key → hash → ring-də növbəti shard
```
- Pros: Shard əlavə/silindikdə minimal data köçü
- Use case: Distributed cache, Cassandra, DynamoDB

**Directory-based Sharding**
```
Lookup service:
user_id → shard_id (mapping table)
```
- Pros: Flexible, custom mapping
- Cons: Lookup service bottleneck; single point of failure

**Geographic Sharding**
```
Shard EU: EU users
Shard US: US users
Shard APAC: Asia-Pacific users
```
- Pros: Data locality, compliance (GDPR)
- Cons: Uneven distribution

### 3. Shard Key Seçimi — Ən Kritik Qərar
Yanlış shard key = hotspot = bütün traffic tək shard-a gedir.

**Yaxşı shard key:**
- High cardinality (çox unikal dəyər)
- Bərabər paylanma
- Access pattern ilə uyğun
- Çox nadir dəyişir

**Nümunə: Twitter**
```
Yanlış: tweet_date (temporal hotspot, yeni tweet-lər tək shard)
Yanlış: user_location (coğrafi hotspot)
Düzgün: user_id hash (bərabər paylanma, user tweets together)
```

**Nümunə: E-commerce orders**
```
Yanlış: order_date (yeni sifarişlər bir shard)
Yanlış: product_id (populyar product hotspot)
Düzgün: customer_id (customer-centric queries birlikdə)
```

### 4. Cross-Shard Queries — Ən Böyük Çətinlik
```sql
-- Single shard (asan):
SELECT * FROM orders WHERE customer_id = 1234
-- → Shard 2 (hash(1234)=2)

-- Cross-shard (çətin):
SELECT COUNT(*) FROM orders WHERE status = 'pending'
-- → Bütün shard-lara göndər, nəticələri topla (scatter-gather)

-- Cross-shard JOIN (çox çətin):
SELECT o.*, u.name FROM orders o 
JOIN users u ON o.user_id = u.id
WHERE o.amount > 1000
-- → orders başqa shard, users başqa shard ola bilər
```

**Həll:**
- Cross-shard queries-i minimize et (data model yenidən düşün)
- Denormalize: user_name-i orders cədvəlində saxla
- Application-level JOIN (2 shard-dan data çək, app-da birləşdir)
- Separate analytics DB (all shards → ETL → data warehouse)

### 5. Hotspot Problemləri və Həllər
**Celebrity Problem (Sosial Media):**
```
Elon Musk-un tweeti → 1M user baxır
Onun user_id hash → Shard 3 → OVERLOADED
```

**Həll 1: Sub-partitioning**
```
Elon Musk user → Shard 3_A, 3_B, 3_C
Load balancer sub-shards arasında paylayır
```

**Həll 2: Fan-out on Write**
```
Tweet yazılır → 1M follower-in cache-inə yazar
Hər user öz cache-ini oxuyur (shard-a getmir)
```

**Həll 3: Separate Tier for Hot Keys**
```
Hot users (>10K followers) → ayrı cluster
Normal users → regular shards
```

### 6. Resharding (Rebalancing)
Shard sayı dəyişdikdə:

**Consistent hashing ilə (minimal disruption):**
- Yeni shard əlavə olunur
- Yalnız adjacent shard-dan data köçür
- ~1/N data hərəkət edir (N = shard sayı)

**Hash-based (disruptive):**
- hash(key) % 4 → hash(key) % 5
- Demək olar ki, bütün data dəyişir
- Blue/green migration tələb edir

**Online resharding prosesi:**
1. Yeni shard-ları əlavə et (boş)
2. Background migration: köhnə shard-dan yeniyə köç
3. Dual-write: Hər yeni yazma hər iki shard-a
4. Migration tamamlandı → trafik yeni shard-a
5. Köhnə shard silinir

### 7. Shard-level Replication
Hər shard-da ayrıca primary-replica var:
```
Shard 1: Primary_1 + Replica_1a + Replica_1b
Shard 2: Primary_2 + Replica_2a + Replica_2b
Shard 3: Primary_3 + Replica_3a + Replica_3b
```

- Yazma → Primary
- Oxuma → Primary или Replica
- Primary fail → Replica promoted

### 8. Sharding Middleware / Proxy
Application-in birbaşa shard bilməsi yerinə:
- **ProxySQL**: MySQL sharding proxy
- **Vitess** (YouTube): MySQL sharding, Kubernetes-native
- **Citus** (PostgreSQL): Sharding extension
- **PgBouncer + custom router**: Application-level

```
App → Vitess Router → Shard 1, 2, 3
```

### 9. Two-Phase Commit (2PC) Problemi
Cross-shard transaction:
```
Transfer $100 from user A (Shard 1) to user B (Shard 2)
1. BEGIN on Shard 1 + Shard 2
2. Deduct from A (Shard 1)
3. Add to B (Shard 2)
4. COMMIT both
```
- Coordinator fail olsa? → Deadlock
- 2PC latency yüksəkdir
- **Tövsiyə:** Saga pattern istifadə et (eventual consistency)

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Əvvəlcə digər scale seçimlərini tükəndirdim" söylə
2. Shard key seçimini əsaslandır (niyə user_id, niyə not date)
3. Hotspot problemini gündəmə gətir
4. Cross-shard query problemini qeyd et
5. Resharding prosesini izah et

### Ümumi Namizəd Səhvləri
- Hər şirket üçün sharding-i ilk həll kimi təklif etmək
- Shard key seçimini əsaslandırmamaq
- Cross-shard query-nin çətinliyini unutmaq
- Resharding prosesini düşünməmək
- Transaction semantics-i sharding ilə necə dəyişdiyini bilməmək

### Senior vs Architect Fərqi
**Senior**: Sharding strategiyasını seçir, shard key müəyyən edir, hotspot həllini bilir.

**Architect**: Sharding ilə operational complexity-nin total cost-unu hesablayır. Vitess/Citus kimi managed sharding tools-a qarşı custom sharding qərarını verir. Resharding operasiyasını zero-downtime planlaşdırır. Team-in sharding ilə işləmə qabiliyyətini qiymətləndirir. "Sharding lazım deyil, vertical scaling + Citus daha ucuz" qərarını verə bilir.

## Nümunələr

### Tipik Interview Sualı
"Design the database layer for a messaging app with 1B users, 100B messages."

### Güclü Cavab
```
Messaging app DB tələbləri:
- 1B users, 100B messages
- Write-heavy: gündə 50B yeni mesaj
- Read: son 100 mesaj sürətlə
- Data locality: user-in bütün mesajları birlikdə

Hesablama:
50B messages/day = 580K writes/sec
Single PostgreSQL max write: ~10K/sec
Lazımdır: 580K/10K = 58 shards (minimum)
Round up: 64 shard (2^6, gəlişmə üçün)

Shard key: user_id (conversation owner)
- Fayda: user-in bütün mesajları 1 shard-da
- Cross-shard: Yalnız sender → receiver (A shard→B shard yaza bilir)

Shard əlavə açıqlama:
- Mesaj M göndərir A-dan B-yə
- A-nın shard-ında yazılır (A-nın sent folder)
- B-nin shard-ında yazılır (B-nin inbox)
- Dual-write by message service

Cross-shard conversation:
- A (Shard 3) → B (Shard 7) söhbəti
- Hər iki shard-da ayrı kopyası var
- Konsistentlik: eventual (mesaj az gecikmə ilə digər shard-da)

Hot user handling:
- Elon Musk: 1M message/day → Shard 12 overloaded
- Həll: dedicated shard for high-volume users
- Threshold: > 100K messages/day → isolated shard

Storage:
- 100B mesaj × 1KB ortalama = 100TB
- 64 shard × ~1.5TB = balanced
- Hər shardda Cassandra (write-optimized, no joins needed)
```

### Sharding Arxitekturası
```
Client
  │
Message Service
  │
Shard Router (consistent hashing)
  │           │           │
Shard-1    Shard-2    Shard-3
(Cassandra) (Cassandra) (Cassandra)
Primary+    Primary+    Primary+
Replica     Replica     Replica
```

## Praktik Tapşırıqlar
- Vitess ilə MySQL sharding qurun
- PostgreSQL Citus extension-ı test edin
- Consistent hashing alqoritmini implement edin
- Cross-shard query-nin performance-ını ölçün
- Hotspot simulation: 1 user-ə 100x daha çox write

## Əlaqəli Mövzular
- [11-consistent-hashing.md] — Consistent hashing algoritmi
- [22-data-partitioning.md] — Partitioning vs sharding
- [17-distributed-transactions.md] — Cross-shard transactions
- [12-cap-theorem-practice.md] — Consistency in sharded systems
- [06-database-selection.md] — Sharding-friendly databases
