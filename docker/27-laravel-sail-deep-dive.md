# Laravel Sail Deep Dive

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Laravel Sail — Laravel üçün light-weight command-line interface olub, Docker mühitində Laravel tətbiqinin inkişafını asanlaşdırır. Developer PHP, MySQL, Redis, MailHog və s. servisləri lokal olaraq Docker container-lərində işlədir, host maşında PHP/MySQL quraşdırmağa ehtiyac yoxdur.

Sail əslində Docker Compose üzərində qurulmuş bir wrapper-dir. `sail` əmri `docker compose exec` əmrlərinin qısaldılmış versiyasıdır.

## Əsas Konseptlər

### 1. Sail Arxitekturası

```
┌────────────────────────────────────────────────┐
│              Host Machine                       │
│  ┌────────────────────────────────────────┐   │
│  │         Docker Compose Network          │   │
│  │                                          │   │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐ │   │
│  │  │Laravel  │  │ MySQL   │  │ Redis   │ │   │
│  │  │ (PHP    │  │ 8.0     │  │ 7       │ │   │
│  │  │  8.3)   │  │         │  │         │ │   │
│  │  └─────────┘  └─────────┘  └─────────┘ │   │
│  │       │                                  │   │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐ │   │
│  │  │ Mailpit │  │Meilisrch│  │Minio/S3 │ │   │
│  │  └─────────┘  └─────────┘  └─────────┘ │   │
│  └────────────────────────────────────────┘   │
│           ↑                                     │
│    localhost:80, localhost:3306, ...            │
└────────────────────────────────────────────────┘
```

### 2. Sail Quraşdırma

Sail Laravel 8+ üçün standart olaraq quraşdırılıb. Yeni layihə:

```bash
# Yeni Laravel + Sail layihəsi
curl -s "https://laravel.build/example-app?with=mysql,redis,mailpit,meilisearch" | bash

cd example-app
./vendor/bin/sail up -d

# Və ya Alias yaradıb qısa istifadə
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
sail up -d
```

Mövcud layihəyə əlavə:

```bash
composer require laravel --dev laravel/sail
php artisan sail:install
```

### 3. docker-compose.yml Strukturu

Sail-in publish etdiyi docker-compose.yml:

```yaml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.3
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
        image: sail-8.3/app
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        ports:
            - '${APP_PORT:-80}:80'
            - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
        environment:
            WWWUSER: '${WWWUSER}'
            LARAVEL_SAIL: 1
            XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
            XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
        volumes:
            - '.:/var/www/html'
        networks:
            - sail
        depends_on:
            - mysql
            - redis
            - mailpit
    mysql:
        image: 'mysql/mysql-server:8.0'
        ports:
            - '${FORWARD_DB_PORT:-3306}:3306'
        environment:
            MYSQL_ROOT_PASSWORD: '${DB_PASSWORD}'
            MYSQL_DATABASE: '${DB_DATABASE}'
        volumes:
            - 'sail-mysql:/var/lib/mysql'
        networks:
            - sail
        healthcheck:
            test: ['CMD', 'mysqladmin', 'ping', '-p${DB_PASSWORD}']
            retries: 3
            timeout: 5s

    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
        healthcheck:
            test: ['CMD', 'redis-cli', 'ping']

networks:
    sail:
        driver: bridge

volumes:
    sail-mysql:
        driver: local
    sail-redis:
        driver: local
```

### 4. Sail Runtimes (PHP Dockerfile)

Sail `vendor/laravel/sail/runtimes/8.3/Dockerfile` daxilində PHP imaji hazırlayır:

```dockerfile
FROM ubuntu:22.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=20
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=15

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 \
    && mkdir -p ~/.gnupg \
    && chmod 600 ~/.gnupg \
    && echo "disable-ipv6" >> ~/.gnupg/dirmngr.conf \
    && echo "keyserver hkp://keyserver.ubuntu.com:80" >> ~/.gnupg/dirmngr.conf \
    && apt-get install -y php8.3-cli php8.3-dev \
       php8.3-pgsql php8.3-sqlite3 php8.3-gd php8.3-imagick \
       php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip \
       php8.3-bcmath php8.3-soap php8.3-intl php8.3-readline \
       php8.3-ldap php8.3-redis php8.3-memcached php8.3-mongodb

RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

## Praktiki Nümunələr

### Ümumi Sail Əmrləri

```bash
# Servisləri başlat (background-da)
sail up -d

# Logları izlə
sail logs -f
sail logs -f laravel.test

# Servisləri dayandır
sail down

# Volume-ları da sil
sail down -v

# Container-də komanda işlət
sail artisan migrate
sail artisan tinker
sail composer require spatie/laravel-permission
sail npm install
sail npm run dev

# MySQL CLI
sail mysql
sail mysql -u root -p

# Redis CLI
sail redis

# Tests
sail test
sail pest
sail phpunit --filter UserTest

# Bash shell
sail shell
sail root-shell
```

### Custom Servislər Əlavə etmək

Sail-ə Elasticsearch əlavə etmək:

```yaml
# docker-compose.yml-ə əlavə
elasticsearch:
    image: 'elasticsearch:8.11.0'
    environment:
        discovery.type: single-node
        xpack.security.enabled: 'false'
        ES_JAVA_OPTS: '-Xms512m -Xmx512m'
    ports:
        - '${FORWARD_ELASTICSEARCH_PORT:-9200}:9200'
    volumes:
        - 'sail-elasticsearch:/usr/share/elasticsearch/data'
    networks:
        - sail

# Volume-a əlavə
volumes:
    sail-elasticsearch:
        driver: local
