# Processes və Signals (Senior)

## İcmal

Go-da xarici prosesləri idarə etmək, shell komandaları icra etmək, OS signal-larını tutmaq — production sistemlərinin əsas tələblərindəndir. `os/exec` paketi shell komandası icrasını, `os/signal` paketi SIGINT/SIGTERM kimi Unix signal-larının tutulmasını təmin edir. DevOps automation, deployment script, monitoring agent — bu mövzu bu tip alətlər yazmaq üçün vacibdir.

## Niyə Vacibdir

- **Deployment script-ləri:** `git pull`, `go build`, `systemctl restart` kimi komandalar
- **CI/CD pipeline:** Test, lint, build komandalarını Go-dan icra etmək
- **Signal handling:** Kubernetes SIGTERM göndərir — düzgün tutulmazsa data itkisi
- **Process monitoring:** Xarici servisin işlədiyini yoxlamaq
- **Graceful shutdown:** SIGTERM → cleanup → SIGKILL (30s timeout) — Kubernetes pattern

## Əsas Anlayışlar

### exec.Command — Komanda İcra Etmək

```go
cmd := exec.Command("ls", "-la")   // proqram + argumentlər
out, err := cmd.Output()           // stdout qaytarır
out, err := cmd.CombinedOutput()   // stdout + stderr birlikdə
err = cmd.Run()                    // gözlə, yalnız error qaytarır
err = cmd.Start()                  // asinxron başlat, gözləmə
```

**Mühüm:** `exec.Command("bash", "-c", userInput)` — injection riski! İstifadəçi girişini heç vaxt shell komandası kimi işlətməyin.

### stdout/stderr Yönləndirmə

```go
cmd := exec.Command("go", "build", "./...")
cmd.Stdout = os.Stdout  // birbaşa terminalə
cmd.Stderr = os.Stderr

var buf bytes.Buffer
cmd.Stdout = &buf       // buffer-a topla

cmd.Stdout = io.MultiWriter(os.Stdout, logFile) // hər ikisi
```

### Exit Kodu

```go
err := cmd.Run()
if exitErr, ok := err.(*exec.ExitError); ok {
    code := exitErr.ExitCode()
    // 0 — uğurlu, 1 — ümumi xəta, 2 — yanlış istifadə
}
```

### Unix Signal-ları

| Signal | Nömrə | Mənası | Tutula bilir? |
|--------|-------|--------|--------------|
| SIGHUP | 1 | Terminal bağlandı / config reload | Bəli |
| SIGINT | 2 | Ctrl+C | Bəli |
| SIGTERM | 15 | kill (xoş dayandırma) | Bəli |
| SIGKILL | 9 | Zorla öldürmə | Xeyr |
| SIGUSR1 | 10 | İstifadəçi siqnalı 1 | Bəli |
| SIGUSR2 | 12 | İstifadəçi siqnalı 2 | Bəli |

### signal.Notify

```go
sigCh := make(chan os.Signal, 1)
signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)

sig := <-sigCh // bloklayır
fmt.Println("Alındı:", sig)
```

**Buffered channel** (capacity ≥ 1) vacibdir — əks halda siqnal itə bilər.

### syscall.Exec — Prosesi Əvəz Etmək

`syscall.Exec` cari Go prosesini başqa proqramla əvəz edir. Shell-in `exec` komandasına bənzər:

```go
binary, _ := exec.LookPath("bash")
syscall.Exec(binary, []string{"bash"}, os.Environ())
// Bu sətirə çatılmır — Go prosesi artıq yoxdur
```

Real use-case: `docker exec`, container init sistemi.

### PID İdarəsi

```go
pid := os.Getpid()   // cari proses ID
ppid := os.Getppid() // parent proses ID

// Digər prosesə signal göndər
process, _ := os.FindProcess(pid)
process.Signal(syscall.SIGTERM)
```

## Praktik Baxış

### Real Layihələrdə İstifadə

**Deployment tool:**
```go
steps := [][]string{
    {"git", "pull", "origin", "main"},
    {"go", "build", "-o", "bin/app", "./cmd/api"},
    {"systemctl", "restart", "myapp"},
}
for _, step := range steps {
    cmd := exec.Command(step[0], step[1:]...)
    cmd.Stdout = os.Stdout
    if err := cmd.Run(); err != nil {
        log.Fatalf("Step failed: %v", err)
    }
}
```

**Kubernetes pod-da SIGTERM:**
```
1. Kubernetes → SIGTERM göndərir
2. App → yeni request qəbul etməyi dayandırır
3. App → mövcud request-ləri tamamlayır
4. App → DB bağlantılarını bağlayır
5. App → çıxır (exit 0)
30 saniyə sonra: Kubernetes → SIGKILL (zorla)
```

**SIGUSR1 ilə config reload:**
```go
signal.Notify(sigCh, syscall.SIGUSR1)
// kill -SIGUSR1 <PID>
// → config faylını yenidən oxu, server-i yenidən başlatma
```

