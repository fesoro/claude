# Database Replication (Senior)

## İcmal

**Database Replication** — eyni data-nın bir neçə database node-unda (replica-da) saxlanmasıdır. Məqsəd: data-ya daha yaxın olmaq (geo-distribution), yükü bölüşdürmək (read scalability), bir node çökəndə sistemin işləməyə davam etməsi (high availability) və analytics/backup iş yükünü production-dan ayırmaq.

Replikasiya distributed sistemlərin bel sütunudur — demək olar ki, hər ciddi production DB (MySQL, PostgreSQL, MongoDB, Cassandra, Redis, DynamoDB) replikasiyanı bu və ya digər formada dəstəkləyir.


## Niyə Vacibdir

Single DB node — single point of failure-dır. Leader-follower replication read scale verir, failover imkanı yaradır; multi-leader conflict resolution tələb edir. Aurora, PlanetScale, Patroni — hamısı bu konseptlər üzərindədir. Replication lag real UX problemlərə gətirir.

## Niyə Replikasiya? (Why Replicate?)

| Məqsəd | İzahı |
|--------|-------|
| **High Availability** | Bir node ölsə, başqası işləməyə davam edir (failover) |
| **Read Scalability** | Oxular replica-lara paylanır, leader yalnız yazıya fokuslanır |
| **Geo-Distribution** | İstifadəçiyə yaxın region-dan oxu (aşağı latency) |
| **Backups** | Replica-dan consistent snapshot götürmək — production-a toxunmur |
| **Analytics Isolation** | OLAP/reporting ayrı replica-da, OLTP-yə zərər vermir |
| **Disaster Recovery** | Başqa data-center-də replica varsa, region itsə belə data sağdır |

## Əsas Anlayışlar

### 1. Single-Leader Replication (Leader-Follower)

Ən geniş yayılmış model. MySQL, PostgreSQL, MongoDB replica set, Redis default olaraq belə işləyir.

```
         ┌──────────┐
Writes ──→│  Leader  │
         └─────┬────┘
               │ replication log
       ┌───────┼───────┐
       ↓       ↓       ↓
   ┌──────┐┌──────┐┌──────┐
   │ F1   ││ F2   ││ F3   │ ← Reads
   └──────┘└──────┘└──────┘
```

- Bütün **yazılar** leader-ə gedir
- Leader dəyişiklikləri **replication log** vasitəsilə follower-lərə göndərir
- **Oxular** istənilən node-dan (adətən follower-dən)

### 2. Sync vs Semi-Sync vs Async

| Növ | Davranış | Latency | Data Safety |
|-----|----------|---------|-------------|
| **Synchronous** | Leader bütün follower-lər yazmayınca commit etmir | Yüksək | Ən yüksək |
| **Semi-Synchronous** | Ən azı 1 follower təsdiqləməlidir | Orta | Yüksək |
| **Asynchronous** | Leader commit edir, follower gələndə gəlir | Aşağı | Ən aşağı (data loss riski) |

**Praktikada:** tam sync yavaşdır — bir follower ləngidərsə bütün yazılar blok olur. Semi-sync yaxşı kompromisdir (MySQL, PostgreSQL dəstəkləyir).

### 3. Replikasiya Metodları (Replication Methods)

| Metod | Necə işləyir | Problem |
|-------|--------------|---------|
| **Statement-based** | SQL statement-lərini follower-də yenidən icra et | `NOW()`, `RAND()`, auto-increment qeyri-determinist |
| **Write-Ahead Log (WAL)** | WAL baytları bayt-bayt follower-ə göndərilir (PostgreSQL streaming) | Versiya-specific format, storage engine bağlı |
| **Row-based (logical)** | Hər dəyişən sətir log-a yazılır (MySQL binlog ROW format) | Versiya-müstəqil, amma log böyükdür |
| **Trigger-based** | DB trigger dəyişikliyi başqa cədvələ yazır, app replicate edir | Performance cost, mürəkkəb |

Müasir sistemlər adətən **row-based (logical)** və ya **WAL shipping** istifadə edir.

### 4. Replikasiya Gecikməsi (Replication Lag)

Async replication-da follower leader-dən geri qalır. Lag milisaniyədən dəqiqələrə qədər ola bilər (network, yük, uzun transaction-lar).

**Lag-dan yaranan problemlər:**

- **Read-your-writes**: İstifadəçi profilini yenilədi, dərhal oxudu → köhnə data görür
- **Monotonic reads**: Eyni istifadəçi əvvəl replica A-dan oxudu (yeni data), sonra replica B-dən (köhnə data) → "zaman geriyə qaçır"
- **Causal consistency**: Şərh post-dan əvvəl görünür

### 5. Failover Mexanikası

Leader çökəndə hansı follower yeni leader olacaq? Kim qərar verir?

