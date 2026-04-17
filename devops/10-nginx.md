# Nginx Web Server

## Nədir? (What is it?)

Nginx (engine-x) yüksək performanslı web server, reverse proxy, load balancer və HTTP cache-dir. Apache-dən fərqli olaraq event-driven, asynchronous arxitekturaya sahibdir ki, bu da daha az yaddaş ilə daha çox bağlantını idarə etməyə imkan verir. Laravel deploy etmək üçün Nginx + PHP-FPM ən çox istifadə olunan kombinasiyadır.

## Əsas Konseptlər (Key Concepts)

### Nginx Quraşdırma və Əsas Əmrlər

```bash
# Quraşdırma
sudo apt update && sudo apt install nginx

# Əsas əmrlər
sudo systemctl start nginx
sudo systemctl stop nginx
sudo systemctl restart nginx
sudo systemctl reload nginx          # Konfiqurasiyanı yenidən yüklə (downtime yox)
sudo systemctl status nginx
sudo systemctl enable nginx          # Boot-da başlasın

# Konfiqurasiya test
sudo nginx -t                        # Sintaksis yoxla
sudo nginx -T                        # Bütün konfiqurasiyanı göstər

# Nginx versiyası
nginx -v                             # Qısa
nginx -V                             # Compile options ilə
```

### Konfiqurasiya Strukturu

```bash
# Fayl strukturu
/etc/nginx/
├── nginx.conf              # Əsas konfiqurasiya
├── sites-available/        # Bütün site konfiqurasiyaları
│   ├── default
│   └── laravel.conf
├── sites-enabled/          # Aktiv site-lar (symlink)
│   └── laravel.conf -> ../sites-available/laravel.conf
├── conf.d/                 # Əlavə konfiqurasiyalar
├── snippets/               # Reusable konfiqurasiya parçaları
├── mime.types              # MIME type mapping
└── modules-enabled/        # Yüklənmiş modullar
```

### Əsas nginx.conf

```nginx
# /etc/nginx/nginx.conf
user www-data;
worker_processes auto;               # CPU sayı qədər (auto = avtomatik)
pid /run/nginx.pid;
worker_rlimit_nofile 65535;          # Açıq fayl limiti

events {
    worker_connections 4096;          # Hər worker-ə düşən bağlantı
    multi_accept on;                  # Birdən çox bağlantını qəbul et
    use epoll;                        # Linux-da ən sürətli event metodu
}

http {
    # Əsas parametrlər
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    server_tokens off;                # Nginx versiyasını gizlə
    client_max_body_size 64M;         # Max upload ölçüsü

    # MIME types
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types
        text/plain
        text/css
        text/javascript
        application/javascript
        application/json
        application/xml
        image/svg+xml;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;

    # SSL
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # Virtual hosts
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

### Server Blocks (Virtual Hosts)

```nginx
# /etc/nginx/sites-available/laravel.conf
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;
    return 301 https://$server_name$request_uri;      # HTTP -> HTTPS redirect
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name example.com www.example.com;

    # SSL certificates
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    # Document root
    root /var/www/laravel/public;
    index index.php index.html;

    # Logging
    access_log /var/log/nginx/laravel_access.log;
    error_log /var/log/nginx/laravel_error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;

        # Timeouts
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }

    # Static files cache
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Gizli faylları bloklama
    location ~ /\.(?!well-known) {
        deny all;
    }

    # vendor qovluğuna giriş qadağan
    location ~ /vendor {
        deny all;
    }

    # Rate limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /login {
        limit_req zone=login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### Location Blocks

```nginx
# Əsas location tiplər (prioritet sırasına görə)

# 1) Tam uyğunluq (exact match)
location = /health {
    return 200 "OK";
    add_header Content-Type text/plain;
}

# 2) Preferential prefix (^~) - regex-dən əvvəl yoxlanır
location ^~ /static/ {
    alias /var/www/static/;
}

# 3) Regex - case sensitive (~)
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
}

# 4) Regex - case insensitive (~*)
location ~* \.(jpg|jpeg|png|gif)$ {
    expires 30d;
}

# 5) Prefix match (adi)
location /api/ {
    proxy_pass http://backend;
}

# 6) Default
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Reverse Proxy

```nginx
# Laravel API üçün reverse proxy
upstream laravel_backend {
    server 127.0.0.1:8000;
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
}

