# Multi-Stage Builds

## Nədir? (What is it?)

Multi-stage build — bir Dockerfile-da birdən çox `FROM` instruksiyası istifadə edərək, build prosesini mərhələlərə ayırmaq texnikasıdır. Bu, son image-dən build alətlərini və aralıq faylları xaric edərək image ölçüsünü əhəmiyyətli dərəcədə azaldır.

Əsas ideya: build mərhələsində lazım olan alətlər (compiler, dev dependency-lər, test framework-lar) production image-ə lazım deyil.

```
Build Stage (böyük)              Final Stage (kiçik)
┌─────────────────────┐         ┌─────────────────────┐
│ OS + Build Tools    │         │ OS (minimal)        │
│ Compiler/SDK        │  COPY   │ Runtime only        │
│ Dev Dependencies    │ ──────> │ App Binary/Code     │
│ Source Code         │ artifacts│ Prod Dependencies  │
│ Test Files          │         │                     │
│ Compiled Artifacts  │         │                     │
└─────────────────────┘         └─────────────────────┘
   ~500MB+                         ~50-100MB
```

## Əsas Konseptlər

### Niyə Multi-Stage? (Why Multi-Stage?)

**Multi-stage olmadan:**
```dockerfile
# Tək stage — hər şey bir image-dədir
FROM php:8.3-fpm

# Build alətləri qalır
RUN apt-get update && apt-get install -y \
    git curl unzip nodejs npm

# Composer quraşdırılır amma production-da lazım deyil
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Dev dependency-lər də quraşdırılır
RUN composer install
RUN npm install && npm run build

# Son image-də git, curl, npm, node_modules, dev dependency-lər qalır
# Ölçü: ~800MB+
```

**Multi-stage ilə:**

```dockerfile
# Stage 1: Composer dependency-lər
FROM composer:2 AS composer-stage
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

# Stage 2: Frontend asset-lər
FROM node:20-alpine AS frontend-stage
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# Stage 3: Final production image
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html
COPY --from=composer-stage /app-laravel/vendor ./vendor
COPY --from=frontend-stage /app-laravel/public/build ./public/build
COPY . .
# Ölçü: ~150MB
```

### Builder Pattern

Multi-stage builds-dən əvvəl "builder pattern" istifadə olunurdu — iki ayrı Dockerfile və shell script:

```bash
# Köhnə üsul (builder pattern)
docker build -t myapp-builder -f Dockerfile.build .
docker create --name builder myapp-builder
docker cp builder:/app/dist ./dist
docker rm builder
docker build -t myapp -f Dockerfile.prod .
```

Multi-stage builds bunu bir Dockerfile-da həll edir.

### Stage-lər arasında Artifact Kopyalama

```dockerfile
# Stage-lərə ad vermək (AS)
FROM golang:1.21 AS builder
WORKDIR /app
COPY . .
RUN go build -o myapp

# Başqa stage-dən kopyalamaq
FROM alpine:3.18
COPY --from=builder /app-laravel/myapp /usr/local/bin/myapp

# Xarici image-dən kopyalamaq
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=nginx:alpine /etc/nginx/nginx.conf /etc/nginx/nginx.conf

# Stage nömrəsi ilə (0-dan başlayır)
COPY --from=0 /app-laravel/output /app/
```

### Müəyyən Stage-i Build Etmək

```bash
# Yalnız müəyyən stage-ə qədər build etmək
docker build --target builder -t myapp-builder .
docker build --target test -t myapp-test .

# CI/CD-də test stage-ini build etmək
docker build --target test -t myapp-test .
docker run myapp-test php artisan test
```

## Praktiki Nümunələr

### Go Tətbiqi (Ən klassik nümunə)

```dockerfile
# Build stage
FROM golang:1.21-alpine AS builder
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o server .

# Final stage
FROM scratch
COPY --from=builder /app-laravel/server /server
EXPOSE 8080
ENTRYPOINT ["/server"]
# Final ölçü: ~10-15MB (Go binary-dən əvvəl ~1GB+)
```

### Node.js Tətbiqi

```dockerfile
# Dependencies stage
FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --only=production

# Build stage
FROM node:20-alpine AS builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Final stage
FROM node:20-alpine
WORKDIR /app
COPY --from=deps /app-laravel/node_modules ./node_modules
COPY --from=builder /app-laravel/dist ./dist
COPY package.json ./

USER node
EXPOSE 3000
CMD ["node", "dist/server.js"]
```

### Test Stage Daxil Etmək

```dockerfile
# Base stage
FROM php:8.3-fpm-alpine AS base
RUN docker-php-ext-install pdo_mysql
WORKDIR /var/www/html

# Dependencies
FROM base AS deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-scripts --prefer-dist

# Test stage
FROM deps AS test
COPY . .
RUN composer install  # dev dependency-lər daxil
RUN php artisan test
RUN ./vendor/bin/phpstan analyse

# Production stage
FROM base AS production
COPY --from=deps /var/www/html/vendor ./vendor
COPY . .
RUN php artisan config:cache && php artisan route:cache
USER www-data
CMD ["php-fpm"]
```

## PHP/Laravel ilə İstifadə

### Tam Laravel Production Dockerfile (Multi-Stage)