```
Failover addımları:
1. Detection    → Leader cavab vermir (heartbeat timeout)
2. Election     → Yeni leader seç (ən yeni log-u olan follower)
3. Reconfigure  → Client-lər və follower-lər yeni leader-ə yönəlir
4. Recovery     → Köhnə leader qayıdanda follower kimi qoşulur
```

**Riski:**
- **Split-brain**: 2 node özünü leader sayır → data inconsistency
- **Data loss**: Async replication-da commit edilmiş amma replicate edilməmiş data itir
- **Cascading failure**: Yeni leader yükə tab gətirmir

**STONITH** (Shoot The Other Node In The Head) — köhnə leader-i zorla söndür ki, split-brain olmasın.

### 6. Multi-Leader Replication

Bir neçə node yazı qəbul edir. Hər yazı digər leader-lərə replicate olunur.

```
┌──────────┐           ┌──────────┐
│ Leader A │ ←───────→ │ Leader B │
│ (EU DC)  │           │ (US DC)  │
└────┬─────┘           └────┬─────┘
     │                       │
     ↓                       ↓
 Followers              Followers
```

**İstifadə halları:**
- **Multi-datacenter**: Hər DC-nin öz leader-i, aşağı write latency
- **Offline clients**: Mobile app offline yazır, online olanda sync
- **Collaborative editing**: Google Docs kimi real-time editing

**Əsas problem — konflikt:** 2 istifadəçi eyni record-u fərqli leader-də dəyişərsə?

| Konflikt Həlli | İzahı |
|----------------|-------|
| **Last-Write-Wins (LWW)** | Ən son timestamp qalib — sadə, amma data itir |
| **Version Vectors** | Hər replica öz counter-ini saxlayır, causal relationship izlənir |
| **CRDTs** | Math-vari konflikt-free data structures (counter, set) |
| **Custom merge** | Business logic qərar verir (məs: hər iki dəyər birləşdirilir) |
| **User resolution** | Konflikti istifadəçiyə göstər (Git merge conflict) |

### 7. Leaderless Replication (Dynamo-Style)

Leader yoxdur. Client bir neçə node-a paralel yazır, bir neçə node-dan oxuyur. Cassandra, DynamoDB, Riak belə işləyir.

**Quorum formulası:**

```
N = ümumi replica sayı
W = yazı üçün təsdiq lazım olan node sayı
R = oxu üçün cavab lazım olan node sayı

W + R > N  →  Strong consistency
```

**Klassik seçim:** N=3, W=2, R=2

```
Yazı (W=2):
Client ──→ [N1 ✓] [N2 ✓] [N3 ✗]  → ACK (2/3 kifayətdir)

Oxu (R=2):
Client ──→ [N1] [N3]  → ən son timestamp qalib
```

**Kömək mexanizmləri:**

- **Read repair**: Oxu zamanı köhnə replica aşkar olursa, yenilənir
- **Anti-entropy (Merkle trees)**: Background prosess replica-ları müqayisə edir və fərqləri sinxronlaşdırır
- **Sloppy quorum**: Əsas node-lar əlçatmazdırsa, müvəqqəti olaraq başqa node-lara yaz
- **Hinted handoff**: Müvəqqəti node "hint" saxlayır, əsas node qayıdanda ona göndərir

## Nümunələr

### Laravel — Read/Write Split

`config/database.php`:

```php
<?php
return [
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'read' => [
                'host' => [
                    'replica-1.db.internal',
                    'replica-2.db.internal',
                    'replica-3.db.internal',
                ],
            ],
            'write' => [
                'host' => ['leader.db.internal'],
            ],
            'sticky'    => true,  // Read-your-writes üçün kritik
            'database'  => env('DB_DATABASE'),
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
];
```

**Sticky connection necə işləyir:**

```php
// Bir HTTP request daxilində:
$user = User::create(['name' => 'Ayten']);  // → leader (write)
$found = User::find($user->id);              // → leader (sticky açıq)
                                              //   olmasaydı → replica (lag riski)

// Növbəti request-də:
$users = User::all();                        // → replica (yazı olmayıb)
```

### Manual Connection Seçimi

```php
// Hesabat üçün məxsusi replica
$report = User::on('mysql-analytics-replica')
    ->where('created_at', '>=', now()->subDays(30))
    ->count();

// Kritik oxunu leader-dən məcbur et
$balance = Account::on('mysql')
    ->getConnection()
    ->getPdo(PDO::ATTR_DEFAULT_FETCH_MODE)
    ->table('accounts')
    ->useWritePdo()  // Laravel 10+: leader-dən oxu
    ->where('id', $accountId)
    ->value('balance');
```

### Replication Lag Detection

