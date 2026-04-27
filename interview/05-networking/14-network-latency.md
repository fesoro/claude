# Network Latency and Bandwidth (Lead ⭐⭐⭐⭐)

## İcmal

Latency — bir paketin göndəricidən alıcıya çatma vaxtıdır. Bandwidth — eyni anda ötürülə bilən data miqdarıdır. Bu iki konsepti bir-birindən fərqləndirmək, real sistemlərdə performans bottleneck-ləri identify etmək, latency-yə görə arxitektura qərarları vermək Lead səviyyəsi üçün tələb olunan bilikdir. "Networks are unreliable, latency is unpredictable" — bu reallıq üzərindən sistem dizayn edə bilmək kritik kompetensiyalardır. Jeff Dean-in "Numbers Every Programmer Should Know" sənədi senior/lead interview-larında tez-tez istinad edilir.

## Niyə Vacibdir

Latency sistemin hər qatını əhatə edir: DNS resolution, TCP handshake, TLS handshake, database query, external API call, Redis lookup — bunların hər biri latency əlavə edir. Lead Developer bu latency-ləri ölçməyi, darboğazı tapmağı, SLO/SLA həddlərini müəyyən etməyi, distributed system-lərdə latency trade-off-larını izah etməyi bacarmalıdır. Latency ignorasiyası P99 spike-larına, user churn-ə, SLA breach-lərinə gətirib çıxarır.

## Əsas Anlayışlar

**Latency əsas ədədlər — yaddaşda saxlanmalı (Jeff Dean numbers):**
- L1 cache reference: ~1 nanosaniyə (ns)
- L2 cache reference: ~4 ns
- Mutex lock/unlock: ~25 ns
- Main memory (RAM) reference: ~100 ns
- Compress 1KB with Snappy: ~3,000 ns = 3 microsaniyə (µs)
- SSD random read: ~16,000 ns = 16 µs
- Read 1MB sequentially from memory: ~250 µs
- Round trip within same datacenter: ~500 µs = 0.5 millisaniyə (ms)
- HDD random read (seek): ~2,000,000 ns = 2 ms
- Database query (indexed, local): ~1-5 ms
- Redis GET (local): ~0.1-1 ms
- HTTP API call (same region): ~1-10 ms
- HTTP API call (cross-region, US East → Europe): ~80-100 ms
- Trans-Atlantic round trip: ~80 ms
- DNS lookup (uncached): ~20-120 ms

**Latency növləri — komponetlər üzrə:**
- **Propagation latency**: Fiziki məsafəyə görə — işığın optical fiber içindəki sürəti (vacuum-dakının ~67%-i). 1000 km = ~5 ms. Azaltmaq mümkün deyil — coğrafi yaxınlıq vacibdir
- **Transmission latency**: Paketin ötürülmə vaxtı = packet size / bandwidth. Bandwidth artdıqca azalır. 1KB paket 1Gbps-də = ~0.008 ms
- **Processing latency**: Router, switch, server-in paketi işləmə vaxtı. Müasir router-lərdə ~µs səviyyəsindədir
- **Queuing latency**: Şəbəkə yükü olduqda paketlər queue-da gözləyir — ən variable hissədir. Traffic burst-larında dramatik artır

**Bandwidth vs Latency — analoji:**
- Bandwidth: Boru diametri (nə qədər su eyni anda keçə bilər)
- Latency: Suyun borudan keçmə vaxtı (məsafəyə görə sabitdir)
- Böyük bandwidth + yüksək latency: Çox data göndərmək olar, amma hər paket gec çatar. File backup üçün OK, interactive app üçün dəhşət
- Kiçik bandwidth + aşağı latency: Tez çatar, amma az data gedir. Gaming / real-time uyğundur
- Video streaming: Bandwidth kritikdir. Online gaming: Latency kritikdir. gRPC: Hem-hem lazımdır

**RTT (Round Trip Time) — connection overhead:**
- TCP handshake: 1 RTT (SYN → SYN-ACK → ACK)
- TLS 1.2 handshake: TCP + 2 RTT əlavə = 3 RTT total
- TLS 1.3 handshake: TCP + 1 RTT əlavə = 2 RTT total (0-RTT session resumption mümkündür)
- HTTP/1.1 sorğusu: 1 RTT per request (head-of-line blocking)
- HTTP/2 üzərindən 10 sorğu: ~1 RTT (multiplexing — paralel streams)
- QUIC (HTTP/3): 0-RTT ilə ilk request — TCP handshake yoxdur

