# Load Testing (Senior)

## İcmal

**Load testing** — tətbiqin müəyyən yük altında necə davrandığını ölçən prosesdir. Go-da benchmark testlər (`testing.B`) unit səviyyəsində, **k6**, **vegeta**, **hey** isə HTTP server səviyyəsində yük testi üçün istifadə olunur. Məqsəd: bottleneck tapmaq, SLO-ları yoxlamaq, regression-ları aşkarlayaq.

## Niyə Vacibdir

- Production incident-lərin böyük hissəsi yüksək trafik altında aşkar olur — öncədən test etmək lazımdır
- Go benchmark-ları: allocation azaltmaq, hotpath optimize etmək
- HTTP load test: P95/P99 latency, throughput (RPS), error rate ölçmək
- CI/CD-yə inteqrasiya: performance regression-ı build zamanı tutmaq

## Əsas Anlayışlar

- **Benchmark** — `func BenchmarkX(b *testing.B)` — Go test framework; `b.N` dəfə işləyir
- **`b.ReportAllocs()`** — hər əməliyyat üçün heap allocation sayı
- **`b.RunParallel`** — concurrent benchmark
- **RPS (Requests Per Second)** — throughput; server capacity göstəricisi
- **P95/P99 latency** — 95%-99% sorğunun cavab vaxtı; ortalama deyil, percentile vacibdir
- **Throughput vs Latency** — RPS artarkən latency artır; müvazinət nöqtəsi tapılmalıdır
- **Warm-up** — JIT/cache effect; ilk sorğular yavaş olur
- **vegeta** — Go ilə yazılmış HTTP load tester; pipeline, rate control
- **k6** — JS scripting ilə flexible load test; cloud execution
- **hey** — sadə CLI; quick smoke test

## Praktik Baxış

**Ne vaxt nə istifadə et:**

| Alət | İstifadə |
|------|---------|
| `testing.B` | Function/package-level micro benchmark |
| `hey` | Quick HTTP sanity check (5 dəqiqə) |
| `vegeta` | CI/CD-ə inteqrasiya, attack files, percentile report |
| `k6` | Complex scenario, virtual users, thresholds, cloud |

**Trade-off-lar:**
- Micro benchmark yanıltıcı ola bilər: compiler inlining, dead code elimination — `sink` dəyişən istifadə et
- Load test mühiti production-a bənzəməlidir: eyni DB, eyni network
- k6 cloud: dağıtıq yük, amma ödənişlidir
- P99 vs ortalama: ortalama yaxşı görünə bilər, amma P99 yüksəkdirsə problem var

**Common mistakes:**
- `b.N` yerinə fixed iteration say istifadə etmək
- Benchmark-da side effect olmayan kodu ölçmək (compiler optimize edir)
- Load test zamanı monitoring olmadan işlətmək — bottleneck haradadır bilinmir

## Nümunələr

### Nümunə 1: Go benchmark əsasları

```go
package main

import (
    "strings"
    "testing"
)

// BenchmarkStringConcat — string birləşdirmə müqayisəsi
func BenchmarkStringConcat(b *testing.B) {
    for i := 0; i < b.N; i++ {
        s := ""
        for j := 0; j < 100; j++ {
            s += "x"
        }
        _ = s
    }
}

func BenchmarkStringBuilder(b *testing.B) {
    for i := 0; i < b.N; i++ {
        var sb strings.Builder
        for j := 0; j < 100; j++ {
            sb.WriteString("x")
        }
        _ = sb.String()
    }
}

// Allocation sayını ölç
func BenchmarkWithAllocs(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        s := make([]int, 100)
        _ = s
    }
}

// Parallel benchmark
func BenchmarkParallel(b *testing.B) {
    b.RunParallel(func(pb *testing.PB) {
        for pb.Next() {
            _ = strings.ToUpper("hello world")
        }
    })
}
```

```bash
# İşlət
go test -bench=. -benchmem ./...

# Nəticə:
# BenchmarkStringConcat-8     200000    8234 ns/op    5328 B/op    99 allocs/op
# BenchmarkStringBuilder-8   2000000     731 ns/op     512 B/op     1 allocs/op
```

### Nümunə 2: HTTP handler benchmark

```go
package handler_test

import (
    "net/http"
    "net/http/httptest"
    "testing"
)

func BenchmarkOrdersHandler(b *testing.B) {
    // Bir dəfə qur
    srv := setupTestServer()
    defer srv.Close()

    b.ResetTimer() // setup vaxtını ölçmə
    b.ReportAllocs()

    b.RunParallel(func(pb *testing.PB) {
        for pb.Next() {
            resp, err := http.Get(srv.URL + "/orders?limit=20")
            if err != nil {
                b.Fatal(err)
            }
            resp.Body.Close()
            if resp.StatusCode != http.StatusOK {
                b.Fatalf("unexpected status: %d", resp.StatusCode)
            }
        }
    })
}

func BenchmarkOrdersHandler_InProcess(b *testing.B) {
    handler := newOrdersHandler(fakeDB())

    b.ResetTimer()
    b.ReportAllocs()

    for i := 0; i < b.N; i++ {
        w := httptest.NewRecorder()
        r := httptest.NewRequest("GET", "/orders?limit=20", nil)
        handler.ServeHTTP(w, r)
    }
}
```

### Nümunə 3: vegeta ilə HTTP load test

