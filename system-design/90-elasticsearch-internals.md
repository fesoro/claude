# Elasticsearch Internals (Architect)

Elasticsearch — Apache Lucene üzərində qurulmuş distributed search engine. File 12 search systems ümumi baxışı verir; bu fayl Elasticsearch-in daxili işinə (segment mərhələləri, translog, cluster state, split-brain qoruması, ILM, rolling upgrade) dərindən baxır.


## Niyə Vacibdir

Elasticsearch-i production-da istifadə edən amma Lucene segment-ni, translog-u, ILM-ni bilməyən engineer cluster-i yavaşladaraq nasazlığa uğradır. Shard count seçimi, mapping explosion, hot-warm-cold arxitekturası — real deployment üçün vacib biliklərdir.

## Arxitektura

```
Cluster
 ├── Master-eligible nodes (1-5, quorum)
 │    └── Cluster state: indices, shards, mappings, routing table
 ├── Data nodes (10-1000s)
 │    ├── Primary shards
 │    └── Replica shards
 ├── Coordinating nodes (query routing)
 └── Ingest nodes (pipelines)

Index (logical)  →  N primary shards (physical)
                 →  each with M replicas
                 
Shard            =  Lucene index
                 →  Multiple segments (immutable)
                 →  Each segment: inverted index + doc values + stored fields
```

## Lucene segment

Hər yazma **in-memory buffer**-ə gedir, refresh zamanı **immutable segment**-ə çevrilir.

```
Segment faylları:
  .fdt/.fdx/.fdm  — stored fields (source)
  .tim/.tip       — term dictionary (FST + trie)
  .doc            — postings list (term → doc IDs)
  .pos            — positions (for phrase queries)
  .dvd/.dvm       — doc values (column-oriented)
  .nvd            — norms (length normalization)
  .fnm            — fields metadata
```

**Niyə immutable?**
- Lock-free read (concurrent query)
- File system cache optimal (OS disk cache)
- Compression possible (dictionary frozen)

**Dezavantaj:** update = delete (marker) + insert. Deletion real space return olmur; merge vaxtı silinir.

## Inverted index

```
Documents:
  doc 1: "the quick brown fox"
  doc 2: "the lazy brown dog"
  doc 3: "quick quick fox"

Inverted index (term → posting list):
  brown  → [1, 2]
  dog    → [2]
  fox    → [1, 3]
  lazy   → [2]
  quick  → [1, 3]
  the    → [1, 2]
```

### Term dictionary — FST (Finite State Transducer)

Lucene FST — compressed trie + suffix sharing. Disk-də 10-30% term ölçüsü.

```
Terms: cat, car, cart, cars
       
FST:   c → a → r → 0  ("car")
                 ↘ t → 1  ("cart")
                 ↘ s → 2  ("cars")
             t → 3  ("cat")
```

### Postings list — frame-of-reference

Doc ID-lər delta encoded + variable-byte:

```
Raw:    [1001, 1002, 1005, 1020, 1050]
Delta:  [1001,    1,    3,   15,   30]
VByte:  packed in blocks of 128, bit-packed minimum
```

Milyardlarla posting-i sıxışdırır 2-4x.

## Refresh, Flush, Fsync

### Write path

```
1. Client → coordinating → primary shard (routing by doc ID)
2. Primary parses → in-memory buffer + translog append (fsync optional)
3. Replicate to replicas (parallel)
4. Ack back to client
```

### Refresh (default: 1s)

```
Memory buffer → Lucene segment (still in memory cache, searchable)
Translog tutulur
```

Near-real-time search — 1 saniyə gecikmə ilə sənəd gözə dəyir.

### Flush

```
Segments in memory → fsync to disk
Translog truncated (safe to discard)
```

Default: segments çatanda və ya translog böyüyəndə (512MB).

### Fsync durability

```yaml
index.translog.durability: request  # hər sorğu fsync (slow, safe)
index.translog.durability: async    # hər 5s fsync (fast, 5s loss risk)
```

## Doc values — columnar

Inverted index **term → docs** istiqamətində. Aggregation və sort üçün **doc → value** lazımdır.

