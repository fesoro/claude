# Production-Ready Laravel Dockerfile

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

Production üçün Laravel Dockerfile sadəcə `php:8.3-fpm` çəkib `COPY . /var/www` etməkdən çox daha çox şeydir. Düzgün qurulmuş production image-də:

- **Multi-stage build** — vendor və asset-lər ayrı stage-də qurulur, final image kiçik olur
- **OpCache + JIT** — production performansı üçün aktiv və tune edilmiş
- **Non-root user** — təhlükəsizlik üçün `www-data` altında işləyir
- **Tini və ya dumb-init** — PID 1 problemini həll edir (SIGTERM düzgün ötürülür)
- **Sabit digest** — `FROM php:8.3-fpm-alpine@sha256:...` reproducible build
- **Gərəksiz layerlərsiz** — hər RUN əmri cache təmizləyir

Bu fayl həmin bütün hissələri bir yerə yığaraq **kopyala-istifadə et** şəklində Dockerfile verir.

## Minimum Production Dockerfile (Nginx + PHP-FPM ayrı konteyner)

Bu pattern-də PHP-FPM öz konteynerində, Nginx isə başqa konteynerdə işləyir (12-factor, daha ölçüləndiriləbilən).

```dockerfile
# ============================================================
# Stage 1: Composer dependencies (production only)
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app

# Əvvəlcə yalnız composer.json və lock kopyalayırıq — cache üçün
COPY composer.json composer.lock ./

# Production vendor qur — dev paketlərsiz, scriptsiz, autoloader optimize
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress \
    --ignore-platform-reqs

# ============================================================
# Stage 2: Frontend assets (Vite / Mix)
# ============================================================
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund

# Mənbə kodu kopyala (resources/, public/)
COPY resources/ resources/
COPY public/ public/

# Production build (public/build/ qovluğu yaranacaq)
RUN npm run build

# ============================================================
# Stage 3: Final PHP-FPM image
# ============================================================
FROM php:8.3-fpm-alpine AS production

# Sistem paketləri + PHP extension-lar
RUN apk add --no-cache \
        tini \
        icu-dev \
        libzip-dev \
        libpng-dev \
        oniguruma-dev \
        postgresql-dev \
        curl \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        gcc \
        g++ \
        make \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# OpCache + JIT konfiqurasiyası (36-cı faylda detallı)
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Laravel tətbiqinin mənbəyini kopyalayırıq
COPY --chown=www-data:www-data . .

# Build-artifact-ları əvvəlki stage-lərdən çəkirik
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Storage və cache yollarını yaz-oxu izni ilə hazırla
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Laravel-in production optimization-ları
# DİQQƏT: config:cache yalnız environment build-time-da stabil olanda edilməlidir
# Əks halda entrypoint-də etmək daha yaxşıdır (43-cü fayl)
RUN php artisan package:discover --ansi \
    && php artisan view:cache \
    && php artisan route:cache \
    && php artisan event:cache

# Entrypoint — migration, config cache runtime-da
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

# Tini PID 1 kimi — SIGTERM düzgün ötürülür
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# Health check — PHP-FPM ping status
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD php-fpm-healthcheck || exit 1
```

### `.dockerignore` Vacib!

Əgər `.dockerignore` yoxdursa, build vaxtı `node_modules/`, `.git/`, `vendor/` kopyalanacaq — build həm yavaş, həm də image böyük olacaq.

```gitignore
# .dockerignore
.git
.gitignore
.env
.env.*
!.env.example

node_modules
vendor
public/build
public/hot
public/storage

storage/*.key
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
!storage/framework/cache/.gitignore
!storage/framework/sessions/.gitignore
!storage/framework/views/.gitignore

docker-compose*.yml
Dockerfile*
.dockerignore
*.md
.github
tests
phpunit.xml
.phpunit.cache
.phpunit.result.cache
```

## Tək Konteynerli Variant (Nginx + PHP-FPM bir yerdə)

Kiçik layihələr və ya VPS-lər üçün tək konteynerdə `supervisord` ilə hər ikisini saxlamaq olar.

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor tini \
    && mkdir -p /run/nginx

# ... (əvvəlki kimi PHP extension-lar) ...

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

EXPOSE 80

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
```

`supervisord.conf` nümunəsi:

```ini
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm -F
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

