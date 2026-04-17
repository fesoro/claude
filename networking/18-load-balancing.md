# Load Balancing

## Nədir? (What is it?)

Load balancing daxil olan network traffic-i bir nece server arasinda paylashdiran texnikadir. Meqsed hec bir serverin heddinden artiq yuklenememesi, yuksek availability ve reliability temin etmekdir. Load balancer client ve server arasinda "traffic polisi" rolunu oynayir.

```
Without Load Balancer:
  All users -----> Single Server (overloaded, SPOF)

With Load Balancer:
  Users -----> [Load Balancer] -----> Server 1
                    |--------------> Server 2
                    |--------------> Server 3
```

## Necə İşləyir? (How does it work?)

### L4 vs L7 Load Balancing

```
L4 (Transport Layer - TCP/UDP):
  - IP + Port esasinda routing
  - Packet content-ine baxmir
  - Daha suretli (az processing)
  - TCP connection forwarding
  - Meselen: AWS NLB

  Client --> LB (TCP) --> Backend
  LB content-i bilmir, yalniz IP:Port-a gore yonlendirir

L7 (Application Layer - HTTP/HTTPS):
  - HTTP headers, URL, cookies esasinda routing
  - Content-based routing (path, host, header)
  - SSL termination, compression, caching
  - Daha flexible, amma daha yavas
  - Meselen: AWS ALB, Nginx, HAProxy

  Client --> LB (HTTP) --> Backend
  LB URL-e baxir: /api/* -> API servers, /images/* -> Static servers
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
   Request 4 -> Server A  (yeniden)
   Sadedir, amma server capacity ferqlerini nezeere almır.

2. Weighted Round Robin:
   Server A (weight: 3) -> 3 request
   Server B (weight: 2) -> 2 request
   Server C (weight: 1) -> 1 request
   Muxtelif gucde serverlər ucun.

3. Least Connections:
   Server A: 10 connections
   Server B: 5 connections  <-- yeni request bura gedir
   Server C: 8 connections
   En az connection-u olan servere gonderir.

4. IP Hash:
   hash(client_ip) % server_count = target server
   Eyni client hemise eyni servere dusur (sticky session).

5. Least Response Time:
   Server A: 50ms
   Server B: 20ms  <-- yeni request bura gedir
   Server C: 35ms
   En suretli cavab veren servere gonderir.

6. Random:
   Her request random servere gonderilir.

7. Weighted Least Connections:
   (connections / weight) en asagi olana gonderir.
```

### Health Checks

```
Load Balancer muntezer olaraq serverleri yoxlayir:

Active Health Check:
  LB ---> GET /health --> Server A (200 OK ✓)
  LB ---> GET /health --> Server B (200 OK ✓)
  LB ---> GET /health --> Server C (503 ✗) --> Pool-dan cixar!

Passive Health Check:
  Real request ugursuz olursa serveri "unhealthy" qeyd edir.

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
Problem: User login oldu Server A-da, novbeti request Server B-ye gedir,
         session tapilmir!

Hell yollari:

1. Sticky Session (Cookie-based):
   LB cookie elave edir: Set-Cookie: SERVERID=server-a
   Novbeti request-de bu cookie-ye gore eyni servere yonlendirir.

2. IP Hash:
   Eyni IP hemise eyni servere gedir.
   Problem: NAT arxasindaki user-ler eyni IP paylaşır.

3. Shared Session Store (en yaxsi):
   Server A -\
   Server B ---> Redis/Memcached (shared session)
   Server C -/
   Hansi servere dussun, session Redis-den oxunur.
```

### SSL/TLS Termination

```
1. SSL Termination at LB:
   Client --HTTPS--> [LB] --HTTP--> Backend Servers
   LB SSL-i acir, backend plain HTTP isleyir.
   + SSL certificate bir yerde
   + Backend-ler daha suretli (SSL overhead yox)
   - LB ile backend arasi sifreli deyil (internal network-de OK)

2. SSL Passthrough:
   Client --HTTPS--> [LB] --HTTPS--> Backend Servers
   LB traffic-e toxunmur, birbase forwarding.
   + End-to-end encryption
   - LB content-e baxa bilmir (L7 routing yox)
   - Her backend-de SSL certificate lazim

3. SSL Re-encryption:
   Client --HTTPS--> [LB] --HTTPS--> Backend Servers
   LB acir, yeniden sifrleyir.
   + End-to-end encryption
   + LB content-e baxa biler
   - Double SSL overhead
```

## PHP/Laravel ilə İstifadə

### Laravel Health Check Endpoint

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

### Trusted Proxies (Load Balancer arxasinda)

