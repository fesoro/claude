# Redis Client — go-redis (Middle)

## İcmal

Go-da Redis ilə işləmək üçün standart kitabxana **go-redis**-dir (`github.com/redis/go-redis/v9`). String, hash, list, set, sorted set, stream əməliyyatları, pub/sub, Lua scripting, pipeline, transaction dəstəkləyir. Context-first API sayəsində timeout, cancellation tam dəstəklənir.

## Niyə Vacibdir

- Session cache, token blacklist, rate limiter — Redis Go-da tez-tez istifadə olunan pattern
- Database-in qarşısındaki cache qatı: okuma yükünü 10x-100x azaldır
- Pub/Sub ilə real-time feature-lar (notification, live update)
- Sorted Set ilə leaderboard, queue sistemi

## Əsas Anlayışlar

- **`redis.NewClient`** — standalone client; `redis.NewClusterClient` — cluster
- **`context.Context`** — hər əməliyyatda məcburi; timeout üçün `WithTimeout`
- **`pipeline`** — çox əmri bir network round-trip ilə göndərmək
- **`TxPipelined`** — atomik pipeline (MULTI/EXEC)
- **`WATCH`** — optimistic locking; dəyər dəyişibsə transaction fail edir
- **`Scan`** — `KEYS *` yerinə; production-da həmişə Scan istifadə et
- **`TTL`** — key ömrü; cache invalidation üçün kritik

## Praktik Baxış

**Ne vaxt Redis:**

| Ssenari | Redis Struct |
|---------|-------------|
| Session storage | Hash (`HSET user:id field val`) |
| Cache (TTL ilə) | String + `EX` |
| Rate limiting | Sorted Set + Lua |
| Pub/Sub notification | Pub/Sub |
| Distributed lock | `SET NX PX` |
| Queue / worker | List (`LPUSH`/`BRPOP`) |

**Trade-off-lar:**
- Redis in-memory: server restart-da data itirilə bilər (persistence konfig lazımdır)
- Cluster mode: multi-key əməliyyatları mürəkkəbləşir (hash slot)
- Connection pool: default 10; yüksək yükdə artır

**Common mistakes:**
- `KEYS *` production-da işlətmək — bloklanma; `SCAN` istifadə et
- TTL olmayan key-lər — memory leak
- Pipeline-ı unutmaq — hər əməliyyat ayrı round-trip → gecikir

## Nümunələr

### Nümunə 1: Bağlantı və əsas əməliyyatlar

```go
package main

import (
    "context"
    "fmt"
    "time"

    "github.com/redis/go-redis/v9"
)

func main() {
    rdb := redis.NewClient(&redis.Options{
        Addr:     "localhost:6379",
        Password: "",
        DB:       0,
        PoolSize: 10,
    })
    defer rdb.Close()

    ctx := context.Background()

    // Ping
    if err := rdb.Ping(ctx).Err(); err != nil {
        panic(err)
    }

    // SET with TTL
    if err := rdb.Set(ctx, "user:1:name", "Orkhan", 10*time.Minute).Err(); err != nil {
        panic(err)
    }

    // GET
    val, err := rdb.Get(ctx, "user:1:name").Result()
    if err == redis.Nil {
        fmt.Println("Key yoxdur")
    } else if err != nil {
        panic(err)
    } else {
        fmt.Println("Dəyər:", val)
    }

    // DEL
    rdb.Del(ctx, "user:1:name")

    // INCR (atomik sayğac)
    rdb.Set(ctx, "hits", 0, 0)
    rdb.Incr(ctx, "hits")
    rdb.IncrBy(ctx, "hits", 5)
    count, _ := rdb.Get(ctx, "hits").Int()
    fmt.Println("Hits:", count) // 6
}
```

### Nümunə 2: Hash — session storage

```go
type Session struct {
    UserID    int
    Role      string
    ExpiresAt time.Time
}

func saveSession(ctx context.Context, rdb *redis.Client, token string, s Session) error {
    key := "session:" + token
    return rdb.HSet(ctx, key,
        "user_id", s.UserID,
        "role", s.Role,
        "expires_at", s.ExpiresAt.Unix(),
    ).Err()
    // TTL ayrıca set et:
    // rdb.Expire(ctx, key, 24*time.Hour)
}

func getSession(ctx context.Context, rdb *redis.Client, token string) (*Session, error) {
    key := "session:" + token
    vals, err := rdb.HGetAll(ctx, key).Result()
    if err != nil || len(vals) == 0 {
        return nil, redis.Nil
    }

    userID, _ := strconv.Atoi(vals["user_id"])
    expiresAt, _ := strconv.ParseInt(vals["expires_at"], 10, 64)

    return &Session{
        UserID:    userID,
        Role:      vals["role"],
        ExpiresAt: time.Unix(expiresAt, 0),
    }, nil
}
```

### Nümunə 3: Cache layer — JSON serialization

