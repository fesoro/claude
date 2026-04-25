# Site Reliability Engineering (Lead)

## Nədir? (What is it?)

Site Reliability Engineering (SRE) – Google-da yaradılmış disiplin, software engineering yanaşmasını operation problemlərinə tətbiq edir. Ben Treynor Sloss (2003) tərəfindən başladıldı. SRE mühəndisləri yarı developer, yarı operator – ancaq Ops-in manual işini kod ilə avtomatlaşdırırlar. Əsas prinsiplər: SLI/SLO/SLA, error budget, toil reduction, blameless postmortem. SRE-nin məqsədi reliability-ni balanslaşdıran velocity-dir – həm stabil, həm sürətli release.

## Əsas Konseptlər (Key Concepts)

### SRE Prinsipləri

```
Google SRE-nin əsasları:

1. EMBRACE RISK
   100% availability mümkün deyil (və lazım deyil)
   Müəyyən error budget qəbul et

2. SERVICE LEVEL OBJECTIVES
   SLI: nə ölçülür?
   SLO: nə hədəfdir?
   SLA: müştəri ilə sazişdə nə var?

3. ELIMINATE TOIL
   Manual, təkrarlanan işləri avtomatlaşdır
   SRE vaxtının 50%-dən çoxu engineering olmalı

4. MONITORING DISTRIBUTED SYSTEMS
   4 Golden Signals: Latency, Traffic, Errors, Saturation

5. AUTOMATION
   "If you can't automate, you can't scale"

6. RELEASE ENGINEERING
   Sürətli, təhlükəsiz deploy
   Canary, feature flags, rollback

7. SIMPLICITY
   Mürəkkəblik = reliability düşməni
```

### SLI, SLO, SLA

```
SLI (Service Level Indicator) = ÖLÇÜ
   Nə ölçürük? Metrik və formula.
   Nümunələr:
   - Availability: uğurlu request / ümumi request
   - Latency: p99 response time
   - Throughput: requests per second
   - Error rate: 5xx / total requests
   - Durability: data itkisiz saxlanma

SLO (Service Level Objective) = MƏQSƏD
   İç hədəf. SLI nə qədər yaxşı olmalıdır?
   Nümunələr:
   - Availability: 99.9% (aylıq)
   - Latency: p99 < 500ms
   - Error rate: < 0.1%

SLA (Service Level Agreement) = SAZİŞ
   Müştəri ilə razılaşdırılmış legal contract
   Pozulsa maddi məsuliyyət (credit, refund)
   SLO-dan aşağı olmalıdır (buffer)

Nümunə:
   SLO: 99.9% availability (iç)
   SLA: 99.5% availability (müştəri)
   Fark = buffer (error budget + safety margin)

99.9% availability nə deməkdir?
Per year:   8h 45m downtime
Per month:  43m 49s
Per week:   10m 4s
Per day:    1m 26s
```

### Error Budget

```
Error budget = 1 - SLO
Məs: 99.9% SLO → 0.1% error budget

Məna: ay ərzində 43 dəqiqə downtime ola bilər
      bu müddəti "yandırmaq" olar yeni feature deploy edərkən

ERROR BUDGET POLICY:

Budget varsa (green):
- Yeni feature deploy et
- Risk götür
- Velocity prioritet

Budget bitib (red):
- Feature development dayandır
- Reliability-yə fokus
- Postmortem, bug fix

Nümunə:
Month SLO: 99.9% (43 dəqiqə error budget)
Week 1: Incident - 20 dəqiqə downtime
Week 2: Another incident - 30 dəqiqə
TOTAL: 50 dəqiqə > 43 dəqiqə budget
→ Deploy freeze, reliability work prioritet
```

### 4 Golden Signals (monitoring)

