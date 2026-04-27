# Service Discovery (Lead ⭐⭐⭐⭐)

## İcmal
Service discovery, microservices arxitekturasında service-lərin bir-birini necə tapacağını həll edən mexanizmdir. Dinamik mühitlərdə (Kubernetes, Docker Swarm, cloud) service-lərin IP adresi daim dəyişir — hard-coded IP konfiqurasiya etmək mümkün deyil. Service discovery bu problemi həll edir: hər service özünü qeydiyyatdan keçirir, digər service-lər adla sorğu edir.

## Niyə Vacibdir
Kubernetes-in kube-dns, Consul, Eureka, etcd — microservices dünyasında service discovery fundamental altyapıdır. "Microservices dizayn edin" dedikdə service discovery avtomatik gəlir. Lead mühəndis client-side vs server-side discovery fərqini, health checking mexanizmini, DNS-based vs registry-based seçimi izah edə bilir.

## Əsas Anlayışlar

### 1. Service Discovery Problemi
```
Microservices mühitindən əvvəl:
  service_b_ip = "10.0.1.5"  // hard-coded config

Microservices/container mühitdə:
  Service B restart olur → yeni IP: 10.0.2.8
  Service B scale: 3 instance: 10.0.2.8, 10.0.2.9, 10.0.2.10
  Blue/green deploy: köhnə IP-lər silinir, yenilər gəlir

Hard-coded IP = BROKEN
```

### 2. Service Registry
Mərkəzi qeydiyyat sistemi:
```
Service B starts → registers: {
    name: "order-service",
    host: "10.0.2.8",
    port: 8080,
    health: "/health",
    metadata: {version: "1.2.3", region: "eu-west"}
}

Service A wants to call Service B:
→ Registry: "order-service haradadır?"
→ Registry: "10.0.2.8:8080, 10.0.2.9:8080, 10.0.2.10:8080"
→ Service A bir instance seçib çağırır

Service B dies → Registry-dən silinir
Service A sorğu etdikdə → yalnız canlı instance-lar alır
```

### 3. Client-Side Discovery
```
Service A (client):
  1. Registry-dən service-B-nin instance list-ini al
  2. Load balancing alqoritmi ilə bir instance seç
  3. Birbaşa seçilmiş instance-a call et

Flow:
  Service A → Registry: "order-service?"
  Registry → Service A: [10.0.2.8:8080, 10.0.2.9:8080]
  Service A (round robin) → 10.0.2.8:8080

  Pros: Simple infrastructure, client control
  Cons: Client library registry-ni bilməlidir, every language needs library
  
  Use case: Netflix Ribbon (Java Eureka client)
```

### 4. Server-Side Discovery
```
Service A → Load Balancer/Router: "call order-service"
Router: Registry-yə baxır
Router: Instance seçir
Router: Seçilmiş instance-a forward edir

Flow:
  Service A → [AWS ALB / Nginx / Envoy] → order-service
  Load Balancer registry sorğu edir (ya da DNS)
  Client registry-ni bilmir

  Pros: Client sadədir (DNS call), dil-agnostik
  Cons: Load balancer hop əlavə edir, SPOF riski

  Use case: Kubernetes Service, AWS ELB, Consul + Envoy
```

### 5. DNS-Based Service Discovery
```
Kubernetes nümunəsi:
  Service name: order-service (namespace: production)
  DNS: order-service.production.svc.cluster.local
  
  nslookup order-service.production.svc.cluster.local
  → 10.96.45.123 (Kubernetes Service ClusterIP)
  
  ClusterIP → kube-proxy → actual pod IPs (round robin)

Kubernetes headless service:
  clusterIP: None
  DNS: order-service.production.svc.cluster.local
  → Returns individual pod IPs directly
  Client: öz load balancing edir

Consul DNS:
  order-service.service.consul
  → Returns healthy service instances
```

