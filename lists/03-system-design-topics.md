## Scaling / compute

Horizontal vs Vertical Scaling
Stateless vs Stateful services
Auto-scaling (reactive, predictive, scheduled)
Cellular architecture (shuffle sharding)
Compute tiering (baseline + burst; spot + reserved)
Stateless Kubernetes (HPA, VPA, Cluster Autoscaler)
Serverless (Lambda, Cloud Run, Cloudflare Workers)
Edge computing vs centralized

## Load balancing / routing

Load Balancing (Round Robin, Least Connections, Least Response Time, IP Hash, Consistent Hash, Weighted, Random)
L4 vs L7 load balancer
Global LB / GSLB (DNS-based, Anycast)
Client-side LB (Ribbon, gRPC xds)
Sticky sessions (session affinity)
Health checks (active/passive, liveness vs readiness)
Blue-green, canary, shadow traffic
API Gateway
BFF (Backend for Frontend)
Reverse proxy (Nginx, Envoy, HAProxy, Caddy)
Service Mesh (Istio, Linkerd, Consul Connect)
mTLS
Ingress controllers (Nginx, Traefik, Kong, AWS ALB, Cilium)

## Caching

L1 CPU cache vs L2 application cache vs L3 CDN
Browser cache (Cache-Control, ETag, 304)
CDN cache (push vs pull)
Reverse proxy cache (Varnish, Nginx)
Application cache (Redis, Memcached, Caffeine, Ehcache)
Database query cache (usually unused; materialized views more reliable)
Cache patterns (cache-aside, read-through, write-through, write-behind / write-back, refresh-ahead)
Cache Invalidation Strategies (TTL, event-based, explicit)
Cache stampede / thundering herd (mutex, probabilistic early expiry)
Hot keys (shard, local cache, request coalescing)
Cache warming / preloading
Client-side caching (RESP3)
Negative caching (cache misses)

## Storage / database

SQL vs NoSQL seçimi
OLTP vs OLAP
Row store vs Column store (ClickHouse, BigQuery, Redshift)
ACID vs BASE
CAP Theorem, PACELC
Database Replication (Single-leader, Multi-leader, Leaderless)
Read Replica (async lag, read-your-writes)
Database Sharding (range, hash, directory, geographic)
Consistent Hashing (virtual nodes)
Partitioning vs Sharding (nuance)
Connection pooling (HikariCP, PgBouncer)
Index types (B-tree, Hash, GIN, GiST, BRIN)
Secondary index in distributed DB
Primary vs replica promotion
Split-brain
Quorum (R+W>N)
Vector clocks, version vectors
CRDT (Conflict-free Replicated Data Types)
Conflict resolution (LWW, CRDT, application-level)
Change Data Capture (CDC) — Debezium
Transactional Outbox pattern
Event Sourcing + CQRS
Data lake, warehouse, lakehouse, mesh
Hot/warm/cold storage tiers
TTL / data retention policies
Backup strategies (full, incremental, PITR)
Geo-replication (multi-region)
Multi-master conflict resolution

## Consistency

Strong consistency (linearizable)
Sequential consistency
Causal consistency
Eventual consistency
Read-your-writes (session consistency)
Monotonic reads / monotonic writes
Bounded staleness (Cosmos)
Write-ahead log (WAL)
Read repair / anti-entropy / Merkle tree sync
Isolation levels (RC, RR, Snapshot, Serializable)

## Messaging / async

Message Queue (Kafka, RabbitMQ, SQS, Pulsar, NATS)
Pub/Sub Pattern
Fan-out (write-heavy) vs fan-in (read-heavy)
Delivery semantics (at-most-once, at-least-once, exactly-once)
Exactly-once via idempotency + dedup
Ordering (partition key, total order with single partition)
Dead Letter Queue (DLQ)
Retry + exponential backoff + jitter
Poison message handling
Outbox + inbox
Event-driven architecture
CQRS
Saga (choreography vs orchestration)
Event schema (Avro, Protobuf, JSON Schema)
Schema Registry
Streaming (Kafka Streams, Flink, Spark Structured Streaming)
Lambda vs Kappa architecture
Backpressure / flow control
Priority queue
Delay queue (visibility timeout)

## API / protocols

HTTP/1.1 vs HTTP/2 vs HTTP/3 (QUIC)
TCP vs UDP
WebSocket
SSE (Server-Sent Events)
Long polling
gRPC vs REST vs GraphQL vs tRPC
Thrift, Avro, Protobuf
Schema evolution
API versioning strategies
Rate Limiting (Token Bucket, Leaky Bucket, Fixed Window, Sliding Window, Sliding Log)
Throttling vs backpressure vs load shedding
Circuit Breaker pattern
Bulkhead
Timeout + retry + idempotency key
Webhook design (HMAC signature, retry, DLQ, SSRF guard)
Backend-for-Frontend (BFF)
API Gateway

## Security / identity