```
1. LATENCY
   Request-ə nə qədər vaxt sərf olunur?
   - Successful request latency
   - Failed request latency (ayrıca)
   - Percentiles: p50, p95, p99

2. TRAFFIC
   Sistem nə qədər yük götürür?
   - Requests per second (RPS)
   - Network I/O
   - Concurrent users

3. ERRORS
   Uğursuz request faizi
   - HTTP 5xx
   - Failed database queries
   - Timeout
   - Logical errors

4. SATURATION
   Sistem resource-ları nə qədər doludur?
   - CPU utilization
   - Memory usage
   - Disk I/O
   - Queue depth

# Four Golden Signals PromQL:
# Latency p99
histogram_quantile(0.99, rate(http_request_duration_seconds_bucket[5m]))

# Traffic
sum(rate(http_requests_total[5m]))

# Errors
sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m]))

# Saturation (CPU)
avg(rate(node_cpu_seconds_total{mode!="idle"}[5m]))
```

### Toil Reduction

```
TOIL = manual, repetitive, automatable, no-enduring-value iş
- Server manual restart
- Log fayllarını əl ilə təmizləmək
- Incident response template doldurmaq
- Account provisioning

Google SRE qaydası: toil < 50% time
Qalan > 50% engineering work (automation, tooling, project)

TOIL REDUCTION STRATEGIES:

1. Automate
   - Runbook → playbook → automated script
   - Cron job, Lambda, Kubernetes Job
   - GitOps (ArgoCD, Flux)

2. Self-service
   - Tickets yerinə portal/API
   - Terraform modules
   - Service catalog (Backstage)

3. Eliminate at source
   - Bug fix (kök səbəb)
   - Design change
   - Process improvement

4. Measure toil
   - Hər həftə toil tracking
   - Retrospective-də müzakirə
```

### Incident Management

```
INCIDENT LIFECYCLE:

1. DETECT
   - Monitoring alert (Prometheus, PagerDuty)
   - User report
   - Automated health check

2. RESPOND
   - Acknowledge alert (5 dəq içində)
   - On-call engineer mobilization
   - Create incident channel (Slack)

3. TRIAGE
   - Severity (SEV1-SEV4)
   - Impact (user count, revenue)
   - Incident Commander (IC) təyin et

4. MITIGATE
   - Restore service ASAP
   - Rollback, failover, scale up
   - Communication (status page)

5. RESOLVE
   - Confirm fix
   - Monitor for recurrence
   - Close incident

6. LEARN
   - Postmortem yaz (48 saat içində)
   - Action items
   - Share with team

ROLES:
- Incident Commander: koordinasiya, decision
- Communications Lead: status page, customer
- Operations Lead: technical fix
- Scribe: timeline yazır

SEVERITY LEVELS:
SEV1: Total outage, revenue impact, CEO involvement
SEV2: Major feature down, high user impact
SEV3: Degraded, workaround exists
SEV4: Minor, no user impact
```

### Postmortem (Blameless)

```markdown
# Postmortem: [Incident Title]

**Date**: 2024-04-15
**Duration**: 45 minutes (14:30 - 15:15 UTC)
**Severity**: SEV2
**Status**: Resolved

## Summary
Database connection pool exhausted, causing 60% of API requests to fail
with 500 errors during peak traffic.

## Impact
- 50,000 users affected
- 12,000 failed requests
- Estimated revenue impact: $3,500

## Timeline (UTC)
- 14:30 - Error rate spike detected by monitoring
- 14:32 - PagerDuty alert fired
- 14:35 - On-call engineer acknowledged
- 14:40 - Incident declared (IC: Alice)
- 14:45 - Identified DB connection pool exhausted
- 14:50 - Decision: restart PHP-FPM workers
- 15:00 - PHP-FPM restarted, error rate decreasing
- 15:15 - Full recovery confirmed

## Root Cause
Recent deploy changed N+1 query pattern, causing each request to open
5x more DB connections. Under peak load, connection pool limit (100)
reached, new requests failed.

## What went well
- Monitoring detected issue within 2 min
- On-call acknowledged within 5 min
- Clear communication in Slack
- Rollback option was ready

## What went wrong
- Load test in staging didn't catch N+1 issue
- No alert for DB connection pool saturation
- Manual mitigation (restart) took 15 min

## Action items
| # | Action | Owner | Priority | Due |
|---|--------|-------|----------|-----|
| 1 | Add DB connection pool monitoring | Bob | P0 | 1 week |
| 2 | Auto-restart on pool saturation | Alice | P1 | 2 weeks |
| 3 | Add N+1 query detection in CI | Carol | P1 | 1 month |
| 4 | Increase staging load test coverage | Dave | P2 | 2 months |

## Lessons Learned
- Always monitor connection pools, not just CPU/memory
- N+1 queries are hard to catch in staging - need production-like load
- Consider connection pool auto-scaling

## BLAMELESS: This postmortem focuses on process and systemic issues,
not individual mistakes. No one is at fault.
```

