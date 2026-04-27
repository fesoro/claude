# Service Mesh (Architect ⭐⭐⭐⭐⭐)

## İcmal
Service Mesh, microservices arxitekturasında service-lər arası kommunikasiyanı idarə edən dedicated infrastructure layer-dir. Hər service yanında sidecar proxy (Envoy) yerləşdirilir, bütün network traffic bu proxy-dən keçir. Istio, Linkerd, Consul Connect populyar implementasiyalardır. Interview-da Architect səviyyəli mövqelər üçün çıxır — böyük microservices infrastrukturunun idarəsini başa düşüb-başa düşmədiyinizi ölçür.

## Niyə Vacibdir
Yüzlərlə microservice-dən ibarət sistemdə hər service-in öz retry, circuit breaker, TLS, observability kodunu yazması code duplication yaradır. Service Mesh bu cross-cutting concern-ləri infrastructure layer-ə çəkir — developer-lər yalnız biznes məntiqinə fokus olur. mTLS ilə service-lər arası şifrələmə avtomatik həyata keçirilir. Bu mövzunu bilmək large-scale distributed systems experience-inizi göstərir.

## Əsas Anlayışlar

- **Data Plane**: Real network traffic-i idarə edir — sidecar proxy-lər (Envoy) bu hissəni təşkil edir
- **Control Plane**: Proxy-ləri konfiqurasiya edir — Istio-da `istiod`, Linkerd-də `control plane`
- **Sidecar Proxy**: Hər pod-da service ilə birlikdə işləyən Envoy proxy — service network-dən xəbərsizdir
- **mTLS (Mutual TLS)**: Service-lər arasında iki tərəfli şifrələmə və autentifikasiya — zero-trust network
- **Traffic Management**: Load balancing, A/B testing, canary deployment, traffic splitting
- **Observability**: Distributed tracing (Jaeger), metrics (Prometheus), access logging — avtomatik, kod lazım deyil
- **Circuit Breaking**: Service bir müddət cavab vermirsə digər service-lər onunla danışmağı dayandırır
- **Retry Logic**: Uğursuz request-lər avtomatik retry edilir — exponential backoff
- **Rate Limiting**: Service-ə gələn request sayını məhdudlaşdırmaq
- **Service Discovery**: Service-lərin bir-birini tapması — proxy directory-ni bilir
- **Istio vs Linkerd**: Istio daha güclü amma kompleks, Linkerd sadə amma less feature-rich
- **eBPF-based mesh**: Cilium kimi yeni nəsil — sidecar olmadan, kernel-level proxying
- **Ambient mesh**: Istio-nun sidecar-sız rejimi — node-level proxy (ztunnel)
- **East-west traffic**: Service-lər arası internal communication — service mesh bu trafiği idarə edir
- **Virtual Service & Destination Rule**: Istio-da traffic routing konfiqurasiyası

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Service Mesh mövzusunda "niyə lazımdır?" sualına konkret problem ilə cavab verin: "50 microservice var, hərəsində retry + circuit breaker kodu yazmaq əvəzinə infrastruktura çəkirik." Sonra trade-off: latency overhead (Envoy hop), complexity, operational cost.

**Follow-up suallar:**
- "Service Mesh olmadan retry/circuit breaker necə implement edərdiniz?"
- "mTLS-in application-level TLS-dən fərqi nədir?"
- "Sidecar overhead-i nə qədərdir?"
- "Service Mesh-i ne vaxt istifadə etməmək lazımdır?"

**Ümumi səhvlər:**
- Service Mesh = API Gateway düşünmək — fərqlidir. API Gateway north-south (xarici) trafiği, Service Mesh east-west (internal) trafiği idarə edir
- Kiçik sistemdə Service Mesh tətbiq etmək — 5-10 service üçün overkill
- mTLS certificate rotation-ı planlaşdırmamaq
- Observability-ni tək Service Mesh-ə buraxmaq — application-level tracing da lazımdır

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab API Gateway ilə fərqini aydın izah edir, mTLS-in sıfır trust modelindəki rolunu, eBPF-based mesh kimi yeni yanaşmaları bilir.

## Nümunələr

### Tipik Interview Sualı
"Service Mesh nədir? API Gateway ilə fərqi nədir? Nə vaxt lazımdır?"

### Güclü Cavab
"Service Mesh east-west trafiği — yəni microservice-lər arası kommunikasiyanı idarə edir. API Gateway north-south — xarici client-dən gələn trafiği idarə edir. Service Mesh-in əsas üstünlüyü: retry, circuit breaker, mTLS, distributed tracing kimi cross-cutting concern-ləri application kodundan çıxarıb infrastructure-a aparmaq. Hər service yanında Envoy sidecar proxy yerləşir, bütün network traffic ondan keçir. Lakin bu əlavə latency (1-2ms hop) deməkdir. 20+ service olan sistemlər üçün məntiqlidir. Daha az service üçün Resilience4j, Polly kimi library-lər kifayət edir."

### Kod / Konfiqurasiya Nümunəsi

