# Docker Optimallaşdırması

## Nədir? (What is it?)

Docker optimization — image ölçüsünü kiçiltmək, build vaxtını azaltmaq və runtime performansını artırmaq üçün istifadə olunan texnikalar toplusudur. Produksiyada hər saniyə və hər meqabayt önəmlidir — kiçik image-lər daha sürətli deploy olur, daha az disk və bandwidth istifadə edir.

Docker build prosesi layer-lər üzərində qurulub. Hər Dockerfile instruksiyası yeni bir layer yaradır. Bu layer-lərin necə idarə olunması birbaşa performansa təsir edir.

## Əsas Konseptlər

### 1. Docker Layer Caching

Docker hər layer-i cache-ləyir. Əgər bir layer dəyişməyibsə, Docker onu yenidən build etmir, cache-dən istifadə edir. Layer sırası çox vacibdir.

```dockerfile
# ❌ PİS — hər kod dəyişikliyində composer install yenidən işləyir
FROM php:8.3-fpm
COPY . /var/www/html
RUN composer install

# ✅ YAXŞI — yalnız composer faylları dəyişdikdə install yenidən işləyir
FROM php:8.3-fpm
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --no-scripts --no-autoloader
COPY . /var/www/html
RUN composer dump-autoload --optimize
```

**Layer cache qaydaları:**
- Layer dəyişdikdə ondan sonrakı BÜTÜN layer-lər yenidən build olur
- `COPY` və `ADD` faylların checksumunu yoxlayır
- `RUN` əmrləri string olaraq müqayisə olunur (fayllar dəyişsə belə)

### 2. .dockerignore

`.dockerignore` faylı build context-dən lazımsız faylları çıxarır. Bu həm build vaxtını, həm də image ölçüsünü azaldır.

```dockerignore
# .dockerignore - Laravel proyekti üçün
.git
.gitignore
.env
.env.*
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
tests
phpunit.xml
docker-compose*.yml
Dockerfile*
README.md
.editorconfig
.styleci.yml
.php-cs-fixer.cache
```

**Build context ölçüsünü yoxlama:**

```bash
# Build context ölçüsünü görmək
docker build . 2>&1 | head -1
# Sending build context to Docker daemon  2.048kB

# .dockerignore olmadan
# Sending build context to Docker daemon  500MB  ← node_modules!
```

### 3. Alpine vs Slim vs Full Images

```
┌──────────────────┬───────────┬─────────────────────────────┐
│ Image            │ Ölçü      │ İstifadə halı               │
├──────────────────┼───────────┼─────────────────────────────┤
│ php:8.3          │ ~480MB    │ Development, tam alətlər     │
│ php:8.3-slim     │ ~130MB    │ Produksiya (əksər hallarda)  │
│ php:8.3-alpine   │ ~50MB     │ Minimal, kiçik image lazımsa │
│ php:8.3-fpm      │ ~490MB    │ FPM ilə development          │
│ php:8.3-fpm-alpine│ ~55MB   │ FPM ilə minimal produksiya   │
└──────────────────┴───────────┴─────────────────────────────┘
```

**Alpine xüsusiyyətləri:**
- musl libc istifadə edir (glibc əvəzinə) — bəzi PHP extension-ları ilə uyğunsuzluq ola bilər
- `apk` paket meneceri (`apt` əvəzinə)
- `ash` shell (`bash` əvəzinə)
- Bəzi PHP extension-larını compile etmək daha çətin ola bilər

```dockerfile
# Alpine ilə PHP extension quraşdırma
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        zip \
        intl \
        opcache \
    && apk del --no-cache freetype-dev libjpeg-turbo-dev libpng-dev
```

**Slim xüsusiyyətləri:**
- Debian-based, glibc istifadə edir — daha uyğun
- Lazımsız paketlər silinib (man pages, docs, dev alətlər)
- Əksər produksiya halları üçün ən yaxşı seçimdir

### 4. BuildKit

BuildKit Docker-un yeni build engine-dir. Paralel build, daha yaxşı caching və secret mount kimi xüsusiyyətlər təqdim edir.

```bash
# BuildKit-i aktivləşdirmək
export DOCKER_BUILDKIT=1
docker build .

# və ya docker buildx istifadə edin
docker buildx build .
```

