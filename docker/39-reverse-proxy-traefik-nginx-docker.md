# Reverse Proxy Pattern: Traefik və Nginx Docker-də

> **Səviyyə (Level):** ⭐⭐⭐ Senior
> **Oxu müddəti:** ~20-25 dəqiqə
> **Kateqoriya:** Docker / Networking

## Nədir? (What is it?)

Reverse proxy — client-in istəklərini qəbul edib backend servislərə yönləndirən proksi server-dir. "Forward proxy" client tərəfindədir (VPN kimi), "reverse proxy" server tərəfindədir.

Docker setup-da reverse proxy-nin rolu:
- **TLS termination** — HTTPS-i burada bağlayır, backend-lərlə HTTP danışır
- **Routing** — `api.example.com` → Laravel, `app.example.com` → Vue SPA
- **Load balancing** — bir neçə backend instance-ı arasında yük bölgüsü
- **Request modification** — header əlavə, rewrite, rate limiting
- **Single entry point** — bütün trafik 80/443 portundan keçir, backend-lər izolyasiyada

Docker world-də əsas 3 variant: **Traefik** (dynamic, label-based), **Nginx** (static, güclü), **Caddy** (auto-HTTPS, sadə).

## Əsas Konseptlər

### 1. Traefik — Docker-in Native Dostu

Traefik Docker labels oxuyur və konfiqurasiyanı **dinamik** yaradır. Yeni konteyner qalxanda — Traefik avtomatik görür və route əlavə edir. Restart lazım deyil.

**Əsas üstünlüklər:**
- Docker (və K8s, Consul, Nomad) ilə native inteqrasiya
- Avtomatik Let's Encrypt (ACME)
- Dashboard (UI) built-in
- HTTP/2, HTTP/3, WebSocket, gRPC hamısı dəstəklənir
- Middleware konsepti (rate limit, auth, headers)

### 2. Traefik docker-compose.yml — Tam Nümunə

```yaml
# docker-compose.yml
version: '3.9'

services:
  # ───────────────────────────────────────────────────
  # Traefik (reverse proxy)
  # ───────────────────────────────────────────────────
  traefik:
    image: traefik:v3.1
    container_name: traefik
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"  # dashboard (prod-da aç məyin)
    volumes:
      # Docker socket: Traefik konteynerlərə baxır
      - /var/run/docker.sock:/var/run/docker.sock:ro  # read-only mütləq!
      - ./letsencrypt:/letsencrypt
    command:
      - "--api.dashboard=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"  # yalnız label qoyulan servislər açılır
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      # HTTP → HTTPS redirect
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
      - "--entrypoints.web.http.redirections.entrypoint.scheme=https"
      # Let's Encrypt
      - "--certificatesresolvers.letsencrypt.acme.email=admin@example.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
      - "--certificatesresolvers.letsencrypt.acme.tlschallenge=true"
    networks:
      - web

  # ───────────────────────────────────────────────────
  # Laravel API
  # ───────────────────────────────────────────────────
  laravel:
    image: mycompany/laravel:1.0.0
    restart: unless-stopped
    environment:
      APP_ENV: production
      DB_HOST: postgres
      REDIS_HOST: redis
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.laravel.rule=Host(`api.example.com`)"
      - "traefik.http.routers.laravel.entrypoints=websecure"
      - "traefik.http.routers.laravel.tls.certresolver=letsencrypt"
      - "traefik.http.services.laravel.loadbalancer.server.port=80"
      # Middleware: rate limit
      - "traefik.http.routers.laravel.middlewares=ratelimit@docker"
      - "traefik.http.middlewares.ratelimit.ratelimit.average=100"
      - "traefik.http.middlewares.ratelimit.ratelimit.burst=200"
    networks:
      - web
      - backend

  # ───────────────────────────────────────────────────
  # Vue SPA (static, Nginx serve edir)
  # ───────────────────────────────────────────────────
  vue-app:
    image: mycompany/vue-app:2.1.0
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.vue.rule=Host(`app.example.com`)"
      - "traefik.http.routers.vue.entrypoints=websecure"
      - "traefik.http.routers.vue.tls.certresolver=letsencrypt"
      - "traefik.http.services.vue.loadbalancer.server.port=80"
    networks:
      - web

  # ───────────────────────────────────────────────────
  # Filament admin
  # ───────────────────────────────────────────────────
  admin:
    image: mycompany/laravel-admin:1.0.0
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.admin.rule=Host(`admin.example.com`)"
      - "traefik.http.routers.admin.entrypoints=websecure"
      - "traefik.http.routers.admin.tls.certresolver=letsencrypt"
      - "traefik.http.services.admin.loadbalancer.server.port=80"
      # Basic auth əlavə
      - "traefik.http.routers.admin.middlewares=admin-auth@docker"
      - "traefik.http.middlewares.admin-auth.basicauth.users=admin:$$apr1$$hashed"
    networks:
      - web
      - backend

  postgres:
    image: postgres:16
    # ... (no traefik labels — daxili servis)
    networks:
      - backend

  redis:
    image: redis:7
    networks:
      - backend

networks:
  web:           # Traefik bu network-dakı konteynerləri görür
    external: true
  backend:       # Daxili servislər — Traefik görmür
```

