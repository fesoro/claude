# Caching — in-memory, Redis, cache strategy-lər (Lead)

## İcmal

Cache — database/API yükünü azaltmaq, latency-ni düşürmək üçün tez-tez istifadə edilən məlumatı yaddaşda saxlamaq üsuludur. Go-da in-memory cache üçün `sync.Map`, `sync.RWMutex` + map, `github.com/allegro/bigcache` istifadə edilir. Distributed cache üçün Redis `github.com/redis/go-redis/v9` paketi ilə işlənir.

Laravel-dəki `Cache::remember()` sadə sintaksis verir, amma Go-da cache strategy-ləri daha çox nəzarət imkanı ilə qurulur.

## Niyə Vacibdir

- N+1 sorğu problemi cache ilə həll oluna bilər
- Database connection pool-u əsas sorğular üçün azad edir
- API rate limit-ini keçməmək üçün external API cavablarını cache et
- Session, JWT black list, rate limit counter-ları Redis-də saxla
- p99 latency-ni əhəmiyyətli dərəcədə aşağı salır

## Əsas Anlayışlar

### Cache strategy-lər

| Strategy | Necə işləyir | Nə vaxt |
|----------|-------------|---------|
| Cache-Aside | App özü cache idarə edir | Ən çox istifadə edilən |
| Read-Through | Cache özü DB-dən oxuyur | Library dəstəkləyirsə |
| Write-Through | Yazma eyni anda cache + DB | Davamlılıq kritikdirsə |
| Write-Behind | Cache-ə yaz, arxa planda DB | Yüksək yazma yükü |
| Refresh-Ahead | TTL bitməzdən əvvəl yenilə | Yüksək oxuma yükü |

### Eviction policy-lər

```
LRU (Least Recently Used)  — ən az yaxın zamanda istifadə olunan silinir
LFU (Least Frequently Used)— ən az istifadə olunan silinir
TTL (Time To Live)         — müddəti bitən silinir
FIFO                       — ilk giren, ilk çıxan
```

### Cache stampede / thundering herd

TTL bitdikdə yüzlərlə goroutine eyni vaxtda DB-ə sorğu göndərə bilər. Həll: singleflight pattern.

## Praktik Baxış

### Nə vaxt in-memory, nə vaxt Redis?

```
Bir server, lokal state       → in-memory (sync.Map, bigcache)
Bir neçə server, paylaşılan  → Redis
Session, black list           → Redis (TTL + persistent)
Hesablama nəticəsi, read-heavy → in-memory (Redis replica mümkün)
```

### Trade-off-lar

- In-memory: server restart-da silinir, çox server arasında paylaşılmır
- Redis: şəbəkə gecikmə əlavə edir (~0.5-2ms), amma distributed
- `sync.Map`: oxuma çox olduqda RWMutex-dən sürətli; yazma çox olduqda yavaş
- bigcache: GC-dən azad (off-heap), amma yalnız `[]byte` saxlayır

### Anti-pattern-lər

```go
// YANLIŞ: cache invalidation yoxdur
func GetUser(id int) User {
    if v, ok := cache[id]; ok {
        return v
    }
    u := db.FindUser(id)
    cache[id] = u // heç vaxt silinmir → stale data!
    return u
}

// YANLIŞ: cache key-i unique etmirsiniz
cache.Set("user", user) // bütün user-lər eyni key!
cache.Set(fmt.Sprintf("user:%d", id), user) // DOĞRU

// YANLIŞ: hot key — bütün sorğular bir cache key-ə düşür
// Redis-in bir shard-u overload olur
// Həll: local cache + Redis, key sharding
```

### Cache warming

Deployment-dan sonra cache soyuq olduğunda ("cold start") trafik birbaşa DB-ə düşür. Həll: deployment zamanı kritik məlumatları əvvəlcədən cache-ə yüklə.

## Nümunələr

### Nümunə 1: Generik TTL Cache