### 6. Consul Service Discovery
```
Architecture:
  Agent: Hər node-da çalışır
  Server: Raft consensus cluster (3 ya 5 server)

Registration:
  POST http://localhost:8500/v1/agent/service/register
  {
    "ID": "order-service-1",
    "Name": "order-service",
    "Address": "10.0.2.8",
    "Port": 8080,
    "Check": {
      "HTTP": "http://10.0.2.8:8080/health",
      "Interval": "10s",
      "Timeout": "3s"
    },
    "Tags": ["v1.2.3", "eu-west"],
    "Meta": {"version": "1.2.3"}
  }

Discovery:
  GET http://consul:8500/v1/health/service/order-service?passing=true
  → Yalnız healthy instance-lar
```

### 7. Kubernetes Service Discovery
```yaml
# Service definition
apiVersion: v1
kind: Service
metadata:
  name: order-service
  namespace: production
spec:
  selector:
    app: order-service  # Bu label-i daşıyan pod-lara route et
  ports:
    - port: 80
      targetPort: 8080

# Pod scale: 3 replica
# Service həmişə eyni DNS-ə sahib: order-service.production
# kube-proxy: ClusterIP → 3 pod-a round-robin
```

**Kubernetes discovery mechanisms:**
- **ClusterIP Service**: Stable internal IP + DNS
- **Headless Service**: Pod IPs directly (StatefulSets)
- **ExternalName**: External DNS CNAME
- **NodePort/LoadBalancer**: External access

### 8. Health Checking
```
Active checks (registry initiates):
  Registry → GET /health → Service
  Interval: 10s
  Timeout: 3s
  Failure threshold: 3 consecutive fails

Passive checks (traffic-based):
  Router: error rate > 50%? → unhealthy
  Circuit breaker integration

Health check types:
  HTTP: GET /health → 200 OK
  TCP: Connection established
  gRPC: gRPC health check protocol
  Script: Custom shell script
  TTL: Service must send heartbeat every N seconds
```

**Health endpoint best practices:**
```json
GET /health/live   → Is the app alive? (liveness probe)
{"status": "alive"}

GET /health/ready  → Can it serve traffic? (readiness probe)
{"status": "ready", "db": "ok", "redis": "ok", "queue_lag": 0}

GET /health/detail → Full diagnostics (not used by LB)
{
  "status": "healthy",
  "version": "1.2.3",
  "db_connections": 45,
  "cache_hit_ratio": 0.95
}
```

### 9. Self-Registration vs Third-Party Registration
**Self-Registration:**
```
Service starts → calls Registry API to register itself
Service stops → calls Registry API to deregister
Pros: Service controls its own registration
Cons: Service must know Registry endpoint, deregistration may fail on crash
```

**Third-Party Registration (Kubernetes approach):**
```
Orchestrator (Kubernetes) registers pods in service registry
Pod created → kube-controller-manager → updates Endpoints
Pod deleted → automatically removed
Pros: Service unaware of registry, crash safe
Cons: Orchestrator must support this
```

