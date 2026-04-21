# Əlavə ediləcək komponentlər

## Kateqoriya 1: Mütləq olmalı
- [x] Exception Handler — DomainException → 422, ValidationException → 400, AuthorizationException → 403
- [x] HTTP Middleware — Rate Limiting, ForceJson, Authenticate
- [x] Authentication (Sanctum) — Login, logout, token
- [x] Tests — Unit (Value Object, Specification), Feature (API), Integration (Repository)
- [x] Seeder + Factory — Demo data
- [x] PHP Enum — OrderStatus, PaymentStatus, PaymentMethod
- [x] Docker Compose — Laravel + MySQL + RabbitMQ + Redis

## Kateqoriya 2: Çox faydalı
- [x] Event Sourcing nümunəsi — Order tarixçəsi event-lər şəklində
- [x] Read Model / Projection — CQRS oxuma üçün denormalized cədvəl
- [x] API Versioning — /api/v1/orders
- [x] Pagination — Product və Order siyahıları
- [ ] Structured Logging — JSON format, ELK stack üçün
- [x] Health Check endpoint — DB, RabbitMQ, Redis yoxlaması
- [x] CORS config
- [x] Scheduler — Outbox, CircuitBreaker cron

## Kateqoriya 3: Əlavə öyrənmə dəyəri
- [x] Bounded Context Communication Service — context-lər arası API
- [x] Idempotency Key — dublikat sorğu qoruması
- [x] Audit Trail / Activity Log — kim nə vaxt nə etdi
- [x] Feature Flags — config əsaslı
- [x] Cache Invalidation Strategy — tag-based
