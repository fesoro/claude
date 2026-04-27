# Feature Store Design (Lead)

## İcmal

**Feature store** — ML modelləri üçün feature-lərin (model input-ları) yaradılması, saxlanılması, versiyalanması və həm training, həm də inference zamanı consistent şəkildə təqdim edilməsi üçün mərkəzləşdirilmiş sistemdir. Feast, Tecton, Hopsworks, Uber Michelangelo, Airbnb Zipline, Netflix Axion belə platformalardır.

Əsas məsələ — **training-serving skew**: training kodu (Python, Spark, offline) feature-ləri bir cür hesablayır, production inference (Java, Go, online) başqa cür. Nəticədə model training-də 0.95 AUC göstərir, canlıda 0.70-ə enir. Feature store bu fərqi aradan qaldırır.


## Niyə Vacibdir

ML modeli training-də istifadə etdiyi feature-lərin real-time serving-də eyni dəyərləri alması point-in-time correctness tələb edir. Feast/Tecton kimi feature store offline/online store-u sinxronlaşdırır; training-serving skew-nin qarşısını alır. ML-enabled backend sistem üçün vacib infrastruktur komponentdir.

## Problem (The Problem)

ML sistem iki fazada feature istifadə edir:

1. **Training** — historical data, batch, millions of rows, latency əhəmiyyətsiz (saatlarla Spark job)
2. **Inference** — canlı istifadəçi üçün, 1 satır, p99 < 10ms SLA

Eyni feature-ləri iki fərqli pipeline-da dublikat kod ilə yazsanız:

- **Skew**: `avg_order_value_30d` training-də SUM/COUNT Python-da, production-da SQL-də fərqli rounding → fərqli dəyərlər
- **Reusability yoxdur**: eyni `user_age_days` feature-i 5 model komandası 5 dəfə yazır
- **Time travel çətin**: 6 ay əvvəlki training üçün o zamanki feature dəyərləri lazımdır — indi bazada yoxdur (user churn etmiş, profil silinmiş)
- **Data leakage**: training data-ya gələcək məlumat qarışır (model hiyləgər dəqiqlik göstərir, canlıda çökür)

Feature store bunların həlli:

- Feature-lərin tək **source of truth**
- Model-lər arası **reusability**
- Offline və online store arasında **consistent values**
- Training üçün **point-in-time correct** time travel

## Arxitektura

```
                   ┌─────────────────────────┐
                   │  Feature Registry       │
                   │  (metadata, schema,     │
                   │   owner, lineage)       │
                   └─────────────────────────┘
                               ▲
                               │ define
                   ┌───────────┴────────────┐
                   │  Transformation Layer  │
                   │  (SQL / Spark / PyFn)  │
                   └───────────┬────────────┘
                               │
              ┌────────────────┼────────────────┐
              │                                 │
              ▼                                 ▼
    ┌─────────────────┐              ┌──────────────────┐
    │  Batch Pipeline │              │ Stream Pipeline  │
    │  (Airflow/Spark)│              │  (Flink / Kafka) │
    └────────┬────────┘              └────────┬─────────┘
             │                                 │
             ▼                                 ▼
    ┌─────────────────┐              ┌──────────────────┐
    │ Offline Store   │  materialize │  Online Store    │
    │ (Parquet/BQ/    │─────────────►│  (Redis/Dynamo/  │
    │  Snowflake)     │              │  Cassandra)      │
    └────────┬────────┘              └────────┬─────────┘
             │ point-in-time                  │ get_online_features
             ▼                                 ▼
    ┌─────────────────┐              ┌──────────────────┐
    │ Training Dataset│              │  Serving API     │
    │  (Spark join)   │              │  (gRPC, <10ms)   │
    └─────────────────┘              └──────────────────┘
```

### Komponentlər

**1. Feature Registry** — metadata catalog: feature adı, type, description, owner team, source dataset, freshness SLA, PII tag. Feast YAML + SQLite, Tecton Postgres.

**2. Transformation Layer** — feature necə hesablanır. Python dekorator və ya SQL:

```python
@feature_view(entity=user, ttl=timedelta(days=7))
def user_features(df):
    return df.groupBy("user_id").agg(
        F.avg("order_value").alias("avg_order_value_30d"),
        F.count("order_id").alias("order_count_30d")
    )
```

**3. Offline Store** — training üçün historical values. Columnar format, geniş query-yə uyğun: Parquet on S3, BigQuery, Snowflake, Redshift, Delta Lake. Hər satır `(entity_id, feature_value, event_timestamp)`.

**4. Online Store** — inference üçün son dəyər. KV lookup, p99 < 10ms: Redis, DynamoDB, Cassandra, ScyllaDB. Key: `feature_view:entity_id`.

**5. Materialization Pipeline** — offline → online sync. Daily batch job offline store-dan son dəyərləri çəkib online store-a yazır. Streaming features üçün Flink birbaşa online-a yazır.

**6. Serving API** — `get_online_features(entity_ids, feature_refs)` — batch lookup, gRPC / HTTP.

## Point-in-Time Correctness

Ən kritik anlayış. Training dataset qurmaq üçün labeled events (məsələn, "user X etdi purchase at T") həmin `T` anındakı feature values ilə birləşdirilməlidir, **not current values** və **not future values**.

```
Event timestamp: 2026-01-15 10:00:00

  user_123  ──┐
              │ join "as of 2026-01-15 10:00:00"
              ▼
  Feature history timeline:
  2026-01-10: avg_order_value = 45.00    <-- stale
  2026-01-14: avg_order_value = 52.00    <-- USE THIS (latest before T)
  2026-01-15 11:00: avg_order_value = 58 <-- FUTURE, forbidden (leakage)
```

Naive SQL `LEFT JOIN` ilə bu olmur — `AS OF` join, `MERGE ASOF` (Pandas), Spark range join window lazımdır. Feature store bunu avtomatlaşdırır:

```python
training_df = store.get_historical_features(
    entity_df=labels_df,            # user_id, event_timestamp, label
    features=["user_features:avg_order_value_30d",
              "user_features:order_count_30d"]
).to_df()
```

Nəticə — hər row-da label və `event_timestamp` anından ən son mövcud feature value. Leakage yoxdur.

## Feature Tipləri (Feature Types)

**Batch features** — gündəlik / saatlıq recompute. User lifetime purchases, 30d average. Airflow + Spark. Freshness: saatlarla.

**Streaming features** — near real-time. Last 5 min clicks, current session length. Kafka → Flink → online store. Freshness: saniyələr.

**On-demand features (request-time)** — inference zamanı hesablanır, store-da saxlanmır. IP geolocation, device fingerprint, cart total. Request context-dən gəlir.

**Composite features** — digər feature-lərdən törəmə: `clicks_per_impression = clicks / impressions`.

## Entity və Feature View

**Entity** — feature-lərin subyekti: user, product, order, session. Unique identifier + type. Composite key mümkündür (user_id + product_id).

**Feature View** — bir entity üçün eyni source və TTL-dən feature qrupu:

```python
user_stats = FeatureView(
    name="user_stats",
    entities=[user],
    ttl=timedelta(days=7),
    source=BigQuerySource(table="analytics.user_daily"),
    schema=[
        Field(name="total_orders", dtype=Int64),
        Field(name="avg_order_value", dtype=Float32),
    ]
)
```

TTL: online store-da dəyər nə qədər qalır. Expire olsa, default value qaytarılır.

## Online Serving SLA

Inference path p99 < 10ms. Necə əldə edilir:

- **Pre-materialized**: feature hesablanma batch-də, online store-da sadəcə GET
- **Batch lookup**: bir istifadəçi üçün 50 feature-i tək Redis `MGET` komandası ilə
- **Connection pooling**: gRPC persistent connections, cold TCP handshake yoxdur
- **Co-location**: feature store və model serving eyni region, eyni zone
- **Schema-based codegen**: serialization overhead minimum (Protobuf, FlatBuffers)