```
doc 1: price=100
doc 2: price=250
doc 3: price=50

Inverted (bad for SUM):
  100 → [1]
  250 → [2]
  50  → [3]

Doc values (good for SUM, on-disk columnar):
  [100, 250, 50]
```

Aggregations (SUM, MAX, AVG), sort, script query — doc values istifadə edir.

**Disk overhead:** hər numeric field ~8 bytes/doc. Index double-dur, amma aggregation 100x sürətli.

## Translog (Write-Ahead Log)

```
Request → translog.append(bytes) + in-memory buffer
       → ack only after translog persisted (based on durability)
```

**Crash recovery:**
```
Restart:
  1. Replay last committed segment checkpoint
  2. Replay translog from checkpoint to end
  3. Rebuild in-memory buffer + segments
```

Translog monotonic, compact binary — millions ops/sec.

## Routing — hansı shard-a getmək

```
shard = hash(routing) % num_primary_shards
```

Default routing — `_id`. Custom routing:

```bash
POST /blog/_doc/5?routing=user-42
{
  "user": "user-42",
  "title": "Hello"
}
```

**Niyə custom routing?** User-ə aid bütün post-lar eyni shard-da — tək-shard query, ultra-fast. Amma **data skew** riski — viral user-lər hot shard yarada bilər.

## Shard sayı — necə seçmək

```
Rule of thumb:
  - Hər shard 10-50 GB arası
  - Node başına 20 shard/GB heap (limit)
  - Primary sayını sonradan dəyişə bilməzsən (reindex lazım)

Misal:
  - Index 500 GB artacaq → 20 shards × 25 GB
  - 5 data node → 4 shards/node
```

**Over-sharding** problemi: 100 shard × 100 index = 10k shard. Cluster state böyüyür, master node boğulur.

## Primary / replica sync

### Write

```
Client → primary
       → primary writes to translog + segment
       → replicates to replicas (parallel)
       → wait for wait_for_active_shards (default: 1, can be "all")
       → ack client
```

### Replica sync — sequence number + primary term

```
seq_no = monotonic per-shard counter
primary_term = incremented when primary fails over

Replica tracks its checkpoint.
If replica falls behind → peer recovery (copy missing ops from primary translog).
If primary translog gone → full shard copy (file-based).
```

## Cluster state

Master node-da saxlanan JSON (+in-memory):

```json
{
  "cluster_name": "prod-search",
  "version": 1024,
  "master_node": "node-1",
  "nodes": { ... },
  "routing_table": {
    "indices": {
      "products": {
        "shards": {
          "0": [
            {"primary": true,  "node": "node-1", "state": "STARTED"},
            {"primary": false, "node": "node-2", "state": "STARTED"}
          ]
        }
      }
    }
  },
  "metadata": {
    "indices": { "products": { "settings": {...}, "mappings": {...} } }
  }
}
```

Hər dəyişiklik master → bütün node-lara yayılır (gossip-like).

### Cluster state scalability

Cluster state-in ölçüsü = O(indices × shards × nodes). 10k+ indices + 1000 nodes = GB səviyyə → master slow.

**Həll:**
- Index templates (schema duplicate olmur)
- Frozen tier (cluster state minimal)
- Rollup / ILM (köhnə index-ləri sil)

## Split-brain və quorum

### Problem

```
3 master-eligible node: A, B, C
Network partitions: [A] | [B, C]

A özünü master elan edir (yazır)
B, C özləri arasında master seçir (yazır)
Partition bitir → conflict!
```

### Zen Discovery və sonra cluster coordination (7.x)

**Köhnə (6.x):**
```yaml
discovery.zen.minimum_master_nodes: 2  # (N/2+1)
```

N=3 node üçün minimum 2 — quorum. Partition-da azlıqda qalan master olmağa çalışmır.

**Yeni (7.x+):**
Raft-inspired consensus. Voting config avtomatik idarə olunur. `minimum_master_nodes` settings lazım deyil.

### Node rolləri

```yaml
node.roles: [master, data_hot, ingest, remote_cluster_client]
```

Dedicated master (data olmur) — cluster state idarəçiliyi üçün stabildir.

