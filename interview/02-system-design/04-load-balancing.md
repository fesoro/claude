# Load Balancing Strategies (Senior ⭐⭐⭐)

## İcmal
Load balancing, gələn trafiki bir neçə server arasında paylayan komponentdir. Yalnız trafikin bölünməsi deyil — health checking, SSL termination, session persistence, rate limiting kimi kritik funksiyaları da yerinə yetirir. Interview-larda load balancer haqqında dərindən danışa bilmək, sistemin həqiqətən production-ready olduğunu göstərir.

## Niyə Vacibdir
Hər distributed sistem load balancing tələb edir. Google, Amazon, Cloudflare kimi şirkətlər öz load balancer-larını yazır. Senior mühəndis load balancer seçimini — L4 vs L7, hardware vs software, sticky session vs stateless — müzakirə edə bilməlidir. Bu mövzu demək olar ki, hər sistem dizayn interview-unda bir şəkildə çıxır.

## Əsas Anlayışlar

### 1. Load Balancer Növləri

**L4 (Transport Layer) Load Balancer**
- TCP/UDP səviyyəsində işləyir
- HTTP content-ini görmür
- Ultra-low latency (sub-ms)
- Daha az CPU tələb edir
- Use case: DNS, gaming, VoIP, raw TCP

**L7 (Application Layer) Load Balancer**
- HTTP/HTTPS, WebSocket, gRPC başa düşür
- URL path, headers, cookies-ə görə routing
- SSL termination aparır
- Request manipulation edə bilir (header əlavə et, URL rewrite)
- Use case: Web apps, APIs, microservices

**Hardware vs Software:**
- Hardware (F5, Citrix): Yüksək performance, baha, inflexible
- Software (HAProxy, Nginx, Envoy): Ucuz, flexible, cloud-native

### 2. Load Balancing Alqoritmləri

**Round Robin**
```
Request 1 → Server A
Request 2 → Server B
Request 3 → Server C
Request 4 → Server A (yenidən başla)
```
- Sadədir, bərabər paylanır
- Problem: Serverlərin gücü fərqlidirsə, zəif server yüklənir

**Weighted Round Robin**
```
Server A (weight 3): 3 request
Server B (weight 1): 1 request
Server C (weight 2): 2 request
```
- Fərqli gücde serverlərdə faydalı
- Blue/green deployment-da istifadə olunur

**Least Connections**
```
Server A: 10 active connections
Server B: 5 active connections  ← növbəti request buraya
Server C: 8 active connections
```
- Uzun müddətli connection-larda (WebSocket, streaming) daha ədalətli
- Counting overhead var

**Least Response Time**
- Ən az latency göstərən servera yönləndir
- Health check + response time monitoring lazımdır

**IP Hash**
```
hash(client_ip) % server_count = server_index
```
- Eyni client həmişə eyni servera gedir (sticky session olmadan)
- Problem: Server sayı dəyişdikdə bütün mapping pozulur
- Consistent hashing bu problemi həll edir

**Random**
- Sadə, stateless
- Az sayda server olduqda qeyri-bərabər paylanma riski

**Resource Based (Adaptive)**
- Serverin CPU/memory-sinə görə yönləndirir
- AWS ALB target weighting bu yanaşmadan istifadə edir

### 3. Health Checking
```
Active health check:
  LB → GET /health → Server (hər 10 saniyə)
  Response: 200 OK → healthy
  Response: 5xx / timeout → unhealthy → traffic kəsilir

Passive health check:
  LB real request-ləri izləyir
  3 ardıcıl xəta → server disabled
```

**Health check endpoint:**
```json
GET /health
{
  "status": "healthy",
  "db": "connected",
  "redis": "connected",
  "version": "1.2.3"
}
```

### 4. SSL/TLS Termination
```
Client (HTTPS) → Load Balancer (SSL terminates) → Backend (HTTP)
```
- SSL handshake CPU-intensive-dir, LB-də aparılır
- Backend-lər HTTP ilə işləyir (daha sürətli)
- Backend-ə güvənilən şəbəkədə HTTP göndərmək kifayət edir
- End-to-end encryption lazımdırsa: mTLS istifadə olunur

### 5. Session Persistence (Sticky Sessions)
**Problem**: User login etdi, Session A server-da saxlanır.
Növbəti request B serverə gedir → session yoxdur.

**Həll 1: Sticky Sessions (Cookie-based)**
```
LB user-ə cookie göndərir: SERVERID=server_a
Sonrakı request-lər server_a-ya yönləndirilir
```
- Problem: Server A down olsa session itirilir
- Scale etmək çətindir

**Həll 2: Stateless + External Session Store (Tövsiyə olunan)**
```
App Server → Redis (session store)
Hər server eyni Redis-dən session oxuyur
```
- Load balancer sadə round-robin istifadə edə bilər
- Server sayı artırıla bilər

### 6. Global vs Local Load Balancing

**Local Load Balancing:**
- Tək region/datacenter daxilinde
- Nginx, HAProxy, AWS ALB

