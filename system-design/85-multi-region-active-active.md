# Multi-Region Active-Active Architecture

## Nədir? (What is it?)

Multi-region active-active - sistemin bir neçə coğrafi region-da eyni anda trafik qəbul etdiyi və istifadəçilərə xidmət göstərdiyi arxitekturadır. Bütün region-lar "aktiv"dir: hər biri read və write qəbul edir, heç biri passive standby-da durmur.

Əsas məqsədlər:
- **Low latency globally** - istifadəçi ən yaxın region-a yönəlir (300ms əvəzinə 30ms)
- **Higher availability** - bir region-un outage-i bütün sistemi dağıtmır (99.99%+ SLA)
- **Disaster recovery** - region itkisi zamanı digərləri yükü götürür (RTO dəqiqələrlə)
- **Regulatory compliance** - GDPR tələbi ilə EU user data EU region-da qalır, Çin data Çin-də
- **Capacity** - trafik region-lar arasında paylanır, kapasitet horizontal genişlənir

Bu məqalə file 30 (disaster recovery) və file 26 (data partitioning) ilə sıx əlaqəlidir: active-active aslında DR-in ən yüksək pilləsidir, və region-lar arası sharding data partitioning strategiyasının coğrafi variantıdır.

## Active-Passive vs Active-Active (Active-Passive vs Active-Active)

### Active-Passive (Hot Standby)

Bir region bütün write-ları qəbul edir, digər region-lar passive standby kimi durur və yalnız failover zamanı aktivləşir.

```
       Users
         |
      GeoDNS (always returns us-east)
         |
         v
    [us-east ACTIVE]  -- async replication -->  [eu-west STANDBY]
         |                                            |
     writes + reads                             no traffic
```

Üstünlüklər: sadədir, write conflict yoxdur, strong consistency asandır.
Çatışmazlıqlar: failover 5-30 dəqiqə downtime, standby region istifadəsiz durur (waste), uzaq istifadəçilər üçün yüksək latency.

### Active-Active

Bütün region-lar eyni anda həm read həm write qəbul edir. Data replication iki tərəfli olur.

```
       Users (global)
        /    \    \
    GeoDNS (returns nearest region)
      /      |      \
     v       v       v
 [us-east] [eu-west] [ap-south]  <-- bidirectional replication
   ACTIVE    ACTIVE    ACTIVE
```

Üstünlüklər: zero-downtime failover, bütün region-lar istifadə olunur, istifadəçiyə yaxın latency.
Çatışmazlıqlar: write conflict resolution, split-brain riski, 2-3x daha kompleks ops, 2-3x infra cost.

## Traffic Routing Strategiyaları (Traffic Routing)

### 1. Anycast IP (BGP)

Eyni IP adresi bir neçə region-da elan olunur (BGP announce). Internet routing protokolu istifadəçini ən yaxın POP-a göndərir. Cloudflare, Google Cloud Global LB belə işləyir.

Üstünlük: DNS cache problem yoxdur, avtomatik fail-over (BGP withdraw).
Çatışmazlıq: mürəkkəbdir, AS ownership tələb edir.

### 2. GeoDNS

DNS server istifadəçinin coğrafi mövqeyinə əsasən müxtəlif IP qaytarır (Route53 geolocation, Cloudflare Geo Steering). Tokyo user -> DNS query -> returns ap-northeast IP.

Üstünlük: sadə, cost-effective. Çatışmazlıq: DNS TTL (failover 60+ saniyə), ISP DNS cache, coğrafi dəqiqlik zəif.

### 3. GSLB (Global Server Load Balancer)

Application-layer load balancer, health check-lər aparır, CDN-ə inteqrasiya edir. AWS Global Accelerator, Cloudflare Load Balancing, F5 BIG-IP DNS.

Üstünlük: real-time health checks, weighted routing, A/B test, session affinity.
Çatışmazlıq: daha baha, vendor lock-in.

### Health Check Driven Routing

Hər region-da endpoint `/health` cavabı verir. GSLB hər 10 saniyədə yoxlayır. Region "unhealthy" olsa, DNS-dən çıxarılır.

```
GSLB health checks:
  us-east /health -> 200 OK (latency 50ms)    -> in pool
  eu-west /health -> 200 OK (latency 30ms)    -> in pool
  ap-south /health -> timeout                  -> DRAIN
```

## Data Replication Modelləri (Data Replication Models)

### 1. Single-Leader (Master-Slave)