### Pipe — Komandaları Zəncirlə

```go
// echo "hello" | tr 'a-z' 'A-Z'
echo := exec.Command("echo", "hello world")
tr := exec.Command("tr", "a-z", "A-Z")

pipe, _ := echo.StdoutPipe()
tr.Stdin = pipe
tr.Stdout = os.Stdout

tr.Start()
echo.Run()
tr.Wait()
```

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| exec.Command | OS komandaları birbaşa | Shell injection riski, OS-a bağımlı |
| bash -c | Pipe, glob, redirect dəstəyi | İnjection riski yüksək |
| Native Go | Portable, güvənli | Bəzi alətlər yenidən implement |
| os.Exec | Proses əvəzi, effektiv | Qayıdış yoxdur |

### Anti-pattern-lər

```go
// Anti-pattern 1: İstifadəçi girişini shell-ə vermək
userInput := r.FormValue("name")
exec.Command("bash", "-c", "echo "+userInput).Run() // SHELL INJECTION!

// Düzgün:
exec.Command("echo", userInput).Run() // argument kimi — güvənli

// Anti-pattern 2: Output-u ignore etmək
cmd.Run() // çıxış kodunu bilmirik, xəta varsa?

// Düzgün:
if err := cmd.Run(); err != nil {
    log.Printf("komanda xətası: %v", err)
}

// Anti-pattern 3: os.Exit defer-ləri işlətmir
defer db.Close() // bu işləməyəcək!
os.Exit(1)       // defer-lər atlanır

// Düzgün: os.Exit əvəzinə log.Fatal(err) istifadə edin
// log.Fatal → os.Exit çağırır, amma bu da defer-i keçir
// Əsl düzgün: graceful shutdown pattern (53-graceful-shutdown.md)

// Anti-pattern 4: Signal channel-i unbuffered etmək
sigCh := make(chan os.Signal) // buffer yox!
signal.Notify(sigCh, syscall.SIGTERM)
// Əgər signal gəlsə və goroutine hazır deyilsə — itir

// Düzgün:
sigCh := make(chan os.Signal, 1)
```

## Nümunələr

### Nümunə 1: Build Script

```go
package main

import (
    "fmt"
    "log"
    "os"
    "os/exec"
    "path/filepath"
    "time"
)

type BuildStep struct {
    Name string
    Cmd  string
    Args []string
}

func runStep(step BuildStep) error {
    start := time.Now()
    fmt.Printf("▶ %s...\n", step.Name)

    cmd := exec.Command(step.Cmd, step.Args...)
    cmd.Stdout = os.Stdout
    cmd.Stderr = os.Stderr

    if err := cmd.Run(); err != nil {
        return fmt.Errorf("step '%s' xəta ilə bitdi: %w", step.Name, err)
    }

    fmt.Printf("✓ %s (%.1fs)\n", step.Name, time.Since(start).Seconds())
    return nil
}

func main() {
    outputBinary := filepath.Join("bin", "api")

    steps := []BuildStep{
        {Name: "Lint", Cmd: "go", Args: []string{"vet", "./..."}},
        {Name: "Test", Cmd: "go", Args: []string{"test", "./...", "-race"}},
        {Name: "Build", Cmd: "go", Args: []string{"build", "-o", outputBinary, "./cmd/api"}},
    }

    for _, step := range steps {
        if err := runStep(step); err != nil {
            log.Fatal(err)
        }
    }

    fmt.Printf("\nUğurlu! Binary: %s\n", outputBinary)
}
```

### Nümunə 2: Signal Handler ilə Clean Shutdown

```go
package main

import (
    "fmt"
    "log"
    "os"
    "os/signal"
    "syscall"
    "time"
)

type Worker struct {
    name   string
    stopCh chan struct{}
    doneCh chan struct{}
}

func NewWorker(name string) *Worker {
    return &Worker{
        name:   name,
        stopCh: make(chan struct{}),
        doneCh: make(chan struct{}),
    }
}

func (w *Worker) Start() {
    go func() {
        defer close(w.doneCh)
        log.Printf("[%s] başladı", w.name)

        for {
            select {
            case <-w.stopCh:
                log.Printf("[%s] dayandı", w.name)
                return
            default:
                // iş simulyasiyası
                time.Sleep(500 * time.Millisecond)
                log.Printf("[%s] işləyir...", w.name)
            }
        }
    }()
}

func (w *Worker) Stop() {
    close(w.stopCh)
    <-w.doneCh // bitməyi gözlə
}

func main() {
    workers := []*Worker{
        NewWorker("email-göndərici"),
        NewWorker("cache-yeniləyici"),
        NewWorker("metrik-toplayan"),
    }

    for _, w := range workers {
        w.Start()
    }

    // Signal-ları tut
    sigCh := make(chan os.Signal, 1)
    signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM, syscall.SIGUSR1)

    fmt.Printf("PID: %d\n", os.Getpid())
    fmt.Println("Ctrl+C basın və ya: kill -SIGTERM", os.Getpid())

    for {
        sig := <-sigCh
        switch sig {
        case syscall.SIGUSR1:
            log.Println("SIGUSR1 alındı — konfiqurasiya yenilənir...")
            // reload config

        case syscall.SIGTERM, syscall.SIGINT:
            log.Println("Dayandırma siqnalı alındı. Cleanup başlayır...")

            // Timeout ilə cleanup
            done := make(chan struct{})
            go func() {
                for _, w := range workers {
                    w.Stop()
                }
                close(done)
            }()

            select {
            case <-done:
                log.Println("Bütün worker-lər dayandı. Çıxılır.")
            case <-time.After(10 * time.Second):
                log.Println("Timeout! Zorla çıxılır.")
            }

            os.Exit(0)
        }
    }
}
```

