# System Design

Backend developer üçün sistemli system design öyrənmə yolu.
Hər mövzu real interview sualları, PHP/Laravel nümunələri və trade-off analizi ilə.

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
| 01 | [01-load-balancing.md](01-load-balancing.md) | Load Balancing — L4/L7, round-robin, HAProxy |
| 02 | [02-api-gateway.md](02-api-gateway.md) | API Gateway — routing, auth, rate limit |
| 03 | [03-caching-strategies.md](03-caching-strategies.md) | Caching Strategies — cache-aside, write-through, Redis |
| 04 | [04-cdn.md](04-cdn.md) | CDN — edge locations, push/pull, CloudFront |
| 05 | [05-message-queues.md](05-message-queues.md) | Message Queues — RabbitMQ, Kafka, SQS |
| 06 | [06-rate-limiting.md](06-rate-limiting.md) | Rate Limiting — token bucket, sliding window |
| 07 | [08-scaling.md](08-scaling.md) | Scaling — horizontal/vertical, stateless design |
| 08 | [09-database-design.md](09-database-design.md) | Database Design — indexing, normalization, CAP |
| 09 | [31-back-of-envelope-estimation.md](31-back-of-envelope-estimation.md) | Back-of-the-Envelope Estimation — QPS, storage, bandwidth |

---

## ⭐⭐ Middle

Praktik sistem komponentləri və klassik design interview sualları.

| # | Fayl | Mövzu |
|---|------|-------|
| 10 | [07-circuit-breaker.md](07-circuit-breaker.md) | Circuit Breaker — states, fallback, bulkhead |
| 11 | [10-microservices.md](10-microservices.md) | Microservices — service boundaries, saga, Lumen |
| 12 | [11-event-driven-architecture.md](11-event-driven-architecture.md) | Event-Driven Architecture — CQRS, event sourcing |
| 13 | [12-search-systems.md](12-search-systems.md) | Search Systems — Elasticsearch, inverted index |
| 14 | [13-notification-system.md](13-notification-system.md) | Notification System — push, email, SMS |
| 15 | [14-authentication-authorization.md](14-authentication-authorization.md) | Auth & Authorization — OAuth2, JWT, Sanctum |
| 16 | [15-file-storage.md](15-file-storage.md) | File Storage — S3, pre-signed URLs |
| 17 | [16-logging-monitoring.md](16-logging-monitoring.md) | Logging & Monitoring — ELK, Prometheus, Grafana |
| 18 | [17-real-time-systems.md](17-real-time-systems.md) | Real-Time Systems — WebSocket, SSE, Broadcasting |
| 19 | [18-url-shortener-design.md](18-url-shortener-design.md) | URL Shortener Design — Base62, hash collision |
| 20 | [19-chat-system-design.md](19-chat-system-design.md) | Chat System Design — presence, groups, delivery |
| 21 | [20-payment-system-design.md](20-payment-system-design.md) | Payment System Design — idempotency, webhooks |
| 22 | [21-task-scheduler-design.md](21-task-scheduler-design.md) | Task Scheduler Design — distributed scheduling |
| 23 | [22-feed-system-design.md](22-feed-system-design.md) | Feed System Design — fan-out, timeline, ranking |
| 24 | [23-video-streaming-design.md](23-video-streaming-design.md) | Video Streaming Design — HLS, transcoding, ABR |
| 25 | [24-e-commerce-design.md](24-e-commerce-design.md) | E-Commerce Design — cart, inventory, orders |
| 26 | [27-proxy-patterns.md](27-proxy-patterns.md) | Proxy Patterns — forward/reverse, Nginx+PHP-FPM |
| 27 | [29-service-discovery.md](29-service-discovery.md) | Service Discovery — Consul, etcd, DNS-based |
| 28 | [41-sql-vs-nosql-selection.md](41-sql-vs-nosql-selection.md) | SQL vs NoSQL Selection — decision tree |

---

## ⭐⭐⭐ Senior

Distributed system fundamentals, advanced design problems, real-world architecture.

