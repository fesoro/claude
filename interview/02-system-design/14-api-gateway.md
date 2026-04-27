# API Gateway Patterns (Lead ⭐⭐⭐⭐)

## İcmal
API Gateway, client-lərlə backend service-lər arasında single entry point rolunu oynayan komponentdir. Routing, authentication, rate limiting, load balancing, SSL termination, request/response transformation — hamısını mərkəzləşdirir. Microservices arxitekturasında API Gateway olmadan client-lər onlarla service-dən istifadə etməli olur. Bu mövzu microservices, distributed system interview-larında mütləq gəlir.

## Niyə Vacibdir
Amazon API Gateway, Kong, Nginx, Envoy, Traefik — hər şirkət API Gateway istifadə edir. Yalnız "proxy" kimi düşünmək yanlışdır. API Gateway cross-cutting concerns-i (auth, logging, rate limiting) bütün service-lərdə duplicate etməkdən qurtarır. Lead mühəndis Gateway vs Service Mesh fərqini, BFF pattern-ni, Gateway aggregation-u izah edə bilər.

## Əsas Anlayışlar

### 1. API Gateway Funksiyaları

**Request Routing:**
```
GET /api/users/123     → User Service
GET /api/orders/456    → Order Service
POST /api/payments     → Payment Service
GET /api/products      → Product Service (with cache)
```

**Authentication & Authorization:**
```
Client → JWT token → Gateway validates → Service
Gateway: Token valid? Scope yetərli?
Service: Auth logic artıq lazım deyil
Centralized auth → DRY principle
```

**Rate Limiting:**
```
Gateway: User X artıq 1000 req/min etdi → 429
Service-lər: Rate limiting logic lazım deyil
```

**Request Aggregation:**
```
Client 1 request:
GET /api/dashboard

Gateway 3 request edir:
→ User Service: GET /users/123
→ Orders Service: GET /orders?user=123
→ Notifications: GET /notifications?user=123

Response-ları birləşdirir, client-ə qaytarır
Client network round-trips: 1 (3 əvəzinə)
```

**Protocol Translation:**
```
Client: REST/JSON
Gateway → Service A: gRPC
Gateway → Service B: SOAP (legacy)
Gateway → Service C: GraphQL
Client yalnız REST görür
```

### 2. API Gateway vs Reverse Proxy vs Load Balancer

```
Reverse Proxy (Nginx):
  - URL-ə görə backend-ə yönləndir
  - SSL termination
  - Static file serving
  - Basic load balancing

Load Balancer (HAProxy, AWS ALB):
  - Multiple backend instance-a trafik paylaşdır
  - Health check
  - Algorithm-based routing

API Gateway (Kong, AWS API GW, Apigee):
  - Reverse proxy + Load balancer features
  + Authentication/Authorization
  + Rate limiting
  + API key management
  + Request/response transformation
  + Analytics
  + Circuit breaking
  + Caching
```

### 3. BFF (Backend for Frontend) Pattern
```
Problem:
  Mobile app: Az data lazımdır (bandwidth saxla)
  Web app: Daha çox data, richer UI
  Single API: Hamını eyni şəkildə serve edir → inefficient

Həll: BFF Pattern
  Mobile BFF → Mobile-optimized responses (compact JSON)
  Web BFF    → Web-optimized responses (full data)
  3rd party  → Partner API (rate limited, versioned)

       [Mobile App]     [Web App]    [Partner App]
            │                │              │
      [Mobile BFF]    [Web BFF]     [Partner API GW]
            │                │              │
    [User Svc] [Order Svc] [Product Svc] [...]
```

**BFF hər frontend team-nin özü idarə edir:**
- Mobile team → Mobile BFF
- Web team → Web BFF
- Decoupled, independently deployable