**BuildKit üstünlükləri:**
- Paralel layer build (asılı olmayan layer-lər eyni vaxtda build olur)
- Cache mount (paket menecer cache-inin saxlanması)
- Secret mount (build zamanı secret-lərin təhlükəsiz istifadəsi)
- SSH mount (SSH key-lərin build zamanı istifadəsi)
- Daha yaxşı çıxış formatı

### 5. Cache Mounts

```dockerfile
# syntax=docker/dockerfile:1.4

FROM php:8.3-fpm-alpine

# Composer cache mount — cache layer-ə yazılmır, ayrı saxlanır
RUN --mount=type=cache,target=/root/.composer/cache \
    --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    composer install --no-dev --no-scripts --no-autoloader

# APK cache mount
RUN --mount=type=cache,target=/var/cache/apk \
    apk add --no-cache libzip-dev \
    && docker-php-ext-install zip

# NPM cache mount
RUN --mount=type=cache,target=/root/.npm \
    npm ci --production
```

### 6. Secret Mounts

```dockerfile
# syntax=docker/dockerfile:1.4

# Build zamanı secret istifadə (image-ə yazılmır)
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json \
    composer install --no-dev

# Build əmri
# docker build --secret id=composer_auth,src=auth.json .
```

## Praktiki Nümunələr

### Tam Optimallaşdırılmış Laravel Dockerfile

```dockerfile
# syntax=docker/dockerfile:1.4

# ============ Stage 1: Composer dependencies ============
FROM composer:2.7 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# ============ Stage 2: Frontend assets ============
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci

COPY resources/ resources/
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# ============ Stage 3: Final image ============
FROM php:8.3-fpm-alpine AS production

# Runtime dependencies only
RUN apk add --no-cache \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        icu-libs \
        linux-headers

# PHP extensions — build deps ayrıca quraşdır və sil
RUN apk add --no-cache --virtual .build-deps \
        libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql zip gd intl opcache bcmath \
    && apk del .build-deps

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Composer dependencies (from stage 1)
COPY --from=composer-deps /app-laravel/vendor vendor/

# Application kodu
COPY . .

# Frontend assets (from stage 2)
COPY --from=frontend /app-laravel/public/build public/build/

# Autoloader optimize
RUN composer dump-autoload --optimize --classmap-authoritative

# Laravel optimizations
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
```

### Image Ölçüsünü Müqayisə

```bash
# Image ölçülərini yoxlamaq
docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}"

# Hər layer-in ölçüsünü görmək
docker history myapp:latest

# dive aləti ilə ətraflı analiz
# https://github.com/wagoodman/dive
dive myapp:latest
```

### RUN İnstruksiyalarını Birləşdirmək

```dockerfile
# ❌ PİS — hər RUN yeni layer yaradır
RUN apt-get update
RUN apt-get install -y curl
RUN apt-get install -y git
RUN apt-get clean

# ✅ YAXŞI — bir RUN-da birləşdir
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
```

### Lazımsız Faylları Silmək

```dockerfile
# Test, doc, cache fayllarını sil
RUN composer install --no-dev --optimize-autoloader \
    && rm -rf \
        /var/www/html/tests \
        /var/www/html/.git \
        /var/www/html/node_modules \
        /var/www/html/storage/logs/*.log \
        /root/.composer/cache
```

## PHP/Laravel ilə İstifadə

### OPcache Konfiqurasiyası

```ini
; docker/php/opcache.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
opcache.jit_buffer_size=100M
opcache.jit=1255
```

### Composer Autoloader Optimization

```dockerfile
# Classmap authoritative — composer.json-dakı autoload qeydləri əsasında
RUN composer dump-autoload --optimize --classmap-authoritative

# Əgər realpath_cache istifadə edirsinizsə
# php.ini-də:
# realpath_cache_size = 4096k
# realpath_cache_ttl = 600
```

### Laravel Artisan Caching

```dockerfile
# Config, route, view cache — produksiyada mütləq
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache
```

### PHP-FPM Tuning

```ini
; docker/php-fpm/www.conf
[www]
pm = static
pm.max_children = 50
pm.max_requests = 1000

; Proses sayını RAM-a görə hesablayın:
; max_children = Mövcud RAM / Hər prosesin ortalama RAM istifadəsi
; Məs: 2GB / 40MB = 50
```

