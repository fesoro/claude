# Monitoring and Observability (Lead ⭐⭐⭐⭐)

## İcmal
Observability, sistemin daxili vəziyyətini xarici çıxışlarına (logs, metrics, traces) görə başa düşmə qabiliyyətidir. Monitoring isə bu çıxışları izləmək və anomaliyalar zamanı xəbərdarlıq verməkdir. "Production-ready sistem" demək, bu üç sütun üzərində qurulmuş observability deməkdir. Interview-larda bu mövzu "Sistemi production-a verdiniz, necə sağlamlığını izləyirsiniz?" sualı ilə gəlir.

## Niyə Vacibdir
Netflix, Google, Amazon — hər şirkətin SRE (Site Reliability Engineering) team-i observability-yə milyonlar xərcləyir. "Mean Time to Detection (MTTD)" və "Mean Time to Resolution (MTTR)" SRE-nin ən vacib KPI-larıdır. Lead mühəndis sistemin observable olması üçün nəyin lazım olduğunu, SLI/SLO/SLA fərqini, alerting best practices-i izah edə bilir.

## Əsas Anlayışlar

### 1. Observability-nin 3 Sütunu

**Logs:**
```
Structured log nümunəsi:
{
  "timestamp": "2026-04-26T10:00:00.123Z",
  "level": "ERROR",
  "service": "order-service",
  "trace_id": "abc123",
  "span_id": "def456",
  "user_id": "usr_789",
  "order_id": "ord_123",
  "message": "Payment charge failed",
  "error": "card_declined",
  "duration_ms": 234
}

Unstructured (avoid):
  "ERROR: Payment failed for user 789 order 123"
  → Axtarmaq, parse etmək çətin
```

**Metrics:**
```
Counter: monotonically artan dəyər
  http_requests_total{method="POST", status="200", path="/orders"} = 1234567

Gauge: anlıq dəyər (artıb azala bilər)
  active_connections = 342
  memory_bytes_used = 1073741824

Histogram: dəyərlərin paylanması
  http_request_duration_seconds_bucket{le="0.1"} = 8000
  http_request_duration_seconds_bucket{le="0.5"} = 9500
  http_request_duration_seconds_bucket{le="1.0"} = 9900
  http_request_duration_seconds_sum = 1234.56
  http_request_duration_seconds_count = 10000

Summary: Client-side percentile hesabı
  http_request_duration_quantile{quantile="0.5"} = 0.082
  http_request_duration_quantile{quantile="0.99"} = 0.735
```

**Traces:**
```
Distributed trace: Bir request-in bütün service-lər üzrə yolu

Trace ID: abc123 (bütün request boyu eyni)
  ├── [Order Service] 120ms
  │     span_id: s1, parent: null
  ├── [Inventory Service] 45ms
  │     span_id: s2, parent: s1
  ├── [Payment Service] 230ms
  │     span_id: s3, parent: s1
  │       ├── [Payment Processor API] 180ms
  │       │     span_id: s4, parent: s3
```

### 2. SLI / SLO / SLA

**SLI (Service Level Indicator):**
Ölçülən metrik:
```
Availability SLI: successful_requests / total_requests
Latency SLI: percent of requests < 200ms
Throughput SLI: requests per second
Error rate SLI: error_requests / total_requests
```

**SLO (Service Level Objective):**
Hədəf dəyər (internal commitment):
```
Availability: 99.9% (8.7 hours downtime/year)
Latency: 95% of requests < 200ms, 99% < 500ms
Error rate: < 0.1%
```

**SLA (Service Level Agreement):**
Müştəriyə verilən öhdəlik (contract):
```
"Our API will be available 99.95% uptime"
"Response time < 500ms for 99% of requests"
SLA breach → penalty (credits, refunds)

SLO > SLA: Buffer
SLO: 99.9% (internal target)
SLA: 99.5% (external commitment)
```

**Error Budget:**
```
SLO: 99.9% availability
Error budget: 100% - 99.9% = 0.1% downtime allowed
Monthly: 0.1% × 30 days = 43.2 minutes downtime allowed

Budget used: 20 minutes this month
Budget remaining: 23.2 minutes

If budget depleted:
  → Freeze new features
  → Focus on reliability
  → Incident review mandatory
```

### 3. The Four Golden Signals (Google SRE)

**Latency:** Request-in cavab müddəti
```
Track: p50, p95, p99, p999
Alert: p99 > 500ms
Dashboard: Latency trend over time
```

**Traffic:** Sistemə gelen yük
```
Track: RPS (requests per second)
Alert: Traffic drop > 50% (system problem?)
Dashboard: Daily/weekly traffic patterns
```

**Errors:** Xəta nisbəti
```
Track: Error rate %, error count by type (4xx, 5xx)
Alert: 5xx rate > 1%
Dashboard: Error rate trend
```

**Saturation:** Sistemin doyma nisbəti
```
Track: CPU %, memory %, disk %, queue length, DB connections
Alert: CPU > 80%, memory > 85%
Dashboard: Resource utilization heatmap
```

