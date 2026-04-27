# Back-of-Envelope Estimation (Senior ⭐⭐⭐)

## İcmal
Back-of-envelope estimation — sistem dizaynı interview-larında çox vacib bacarıqdır. Bu texnika, dəqiq rəqəmlər olmadan sürətlə təxmini hesablamalar aparmağı əhatə edir. Interviewer-lər bu bacarıqla namizədin ölçülərə (scale) olan intuisiyasını, rəqəmlərlə işləmə qabiliyyətini və sistemin tələb olunan kapasitesini müəyyən edə bilmə bacarığını yoxlayır. Yuvarlaq rəqəmlərlə sürətli, doğru istiqamətli cavab vermək məqsəddir.

## Niyə Vacibdir
Google, Amazon, Meta kimi şirkətlər estimation qabiliyyətini sistem dizaynının ayrılmaz hissəsi kimi qiymətləndirir. Estimation olmadan hansı komponent-lərin bottleneck olacağını, caching-in nə qədər effektiv olacağını ya da database-in horizontal scale tələb edib-etməyəcəyini bilə bilməzsiniz. "Çox traffic olacaq" demək deyil, "12K RPS peak-də PostgreSQL single node yetmir, read replica lazımdır" demək. Rəqəmli düşüncə Senior mühəndisin vacib keyfiyyətlərindəndir.

## Əsas Anlayışlar

### Powers of 2 — Əzbər Rəqəmlər:
```
2^10 =      1,024 ≈ 10^3  (1 KB)
2^20 =  1,048,576 ≈ 10^6  (1 MB)
2^30 =          - ≈ 10^9  (1 GB)
2^40 =          - ≈ 10^12 (1 TB)
2^50 =          - ≈ 10^15 (1 PB)
```
Bu rəqəmlər əzbər olmalıdır — storage hesablamalarının təməlidir.

### Vaxt Çevirmə — Fundamental Trick:
```
1 gün  = 86,400 saniyə ≈ 100,000 (rounding trick: ~1.16 dəfə böyüdülür)
1 ay   = 2,592,000     ≈ 2.5M saniyə
1 il   = 31,536,000    ≈ 30M saniyə
1 həftə= 604,800       ≈ 600K saniyə

QEYDİ: req/day → req/sec üçün 100,000-ə böl.
1M req/day / 100K = 10 req/sec
100M req/day / 100K = 1,000 req/sec = 1K RPS
1B req/day / 100K = 10,000 req/sec = 10K RPS
```

### Latency Cədvəli — Hər Mühəndisin Əzbərləməli Olduğu Rəqəmlər:
```
Operation                           Latency        Notes
─────────────────────────────────────────────────────────────
L1 cache reference                  0.5   ns
Branch mispredict                   5     ns
L2 cache reference                  7     ns
Mutex lock/unlock                   25    ns
Main memory reference               100   ns       ← RAM
Compress 1K bytes (Snappy)          3     μs
Send 1K bytes over 1 Gbps network   10    μs
SSD random read                     100   μs       ← SSD
Read 1 MB sequentially from RAM     250   μs
Round-trip within same datacenter   500   μs       ← Local network
Read 1 MB sequentially from SSD     1     ms
HDD random read (seek time)         10    ms       ← HDD — çox yavaş
Send packet CA → Netherlands → CA   150   ms       ← Cross-continent
```

**Key Ratios (bunlar interview-da çox soruşulur)**:
- RAM, SSD-dən **1000x sürətlidir** — caching-in niyə kritik olduğunun riyazi izahı.
- RAM, HDD-dən **100,000x sürətlidir**.
- Same DC network, cross-continent-dən **300x sürətlidir**.
- Cache hit vs DB query: **10-1000x** fərq (Redis 0.5ms vs PostgreSQL 100ms-500ms).

### DAU → RPS Hesabı (Ən Vacib Formula):
```
DAU: 100M users
Hər user gündə 10 sorğu
─────────────────────────────────────
Total: 100M × 10 = 1B req/day
1B / 100K = 10,000 req/sec = 10K RPS (average)
Peak: 10K × 2-3x = 20K-30K RPS

Active hours = 16 saat (user-lar yatır)
→ Peak RPS = 10K × (24/16) × 2 = 30K RPS
```

**Tez hesab üsulu**: DAU (million) × daily_requests × 10 ≈ RPS (rough).
- 100M DAU × 10 req × 10 = 10,000 RPS (10K RPS). Çox yaxın!

