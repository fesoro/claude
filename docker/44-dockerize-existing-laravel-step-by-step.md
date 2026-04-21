# Dockerize Existing Laravel Project — Step by Step

## Nədir? (What is it?)

Mövcud Laravel layihəni sıfırdan Docker-ə keçirmək üçün bu fayl **təcrübədə sınanmış addım-addım yol xəritəsidir**. Yeni layihə üçün `laravel new` + Sail yaxşıdır — amma real dünyada əksər iş köhnə layihələri dockerize etməkdir.

Bu fayl bir Laravel 10/11 layihənin `git clone` etdiyini fərz edir və 2 saat içində **dev üçün tam işlək Docker mühiti** qurmağı göstərir. Sonra production üçün necə genişləndirəcəyini izah edir.

## Həll edəcək Problemlər

- [ ] Host-da PHP/MySQL/Redis quraşdırmağa ehtiyac yoxdur
- [ ] `docker compose up -d` hər şeyi qaldırır
- [ ] Kod dəyişiklikləri dərhal görünür (bind mount)
- [ ] `artisan`, `composer`, `npm` konteynerdə işləyir
- [ ] Database persistent volume-dadır
- [ ] Xdebug VS Code/PHPStorm ilə işləyir
- [ ] Queue worker + scheduler ayrı konteynerdədir
- [ ] Production Dockerfile hazırdır CI/CD üçün

## Ön Şərtlər

Host-da lazımdır:
- Docker 24+ və Docker Compose v2+
- Git
- IDE (VS Code və ya PHPStorm)

Lokal-da lazım DEYİL:
- PHP
- MySQL
- Redis
- Composer (konteynerdə)
- Node (konteynerdə)

## Addım 1: Layihə Strukturu

Mövcud layihədə `docker/` qovluğu yarat:

```
myapp/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── vendor/             ← gitignore
├── node_modules/       ← gitignore
├── .env                ← gitignore
├── .env.example
├── composer.json
├── composer.lock
├── package.json
├── artisan
│
├── docker/             ← YENİ
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   ├── php.ini
│   │   ├── xdebug.ini
│   │   └── www.conf
│   ├── entrypoint.sh
│   └── scheduler-loop.sh
├── Dockerfile          ← YENİ
├── docker-compose.yml  ← YENİ
├── docker-compose.override.yml  ← YENİ
└── .dockerignore       ← YENİ
```

## Addım 2: `.dockerignore`

```gitignore
# .dockerignore
.git
.gitignore
.gitattributes
.github

.env
.env.*
!.env.example

node_modules
vendor
public/build
public/hot
public/storage

storage/framework/cache/*
!storage/framework/cache/.gitignore
storage/framework/sessions/*
!storage/framework/sessions/.gitignore
storage/framework/views/*
!storage/framework/views/.gitignore
storage/logs/*
!storage/logs/.gitignore

docker-compose*.yml
Dockerfile*
.dockerignore

tests
phpunit.xml
.phpunit.cache
.phpunit.result.cache
*.md

.vscode
.idea
.fleet
```

## Addım 3: Dockerfile (Multi-Stage)

```dockerfile
# ============================================================
# Base — hər iki dev və prod üçün ümumi
# ============================================================
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        tini \
        curl \
        icu-dev \
        libzip-dev \
        libpng-dev \
        oniguruma-dev \
        postgresql-dev \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf gcc g++ make \
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

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# ============================================================
# Dev — Xdebug + composer + dev paketlər
# ============================================================
FROM base AS dev

RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-deps

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000

RUN deluser www-data 2>/dev/null; \
    addgroup -g ${GID} -S www-data && \
    adduser -u ${UID} -D -S -G www-data www-data

USER www-data

CMD ["php-fpm"]

# ============================================================
# Vendor — production dependencies
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader --ignore-platform-reqs

# ============================================================
# Frontend — Vite build
# ============================================================
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# ============================================================
# Production — final minimal image
# ============================================================
FROM base AS production

COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY --chown=www-data:www-data . .

RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN php artisan view:cache route:cache event:cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/scheduler-loop.sh /usr/local/bin/scheduler-loop.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/scheduler-loop.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

## Addım 4: Docker Compose

### `docker-compose.yml` (base)

```yaml
services:
  app:
    build:
      context: .
      target: dev
      args:
        UID: ${UID:-1000}
        GID: ${GID:-1000}
    image: myapp:dev
    restart: unless-stopped
    working_dir: /var/www/html
    environment:
      - CONTAINER_ROLE=app
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - myapp

  nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app
    networks:
      - myapp

  mysql:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-password}
      MYSQL_DATABASE: ${DB_DATABASE:-myapp}
      MYSQL_USER: ${DB_USERNAME:-myapp}
      MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
    ports:
      - "${FORWARD_DB_PORT:-3306}:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-uroot", "-p${DB_ROOT_PASSWORD:-password}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - myapp

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    ports:
      - "${FORWARD_REDIS_PORT:-6379}:6379"
    volumes:
      - redis-data:/data
    networks:
      - myapp

  worker:
    build:
      context: .
      target: dev
      args:
        UID: ${UID:-1000}
        GID: ${GID:-1000}
    image: myapp:dev
    restart: unless-stopped
    command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600"]
    environment:
      - CONTAINER_ROLE=worker
    env_file:
      - .env
    depends_on:
      - redis
      - mysql
    networks:
      - myapp

  scheduler:
    build:
      context: .
      target: dev
    image: myapp:dev
    restart: unless-stopped
    command: ["/usr/local/bin/scheduler-loop.sh"]
    environment:
      - CONTAINER_ROLE=scheduler
    env_file:
      - .env
    depends_on:
      - app
    networks:
      - myapp

  mailpit:
    image: axllent/mailpit
    restart: unless-stopped
    ports:
      - "1025:1025"
      - "${MAILPIT_UI_PORT:-8025}:8025"
    networks:
      - myapp

