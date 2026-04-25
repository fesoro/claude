# Docker və Dockerfile Anti-Patterns (PHP/Laravel)

> **Səviyyə (Level):** ⭐⭐ Middle
> **Oxu müddəti:** ~15-20 dəqiqə
> **Kateqoriya:** Best Practices & Troubleshooting

## Nədir? (What is it?)

Bu fayl — PHP/Laravel Dockerfile-larında ən çox təkrarlanan səhvlərin kataloqudur. Hər anti-pattern üçün:
1. **YANLIŞ** nümunə (real kod)
2. **NİYƏ** pisdir (konkret nəticə)
3. **DÜZGÜN** həll (real kod)

Bu səhvlər "build işləyir deyə" illərlə prod-da qalır — ta ki incident baş verənə, ya da image ölçüsü 2 GB olana qədər. Aşağıdakı 18 anti-pattern senior müsahibələrdə tez-tez soruşulur.

## 1. `COPY . /var/www` əvvəl `composer install`

### YANLIŞ

```dockerfile
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

COPY . .                            # <-- əvvəl bütün kod
RUN composer install --no-dev
```

### NİYƏ

Docker layer cache yuxarıdan aşağı işləyir. `COPY . .` layer-i hər **bir** kod dəyişikliyində invalidate olur. Nəticə: `RUN composer install` də yenidən işləyir. 80 MB vendor, 2 dəqiqə build vaxtı — hər commit-də.

### DÜZGÜN

```dockerfile
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Əvvəl yalnız composer faylları
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Sonra bütün kod
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative
```

Kod dəyişikliyi → vendor cache-dən gəlir → build 10 saniyə.

## 2. `apt-get update && install` ayrı RUN-larda

### YANLIŞ

```dockerfile
FROM php:8.3-fpm
RUN apt-get update
RUN apt-get install -y libpng-dev libzip-dev
```

### NİYƏ

Hər `RUN` ayrı layer-dir. `apt-get update` layer-i cache-lənir. Növbəti build-də əgər `update` cache-dən gəlirsə, package metadata köhnədir — `install` outdated versiya götürə bilər və ya fail edə bilər ("package not found"). Buna **cache busting** problemi deyilir.

