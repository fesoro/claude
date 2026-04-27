# System Design Interview Approach (Senior ⭐⭐⭐)

## İcmal
System design interview-ları namizədin böyük miqyaslı sistemlər qurmaq qabiliyyətini qiymətləndirmək üçün istifadə olunur. Bu interview-larda konkret cavab yoxdur — proses, düşüncə tərzi və trade-off-ları müzakirə etmək qabiliyyəti qiymətləndirilir. Senior mühəndis kimi yalnız sistemi dizayn etmir, həm də qərarlarını əsaslandırır, alternativlər təklif edir, interviewer ilə dialog qurursunuz.

## Niyə Vacibdir
Google, Amazon, Meta, Uber, Stripe kimi şirkətlər sistem dizayn suallarını Senior+ mövqelər üçün mütləq daxil edir. Düzgün yanaşma olmadan, hətta texniki bilgisi güclü olan namizədlər bu mərhələdə uğursuz olur. Interviewer "düzgün cavab" deyil, "necə düşünürsən, alternativləri bilirsən, trade-off-ları müzakirə edə bilirsən" axtarır. Bu müsahibə həm sistemin arxitekturasını, həm də sizin gündəlik iş metodologiyanızı göstərir.

## Əsas Anlayışlar

### RESHADED Framework:
**R**equirements → **E**stimation → **S**torage → **H**igh-level design → **A**PI design → **D**etailed design → **E**dge cases → **D**eep dive.

Bu strateji çərçivə — hər mərhələdə nə etmək lazım olduğunu bilmək. Təsadüfi danışmamaq üçün bu ardıcıllığı izlə.

### Mərhələ 1 — Requirements Clarification (0-5 dəq):
**Functional requirements** — sistem nə etməlidir:
- Kim istifadə edir? (user, admin, third-party)
- Əsas feature-lər nələrdir? Prioritetlər?
- Out-of-scope nədir? (açıq söylə, scope creep-in qarşısın al)

**Non-functional requirements** — sistemin necə işləməlidir:
- DAU/MAU — gündəlik/aylıq aktiv user sayı
- Latency hədəfləri — p99 < 100ms, p50 < 20ms?
- Availability — 99.9% (8.7h/year downtime), 99.99% (52min/year)?
- Consistency vs Availability trade-off — eventual consistency qəbul edilirmi?
- Read/write ratio — Twitter 50:1, messaging app 1:1.
- Data retention — logs nə qədər saxlanır? GDPR?

**Assumptions-larını whiteboard-a yaz** — interviewer-in razılığını al. "Assumption edirəm ki..."

### Mərhələ 2 — Estimation (5-10 dəq):
DAU → RPS → Storage → Bandwidth zəncirini izlə. Yuvarlaq rəqəmlər istifadə et.

**Template**:
```
DAU: X milyon
Write: X × write_rate / 100K = write RPS
Read:  X × read_rate / 100K = read RPS (peak = ortalama × 2-3)
Storage: write_per_user × bytes_per_record × days × users = total storage
Bandwidth: RPS × avg_response_size = bytes/sec
```

Estimation-dan sistem implication çıxar: "10K RPS → single DB yetmir, read replica lazımdır."

### Mərhələ 3 — High-Level Design (10-20 dəq):
Sadədən başla. Single server → multiple servers → distributed.

**Standart komponentlər**:
- Client (Web, Mobile, IoT)
- CDN (static assets, globally cached content)
- Load Balancer (L4/L7, SSL termination)
- API Gateway (auth, rate limiting, routing)
- Application Servers (stateless)
- Database (relational vs NoSQL seçimi)
- Cache (Redis — hot data)
- Message Queue (Kafka/RabbitMQ — async processing)
- Object Storage (S3 — media, files)
- Search Service (Elasticsearch — full-text search)

Hər komponent üçün "niyə?" sorusu cavablandırılmalıdır. "CDN əlavə edirəm, çünki static assets-i globally distribute etməliyik."

### Mərhələ 4 — API Design (15-25 dəq):
- REST vs GraphQL vs gRPC seçimi əsaslandır
- Əsas endpoint-lər, HTTP methods, request/response format
- Pagination — cursor-based > offset-based (niyə?)
- Rate limiting strategiyası
- Authentication/Authorization

