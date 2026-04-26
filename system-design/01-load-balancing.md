# Load Balancing (Junior)

## İcmal

Load balancer gələn network trafikini bir neçə server arasında paylayan komponentdir.
Məqsəd heç bir serverin həddindən artıq yüklənməməsini təmin etməkdir. Bu, yüksək
availability, reliability və scalability təmin edir.

Sadə dillə: bir restoranın qapısında duran hostess kimi düşünün - müştəriləri
boş stollar arasında bərabər paylaşdırır.

```
Client Request
      |
      v
 [Load Balancer]
  /    |    \
 v     v     v
[S1]  [S2]  [S3]   <-- Backend Servers
```


## Niyə Vacibdir

Hər production sistemi bir anda tək serverdən çox trafikə xidmət etməlidir. Load balancer olmadan tək server çöküşü bütün sistemi dayandırır; health check mexanizmi aradan çıxan serverləri avtomatik traffic-dən kənar tutur. Deploy, rolling update, canary release — hamısı load balancer üzərindən idarə olunur.

## Əsas Anlayışlar

### L4 vs L7 Load Balancing

**Layer 4 (Transport Layer)**
- TCP/UDP səviyyəsində işləyir
- IP address və port əsasında routing edir
- Request-in content-ini görmür (HTTP headers, body yoxdur)
- Daha sürətli, çünki packet-ləri inspect etmir
- Misal: AWS NLB (Network Load Balancer)

```
Client -> [L4 LB] -> TCP connection birbaşa backend-ə forward olunur
         (IP:Port əsasında qərar verir)
```

**Layer 7 (Application Layer)**
- HTTP/HTTPS səviyyəsində işləyir
- URL path, headers, cookies, request body əsasında routing
- Content-based routing mümkündür
- SSL termination edə bilir
- Misal: AWS ALB, Nginx, HAProxy

```
Client -> [L7 LB] -> HTTP request-i oxuyur, analiz edir, uyğun backend-ə yönləndirir
         (/api/* -> API servers, /images/* -> Static servers)
```

### Load Balancing Alqoritmləri

**1. Round Robin**
Ən sadə üsul. Request-ləri sıra ilə serverlərə göndərir.

```
Request 1 -> Server A
Request 2 -> Server B
Request 3 -> Server C
Request 4 -> Server A  (yenidən başlayır)
```

Üstünlük: Sadə, anlamaq asandır
Mənfi: Serverlərin fərqli gücləri varsa, effektiv deyil

**2. Weighted Round Robin**
Hər serverə çəki (weight) verilir. Güclü serverlər daha çox request alır.

```
Server A (weight: 5) -> 5 request
Server B (weight: 3) -> 3 request
Server C (weight: 2) -> 2 request
```

**3. Least Connections**
Ən az aktiv connection-u olan serverə göndərir.

```
Server A: 10 active connections
Server B: 5 active connections   <-- bu seçilir
Server C: 8 active connections
```

Üstünlük: Long-lived connections üçün yaxşıdır (WebSocket)

**4. IP Hash**
Client IP-sinə əsasən həmişə eyni serverə yönləndirir.

```
hash(client_ip) % server_count = target_server
hash(192.168.1.1) % 3 = 1 -> Server B (həmişə)
```

Üstünlük: Session persistence təmin edir
Mənfi: Server əlavə/çıxarıldıqda redistribution baş verir

**5. Least Response Time**
Ən az response time olan serverə göndərir.

**6. Random**
Təsadüfi seçim. Statistik olaraq böyük trafikdə bərabər paylanır.

### Health Checks

Load balancer backend serverlərin sağlam olduğunu yoxlayır:

**Active Health Check:** LB periodik olaraq serverlərə request göndərir
```
LB -> GET /health -> Server A (200 OK - sağlamdır)
LB -> GET /health -> Server B (503 Error - xaric edilir)
LB -> GET /health -> Server C (200 OK - sağlamdır)
```

**Passive Health Check:** Real traffic response-larına baxır
```
Əgər Server B ardıcıl 3 dəfə error qaytarırsa -> pool-dan çıxarılır
```

### Sticky Sessions (Session Affinity)

