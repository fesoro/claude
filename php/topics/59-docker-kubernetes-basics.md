# Docker və Kubernetes Əsasları — Senior PHP Developer

---

## 1. Docker Əsasları: Image-lər, Container-lər, Layer-lər, PHP üçün Dockerfile Best Practice-lər

### Əsas Anlayışlar

**Image** — container-in salt-oxunan şablonudur. Hər image bir sıra layer-lərdən ibarətdir.

**Container** — image-in çalışan nüsxəsidir. Image üzərinə yazıla bilən nazik bir layer əlavə edilir.

**Layer** — Dockerfile-dakı hər `RUN`, `COPY`, `ADD` əmri yeni layer yaradır. Layer-lər keşlənir; dəyişməyən layer-lər yenidən build edilmir.

### Layer Keşləmə Qaydası

Docker Dockerfile-ı yuxarıdan aşağıya oxuyur. Bir layer dəyişərsə, ondan sonrakı bütün layer-lər invalidasiya olunur. Bu səbəbdən:

- Tez-tez dəyişən faylları (məsələn, `composer.json`) gec-dəyişənlərdən sonra kopyalayın.
- `COPY . .` əmrini mümkün qədər sona qoyun.

### PHP üçün Dockerfile Best Practice-lər

*PHP üçün Dockerfile Best Practice-lər üçün kod nümunəsi:*
```dockerfile
# Konkret versiya istifadə edin — "latest" istifadə etməyin
FROM php:8.3-fpm-alpine

# Sistem paketlərini bir RUN əmrində quraşdırın (layer sayını azaldır)
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        opcache \
    && rm -rf /var/cache/apk/*

# Composer-i rəsmi image-dən götürün
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Ayrı bir istifadəçi ilə işləyin (root kimi işləməyin)
RUN addgroup -g 1000 appgroup && adduser -u 1000 -G appgroup -s /bin/sh -D appuser

WORKDIR /var/www/html

# Əvvəlcə yalnız dependency fayllarını kopyalayın (keş üçün)
COPY --chown=appuser:appgroup composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Sonra qalan kodu kopyalayın
COPY --chown=appuser:appgroup . .

# Post-install skriptlərini icra edin
RUN composer run-script post-install-cmd --no-interaction || true

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
```

### Əsas Qaydalar

- `alpine`-based image istifadə edin — daha kiçik, daha az attack surface.
- `--no-dev` ilə production dependency-lərini quraşdırın.
- Həssas məlumatları (API açarları) `ENV` ilə image-ə yerləşdirməyin.
- `.dockerignore` faylı yaradın:

```
.git
.env
vendor/
node_modules/
*.log
tests/
```

---

## 2. PHP üçün Multi-Stage Build-lər — Image Ölçüsünü Optimallaşdırmaq

Multi-stage build-lər build vaxtı lazım olan alətləri (Composer, Node.js) final image-ə daxil etmədən build etməyə imkan verir.

