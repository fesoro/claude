# Build Tags — `//go:build`, platform-specific kod, conditional compilation (Lead)

## İcmal

Build tags (build constraint-lər) — faylın müəyyən şərtlər altında kompilyasiya olub-olunmayacağını idarə edən mexanizmdir. Platform-specific kod (Linux/Windows/macOS), feature flag-lər, debug/release rejim, integration test, CGO constraint-ləri üçün istifadə olunur.

Go 1.17-dən əvvəl `// +build linux` formatı işlədilirdi. Yeni format `//go:build linux`-dur — daha aydın, boolean operatorlarla.

PHP-də bu yoxdur — hər mühit `config.php` ilə idarə olunur. Go-da isə build zamanı fərqli kod birləşdirilir — runtime yük yoxdur, binary incədir.

## Niyə Vacibdir

- Cross-platform CLI alətlər: bir kodla Linux, macOS, Windows binary yaratmaq
- Platform API-ları: Linux `epoll`, macOS `kqueue`, Windows `IOCP` — hər platforma ayrıca
- Integration test-lər adi `go test`-ə qarışmasın
- Debug/tracing kodu production binary-da olmadığı üçün overhead yoxdur
- CGO olmadan statik binary: Docker, Alpine Linux üçün vacib

## Əsas Anlayışlar

### Sintaksis (Go 1.17+)

```go
//go:build <constraint>

// MÜTLƏQ boş sətir olmalıdır build tag ilə package arasında:
//go:build linux

package main
```

### Boolean operatorlar

```go
//go:build linux && amd64        // VƏ
//go:build linux || darwin       // VƏ YA
//go:build !windows              // YOX
//go:build (linux || darwin) && !arm  // qruplaşdırma
```

### Fayl adı ilə constraint (ən sadə)

```
config_linux.go         → yalnız Linux
config_darwin.go        → yalnız macOS
config_windows.go       → yalnız Windows
config_linux_amd64.go   → Linux AMD64
impl_test.go            → yalnız test
```

### Mövcud GOOS dəyərləri

```
linux, darwin, windows, freebsd, openbsd, plan9, js, wasip1, ...
```

### Mövcud GOARCH dəyərləri

```
amd64, arm64, arm, 386, mips, mips64, riscv64, wasm, ...
```

## Praktik Baxış

### Nə vaxt build tag, nə vaxt runtime yoxlama?

```go
// Build tag: compile-time seçim (daha performantlı)
//go:build linux
func getHomeDir() string { return os.Getenv("HOME") }

// Runtime: açar-dəyər sabit deyilsə
func getHomeDir() string {
    if runtime.GOOS == "windows" {
        return os.Getenv("USERPROFILE")
    }
    return os.Getenv("HOME")
}
```

Kiçik fərqlər üçün runtime yoxlama sadədir. Böyük platform-specific implementasiya üçün ayrı fayl + build tag daha yaxşıdır.

### Trade-off-lar

- Build tag-lar: kod paylaşımı çətinləşir, eyni funksiya birdən çox yerdə yazılır
- Fayl proliferasiya: çox platform × çox funksiya = çox fayl
- Test: hər platform üçün ayrıca CI matrix lazım (GitHub Actions matrix)
- Feature flag: binary-ı yenidən build etmək lazım — runtime flag-dan fərqli

### Anti-pattern-lər

```go
// YANLIŞ: package-dən əvvəl boş sətir yoxdur
//go:build linux
package main  // BUG: build tag işləmir!

// YANLIŞ: köhnə format ilə yeni format qarışdırılır
// +build linux
//go:build linux
package main  // ikiləşmə, biri işləmir

// DOĞRU:
//go:build linux

package main
```

```go
// YANLIŞ: build tag-ı şərhdən sonra yazılır
// Bu paketi build edirik
//go:build linux  // XƏTA — şərhdən sonra gəlməməlidir

// DOĞRU: faylın ən başında
//go:build linux

// Bu paketi build edirik
package main
```

## Nümunələr

### Nümunə 1: Platform-specific fayl sistemi əməliyyatları

```go
// fayl: fileutils/path_unix.go
//go:build linux || darwin || freebsd

package fileutils

import (
    "os"
    "path/filepath"
)

func ConfigDir() string {
    if xdg := os.Getenv("XDG_CONFIG_HOME"); xdg != "" {
        return filepath.Join(xdg, "myapp")
    }
    home, _ := os.UserHomeDir()
    return filepath.Join(home, ".config", "myapp")
}

func TempDir() string {
    return "/tmp/myapp"
}

func IsHidden(name string) bool {
    return len(name) > 0 && name[0] == '.'
}
```

