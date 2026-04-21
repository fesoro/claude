# Spring Data Redis və MongoDB vs Laravel — Dərin Müqayisə

## Giriş

NoSQL və cache store-ları modern backend-lərin əsas hissəsidir. **Redis** — in-memory key-value (cache, queue, pub/sub, rate limiter, leaderboard, distributed lock). **MongoDB** — document DB (JSON-bənzər schema-less data, geospatial, aggregation pipeline).

Spring tərəfində **Spring Data Redis** və **Spring Data MongoDB** ayrı modullar kimi gəlir. Hər ikisi `Template` (low-level API) + `Repository` (high-level abstraksiya) yanaşması verir. Reactive variantları da var (`ReactiveRedisTemplate`, `ReactiveMongoTemplate`).

Laravel tərəfində **Redis facade** built-in-dir (phpredis və ya predis client), cache/queue/broadcast driver kimi də istifadə olunur. **Horizon** Redis queue dashboard-ı verir. MongoDB Laravel-də native deyil — `mongodb/laravel-mongodb` paketi (Jenssegers) Eloquent-uyğun driver əlavə edir.

---

## Spring-də istifadəsi

### 1) Spring Data Redis — starter və konfiqurasiya

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis</artifactId>
</dependency>
<!-- Default client: Lettuce (reactive-ready) -->
<!-- Alternative: Jedis -->
<dependency>
    <groupId>redis.clients</groupId>
    <artifactId>jedis</artifactId>
</dependency>
```

```yaml
# application.yml
spring:
  data:
    redis:
      host: localhost
      port: 6379
      password: ${REDIS_PASSWORD}
      database: 0
      timeout: 2s
      lettuce:
        pool:
          max-active: 20
          max-idle: 10
          min-idle: 2
          max-wait: 1s
```

**Lettuce vs Jedis:**

- **Lettuce** (default) — Netty əsaslı, thread-safe (tək connection bütün thread-lərdə istifadə edilə bilər), reactive dəstəkləyir. Cluster və Sentinel üçün daha yaxşı.
- **Jedis** — daha köhnə, thread-safe deyil (pool lazım), sadədir. Bəzi production komandalar hələ də seçir.

Yeni layihələrdə Lettuce tövsiyə olunur.

### 2) RedisTemplate — low-level API

```java
@Configuration
public class RedisConfig {

    @Bean
    public RedisTemplate<String, Object> redisTemplate(RedisConnectionFactory cf) {
        RedisTemplate<String, Object> template = new RedisTemplate<>();
        template.setConnectionFactory(cf);

        // Key serializer — string
        template.setKeySerializer(new StringRedisSerializer());
        template.setHashKeySerializer(new StringRedisSerializer());

        // Value serializer — JSON
        Jackson2JsonRedisSerializer<Object> json = new Jackson2JsonRedisSerializer<>(Object.class);
        template.setValueSerializer(json);
        template.setHashValueSerializer(json);

        template.afterPropertiesSet();
        return template;
    }
}

@Service
public class CacheService {

    private final RedisTemplate<String, Object> redis;

    // String value
    public void cacheUser(Long id, User user) {
        redis.opsForValue().set("user:" + id, user, Duration.ofMinutes(10));
    }

    public Optional<User> getUser(Long id) {
        Object value = redis.opsForValue().get("user:" + id);
        return Optional.ofNullable((User) value);
    }

    // Hash
    public void updateProfile(Long id, Map<String, Object> fields) {
        redis.opsForHash().putAll("profile:" + id, fields);
        redis.expire("profile:" + id, Duration.ofHours(1));
    }

    // List — queue
    public void enqueue(String queue, String job) {
        redis.opsForList().leftPush(queue, job);
    }

    public String dequeue(String queue) {
        return (String) redis.opsForList().rightPop(queue, Duration.ofSeconds(5));
    }

    // Set
    public boolean addToSet(String key, String member) {
        return redis.opsForSet().add(key, member) > 0;
    }

    // Sorted Set — leaderboard
    public void recordScore(String game, String user, double score) {
        redis.opsForZSet().add("leaderboard:" + game, user, score);
    }

    public Set<ZSetOperations.TypedTuple<Object>> topPlayers(String game, int n) {
        return redis.opsForZSet().reverseRangeWithScores("leaderboard:" + game, 0, n - 1);
    }
}
```

### 3) StringRedisTemplate — tipik string işlər üçün

```java
@Service
public class RateLimiter {

    private final StringRedisTemplate redis;

    public boolean tryAcquire(String userId, int maxPerMinute) {
        String key = "rate:" + userId + ":" + (System.currentTimeMillis() / 60000);
        Long count = redis.opsForValue().increment(key);
        if (count == 1L) {
            redis.expire(key, Duration.ofMinutes(2));
        }
        return count <= maxPerMinute;
    }
}
```

`StringRedisTemplate` — `RedisTemplate<String, String>` altkonfiqurasiyası. Sayğac, flag, sadə cache üçün istifadə olunur.

### 4) `@Cacheable` — Spring Cache abstraction ilə Redis

```yaml
spring:
  cache:
    type: redis
    redis:
      time-to-live: 10m
      cache-null-values: false
      key-prefix: "app:"