*Multi-stage build-lər build vaxtı lazım olan alətləri (Composer, Node üçün kod nümunəsi:*
```dockerfile
# ---- Stage 1: Composer dependency-ləri ----
FROM composer:2.7 AS composer_stage

WORKDIR /app

COPY composer.json composer.lock ./

# Production dependency-ləri quraşdır
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

# ---- Stage 2: Frontend asset-lər (lazım olarsa) ----
FROM node:20-alpine AS node_stage

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --production

COPY resources/ ./resources/
COPY vite.config.js ./

RUN npm run build

# ---- Stage 3: Final production image ----
FROM php:8.3-fpm-alpine AS production

RUN apk add --no-cache \
    libpng-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        opcache \
        pcntl \
        bcmath \
    && rm -rf /var/cache/apk/*

# OPcache konfiqurasiyası
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN addgroup -g 1000 appgroup \
    && adduser -u 1000 -G appgroup -s /bin/sh -D appuser

WORKDIR /var/www/html

# Yalnız lazımi faylları əvvəlki stage-lərdən kopyalayın
COPY --from=composer_stage --chown=appuser:appgroup /app/vendor ./vendor
COPY --from=node_stage --chown=appuser:appgroup /app/public/build ./public/build
COPY --chown=appuser:appgroup . .

USER appuser

EXPOSE 9000
CMD ["php-fpm"]
```

### OPcache Konfiqurasiyası (`docker/php/opcache.ini`)

*OPcache Konfiqurasiyası (`docker/php/opcache.ini`) üçün kod nümunəsi:*
```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
; Production-da fayl dəyişikliyini yoxlamayın
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### Ölçü Müqayisəsi

| Yanaşma | Təxmini Ölçü |
|---|---|
| php:8.3-fpm (Debian) + Composer | ~600MB |
| php:8.3-fpm-alpine + Composer | ~120MB |
| Multi-stage (alpine, yalnız vendor) | ~80MB |

---

## 3. Docker-da PHP-FPM + Nginx — Tam Konfiqurasiya

PHP-FPM və Nginx ayrı container-lərdə işləməlidir. Nginx statik faylları özü serv edir, dinamik sorğuları PHP-FPM-ə yönləndirir.

### Nginx Konfiqurasiyası (`docker/nginx/default.conf`)

*Nginx Konfiqurasiyası (`docker/nginx/default.conf`) üçün kod nümunəsi:*
```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    # Statik fayllar üçün keş başlıqları
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # PHP-FPM container-inin adı ilə bağlanır
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Timeout-lar
        fastcgi_read_timeout 300;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;

        # Buffer-lər
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    location ~ /\.ht {
        deny all;
    }

    # Sağlamlıq yoxlaması üçün endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
```

### PHP-FPM Konfiqurasiyası (`docker/php/www.conf`)

*PHP-FPM Konfiqurasiyası (`docker/php/www.conf`) üçün kod nümunəsi:*
```ini
[www]
user = appuser
group = appgroup

; Unix socket Nginx ilə eyni container-də olduqda daha sürətlidir
; Lakin ayrı container-lərdə TCP istifadə edin
listen = 0.0.0.0:9000

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; Yavaş sorğuları logla
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s

; Status endpoint — health check üçün
pm.status_path = /fpm-status
ping.path = /fpm-ping
ping.response = pong
```

### Nginx Dockerfile (`docker/nginx/Dockerfile`)

*Nginx Dockerfile (`docker/nginx/Dockerfile`) üçün kod nümunəsi:*
```dockerfile
FROM nginx:1.25-alpine

# Statik fayllar həm Nginx, həm PHP-FPM-ə lazımdır
# Bu səbəbdən ya shared volume, ya da Nginx image-ə kopyalama
COPY --from=php_build /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
```

---

## 4. Local Development üçün Docker Compose

*4. Local Development üçün Docker Compose üçün kod nümunəsi:*
```yaml
# docker-compose.yml
version: '3.9'

services:
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      php:
        condition: service_healthy
    networks:
      - app_network

  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: development  # Multi-stage-dən dev stage
    volumes:
      - .:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini
      - ./docker/php/www.conf:/usr/local/etc/php-fpm.d/www.conf
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: ${DB_DATABASE:-app}
      DB_USERNAME: ${DB_USERNAME:-app}
      DB_PASSWORD: ${DB_PASSWORD:-secret}
      REDIS_HOST: redis
    healthcheck:
      test: ["CMD", "php-fpm", "-t"]
      interval: 10s
      timeout: 5s
      retries: 3
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app_network

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root_secret
      MYSQL_DATABASE: ${DB_DATABASE:-app}
      MYSQL_USER: ${DB_USERNAME:-app}
      MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "3306:3306"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p$$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app_network

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 3
    networks:
      - app_network

  queue_worker:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: development
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    volumes:
      - .:/var/www/html
    environment:
      APP_ENV: local
      DB_HOST: mysql
      REDIS_HOST: redis
      QUEUE_CONNECTION: redis
    depends_on:
      - php
      - redis
    restart: unless-stopped
    networks:
      - app_network

  mailhog:
    image: mailhog/mailhog:latest
    ports:
      - "1025:1025"   # SMTP
      - "8025:8025"   # Web UI
    networks:
      - app_network

volumes:
  mysql_data:
  redis_data:

networks:
  app_network:
    driver: bridge
```

### Development PHP Dockerfile (`docker/php/Dockerfile`)

*Development PHP Dockerfile (`docker/php/Dockerfile`) üçün kod nümunəsi:*
```dockerfile
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache git curl libpng-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo pdo_mysql opcache pcntl bcmath gd

# ---- Development stage ----
FROM base AS development

# Xdebug yalnız development-da
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
CMD ["php-fpm"]

# ---- Production stage ----
FROM base AS production

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN addgroup -g 1000 appgroup && adduser -u 1000 -G appgroup -s /bin/sh -D appuser

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
COPY --chown=appuser:appgroup . .

USER appuser
CMD ["php-fpm"]
```

---

## 5. Kubernetes Əsas Konseptləri

### Pod

Kubernetes-in ən kiçik deploy edilə bilən vahididir. Bir və ya bir neçə container-i əhatə edir. Eyni Pod-dakı container-lər eyni şəbəkə namespace-ini paylaşır.

*Kubernetes-in ən kiçik deploy edilə bilən vahididir. Bir və ya bir neç üçün kod nümunəsi:*
```yaml
apiVersion: v1
kind: Pod
metadata:
  name: php-app
  labels:
    app: php-app
spec:
  containers:
    - name: php-fpm
      image: myapp/php:1.0.0
      ports:
        - containerPort: 9000
```

### Deployment

Pod-ların istənilən sayını idarə edir, rolling update və rollback təmin edir.

*Pod-ların istənilən sayını idarə edir, rolling update və rollback təmi üçün kod nümunəsi:*
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: php-app
  template:
    metadata:
      labels:
        app: php-app
    spec:
      containers:
        - name: php-fpm
          image: myapp/php:1.0.0
```

### Service

Pod-lara sabit şəbəkə ünvanı verir. Pod-lar yenidən yarandıqda IP dəyişir, Service isə dəyişməz qalır.

*Pod-lara sabit şəbəkə ünvanı verir. Pod-lar yenidən yarandıqda IP dəyi üçün kod nümunəsi:*
```yaml
apiVersion: v1
kind: Service
metadata:
  name: php-app-service
spec:
  selector:
    app: php-app
  ports:
    - protocol: TCP
      port: 9000
      targetPort: 9000
  type: ClusterIP  # Yalnız cluster daxilində əlçatan
```

### Ingress

HTTP/HTTPS trafikini xarici dünyadan cluster-dəki Service-lərə yönləndirir.

*HTTP/HTTPS trafikini xarici dünyadan cluster-dəki Service-lərə yönlənd üçün kod nümunəsi:*
```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: php-app-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
    cert-manager.io/cluster-issuer: letsencrypt-prod
spec:
  tls:
    - hosts:
        - myapp.example.com
      secretName: myapp-tls
  rules:
    - host: myapp.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: nginx-service
                port:
                  number: 80
```

### ConfigMap

Həssas olmayan konfiqurasiya məlumatlarını saxlayır.

*Həssas olmayan konfiqurasiya məlumatlarını saxlayır üçün kod nümunəsi:*
```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: php-app-config
data:
  APP_ENV: production
  APP_DEBUG: "false"
  CACHE_DRIVER: redis
  SESSION_DRIVER: redis
  QUEUE_CONNECTION: redis
  php.ini: |
    memory_limit = 256M
    max_execution_time = 60
    upload_max_filesize = 50M
```

### Secret

Şifrələnmiş (base64) həssas məlumatları saxlayır.

*Şifrələnmiş (base64) həssas məlumatları saxlayır üçün kod nümunəsi:*
```yaml
apiVersion: v1
kind: Secret
metadata:
  name: php-app-secrets
type: Opaque
data:
  # echo -n "value" | base64
  DB_PASSWORD: c2VjcmV0cGFzc3dvcmQ=
  APP_KEY: YmFzZTY0OmtleQ==
  REDIS_PASSWORD: cmVkaXNwYXNz
```

> **Qeyd:** Production-da Kubernetes Secrets kifayət qədər təhlükəsiz deyil. HashiCorp Vault və ya AWS Secrets Manager istifadə edin.

---

## 6. PHP Tətbiqləri üçün Kubernetes: Çoxlu Replica, Rolling Deployment

### Tam Deployment Manifesti

*Tam Deployment Manifesti üçün kod nümunəsi:*
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app
  namespace: production
  labels:
    app: php-app
    version: "1.0.0"
spec:
  replicas: 3
  selector:
    matchLabels:
      app: php-app
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1         # Eyni anda +1 yeni Pod yarada bilər
      maxUnavailable: 0   # Heç bir Pod unavailable olmamalıdır
  template:
    metadata:
      labels:
        app: php-app
        version: "1.0.0"
    spec:
      # Podları fərqli node-lara yay
      affinity:
        podAntiAffinity:
          preferredDuringSchedulingIgnoredDuringExecution:
            - weight: 100
              podAffinityTerm:
                labelSelector:
                  matchExpressions:
                    - key: app
                      operator: In
                      values:
                        - php-app
                topologyKey: kubernetes.io/hostname
      containers:
        - name: php-fpm
          image: myregistry/php-app:1.0.0
          ports:
            - containerPort: 9000
          envFrom:
            - configMapRef:
                name: php-app-config
            - secretRef:
                name: php-app-secrets
          resources:
            requests:
              memory: "128Mi"
              cpu: "100m"
            limits:
              memory: "512Mi"
              cpu: "500m"
          livenessProbe:
            exec:
              command: ["php-fpm-healthcheck"]
            initialDelaySeconds: 10
            periodSeconds: 10
            failureThreshold: 3
          readinessProbe:
            exec:
              command: ["php-fpm-healthcheck", "--accepted-conn=1"]
            initialDelaySeconds: 5
            periodSeconds: 5
            failureThreshold: 3
          lifecycle:
            preStop:
              exec:
                # Graceful shutdown — mövcud sorğuları tamamla
                command: ["/bin/sh", "-c", "sleep 5"]
        
        - name: nginx
          image: myregistry/nginx-app:1.0.0
          ports:
            - containerPort: 80
          resources:
            requests:
              memory: "32Mi"
              cpu: "50m"
            limits:
              memory: "128Mi"
              cpu: "200m"
      
      terminationGracePeriodSeconds: 30
```

### Nginx Service

*Nginx Service üçün kod nümunəsi:*
```yaml
apiVersion: v1
kind: Service
metadata:
  name: php-app-nginx-service
  namespace: production
spec:
  selector:
    app: php-app
  ports:
    - name: http
      protocol: TCP
      port: 80
      targetPort: 80
  type: ClusterIP
```

---

## 7. Health Check-lər: Liveness Probe, Readiness Probe — PHP üçün Niyə Kritikdir

### Liveness Probe

Container-in "diri" olub olmadığını yoxlayır. Uğursuz olarsa, container yenidən başladılır.

**PHP üçün niyə vacibdir:**
- PHP-FPM prosesi donub qalsa (deadlock, memory leak)
- PHP worker-lər cavab verməsə
- Uzun müddətli sorğular worker pool-u tükətsə

### Readiness Probe

Container-in trafik qəbul etməyə hazır olub olmadığını yoxlayır. Uğursuz olarsa, Pod Service-dən çıxarılır (trafik göndərilmir).

**PHP üçün niyə vacibdir:**
- Laravel `php artisan config:cache` hələ tamamlanmayıbsa
- Verilənlər bazası əlaqəsi hazır deyilsə
- Cache warming aparılırsa

### PHP-FPM Health Check Skripti

`php-fpm-healthcheck` (ayrıca quraşdırılmalıdır):

*`php-fpm-healthcheck` (ayrıca quraşdırılmalıdır) üçün kod nümunəsi:*
```dockerfile
RUN wget -O /usr/local/bin/php-fpm-healthcheck \
    https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck
```

### Alternativ — HTTP Health Check Endpoint

*Alternativ — HTTP Health Check Endpoint üçün kod nümunəsi:*
```php
// public/health.php
<?php

$checks = [];

// Verilənlər bazası yoxlaması
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_DATABASE')),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 2]
    );
    $pdo->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Exception $e) {
    $checks['database'] = 'fail';
}

