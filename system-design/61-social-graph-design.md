# Social Graph Design

## Nədir? (What is it?)

Social graph istifadəçilər arasındakı əlaqələri (follow, friend, connection) saxlayan və
sorğulayan sistemdir. Twitter-də "follow", Instagram-da "follow", Facebook-da "friend",
LinkedIn-də "connection" — hamısı eyni problemin fərqli variantlarıdır. Interface sadə
görünür (follow/unfollow düyməsi) amma arxada 500M+ user, 100B+ edge, celebrity
fan-out və multi-hop traversal var. Feed, suggestion, notification, DM — hamısı bu
graph-ın üstündə oturur.

```
    Alice ────follows───▶ Bob
      │                    │
      │                    ▼
      └────follows───▶ Carol ◀──follows── Dave
                         │
                         ▼
                       Eve
```

## Tələblər (Requirements)

### Funksional Tələblər (Functional)

- Follow / unfollow (directed) və ya friend / unfriend (bidirectional consent)
- "A user-i B user-i izləyirmi?" — O(1) check
- Followers list və following list (paginated, ranked)
- Followers count, following count (cached counter)
- Friend-of-friend (2-hop) — "ortaq tanışlar" sorğusu
- Common friends between A and B (intersection)
- "People you may know" — graph + ML suggestions
- LinkedIn shortest path — "1st, 2nd, 3rd degree" bədii göstərmək
- Block, mute, restrict — əlavə relationship tipləri

### Qeyri-Funksional Tələblər (Non-Functional)

- **Scale** — 500M user, ortalama 200 follow → 100B edge
- **Power-law distribution** — celebrity 100M follower, median user 50 follower
- **Latency** — is-following p99 < 5ms, FoF p99 < 50ms
- **Availability** — 99.99%, read-heavy (100:1 read/write)
- **Consistency** — eventual OK (follow 1-2 saniyə gec görsənə bilər)
- **Durability** — edge silinməməlidir (GDPR istisnası)

## Directed vs Undirected Graph

```
Directed (Twitter, Instagram, TikTok):
    Alice ───follows──▶ Bob
    Bob mütləq Alice-i izləməyə bilər
    Edge: (from_id, to_id)

Undirected (Facebook friend, LinkedIn connection):
    Alice ◀──friends──▶ Bob
    Qarşılıqlı razılıq lazımdır (request + accept)
    Edge: (user_a, user_b) — (min, max) saxla ki, dup olmasın
```

**Fərqlər:**
- Directed: tək tərəfli yazma, celebrity problem kəskin
- Undirected: iki request (pending → accepted), daha az edge, graph daha "dense"
- Hybrid (Instagram): follow directed, amma "close friends" subset mövcud

## Capacity Estimation

```
User sayı:           500M
Avg following:       200 → 100B edge (directed)
Celebrity:           top 0.01% → 50K user, avg 10M follower
Edge ölçüsü:         (from_id 8B, to_id 8B, created_at 8B, type 1B) ≈ 32B
Ümumi storage:       100B × 32B = 3.2 TB (raw edges)
Denormalized:        2x (followers + following) = 6.4 TB
Index overhead:      2-3x = ~20 TB

Write rate:          500M × 0.1 follow/day ÷ 86400 ≈ 580 follow/s
Read rate:           58K follow-check/s (100x read-heavy)
Peak:                5x = ~290K RPS
Cache working set:   top 10% user activity = 50M user × 50KB hot data = 2.5 TB
```

## Storage Approaches

### 1. Relational Edge Table

```sql
CREATE TABLE follows (
    follower_id BIGINT, followee_id BIGINT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followee_id),
    INDEX idx_followee (followee_id, created_at)
);
```
Sadə, ACID, O(1) check via PK. Çatışmazlıq: FoF = self-join, celebrity row hot
partition. < 10M user MVP üçün yaxşıdır.

### 2. Key-Value (Redis Sets)

