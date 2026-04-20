# Distributed File System Design (HDFS / GFS)

## Nədir? (What is it?)

Distributed file system minlərlə commodity server üzərində petabyte-larla məlumatı
saxlayan, fault-tolerant, yüksək throughput-lu storage sistemidir. Klassik nümunələr:
Google File System (GFS), Hadoop Distributed File System (HDFS), Ceph, Amazon S3.

Sadə dillə: bir fayl bir diskə sığmayanda, onu kiçik bloklara bölürsən, blokları
minlərlə serverə yayırsan, hər bloku 3 yerə kopyalayırsan ki disk ölsə də məlumat
itməsin. Master server "hansı blok hansı serverdədir" xəritəsini saxlayır.

```
File (1 TB video.mp4)
      │
      ├─ Chunk 1 (128 MB) → DataNode A, B, C  (3 replica)
      ├─ Chunk 2 (128 MB) → DataNode B, D, E
      └─ Chunk 3 (128 MB) → DataNode A, C, F
```

## Tələblər (Requirements)

- İstənilən ölçüdə fayl (GB-dan TB-a), write-once-read-many workload
- Append əməliyyatı (streaming), high aggregate throughput
- Durability 11 nines (99.999999999%)
- Fault tolerance: disk, node, rack səviyyəsində

### Scale estimation

```
1000 DataNode × 10 TB = 10 PB raw, RF=3 → 3.3 PB usable
Chunk 128 MB → 25M chunks; Files ~5M (avg 600 MB)
Master metadata: 25M × 150 B ≈ 3.75 GB RAM
```

## GFS / HDFS Arxitekturası (Architecture)

```
      ┌────────────────────────────┐
      │     Client Application      │
      │  (Spark, Hive, MapReduce)   │
      └──────┬─────────────┬────────┘
             │ 1. Metadata │ 2. Data I/O
             │ (who has X?)│ (direct)
             ▼             │
   ┌──────────────────┐    │
   │ NameNode/Master  │    │
   │ - Namespace      │    │
   │ - File → chunks  │    │
   │ - Chunk → nodes  │    │
   │ - In-memory      │    │
   │ - fsimage +      │    │
   │   editlog (WAL)  │    │
   └────────┬─────────┘    │
            │ heartbeat    │
            │ + block report
            ▼              ▼
   ┌──────────────────────────────┐
   │  DataNodes / ChunkServers    │
   │  ┌────┐  ┌────┐  ┌────┐      │
   │  │DN1 │  │DN2 │  │DN3 │ ...  │
   │  │A,B │  │A,C │  │B,D │      │
   │  └────┘  └────┘  └────┘      │
   └──────────────────────────────┘
```

### Master / NameNode

Master bütün metadata-nı in-memory saxlayır (sürət üçün):

- **Namespace** — fayl yolları, permissions
- **File → Chunks** mapping
- **Chunk → DataNodes** mapping
- **Chunk lease** — hansı replica primary-dir (write coordination)

Persistence: **fsimage** (disk snapshot) + **editlog** (WAL). Periodic checkpoint.

### DataNode / ChunkServer

- Actual data bloklarını diskdə saxlayır
- Hər chunk 64 MB (GFS) və ya 128 MB (HDFS default)
- Client-ə birbaşa stream (master bypass)
- Master-ə **heartbeat** + **block report**

### Niyə böyük chunk (64-128 MB)?

```
Chunk 4 KB: 1 TB file = 250M chunks → master metadata 37 GB (crash)
Chunk 128 MB: 1 TB file = 8000 chunks → metadata 1.2 MB
TCP setup overhead amortize olur, sequential read ideal
Çatışmazlıq: kiçik fayllar hələ də 1 entry tutur (small file problem)
```

## Write Pipeline (GFS)

Control flow və data flow ayrılır:

```
Step 1: Client → Master
  "write file.dat offset X"
  Master → "primary: DN1, replicas: DN2, DN3" (60s lease DN1-ə)

Step 2: Data push (pipeline, closest-first)
  Client ──data──▶ DN2 ──data──▶ DN1 ──data──▶ DN3
  (bütün replicas memory buffer-da, hələ disk-ə yox)

Step 3: Commit
  Client ──"commit"──▶ DN1 (primary)
  DN1 serial order təyin edir (seq=42)
  DN1 ──"apply seq=42"──▶ DN2, DN3
  DN2, DN3 ──"ACK"──▶ DN1 ──"ACK"──▶ Client

Step 4: Fail → client retry from step 1
```

Niyə pipeline (chain)? Client 1× bandwidth istifadə edir (paralel olsa 3× lazım olardı),
network topology optimize olunur.

## Replication və Rack Awareness

```
Rack 1:  DN1 (replica 1), DN2
Rack 2:  DN3 (replica 2), DN4 (replica 3)

HDFS default:
  - Replica 1: client-ə ən yaxın (same rack)
  - Replica 2: fərqli rack
  - Replica 3: same rack as replica 2 (fərqli node)

Səbəb: rack switch fail → hələ 1 replica sağdır
       cross-rack bandwidth bahalı → 2 replica same rack
```

## Durability, Scrubbing, Read Path

- **Checksums**: hər 512 KB block üçün CRC32. Mismatch → başqa replica + re-replicate
- **Periodic scrub**: DN arxa planda bütün chunk-ları yoxlayır (bit rot)
- **Re-replication**: DN heartbeat 10 dəq gəlmir → dead. Master under-replicated
  chunk-ları healthy replica-dan kopyalayır, rack diversity qorunur

```
Read: Client → Master (offset → chunk index → [DN2, DN5, DN7])
      Client cache TTL 1 saat
      Client → closest DN (same rack) birbaşa stream
      Subsequent reads → cache hit (master bypass)
```

## Master Bottleneck və Həllər

### HDFS HA (High Availability)

```
┌────────────┐         ┌────────────┐
│ Active NN  │◀──────▶│ Standby NN │
└─────┬──────┘         └─────┬──────┘
      │                      │
      └──────┐        ┌──────┘
             ▼        ▼
        ┌─────────────────┐
        │   JournalNodes  │ (editlog quorum)
        └─────────────────┘
             ▲
        ┌─────────────────┐
        │    ZooKeeper    │ (failover coord)
        └─────────────────┘
```

Active editlog-u JournalNodes-a yazır, Standby oxuyur. Active fail → ZooKeeper
saniyələr içində Standby-ı Active-ə çevirir.

### HDFS Federation

Namespace partitioning — NN1 `/user`, NN2 `/data`, NN3 `/tmp`. Bütün NN-lər eyni
DataNode pool-undan istifadə edir. Namespace horizontal scale.

### Shadow Master (GFS)

Primary → Shadow read-only replication. Manual failover.

## Ceph — Metadata-free

```
Object "photo123" → hash → CRUSH(hash, cluster_map) → [OSD42, OSD17, OSD88]

CRUSH: deterministic algorithm, hamı eyni nəticəni hesablayır
Cluster map bütün client-lərdə cache olunur
Lookup yox — hesablama. Master scale limiti yoxdur.
```

Üstünlük: SPOF yoxdur, scale. Çatışmazlıq: cluster map yaymaq, topology dəyişəndə
rebalance.

## Consistency

- **GFS**: relaxed. Paralel record append duplicate və padding yarada bilər. Tətbiq
  idempotency + checkpoint tələb edir.
- **HDFS**: single-writer lease (bir fayl bir client). Metadata əməliyyatları
  (create, rename, delete) atomic və linearizable.

## Erasure Coding

```
Replication RF=3: 1 GB → 3 GB (200% overhead), tolerate 2 fail
Reed-Solomon 6+3: 1 GB → 1.5 GB (50% overhead), tolerate 3 fail
Trade-off: reconstruction zamanı yüksək CPU + network
```

Hot data → replication (locality, sadəlik).
Cold data → erasure coding (storage qənaət).

## Rebalancing

Yeni node əlavə və ya fail zamanı balancer daemon cluster utilization hesablayır,
overloaded → underloaded node-a block köçürür (threshold default 10%), throttle ilə
normal traffic-ə mane olmur, rack diversity saxlanılır.