```yaml
# Istio VirtualService — Traffic splitting (Canary)
apiVersion: networking.istio.io/v1alpha3
kind: VirtualService
metadata:
  name: order-service
spec:
  hosts:
    - order-service
  http:
    - match:
        - headers:
            x-canary:
              exact: "true"
      route:
        - destination:
            host: order-service
            subset: v2
          weight: 100
    - route:
        - destination:
            host: order-service
            subset: v1
          weight: 90
        - destination:
            host: order-service
            subset: v2
          weight: 10

---
# DestinationRule — Subsets + Circuit Breaking + mTLS
apiVersion: networking.istio.io/v1alpha3
kind: DestinationRule
metadata:
  name: order-service
spec:
  host: order-service
  trafficPolicy:
    tls:
      mode: ISTIO_MUTUAL   # mTLS avtomatik
    connectionPool:
      tcp:
        maxConnections: 100
      http:
        http1MaxPendingRequests: 100
        maxRequestsPerConnection: 10
    outlierDetection:          # Circuit Breaking
      consecutive5xxErrors: 5
      interval: 30s
      baseEjectionTime: 30s
      maxEjectionPercent: 100
  subsets:
    - name: v1
      labels:
        version: v1
    - name: v2
      labels:
        version: v2

---
# Retry Policy
apiVersion: networking.istio.io/v1alpha3
kind: VirtualService
metadata:
  name: payment-service
spec:
  hosts:
    - payment-service
  http:
    - retries:
        attempts: 3
        perTryTimeout: 2s
        retryOn: gateway-error,connect-failure,retriable-4xx
      timeout: 10s
      route:
        - destination:
            host: payment-service

---
# PeerAuthentication — mTLS enforce
apiVersion: security.istio.io/v1beta1
kind: PeerAuthentication
metadata:
  name: default
  namespace: production
spec:
  mtls:
    mode: STRICT   # Bütün service-lər mTLS istifadə etməlidir

---
# AuthorizationPolicy — Zero-trust: yalnız icazəli service-lər danışa bilər
apiVersion: security.istio.io/v1beta1
kind: AuthorizationPolicy
metadata:
  name: order-service-policy
  namespace: production
spec:
  selector:
    matchLabels:
      app: order-service
  rules:
    - from:
        - source:
            principals:
              - cluster.local/ns/production/sa/api-gateway
              - cluster.local/ns/production/sa/notification-service
      to:
        - operation:
            methods: ["GET", "POST"]
            paths: ["/api/orders*"]
```

```yaml
# Kubernetes Deployment — Sidecar injection avtomatik
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
  namespace: production
  labels:
    app: order-service
    version: v2
spec:
  replicas: 3
  selector:
    matchLabels:
      app: order-service
      version: v2
  template:
    metadata:
      labels:
        app: order-service
        version: v2
      annotations:
        sidecar.istio.io/inject: "true"  # Envoy sidecar əlavə edilir
        # proxy.istio.io/config: |       # Əlavə proxy konfiqurasiyası
        #   proxyStatsMatcher:
        #     inclusionPrefixes:
        #     - "cluster.outbound"
    spec:
      serviceAccountName: order-service
      containers:
        - name: order-service
          image: myregistry/order-service:v2
          ports:
            - containerPort: 8080
          # Service Mesh sayəsində burada retry/TLS/tracing kodu yoxdur
```

```php
// Application kodu — Service Mesh cross-cutting concern-ləri idarə edir
// Developer yalnız biznes məntiqinə fokus olur

class OrderService
{
    public function __construct(
        private HttpClient $httpClient  // sadə HTTP client
    ) {}

    public function checkInventory(string $productId, int $qty): bool
    {
        // Retry, timeout, circuit breaking — Envoy sidecar idarə edir
        // mTLS — avtomatik, kod lazım deyil
        // Distributed trace ID — header avtomatik ötürülür
        $response = $this->httpClient->get(
            "http://inventory-service/products/{$productId}/availability",
            ['quantity' => $qty]
        );

        return $response->json('available');
    }
}
```

**Observability — Distributed Tracing (avtomatik):**
```yaml
# Jaeger — Istio avtomatik trace yaradır
# Prometheus — Envoy metrics avtomatik export edir
apiVersion: telemetry.istio.io/v1alpha1
kind: Telemetry
metadata:
  name: default
  namespace: production
spec:
  tracing:
    - providers:
        - name: jaeger
  metrics:
    - providers:
        - name: prometheus
  accessLogging:
    - providers:
        - name: otel
```

## Praktik Tapşırıqlar

- Istio-nu local Kubernetes cluster-da quraşdırın (minikube ilə)
- mTLS STRICT mode aktivləşdirin, iki service arasında plaintext communication-ı blok edin
- Canary deployment üçün VirtualService yazın — 90/10 traffic split
- Circuit breaking konfiqurasiyası — 5 ardıcıl 5xx sonra service ejection
- Distributed tracing dashboard-da request flow-u izləyin

## Əlaqəli Mövzular

- `01-monolith-vs-microservices.md` — Service Mesh yalnız microservices-də mənalıdır
- `13-blue-green-canary.md` — Service Mesh ilə traffic splitting
- `14-zero-downtime-deployments.md` — Service Mesh deployment strategiyaları
- `09-backend-for-frontend.md` — North-south vs East-west traffic