**Tail latency — P50, P95, P99, P99.9:**
- P50 (median): Sorğuların yarısı bu latency-dən aşağıdır — "normal" user
- P95: 95% sorğu bu latency-dən aşağı
- P99: 99% sorğu bu həddən aşağı — "worst case" istifadəçi təcrübəsi
- P99.9: 1000 sorğudan 1-i bu latency-dən yuxarı — "long tail"
- P99 çox vaxt P50-dən 10-100x daha yüksəkdir — garbage collection, lock contention, cache miss
- **Microservices fanout problem**: 100 service-ə parallel call. Bir service-in P99-u bütün chain-in P50-sini kötüləşdirir. `Total latency ≥ max(service latencies)`

**Latency azaltma strategiyaları:**
- **Geographic distribution**: CDN, multi-region deployment — user-a fiziki yaxınlıq. Edge computing
- **Connection reuse**: HTTP keep-alive, database connection pooling, Redis connection pooling — TCP + TLS handshake yalnız bir dəfə
- **Caching**: Response cache, DB query cache, DNS TTL, computed result cache — sorğuları azalt
- **Async processing**: Gözləmə lazım olmayan işləri queue-ya göndər — response time azalır
- **Parallel requests**: Dependency olmayan API call-ları eyni anda göndər (`Promise.all`, `Http::pool`)
- **Protocol optimization**: HTTP/2 multiplexing, TLS 1.3 1-RTT, QUIC 0-RTT
- **Compression**: gzip, brotli — data ötürülmə vaxtını azaldır (bandwidth-limited hallarda)
- **Prefetching/preloading**: İstifadəçi tələb etmədən əvvəl data hazırla, cache-ə qoy

**Little's Law — latency spike analizi:**
- `L = λ × W` (L = sistemdəki request sayı, λ = arrival rate, W = latency/response time)
- W artarsa (latency spike), L artar (sistemdəki in-flight request sayı) → queue uzanır → W daha da artır
- Bu feedback loop-dur — latency spike-lar özünü gücləndirir (vicious cycle)
- Həll: Capacity artır YA DA arrival rate (λ) azalt (rate limiting, shedding)

**Service Level Objectives (SLO) — latency budget:**
- API P99 latency < 200ms
- Database query P99 < 50ms
- Cache hit P99 < 5ms
- External API timeout: 3000ms
- SLO-ları açıq müəyyən etmədən sistem performansı ölçülmür, team alignment olmur

**Latency budget — cascade design:**
- Bir request-in ümumi budget-i: 200ms P99
- DNS: 1ms, TCP: 1ms, TLS: 20ms, Nginx: 1ms, PHP: 5ms, DB: 50ms, Redis: 1ms, Serialize: 5ms = ~84ms P50
- P99 = P50 × 3 (rough estimate): ~252ms — budget keçilir → DB query-lərini optimize et
- Hər layer üçün SLO müəyyən et, alert qur

## Praktik Baxış

**Interview-da yanaşma:**
Latency haqqında sual gəldikdə "ölçmədən bilmirəm" düzgün başlanğıcdır, lakin sonra ölçmə metodologiyasını izah edin. Konkret latency ədədlərini bilmək (ms, µs, ns) sizi fərqləndirir. Trade-off-ları — latency vs cost, latency vs consistency, latency vs accuracy — izah edin.

**Follow-up suallar (top companies-da soruşulur):**
- "Tail latency niyə vacibdir?" → Microservices-da fanout: 100 service-ə parallel call. Bir service-in P99-u bütün chain-in P50-sini kötüləşdirir. User experience-ı worst-case belirler. Google-un araşdırmasına görə 100ms gecikməsi conversion-ı 1% azaldır
- "Caching latency-ni necə azaldır?" → DB/API call (~5-100ms) əvəzinə Redis hit (~0.1-1ms). Trade-off: stale data riski
- "Connection pooling nə qazandırır?" → TCP (1 RTT) + TLS (1-2 RTT) overhead hər requestdə olmur — pool-da hazır connection-lar var. DB connection açmaq ~20-50ms çəkir
- "CDN-in latency üzərindəki təsiri?" → User-a fiziki yaxın edge server cavab verir — propagation latency azalır. Singapore user → Singapore PoP (5ms) vs New York origin (200ms+)
- "P99 spike-ı diagnoz etmək üçün ilk addım?" → Distributed tracing (Jaeger/Zipkin) ilə slow request trace-ını aç. Hansı span-lar uzun sürüb? DB-dəmi, network-dəmi, application logic-dəmi?
- "Latency vs throughput trade-off?" → Batch processing throughput artırır (amortized overhead) amma latency artır. Real-time processing latency azaldır amma throughput azalır. Use-case müəyyən edir
- "Database query latency-si spike edəndə N+1 query-ni necə detect edirsiniz?" → Laravel Telescope-da query count per request. Datadog APM-də DB time per endpoint. Larevel Debugbar-da N+1 warning

