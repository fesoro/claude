# API Performance Optimization (Senior ⭐⭐⭐)

## İcmal

API performance — yalnız "sorğu sürətli cavab verirmi?" sualı deyil. Bu response time, throughput, latency breakdown, caching, N+1 problemlərinin həlli, async pattern-lər, və production-da real ölçümü əhatə edən geniş bir sahədir.

Senior developer kimi müsahibədə gözlənilən: yavaş endpoint-i necə debug edərsiniz, scale etmək lazım gəldikdə nə edərsiniz, və hansı trade-off-lara hazırsınız.

## Niyə Vacibdir

- API latency-si birbaşa istifadəçi təcrübəsinə təsir edir: 100ms artım dönüşümü azaldır
- Yavaş API-lər mobil cihazlarda daha pis görünür — network + latency ikiqat olur
- Yüksək latency-li endpoint-lər server resursu tüketir — qalan sorğulara yer qalmır
- Bir pis endpoint bütün sistemi yavaşlada bilər (thread pool saturation)

## Əsas Anlayışlar

### 1. Response Time vs Throughput

Bunlar fərqli metrikalar, fərqli optimizasiyalar tələb edir:

```
Response Time (Latency):
  Tək bir sorğunun cavab müddəti.
  Məqsəd: P95 < 200ms, P99 < 500ms
  Optimizasiya: DB query, N+1, cache hit

Throughput:
  Saniyədə neçə sorğu emal edilir (RPS — requests per second).
  Məqsəd: Maksimum RPS-i artır, resource-u az işlət
  Optimizasiya: Connection pool, worker sayı, horizontal scale

Diqqət: Latency-ni azaltmaq həmişə throughput-u artırmır, əksi də.
Yüksək concurrency-də latency artır — bu normal (queuing theory).
```

### 2. Latency Breakdown

Bir API sorğusunun anatomy-si:

```
Client                                           Server
  │                                                │
  │──── DNS Lookup (10-100ms, cache varsa ~0ms) ──▶│
  │                                                │
  │──── TCP Handshake (RTT × 1) ───────────────── ▶│
  │                                                │
  │──── TLS Handshake (RTT × 1-2) ──────────────▶ │
  │                                                │
  │──── HTTP Request ────────────────────────────▶ │
  │                   ┌────────────────────────┐   │
  │                   │ Server Processing:     │   │
  │                   │  Routing: ~1ms         │   │
  │                   │  Middleware: ~5ms      │   │
  │                   │  Business logic: Xms   │   │
  │                   │  DB queries: Yms       │   │
  │                   │  Cache: ~1ms (hit)     │   │
  │                   │  Serialization: ~2ms   │   │
  │                   └────────────────────────┘   │
  │◀──── HTTP Response ─────────────────────────── │
  │                                                │
  │──── Transfer time (data size / bandwidth) ─────│

HTTP/2 ilə: DNS + TCP + TLS yalnız bir dəfə — multiplexing sayəsində
HTTP/1.1 ilə: hər sorğu potensial olaraq yeni handshake
```

**İlk olaraq nə ölç:**
```bash
# curl ilə breakdown
curl -w "\nDNS: %{time_namelookup}s\nTCP: %{time_connect}s\nTLS: %{time_appconnect}s\nTTFB: %{time_starttransfer}s\nTotal: %{time_total}s\n" \
  -o /dev/null -s https://api.example.com/v1/users
```

### 3. N+1 Problemi API-da

```
# N+1 problemi:
GET /orders → 20 order qaytarır
  → hər order üçün ayrıca: SELECT * FROM users WHERE id = ?  (20 sorğu)
  → hər order üçün ayrıca: SELECT * FROM products WHERE id = ? (20 sorğu)
Cəmi: 1 + 20 + 20 = 41 DB sorğusu

# Həll 1: Eager Loading
Order::with(['user', 'products'])->paginate(20)
→ 3 sorğu cəmi (orders, users IN (...), products IN (...))

# Həll 2: DataLoader Pattern (GraphQL-dən gəlir, REST-ə də tətbiq olunur)
Request 1: user_id=5 → batch-ə əlavə et
Request 2: user_id=7 → batch-ə əlavə et
Request 3: user_id=5 → batch-ə əlavə et (dublikat — merge et)
→ SELECT * FROM users WHERE id IN (5, 7)  (1 sorğu)
```

