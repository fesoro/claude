# Scalability Fundamentals (Senior ⭐⭐⭐)

## İcmal
Scalability, sistemin artan yükü idarə edə bilmə qabiliyyətidir. Bu yalnız daha çox server əlavə etmək deyil — arxitektura qərarları, bottleneck analizi, data partitioning, caching, asynchronous processing, connection pooling-i əhatə edir. Interview-larda scalability fundamental bilgisi olmadan heç bir sistem dizaynı tam sayılmır. "10x traffic gəlsə nə dəyişərdi?" — bu sual hər interview-da gəlir.

## Niyə Vacibdir
Hər böyük şirkət mühəndisdən sistemin limitlərini başa düşməsini gözləyir. Scalability bilgisi Senior mühəndisin production-da real problemlər həll edəcəyinə dair ən güclü siqnaldır. Stack Overflow 2013-cü ildə 560M monthly page view-u tək server ilə idarə edirdi — düzgün caching ilə. Profiling etmədən "microservices lazımdır" demək — premature optimization.

## Əsas Anlayışlar

- **Vertical Scaling (Scale Up)**: Eyni serverin RAM, CPU, disk-ini artır. Sadədir — application dəyişmir. Limiti var: ən böyük maşın 192 CPU, 24TB RAM (AWS u-12tb1 metal). Single point of failure qalır. Use case: DB primary node, legacy applications.

- **Horizontal Scaling (Scale Out)**: Eyni serverdən daha çox əlavə et. Stateless application tələb edir. Teorik olaraq limitsiz. Load balancer lazımdır. Use case: web tier, API tier, cache tier.

- **Scale qərar matrisi**: Web/API tier → horizontal (stateless), ilk. DB primary → vertical (əvvəl), sonra read replica, sonra sharding. Cache → horizontal cluster. Queue → partition-based scale.

- **Stateless Architecture**: Horizontal scaling üçün application stateless olmalıdır. Session server memory-də saxlanmırsa — hər server eyni sorğunu idarə edə bilir. Session → Redis. State → DB. Local file → Object storage.

- **Bottleneck analizi**: CPU-intensive (video encoding, ML inference), Memory (large dataset), Disk I/O (DB heavy reads/writes), Network bandwidth, Database connections, Single thread bottleneck. **Profiling etmədən scale etmə — anti-pattern.**

- **Caching layers (aşağıdan yuxarı)**: Browser cache → CDN → Load Balancer cache → App cache (in-memory) → Distributed cache (Redis) → DB query cache. Hit ratio hər layer-da artır, latency azalır.

- **Cache Hit Ratio**: 90% hit ratio → 10x DB yükü azalır. 99% hit rate → 100x. 80/20 rule: 20% content = 80% traffic. Hot data-nı cache-ə al, cold data-nı DB-də saxla.

- **Database Read Scaling**: Read replicas (streaming replication) — read traffic'ı distribute et. Primary → write. Replicas (2-3) → read. CQRS — read model ayrı database.

- **Database Write Scaling**: Connection pooling (PgBouncer — PHP-FPM üçün critical). Write batching. Async writes via queue (eventual consistency qəbul edilərsə). Sharding (son vasitə — complexity artır).

- **Async Processing pattern**: Uzun əməliyyatlar sync deyil, async olmalıdır. POST /send-email → queue-a at → 200 OK (instant). Worker email göndərir (background). Use cases: email/SMS, image processing, PDF generation, 3rd party API calls, report generation.

- **Database Connection Pool problemi**: PHP-FPM: 100 worker × 100 server = 10,000 connection. PostgreSQL max_connections = 200 → CRISIS. Həll: PgBouncer — 10,000 virtual connection-u 100-ə map edir. Transaction mode (connection-ı transaction müddətinə alır) vs Session mode.

- **CAP Theorem praktiki tətbiqi**: Distributed sistemlər C, A, P-dən ikisini seçə bilər. CP sistemi (consistent, partition tolerant): HBase, ZooKeeper, etcd — availability fəda edilir. AP sistemi (available, partition tolerant): Cassandra, DynamoDB — consistency fəda edilir (eventual). CA: Traditional RDBMS (distributed deyil).

