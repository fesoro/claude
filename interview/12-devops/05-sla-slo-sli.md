# SLA / SLO / SLI (Lead ⭐⭐⭐⭐)

## İcmal
SLA (Service Level Agreement), SLO (Service Level Objective), SLI (Service Level Indicator) — sistemin etibarlılığını ölçmək, hədəfləmək və müqavilə hüquqi olaraq zəmanət vermək üçün istifadə olunan çərçivədir. Google SRE (Site Reliability Engineering) kitabında sistematikləşdirilmişdir. Lead səviyyəsindəki developer bu anlayışları həm texniki həm də biznes kontekstini başa düşərək tətbiq edir — error budget policy-ni deployment qərarları ilə birləşdirir.

## Niyə Vacibdir
"Sistem reliable olmalıdır" — bu cümlə həm çox istəkdir, həm ölçülə bilməz. SLI/SLO çərçivəsi bu abstraksiyadan çıxıb konkret, ölçülə bilən hədəflər qoyur. Lead developer kimi qərar verməlisiniz: "Bu feature ilə SLO-nu vura bilərikmi?" ya da "Error budget tükənib, bu deployment-ı dayandırmalıyıq." Engineering team ilə product team arasında obyektiv dialog yaratmaq üçün bu framework vacibdir.

## Əsas Anlayışlar

- **SLI (Service Level Indicator)**: Sistemin performansını ölçən konkret metrika. Availability SLI: `successful_requests / total_requests * 100`. Latency SLI: `requests_under_500ms / total_requests * 100`. Error rate SLI: `error_requests / total_requests * 100`. Throughput: `requests_per_second`.

- **SLO (Service Level Objective)**: SLI üçün hədəflənən dəyər. Daxili razılaşma — team-in öz öhdəliyi. "p99 latency < 500ms, 30 günlük pəncərədə 99.9% vaxt." "Error rate < 0.1%, aylıq basis." "Availability > 99.5%."

- **SLA (Service Level Agreement)**: Müştəri ilə hüquqi müqavilə. SLO-dan daha konservativ tutulur — buffer var. SLO 99.9% → SLA 99.5% (SLO breach SLA-ya çevrilməsin deyə). SLA pozulduqda: refund, credit, penalti.

- **Əlaqə şəması**: SLI (ölçülən metrika) → SLO (daxili hədəf) → SLA (xarici öhdəlik). Hər biri əvvəlkindən istifadə edir, hər biri bir az daha konservativdir.

- **Error Budget**: SLO-nun tərsi — sistemin "itirilə bilən" vaxt ya da xəta payı. SLO 99.9% → Error Budget = 0.1%. 30 günlük pəncərədə: 43,200 dəqiqə × 0.001 = **43.2 dəqiqə/ay**. Bu 43.2 dəqiqəlik "risk götürmə limiti"dir.

- **Error Budget Policy**: Budget > 50% → yeni feature deploy etmək mümkün. Budget < 50% → yalnız stability work. Budget tükənib → feature freeze, yalnız reliability fix. Bu policy engineering ilə product team arasındakı müqavilədir.

- **Error Budget Consumed hesabı**: `(1 - SLI_actual) / (1 - SLO_target)`. Nümunə: SLO 99.9%, aktual 99.7%: `(1-0.997)/(1-0.999) = 0.003/0.001 = 3x` — budget 3 dəfə aşılıb!

- **Burn Rate**: Normal burn rate 1x (budget 30 günə tam sərfolunar). Fast burn 14.4x = 2 saatda günlük budget tükənir → dərhal alert. Slow burn 1.2x = 25 günə budget tükənər → notification. Burn rate alerting E2E outage-dan daha erkən xəbər verir.

- **100% SLO-nun problemi**: 100% uptime hədəfləmək = "heç nə dəyişdirməyin" deməkdir. Hər deployment risk daşıyır. Error budget qalmazsa yeni feature deploy edilə bilməz. Engineering çox konservativ olur, innovation dayanır.

- **SLO Window növləri**: Rolling window (davam edən 30 gün) — hər an son 30 günü dəyərləndirirsən, real-time budget awareness, kompleks hesablama. Calendar window (aylıq reset) — sadə, amma ayın sonunda budget qalıbsa "sonuncu günü risk götür" meyli.