#### Batch Endpoint Pattern

```
# Pis — hər item üçün ayrı API call
GET /products/1 → 50ms
GET /products/2 → 50ms
GET /products/3 → 50ms
Cəmi: 150ms + 3 × network round-trip

# Yaxşı — bir sorğuda hamısı
GET /products?ids=1,2,3
→ { "data": { "1": {...}, "2": {...}, "3": {...} } }
Cəmi: ~55ms + 1 × network round-trip
```

### 4. Field Selection (Sparse Fieldsets)

```
# Bütün sahəni qaytarma — mobile client yalnız id və name lazımdır
GET /users/42
→ { id, name, email, phone, address, created_at, updated_at, preferences, ... }  // 2KB

# Yalnız lazım olanı qaytarma
GET /users/42?fields=id,name
→ { id, name }  // 50 bytes — 40x kiçik

# JSON:API standartı:
GET /articles?fields[articles]=title,body&fields[people]=name
```

**Faydaları:** Kiçik payload, az serialization, az DB column fetch.

### 5. Async API Pattern — 202 Accepted

```
# Sinxron (pis — report 30s çəkir)
POST /reports/generate
→ [30 saniyə gözlə]
→ 200 OK { data: {...} }
→ Client timeout riskı, server thread işğal altında

# Asinxron (yaxşı)
POST /reports/generate
→ 202 Accepted
  { "job_id": "rpt_abc123", "status_url": "/jobs/rpt_abc123" }

[Client polling edir]
GET /jobs/rpt_abc123
→ { "status": "running", "progress": 45 }

GET /jobs/rpt_abc123
→ { "status": "completed", "result_url": "/reports/rpt_abc123" }

# Alternativ: Webhook
POST /reports/generate
  { ..., "webhook_url": "https://client.com/callbacks/report" }
→ 202 Accepted
[İş bitdikdə server client-ın URL-ə POST edir]
```

### 6. Response Compression

```
# gzip — universal dəstək, yaxşı sıxışdırma
Request:  Accept-Encoding: gzip, deflate, br
Response: Content-Encoding: gzip

# Brotli — gzip-dən 15-25% daha yaxşı sıxışdırma, HTTPS lazımdır
Response: Content-Encoding: br

Nə vaxt aktiv et:
✓ JSON/XML/HTML — 60-80% kiçilir (10KB → 2-3KB)
✗ JPEG/PNG/MP4 — artıq sıxışdırılıb, overhead yaranır
✗ Çox kiçik cavablar (<1KB) — overhead compression gain-i keçir

Nginx konfiqurasiya:
gzip on;
gzip_types application/json application/xml text/plain;
gzip_min_length 1024;  # 1KB-dan kiçiyi sıxma
gzip_comp_level 6;     # 1-9 arası; 6 = tarazlıq
```

### 7. HTTP/2 və API Optimizasiya Strategiyası

```
HTTP/1.1 dövrü:
  - Paralel sorğular üçün 6 TCP connection limitı (browser)
  - Request bundling lazımdır (CSS/JS birləşdirmə)
  - API batching (N sorğunu 1-ə endirmək) kritik idi

HTTP/2 dövrü:
  - Tək TCP connection üzərindən çox sorğu (multiplexing)
  - Server Push (az istifadə edilir)
  - Header compression (HPACK)
  → Kiçik, focused API endpoint-lər daha məqsədyönlüdür
  → Həddindən artıq batching artıq lazım deyil

HTTP/3 (QUIC):
  - UDP üzərindən — head-of-line blocking yoxdur
  - Mobile/yüksək packet loss şəbəkələrdə üstün
  - Hələ geniş yayılmayıb
```

### 8. Connection Keep-alive və Connection Pooling

