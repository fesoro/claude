# Service Mesh (Lead)

## İcmal

Service Mesh - microservices arasındakı şəbəkə əlaqələrini idarə edən xüsusi infrastructure layer-dir. Application kodu dəyişdirmədən retries, timeouts, mTLS, observability, traffic splitting kimi cross-cutting concerns-i təmin edir.

### Problem (Niyə lazımdır?)

Microservices arxitekturasında hər servisin qarşılaşdığı məsələlər:
- **Retry logic** - request uğursuz olarsa nə qədər dəfə təkrarlamaq?
- **Timeout management** - slow service-i nə vaxt dayandırmaq?
- **Circuit breaking** - fail olan servisi trafikdən çıxarmaq
- **mTLS encryption** - service-to-service şifrələmə
- **Observability** - distributed tracing, metrics, logs
- **Traffic management** - canary deployment, A/B testing
- **Authorization** - hansı servis hansı servisə zəng edə bilər?

Bu funksiyaları hər servisə library kimi əlavə etsək:
- Hər dildə (PHP, Java, Go, Python, Node.js) eyni logic yenidən yazılır
- Library yeniləməsi bütün servisləri deploy etməyi tələb edir
- Business code infrastructure concern-lə qarışır
- Polyglot environment-də uyğunsuzluq yaranır

### Həll: Service Mesh

Service Mesh bu funksiyaları **sidecar proxy** vasitəsilə application-dan kənarda həyata keçirir. Laravel, Spring Boot və ya Go servis sadəcə `localhost:8080` üzərindən istək göndərir - retry, mTLS, tracing avtomatik sidecar tərəfindən edilir.

```
Əvvəl:
[Laravel App] --HTTP--> [User Service]
    └ retry, mTLS, tracing kodu daxilində

Sonra (Service Mesh ilə):
[Laravel App] → [Envoy Sidecar] --mTLS+retry--> [Envoy Sidecar] → [User Service]
     (sadə HTTP)                                                    (sadə HTTP)
```


## Niyə Vacibdir

Mikroservis sayı artdıqca mTLS, retry, circuit breaker, observability hər servisdə ayrı-ayrılıqda implement etmək mümkün olmur. Service mesh (Istio/Linkerd) bu cross-cutting concern-ləri infra-ya köçürür. K8s-da Envoy sidecar — real şirkətlərin standart yanaşmasıdır.

## Arxitektura

### Data Plane

Data plane hər pod-a yerləşən sidecar proxy-lərdən ibarətdir. Bütün gələn və gedən trafik transparent şəkildə intercept edilir.

- **Envoy** - C++ ilə yazılmış, Istio və Consul Connect istifadə edir
- **linkerd2-proxy** - Rust ilə yazılmış, çox yüngül, Linkerd-in öz proxy-si
- **MOSN** - Go-da yazılmış, Ant Group-un Envoy alternativi

Pod-a sidecar inject edilir (Kubernetes-də `istio-injection=enabled` label ilə avtomatik). Traffic intercepti `iptables` qaydaları vasitəsilə edilir.

### Control Plane

Control plane sidecar-ları idarə edir - konfiqurasiya, sertifikat, policy push edir.

- **Istio**: `istiod` (pilot + citadel + galley birləşdirilib)
- **Linkerd**: `destination`, `identity`, `proxy-injector`
- **Consul Connect**: Consul server-lər

Control plane sidecar-lara konfiqurasiyanı **xDS API** (Envoy Discovery Service) üzərindən push edir: LDS (Listener), CDS (Cluster), EDS (Endpoint), RDS (Route).

```
                   ┌─────────────────────┐
                   │  Control Plane      │
                   │  (istiod)           │
                   │  - Config           │
                   │  - Certificates     │
                   │  - Policy           │
                   └──────────┬──────────┘
                              │ xDS API
           ┌──────────────────┼──────────────────┐
           ↓                  ↓                  ↓
    ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
    │  Pod A       │   │  Pod B       │   │  Pod C       │
    │ ┌──────────┐ │   │ ┌──────────┐ │   │ ┌──────────┐ │
    │ │ App      │ │   │ │ App      │ │   │ │ App      │ │
    │ └────┬─────┘ │   │ └────┬─────┘ │   │ └────┬─────┘ │
    │ ┌────┴─────┐ │   │ ┌────┴─────┐ │   │ ┌────┴─────┐ │
    │ │ Envoy    │◄┼───┼►│ Envoy    │◄┼───┼►│ Envoy    │ │
    │ └──────────┘ │   │ └──────────┘ │   │ └──────────┘ │
    └──────────────┘   └──────────────┘   └──────────────┘
         Data Plane (sidecar proxies, mTLS traffic)
```

