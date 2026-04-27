# CI/CD Pipeline Design (Senior ⭐⭐⭐)

## İcmal
CI/CD pipeline — kod dəyişikliyindən başlayaraq production deploy-a qədər olan prosesin avtomatlaşdırılmasıdır. CI (Continuous Integration) hər commit-i avtomatik test edir; CD (Continuous Delivery/Deployment) isə produksiyaya çatdırır. Düzgün qurulmuş pipeline developer produktivliyinin əsasıdır. Senior interview-larda bu mövzu "pipeline-ınızı necə qurarsınız?" şəklində gəlir.

## Niyə Vacibdir
CI/CD yalnız DevOps mövzusu deyil — hər Senior developer-in başa düşməli olduğu infrastructure-dır. Pipeline-ı düzgün qurmaq deployment riskini azaldır, developer feedback loop-unu sürətləndirir, human error-u minimuma endirir. "Manual deploy edirik" deyən şirkətdə risk hər deployda artır. Bu anlayış backend engineer-in "infrastructure ownership" düşüncəsini göstərir.

## Əsas Anlayışlar

### CI vs CD Fərqi:

**Continuous Integration (CI):**
- Hər commit/PR üzərində avtomatik: build, test, lint, security scan
- Məqsəd: Broken code-u tez aşkar etmək
- Sürət kritikdir: 5-10 dəqiqə içində feedback

**Continuous Delivery (CD — Delivery):**
- CI keçdikdən sonra artifact production-a hazır olur
- Deploy manualdır (bir düymə ilə)
- Niyə manual? Regulated industry (banking, health) ya da risk management

**Continuous Deployment (CD — Deployment):**
- CI keçdikdən sonra avtomatik production deploy
- Human intervention yoxdur
- Tez dəyişən, yüksək test confidence olan sistemlər üçün

---

### Pipeline Stages:

```
Commit
  │
  ▼
[Stage 1] Source Control Trigger
  - Git hook / webhook
  - Branch filter (feature/*, main, release/*)

[Stage 2] Build
  - Dependency install (composer install, npm ci)
  - Asset compilation
  - Docker image build

[Stage 3] Static Analysis (Fast Gate)
  - Linting (PHP-CS-Fixer, ESLint)
  - Static analysis (PHPStan level 8, Psalm)
  - Secret scanning (Gitleaks, TruffleHog)

[Stage 4] Unit Tests
  - PHPUnit unit suite
  - Coverage threshold check

[Stage 5] Integration Tests
  - Feature/integration tests
  - Database migrations
  - Contract verification

[Stage 6] Security Scan
  - SAST (Static Application Security Testing)
  - Dependency vulnerability scan (composer audit)
  - Container scan (Trivy)

[Stage 7] Build Artifact
  - Docker image tag + push to registry
  - Version tagging

[Stage 8] Deploy to Staging
  - Automated deploy
  - Smoke tests

[Stage 9] E2E Tests (Staging)
  - Critical user journey tests

[Stage 10] Deploy to Production
  - Manual gate (CD Delivery) ya da avtomatik (CD Deployment)
  - Blue-Green / Canary deployment
  - Health check
```

---

### GitHub Actions Nümunəsi:

```yaml
# .github/workflows/ci-cd.yml
name: CI/CD Pipeline

on:
  push:
    branches: [main, 'release/*']
  pull_request:
    branches: [main]

jobs:
  # STAGE 1: Fast feedback (2-3 dəqiqə)
  lint-and-static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Cache composer
        uses: actions/cache@v3
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install dependencies
        run: composer install --no-dev --prefer-dist

      - name: PHP-CS-Fixer
        run: vendor/bin/php-cs-fixer check --diff

      - name: PHPStan
        run: vendor/bin/phpstan analyse src --level=8

      - name: Security audit
        run: composer audit

  # STAGE 2: Unit tests (2-4 dəqiqə)
  unit-tests:
    needs: lint-and-static-analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: pcov

      - run: composer install

      - name: Run unit tests with coverage
        run: vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage.xml

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: coverage.xml
          fail_ci_if_error: true

  # STAGE 3: Integration tests (5-10 dəqiqə)
  integration-tests:
    needs: unit-tests
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: test_db
          POSTGRES_PASSWORD: secret
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-retries 5

      redis:
        image: redis:7
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - run: composer install

      - name: Setup test environment
        run: |
          cp .env.testing .env
          php artisan key:generate
          php artisan migrate --force

      - name: Run integration tests
        run: vendor/bin/phpunit --testsuite=integration

  # STAGE 4: Build & Push Docker image (yalnız main)
  build-and-push:
    needs: integration-tests
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    outputs:
      image-tag: ${{ steps.meta.outputs.tags }}

    steps:
      - uses: actions/checkout@v4

      - name: Docker meta
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ github.repository }}
          tags: |
            type=sha,prefix=,suffix=,format=short

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          push: true
          tags: ${{ steps.meta.outputs.tags }}

  # STAGE 5: Deploy to staging
  deploy-staging:
    needs: build-and-push
    runs-on: ubuntu-latest
    environment: staging
    steps:
      - name: Deploy to staging
        run: |
          kubectl set image deployment/app \
            app=ghcr.io/${{ github.repository }}:${{ needs.build-and-push.outputs.image-tag }}
          kubectl rollout status deployment/app

      - name: Smoke test
        run: curl -f https://staging.example.com/health

  # STAGE 6: Production deploy (manual approval)
  deploy-production:
    needs: deploy-staging
    runs-on: ubuntu-latest
    environment:
      name: production  # GitHub-da "Required reviewers" konfiqurasiyası
    steps:
      - name: Deploy to production
        run: |
          kubectl set image deployment/app \
            app=ghcr.io/${{ github.repository }}:${{ needs.build-and-push.outputs.image-tag }}
          kubectl rollout status deployment/app --timeout=300s
```

