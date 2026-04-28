# Service Mesh (Lead)

## Nədir? (What is it?)

Service mesh – microservice-lər arasındakı şəbəkə kommunikasiyasını idarə edən infrastruktur layer-idir. Hər service-in yanında sidecar proxy (Envoy) qoyulur və bütün şəbəkə trafiki bu proxy üzərindən keçir. Bu, developer-lərin kodda implement etməli olduğu əsas concern-ləri (load balancing, retry, circuit breaking, mTLS, observability) infrastructure layer-ə köçürür. Əsas service mesh məhsulları: Istio, Linkerd, Consul Connect, AWS App Mesh.

## Əsas Konseptlər (Key Concepts)

### Service Mesh Arxitekturası

```
┌──────────────────────────────────────┐
│          Control Plane               │
│  (Istio Pilot, Citadel, Galley)     │
│  - Configuration                     │
│  - Certificate management            │
│  - Policy                            │
└──────────────────────────────────────┘
              │ configure
              ▼
┌─────────────────┐    ┌─────────────────┐
│   Pod A         │    │   Pod B         │
│  ┌──────┐┌────┐ │    │  ┌──────┐┌────┐ │
│  │App A ││Envoy│◄─mTLS►│Envoy ││App B │ │
│  └──────┘└────┘ │    │  └────┘ └──────┘ │
└─────────────────┘    └─────────────────┘
        Data Plane (Envoy proxy-lər)
```

### Sidecar Pattern

```yaml
# Hər Pod-a Envoy sidecar avtomatik enjekt olunur
# istio-injection=enabled label ilə namespace-ə

apiVersion: v1
kind: Namespace
metadata:
  name: production
  labels:
    istio-injection: enabled

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
      - name: laravel
        image: laravel:1.0
        ports:
        - containerPort: 80
      # Istio avtomatik sidecar əlavə edir:
      # - name: istio-proxy (Envoy)
      #   image: docker.io/istio/proxyv2:1.20.0
```

### Istio Core Components

```bash
# 1. Istiod (Control plane)
# - Pilot: service discovery, config push
# - Citadel: certificate authority (mTLS)
# - Galley: configuration validation

# 2. Envoy Proxy (Data plane)
# - Sidecar in each pod
# - Handles all inbound/outbound traffic
# - L7 proxy (HTTP, gRPC)

# 3. Ingress/Egress Gateway
# - North-South traffic (external)
# - Separate pod, not sidecar

# Istio install
istioctl install --set profile=default
kubectl label namespace default istio-injection=enabled

# Istioctl komandaları
istioctl proxy-config clusters <pod>         # Envoy cluster config
istioctl proxy-config routes <pod>           # Routing rules
istioctl proxy-config listeners <pod>        # Listeners
istioctl analyze                             # Config validation
istioctl dashboard kiali                     # Service graph
istioctl dashboard jaeger                    # Distributed tracing
```

### Traffic Management

```yaml
# VirtualService - Routing rules
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: laravel-api
spec:
  hosts:
  - api.example.com
  gateways:
  - laravel-gateway
  http:
  # Canary: 10% traffic to v2
  - match:
    - headers:
        x-version:
          exact: v2
    route:
    - destination:
        host: laravel-api
        subset: v2
  - route:
    - destination:
        host: laravel-api
        subset: v1
      weight: 90
    - destination:
        host: laravel-api
        subset: v2
      weight: 10

---
# DestinationRule - Version subsets, traffic policy
apiVersion: networking.istio.io/v1beta1
kind: DestinationRule
metadata:
  name: laravel-api
spec:
  host: laravel-api
  subsets:
  - name: v1
    labels:
      version: v1
  - name: v2
    labels:
      version: v2
  trafficPolicy:
    connectionPool:
      tcp:
        maxConnections: 100
      http:
        http2MaxRequests: 1000
        maxRequestsPerConnection: 10
    loadBalancer:
      simple: LEAST_REQUEST        # ROUND_ROBIN, RANDOM, PASSTHROUGH
    outlierDetection:              # Circuit breaker
      consecutive5xxErrors: 5
      interval: 30s
      baseEjectionTime: 30s
      maxEjectionPercent: 50

---
# Gateway - Ingress routing
apiVersion: networking.istio.io/v1beta1
kind: Gateway
metadata:
  name: laravel-gateway
spec:
  selector:
    istio: ingressgateway
  servers:
  - port:
      number: 443
      name: https
      protocol: HTTPS
    hosts:
    - api.example.com
    tls:
      mode: SIMPLE
      credentialName: laravel-tls
```