### DÜZGÜN

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
      libpng-dev \
      libzip-dev \
      libicu-dev \
      libonig-dev \
    && rm -rf /var/lib/apt/lists/*
```

Tək RUN → tək layer → update və install atomic. `rm -rf /var/lib/apt/lists/*` apt cache-i təmizləyir (50 MB azaldır).

## 3. `--no-install-recommends` yoxdur

### YANLIŞ

```dockerfile
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev
```

### NİYƏ

Debian / Ubuntu default olaraq "recommended" paketləri də quraşdırır. Bu "tövsiyə" paketlər tez-tez lazımsızdır: GUI library-lər, dəqiq alətlər. `git` yüklədikdə `perl-modules`, `manpages`, `python3` də gəlir — 200 MB əlavə.

### DÜZGÜN

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*
```

Yalnız dependencies-in özünü quraşdırır. Image ölçüsündə 100-300 MB fərq.

## 4. Base image-i pin etməmək

### YANLIŞ

```dockerfile
FROM php:8.3-fpm
```

### NİYƏ

`php:8.3-fpm` mutable tag-dir. Bu gün v8.3.12, sabah v8.3.13. "Mənim maşımda işləyirdi" problemi. Həmçinin:
- Debian / Alpine fərqi aydın deyil
- Minor security patch-lər sərhədinizə gözəl çıxa bilər

### DÜZGÜN

```dockerfile
FROM php:8.3.12-fpm-alpine3.19
```

Tam tag: major.minor.patch + OS + OS version. Reproducible build. Yenilənmə manual + test edilərək.

Daha dəqiq: digest pin (production-larda):
```dockerfile
FROM php:8.3.12-fpm-alpine3.19@sha256:abc123...
```

## 5. Root istifadəçi olaraq işlətmək

### YANLIŞ

```dockerfile
FROM php:8.3-fpm-alpine
COPY . /var/www/html
CMD ["php-fpm"]
```

Default istifadəçi root-dur.

### NİYƏ

- **Security**: container escape olsa, host-da root access
- PHP-FPM default-da `www-data` altında işləyir, amma Dockerfile-da USER təyin olunmasa master process root altındadır
- Prod image-də `www-data` uid/gid host-unkına uyğun olmalıdır (42 fayla bax)

### DÜZGÜN

```dockerfile
FROM php:8.3-fpm-alpine

# www-data artıq var Alpine image-də
COPY --chown=www-data:www-data . /var/www/html

USER www-data
CMD ["php-fpm"]
```

Əgər root-a ehtiyac varsa (package install, permission fix), `USER root` → işini gör → `USER www-data` qayıt.

## 6. `.env` image-ə copy etmək

### YANLIŞ

```dockerfile
COPY . /var/www/html
# .env də image-ə düşdü
```

`.dockerignore`-da `.env` yoxdur. Nəticə: production secrets image-də qalır.

### NİYƏ

Image registry-yə push olanda `.env` də push olur. Public registry → bütün dünya görür. Private registry-də işçi access olsa — hər şey görünür.

### DÜZGÜN

`.dockerignore`-da:
```gitignore
.env
.env.*
!.env.example
```

Runtime-də env variable-ları inject et:
```yaml
# docker-compose.yml
services:
  app:
    image: myapp:v1
    env_file:
      - .env.production    # host-da, image-də yox
```

K8s:
```yaml
envFrom:
  - secretRef:
      name: app-secrets
```

## 7. `composer install` prod-da `--no-dev` olmadan

### YANLIŞ

```dockerfile
RUN composer install
```

### NİYƏ

Production image-də 30-50 MB dev paketlər qalır: `phpunit`, `faker`, `ide-helper`, `telescope`, `debugbar`. Həm ölçü, həm security (dev paketlərdə bəzən test endpoint-lər açılır).

### DÜZGÜN

```dockerfile
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader
```

`--no-dev` — `require-dev` paketlərini keçər. `--optimize-autoloader` — autoloader-i classmap-a çevirir (10x sürətli).

## 8. `php artisan config:cache` runtime-da

### YANLIŞ

```dockerfile
COPY . /var/www/html
CMD ["sh", "-c", "php artisan config:cache && php-fpm"]
```

Və ya entrypoint-də:
```sh
php artisan config:cache
php artisan route:cache
exec "$@"
```

### NİYƏ

Hər container başlanğıcında config:cache işləyir — 1-3 saniyə. Horizontal scale-də (10 pod restart) hər pod öz-özünə cache yaradır. Build vaxtı etmək olar (bir dəfə).

### DÜZGÜN

Build-də:
```dockerfile
# Build-time env ilə cache
COPY .env.production .env
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# .env-i sil — runtime-də secret-lar env var-dan gələcək
RUN rm .env
```

Problem: `config:cache` env-ə bağlıdır. Çox container-lərdə hər pod fərqli env ilə start edir (dev vs staging vs prod).

**Better solution:** env-ə bağlı olmayan config-ləri build-də cache et, env-ə bağlı olanları runtime-də:
- Build-də: view:cache, route:cache
- Runtime-də: config:cache (əgər env dinamikdirsə)

Və ya `CACHE_CONFIG_DRIVER=array` — prod-da env-dən oxu, cache yox.

## 9. Hər environment üçün ayrı image

### YANLIŞ

```bash
docker build -t myapp:dev .
docker build -t myapp:staging --build-arg APP_ENV=staging .
docker build -t myapp:prod --build-arg APP_ENV=production .
```

Üç fərqli image build edilir.

### NİYƏ

**Twelve-Factor App** prinsipi: eyni image hər mühitdə. Fərq env variable-larla idarə olunur. Fərqli build-lər → dev-də işləyir, prod-da işləmir (çünki gerçəkdən fərqli binaries).

### DÜZGÜN

Bir image:
```bash
docker build -t myapp:v1.2.3-abc1234 .
```

Hər mühitdə env fərqli:
```yaml
# dev
environment:
  APP_ENV: local
  APP_DEBUG: "true"

# staging
environment:
  APP_ENV: staging
  APP_DEBUG: "false"

# prod
environment:
  APP_ENV: production
  APP_DEBUG: "false"
```

Eyni image dev-də test olundu → staging-də eyni image → prod-da eyni image.

## 10. `tini` / `dumb-init` yoxdur (PID 1 problemi)

### YANLIŞ

```dockerfile
CMD ["php", "artisan", "queue:work"]
```

### NİYƏ

Linux-da PID 1 xüsusi prosessdir. Signal handling-i fərqlidir:
- SIGTERM default ignore olunur (əgər handler yoxdursa)
- Zombie process (child terminated amma reaped olmayıb) — PID 1 reap etməlidir

PHP — PID 1 olanda SIGTERM-ə cavab vermir, `docker stop` 10 saniyə gözləyir, sonra SIGKILL. Queue worker-lər yarımçıq job-ları zorla tərk edir.

### DÜZGÜN

`tini` istifadə et:

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache tini

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php", "artisan", "queue:work"]
```

Və ya Docker run-da `--init`:
```bash
docker run --init myapp queue:work
```

Tini PID 1 olur — SIGTERM-i child-a ötürür, zombie-ləri reap edir. Queue worker SIGTERM alır, cari job bitirir, graceful çıxır.

## 11. Log-ları container filesystem-ə yazmaq

### YANLIŞ

```php
// config/logging.php
'channels' => [
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
    ],
],
```

Log `storage/logs/laravel.log`-ə yazılır — container filesystem-ə.

### NİYƏ

- Container silinir → log-lar itir
- Disk dolur → container crash (storage read-only filesystem effect)
- `docker logs` heç bir şey göstərmir
- Aggregation (CloudWatch, Datadog) log-a çata bilmir

### DÜZGÜN

Log-u stdout/stderr-ə yaz:

```php
// config/logging.php
'default' => env('LOG_CHANNEL', 'stderr'),

'channels' => [
    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => StreamHandler::class,
        'with' => [
            'stream' => 'php://stderr',
        ],
        'formatter' => JsonFormatter::class,  // structured JSON
    ],
],
```

Compose:
```yaml
services:
  app:
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"
```

`docker logs myapp` işləyir. Log aggregator container stdout-u oxuyur.

## 12. Production-da bind mount

### YANLIŞ

```yaml
# prod docker-compose.yml
services:
  app:
    image: myapp:v1
    volumes:
      - ./app:/var/www/html/app         # <-- bind mount
      - ./config:/var/www/html/config
```

### NİYƏ

- Host filesystem image-in üzərinə yazır — image-dəki kod ignore olur
- Deployment atomicity itir (host-da fayl yarımçıq kopy olduqda container köhnə və yeni qarışıq işlədir)
- Rollback çətindir — fayllar host-dadır
- K8s kimi platformalarda bind mount yoxdur (ConfigMap / Secret / PV var)

### DÜZGÜN

Bind mount yalnız **dev**-də. Production-da:

```yaml
services:
  app:
    image: myapp:v1.2.3-abc1234     # kod image-dədir
    volumes:
      - storage:/var/www/html/storage    # named volume — Docker idarə edir

volumes:
  storage:
```

Kod image-də COPY edilib, deployment atomic.

## 13. Cron-u `docker exec` ilə

### YANLIŞ

Host crontab:
```
* * * * * docker exec myapp-container php artisan schedule:run
```

### NİYƏ

- Container restart olsa — cron miss edir
- Multi-node K8s-də host crontab yoxdur
- Manual, scale etmir
- Monitoring yoxdur

### DÜZGÜN

Ayrı scheduler container:

```yaml
services:
  app:
    image: myapp:v1

  scheduler:
    image: myapp:v1
    command: >
      sh -c "while true; do
        php artisan schedule:run;
        sleep 60;
      done"
    restart: unless-stopped
```

Və ya Kubernetes CronJob:
```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-schedule
spec:
  schedule: "* * * * *"
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: scheduler
            image: myapp:v1.2.3
            command: ["php", "artisan", "schedule:run"]
          restartPolicy: OnFailure
```

K8s native, observability, retry, parallel idarəsi var.

## 14. `chown -R` entrypoint-də hər restart-da

### YANLIŞ

```sh
#!/bin/sh
chown -R www-data:www-data /var/www/html
exec "$@"
```

### NİYƏ

`/var/www/html`-də 100,000 fayl var (vendor, node_modules, cache). `chown -R` 10-30 saniyə çəkir. Hər pod restart-da. Startup probe fail edir, K8s yenidən kill edir.

### DÜZGÜN

**Üsul 1:** yalnız dəyişəni `chown`:
```sh
#!/bin/sh
if [ "$(stat -c %u /var/www/html/storage)" != "$(id -u www-data)" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
fi
exec "$@"
```

**Üsul 2:** build-də bir dəfə:
```dockerfile
COPY --chown=www-data:www-data . /var/www/html
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
# Entrypoint chown etmir
```

**Üsul 3 (K8s):**
```yaml
securityContext:
  fsGroup: 82
  fsGroupChangePolicy: "OnRootMismatch"
```

## 15. OpCache prod-da aktiv deyil

### YANLIŞ

Default PHP image-də OpCache var, amma konfiqurasiya suboptimaldır:
```ini
opcache.enable=1
opcache.validate_timestamps=1   # <-- problem
```

### NİYƏ

`validate_timestamps=1` hər request-də bütün PHP fayllarını disk-də check edir — dəyişibmi? Dev-də yaxşıdır (kod dəyişsin), prod-da 30% performance itkisi.

### DÜZGÜN

Prod üçün `opcache.ini`:

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0       # prod: kodu yoxlamır
opcache.revalidate_freq=0
opcache.fast_shutdown=1
opcache.save_comments=1              # Laravel annotation-lar üçün
```

Dockerfile:
```dockerfile
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/
```

Dev üçün override:
```dockerfile
# Dev stage
COPY docker/php/opcache.dev.ini /usr/local/etc/php/conf.d/zz-opcache.ini
```

Dev `opcache.dev.ini`:
```ini
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

## 16. `-fpm` image + port 80

### YANLIŞ

```yaml
services:
  app:
    image: myapp:v1           # FROM php:8.3-fpm-alpine
    ports:
      - "80:80"                # <-- FPM-də 80 yoxdur
```

### NİYƏ

PHP-FPM `:9000`-də dinləyir (FastCGI protocol). HTTP protokolu deyil. Browser connect edə bilməz. Sənə nginx / apache lazımdır — FPM qabağında.

### DÜZGÜN

İki container:
```yaml
services:
  app:
    image: myapp:v1            # php:8.3-fpm-alpine
    expose:
      - "9000"                 # FPM port, yalnız internal

  nginx:
    image: nginx:1.25-alpine
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
```

`nginx.conf`:
```nginx
server {
    listen 80;
    root /var/www/html/public;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;       # <-- FPM-ə ötür
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Alternativ: `php:8.3-apache` (Apache + mod_php) — bir container, amma Apache tez-tez FPM-dən yavaş olur yüksək yüklərdə.

Və ya **FrankenPHP** (modern):
```dockerfile
FROM dunglas/frankenphp:1.2
# bir container, HTTP/2, HTTP/3 dəstəyi
```

## 17. `.dockerignore` yoxdur

### YANLIŞ

`.dockerignore` fayl mövcud deyil. Ya da çox məhduddur:
```gitignore
.git
```

### NİYƏ

Build context 400 MB göndərilir (vendor, node_modules, .git tarixçə, storage/logs). `.env` image-ə düşə bilər (secret leak). Build yavaşdır (30-60 saniyə yalnız context transfer). Detallı izah üçün `.dockerignore` faylına bax.

### DÜZGÜN

Tam `.dockerignore`:

```gitignore
.git
.gitignore
.github

vendor
node_modules

.env
.env.*
!.env.example

storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*

tests
phpunit.xml
.phpunit.cache

.idea
.vscode
.DS_Store

docker-compose.override.yml
docker-compose.dev.yml
```

Build kontekst-i 5 MB-a düşür, 20x sürətli.

## 18. Tək böyük layer

### YANLIŞ

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache git curl libpng-dev libzip-dev icu-dev autoconf gcc g++ make \
    && docker-php-ext-install gd intl zip pdo_mysql \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev \
    && php artisan config:cache \
    && php artisan route:cache \
    && chown -R www-data:www-data /var/www/html
COPY . .
```

### NİYƏ

Hər şey bir layer-dədir. Bir şey dəyişsə — bütün layer yenidən qurulur. Cache itir. Build 5 dəqiqə.

### DÜZGÜN

Layer-ləri məntiqə görə ayır — ən az dəyişəni üstdə, ən çox dəyişəni altda:

```dockerfile
FROM php:8.3-fpm-alpine

# 1. System dependencies — nadir dəyişir
RUN apk add --no-cache git curl libpng-dev libzip-dev icu-dev \
    && apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
    && docker-php-ext-install gd intl zip pdo_mysql \
    && apk del .build-deps

# 2. Composer binary — nadir dəyişir
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# 3. Composer dependencies — orta dəyişir
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# 4. App code — hər commit dəyişir
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --classmap-authoritative \
    && chmod -R 775 storage bootstrap/cache

# 5. Build-time cache — hər commit
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

USER www-data
CMD ["php-fpm"]
```

Kod dəyişikliyi → 4 və 5 yenidən qurulur, 1-3 cache-dən. 10 saniyə əvəzinə 2 dəqiqə.

## Bonus: Multi-arch Build

### YANLIŞ

```bash
docker build -t myapp:v1 .
```

x86_64 host-da build edir. ARM64 (M1/M2 Mac, Graviton) server-də çalışmır.

### DÜZGÜN

```bash
docker buildx create --use
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  --tag myapp:v1 \
  --push \
  .
```

Iki arxitektura üçün image — registry-də manifest list saxlanır, hər host öz arxitekturasını seçir.

## Best Practices Recap

1. **Layer caching üçün sıra:** system deps → composer.json → composer install → kod → app cache
2. **Multi-stage build:** vendor stage + frontend stage + production stage
3. **Base image pin:** `php:8.3.12-fpm-alpine3.19`, ya da digest
4. **`--no-install-recommends` + apt cache təmizlə**
5. **USER www-data, root deyil**
6. **`.env` heç vaxt image-ə copy etmə** (`.dockerignore`)
7. **`--no-dev --optimize-autoloader` composer-də prod**
8. **Artisan cache build-də, runtime-də yox**
9. **Bir image, hər environment fərqli env var**
10. **tini / `--init`** queue worker-lər üçün
11. **Log-lar stdout-a, fayl-a yox**
12. **Production-da bind mount yoxdur**
13. **Cron → CronJob / scheduler container**
14. **`chown -R` entrypoint-də yalnız ehtiyac varsa**
15. **OpCache prod-da `validate_timestamps=0`**
16. **FPM + Nginx iki container (və ya FrankenPHP)**
17. **`.dockerignore` həmişə var olsun**
18. **Layer-ləri məntiqə görə ayır**

## Tələlər (Gotchas)

### Composer binary-ni scratch-dan endirmək

```dockerfile
RUN curl -sS https://getcomposer.org/installer | php
```

Checksum yoxlanmır. Supply chain attack riski. `COPY --from=composer:2.7` istifadə et — rəsmi image.

### `ENV PATH` override

```dockerfile
ENV PATH="/custom/bin"    # <-- /usr/bin silindi!
```

Bütün default path itir. `ENV PATH="/custom/bin:$PATH"` düzgündür.

### `COPY --from=` external image-dən

```dockerfile
COPY --from=nginx:latest /etc/nginx/nginx.conf /etc/nginx/
```

Mutable tag — `latest` dəyişsə build fərqli. Pin et: `nginx:1.25.3-alpine`.

### Healthcheck yoxdur

```dockerfile
# HEALTHCHECK yoxdur — orchestrator bilmir container sağlamdır
```

Əlavə et:
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9000/health || exit 1
```

K8s-də liveness / readiness probe tərcih olunur, amma plain Docker-də HEALTHCHECK faydalıdır.

## Müsahibə Sualları

- **Q:** Dockerfile-da ən tez-tez rastlaşdığın anti-pattern?
  - `COPY . .` əvvəl `composer install`. Hər kod dəyişikliyi vendor cache-i invalidate edir, build 2 dəqiqə çəkir. Həll: `composer.json/lock`-u əvvəl kopyala, install et, sonra bütün kodu.

- **Q:** `apt-get update && install`-i niyə eyni RUN-da yazırsan?
  - Ayrı olarsa cache busting olur — `update` cache-dən gəlir, stale package index-lə `install` fail edir. Eyni RUN-da atomic olur.

- **Q:** Prod image-də niyə root istifadəçisi pisdir?
  - Container escape olarsa host-da root. Həmçinin `www-data` ilə işləmək file permission-ları host-unkı ilə uyğunlaşdırır.

- **Q:** `.env` image-ə düşməməsi üçün nə edirsən?
  - `.dockerignore`-da `.env` və `.env.*` exclude. Runtime-də env-i `env_file`, `environment`, ya K8s Secret ilə inject et. Build-time `.env` yalnız `.env.example` ola bilər.

- **Q:** `tini` nədir və niyə lazımdır?
  - PID 1 minimal init system. PHP default-da signal handler-i və zombie reaping-i yaxşı etmir. Tini PID 1 olanda SIGTERM-i child-a ötürür, graceful shutdown təmin edir. Queue worker-lər yarımçıq job-ları saxlayır.

- **Q:** Hər environment üçün fərqli image niyə antipattern?
  - 12-factor prinsipi: build bir dəfə, everywhere çalışsın. Fərqli build-lər fərqli binaries. Dev-də test olunan kod prod-da fərqli olur. Env-i runtime-də variable kimi inject et.

- **Q:** Production-da bind mount niyə pisdir?
  - Deployment atomic deyil — fayl kopyası yarımçıqsa container köhnə+yeni qarışıq işlədir. Rollback host fayl dəyişdirməyi tələb edir. K8s kimi platformalarda bind mount dəstəklənmir. Kod image-də COPY olmalıdır.

- **Q:** `-fpm` image-də niyə 80 port işləmir?
  - FPM `:9000`-də FastCGI protokolunda dinləyir, HTTP deyil. Browser HTTP istəyir. Nginx / Apache qabaqda lazımdır və ya FrankenPHP (HTTP native) istifadə et.

- **Q:** `chown -R` hər restart-da niyə pisdir?
  - Storage-də çox fayl varsa 10-30 saniyə çəkir. Startup probe fail edir. Yalnız ehtiyac varsa chown et (stat-la yoxla), build-time chown et, ya K8s `fsGroup` istifadə et.

- **Q:** Layer-ləri necə sıralamalı?
  - Cache hit dərəcəsi yüksək olanlar üstdə: 1) sistem dependencies 2) language runtime 3) composer deps 4) app code 5) app cache. Hər instruction bundan aşağıdakı hər şeyi invalidate edir. Ən çox dəyişən ən altda.
