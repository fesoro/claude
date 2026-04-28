# Docker BuildKit Advanced

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

**BuildKit** — Docker-in növbəti nəsil build engine-idir. Klassik `docker build` əmrinin arxasında duran sistem olub, paralel build, daha yaxşı caching, secrets management, SSH forwarding, və multi-platform build imkanı verir.

BuildKit Docker 18.09-dan buyana mövcuddur, Docker 23+ versiyalarda default olaraq aktivdir.

## Əsas Konseptlər

### 1. BuildKit-in Üstünlükləri

```
Klassik builder:               BuildKit:
├── Sequential steps           ├── Parallel execution (DAG)
├── Linear cache              ├── Content-addressable cache
├── No secrets support        ├── --mount=type=secret
├── No SSH support            ├── --mount=type=ssh
├── Single platform           ├── Multi-platform (QEMU)
└── Limited cache management  └── Cache import/export
```

### 2. BuildKit-i Aktivləşdirmək

```bash
# Docker 23+ üçün default aktivdir

# Köhnə versiyalar üçün:
export DOCKER_BUILDKIT=1
docker build .

# Və ya /etc/docker/daemon.json
{
  "features": {
    "buildkit": true
  }
}

# docker-compose üçün
export COMPOSE_DOCKER_CLI_BUILD=1
export DOCKER_BUILDKIT=1
```

### 3. Syntax Header

BuildKit-in advanced feature-ları üçün Dockerfile-ın əvvəlində syntax direktivi qoyulur:

```dockerfile
# syntax=docker/dockerfile:1.7
# Bu ən son frontend versiyasını yükləyir — --mount dəstəyi və s. üçün lazım

FROM php:8.3-fpm-alpine
...
```

## Praktiki Nümunələr

### 1. Cache Mount (composer install-ı sürətləndirmək)

```dockerfile
# syntax=docker/dockerfile:1.7
FROM php:8.3-cli-alpine AS build

WORKDIR /app
COPY composer.json composer.lock ./

# Composer cache-i host-da saxlanılır
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize
```

Növbəti build-də composer cache yenidən yüklənmir — ilk build 60s, sonrakılar 5s.

### 2. Secret Mount (build zamanı secret istifadə etmək)

```dockerfile
# syntax=docker/dockerfile:1.7
FROM composer:2 AS build

WORKDIR /app
COPY composer.json composer.lock auth.json /tmp/
COPY . .

# GitHub token ilə private package install
RUN --mount=type=secret,id=github_token \
    GH_TOKEN=$(cat /run/secrets/github_token) && \
    composer config github-oauth.github.com "$GH_TOKEN" && \
    composer install --no-dev
```

Build:
```bash
# Terminal-dən secret ötür
docker build --secret id=github_token,env=GITHUB_TOKEN -t myapp .

# Yaxud fayldan
docker build --secret id=github_token,src=$HOME/.github_token -t myapp .
```

Image-də secret **saxlanmır**.

### 3. SSH Mount (private repository clone)

```dockerfile
# syntax=docker/dockerfile:1.7
FROM alpine/git AS clone

RUN --mount=type=ssh \
    mkdir -p -m 0700 ~/.ssh && \
    ssh-keyscan github.com >> ~/.ssh/known_hosts && \
    git clone git@github.com:myorg/private-repo.git /repo
```

Build:
```bash
# SSH agent-dən forward
eval $(ssh-agent)
ssh-add ~/.ssh/id_ed25519
docker build --ssh default -t myapp .

# Spesifik açar
docker build --ssh default=$HOME/.ssh/id_rsa -t myapp .
```

### 4. Bind Mount (source-u kopyalamadan build)

```dockerfile
# syntax=docker/dockerfile:1.7
FROM composer:2 AS build
WORKDIR /app

# composer.json-u bind mount ilə, kopyalamadan
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-scripts
```

Faydası: composer install artefaktı final image-də qalır, lakin composer.json layer-də yox.