```

```java
@Service
public class UserService {

    @Cacheable(value = "users", key = "#id", unless = "#result == null")
    public User findUser(Long id) {
        return repo.findById(id).orElse(null);
    }

    @CachePut(value = "users", key = "#user.id")
    public User updateUser(User user) {
        return repo.save(user);
    }

    @CacheEvict(value = "users", key = "#id")
    public void deleteUser(Long id) {
        repo.deleteById(id);
    }

    @CacheEvict(value = "users", allEntries = true)
    public void clearAll() {}
}
```

Bu abstraction arxa planda Redis (və ya Caffeine, EhCache) istifadə edir. Cache açarı SpEL ilə construct edilir: `#id`, `#user.id`, `#a0`.

### 5) RedisRepository — `@RedisHash`

Key-value mapping-i Hibernate entity-ə bənzər şəkildə:

```java
@RedisHash(value = "sessions", timeToLive = 3600)
public class Session {
    @Id
    private String id;

    @Indexed
    private String userId;

    private String ipAddress;
    private LocalDateTime loginAt;

    // ... getters/setters
}

public interface SessionRepository extends CrudRepository<Session, String> {
    List<Session> findByUserId(String userId);     // secondary index
}
```

Hər Session Redis-də `sessions:<id>` hash kimi yazılır. `@Indexed` ilə secondary index yaradılır (`sessions:userId:ali`). TTL Redis səviyyəsində qoyulur.

Məhdudiyyət: Redis relational DB deyil. Kompleks join, transaction yoxdur. Session, rate limit, short-lived state üçün yaxşıdır.

### 6) Pub/Sub — `RedisMessageListenerContainer`

```java
@Configuration
public class PubSubConfig {

    @Bean
    public RedisMessageListenerContainer container(RedisConnectionFactory cf,
                                                    MessageListenerAdapter adapter) {
        RedisMessageListenerContainer c = new RedisMessageListenerContainer();
        c.setConnectionFactory(cf);
        c.addMessageListener(adapter, new PatternTopic("events.*"));
        return c;
    }

    @Bean
    public MessageListenerAdapter adapter(EventSubscriber subscriber) {
        return new MessageListenerAdapter(subscriber, "onEvent");
    }
}

@Component
public class EventSubscriber {
    public void onEvent(String message, String channel) {
        log.info("channel={} msg={}", channel, message);
    }
}

// Publish
@Service
public class EventPublisher {
    private final StringRedisTemplate redis;

    public void publish(String channel, String payload) {
        redis.convertAndSend(channel, payload);
    }
}
```

Pub/Sub fire-and-forget-dir — heç kim listen etmirsə mesaj itir. Persistent mesaj üçün Redis Streams lazımdır.

### 7) Redis Streams — persistent log

```java
@Service
public class StreamService {

    private final RedisTemplate<String, String> redis;

    public RecordId publish(String stream, Map<String, String> body) {
        return redis.opsForStream().add(MapRecord.create(stream, body));
    }

    public List<MapRecord<String, String, String>> read(String stream, String lastId) {
        return redis.opsForStream()
            .range(stream, Range.rightOpen(lastId, "+"));
    }
}

// Consumer group
@Component
public class OrderStreamListener {

    @EventListener(ApplicationReadyEvent.class)
    public void init() {
        redis.opsForStream().createGroup("orders.stream", "order-processors");
    }

    @Scheduled(fixedDelay = 1000)
    public void poll() {
        List<MapRecord<String, Object, Object>> records = redis.opsForStream()
            .read(Consumer.from("order-processors", "consumer-1"),
                StreamReadOptions.empty().count(10).block(Duration.ofSeconds(2)),
                StreamOffset.create("orders.stream", ReadOffset.lastConsumed()));

        for (var r : records) {
            processOrder(r.getValue());
            redis.opsForStream().acknowledge("order-processors", r);
        }
    }
}
```

Streams Kafka-ya bənzər: ID ilə log, consumer group, ack. Laravel tərəfində də Redis Streams istifadə oluna bilir, amma built-in API yoxdur — raw command lazımdır.

### 8) Reactive Redis — `ReactiveRedisTemplate`

WebFlux tətbiqlərində:

```java
@Service
public class ReactiveCacheService {

    private final ReactiveRedisTemplate<String, User> redis;

    public Mono<User> getUser(Long id) {
        return redis.opsForValue().get("user:" + id);
    }

    public Mono<Boolean> cacheUser(Long id, User user) {
        return redis.opsForValue().set("user:" + id, user, Duration.ofMinutes(10));
    }

    public Flux<String> recentEvents() {
        return redis.opsForList().range("events:recent", 0, 99);
    }
}
```

