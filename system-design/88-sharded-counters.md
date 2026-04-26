# Sharded Counters & Probabilistic Counting (Lead)

Distributed sistemdə "neçə like?", "neçə view?", "neçə istifadəçi online?" suallarına saniyədə milyonlarla event axınında cavab vermək — single-row `UPDATE counter SET n=n+1` ilə mümkün deyil. Bu fayl hot-row contention problemini, **sharded counter** həllini və approximate counting (HyperLogLog, Morris counter) texnikalarını araşdırır. File 33 ümumi probabilistic DS-ə toxunur — bu fayl counter-specific deep dive-dır.


## Niyə Vacibdir

Instagram 'like' sayacı single row UPDATE-dir — yüksək yazı trafiki row lock contentionuna gətirir. Sharded counter, Redis INCR + flush, Morris approximate algorithm — hot row probleminin praktik həlləridir. YouTube view count, Reddit karma — hamısı bu pattern-i istifadə edir.

## Problem — Single-row UPDATE contention

### Naive yanaşma

```sql
-- Instagram post-una like gələndə:
UPDATE posts SET like_count = like_count + 1 WHERE id = 42;
```

**Post 42 viral olarsa** (Cristiano Ronaldo posts), saniyədə 50k like gəlir. Postgres/MySQL-də:

```
T=0  TX-A: BEGIN; UPDATE posts SET like_count=100 WHERE id=42  (row lock)
T=1  TX-B: UPDATE ... WHERE id=42  (WAITS for A's lock)
T=2  TX-C: UPDATE ... WHERE id=42  (WAITS)
...
T=50k queued writers for ONE row
```

### Niyə pis?

1. **Row-level lock** — hər yazı bütün digərlərini gözlədir
2. **Serial execution** — 50k/saniyə olsa da, DB 500/saniyə etməyə məhdudlaşır
3. **Connection pool exhaustion** — bloklanmış connection-lar başqa sorğuları da boğur
4. **Replication lag** — hər UPDATE bir WAL entry, replica geri qalır
5. **Deadlock riski** — bir neçə counter eyni TX-də dəyişəndə

```
Throughput:
  no-contention:    50,000 writes/sec per-table  ✓
  single hot row:   ~500 writes/sec (lock bottleneck)  ✗
```

## Həll 1 — Sharded Counter

**Fikir:** bir counter-i N fiziki satıra böl. Yazanda random shard-a `+1` et. Oxuyanda bütün shard-ları topla.

### Schema

```sql
CREATE TABLE post_like_shards (
    post_id   BIGINT NOT NULL,
    shard_id  SMALLINT NOT NULL,  -- 0..N-1
    count     BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (post_id, shard_id)
);

-- N=100 shard per post (səbəb: 50k writes / 100 = 500/shard = OK)
```

### Write

```php
// app/Services/LikeCounter.php
public function like(int $postId): void
{
    $shard = random_int(0, self::SHARDS - 1);
    DB::statement("
        INSERT INTO post_like_shards (post_id, shard_id, count)
        VALUES (?, ?, 1)
        ON CONFLICT (post_id, shard_id)
        DO UPDATE SET count = post_like_shards.count + 1
    ", [$postId, $shard]);
}
```

### Read

```sql
SELECT COALESCE(SUM(count), 0) AS total
FROM post_like_shards
WHERE post_id = 42;
```

N=100 row-u bir sorğuda toplayırıq — 100 satır üçün <1ms.

### Trade-off

| Metrik | Single-row | Sharded (N=100) |
|--------|-----------|-----------------|
| Write throughput | 500/sec | 50,000/sec |
| Read latency | 1ms | 2-5ms (SUM) |
| Storage | 1 row | N rows/counter |
| Exact value | yes | yes |
| Hot spot | yes | no |

### Shard count necə seçilir?

```
N = peak_writes_per_sec / safe_per_row_throughput
  = 50000 / 500
  = 100 shards
```

Çox shard = boş satırlar + oxuma bahası. Az shard = hələ də contention.

**Dynamic sharding:** viral olmayan post üçün N=1, trend-ə düşəndə N=100-ə artır:

```php
$shards = Cache::remember("post:$postId:shards", 60, function() use($postId) {
    $velocity = Cache::get("post:$postId:writes_per_sec", 0);
    return min(100, max(1, intval($velocity / 500)));
});
$shard = random_int(0, $shards - 1);
```

## Həll 2 — Redis INCR + periodic flush

Redis INCR atomic və single-threaded — saniyədə 100k+ INCR edir.

```php
// write path — Redis-ə
Redis::incr("post:$postId:likes");

// read path — Redis-dən oxu
$count = Redis::get("post:$postId:likes");

// flush path — hər 30 saniyədə Postgres-ə yaz
foreach ($dirtyPosts as $postId) {
    $count = Redis::get("post:$postId:likes");
    DB::update("UPDATE posts SET like_count=? WHERE id=?", [$count, $postId]);
}
```

### Trade-off