## Xüsusiyyətlər (Features)

### 1. mTLS (Mutual TLS)

Service mesh hər servisin öz identity-sinə (SPIFFE ID) sertifikat verir. Sertifikatlar qısa müddətli olur (default 24 saat) və avtomatik yenilənir.

- Application kodunda heç bir TLS konfiqurasiyası olmur
- Zero-trust network - hər istək avtomatik şifrələnir
- Identity-based authorization (RBAC)

### 2. Traffic Management

- **Canary deployment** - trafikin 10%-ni v2-yə yönləndir
- **A/B testing** - header-ə görə routing
- **Traffic mirroring** - production trafikini shadow-a kopyala
- **Fault injection** - süni latency/error inject et (chaos engineering)

### 3. Resilience

- **Retries** - uğursuz request-ləri avtomatik təkrarla
- **Timeouts** - slow service-ə timeout qoy
- **Circuit breakers** - fail olan endpoint-ləri izolyasiya et
- **Outlier detection** - unhealthy instance-ları cluster-dən çıxar

### 4. Observability

- **Distributed tracing** - Jaeger/Zipkin-ə avtomatik trace göndər
- **Metrics** - Prometheus-a RED metrics (Rate, Errors, Duration)
- **Access logs** - bütün L7 request-lər loglanır
- **Service graph** - Kiali ilə servis əlaqələri vizuallaşdırılır

### 5. Authorization Policies

Kim kimə zəng edə bilər? Mesh səviyyəsində təyin olunur: `order-service` yalnız `payment-service`-dən çağırıla bilər, `admin-service` yalnız admin namespace-dən.

## Istio vs Linkerd vs Consul Connect

| Xüsusiyyət | Istio | Linkerd | Consul Connect |
|------------|-------|---------|----------------|
| Proxy | Envoy (C++) | linkerd2-proxy (Rust) | Envoy |
| Resource | Ağır (~50MB/pod) | Yüngül (~10MB/pod) | Orta |
| Mürəkkəblik | Yüksək | Aşağı | Orta |
| Feature | Çox geniş | Sadə, əsaslar | Multi-DC |
| Mandar | CNCF | CNCF | HashiCorp |
| mTLS | Manual/auto | Default auto | Auto |
| Ambient mode | Var (Ambient) | Yox | Yox |

**Linkerd** - sadəlik və performans üçün. Rust proxy Envoy-dan 2-3 dəfə az yaddaş istifadə edir.
**Istio** - maksimum funksionallıq, böyük komanda idarə edə bilərsə.
**Consul Connect** - artıq Consul istifadə edirsənsə, multi-datacenter üçün.

## Sidecar vs Ambient Mesh

Klassik sidecar problemləri: hər pod-a +1 container (resource overhead), pod restart olmadan mesh update çətin, startup time artır.

**Istio Ambient Mode** (2024+): pod-a sidecar inject edilmir. `ztunnel` per-node L4 proxy mTLS təmin edir, `Waypoint proxy` opsional L7 funksiyalar üçün namespace-level. Resource overhead 3-5x azalır, amma production-da Linkerd sidecar modelindən az test olunub.

## YAML Nümunələri (YAML Examples)

### Istio VirtualService - Canary 90/10

```yaml
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: laravel-api
  namespace: production
spec:
  hosts:
    - laravel-api.production.svc.cluster.local
  http:
    - match:
        - headers:
            x-beta-user:
              exact: "true"
      route:
        - destination:
            host: laravel-api
            subset: v2
          weight: 100
    - route:
        - destination:
            host: laravel-api
            subset: v1
          weight: 90
        - destination:
            host: laravel-api
            subset: v2
          weight: 10
      retries:
        attempts: 3
        perTryTimeout: 2s
        retryOn: 5xx,reset,connect-failure
      timeout: 10s
```

### DestinationRule - Subsets + Circuit Breaker

