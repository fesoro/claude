# Backpressure & Load Shedding

## Nədir? (What is it?)

Overload altında service naive şəkildə hər request-i qəbul edərsə, queue şişir, latency
partlayır, timeout-lar artır və cascading failure başlayır. **Backpressure** upstream-ə
"yavaşla" siqnalı göndərir (flow control); **Load shedding** capacity-yə yaxın olduqda
bir hissə request-i aktiv atır ki qalanı sağ qalsın. **Adaptive concurrency** cari
gücü probe edib limit-i avtomatik tənzimləyir.

```
Overload (no shedding):
  λ=1000 rps, capacity=600 rps
  Queue: 0 → 400 → 800 → 1200 → OOM
  Latency: 50ms → 500ms → 5s → timeout storm → cascade

With load shedding:
  λ=1000 rps, accept=600, shed=400 (fast 503)
  Queue stable, p99 low, sistem sağ qalır
```

## Əsas Konseptlər (Key Concepts)

### Little's Law — Niyə Atmalıyıq

```
L = λ × W
  L = sistem daxilində request sayı
  λ = arrival rate
  W = hər request-in qaldığı müddət (latency)

λ > service rate olsa: W sürətlə böyüyür → L unbounded → OOM.
Həll: λ-nı azalt (shed) və ya W-ni məhdudla (timeout).
Queue bounded olmalıdır, əks halda tail latency sonsuzluğa gedir.
```

### Backpressure vs Load Shedding

```
Backpressure:  "yavaşla, hazır olanda de"   (cooperative)
  TCP window, HTTP/2 flow control, reactive streams
  Org-daxili service-lər üçün uyğundur

Load shedding: "indi götürə bilmirəm"        (unilateral)
  503 Retry-After, 429 Too Many Requests
  Public API / naməlum client üçün praktikdir
```

### Shedding Strategies

**1. Random drop** — sadə, uniform.
```
load_ratio = current_inflight / max_inflight
if load_ratio > 0.9: drop with p = (load_ratio - 0.9) / 0.1
```

**2. Priority-based** — az əhəmiyyətli əvvəl at.
```
Critical  (/payments):  threshold=0.95  (son çarəyə qədər saxla)
Normal    (/orders):    threshold=0.80
Low       (/metadata):  threshold=0.60
Background (/analytics): threshold=0.40
```

**3. Adaptive (queue depth / p99)** — real siqnala baxır.
```
if queue_depth > target OR p99 > SLO: shed_rate += 0.1
else: shed_rate -= 0.05
```

**4. Deadline-aware** — client artıq timeout olubsa işləmə.
```
Request: deadline = now + 500ms  (gRPC metadata ilə propagate olur)
Queue-dan çıxanda: now > deadline → skip, heç emal etmə
```

**5. Cost-based** — bahalı query-ləri əvvəl at (aggregation, full-scan).

### Backpressure Mexanizmləri

```
Bounded queue:
  Block producer:  queue.put() gözləyir    (async-da təhlükəli)
  Reject on full:  queue.offer() → false   (shedding ilə birləşir)

TCP window:        receiver window=0 → sender dayanır
HTTP/2:            per-stream WINDOW_UPDATE frame
gRPC streaming:    onReady() callback, client pause edir
Reactive streams:  subscriber.request(n) — pull-based (Reactor, RxJava)
Kafka consumer:    lag böyüsə consumer.pause(partitions) → resume()
```

### Adaptive Concurrency (Netflix concurrency-limits)

Static limit (max=100) real capacity-ni əks etdirmir - DB cold olanda çox, hot
olanda az. TCP Vegas-dan ilhamlanmış alqoritmlər:

```
Vegas:
  base_rtt    = indiyə qədər ən az ölçülən RTT
  current_rtt = son RTT
  queue_size  = limit × (1 - base_rtt / current_rtt)

  queue_size kiçik: limit += 1       (probe more capacity)
  queue_size böyük: limit /= 2       (AIMD back-off)

Gradient:
  gradient  = base_rtt / current_rtt
  new_limit = current_limit × gradient + queue_size
```