```
followers:42     → SET {1, 5, 9, 17, ...}    # 42-ni izləyənlər
following:42     → SET {3, 7, 11, ...}       # 42-nin izlədikləri
followers_ts:42  → ZSET (user_id, timestamp) # zaman-sıralı
```
O(1) SISMEMBER, SINTER, SDIFF. Dual-write və memory baha. Hot cache layer
DB-nin üstündə.

### 3. Graph Database (Neo4j, Neptune, JanusGraph, DGraph)

```cypher
MATCH (a:User {id: 42})-[:FOLLOWS]->(b:User)-[:FOLLOWS]->(c:User)
WHERE NOT (a)-[:FOLLOWS]->(c)
RETURN c.id, COUNT(b) AS mutual ORDER BY mutual DESC LIMIT 20
```
Native traversal, multi-hop O(depth). Sharding çətin, write throughput SQL-dən
aşağı. LinkedIn degrees, FoF suggestions, fraud ring üçün ideal.

### 4. Wide Column (Cassandra / ScyllaDB)

Row per user, PRIMARY KEY (user_id, follower_id). Celebrity row 100M column → hot
partition. Çözüm: user_id + bucket (0..15) shard daxilində də partition. Write
optimized, amma read-before-write lazımdır.

## Arxitektura (Architecture)

```
                    ┌────────────────────┐
                    │    API Gateway     │
                    └──────────┬─────────┘
                               │
                    ┌──────────▼─────────┐
                    │  Social Graph Svc  │
                    │  (Laravel / Go)    │
                    └──┬────────┬────────┘
                       │        │
          ┌────────────┘        └────────────┐
          ▼                                  ▼
   ┌─────────────┐                   ┌──────────────┐
   │  Redis      │                   │  Neo4j /     │
   │  (hot set,  │                   │  Neptune     │
   │  counter)   │                   │  (multi-hop) │
   └──────┬──────┘                   └──────┬───────┘
          │                                 │
          └───────────┬─────────────────────┘
                      ▼
           ┌──────────────────────┐
           │  MySQL / Cassandra   │
           │  (source of truth)   │
           │  sharded by user_id  │
           └──────────────────────┘
```

## Sharding Challenges

### User ID Sharding

```
shard = hash(user_id) % N

Shard 1: user 1, 5, 9, ...
Shard 2: user 2, 6, 10, ...
...
```

**Problem:** FoF sorğusu cross-shard fan-out edir.

```
User 42 (shard 2) → following [3 (s3), 7 (s3), 11 (s3), 100 (s0)]
FoF query = 4 shard-a paralel sorğu + aggregation
```

### Celebrity / Hot Shard Problem

```
Taylor Swift (user_id 999) 100M follower
→ shard(999) hər write-da vurulur (celebrity post edəndə notification)
→ followers:999 Redis SET 100M element, SMEMBERS çoxalır

Çözümlər:
1. Celebrity detection (> 1M follower) — ayrı tier
2. Mirror celebrity follower list → birdən çox shard
3. Push/pull hybrid feed (bax fayl 22-feed-system)
4. Follower list paginated, yalnız "active" follower saxla cache-də
```

### Denormalization

```
Hər follow iki yerə yazılır:
  edge (source of truth)    → MySQL shard(follower_id)
  followers:followee_id     → Redis shard(followee_id)
  following:follower_id     → Redis shard(follower_id)

Write: 3 system, transactional olmalıdır → outbox pattern + saga
Read: denormalized istər follower, istər following tərəfdən O(1)
```

## Friend-of-Friend və Suggestions

### FoF Query (BFS 2-hop)

```php
$following = Redis::smembers("following:42");
$fofUnion = [];
foreach ($following as $friendId) {
    foreach (Redis::smembers("following:$friendId") as $candidate) {
        if ($candidate == 42) continue;
        $fofUnion[$candidate] = ($fofUnion[$candidate] ?? 0) + 1;
    }
}
arsort($fofUnion);
$suggestions = array_slice(array_keys($fofUnion), 0, 20);
```

### "People You May Know" və LinkedIn Degrees

