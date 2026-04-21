# Docker CI/CD with GitHub Actions for PHP/Laravel

## Nədir? (What is it?)

Docker image-in build olunması və registry-yə push olunması CI/CD-nin mərkəzidir. Əl ilə `docker build && docker push` — development-də yaxşıdır, amma production üçün:

- Hər commit-də avtomatik build + test
- Image-lər SHA və semver ilə taglanmalıdır
- Build cache (BuildKit + registry cache) 5-10x sürət verir
- Security scan (Trivy, Snyk) CI-də
- Signed image (Cosign) — supply chain security
- Multi-arch build (amd64 + arm64)
- Deploy trigger (K8s `kubectl rollout`, ArgoCD sync, SSH)

Bu fayl tam GitHub Actions workflow-u verir PHP/Laravel layihələr üçün — kopyala-istifadə et.

## Minimum Workflow — Build & Push

### `.github/workflows/docker-build.yml`

```yaml
name: Build & Push Docker Image

on:
  push:
    branches: [main]
    tags: ['v*']
  pull_request:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write       # ghcr.io-a push üçün
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4
      
      - name: Set up QEMU (multi-arch)
        uses: docker/setup-qemu-action@v3
      
      - name: Set up Buildx
        uses: docker/setup-buildx-action@v3
      
      - name: Login to GHCR
        if: github.event_name != 'pull_request'
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
            type=ref,event=branch
            type=ref,event=pr
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=sha,format=short,prefix=sha-
            type=raw,value=latest,enable={{is_default_branch}}
      
      - name: Build & Push
        uses: docker/build-push-action@v5
        with:
          context: .
          target: production
          platforms: linux/amd64,linux/arm64
          push: ${{ github.event_name != 'pull_request' }}
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
          build-args: |
            APP_VERSION=${{ github.sha }}
            BUILD_DATE=${{ github.event.head_commit.timestamp }}
```

### Metadata Tags Nəticəsi

`docker/metadata-action` avtomatik tag-lər yaradır:

| Commit növü | Tag-lər |
|-------------|---------|
| `git push main` | `latest`, `main`, `sha-abc1234` |
| `git push feature-branch` | `feature-branch`, `sha-abc1234` |
| `git push v1.2.3 tag` | `1.2.3`, `1.2`, `latest`, `sha-abc1234` |
| PR #42 | `pr-42`, `sha-abc1234` (push olmur) |

## Cache Strategiyaları

### GitHub Actions Cache (GHA)

```yaml
cache-from: type=gha
cache-to: type=gha,mode=max
```

- `mode=min` (default) — yalnız final image cache olunur
- `mode=max` — bütün multi-stage stage-lər cache olunur (vendor, frontend)

**Limitations:**
- 10 GB limit per repo
- Branch cache izole olunur (main cache PR-larda istifadə olunmur)

### Registry Cache

Image registry-də cache saxla — branch-lər arasında paylaşılır:

```yaml
cache-from: |
  type=registry,ref=ghcr.io/user/app:buildcache
cache-to: |
  type=registry,ref=ghcr.io/user/app:buildcache,mode=max
```

**Üstünlüklər:**
- Cache ölçü limiti yoxdur (registry-də saxlanılır)
- Branch-lər arası paylaşılır
- Yerli dev maşında `docker buildx build --cache-from ghcr.io/...` istifadə edilə bilər

### Inline Cache

Hər image öz cache-ini daşıyır:

```yaml
cache-from: type=registry,ref=ghcr.io/user/app:latest
cache-to: type=inline
```

Sadə, amma kiçik cache.

### BuildKit Mount Cache (Dockerfile-də)

Composer üçün (40-cı fayl):
```dockerfile
# syntax=docker/dockerfile:1.7
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_HOME=/tmp/composer-cache \
    composer install --no-dev --optimize-autoloader
```

NPM üçün:
```dockerfile
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund
```

BuildKit bu cache-i GHA və ya registry-də saxlayır — build-lər arasında composer/npm yükləməsi pulsuz.

## Test İş Axını

Build-dən əvvəl test-lər işləməlidir:

### `.github/workflows/test.yml`

```yaml
name: Test

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h localhost"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, bcmath, gd, intl, pdo_mysql, redis, zip
          coverage: xdebug
          tools: composer:v2
      
      - name: Composer cache
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress
      
      - name: Copy .env
        run: cp .env.testing .env
      
      - name: Generate key
        run: php artisan key:generate
      
      - name: Migrate
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
        run: php artisan migrate --force
      
      - name: Run tests
        env:
          DB_HOST: 127.0.0.1
          REDIS_HOST: 127.0.0.1
        run: php artisan test --coverage --min=80
      
      - name: Static analysis
        run: ./vendor/bin/phpstan analyse --memory-limit=1G
      
      - name: Code style
        run: ./vendor/bin/pint --test
```