Üstünlük: manual tuning yox, DB yavaşlayanda özü daralır, autoscale-ə uyğunlaşır.

### Circuit Breaker vs Load Shedding

```
Circuit breaker: downstream-dən qoruyur
  "Payment API down, ona zəng atma, fallback qaytar"

Load shedding: upstream-dən qoruyur
  "Mən yüklüyəm, yeni client götürə bilmirəm → 503"

Birlikdə lazımdır - tamamlayıcıdır.
```

### CoDel və LIFO Queue

**CoDel (Controlled Delay)** — sustained queue latency target-i aşsa paket atır.
Bufferbloat-un həlli.
```
target = 5ms, interval = 100ms
son 100ms ərzində queue delay > 5ms → drop
```

**LIFO vs FIFO overload altında**:
```
FIFO: köhnə request-lər əvvəl çıxır
  Client artıq timeout olub, cavab heç yerə getmir (wasted work)
FIFO-nun p99-u pis olur.

LIFO: yeni (fresh deadline) request prioritet alır
  Köhnələr shed, yeni client cavab alır
  Median pisləşir, p99 yaxşılaşır
Facebook, Amazon yüklü servislərdə LIFO istifadə edir.
```

### Graceful Degradation

```
Normal:        /feed → personalized, 50 items, real-time
Mild overload: /feed → personalized, 20 items, 10s cache
Heavy load:    /feed → popular items, 20, 1min cache
Emergency:     /feed → static "top stories"
```

## Arxitektura (Architecture)

### Admission Control Layers

```
[Client]
    ↓
[CDN / WAF]            ← rate limit per IP
    ↓
[API Gateway / Envoy]  ← global concurrency, priority, circuit breaker
    ↓
[NGINX]                ← limit_conn, limit_req, max_conns to backend
    ↓
[Laravel app]          ← admission middleware (Redis gate)
    ↓
[PHP-FPM]              ← pm.max_children (hard cap)
    ↓
[Database]             ← max_connections, statement_timeout
```

Ən yaxşı kənarda (gateway) shed etmək - daxili resource xərclənmir.

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Admission Middleware — 503 on Overload

```php
// app/Http/Middleware/LoadShedding.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class LoadShedding
{
    private const MAX_INFLIGHT = 200;
    private const MAX_QUEUE_DEPTH = 5000;
    private const THRESHOLDS = [
        'critical' => 1.00,  // heç atma
        'normal'   => 0.80,
        'low'      => 0.50,
    ];

    public function handle(Request $request, Closure $next, string $priority = 'normal'): Response
    {
        $inflight = (int) Redis::get('app:inflight') ?: 0;
        $queueDepth = (int) Redis::llen('queues:default');

        $load = max(
            $inflight / self::MAX_INFLIGHT,
            $queueDepth / self::MAX_QUEUE_DEPTH,
        );

        $threshold = self::THRESHOLDS[$priority] ?? 0.80;

        if ($load >= $threshold) {
            app('metrics')->increment('requests.shed', ['priority' => $priority]);
            return response()->json([
                'error' => 'service_overloaded',
            ], 503)->header('Retry-After', (int) ($load * 10) + random_int(0, 5));
        }

        Redis::incr('app:inflight');
        try {
            return $next($request);
        } finally {
            Redis::decr('app:inflight');
        }
    }
}
```

Route istifadəsi:
```php
Route::post('/payments', [Pay::class, 'charge'])->middleware('shed:critical');
Route::get('/analytics', [Analytics::class, 'idx'])->middleware('shed:low');
```

### Redis Concurrency Gate — Downstream Protection