### Retry, Timeout, Circuit Breaking

```yaml
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: laravel-retry
spec:
  hosts:
  - laravel-api
  http:
  - route:
    - destination:
        host: laravel-api
    # Timeout
    timeout: 10s
    # Retry
    retries:
      attempts: 3
      perTryTimeout: 3s
      retryOn: 5xx,reset,connect-failure,refused-stream
    # Fault injection (chaos testing)
    fault:
      delay:
        percentage:
          value: 10
        fixedDelay: 5s
      abort:
        percentage:
          value: 1
        httpStatus: 500
```

### mTLS (Mutual TLS)

```yaml
# Strict mTLS - bütün service trafiği encrypted
apiVersion: security.istio.io/v1beta1
kind: PeerAuthentication
metadata:
  name: default
  namespace: production
spec:
  mtls:
    mode: STRICT          # DISABLE, PERMISSIVE, STRICT

---
# Authorization Policy
apiVersion: security.istio.io/v1beta1
kind: AuthorizationPolicy
metadata:
  name: laravel-authz
  namespace: production
spec:
  selector:
    matchLabels:
      app: laravel-api
  action: ALLOW
  rules:
  - from:
    - source:
        principals: ["cluster.local/ns/production/sa/frontend-sa"]
    to:
    - operation:
        methods: ["GET", "POST"]
        paths: ["/api/*"]
  - from:
    - source:
        principals: ["cluster.local/ns/production/sa/worker-sa"]
    to:
    - operation:
        methods: ["POST"]
        paths: ["/internal/jobs"]
```

### Observability

```bash
# Istio metrics (Prometheus)
# istio_requests_total - request sayı
# istio_request_duration_milliseconds - latency
# istio_request_bytes - request size
# istio_response_bytes - response size

# PromQL examples
# Error rate
sum(rate(istio_requests_total{response_code=~"5.."}[5m])) by (destination_service)
/
sum(rate(istio_requests_total[5m])) by (destination_service)

# P99 latency
histogram_quantile(0.99,
  sum(rate(istio_request_duration_milliseconds_bucket[5m])) by (destination_service, le)
)

# Jaeger (distributed tracing)
# Automatic trace propagation via B3 headers
istioctl dashboard jaeger

# Kiali (service graph)
istioctl dashboard kiali

# Grafana
istioctl dashboard grafana
```

### Envoy Filters (Advanced)

```yaml
apiVersion: networking.istio.io/v1alpha3
kind: EnvoyFilter
metadata:
  name: add-header
spec:
  workloadSelector:
    labels:
      app: laravel-api
  configPatches:
  - applyTo: HTTP_FILTER
    match:
      context: SIDECAR_INBOUND
    patch:
      operation: INSERT_BEFORE
      value:
        name: envoy.filters.http.lua
        typed_config:
          "@type": type.googleapis.com/envoy.extensions.filters.http.lua.v3.Lua
          inlineCode: |
            function envoy_on_response(response_handle)
              response_handle:headers():add("X-Custom-Header", "my-value")
            end

---
# Rate limiting (local)
apiVersion: networking.istio.io/v1alpha3
kind: EnvoyFilter
metadata:
  name: local-rate-limit
spec:
  configPatches:
  - applyTo: HTTP_FILTER
    match:
      listener:
        filterChain:
          filter:
            name: "envoy.filters.network.http_connection_manager"
    patch:
      operation: INSERT_BEFORE
      value:
        name: envoy.filters.http.local_ratelimit
        typed_config:
          "@type": type.googleapis.com/envoy.extensions.filters.http.local_ratelimit.v3.LocalRateLimit
          stat_prefix: http_local_rate_limiter
          token_bucket:
            max_tokens: 100
            tokens_per_fill: 100
            fill_interval: 60s
```

## Praktiki Nümunələr (Practical Examples)

### Canary deployment Istio ilə

