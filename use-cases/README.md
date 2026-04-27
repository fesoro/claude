# Backend Use Cases

Real backend layihələrindəki konkret problem-həll ssenariləri. Hər fayl bir produksiyon problemi götürür, **niyə yaranır** izah edir, **step-by-step həll** göstərir.

Kod nümunələri **PHP/Laravel** ilə yazılmışdır, lakin mövzular **language-agnostic**-dir — prinsiplər hər backend texnologiyasına tətbiq olunur.

**Toplam: 60 use case**

---

## Səviyyə Göstəriciləri

| Göstərici | Mürəkkəblik |
|-----------|-------------|
| ⭐⭐ Middle | Tez-tez rastlaşılan, standart həll var |
| ⭐⭐⭐ Senior | Arxitektura qərarı tələb edir, trade-off-lar var |
| ⭐⭐⭐⭐ Lead | Sistemin bir hissəsini dizayn etmək lazımdır |

---

## ⭐⭐ Middle — Tez-Tez Rastlaşılan Problemlər

### External Services & Timeouts
1. [External API Timeout — gecikmə, fallback, retry](01-external-api-timeout.md)
2. [Idempotent API Design — duplicate request prevention](34-idempotent-api-design.md)
3. [Request Deduplication](47-request-deduplication.md)
4. [Token Refresh Strategy — silent refresh, race condition](43-token-refresh-strategy.md)

### Files & Uploads
5. [File Upload & Processing — S3, queued processing](04-file-upload-processing.md)
6. [Async Image Processing — resize, format, CDN](42-async-image-processing.md)

### Rate Limiting & Throttling
7. [Rate Limiting an API — Redis token bucket, sliding window](03-rate-limiting-api.md)

### Notifications
8. [Notification System — multi-channel, queued](05-notification-system.md)
9. [Real-Time Notifications — websockets, SSE, polling](21-real-time-notifications.md)

### Configuration & Environment
10. [Config Management & Env — secrets, .env, feature flags](28-config-management-env.md)

### Data Operations
11. [Audit Logging — who did what, when, why](10-audit-logging.md)
12. [Soft Delete & GDPR](45-soft-delete-gdpr.md)
13. [PII Masking & Anonymization](44-pii-masking-anonymization.md)
14. [Large Dataset Export — CSV/Excel with millions of rows](22-large-dataset-export.md)
15. [Bulk Import Processing — CSV import, validation, queued](25-bulk-import-processing.md)

---

## ⭐⭐⭐ Senior — Arxitektura Qərarı Tələb Edir

### Payments & Financial
16. [Double Charge Prevention — idempotency key, distributed lock](02-double-charge-prevention.md)
17. [Complex Payment Flow — multi-step, saga pattern](11-complex-payment-flow.md)
18. [Subscription Billing & Proration](35-subscription-billing-proration.md)
19. [Loyalty Points & Credit System](36-loyalty-points-credit-system.md)
20. [Coupon & Discount Engine](37-coupon-discount-engine.md)
21. [Multi-Currency Pricing](52-multi-currency-pricing.md)

### Database & Caching
22. [Distributed Locking — Redis, Redlock, deadlock](09-distributed-locking.md)
23. [Distributed Cache Invalidation — consistency strategies](18-distributed-cache-invalidation.md)
24. [Zero-Downtime DB Migration — expand-contract, online schema](19-zero-downtime-db-migration.md)
25. [Hot Key & Thundering Herd](40-hot-key-thundering-herd.md)
26. [Pagination at Scale — offset vs cursor vs keyset](39-pagination-at-scale.md)

### Multi-Tenancy & Scaling
27. [Multi-Tenant SaaS — separate DB, shared DB, schema](08-multi-tenant-saas.md)
28. [Multi-Tenant Data Isolation](27-multi-tenant-data-isolation.md)
29. [Geo Routing & Localization](53-geo-routing-localization.md)
30. [Session Management at Scale](23-session-management-at-scale.md)

### Inventory & Business Logic
31. [Inventory Management — overselling prevention, reservation](06-inventory-management.md)
32. [Price Calculation Engine — complex rules, discounts](26-price-calculation-engine.md)
33. [Approval Workflow — multi-step, state machine](38-approval-workflow.md)
34. [Multi-Step Checkout — cart, stock, payment, fulfillment](33-multi-step-checkout.md)

### Performance
35. [High-Throughput API — bottleneck analysis, optimization](12-high-throughput-api.md)
36. [Write-Heavy Leaderboard — Redis sorted sets, batching](41-write-heavy-leaderboard.md)
37. [Graceful Degradation — circuit breaker, fallback, degraded mode](46-graceful-degradation.md)

### Communication & Real-Time
38. [Webhook Delivery System — retry, signing, delivery guarantee](20-webhook-delivery-system.md)
39. [Real-Time Chat System — websockets, presence, history](49-realtime-chat-system.md)

### Search & GraphQL
40. [Search & Autocomplete — Elasticsearch, Redis, prefix index](07-search-autocomplete.md)
41. [Search & Ranking — relevance, boosts, personalization](14-search-and-ranking.md)
42. [GraphQL N+1 Problem & DataLoader](48-graphql-n-plus-1.md)

### Security & Auth
43. [GDPR & Audit Compliance — data retention, right to erasure](16-audit-and-gdpr-compliance.md)

### Feature Management
44. [Feature Flags & A/B Testing](17-feature-flags-and-ab-testing.md)
45. [Canary & Feature Flag Rollout](51-canary-feature-flag-rollout.md)

---

## ⭐⭐⭐⭐ Lead — Sistem Hissəsi Dizayn Etmək

### Background Jobs & Workflows
46. [Background Job Orchestration — saga, step functions](13-background-job-orchestration.md)
47. [Onboarding Workflow Orchestration — multi-step, async](24-onboarding-workflow-orchestration.md)
48. [Delayed & Scheduled Jobs](57-delayed-scheduled-jobs.md)
49. [Scheduled Reports & Batch Processing](60-scheduled-reports-batch.md)

### Microservices & Event-Driven
50. [Event-Driven Microservices — domain events, sagas](29-event-driven-microservices.md)
51. [gRPC Internal Communication](30-grpc-internal-communication.md)
52. [Service Registry & Health Check](31-service-registry-health-check.md)
53. [Database per Service](32-database-per-service.md)
54. [CDC Pipeline — Debezium, Outbox → Kafka](50-cdc-pipeline.md)
55. [Message Replay & Event Store](58-message-replay-event-store.md)
56. [BFF Pattern — Mobile vs Web API](59-bff-pattern-mobile-web.md)

### Deployment & Infrastructure
57. [Multi-Region Deployment — active-active, failover](15-multi-region-deployment.md)

### Advanced Business Features
58. [Social Feed & Ranking — fanout on write/read, personalization](54-social-feed-ranking.md)
59. [Recommendation Engine — collaborative filtering, content-based](55-recommendation-engine.md)
60. [Fraud Detection Real-Time — rules engine, ML scoring](56-fraud-detection-realtime.md)

---

## Tematik Axtarış

```
Ödəniş problemi?        → 16, 17, 18, 19, 20, 21
Cache problemi?         → 23, 25
Multi-tenancy?          → 27, 28
Fayl/upload?            → 5, 6
Real-time?              → 9, 38, 39
GraphQL?                → 42
Microservices?          → 50-56
Background jobs?        → 46, 47, 48, 49
Search?                 → 40, 41
Auth/Security?          → 43
Database migration?     → 24
Inventory?              → 31, 34
Fraud/Compliance?       → 43, 60
```
