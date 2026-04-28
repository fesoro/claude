# .dockerignore və Build Context Optimizasiyası

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

`.dockerignore` — `docker build` başlayanda **build context**-ə daxil olmamalı faylları göstərən fayldır. Build context — Docker daemon-a göndərilən qovluqdur (adətən `.`, yəni `Dockerfile`-ın olduğu qovluq).

Çoxlu developer `.dockerignore`-u unudur. Nəticə:
- Build **yavaş** olur — daemon-a hər dəfə 500 MB `node_modules` göndərilir
- Image **böyük** olur — `.git` qovluğu və `vendor/` image-ə düşür
- **Secret leak** olur — `.env`, `.env.local`, `storage/logs/laravel.log` image-ə girir
- `COPY . .` gözlədiyindən fərqli davranır — host-dakı stale `vendor/` image-dəkini silir

`.dockerignore` — təxminən `.gitignore`-un Docker variantıdır, amma qayda sintaksisi fərqlidir və məqsədi başqadır (security + performance, təkcə version control deyil).

## Build Context Necə İşləyir?

```
developer maşını                    Docker daemon
┌──────────────────┐                ┌────────────────┐
│  myapp/          │                │                │
│  ├── app/        │                │                │
│  ├── vendor/     │  ─tarball─>    │  context dir   │
│  ├── node_modules│    (gzipped)   │  (extracted)   │
│  ├── .git/       │                │                │
│  ├── .env        │                │  COPY . .      │
│  └── Dockerfile  │                │                │
└──────────────────┘                └────────────────┘
```

`docker build .` işlədəndə **bütün** `.` qovluğu tar-lanır, sıxılır və daemon-a göndərilir. Daemon onu local diskə açır və `COPY`/`ADD` əmrləri bu qovluqdan oxuyur.

Real ölçü nümunəsi Laravel layihəsində:
- `app/`, `config/`, `routes/`, `public/`, `resources/` — ~5 MB
- `vendor/` — ~80 MB (fresh install)
- `node_modules/` — ~200 MB (Vite + Tailwind + dev deps)
- `.git/` — ~50 MB (uzun tarixçə)
- `storage/logs/` — ~30 MB (developer local maşında log yığılıb)
- `tests/`, `.github/`, docs — ~5 MB

**Total 370+ MB** — build daemon-a 370 MB göndərilir, amma istifadəsinin yalnız 5 MB-a (mənbə kod) ehtiyac var.

`.dockerignore` düzgün yazılsa: kontekst 5 MB. Build 20x sürətli başlayır.

## Ölçünü Ölçmə

### Üsul 1: `du -sh`

```bash
# Çox sürətli ilkin ölçüm
du -sh . --exclude='./.git'

# Hansı qovluqlar kontekst-də ən böyükdür
du -sh -- */ | sort -rh | head -10
```

### Üsul 2: `docker build --progress=plain`

BuildKit context transfer-in ölçüsünü göstərir:

```bash
DOCKER_BUILDKIT=1 docker build --progress=plain -t myapp . 2>&1 | grep -i "transferring context"
```

Output:
```
#3 [internal] load .dockerignore
#3 transferring dockerfile: 1.2kB done
#5 [internal] load build context
#5 transferring context: 4.8MB done        <-- kontekst ölçüsü
```

### Üsul 3: Dry-run tar

Gerçək nə göndərildiyini gör:

```bash
# .dockerignore-a uyğun fayl siyahısı
docker build --no-cache --progress=plain . 2>&1 | head -50

# Və ya manually tar yarat
tar --exclude-from=.dockerignore -czf /tmp/context.tar.gz . 
ls -lh /tmp/context.tar.gz
```

## Tam Laravel `.dockerignore` Template