### 4. Gateway Aggregation Pattern
```
// Pseukod - Gateway request aggregation
async function dashboardHandler(userId) {
    const [user, orders, notifications, balance] = await Promise.all([
        userService.get(userId),
        orderService.getRecent(userId, limit=5),
        notificationService.getUnread(userId),
        paymentService.getBalance(userId)
    ]);
    
    return {
        user: user,
        recentOrders: orders,
        unreadCount: notifications.count,
        balance: balance.amount
    };
}
```

**Fanout circuit breaking:**
```
Əgər payment service timeout → partial response qaytar
{
  "user": {...},
  "recentOrders": [...],
  "balance": null,  // failed, null returned
  "_partial": true  // client bilir partial data
}
```

### 5. API Versioning via Gateway
```
/api/v1/users → v1 User Service
/api/v2/users → v2 User Service (breaking change)

Header-based versioning:
Accept: application/vnd.myapi.v2+json
→ Gateway routes to v2

Subdomain versioning:
v1.api.example.com → v1 cluster
v2.api.example.com → v2 cluster
```

### 6. Request/Response Transformation
```
Client request (REST):
POST /api/orders
{"items": [{"sku": "ABC", "qty": 2}]}

Gateway transforms to Order Service format:
{
  "order": {
    "items": [...],
    "timestamp": "2026-04-26T10:00:00Z",
    "request_id": "req_xyz"  // gateway adds
  }
}

Service response:
{"orderId": "ord_123", "status": "pending"}

Gateway transforms back to client format:
{"order_id": "ord_123", "status": "pending", "links": {...}}
```

### 7. Service Mesh vs API Gateway
```
API Gateway:
  - North-South traffic (external → internal)
  - Client-facing API
  - Coarse-grained: per-API policies
  - Single entry point

Service Mesh (Istio, Linkerd, Consul Connect):
  - East-West traffic (service ↔ service)
  - Internal microservice communication
  - Fine-grained: per-pod, per-connection policies
  - Sidecar proxy (Envoy)
  - mTLS, circuit breaking, retry, observability

Combined:
  External → API Gateway → Service Mesh → Services
```

### 8. API Gateway Authentication Flow
```
1. Client → POST /auth/login → credentials
2. Auth Service → JWT token
3. Client → GET /api/users (Bearer: JWT)
4. Gateway → JWT decode, verify signature, check expiry
5. Gateway → Forward request + X-User-Id: 123 header
6. Service → Trust X-User-Id (already authenticated)
7. Service → No need for JWT library

Mərkəzləşdirilmiş auth:
+ Service-lər auth logic-i implement etmir
+ Token format dəyişsə yalnız Gateway yenilənir
- Gateway bottleneck ola bilər
```

### 9. API Gateway Challenges
**Single Point of Failure:**
```
Həll: Gateway cluster (multiple instances + LB)
Həll: Multi-region Gateway
```

**Performance Overhead:**
```
Her request üçün: Auth check, rate limit check, logging
Overhead: 5-20ms per request
Həll: Async logging, Redis cache for auth, compiled rules
```

**Configuration Management:**
```
100 service × 10 routes = 1000 route konfigurasyonu
Həll: GitOps (config repo), Kong Admin API, AWS CDK
```

**Gateway Evolution Bottleneck:**
```
Bütün API dəyişikliklər Gateway-dən keçir
→ Gateway team bottleneck
Həll: Self-service gateway config (Kong declarative, AWS API GW Terraform)
```

### 10. Popular Gateways

**Kong Gateway (Open Source):**
- Plugin-based architecture (Lua/Go plugins)
- PostgreSQL backend for config
- Kubernetes: Kong Ingress Controller
- Admin API: declarative config

**AWS API Gateway:**
- Serverless, managed
- Lambda integration (direct invoke)
- Usage plans, API keys
- WebSocket support

**Nginx:**
- Lightweight, high performance
- OpenResty: Lua scripting
- Manual config, less feature-rich

**Envoy (Istio-based):**
- gRPC native
- Advanced observability (traces, metrics)
- Service mesh integration

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Single entry point" deyəndə bütün funksiyaları sırala
2. BFF pattern-ı nə zaman lazım olduğunu izah et
3. Gateway vs Service Mesh fərqini müzakirə et
4. Gateway-in özünün SPOF olmasını qeyd et, həll göstər
5. Request aggregation-ı nümunə ilə izah et