### 9) Distributed locks — Redisson

`RedisTemplate` özündə distributed lock yoxdur. Redisson library bunu gətirir:

```xml
<dependency>
    <groupId>org.redisson</groupId>
    <artifactId>redisson-spring-boot-starter</artifactId>
    <version>3.32.0</version>
</dependency>
```

```java
@Service
public class InventoryService {

    private final RedissonClient redisson;

    public void decrementStock(Long productId, int qty) {
        RLock lock = redisson.getLock("product:lock:" + productId);
        try {
            if (lock.tryLock(5, 30, TimeUnit.SECONDS)) {
                try {
                    Product p = repo.findById(productId).orElseThrow();
                    if (p.getStock() < qty) throw new InsufficientStockException();
                    p.setStock(p.getStock() - qty);
                    repo.save(p);
                } finally {
                    lock.unlock();
                }
            } else {
                throw new LockAcquisitionException();
            }
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new RuntimeException(e);
        }
    }
}
```

Redisson həmçinin distributed rate limiter, semaphore, count-down latch, reliable queue, live objects verir.

### 10) Spring Data MongoDB — starter

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-mongodb</artifactId>
</dependency>
```

```yaml
spring:
  data:
    mongodb:
      uri: mongodb://user:pass@localhost:27017/shop?authSource=admin
      auto-index-creation: true
```

```java
@Document(collection = "products")
public class Product {
    @Id
    private String id;

    @Indexed(unique = true)
    private String sku;

    @Field("product_name")
    private String name;

    @Indexed
    private BigDecimal price;

    private List<String> categories;

    private Map<String, Object> attributes;      // dinamik schema

    @CreatedDate
    private Instant createdAt;

    @LastModifiedDate
    private Instant updatedAt;
}

public interface ProductRepository extends MongoRepository<Product, String> {
    Optional<Product> findBySku(String sku);
    List<Product> findByCategoriesContaining(String category);
    Page<Product> findByPriceBetween(BigDecimal min, BigDecimal max, Pageable pageable);

    @Query("{ 'categories': ?0, 'price': { $gte: ?1, $lte: ?2 } }")
    List<Product> search(String category, BigDecimal min, BigDecimal max);
}
```

JPA-ya bənzər API — `Repository`, derived query, `@Query`. Fərq: query MongoDB JSON syntax-ıdır.

### 11) MongoTemplate — low-level

```java
@Service
public class ProductService {

    private final MongoTemplate mongo;

    public List<Product> findInCategory(String category, BigDecimal maxPrice) {
        Query q = new Query();
        q.addCriteria(Criteria.where("categories").in(category)
            .and("price").lte(maxPrice));
        q.with(Sort.by(Sort.Direction.DESC, "createdAt"));
        q.limit(100);
        return mongo.find(q, Product.class);
    }

    public void bulkUpdate(List<String> ids, BigDecimal discount) {
        Query q = new Query(Criteria.where("_id").in(ids));
        Update u = new Update().mul("price", discount);
        mongo.updateMulti(q, u, Product.class);
    }
}
```

### 12) Aggregation pipeline

Mongo aggregation pipeline SQL GROUP BY-ə bənzər, amma daha güclüdür:

```java
public List<CategoryStats> categoryStats() {
    Aggregation agg = Aggregation.newAggregation(
        Aggregation.match(Criteria.where("price").gt(0)),
        Aggregation.unwind("categories"),
        Aggregation.group("categories")
            .count().as("productCount")
            .avg("price").as("avgPrice")
            .max("price").as("maxPrice"),
        Aggregation.project("productCount", "avgPrice", "maxPrice")
            .and("_id").as("category"),
        Aggregation.sort(Sort.Direction.DESC, "productCount"),
        Aggregation.limit(20)
    );

    return mongo.aggregate(agg, "products", CategoryStats.class).getMappedResults();
}

public record CategoryStats(String category, long productCount,
                            BigDecimal avgPrice, BigDecimal maxPrice) {}
```

Mongo aggregation stages: `$match`, `$group`, `$project`, `$sort`, `$limit`, `$lookup` (join), `$unwind`, `$bucket`, `$facet`. `Aggregation.newAggregation(...)` DSL ilə type-safe pipeline yazılır.

### 13) `@DBRef` vs embedded documents

```java
// Embedded — kiçik, birlikdə oxunan data
@Document
public class Order {
    @Id private String id;
    private List<OrderItem> items;       // embed
    private Address shippingAddress;     // embed
}

public class OrderItem {
    private String productId;
    private String name;
    private int quantity;
    private BigDecimal price;
}

// Reference — ayrı koleksiya
@Document
public class Order {
    @Id private String id;