```php
// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(
        at: '*',  // Butun proxy-lere guven (ve ya konkret IP)
        headers: Request::HEADER_X_FORWARDED_FOR |
                 Request::HEADER_X_FORWARDED_HOST |
                 Request::HEADER_X_FORWARDED_PORT |
                 Request::HEADER_X_FORWARDED_PROTO |
                 Request::HEADER_X_FORWARDED_AWS_ELB
    );
})

// Niye lazimdir?
// LB arxasinda $request->ip() LB-nin IP-sini qaytarir (real client IP deyil)
// X-Forwarded-For header ile real IP alinir
// $request->ip()     -> Client-in real IP-si
// $request->secure() -> HTTPS oldugunu bilir (X-Forwarded-Proto)
// $request->url()    -> Duzgun URL qaytarir
```

### Shared Session (Redis)

```php
// .env
SESSION_DRIVER=redis
REDIS_HOST=redis-cluster.internal
REDIS_PORT=6379

// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => 'session',  // ayri Redis connection
'lifetime' => 120,

// config/database.php
'redis' => [
    'session' => [
        'host' => env('REDIS_HOST'),
        'port' => env('REDIS_PORT'),
        'database' => 1,  // session ucun ayri database
    ],
],

// Indi butun serverler eyni session-u oxuyur
```

### Queue Worker Load Distribution

```php
// Muxtelif serverler muxtelif queue-lari isleyir:

// Server 1 - High priority
// php artisan queue:work --queue=high,default

// Server 2 - Default
// php artisan queue:work --queue=default

// Server 3 - Low priority (reports, emails)
// php artisan queue:work --queue=low,notifications

// Supervisor config (her server ucun)
// /etc/supervisor/conf.d/worker.conf
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=4
```

### Nginx Load Balancer Configuration

```nginx
# /etc/nginx/conf.d/load-balancer.conf

upstream backend {
    # Least connections algorithm
    least_conn;

    # Weighted servers
    server 10.0.0.1:8000 weight=3;
    server 10.0.0.2:8000 weight=2;
    server 10.0.0.3:8000 weight=1;

    # Backup server (yalniz digerleri down olanda)
    server 10.0.0.4:8000 backup;

    # Health check parametrleri
    # max_fails=3 fail_timeout=30s
    server 10.0.0.1:8000 max_fails=3 fail_timeout=30s;

    # Sticky session (ip_hash)
    # ip_hash;

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

## Interview Sualları

### 1. L4 ve L7 load balancing arasinda ferq nedir?
**Cavab:** L4 (Transport layer) TCP/UDP seviyyesinde isleyir, IP+port-a gore routing edir, content-e baxmır, daha suretlidir. L7 (Application layer) HTTP seviyyesinde isleyir, URL/header/cookie-ye gore routing eder, SSL termination edir, daha flexible-dir amma daha yavasdır.

### 2. En cox istifade olunan LB algorithms hansılardir?
**Cavab:** Round Robin (sade, sira ile), Least Connections (en az baglantisi olana), IP Hash (sticky session ucun), Weighted Round Robin (ferqli gucde serverler ucun), Least Response Time (en suretli cavab verene).

### 3. Sticky session nedir ve niye problemlidir?
**Cavab:** Eyni client-in hemise eyni servere yonlendirilmesidir. Session state ucun lazimdir. Problemleri: uneven load distribution, server down olanda session itir, scale-down cetin. Hell yolu: shared session store (Redis).

### 4. Health check nece isleyir?
**Cavab:** LB muntezer olaraq (her 5-30 saniye) serverlere health endpoint-e sorgu gonderir. 200 OK alsa healthy, ugursuz olsa unhealthy qeyd edir ve traffic gondermez. Server recovery olunca yeniden pool-a elave edir.

### 5. SSL termination nedir?
**Cavab:** Load balancer-in HTTPS traffic-i acib backend serverlere plain HTTP olaraq gondermesidir. Ustulukleri: SSL certificate bir yerde, backend-ler daha suretli. Dezavantaj: LB-backend arasi sifreli deyil (internal network-de OK).

### 6. Laravel-de LB arxasinda hansi problem olur?
**Cavab:** `$request->ip()` LB-nin IP-sini qaytarir, `$request->secure()` yanlis isleyir. Trusted Proxies middleware ile X-Forwarded-* headerlari oxuyub real client IP ve proto alinir.

### 7. Session problemi LB arxasinda nece hell olunur?
**Cavab:** 1) Shared session store (Redis/Memcached) - butun serverler eyni session-u oxuyur. 2) Sticky sessions (LB cookie ile). 3) Stateless auth (JWT). En yaxsi yol Redis-dir.

## Best Practices

1. **Health check hemise** - Backend serverleri muntezer yoxlayin
2. **Shared session** - Redis ile session paylashin
3. **SSL termination** - LB seviyyesinde SSL qurun
4. **Trusted proxies** - X-Forwarded-* headerlari duzgun handle edin
5. **Graceful shutdown** - Deploy zamani connection-lar tamamlansin
6. **Auto-scaling** - Yuke gore server sayi artsin/azalsin
7. **Connection draining** - Server ciximindan evvel aktiv request-ler tamamlansin
8. **Multiple AZ** - Serverler ferqli availability zone-larda olsun
9. **Monitoring** - Response time, error rate, connection count izleyin
10. **Backup servers** - En az 1 backup server saxlayin
