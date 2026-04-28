# Social Feed Ranking (Lead)

## Problem
- 1M user, hər user 100 friend
- User feed açanda 50 ən "relevant" post göstər
- Real-time (< 200ms latency)
- Scoring: recency + engagement + relationship strength

---

## Həll: Hybrid (push + pull) + Redis sorted set

```
Strategy seçimi:
  
  PUSH (fan-out on write):
    Post yaradılanda hər follower-in feed-ə push
    ✓ Read fast (Redis ZRANGE)
    ✗ Write slow (1M follower × push)
    ✗ Storage çox
  
  PULL (fan-out on read):
    Feed soruşulanda follow olduğun user-lərin son post-larını fetch
    ✓ Write fast
    ✗ Read slow (1000 friend × 5 post = 5000 query)
  
  HYBRID:
    Normal user: push (fast read)
    Celebrity (1M+ follower): pull (fan-out cost yoxdur)
    Active user: push, inactive: pull (storage save)
```

---

## 1. Database schema

```sql
CREATE TABLE posts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    content TEXT,
    created_at TIMESTAMP,
    INDEX (user_id, created_at)
);

CREATE TABLE post_engagement (
    post_id BIGINT PRIMARY KEY,
    likes INT DEFAULT 0,
    comments INT DEFAULT 0,
    shares INT DEFAULT 0,
    views INT DEFAULT 0,
    updated_at TIMESTAMP
);

CREATE TABLE follows (
    follower_id BIGINT,
    following_id BIGINT,
    created_at TIMESTAMP,
    strength FLOAT DEFAULT 1.0,    -- relationship strength
    PRIMARY KEY (follower_id, following_id)
);

CREATE TABLE user_metadata (
    user_id BIGINT PRIMARY KEY,
    follower_count INT,
    is_celebrity BOOLEAN,           -- > 100k follower
    activity_score FLOAT             -- last login, post freq
);
```

---

## 2. Push fan-out (post created)

```php
<?php
class PublishPostJob implements ShouldQueue
{
    public function __construct(public int $postId) {}
    
    public function handle(): void
    {
        $post = Post::findOrFail($this->postId);
        
        // Author celebrity? Skip push fan-out
        if ($post->user->is_celebrity) {
            return;
        }
        
        // Push to followers' feed
        $followerIds = Follow::where('following_id', $post->user_id)
            ->pluck('follower_id');
        
        // Chunk — 1000 follower üçün bulk
        foreach ($followerIds->chunk(1000) as $chunk) {
            ProcessFollowerFeedChunk::dispatch($post->id, $chunk->toArray());
        }
    }
}

class ProcessFollowerFeedChunk implements ShouldQueue
{
    public function __construct(
        public int $postId,
        public array $followerIds,
    ) {}
    
    public function handle(\Redis $redis): void
    {
        $score = $this->calculateInitialScore();
        
        $redis->multi(\Redis::PIPELINE);
        foreach ($this->followerIds as $userId) {
            $redis->zAdd("feed:user:$userId", $score, $this->postId);
            $redis->zRemRangeByRank("feed:user:$userId", 0, -1001);  // top 1000 saxla
            $redis->expire("feed:user:$userId", 86400 * 7);          // 7 gün TTL
        }
        $redis->exec();
    }
    
    private function calculateInitialScore(): float
    {
        // Basit: timestamp (sonradan recompute)
        return time();
    }
}
```

---

## 3. Feed read

