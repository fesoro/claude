# Load Balancing (Middle)

## İcmal

Load balancing daxil olan network traffic-i bir neçə server arasında paylaşdıran texnikadır. Məqsəd heç bir serverin həddindən artıq yüklənməməsi, yüksək availability və reliability təmin etməkdir. Load balancer client və server arasında "traffic polisi" rolunu oynayır.

```
Without Load Balancer:
  All users -----> Single Server (overloaded, SPOF)

With Load Balancer:
  Users -----> [Load Balancer] -----> Server 1
                    |--------------> Server 2
                    |--------------> Server 3
```

## Niyə Vacibdir

Tək server həm SPOF (Single Point of Failure)-dur, həm də yük artdıqda sistem çöküşünə uğrayır. Load balancing horizontal scaling-i mümkün edir — trafikə görə server sayını artırmaq/azaltmaq. Health check mexanizmi sayəsində problem olan server avtomatik pool-dan çıxarılır, istifadəçi xidməti kəsilmədən davam edir. Production Laravel tətbiqləri mütləq load balancer arxasında işləməlidir.

## Əsas Anlayışlar

### L4 vs L7 Load Balancing

```
L4 (Transport Layer - TCP/UDP):
  - IP + Port əsasında routing
  - Packet content-inə baxmır
  - Daha sürətli (az processing)
  - TCP connection forwarding
  - Məsələn: AWS NLB

  Client --> LB (TCP) --> Backend
  LB content-i bilmir, yalnız IP:Port-a görə yönləndirir

L7 (Application Layer - HTTP/HTTPS):
  - HTTP headers, URL, cookies əsasında routing
  - Content-based routing (path, host, header)
  - SSL termination, compression, caching
  - Daha flexible, amma daha yavaş
  - Məsələn: AWS ALB, Nginx, HAProxy

  Client --> LB (HTTP) --> Backend
  LB URL-ə baxır: /api/* -> API servers, /images/* -> Static servers
```

```
L7 Content-Based Routing:

                        /api/*     --> [API Server Pool]
Client --> [L7 LB] --> /static/*  --> [CDN / Static Pool]
                        /ws/*      --> [WebSocket Pool]
                        /admin/*   --> [Admin Pool]
```

### Load Balancing Algorithms

```
1. Round Robin:
   Request 1 -> Server A
   Request 2 -> Server B
   Request 3 -> Server C
   Request 4 -> Server A  (yenidən)
   Sadədir, amma server capacity fərqlərini nəzərə almır.

2. Weighted Round Robin:
   Server A (weight: 3) -> 3 request
   Server B (weight: 2) -> 2 request
   Server C (weight: 1) -> 1 request
   Müxtəlif gücde serverlər üçün.

3. Least Connections:
   Server A: 10 connections
   Server B: 5 connections  <-- yeni request bura gedir
   Server C: 8 connections
   Ən az connection-u olan serverə göndərir.

4. IP Hash:
   hash(client_ip) % server_count = target server
   Eyni client həmişə eyni serverə düşür (sticky session).

5. Least Response Time:
   Server A: 50ms
   Server B: 20ms  <-- yeni request bura gedir
   Server C: 35ms
   Ən sürətli cavab verən serverə göndərir.

6. Random:
   Hər request random serverə göndərilir.

7. Weighted Least Connections:
   (connections / weight) ən aşağı olana göndərir.
```

### Health Checks

```
Load Balancer müntəzər olaraq serverləri yoxlayır:

Active Health Check:
  LB ---> GET /health --> Server A (200 OK ✓)
  LB ---> GET /health --> Server B (200 OK ✓)
  LB ---> GET /health --> Server C (503 ✗) --> Pool-dan çıxar!

Passive Health Check:
  Real request uğursuz olursa serveri "unhealthy" qeyd edir.

Health Check Response:
{
  "status": "healthy",
  "database": "connected",
  "redis": "connected",
  "disk_free": "45GB",
  "memory_usage": "62%"
}
```

### Session Persistence (Sticky Sessions)

