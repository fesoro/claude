# News Aggregator Design (Reddit / Hacker News)

## Nədir? (What is it?)

News aggregator istifadəçilərin link və text post submit etdiyi, bir-birinin
postlarına upvote/downvote verdiyi, nested comment yazdığı sosial platformadır.
Reddit, Hacker News, Lobsters, Product Hunt buna nümunədir. Sistemin əsas hissəsi
ranking alqoritmidir — hansı post "hot" feed-in yuxarısına çıxacaq, hansı aşağıya
düşəcək, time decay ilə necə köhnələcək.

Sadə dillə: qəzetin birinci səhifəsi kimi — amma redaktor yox, istifadəçilərin
səsi (vote) hansı xəbərin top-a çıxacağını müəyyən edir.

```
User submits post ──▶ [Post DB] ──┐
                                  │
Users vote ────────▶ [Vote DB] ───┼──▶ Ranking Engine ──▶ Hot Feed
                                  │    (score + time)     (Redis ZSET)
Users comment ─────▶ [Comment DB]─┘
                                         ▼
                                   User sees top 25 posts
```

## Requirementlər (Requirements)

**Functional:** user link/text post submit edir; upvote/downvote (bir user bir
post üçün bir dəfə); nested comments; feed types (Hot, New, Top, Rising);
subreddit/category; subscription; search title+body; trending.

**Non-Functional:** 500M user, 50M DAU, 100M post/month, 1B vote/month
(~385 vote/saniyə), read QPS 10k, feed p99 < 200ms, vote ack < 100ms,
viral post 1M+ read/saat tab gətirməlidir.

```
Capacity:
  Post:    100M/month × 2KB  = 200GB/month  → 5 il: ~12TB
  Vote:    1B/month   × 32B  = 32GB/month   → 5 il: ~2TB
  Comment: 300M/month × 500B = 150GB/month  → 5 il: ~9TB
  Cache (top 10k hot post body + metadata): ~100MB in Redis
```

## Əsas Komponentlər (Key Components)

### Ranking Algoritmləri

**Hacker News:** `score = (points - 1) / (age_in_hours + 2)^1.8`
```
points: upvotes - downvotes;  1.8: gravity (yüksək → daha sürətli düşür)

Post A: 100 pts, 2 saat  → 99/(4)^1.8   = 8.17
Post B:  50 pts, 1 saat  → 49/(3)^1.8   = 6.78
Post C: 200 pts, 8 saat  → 199/(10)^1.8 = 3.15  (köhnəlir)
```

**Reddit "hot":** `score = log10(max(|net|, 1)) + sign(net) × age_sec / 45000`
```
log10: ilk 10 vote 1 vahid, sonrakı 90 vote 1 vahid, 900 yenə 1 vahid
       (viral post-un sonsuz dominant olmasının qarşısı)
45000: ~12.5 saat — hər 12.5 saatda post 1 vahid qazanır (köhnələri qovur)
```

**Wilson score** (comment "best" ranking):
```
        p + z²/2n - z × sqrt((p(1-p) + z²/4n)/n)
score = ────────────────────────────────────────   z=1.96 (95% CI)
                     1 + z²/n                      p=upvotes/n, n=total

Niyə: 1/1 post (100%) 950/1000 (95%)-dən yuxarı olmamalıdır — sample kiçikdir.
Wilson 95% CI-nin aşağı həddini götürür.
```

**Trending:** son 1 saat vote count / son 24 saat orta. Nisbət > 3 → trending.

### Data Model

```sql
posts (
    id BIGINT PK, subreddit_id BIGINT, user_id BIGINT,
    title VARCHAR(300), body TEXT, url VARCHAR(2000),
    upvotes INT, downvotes INT, hot_score DOUBLE,
    created_at TIMESTAMP,
    INDEX (subreddit_id, hot_score), INDEX (created_at)
);

votes (
    user_id BIGINT, post_id BIGINT, value TINYINT,  -- -1, 0, 1
    created_at TIMESTAMP,
    PRIMARY KEY (user_id, post_id)                   -- idempotency
);

comments (
    id BIGINT PK, post_id BIGINT, parent_id BIGINT NULL,
    user_id BIGINT, body TEXT, score INT, created_at TIMESTAMP,
    INDEX (post_id, parent_id)
);

subreddits (id, name, description, created_at)
subscriptions (user_id, subreddit_id, created_at)
```

### Comment Tree Storage

Üç yanaşma var:

```
Adjacency list:  parent_id field + recursive CTE
  ✓ sadə, write ucuz    ✗ read bahalı, depth çox olanda yavaş

Closure table:   (ancestor_id, descendant_id, depth) cütləri pre-compute
  ✓ subtree bir JOIN   ✗ write 2-3x bahalı (N ancestor row insert)

Materialized path: path VARCHAR ("1.5.12.3")
  ✓ subtree LIKE '1.5.%', pre-order sort ORDER BY path
  ✗ path dərinliyi məhdud, update bahalı
```

