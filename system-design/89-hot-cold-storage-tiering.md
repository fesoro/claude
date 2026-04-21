# 89. Hot / Warm / Cold Storage Tiering

Bütün məlumat eyni dərəcədə dəyərli deyil və eyni tezlikdə oxunmur. **Tiered storage** — access pattern-ə əsaslanaraq məlumatı fərqli qiymət/performans səviyyələrinə yerləşdirir. Bu fayl hot/warm/cold/frozen yerləşdirmələrini, S3 lifecycle policy-lərini, Cassandra/Elasticsearch tiered compaction-ı və cost optimization texnikalarını araşdırır.

## Niyə tiering lazımdır?

Adi sistem:
- Bütün məlumat NVMe SSD-də
- 1 TB SSD = $100/ay
- 100 TB log = $10,000/ay sadəcə storage

Access pattern həqiqəti:
- **Son 1 gün** — 90% query-lər
- **Son 7 gün** — 98% query-lər
- **Son 30 gün** — 99.5% query-lər
- **Son 1 il** — 99.9%
- **Qalan** — compliance üçün saxlanır, nadirən oxunur

Beləliklə, 1 TB isti məlumat NVMe-də, 99 TB soyuq məlumat S3 Glacier-də = $600/ay (16x ucuz).

## Tier tərifi

| Tier | Latency | IOPS | Cost/GB/month | İstifadə |
|------|---------|------|---------------|----------|
| **Hot** | <1ms | 100k+ | $0.10-0.25 (NVMe) | Son data, active queries |
| **Warm** | 10-50ms | 1k-10k | $0.05 (HDD / SATA SSD) | Recent, occasional query |
| **Cold** | 100-500ms | <1k | $0.023 (S3 Standard) | Archive, rare query |
| **Frozen** | minutes-hours | N/A | $0.004 (S3 Glacier Deep) | Compliance, disaster |

**Qiymətlər təqribidir** (AWS 2024).

## Access pattern analizi

### Tier seçimi üçün suallar

1. **Son giriş** — "last-accessed-at" nə zaman?
2. **Read frequency** — saniyədə, gündə, ayda?
3. **Latency SLA** — 10ms-ə cavab lazımdırmı?
4. **Retrieval cost** — frequent restore baha ola bilər (Glacier egress)
5. **Lifespan** — GDPR 7 il saxla, sonra sil

### Qaydalar (rule of thumb)

```
IF last_access < 7 days  →  HOT
IF last_access < 90 days →  WARM
IF last_access < 1 year  →  COLD
ELSE                     →  FROZEN (or delete)
```

Dinamik: Netflix'in "90 gün baxılmamış" shows daha ucuz CDN tier-inə köçürülür.

## S3 storage classes

```
S3 Standard        — $0.023/GB  — millisecond latency
S3 Intelligent-Tier — auto-move  — fee +$0.0025/1000 objects
S3 Standard-IA     — $0.0125/GB — min 30 days, retrieval $0.01/GB
S3 One Zone-IA     — $0.01/GB   — single AZ (non-critical)
S3 Glacier Instant — $0.004/GB  — ms retrieval, min 90 days
S3 Glacier Flexible — $0.0036/GB — minutes to hours
S3 Glacier Deep    — $0.00099/GB — 12 hour retrieval, min 180 days
```

### S3 Lifecycle policy

```json
{
  "Rules": [
    {
      "Id": "LogRetention",
      "Status": "Enabled",
      "Filter": { "Prefix": "logs/" },
      "Transitions": [
        { "Days": 30,  "StorageClass": "STANDARD_IA" },
        { "Days": 90,  "StorageClass": "GLACIER" },
        { "Days": 365, "StorageClass": "DEEP_ARCHIVE" }
      ],
      "Expiration": { "Days": 2555 }
    }
  ]
}
```

2555 gün = 7 il (GDPR/HIPAA limit).

### Intelligent-Tiering

AWS avtomatik:
- 30 gün toxunulmasa → Infrequent Access
- 90 gün → Archive Instant
- 180 gün → Archive Access

