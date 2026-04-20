# Load Balancing Algorithms (L4 vs L7, Round-Robin, Consistent Hash)

## Mündəricat
1. [Load balancer nədir?](#load-balancer-nədir)
2. [L4 vs L7](#l4-vs-l7)
3. [Round-Robin](#round-robin)
4. [Weighted Round-Robin](#weighted-round-robin)
5. [Least Connections](#least-connections)
6. [Least Response Time](#least-response-time)
7. [IP Hash / Consistent Hash](#ip-hash--consistent-hash)
8. [Power of Two Choices](#power-of-two-choices)
9. [Sticky sessions](#sticky-sessions)
10. [Health checks](#health-checks)
11. [Nginx & HAProxy konfiqurasiya](#nginx--haproxy-konfiqurasiya)
12. [Kubernetes və cloud LB](#kubernetes-və-cloud-lb)
13. [İntervyu Sualları](#intervyu-sualları)

---

## Load balancer nədir?

```
LB — gələn trafiyi çoxlu backend arasında paylayır.
Məqsəd:
  1. High availability (bir server gedəndə digərləri)
  2. Horizontal scaling
  3. SSL termination (CPU-intensive task-ı LB-də)
  4. Session affinity (kəmər-like)
  5. Geographic routing (yaxın region)
  6. DDoS qoruma (rate limit, WAF)

Topologiya:

                ┌─ Server 1 (active)
  Client ── LB ─┼─ Server 2 (active)
                ├─ Server 3 (active)
                └─ Server 4 (standby)
```

---

## L4 vs L7

```
OSI Layer 4 (Transport — TCP/UDP):
  LB packet header-ə baxır: IP, port
  Content-ə baxmır — sadəcə TCP connection-u forward edir
  
  Üstünlük:
    ✓ Sürətli (kernel-level, DSR imkanı)
    ✓ Aşağı CPU
    ✓ Protokol-agnostik (HTTP, gRPC, MQTT, TCP)
    ✓ Yüksək throughput (1M+ req/s)
  
  Çatışmazlıq:
    ✗ HTTP header-lərinə görə routing yox
    ✗ Path-based routing yox
    ✗ Retry, circuit breaker yox
    ✗ TLS termination yox (bəzi L4 LB-lərdə var)

Layer 7 (Application — HTTP, gRPC):
  LB request content-ini parse edir: URL, headers, cookies
  
  Üstünlük:
    ✓ Path-based routing (/api → A, /web → B)
    ✓ Header-based routing (X-User-Type)
    ✓ Cookie-based sticky session
    ✓ Retry, circuit breaker, rate limit
    ✓ SSL termination + CDN
    ✓ Content compression, caching
  
  Çatışmazlıq:
    ✗ Daha yavaş (hər packet parse)
    ✗ Yüksək CPU
    ✗ HTTPS connection LB-də terminate olur (visibility)

Tipik istifadə:
  L4:  database replicas, gRPC internal, WebSocket
  L7:  public API, web app, microservice routing

Nümunələr:
  L4: AWS NLB, HAProxy (L4 mode), nginx stream, LVS
  L7: AWS ALB, nginx, HAProxy (L7 mode), Envoy, Traefik
```

---

## Round-Robin

```
Ən sadə alqoritm — sıra ilə backend-lərə paylayır.

Request 1 → Server 1
Request 2 → Server 2
Request 3 → Server 3
Request 4 → Server 1  (başa qayıdır)
...

Üstünlük:
  ✓ Sadə, stateless
  ✓ Uniform distribution (long-run-da)

Çatışmazlıq:
  ✗ Server yüklərinə baxmır
  ✗ Heterogeneous cluster-də problem (zəif server-ə çox yük)
  ✗ Connection müddəti nəzərə alınmır

Nginx:
  upstream backend {
      server srv1.example.com;
      server srv2.example.com;
      server srv3.example.com;
  }
```

---

## Weighted Round-Robin

```
Hər server-ə weight (çəki) verilir — güclü server daha çox request alır.

Server 1 weight=3
Server 2 weight=1
Server 3 weight=1

Sıra: 1, 1, 1, 2, 3, 1, 1, 1, 2, 3 ...
Yəni 3/5 = 60% Server 1-ə.

Use case:
  Heterogeneous cluster (8-core vs 4-core)
  Gradual rollout (yeni version 10% trafiklə)
  Canary deployment

Nginx:
  upstream backend {
      server srv1 weight=3;
      server srv2;         # default weight=1
      server srv3;
  }
```

---

## Least Connections

```
Ən az aktiv connection olan server-ə göndər.

Server 1: 45 active conn
Server 2: 12 active conn   ← yeni request bura
Server 3: 67 active conn

Üstünlük:
  ✓ Uzun-müddətli connection-lar (WebSocket, long polling) üçün əla
  ✓ Heterogeneous load — avtomatik balance
  ✓ Slow server (bağlanmayan conn) özünə az request alır

Çatışmazlıq:
  ✗ State saxlamalıdır (conn sayğacı)
  ✗ Distributed LB-də state sync problem

Nginx:
  upstream backend {
      least_conn;
      server srv1;
      server srv2;
  }

HAProxy:
  backend app
      balance leastconn
```

---

## Least Response Time

```
Ən qısa p50 latency göstərən server-ə göndər.

Üstünlük:
  ✓ Real-time performance-ə əsaslanır
  ✓ Slow server avtomatik az yüklənir

Çatışmazlıq:
  ✗ Pendulum effect: sürətli server-ə hamı hücum edir → yavaşlayır → növbəti hamı başqa server-ə
  ✗ State management mürəkkəb

Nginx Plus (paid):
  upstream backend {
      least_time last_byte;
      server srv1;
      server srv2;
  }
```

---

## IP Hash / Consistent Hash

```
Client IP hash-ə görə server seç. Eyni IP həmişə eyni server-ə.

hash(client_ip) % N → server

Use case:
  Session affinity (cookie yox, IP based)
  Cache locality (eyni user eyni server-ə → cache hit)

Problem: N dəyişəndə hamısı remap (cache invalidation storm)
  Həll: Consistent Hashing (bax 171-consistent-hashing.md)

Nginx:
  upstream backend {
      ip_hash;                    # sadə modulo
      server srv1;
      server srv2;
  }

  # Consistent hash (Nginx Plus və ya 3rd-party module)
  upstream backend {
      hash $request_uri consistent;
      server srv1;
      server srv2;
  }
```

---

## Power of Two Choices

```
İki random server seç, daha az yüklü olanı seç.

Alqoritm:
  1. N server-dən 2-sini random seç
  2. Hansı az yükü var? (conn count, latency)
  3. Az yüklü olana göndər

Niyə bu?
  - Full least-conn: bütün server-lərə baxmalısan (O(N))
  - Random 2 + seç: O(1) + minimal synchronization
  - Nəticə: "least-conn"-a çox yaxın, scale edir

Nəzəri fon:
  Michael Mitzenmacher (1996) — "The Power of Two Choices"
  Exponentially better than random: n server-də log log n maksimum yük

İstifadə:
  HAProxy: "first" algorithm (bir növ power-of-two)
  NGINX: Lua script ilə
  Consul, Istio Envoy — built-in
```

---

## Sticky sessions

```
"Eyni user-i həmişə eyni server-ə göndər" — session state server-də varsa.

Problem: Session saxlayan server gedirsə, user logout olur.
Modern approach: Stateless server + external session store (Redis).

Sticky session variantları:
  1. Cookie-based:
     LB cookie təyin edir ("BACKEND=server-2")
     Sonrakı request-lər həmin cookie ilə gəlir → server-2
  
  2. IP-based:
     IP hash → server (mobile, NAT-də problem)
  
  3. Application cookie:
     App özü cookie yazır (JSESSIONID), LB oxuyur

Use case HƏqiqi:
  - WebSocket (stateful connection)
  - Legacy session-in-memory app
  - gRPC streaming

Nginx:
  upstream backend {
      server srv1;
      server srv2;
      sticky cookie srv_id expires=1h;   # Nginx Plus
  }

HAProxy:
  backend app
      cookie SERVERID insert indirect nocache
      server s1 10.0.0.1:80 cookie s1 check
      server s2 10.0.0.2:80 cookie s2 check
```

---

## Health checks

```
LB backend-in sağlamlığını yoxlayır — xəstə server-lərə trafik göndərmir.

Aktiv health check (pull):
  LB hər N saniyədən bir GET /health göndərir
  200 OK → healthy
  5xx / timeout → unhealthy (throw out of rotation)

Passiv health check (push):
  Real request-lərə cavab əsasında qərar
  N ardıcıl fail → throw out
  M ardıcıl success → bring back

Best practice:
  - Aktiv + passiv ikisini də istifadə et
  - /health endpoint LIGHT olmalıdır (DB ping yox, sadəcə server OK)
  - /ready endpoint daha ciddi yoxlama (DB, cache, queue available?)
  - Kubernetes liveness vs readiness fərqi

Nginx:
  upstream backend {
      server srv1 max_fails=3 fail_timeout=30s;
      server srv2 max_fails=3 fail_timeout=30s;
  }
  # 30s-də 3 fail → 30s-lik ban

HAProxy:
  backend app
      option httpchk GET /health
      server s1 10.0.0.1:80 check inter 5s fall 3 rise 2
      # hər 5s check, 3 ardıcıl fail → down, 2 ardıcıl ok → up
```

---

## Nginx & HAProxy konfiqurasiya

```nginx
# Nginx production config
upstream api_backend {
    least_conn;
    
    server api-1.internal:8080 weight=3 max_fails=3 fail_timeout=30s;
    server api-2.internal:8080 weight=3 max_fails=3 fail_timeout=30s;
    server api-3.internal:8080 weight=1 max_fails=3 fail_timeout=30s backup;
    
    keepalive 32;       # connection pool
    keepalive_requests 100;
    keepalive_timeout 60s;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;
    
    ssl_certificate /etc/ssl/cert.pem;
    ssl_certificate_key /etc/ssl/key.pem;
    
    location / {
        proxy_pass http://api_backend;
        
        # Headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Request-ID $request_id;
        
        # Timeouts
        proxy_connect_timeout 2s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
        
        # Retry on failure
        proxy_next_upstream error timeout http_502 http_503 http_504;
        proxy_next_upstream_tries 3;
        proxy_next_upstream_timeout 10s;
        
        # Rate limiting
        limit_req zone=api burst=20 nodelay;
    }
}
```

```haproxy
# HAProxy production config
global
    maxconn 50000
    log stdout format raw local0 info

defaults
    mode http
    timeout connect 5s
    timeout client 30s
    timeout server 30s
    option httplog
    option dontlognull
    option http-server-close
    option redispatch
    retries 3

frontend https_in
    bind *:443 ssl crt /etc/ssl/cert.pem alpn h2,http/1.1
    
    # Path-based routing
    acl is_api path_beg /api/
    acl is_admin path_beg /admin/
    
    use_backend api_servers if is_api
    use_backend admin_servers if is_admin
    default_backend web_servers

backend api_servers
    balance leastconn
    option httpchk GET /health
    http-check expect status 200
    
    server api1 10.0.1.1:8080 check inter 5s fall 3 rise 2 weight 3
    server api2 10.0.1.2:8080 check inter 5s fall 3 rise 2 weight 3
    server api3 10.0.1.3:8080 check inter 5s fall 3 rise 2 weight 1 backup
```

---

## Kubernetes və cloud LB

```
Kubernetes:
  Service ClusterIP:
    Dahili LB (iptables / IPVS — L4 round-robin)
    kube-proxy ilə implement olunur
  
  Service LoadBalancer:
    Cloud-provider LB (AWS NLB, GCP LB)
    External IP təqdim edir
  
  Ingress:
    L7 LB (nginx, Traefik, HAProxy)
    Host/path routing, TLS termination

AWS:
  ALB (Application Load Balancer) — L7 HTTPS, path routing
  NLB (Network Load Balancer) — L4 TCP/UDP, ultra-fast
  CLB (Classic) — köhnə, yeni layihələrdə istifadə etmə

GCP:
  HTTP(S) Load Balancer — global, anycast
  Network LB — regional, TCP/UDP

Azure:
  Application Gateway — L7
  Load Balancer — L4

Service Mesh (Istio/Linkerd):
  Sidecar proxy (Envoy) hər pod-da
  East-west (daxili) trafik LB
  Retry, circuit breaker, tracing built-in
```

```yaml
# Kubernetes Service LoadBalancer
apiVersion: v1
kind: Service
metadata:
  name: api
  annotations:
    service.beta.kubernetes.io/aws-load-balancer-type: nlb
spec:
  type: LoadBalancer
  selector:
    app: api
  ports:
  - port: 443
    targetPort: 8080
    protocol: TCP
  sessionAffinity: ClientIP   # sticky session
  sessionAffinityConfig:
    clientIP:
      timeoutSeconds: 10800   # 3 saat
```

---

## İntervyu Sualları

- L4 və L7 load balancer arasındakı fərq nədir?
- Round-robin nə vaxt pisdir? Hansı alternativ?
- Least connections nə vaxt round-robin-dən üstündür?
- Sticky session niyə anti-pattern ola bilər?
- Consistent hashing LB-də nə vaxt istifadə olunur?
- Power of Two Choices alqoritmi necə işləyir?
- Aktiv və passiv health check fərqi?
- Nginx `max_fails` və `fail_timeout` necə işləyir?
- Kubernetes Service ClusterIP hansı LB alqoritmini istifadə edir?
- AWS ALB və NLB arasında necə seçim edirsiniz?
- SSL termination LB-də niyə edilir?
- WebSocket trafik üçün LB-də nəyə diqqət edilir?