## İntervyu Sualları

### S1: Docker layer caching necə işləyir?
**C:** Docker hər Dockerfile instruksiyasını layer olaraq cache-ləyir. Build zamanı Docker hər layer üçün cache-i yoxlayır. Əgər instruksiya və onun input-ları (COPY üçün fayl checksumları, RUN üçün əmr stringi) dəyişməyibsə, cached layer istifadə olunur. Bir layer dəyişdikdə, ondan sonrakı BÜTÜN layer-lər yenidən build olur. Buna görə az dəyişən instruksiyalar yuxarıda, çox dəyişənlər aşağıda olmalıdır.

### S2: Alpine image-lərin üstünlükləri və çatışmazlıqları nədir?
**C:** Üstünlüklər: çox kiçik ölçü (~5MB base), kiçik attack surface, sürətli pull/push. Çatışmazlıqlar: musl libc istifadə edir (glibc əvəzinə) — bəzi binary-lər və PHP extension-ları düzgün işləməyə bilər, debug alətləri azdır, compile vaxtı daha uzun ola bilər. Əksər Laravel produksiya halları üçün `slim` daha uyğun seçimdir.

### S3: .dockerignore niyə vacibdir?
**C:** Build context-i kiçildir (build vaxtını azaldır), image-ə lazımsız faylların düşməsinin qarşısını alır (`.git`, `node_modules`, `.env`), təhlükəsizliyi artırır (secret fayllar image-ə düşmür). `.dockerignore` olmadan böyük fayllar build context-ə daxil olur və hər build-də Docker daemon-a göndərilir.

### S4: BuildKit nə üstünlük verir?
**C:** Paralel build (asılı olmayan stage-lər eyni anda build olur), cache mount (composer/npm cache-inin build-lər arasında saxlanması), secret mount (build zamanı secret-lərin təhlükəsiz istifadəsi), SSH forwarding, daha yaxşı output formatı. `DOCKER_BUILDKIT=1` ilə aktivləşdirilir.

### S5: PHP/Laravel Docker image-ini necə optimallaşdırarsınız?
**C:** Multi-stage build (composer deps ayrı stage-də), alpine/slim base image, layer ordering (composer.json əvvəl COPY olunur), .dockerignore, OPcache aktivləşdirmə, `composer dump-autoload --optimize`, Laravel config/route/view caching, lazımsız faylları silmə, PHP-FPM tuning (pm.max_children).

### S6: `--no-install-recommends` nə edir?
**C:** apt-get-ə yalnız birbaşa tələb olunan paketləri quraşdırmağı, tövsiyə olunan əlavə paketləri quraşdırmamağı söyləyir. Bu, image ölçüsünü əhəmiyyətli dərəcədə kiçildir.

### S7: Cache mount ilə adi layer caching arasında fərq nədir?
**C:** Layer caching-də hər RUN nəticəsi image layer-ə yazılır. Cache mount-da isə ayrıca cache saxlanılır və layer-ə yazılmır. Bu o deməkdir ki, composer/npm cache build-lər arasında saxlanır amma final image-ə əlavə ölçü vermir. `RUN --mount=type=cache,target=/path` sintaksisi ilə istifadə olunur.

## Best Practices

1. **Layer sırasına diqqət edin** — az dəyişən instruksiyalar yuxarıda, çox dəyişənlər aşağıda
2. **Multi-stage build istifadə edin** — build alətləri final image-ə düşməsin
3. **RUN əmrlərini birləşdirin** — `&&` ilə bir RUN-da, lazımsız layer yaratmayın
4. **.dockerignore mütləq olsun** — `.git`, `node_modules`, `.env`, `tests`
5. **Slim/Alpine seçin** — produksiyada full image lazım deyil
6. **BuildKit istifadə edin** — cache mount, paralel build
7. **`--no-install-recommends` istifadə edin** — apt-get ilə
8. **Cache-i təmizləyin** — `rm -rf /var/lib/apt/lists/*`, `apk del .build-deps`
9. **OPcache aktiv edin** — produksiyada `validate_timestamps=0`
10. **`dive` aləti ilə image-i analiz edin** — hər layer-in nə qədər yer tutduğunu görün