```
Request → Feature Store API (2ms) → Redis MGET (1ms) → Serialize (1ms) → Response
                                                                           (~5ms p99)
```

## Freshness SLA-lar

Hər feature-in freshness SLA-sı fərqli:

- **Fraud score signals**: <1 dəqiqə (real-time)
- **User clicks last hour**: 5 dəqiqə
- **Product ranking features**: 1 saat
- **User demographics**: 1 gün (batch)
- **Historical aggregates**: həftəlik

Stale feature → qərar keyfiyyəti düşür. Freshness monitor metriki: `now - feature.event_timestamp`.

## Storage Sizing

Online store üçün sadə hesablama:

```
Feature count:     1000
User count:        100M
Avg feature size:  8 bytes (Float64)
Raw:               1000 × 100M × 8 = 800 GB

+ Redis overhead (keys, metadata) ~30%: ≈ 1 TB
```

Optimallaşdırmalar:
- **TTL**: inactive users 30 gün sonra expire
- **Feature selection**: model-də istifadə olunmayan feature-ləri materialize etmə
- **Compression**: Float32 əvəzinə Float16, quantization
- **Sharded Redis cluster** və ya DynamoDB auto-scale

Offline store: illərlə tarix, petabyte-lara qədər. S3 + Parquet ucuz ($23/TB/month).

## Data Quality və Monitoring

- **Schema validation** — feature type, range, null rate
- **Drift detection** — feature distribution zamanla dəyişir (covariate shift). KS test, PSI > 0.25 alert
- **Freshness alerts** — materialization pipeline gec qaldıqda
- **Null rate spike** — upstream source pozulub
- **Feature importance drift** — model explanation dəyişir

Monitoring olmasa feature store "silent failure" verir — model yavaş-yavaş pisləşir, heç kim bilmir.

## Governance

- **ACL**: PII feature-lərə yalnız compliance-approved service-lər
- **PII tags**: feature metadata-da `pii=true`, auto-masking
- **Lineage**: bu feature hansı dataset-dən, hansı transformation ilə gəlir
- **Versioning**: `user_features_v2` — breaking change etsən yeni view, köhnəsi deprecate
- **Cost attribution**: hansı komanda nə qədər compute / storage istifadə edir

## Real-Time Inference Pipeline

```
1. API request:  POST /score { "user_id": 42, "amount": 199.99 }
        │
        ▼
2. Feature fetch: get_online_features(
        entity="user_id=42",
        features=["user_stats:avg_order_value_30d",
                  "user_stats:fraud_reports_7d",
                  "user_stats:account_age_days"])
        │
        ▼
3. On-demand: combine with request context (amount, ip_country)
        │
        ▼
4. Model serving: TF Serving / TorchServe gRPC → prediction
        │
        ▼
5. Return:  { "fraud_score": 0.87, "decision": "review" }

Total budget: 50ms  (features 5ms + model 20ms + network 5ms + app logic 20ms)
```

## Tools (Alətlər)

- **Feast** — open source, Python-first, vendor-agnostic
- **Tecton** — managed, Feast co-founders, enterprise
- **Hopsworks** — open + managed, time-travel strong
- **Vertex AI FS / SageMaker FS / Databricks FS** — cloud managed

Şirkət daxili: **Uber Michelangelo** (pioner, end-to-end ML), **Airbnb Zipline** (time travel + streaming), **Netflix Axion** (Metaflow integration), **Spotify Jukebox** (personalization).

## Laravel Inference Endpoint

Laravel fraud scoring. Feature-ləri Feast-dən (gRPC) alır, TF Serving-ə yollayır.

