# Composer in Docker — Best Practices

## Nədir? (What is it?)

Composer Docker-də **ən çox səhv edilən** hissədir. Tipik problemlər:
- Hər kod dəyişikliyində `composer install` sıfırdan icra olunur (1-2 dəqiqə itir)
- Production image-də dev paketlər qalır (`phpunit`, `faker`, `ide-helper`) — ölçü artır
- Platform extension-lar yoxdur deyə `composer install` fail olur
- `composer.lock` istifadə edilmir — dev və prod fərqli versiyaları quraşdırır
- GitHub rate limit-i build-i dayandırır

Bu fayl bunların hamısının həllini verir — **multi-stage composer pattern**, cache, platform-check, private repo auth.

## Golden Rule: Composer Ayrı Stage-də

Composer-i final image-ə quraşdırmağa ehtiyac yoxdur. Ayrı build stage-də işlədin, yalnız `vendor/` qovluğunu son image-ə çəkin:

```dockerfile
# ============================================================
# Stage 1: Composer
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app

# ADDIM 1: Yalnız composer faylları — cache layer
COPY composer.json composer.lock ./

# ADDIM 2: Production dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ADDIM 3: App kodu (artıq vendor var, cache invalidate olmur)
COPY . .

# ADDIM 4: Autoloader regenerate (app-in class-larını map et)
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ============================================================
# Stage 2: Final PHP-FPM
# ============================================================
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Composer olmadan, sadəcə vendor qovluğunu kopyala
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
COPY . .
```

**Niyə bu pattern?**
- Final image-də Composer yoxdur — ~10 MB daha kiçik
- Final image-də Git, unzip kimi build asılılıqları yoxdur
- Security: production image-də `composer install` edə bilməzsən (təhlükəsizlik əlavəsi)

## Layer Caching — Ən Vacib Optimize

Docker build layer-ləri yuxarıdan aşağıya cache edir. Yuxarıda bir layer dəyişirsə, aşağıdakılar da yenidən qurulur. Composer qazancın açarı: **composer.json/lock-u ayrı kopyala**:

```dockerfile
# YANLIŞ — hər kod dəyişikliyində composer install yenidən işləyir
COPY . .
RUN composer install

# DÜZGÜN — yalnız composer faylları dəyişsə composer install işləyir
COPY composer.json composer.lock ./
RUN composer install --no-scripts
COPY . .
RUN composer dump-autoload
```

Kod dəyişikliyi 5-10 saniyədə build olur (vendor/ cache-dən gəlir), composer dəyişikliyi 1-2 dəqiqə.

## `--no-scripts` Niyə?

Laravel-də `composer install` default post-install script-ləri icra edir:
- `package:discover` (service provider-ları cache et)
- `@php artisan vendor:publish --tag=laravel-assets`

Problem: bu script-lər `.env`-ə ehtiyac duyur, build vaxtı env yoxdur, fail olurlar.

**Həll:** `--no-scripts` — script-ləri keç. Sonra build bitəndə `RUN php artisan package:discover` əlavə et.

```dockerfile
RUN composer install --no-dev --no-scripts --optimize-autoloader
COPY . .
RUN php artisan package:discover --ansi
```

## `--optimize-autoloader` vs `--classmap-authoritative`

| Flag | Nə edir |
|------|---------|
| `--optimize-autoloader` / `-o` | Autoloader-i PSR-4 scan əvəzinə classmap-a çevirir — 10x daha sürətli |
| `--classmap-authoritative` / `-a` | `-o` + əgər classmap-da yoxdursa, diskə baxma (daha da sürətli, amma dinamik class load-u əngəlləyir) |
| `--apcu-autoloader` | APCu cache-lə autoloader — hər request əvvəzinə bir dəfə load |

**Laravel production:** `--optimize-autoloader` adətən kifayətdir. `--classmap-authoritative` runtime-da class generate edirsə (Eloquent model-ləri IDE-helper vs) problem ola bilər.

## Platform Requirements

`composer.json`-da:
```json
{
  "require": {
    "php": "^8.3",
    "ext-gd": "*",
    "ext-intl": "*",
    "ext-pdo_mysql": "*"
  }
}
```

Composer build vaxtı bu extension-ları image-də axtarır və tapmırsa fail olur:
```
Problem 1
  - Root composer.json requires PHP extension ext-gd * but it is missing from your system.
```

### Həll 1: `--ignore-platform-reqs` (vendor stage-də)

Vendor stage-də Composer image-də php-gd yoxdur, amma final image-də var:
```dockerfile
FROM composer:2.7 AS vendor
# composer image-də php-gd yoxdur — ignore et
RUN composer install --no-dev --ignore-platform-reqs
```