**Ümumi səhvlər (candidate-ların etdiyi):**
- Latency ilə bandwidth-i eyniləşdirmək — "daha yaxşı connection = daha az latency" hər zaman doğru deyil
- Yalnız average latency-yə baxmaq — P99 görünmür, real user experience ölçülmür
- Geographic latency-ni hesaba qatmamaq — multi-region deployment dizaynında
- "Daha çox server = daha aşağı latency" düşüncəsi — bottleneck-i tapmadan scale etmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Little's Law-ı latency spike analizi üçün tətbiq edə bilmək, tail latency-nin microservices fanout-a necə multiplikator effekt etdiyini izah etmək, latency budget dizayn edə bilmək (hər service layer üçün max latency limiti), P99.9 targeting-in cost implicationlarını bilmək.

## Nümunələr

### Tipik Interview Sualı

"Your API's P99 latency suddenly spiked from 50ms to 500ms. Walk me through how you would diagnose and fix this."

### Güclü Cavab

P99 latency spike-ını diagnoz etmək üçün sistematik approach tətbiq edərdim.

**1. Temporal isolation**: Spike nə vaxt başladı? Son deployment, config dəyişikliyi, ya da traffic artımı ilə temporal korrelyasiya varmı? Rollback bir seçimdir.

**2. Scope isolation**: Bütün endpoint-lər mi, yoxsa spesifik endpoint-lər? Müəyyən user segment-i mi? Specific region-da mı?

**3. Metrics layer (APM)**: Datadog/New Relic-də P50/P95/P99-u hər service üçün müqayisə et. Hansı downstream dependency-nin latency-si artıb? Database query time, Redis latency, external API call time — eyni zaman kəsiyini yoxla.

**4. Distributed tracing**: Jaeger/Zipkin ilə konkret bir slow request-in trace-ını aç. Hansı span-lar uzun sürüb? Bu darboğazı identify edir.

**Olası səbəblər (ən çox görülənə görə):**
- Database slow query: `EXPLAIN ANALYZE` ilə — index missing, sequential scan, statistics outdated?
- N+1 query problemi: ORM lazy loading artıb — query sayı 10x artdı
- Memory pressure: GC pauses — spikes periodikdirsə şübhəli (PHP-FPM worker restart, JVM GC)
- External API degradation: Third-party service yavaşladı — circuit breaker yoxdursa cascade
- Hot partition/key: Bir database shard-ı ya Redis key-i çox yük alır
- Connection pool exhaustion: DB/Redis connection pool dolub — waiting

**Həll:** Root cause-a görə: index əlavə et, N+1 fix et (eager loading), circuit breaker tətbiq et, cache əlavə et, connection pool artır.

**Uzunmüddətli:** Latency budget müəyyən et, P99 SLO alert qur (150ms threshold), load testing ilə peak capacity validate et.

### Kod Nümunəsi

```php
// Laravel — request latency tracking middleware
class LatencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);  // nanosaniyə dəqiqliyi

        $response = $next($request);

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        // Response header-da latency — debugging üçün faydalı
        $response->headers->set('X-Response-Time', round($durationMs, 2) . 'ms');

        // Slow request log — P99 aşıldıqda
        if ($durationMs > 500) {
            Log::warning('Slow request detected', [
                'method'      => $request->method(),
                'path'        => $request->path(),
                'duration_ms' => $durationMs,
                'user_id'     => $request->user()?->id,
                'ip'          => $request->ip(),
                'query_count' => DB::getQueryLog() ? count(DB::getQueryLog()) : null,
            ]);
        }

        // Prometheus/Datadog histogram metriki
        // Labels ilə bucket-ləmək: [10, 25, 50, 100, 200, 500, 1000, 5000]
        app(MetricsClient::class)->histogram(
            name: 'http_request_duration_ms',
            value: $durationMs,
            tags: [
                'method' => $request->method(),
                'route'  => $request->route()?->getName() ?? 'unknown',
                'status' => $response->getStatusCode(),
            ]
        );

        return $response;
    }
}
```

```php
// Parallel HTTP request-lər ilə latency azaltma
use Illuminate\Support\Facades\Http;

// ❌ Sequential — 3 API call sırayla: ~300ms (100ms + 100ms + 100ms)
$user    = Http::get('/api/user/1')->json();
$orders  = Http::get('/api/orders?user=1')->json();
$reviews = Http::get('/api/reviews?user=1')->json();

// ✅ Parallel — 3 API call eyni anda: ~100ms (max of 3)
[$userResp, $ordersResp, $reviewsResp] = Http::pool(fn($pool) => [
    $pool->get('/api/user/1'),
    $pool->get('/api/orders?user=1'),
    $pool->get('/api/reviews?user=1'),
]);

$user    = $userResp->json();
$orders  = $ordersResp->json();
$reviews = $reviewsResp->json();

// ❌ N+1 problem — 1 + N DB query: yüksək latency
$posts = Post::all();  // 1 query
foreach ($posts as $post) {
    echo $post->author->name;  // Hər post üçün ayrı query = N query
}
// 100 post = 101 query, ~100ms+

// ✅ Eager loading — 2 query: aşağı latency
$posts = Post::with('author')->get();  // 2 query, ~5ms
foreach ($posts as $post) {
    echo $post->author->name;  // Cache-dədir, DB sorğusu yoxdur
}
```