```gitignore
# ============================================================
# Version control
# ============================================================
.git
.gitignore
.gitattributes
.github

# ============================================================
# PHP / Composer
# ============================================================
# vendor build-də composer install ilə yenidən yaranır
vendor
composer.phar

# ============================================================
# Node / Frontend assets
# ============================================================
# node_modules build-də npm ci ilə yenidən yaranır
node_modules
npm-debug.log*
yarn-debug.log*
yarn-error.log*
.npm
.yarn
.pnpm-store

# Build output (Vite) — image-də npm run build ilə generate edilir
public/build
public/hot
public/mix-manifest.json

# ============================================================
# Laravel storage — developer-in local məlumatları
# ============================================================
storage/logs/*.log
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
storage/framework/testing/*
storage/app/public/*
!storage/app/public/.gitkeep
!storage/framework/cache/.gitignore
!storage/framework/sessions/.gitignore
!storage/framework/views/.gitignore

# ============================================================
# Environment — CRITICAL, heç vaxt image-ə düşməsin
# ============================================================
.env
.env.*
!.env.example
!.env.production.example

# ============================================================
# Testing — prod image-də lazım deyil
# ============================================================
tests
phpunit.xml
phpunit.xml.dist
.phpunit.cache
.phpunit.result.cache
coverage
.phpunit.coverage

# Pest
pest.xml

# ============================================================
# IDE və editor faylları
# ============================================================
.idea
.vscode
.fleet
*.sublime-project
*.sublime-workspace
.phpstorm.meta.php
_ide_helper.php
_ide_helper_models.php

# ============================================================
# OS faylları
# ============================================================
.DS_Store
Thumbs.db
desktop.ini

# ============================================================
# Docker development files
# ============================================================
# Prod build-də dev-compose lazım deyil, həm də secret içərə bilər
docker-compose.override.yml
docker-compose.dev.yml
docker-compose.local.yml
.docker
.devcontainer

# ============================================================
# Documentation və artefaktlar
# ============================================================
README.md
CHANGELOG.md
CONTRIBUTING.md
LICENSE
docs
*.md
!docker/*.md

# ============================================================
# Build artefaktlar
# ============================================================
*.log
*.cache
*.swp
*.swo
*.bak
*.tmp
.phpactor.json

# ============================================================
# Deployment script-ləri
# ============================================================
deploy
.circleci
.gitlab-ci.yml
.travis.yml
bitbucket-pipelines.yml

# ============================================================
# Laravel-specific
# ============================================================
bootstrap/cache/*.php
public/storage
Homestead.yaml
Homestead.json
.vagrant

# ============================================================
# Frontend source (prod image-də lazımdır — istisna)
# ============================================================
# resources/ lazımdır — Vite build onu istifadə edir
# !resources
```

**Qeyd:** `vendor/` və `node_modules/`-u ignore edirsən, amma multi-stage build-də Composer stage `composer install` ilə onları yenidən yaradır. Sonra final stage-də `COPY --from=vendor /app/vendor ./vendor` ilə əlavə olunur.

## `.dockerignore` Sintaksisi — `.gitignore`-dan Fərqi

### Oxşarlıqlar

- `#` ilə başlayan sətirlər comment
- `/` ilə qovluq, olmadan həm fayl, həm qovluq
- `*` wildcard
- `**` rekursiv wildcard (yeni Docker versiyalarında)
- `!` negation (istisna)

### Fərqlər

| Xüsusiyyət | `.gitignore` | `.dockerignore` |
|------------|--------------|-----------------|
| Lokasiya | Hər qovluqda ola bilər | Yalnız build context root-unda |
| Rekursiv | Default rekursiv | `**` açıq göstərməlidir |
| Leading `/` | Root-a bağlayır | Root-a bağlayır (eyni) |
| `!` negation | Parent ignore olunursa işləmir | Daha maraqlıdır — parent ignore-dan sonra fayl `!`-la qaytarıla bilər |

### Tipik `**` nümunələri