```php
<?php
class FeedService
{
    public function getFeed(int $userId, int $limit = 50): array
    {
        // 1. Push feed-dən top N (Redis sorted set)
        $postIds = Redis::zRevRange("feed:user:$userId", 0, $limit - 1);
        
        // 2. Celebrity post-larını pull
        $celebrityPosts = $this->fetchCelebrityFeed($userId, 20);
        
        // 3. Birləşdir + score recalculate
        $merged = $this->mergeFeed($postIds, $celebrityPosts, $userId);
        
        // 4. Top N seç
        return array_slice($merged, 0, $limit);
    }
    
    private function fetchCelebrityFeed(int $userId, int $limit): array
    {
        // Celebrity-lərin son post-ları (cache)
        $celebrityIds = Cache::remember(
            "celebrity-follows:$userId", 
            300,
            fn() => Follow::where('follower_id', $userId)
                ->whereHas('following', fn($q) => $q->where('is_celebrity', true))
                ->pluck('following_id')
                ->toArray()
        );
        
        if (empty($celebrityIds)) return [];
        
        return Post::whereIn('user_id', $celebrityIds)
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id')
            ->toArray();
    }
    
    private function mergeFeed(array $pushed, array $pulled, int $userId): array
    {
        $allIds = array_unique(array_merge($pushed, $pulled));
        
        // Bulk fetch with engagement
        $posts = Post::with(['user', 'engagement'])
            ->whereIn('id', $allIds)
            ->get()
            ->keyBy('id');
        
        // Score hər post üçün
        $scored = [];
        foreach ($allIds as $id) {
            if (!isset($posts[$id])) continue;
            $scored[$id] = [
                'post'  => $posts[$id],
                'score' => $this->scorePost($posts[$id], $userId),
            ];
        }
        
        // Sort by score
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_map(fn($s) => $s['post'], $scored);
    }
    
    private function scorePost(Post $post, int $userId): float
    {
        $now = time();
        $age = $now - strtotime($post->created_at);
        
        // 1. Recency decay (24 saat ərzində azalır)
        $recencyScore = max(0, 1 - $age / 86400);
        
        // 2. Engagement score
        $eng = $post->engagement;
        $engagementScore = log($eng->likes + 1) * 1.0
                          + log($eng->comments + 1) * 2.0
                          + log($eng->shares + 1) * 3.0;
        
        // 3. Relationship strength
        $relationship = Follow::where('follower_id', $userId)
            ->where('following_id', $post->user_id)
            ->value('strength') ?? 0.5;
        
        // 4. Aggregate (weighted)
        return $recencyScore * 100
             + $engagementScore * 5
             + $relationship * 50;
    }
}
```

---

## 4. Score caching (avoid recomputation)

```php
<?php
// Daily — pre-compute score for top N posts per user
class PrecomputeFeedScoresJob implements ShouldQueue
{
    public function handle(): void
    {
        User::active()->chunk(1000, function ($users) {
            foreach ($users as $user) {
                $service = app(FeedService::class);
                $feed = $service->getFeed($user->id, 200);
                
                Redis::pipeline(function ($pipe) use ($user, $feed) {
                    foreach ($feed as $i => $post) {
                        $pipe->zAdd(
                            "feed:user:{$user->id}",
                            -1 * $i,         // sort by index
                            $post->id
                        );
                    }
                    $pipe->expire("feed:user:{$user->id}", 3600);
                });
            }
        });
    }
}
```

---

## 5. Engagement update (real-time score adjust)

```php
<?php
class LikePostHandler
{
    public function handle(int $postId, int $userId): void
    {
        // 1. Atomic increment
        Redis::hIncrBy("post:engagement:$postId", 'likes', 1);
        
        // 2. Async DB update (write-behind)
        SyncEngagementJob::dispatch($postId)->delay(now()->addMinute());
        
        // 3. Bütün feed-lərdə score yenilə? (HƏYR — çox bahalıdır)
        // Yalnız müəyyən threshold-da:
        $newCount = Redis::hGet("post:engagement:$postId", 'likes');
        if (in_array($newCount, [10, 100, 1000, 10000])) {
            BoostPostScoreJob::dispatch($postId);
        }
    }
}

class BoostPostScoreJob implements ShouldQueue
{
    public function handle(int $postId): void
    {
        // Bu post-un göründüyü bütün feed-lərdə score artır
        $post = Post::find($postId);
        $followerIds = Follow::where('following_id', $post->user_id)->pluck('follower_id');
        
        foreach ($followerIds->chunk(1000) as $chunk) {
            Redis::pipeline(function ($pipe) use ($postId, $chunk) {
                foreach ($chunk as $uid) {
                    $pipe->zIncrBy("feed:user:$uid", 100, $postId);   // boost score
                }
            });
        }
    }
}
```

---

## 6. Performance budget

```
Read feed:
  Redis ZREVRANGE: 1ms
  Celebrity fetch (cached): 1ms
  Bulk post fetch (50 ID): 10ms (with eager loading)
  Score recompute: 5ms
  Total: ~20ms ← ✓ SLO < 200ms

Write post:
  DB insert: 5ms
  Queue dispatch: 1ms
  
  Async push (background):
    1000 follower / chunk × 1000 chunks = 1M Redis ops
    Pipeline: 10k ops/sec/connection × 100 connections = 1M/sec
    Total: ~1 second async
    
Storage:
  Per-user feed: 1000 post × 16 bytes (zset entry) = 16 KB
  10M user × 16 KB = 160 GB (Redis RAM)
  → Sharded Redis cluster lazımdır
```

