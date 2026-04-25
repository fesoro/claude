# Reverse Proxy (Middle)

## İcmal

Reverse proxy client request-lərini qəbul edib backend serverlərə yönləndirən serverdir. Client, reverse proxy-nin arxasındakı serverlərin varlığından xəbərdar deyil. Nginx, Apache, HAProxy, Caddy ən populyar reverse proxy-lərdir.

```
Forward Proxy (Client tərəfindədir):
  Client --> [Forward Proxy] --> Internet --> Server
  Client özünü gizlədir (VPN kimi)

Reverse Proxy (Server tərəfindədir):
  Client --> Internet --> [Reverse Proxy] --> Backend Server(s)
  Server özünü gizlədir, client yalnız proxy-ni görür
```

## Niyə Vacibdir

Reverse proxy müasir web arxitekturasının əsas komponentidir: SSL termination, static file serving, rate limiting, caching və security kimi cross-cutting concern-ləri backend-dən ayırır. PHP-FPM ilə birlikdə Nginx istifadəsi Laravel tətbiqləri üçün standart deployment modelidir. Bir reverse proxy arxasında bir neçə backend serveri gizlədib trafiki idarə etmək həm performansı artırır, həm də sistemi daha etibarlı edir.

## Əsas Anlayışlar

### Forward vs Reverse Proxy

```
Forward Proxy:
  - Client tərəfində durur
  - Client internet-ə çıxışı kontrol edir
  - Client IP-sini gizlədir
  - Content filtering, caching
  - Məsələn: Squid, corporate proxy

  [Client A] --\
  [Client B] ----> [Forward Proxy] ----> [Internet] ----> [Server]
  [Client C] --/

Reverse Proxy:
  - Server tərəfində durur
  - Serverə gələnləri kontrol edir
  - Server IP-sini gizlədir
  - Load balancing, SSL, caching, security
  - Məsələn: Nginx, Apache

  [Client] ----> [Internet] ----> [Reverse Proxy] ----> [Server A]
                                        |-----------> [Server B]
                                        |-----------> [Server C]
```

### Reverse Proxy Funksiyaları

```
1. Load Balancing
   Trafiki bir neçə backend arasında paylaşdırmaq

2. SSL Termination
   HTTPS-i açıb backend-ə HTTP göndərmək

3. Caching
   Static content və API response-ları cache etmək

4. Compression
   Response-ları gzip/brotli ilə sıxmaq

5. Security
   Backend serverləri gizləmək, DDoS qorunma, WAF

6. Request/Response Modification
   Header əlavə/silmək, URL rewrite

7. Static File Serving
   Statik faylları backend-ə göndərmədən birbaşa serve etmək

8. Rate Limiting
   Request sayını məhdudlaşdırmaq
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
  static    - Sabit sayda worker (ən yaxşı performance)
  dynamic   - Min/max arası worker sayını tənzimləyir
  ondemand  - Lazım olanda worker yaradır (az memory, çox latency)
```

### Caching Proxy

```
Nginx cache ilə:

  Request 1: /api/products
    -> Cache MISS -> PHP-FPM -> Response -> Cache-ə yaz -> Client-ə göndər
  
  Request 2: /api/products (5 saniyə içində)
    -> Cache HIT -> Client-ə göndər (PHP-FPM-ə getmir!)

  Cache-Control headerinə əsasən necə cache olunur:
    Cache-Control: public, max-age=300     (5 dəqiqə cache)
    Cache-Control: no-store                (cache etmə)
    Cache-Control: private                 (yalnız brauzer cache)
```

## Praktik Baxış

**Üstünlüklər:**
- Backend serverlərini internet-dən gizlətmək (security)
- SSL termination — sertifikat idarəsi bir yerdə
- Static faylları PHP-yə göndərmədən serve etmək (performance)
- Caching ilə backend yükünü azaltmaq

**Trade-off-lar:**
- Əlavə infrastructure komponenti — SPOF riski yaranır
- Yanlış konfigurasiya bütün sistemi iflic edə bilər
- SSL termination backend-ə plain HTTP göndərir — internal network trust tələb olunur

**Nə vaxt istifadə edilməməlidir:**
- Çox sadə, tək serverdə işləyən dev mühitlərində overhead-ə dəyməz (local docker-compose-da birbaşa PHP-FPM da olar)

**Anti-pattern-lər:**
- TCP port əvəzinə unix socket istifadə etməmək (eyni serverdə unix socket daha sürətlidir)
- Static faylları PHP-FPM-ə göndərmək
- `pm.max_children`-i RAM hesablamadan artırmaq (memory exhaustion)
- `.env`, `.git`, `vendor` üçün deny rule yazmamaq