```bash
# Quraşdırma
go install github.com/tsenart/vegeta@latest

# Sadə attack — 100 RPS, 30 saniyə
echo "GET http://localhost:8080/orders" | \
    vegeta attack -rate=100 -duration=30s | \
    vegeta report

# Nəticə:
# Requests      [total, rate, throughput]  3000, 100.03, 99.98
# Duration      [total, attack, wait]      30.002s, 29.991s, 10.9ms
# Latencies     [min, mean, 50, 90, 95, 99, max]  2.1ms, 11.2ms, 9.8ms, 18.4ms, 24.1ms, 45.2ms, 102ms
# Success       [ratio]                    99.97%
# Status Codes  [code:count]               200:2999  500:1

# Attack file ilə (çox endpoint)
cat > targets.txt << EOF
GET http://localhost:8080/health
GET http://localhost:8080/orders?limit=10
POST http://localhost:8080/orders
Content-Type: application/json
@order_body.json
EOF

vegeta attack -targets=targets.txt -rate=50 -duration=60s > results.bin
vegeta report results.bin
vegeta plot results.bin > plot.html
```

```go
// vegeta Go library ilə programmatic load test
package loadtest

import (
    "fmt"
    "net/http"
    "time"

    vegeta "github.com/tsenart/vegeta/v12/lib"
)

func RunLoadTest(target string, rps int, duration time.Duration) {
    rate := vegeta.Rate{Freq: rps, Per: time.Second}
    targeter := vegeta.NewStaticTargeter(vegeta.Target{
        Method: "GET",
        URL:    target,
    })

    attacker := vegeta.NewAttacker()
    var metrics vegeta.Metrics

    for res := range attacker.Attack(targeter, rate, duration, "load-test") {
        metrics.Add(res)
    }
    metrics.Close()

    fmt.Printf("P95 latency: %s\n", metrics.Latencies.P95)
    fmt.Printf("P99 latency: %s\n", metrics.Latencies.P99)
    fmt.Printf("Success rate: %.2f%%\n", metrics.Success*100)
    fmt.Printf("Throughput: %.2f RPS\n", metrics.Throughput)

    // SLO check — P99 < 100ms
    if metrics.Latencies.P99 > 100*time.Millisecond {
        fmt.Printf("FAIL: P99 latency %.2fms exceeds 100ms SLO\n",
            float64(metrics.Latencies.P99)/float64(time.Millisecond))
    }
}
```

### Nümunə 4: k6 skript

```javascript
// load_test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
    stages: [
        { duration: '30s', target: 50 },   // ramp-up
        { duration: '1m',  target: 50 },   // steady state
        { duration: '30s', target: 100 },  // spike
        { duration: '30s', target: 0 },    // ramp-down
    ],
    thresholds: {
        http_req_duration: ['p(95)<200', 'p(99)<500'], // SLO
        errors: ['rate<0.01'],                          // max 1% xəta
    },
};

export default function () {
    const res = http.get('http://localhost:8080/orders?limit=10', {
        headers: { 'Authorization': `Bearer ${__ENV.TOKEN}` },
    });

    check(res, {
        'status 200': (r) => r.status === 200,
        'response time < 200ms': (r) => r.timings.duration < 200,
    });

    errorRate.add(res.status !== 200);
    sleep(0.1);
}
```

```bash
# k6 işlət
k6 run load_test.js

# Cloud execution (dağıtıq)
k6 cloud load_test.js

# ENV dəyişən ilə
TOKEN=xxx k6 run load_test.js
```

### Nümunə 5: Benchmark comparison (benchstat)

```bash
# İki branch-ı müqayisə et
git checkout main
go test -bench=BenchmarkOrdersHandler -count=5 ./... > old.txt

git checkout feature/optimize
go test -bench=BenchmarkOrdersHandler -count=5 ./... > new.txt

# benchstat ilə statistik müqayisə
go install golang.org/x/perf/cmd/benchstat@latest
benchstat old.txt new.txt

# Nəticə:
#                           old          new         delta
# BenchmarkOrdersHandler  11.2ms ± 3%  8.4ms ± 2%  -25.0%  (p=0.008 n=5+5)
```

## Praktik Tapşırıqlar

1. **Micro benchmark:** JSON marshal/unmarshal üçün 3 fərqli approach benchmark et; `benchstat` ilə müqayisə et
2. **HTTP benchmark:** `/orders` endpoint üçün vegeta ilə 100 RPS × 60s test; P95/P99 report al
3. **k6 scenario:** Ramp-up + steady + spike load pattern yaz; P95 < 200ms threshold əlavə et
4. **CI integration:** `go test -bench=. -benchmem` CI pipeline-a əlavə et; regression aşkarla

## PHP ilə Müqayisə

```
PHP/Laravel                    →  Go
────────────────────────────────────────
k6 / JMeter                    →  k6 / vegeta (eyni alətlər)
Laravel Benchmark facade        →  testing.B
Blackfire.io profiler           →  go tool pprof
PHP benchmark loop (microtime)  →  b.N loop (framework idarə edir)
```

PHP-də benchmark test framework yoxdur — manual `microtime()` istifadə olunur. Go-da `testing.B` framework özü `b.N`-i avtomatik idarə edir, statistical variance minimize edir.

## Əlaqəli Mövzular

- [../backend/01-http-server](../backend/01-http-server.md) — load test edilən server
- [24-monitoring-and-observability](24-monitoring-and-observability.md) — load test zamanı metrics
- [../backend/38-opentelemetry-tracing](../backend/38-opentelemetry-tracing.md) — bottleneck tapmaq
- [42-feature-flags](42-feature-flags.md) — flag altında performance testi
- [../core/29-profiling](../core/29-profiling.md) — pprof ilə hotspot analizi