// Redis yoxlaması
try {
    $redis = new Redis();
    $redis->connect(getenv('REDIS_HOST'), 6379, 2);
    $redis->ping();
    $checks['redis'] = 'ok';
} catch (Exception $e) {
    $checks['redis'] = 'fail';
}

$healthy = !in_array('fail', $checks);

http_response_code($healthy ? 200 : 503);
header('Content-Type: application/json');
echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'checks' => $checks,
    'timestamp' => time(),
]);
```

*'timestamp' => time(), üçün kod nümunəsi:*
```yaml
livenessProbe:
  httpGet:
    path: /health.php
    port: 80
  initialDelaySeconds: 30
  periodSeconds: 15
  timeoutSeconds: 5
  failureThreshold: 3

readinessProbe:
  httpGet:
    path: /health.php
    port: 80
  initialDelaySeconds: 10
  periodSeconds: 5
  timeoutSeconds: 3
  failureThreshold: 3
  successThreshold: 1
```

---

## 8. Resource Limitləri (CPU/Yaddaş) — Bulkhead Pattern ilə Əlaqə

### Resource Requests vs Limits

| Parametr | Məna |
|---|---|
| `requests.cpu` | Kubernetes-in scheduling üçün rezerv etdiyi CPU |
| `requests.memory` | Kubernetes-in scheduling üçün rezerv etdiyi yaddaş |
| `limits.cpu` | Container-in istifadə edə biləcəyi maksimum CPU (throttling baş verir) |
| `limits.memory` | Container-in istifadə edə biləcəyi maksimum yaddaş (aşılarsa OOMKill) |

*həll yanaşmasını üçün kod nümunəsi:*
```yaml
resources:
  requests:
    memory: "128Mi"
    cpu: "100m"    # 0.1 CPU core
  limits:
    memory: "512Mi"
    cpu: "500m"    # 0.5 CPU core
