# Docker Registry (Docker Reyestri)

## Nədir? (What is it?)

Docker Registry — Docker image-lərini saxlamaq və paylamaq üçün istifadə olunan servisdur. Registry image-lərin mərkəzləşdirilmiş anbarıdır. Docker Hub ən məşhur public registry-dir, lakin şirkətlər adətən private registry istifadə edir.

Registry olmadan Docker image-lərini başqa maşınlara köçürmək çətin olardı. Registry `docker push` və `docker pull` əmrləri ilə işləyir.

## Əsas Konseptlər (Key Concepts)

### 1. Docker Hub

Docker Hub — Docker-un rəsmi public registry-sidir.

```bash
# Docker Hub-a login
docker login
# Username: myuser
# Password: ****

# Image-i tag-ləmək (Docker Hub formatı)
docker tag myapp:latest myuser/myapp:1.0.0

# Image-i push etmək
docker push myuser/myapp:1.0.0

# Image-i pull etmək
docker pull myuser/myapp:1.0.0

# Docker Hub-dan axtarış
docker search php
```

**Docker Hub planları:**

| Plan | Private Repo | Pull Rate Limit |
|------|-------------|-----------------|
| Free | 1 | 100 pull/6 saat |
| Pro | ∞ | 5000 pull/gün |
| Team | ∞ | 5000 pull/gün (hər user) |
| Business | ∞ | Limitsiz |

### 2. Private Registry (Self-hosted)

```bash
# Docker Registry-ni local işlətmək
docker run -d \
    --name registry \
    -p 5000:5000 \
    -v registry-data:/var/lib/registry \
    registry:2

# Local registry-ə push etmək
docker tag myapp:latest localhost:5000/myapp:1.0.0
docker push localhost:5000/myapp:1.0.0

# Pull etmək
docker pull localhost:5000/myapp:1.0.0

# Registry-dəki image-ləri siyahılamaq
curl http://localhost:5000/v2/_catalog
# {"repositories":["myapp"]}

# Image tag-lərini görmək
curl http://localhost:5000/v2/myapp/tags/list
# {"name":"myapp","tags":["1.0.0","latest"]}
```

**TLS ilə Private Registry:**

```yaml
# docker-compose.yml
services:
  registry:
    image: registry:2
    ports:
      - "5000:5000"
    volumes:
      - ./certs:/certs
      - registry-data:/var/lib/registry
    environment:
      REGISTRY_HTTP_TLS_CERTIFICATE: /certs/domain.crt
      REGISTRY_HTTP_TLS_KEY: /certs/domain.key
      REGISTRY_AUTH: htpasswd
      REGISTRY_AUTH_HTPASSWD_REALM: Registry Realm
      REGISTRY_AUTH_HTPASSWD_PATH: /auth/htpasswd

volumes:
  registry-data:
```

### 3. Cloud Registry-lər

#### Amazon ECR (Elastic Container Registry)

```bash
# AWS CLI ilə ECR-ə login
aws ecr get-login-password --region eu-west-1 | \
    docker login --username AWS --password-stdin \
    123456789.dkr.ecr.eu-west-1.amazonaws.com

# Repository yaratmaq
aws ecr create-repository --repository-name myapp

# Tag və push
docker tag myapp:latest \
    123456789.dkr.ecr.eu-west-1.amazonaws.com/myapp:1.0.0
docker push \
    123456789.dkr.ecr.eu-west-1.amazonaws.com/myapp:1.0.0
```

**ECR Lifecycle Policy (köhnə image-ləri avtomatik silmək):**

```json
{
  "rules": [
    {
      "rulePriority": 1,
      "description": "Keep last 10 images",
      "selection": {
        "tagStatus": "any",
        "countType": "imageCountMoreThan",
        "countNumber": 10
      },
      "action": {
        "type": "expire"
      }
    }
  ]
}
```

#### Google GCR / Artifact Registry

```bash
# GCR-ə login
gcloud auth configure-docker

# Tag və push
docker tag myapp:latest gcr.io/my-project/myapp:1.0.0
docker push gcr.io/my-project/myapp:1.0.0

# Artifact Registry (yeni)
gcloud auth configure-docker europe-west1-docker.pkg.dev
docker tag myapp:latest \
    europe-west1-docker.pkg.dev/my-project/my-repo/myapp:1.0.0
```

#### Azure ACR (Azure Container Registry)

