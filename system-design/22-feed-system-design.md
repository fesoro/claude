# Feed System Design

## Nədir? (What is it?)

Feed system istifadəçilərə personalized content axını göstərən sistemdir. Social media
news feed, Twitter timeline, Instagram feed kimi. İstifadəçinin izlədiyi insanların
postlarını kronoloji və ya relevance əsasında sıralayıb göstərir.

Sadə dillə: qəzet kimi - amma hər oxucu üçün fərqli məzmun seçilir, onun
maraqlarına və izlədiklərinə əsasən.

```
User follows: A, B, C

A posts: "Hello world"          ┌──────────────┐
B posts: "Nice weather"    ──── │  Feed Engine │ ──── User's Feed:
C posts: "New blog post"        │              │      1. C: "New blog post"
                                │  Ranking +   │      2. A: "Hello world"
                                │  Filtering   │      3. B: "Nice weather"
                                └──────────────┘
```

## Əsas Konseptlər (Key Concepts)

### Fan-out on Write vs Fan-out on Read

**Fan-out on Write (Push Model):**
```
User A posts something:
  → Write to A's followers' feed caches immediately

A has 1000 followers:
  → 1000 cache writes happen when A posts

Pros: Fast read (feed already prepared), simple read logic
Cons: Slow write (especially for celebrities), wasted space
      (followers who never check feed)

Best for: Users with < 10K followers
```

```
User A posts ──▶ Fan-out Service ──▶ Write to each follower's feed cache
                       │
                       ├──▶ Follower 1 feed cache: [new post, ...]
                       ├──▶ Follower 2 feed cache: [new post, ...]
                       ├──▶ Follower 3 feed cache: [new post, ...]
                       └──▶ ... (1000 writes)
```

**Fan-out on Read (Pull Model):**
```
User opens feed:
  → Fetch posts from all followed users at read time
  → Merge and rank in real-time

Pros: Fast write (just store the post), no wasted computation
Cons: Slow read (must query many sources), complex aggregation

Best for: Users following < 500 accounts
```

```
User opens feed ──▶ Feed Service ──▶ Query each followed user's posts
                         │
                         ├──▶ Get User A posts
                         ├──▶ Get User B posts
                         ├──▶ Get User C posts
                         │
                         ▼
                    Merge + Rank + Return
```

**Hybrid Approach (Twitter/Instagram):**
```
Regular users (< 10K followers): Fan-out on Write
Celebrities (> 10K followers):   Fan-out on Read

When user opens feed:
  1. Get pre-computed feed (from push)
  2. Fetch celebrity posts (pull)
  3. Merge and rank
  4. Return to user
```

### Feed Ranking

```
Chronological: Simply sort by timestamp (Twitter 2006-2016)

Ranked/Algorithmic:
  Score = f(affinity, weight, decay)

  Affinity:  How close is user to author? (interactions, mutual friends)
  Weight:    Content type value (video > photo > text)
  Decay:     Time decay (newer = higher score)

  score = (like_count × 1.0 +
           comment_count × 2.0 +
           share_count × 3.0 +
           author_affinity × 5.0) ×
          time_decay(hours_since_posted)
```

### Feed Storage

```
Approach 1: Pre-computed feed in Redis (sorted set)
  Key: feed:{user_id}
  Members: post_id with score (timestamp or ranking score)

  ZADD feed:123 1700000000 "post:456"
  ZADD feed:123 1700000100 "post:789"
  ZREVRANGE feed:123 0 19  → latest 20 posts

Approach 2: Feed table in database
  CREATE TABLE feeds (
      user_id BIGINT,
      post_id BIGINT,
      score DOUBLE,
      created_at TIMESTAMP,
      PRIMARY KEY (user_id, post_id),
      INDEX (user_id, score DESC)
  );
```

### Pagination

```
Offset-based (problematic for feeds):
  Page 1: /feed?offset=0&limit=20
  Page 2: /feed?offset=20&limit=20
  Problem: New posts shift everything, duplicates appear

Cursor-based (recommended):
  Page 1: /feed?limit=20
  Response: {posts: [...], cursor: "post_1700000100"}
  Page 2: /feed?limit=20&cursor=post_1700000100
  Advantage: Stable pagination, no duplicates
```

## Arxitektura (Architecture)

### Feed System Architecture