Yalnız bir region "leader"dir, digərləri "follower". Bütün write-lar leader-ə gedir, async/sync replication follower-lərə.

```
[us-east LEADER] ---async---> [eu-west FOLLOWER]
                 ---async---> [ap-south FOLLOWER]
```

- Write-lar leader region-da lokaldir, qalan region-dan write-ları əvvəl cross-region göndərilir (100-200ms).
- Read-lər follower-dən lokal, amma stale data mümkündür.
- Leader fail olsa promotion lazımdır (failover).

Use case: strong consistency tələb olunan, read-heavy workload (product catalog, user profile).

### 2. Multi-Leader

Hər region həm read həm write qəbul edir. Async replication full-mesh iki tərəfli (us-east <-> eu-west <-> ap-south).

Write conflict mümkündür: eyni row eyni vaxtda iki region-da dəyişsə? Conflict resolution lazımdır.

Use case: geographic locality, high write availability (user-generated content, sosial feed).

### 3. Leaderless (Dynamo-Style)

Heç bir leader yoxdur. Write N node-a göndərilir, W node təsdiqləsə uğurlu sayılır. Read R node-dan yığılır. Quorum: `W + R > N` strong consistency verir.

Cross-region leaderless yüksək latency yaradır (hər write 3 region-a getməli). Riak, Cassandra multi-DC istifadə edir.

### 4. Strong Consistency Multi-Region

**Google Spanner:** TrueTime API (atomic clock + GPS) istifadə edir, global strict serializability təmin edir. Cross-region Paxos.

**CockroachDB:** Per-range Raft consensus, geo-partitioned leaseholder, ACID transactions multi-region.

Latency cost: commit üçün 2PC round-trip (trans-atlantic 200ms+).

## Conflict Resolution (Conflict Resolution)

Multi-leader yanaşmada iki region eyni anda eyni data-nı dəyişsə nə olur?

### 1. Last-Write-Wins (LWW)

Timestamp ən yüksək olan qalib gəlir. Sadədir, amma:
- Clock skew data loss yaradır (NTP ~ms drift)
- Concurrent update-lərdən biri itir
- Cassandra default strategy

### 2. CRDTs (Conflict-free Replicated Data Types)

Əməliyyatlar riyazi olaraq commutative və idempotent-dir, avtomatik merge olunurlar. Detallar üçün file 34 (CRDT) bax.

Nümunələr: G-Counter (grow-only counter), OR-Set (observed-remove set), RGA (sequence for text).

Use case: Redis CRDB, Riak, Yjs (collaborative editing), Facebook Apollo.

### 3. Application-Specific Merge

Biznes məntiqinə əsasən custom merge. Dynamo Paper-ın shopping cart nümunəsi: iki cart versiyası merge olunur (union), heç bir item itmir (amma silinmiş item qaytara bilər).

```php
// Shopping cart merge example
public function mergeCarts(Cart $local, Cart $remote): Cart {
    $merged = new Cart();
    foreach ($local->items + $remote->items as $item) {
        $merged->addOrUpdate($item, max qty);
    }
    return $merged;
}
```

## Geographic Sharding (Sharding by Region)

Hər shard bir region-da master olur. Yazılar həmin region-da lokal olur (write locality), cross-region reads hələ mümkündür.

```
Sharding key: user.home_region
  EU users   -> eu-west shard (master)
  US users   -> us-east shard (master)
  APAC users -> ap-south shard (master)
```

Üstünlük: write conflict yoxdur (hər shard bir master), GDPR-friendly.
Çatışmazlıq: user travel edəndə latency, cross-region aggregation mürəkkəb.

## Cross-Region Latency Budget (Latency Budget)

Fiziki qanunlar, işığın sürəti hər 1000 km-də ~5ms gedişi:

- Intra-continent: 30-100ms (Frankfurt <-> London ~15ms)
- Trans-Atlantic: 70-100ms (New York <-> London)
- Trans-Pacific: 150-200ms (Tokyo <-> California)
- Half-globe: 250-300ms (Sydney <-> London)

2PC protokolu 2-4 RTT tələb edir, trans-pacific 2PC commit 600-1200ms ola bilər. Buna görə strong consistency multi-region-da bahadır.

## Edge Caching (Edge Caching)

CDN (CloudFront, Cloudflare, Fastly) static asset-ləri 200+ PoP-da cache edir - multi-region-ın ən sadə formasıdır. Dynamic content üçün: regional Redis cluster (read-heavy), stale-while-revalidate, edge compute (Cloudflare Workers, Lambda@Edge).