### 5. Multi-Platform Build

```bash
# Setup buildx
docker buildx create --name multiplatform --use
docker buildx inspect --bootstrap

# amd64 + arm64 üçün eyni anda build
docker buildx build \
    --platform linux/amd64,linux/arm64 \
    --tag myregistry/myapp:1.0.0 \
    --push \
    .
```

Dockerfile multi-platform ucün:

```dockerfile
# syntax=docker/dockerfile:1.7
ARG TARGETPLATFORM
ARG BUILDPLATFORM
FROM --platform=$BUILDPLATFORM golang:1.22 AS build
ARG TARGETOS TARGETARCH
WORKDIR /app
COPY . .
RUN GOOS=$TARGETOS GOARCH=$TARGETARCH go build -o app .

FROM alpine:3.19
COPY --from=build /app-laravel/app /app
CMD ["/app"]
```

### 6. Cache Export/Import (Registry-yə cache yaz)

```bash
# Cache-i registry-yə push et
docker buildx build \
    --cache-to type=registry,ref=myregistry/myapp:cache,mode=max \
    --cache-from type=registry,ref=myregistry/myapp:cache \
    --tag myregistry/myapp:latest \
    --push .
```

CI/CD-də faydalıdır — hər runner eyni cache-i paylaşır.

```bash
# Inline cache (image daxilində cache)
docker buildx build \
    --cache-to type=inline \
    --cache-from myregistry/myapp:latest \
    --tag myregistry/myapp:latest \
    --push .
```

### 7. Docker Bake (Multiple Images)

`docker-bake.hcl`:
```hcl
group "default" {
  targets = ["app", "worker", "nginx"]
}

target "common" {
  context = "."
  platforms = ["linux/amd64", "linux/arm64"]
  cache-from = ["type=registry,ref=myregistry/cache"]
  cache-to = ["type=registry,ref=myregistry/cache,mode=max"]
}

target "app" {
  inherits = ["common"]
  dockerfile = "docker/app.Dockerfile"
  tags = ["myregistry/app:latest", "myregistry/app:${VERSION}"]
}

target "worker" {
  inherits = ["common"]
  dockerfile = "docker/worker.Dockerfile"
  tags = ["myregistry/worker:latest"]
}

target "nginx" {
  inherits = ["common"]
  dockerfile = "docker/nginx.Dockerfile"
  tags = ["myregistry/nginx:latest"]
}

variable "VERSION" {
  default = "1.0.0"
}
```

Build:
```bash
VERSION=1.2.3 docker buildx bake --push
# 3 image eyni anda paralel build olunur
```

## PHP/Laravel ilə İstifadə

### Optimized Laravel Dockerfile

```dockerfile
# syntax=docker/dockerfile:1.7

# Stage 1: Composer dependencies (with cache)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    --mount=type=secret,id=composer_auth,target=/app/auth.json,required=false \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-scripts \
        --no-interaction \
        --prefer-dist

# Stage 2: Node modules + frontend build (with cache)
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci

COPY resources/ resources/
COPY public/ public/
RUN --mount=type=cache,target=/app/node_modules/.vite \
    npm run build

# Stage 3: Final PHP-FPM image
FROM php:8.3-fpm-alpine

RUN --mount=type=cache,target=/var/cache/apk \
    apk add --no-cache \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        intl \
        bcmath \
        zip \
        opcache

RUN addgroup -g 1000 laravel && adduser -u 1000 -G laravel -s /bin/sh -D laravel

WORKDIR /var/www/html
COPY --from=vendor --chown=laravel:laravel /app-laravel/vendor vendor/
COPY --from=frontend --chown=laravel:laravel /app-laravel/public/build public/build/
COPY --chown=laravel:laravel . .

RUN composer dump-autoload --optimize --classmap-authoritative

USER laravel
EXPOSE 9000
CMD ["php-fpm"]
```