```gitignore
# Bütün .log faylları hər yerdə
**/*.log

# Root-dakı app/debug qovluğu
/app/debug

# Hər yerdə node_modules
**/node_modules

# Yalnız test qovluqlarındakı fixture-ları saxla, qalanını ignore et
tests/**/*
!tests/**/fixtures/
!tests/**/fixtures/**
```

### Negation (`!`) tələsi

```gitignore
# YANLIŞ — bu işləmir
.env*
!.env.example

# Bu işləyir, çünki Docker `!` qaydasını sonuncu uyğun gələn kimi götürür
.env*
!.env.example
```

Amma:
```gitignore
# YANLIŞ — parent qovluq ignore olunub
storage/**
!storage/app/.gitkeep      # bu effektsizdir!
```

Parent qovluq (`storage/`) tam ignore olunursa, daxilindəki `!` işləmir. Bunun əvəzinə qovluq-səviyyə wildcard:

```gitignore
# DÜZGÜN
storage/*
!storage/app
storage/app/*
!storage/app/.gitkeep
```

Mürəkkəbdir — buna görə çox developer sadəcə storage qovluğunun daxilindəki log/cache fayllarını göstərir:

```gitignore
storage/logs/*.log
storage/framework/cache/data/*
```

## Multi-Stage Build-də Context

**Vacib misconception:** multi-stage build-də **hər stage eyni build context-ə malikdir**. `.dockerignore` bir dəfə tətbiq olunur — bütün stage-lər üçün.

```dockerfile
FROM composer:2.7 AS vendor
COPY composer.json composer.lock ./   # eyni kontekstdən
RUN composer install --no-dev

FROM node:20-alpine AS frontend
COPY package.json package-lock.json ./   # eyni kontekstdən
COPY resources/ resources/               # eyni kontekstdən
RUN npm ci && npm run build

FROM php:8.3-fpm-alpine AS production
COPY . .                                 # eyni kontekstdən
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
```

`.dockerignore`-da `vendor/` və `node_modules/`-u exclude etsən:
- Vendor stage-də `composer install` işləyir (host-dan gəlmir — yenidən yaranır)
- Frontend stage-də `npm ci` işləyir
- Production stage-də `COPY . .` mənbə kodunu gətirir, `vendor/` və `node_modules/` yoxdur (çünki ignore olunub), sonra `COPY --from=...` ilə gətirilir

Bu — ideal senarió. Ignore etməsən:
- Host-dakı stale `vendor/` production image-ə copy olur
- `COPY --from=vendor` üzərinə yazır
- Amma əvvəl gərəksiz 80 MB kontekstə gəlib

## Security — `.env` Sızması

Ən təhlükəli səhv: `.dockerignore`-dan `.env` excludeunu unutmaq.

### Problem

```dockerfile
COPY . /var/www/html
```

Əgər `.env` build context-dədirsə, image-ə yazılır:

```bash
docker run --rm myapp:latest cat /var/www/html/.env
# APP_KEY=base64:productionSecret...
# DB_PASSWORD=superSecret
# STRIPE_SECRET=sk_live_...
# AWS_SECRET_ACCESS_KEY=...
```

Əgər image registry-yə push olunursa (Docker Hub public, kompromis olmuş private), **bütün secrets leak**.

### Image History

`docker history` hər layer-i göstərir:
```bash
docker history myapp:latest
```

Əgər `COPY . .` edibsinizsə, image history-də görünmür, amma image fayl sistemində qalır. `docker save myapp | tar -xv` ilə çıxarmaq olar.

### Düzgün həll

`.dockerignore`-da:
```gitignore
.env
.env.*
!.env.example
```

Build-də `.env` daxil olmur. Runtime-də container-ə env variable-ları göstər:

```yaml
services:
  app:
    image: myapp:v1
    env_file:
      - .env.production     # host-da, image-də yox
    environment:
      APP_ENV: production
```

K8s-də `Secret` obyekti kimi:
```yaml
envFrom:
  - secretRef:
      name: app-secrets
```

## Sıralanma və Prioritet