Request path: User -> Edge PoP (5ms static) -> Regional Redis (20ms) -> Origin DB (50ms).

## Session Management (Session Management)

- **Sticky Region (Session Affinity)** - user bir region-a pin olunur (cookie/header ilə). Üstünlük: session replicate lazım deyil. Çatışmazlıq: region fail olsa user logout olur.
- **Replicated Sessions** - session bütün region-larda replicate (DynamoDB Global Tables, Redis CRDB). İstənilən region request-i götürür.
- **Stateless JWT** - ən yaxşı variant: session-suz auth. JWT payload user-id+permissions daşıyır, hər region public key ilə lokal verify edir. File 14 (auth)-da detallıdır.

## Per-Service Database Strategy (DB Strategy per Service)

Hər mikroxidmət üçün fərqli strategiya:

| Service | Strategy | Niyə? |
|---------|----------|-------|
| User auth | Per-region writable replica, async | Login latency az, stale 1-2s OK |
| Shopping cart | CRDT və ya per-user shard | Offline tolerant, conflict safe |
| Financial tx | Single leader, strong consistency | Zero data loss, ACID |
| Product catalog | Read-only replica everywhere | Read-heavy, yazıları nadir |
| Analytics | Kafka + per-region warehouse | Eventual OK, batch processing |
| Chat/messaging | Per-region shard, message queue | Low latency priority |

## Event Propagation (Event Propagation)

Region-lar arası event stream:

- **Kafka MirrorMaker 2** - topic-ləri region-lar arası mirror edir
- **DynamoDB Global Tables** - avtomatik multi-region async replication
- **Google Spanner** - sinxron multi-region
- **AWS Aurora Global** - storage-level replication, <1s lag
- **Change Data Capture (CDC)** - Debezium ilə DB change-ləri event-ə çevirib mirror etmək

## Clock Synchronization (Clock Sync)

Multi-region timestamp üçün clock sinxronizasiyası kritikdir.

- **NTP (Network Time Protocol)** - ~1-10ms drift, ucuz, universal
- **PTP (Precision Time Protocol)** - sub-microsecond, data center içində
- **Google TrueTime** - atomic clock + GPS, `TT.now()` interval qaytarır (`earliest, latest`), Spanner istifadə edir
- **AWS Time Sync Service** - NTP + satellite, 1ms doğruluq

Clock skew problemləri: LWW data loss, token expiry bugs, cache poisoning.

## Failover Ssenariləri (Failover Scenarios)

### 1. Tam Region Itkisi (Full Region Failure)

AWS us-east-1 tam düşüb. Nə olur?

```
Step 1: Health check failures (10-30s)
Step 2: GSLB withdraws region from pool
Step 3: GeoDNS updates (TTL 60s) - clients re-resolve
Step 4: Traffic shifts to eu-west + ap-south
Step 5: Capacity doubles in remaining regions (pre-scaled)
```

Hazırlıq: digər region-lar 2x capacity saxlamalı (cost!), ya auto-scale sürətli olmalı.

### 2. Partial Outage (Qismi Outage)

Region-ın yalnız bir AZ və ya bir service düşür. Full drain lazım deyil, partial drain.

Progressive degradation: write traffic drain, read traffic qalır (replica hələ sağlam).

### 3. Split-Brain

Network partition - iki region bir-birini görmür, hər biri "qalan region öldü" düşünüb leader olur.

Həll:
- **Fencing token** - global coordinator (Zookeeper/etcd) leader seçir
- **Quorum-based** - 3+ region, 2/3 çoxluq qalan leader
- **Accept eventual reconciliation** - CRDT ilə conflict qorxulu deyil

## Regulatory Constraints (Tənzimləmə Məhdudiyyətləri)

- **GDPR** - EU citizen data EU region-dan çıxmamalı, "right to be forgotten" bütün region-larda tətbiq edilməli
- **Schrems II** - US-ə EU data transfer məhduddur (Privacy Shield invalid)
- **China Cybersecurity Law** - Çin user data Çin-dəki region-da saxlanılmalı
- **Russia Data Localization** - rus data Rusiya-da
- **HIPAA (US healthcare)** - data residency US
- **PCI DSS** - payment data şifrələnməli, audit trail multi-region-da konsistent

Bu qaydalar əsasən sharding-i məcbur edir (geographic sharding).