```bash
# External network yaradırıq (Traefik üçün ortaq)
docker network create web

# Qaldır
docker compose up -d

# Yoxla
curl https://api.example.com/health
```

**Nə baş verir?**

1. Client `https://api.example.com` istəyir
2. DNS → Traefik host-unun IP-si
3. Traefik 443 portunda qəbul edir, TLS-i açır
4. Docker label `Host(api.example.com)` olan konteyner-i tapır — `laravel`
5. Trafiki `laravel:80` portuna yönləndirir
6. Laravel cavab verir, Traefik TLS-lə client-ə ötürür

### 3. Traefik Middleware Nümunələri

```yaml
labels:
  # HTTPS-ə redirect
  - "traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https"
  
  # Rate limiting
  - "traefik.http.middlewares.ratelimit.ratelimit.average=50"
  - "traefik.http.middlewares.ratelimit.ratelimit.burst=100"
  - "traefik.http.middlewares.ratelimit.ratelimit.period=1m"
  
  # Security headers
  - "traefik.http.middlewares.secheaders.headers.framedeny=true"
  - "traefik.http.middlewares.secheaders.headers.stsseconds=31536000"
  - "traefik.http.middlewares.secheaders.headers.contenttypenosniff=true"
  
  # IP whitelist
  - "traefik.http.middlewares.internal-only.ipallowlist.sourcerange=10.0.0.0/8,192.168.0.0/16"
  
  # Strip prefix
  - "traefik.http.middlewares.strip-api.stripprefix.prefixes=/api"
  
  # Birləşdirmək
  - "traefik.http.routers.laravel.middlewares=ratelimit,secheaders,strip-api"
```

### 4. Nginx — Klassik, Güclü, Static Konfiqurasiya

Nginx reverse proxy qurmaq üçün config file yazmaq lazımdır. Dinamik discovery yoxdur (amma `nginx-proxy/jwilder` image ilə əlavə edilə bilər).

**Nə vaxt Nginx seç:**
- Complex rewrite rule-ları lazımdır
- FastCGI cache lazımdır (PHP üçün)
- gzip/brotli tuning-ində full control lazımdır
- Legacy setup-dan köçürürsən
- Team Nginx-də təcrübəlidir

### 5. Nginx docker-compose.yml

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - ./nginx/ssl:/etc/nginx/ssl:ro
      - ./nginx/html:/usr/share/nginx/html:ro
    networks:
      - web

  laravel:
    image: mycompany/laravel:1.0.0
    networks:
      - web
      - backend

  vue-app:
    image: mycompany/vue-app:2.1.0
    networks:
      - web

networks:
  web:
    external: true
  backend:
```

**nginx/conf.d/api.conf:**

```nginx
# Upstream tərifi — load balancing üçün
upstream laravel_backend {
    # Docker DNS-də laravel konteynerinə resolve olunur
    server laravel:80;
    # Birdən çox instance-da:
    # server laravel_1:80;
    # server laravel_2:80;
    keepalive 32;
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$host$request_uri;
}