## Small File Problem

HDFS hər fayl ~150 B metadata. 100M kiçik fayl = 15 GB NN heap. Həllər:

- **HAR (Hadoop Archive)** — kiçik fayllar → .har arxiv
- **SequenceFile** — binary key-value, birləşdirmə
- **HBase** — file əvəzinə row store
- **Upstream batching** — upload zamanı birləşdir

## Cloud Equivalents

| Sistem    | Tip         | Consistency       | Use Case                 |
|-----------|-------------|-------------------|--------------------------|
| HDFS      | File        | Strong (metadata) | On-prem Hadoop/Spark     |
| GFS       | File        | Relaxed           | Google internal          |
| Ceph      | Object/File | Strong            | Private cloud, OpenStack |
| S3        | Object      | Strong (2020+)    | Cloud analytics          |
| GCS       | Object      | Strong            | BigQuery, Dataflow       |
| MinIO     | Object (S3) | Strong            | Self-hosted S3           |

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

PHP-də birbaşa HDFS client nadirdir. Tipik yanaşmalar: **S3/MinIO** (object store),
**WebHDFS** REST API.

### Laravel + MinIO (S3-compatible)

```php
// config/filesystems.php
'disks' => [
    'minio' => [
        'driver' => 's3',
        'key' => env('MINIO_ACCESS_KEY'),
        'secret' => env('MINIO_SECRET_KEY'),
        'region' => 'us-east-1',
        'bucket' => env('MINIO_BUCKET', 'analytics'),
        'endpoint' => env('MINIO_ENDPOINT', 'http://minio:9000'),
        'use_path_style_endpoint' => true,
    ],
],
```

```php
class AnalyticsUploader
{
    // Böyük log faylını multipart upload
    public function uploadLargeFile(string $localPath, string $key): void
    {
        $client = Storage::disk('minio')->getClient();

        $uploader = new \Aws\S3\MultipartUploader($client, $localPath, [
            'bucket' => config('filesystems.disks.minio.bucket'),
            'key' => $key,
            'part_size' => 16 * 1024 * 1024, // 16 MB
            'concurrency' => 4,
        ]);

        try {
            $result = $uploader->upload();
            Log::info('Uploaded', ['location' => $result['ObjectURL']]);
        } catch (\Aws\Exception\MultipartUploadException $e) {
            Cache::put("upload:{$key}", $e->getState(), now()->addHours(24));
            throw $e;
        }
    }

    // Stream read — bütün fayl yaddaşa yüklənmir
    public function streamLargeFile(string $key): \Generator
    {
        $stream = Storage::disk('minio')->readStream($key);
        while (!feof($stream)) {
            yield fread($stream, 8192);
        }
        fclose($stream);
    }
}
```

### WebHDFS (HDFS REST gateway)

```php
class WebHdfsClient
{
    public function __construct(
        private string $baseUrl = 'http://namenode:50070/webhdfs/v1',
        private string $user = 'hadoop'
    ) {}

    public function read(string $path): string
    {
        // NameNode redirect qaytarır, data DataNode-dan gəlir
        $redirect = Http::withOptions(['allow_redirects' => false])
            ->get("{$this->baseUrl}{$path}", [
                'op' => 'OPEN',
                'user.name' => $this->user,
            ]);

        return Http::get($redirect->header('Location'))->body();
    }

    public function append(string $path, string $data): void
    {
        // Write-once-read-many — append var, random write yox
        $redirect = Http::withOptions(['allow_redirects' => false])
            ->post("{$this->baseUrl}{$path}", [
                'op' => 'APPEND',
                'user.name' => $this->user,
            ]);

        Http::withBody($data, 'application/octet-stream')
            ->post($redirect->header('Location'));
    }
}
```

## Interview Sualları (Interview Questions)

**S1: Niyə chunk size 64-128 MB, 4 KB yox?**
C: Master metadata in-memory saxlanır, hər entry ~150 B. Kiçik chunk → milyardlarla
entry → master crash. Böyük chunk TCP setup overhead-ini amortize edir və sequential
read üçün idealdır. Çatışmazlıq: kiçik fayllar üçün yaddaş israfı (small file problem).