`.dockerignore` **top-down** oxunur, amma **axırıncı uyğun gələn qayda** qalib gəlir. Bu vacibdir negation üçün:

```gitignore
# Bütün log fayllarını exclude et
*.log

# Amma deployment.log lazımdır (nümunə üçün)
!deployment.log
```

Tərsi:
```gitignore
!deployment.log       # heç bir təsiri yoxdur
*.log                 # hər .log exclude olur, deployment.log da
```

## Tələlər (Gotchas)

### 1. `.dockerignore` yoxdur → build 5 dəqiqə çəkir

Tez quraşdırılmış Laravel layihədə developer `docker build` edir, 3-5 dəqiqə gözləyir, sonra şikayət edir. Kontekst 400 MB göndərilir. `.dockerignore` əlavə edildikdən sonra build 30 saniyə.

**Test:** `docker build` başlayanda ilk sətirlərə bax:
```
Sending build context to Docker daemon  412.5MB      <-- problem buradadır
```

### 2. `.env` image-ə düşdü, registry-yə push oldu

Real hadisə: startup Docker Hub public repo-ya image push edib, `.dockerignore`-da `.env` yox idi. 24 saat sonra AWS-də $40k charge. Bot-lar public registry-ləri scan edir.

**Qayda:** `.dockerignore`-u həmişə əvvəl yaz, sonra `Dockerfile`. Secret leak qorxusu ən vacibdir.

### 3. `.git` qovluğu image-ə düşür

50+ MB `.git` qovluğu image ölçüsünə əlavə olunur, üstəlik tarixçədə commit mesajları görünür (bəzən sensitive). 

```bash
docker run --rm myapp cat /var/www/html/.git/config
# [remote "origin"]
#     url = https://token:xxxxx@github.com/company/repo
```

Bəli, bəzi CI setup-larda token HTTPS url-də qalır.

**Həll:** `.git`-i həmişə `.dockerignore`-da.

### 4. `vendor/` ignore-lu amma dev-də lazımdır

```dockerfile
# dev Dockerfile
COPY . .
RUN composer install    # vendor/ yoxdur, yenidən qurur
```

Yaxşı işləyir — vendor build vaxtı yaranır. **Amma** developer `docker compose up` edəndə ekspektasiya var ki, `composer require` ilə əlavə etdiyi paket dərhal işləsin. Bu halda bind mount və anonymous volume lazımdır (42-ci faylda izah olunur).

### 5. `COPY tests/ ./tests/` amma `tests/` ignore-da

```dockerfile
COPY tests/ ./tests/      # error: tests not found
```

`.dockerignore` → `tests` olsa, `COPY tests/` fail edir. Yaxşı xəbər: build çökür, deploy olmur. Pis xəbər: dev-də belə problem olmur, yalnız CI-də görünür.

**Həll:** `tests/` yalnız prod-da exclude edilməlidir. Multi-stage-də:
- test stage-də `tests/` var (başqa Dockerfile, və ya `--target=test`)
- prod stage-də `tests/` yoxdur

### 6. `public/build` həm ignore, həm lazım

Vite build artefaktı. Developer local-da `npm run build` edib, `public/build/` yaranıb. `.dockerignore`-da `public/build` var → build image-ə düşmür. Amma multi-stage frontend stage olmadan yaranmır.

**Həll:** multi-stage build-də frontend stage `npm run build` etməlidir. Və ya `.dockerignore`-dan çıxar (amma o zaman host-dakı stale build düşə bilər).

### 7. `**/*.log` — nə baş verir?

```gitignore
**/*.log
```

Docker 20.10+ bunu dəstəkləyir. Köhnə versiyalarda yalnız single-level (`*.log`) işləyirdi. Əgər build fail edir, CI-də Docker versiyasını yoxla.

### 8. Symlink və `.dockerignore`

`storage -> /var/data/storage` symlink varsa, `.dockerignore`-da `storage` ignore olsa da, symlink özü copy olunur. Daxilindəki məzmun yox.