### Ümumi Namizəd Səhvləri
- "Gateway sadəcə proxy-dir" demək
- BFF pattern-ı bilməmək
- Gateway-in performance overhead-ini nəzərə almamaq
- Service Mesh ilə fərqini bilməmək
- Gateway cluster/HA dizaynını unutmaq

### Senior vs Architect Fərqi
**Senior**: Gateway routing, auth, rate limiting konfigurasiya edir, BFF bilir.

**Architect**: Gateway-i organizational boundary kimi dizayn edir (team-ə görə BFF), Gateway evolution strategy planlaşdırır, Gateway vs Service Mesh layering qərarını verir, multi-region Gateway topology (latency + failover), Gateway-in observability (distributed tracing, error budgets) dizaynı.

## Nümunələr

### Tipik Interview Sualı
"Design the API layer for a microservices e-commerce platform with mobile and web clients."

### Güclü Cavab
```
E-commerce API Gateway Design:

Clients:
- Mobile App (iOS/Android): Bandwidth sensitive
- Web App: Rich data
- Partner API: Rate limited, versioned

Gateway Architecture: BFF Pattern

[Mobile App] → [Mobile BFF] → Compact responses
[Web App]    → [Web BFF]    → Full responses
[Partners]   → [Partner GW] → Rate limited, API key

Mobile BFF responsibilities:
  - /mobile/v1/home → aggregate user + featured products + cart count
  - Response: Minimal fields (id, name, thumb_url only)
  - Cache: 5 min for product catalog

Web BFF responsibilities:
  - /web/v1/products/{id} → full product details + reviews + recommendations
  - Server-side rendering friendly
  - Richer error messages

Both BFFs share:
  - JWT authentication (shared auth library)
  - Rate limiting: 1000 req/min per user
  - Request ID propagation
  - Distributed tracing (correlation-id header)

Gateway → Service routing:
  /users/*      → User Service (gRPC)
  /products/*   → Product Service (REST)
  /orders/*     → Order Service (REST)
  /payments/*   → Payment Service (gRPC, mTLS)
  /search       → Elasticsearch proxy

Security:
  - External: TLS 1.3, certificate pinning (mobile)
  - Internal: mTLS (service mesh)
  - Auth: JWT validation at BFF, X-User-Id forwarded
  - OWASP WAF rules at Gateway

High Availability:
  - BFF: 3 replicas each (Kubernetes)
  - Anti-affinity rules (different nodes)
  - Health check: /healthz
  - Circuit breaker: If downstream service fails → graceful degradation
```

### Gateway Arxitekturası
```
                    [WAF + DDoS]
                         │
              ┌──────────┴──────────┐
        [Mobile BFF]           [Web BFF]       [Partner GW]
              │                    │                 │
    ┌─────────┴─────────┐         │                 │
    │    API Gateway Layer (Kong / Envoy)            │
    └─────────┬─────────┘                           │
              │
    ┌─────────┴──────────────────────────┐
 [User Svc] [Product Svc] [Order Svc] [Payment Svc]
              └── Service Mesh (Istio) ──┘
```

## Praktik Tapşırıqlar
- Kong Gateway qurun, route + rate limiting plugin konfigurasiya edin
- AWS API Gateway + Lambda integration qurun
- BFF pattern-ı implement edin: mobile vs web endpoints
- Gateway circuit breaker: downstream service mock fail edin
- Request aggregation: 3 service-dən data birləşdirin

## Əlaqəli Mövzular
- [09-rate-limiting.md] — Gateway-də rate limiting
- [15-service-discovery.md] — Gateway service discovery
- [16-circuit-breaker.md] — Gateway circuit breaking
- [04-load-balancing.md] — Gateway backend load balancing
- [20-monitoring-observability.md] — Gateway metrics, tracing
