# Database Design Patterns & Real-World Systems

## Mündəricat

### Ümumi Tətbiq Dizaynları
| Fayl | Tətbiq | Tövsiyə olunan DB |
|------|--------|-------------------|
| [chat-app.md](chat-app.md) | Mesajlaşma tətbiqi | Cassandra + Redis |
| [e-commerce.md](e-commerce.md) | E-ticarət | PostgreSQL + Redis + Elasticsearch |
| [blog-cms.md](blog-cms.md) | Blog / CMS | PostgreSQL + Redis |
| [social-media.md](social-media.md) | Sosial media | PostgreSQL + Cassandra + Redis |
| [ride-sharing.md](ride-sharing.md) | Taksi tətbiqi | PostgreSQL + Redis (geospatial) |
| [food-delivery.md](food-delivery.md) | Yemək çatdırılması | PostgreSQL + Redis |
| [banking-fintech.md](banking-fintech.md) | Bank / FinTech | PostgreSQL (ACID) |
| [video-streaming.md](video-streaming.md) | Video streaming | Cassandra + MySQL + S3 |
| [google-drive.md](google-drive.md) | Cloud file storage | PostgreSQL + S3 + Redis |
| [youtube.md](youtube.md) | Video platform | MySQL (Vitess) + Bigtable + S3 |
| [hotel-booking.md](hotel-booking.md) | Otel rezervasiyası | PostgreSQL + Redis + Elasticsearch |
| [stock-trading.md](stock-trading.md) | Birja / Trading | PostgreSQL + Redis + TimescaleDB |
| [event-ticketing.md](event-ticketing.md) | Bilet satışı | PostgreSQL + Redis (seat lock) |
| [healthcare-ehr.md](healthcare-ehr.md) | Sağlıq / EHR | PostgreSQL (HIPAA) |
| [online-learning.md](online-learning.md) | Online təhsil | PostgreSQL + Redis + S3 |
| [job-board.md](job-board.md) | İş elanları | PostgreSQL + Elasticsearch |

### Tanınmış Sistemlərin DB Dizaynları
| Fayl | Sistem | Actual DB Stack |
|------|--------|-----------------|
| [netflix.md](netflix.md) | Netflix | Cassandra + MySQL + CockroachDB |
| [instagram.md](instagram.md) | Instagram | PostgreSQL + Cassandra + Redis |
| [whatsapp.md](whatsapp.md) | WhatsApp | Mnesia (Erlang) + MySQL |
| [amazon.md](amazon.md) | Amazon | DynamoDB + Aurora + RDS |
| [paypal.md](paypal.md) | PayPal | Oracle → MySQL + PostgreSQL |
| [uber.md](uber.md) | Uber | MySQL + Schemaless + H3 |
| [twitter.md](twitter.md) | Twitter/X | MySQL + Manhattan (custom) + Redis |
| [airbnb.md](airbnb.md) | Airbnb | MySQL + Amazon RDS |
| [discord.md](discord.md) | Discord | ScyllaDB + PostgreSQL + Redis |
| [google.md](google.md) | Google | Bigtable + Spanner + BigQuery |
| [linkedin.md](linkedin.md) | LinkedIn | Espresso + Kafka + Voldemort |
| [github.md](github.md) | GitHub | MySQL (Vitess) + Git objects |
| [slack.md](slack.md) | Slack | MySQL (Vitess) + Redis + Flannel |
| [spotify.md](spotify.md) | Spotify | PostgreSQL + Cassandra + GCP |
| [tiktok.md](tiktok.md) | TikTok | TiDB + ClickHouse + Cassandra |
| [shopify.md](shopify.md) | Shopify | MySQL (Vitess) + Redis |

### DB Seçim Bələdçisi
| [db-selection-guide.md](db-selection-guide.md) | Hansı DB-ni seçmək lazımdır? CAP, PACELC, consistency models |

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
