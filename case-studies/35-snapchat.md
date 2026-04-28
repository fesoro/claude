# Snapchat / Snap (Lead)

## Ümumi baxış
- **Nə edir:** Ephemeral foto/video mesajlaşma, Stories (24 saatlıq hekayələr), AR Lenses (artırılmış gerçəklik filterlər), Spotlight (qısa video feed), Snap Map (yer paylaşımı), Bitmoji (avatar platforma).
- **Yaradılıb:** 2011-ci ildə Stanford-da Evan Spiegel, Bobby Murphy, Reggie Brown tərəfindən.
- **IPO:** 2017-ci ildə.
- **Miqyas (2024):**
  - 400M+ gündəlik aktiv istifadəçi.
  - 5B+ snap yaradılır hər gün.
  - 200M+ istifadəçi gündəlik AR Lens istifadə edir.
  - 750M+ aylıq aktiv istifadəçi.
- **Əsas tarixi anlar:**
  - 2011: Launch — disappearing photos.
  - 2013: **Stories** ixtira olundu — 24 saatlıq ephemeral hekayələr. (Instagram 2016, WhatsApp 2017 kopyaladı.)
  - 2015: Discover — publisher content platform.
  - 2016: Spectacles (ağıllı eynək). Bitmoji alışı (~$100M). Memories (qeyri-ephemeral saxlama əlavə olundu).
  - 2017: IPO. Snap Map.
  - 2018: Böyük redesign — məşhur uğursuzluq. DAU azaldı. Instagram Stories rəqabəti kəskinləşdi.
  - 2021: Spotlight (TikTok-a cavab, alqoritmik video feed).
  - 2022: Snap+ abunəlik. My AI (ChatGPT inteqrasiyası).
  - 2023: AR Enterprise, Snap AI. AR Spectacles 5-ci nəsil.

Snap **"ephemeral by design"** prinsipi ilə məşhurdur. Həmçinin **Stories formatının ixtiraçısı** (sonradan sənayenin standartı oldu) və **AR Lenses-in texniki mükəmməlliyi** ilə seçilir. **100% Google Cloud Platform** istifadə edən ən böyük şirkətlərdən biridir.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Backend | **Go** (əsas), **Python** (ML), **Java** (legacy, bəzi servislər) | Go-nun concurrency modeli real-time delivery üçün uyğundur |
| AR / Lens engine | **C++** (device-side), **Objective-C/Swift** (iOS), **Kotlin** (Android) | Native performance; 60fps AR filterlər üçün JVM kifayət etmir |
| Frontend (web) | **TypeScript + React** | Standart |
| Primary DB | **Google Spanner** | Qlobal tranzaksional consistency; multi-region |
| Wide-column / analytics | **Google Bigtable** | Snap history, engagement events, time-series |
| Analytics | **Google BigQuery** | Petabayt miqyasında ad analytics |
| Cache | **Redis**, **Memcached** | Hot user data, rate limiting, session |
| Messaging / events | **Apache Kafka**, **Google Pub/Sub** | Event streaming; real-time fan-out |
| Object storage | **Google Cloud Storage** | Snap media (photos, videos, Stories) |
| CDN | **GCP CDN + Fastly** | Qlobal media delivery |
| Search | **Elasticsearch** | Story axtarışı, Discover kontent |
| ML platform | **SnapML** (öz), **TFX**, **Vertex AI** | On-device inference + cloud training |
| Video processing | **Öz pipeline** (FFmpeg əsaslı) + **Transcoder API** | Real-time video encoding, format conversion |
| Infrastructure | **100% Google Cloud Platform** | Milliard dollarlıq çoxillik müqavilə; GCP ML tooling üstünlüyü |
| Orchestration | **Kubernetes (GKE)** | Sənaye standartı |
| Monitorinq | **Prometheus, Grafana, Cloud Monitoring** | Standart observability yığını |

## Dillər — Nə və niyə

### Go — əsas backend
- Snap backend servisləri əsasən Go-da yazılıb.
- **Niyə Go:** Goroutine-lər real-time mesaj delivery üçün uyğundur — hər bağlantı üçün ayrı goroutine; Go-nun GC-si Java-nın GC-sindən daha proqnozlaşdırılan gecikmə verir; statik binary deploy sadədir; yaxşı HTTP/2 + gRPC dəstəyi.
- Chat fan-out, presence, push notification servisləri hamısı Go-dadır.

### Python — ML ekosistemi
- Model training (TensorFlow, PyTorch), data pipeline-ları, ML feature engineering.
- SnapML on-device inference üçün Python training pipeline istifadə edir; nəticə modellər C++-a export olunur.
- Airflow ilə orchestrate olunan Spark job-ları da Python-dadır.

