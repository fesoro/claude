# Anti-Entropy & Merkle Trees (Architect)

Distributed store-larda replica-lar zamanla divergence edir — yaddan çıxan yazı, partition, crash. **Anti-entropy** — replica-lar arasında fərqləri aşkarlayıb düzəltmə mexanizmidir. **Merkle tree** isə bu fərqləri O(log n) məlumat mübadiləsi ilə tapmaq üçün ağıllı data struktur-dur. Dynamo, Cassandra, Riak, DynamoDB hamısı bu konseptləri istifadə edir.


## Niyə Vacibdir

Replica divergence-i aşkar etmək üçün Merkle tree hash comparison O(log n) müqayisə ilə fərqləri tapır. Hinted handoff, read repair, AAE — Cassandra/DynamoDB-nin eventual consistency-ni necə maintain etdiyi bu mexanizmlər üzərindədir. Distributed storage sistemini düzgün istifadə etmək üçün vacibdir.

## Problem — replica divergence

### Nə divergence yaradır?

```
1. Temporary failure:
   Write -> node-1 (ok), node-2 (down)
   node-2 bərpa olunanda yazı yoxdur

2. Network partition:
   Client → node-A (keeps write)
   Replica-replica sync blocked

3. Concurrent write without coordination:
   Dynamo-style, client multiple paths
   LWW resolved differently on replicas

4. Bit rot / disk corruption:
   Silent data error
```

### Niyə mənim quorum write-im kifayət etməz?

Quorum (`W + R > N`) read-də düzgün son dəyəri təmin edir — amma çaşqın replika *həmin an* düzələ bilməz. Anti-entropy həmin background proses-dir ki, bütün replika-ları eventually identical edir.

## Mexanizmlər — üç katman

```
1. Hinted Handoff      — yazı failure zamanı
2. Read Repair         — oxu zamanı aşkar olunan fərq
3. Active Anti-Entropy — periodic background sync (Merkle tree)
```

Üçü birlikdə — bir-birini tamamlayır. Heç biri tək başına kifayət deyil.

## 1. Hinted Handoff

**Cassandra / Dynamo pattern:**

```
Client write (RF=3) → coordinator
  coordinator fan-out to replica-A, B, C

B is down. Coordinator:
  - Writes to A, C (quorum satisfied)
  - Stores a HINT: "write (key=K, value=V) destined for B"
  - Hints kept locally with TTL (3 hours default)

When B comes back:
  - A sends stored hints to B (replay)
  - B catches up
```

### Trade-off
- **+** Fast recovery for short outages
- **+** Reduces read-repair cost
- **−** Limited TTL (>3h outage → hints expired, miss)
- **−** Hint storage overhead on coordinators
- **−** Not sufficient for long-term consistency

### Hint limit

Hints overwhelm edə bilər (node down uzun müddət). Cassandra:
```yaml
hinted_handoff_enabled: true
max_hint_window_in_ms: 10800000  # 3 hours
max_hints_delivery_threads: 2
```

> 3 saatdan çox down — hint-lər silinir, full repair lazımdır.

## 2. Read Repair

```
Client read (R=2 of N=3):
  → coordinator reads from replica-A, replica-B
  → A returns: (key=K, value=V1, timestamp=100)
  → B returns: (key=K, value=V2, timestamp=120)
  
Coordinator:
  1. Returns V2 to client (latest timestamp)
  2. Background: write V2 to replica-A (repair)
```

### Variants

**Blocking read repair:**
- Client waits for repair confirmation
- Higher latency, stronger guarantee

**Asynchronous read repair:**
- Return to client immediately
- Fire-and-forget write to stale replica
- Default in Cassandra

**Read repair chance:**
```yaml
read_repair_chance: 0.1  # 10% of reads trigger background repair
                         # across ALL replicas, not just queried
```

### Limitations