    @DBRef(lazy = true)
    private User user;                   // users koleksiyasına reference
}
```

Mongo-da join yoxdur (amma `$lookup` aggregation var). `@DBRef` application layer-də extra query ilə yükləyir. Nə vaxt embed, nə vaxt reference?

- **Embed** — 1:1 və ya 1:few, birlikdə oxunur, dəyişmir çox
- **Reference** — 1:many (çox), ayrıca yenilənir, paylaşılan data (user order-lərdən istifadə edilir)

Rule of thumb: "bir ekran üçün hər şey bir query-də olmalıdır" — embed səmərəlidir.

### 14) Index creation — `@Indexed`, `@CompoundIndex`

```java
@Document(collection = "orders")
@CompoundIndexes({
    @CompoundIndex(name = "user_status_idx", def = "{'userId': 1, 'status': 1}"),
    @CompoundIndex(name = "created_idx", def = "{'createdAt': -1}")
})
public class Order {
    @Id private String id;

    @Indexed
    private String userId;

    @Indexed(expireAfterSeconds = 604800)  // 7 gün TTL
    private Instant createdAt;

    @TextIndexed                           // full-text search
    private String description;

    @GeoSpatialIndexed(type = GeoSpatialIndexType.GEO_2DSPHERE)
    private Point location;

    private String status;
}
```

Index kritikdir — Mongo-da düzgün index olmasa query tam koleksiyanı scan edir. `explain()` plan-ı yoxlamaq üçün:

```java
mongo.getCollection("orders").find(query).explain(Document.class);
```

### 15) Reactive MongoDB

```java
@Repository
public interface ProductRepository extends ReactiveMongoRepository<Product, String> {
    Flux<Product> findByCategoriesContaining(String category);
    Mono<Product> findBySku(String sku);
}

@Service
public class ProductService {
    public Flux<Product> search(String category) {
        return repo.findByCategoriesContaining(category)
            .filter(p -> p.getStock() > 0)
            .take(50);
    }
}
```

### 16) Change Streams

Mongo 3.6+ change streams — koleksiya dəyişikliklərini real-time izləmək:

```java
@EventListener(ApplicationReadyEvent.class)
public void watchOrders() {
    ChangeStreamRequest<Order> request = ChangeStreamRequest.builder(Order.class)
        .collection("orders")
        .filter(Aggregation.newAggregation(
            Aggregation.match(Criteria.where("operationType").is("insert"))))
        .publishTo(this::onOrderChange)
        .build();

    reactiveMongo.changeStream(Order.class, request).subscribe();
}

private void onOrderChange(ChangeStreamEvent<Order> event) {
    Order order = event.getBody();
    log.info("new order: {}", order.getId());
    notificationService.notifyUser(order.getUserId());
}
```

CDC (Change Data Capture) use-case: audit log, cache invalidation, real-time analytics.

---

## Laravel-də istifadəsi

### 1) Redis facade

```bash
composer require predis/predis
# və ya phpredis PHP extension (daha sürətli)
```

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),      // və ya 'predis'
    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
    ],
    'cache' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', 6379),
        'database' => env('REDIS_CACHE_DB', 1),
    ],
],
```

**phpredis vs predis:**

- **phpredis** — C extension, daha sürətli, PECL-dən quraşdırılır (`pecl install redis`)
- **predis** — saf PHP, composer package, quraşdırma asan. Development üçün yaxşı, production-da phpredis tövsiyə olunur

```php
use Illuminate\Support\Facades\Redis;

// String
Redis::set('user:1', json_encode($user));
Redis::setex('user:1', 600, json_encode($user));    // TTL 10 min
$user = json_decode(Redis::get('user:1'), true);

// Hash
Redis::hset('profile:1', 'name', 'Ali');
Redis::hset('profile:1', 'email', 'ali@ex.com');
$profile = Redis::hgetall('profile:1');

// List
Redis::lpush('queue:email', json_encode($job));
$job = Redis::rpop('queue:email');

// Sorted Set — leaderboard
Redis::zadd('leaderboard:chess', 1500, 'ali');
Redis::zadd('leaderboard:chess', 1800, 'veli');
$top = Redis::zrevrange('leaderboard:chess', 0, 9, 'WITHSCORES');

// Expire
Redis::expire('user:1', 3600);
Redis::ttl('user:1');
```

### 2) Cache facade — Redis driver

```php
// config/cache.php
'default' => env('CACHE_STORE', 'redis'),
'stores'  => [
    'redis' => [
        'driver'     => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// .env
CACHE_STORE=redis
```

