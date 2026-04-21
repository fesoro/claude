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
