# Nginx + PHP-FPM Container Setup

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

PHP-FPM özbaşına HTTP server deyil — FastCGI protokolu ilə işləyir. Qarşısında Nginx (və ya Apache, Caddy) olmalıdır. Konteyner dünyasında bu iki hissəni necə yerləşdirməyin 3 əsas yolu var:

1. **Eyni konteynerdə (supervisord)** — sadə, tək image, tək deploy
2. **Ayrı konteynerlərdə, TCP ilə** — 12-factor, hər biri ayrı ölçülənir
3. **Ayrı konteynerlərdə, Unix socket ilə (shared volume)** — TCP-dən bir az sürətli, amma daha çətin

Bu fayl hər üçünü göstərir, config-ləri verir və hansını nə vaxt seçməli olduğunu izah edir.

## Arxitektura Variantları

```
┌──────────────────────────────────────────────────────────────┐
│  Variant A: Sidecar (ayrı konteyner, TCP)                     │
│  [Nginx konteyner] --- TCP:9000 ---> [PHP-FPM konteyner]     │
│  Shared volume: public/ (static fayllar üçün)                 │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│  Variant B: Eyni konteyner, supervisord                       │
│  ┌─[supervisord]─────────────────────┐                        │
│  │  [Nginx] --- Unix socket ---> [PHP-FPM]                    │
│  └────────────────────────────────────┘                        │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│  Variant C: Ayrı konteyner, Unix socket (shared volume)       │
│  [Nginx] --- /var/run/php.sock (volume) ---> [PHP-FPM]        │
│  Shared: /var/www/html, /var/run                              │
└──────────────────────────────────────────────────────────────┘
```

## Variant A: Sidecar (Ən Çox İstifadə Olunan)

### `docker-compose.yml`

```yaml
services:
  app:
    build:
      context: .
      target: production
    # PHP-FPM 9000 portunda listen edir
    expose:
      - "9000"
    volumes:
      - public-assets:/var/www/html/public    # Nginx-lə paylaşılan
    environment:
      - APP_ENV=production
    networks:
      - app-net
  
  nginx:
    image: nginx:1.27-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - public-assets:/var/www/html/public:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
    depends_on:
      app:
        condition: service_healthy
    networks:
      - app-net

volumes:
  public-assets:

networks:
  app-net:
```

### Nginx `default.conf`

```nginx
# docker/nginx/default.conf
server {
    listen 80 default_server;
    server_name _;
    
    root /var/www/html/public;
    index index.php;
    
    # Logging stdout/stderr-ə (konteyner best practice)
    access_log /dev/stdout;
    error_log /dev/stderr warn;
    
    # Yükləmə limiti (php.ini ilə eyni olsun)
    client_max_body_size 20M;
    
    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
    gzip_min_length 256;
    
    # Static fayllar — PHP-yə ötürmə, birbaşa Nginx ver
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg|webp|avif)$ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    
    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP faylları FPM-ə ötür
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        
        # Üç konteyner adı "app" olan PHP-FPM service-nə göndər
        fastcgi_pass app:9000;
        
        fastcgi_index index.php;
        include fastcgi_params;
        
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTP_PROXY "";              # Httpoxy vulnerability-ə qarşı
        
        # Timeout-lar (uzun request-lər üçün)
        fastcgi_read_timeout 60;
        fastcgi_send_timeout 60;
        fastcgi_connect_timeout 10;
        
        # Buffer-lər (böyük response-lar üçün)
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }
    
    # Gizli fayllar (.env, .git və s.) əlçatan olmasın
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # FPM status (yalnız internal)
    location ~ ^/(fpm-status|fpm-ping)$ {
        allow 127.0.0.1;
        allow 10.0.0.0/8;
        allow 172.16.0.0/12;
        allow 192.168.0.0/16;
        deny all;
        
        fastcgi_pass app:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
    }
}
```

### Nginx `nginx.conf` (ana)