**Trade-off:** fee (~$0.0025 per 1000 objects) amma manual policy lazım deyil. Kiçik obyektlər üçün (<128KB) fee > tier saving.

## Cassandra Tiered Compaction (TWCS)

### Problem

Cassandra LSM tree-dir — hər yazı SSTable-a commit olur, sonra kompakt edilir. **STCS** (Size-Tiered) oxşar ölçüdəki SSTable-ları birləşdirir. Time-series data üçün pis:

```
STCS:
  2020 data mixes with 2024 data in same SSTable
  → hər query bütün SSTable-ları oxumalı
```

### TWCS (Time Window Compaction Strategy)

```
Hər vaxt pəncərəsi (1 gün) öz bucket-ində:

Bucket[2024-01-01]: sstable-1, sstable-2 → compact → daily.sstable
Bucket[2024-01-02]: sstable-3, sstable-4 → compact → daily.sstable
...
```

Köhnə bucket-lar yenidən birləşdirilmir (append-only). TTL expiry sadədir — köhnə bucket sil.

```yaml
compaction:
  class: TimeWindowCompactionStrategy
  compaction_window_unit: DAYS
  compaction_window_size: 1
```

**Use case:** IoT sensors, logs, metrics — time-series where old data never updates.

## Elasticsearch ILM (Index Lifecycle Management)

### Hot-warm-cold arxitekturası

```
[hot nodes]         — NVMe, indexing + recent queries
[warm nodes]        — HDD, read-only old indices
[cold nodes]        — SATA HDD, archive + snapshot
[frozen nodes]      — S3-backed searchable snapshots
```

### ILM policy

```json
{
  "policy": {
    "phases": {
      "hot": {
        "min_age": "0ms",
        "actions": {
          "rollover": { "max_age": "1d", "max_size": "50gb" }
        }
      },
      "warm": {
        "min_age": "7d",
        "actions": {
          "shrink": { "number_of_shards": 1 },
          "forcemerge": { "max_num_segments": 1 },
          "allocate": { "include": { "data": "warm" } }
        }
      },
      "cold": {
        "min_age": "30d",
        "actions": {
          "searchable_snapshot": { "snapshot_repository": "s3_repo" },
          "allocate": { "include": { "data": "cold" } }
        }
      },
      "frozen": {
        "min_age": "90d",
        "actions": {
          "searchable_snapshot": { "snapshot_repository": "s3_repo" }
        }
      },
      "delete": {
        "min_age": "365d",
        "actions": { "delete": {} }
      }
    }
  }
}
```

### Searchable snapshots

Frozen tier — index-in heç bir byte-ı node-un local disk-ində yoxdur, birbaşa S3-dən streaming query edilir. Latency 500ms-5s, amma storage cost 50x aşağı.

## ClickHouse TTL

```sql
CREATE TABLE metrics (
    timestamp DateTime,
    metric    String,
    value     Float64
)
ENGINE = MergeTree
ORDER BY (metric, timestamp)
TTL timestamp + INTERVAL 7 DAY TO VOLUME 'warm',
    timestamp + INTERVAL 30 DAY TO VOLUME 'cold',
    timestamp + INTERVAL 90 DAY DELETE;
```

**Storage policy:**
```xml
<storage_configuration>
    <disks>
        <hot>  <path>/mnt/nvme/</path>  </hot>
        <warm> <path>/mnt/hdd/</path>   </warm>
        <cold> <type>s3</type> <endpoint>https://s3...</endpoint> </cold>
    </disks>
    <policies>
        <tiered>
            <volumes>
                <hot>  <disk>hot</disk>  </hot>
                <warm> <disk>warm</disk> </warm>
                <cold> <disk>cold</disk> </cold>
            </volumes>
        </tiered>
    </policies>
</storage_configuration>
```

## Downsampling

Köhnə məlumatın **dəqiqliyini** azaltmaqla ölçüsü dramatik azalır:

```
Son 1 gün:  1-saniyəlik granularity  (86400 points/metric)
Son 7 gün:  1-dəqiqəlik avg         (10080 points)
Son 30 gün: 5-dəqiqəlik avg         (8640 points)
Son 1 il:   1-saatlıq avg           (8760 points)
```

Prometheus-da `recording rules`:

```yaml
groups:
  - name: downsample_1h
    interval: 1h
    rules:
      - record: job:http_requests:rate5m_1h
        expr: avg_over_time(http_requests:rate5m[1h])
```

Thanos/Cortex/Mimir bunu avtomatik edir — raw data local, 5m/1h downsampled S3-də.

## Compaction və cost

### Storage cost nizamlanması

```
Before compaction:
  1000 segments × 100 MB = 100 GB
  + metadata overhead = 115 GB

After merge to 10 large segments:
  10 × 10 GB = 100 GB
  + metadata = 101 GB
  (14% saving + faster queries)
```

Trade-off: compaction CPU/IO bahasıdır. Off-peak-də planlaşdır.

## Log retention strategiyası

### Nümunə — application logs

```
Tier 1 — Real-time (last 24h):
  Elasticsearch hot cluster
  Full text search
  Cost: $500/day

Tier 2 — Recent (1-30 days):
  Loki + S3 Standard
  LogQL queries, 10s latency
  Cost: $50/day

Tier 3 — Archive (30-365 days):
  S3 Glacier Instant
  On-demand restore, 1-5 min
  Cost: $5/day

Tier 4 — Compliance (1-7 years):
  S3 Glacier Deep Archive
  12-hour restore
  Cost: $0.50/day
```

100 TB log üzrə illik cost:
- All-hot: $25,000
- Tiered: $4,500 (82% save)

### Log sampling

Hot tier-ə yazmadan əvvəl sampling:

```
INFO logs   → 1%   (99% drop)
WARN logs   → 100%
ERROR logs  → 100%
DEBUG logs  → 0.1% (at scale)
```

Sampling decision adaptive: error rate yüksəlirsə, o endpoint üçün sampling 100%-ə qalxır.

## Real-world nümunələr

### Netflix — viewing history

```
Hot  (0-30 days):   Cassandra cluster, read-heavy, user can see
Warm (30-365 days): Cassandra compacted, less replication
Cold (1-3 years):   S3 Parquet, batch analytics only
Frozen (3+ years):  Glacier, compliance
```

### Uber — trip data

```
Hot:  Postgres sharded — active trips + last 90 days (driver/rider app)
Warm: Cassandra — historical trips (support tickets)
Cold: S3 Parquet — analytics, ML training
Delete after 7 years (regulation)
```

### Instagram — photo storage

```
Hot:   Cassandra + Haystack — last 30 days, all CDN-cached
Warm:  Haystack — older photos, direct origin fetch
Cold:  Glacier — deleted user data (30-day grace period)
       then hard delete
```

### Dropbox — Magic Pocket

- **Extant tier** — SSD, active sync
- **Cold tier** — SMR HDD (Shingled Magnetic Recording), read-heavy archive
- **Freeze tier** — tape library (LTO) for disaster + compliance

Cost difference: SSD $100/TB vs tape $10/TB.

## Glacier restore latency və cost

### Restore tiers

| Tier | Latency | Cost per 1000 requests | Cost per GB |
|------|---------|------------------------|-------------|
| Expedited | 1-5 min | $10 | $0.03 |
| Standard | 3-5 hours | $0.05 | $0.01 |
| Bulk | 5-12 hours | $0.025 | $0.0025 |

**Gotcha:** retrieval kumulyativ account-level quota-ya tabe. Çox file bir anda restore etsən rate-limit ediləcəksən.

**Best practice:** plan restores in advance. Compliance audit "give us all 2019 data in 72 hours" → use bulk tier overnight.

## PHP/Laravel lifecycle implementation

### Application-level tiering

