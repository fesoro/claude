# System Design

Backend developer üçün sistemli system design öyrənmə yolu.
Hər mövzu real interview sualları, PHP/Laravel nümunələri və trade-off analizi ilə.
**123 fayl** — Junior-dan Architect-ə qədər.

## Səviyyə Sistemi

| Level | Label |
|-------|-------|
| Junior | ⭐ |
| Middle | ⭐⭐ |
| Senior | ⭐⭐⭐ |
| Lead | ⭐⭐⭐⭐ |
| Architect | ⭐⭐⭐⭐⭐ |

---

## ⭐ Junior

Hər backend developer bilməli olan əsas system design konseptləri.

| # | Fayl | Mövzu |
|---|------|-------|
| 001 | [001-load-balancing.md](001-load-balancing.md) | Load Balancing — L4/L7, round-robin, HAProxy |
| 002 | [002-api-gateway.md](002-api-gateway.md) | API Gateway — routing, auth, rate limit |
| 003 | [003-caching-strategies.md](003-caching-strategies.md) | Caching Strategies — cache-aside, write-through, Redis |
| 004 | [004-cdn.md](004-cdn.md) | CDN — edge locations, push/pull, CloudFront |
| 005 | [005-message-queues.md](005-message-queues.md) | Message Queues — RabbitMQ, Kafka, SQS |
| 006 | [006-rate-limiting.md](006-rate-limiting.md) | Rate Limiting — token bucket, sliding window |
| 008 | [008-scaling.md](008-scaling.md) | Scaling — horizontal/vertical, stateless design |
| 009 | [009-database-design.md](009-database-design.md) | Database Design — indexing, normalization, CAP |
| 031 | [031-back-of-envelope-estimation.md](031-back-of-envelope-estimation.md) | Back-of-the-Envelope Estimation — QPS, storage, bandwidth |

---

## ⭐⭐ Middle

Praktik sistem komponentləri, klassik design interview sualları və mesajlaşma əsasları.

### Sistem Komponentləri

| # | Fayl | Mövzu |
|---|------|-------|
| 007 | [007-circuit-breaker.md](007-circuit-breaker.md) | Circuit Breaker — states, fallback, bulkhead |
| 010 | [010-microservices.md](010-microservices.md) | Microservices — service boundaries, saga, Lumen |
| 011 | [011-event-driven-architecture.md](011-event-driven-architecture.md) | Event-Driven Architecture — CQRS, event sourcing |
| 012 | [012-search-systems.md](012-search-systems.md) | Search Systems — Elasticsearch, inverted index |
| 013 | [013-notification-system.md](013-notification-system.md) | Notification System — push, email, SMS |
| 014 | [014-authentication-authorization.md](014-authentication-authorization.md) | Auth & Authorization — OAuth2, JWT, Sanctum |
| 015 | [015-file-storage.md](015-file-storage.md) | File Storage — S3, pre-signed URLs |
| 016 | [016-logging-monitoring.md](016-logging-monitoring.md) | Logging & Monitoring — ELK, Prometheus, Grafana |
| 017 | [017-real-time-systems.md](017-real-time-systems.md) | Real-Time Systems — WebSocket, SSE, Broadcasting |
| 027 | [027-proxy-patterns.md](027-proxy-patterns.md) | Proxy Patterns — forward/reverse, Nginx+PHP-FPM |
| 029 | [029-service-discovery.md](029-service-discovery.md) | Service Discovery — Consul, etcd, DNS-based |
| 041 | [041-sql-vs-nosql-selection.md](041-sql-vs-nosql-selection.md) | SQL vs NoSQL Selection — decision tree |

### Klassik Design Interview Problemləri

