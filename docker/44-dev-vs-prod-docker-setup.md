# Dev vs Prod Docker Setup

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Development və production Docker mühitləri **fərqli tələblərə** malikdir:

| Aspect | Development | Production |
|--------|-------------|------------|
| Kod | Bind mount (canlı dəyişiklik) | COPY (image-ə daxil) |
| Composer | `--dev` paketlər daxildir | `--no-dev`, optimize autoloader |
| Xdebug | Aktiv | **Deaktiv** (10x yavaşdır) |
| OpCache `validate_timestamps` | `1` (dəyişiklik görünsün) | `0` (sürətli) |
| Error display | `On` | `Off` |
| Volume-lər | Persistent, bind mount | Volume minimal, stateless |
| Image | Böyük (debug tool-lar) | Minimal |
| `APP_DEBUG` | `true` | `false` |

Bu fayl dev və prod arasında **sadə keçid** yaratmağın ən yaxşı üsullarını verir: `docker-compose.override.yml`, Compose profiles, multi-stage target.

## Strategiya 1: `docker-compose.override.yml` (Ən Geniş Yayılan)

Docker Compose avtomatik olaraq iki fayl oxuyur:
1. `docker-compose.yml` (əsas, hər yerdə)
2. `docker-compose.override.yml` (override, adətən dev)

### `docker-compose.yml` — Minimal Prod-Ready

```yaml
services:
  app:
    build:
      context: .
      target: production
    image: myapp:${APP_VERSION:-latest}
    restart: unless-stopped
    environment:
      - APP_ENV=${APP_ENV:-production}
      - APP_DEBUG=${APP_DEBUG:-false}
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - app-net
  
  nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app
    networks:
      - app-net
  
  mysql:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app-net
  
  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - redis-data:/data
    networks:
      - app-net

volumes:
  mysql-data:
  redis-data:

networks:
  app-net:
```

### `docker-compose.override.yml` — Dev Üstünə Gəlir

```yaml
# Bu fayl avtomatik oxunur — `docker compose up` etdikdə
services:
  app:
    build:
      target: dev            # Multi-stage dev target
    volumes:
      - .:/var/www/html      # Bind mount (canlı kod)
      - /var/www/html/vendor # Anonymous volume — vendor host-dan gəlməsin
      - /var/www/html/node_modules
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - XDEBUG_MODE=debug
      - XDEBUG_CONFIG=client_host=host.docker.internal client_port=9003
    extra_hosts:
      - "host.docker.internal:host-gateway"
  
  nginx:
    ports:
      - "8080:80"            # Dev port 8080
    volumes:
      - ./public:/var/www/html/public:ro
  
  mysql:
    ports:
      - "3306:3306"          # Host-dan MySQL Workbench-lə qoş
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: myapp
      MYSQL_USER: dev
      MYSQL_PASSWORD: secret
  
  mailpit:                    # Dev-only service
    image: axllent/mailpit
    ports:
      - "1025:1025"          # SMTP
      - "8025:8025"          # Web UI
    networks:
      - app-net
```

### Necə İstifadə

```bash
# Dev (override avtomatik tətbiq olunur)
docker compose up -d

# Prod (override-ı ignore et)
docker compose -f docker-compose.yml up -d

# Və ya prod override istifadə et
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## Strategiya 2: Multi-Stage Dockerfile Target

Tək Dockerfile, fərqli stage-lər:

```dockerfile
# ============================================================
# Base — ümumi
# ============================================================
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache tini icu-dev libzip-dev libpng-dev \
    && docker-php-ext-install bcmath gd intl opcache pcntl pdo_mysql zip \
    && pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www/html

# ============================================================
# Dev — Xdebug əlavə, composer dev paketlər
# ============================================================
FROM base AS dev

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]

# ============================================================
# Vendor — production asılılıqlar
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# ============================================================
# Production — minimal
# ============================================================
FROM base AS production

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .

RUN php artisan view:cache && php artisan route:cache

USER www-data

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

### `docker/php/xdebug.ini`

```ini
zend_extension=xdebug.so
xdebug.mode=develop,debug
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.start_with_request=yes
xdebug.discover_client_host=false
xdebug.idekey=VSCODE
xdebug.log=/tmp/xdebug.log
```

### `docker/php/php-dev.ini`

```ini
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0
memory_limit = 512M
```