server {
    listen 80;
    server_name api.example.com;

    location / {
        proxy_pass http://laravel_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket dəstəyi (Laravel Broadcasting)
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;

        # Buffering
        proxy_buffering on;
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
    }
}
```

### Load Balancing

```nginx
# Round Robin (default)
upstream backend {
    server 10.0.1.10:8000;
    server 10.0.1.11:8000;
    server 10.0.1.12:8000;
}

# Weighted
upstream backend_weighted {
    server 10.0.1.10:8000 weight=5;     # 5x daha çox traffic
    server 10.0.1.11:8000 weight=3;
    server 10.0.1.12:8000 weight=1;
}

# Least connections
upstream backend_least {
    least_conn;
    server 10.0.1.10:8000;
    server 10.0.1.11:8000;
}

# IP Hash (session persistence)
upstream backend_iphash {
    ip_hash;
    server 10.0.1.10:8000;
    server 10.0.1.11:8000;
}

# Health check ilə
upstream backend_health {
    server 10.0.1.10:8000 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:8000 max_fails=3 fail_timeout=30s;
    server 10.0.1.12:8000 backup;       # Backup server
}
```

### Caching

```nginx
# Proxy cache konfiqurasiyası
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=app_cache:10m
                 max_size=1g inactive=60m use_temp_path=off;

server {
    location /api/ {
        proxy_pass http://backend;
        proxy_cache app_cache;
        proxy_cache_valid 200 10m;       # 200 cavabları 10 dəq cache et
        proxy_cache_valid 404 1m;        # 404 cavabları 1 dəq
        proxy_cache_use_stale error timeout updating;
        add_header X-Cache-Status $upstream_cache_status;

        # Cache bypass
        proxy_cache_bypass $http_cache_control;
        proxy_no_cache $arg_nocache;
    }

    # FastCGI cache (PHP üçün)
    fastcgi_cache_path /var/cache/nginx/fastcgi levels=1:2
                       keys_zone=php_cache:10m max_size=512m inactive=30m;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_cache php_cache;
        fastcgi_cache_valid 200 5m;
        fastcgi_cache_key "$scheme$request_method$host$request_uri";

        # Login olmuş istifadəçiləri cache etmə
        set $no_cache 0;
        if ($http_cookie ~* "laravel_session") {
            set $no_cache 1;
        }
        fastcgi_no_cache $no_cache;
        fastcgi_cache_bypass $no_cache;
    }
}
```

## PHP/Laravel ilə İstifadə

### PHP-FPM Konfiqurasiyası

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
[www]
user = www-data
group = www-data

listen = /var/run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data

; Process manager
pm = dynamic
pm.max_children = 50           ; Maksimum PHP process sayı
pm.start_servers = 10          ; Başlanğıcda işləyən process
pm.min_spare_servers = 5       ; Minimum boş process
pm.max_spare_servers = 20      ; Maksimum boş process
pm.max_requests = 500          ; Process yenidən başlamadan əvvəl max request

; Timeouts
request_terminate_timeout = 60s

; Status page
pm.status_path = /fpm-status
```

### Laravel Trusted Proxy (Load Balancer arxasında)

```php
<?php
// app/Http/Middleware/TrustProxies.php (Laravel 11+)
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(
        at: '*',   // Bütün proxy-lərə güvən (Load Balancer arxasında)
        headers: Request::HEADER_X_FORWARDED_FOR |
                 Request::HEADER_X_FORWARDED_HOST |
                 Request::HEADER_X_FORWARDED_PORT |
                 Request::HEADER_X_FORWARDED_PROTO
    );
})
```

### Nginx + Laravel Site Aktivləşdirmə

```bash
# Site konfiqurasiyası yaratmaq
sudo nano /etc/nginx/sites-available/laravel.conf

# Aktivləşdirmək (symlink)
sudo ln -s /etc/nginx/sites-available/laravel.conf /etc/nginx/sites-enabled/

# Default site-ı silmək
sudo rm /etc/nginx/sites-enabled/default

# Test və restart
sudo nginx -t && sudo systemctl reload nginx

# Permissions
sudo chown -R www-data:www-data /var/www/laravel/storage
sudo chown -R www-data:www-data /var/www/laravel/bootstrap/cache
sudo chmod -R 775 /var/www/laravel/storage
sudo chmod -R 775 /var/www/laravel/bootstrap/cache
```

## Interview Sualları