### C++ — AR core
- Snap Lenses-in device-side AR engine-i C++-da.
- Üz tanıma, facial landmark detection (468 facial point real-time), dünya kamerasında object placement — hamısı C++-da SIMD optimallaşdırılması ilə.
- Lens Studio (developer aləti) da C++ core üzərinde qurulub.
- iOS/Android cihazlara WASM vasitəsilə də çatdırıla bilər (web Lenses üçün).

### Java
- Bəzi legacy backend servislər hələ Java-dadır.
- Əsasən yeni servisler Go-ya keçir.

## Framework seçimləri — Nə və niyə

- **gRPC** — servislər arası kommunikasiya. HTTP/2 üzərindən binary serialization, Go/Python/C++ üçün koda generasiya.
- **Protobuf** — standart data format. Snap bütün servislər arası kommunikasiyada Protobuf istifadə edir.
- **GKE (Google Kubernetes Engine)** — container orchestration. GCP-nin idarə olunan K8s.
- **Lens Studio** — Snap-ın AR lens yaratma SDK-sı. Developer-lər öz lenslərini burada hazırlayır; Snap bunları review edib App-a daxil edir. 3M+ community lens var.

## Verilənlər bazası seçimləri — Nə və niyə

### Google Spanner — qlobal tranzaksional store
- Snap-ın user account-ları, friendship graph, subscription data üçün əsas tranzaksional DB.
- **Niyə Spanner:** Multi-region active-active yazılar; strong consistency; SQL interface; TrueTime-əsaslı external consistency.
- Snap 100% GCP-dədir, Spanner onlar üçün "managed Postgres at global scale" rolunu oynayır.
- Kompromis: Spanner bahalı (serverless deyil, node-based pricing); sorğu tənzimləməsi Postgres-dən fərqlidir.

### Google Bigtable — geniş sütun
- **Snap history** (kim kimə snap göndərdi, nə vaxt), **engagement events**, **time-series analytics**.
- Bigtable-ın yazıya optimallaşdırılmış geniş-sütun modeli "user_id + timestamp" sorğu nümunəsinə mükəmməl uyğunlaşır.
- Cassandra ilə müqayisə: Bigtable idarə ediləndir, Cassandra özünü idarə etmək lazımdır. Snap GCP-yə committed olduğu üçün Bigtable uyğundur.

### Redis
- Hot cache: istifadəçi session-ları, friend list-lər, rate limiting.
- Real-time presence tracking (kim online-dır).
- Pub/sub messaging üçün köməkçi.

### Google Cloud Storage (GCS)
- Bütün media: foto, video, Stories, Memories.
- Snap-ın media pipeline-ı GCS-ə yazır → CDN-dən xidmət göstərilir.
- Ephemeral media (snaps, stories) avtomatik TTL ilə silinir.

## Proqram arxitekturası

### Ephemeral messaging arxitekturası

Snap-ın ən mühüm arxitektura qərarı: **mesajlar tranzitdədir, arxiv deyil**.

```
   Mobile Client
        |
   [API Gateway (Go)]
        |
   [Chat Server (Go)]
        |
   +--->  GCS (media bytes)
   |
   +--->  Bigtable (message metadata, receipt)
   |
   +--->  Push Notification Service
                |
           [APNs / FCM]  ---> Receiver's device
                |
           [Receiver opens snap]
                |
           [Media fetched from GCS CDN]
                |
           [Delivered = deleted from server]
```

- Snap göndəriləndə: media GCS-yə upload olunur, metadata Bigtable-a yazılır.
- Receiver açdıqdan sonra: media GCS-dən silinir (TTL əsaslı), metadata "viewed" işarəsi alır.
- Server mesaj məzmununu oxuya bilmir (E2EE tətbiqindən əvvəl partial, indi selective).

### Stories arxitekturası

Stories snaps-dən fundamental fərqlidir: **ordered list of media with per-user view tracking**.

```
   User posts Story snap
        |
   [Story Server]
        |
   +--->  GCS (media, 24h TTL)
   |
   +--->  Spanner (story metadata: author, order, expiry)
   |
   +--->  Bigtable (per-viewer view tracking)
        |
   Friend requests story feed
        |
   [Story Feed Service] --- Spanner (friend list)
        |
   [Cache: Redis + CDN edge cache]
```

- TTL mexanizmi: GCS object expiration + background job silinmə üçün əlavə işləyir.
- View tracking: Bigtable-da `story_id + viewer_id` cərgəsi. Yazar öz story-nin kim baxdığını görə bilər.
- Fan-out: Push notification followers-ə göndərilir (məşhur istifadəçilər üçün rate-limited).

### AR Lenses arxitekturası