```
Problem: User login oldu Server A-da, növbəti request Server B-yə gedir,
         session tapılmır!

Həll yolları:

1. Sticky Session (Cookie-based):
   LB cookie əlavə edir: Set-Cookie: SERVERID=server-a
   Növbəti request-də bu cookie-yə görə eyni serverə yönləndirir.

2. IP Hash:
   Eyni IP həmişə eyni serverə gedir.
   Problem: NAT arxasındakı user-lər eyni IP paylaşır.

3. Shared Session Store (ən yaxşı):
   Server A -\
   Server B ---> Redis/Memcached (shared session)
   Server C -/
   Hansı serverə düşsün, session Redis-dən oxunur.
```

### SSL/TLS Termination

```
1. SSL Termination at LB:
   Client --HTTPS--> [LB] --HTTP--> Backend Servers
   LB SSL-i açır, backend plain HTTP işləyir.
   + SSL certificate bir yerdə
   + Backend-lər daha sürətli (SSL overhead yox)
   - LB ilə backend arası şifrəli deyil (internal network-də OK)

2. SSL Passthrough:
   Client --HTTPS--> [LB] --HTTPS--> Backend Servers
   LB traffic-ə toxunmur, birbaşa forwarding.
   + End-to-end encryption
   - LB content-ə baxa bilmir (L7 routing yox)
   - Hər backend-də SSL certificate lazım

3. SSL Re-encryption:
   Client --HTTPS--> [LB] --HTTPS--> Backend Servers
   LB açır, yenidən şifrləyir.
   + End-to-end encryption
   + LB content-ə baxa bilər
   - Double SSL overhead
```

## Praktik Baxış

**Nə vaxt L4, nə vaxt L7 istifadə etmək lazımdır:**
- L4: Yüksək throughput, sadə TCP forwarding, minimum latency tələb olunanda
- L7: Content-based routing, SSL termination, header manipulation, authentication lazım olanda

**Trade-off-lar:**
- Sticky session uneven load distribution yaradır — Redis shared session daha yaxşıdır
- SSL termination LB ilə backend arası trafikin şifrəsiz getməsi deməkdir (internal network güvənirsinizsə OK)
- Health check intervali qısa olsa LB özü də yük yaradır

**Anti-pattern-lər:**
- Trusted Proxies konfiqurasiyasız deploy etmək — `$request->ip()` LB IP-sini qaytarır
- Session-u sticky session ilə həll etmək (Redis lazımdır)
- Health check endpoint-ini autentifikasiya arxasında saxlamaq — LB ərisə bilmir
- Bütün serverləri eyni AZ-da yerləşdirmək — AZ down olanda hamısı gedir

## Nümunələr

### Ümumi Nümunə

Multi-server Laravel deploy arxitekturası:

```
[Nginx LB: 10.0.0.0]
        |
        |-- Round Robin --> [Laravel App: 10.0.0.1:8000]
        |-- Round Robin --> [Laravel App: 10.0.0.2:8000]
        |-- Round Robin --> [Laravel App: 10.0.0.3:8000]
                                    |
                            [Redis Cluster] (shared session, cache, queue)
                                    |
                            [MySQL Primary + Replicas]
```

### Kod Nümunəsi

**Laravel Health Check Endpoint:**

```php
// routes/api.php
Route::get('/health', function () {
    $checks = [];
    $healthy = true;

    // Database check
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error: ' . $e->getMessage();
        $healthy = false;
    }

    // Redis check
    try {
        Redis::ping();
        $checks['redis'] = 'ok';
    } catch (\Exception $e) {
        $checks['redis'] = 'error';
        $healthy = false;
    }

    // Disk space
    $freeSpace = disk_free_space('/');
    $checks['disk_free_gb'] = round($freeSpace / 1024 / 1024 / 1024, 2);
    if ($checks['disk_free_gb'] < 1) {
        $healthy = false;
    }

    // Memory
    $checks['memory_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 2);

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
        'hostname' => gethostname(),
    ], $healthy ? 200 : 503);
});
```

**Trusted Proxies (Load Balancer arxasında):**