```yaml
# 1. Deploy v2 (yeni version)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-api-v2
spec:
  replicas: 1
  selector:
    matchLabels:
      app: laravel-api
      version: v2
  template:
    metadata:
      labels:
        app: laravel-api
        version: v2
    spec:
      containers:
      - name: laravel
        image: laravel:2.0

---
# 2. VirtualService - 5% trafik v2-yə
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: laravel-api
spec:
  hosts:
  - laravel-api
  http:
  - route:
    - destination:
        host: laravel-api
        subset: v1
      weight: 95
    - destination:
        host: laravel-api
        subset: v2
      weight: 5

# 3. Monitor metrics (Grafana)
# Error rate, latency müqayisə et

# 4. Tədricən artır: 5% → 25% → 50% → 100%
# 5. Problem olsa weight=100 v1-ə qaytar
```

### A/B Testing header əsaslı

```yaml
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: laravel-ab-test
spec:
  hosts:
  - laravel-api
  http:
  # Beta user-lər yeni versiya
  - match:
    - headers:
        x-user-group:
          exact: beta
    route:
    - destination:
        host: laravel-api
        subset: v2
  # Premium user-lər yeni versiya
  - match:
    - headers:
        authorization:
          regex: ".*premium.*"
    route:
    - destination:
        host: laravel-api
        subset: v2
  # Qalan bütün trafik v1
  - route:
    - destination:
        host: laravel-api
        subset: v1
```

## PHP/Laravel ilə İstifadə

### Laravel + Istio sidecar

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-api
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel-api
      version: v1
  template:
    metadata:
      labels:
        app: laravel-api
        version: v1
      annotations:
        sidecar.istio.io/inject: "true"
        sidecar.istio.io/proxyCPU: "100m"
        sidecar.istio.io/proxyMemory: "128Mi"
    spec:
      serviceAccountName: laravel-sa
      containers:
      - name: laravel
        image: laravel:1.0
        ports:
        - containerPort: 80
          name: http
        env:
        - name: OTEL_EXPORTER_OTLP_ENDPOINT
          value: http://jaeger-collector.istio-system:4318
        resources:
          requests:
            memory: "256Mi"
            cpu: "200m"
```

### Laravel distributed tracing

```php
// composer require open-telemetry/api open-telemetry/sdk

// config/tracing.php
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\Contrib\Otlp\SpanExporter;

// app/Providers/AppServiceProvider.php
public function boot(): void
{
    $exporter = new SpanExporter(
        transport: (new OtlpHttpTransportFactory())->create(
            env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://jaeger:4318'),
            'application/x-protobuf'
        )
    );
    
    $tracerProvider = TracerProvider::builder()
        ->addSpanProcessor(new BatchSpanProcessor($exporter))
        ->setResource(ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => 'laravel-api',
                ResourceAttributes::SERVICE_VERSION => '1.0.0',
            ]))
        ))
        ->build();
    
    Globals::registerInitializer(fn() => $tracerProvider);
}
```

```php
// Custom span yaratmaq
use OpenTelemetry\API\Globals;

$tracer = Globals::tracerProvider()->getTracer('laravel-api');

public function processOrder(int $orderId)
{
    $span = $tracer->spanBuilder('process-order')
        ->setAttribute('order.id', $orderId)
        ->startSpan();
    
    try {
        $scope = $span->activate();
        
        // Work...
        $order = Order::find($orderId);
        
        $span->setAttribute('order.total', $order->total);
        $span->setStatus(StatusCode::STATUS_OK);
    } catch (\Exception $e) {
        $span->recordException($e);
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        throw $e;
    } finally {
        $span->end();
        $scope?->detach();
    }
}
```

### Laravel service-to-service mTLS ilə

```php
// Istio mTLS aktivdirsə, Laravel app heç bir dəyişiklik etmir
// Envoy sidecar avtomatik encrypt edir

// Service call-ı:
$response = Http::get('http://users-api/api/users/' . $id);
// Envoy avtomatik mTLS aplikasiyası edir

// Service identity SPIFFE format:
// spiffe://cluster.local/ns/production/sa/laravel-sa

