# Event Loop (Senior ⭐⭐⭐)

## İcmal
Event loop — single-threaded mühitdə non-blocking I/O-u mümkün edən mexanizmdir. Node.js, Python asyncio, Go runtime, Nginx — hamısı müxtəlif formalarda event-driven model istifadə edir. "Bir thread minlərlə concurrent connection" — bu event loop-un vədidir. Senior interview-larda bu mexanizmin niyə işlədiyini izah etmək tələb olunur.

## Niyə Vacibdir
PHP developer olaraq event loop anlayışı Node.js, Go, ya da async framework-lərə keçid üçün kritikdir. İnterviewer bu sualla sizin "single-threaded niyə concurrent-dir?" paradoksunu izah edə bilmənizi, blocking code-un event loop-u necə öldürdüyünü, OS-level mexanizmi bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

- **Event Loop Əsas İdeya:** Tək thread — I/O gözlərkən CPU-nu boş saxlama; callback/continuation-ları ardıcıl icra et
- **Call Stack:** Sinxron kod icra edən stack — function call-lar buraya gəlir; boş olduqda event queue yoxlanır
- **Event Queue (Macrotask Queue):** Tamamlanan I/O callback-ləri, `setTimeout`, `setInterval` — call stack boşaldıqda icra olunur
- **Microtask Queue:** Promise callback-ləri (`then`, `catch`), `queueMicrotask` — macrotask-dan əvvəl, call stack boşaldıqda dərhal icra olunur
- **epoll (Linux):** OS kernel-in async I/O event notification mexanizmi — "bu file descriptor hazırdır" bildirişi; O(1) event detection
- **kqueue (macOS/BSD):** macOS-un epoll ekvivalenti — `kevent()` system call
- **IOCP (Windows):** Windows-un I/O Completion Ports — proactor pattern əsaslı async I/O
- **libuv:** Node.js-in underlying C library — OS-in event notification sistemini (epoll/kqueue/IOCP) abstrakt edir; thread pool da saxlayır
- **Blocking Code:** CPU-intensive kod event loop-u tutur — bu müddətdə heç bir callback icra ola bilmir, server yanıt verə bilmir
- **Worker Threads:** CPU-bound task-ları event loop-dan çıxarmaq — Node.js `worker_threads` modulu, Python `ProcessPoolExecutor`
- **setImmediate vs setTimeout(0):** Node.js event loop faza fərqi — `setImmediate` I/O callback-lərindən sonra, `setTimeout(0)` timer fazasında
- **process.nextTick:** Cari əməliyyat bitdikdən sonra, I/O callback-lərindən əvvəl, microtask queue-dan da əvvəl icra olunur
- **Reactor Pattern:** Event loop-un dizayn pattern-i — event demultiplexer (epoll) + event handler-lər; Nginx, libuv istifadə edir
- **Proactor Pattern:** I/O tamamlandıqda callback çağırılır — Windows IOCP, Boost.Asio; Reactor-dan fərq: OS tamamlama bildirişi göndərir
- **Python asyncio Event Loop:** `asyncio.get_event_loop()` — `async def` + `await`; single-threaded cooperative multitasking
- **Tokio (Rust):** Multi-threaded async runtime — work-stealing scheduler; hər CPU core-a bir thread, goroutine-vari task-lar
- **Go Runtime Scheduler:** M:N model — N goroutine M OS thread-ə map edilir; I/O-da goroutine yields, OS thread başqasını götürür
- **Starvation:** Uzun sync kod event loop-u tutur — bütün digər callback-lər gözləyir; latency spike
- **Backpressure in Event Loop:** Gelen event-lər tez, callback-lər yavaş — event queue böyüyür; flow control lazımdır

---

## Praktik Baxış

**Interview-da yanaşma:**
- "Bir thread minlərlə connection?" — I/O gözlərkən CPU-nu başqa callback-lərə ver; diaqramla izah et
- "Event loop-u niyə bloklamaq olmaz?" — Bütün pending callback-lər gözləyir; latency spike; TTFB artır
- Node.js cluster mode-unu qeyd edin — event loop + multi-core: `cluster.fork()`