### 4. RED Method (For Services)
Microservice-lərdə:
- **Rate**: Requests per second
- **Errors**: Error requests per second
- **Duration**: Request latency distribution

```
rate(http_requests_total{status="200"}[5m])     ← Rate
rate(http_requests_total{status=~"5.."}[5m])    ← Errors
histogram_quantile(0.99, rate(http_request_duration_seconds_bucket[5m]))  ← Duration
```

### 5. USE Method (For Resources)
Infrastructure komponentlər üçün:
- **Utilization**: % time resource is busy
- **Saturation**: Queue length, wait time
- **Errors**: Error count

```
CPU: utilization=80%, saturation=5% steal, errors=0
Memory: utilization=70%, saturation=0 swap, errors=0
Disk: utilization=40%, saturation=2ms wait, errors=0
Network: utilization=30%, saturation=0 drops, errors=0
```

### 6. Observability Stack

**Open Source:**
```
Metrics: Prometheus + Grafana
Logs: ELK Stack (Elasticsearch, Logstash, Kibana)
      ya da Grafana Loki + Promtail
Traces: Jaeger ya da Zipkin
Alerting: Alertmanager (Prometheus) ya da Grafana Alerts
```

**Cloud Managed:**
```
AWS:  CloudWatch Metrics + X-Ray (traces) + CloudWatch Logs
GCP:  Cloud Monitoring + Cloud Trace + Cloud Logging
Azure: Azure Monitor + Application Insights

SaaS: Datadog, New Relic, Dynatrace, Honeycomb
```

**OpenTelemetry (Standard):**
```
Vendor-neutral instrumentation SDK
Code: OpenTelemetry SDK → export → any backend
Language support: Go, Java, Python, PHP, Node.js, Ruby, ...

Auto-instrumentation:
  HTTP: Request/response automatically traced
  DB: Query duration, statement traced
  Queue: Message processing traced
  
Manual instrumentation:
  Custom spans for business logic
  Custom metrics for business KPIs
```

### 7. Alerting Best Practices

**Alert on symptoms, not causes:**
```
Bad alert: "CPU > 80%"
  → CPU yüksək amma user impact yoxdur?

Good alert: "Error rate > 1%" 
  → User-lər xəta alır!

Good alert: "p99 latency > 2s"
  → User-lər yavaşlığı hiss edir!
```

**Alert tiers:**
```
P1 (Critical): Immediate page
  - Service completely down
  - Error rate > 5%
  - Data loss/corruption suspected
  
P2 (High): Notify within 15 min
  - Error rate 1-5%
  - p99 latency > 2x normal
  - SLO burn rate too high
  
P3 (Medium): Business hours
  - Elevated but acceptable errors
  - Gradual degradation trend
  - Capacity warning

P4 (Low): Weekly review
  - Informational
  - Trend anomalies
```

**Alert fatigue prevention:**
```
Too many alerts → engineers ignore them → real incident missed

Solution:
  - Multi-window alerting: Alert if bad for 5 min, not 1 spike
  - Burn rate alerts: SLO budget being consumed too fast
  - Deduplication: Same alert multiple times → 1 notification
  - Routing: Right person for right alert
```

### 8. Distributed Tracing Implementation
```
HTTP header propagation:
  Service A → Service B:
  Headers:
    X-Trace-ID: abc123
    X-Span-ID: def456
    X-Parent-Span-ID: xyz789

  Service B creates child span with:
    trace_id: abc123 (same)
    span_id: new_id
    parent_span_id: def456 (Service A's span)

OpenTelemetry context propagation:
  Automatic in most frameworks
  PHP: opentelemetry-auto-laravel package
  Java: Spring Boot auto-instrumentation (Java agent)
  Go: OTEL SDK
```

### 9. Log Levels and Structured Logging
```
TRACE: Very detailed debugging (not in production)
DEBUG: Detailed debugging (enable per-request in prod)
INFO:  Normal operations (request start/end, business events)
WARN:  Unexpected but non-fatal (high latency, retry)
ERROR: Errors that need investigation
FATAL: System cannot continue

Structured logging (PHP/Laravel):
  Log::info('Order placed', [
      'order_id' => $order->id,
      'user_id' => $user->id,
      'amount' => $order->total,
      'trace_id' => request()->header('X-Trace-ID'),
  ]);

Never log:
  Passwords, credit card numbers, SSN (PII)
  API keys, tokens
  Full request body (may contain sensitive data)
```

