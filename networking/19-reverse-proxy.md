# Reverse Proxy

## Nədir? (What is it?)

Reverse proxy client request-lerini qebul edib backend serverlere yonlendiren server-dir. Client reverse proxy-nin arxasindaki server-lerin varliqindan xeberdar deyil. Nginx, Apache, HAProxy, Caddy en populyar reverse proxy-lerdir.

```
Forward Proxy (Client terefindir):
  Client --> [Forward Proxy] --> Internet --> Server
  Client ozunu gizledir (VPN kimi)

Reverse Proxy (Server terefindir):
  Client --> Internet --> [Reverse Proxy] --> Backend Server(s)
  Server ozunu gizledir, client yalniz proxy-ni gorur
```

## Necə İşləyir? (How does it work?)

### Forward vs Reverse Proxy

```
Forward Proxy:
  - Client terefinde durur
  - Client internet-e cixisi kontrol edir
  - Client IP-sini gizleyir
  - Content filtering, caching
  - Meselen: Squid, corporate proxy

  [Client A] --\
  [Client B] ----> [Forward Proxy] ----> [Internet] ----> [Server]
  [Client C] --/

Reverse Proxy:
  - Server terefinde durur
  - Servere gelenleri kontrol edir
  - Server IP-sini gizleyir
  - Load balancing, SSL, caching, security
  - Meselen: Nginx, Apache

  [Client] ----> [Internet] ----> [Reverse Proxy] ----> [Server A]
                                        |-----------> [Server B]
                                        |-----------> [Server C]
```

### Reverse Proxy Funksiyalari

```
1. Load Balancing
   Trafiki bir nece backend arasinda paylasdirmaq

2. SSL Termination
   HTTPS-i acib backend-e HTTP gondermek

3. Caching
   Static content ve API response-lari cache etmek

4. Compression
   Response-lari gzip/brotli ile sixmaq

5. Security
   Backend serverleri gizlemek, DDoS qorunma, WAF

6. Request/Response Modification
   Header elave/silmek, URL rewrite

7. Static File Serving
   Statik faylları backend-e gondermeden birbaşa serve etmek

8. Rate Limiting
   Request sayini mehdudlashdirmaq
```

### Request Flow

```
Client                  Nginx (Reverse Proxy)           PHP-FPM
  |                          |                              |
  |--- HTTPS Request ------->|                              |
  |                          |-- SSL Terminate              |
  |                          |                              |
  |                          |-- Static file? (/img/logo)   |
  |                          |   Yes -> Serve directly      |
  |                          |   No  -> Continue            |
  |                          |                              |
  |                          |-- Check cache                |
  |                          |   Hit -> Return cached       |
  |                          |   Miss -> Continue           |
  |                          |                              |
  |                          |-- FastCGI/HTTP to PHP ------>|
  |                          |                              |-- Process
  |                          |<-- Response -----------------| 
  |                          |                              |
  |                          |-- Compress (gzip)            |
  |                          |-- Add headers                |
  |<-- HTTPS Response -------|                              |
```

## Əsas Konseptlər (Key Concepts)

### Nginx + PHP-FPM Architecture

```
                    ┌─────────────────────────┐
                    │         Nginx            │
                    │  (Reverse Proxy + Web)   │
                    │                          │
                    │  - SSL termination       │
                    │  - Static files          │
                    │  - Gzip compression      │
                    │  - Rate limiting         │
                    │  - Request routing       │
                    └──────────┬───────────────┘
                               │
                    FastCGI (port 9000 / unix socket)
                               │
                    ┌──────────┴───────────────┐
                    │        PHP-FPM            │
                    │  (FastCGI Process Manager)│
                    │                          │
                    │  Worker 1 (PHP process)  │
                    │  Worker 2 (PHP process)  │
                    │  Worker 3 (PHP process)  │
                    │  Worker N (PHP process)  │
                    └──────────────────────────┘

PHP-FPM Process Manager Modes:
  static    - Sabit sayda worker (oncareful = best performance)
  dynamic   - Min/max arasi worker sayini tenzimleyir
  ondemand  - Lazim olanda worker yaradir (az memory, cox latency)
```

### Caching Proxy

```
Nginx cache ile:

  Request 1: /api/products
    -> Cache MISS -> PHP-FPM -> Response -> Cache-e yaz -> Client-e gonder
  
  Request 2: /api/products (5 saniye icinde)
    -> Cache HIT -> Client-e gonder (PHP-FPM-e getmir!)

  Cache-Control headerine esasen nece cache olunur:
    Cache-Control: public, max-age=300     (5 deqiqe cache)
    Cache-Control: no-store                (cache etme)
    Cache-Control: private                 (yalniz brauzer cache)
```

## PHP/Laravel ilə İstifadə

### Nginx Configuration for Laravel

```nginx
# /etc/nginx/sites-available/laravel.conf

server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name example.com;

    # SSL
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Root directory
    root /var/www/laravel/public;
    index index.php;

    # Logging
    access_log /var/log/nginx/laravel-access.log;
    error_log /var/log/nginx/laravel-error.log;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml application/xml+rss text/javascript;
    gzip_min_length 256;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Static files - Nginx birbase serve edir (PHP-ye getmir)
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    # Laravel main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        # ve ya: fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Timeouts
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;

        # Buffering
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    # Gizli fayllar
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Vendor, .env, storage
    location ~ ^/(\.env|composer\.(json|lock)|package\.json) {
        deny all;
    }
}
```

### PHP-FPM Configuration

