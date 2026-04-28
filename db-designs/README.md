# Database Design Patterns & Real-World Systems

Real sistemlərin database arxitekturası. Hər fayl konkret bir tətbiqin schema dizaynını, DB seçimini və **niyə bu seçim edilib** sualına cavab verir.

**Toplam: 43 fayl** (25 ümumi tətbiq + 16 tanınmış sistem + 2 bələdçi)

---

## Səviyyə Sistemi

| Level | Stars |
|-------|-------|
| Junior | ⭐ |
| Middle | ⭐⭐ |
| Senior | ⭐⭐⭐ |
| Lead | ⭐⭐⭐⭐ |
| Architect | ⭐⭐⭐⭐⭐ |

---

## Ümumi Tətbiq Dizaynları

| Fayl | Tətbiq | Level | Tövsiyə olunan DB |
|------|--------|-------|-------------------|
| [01-blog-cms.md](01-blog-cms.md) | Blog / CMS | Junior ⭐ | PostgreSQL + Redis |
| [16-job-board.md](16-job-board.md) | İş elanları | Junior ⭐ | PostgreSQL + Elasticsearch |
| [06-food-delivery.md](06-food-delivery.md) | Yemək çatdırılması | Middle ⭐⭐ | PostgreSQL + Redis |
| [11-hotel-booking.md](11-hotel-booking.md) | Otel rezervasiyası | Middle ⭐⭐ | PostgreSQL + Redis + Elasticsearch |
| [15-online-learning.md](15-online-learning.md) | Online təhsil | Middle ⭐⭐ | PostgreSQL + Redis + S3 |
| [38-url-shortener.md](38-url-shortener.md) | URL Shortener | Middle ⭐⭐ | PostgreSQL + Redis + ClickHouse |
| [40-leaderboard.md](40-leaderboard.md) | Leaderboard / Ranking | Middle ⭐⭐ | Redis + PostgreSQL |
| [02-chat-app.md](02-chat-app.md) | Mesajlaşma tətbiqi | Middle ⭐⭐ | Cassandra + Redis |
| [03-e-commerce.md](03-e-commerce.md) | E-ticarət | Senior ⭐⭐⭐ | PostgreSQL + Redis + Elasticsearch |
| [04-social-media.md](04-social-media.md) | Sosial media | Senior ⭐⭐⭐ | PostgreSQL + Cassandra + Redis |
| [05-ride-sharing.md](05-ride-sharing.md) | Taksi tətbiqi | Senior ⭐⭐⭐ | PostgreSQL + Redis (geospatial) |
| [08-video-streaming.md](08-video-streaming.md) | Video streaming | Senior ⭐⭐⭐ | Cassandra + MySQL + S3 |
| [09-google-drive.md](09-google-drive.md) | Cloud file storage | Senior ⭐⭐⭐ | PostgreSQL + S3 + Redis |
| [13-event-ticketing.md](13-event-ticketing.md) | Bilet satışı | Senior ⭐⭐⭐ | PostgreSQL + Redis (seat lock) |
| [34-multi-tenant-saas.md](34-multi-tenant-saas.md) | Multi-Tenant SaaS | Senior ⭐⭐⭐ | PostgreSQL (RLS / schemas) |
| [35-notification-system.md](35-notification-system.md) | Notification System | Senior ⭐⭐⭐ | PostgreSQL + Redis + Kafka |
| [37-search-system.md](37-search-system.md) | Search System | Senior ⭐⭐⭐ | Elasticsearch + PostgreSQL + Redis |
| [39-rate-limiter.md](39-rate-limiter.md) | Rate Limiter | Senior ⭐⭐⭐ | Redis + PostgreSQL |
| [41-recommendation-system.md](41-recommendation-system.md) | Recommendation System | Senior ⭐⭐⭐ | PostgreSQL + pgvector + Redis |
| [42-job-scheduler.md](42-job-scheduler.md) | Job / Task Scheduler | Middle ⭐⭐ | PostgreSQL + Redis |
| [43-feature-flags.md](43-feature-flags.md) | Feature Flags / A/B Testing | Middle ⭐⭐ | PostgreSQL + Redis |
| [07-banking-fintech.md](07-banking-fintech.md) | Bank / FinTech | Lead ⭐⭐⭐⭐ | PostgreSQL (ACID) |
| [10-youtube.md](10-youtube.md) | Video platform | Lead ⭐⭐⭐⭐ | MySQL (Vitess) + Bigtable + S3 |
| [12-stock-trading.md](12-stock-trading.md) | Birja / Trading | Lead ⭐⭐⭐⭐ | PostgreSQL + Redis + TimescaleDB |
| [14-healthcare-ehr.md](14-healthcare-ehr.md) | Sağlıq / EHR | Lead ⭐⭐⭐⭐ | PostgreSQL (HIPAA) |

---

## Tanınmış Sistemlərin DB Dizaynları