volumes:
  mysql-data:
  redis-data:

networks:
  myapp:
```

### `docker-compose.override.yml` (dev-only overrides)

```yaml
services:
  app:
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
      - /var/www/html/node_modules
    environment:
      - XDEBUG_MODE=debug
      - XDEBUG_CONFIG=client_host=host.docker.internal client_port=9003
    extra_hosts:
      - "host.docker.internal:host-gateway"
  
  worker:
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
  
  scheduler:
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
```

## Addım 5: Nginx Config

### `docker/nginx/default.conf`

```nginx
server {
    listen 80 default_server;
    server_name _;
    root /var/www/html/public;
    index index.php;
    
    access_log /dev/stdout;
    error_log /dev/stderr warn;
    
    client_max_body_size 20M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 60;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Qeyd: Nginx konteynerində `/var/www/html/public` yoxdur! Ya shared volume, ya da Nginx-ə də kod mount et:

```yaml
# docker-compose.override.yml
services:
  nginx:
    volumes:
      - ./public:/var/www/html/public:ro
```

Və ya daha yaxşı: app konteynerində Nginx də saxla (variant B) — amma bu faylda ayrı konteyner variantı var.

**Düzgün həll:** Paylaşılan volume ilə `public/` qovluğunu Nginx-də oxuya ver:

```yaml
services:
  app:
    volumes:
      - public-assets:/var/www/html/public
  
  nginx:
    volumes:
      - public-assets:/var/www/html/public:ro

volumes:
  public-assets:
```

Dev-də əl ilə bind mount daha sadədir.

## Addım 6: PHP Config

### `docker/php/php.ini`

```ini
memory_limit = 512M
max_execution_time = 120
upload_max_filesize = 20M
post_max_size = 20M

display_errors = On
log_errors = On
error_log = /proc/self/fd/2

date.timezone = UTC

opcache.enable = 1
opcache.memory_consumption = 128
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0
opcache.save_comments = 1
```

### `docker/php/www.conf`

```ini
[www]
user = www-data
group = www-data
listen = 9000

pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500

clear_env = no

access.log = /proc/self/fd/2
catch_workers_output = yes
decorate_workers_output = no

php_admin_value[memory_limit] = 256M
```

### `docker/php/xdebug.ini`

```ini
zend_extension=xdebug.so
xdebug.mode=develop,debug
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.start_with_request=yes
xdebug.idekey=VSCODE
xdebug.log=/tmp/xdebug.log
```

## Addım 7: Entrypoint Script

### `docker/entrypoint.sh`

```bash
#!/bin/sh
set -e

log() { echo "[entrypoint] $(date '+%H:%M:%S') $1"; }

# Role-a görə davranış
ROLE=${CONTAINER_ROLE:-app}
log "Starting as $ROLE"

# Hamısı üçün: DB gözlə
if [ -n "$DB_HOST" ]; then
    log "Waiting for DB at $DB_HOST:${DB_PORT:-3306}..."
    timeout=60
    while ! nc -z "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            echo "DB not reachable" >&2
            exit 1
        fi
        sleep 1
    done
    log "DB is ready"
fi

# Yalnız app role-da migration və cache
if [ "$ROLE" = "app" ]; then
    if [ ! -L public/storage ]; then
        log "Creating storage symlink"
        php artisan storage:link || true
    fi
    
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        log "Running migrations"
        php artisan migrate --force
    fi
    
    log "Caching config/routes/views"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

log "Executing: $*"
exec "$@"
```

### `docker/scheduler-loop.sh`

```bash
#!/bin/sh
set -e

trap 'exit 0' TERM INT

while true; do
    php /var/www/html/artisan schedule:run --verbose --no-interaction
    sleep 60
done
```

## Addım 8: `.env` Hazırla

```bash
cp .env.example .env
```

`.env`-də Docker üçün dəyiş:
```
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql           # service name
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="hello@myapp.test"

# Docker-specific
UID=1000
GID=1000
APP_PORT=8080
MAILPIT_UI_PORT=8025
```

## Addım 9: İlk Qaldırma

```bash
# UID/GID-i təyin et
export UID=$(id -u)
export GID=$(id -g)

# İlk dəfə build (5-10 dəqiqə)
docker compose build

# Qaldır
docker compose up -d

# Log-lara bax
docker compose logs -f

# Ayrı terminalda: APP_KEY generate
docker compose exec app php artisan key:generate

# Migration
docker compose exec app php artisan migrate

# Brauzerdə aç
open http://localhost:8080
```

## Addım 10: Workflow Komandaları

Əlverişliyi artırmaq üçün alias və ya Makefile:

### Makefile

```makefile
.PHONY: up down build rebuild logs shell art composer npm test fresh

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose build --no-cache

logs:
	docker compose logs -f --tail=100

shell:
	docker compose exec app sh

art:
	docker compose exec app php artisan $(cmd)

composer:
	docker compose exec app composer $(cmd)

npm:
	docker compose run --rm -w /var/www/html --entrypoint="" app sh -c "apk add --no-cache nodejs npm && npm $(cmd)"

test:
	docker compose exec app php artisan test

fresh:
	docker compose exec app php artisan migrate:fresh --seed

tinker:
	docker compose exec app php artisan tinker
```

İstifadə:
```bash
make up
make art cmd="make:model Post -m"
make composer cmd="require guzzlehttp/guzzle"
make test
make shell
```

### Bash Alias

```bash
# ~/.bashrc və ya ~/.zshrc
alias dc='docker compose'
alias art='docker compose exec app php artisan'
alias composer='docker compose exec app composer'
alias dcu='docker compose up -d'
alias dcd='docker compose down'
alias dcl='docker compose logs -f'
alias dcsh='docker compose exec app sh'
```

İstifadə:
```bash
art migrate
composer require spatie/laravel-permission
dcl app
```

## Addım 11: VS Code Xdebug Setup

### `.vscode/launch.json`

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}"
            }
        }
    ]
}
```

Run → Start Debugging (F5) → breakpoint qoy → brauzerdə aç.

## Addım 12: Frontend (Vite HMR)

`docker-compose.override.yml`-a əlavə:

```yaml
  vite:
    image: node:20-alpine
    working_dir: /app
    command: ["sh", "-c", "npm install && npm run dev -- --host 0.0.0.0"]
    ports:
      - "5173:5173"
    volumes:
      - .:/app
      - /app/node_modules
    networks:
      - myapp