---

### Deployment Strategies:

**Blue-Green Deployment:**
- İki eyni mühit: Blue (canlı), Green (yeni version)
- Traffic switch etmək: anında ya da progressiv
- Rollback: traffic-i Blue-ya qaytarmaq — saniyələr içində

**Canary Deployment:**
- Yeni version əvvəlcə küçük % traffic alır (məs: 5%)
- Metriklər izlənir: error rate, latency
- Problemsizdirsə 100%-ə qalxdırılır
- Laravel/PHP-də: Nginx upstream weight ya da Kubernetes canary

**Rolling Deployment:**
- Pod-lar ardıcıl olaraq yenilənir
- Zero-downtime, amma rollback yavaşdır
- Kubernetes default deployment strategiyası

---

### Pipeline Optimizasiya:

**Cache strategy:**
```yaml
- uses: actions/cache@v3
  with:
    path: |
      vendor
      ~/.composer/cache
    key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}
    restore-keys: |
      ${{ runner.os }}-composer-
```

**Parallel jobs:**
```yaml
# Unit test + lint eyni vaxtda
lint-and-unit:
  strategy:
    matrix:
      job: [lint, unit-tests, security-scan]
```

**Conditional stages:**
```yaml
# E2E yalnız main branch-də
deploy-e2e:
  if: github.ref == 'refs/heads/main' && github.event_name == 'push'
```

---

### Deployment Health Checks:

```bash
# Deploy sonrası health check
kubectl rollout status deployment/app --timeout=120s

# Readiness probe (Kubernetes)
readinessProbe:
  httpGet:
    path: /health
    port: 80
  initialDelaySeconds: 10
  periodSeconds: 5
  failureThreshold: 3
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"CI/CD pipeline-ınızı necə qurarsınız?" sualına yalnız "GitHub Actions istifadə edirəm" demə. Stage-ləri, fast-fail strategiyasını, deployment strategiyasını (Blue-Green, Canary), health check-ləri izah et. "Niyə bu quruluş?" sualına hazır ol.

**Follow-up suallar:**
- "Deployment uğursuz olduqda rollback necə edirsiniz?"
- "Zero-downtime deploy necə təmin edirsiz?"
- "Canary vs Blue-Green — nə vaxt hansını seçirsiniz?"

**Ümumi səhvlər:**
- Build artifact-ı hər stage-də yenidən build etmək (cache yoxdur)
- Deployment health check olmadan pipeline-ı tamamlanmış saymaq
- Staging-i skip etmək — birbaşa production deploy
- Secrets-i environment variable-dan deyil, kod içinə hardcode etmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Stage sırası + niyə bu sıra (fast-fail), artifact reuse, deployment strategy seçimi kriteriyaları, rollback planı — bunları birlikdə izah etmək əla cavabdır.

## Nümunələr

### Tipik Interview Sualı
"Yeni startup-da CI/CD pipeline-ı əvvəldən qurmağınız lazımdır. Haradan başlayarsınız?"

### Güclü Cavab
"Əvvəlcə üç şeyi müəyyənləşdirərəm: team ölçüsü, deployment tezliyi hədəfi, risk toleransı. Kiçik team üçün GitHub Actions ilə başlardım: lint → unit test → integration test → Docker build → staging deploy. Production deploy üçün əvvəlcə manual gate qoyardım — confidence artdıqca avtomatlaşdırardım. Deployment strategiyası olaraq rolling deployment başlanğıc üçün yetərlidir; traffic artdıqca canary deploy keçərdim. Əsas focus: hər commit-dən 10 dəqiqə içində feedback, staging-dən production-a promotion bir düymə ilə."

## Praktik Tapşırıqlar
- GitHub Actions ilə tam CI pipeline yaz: lint → unit → integration
- Docker image build + push to GHCR əlavə et
- Staging environment-ə deploy stage əlavə et
- `environment: production` ilə manual approval gate qur

## Əlaqəli Mövzular
- [09-testing-in-cicd.md](../11-testing/09-testing-in-cicd.md) — CI-da test strategiyası
- [02-container-orchestration.md](02-container-orchestration.md) — Kubernetes deployment
- [10-gitops.md](10-gitops.md) — GitOps ilə CD yanaşması
- [07-incident-response.md](07-incident-response.md) — Deploy sonrası problem — incident response
