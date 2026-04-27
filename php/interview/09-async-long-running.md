# Async PHP & Long-Running (Senior)

## Mündəricat
1. Process model
2. Octane / RoadRunner / FrankenPHP
3. Swoole / ReactPHP / Amphp
4. Memory & state management
5. Sual-cavab seti

---

## 1. Process model

**S: PHP-FPM ilə Octane arasında əsas fərq?**
C: FPM hər request üçün PHP yenidən boot edir (50-100ms). Octane worker bir dəfə boot olunur, çoxlu request handle edir (5-10× sürət).

**S: PHP-FPM-də `pm.max_children` necə hesablanır?**
C: `(Total RAM × 0.8) / avg_worker_memory`. 8GB × 0.8 / 64MB = ~100 worker.

**S: `pm.max_requests` nə üçün vacibdir?**
C: Worker N request-dən sonra avtomatik restart. Memory leak qarşısı (PHP extension-ları, OPcache-də). Production-da 500-1000 dəyəri tipik.

**S: Process model-lər (`static`, `dynamic`, `ondemand`) fərqi?**
C: 
- static: fixed sayı (predictable)
- dynamic: min-max range (orta yük)
- ondemand: yalnız request gələndə spawn (low traffic)

---

## 2. Octane / RoadRunner / FrankenPHP

**S: Octane Swoole və RoadRunner arasındakı fərq?**
C: Swoole — PHP extension (C), coroutine dəstəyi. RoadRunner — Go binary, worker manager. RoadRunner stabilrdır, Swoole sürətlidir.

**S: FrankenPHP niyə yeni nəsil?**
C: Caddy üzərində qurulub: HTTP/2, HTTP/3, auto-HTTPS. Worker mode (long-running) və classic mode (PHP-FPM kimi) ikisini dəstəkləyir.

**S: Octane production-da qarşılaşdığınız ən çox bug?**
C: Singleton state leak. `app()->singleton()` request-arası state saxlayır. `app()->scoped()` istifadə etmək lazımdır.

**S: `Octane::concurrently()` nə vaxt istifadə olunur?**
C: Bir request daxilində paralel I/O (3 microservice çağırışı). Toplam latency = max(t1, t2, t3), cəmi yox.

---

## 3. Swoole / ReactPHP / Amphp

**S: Swoole coroutine necə işləyir?**
C: Cooperative multitasking — yield point-də kontrol başqa coroutine-ə keçir. Network I/O coroutine ilə paralel olur.

**S: ReactPHP event loop necə işləyir?**
C: Single-threaded async event loop (Node.js kimi). Promise/Future API. Blocking call (DB) coroutine olmadığına görə əzab.

**S: PHP fibers (8.1+) coroutine-mu?**
C: Bəli. Stackful coroutine. ReactPHP/Amphp fiber üzərində sync syntax verir (await əvəzinə yield).

**S: Swoole ilə file I/O sync deyil, niyə?**
C: Swoole network I/O üçün hook-lar var, file I/O üçün məhdud. Heavy file işi üçün ayrı task worker (`task_worker`).

---

## 4. Memory & state management

**S: Long-running PHP-də ən böyük tələ?**
C: Static property və singleton-larda state pollution. Hər request təmiz olmadığına görə "stale data" problemi.

**S: `app()->singleton()` Octane-da niyə təhlükəli?**
C: Worker boot-da bir dəfə yaranır, bütün request-lər eyni instance-i görür. State leak.

**S: `app()->scoped()` nə vaxt əlavə olundu?**
C: Laravel 9+. Per-request scope — Octane bunu hər request bitdikdə flush edir.

**S: Memory leak Octane-da necə debug olunur?**
C: 
- `memory_get_usage()` log et
- `max_jobs` tweak et (500-1000)
- Object reference izlə (`$em->clear()` Doctrine-də)
- Static property-lərdən qaç
- `gc_collect_cycles()` bəzən manual çağır

**S: Worker restart graceful necə olur?**
C: SIGTERM → mövcud request bitirilir (max 30s) → worker restart. Kubernetes `terminationGracePeriodSeconds` 60s+ olmalıdır.

