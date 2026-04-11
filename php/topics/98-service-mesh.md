# Service Mesh (Istio, Envoy)

## Mündəricat
1. [Service Mesh nədir?](#service-mesh-nədir)
2. [Sidecar Proxy Pattern](#sidecar-proxy-pattern)
3. [mTLS — Mutual TLS](#mtls--mutual-tls)
4. [Traffic Management](#traffic-management)
5. [Observability](#observability)
6. [Service Mesh nə vaxt lazımdır?](#service-mesh-nə-vaxt-lazımdır)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Service Mesh nədir?

```
Service Mesh — microservice-lər arası communication-ı idarə edən
infrastructure layer.

Problemlər ki service mesh həll edir:
  Service A → Service B:
  - Necə şifrələnir? (TLS)
  - Uğursuz olduqda retry edirmi?
  - Circuit breaker varmı?
  - Bu request-in trace-i hansıdır?
  - A-nın B-yə çatması authorization-a tabedirmi?

Ənənəvi üsul:
  Hər service öz retry, timeout, TLS, tracing kodunu yazır.
  Library-lər: Hystrix, Resilience4j...
  Kod base-ə toxunmaq lazımdır.

Service Mesh üsulu:
  Bu məsuliyyəti application-dan çıxar.
  Infrastructure level-ə at.
  Kod dəyişikliyi olmadan!

İstio — ən geniş yayılmış service mesh.
Envoy — data plane proxy (Istio-nun altında işləyir).
Linkerd — daha sadə alternativ.
```

---

## Sidecar Proxy Pattern

```
Hər Pod-a avtomatik Envoy sidecar əlavə edilir.
Bütün traffic bu proxy-dən keçir.

Sidecar olmadan:
  Service A ────────────────────────────► Service B

Sidecar ilə:
  Service A → Envoy(A) ────────────────► Envoy(B) → Service B
               (sidecar)                  (sidecar)
              [retry, TLS,               [auth, rate limit,
               tracing]                   tracing, metrics]

Pod-da:
  ┌─────────────────────────────┐
  │          Pod                │
  │  ┌───────────┐ ┌─────────┐  │
  │  │  App      │ │ Envoy   │  │
  │  │ Container │ │ Sidecar │  │
  │  └───────────┘ └─────────┘  │
  └─────────────────────────────┘
  
  Envoy injected avtomatik olaraq (mutating webhook ilə).
  Application kodu Envoy-dan xəbərsizdir.
  localhost → Envoy → remote service

Control Plane (Istio istiod):
  Sidecar-lara policy göndərir.
  "Service A yalnız Service B-yə çata bilər"
  "Retry: 3 dəfə, 1s exponential backoff"
```

---

## mTLS — Mutual TLS

```
Normal TLS: Client server-i verify edir.
mTLS: Hər iki tərəf bir-birini verify edir.

Normal TLS:
  Client ──TLS──► Server
  "Sən kimsən?" → Server Certificate
  Client server-ə inanır.
  Server client-ə inanmır (anonim)

mTLS:
  Client ──TLS──► Server
  "Sən kimsən?" → Server Certificate
  "Sən kimsən?" → Client Certificate ← əlavə!
  Hər iki tərəf sertifikat təqdim edir.

Service Mesh-də mTLS:
  Hər service öz sertifikatını alır (Istio CA-dan).
  Service A → Service B: mutual authentication
  Service A bilir: "B həqiqətən B-dir"
  Service B bilir: "A həqiqətən A-dır"

Avtomatik mTLS (Istio):
  Kod dəyişikliyi olmadan!
  Sidecar proxy-lər arasında şifrələnmiş.
  STRICT mode: plain-text traffic reject edilir.

  PeerAuthentication:
    mtls:
      mode: STRICT  # Yalnız mTLS qəbul et

Zero-trust security:
  "Heç kimə güvənmə, hər şeyi yoxla"
  Internal traffic-ı da şifrələ.
```

---

## Traffic Management

```
Istio traffic management imkanları:

1. Retry Policy:
  retries:
    attempts: 3
    perTryTimeout: 2s
    retryOn: "5xx,connect-failure,retriable-4xx"

2. Timeout:
  timeout: 10s
  (Upstream-ə 10s-dən çox gözləmə)

3. Circuit Breaker (DestinationRule):
  outlierDetection:
    consecutiveErrors: 5        # 5 ardıcıl xəta
    interval: 30s               # 30s-lik window
    baseEjectionTime: 30s       # 30s kənara at
    maxEjectionPercent: 50      # Max 50% endpoint kənar at

4. Traffic Split (A/B / Canary):
  route:
  - destination:
      host: product-service
      subset: v1
    weight: 90
  - destination:
      host: product-service
      subset: v2
    weight: 10
  (v2-yə 10% traffic yönləndir)

5. Fault Injection (Test üçün):
  fault:
    delay:
      percentage:
        value: 50
      fixedDelay: 2s     # 50% request-ə 2s əlavə gecikmə
    abort:
      percentage:
        value: 10
      httpStatus: 500    # 10% request 500 qaytarsın
```

---

## Observability

```
Service Mesh bütün traffic-ı görür → metrics, logs, traces avtomatik.

Metrics (Prometheus):
  Hər servis çütü üçün:
  - Request rate (req/s)
  - Error rate (%)
  - P50/P95/P99 latency

  istio_requests_total{
    source_app="frontend",
    destination_app="api",
    response_code="200"
  }

Distributed Tracing (Jaeger/Zipkin):
  Sidecar-lar trace context-i avtomatik ötürür.
  Service A → Service B → Service C → DB
  Hər request-in tam journey-si görünür.

  Tələb: Application trace header-i saxlamalıdır!
    X-B3-TraceId, X-B3-SpanId forward edilməlidir.

Service Graph (Kiali):
  Visual topology: hansı service hansıya çağırır?
  Real-time traffic flow.
  Error hotspot aşkarlaması.
```

---

## Service Mesh nə vaxt lazımdır?

```
Lazımdır:
  ✓ 10+ microservice
  ✓ Zero-trust security tələbi
  ✓ Cross-cutting concerns (retry, timeout) mərkəzləşdirilmiş idarəsi
  ✓ Canary deployment trafic split
  ✓ Compliance: traffic şifrələnməsi audit

Lazım deyil (overkill):
  ✗ Monolith / az sayda servis
  ✗ Kiçik komanda (learning curve yüksəkdir)
  ✗ Simple K8s deployment bəs edir
  ✗ Performance overhead qəbul edilmir
    (Envoy sidecar: ~1-3ms latency əlavə)

Alternativlər:
  Linkerd: Daha sadə, Rust-based sidecar, az overhead
  Cilium:  eBPF-based, sidecar-sız (ambient mesh)
  
Ambient Mesh (yeni trend):
  Sidecar yoxdur!
  Node-level proxy (ztunnel) + L7 proxy (waypoint)
  Daha az overhead, sadə idarəetmə
```

---

## İntervyu Sualları

- Service mesh nədir? Nə problemləri həll edir?
- Sidecar proxy pattern — application koduna nə qədər toxunur?
- mTLS normal TLS-dən nəylə fərqlənir? Microservice-lərdə niyə vacibdir?
- Istio fault injection nə üçün istifadə edilir?
- Service mesh olmadan distributed tracing mümkündürmü?
- 5 microservice-li sistemdə service mesh tövsiyə edərdinizmi? Niyə?
- Ambient mesh nədir? Sidecar-dan üstünlüyü?
