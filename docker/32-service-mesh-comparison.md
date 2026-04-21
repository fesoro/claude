# Service Mesh Müqayisəsi (Istio, Linkerd, Consul, Kuma)

## Nədir? (What is it?)

**Service Mesh** — mikroservislər arası şəbəkə kommunikasiyanı idarə edən dedicated infrastruktur layeridir. Application kodunu dəyişmədən mTLS, retries, timeouts, load balancing, observability, traffic splitting kimi funksionalları təmin edir.

Əsas komponentlər:
- **Data plane** — hər pod-da sidecar (Envoy, linkerd-proxy) və ya node-level proxy
- **Control plane** — config paylayan, certificate idarə edən (istiod, linkerd controller)

## Əsas Konseptlər

### 1. Sidecar Pattern

```
┌─ Pod ──────────────────────────────────┐
│  ┌──────────┐   localhost   ┌──────┐  │
│  │ App      │ ────────────► │ Envoy│  │
│  │ (Laravel)│ ◄──────────── │sidecar│ │
│  └──────────┘                └──────┘  │
│                                 │      │
└─────────────────────────────────┼──────┘
                                  │
                                  ▼
                              mTLS traffic
                                  ↓
                            Another Pod
```

Bütün trafik sidecar-dan keçir — app kodunu dəyişmir.

### 2. Control Plane + Data Plane

```
┌─ Control Plane ──────────────┐
│  istiod / linkerd-controller │
│  - Config distribute         │
│  - Certificate issue (CA)    │
│  - Telemetry aggregate       │
└─────────────┬────────────────┘
              │ xDS API
              ▼
┌─ Data Plane (hər pod-da) ────┐
│  Envoy / linkerd-proxy       │
│  - mTLS                      │
│  - Load balance              │
│  - Retry/Timeout             │
│  - Metrics                   │
└──────────────────────────────┘
```

### 3. Service Mesh Müqayisəsi

| Feature | Istio | Linkerd | Consul | Kuma |
|---------|-------|---------|--------|------|
| Proxy | Envoy | linkerd2-proxy (Rust) | Envoy | Envoy |
| Control plane dili | Go | Go/Rust | Go | Go |
| CNCF status | Graduated | Graduated | N/A | Sandbox |
| Resource usage | Yüksək | Aşağı | Orta | Orta |
| Ambient mode | Hə | Yox | Yox | Yox |
| Multi-cluster | Hə | Hə | Hə | Hə |
| Non-K8s dəstək | Limitli | Yox | Güclü (VM) | Hə |
| Learning curve | Dik | Asan | Orta | Orta |

## Istio

### 1. Arxitektura

```
┌─ istiod (control plane) ──────────┐
│  - Pilot (config)                 │
│  - Citadel (certificates)         │
│  - Galley (validation)            │
└───────────────────────────────────┘
            │
            ▼ xDS
┌─ Envoy sidecar (data plane) ──────┐
│  - Inbound/outbound proxy         │
│  - mTLS                           │
│  - Traffic routing                │
└───────────────────────────────────┘
```

### 2. Install

```bash
curl -L https://istio.io/downloadIstio | sh -
cd istio-1.20.0
istioctl install --set profile=default -y

# Namespace-də sidecar injection aktiv et
kubectl label namespace production istio-injection=enabled
```

### 3. VirtualService (Traffic Routing)

```yaml
apiVersion: networking.istio.io/v1
kind: VirtualService
metadata:
  name: laravel
  namespace: production
spec:
  hosts:
    - laravel.production.svc.cluster.local
  http:
    - match:
        - headers:
            x-canary:
              exact: "true"
      route:
        - destination:
            host: laravel
            subset: v2
    - route:
        - destination:
            host: laravel
            subset: v1
          weight: 90
        - destination:
            host: laravel
            subset: v2
          weight: 10       # 10% canary
      retries:
        attempts: 3
        perTryTimeout: 2s
        retryOn: 5xx,reset,connect-failure
      timeout: 10s
```

### 4. DestinationRule (Subsets)

```yaml
apiVersion: networking.istio.io/v1
kind: DestinationRule
metadata:
  name: laravel
  namespace: production
spec:
  host: laravel
  trafficPolicy:
    connectionPool:
      tcp:
        maxConnections: 100
      http:
        http2MaxRequests: 1000
        maxRequestsPerConnection: 10
    outlierDetection:
      consecutive5xxErrors: 3
      interval: 30s
      baseEjectionTime: 30s
    loadBalancer:
      simple: LEAST_REQUEST
  subsets:
    - name: v1
      labels:
        version: "1.0"
    - name: v2
      labels:
        version: "2.0"
```

### 5. mTLS (Strict)

