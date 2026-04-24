# 064 — Spring Data Redis — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Redis nədir?](#redis-nədir)
2. [Spring Data Redis konfiqurasiyası](#spring-data-redis-konfiqurasiyası)
3. [RedisTemplate](#redistemplate)
4. [Spring Cache ilə Redis](#spring-cache-ilə-redis)
5. [Redis data strukturları](#redis-data-strukturları)
6. [Pub/Sub](#pubsub)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Redis nədir?

**Redis** — in-memory key-value data store. Cache, session store, pub/sub, rate limiting, distributed lock kimi istifadə olunur.

```
Redis istifadə sahələri:
  ├── Cache — DB sorğularını cache-ləmək (latency azaltmaq)
  ├── Session — user session saxlamaq
  ├── Rate Limiting — API çağırış limitləmə
  ├── Pub/Sub — event broadcasting
  ├── Distributed Lock — çox instance arasında lock
  ├── Leaderboard — sorted set ilə sıralama
  └── Counter — atomic increment/decrement

Niyə Redis sürətlidir?
  → In-memory — RAM-da saxlanılır
  → Single-threaded I/O model — context switching yoxdur
  → Efficient data structures — O(1) əməliyyatlar
  → Persistence optional — RDB snapshot + AOF log
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis</artifactId>
</dependency>
<!-- Connection pool üçün -->
<dependency>
    <groupId>org.apache.commons</groupId>
    <artifactId>commons-pool2</artifactId>
</dependency>
```

---

## Spring Data Redis konfiqurasiyası

```yaml
# application.yml
spring:
  data:
    redis:
      host: localhost
      port: 6379
      password: ""          # Şifrəsiz olduqda boş
      database: 0           # DB seçimi (0-15)
      timeout: 6000ms       # Bağlantı timeout
      connect-timeout: 6000ms

      # Lettuce (default) connection pool
      lettuce:
        pool:
          max-active: 16    # Maksimum aktiv bağlantı
          max-idle: 8       # Maksimum idle
          min-idle: 4       # Minimum idle
          max-wait: -1ms    # -1 = məhdudsuz gözlə

      # Redis Cluster
      cluster:
        nodes:
          - redis-node1:6379
          - redis-node2:6379
          - redis-node3:6379
        max-redirects: 3

      # Redis Sentinel (HA)
      sentinel:
        master: mymaster
        nodes:
          - sentinel1:26379
          - sentinel2:26379
```

```java
// ─── Redis konfiqurasiya sinfi ────────────────────────
@Configuration
@EnableRedisRepositories
public class RedisConfig {

    @Bean
    public RedisTemplate<String, Object> redisTemplate(
            RedisConnectionFactory connectionFactory) {

        RedisTemplate<String, Object> template = new RedisTemplate<>();
        template.setConnectionFactory(connectionFactory);

        // Serializer-lər
        Jackson2JsonRedisSerializer<Object> jsonSerializer =
            new Jackson2JsonRedisSerializer<>(Object.class);

        ObjectMapper mapper = new ObjectMapper();
        mapper.activateDefaultTyping(
            mapper.getPolymorphicTypeValidator(),
            ObjectMapper.DefaultTyping.NON_FINAL
        );
        jsonSerializer.setObjectMapper(mapper);

        // Key → String
        template.setKeySerializer(new StringRedisSerializer());
        template.setHashKeySerializer(new StringRedisSerializer());

        // Value → JSON
        template.setValueSerializer(jsonSerializer);
        template.setHashValueSerializer(jsonSerializer);

        template.afterPropertiesSet();
        return template;
    }

    @Bean
    public StringRedisTemplate stringRedisTemplate(
            RedisConnectionFactory connectionFactory) {
        return new StringRedisTemplate(connectionFactory);
    }

    // Cache Manager konfiqurasiyası
    @Bean
    public RedisCacheManager cacheManager(
            RedisConnectionFactory connectionFactory) {

        RedisCacheConfiguration defaultConfig = RedisCacheConfiguration
            .defaultCacheConfig()
            .entryTtl(Duration.ofMinutes(30))
            .serializeKeysWith(
                RedisSerializationContext.SerializationPair
                    .fromSerializer(new StringRedisSerializer()))
            .serializeValuesWith(
                RedisSerializationContext.SerializationPair
                    .fromSerializer(new Jackson2JsonRedisSerializer<>(Object.class)))
            .disableCachingNullValues();

        Map<String, RedisCacheConfiguration> cacheConfigs = Map.of(
            "orders", defaultConfig.entryTtl(Duration.ofMinutes(5)),
            "products", defaultConfig.entryTtl(Duration.ofHours(1)),
            "users", defaultConfig.entryTtl(Duration.ofMinutes(15))
        );

        return RedisCacheManager.builder(connectionFactory)
            .cacheDefaults(defaultConfig)
            .withInitialCacheConfigurations(cacheConfigs)
            .build();
    }
}
```

---

## RedisTemplate

```java
@Service
public class OrderCacheService {

    private final RedisTemplate<String, Object> redisTemplate;
    private final StringRedisTemplate stringRedisTemplate;

    // ─── String / Value əməliyyatları ────────────────
    public void cacheOrder(Order order) {
        String key = "order:" + order.getId();
        redisTemplate.opsForValue().set(key, order, Duration.ofMinutes(30));
    }

    public Optional<Order> getCachedOrder(Long orderId) {
        String key = "order:" + orderId;
        Order order = (Order) redisTemplate.opsForValue().get(key);
        return Optional.ofNullable(order);
    }

    // TTL ilə əməliyyatlar
    public void cacheWithTtl(String key, Object value, Duration ttl) {
        redisTemplate.opsForValue().set(key, value, ttl);
    }

    // TTL almaq
    public Long getRemainingTtl(String key) {
        return redisTemplate.getExpire(key, TimeUnit.SECONDS);
    }

    // Sil
    public void evict(String key) {
        redisTemplate.delete(key);
    }

    // Pattern ilə sil (⚠ production-da diqqətli ol — keys* performans problemi)
    public void evictByPattern(String pattern) {
        Set<String> keys = redisTemplate.keys(pattern);
        if (keys != null && !keys.isEmpty()) {
            redisTemplate.delete(keys);
        }
    }

    // ─── Atomic operations ────────────────────────────
    public Long incrementCounter(String key) {
        return redisTemplate.opsForValue().increment(key);
    }

    public Long decrementCounter(String key) {
        return redisTemplate.opsForValue().decrement(key);
    }

    // getAndSet — atomic
    public Object getAndSet(String key, Object newValue) {
        return redisTemplate.opsForValue().getAndSet(key, newValue);
    }

    // Set if absent — distributed lock üçün
    public Boolean setIfAbsent(String key, Object value, Duration ttl) {
        return redisTemplate.opsForValue().setIfAbsent(key, value, ttl);
    }

    // ─── Hash əməliyyatları ───────────────────────────
    public void cacheOrderFields(Long orderId, Order order) {
        String key = "order:hash:" + orderId;
        redisTemplate.opsForHash().put(key, "status", order.getStatus());
        redisTemplate.opsForHash().put(key, "amount", order.getTotalAmount());
        redisTemplate.opsForHash().put(key, "customerId", order.getCustomerId());
        redisTemplate.expire(key, Duration.ofMinutes(30));
    }

    public String getOrderStatus(Long orderId) {
        return (String) redisTemplate.opsForHash()
            .get("order:hash:" + orderId, "status");
    }

    public Map<Object, Object> getAllOrderFields(Long orderId) {
        return redisTemplate.opsForHash().entries("order:hash:" + orderId);
    }
}
```

---

## Spring Cache ilə Redis

```java
// ─── @EnableCaching ────────────────────────────────────
@SpringBootApplication
@EnableCaching
public class Application { }

// ─── @Cacheable ───────────────────────────────────────
@Service
public class OrderService {

    // İlk çağırışda DB-dən oxuyur, Redis-ə yazır
    // Sonrakı çağırışlarda Redis-dən qaytarır
    @Cacheable(value = "orders", key = "#orderId")
    public Order findById(Long orderId) {
        log.info("DB-dən oxunur: {}", orderId);
        return orderRepository.findById(orderId).orElseThrow();
    }

    // Şərtli cache
    @Cacheable(value = "orders", key = "#orderId",
               condition = "#orderId > 0",
               unless = "#result.status == 'CANCELLED'")
    public Order findByIdIfActive(Long orderId) {
        return orderRepository.findById(orderId).orElseThrow();
    }

    // ─── @CachePut — həmişə yazır, cache-i yeniləyir
    @CachePut(value = "orders", key = "#order.id")
    public Order updateOrder(Order order) {
        Order updated = orderRepository.save(order);
        return updated; // Redis-ə yenilənmiş versiya yazılır
    }

    // ─── @CacheEvict — cache-i silir
    @CacheEvict(value = "orders", key = "#orderId")
    public void deleteOrder(Long orderId) {
        orderRepository.deleteById(orderId);
    }

    // Hamısını sil
    @CacheEvict(value = "orders", allEntries = true)
    public void clearAllOrderCache() {
        // Bütün "orders" cache silinir
    }

    // ─── @Caching — bir neçə cache annotation ────────
    @Caching(
        evict = {
            @CacheEvict(value = "orders", key = "#order.id"),
            @CacheEvict(value = "customerOrders", key = "#order.customerId")
        },
        put = {
            @CachePut(value = "orders", key = "#result.id")
        }
    )
    public Order confirmOrder(Order order) {
        order.setStatus(OrderStatus.CONFIRMED);
        return orderRepository.save(order);
    }
}

// ─── @CacheConfig — sinif səviyyəsindəki default ─────
@Service
@CacheConfig(cacheNames = "products")
public class ProductService {

    @Cacheable  // "products" cache istifadə edilir
    public Product findById(Long id) {
        return productRepository.findById(id).orElseThrow();
    }

    @CacheEvict
    public void delete(Long id) {
        productRepository.deleteById(id);
    }
}
```

---

## Redis data strukturları

```java
@Service
public class RedisDataStructuresService {

    private final RedisTemplate<String, Object> redis;

    // ─── List — queue/stack ───────────────────────────
    public void addToQueue(String queueKey, String item) {
        redis.opsForList().rightPush(queueKey, item); // Sona əlavə
        redis.opsForList().leftPush(queueKey, item);  // Başa əlavə
    }

    public String pollFromQueue(String queueKey) {
        return (String) redis.opsForList().leftPop(queueKey); // Başdan götür
    }

    public List<Object> getAll(String key) {
        return redis.opsForList().range(key, 0, -1); // Hamısını götür
    }

    // ─── Set — unique values ──────────────────────────
    public void addToActiveUsers(String userId) {
        redis.opsForSet().add("active:users", userId);
        redis.expire("active:users", Duration.ofMinutes(5));
    }

    public Boolean isUserActive(String userId) {
        return redis.opsForSet().isMember("active:users", userId);
    }

    public Set<Object> getActiveUsers() {
        return redis.opsForSet().members("active:users");
    }

    // Set intersection — iki istifadəçinin ortaq orderleri
    public Set<Object> getCommonOrders(String user1, String user2) {
        return redis.opsForSet().intersect(
            "user:orders:" + user1,
            "user:orders:" + user2
        );
    }

    // ─── Sorted Set — leaderboard / priority queue ────
    public void addToLeaderboard(String playerId, double score) {
        redis.opsForZSet().add("leaderboard", playerId, score);
    }

    public Set<Object> getTopPlayers(int count) {
        return redis.opsForZSet().reverseRange("leaderboard", 0, count - 1);
    }

    public Long getPlayerRank(String playerId) {
        Long rank = redis.opsForZSet().reverseRank("leaderboard", playerId);
        return rank != null ? rank + 1 : null; // 1-indexed
    }

    // ─── Rate Limiting ────────────────────────────────
    public boolean isRateLimited(String userId) {
        String key = "rate:limit:" + userId;
        Long count = redis.opsForValue().increment(key);

        if (count == 1) {
            redis.expire(key, Duration.ofMinutes(1)); // İlk request-də TTL qoy
        }

        return count > 100; // Dəqiqədə 100-dən çox
    }

    // ─── Distributed Lock ─────────────────────────────
    public boolean tryLock(String lockKey, String lockValue, Duration timeout) {
        // SET key value NX EX seconds — atomic
        return Boolean.TRUE.equals(
            redis.opsForValue().setIfAbsent(lockKey, lockValue, timeout)
        );
    }

    public boolean releaseLock(String lockKey, String expectedValue) {
        // Lua script ilə atomic check-and-delete
        String script = """
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
            """;

        Long result = redis.execute(
            new DefaultRedisScript<>(script, Long.class),
            List.of(lockKey),
            expectedValue
        );
        return Long.valueOf(1).equals(result);
    }
}
```

---

## Pub/Sub

```java
// ─── Publisher ────────────────────────────────────────
@Service
public class OrderEventPublisher {

    private final RedisTemplate<String, Object> redisTemplate;

    public void publishOrderCreated(Order order) {
        OrderCreatedEvent event = new OrderCreatedEvent(
            order.getId(),
            order.getCustomerId(),
            order.getTotalAmount(),
            Instant.now()
        );

        redisTemplate.convertAndSend("order.created", event);
    }

    public void publishOrderStatusChanged(Long orderId, OrderStatus status) {
        redisTemplate.convertAndSend(
            "order.status.changed",
            Map.of("orderId", orderId, "status", status.name())
        );
    }
}

// ─── Subscriber ───────────────────────────────────────
@Component
public class OrderEventSubscriber implements MessageListener {

    @Override
    public void onMessage(Message message, byte[] pattern) {
        String channel = new String(message.getChannel());
        String body = new String(message.getBody());

        log.info("Redis mesajı alındı. Channel: {}, Body: {}", channel, body);

        // Process
    }
}

// ─── Listener konfiqurasiyası ─────────────────────────
@Configuration
public class RedisSubscriberConfig {

    @Bean
    public RedisMessageListenerContainer listenerContainer(
            RedisConnectionFactory connectionFactory,
            OrderEventSubscriber subscriber) {

        RedisMessageListenerContainer container =
            new RedisMessageListenerContainer();
        container.setConnectionFactory(connectionFactory);

        // Channel subscription
        container.addMessageListener(subscriber,
            new ChannelTopic("order.created"));

        container.addMessageListener(subscriber,
            new ChannelTopic("order.status.changed"));

        // Pattern subscription (wildcard)
        container.addMessageListener(subscriber,
            new PatternTopic("order.*"));

        return container;
    }
}
```

---

## İntervyu Sualları

### 1. Redis hansı data strukturları dəstəkləyir?
**Cavab:** String (text, sayğac, binary), List (queue/stack, ordered collection), Set (unique values, set operations), Sorted Set (score ilə sıralı, leaderboard, priority queue), Hash (object field-lər, efisient saxlama), Bitmap (bit array, user activity tracking), HyperLogLog (approximate unique count), Stream (log/event stream, Kafka alternativ).

### 2. @Cacheable necə işləyir?
**Cavab:** İlk çağırışda — cache miss → metod icra edilir → nəticə Redis-ə yazılır → client-ə qaytarılır. Sonrakı çağırışlarda — cache hit → Redis-dən oxunur → metod icra edilmır. Cache key SpEL ilə müəyyən edilir (`#orderId`). TTL konfiqurasiyada təyin edilir. `unless` şərti `true` olduqda cache-ə yazılmır (məs: null nəticə).

### 3. Redis distributed lock necə işləyir?
**Cavab:** `SET key value NX EX seconds` — atomic əməliyyat: key mövcud deyilsə yaz + TTL qoy. `NX` (Not eXists) atomikliyi zəmanət edir — iki process eyni anda lock almaq üçün yarışanda yalnız biri `true` alır. Unlock üçün Lua script — key-in öz prosesə aid olduğunu yoxlayıb silir (atomik check-and-delete). TTL — proses crash olduqda lock avtomatik silinir.

### 4. Redis Pub/Sub-u Kafka ilə müqayisə edin.
**Cavab:** Redis Pub/Sub — fire-and-forget, mesaj saxlanmır, subscriber yoxdursa mesaj itirilir, at-most-once. Kafka — mesaj saxlanır (disk persistence), consumer group, replay, at-least-once/exactly-once. Redis: sadə event notification, real-time broadcast, session invalidation. Kafka: event sourcing, audit log, qorunmayan əhəmiyyətli mesajlar. Redis Streams (XADD/XREAD) Kafka-ya daha oxşar — mesaj saxlanır.

### 5. Cache invalidation strategiyaları?
**Cavab:** (1) **TTL-based** — müddət bitdikdə avtomatik silinir, sadə amma stale data riski. (2) **Write-through** — DB yazıldıqda eyni zamanda cache yenilənir (`@CachePut`), ən fresh, yazı overhead. (3) **Cache-aside** — `@Cacheable`: cache miss-də DB-dən oxu, cache-ə yaz. (4) **Event-driven** — entity dəyişdikdə Kafka/Redis event → cache evict. (5) **@CacheEvict** — silmə əməliyyatında açıq cache silmə. Spring Cache annotation-ları bu strategiyaları abstrakt edir.

*Son yenilənmə: 2026-04-10*