```
   Camera frame → On-device C++ AR engine
        |
   Face detection → 468 landmark tracking
        |
   3D face mesh → Shader/material overlay
        |
   Rendered frame → Video encoder → Display
```

- Bütün AR processing cihazda baş verir (server-side AR yoxdur).
- Lens Studio ilə qurulmuş community lenslər on-device JS sandbox-da işləyir.
- SnapML: model compression + on-device inference engine (Core ML / TFLite altında).

### Spotlight (Alqoritmik Feed)

```
   Content ingestion → Kafka
        |
   Feature extraction → ML pipeline (Python/TFX)
        |
   Engagement prediction → Ranking model
        |
   Personalized feed → Redis (hot) + Bigtable (cold)
        |
   Client fetch → Paginated recommendations
```

TikTok/Instagram Reels ilə eyni pattern: Kafka-ya gelen content → ML scoring → personalized feed.

## İnfrastruktur və deploy

- **100% Google Cloud Platform.** Snap GCP ilə çoxillik milliard dollarlıq kommitment imzaladı (2018: $2B, 2022: yeniləndi). Bu onları GCP-nin ən böyük müştərilərindən biri edir.
- **Kubernetes (GKE)** bütün servislər üçün. Rolling deployments + canary releases.
- **CI/CD:** Spinnaker (Netflix OSS) və ya öz pipeline-ları ilə canary → production.
- **Multi-region:** GCP-nin global bölgə şəbəkəsi üzərindən — us-central1, europe-west1, asia-east1 və s.
- **Monitoring:** Cloud Monitoring + Prometheus + öz dashboardlar. SLO-based alerting.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|----|------------|
| 2011 | Launch — ephemeral photo messaging, AWS-də |
| 2012–2013 | Stories ixtirası. İlk ciddi miqyas böhranı |
| 2014–2015 | Discover (publisher content). AWS-dən GCP-yə keçid başlanır |
| 2016 | Bitmoji alışı. Memories (non-ephemeral storage). GCP commits güclənir |
| 2017 | IPO. Go backend adoption sürətlənir. Snap Map |
| 2018 | Böyük redesign uğursuzluğu. ML-əsaslı content moderation intensivləşir |
| 2019–2020 | AR Enterprise. Spotlight (alqoritmik feed) |
| 2021 | Snapchat+ abunəlik. AR Lenses — 3M+ community lens |
| 2022 | My AI (ChatGPT inteqrasiyası). 750M+ MAU |
| 2023–2024 | AR Spectacles yenisi. Snap AI. Generative AI lenses |

## Əsas texniki qərarlar

### 1. Ephemeral by design — deletion as first-class citizen
**Problem:** Saxlama olmadan mesajlaşma necə qurulur? Bütün sistemlər "əlavə et, saxla, oxu" nümunəsi üzərindədir.
**Seçim:** Media GCS-yə yazılır, amma 24 saatdan (Stories) və ya oxunduqdan (snaps) sonra silinir. Server "relay" rolundadır, "arxiv" deyil.
**Kompromislər:** Server-side delete mükəmməl E2EE-yə ziddir (sonradan əlavə olundu). Silmə həmişə deterministic deyil (crash recovery, CDN cache).
**Sonra nə oldu:** Snap mədəniyyəti "privacy by default" oldu. GDPR-dən əvvəl bu yanaşma rəqabət üstünlüyü idi. 2016-da Memories əlavə edərək qeyri-ephemeral saxlama da gətirdilər — bu fəlsəfi kompromis idi, amma istifadəçilər istədi.

### 2. Stories formatının ixtirası
**Problem:** Snaps birdəfəlikdir. Bir günün hadisələrini necə bölüşmək olar, hər şeyi ayrıca göndərmədən?
**Seçim:** Sıralı media siyahısı + 24 saatlıq TTL. Followers öz tempolarında baxa bilər. Hekayə başlığı altında sıralı content.
**Kompromislər:** Fan-out məşhur istifadəçilər üçün bahalıdır (bir story, milyonlarla notification). View tracking Bigtable-da saxlamaq lazım.
**Sonra nə oldu:** 2016-da Instagram Stories, 2017-da WhatsApp Status, Facebook Stories kopyaladı. "Story" format artıq sənayenin standartıdır. Snap "ixtiraçı" olmasına baxmayaraq rəqabəti itirdi — çünki Instagram-ın böyük istifadəçi bazası var idi.