---

## 5. Sual-cavab seti (Async fokus)

**S: Async PHP-də DB query niyə bottleneck olur?**
C: PDO blocking-dir. Coroutine PDO əvəzinə Swoole MySQL driver və ya ReactPHP MySQL package istifadə etmək lazımdır.

**S: `Promise::all()` niyə "fail-fast"-dir?**
C: Bir promise reject olarsa, qalanların nəticəsi atılır. `Promise::settle()` hər biri ayrı işlənir.

**S: Backpressure async sistemdə necə həll edilir?**
C: 
- Bounded buffer (queue size limit)
- Slow consumer → publisher slow et
- Drop / sample (telemetry)
- Apply pressure upstream

**S: PHP-də "event loop" hansı tool-larda var?**
C: ReactPHP, Amphp, Swoole, RoadRunner, FrankenPHP worker mode.

**S: gRPC PHP-də niyə tarixən zəif olub?**
C: PHP-FPM stateless — gRPC streaming üçün uyğun deyil. RoadRunner / Swoole gRPC server dəstəkləyir.

**S: WebSocket FPM-də niyə işləməz?**
C: FPM hər request üçün worker. WebSocket connection uzundur (saatlar) — worker ölmür, pool tükənir.

**S: Octane Reverb (WebSocket) ilə birgə işləyirmi?**
C: Octane HTTP üçündür. Reverb ayrı process-dir (ReactPHP əsaslı). İkisi paralel işləyir.

**S: `workerman` PHP framework-i nədir?**
C: Çin-də populyar async framework (ReactPHP-yə oxşar). Built-in WebSocket, TCP, UDP server.

**S: Long-running CLI script-də memory leak necə tapılır?**
C: `memory_get_peak_usage()` log et, Xdebug GC stats, `tideways/blackfire` profile. Şübhəli yer: ORM identity map (Doctrine `$em->clear()`).

**S: Octane-da `request()` helper niyə təhlükəli ola bilər?**
C: Container scope-da olmasa stale request qaytara bilər. Constructor injection daha təhlükəsizdir.

**S: Async job queue (Octane-də) ilə Horizon-un fərqi?**
C: Octane HTTP request handler. Queue worker ayrı process — Horizon onu manage edir.

**S: `pcntl_fork()` nə vaxt istifadə olunur?**
C: CLI script-də child process spawn. FPM-də mövcud deyil. Use case: parallel batch processing.

**S: PHP `parallel` extension nədir?**
C: True multi-threading PHP-də. ZTS (Zend Thread Safe) build lazımdır. Production-da nadir istifadə.

**S: Concurrent HTTP request-lər `Http::pool()` necə işləyir?**
C: Guzzle Promise-əsaslı. cURL multi handle (CURLOPT_MULTI). 10 request paralel.

**S: Octane sync code-u async-ə çevirirmi?**
C: Xeyr. Code sync qalır. Octane sadəcə bootstrap cost azaldır + worker reuse.

**S: Memory limit Octane worker-də nə qədər?**
C: Tipik 256-512 MB. Worker boot zamanı framework yüklənir (~50 MB), hər request artırır.

**S: `WithoutOverlapping` middleware nə üçündür?**
C: Eyni job paralel işlənməsin (eyni order ID üçün iki worker). Cache lock istifadə edir.

**S: Job retry necə exponential backoff edilir?**
C: `public function backoff(): array { return [1, 5, 15, 60]; }` → 1s, 5s, 15s, 60s.

**S: Failed job DB-də necə saxlanılır?**
C: `failed_jobs` cədvəlində payload + exception + UUID. `php artisan queue:retry all` ilə yenidən cəhd.

**S: Long-polling `Cache::lock()` ilə necə yazılır?**
C: 
```php
$lock = Cache::lock('job-x', 60);
if ($lock->block(10)) {  // 10s gözlə
    try { /* work */ } finally { $lock->release(); }
}
```

**S: Health check probe Octane-da necə?**
C: Lightweight `/health` endpoint (no DB). Kubernetes readinessProbe `initialDelaySeconds: 10` (boot vaxtı).
