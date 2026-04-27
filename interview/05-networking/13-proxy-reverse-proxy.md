# Proxy vs Reverse Proxy (Middle ⭐⭐)

## İcmal

Proxy və reverse proxy ikisi də "aracı" rolunda işləyir, lakin fərqli istiqamətdə. Forward proxy client tərəfindən işləyir — client adından sorğu göndərir. Reverse proxy server tərəfindən işləyir — gələn sorğuları backend server-lərə yönləndirir. Backend developer kimi reverse proxy (Nginx, HAProxy, Envoy) günlük işin bir hissəsidir: load balancing, SSL termination, caching, rate limiting, zero-downtime deployment. Interview-larda bu mövzu infrastructure, microservices, API gateway sualları ilə birlikdə gəlir.

## Niyə Vacibdir

Reverse proxy production arxitekturasının ayrılmaz hissəsidir. Nginx olmadan bir production deployment düşünmək demək olar ki, mümkün deyil. Interviewer bu mövzuda yoxlayır: "SSL termination nə deməkdir? Load balancer algoritmlərini bilirsizmi? Canary deployment üçün Nginx necə konfiqurasiya olunur? Service mesh reverse proxy-dən fərqlidirmi?" Bu bilgi microservices dizaynı, CDN arxitekturası, zero-downtime deployment zamanı birbaşa lazımdır.

## Əsas Anlayışlar

**Forward Proxy (Client-side proxy):**
- Client tərəfindədir — client-in adından sorğu göndərir, server yalnız proxy-nin IP-sini görür
- Client real IP-si server-dən gizlənir (anonymity, privacy)
- Use case: Corporate firewall (müəyyən saytlara girişi bloklamaq/icazə vermək), content filtering, geo-restriction bypass (VPN bənzər), egress traffic monitoring
- DNS: Client proxy-nin IP-sinə qoşulur, proxy istədiyini resolve edir
- Caching: Eyni resursu çox user tələb edərsə bir dəfə çəkib cache edir — bandwidth qənaəti
- Authentication: Corporate proxy istifadəçini authenticate edə bilər, traffic log edə bilər
- CONNECT method: HTTPS tunneling üçün — proxy TCP tunnel açır, TLS client-dən server-ə qədər gedir

**Reverse Proxy (Server-side proxy):**
- Server tərəfindədir — gələn sorğuları backend-lərə yönləndirir
- Client backend server-lərin real IP-sini bilmir — security izolasiyası
- **SSL/TLS Termination**: Client-dən HTTPS sorğusu gəlir, reverse proxy TLS-i burada decrypt edir, backend-ə plain HTTP göndərir. Backend TLS overhead-dən azad olur, certificate yalnız bir yerdə idarə olunur. Backend-lər arasında network trusted ise bu yetər
- **Load Balancing**: Alqoritmlər: Round Robin (sırayla), Weighted Round Robin, Least Connections (az aktif connection-lu server), IP Hash (sticky session — eyni client eyni backend-ə), Random, Least Response Time
- **Caching**: GET response-larını cache-ləmək — backend-ə yük azalır, latency azalır
- **Compression**: gzip/brotli — backend-in etməsinə ehtiyac yoxdur, CPU azalır
- **Rate Limiting**: IP ya da API key-ə görə sorğu limitləmək — DDoS protection
- **Health Check**: Backend-lərin sağlamlığını yoxlamaq — xəstə server-i rotation-dan çıxarmaq, recovery-dən sonra əlavə etmək
- **Request Buffering**: Yavaş client-lər backend connection-ı tutmasın — reverse proxy client-dən tam request-i alıb backend-ə göndərir (slow loris mitigation)
- **A/B Testing / Canary Deployment**: Trafiki müxtəlif backend version-lara bölmək — `weight=95` vs `weight=5`