Read-heavy sistem (Reddit, HN — 100:1) üçün closure table + Redis preserialized
tree cache üstün seçimdir.

### Vote Processing

Vote **idempotent** olmalıdır — F5 və ya retry score-u iki dəfə artırmamalıdır.
`(user_id, post_id)` unique + upsert (`INSERT ... ON DUPLICATE KEY UPDATE`).

**Score denormalization async** — vote insert olunduqda queue-ya job göndərilir,
worker `post.hot_score` yeniləyir. Viral post lock contention problemi
(1M user eyni row → deadlock) — atomic increment və ya batch update (hər 5-10
saniyə aggregate) ilə həll olunur.

### Feed Generation

```
Hot:    Background job hər 30 saniyə top 1000 post-un scorunu yeniləyir
          ZADD hot:all {score} {post_id}
        Read: ZREVRANGE hot:all 0 24  (O(log N), < 1ms)

New:    ORDER BY created_at DESC LIMIT 25 (index-lə trivial)

Top:    ZADD top:day:{YYYY-MM-DD} {points} {post_id}  ← hər vote
        ZREVRANGE top:day:2026-04-18 0 24

Rising: Son 1 saatda vote/dəq nisbəti yüksək olanlar (trending alqoritmi)

Home:   User subscribed [r/php, r/laravel, r/golang]
        ZUNIONSTORE tmp_{user} 3 hot:r/php hot:r/laravel hot:r/golang
        ZREVRANGE tmp_{user} 0 24  → 5 dəq cache
```

### Caching Strategiyası

```
Layer 1: CDN           — post page HTML, 60s TTL (viral post edge-dən)
Layer 2: Redis         — post:{id} body JSON, 5 dəq TTL
Layer 3: Redis ZSET    — feed order (hot, top, per-subreddit)
Layer 4: Request memo  — per-request in-process cache

Hot post problem: viral 1M read/saat → layer 1-2 həll edir, DB-yə heç nə gəlmir
Vote write path: debounced queue job (ShouldBeUnique, 10s window) → spike absorb
```

### Anti-Abuse, Moderation, Search, Real-Time

- **Vote manipulation**: karma threshold 10+ (yeni hesab vote edə bilmir),
  IP velocity 100 vote/dəq → shadowban (UI-da görür, real score dəyişmir),
  vote graph analysis (A→B patterns → brigade detection)
- **Rate limiting**: post 1/10dəq, comment 1/30san, vote 30/dəq
- **CAPTCHA**: ilk 24 saat yeni hesab, suspicious activity
- **Moderation**: user report queue, automod regex/ML, mod audit log
- **Search**: Elasticsearch `match(title)^3 + match(body)^1 + function_score
  (log10(upvotes) + gauss_decay(age, 30d))`, MySQL→ES async (Debezium)
- **Real-time**: WebSocket (Laravel Reverb / Pusher), `channel: post.{id}`,
  live vote/comment counter animate (Reddit bubble effect)

## Arxitektura (Architecture)

```
┌────────┐   ┌──────────────┐   ┌──────────────┐
│ Client │──▶│ API Gateway  │──▶│   Web Tier   │
│        │   │ + Rate Limit │   │  (Laravel)   │
└────────┘   └──────────────┘   └──────┬───────┘
                                       │
            ┌──────────────┬───────────┼──────────────┬──────────────┐
            │              │           │              │              │
       ┌────▼────┐   ┌─────▼──────┐ ┌──▼──────┐ ┌────▼─────┐ ┌──────▼──────┐
       │  Redis  │   │   MySQL    │ │  Elastic│ │ Horizon  │ │  WebSocket  │
       │  ZSET   │   │  (sharded  │ │  Search │ │ (vote +  │ │ (live vote  │
       │ + body  │   │ by post_id)│ │         │ │ feed job)│ │   counts)   │
       └─────────┘   └────────────┘ └─────────┘ └──────────┘ └─────────────┘

Read (hot feed):
  1. GET /api/feed/hot
  2. ZREVRANGE hot:all 0 24   → post id-lər
  3. MGET post:{id1..25}      → body JSON batch
  4. Miss olanları MySQL → cache warm → response

Write (vote):
  1. POST /api/posts/{id}/upvote
  2. DB upsert (idempotent)
  3. RecalculateScore::dispatch($id)  (ShouldBeUnique, 10s window)
  4. Return 200 immediately (optimistic UI)
  5. Worker: score hesabla, post update, ZADD hot:all new_score id
```