- **Fan-out pattern**: 1 yazı → N istifadəçiyə yayımla (Twitter timeline). Fan-in: N source → 1 aggregator (metrics collection). Work queue: task-ları parallel worker-lara paylama.

- **Data Denormalization**: Read performance üçün normalization qurbanı veririk. 3 JOIN əvəzinə denormalized table. Storage artır, write complexity artır, read sürətlənir. Trade-off: write şiddəti az, read şiddəti çox olan sistemlər üçün.

- **Index Strategy**: Compound index — equality columns first, then range. `CREATE INDEX idx ON orders(user_id, status, created_at)`. Covering index — query yalnız index-dən cavablandırılır. 100M row table, index olmadan: 10s+ latency. Index ilə: 1ms.

- **Microservices Scale Pattern**: Monolith scale yolu: vertical → horizontal → read replicas → caching → async → modular monolith → sonra microservices. 3 developer, 20 microservice → operational overhead > benefit. Anti-pattern.

- **Data Partitioning (Sharding)**: Horizontal sharding — data-nı ayrı DB node-larına böl. Shard key seçimi kritik: user_id (skew risk), hash (uniform distribution), range (range query asan). Cross-shard query — çətin, mümkün qədər qaç.

- **Rate Limiting**: Token Bucket, Leaky Bucket, Sliding Window algoritmları. Per user, per IP, per API key. Redis-də implement edilir. Load-ı idarə etmək, abuse qarşısı.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
1. Sadə dizayn et — single server, single DB
2. "Nə zaman fail olar?" soruşun
3. Bottleneck-i müəyyən edin (DB? CPU? Network?)
4. Addım-addım scale edin, hər addım əsaslandırılır
5. Hər həllin trade-off-unu qeyd edin

**Junior-dan fərqlənən senior cavabı:**
Junior: "Redis əlavə edərəm, Kubernetes istifadə edərik."
Senior: "Əvvəlcə bottleneck-i profiling ilə tapardım. DB read heavy isə — read replica. Connection pool dolursa — PgBouncer. Sonra Redis cache."
Lead: "Scale roadmap hazırlardım: 0-100K user: monolith + cache. 100K-1M: read replicas + async queues. 1M-10M: sharding + CDN. Hər mərhələdə team capacity və cost nəzərə alınır."

**Follow-up suallar:**
- "Stateful application-ı necə stateless edərdiniz?"
- "Database connection pool problemi yarandığında nə edirsiniz?"
- "Read replica lag-ı probleminə necə yanaşırsınız?"
- "Cache invalidation niyə çətin bir problemdir?"
- "Sharding key seçimini necə edirsiniz?"

**Ümumi səhvlər:**
- İlk dəfə over-engineered sistem — "ilk gündən sharding"
- Scalability problemlərini şişirtmək — 1K RPS üçün 10 server gerekmez
- Caching-i unudub yalnız DB scale etmək
- Stateful design — horizontal scale mümkün olmur
- PgBouncer-i əlavə etmədən PHP-FPM + PostgreSQL düşünmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Hər addımda konkret metrikalar. "Read replica əlavə etdikdə ortalama query latency 200ms-dən 50ms-ə endi." Scale roadmap + cost estimation + team capacity — bu Lead/Architect cavabıdır.

## Nümunələr

### Tipik Interview Sualı
"You have a web app with 10K users. How would you scale it to 10M users?"

### Güclü Cavab