| # | Fayl | Mövzu |
|---|------|-------|
| 018 | [018-url-shortener-design.md](018-url-shortener-design.md) | URL Shortener Design — Base62, hash collision |
| 019 | [019-chat-system-design.md](019-chat-system-design.md) | Chat System Design — presence, groups, delivery |
| 020 | [020-payment-system-design.md](020-payment-system-design.md) | Payment System Design — idempotency, webhooks |
| 021 | [021-task-scheduler-design.md](021-task-scheduler-design.md) | Task Scheduler Design — distributed scheduling |
| 022 | [022-feed-system-design.md](022-feed-system-design.md) | Feed System Design — fan-out, timeline, ranking |
| 023 | [023-video-streaming-design.md](023-video-streaming-design.md) | Video Streaming Design — HLS, transcoding, ABR |
| 024 | [024-e-commerce-design.md](024-e-commerce-design.md) | E-Commerce Design — cart, inventory, orders |

### Mesajlaşma Əsasları

| # | Fayl | Mövzu |
|---|------|-------|
| 095 | [095-redis.md](095-redis.md) | Redis Fundamentals — data types, persistence, use cases |
| 096 | [096-rabbitmq-vs-redis.md](096-rabbitmq-vs-redis.md) | RabbitMQ vs Redis — messaging comparison |
| 097 | [097-redis-streams.md](097-redis-streams.md) | Redis Streams — consumer groups, XADD/XREAD |

---

## ⭐⭐⭐ Senior

Distributed system fundamentals, advanced design problems, real-world architecture.

### Distributed Systems Əsasları

| # | Fayl | Mövzu |
|---|------|-------|
| 025 | [025-distributed-systems.md](025-distributed-systems.md) | Distributed Systems — consensus, leader election |
| 026 | [026-data-partitioning.md](026-data-partitioning.md) | Data Partitioning — consistent hashing, sharding |
| 028 | [028-idempotency.md](028-idempotency.md) | Idempotency — idempotency keys, retry safety |
| 030 | [030-disaster-recovery.md](030-disaster-recovery.md) | Disaster Recovery — RTO/RPO, failover, multi-region |
| 032 | [032-consistency-patterns.md](032-consistency-patterns.md) | Consistency Patterns — strong, eventual, causal |
| 033 | [033-probabilistic-data-structures.md](033-probabilistic-data-structures.md) | Probabilistic Data Structures — Bloom, HLL, Count-Min |
| 035 | [035-multi-tenancy.md](035-multi-tenancy.md) | Multi-Tenancy — tenant isolation, schema-per-tenant |
| 042 | [042-cap-pacelc.md](042-cap-pacelc.md) | CAP & PACELC Theorem — consistency/availability trade-offs |
| 043 | [043-database-replication.md](043-database-replication.md) | Database Replication — leader-follower, quorum |
| 044 | [044-sla-slo-sli.md](044-sla-slo-sli.md) | SLA, SLO, SLI & Error Budgets — availability math |
| 040 | [040-object-oriented-design.md](040-object-oriented-design.md) | OOD — parking lot, elevator, ATM, SOLID |
| 055 | [055-api-design-patterns.md](055-api-design-patterns.md) | API Design Patterns — REST/gRPC/GraphQL/WebSocket |

### Microservices & Mesajlaşma Pattern-ləri

| # | Fayl | Mövzu |
|---|------|-------|
| 098 | [098-service-discovery.md](098-service-discovery.md) | Service Discovery Deep Dive — Consul, etcd, k8s DNS |
| 099 | [099-microservices-communication.md](099-microservices-communication.md) | Microservices Communication — sync/async, gRPC, events |
| 100 | [100-competing-consumers.md](100-competing-consumers.md) | Competing Consumers Pattern — parallel processing |
| 101 | [101-retry-patterns.md](101-retry-patterns.md) | Retry Patterns — exponential backoff, jitter |
| 102 | [102-transactional-messaging.md](102-transactional-messaging.md) | Transactional Messaging — outbox, at-least-once |
| 103 | [103-dead-letter-queue.md](103-dead-letter-queue.md) | Dead Letter Queue — poison messages, DLQ routing |
| 104 | [104-idempotency-patterns.md](104-idempotency-patterns.md) | Idempotency Patterns — consumer-side deduplication |
| 105 | [105-data-consistency-patterns.md](105-data-consistency-patterns.md) | Data Consistency Patterns — saga, outbox, CQRS |
| 106 | [106-distributed-transactions.md](106-distributed-transactions.md) | Distributed Transactions — 2PC, saga, compensations |
| 107 | [107-rabbitmq-deep-dive.md](107-rabbitmq-deep-dive.md) | RabbitMQ Deep Dive — exchanges, bindings, HA |
| 108 | [108-redis-deep-dive.md](108-redis-deep-dive.md) | Redis Deep Dive — internals, cluster, Lua scripts |