- **Practical SLO hədəflər**: Internal API: 99.5%. External API: 99.9%. Payment processing: 99.95%. Healthcare/critical: 99.99%. Bu rəqəmlər error budget-i müəyyən edir.

- **Dependency effect**: İki servis ardıcıl: `99.9% × 99.9% = 99.8%` available. Redundant servis: `1 - (0.001 × 0.001) = 99.9999%`. System-level SLO daima tək service SLO-sundan aşağıdır.

- **Toil vs Reliability Work**: Google SRE "toil" anlayışı — manual, repetitive operational iş. SRE öhdəliyi: toil < 50%. Error budget varsa yeni feature. Budget yoxdursa: reliability, automation, toil reduction.

- **SLO monitoring toolları**: Prometheus + Grafana — metrics topla, SLO dashboards. Sloth (yaml-based SLO generator). Pyrra. DataDog SLO tracking. Google Cloud Operations.

- **SLI seçimi prinsipi**: Yalnız istifadəçinin hiss etdiyi şeyləri SLI kimi seçin. CPU utilization SLI deyil — istifadəçi CPU-nu görmür. Response latency, error rate, availability — bunlar istifadəçi experiencesi ilə bağlıdır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
SLA/SLO/SLI-ı izah edərkən yalnız tərif vermə. Error budget konseptini izah et — "error budget tükənəndə nə edirik" sualına cavab ver. 100% SLO-nun niyə mənasız olduğunu izah et. Real layihə nümunəsi: "Payment API üçün SLO 99.95% idi, bir deployment bunu pozdu, feature freeze etdik" — bu operational maturity göstərir.

**Junior-dan fərqlənən senior cavabı:**
Junior: "SLA müqavilədir, SLO hədəfdir."
Senior: "SLO 99.9% = ayda 43.2 dəqiqə error budget. Budget 80% qalıbsa normal deployment. Budget tükənirsə feature freeze — bu qərar objective."
Lead: "Error budget policy product team ilə razılaşdırılmışdır. Hər sprint-də budget status görüşülür. Burn rate alert-i SLO breach-dən əvvəl xəbər verir."

**Follow-up suallar:**
- "Error budget nədir? Necə istifadə olunur?"
- "100% SLO niyə hədəflənməməlidir?"
- "SLA SLO-dan niyə aşağı tutulur?"
- "Burn rate alerting nədir? Niyə SLO threshold-dan daha yaxşıdır?"
- "Multi-service sistemdə SLO necə hesablanır?"

**Ümumi səhvlər:**
- SLA = SLO kimi başa düşmək
- Error budget policy olmadan SLO qoymaq — rəqəm var, amma decision framework yoxdur
- Bütün servisləri eyni SLO ilə ölçmək — critical payment service ilə internal log service eyni SLO-ya ehtiyac duymur
- SLO-nu monitoring olmadan qoymaq — ölçülmürsə yoxdur
- CPU/memory-ni SLI kimi seçmək — istifadəçi baxımından deyil

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Error budget-i deployment qərarları ilə birləşdirmək. "Ayın 15-inəcən error budget 80% sərfolunmuşsa deployment policy-ni dəyişirik" — bu operational maturity göstərir. Burn rate alerting-i bilmək texniki dərinlikdir.

## Nümunələr

### Tipik Interview Sualı
"SLO ilə SLA arasındakı fərq nədir? Error budget nədir və necə istifadə olunur?"

### Güclü Cavab
"SLI ölçülən metrikadır — məsələn, successful request faizi. SLO daxili hədəfimizdir — '99.9% availability'. SLA isə müştəri ilə müqavilədir — '99.5% zəmanət verilir' — SLO-dan aşağı tutulur ki, SLO breach SLA-ya çevrilməsin. Error budget SLO-nun tərsidir: 99.9% SLO = 0.1% 'sına bilərsiniz' faizi — ayda 43 dəqiqə. Bu budget feature deployment, canary release, maintenance window üçün xərclənir. Budget tükəndikdə feature freeze, yalnız reliability work. Ən böyük xəta 100% SLO hədəfləmək — bu 'heç nə dəyişdirməyin' deməkdir, engineering innovation dayanır."

### Hesablama Nümunəsi