```
Mərhələli scale strategiyası:

Phase 1 (10K → 100K users):
─────────────────────────────────────────────────
Hazırkı: Single server, single DB

Dəyişikliklər:
  ✓ Stateless app servers (session → Redis)
  ✓ Read replica (1 primary, 2 replicas)
    - Read traffic-i distribute et
    - Primary yalnız write
  ✓ Redis cache (sessions + hot data)
    - DB-yə yük 10x azalır
  ✓ CDN (static assets — JS, CSS, images)
  ✓ App server: 2-3 instances

Metrics target:
  Response time: p99 < 200ms
  Availability: 99.9%

Phase 2 (100K → 1M users):
─────────────────────────────────────────────────
Bottleneck: DB write QPS, connection pool

Dəyişikliklər:
  ✓ DB vertical scaling (r6g.4xlarge: 128GB RAM)
  ✓ PgBouncer (connection pooling)
    - 1000 PHP workers → 200 DB connections
  ✓ Async processing (Redis Queue / SQS)
    - Email, notifications, reports → background
  ✓ Horizontal app scaling: 5-10 instances auto-scaling
  ✓ Database query optimization + compound indexes
  ✓ Slow query logging → identify N+1

Metrics target:
  DB write: < 5K QPS (within single node capacity)
  Connection pool: < 80% utilized

Phase 3 (1M → 10M users):
─────────────────────────────────────────────────
Bottleneck: DB read QPS exceeds single node, storage

Dəyişikliklər:
  ✓ DB horizontal sharding (user_id hash-based)
    - 4 shards, ~250K users/shard
  ✓ Multiple cache tiers + Redis Cluster
  ✓ CDN for API responses (cache-control headers)
  ✓ App tier: auto-scaling group (20-50 instances)
  ✓ Separate heavy workloads (media, notifications → dedicated services)
  ✓ Search: Elasticsearch (full-text search DB-dən çıxarılır)

Phase 4 (10M+ users):
─────────────────────────────────────────────────
  ✓ Multi-region deployment (US, EU, APAC)
  ✓ Active-passive failover → Active-active
  ✓ Global load balancing (Anycast DNS, GeoDNS)
  ✓ Data replication across regions
  ✓ Service mesh (Istio) — service-to-service
```

### Arxitektura Diaqramları

```
Phase 1 (100K users):
─────────────────────────────────────────────────
Client
  │
CDN (static)
  │
Load Balancer
  │
App Server × 3 ──── Redis (cache, session)
  │
PostgreSQL Primary ──── Read Replica × 2

Phase 2 (1M users):
─────────────────────────────────────────────────
Client
  │
CDN
  │
Load Balancer (L7)
  │
App Server × 10 (Auto-scaling group)
  ├── Redis Cluster (cache, session, queue)
  │
PgBouncer (connection pooler)
  │
PostgreSQL Primary ──── Read Replica × 3
                │
               SQS/Redis Queue ──── Worker × 5

Phase 3 (10M users):
─────────────────────────────────────────────────
Client
  │
CDN (API responses cached too)
  │
Global Load Balancer
  │
API Gateway (auth, rate limit)
  │
App Server × 50 (ASG, multiple AZs)
  ├── Redis Cluster (32 nodes)
  │
Shard Router
  ├── PostgreSQL Shard 1 (users 0-2.5M)
  ├── PostgreSQL Shard 2 (users 2.5-5M)
  ├── PostgreSQL Shard 3 (users 5-7.5M)
  └── PostgreSQL Shard 4 (users 7.5-10M)
  │
Elasticsearch (search)
```

### Kod / Konfiqurasiya Nümunəsi

