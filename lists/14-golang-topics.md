## Syntax / tipler

Tiplər: int, int8/16/32/64, uint, float32/64, complex64/128, string, bool, byte (=uint8), rune (=int32)
var, :=, const
const-larda iota (auto-increment)
Array, Slice, Map
Slice header (pointer, length, capacity)
make() vs new() fərqi
Struct, Pointer
Zero values (bütün tiplərdə default)
Anonymous struct
Struct tag (`json:"name"`, `validate:"required"`)
Composite literal
Type alias (type T = int) vs type definition (type T int)
Function, Variadic Function, Named Return
Multiple return values
Blank identifier _ (intentional unused)
Method (Value vs Pointer Receiver — çox vacib)
Interface, Empty Interface (any)
Type Assertion, Type Switch
Stringer Interface (String() string)
error Interface

## Concurrency

Goroutine (`go func()`)
Channel (buffered vs unbuffered)
Channel directions (chan<-, <-chan)
Close channel; receive from closed
select statement (non-blocking, default case)
sync.WaitGroup
sync.Mutex, sync.RWMutex
sync.Once (tək icra)
sync.Cond
sync.Map (concurrent-safe map)
sync.Pool (obyekt reuse)
sync/atomic — atomic ops (CompareAndSwap, LoadInt32)
Context (WithCancel, WithTimeout, WithDeadline, WithValue)
errgroup (golang.org/x/sync/errgroup)
singleflight (dedup concurrent calls)
Worker Pool pattern
Fan-out / Fan-in pattern
Pipeline pattern
Rate limiting (golang.org/x/time/rate)
Race condition — data race
Deadlock
Goroutine leak

## Error handling

Error Interface
Custom Error (struct ilə, Error() method)
fmt.Errorf ilə wrapping (%w)
errors.New
errors.Is (equality check)
errors.As (type unwrap)
errors.Unwrap
Sentinel errors (io.EOF kimi)
Panic, Recover, Defer
defer icra sırası (LIFO)
defer args evaluation (immediate)
Panic in goroutine (tamamilə crash edir)

## Modules / packages

Package sistemi, go.mod
go get <pkg>@<version>
go mod tidy — istifadəsiz dep-ləri sil, yenilərini əlavə et
go mod vendor — vendor/ yarat
go mod download
go work (multi-module workspaces)
init() funksiyası (auto-çağırılır)
Exported (capital) vs unexported
Internal packages (/internal/ — access scope)
Struct Embedding (composition inheritance əvəzi)

## Generics (Go 1.18+)

Type parameters [T any]
Constraints (comparable, ~int, interface)
Generic functions, types
constraints package

## Testing

testing package
t.Run (subtest)
t.Parallel
Table-driven tests
t.Helper() — helper function marker
t.Cleanup — defer-dən daha ətraflı
Benchmark (testing.B)
b.ResetTimer(), b.StopTimer()
Example functions (go-doc-da görünür)
Fuzz testing (Go 1.18+)
testify (assert, require, mock, suite)
gomock
httptest (HTTP test server)
go test -race — race detector
go test -cover — coverage
go test -count=1 — cache-siz
go test -run TestFoo — konkret test
go test -v ./... — verbose, bütün packages
go test -bench=. -benchmem

## stdlib-dən vacib paketlər

net/http — HTTP server və client
net/http/httptest
encoding/json — JSON marshal/unmarshal
encoding/xml
database/sql, sqlx
context
io, bufio
os, os/exec
path/filepath
strings, strconv, unicode
time, time.Duration
sort
container/heap, container/list
reflect — runtime reflection
regexp
log, log/slog (Go 1.21+ structured logging)
sync, sync/atomic
runtime, runtime/pprof
embed — go:embed directive

## Ekosistem / framework

