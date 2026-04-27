# sync.Pool — Object Pooling (Lead)

## İcmal

`sync.Pool` — tez-tez yaradılan və atılan obyektlər üçün yenidən istifadə mexanizmidir. Bir pool-a obyekt qaytarılır (Put), sonrakı istifadəçi onu yenidən alır (Get) — yeni allokasiya olmur. Bu, GC-nin işini azaldır və yüksək yüklü ssenarilərdə performans artırır.

`sync.Pool` **cache deyil**: GC hər cycle-da pool-u silə bilər. Davamlı saxlamaq üçün deyil, yüksək allokasiya yükünü azaltmaq üçündür.

## Niyə Vacibdir

- HTTP handler-lər hər request üçün `bytes.Buffer`, `json.Encoder`, custom struct yaradırsa → GC pressure artır, latency tənzimlənir
- Yüksək throughput-lu servislərdə (100k+ req/s) allokasiya GC pause-lara səbəb olur
- Go GC concurrent-dir, amma hər allokasiya heap-ə gedir — pool ilə heap bypass

## Əsas Anlayışlar

### `Get` və `Put`

```go
var bufPool = sync.Pool{
    New: func() any {
        return new(bytes.Buffer) // Pool boş olduqda çağırılır
    },
}

func processRequest(data []byte) string {
    buf := bufPool.Get().(*bytes.Buffer) // Pool-dan al
    buf.Reset()                          // Əvvəlki məzmunu sil — məcburidir!
    defer bufPool.Put(buf)               // Bitdikdən sonra pool-a qaytaraq

    buf.Write(data)
    buf.WriteString(" processed")
    return buf.String()
}
```

### Reset qaydası

Pool-dan alınan obyekt əvvəlki istifadəçidən qalma vəziyyətdə ola bilər. **Mütləq sıfırlanmalıdır:**

```go
// bytes.Buffer üçün:
buf.Reset()

// slice üçün:
s = s[:0]

// struct üçün:
obj.field1 = ""
obj.field2 = 0
// və ya: *obj = MyStruct{}
```

### GC ilə münasibət

```go
// sync.Pool-un GUARANTEED olmayan cəhdi:
// - GC hər cycle-da pool-un bəzi/hamı obyektlərini silə bilər
// - GOGC=100 (default) → heap 2x olduqda GC başlayır
// - Pool-u "cache" kimi istifadə etmə — silinəcəyini bil

// Bu sehbəti pool-da saxlama:
// ❌ DB connection (connection pool istifadə et)
// ❌ File handle
// ❌ Böyük state daşıyan obyektlər
```

### Pointer Pool Davranışı

```go
// Pool struct saxlamalıdır, interface deyil — daha az allokasiya
var pool = sync.Pool{
    New: func() any { return &MyStruct{} }, // pointer — düzgün
}

obj := pool.Get().(*MyStruct) // type assertion lazımdır
```

## Praktik Baxış

### bytes.Buffer Pool — JSON encoding

```go
var jsonBufPool = sync.Pool{
    New: func() any { return new(bytes.Buffer) },
}

func jsonResponse(w http.ResponseWriter, v any) {
    buf := jsonBufPool.Get().(*bytes.Buffer)
    buf.Reset()
    defer jsonBufPool.Put(buf)

    if err := json.NewEncoder(buf).Encode(v); err != nil {
        http.Error(w, err.Error(), http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    w.Write(buf.Bytes())
}
```

**Alternativ:** `json.Marshal` hər dəfə byte slice allocate edir. Pool ilə eyni buffer yenidən istifadə edilir.

### Böyük Slice Pool

```go
// Hər request üçün eyni ölçülü slice lazımdırsa:
var recordPool = sync.Pool{
    New: func() any {
        s := make([]Record, 0, 1000) // 1000 capacity ilə yarat
        return &s
    },
}

func processRecords(db *sql.DB) error {
    sp := recordPool.Get().(*[]Record)
    records := (*sp)[:0] // capacity saxla, length sıfırla
    defer func() {
        *sp = records[:0]
        recordPool.Put(sp)
    }()

    // records-u doldur, işlə...
    return nil
}
```

### HTTP Middleware-də Pool