```
┌──────────────────────────────────────────────────┐
│                   Post Service                    │
│  User creates post → Store in DB → Emit Event    │
└──────────┬───────────────────────────────────────┘
           │
    ┌──────┴──────┐
    │  Event Bus  │
    │  (Kafka)    │
    └──────┬──────┘
           │
    ┌──────┴──────────────────┐
    │  Fan-out Service        │
    │                         │
    │  1. Get follower list   │
    │  2. For each follower:  │
    │     - Add to feed cache │
    │  3. Skip celebrities    │
    └──────┬──────────────────┘
           │
    ┌──────┴──────┐
    │  Feed Cache │
    │  (Redis)    │
    │             │
    │ Sorted Sets │
    │ per user    │
    └──────┬──────┘
           │
    ┌──────┴──────────────────┐
    │  Feed API Service       │
    │                         │
    │  1. Get cached feed     │
    │  2. Fetch celebrity     │
    │     posts (pull)        │
    │  3. Merge & rank        │
    │  4. Return paginated    │
    └─────────────────────────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Post Creation with Fan-out

```php
class PostService
{
    public function createPost(int $userId, array $data): Post
    {
        $post = Post::create([
            'user_id' => $userId,
            'content' => $data['content'],
            'media' => $data['media'] ?? null,
            'type' => $data['type'] ?? 'text',
        ]);

        // Trigger fan-out
        FanOutPost::dispatch($post);

        return $post;
    }
}

class FanOutPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public function __construct(private Post $post) {}

    public function handle(FeedService $feedService, FollowerService $followers): void
    {
        $authorFollowerCount = $followers->getFollowerCount($this->post->user_id);

        // Celebrity check - skip fan-out for users with > 10K followers
        if ($authorFollowerCount > 10000) {
            Log::info("Skipping fan-out for celebrity user {$this->post->user_id}");
            return;
        }

        // Fan-out to followers in batches
        $followers->getFollowerIds($this->post->user_id)
            ->chunk(500)
            ->each(function ($followerBatch) use ($feedService) {
                foreach ($followerBatch as $followerId) {
                    $feedService->addToFeed($followerId, $this->post);
                }
            });
    }
}
```

### Feed Service

```php
class FeedService
{
    private const FEED_MAX_SIZE = 1000;
    private const FEED_PAGE_SIZE = 20;

    public function __construct(
        private \Redis $redis,
        private PostRepository $posts,
        private FollowerService $followers
    ) {}

    public function addToFeed(int $userId, Post $post): void
    {
        $key = "feed:{$userId}";
        $score = $post->created_at->timestamp;

        $this->redis->zadd($key, $score, $post->id);

        // Trim feed to max size
        $this->redis->zremrangebyrank($key, 0, -self::FEED_MAX_SIZE - 1);

        // Set TTL
        $this->redis->expire($key, 86400 * 7); // 7 days
    }

    public function getFeed(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        // Step 1: Get pre-computed feed (push model results)
        $cachedPostIds = $this->getCachedFeed($userId, $cursor, $limit);

        // Step 2: Get celebrity posts (pull model)
        $celebrityPosts = $this->getCelebrityPosts($userId, $cursor, $limit);

        // Step 3: Merge and deduplicate
        $allPostIds = collect($cachedPostIds)
            ->merge($celebrityPosts)
            ->unique()
            ->toArray();

        // Step 4: Fetch full post data
        $posts = $this->posts->findMany($allPostIds);

        // Step 5: Rank
        $ranked = $this->rankPosts($posts, $userId);

        // Step 6: Paginate
        $paginated = $ranked->take($limit);
        $nextCursor = $paginated->last()?->id;

        return [
            'posts' => $paginated->values(),
            'cursor' => $nextCursor,
            'has_more' => $ranked->count() > $limit,
        ];
    }

    private function getCachedFeed(int $userId, ?string $cursor, int $limit): array
    {
        $key = "feed:{$userId}";

        if ($cursor) {
            $cursorScore = $this->redis->zscore($key, $cursor);
            return $this->redis->zrevrangebyscore(
                $key,
                $cursorScore - 1,
                '-inf',
                ['limit' => [0, $limit + 10]]
            );
        }

        return $this->redis->zrevrange($key, 0, $limit + 10);
    }