---

## 7. Pitfalls

```
❌ Naïve fan-out 1M follower üçün — sync request fail
   ✓ Async queue + chunked

❌ Score recomputation hər feed read-də
   ✓ Pre-compute (daily) + on-demand for new posts

❌ Celebrity post-ları fan-out etsən Redis crash
   ✓ Pull strategy celebrity üçün

❌ Inactive user-in feed-i həmişə doldurulur
   ✓ Last login > 30 days → no push, lazy build on next login

❌ Post deletion fan-out edilmir
   ✓ Tombstone event → bütün feed-lərdə ZREM

❌ Feed pagination cursor yoxdur
   ✓ Score-based cursor (after_score=X)
```

---

## Problem niyə yaranır?

Social feed problemi ilk baxışda sadə görünür: "user-in follow etdiyi insanların post-larını göstər". Lakin bu düşüncə tərzi production-da dərhal çöküşə gətirib çıxarır. Naive implementation adətən belə görünür: hər feed request-ində `follows` cədvəlindən user-in follow etdiklərini çəkir, sonra həmin user-lərin `posts` cədvəlindən son N post-u JOIN ilə alır, ardından application layer-ında scoring hesablayır. 1000 follower olan bir user üçün bu sorğu artıq yüzlərlə millisecond çəkir, çünki database-in N müxtəlif user-in post-larını bir anda join etməsi index-lərə baxmayaraq çox bahalı əməliyyatdır. 1M user sistemi üçün bu yanaşma tamamilə işə yaramır.

Daha dərin problem isə yazma tərəfindədir. Məşhur bir celebrity — deyək ki, 5 milyon follower-i olan bir influencer — yeni post paylaşdıqda, əgər push (fanout-on-write) strategiyası seçilibsə, 5 milyon Redis ZADD əməliyyatı eyni anda başlamalıdır. Bu həcmdə fanout bir neçə dəqiqə çəkir, Redis cluster-ı aşırı yükləyir, queue worker-lar tıxanır. Digər user-lərin adi post-ları isə queue-da gözləyir — feed real-time olması əvəzinə saatlarla gecikmə ilə göstərilir. Buna "celebrity problem" və ya "hot key problem" deyilir.

Scoring tərəfindəki problem isə başqadır. Post-un relevance score-u heç vaxt statik deyil — hər yeni like, comment, share onun dəyərini dəyişdirir. Əgər hər feed read-ində bütün post-ların score-u yenidən hesablanırsa, bu həm hesablama, həm də database yükü baxımından qəbuledilməzdir. Lakin score-u çox nadir yeniləsən, user köhnəlmiş sıralama görür. Bu trade-off-u düzgün idarə etmək üçün threshold-based score update tətbiq edilir: yalnız post viral olmağa başlayanda (məs: 100, 1000, 10000 like keçəndə) score yenilənir, hər like-da deyil.

---

## Trade-offs

| Strategiya | Üstünlüklər | Çatışmazlıqlar | Nə zaman istifadə et |
|------------|-------------|----------------|----------------------|
| **Push (fanout-on-write)** | Feed read-i O(1) — sadəcə Redis ZRANGE; latency çox aşağıdır; pre-computed feed | Write cost yüksəkdir; celebrity üçün milyonlarla Redis write; inactive user-lər üçün boş storage; post edit/delete bütün feed-lərdə yenilənməlidir | Kiçik-orta auditoriya; follower sayı məhdud (< 50k); read-heavy sistemlər |
| **Pull (fanout-on-read)** | Write zamanı əlavə iş yoxdur; celebrity post-ları problem deyil; storage qənaəti; post dəyişikliyi avtomatik əks olunur | Her feed read-də çoxlu DB sorğusu (N+1); latency yüksəkdir; scoring real-time hesablanmalıdır; cache olmadan ölçəklənmir | Write-heavy sistemlər; çox az follower sayı; real-time accuracy kritikdirsə |
| **Hybrid (push + pull)** | Normal user-lər üçün sürətli read; celebrity üçün fanout problemi yoxdur; inactive user-lər üçün storage qənaəti; ən çevik yanaşma | Ən mürəkkəb implementation; iki strategiyanın merge logic-i; celebrity threshold-unun düzgün seçilməsi tələb olunur; debug çətindir | Böyük miqyaslı sosial platformalar (Instagram, Twitter/X, Facebook); mixed audience (celebrity + normal user) |