```yaml
apiVersion: networking.istio.io/v1beta1
kind: DestinationRule
metadata:
  name: laravel-api
  namespace: production
spec:
  host: laravel-api.production.svc.cluster.local
  trafficPolicy:
    connectionPool:
      tcp:
        maxConnections: 100
      http:
        http2MaxRequests: 1000
        maxRequestsPerConnection: 10
    outlierDetection:
      consecutive5xxErrors: 5
      interval: 30s
      baseEjectionTime: 60s
      maxEjectionPercent: 50
    tls:
      mode: ISTIO_MUTUAL
  subsets:
    - name: v1
      labels:
        version: v1
    - name: v2
      labels:
        version: v2
```

### Laravel Deployment (no mesh code)

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-api-v1
  namespace: production
  labels:
    app: laravel-api
    version: v1
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
    spec:
      containers:
        - name: laravel
          image: registry.example.com/laravel-api:1.8.3
          ports:
            - containerPort: 9000
          env:
            - name: USER_SERVICE_URL
              value: "http://user-service.production.svc.cluster.local"
          # Diqqət: Laravel mTLS, retry, timeout haqqında heç nə bilmir
          # Bütün bunları Envoy sidecar edir
          readinessProbe:
            httpGet:
              path: /health
              port: 9000
            initialDelaySeconds: 5
```

### PeerAuthentication - Strict mTLS

```yaml
apiVersion: security.istio.io/v1beta1
kind: PeerAuthentication
metadata:
  name: default
  namespace: production
spec:
  mtls:
    mode: STRICT
```

### AuthorizationPolicy

```yaml
apiVersion: security.istio.io/v1beta1
kind: AuthorizationPolicy
metadata:
  name: laravel-api-access
  namespace: production
spec:
  selector:
    matchLabels:
      app: laravel-api
  rules:
    - from:
        - source:
            principals:
              - "cluster.local/ns/production/sa/api-gateway"
              - "cluster.local/ns/production/sa/order-service"
      to:
        - operation:
            methods: ["GET", "POST"]
```

## Trade-offs

### Üstünlüklər

1. **Polyglot support** - PHP, Java, Go fərqi yoxdur
2. **Zero application changes** - Laravel kodu dəyişmir
3. **Consistent policies** - bütün servislər üçün eyni qayda
4. **Automatic mTLS** - sertifikat idarəsi avtomatikdir
5. **Observability out of the box** - manual instrumentation az
6. **Progressive delivery** - canary, blue-green asan

### Çatışmazlıqlar

1. **Latency overhead** - hər hop ~1-2ms (2 sidecar = ~3-4ms əlavə)
2. **Resource overhead** - hər pod-da +50-100MB RAM, +0.1 CPU
3. **Operational complexity** - YAML CRD-lər çox, learning curve dik
4. **Debugging çətinliyi** - traffic sidecar-dan keçir, tcpdump fərqli
5. **Control plane SPOF** - istiod down olsa, yeni pod-lar mesh-ə qoşula bilmir
6. **Version upgrade mürəkkəbdir** - sidecar + control plane uyğunluğu

## Nə Vaxt İstifadə Etməli? (When to Use)

### İstifadə Et

- **≥20 microservice** var
- **Polyglot environment** (Laravel + Spring Boot + Go)
- **Zero-trust security** tələbi (finance, health)
- **Progressive delivery** (canary, A/B testing) lazımdır
- **Compliance** - PCI-DSS, HIPAA mTLS tələb edir
- **Observability** ciddi tələbdir

### İstifadə Etmə

- **<10 servis** var - library-based yanaşma daha sadə
- **Monolitik Laravel** - lazım deyil
- **Tək dildə yazılıb** - framework-daxili resilience kifayət edir
- **Komanda Kubernetes öyrənməyə hələ hazır deyil**
- **Latency critical** - hər ms önəmlidir (trading, real-time)

## API Gateway vs Service Mesh

Bunlar **tamamlayıcı** texnologiyalardır, rəqib deyil:

| Aspekt | API Gateway | Service Mesh |
|--------|-------------|--------------|
| Trafik istiqaməti | North-South (xaricdən daxilə) | East-West (servislər arası) |
| Primary user | External client | Internal service |
| Auth | JWT, API key, OAuth | mTLS, SPIFFE identity |
| Rate limiting | Per-user, per-IP | Per-service |
| Nümunə | Kong, Tyk, AWS API Gateway | Istio, Linkerd |
| Kim idarə edir | Platform team | Platform/SRE team |

Real arxitektura:
```
Internet → [API Gateway] → [Service Mesh (internal)] → Services
                            ↑ mTLS, retry, tracing