```

### Bulkhead Pattern ilə Əlaqə

Bulkhead pattern — bir komponentin uğursuzluğunun digərini etkiləməməsini təmin edir (gəmidəki su keçirməz bölmələr kimi).

Kubernetes resource limitləri bu patterni həyata keçirir:

*Kubernetes resource limitləri bu patterni həyata keçirir üçün kod nümunəsi:*
```yaml
# API service — yüksək prioritet
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api-service
spec:
  replicas: 3
  template:
    spec:
      containers:
        - name: php-fpm
          resources:
            requests:
              memory: "256Mi"
              cpu: "200m"
            limits:
              memory: "512Mi"
              cpu: "1000m"

# Queue worker — aşağı prioritet, API-ya təsir etməməlidir
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: queue-worker
spec:
  replicas: 2
  template:
    spec:
      containers:
        - name: worker
          resources:
            requests:
              memory: "128Mi"
              cpu: "100m"
            limits:
              memory: "256Mi"
              cpu: "500m"
```

### PHP üçün Yaddaş Hesablaması

```
Yaddaş limiti = PHP memory_limit × pm.max_children + overhead
Məsələn: 128MB × 10 workers + 50MB = ~1.3GB
```

### LimitRange — Namespace Səviyyəsində Default-lar

*LimitRange — Namespace Səviyyəsində Default-lar üçün kod nümunəsi:*
```yaml
apiVersion: v1
kind: LimitRange
metadata:
  name: php-app-limits
  namespace: production
spec:
  limits:
    - type: Container
      default:
        cpu: "500m"
        memory: "256Mi"
      defaultRequest:
        cpu: "100m"
        memory: "128Mi"
      max:
        cpu: "2"
        memory: "2Gi"
      min:
        cpu: "50m"
        memory: "64Mi"
```

---

## 9. Horizontal Pod Autoscaling

HPA — trafik yüküne görə Pod sayını avtomatik artırır/azaldır.

### CPU-əsaslı HPA

*CPU-əsaslı HPA üçün kod nümunəsi:*
```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: php-app-hpa
  namespace: production
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: php-app
  minReplicas: 2
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70   # CPU 70%-ə çatanda scale et
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 60   # 1 dəqiqə stabilləşdirmə
      policies:
        - type: Pods
          value: 2
          periodSeconds: 60            # Hər 60 saniyədə max 2 Pod əlavə et
    scaleDown:
      stabilizationWindowSeconds: 300  # 5 dəqiqə stabilləşdirmə (tez scale-down etmə)
      policies:
        - type: Pods
          value: 1
          periodSeconds: 120           # Hər 2 dəqiqədə max 1 Pod azalt
```

### Custom Metrics ilə HPA (Queue uzunluğuna görə)

*Custom Metrics ilə HPA (Queue uzunluğuna görə) üçün kod nümunəsi:*
```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: queue-worker-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: queue-worker
  minReplicas: 1
  maxReplicas: 10
  metrics:
    - type: External
      external:
        metric:
          name: redis_queue_length
          selector:
            matchLabels:
              queue: default
        target:
          type: AverageValue
          averageValue: "30"   # Hər worker üçün max 30 iş
```

### Metrics Server Quraşdırılması

HPA işləməsi üçün metrics-server tələb olunur:

*HPA işləməsi üçün metrics-server tələb olunur üçün kod nümunəsi:*
```bash
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
```

---

## 10. Zero-Downtime Deployment-lər: Rolling Update Strategiyası

### Rolling Update Necə İşləyir

1. Yeni Pod-lar yaradılır (`maxSurge` daxilindəki sayda).
2. Yeni Pod-lar `Ready` vəziyyətinə keçdikdən sonra köhnə Pod-lar silinir.
3. `maxUnavailable: 0` ilə heç bir Pod trafik qəbul etməyi dayandırmır.

### Tam Zero-Downtime Konfiqurasiyası

*Tam Zero-Downtime Konfiqurasiyası üçün kod nümunəsi:*
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  template:
    spec:
      containers:
        - name: php-fpm
          image: myregistry/php-app:2.0.0
          readinessProbe:
            httpGet:
              path: /health.php
              port: 80
            initialDelaySeconds: 10
            periodSeconds: 5
            successThreshold: 2    # 2 ardıcıl uğurlu cavabdan sonra ready
          lifecycle:
            preStop:
              exec:
                # Kubernetes trafiki dayandırmazdan əvvəl gözlə
                command: ["/bin/sh", "-c", "sleep 10 && kill -QUIT 1"]
      terminationGracePeriodSeconds: 60
```

### Database Migration Strategiyası

Zero-downtime deployment zamanı database migration ciddi problemdir. Tövsiyə olunan yanaşma:

*Zero-downtime deployment zamanı database migration ciddi problemdir. T üçün kod nümunəsi:*
```yaml
# initContainer ilə migration — deploy əvvəli işlər
spec:
  initContainers:
    - name: migration
      image: myregistry/php-app:2.0.0
      command: ["php", "artisan", "migrate", "--force"]
      envFrom:
        - secretRef:
            name: php-app-secrets
```

**Qızıl qayda:** Migration-lar həmişə backward-compatible olmalıdır:
- Köhnə kod yeni sütunla işləyə bilməlidir (nullable sütun əlavə edin).
- Sütunu silməzdən əvvəl kodu deploy edin.
- Rename əvəzinə yeni sütun əlavə edin, köhnəni sonra silin.

### Deployment-i İzləmək

*Deployment-i İzləmək üçün kod nümunəsi:*
```bash
# Rollout statusunu izlə
kubectl rollout status deployment/php-app -n production

# Rollout tarixçəsi
kubectl rollout history deployment/php-app -n production

# Sürətli rollback
kubectl rollout undo deployment/php-app -n production

# Müəyyən versiyaya rollback
kubectl rollout undo deployment/php-app --to-revision=2 -n production
```

---

## 11. Kubernetes-də PHP Session İdarəetməsi (Stateless Tələbi)

### Problem

HTTP request-lər müxtəlif Pod-lara düşür. Əgər session fayl sistemində saxlanılırsa, istifadəçi hər dəfə fərqli Pod-a düşəndə session itirilir.

### Həll: Mərkəzləşdirilmiş Session Storage

**Redis ilə:**

```php
// config/session.php (Laravel)
return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'connection' => 'session',
    'lifetime' => env('SESSION_LIFETIME', 120),
    'encrypt' => true,
];

// config/database.php
'redis' => [
    'session' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '1'),
        'persistent' => true,
    ],
],
```

*'persistent' => true, üçün kod nümunəsi:*
```ini
; php.ini
session.save_handler = redis
session.save_path = "tcp://redis-service:6379?auth=password&database=1"
```

### Kubernetes Redis Deployment

*Kubernetes Redis Deployment üçün kod nümunəsi:*
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  namespace: production
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
        - name: redis
          image: redis:7-alpine
          command:
            - redis-server
            - --requirepass
            - $(REDIS_PASSWORD)
            - --maxmemory
            - 512mb
            - --maxmemory-policy
            - allkeys-lru
          env:
            - name: REDIS_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: php-app-secrets
                  key: REDIS_PASSWORD
          resources:
            requests:
              memory: "256Mi"
              cpu: "100m"
            limits:
              memory: "512Mi"
              cpu: "500m"
          ports:
            - containerPort: 6379
---
apiVersion: v1
kind: Service
metadata:
  name: redis-service
  namespace: production
spec:
  selector:
    app: redis
  ports:
    - port: 6379
      targetPort: 6379
  type: ClusterIP
```

### Sticky Sessions (Tövsiyə Edilmir)

Nginx Ingress ilə sticky session mümkündür, lakin bu scaling-i çətinləşdirir:

*Nginx Ingress ilə sticky session mümkündür, lakin bu scaling-i çətinlə üçün kod nümunəsi:*
```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  annotations:
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "SERVERID"
    nginx.ingress.kubernetes.io/session-cookie-expires: "172800"
```

**Bu yanaşmanın mənfi cəhəti:** Bir Pod düşərsə, həmin Pod-a bağlı bütün session-lar itirilir.

---

## 12. Kubernetes-də Queue Worker-lər — Worker-ləri Scale Etmək

### Laravel Queue Worker Deployment

*Laravel Queue Worker Deployment üçün kod nümunəsi:*
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: queue-worker
  namespace: production
  labels:
    app: queue-worker
spec:
  replicas: 2
  selector:
    matchLabels:
      app: queue-worker
  template:
    metadata:
      labels:
        app: queue-worker
    spec:
      containers:
        - name: worker
          image: myregistry/php-app:1.0.0
          command:
            - php
            - artisan
            - queue:work
            - redis
            - --sleep=3
            - --tries=3
            - --max-time=3600   # 1 saatdan sonra worker-i yenidən başlat
            - --memory=256      # 256MB-dan çox istifadə etsə restart
          envFrom:
            - configMapRef:
                name: php-app-config
            - secretRef:
                name: php-app-secrets
          resources:
            requests:
              memory: "128Mi"
              cpu: "100m"
            limits:
              memory: "256Mi"
              cpu: "500m"
          livenessProbe:
            exec:
              command:
                - php
                - artisan
                - queue:monitor
                - --max=1000
            initialDelaySeconds: 30
            periodSeconds: 60
            failureThreshold: 3
      # Worker üçün graceful shutdown
      terminationGracePeriodSeconds: 90
```

### Fərqli Növ Queue-lar üçün Ayrı Deployment-lər

*Fərqli Növ Queue-lar üçün Ayrı Deployment-lər üçün kod nümunəsi:*
```yaml
# Yüksək prioritetli queue
apiVersion: apps/v1
kind: Deployment
metadata:
  name: queue-worker-high
spec:
  replicas: 3
  template:
    spec:
      containers:
        - name: worker
          command: ["php", "artisan", "queue:work", "redis", "--queue=high,default", "--tries=3"]
          resources:
            limits:
              memory: "512Mi"
              cpu: "1000m"
---
# Aşağı prioritetli, uzun müddətli işlər
apiVersion: apps/v1
kind: Deployment
metadata:
  name: queue-worker-low
spec:
  replicas: 1
  template:
    spec:
      containers:
        - name: worker
          command: ["php", "artisan", "queue:work", "redis", "--queue=low", "--sleep=10"]
          resources:
            limits:
              memory: "256Mi"
              cpu: "500m"
```