### Storage Estimation Metodologiyası:
```
Formula: bytes_per_record × writes_per_day × retention_days × replication_factor

Twitter tweet:
  300 bytes/tweet
  × 500M tweets/day
  × 365 days/year
  × 3 replicas
  = 165 TB/year

Instagram photo:
  300 KB/photo (avg, 3 versions: thumb+med+large)
  × 100M photos/day
  × 365 days/year
  = 10.95 PB/year → ~11 PB/year → S3 + CDN lazımdır

WhatsApp message:
  100 bytes/message
  × 100B messages/day
  × 365 days
  = 3.65 PB/year text storage
```

### Database QPS Limitləri (Əzbər!):
```
Database          Read QPS (indexed)    Write QPS     Notes
────────────────────────────────────────────────────────────
PostgreSQL        10K - 50K             5K - 10K      Depends on query
MySQL             20K - 100K            3K - 10K      Simpler queries faster
Redis             100K - 1M             100K - 1M     In-memory, ops/sec
Cassandra         50K/node              100K/node     Write-optimized
MongoDB           10K - 50K             5K - 20K      Varies by ops
ClickHouse        -                     500K           Batch insert
Elasticsearch     5K - 20K             1K - 5K        Search-optimized
```

**Sharding qərarı**: "DB read yükü > 50K RPS → read replicas yetmir, sharding lazımdır."

### Server Capacity:
```
Server type              Concurrent connections   RPS         Notes
─────────────────────────────────────────────────────────────────────
PHP-FPM (8 core)         500-1000 workers        500-1000    CPU-bound
Node.js (async)          10,000+ concurrent      10K-50K     I/O-bound
Go HTTP server           100K+ concurrent        50K-200K    Efficient
Nginx (static files)     50K concurrent          100K+       File serving
Load Balancer (HAProxy)  100K concurrent         100K-500K   Pass-through
WebSocket server         100K connections        -           Connection-based
```

### Network Bandwidth Estimation:
```
1M concurrent video streams × 5 Mbps = 5 Tbps aggregate bandwidth
1 server × 10 Gbps NIC → 10Gbps / 5Mbps = 2,000 streams/server
→ 1M / 2000 = 500 streaming servers minimum

API response size:
  REST JSON (list):       5 KB average
  REST JSON (detail):     2 KB average
  Image (thumbnail):      20 KB
  Video chunk (1 sec):    500 KB

10K RPS × 5 KB = 50 MB/s = 400 Mbps — single server NIC yetər (10 Gbps)
10K RPS × 500 KB = 5 GB/s = 40 Gbps — multiple servers + CDN lazımdır
```

### Cache Memory Estimation (80/20 Rule):
```
Total users:         100M
Active users (daily): 10M (10% aktiv)
Cache hot data: top 20% users = 80% traffic
  → Cache 20% × 10M = 2M user-in data-sını

Per user cache: 100 items × 300 bytes = 30 KB
2M × 30 KB = 60 GB RAM

Redis cluster: 3 nodes × 32 GB = 96 GB → yetər
Single Redis (128 GB): daha sadə, amma SPOF
```

### Sanity Check — Nəticə Məntiqlidirmi?
```
"10 PB/day storage?" → YouTube-dan çoxdur → assumption-ı yoxla
"3 TB storage / 5 year?" → single server yetər → ağlabatandır
"12K RPS?" → single server yetmir → read replica lazımdır → məntiqlidir
"0.5 MB/s bandwidth?" → 4 Mbps → trivial → CDN lazım deyil
"5 Tbps bandwidth?" → impossible for single datacenter → multi-CDN
```

### Bottleneck Identification — Estimation-dan Design-a:
```
RPS hesablandı → Hansı komponent limit olacaq?

RPS < 1K:
  → Single server yetər
  → Single DB (primary only) yetər
  → Cache optional

1K < RPS < 10K:
  → Multiple app servers (load balancer)
  → DB: primary + 1-2 read replicas
  → Redis cache critical

10K < RPS < 100K:
  → Horizontal scale: 10+ app servers
  → DB: sharding ya da NoSQL
  → Redis cluster
  → CDN for static content

RPS > 100K:
  → Distributed architecture
  → Multi-region
  → Message queue (Kafka)
  → Eventual consistency model
```

