# Backend Use Cases

Real backend layihələrindəki konkret problem-həll ssenariləri. Hər fayl bir produksiyon problemi götürür, **niyə yaranır** izah edir, **step-by-step həll** göstərir.

Kod nümunələri **PHP/Laravel** ilə yazılmışdır, lakin mövzular **language-agnostic**-dir — prinsiplər hər backend texnologiyasına tətbiq olunur.

**Toplam: 69 use case**

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
7. [Presigned URLs & Secure File Serving — S3 temporary access, signed routes](70-presigned-url-secure-file-serving.md)

### Rate Limiting & Throttling
8. [Rate Limiting an API — Redis token bucket, sliding window](03-rate-limiting-api.md)

### Notifications
9. [Notification System — multi-channel, queued](05-notification-system.md)
10. [Real-Time Notifications — websockets, SSE, polling](21-real-time-notifications.md)

### Configuration & Environment
11. [Config Management & Env — secrets, .env, feature flags](28-config-management-env.md)

### API & Auth
12. [API Versioning — URL prefix, Accept header, deprecation](61-api-versioning.md)
13. [OAuth2 Social Login — Google/GitHub flow, account linking](63-oauth2-social-login.md)

### Data Operations
14. [Audit Logging — who did what, when, why](10-audit-logging.md)
15. [Soft Delete & GDPR](45-soft-delete-gdpr.md)
16. [PII Masking & Anonymization](44-pii-masking-anonymization.md)
17. [Large Dataset Export — CSV/Excel with millions of rows](22-large-dataset-export.md)
18. [Bulk Import Processing — CSV import, validation, queued](25-bulk-import-processing.md)

---

## ⭐⭐⭐ Senior — Arxitektura Qərarı Tələb Edir

### Payments & Financial
19. [Double Charge Prevention — idempotency key, distributed lock](02-double-charge-prevention.md)
20. [Complex Payment Flow — multi-step, saga pattern](11-complex-payment-flow.md)
21. [Subscription Billing & Proration](35-subscription-billing-proration.md)
22. [Loyalty Points & Credit System](36-loyalty-points-credit-system.md)
23. [Coupon & Discount Engine](37-coupon-discount-engine.md)
24. [Multi-Currency Pricing](52-multi-currency-pricing.md)

### Database & Caching
25. [Distributed Locking — Redis, Redlock, deadlock](09-distributed-locking.md)
26. [Distributed Cache Invalidation — consistency strategies](18-distributed-cache-invalidation.md)
27. [Zero-Downtime DB Migration — expand-contract, online schema](19-zero-downtime-db-migration.md)
28. [Hot Key & Thundering Herd](40-hot-key-thundering-herd.md)
29. [Pagination at Scale — offset vs cursor vs keyset](39-pagination-at-scale.md)
30. [Optimistic Locking & Concurrent Edit — version column, ETag](65-optimistic-locking-concurrent-edit.md)
31. [Search Index Sync — MySQL → Elasticsearch/Meilisearch](67-search-index-sync.md)

### Multi-Tenancy & Scaling
32. [Multi-Tenant SaaS — separate DB, shared DB, schema](08-multi-tenant-saas.md)
33. [Multi-Tenant Data Isolation](27-multi-tenant-data-isolation.md)
34. [Geo Routing & Localization](53-geo-routing-localization.md)
35. [Session Management at Scale](23-session-management-at-scale.md)

### Inventory & Business Logic
36. [Inventory Management — overselling prevention, reservation](06-inventory-management.md)
37. [Price Calculation Engine — complex rules, discounts](26-price-calculation-engine.md)
38. [Approval Workflow — multi-step, state machine](38-approval-workflow.md)
39. [Multi-Step Checkout — cart, stock, payment, fulfillment](33-multi-step-checkout.md)