```go
type requestContext struct {
    userID   int
    traceID  string
    params   map[string]string
    errors   []error
}

var ctxPool = sync.Pool{
    New: func() any {
        return &requestContext{
            params: make(map[string]string, 8),
            errors: make([]error, 0, 4),
        }
    },
}

func (rc *requestContext) reset() {
    rc.userID = 0
    rc.traceID = ""
    for k := range rc.params {
        delete(rc.params, k)
    }
    rc.errors = rc.errors[:0]
}

func middleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        rc := ctxPool.Get().(*requestContext)
        rc.reset()
        defer ctxPool.Put(rc)

        ctx := context.WithValue(r.Context(), ctxKey, rc)
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}
```

### Benchmark ilə fərqin görülməsi

```go
// BenchmarkWithoutPool — hər iterasiyada bytes.Buffer allokasiyası
func BenchmarkWithoutPool(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        buf := new(bytes.Buffer)
        buf.WriteString("hello world")
        _ = buf.String()
    }
}

// BenchmarkWithPool — pool-dan alır, qaytarır
var pool = sync.Pool{New: func() any { return new(bytes.Buffer) }}

func BenchmarkWithPool(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        buf := pool.Get().(*bytes.Buffer)
        buf.Reset()
        buf.WriteString("hello world")
        _ = buf.String()
        pool.Put(buf)
    }
}

// Tipik nəticə (yüksək yüklü ssenari):
// BenchmarkWithoutPool   10000000    150 ns/op    64 B/op    1 allocs/op
// BenchmarkWithPool      20000000     72 ns/op     0 B/op    0 allocs/op
```

### Nə vaxt sync.Pool istifadə etmə

```
❌ Az sayda yaradılan obyektlər — overhead artır, faydası yoxdur
❌ Bağlantı idarəetməsi — database/sql pool istifadə et
❌ Uzun ömürlü state — GC siləcək, itkisi olar
❌ Çox kiçik obyektlər (int, bool) — allokasiya overhead-i minimal

✓ Yüksək throughput (1000+ op/s) + eyni tip obyektin tez-tez yaranması
✓ bytes.Buffer, []byte, encoding/json.Encoder kimi serialization köməkçiləri
✓ Temporary struct-lar (logger fields, request context)
```

### `vet` xəbərdarlığı

```go
// Pool-dan alınan dəyəri Copy etmə — race condition yaranır:
obj := pool.Get().(*MyStruct)
copy := *obj  // ❌ — pool.Put-dan sonra copy-nin dəyəri dəyişə bilər
pool.Put(obj)
// copy-ni istifadə etmə
```

## Praktik Tapşırıqlar

1. **JSON handler:** `json.NewEncoder` + `bytes.Buffer` pool-u ilə HTTP JSON handler yaz, `go test -bench -benchmem` ilə allokasiya fərqini ölç
2. **Template rendering:** `text/template.Execute` pool-dakı buffer-ə yaz, response üçün istifadə et
3. **Log formatter:** Her log entry üçün string formatting buffer-i pool-dan al
4. **Race condition testi:** Pool-dan alınan struct-a race detector ilə yanaş: `go test -race`
5. **GC bağlılığı:** `runtime.GC()` çağır, pool-un boşaldığını yoxla (Get → New çağrılır)

## PHP ilə Müqayisə

PHP-də `sync.Pool` analoji yoxdur. PHP prosesi request başında başlayır, bitdikdə bütün memory azad olunur — object pooling mənasızdır.

Go-da isə uzun sürən process var: bir server instance-ı minlərlə request işləyir. Heap-də toplanan garbage GC pause-larına gətirir. Pool bu yükü azaldır.

**Bənzər konsept:**
- PHP-nin connection pool-u (persistent connection, `pg_pconnect`) — bağlantını saxlayır, yenidən yaratmır
- Go `sync.Pool` — istənilən obyekti pooling edir, DB connection-la məhdud deyil

## Əlaqəli Mövzular

- [69-memory-management.md](69-memory-management.md) — GC internals, escape analysis
- [68-profiling-and-benchmarking.md](68-profiling-and-benchmarking.md) — allokasiya profili, pprof
- [56-advanced-concurrency.md](56-advanced-concurrency.md) — sync paket
- [57-advanced-concurrency-2.md](57-advanced-concurrency-2.md) — atomic, lock-free structures
