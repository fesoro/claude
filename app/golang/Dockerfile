# Multi-stage build — Spring Dockerfile-ə oxşar
# Laravel: PHP-FPM image  ·  Spring: JDK 21 image  ·  Go: golang + scratch
FROM golang:1.23-alpine AS builder
WORKDIR /workspace

# Cache go mod
COPY go.mod go.sum* ./
RUN go mod download

# Bütün qaynağı kopyala
COPY . .

# Static binary (CGO disabled — minimal image üçün)
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o /app/api ./cmd/api
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o /app/cli ./cmd/cli

# Runtime — minimal alpine (təxmini 15MB)
FROM alpine:3.20
WORKDIR /app
RUN apk add --no-cache curl ca-certificates tzdata

COPY --from=builder /app/api /app/api
COPY --from=builder /app/cli /app/cli
COPY configs /app/configs/
COPY migrations /app/migrations/
COPY templates/ /app/templates/

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD curl -f http://localhost:8080/api/health/live || exit 1

ENV APP_CONFIG=/app/configs/config.yaml
ENTRYPOINT ["/app/api"]