```php
namespace App\Services;

use Feast\ServingServiceClient;
use Feast\GetOnlineFeaturesRequest;
use Illuminate\Support\Facades\{Http, Cache};

class FraudScoringService
{
    private ServingServiceClient $featureClient;

    public function __construct()
    {
        $this->featureClient = new ServingServiceClient(
            config('ml.feast_endpoint'),
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        );
    }

    public function score(int $userId, float $amount, string $ipCountry): array
    {
        // 1. Feature cache (hot users, 60s TTL)
        $features = Cache::remember("features:user:$userId", 60,
            fn () => $this->fetchFeatures($userId));

        // 2. On-demand features (request context)
        $features['amount'] = $amount;
        $features['ip_country_risk'] = $this->countryRisk($ipCountry);

        // 3. Model serving
        $response = Http::timeout(0.5)
            ->post(config('ml.tf_serving_url').'/v1/models/fraud:predict',
                ['instances' => [$features]]);

        $score = $response->json('predictions.0.0');
        return ['user_id' => $userId, 'score' => $score,
                'decision' => $this->decide($score)];
    }

    private function fetchFeatures(int $userId): array
    {
        $request = new GetOnlineFeaturesRequest();
        $request->setFeatures([
            'user_stats:avg_order_value_30d',
            'user_stats:fraud_reports_7d',
            'user_stats:account_age_days',
        ]);
        $request->setEntities(['user_id' => [$userId]]);

        [$response] = $this->featureClient->GetOnlineFeatures($request)->wait();
        return $this->parseFeatures($response);
    }

    private function decide(float $score): string
    {
        return $score > 0.9 ? 'block' : ($score > 0.6 ? 'review' : 'allow');
    }
}
```

Latency budget: Feast 5ms + TF Serving 15ms + Laravel overhead 10ms ≈ 30ms, p99 < 50ms.

## Trade-offs

**Batch vs streaming features:**
- Batch ucuz, consistent, amma stale
- Streaming fresh, amma infrastructure (Flink cluster) bahalı və debug çətin
- Çox feature batch kifayət edir, kritik signal-lar üçün streaming

**Feature store vs SQL + Redis:**
- Kiçik komanda (1-2 model) üçün feature store overkill — SQL job + Redis KV bəs edir
- 10+ model, 5+ komanda olsa duplication əzab verir, feature store ROI verir
- Governance / compliance (banking, healthcare) məcburiyyət

**Managed (Tecton) vs self-hosted (Feast):**
- Managed $50k+/il, amma ops yükü yox
- Self-hosted ucuz, amma SRE komanda lazım

## Praktik Tapşırıqlar

**S1: Training-serving skew nədir və feature store necə həll edir?**
C: Training (offline Python / Spark) və serving (online Java / Go) feature-i fərqli hesablayır — eyni transformation iki dəfə yazılır, implementation fərqli olur. Feature store tək transformation definition-dan batch (offline store) və streaming (online store) pipeline-ları generate edir. Eyni kod, eyni nəticə.

**S2: Point-in-time join niyə lazımdır?**
C: Training data hazırlayarkən hər labeled event üçün həmin anda mövcud olan feature dəyərini götürməlisən, gələcəkdəki dəyəri yox. Naive `LEFT JOIN` müasir dəyəri alır — bu data leakage-dir, model overfit olur. `AS OF` join event timestamp-dən əvvəlki ən son feature value-nu götürür. Feature store bunu `get_historical_features` API-ilə avtomatlaşdırır.

**S3: Niyə offline və online store ayrıdır?**
C: Fərqli access pattern-lər. Offline — böyük scan, join, agregasiya, latency mühüm deyil (Parquet / BigQuery ideal). Online — tək entity üçün O(1) lookup, p99 < 10ms (Redis / DynamoDB ideal). Bir storage hər ikisini yaxşı etmir — columnar analitika KV lookup-dan zəifdir.

**S4: Streaming feature-ləri necə implement edirik?**
C: Kafka topic-dan event oxuyan Flink job aggregation window (tumbling / sliding) ilə feature hesablayır və hər window sonunda online store-a yazır. Məs: `last_5min_clicks` — Flink 5 dəqiqəlik window-da user üçün count, nəticə Redis-ə `user_clicks_5m:{user_id}` → value. Offline store-a eyni feature batch Spark job ilə yazılır (dual pipeline problem — Kappa arxitektura bunu həll etməyə çalışır).