```php
use Illuminate\Support\Facades\Cache;

// Set + TTL
Cache::put('user:1', $user, now()->addMinutes(10));

// Get
$user = Cache::get('user:1');

// Remember (get or compute + cache)
$user = Cache::remember('user:1', 600, function () {
    return User::find(1);
});

// Forever
Cache::forever('config:app', $config);

// Delete
Cache::forget('user:1');
Cache::flush();                  // bütün cache

// Tags (yalnız Redis, Memcached)
Cache::tags(['users', 'profile'])->put('user:1', $user, 600);
Cache::tags('users')->flush();   // bütün user cache

// Atomic lock
$lock = Cache::lock('process-order:1', 10);
if ($lock->get()) {
    try {
        // kritik bölmə
    } finally {
        $lock->release();
    }
}

// Block until lock
Cache::lock('key')->block(5, function () {
    // 5 saniyəyə qədər gözlə
});
```

`Cache::lock` Redis `SET NX EX` üzərində işləyir — Redisson-a ekvivalentdir (amma daha sadə, fencing token yoxdur).

### 3) Redis pipeline və transaction

```php
// Pipeline — bir neçə əmr bir network roundtrip-də
Redis::pipeline(function ($pipe) {
    for ($i = 0; $i < 1000; $i++) {
        $pipe->set("key:$i", "value:$i");
    }
});

// Transaction — MULTI/EXEC
Redis::transaction(function ($tx) {
    $tx->incr('counter');
    $tx->sadd('users:online', 'ali');
});
```

Transaction atomicdir amma rollback yoxdur — əgər EXEC arasında xəta olmadısa, hamısı icra olunur.

### 4) Queue driver — Redis

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'redis'),
'connections' => [
    'redis' => [
        'driver'      => 'redis',
        'connection'  => 'default',
        'queue'       => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for'   => 5,
    ],
],
```

```php
dispatch(new SendEmail($user));

// Worker
// php artisan queue:work redis --queue=high,default --sleep=3 --tries=3
```

Laravel queue Redis-də LIST + ZSET istifadə edir: LIST `queues:default`, ZSET `queues:default:delayed` (gələcəkdə işləyəcək job-lar), ZSET `queues:default:reserved` (işlənməkdə).

### 5) Horizon — Redis queue dashboard

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Horizon auto-scaling worker-lər, per-queue statistika, failed job retry, tag-əsaslı axtarış verir. Spring tərəfində dəqiq ekvivalenti yoxdur.

### 6) Pub/Sub

```php
// Publisher
Redis::publish('events.order.created', json_encode($order));

// Subscriber — console command
class RedisSubscribe extends Command
{
    protected $signature = 'redis:subscribe';

    public function handle(): void
    {
        Redis::subscribe(['events.*'], function ($message, $channel) {
            $this->info("$channel: $message");
        });
    }
}

// php artisan redis:subscribe
```

Subscribe blokinq-dir — proses işləyərkən başqa iş görə bilməz. Supervisor altında ayrıca uzun-ömürlü proses kimi qaldırılır.

### 7) Broadcasting driver — Redis

```php
// config/broadcasting.php
'default' => env('BROADCAST_CONNECTION', 'redis'),
'connections' => [
    'redis' => [
        'driver'     => 'redis',
        'connection' => 'default',
    ],
],
```

```php
event(new OrderShipped($order));
```

Event Redis pub/sub kanalına yazılır. Reverb (Laravel WebSocket server) subscribe edir və client-ə push edir.

### 8) Redis Streams — paket əlavəsi

Laravel Redis Streams üçün built-in API yoxdur. Raw command istifadə olunur və ya `spatie/laravel-stream-pressure` kimi paketlər:

```php
// XADD
Redis::command('XADD', ['orders.stream', '*', 'orderId', '123', 'total', '99.90']);

// XREAD
$entries = Redis::command('XREAD', [
    'COUNT', 10, 'BLOCK', 2000, 'STREAMS', 'orders.stream', '$',
]);

// Consumer group
Redis::command('XGROUP', ['CREATE', 'orders.stream', 'processors', '$', 'MKSTREAM']);
Redis::command('XREADGROUP', [
    'GROUP', 'processors', 'worker-1',
    'COUNT', 10, 'BLOCK', 2000,
    'STREAMS', 'orders.stream', '>',
]);
```

### 9) MongoDB — `mongodb/laravel-mongodb` paketi

```bash
composer require mongodb/laravel-mongodb
```

```php
// config/database.php
'connections' => [
    'mongodb' => [
        'driver'   => 'mongodb',
        'host'     => env('MONGO_HOST', '127.0.0.1'),
        'port'     => env('MONGO_PORT', 27017),
        'database' => env('MONGO_DATABASE', 'shop'),
        'username' => env('MONGO_USERNAME'),
        'password' => env('MONGO_PASSWORD'),
        'options'  => [
            'authSource' => 'admin',
        ],
    ],
],
```

```php
// app/Models/Product.php
use MongoDB\Laravel\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'products';

    protected $fillable = ['sku', 'name', 'price', 'categories', 'attributes'];

    protected $casts = [
        'price'      => 'decimal:2',
        'categories' => 'array',
        'attributes' => 'array',
    ];
}
```

```php
// Eloquent-ə bənzər — yaxşı xəbərdir
Product::where('categories', 'electronics')
    ->where('price', '<', 1000)
    ->orderBy('price', 'desc')
    ->limit(20)
    ->get();