**Sharding:** `post_id % 16` uniform distribution verir, amma subreddit feed
query cross-shard scatter-gather olur. Alternative: shard by subreddit_id —
r/funny kimi hot subreddit hotspot yaradır. Pragmatic həll: post_id sharding
(data layer) + Redis ZSET per subreddit (feed layer).

## Praktiki Nümunələr (PHP/Laravel)

### Hot Score Calculator

```php
class HotScoreCalculator
{
    public function reddit(int $up, int $down, Carbon $createdAt): float
    {
        $net = $up - $down;
        $order = log10(max(abs($net), 1));
        $sign = $net > 0 ? 1 : ($net < 0 ? -1 : 0);
        $epoch = Carbon::parse('2005-12-08 07:46:43');
        $seconds = $createdAt->diffInSeconds($epoch);

        return round($order + ($sign * $seconds / 45000), 7);
    }

    public function hackerNews(int $points, Carbon $createdAt): float
    {
        $ageHours = $createdAt->diffInHours(now());
        return ($points - 1) / pow($ageHours + 2, 1.8);
    }
}
```

### Vote Service (Idempotent + Debounced Recalc)

```php
class VoteService
{
    public function vote(int $userId, int $postId, int $value): void
    {
        if (!in_array($value, [-1, 0, 1])) {
            throw new InvalidVoteException();
        }

        DB::table('votes')->upsert(
            [['user_id' => $userId, 'post_id' => $postId, 'value' => $value]],
            uniqueBy: ['user_id', 'post_id'],
            update: ['value', 'updated_at']
        );

        RecalculateScore::dispatch($postId)
            ->onQueue('votes')
            ->delay(now()->addSeconds(5));  // debounce viral spike
    }
}

class RecalculateScore implements ShouldBeUnique
{
    public int $uniqueFor = 10;  // only 1 job per post per 10s

    public function __construct(public int $postId) {}

    public function uniqueId(): string { return "score:{$this->postId}"; }

    public function handle(HotScoreCalculator $calc): void
    {
        $post = Post::findOrFail($this->postId);
        $up   = DB::table('votes')->where('post_id', $this->postId)->where('value', 1)->count();
        $down = DB::table('votes')->where('post_id', $this->postId)->where('value', -1)->count();
        $score = $calc->reddit($up, $down, $post->created_at);

        $post->update(['upvotes' => $up, 'downvotes' => $down, 'hot_score' => $score]);

        Redis::zadd('hot:all', $score, $this->postId);
        Redis::zadd("hot:r/{$post->subreddit}", $score, $this->postId);
    }
}
```

### Feed Controller (Redis ZSET read)

```php
class FeedController extends Controller
{
    public function hot(Request $request): JsonResponse
    {
        $cursor = (int) $request->query('cursor', 0);
        $limit = 25;

        $postIds = Redis::zrevrange('hot:all', $cursor, $cursor + $limit - 1);

        $keys = array_map(fn($id) => "post:{$id}", $postIds);
        $cached = Redis::mget($keys);

        $posts = []; $misses = [];
        foreach ($postIds as $i => $id) {
            $cached[$i] ? $posts[] = json_decode($cached[$i], true) : $misses[] = $id;
        }

        if ($misses) {
            Post::whereIn('id', $misses)->get()->each(function ($p) use (&$posts) {
                Redis::setex("post:{$p->id}", 300, $p->toJson());
                $posts[] = $p->toArray();
            });
        }

        return response()->json(['posts' => $posts, 'next_cursor' => $cursor + $limit]);
    }
}
```

### Nested Comments (Closure Table)

```php
class CommentRepository
{
    public function getTree(int $postId): array
    {
        $rows = DB::select("
            SELECT c.*, cc.depth
            FROM comments c
            JOIN comment_closure cc ON c.id = cc.descendant_id
            JOIN comments root      ON cc.ancestor_id = root.id
            WHERE root.post_id = ? AND root.parent_id IS NULL
            ORDER BY root.id, cc.depth, c.score DESC
        ", [$postId]);

        return $this->buildTree($rows);
    }

    private function buildTree(array $rows, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($rows as $row) {
            if ($row->parent_id === $parentId) {
                $row->children = $this->buildTree($rows, $row->id);
                $tree[] = $row;
            }
        }
        return $tree;
    }
}
```

Package alternativləri: `kalnoy/nestedset` (nested set model), `staudenmeir/laravel-adjacency-list`
(recursive CTE builder).

## Interview Sualları

**S1: Reddit "hot" algoritmində niyə log10 istifadə olunur?**
C: Viral post-un sonsuz dominant olmaması üçün. log10 sayəsində ilk 10 vote
1 vahid gətirir, sonrakı 90 vote əlavə 1 vahid, 900 vote yenə 1 vahid. Beləliklə
yeni və az-vote post-lar da top-a çıxa bilir — time component ilə birlikdə
köhnə postlar təbii şəkildə aşağı düşür.

