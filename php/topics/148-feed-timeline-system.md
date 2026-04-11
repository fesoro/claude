# System Design: Feed / Timeline System

## Mündəricat
1. [Tələblər](#tələblər)
2. [Fan-out Strategiyaları](#fan-out-strategiyaları)
3. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Post yaratmaq
  Öz feed-ini görmək (follow etdiklərinin postları)
  Yeni post gəldikdə feed yenilənir
  Pagination (sonsuz scroll)

Qeyri-funksional:
  Yüksək oxuma: 300M istifadəçi × 5 feed refresh/gün
  Aşağı gecikmə: feed < 200ms
  Eventual consistency: bütün follower-lar bir-iki saniyə gec görə bilər

Hesablamalar:
  Write: 5M post/gün → 58 post/saniyə
  Read:  1.5B feed request/gün → 17,000 read/saniyə
  Read:Write = ~300:1 → Read ağır sistem
```

---

## Fan-out Strategiyaları

```
Seçim 1 — Fan-out on Write (Push Model):
  Post yazılanda → bütün follower-ların feed cache-ınə əlavə et
  
  User A (1M follower) → post yazdı
  → 1M Redis entry yenilə (fan-out)
  
  Üstünlük: Read sürətli (pre-computed feed)
  Çatışmazlıq: Celebrity problem (1M follower = 1M yazı)

Seçim 2 — Fan-out on Read (Pull Model):
  Feed istənəndə → follow edilənlərin postlarını al, birləşdir
  
  User B → feed istəyir
  → 500 follow etdiyi var → 500 sorğu → birləşdir → sırala
  
  Üstünlük: Write ucuz
  Çatışmazlıq: Read bahalı (çox sorğu)

Seçim 3 — Hybrid (Twitter/Instagram yanaşması):
  Normal user (< 1000 follower): Fan-out on Write
  Celebrity (> 1M follower): Fan-out on Read
  
  Feed oxunanda:
    Redis-dəki pre-computed feed + Celebrity-lərin son postları
    Birləşdir → son N post

Bu hybrid yanaşma ən praktikdir.
```

---

## Yüksək Səviyyəli Dizayn

```
Post yaratma:
  User → POST /posts → Post Service
                       → DB-ə yaz
                       → Kafka: PostCreated event
                         → Fan-out Service:
                           Normal: follower Redis-lərini güncəllə
                           Celebrity: skip (read-time əlavə ediləcək)

Feed oxuma:
  User → GET /feed → Feed Service
                     → Redis: user_id:feed → [post_id_list]
                     → Post DB-dən post məzmunlarını al (batch)
                     → Celebrity-lərin postlarını əlavə et
                     → Birləşdir, sırala, qaytar

Storage:
  Posts: MySQL/Cassandra (post məzmunları)
  Feed: Redis Sorted Set (score = timestamp)
  Social Graph: Neo4j / MySQL (kim kimi follow edir)
  Media: S3 + CDN
```

---

## PHP İmplementasiyası

```php
<?php
// Feed Redis Sorted Set ilə
class FeedRepository
{
    private const FEED_KEY_PREFIX = 'feed:';
    private const MAX_FEED_SIZE   = 1000; // Redis-də max saxlanan post sayı

    public function __construct(private \Redis $redis) {}

    public function addToFeed(string $userId, string $postId, float $timestamp): void
    {
        $key = self::FEED_KEY_PREFIX . $userId;

        // Sorted Set: score = timestamp (yenilər yuxarıda)
        $this->redis->zAdd($key, $timestamp, $postId);

        // Feed çox böyüyərsə köhnəni sil
        $size = $this->redis->zCard($key);
        if ($size > self::MAX_FEED_SIZE) {
            $this->redis->zRemRangeByRank($key, 0, $size - self::MAX_FEED_SIZE - 1);
        }
    }

    public function getFeed(string $userId, int $offset = 0, int $limit = 20): array
    {
        $key = self::FEED_KEY_PREFIX . $userId;

        // Ən yeni postlar (yüksək score = son)
        return $this->redis->zRevRange($key, $offset, $offset + $limit - 1);
    }

    public function removeFromFeed(string $userId, string $postId): void
    {
        $key = self::FEED_KEY_PREFIX . $userId;
        $this->redis->zRem($key, $postId);
    }
}
```

```php
<?php
// Fan-out Service
class FanOutService
{
    private const CELEBRITY_THRESHOLD = 10_000; // Bu saydan çox follower = celebrity

    public function __construct(
        private FollowerRepository $followers,
        private FeedRepository     $feedRepo,
        private UserRepository     $users,
        private JobQueue           $queue,
    ) {}

    public function fanOut(string $authorId, string $postId, float $timestamp): void
    {
        $followerCount = $this->followers->countFollowers($authorId);

        if ($followerCount > self::CELEBRITY_THRESHOLD) {
            // Celebrity: fan-out etmə (pull-time əlavə ediləcək)
            return;
        }

        // Normal user: bütün follower-lara fan-out
        // Böyük say üçün batch + async
        $this->queue->publish(new FanOutJob($authorId, $postId, $timestamp));
    }
}

class FanOutWorker
{
    private const BATCH_SIZE = 500;

    public function process(FanOutJob $job): void
    {
        $cursor = null;

        do {
            // Pagination ilə follower-ları al (çox ola bilər)
            [$followers, $cursor] = $this->followers->findFollowersBatch(
                $job->authorId,
                self::BATCH_SIZE,
                $cursor,
            );

            foreach ($followers as $followerId) {
                $this->feedRepo->addToFeed($followerId, $job->postId, $job->timestamp);
            }

        } while ($cursor !== null);
    }
}
```

```php
<?php
// Feed Aggregator — hybrid approach
class FeedService
{
    private const CELEBRITY_THRESHOLD = 10_000;

    public function __construct(
        private FeedRepository $feedRepo,
        private PostRepository $posts,
        private FollowRepository $follows,
        private UserRepository $users,
    ) {}

    public function getFeed(string $userId, int $page = 1, int $size = 20): array
    {
        // 1. Pre-computed feed-dən post ID-lərini al
        $offset  = ($page - 1) * $size;
        $postIds = $this->feedRepo->getFeed($userId, $offset, $size * 2);

        // 2. Celebrity-lərin son postlarını əlavə et
        $celebrityPosts = $this->getCelebrityPosts($userId);

        // 3. Birləşdir, dublikatları aradan qaldır, timestamp-ə görə sırala
        $allPostIds = array_unique(array_merge($postIds, $celebrityPosts));

        // 4. Post məzmunlarını batch-lə al
        $posts = $this->posts->findByIds(array_slice($allPostIds, 0, $size));

        // Timestamp-ə görə sırala
        usort($posts, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return array_slice($posts, 0, $size);
    }

    private function getCelebrityPosts(string $userId): array
    {
        // Bu user-in follow etdiyi celebrity-lər
        $celebrities = $this->follows->findCelebrityFollowees(
            $userId,
            self::CELEBRITY_THRESHOLD,
        );

        $postIds = [];
        foreach ($celebrities as $celebrity) {
            $recent = $this->posts->findRecentByAuthor($celebrity->getId(), limit: 10);
            $postIds = array_merge($postIds, array_column($recent, 'id'));
        }

        return $postIds;
    }
}
```

---

## İntervyu Sualları

- Fan-out on Write vs Fan-out on Read — hər birinin üstünlüyü nədir?
- Celebrity problem nədir? Hybrid yanaşma necə həll edir?
- Redis Sorted Set feed üçün niyə uyğundur?
- Feed cache-i boş olduqda (cold start) nə baş verir?
- Post silinəndə bütün follower feed-lərindən silmək mümkündürmü?
- Instagram/Twitter hansı yanaşmanı istifadə edir?