**Nginx vs HAProxy vs Envoy:**
- Nginx: HTTP/HTTPS reverse proxy + static file server. Konfigurasiya asan, event-driven model. En geniş istifadə olunan
- HAProxy: Layer 4 (TCP) + Layer 7 (HTTP) load balancing. Ultra-high performance (1M+ req/s), connection-level statistics, çox detaylı stats. Database load balancing üçün mükəmməl
- Envoy: Modern proxy, xDDS API ilə dynamic konfiqurasiya, gRPC dəstəyi, tracing, circuit breaker built-in. Service mesh-in (Istio, Linkerd) sidecar proxy-si

**Layer 4 vs Layer 7 Load Balancing:**
- Layer 4 (TCP/UDP): IP + port əsasında yönləndirmə. HTTP content görünmür. Çox sürətli, az CPU. Database, SMTP, game server üçün. Connection-ı split etmir — TCP-ni birbaşa forward edir
- Layer 7 (HTTP): HTTP content-ə (URL path, header, cookie, query param) görə yönləndirmə. `/api` → API server, `/static` → CDN, `/admin` → admin service. Daha güclü routing, amma daha çox CPU. Session affinity cookie-yə görə mümkündür

**API Gateway vs Reverse Proxy:**
- Reverse proxy: Low-level routing, SSL, load balancing — infrastructure concern
- API Gateway: Reverse proxy + authentication, authorization, rate limiting (API key-ə görə), request transformation, response aggregation, analytics, developer portal, quota management. Kong, AWS API Gateway, Apigee, Traefik

**Service Mesh vs Reverse Proxy:**
- Reverse proxy: Centralized edge proxy — North-South traffic (client ↔ service)
- Service mesh: Hər service yanında sidecar proxy (Envoy). East-West traffic (service ↔ service). mTLS automatic, observability per-service, circuit breaker per-service, retry automatic. Istio, Linkerd

**X-Forwarded-For, X-Real-IP:**
- Reverse proxy arxasında client-in real IP-si backend-ə çatmır — proxy IP görünür
- `X-Forwarded-For: client_ip, proxy1_ip, proxy2_ip` — proxy chain-i göstərir
- `X-Real-IP: client_ip` — Nginx-in sadə header-ı
- Laravel-də `TrustProxies` middleware — bu header-ları trust etmək üçün

## Praktik Baxış

**Interview-da yanaşma:**
Forward vs Reverse proxy ayrımını aydın etdikdən sonra reverse proxy-nin production use-case-lərini izah edin: SSL termination, load balancing, caching, rate limiting, canary deployment. Sonra Layer 4 vs Layer 7 ayrımını, API Gateway ilə fərqi qeyd edin.

**Follow-up suallar (top companies-da soruşulur):**
- "SSL termination nə zaman backend-ə qədər uzatmalıyıq (end-to-end TLS)?" → PCI DSS, HIPAA kimi compliance tələb edən hallarda, ya da untrusted internal network-lərdə (multi-tenant cloud). Backend-ə qədər TLS = mTLS optimal
- "Blue-green deployment üçün reverse proxy necə konfiqurasiya olunur?" → Upstream weight-ini dəyişmək: 100% blue → `weight=90/10` → 50/50 → 0/100 green. Zero-downtime
- "Nginx-in worker_processes/worker_connections parametrləri niyə vacibdir?" → event-driven model — hər worker process non-blocking async connection-ları handle edir. `worker_processes = CPU core sayı`, `worker_connections = 1024+`
- "X-Forwarded-For-u trust etmək niyə risklidir?" → Client header-ı fake edə bilər: `X-Forwarded-For: 127.0.0.1`. Yalnız trusted proxy-nin IP-sindən gələn header-ı qəbul et. Laravel `TrustProxies` middleware-i konfiqurasiya et
- "keepalive connection-larının faydası nədir?" → Nginx ↔ Backend arasında TCP connection-ı qapat — hər request üçün yeni TCP handshake yox. `keepalive 32;` upstream-da
- "Canary deployment-da 5% trafiki yeni versiyaya göndərmək üçün?" → Nginx `upstream` blokunda `weight` parametri: `server v1 weight=95; server v2 weight=5;`. Header-a görə: `$cookie_canary` ilə seçim
- "Nginx proxy_buffering off nə zaman lazımdır?" → SSE (Server-Sent Events) — response stream real-time çatdırılmalıdır. Buffering SSE-ni sındırır. WebSocket üçün `upgrade` directive