```php
// app/Services/ConcurrencyGate.php
class ConcurrencyGate
{
    public function __construct(private string $key, private int $limit) {}

    public function acquire(callable $work): mixed
    {
        $lock = Cache::lock("gate:{$this->key}:slot", 10);
        if (!$lock->block(0.1)) {  // qısa gözləmə — tez "yox" de
            throw new OverloadedException("{$this->key} full");
        }

        $count = (int) Cache::get("gate:{$this->key}:count", 0);
        if ($count >= $this->limit) {
            $lock->release();
            throw new OverloadedException("{$this->key} at limit");
        }

        Cache::increment("gate:{$this->key}:count");
        $lock->release();

        try { return $work(); }
        finally { Cache::decrement("gate:{$this->key}:count"); }
    }
}

// Bahalı downstream üçün
$gate = new ConcurrencyGate('payment-api', limit: 20);
$result = $gate->acquire(fn() => Http::post('https://payments.example.com', $data));
```

### Horizon Queue Concurrency Limit

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-critical' => [
            'queue' => ['payments', 'auth'],
            'maxProcesses' => 20, 'balance' => 'simple', 'tries' => 3,
        ],
        'supervisor-normal' => [
            'queue' => ['default'],
            'minProcesses' => 2, 'maxProcesses' => 10, 'balance' => 'auto',
        ],
        'supervisor-background' => [
            'queue' => ['analytics'],
            'maxProcesses' => 3,  // analytics bütün resource-u yeməsin
        ],
    ],
],
```

### PHP-FPM Hard Cap + NGINX

```ini
; php-fpm pool.d/www.conf
pm = dynamic
pm.max_children = 50                 ; MUTLAQ concurrency cap
request_terminate_timeout = 30s
```

```nginx
upstream laravel {
    server 127.0.0.1:9000 max_conns=50;    # FPM-ə uyğun
    queue 100 timeout=1s;                   # 100 gözləyən, 1s sonra 503
}

server {
    location / {
        limit_conn per_ip 10;
        limit_req zone=api burst=20 nodelay;
        proxy_pass http://laravel;
    }
}
```

### Observability

```php
app('metrics')->gauge('inflight_requests', $inflight);
app('metrics')->gauge('queue_depth', $queueDepth);
app('metrics')->increment('requests.shed', ['priority' => $p, 'reason' => 'overload']);
```

Prometheus alert:
```yaml
- alert: SustainedLoadShedding
  expr: rate(requests_shed_total[5m]) > 10
  for: 5m
  annotations:
    summary: "Shedding 5+ dəqiqə davam edir — capacity investigate et"
