# app/ — 3 Stack DDD E-Commerce

Eyni biznes sistemin üç texnologiya stack-ində paralel implementasiyası. Məqsəd — müqayisəli öyrənmə.

| Stack | Qovluq | Texnologiya |
|-------|--------|-------------|
| **Laravel** (orijinal) | [`laravel/`](laravel/) | PHP 8.3 + Laravel 13 + Eloquent |
| **Spring Boot** | [`spring/`](spring/) | Java 21 + Spring Boot 3.4 + JPA |
| **Go** | [`golang/`](golang/) | Go 1.23 + Gin + GORM |

## Sənədlər

- [laravel/DOCUMENTATION.md](laravel/DOCUMENTATION.md) — tam texniki sənəd
- [spring/DOCUMENTATION.md](spring/DOCUMENTATION.md) — Laravel ↔ Spring müqayisəsi
- [golang/DOCUMENTATION.md](golang/DOCUMENTATION.md) — Laravel ↔ Spring ↔ Go müqayisəsi
- [laravel/COMPARISON.md](laravel/COMPARISON.md) — texnologiya mapping, performance, hansını seçmək

## Quick Start

```bash
# Laravel
cd laravel && make up && make migrate && make seed

# Spring
cd spring && make up-all

# Go
cd golang && make up && make migrate && make seed
```

Hər biri `http://localhost:8080/api/health` endpoint-ində işləyir.

## Nə Öyrənmək Olar?

Hər 3 layihədə eyni pattern-lər var — sadəcə sintaksis fərqlənir:

- DDD (Domain-Driven Design) — Bounded Context, Aggregate Root, Value Object
- CQRS — Command/Query Bus + middleware pipeline
- Event Sourcing — Order tarixçəsi
- Saga — OrderSaga (orchestration)
- Strategy Pattern — 3 PaymentGateway
- Outbox + Inbox + DLQ
- Circuit Breaker, Distributed Lock, Tagged Cache
- JWT auth + 2FA TOTP