- Yalnız **oxunan** key-lər düzəlir
- Nadir oxunan dəyərlər uzun müddət divergent qala bilər
- Yüksək read amplification (cold data scan yaratmaq lazımdır)

## 3. Active Anti-Entropy (Merkle Tree)

Periodic, bütün dataset üzrə replica-lar arası tam sync.

### Naive yanaşma

```
Node-A: hash(all_data_A)
Node-B: hash(all_data_B)
if hash_A == hash_B: OK
else: transfer ALL data → compare → patch
```

**Problem:** TB məlumat üçün full transfer imkansız.

### Merkle tree yanaşması

Dataset-i tree-dakı yarpaqlara böl. Hər daxili node uşaqlarının hash-i.

```
                   root_hash
                   /        \
              h12             h34
             /    \          /    \
            h1    h2        h3    h4
           / \   / \        / \   / \
          k1 k2 k3 k4     k5 k6 k7 k8   ← key ranges
```

### Fərq axtarışı (range comparison)

```
1. Compare root hash
   Node-A: root=X
   Node-B: root=Y
   X != Y → fərq var

2. Compare children
   Node-A: h12=A1, h34=A2
   Node-B: h12=A1, h34=B2
   → fərq h34 subtree-də

3. Recurse down h34
   h3 == h3
   h4 == B4 (fərqli)
   → fərq h4-də (k7, k8 arasında)

4. Detail level — actual keys
   Exchange k7, k8 values
   Determine which side wins (timestamp / vector clock)
   Apply patch
```

**Network cost:** O(log N) hash exchange + actual differing keys. Milyon key-də ~20 hash compare.

### Merkle hash construction

```
def build_merkle(sorted_kv_pairs, branching=16):
    leaves = []
    for chunk in chunks(sorted_kv_pairs, BUCKET_SIZE=4096):
        leaves.append(hash(chunk))
    
    level = leaves
    while len(level) > 1:
        parent = []
        for group in chunks(level, branching):
            parent.append(hash(concat(group)))
        level = parent
    
    return level[0]  # root
```

**Tree depth:** `log_b(N / bucket_size)`. N=10M, bucket=4K, branching=16 → 3 səviyyə.

### Cassandra nodetool repair

```bash
# Full repair across all keyspaces
nodetool repair

# Repair specific keyspace
nodetool repair -pr keyspace_name  # primary range only

# Incremental repair (3.0+)
nodetool repair -inc
```

**Daxilində:**
1. Hər node öz primary range üçün Merkle tree qurur
2. Replica-lar tree-ləri mübadilə edir
3. Fərqli branch-lar detail-də müqayisə
4. Streaming transfer of out-of-sync data

**Cost:**
- CPU-heavy (hash hesablama + disk scan)
- Network (streaming differences)
- Anti-pattern: `nodetool repair` bütün cluster-də eyni vaxtda — production-u boğur

### Repair scheduling

Cassandra Reaper (third-party tool):
- Avto repair schedule (hər 7 gün)
- Segment-based (bir anda small range)
- Pause / resume
- Grafana metrics

## DynamoDB — Amazon'un yanaşması

Dynamo paper (2007) Merkle tree anti-entropy-i populyarlaşdırdı:

```
Every replica maintains a Merkle tree per partition.
Periodic gossip-based comparison with peer.
Detected diff → exchange values.
Vector clock decides winner (or app-level reconciliation).
```

**DynamoDB (managed):** anti-entropy detallar gizli, amma presentation-lardan bilirik:
- Continuous background repair
- No manual repair needed
- SLA-lı consistency guarantee

## Riak

Active Anti-Entropy (AAE) — Riak 1.3+ built-in:
- Hər vnode öz Merkle tree
- Background exchange with replica vnode-lar
- ETS/LevelDB-də sürətli lookup

```erlang
riak-admin aae-status
```

Detects:
- Missing objects (delivery failure)
- Divergent values (conflict)
- Corruption (hash mismatch)

## Merkle tree vs hash table compare