TLS / mTLS
Certificate management (cert-manager, Let's Encrypt)
JWT Authentication (access + refresh)
OAuth2 (Authorization Code + PKCE, Client Credentials)
OIDC (OpenID Connect)
SAML 2.0
Session cookies (HttpOnly, Secure, SameSite)
CSRF protection (SameSite, token)
CORS
API keys
HMAC request signing
Secrets management (Vault, KMS, Secrets Manager)
Encryption at rest / in transit
KMS, envelope encryption
Key rotation
OWASP Top 10, OWASP API Top 10
RBAC / ABAC / ReBAC
Policy-as-code (OPA/Rego, Cedar)
Zero Trust
Gatekeeper / admission control (K8s)

## Reliability / ops

Idempotency (Idempotency-Key)
Exactly-once processing (via idempotency + dedup)
Retry safety
Graceful shutdown (SIGTERM, drain)
Distributed Transactions (2PC, 3PC, Saga)
Distributed Locking (Redlock critique, ZooKeeper/etcd, fencing tokens)
Leader Election
Consensus (Raft, Paxos, ZAB, Viewstamped)
Gossip protocol (Cassandra, Consul)
Heartbeat / failure detection
Chaos Engineering
Disaster Recovery (RTO, RPO, multi-region)
Active-active vs active-passive
SLA / SLO / SLI + error budgets
MTTR / MTBF
Runbooks
On-call rotation / PagerDuty
Postmortems (blameless)
Feature flags + kill switches
Dark launch / canary + rollback

## Observability

Structured logging
Centralized logging (ELK, Loki, Splunk)
Metrics (Prometheus, Datadog, Graphite)
RED method (Rate, Errors, Duration)
USE method (Utilization, Saturation, Errors)
Four Golden Signals (latency, traffic, errors, saturation)
Distributed Tracing (OpenTelemetry, Jaeger, Zipkin, Tempo)
Trace sampling (head vs tail)
Correlation ID / request ID
APM (Datadog, New Relic, AppDynamics)
Alerting (burn rate alerts vs threshold)
Dashboards (Grafana, Datadog)
Profiling (continuous profiling; Pyroscope, Parca)
Synthetic monitoring
Real user monitoring (RUM)
Anomaly detection

## Probabilistic / approx data structures

Bloom Filter — membership test
Cuckoo Filter — Bloom + deletion
Count-Min Sketch — frequency estimation
HyperLogLog — cardinality estimation
Top-K (Heavy Hitters)
T-Digest / HDR Histogram — quantiles
MinHash / LSH — similarity

## Geospatial

Geohashing
Quadtree
R-tree
S2 library (Google) — cells on sphere
H3 (Uber) — hexagonal grid
PostGIS, Redis GEO, Elasticsearch geo

## Search / recommendation / analytics

Inverted index
Tokenization, stemming, stop words
TF-IDF, BM25
Elasticsearch / OpenSearch
Vector search (HNSW, IVF, ScaNN)
Hybrid search (lexical + semantic)
Typeahead / autocomplete (trie, top-K per node)
Reranking
Faceted search
Collaborative filtering (user-user, item-item)
Content-based recommendation
Matrix factorization (SVD, ALS)
Deep learning recs (two-tower, transformer)
Feature store (Feast, Tecton)
A/B testing infrastructure

## Real-time / streaming

WebSocket server (Socket.io, Soketi, Reverb, Pusher)
SSE
Push notifications (APNs, FCM, Web Push)
Live streaming (HLS, LL-HLS, WebRTC, SFU vs MCU)
Video conferencing (WebRTC + SFU)
Collaborative editing (OT vs CRDT)
Presence system (Redis pub/sub, heartbeats)
Chat system (message fan-out, read receipts)

## Data engineering

ETL vs ELT
Batch vs Stream vs Lambda vs Kappa
Data modeling (star, snowflake schema)
Slowly changing dimensions (SCD 1/2/3/4/6)
Time-series DB (Gorilla compression, downsampling, retention)
Distributed ID generation (Snowflake, ULID, UUIDv7, KSUID)
Data mesh, data lakehouse, medallion
dbt, Airflow, Dagster, Prefect (orchestration)

## Classic design exercises

URL Shortener (bit.ly) dizaynı
News Feed / Timeline dizaynı (Twitter, Facebook)
Chat sistemi dizaynı (WhatsApp, Slack, Discord)
Notification sistemi dizaynı
Search Autocomplete / Typeahead dizaynı
File Storage dizaynı (S3-like, Dropbox)
Rate Limiter dizaynı
Distributed Cache (Memcached/Redis cluster)
Key-Value Store (Dynamo paper)
Message Queue (Kafka-like)
Pub/Sub system
Web Crawler
Payment system / Digital Wallet
Ride-sharing (Uber, Lyft)
Food delivery (DoorDash, Wolt)
Video streaming (YouTube, Netflix)
Live streaming (Twitch)
E-commerce (Amazon)
Ticketing system (Ticketmaster)
Google Docs / Collaborative editor
Metrics / Monitoring (Prometheus)
Time-series DB
Distributed File System (GFS/HDFS)
Ad serving (RTB)
Feature Store
Recommendation system (YouTube, Netflix)
Social graph (Twitter, LinkedIn)
Matchmaking (Xbox, Riot)
Webhook delivery
Push notification backend
Stock trading / matching engine
Elevator, parking lot, ATM (OOD)
Typeahead
Airbnb / Booking system
GitHub-like (repo hosting)
News aggregator (Reddit, HN)
Auction system (eBay)
Crypto exchange
Document search (Algolia-like)
AI inference serving (vLLM, Triton)
Multi-region active-active

## Back-of-envelope constants (memorize)

1 B = 10⁹ reqs/day ≈ 11.6 k QPS (peak ~2x)
1 day ≈ 86 400 s ≈ 10⁵ s
L1 cache: 0.5 ns
L2 cache: 7 ns
RAM: 100 ns
SSD random read: 100 μs
HDD seek: 10 ms
Same DC RTT: 0.5 ms
Cross-region RTT: 100+ ms
1 MB from network: ~10 ms
1 Gbps NIC: 125 MB/s
Typical RDBMS: ~1-5k QPS per instance
Redis: ~100k ops/sec per instance
Kafka: ~100k-1M msg/sec per broker
