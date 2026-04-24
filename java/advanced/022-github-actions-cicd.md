# 022 — GitHub Actions Java CI/CD — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [CI/CD nədir?](#cicd-nədir)
2. [GitHub Actions əsasları](#github-actions-əsasları)
3. [Java CI pipeline](#java-ci-pipeline)
4. [Docker build & push](#docker-build--push)
5. [Kubernetes deployment](#kubernetes-deployment)
6. [Advanced patterns](#advanced-patterns)
7. [İntervyu Sualları](#intervyu-sualları)

---

## CI/CD nədir?

```
CI — Continuous Integration:
  → Kod push edildikdə avtomatik build + test
  → Hər developer-in kodu erkən inteqrasiya olunur
  → Xətalar tez aşkarlanır

CD — Continuous Delivery/Deployment:
  Delivery: Test mühitinə avtomatik deploy, prod manual
  Deployment: Prod-a da avtomatik deploy

CI/CD faydaları:
  ✅ Manual process yoxdur ("ələ deploy" artıq yoxdur)
  ✅ Tez feedback (push → 5 dəq içində test nəticəsi)
  ✅ Reproducible build (hər yerdə eyni nəticə)
  ✅ Audit trail (kim, nə vaxt, nə deploy etdi)
  ✅ Rollback asandır

Populyar CI/CD alətləri:
  GitHub Actions  → GitHub ilə sıx inteqrasiya, pulsuz
  GitLab CI/CD   → Self-hosted mümkün
  Jenkins        → Köhnə, amma çevik
  CircleCI       → Sürətli, pulsuz tier
  ArgoCD         → Kubernetes GitOps
  Tekton         → Kubernetes-native
```

---

## GitHub Actions əsasları

```yaml
# .github/workflows/ci.yml

# Workflow strukturu:
#   on: — nə vaxt işə düşür?
#   jobs: — paralel ya da ardıcıl işlər
#     steps: — hər job-dakı addımlar

name: CI

on:
  push:                          # Push zamanı
    branches: [main, develop]
  pull_request:                  # PR zamanı
    branches: [main]
  schedule:                      # Cron (hər gecə)
    - cron: '0 2 * * *'
  workflow_dispatch:             # Manual trigger

jobs:
  build:
    runs-on: ubuntu-latest       # Runner

    # Matrix build — çox Java versiyasında test
    strategy:
      matrix:
        java-version: [17, 21]

    steps:
      # ─── Step 1: Kod al ──────────────────────────────
      - name: Checkout
        uses: actions/checkout@v4

      # ─── Step 2: JDK quraşdır ────────────────────────
      - name: Set up JDK ${{ matrix.java-version }}
        uses: actions/setup-java@v4
        with:
          java-version: ${{ matrix.java-version }}
          distribution: 'temurin'
          cache: 'maven'    # Maven dependency cache

      # ─── Step 3: Build ───────────────────────────────
      - name: Build with Maven
        run: mvn clean verify

      # ─── Step 4: Test nəticəsi ────────────────────────
      - name: Publish test results
        uses: EnricoMi/publish-unit-test-result-action@v2
        if: always()
        with:
          files: target/surefire-reports/*.xml

      # ─── Step 5: Artifact saxla ──────────────────────
      - name: Upload build artifact
        uses: actions/upload-artifact@v4
        with:
          name: myapp-jar-java${{ matrix.java-version }}
          path: target/myapp-*.jar
          retention-days: 7
```

---

## Java CI pipeline

```yaml
# .github/workflows/java-ci.yml — Tam CI pipeline

name: Java CI Pipeline

on:
  push:
    branches: [main, 'feature/**', 'release/**']
  pull_request:
    branches: [main, develop]

env:
  JAVA_VERSION: '21'
  MAVEN_OPTS: '-Xmx1024m'

jobs:
  # ─── 1. Kod keyfiyyəti yoxlaması ────────────────────────
  code-quality:
    name: Code Quality
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0    # SonarCloud üçün tarix lazımdır

      - uses: actions/setup-java@v4
        with:
          java-version: ${{ env.JAVA_VERSION }}
          distribution: temurin
          cache: maven

      - name: Checkstyle
        run: mvn checkstyle:check

      - name: SpotBugs
        run: mvn spotbugs:check

      - name: SonarCloud Analysis
        run: mvn sonar:sonar
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
        continue-on-error: true   # Sonar fail → workflow dayandırmır

  # ─── 2. Unit Tests ──────────────────────────────────────
  unit-tests:
    name: Unit Tests
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-java@v4
        with:
          java-version: ${{ env.JAVA_VERSION }}
          distribution: temurin
          cache: maven

      - name: Run unit tests
        run: mvn test -Punit-tests

      - name: JaCoCo coverage report
        run: mvn jacoco:report

      - name: Coverage check (minimum 80%)
        run: mvn jacoco:check

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v4
        with:
          files: target/site/jacoco/jacoco.xml
          token: ${{ secrets.CODECOV_TOKEN }}

  # ─── 3. Integration Tests ───────────────────────────────
  integration-tests:
    name: Integration Tests
    runs-on: ubuntu-latest
    needs: unit-tests          # Unit test keçmədən başlamır

    services:                  # Docker service-lər (Testcontainers əvəzi)
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: testdb
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

      redis:
        image: redis:7
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-java@v4
        with:
          java-version: ${{ env.JAVA_VERSION }}
          distribution: temurin
          cache: maven

      - name: Run integration tests
        run: mvn verify -Pintegration-tests
        env:
          SPRING_DATASOURCE_URL: jdbc:postgresql://localhost:5432/testdb
          SPRING_DATASOURCE_USERNAME: test
          SPRING_DATASOURCE_PASSWORD: test
          SPRING_DATA_REDIS_HOST: localhost

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: integration-test-results
          path: target/failsafe-reports/

  # ─── 4. Security Scan ───────────────────────────────────
  security-scan:
    name: Security Scan
    runs-on: ubuntu-latest
    needs: unit-tests
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-java@v4
        with:
          java-version: ${{ env.JAVA_VERSION }}
          distribution: temurin
          cache: maven

      - name: OWASP Dependency Check
        run: mvn dependency-check:check
        env:
          NVD_API_KEY: ${{ secrets.NVD_API_KEY }}

      - name: Upload security report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: dependency-check-report
          path: target/dependency-check-report.html
```

---

## Docker build & push

```yaml
# .github/workflows/docker-cd.yml

name: Docker Build & Deploy

on:
  push:
    branches: [main]
    tags: ['v*.*.*']

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build-and-push:
    name: Build & Push Docker Image
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
      security-events: write

    outputs:
      image-tag: ${{ steps.meta.outputs.version }}
      image-digest: ${{ steps.build.outputs.digest }}

    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-java@v4
        with:
          java-version: '21'
          distribution: temurin
          cache: maven

      - name: Build JAR
        run: mvn package -DskipTests

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Docker metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=sha,prefix=sha-,format=short
            type=raw,value=latest,enable={{is_default_branch}}

      - name: Build and push
        id: build
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          platforms: linux/amd64,linux/arm64    # Multi-platform
          cache-from: type=gha
          cache-to: type=gha,mode=max
          provenance: true                       # SBOM/provenance

      - name: Run Trivy vulnerability scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:latest
          format: sarif
          output: trivy-results.sarif
          severity: CRITICAL,HIGH

      - name: Upload Trivy results to Security tab
        uses: github/codeql-action/upload-sarif@v3
        if: always()
        with:
          sarif_file: trivy-results.sarif
```

---

## Kubernetes deployment

```yaml
# .github/workflows/k8s-deploy.yml

name: Deploy to Kubernetes

on:
  workflow_run:
    workflows: ["Docker Build & Deploy"]
    types: [completed]
    branches: [main]

jobs:
  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    if: ${{ github.event.workflow_run.conclusion == 'success' }}
    environment:
      name: staging
      url: https://staging.mycompany.com

    steps:
      - uses: actions/checkout@v4

      - name: Configure kubectl
        uses: azure/k8s-set-context@v4
        with:
          method: kubeconfig
          kubeconfig: ${{ secrets.KUBE_CONFIG_STAGING }}

      - name: Deploy to Kubernetes
        run: |
          # Image tag-ı yenilə
          kubectl set image deployment/order-service \
            order-service=${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:${{ env.IMAGE_TAG }} \
            -n staging

          # Deployment tamamlanana qədər gözlə
          kubectl rollout status deployment/order-service \
            -n staging \
            --timeout=300s

      - name: Smoke test
        run: |
          sleep 30
          curl -f https://staging.mycompany.com/actuator/health || exit 1

  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: deploy-staging
    environment:
      name: production
      url: https://api.mycompany.com
    # Manual approval tələb edir (GitHub Environment protection rule)

    steps:
      - uses: actions/checkout@v4

      - name: Configure kubectl
        uses: azure/k8s-set-context@v4
        with:
          method: kubeconfig
          kubeconfig: ${{ secrets.KUBE_CONFIG_PROD }}

      - name: Deploy with Helm
        run: |
          helm upgrade --install order-service ./helm/order-service \
            --namespace production \
            --set image.tag=${{ env.IMAGE_TAG }} \
            --set replicas=3 \
            --wait \
            --timeout 5m

      - name: Verify deployment
        run: |
          kubectl rollout status deployment/order-service -n production
          kubectl get pods -n production -l app=order-service

      - name: Post-deploy notification
        if: always()
        uses: slackapi/slack-github-action@v1
        with:
          payload: |
            {
              "text": "Production deploy: ${{ job.status }}\nVersion: ${{ env.IMAGE_TAG }}\nBy: ${{ github.actor }}"
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

---

## Advanced patterns

```yaml
# ─── Reusable Workflow ────────────────────────────────────
# .github/workflows/reusable-java-build.yml

name: Reusable Java Build

on:
  workflow_call:
    inputs:
      java-version:
        description: 'Java version'
        type: string
        default: '21'
      run-integration-tests:
        type: boolean
        default: true
    secrets:
      SONAR_TOKEN:
        required: false
    outputs:
      artifact-name:
        value: ${{ jobs.build.outputs.artifact-name }}

jobs:
  build:
    runs-on: ubuntu-latest
    outputs:
      artifact-name: ${{ steps.artifact.outputs.name }}
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-java@v4
        with:
          java-version: ${{ inputs.java-version }}
          distribution: temurin
          cache: maven
      - run: mvn package
      - id: artifact
        run: echo "name=myapp-$(git rev-parse --short HEAD)" >> $GITHUB_OUTPUT

# Caller workflow:
# jobs:
#   build:
#     uses: ./.github/workflows/reusable-java-build.yml
#     with:
#       java-version: '21'
#     secrets: inherit

---
# ─── Environments & Approvals ─────────────────────────────
# GitHub → Settings → Environments → production
# → Required reviewers: DevOps team
# → Deployment branches: main only
# → Wait timer: 5 minutes (canary sağlamlıq üçün)

# .github/workflows/deploy.yml
jobs:
  deploy:
    environment:
      name: production    # Bu environment-in approval tələb edir
      url: https://api.mycompany.com
    steps:
      - run: echo "Approved! Deploying..."

---
# ─── Caching strategies ───────────────────────────────────
steps:
  - name: Cache Maven packages
    uses: actions/cache@v4
    with:
      path: ~/.m2/repository
      key: ${{ runner.os }}-maven-${{ hashFiles('**/pom.xml') }}
      restore-keys: |
        ${{ runner.os }}-maven-

  - name: Cache SonarCloud packages
    uses: actions/cache@v4
    with:
      path: ~/.sonar/cache
      key: ${{ runner.os }}-sonar
      restore-keys: ${{ runner.os }}-sonar

  # Docker layer cache (Buildx)
  - uses: docker/build-push-action@v5
    with:
      cache-from: type=gha
      cache-to: type=gha,mode=max
```

---

## İntervyu Sualları

### 1. CI vs CD fərqi nədir?
**Cavab:** **CI (Continuous Integration)** — kod push edildikdə avtomatik build, test, kod keyfiyyəti yoxlaması. Məqsəd: "integration hell"-i önləmək, xətaları erkən aşkarlamaq. **CD (Continuous Delivery)** — test mühitinə avtomatik deploy, production-a manual (bir düymə). **CD (Continuous Deployment)** — production-a da avtomatik deploy, human intervention yoxdur. Çox şirkət Continuous Delivery seçir — prod deploy üçün bir reviewer tələb edir.

### 2. GitHub Actions-da job vs step fərqi?
**Cavab:** **Job** — paralel çalışan müstəqil vahid (ayrı runner/VM-də). `needs` keyword ilə ardıcıllıq qurulur. Hər job öz mühitini sıfırdan başlayır. **Step** — eyni job içindəki ardıcıl addımlar, eyni mühiti paylaşır. Step-lər `uses` (action) ya da `run` (shell command) ilə müəyyən edilir. Artifact-lar job-lar arasında paylaşmaq üçün `upload-artifact`/`download-artifact` lazımdır.

### 3. GitHub Actions Secrets necə işləyir?
**Cavab:** Secrets — repository ya da organization-level şifrəli dəyişənlər. Workflow-da `${{ secrets.MY_SECRET }}` ilə istifadə. Log-larda `***` ilə maskalanır. Environment-specific secrets: staging/prod üçün ayrı. Best practice: (1) Minimal permission — yalnız lazımlı scope; (2) Rotate regularly; (3) Production secrets üçün environment protection rules. OIDC (OpenID Connect): cloud provider ilə static secret olmadan authenticate — GitHub Actions → AWS/GCP/Azure token alır.

### 4. Docker layer cache GitHub Actions-da necə işləyir?
**Cavab:** `docker/build-push-action` ilə `cache-from: type=gha, cache-to: type=gha,mode=max`. GitHub Actions Cache storage-da Docker layer-ları saxlanılır. Sonrakı build-də dəyişməyən layer-lar cache-dən gəlir — sürətli build. `mode=max` — bütün layer-lar, `mode=min` — yalnız final image layer-ları. Layered Spring Boot jar ilə birlikdə: dependency layer-lar nadir dəyişir → cache-dən, yalnız application qatı rebuild.

### 5. GitOps nədir?
**Cavab:** Infrastructure as Code + Git = GitOps. Desired state (Kubernetes YAML) Git repo-da saxlanılır. ArgoCD/Flux kimi operator Git-i izləyir, dəyişiklik görəndə cluster-ı güncəlləyir. Üstünlüklər: (1) Audit trail — bütün dəyişikliklər git history-da; (2) Rollback = git revert; (3) PR-based workflow — cluster dəyişikliyi review tələb edir; (4) Drift detection — cluster desired state-dən ayrılarsa avtomatik düzəldilir. GitHub Actions → image build/push; ArgoCD → cluster-a deploy (pull-based).

*Son yenilənmə: 2026-04-10*
