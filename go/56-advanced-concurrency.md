# Advanced Concurrency — sync paketi və atomic əməliyyatlar (Lead)

## İcmal

Go-da concurrency yalnız goroutine və channel-dən ibarət deyil. Production sistemlərində `sync.Mutex`, `sync.RWMutex`, `sync.Once`, `sync.Pool`, `sync.Cond`, `sync.Map` və `atomic` paketi tez-tez işlədilir. Bu mövzuda həmin primitiv-lərin nə vaxt, necə və niyə istifadə olunduğunu, race condition-ların necə baş verdiyini və onlardan necə qorunacağını öyrənəcəksiniz.

PHP/Laravel-də concurrency demək olar ki yoxdur — hər request öz prosesindədir. Go-da isə eyni process daxilində minlərlə goroutine paylaşılan vəziyyəti (shared state) dəyişə bilər, buna görə sinxronizasiya primitiv-ləri vacibdir.

## Niyə Vacibdir

- Shared state olan kodda Mutex istifadə etməmək **data race** yaradır — `go test -race` ilə tutulur
- `sync.RWMutex` — oxuma-ağırlıqlı iş yüklərini 2–10x sürətləndirir
- `sync.Once` — singleton, lazy initialization üçün yeganə doğru yol
- `sync.Pool` — GC təzyiqini azaldır, yüksək throughput servislərdə kritik
- `atomic` əməliyyatlar — sadə sayğaclar üçün Mutex-dən daha sürətli (~10x)
- `sync.Cond` — producer/consumer pattern-lərdə effektiv olmayan polling-i aradan qaldırır

## Əsas Anlayışlar

### Mutex növləri

| Primitiv | İstifadə halı | Trade-off |
|----------|--------------|-----------|
| `sync.Mutex` | Oxuma + yazma mix | Sadə, universal |
| `sync.RWMutex` | Çox oxuma, az yazma | Oxuma concurrent, yazma exclusive |
| `sync.Map` | Çox oxuma, nadir yazma, key-lər sabit | API əlverişsiz, amma lock-free oxuma |

### atomic əməliyyatlar

`atomic` paketi CPU səviyyəsində atomic əməliyyatlar təmin edir — Mutex lock/unlock yükü olmadan. Yalnız sadə tip-lər üçün işlər: `int32`, `int64`, `uint64`, `uintptr`, `Pointer`.

**CompareAndSwap (CAS)** — lock-free data structure-ların əsasıdır. "Dəyər hələ `old`-dursa, `new` ilə əvəz et" — bu əməliyyat atomikdir.

### sync.Pool semantikası

`sync.Pool` GC tərəfindən istənilən vaxt boşaldıla bilər — qıyməti uzun müddət saxlamaq üçün uyğun deyil. Onun məqsədi **allocation həcmini azaltmaqdır**, xüsusən request-per-second yüksək olan servislərdə (HTTP handler-lər, JSON encoder/decoder).

## Praktik Baxış

### Real layihədə Mutex seçimi

```
Yalnız write → sync.Mutex
90%+ read   → sync.RWMutex
Global, sabit key-lər, concurrent read → sync.Map
Sayğac/bayraq → atomic
```

### Trade-off-lar

- `sync.RWMutex` — yazma zamanı bütün oxuyucuların bitməsini gözləyir; yüksək yazma yüklərində `sync.Mutex`-dən yavaş ola bilər
- `sync.Pool` — GC-dən əvvəl obyektlər silinir; `finalizer` ilə birlikdə istifadə etmək mümkün deyil
- `atomic.Value` — istənilən tipi saxlayır, amma `Store` eyni tip olmalıdır, əks halda panic
- `sync.Cond` — mürəkkəbdir, əksər hallarda channel ilə əvəz edilə bilər

### Anti-pattern-lər

```go
// YANLIŞ: Mutex-i dəyər kimi ötürmək (kopyalanır)
func process(mu sync.Mutex) { mu.Lock() } // BUG

// DOĞRU: pointer ilə
func process(mu *sync.Mutex) { mu.Lock() }

// YANLIŞ: defer olmadan Unlock
mu.Lock()
if err != nil {
    return err // Unlock unuduldu!
}
mu.Unlock()

// DOĞRU:
mu.Lock()
defer mu.Unlock()
```

### Production hazırlıq

