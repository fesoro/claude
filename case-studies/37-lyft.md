# Lyft (Lead)

## Ümumi baxış
- **Nə edir:** Ride-sharing (taksi), bike/scooter sharing, avtomobil kirayəsi. ABŞ-da ikinci ən böyük ride-sharing platforması (Uber-dən sonra).
- **Yaradılıb:** 2012-ci ildə Logan Green və John Zimmer tərəfindən (öncə Zimride, 2007).
- **Miqyas (2024):**
  - ABŞ-da 40%+ ride-sharing bazar payı (bəzi şəhərlərdə Uber-i üstələyir).
  - 1B+ tamamlanmış gediş (kümülyativ).
  - 20M+ aktiv istifadəçi.
  - Driver/Rider cütlük matching hər saniyə yüzlərlə əməliyyat.
- **Əsas tarixi anlar:**
  - 2012: Lyft işə salındı, "dostunun araba sürdürməsi" konsepti.
  - 2016: Lyft + Uber — ABŞ-da intensiv rəqabət başlayır.
  - 2018: Flyte (ML workflow orkestrasiyası) daxili yaradıldı.
  - 2019: IPO (Uber-dən əvvəl); ilk gün $87/share, sonra düşdü.
  - 2020: COVID şoku; gəlir 75% düşdü; bərpa oldu.
  - 2021: Flyte açıq mənbəyə çevrildi, Cloud Native Computing Foundation (CNCF)-ə bağışlandı.
  - 2023: Uber ilə bazar payı mübarizəsi davam edir; xərc azaltma.

Lyft **"Uber-in sadə versiyası"** deyil — fərqli texniki qərarlar verdi. Uber 4000+ mikroservis, 20+ proqramlaşdırma dili; Lyft **Python + Go dilingüisti**, daha konservativ arxitektura. Bu rəqabət şirkətlər arasında arxitektura yanaşmalarının fərqini göstərir.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Main backend | **Python** (Flask, Tornado, sonra Envoy) | Orijinal seçim; ML ekosistemi; tez iteration |
| Performance-critical | **Go** (matching, dispatch, geo) | Real-time dispatch üçün düşük gecikməli |
| Data engineering | **Scala + Spark** | Distributed data processing |
| ML platform | **Python** (PyTorch, TensorFlow) + **Flyte** | Lyft-in öz workflow orchestration |
| RPC | **gRPC + Protobuf** | Service-to-service communication |
| Primary DB | **MySQL** (shardlanıb) | Core user + ride data |
| Cache | **Redis** | Session, rate limit, geospatial |
| Geospatial | **PostGIS + custom S2 geometry** | Rider/Driver proximity matching |
| Queue/messaging | **Apache Kafka** | Event streaming, async fan-out |
| Search | **Elasticsearch** | Driver supply/demand analytics |
| Infrastructure | **AWS** (EC2, EKS, S3, RDS) | Fully AWS, containerized |
| Container orchestration | **Kubernetes (EKS)** | Microservice orchestration |
| Monitoring | **Prometheus + Grafana + DataDog** | Full observability stack |
| Feature store | **Feast** (open-source, Lyft tərəfindən yaradılıb) | ML feature management |

## Dillər — Nə və niyə

### Python — əsas backend
Lyft Python ilə başladı, çünki 2012-də sürətli MVP üçün ideal idi. Flask-əsaslı monolit, sonra servisləri ayrıldı.

**Python-u niyə saxladılar:**
- ML/data science ekosistemi (PyTorch, scikit-learn, pandas) Python-dadır.
- Data mühəndisləri, ML mühəndisləri, backend developer-lər eyni dildə işləyir.
- Böyük işə götürmə bazası.

**Python-un harda çatışmadığı:**
- Real-time ride matching: millisaniyə-həssas.
- Geospatial hesablamalar: CPU-intensive.
- Concurrent WebSocket bağlantıları: GIL problem.

### Go — performance-critical servislər
- **Dispatch servisi** (hansı driver hansı rider-ı götürür): Go. Microsaniyələrdə qərar.
- **Geo servisi**: S2 geometry hesablamaları, driver proximity sorğuları.
- **Real-time telemetry**: driver GPS güncellemələri, yüksək yazma hızı.

**Niyə Go, Rust deyil:**
- Lyft Uber kimi (Python → Node → Go → Rust) radikal polyglot olmadı.
- Go Python mühəndisləri üçün öyrənilmə əyrisi aşağıdır.
- GC Go-da daha az problematikdir (tunable GOGC).