### `docker/php/php-prod.ini`

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /proc/self/fd/2
opcache.validate_timestamps = 0
memory_limit = 256M
```

## Strategiya 3: Compose Profiles (Docker Compose 2.2+)

Profiles ilə hansı service-lərin qalxacağını idarə edirik:

```yaml
services:
  app:
    image: myapp
    # Profil yoxdur — həmişə qalxır
  
  mailpit:
    image: axllent/mailpit
    profiles: ["dev"]         # Yalnız dev profilində
    ports: ["8025:8025"]
  
  xdebug-proxy:
    image: devilbox/xdebug-proxy
    profiles: ["dev", "debug"]
  
  phpmyadmin:
    image: phpmyadmin
    profiles: ["dev"]
    ports: ["8081:80"]
  
  traefik:
    image: traefik
    profiles: ["prod"]
```

İstifadə:
```bash
# Yalnız core
docker compose up

# Dev + core
docker compose --profile dev up

# Debug profil
docker compose --profile dev --profile debug up
```

## Xdebug — Yalnız Dev-də

### Problem: Xdebug 10x Yavaşlatır

Production-da heç vaxt:
```dockerfile
# PRODUCTION — Xdebug YOX
# Heç bir `pecl install xdebug`
```

Development-də yalnız lazım olanda:
```dockerfile
FROM base AS dev
RUN pecl install xdebug && docker-php-ext-enable xdebug
```

### `xdebug.mode` Dəyərləri

| Mode | İstifadə |
|------|----------|
| `off` | Deaktiv (yalnız binary yüklənir) |
| `develop` | Enhanced error messages, `var_dump()` |
| `debug` | Step debugger (IDE-ə qoşul) |
| `profile` | Cachegrind profile fayllarını yaz |
| `trace` | Bütün function call-ları log et |
| `coverage` | PHPUnit code coverage |

Birləşdir: `xdebug.mode=develop,debug`

### IDE Setup

**VS Code** (`.vscode/launch.json`):
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

**PHPStorm:**
- Settings → PHP → Debug → Xdebug: port 9003
- DBGp Proxy: IDE key `PHPSTORM`
- Servers → path mapping: project path → `/var/www/html`

### `host.docker.internal`

Xdebug konteynerdən host IDE-yə qoşulmalıdır. Docker Desktop-da avtomatik işləyir. Linux-da:
```yaml
services:
  app:
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

### Xdebug On/Off Toggle (Alias)

```bash
# docker/toggle-xdebug.sh
#!/bin/sh
STATE=$1
if [ "$STATE" = "on" ]; then
    docker compose exec app sh -c 'echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/xdebug.ini'
else
    docker compose exec app sh -c 'echo "" > /usr/local/etc/php/conf.d/xdebug.ini'
fi
docker compose restart app
```

İstifadə:
```bash
./toggle-xdebug.sh on   # Debug lazımdır
./toggle-xdebug.sh off  # Performans testləri
```

## Dev Experience — Hot Reload

### Vite HMR (Laravel 9+)

```yaml
# docker-compose.override.yml
services:
  vite:
    image: node:20-alpine
    working_dir: /app
    command: ["npm", "run", "dev"]
    ports:
      - "5173:5173"
    volumes:
      - .:/app
      - /app/node_modules
    environment:
      - VITE_HOST=0.0.0.0
```

`vite.config.js`:
```js
export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
    },
});
```

## Secret Management — Dev vs Prod

### Dev: `.env`-də plain text

```
# .env (GİT-ə DÜŞMƏSİN)
DB_PASSWORD=secret
STRIPE_KEY=sk_test_xxx
```

### Prod: Runtime env-dən (Docker Secrets, K8s Secret, Vault)

```yaml
# docker-compose.yml (swarm mode)
services:
  app:
    secrets:
      - db_password
      - stripe_key

secrets:
  db_password:
    external: true
  stripe_key:
    external: true
```

```bash
echo "real-prod-password" | docker secret create db_password -
```

Konteynerdə `/run/secrets/db_password` faylı yaranır. Laravel-də:
```php
'password' => file_get_contents('/run/secrets/db_password'),
```

**Kubernetes:** Secret resource + `envFrom: secretRef` (17-ci fayl).

## Production Compose Override Nümunəsi

```yaml
# docker-compose.prod.yml
services:
  app:
    image: ghcr.io/mycompany/myapp:${APP_VERSION}
    # build yoxdur — CI-də qurulmuş image istifadə olunur
    pull_policy: always
    restart: unless-stopped
    deploy:
      replicas: 3
      update_config:
        parallelism: 1
        order: start-first
      resources:
        limits:
          memory: 1G
          cpus: '1'
    logging:
      driver: loki
      options:
        loki-url: "http://loki:3100/loki/api/v1/push"
  
  nginx:
    image: ghcr.io/mycompany/nginx:${APP_VERSION}
    ports:
      - "443:443"
    volumes:
      - ./certs:/etc/nginx/certs:ro
    deploy:
      replicas: 2
  
  # Dev service-lər yoxdur (mailpit, phpmyadmin)
```