```yaml
apiVersion: security.istio.io/v1
kind: PeerAuthentication
metadata:
  name: default
  namespace: production
spec:
  mtls:
    mode: STRICT   # PERMISSIVE | STRICT | DISABLE
```

Artıq production-da bütün pod-lar arası trafik avtomatik mTLS-dir.

### 6. AuthorizationPolicy

```yaml
apiVersion: security.istio.io/v1
kind: AuthorizationPolicy
metadata:
  name: laravel-api-allow
  namespace: production
spec:
  selector:
    matchLabels:
      app: laravel-api
  action: ALLOW
  rules:
    - from:
        - source:
            principals: ["cluster.local/ns/frontend/sa/nextjs"]
      to:
        - operation:
            methods: ["GET", "POST"]
            paths: ["/api/*"]
```

### 7. Gateway (Ingress)

```yaml
apiVersion: networking.istio.io/v1
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
      tls:
        mode: SIMPLE
        credentialName: laravel-tls
      hosts:
        - laravel.example.com
```

### 8. Istio Ambient Mode (Sidecar-less)

2023-də GA. Sidecar yerinə:
- **ztunnel** (per-node) — L4 + mTLS
- **waypoint proxy** (optional) — L7 policy

```yaml
# Ambient mode-u aktiv et
apiVersion: v1
kind: Namespace
metadata:
  name: production
  labels:
    istio.io/dataplane-mode: ambient
```

Üstünlükləri:
- Pod CPU/memory azalır (sidecar yox)
- Pod restart-sız mesh-ə əlavə
- Hər pod per-request ~100 µs latency azalır

Dezavantajları:
- Hələ də yeni (yetkin deyil)
- Bəzi L7 feature-lər waypoint tələb edir

## Linkerd

### 1. Niyə Linkerd

- **Rust proxy** — Envoy-dən yüngül, memory safe
- **Zero config** — mTLS default, heç nə yazmadan işləyir
- **Sadəlik** — Istio-dan çox az CRD
- **Performance** — ən aşağı latency overhead

### 2. Install

```bash
curl -sL https://run.linkerd.io/install | sh
linkerd install | kubectl apply -f -
linkerd viz install | kubectl apply -f -

# Namespace-ə sidecar inject
kubectl get deploy -n production -o yaml | linkerd inject - | kubectl apply -f -
```

### 3. ServiceProfile

```yaml
apiVersion: linkerd.io/v1alpha2
kind: ServiceProfile
metadata:
  name: laravel.production.svc.cluster.local
  namespace: production
spec:
  routes:
    - name: GET /api/users
      condition:
        method: GET
        pathRegex: "/api/users/[0-9]+"
      responseClasses:
        - condition:
            status:
              min: 500
              max: 599
          isFailure: true
      timeout: 2s
      isRetryable: true
  retryBudget:
    retryRatio: 0.2         # max 20% extra retry
    minRetriesPerSecond: 10
    ttl: 10s
```

### 4. TrafficSplit (Canary)

```yaml
apiVersion: split.smi-spec.io/v1alpha1
kind: TrafficSplit
metadata:
  name: laravel-canary
  namespace: production
spec:
  service: laravel
  backends:
    - service: laravel-stable
      weight: 900m         # 90%
    - service: laravel-canary
      weight: 100m         # 10%
```

### 5. Linkerd Viz (Dashboard)

```bash
linkerd viz dashboard
# Real-time: success rate, latency p50/p95/p99, RPS
```

Tap — real-time traffic görmək:
```bash
linkerd viz tap -n production deploy/laravel
```

## Consul Connect

### 1. Xüsusiyyətləri

HashiCorp-un service mesh-i. VM + K8s hibrid mühitlərdə yaxşıdır:

```bash
helm install consul hashicorp/consul \
    --namespace consul \
    --set global.name=consul \
    --set connectInject.enabled=true
```

### 2. ServiceDefaults

```yaml
apiVersion: consul.hashicorp.com/v1alpha1
kind: ServiceDefaults
metadata:
  name: laravel
spec:
  protocol: http
```

### 3. ServiceIntentions (Authorization)

```yaml
apiVersion: consul.hashicorp.com/v1alpha1
kind: ServiceIntentions
metadata:
  name: laravel-api
spec:
  destination:
    name: laravel-api
  sources:
    - name: frontend
      action: allow
    - name: "*"
      action: deny
```

### 4. Non-K8s İnteqrasiya

```hcl
# VM-də Consul agent
service {
  name = "legacy-mysql"
  port = 3306
  connect {
    sidecar_service {}
  }
}
```

K8s-dəki Laravel bu legacy MySQL-ə mesh vasitəsilə çata bilər.