Build:
```bash
docker buildx build \
    --secret id=composer_auth,src=$HOME/.composer/auth.json \
    --cache-from type=registry,ref=myregistry/laravel:buildcache \
    --cache-to type=registry,ref=myregistry/laravel:buildcache,mode=max \
    --tag myregistry/laravel:1.0.0 \
    --push .
```

### GitHub Actions-da BuildKit

```yaml
name: Build Laravel
on: push

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          platforms: linux/amd64,linux/arm64
          push: true
          tags: ghcr.io/myorg/laravel:latest
          cache-from: type=gha
          cache-to: type=gha,mode=max
          secrets: |
            composer_auth=${{ secrets.COMPOSER_AUTH }}
```

## Interview Sualları

**1. BuildKit nədir və klassik builder-dən nə ilə fərqlənir?**
BuildKit — Docker-in yeni build engine-idir. Paralel build (DAG), content-addressable cache, secrets/SSH mount, multi-platform build dəstəyi var.

**2. `--mount=type=cache` nə edir?**
Build zamanı persistent cache volume yaradır. Composer cache, npm cache, apt cache kimi yerlər üçün istifadə olunur. Image-də bu cache qalmır — yalnız build zamanı istifadə olunur.

**3. Build secret-ları necə ötürürük?**
`--mount=type=secret,id=mysecret` ilə. CLI-da `--secret id=mysecret,src=/path/to/file`. Secret image history və layer-larda qalmır.

**4. Multi-platform build necə işləyir?**
Buildx + QEMU emulation. Ya native builder (ARM maşın amd64 build edir) yaxud QEMU virtualization. `docker buildx build --platform linux/amd64,linux/arm64`.

**5. `--cache-to` və `--cache-from` fərqi?**
- `--cache-to`: build cache-ini yerə yaz (registry, local, inline)
- `--cache-from`: cache-i hardan oxu

CI-də cache registry-də saxlanır, hər run oradan yüklənir.

**6. Docker Bake nədir?**
Birdən çox image-i bir konfiqurasiyadan build etmək üçün. HCL/JSON formatında target-lər təyin olunur, `docker buildx bake` ilə hamısı paralel build olunur.

**7. Inline cache vs Registry cache fərqi?**
- **Inline**: cache metadata image-ə əlavə olunur, image ölçüsünü artırır, sadə
- **Registry**: ayrıca cache image, image ölçüsünə təsir etmir, daha rəqəmsal kontrol

**8. BuildKit performans üstünlüyü harda görünür?**
Multi-stage build-də paralelizm. Məs: 3 stage varsa, bir-birindən asılı olmayan stage-lər eyni anda işləyir. Klassik builder sequential işləyirdi.

## Best Practices

1. **BuildKit-i həmişə aktivləşdir** — CI və local-da default olsun
2. **`# syntax=docker/dockerfile:1.7`** — yeni feature-lar üçün
3. **Cache mount** — composer, npm, apt cache üçün
4. **Secret mount** — private package token, API key üçün (ENV ARG istifadə etmə!)
5. **Multi-stage** — build artefaktlarını final image-dən ayır
6. **Registry cache** — CI/CD-də build vaxtı əhəmiyyətli azaldır
7. **Multi-platform** — ARM64 (M1/M2 Mac, AWS Graviton) üçün də build et
8. **Bake** — multi-image repos-larda istifadə et
9. **`.dockerignore`** — lazımsız faylları build context-dən çıxar
10. **Copy strategy** — ən az dəyişən faylları əvvəl kopyala (cache invalidation-dan qaç)


## Əlaqəli Mövzular

- [multi-stage-builds.md](04-multi-stage-builds.md) — Multi-stage build
- [docker-optimization.md](11-docker-optimization.md) — Layer caching
- [docker-ci-cd-github-actions-php.md](51-docker-ci-cd-github-actions-php.md) — CI/CD ilə BuildKit