**Nümunə**:
```
POST /api/v1/tweets
GET  /api/v1/tweets/{id}
GET  /api/v1/users/{id}/timeline?cursor={cursor}&limit=20
DELETE /api/v1/tweets/{id}
```

### Mərhələ 5 — Data Model (20-30 dəq):
- Entity-lər, attributes, data types, relationships
- Normalization vs denormalization qərarları
- Index strategiyası — hansı field-lər üçün index? Composite index?
- Primary key seçimi — sequential ID vs UUID (UUID-nin insertion problem-i)
- Partitioning key — sharding olacaqsa hansı field-ə görə?

### Mərhələ 6 — Detailed Component Design (25-40 dəq):
Hər kritik komponentə dərin giriş:
- **Caching strategiyası**: Cache-aside (Lazy Loading), Write-Through, Write-Behind. TTL. Cache eviction (LRU, LFU).
- **Message queue**: Nə zaman async? Guaranteed delivery? At-least-once vs exactly-once.
- **Database sharding**: Horizontal vs vertical. Shard key seçimi. Hot spot problemi. Cross-shard query.
- **CDN konfiqurasiyası**: Cache-Control, Vary header, purge strategiyası.

### Non-Functional Requirements Coverage:
- **Scalability**: Horizontal scale plan — stateless app servers, DB read replicas, sharding.
- **Reliability**: Single points of failure-ı aradan qaldır. Redundancy hər katmanda.
- **Availability**: Active-active vs active-passive. Multi-AZ deployment. Health checks.
- **Consistency**: Strong vs eventual. CAP theorem. Read-your-writes guarantee.
- **Durability**: Replication factor. Backup strategy. Point-in-time recovery.

### Trade-off Müzakirəsi:
"X yanaşması Y avantajı verir, lakin Z dezavantajı var" formatı.

Interviewer-in rəyini al: "Bu trade-off məntiqli görünürmü?" Bu aktiv dialog interviewer-a yaxşı izlenim yaradır.

**Tipik trade-off-lar**:
- Strong consistency vs High availability (CAP theorem)
- Read performance vs Write performance (caching, denormalization)
- Storage cost vs Query performance (indexing, materialized views)
- Latency vs Throughput (sync vs async)
- Simplicity vs Scalability (monolith vs microservices)

### Zaman İdarəetməsi:

**45 dəqiqəlik interview**:
```
0-5  dəq:  Requirements clarification — sual sor, assumptions yaz
5-8  dəq:  Estimation — yuvarlaq rəqəmlərlə, bottleneck tap
8-20 dəq:  High-level design — diagram çək, komponentlər
20-30 dəq: Data model + API design
30-40 dəq: Detailed design — ən kritik komponent
40-45 dəq: Trade-offs + edge cases + interviewer sualları
```

**60 dəqiqəlik interview**:
```
0-8  dəq:  Requirements + Estimation
8-20 dəq:  High-level design
20-30 dəq: API + Data model
30-50 dəq: Deep dive — 2-3 kritik komponent
50-60 dəq: Trade-offs + scaling + edge cases
```

### Senior vs Lead/Architect Fərqi:
- **Senior**: Sistemi funksional dizayn edir, əsas scale problemlərini həll edir, əsas trade-off-ları bilir.
- **Lead**: Business requirements ilə əlaqələndirir, team delivery plan, cost estimate, tech debt.
- **Architect**: Multi-region deployment, platform-level patterns, cross-functional impact, org-level tech strategy.

### "Bottleneck-First" Düşüncəsi:
Hər komponent üçün: "Bu nə zaman fail olur?"
- 10M DAU → Single DB? → Read replicas lazımdır.
- 100M DAU → DB read replicas? → Sharding lazımdır.
- User 150M follower? → Fan-out on write infeasible → Hybrid strategy.
Bu proaktiv düşüncə senior-ı fərqləndiriр.