```
Availability SLO Nines cədvəli:
─────────────────────────────────────────────
99%     = 3.65 gün/il    = 7.2 saat/ay
99.5%   = 1.83 gün/il    = 3.6 saat/ay
99.9%   = 8.76 saat/il   = 43.2 dəqiqə/ay
99.95%  = 4.38 saat/il   = 21.9 dəqiqə/ay
99.99%  = 52.6 dəqiqə/il = 4.32 dəqiqə/ay
99.999% = 5.26 dəqiqə/il = 26 saniyə/ay

Error Budget Consumed Hesabı:
─────────────────────────────────────────────
SLO: 99.9%
Son ayın actual availability: 99.85%

Consumed = (1 - 0.9985) / (1 - 0.999)
         = 0.0015 / 0.001
         = 1.5x  →  budget 1.5x istifadə edilib

Qalan budget: 43.2 min × (1 - 1.5) = NEGATIF
→ Feature Freeze! Bu ay reliability work.

Multi-Service SLO:
─────────────────────────────────────────────
Order Service:    99.9%
Payment Service:  99.95%
Inventory Service: 99.9%

Combined (ardıcıl): 0.999 × 0.9995 × 0.999 = 99.749%
→ Checkout flow-un SLO-su ən zəif service-dən aşağıdır!
→ Redundancy əlavə etmək lazımdır
```

```yaml
# Prometheus — SLO Rules
# prometheus/rules/slo.yml
groups:
  - name: slo_rules
    interval: 30s
    rules:
      # SLI: 5 dəqiqəlik error rate
      - record: job:http_request_error_rate:5m
        expr: |
          sum(rate(http_requests_total{status=~"5.."}[5m]))
          /
          sum(rate(http_requests_total[5m]))

      # SLI: p99 latency
      - record: job:http_request_duration_p99:5m
        expr: |
          histogram_quantile(0.99,
            sum(rate(http_request_duration_seconds_bucket[5m])) by (le)
          )

      # Error Budget Remaining (30 gün)
      - record: job:slo_error_budget_remaining:30d
        expr: |
          1 - (
            (1 - sum(increase(http_requests_total{status!~"5.."}[30d]))
                 / sum(increase(http_requests_total[30d])))
            /
            (1 - 0.999)  # SLO: 99.9%
          )

  - name: slo_alerts
    rules:
      # Fast Burn Alert — 2 saatda günlük budget tükənir
      - alert: SLOErrorBudgetFastBurn
        expr: |
          job:http_request_error_rate:5m > (14.4 * (1 - 0.999))
        for: 2m
        labels:
          severity: critical
          team: platform
        annotations:
          summary: "Fast burn: {{ $value | humanizePercentage }} error rate"
          description: |
            Error budget günlük 14.4x sürətlə tükənir.
            2 saatda günlük budget bitar.
            Runbook: https://wiki/slo-fast-burn
          dashboard: "https://grafana/d/slo-dashboard"

      # Slow Burn Alert — 25 günə budget tükənər
      - alert: SLOErrorBudgetSlowBurn
        expr: |
          job:http_request_error_rate:5m > (1.2 * (1 - 0.999))
        for: 60m
        labels:
          severity: warning
          team: platform
        annotations:
          summary: "Slow burn — budget 25 günə tükənəcək"
```

```yaml
# Grafana Dashboard — SLO Overview
# panels:
#
# Panel 1: Current SLI (son 30 gün rolling)
# Metric: job:slo_error_budget_remaining:30d * 100
# Threshold: < 20% red, < 50% yellow, > 50% green
#
# Panel 2: Error Budget Burn Rate
# Metric: job:http_request_error_rate:5m / (1 - 0.999)
# Threshold: > 14.4 red (fast burn), > 1.2 yellow (slow burn)
#
# Panel 3: Availability Calendar (heatmap)
# Monthly availability heatmap
#
# Panel 4: Latency p50/p95/p99 trends
# SLO threshold line əlavə et (p99 < 500ms)
#
# Panel 5: Recent incidents
# Annotation-lardan

# Sloth ile YAML-based SLO definition:
# sloth/slos/api-slo.yaml
version: prometheus/v1
service: payment-api
slos:
  - name: payment-api-availability
    objective: 99.95
    description: Payment API availability
    sli:
      events:
        error_query:   'sum(rate(http_requests_total{job="payment-api",status=~"5.."}[{{.window}}]))'
        total_query:   'sum(rate(http_requests_total{job="payment-api"}[{{.window}}]))'
    alerting:
      name: PaymentAPIAvailability
      page_alert:
        labels: {severity: critical, oncall: payment-team}
      ticket_alert:
        labels: {severity: warning}
```

