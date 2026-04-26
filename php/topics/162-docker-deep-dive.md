# Docker (Senior)

## Mündəricat
1. [Docker Arxitekturası](#docker-arxitekturası)
2. [Dockerfile Best Practices](#dockerfile-best-practices)
3. [Multi-stage Build](#multi-stage-build)
4. [Networking](#networking)
5. [Volumes](#volumes)
6. [Docker Compose](#docker-compose)
7. [PHP Production Setup](#php-production-setup)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Docker Arxitekturası

```
// Bu kod Docker Engine arxitekturasını, container vs VM fərqini izah edir
┌─────────────────────────────────────────────────┐
│                  Host OS                        │
│                                                 │
│  ┌──────────────────────────────────────────┐   │
│  │           Docker Engine                  │   │
│  │                                          │   │
│  │  ┌────────────┐  ┌────────────────────┐  │   │
│  │  │  Container │  │    Container       │  │   │
│  │  │  (PHP-FPM) │  │    (MySQL)         │  │   │
│  │  │            │  │                    │  │   │
│  │  │  Isolated  │  │    Isolated        │  │   │
│  │  │  process   │  │    process         │  │   │
│  │  └────────────┘  └────────────────────┘  │   │
│  │        Shared OS Kernel                  │   │
│  └──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘

Container vs VM:
  VM: tam OS, hypervisor, ağır (GBs, dəqiqələr)
  Container: shared kernel, yüngül (MBs, saniyələr)
  
Komponentlər:
  Docker Engine (daemon): arxa planda çalışır
  Docker Client (CLI): docker komandaları
  Docker Registry: image-ləri saxlayır (Docker Hub, ECR)
  Image: read-only template (layers)
  Container: image-in running instance-ı
```

---

## Dockerfile Best Practices

*Dockerfile Best Practices üçün kod nümunəsi:*
```dockerfile
# Bu kod yaxşı Dockerfile layer cache strategiyasını pis nümunə ilə müqayisəli göstərir
# ❌ PISI — hər şey bir layer, cache yoxdur
FROM php:8.3-fpm
COPY . /var/www
RUN apt-get update && apt-get install -y git && \
    composer install && \
    php artisan optimize

# ✅ YAXŞI — layer cache-ini istifadə et
FROM php:8.3-fpm

# 1. Sistem paketləri (ən az dəyişən)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-install pdo_mysql zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Composer (az dəyişən)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Dependencies (composer.json dəyişəndə)
WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# 4. Kod (ən çox dəyişən — sonuncu)
COPY . .
RUN composer dump-autoload --optimize

# Layer cache prinsipi:
# Dəyişən şeylər sonra gəlsin ki,
# dəyişməyən layerlər cache-dən istifadə etsin
```

**Image ölçüsünü azalt:**

```dockerfile
# Bu kod Alpine base image və .dockerignore ilə image ölçüsünü azaltmağı göstərir
# Alpine base — minimal
FROM php:8.3-fpm-alpine

# Multi-stage: build artifacts-ı prod image-ə kopyala
# (aşağıda izah edilir)

# .dockerignore — lazımsız faylları exclude et
# .dockerignore faylı:
.git
node_modules
vendor
.env
*.log
tests/
docker/
README.md
```

---

## Multi-stage Build

*Multi-stage Build üçün kod nümunəsi:*

```dockerfile
# Bu kod multi-stage build ilə composer, node və production image-lərini ayrı mərhələlərdə qurur
# Stage 1: Dependencies (build stage)
FROM composer:2 AS composer-stage
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# Stage 2: Assets (frontend build)
FROM node:20-alpine AS node-stage
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# Stage 3: Production image (yalnız lazımlı şeylər)
FROM php:8.3-fpm-alpine AS production

RUN apk add --no-cache \
    libpng \
    libzip \
    && docker-php-ext-install pdo_mysql zip gd opcache

# Yalnız production dependencies kopyala
COPY --from=composer-stage /app-laravel/vendor /var/www/vendor

# Build edilmiş assets
COPY --from=node-stage /app-laravel/public/build /var/www/public/build

# Kod
COPY . /var/www

# OPcache konfiqurasiyası
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Non-root user (security)
RUN adduser -D -u 1000 appuser
USER appuser

EXPOSE 9000
CMD ["php-fpm"]

# Nəticə: yalnız runtime lazım olan şeylər var
# composer, node, npm, dev packages yoxdur!
```

---

## Networking

```
// Bu kod Docker network driver növlərini və istifadə hallarını izah edir
Docker network driver-ları:

bridge (default):
  Container-lər arasında private network
  Host-dan izolə
  Container adı ilə DNS resolution

host:
  Container host network-ü birbaşa istifadə edir
  Performance yüksəkdir
  İzolasiya azdır

none:
  Network yoxdur, tam izolasiya

overlay:
  Docker Swarm/multi-host networking
  Container-lər fərqli hostlarda olub danışa bilir

```

*Container-lər fərqli hostlarda olub danışa bilir üçün kod nümunəsi:*
```bash
# Bu kod custom Docker network yaratmağı və container-lar arasında DNS əlaqəsini göstərir
# Custom network yarat
docker network create app-network

# Container-ı network-ə qoş
docker run --network app-network --name php-app php:8.3-fpm
docker run --network app-network --name db mysql:8

# php-app container-ından db-yə əlçatımlıdır:
# mysql -h db -u root -p  ← container adı ilə DNS

# Port binding: host:container
docker run -p 8080:80 nginx  # host:8080 → container:80

# Network inspect
docker network inspect app-network
```

*docker network inspect app-network üçün kod nümunəsi:*
```yaml
# Bu kod docker-compose-da frontend/backend network izolyasiyasını göstərir
# docker-compose.yml — network konfiqurasiyası
services:
  nginx:
    networks: [frontend, backend]
  
  php:
    networks: [backend]
  
  mysql:
    networks: [backend]
  
  # nginx həm frontend-ə, həm backend-ə çatır
  # mysql yalnız backend-dədir (internet-ə açıq deyil!)

networks:
  frontend:
  backend:
    internal: true  # Host-dan əlçatılmaz
```

---

## Volumes

```
// Bu kod Docker volume növlərini (named, bind mount, tmpfs) izah edir
Volume növləri:

Named Volume:
  docker volume create mydata
  Docker idarə edir, /var/lib/docker/volumes/
  Container silinəndə data qalır

Bind Mount:
  host path → container path
  Development üçün ideal (kod dəyişikliyi anında görünür)

tmpfs:
  RAM-da, geçici
  Sensitive data üçün (secrets)
```

*Sensitive data üçün (secrets) üçün kod nümunəsi:*
```yaml
# Bu kod named volume və bind mount-un docker-compose-da konfiqurasiyasını göstərir
# docker-compose.yml — volumes
services:
  mysql:
    image: mysql:8
    volumes:
      # Named volume — data persist olur
      - mysql_data:/var/lib/mysql
    
  php:
    volumes:
      # Bind mount — development-də kod sync
      - ./:/var/www:cached
      # :cached — Mac-da performance (read heavy)
      # :delegated — write heavy
      
      # Vendor-u container-da saxla (host-a mount etmə!)
      - vendor_cache:/var/www/vendor

volumes:
  mysql_data:
  vendor_cache:
```

---

## Docker Compose

*Docker Compose üçün kod nümunəsi:*
```yaml
# Bu kod nginx, php, mysql, redis və queue worker-dən ibarət tam PHP stack-i göstərir
# docker-compose.yml — tam PHP stack
version: '3.9'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www
      - ./docker/nginx/conf.d:/etc/nginx/conf.d
    depends_on:
      php:
        condition: service_healthy
    networks: [app]

  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: development  # Multi-stage: dev vs prod
    volumes:
      - ./:/var/www
      - vendor_cache:/var/www/vendor
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - REDIS_HOST=redis
    healthcheck:
      test: ["CMD", "php-fpm-healthcheck"]
      interval: 10s
      timeout: 5s
      retries: 3
    depends_on:
      mysql:
        condition: service_healthy
    networks: [app]

  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      retries: 10
    networks: [app]

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks: [app]

  queue:
    build:
      context: .
      target: development
    command: php artisan queue:work --sleep=3 --tries=3
    volumes:
      - ./:/var/www
      - vendor_cache:/var/www/vendor
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on: [mysql, redis]
    networks: [app]

volumes:
  mysql_data:
  redis_data:
  vendor_cache:

networks:
  app:
    driver: bridge
```

---

## PHP Production Setup

*PHP Production Setup üçün kod nümunəsi:*
```dockerfile
# Bu kod development və production mərhələləri olan PHP Dockerfile-ı OPcache konfiqurasiyası ilə göstərir
# docker/php/Dockerfile
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    libpng-dev libzip-dev libpq-dev \
    && docker-php-ext-install \
       pdo pdo_mysql pdo_pgsql \
       zip gd opcache pcntl \
    && apk del libpng-dev libzip-dev libpq-dev

# OPcache — production üçün
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
# opcache.ini:
# opcache.enable=1
# opcache.memory_consumption=256
# opcache.max_accelerated_files=20000
# opcache.validate_timestamps=0  ← prod: 0, dev: 1

FROM base AS development
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

FROM base AS production
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader
COPY . .
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
```

---

## İntervyu Sualları

**1. Docker Container vs VM fərqi nədir?**
VM: tam OS, hypervisor üzərindədir, GBs, dəqiqələrlə başlayır. Container: host OS kernel-ini paylaşır, MBs, saniyələrdə başlayır. Container daha yüngül, daha sürətli amma daha az izolasiya.

**2. Multi-stage build nədir, niyə lazımdır?**
Bir Dockerfile-da bir neçə FROM. Hər stage bir məqsəd üçün. Final image-ə yalnız lazımlı artefaktlar kopyalanır. Composer, Node, dev tools final image-ə daxil olmur. Image ölçüsü kəskin azalır (1GB → 100MB).

**3. Layer cache necə işləyir, necə optimallaşdırılır?**
Dockerfile-dakı hər RUN/COPY/ADD yeni layer yaradır. Layer dəyişməyibsə cache-dən istifadə edilir. Ən az dəyişən şeylər yuxarıda, ən çox dəyişənlər aşağıda olmalıdır. composer.json dəyişmədən composer install cache-dən işləyir.

**4. Named volume vs bind mount fərqi nədir?**
Bind mount: host path-i container-a mount edir, development-də ideal (kod sync). Named volume: Docker idarə edir, production-da data persistence üçün, container silinəndə qalır.

**5. Container-lər bir-biri ilə necə kommunikasiya edir?**
Eyni Docker network-dədirsə, container adı ilə DNS resolution: `mysql`, `redis`. docker-compose avtomatik network yaradır. Port binding lazım deyil (internal) — yalnız host-a expose etmək üçün `-p` lazımdır.

**6. `COPY` vs `ADD` fərqi nədir?**
`COPY`: sadəcə faylları kopyalayır, tövsiyə edilən. `ADD`: əlavə olaraq URL-dən yükləmə və tar arxivini avtomatik extract edir. Amma `ADD`-nin bu gizli davranışı çaşqınlıq yaradır — yalnız `COPY` istifadə edin, URL lazımdırsa `RUN curl` işlədin.

**7. OPcache-i production-da necə konfigurasiya etmək lazımdır?**
`opcache.validate_timestamps=0` — faylın timestamp-ini yoxlamır (restart-sız kod dəyişikliyi görünmür). `opcache.memory_consumption=256` — kifayət qədər memory. `opcache.max_accelerated_files=20000` — bütün PHP faylları sığdırmaq üçün. Dev-də `validate_timestamps=1` olmalıdır.

**8. Docker image-ləri necə təhlükəsiz etmək lazımdır?**
Non-root user (`USER www-data`). Minimal base image (alpine). `.dockerignore` ilə `.env`, `tests/` exclude. `--cap-drop ALL`. Image-ləri vulnerability scanner (Trivy, Snyk) ilə yoxla. Secrets environment variable kimi inject et, image-ə bişirmə.

---

## Anti-patternlər

**1. Sık dəyişən faylları Dockerfile-ın əvvəlinə qoymaq**
`COPY . .` ilk sətirlərdə yerləşdirmək — hər kod dəyişikliyindən sonra bütün layer-lar, o cümlədən `composer install`, yenidən çalışır, build vaxtı çox uzanır. Ən az dəyişən şeyləri (base image, sistem paketlər, `composer.json`) yuxarıya, ən çox dəyişəni (`COPY . .`) aşağıya qoyun.

**2. `.dockerignore` faylı olmadan build etmək**
`COPY . .` ilə `vendor/`, `.git/`, `node_modules/`, `.env` bütün layerə daxil olur — image ölçüsü şişir, sensitive data image-ə girə bilər. `.dockerignore` yaradın: `vendor`, `.git`, `*.log`, `.env` mütləq siyahıya daxil edilsin.

**3. Multi-stage build olmadan production image qurmaq**
Composer, Node, xdebug, dev dependency-lər production image-ə daxil olur — image 1GB+ olur, attack surface genişdir. Multi-stage build istifadə edin: `builder` stage-dən yalnız `vendor/` və compiled asset-ləri final production image-ə kopyalayın.

**4. Named volume əvəzinə container içinə data yazmaq**
MySQL data, upload fayllar container filesystem-ə yazılır — container silinəndə bütün data itirilir. Production data üçün mütləq named volume istifadə edin: `docker volume create` ilə yaradın, container-i silsəniz data qalsın.

**5. `latest` tag ilə production deploy etmək**
`image: php:latest` — gözlənilmədən yeni version çıxır, behavior dəyişir, build reproducible deyil. Həmişə explicit version pin edin: `php:8.3.4-fpm-alpine`; CI/CD pipeline-da image-lərinizi də SHA digest ilə tag edin.

**6. Hər container-i `--privileged` ilə işlətmək**
Debug üçün `--privileged` əlavə edilir, production-a çıxır — container host kernel-ə tam giriş əldə edir, security boundary pozulur. Minimal capability-lərlə işləyin: `--cap-drop ALL --cap-add` yalnız lazımlı olanı əlavə edin; privileged heç vaxt production-da olmasın.