---

## Anti-patternlər

**1. Bütün follower-lərə sync push etmək (Celebrity problem)**
5 milyon follower-i olan bir account post paylaşdıqda synchronous fanout başlanırsa, API request timeout-a uğrayır, queue worker-lar bloklanır, digər user-lərin job-ları saatlarla gözləyir. Celebrity threshold (məs: 100k+ follower) müəyyən edilməli, həmin account-lar üçün push fanout tamamilə söndürülərək pull strategiyasına keçilməlidir.

**2. Feed-i hər dəfə real-time SQL ilə hesablamaq**
`SELECT posts.* FROM posts JOIN follows ON ... WHERE follows.follower_id = ? ORDER BY score DESC LIMIT 50` sorğusu ilk baxışda düzgün görünür. Lakin 500 nəfəri follow edən bir user üçün bu sorğu 500 müxtəlif user-in post-larını birləşdirməli, engagement data-sını JOIN etməli, scoring hesablamalıdır. Her feed refresh-ində bu bahalı sorğunun işləməsi database-i aşırı yükləyir. Feed mütləq pre-computed olmalı, Redis sorted set-də saxlanmalıdır.

**3. Feed-də OFFSET-based pagination istifadə etmək**
`LIMIT 50 OFFSET 100` kimi sorğular feed context-ində iki ciddi problem yaradır: database hər dəfə əvvəlki 100 sətri oxuyub atır (performans), üstəlik feed real-time yeniləndiyindən yeni post-lar əlavə olunanda offset sürüşür — user eyni post-u iki dəfə görür və ya bəzi post-ları heç görmür. Bunun əvəzinə score-based cursor pagination tətbiq edilməlidir: `after_score=X` parametri ilə yalnız X score-dan aşağı olan post-lar qaytarılır.

**4. Score-u hər like/comment-də bütün feed-lərdə real-time yeniləmək**
Post 1 milyon follower-in feed-indədirsə və hər like-da həmin 1 milyon feed-dəki score yenilənirsə, bu 1 milyon Redis ZINCRBY əməliyyatı deməkdir — hər bir like üçün. Viral bir post saniyədə yüzlərlə like alır, bu isə Redis cluster-ı tamamilə çökdürür. Əvəzinə threshold-based update tətbiq edilməlidir: yalnız 10, 100, 1000, 10000 like keçildiyi anlarda score boost edilsin.

**5. Feed cache-ni çox tez expire etmək**
TTL-i 5 dəqiqə kimi qısa tutmaq cache miss storm-una gətirib çıxarır: çoxlu user eyni anda feed açırsa (məs: peak saatlarda), hamısı eyni vaxtda cache miss alır, hamısı DB-ə query vurur — bu "thundering herd" problemidir. Feed TTL ən azı 30 dəqiqə–1 saat olmalıdır; yeni post publish edildikdə isə yalnız həmin post-un follower-lərinin feed cache-i proaktiv olaraq invalidate edilməlidir.

**6. Silinmiş post-ları feed-dən silməmək (Dangling references)**
Post silinəndə yalnız `posts` cədvəlindən silmək kifayət deyil. Redis feed sorted set-lərindəki həmin post-un ID-si durur. User feed açanda bu ID ilə `posts` cədvəlindən data çəkməyə çalışırsa null gəlir — ya application xəta verir, ya da feed-də "boş" yer görünür. Post silinəndə mütləq tombstone event göndərilməli, async job bütün feed-lərdən `ZREM feed:user:* postId` əməliyyatı ilə həmin ID-ni silməlidir.