- `go test -race ./...` — CI/CD pipeline-ında məcburi olmalıdır
- `sync.Mutex` yerini dəyişdirmək — struct embedding zamanı diqqət: mutex kopyalana bilməz
- Lock granularity — çox böyük lock scope, bottleneck yaradır; çox kiçik, deadlock riskini artırır

## Nümunələr

### Nümunə 1: sync.RWMutex ilə thread-safe in-memory store

```go
package main

import (
    "fmt"
    "sync"
    "time"
)

// ConfigStore — çox oxunan, nadir yazılan konfiqurasiya
type ConfigStore struct {
    mu   sync.RWMutex
    data map[string]string
}

func NewConfigStore() *ConfigStore {
    return &ConfigStore{
        data: make(map[string]string),
    }
}

// Get — concurrent oxuma mümkün (RLock)
func (c *ConfigStore) Get(key string) (string, bool) {
    c.mu.RLock()
    defer c.mu.RUnlock()
    v, ok := c.data[key]
    return v, ok
}

// Set — exclusive yazma (Lock)
func (c *ConfigStore) Set(key, value string) {
    c.mu.Lock()
    defer c.mu.Unlock()
    c.data[key] = value
}

// Snapshot — bütün konfiqurasiyanı kopyala
func (c *ConfigStore) Snapshot() map[string]string {
    c.mu.RLock()
    defer c.mu.RUnlock()
    out := make(map[string]string, len(c.data))
    for k, v := range c.data {
        out[k] = v
    }
    return out
}

func main() {
    store := NewConfigStore()

    // Yazıcı goroutine
    go func() {
        for i := 0; i < 5; i++ {
            store.Set("version", fmt.Sprintf("1.%d", i))
            time.Sleep(100 * time.Millisecond)
        }
    }()

    // Paralel oxuyucular
    var wg sync.WaitGroup
    for i := 0; i < 10; i++ {
        wg.Add(1)
        go func(id int) {
            defer wg.Done()
            v, _ := store.Get("version")
            fmt.Printf("Reader %d: %s\n", id, v)
        }(i)
    }
    wg.Wait()
}
```

### Nümunə 2: sync.Once ilə production-grade Singleton

```go
package main

import (
    "database/sql"
    "fmt"
    "sync"

    _ "github.com/lib/pq"
)

type Database struct {
    db *sql.DB
}

var (
    dbInstance *Database
    dbOnce     sync.Once
    dbInitErr  error
)

// GetDB — thread-safe singleton, ilk çağırışda inisializasiya
func GetDB(connStr string) (*Database, error) {
    dbOnce.Do(func() {
        db, err := sql.Open("postgres", connStr)
        if err != nil {
            dbInitErr = fmt.Errorf("DB açılması: %w", err)
            return
        }
        db.SetMaxOpenConns(25)
        db.SetMaxIdleConns(5)
        dbInstance = &Database{db: db}
    })
    return dbInstance, dbInitErr
}

// QEYD: sync.Once bir dəfə error verərsə,
// növbəti çağırışlarda həmin error qaytarılmır — instance nil qalır.
// Bu edge case üçün ayrıca sağlamlıq yoxlaması əlavə edin.
```

### Nümunə 3: atomic sayğac ilə yüksək-performanslı metrics

```go
package main

import (
    "fmt"
    "sync"
    "sync/atomic"
    "time"
)

// Metrics — lock-free sayğaclar
type Metrics struct {
    requestCount  atomic.Int64
    errorCount    atomic.Int64
    totalDuration atomic.Int64 // nanosaniyə
}

func (m *Metrics) RecordRequest(duration time.Duration, isError bool) {
    m.requestCount.Add(1)
    m.totalDuration.Add(int64(duration))
    if isError {
        m.errorCount.Add(1)
    }
}

func (m *Metrics) Summary() {
    count := m.requestCount.Load()
    errors := m.errorCount.Load()
    totalNs := m.totalDuration.Load()

    if count == 0 {
        fmt.Println("Hələ sorğu yoxdur")
        return
    }
    avgMs := time.Duration(totalNs/count).Milliseconds()
    fmt.Printf("Sorğu: %d, Xəta: %d, Ort. müddət: %dms\n", count, errors, avgMs)
}

func main() {
    m := &Metrics{}
    var wg sync.WaitGroup

    // Minlərlə goroutine eyni anda yazır — lock yoxdur
    for i := 0; i < 10000; i++ {
        wg.Add(1)
        go func(id int) {
            defer wg.Done()
            dur := time.Duration(id%50) * time.Millisecond
            m.RecordRequest(dur, id%100 == 0) // hər 100-cü xəta
        }(i)
    }
    wg.Wait()
    m.Summary()
}
```