```php
// app/Services/AttachmentArchiver.php
class AttachmentArchiver
{
    public function archiveOld(): void
    {
        Attachment::query()
            ->where('last_accessed_at', '<', now()->subDays(90))
            ->where('tier', 'hot')
            ->chunk(500, function ($chunks) {
                foreach ($chunks as $att) {
                    // Move from hot S3 to Glacier
                    Storage::disk('s3')->copy(
                        $att->path,
                        'glacier/' . $att->path
                    );

                    // Set storage class on copy
                    Storage::disk('s3-glacier')->put(
                        $att->path,
                        Storage::disk('s3')->get($att->path),
                        ['StorageClass' => 'GLACIER']
                    );

                    $att->update(['tier' => 'cold', 'tier_changed_at' => now()]);
                }
            });
    }
}
```

### Restore request

```php
public function restore(Attachment $att): string
{
    $client = app('aws.s3');
    $client->restoreObject([
        'Bucket' => 'my-bucket',
        'Key' => $att->path,
        'RestoreRequest' => [
            'Days' => 7,
            'GlacierJobParameters' => [
                'Tier' => 'Standard',
            ],
        ],
    ]);

    $att->update(['restore_requested_at' => now()]);

    // Email user when ready
    dispatch(new NotifyWhenRestored($att))->delay(now()->addHours(5));

    return 'Restore started; available in 3-5 hours';
}
```

## Back-of-envelope

**SaaS log platform:**
- 100 TB/day ingested
- 30 day hot, 90 day warm, 7 year compliance
- Hot (3 PB total after 30 days × 100): S3 Standard = $70k/month
- After lifecycle:
  - Hot (30 days): 3 PB × $0.023 = $70k
  - Warm (60 days): 6 PB × $0.0125 = $75k
  - Cold (6.5 years): 237 PB × $0.00099 = $235k

Annual: ($70k + $75k + $235k) × 12 = $4.56M — still significant, but without tiering would be $130M+.

## Anti-patterns

1. **Tier-ing active data** — 10ms SLA varsa cold tier fatal
2. **Frequent Glacier restore** — restoration cost >= storage savings
3. **No monitoring of tier transitions** — S3 versioning bug → 10x storage
4. **Aggressive delete** — regulatory compliance violation
5. **Uniform TTL** — hər dataset-in öz access pattern-i var, tək size fits all olmaz
6. **Storage class fee ignored** — Intelligent-Tiering kiçik fayllar üçün zərər
7. **No index on tier column** — migration query table scan olur

## Monitoring tier health

Metrikalar:
- `storage_bytes_by_tier{tier="hot|warm|cold|frozen"}`
- `tier_transitions_per_day{from,to}`
- `glacier_restore_requests_per_day`
- `glacier_restore_wait_seconds_p99`
- `cost_per_gb_blended` (weighted average)

Alert:
- Hot tier growth > warm tier → transition lag
- Restore requests > baseline 2x → investigate (attack? bug?)

## Best practices

1. **Access pattern ölç** — assumption əvəzinə heatmap qur
2. **Lifecycle policy declarative** — Terraform / CloudFormation
3. **Test restore process** — quarterly disaster recovery drill
4. **Compliance requirements** early planning — legal hold mexanizmi
5. **Cost-aware queries** — BigQuery/Athena "you will scan 10 TB = $50" dialog
6. **Versioning + lifecycle together** — köhnə versiyaları ayrıca tier-lə
7. **Delete marker ilə expiration** — permanent delete vs soft delete

## Yekun

Storage tiering modern data platforms-in əsasıdır. 10x-100x cost saving mümkündür — amma access pattern yanlış başa düşülərsə, restore latency və cost SLA-nı öldürə bilər. Ən vacib addım: əvvəl **ölç** (last-accessed-at, query frequency), sonra policy yaz. S3 Intelligent-Tiering və ClickHouse/Elasticsearch ILM son illərdə əməliyyat yükünü əhəmiyyətli dərəcədə azaldıb, amma hər storage profili üçün özünəməxsus tuning lazımdır.