```
# Keep-alive — TCP connection-ı saxla, hər sorğu üçün yeni handshake etmə
Connection: keep-alive
Keep-Alive: timeout=5, max=100

# Client-side connection pool (API client):
$client = new GuzzleHttp\Client([
    'timeout' => 5,
    // Curl multi-handler — bağlantıları pool-da saxlayır
    'handler' => HandlerStack::create(
        new CurlMultiHandler(['max_handles' => 20])
    ),
]);
```

### 9. API Səviyyəsində Caching

#### ETag ilə Conditional Request

```
# İlk sorğu
GET /users/42
→ 200 OK
  ETag: "abc123"
  Cache-Control: private, max-age=300
  { "id": 42, "name": "Əli" }

# Növbəti sorğu (304 boş body = sürətli)
GET /users/42
If-None-Match: "abc123"
→ 304 Not Modified  // 0 byte body — client cache-ni istifadə edir

# Cache-Control direktivlər:
Cache-Control: no-store        # Heç cache etmə
Cache-Control: no-cache        # Cache et, amma validasiya et
Cache-Control: private         # Yalnız browser cache (CDN yox)
Cache-Control: public          # CDN cache edə bilər
Cache-Control: max-age=3600    # 1 saat etibarlıdır
Cache-Control: s-maxage=3600   # CDN üçün (proxy cache)
Cache-Control: stale-while-revalidate=60  # Köhnəsini ver, arxada yenilə
```

#### Last-Modified ilə Conditional Request

```
GET /products/catalog
→ 200 OK
  Last-Modified: Wed, 26 Apr 2026 08:00:00 GMT

GET /products/catalog
If-Modified-Since: Wed, 26 Apr 2026 08:00:00 GMT
→ 304 Not Modified  // Dəyişməyibsə
```

### 10. Database-level API Optimizasiya

```
Read Replicas:
  Primary  ←─── Yazma (INSERT, UPDATE, DELETE)
     │
     ├──▶ Replica 1 ──▶ GET /users (read)
     ├──▶ Replica 2 ──▶ GET /reports (read)
     └──▶ Replica 3 ──▶ GET /analytics (heavy read)

Caching Layer:
  GET /users/42
  → Redis yoxla (hit: ~1ms qaytarma)
  → Miss: DB-dən yüklə, Redis-ə yaz (TTL: 5min), qaytar

Query Optimizasiya (API kontekstdə):
  - Pagination-sız COUNT(*) yazmaqdan qaçın — expensive
  - SELECT yalnız lazımlı sütunları (SELECT *  yox)
  - N+1-i aşkar etmək: Debugbar, Telescope
  - Slow query log aktiv et (> 1000ms): log_min_duration_statement
```

### 11. APM Alətləri ilə API Profiling

```
APM (Application Performance Monitoring) nə göstərir:
  - Hər endpoint-in orta, P95, P99 response time
  - Ən yavaş endpointlər (latency distribution)
  - Hər sorğudakı DB query sayı (N+1 aşkarı)
  - External API call-ların müddəti
  - Memory/CPU per endpoint

Distributed Tracing:
  Client → API → DB + Cache + External API
  ┌──────────────────────────────────────────────────┐
  │ GET /orders/42                    total: 245ms   │
  │  ├── Middleware                        3ms       │
  │  ├── Route handler                     2ms       │
  │  ├── DB: orders query                 15ms       │
  │  ├── DB: users query (N+1!)          120ms ⚠️   │
  │  ├── Cache: product details hit        1ms       │
  │  ├── External: shipping API           98ms ⚠️   │
  │  └── Serialization                     6ms       │
  └──────────────────────────────────────────────────┘

Alətlər:
  - Datadog APM, New Relic — commercial, enterprise
  - Sentry Performance — developer-friendly
  - Jaeger, Zipkin — open-source, self-hosted
  - Laravel Telescope — lokal development
  - Laravel Pulse — production-ready, built-in
```

### 12. API Load Testing