### Read:Write Ratio — System Design-a Təsiri:
```
Platform            Read:Write ratio    Implication
──────────────────────────────────────────────────────────
Twitter             50:1                Read-heavy → cache critical
E-commerce          10:1                Cache product catalog
Wikipedia           1000:1              Aggressive CDN + cache
Banking             1:1                 Strong consistency, ACID
Sensor/IoT          1:50                Write-heavy → time-series DB
Chat/Messaging      1:1                 WebSocket, message queue
Search engine       100:1               Inverted index, read replicas
```

### Percentile Düşüncəsi:
```
Average latency: Yanıltıcıdır!
10,000 requests:
  p50 (median):  20ms  — 5,000 request 20ms altında
  p95:           100ms — 9,500 request 100ms altında
  p99:           500ms — 9,900 request 500ms altında
  p99.9:         2s    — 9,990 request 2s altında

SLO (Service Level Objective) p99 üzərindən müəyyən edilir.
"p99 latency < 100ms" → "Hər 100 requestdən 99-u 100ms altında."
Average 20ms ola bilər, amma p99 500ms — user 1% vax uzun gözləyir.
```

### Cost Estimation (Architect Səviyyəsi):
```
AWS EC2 pricing (2025 approximate):
  t3.medium (2 vCPU, 4 GB):    $30/ay
  m6g.xlarge (4 vCPU, 16 GB):  $120/ay
  r6g.2xlarge (8 vCPU, 64 GB): $380/ay
  r6g.4xlarge (16 vCPU, 128 GB): $760/ay

RDS PostgreSQL:
  db.t3.medium:    $55/ay
  db.m6g.xlarge:   $240/ay
  db.r6g.2xlarge:  $500/ay

S3: $23/TB/ay
CloudFront: $85/TB outbound
ElastiCache Redis: $120/ay (r6g.large, 13 GB)

URL Shortener (100M DAU) monthly cost:
  2× app servers (m6g.xlarge): $240
  1× RDS PostgreSQL (m6g.xlarge): $240
  1× ElastiCache Redis (r6g.large): $120
  S3 + CloudFront: negligible
  Total: ~$600-800/ay
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq:
Rəqəmləri söyləyən kimi whiteboard-a yaz. Hər addımı izah et:
- "100M DAU, gündə 10 sorğu → 1B req/day → 100K-ə bölürəm → 10K RPS."
- "Peak = average × 3 → 30K RPS."
- "Cache hit rate 80% → DB görür 30K × 20% = 6K RPS → single PostgreSQL + 1 read replica yetər."
Yuvarlaq rəqəmlər istifadə et. Assumption-ları açıq söylə. Nəticədən system implication çıxar.

### Junior-dan Fərqlənən Senior Cavabı:
- **Junior**: "Çox traffic olacaq, Kafka və Redis lazımdır." — Rəqəm yoxdur, əsas yoxdur.
- **Senior**: "10M DAU, hər user 5 sorğu = 50M/day = 500 RPS. Peak 1,500 RPS. PostgreSQL single node handle edir. Redis cache ilə yalnız DB hit-ləri azaldırıq. Kafka lazım deyil — 500 RPS async queue-dan faydalanmaz."
- **Lead**: "Bu scale üçün: 2 app server (failover), 1 primary + 2 read replica DB, Redis cluster. Monthly AWS cost ≈ $800. İlk 12 ay üçün yetər. 10M → 100M DAU keçişdə sharding planı tələb olunacaq."

### Follow-up Suallar:
- "10x traffic gəlsə hansı komponent ilk fail edər?" — Bottleneck identification.
- "Storage xərci necə azaldıla bilər?" — Tiered storage, compression, deduplication.
- "Peak traffic üçün auto-scaling nə vaxt aktiv olmalıdır?" — CPU 70% olduqda scale out.
- "Bandwidth çox bahalıdırsa nə edərdiniz?" — CDN, compression (gzip/brotli), image optimization.

### Ümumi Səhvlər:
- Rəqəmləri bilmədən estimation etməyə çalışmaq — latency/QPS cədvəlini öyrən.
- Çox dəqiq olmağa çalışmaq — "86,400 saniyə" deyil "100K saniyə" de. Dəqiqlik deyil, istiqamət vacibdir.
- Estimation-dan system implication çıxarmamaq — saylar var, amma "buna görə X lazımdır" yoxdur.
- Peak traffic-i unutmaq — average × 2-3 = peak.
- Storage vs bandwidth-i qarışdırmaq — hər ikisi ayrı hesablanır.
- Replication factor-u unutmaq — 3 replica = 3× storage.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən:
Estimation nəticəsindən konkret arxitektura qərarı çıxarılır: "6K DB read RPS → Redis cache ilə 80% hit rate → DB görür 1.2K RPS → single PostgreSQL yetər, read replica əlavə etmirəm hələ. Bu qənaəti $240/ay DB cost azaldır." Bu rəqəmli əsaslandırma + cost awareness-dır.

## Nümunələr

### Tipik Interview Sualı
"Design a URL shortener like bit.ly. First, estimate the scale. DAU: 100M."

### Güclü Cavab

```
Namizəd: Assumption-larımı qeyd edim:
  - DAU: 100M (bit.ly-nin real-dünya traffic-i)
  - Read:Write ratio: 10:1 (klik >> URL yaratma)
  - User hər gün ortalama: 10% URL yaradır, hamısı klik edir

