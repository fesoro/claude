# Senior PHP/Laravel Developer - Interview Hazırlığı

## Fayllar

### Əsas Biliklər (01-13)
| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [php-core.md](01-php-core.md) | PHP 8.x xüsusiyyətləri, type system, generators, closures, error handling, magic methods |
| 02 | [oop-design-patterns.md](02-oop-design-patterns.md) | SOLID, Strategy, Observer, Factory, Decorator, Singleton, DTO, Service Layer, Composition |
| 03 | [laravel-fundamentals.md](03-laravel-fundamentals.md) | Service Container, Providers, Middleware, Lifecycle, Eloquent, Events, Validation, Resources |
| 04 | [database-eloquent-advanced.md](04-database-eloquent-advanced.md) | Indexing, Transactions, Query Optimization, Migrations, Casting, Soft Deletes, Caching |
| 05 | [api-development.md](05-api-development.md) | REST API, Sanctum/Passport, Rate Limiting, Versioning, Error Handling, API Testing |
| 06 | [queues-jobs-scheduling.md](06-queues-jobs-scheduling.md) | Queues, Jobs, Chaining, Batching, Horizon, Task Scheduling, Unique Jobs |
| 07 | [testing.md](07-testing.md) | Unit/Feature Tests, Mocking, Faking, Database Testing, Data Providers |
| 08 | [security.md](08-security.md) | SQL Injection, XSS, CSRF, Mass Assignment, Encryption, CORS |
| 09 | [performance-optimization.md](09-performance-optimization.md) | Caching, DB Performance, Octane, Queue Optimization, Monitoring |
| 10 | [architecture-deployment.md](10-architecture-deployment.md) | Monolith/Microservices, DDD, Docker, CI/CD, Zero-Downtime Deploy |
| 11 | [advanced-laravel.md](11-advanced-laravel.md) | Pipeline, Notifications, Broadcasting, Actions, Collections, Multi-tenancy |
| 12 | [system-design-behavioral.md](12-system-design-behavioral.md) | System Design, Debugging, Refactoring, Code Review, Migration Planning |
| 13 | [git-tools-devops.md](13-git-tools-devops.md) | Git branching, Composer, PHPStan, Linux, Nginx, Redis |

### Dərin Biliklər (14-19, 25-27)
| # | Fayl | Mövzu |
|---|------|-------|
| 14 | [php-internals-memory.md](14-php-internals-memory.md) | Zend Engine, OPcache, JIT, Memory/GC, Streams, SPL, Fibers, PHP-FPM |
| 15 | [database-deep-dive.md](15-database-deep-dive.md) | Normalization, Replication, Sharding, Deadlocks, EXPLAIN, PostgreSQL vs MySQL |
| 16 | [microservices-messaging.md](16-microservices-messaging.md) | RabbitMQ, Event Sourcing, CQRS, GraphQL, Saga Pattern, Idempotency, Webhooks |
| 17 | [coding-challenges.md](17-coding-challenges.md) | 13 Live Coding sualı — LRU Cache, DI Container, Binary Search, Rate Limiter |
| 18 | [clean-code-refactoring.md](18-clean-code-refactoring.md) | Code Smells, Refactoring, Guard Clauses, Value Objects, Immutability |
| 19 | [elasticsearch-search.md](19-elasticsearch-search.md) | Elasticsearch, Full-Text Search, Data Import/Export |
| 25 | [additional-patterns-questions.md](25-additional-patterns-questions.md) | Cursor Pagination, Specification Pattern, Circuit Breaker, Regex, PHP 8.4 |
| 26 | [missing-design-patterns.md](26-missing-design-patterns.md) | Adapter, Chain of Responsibility, Command, State Machine, Mediator, Proxy, Builder |
| 27 | [laravel-ecosystem.md](27-laravel-ecosystem.md) | Livewire, Inertia.js, Pennant, Pulse, Reverb, Horizontal Scaling, OpenAPI/Swagger |

### Real Use Cases — Praktiki Problemlər (20-24, 28)
| # | Fayl | Use Case-lər |
|---|------|-------------|
| 20 | [real-world-scenarios.md](20-real-world-scenarios.md) | Double Charge fix, Slow API debug, 100K Bulk Email, 50M row Migration, DDoS müdafiə, 502 Debug, Memory Leak |
| 21 | [real-use-cases-ecommerce.md](21-real-use-cases-ecommerce.md) | Shopping Cart (guest merge), Order Pipeline (tam lifecycle), Stripe Payment (webhooks), Product Filtering, Coupon/Discount, Review & Rating |
| 22 | [real-use-cases-auth-rbac.md](22-real-use-cases-auth-rbac.md) | RBAC sistemi, JWT daxili işləyişi, Email Verification, Password Reset, Audit Log, 2FA, Social Login |
| 23 | [real-use-cases-notifications-uploads.md](23-real-use-cases-notifications-uploads.md) | Multi-channel Notifications, Image Processing Pipeline, Real-time Chat, Async Export, Booking/Reservation, Redis Leaderboard |
| 24 | [real-use-cases-advanced.md](24-real-use-cases-advanced.md) | Sliding Window Rate Limiter, Distributed Lock, Flash Sale Inventory, Webhook Delivery sistemi, Multi-step Wizard, Scheduled Pricing |
| 28 | [real-use-cases-more.md](28-real-use-cases-more.md) | Multi-language (i18n), Subscription/Recurring Payment, Team Invitation, Wallet/Balance, Activity Feed, Maintenance Mode, Retry Pattern, Tagging |

---

**Ümumi: 28 fayl, ~220+ sual və use case, tam kod nümunələri ilə**

## Mövzu Xəritəsi

```
PHP Core & Internals ──────── 01, 14, 25
OOP & Design Patterns ─────── 02, 18, 25, 26
Laravel Framework ─────────── 03, 11, 27
Database ──────────────────── 04, 15
API Development ─��─────────── 05, 16, 25
Queues & Async ────────────── 06
Testing ───────────────────── 07
Security ──────────────────── 08, 22
Performance ───────────────── 09, 24
Architecture & DevOps ─────── 10, 13, 27
Search & Data ─────────────── 19
Live Coding ───────────────── 17
System Design ─────────────── 12, 20
Clean Code ��───────────────── 18

Real Use Cases:
├── E-Commerce ────────────── 21 (Cart, Order, Payment, Coupon, Review)
├── Auth & Security ───────── 22 (RBAC, 2FA, OAuth, Audit, JWT)
├── Communication ─────────── 23 (Chat, Notifications, Export, Booking)
├── Advanced Patterns ─────── 24 (Flash Sale, Webhooks, Distributed Lock)
└── Business Logic ────────── 28 (Subscription, Wallet, Feed, Teams, i18n)
```

## Hazırlıq Strategiyası

1. **Əvvəlcə** — 01, 02, 03 (PHP core + OOP + Laravel fundamentals)
2. **Sonra** — 04, 05, 08 (Database + API + Security)
3. **Dərinləş** — 14, 15, 16 (Internals + DB deep dive + Microservices)
4. **Praktika** — 17 (Coding challenges)
5. **Real problemlər** — 20, 21, 22, 23, 24, 28 (Use cases)
6. **Fərqlən** — 26, 27 (Design patterns + Ecosystem)
