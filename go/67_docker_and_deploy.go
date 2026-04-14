package main

import "fmt"

// ===============================================
// DOCKER VE DEPLOY
// ===============================================

// Go proqramlarini Docker ile paketlemek ve deploy etmek

func main() {

	fmt.Println(`
=======================================
1. SADƏ DOCKERFILE
=======================================

# Dockerfile
FROM golang:1.22-alpine AS builder

WORKDIR /app

# Asililqlari yukle (cache ucun ayri addim)
COPY go.mod go.sum ./
RUN go mod download

# Kodu kopyala ve build et
COPY . .
RUN go build -o /app/server ./cmd/api

# Final image (kicik)
FROM alpine:latest

RUN apk --no-cache add ca-certificates
WORKDIR /root/
COPY --from=builder /app/server .

EXPOSE 8080
CMD ["./server"]
=======================================
2. MULTI-STAGE BUILD (optimallasdirilmis)
=======================================

# Dockerfile
FROM golang:1.22-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

# CGO sondurulur (statik binary), olcunu azalt
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -ldflags="-w -s" -o /app/server ./cmd/api
# -w: debug melumatini sil
# -s: simvol cedvelini sil
# Netice: daha kicik binary

# Scratch - en minimal image (0 MB base)
FROM scratch

COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/
COPY --from=builder /app/server /server

EXPOSE 8080
ENTRYPOINT ["/server"]

# Netice: ~10-20 MB image (Go binary + TLS sertifikatlari)
=======================================
3. DOCKER COMPOSE
=======================================

# docker-compose.yml
version: '3.8'

services:
  api:
    build: .
    ports:
      - "8080:8080"
    environment:
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_USER=myuser
      - DB_PASSWORD=mypass
      - DB_NAME=mydb
      - APP_ENV=production
    depends_on:
      postgres:
        condition: service_healthy
    restart: unless-stopped

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_USER: myuser
      POSTGRES_PASSWORD: mypass
      POSTGRES_DB: mydb
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U myuser"]
      interval: 5s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  pgdata:
=======================================
4. MAKEFILE
=======================================

# Makefile
APP_NAME=myapp
VERSION=$(shell git describe --tags --always)
BUILD_TIME=$(shell date -u +%Y-%m-%dT%H:%M:%SZ)

.PHONY: build run test docker clean

build:
	CGO_ENABLED=0 go build -ldflags="-w -s \
		-X main.version=$(VERSION) \
		-X main.buildTime=$(BUILD_TIME)" \
		-o bin/$(APP_NAME) ./cmd/api

run:
	go run ./cmd/api

test:
	go test ./... -v -race -cover

lint:
	golangci-lint run ./...

docker-build:
	docker build -t $(APP_NAME):$(VERSION) .
	docker tag $(APP_NAME):$(VERSION) $(APP_NAME):latest

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
=======================================
5. CI/CD - GITHUB ACTIONS
=======================================

# .github/workflows/ci.yml
name: CI/CD

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-go@v5
        with:
          go-version: '1.22'

      - name: Test
        run: go test ./... -v -race -coverprofile=coverage.out

      - name: Lint
        uses: golangci/golangci-lint-action@v4

  build:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4

      - name: Docker Build & Push
        run: |
          docker build -t myapp:latest .
          # docker push ...
=======================================
6. CROSS COMPILATION
=======================================

# Ferqli platformalar ucun build
GOOS=linux   GOARCH=amd64 go build -o myapp-linux-amd64
GOOS=linux   GOARCH=arm64 go build -o myapp-linux-arm64
GOOS=darwin  GOARCH=amd64 go build -o myapp-macos-amd64
GOOS=darwin  GOARCH=arm64 go build -o myapp-macos-arm64
GOOS=windows GOARCH=amd64 go build -o myapp-windows.exe

# Go-nun ustunluyu: bir komanda ile istənilən platform ucun build!
=======================================
7. VERSIYA MELUMATI INJECT ETMEK
=======================================

// main.go
package main

var (
    version   = "dev"
    buildTime = "unknown"
)

func main() {
    fmt.Printf("Versiya: %s, Build: %s\n", version, buildTime)
}

// Build zamani inject:
// go build -ldflags="-X main.version=1.2.3 -X main.buildTime=2024-03-15"
`)
}