## Kuma

### 1. Özəllikləri

- Envoy-based
- Universal (K8s + VM)
- Multi-zone (multi-cluster native)
- Kong-un dəstəklədiyi

### 2. Install

```bash
kumactl install control-plane | kubectl apply -f -
```

### 3. Mesh

```yaml
apiVersion: kuma.io/v1alpha1
kind: Mesh
metadata:
  name: default
spec:
  mtls:
    enabledBackend: ca-1
    backends:
      - name: ca-1
        type: builtin
  tracing:
    defaultBackend: jaeger-1
    backends:
      - name: jaeger-1
        type: zipkin
        conf:
          url: http://jaeger:9411/api/v2/spans
```

### 4. TrafficPermission

```yaml
apiVersion: kuma.io/v1alpha1
kind: TrafficPermission
mesh: default
metadata:
  name: allow-frontend
spec:
  sources:
    - match:
        kuma.io/service: frontend_default_svc_80
  destinations:
    - match:
        kuma.io/service: laravel-api_default_svc_80
```

## Service Mesh Ortaq Feature-ləri

### 1. mTLS (Mutual TLS)

Hər service üçün avto certificate issue:
- Kim kiminlə danışır (identity)
- Trafik encrypted
- Certificate rotation avto

### 2. Traffic Splitting (Canary)

A version 90%, B version 10% — incremental rollout. Success rate/latency metric-lərinə görə avto promote.

### 3. Retries və Timeouts

App kodunda retry yazmağa ehtiyac yox — mesh idarə edir:
- Exponential backoff
- Per-try timeout
- Retry budget (infinite retry qarşısını alır)

### 4. Circuit Breaking

Failing service-ə çağırışları dayandırıb fail-fast et:

```yaml
outlierDetection:
  consecutive5xxErrors: 5
  interval: 10s
  baseEjectionTime: 30s
```

### 5. Observability

- **Metrics** (RED): Rate, Errors, Duration per service
- **Traces** (distributed): bir request bütün service-lərdən necə keçir
- **Logs** (access logs)
- **Service graph** — kim kiminlə danışır

## Sidecar vs Ambient

### Sidecar Mode (Klassik)

```
Pod: [App + Envoy] — hər pod-da Envoy
  + Tam L7 feature
  + Mature
  - CPU/memory overhead (hər pod +100-500m CPU, +50-200Mi mem)
  - Pod restart sidecar update üçün
```

### Ambient Mode (Istio)

```
Node: [ztunnel] — L4 + mTLS (bütün pod-lar üçün)
Namespace: [waypoint] — L7 (optional)
  + Resource efficient
  + Pod restart-sız upgrade
  + Incremental adoption
  - Yeni, mature deyil
  - Bəzi feature-lər yoxdur
```

## When NOT to Use a Service Mesh

1. **Kiçik cluster (< 5 servis)** — overhead dəyməz
2. **Monolith** — mesh mikroservis üçündür
3. **Team ready deyil** — steep learning curve
4. **Latency critical** — hər request +1-5 ms
5. **Resource constrained** — sidecar-lar CPU/mem yeyir
6. **Library ilə həll olunur** — gRPC üçün go-grpc-middleware
7. **Single team** — organizational complexity

**Alternativ**: API Gateway (Kong, Ambassador) + mutual TLS cert-manager ilə.

## PHP/Laravel ilə İstifadə

### Laravel Microservices ilə Istio

Senari: `laravel-auth`, `laravel-users`, `laravel-orders` — 3 mikroservis.

```yaml
# 1. mTLS strict
apiVersion: security.istio.io/v1
kind: PeerAuthentication
metadata:
  name: default
  namespace: production
spec:
  mtls:
    mode: STRICT
---
# 2. Authorization
apiVersion: security.istio.io/v1
kind: AuthorizationPolicy
metadata:
  name: orders-allow-users
  namespace: production
spec:
  selector:
    matchLabels:
      app: laravel-orders
  rules:
    - from:
        - source:
            principals:
              - "cluster.local/ns/production/sa/laravel-users"
              - "cluster.local/ns/production/sa/laravel-auth"
---
# 3. Retry + timeout
apiVersion: networking.istio.io/v1
kind: VirtualService
metadata:
  name: laravel-orders
  namespace: production
spec:
  hosts:
    - laravel-orders
  http:
    - route:
        - destination:
            host: laravel-orders
      timeout: 5s
      retries:
        attempts: 3
        perTryTimeout: 2s
        retryOn: 5xx,reset
```

Laravel HTTP client avto retry-dan istifadə edir (yəni kod sadədir):