## Laravel Multi-Region Konfiqurasiyası (Laravel Example)

### config/database.php - read/write connection

```php
'mysql' => [
    'read'  => ['host' => [env('DB_READ_HOST_LOCAL')]],    // local replica
    'write' => ['host' => env('DB_WRITE_HOST_REGIONAL')],  // user's home region
    'driver' => 'mysql',
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'sticky' => true,
],
```

### Region-aware routing middleware

```php
class RegionRouter
{
    public function handle($request, Closure $next)
    {
        $region = config('app.region');                          // us-east / eu-west
        $userRegion = $request->user()?->home_region ?? $region;

        // Financial writes must go to user's home region
        if ($request->is('api/payment/*') && $userRegion !== $region) {
            return redirect(config("regions.{$userRegion}.endpoint")
                . $request->path(), 307);
        }

        config(['database.connections.mysql.write.host' =>
            config("regions.{$userRegion}.db_write")]);

        return $next($request);
    }
}
```

### JWT stateless auth (no session pinning)

```php
$token = JWT::encode([
    'sub' => $user->id,
    'region' => $user->home_region,
    'exp' => time() + 3600,
], config('app.jwt_private_key'), 'RS256');
// Token issued in any region, validated in any region with public key
```

### Geo-DNS (Cloudflare Load Balancer)

```yaml
pool:
  - name: eu-west
    origin: eu-west.api.example.com
    health_check: https://eu-west.api.example.com/health
  - name: us-east
    origin: us-east.api.example.com
traffic_steering: geo
rules:
  - countries: [DE, FR, IT, ES] -> eu-west
  - countries: [US, CA, MX]     -> us-east
  - default                      -> closest by latency
```

## Observability (Monitoring)

Per-region metrics:
- **RED (Rate, Errors, Duration)** - hər region üçün ayrı
- **Replication lag** - leader->follower gecikmə (saniyə)
- **Cross-region bandwidth** - data transfer cost monitoring
- **Regional error rate** - bir region "sick" olmadan bilmək üçün
- **Failover drill success** - GameDay nəticələri

Grafana dashboard-lar region dimension ilə filter olunmalı. Alerting per-region thresholds.

## Chaos Testing (Chaos Testing)

Region failover-i düzgün işlədiyini yoxlamaq üçün chaos engineering (detallar file 56):
- **GameDay** - planlı region shutdown exercise
- **Chaos Monkey / Gremlin** - random instance kill
- **Region blackhole** - network-ı region-a kəsib müşahidə etmək
- **Latency injection** - cross-region 500ms əlavə et, görüm necə reaksiya verir

Netflix hər həftə production-da chaos exercise aparır. Onların prinsipi: "DR plan test edilməyibsə, yoxdur."

## Real-World Nümunələr (Real-World Examples)

- **Netflix** - bütün AWS region-lar active, istənilən region 3x capacity götürür
- **Facebook** - regional tier-lər (primary + read replicas), TAO cache layer
- **Amazon DynamoDB Global Tables** - avtomatik multi-master, LWW conflict resolution
- **Google Spanner** - globally distributed SQL, strong consistency TrueTime ilə
- **CockroachDB** - multi-region SQL, Raft consensus, geo-partitioning
- **Shopify** - pod architecture, hər pod region-ə bağlı
- **Stripe** - multi-region Mongo + Kafka, financial data strong consistency

## Müsahibə Sualları (Interview Q&A)

**S1: Active-passive və active-active arasında seçim necə edirsən?**

Active-passive sadədir və ucuz (1x infra + backup), amma failover downtime var (5-30 dəq). Active-active zero-downtime və aşağı global latency verir, amma 2-3x cost + conflict resolution complexity. Mission-critical (banking, medical) və SLA 99.99%+ sistemlər üçün active-active. Startup və daxili tool üçün active-passive yaxşıdır.

**S2: Multi-leader-də eyni user eyni anda iki region-da profilini dəyişir. Necə həll edirsən?**

Variantlar: (1) LWW - ən böyük timestamp qalib, sadə amma data loss. (2) CRDT - profile field-ləri CRDT kimi modelləşdir, avtomatik merge. (3) Geographic sharding - user-in home_region-u var, yalnız o region write qəbul edir, digər region redirect. (4) Application merge - per-field strategy (email LWW, bio append-only). Praktikada 3+4 kombinasiyası istifadə olunur.

**S3: GeoDNS TTL-i nə qədər olmalı?**

