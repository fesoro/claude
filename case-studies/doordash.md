# DoorDash

## Ümumi baxış
- **Nə edir:** On-demand food delivery marketplace — müştərilər, restoranlar, dasher-lər (courier). Genişlənmə: grocery (DashMart), alkol, retail (DoorDash + Walmart-vari partnerships).
- **Yaradılıb:** 2013-də Stanford tələbələri tərəfindən (Tony Xu, Andy Fang, Stanley Tang, Evan Moore).
- **Miqyas (açıq məlumat):**
  - ABŞ-in #1 delivery market share (2021+).
  - Milyonlarla sifarişlər gündəlik.
  - Milyonlarla aktiv Dasher ayda.
  - 30+ ölkə, 2023-də Wolt alışından sonra.
- **Əsas tarixi anlar:**
  - 2013: Palo Alto-da "Palo Alto Delivery" kimi başladı.
  - 2018: aqressiv genişlənmə, böyük funding round-ları.
  - 2020: COVID-19 partlayış — gəlir ikiqatlaşdı.
  - 2020-2022: *"Migrating from Python to Kotlin"* blog posts serial — məşhur miqrasiya case.
  - 2021: IPO.
  - 2022: Wolt alışı (Avropa delivery).

DoorDash **"Python monolith-dən Kotlin mikroservislər-ə" miqrasiyasının** ən ətraflı ictimai case study-dir. Əgər `legacy rewrite` haqqında müsahibədə danışmaq lazımdırsa, DoorDash blog-u reference-dir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Legacy backend | **Python (Django)** | İlkin monolith, sürətli iterasiya |
| New backend (2019+) | **Kotlin on JVM** | Tiplər, performance, async patterns |
| ML, data science | **Python** (hələ də) | ML ekosistemi |
| Primary DB | **PostgreSQL** (çox sayda Aurora cluster) | Tranzaksional dəqiqlik |
| Event streaming | **Apache Kafka** | Sifariş, dispatch event-ləri |
| Workflow engine | **Cadence / Temporal** | Uzun davam edən uçqunlu prosesslər |
| Cache | **Redis** | Hot data, rate limit |
| Search | **Elasticsearch** | Restoran axtarışı, menyu |
| ML serving | TensorFlow, XGBoost + öz platform | ETA, dispatch, recommendation |
| Optimization | OR-tools, Gurobi + custom | Dispatch assignment |
| Infrastructure | **AWS** | Əksər workload |
| Service mesh / RPC | gRPC | Mikroservislər arası |
| Frontend | **React + TypeScript** | Web |
| Mobile | Native iOS (Swift), Android (Kotlin) | Driver app critical — native |

## Dillər — Nə və niyə

### Python → Kotlin miqrasiyası (2019-2022)
- Əvvəl: DoorDash **böyük Python Django monolit** idi.
- 2018-ə qədər böyümə ilə problemlər artdı:
  - **Performance:** Python GIL, sync I/O əsaslı kodbaza.
  - **Type safety yoxdur:** refactor-lar təhlükəli.
  - **Tail latency:** p99 aşan, scale altında çətin.
  - **Team scaling:** yüzlərlə engineer tək Python monolit-də.
- Qərar: yeni servislər **Kotlin** (JVM üstündə) yazmaq, tədricən Python-dan ayırmaq.
- **Niyə Kotlin:**
  - JVM — yetkin runtime, yaxşı GC, əla observability.
  - Statik tip yoxlayıcısı — böyük refactor-lar güvənli.
  - Async programming (`suspend`, coroutines).
  - Java ekosistemi (Kafka, gRPC, Micrometer) tam əlçatan.
  - Scala-dan daha asan hiring, daha yaxşı developer experience.
- **Necə:** Strangler-fig pattern. Hər feature ayrı servis, Python-la HTTP/gRPC ilə danışır. Python tədricən deaktiv edilir.

### Python — hələ də
- ML pipeline-ları, data science, model training.
- Recommendation, fraud detection, ETA modelləri.
- Airflow üzərində ETL.