```ini
; /etc/php/8.3/fpm/pool.d/www.conf

[www]
user = www-data
group = www-data

; Socket (Nginx ile eyni serverdedirse)
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data

; Process Management
pm = dynamic
pm.max_children = 50        ; Max worker sayi
pm.start_servers = 10       ; Baslangicda worker sayi
pm.min_spare_servers = 5    ; Min bosda olan worker
pm.max_spare_servers = 20   ; Max bosda olan worker
pm.max_requests = 500       ; Memory leak qarsisi (500 request-den sonra restart)

; Status page (monitoring ucun)
pm.status_path = /status

; Slow log (yavas request-leri izlemek)
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s
request_terminate_timeout = 30s

; PHP settings per pool
php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
php_value[memory_limit] = 256M
php_value[max_execution_time] = 30
php_value[upload_max_filesize] = 10M
php_value[post_max_size] = 12M
```

### Nginx Caching for Laravel API

```nginx
# Cache zone teyin et
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=api_cache:10m
                 max_size=1g inactive=60m use_temp_path=off;

server {
    # ...

    # Cached API endpoints
    location /api/products {
        proxy_cache api_cache;
        proxy_cache_valid 200 5m;           # 200 response-u 5 deqiqe cache
        proxy_cache_valid 404 1m;           # 404-u 1 deqiqe
        proxy_cache_use_stale error timeout updating; # Error zamani kohne cache istifade
        proxy_cache_key "$request_uri";
        add_header X-Cache-Status $upstream_cache_status;

        # Auth olan request-leri cache etme
        proxy_cache_bypass $http_authorization;
        proxy_no_cache $http_authorization;

        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### Laravel Response Cache

```php
// Laravel terefinden cache header teyin etmek
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheResponse
{
    public function handle(Request $request, Closure $next, int $minutes = 5)
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', "public, max-age=" . ($minutes * 60));
            $response->headers->set('ETag', md5($response->getContent()));
        }

        return $response;
    }
}

// Route-da istifade
Route::get('/api/products', [ProductController::class, 'index'])
    ->middleware('cache.response:10'); // 10 deqiqe
```

### Multiple Backend Routing

```nginx
# Ferqli servisler ucun reverse proxy

# API service
upstream api_backend {
    server 10.0.1.1:8000;
    server 10.0.1.2:8000;
}

# Admin service
upstream admin_backend {
    server 10.0.2.1:8000;
}

# WebSocket service
upstream ws_backend {
    server 10.0.3.1:6001;
}

server {
    listen 443 ssl http2;

    # API requests
    location /api/ {
        proxy_pass http://api_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Admin panel
    location /admin/ {
        proxy_pass http://admin_backend;
        proxy_set_header Host $host;
    }

    # WebSocket
    location /ws/ {
        proxy_pass http://ws_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400s;
    }
}
```

## Interview Sualları

### 1. Forward proxy ve reverse proxy arasinda ferq nedir?
**Cavab:** Forward proxy client terefinde durur, client-in internet-e cixisini kontrol edir, client IP-sini gizledir. Reverse proxy server terefinde durur, servere gelenleri kontrol edir, server IP-sini gizleder. Forward = client ucun, Reverse = server ucun.

### 2. Nginx ve PHP-FPM nece birlikde isleyir?
**Cavab:** Nginx HTTP request qebul edir, static faylları ozü serve edir, PHP fayllarini FastCGI protokolu ile PHP-FPM-e gonderir. PHP-FPM worker process-ler pool-u saxlayir, her worker bir PHP request-i isleyir ve neticeni Nginx-e qaytarir.

### 3. SSL termination nedir?
**Cavab:** Reverse proxy-nin HTTPS traffic-i decypt edib backend serverlere plain HTTP gondermesidir. Ustulukleri: bir yerde SSL certificate, backend-ler daha suretli, sertifikat idaresi asan. Backend-ler internal network-de olmalıdır.

### 4. Nginx-de `try_files` nedir?
**Cavab:** Nginx-e fayl axtarish sirasi verir. `try_files $uri $uri/ /index.php?$query_string` - evvelce faylı tap, sonra qovlugu yoxla, tapilmazsa index.php-ye yonlendir. Bu Laravel-in routing-inin islemesi ucun lazimdir.

### 5. PHP-FPM process manager modlari nelerdir?
**Cavab:** `static` - sabit worker sayi (en yaxsi performance), `dynamic` - min/max arasi worker (umumiyyetle en yaxsi secim), `ondemand` - lazim olanda yaradir (az memory, cox latency). Production-da adeten dynamic istifade olunur.

### 6. Reverse proxy nece tehlukesizlik temin edir?
**Cavab:** Backend IP-lerini gizleyir, DDoS filterleme, rate limiting, WAF funksionalligi, SSL termination, request filtering (boyuk request-leri reject), gizli faylları bloklama (.env, .git).

### 7. pm.max_children nece hesablanir?
**Cavab:** `Movcud RAM / Her PHP process-in orta memory istifadesi`. Meselen: 4GB RAM, her process ~50MB = 80 max_children. Amma OS ve basqa servisler ucun 30% saxlayin = ~50 max_children.

## Best Practices

1. **Unix socket istifade edin** - TCP port yerine (eyni server-de daha suretli)
2. **Static faylları Nginx serve etsin** - PHP-ye gondermemek
3. **Gzip/Brotli aktiv edin** - Response size azaldir
4. **Buffer size tuning** - Boyuk response-lar ucun buffer artirin
5. **Worker connection tuning** - `worker_connections 1024` ve ya daha cox
6. **Keepalive backend** - Backend-e keepalive connection
7. **Gizli faylları bloklayın** - `.env`, `.git`, `vendor`
8. **Rate limiting** - `limit_req_zone` ile request mehdudlashdirin
9. **pm.max_requests** - Memory leak-den qorunmaq ucun worker restart
10. **Access log format** - JSON format ile structured logging