```

## Alternativlər (Alternatives)

- **Library-based** - Spring Cloud (Java), Steeltoe (.NET), go-kit (Go). Latency əlavə etmir, debug asan, amma hər dildə ayrı implementation və upgrade redeploy tələb edir.
- **gRPC built-in** - retry, deadline, load balancing, TLS daşıyır; yalnız gRPC servisləri üçün.
- **Proxyless Service Mesh (gRPC xDS)** - gRPC library birbaşa xDS-dən config alır, sidecar yoxdur. Google yanaşması, yalnız gRPC.

## Praktik Tapşırıqlar

**Q1: Service Mesh olmadan mikroservislərdə cross-cutting concerns-i necə həll edərdin?**
Library-based yanaşma: Spring Cloud (Java), Laravel package-ləri (PHP), go-kit (Go). Problem: hər dildə ayrıca implementation, upgrade bütün servisləri deploy etməyi tələb edir, polyglot environment-də uyğunsuzluq. Service Mesh bu funksiyaları infrastructure layer-ə çıxarır - application kodu toxunulmaz qalır.

**Q2: Sidecar proxy pattern necə işləyir?**
Hər pod-a Envoy (və ya linkerd2-proxy) container inject edilir. `iptables` qaydaları pod-a gələn/gedən bütün trafiki sidecar-a yönləndirir. Application `localhost:8080`-a istək göndərir kimi düşünür, amma əslində sidecar-dan keçir. Sidecar mTLS, retry, routing tətbiq edib hədəf pod-un sidecar-ına göndərir. Application bu proses haqqında heç nə bilmir.

**Q3: Istio control plane komponentləri nələrdir?**
`istiod` - v1.5+ birləşmiş binary: Pilot (konfiqurasiya push, xDS API), Citadel (sertifikat idarəsi, CA), Galley (konfiqurasiya validation və distribution). Əlavə komponentlər: Ingress Gateway (xaricdən daxilə), Egress Gateway (daxildən xaricə). Data plane - hər pod-da Envoy sidecar.

**Q4: mTLS service mesh-də necə həyata keçir?**
Control plane (Citadel) hər servisə SPIFFE formatında identity verir: `spiffe://cluster.local/ns/production/sa/laravel-api`. Qısa müddətli sertifikat (default 24h) pod-a mount edilir və Envoy istifadə edir. Service A -> Service B zəngində hər iki sidecar sertifikatları exchange edir və identity-ni yoxlayır. Laravel və ya Spring Boot kodu TLS haqqında bilmir. Sertifikat rotation avtomatikdir.

**Q5: Istio VirtualService və DestinationRule fərqi nədir?**
**VirtualService** - trafik routing qaydalarını təyin edir: hansı host-a, subset-ə gedir, weight, retry, timeout, header-based routing. "Necə yönləndirmək" sualına cavab verir.
**DestinationRule** - hədəf servis üçün policy təyin edir: subset-lər (v1, v2 labels), load balancing algorithm (round-robin, least-conn), circuit breaker, connection pool, TLS mode. "Hədəfə necə davranmaq" sualına cavab verir.
Canary deployment üçün hər ikisi lazımdır.

**Q6: Service Mesh istifadə etməyin dezavantajları nələrdir?**
- **Latency** - hər hop +1-2ms (iki sidecar arası zəngdə +3-4ms)
- **Resource overhead** - hər pod +50-100MB RAM (Envoy), Linkerd ~10MB
- **Operational complexity** - CRD-lər çox (VirtualService, DestinationRule, Gateway, AuthorizationPolicy), YAML konfiqurasiya çoxalır
- **Debugging çətinləşir** - istək application-dan gedir, sidecar-a, network-ə, uzaq sidecar-a, uzaq application-a; problem haradadır?
- **Control plane dependency** - istiod down olsa, yeni pod schedule edilə bilmir (sidecar inject olmur)
- **Version skew** - sidecar və control plane uyğunluğunu izləmək lazımdır