    private function getCelebrityPosts(int $userId, ?string $cursor, int $limit): array
    {
        // Get celebrity accounts that user follows
        $celebrityIds = $this->followers->getCelebrityFollowing($userId);

        if (empty($celebrityIds)) return [];

        $query = Post::whereIn('user_id', $celebrityIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        return $query->pluck('id')->toArray();
    }

    private function rankPosts(Collection $posts, int $userId): Collection
    {
        return $posts->map(function ($post) use ($userId) {
            $post->feed_score = $this->calculateScore($post, $userId);
            return $post;
        })->sortByDesc('feed_score');
    }

    private function calculateScore(Post $post, int $userId): float
    {
        $likeWeight = 1.0;
        $commentWeight = 2.0;
        $shareWeight = 3.0;

        $engagementScore =
            ($post->likes_count * $likeWeight) +
            ($post->comments_count * $commentWeight) +
            ($post->shares_count * $shareWeight);

        // Author affinity (how often user interacts with author)
        $affinity = Cache::remember(
            "affinity:{$userId}:{$post->user_id}",
            3600,
            fn () => $this->calculateAffinity($userId, $post->user_id)
        );

        // Time decay
        $hoursOld = $post->created_at->diffInHours(now());
        $timeDecay = 1 / (1 + ($hoursOld / 24));

        return ($engagementScore + $affinity * 10) * $timeDecay;
    }

    private function calculateAffinity(int $userId, int $authorId): float
    {
        // Count interactions in last 30 days
        $interactions = DB::table('interactions')
            ->where('user_id', $userId)
            ->where('target_user_id', $authorId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return min($interactions / 10, 1.0); // Normalize to 0-1
    }

    public function removeFromFeed(int $userId, int $postId): void
    {
        $this->redis->zrem("feed:{$userId}", $postId);
    }
}
```

### Feed Controller

```php
class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->feed->getFeed(
            userId: auth()->id(),
            cursor: $request->input('cursor'),
            limit: min($request->input('limit', 20), 50)
        );

        return response()->json([
            'data' => PostResource::collection($result['posts']),
            'meta' => [
                'cursor' => $result['cursor'],
                'has_more' => $result['has_more'],
            ],
        ]);
    }
}
```

### Follow/Unfollow with Feed Update

```php
class FollowService
{
    public function follow(int $userId, int $targetId): void
    {
        Follow::create([
            'follower_id' => $userId,
            'following_id' => $targetId,
        ]);

        // Backfill: add target's recent posts to follower's feed
        BackfillFeed::dispatch($userId, $targetId);
    }

    public function unfollow(int $userId, int $targetId): void
    {
        Follow::where('follower_id', $userId)
            ->where('following_id', $targetId)
            ->delete();

        // Remove target's posts from follower's feed
        CleanupFeed::dispatch($userId, $targetId);
    }
}

class BackfillFeed implements ShouldQueue
{
    public function handle(FeedService $feedService): void
    {
        $recentPosts = Post::where('user_id', $this->targetId)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($recentPosts as $post) {
            $feedService->addToFeed($this->userId, $post);
        }
    }
}
```

## Real-World Nümunələr

1. **Facebook** - Edge Rank algorithm, personalized feed, 2B+ users
2. **Twitter/X** - Timeline, "For You" algorithmic + "Following" chronological
3. **Instagram** - Ranked feed based on relationships, interests, recency
4. **LinkedIn** - Professional feed with engagement-based ranking
5. **TikTok** - "For You" page, content-based recommendation (not follow-based)

## Interview Sualları

**S1: Fan-out on Write vs Read fərqi nədir?**
C: Write: post yarananda bütün follower-ların feed-inə push. Sürətli read amma
yavaş write. Read: feed açılanda bütün following-lərin postlarını pull. Sürətli write
amma yavaş read. Hybrid: regular users push, celebrities pull.

**S2: Celebrity problemi necə həll olunur?**
C: Celebrity 10M follower-a post etdikdə 10M write lazımdır (fan-out on write).
Həll: celebrity postlarını fan-out etməyin, feed render zamanı pull edin.
Threshold təyin edin (10K+ followers = celebrity).

**S3: Feed-də deduplication necə olur?**
C: Redis sorted set post ID-ni member kimi saxlayır (avtomatik unique).
Pull model-də merge zamanı duplicate ID-ləri filter edin.
Client tərəfində də son defense olaraq dedup edin.

**S4: Cursor-based pagination niyə feed üçün daha yaxşıdır?**
C: Offset ilə yeni post əlavə olunanda sıra dəyişir, eyni post iki dəfə görünə bilər.
Cursor (son görülən post ID/timestamp) ilə "bundan sonrakıları göstər" deyirik,
stable pagination olur.

**S5: Feed ranking algorithm necə dizayn olunur?**
C: Engagement signals (likes, comments, shares), author affinity (interaction
history), content type weight, time decay, personalization signals.
A/B testing ilə weight-ləri optimize edin. ML model daha advanced yanaşmadır.

**S6: Yeni istifadəçinin boş feed problemi?**
C: Cold start problem. Həll: popular/trending posts göstərin, onboarding-də
maraq kateqoriyaları seçdirin, suggested accounts, editorial curated content
göstərin ilk günlərdə.

## Best Practices

1. **Hybrid Fan-out** - Push for regular users, pull for celebrities
2. **Cursor Pagination** - Offset əvəzinə cursor istifadə edin
3. **Feed Cache** - Redis sorted set ilə pre-computed feed
4. **Async Fan-out** - Queue ilə background-da fan-out edin
5. **Feed TTL** - Köhnə feed entry-lərini expire edin (7-30 gün)
6. **Ranking A/B Test** - Algorithm dəyişikliklərini A/B test edin
7. **Rate Limit Fan-out** - Celebrity fan-out-u throttle edin
8. **Denormalized Data** - Feed item-da lazımi data embed edin
9. **Real-time Updates** - WebSocket ilə yeni postları push edin
10. **Content Diversity** - Eyni authordan ardıcıl çox post göstərməyin