### 3. 100% GCP kommitment
**Problem:** 2018-də Snap AWS-dən çıxmaq qərarı aldı. Cloud multi-vendor olmaq əvəzinə GCP-yə tam mərc etdi.
**Seçim:** GCP ilə çoxillik milliard dollarlıq müqavilə. BigQuery, Spanner, Vertex AI, Pub/Sub hər yerdə.
**Kompromislər:** Vendor lock-in — GCP baha olarsa, köçmək çox çətindir. Bəzi AWS servisləri GCP-dən güclüdür (bəzi ML tooling-ləri, Lambda-vari serverless).
**Sonra nə oldu:** GCP inteqrasiyası Snap-a BigQuery analitika, Spanner qlobal consistency, Vertex AI ML tooling verdi. Bitmoji, AR Lenses, Spotlight — hamısı GCP ML infrasında işləyir.

### 4. On-device AR (server-side deyil)
**Problem:** Milyonlarla istifadəçi real-time AR Lenses istifadə edir. Server-side rendering mümkün deyil (latency, cost).
**Seçim:** AR engine tamamilə device-side C++-da. 468 facial landmark tracking 30-60fps-də cihazda işləyir. Server yalnız model update-ləri göndərir.
**Kompromislər:** Lens capability device hardware-ına bağlıdır. Köhnə telefonlarda bəzi lenslər işləmir.
**Sonra nə oldu:** SnapML on-device inference engine yarandı. Lens Studio ilə 3M+ community lens. Bu Snap-ın əsas rəqabət üstünlüyünə çevrildi — Google, Meta AR filter cəhdləri eyni keyfiyyətə çata bilmirdi.

### 5. Spotlight — alqoritmik feed (TikTok-a cavab)
**Problem:** 2020-ci ildə TikTok-un for-you alqoritmik feed-i gəncləri Snapchat-dan uzaqlaşdırırdı.
**Seçim:** Snap Spotlight — alqoritmik video feed. Yaradıcılara $1M/gün priz fondundan ödəniş (erkən davranışı cəlb etmək üçün).
**Kompromislər:** Content moderation yük artdı (alqoritmik kəşf NSFW məzmunu geniş yaya bilər). Priz fondu çox bahalı idi — sonradan azaldıldı.
**Sonra nə oldu:** Spotlight DAU böyüdü, amma TikTok dominantlığını qıra bilmədilər. AR Lenses Snap-ın həqiqi differentiator-u olaraq qaldı.

## Müsahibədə necə istinad etmək

1. **Ephemeral storage design:** "Snap-ın yanaşması: media object storage-ə yazılır, TTL-lə silinir. Metadata ayrı DB-dədir. Bu 'delivery confirmation + delete' nümunəsi mesajlaşma sistemlərinin əsasıdır. Laravel-də oxşar nümunə: fayl S3-ə yazılır, job işlənəndən sonra silinir."

2. **Stories data model:** "Stories bir sıralı media siyahısıdır, timestamp-əsaslı TTL ilə. Fan-out məşhur istifadəçilər üçün pull, əksəri üçün push. Bu Instagram Reels, YouTube Shorts-la eyni fundamental pattern."

3. **On-device ML vs server-side:** "Snap AR Lenses decision: hər şeyi device-də işlət, server yalnız model çatdırır. Bu latency sıfırdır, server xərci yoxdur. Trade-off: device capability-dən asılıdır, model update cycle serverə bağlıdır."

4. **Vendor lock-in trade-off:** "Snap GCP-yə tam mərc etdi — milliard dollarlıq kommitment. Benefit: dərin inteqrasiya (Spanner, BigQuery, Vertex AI). Risk: GCP bahalaşarsa və ya xidmət dayanarsa, seçim yoxdur. Startup üçün oxşar qərar: PostgreSQL managed (Supabase, Neon) vs MySQL managed (PlanetScale) — ikincisini seçsəniz, migration bahalı olur."

5. **Stories formatını kopyalanmaq:** "Snap Stories ixtira etdi, amma Instagram bazası böyük olduğu üçün eyni funksiyanı kopyalayıb bazara çıxardı. Texniki innovasiya təkbaşına bazarda qalib olmağa bəs etmir — distributor/network effekti də vacibdir."

6. **Real-time fan-out:** "Snap-da story fan-out: məşhur istifadəçilərin story-ləri cache-ləniр (pull model), adi istifadəçilərinki push olunur. Bu tam olaraq Instagram, Twitter, Reddit-in etdiyi hibrid push/pull nümunəsidir."

## Əlavə oxu üçün
- Snap Engineering Blog: *How Snap Stores and Processes 3 Billion Views Per Day*
- Snap Engineering Blog: *Building Snap Map*
- Talk: *Snap's ML Platform* (ML Summit, müxtəlif illər)
- Snap Lens Studio documentation — AR development
- Paper: *SnapML: An ML framework for Apple devices*
- Talk: *Engineering Ephemeral Content at Scale* (QCon, Snap engineering)
- Blog: *How We Built Bitmoji for Stories*
- GCP Case Study: *Snap Inc. and Google Cloud Partnership*