**Ümumi səhvlər (candidate-ların etdiyi):**
- Proxy ilə reverse proxy-ni qarışdırmaq — "proxy = anonimlik" düşüncəsi
- SSL termination sonrası backend-ə HTTP göndərməyin trusted network tələb etdiyini qeyd etməmək
- Health check olmadan load balancing konfigurasiya etmək — dead backend-lərə trafik göndərilir
- `X-Forwarded-For`-u sanitize etmədən rate limiting üçün istifadə etmək — IP spoofing

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Layer 4 vs Layer 7 load balancing-in konkret use-case-lərini, service mesh sidecar pattern-inin centralized reverse proxy-dən üstünlüklərini (mTLS automatic, per-service circuit breaker), ya da canary deployment üçün weighted routing implementasiyasını izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"You're deploying a Laravel application with multiple worker nodes. How would you set up Nginx as a reverse proxy, and what features would you use?"

### Güclü Cavab

Laravel deployment üçün Nginx reverse proxy belə konfiqurasiya olunur:

**SSL termination**: Nginx bütün HTTPS sorğularını qəbul edir, TLS-i burada decrypt edir. Backend PHP-FPM server-lərə plain HTTP göndərilir — PHP serverları certificate idarə etmir, TLS overhead yoxdur. Internal network trusted-dir.

**Load balancing**: `upstream` blokunda üç PHP-FPM worker: `least_conn` alqoritmi ilə ən az aktiv connection-lu server-ə göndərilir. Health check: hər 5s bir backend-i yoxla, 3 uğursuz cəhddən sonra rotation-dan çıxar.

**Rate limiting**: `/api` endpoint-lər üçün IP-ə görə limitlər: abuse + DDoS qorunması.

**Caching**: Public endpoint-ləri (məs: `GET /api/products`) Nginx proxy_cache ilə cache et — Laravel-ə yük getmir.

**Gzip**: Bütün response-ları compress et — bandwidth azalır.

**Canary deployment**: Yeni version-u `weight=5` ilə başla, izlə, artır.

### Kod Nümunəsi

```nginx
# /etc/nginx/conf.d/laravel.conf

# Upstream — load balancing
upstream php_workers {
    least_conn;  # Az aktiv connection-lu server-ə göndər

    server 10.0.1.10:9000 weight=1 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:9000 weight=1 max_fails=3 fail_timeout=30s;
    server 10.0.1.12:9000 weight=1 max_fails=3 fail_timeout=30s;

    # Nginx ↔ Backend connection reuse
    keepalive 32;
}

# Canary deployment upstream
upstream php_workers_canary {
    least_conn;
    server 10.0.2.10:9000;  # Yeni version
}

# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_conn_zone $binary_remote_addr zone=conn:10m;

# Cache zone
proxy_cache_path /var/cache/nginx/app levels=1:2
    keys_zone=api_cache:10m max_size=1g inactive=60m;

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name app.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name app.example.com;

    # SSL Termination
    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;

    # Gzip compression
    gzip on;
    gzip_min_length 256;
    gzip_types application/json text/plain text/css application/javascript application/xml;

    # Security headers
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    root /var/www/laravel/public;

    # Connection limiting
    limit_conn conn 20;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass    php_workers;
        fastcgi_index   index.php;
        fastcgi_param   SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include         fastcgi_params;
        fastcgi_param   HTTPS on;
        fastcgi_param   HTTP_X_REAL_IP $remote_addr;
        fastcgi_param   HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;

        # Request buffering (slow loris mitigation)
        fastcgi_request_buffering on;
        fastcgi_read_timeout 60;
    }

    # API rate limiting + caching
    location /api/ {
        # Rate limiting — burst icazəli
        limit_req zone=api burst=20 nodelay;
        limit_req_status 429;

        # Cache GET sorğuları (authenticated deyilsə)
        proxy_cache         api_cache;
        proxy_cache_valid   200 1m;
        proxy_cache_valid   404 10s;
        proxy_cache_methods GET HEAD;
        proxy_cache_bypass  $http_authorization $http_x_bypass_cache;
        proxy_no_cache      $http_authorization;
        add_header          X-Cache-Status $upstream_cache_status;

        try_files $uri /index.php?$query_string;
    }

    # Login brute-force protection
    location /api/auth/login {
        limit_req zone=login burst=3 nodelay;
        limit_req_status 429;
        try_files $uri /index.php?$query_string;
    }

    # Canary deployment — 5% trafiki yeni versiyaya
    location /api/v2/ {
        # Cookie-based canary routing
        set $backend php_workers;
        if ($cookie_canary = "v2") {
            set $backend php_workers_canary;
        }
        # Random 5% seçim (weight ilə)
        # split_clients "$request_id" $backend {
        #     5%  php_workers_canary;
        #     *   php_workers;
        # }

        fastcgi_pass $backend;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    }

    # Static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|woff2|woff|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Honeypot — bura gələn IP-ləri blok et
    location ~ /\.env|/wp-admin|/phpmyadmin {
        return 444;  # Connection drop, cavab yoxdur
    }
}
```

