# Ecommerce Laravel

**PHP 8.3 + Laravel 13** ilə yazılmış DDD e-commerce tətbiqi. Eyni biznes sistemin [Spring Boot](../spring/) və [Go](../golang/) versiyaları ilə müqayisəli öyrənmə üçündür.

## Quick Start

```bash
make up       # docker servisləri qaldır
make migrate  # DB migration
make seed     # test data
```

Endpoint: `http://localhost:8080/api/health`

## Sənədləşdirmə

- [DOCUMENTATION.md](DOCUMENTATION.md) — tam texniki sənəd (arxitektura, DDD, CQRS, pattern-lər)
- [COMPARISON.md](COMPARISON.md) — 3 stack müqayisəsi (Laravel ↔ Spring ↔ Go)
- [HTTP_EXAMPLES.md](HTTP_EXAMPLES.md) — bütün endpoint-lər curl nümunəsi ilə

## Texnoloji Stack

| Texnologiya | Versiya | Məqsəd |
|-------------|---------|--------|
| PHP | 8.3 | Proqramlaşdırma dili |
| Laravel | 13 | Framework |
| MySQL | 8.0 | Əsas verilənbazası |
| Redis | Latest | Cache, session, queue, distributed lock |
| RabbitMQ | 3-management | Message broker |
| Nginx | Latest | Reverse proxy |
| Docker + Compose | Latest | Konteynerləşdirmə |
| Mailpit | Latest | Dev mühitdə email testi |

## Arxitektura

5 Bounded Context — **User · Product · Order · Payment · Notification** + Shared Kernel

Hər context-də DDD layered struktur:
```
src/{Context}/
├── Domain/        ← saf business logic, framework-dən asılı deyil
├── Application/   ← CQRS handler-lər, DTO-lar
└── Infrastructure/ ← Eloquent model, controller, gateway
```

## Test

```bash
make test                          # bütün testlər
php artisan test --filter=OrderTest  # tək test
php artisan test --coverage          # coverage
```