```go
type CacheService struct {
    rdb *redis.Client
    ttl time.Duration
}

func (c *CacheService) GetUser(ctx context.Context, id int, fallback func(ctx context.Context, id int) (*User, error)) (*User, error) {
    key := fmt.Sprintf("user:%d", id)

    // Cache yoxla
    data, err := c.rdb.Get(ctx, key).Bytes()
    if err == nil {
        var user User
        if err := json.Unmarshal(data, &user); err == nil {
            return &user, nil
        }
    }

    // Cache miss — DB-dən al
    user, err := fallback(ctx, id)
    if err != nil {
        return nil, err
    }

    // Cache-ə yaz
    if data, err := json.Marshal(user); err == nil {
        c.rdb.Set(ctx, key, data, c.ttl)
    }

    return user, nil
}

func (c *CacheService) InvalidateUser(ctx context.Context, id int) {
    c.rdb.Del(ctx, fmt.Sprintf("user:%d", id))
}
```

### Nümunə 4: Pipeline — batch əməliyyatlar

```go
func batchGet(ctx context.Context, rdb *redis.Client, userIDs []int) (map[int]string, error) {
    pipe := rdb.Pipeline()

    cmds := make([]*redis.StringCmd, len(userIDs))
    for i, id := range userIDs {
        cmds[i] = pipe.Get(ctx, fmt.Sprintf("user:%d:name", id))
    }

    // Bir network round-trip ilə hamısını göndər
    if _, err := pipe.Exec(ctx); err != nil && err != redis.Nil {
        return nil, err
    }

    result := make(map[int]string)
    for i, cmd := range cmds {
        if val, err := cmd.Result(); err == nil {
            result[userIDs[i]] = val
        }
    }
    return result, nil
}
```

### Nümunə 5: Distributed lock

```go
func withLock(ctx context.Context, rdb *redis.Client, lockKey string, ttl time.Duration, fn func() error) error {
    // SET NX (only if Not eXists) — atomik lock
    lockVal := fmt.Sprintf("%d", time.Now().UnixNano())
    ok, err := rdb.SetNX(ctx, lockKey, lockVal, ttl).Result()
    if err != nil {
        return fmt.Errorf("lock alına bilmədi: %w", err)
    }
    if !ok {
        return fmt.Errorf("lock artıq tutulub")
    }

    defer func() {
        // Yalnız öz lock-umuzu silirik (Lua ilə atomik)
        script := redis.NewScript(`
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        `)
        script.Run(ctx, rdb, []string{lockKey}, lockVal)
    }()

    return fn()
}
```

### Nümunə 6: Pub/Sub — notification

```go
func publisher(ctx context.Context, rdb *redis.Client) {
    for i := 0; i < 5; i++ {
        rdb.Publish(ctx, "orders", fmt.Sprintf(`{"id":%d,"status":"created"}`, i))
        time.Sleep(time.Second)
    }
}

func subscriber(ctx context.Context, rdb *redis.Client) {
    sub := rdb.Subscribe(ctx, "orders")
    defer sub.Close()

    ch := sub.Channel()
    for {
        select {
        case msg, ok := <-ch:
            if !ok {
                return
            }
            fmt.Printf("Mesaj: %s\n", msg.Payload)
        case <-ctx.Done():
            return
        }
    }
}
```

## Praktik Tapşırıqlar

1. **Cache middleware:** HTTP handler-ləri üçün cache middleware yaz — `GET /users/1`-i 5 dəqiqə cache-lə, `POST/PUT/DELETE`-da invalidasiya et
2. **Rate limiter:** Redis Sorted Set ilə sliding window rate limiter implement et — 100 req/dəqiqə
3. **Session store:** JWT əvəzinə Redis session store — login, logout, session yeniləmə
4. **Distributed lock:** İki goroutine eyni resursa yazar — lock ilə serialization et, `go test -race` ilə yoxla

## PHP ilə Müqayisə

```
PHP/Laravel              →  Go
────────────────────────────────────────
Redis::set(k, v, ttl)    →  rdb.Set(ctx, k, v, ttl)
Redis::get(k)            →  rdb.Get(ctx, k).Result()
Redis::hset(k, f, v)     →  rdb.HSet(ctx, k, f, v)
Cache::remember()        →  manual Get + fallback + Set
Redis::pipeline(fn)      →  rdb.Pipeline()
```

Laravel-in `Cache` facade-ı Redis-i abstraktlaşdırır. Go-da go-redis birbaşa Redis protokolu ilə işləyir — daha az sehrbazlıq, daha çox nəzarət.

## Əlaqəli Mövzular

- [05-database](05-database.md) — database/sql ilə müqayisə
- [15-rate-limiting](15-rate-limiting.md) — token bucket rate limiter
- [17-graceful-shutdown](17-graceful-shutdown.md) — Redis connection-larını bağlamaq
- [../core/28-context](../core/28-context.md) — timeout, cancellation
- [../advanced/08-caching](../advanced/08-caching.md) — in-memory + Redis cache strategiyaları