```dockerfile
# ============================================
# Stage 1: Composer Dependencies
# ============================================
FROM composer:2 AS composer-deps

WORKDIR /app

# Composer fayllarını kopyala (cache üçün)
COPY composer.json composer.lock ./

# Yalnız production dependency-lər
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# Tam kodu kopyala və autoloader optimize et
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ============================================
# Stage 2: Frontend Assets (Vite/Mix)
# ============================================
FROM node:20-alpine AS frontend-build

WORKDIR /app

# Node dependency-lər (cache üçün əvvəl)
COPY package.json package-lock.json ./
RUN npm ci

# Frontend mənbə kodları
COPY resources/ resources/
COPY vite.config.js tailwind.config.js postcss.config.js ./

# Asset build
RUN npm run build

# ============================================
# Stage 3: Final Production Image
# ============================================
FROM php:8.3-fpm-alpine AS production

# Metadata
LABEL maintainer="dev@example.com"
LABEL description="Laravel Production Image"

# Sistem asılılıqları
RUN apk add --no-cache \
    freetype-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# OPcache konfiqurasiyası
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/opcache.ini

# İş qovluğu
WORKDIR /var/www/html

# Composer dependency-ləri (Stage 1-dən)
COPY --from=composer-deps /app-laravel/vendor ./vendor

# Frontend asset-ləri (Stage 2-dən)
COPY --from=frontend-build /app-laravel/public/build ./public/build

# Tətbiq kodu
COPY . .

# Laravel optimization
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Qovluq icazələri
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Non-root istifadəçi
USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

CMD ["php-fpm"]
```

### Nginx üçün Ayrı Multi-Stage

```dockerfile
# Stage 1: Frontend build (eyni stage-i paylaşmaq)
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# Stage 2: Nginx with static files
FROM nginx:1.25-alpine

# Nginx konfiqurasiyası
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Statik fayllar
COPY public/ /var/www/html/public/
COPY --from=frontend /app-laravel/public/build /var/www/html/public/build

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

### Ölçü Müqayisəsi

```bash
# Multi-stage olmadan
# myapp:single-stage    ~850MB

# Multi-stage ilə
# myapp:multi-stage     ~150MB

# Ölçüləri yoxlamaq
docker images myapp
```

## İntervyu Sualları

### 1. Multi-stage build nədir və niyə istifadə olunur?
**Cavab:** Bir Dockerfile-da birdən çox FROM istifadə edərək build prosesini mərhələlərə ayırmaq texnikasıdır. Build alətləri və aralıq fayllar son image-ə daxil olmur. Nəticədə image ölçüsü kiçilir, təhlükəsizlik artır (az komponent = az attack surface), build prosesi təmiz qalır.

### 2. `COPY --from` nə edir?
**Cavab:** Başqa bir build stage-dən və ya xarici image-dən fayl kopyalayır. Stage-ə ad (`AS builder`) və ya nömrə (0, 1, 2...) ilə istinad edilir. Həmçinin xarici image-dən birbaşa kopyalamaq mümkündür: `COPY --from=composer:2 /usr/bin/composer`.

### 3. `--target` flaqı nə üçün istifadə olunur?
**Cavab:** Build prosesini müəyyən stage-də dayandırır. CI/CD-də fərqli stage-lər üçün fərqli image qurmaq üçün istifadə olunur. Məsələn, `--target test` yalnız test stage-inə qədər build edir, `--target production` isə tam production image qurur.

### 4. Multi-stage build-lərdə cache necə işləyir?
**Cavab:** Hər stage-in öz cache layer-ləri var. Stage-lər paralel build oluna bilər (BuildKit ilə). Bir stage dəyişdikdə yalnız həmin stage və ondan asılı olan hissələr yenidən qurulur. Dependency fayllarını əvvəl kopyalamaq (composer.json, package.json) cache-i qoruyur.

### 5. Laravel üçün ideal multi-stage strategiya nədir?
**Cavab:** 3 stage: (1) Composer stage — dependency-lər quraşdırılır, (2) Node stage — frontend asset-lər build olunur, (3) PHP-FPM stage — yalnız runtime, vendor qovluğu və build olunmuş asset-lər kopyalanır. Nəticədə git, npm, node_modules son image-dən xaric olur.

### 6. Multi-stage build image ölçüsünü necə azaldır?
**Cavab:** Yalnız son stage-in layer-ləri final image-ə daxil olur. Build alətləri (gcc, make, npm, git), source fayllar, dev dependency-lər, test faylları — hamısı aralıq stage-lərdə qalır. Məsələn, Go tətbiqi 1GB+ build image-dən 10-15MB final image yarada bilir.

### 7. `FROM scratch` nədir?
**Cavab:** Tamamilə boş base image. Heç bir OS, shell, alət yoxdur. Statik link olunmuş binary-lər üçün istifadə olunur (Go, Rust). Ən kiçik mümkün image ölçüsünü verir. Ancaq debug etmək çətindir çünki shell yoxdur.

## Best Practices

1. **Stage-lərə mənalı adlar verin** — `AS builder`, `AS frontend`, `AS production`.
2. **Dependency fayllarını əvvəl kopyalayın** — Cache-dən maksimum faydalanın.
3. **Hər stage-i mümkün qədər minimal saxlayın** — Yalnız lazım olanı quraşdırın.
4. **Test stage əlavə edin** — CI/CD-də `--target test` ilə testləri ayrıca icra edin.
5. **BuildKit istifadə edin** — Paralel stage build, cache mount, secret mount dəstəyi.
6. **Son stage-də alpine istifadə edin** — Minimal base image seçin.
7. **`--no-dev` istifadə edin** — Composer/npm-də dev dependency-ləri production-a daxil etməyin.
8. **Stage-ləri yenidən istifadə edin** — Eyni base stage-dən development və production image-lər qurun.
9. **Xarici image-lərdən alətləri kopyalayın** — `COPY --from=composer:2` kimi.
10. **Image ölçüsünü izləyin** — Hər build-dən sonra `docker images` ilə yoxlayın.