Deploy:
```bash
docker stack deploy -c docker-compose.yml -c docker-compose.prod.yml myapp
```

## Makefile — Dev Workflow

Əllə komand yazmamaq üçün:

```makefile
# Makefile
.PHONY: up down build logs shell migrate test fresh

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

logs:
	docker compose logs -f --tail=100

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test

prod-build:
	docker build --target=production -t myapp:prod .

prod-test:
	docker run --rm myapp:prod php artisan --version
```

İstifadə:
```bash
make up
make migrate
make test
```

## Tipik Səhvlər (Gotchas)

### 1. `.env` prod image-də

`COPY . .` ilə `.env` image-ə düşür — credential leak.

**Həll:** `.dockerignore`-da `.env`. Runtime-da env variables.

### 2. Dev-də volume mount amma vendor boş

```yaml
volumes:
  - .:/var/www/html
```

Host-da `vendor/` yoxdur (gitignore) — konteyner fail.

**Həll:** Anonymous volume:
```yaml
volumes:
  - .:/var/www/html
  - /var/www/html/vendor              # image-dəki saxla
  - /var/www/html/node_modules
```

### 3. Dev və prod image fərqlidir

Dev-də işləyir, prod-da fail — "prod-da niyə fərqlidir?" problemi.

**Həll:** Eyni Dockerfile, yalnız target dəyişir. CI-də dev image-lə də smoke test.

### 4. Xdebug prod-da

İmage-də Xdebug quraşdırılıb amma deaktiv — amma zend_extension yüklənir, 5-10% overhead.

**Həll:** Production stage-də ümumiyyətlə `pecl install xdebug` etmə.

### 5. OpCache `validate_timestamps=0` dev-də

Dev-də kod dəyişir, cache reset olmur — developer "niyə dəyişmir?".

**Həll:** Dev-də `validate_timestamps=1`, `revalidate_freq=0`.

### 6. `APP_DEBUG=true` production-da

Stack trace, env variables response-da görünür — security leak.

**Həll:** Compose-də `environment: APP_DEBUG: "${APP_DEBUG:-false}"`, default false.

### 7. Prod compose-də `build:` olması

Prod server-də source code yoxdur — build fail.

**Həll:** Prod compose-də `image:` istifadə et, `build:` dev-də.

## Interview sualları

- **Q:** Dev və prod arasında nə dəyişir?
  - Kod (bind mount vs COPY), composer (`--dev` vs `--no-dev`), Xdebug (var/yox), OpCache (`validate_timestamps`), `APP_DEBUG`, error display, image ölçüsü.

- **Q:** `docker-compose.override.yml` necə işləyir?
  - Avtomatik `docker-compose.yml` üstünə merge olur. Prod-da `-f docker-compose.yml` ilə yalnız əsas fayl istifadə olunur.

- **Q:** Compose profiles nə üçün?
  - Service-ləri condition-lı qaldırmaq. `mailpit`, `phpmyadmin` yalnız `--profile dev` ilə qalxır.

- **Q:** Xdebug production-da ola bilərmi?
  - **Heç vaxt!** 10x performance azalması. Image-ə quraşdırılmamalıdır.

- **Q:** `APP_KEY`-i dev və prod-da necə fərqləndirirsiz?
  - Dev-də `.env`-də (hər developer eyni key paylaşa bilər), prod-da Docker secret və ya K8s Secret. **Prod APP_KEY sabitdir** — dəyişsə bütün encrypted session-lar yanır.

- **Q:** Dev container-də vendor host-dan niyə mount olunmur?
  - Host-da composer versiyası fərqli ola bilər, ya da yox. Anonymous volume ilə image-dəki vendor saxlanır — deterministic.


## Əlaqəli Mövzular

- [docker-compose.md](05-docker-compose.md) — Docker Compose əsasları
- [docker-env-secrets-laravel.md](46-docker-env-secrets-laravel.md) — Env idarəsi
- [testing-php-in-docker.md](47-testing-php-in-docker.md) — Test mühiti
- [laravel-sail-deep-dive.md](27-laravel-sail-deep-dive.md) — Laravel Sail dev setup
