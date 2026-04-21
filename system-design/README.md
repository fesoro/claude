# System Design Interview Hazırlığı

Bu folder system design interview suallarına hazırlaşmaq üçün yaradılıb.
Hər fayl ətraflı izahat, PHP/Laravel nümunələri və real-world misallar ehtiva edir.

## Mövzular

### Əsas Konseptlər (Core Concepts)
1. [Load Balancing](01-load-balancing.md) - L4/L7, algorithms, HAProxy, Nginx, AWS ELB
2. [API Gateway](02-api-gateway.md) - Routing, rate limiting, authentication, Kong
3. [Caching Strategies](03-caching-strategies.md) - Cache-aside, write-through, Redis, Laravel Cache
4. [CDN](04-cdn.md) - Edge locations, push/pull, CloudFront, Cloudflare
5. [Message Queues](05-message-queues.md) - RabbitMQ, Kafka, SQS, Laravel Queues
6. [Rate Limiting](06-rate-limiting.md) - Token bucket, sliding window, Laravel throttle
7. [Circuit Breaker](07-circuit-breaker.md) - States, fallback, bulkhead, retry patterns
8. [Scaling](08-scaling.md) - Horizontal/vertical, auto-scaling, stateless design
9. [Database Design](09-database-design.md) - Indexing, sharding, CAP theorem, Eloquent
10. [Microservices](10-microservices.md) - Service boundaries, saga pattern, Lumen

### Arxitektura Patternləri (Architecture Patterns)
11. [Event-Driven Architecture](11-event-driven-architecture.md) - Event sourcing, CQRS, Laravel Events
12. [Search Systems](12-search-systems.md) - Elasticsearch, inverted index, Laravel Scout
13. [Notification System](13-notification-system.md) - Push, email, SMS, Laravel Notifications
14. [Authentication & Authorization](14-authentication-authorization.md) - OAuth2, JWT, Sanctum, Passport
15. [File Storage](15-file-storage.md) - S3, pre-signed URLs, Laravel Storage
16. [Logging & Monitoring](16-logging-monitoring.md) - ELK, Prometheus, Grafana, Telescope
17. [Real-Time Systems](17-real-time-systems.md) - WebSocket, SSE, Laravel Broadcasting

### System Design Nümunələri (Design Examples)
18. [URL Shortener Design](18-url-shortener-design.md) - Base62, hash collision, analytics
19. [Chat System Design](19-chat-system-design.md) - Real-time messaging, presence, groups
20. [Payment System Design](20-payment-system-design.md) - Idempotency, webhooks, Laravel Cashier
21. [Task Scheduler Design](21-task-scheduler-design.md) - Distributed scheduling, Laravel Schedule
22. [Feed System Design](22-feed-system-design.md) - Fan-out, timeline, ranking
23. [Video Streaming Design](23-video-streaming-design.md) - HLS/DASH, transcoding, adaptive bitrate
24. [E-Commerce Design](24-e-commerce-design.md) - Cart, inventory, orders, payments

### Distributed Systems
25. [Distributed Systems](25-distributed-systems.md) - Consensus, leader election, distributed locks
26. [Data Partitioning](26-data-partitioning.md) - Consistent hashing, sharding strategies
27. [Proxy Patterns](27-proxy-patterns.md) - Forward/reverse proxy, Nginx + PHP-FPM
28. [Idempotency](28-idempotency.md) - Idempotency keys, retry safety
29. [Service Discovery](29-service-discovery.md) - Consul, etcd, DNS-based discovery
30. [Disaster Recovery](30-disaster-recovery.md) - RTO/RPO, failover, multi-region