**Follow-up suallar:**
1. "Synchronous CPU-intensive kod event loop-a nə edir?" — Bloklar; bütün digər request-lər callback queue-da gözləyir
2. "`setImmediate` vs `setTimeout(0)` fərqi?" — `setImmediate` I/O callback fazasında; `setTimeout(0)` timer fazasında; əksər hallarda eyni, amma I/O callback içərisindən fərqlidir
3. "Go-nun goroutine scheduler-i event loop-dan nə ilə fərqlənir?" — Event loop single-threaded; Go M:N — çoxlu OS thread-ləri var, goroutine-lər onlara map edilir; preemptive (Go 1.14+)
4. "Nginx niyə event-driven?" — Her connection üçün thread yaratmaq deyil; az sayda worker process minlərlə connection; epoll ilə
5. "Node.js necə CPU-intensive iş görür?" — `worker_threads` modulu; ayrı thread-də icra, postMessage ilə nəticə
6. "PHP-FPM vs Nginx məhsuldarlığı fərqi niyədir?" — PHP-FPM hər request üçün process; Nginx event-driven — 10.000 connection = 10.000 PHP process vs Nginx-in birkaç worker

**Code review red flags:**
- Node.js-də `fs.readFileSync()`, `execSync()` — blocking; event loop donur
- `for (... await ...)` loop-unda paralel edilə bilən task-lar sequential edilir
- Event handler içərisindən sync heavy loop — `while(true)` kimi
- Python-da async context-də `time.sleep()` əvəzinə `asyncio.sleep()` istifadə etməmək

**Production debugging ssenariləri:**
- Node.js-də latency spike: `clinic.js flame` ilə CPU-intensive callback tapılır; worker thread-ə köçürülür
- Python asyncio "blocking call detected": `asyncio.set_debug(True)` ilə; `time.sleep` tapılır
- Event queue uzanması: "high event loop lag" alert; `perf_hooks.monitorEventLoopDelay()` Node.js-də
- Go goroutine leak: `runtime.NumGoroutine()` artır; goroutine-lər select-dən əbədi gözləyir; context cancel lazımdır

---

## Nümunələr

### Tipik Interview Sualı
"Node.js single-threaded-dir. Eyni anda 10.000 HTTP request qəbul edə bilir. Bu necə mümkündür?"

### Güclü Cavab
Node.js single-threaded JavaScript icra edir, amma I/O non-blocking-dir. HTTP request gəlir, event loop onu qəbul edir, database query başladılır — lakin thread gözləmir. Kernel (epoll/kqueue) query cavabı hazır olduqda event loop-a notification göndərir. Bu müddətdə event loop digər 9999 request-in callback-lərini icra edə bilər.

Əgər 10.000 request eyni anda gəlsə, hər biri 100ms I/O gözləsə — bir thread 1 saniyəyə yaxın müddətdə hamısını tamamlayar (overlap edən gözləmə), blocking modeldə isə 1.000 saniyə çəkərdi.

Vacib qoruyucu: CPU-intensive hesablama event loop-u bloklar — bütün digər request-lər gözləyir. Bu halda `worker_threads` ilə ayrı thread-ə göndərmək lazımdır. Production-da CPU core sayına görə `cluster` modulu istifadə edilir.

### Kod Nümunəsi

```javascript
// ── Node.js Event Loop: Execution Order ─────────────────────────
console.log('1: Sync — call stack');

setTimeout(() => {
    console.log('6: setTimeout(0) — macrotask queue');
}, 0);

setImmediate(() => {
    console.log('5: setImmediate — check phase (I/O callback-dən sonra)');
});

Promise.resolve().then(() => {
    console.log('3: Promise.then — microtask queue');
});

process.nextTick(() => {
    console.log('2: nextTick — microtask-dan da əvvəl');
});

queueMicrotask(() => {
    console.log('4: queueMicrotask — microtask queue');
});

console.log('7: Sync — call stack');

// Çıxış sırası: 1, 7, 2, 3, 4, 5, 6
// Call Stack → nextTick → Microtask → Macrotask (setImmediate → setTimeout)
```