```php
// Laravel — TrustProxies middleware
// app/Http/Middleware/TrustProxies.php
class TrustProxies extends Middleware
{
    // Production-da load balancer/Nginx IP-ləri
    protected $proxies = [
        '10.0.0.0/8',      // Private network
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    // Hansı header-ları trust et
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}

// config/app.php-da TrustProxies middleware-i ilk qoy
// app/Http/Kernel.php → $middleware array-in başında
```

```
Architecture Overview:

           Internet
               │
         ┌─────▼─────┐
         │    CDN     │ (Cloudflare / CloudFront)
         │ (Edge PoP) │ — Static assets, DDoS mitigation
         └─────┬─────┘
               │ HTTPS
         ┌─────▼──────┐
         │   Nginx    │ ← SSL Termination, Rate Limiting, Caching
         │ (Reverse   │ ← Load Balancing, Compression, Security Headers
         │  Proxy)    │
         └──┬──┬──┬───┘
            │  │  │ HTTP (internal)
     ┌──────┘  │  └──────┐
     ▼         ▼         ▼
  [PHP-FPM] [PHP-FPM] [PHP-FPM]   ← Laravel Workers
     │         │         │
     └─────────┼─────────┘
               │
     ┌─────────▼──────────┐
     │ MySQL + Redis Cache│
     └────────────────────┘
```

## Praktik Tapşırıqlar

1. Nginx-i Docker-da Laravel üçün konfigurasiya edin: SSL termination + PHP-FPM upstream
2. `least_conn` vs `round_robin` load balancing alqoritmlərini Apache Benchmark (ab) ilə yük altında test edin
3. `X-Forwarded-For` ilə Laravel-in real IP-ni log etməsini konfigurasiya edin, `$request->ip()` doğru IP qaytarırmı?
4. Nginx rate limiting: IP-ə görə `/api` endpoint-lərini limit edin, `429 Too Many Requests` cavabını curl ilə test edin
5. Canary deployment: Nginx upstream weight-ini dəyişərək (90/10, 70/30, 50/50) trafiki tədricən yeni version-a keçirin
6. Proxy cache test: `X-Cache-Status: HIT` header-ını confirm edin, cache bypass header ilə keçin
7. HAProxy-ni database connection pooling üçün konfigurasiya edin: MySQL 3306-ya TCP load balancing

## Əlaqəli Mövzular

- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — SSL termination at reverse proxy, TLS konfiqurasiyası
- [HTTP Caching](09-http-caching.md) — Nginx proxy caching, `Vary` header, cache invalidation
- [Long Polling vs SSE vs WebSocket](07-polling-sse-websocket.md) — Nginx SSE proxy_buffering off, WebSocket upgrade
- [DDoS Protection](15-ddos-protection.md) — Rate limiting, connection limiting at reverse proxy
- [CORS](10-cors.md) — CORS handling at reverse proxy level, centralization