**S2: Wilson score niyə sadə ratio (upvote/total)-dan üstündür?**
C: Sadə ratio statistical confidence-i saymır. 1/1 post (100%) 950/1000 (95%)-dən
yuxarı olmamalıdır çünki sample kiçikdir. Wilson 95% confidence interval-ın aşağı
həddini götürür — "real ratio çox güman ki bundan aşağı deyil" siqnalı. Reddit
"best" comment sort məhz bunu istifadə edir.

**S3: Nested comments üçün adjacency list vs closure table — hansını seçərdin?**
C: Read/write ratio-ya bağlıdır. Read-heavy platformada (Reddit, HN — 100:1)
closure table üstündür, bir JOIN ilə subtree gəlir. Write-heavy və ya sadə
sistemdə adjacency list + recursive CTE yetər. Reddit hybrid: MySQL-də parent_id,
Redis-də preserialized comment tree cache.

**S4: Vote idempotency-ni necə təmin edirsən?**
C: `(user_id, post_id)` unique constraint + `INSERT ... ON DUPLICATE KEY UPDATE`.
Client retry etsə də DB-də yalnız bir row olur. Score recalc async və
`ShouldBeUnique` queue job ilə debounce olunur — viral post 1M vote alsa da
10 saniyədə bir recalc olur, lock contention azalır.

**S5: Hot feed-i hər request-də necə hesablamırsan?**
C: Precomputation + Redis sorted set. Background job hər 30 saniyədə top 1000
post-un hot score-unu hesablayır və `ZADD hot:all`. Feed read sadəcə
`ZREVRANGE 0 24` — O(log N), sub-millisecond. Score vote gəldikcə async
yenilənir, stale olsa da 30 saniyə içində düzəlir.

**S6: Viral post DB-ni öldürməsin deyə nə edərdin?**
C: Çox layer cache — CDN (edge, 60s), Redis post body (5 dəq), request memo.
Post body bir dəfə DB-dən oxunur, qalan 1M request cache-dən gəlir. Vote write
debounced queue job (10s unique window). Həqiqi hot yük zamanı DB-yə yalnız
vote insertləri düşür, read hamısı cache-dən.

**S7: Personalized home feed-i necə generate edirsən?**
C: User-in subscribed subreddit siyahısını götürürəm (adətən < 50). Hər subreddit
üçün `hot:r/{name}` Redis ZSET var. `ZUNIONSTORE tmp_user:{id} N zset1 zset2 ...`
ilə union alıram (WEIGHTS ilə subreddit prioriteti). `ZREVRANGE 0 24` → 5 dəq
cache. Çox subscription olanda (500+) fan-out on read model-ə keçirəm.

**S8: Search və trending-i necə implement edirsən?**
C: **Search**: Elasticsearch — title^3 + body^1 match, function_score ilə
log10(upvotes) + gauss decay(age) boost. MySQL → ES async replication Debezium
ilə. **Trending**: Redis sliding window counter — hər vote `INCR trend:{post}:
{1h_bucket}`. Nisbət (son 1 saat / son 24 saat orta) > 3 olanlar trending ZSET-ə.

## Cross-References

- Feed system (fan-out strategiyaları, Redis ZSET pattern) → 22-feed-system-design.md
- Caching strategies (multi-layer cache, CDN) → 03-caching-strategies.md
- Rate limiting (anti-abuse) → 06-rate-limiting.md
- Search systems (Elasticsearch integration) → 12-search-systems.md
- Data partitioning (post_id vs subreddit sharding) → 26-data-partitioning.md

## Best Practices

1. **Async Score Recalc** - Vote path sync update ilə yüklənmir, queue-ya atılır
2. **Debounce Viral Updates** - `ShouldBeUnique` job 10 saniyə pəncərə
3. **Redis ZSET for Feeds** - Precomputed hot feed O(log N), fresh
4. **Closure Table for Comments** - Read-heavy-də subtree bir JOIN
5. **Idempotent Votes** - `(user_id, post_id)` unique + UPSERT
6. **Multi-Layer Cache** - CDN + Redis + request memo
7. **Wilson Score for Comments** - Statistical confidence ratio-dan üstündür
8. **Rate Limit Everywhere** - Post, comment, vote endpointlərinə ayrı limit
9. **Karma Threshold** - Yeni hesab vote manipulation qarşısı
10. **Shard by post_id** - Uniform load, subreddit hotspot-lardan qaçma
11. **CDN for Post Pages** - Viral post HTML edge-dən, 60s TTL
