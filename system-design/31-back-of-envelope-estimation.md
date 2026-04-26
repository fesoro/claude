# Back-of-the-Envelope Estimation (Junior)

## İcmal

**Back-of-the-envelope estimation** — sistem dizayn müsahibələrində və real dünya planlaşdırmasında cari/gələcək sistem tələblərini (QPS, storage, bandwidth, memory) tez hesablamaqdır. Dəqiq rəqəmlər yox, sıra-böyüklüyü (order of magnitude) əsasdır.

Bu bacarıq interview-larda çox yüksək qiymətləndirilir — gerçək system design sualının mərkəzində yer alır.


## Niyə Vacibdir

Interview-da sistem dizaynına başlamazdan əvvəl QPS, storage, bandwidth hesablamaq lazımdır — əks halda düzgün component seçimi mümkün olmur. Real layihələrdə capacity planning, hardware seçimi, cost estimation üçün bu bacarıq birbaşa lazımdır.

## Əsas Anlayışlar

### 1. Əzbərləmək Lazım Olan Rəqəmlər

**Powers of 2:**
```
2^10 = 1 KB  (1024)
2^20 = 1 MB  (1,048,576)
2^30 = 1 GB  (~1 billion)
2^40 = 1 TB  (~1 trillion)
2^50 = 1 PB  (~1 quadrillion)
```

**Powers of 10:**
```
10^3  = Thousand       (K)
10^6  = Million        (M)
10^9  = Billion        (B)
10^12 = Trillion       (T)
```

### 2. Vaxt Konvertasiyaları

```
1 second       = 1,000 ms = 1,000,000 μs = 10^9 ns
1 minute       = 60 seconds
1 hour         = 3,600 seconds (~3.6 K)
1 day          = 86,400 seconds (~86 K, kabul et: 100K)
1 month        = ~2.5 M seconds
1 year         = ~31.5 M seconds (~32 M)
```

### 3. Latency Rəqəmləri (Jeff Dean-ın məşhur cədvəli)

```
L1 cache reference                           0.5 ns
L2 cache reference                           7   ns
Mutex lock/unlock                           25   ns
Main memory reference                      100   ns
Compress 1 KB with Zippy                 3,000   ns = 3 μs
Send 1 KB over 1 Gbps network           10,000   ns = 10 μs
Read 4 KB randomly from SSD            150,000   ns = 150 μs
Read 1 MB sequentially from memory     250,000   ns = 250 μs
Round trip within same datacenter      500,000   ns = 500 μs
Read 1 MB sequentially from SSD      1,000,000   ns = 1 ms
Disk seek                           10,000,000   ns = 10 ms
Read 1 MB sequentially from disk    20,000,000   ns = 20 ms
Send packet CA→NL→CA               150,000,000   ns = 150 ms
```

### 4. Availability (Nine-ların tərcüməsi)

```
99%      (2 nines)    = 3.65 days/year downtime
99.9%    (3 nines)    = 8.76 hours/year
99.99%   (4 nines)    = 52.6 minutes/year
99.999%  (5 nines)    = 5.26 minutes/year
99.9999% (6 nines)    = 31.5 seconds/year
```

### 5. Tipik Server Gücü

```
Modern server (2024):
- 16-64 CPU cores
- 64-512 GB RAM
- 1-10 TB SSD
- 10 Gbps network
- ~10,000-50,000 QPS (typical web app)

Database server:
- MySQL/Postgres: ~10-50K simple reads/sec
- Redis: 100K-1M ops/sec (memory-bound)
- Cassandra: 10-50K writes/sec per node
```

## Nümunələr

### Nümunə 1: Twitter tipli sistem

**Tələb**: 300M istifadəçi, orta gündə bir tweet

**QPS hesablaması:**
```
Günlük tweet: 300M tweet/day
Saniyədə: 300M / 86,400 ≈ 3,500 tweets/sec (average)

Peak (2-3x average):
Peak QPS ≈ 10,000 tweets/sec

Read QPS (tweet oxuyanlar):
Hər istifadəçi 30 tweet/gün oxuyur
300M * 30 = 9B reads/day
9B / 86,400 ≈ 100,000 reads/sec
Peak: 300,000 reads/sec
```

**Storage hesablaması:**
```
Tweet ölçüsü: 280 char * 2 bytes = 560 bytes
+ metadata (id, user_id, timestamp) = 200 bytes
Toplam: ~1 KB per tweet

Günlük storage: 300M * 1 KB = 300 GB/day
İllik: 300 GB * 365 ≈ 110 TB/year

5 il üçün: 550 TB
Replikasiya (3x): 1.65 PB
```

**Bandwidth:**
```
Peak read traffic: 300K reads/sec * 1 KB = 300 MB/sec
= 2.4 Gbps
```

### Nümunə 2: URL Shortener

**Tələb**: Ayda 500M yeni URL

**QPS:**
```
500M / 30 days / 86,400 sec ≈ 200 writes/sec
Read:write ratio 100:1 → 20,000 reads/sec
Peak 2x: 40,000 reads/sec
```

