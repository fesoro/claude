# TikTok / ByteDance (Architect)

## Ümumi baxış
- **Nə edir:** Qısa video platform, dünyanın ən böyük recommendation engine-lərindən biri. ByteDance həmçinin Douyin (Çin), Lark (enterprise chat), Toutiao (news), CapCut (video edit) sahibidir.
- **Yaradılıb:** Douyin 2016, TikTok 2017 (ByteDance tərəfindən, 2012-də Zhang Yiming tərəfindən qurulub). Musical.ly ilə 2018-də birləşdi.
- **Miqyas (açıq məlumat):**
  - 1B+ aylıq aktiv istifadəçi qlobal olaraq.
  - Hər gün milyardlarla video baxış.
  - Petabayt video upload hər gün.
  - On minlərlə engineer ByteDance-da.
- **Əsas tarixi anlar:**
  - 2012: ByteDance yaradıldı, Toutiao (news feed) ilə.
  - 2016: Douyin (Çin versiyası) launch.
  - 2017: TikTok qlobal launch, ilkin olaraq mobil-əsaslı.
  - 2018: Musical.ly alışı və birləşmə.
  - 2020+: ML platforma detayları ByteDance blog-unda görünür (Monolith, BytePS).
  - 2022: *Monolith* paper — ByteDance-in recommendation ML sistemi.

TikTok **recommendation engine** sahəsində dünyanın ən mürəkkəb sistemi ola bilər. ByteDance-ın fundamental rəqabət üstünlüyü budur, video content deyil.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Backend services | **Go** (əsas), **Python**, **Java**, **C++** | Polyglot; Go mikroservislər üçün əsas |
| ML training & serving | **Python + C++** (Monolith, BytePS) | TensorFlow özəlləşdirilmiş forks |
| Primary RDBMS | **MySQL** + **TiDB** | Sharded MySQL, TiDB HTAP workload üçün |
| Wide-column / KV | **Abase (daxili Redis)**, **Bytable** (daxili) | Daxili alternativlər Redis / Bigtable-ə |
| Search | Öz custom engine | Video axtarışı, hashtag |
| Messaging / events | **Kafka** (öz fork-u ilə) | Event streaming, ML feature events |
| CDN / video | Öz global CDN (ByteDance) + partnyorlar | Global video delivery |
| Stream processing | **Flink** | Real-time recommendation updates |
| Analytics | **ClickHouse**, ByteHouse (daxili fork) | OLAP, trilyonlarla event |
| Infrastructure | Öz data centers (Çin, ABŞ, Singapur, AB) | Data sovereignty, qlobal miqyas |
| Orchestration | **Kubernetes** | Öz operator-ları ilə |
| ML framework | **TensorFlow, PyTorch + Monolith (daxili)** | Rec system üçün birləşdirilmiş platform |

## Dillər — Nə və niyə

### Go — əsas backend
- ByteDance dünyanın ən böyük Go istifadəçisidir.
- Əksər mikroservislər: Go.
- **Niyə Go:**
  - Sürətli compile — minlərlə engineer paralel çalışır.
  - Yaxşı concurrency — video yükləmə, fan-out və s.
  - Kiçik binary-lər — deployment asan.
  - Python-dan daha sürətli, C++-dan daha asan.
- ByteDance açıq mənbəli: **Kitex** (Go RPC framework), **Hertz** (Go HTTP framework), **CloudWeGo** initiative.

### Python — ML və data science
- Recommendation model training.
- Feature engineering pipeline-ları.
- Data scientists üçün əsas dil.

### C++ — performance-critical
- Video encoding/decoding.
- ML inference optimizasiyaları.
- **Monolith** (rec system core) C++ ilə yazılıb.

### Java / Scala — data ekosistemi
- Kafka, Flink, Spark entegrasiyaları.
- Bəzi legacy sistemlər.

### Rust — artan istifadə
- Bəzi network-heavy komponentlərdə istifadə olunur.
- Video transcoding pipeline-larında bəzi memory-sensitive komponentlər.
- Amazon-un Rust-a ağır investisiyasından ilham alan Go→Rust qismən miqrasiyalar (hələ tam deyil).
- ByteDance-in Rust istifadəsi Go qədər yaygın deyil — GC tuning ilə Go hələ kifayət edir.

## Framework seçimləri — Nə və niyə
- **Kitex** — ByteDance-in açıq mənbəli yüksək performanslı Go RPC framework-u (Thrift/gRPC uyğunluğu).
- **Hertz** — ByteDance-in açıq mənbəli Go HTTP framework-u.
- **Monolith** — ByteDance-in rec ML framework-u (2022-də açıq mənbəli edildi).
- **BytePS** — distributed deep learning parameter server.
- **TensorFlow (ByteDance fork)** — birbaşa production-da istifadə üçün təkmilləşdirilib.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL + TiDB
- ByteDance dünyanın ən böyük **TiDB** istifadəçisidir — PingCAP tərəfindən açıq mənbə HTAP (hibrid transactional/analytical) database.
- **Niyə TiDB:**
  - MySQL-uyğun wire protokol (drop-in replacement).
  - Horizontal scale-out.
  - Strong consistency (Raft).
  - HTAP: eyni DB-də həm OLTP, həm OLAP.
- Video metadata, user data, engagement events.

### Abase — daxili Redis alternativi
- Redis-uyğun API, lakin data sharding + persistence dəstəyi ilə ByteDance-in dizaynı.
- Öz protokolu, Redis API səthi.

### Bytable — daxili Bigtable-vari sistem
- Wide-column storage, Bigtable/HBase-dən ilhamlanıb.
- Video thumbnail-ları, metadata, ML feature-lar.

