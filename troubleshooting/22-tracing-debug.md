# Tracing Debug

## Problem (nə görürsən)
Bir request yavaş və ya fail olur. Log-lar sənə hər servisdə nə baş verdiyini deyir, amma səbəb zəncirini asanca göstərmir. Metriklər aqreqat davranışı göstərir, amma ayrı-ayrı request-ləri yox. Sənə distributed tracing lazımdır: bir request-in bir çox servis boyunca axışının görünüşü, zamanın harada getdiyini dəqiq göstərir.

Tracing-in doğru alət olduğu simptomlar:
- Yavaş endpoint, amma hansı downstream call yavaş olduğunu bilmirsən
- grep-in tapmadığı fasiləli səhvlər
- Zəncirvari uğursuzluqlar — hansı servis uğursuzluğa başladı?
- Quyruq latency ovu (p99 spike-ləri)

## Sürətli triage (ilk 5 dəqiqə)

### Tracing-in var?

Ümumi sistemlər:
- **Jaeger** — open source, CNCF
- **Zipkin** — köhnə, sadə
- **Datadog APM** — kommersiya, mükəmməl UX
- **New Relic** — kommersiya
- **Honeycomb** — hadisə əsaslı, debug üçün çox güclüdür
- **AWS X-Ray** — AWS native
- **Tempo** — Grafana Labs, Loki + Prometheus ilə uyğun gəlir

Əgər varsa, tracing UI-ə get, yavaş endpoint üzrə filter et, duration desc üzrə sort et, yavaş trace seç.

### Trace-i tez oxumaq

İstənilən tracing UI-də trace Gantt chart kimi görünür:

```
[─── GET /orders/123 (1200ms) ───────────────────────────]
  [── auth.verify (50ms) ──]
                           [─ db.SELECT orders (80ms) ─]
                                                       [──── payments.charge (950ms) ────]
                                                                                         [─ notify (30ms) ─]
```

Yavaş span aydındır: ümumi 1200ms-nin 950ms-i `payments.charge`.

## Diaqnoz

### Trace strukturu

- **Trace** = servislər arasında bir məntiqi əməliyyat (bir request)
- **Span** = trace içində bir əməliyyat (bir function çağırışı, bir DB query, bir HTTP çağırışı)
- **Parent span** = çağıran
- **Child span-lar** = çağıranın çağırdıqları
- **Span attribute-lar** = metadata (http.method, db.statement, user_id, və s.)

### Yavaş span-ı tapmaq

Strategiya:
1. Root span-dan başla (gələn request)
2. Ağacda aşağı get, həmişə ən uzun müddətli child-a get
3. Child-larının zamanının çoxunu təşkil etmədiyi span-a çatanda dayan — həmin span-ın "self time"-i isti nöqtədir

```
root 1200ms
├─ auth 50ms (self time 50, fast)
├─ select 80ms (self time 80, fast)
└─ payments 950ms ← go here
   ├─ http call 900ms ← go here
   │  └─ (no children) self time 900ms ← THE PROBLEM
   └─ json parse 50ms
```

Bu halda, payments servisinə HTTP çağırışı 900ms çəkdi və breakdown yoxdur — daha dərin getmək üçün payments servis tərəfində tracing lazımdır.

### Uğursuz span-ı tapmaq

Əksər tracing UI-lar səhv verən span-ları qırmızı rənglə göstərir. Əvvəlcə qırmızı span-lara bax. Əgər root qırmızıdırsa child qırmızı olduğu üçün, zənciri aşağı izlə.

### Trace-ləri log-larla korrelyasiya etmək

Hər log sətri `trace_id` və `span_id` daxil etməlidir:

```json
{"ts":"...","level":"ERROR","trace_id":"abc123","span_id":"def456","msg":"Connection refused"}
```

Trace UI-dən trace_id-ni kopyala, log axtarışına yapışdır və bu bir request üçün tam log hekayəsinə sahibsən.

## Fix (qanaxmanı dayandır)

Tracing düzəltmir — lokalizasiya edir. Lokalizasiyadan sonra:
- Yavaş DB query → index əlavə et, query-ni yenidən yaz
- Yavaş xarici API → cache, circuit-break, aşağı timeout
- Yavaş daxili servis → həmin komandanı trace ilə page et
- Retry storm → retry siyasətini tənzimlə
- Connection pool tükənib → pool ölçüsünü artır və ya connection hold time-ı azalt

## Əsas səbəbin analizi

Incident sonrası trace-lər RCA üçün qızıldır:
- Uğursuzluğun 5-10 təmsilçi trace-ni saxla
- Müqayisə üçün incident-dən əvvəlki 5-10 trace saxla
- Onları post-mortem-ə əlavə et