### Performance
40. [High-Throughput API — bottleneck analysis, optimization](12-high-throughput-api.md)
41. [Write-Heavy Leaderboard — Redis sorted sets, batching](41-write-heavy-leaderboard.md)
42. [Graceful Degradation — circuit breaker, fallback, degraded mode](46-graceful-degradation.md)

### Communication & Real-Time
43. [Webhook Delivery System — retry, signing, delivery guarantee](20-webhook-delivery-system.md)
44. [Real-Time Chat System — websockets, presence, history](49-realtime-chat-system.md)

### Search & GraphQL
45. [Search & Autocomplete — Elasticsearch, Redis, prefix index](07-search-autocomplete.md)
46. [Search & Ranking — relevance, boosts, personalization](14-search-and-ranking.md)
47. [GraphQL N+1 Problem & DataLoader](48-graphql-n-plus-1.md)

### Security & Auth
48. [Two-Factor Authentication — TOTP, recovery codes, remember device](62-two-factor-authentication.md)
49. [Email Deliverability — SPF/DKIM/DMARC, bounce handling](64-email-deliverability.md)
50. [GDPR & Audit Compliance — data retention, right to erasure](16-audit-and-gdpr-compliance.md)

### Feature Management
51. [Feature Flags & A/B Testing](17-feature-flags-and-ab-testing.md)
52. [Canary & Feature Flag Rollout](51-canary-feature-flag-rollout.md)

---

## ⭐⭐⭐⭐ Lead — Sistem Hissəsi Dizayn Etmək

### Background Jobs & Workflows
53. [Background Job Orchestration — saga, step functions](13-background-job-orchestration.md)
54. [Onboarding Workflow Orchestration — multi-step, async](24-onboarding-workflow-orchestration.md)
55. [Delayed & Scheduled Jobs](57-delayed-scheduled-jobs.md)
56. [Scheduled Reports & Batch Processing](60-scheduled-reports-batch.md)

### Microservices & Event-Driven
57. [Event-Driven Microservices — domain events, sagas](29-event-driven-microservices.md)
58. [Service Registry & Health Check](31-service-registry-health-check.md)
59. [Database per Service](32-database-per-service.md)
60. [CDC Pipeline — Debezium, Outbox → Kafka](50-cdc-pipeline.md)
61. [Message Replay & Event Store](58-message-replay-event-store.md)
62. [BFF Pattern — Mobile vs Web API](59-bff-pattern-mobile-web.md)

### Database & Data
63. [DB Read Replica Routing — sticky reads, lag-aware](66-db-read-replica-routing.md)
64. [Data Archival & Table Partitioning — RANGE partitioning, cold storage](69-data-archival-table-partitioning.md)

### Deployment & Infrastructure
65. [Multi-Region Deployment — active-active, failover](15-multi-region-deployment.md)

### Advanced Business Features
66. [Social Feed & Ranking — fanout on write/read, personalization](54-social-feed-ranking.md)
67. [Recommendation Engine — collaborative filtering, content-based](55-recommendation-engine.md)
68. [Fraud Detection Real-Time — rules engine, ML scoring](56-fraud-detection-realtime.md)
69. [Content Moderation Pipeline — rules + AI + human review + appeals](68-content-moderation-pipeline.md)

---

## Tematik Axtarış

```
Ödəniş problemi?           → 19, 20, 21, 22, 23, 24
Cache problemi?            → 26, 28
Multi-tenancy?             → 32, 33
Fayl/upload?               → 5, 6, 7
Real-time?                 → 10, 43, 44
GraphQL?                   → 47
Microservices?             → 57-62
Background jobs?           → 53, 54, 55, 56
Search?                    → 31, 45, 46
Auth/Security?             → 12, 13, 48, 49, 50
Database migration?        → 27
Database performance?      → 29, 30, 63, 64
Inventory?                 → 36, 39
Fraud/Compliance?          → 50, 68, 69
Content moderation?        → 69
API versioning?            → 12
Email?                     → 49
2FA / OAuth?               → 48, 13
```