### Complex System Design Problemləri

| # | Fayl | Mövzu |
|---|------|-------|
| 036 | [036-recommendation-system.md](036-recommendation-system.md) | Recommendation System — collaborative filtering |
| 037 | [037-ride-sharing-design.md](037-ride-sharing-design.md) | Ride-Sharing Design — geo-indexing, surge pricing |
| 038 | [038-stock-trading-system.md](038-stock-trading-system.md) | Stock Trading System — order matching, low-latency |
| 039 | [039-booking-system.md](039-booking-system.md) | Booking System Design — availability, overbooking |
| 048 | [048-web-crawler-design.md](048-web-crawler-design.md) | Web Crawler Design — URL frontier, dedup |
| 049 | [049-distributed-cache-design.md](049-distributed-cache-design.md) | Distributed Cache Design — Redis cluster, hot keys |
| 051 | [051-collaborative-editing-design.md](051-collaborative-editing-design.md) | Collaborative Editing — Google Docs, OT vs CRDT |
| 052 | [052-dropbox-design.md](052-dropbox-design.md) | Dropbox / Drive Design — chunking, delta sync |
| 053 | [053-metrics-monitoring-design.md](053-metrics-monitoring-design.md) | Metrics & Monitoring — Prometheus TSDB, alerting |
| 058 | [058-live-streaming-design.md](058-live-streaming-design.md) | Live Streaming Design — Twitch/YT Live, LL-HLS |
| 059 | [059-ticketing-system-design.md](059-ticketing-system-design.md) | Ticketing System — virtual queue, seat lock |
| 060 | [060-matchmaking-system-design.md](060-matchmaking-system-design.md) | Matchmaking Design — Elo/TrueSkill, bucketing |
| 061 | [061-social-graph-design.md](061-social-graph-design.md) | Social Graph Design — FoF, celebrity shard |
| 062 | [062-email-system-design.md](062-email-system-design.md) | Email System Design — SMTP, threading, SPF/DKIM |
| 063 | [063-ad-serving-design.md](063-ad-serving-design.md) | Ad Serving Design — RTB, targeting, attribution |
| 064 | [064-food-delivery-design.md](064-food-delivery-design.md) | Food Delivery Design — dispatch, ETA, batching |
| 068 | [068-distributed-id-generation.md](068-distributed-id-generation.md) | Distributed ID Generation — Snowflake, ULID, UUIDv7 |
| 071 | [071-geospatial-system-design.md](071-geospatial-system-design.md) | Geospatial Design — geohash, quadtree, S2/H3 |
| 072 | [072-deployment-strategies.md](072-deployment-strategies.md) | Deployment Strategies — blue-green, canary, shadow |
| 073 | [073-news-aggregator-design.md](073-news-aggregator-design.md) | News Aggregator Design — hot score, Wilson |
| 074 | [074-github-like-design.md](074-github-like-design.md) | GitHub-like Platform — repo storage, PR workflow |
| 075 | [075-typeahead-autocomplete.md](075-typeahead-autocomplete.md) | Typeahead / Autocomplete — Trie, top-K, ranking |
| 076 | [076-document-search-design.md](076-document-search-design.md) | Document Search — Algolia-like, facets, synonyms |
| 077 | [077-digital-wallet-design.md](077-digital-wallet-design.md) | Digital Wallet Design — double-entry ledger, KYC |
| 079 | [079-push-notification-backend.md](079-push-notification-backend.md) | Push Notification Backend — APNs/FCM, fan-out |
| 109 | [109-feed-timeline-system.md](109-feed-timeline-system.md) | Feed & Timeline System — fan-out-on-write vs read |
| 110 | [110-booking-system.md](110-booking-system.md) | Booking System Advanced — distributed locking, seats |
| 111 | [111-distributed-job-scheduler.md](111-distributed-job-scheduler.md) | Distributed Job Scheduler — cron, priority, at-least-once |

