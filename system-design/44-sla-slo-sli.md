# SLA, SLO, SLI & Error Budgets

## Nədir? (What is it?)

SRE (Site Reliability Engineering) dünyasında reliability (etibarlılıq) rəqəmlərlə
ölçülür. SLI, SLO və SLA bu ölçmənin üç səviyyəsidir. Error budget isə bu rəqəmlərdən
doğan praktiki bir "büdcə"dir - nə qədər fail ola bilərik deyə qərar verir.

Sadə dillə: SLI = nə ölçürük, SLO = hədəfimiz nədir, SLA = müştəriyə nə vəd etmişik.

```
SLI (Indicator)  → Actual measurement: "p99 latency = 180ms"
SLO (Objective)  → Internal target:    "p99 latency < 200ms, 99.9% of the time"
SLA (Agreement)  → External contract:  "99.5% uptime or you get refund"

SLA  ≤  SLO  ≤  SLI (actual)
(loosest)  (tighter)  (reality)
```

## Əsas Konseptlər (Key Concepts)

### Üç Səviyyə (Three Levels)

```
┌─────────────────────────────────────────────────────────────┐
│ SLI — Service Level Indicator                               │
│ Nəyi ölçürük? (availability, latency, error rate)           │
│ Misal: "successful_requests / total_requests = 99.95%"      │
├─────────────────────────────────────────────────────────────┤
│ SLO — Service Level Objective                               │
│ Daxili hədəf. Komandanın özü üçün qoyduğu bar.              │
│ Misal: "availability ≥ 99.9% over 30 days"                  │
├─────────────────────────────────────────────────────────────┤
│ SLA — Service Level Agreement                               │
│ Müştəri ilə hüquqi müqavilə. Pozulsa penalty/refund var.    │
│ Misal: "99.5% uptime or 10% credit back"                    │
└─────────────────────────────────────────────────────────────┘
```

**Vacib qayda:** SLO həmişə SLA-dan daha sıx olmalıdır. Əgər SLA 99.5%-disə,
SLO 99.9% olmalıdır - ki SLA-nı pozmadan əvvəl xəbərdar olaq.

### Availability Math (Nines → Downtime)

| SLO     | Downtime per year | Per month   | Per week  | Per day   |
|---------|-------------------|-------------|-----------|-----------|
| 99%     | 3.65 days         | 7.2 hours   | 1.68 h    | 14.4 min  |
| 99.5%   | 1.83 days         | 3.6 hours   | 50.4 min  | 7.2 min   |
| 99.9%   | 8.76 hours        | 43.8 min    | 10.1 min  | 86.4 sec  |
| 99.95%  | 4.38 hours        | 21.9 min    | 5.04 min  | 43.2 sec  |
| 99.99%  | 52.56 minutes     | 4.38 min    | 60.5 sec  | 8.64 sec  |
| 99.999% | 5.26 minutes      | 26.3 sec    | 6.05 sec  | 0.86 sec  |

Hər bir əlavə "nine" təqribən 10x daha baha olur. 99.99% üçün ayda cəmi 4 dəqiqə
downtime var - deploy, DB migration, hətta DNS TTL bunu yeyə bilər.

### Common SLI Types

```
1. Availability    → successful_requests / valid_requests
2. Latency         → p50, p95, p99 (percentile distributions)
3. Throughput      → requests per second, jobs processed/min
4. Error rate      → 5xx responses / total responses
5. Durability      → data_not_lost / total_data (S3 = 99.999999999%)
6. Freshness       → how stale is the data (cache, replica lag)
7. Correctness     → wrong_results / total_results (billing, search)
```

### Compound SLO (Service Dependencies)

Bir service başqa service-lərdən asılıdırsa, həqiqi availability azalır:

```
Frontend (99.9%) → API (99.9%) → Database (99.9%)

End-to-end = 0.999 × 0.999 × 0.999 = 0.997 = 99.7%

Yəni hər komponent 99.9% olsa da, zəncir 99.7%-dir.
Downtime per year: 8.76h → 26.3h (3x artım)
```

Həll yolu:
- Hər dependency üçün daha yüksək SLO tələb etmək
- Critical path-i qısaltmaq (fewer services)
- Caching, circuit breaker, fallback
- Async patterns (queue, retry)