### 10. Health Check Endpoint Design
```
/health/live   → Is process running? (Kubernetes liveness)
  200: {"status": "alive"}
  500: {"status": "dead"} → Kubernetes restarts pod

/health/ready  → Can serve traffic? (Kubernetes readiness)
  200: {"status": "ready", "db": "ok", "redis": "ok"}
  503: {"status": "not_ready", "db": "disconnected"}
  → Kubernetes removes from load balancer

/health/detail → Full health info (not for LB)
  {
    "status": "healthy",
    "checks": {
      "database": {"status": "ok", "latency_ms": 2},
      "redis": {"status": "ok", "latency_ms": 0.5},
      "kafka": {"status": "ok", "lag": 100},
      "disk_space": {"status": "ok", "free_gb": 45}
    },
    "version": "1.2.3",
    "uptime_seconds": 86400
  }
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Bu sistemi production-a verdikdən sonra necə monitorinq edərsiniz?" sualını özünüz soruşun
2. 3 pillar (logs, metrics, traces) hamısını qeyd et
3. SLI/SLO/SLA fərqini izah et
4. 4 Golden Signals ilə nəyi izləyəcəyini göstər
5. Alert strategy müzakirə et (alert fatigue qarşısını almaq)

### Ümumi Namizəd Səhvləri
- Monitoring = alert sistemi düşünmək (observability daha geniş)
- Distributed tracing-i bilməmək
- SLO/SLA fərqini bilməmək
- Error budget konseptini qeyd etməmək
- "Logs əlavə etdik, hazırdır" demək (structured logging, correlation ID olmadan)

### Senior vs Architect Fərği
**Senior**: 4 Golden Signals qurur, SLO müəyyən edir, alert rules yazır, distributed tracing tətbiq edir.

**Architect**: Observability platform seçimi (build vs buy), error budget policy (feature freeze when depleted), observability-as-code (Grafana dashboards as Terraform), multi-tenant observability (shared platform), observability data retention cost optimization, SLO review process (quarterly SLO revision), chaos engineering ilə SLO validation.

## Nümunələr

### Tipik Interview Sualı
"You've just deployed a payment service. How do you ensure it's working correctly and detect issues before users report them?"

### Güclü Cavab
```
Payment service observability:

SLI/SLO definition:
  SLI 1: Availability = successful_payments / total_attempts
  SLO:   99.95% (26 min downtime/month budget)

  SLI 2: Latency = P99 of payment processing time
  SLO:   P99 < 3 seconds

  SLI 3: Success rate = successful / (successful + card_declined + errors)
  SLO:   > 97% (3% card declines expected from users)

4 Golden Signals:

1. Latency:
   Prometheus: payment_duration_seconds histogram
   Alert: P99 > 3s for 5 min → P2 alert
   Alert: P99 > 10s → P1 page

2. Traffic:
   Prometheus: payment_attempts_total counter
   Alert: Traffic drops > 30% from 5-min avg → P1 (system issue)
   Dashboard: Hourly/daily pattern (baseline)

3. Errors:
   Prometheus: payment_errors_total{type="processor_error"}
   Alert: processor_error rate > 2% → P1 immediate
   Alert: card_decline rate > 20% → P2 (unusual fraud pattern?)

4. Saturation:
   Prometheus: DB connection pool utilization
   Alert: Connection pool > 80% → P2

Distributed tracing:
   All payment requests: OpenTelemetry spans
   Trace: API Gateway → Payment Service → Processor API
   Slow trace alert: P99 > 2s → Jaeger query to find bottleneck

Logs:
   All payment attempts: structured log
   {payment_id, user_id, amount, processor, duration_ms, result, trace_id}
   ERROR: processor error → Elasticsearch index + Slack notification
   PII: card number NEVER logged (only last 4 digits)

Dashboard (Grafana):
   - Payment success rate (large number, green/red)
   - P50/P95/P99 latency timeline
   - Error breakdown by type
   - Processor API latency trend
   - Active payments in-flight

Alerting:
   P1 (page on-call): success_rate < 95% or processor_error > 5%
   P2 (Slack alert): latency P99 > 3s or success_rate < 97%
   P3 (dashboard only): any metric outside 3-sigma normal range

Runbook:
   Each alert has runbook link:
   "Payment Processor Error": Check processor status page, check logs
```

### Dashboard Queries (Prometheus)
```promql
# Payment success rate
sum(rate(payment_attempts_total{status="success"}[5m]))
/
sum(rate(payment_attempts_total[5m]))

# P99 latency
histogram_quantile(0.99, 
  rate(payment_duration_seconds_bucket[5m]))

# Error rate by type
rate(payment_errors_total[5m]) by (error_type)
```

## Praktik Tapşırıqlar
- Prometheus + Grafana + Laravel-app stack qurun
- SLO dashboard: availability + latency percentiles
- Alert rule: Error rate spike detect
- OpenTelemetry PHP instrumentation qurun
- Chaos experiment: DB latency inject → trace ilə bottleneck tap

## Əlaqəli Mövzular
- [01-system-design-approach.md](01-system-design-approach.md) — Production readiness
- [16-circuit-breaker.md](16-circuit-breaker.md) — CB metrics monitoring
- [14-api-gateway.md](14-api-gateway.md) — Gateway-level metrics
- [08-message-queues.md](08-message-queues.md) — Consumer lag monitoring
- [03-scalability-fundamentals.md](03-scalability-fundamentals.md) — Capacity planning metrics