```

`vite.config.js`:
```js
export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
    },
    plugins: [laravel(['resources/css/app.css', 'resources/js/app.js'])],
});
```

## Addım 13: Production Build Test

Dev-də işləyəndən sonra production-u test et:

```bash
# Production image qur
docker build --target=production -t myapp:prod .

# Test et
docker run --rm \
    -e APP_KEY=$(grep APP_KEY .env | cut -d '=' -f2-) \
    -e APP_ENV=production \
    -e APP_DEBUG=false \
    -e DB_HOST=host.docker.internal \
    -e DB_DATABASE=myapp \
    -e DB_USERNAME=root \
    -e DB_PASSWORD=password \
    -p 9000:9000 \
    myapp:prod

# Nginx ilə birlikdə production-like test:
# docker-compose.prod.yml yarat və istifadə et
```

## Typical İşləmə Patternləri

### Composer paket əlavə et
```bash
make composer cmd="require ramsey/uuid"
# Və ya:
docker compose exec app composer require ramsey/uuid
```

### Database reset
```bash
make fresh
```

### Log-lara bax
```bash
make logs
# Yalnız app:
docker compose logs -f app
```

### Queue retry failed
```bash
art queue:failed
art queue:retry all
```

### MySQL-ə bağlan
```bash
# Host-dan (port 3306 forward olunub)
mysql -h 127.0.0.1 -u myapp -p