## Praktiki Nümunələr (Practical Examples)

### SLO tracking Laravel üçün

```yaml
# Prometheus alert rules
groups:
- name: laravel-slo
  rules:
  # SLI: Availability (uğurlu request / total)
  - record: laravel:availability:ratio_5m
    expr: |
      sum(rate(http_requests_total{status!~"5..",app="laravel"}[5m]))
      /
      sum(rate(http_requests_total{app="laravel"}[5m]))
  
  # Error budget
  - record: laravel:error_budget:remaining_30d
    expr: |
      1 - (
        (1 - avg_over_time(laravel:availability:ratio_5m[30d]))
        / 0.001
      )
  
  # Latency P99
  - record: laravel:latency:p99_5m
    expr: |
      histogram_quantile(0.99,
        sum(rate(http_request_duration_seconds_bucket{app="laravel"}[5m])) by (le)
      )
  
  # Alerts
  - alert: SLOErrorBudgetBurningFast
    expr: |
      (1 - laravel:availability:ratio_5m) * 1h > 14.4 * (1 - 0.999)
    for: 5m
    labels:
      severity: critical
      slo: availability
    annotations:
      summary: "Error budget burning >14.4x normal rate"
      description: "At this rate, 30-day error budget exhausted in <2 days"
  
  - alert: HighLatency
    expr: laravel:latency:p99_5m > 0.5
    for: 10m
    labels:
      severity: warning
    annotations:
      summary: "P99 latency > 500ms"
```

### Runbook nümunəsi

```markdown
# Runbook: Laravel High Error Rate

## Alert: `LaravelHighErrorRate`
**Severity**: SEV2
**Trigger**: 5xx error rate > 1% for 5 minutes

## Diagnostic Steps

### 1. Check error distribution
```
kubectl logs -n production -l app=laravel --tail=100 | grep ERROR
```

### 2. Check recent deploys
```
kubectl rollout history deployment/laravel-api -n production
```

### 3. Check database
```
# Connections
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
# Slow queries
mysql -e "SHOW PROCESSLIST;" | head -20
```

### 4. Check Redis
```
redis-cli INFO stats | grep -E "instantaneous_ops|connected_clients"
```

### 5. Check external dependencies
- Payment API: `curl https://api.stripe.com/healthcheck`
- Email service: check SendGrid status page

## Mitigation

### Option 1: Rollback (if recent deploy)
```
kubectl rollout undo deployment/laravel-api -n production
```

### Option 2: Scale up
```
kubectl scale deployment/laravel-api --replicas=10 -n production
```

### Option 3: Restart workers
```
kubectl rollout restart deployment/laravel-api -n production
```

## Escalation
If not resolved in 15 minutes:
- Page Senior SRE (+1-xxx)
- Notify CTO
- Update status page
```

## PHP/Laravel ilə İstifadə

### Laravel SLI collection

```php
// app/Http/Middleware/SLIMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;

class SLIMiddleware
{
    public function __construct(protected CollectorRegistry $registry) {}
    
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;
        
        // Histogram: request duration
        $histogram = $this->registry->getOrRegisterHistogram(
            'laravel',
            'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'route', 'status'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
        
        $histogram->observe($duration, [
            $request->method(),
            $request->route()?->getName() ?? 'unknown',
            (string) $response->getStatusCode(),
        ]);
        
        // Counter: request count
        $counter = $this->registry->getOrRegisterCounter(
            'laravel',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'route', 'status']
        );
        
