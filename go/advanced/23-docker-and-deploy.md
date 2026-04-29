# Docker and Deploy (Architect)

## İcmal

Bu fəsil production-ready Docker strategiyasını, multi-stage build-ləri, distroless image-ları, Kubernetes deployment-ını, health check-ləri, rolling update strategiyasını və CI/CD pipeline-ını əhatə edir.

## Niyə Vacibdir

- Go binary-nin heç bir runtime asılılığı yoxdur — minimal, güvənli image mümkündür
- Multi-stage build: developer image (1GB+) → production image (10-20MB)
- `scratch` image ilə attack surface praktiki olaraq sıfıra endirilir
- Build-time versiya injection — production-da hansı kod işlədiyini dəqiq bilmək
- Kubernetes rolling update ilə zero-downtime deploy

## Əsas Anlayışlar

**Multi-stage build:**
- `AS builder` mərhələsində kompilyasiya edilir
- Final stage yalnız binary alır — toolchain, source code, test faylları qalmır
- Hər stage ayrı cache layer-dir — `go mod download` ayrıca addımdır

**CGO_ENABLED=0:**
- C kitabxanalarına bağlılığı söndürür
- Nəticə: tam statik binary, libc asılılığı yoxdur
- `scratch` və ya `distroless` image-da işləyə bilir

**ldflags:**
- `-w`: DWARF debug məlumatını sil
- `-s`: symbol table-ı sil
- Nəticə: binary ölçüsü 30-40% kiçilir
- `-X main.version=1.2.3`: build zamanı dəyişkən inject et

**Distroless vs Scratch:**
- `scratch`: tam boş, yalnız binary — ən kiçik, amma debug çətin
- `distroless/static`: minimal Linux, CA sertifikatları, timezone datası — tarazlıqlı seçim
- `alpine`: 5MB, shell var, package manager var — development/debug üçün

**Health check:**
- Liveness probe: proses işləyirmi? (restart trigger)
- Readiness probe: request qəbul edə bilirmi? (traffic switch)

## Praktik Baxış

**Nə vaxt `scratch` istifadə et:**
- External HTTP call edən proqram → CA sertifikatlarını əlavə et
- Timezone lazımdır → `zoneinfo` kopyala
- Debug lazım deyil, minimal attack surface — production microservice

**Nə vaxt `alpine` istifadə et:**
- `sh` lazımdır (init script, debug)
- Əlavə package lazımdır (git, curl, etc.)
- Team-in tanış olduğu image

**Trade-off-lar:**
- Binary ölçüsü vs debug rahatlığı: scratch minimal, alpine debug üçün əlverişlidir
- `CGO_ENABLED=1` lazım gəldikdə (SQLite, native lib): alpine ilə gediyin, scratch-da çalışmaz
- Multi-stage build CI-da daha yavaş (amma layer cache kömək edir)

**Common mistakes:**
- `COPY . .` əvvəl, `go mod download` sonra — her kod dəyişiklikdə dependency re-download
- Secrets-ı ENV variable-da saxlamaq (image layer-ında qalır) — runtime-da inject edin
- `latest` tag istifadə etmək — immutable tag (git SHA, semver) istifadə edin
- Production-da `CMD ["sh"]` qoymaq — ENTRYPOINT binary ilə direct işləsin

## Nümunələr

### Nümunə 1: Sadə multi-stage Dockerfile

```dockerfile
# Dockerfile (sadə)
FROM golang:1.22-alpine AS builder

WORKDIR /app

# Asılılıqları yüklə — kod dəyişsə bu layer cache-dən gəlir
COPY go.mod go.sum ./
RUN go mod download

# Kodu kopyala və build et
COPY . .
RUN go build -o /app/server ./cmd/api

# Final image — yalnız binary
FROM alpine:3.19

# TLS sertifikatları (HTTPS üçün lazım)
RUN apk --no-cache add ca-certificates

WORKDIR /root/
COPY --from=builder /app/server .

EXPOSE 8080
CMD ["./server"]
```

### Nümunə 2: Production-optimized Dockerfile (scratch)