# HTTPS
server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate     /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;

    # Proxy ayarları
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Timeouts
    proxy_connect_timeout 10s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;

    # Body size (file upload üçün)
    client_max_body_size 50M;

    location / {
        proxy_pass http://laravel_backend;
    }

    # WebSocket dəstəyi (Laravel Echo/Reverb üçün)
    location /app/ {
        proxy_pass http://laravel_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
}
```

**nginx/conf.d/app.conf (Vue SPA):**

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;

    ssl_certificate     /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;

    location / {
        proxy_pass http://vue-app;
    }
}
```

### 6. Nginx-proxy + acme-companion (Avtomatik SSL)

Static Nginx-də Let's Encrypt manual olur. Bunu həll edən kombinasiya:

```yaml
services:
  nginx-proxy:
    image: nginxproxy/nginx-proxy:latest
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/tmp/docker.sock:ro
      - certs:/etc/nginx/certs
      - html:/usr/share/nginx/html
      - vhostd:/etc/nginx/vhost.d
    networks:
      - web

  acme-companion:
    image: nginxproxy/acme-companion:latest
    volumes_from:
      - nginx-proxy
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - acme:/etc/acme.sh
    environment:
      DEFAULT_EMAIL: admin@example.com
    networks:
      - web

  laravel:
    image: mycompany/laravel:1.0.0
    environment:
      VIRTUAL_HOST: api.example.com
      LETSENCRYPT_HOST: api.example.com
      LETSENCRYPT_EMAIL: admin@example.com
    networks:
      - web
```

Bu setup label-lar əvəzinə `VIRTUAL_HOST` env var işlədir. Sadədir, amma Traefik qədər güclü deyil.

### 7. Caddy — Üçüncü Variant

Caddy Traefik kimi auto-HTTPS verir, amma config Nginx-ə bənzəyir (Caddyfile):

```
# Caddyfile
api.example.com {
    reverse_proxy laravel:80
}

app.example.com {
    reverse_proxy vue-app:80
}

admin.example.com {
    basicauth {
        admin $2a$14$hashedpassword
    }
    reverse_proxy admin:80
}
```

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
```

Sadədir, amma Docker ilə dinamik label-based discovery yoxdur (plugin ilə var).

### 8. Multi-App Pattern — Real Nümunə

```
                    ┌─────────────┐
                    │   Internet  │
                    └──────┬──────┘
                           │ :443
                    ┌──────▼──────────────────┐
                    │  Traefik (Reverse Proxy) │
                    │  - TLS termination       │
                    │  - Routing               │
                    │  - Rate limiting         │
                    └──┬────────┬────────┬─────┘
                       │        │        │
           ┌───────────┘   ┌────┘   └──────────┐
           │               │                   │
    ┌──────▼──────┐  ┌─────▼──────┐  ┌────────▼─────┐
    │ Laravel API │  │  Vue SPA   │  │  Filament    │
    │ api.xxx.com │  │ app.xxx.com│  │ admin.xxx.com│
    │  :80 (2x)   │  │   :80      │  │   :80        │
    └──────┬──────┘  └────────────┘  └──────┬───────┘
           │                                │
           └──────────────┬─────────────────┘
                          │
                ┌─────────▼──────────┐
                │ Postgres + Redis   │
                │ (internal network) │
                └────────────────────┘
```

Bir Traefik (və ya bir Nginx) hər üç app-ı 80/443 portundan serve edir.

### 9. Cloud Load Balancer Arxasında

**Sual:** AWS ALB artıq var, nə üçün Traefik lazımdır?

**Cavab:** Çox app per cluster olanda ALB rule-ları partlayır (sıx quota limitləri, hər rule üçün pul). Pattern:

```
Internet → AWS ALB (L7) → Traefik (cluster içində) → Backends
```

ALB yalnız TLS termination və "bütün trafik Traefik-ə" edir. Path-based və host-based routing-i Traefik həll edir. ALB qiyməti və complexity aşağı.

**Alternativ:** ALB + Ingress Controller (K8s-də bu eyni pattern-dir).

### 10. TLS Termination Harada?

```
Variant A: LB-də    Internet → [TLS]LB → HTTP → Traefik → HTTP → app
Variant B: Proxy-də Internet → [TLS]LB → [TLS]Traefik → HTTP → app
Variant C: App-da   Internet → LB → Traefik → [TLS]app (nadir)
```

Çox vaxt **Variant A** və ya **B**. Variant A daha sadə (bir yerdə cert), Variant B daha təhlükəsiz (zero-trust şəbəkə).

### 11. Sticky Sessions (Session Affinity)

Köhnə PHP session-based auth: client eyni backend-ə gələn istəkləri olmalıdır (session file local-dadır). Həll: **sticky sessions** (Traefik-də `loadbalancer.sticky`).

```yaml
labels:
  - "traefik.http.services.laravel.loadbalancer.sticky.cookie=true"
  - "traefik.http.services.laravel.loadbalancer.sticky.cookie.name=server"