### Kotlin — əsas yeni dil
- Yeni mikroservislərin 90%-i Kotlin.
- Ordering, Dispatch, Payment, Notification.

### Java
- Bəzi legacy köməkçi servislər.

### TypeScript
- Frontend (React, Next.js).
- Bəzi Node.js BFF servislər.

### Swift / Kotlin (native mobile)
- Müştəri və Dasher (courier) app-ları native.
- **Niyə native:** Dasher app background GPS, navigation, offline resilience — cross-platform framework-lər bu use case üçün yetərli deyil.

## Framework seçimləri — Nə və niyə
- **Django** (legacy) — Python monolit.
- **Kotlin + Spring Boot + gRPC** — yeni servislər.
- **Ktor** — bəzi yüngül Kotlin servisləri.
- **Temporal** (Cadence-in varisi) — orchestration.
- **Micrometer + Prometheus** — observability.
- **Kafka + Flink** — stream processing.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL (çoxsaylı Aurora cluster-ləri)
- Hər mikroservis öz DB-si (database-per-service pattern).
- **Niyə Aurora:** managed, high-durability, fast failover.
- **Write-heavy servislər:** sharding manual olaraq tətbiq olunur (partition key: `user_id`, `restaurant_id`).

### Kafka
- Bütün sifariş event-ləri burada.
- Event sourcing patterns — order state dəyişiklikləri tam audit trail.
- ML feature generation Kafka event-lərindən.

### Redis
- Hot data cache, rate limiting.
- Real-time ETA calculation cache.
- Dasher position updates.

### Elasticsearch
- Restoran axtarışı, menyu item axtarışı.

### Snowflake + S3
- Analytics data warehouse.
- Dashboard-lar, business analytics.

### Cadence / Temporal üçün MySQL/Cassandra
- Workflow state storage.

## Proqram arxitekturası

```
  [Client (customer, dasher, merchant apps)]
            |
       [Edge, API gateway]
            |
   [Kotlin services: Order, Menu, Dispatch, Payment, Notification]
      /  |  \  |  \
     v   v   v   v
   Postgres  Kafka  Redis  Elasticsearch
       |      |
       |   [Cadence/Temporal workflows]
       |      |
   [Python ML services: ETA, Ranking, Fraud]
       |
   [Snowflake (analytics)]
```

### Dispatch — DoorDash-in core problemi
- Sifariş gələndə, sistem **assignment problem-ini** həll etməlidir:
  - Hansı Dasher götürsün?
  - Real-time ETA-nı minimizə et.
  - Həm müştəri, həm Dasher, həm restoran faktorlarını nəzərə al.
- Bu **mixed-integer programming (MIP)** problemidir.
- DoorDash **OR-tools, Gurobi** və öz solver-lərini istifadə edir.
- Real-time constraint-lər: 500ms-də assignment qərarı lazımdır.
- Batching: eyni zamanda çox sifarişi toplu həll etmək daha yaxşı assignment verir (hər sifarişi ayrı həll etməkdənsə).

### ETA model
- Machine learning-based: restaurant prep time + driving time.
- Features: time of day, weather, restaurant historical data, distance, traffic.
- Real-time recalculation — sifariş proses zamanı ETA yenilənir.

### Sifariş lifecycle — Cadence/Temporal ilə
- Sifariş create → assign dasher → dasher gəlir restoran → pickup → deliver → ödəmə → confirm.
- Bu uzun-müddətli workflow-dur (saatlar).
- Cadence/Temporal fayda verir:
  - Sadə kod (`await deliver()` — retry, timeout, state persistence aşikar).
  - Failure-lara qarşı robustluq.
  - Tarixi workflow yenidən oxuya bilər.

### Payment
- Stripe integration.
- Idempotency keys hər ödəmə əməliyyatında (Stripe pattern).
- Reconciliation batch job-lar gündə.

## İnfrastruktur və deploy
- AWS əsaslı; multi-region.
- Kubernetes (EKS) mikroservislər üçün.
- gRPC servis mesh.
- Heavy observability: Datadog, öz dashboard-lar.
- Feature flag-lar geniş istifadə.