```javascript
// ── Blocking vs Non-blocking ─────────────────────────────────────
const http = require('http');
const { Worker, isMainThread, parentPort } = require('worker_threads');

if (isMainThread) {
    const server = http.createServer((req, res) => {
        if (req.url === '/heavy') {
            // YANLIŞ: CPU-intensive loop event loop-u bloklar
            // const start = Date.now();
            // while (Date.now() - start < 2000) {}  // 2s → bütün request-lər gözləyir!
            // res.end('done');

            // DÜZGÜN: Worker thread-ə göndər
            const worker = new Worker(__filename, {
                workerData: { task: 'heavy', n: 1e9 }
            });
            worker.on('message', result => {
                res.end(`Result: ${result}`);
            });
            worker.on('error', err => {
                res.writeHead(500);
                res.end(err.message);
            });
        } else {
            // Digər endpoint-lər heavy task işlərkən də cavab verir
            res.end('Hello! Event loop alive.');
        }
    });
    server.listen(3000);
    console.log('Server running on :3000');
} else {
    // Worker thread-də icra olunur
    const { workerData } = require('worker_threads');
    if (workerData.task === 'heavy') {
        let count = 0;
        for (let i = 0; i < workerData.n; i++) count++;
        parentPort.postMessage(count);
    }
}

// ── async generator: böyük data event loop-u bloklamadan ────────
async function* streamLargeDataset(batchSize = 100) {
    let offset = 0;
    while (true) {
        const rows = await db.query(
            'SELECT * FROM events ORDER BY id LIMIT ? OFFSET ?',
            [batchSize, offset]
        );
        if (rows.length === 0) break;
        for (const row of rows) {
            yield row; // Bir-bir qaytarır; bütün dataset-i yükləmir
        }
        offset += batchSize;
        // await — event loop-a qayıtma nöqtəsi
    }
}

async function processAllEvents() {
    let processed = 0;
    for await (const event of streamLargeDataset()) {
        await processEvent(event); // Event loop-u tutmur
        processed++;
        if (processed % 1000 === 0) {
            console.log(`Processed: ${processed}`);
        }
    }
}
```

```javascript
// ── Event Loop Diaqnostika ───────────────────────────────────────
const { monitorEventLoopDelay } = require('perf_hooks');

// Event loop lag monitoring
const histogram = monitorEventLoopDelay({ resolution: 10 });
histogram.enable();

setInterval(() => {
    const lag = histogram.mean / 1e6; // nanoseconds → milliseconds
    if (lag > 100) {
        console.warn(`Event loop lag: ${lag.toFixed(2)}ms — possible blocking code!`);
    }
    console.log(`EL lag: mean=${(histogram.mean/1e6).toFixed(2)}ms, p99=${(histogram.percentile(99)/1e6).toFixed(2)}ms`);
    histogram.reset();
}, 5000);

// clinic.js — production event loop profiling
// npm install -g clinic
// clinic doctor -- node server.js
// clinic flame -- node server.js  ← CPU flamegraph
// clinic bubbleprof -- node server.js  ← async operasiyalar
```

```python
# ── Python asyncio event loop ────────────────────────────────────
import asyncio
import aiohttp
import time

# YANLIŞ: Async context-də blocking call
async def bad_handler(url: str):
    time.sleep(2)              # BLOCKING! Event loop donur — bütün coroutine-lər gözləyir
    return await fetch(url)

# DÜZGÜN: Non-blocking sleep
async def good_handler(url: str):
    await asyncio.sleep(2)    # Non-blocking — event loop başqa coroutine icra edir
    return await fetch(url)

# DÜZGÜN: CPU-bound task-ı ProcessPoolExecutor-a göndər
import concurrent.futures

async def handle_with_cpu_work(data: bytes):
    loop = asyncio.get_running_loop()

    with concurrent.futures.ProcessPoolExecutor() as executor:
        # heavy_compute() process pool-da icra olunur — event loop bloklanmır
        result = await loop.run_in_executor(executor, heavy_compute, data)

    return result

# Event loop monitoring — asyncio debug mode
async def main():
    # Debug mode: 100ms-dən uzun əməliyyatlar log olunur
    asyncio.get_event_loop().set_debug(True)

    async with aiohttp.ClientSession() as session:
        tasks = [
            asyncio.create_task(fetch(session, f'https://httpbin.org/delay/1'))
            for _ in range(50)
        ]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        successes = sum(1 for r in results if not isinstance(r, Exception))
        print(f"Completed: {successes}/50")

asyncio.run(main())
```