**Storage:**
```
URL ölçüsü: 100 bytes (long URL) + 10 bytes (short) + metadata = 500 bytes
Aylıq: 500M * 500 bytes = 250 GB
10 il: 30 TB
```

**Unikallıq üçün açar sayı:**
```
Base62 (a-z, A-Z, 0-9) istifadə etsək:
7 char: 62^7 = 3.5 trillion kombinasiya
10 il * 500M/ay = 60B URL → 7 char kifayətdir
```

### Nümunə 3: Chat Sistemi (WhatsApp tipli)

**Tələb**: 1B active istifadəçi, gündə 50 mesaj/istifadəçi

**Messages:**
```
Günlük: 1B * 50 = 50B mesaj
Saniyədə: 50B / 86,400 ≈ 580,000 mesaj/sec
Peak 3x: 1,740,000 mesaj/sec ≈ 2M mesaj/sec
```

**Storage:**
```
Mesaj ölçüsü: 100 bytes
Günlük: 50B * 100 B = 5 TB/day
Aylıq: 150 TB
İllik: 1.8 PB

Replikasiya 3x: 5.4 PB/year
```

**Connection-lar (WebSocket)**:
```
1B active user, 10% eyni vaxtda online:
100M concurrent connection
Hər server 100K connection həll edə bilsə:
100M / 100K = 1,000 server lazımdır
```

### Nümunə 4: Video Streaming (YouTube tipli)

**Tələb**: 2B istifadəçi, gündə 1 saat video

**Total video stream hours:**
```
2B * 1 hour = 2B saat/gün
```

**Bandwidth:**
```
HD video: 5 Mbps
Concurrent viewers (10% eyni vaxtda): 200M
Peak bandwidth: 200M * 5 Mbps = 1 Pbps (!!)

Bu səviyyə bandwidth üçün global CDN lazımdır.
```

**Storage:**
```
Günlük yeni video: 500 saat/dəq * 60 * 24 = 720K saat/gün
Hər saat HD: 2 GB
Günlük: 720K * 2 GB = 1.4 PB/day
İllik: 500 PB/year
```

### Nümunə 5: Laravel E-commerce

**Tələb**: 10M aktiv istifadəçi, Black Friday peak

**Normal day:**
```
Page views: 10M * 5 pages/gün = 50M/gün
QPS: 50M / 86,400 ≈ 600 req/sec
Peak (9am-9pm): 1,500 req/sec
```

**Black Friday:**
```
10x normal: 15,000 req/sec
DB reads: 10x * read ratio
DB writes (orders): 500 orders/sec peak
```

**Laravel server hesablaması:**
```
PHP-FPM işçi: 100 concurrent req/server
15,000 req/sec ÷ 100 = 150 PHP server
+50% buffer = 225 server

Redis cache: 1-2 server kifayətdir (100K QPS/Redis)
MySQL: read replica + sharding 3-5 master
```

## PHP/Laravel Nümunələri

### Laravel Sistem Dizayn Hesablaması

Laravel blog platforması: 1M user, Medium-tipli sistem.

**Tələblər:**
- 1M aktiv istifadəçi
- Gündə 100K yeni post
- Post oxumaq:yazmaq = 50:1

**Storage (5 il):**
```
Post ölçüsü:
- Title (100 bytes)
- Body (ortalama 5 KB — markdown)
- Metadata (500 bytes)
- Hər post = 6 KB

5 il * 365 gün * 100K post/gün = 180M post
180M * 6 KB = 1 TB

+ User data: 1M * 10 KB = 10 GB
+ Comments: 10 per post * 500 bytes = 900 GB

Total: ~2 TB (replikasiya ilə 6 TB)
```

**MySQL setup:**
```
- Master + 2 Read Replica
- Read queries → replica
- Write queries → master
- Laravel "read/write" connection istifadə edir
```

**Redis cache:**
```
Hot posts (top 10K): 10K * 6 KB = 60 MB
Session data: 1M user * 1 KB = 1 GB
Feed cache: 100K active user * 10 posts * 1 KB = 1 GB

Redis server: 2 GB → 4 GB instance kifayətdir
```

**PHP-FPM konfiqurasiyası:**
```
Hər server 4 vCPU, 8 GB RAM
pm.max_children = 30 (hər child ~100 MB RAM)
Peak 10,000 req/sec → 10,000 / 30 ÷ 0.1s avg → ~33 server
+ 50% buffer = 50 server
```

**CDN bandwidth:**
```
Hər səhifə ~500 KB (HTML + CSS + JS + img)
5M səhifə görüntüsü/gün * 500 KB = 2.5 TB/gün
= 240 Mbps sustained, 1 Gbps peak
→ CDN (Cloudflare) şərtdir
```

### Capacity Planning Script