### Queue Worker-ləri Scale Etmək (KEDA ilə)

KEDA (Kubernetes Event-driven Autoscaling) queue uzunluğuna görə worker-ləri scale edir:

*KEDA (Kubernetes Event-driven Autoscaling) queue uzunluğuna görə worke üçün kod nümunəsi:*
```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledObject
metadata:
  name: queue-worker-scaler
  namespace: production
spec:
  scaleTargetRef:
    name: queue-worker
  minReplicaCount: 0   # Boş olduqda 0-a enə bilər
  maxReplicaCount: 20
  triggers:
    - type: redis
      metadata:
        address: redis-service.production.svc.cluster.local:6379
        listName: queues:default
        listLength: "10"   # 10 iş varsa 1 worker başlat
      authenticationRef:
        name: redis-auth
```

### Cron Job-lar (Laravel Scheduler)

*Cron Job-lar (Laravel Scheduler) üçün kod nümunəsi:*
```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-scheduler
  namespace: production
spec:
  schedule: "* * * * *"   # Hər dəqiqə
  concurrencyPolicy: Forbid   # Paralel icranın qarşısını al
  successfulJobsHistoryLimit: 3
  failedJobsHistoryLimit: 3
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: scheduler
              image: myregistry/php-app:1.0.0
              command: ["php", "artisan", "schedule:run"]
              envFrom:
                - configMapRef:
                    name: php-app-config
                - secretRef:
                    name: php-app-secrets
              resources:
                limits:
                  memory: "256Mi"
                  cpu: "500m"
```

---

## 13. Ümumi Pitfall-lar: Container-lərdə OPcache, Fayl İcazələri

### OPcache Pitfall-ları

**Problem 1: OPcache köhnə kodu keşləyir**

Container-lər immutable olmalıdır — kod dəyişdikdə yeni image build edilir. Lakin development-da volume mount istifadə edirsinizsə:

*Container-lər immutable olmalıdır — kod dəyişdikdə yeni image build ed üçün kod nümunəsi:*
```ini
; Development üçün OPcache-i deaktiv edin
opcache.enable=0

; Və ya revalidasiya tezliyini artırın
opcache.revalidate_freq=0
opcache.validate_timestamps=1
```

**Problem 2: Container restart zamanı OPcache sıfırlanmır**

PHP-FPM yenidən başlayana qədər köhnə keş qala bilər. Rolling update zamanı yeni Pod-lar fresh keşlə gəlir — bu doğru davranışdır.

**Problem 3: OPcache memory_consumption çox azdır**

```ini
; Böyük proyektlər üçün
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
; opcache_get_status() ilə yoxlayın
```

**Yanlış Konfiqurasiya:**
```ini
opcache.validate_timestamps=1  ; Production-da FALSE olmalıdır
opcache.revalidate_freq=60     ; Production-da 0 olmalıdır
```

### Fayl İcazəsi Pitfall-ları

**Problem 1: Container root kimi işləyir**

```dockerfile
# YANLIŞ — root kimi işlətmə
FROM php:8.3-fpm
# ...

# DOĞRU
RUN useradd -u 1000 -ms /bin/bash appuser
USER appuser
```

**Problem 2: Volume mount-da icazə problemləri**

Host UID/GID container UID/GID ilə uyğun gəlməyəndə problem yaranır:

*Host UID/GID container UID/GID ilə uyğun gəlməyəndə problem yaranır üçün kod nümunəsi:*
```yaml
# docker-compose.yml
services:
  php:
    user: "1000:1000"  # Host istifadəçisinin UID:GID-i
```

**Problem 3: Storage/cache qovluqları yazıla bilmir**

```dockerfile
# Laravel storage qovluqlarına icazə ver
RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R appuser:appgroup storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
```

**Problem 4: Kubernetes-də ReadOnlyRootFilesystem**

```yaml
spec:
  containers:
    - name: php-fpm
      securityContext:
        readOnlyRootFilesystem: true   # Təhlükəsizlik üçün yaxşıdır
      volumeMounts:
        # Yazıla bilən qovluqları ayrıca mount edin
        - name: storage
          mountPath: /var/www/html/storage
        - name: bootstrap-cache
          mountPath: /var/www/html/bootstrap/cache
        - name: tmp
          mountPath: /tmp
  volumes:
    - name: storage
      emptyDir: {}
    - name: bootstrap-cache
      emptyDir: {}
    - name: tmp
      emptyDir: {}
```

### Digər Ümumi Pitfall-lar

**PHP-FPM Worker Sayı Hesablanması**

```ini
; Yanlış: çox worker — OOM (Out of Memory) Kill
pm.max_children = 100   ; 100 × 128MB = 12.8GB

; Doğru hesablama:
; pm.max_children = container_memory_limit / php_memory_per_worker
; = 512MB / 64MB = ~8 worker
pm.max_children = 8
```

**ENV Dəyişənləri image-ə yazılır**

```dockerfile
# YANLIŞ — sirrli məlumatları image-ə yazma
ENV DB_PASSWORD=mysecretpassword

# DOĞRU — runtime-da Kubernetes Secret-dən al
# (Deployment manifestindəki secretRef)
```

**Log-lar fayl sisteminə yazılır**

Kubernetes-də log-lar stdout/stderr-ə yazılmalıdır:

*Kubernetes-də log-lar stdout/stderr-ə yazılmalıdır üçün kod nümunəsi:*
```php
// config/logging.php (Laravel)
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['stderr'],  // fayl yox, stderr
    ],
    'stderr' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'formatter' => JsonFormatter::class,
        'with' => [
            'stream' => 'php://stderr',
        ],
    ],
],
```

---

## 14. İntervyu Sualları