```
Qazanılan bilik:
  - Maksimum RPS (requests per second) nədir?
  - Hansı endpointdə bottleneck var?
  - P99 latency yüklə necə dəyişir?
  - Memory leak varmı (uzun müddətdə yaddaş artımı)?

Alətlər:
  k6 — JavaScript, developer-friendly
  Apache Bench (ab) — sadə, tez nəticə
  wrk — yüksək concurrency testi
  Locust — Python, mürəkkəb ssenari

k6 nümunəsi:
  import http from 'k6/http';
  export const options = {
    vus: 100,          // virtual users
    duration: '30s',
  };
  export default function () {
    http.get('https://api.example.com/v1/products');
  }

İzlənəcək metrikalar:
  - http_req_duration (P50, P95, P99)
  - http_req_failed (xəta faizi)
  - http_reqs (throughput)
  - Error rate @ different concurrency levels

İnterpretasiya:
  100 RPS @ P95 < 100ms  → sağlamdır
  1000 RPS @ P95 = 800ms → bottleneck var, araşdır
  Xəta rate > 1%         → kritik, dərhal araşdır
```

### 13. CDN API-lər üçün

```
Cache edilə bilər:
✓ GET /products (public catalog)
✓ GET /blog/posts (public content)
✓ GET /cities (static reference data)
✓ Authenticated olmayan static resurslar

Cache edilə bilməz (CDN-dən keçsə belə keçməməlidir):
✗ GET /users/me (şəxsi data)
✗ POST/PUT/DELETE (mutating operations)
✗ Authorization header tələb edən sorğular

Cache-Control ilə CDN-ə yol göstər:
  public, max-age=300, s-maxage=3600
  (browser 5 dəq, CDN 1 saat cache edir)

Stale-while-revalidate pattern:
  Cache-Control: public, max-age=60, stale-while-revalidate=600
  → 60 saniyə fresh
  → 60-660 saniyə: köhnə versiyasını qaytar, arxada yenilə
  → 660+ saniyə: origin-dən yüklə
```

### 14. Slow Query Aşkarı və Həlli

```sql
-- PostgreSQL: 1 saniyədən yavaş sorğular
SELECT pid, duration, query
FROM pg_stat_activity
WHERE state = 'active'
  AND duration > interval '1 second';

-- Slow query log (postgresql.conf):
log_min_duration_statement = 1000  -- ms

-- EXPLAIN ANALYZE:
EXPLAIN ANALYZE
SELECT u.*, o.total
FROM users u
JOIN orders o ON o.user_id = u.id
WHERE u.status = 'active'
ORDER BY o.created_at DESC
LIMIT 20;

-- Seq Scan görürsənsə → index lazımdır
-- Hash Join əvəzinə Nested Loop görürsənsə → statistics köhnədir, ANALYZE et
```

### 15. Rate Limiting — Client-side Backoff

```
Server tərəf:
  Rate limit keçildikdə: 429 Too Many Requests
  Retry-After: 60  ← neçə saniyə gözlə

Client tərəf (düzgün yanaşma — exponential backoff + jitter):
  Attempt 1: dərhal
  429 → wait = 2^1 + random(0, 1000ms) = ~2s
  Attempt 2: 2s sonra
  429 → wait = 2^2 + random(0, 1000ms) = ~4s
  Attempt 3: 4s sonra
  ...
  Max 5 attempt → xəta

Pis yanaşma (retry storm):
  429 → dərhal retry → 429 → dərhal retry → ...
  Çox client eyni anda retry etsə → server daha çox yüklənir
  → Thundering herd problemi

Jitter bunu həll edir — hər client fərqli vaxt gözləyir
```

## Praktik Baxış

### Performance Budget

Müsahibədə "API-nız nə qədər sürətli olmalıdır?" sualına hazır ol:

| Endpoint Tipi | P50 hədəf | P95 hədəf | P99 hədəf |
|--------------|----------|----------|----------|
| Autentifikasiya | < 50ms | < 100ms | < 200ms |
| Basit GET | < 50ms | < 150ms | < 300ms |
| Mürəkkəb siyahı | < 100ms | < 300ms | < 500ms |
| Hesabat/Analitik | < 500ms | < 2s | Async et |