### Real Sayları Bilmək:
```
PostgreSQL single node:  10K-50K read QPS (indexed)
                         5K-10K write QPS
Redis:                   100K-1M ops/sec
SSD latency:             ~100μs
Network same DC:         ~1ms
Network cross-continent: ~150ms
S3 latency:              ~50-200ms (cold), ~10ms (warm)
Kafka throughput:        1M msgs/sec/broker
```

### Database Seçim Kriteriyaları:
- **PostgreSQL/MySQL**: Relational, ACID, strong consistency, complex queries.
- **MongoDB**: Flexible schema, document model, horizontal scale, JSON-native.
- **Redis**: In-memory, ultra-low latency (~microsecond), ephemeral data, pub/sub.
- **Cassandra**: Write-heavy, time-series, AP system, wide-column, no JOINs.
- **Elasticsearch**: Full-text search, log analytics, inverted index.
- **S3/Blob**: Unstructured data, media files, cheap storage.
- **ClickHouse**: OLAP, analytics, time-series aggregation.

## Praktik Baxış

### Interview-da Necə Yanaşmaq:
1. Sualı anlamadan yazmağa başlama — sual sor (5 dəqiqə)
2. Assumptions-ları whiteboard-a yaz
3. Komponentlər arasındakı data flow-u izah et
4. Hər qərar üçün alternativləri qeyd et
5. Bottleneck-ləri özün tap

### Junior-dan Fərqlənən Senior Cavabı:
- **Junior**: Dərhal microservices, Kafka, Redis, Kubernetes — over-engineered. "Kafka əlavə edərik" amma niyə sualına cavab yoxdur.
- **Senior**: Sadədən başlayır, "bu nöqtədə nə yetmir?" soruşur, tədricən scale edir. Hər qərar əsaslandırılır.
- **Lead**: Business constraint-ləri (cost, team size, timeline) nəzərə alır. "Bu arxitektura $50K/ay AWS xərci deməkdir, alternativi var."

### Follow-up Suallar:
- "10x daha çox traffic gəlsə sistemin nə dəyişərdi?" — Bottleneck-ləri əvvəlcədən müzakirə et.
- "Bu sistemi 3 developer qurarsa nə sadələşdirərdiniz?" — Operational complexity vs team size.
- "Database schema-nı göstərə bilərsinizmi?" — Detallar vacibdir.
- "Hansı component first fail edər?" — Bottleneck identification.
- "Multi-region deployment necə edərdiniz?" — Replication, consistency, active-active vs active-passive.

### Uğursuzluğa Aparan Səhvlər (Interviewer-in Gözündən):
1. **Requirements clarification etmədən dərhal yazmağa başlamaq** — "Bu namizəd tələbsiz sistem qurur." Production-da fatal hatadır.
2. **Yalnız "happy path" dizayn etmək** — Failure mode-lar? Server crash? Network partition? "İşləyir amma fail etmir" sistem mövcud deyil.
3. **Rəqəmsiz danışmaq** — "Çox user" vs "10M DAU, 12K RPS" — rəqəmsiz estimation credibility-ni azaldır.
4. **Bir komponentə çox vaxt xərcləyib digərlərini unutmaq** — Database-ə 30 dəqiqə, cache-ə 0 dəqiqə.
5. **Trade-off müzakirəsi olmadan "ən yaxşı" həll iddia etmək** — "Kafka istifadə edəcəm" → "niyə RabbitMQ yox?" sualına cavab yoxdur.
6. **Interviewer-in ipuclarını dinləməmək** — "Database-ə daha dərin baxaq" — redirect-i qəbul et.
7. **Over-engineering** — MVP üçün microservices, Kubernetes, service mesh — 3 developer üçün əsassızdır.
8. **Under-engineering** — "Single server kifayət edir" — scale problemi yoxlanılmır.

## Nümunələr

### Tipik Interview Sualı
"Design a URL shortener like bit.ly. 45 minutes. You have 100M DAU."

### Güclü Cavab — Tam Walkthrough