### Advanced Topics
31. [Back-of-the-Envelope Estimation](31-back-of-envelope-estimation.md) - QPS, storage, bandwidth hesablamaları
32. [Consistency Patterns](32-consistency-patterns.md) - Strong, eventual, causal, read-your-writes
33. [Probabilistic Data Structures](33-probabilistic-data-structures.md) - Bloom filter, HyperLogLog, Count-min sketch
34. [CRDT](34-crdt.md) - Conflict-free Replicated Data Types, collaborative editing
35. [Multi-Tenancy](35-multi-tenancy.md) - Tenant isolation strategies, schema-per-tenant, row-level
36. [Recommendation System](36-recommendation-system.md) - Collaborative, content-based, hybrid
37. [Ride-Sharing Design](37-ride-sharing-design.md) - Geo-indexing, matching, surge pricing
38. [Stock Trading System](38-stock-trading-system.md) - Order matching, low-latency, market data
39. [Booking System](39-booking-system.md) - Availability, concurrency, overbooking prevention
40. [Object-Oriented Design](40-object-oriented-design.md) - Parking lot, elevator, ATM, chess, SOLID, patterns
41. [SQL vs NoSQL Selection](41-sql-vs-nosql-selection.md) - Decision tree, NoSQL types, real-world choices

### Core Fundamentals (Extra)
42. [CAP & PACELC Theorem](42-cap-pacelc.md) - Consistency, availability, partition, latency trade-offs
43. [Database Replication](43-database-replication.md) - Leader-follower, multi-leader, leaderless, quorum
44. [SLA, SLO, SLI & Error Budgets](44-sla-slo-sli.md) - SRE fundamentals, availability math, burn rates
45. [Distributed Transactions & Saga](45-distributed-transactions-saga.md) - 2PC, 3PC, Saga orchestration/choreography
46. [CDC & Outbox Pattern](46-cdc-outbox-pattern.md) - Change Data Capture, Debezium, transactional outbox
47. [Service Mesh](47-service-mesh.md) - Istio, Linkerd, Envoy sidecar, mTLS, traffic management

### Classic Design Interviews (Extra)
48. [Web Crawler Design](48-web-crawler-design.md) - URL frontier, politeness, dedup, Bloom filter
49. [Distributed Cache Design](49-distributed-cache-design.md) - Memcached/Redis cluster, sharding, hot keys
50. [Key-Value Store Design](50-key-value-store-design.md) - Dynamo paper, vector clocks, Merkle trees
51. [Collaborative Editing Design](51-collaborative-editing-design.md) - Google Docs: OT vs CRDT, presence
52. [Dropbox / Drive Design](52-dropbox-design.md) - Chunking, delta sync, content-addressable storage
53. [Metrics & Monitoring Design](53-metrics-monitoring-design.md) - Prometheus-like TSDB, cardinality, alerting

### Advanced Patterns (Extra)
54. [Stream Processing](54-stream-processing.md) - Lambda vs Kappa, Flink, windowing, exactly-once
55. [API Design Patterns](55-api-design-patterns.md) - REST/gRPC/GraphQL/WebSocket selection, versioning
56. [Chaos Engineering](56-chaos-engineering.md) - Netflix Chaos Monkey, fault injection, game days
57. [Backpressure & Load Shedding](57-backpressure-load-shedding.md) - Adaptive concurrency, CoDel, priority drop

### Əlavə Design Nümunələri (More Design Interviews)
58. [Live Streaming Design](58-live-streaming-design.md) - Twitch/YouTube Live, LL-HLS, WebRTC, chat fan-out
59. [Ticketing System Design](59-ticketing-system-design.md) - Ticketmaster: virtual queue, seat lock, fairness
60. [Matchmaking System Design](60-matchmaking-system-design.md) - Elo/TrueSkill, adaptive bucketing, dedicated servers
61. [Social Graph Design](61-social-graph-design.md) - Twitter/LinkedIn graph, FoF, celebrity shard
62. [Email System Design](62-email-system-design.md) - Gmail: SMTP, storage, threading, spam, SPF/DKIM/DMARC
63. [Ad Serving Design](63-ad-serving-design.md) - RTB, targeting, attribution, click fraud
64. [Food Delivery Design](64-food-delivery-design.md) - DoorDash/Wolt: dispatch, ETA, batching
65. [Distributed File System](65-distributed-file-system.md) - HDFS/GFS: NameNode, write pipeline, replication

