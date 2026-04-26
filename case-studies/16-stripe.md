# Stripe (Lead)

## Ümumi baxış
- **Nə edir:** Developer-first ödəmə infrastrukturu. Onlayn biznes üçün API-lər: kartlar, abunə, invoicing, Connect (marketplace), Radar (fraud), Atlas (incorporation), Issuing (virtual cards). İnternetin ödəmə təbəqəsi.
- **Yaradılıb:** 2010-cu ildə Patrick və John Collison qardaşları tərəfindən.
- **Miqyas (açıq məlumat):**
  - 2023-də $1T+ illik ödəmə həcmi.
  - Shopify, Amazon (qismən), Google, Zoom, Uber, Salesforce istifadə edir.
  - Yüz milyonlarla API sorğu günlük.
  - 46+ ölkədə aktiv.
- **Əsas tarixi anlar:**
  - 2010: "/dev/payments" layihəsi kimi başladı.
  - 2011: ilk məhsul — 7 sətir Ruby ilə ödəmə API-si.
  - 2016: Stripe Radar (ML fraud detection).
  - 2018: Stripe Connect marketplace-lər üçün.
  - 2021: $95B qiymətləndirmə.
  - 2023: Idempotency keys, API versioning Stripe blog-u ilə pattern kimi məşhurlaşdı.

Stripe **API dizayn mədəniyyəti** ilə məşhurdur. Onların REST API-si və sənədləşdirməsi sənayə standartıdır. Çoxları Stripe API-sini internetin ən yaxşı API-si hesab edir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Main backend | **Ruby + Rails (custom forks)** | İlk gündən; developer velocity |
| Risk/ML, performance | **Scala, Java (JVM)** | ML Pipelines, Apache Spark üzərində |
| Data pipelines | **Scala + Spark** | Petabyte analytics |
| Primary DB | **MongoDB** → daha sonra **PostgreSQL** (bəziləri) | Start: çevik schema; sonra: tranzaksion dəqiqlik |
| Key-value / cache | **Redis** | Rate limit, hot data |
| Message queue | **Kafka** + daxili pipeline | Event streaming, audit log |
| Search | **Elasticsearch** | Daxili dashboard axtarışı |
| RPC | Custom JSON-RPC, tədricən gRPC | Servis-təki kommunikasiya |
| Frontend | **React + TypeScript** | Dashboard, checkout, elements |
| Mobile | Native iOS/Android + React Native | SDK-lar |
| Infrastructure | AWS (böyük hissəsi) | Commodity cloud, öz networking qatı |
| CI/CD | Öz tooling | Sürətli, sıx deploy |

## Dillər — Nə və niyə

### Ruby — core backend (hələ də)
- Stripe-ın əsas monoliti "pay-server" adlanır, **Ruby (Rails)** ilə yazılıb.
- Dünyanın ən böyük Ruby kod bazalarından biri.
- **Niyə Ruby/Rails:**
  - 2010-cu ildə sürətli iterasiya üçün ən yaxşı seçim.
  - Meta-programming API-ləri təsvir etmək üçün əla — Stripe-ın Ruby DSL-i onların public API-sini generasiya edir.
  - Developer velocity > raw performance onların use case-lərinin əksəriyyəti üçün.
- Stripe Ruby compiler-inə (**Sorbet**, tipli Ruby) böyük töhfə verdi, Shopify ilə birlikdə hazırladı.

### Scala / Java — ML və data
- Radar (fraud detection) — Scala + Spark.
- ML model training, feature stores.
- **Niyə:** JVM ekosistemi — Spark, Flink, Kafka — burada yaşayır.

### Go — bəzi servislər
- Proxy-lər, network-heavy servislər.
- Rust kiçik miqdarda performans-kritik yerlərdə.

### TypeScript — bütün frontend
- Stripe Dashboard, Checkout, Elements.

## Framework seçimləri — Nə və niyə
- **Rails (custom fork-lar ilə)** — pay-server-in əsası.
- **Sorbet** — Ruby üçün gradual typing. Böyük Ruby kod bazasının type safety problemini həll etdi.
- **React + TypeScript** — frontend standartı.
- **Apache Spark (Scala)** — ML/data pipeline-ları.

## Verilənlər bazası seçimləri — Nə və niyə

### MongoDB — ilkin seçim
- Erkən günlərdə çevik schema və sürətli iterasiya üçün.
- İndi **bəzi workload-lar MongoDB-də qalır**, bəziləri PostgreSQL-ə köçürüldü.
- Stripe Mongo storage engine-i üçün daxili yamalar hazırladı.

### PostgreSQL — seçilmiş yerlərdə
- Möhkəm tranzaksional zəmanət lazım olan funksiyalar.
- Bəzi yeni məhsullar.

### Kafka
- Audit log, event streaming, CDC.
- Mongo dəyişikliklərindən Kafka-ya event çıxışı (outbox-a bənzər pattern).

### Redis
- Rate limiting, idempotency-key storage, hot cache.
- **Idempotency-keys** Redis-də saxlanılır (TTL ilə); əsas DB-də deyil.

### Spark + S3-type storage
- Analytics, Radar ML training.

## Proqram arxitekturası

Stripe **böyük Ruby monoliti ("pay-server")** + **ətrafdakı servislər** modelindədir. Discord-un polyglot yanaşmasından fərqli olaraq, Stripe monoliti üstün tutur.