### Nümunə 4: sync.Pool ilə HTTP handler üçün buffer pool

```go
package main

import (
    "bytes"
    "fmt"
    "net/http"
    "sync"
)

var bufPool = sync.Pool{
    New: func() interface{} {
        return new(bytes.Buffer)
    },
}

func jsonHandler(w http.ResponseWriter, r *http.Request) {
    buf := bufPool.Get().(*bytes.Buffer)
    buf.Reset() // əvvəlki məlumatı təmizlə
    defer bufPool.Put(buf) // işi bitəndə geri qoy

    fmt.Fprintf(buf, `{"status":"ok","path":"%s"}`, r.URL.Path)

    w.Header().Set("Content-Type", "application/json")
    w.Write(buf.Bytes())
}

// QEYD: buf.Reset() çağırmağı unutmayın!
// Pool-a qoyulmuş buffer-in köhnə məlumatı silinmir.
```

### Nümunə 5: sync.Cond ilə producer/consumer

```go
package main

import (
    "fmt"
    "sync"
    "time"
)

type Queue struct {
    mu    sync.Mutex
    cond  *sync.Cond
    items []int
    limit int
}

func NewQueue(limit int) *Queue {
    q := &Queue{limit: limit}
    q.cond = sync.NewCond(&q.mu)
    return q
}

// Push — dolu olduqda gözləyir
func (q *Queue) Push(item int) {
    q.mu.Lock()
    defer q.mu.Unlock()
    for len(q.items) >= q.limit {
        q.cond.Wait() // boşalana kimi gözlə
    }
    q.items = append(q.items, item)
    q.cond.Broadcast() // consumer-ləri oyat
}

// Pop — boş olduqda gözləyir
func (q *Queue) Pop() int {
    q.mu.Lock()
    defer q.mu.Unlock()
    for len(q.items) == 0 {
        q.cond.Wait() // dolu olana kimi gözlə
    }
    item := q.items[0]
    q.items = q.items[1:]
    q.cond.Broadcast() // producer-ləri oyat
    return item
}

func main() {
    q := NewQueue(5)

    // Producer
    go func() {
        for i := 0; i < 10; i++ {
            q.Push(i)
            fmt.Printf("Push: %d\n", i)
            time.Sleep(50 * time.Millisecond)
        }
    }()

    // Consumer
    for i := 0; i < 10; i++ {
        v := q.Pop()
        fmt.Printf("Pop: %d\n", v)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Rate limiter:**
`sync.Mutex` istifadə edərək thread-safe token bucket rate limiter yazın. `Allow() bool` metodu olsun. Hər saniyə token-lər artırılsın, goroutine ilə.

**Tapşırıq 2 — Metrics aggregator:**
HTTP server üçün metrics toplayan struct yazın: sorğu sayı, xəta sayı, p50/p99 latency. Latency-ni `atomic` ilə saxlamaq mümkündürmü? Nə vaxt `sync.Mutex` lazımdır?

**Tapşırıq 3 — Race condition tapın:**
```go
var m = make(map[string]int)
var wg sync.WaitGroup
for i := 0; i < 100; i++ {
    wg.Add(1)
    go func(n int) {
        defer wg.Done()
        m[fmt.Sprint(n)] = n // BUG: race condition
    }(i)
}
wg.Wait()
```
Bu kodu `go test -race` ilə test edin. `sync.RWMutex` və ya `sync.Map` ilə düzəldin. Hər iki həlli performans baxımından müqayisə edin.

**Tapşırıq 4 — Object pool benchmark:**
`sync.Pool` olan və olmayan HTTP handler yaradın. `ab -n 10000 -c 100` ilə benchmark edin. Fərq nə qədərdir?

## Əlaqəli Mövzular

- [27-goroutines-and-channels](27-goroutines-and-channels.md) — goroutine əsasları
- [28-context](28-context.md) — context ilə goroutine ləğvi
- [57-advanced-concurrency-2](57-advanced-concurrency-2.md) — errgroup, semaphore, worker pool
- [58-channel-patterns](58-channel-patterns.md) — fan-out/fan-in, pipeline, tee
- [68-profiling-and-benchmarking](68-profiling-and-benchmarking.md) — mutex contention profilləşdirməsi
