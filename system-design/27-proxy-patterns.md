# Proxy Patterns

## Nədir? (What is it?)

Proxy - client və server arasında dayanan aralıq serverdir. Client-in sorğularını qəbul edir, lazım olduqda dəyişdirir, sonra target server-ə göndərir. Response də proxy üzərindən keçir. Proxy-lər bir çox məqsəd üçün istifadə olunur: security, caching, load balancing, logging, SSL termination.

İki əsas növ:
- **Forward Proxy** - client tərəfdə, client-lərin internetə çıxışını idarə edir
- **Reverse Proxy** - server tərəfdə, server-lərə daxil olan trafiki idarə edir

## Əsas Konseptlər (Key Concepts)

### 1. Forward Proxy

Forward proxy client adından internet-ə sorğu göndərir. Client birbaşa internet-ə deyil, proxy-yə qoşulur.

**İstifadə halları:**
- **Corporate networks** - şirkət daxili internet filtrasiyası
- **Content filtering** - müəyyən saytların bloklanması
- **Anonymity** - client IP gizlədilir (VPN kimi)
- **Caching** - tez-tez istifadə olunan resurslar cache olunur
- **Bypassing restrictions** - geographic restriction-ları keçmək

Nümunələr: Squid, CCProxy, privoxy

```
Client → Forward Proxy → Internet → Server
```

### 2. Reverse Proxy

Reverse proxy server adından client sorğularını qəbul edir. Client üçün proxy server görünür, əsl server gizlidir.

**İstifadə halları:**
- **Load balancing** - sorğuları backend server-lərə paylayır
- **SSL termination** - HTTPS proxy-də decode olunur, backend-ə HTTP gedir
- **Caching** - static content cache olunur
- **Compression** - response-u gzip/brotli ilə sıxışdırır
- **Security** - DDoS protection, WAF
- **URL rewriting** - path-lərin dəyişdirilməsi

Nümunələr: Nginx, HAProxy, Apache, Cloudflare, AWS ELB

```
Client → Internet → Reverse Proxy → Backend Servers
```

### 3. SSL/TLS Termination

SSL termination - HTTPS trafikin proxy-də decrypt olunub backend-ə HTTP olaraq göndərilməsidir.

Üstünlüklər:
- Backend-də CPU yükü azalır (SSL decryption expensive)
- Sertifikat idarəsi mərkəzləşir (bir yerdə renew)
- Load balancer SSL traffic-ni analiz edə bilir

Çatışmazlıq:
- Internal network-də traffic plain HTTP olur (zero-trust arxitekturaya zidd)
- Həll: SSL passthrough və ya internal TLS

### 4. Caching Proxy

Proxy static və dynamic content-i cache edir. Cache strategiyaları:
- **Time-based (TTL)** - N saniyə sonra yenilə
- **Cache-Control headers** - server göstərir nə qədər cache olsun
- **Conditional GET** - ETag, If-Modified-Since
- **Purge on update** - manual invalidation

### 5. Proxy Chains

Bir neçə proxy zəncir halında istifadə oluna bilər:

```
Client → Edge Proxy (Cloudflare) → Load Balancer (Nginx) → App Server
```

Hər layer müxtəlif məsuliyyətlər daşıyır: CDN, DDoS, load balancing, application.

### 6. API Gateway vs Reverse Proxy

API Gateway reverse proxy-nin xüsusi formasıdır. Fərqlər:
- Reverse proxy - ümumi, HTTP(S) routing
- API Gateway - API-spesifik features (rate limiting, auth, API versioning, transformation)
- API Gateway adətən reverse proxy üzərində qurulur (Kong, Nginx-based)

## Arxitektura (Architecture)

Laravel üçün tipik arxitektura:
```
Internet
    ↓
Cloudflare (DDoS, CDN, SSL)
    ↓
AWS ELB (Load Balancer)
    ↓
Nginx (Reverse Proxy, SSL Termination, Static Files)
    ↓
PHP-FPM (FastCGI Process Manager)
    ↓
Laravel Application
    ↓
MySQL / Redis
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Nginx + PHP-FPM Setup for Laravel

**/etc/nginx/sites-available/laravel-app.conf:**

```nginx
server {
    listen 80;
    server_name example.com www.example.com;

    # HTTP → HTTPS redirect
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name example.com www.example.com;

    # SSL configuration
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Laravel public directory
    root /var/www/laravel-app/public;
    index index.php index.html;

    charset utf-8;

    # Logging
    access_log /var/log/nginx/laravel-access.log;
    error_log /var/log/nginx/laravel-error.log warn;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Max upload size
    client_max_body_size 100M;

    # Timeouts
    client_body_timeout 60s;
    client_header_timeout 60s;
    keepalive_timeout 65s;
    send_timeout 60s;

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM handler
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include fastcgi_params;

        # Timeouts
        fastcgi_read_timeout 300;
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 60;

        # Buffers
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Block hidden files
    location ~ /\.(?!well-known) {
        deny all;
    }

    # Block sensitive files
    location ~* \.(env|log|git|htaccess)$ {
        deny all;
    }
}
```

### PHP-FPM Pool Configuration

**/etc/php/8.3/fpm/pool.d/laravel.conf:**

```ini
[laravel]
user = www-data
group = www-data