Final image-də extension-lar var, real istifadə vaxtı problem olmur.

### Həll 2: Vendor stage-də də extension quraşdır

Daha təhlükəsizdir (real check olur):
```dockerfile
FROM php:8.3-cli-alpine AS vendor
RUN apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
    && apk add --no-cache libpng-dev libzip-dev icu-dev \
    && docker-php-ext-install gd intl zip \
    && apk del .build-deps

# Composer-i binary kimi çək
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts
```

Sənət vs sadəlik: böyük layihələrdə həll 2 tövsiyə olunur.

### `composer check-platform-reqs`

Build bitəndən sonra yoxla:
```dockerfile
RUN composer check-platform-reqs --no-dev
```

## GitHub Rate Limit

Composer public repo-dan çox repo çəkəndə GitHub API rate limit (60/saat unauth) işə düşür:
```
Failed to execute git clone ... authentication required
```

### Həll 1: `COMPOSER_AUTH` secret

```bash
# CI/CD-də
docker build \
  --secret id=composer_auth,src=$HOME/.composer/auth.json \
  -t myapp .
```

Dockerfile-da:
```dockerfile
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json \
    composer install --no-dev
```

`auth.json` formatı:
```json
{
    "github-oauth": {
        "github.com": "ghp_XXXXXXXXXXXX"
    }
}
```

### Həll 2: Environment variable

```dockerfile
ARG GITHUB_TOKEN
ENV COMPOSER_AUTH='{"github-oauth": {"github.com": "'$GITHUB_TOKEN'"}}'
RUN composer install
```

**DİQQƏT:** `ARG` və `ENV` image history-də qalır. `docker history myapp` ilə görünür. Secret mount tövsiyə olunur.

## Private Packages (Private Packagist, Satis, Composer Satis)

### `composer config repositories`
```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://packagist.mycompany.com"
    }
  ]
}
```

Auth:
```json
{
    "http-basic": {
        "packagist.mycompany.com": {
            "username": "ci-bot",
            "password": "xxxxx"
        }
    }
}
```

Build-də:
```dockerfile
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json \
    composer install --no-dev
```

## BuildKit Cache Mount — 3-5x Sürətli

Composer öz cache-ini `~/.composer/cache`-də saxlayır. Hər build yenidən download olur — uzun sürür. BuildKit cache mount həmin cache-i layihələr arasında paylaşır:

```dockerfile
# syntax=docker/dockerfile:1.7

FROM composer:2.7 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_HOME=/tmp/composer-cache \
    composer install --no-dev --no-scripts --optimize-autoloader
```

Build vaxtı test:
- Cache-siz: 60-90 saniyə
- Cache ilə: 10-20 saniyə

Şərt: BuildKit (`DOCKER_BUILDKIT=1`) — Docker 23+ default. CI-də:
```yaml
# GitHub Actions
- uses: docker/setup-buildx-action@v3
- uses: docker/build-push-action@v5
  with:
    cache-from: type=gha
    cache-to: type=gha,mode=max
```

## `composer.lock` — Vacib Qaydalar

- **Həmişə commit et** `composer.lock`
- **Dev-də** `composer require package/name` → lock yenilənir, test et, commit et
- **Prod-da** yalnız `composer install` (lock-a sadiq qal), heç vaxt `composer update`
- **CI-də** `composer install --no-dev` — lock-a sadiq

Əgər `composer install` fail edirsə "Your lock file does not contain a compatible set of packages", bu o deməkdir ki:
- Biri `composer update` etdi, amma lock-u commit etmədi
- PHP versiyası dəyişdi (lock köhnə PHP üçündür)
- Base image yeniləndi

## `composer install` vs `composer update`

| Komand | Nə edir |
|--------|---------|
| `composer install` | `composer.lock`-dakı dəqiq versiyaları quraşdırır (deterministic) |
| `composer update` | Yeni versiyaları axtarır, lock-u yeniləyir |
| `composer update vendor/package` | Yalnız bir paketi yenilə |

**Prod Docker build-də yalnız `install`**. Update yalnız dev maşında + commit.

## Dev vs Prod Composer

### Development Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine AS dev

# ...

# Dev-də composer mövcud olsun, dev paketlər də
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts

COPY . .
RUN composer dump-autoload --optimize
```

### Production Dockerfile

```dockerfile
FROM composer:2.7 AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

FROM php:8.3-fpm-alpine AS production
COPY --from=vendor /app/vendor ./vendor
# composer binary-si yoxdur!
```

Multi-stage target istifadə:
```bash
# Dev üçün
docker build --target=dev -t myapp:dev .