// Mongo-specific operator
Product::whereIn('categories', ['phones', 'laptops'])
    ->where('stock', 'exists', true)
    ->where('attributes.brand', 'Apple')
    ->get();

// Full-text
Product::where('$text', ['$search' => 'iphone'])->get();
```

### 10) Aggregation Laravel-də

```php
use MongoDB\Laravel\Facades\DB;

$stats = DB::connection('mongodb')
    ->collection('products')
    ->raw(function ($collection) {
        return $collection->aggregate([
            ['$match' => ['price' => ['$gt' => 0]]],
            ['$unwind' => '$categories'],
            ['$group' => [
                '_id'          => '$categories',
                'productCount' => ['$sum' => 1],
                'avgPrice'     => ['$avg' => '$price'],
                'maxPrice'     => ['$max' => '$price'],
            ]],
            ['$sort' => ['productCount' => -1]],
            ['$limit' => 20],
        ])->toArray();
    });
```

### 11) Embedded documents

```php
class Order extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'orders';

    public function items(): EmbedsMany
    {
        return $this->embedsMany(OrderItem::class);
    }

    public function shippingAddress(): EmbedsOne
    {
        return $this->embedsOne(Address::class);
    }
}

$order = Order::find($id);
$order->items()->create([
    'productId' => 'p1',
    'quantity'  => 2,
    'price'     => 99.90,
]);
```

`embedsMany` və `embedsOne` yalnız `laravel-mongodb` paketində var — standart Eloquent-də yoxdur.

### 12) GridFS — böyük fayllar

```php
use MongoDB\GridFS\Bucket;

$bucket = DB::connection('mongodb')->getMongoClient()
    ->selectDatabase('files')
    ->selectGridFSBucket();

// Upload
$stream = $bucket->openUploadStream('report.pdf');
fwrite($stream, file_get_contents('/tmp/report.pdf'));
fclose($stream);

// Download
$stream = $bucket->openDownloadStreamByName('report.pdf');
$contents = stream_get_contents($stream);
fclose($stream);
```

### 13) Pattern: TTL cache + rate limiter + leaderboard

**TTL cache** (hər iki):

```java
// Spring
redis.opsForValue().set("user:" + id, user, Duration.ofMinutes(10));
```

```php
// Laravel
Cache::put("user:$id", $user, now()->addMinutes(10));
// və ya
Redis::setex("user:$id", 600, json_encode($user));
```

**Rate limiter — fixed window**:

```java
// Spring
public boolean allow(String userId, int max) {
    String key = "rate:" + userId + ":" + (System.currentTimeMillis() / 60000);
    Long n = redis.opsForValue().increment(key);
    if (n == 1L) redis.expire(key, Duration.ofMinutes(2));
    return n <= max;
}
```

```php
// Laravel — RateLimiter facade
use Illuminate\Support\Facades\RateLimiter;