### Scala + Spark
- Data pipeline-lar: gediş datası, driver analytics, ML training.
- Fiyat hesablaması (surge pricing) üçün batch hesablamalar.

## Framework seçimləri — Nə və niyə

### gRPC + Protobuf — service-to-service
Lyft servisləri arasında gRPC istifadə edir. REST əvəzinə gRPC seçiminin səbəbləri:
- Güclü tipləmə (Protobuf schema-ları).
- Binary serialization — JSON-dan küçük, sürətli.
- Bidirectional streaming (driver GPS updates üçün ideal).
- Code generation: Python, Go, Java üçün avtomatik client/server stubs.

### Envoy Proxy — service mesh
Lyft **Envoy**-u yaratdı (2016) — high-performance proxy. Sonra CNCF-ə bağışlandı. İstio, AWS App Mesh, Consul Connect altında Envoy işləyir.

**Envoy niyə yaradıldı:**
- Dəstəkləmə qabiliyyəti olmayan nginx/HAProxy layer-ları vardı.
- L7 traffic management (circuit breaking, retry, timeout, tracing) lazım idi.
- gRPC proxy dəstəyi tələb olunurdu.

### Feast — ML Feature Store
Lyft 2018-də Feast-i yaratdı — ML model-lərinin real-time feature-larını idarə etmək üçün. Məsələn:
- "Bu sürücünün son 30 gündə ortalama qiymətləndirməsi nədir?"
- "Bu bölgədə son 1 saatda neçə gediş tamamlandı?"
- "Bu rider-ın iptal nisbəti nədir?"

```
Training data         Serving (real-time)
[Batch features]  →  [Feature Store (Feast)] ← [Online serving]
[Historical rides]    [Redis + DWH]              → [ML Model]
```

Feast açıq mənbəyə çevrildi, CNCF-ə daxil oldu.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL — core transactional data
- İstifadəçilər, sürücülər, gedişlər, ödənişlər.
- AWS RDS MySQL, multi-AZ, read replikalı.
- Horizontal shardlama: rider_id, driver_id üzrə.

### Redis — multi-purpose
- **Session cache:** giriş tokenləri, istifadəçi sessiyaları.
- **Rate limiting:** API request limiti (Redis INCR + TTL).
- **Geospatial:** `GEOADD`, `GEORADIUS` — yaxın sürücüləri tapmaq.
- **Pub/Sub:** real-time driver location broadcasting.

```
# Redis Geospatial — driver location
GEOADD drivers_online -122.4194 37.7749 "driver_123"
GEORADIUS drivers_online -122.4150 37.7700 2 km ASC COUNT 5
```

### PostGIS + S2 Geometry
- Kompleks geo hesablamalar üçün.
- **S2**: Google-un spherical geometry kitabxanası — Lyft bunu Go-da istifadə edir.
- Surcharge zone-ları, airport pick-up areas, geofencing.

### Apache Kafka — event backbone
- Hər gediş bir hadisə seriyasıdır: request → match → pickup → dropoff → payment.
- Real-time analytics, billing, driver earnings hesablamaları.
- ML training üçün event replay.

### Snowflake + AWS S3 — data warehouse
- Tarixçi data: gedişlər, qiymətlər, gerisixinimlar.
- BI analytics, fraud detection, pricing models üçün.

## Proqram arxitekturası

```
  [Rider App]        [Driver App]
       |                  |
  [API Gateway (Envoy)]
       |
  +----+----+--------+----------+
  |         |        |          |
[Rider   [Driver  [Dispatch   [Payment
 Service] Service] Service]   Service]
 (Python) (Python)  (Go)      (Python)
  |         |          |
  |    [Geo Service]   |
  |    (Go + Redis     |
  |     Geospatial)    |
  +-----+------+-------+
        |      |
  [MySQL   [Redis]
   Shards]
        |
  [Kafka — event bus]
  |             |
[Analytics]  [Feast Feature Store]
             |
         [ML Models: surge pricing,
          ETA prediction, fraud]
```

### Matching/Dispatch pipeline
Real-time ride matching Lyft-in ən kritik servis-idir:

```
Rider requests ride
      |
[Request Service]
      |  (publishes to Kafka)
[Dispatch Service (Go)]
      |
[Geo Service] → Find nearby available drivers
      |          within 2km radius
[Matching algorithm]
      |  (considers: ETA, driver rating, surge zone)
[Offer sent to Driver App via WebSocket]
      |
[Driver accepts/rejects]
      |
[Ride created in MySQL]
```