Gin, Echo, Fiber — HTTP framework
chi, gorilla/mux — router
GORM, ent, sqlc — ORM / SQL code gen
gRPC + Protobuf (google.golang.org/grpc)
Protobuf (protoc-gen-go, buf)
Cobra — CLI
Viper — config
Zap, zerolog, slog — logging
Kafka clients (segmentio/kafka-go, confluent-kafka-go)
Redis (go-redis/redis)
Testcontainers-go
Wire — compile-time DI
fx (Uber) — runtime DI

## Tooling

go build — binary yarat
go run — işə sal
go install — $GOPATH/bin-ə install et
go test
go vet — suspicious code detect
go fmt — format
goimports — fmt + import management
golangci-lint — meta-linter
go test -race — race detector
pprof — CPU/memory profiling
go tool pprof
go tool trace
go generate — code generation
delve (dlv) — debugger

## Runtime / memory

Stack vs Heap (escape analysis)
Garbage Collector (concurrent mark-sweep)
GOMAXPROCS
GOGC (GC tunning)
Scheduler: G (goroutine), M (OS thread), P (processor)
Work-stealing
runtime.Gosched()

## Best practices

Accept interface, return struct
Struct embedding — inheritance yox, composition
Error vs panic fərqi (panic yalnız unrecoverable üçün)
Nil channel əbədi bloklayır (select default üçün faydalı)
Struct-of-arrays vs array-of-structs
Method receiver: pointer vs value qaydalar
Prefer early returns
Context first param (ctx context.Context)
errors wrap zəncirləmə
defer ilə resource cleanup

## Modern Go (1.21 → 1.24)

# 1.21
log/slog — structured logging in stdlib
slices, maps packages — generic helpers (slices.Sort, slices.Contains, maps.Keys)
cmp package — cmp.Compare, cmp.Less, cmp.Or
min, max, clear builtins
context.WithoutCancel, context.AfterFunc, context.WithDeadlineCause
sync.OnceFunc / OnceValue / OnceValues — generic once

# 1.22
http.ServeMux — method + path patterns ("GET /users/{id}")
range over int — for i := range 10 { ... }
loop variable scoping fix (per-iteration variable)
math/rand/v2 — improved API, no global mutable state

# 1.23
range over functions — for k, v := range myIter { ... } (push iterators)
iter.Seq, iter.Seq2 — iterator types
unique package — value canonicalization (string interning)
slog.DiscardHandler

# 1.24
generic type aliases (type Set[T comparable] = map[T]struct{})
crypto/mlkem — post-quantum KEM
weak package — weak references
go tool directive in go.mod (replace go install with versioned tools)
omitzero JSON struct tag — omit zero values

# Deprecated patterns to avoid
math/rand global Seed (use rand/v2)
ioutil.* (use io / os since 1.16)
io/ioutil (whole package deprecated)

## stdlib patterns (modern)

# log/slog — structured logging
logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelDebug}))
logger.Info("user login", "user_id", 1, "ip", ip)
logger = logger.With("trace_id", tid)              — child with context
slog.SetDefault(logger)
slog.Info(...)                                       — uses default

# http.ServeMux 1.22+ routing
mux := http.NewServeMux()
mux.HandleFunc("GET /users/{id}", getUser)
mux.HandleFunc("POST /users", createUser)
mux.HandleFunc("DELETE /users/{id}", deleteUser)
id := r.PathValue("id")                              — extract path param
http.ListenAndServe(":8080", mux)

# embed FS
import "embed"
//go:embed templates/*.html
var templates embed.FS
//go:embed config.yaml
var configBytes []byte
//go:embed static/*
var static embed.FS                                  — http.FS(static) for static serving

# range over function (1.23+)
func Numbers(n int) iter.Seq[int] {
    return func(yield func(int) bool) {
        for i := 0; i < n; i++ {
            if !yield(i) { return }
        }
    }
}
for v := range Numbers(10) { fmt.Println(v) }

