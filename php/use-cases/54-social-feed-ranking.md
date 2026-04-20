# Use Case: Social Feed Ranking (Timeline Algorithm)

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