---

## ⭐⭐⭐⭐ Lead

Mürəkkəb distributed pattern-lər, internals, production-critical sistemlər.

### Advanced Distributed Patterns

| # | Fayl | Mövzu |
|---|------|-------|
| 034 | [034-crdt.md](034-crdt.md) | CRDT — conflict-free replicated data types |
| 045 | [045-distributed-transactions-saga.md](045-distributed-transactions-saga.md) | Distributed Transactions & Saga — 2PC, 3PC, orchestration |
| 046 | [046-cdc-outbox-pattern.md](046-cdc-outbox-pattern.md) | CDC & Outbox Pattern — Debezium, transactional outbox |
| 047 | [047-service-mesh.md](047-service-mesh.md) | Service Mesh — Istio, Envoy, mTLS, traffic mgmt |
| 050 | [050-key-value-store-design.md](050-key-value-store-design.md) | Key-Value Store Design — Dynamo, vector clocks |
| 054 | [054-stream-processing.md](054-stream-processing.md) | Stream Processing — Lambda/Kappa, Flink, exactly-once |
| 056 | [056-chaos-engineering.md](056-chaos-engineering.md) | Chaos Engineering — fault injection, game days |
| 057 | [057-backpressure-load-shedding.md](057-backpressure-load-shedding.md) | Backpressure & Load Shedding — CoDel, priority drop |
| 065 | [065-distributed-file-system.md](065-distributed-file-system.md) | Distributed File System — HDFS/GFS, NameNode |
| 066 | [066-time-series-database.md](066-time-series-database.md) | Time-Series Database — Gorilla compression, downsampling |
| 067 | [067-data-lake-warehouse-mesh.md](067-data-lake-warehouse-mesh.md) | Data Lake / Warehouse / Mesh — Iceberg, medallion |
| 069 | [069-vector-database-design.md](069-vector-database-design.md) | Vector Database Design — HNSW, IVF, RAG, pgvector |
| 070 | [070-feature-store-design.md](070-feature-store-design.md) | Feature Store Design — Feast, offline/online, PiT join |
| 078 | [078-ai-inference-serving.md](078-ai-inference-serving.md) | AI Inference Serving — Triton/vLLM, dynamic batching |
| 080 | [080-video-conferencing-design.md](080-video-conferencing-design.md) | Video Conferencing — WebRTC, SFU vs MCU, simulcast |
| 081 | [081-pubsub-system-design.md](081-pubsub-system-design.md) | Pub/Sub System — topics, delivery semantics, filtering |
| 082 | [082-webhook-delivery-system.md](082-webhook-delivery-system.md) | Webhook Delivery — retry, HMAC, circuit breaker |
| 083 | [083-distributed-locks-deep-dive.md](083-distributed-locks-deep-dive.md) | Distributed Locks — Redlock critique, fencing tokens |
| 085 | [085-multi-region-active-active.md](085-multi-region-active-active.md) | Multi-Region Active-Active — geo writes, conflict resolution |
| 086 | [086-edge-computing.md](086-edge-computing.md) | Edge Computing — CF Workers, Lambda@Edge, D1/KV |
| 087 | [087-live-auction-design.md](087-live-auction-design.md) | Live Auction Design — bid concurrency, anti-sniping |
| 088 | [088-sharded-counters.md](088-sharded-counters.md) | Sharded Counters & Probabilistic Counting — hot-row |
| 089 | [089-hot-cold-storage-tiering.md](089-hot-cold-storage-tiering.md) | Hot/Cold Storage Tiering — S3 lifecycle, TWCS |
| 094 | [094-feature-flags-progressive-delivery.md](094-feature-flags-progressive-delivery.md) | Feature Flags & Progressive Delivery — LaunchDarkly |

### Messaging Internals & Observability