**Nə vaxt bu pattern?**
- Kiçik layihələr, VPS deploy (Hetzner, DigitalOcean)
- Konteyner orkestrasiyası (K8s) yoxdur
- CI/CD sadədir

**Nə vaxt ayrı konteynerlər?**
- Kubernetes, ECS, production-grade
- Nginx horizontal scale etməlidir (static asset, CDN)
- PHP-FPM öz ölçüləndirmə sürətinə malik olmalıdır

## Image Ölçüsü — Hədəf

| Base | Ölçü (təqribən) |
|------|-----------------|
| `php:8.3-fpm` (Debian) | ~450 MB |
| `php:8.3-fpm-alpine` | ~110 MB |
| Multi-stage + Alpine | **~90-130 MB** (Laravel ilə) |
| Distroless (22-ci fayl) | ~60-90 MB |

## Tipik Səhvlər (Gotchas)

### 1. `composer install` hər dəyişiklikdə yenidən işləyir

**Səbəb:** `COPY . .` əvvəl olunur, sonra `composer install`. Mənbədə hər hansı dəyişiklik bütün layer-i invalidate edir.

**Həll:** Əvvəlcə yalnız `composer.json` və `composer.lock` kopyalayın (yuxarıdakı Stage 1-də göstərildiyi kimi).

### 2. `.env` image-ə düşür

**Səbəb:** `.dockerignore`-da `.env` yoxdur.

**Həll:** `.env` heç vaxt image-də olmamalıdır. Runtime-da `env_file:` və ya K8s Secret ilə mount olunur.

### 3. `php artisan config:cache` build-time-da

**Problem:** Build zamanı `.env` olmur, `config:cache` boş DB credential-ları cache edir.

**Həll:** `config:cache` entrypoint-də runtime-da icra olunmalıdır (bax: 38-ci fayl).

### 4. Root kimi işləmək

**Problem:** `USER www-data` yoxdur, PHP-FPM root-da işləyir.

**Həll:** Yuxarıda göstərildiyi kimi `USER www-data`. FPM pool-da da `user = www-data` olmalıdır.

### 5. Storage və bootstrap/cache permission error

**Problem:** Konteyner başlayır amma `Permission denied: storage/logs/laravel.log`.

**Həll:** Build-time-da `chown -R www-data:www-data storage bootstrap/cache` və volume mount-da UID match (42-ci fayl).

### 6. `apt-get install` cache təmizləməmək

**Problem:** Image 200 MB + olur, lazımsız paket siyahıları qalır.

**Həll:** Alpine-də `apk del .build-deps && rm -rf /var/cache/apk/*`. Debian-da `apt-get clean && rm -rf /var/lib/apt/lists/*`.

## Interview sualları

- **Q:** Production Laravel image-i necə qurursunuz?
  - Multi-stage: composer (vendor) → node (frontend) → php-fpm (final). `--no-dev`, `--optimize-autoloader`. Nginx ayrı konteynerdə. Non-root. Tini PID 1.

- **Q:** Niyə Alpine Debian-dan yaxşıdır?
  - Ölçü: 5 MB base vs 60 MB. Amma Alpine `musl libc` istifadə edir (glibc yox), bəzi extension-lar uyğun olmaya bilər. Debian `bookworm-slim` də alternativdir (~80 MB).

- **Q:** Build cache-i necə optimize edirsiz?
  - `composer.json/lock`-u əvvəl kopyala, sonra `composer install`. Asset build ayrı stage-də. BuildKit cache mount (23-cü fayl). Registry cache (`--cache-from`).

- **Q:** `config:cache` build-time-da problemlidirmi?
  - Bəli — `.env` olmadan cache boş credential-larla yaranır. Entrypoint-də runtime-da edin, və ya runtime env variables-ı mount edin.


## Əlaqəli Mövzular

- [multi-stage-builds.md](04-multi-stage-builds.md) — Multi-stage build pattern
- [php-fpm-tuning-docker.md](37-php-fpm-tuning-docker.md) — PHP-FPM tuning
- [nginx-php-fpm-container-setup.md](38-nginx-php-fpm-container-setup.md) — Nginx + FPM setup
- [docker-entrypoint-scripts-laravel.md](40-docker-entrypoint-scripts-laravel.md) — Entrypoint skriptlər