        $counter->inc([
            $request->method(),
            $request->route()?->getName() ?? 'unknown',
            (string) $response->getStatusCode(),
        ]);
        
        return $response;
    }
}
```

### Laravel health check endpoint

```php
// routes/api.php
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/ready', [HealthController::class, 'ready']);

// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        // Liveness: is app alive?
        return response()->json(['status' => 'ok']);
    }
    
    public function ready(): JsonResponse
    {
        // Readiness: can app serve traffic?
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];
        
        $healthy = !in_array(false, $checks, true);
        
        return response()->json([
            'status' => $healthy ? 'ready' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
    
    protected function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Exception) {
            return false;
        }
    }
    
    protected function checkRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception) {
            return false;
        }
    }
    
    protected function checkStorage(): bool
    {
        try {
            return Storage::disk('s3')->exists('health-check.txt');
        } catch (\Exception) {
            return false;
        }
    }
}
```

### Laravel graceful shutdown

```php
// bootstrap/app.php - Laravel 11
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(/* ... */)
    ->withMiddleware(/* ... */)
    ->withExceptions(function (Exceptions $exceptions) {
        // Graceful error handling
        $exceptions->reportable(function (Throwable $e) {
            // Log-a yaz amma user-ə generic mesaj qaytar
            Log::error($e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => request()->all(),
            ]);
        });
    })
    ->create();