## Arxitekturanın təkamülü (zaman xətti)

| Year | Change |
|------|--------|
| 2013 | Python Django monolith başlanğıcı |
| 2015-2017 | Sadə sharding, Redis cache |
| 2018 | Monolit miqyaslama problemi — p99 latency ağır |
| 2019 | Kotlin ilk servislər, strangler-fig başlanğıcı |
| 2020 | COVID partlayış — infra scale problemi |
| 2021 | Blog: *Migrating from Python to Kotlin on the JVM* |
| 2022 | Cadence → Temporal miqrasiyası |
| 2023+ | AI/ML platforma geniş sərmayə, Wolt inteqrasiyası |

## 3-5 Əsas texniki qərarlar

1. **Python → Kotlin tədricən miqrasiyası.** Big-bang rewrite deyil; hər yeni feature Kotlin-də, Python tədricən ayrılır. Strangler-fig pattern nümunəsi.
2. **Temporal (Cadence) workflow-lar üçün.** Uzun-müddətli stateful sifariş lifecycle məntiqi "just code" kimi — retry, timeout, persistence Temporal-a ötürülür. Cron + state machine kabusundan qurtulur.
3. **Real optimization engine (OR-tools, Gurobi).** Naive "closest dasher" yaramır. MIP solver ilə assignment batching — hər iki tərəf üçün daha yaxşı nəticə.
4. **Native mobile apps.** Dasher app critical real-time GPS, background work, offline — React Native və ya Flutter yetərli deyil.
5. **Polyglot (Python ML + Kotlin services).** ML team-i Python-da saxladılar, yeni Python yazmadılar, Kotlin-dən gRPC ilə çağırdılar. Hər team öz stack-ində qalır.

## Müsahibədə necə istinad etmək

1. **"Legacy monolit-i necə modernləşdirərsən?"** → "DoorDash blog-undan strangler-fig nümunəsi. Big-bang rewrite etmirsən. Yeni feature-lar yeni dildə, köhnə monolitlə gRPC/HTTP ilə danışır. İllər ərzində köhnə kodu deaktiv edirsən."
2. **"On-demand matching/assignment necə dizayn edərsən?"** → "DoorDash approach: assignment MIP problem, OR-tools və ya Gurobi. Batching window (50-500ms) toplayır, toplu həll edir. Real-time constraint + optimal solution trade-off."
3. **"Long-running workflow necə qurarsan?"** → "Temporal/Cadence pattern. Sifariş lifecycle saatlar çəkir — kod kimi, sadə, amma durable. State machine + cron scheduler manual alternative kabusdur."
4. **"Python vs JVM scale-də?"** → "Python GIL, sync I/O performance ceiling-ə çatır. Kotlin JVM: tiplər, coroutines, gRPC, mature GC. DoorDash bu səbəblərdən keçdi. Əgər hələ Python-dasınızsa, narahat olmayın — ağrı nöqtəsində keçirsiniz."
5. **"Database-per-service əsl həyatda necədir?"** → "DoorDash mikroservis başına Aurora cluster. Cross-service consistency: Kafka event-lər + eventual consistency. Distributed transaction yoxdur (Saga pattern — cancel/compensate)."

## Əlavə oxu üçün (başlıqlar, URL yoxdur)
- DoorDash Engineering Blog: *Migrating DoorDash's backend from Python to Kotlin*
- DoorDash Engineering Blog: *Building Faster Indexing with Apache Kafka and Elasticsearch*
- DoorDash Engineering Blog: *How DoorDash uses Cadence for our async workflows*
- DoorDash Engineering Blog: *Meet Dispatch: DoorDash's delivery optimization*
- DoorDash Engineering Blog: *Overcoming Postgres Scalability Challenges*
- QCon / InfoQ: DoorDash engineering talks
- Temporal.io case studies on DoorDash
- Blog: *Real-time ETA at DoorDash with ML*