## Nümunələr

### Ümumi Nümunə

Nginx reverse proxy olaraq:
1. Bütün HTTPS trafikini qəbul edir
2. SSL-i açır
3. Static faylları özü qaytarır
4. PHP fayllarını FastCGI protokolu ilə PHP-FPM-ə göndərir
5. Response-u sıxıb (gzip) clientə qaytarır

### Kod Nümunəsi

**Nginx Configuration for Laravel:**

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

    root /var/www/laravel/public;
    index index.php;

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

    # Static files — Nginx birbaşa serve edir (PHP-yə getmir)
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;

        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    # Gizli fayllar
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ ^/(\.env|composer\.(json|lock)|package\.json) {
        deny all;
    }
}
```

**PHP-FPM Configuration:**

```ini
; /etc/php/8.3/fpm/pool.d/www.conf

[www]
user = www-data
group = www-data

listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50        ; Max worker sayı
pm.start_servers = 10       ; Başlanğıcda worker sayı
pm.min_spare_servers = 5    ; Min boşda olan worker
pm.max_spare_servers = 20   ; Max boşda olan worker
pm.max_requests = 500       ; Memory leak qarşısı (500 request-dən sonra restart)

pm.status_path = /status
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s
request_terminate_timeout = 30s

php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
php_value[memory_limit] = 256M
php_value[max_execution_time] = 30
php_value[upload_max_filesize] = 10M
php_value[post_max_size] = 12M
```

**Nginx Caching for Laravel API:**

```nginx
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=api_cache:10m
                 max_size=1g inactive=60m use_temp_path=off;

server {
    location /api/products {
        proxy_cache api_cache;
        proxy_cache_valid 200 5m;
        proxy_cache_valid 404 1m;
        proxy_cache_use_stale error timeout updating;
        proxy_cache_key "$request_uri";
        add_header X-Cache-Status $upstream_cache_status;

        proxy_cache_bypass $http_authorization;
        proxy_no_cache $http_authorization;

        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

**Laravel Response Cache Middleware:**

```php
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

// Route-da istifadə
Route::get('/api/products', [ProductController::class, 'index'])
    ->middleware('cache.response:10'); // 10 dəqiqə
```

**Multiple Backend Routing:**

```nginx
upstream api_backend {
    server 10.0.1.1:8000;
    server 10.0.1.2:8000;
}

upstream admin_backend {
    server 10.0.2.1:8000;
}

upstream ws_backend {
    server 10.0.3.1:6001;
}

server {
    listen 443 ssl http2;

    location /api/ {
        proxy_pass http://api_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /admin/ {
        proxy_pass http://admin_backend;
        proxy_set_header Host $host;
    }

    location /ws/ {
        proxy_pass http://ws_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400s;
    }
}
```

## Praktik Tapşırıqlar

1. **Nginx + PHP-FPM quraşdırma:** Laravel layihəsi üçün sıfırdan Nginx konfiqurasiyası yazın. SSL, gzip, static file serving, PHP-FPM socket bağlantısını konfiqurasiya edin. `curl -I https://example.com` ilə response headerlərini yoxlayın.

2. **PHP-FPM tuning:** `pm.max_children` dəyərini serverinizin RAM-ına əsasən hesablayın. `pm.status` endpoint-ini aktiv edib `watch -n1 'curl -s localhost/status'` ilə real-time worker statistikasını izləyin.

3. **Nginx caching:** `/api/products` endpoint-i üçün Nginx-də 5 dəqiqəlik cache qurun. `X-Cache-Status` headerini izləyib HIT/MISS statistikasını müşahidə edin. Auth header olan request-lərin cache-lənmədiyini yoxlayın.

4. **Multiple backend routing:** Eyni Nginx-dən `/api/`, `/admin/`, `/ws/` yollarını müxtəlif backend-lərə yönləndirin. WebSocket üçün `Upgrade` headerini düzgün ötürdüyünüzü yoxlayın.

5. **Security audit:** `.env`, `.git`, `vendor` qovluqlarının Nginx tərəfindən bloqlandığını `curl https://example.com/.env` ilə yoxlayın — 403 almalısınız.

## Əlaqəli Mövzular

- [Load Balancing](18-load-balancing.md)
- [HTTPS / SSL / TLS](06-https-ssl-tls.md)
- [API Gateway](21-api-gateway.md)
- [Network Security](26-network-security.md)
- [CDN](20-cdn.md)
