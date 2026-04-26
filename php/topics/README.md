# PHP / Laravel Topics

PHP backend developer üçün vacib bütün mövzular — dil əsaslarından enterprise arxitekturasına qədər.

**Toplam: 200 mövzu** — Junior-dan Architect-ə, sadədən mürəkkəbə.

---

## Səviyyə Göstəriciləri

| Göstərici | Kimə uyğundur |
|-----------|--------------|
| ⭐ **Junior** | PHP ilə yeni tanış / 0-2 illik (001–022) |
| ⭐⭐ **Middle** | Design patterns, Laravel / 2-4 il (023–083) |
| ⭐⭐⭐ **Senior** | Arxitektura, distributed systems / 4-6+ il (084–173) |
| ⭐⭐⭐⭐ **Lead** | Mürəkkəb sistem dizaynı / 7+ il (174–196) |
| ⭐⭐⭐⭐⭐ **Architect** | Enterprise arxitekturası (197–200) |

---

## ⭐ Junior — PHP Fundamentals (001–022)

### PHP Dil Əsasları
1. [OOP — 4 Prinsip, Abstract, Interface](001-oop.md)
2. [PHP Types & Strict Types](002-php-types-and-strict-types.md)
3. [Error Handling & Exceptions](003-error-handling-and-exceptions.md)
4. [PHP Generators — yield, memory-efficient iteration](004-php-generators.md)
5. [Magic Methods — __get, __set, __call, __invoke](005-magic-methods-deep-dive.md)
6. [Type Juggling & Gotchas — == vs ===](006-type-juggling-gotchas.md)
7. [Traits — composition vs inheritance](007-traits-deep-dive.md)
8. [PHP Enums (8.1+)](008-php-enums-deep-dive.md)
9. [PHP 8.3 / 8.4 New Features](009-php-83-84-features.md)
10. [PHP Attributes (8.0+)](010-php-attributes.md)
11. [PHP SPL — data structures, iterators](011-php-spl.md)
12. [PHP Streams — wrappers, filters](012-php-streams.md)
13. [PHP JIT Compiler](013-php-jit-compiler.md)
14. [PHP Process Model — FPM, request lifecycle](014-php-process-model.md)
15. [OPcache & Bytecode](015-php-internals-opcache.md)
16. [PHP CLI Application](016-php-cli-application.md)

### Fundamental Prinsiplər
17. [SOLID Principles in PHP/Laravel](017-solid-principles.md)
18. [REST Best Practices](018-rest-best-practices.md)

### Tooling
19. [Composer & Package Management](019-composer-and-package-management.md)
20. [PHP Profiling Tools — Blackfire, Tideways](020-php-profiling-tools.md)
21. [Pest — modern PHP testing](021-pest-framework.md)
22. [Xdebug — debugging & profiling](022-xdebug-deep-dive.md)

---

## ⭐⭐ Middle — Design, Laravel, DB Basics (023–083)

### Design Patterns & OOP
23. [Value Objects](023-value-objects.md)
24. [DTO — Data Transfer Object](024-dto.md)
25. [Design Patterns — GoF patterns in PHP](025-design-patterns.md)
26. [Repository & Singleton Patterns](026-repository-singleton-patterns.md)
27. [Dependency Injection vs Service Locator](027-dependency-injection-vs-service-locator.md)
28. [PSR Standards — PSR-1/2/3/4/7/11/12/15](028-psr-standards.md)
29. [PSR-14 Event Dispatcher](029-psr-14-event-dispatcher.md)
30. [PSR-7/15 Middleware](030-psr-middleware-deep-dive.md)
31. [Code Smells & Refactoring](031-code-smells-refactoring.md)

### Laravel Ecosystem
32. [PHP Reflection API](032-reflection.md)
33. [Service Provider & Service Container](033-service-provider.md)
34. [Laravel Internals — lifecycle, bindings](034-laravel-internals.md)
35. [Symfony Service Container](035-symfony-service-container.md)
36. [DI Container Comparison — Laravel vs Symfony vs PHP-DI](036-di-container-comparison.md)
37. [Autoloading Internals — PSR-4, classmap](037-autoloading-internals.md)
38. [PHP Execution Models — FPM vs CLI vs async](038-php-execution-models.md)
39. [Composer Advanced — plugins, scripts, private packages](039-composer-advanced.md)
40. [Laravel Telescope & Clockwork](040-laravel-telescope-clockwork.md)
41. [Laravel Auth — Sanctum, Passport, JWT](041-laravel-auth-sanctum-passport-jwt.md)
42. [Livewire & Inertia.js](042-livewire-inertia.md)
43. [Artisan Commands — custom commands, scheduling](043-artisan-commands-deep.md)
44. [Package Development](044-package-development.md)
45. [Doctrine ORM](045-doctrine-orm-deep.md)
46. [Symfony Console](046-symfony-console-deep.md)