```go
package main

import (
    "fmt"
    "sync"
    "time"
)

type entry[V any] struct {
    value     V
    expiresAt time.Time
}

func (e entry[V]) expired() bool {
    return !e.expiresAt.IsZero() && time.Now().After(e.expiresAt)
}

// Cache — type-safe generik TTL cache
type Cache[K comparable, V any] struct {
    mu      sync.RWMutex
    data    map[K]entry[V]
    onEvict func(K, V)
}

func NewCache[K comparable, V any](opts ...func(*Cache[K, V])) *Cache[K, V] {
    c := &Cache[K, V]{data: make(map[K]entry[V])}
    for _, opt := range opts {
        opt(c)
    }
    return c
}

func WithEvictCallback[K comparable, V any](fn func(K, V)) func(*Cache[K, V]) {
    return func(c *Cache[K, V]) { c.onEvict = fn }
}

func (c *Cache[K, V]) Set(key K, value V, ttl time.Duration) {
    c.mu.Lock()
    defer c.mu.Unlock()

    var exp time.Time
    if ttl > 0 {
        exp = time.Now().Add(ttl)
    }
    c.data[key] = entry[V]{value: value, expiresAt: exp}
}

func (c *Cache[K, V]) Get(key K) (V, bool) {
    c.mu.RLock()
    e, ok := c.data[key]
    c.mu.RUnlock()

    if !ok || e.expired() {
        if ok { // keçmiş entry-ni sil
            c.mu.Lock()
            if e2, ok2 := c.data[key]; ok2 && e2.expired() {
                delete(c.data, key)
                if c.onEvict != nil {
                    go c.onEvict(key, e.value)
                }
            }
            c.mu.Unlock()
        }
        var zero V
        return zero, false
    }
    return e.value, true
}

func (c *Cache[K, V]) Delete(key K) {
    c.mu.Lock()
    defer c.mu.Unlock()
    delete(c.data, key)
}

func (c *Cache[K, V]) Len() int {
    c.mu.RLock()
    defer c.mu.RUnlock()
    return len(c.data)
}

// Cleanup — expired entry-ləri sil
func (c *Cache[K, V]) StartCleanup(interval time.Duration) (stop func()) {
    ticker := time.NewTicker(interval)
    done := make(chan struct{})

    go func() {
        for {
            select {
            case <-ticker.C:
                c.mu.Lock()
                for k, e := range c.data {
                    if e.expired() {
                        if c.onEvict != nil {
                            go c.onEvict(k, e.value)
                        }
                        delete(c.data, k)
                    }
                }
                c.mu.Unlock()
            case <-done:
                ticker.Stop()
                return
            }
        }
    }()

    return func() { close(done) }
}

func main() {
    type User struct{ Name string; Email string }

    cache := NewCache[int, User](
        WithEvictCallback[int, User](func(id int, u User) {
            fmt.Printf("Evict: user %d (%s)\n", id, u.Name)
        }),
    )
    stop := cache.StartCleanup(30 * time.Second)
    defer stop()

    cache.Set(1, User{Name: "Orkhan", Email: "o@example.com"}, 5*time.Minute)
    cache.Set(2, User{Name: "Əli", Email: "ali@example.com"}, 1*time.Second)

    if u, ok := cache.Get(1); ok {
        fmt.Println("Tapıldı:", u.Name)
    }

    time.Sleep(2 * time.Second)
    if _, ok := cache.Get(2); !ok {
        fmt.Println("User 2 vaxtı bitdi")
    }
}
```

### Nümunə 2: Singleflight — cache stampede qorunması

