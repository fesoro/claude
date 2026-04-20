# Observability & SRE — Interview Suallar

## Mündəricat
1. Logging
2. Metrics
3. Tracing
4. SLI/SLO/SLA
5. Incident response
6. Sual-cavab seti

---

## 1. Logging

**S: Structured logging niyə vacibdir?**
C: JSON format → log aggregator (ELK, Loki) parse edə bilir. Field-ə görə filter, qruplaşdırma. Plain text grep ilə deyil.

**S: PHP-də Monolog necə istifadə olunur?**
C: PSR-3 logger. Çoxlu handler (file, Slack, Sentry, ES). Processor (request_id, user_id auto-əlavə).

**S: Log level-lər nələrdir?**
C: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY (PSR-3).

**S: Production-da hansı level?**
C: WARNING və yuxarı. INFO bəzi hallarda (sampled). DEBUG yalnız troubleshoot zamanı.

**S: Sensitive data (password, token) log-da necə qarşısı alınır?**
C: Log processor (mask), `monolog/redact` package, denylist regex. PHPUnit-də log integration test.

```php
$log->info('User registered', [
    'user_id'    => $user->id,
    'ip'         => $request->ip(),
    'request_id' => request()->header('X-Request-ID'),
    // 'password' YOX!
]);
```

---

## 2. Metrics

**S: Counter, Gauge, Histogram fərqi?**
C:
- Counter: yalnız artır (request count)
- Gauge: artar/azalar (memory usage, queue size)
- Histogram: distribution (latency p50/p95/p99)

**S: Prometheus PHP-də necə inteqrasiya olunur?**
C: `promphp/prometheus_client_php`. Redis storage. `/metrics` endpoint Prometheus scrape üçün.

**S: USE method (Brendan Gregg) nədir?**
C: Utilization, Saturation, Errors. Resource-level monitoring (CPU, memory, disk).

**S: RED method (Tom Wilkie) nədir?**
C: Rate, Errors, Duration. Service-level (request rate, error %, latency).

**S: Cardinality nədir, niyə diqqət lazımdır?**
C: Hər unique label kombinasiyası ayrı time series. `user_id` label → milyonlarla series → Prometheus crash. ID-ləri label etmə.

---

## 3. Tracing

**S: Distributed tracing nə üçündür?**
C: Bir request birdən çox servisdən keçir. Tracing harada vaxt keçir (DB, external API) göstərir.

**S: OpenTelemetry nədir?**
C: CNCF observability standartı. Vendor-neutral (Jaeger, Tempo, Datadog dəstəkləyir). PHP SDK var.

**S: Span və trace fərqi?**
C: Trace = bir request-in lifecycle. Span = operation (DB query, HTTP call). Span parent-child ağacı.

**S: Trace context propagation necə işləyir?**
C: HTTP header (`traceparent`, W3C standartı). Service A → B çağırışında header ötürülür, span ID parent kimi.

**S: Sampling niyə vacibdir?**
C: Bütün trace-lər saxlanmırsa storage cost. Sample rate (1%, 10%) — yalnız əhəmiyyətli (slow, error) trace.

---

## 4. SLI/SLO/SLA

**S: SLI, SLO, SLA fərqi?**
C: 
- SLI = ölçü (availability rate, P99 latency)
- SLO = daxili hədəf (99.9%)
- SLA = müştəri vədi + cərimə (99.5%)

**S: Error budget nədir?**
C: 1 - SLO. 99.9% SLO → 43 dəq/ay icazə verilən downtime. Budget xərclənəndə feature freeze.

**S: 99.9% və 99.99% arasında praktik fərq?**
C: Aylıq 43 dəqiqə vs 4 dəqiqə downtime. Hər nine 10× xərc artırır.

**S: Multi-window burn rate alert nədir?**
C: Qısa (5m) və uzun (1h) pəncərələrin BƏRƏBƏRİ aşma → real problem. Tək pəncərə false positive verir.

**S: Latency SLI niyə "average" olmamalıdır?**
C: Average outlier-i gizlədir. Threshold-based: "% requests < 500ms".

---

## 5. Incident response

**S: Postmortem nədir, blameless niyə vacibdir?**
C: Incident sonra yazılan sənəd: səbəb, timeline, action item. Blameless — fərd yox, sistem hatası fokusu.