**S5: Feature store-da versiyalanma necə aparılır?**
C: Breaking change olsa yeni feature view (`user_stats_v2`) — köhnəsi deprecated mark, amma yaşayır modeli köhnə versiyadan istifadə edənlər bitirənə qədər. Schema əlavəsi (yeni column) backward-compatible, versiya dəyişmir. Feature hesablama məntiqini dəyişsən təsirlənən model-lərin training-i təkrar lazımdır — lineage metadata bunu track edir.

**S6: Drift detection niyə kritikdir?**
C: Dünya dəyişir — user behavior, market conditions, hətta COVID kimi qara qu. Model train olan distribution ilə production distribution fərqlənəndə (covariate shift) tahmin keyfiyyəti səssizcə pisləşir. PSI (Population Stability Index) > 0.25 və ya KS test p<0.05 alert verir, retraining trigger olur. Drift olmasa model silent degradation edir, biznes KPI düşənə qədər görünmür.

**S7: 10ms p99 SLA-nı necə saxlayırıq?**
C: (1) Pre-materialized online store — hesablama zamanı yox, lookup zamanı; (2) Batch MGET — bir istifadəçi üçün 50 feature tək Redis round-trip; (3) Co-location — feature store və model serving eyni zone; (4) Connection pool (gRPC persistent); (5) Protobuf serialization; (6) Tail latency üçün hedged request (iki replica-ya parallel, ilkin cavab götür); (7) Kritik features-i app-a ən yaxın cache-lə (L1 cache).

**S8: Kiçik şirkət üçün feature store gərəkdirmi?**
C: 1-2 model varsa yox — SQL view + Redis KV + notebook kifayətdir, feature store overhead-dir. 5+ model, fərqli komandalar, eyni feature-ləri yenidən yazmaq başlayanda ROI görünür. Compliance tələbi (banking, healthcare — PII lineage, audit) olsa məcburiyyətdir. Başlanğıc üçün Feast (open source) yaxşı — sadə, Python-native, sonradan Tecton / managed-ə keçmək mümkündür.

## Praktik Baxış

- **Tək transformation definition** — batch və streaming pipeline-ı eyni koddan generate et (skew olmaz)
- **Point-in-time join mütləq**: naive join training-də silent data leakage yaradır
- **Entity-ni düzgün model et**: composite key (user_id + device_id) bəzən tək id-dən yaxşıdır
- **TTL hər feature view-də təyin et**: expire yoxdursa online store şişir
- **Freshness SLA hər feature üçün dokumentləşdir**: kritik yoxsa standart
- **Drift monitoring avtomatik**: manual kontrol unutulur
- **PII tagging at registry level**: downstream access policy avtomatik tətbiq olunsun
- **Lineage track et**: hansı model hansı feature istifadə edir (refactor, deprecation rahat)
- **Versioning disiplinli**: breaking change = yeni view, köhnəni 60 gün saxla
- **Feature reusability stimul ver**: komandalar öz dublikatını yazmaq əvəzinə mövcudu istifadə etsin
- **Storage hygiene**: istifadə olunmayan feature view-ləri sil (cost discipline)
- **Fallback values**: online store miss olduqda model default feature ilə işləsin, 500 verməsin
- **Schema enforcement**: type mismatch istədiyin anda production-da crash edir
- **Cross-reference**:
  - Fayl 36 — recommendation system (feature store istifadəsi)
  - Fayl 54 — stream processing (streaming features)
  - Fayl 16 — logging/monitoring (drift, freshness alerts)
  - Fayl 46 — CDC/outbox (source-dan feature pipeline trigger)


## Əlaqəli Mövzular

- [Vector Database](69-vector-database-design.md) — embedding feature saxlama
- [Recommendation System](36-recommendation-system.md) — feature-lərin istehlakçısı
- [AI Inference Serving](78-ai-inference-serving.md) — feature serving latency
- [Time-Series DB](66-time-series-database.md) — time-based feature hesablaması
- [Data Lake/Warehouse](67-data-lake-warehouse-mesh.md) — offline feature pipeline