### Nümunə 3: Process Monitor

```go
package main

import (
    "fmt"
    "log"
    "os/exec"
    "time"
)

type ProcessMonitor struct {
    command string
    args    []string
    maxRestarts int
}

func NewMonitor(command string, args ...string) *ProcessMonitor {
    return &ProcessMonitor{
        command:     command,
        args:        args,
        maxRestarts: 5,
    }
}

func (m *ProcessMonitor) Run() {
    restarts := 0
    backoff := time.Second

    for {
        if restarts >= m.maxRestarts {
            log.Fatalf("Maksimum restart sayına çatıldı (%d)", m.maxRestarts)
        }

        log.Printf("Proses başladılır: %s %v (cəhd #%d)",
            m.command, m.args, restarts+1)

        cmd := exec.Command(m.command, m.args...)
        start := time.Now()

        if err := cmd.Run(); err != nil {
            duration := time.Since(start)
            if exitErr, ok := err.(*exec.ExitError); ok {
                log.Printf("Proses çıxdı (kod: %d, müddət: %v)",
                    exitErr.ExitCode(), duration)
            } else {
                log.Printf("Proses xəta ilə bitdi: %v", err)
            }

            restarts++
            log.Printf("%v sonra yenidən başladılacaq...", backoff)
            time.Sleep(backoff)

            // Exponential backoff, max 30s
            backoff *= 2
            if backoff > 30*time.Second {
                backoff = 30 * time.Second
            }
        } else {
            log.Println("Proses uğurla bitdi")
            return
        }
    }
}

func main() {
    // Demo: uğursuz olan bir şey monitor et
    monitor := NewMonitor("ls", "/nonexistent")
    fmt.Printf("Monitor başladı. Hər xətadan sonra yenidən cəhd ediləcək.\n")
    monitor.Run()
}
```

### Nümunə 4: Pipe ilə Output Processing

```go
package main

import (
    "bufio"
    "fmt"
    "os/exec"
    "strings"
)

func runWithLineProcessing(command string, args []string,
    process func(line string)) error {

    cmd := exec.Command(command, args...)

    stdout, err := cmd.StdoutPipe()
    if err != nil {
        return err
    }

    if err := cmd.Start(); err != nil {
        return err
    }

    scanner := bufio.NewScanner(stdout)
    for scanner.Scan() {
        process(scanner.Text())
    }

    return cmd.Wait()
}

func main() {
    fmt.Println("Go faylları analiz edilir:")

    lineCount := 0
    err := runWithLineProcessing("find", []string{".", "-name", "*.go"}, func(line string) {
        line = strings.TrimSpace(line)
        if line != "" {
            lineCount++
            fmt.Printf("  [%d] %s\n", lineCount, line)
        }
    })

    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    fmt.Printf("\nCəmi: %d fayl\n", lineCount)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — CI Pipeline:**
`go vet`, `golangci-lint`, `go test`, `go build` addımlarını ardıcıl icra edən build tool yazın. Hər addımın duration-ını göstərin. Xəta olsa sonrakı addımları keçin.

**Tapşırıq 2 — Config Reload:**
SIGUSR1 siqnalı ilə konfiqurasiya faylını yenidən yükləyən server yazın. Server-i dayandırmadan yeni ayarlar tətbiq olunmalıdır.

**Tapşırıq 3 — Process Supervisor:**
Nginx/Gunicorn kimi sadə process supervisor yazın. Worker proseslər çökərsə avtomatik yenidən başlatsın. `SIGCHLD` siqnalını tutun.

**Tapşırıq 4 — Safe Shell Wrapper:**
`exec.Command`-ı wrap edən `SafeRun(timeout time.Duration, cmd string, args ...string) (string, error)` funksiyası yazın. Context ile timeout implement edin.

## Əlaqəli Mövzular

- [28-context](28-context.md) — Context ilə komanda icrası timeout-u
- [53-graceful-shutdown](53-graceful-shutdown.md) — Signal handling ilə server shutdown
- [27-goroutines-and-channels](27-goroutines-and-channels.md) — Goroutine management
- [70-docker-and-deploy](70-docker-and-deploy.md) — Container-də signal handling
- [71-monitoring-and-observability](71-monitoring-and-observability.md) — Proses monitorinqi