```go
package main

import (
    "context"
    "fmt"
    "sync"
    "time"

    "golang.org/x/sync/singleflight"
)

type ProductService struct {
    cache  *Cache[int, Product]
    sf     singleflight.Group
    db     ProductDB
}

type Product struct {
    ID    int
    Name  string
    Price float64
}

type ProductDB interface {
    FindByID(ctx context.Context, id int) (*Product, error)
}

type mockDB struct{}

func (m *mockDB) FindByID(_ context.Context, id int) (*Product, error) {
    time.Sleep(100 * time.Millisecond) // DB gecikmə simulyasiyası
    return &Product{ID: id, Name: fmt.Sprintf("Məhsul-%d", id), Price: float64(id * 10)}, nil
}

func NewProductService(db ProductDB) *ProductService {
    c := NewCache[int, Product]()
    c.StartCleanup(5 * time.Minute)
    return &ProductService{cache: c, db: db}
}

func (s *ProductService) GetProduct(ctx context.Context, id int) (*Product, error) {
    // 1. Cache yoxla
    if p, ok := s.cache.Get(id); ok {
        return &p, nil
    }

    // 2. Singleflight: eyni key üçün yalnız bir DB sorğusu
    key := fmt.Sprintf("product:%d", id)
    val, err, _ := s.sf.Do(key, func() (interface{}, error) {
        // Bu funksiya eyni anda yalnız bir dəfə işləyir
        p, err := s.db.FindByID(ctx, id)
        if err != nil {
            return nil, err
        }
        s.cache.Set(id, *p, 5*time.Minute)
        return p, nil
    })
    if err != nil {
        return nil, err
    }

    return val.(*Product), nil
}

func main() {
    svc := NewProductService(&mockDB{})

    var wg sync.WaitGroup
    start := time.Now()

    // 100 eyni anda sorğu — yalnız 1 DB sorğusu olacaq
    for i := 0; i < 100; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            p, err := svc.GetProduct(context.Background(), 42)
            if err != nil {
                fmt.Println("Xəta:", err)
            }
            _ = p
        }()
    }

    wg.Wait()
    fmt.Printf("100 sorğu tamamlandı: %v\n", time.Since(start).Round(time.Millisecond))
    // ~100ms — sanki bir DB sorğusu
}
```

### Nümunə 3: Redis ilə cache (go-redis)

```go
package main

import (
    "context"
    "encoding/json"
    "fmt"
    "time"

    "github.com/redis/go-redis/v9"
)

type RedisCache struct {
    client *redis.Client
    prefix string
}

func NewRedisCache(addr, password, prefix string, db int) *RedisCache {
    client := redis.NewClient(&redis.Options{
        Addr:         addr,
        Password:     password,
        DB:           db,
        DialTimeout:  5 * time.Second,
        ReadTimeout:  3 * time.Second,
        WriteTimeout: 3 * time.Second,
        PoolSize:     10,
        MinIdleConns: 5,
    })
    return &RedisCache{client: client, prefix: prefix}
}

func (c *RedisCache) key(k string) string {
    return c.prefix + ":" + k
}

func (c *RedisCache) Set(ctx context.Context, key string, value interface{}, ttl time.Duration) error {
    data, err := json.Marshal(value)
    if err != nil {
        return fmt.Errorf("serializasiya: %w", err)
    }
    return c.client.Set(ctx, c.key(key), data, ttl).Err()
}

func (c *RedisCache) Get(ctx context.Context, key string, dest interface{}) error {
    data, err := c.client.Get(ctx, c.key(key)).Bytes()
    if err != nil {
        if err == redis.Nil {
            return fmt.Errorf("cache miss: %s", key)
        }
        return fmt.Errorf("Redis oxuma: %w", err)
    }
    return json.Unmarshal(data, dest)
}

func (c *RedisCache) Delete(ctx context.Context, keys ...string) error {
    rkeys := make([]string, len(keys))
    for i, k := range keys {
        rkeys[i] = c.key(k)
    }
    return c.client.Del(ctx, rkeys...).Err()
}

// Remember — cache-aside pattern helper
func Remember[T any](ctx context.Context, cache *RedisCache, key string, ttl time.Duration, fetch func() (T, error)) (T, error) {
    var result T
    err := cache.Get(ctx, key, &result)
    if err == nil {
        return result, nil // cache hit
    }

    // cache miss — DB-dən al
    result, err = fetch()
    if err != nil {
        return result, err
    }

    // Cache-ə yaz (xəta olsa da qayıt)
    _ = cache.Set(ctx, key, result, ttl)
    return result, nil
}

type UserProfile struct {
    ID    int    `json:"id"`
    Name  string `json:"name"`
    Email string `json:"email"`
}

func GetUserProfile(ctx context.Context, cache *RedisCache, userID int) (*UserProfile, error) {
    key := fmt.Sprintf("user:profile:%d", userID)

    return Remember(ctx, cache, key, 15*time.Minute, func() (*UserProfile, error) {
        // DB sorğusu simulyasiyası
        fmt.Printf("DB-dən oxunur: user %d\n", userID)
        return &UserProfile{ID: userID, Name: "Orkhan", Email: "o@example.com"}, nil
    })
}

func main() {
    ctx := context.Background()
    cache := NewRedisCache("localhost:6379", "", "myapp", 0)

    // Cache-aside ilə istifadəçi profili
    profile, err := GetUserProfile(ctx, cache, 42)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Profil: %+v\n", profile)

    // İkinci sorğu — cache-dən gəlir
    profile2, _ := GetUserProfile(ctx, cache, 42)
    fmt.Printf("Cache-dən: %+v\n", profile2)

    // Redis pipeline — bir neçə əmri birlikdə göndər
    pipe := cache.client.Pipeline()
    pipe.Set(ctx, "counter:visits", 0, 24*time.Hour)
    pipe.Incr(ctx, "counter:visits")
    pipe.Incr(ctx, "counter:visits")
    cmds, err := pipe.Exec(ctx)
    if err != nil {
        fmt.Println("Pipeline xətası:", err)
    }
    _ = cmds
}
```