```php
<?php
// system_capacity.php — Laravel capacity estimation

class CapacityEstimator
{
    public static function calculate(array $params): array
    {
        $dau = $params['daily_active_users'];
        $actionsPerUser = $params['actions_per_user'];
        $avgPayloadKB = $params['avg_payload_kb'];
        $peakMultiplier = $params['peak_multiplier'] ?? 3;
        
        $dailyRequests = $dau * $actionsPerUser;
        $avgQPS = $dailyRequests / 86400;
        $peakQPS = $avgQPS * $peakMultiplier;
        
        $dailyStorageMB = ($dailyRequests * $avgPayloadKB) / 1024;
        $yearlyStorageGB = ($dailyStorageMB * 365) / 1024;
        
        // PHP-FPM calculation
        $avgResponseTime = 0.1; // 100ms
        $phpFpmProcesses = ceil($peakQPS * $avgResponseTime);
        $phpServers = ceil($phpFpmProcesses / 30); // 30 per server
        
        return [
            'avg_qps' => round($avgQPS),
            'peak_qps' => round($peakQPS),
            'daily_storage_mb' => round($dailyStorageMB),
            'yearly_storage_gb' => round($yearlyStorageGB),
            'php_processes_needed' => $phpFpmProcesses,
            'php_servers_needed' => $phpServers,
        ];
    }
}

// Istifadə
$result = CapacityEstimator::calculate([
    'daily_active_users' => 1_000_000,
    'actions_per_user' => 10,
    'avg_payload_kb' => 2,
    'peak_multiplier' => 3,
]);

print_r($result);
/*
Array (
    [avg_qps] => 116
    [peak_qps] => 347
    [daily_storage_mb] => 19531
    [yearly_storage_gb] => 6966
    [php_processes_needed] => 35
    [php_servers_needed] => 2
)
*/
```

## Real-World Nümunələr

### Facebook (Meta)

- 3B monthly active users
- 6B photos uploaded/day
- Storage: Exabytes
- Datacenter peak: 100+ Tbps

### Google Search

- 100K+ queries/sec (peak)
- Index: ~100 PB
- Latency target: <200ms

### Netflix

- 15% internet bandwidth (peak hours)
- 200M subscriber
- AWS: 100K+ VM instance

## Praktik Tapşırıqlar

**1. Back-of-the-envelope estimation niyə vacibdir?**
System design-ın fundamentidir. Rəqəmlər olmadan arxitektura qərarı vermək mümkün deyil (1 server və ya 1000 server? SQL və ya NoSQL?). Scale səviyyəsini müəyyən edir.

**2. QPS və bandwidth arasında fərq?**
- QPS: saniyədə sorğu sayı
- Bandwidth: saniyədə baytlar

Hər sorğu 10 KB-dirsə, 1000 QPS = 10 MB/sec = 80 Mbps.

**3. Peak QPS-i orta QPS-dən necə hesablayırıq?**
Ümumilikdə 2-3x çarpan. Lakin event-driven sistemlər (spike-lar) 10-100x ola bilər. Black Friday, flash sale və s.

**4. 99.9% availability nə deməkdir?**
İldə 8.76 saat downtime icazə verilir. Cloud provider SLA-ları (AWS EC2 99.99%) nəzərə alsaq, özü-özlüyündə 99.9%-ə çatmaq çətindir.

**5. Read:Write ratio niyə vacibdir?**
Cache strategiyasını müəyyən edir. 100:1 read-heavy → aggressive caching. Write-heavy (analytics, logging) → write-optimized DB (Cassandra, ClickHouse).

**6. 1 milyard istifadəçi üçün storage necə hesablanır?**
```
Per user ortalama data (metadata, content): 10 KB - 1 MB
1B user * 100 KB = 100 TB
+ 3x replication = 300 TB
+ Content (posts, media) → çox vaxt TB-dan PB-ya qədər
```

**7. Netflix-in 100 Tbps traffic-ı necə idarə olunur?**
Öz CDN (Open Connect) var. ISP-lərə cache server qoyur. Traffic origin datacenter-dən deyil, ISP-nin daxili network-dən gəlir.

**8. WebSocket connection limitini necə hesablayırıq?**
Server başına ~100K WebSocket connection (memory, file descriptor). 1M concurrent user → 10 server. Sticky session və ya pub/sub backend lazım.

## Praktik Baxış

1. **Powers of 2 və 10 əzbərlə** — müsahibədə tez hesablamalar üçün
2. **Latency rəqəmlərini bil** — disk 10ms, memory 100ns, network 500μs
3. **Peak 2-3x orta** — saxlama buffer həmişə olsun
4. **Read:Write ratio təyin et** — cache/DB arxitekturasına təsir edir
5. **Replikasiya nəzərə al** — storage hesabında 3x
6. **Growth rate** — 5 illik plan et, gələcək üçün
7. **Round et** — 347 QPS deyil, ~500 QPS de
8. **Sanity check** — rəqəmlər məntiqli görünür?
9. **Assumptions de** — "Assuming 100M DAU..." deyərək başla
10. **Iterativ yanaşma** — əvvəl rough estimate, sonra refine


## Əlaqəli Mövzular

- [Scaling](08-scaling.md) — capacity əsasında miqyaslandırma
- [Data Partitioning](26-data-partitioning.md) — neçə shard lazımdır
- [Database Design](09-database-design.md) — storage hesabı
- [Caching](03-caching-strategies.md) — cache hit rate hesabı
- [Load Balancing](01-load-balancing.md) — QPS əsasında server sayı