### Test Docker Image İçində

Alternativ — image-də test et (eyni mühit dev-dev):

```yaml
      - name: Build test image
        uses: docker/build-push-action@v5
        with:
          context: .
          target: dev
          load: true         # Local Docker daemon-a yüklə
          tags: myapp:test
          cache-from: type=gha
      
      - name: Run tests in container
        run: |
          docker run --rm --network=host \
            -e DB_HOST=127.0.0.1 \
            -e REDIS_HOST=127.0.0.1 \
            myapp:test php artisan test
```

## Security Scan — Trivy

```yaml
      - name: Trivy vulnerability scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ghcr.io/${{ github.repository }}:sha-${{ github.sha }}
          format: sarif
          output: trivy-results.sarif
          severity: CRITICAL,HIGH
          exit-code: 1          # CI fail olsun CRITICAL tapılsa
      
      - name: Upload to GitHub Security
        if: always()
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: trivy-results.sarif
```

Trivy vulnerability-ləri GitHub Security tab-da göstərir. CI fail CRITICAL tapılanda.

## Image Signing — Cosign

Supply chain security (25-ci fayl):

```yaml
      - name: Install Cosign
        uses: sigstore/cosign-installer@v3
      
      - name: Sign image
        env:
          COSIGN_EXPERIMENTAL: "true"
        run: |
          cosign sign --yes \
            ghcr.io/${{ github.repository }}@${{ steps.build.outputs.digest }}
```

Keyless signing — GitHub OIDC ilə, key saxlamaq lazım deyil.

Verification (deploy vaxtı):
```bash
cosign verify \
  --certificate-identity-regexp="https://github.com/myorg/.*" \
  --certificate-oidc-issuer="https://token.actions.githubusercontent.com" \
  ghcr.io/myorg/myapp:latest
```

## SBOM — Software Bill of Materials

```yaml
      - name: Generate SBOM
        uses: anchore/sbom-action@v0
        with:
          image: ghcr.io/${{ github.repository }}:sha-${{ github.sha }}
          format: spdx-json
          output-file: sbom.spdx.json
      
      - name: Attach SBOM to image
        run: |
          cosign attest --yes \
            --predicate sbom.spdx.json \
            --type spdx \
            ghcr.io/${{ github.repository }}@${{ steps.build.outputs.digest }}
```

SBOM — image-dəki bütün paketlərin siyahısı. Gələcək CVE-ləri tracking etmək üçün vacib.

## Multi-Architecture Build (amd64 + arm64)

Apple Silicon, AWS Graviton üçün arm64 lazımdır. QEMU emulation ilə bir runner-dən hər iki arxitektura qurula bilər:

```yaml
      - name: Set up QEMU
        uses: docker/setup-qemu-action@v3
      
      - name: Set up Buildx
        uses: docker/setup-buildx-action@v3
      
      - name: Build multi-arch
        uses: docker/build-push-action@v5
        with:
          platforms: linux/amd64,linux/arm64
          # ...
```

**Qeyd:** arm64 build 3-5x yavaşdır (emulation). Native runner (Ubuntu arm64) istifadə etmək daha sürətlidir:

```yaml
jobs:
  build-amd64:
    runs-on: ubuntu-latest
    # ...
  
  build-arm64:
    runs-on: ubuntu-latest-arm64    # Native arm64
    # ...
  
  merge:
    needs: [build-amd64, build-arm64]
    runs-on: ubuntu-latest
    steps:
      - name: Create manifest
        run: |
          docker manifest create myapp:latest \
            myapp:amd64 \
            myapp:arm64
          docker manifest push myapp:latest
```

## Deploy Pipeline

### Deploy to VPS (SSH)

```yaml
  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: SSH deploy
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /srv/myapp
            export APP_VERSION=sha-${{ github.sha }}
            docker compose -f docker-compose.prod.yml pull
            docker compose -f docker-compose.prod.yml up -d --remove-orphans
            docker image prune -f
```

### Deploy to Kubernetes