### Kafka (ByteDance fork)
- Event log — user action-ları, video upload event-ləri, ML feature event-ləri.
- ByteDance Kafka-ya öz yamalarını tətbiq etdi.

### ClickHouse / ByteHouse
- Analytics üçün — trilyonlarla event.
- ByteDance ClickHouse-un daxili fork-unu (ByteHouse) saxlayır.

### Video storage
- Öz obyect storage (HDFS üzərində qurulub).
- Global CDN — ByteDance + partnyor CDN-lər.

## Proqram arxitekturası

```
   User (app, web)
        |
   [Global CDN]
        |
   [Edge LB / regional gateway]
        |
   [API Gateway (Go/Hertz)]
        |
   [Services: Upload, Feed, Recommendation, Search, User]
        |
   [MySQL/TiDB, Abase, Bytable, Kafka]
        |
   [Flink (real-time features)] → [Monolith (ML training + serving)]
        |
   [Feature store] → [Rec model serving]
```

### Recommendation engine — ByteDance-in moat-ı
- TikTok "For You" feed-i **Monolith** sistemi üzərində işləyir.
- **Monolith** paper (2022): deep-learning based recommender, real-time model updates.
- **Real-time learning:** model user feedback-ə dəqiqələr içində adapte olur, saatlar deyil.
- **Collisionless embeddings:** hash collision-ları saxlamaq üçün hash table dinamik böyüyür.
- **Negative sampling:** user izləmədiyi videolar training-ə negative signal kimi daxil olur.
- **Feature-lər:** watch time, completion rate, like, share, follow, re-watch, skip.

### Feed serving
- User dəqiqələr içində feed açır, backend hundreds of candidate video hazırlayır.
- Funnel: candidate generation (millions) → retrieval (thousands) → ranking (hundreds) → final top-20.
- Each stage fərqli ML model.

### Video upload pipeline
- User video upload → edge server → regional upload service.
- Transcoding (multiple bitrates, codecs).
- Thumbnail extraction, automatic moderation (ML).
- Global replication CDN-ə.

### Moderation
- ML-əsaslı real-time moderation.
- Human moderator-lar minlərlə, xüsusilə sensitive regionlar üçün.
- Content policy ilə ML modellər arasında tight feedback loop.

## İnfrastruktur və deploy
- Öz data centers çoxsaylı regionlarda.
- ABŞ TikTok operasiyaları məlumat suverenliyi üçün ayrı altyapıda (Project Texas).
- Kubernetes + öz operator-lar.
- Gündə minlərlə deploy.
- Heavy observability — öz APM, Kafka-əsaslı log aggregation.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2012 | ByteDance yaradıldı (Toutiao news feed) |
| 2016 | Douyin (Çin) launch, Go əsasən |
| 2017 | TikTok qlobal launch |
| 2018 | Musical.ly alışı; qlobal miqyas artımı |
| 2019+ | Öz ML platform (Monolith erkən versiyalar) |
| 2021 | Kitex / Hertz açıq mənbə (CloudWeGo) |
| 2022 | Monolith paper; BytePS açıq mənbə |
| 2023+ | Project Texas — US data sovereignty layer |

## Əsas texniki qərarlar

1. **Go → əsas backend dili.** Python (Instagram, Pinterest) və Java (LinkedIn) əvəzinə Go seçdilər. Compile sürəti və concurrency qərarverici idi.
2. **Monolith — öz recommender framework.** TensorFlow default sistem ilə işə yaramadı (embedding table-ları TikTok miqyasında yaramaz). Öz C++ sistemini qurdular.
3. **Real-time learning kritik.** Əksər şirkətlər saatda/günündə bir model update edir. TikTok dəqiqələr içində edir — "niyə bu qədər yaxşı" ipucunun böyük hissəsi.
4. **TiDB qəbulu.** MySQL API + horizontal scale istəyi onları TiDB-nin ən böyük istifadəçisi etdi.
5. **Ağır qlobal CDN sərmayəsi.** Video workload üçün edge delivery hər şeydir. Partner CDN-lər yetərli deyil; öz edge infrastrukturu lazım.

## Müsahibədə necə istinad etmək

1. **Feed ranking sualı:** "TikTok pattern-i: multi-stage funnel. Candidate generation → retrieval (coarse model) → ranking (fine model) → diversity re-rank. Real-time feedback → online model update (Monolith)."
2. **Recommendation system:** "Collaborative filtering başlanğıc; TikTok-u unique edən real-time deep-learning sistem. Watch time + completion rate əsas signal, likes deyil."
3. **Video upload pipeline:** "Upload servisə gələn video → transcoding queue → moderation → thumbnail → CDN replicate. Async, event-driven (Kafka)."
4. **Scale üçün DB seçimi:** "MySQL-uyğun, amma horizontal scale lazımdır → TiDB və ya Vitess. TikTok TiDB seçdi, Shopify Vitess."
5. **Go polyglot-da niyə qalib gəlir:** "TikTok, Cloudflare, Uber — hər biri Go-nu compile speed və concurrency üçün seçdi. Python ML, Java ağır enterprise, Go əksər mikroservislər üçün middle ground."

## Əlavə oxu üçün
- Paper: *Monolith: Real Time Recommendation System With Collisionless Embedding Table* (ByteDance, 2022)
- Paper: *BytePS: A high performance and general framework for distributed DNN training*
- ByteDance Engineering Blog: Kitex, Hertz, CloudWeGo posts
- Talks: ByteDance on QCon / ArchSummit China
- Talk: *How Douyin scales recommendation systems*
- Blog posts on TiDB at ByteDance (PingCAP və ByteDance-dən)
- Project Texas — TikTok US data architecture (public filings)
