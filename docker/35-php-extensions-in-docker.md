# PHP Extension-larını Docker-də Quraşdırmaq

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

PHP extension-lar PHP-nin **funksionallığını genişləndirən** C kitabxanalarıdır (`.so` faylları). Laravel işləmək üçün bir neçə extension-a ehtiyac duyur: `pdo_mysql` (DB), `mbstring` (UTF-8), `bcmath` (dəqiq pul hesabları), `gd` və ya `imagick` (şəkil), `redis` (cache/queue), `opcache` (performans).

Rəsmi `php:8.3-fpm` image-də bunların **hamısı yoxdur** — minimal gəlir. Extension-ları özün quraşdırmalısan.

Üç növ PHP extension var:

| Növ | Mənbə | Nümunə |
|-----|-------|--------|
| **Built-in (core)** | PHP source tree-də gəlir, amma build-time-da aktiv edilməlidir | `pdo_mysql`, `bcmath`, `gd`, `intl`, `opcache`, `zip`, `exif`, `pcntl` |
| **PECL** | [pecl.php.net](https://pecl.php.net)-dən yüklənir, C-də yazılıb, compile olmalıdır | `redis`, `xdebug`, `swoole`, `mongodb`, `pcov`, `imagick` |
| **Pure PHP** | Composer paketi — extension deyil, amma bəzən səhv salınır | `symfony/polyfill-*` |

PHP Docker image-də **xüsusi helper script-lər** var ki, extension quraşdırmaq asan olsun:
- `docker-php-ext-install <name>` — built-in extension-ı compile et və aktiv et
- `docker-php-ext-configure <name>` — compile flag-ları (məs. `gd` üçün `--with-jpeg`)
- `docker-php-ext-enable <name>` — artıq compile olunmuş extension-ı aktiv et
- `pecl install <name>` — PECL-dən yüklə və compile et

## Əsas Konseptlər

### 1. `docker-php-ext-install` vs `pecl install`

`docker-php-ext-install` **yalnız core extension-lar üçündür** — PHP source tarball-da olan (`/usr/src/php/ext/*`). `redis`, `xdebug`, `swoole` burada **yoxdur** — onlar PECL-dən gəlir.

```dockerfile
# Core — PHP source-da hazır
RUN docker-php-ext-install pdo_mysql bcmath intl opcache

# PECL — kənar mənbə
RUN pecl install redis \
    && docker-php-ext-enable redis
```

`pecl install` compile edir amma **aktiv etmir** — `docker-php-ext-enable` ayrıca çağırılmalıdır. Əks halda `php -m`-də görünməz.

### 2. Sistem Kitabxanaları (System Deps)

Hər extension bir C kitabxanasına bağlıdır. Onlar OS paket manager-dən (`apt`, `apk`) gəlir:

| PHP Extension | Debian/Ubuntu Paket | Alpine Paket |
|---------------|---------------------|--------------|
| `gd` (jpeg, png, webp, freetype) | `libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev` | `libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev` |
| `intl` | `libicu-dev` | `icu-dev` |
| `zip` | `libzip-dev` | `libzip-dev` |
| `pdo_pgsql`, `pgsql` | `libpq-dev` | `postgresql-dev` |
| `pdo_mysql` | (gəlir) | (gəlir) |
| `imagick` (PECL) | `libmagickwand-dev` | `imagemagick-dev` |
| `exif` | (yox) | (yox) |
| `mbstring` | `libonig-dev` (PHP 7.4+-də artıq yox) | `oniguruma-dev` |
| `soap` | `libxml2-dev` | `libxml2-dev` |
| `ldap` | `libldap2-dev` | `openldap-dev` |
| `bz2` | `libbz2-dev` | `bzip2-dev` |

Əgər lib-ni quraşdırmasan:
```
configure: error: freetype-config not found.
```

### 3. `docker-php-ext-configure` — Compile Flag-ları

Bəzi extension-lar əlavə flag istəyir. Ən məşhur nümunə `gd`:

```dockerfile
# GD — JPEG + WebP + FreeType dəstəyi ilə
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) gd
```

Əgər `--with-jpeg` yoxdursa, JPEG fayl açanda fail olur runtime-da:
```
Imagick::readImage(): unable to decode image 'photo.jpg'
```

**`-j$(nproc)`** — `make`-ə paralel compile etməyi deyir, CPU sayı qədər (build 2-5x sürətli).

## Mütləq Pattern: Multi-Stage və `.build-deps`

Extension-lar compile olunmaq üçün `gcc`, `make`, `autoconf` tələb edir (~300 MB). Bunlar final image-də **lazım deyil**. Pattern:

### Debian variant

```dockerfile
FROM php:8.3-fpm-bookworm

RUN set -eux; \
    # 1. Runtime lib-lər (image-də qalır)
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libicu72 \
        libpng16-16 \
        libjpeg62-turbo \
        libwebp7 \
        libfreetype6 \
        libzip4 \
        libpq5; \
    \
    # 2. Build-only paketlər (sonda silinəcək)
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get install -y --no-install-recommends \
        libicu-dev \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        libpq-dev \
        $PHPIZE_DEPS; \
    \
    # 3. Extension-ları qur
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip; \
    \
    pecl install redis-6.0.2; \
    docker-php-ext-enable redis; \
    \
    # 4. Build-deps-i sil, runtime lib-lər qalır
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/* /tmp/pear
```

`apt-mark` tricki — runtime üçün lazım olan paketləri qoruyur, yalnız build-deps silinir. Image 450 MB → ~180 MB.

### Alpine variant (daha qısa)

Alpine-də `--virtual` pattern var — paket qrupunu bir adla saxlayıb sonda toplu silmək:

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        # Runtime lib-lər
        icu-libs \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
        libzip \
        libpq \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
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
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*
```

Alpine final image ~110 MB vs Debian ~180 MB.

## Modern Shortcut: `mlocati/docker-php-extension-installer`

Yuxarıdakı pattern uzun və səhvə açıqdır. `mlocati/docker-php-extension-installer` avtomatik **hansı sistem lib-ləri lazım olduğunu bilir** və onları quraşdırır, build-deps-i silir. Həm Debian, həm Alpine-də işləyir.

```dockerfile
FROM php:8.3-fpm-alpine

# Installer-i əlavə et
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        redis \
        zip \
        imagick
```

Yuxarıdakı 40 sətir → 15 sətir. Sistem lib-ləri, configure flag-ları, build-deps-in silinməsi — hər şey avtomatikdir. Hər extension öz düzgün default flag-ları ilə gəlir (məs. `gd` WebP + FreeType + JPEG ilə).

**Qeyd:** Production-da installer versiyasını pin et:
```dockerfile
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.2.13/install-php-extensions \
    /usr/local/bin/
```

## Laravel üçün Tipik Extension Siyahısı

| Extension | Niyə Laravel-də lazımdır |
|-----------|--------------------------|
| `pdo_mysql` / `pdo_pgsql` | DB əlaqəsi (Eloquent) |
| `mbstring` | UTF-8 string-lər (default image-də var) |
| `bcmath` | Pul, dəqiq onluq hesab (`Money::make`, financial app-lar) |
| `intl` | Carbon localization, sıralama, `Str::slug` (transliteration) |
| `gd` | Şəkil resize, Intervention Image (alternativ) |
| `imagick` | Şəkil güclü manipulyasiya — PDF, SVG, CMYK |
| `exif` | Şəkil meta-data (camera, orientation) |
| `zip` | Composer + `Storage::extract` + Excel export |
| `opcache` | Bytecode cache — prod-da ~3x sürətli |
| `redis` | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` |
| `pcntl` | Queue worker (`php artisan queue:work`), Octane |
| `posix` | Queue daemon signal (`SIGTERM` graceful shutdown) |
| `sodium` | Laravel Passport, modern crypto (default var) |
| `openssl` | HTTPS, JWT, `Crypt::encrypt` (default var) |
| `xml`, `dom`, `simplexml` | SOAP, Excel, XML parse (default var) |
| `ctype`, `json`, `tokenizer` | Laravel core (default var) |

`composer.json`-dakı `require` bölümü `ext-*` sətirləri image-də hamısı olmalıdır:
```json
"require": {
    "php": "^8.3",
    "ext-bcmath": "*",
    "ext-gd": "*",
    "ext-intl": "*",
    "ext-pdo_mysql": "*",
    "ext-redis": "*"
}
```

## PECL Extension-ları — Dərin Baxış

### Redis

```dockerfile
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis
```

Versiyanı pin et (`redis-6.0.2`), yoxsa hər build fərqli versiya gələ bilər.

### Imagick

```dockerfile
# Alpine
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev \
    && apk add --no-cache imagemagick imagemagick-libs \
    && pecl install imagick-3.7.0 \
    && docker-php-ext-enable imagick \
    && apk del .build-deps
```

**Tələ:** Alpine-də `musl libc` səbəbindən bəzi versiyalarda segfault olur. Debian daha sabitdir imagick üçün.

### Xdebug (yalnız dev)

```dockerfile
RUN pecl install xdebug-3.3.2 \
    && docker-php-ext-enable xdebug
```

Production-da **heç vaxt** — 10x yavaşladır (41-ci faylda detallı).

### Swoole / Octane

```dockerfile
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install swoole-5.1.2 \
    && docker-php-ext-enable swoole \
    && apk del .build-deps
```

### MongoDB

```dockerfile
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS openssl-dev \
    && pecl install mongodb-1.18.1 \
    && docker-php-ext-enable mongodb \
    && apk del .build-deps
```

### PCOV (test coverage, prod-grade sürətli)

```dockerfile
RUN pecl install pcov \
    && docker-php-ext-enable pcov
```

Xdebug-dan 5-10x sürətli coverage. Test profilində istifadə et.

## Alpine vs Debian — Hansı?

| Kriteriya | Alpine | Debian (`bookworm-slim`) |
|-----------|--------|--------------------------|
| Base ölçü | ~5 MB | ~80 MB |
| PHP image ölçü | ~80 MB | ~180 MB |
| C kitabxanası | `musl libc` | `glibc` |
| Package manager | `apk` | `apt` |
| Build sürəti | Sürətli (kiçik paket ağacı) | Daha yavaş |
| Compatibility | Bəzi extension-lar segfault (imagick, icu) | Hər şey işləyir |
| Security scan | Az CVE | Çox paket, çox CVE |

**Tövsiyə:**
- **Başla Alpine-lə** — kiçik, sürətli
- **Problem yaşayırsan** (imagick crash, DNS resolver, glibc-spec binary) — Debian-a keç
- **Enterprise** — Debian daha təhlükəsiz hesab olunur (stabil LTS dəstəyi)

Məsələn, bizim case-də bir müştəri Alpine-də `imagick` + böyük PDF → segfault. Debian-a keçdik, problem həll oldu.

## Verifikasiya: Extension-lar Həqiqətən Quraşdırılıbmı?

```bash
# Konteynerdə
docker compose exec app php -m

# Nəticə:
# [PHP Modules]
# bcmath
# Core
# ctype
# curl
# date
# dom
# exif
# fileinfo
# filter
# gd
# hash
# iconv
# intl
# json
# libxml
# mbstring
# mysqlnd
# openssl
# pcntl
# pcre
# PDO
# pdo_mysql
# pdo_pgsql
# Phar
# posix
# readline
# redis
# Reflection
# session
# SimpleXML
# sodium
# SPL
# standard
# tokenizer
# xml
# xmlreader
# xmlwriter
# zip
# zlib
```

Konkret extension:
```bash
docker compose exec app php -r 'echo extension_loaded("redis") ? "YES" : "NO";'
```

Versiya:
```bash
docker compose exec app php -r 'echo phpversion("redis");'
# 6.0.2
```

Laravel `composer check-platform-reqs`:
```bash
docker compose exec app composer check-platform-reqs
```

Bütün `ext-*` tələblər işarəli olmalıdır.

## Reproducible Build — Versiya Pin

Hər PECL extension-ın versiyasını pin et:

```dockerfile
# YANLIŞ — hər build fərqli versiya
RUN pecl install redis

# DÜZGÜN — tam deterministic
RUN pecl install redis-6.0.2
```

Eyni şey `install-php-extensions`-la:
```dockerfile
RUN install-php-extensions redis-6.0.2 imagick-3.7.0
```

Base image-də də digest pin:
```dockerfile
FROM php:8.3.14-fpm-alpine@sha256:abc123...
```

## Tələlər (Gotchas)

### 1. `docker-php-ext-install redis` işləmir

```
error: /usr/src/php/ext/redis does not exist
```

**Səbəb:** `redis` core extension **deyil**, PECL extension-dır.

**Həll:** `pecl install redis && docker-php-ext-enable redis`.

### 2. `gd` JPEG açmır

```
Call to undefined function imagecreatefromjpeg()
```

**Səbəb:** `docker-php-ext-install gd` olub, amma `docker-php-ext-configure` yoxdur. Default flag-lar `--with-jpeg` aktiv etmir.

**Həll:**
```dockerfile
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd
```

### 3. `pecl install` OK, amma `php -m`-də yoxdur

PECL compile edir, amma aktiv etmir — `.ini` faylı yazılmır.

**Həll:**
```dockerfile
RUN pecl install redis && docker-php-ext-enable redis
#                        ^^^^^^^^^^^^^^^^^^^^^^^^^^^ bu vacibdir
```

### 4. Image 500 MB+ olur

**Səbəb:** Build-deps (`autoconf`, `gcc`, `make`, `libpng-dev`) final image-də qalır.

**Həll:** `.build-deps` virtual paketi (Alpine) və ya `apt-mark` pattern (Debian) ilə silin. Və ya `install-php-extensions` istifadə et — avtomatik.

### 5. `mbstring` configure fail

```
checking for Oniguruma... no
```

PHP 7.4+-də `mbstring` artıq Oniguruma istəmir. Amma köhnə image-də:

**Həll (Alpine):** `apk add oniguruma-dev`. **PHP 8+-də artıq problem yoxdur**.

### 6. Alpine-də `imagick` segfault

```
[Wed Oct 04 10:23:45] NOTICE: child 12 exited on signal 11 (SIGSEGV)
```

**Səbəb:** `musl libc` + ImageMagick uyğunsuzluğu bəzi versiyalarda.

**Həll:** Debian base-ə keç (`php:8.3-fpm-bookworm`). Və ya ImageMagick əvəzinə `gd` + Intervention Image.

### 7. `install-php-extensions` cache bust etmir

Installer-in URL-i `latest/download/...`-a göstərir — versiya dəyişəndə Docker cache layer-i bust etmir.

**Həll:** Versiya pin:
```dockerfile
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.2.13/install-php-extensions \
    /usr/local/bin/
```

### 8. `pcntl` Alpine-də siqnal düzgün ötürmür

Queue worker `SIGTERM` gəlir amma dayanmır — orphaned process.

**Həll:** `tini` PID 1 kimi. `docker-compose.yml`:
```yaml
init: true
```

### 9. `opcache` quraşdırılıb, amma aktiv deyil

Core extension-dır, `docker-php-ext-install opcache` kifayət etməlidir. Amma bəzi köhnə image-də manual `.ini` lazımdır:

```dockerfile
RUN docker-php-ext-install opcache \
    && echo "zend_extension=opcache.so" > /usr/local/etc/php/conf.d/opcache.ini
```

Yoxla: `php -i | grep -i opcache`.

### 10. Eyni extension iki dəfə yüklənir

`php -m`-də `redis` iki dəfə görünür — həm PECL, həm apt-dən (`php8-redis` paket) gəlib.

**Həll:** Base image-dən istifadə et (`php:8.3-fpm`), distro repo-dan PHP paketləri quraşdırma.

## Tam İşləyən Nümunə — Laravel Prod Dockerfile

```dockerfile
# syntax=docker/dockerfile:1.7
FROM php:8.3-fpm-alpine AS base

# Installer (modern shortcut)
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.2.13/install-php-extensions \
    /usr/local/bin/

# Laravel-in tam extension dəsti
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        redis-6.0.2 \
        zip \
    && rm /usr/local/bin/install-php-extensions

# Verifikasiya build-time-da
RUN php -m | grep -qE '^(bcmath|gd|intl|redis|pdo_mysql)$' \
    && echo "All extensions OK"

WORKDIR /var/www/html

# ... composer install, COPY, entrypoint ...
```

## Müsahibə Sualları

- **Q:** `docker-php-ext-install` və `pecl install` fərqi nədir?
  - `docker-php-ext-install` yalnız **core extension-lar** üçündür (PHP source tree-də `ext/`-də olanlar — pdo_mysql, gd, intl). `pecl install` **kənar extension-lar** üçündür (redis, xdebug, imagick, swoole). PECL-dən sonra `docker-php-ext-enable` çağırmaq lazımdır.

- **Q:** `docker-php-ext-configure` nə vaxt lazımdır?
  - Extension compile flag-ı istəyəndə. Ən məşhur — `gd`: `--with-freetype --with-jpeg --with-webp`. Bu flag-lar olmasa GD JPEG/WebP/PNG ilə işləməz.

- **Q:** Niyə build-deps ayrıca `.build-deps` virtual paket kimi qurulur?
  - Compile üçün `gcc`, `make`, `autoconf`, `*-dev` lib-lər lazımdır (~300 MB), amma runtime-da lazım deyil. Alpine-də `apk add --virtual .build-deps ... && apk del .build-deps`, Debian-da `apt-mark` pattern. Image 450 MB → 110 MB.

- **Q:** `install-php-extensions` nədir, nə vaxt istifadə edərdiniz?
  - `mlocati/docker-php-extension-installer` community-driven shortcut-dur. Sistem lib-lərini avtomatik quraşdırır, düzgün flag-ları verir, build-deps-i silir. Dockerfile-ı 40 sətirdən 15-ə salır. **Production-da versiyasını pin edin** (`latest/download` cache bust etmir).

- **Q:** Alpine və Debian base arasında extension quraşdırarkən fərqlər nələrdir?
  - Alpine `musl libc`, Debian `glibc` istifadə edir. Paket adları fərqlidir (`libpng-dev` vs `libpng-dev` çox oxşar, amma `libicu-dev` vs `icu-dev`). Alpine-də `imagick` bəzən segfault olur. Alpine image 80 MB, Debian 180 MB. Tövsiyə: Alpine-lə başla, problem olsa Debian-a keç.

- **Q:** Production image-də `xdebug` nə üçün olmaz?
  - Xdebug zend_extension yüklənir — 5-10% overhead **deaktiv olsa belə**. `xdebug.mode=debug` aktiv olsa 10x yavaşlayır. Dev image-də olsun, prod image-də `pecl install xdebug` heç vaxt. Test coverage üçün PCOV istifadə et (prod-grade, sürətli).

- **Q:** Extension versiyasını niyə pin edirsiz?
  - Reproducible build. `pecl install redis` bu həftə 6.0.2 yüklədi, gələn həftə 6.1.0 gələ bilər — API dəyişsə prod qırılır. Həmişə `pecl install redis-6.0.2`. Composer.lock ilə eyni fəlsəfə.

- **Q:** Laravel üçün minimum extension siyahısı nədir?
  - `pdo_mysql` (DB), `mbstring` + `openssl` + `tokenizer` + `json` + `ctype` (core, default var), `bcmath` (pul), `intl` (locale), `gd` və ya `imagick` (şəkil), `zip` (Composer), `redis` (cache/queue), `opcache` (prod perf), `pcntl` (queue worker). `composer.json`-dakı `ext-*` siyahısına əməl et.


## Əlaqəli Mövzular

- [dockerfile.md](02-dockerfile.md) — Dockerfile instruksiyaları
- [php-laravel-production-dockerfile.md](36-php-laravel-production-dockerfile.md) — Production Dockerfile
- [composer-in-docker-best-practices.md](43-composer-in-docker-best-practices.md) — Composer best practices