listen = /var/run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 1000
pm.process_idle_timeout = 30s

; Performance
request_terminate_timeout = 300
rlimit_files = 131072
rlimit_core = unlimited

; Logging
access.log = /var/log/php-fpm/laravel-access.log
slowlog = /var/log/php-fpm/laravel-slow.log
request_slowlog_timeout = 5s

; PHP settings
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[max_execution_time] = 300
php_admin_value[error_log] = /var/log/php-fpm/laravel-error.log
php_admin_flag[log_errors] = on

; Environment
env[HOSTNAME] = $HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[APP_ENV] = production
```

### Load Balancing with Nginx

```nginx
upstream laravel_backend {
    least_conn;
    server 10.0.1.10:9000 weight=3 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:9000 weight=2 max_fails=3 fail_timeout=30s;
    server 10.0.1.12:9000 weight=1 max_fails=3 fail_timeout=30s;
    server 10.0.1.13:9000 backup;

    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;

    location / {
        proxy_pass http://laravel_backend;
        proxy_http_version 1.1;

        # Headers to backend
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port $server_port;

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;

        # Buffering
        proxy_buffering on;
        proxy_buffer_size 8k;
        proxy_buffers 16 8k;
    }
}
```

### Caching Proxy Configuration

```nginx
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=laravel_cache:10m max_size=1g inactive=60m use_temp_path=off;

server {
    location /api/products {
        proxy_cache laravel_cache;
        proxy_cache_valid 200 10m;
        proxy_cache_valid 404 1m;
        proxy_cache_key "$scheme$request_method$host$request_uri";
        proxy_cache_use_stale error timeout updating http_500 http_502 http_503 http_504;
        proxy_cache_lock on;

        add_header X-Cache-Status $upstream_cache_status;

        proxy_pass http://laravel_backend;
    }
}
```

### Laravel Trust Proxy Configuration

```php
<?php
// app/Http/Middleware/TrustProxies.php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies = [
        '10.0.0.0/8',       // Internal network
        '172.16.0.0/12',    // Docker network
        '192.168.0.0/16',   // LAN
    ];

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
```

### Docker Compose Setup

```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
      - ./app:/var/www/html
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - php-fpm
    networks:
      - laravel

  php-fpm:
    build:
      context: .
      dockerfile: php-fpm.Dockerfile
    volumes:
      - ./app:/var/www/html
    environment:
      - APP_ENV=production
    networks:
      - laravel

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - laravel

  redis:
    image: redis:alpine
    networks:
      - laravel

networks:
  laravel:

volumes:
  db_data:
```

## Real-World Nümunələr

- **Cloudflare** - Global reverse proxy, CDN, DDoS protection
- **Nginx** - World's most popular web server, reverse proxy
- **HAProxy** - High-performance load balancer, TCP/HTTP proxy
- **Apache Traffic Server** - Yahoo/Apache-ın caching proxy-si
- **Varnish** - HTTP accelerator, dynamic content caching
- **Envoy** - Modern L7 proxy, service mesh (Istio üçün)
- **AWS ALB/CloudFront** - Managed reverse proxy, CDN
- **Squid** - Popyular forward proxy

## Interview Sualları

**Q1: Forward proxy və reverse proxy fərqi?**
Forward proxy - client tərəfdə, client adından server-lərə sorğu göndərir. Məqsəd: client identity gizlətmək, content filtering, caching. Reverse proxy - server tərəfdə, server-lər adından client sorğularını qəbul edir. Məqsəd: load balancing, SSL termination, security. Forward proxy client-i, reverse proxy server-i qoruyur.

**Q2: Niyə Nginx və PHP-FPM birlikdə istifadə olunur?**
Nginx event-driven, async arxitekturada hazırlanıb - minlərlə concurrent connection-u az resurs ilə idarə edir. PHP-FPM ayrıca process-lər kimi işləyir, PHP icrasına optimallaşıb. Nginx static file-ları sürətli serve edir, dynamic sorğuları FastCGI ilə PHP-FPM-ə göndərir. Bu arxitektura Apache+mod_php-dən daha performantdır.

**Q3: SSL termination-ın mənfi cəhətləri nədir?**
- Internal traffic plain HTTP olur (MITM risk internal network-də)
- Zero-trust arxitekturaya ziddir
- Compliance (PCI-DSS) tələbləri pozula bilər
Həll: SSL passthrough (proxy SSL-i decrypt etmir) və ya internal TLS (proxy decrypt edir, sonra yenidən encrypt edir backend-ə).

**Q4: Reverse proxy hansı üstünlüklər verir?**
1. Load balancing - traffic distribution
2. SSL termination - mərkəzləşdirilmiş cert management
3. Caching - static və dynamic content
4. Compression - gzip, brotli
5. Security - hidden backend IP, WAF, DDoS protection
6. Request routing - path, header, host-based
7. Rate limiting - abuse prevention
8. Monitoring - mərkəzləşdirilmiş logs

**Q5: PHP-FPM-də static, dynamic, və ondemand process manager fərqi?**
- **static** - sabit sayda child process (pm.max_children). Memory çox, amma sabit performance.
- **dynamic** - min/max spare servers arasında dəyişir. Balance variant, ən çox istifadə olunur.
- **ondemand** - yalnız lazım olanda yaradılır. Az memory, amma soyuq start var.

Production-da adətən dynamic istifadə olunur.

**Q6: Nginx worker_processes və worker_connections necə tənzimlənir?**
- **worker_processes** - CPU core sayı qədər (adətən `auto`)
- **worker_connections** - hər worker-in qəbul edə biləcəyi connection sayı (1024-10000)
- Max clients = worker_processes × worker_connections
- Reverse proxy olarsa: × 2 (client və backend connection)
- `ulimit -n` limitini də artırmaq lazımdır

**Q7: X-Forwarded-For header nədir?**
X-Forwarded-For (XFF) - proxy chain-dən keçən orijinal client IP-ni saxlayır. Format: `X-Forwarded-For: client, proxy1, proxy2`. Laravel-də `TrustProxies` middleware ilə `$request->ip()` orijinal IP-ni qaytarır. Qəbul etmədən əvvəl trusted proxy IP-ləri konfiqurasiya etmək vacibdir (spoofing riski).

**Q8: Nginx buffer tuning nə üçün lazımdır?**
Buffer-lər client və backend arasında ara yaddaşdır:
- **proxy_buffer_size** - response header-i üçün
- **proxy_buffers** - response body üçün
- **client_body_buffer_size** - request body üçün
Kiçik buffer disk I/O yaradır (yavaş), çox böyük memory israf edir. File upload çox olanda client_max_body_size və client_body_buffer_size artırmaq lazımdır.

**Q9: keepalive nə üçün lazımdır?**
Keepalive - eyni TCP connection-u çox sorğu üçün istifadə etmək. Upstream-də `keepalive 32` - hər worker 32 backend connection-u idle saxlayır. Hər sorğuda yeni TCP handshake (SYN/SYN-ACK/ACK) qarşısı alınır. HTTP/2 default keepalive istifadə edir.

**Q10: Blue-green deployment-ı Nginx ilə necə edirsən?**
```nginx
upstream blue { server 10.0.1.1:80; }
upstream green { server 10.0.1.2:80; }

# Production konfigurasiyasını `blue` və ya `green` qrupa yönləndir
upstream backend {
    server 10.0.1.1:80; # active
}
```
Deploy zamanı:
1. Green-ə yeni versiya deploy et
2. Green-i test et (internal)
3. Nginx config-də upstream-i green-ə dəyişdir
4. `nginx -s reload` (zero-downtime)
5. Problem olsa, blue-ya geri dön

## Best Practices

1. **Always terminate SSL at edge** - Mərkəzləşdirilmiş cert management
2. **Use HTTP/2** - Multiplexing, header compression
3. **Enable gzip/brotli** - Bandwidth qənaəti
4. **Set appropriate timeouts** - Client və backend üçün
5. **Hide backend IPs** - Security layer
6. **Log detailed access** - Debug və security üçün
7. **Rate limit at proxy** - DDoS protection
8. **Health check backends** - Unhealthy server-ləri trafikdən çıxar
9. **Use HSTS** - HTTPS məcburi et
10. **Monitor cache hit ratio** - Caching effektivliyi üçün
11. **Separate static and dynamic** - Static content Nginx-dən direkt serve olunsun
12. **Keepalive connections** - Backend-ə olan connection-ları reuse et
13. **Tune worker processes** - CPU və load-a görə
14. **Security headers** - X-Frame-Options, CSP, HSTS
15. **Zero-downtime reload** - `nginx -s reload` istifadə et