Eyni user-in request-ləri həmişə eyni serverə getməlidir:

```
User A -> Cookie: SERVERID=s1 -> Server 1
User B -> Cookie: SERVERID=s2 -> Server 2
```

Problem: Server düşərsə, session itirilir.
Həll: Session-ları Redis/Memcached-ə keçirmək (centralized session store).

## Arxitektura

### Tipik Production Arxitekturası

```
Internet
   |
[DNS - Round Robin]
   |
[L4 LB - TCP level]  <-- NLB / keepalived + VRRP
   |        |
[L7 LB]  [L7 LB]    <-- Nginx / HAProxy (redundant pair)
 / | \    / | \
[App Servers]         <-- PHP-FPM processes
   |
[Database]
```

### HAProxy Konfiqurasiyası

```
# /etc/haproxy/haproxy.cfg
global
    maxconn 50000
    log /dev/log local0

defaults
    mode http
    timeout connect 5s
    timeout client 30s
    timeout server 30s
    option httplog
    option dontlognull

frontend http_front
    bind *:80
    bind *:443 ssl crt /etc/ssl/certs/site.pem
    redirect scheme https if !{ ssl_fc }
    default_backend app_servers

    # L7 routing
    acl is_api path_beg /api
    acl is_static path_beg /static
    use_backend api_servers if is_api
    use_backend static_servers if is_static

backend app_servers
    balance roundrobin
    option httpchk GET /health
    http-check expect status 200

    server app1 10.0.1.1:9000 check weight 5 inter 5s fall 3 rise 2
    server app2 10.0.1.2:9000 check weight 3 inter 5s fall 3 rise 2
    server app3 10.0.1.3:9000 check weight 2 inter 5s fall 3 rise 2
    cookie SERVERID insert indirect nocache

backend api_servers
    balance leastconn
    option httpchk GET /api/health
    server api1 10.0.2.1:9000 check
    server api2 10.0.2.2:9000 check

backend static_servers
    balance roundrobin
    server static1 10.0.3.1:80 check
    server static2 10.0.3.2:80 check

listen stats
    bind *:8404
    stats enable
    stats uri /stats
    stats refresh 10s
```

### Nginx Load Balancer Konfiqurasiyası

```nginx
upstream app_backend {
    least_conn;
    server 10.0.1.1:9000 weight=5 max_fails=3 fail_timeout=30s;
    server 10.0.1.2:9000 weight=3 max_fails=3 fail_timeout=30s;
    server 10.0.1.3:9000 backup;  # yalnız digərləri düşəndə
}

upstream websocket_backend {
    ip_hash;  # WebSocket üçün sticky session
    server 10.0.2.1:6001;
    server 10.0.2.2:6001;
}

server {
    listen 80;
    listen 443 ssl;
    server_name example.com;

    ssl_certificate /etc/ssl/certs/site.pem;
    ssl_certificate_key /etc/ssl/private/site.key;

    location / {
        proxy_pass http://app_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_next_upstream error timeout http_502 http_503;
    }

    location /ws {
        proxy_pass http://websocket_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    location /health {
        access_log off;
        return 200 "OK";
    }
}
```

### AWS ELB/ALB

**Classic Load Balancer (CLB):** Köhnə, L4+L7, artıq tövsiyə edilmir
**Application Load Balancer (ALB):** L7, path-based routing, host-based routing
**Network Load Balancer (NLB):** L4, ultra-low latency, static IP

```
ALB Routing Rules:
- Host: api.example.com -> Target Group: API Servers
- Path: /admin/* -> Target Group: Admin Servers
- Path: /* -> Target Group: Web Servers
- Header: X-Custom: mobile -> Target Group: Mobile API
```

## Nümunələr

### Laravel Load Balancer Arxasında

```php
// app/Http/Middleware/TrustProxies.php
namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    // Load balancer IP-ləri
    protected $proxies = [
        '10.0.0.1',
        '10.0.0.2',
    ];

    // Və ya bütün proxy-ləri trust et (AWS ALB üçün)
    // protected $proxies = '*';

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
```

### HTTPS Detection Behind LB