```dockerfile
# Dockerfile (production)
FROM golang:1.22-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

# CGO söndürülür (statik binary), ölçüsü azalt
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build \
    -ldflags="-w -s -X main.version=$(git describe --tags --always) -X main.buildTime=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    -o /app/server \
    ./cmd/api

# -w: debug məlumatını sil
# -s: simvol cədvəlini sil
# Nəticə: ~10-15 MB binary

# Scratch — tam boş image (0 MB base)
FROM scratch

# CA sertifikatları (HTTPS call-lar üçün)
COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

# Timezone məlumatı (time.LoadLocation üçün)
COPY --from=builder /usr/share/zoneinfo /usr/share/zoneinfo

# Yalnız binary
COPY --from=builder /app/server /server

EXPOSE 8080
ENTRYPOINT ["/server"]

# Nəticə: ~12-18 MB image (Go binary + TLS sertifikatları)
# Müqayisə: php:8.3-fpm-alpine → ~100MB, node:20-alpine → ~180MB
```

### Nümunə 3: Distroless (tövsiyə olunan)

```dockerfile
# Dockerfile (distroless — ən yaxşı balans)
FROM golang:1.22 AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux \
    go build -ldflags="-w -s" -o server ./cmd/api

# gcr.io/distroless/static: CA certs + timezone var, shell yoxdur
FROM gcr.io/distroless/static:nonroot

WORKDIR /app

COPY --from=builder /app/server .

# nonroot: 65532 UID ilə işlər — güvənlik üçün
USER nonroot:nonroot

EXPOSE 8080
ENTRYPOINT ["/app/server"]
```

### Nümunə 4: Docker Compose (development + production)

```yaml
# docker-compose.yml
version: '3.8'

services:
  api:
    build:
      context: .
      target: builder          # development üçün builder stage
      # target: production     # production üçün
    ports:
      - "8080:8080"
    environment:
      - APP_ENV=production
      - DB_DSN=postgres://user:pass@postgres:5432/mydb?sslmode=disable
      - REDIS_ADDR=redis:6379
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "/server", "-healthcheck"]
      # və ya: test: ["CMD-SHELL", "wget -q -O- http://localhost:8080/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_USER: user
      POSTGRES_PASSWORD: pass
      POSTGRES_DB: mydb
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U user -d mydb"]
      interval: 5s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru

volumes:
  pgdata:
```

### Nümunə 5: Makefile

```makefile
# Makefile
APP_NAME    := myapp
VERSION     := $(shell git describe --tags --always --dirty)
BUILD_TIME  := $(shell date -u +%Y-%m-%dT%H:%M:%SZ)
LDFLAGS     := -ldflags="-w -s -X main.version=$(VERSION) -X main.buildTime=$(BUILD_TIME)"
REGISTRY    := ghcr.io/myorg

.PHONY: build run test lint docker-build docker-push deploy clean

build:
	CGO_ENABLED=0 go build $(LDFLAGS) -o bin/$(APP_NAME) ./cmd/api

run:
	go run ./cmd/api

test:
	go test ./... -v -race -coverprofile=coverage.out

test-coverage:
	go tool cover -html=coverage.out

lint:
	golangci-lint run ./...

docker-build:
	docker build \
		--build-arg VERSION=$(VERSION) \
		--build-arg BUILD_TIME=$(BUILD_TIME) \
		-t $(REGISTRY)/$(APP_NAME):$(VERSION) \
		-t $(REGISTRY)/$(APP_NAME):latest \
		.

docker-push:
	docker push $(REGISTRY)/$(APP_NAME):$(VERSION)
	docker push $(REGISTRY)/$(APP_NAME):latest

docker-run:
	docker compose up -d

docker-stop:
	docker compose down

migrate-up:
	migrate -path migrations -database "$(DB_URL)" up

migrate-down:
	migrate -path migrations -database "$(DB_URL)" down 1

clean:
	rm -rf bin/
	docker compose down -v

# Cross-compilation
build-all:
	GOOS=linux   GOARCH=amd64 go build $(LDFLAGS) -o bin/$(APP_NAME)-linux-amd64 ./cmd/api
	GOOS=linux   GOARCH=arm64 go build $(LDFLAGS) -o bin/$(APP_NAME)-linux-arm64 ./cmd/api
	GOOS=darwin  GOARCH=amd64 go build $(LDFLAGS) -o bin/$(APP_NAME)-darwin-amd64 ./cmd/api
	GOOS=darwin  GOARCH=arm64 go build $(LDFLAGS) -o bin/$(APP_NAME)-darwin-arm64 ./cmd/api
	GOOS=windows GOARCH=amd64 go build $(LDFLAGS) -o bin/$(APP_NAME)-windows.exe  ./cmd/api
```