```php
// Connection Pooling — PHP-FPM + PgBouncer
// Problem olmadan əvvəl:
// 100 PHP workers × 100 servers = 10,000 PostgreSQL connections
// PostgreSQL max_connections = 200 → İllegal!

// PgBouncer konfiqurasiya:
// /etc/pgbouncer/pgbouncer.ini
/*
[databases]
app_db = host=postgresql port=5432 dbname=app_production

[pgbouncer]
listen_port = 5432
listen_addr = 0.0.0.0
auth_type = md5
pool_mode = transaction     # transaction bitdikdə connection pool-a qayıdır
max_client_conn = 10000     # PHP worker-ların bağlana biləcəyi max
default_pool_size = 100     # PostgreSQL-ə real connection sayı
min_pool_size = 10
reserve_pool_size = 5
server_lifetime = 3600
*/

// Stateless Session — Redis-də
// Config:
// SESSION_DRIVER=redis
// REDIS_HOST=redis-cluster

// Caching Strategy — Cache-Aside pattern
class ProductRepository
{
    public function __construct(
        private Redis $cache,
        private DB $db
    ) {}

    public function findById(int $id): ?Product
    {
        $cacheKey = "product:{$id}";

        // Cache-dən yoxla
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return Product::fromArray(json_decode($cached, true));
        }

        // DB-dən al
        $product = $this->db->selectOne(
            'SELECT * FROM products WHERE id = ?', [$id]
        );

        if ($product) {
            // Cache-ə yaz (1 saat TTL)
            $this->cache->setex($cacheKey, 3600, json_encode($product));
        }

        return $product ? Product::fromObject($product) : null;
    }

    public function save(Product $product): void
    {
        $this->db->statement(
            'UPDATE products SET name=?, price=? WHERE id=?',
            [$product->name, $product->price, $product->id]
        );

        // Cache invalidation — write-through
        $this->cache->del("product:{$product->id}");
        // Əvvəlki cache silindi, növbəti read DB-dən alıb cache-ə yazacaq
    }
}

// Async Processing — Queue pattern
class OrderController
{
    public function store(OrderRequest $request): JsonResponse
    {
        // Sync: order DB-yə yazılır (critical)
        $order = Order::create([
            'user_id' => $request->user()->id,
            'total'   => $request->total(),
            'status'  => 'pending',
        ]);

        // Async: notification, analytics, email — queue-a at (non-critical)
        dispatch(new SendOrderConfirmationEmail($order));
        dispatch(new UpdateInventory($order));
        dispatch(new TrackOrderAnalytics($order));

        // İnstant response — queue-dakı işlər background-da davam edir
        return response()->json([
            'order_id' => $order->id,
            'status'   => 'accepted',
        ], 202);
    }
}

// Read Replica — Laravel automatic routing
// config/database.php
/*
'mysql' => [
    'driver' => 'mysql',
    'read' => [
        'host' => ['db-replica-1', 'db-replica-2'],  // read queries buraya
    ],
    'write' => [
        'host' => 'db-primary',   // write queries primary-ə
    ],
    'sticky' => true,  // Write-dən sonra read primary-dən oxur (replication lag)
],
*/
```

### Müqayisə Cədvəli — Scale Texnikaları

| Texnika | Problem | Trade-off | Nə vaxt |
|---------|---------|-----------|---------|
| Read Replicas | Read QPS yüksək | Replication lag, eventual consistency | DB read bottleneck |
| Redis Cache | DB load yüksək | Cache invalidation, memory cost | Hot data access patterns |
| PgBouncer | Connection pool full | Slight overhead | PHP + PostgreSQL |
| Async Queue | Long sync operations | Eventual consistency | Email, media, reports |
| Horizontal Scaling | CPU/memory limit | Load balancer, stateless app | All tiers |
| DB Sharding | Write QPS + storage | Cross-shard query çətin | DB write bottleneck |
| CDN | Static content bandwidth | TTL invalidation | Media, static assets |
| Vertical Scaling | Simple bottleneck | Physical limit, SPOF | DB primary (initially) |

## Praktik Tapşırıqlar

1. E-commerce site: mövcud 1K RPS-dən 100K RPS-ə necə scale edərdiniz? Hər addım üçün bottleneck göstərin.
2. News feed: 10M user, hər postun 100K follower-ı var. Fan-out on write vs Fan-out on read trade-off analizi.
3. PgBouncer install edib PHP-FPM ilə connection pooling test edin. DB connection sayını ölçün.
4. Redis cache-aside pattern implement edin — cache hit rate metric-ini ölçün.
5. Async queue əlavə edin: sinxron bir API-ni queue-a at, background worker işlətsin. Latency fərqini ölçün.
6. Şərti ssenario: "DB replica lag 10 saniyədir. Istifadəçi profil yeniləyir, amma köhnəni görür." Həlli izah edin.
7. Sharding key seçimi: user_id vs timestamp vs hash — hər birinin pro/con-larını sıralayın.
8. Capacity planning: 3M DAU, 15 sorğu/gün. Neçə app server, hansı DB konfigurasyonu?

## Əlaqəli Mövzular

- [04-load-balancing.md](04-load-balancing.md) — Load distribution
- [05-caching-strategies.md](05-caching-strategies.md) — Caching patterns in detail
- [07-database-sharding.md](07-database-sharding.md) — Horizontal DB scaling
- [08-message-queues.md](08-message-queues.md) — Async processing
- [12-cap-theorem-practice.md](12-cap-theorem-practice.md) — Consistency/Availability tradeoffs