Trace-lərin cavab verdiyi suallar:
- Yavaş asılılıq bütün istifadəçilər üçün yavaş idi, yoxsa yalnız bəziləri üçün?
- Hansı span attribute-ları uğursuzluqlarla korrelyasiya edir (user_id, region, feature flag)?
- Latency-in nə qədəri network, nə qədəri compute idi?

## Qarşısının alınması

- Hər network çağırışını span ilə instrumentasiya et
- Kontekst-relevant atributlar əlavə et (user_id, tenant_id, feature_flag)
- Ağıllı sample et — səhvlərin 100%-i, uğurların 10-50%-i
- Trace kontekstini async sərhədləri arasında yay (queue-lar, webhook-lar)
- Trace-lərlə yanaşı dashboard-lar: servis başına p99 latency, error rate
- SLO əsaslı alerting trace-dən çıxarılmış metriklər istifadə edir

## PHP/Laravel üçün qeydlər

### Laravel üçün OpenTelemetry

Paket: `open-telemetry/opentelemetry-auto-laravel`

```bash
composer require open-telemetry/opentelemetry-auto-laravel
composer require open-telemetry/exporter-otlp
```

Environment:
```ini
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-api
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_TRACES_SAMPLER=parentbased_traceidratio
OTEL_TRACES_SAMPLER_ARG=0.1
```

Avtomatik instrumentasiya edir:
- HTTP request-lər (gələn və `Http` facade vasitəsilə gedən)
- Database query-lər (Eloquent/PDO)
- Queue job-lar
- Cache əməliyyatları

### Laravel-də manual span-lar

```php
use OpenTelemetry\API\Globals;

$tracer = Globals::tracerProvider()->getTracer('my-app');
$span = $tracer->spanBuilder('order.process')
    ->setAttribute('order.id', $order->id)
    ->startSpan();

try {
    // work
    $span->setStatus(StatusCode::STATUS_OK);
} catch (\Throwable $e) {
    $span->recordException($e);
    $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
    throw $e;
} finally {
    $span->end();
}
```

### Queue-lar arasında trace kontekstini yaymaq

Job dispatch edərkən:
```php
// Capture current trace context
$carrier = [];
TraceContextPropagator::getInstance()->inject($carrier);

SendEmail::dispatch($user, $carrier);

// In the job
public function handle()
{
    $context = TraceContextPropagator::getInstance()->extract($this->carrier);
    $scope = $context->activate();
    try {
        // work, traces will be under the original trace
    } finally {
        $scope->detach();
    }
}
```

### Datadog APM alternativi

```bash
composer require datadog/dd-trace
```

`DD_TRACE_ENABLED=true` env təyin et. Datadog Laravel/Lumen-i qutudan auto-instrumentasiya edir.

## Yadda saxlanacaq komandalar

```bash
# Start the OTel collector locally for testing
docker run -p 4318:4318 otel/opentelemetry-collector

# Test trace export
curl -X POST http://localhost:4318/v1/traces \
  -H "Content-Type: application/json" \
  -d @trace.json

# Jaeger all-in-one for local dev
docker run -d -p 16686:16686 -p 4318:4318 jaegertracing/all-in-one

# Open Jaeger UI
open http://localhost:16686

# Datadog APM sanity
curl http://localhost:8126/info
```

### Grafana Tempo query

```
{.service.name="api"} | duration > 1s
```

### Honeycomb query

```
WHERE service_name = "api" AND duration_ms > 1000
```

## Interview sualı

"Distributed tracing log-lara nisbətən necə kömək edir?"

Güclü cavab:
- "Log-lar mənə hər servisdə nə baş verdiyini deyir, tracing bir request-in hamısı boyunca necə axdığını deyir."
- "Latency araşdırması üçün tracing üstsüzdür: hansı span vaxt aldığını dəqiq görə bilirəm."
- "Trace ID-lər log və trace-ləri birləşdirir — yavaş trace-dən həmin request-dən olan hər log sətrinə sıçraya bilirəm."
- "OpenTelemetry ilə instrumentasiya edirəm, çünki vendor-neytraldır; dev-də Jaeger-ə, prod-da Datadog-a export edə bilirəm."
- "Trace kontekstini queue-lar arasında həmişə yayıram, ona görə background job-lar onları triggerləyən HTTP request-i ilə eyni trace-in bir hissəsidir."

Bonus: "Bir halda log-larda görünməyən 2s p99 quyruğunu tapdım — bir Redis call DNS misconfig-ə görə uzaq regiona gedirdi. Tracing 5 dəqiqədə onu aydın etdi."
