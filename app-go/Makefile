# Go Makefile — eyni interfeys (make help)
.PHONY: help up down build test seed migrate logs shell run lint vet

help:  ## bütün command-lar
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up:  ## docker compose up (servislər)
	docker compose up -d mysql rabbitmq redis mailpit

up-all:  ## hər şey Docker-də (app + nginx daxil)
	docker compose up --build

down:  ## docker compose down
	docker compose down

build:  ## binary qur
	CGO_ENABLED=0 go build -o bin/api ./cmd/api
	CGO_ENABLED=0 go build -o bin/cli ./cmd/cli

run:  ## lokal işə sal (Docker servislər lazımdır)
	go run ./cmd/api

logs:  ## app log-ları
	docker compose logs -f app

shell:  ## app container-ə daxil ol
	docker compose exec app sh

migrate:  ## migration-lar avtomatik işləyir, manual çağırış (CLI ilə)
	go run ./cmd/cli migrate

seed:  ## seed CLI command
	go run ./cmd/cli seed

test:  ## unit + feature testlər
	go test -v ./test/unit/... ./test/feature/...

it:  ## integration testlər (Testcontainers, Docker lazımdır)
	go test -v -tags=integration ./test/integration/...

cover:  ## coverage report
	go test -coverprofile=coverage.out ./internal/...
	go tool cover -html=coverage.out

lint:  ## golangci-lint
	golangci-lint run ./...

vet:  ## go vet
	go vet ./...

tidy:  ## go.mod sırala
	go mod tidy