### S1: Nginx və Apache arasında əsas fərqlər nədir?
**C:** Nginx event-driven, asynchronous arxitekturalıdır - bir thread çox bağlantını idarə edir, az yaddaş istifadə edir. Apache process/thread-per-connection modelidir - hər bağlantı üçün ayrı process/thread yaradır, daha çox yaddaş istifadə edir. Nginx statik faylları çox sürətli verir. Apache .htaccess dəstəkləyir, Nginx dəstəkləmir. Laravel üçün Nginx + PHP-FPM daha performanslı və tövsiyə olunan kombinasiyadır.

### S2: try_files direktivi nə edir?
**C:** `try_files $uri $uri/ /index.php?$query_string;` - Nginx əvvəlcə faylı axtarır ($uri), sonra qovluğu ($uri/), tapmazsa request-i index.php-yə yönləndirir. Bu Laravel routing-in işləməsi üçün vacibdir. Bütün request-lər index.php-dən keçir, Laravel router URL-ə uyğun controller-i tapır.

### S3: Nginx-də rate limiting necə işləyir?
**C:** `limit_req_zone` ilə zone yaradılır: `limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;`. Sonra location-da istifadə olunur: `limit_req zone=api burst=20 nodelay;`. `rate` saniyədə icazə verilən request sayıdır. `burst` əlavə buffer-dir. `nodelay` burst request-lərini gecikdirmədən keçirir. API endpoint-lərini DDoS-dan qorumaq üçün istifadə olunur.

### S4: Reverse proxy nədir və niyə istifadə olunur?
**C:** Reverse proxy client ilə backend server arasında durur. Üstünlükləri: 1) Load balancing - traffic-i bir neçə server arasında paylamaq, 2) SSL termination - SSL-i Nginx-də bitirmək, backend-ə HTTP göndərmək, 3) Caching - tez-tez istənən cavabları cache etmək, 4) Security - backend server-ləri gizlətmək, 5) Compression - response-ları sıxışdırmaq. `proxy_pass` direktivi ilə konfiqurasiya olunur.

### S5: worker_processes və worker_connections nə edir?
**C:** `worker_processes` Nginx-in neçə worker process işlədəcəyini bildirir. `auto` qoyduqda CPU core sayı qədər olur. `worker_connections` hər worker-in eyni anda neçə bağlantını idarə edə biləcəyini bildirir. Ümumi bağlantı limiti = worker_processes * worker_connections. 4 CPU, 4096 connections = 16384 eyni vaxtda bağlantı.

### S6: Nginx-də SSL/TLS necə konfiqurasiya olunur?
**C:** `listen 443 ssl http2;` ilə SSL port-u açılır. `ssl_certificate` və `ssl_certificate_key` ilə sertifikat təyin olunur. `ssl_protocols TLSv1.2 TLSv1.3;` ilə protokol versiyaları, `ssl_ciphers` ilə şifrləmə metodları seçilir. Let's Encrypt/Certbot ilə pulsuz sertifikat almaq olar. HTTP-dən HTTPS-ə redirect `return 301 https://...` ilə edilir.

### S7: Nginx-də 502 Bad Gateway nə deməkdir?
**C:** 502 Nginx-in backend-ə (PHP-FPM) qoşula bilmədiyini göstərir. Səbəblər: 1) PHP-FPM işləmir - `systemctl status php8.3-fpm`, 2) Socket yolu yanlışdır - `fastcgi_pass` yoxla, 3) PHP-FPM socket permissions problemi, 4) PHP-FPM max_children dolub - process sayını artır, 5) PHP script timeout olub. Error log-a (`/var/log/nginx/error.log`) baxmaq ilk addımdır.

## Best Practices

1. **server_tokens off** - Nginx versiyasını gizlədin
2. **client_max_body_size** - Laravel upload limitinə uyğun təyin edin
3. **gzip aktiv edin** - Bandwidth-i azaldır, sürəti artırır
4. **Security headers** əlavə edin - X-Frame-Options, CSP, HSTS
5. **Static file caching** - CSS/JS/images üçün uzun expires təyin edin
6. **Rate limiting** istifadə edin - API və login endpoint-lərini qoruyun
7. **nginx -t** həmişə restart-dan əvvəl işlədin
8. **Ayrı log faylları** - Hər virtual host üçün ayrı access/error log
9. **Gizli faylları bloklayın** - `.env`, `.git` kimi faylları deny edin
10. **SSL best practices** - TLS 1.2+, güclü cipher-lər, HSTS aktiv edin