```
Offline daily batch (Spark/Flink):
  1. FoF count per pair + shared school/workplace/city features
  2. ML model (gradient boosting) → score
  3. Top 500 candidate per user → Redis ZSET suggestions:user_id
  Online: GET suggestions:42 → millisecond lookup

Shortest path — Bidirectional BFS:
  frontier_A = {A}, frontier_B = {B}
  hər iteration kiçik frontier-i 1 hop genişləndir, kəsişmə axtar
  early termination max_depth = 3
  celebrity-ləri BFS-dən çıxar (Bill Gates hamının 2nd-si, spam)
```

## Write / Read Path

```
Follow write:
  1. Validation (block list, protected account, rate limit)
  2. TX: INSERT edge + outbox row → COMMIT
  3. Kafka consumer:
     - SADD followers:followee, SADD following:follower
     - INCR followers_count, ZADD followers_ts
     - Feed fan-out (push vs pull), notification push
  Unfollow: eyni yol, DELETE + SREM + DECR

Read:
  is_following(a,b): SISMEMBER following:a b → < 1ms, miss isə DB EXISTS
  followers_count:  GET counter, hər gecə DB COUNT ilə reconcile
  get_followers:    ZRANGEBYSCORE followers_ts cursor +inf LIMIT 50
```

## Laravel Implementation

### Eloquent Many-to-Many

```php
class User extends Model
{
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows',
            'follower_id', 'followee_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows',
            'followee_id', 'follower_id')->withTimestamps();
    }

    public function follow(User $target): void
    {
        DB::transaction(function () use ($target) {
            $this->following()->syncWithoutDetaching([$target->id]);
            event(new UserFollowed($this, $target));
        });
    }
}
```

### Redis Layer + Neo4j

```php
class FollowGraph
{
    public function follow(int $a, int $b): void
    {
        Redis::pipeline(function ($pipe) use ($a, $b) {
            $pipe->sadd("following:$a", $b);
            $pipe->sadd("followers:$b", $a);
            $pipe->incr("followers_count:$b");
            $pipe->zadd("followers_ts:$b", now()->timestamp, $a);
        });
    }

    public function isFollowing(int $a, int $b): bool
    {
        return (bool) Redis::sismember("following:$a", $b);
    }

    public function commonFriends(int $a, int $b): array
    {
        return Redis::sinter(["following:$a", "following:$b"]);
    }
}

// Neo4j via laudis/neo4j-php-client (Bolt protocol)
$client = \Laudis\Neo4j\ClientBuilder::create()
    ->withDriver('bolt', 'bolt://neo4j:password@localhost:7687')->build();

$result = $client->run(
    'MATCH (me:User {id: $id})-[:FOLLOWS]->(f)-[:FOLLOWS]->(fof)
     WHERE NOT (me)-[:FOLLOWS]->(fof) AND me <> fof
     RETURN fof.id AS id, COUNT(f) AS mutual
     ORDER BY mutual DESC LIMIT 20', ['id' => 42]);
```

## Blocking, Muting, Privacy

```
FOLLOW    — normal
BLOCK     — A blocks B → B cannot see/follow A
MUTE      — A mutes B → A does not see B's posts (B bilmir)
RESTRICT  — IG specific, B follow edə bilər amma posts gizli

Read-time filter: feed.filter(post => !blocked(me, post.author))
Protected accounts: follow_request pending → accept/reject
GDPR delete: cascade bütün edge-ləri sil, 30 gün grace
```

## Real-World Systems

- **Twitter FlockDB** (deprecated) — MySQL shard + edge-optimized layer
- **Facebook TAO** — cached graph store MySQL üstündə, 1B+ read/s
- **LinkedIn Espresso + Graph Service** — document store + specialized graph
- **Instagram Cassandra** — celebrity bucket sharding
- **Pinterest Zen** — HBase edge store
- **Dgraph, Neo4j Aura** — managed graph DB

## Interview Sualları

**S1: Niyə sadəcə edge table yetmir, həm də denormalized Redis set saxlayırsan?**
C: `SELECT * FROM follows WHERE followee_id = 999` celebrity üçün 100M sətir
qaytarır, disk I/O partlayır. Redis SET O(1) SISMEMBER və O(N) iteration yaddaşda.
Trade-off: dual-write complexity, amma read 1000x sürətli.