### S1: Docker image ilə container arasındakı fərq nədir?

**C:** Image — salt-oxunan şablondur, layer-lərdən ibarətdir. Container — image-in çalışan nüsxəsidir, üzərinə nazik yazıla bilən layer əlavə edilir. Eyni image-dən çoxlu container yaratmaq mümkündür. Image silinməz, container isə dayandırılıb silinə bilər.

---

### S2: Multi-stage build nə üçün lazımdır? PHP proyektinə nümunə göstərin.

**C:** Multi-stage build-lər final image-in ölçüsünü azaldır — build alətlərini (Composer, Node.js, gcc) production image-ə daxil etmir. PHP üçün: birinci mərhələdə Composer image istifadə edib `vendor/` qurulur, ikinci mərhələdə isə yalnız `vendor/` kopyalanır. Nəticədə Composer binary-si final image-ə daxil olmur.

---

### S3: Kubernetes-də Liveness və Readiness Probe arasındakı fərq nədir? PHP-FPM üçün ikisini necə konfiqurasiya edərdiniz?

**C:** Liveness — container-in "diri" olub olmadığını yoxlayır; uğursuz olarsa container restart edilir. Readiness — trafik qəbul etməyə hazır olub olmadığını yoxlayır; uğursuz olarsa Pod Service-dən çıxarılır. PHP-FPM üçün: Liveness üçün `php-fpm-healthcheck` əmrini, Readiness üçün isə həm FPM-i, həm də DB/Redis əlaqəsini yoxlayan HTTP endpoint (`/health.php`) istifadə edərdim. `initialDelaySeconds` PHP-FPM-in tam başlaması üçün kifayət qədər böyük olmalıdır.

---

### S4: Zero-downtime deployment üçün hansı strategiya istifadə edərdiniz və database migration-larla necə işləyərdiniz?

**C:** Rolling Update strategiyası ilə `maxUnavailable: 0` — yeni Pod-lar ready olana qədər köhnələr silinmir. Database migration-lar üçün: `initContainer` ilə deployment əvvəlindən migration-ı icra edərdim, lakin migration-ların backward-compatible olmasını təmin edərdim. Yəni: yeni sütun əlavə edəndə nullable edərdim, köhnə kodu dəstəklərdi; sütun silmə əməliyyatını isə yeni kodun deploy edilməsindən sonrakı ayrı deployment-ə saxlardım.

---

### S5: PHP tətbiqi Kubernetes-də session-ları necə idarə etməlidir?

**C:** Fayl sistemi session-ları Kubernetes-də işləmir, çünki sorğular müxtəlif Pod-lara düşür. Redis (mərkəzləşdirilmiş storage) istifadə etmək lazımdır. Laravel-də `SESSION_DRIVER=redis` konfiqurasiyası ilə. Redis-ə əlaqə Kubernetes Service üzərindən olmalıdır. Sticky session texniki cəhətdən mümkündür, lakin tövsiyə edilmir — Pod çöküşündə bütün session-lar itirilir.

---

### S6: HPA (Horizontal Pod Autoscaler) necə işləyir? Queue worker üçün necə konfiqurasiya edərdiniz?

**C:** HPA metrics-server-dən (CPU, yaddaş) və ya custom metrics-dən oxuyur; threshold aşılanda replica sayını artırır. `scaleDown.stabilizationWindowSeconds` ani azalmalardan qoruyur. Queue worker üçün KEDA istifadə edərdim — queue uzunluğuna görə scale edir. Məsələn, `listLength: 10` — hər 10 iş üçün 1 worker. Boş queue-da `minReplicaCount: 0` ilə worker-i tamamilə söndürə bilərəm.

---

### S7: Container-də OPcache konfiqurasiyasının ən kritik parametrləri hansılardır? Nə üçün?

**C:** `validate_timestamps=0` — production-da fayl dəyişikliyini yoxlamasın (performans). `revalidate_freq=0` — 0 ilə timestamp yoxlanılır, amma validate_timestamps=0 olarsa heç yoxlanılmaz. `memory_consumption` — kifayət qədər böyük olmalıdır, əks halda keş dolur, yeni fayllar keşlənmir. `max_accelerated_files` — proyektdəki PHP fayl sayından çox olmalıdır. Container-lərdə kod immutable olduğundan validate_timestamps=0 tamamilə təhlükəsizdir.

---

### S8: Kubernetes-də PHP queue worker-lərini idarə edərkən hansı problemlərlə qarşılaşdınız?

**C:** Əsas problemlər: (1) Worker-lər uzun müddətli işlər görəndə graceful shutdown gecikir — `terminationGracePeriodSeconds`-u artırmaq lazımdır; (2) Memory leak — `--max-time` və `--memory` limitləri ilə worker-i mütəmadi restart etmək; (3) Failed job-ların idarəsi — retry məntiqini düzgün qurmaq, dead letter queue; (4) Scaling — standart HPA CPU-a görə scale edir, amma queue üçün queue uzunluğu daha uyğundur — KEDA istifadə etmək.

---

### S9: Kubernetes-də PHP tətbiqi üçün resource limitlərini necə hesablayarsınız?

**C:** PHP-FPM üçün: `pm.max_children × ortalama_sorğu_yaddaşı + overhead`. Məsələn, hər PHP sorğusu 64MB tutsa, 8 worker = 512MB, overhead 50MB = 560MB limit. CPU üçün isə: yük testindən alınan ortalama CPU istifadəsi + 20-30% ehtiyat. `requests` həmişə `limits`-dən az olmalıdır ki, Kubernetes düzgün schedule edə bilsin. İlk deploymentda az başlayıb, monitoring məlumatlarına əsasən tənzimləmək daha etibarlıdır.

---

### S10: Bulkhead pattern-ni Kubernetes context-ində izah edin.