// AuthorizationPolicy ilə yalnız lazım olan service-lər laravel-api-yə çıxa bilər
```

## Interview Sualları (5-10 Q&A)

**S1: Service mesh nədir və niyə lazımdır?**
C: Service mesh – microservice-lər arası şəbəkə kommunikasiyasını infrastructure layer-də idarə edən sistem. Developer-lər load balancing, retry, circuit breaker, TLS, metrics kodda implement etmir – sidecar proxy (Envoy) bunu edir. Polyglot environment-də (hər servisi fərqli dildə) çox faydalıdır. Laravel, Node.js, Go servisləri eyni reliability pattern-ləri alır.

**S2: Sidecar pattern necə işləyir?**
C: Hər pod-a ana container yanında kiçik proxy container (Envoy) enjekt olunur. Pod trafiki iptables rules ilə Envoy-ə redirect olur. Envoy outbound/inbound hamısını görür: L7 routing, encryption, metrics, retry. Application kod dəyişməz – tamamilə şəffafdır. Qazanc: reliability, observability; itki: +50-100ms latency, CPU/memory overhead.

**S3: Istio Control Plane və Data Plane fərqi?**
C: Control Plane (Istiod) – konfiqurasiya idarə edir, mTLS sertifikatları verir, policy-ləri paylaşdırır (VirtualService, DestinationRule). Data Plane (Envoy proxy-lər) – real trafiği işləyir, routing, security, metrics edir. Control plane down olsa, mövcud konfiqurasiya ilə data plane işləməyə davam edir (graceful degradation).

**S4: Istio-da mTLS necə işləyir?**
C: Istio Citadel (Control Plane) hər pod üçün sertifikat verir (SPIFFE identity). Envoy proxy-lər bir-biri ilə mTLS (mutual TLS) ilə əlaqə qurur – həm client, həm server sertifikatlarını doğrulayır. Application kod dəyişməz. Mode-lar: DISABLE, PERMISSIVE (plaintext və mTLS), STRICT (yalnız mTLS). Defence in depth üçün vacib.

**S5: Circuit breaker niyə lazımdır və Istio-da necə qurulur?**
C: Circuit breaker – failing service-ə davamlı request göndərməyi dayandırır, cascading failure-un qarşısını alır. `DestinationRule`-da `outlierDetection`: məs. 5 ardıcıl 5xx-dən sonra 30 saniyə trafik göndərilməz. Pod heal olandan sonra tədricən qaytarılır. Retry ilə birlikdə istifadə olunur.

**S6: Canary deployment service mesh olmadan olurmu?**
C: Olur, amma service mesh çox sadələşdirir. Service mesh olmadan: (1) İki Kubernetes service; (2) Ingress weight ilə (məhdud imkan); (3) Custom load balancer logic. Service mesh ilə: YAML ilə weight (5%→25%→50%), header-based routing, avtomatik metric topluluğu ilə analiz, Flagger ilə tam avtomatlaşdırma. Service mesh çox asan və güclü.

**S7: Distributed tracing nədir və niyə vacibdir?**
C: Microservice arxitekturasında bir request bir neçə service-dən keçir. Distributed tracing – request-in bütün servisləri keçməsi boyunca izləmə (trace ID). Hansı service yavaş işləyir, hansı xəta buraxır? Jaeger, Zipkin populyar. Istio avtomatik B3/W3C headers propagate edir. Laravel OpenTelemetry ilə span yarada bilər.

**S8: Istio ilə performans itkisi nə qədər olur?**
C: Tipik overhead: latency +5-10ms (p50), +15-30ms (p99). CPU: sidecar üçün 50-100m, memory 100-200MB hər pod. Yüksək QPS servislərdə önəmli ola bilər. Optimization: sidecar resource limit, mTLS tune, lazımsız EnvoyFilter silmək. Linkerd daha yüngüldür (Rust əsaslı), amma daha az feature.

**S9: Linkerd və Istio arasında fərq?**
C: Linkerd – sadə, yüngül, Rust proxy, performans. Istio – çox funksiyalı (EnvoyFilter, Wasm), kompleks, çox istifadə. Kiçik komandalar üçün Linkerd, enterprise üçün Istio. Hər ikisi mTLS, observability, traffic management dəstəkləyir. Linkerd adaptation asan, Istio öyrənmə əyrisi sıxdır.

**S10: Service mesh nə zaman overkill sayılır?**
C: (1) 5-dən az microservice varsa; (2) Monolith-dirsə; (3) Performance-critical workload; (4) Operational expertise yoxdursa (Istio mürəkkəbdir). Alternative: Kubernetes Ingress + NetworkPolicy + library-level reliability (Laravel retry middleware). Service mesh qiyməti – operational complexity.

## Best Practices

1. **Tədricən adoption**: Bütün cluster-ə birdən deploy etmə, namespace-by-namespace.
2. **Namespace injection**: `istio-injection=enabled` label, avtomatik sidecar.
3. **mTLS STRICT**: Production-da strict mode – plaintext qadağan.
4. **Resource limits**: Sidecar üçün CPU/memory limit qoy.
5. **AuthorizationPolicy**: Default deny, explicit allow (zero-trust).
6. **DestinationRule hər servisdə**: Connection pool, circuit breaker konfiqurasiya et.
7. **Timeout və retry**: VirtualService-də application-a görə qur.
8. **Observability**: Jaeger (tracing), Prometheus (metrics), Kiali (graph).
9. **Canary deployment**: Flagger ilə avtomatlaşdır.
10. **Version header**: App-lər version metadata göndərsin (debug asanlığı).
11. **Gradual rollout**: 5% → 25% → 50% → 100% traffic.
12. **Istio upgrade strategy**: Canary upgrade (in-place əvəzinə).
13. **PodDisruptionBudget**: Control plane HA üçün.
14. **Network policy kombinasiyası**: Service mesh + NetworkPolicy defence in depth.
15. **Alert threshold**: Error rate, P99 latency, circuit breaker events.
16. **Test fault injection**: Chaos engineering üçün (delay, abort).
17. **Documentation**: VirtualService/DestinationRule-lar nə üçündür kommentlə.

---

## Praktik Tapşırıqlar

1. Istio qurun: `istioctl install --set profile=demo`, namespace-ə `istio-injection: enabled` label əlavə edin; Laravel pod-un sidecar proxy aldığını `kubectl describe pod`-da görün; `istioctl proxy-status` ilə mesh sağlamlığını yoxlayın
2. mTLS policy tətbiq edin: `PeerAuthentication` `mode: STRICT` — bütün service-to-service trafiki mTLS tələb etsin; `PERMISSIVE` modedən STRICT-ə keçid zamanı nə baş verdiyini görün; `kubectl exec` ilə pod içindən plain HTTP request edib rədd edildiyini test edin
3. Traffic splitting qurun: `VirtualService` ilə Laravel v1 90%, v2 10% — canary deployment simulyasiya edin; `curl`-u 100 dəfə çağıran loop yazın, v2-yə 10% getdiyini sayın; v1-in `HTTP 500` verdiyi vaxt Istio-nun retry etdiyini test edin
4. Circuit breaker konfigurasiya edin: `DestinationRule` `outlierDetection` — 5 serverdən 1-i xəta verərsə 30 saniyə ejection; downstream servisi dayandırın, Kiali dashboard-da circuit açıldığını görün; servis bərpa edildikdən sonra trafik avtomatik geri döndü?
5. Fault injection edin: `VirtualService` `fault: delay: fixedDelay: 5s, percentage: 50%` — istifadəçilərin 50%-i 5s gecikmə yaşasın; Laravel-in bu gecikmə zamanı timeout tənzimləməsini görün; `fault: abort: httpStatus: 503, percentage: 20%` əlavə edin
6. Jaeger ilə distributed trace izləyin: Istio sidecar-ın avtomatik yaratdığı span-ları Jaeger UI-da görün; Laravel → internal API → database chain-i trace edin; latency bottleneck-i müəyyən edin; `sampling: 100%`-dən `10%`-ə endirin, trace azaldığını yoxlayın

## Əlaqəli Mövzular

- [Container Security](29-container-security.md) — mTLS, pod security, Network Policy
- [Distributed Tracing](22-distributed-tracing.md) — Jaeger, Istio trace integration
- [Deployment Strategies](44-deployment-strategies.md) — Istio ilə canary, traffic splitting
- [Observability](42-observability.md) — service mesh observability, Kiali
- [Site Reliability](34-site-reliability.md) — SRE reliability patterns