| # | Fayl | Mövzu |
|---|------|-------|
| 29 | [25-distributed-systems.md](25-distributed-systems.md) | Distributed Systems — consensus, leader election |
| 30 | [26-data-partitioning.md](26-data-partitioning.md) | Data Partitioning — consistent hashing, sharding |
| 31 | [28-idempotency.md](28-idempotency.md) | Idempotency — idempotency keys, retry safety |
| 32 | [30-disaster-recovery.md](30-disaster-recovery.md) | Disaster Recovery — RTO/RPO, failover, multi-region |
| 33 | [32-consistency-patterns.md](32-consistency-patterns.md) | Consistency Patterns — strong, eventual, causal |
| 34 | [33-probabilistic-data-structures.md](33-probabilistic-data-structures.md) | Probabilistic Data Structures — Bloom, HLL, Count-Min |
| 35 | [35-multi-tenancy.md](35-multi-tenancy.md) | Multi-Tenancy — tenant isolation, schema-per-tenant |
| 36 | [36-recommendation-system.md](36-recommendation-system.md) | Recommendation System — collaborative filtering |
| 37 | [37-ride-sharing-design.md](37-ride-sharing-design.md) | Ride-Sharing Design — geo-indexing, surge pricing |
| 38 | [38-stock-trading-system.md](38-stock-trading-system.md) | Stock Trading System — order matching, low-latency |
| 39 | [39-booking-system.md](39-booking-system.md) | Booking System Design — availability, overbooking |
| 40 | [40-object-oriented-design.md](40-object-oriented-design.md) | OOD — parking lot, elevator, ATM, SOLID |
| 41 | [42-cap-pacelc.md](42-cap-pacelc.md) | CAP & PACELC Theorem — consistency/availability trade-offs |
| 42 | [43-database-replication.md](43-database-replication.md) | Database Replication — leader-follower, quorum |
| 43 | [44-sla-slo-sli.md](44-sla-slo-sli.md) | SLA, SLO, SLI & Error Budgets — availability math |
| 44 | [48-web-crawler-design.md](48-web-crawler-design.md) | Web Crawler Design — URL frontier, dedup |
| 45 | [49-distributed-cache-design.md](49-distributed-cache-design.md) | Distributed Cache Design — Redis cluster, hot keys |
| 46 | [51-collaborative-editing-design.md](51-collaborative-editing-design.md) | Collaborative Editing — Google Docs, OT vs CRDT |
| 47 | [52-dropbox-design.md](52-dropbox-design.md) | Dropbox / Drive Design — chunking, delta sync |
| 48 | [53-metrics-monitoring-design.md](53-metrics-monitoring-design.md) | Metrics & Monitoring — Prometheus TSDB, alerting |
| 49 | [55-api-design-patterns.md](55-api-design-patterns.md) | API Design Patterns — REST/gRPC/GraphQL/WebSocket |
| 50 | [58-live-streaming-design.md](58-live-streaming-design.md) | Live Streaming Design — Twitch/YT Live, LL-HLS |
| 51 | [59-ticketing-system-design.md](59-ticketing-system-design.md) | Ticketing System — virtual queue, seat lock |
| 52 | [60-matchmaking-system-design.md](60-matchmaking-system-design.md) | Matchmaking Design — Elo/TrueSkill, bucketing |
| 53 | [61-social-graph-design.md](61-social-graph-design.md) | Social Graph Design — FoF, celebrity shard |
| 54 | [62-email-system-design.md](62-email-system-design.md) | Email System Design — SMTP, threading, SPF/DKIM |
| 55 | [63-ad-serving-design.md](63-ad-serving-design.md) | Ad Serving Design — RTB, targeting, attribution |
| 56 | [64-food-delivery-design.md](64-food-delivery-design.md) | Food Delivery Design — dispatch, ETA, batching |
| 57 | [68-distributed-id-generation.md](68-distributed-id-generation.md) | Distributed ID Generation — Snowflake, ULID, UUIDv7 |
| 58 | [71-geospatial-system-design.md](71-geospatial-system-design.md) | Geospatial Design — geohash, quadtree, S2/H3 |
| 59 | [72-deployment-strategies.md](72-deployment-strategies.md) | Deployment Strategies — blue-green, canary, shadow |
| 60 | [73-news-aggregator-design.md](73-news-aggregator-design.md) | News Aggregator Design — hot score, Wilson |
| 61 | [74-github-like-design.md](74-github-like-design.md) | GitHub-like Platform — repo storage, PR workflow |
| 62 | [75-typeahead-autocomplete.md](75-typeahead-autocomplete.md) | Typeahead / Autocomplete — Trie, top-K, ranking |
| 63 | [76-document-search-design.md](76-document-search-design.md) | Document Search — Algolia-like, facets, synonyms |
| 64 | [77-digital-wallet-design.md](77-digital-wallet-design.md) | Digital Wallet Design — double-entry ledger, KYC |
| 65 | [79-push-notification-backend.md](79-push-notification-backend.md) | Push Notification Backend — APNs/FCM, fan-out |

---

## ⭐⭐⭐⭐ Lead

Mürəkkəb distributed pattern-lər, internals, production-critical sistemlər.