```nginx
# /etc/nginx/nginx.conf
user nginx;
worker_processes auto;
error_log /dev/stderr warn;
pid /var/run/nginx.pid;

events {
    worker_connections 2048;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for" '
                    'rt=$request_time uct="$upstream_connect_time" '
                    'uht="$upstream_header_time" urt="$upstream_response_time"';
    
    access_log /dev/stdout main;
    
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    
    server_tokens off;             # Nginx versiyasını gizlə
    
    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    include /etc/nginx/conf.d/*.conf;
}
```

## Variant B: Eyni Konteynerdə (Supervisord)

Sadəlik üçün hər ikisini bir konteynerdə işə salmaq — kiçik layihələr və VPS-lər üçün yaxşıdır.

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor tini \
    && mkdir -p /run/nginx /var/log/supervisor

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# ... (PHP extension-lar, composer, app copy) ...

EXPOSE 80
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
```

`supervisord.conf`:
```ini
[supervisord]
nodaemon=true
user=root
logfile=/dev/null
logfile_maxbytes=0
pidfile=/tmp/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
priority=5
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
stopsignal=QUIT
stopwaitsecs=30

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
priority=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
stopsignal=QUIT
stopwaitsecs=30
```

Bu variantda FPM-ə socket-lə çatmaq daha sürətlidir:
```nginx
# default.conf-da dəyiş:
fastcgi_pass unix:/var/run/php-fpm.sock;
```

## Variant C: Ayrı Konteyner + Unix Socket

TCP `app:9000` əvəzinə Unix socket istifadə etmək — localhost-da 5-10% daha sürətli. Amma Docker-də shared volume lazımdır:

```yaml
services:
  app:
    volumes:
      - php-socket:/var/run/php
    # php-fpm listen direktivini soketə dəyiş (www.conf-da)
    # listen = /var/run/php/php-fpm.sock
  
  nginx:
    volumes:
      - php-socket:/var/run/php:ro

volumes:
  php-socket:
```

**Real mühitdə dəyərmi?** Çox az halda. Kubernetes-də pod-lar arası Unix socket paylaşmaq çətindir. Production tövsiyə: **TCP ilə sidecar**.

## TCP vs Unix Socket — Performans

| Metod | Latency (p99) | Setup mürəkkəbliyi | K8s dostu? |
|-------|---------------|---------------------|-------------|
| TCP localhost | ~0.5 ms | Ən sadə | Bəli |
| Unix socket | ~0.3 ms | Shared volume lazımdır | Pod daxilində bəli |
| TCP remote (pod) | ~1-2 ms | Standart | Bəli (sidecar) |

**Qərar:** 10k+ req/sec-ə çatana qədər fərq hiss olunmur. Sadə saxla — TCP sidecar.

## Nginx HTTPS (SSL Termination)

### Sənədlənmiş Certs (dev)

```dockerfile
FROM nginx:alpine
COPY certs/local.crt /etc/nginx/certs/
COPY certs/local.key /etc/nginx/certs/
```

### Let's Encrypt (prod) — Acme.sh və ya certbot

Tövsiyə: SSL-i Nginx-də termine etməyin — **Load Balancer-də edin** (ALB, GCLB, Nginx Ingress). Konteyner yalnız HTTP-dir, sertifikat orkestrasiya səviyyəsində dəyişir.

Əgər single-server deploy edirsinizsə (VPS, Hetzner), Traefik və ya Caddy istifadə edin — avtomatik Let's Encrypt:

```yaml
services:
  caddy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
      - public-assets:/var/www/html/public:ro

volumes:
  caddy_data:
  caddy_config:
```

`Caddyfile`:
```
example.com {
    root * /var/www/html/public
    php_fastcgi app:9000
    file_server
    encode gzip
    log {
        output stdout
    }
}
```

## Nginx Tuning (Production)

```nginx
# http context-də
worker_processes auto;           # CPU sayına görə
worker_rlimit_nofile 65535;      # FD limit
worker_connections 4096;         # per worker

# FastCGI cache (GET request-lər üçün səhifə cache)
fastcgi_cache_path /var/cache/nginx/fastcgi levels=1:2 keys_zone=fpm_cache:100m inactive=60m max_size=500m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";
fastcgi_cache_use_stale error timeout updating invalid_header http_500 http_503;