```

### XDebug Quraşdırmaq

```bash
# .env faylında
SAIL_XDEBUG_MODE=develop,debug,coverage
SAIL_XDEBUG_CONFIG="client_host=host.docker.internal"

# Yenidən başlat
sail down && sail up -d
```

PHPStorm konfiqurasiyası:
```
Preferences → PHP → Servers
Name: Docker
Host: localhost
Port: 80
Use path mappings: 
  Project root → /var/www/html
```

### Share (Expose to Internet)

Sail `share` əmri ilə lokal tətbiqi internetdə paylaşmaq olur (Expose xidməti vasitəsilə):

```bash
sail share --subdomain=my-laravel-app
# => https://my-laravel-app.expose.dev
```

Webhook test etmək (Stripe, GitHub) üçün faydalıdır.

## PHP/Laravel ilə İstifadə

### Production Dockerfile (Sail alternativ)

Sail development üçündür. Production üçün custom multi-stage Dockerfile:

```dockerfile
# Build stage
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Frontend build stage
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources/ resources/
RUN npm ci && npm run build

# Final stage
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

RUN apk add --no-cache \
    nginx supervisor \
    && docker-php-ext-install pdo_mysql opcache

COPY --from=vendor /app-laravel/vendor /var/www/html/vendor
COPY --from=frontend /app-laravel/public/build /var/www/html/public/build
COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data

CMD ["php-fpm"]
```

### Multi-environment Sail

```bash
# docker-compose.override.yml — development
# docker-compose.prod.yml — production (must build custom image)

# Sadəcə override:
sail up -d

# Production compose:
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### Publishing Sail Config

Sail-in docker-compose.yml-ini dəyişmək üçün publish:

```bash
sail artisan sail:publish
# => runtimes/8.3/Dockerfile layihəyə kopyalanır
# İndi həmin fayla əlavə edilməli extension, pack və s. olar
```

```dockerfile
# runtimes/8.3/Dockerfile-ə əlavə
RUN apt-get install -y php8.3-imagick \
    && pecl install redis \
    && docker-php-ext-enable redis
```

```bash
# Yenidən build
sail build --no-cache
sail up -d
```

## İntervyu Sualları

**1. Laravel Sail nədir və Docker-dən nə ilə fərqlənir?**
Sail Docker Compose üzərində wrapper-dir. `sail artisan migrate` əslində `docker compose exec laravel.test php artisan migrate`. Sail Laravel üçün pre-konfiqurə edilmiş servis stack təklif edir.

**2. Sail production-da istifadə edilir?**
Xeyr. Sail development üçün nəzərdə tutulub. Production üçün custom multi-stage Dockerfile, Nginx+PHP-FPM, optimized image lazımdır.

**3. Sail-ə yeni servis necə əlavə olunur?**
`docker-compose.yml` faylını redaktə edib yeni servis bloku, port, volume, network və depends_on əlavə olunur. Sonra `sail down && sail up -d`.

**4. XDebug Sail-də necə işləyir?**
`.env`-də `SAIL_XDEBUG_MODE=debug` və `SAIL_XDEBUG_CONFIG="client_host=host.docker.internal"`. IDE-də (PHPStorm) path mapping qurulur: project root → /var/www/html.

**5. Sail container-lərinin performansı macOS-da niyə zəif olur?**
macOS-da Docker bind mount volume-ları yavaş işləyir (osxfs/VirtioFS). Həll: Mutagen sync, VirtioFS (Docker Desktop 4.6+), yaxud cached/delegated mount options.

**6. `sail share` necə işləyir?**
Sail Expose xidmətinə (Beyond Code) tunelləşdirir. Lokal tətbiqi internetdə ifşa edir (ngrok-a oxşar) — webhook test, demo üçün yaxşıdır.

**7. Sail ilə birdən çox PHP versiyası lokal işlədə bilərik?**
Bəli. Hər bir layihənin öz docker-compose.yml-i var, fərqli `APP_PORT`, `FORWARD_DB_PORT` konfiqurasiyası ilə paralel işləyə bilər.

**8. Sail volume-ları harada saxlanır?**
Named volumes: `sail-mysql`, `sail-redis`. Docker Desktop-da `/var/lib/docker/volumes` altında. `docker volume ls` və `docker volume inspect sail-mysql`.

## Best Practices

1. **Production-da Sail istifadə etmə** — custom Dockerfile + Nginx/PHP-FPM qur
2. **`.env` faylında portları dəyiş** — eyni vaxtda birdən çox layihə işlədəndə (`APP_PORT=8080`, `FORWARD_DB_PORT=33060`)
3. **Named volume istifadə et** — bind mount DB data üçün yavaş/problemlidir
4. **Sail publish et** — custom extension, tool əlavə etməyə ehtiyac olsa
5. **`depends_on` + healthcheck** — servislər düzgün ardıcıllıqla başlayanda
6. **Git-ə docker-compose.yml commit et** — komanda eyni mühitdə işləsin
7. **`.dockerignore`** istifadə et — `node_modules`, `vendor` container-ə kopyalanmasın
8. **macOS-da Mutagen/VirtioFS** — file sync performance üçün
9. **`sail artisan queue:work`** — queue worker dedicated container-də işlət
10. **Supervisor** quraşdır — production-da PHP-FPM, queue worker, schedule idarəsi üçün


## Əlaqəli Mövzular

- [docker-compose.md](05-docker-compose.md) — Docker Compose əsasları
- [dev-vs-prod-docker-setup.md](44-dev-vs-prod-docker-setup.md) — Dev vs prod setup
- [database-services-in-docker.md](06-database-services-in-docker.md) — Database servislər