```
Hash table approach (naive):
  For each key, exchange (key, hash(value))
  → O(N) network

Merkle tree:
  Top-down, prune identical subtrees
  → O(D + log N) where D = differing keys
```

Milyon key-dən 100 fərqli: 1M vs 100 + 20 = 120 message.

## Git — Merkle DAG (contrast)

Git hər commit-i Merkle DAG node kimi saxlayır:

```
Commit = hash(tree_hash + parent_hashes + metadata)
Tree   = hash(list of (mode, name, blob_hash))
Blob   = hash(file_content)
```

**Eynilik:** content-addressable, hash-based identity, deduplication.

**Fərq:**
- Git DAG (commit-lər arası parent pointers), Cassandra/Dynamo — balanced tree for range comparison
- Git user-initiated sync (`git fetch`), Dynamo continuous background
- Git immutable history, Dynamo mutable key-value

Git `git fetch` essentially Merkle walk:
```
Client:    "I have commit X"
Server:    "I have Y (ancestor of X? no, divergent)"
Client:    "Send me Y's tree"
           (recursive tree walk, skip identical subtrees)
Server:    "Blob A, B, C new"
```

Ayrı bir pack file-da differ göndərir.

## rsync — rolling hash (brief)

Fayl sync alqoritmi. Blok-səviyyədə Merkle deyil amma oxşar ideya:

```
1. Receiver: dosyayı fixed-size blok-lara böl.
   Her blok üçün weak rolling hash (adler32) + strong hash (MD5).
2. Send hash list to sender.
3. Sender: rolling hash ilə scan et bütün pozisyonlarda
   (1 byte shift at a time — adler32 update O(1) ilə).
4. Match olarsa, block reference. Match yoxsa, literal bytes.
5. Receiver: reconstruct from references + literals.
```

**Ortaq ideya:** hash-based diff, amma rsync kiçik faylın içindəki dəyişikliklər üçün; Merkle tree böyük key-value set-in arasındakı fərqlər üçün.

## Trade-off table

| Mexanizm | Latency impact | Completeness | Network cost | Ne zaman |
|----------|----------------|--------------|--------------|----------|
| Hinted handoff | None (async) | Short outages | Low | Temporary failure |
| Read repair | Tiny on read | Only read keys | Per-read | On-the-fly |
| Active AAE | Background CPU/IO | Full dataset | O(log N) + diffs | Periodic (hourly/daily) |
| Full repair | High | Full | O(N) | Disaster recovery, fresh node |

Production guideline: all three enabled.

## PHP/Laravel pseudo-implementation (örnək)

Gerçek production-da Cassandra/Riak istifadə olunur, amma pedagogical nümunə:

```php
class MerkleNode
{
    public string $hash;
    public ?MerkleNode $left = null;
    public ?MerkleNode $right = null;
    public array $keys = [];
}

class MerkleTree
{
    public static function build(array $sortedKV, int $bucketSize = 4096): MerkleNode
    {
        // Leaf bucket formation
        $leaves = [];
        foreach (array_chunk($sortedKV, $bucketSize, true) as $chunk) {
            $node = new MerkleNode();
            $node->keys = array_keys($chunk);
            $node->hash = hash('sha256', json_encode($chunk));
            $leaves[] = $node;
        }

        // Build up
        while (count($leaves) > 1) {
            $level = [];
            for ($i = 0; $i < count($leaves); $i += 2) {
                $parent = new MerkleNode();
                $parent->left = $leaves[$i];
                $parent->right = $leaves[$i + 1] ?? null;
                $parent->hash = hash('sha256',
                    $parent->left->hash . ($parent->right?->hash ?? ''));
                $level[] = $parent;
            }
            $leaves = $level;
        }

        return $leaves[0];
    }

    public static function diff(MerkleNode $a, MerkleNode $b): array
    {
        if ($a->hash === $b->hash) return [];
        if (empty($a->left) && empty($b->left)) {
            // Leaf level — return keys for detailed compare
            return array_merge($a->keys, $b->keys);
        }
        return array_merge(
            $a->left && $b->left ? self::diff($a->left, $b->left) : [],
            $a->right && $b->right ? self::diff($a->right, $b->right) : []
        );
    }
}
```