if (RateLimiter::tooManyAttempts('api:' . $userId, 60)) {
    abort(429);
}
RateLimiter::hit('api:' . $userId, 60);     // 60 saniyəyə dekadens
```

**Leaderboard — sorted set**:

```java
// Spring
redis.opsForZSet().add("leaderboard", userId, score);
var top = redis.opsForZSet().reverseRangeWithScores("leaderboard", 0, 9);
```

```php
// Laravel
Redis::zadd('leaderboard', $score, $userId);
$top = Redis::zrevrange('leaderboard', 0, 9, 'WITHSCORES');
```

### 14) composer.json

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "laravel/horizon": "^5.30",
        "predis/predis": "^2.2",
        "mongodb/laravel-mongodb": "^5.0"
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Redis client | Lettuce (default), Jedis | phpredis (prod), predis (dev) |
| Low-level API | `RedisTemplate`, `StringRedisTemplate` | `Redis` facade |
| High-level | `@RedisHash` + Repository | Yoxdur (manuel key-based) |
| Cache abstraction | `@Cacheable`, `@CacheEvict` | `Cache` facade + `Cache::remember` |
| Cache tag | Manual | Built-in (`Cache::tags()`) |
| Pub/Sub | `RedisMessageListenerContainer` | `Redis::subscribe()` (blokinq proses) |
| Redis Streams | Built-in API (`opsForStream`) | Raw command |
| Distributed lock | Redisson (3rd party) | `Cache::lock()` built-in |
| Reactive Redis | `ReactiveRedisTemplate` | Yoxdur |
| Mongo driver | Spring Data MongoDB (rəsmi) | Community paketi (`mongodb/laravel-mongodb`) |
| Mongo Repository | `MongoRepository` + derived query | Eloquent Model extend |
| Mongo Template | `MongoTemplate` | Raw query + `DB::raw` |
| Aggregation | `Aggregation.newAggregation(...)` DSL | Raw array array pipeline |
| Embedded docs | `@Field` embed class | `embedsMany`, `embedsOne` (paket) |
| DBRef | `@DBRef` | Manual reference |
| Index | `@Indexed`, `@CompoundIndex` | Manual migration və ya `Schema::` |
| Change Streams | Built-in | Raw driver istifadəsi |
| Queue | Manual (Kafka/RabbitMQ/JMS) | Redis queue + Horizon |

---

## Niyə belə fərqlər var?

**Java-nın "template pattern" ənənəsi.** Spring-da hər şey `Template`-dir: `JdbcTemplate`, `RestTemplate`, `RedisTemplate`, `MongoTemplate`. Bu pattern low-level API + boilerplate azaldır. Laravel-də facade pattern eyni işi görür — `Redis::`, `Cache::`, `DB::`.

**Reactive variantları yalnız Spring-də.** Reactor bütün Spring Data modullarını əhatə edir. `ReactiveRedisTemplate`, `ReactiveMongoRepository` WebFlux ilə birləşir. Laravel-də reactive stream anlayışı yoxdur — Octane coroutine konkurentlik verir, amma Redis call-ları sinxrondur.

**MongoDB dəstəyi.** MongoDB Inc. Spring Data MongoDB-ni aktiv dəstəkləyir — JPA-ya bənzər API ilə. Laravel tərəfində `mongodb/laravel-mongodb` community paketidir (əvvəlcə Jenssegers, indi MongoDB Inc. rəsmi saxlayır). Eloquent-uyğun olması cəlbedicidir, amma standart Eloquent feature-lərinin hamısı işləmir (məsələn, SQL-specific migrations).

**Distributed lock.** Spring ekosisteminin lock üçün Redisson seçimi var — fencing token, reentrancy, lock pattern-lərin çoxu. Laravel `Cache::lock()` sadədir — SET NX EX üzərində. Sadə use-case-lər üçün kifayətdir, amma mürəkkəb scenario-lar (reentrancy, multi-lock) üçün Redisson sezilecek üstünlükdür.

**Cache tags.** Laravel cache tag-larını built-in verir — `Cache::tags(['users'])->flush()` bütün "users" tag-lı cache-i təmizləyir. Arxa planda Redis SET istifadə olunur. Spring `@Cacheable` tag dəstəkləmir — bütün cache-i flush etmək və ya ayrı-ayrı key-lərlə işləmək lazımdır.

**Horizon.** Laravel Horizon UI-dan auto-scaling, throughput izləmə verir. Spring-də bunun tam ekvivalenti yoxdur — Actuator + Grafana ilə dashboard qurulur. Queue dashboard üçün Laravel üstünlüyə malikdir.

**Redis Streams.** Spring Data Redis-də `opsForStream()` birinci dərəcəli dəstəkləyir — `add`, `range`, consumer group. Laravel-də raw command istifadə olunmalıdır. Enterprise use-case-lərdə (event sourcing, CDC) bu fərq önəmli ola bilər.

**`@RedisHash` vs manual.** Spring `@RedisHash` ilə POJO-ları hash kimi saxlayır, secondary index, TTL avtomatikdir. Laravel-də bu abstraction yoxdur — hər şey manualdır. Sadə cache üçün fərq yoxdur, amma structured data üçün Spring daha elegantdır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `@RedisHash` + Repository abstraction (Redis-də entity-bənzər)
- `ReactiveRedisTemplate`, `ReactiveMongoTemplate` — reactive variant
- Redis Streams built-in API (`opsForStream`)
- MongoDB Change Streams (reactive)
- `Aggregation.newAggregation(...)` DSL — type-safe pipeline
- `@DBRef` lazy reference
- `@Indexed`, `@CompoundIndex`, `@TextIndexed`, `@GeoSpatialIndexed` annotations
- `@CreatedDate`, `@LastModifiedDate` Mongo-da audit
- `MongoRepository` derived query (`findByCategoriesContaining`)
- `@Cacheable`, `@CachePut`, `@CacheEvict` AOP cache
- Redisson ilə güclü distributed lock, semaphore, reliable queue
- Lettuce reactive client

**Yalnız Laravel-də (və ya daha sadə):**
- `Cache::tags()` — cache tag-ları built-in
- `Cache::lock()` — sadə distributed lock
- `Cache::remember()` — get-or-compute pattern tək sətirdə
- Horizon — Redis queue dashboard (auto-scaling, throughput)
- `RateLimiter` facade — sadə rate limit API
- `Redis::pipeline()` closure syntax
- Built-in Redis queue driver + tags + delayed jobs
- Broadcasting Redis driver + Reverb inteqrasiyası
- Eloquent-uyğun Mongo model (`embedsMany`, `embedsOne`)
- Queue failure jobs table + retry UI
- `predis` vs `phpredis` seçim asan

---

## Best Practices

**Spring — Redis:**
- Production-da Lettuce istifadə edin (thread-safe, reactive-ready)
- `RedisTemplate`-i bir dəfə `@Bean` kimi konfiqurasiya edin — JSON serializer-lə
- `@Cacheable` key SpEL-də `#a0`, `#methodName` kimi ifadələrdən çəkinin — aydın `key = "#id"` yazın
- TTL həmişə qoyun — heç vaxt "forever" key yaratmayın (infinite memory risk)
- Atomic əməliyyatlar üçün `increment`, `setIfAbsent` istifadə edin
- Distributed lock-da Redisson seçin — DIY lock-da subtle bug-lar olur
- Redis cluster-də `{tag}` key tagging ilə hash slot-u birləşdirin
- Cache stampede üçün double-check pattern və ya `@Cacheable(sync = true)`
- Metrics: cache hit rate, latency, connection pool usage (Micrometer)