### Trade-off-lar

- **Caching vs Freshness** — 5 dəqiqəlik cache = API yükü 90% azalır, amma köhnə data
- **Eager loading vs Lazy loading** — hər zaman eager loading pis deyil, lakin həmişə lazım olan data lazım deyil
- **Compression vs CPU** — gzip CPU istifadə edir; yüksək RPS-də tradeoff var
- **Sync vs Async** — developer experience asanlaşır (async mürəkkəbdir), latency gizlənir

### Anti-pattern-lər

- Sync-da 10s-lik işlər
- Production-da EXPLAIN olmadan query optimizasiya etmək
- P50-yə baxmaq, P99-u ignore etmək (istifadəçilərin 1%-i ən pis təcrübəni yaşayır)
- Cache invalidation-ı düşünmədən hər şeyi cache etmək
- Load test etmədən "scale edir" demək

## Nümunələr

### Tipik Interview Sualı

> "Bir endpoint-in yavaş olduğunu gördünüz — P95 latency 3 saniyədir. Necə debug edərdiniz?"

---

### Güclü Cavab

"Sistematik yanaşma:

**1. Ölç, güman etmə.** APM-ə baxıram: latency breakdown göstərir DB-mi, external API-mi, application logic-mi yavaşdır.

**2. DB-dirsə:** `EXPLAIN ANALYZE` ilə slow query-ni aşkar edirəm. N+1 varsa eager loading əlavə edirəm. Index lazımdırsa əlavə edirəm. Read replica-ya yönləndirilə bilərmi baxıram.

**3. External API-dirsə:** Timeout tənzimləyirəm. Cache edə bilərəmsə cache əlavə edirəm. Async-ə çevirə bilərəmsə 202 pattern-ə keçirəm.

**4. Application logic-dirsə:** Flame graph ilə ən çox vaxt harda keçir görürəm. N^2 loop, yersiz hesablama — bunları optimize edirəm.

**5. Hər şey yaxşıdır amma yenə yavaşdırsa:** Load test edirəm — concurrency-də bottleneck tapıram. Connection pool tənzimləyirəm.

Metrics: P50, P95, P99 — hamısını izləyirəm. Yalnız P50-yə baxmaq yanıldıcıdır."

---

### Nümunə: N+1 Problemi və Həlli

```php
<?php

// YANLIŞ — N+1 problemi
// GET /orders → 20 order qaytarır
// → 20 ayrı user sorğusu
// → 20 ayrı product sorğusu
// Cəmi: 41 DB sorğusu

$orders = Order::paginate(20);
foreach ($orders as $order) {
    echo $order->user->name;        // hər dəfə SELECT users WHERE id=?
    echo $order->product->price;    // hər dəfə SELECT products WHERE id=?
}

// DÜZGÜN — Eager Loading
// 3 sorğu: orders, users IN (...), products IN (...)
$orders = Order::with(['user', 'product'])->paginate(20);

// Telescope/Debugbar ilə yoxlama:
// Before: 41 queries, 850ms
// After:  3 queries, 45ms
```

```sql
-- N+1-i SQL-də görmək (slow query log-da):
-- Bu pattern çoxlayırsa N+1 var:
[2026-04-26 14:23:01] SELECT * FROM users WHERE id = 5
[2026-04-26 14:23:01] SELECT * FROM users WHERE id = 7
[2026-04-26 14:23:01] SELECT * FROM users WHERE id = 9
...

-- Olmalıdır:
[2026-04-26 14:23:01] SELECT * FROM users WHERE id IN (5, 7, 9, 11, 13...)
```

---

### Nümunə: Async API Pattern

