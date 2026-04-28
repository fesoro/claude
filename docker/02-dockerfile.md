# Dockerfile

> **Səviyyə (Level):** ⭐ Junior

## Nədir? (What is it?)

Dockerfile — Docker image yaratmaq üçün instruksiyalar dəsti olan mətn faylıdır. Hər instruksiya image-də yeni bir layer yaradır. Docker `docker build` əmri ilə Dockerfile-ı oxuyur və addım-addım image qurur.

```bash
# Dockerfile-dan image qurmaq
docker build -t myapp:v1.0 .
docker build -t myapp:v1.0 -f Dockerfile.production .
```

## Əsas Konseptlər

### FROM — Base Image

Hər Dockerfile `FROM` ilə başlamalıdır. Base image-i təyin edir.

```dockerfile
# Rəsmi PHP image
FROM php:8.3-fpm

# Alpine (yüngül) versiya
FROM php:8.3-fpm-alpine

# Spesifik versiya (digest ilə)
FROM php:8.3-fpm-alpine@sha256:abc123...

# Scratch (boş) image — minimal binary-lər üçün
FROM scratch
```

### RUN — Əmr İcra Etmə

Build vaxtı əmr icra edir. Hər RUN yeni layer yaradır.

```dockerfile
# Tək sətir
RUN apt-get update

# Çox sətirli (layer sayını azaltmaq üçün birləşdirmək vacibdir)
RUN apt-get update && apt-get install -y \
    curl \
    git \
    libpng-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-install \
    gd \
    pdo_mysql \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
```

**Multi-line RUN Optimization:** Paketləri bir RUN-da birləşdirin, cache-i təmizləyin, əlifba sırası ilə yazın.

```dockerfile
# PİS — hər biri ayrı layer yaradır, cache böyüyür
RUN apt-get update
RUN apt-get install -y curl
RUN apt-get install -y git
RUN apt-get clean

# YAXŞI — bir layer, cache təmizlənir
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
```

### CMD — Default Əmr

Konteyner başlayanda icra olunan default əmr. Yalnız bir CMD ola bilər. `docker run` ilə override edilə bilər.

```dockerfile
# Exec form (tövsiyə olunan)
CMD ["php-fpm"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# Shell form
CMD php artisan serve --host=0.0.0.0 --port=8000
```

### ENTRYPOINT — Giriş Nöqtəsi

Konteyner başlayanda HƏMİŞƏ icra olunan əmr. CMD ilə birlikdə istifadə olunanda CMD arqument verir.

```dockerfile
# ENTRYPOINT + CMD kombinasiyası
ENTRYPOINT ["php", "artisan"]
CMD ["serve", "--host=0.0.0.0"]

# İstifadə:
# docker run myapp                    -> php artisan serve --host=0.0.0.0
# docker run myapp migrate            -> php artisan migrate
# docker run myapp queue:work         -> php artisan queue:work
```

### CMD vs ENTRYPOINT Fərqi

```dockerfile
# Yalnız CMD — docker run ilə tam override olunur
CMD ["php-fpm"]
# docker run myapp bash  ->  bash icra olunur (CMD override edildi)

# Yalnız ENTRYPOINT — docker run arqumentləri sonuna əlavə olunur
ENTRYPOINT ["php-fpm"]
# docker run myapp -R  ->  php-fpm -R icra olunur

# Hər ikisi birlikdə — ENTRYPOINT sabit, CMD default arqument
ENTRYPOINT ["php"]
CMD ["artisan", "serve"]
# docker run myapp                    -> php artisan serve
# docker run myapp artisan migrate    -> php artisan migrate
```

| Xüsusiyyət | CMD | ENTRYPOINT |
|-------------|-----|------------|
| Override | `docker run` ilə asanlıqla | `--entrypoint` flaqı lazımdır |
| Məqsəd | Default əmr/arqument | Sabit icra olunan əmr |
| Birlikdə | ENTRYPOINT-ə arqument verir | CMD-dən arqument alır |

### COPY — Fayl Kopyalama

Host-dan image-ə fayl kopyalayır.

```dockerfile
# Tək fayl
COPY composer.json /app/composer.json

# Qovluq
COPY . /app

# Çoxlu fayl
COPY composer.json composer.lock /app/

# --chown ilə (ownership)
COPY --chown=www-data:www-data . /app

# --chmod ilə (icazələr, BuildKit lazımdır)
COPY --chmod=755 entrypoint.sh /usr/local/bin/
```

### ADD — Genişləndirilmiş Kopyalama

COPY kimi işləyir, əlavə olaraq URL-dən yükləyir və tar arxivlərini açır.