// Octane graceful shutdown
// config/octane.php
'max_execution_time' => 30,
'warm' => [
    // Pre-warm services
],
'listeners' => [
    WorkerStopping::class => [
        function () {
            // Cleanup on worker stop
            DB::disconnect();
            Redis::disconnect();
        }
    ],
],
```

## Interview Sualları (5-10 Q&A)

**S1: SRE və DevOps arasında fərq nədir?**
C: DevOps – mədəniyyət/fəlsəfə, Dev və Ops arasında əməkdaşlıq. SRE – Google-un DevOps-u implement etmə üsulu, konkret praktika və metriklər. SRE engineering focus (coding, automation), toil < 50%, error budget, SLO/SLA. DevOps daha geniş, SRE daha konkret. Google: "class SRE implements DevOps".

**S2: SLI, SLO, SLA fərqini izah edin.**
C: SLI (Indicator) – ölçü, "nə ölçürük" (availability, latency). SLO (Objective) – iç hədəf, "nə qədər yaxşı olmalıdır" (99.9% availability). SLA (Agreement) – müştəri ilə saziş, pozulsa cəzası var (credit). SLO SLA-dan tight olmalıdır (buffer), əks halda SLA pozulma riski.

**S3: Error budget necə istifadə olunur?**
C: 100% - SLO = error budget. Məs. 99.9% SLO → ayda 43 dəq "xərcləmə" imkanı. Budget varsa velocity (yeni feature), yoxsa reliability (bug fix, stabilization). Obyektiv qərarlar verir: "feature freeze nə zaman?". Deploy etmək riski budget-dan çıxır. Budget iki tərəfə məcbur edir: Dev isə yeni feature, Ops isə reliability üçün danışmır.

**S4: 4 Golden Signal nədir?**
C: Distributed system monitoring-də 4 əsas metrik: (1) Latency – request cavab vaxtı; (2) Traffic – sistem yükü (RPS); (3) Errors – uğursuz request faizi; (4) Saturation – resource doluğu (CPU, memory). Google SRE bunu "Monitoring Distributed Systems" fəslində yazıb. Hər servis üçün dashboard yaradılmalıdır.

**S5: Toil nədir və niyə azaldılmalıdır?**
C: Toil – manual, təkrarlanan, avtomatlaşdırıla bilən iş (server restart, log cleanup, account provisioning). Linear olaraq artır – servis böyüdükcə toil böyüyür. Google: SRE < 50% toil. Azaldılması üçün: automation, self-service portals, root cause fix. Toil ölçülməli (həftəlik tracking).

**S6: Blameless postmortem nə deməkdir?**
C: Postmortem-də şəxsləri günahlandırmadan (blame) sistemli problem axtarmaq. "Alice yanlışlıqla button sıxdı" yox, "process bunu səhv qəbul etməyə icazə verdi". Bu psixoloji təhlükəsizlik yaradır – insanlar incident-i gizlətməz, açıq danışır. Nəticə: real root cause tapılır, sistem yaxşılaşır. Etay-blame culture zərərlidir.

**S7: MTTR nədir və necə azaldıla bilər?**
C: Mean Time To Recovery – incident-dən bərpa üçün orta vaxt. Azaltma yolları: (1) Monitoring – tez detect; (2) Runbook – hazır prosedur; (3) Automation – avtomatik rollback/failover; (4) Practice – game day, chaos engineering; (5) Observability – tez diagnose (logs, traces); (6) On-call rotation. 99.9% SLO-da MTTR 1 saatdan az olmalıdır.

**S8: Incident Commander rolu nədir?**
C: Major incident-də (SEV1/SEV2) koordinasiya edən şəxs. Məsuliyyəti: decision-making, resource allocation, communication (stakeholders), timeline tracking. Technical fix özü etmir, başqalarını yönəldir. Dərin texniki biliklər yox, leadership/coordination bacarığı əsasdır. Böyük şirkətlərdə 24/7 on-call IC rotation.

**S9: Error budget bitdikdə nə edirik?**
C: Policy ilə davranış: (1) Feature development dayandırılır (və ya yavaşlayır); (2) Reliability work prioritet alır (bug fix, tooling); (3) Postmortem-lər review edilir; (4) Launch-lar deferred; (5) Operational review team meeting. Budget bərpa olanda normal rejimə qayıdır. Bu, obyektiv decision-making verir – subjective debates əvəzinə.

**S10: Chaos engineering SRE ilə necə əlaqəlidir?**
C: Chaos engineering SRE alətlərindən biridir – sistem resiliency test etmək üçün. SRE reliability-ni təmin edir, chaos engineering reliability-ni təsdiqləyir ("prove it"). Unknown failure mode-ları aşkar edir ki, real incident zamanı sürpriz olmasın. Game day-lər SRE team skill-lərini inkişaf etdirir. Her ikisi "embrace failure" fəlsəfəsindən gəlir.

## Best Practices

1. **Define SLIs/SLOs**: Biznes tələbi ilə sinxronlaşdır, ölçülə bilən metriklər.
2. **Error budget policy**: Yazılı siyasət, komanda razılaşsın.
3. **4 Golden Signals**: Hər servis üçün dashboard – Latency, Traffic, Errors, Saturation.
4. **Blameless postmortem**: Hər SEV1/SEV2 üçün 48 saat içində yaz.
5. **Action items tracking**: Postmortem action-ları icra edilsin (Jira ticket).
6. **On-call rotation**: Sustainable (8 saat/gün maksimum), rotation həftəlik.
7. **Runbook-lar**: Hər alert üçün runbook, yenilə.
8. **Monitoring coverage**: Əvvəlcə SLI, sonra alert, sonra dashboard.
9. **Toil tracking**: Həftəlik toil % ölç, 50%-dən az saxla.
10. **Automation**: Manual iş → script → fully automated.
11. **Chaos testing**: Rüblük game day, aylıq small experiment.
12. **Capacity planning**: Traffic forecast, proactive scale.
13. **Incident severity**: SEV1-SEV4 aydın kriteriya, response time SLA.
14. **Status page**: Public, incident zamanı dərhal update.
15. **Observability**: Metrics + Logs + Traces – üçü birdən.
16. **Deploy safety**: Canary, gradual rollout, automated rollback.
17. **Learning culture**: Postmortem-ləri share et, blameless psixoloji təhlükəsizlik.
18. **SRE/Dev əməkdaşlığı**: Joint on-call, joint postmortem, shared ownership.