**7. Fan-out job-larını single queue-da çalışdırmaq**
Bütün fanout job-larını eyni queue-ya göndərmək böyük account-ların (məs: 1M follower) job-larının kiçik account-ların (məs: 100 follower) job-larını bloklamasına səbəb olur. Nəticədə kiçik account-ların follower-ləri post-u normal vaxtda görür, böyük account-larınkılar isə saatlarla gözləyir — və ya əksinə. Fanout queue-ları prioritet əsasında ayrılmalıdır: celebrity fan-out ayrı (az prioritet, çox worker), normal user fan-out ayrı (yüksək prioritet) queue-da işləməlidir.

---

## Interview Sualları və Cavablar

**S: Fan-out on write vs fan-out on read fərqi nədir, hansını seçərdiniz?**

Fan-out on write (push) strategiyasında post yaradılarkən hər follower-in feed-inə əlavə edilir — read zamanı sadəcə pre-computed feed oxunur, çox sürətlidir. Fan-out on read (pull) strategiyasında isə user feed açarkən follow etdiyi hər account-ın son post-ları real-time çəkilir — write zamanı əlavə iş yoxdur, amma read bahalıdır. Real sistemdə mən hybrid seçərdim: normal user-lər üçün push (sürətli read), 100k+ follower-i olan celebrity account-lar üçün isə pull (fanout cost yoxdur). Instagram, Twitter/X da məhz bu yanaşmanı istifadə edir.

**S: Instagram/Twitter celebrity account-ları üçün nə edirlər?**

Hər iki platform celebrity (high-follower) account-lar üçün fanout-on-write strategiyasını söndürür. Bunun əvəzinə user feed açanda ayrıca bir sorğu ilə follow etdiyi celebrity account-ların son post-ları çəkilir (pull), sonra normal push feed ilə merge edilir. Twitter-in 2023-cü ildəki "For You" feed arxitekturasında bu merge logic GraphQL layer-ında real-time baş verir. Əsas məqam: `is_celebrity` flag (məs: `follower_count > 100_000`) hər fan-out qərarında yoxlanılır, celebrity-nin follower-ları real-time pull ilə servis edilir.

**S: Feed ranking score-unu real-time hesablamaq olarmı?**

Texniki cəhətdən mümkündür, lakin production miqyasında qəbuledilməzdir. 50 post üçün hər birinin recency, engagement və relationship strength score-unu hesablamaq 50 ayrı DB sorğusu (relationship strength üçün) deməkdir — bu N+1 problemidir. Düzgün yanaşma: post yaradılarkən initial score hesablanır, Redis sorted set-ə yazılır; engagement threshold keçildikcə (10/100/1000 like) score async job ilə yenilənir; feed read zamanı isə yalnız kiçik bir in-memory recalculation (son N post üçün) aparılır. Bu şəkildə read latency ~20ms-ə salınır.

**S: Redis sorted set-in feed üçün istifadəsini izah edin.**

Redis sorted set (`ZSET`) hər element üçün bir float `score` saxlayır və elementlər həmişə score-a görə sıralı vəziyyətdə olur. Feed üçün `feed:user:{userId}` key altında post ID-ləri score ilə (relevance score və ya timestamp) saxlanır. `ZADD feed:user:42 1700000000 postId` əmri post-u feed-ə əlavə edir; `ZREVRANGE feed:user:42 0 49` ən yüksək score-lu 50 post-u O(log N + M) mürəkkəbliyi ilə qaytarır. `ZREMRANGEBYRANK` ilə feed ölçüsü (məs: max 1000 entry) saxlanır, köhnə post-lar avtomatik silinir. Pipeline ilə 1000 follower-in feed-inə eyni anda yazma əməliyyatı ~10ms çəkir.

**S: Feed-i infinite scroll ilə necə implement edərdiniz?**

OFFSET-based pagination-dan qaçınmaq lazımdır — feed real-time yeniləndiyindən offset sürüşür. Bunun əvəzinə score-based cursor istifadə edilir: ilk request `ZREVRANGE feed:user:42 0 49` ilə top 50 post-u qaytarır; response-da sonuncu post-un score-u `next_cursor` kimi göndərilir; növbəti request `ZREVRANGEBYSCORE feed:user:42 (cursor_score -inf LIMIT 0 50` ilə yalnız cursor-dan aşağı score-lu post-ları qaytarır. Bu şəkildə yeni post əlavə olunsa belə pagination düzgün işləyir, eyni post iki dəfə görünmür. Redis-də `ZRANGEBYSCORE` əmrinin `(` prefiksi exclusive range deməkdir — cursor post-un özü daxil edilmir.