```
ADDIM 1 — Requirements (5 dəq)
────────────────────────────────
Functional:
  - URL shortening: long URL → short code (6-7 char)
  - Redirection: short URL → 301/302 redirect to original
  - Custom alias (optional) — "example.com/my-brand"
  - Analytics: click count, geography, device (optional)

Out-of-scope: User auth, link expiry, spam detection
              (assumption: sadə, public service)

Non-functional:
  - DAU: 100M
  - Read:Write ratio = 10:1 (klik >> yaratma)
  - Availability: 99.99% (redirect fail = revenue loss)
  - Latency: p99 < 50ms for redirect
  - Consistency: Eventual OK (analytics)

ADDIM 2 — Estimation (5 dəq)
────────────────────────────────
DAU: 100M
Write (URL create): 100M × 10% = 10M URLs/day
  → 10M / 100K = 100 write/sec; Peak: 300 write/sec

Read (URL redirect): 100M × 10 clicks = 1B reads/day
  → 1B / 100K = 10K read/sec; Peak: 30K read/sec

Read:Write = 100:1 → read-heavy, cache critical!

Storage per URL: ~131 bytes (short_code + long_url + metadata)
5 years: 10M/day × 365 × 5 × 131B = ~2.4 TB
→ Single PostgreSQL + 1 read replica yetər

Cache: 80/20 rule → 20% URL = 80% traffic
  20% × 10M daily active = 2M URLs cached
  2M × 131B = 262 MB Redis — çox kiçik!

ADDIM 3 — High-Level Design
────────────────────────────────
Client
  → DNS → CDN (redirect cache)
       → Load Balancer (L7)
            → URL Service (stateless app servers)
                 ↓              ↑ cache miss
            PostgreSQL       Redis Cache
            (URLs table)     (short_code → long_url)

Redirect flow: Client → App → Redis (hit? 301 redirect; miss? DB → cache → redirect)
Create flow:   Client → App → DB write + Cache set

ADDIM 4 — API Design
────────────────────────────────
POST /api/v1/urls
  Body: { "long_url": "https://...", "custom_alias": "optional" }
  Response 201: { "short_url": "https://bit.ly/aB3kZ9x" }

GET /{short_code}   → 301 Moved Permanently (long_url cache: 1 year)
                    → 302 Found (analytics tracking lazımdırsa)
  Header: Location: {long_url}
  Cache-Control: public, max-age=3600

GET /api/v1/urls/{short_code}/stats → { clicks, geography, devices }

ADDIM 5 — Data Model
────────────────────────────────
TABLE urls:
  id          BIGSERIAL PRIMARY KEY
  short_code  VARCHAR(7)  UNIQUE NOT NULL     ← axtarış key-i
  long_url    TEXT        NOT NULL
  user_id     BIGINT      REFERENCES users    (nullable — anonymous)
  created_at  TIMESTAMPTZ DEFAULT NOW()
  click_count BIGINT      DEFAULT 0
  expires_at  TIMESTAMPTZ                     (optional expiry)

INDEX: CREATE UNIQUE INDEX idx_short_code ON urls(short_code);
INDEX: CREATE INDEX idx_user_created ON urls(user_id, created_at DESC);

TABLE url_clicks:  (analytics — ayrı table, high write)
  id          BIGSERIAL
  url_id      BIGINT REFERENCES urls
  clicked_at  TIMESTAMPTZ
  ip_address  INET
  user_agent  TEXT
  country     VARCHAR(2)

ADDIM 6 — Short Code Generation
────────────────────────────────
Option A — MD5 hash: MD5(long_url) → base62 ilk 7 char
  Problem: Collision possible (different URLs, same hash)
  Fix: URL-ə timestamp əlavə et

Option B — Counter + base62:
  Global counter (DB auto-increment) → base62 encode
  7 chars base62 = 62^7 ≈ 3.5 trillion unique codes
  Problem: Single point of failure (counter server)
  Fix: Multiple counter ranges (Range-based: [1-1M server A], [1M-2M server B])

Option C — UUID → base62 (first 7 chars)
  Problem: Collision risk, random → poor cache locality

RECOMMENDED: Counter + base62 with distributed counter (Redis INCR ya DB sequence)

ADDIM 7 — Cache Strategy
────────────────────────────────
Read-through cache: App Redis-ə baxır, miss → DB-dən al, cache-ə yaz.
TTL: Popular URL-lər → 24 saat. Long tail → 1 saat.
Cache eviction: LRU (Least Recently Used) — 80/20 rule ilə uyğun.
Cache miss rate: 20% (80/20 rule). DB load = 30K RPS × 20% = 6K RPS — single node yetər.

ADDIM 8 — Bottleneck Analysis
────────────────────────────────
Current scale (100M DAU):
  ✓ 262 MB Redis — single node yetər
  ✓ 6K DB read RPS (after cache) — single PostgreSQL + read replica yetər
  ✓ 300 write RPS — single PostgreSQL yetər
  ✓ 2.4 TB storage / 5 years — single node yetər

Scale to 10x (1B DAU):
  → Redis cluster (262 MB → 2.6 GB) — still manageable
  → DB read: 60K RPS → multiple read replicas
  → DB write: 3K RPS → still single primary
  → Storage: 24 TB → sharding ya da partitioning

Scale to 100x (10B DAU):
  → Sharding on short_code (first char → shard)
  → Multiple Redis cluster
  → Global CDN for redirect caching
  → Separate analytics service (Cassandra/ClickHouse)
```