```

**Daha yaxşı həll:** Session-ları Redis-də saxla (`SESSION_DRIVER=redis` Laravel-də). Sticky sessions-a ehtiyac qalmır, hər backend hər request-i idarə edə bilər.

### 12. Real Client IP — X-Forwarded-For

Proxy arxasında `$_SERVER['REMOTE_ADDR']` proxy-nin IP-sini verir, real client-inkini yox. Laravel-də `TrustProxies` middleware-ni konfiqurə et:

```php
// app/Http/Middleware/TrustProxies.php
class TrustProxies extends Middleware
{
    protected $proxies = '*';  // və ya konkret IP/CIDR-lər
    
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
```

Yoxsa log-larda hamı proxy IP-sindən gəlir, rate limiting səhv işləyir.

## Best Practices

1. **Docker socket read-only** (`:ro`) — Traefik socket-ə yazmamalıdır
2. **Dashboard-u prod-da secure** — basic auth və ya IP whitelist
3. **Auto-HTTPS işlət** (Traefik/Caddy) — manual cert renewal unutmaq asandır
4. **Rate limit qoy** — DDoS-dan qorunma
5. **Security headers** — HSTS, X-Frame-Options, CSP
6. **Log səviyyəsini prod-da ERROR** — DEBUG disk doldurur
7. **Health check endpoint** — Traefik də backend-i probe edə bilsin
8. **TrustProxies middleware** Laravel-də — real IP üçün
9. **Session-ları Redis-də** — sticky session-a ehtiyac qoyma
10. **Single entry point** — bütün trafik bir proxy-dən keçsin

## Tələlər (Gotchas)

### 1. Docker socket exposure
`/var/run/docker.sock`-u Traefik-ə verdiyin anda Traefik konteyner qırıldıqda bütün host-u təhlükədə qoyur. Həlllər:
- `:ro` (read-only) mütləq
- `docker-socket-proxy` image işlədə bilərsən (socket-i filter edir)

### 2. Traefik v1 vs v2/v3
v1 konfiqurasiyası v2+ ilə TAMAM fərqlidir. Köhnə tutorial tapdıqda versiyaya diqqət et. v3 ən son.

### 3. Let's Encrypt rate limit
ACME-nin haftalıq limit-i var (50 cert/domain/week). Test edərkən `caserver` staging endpoint işlət:
```
--certificatesresolvers.letsencrypt.acme.caserver=https://acme-staging-v02.api.letsencrypt.org/directory
```

### 4. Real client IP problemi
Birdən çox proxy arasında (Cloudflare → ALB → Traefik → Laravel) `X-Forwarded-For` uzun chain olur. Laravel-də `TrustProxies` düzgün konfiqurasiya olmalıdır.

### 5. WebSocket Nginx-də
Default Nginx config WebSocket-i çəkmir. `proxy_set_header Upgrade` və `Connection "upgrade"` lazımdır.

### 6. Nginx worker count
`worker_processes auto;` default-dir, amma Docker konteynerində "auto" host-un CPU sayını alır, konteynerin limit-ini yox. `worker_processes 2;` manual qoy.

### 7. `client_max_body_size` default kiçikdir
Nginx default 1MB. File upload varsa `client_max_body_size 50M;` qoy.

### 8. Traefik dashboard yanlış açıq qalır
`--api.insecure=true` yalnız local-da. Prod-da dashboard-u authenticated router-in arxasına qoy.

### 9. Port 80/443 başqa servis tutub
Apache/Nginx host-da işləyirsə, Docker proxy qalxmır. `systemctl stop apache2` və ya port dəyişdir.

### 10. HTTP/2 və grpc config-i ayrıdır
gRPC backend-sə Traefik-də `scheme=h2c` lazımdır. Default HTTP/1.1 işləmir.

## Müsahibə Sualları

### S1: Reverse proxy nə üçündür və forward proxy-dən fərqi nədir?
**C:** Reverse proxy server tərəfində dayanır — client-in hansı backend-ə getdiyini bilmir, proxy qərar verir. TLS termination, routing, load balancing, caching verir. Forward proxy client tərəfindədir (VPN, corporate proxy) — client outbound trafiki üçün. Docker setup-da həmişə reverse proxy söhbəti olur.

### S2: Traefik və Nginx arasında fərq nədir, hansı hansında daha yaxşıdır?
**C:** **Traefik** dinamikdir — Docker label-lar oxuyur, yeni konteyner qalxanda avtomatik route əlavə edir. Auto-HTTPS built-in. Docker/K8s üçün native. **Nginx** statikdir — config file yazmaq lazımdır, amma daha güclü (FastCGI cache, complex rewrite, uzun tarix). Docker-native dinamik setup üçün Traefik, klassik və ya fine-grained control üçün Nginx.

### S3: Docker socket-i Traefik-ə niyə read-only verirsən?
**C:** Read-write socket Traefik-ə konteyner yaratmaq/silmək/exec etmək imkanı verər — yəni host-u tam control. Traefik yalnız konteynerləri siyahılamaq üçün socket-ə baxır, `:ro` kifayətdir. Daha təhlükəsiz: `docker-socket-proxy` image istifadə et — yalnız müəyyən endpoint-ləri icazə verir.

### S4: TLS termination-u harada etməlisən — LB-də, proxy-də, yoxsa app-da?
**C:** Çox halda cloud LB-də (ALB/GCP LB) və ya cluster-içi reverse proxy-də. App-da etmək nadir — hər app-ın cert-i idarə etməsi və CPU-nu TLS-ə xərcləməsi pis scaling-dir. LB-də etmək sadə (bir cert, bir yer), amma LB-backend şəbəkəsi HTTP olur. Zero-trust tələbi varsa, proxy-də də TLS (mTLS).

### S5: Real client IP-ni proxy arxasında necə alırsan?
**C:** Proxy `X-Forwarded-For` header qoyur — real client IP-si orada. Laravel-də `TrustProxies` middleware-ni konfiqurə et: `$proxies = '*'` (və ya CIDR list), və `$headers` bit mask. Yoxsa `$request->ip()` proxy-nin IP-sini qaytarır. Rate limiting, audit log, geoblocking üçün kritikdir.

### S6: Sticky session nədir və nə vaxt lazımdır?
**C:** Load balancer client-i həmişə eyni backend-ə göndərir (cookie ilə). Köhnə PHP session-based auth-da lazım idi — session fayl backend-də local idi. Modern: session-ları Redis/DB-də saxla, sticky session-a ehtiyac yoxdur. WebSocket-də hələ də faydalıdır (connection state).

### S7: Cloud LB varsa, nə üçün Traefik də?
**C:** Cluster-də 20-50 app varsa, ALB rule-ları partlayır (quota limiti, qiymət). ALB-ni "TLS termination + Traefik-ə yönləndir" kimi işlət, path/host-based routing-i Traefik-ə ver. Bir ALB rule, amma Traefik-də istənilən sayda route. Qiymət və complexity aşağı.

### S8: Traefik middleware nədir və hansıları lazımdır?
**C:** Middleware — request/response-u dəyişən əlavə layer-dir. Chain-də tətbiq olunur. Yaygın: rate limiting, auth (basic/forward), header manipulation (HSTS, X-Frame-Options), IP whitelist, strip prefix, compression, retry. Label-da `traefik.http.middlewares.X.Y.Z` kimi konfiqurasiya olunur, sonra router-ə bağlanır.