```go
// fayl: fileutils/path_windows.go
//go:build windows

package fileutils

import (
    "os"
    "path/filepath"
)

func ConfigDir() string {
    appData := os.Getenv("APPDATA")
    if appData == "" {
        appData = os.Getenv("USERPROFILE")
    }
    return filepath.Join(appData, "myapp")
}

func TempDir() string {
    return filepath.Join(os.Getenv("TEMP"), "myapp")
}

func IsHidden(name string) bool {
    // Windows-da gizli faylları GetFileAttributes ilə yoxlamaq lazımdır
    // Sadə fallback:
    return len(name) > 0 && name[0] == '.'
}
```

```go
// fayl: fileutils/path_linux.go
//go:build linux

package fileutils

import (
    "os"
    "os/exec"
)

// LinuxSpecific — yalnız Linux-da mövcuddur
func SendNotification(title, body string) error {
    return exec.Command("notify-send", title, body).Run()
}

// Systemd service faylını izlə
func WatchServiceLog(serviceName string) (<-chan string, error) {
    cmd := exec.Command("journalctl", "-f", "-u", serviceName)
    // ... stream output
    return make(chan string), nil
}
```

### Nümunə 2: Feature flag-lar — premium build

```go
// fayl: features/premium.go
//go:build premium

package features

import "fmt"

// PremiumEnabled — yalnız premium build-də true
const PremiumEnabled = true

type AdvancedAnalytics struct {
    // premium funksionallıq
}

func NewAdvancedAnalytics() *AdvancedAnalytics {
    return &AdvancedAnalytics{}
}

func (a *AdvancedAnalytics) GenerateReport() string {
    return "Premium hesabat generasiyası"
}

func init() {
    fmt.Println("[Premium] Ətraflı analitika aktiv")
}
```

```go
// fayl: features/premium_stub.go
//go:build !premium

package features

// Stub — premium olmayan build-də
const PremiumEnabled = false

type AdvancedAnalytics struct{}

func NewAdvancedAnalytics() *AdvancedAnalytics {
    return &AdvancedAnalytics{}
}

func (a *AdvancedAnalytics) GenerateReport() string {
    return "Premium hesabat üçün premium versiyaya keçin"
}
```

```go
// fayl: main.go
package main

import (
    "fmt"
    "myapp/features"
)

func main() {
    analytics := features.NewAdvancedAnalytics()
    fmt.Println(analytics.GenerateReport())
    fmt.Printf("Premium aktivdir: %v\n", features.PremiumEnabled)
}

// Build komandaları:
// Standard:  go build -o myapp ./...
// Premium:   go build -tags premium -o myapp-premium ./...
```

### Nümunə 3: Debug tracing — production-da sıfır overhead

```go
// fayl: trace/trace_debug.go
//go:build debug

package trace

import (
    "fmt"
    "runtime"
    "time"
)

// Debug build-də tam tracing

type Tracer struct {
    enabled bool
}

func New() *Tracer {
    fmt.Println("[TRACE] Debug tracing aktiv")
    return &Tracer{enabled: true}
}

func (t *Tracer) Start(name string) func() {
    start := time.Now()
    _, file, line, _ := runtime.Caller(1)
    fmt.Printf("[TRACE] START %s (%s:%d)\n", name, file, line)

    return func() {
        duration := time.Since(start)
        fmt.Printf("[TRACE] END   %s → %v\n", name, duration)
    }
}

func (t *Tracer) Log(format string, args ...interface{}) {
    fmt.Printf("[TRACE] "+format+"\n", args...)
}
```

```go
// fayl: trace/trace_release.go
//go:build !debug

package trace

// Release build-də: no-op, sıfır overhead

type Tracer struct{}

func New() *Tracer { return &Tracer{} }

// inline-lanır, binary-da heç bir kod qalmır
func (t *Tracer) Start(name string) func() { return func() {} }
func (t *Tracer) Log(format string, args ...interface{}) {}
```

```go
// İstifadə (eyni kod hər iki build-də işləyir):
package main

import "myapp/trace"

func processOrder(id int) {
    t := trace.New()
    defer t.Start("processOrder")()
    t.Log("Sifariş emalı: %d", id)
    // ...
}

// go build -o app-debug -tags debug ./...   → tam tracing
// go build -o app ./...                      → sıfır overhead
```

### Nümunə 4: Integration test ayrılması

```go
// fayl: db/postgres_integration_test.go
//go:build integration

package db_test

import (
    "context"
    "testing"
    "os"

    "github.com/stretchr/testify/require"
)

// Bu test yalnız `go test -tags integration ./...` ilə işləyir

func TestPostgresConnection(t *testing.T) {
    dsn := os.Getenv("TEST_DATABASE_URL")
    if dsn == "" {
        t.Skip("TEST_DATABASE_URL set edilməyib")
    }

    db, err := Connect(dsn)
    require.NoError(t, err)
    defer db.Close()

    err = db.PingContext(context.Background())
    require.NoError(t, err)
}

func TestUserRepository_Integration(t *testing.T) {
    dsn := os.Getenv("TEST_DATABASE_URL")
    if dsn == "" {
        t.Skip("TEST_DATABASE_URL set edilməyib")
    }

    // Real DB ilə test
    // ...
}
```