### Arxitektura Diaqramı
```
                     ┌──────────────┐
                     │   Clients    │
                     └──────┬───────┘
                            │
                     ┌──────▼───────┐
                     │     CDN      │  ← Cached redirects, 1-hour TTL
                     │ (Cloudflare) │
                     └──────┬───────┘
                            │
                     ┌──────▼───────┐
                     │  Load Bal.   │  ← L7, SSL termination, health check
                     │  (HAProxy)   │
                     └──┬───────┬───┘
                        │       │
              ┌─────────▼──┐ ┌──▼─────────┐
              │ URL Service │ │ URL Service │  ← Stateless, horizontal scale
              │   (Pod 1)   │ │   (Pod 2)   │
              └─────┬───────┘ └──────┬──────┘
                    │                │
              ┌─────▼────────────────▼─────┐
              │       Redis Cluster         │  ← 262 MB, LRU, TTL 24h
              │  short_code → long_url      │
              └─────────────┬───────────────┘
                            │ cache miss
              ┌─────────────▼───────────────┐
              │  PostgreSQL (Primary)        │  ← Write
              │  + 2× Read Replicas          │  ← Read (6K RPS)
              └─────────────────────────────┘
```

## Praktik Tapşırıqlar

1. **Design URL Shortener (bit.ly)** — başlangıc problem. 45 dəqiqəyə tam sistemi dizayn et. Timer qoy.
2. **Design Instagram** — media upload, feed delivery, stories. Storage, CDN, feed fanout.
3. **Design WhatsApp** — messaging, delivery receipts, read receipts, group chat, WebSocket.
4. **Design Uber** — geolocation update (driver → server), driver matching, surge pricing.
5. **Design YouTube** — video upload, encoding pipeline, CDN streaming, recommendations.
6. **Özünütəst**: Hər dizayn üçün 45 dəqiqə timer qoy. Nə tamamlandı, nə atlandı?
7. **Trade-off siyahısı**: Hər dizayn üçün 5 trade-off yaz — SQL vs NoSQL, sync vs async, cache vs no-cache.
8. **"10x traffic" ssenarisi**: Mövcud dizaynınızı 10x traffic-ə necə scale edərdiniz?
9. **Estimation practice**: Hər gün bir sistem üçün estimation et — Twitter, Gmail, Netflix. Rəqəmləri işbazlıq etmədən bilmək.
10. **Failure mode analysis**: Hər dizayn üçün "nə baş verər əgər X fail etsə?" — 5 fərqli component üçün.

## Əlaqəli Mövzular

- [02-back-of-envelope.md](02-back-of-envelope.md) — Estimation texnikaları, latency cədvəli
- [03-scalability-fundamentals.md](03-scalability-fundamentals.md) — Horizontal vs vertical scale
- [04-load-balancing.md](04-load-balancing.md) — Load balancing strategiyaları
- [06-database-selection.md](06-database-selection.md) — DB seçim kriteriyaları
- [20-monitoring-observability.md](20-monitoring-observability.md) — Production readiness, SLO/SLA