**Global Load Balancing (GSLB):**
- Birdən çox region arasında
- DNS-based: User-in IP-nə görə ən yaxın datacenter-ə yönləndir
- Anycast: Eyni IP, ən yaxın PoP cavab verir (Cloudflare)
- Latency-based routing (AWS Route 53)

### 7. Load Balancer Redundancy
```
Active-Passive:
  Primary LB ── Active
  Secondary LB ── Standby (heartbeat monitor)
  Primary fail → Secondary IP devralır (VIP failover)

Active-Active:
  LB1 ── Traffic 50%
  LB2 ── Traffic 50%
  DNS round-robin ilə
  Daha yüksək availability
```

### 8. Rate Limiting at Load Balancer
```nginx
# Nginx rate limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/s;

location /api/ {
    limit_req zone=api burst=200 nodelay;
}
```
- Application layer-a çatmadan DDoS/abuse-u bloklayır
- Token bucket və ya leaky bucket alqoritmi

### 9. Circuit Breaker at LB Level
- Backend-lər error threshold keçəndə LB trafiği kəsir
- Fast fail — client uzun timeout gözləmir
- Envoy proxy built-in circuit breaking dəstəkləyir

### 10. Load Balancer Metrics
Monitorinq olunmalı məlumatlar:
```
- Active connections per backend
- Request rate (RPS)
- Backend response time (p50, p95, p99)
- Error rate (4xx, 5xx)
- Backend pool size (healthy/unhealthy count)
- SSL handshake rate
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Sistem tiplini soruşun: Web app? Microservices? Real-time?
2. "LB əlavə edirəm" deyəndə hansı növ olduğunu izah et
3. Sticky session probleminə toxun, stateless həll təklif et
4. Health check mexanizmini qeyd et
5. LB-nin özünün single point of failure olduğunu soruş və həllini izah et

### Ümumi Namizəd Səhvləri
- Load balancer-in özünün SPOF olduğunu unutmaq
- SSL termination haqqında danışmamaq
- Sticky session probleminə toxunmamaq
- L4 vs L7 fərqini bilməmək
- Health check detallarını izah etməmək

### Senior vs Architect Fərqi
**Senior**: Doğru load balancing alqoritmini seçir, sticky session problemini həll edir, health check konfiqurasiya edir.

**Architect**: Global traffic routing, multi-region failover, latency-based routing, anycast, cost vs performance trade-off, CanaryDeployment with weighted routing — hər birini əsaslandırır.

## Nümunələr

### Tipik Interview Sualı
"Design the load balancing layer for a payment processing API handling 50K RPS."

### Güclü Cavab
```
Payment API üçün tələblər:
- High availability: 99.99%
- Low latency: p99 < 50ms
- Security: SSL/TLS, PCI compliance
- Stateless: sticky session yoxdur (JWT auth)

Arxitektura:
Clients
  │ HTTPS
Global DNS (Route 53 latency-based)
  │
  ├── US-EAST LB Cluster
  └── EU-WEST LB Cluster

LB Cluster (active-active):
- 2x L7 load balancer (Nginx Plus / AWS ALB)
- Virtual IP with keepalived failover
- SSL termination at LB
- Backend: HTTP/1.1 + keep-alive

Algorithm: Least Connections
- Payment requests ola bilər ki, fərqli müddət tutsun
- Round Robin düzgün paylamaz
- Least Connections = aktiv işi az olan servera yönləndir

Health Check:
- Hər 5 saniyə: GET /health/ready
- 2 ardıcıl fail → server pool-dan çıxarılır
- DB, Redis status health endpoint-ə daxildir

Rate Limiting:
- Per-user: 100 req/min
- Per-IP: 1000 req/min
- Burst: 200 instant requests
```

### Arxitektura Diaqramı
```
Internet
    │
[Anycast DNS / CDN]
    │
[L4 LB: DDoS protection, TCP termination]
    │
[L7 LB: SSL termination, routing, rate limit]
    │      │        │
[App-1] [App-2] [App-3]
    │      │        │
    └──────┼────────┘
         [Redis]     [PostgreSQL Primary]
                          │
                   [PG Replicas x2]
```

## Praktik Tapşırıqlar
- Nginx-i load balancer olaraq konfiqurasiya edin (least_conn + health check)
- HAProxy stats dashboard qurun
- AWS ALB + Target Group + Health Check konfiqurasiya edin
- Sticky session-u aradan qaldıraraq Redis session store qoşun
- Canary deployment: 90/10 weighted traffic split konfiqurasiya edin

## Əlaqəli Mövzular
- [03-scalability-fundamentals.md](03-scalability-fundamentals.md) — Scale etmə prinsipləri
- [09-rate-limiting.md](09-rate-limiting.md) — Rate limiting strategiyaları
- [16-circuit-breaker.md](16-circuit-breaker.md) — Circuit breaker pattern
- [11-consistent-hashing.md](11-consistent-hashing.md) — Consistent hashing
- [15-service-discovery.md](15-service-discovery.md) — Service discovery