### 10. Service Discovery with Service Mesh
```
Istio service discovery:
  - Envoy sidecar hər pod-da
  - Pilot: Control plane, xDS API ilə Envoy-ları konfiqurasiya edir
  - Service registry: Kubernetes API from Pilot

Client-side (Envoy as sidecar):
  Service A calls "order-service"
  Envoy intercepts → Pilot-dan latest endpoints
  Envoy: load balancing, circuit breaking, mTLS
  → Seçilmiş Order Service pod-una

Benefits vs pure service registry:
  - mTLS automatic
  - Circuit breaking automatic
  - Retry logic built-in
  - Observability (traces, metrics) automatic
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Service-lər necə bir-birini tapır?" sualını erkən soruşun
2. Environment-ı soruşun: Kubernetes? Bare metal? Cloud?
3. DNS-based (Kubernetes) vs Registry-based (Consul) seçimini müzakirə et
4. Health check mexanizmini izah et
5. Service Mesh olduqda sidecar discovery-ni qeyd et

### Ümumi Namizəd Səhvləri
- Microservices dizaynında service discovery-ni yaddan çıxarmaq
- Hard-coded service URL-lər (environment variable belə yanlış)
- Health check endpoint-lərini unutmaq
- Client-side vs server-side fərqini bilməmək
- Registry-nin özünün SPOF olmasını nəzərə almamaq

### Senior vs Architect Fərqi
**Senior**: Consul ya Kubernetes Service-i konfigurasiya edir, health check qurur, basic service discovery tətbiq edir.

**Architect**: Service discovery failure mode-larını analiz edir (registry down → stale cache?), multi-datacenter service discovery (Consul Federation, multi-cluster Kubernetes), service discovery-nin consistency guarantees (eventual vs strong), service mesh-in service discovery ilə inteqrasiyasını planlaşdırır, observability (hangi service neyi call edir, dependency graph).

## Nümunələr

### Tipik Interview Sualı
"Design the service communication layer for a microservices platform on Kubernetes."

### Güclü Cavab
```
Kubernetes microservices service discovery:

Setup:
- 10 microservices on Kubernetes
- 3 replicas each (30 pods total)
- Pods restart frequently (deploy, crash, scale)

Service Discovery: Kubernetes-native DNS

Each service defined as Kubernetes Service:
  name: user-service
  DNS: user-service.production.svc.cluster.local
  ClusterIP: stable (10.96.x.x)
  → Backed by healthy pods (automatic endpoint management)

Code (no hard-coded IPs):
  http://user-service/api/users  (within cluster)
  http://user-service.production.svc.cluster.local/api/users (full)
  
  Environment variable (Kubernetes-injected):
  USER_SERVICE_URL=http://user-service.production

Health Checks:
  Liveness probe: /health/live → pod restart if fails
  Readiness probe: /health/ready → removed from Service endpoints if fails

  readinessProbe:
    httpGet:
      path: /health/ready
      port: 8080
    initialDelaySeconds: 10
    periodSeconds: 10
    failureThreshold: 3

Service Mesh (Istio) for advanced needs:
  - mTLS between services (zero-trust networking)
  - Circuit breaking (automatic with Envoy)
  - Distributed tracing (Jaeger auto-instrumentation)
  - Traffic splitting: 90% v1, 10% v2 (canary)
  - Retry policies (3 retries, 500ms timeout)

External service discovery:
  - External services (DBs, 3rd party) → Kubernetes ExternalName
  - or: ServiceEntry in Istio
  - DNS: external-db.svc.cluster.local → actual external hostname

Service registry (without Kubernetes):
  - Alternative: Consul
  - Agent per node
  - Service auto-registers on start
  - DNS: order-service.service.consul
  - Health: HTTP check every 10s
  - Failing service: automatically deregistered
```

### Kubernetes Service DNS Flow
```
[Service A pod]
      │ call http://order-service/api/orders
      ▼
[kube-dns (CoreDNS)]
  order-service.production → 10.96.45.123
      │
[kube-proxy (iptables rules)]
  10.96.45.123 → [Pod 10.0.2.8] (round-robin)
              → [Pod 10.0.2.9]
              → [Pod 10.0.2.10]
      │
[Order Service Pod]
```

## Praktik Tapşırıqlar
- Kubernetes Service + Deployment qurun, DNS-lə call edin
- Consul agent qurun, service register edin
- Readiness probe failing: endpoint-dən çıxarıldığını verify edin
- Istio kurun, service-to-service mTLS test edin
- kube-dns resolution tracing (kubectl exec → nslookup)

## Əlaqəli Mövzular
- [14-api-gateway.md] — Gateway ilə service discovery
- [16-circuit-breaker.md] — Health check → circuit breaking
- [04-load-balancing.md] — Service-level load balancing
- [20-monitoring-observability.md] — Service dependency tracking
- [03-scalability-fundamentals.md] — Dynamic scaling
