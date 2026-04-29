# Ecommerce Go

**Laravel DDD e-commerce** layihəsinin **Go (Gin + GORM)** ekvivalenti.

## Quick Start

```bash
docker compose up --build
```

Endpoint: `http://localhost:8080/api/health`

## Sənədləşdirmə

📖 [DOCUMENTATION.md](DOCUMENTATION.md) — Laravel ↔ Spring ↔ **Go** üç-versiyalı müqayisə (öyrənmə üçün).

📋 [../laravel/HTTP_EXAMPLES.md](../laravel/HTTP_EXAMPLES.md) — bütün 3 stack üçün curl nümunələri (eyni `localhost:8080` portu).

## Texnoloji stack

- **Go 1.23** + Generics
- **Gin** — HTTP framework (78k ⭐)
- **GORM** — ORM (36k ⭐)
- **golang-migrate** — DB migration (Flyway əvəzi)
- **viper** — config (application.yml əvəzi)
- **go-redis + Redsync** — cache + distributed lock
- **amqp091-go** — RabbitMQ
- **gobreaker** — Circuit Breaker (Resilience4j əvəzi)
- **golang-jwt** — JWT (Sanctum əvəzi)
- **pquerna/otp** — 2FA TOTP
- **go-mail + text/template** — email
- **cobra** — CLI (Artisan əvəzi)
- **slog** — structured logging (stdlib)
- **testify + testcontainers-go** — testing

## Arxitektura

5 Bounded Context — eyni Laravel/Spring versiyaları kimi:
- **User · Product · Order · Payment · Notification** + Shared Kernel

Hər context-də DDD layered struktur:
```
domain/         ← saf POJO/struct, framework-dən asılı deyil
application/    ← CQRS handler-lər, DTO-lar
infrastructure/ ← GORM model, controller, gateway
```

## Test

```bash
go test ./test/unit/...                            # unit testlər (domain VO-lar)
go test ./test/feature/...                         # feature testlər (HTTP layer, in-memory)
go test -tags=integration ./test/integration/...   # Testcontainers (Docker lazımdır)
go test -cover ./internal/...                      # coverage
```

## Faydalı detal

Bu layihə **performansda** Laravel/Spring-dən üstündür: cold start ~50ms (vs ~500ms PHP, ~3s JVM), Docker image ~15MB (vs ~200MB), eyni trafiklə **5-10x az RAM**.