| # | Fayl | Mövzu |
|---|------|-------|
| 112 | [112-cell-based-architecture.md](112-cell-based-architecture.md) | Cell-Based Architecture — blast radius, cell routing |
| 113 | [113-message-prioritization.md](113-message-prioritization.md) | Message Prioritization — priority queues, HOL blocking |
| 114 | [114-leader-election.md](114-leader-election.md) | Leader Election — ZooKeeper, etcd, bully algorithm |
| 115 | [115-kafka-vs-rabbitmq.md](115-kafka-vs-rabbitmq.md) | Kafka vs RabbitMQ — deep comparison, when to use |
| 116 | [116-message-schema-evolution.md](116-message-schema-evolution.md) | Message Schema Evolution — Avro, Protobuf, backward compat |
| 117 | [117-correlation-id-patterns.md](117-correlation-id-patterns.md) | Correlation ID Patterns — request tracing, propagation |
| 118 | [118-audit-log-system.md](118-audit-log-system.md) | Audit Log System — tamper-proof, event replay |

### Production System Design

| # | Fayl | Mövzu |
|---|------|-------|
| 119 | [119-ecommerce-order-system.md](119-ecommerce-order-system.md) | E-Commerce Order System — state machine, inventory lock |
| 120 | [120-ab-testing-platform.md](120-ab-testing-platform.md) | A/B Testing Platform — bucketing, statistical significance |
| 121 | [121-realtime-analytics.md](121-realtime-analytics.md) | Real-Time Analytics — OLAP, Druid, ClickHouse, Lambda |

---

## ⭐⭐⭐⭐⭐ Architect

Consensus algoritmaları, sistem internals, global-scale design.

| # | Fayl | Mövzu |
|---|------|-------|
| 084 | [084-raft-paxos-consensus.md](084-raft-paxos-consensus.md) | Raft, Paxos, ZAB Consensus — leader election, log replication |
| 090 | [090-elasticsearch-internals.md](090-elasticsearch-internals.md) | Elasticsearch Internals — Lucene segments, ILM, CCR |
| 091 | [091-distributed-tracing-deep-dive.md](091-distributed-tracing-deep-dive.md) | Distributed Tracing — OpenTelemetry, W3C, sampling |
| 092 | [092-anti-entropy-merkle-trees.md](092-anti-entropy-merkle-trees.md) | Anti-Entropy & Merkle Trees — hinted handoff, read repair |
| 093 | [093-cdc-streaming-architectures.md](093-cdc-streaming-architectures.md) | CDC Streaming Architectures — schema registry, DLQ |
| 122 | [122-i18n-l10n-design.md](122-i18n-l10n-design.md) | i18n & l10n Design — locale routing, translation pipeline |
| 123 | [123-workflow-orchestration.md](123-workflow-orchestration.md) | Workflow Orchestration — Temporal, Conductor, durable execution |

---

## Reading Paths

### Backend Developer Əsas Yolu (Junior → Senior)
`001` → `002` → `003` → `005` → `006` → `009` → `007` → `010` → `011` → `014` → `016` → `025` → `026` → `042` → `043` → `028`

### System Design Interview Hazırlığı
`031` (estimation) → `001` → `003` → `005` → `018` → `019` → `022` → `037` → `059` → `074` → `075` → `109` → `110`

### Distributed Systems Yolu
`025` → `026` → `032` → `043` → `042` → `045` → `046` → `050` → `083` → `085` → `084` → `092` → `114`

### Messaging & Queuing Yolu
`005` → `095` → `096` → `097` → `100` → `101` → `102` → `103` → `107` → `108` → `113` → `115` → `116`

### Data Consistency Yolu
`028` → `032` → `104` → `105` → `106` → `045` → `046` → `034` → `084` → `092`

### Data Systems Yolu
`009` → `041` → `043` → `066` → `067` → `069` → `070` → `090` → `093` → `121`

### Real-Time & Streaming Yolu
`017` → `005` → `011` → `054` → `057` → `058` → `080` → `081` → `082` → `097` → `121`

### Microservices & Architecture Yolu
`010` → `047` → `098` → `099` → `102` → `105` → `106` → `112` → `117` → `118` → `123`

### Production Engineering Yolu (Senior → Lead)
`044` → `056` → `072` → `082` → `094` → `113` → `117` → `118` → `120` → `121` → `123`