═════════════════════════════════════════════════════
TRAFFIC ESTIMATION
═════════════════════════════════════════════════════
DAU: 100M

Write (URL creation):
  100M users × 10% create URL/day = 10M URL/day
  10M / 100,000 = 100 write/sec (average)
  Peak: 100 × 3 = 300 write/sec

Read (URL redirect):
  10M new URLs × 10 clicks each = 100M reads/day
  100M / 100,000 = 1,000 read/sec (average)
  Peak: 1,000 × 3 = 3,000 read/sec

Read:Write ratio = 30:1 (peak-də) → read-heavy → cache optimal

═════════════════════════════════════════════════════
STORAGE ESTIMATION
═════════════════════════════════════════════════════
Per URL record:
  short_code:   7 bytes   ("aB3kZ9x")
  long_url:     100 bytes (average, 50-200 range)
  user_id:      8 bytes
  created_at:   8 bytes
  click_count:  8 bytes
  Total:        ~131 bytes/record

5 years storage:
  10M URLs/day × 365 × 5 = 18.25B records
  18.25B × 131 bytes = ~2.4 TB

→ 2.4 TB: Single PostgreSQL node + 1 replica. Sharding lazım deyil.

═════════════════════════════════════════════════════
CACHE ESTIMATION
═════════════════════════════════════════════════════
80/20 rule: 20% URL = 80% traffic
Active daily URLs: 10M (yeni + popular köhnə)
  → Cache top 20%: 10M × 20% = 2M URLs
  → 2M × 131 bytes = 262 MB Redis

→ Very manageable! Single Redis node (8GB) yetər. 262 MB sadəcə 3%.
Cache hit rate: ~80% → DB görür 3K × 20% = 600 RPS ← PostgreSQL asanlıqla handle edir.

═════════════════════════════════════════════════════
BANDWIDTH ESTIMATION
═════════════════════════════════════════════════════
Read response (301 redirect, yalnız headers): ~500 bytes
Write response (JSON): ~200 bytes

Outbound: 3,000 reads/sec × 500B = 1.5 MB/s = 12 Mbps  (negligible!)
Inbound:    300 writes/sec × 200B = 60 KB/s              (negligible!)

→ Bandwidth problem yoxdur. CPU/DB compute bottleneck.

═════════════════════════════════════════════════════
SYSTEM IMPLICATIONS (bu hissə çox vacibdir!)
═════════════════════════════════════════════════════
1. Single PostgreSQL (primary) + 1 read replica:
   → 300 writes/sec ← primary handle edir ✓
   → 600 reads/sec (after cache) ← read replica handle edir ✓

2. Redis cache (262 MB in 8GB instance):
   → 80% read hit rate — DB yükünü 5x azaldır ✓

3. URL generation strategy:
   → Base62 counter (62^7 ≈ 3.5T unique codes) — 5 ildən artıq yetər ✓
   → MD5 collision risk var — counter better

4. CDN: Redirect response kiçikdir (500B) — CDN minimal dəyər verir.
         Amma "public, max-age=3600" Cache-Control → browser cache faydalı.

5. Monitoring: p99 redirect latency < 50ms hədəf.
   Redis hit: ~1ms. DB miss: ~10ms. Hər ikisi hədəf daxilindədir ✓