| # | Fayl | Mövzu |
|---|------|-------|
| 66 | [34-crdt.md](34-crdt.md) | CRDT — conflict-free replicated data types |
| 67 | [45-distributed-transactions-saga.md](45-distributed-transactions-saga.md) | Distributed Transactions & Saga — 2PC, 3PC, orchestration |
| 68 | [46-cdc-outbox-pattern.md](46-cdc-outbox-pattern.md) | CDC & Outbox Pattern — Debezium, transactional outbox |
| 69 | [47-service-mesh.md](47-service-mesh.md) | Service Mesh — Istio, Envoy, mTLS, traffic mgmt |
| 70 | [50-key-value-store-design.md](50-key-value-store-design.md) | Key-Value Store Design — Dynamo, vector clocks |
| 71 | [54-stream-processing.md](54-stream-processing.md) | Stream Processing — Lambda/Kappa, Flink, exactly-once |
| 72 | [56-chaos-engineering.md](56-chaos-engineering.md) | Chaos Engineering — fault injection, game days |
| 73 | [57-backpressure-load-shedding.md](57-backpressure-load-shedding.md) | Backpressure & Load Shedding — CoDel, priority drop |
| 74 | [65-distributed-file-system.md](65-distributed-file-system.md) | Distributed File System — HDFS/GFS, NameNode |
| 75 | [66-time-series-database.md](66-time-series-database.md) | Time-Series Database — Gorilla compression, downsampling |
| 76 | [67-data-lake-warehouse-mesh.md](67-data-lake-warehouse-mesh.md) | Data Lake / Warehouse / Mesh — Iceberg, medallion |
| 77 | [69-vector-database-design.md](69-vector-database-design.md) | Vector Database Design — HNSW, IVF, RAG, pgvector |
| 78 | [70-feature-store-design.md](70-feature-store-design.md) | Feature Store Design — Feast, offline/online, PiT join |
| 79 | [78-ai-inference-serving.md](78-ai-inference-serving.md) | AI Inference Serving — Triton/vLLM, dynamic batching |
| 80 | [80-video-conferencing-design.md](80-video-conferencing-design.md) | Video Conferencing — WebRTC, SFU vs MCU, simulcast |
| 81 | [81-pubsub-system-design.md](81-pubsub-system-design.md) | Pub/Sub System — topics, delivery semantics, filtering |
| 82 | [82-webhook-delivery-system.md](82-webhook-delivery-system.md) | Webhook Delivery — retry, HMAC, circuit breaker |
| 83 | [83-distributed-locks-deep-dive.md](83-distributed-locks-deep-dive.md) | Distributed Locks — Redlock critique, fencing tokens |
| 84 | [85-multi-region-active-active.md](85-multi-region-active-active.md) | Multi-Region Active-Active — geo writes, conflict resolution |
| 85 | [86-edge-computing.md](86-edge-computing.md) | Edge Computing — CF Workers, Lambda@Edge, D1/KV |
| 86 | [87-live-auction-design.md](87-live-auction-design.md) | Live Auction Design — bid concurrency, anti-sniping |
| 87 | [88-sharded-counters.md](88-sharded-counters.md) | Sharded Counters & Probabilistic Counting — hot-row |
| 88 | [89-hot-cold-storage-tiering.md](89-hot-cold-storage-tiering.md) | Hot/Cold Storage Tiering — S3 lifecycle, TWCS |
| 89 | [94-feature-flags-progressive-delivery.md](94-feature-flags-progressive-delivery.md) | Feature Flags & Progressive Delivery — LaunchDarkly |

---

## ⭐⭐⭐⭐⭐ Architect

Consensus algoritmaları, sistem internals, replica sync — dərin texniki anlayış.

| # | Fayl | Mövzu |
|---|------|-------|
| 90 | [84-raft-paxos-consensus.md](84-raft-paxos-consensus.md) | Raft, Paxos, ZAB Consensus — leader election, log replication |
| 91 | [90-elasticsearch-internals.md](90-elasticsearch-internals.md) | Elasticsearch Internals — Lucene segments, ILM, CCR |
| 92 | [91-distributed-tracing-deep-dive.md](91-distributed-tracing-deep-dive.md) | Distributed Tracing — OpenTelemetry, W3C, sampling |
| 93 | [92-anti-entropy-merkle-trees.md](92-anti-entropy-merkle-trees.md) | Anti-Entropy & Merkle Trees — hinted handoff, read repair |
| 94 | [93-cdc-streaming-architectures.md](93-cdc-streaming-architectures.md) | CDC Streaming Architectures — schema registry, DLQ |

---

## Reading Paths

### Backend Developer üçün Əsas Yol (Junior → Senior)
01 → 02 → 03 → 05 → 06 → 09 → 07 → 10 → 11 → 14 → 16 → 25 → 26 → 42 → 43 → 28

### System Design Interview Hazırlığı
31 (estimation) → 01 → 03 → 05 → 18 → 19 → 22 → 37 → 52 → 74 → 75

### Distributed Systems Yolu
25 → 26 → 32 → 43 → 42 → 45 → 46 → 50 → 83 → 85 → 84 → 92

### Data Systems Yolu
09 → 41 → 43 → 66 → 67 → 69 → 70 → 90 → 93

### Real-Time & Streaming Yolu
17 → 05 → 11 → 54 → 57 → 58 → 80 → 81 → 82