## Back-of-envelope

**Cassandra cluster:**
- 1 TB data per node, 1 KB avg record → 10^9 keys
- Bucket size 4K keys → 250k leaves
- Branching 16 → depth = log_16(250k) ≈ 4.5 → 5 levels
- Merkle tree memory: ~500k nodes × 40 bytes = 20 MB per node

**Sync cost (0.01% divergence):**
- Hash exchange: 20 MB (full tree) vs just top few levels ≈ ~1 MB
- Actual diff: 10^9 × 0.0001 = 100k keys × 1 KB = 100 MB streamed
- vs naive full transfer: 1 TB (10,000x saving)

## Consistency considerations

### Merkle tree stale-ness problem

```
Node-A: writes coming in live.
Node-A builds Merkle tree at T=0.
Node-A sends to node-B at T=10.
During T=0..10, more writes → tree is stale by the time comparison starts.

Solution: snapshot (consistent view) for tree build.
OR: accept eventual — next repair cycle catches up.
```

### Tombstones and Merkle

Deletion = tombstone (not physical remove) — tombstone TTL (`gc_grace_seconds` default 10 days).

**Rule:** repair must complete within `gc_grace_seconds`. Otherwise deleted data resurrects:

```
Node-A: delete key K → tombstone
Node-B: down, never saw delete
Tombstone on A expires after 10 days (GC removes it)
B comes back → K exists on B, not on A
Next repair → A gets K from B (back from the dead!)
```

Critical ops practice: schedule repair < `gc_grace_seconds`.

## Real-world

### Cassandra
- Merkle + nodetool repair + Reaper orchestration
- Default grace: 10 days
- Production: weekly full repair cycle per keyspace

### DynamoDB
- Managed, invisible to user
- Multi-AZ replica sync < 1 sec usual
- "Last write wins" LWW reconciliation

### Riak
- AAE + read repair + hinted handoff
- Sibling explosion if many concurrent writes (client-side reconcile)

### Amazon Aurora
- Not key-value, but: quorum-based log replication (6/6 writes, 4/6 reads)
- No Merkle — log replay from leader
- Different paradigm (replicated log, not replicated state)

## Anti-patterns

1. **No repair schedule** — data drift forever
2. **Repair after gc_grace_seconds** — deleted records resurrect
3. **Full repair across all cluster simultaneously** — kills prod
4. **Ignoring hinted handoff storage overflow** — coordinator disk fills
5. **Merkle tree not incremental** — rebuild from scratch every time (CPU waste)
6. **Relying on read repair alone** — cold data stays divergent

## Ətraflı Qeydlər

Merkle trees anti-entropy-nin əsasıdır — eventual consistency vəd edən sistemləri həqiqətən convergent edən mexanizm. Ən önemli başa düşmək: üç katmanlı strategiya (hinted handoff + read repair + active AAE) üçün də lazımdır, tək başına heç biri kifayət etmir. Modern managed sistem-lər (DynamoDB, Cosmos DB) bu detalları gizlədir, amma on-prem Cassandra/Riak idarəçiliyi üçün `nodetool repair` və `gc_grace_seconds` arasındakı əlaqəni anlamaq kritikdir.


## Əlaqəli Mövzular

- [Database Replication](43-database-replication.md) — replica sinxronizasiyası
- [Distributed Systems](25-distributed-systems.md) — eventual consistency
- [Consistency Patterns](32-consistency-patterns.md) — read repair mexanizmi
- [KV Store](50-key-value-store-design.md) — Dynamo-da anti-entropy
- [Raft/Paxos](84-raft-paxos-consensus.md) — strong vs eventual consistency müqayisəsi