```php
// Laravel tərəfi sadə HTTP call
$response = Http::timeout(5)->get('http://laravel-orders/api/orders/123');
// Istio sidecar retry/timeout/mTLS-i avto tətbiq edir
```

### Laravel Queue Traffic Splitting

Yeni queue worker versiyasını test etmək:

```yaml
apiVersion: networking.istio.io/v1
kind: VirtualService
metadata:
  name: laravel-worker
spec:
  hosts:
    - laravel-worker
  http:
    - route:
        - destination:
            host: laravel-worker
            subset: v1
          weight: 95
        - destination:
            host: laravel-worker
            subset: v2
          weight: 5          # canary 5%
```

## Interview Sualları

**1. Service mesh niyə lazımdır?**
Mikroservislər çox olduqda — mTLS, retry, timeout, observability, traffic splitting app kodundan infrastructure-ə köçürülür. Poliglot stack-də (PHP + Go + Java) hər dil üçün ayrı-ayrı library yazmamaq üçün.

**2. Istio və Linkerd arasında necə seçim edim?**
- Istio: feature-rich, ecosystem böyük, Envoy ilə tam config imkanı, ambient mode var
- Linkerd: sadə, Rust-da yazılıb (sürətli), resource-efficient, zero-config mTLS
Kiçik komanda → Linkerd. Kompleks routing/policy → Istio.

**3. Sidecar proxy latency nə qədər əlavə edir?**
~1-5 ms per hop. İki service arası call = 2 sidecar = 2-10 ms əlavə. Ambient mode (L4-only) daha az (<1 ms). Latency-critical app-lər üçün ciddi faktor.

**4. Ambient mode klassik sidecar-dan nəyi dəyişdirir?**
Sidecar hər pod-da Envoy əvəzinə, hər node-da ztunnel (L4 + mTLS) və namespace-də waypoint (L7, optional). Resource azalır, pod restart-sız upgrade.

**5. mTLS nədir, niyə vacib?**
Mutual TLS — hər iki tərəf certificate ilə identifikasiya olunur. Service identity (kim kiminlə danışır) + encryption. Control plane CA kimi işləyir, cert-ləri avto issue/rotate edir.

**6. Istio VirtualService və DestinationRule fərqi?**
- VirtualService: routing qərarları (kim hara gedir, match, weight, retry, timeout)
- DestinationRule: destination policy (subsets, connection pool, outlier detection, load balancer)

**7. Circuit breaker necə işləyir service mesh-də?**
Outlier detection — N consecutive 5xx error olduqda endpoint pool-dan eject edilir, müəyyən müddət sonra geri qoşulmağa cəhd edilir. App-də try/catch yazmağa ehtiyac yoxdur.

**8. Service mesh HTTP-də işləyir amma TCP-də?**
Hə — TCP-də də mTLS, retry (connect-level), circuit breaker işləyir. Amma L7 feature-lər (path-based routing, HTTP retry on 5xx, JWT validation) yalnız HTTP/gRPC-də.

**9. Mesh-dəki performance impact necə ölçülür?**
Linkerd benchmarks: p99 latency +2-5 ms, CPU +10-50m per pod, memory +20-50 Mi per pod. Istio daha ağırdır. Load test ilə baseline vs mesh müqayisə etmək vacibdir.

**10. Service mesh nə vaxt istifadə ETMƏMƏLİ?**
1. Monolit
2. Az sayda service (< 5-10)
3. Team hazır deyil (steep curve)
4. Resource constrained
5. Extreme low-latency (fintech HFT)
6. Single language + library (Go gRPC) ilə həll olunur

## Best Practices

1. **mTLS PERMISSIVE → STRICT** — gradual migration
2. **Sidecar injection selektiv** — `istio-injection=enabled` label ilə
3. **Resource limits sidecar-da** — istio-proxy container üçün explicit
4. **Retry budget** — infinite retry storm qarşısı
5. **Tracing sampling** — production-da 1-5%
6. **Gateway və mesh separate** — ingress ayrı component
7. **Canary analiz** — Flagger, Argo Rollouts ilə avto promote
8. **Observability-first** — metric/trace olmadan debug çətin
9. **Linkerd kiçik cluster üçün** — sadəlik dəyər
10. **Istio ambient gələcəkdir** — yeni cluster-lər üçün düşün
11. **Multi-cluster gateway** — federation üçün
12. **Not a silver bullet** — mesh-in həll etmədiyi: DB outages, app bugs, business logic

### Faydalı Alətlər

| Alət | Funksiya |
|------|----------|
| istioctl | Istio CLI |
| linkerd (CLI) | Linkerd install/debug |
| kiali | Istio service graph |
| meshery | Multi-mesh management |
| flagger | Progressive delivery |
| kuma gui | Kuma dashboard |