**Niyə Go dispatch servisi üçün:**
- Saniyədə yüzlərlə rider/driver cütü hesablanır.
- Python-un GIL bu concurrent hesablamalar üçün bottleneck idi.
- Go goroutines: fan-out driver candidates-a concurrent GEOSEARCH.

### Surge Pricing (dinamik qiymət)
- Demand/supply nisbəti → surge multiplier.
- Batch hesablanır (hər 30 saniyə), Redis-də cache-lənir.
- Geospatial zonelara bölünür — SF downtown ≠ SF airport.

## İnfrastruktur və deploy

- AWS-də tam işləyir (EKS, RDS, ElastiCache, MSK).
- **GitOps**: Kubernetes deployment-ları Git-dən idarə olunur.
- **Canary deploy**: trafik 1% → 10% → 100% tədricən artırılır.
- **Feature flags**: yeni funksiyalar şəhər-şəhər roll out olunur.
- **Multi-region**: US-East, US-West, aktiv-aktiv.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2012 | Python Flask monolit, single MySQL, AWS |
| 2013 | Servisləri ayırmağa başladı; Redis əlavə olundu |
| 2015 | Kafka event streaming; Go ilk dispatch servisləri |
| 2016 | Envoy proxy yaradıldı; gRPC qəbul edildi |
| 2017 | ML platforması gücləndirildi; surge pricing modeli |
| 2018 | Feast (feature store) daxili yaradıldı |
| 2019 | IPO; Kubernetes (EKS)-ə tam miqrasiya |
| 2021 | Flyte + Feast açıq mənbəyə çevrildi |
| 2022 | Go-ya daha çox miqrasiya; xərc optimizasiyası |
| 2023 | ML-first: ETA, pricing, fraud detection gücləndirildi |

## Əsas texniki qərarlar

### 1. Envoy proxy-nin yaradılması
**Problem:** Lyft-in service mesh-i nginx + məxsusi middleware kartmakarışıqlığı idi. Circuit breaking, retry, timeout hər servisdə ayrıca implement edilirdi. gRPC tracing yox idi.
**Seçim:** Matt Klein tərəfindən sıfırdan Envoy yazıldı: L7 proxy, gRPC dəstəyi, pluggable observability.
**Kompromislər:** Böyük engineering investisiyası; yeni infrastruktur component.
**Sonra nə oldu:** CNCF-ə bağışlandı; İstio, AWS App Mesh, Consul Connect Envoy istifadə edir. Service mesh-in standart data plane-ına çevrildi.

### 2. Python + Go — Uber-in polyglot yolunu getməmək
**Problem:** Performance-critical servislər Python-un GIL-i tərəfindən məhdudlaşdırılırdı. Amma Uber kimi 10+ dil əlavə etmək istəmirdilər.
**Seçim:** İki dil: Python (çox şey üçün) + Go (yalnız latency-sensitive servislər üçün). Java, Node, Scala yalnız data pipeline-ları üçün.
**Kompromislər:** Go-ya miqrasiya hər Python mühəndisi üçün öyrənmə xərci. İki deployment modeli.
**Sonra nə oldu:** Lyft daha kiçik, daha idarə ediləbilər engineering mühiti saxladı. Uber-in 30+ dil operasion mürəkkəbliyi olmadan performance əldə etdi.

### 3. Feast — açıq mənbə ML feature store
**Problem:** ML model-ləri üçün feature-lar (real-time + batch) iki ayrı sistemdən gəlirdi. Training-serving skew — training data-sı ilə real-time serving data-sı uyğunsuz idi.
**Seçim:** Feature store konsepti: Feature-ları bir dəfə tərif et, həm training həm serving üçün istifadə et.
**Kompromislər:** Əlavə infrastruktur; data pipeline mürəkkəbliyi.
**Sonra nə oldu:** Feast sənaye standartına çevrildi — Uber, Twitter, Shopify qəbul etdi. "Feature store" indi ML Ops-un ayrılmaz hissəsidir.