**S: MTTR nə ölçür?**
C: Mean Time To Recover. Incident başlayır → bərpa olunur. Düşmək vaxt = improvement metric.

**S: On-call rotation necə qurulur?**
C: 1 hafta primary + 1 hafta secondary. PagerDuty/Opsgenie alert routing. Burnout qarşı: 1 hafta sonrası 1 hafta dincəlmə.

**S: Runbook nədir?**
C: Spesifik incident üçün step-by-step həll. Səhər 3-də sleep-də adam oxuya bilməlidir. Misal: "DB CPU spike" → 5 step.

**S: Change freeze nə vaxt deklarasiya olunur?**
C: Black Friday, Big game day, deploy bug-dan sonra error budget tükəndikdə.

---

## 6. Sual-cavab seti (Observability fokus)

**S: Three pillars of observability?**
C: Logs, Metrics, Traces. + bəziləri "Events" (4-cü) əlavə edir.

**S: Log aggregation üçün Elastic vs Loki?**
C: Elastic — full-text indexing (heavy, expensive). Loki — label-only indexing (Grafana ekosistem, cheap).

**S: PHP-də correlation ID necə implementasiya olunur?**
C: Middleware-də generate (X-Request-ID header). Logger context-ə əlavə. Downstream service-ə HTTP header kimi ötür.

**S: Prometheus scrape interval nə qədər olmalıdır?**
C: Tipik 15-60 saniyə. Çox kiçik → load. Çox böyük → sürətli incident görünmür.

**S: Grafana alert manager-də alert silence necə?**
C: Müvəqqəti silence (deploy zamanı). Time-based, label-based filter.

**S: Cardinality explosion-a real misal?**
C: Label-də user ID istifadə → 1M user × 10 metric × 10 endpoint = 100M time series. Storage və query yavaşlayır.

**S: Trace context "baggage" nədir?**
C: Trace boyunca daşınan key-value pair (user_id, session_id). Bütün span-larda görünür.

**S: APM (Datadog, New Relic) ilə open-source stack fərqi?**
C: APM — turnkey, paid. OSS (Prometheus + Grafana + Tempo) — self-hosted, daha çox iş.

**S: USE və RED birgə nə vaxt istifadə olunur?**
C: USE — infrastructure (server, DB). RED — application (HTTP service). İkisi tamamlayıcı.

**S: SLO 100% niyə pis?**
C: Heç vaxt çatmaq olmur. Cost qeyri-mümkün. User onsuz da fərqində olmur (onların öz network-ü 99% qalmır).

**S: Postmortem-də 5 Whys texnikası nədir?**
C: Hər "niyə"-yə cavab → yenə "niyə". 5 dəfə təkrar → root cause çıxır (səthi səbəbdən deyil).

**S: Chaos engineering observability ilə necə əlaqəli?**
C: Chaos test → metric/alert işləyirmi? Monitoring blind spot tapmaq üçün ideal.

**S: Synthetic monitoring nədir?**
C: Real istifadəçidən asılı olmadan endpoint-i ping et (hər N dəqiqə). Pingdom, Datadog Synthetics.

**S: Real User Monitoring (RUM) nədir?**
C: Client-side JavaScript metric (page load, JS error). Backend metric-dən fərqli.

**S: Sentry PHP-də nə üçün lazımdır?**
C: Exception aggregation. Eyni error qruplaşdırılır, frequency, user count, stack trace.

**S: Log retention policy nə qədər olmalıdır?**
C: Compliance + cost balance. Tipik: 30 gün hot, 90 gün warm, 1 il cold. GDPR — silmək imkanı.

**S: Heartbeat metric nədir?**
C: Servis "diridir" siqnalı (hər 30s ping). Alert: heartbeat 2 dəqiqə yoxdursa incident.

**S: Toil nədir SRE kontekstdə?**
C: Manual repetitive operational work (no value-add). Automate edilməlidir. Toil > 50% → eng team scale problem.

**S: Error budget bitəndə nə edilir?**
C: Feature freeze. Bütün engineering effort reliability-yə. Postmortem deeper. Process review.

**S: P50, P95, P99 latency niyə fərqli?**
C: P50 average, "yaxşı user experience". P99 worst-case (1%) — slow user-lər (bot, geo-distant). P99 → SLO.