### Nümunə 6: Kubernetes deployment

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: myapp
  namespace: production
  labels:
    app: myapp
    version: "1.2.3"
spec:
  replicas: 3
  selector:
    matchLabels:
      app: myapp
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1        # eyni anda max 1 əlavə pod
      maxUnavailable: 0  # heç bir pod mövcud olmamalıdır (zero-downtime)
  template:
    metadata:
      labels:
        app: myapp
    spec:
      containers:
        - name: myapp
          image: ghcr.io/myorg/myapp:1.2.3
          ports:
            - containerPort: 8080
          env:
            - name: APP_ENV
              value: "production"
            - name: DB_DSN
              valueFrom:
                secretKeyRef:
                  name: myapp-secrets
                  key: db-dsn
          resources:
            requests:
              memory: "64Mi"
              cpu: "250m"
            limits:
              memory: "256Mi"
              cpu: "500m"
          # Liveness: proses işləyirmi? Xeyr olarsa pod restart olur
          livenessProbe:
            httpGet:
              path: /health
              port: 8080
            initialDelaySeconds: 10
            periodSeconds: 30
            failureThreshold: 3
          # Readiness: request qəbul edə bilirmi? Xeyr olarsa traffic gəlmir
          readinessProbe:
            httpGet:
              path: /ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 10
            failureThreshold: 3
          # Graceful shutdown üçün
          lifecycle:
            preStop:
              exec:
                command: ["/bin/sh", "-c", "sleep 5"]
      terminationGracePeriodSeconds: 30

---
# k8s/service.yaml
apiVersion: v1
kind: Service
metadata:
  name: myapp
  namespace: production
spec:
  selector:
    app: myapp
  ports:
    - port: 80
      targetPort: 8080
  type: ClusterIP

---
# k8s/hpa.yaml — Horizontal Pod Autoscaler
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: myapp
  namespace: production
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: myapp
  minReplicas: 2
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
```

### Nümunə 7: GitHub Actions CI/CD

```yaml
# .github/workflows/ci.yml
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
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-go@v5
        with:
          go-version: '1.22'
          cache: true

      - name: Test
        run: go test ./... -v -race -coverprofile=coverage.out

      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          file: ./coverage.out

      - name: Lint
        uses: golangci/golangci-lint-action@v4

  build-and-push:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' || startsWith(github.ref, 'refs/tags/')
    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0  # git describe üçün lazım

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Docker meta
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=semver,pattern={{version}}
            type=sha,prefix=sha-

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  deploy:
    needs: build-and-push
    runs-on: ubuntu-latest
    if: startsWith(github.ref, 'refs/tags/')
    steps:
      - name: Deploy to Kubernetes
        run: |
          kubectl set image deployment/myapp \
            myapp=${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:${{ github.ref_name }} \
            --namespace=production
          kubectl rollout status deployment/myapp --namespace=production
```

### Nümunə 8: Build zamanı versiya inject etmək

```go
// cmd/api/main.go
package main

import (
    "fmt"
    "log/slog"
    "os"
)

// Build zamanı ldflags ilə inject edilir
var (
    version   = "dev"     // -X main.version=1.2.3
    buildTime = "unknown" // -X main.buildTime=2024-03-15
    gitCommit = "unknown" // -X main.gitCommit=abc123
)

func main() {
    logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))
    logger.Info("Server başladı",
        "version", version,
        "buildTime", buildTime,
        "gitCommit", gitCommit,
    )

    // /version endpoint-i
    // GET /version → {"version":"1.2.3","buildTime":"...","commit":"abc123"}
    fmt.Printf("Version: %s, Build: %s, Commit: %s\n", version, buildTime, gitCommit)
}