**S2: NameNode crash olsa nə baş verir?**
C: HA olmadan cluster unavailable. HA ilə: JournalNodes-da editlog quorum saxlanır,
Standby NN həmişə sync-dir. ZooKeeper failover coordinate edir — saniyələr içində
Standby Active olur. Data (DataNode-larda) itmir, yalnız metadata service downtime
var. Shadow master (GFS) read-only failover verir.

**S3: Write pipeline niyə chain (A→B→C), paralel yox?**
C: Client bandwidth məhduddur. Paralel 3 replica-ya yazmaq 3× bandwidth tələb edir —
client bottleneck. Pipeline-da hər node növbətiyə stream edir, client 1× bandwidth
istifadə edir. Network topology optimize olunur (closest-first), overall throughput
artır. Həm də control flow (commit order) data flow-dan ayrılır.

**S4: Replication factor 3 — niyə 2 yox, 5 yox?**
C: RF=2 scrub zamanı fail olanda riskli. RF=3 bir node + bir disk fail tolerance
(11 nines). RF=5 storage cost 400%. RF=3 sənaye sweet spot-dur. Cold data üçün
erasure coding (6+3 = 1.5×) əvəzdir.

**S5: GFS consistency zəifdir, tətbiqlər necə uyğunlaşır?**
C: GFS record append-də duplicate və padding mümkündür. Tətbiq: unique ID əlavə et,
idempotent consumer yaz, checkpoint saxla. HDFS single-writer lease ilə problem
həll edir — fayl açıq olanda yalnız bir writer. Metadata əməliyyatları hər iki
sistemdə strict consistent-dir.

**S6: Ceph GFS-dən nə ilə fərqlənir?**
C: Ceph merkəzi metadata master saxlamır. CRUSH algorithm object → OSD mapping-ini
deterministik hesablayır (cluster map + hash). Üstünlük: master scale limiti yoxdur,
SPOF azalır. Çatışmazlıq: cluster map-i yaymaq lazımdır. GFS/HDFS lookup edir,
Ceph hesablayır.

**S7: Erasure coding nə vaxt yaxşıdır?**
C: Cold data üçün — arxiv, köhnə log, nadir oxunan backup. 3× replication = 200%
overhead, Reed-Solomon 6+3 = 50% (3× qənaət). Çatışmazlıq: read zamanı (xüsusən
reconstruction) yüksək CPU + network. Hot data üçün replication — locality və sadəlik.

**S8: Small file problem nədir?**
C: HDFS hər fayl ~150 B metadata. 100M kiçik fayl = 15 GB NN heap → GC, performance
problemləri. Həllər: HAR arxiv, SequenceFile (binary key-value), HBase (row store),
upstream batching. Ən yaxşı: birinci növbədə kiçik fayl yaratmamaq.

## Real-World Nümunələr

1. **Google GFS / Colossus** — BigTable və MapReduce altında
2. **HDFS** — Yahoo, Facebook, LinkedIn analytics
3. **Ceph** — CERN, Bloomberg, OpenStack
4. **Amazon S3** — Netflix, Airbnb
5. **Facebook Haystack / f4** — photo storage, warm/cold tiers

## Best Practices

1. **Chunk size uyğunlaşdır** — sequential workload 128 MB+, mixed 64 MB
2. **Rack-aware placement** — replica-lar fərqli rack-lərdə
3. **Checksums həmişə aktiv** — silent corruption səssiz itkidir
4. **HA NameNode** — production-da tək NN qəbul edilməz
5. **Erasure coding** — cold data üçün, 50%+ qənaət
6. **Balancer throttle** — rebalancing traffic-ə mane olmasın
7. **Small file qarşısını al** — upstream birləşdir, HAR istifadə et
8. **Monitoring** — missing blocks, under-replicated, NN heap, DN fullness
9. **Backup + DR** — distcp cross-cluster, snapshot
10. **Quota və permissions** — namespace/space quota, HDFS ACL
11. **Hybrid pattern** — S3 + Spark/Trino indi standard, pure on-prem HDFS azalır