# slices / maps generics (1.21+)
slices.Contains(s, x)
slices.Sort(s) / slices.SortFunc(s, cmp)
slices.Index(s, x)
slices.Reverse(s)
slices.Equal(a, b)
slices.Clone(s)
slices.Concat(a, b, c)                              — 1.22+
maps.Keys(m) / maps.Values(m)                        — returns iter.Seq (1.23+)
maps.Clone(m)
maps.Copy(dst, src)
maps.Equal(a, b)

# sync.OnceValue (1.21+)
var loadConfig = sync.OnceValue(func() *Config {
    return parseConfig()
})

## Security / supply chain

govulncheck ./...                                    — known CVE scan (golang.org/x/vuln)
go list -m -json all | nancy sleuth                  — alt vulnerability scanner
go mod verify                                        — check sum vs go.sum
GOFLAGS="-trimpath" go build                          — strip paths
go build -ldflags="-s -w"                            — strip debug
GOSUMDB=sum.golang.org / GONOSUMCHECK / GOPRIVATE=corp.com/*
crypto/rand for security; math/rand only for non-crypto
gosec ./...                                          — static security analyzer
go install honnef.co/go/tools/cmd/staticcheck@latest

## Observability

OpenTelemetry SDK (go.opentelemetry.io/otel)
otel-go-contrib instrumentations (net/http, database/sql, gRPC, redis)
prometheus client_golang
expvar — stdlib runtime stats (/debug/vars)
net/http/pprof — runtime profiles (/debug/pprof/)
runtime.ReadMemStats / runtime.NumGoroutine
GODEBUG=schedtrace=1000,scheddetail=1 — runtime trace (debug only)

## Tooling (modern)

go work — multi-module workspaces (go.work file)
go work init ./module1 ./module2
go work use ./newmod
go work sync

go.mod toolchain directive (1.21+) — pin Go version
toolchain go1.23.0

go install <pkg>@latest   — install CLI tools to $GOBIN
go install -tags="prod" ./cmd/server
go run -race ./...
go test -race -shuffle=on -count=1 ./...
go test -fuzz=FuzzName -fuzztime=30s
go test -bench=. -benchmem -benchtime=10s
go tool covdata percent -i=cov.out                  — 1.20+ binary coverage

# Lint / format
gofmt -s -w .                                        — simplify + write
goimports -local mycompany.com -w .
golangci-lint run                                    — meta-linter (gofmt, govet, staticcheck, etc.)
gopls — language server

# Generate
//go:generate stringer -type=Status
//go:generate mockgen -source=foo.go -destination=mock_foo.go
go generate ./...

# Vendor / modules
go mod download
go mod tidy
go mod why <pkg>
go mod graph
go mod edit -replace=old=new
go mod edit -dropreplace=old
GOPROXY=https://proxy.golang.org,direct
GOPRIVATE=github.com/mycompany/*    — skip proxy/sumdb for private

## Common idioms (production)

# Graceful shutdown
ctx, cancel := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
defer cancel()
srv := &http.Server{Addr: ":8080", Handler: mux}
go func() { srv.ListenAndServe() }()
<-ctx.Done()
shutdownCtx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
defer cancel()
srv.Shutdown(shutdownCtx)

# Error wrapping with stack-like context
return fmt.Errorf("load user %d: %w", id, err)
errors.Is(err, ErrNotFound)
var pe *PathError; errors.As(err, &pe)

# Functional options pattern
type Option func(*Server)
func WithPort(p int) Option { return func(s *Server) { s.port = p } }
func New(opts ...Option) *Server { s := &Server{}; for _, o := range opts { o(s) }; return s }

# Singleflight (dedup concurrent calls)
var g singleflight.Group
v, err, _ := g.Do(key, func() (any, error) { return loadFromDB(key) })

# errgroup
g, ctx := errgroup.WithContext(ctx)
for _, url := range urls {
    url := url
    g.Go(func() error { return fetch(ctx, url) })
}
if err := g.Wait(); err != nil { ... }
g.SetLimit(10)                                       — bounded concurrency