```

### Əsas Rəqəmlər Cədvəli — Interview Hazırlığı
```
Latency Numbers (əzbər):
────────────────────────────────────────────────
L1 cache reference         :      0.5 ns
L2 cache reference         :      7   ns
Main memory reference      :    100   ns
SSD random read            :    100   μs
Round-trip same datacenter :    500   μs
Round-trip NYC → London    :    150   ms

Key Ratios:
  Memory vs SSD:     1000x faster
  Memory vs HDD:   100,000x faster
  SSD vs HDD:          100x faster
  Same DC vs Cross:    300x faster

Database Capacities (single node):
────────────────────────────────────────────────
PostgreSQL    read:  10K-50K QPS (indexed)
              write: 5K-10K QPS
Redis         ops:   100K-1M ops/sec
Cassandra     write: 100K QPS/node

Storage Size Reference:
────────────────────────────────────────────────
Tweet text:         300 bytes
User record:        1 KB
Email:              20 KB
Photo (JPEG):       200 KB → 300 KB (3 sizes)
MP3 (1 minute):     1 MB
HD video (1 min):   50 MB
4K video (1 min):   400 MB
```

### Üç Böyük Sistem — Estimation Müqayisəsi

**Twitter (500M DAU, 10:1 read:write)**:
```
Writes: 500M × 5% tweet/day = 25M tweets/day → 280 TPS
Reads:  500M × 20 timeline = 10B reads/day → 115K RPS
Storage (text): 25M × 300B × 365 = 2.7 TB/year → manageable
Storage (media): 25M × 30% × 200KB = 1.5 TB/day → 550 TB/year → CDN + S3
Bottleneck: Read scalability → Redis cluster, fan-out service
```

**Instagram (2B MAU / 500M DAU)**:
```
Photos: 100M uploads/day → 1,157 uploads/sec
Photo size (3 versions): 300 KB → 30 TB/day → 10.9 PB/year
Feed reads: 500M × 10 = 5B reads/day → 58K RPS
Bottleneck: Storage (S3), Delivery (CDN), Feed computation (fan-out)
Cache: 20% popular posts = 80% traffic → Redis cluster 100GB+
```

**WhatsApp (2B users, 100B msgs/day)**:
```
Messages: 100B / 86,400 = 1.16M msgs/sec
Storage: 100B × 100B bytes = 10 TB/day text
Media: 50% has photo/video → complex
Active connections: 100M WebSocket connections → custom server (Erlang/Go)
Bottleneck: Connection management (WebSocket), storage tiering
```

## Praktik Tapşırıqlar

1. **Instagram estimate**: 1B MAU, gündə 100M foto. Storage 5 ildə nə qədər? Neçə server? Cache nə qədər?
2. **WhatsApp estimate**: 2B users, gündə 100B mesaj. RPS? WebSocket connection count? Storage?
3. **YouTube estimate**: gündə 500 saat video upload per minute. Encoding server sayı? Bandwidth? Storage?
4. **Ride-sharing (Uber)**: 10M rides/day, geolocation update hər 5 saniyə. Driver update stream RPS?
5. **Hər estimate üçün bottleneck tapın**: Hansı komponent ilk limitə çatır?
6. **Real şirkət blog-ları ilə müqayisə edin**: AWS architecture blog, Netflix tech blog, Uber engineering blog.
7. **Hər həftə bir estimation** — 10 dəqiqəyə bitirməyə çalışın. Sürəti artırın.
8. **Latency cədvəlini əzbərləyin**, sonra "cache niyə 1000x sürətlidir?" sualını riyazi izah edin.
9. **Cost awareness**: URL shortener-in 100M → 1B DAU keçişindəki AWS cost artımını hesablayın.
10. **Percentile drill**: "Average 50ms, p99 500ms" — bu fərq niyə vacibdir? SLO necə müəyyən edilir?

## Əlaqəli Mövzular

- [01-system-design-approach.md](01-system-design-approach.md) — Ümumi interview yanaşması, RESHADED framework
- [03-scalability-fundamentals.md](03-scalability-fundamentals.md) — Scale etmə prinsipləri, horizontal vs vertical
- [07-database-sharding.md](07-database-sharding.md) — Sharding nə vaxt lazımdır (QPS limitlər)
- [05-caching-strategies.md](05-caching-strategies.md) — Cache capacity planning, LRU vs LFU
- [04-load-balancing.md](04-load-balancing.md) — Server sayının hesablanması, concurrent connections