```

## Interview Sualları

**S: Niyə sadəcə hər request-i qəbul etmək əvəzinə atmalıyıq?**
C: Little's law (L = λW): arrival rate service rate-dən yüksəkdirsə queue və latency
sonsuzluğa gedir, memory OOM olur. Client-lər artıq timeout olub amma biz hələ işləyirik
(wasted work), retry storm gəlir. Early shedding qalanları sürətli saxlayır. 90% fast
accepted >> 100% slow+crashed.

**S: Backpressure və load shedding fərqi nədir?**
C: Backpressure cooperative siqnaldır: "yavaşla" (TCP window, HTTP/2 flow control,
reactive streams). Upstream-i məlumatlandırır, org-daxili uyğundur. Load shedding
unilateraldır: 503/429 qaytarırsan. Public API üçün praktikdir (upstream-ə güvənmək
olmur). Adətən birlikdə istifadə olunur.

**S: Adaptive concurrency static limit-dən niyə yaxşıdır?**
C: Static limit real capacity-ni bilmir - DB cache cold olanda 100 çox, hot olanda
az. Netflix-in concurrency-limits kitabxanası (Vegas, gradient alqoritmi) RTT-ni
ölçüb limit-i avtomatik tənzimləyir: latency artırsa AIMD ilə geri çəkilir, stabil
olanda probe ilə artır. TCP congestion control-un application layer versiyası.

**S: Circuit breaker və load shedding nə vaxt istifadə olunur?**
C: Tamamlayıcıdır. Circuit breaker downstream-dən qoruyur: "Payment API down, zəng
atma, fallback qaytar". Load shedding upstream-dən qoruyur: "Mən yüklüyəm, yeni
client götürə bilmirəm". Production-da hər ikisi lazımdır.

**S: Priority-based shedding necə işləyir?**
C: Request-lər kateqoriyalanır - critical (payments), normal (read), low (analytics).
Hər priority üçün shed threshold: critical 95%, normal 80%, low 50%. Yüksək yükdə
pul gətirən trafik yaşayır, nice-to-have disable olur. Graceful degradation-ın əsası.

**S: Deadline-aware shedding nə deməkdir?**
C: Request gəldikdə deadline metadata-ya yazılır (gRPC-də standart). Queue-dan
çıxarkən `now > deadline` olsa skip - client artıq timeout olub, işləmək wasted.
Multi-hop sistemdə deadline propagation ilə hər hop qalan budget-i bilir. Overload
zamanı latency-ni dramatik azaldır.

**S: LIFO queue-nun overload-da üstünlüyü nədir?**
C: FIFO-da köhnə request əvvəl çıxır - amma client onsuz da timeout olub, cavab heç
yerə getmir. LIFO-da fresh deadline olan yeni request prioritet alır, köhnələr shed
olur. p99 yaxşılaşır, median pisləşə bilər. Facebook və Amazon bəzi servislərdə
LIFO istifadə edir.

**S: Load shedding-i haradan monitor etmək lazımdır?**
C: Əsas metric - `rate(requests_shed_total[5m])`. Sustained shedding (5+ dəqiqə)
capacity və ya incident siqnalıdır. Per-priority shed rate izlə (critical shed olursa
böyük problem). Inflight requests, queue depth, p99 latency, CPU ilə birlikdə
dashboard qur - root cause analiz üçün.

## Best Practices

- **Bounded queue-lar istifadə et** — hər queue-nun limit olmalıdır; sonsuz queue OOM və gizli latency deməkdir.
- **Priority classification** — request-lərə kritik/normal/low tipi ver (header, auth context, path əsasında); overload-da low əvvəl atılır.
- **Deadline propagation** — gRPC və ya custom header ilə deadline hər hop-a ötür; deadline keçib queue-da gözləyən işi atarkən shed et.
- **Adaptive concurrency seç, static limit əvəzinə** — Netflix `concurrency-limits` və ya AIMD; trafik pattern dəyişəndə self-tune olunur.
- **503 Retry-After qaytar** — client exponential backoff ilə retry etsin; shed olan response retry-able olmalıdır.
- **Jitter retry-larda əlavə et** — eyni anda hamı retry etsə thundering herd. 50-150ms random jitter.
- **Circuit breaker ilə kombinə et** — downstream-dan shed siqnalı gələndə circuit aç, əlavə retry etmə.
- **Graceful degradation hazırla** — stale cache, simplified response, optional feature disable; 100% shed etmək əvəzinə partial cavab.
- **PHP-FPM pm.max_children doğru tənzim** — CPU/memory-yə uyğun; hədd keçəndə nginx 503 verər. `pm = static` və ya `dynamic`.
- **Horizon / queue worker max_processes** — job xilas edib sistemi boğmasın; per-queue prioritet təyin et.
- **Shed rate metric-ə alert qoy** — sustained shedding capacity problemini göstərir, incident pager ilə gəlsin.
- **Load testi mütləq et** — k6, JMeter ilə breakdown point tap; overload-da sistemin necə davrandığını bil.
- **LIFO-nu overload-da sına** — p99 tail latency kritikdirsə; normal vaxtda FIFO, shed mode-da LIFO.
- **Backpressure chain-də işləsin** — LB → API gateway → service → DB hər layer-də limit; bottleneck upstream-ə siqnal versin.
- **Playbook runbook** — shed başlayanda nə yoxlamalı (capacity, downstream, hot key), kimə eskalasiya. Hər incident-dən sonra yenilə.