- **+** Ultra-high write throughput
- **+** Read ultra-cheap (O(1))
- **−** Redis crash olsa, son window itirilə bilər (Redis AOF istifadə et)
- **−** Exact sayı olmayacaq yazılar Redis-də, DB-də köhnə

### Redis-də sharding

Tək Redis key-i də hot ola bilər (single shard Redis cluster). Client-side sharding:

```
keys = [
  "post:42:likes:shard-0",
  "post:42:likes:shard-1",
  ...
  "post:42:likes:shard-15"
]
shard = hash(client_id) % 16
INCR keys[shard]

Read: MGET all 16 shards, SUM
```

Redis Cluster-da shard-lar fərqli node-lara düşür, hot key boğazlanmır.

## Həll 3 — Write-behind (async queue)

```
Client ---> App ---> Kafka topic "likes"  (event)
                          |
                          v
                 Consumer (batched)
                          |
                          v
                 DB UPDATE ... count += batch_size
```

**Batching:**
```php
// Consumer: 100 event-i bir UPDATE-də birləşdir
$counts = [];
foreach ($events as $e) {
    $counts[$e->post_id] = ($counts[$e->post_id] ?? 0) + 1;
}

DB::transaction(function() use ($counts) {
    foreach ($counts as $postId => $delta) {
        DB::update("UPDATE posts SET like_count = like_count + ? WHERE id = ?",
                   [$delta, $postId]);
    }
});
```

50k event/saniyə → 100 batch × 500 UPDATE/saniyə — DB rahat.

## Approximate counter — Morris algorithm (1977)

**Problem:** milyardlarla events saymaq lazım, amma **exact** sayı lazım deyil (dəqiqlik ±5% OK). Hər event üçün full int32 (4 byte) lazım deyil.

**Morris counter:** counter dəyəri `v`-dir, amma **həqiqi** say `2^v`-dir. Increment probabilistic:

```
On event:
  with probability 1 / 2^v:
      v := v + 1

Estimate:
  N ≈ 2^v - 1
```

Beləliklə 8-bit counter-də 2^255-ə qədər sayabilirsən (aldatıcı dəqiqliklə).

**İstifadə:** network routers saniyədə milyardlarla packet görür — exact sayı lazım deyil, traffic trend-i lazımdır.

## HyperLogLog — unique sayma

**Problem:** "bu post-u neçə **unique** user gördü?" — `COUNT(DISTINCT user_id)` milyardlarla row-da yaddaşda saxlanmayan iş.

HyperLogLog (HLL): **probabilistic cardinality estimation**. 12 KB ilə 10^9-a qədər unique element, ±2% error.

### İntuisiya

Hash(user_id) → binary. **Leading zeros** say. 32-bit hash-da 5 leading zero o deməkdir ki, ~2^5 = 32 unique element görmüsən (birinin belə olması gözlənir).

```
user-A hash = 000001010...  (5 leading zeros → estimate 2^5=32)
user-B hash = 00010...       (3 leading zeros)
user-C hash = 0000001...     (6 leading zeros → estimate 2^6=64)

max_leading_zeros = 6  →  estimate ~64 unique
```

Tək counter variance çox yüksəkdir. Həll — **m register** (adətən m=1024 və ya 16384). Hash-in ilk `log2(m)` biti register seçir, qalanı leading-zero sayılır.

### Formula

```
E = α_m × m² × 1 / Σ 2^(-M[j])

α_m — bias correction constant
M[j] — j-ci register-də max leading zero
```

### Redis HLL

```bash
PFADD post:42:viewers user_123
PFADD post:42:viewers user_456
PFCOUNT post:42:viewers   # → ~2 (probabilistic)

# Birləşdirmə (union):
PFMERGE post:42:viewers:total post:42:viewers:day-1 post:42:viewers:day-2
PFCOUNT post:42:viewers:total
```

Hər key 12 KB — milyardlarla unique viewer saxlamaq olar.

### Real istifadə

- **Reddit** — post unique viewers HLL ilə
- **Google Analytics** — approximate users
- **YouTube view count** — ilk 300-dən sonra probabilistic
- **Twitter** — impressions count (HLL + sharded counter)

## Eventual vs exact counting

| Scenario | Approach |
|----------|----------|
| Bank balance | **Exact** — sharded counter + DB, ACID |
| Post like count | Eventual OK — Redis INCR + periodic flush |
| Unique viewers | Approximate OK — HLL |
| Inventory stock | **Exact** — row-level lock OK (low contention) |
| Dashboard metrics | Approximate OK — Morris / HLL |
| Billing (ad clicks) | **Exact** — Kafka + idempotent consumer |

**Qaydalar:**
- Pul sayılan yerlərdə **exact** və audit log
- UX metrikası üçün **approximate** OK
- Trend > exact sayı — "1.2M likes" yazmaq "1,234,567" yazmaqdan fərqli deyil

## Real-world — Instagram like counter

**Məlumat (public talks):**
- Post 42 viral → 100k likes/saniyə
- Hər like iki counter artırır: post.like_count + user.like_given_count

