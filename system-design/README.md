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

## Necə İstifadə Etməli?
1. Hər mövzunu sıra ilə oxuyun
2. PHP/Laravel nümunələrini praktik edin
3. Interview suallarını cavablayın
4. Real-world nümunələri araşdırın