```go
// fayl: db/postgres_test.go
// (build tag yoxdur — hər zaman işləyir)

package db_test

import (
    "testing"
    "github.com/stretchr/testify/assert"
)

func TestBuildQuery(t *testing.T) {
    q := buildSelectQuery("users", map[string]interface{}{"id": 1})
    assert.Contains(t, q, "WHERE")
}
```

```bash
# Normal test: yalnız unit test-lər
go test ./...

# Integration test-lər daxil:
TEST_DATABASE_URL="postgres://..." go test -tags integration ./...

# CI/CD fərqli job-lar:
# job: unit-tests    → go test ./...
# job: integration   → TEST_DATABASE_URL=... go test -tags integration ./...
```

### Nümunə 5: Cross-compilation script

```bash
#!/bin/bash
# build-all.sh — bütün platformalar üçün build

APP_NAME="myapp"
VERSION=$(git describe --tags --always)
BUILD_FLAGS="-ldflags=-X main.Version=${VERSION}"

platforms=(
    "linux/amd64"
    "linux/arm64"
    "darwin/amd64"
    "darwin/arm64"
    "windows/amd64"
)

for platform in "${platforms[@]}"; do
    GOOS="${platform%/*}"
    GOARCH="${platform#*/}"
    output="dist/${APP_NAME}-${VERSION}-${GOOS}-${GOARCH}"

    if [ "$GOOS" = "windows" ]; then
        output="${output}.exe"
    fi

    echo "Building: $output"
    CGO_ENABLED=0 GOOS=$GOOS GOARCH=$GOARCH \
        go build $BUILD_FLAGS -o "$output" ./cmd/api

    if [ $? -ne 0 ]; then
        echo "XƏTA: $platform build uğursuz"
        exit 1
    fi
done

echo "Bütün platformalar üçün build tamamlandı"
ls -lh dist/
```

```go
// Version injection:
// main.go
package main

import "fmt"

var Version = "dev" // ldflags ilə əvəz olunur

func main() {
    fmt.Println("Versiya:", Version)
}

// go build -ldflags="-X main.Version=1.2.3" ./...
```

### Nümunə 6: CGO olmadan statik binary

```go
// fayl: storage/storage_cgo.go
//go:build cgo

package storage

// SQLite ilə (CGO tələb edir)
import (
    "database/sql"
    _ "github.com/mattn/go-sqlite3"
)

func NewSQLiteDB(path string) (*sql.DB, error) {
    return sql.Open("sqlite3", path)
}
```

```go
// fayl: storage/storage_nocgo.go
//go:build !cgo

package storage

import "fmt"

// CGO olmadıqda yalnız in-memory store
func NewSQLiteDB(_ string) (interface{}, error) {
    return nil, fmt.Errorf("SQLite CGO tələb edir; CGO_ENABLED=1 istifadə edin")
}
```

```bash
# CGO ilə (dinamik link — SQLite işləyir):
go build -o app ./...

# CGO olmadan (statik binary — Docker Alpine üçün ideal):
CGO_ENABLED=0 go build -o app ./...

# Docker multi-stage:
# FROM golang:1.22 AS builder
# RUN CGO_ENABLED=0 go build -o /app ./...
#
# FROM alpine:latest
# COPY --from=builder /app /app
# CMD ["/app"]
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Notification servisi:**
`Notify(title, body string)` funksiyasını üç platform üçün yazın: Linux (`notify-send`), macOS (`osascript`), Windows (stub). Build tag-larla ayrılsın.

**Tapşırıq 2 — Benchmark tag:**
`//go:build bench` tag-lı benchmark faylı yaradın. Normal `go test ./...`-da işləməsin, `go test -tags bench -bench ./...`-da işləsin.

**Tapşırıq 3 — Version injection:**
`go build -ldflags="-X main.Version=v1.0.0 -X main.BuildTime=$(date)"` ilə version + build time-ı binary-a yerləşdirin. `--version` flag-ı əlavə edin.

**Tapşırıq 4 — Multi-platform CI:**
GitHub Actions matrix strategy ilə `linux/amd64`, `darwin/arm64`, `windows/amd64` üçün eyni anda build + test işlədin.

**Tapşırıq 5 — CGO-free Docker:**
`CGO_ENABLED=0` ilə static binary qurun. `scratch` Docker image-da işləyin. Image ölçüsünü ölçün.

## Əlaqəli Mövzular

- [14-packages-and-modules](14-packages-and-modules.md) — Go modullar
- [22-init-and-modules](22-init-and-modules.md) — init funksiya
- [70-docker-and-deploy](70-docker-and-deploy.md) — Docker multi-stage build
- [24-testing](24-testing.md) — test strukturu
- [68-profiling-and-benchmarking](68-profiling-and-benchmarking.md) — benchmark tag-lar
