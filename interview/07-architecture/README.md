# Interview Prep: Architecture (07)

Bu folder backend arxitekturası üzrə interview suallarını əhatə edir. Hər fayl real müsahibə suallarına, güclü cavablara, kod nümunələrinə və praktik tapşırıqlara malikdir. Hədəf: 5+ il təcrübəli backend developer-ın senior/lead/architect müsahibəsinə hazırlığı.

---

## Mövzular

### Senior ⭐⭐⭐

| Fayl | Mövzu |
|------|-------|
| [01-monolith-vs-microservices.md](01-monolith-vs-microservices.md) | Monolith vs Microservices Trade-offs |
| [03-clean-architecture.md](03-clean-architecture.md) | Clean Architecture |
| [04-hexagonal-architecture.md](04-hexagonal-architecture.md) | Hexagonal Architecture (Ports & Adapters) |
| [08-api-first-design.md](08-api-first-design.md) | API-First Design |
| [12-feature-flags.md](12-feature-flags.md) | Feature Flags |
| [16-background-job-patterns.md](16-background-job-patterns.md) | Background Job Patterns |

### Lead ⭐⭐⭐⭐

| Fayl | Mövzu |
|------|-------|
| [02-domain-driven-design.md](02-domain-driven-design.md) | Domain-Driven Design (DDD) |
| [06-cqrs-architecture.md](06-cqrs-architecture.md) | CQRS Architecture Deep Dive |
| [09-backend-for-frontend.md](09-backend-for-frontend.md) | Backend for Frontend (BFF) |
| [10-strangler-fig.md](10-strangler-fig.md) | Strangler Fig Pattern |
| [13-blue-green-canary.md](13-blue-green-canary.md) | Blue-Green and Canary Deployments |
| [14-zero-downtime-deployments.md](14-zero-downtime-deployments.md) | Zero-Downtime Deployments |
| [15-technical-debt-management.md](15-technical-debt-management.md) | Technical Debt Management |

### Architect ⭐⭐⭐⭐⭐

| Fayl | Mövzu |
|------|-------|
| [05-event-sourcing.md](05-event-sourcing.md) | Event Sourcing |
| [07-service-mesh.md](07-service-mesh.md) | Service Mesh |
| [11-saga-pattern.md](11-saga-pattern.md) | Saga Pattern |

---

## Oxuma Yolları (Reading Paths)

### Microservices Müsahibəsinə Hazırlıq
```
01 → 02 → 07 → 11 → 06 → 09
```
Monolith → DDD → Service Mesh → Saga → CQRS → BFF

### Clean Code / Architecture Müsahibəsinə Hazırlıq
```
03 → 04 → 02 → 05 → 06
```
Clean Architecture → Hexagonal → DDD → Event Sourcing → CQRS

### Deployment / Operations Müsahibəsinə Hazırlıq
```
12 → 13 → 14 → 10 → 15
```
Feature Flags → Blue-Green/Canary → Zero-Downtime → Strangler Fig → Debt Management

### API / Design Müsahibəsinə Hazırlıq
```
08 → 16 → 09 → 01 → 02
```
API-First → Background Jobs → BFF → Monolith/Micro → DDD

### Sıfırdan Başlamaq (Tam Ardıcıllıq)
```
01 → 03 → 04 → 02 → 08 → 16 → 09 → 05 → 06 → 11 → 07 → 12 → 13 → 14 → 10 → 15
```

---

## Ən Çox Soruşulan 5 Sual

1. **Monolith vs Microservices** — hər müsahibədə çıxır, kontekst əsaslı cavab verin
2. **DDD Bounded Context** — service boundary necə müəyyən edilir
3. **Event Sourcing vs CRUD** — nə vaxt Event Sourcing seçmək lazımdır
4. **Blue-Green vs Canary** — deployment strategy seçimi
5. **Technical Debt** — management-ə necə izah edersiniz, prioritetlər necə qurulur
6. **Background Job dizaynı** — retry, DLQ, idempotency, fan-out, cron locking
