# GitHub Actions CI/CD (Senior)

## İcmal

GitHub Actions — Go proyektləri üçün CI/CD pipeline-ı birbaşa repository-də qurmağa imkan verir. Hər `git push`-dan sonra: kod build olunur, testlər keçir, Docker image yaranır, K8s-ə deploy edilir. Manual işlər avtomatlaşır, human error aradan qalxır.

## Niyə Vacibdir

PHP/Laravel-də `Envoyer` və ya `Deployer` ilə deploy etmişdin. Go projekti üçün isə:

- `go build` — hər PR-da kodu yoxlamaq lazımdır
- `go test -race` — race condition-ları CI-da tutmaq daha ucuzdur
- `golangci-lint` — kod review-da manual lint yerinə avtomatik
- Docker image → GHCR → K8s deploy — tam pipeline olmalıdır

GitHub Actions-ın üstünlüyü: repository ilə eyni yerdə yaşayır, pulsuz (public repo), secrets idarəsi rahatdır.

## Əsas Anlayışlar

- **Workflow**: `.github/workflows/*.yml` faylında təyin edilən pipeline
- **Trigger** (`on:`): push, pull_request, schedule, manual (`workflow_dispatch`)
- **Job**: paralel və ya ardıcıl işləyən addımlar qrupu
- **Step**: job daxilindəki bir əmr və ya action
- **Action**: hazır step (`actions/checkout`, `actions/setup-go`)
- **Runner**: workflow-u icra edən virtual maşın (ubuntu-latest)
- **Secret**: GitHub → Settings → Secrets-dəki həssas dəyişənlər
- **Matrix build**: eyni workflow-u müxtəlif Go versiyalarında paralel işlətmək

## Praktik Baxış

### CI workflow strategiyası

- `push` + `pull_request` trigger: hər dəyişiklik yoxlanır
- Cache: `go mod download` hər dəfə çalışmasın — 30-60 saniyə qazanılır
- Race detector (`-race`): production bug-ları CI-da tutulur
- Coverage: `go test -coverprofile` → Codecov upload

### CD workflow strategiyası

- Yalnız `main` branch-a push olunanda işlər
- Staging → smoke test → production (manual approval) ardıcıllığı
- `environment: production` ilə GitHub Environments approval qurulur
- Image tag: `sha-${{ github.sha }}` — hansı commit-dən build olduğu bəlli olur

### golangci-lint

Ən vacib linters:
- `errcheck` — qaytarılan error-ların yoxlanması
- `govet` — compiler warning-ləri
- `staticcheck` — dərin statik analiz
- `gocyclo` — cyclomatic complexity
- `misspell` — yazım xətaları
- `revive` — `golint`-in modern versiyası

## Nümunələr

### Ümumi Nümunə

Laravel-də CI/CD belə görünürdü:
```
push → GitHub → Forge/Envoyer → server-ə SSH → composer install → migrate → restart
```

Go-da isə:
```
push → GitHub Actions:
  CI: build → vet → test → lint (paralel)
  CD: docker build → GHCR push → staging deploy → smoke test → prod deploy (approval)
```

### Kod Nümunəsi

**.github/workflows/ci.yml**

```yaml
name: CI

on:
  push:
    branches: ["**"]
  pull_request:
    branches: [main]

env:
  GO_VERSION: "1.23"

jobs:
  build-and-test:
    name: Build & Test
    runs-on: ubuntu-latest

    strategy:
      matrix:
        go-version: ["1.22", "1.23"]  # Matrix: paralel test

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Go ${{ matrix.go-version }}
        uses: actions/setup-go@v5
        with:
          go-version: ${{ matrix.go-version }}

      - name: Cache Go modules
        uses: actions/cache@v4
        with:
          path: |
            ~/.cache/go-build
            ~/go/pkg/mod
          key: ${{ runner.os }}-go-${{ matrix.go-version }}-${{ hashFiles('**/go.sum') }}
          restore-keys: |
            ${{ runner.os }}-go-${{ matrix.go-version }}-

      - name: Download dependencies
        run: go mod download

      - name: Build
        run: go build ./...

      - name: Vet
        run: go vet ./...

      - name: Test with race detector
        run: go test -race -coverprofile=coverage.out -covermode=atomic ./...

      - name: Upload coverage to Codecov
        if: matrix.go-version == '1.23'  # Yalnız bir versiyadan upload
        uses: codecov/codecov-action@v4
        with:
          token: ${{ secrets.CODECOV_TOKEN }}
          file: ./coverage.out
          fail_ci_if_error: false

  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: ${{ env.GO_VERSION }}

      - name: Run golangci-lint
        uses: golangci/golangci-lint-action@v6
        with:
          version: latest
          args: --timeout=5m

  check-tidy:
    name: Check go mod tidy
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-go@v5
        with:
          go-version: ${{ env.GO_VERSION }}
      - run: go mod tidy
      - name: Check for changes
        run: |
          if [ -n "$(git status --porcelain)" ]; then
            echo "go mod tidy produced changes — commit them"
            git diff
            exit 1
          fi
```

**.github/workflows/cd.yml**