**C:** Bulkhead — bir komponentin uğursuzluğunun digərini etkiləməməsini təmin edir. Kubernetes-də: ayrı Deployment-lər (API, worker, cron ayrı-ayrı), ayrı resource limiti, namespace isolation. Məsələn, worker Deployment-i çox yaddaş tutsa, API Deployment-i təsirlənmir. LimitRange ilə namespace səviyyəsində default limitlər qoyulur. Ayrıca, PodDisruptionBudget ilə minimum hazır Pod sayını təmin etmək olar.

---

### S13: Docker layer keşlənməsi necə işləyir, PHP proyektlərini necə optimallaşdırır?

**C:** Docker hər instruction-ı ayrı layer-da keşləyir. Bir layer dəyişsə, ondan sonrakılar keş invalidasiya olunur. PHP layihəsindəki ən kritik optimallaşdırma: `COPY composer.json composer.lock ./` + `RUN composer install` — əvvəlcə, sonra `COPY . .`. Bu sayədə yalnız `composer.json` dəyişdikdə `composer install` yenidən çalışır. Kod faylları dəyişdikdə isə `vendor/` layer-i keşdən gəlir, build sürətlənir.

---

### S14: `docker exec` ilə running container-ə daxil olmaq lazım olanda nə etmək lazımdır?

**C:** `docker exec -it <container_id> sh` (alpine) ya da `bash` (debian). PHP container-da debug üçün: `docker exec -it php-fpm php artisan tinker`. Kubernetes-də: `kubectl exec -it <pod-name> -n <namespace> -- sh`. Production-da bu nadir hallar üçün olmalıdır — debug üçün `kubectl port-forward` ya da ephemeral debug container (`kubectl debug`) istifadə edilə bilər.

---

### S11: Docker-da `CMD` vs `ENTRYPOINT` fərqi nədir? PHP container üçün hansını istifadə edərdiniz?

**C:** `ENTRYPOINT` — həmişə icra edilir, üzərinə yazmaq çətindir (yalnız `--entrypoint` ilə). `CMD` — default arqumentlər verir, asanlıqla üzərinə yazılır. PHP üçün adətən kombinasiya: `ENTRYPOINT ["docker-entrypoint.sh"]` (başlanğıc skriptlər, konfiqurasiya), `CMD ["php-fpm"]`. Bu sayədə `docker run myimage php artisan migrate` kimi əmrləri asanlıqla icra etmək mümkündür.

---

### S12: Kubernetes-də konfidensial məlumatları (DB şifrəsi, API açarları) necə idarə edərdiniz?

**C:** Kubernetes Secret-lər base64-dür, şifrəli deyil — kifayət deyil. Production üçün: (1) HashiCorp Vault + Vault Agent Injector — secret-ləri sidecar container kimi Pod-a inject edir; (2) AWS Secrets Manager / GCP Secret Manager — external-secrets-operator ilə; (3) Sealed Secrets — git-də şifrəli secret saxlamağa imkan verir. Heç bir zaman secret-ləri Dockerfile-a, image-ə və ya git-ə yazmamalıdır. RBAC ilə Secret-lərə giriş minimuma endirilməlidir.

---

> **Əlavə Resurslar:**
> - [Kubernetes Rəsmi Sənədləri](https://kubernetes.io/docs/)
> - [PHP-FPM Konfiqurasiya Bələdçisi](https://www.php.net/manual/en/install.fpm.configuration.php)
> - [KEDA — Kubernetes Event-driven Autoscaling](https://keda.sh/)
> - [Docker Best Practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)

---

## Anti-patternlər

**1. Secret-ləri Dockerfile-a ya da image-ə yazmaq**
`ENV DB_PASSWORD=secret` ya da `COPY .env /app` — image layer-larında şifrə görünür, Docker Hub-a push olarsa hər kəs görə bilər. Secret-ləri mütləq image xaricindən inject edin: K8s Secrets, HashiCorp Vault, ya da environment variable ilə runtime-da verin.

**2. Container-i root user ilə işlətmək**
Dockerfile-da `USER` direktivi olmadan PHP-FPM çalışdırmaq — container exploit olarsa host-da root giriş riski yaranır. `RUN adduser --disabled-password appuser` ilə ayrı user yaradın, `USER appuser` ilə container-i non-root kimi işlədin.

**3. Kubernetes-də resource limit qoymamaq**
Pod-a CPU/memory limit verilmədən deploy etmək — bir pod həddən artıq resurs istehlak edir, digər pod-lar evict edilir, node-lar çöküş yaşayır. Hər pod üçün `resources.requests` və `resources.limits` müəyyən edin; limits olmadan HPA da düzgün işləmir.

**4. Liveness probe-da DB bağlantısını yoxlamaq**
`/healthz` endpoint-i DB sorğusu edir, DB geçici yavaşlayır, liveness fail olur, pod restart edilir — DB yavaşladıqca bütün pod-lar restart loopuna girə bilər. Liveness-də yalnız prosesin özünü yoxlayın (PHP-FPM cavab verir?); DB yoxlamasını readiness probe-a verin.

**5. Single Dockerfile ilə dev və production eyni image**
Dev dependency-lər (xdebug, test paketlər) production image-ə daxildir — image böyük, attack surface geniş, debug tool-lar production-da aktiv. Multi-stage build istifadə edin: `builder` stage-də dev tool-lar, `production` stage-də yalnız çalışmaq üçün lazım olanlar.

**6. PersistentVolume olmadan stateful data saxlamaq**
DB datası container-in özündə saxlanılır — pod restart olunca bütün data itirilir. Stateful data üçün mütləq PersistentVolumeClaim istifadə edin; production DB-lərini K8s-dən kənarda (managed service: RDS, Cloud SQL) idarə edin.