| Fayl | Sistem | Level | Actual DB Stack |
|------|--------|-------|-----------------|
| [24-airbnb.md](24-airbnb.md) | Airbnb | Senior ⭐⭐⭐ | MySQL + Amazon RDS |
| [25-discord.md](25-discord.md) | Discord | Senior ⭐⭐⭐ | ScyllaDB + PostgreSQL + Redis |
| [29-slack.md](29-slack.md) | Slack | Senior ⭐⭐⭐ | MySQL (Vitess) + Redis + Flannel |
| [30-spotify.md](30-spotify.md) | Spotify | Senior ⭐⭐⭐ | PostgreSQL + Cassandra + GCP |
| [32-shopify.md](32-shopify.md) | Shopify | Senior ⭐⭐⭐ | MySQL (Vitess) + Redis |
| [17-netflix.md](17-netflix.md) | Netflix | Lead ⭐⭐⭐⭐ | Cassandra + MySQL + CockroachDB |
| [18-instagram.md](18-instagram.md) | Instagram | Lead ⭐⭐⭐⭐ | PostgreSQL + Cassandra + Redis |
| [19-whatsapp.md](19-whatsapp.md) | WhatsApp | Lead ⭐⭐⭐⭐ | Mnesia (Erlang) + MySQL |
| [21-paypal.md](21-paypal.md) | PayPal | Lead ⭐⭐⭐⭐ | Oracle → MySQL + PostgreSQL |
| [22-uber.md](22-uber.md) | Uber | Lead ⭐⭐⭐⭐ | MySQL + Schemaless + H3 |
| [23-twitter.md](23-twitter.md) | Twitter/X | Lead ⭐⭐⭐⭐ | MySQL + Manhattan (custom) + Redis |
| [27-linkedin.md](27-linkedin.md) | LinkedIn | Lead ⭐⭐⭐⭐ | Espresso + Kafka + Voldemort |
| [28-github.md](28-github.md) | GitHub | Lead ⭐⭐⭐⭐ | MySQL (Vitess) + Git objects |
| [31-tiktok.md](31-tiktok.md) | TikTok | Lead ⭐⭐⭐⭐ | TiDB + ClickHouse + Cassandra |
| [36-stripe.md](36-stripe.md) | Stripe | Lead ⭐⭐⭐⭐ | PostgreSQL + Redis + Kafka |
| [20-amazon.md](20-amazon.md) | Amazon | Architect ⭐⭐⭐⭐⭐ | DynamoDB + Aurora + RDS |
| [26-google.md](26-google.md) | Google | Architect ⭐⭐⭐⭐⭐ | Bigtable + Spanner + BigQuery |

---

## Bələdçilər

| Fayl | Mövzu | Level |
|------|-------|-------|
| [33-db-selection-guide.md](33-db-selection-guide.md) | Hansı DB-ni seçmək lazımdır? CAP, PACELC, consistency, NewSQL, Vector DB | Senior ⭐⭐⭐ |

---

## Əsas Prinsiplər

```
1. Doğru DB seçimi arxitekturanın 50%-ini həll edir
2. "One size fits all" yoxdur — polyglot persistence
3. ACID vs BASE: consistency vs availability trade-off
4. Read:Write ratio → cache, read replica, CQRS
5. Data structure → relational, document, columnar, graph
6. Access patterns first → schema follows (especially NoSQL)
7. Sharding: son çarə — önce vertical scale, sonra horizontal
8. Multi-tenancy: tenant_id scope > schema isolation > separate DB
9. Idempotency: ödəniş və kritik əməliyyatlarda mütləq
10. Fan-out strategy: write vs read — follower sayına görə seç
```

---

## Tez Axtarış

```
ACID lazımdır?         → PostgreSQL / MySQL
Write-heavy, scale?    → Cassandra / ScyllaDB
Real-time analytics?   → ClickHouse / Apache Pinot
Global SQL?            → Spanner / CockroachDB / TiDB
Full-text search?      → Elasticsearch / Solr (bax: 37-search-system)
Geospatial?            → PostgreSQL + PostGIS / S2
Cache / session?       → Redis
Object storage?        → S3 / GCS / Azure Blob
Time-series?           → TimescaleDB / InfluxDB
Graph?                 → Neo4j / Neptune
Multi-tenant SaaS?     → PostgreSQL + tenant_id scope (RLS)
Payments?              → PostgreSQL + Idempotency keys
Notifications?         → PostgreSQL + Redis + Kafka
URL shortening?        → PostgreSQL + Redis (bax: 38-url-shortener)
Rate limiting?         → Redis Sorted Set / Counter (bax: 39-rate-limiter)
Leaderboard?           → Redis Sorted Set (bax: 40-leaderboard)
Recommendation Engine? → pgvector + Redis (bax: 41-recommendation-system)
Background jobs?       → PostgreSQL FOR UPDATE SKIP LOCKED (bax: 42-job-scheduler)
Feature flags / A/B?   → PostgreSQL + Redis (bax: 43-feature-flags)
```

---

## Reading Paths

### Backend Developer başlanğıcı:
01 → 16 → 02 → 06 → 11 → 38 → 42 → 40 → 03 → 15 → 34 → 35

### System design hazırlığı:
04 → 05 → 08 → 10 → 12 → 13 → 37 → 39 → 41 → 33 → 26 → 20

### Real company arxitekturası:
17 → 18 → 19 → 29 → 25 → 36 → 22 → 27 → 28 → 26

### FinTech / Payments yolu:
07 → 12 → 21 → 36 → 20 → 26

### Search & Discovery yolu:
37 → 03 → 24 → 30 → 31 → 27

### Redis patterns yolu:
38 → 39 → 40 → 13 → 35 → 04 → 05

### Product engineering yolu:
43 → 42 → 41 → 34 → 35 → 39