```php
// Laravel — SLO-aware Exception Handling
// Application-level SLI tracking

class SloMetricsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration   = microtime(true) - $startTime;
        $statusCode = $response->getStatusCode();

        // Prometheus/StatsD-ə SLI metrikalarını göndər
        $labels = [
            'route'  => $request->route()?->getName() ?? 'unknown',
            'method' => $request->method(),
            'status' => (string) $statusCode,
        ];

        // Request count (error rate SLI üçün)
        app(MetricsCollector::class)->increment('http_requests_total', $labels);

        // Latency histogram (latency SLI üçün)
        app(MetricsCollector::class)->histogram(
            'http_request_duration_seconds',
            $duration,
            $labels
        );

        // Yüksək latency log
        if ($duration > 1.0) { // 1 saniyə > SLO threshold
            Log::warning('SLO latency threshold exceeded', [
                'route'    => $request->route()?->getName(),
                'duration' => $duration,
                'slo_ms'   => 500,
            ]);
        }

        return $response;
    }
}

// Error Budget Policy enforcement (automatic)
class DeploymentGateService
{
    public function canDeploy(string $service, string $environment): DeploymentDecision
    {
        if ($environment !== 'production') {
            return DeploymentDecision::allow('Non-production environment');
        }

        $budgetRemaining = $this->getErrorBudgetRemaining($service);

        return match (true) {
            $budgetRemaining > 0.50 => DeploymentDecision::allow(
                "Error budget {$budgetRemaining}% remaining — deployment allowed"
            ),
            $budgetRemaining > 0.20 => DeploymentDecision::warn(
                "Low error budget ({$budgetRemaining}%) — deploy with caution, canary only"
            ),
            $budgetRemaining > 0   => DeploymentDecision::requireApproval(
                "Critical error budget ({$budgetRemaining}%) — SRE lead approval required"
            ),
            default => DeploymentDecision::deny(
                "Error budget exhausted — feature freeze active"
            ),
        };
    }
}
```

### Müqayisə Cədvəli

| Konsept | Tərəf | Nə edir | Nümunə |
|---------|-------|---------|--------|
| SLI | Engineering | Ölçür | "99.85% successful requests" |
| SLO | Engineering (daxili) | Hədəfləyir | "99.9% olmalıdır" |
| SLA | Business (xarici) | Zəmanət verir | "99.5% müqavilə" |
| Error Budget | Engineering | Qərar verir | "43.2 dəqiqə qalıb" |
| Burn Rate | Engineering | Xəbər verir | "14.4x fast burn — alert!" |

## Praktik Tapşırıqlar

1. Mövcud servisiniz üçün 3 SLI müəyyən edin — availability, latency, error rate. Bu metrikalar Prometheus-da mövcuddurmu?
2. Error budget hesablayın: 99.9% SLO ilə son ay neçə dəqiqə downtime oldu? Budget nə qədər istifadə edildi?
3. Prometheus-da SLO burn rate alert qaydasını yazın — fast burn (14.4x) və slow burn (1.2x).
4. Grafana-da error budget remaining panel yaradın — real-time budget tracking.
5. Multi-service SLO hesablayın: 3 servis ardıcıl işlədiyində combined SLO nə qədərdir?
6. Error Budget Policy sənədinə yazın: team üçün "budget tükənəndə nə edirik" proseduru.
7. SLA vs SLO buffer niyə lazımdır? Konkret ssenari ilə izah edin.
8. Sloth ilə YAML-based SLO definition yazın, Prometheus rules generate edin.

## Əlaqəli Mövzular

- [04-observability-pillars.md](04-observability-pillars.md) — SLI-lar metrics-dən hesablanır
- [07-incident-response.md](07-incident-response.md) — Incident SLO-ya necə təsir edir
- [06-oncall-best-practices.md](06-oncall-best-practices.md) — On-call SLO pozulmasında devreye girer
- [08-capacity-planning.md](08-capacity-planning.md) — Capacity SLO-nu qorumaq üçün planlanır