**Arxitektura:**

```
client → app → Kafka topic "likes"
                    ↓
         Consumer (dedup + batch)
                    ↓
         Redis INCR (real-time display)
         +
         Cassandra counter column (persist)
                    ↓
         Every 1s: replica → read endpoint
```

- **Hot post detection** — son 1 dəqiqədə >1000 like olsa, shard count-u artır
- **Cassandra counter columns** — native sharded counter implementation
- **Kafka** — idempotent (user_id+post_id unique event_id) → deduplication
- **Display** — Redis (real-time), ground truth Cassandra

## Real-world — YouTube view count

**Məlumat (Google engineers, 2012):**
- "Gangnam Style" ilk 18 ay ərzində int32 overflow təhlükəsi (2.147B)
- Google int64-ə keçdi

**Strategy:**
1. İlk 300 view — **exact** (bot/fraud detection üçün)
2. 301+ — **approximate** (sampled + HLL + bot filter)
3. Periodic **full recount** (gündə bir dəfə, batch job)

Niyə? View fraud böyük problem — click farm-lar milyonlarla view yaradır. YouTube sayı "freeze" edir 300-də və ML ilə təhlil edir, sonra açır.

## Cassandra Counter Column

```sql
CREATE TABLE post_stats (
    post_id UUID PRIMARY KEY,
    likes   COUNTER,
    views   COUNTER
);

UPDATE post_stats SET likes = likes + 1 WHERE post_id = ?;
```

**Daxilində:** hər replica öz `delta`-nı saxlayır, read zamanı `SUM(deltas)` aparılır. Quorum read. LWW + anti-entropy.

**Diqqət:** Cassandra counter-lər idempotent deyil (retry → double count). İdempotency üçün event log + ayrı materialization lazımdır.

## Back-of-envelope

**Twitter tweet like counter, peak:**
- Tweets per day: 500M
- Likes per tweet (avg): 5
- Likes per day: 2.5B
- Peak QPS: 2.5B / 86400 × 3 (peak-off-peak ratio) = ~87k likes/sec
- Viral tweet peak: 50k/sec on single tweet

**Storage (Cassandra counter):**
- 2.5B events/day × 100 bytes (with metadata) = 250 GB/day
- Compressed with LZ4: ~50 GB/day
- Annual: 18 TB (retention 1 year)

**Sharded counter (Postgres alternative):**
- 500M tweets × 10 shards × 24 bytes = 120 GB (all time)
- Read: 10 rows per SELECT, well within index

## Praktik Baxış

1. **Write-heavy counter → shard** — hot row öldürən enemy
2. **Exact-unique → HLL** olmasa, impossible memory-wise
3. **Eventual consistency qəbul et** — 1 saniyə geri qalma OK social app-da
4. **Redis-i cache kimi, DB-ni ground truth kimi** — Redis restart-lı üçün AOF/RDB
5. **Dynamic shard count** — viral content detect et, avtomatik sharding
6. **Approximate != false** — 1.2M vs 1,234,567 UX-ə fərq etmir
7. **Fraud & bot detection layer** ayrı — counter-i freeze et, təmizlədikdən sonra yenilə

## Anti-patterns

1. **Single row counter with high QPS** — lock hell
2. **Redis INCR without persistence** — crash data loss
3. **Client-side counter** — asla etibar etmə (bot manipulation)
4. **Reading all shards synchronously in critical path** — cache SUM result
5. **HLL for small cardinalities** — <1000 üçün COUNT DISTINCT daha dəqiq

## İnterview suallarının tipik cavabları

**"Design like button that scales to Ronaldo's posts"**
- Kafka "like-events" topic (idempotent by event_id)
- Consumer batched write → sharded Postgres (N=100) + Redis INCR
- Display from Redis, flush to DB every 5s
- HLL for unique engagement metric
- Bot/fraud layer at Kafka level

**"Count unique DAU across 100M users"**
- HLL per day per product → 12KB each
- PFMERGE for multi-day / multi-product
- Exact count possible via Spark batch (hourly)

## Ətraflı Qeydlər

Sharded counter sadə ideyadır amma hot-row bottleneck-in ən effektiv həllidir. Ən vacib suallar: **exact sayı doğrudan lazımdır?** (pul: ha, likes: yox), **write QPS necədir?** (decide shard count), **cost / storage constraint?** (HLL dramatic save). Modern sistemlər (Instagram, Twitter, YouTube) bu texnikaların kombinasiyasını işlədir — tək-tək deyil.


## Əlaqəli Mövzular

- [Data Partitioning](26-data-partitioning.md) — sharding prinsipləri
- [Caching](03-caching-strategies.md) — Redis-də counter cache
- [Distributed Locks](83-distributed-locks-deep-dive.md) — counter atomicity
- [Probabilistic Data Structures](33-probabilistic-data-structures.md) — HLL approximate count
- [Pub/Sub](81-pubsub-system-design.md) — counter event streaming