### Database & ORM
47. [MySQL vs PostgreSQL](047-mysql-vs-postgresql.md)
48. [Database Normalization](048-database-normalization.md)
49. [NoSQL vs SQL](049-nosql-vs-sql.md)
50. [N+1 Problem](050-n-plus-one-problem.md)
51. [ORM Deep Dive — Eloquent, Active Record pattern](051-orm-deep-dive.md)
52. [Database Testing Strategies](052-database-testing-strategies.md)
53. [Full-Text Search — MySQL FTS, Elasticsearch basics](053-full-text-search.md)

### Storage & Queues
54. [Redis — data structures, use cases](054-redis.md)
55. [Caching — strategies, invalidation](055-caching.md)
56. [RabbitMQ vs Redis for Queues](056-rabbitmq-vs-redis.md)
57. [Queues & Jobs — Laravel queue basics](057-queues.md)
58. [Laravel Horizon — queue monitoring](058-laravel-horizon-queue.md)
59. [Redis Streams Deep Dive](059-redis-streams-deep.md)
60. [Elasticsearch with PHP](060-elasticsearch-php-deep.md)
61. [MongoDB with PHP/Laravel](061-mongodb-with-php.md)

### Performance & PHP Internals
62. [PHP Performance Profiling](062-php-performance-profiling.md)
63. [PHP Fibers & Async](063-php-fibers-async.md)
64. [PHP Memory & Heap](064-php-memory-heap.md)
65. [PHP-FPM Configuration & Tuning](065-php-fpm-configuration.md)
66. [Long-Running PHP Processes](066-long-running-php-processes.md)

### Security & Auth
67. [Security Best Practices in PHP](067-security-best-practices.md)
68. [OAuth2, JWT & OIDC](068-oauth2-jwt-oidc.md)
69. [OWASP Top 10 for PHP](069-owasp-top10-php.md)

### APIs & HTTP
70. [API Versioning Strategies](070-api-versioning.md)
71. [gRPC & Protobuf](071-grpc-protobuf.md)
72. [Webhook Design](072-webhook-design.md)
73. [HTTP Caching — Cache-Control, ETags, CDN](073-http-caching.md)
74. [HTTP Client Patterns — retry, timeout, circuit breaker](074-http-client-patterns.md)
75. [Saloon — API SDK builder](075-saloon-api-sdk.md)
76. [API Pagination — offset, cursor, keyset](076-api-pagination-patterns.md)

### Observability & Infrastructure
77. [Logging & Monitoring](077-logging-and-monitoring.md)
78. [Monolith vs Microservices vs SOA](078-monolith-vs-others.md)
79. [Integration & Contract Testing](079-integration-and-contract-testing.md)
80. [CI/CD & Deployment Basics](080-ci-cd-and-deployment.md)
81. [Git Advanced](081-git-advanced.md)
82. [Feature Flags](082-feature-flags.md)
83. [12-Factor App](083-12-factor-app.md)

---

## ⭐⭐⭐ Senior — Architecture & Distributed Systems (084–173)

### Architecture Patterns
84. [DDD — Strategic & Tactical Design](084-ddd.md)
85. [Event-Driven Architecture](085-event-driven-architecture.md)
86. [TDD — Test-Driven Development](086-tdd.md)
87. [CQRS](087-cqrs.md)
88. [Event Sourcing](088-event-sourcing.md)
89. [Modular Monolith](089-modular-monolith.md)
90. [Microservices vs Modular Monolith](090-microservices-vs-modular-monolith.md)
91. [Layered Architecture](091-layered-architectures.md)
92. [Onion Architecture](092-onion-architecture.md)
93. [Hexagonal Architecture — Ports & Adapters](093-hexagonal-architecture.md)
94. [N-Tier Architecture](094-n-tier-architecture.md)
95. [Choreography vs Orchestration](095-choreography-vs-orchestration.md)
96. [GRASP Principles](096-grasp-principles.md)
97. [Clean Code in PHP](097-clean-code-php.md)