Trade-off: kiçik TTL (30-60s) failover sürətli, amma query cost yüksək. Böyük TTL (3600s) ucuz amma failover uzun. Praktikada 60-300s. ISP-lər TTL-ə riayət etmir - buna görə anycast və ya health-check-driven LB daha etibarlıdır. Cloudflare LB 30s TTL + proxy ilə həll edir.

**S4: Trans-atlantic sinxron replication nə üçün çətindir?**

İşıq sürəti fiziki limitdir: New York-London ~5500 km, fiber ilə ~40ms bir yön, 70-100ms RTT. 2PC üçün 2 RTT lazımdır - yəni hər write 140-200ms. Müqayisə: region daxilində 1-5ms. User UX-i pisdir, buna görə sistemlər async replication və ya Spanner kimi specialized DB seçir.

**S5: Split-brain ssenarisini necə əngəlləyirsən?**

Həll: (1) Global coordinator (Zookeeper/etcd) leader seçir, minority partition read-only olur. (2) Quorum-based - 3+ region-lu sistemdə majority (2/3) write qəbul edir. (3) CRDT - split-brain qəbul olunur, sonra reconcile. (4) Manual failover - on-call engineer qərar verir (aviation, financial). Netflix və Google majority-quorum istifadə edir.

**S6: Regional sharding GDPR problemini necə həll edir?**

Shard key olaraq user.home_region. EU user data (PII, mesajlar) yalnız EU region-da saxlanılır. Analytics üçün anonymized aggregate global warehouse-a göndərilir. "Right to be forgotten" - DELETE leader region-da, sonra replicate. User EU-dan US-ə köçəndə data migration mürəkkəbdir, legal təsdiq tələb edir.

**S7: Multi-region cost-u necə optimizasiya edirsən?**

(1) Cross-region bandwidth bahadır ($0.02-0.09/GB AWS) - data locality + minimum transfer. (2) Read replica-ları 2-3 region-la məhdudlaşdır. (3) Tiered storage - hot multi-region, cold tək region. (4) Reserved instances / Savings Plans. (5) CDN edge cache DB hit-i azaldır. (6) Compression (Kafka snappy/lz4). (7) Async batch replication. Netflix-in ən böyük cost-larından biri cross-region bandwidth-dir.

**S8: Active-active sistemdə deploy necə edirsən?**

Regional rolling deploy: bir region-da deploy, metrics müşahidə, sonra digərləri. Per-region canary (5% -> 100%). Feature flag region-specific. DB migration - backward-compatible schema (expand/contract pattern): öncə yeni sütun, sonra code dəyişir, axırda köhnə silinir. Hər mərhələdə region-lar arası fərq backward compatible olmalıdır.

## Best Practices (Best Practices)

1. **Stateless services** - scale-out və region failover asandır. Session data kənar store-da (Redis, JWT).
2. **Idempotent APIs** - cross-region retry duplicate yaratmasın. Idempotency key hər write request-də.
3. **Async-first design** - sinxron cross-region call-lardan qaç. Event-driven architecture (Kafka, SNS).
4. **Per-region circuit breaker** - bir region slow olsa timeout-u artırma, fail-fast et və rerouting.
5. **Data residency classification** - hər data tipi üçün "hansı region-larda ola bilər" mark et.
6. **Cell-based architecture** - region daxilində kiçik "cell"-lərə böl, blast radius kiçik olsun (AWS cell pattern).
7. **Replication lag monitoring** - alerting 10s+ lag üzrə, dashboard hər leader-follower cütü üçün.
8. **Failover drill hər kvartal** - real GameDay, planned region shutdown.
9. **DNS + health check + capacity planning** - failover zamanı qalan region-lar yükü götürə bilmirsə, failover işləmir.
10. **Regulatory audit** - hər il data flow diagram-ı hazırla, legal təsdiq et (GDPR DPO).
11. **Observability per-region** - dashboard region dimension ilə, SLI hər region üçün ayrıca.
12. **Cost alert** - cross-region bandwidth spike-larına alert (outage və ya bug göstəricisi).
13. **Documentation** - hər service üçün "home region", "failover region", "RPO/RTO", "conflict strategy" yazılı olsun.
14. **Dependency mapping** - service A region-larda active, amma DB X yalnız us-east-dədir? Onda A aslında active-passive-dir.
15. **Chaos testing** - file 56 (chaos engineering) baxın, multi-region üçün network partition test-ləri kritikdir.