```yaml
name: CD

on:
  push:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}  # myorg/go-api

jobs:
  build-and-push:
    name: Build & Push Docker Image
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    outputs:
      image-tag: ${{ steps.meta.outputs.tags }}
      image-digest: ${{ steps.build.outputs.digest }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}  # Avtomatik — secret lazım deyil

      - name: Extract Docker metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=sha,prefix=sha-
            type=raw,value=latest,enable={{is_default_branch}}

      - name: Build and push Docker image
        id: build
        uses: docker/build-push-action@v6
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    needs: build-and-push
    environment: staging

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up kubectl
        uses: azure/setup-kubectl@v4

      - name: Configure kubeconfig
        run: |
          mkdir -p ~/.kube
          echo "${{ secrets.KUBE_CONFIG_STAGING }}" | base64 -d > ~/.kube/config

      - name: Deploy to staging
        run: |
          IMAGE="${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:sha-${{ github.sha }}"
          kubectl set image deployment/go-api go-api=$IMAGE -n staging
          kubectl rollout status deployment/go-api -n staging --timeout=120s

      - name: Run smoke tests
        run: |
          sleep 10  # Tətbiqin ayağa qalxmasını gözlə
          STAGING_URL="https://staging-api.myapp.com"
          STATUS=$(curl -s -o /dev/null -w "%{http_code}" $STAGING_URL/health)
          if [ "$STATUS" != "200" ]; then
            echo "Smoke test failed! Status: $STATUS"
            exit 1
          fi
          echo "Smoke test passed!"

  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: deploy-staging
    environment: production  # GitHub-da manual approval tələb edir

    steps:
      - name: Set up kubectl
        uses: azure/setup-kubectl@v4

      - name: Configure kubeconfig
        run: |
          mkdir -p ~/.kube
          echo "${{ secrets.KUBE_CONFIG_PROD }}" | base64 -d > ~/.kube/config

      - name: Deploy to production
        run: |
          IMAGE="${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:sha-${{ github.sha }}"
          kubectl set image deployment/go-api go-api=$IMAGE -n production
          kubectl rollout status deployment/go-api -n production --timeout=180s

      - name: Verify production health
        run: |
          sleep 15
          STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://api.myapp.com/health)
          if [ "$STATUS" != "200" ]; then
            kubectl rollout undo deployment/go-api -n production
            echo "Production health check failed — rolled back!"
            exit 1
          fi
          echo "Production deploy successful!"
```

**.golangci.yml**

```yaml
run:
  timeout: 5m
  go: "1.23"

linters:
  enable:
    - errcheck        # error-ları yoxla
    - govet           # go vet analizi
    - staticcheck     # dərin statik analiz
    - unused          # istifadə olunmayan kod
    - gocyclo         # complexity
    - misspell        # yazım xətaları
    - revive          # golint modern versiyası
    - gosec           # security check
    - bodyclose       # http response body close
    - noctx           # context-siz http request
    - rowserrcheck    # sql.Rows.Err() yoxla
    - sqlclosecheck   # sql.Rows.Close() yoxla

linters-settings:
  gocyclo:
    min-complexity: 15
  errcheck:
    check-type-assertions: true
  govet:
    check-shadowing: true
  revive:
    rules:
      - name: exported
        disabled: false

issues:
  exclude-rules:
    - path: "_test.go"
      linters:
        - errcheck  # Test fayllarında error yoxlaması tələb etmə
    - path: "main.go"
      linters:
        - gocyclo

  max-issues-per-linter: 50
  max-same-issues: 10
```

**Makefile** (Actions-dan çağırılan)

```makefile
.PHONY: build test lint clean

GO=go
BINARY=bin/server

build:
	$(GO) build -o $(BINARY) ./cmd/api

test:
	$(GO) test -race -coverprofile=coverage.out ./...

lint:
	golangci-lint run ./...

tidy:
	$(GO) mod tidy

clean:
	rm -rf bin/ coverage.out

# CI-dan: make ci
ci: tidy build test lint
```

**GitHub Actions-da Makefile çağırmaq:**

```yaml
- name: Run CI checks
  run: make ci
```

## Praktik Tapşırıqlar

**1. Lokal workflow test et:**
```bash
# act ilə lokal GitHub Actions test (Docker lazımdır)
brew install act
act push --job build-and-test
```

**2. Secrets qur:**

GitHub → Repository → Settings → Secrets and variables → Actions:
- `CODECOV_TOKEN` — codecov.io-dan
- `KUBE_CONFIG_STAGING` — `base64 ~/.kube/config`
- `KUBE_CONFIG_PROD` — production kubeconfig

**3. Environment approval qur:**

GitHub → Settings → Environments → production → "Required reviewers" əlavə et. Beləliklə staging-dən sonra production deploy etmək üçün manual approval lazım olacaq.

**4. Cache performansını yoxla:**

Workflow run-ları arasında "Cache restored" vs "Cache missed" nisbətinə bax. İlk run: ~2 dəqiqə. Cache-dən: ~30 saniyə.

**5. Matrix build iflası:**

```yaml
strategy:
  fail-fast: false  # Bir versiya fail olsa, digəri davam etsin
  matrix:
    go-version: ["1.22", "1.23"]
```

`fail-fast: false` — bir matrix kombinasiyası fail olsa, digərləri dayanmasın.

**6. Workflow badge README-ə əlavə et:**

```markdown
![CI](https://github.com/myorg/go-api/actions/workflows/ci.yml/badge.svg)
```

## Əlaqəli Mövzular

- `23-docker-and-deploy.md` — Docker image build detalları
- `38-kubernetes-basics.md` — K8s-ə deploy etmə
- `07-security.md` — secrets idarəsi
- `16-testcontainers.md` — integration test-lər CI-da