### DDD Advanced
98. [DDD Aggregates — design, invariants](098-ddd-aggregates-deep-dive.md)
99. [DDD Domain Events](099-ddd-domain-events.md)
100. [DDD Bounded Context Integration](100-ddd-bounded-context-integration.md)
101. [Specification Pattern](101-specification-pattern.md)
102. [Shared Kernel](102-shared-kernel.md)
103. [Domain Service vs Application Service](103-domain-service-vs-application-service.md)
104. [Aggregate Design Heuristics](104-aggregate-design-heuristics.md)
105. [Policy & Handler Pattern](105-policy-handler-pattern.md)
106. [Command/Query Bus & Mediator](106-command-query-bus-mediator.md)
107. [State Machine & Workflow](107-state-machine-workflow.md)

### Microservices Patterns
108. [API Gateway Pattern](108-api-gateway-pattern.md)
109. [Strangler Fig Pattern](109-strangler-fig-pattern.md)
110. [Circuit Breaker Pattern](110-circuit-breaker-pattern.md)
111. [Bulkhead Pattern](111-bulkhead-pattern.md)
112. [Service Discovery](112-microservices-service-discovery.md)
113. [Microservices Communication](113-microservices-communication.md)
114. [Anti-Corruption Layer, Sidecar, Ambassador](114-anti-corruption-layer-sidecar-ambassador.md)
115. [Service Mesh](115-service-mesh.md)
116. [Search Architecture](116-search-architecture.md)
117. [BFF — Backend for Frontend](117-bff-pattern.md)
118. [API Composition Pattern](118-api-composition-pattern.md)

### Distributed Patterns
119. [Saga Pattern](119-saga-pattern.md)
120. [Outbox Pattern](120-outbox-pattern.md)
121. [API Rate Limiting Deep Dive](121-api-rate-limiting-deep-dive.md)
122. [Caching Strategies Deep Dive](122-caching-strategies-deep-dive.md)
123. [Competing Consumers](123-competing-consumers.md)
124. [Retry, Retry Storm & Graceful Shutdown](124-retry-storm-graceful-shutdown.md)
125. [Transactional Messaging](125-transactional-messaging.md)
126. [Dead Letter Queue](126-dead-letter-queue.md)
127. [CAP Theorem & BASE vs ACID](127-cap-theorem-base-acid.md)
128. [Distributed Locks](128-distributed-locks.md)
129. [Idempotency Patterns](129-idempotency-patterns.md)
130. [Multi-Tenancy Strategies](130-multi-tenancy.md)
131. [Zero-Downtime Deployment](131-zero-downtime-deployment.md)
132. [Backpressure Patterns](132-backpressure-patterns.md)
133. [Data Consistency Patterns](133-data-consistency-patterns.md)
134. [Distributed Transactions Alternatives](134-distributed-transactions-alternatives.md)
135. [Concurrency Patterns](135-concurrency-patterns.md)

### Database Advanced
136. [Database Replication](136-database-replication.md)
137. [Database Indexing Deep Dive](137-database-indexing-deep-dive.md)
138. [Database Transactions — isolation, deadlock](138-database-transactions.md)
139. [Database Query Optimization](139-database-query-optimization.md)
140. [Slow Query Log & Profiling](140-slow-query-log-profiling.md)
141. [Database Partitioning](141-database-partitioning.md)
142. [Database Connection Pooling](142-database-connection-pooling.md)
143. [Zero-Downtime Migrations](143-zero-downtime-migrations.md)
144. [CDC — Change Data Capture & Debezium](144-cdc-debezium.md)

### Observability & Security
145. [Observability — Metrics, Tracing, Logs](145-observability-metrics-tracing.md)
146. [Grafana & Observability Stack](146-grafana-observability.md)
147. [OpenTelemetry Deep Dive](147-opentelemetry-deep-dive.md)
148. [Secrets Management](148-secrets-management.md)
149. [API Security Patterns](149-api-security-patterns.md)
150. [Authorization — RBAC, ABAC](150-authorization-rbac-abac.md)

