# SLA, SLO, SLI, Error Budget (Senior)

## Mündəricat
1. [Niyə SLI/SLO/SLA?](#niyə-sloslislo)
2. [SLI — Service Level Indicator](#sli--service-level-indicator)
3. [SLO — Service Level Objective](#slo--service-level-objective)
4. [SLA — Service Level Agreement](#sla--service-level-agreement)
5. [Error Budget](#error-budget)
6. [Nines hesablama](#nines-hesablama)
7. [SLI formulları](#sli-formulları)
8. [Alert strategy](#alert-strategy)
9. [Prometheus ilə implementasiya](#prometheus-ilə-implementasiya)
10. [PHP servis-də SLI-lar](#php-servis-də-sli-lar)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə SLI/SLO/SLA?

```
Problem: "Uptime vacibdir" — amma NƏ QƏDƏR?
  99%? 99.9%? 99.99%?
  Cost üstlüdür — hər "nine" on dəfə bahadır.
  
  Developer: "Daha yaxşı reliability!"
  Business: "Daha sürətli feature!"
  
  SRE metodologiyası bu konflikti həll edir:
  1. OBYEKTİV metriklər (SLI)
  2. HƏDƏF qoy (SLO)
  3. Error budget hesabla (1 - SLO)
  4. Budget xərclənəndə — feature yavaşlat, reliability artır
  5. Budget qalırsa — feature hızlı, risk götürmək OK

Bu "data-driven" qərardır, "sinsiyyə" yox.
```

---

## SLI — Service Level Indicator

```
SLI — ölçülə bilən METRİK.
"Sistemim NECƏ işləyir?" sualına rəqəmli cavab.

Good / Total format:
  SLI = good_events / total_events
  
Tipik SLI-lər:
  
  1. Availability (mövcudluq):
     good = 200/300 cavab, total = bütün request-lər
     SLI = (requests_successful / total_requests) * 100%
  
  2. Latency:
     good = p99 < 500ms cavab, total = bütün request-lər
     SLI = (requests_fast / total_requests) * 100%
  
  3. Quality:
     good = errors yoxdur və data düzdür
     (ML inference correctness, checkout completeness)
  
  4. Throughput:
     good = 1 saniyədə 1000+ event işlənilib
     (event pipeline-lar üçün)
  
  5. Durability (data saxlamanın etibarlılığı):
     good = saxlanmış data yenidən oxuna bilir
     (99.999999999% — "11 nines" S3)
  
  6. Freshness (data yenilik):
     good = son update < 5 dəqiqə əvvəl
     (real-time analytics üçün)

SLI SEÇMƏ qaydaları:
  ✓ USER-CENTRIC — "user-in hiss etdiyi"
  ✗ Server-centric ("CPU 80%") — SLI yox!
  ✓ Aşağı cardinality (request-level, user-level değil)
  ✓ Aggregatable — zaman boyunca birləşdirilə bilər
```

---

## SLO — Service Level Objective

```
SLO — SLI üzərində DAXİLİ hədəf.
"İstehsalat bu SLI-ni bu səviyyədə saxlayacaq"

Format:
  X% of Y events will meet Z condition over time T
  
Nümunələr:

  Availability:
    99.9% of requests succeed over 30 days (rolling)
    = 43 dəqiqə/ay downtime icazə verilir

  Latency:
    99% of requests < 500ms p99 over 28 days
    = 1% request 500ms+ ola bilər

  Availability + Latency birlikdə:
    99.5% of requests succeed AND respond in < 1s over 7 days

Zaman pəncərəsi (window):
  Short (1h): alerting üçün — tez reaksiya
  Medium (7d): trend analiz
  Long (30d): business reporting, SLA-ya uyğunluq

SLO seçərkən:
  ✗ 100% olmaz — bahalı və mənasız (nə vaxtsa downtime olur)
  ✓ User "daha çox reliability-dən istifadə etməyəcək" səviyyədə qoy
  ✓ Kompetitorlardan geri qalma, amma "overkill" olma
```

---

## SLA — Service Level Agreement

```
SLA — XARİCİ, biznes müqaviləsi.
"Müştərilərə vəd verdiyimiz minimum səviyyə, pozulsa — cərimə."

SLO və SLA fərqi:
  SLO: 99.95% daxili məqsəd
  SLA: 99.9% müştəri vədi (buffer qoy!)
  
  Daxili SLO HƏMİŞƏ SLA-dan yüksək olmalıdır.
  "SLA səndən gözlənilir, SLO özünə qoyursan."

Tipik SLA penalty:
  Uptime 99.9% altı:  ay ücrətinin 10% qaytarılır
  99.5% altı:         25%
  99.0% altı:         50%
  
Business SLA nümunələri:
  AWS EC2:    99.99% (annual)
  AWS S3:     99.9% availability, 99.999999999% durability
  Google Cloud SQL: 99.95%
  Stripe API: 99.995%
```

---

## Error Budget

```
Error Budget — "icazə verilən failure miqdarı".
  Error Budget = 1 - SLO
  
  SLO 99.9% → Error Budget 0.1%
  30 günlük window-da:
    30 × 24 × 60 = 43,200 dəqiqə
    0.1% = 43 dəqiqə "icazə verilən downtime"

İstifadə qaydası:
  Budget var  →  yeni feature deploy, risk götür
  Budget 0-da → feature freeze, reliability work

Budget burn rate:
  Hər saat 1% budget xərclənirsə:
    100 saat sonra budget bitəcək
    1 günə az qalırsa → DƏRHAL alert

Misal:
  SLO: 99.9% availability / 30 gün
  Budget: 43 dəqiqə downtime icazə
  
  Həftənin 1-ci günü 20 dəqiqə outage oldu:
    Budget xərcləndi: 46%
    Qalan: 23 dəqiqə, 23 gün müddətində
    → Feature freeze, root-cause analysis prioritet
  
  Həftəsonunda 5 dəqiqə oldu:
    Budget xərcləndi: 58%
    Qalan: 18 dəqiqə, 22 gün
    → Deploy-lar daha ciddi review ilə

Error budget policy:
  50% xərclənib  → postmortem məcburi hər incident üçün
  75% xərclənib  → feature deploy-lar yavaşla
  100% xərclənib → CODE FREEZE (yalnız reliability fix)
  110% xərclənib → engineering lead + SRE intervention
```

---

## Nines hesablama

```
Downtime per SLO (30-day window):

SLO      | Nines | Downtime/ay      | Downtime/il
─────────────────────────────────────────────────────
99%      | 2     | 7h 12m           | 3d 15h
99.5%    | 2.5   | 3h 36m           | 1d 19h
99.9%    | 3     | 43m              | 8h 46m
99.95%   | 3.5   | 21m              | 4h 23m
99.99%   | 4     | 4m 19s           | 52m 35s
99.999%  | 5     | 26s              | 5m 15s
99.9999% | 6     | 2.6s             | 31s

"Bir nine artırmaq 10 dəfə bahalıdır"
  2→3 nine: asan (retry, health check)
  3→4 nine: orta (HA, multi-AZ)
  4→5 nine: çətin (multi-region)
  5→6 nine: çox nadirdir (reality physics against you)

Əksər web app üçün: 99.9-99.95% uyğundur.
Financial/healthcare: 99.99%.
Mars rover: 99.9999%.
```

---

## SLI formulları

```
AVAILABILITY SLI:

  # HTTP-based
  SLI = (HTTP 2xx + 3xx responses) / (Total responses)
  
  # Exclude: 4xx (client error — user-in günahı)
  # Include: 5xx (server error)
  # Include: timeout, connection refused
  
  Prometheus:
    sum(rate(http_requests_total{status=~"2..|3.."}[5m]))
    /
    sum(rate(http_requests_total{status!~"4.."}[5m]))

LATENCY SLI:

  # Threshold-based (nə qədər sürətli?)
  SLI = (Requests faster than 500ms) / (Total requests)
  
  Prometheus (histogram):
    sum(rate(http_request_duration_seconds_bucket{le="0.5"}[5m]))
    /
    sum(rate(http_request_duration_seconds_count[5m]))

QUALITY SLI:

  # Error-free
  SLI = (Requests without errors) / (Total requests)
  
  # İnteqrasiya test kimi — end-to-end check

FRESHNESS SLI:

  # Data age
  SLI = (Reads where data age < 5min) / (Total reads)

CORRECTNESS SLI:

  # Canary-based
  Reference data ilə comparison.
  ML model: prediction accuracy > 90%.
```

---

## Alert strategy

```
Naïve alert:
  "Error rate > 1%" → alert!
  
  Problem:
    1% error 1 saniyə davam etsə də alert.
    Alert fatigue — sistem hazır olduğuna baxmayaraq.

Error budget burn rate alert:
  Slow burn:  1 saatda budget 1% xərclənir → ~100 saatda bitər (4 gün)
  Fast burn:  1 dəqiqədə 2% xərclənir → 50 dəqiqədə bitər

  Multi-window alert:
    Short (5m, 1h):  sürətli burn — indi yanır
    Long (1h, 6h):   yavaş burn — systematik problem
  
  Hər ikisinə alert → panik yox, amma prioritet düzgün.

Google SRE Workbook formulası:
  
  Page (high urgency):
    5m window: rate > 14.4 × SLO error rate
    AND
    1h window: rate > 14.4 × SLO error rate
    → 2% budget per hour
  
  Ticket (medium urgency):
    30m window: rate > 6 × SLO
    AND
    6h window: rate > 6 × SLO
    → 10% budget per day
```

---

## Prometheus ilə implementasiya

```yaml
# prometheus rules
groups:
- name: slo.availability
  rules:
  # Recording rules — SLI hesabla
  - record: sli:availability:ratio_5m
    expr: |
      sum(rate(http_requests_total{status=~"2..|3.."}[5m]))
      /
      sum(rate(http_requests_total{status!~"4.."}[5m]))
  
  - record: sli:availability:ratio_1h
    expr: avg_over_time(sli:availability:ratio_5m[1h])
  
  - record: sli:availability:ratio_30d
    expr: avg_over_time(sli:availability:ratio_5m[30d])
  
  # SLO = 99.9% → error budget 0.001
  # Burn rate = (1 - SLI) / (1 - SLO) = (1-SLI) / 0.001
  
  - record: slo:error_budget:burn_rate_1h
    expr: (1 - sli:availability:ratio_1h) / 0.001
  
  # Alert — fast burn
  - alert: HighErrorBudgetBurn
    expr: slo:error_budget:burn_rate_1h > 14.4
    for: 2m
    labels:
      severity: critical
    annotations:
      summary: "Error budget burning fast"
      description: "1h burn rate {{ $value }}× SLO budget"
```

```yaml
# Grafana dashboard panel (JSON-da)
{
  "title": "SLO Availability (30d)",
  "targets": [{
    "expr": "sli:availability:ratio_30d * 100",
    "legendFormat": "Availability %"
  }],
  "thresholds": [
    { "value": 99.9, "color": "green" },
    { "value": 99.5, "color": "yellow" },
    { "value": 0, "color": "red" }
  ]
}
```

---

## PHP servis-də SLI-lar

```php
<?php
// Prometheus PHP client — HTTP middleware ilə instrumentation
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class SloMetricsMiddleware
{
    private Histogram $latency;
    private Counter $requests;
    
    public function __construct(CollectorRegistry $registry)
    {
        $this->requests = $registry->getOrRegisterCounter(
            'app', 'http_requests_total',
            'HTTP requests total',
            ['method', 'route', 'status']
        );
        
        $this->latency = $registry->getOrRegisterHistogram(
            'app', 'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'route'],
            [0.01, 0.05, 0.1, 0.3, 0.5, 1.0, 2.0, 5.0]  // buckets
        );
    }
    
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
        } catch (\Throwable $e) {
            $status = 500;
            throw $e;
        } finally {
            $duration = microtime(true) - $start;
            $route = $request->route()?->getName() ?? 'unknown';
            
            $this->requests->inc([$request->method(), $route, $status]);
            $this->latency->observe($duration, [$request->method(), $route]);
        }
        
        return $response;
    }
}

// /metrics endpoint
Route::get('/metrics', function (CollectorRegistry $reg) {
    $renderer = new \Prometheus\RenderTextFormat();
    return response($renderer->render($reg->getMetricFamilySamples()))
        ->header('Content-Type', 'text/plain');
});
```

---

## İntervyu Sualları

- SLI, SLO, SLA fərqi nədir? Nümunə ilə izah edin.
- Error budget nə üçündür və necə hesablanır?
- 99.9% SLO aylıq neçə dəqiqə downtime-a icazə verir?
- "SLO 100% olsun" təklifinin problemi nədir?
- Multi-window alert niyə Single-window-dan üstündür?
- Availability SLI-də 4xx niyə "good" sayılmır? Niyə "ignore"?
- Latency SLI niyə average əvəzinə "threshold-based" hesablanır?
- Error budget bitdikdə team nə edir?
- Daxili SLO xarici SLA-dan niyə yüksək olmalıdır?
- "Burn rate" konsepti necə izah edilir?
- Prometheus-da SLI recording rule nə üçündür?
- ML sistemində "correctness" SLI necə modelləşdirilir?