```php
// Connection Pool — DB + Redis latency azaltma
// Laravel database connection pooling (PgBouncer + Laravel)

// config/database.php — persistent connection settings
'mysql' => [
    'driver'    => 'mysql',
    'options'   => [
        PDO::ATTR_PERSISTENT => true,  // Connection reuse
    ],
    // Pool size: php-fpm worker sayı × average connections = pool size
    // 10 workers × 3 connections = 30 pool size
],

// Redis connection pool
// config/database.php
'redis' => [
    'client' => 'phpredis',  // predis-dən daha sürətli
    'default' => [
        'host'         => env('REDIS_HOST', '127.0.0.1'),
        'port'         => env('REDIS_PORT', 6379),
        'persistent'   => true,         // Connection reuse
        'timeout'      => 0.5,          // 500ms timeout
        'read_timeout' => 1.0,
    ],
],
```

```
Latency Budget Example — Full Request Lifecycle:
Total target: P99 < 200ms

Client                    CDN Edge    Nginx         PHP-FPM     MySQL      Redis
   |                         |           |               |           |         |
   |--- HTTPS request -----> |           |               |           |         |
   | ~20ms TLS (cached cert) |           |               |           |         |
   |                         |--- fwd -->|               |           |         |
   |                         |  ~1ms     |--- fastcgi -->|           |         |
   |                         |           |    ~1ms       |-- Redis -->|         |
   |                         |           |               |   ~1ms    |         |
   |                         |           |               |<-- hit ---|         |
   |                         |           |               |           |         |
   |                         |           |               |-- DB ----->|         |
   |                         |           |               |   ~10ms   |         |
   |                         |           |               |<-- res ---|         |
   |<--- Response -----------|           |               |           |         |
   | ~5ms (compressed, gzip)             |               |           |         |

P50 breakdown:
  TLS resumption:  5ms
  Nginx:           1ms
  PHP processing:  5ms
  Redis cache:     1ms (hit)
  DB query:        10ms
  Response tx:     2ms
  ─────────────────────
  Total P50:      ~24ms
  P99 (x5 est.): ~120ms ✓ (budget: 200ms)

Red flags (budget keçilir):
  DB query > 50ms → EXPLAIN ANALYZE
  Cache miss rate > 20% → cache warming
  PHP time > 20ms → N+1 check, algorithm review
```

## Praktik Tapşırıqlar

1. `ping`, `traceroute`, `mtr` ilə müxtəlif bölgələrə real network latency ölçün: eyni datacenter vs cross-region vs trans-atlantic
2. APM aləti qurun (Datadog, New Relic ya da Laravel Telescope) — P50/P95/P99 latency distribution-ı görün, nə hissə DB-yə gedir?
3. Parallel HTTP request-lərin latency üstünlüyünü benchmark edin: `Http::pool` vs sequential — real ms fərqi nədir?
4. Connection pooling olmadan vs olduqda latency fərqini ölçün: PgBouncer əlavə edib əvvəl/sonra müqayisə edin
5. Latency budget dizayn edin: bir API endpoint-i üçün hər layer-ın maksimum ms hədlərini müəyyən edin, SLO alert qurun
6. P99 spike simulyasiyası: `SLEEP(1)` ilə yavaş DB query-si create edib APM-də P99 spike-ı müşahidə edin
7. Little's Law tətbiqini test edin: fixed throughput API-yə artan load göndərin, latency necə artır?

## Əlaqəli Mövzular

- [TCP vs UDP](01-tcp-vs-udp.md) — Protocol overhead latency-yə təsiri, TCP handshake RTT
- [HTTP Versions](02-http-versions.md) — HTTP/2 multiplexing, HTTP/3 QUIC 0-RTT latency azaltması
- [HTTP Caching](09-http-caching.md) — Caching latency-ni necə azaldır — cache hit vs miss
- [DNS Resolution](04-dns-resolution.md) — DNS lookup latency, DNS caching TTL
- [Proxy vs Reverse Proxy](13-proxy-reverse-proxy.md) — Reverse proxy latency overhead, keepalive