### Nümunə 4: Two-level cache (local + Redis)

```go
package main

import (
    "context"
    "fmt"
    "time"
)

// TwoLevelCache — local in-memory + Redis
type TwoLevelCache struct {
    local  *Cache[string, []byte]
    remote *RedisCache
    localTTL time.Duration
}

func NewTwoLevelCache(redis *RedisCache, localTTL time.Duration) *TwoLevelCache {
    c := &Cache[string, []byte]()
    c.StartCleanup(time.Minute)
    return &TwoLevelCache{local: c, remote: redis, localTTL: localTTL}
}

func (c *TwoLevelCache) Get(ctx context.Context, key string) ([]byte, bool) {
    // 1. Local cache
    if v, ok := c.local.Get(key); ok {
        return v, true
    }

    // 2. Redis
    var data []byte
    if err := c.remote.Get(ctx, key, &data); err == nil {
        // Local cache-ə yaz (qısa TTL)
        c.local.Set(key, data, c.localTTL)
        return data, true
    }

    return nil, false
}

func (c *TwoLevelCache) Set(ctx context.Context, key string, value []byte, ttl time.Duration) error {
    c.local.Set(key, value, c.localTTL)
    return c.remote.Set(ctx, key, value, ttl)
}

func (c *TwoLevelCache) Invalidate(ctx context.Context, key string) error {
    c.local.Delete(key)
    return c.remote.Delete(ctx, key)
}

func main() {
    ctx := context.Background()
    redisCache := NewRedisCache("localhost:6379", "", "app", 0)
    twoLevel := NewTwoLevelCache(redisCache, 30*time.Second)

    data := []byte(`{"id":1,"name":"Orkhan"}`)

    _ = twoLevel.Set(ctx, "user:1", data, 5*time.Minute)

    if v, ok := twoLevel.Get(ctx, "user:1"); ok {
        fmt.Println("Məlumat:", string(v))
    }

    // Cache invalidation
    _ = twoLevel.Invalidate(ctx, "user:1")
    fmt.Println("Silindi")
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — LRU benchmark:**
`NewLRUCache(1000)` ilə 10K get/set əməliyyatı üçün benchmark yazın. `sync.Map` ilə müqayisə edin.

**Tapşırıq 2 — Cache-aside middleware:**
HTTP handler üçün cache middleware yazın: `Cache(ttl time.Duration) Middleware`. URL + method-u key kimi istifadə etsin.

**Tapşırıq 3 — Redis pub/sub ilə cache invalidation:**
Bir serverdə məlumat yeniləndikdə digər serverlərin local cache-ini invalidate etmək üçün Redis pub/sub işlədin.

**Tapşırıq 4 — Metrics:**
Cache için hit rate, miss rate, eviction count metrikalarını toplayın. `expvar` və ya Prometheus formatında göstərin.

**Tapşırıq 5 — Stale-while-revalidate:**
TTL bitdikdə köhnə məlumatı qaytarın, arxa planda yeniləyin (stale-while-revalidate pattern). Race condition olmasın.

## Əlaqəli Mövzular

- [56-advanced-concurrency](56-advanced-concurrency.md) — sync.RWMutex, sync.Map
- [57-advanced-concurrency-2](57-advanced-concurrency-2.md) — singleflight
- [37-database](37-database.md) — DB connection pool
- [34-http-client](34-http-client.md) — HTTP response caching
- [71-monitoring-and-observability](71-monitoring-and-observability.md) — cache metrics