```php
// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(
        at: '*',  // Bütün proxy-lərə güvən (və ya konkret IP)
        headers: Request::HEADER_X_FORWARDED_FOR |
                 Request::HEADER_X_FORWARDED_HOST |
                 Request::HEADER_X_FORWARDED_PORT |
                 Request::HEADER_X_FORWARDED_PROTO |
                 Request::HEADER_X_FORWARDED_AWS_ELB
    );
})

// Niyə lazımdır?
// LB arxasında $request->ip() LB-nin IP-sini qaytarır (real client IP deyil)
// X-Forwarded-For header ilə real IP alınır
// $request->ip()     -> Client-in real IP-si
// $request->secure() -> HTTPS olduğunu bilir (X-Forwarded-Proto)
// $request->url()    -> Düzgün URL qaytarır
```

**Shared Session (Redis):**

```php
// .env
SESSION_DRIVER=redis
REDIS_HOST=redis-cluster.internal
REDIS_PORT=6379

// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => 'session',  // ayrı Redis connection
'lifetime' => 120,

// config/database.php
'redis' => [
    'session' => [
        'host' => env('REDIS_HOST'),
        'port' => env('REDIS_PORT'),
        'database' => 1,  // session üçün ayrı database
    ],
],

// İndi bütün serverlər eyni session-u oxuyur
```

**Queue Worker Load Distribution:**

```php
// Müxtəlif serverlər müxtəlif queue-ları işləyir:

// Server 1 - High priority
// php artisan queue:work --queue=high,default

// Server 2 - Default
// php artisan queue:work --queue=default

// Server 3 - Low priority (reports, emails)
// php artisan queue:work --queue=low,notifications

// Supervisor config (hər server üçün)
// /etc/supervisor/conf.d/worker.conf
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=4
```

**Nginx Load Balancer Configuration:**

```nginx
# /etc/nginx/conf.d/load-balancer.conf

upstream backend {
    # Least connections algorithm
    least_conn;

    # Weighted servers
    server 10.0.0.1:8000 weight=3;
    server 10.0.0.2:8000 weight=2;
    server 10.0.0.3:8000 weight=1;

    # Backup server (yalnız digərləri down olanda)
    server 10.0.0.4:8000 backup;

    # Health check parametrləri
    server 10.0.0.1:8000 max_fails=3 fail_timeout=30s;

    # Keep-alive connections to backend
    keepalive 32;
}

server {
    listen 443 ssl;
    server_name api.example.com;

    ssl_certificate /etc/ssl/cert.pem;
    ssl_certificate_key /etc/ssl/key.pem;

    location / {
        proxy_pass http://backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeouts
        proxy_connect_timeout 5s;
        proxy_read_timeout 60s;

        # WebSocket support
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    # Health check endpoint
    location /health {
        proxy_pass http://backend;
        access_log off;
    }
}
```

## Praktik Tapşırıqlar

1. **Health check endpoint:** `/api/health` endpoint-ini implement edin. DB, Redis, disk məkanını yoxlasın. Hər hansı biri uğursuz olsa 503, hamısı OK-dırsa 200 qaytarsın.

2. **Trusted Proxies:** LB arxasında `$request->ip()` yanlış IP qaytardığını göstərin. `trustProxies()` konfiqurasiyası ilə düzəldin. `X-Forwarded-For` header-ini test edin.

3. **Redis session:** `SESSION_DRIVER=redis` konfiqurasiya edin. 2 ayrı `php artisan serve` prosesi başladın. Birində login olun, digərinin eyni session-u oxuduğunu yoxlayın.

4. **Nginx upstream:** Nginx ilə 2 Laravel backend arasında `round_robin` load balancing qurun. `server_name` response header-i əlavə edərək hansı serverə düşüldüyünü göstərin.

5. **Algorithm müqayisəsi:** `round_robin` vs `least_conn` vs `ip_hash` algoritmini `curl` ilə test edin. 100 request göndərin — hansı server neçə request aldı?

6. **Graceful shutdown:** `php artisan queue:work --stop-when-empty` ilə server-i yük olmadan dayandırın. Nginx-in connection draining-i `proxy_read_timeout` ilə necə idarə etdiyini izləyin.

## Əlaqəli Mövzular

- [Reverse Proxy](19-reverse-proxy.md)
- [CDN](20-cdn.md)
- [API Gateway](21-api-gateway.md)
- [WebSocket](11-websocket.md)
- [Network Timeouts](42-network-timeouts.md)
- [Service Discovery](43-service-discovery.md)