# Konteyner içindən
docker compose exec mysql mysql -u myapp -psecret myapp
```

### Redis CLI
```bash
docker compose exec redis redis-cli
> keys *
> flushall
```

### Storage temizlə
```bash
art storage:clear       # və ya:
docker compose exec app sh -c 'rm -rf storage/framework/cache/data/*'
```

## Troubleshooting

### "Permission denied: storage/logs/laravel.log"

Fayl icazə problemi (42-ci fayl). Həll:
```bash
docker compose exec -u root app sh -c 'chown -R www-data:www-data storage bootstrap/cache'
```

Və ya UID match et Dockerfile-da.

### "Connection refused" (DB)

DB hazır olmadan app başladı. Entrypoint `nc -z` gözləyir — 60 saniyə timeout. MySQL ilk dəfə başladıqda 20-30 saniyə çəkir.

### "SQLSTATE[HY000] [2002]"

DB_HOST səhvdir. `.env`-də `DB_HOST=mysql` olmalıdır (service name), `127.0.0.1` yox.

### Xdebug işləmir

- `xdebug.client_host=host.docker.internal` düzdürmü?
- Linux-da `extra_hosts: ["host.docker.internal:host-gateway"]` varmı?
- VS Code port 9003-də dinləyirmi?
- Tarayıcıya "Xdebug Helper" extension quraşdır, IDE Key `VSCODE` qoy

### Vite manifest boş / yoxdur

Dev-də `npm run dev` lazımdır (vite container), prod-da `npm run build` build stage-də işləməlidir.

### "Mixed content" HTTPS-də

`APP_URL` və Vite HMR host SSL ilə uyğun deyil. `APP_URL=https://...`, `vite.config.js`-də `https: true`.

## Addım 14: Production Deployment (qısa)

CI/CD-də (46-cı fayl detallı):
```yaml
# .github/workflows/deploy.yml
- name: Build image
  run: docker build --target=production -t ghcr.io/me/myapp:${{ github.sha }} .

- name: Push
  run: docker push ghcr.io/me/myapp:${{ github.sha }}

- name: Deploy
  run: ssh server "docker compose -f /srv/myapp/docker-compose.prod.yml pull && docker compose -f /srv/myapp/docker-compose.prod.yml up -d"
```

## Checklist — Bitdi?

- [ ] `docker compose up -d` işləyir, xəta yoxdur
- [ ] `http://localhost:8080` Laravel welcome göstərir
- [ ] `php artisan migrate` işləyir
- [ ] `php artisan tinker`-də DB query işləyir
- [ ] Kod dəyişikliyi (controller) dərhal görünür
- [ ] Queue worker log-da `[INFO] Processing:` göstərir
- [ ] Scheduler 1 dəqiqədə 1 dəfə `schedule:run` çağırır
- [ ] Xdebug breakpoint IDE-də tutulur
- [ ] Mailpit (localhost:8025) test email-lərini göstərir
- [ ] `docker compose down && docker compose up -d` data itirmir (volume-lar)
- [ ] `docker build --target=production` fail-siz qurur
- [ ] `.dockerignore` `.env` ilə image-i təmiz saxlayır

## Ümumi Fayl Sayı

12 fayl əlavə/modifikasiya:
1. `.dockerignore`
2. `Dockerfile`
3. `docker-compose.yml`
4. `docker-compose.override.yml`
5. `docker/nginx/default.conf`
6. `docker/php/php.ini`
7. `docker/php/www.conf`
8. `docker/php/xdebug.ini`
9. `docker/entrypoint.sh`
10. `docker/scheduler-loop.sh`
11. `.env` (DB_HOST update)
12. `Makefile` (optional)

## Interview sualları

- **Q:** Mövcud Laravel layihəni dockerize etmək üçün ilk addım nədir?
  - `.dockerignore` yaz — əks halda `node_modules`, `vendor`, `.env`, `.git` image-ə düşür. Sonra multi-stage Dockerfile (base → dev → vendor → frontend → production).

- **Q:** Dev-də niyə bind mount, prod-da COPY?
  - Dev-də kod dəyişikliyi dərhal görünməlidir (bind mount). Prod-da image immutable olmalıdır (COPY) — CI-də bir dəfə qurulur, hər yerə eyni deploy olunur.

- **Q:** Host-da PHP yoxdursa, `composer require` necə?
  - `docker compose exec app composer require ...` — konteyner daxilindən. Host-a PHP/composer quraşdırmaq lazım deyil.

- **Q:** Eyni image həm web, həm worker, həm scheduler üçün?
  - Bəli — fərqli `CMD`. Web: `php-fpm`, worker: `artisan queue:work`, scheduler: `scheduler-loop.sh`. `CONTAINER_ROLE` env ilə entrypoint davranışı fərqlənir.

- **Q:** Xdebug production-da?
  - **Heç vaxt!** Dev-də `target: dev` stage-də var, prod-da `target: production` stage-də yoxdur. 10x performans azalması.

- **Q:** `DB_HOST=mysql` niyə `127.0.0.1` yox?
  - Docker Compose service name-ləri DNS ilə resolve edir. Konteyner daxilində `mysql` → mysql service-nin IP-si. `127.0.0.1` isə konteynerin özüdür.