## Cross-cluster replication (CCR)

Aktiv-passiv disaster recovery:

```
Primary cluster (eu-west-1) → leader index
    ↓ (pull-based replication)
Remote cluster (us-east-1)  → follower index (read-only)
```

DR senari:
1. EU cluster ölsə, US cluster-i promote et (follower → regular index)
2. Traffic keçir

**Trade-off:**
- Paid (Platinum license)
- Eventually consistent — seconds lag
- Write yalnız leader-də

## ILM (Index Lifecycle Management)

File 89 (hot/cold tiering) detallı izah edir. ES-specific:

```
hot → warm → cold → frozen → delete

Phase actions:
  rollover       — yeni index yarat köhnə çox böyüyəndə
  shrink         — shard sayını azalt (post-rollover)
  forcemerge     — segment birləşdir (refresh interval sonsuz qoy)
  allocate       — fərqli node set-ə köçür
  freeze         — minimal memory footprint
  searchable_snapshot — S3-ə snapshot
  delete         — təmizlə
```

### Rollover nümunəsi

```
Alias: logs-write
Index: logs-000001 (hot, accepts writes)

Condition: max_age: 1d OR max_size: 50gb
  → rollover triggered

New:
  logs-write alias → logs-000002
  logs-000001 move to warm
```

Reverse pointer (read alias) bütün tarix index-lərinə:

```
logs-read → logs-*
```

## Rolling upgrade

### Zero-downtime upgrade strategiyası

```
1. Disable shard allocation:
   PUT /_cluster/settings
   { "persistent": { "cluster.routing.allocation.enable": "primaries" } }

2. Sync flushed indices (fast recovery):
   POST /_flush/synced

3. Stop node-1:
   systemctl stop elasticsearch

4. Upgrade binary:
   apt upgrade elasticsearch

5. Start node-1:
   systemctl start elasticsearch

6. Wait for yellow/green

7. Re-enable allocation:
   PUT /_cluster/settings
   { "persistent": { "cluster.routing.allocation.enable": null } }

8. Repeat for node-2, node-3 ...
```

**Version compatibility:**
- Same major: 7.10 → 7.17 rolling OK
- Major: 7.x → 8.x requires specific path, downtime possible

## Hot-warm-cold arxitekturası

```
Hot nodes:
  - NVMe SSD
  - High CPU
  - Receives writes, latest searches
  - 1-7 days data

Warm nodes:
  - SATA SSD / HDD
  - Lower CPU
  - Read-only, occasional search
  - 7-30 days

Cold nodes:
  - HDD, less RAM
  - Searchable snapshots (S3-backed)
  - 30-365 days

Frozen nodes (8.x):
  - Full searchable snapshot mode
  - Index data lives entirely on S3
  - 1+ year, rarely queried
```

Node labels:
```yaml
node.attr.data: hot
# or warm, cold, frozen
```

Index allocation awareness:
```json
{
  "index.routing.allocation.include.data": "warm"
}
```

## Query execution

### Query phase

```
1. Client → coordinator node
2. Coordinator sends query to ONE copy of each shard (primary or replica)
3. Each shard executes locally, returns top-K doc IDs + scores
4. Coordinator merges, selects global top-K
```

### Fetch phase

```
5. Coordinator asks shard for full documents (for top-K only)
6. Returns to client
```

### Deep pagination problem

`from=10000, size=10` — her shard 10010 sənəd yığır, N shard = N × 10010 nəticə coordinator-da. Memory explosion.

**Həll:**
- `search_after` (cursor-based, preferred)
- `scroll` (snapshotted, batch export)
- `point-in-time` + `search_after` (7.10+)

## Aggregations

```json
{
  "aggs": {
    "by_category": {
      "terms": { "field": "category", "size": 10 },
      "aggs": {
        "avg_price": { "avg": { "field": "price" } }
      }
    }
  }
}
```

Yürüdülür:
1. Hər shard local aggregation — top 10 × 2 = top 20 qaytarır (shard_size)
2. Coordinator global merge, final top 10