// Build əmri:
// go build \
//   -ldflags="-X main.version=1.2.3 -X main.buildTime=$(date -u +%Y-%m-%dT%H:%M:%SZ) -X main.gitCommit=$(git rev-parse --short HEAD)" \
//   -o server ./cmd/api
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Multi-stage build:**
1. Mövcud Go proqramı üçün iki Dockerfile yazın: sadə və multi-stage
2. Hər iki image-ın ölçüsünü müqayisə edin: `docker images`
3. `docker history <image>` ilə layer-ları araşdırın

**Tapşırıq 2 — Scratch image:**
1. `scratch` image ilə Dockerfile yazın
2. HTTPS call edən proqram yaz (məs: external API çağır)
3. CA sertifikatları olmadan çalışdırın — xəta görün
4. CA sertifikatlarını əlavə edin — düzəldin

**Tapşırıq 3 — Docker Compose stack:**
1. Go API + PostgreSQL + Redis stack qurun
2. PostgreSQL üçün healthcheck əlavə edin
3. API servisi yalnız PostgreSQL hazır olduqdan sonra başlasın
4. `docker compose logs -f` ilə logları izləyin

**Tapşırıq 4 — Kubernetes rolling update:**
1. Local Kubernetes qurun (kind/minikube)
2. Deployment, Service yaradın
3. Liveness və readiness probe əlavə edin
4. `kubectl rollout status` ilə update izləyin
5. `kubectl rollout undo` ilə geri qaytarın

**Tapşırıq 5 — GitHub Actions pipeline:**
1. Sadə CI pipeline qurun: test → lint → docker build
2. Tag push-da avtomatik push əlavə edin
3. `GITHUB_TOKEN` ilə `ghcr.io`-ya push edin

## Ətraflı Qeydlər

**`.dockerignore` vacibdir:**
```
.git
.gitignore
*.md
bin/
vendor/
.env
*.test
coverage.out
```

**Security best practices:**
- Non-root user ilə işlət: `USER nonroot:nonroot` (distroless) və ya `adduser -D appuser`
- Read-only filesystem: `docker run --read-only`
- Secrets image-da deyil, runtime-da inject: Kubernetes Secrets, HashiCorp Vault
- `latest` tag istifadə etmə — immutable tag (semver, git SHA)

**Cross-compilation üstünlüyü:**
- Go-nun ən böyük üstünlüklərindən biri: `GOOS=linux GOARCH=arm64 go build`
- Mac-da ARM64 Linux binary build et — CI/CD qurulmadan local test mümkün

## PHP ilə Müqayisə

Go proqramlarını Docker ilə konteynerləşdirmək PHP/Laravel-dən əhəmiyyətli dərəcədə fərqlidir. Go statik binary kompilyasiya edir — runtime, PHP-FPM, Apache/Nginx, extension-lar lazım deyil. PHP Laravel Dockerfile-ı adətən `php:8.x-fpm` base image-ı ilə başlayır, Nginx, Composer, PHP extension-ları (pdo, redis, mbstring...) quraşdırılır — nəticə 300MB–1GB image. Go-da eyni funksionallıq 10–20MB binary + distroless base ilə əldə edilir. Cross-compilation Go-nun ən böyük üstünlüklərindən biridir: PHP bu imkanı təqdim etmir.

## Əlaqəli Mövzular

- [../backend/17-graceful-shutdown.md](../backend/17-graceful-shutdown.md) — Graceful shutdown implementasiyası
- [71-monitoring-and-observability.md](24-monitoring-and-observability.md) — Health check endpoint-ləri
- [../backend/07-environment-and-config.md](../backend/07-environment-and-config.md) — Environment dəyişkənləri
- [26-microservices.md](26-microservices.md) — Microservice arxitekturası
