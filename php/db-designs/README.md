# Database Design Patterns & Real-World Systems

## Mündəricat

### Ümumi Tətbiq Dizaynları
| Fayl | Tətbiq | Tövsiyə olunan DB |
|------|--------|-------------------|
| [01-blog-cms.md](01-blog-cms.md) | Blog / CMS | PostgreSQL + Redis |
| [02-chat-app.md](02-chat-app.md) | Mesajlaşma tətbiqi | Cassandra + Redis |
| [03-e-commerce.md](03-e-commerce.md) | E-ticarət | PostgreSQL + Redis + Elasticsearch |
| [04-social-media.md](04-social-media.md) | Sosial media | PostgreSQL + Cassandra + Redis |
| [05-ride-sharing.md](05-ride-sharing.md) | Taksi tətbiqi | PostgreSQL + Redis (geospatial) |
| [06-food-delivery.md](06-food-delivery.md) | Yemək çatdırılması | PostgreSQL + Redis |
| [07-banking-fintech.md](07-banking-fintech.md) | Bank / FinTech | PostgreSQL (ACID) |
| [08-video-streaming.md](08-video-streaming.md) | Video streaming | Cassandra + MySQL + S3 |
| [09-google-drive.md](09-google-drive.md) | Cloud file storage | PostgreSQL + S3 + Redis |
| [10-youtube.md](10-youtube.md) | Video platform | MySQL (Vitess) + Bigtable + S3 |
| [11-hotel-booking.md](11-hotel-booking.md) | Otel rezervasiyası | PostgreSQL + Redis + Elasticsearch |
| [12-stock-trading.md](12-stock-trading.md) | Birja / Trading | PostgreSQL + Redis + TimescaleDB |
| [13-event-ticketing.md](13-event-ticketing.md) | Bilet satışı | PostgreSQL + Redis (seat lock) |
| [14-healthcare-ehr.md](14-healthcare-ehr.md) | Sağlıq / EHR | PostgreSQL (HIPAA) |
| [15-online-learning.md](15-online-learning.md) | Online təhsil | PostgreSQL + Redis + S3 |
| [16-job-board.md](16-job-board.md) | İş elanları | PostgreSQL + Elasticsearch |

### Tanınmış Sistemlərin DB Dizaynları
| Fayl | Sistem | Actual DB Stack |
|------|--------|-----------------|
| [17-netflix.md](17-netflix.md) | Netflix | Cassandra + MySQL + CockroachDB |
| [18-instagram.md](18-instagram.md) | Instagram | PostgreSQL + Cassandra + Redis |
| [19-whatsapp.md](19-whatsapp.md) | WhatsApp | Mnesia (Erlang) + MySQL |
| [20-amazon.md](20-amazon.md) | Amazon | DynamoDB + Aurora + RDS |
| [21-paypal.md](21-paypal.md) | PayPal | Oracle → MySQL + PostgreSQL |
| [22-uber.md](22-uber.md) | Uber | MySQL + Schemaless + H3 |
| [23-twitter.md](23-twitter.md) | Twitter/X | MySQL + Manhattan (custom) + Redis |
| [24-airbnb.md](24-airbnb.md) | Airbnb | MySQL + Amazon RDS |
| [25-discord.md](25-discord.md) | Discord | ScyllaDB + PostgreSQL + Redis |
| [26-google.md](26-google.md) | Google | Bigtable + Spanner + BigQuery |
| [27-linkedin.md](27-linkedin.md) | LinkedIn | Espresso + Kafka + Voldemort |
| [28-github.md](28-github.md) | GitHub | MySQL (Vitess) + Git objects |
| [29-slack.md](29-slack.md) | Slack | MySQL (Vitess) + Redis + Flannel |
| [30-spotify.md](30-spotify.md) | Spotify | PostgreSQL + Cassandra + GCP |
| [31-tiktok.md](31-tiktok.md) | TikTok | TiDB + ClickHouse + Cassandra |
| [32-shopify.md](32-shopify.md) | Shopify | MySQL (Vitess) + Redis |

### DB Seçim Bələdçisi
| [33-db-selection-guide.md](33-db-selection-guide.md) | Hansı DB-ni seçmək lazımdır? CAP, PACELC, consistency models |

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
```

## Tez Axtarış

```
ACID lazımdır?         → PostgreSQL / MySQL
Write-heavy, scale?    → Cassandra / ScyllaDB
Real-time analytics?   → ClickHouse
Global SQL?            → Spanner / CockroachDB / TiDB
Full-text search?      → Elasticsearch
Geospatial?            → PostgreSQL + PostGIS
Cache / session?       → Redis
Object storage?        → S3 / GCS
Time-series?           → TimescaleDB / InfluxDB
Graph?                 → Neo4j / Neptune
```