**Cardinality aggregation** — HLL (HyperLogLog) əsaslı. Dəqiq deyil amma böyük cardinality üçün memory-safe.

## Scoring — BM25

Default similarity funksiyası:

```
score(q, d) = Σ IDF(q) × (tf(q,d) × (k1+1)) / (tf(q,d) + k1 × (1-b+b × dl/avgdl))

IDF  — inverse doc frequency (rare term more valuable)
tf   — term frequency in doc
dl   — document length
avgdl— avg doc length in shard
k1,b — tuning (default 1.2, 0.75)
```

Custom scoring:
- `function_score` — multiply/boost with field value
- `script_score` — Painless/Lucene Expressions
- `rank_feature` — static boosts (page rank, popularity)

## Real-world istifadə

### Netflix
- 500+ cluster
- ILM hot-warm-cold
- CCR for multi-region search

### GitHub code search
- Custom Lucene extensions
- Tokenizer for programming languages
- Grammar-aware parsing
- 2023-də hibrid Blackbird sisteminə keçdi (Lucene + Rust)

### Uber
- Geo queries (geo_shape, geohash_grid)
- 100+ PB logs in ELK
- Tiered storage (hot 7d, warm 30d, cold S3 snapshots)

### Shopify
- Product search, BM25 + neural rerank
- CCR across regions
- 10k+ shards per cluster

## Common pitfalls

1. **Mapping explosion** — dinamik mapping → 10k field → memory OOM. Use `strict` mapping.
2. **Text vs keyword confusion** — aggregation text field üzrə → "fielddata" memory bomb
3. **Default refresh 1s production yük** — bulk ingest vaxtı refresh kapat (-1), sonra aç
4. **Too many shards** — 1000+ kiçik index → cluster state blow-up
5. **Forgetting translog** — write-heavy workload-da fsync settings yoxlamaq
6. **Deep pagination** — from/size > 10000 default-ən bloklanır (index.max_result_window)
7. **No retention policy** — disk dolana qədər gözləmək

## Monitoring

Key metrics:
- `indices.indexing.index_time_in_millis` — write latency
- `indices.search.query_time_in_millis` — query latency
- `indices.refresh.total_time_in_millis` — refresh pressure
- `indices.merges.current_docs` — merge backlog
- `jvm.gc.collectors.old.collection_time_in_millis` — GC pressure
- `cluster_health.unassigned_shards` — under-replication
- `thread_pool.*.rejected` — overload

## Back-of-envelope

**Log ingestion cluster:**
- 100 TB/day, replication=1
- Hot tier — 7 days × 100 TB × 2 (replica) = 1.4 PB
- i3.xlarge (1 TB NVMe each) → 1400 nodes just for hot
- With compression (LZ4 3x) → 470 nodes

**Search cluster (products):**
- 100M products, 5 KB/doc indexed = 500 GB
- Replication 2 → 1.5 TB
- 5 shards × 100 GB each, 6 nodes (2 zones × 3)
- 10k QPS search → ~500 QPS/shard, OK on modern hardware

## Ətraflı Qeydlər

Elasticsearch scale etmək — Lucene segment cycle, shard placement və cluster state-i başa düşmək deməkdir. Ən tez-tez qarşılaşılan problemlər: yanlış shard sayı, mapping explosion, deep pagination və inadequate retention. Hot-warm-cold ILM cost-u dramatik azaldır. 8.x-dən başlayaraq frozen tier searchable snapshots S3-də saxlanan sense etdirir — dəqiqliklə latency trade-off. Modern replacement-lər (OpenSearch, Quickwit, Loki) niche-lərdə rəqabət aparır, amma general-purpose search üçün ES hələ də dominant platformadır.


## Əlaqəli Mövzular

- [Search Systems](12-search-systems.md) — Elasticsearch-in istifadə yeri
- [Document Search](76-document-search-design.md) — Algolia vs Elasticsearch
- [Hot/Cold Storage Tiering](89-hot-cold-storage-tiering.md) — ILM lifecycle
- [Metrics System](53-metrics-monitoring-design.md) — ELK monitoring stack
- [Time-Series DB](66-time-series-database.md) — Elasticsearch time-series use-case