# server block-da:
location ~ \.php$ {
    # ... standart fastcgi ...
    
    # Yalnız GET-ləri cache et, authenticated user-ləri cache etmə
    fastcgi_cache fpm_cache;
    fastcgi_cache_valid 200 302 10m;
    fastcgi_cache_valid 404 1m;
    fastcgi_cache_bypass $http_cache_control $cookie_laravel_session;
    fastcgi_no_cache $http_cache_control $cookie_laravel_session;
    
    add_header X-Cache $upstream_cache_status;
}
```

Laravel üçün cache kilid: authenticated session cookie olanda cache bypass.

## Nginx Log-lar JSON Format-ında

Loki/CloudWatch-a göndərmək üçün JSON log daha yaxşıdır:

```nginx
log_format json_combined escape=json
    '{'
        '"time":"$time_iso8601",'
        '"remote_addr":"$remote_addr",'
        '"request_method":"$request_method",'
        '"uri":"$request_uri",'
        '"status":$status,'
        '"body_bytes_sent":$body_bytes_sent,'
        '"request_time":$request_time,'
        '"upstream_response_time":"$upstream_response_time",'
        '"user_agent":"$http_user_agent",'
        '"x_forwarded_for":"$http_x_forwarded_for",'
        '"referer":"$http_referer"'
    '}';

access_log /dev/stdout json_combined;
```

## Tipik Səhvlər (Gotchas)

### 1. `SCRIPT_FILENAME` yanlış

**Xəta:** `File not found` hər PHP request-də.

**Səbəb:** `fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;` — amma Nginx və PHP-FPM konteynerləri arasında fərqli yol var. PHP-FPM `/var/www/html/public/index.php` axtarır, amma o yol yoxdur.

**Həll:** Hər iki konteynerdə eyni mount path olmalıdır (volume ilə) və `root` Nginx-də `/var/www/html/public` olmalıdır.

### 2. Static fayllar 404

**Səbəb:** Nginx konteynerində `public/build/` qovluğu yoxdur — PHP image-də build olunub, Nginx onu görmür.

**Həll:** Shared volume (yuxarıda `public-assets`) və ya Nginx image-ə assets-i `COPY --from=app` ilə əlavə et.

### 3. `client_max_body_size` kiçikdir

**Xəta:** Big file upload-da `413 Request Entity Too Large`.

**Həll:** Nginx `client_max_body_size 20M;` + PHP `post_max_size = 20M`, `upload_max_filesize = 20M`.

### 4. Trusted Proxies

Laravel `x-forwarded-*` header-lərini default qəbul etmir. Load Balancer arxasında:
```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = '*';    // və ya specific CIDR
```

### 5. Httpoxy vulnerability

Default-da FastCGI `HTTP_PROXY` environment-i ötürür — təhlükəsizlik problemi.

**Həll:** `fastcgi_param HTTP_PROXY "";` həmişə.

## Interview sualları

- **Q:** PHP-FPM niyə Nginx arxasındadır?
  - PHP-FPM HTTP server deyil, FastCGI protokolu ilə işləyir. Nginx (və ya Apache/Caddy) HTTP-ni qəbul edib FastCGI-yə tərcümə edir. Nginx həm də static fayllar, gzip, SSL, caching edir.

- **Q:** Eyni konteyner vs ayrı konteyner?
  - Eyni (supervisord): sadə, tək deploy, kiçik layihələr. Ayrı (sidecar): 12-factor, müstəqil scale, K8s üçün tövsiyə.

- **Q:** Unix socket TCP-dən nə qədər sürətlidir?
  - ~30% daha aşağı latency (0.3 vs 0.5 ms), amma real trafik-də fərq hiss olunmur. K8s-də setup çətindir — TCP sidecar tövsiyə.

- **Q:** SSL-i konteynerdə yoxsa Load Balancer-də?
  - **Load Balancer-də** — sertifikat orkestrasiya səviyyəsində, konteynerlər HTTP-dir. Single-server-də Caddy/Traefik avtomatik Let's Encrypt.

- **Q:** FastCGI cache-i Laravel-də necə qoruyursuz?
  - Session cookie (`laravel_session`) olan request-ləri `fastcgi_cache_bypass` ilə cache-dən xaric et. Yalnız anonymous GET-ləri cache et.