```bash
# ACR yaratmaq
az acr create --name myregistry --resource-group mygroup --sku Basic

# Login
az acr login --name myregistry

# Tag və push
docker tag myapp:latest myregistry.azurecr.io/myapp:1.0.0
docker push myregistry.azurecr.io/myapp:1.0.0
```

### 4. Tagging Strategiyaları

```bash
# Semantic Versioning (SemVer)
docker tag myapp:latest myuser/myapp:1.0.0
docker tag myapp:latest myuser/myapp:1.0
docker tag myapp:latest myuser/myapp:1

# Git commit hash ilə
docker tag myapp:latest myuser/myapp:$(git rev-parse --short HEAD)
# myuser/myapp:a1b2c3d

# Git branch + commit
docker tag myapp:latest myuser/myapp:main-a1b2c3d

# Tarix ilə
docker tag myapp:latest myuser/myapp:20260416

# Environment ilə
docker tag myapp:latest myuser/myapp:staging
docker tag myapp:latest myuser/myapp:production
```

**Tagging best practice-ləri:**

```
┌─────────────────────────────────────────────────────────────┐
│ Tag Strategiyası                                            │
├─────────────────────────────────────────────────────────────┤
│ ✅ Hər build-ə unikal tag verin (SemVer, git hash)        │
│ ✅ latest tag-ı development üçün istifadə edin             │
│ ❌ Produksiyada latest tag istifadə ETMƏYİN               │
│ ✅ Immutable tag-lər istifadə edin (1.0.0, git hash)      │
│ ❌ Eyni tag-ı fərqli image-lər üçün istifadə etməyin      │
│ ✅ Multi-level tag-lər: 1.0.0, 1.0, 1                     │
└─────────────────────────────────────────────────────────────┘
```

### 5. Image Versioning Strategiyası

```
Production release flow:
  develop → staging → production

Image tags:
  myapp:dev-a1b2c3d      (develop branch)
  myapp:staging-a1b2c3d   (staging-ə deploy)
  myapp:1.2.3             (production release)
  myapp:1.2               (minor version alias)
  myapp:1                 (major version alias)
```

## Praktiki Nümunələr (Practical Examples)

### CI/CD Pipeline-da Registry İstifadəsi

**GitHub Actions nümunəsi:**

```yaml
# .github/workflows/docker.yml
name: Build and Push Docker Image

on:
  push:
    branches: [main]
    tags: ['v*']

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=sha,prefix=

      - name: Build and Push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

### GitLab CI nümunəsi

```yaml
# .gitlab-ci.yml
build:
  stage: build
  image: docker:24
  services:
    - docker:24-dind
  variables:
    DOCKER_IMAGE: $CI_REGISTRY_IMAGE
  before_script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
  script:
    - docker build -t $DOCKER_IMAGE:$CI_COMMIT_SHA .
    - docker tag $DOCKER_IMAGE:$CI_COMMIT_SHA $DOCKER_IMAGE:latest
    - docker push $DOCKER_IMAGE:$CI_COMMIT_SHA
    - docker push $DOCKER_IMAGE:latest
```

### Image-i Registry-lər Arasında Köçürmək

```bash
# Docker Hub-dan ECR-ə köçürmək
docker pull myuser/myapp:1.0.0
docker tag myuser/myapp:1.0.0 \
    123456789.dkr.ecr.eu-west-1.amazonaws.com/myapp:1.0.0
docker push 123456789.dkr.ecr.eu-west-1.amazonaws.com/myapp:1.0.0

# skopeo ilə (Docker daemon-sız)
skopeo copy \
    docker://docker.io/myuser/myapp:1.0.0 \
    docker://123456789.dkr.ecr.eu-west-1.amazonaws.com/myapp:1.0.0
```

## PHP/Laravel ilə İstifadə (Usage with PHP/Laravel)

### Laravel Image-ini Registry-ə Push Etmək

```bash
# Build
docker build -t mycompany/laravel-app:1.0.0 \
    -f docker/Dockerfile.production .

# Test (push etmədən əvvəl)
docker run --rm mycompany/laravel-app:1.0.0 php artisan --version

# Push
docker push mycompany/laravel-app:1.0.0
```

### Private Composer Packages üçün Build

```dockerfile
# syntax=docker/dockerfile:1.4
FROM composer:2.7 AS deps

WORKDIR /app
COPY composer.json composer.lock ./