**S2: Celebrity (100M follower) fan-out-u necə həll edirsən?**
C: Hybrid push/pull. Follower < 10K → push (feed-ə yaz). Celebrity → pull (user feed
açanda celebrity post-ları ayrıca götür, mərkəzi cache). Follower list birdən çox
shard-a mirror. Bax fayl 22-feed-system.

**S3: Friend-of-friend sorğusu relational DB-də niyə yavaş olur?**
C: Self-join: `follows f1 JOIN follows f2 ON f1.followee = f2.follower`. 200 follow-u
olan user üçün 200 × 200 = 40K candidate, cross-shard fan-out. Graph DB-də native
traversal O(follow_count × avg_follow), index-dən istifadə edir.

**S4: Sharding key olaraq user_id-ni seçsən, FoF cross-shard olur. Necə optimize edərsən?**
C: (1) Denormalize — hər user-in shard-ında həm followers, həm following saxla.
(2) Neo4j kimi ayrı graph service, sharding-i özü edir. (3) FoF-u pre-compute et
(batch), Redis-də cache. (4) Sorğu-yönlü duplikasiya.

**S5: LinkedIn 1st/2nd/3rd degree real-time necə hesablayır?**
C: Bidirectional BFS — həm source, həm target-dan 1 hop genişlənir, kəsişmə axtarır.
Celebrity (> 1M) BFS-dən çıxarılır yoxsa spam. Nəticə cache-lənir 24 saat. Depth > 3
üçün "out of network" qaytarılır.

**S6: Follow event-i necə consistent yazırsan — edge DB + Redis + counter + feed?**
C: Outbox pattern. TX-də edge INSERT + outbox sətri yaz, commit. Relay consumer
Kafka-ya push, sonra Redis / counter / feed service idempotent consumer-lərlə yenilənir.
Bax fayl 46-cdc-outbox.

**S7: "People you may know" necə hesablayırsan, real-time yoxsa batch?**
C: Batch daily (Spark). FoF count + shared school/workplace/location → ML model
(LightGBM) → top 500 candidate per user → Redis ZSET. Read-time yalnız lookup.
Real-time əlavə signal var (son 1 saatdakı search, profile view) — recency boost.

**S8: GDPR ilə user profile silinəndə graph-da nə baş verir?**
C: Cascade delete edge-lər həm follower, həm following tərəfdən. Counter-lər
reconcile olur. Amma digər user-in "following count"-u azala bilər — audit log saxla.
"Right to be forgotten" 30 gün grace period, sonra hard delete, backup-lardan da.

## Query Latency Targets

```
is_following(a, b):       p99 < 5ms   (Redis SISMEMBER)
followers_count(user):    p99 < 2ms   (cached counter)
get_followers paginated:  p99 < 20ms  (Redis ZRANGE + user hydrate)
common_friends(a, b):     p99 < 15ms  (Redis SINTER)
FoF top 20:               p99 < 50ms  (precomputed ZSET)
shortest path 3-hop:      p99 < 100ms (bidir BFS, cached)
follow write:             p99 < 30ms  (DB commit + async fan-out)
```

## Best Practices

1. **Denormalize both sides** — followers və following ayrı-ayrı saxla, read O(1)
2. **Celebrity tier ayrı** — > 1M follower üçün xüsusi logic (mirror, pull fanout)
3. **Outbox pattern** — edge write + cache update atomic olsun
4. **Counter cache + nightly reconcile** — real count drift-i düzəldir
5. **Bidirectional BFS** — shortest path üçün 1-hop BFS-dən qat-qat sürətli
6. **Precompute FoF suggestions** — batch job, read-time sadəcə lookup
7. **Hash + bucket sharding** — celebrity row-u shard daxilində də böl
8. **Rate limit follow action** — bot / spam-a qarşı (10 follow/minute)
9. **Soft-delete grace period** — unfollow / delete-ləri 7 gün geri qaytarılabilən saxla
10. **Monitoring** — edge write rate, hot shard QPS, FoF latency, cache hit ratio