# Prod üçün
docker build --target=production -t myapp:prod .
```

## Security — `composer audit`

Composer 2.4+ `composer audit` ilə CVE yoxlayır:
```dockerfile
RUN composer install --no-dev \
    && composer audit --no-interaction --format=table
```

Build fail edir əgər vulnerability varsa. CI-də istifadə et.

## Composer Scripts Build-də

`composer.json`-dakı `scripts` bölməsi:
```json
{
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ]
    }
}
```

Problem: `package:discover` `.env` və DB əlaqəsi istəyə bilər. Build vaxtı `.env` yoxdur.

### Həll 1: `--no-scripts` build-də

```dockerfile
RUN composer install --no-scripts
COPY . .
RUN php artisan package:discover --ansi    # Manually run
```

### Həll 2: Build-time `.env.production` stub

```dockerfile
COPY .env.build .env    # Sadəcə APP_KEY, heç bir DB
RUN composer install
RUN rm .env
```

`.env.build` minimal:
```
APP_KEY=base64:fake-key-for-build-only
APP_ENV=production
```

## Tipik Səhvlər (Gotchas)

### 1. `COPY . .` sonra `composer install`

Hər kod dəyişikliyində vendor yenidən qurulur.

**Həll:** `composer.json/lock`-u əvvəl kopyala, `install`, sonra `COPY . .`

### 2. Vendor volume-a mount olunur, amma image-də də var

```yaml
volumes:
  - .:/var/www/html                  # Host-dan vendor-u da mount edir!
```

Host-da `vendor/` yoxdursa və ya köhnədirsə — container fail.

**Həll:** Anonymous volume ilə vendor-u image-dəki versiyasını saxla:
```yaml
volumes:
  - .:/var/www/html
  - /var/www/html/vendor              # image-dəki saxla
```

### 3. `--no-dev` unutmaq

Production image-də 30-50 MB dev paketlər qalır.

**Həll:** Production stage-də həmişə `--no-dev`.

### 4. `composer install` as root

`composer` image-də default user root-dur. Warning verir:
```
Do not run Composer as root/super user!
```

Final image-də `USER www-data` olsa belə vendor root-owned qala bilər.

**Həll:**
```dockerfile
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
```

### 5. `COMPOSER_MEMORY_LIMIT`

Böyük `composer update`-lər "Allowed memory size exhausted" verir.

**Həll:**
```dockerfile
ENV COMPOSER_MEMORY_LIMIT=-1
```

### 6. `COMPOSER_PROCESS_TIMEOUT`

Private repo-dan uzaq clone timeout:
```dockerfile
ENV COMPOSER_PROCESS_TIMEOUT=600
```

### 7. `composer.json` və `composer.lock` sinxron deyil

Build fail: "composer.lock is out of sync with composer.json".

**Səbəb:** `composer.json`-da yeni paket əlavə olundu, lock yenilənmədi.

**Həll:** Lokal-da `composer update package/name`, lock-u commit et.

## Interview sualları

- **Q:** Composer-i Docker image-ində niyə multi-stage istifadə edirsiniz?
  - Final image-də composer binary-si və git/build asılılıqları lazım deyil — ~10 MB kiçik. Təhlükəsizlik: production-da yeni paket install olunmasın. Sürət: vendor stage cache-lənir.

- **Q:** Layer caching-i composer üçün necə optimize edirsiniz?
  - `composer.json` və `composer.lock`-u əvvəl kopyala, `install` et, sonra bütün kodu kopyala. Kod dəyişikliyi vendor cache-i invalidate etmir.

- **Q:** `--no-scripts` nə deməkdir?
  - Post-install Laravel script-lərini keç (`package:discover`, `vendor:publish`). Bu script-lər `.env` istəyir — build vaxtı yoxdur. Manually sonra run et.

- **Q:** `--optimize-autoloader` və `--classmap-authoritative` fərqi?
  - `-o`: autoloader-i classmap-a çevirir (10x sürətli). `-a`: o + classmap-da olmayan class-ı axtarmır (daha sürətli, dinamik class-da problem).

- **Q:** Private packagist-dən auth-u necə verirsiniz build-də?
  - BuildKit `--mount=type=secret` ilə `auth.json`-ı mount et. Image history-də qalmır. `ARG GITHUB_TOKEN` istifadə etmə — təhlükəsizdir.

- **Q:** BuildKit cache mount composer üçün?
  - `RUN --mount=type=cache,target=/tmp/composer-cache COMPOSER_HOME=/tmp/composer-cache composer install` — 3-5x daha sürətli build.