### Error Budget

```
Error Budget = 100% - SLO
SLO 99.9% → Error Budget = 0.1% = 43.8 dəqiqə/ay

Bu "büdcə"ni necə xərcləyə bilərik:
- Planned maintenance
- Risky deployments
- Experimental features
- Infrastructure changes
```

**Error Budget Policy (qayda):**

```
Budget remaining > 50%  → Full velocity, feature work, experiments
Budget 10% — 50%       → Slow down, more testing, careful deploys
Budget 0% — 10%        → Freeze new features, focus on reliability
Budget exhausted       → Full freeze, only fixes & rollbacks
```

### Burn Rate Alerts

Error budget-in nə qədər sürətlə bitdiyini ölçür:

```
Burn rate = (errors / total) / (1 - SLO)

Misal: SLO = 99.9%, error budget = 0.1%
Son 1 saatda error rate = 1% → burn rate = 10x

Fast burn (critical):  2% of 30-day budget in 1 hour  → page immediately
Slow burn (warning):   10% of 30-day budget in 3 days → ticket / Slack
```

Fast burn - outage var, dərhal response. Slow burn - degradation, gündüz vaxtı düzəlt.

### Percentile Latency (Why p99 Matters)

Average (orta) latency aldadıcıdır. 1000 request-in 990-ı 50ms, 10-u 5000ms olsa:
- Average = 99.5ms (yaxşı görünür)
- p99 = 5000ms (pis!)

```
Request distribution:
p50 (median)  = 80ms   → 50% of users see this or better
p95           = 200ms  → 95% of users see this or better
p99           = 450ms  → 99% of users see this or better
p99.9         = 1200ms → 0.1% of users hit this (but they complain loudly)
```

**Tail latency amplification (fan-out problemi):**

```
1 request fan-out to 100 backend calls, each p99 = 100ms
Probability ALL 100 complete fast = 0.99^100 = 36%
→ 64% of user requests hit at least one slow call!

Həll: hedging, parallel requests, timeout+retry, caching
```

## Praktiki Nümunələr (Practical Examples)

### Laravel Middleware — SLI Collection

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class MeasureSli
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (microtime(true) - $start) * 1000;

        $status = $response->getStatusCode();
        $route = $request->route()?->getName() ?? $request->path();
        $isError = $status >= 500;
        $isValid = $status < 400 || $status >= 500; // 4xx = client error, not SLI failure

        // Good events / valid events ratio
        if ($isValid) {
            Redis::hincrby("sli:valid:{$route}", date('YmdH'), 1);
            if (!$isError) {
                Redis::hincrby("sli:good:{$route}", date('YmdH'), 1);
            }
        }

        // Latency histogram (bucket-based)
        $bucket = $this->latencyBucket($durationMs);
        Redis::hincrby("sli:latency:{$route}:" . date('YmdH'), $bucket, 1);

        // Prometheus-style header (observability)
        $response->headers->set('X-Response-Time-Ms', round($durationMs, 2));

        return $response;
    }

    private function latencyBucket(float $ms): string
    {
        foreach ([50, 100, 200, 500, 1000, 2000, 5000] as $bucket) {
            if ($ms <= $bucket) return "le_{$bucket}";
        }
        return 'le_inf';
    }
}
```

### SLO Calculation Service

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SloCalculator
{
    private const AVAILABILITY_SLO = 0.999; // 99.9%
    private const LATENCY_SLO_MS = 200;
    private const LATENCY_TARGET = 0.95; // 95% of requests under 200ms

    public function availability(string $route, int $hours = 720): array
    {
        $good = 0;
        $valid = 0;

        for ($i = 0; $i < $hours; $i++) {
            $hour = date('YmdH', strtotime("-{$i} hour"));
            $good += (int) (Redis::hget("sli:good:{$route}", $hour) ?? 0);
            $valid += (int) (Redis::hget("sli:valid:{$route}", $hour) ?? 0);
        }

        if ($valid === 0) {
            return ['sli' => null, 'meets_slo' => null];
        }

        $sli = $good / $valid;
        $errorBudget = 1 - self::AVAILABILITY_SLO;
        $errorsAllowed = $valid * $errorBudget;
        $errorsUsed = $valid - $good;
        $budgetRemaining = max(0, 1 - ($errorsUsed / max($errorsAllowed, 1)));

        return [
            'sli' => round($sli * 100, 4),
            'slo' => self::AVAILABILITY_SLO * 100,
            'meets_slo' => $sli >= self::AVAILABILITY_SLO,
            'error_budget_remaining_pct' => round($budgetRemaining * 100, 2),
            'total_requests' => $valid,
            'failed_requests' => $errorsUsed,
        ];
    }

    public function burnRate(string $route, int $windowHours): float
    {
        $stats = $this->availability($route, $windowHours);
        if ($stats['sli'] === null) return 0;

        $errorRate = 1 - ($stats['sli'] / 100);
        $errorBudget = 1 - self::AVAILABILITY_SLO;
        return $errorRate / $errorBudget;
    }
}
```