```yaml
  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Configure kubectl
        uses: azure/k8s-set-context@v4
        with:
          kubeconfig: ${{ secrets.KUBECONFIG }}
      
      - name: Run migrations (Job)
        run: |
          sed "s|IMAGE_TAG|sha-${{ github.sha }}|" k8s/migrate-job.yaml | \
            kubectl apply -f -
          kubectl wait --for=condition=complete \
            --timeout=300s job/migrate-sha-${{ github.sha }}
      
      - name: Rollout deployment
        run: |
          kubectl set image deployment/laravel-app \
            app=ghcr.io/${{ github.repository }}:sha-${{ github.sha }}
          kubectl rollout status deployment/laravel-app --timeout=300s
      
      - name: Smoke test
        run: |
          curl -f https://myapp.com/health || exit 1
```

### Deploy with ArgoCD (GitOps)

Deploy əvəzinə commit image tag-i git repo-da dəyişdir:

```yaml
  update-gitops:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: Checkout gitops repo
        uses: actions/checkout@v4
        with:
          repository: myorg/gitops
          token: ${{ secrets.GITOPS_TOKEN }}
      
      - name: Update image tag
        run: |
          cd overlays/production
          kustomize edit set image \
            ghcr.io/myorg/myapp=ghcr.io/myorg/myapp:sha-${{ github.sha }}
      
      - name: Commit
        run: |
          git config user.email "ci@myorg.com"
          git config user.name "CI"
          git commit -am "Update myapp to sha-${{ github.sha }}"
          git push
      # ArgoCD avtomatik görür və sync edir
```

## Tam Production Workflow

```yaml
name: CI/CD

on:
  push:
    branches: [main]
    tags: ['v*']
  pull_request:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql: { image: mysql:8.0, env: { MYSQL_ROOT_PASSWORD: password, MYSQL_DATABASE: testing }, ports: [3306:3306], options: >- --health-cmd="mysqladmin ping" --health-interval=10s }
      redis: { image: redis:7-alpine, ports: [6379:6379] }
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, bcmath, gd, intl, pdo_mysql, redis, zip
      - run: composer install --prefer-dist --no-interaction
      - run: cp .env.testing .env && php artisan key:generate
      - env: { DB_HOST: 127.0.0.1, REDIS_HOST: 127.0.0.1 }
        run: |
          php artisan migrate --force
          php artisan test
          ./vendor/bin/phpstan analyse
          ./vendor/bin/pint --test
  
  build:
    needs: test
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
      id-token: write       # Cosign keyless
    outputs:
      image: ${{ steps.meta.outputs.tags }}
      digest: ${{ steps.build.outputs.digest }}
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-qemu-action@v3
      - uses: docker/setup-buildx-action@v3
      - uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      
      - id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=ref,event=branch
            type=semver,pattern={{version}}
            type=sha,format=short,prefix=sha-
            type=raw,value=latest,enable={{is_default_branch}}
      
      - id: build
        uses: docker/build-push-action@v5
        with:
          context: .
          target: production
          platforms: linux/amd64,linux/arm64
          push: ${{ github.event_name != 'pull_request' }}
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
      
      - name: Trivy scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}@${{ steps.build.outputs.digest }}
          severity: CRITICAL,HIGH
          exit-code: 1
      
      - uses: sigstore/cosign-installer@v3
      - env: { COSIGN_EXPERIMENTAL: "true" }
        run: cosign sign --yes ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}@${{ steps.build.outputs.digest }}
  
  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    environment:
      name: production
      url: https://myapp.com
    steps:
      - uses: azure/k8s-set-context@v4
        with:
          kubeconfig: ${{ secrets.KUBECONFIG }}
      
      - name: Run migration
        run: |
          kubectl create job --from=cronjob/migrate migrate-${{ github.sha }}
          kubectl wait --for=condition=complete --timeout=300s job/migrate-${{ github.sha }}
      
      - name: Update deployment
        run: |
          kubectl set image deployment/laravel-app \
            app=${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:sha-${{ github.sha }}
          kubectl rollout status deployment/laravel-app --timeout=300s
      
      - name: Smoke test
        run: |
          sleep 10
          curl -fsS https://myapp.com/health
```

## Speed Optimization — 10 dəqiqədən 2 dəqiqəyə

### Əvvəl (optimize olmamış)
- Composer install: 90s
- NPM ci + build: 60s
- Docker build: 120s
- Push: 60s
- **Total: ~5-7 dəq**

### Sonra (cache + parallel)
- Composer install (BuildKit cache): 10s
- NPM ci (GHA cache): 15s
- Docker build (layer cache): 30s
- Push (multi-arch parallel): 20s
- **Total: ~1.5-2 dəq**

### Açarlar