### PHP/Laravel Advanced
151. [Payment Gateway Design](151-payment-gateway-design.md)
152. [GraphQL with PHP/Laravel](152-graphql-php.md)
153. [Laravel Octane, RoadRunner & FrankenPHP](153-laravel-octane-roadrunner-frankenphp.md)
154. [WebSockets Deep Dive](154-websockets-deep-dive.md)
155. [Swoole & ReactPHP](155-swoole-reactphp.md)
156. [Consistent Hashing, Bloom Filter, HyperLogLog](156-consistent-hashing-bloom-hll.md)
157. [Chaos Engineering](157-chaos-engineering.md)
158. [SLA / SLO / SLI / Error Budget](158-sla-slo-sli-error-budget.md)
159. [Load Balancing Algorithms](159-load-balancing-algorithms.md)
160. [Deployment Strategies — blue/green, canary, rolling](160-deployment-strategies.md)
161. [CDN & Edge Caching](161-cdn-edge-caching.md)
162. [Docker Deep Dive](162-docker-deep-dive.md)
163. [RabbitMQ Deep Dive](163-rabbitmq-deep-dive.md)
164. [Redis Deep Dive](164-redis-deep-dive.md)
165. [Docker & Kubernetes Basics](165-docker-kubernetes-basics.md)

### System Design — Component Level
166. [Contract-First API Design](166-contract-first-api-design.md)
167. [Real-Time Communication Patterns](167-real-time-communication-patterns.md)
168. [URL Shortener Design](168-url-shortener-design.md)
169. [Notification System Design](169-notification-system-design.md)
170. [File Upload & Storage System](170-file-upload-storage-system.md)
171. [Feed & Timeline System](171-feed-timeline-system.md)
172. [Booking & Reservation System](172-booking-reservation-system.md)
173. [Distributed Job Scheduler](173-distributed-job-scheduler.md)

---

## ⭐⭐⭐⭐ Lead — Complex System Design (174–196)

### Advanced Distributed Systems
174. [Two-Phase Commit (2PC)](174-two-phase-commit.md)
175. [Database Sharding](175-database-sharding.md)
176. [Event Sourcing + CQRS Combined](176-event-sourcing-cqrs.md)
177. [CQRS Read Model & Projections](177-cqrs-read-model-projection.md)
178. [Materialized View, CDC & Polyglot Persistence](178-materialized-view-cdc-polyglot.md)
179. [Cell-Based Architecture & Shared Nothing](179-cell-based-architecture-shared-nothing.md)
180. [Message Prioritization](180-message-prioritization.md)
181. [Leader Election & Consensus (Raft/Paxos)](181-leader-election-consensus.md)
182. [Kubernetes Deep Dive](182-kubernetes-deep-dive.md)
183. [Kafka vs RabbitMQ](183-kafka-vs-rabbitmq.md)
184. [Message Schema Evolution](184-message-schema-evolution.md)
185. [Correlation & Causation IDs](185-correlation-causation-id.md)
186. [Enterprise Integration Patterns (EIP)](186-eip-patterns.md)
187. [Event Sourcing — Snapshots & Time-Travel Queries](187-event-sourcing-snapshots.md)
188. [Time-Series Databases](188-timeseries-databases.md)
189. [Vector Databases & Embeddings](189-vector-databases.md)
190. [Technical Debt & Fitness Functions](190-technical-debt-fitness-functions.md)

### Complex System Designs
191. [Audit Log System](191-audit-log-system.md)
192. [Chat Application Design](192-chat-application-design.md)
193. [API Gateway Implementation](193-api-gateway-implementation.md)
194. [Rate Limiter System Design](194-rate-limiter-system-design.md)
195. [E-Commerce Order System](195-e-commerce-order-system.md)
196. [A/B Testing Platform](196-ab-testing-platform.md)

---

## ⭐⭐⭐⭐⭐ Architect — Enterprise Architecture (197–200)

197. [Real-Time Analytics Pipeline](197-real-time-analytics-pipeline.md)
198. [Multi-Region Deployment](198-multi-region-deployment.md)
199. [Search Platform Design](199-search-platform-design.md)
200. [Webhook Delivery System](200-webhook-delivery-system.md)

---

## Reading Paths

### PHP/Laravel Interview Hazırlığı (2-3 həftə)
001–022 (Junior) → 023–031 (patterns) → 032–046 (Laravel) → 047–083 (DB, queues, infra) → 084–135 (architecture) → interview/ folder

### Laravel Ecosystem Deep Dive
033 → 034 → 030 → 043 → 041 → 029 → 036 → 037 → 039 → 044

### System Design (Backend Interview)
127 (CAP) → 128 (locks) → 129 (idempotency) → 119 (saga) → 120 (outbox) → 122 (caching) → 136 (replication) → 175 (sharding) → 168–173 (component designs) → 191–196 (full designs)

### Microservices & DDD Path
084 → 098–107 → 087 → 088 → 176 → 119 → 120 → 108 → 112–113 → 093 → 106–107

### Performance & Scalability
054–055 → 062 → 065 → 122 → 128 → 129 → 139–141 → 142 → 132 → 156 → 159