```dockerfile
# Tar arxivini avtomatik açır
ADD app.tar.gz /app

# URL-dən yükləyir
ADD https://example.com/file.txt /app/

# Adətən COPY tövsiyə olunur, ADD yalnız tar açma lazım olanda
```

### WORKDIR — İş Qovluğu

Sonrakı instruksiyalar üçün iş qovluğunu təyin edir.

```dockerfile
WORKDIR /var/www/html

# Əgər mövcud deyilsə, yaradılır
WORKDIR /app
COPY . .
RUN composer install
```

### EXPOSE — Port Elanı

Konteynerin hansı portda dinlədiyini sənədləşdirir. Əslində portu açmır, yalnız metadata-dır.

```dockerfile
EXPOSE 80
EXPOSE 443
EXPOSE 9000

# Portu həqiqətən açmaq üçün docker run -p lazımdır
# docker run -p 8080:80 myapp
```

### ENV — Mühit Dəyişəni

Build vaxtı və runtime-da mövcud olan mühit dəyişənləri.

```dockerfile
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_PORT=8000

# Çoxlu dəyişən
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_HOST=mysql
```

### ARG — Build Arqumenti

Yalnız build vaxtı mövcud olan dəyişən. Runtime-da yoxdur.

```dockerfile
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

# Build vaxtı dəyişdirmək
# docker build --build-arg PHP_VERSION=8.2 .
# docker build --build-arg APP_ENV=staging .
```

| Xüsusiyyət | ENV | ARG |
|-------------|-----|-----|
| Build vaxtı | Var | Var |
| Runtime | Var | Yox |
| Override | `docker run -e` | `docker build --build-arg` |
| Layer-lərə təsir | Var | Var |

### USER — İstifadəçi

Sonrakı instruksiyalar və runtime üçün istifadəçini təyin edir.

```dockerfile
# İstifadəçi yaratmaq və keçmək
RUN addgroup --system appgroup && \
    adduser --system --ingroup appgroup appuser

USER appuser

# Və ya mövcud istifadəçi
USER www-data
```

### LABEL — Metadata

Image-ə metadata əlavə edir.

```dockerfile
LABEL maintainer="developer@example.com"
LABEL version="1.0"
LABEL description="Laravel Application"
LABEL org.opencontainers.image.source="https://github.com/user/repo"
```

### .dockerignore

Build context-dən faylları xaric edir. `.gitignore` kimi işləyir.

```
# .dockerignore
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
docker-compose*.yml
Dockerfile*
README.md
.editorconfig
.phpunit.result.cache
phpunit.xml
```

## Praktiki Nümunələr

### Sadə PHP Dockerfile

```dockerfile
FROM php:8.3-cli

WORKDIR /app

COPY . .

CMD ["php", "index.php"]
```

### Nginx Dockerfile

```dockerfile
FROM nginx:1.25-alpine

COPY nginx.conf /etc/nginx/nginx.conf
COPY site.conf /etc/nginx/conf.d/default.conf

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
```

## PHP/Laravel ilə İstifadə

### Tam Laravel Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

# Metadata
LABEL maintainer="developer@example.com"
LABEL description="Laravel PHP-FPM Application"

# Build arqumentləri
ARG APP_ENV=production
ARG WWWUSER=1000
ARG WWWGROUP=1000

# Mühit dəyişənləri
ENV APP_ENV=${APP_ENV} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

# Sistem paketləri və PHP extension-ları
RUN apk add --no-cache \
    curl \
    freetype-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer quraşdırma
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP konfiqurasiyası
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# İstifadəçi yaratmaq
RUN addgroup -g ${WWWGROUP} -S www && \
    adduser -u ${WWWUSER} -S -G www www

# İş qovluğu
WORKDIR /var/www/html

# Əvvəlcə composer fayllarını kopyalayın (cache üçün)
COPY composer.json composer.lock ./

# Dependency-ləri quraşdırmaq
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# Tətbiq kodunu kopyalamaq
COPY --chown=www:www . .

# Post-install scriptlər
RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Qovluq icazələri
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www:www storage bootstrap/cache

# İstifadəçini dəyişmək
USER www

# Port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Başlatmaq
CMD ["php-fpm"]
```

### Laravel Artisan üçün Entrypoint Script

```bash
#!/bin/sh
# docker/entrypoint.sh

set -e

# İlk dəfə işlədikdə
if [ "$1" = "php-fpm" ] || [ "$1" = "php" ]; then
    # Migrations
    php artisan migrate --force

    # Cache
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "Application is ready!"
fi