### Health Check Endpoint (SLI Export)

```php
<?php
namespace App\Http\Controllers;

use App\Services\SloCalculator;
use Illuminate\Http\JsonResponse;

class SloController
{
    public function __construct(private SloCalculator $slo) {}

    public function metrics(): JsonResponse
    {
        $routes = ['api.orders.create', 'api.orders.list', 'api.checkout'];
        $report = [];

        foreach ($routes as $route) {
            $report[$route] = [
                'availability' => $this->slo->availability($route, 720), // 30 days
                'burn_rate_1h' => $this->slo->burnRate($route, 1),
                'burn_rate_6h' => $this->slo->burnRate($route, 6),
            ];
        }

        return response()->json($report);
    }
}
```

### Prometheus Exporter Format

```
# HELP http_requests_total Total HTTP requests
# TYPE http_requests_total counter
http_requests_total{route="api.orders.create",status="success"} 198234
http_requests_total{route="api.orders.create",status="error"} 87

# HELP http_request_duration_seconds Request latency
# TYPE http_request_duration_seconds histogram
http_request_duration_seconds_bucket{route="api.orders.create",le="0.05"} 120000
http_request_duration_seconds_bucket{route="api.orders.create",le="0.1"} 175000
http_request_duration_seconds_bucket{route="api.orders.create",le="0.2"} 195000
http_request_duration_seconds_bucket{route="api.orders.create",le="0.5"} 198100
http_request_duration_seconds_bucket{route="api.orders.create",le="+Inf"} 198321
```

### Where to Measure

```
Client-side (browser/mobile)  → real user experience, includes network
    ↓
Load Balancer (nginx, ALB)    → server-side truth, excludes client network
    ↓
Application (Laravel)         → business logic latency, no proxy time
    ↓
Database / Downstream         → dependency health

Tövsiyə: LB səviyyəsində ölç. User-centric amma stabil.
Client-side çox noisy, app-level internal overhead-i görmür.
```

## Interview Sualları

**S: SLA, SLO, SLI fərqi nədir?**
C: SLI - nəyi ölçürük (actual measurement, misal availability = 99.95%).
SLO - daxili hədəf (99.9% over 30 days). SLA - müştəri ilə müqavilə, pozulsa refund
var (99.5% uptime). SLO həmişə SLA-dan sıx olmalıdır ki buffer qalsın.

**S: Error budget nədir və necə istifadə olunur?**
C: Error budget = 100% - SLO. Əgər SLO 99.9%-disə, ayda 43.8 dəqiqə "icazə verilən"
downtime var. Budget qalırsa - yeni feature deploy et, experiment et. Budget bitirsə -
yeni release dayandır, reliability üzərində işlə. Bu həm dev sürətini, həm də
stabilliyi balanslaşdırır.

**S: Niyə p99 latency orta latency-dən vacibdir?**
C: Average aldadıcıdır - bir neçə slow request sayını ört-bas edir. p99 o deməkdir
ki userin 1%-i bundan pis təcrübə görür. Fan-out sistemdə (1 request → 100 backend)
p99=100ms olsa belə, user-in 64%-i ən azı 1 slow call görür (tail latency
amplification).