```php
// config/app.php - Force HTTPS
'url' => env('APP_URL', 'https://example.com'),

// AppServiceProvider.php
public function boot(): void
{
    if ($this->app->environment('production')) {
        \URL::forceScheme('https');
    }
}
```

### Health Check Endpoint

```php
// routes/web.php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Cache::store('redis')->get('health-check');
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'up',
                'cache' => 'up',
            ]
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 503);
    }
});
```

### Session Management with Redis (LB üçün vacib)

```php
// .env
SESSION_DRIVER=redis
REDIS_HOST=redis-cluster.example.com

// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => 'session',
'lifetime' => 120,

// config/database.php
'redis' => [
    'session' => [
        'host' => env('REDIS_HOST'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 1,  // session üçün ayrı DB
    ],
],
```

## Real-World Nümunələr

**Netflix:** Multiple layer LB istifadə edir. DNS-level load balancing + Zuul (L7 API gateway).
Hər region-da ayrı LB cluster-ləri var. Custom least-connection alqoritm istifadə edirlər.

**Uber:** HAProxy + custom L7 routing. Geo-based load balancing ilə ən yaxın data center-ə
yönləndirmə edir. gRPC traffic üçün xüsusi LB həlləri var.

**GitHub:** GLB (GitHub Load Balancer) adlı custom L4 LB yaradıblar. DPDK istifadə edərək
kernel-ı bypass edir, çox yüksək performance əldə edirlər.

## Praktik Tapşırıqlar

**S: L4 və L7 load balancing arasındaki fərq nədir?**
C: L4 transport layer-də (TCP/UDP) işləyir, yalnız IP və port-a baxır, daha sürətlidir.
L7 application layer-də (HTTP) işləyir, URL, headers, cookies-ə baxır, content-based
routing edə bilir, amma daha çox resource tələb edir.

**S: Bir server düşərsə nə baş verir?**
C: Health check mexanizmi bunu detect edir. Active health check periodik sorğu göndərir,
passive health check real traffic-dən baxır. Əgər server fail olarsa, LB onu pool-dan
çıxarır və trafiği digər serverlərə yönləndirir. Server düzəldikdə yenidən əlavə edilir.

**S: Sticky sessions niyə problemdir?**
C: Trafik bərabər paylanmır, bir server overload ola bilər. Server düşərsə session itirilir.
Həll: Session-ları centralized store-da (Redis) saxlamaq, beləliklə hər hansı server
istənilən user-in session-ını oxuya bilər.

**S: 10 milyon concurrent user üçün LB necə dizayn edərdiniz?**
C: Multi-tier yanaşma: DNS Round Robin (global) -> L4 NLB (regional) -> L7 ALB (application).
Auto-scaling group ilə backend serverləri. Health check + circuit breaker. Geo-based routing
ilə istifadəçiləri ən yaxın region-a yönləndirmə.

## Praktik Baxış

1. **Həmişə redundant LB olsun** - Tək LB single point of failure-dır. Active-passive və ya active-active pair istifadə edin
2. **Health check-ləri düzgün konfiqurasiya edin** - Yalnız HTTP 200 yox, database və cache connectivity-ni də yoxlayın
3. **Connection draining aktiv edin** - Server çıxarılarkən mövcud request-lərin bitməsini gözləyin
4. **SSL termination LB-da edin** - Backend serverlərdə CPU yükünü azaldır
5. **Stateless application dizayn edin** - Session, cache, files üçün external store istifadə edin
6. **Monitoring quraşdırın** - LB metrics: request rate, error rate, latency, active connections
7. **Rate limiting əlavə edin** - DDoS-dan qorunmaq üçün LB-da rate limit tətbiq edin
8. **Graceful degradation** - Backend-lər yavaşlayanda timeout-ları düzgün tənzimləyin


## Əlaqəli Mövzular

- [API Gateway](02-api-gateway.md) — L7 routing və auth mərkəzləşdirmə
- [Rate Limiting](06-rate-limiting.md) — trafikə limit qoymaq
- [Scaling](08-scaling.md) — horizontal/vertical böyümə strategiyaları
- [CDN](04-cdn.md) — edge-dən statik content serv etmək
- [Backpressure & Load Shedding](57-backpressure-load-shedding.md) — həddindən artıq yük idarəsi