# Private Composer repo üçün auth (secret mount ilə)
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json \
    composer install --no-dev --prefer-dist

FROM php:8.3-fpm-alpine
COPY --from=deps /app/vendor /var/www/html/vendor
COPY . /var/www/html
```

```bash
# Build əmri (auth.json secret olaraq verilir)
docker build \
    --secret id=composer_auth,src=auth.json \
    -t mycompany/laravel-app:1.0.0 .
```

### Multi-Architecture Build

```bash
# ARM və AMD64 üçün eyni image
docker buildx create --name multiarch --use
docker buildx build \
    --platform linux/amd64,linux/arm64 \
    -t mycompany/laravel-app:1.0.0 \
    --push .
```

## Interview Sualları (Interview Questions)

### S1: Docker Hub ilə private registry arasında fərq nədir?
**C:** Docker Hub — public, Docker şirkəti tərəfindən idarə olunan registry-dir. Free planda pull rate limit var. Private registry (self-hosted və ya cloud) — şirkətin öz nəzarətindədir, data şirkətin infrastrukturunda saxlanır, rate limit yoxdur, güclü access control var. Produksiya mühitlərində adətən private registry (ECR, GCR, ACR) istifadə olunur.

### S2: Niyə produksiyada `latest` tag istifadə etməmək lazımdır?
**C:** `latest` mutable tag-dir — hər push-da dəyişir. Rollback çətinləşir (hansı versiyanın deploy olunduğu bilinmir), reproducibility itirilir, caching problemləri yaranır (Kubernetes `latest` üçün hər dəfə pull edə bilər). Əvəzinə SemVer (1.0.0) və ya git hash (a1b2c3d) kimi immutable tag-lər istifadə edin.

### S3: ECR lifecycle policy nə üçün lazımdır?
**C:** Registry-də köhnə, istifadə olunmayan image-lər yığılır və storage xərci artır. Lifecycle policy avtomatik olaraq köhnə image-ləri silir — məsələn, "son 10 image-i saxla, qalanlarını sil" və ya "30 gündən köhnə untagged image-ləri sil".

### S4: Multi-architecture image nədir?
**C:** Eyni tag altında fərqli CPU arxitekturaları (amd64, arm64) üçün build olunmuş image. `docker buildx` ilə yaradılır. Docker pull zamanı avtomatik olaraq düzgün arxitektura seçilir. Bu, Apple Silicon (M1/M2) və ARM-based serverlər üçün vacibdir.

### S5: Image-i registry-lər arasında necə köçürmək olar?
**C:** İki yol var: 1) `docker pull` + `docker tag` + `docker push` — Docker daemon-dan keçir, disk istifadə edir. 2) `skopeo copy` — Docker daemon-sız, birbaşa registry-lər arasında kopyalayır, daha sürətlidir.

### S6: Docker content trust nədir?
**C:** Docker Content Trust (DCT) image-lərin imzalanması və doğrulanması mexanizmidir. `DOCKER_CONTENT_TRUST=1` ilə aktivləşdirilir. Push zamanı image imzalanır, pull zamanı imza yoxlanır. Man-in-the-middle hücumlarının qarşısını alır.

### S7: GitHub Container Registry (ghcr.io) nə üstünlük verir?
**C:** GitHub repo ilə inteqrasiya, GITHUB_TOKEN ilə avtomatik auth, GitHub Actions-da sürətli access, repo permissions ilə uyğunlaşma, public image-lər üçün limitsiz pull. GitHub ekosistemindən istifadə edən komandalar üçün əlverişlidir.

## Best Practices

1. **Produksiyada immutable tag-lər istifadə edin** — SemVer, git hash
2. **`latest` yalnız development üçün** — produksiyada istifadə etməyin
3. **Private registry istifadə edin** — produksiya image-ləri üçün
4. **Lifecycle/retention policy qurun** — köhnə image-ləri avtomatik silin
5. **Image scanning aktiv edin** — vulnerability scan (Trivy, Snyk)
6. **Multi-arch build edin** — ARM serverləri artır
7. **CI/CD-dən push edin** — manual push etməyin
8. **Registry auth-u credential helper ilə idarə edin** — password-u plain text saxlamayın
9. **Image-ləri imzalayın** — Docker Content Trust və ya cosign
10. **Pull rate limit-ə diqqət edin** — Docker Hub free plan-da 100/6 saat