**S: 99.9% availability kifayətdirmi?**
C: Asılıdır. 99.9% = ayda 43 dəqiqə downtime. Internal tools üçün OK. Payment
sistemi üçün yox - ayda 43 dəqiqə revenue itkisi olar. Banking üçün 99.99%+
lazımdır. Amma hər "nine" təqribən 10x baha olur, business value ilə balans tutulmalıdır.

**S: Burn rate alert nədir?**
C: Error budget-in nə qədər sürətlə bitdiyini ölçür. Fast burn (2% budget in 1h) -
dərhal page, kritik outage var. Slow burn (10% in 3 days) - ticket/Slack, gündüz vaxtı
bax. Sabit threshold (5xx > 1%) alerts çox noisy olur - burn rate kontekst verir.

**S: Compound SLO necə hesablanır?**
C: Service-lər zəncirvarı çağırılırsa, availability-lər vurulur. 3 service hər biri
99.9% → end-to-end = 0.999³ = 99.7%. Həll: critical path-i qısalt, async yap
(queue), circuit breaker əlavə et, cache istifadə et ki dependency-dən asılı olmayasan.

**S: SLO-nu necə təyin edirsən?**
C: 1) User journey-i müəyyən et (checkout, login). 2) Hansı ölçü user happiness-ə
bağlıdır (availability? latency?). 3) Current performance-a bax, reasonable target qoy
(misal: indi 99.5% → hədəf 99.9%). 4) Business ilə razılaş - çox sıx olsa dev
velocity-ni öldürür, çox boş olsa user narazıdır. 100% hədəf səhvdir - imkansız
və bahalıdır.

**S: Good events / valid events fərqi?**
C: SLI formul: good_events / valid_events. Valid events - sayılmalı olan hər şey
(2xx, 5xx). 4xx (client error) çıxarılır - bu bizim xəta deyil. Good events - uğurlu
nəticələr. Misal: 1000 request, 900 = 2xx, 50 = 4xx (bad input), 50 = 5xx.
Availability = 900 / (900 + 50) = 94.7%, 4xx kənara atılır.

## Best Practices

1. **Az sayda SLO saxla** - 3-5 critical user journey üçün SLO. 50 metric izləmək
   hamısını mənasızlaşdırır.
2. **User-centric ölç** - Internal metric-lər (CPU, memory) SLO deyil. User nə
   hiss edir (latency, availability) SLI olmalıdır.
3. **SLO < SLA** - Daxili hədəf xarici vədd-dən həmişə sıx. SLA 99.5%-disə, SLO
   99.9% qoy. Buffer olmadan SLA pozulanda gec olur.
4. **Error budget policy yaz** - Budget bitəndə nə olacaq? Freeze? Slowdown?
   Əvvəlcədən qərarlaşdırılmalıdır, yoxsa hər dəfə mübahisə olur.
5. **Burn rate alerts** - Sabit threshold (error > 1%) əvəzinə burn rate istifadə
   et. Multi-window alerts (1h + 6h) false positive-ləri azaldır.
6. **Percentile istifadə et** - Average latency SLO-lara qoyma. p95, p99 real
   user experience-i əks etdirir.
7. **LB səviyyəsində ölç** - Client-side noisy, app-level internal. Load balancer
   stabil və user-centric-dir.
8. **Compound SLO-nu nəzərə al** - Dependency chain uzundursa, hər link daha
   yüksək SLO tələb edir. Critical path-i qısalt.
9. **SLO review et** - Rüblük SLO-lara bax. Həmişə yerinə yetirilirsə - çox boşdur,
   sıxlaşdır. Heç yerinə yetirilmirsə - səbəbi tap, hədəfi dəyişmə.
10. **100% pursuit etmə** - 100% availability imkansız və bahalıdır. User 99.99%
    ilə 100% arasında fərqi hiss etmir, amma cost fərqi böyükdür.
11. **Anti-pattern: SLO on vanity metrics** - CPU usage, memory usage SLO olmamalıdır.
    User onları görmür. Result-based metrics (request success, latency) SLO olur.
12. **Blameless post-mortem** - SLO pozulanda fərdi günahlandırma yox, sistemi düzəlt.
    Error budget bunun üçün var - risk götürmək normal qarşılanır.