```
   Merchant app / checkout
             |
   [TLS, WAF, API gateway]
             |
   [pay-server (Ruby on Rails)]  <-- böyük monolit, əsas məntiq
       /    |    \
      v     v     v
    Mongo  Redis  Kafka
                   |
            [Workers: Scala/Java for risk/ML]
                   |
              [Spark for training]
```

### Idempotency keys — Stripe-ın imzası
- Stripe **`Idempotency-Key` HTTP header**-i pattern kimi məşhurlaşdırdı.
- Client UUID generasiya edir və POST sorğusunda göndərir.
- Server key + hashed request body-ni Redis-də 24 saat saxlayır.
- Həmin key ilə təkrar sorğu gələrsə, **eyni cavab** qaytarılır — heç bir yenidən emal yoxdur.
- Bu ödəmə sistemləri üçün kritikdir: şəbəkə timeout-larında retry-lar ikiqat ödəmə yaratmamalıdır.

### API versioning
- Stripe **tarixlərə əsaslanan API versiyaları** tətbiq edir (`2023-10-16`).
- Account yaradıldığı gün bir versiyada "pin" edilir.
- Daxili request router köhnə versiyadan yeni versiyaya response transform edir.
- Nəticə: breaking change-lər olur, heç bir müştəri pozulmur.

### Konfiqurasiya və feature flag-lər
- Ağır feature-flag istifadəsi — bütün yeni məhsullar flag arxasındadır.
- Regional roll-out: dashboard → canary account-lar → kiçik mercant-lər → bütün client-lər.

### Radar (fraud)
- Real-time ML inference — tranzaksiya başına < 100ms.
- Yüzlərlə feature (card BIN, country, device, graph of co-occurring cards).
- Gradient boosted trees (əvvəl), daha sonra deep learning.
- Öz trening pipeline-ı Spark üzərində.

## İnfrastruktur və deploy
- AWS-ə əsaslanır, lakin custom networking.
- Sürətli deploy tempi (gündə çox dəfə pay-server-in hissələrini deploy edir).
- Öz observability: metric-lər, distributed tracing, log aggregation.
- Compliance-ə görə ağır audit və logging: PCI-DSS Level 1, SOC 2.

## Arxitekturanın təkamülü

| Year | Change |
|------|--------|
| 2010 | Ruby + Mongo; kiçik Rails monolit |
| 2013 | Ağır Mongo skalalanması; Kafka daxil olur |
| 2015 | Scala/Spark data pipeline-lar, Radar başlanğıcı |
| 2017 | Sorbet (Ruby type checker) açıq mənbə |
| 2019 | Bəzi workload-lar Mongo-dan Postgres-ə köçür |
| 2021 | Daha çox Go, bəzi gRPC servisləri |
| 2023+ | AI/ML üçün daha dərin sərmayə (Radar-ın LLM ilə genişləndirilməsi) |

## 3-5 Əsas texniki qərarlar

1. **Monolit + Ruby + Rails, miqyasda saxlanılır.** Stripe parçalamaq əvəzinə daxili alətlərə (Sorbet, custom fork-lar) sərmayə qoydu. Developer velocity > "mikroservis olmalıdır".
2. **Idempotency-key pattern-i standarta çevirmək.** Əvvəl ad-hoc idi; Stripe public API-də standartlaşdırdı. İndi bütün ödəmə sistemlərinə kopyalanıb.
3. **Tarixlərlə API versioning.** Əksər şirkətlər `v1, v2` istifadə edir — pis. Stripe tarixlərə əsaslanan versioning tətbiq etdi, nəticədə dekadalarla geriyə uyğunluq.
4. **Mongo saxla, lakin müvafiq yerlərdə Postgres əlavə et.** "Bütün DB-ni dəyiş" deyil — workload-a görə seçim.
5. **ML-ə erkən sərmayə (Radar).** Fraud fee-lər həmişə Stripe gəlirinin böyük hissəsidir; ML model dəqiqliyi birbaşa mənfəətə təsir edir.

## Müsahibədə necə istinad etmək

1. **"Idempotent API necə dizayn edərsiniz?"** → "Stripe pattern-i: client UUID generasiya edir, `Idempotency-Key` header-i göndərir, server Redis-də key + request hash-i 24h saxlayır, retry-larda eyni cavabı qaytarır. Niyə hashed request: eyni key ilə fərqli payload collision-u aşkar etmək üçün."
2. **"API versioning necə etmək?"** → "Stripe tarixlərlə versioning, hesab pinning. Breaking change-lər hesablar üçün transformerslə görünməz."
3. **"Payment sistemi necə qurular?"** → "Əsas prinsip: immutable event log (Kafka), idempotency hər səviyyədə, reconciliation batch job-lar, hər transaction state-inin audit log-u."
4. **"Monolit, miqyasda saxlanılır?"** → "Stripe pay-server, Shopify majestic monolith, Basecamp. Monolit problem deyil — kod sahibliyi və test strategiyası problemdir."
5. **"Fraud detection?"** → "Real-time ML, sub-100ms inference, feature store, model deployment pipeline. Ensemble (gradient boosting + deep learning). Stripe Radar kimi."

## Əlavə oxu üçün
- Stripe Blog: *Designing robust and predictable APIs with idempotency*
- Stripe Blog: *APIs as infrastructure: future-proofing Stripe with versioning*
- Stripe Blog: *Online migrations at scale*
- Stripe Blog: *Sorbet: A type checker for Ruby*
- Patrick Collison: *Collison installation* (developer-first kultur)
- Jeff Weinstein: *API design lessons from Stripe*
- QCon / Strange Loop: Stripe engineering çıxışları