**Q7: Ambient Mesh klassik sidecar-dan necə fərqlənir?**
Sidecar model-də hər pod-a Envoy inject edilir - resource overhead və mesh update pod restart tələb edir. Ambient mode-da (Istio 1.18+): `ztunnel` per-node L4 proxy (DaemonSet) mTLS təmin edir, `Waypoint proxy` namespace-level L7 funksiyalar üçün opsional. Resource overhead 3-5x azalır, mesh upgrade pod-u touch etmir. Dezavantaj: daha yeni, production readiness az test olunub.

**Q8: API Gateway və Service Mesh eyni funksiyanı görürmü?**
Xeyr, tamamlayıcıdırlar. **API Gateway** north-south trafiki idarə edir - xarici client-lərin sistemə girişi. JWT auth, rate limiting per-user, API versioning, developer portal. Nümunə: Kong, Tyk. **Service Mesh** east-west trafiki - daxili servislər arası. mTLS, retry, circuit breaker, service-level authorization. Production arxitekturasında hər ikisi istifadə olunur: Internet → API Gateway → Service Mesh → Services.

**Q9: Laravel servis mesh-də necə deploy edilir?**
Laravel container-ında heç bir mesh-specific kod yoxdur - adi Kubernetes Deployment. Namespace-ə `istio-injection=enabled` label qoyulur, Istio admission controller avtomatik Envoy sidecar inject edir. Laravel `http://user-service` kimi sadə URL-ə zəng edir - Envoy DNS resolve edir, mTLS açır, retry tətbiq edir, hədəf pod-a göndərir. Laravel-də `.env`-də `USER_SERVICE_URL` kifayətdir. Health check, Prometheus metrics, Jaeger trace - hamısı sidecar tərəfindən avtomatik. Laravel kodunda Istio SDK və ya library yoxdur.

**Q10: Service Mesh nə zaman overkill sayılır?**
- **Monolitik application** - tək servis mesh-ə ehtiyac duymur
- **<10 mikroservis** - library-based yanaşma daha sadə və ucuz
- **Tək dildə ecosystem** - Spring Cloud Java-da bütün mesh funksiyalarını verir
- **Yüngül komanda** - 1-2 DevOps mühəndisi Istio-nu production-da saxlaya bilməz
- **Latency-critical (trading, gaming)** - 1-2ms hop kritik ola bilər
- **Short-lived project** - 6 ay işləyəcək proyekt üçün Istio öyrənmək iqtisadi deyil

Bu hallarda: Laravel `guzzlehttp/guzzle` + retry middleware + Sentry tracing kifayətdir.

## Praktik Baxış

1. **Start small** - namespace-by-namespace rollout et, bütün cluster-i bir anda çevirmə
2. **Linkerd ilə başla** - sadəlik və performans; lazım olsa Istio-ya keç
3. **Strict mTLS məcburidir** - permissive mode yalnız migration üçün, production-da STRICT
4. **Resource limits qoy** - Envoy sidecar üçün CPU/memory limit (50m CPU, 128Mi RAM)
5. **Timeout hər yerdə** - default infinite təhlükəlidir, 10-30s qoy
6. **Retry budget** - infinite retry etmə (attempts: 3, max)
7. **Circuit breaker aktiv et** - `outlierDetection` ilə bad pod-ları ayır
8. **AuthorizationPolicy default deny** - namespace-ə default deny, sonra allow rules əlavə et
9. **Traffic mirror prod-dan** - yeni versiyanı real trafikde test et (response ignored)
10. **Canary ilə deploy et** - 1% → 10% → 50% → 100% (argocd rollouts, flagger)
11. **Observability stack qur** - Prometheus + Grafana + Jaeger + Kiali
12. **Control plane HA** - istiod minimum 2 replica, PodDisruptionBudget qoy
13. **Version skew idarə et** - control plane və sidecar-lar bir minor versiya arasında olmalıdır
14. **Chaos testing** - fault injection ilə retry/timeout-u test et
15. **Exclude non-HTTP services** - database connection-lar mesh-dən kənar saxla
16. **GitOps ilə idarə et** - VirtualService/DestinationRule-lər git-də, audit trail üçün


## Əlaqəli Mövzular

- [Microservices](10-microservices.md) — service mesh-in əsas müştərisi
- [Service Discovery](29-service-discovery.md) — mesh-də service registry
- [API Gateway](02-api-gateway.md) — north-south vs east-west traffic
- [Distributed Tracing](91-distributed-tracing-deep-dive.md) — mesh observability
- [Circuit Breaker](07-circuit-breaker.md) — mesh-də sidecar circuit breaker