exec "$@"
```

```dockerfile
# Dockerfile-da entrypoint əlavə etmək
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
```

## İntervyu Sualları

### 1. CMD və ENTRYPOINT arasında fərq nədir?
**Cavab:** CMD konteyner başlayanda default əmr verir, `docker run` ilə asanlıqla override olunur. ENTRYPOINT sabit icra olunan əmri təyin edir, override üçün `--entrypoint` lazımdır. Birlikdə istifadə olunanda ENTRYPOINT əmri, CMD isə default arqumentləri təyin edir.

### 2. COPY və ADD arasında fərq nədir?
**Cavab:** Hər ikisi fayl kopyalayır. ADD əlavə olaraq URL-dən yükləyə bilir və tar arxivlərini avtomatik açır. Best practice olaraq COPY tövsiyə olunur çünki daha şəffaf və sadədir. ADD yalnız tar açma lazım olanda istifadə edilməlidir.

### 3. RUN instruksiyalarını niyə birləşdirmək lazımdır?
**Cavab:** Hər RUN yeni layer yaradır. Çoxlu kiçik RUN-lar image ölçüsünü artırır (əvvəlki layer-lərdə silmək sonrakı layer-lərdə yer azaltmır). Birləşdirmə layer sayını azaldır, cache təmizliyi eyni layer-də olduqda ölçünü kiçildir, build sürətini artırır.

### 4. .dockerignore faylı nə üçün lazımdır?
**Cavab:** Build context-in ölçüsünü azaldır (Docker CLI bütün context-i daemon-a göndərir). Həssas faylların (.env, .git) image-ə düşməsinin qarşısını alır. Build sürətini artırır. `.gitignore` sintaksisi ilə işləyir.

### 5. ARG və ENV arasında fərq nədir?
**Cavab:** ARG yalnız build vaxtı mövcuddur, `--build-arg` ilə dəyişdirilir, runtime-da yoxdur. ENV həm build həm runtime-da mövcuddur, `docker run -e` ilə override olunur. ARG-dan ENV-ə dəyər ötürmək üçün `ENV VAR=${ARG_VAR}` istifadə olunur.

### 6. Dockerfile-da layer cache necə işləyir?
**Cavab:** Docker hər instruksiyanı hash-ləyir. Əgər instruksiya və ondan əvvəlki bütün layer-lər dəyişməyibsə, cache-dən istifadə edir. Bir layer dəyişdikdə ondan sonrakı bütün layer-lər yenidən qurulur. Ona görə az dəyişən instruksiyalar (apt-get, composer install) əvvəl, tez dəyişən (COPY . .) sonra yazılmalıdır.

### 7. EXPOSE instruksiyası portu açırmı?
**Cavab:** Xeyr. EXPOSE yalnız sənədləşdirmə məqsədlidir (metadata). Portu həqiqətən açmaq üçün `docker run -p` lazımdır. Ancaq `docker run -P` istifadə edildikdə EXPOSE-da göstərilən portlar avtomatik random host portlara map olunur.

### 8. Non-root istifadəçi niyə vacibdir?
**Cavab:** Default olaraq konteyner root kimi işləyir. Əgər konteyner kompromis olunsa, root hüquqları ilə host-a zərər verə bilər. USER instruksiyası ilə non-root istifadəçiyə keçmək təhlükəsizliyi artırır. Laravel üçün www-data və ya xüsusi istifadəçi yaradılmalıdır.

## Best Practices

1. **Minimal base image istifadə edin** — `alpine` variantlarını seçin (`php:8.3-fpm-alpine`).
2. **RUN instruksiyalarını birləşdirin** — Layer sayını və ölçüsünü azaldın.
3. **Cache-i düşünərək sıralayın** — Az dəyişən instruksiyaları əvvələ qoyun.
4. **Composer fayllarını əvvəl kopyalayın** — `COPY composer.json composer.lock ./` sonra `RUN composer install`, sonra `COPY . .`
5. **Non-root istifadəçi istifadə edin** — `USER www-data` ilə təhlükəsizliyi artırın.
6. **`.dockerignore` yaradın** — Lazımsız faylları xaric edin.
7. **Spesifik tag istifadə edin** — `FROM php:8.3-fpm-alpine` (`latest` deyil).
8. **HEALTHCHECK əlavə edin** — Konteyner sağlamlığını yoxlayın.
9. **Exec form istifadə edin** — `CMD ["php-fpm"]` (`CMD php-fpm` deyil).
10. **Secret-ləri build-ə daxil etməyin** — ARG/ENV-də parol saxlamayın, BuildKit secret mount istifadə edin.


## Əlaqəli Mövzular

- [docker-basics.md](01-docker-basics.md) — Docker əsasları, konteyner konsepti
- [dockerignore-build-context.md](03-dockerignore-build-context.md) — Build context optimallaşdırma
- [multi-stage-builds.md](04-multi-stage-builds.md) — Multi-stage ilə image ölçüsünü azaltmaq
- [docker-optimization.md](11-docker-optimization.md) — Layer caching, BuildKit