**Qayda:** symlink-ləri build context-dən tamamilə çıxar və ya image daxilində yarat.

## Best Practices

1. **`.dockerignore` Dockerfile-dan əvvəl yazılır.** Layihə başlanğıcında.
2. **`.env*` həmişə ignore-da.** İstisnaları `!` ilə aç (`!.env.example`).
3. **`.git` həmişə ignore-da.** Repo meta-sı image-də lazım deyil.
4. **Vendor qovluqlarını ignore et** (`vendor`, `node_modules`), multi-stage build-də yenidən qur.
5. **Test et:** `docker build` log-unda "transferring context" ölçüsünə bax.
6. **Documentation də ignore et** (`*.md`, `docs/`), istisna Docker-dəki README-lər.
7. **`.dockerignore`-u commit et.** `.gitignore`-dan fərqli, layihənin bir hissəsidir.
8. **Negation-a ehtiyatla yanaş.** Parent ignore olunubsa child `!` işləmir.

## Müsahibə Sualları

- **Q:** `.dockerignore` nəyə lazımdır?
  - Build context-dən faylları exclude etməyə: 1) build sürəti (daemon-a az məlumat göndərilir), 2) image ölçüsü (gərəksiz fayllar düşmür), 3) security (`.env` və `.git` image-ə sızmır).

- **Q:** `.dockerignore` və `.gitignore` eynidir?
  - Xeyr. Məqsədləri fərqlidir (git history vs build context), sintaksisləri oxşardır amma eyni deyil. `.dockerignore` yalnız root-dadır, `**` açıq yazılır, negation Docker-ə xasdır.

- **Q:** `.env` fayl image-ə düşsə nə olur?
  - Hər kim image-ə access edirsə secret görür: `docker run --rm image cat .env`. Əgər registry public-dirsə — prod secrets leak. Runtime-də env var-ları environment / secret olaraq inject et.

- **Q:** Build context ölçüsü build-ə necə təsir edir?
  - Daemon-a hər build-də bütün context tar-lanır göndərilir. 500 MB context 30-60 saniyə əlavə edir. `.dockerignore` ilə 5 MB-a düşürəndə build dərhal başlayır.

- **Q:** Multi-stage build-də `.dockerignore` hər stage-ə aiddir?
  - Bəli, bir dəfə tətbiq olunur, bütün stage-lər eyni context-ə malikdir. `COPY --from=stage` inter-stage, hər stage eyni host context-indən oxuyur.

- **Q:** `vendor/` `.dockerignore`-dadır — Composer stage necə işləyir?
  - Composer stage `COPY composer.json composer.lock` edir, sonra `composer install` — vendor image içində yaranır (host-dan gəlmir). Final stage `COPY --from=vendor /app/vendor`-u gətirir. Host-dakı stale vendor işə qarışmır.

- **Q:** `**/*.log` ilə `*.log` fərqi?
  - `*.log` — yalnız root-dakı .log fayllar. `**/*.log` — bütün qovluqlardakı. Laravel-də `storage/logs/laravel.log` `**/*.log` ilə tutulur.

- **Q:** `.dockerignore`-da negation (`!`) nə vaxt işləmir?
  - Parent qovluq tam ignore-dadır (`storage/**`), child `!storage/app/.gitkeep` tətbiq olunmur. Həll: hər səviyyəni açıq göstər (`storage/*`, sonra `!storage/app`, sonra `storage/app/*`, sonra `!storage/app/.gitkeep`).


## Əlaqəli Mövzular

- [dockerfile.md](02-dockerfile.md) — Dockerfile instruksiyaları
- [multi-stage-builds.md](04-multi-stage-builds.md) — Multi-stage builds
- [docker-optimization.md](11-docker-optimization.md) — Image optimallaşdırma
- [buildkit-advanced.md](29-buildkit-advanced.md) — BuildKit advanced features