```php
<?php

// YANLIŞ — Sinxron, yavaş endpoint
class ReportController extends Controller
{
    // 30 saniyə gözlədir — timeout riski
    public function generate(Request $request): JsonResponse
    {
        $report = $this->reportService->generate($request->all()); // 30s
        return response()->json(['data' => $report]);
    }
}

// DÜZGÜN — Asinxron, 202 Accepted
class ReportController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $report = Report::create([
            'user_id' => auth()->id(),
            'status'  => 'pending',
            'params'  => $request->validated(),
        ]);

        // Job queue-ya göndər
        GenerateReportJob::dispatch($report->id);

        // Dərhal cavab qaytar
        return response()->json([
            'job_id'     => $report->id,
            'status'     => 'pending',
            'status_url' => "/v1/jobs/{$report->id}",
        ], 202); // 202 Accepted
    }

    // Client polling edir
    public function status(string $reportId): JsonResponse
    {
        $report = Report::findOrFail($reportId);

        $response = ['status' => $report->status];

        if ($report->status === 'running') {
            $response['progress'] = $report->progress;
        }

        if ($report->status === 'completed') {
            $response['result_url'] = "/v1/reports/{$reportId}";
            $response['completed_at'] = $report->completed_at;
        }

        return response()->json($response);
    }
}
```

```
Sequence Diagram:
Client                  API             Queue      Worker
  │                      │                │           │
  │── POST /reports ────▶│                │           │
  │                      │── enqueue ────▶│           │
  │◀── 202 {job_id} ─────│                │           │
  │                      │                │◀─ dequeue─│
  │── GET /jobs/{id} ───▶│                │   (working)
  │◀── {status:running} ─│                │           │
  │                      │                │           │
  │── GET /jobs/{id} ───▶│                │           │
  │◀── {status:completed,│                │           │
  │    result_url}       │                │           │
  │── GET /reports/{id} ▶│                │           │
  │◀── {data: ...}       │                │           │
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1 — Latency Ölçümü

Production API-nızdakı ən yavaş 5 endpoint-i tapın:
- APM aləti istifadə edin (Telescope, Pulse, ya da curl timing)
- P50, P95, P99 dəyərlərini qeyd edin
- Latency breakdown-ı çıxarın: DB, cache, external API, application

### Tapşırıq 2 — N+1 Audit

Mövcud bir API endpoint-ini seçin:
- Telescope/Debugbar ilə DB sorğu sayına baxın
- N+1 varsa eager loading əlavə edin
- Before/after sorğu sayı və response time-ı müqayisə edin

### Tapşırıq 3 — Cache Strategiyası

`GET /products/catalog` endpoint-i üçün caching strategiyası dizayn edin:
- Cache key nə olacaq?
- TTL nə qədər?
- Məhsul qiyməti dəyişəndə cache necə invalidasiya olacaq?
- Cache-Control header-ləri necə ayarlanacaq?

### Tapşırıq 4 — Async Konvertasiya

Aşağıdakı endpoint-lərdən hansını async-ə çevirmək lazımdır?
1. `POST /users` — istifadəçi yaratmaq (~50ms)
2. `POST /invoices/generate` — PDF invoice yaratmaq (~8s)
3. `GET /dashboard/stats` — analitika (~2s)
4. `POST /orders/{id}/cancel` — ödənişi geri qaytarmaq (~1.5s)

Hər biri üçün qərarınızı əsaslandırın.

### Tapşırıq 5 — Load Test

k6 ilə öz API-nızı test edin:
- 10, 50, 100, 200 virtual user ilə 30s testlər keçirin
- P95 latency neçədən keçdikdə artmağa başlayır?
- Xəta ilk dəfə neçə RPS-də görünür?
- Bottleneck haradır — CPU? DB? Memory?

## Əlaqəli Mövzular

- [01-performance-profiling.md](01-performance-profiling.md) — Profiling yanaşması
- [02-query-optimization.md](02-query-optimization.md) — DB query optimizasiya
- [03-caching-layers.md](03-caching-layers.md) — Multi-level caching
- [09-async-batch-processing.md](09-async-batch-processing.md) — Async processing dərinləşdir
- [11-apm-tools.md](11-apm-tools.md) — APM alətləri ilə production monitoring
- [12-load-testing.md](12-load-testing.md) — Load testing ətraflı