1. **BuildKit mount cache** composer/npm üçün
2. **GHA cache** Docker layer-lər üçün
3. **Paralel job-lar** (test, build, scan)
4. **Metadata caching** (`actions/cache` composer-vendor üçün test job-da)
5. **Docker layer ordering** (ən az dəyişən layer-lər əvvəldə)

## Tipik Səhvlər (Gotchas)

### 1. `GITHUB_TOKEN` permissions

GHCR-ə push fail:
```
denied: permission_denied: write_package
```

**Həll:**
```yaml
permissions:
  packages: write
```

Və ya repo settings → Actions → Workflow permissions → "Read and write".

### 2. Cache invalidate olmur

Hər dəyişiklikdə tam rebuild.

**Həll:** BuildKit aktiv olduğunu yoxla. `cache-from` və `cache-to` düzgün yazılıb? Dockerfile-da layer ordering — kod `COPY . .`-dan əvvəl composer install.

### 3. PR-larda push

Fork-dan PR-lar registry-yə push edə bilməz (secret yoxdur).

**Həll:** `push: ${{ github.event_name != 'pull_request' }}` — PR-larda yalnız build + test.

### 4. Secret exposure

`ARG` image history-də qalır:
```dockerfile
ARG GITHUB_TOKEN    # docker history-də görünür!
```

**Həll:** BuildKit secret mount:
```yaml
- uses: docker/build-push-action@v5
  with:
    secrets: |
      github_token=${{ secrets.GITHUB_TOKEN }}
```
```dockerfile
RUN --mount=type=secret,id=github_token \
    TOKEN=$(cat /run/secrets/github_token) composer install
```

### 5. Multi-arch yavaş

QEMU emulation arm64-ü 5x yavaşdır.

**Həll:** Matrix build, hər arxitektura native runner-də.

### 6. Test DB connection

```
SQLSTATE[HY000] [2002] Connection refused
```

Service-container hazır olmadan test başlayır.

**Həll:** `health-cmd` + `health-retries` service-də, və `wait` əmri:
```bash
- run: |
    until mysqladmin ping -h 127.0.0.1 -u root -ppassword; do
      sleep 1
    done
```

### 7. Image tag `latest` konfuzya

`latest` dinamikdir — production-da `latest` istifadə etmə. SHA tag-i istifadə et:
```bash
kubectl set image deployment/app app=myapp:sha-abc1234
```

`latest` yalnız development və quick smoke test üçün.

### 8. Deploy job secret scopes

Production deployment secret-ləri yalnız production environment-da olmalıdır:

```yaml
deploy:
  environment:
    name: production       # GitHub environment protection rules
```

## Interview sualları

- **Q:** Docker image-i CI-də necə tag edirsiniz?
  - `docker/metadata-action` ilə: SHA short (`sha-abc1234`), branch name, semver (`1.2.3`, `1.2`, `latest`), PR number. Production-da hər zaman SHA tag istifadə et — `latest` dinamikdir.

- **Q:** Build-in sürəti necə artırırsınız?
  - BuildKit cache mount (composer, npm), GHA cache (Docker layers), Dockerfile layer ordering (composer.json-u kod-dan əvvəl kopyala), paralel job-lar (test + build).

- **Q:** Secret-ləri CI-də necə qoruyursunuz?
  - GitHub Secrets → `${{ secrets.NAME }}`. Environment-based secrets (production üçün ayrı). BuildKit `--mount=type=secret` image history-də qalmasın. `ARG GITHUB_TOKEN` istifadə etmə.

- **Q:** Image signing niyə lazımdır?
  - Supply chain security — deploy vaxtı image-in CI-də qurulduğunu sübut edir. Cosign + OIDC keyless signing — heç bir key saxlamaq lazım deyil. Deployment-də `cosign verify` ilə yoxlayın.

- **Q:** Migration-ı CI/CD-də necə icra edirsiniz?
  - K8s Job ilə: `kubectl create job --from=cronjob/migrate` → deploy-dan əvvəl. Rollback olanda Job avtomatik geri qaytarmaz (amma migration-ları reversible yaz).

- **Q:** Multi-arch build — niyə və necə?
  - ARM64 hazırda production-da (AWS Graviton, Apple M-series dev). `docker/setup-qemu-action` ilə emulation, və ya native arm64 runner-də matrix build (3-5x daha sürətli).

- **Q:** Trivy CRITICAL tapırsa?
  - Build fail et (`exit-code: 1`). Developer CVE-ni yoxlayır — base image yenilə (`FROM php:8.3-fpm-alpine` → yeni digest), paketi update et, və ya CVE irrelevant-dirsə `.trivyignore`-a əlavə et.