### 4. Geospatial-first arxitektura
**Problem:** Ride-sharing biznesinin mərkəzindədir: "yaxın sürücü tap, ETA hesabla, surge zone müəyyən et." MySQL-in klassik lat/lng sorğuları miqyaslana bilmirdi.
**Seçim:** Redis Geospatial (online driver tracking), PostGIS (geo boundaries), Google S2 (geometric computations in Go).
**Kompromislər:** Çoxlu geo layer-lər — operational mürəkkəblik.
**Sonra nə oldu:** Real-time matching dəqiqliyi artdı; driver wait time azaldı.

### 5. Kafka event-driven architecture
**Problem:** Bir gediş 10+ sistemi (billing, analytics, driver earnings, fraud) tetikləməli idi. Sinxron çağırışlar latency artırırdı və reliability azaldırdı.
**Seçim:** Hər ride state dəyişikliyi Kafka event-i. Downstream sistemlər abunə olur.
**Kompromislər:** Eventually consistent; debugging mürəkkəbliyi.
**Sonra nə oldu:** Yeni sistemlər əlavə etmək asanlaşdı — yalnız Kafka-ya subscribe ol.

## Müsahibədə necə istinad etmək

1. **"Real-time ride matching dizayn edin":** "Lyft Redis Geospatial (`GEORADIUS`) + Go dispatch servisi istifadə edir. Key trade-off: fan-out (bütün yaxın sürücülərə offer göndər) vs. sequential (ən yaxına birinci). Lyft hybrid: bölgəyə görə müəyyən driver-lardan quota-ya qədər offer göndərilir, ilk qəbul edənə ride verilir."

2. **"Uber vs Lyft arxitektura fərqi":** "Uber 4000+ servis, 20+ dil — polyglot ekstremal. Lyft Python + Go — konservativ. Uber-in yanaşması maksimum performans verir amma operational mürəkkəbliyi astronomikdir. Lyft-in yanaşması daha az engineer ilə idarə olunabilir sistemi saxlayır. Hər ikisi işləyir — komanda ölçüsü, mühəndislik kültürü fərqlidir."

3. **"Surge pricing necə işləyir?"** "Lyft demand/supply ratio-nu geo zone-lara görə hesablayır (hər 30 saniyə). Result Redis-də cache-lənir; rider request-ləri Redis-dən oxuyur. Bayesian model istifadə edir — tarixçi pattern-lər + real-time signal. Laravel-də: background job hər N saniyə pricing hesablayır, cache-ə yazır, API cache-dən oxuyur."

4. **"Service mesh nədir?"** "Lyft Envoy-u yaratdı — service mesh-in data plane-ı. Hər servis sidecar proxy ilə işləyir: retries, circuit breaking, timeouts, TLS, tracing avtomatik. Kodda deyil, proxy-də. PHP-də ekvivalent: API Gateway + Circuit Breaker library (Ganesha) kombinasiyası."

5. **"Feature store nədir?"** "Feast Lyft-dən gəlir. ML model-ləri iki tip feature istifadə edir: batch (tarixçi, saatlarla hesablanır) + real-time (son 5 dəqiqə). Feature store hər ikisini vahid API-dən verir. Laravel kontekstdə: Redis (real-time) + MySQL (batch) kombinasiyası + cache invalidation strategiyası eyni problemi kiçik miqyasda həll edir."

6. **"Əgər Uber-dən küçük olsaydınız, necə miqyaslanardınız?"** "Lyft nümunəsi: bir texniki seçim etmə, bir dil əlavə et (Python + Go). Infrastrukturu AWS-ə ver (EKS, RDS, MSK). Yalnız kritik path-larda Go istifadə et. Bu "pragmatic polyglot" — Uber-in "radikal polyglot"undan fərqlidir."

## Əlavə oxu üçün
- Engineering Blog: Lyft Engineering Blog (eng.lyft.com)
- Blog: *"Envoy Proxy: Lyft's Open Source Edge and Service Proxy"* (Matt Klein)
- Blog: *"Feast: Bridging ML Models and Data"* (Lyft Engineering)
- Blog: *"How Lyft Uses Kafka in the Cloud"*
- GitHub: Envoy (envoyproxy/envoy), Feast (feast-dev/feast), Flyte (flyteorg/flyte)
- Paper: *"Flyte: A Container-native Workflow Automation Platform"* (CNCF)
- Talk: Matt Klein — *"Designing for failure in distributed systems"* (QCon)
- Blog: *"Real-time Maps at Lyft"* — geospatial architecture
- Book: *"Building Microservices"* (Sam Newman) — Lyft-in yanaşmasına uyğun prinsiplər