**Spring — MongoDB:**
- `auto-index-creation: false` production-da — index-ləri migration ilə yaradın
- `explain()` ilə query plan yoxlayın — COLLSCAN-dan qorunun
- Embedded vs reference qərarını "bir səhifə = bir query" prinsipilə verin
- Aggregation pipeline-ı çoxsaylı stage-ə bölün, oxunaqlılıq artır
- `$match` pipeline-ın başında olsun — sonrakı stage-lər az data ilə işləsin
- `BigDecimal` əvəzinə `Decimal128` seçin (native Mongo dəqiq rəqəm)
- `@DBRef(lazy = true)` istifadə edin — performans üçün
- Change Streams consumer-ı reliable saxlayın — resume token DB-də saxlayın

**Laravel — Redis:**
- Production-da phpredis (C extension), dev-də predis
- `Cache::remember` — manuel `get`/`set` əvəzinə
- `Cache::tags` yalnız Redis/Memcached-də işləyir
- `Cache::lock` + `block()` — lock acquisition timeout həmişə qoyun
- `Redis::pipeline` ilə bulk əməliyyatları batch edin (1000-lik chunk-lar)
- Queue driver üçün ayrı Redis DB seçin (`REDIS_QUEUE_DB=2`)
- Horizon-u supervisor altında qaldırın — restart lazım olduğu üçün
- Broadcast Redis + Reverb üçün həmişə persistent connection
- `Redis::subscribe` blokinq-dir — ayrıca Artisan command + supervisor

**Laravel — MongoDB:**
- `laravel-mongodb` paketi aktiv saxlanılır (indi MongoDB Inc. rəsmi)
- Eloquent query çoxu işləyir, amma `whereHas` ilə əlaqə məhduddur
- Migration əvəzinə `Schema::connection('mongodb')->create()` — index-lər üçün
- Embedded documents Eloquent relation-lardan fərqlidir — `save()` mexanizmi ayrıdır
- Full-text search üçün `$text` operator + text index qabaqcadan yaradılsın
- GridFS böyük fayllar üçün — 16MB-dan böyük documents direct saxlana bilməz
- `DB::raw()` ilə aggregation — Eloquent aggregation limit-lidir

---

## Yekun

Spring Data Redis və MongoDB güclü, standartlaşmış, reactive variant da təqdim edən modullardır. `RedisTemplate`, `MongoTemplate`, Repository pattern, Aggregation DSL, Change Streams hamısı enterprise-hazırdır. Redisson distributed lock və coordination üçün sənaye standartıdır. Öyrənmə əyrisi var, amma kompleks scenario-larda (reactive streams, distributed locks, audit, change streams) çox dəyərlidir.

Laravel Redis tərəfində çox sadə və pragmatic-dir — `Cache::tags`, `Cache::lock`, Horizon dashboard, built-in queue driver. MongoDB üçün `laravel-mongodb` paketi Eloquent API-ni Mongo-ya gətirir, amma bəzi feature-lər (embed, change streams) paket-spesifikdir. Cache tag-ları və Horizon Spring-də tam ekvivalenti olmayan güclü üstünlüklərdir.

Seçim qaydası: **reactive streams, güclü distributed coordination, MongoDB native integration, enterprise audit** — Spring. **Sadə cache + queue + broadcasting, sürətli development, Horizon dashboard, Eloquent-bənzər Mongo API** — Laravel. Hər iki ekosistem production-da miqyas edə bilir, sadəcə complexity vs abstraction tradeoff-u fərqli yerlərdədir. Əsas tövsiyə: cache key-də TTL məcburi, distributed lock-da timeout məcburi, Mongo-da index plan tərtibatın ilk addımıdır.