```php
class ReplicationHealthService
{
    public function getLagSeconds(string $connection): int
    {
        // MySQL
        $status = DB::connection($connection)
            ->select('SHOW SLAVE STATUS')[0] ?? null;

        return $status->Seconds_Behind_Master ?? 999;
    }

    public function pickHealthyReplica(): string
    {
        foreach (['replica-1', 'replica-2', 'replica-3'] as $replica) {
            if ($this->getLagSeconds($replica) < 2) {
                return $replica;
            }
        }
        return 'mysql';  // fallback leader
    }
}
```

### Read-After-Write with Cache Guard

```php
class OrderService
{
    public function place(array $data): Order
    {
        $order = Order::create($data);

        // Bu request-də sticky işləyir, amma başqa request gələrsə?
        Cache::put("order:{$order->id}", $order, 10);  // 10 san. qoruma

        return $order;
    }

    public function find(int $id): ?Order
    {
        // Əvvəl cache — replica lag pəncərəsində qoruyur
        return Cache::remember("order:{$id}", 60, fn () => Order::find($id));
    }
}
```

## Texnologiya Müqayisəsi (Technology Comparison)

| Sistem | Model | Konflikt Həlli | Failover |
|--------|-------|----------------|----------|
| **MySQL (GTID)** | Single-leader | — (single-leader) | Orchestrator, ProxySQL |
| **PostgreSQL streaming** | Single-leader (WAL shipping) | — | Patroni, repmgr |
| **MongoDB replica set** | Single-leader (auto-elect) | — | Built-in (Raft-like) |
| **Redis Sentinel** | Single-leader | — | Sentinel quorum |
| **Redis Cluster** | Multi-shard, single-leader per shard | — | Gossip + auto-failover |
| **Cassandra** | Leaderless (Dynamo) | LWW (timestamp) | N/A (no leader) |
| **DynamoDB** | Leaderless (managed) | LWW + conditional writes | Avtomatik |
| **CockroachDB** | Raft per range | Consensus (no conflict) | Built-in |

## Cross-Region Replikasiya

| Məsələ | Nüans |
|--------|-------|
| **Bandwidth** | Region-lararası trafik bahadır, row-based log yüksək həcm tuta bilər |
| **Latency** | Sync replication cross-region praktik deyil (100ms+ RTT) |
| **Consistency** | Adətən eventual consistency qəbul edilir, region-daxili strong qalır |
| **Conflict** | Multi-leader cross-region-da konflikt qaçınılmaz — strategy əvvəldən seç |
| **Cost** | AWS/GCP cross-region data transfer bahadır — filter edib göndər |

## Praktik Tapşırıqlar

### Q: Single-leader və multi-leader replikasiya arasında necə seçirsən?
**A:** Single-leader default seçimimdir — konflikt yoxdur, sadədir, əksər OLTP yüklər üçün kifayətdir. Multi-leader-i yalnız aşağıdakı hallarda seçirəm: (1) multi-datacenter yazı latency-si kritikdir, (2) offline clients sync lazımdır (mobile app), (3) collaborative editing. Multi-leader konflikt həlli mürəkkəbləşdirir, ona görə əvvəlcə CRDT və ya deterministic merge strategy olub-olmadığına baxıram.

### Q: Replication lag problemi necə həll olunur?
**A:** Bir neçə təbəqə: (1) **Sticky connection** — eyni request-də yazıdan sonra oxu leader-dən gedir (Laravel `sticky => true`); (2) **Cache write-through** — yazıdan sonra cache-ə dərhal qoy, bir neçə saniyə qoru; (3) **Semi-sync replication** — ən azı 1 replica təsdiqləsin; (4) **Lag monitoring** — `Seconds_Behind_Master` yüksək olan replica-nı load balancer-dan çıxar; (5) **Kritik oxular üçün** leader-dən oxu (məs: balance, payment status).

### Q: Split-brain nədir və necə qarşısı alınır?
**A:** Split-brain — şəbəkə partition olduqda 2 node özünü leader sayır və hər ikisi yazı qəbul edir, data divergence yaranır. Qarşısı alınma yolları: (1) **Quorum-based election** — əksəriyyət (N/2+1) razılaşmasa leader seçilmir (Raft, Paxos); (2) **Fencing tokens** — yeni leader artan token alır, köhnə leader yazısı rədd edilir; (3) **STONITH** — köhnə leader fiziki/məntiqi olaraq öldürülür (power off, network isolate); (4) Tək sayda node istifadə et ki, tie olmasın.

### Q: Quorum-da `W + R > N` niyə strong consistency verir?
**A:** Çünki yazı ən azı W node-da olur, oxu ən azı R node-dan gəlir. W + R > N olduqda, oxu və yazı çoxluqları **məcburən kəsişir** — ən azı bir node son yazını görmüş olacaq. Client o node-un ən yeni timestamp-lı dəyərini götürür. N=3, W=2, R=2 klassik seçimdir. W=N, R=1 oxu sürətli amma yazı yavaş; W=1, R=N tərsinə. W=2, R=2 balansdır.