### Data & AI Systems
66. [Time-Series Database](66-time-series-database.md) - TSDB internals: Gorilla compression, downsampling
67. [Data Lake / Warehouse / Mesh](67-data-lake-warehouse-mesh.md) - Lakehouse (Iceberg/Delta/Hudi), medallion, mesh
68. [Distributed ID Generation](68-distributed-id-generation.md) - Snowflake, ULID, UUIDv7, clock drift
69. [Vector Database Design](69-vector-database-design.md) - HNSW, IVF, embeddings, RAG, pgvector/Pinecone
70. [Feature Store Design](70-feature-store-design.md) - Feast/Tecton, offline vs online store, point-in-time join
71. [Geospatial System Design](71-geospatial-system-design.md) - Geohash, quadtree, S2/H3, PostGIS, Redis GEO
72. [Deployment Strategies](72-deployment-strategies.md) - Blue-green, canary, shadow, feature flags, rollback

### Platform & Product Design
73. [News Aggregator Design](73-news-aggregator-design.md) - Reddit/HN: hot score, Wilson, nested comments
74. [GitHub-like Platform Design](74-github-like-design.md) - Repo storage, PR workflow, Spokes replication
75. [Typeahead / Autocomplete](75-typeahead-autocomplete.md) - Trie, top-K per node, typo tolerance, ranking
76. [Document Search Design](76-document-search-design.md) - Algolia-like: instant search, facets, synonyms
77. [Digital Wallet Design](77-digital-wallet-design.md) - Double-entry ledger, multi-currency, KYC/AML
78. [AI Inference Serving](78-ai-inference-serving.md) - Triton/vLLM, dynamic batching, KV cache, MIG GPUs
79. [Push Notification Backend](79-push-notification-backend.md) - APNs/FCM/Web Push, fan-out, priority
80. [Video Conferencing Design](80-video-conferencing-design.md) - WebRTC, SFU vs MCU, simulcast, signaling
81. [Pub/Sub System Design](81-pubsub-system-design.md) - Topics/subscriptions, delivery semantics, filtering
82. [Webhook Delivery System](82-webhook-delivery-system.md) - Retry, HMAC sig, circuit breaker, SSRF guard

### Distributed Systems Deep Dives
83. [Distributed Locks Deep Dive](83-distributed-locks-deep-dive.md) - Redlock critique, ZK/etcd, fencing tokens
84. [Raft, Paxos, ZAB Consensus](84-raft-paxos-consensus.md) - Leader election, log replication, variants
85. [Multi-Region Active-Active](85-multi-region-active-active.md) - Geo-distributed writes, conflict resolution
86. [Edge Computing](86-edge-computing.md) - Cloudflare Workers, Lambda@Edge, V8 isolates, D1/KV
87. [Live Auction Design](87-live-auction-design.md) - eBay-like: bid concurrency, anti-sniping, closing

### Advanced Systems Internals
88. [Sharded Counters](88-sharded-counters.md) - Hot-row contention, sharded counters, HyperLogLog, Morris, Instagram/YouTube
89. [Hot/Cold Storage Tiering](89-hot-cold-storage-tiering.md) - S3 lifecycle, Cassandra TWCS, ClickHouse TTL, Glacier restore
90. [Elasticsearch Internals](90-elasticsearch-internals.md) - Lucene segments, translog, cluster state, ILM, CCR, rolling upgrade
91. [Distributed Tracing Deep Dive](91-distributed-tracing-deep-dive.md) - OpenTelemetry, W3C Trace Context, sampling, Tempo/Jaeger
92. [Anti-Entropy & Merkle Trees](92-anti-entropy-merkle-trees.md) - Hinted handoff, read repair, AAE, Cassandra nodetool repair
93. [CDC Streaming Architectures](93-cdc-streaming-architectures.md) - Debezium, schema registry, DLQ, exactly-once, slot retention
94. [Feature Flags & Progressive Delivery](94-feature-flags-progressive-delivery.md) - LaunchDarkly/Unleash, sticky bucketing, canary, OpenFeature

## Necə İstifadə Etməli?
1. Hər mövzunu sıra ilə oxuyun
2. PHP/Laravel nümunələrini praktik edin
3. Interview suallarını cavablayın
4. Real-world nümunələri araşdırın