```go
// ── Go: Event loop yoxdur, M:N scheduler ────────────────────────
package main

import (
    "context"
    "fmt"
    "net/http"
    "runtime"
    "sync/atomic"
    "time"
)

func main() {
    fmt.Printf("Available CPUs: %d\n", runtime.NumCPU())
    fmt.Printf("GOMAXPROCS: %d\n", runtime.GOMAXPROCS(0)) // Default: NumCPU

    var activeGoroutines int64

    http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        // Hər request üçün goroutine artıq var (net/http edir)
        // Goroutine I/O-da yields → OS thread başqa goroutine-ə keçir

        atomic.AddInt64(&activeGoroutines, 1)
        defer atomic.AddInt64(&activeGoroutines, -1)

        // Simulated DB query — goroutine yields burada
        ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
        defer cancel()

        result, err := queryDB(ctx, "SELECT 1")
        if err != nil {
            http.Error(w, err.Error(), 500)
            return
        }

        fmt.Fprintf(w, "Result: %v, Active goroutines: %d",
            result, atomic.LoadInt64(&activeGoroutines))
    })

    http.HandleFunc("/metrics", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintf(w, "Goroutines: %d\n", runtime.NumGoroutine())
        var m runtime.MemStats
        runtime.ReadMemStats(&m)
        fmt.Fprintf(w, "Heap: %d MB\n", m.HeapAlloc/1024/1024)
    })

    fmt.Println("Server on :8080")
    http.ListenAndServe(":8080", nil)
    // 10.000 concurrent request → 10.000 goroutine (~20MB)
    // vs 10.000 OS thread → ~10 GB stack memory
}

// Go-da blocking I/O: goroutine göründüyündə blocks, amma OS thread bloklanmır
// Go runtime: I/O-da goroutine → park, OS thread → next goroutine
// Bu Go-nun "event loop"udur, multi-threaded
```

```nginx
# Nginx event-driven model konfigurasiyası
# nginx.conf

# Worker sayı = CPU core sayı
worker_processes auto;

events {
    # Hər worker-in eyni anda idarə edə biləcəyi connection sayı
    # worker_processes * worker_connections = max connections
    worker_connections 10000;

    # epoll istifadəsi (Linux) — O(1) event notification
    use epoll;

    # Yeni event-lər üçün mümkün qədər çox connection qəbul et
    multi_accept on;
}

http {
    # Upstream PHP-FPM pool
    upstream php_fpm {
        server unix:/run/php-fpm.sock;
        keepalive 32;  # Connection-ları saxla — yenidən yaratma overhead yoxdur
    }

    server {
        location ~ \.php$ {
            fastcgi_pass php_fpm;
            # Nginx event-driven, PHP-FPM process-based
            # Nginx az sayda process ilə 10.000 connection
            # PHP-FPM 100 process → 100 paralel PHP execution
        }
    }
}
# Nginx model: event loop (az process, çox connection)
# PHP-FPM model: process pool (az connection, tam izolasiya)
# İkisi birlikdə: Nginx I/O qatı, PHP-FPM execution qatı
```

---

## Praktik Tapşırıqlar

- Node.js-də `setTimeout(0)` vs `Promise.resolve().then()` execution order-ini test edin; `process.nextTick` əlavə edin
- CPU-intensive loop ilə HTTP server-i bloklamağı reproduce edin; worker thread ilə düzəldin; `clinic.js` ilə profil çıxarın
- Python asyncio-da `time.sleep(1)` vs `asyncio.sleep(1)` fərqini ölçün: eyni anda 100 coroutine ilə
- `ab -c 100 -n 1000` ilə Node.js event loop-lu server vs thread-per-request server-i benchmark edin
- Go-da 100.000 goroutine çalışdırın, `runtime.NumGoroutine()` və `runtime.NumCPU()` fərqini müşahidə edin

## Əlaqəli Mövzular
- `06-async-await.md` — Event loop üzərindəki abstraction
- `05-thread-pools.md` — Thread-based concurrent model ilə müqayisə
- `01-threads-vs-processes.md` — Process/thread model konteksti
- `02-race-conditions.md` — Single-threaded model race condition-u azaldır, amma aradan qaldırmır