### Q: Sync və async replication arasında trade-off nədir?
**A:** **Sync**: yazı bütün (və ya bəzi) replica-lar təsdiqləyənə qədər commit olunmur — data loss sıfıra yaxın, amma latency yüksək və bir replica yavaş olarsa bütün yazılar blok olur. **Async**: leader commit edir, replikasiya arxa planda — latency aşağı, yüksək throughput, amma leader çökərsə replicate olunmamış commit-lər itir. Praktikada **semi-sync** seçilir: ən azı 1 replica təsdiqləsin. Finansal sistemlərdə sync, sosial şəbəkədə async normaldır.

### Q: MySQL binlog row-based və statement-based arasında fərq nədir?
**A:** **Statement-based**: SQL statement-ləri log-a yazılır, follower yenidən icra edir. Log kiçikdir, amma `NOW()`, `UUID()`, `RAND()` kimi qeyri-determinist funksiyalar və auto-increment race-lər problem yaradır. **Row-based**: hər dəyişən sətrin əvvəlki və yeni dəyəri log-a yazılır. Tam determinist, amma log həcmli olur (BLOB update bütün sətri yazır). MySQL default olaraq **MIXED** istifadə edir — əksəriyyətdə statement, riskli hallarda row. Production-da adətən ROW məsləhət görülür.

### Q: Leaderless replication-da "read repair" nə edir?
**A:** Client N node-dan oxuyur (məs: R=3). Əgər node-lar fərqli versiya qaytarırsa (timestamp və ya version vector fərqi), client ən son versiyanı qaytarır və arxa planda köhnə versiyası olan node-lara yeni versiyanı göndərir. Bu **online (foreground)** sinxronizasiyadır. Tamamlayıcı mexanizm **anti-entropy** — Merkle tree ilə replica-lar periodik olaraq müqayisə olunur və fərqlər bərpa edilir (offline).

### Q: Laravel-də read-after-write problemini necə həll edirsən?
**A:** Əsas alət `'sticky' => true` config-də. Bu, eyni HTTP request daxilində yazı baş verərsə, sonrakı oxuların həmin request üçün leader-dən getməsini təmin edir. Amma bu **request-əsaslıdır** — istifadəçi növbəti səhifəyə keçirsə, yenidən replica-dan oxuyur. Bunu həll etmək üçün: (1) yazıdan sonra cache-ə dərhal yaz (TTL lag pəncərəsindən böyük), (2) kritik sahələrdə `DB::connection()->getPdo()` ilə write PDO-nu məcbur et, (3) user-specific data üçün session-based affinity (eyni user həmişə leader-dən oxusun kritik endpoint-lərdə).

## Praktik Baxış

1. **Default olaraq single-leader seç** — multi-leader yalnız konkret ehtiyac olduqda
2. **Sticky connection aç** (Laravel `sticky => true`) — read-your-writes üçün minimum qoruma
3. **Semi-sync replication** istifadə et — tam async data loss riski yaradır
4. **Replication lag-ı monitor et** — Prometheus/Grafana-da `Seconds_Behind_Master` alert qur
5. **Avtomatik failover** qur — Orchestrator, Patroni, Sentinel — amma split-brain qoruması ilə
6. **Fencing tokens** istifadə et — köhnə leader yazılarını rədd etmək üçün
7. **Cross-region sync replication etmə** — latency öldürücüdür, async + conflict strategy seç
8. **Analytics replica ayır** — OLAP sorğuları OLTP leader-ə toxunmasın
9. **Backup replica-dan götür** — leader-də I/O yükü yaratma
10. **Read replica count-u yükə görə tənzimlə** — hər biri lag riski və operational overhead deməkdir
11. **Kritik oxular üçün leader-dən oxu** — balance, payment status, auth check
12. **Conflict resolution strategy-ni əvvəldən seç** — multi-leader qurursan, LWW/CRDT/custom seçimini kod yazmazdan əvvəl ver
13. **GTID (MySQL) və ya logical replication slots (PostgreSQL)** istifadə et — pozisiya-əsaslı replication-dan etibarlıdır
14. **Disaster recovery test et** — failover drill ildə ən azı 2 dəfə real şəraitdə icra olunmalıdır


## Əlaqəli Mövzular

- [CAP & PACELC](42-cap-pacelc.md) — replication consistency trade-off
- [Consistency Patterns](32-consistency-patterns.md) — replication lag = eventual consistency
- [Data Partitioning](26-data-partitioning.md) — shard-daxili replica
- [Disaster Recovery](30-disaster-recovery.md) — replica üzərindən failover
- [Anti-Entropy](92-anti-entropy-merkle-trees.md) — replica divergence sinxronizasiyası
