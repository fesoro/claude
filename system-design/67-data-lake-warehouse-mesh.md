# Data Lake, Warehouse, Lakehouse & Mesh

## Nədir? (What is it?)

Müasir data arxitekturası sadəcə "data warehouse-a ETL" deyil. OLTP sistemindən gələn data BI dashboard, ML training, real-time analytics, data science notebook və ML feature store üçün istifadə olunur. Hər workload-un fərqli tələbi var — schema-on-write vs schema-on-read, SQL vs Python, columnar vs raw file, hot vs cold storage.

Dörd əsas paradiqma:

| Paradiqma | Storage | Schema | Workload | Nümunələr |
|-----------|---------|--------|----------|-----------|
| Data Warehouse | proprietary columnar | schema-on-write | BI, SQL analytics | Snowflake, BigQuery, Redshift |
| Data Lake | object storage (S3, ADLS) | schema-on-read | ML, data science, raw | Hive + S3, Glue + Athena |
| Lakehouse | object storage + table format | hybrid (ACID on lake) | BI + ML unified | Delta Lake, Iceberg, Hudi |
| Data Mesh | decentralized (any of above) | domain-owned products | organizational | Zalando, Netflix, JPMorgan |

Hər biri fərqli problemi həll edir və çox vaxt birlikdə istifadə olunur (məs. Iceberg lakehouse + Mesh organizational layer).

## Data Warehouse

Structured, schema-on-write OLAP sistem. Data əvvəlcə təmizlənir, transform olunur, sonra warehouse-a yazılır. Columnar storage (predicate pushdown, compression), MPP (massively parallel processing), SQL-first.

```
OLTP (Postgres)     SaaS (Stripe, Salesforce)
      │                      │
      └──────────┬───────────┘
                 ▼
          ┌─────────────┐
          │  ETL / ELT  │  (Fivetran, Airbyte, dbt)
          └──────┬──────┘
                 ▼
        ┌────────────────┐
        │ Data Warehouse │  (Snowflake, BigQuery)
        │  star schema   │
        │  fact + dim    │
        └───────┬────────┘
                ▼
         BI (Tableau, Looker, Metabase)
```

Üstünlüklər: ACID, yüksək performans SQL üçün, governance hazır.
Çatışmazlıq: storage bahadır (Snowflake ~$23/TB/ay, S3 ~$23/TB/ay amma lake kimi, warehouse-da compute + storage ayrı olsa belə format proprietary), ML workload üçün əlverişsiz (Python-dan çıxarmaq lazım).

## Data Lake

Raw faylları ucuz object storage-də saxlamaq. Schema-on-read — data yazılanda schema yoxlanmır, oxunanda interpret olunur. Parquet, Avro, ORC, JSON, CSV — hər format mümkündür.

```
Events, logs, clickstream, images
              │
              ▼
  ┌───────────────────┐
  │  S3 / ADLS / GCS  │   (pennies per GB)
  │  /raw/events/     │
  │    dt=2026-04-18/ │
  │      part-*.parquet
  └─────────┬─────────┘
            │
   ┌────────┴──────────┐
   ▼                   ▼
Glue Catalog        Databricks/Spark
Athena/Trino        (ML training)
```

Üstünlüklər: storage ucuz, istənilən format, ML-friendly, storage/compute decoupled. Çatışmazlıq: ACID yox (partial write corrupt), update/delete çətin (GDPR), data swamp riski.

## Lakehouse

Data Lake + ACID table format. Object storage-də faylların üstündə **metadata log** saxlayır, bu log ACID transactions, time travel, schema evolution, MERGE INTO təmin edir. BI və ML eyni data üzərində işləyə bilir.

```
            ┌─────────────────────┐
            │   S3 (Parquet)      │  data files
            └──────────┬──────────┘
                       │
            ┌──────────┴──────────┐
            │  Table Format Log   │  (Delta/Iceberg/Hudi)
            │  snapshots, schema  │
            └──────────┬──────────┘
                       │
  ┌──────────┬─────────┼──────────┬──────────┐
  ▼          ▼         ▼          ▼          ▼
Spark      Trino    Snowflake   Flink     DuckDB
(ETL)     (ad-hoc)  (external) (stream)   (local)
```

Üç əsas format:

- **Delta Lake** (Databricks) — transaction log `_delta_log/*.json`, ACID, Z-order clustering, optimize/vacuum. Spark-native, Trino/Flink connector var.
- **Apache Iceberg** (Netflix) — snapshot isolation, hidden partitioning + partition evolution (unique!), schema evolution rename/reorder. Engine-neutral — Trino, Flink, Spark, Snowflake hamısı dəstəkləyir.
- **Apache Hudi** (Uber) — upsert-heavy (CDC ingestion), Copy-on-Write + Merge-on-Read, incremental query native.

## Medallion Architecture

Data quality-ni layer-lərə ayırmaq. Databricks populyarlaşdırdı.

```
  Raw sources          Bronze            Silver            Gold
 ─────────────    ───────────────    ───────────────   ───────────────
 Kafka, API       as-is ingest       cleaned,          aggregated,
 files, CDC   ──► + metadata     ──► deduplicated, ──► business-ready
 S3 drops         (append only)      conformed,        star schema,
                  history kept       joined            metrics, KPI

 Retention:       30-90 gün          1-2 il            sonsuz
 Users:           debug, replay      data scientists   BI, analysts
 Format:          raw JSON/Avro      Parquet/Iceberg   Parquet/Iceberg
```

- **Bronze** — raw, audit üçün, heç vaxt overwrite olunmur. GDPR üçün PII olduğu kimi qalır.
- **Silver** — parse, deduplicate, null handling, type cast, enrich (geo, user lookup). ML training bundan oxuyur.
- **Gold** — aggregated fact/dim, metric layer, dashboard source.

## Table Format Features

Müasir table format (Delta/Iceberg/Hudi) aşağıdakıları verir:

- **ACID transactions** — concurrent write-lar təhlükəsiz, partial write görünmür
- **Time travel** — `SELECT * FROM t VERSION AS OF 42` və ya `TIMESTAMP AS OF '2026-04-01'`
- **Schema evolution** — kolon add/rename/drop without rewrite (Iceberg ən güclü)
- **Partition evolution** — partition strategiyasını dəyişmək (Iceberg unique feature)
- **MERGE INTO / upsert** — GDPR delete, CDC ingestion, slowly-changing dimensions
- **Compaction / OPTIMIZE** — kiçik faylları birləşdir (small file problem), Z-order cluster
- **VACUUM** — köhnə snapshot-ları təmizlə (storage reclaim)

```sql
-- Delta Lake MERGE INTO (upsert)
MERGE INTO silver.users AS t
USING bronze.user_events AS s
ON t.user_id = s.user_id
WHEN MATCHED THEN UPDATE SET t.email = s.email, t.updated_at = s.ts
WHEN NOT MATCHED THEN INSERT *;

-- Iceberg time travel
SELECT * FROM orders.snapshot_id_42
WHERE order_date = '2026-04-18';
```

## Storage Formats

- **Parquet** — columnar, snappy/zstd compression, predicate pushdown (min/max per row group). Analytics default.
- **Avro** — row-based, schema embedded, strong evolution. Kafka message-lər üçün ideal (Schema Registry ilə).
- **ORC** — Hive ekosistemi, columnar.
- **JSON/CSV** — debug/interchange, production-da yavaş.

Bronze: Avro və ya raw JSON. Silver/Gold: Parquet + Iceberg/Delta.

## ETL vs ELT

```
ETL (köhnə, on-prem warehouse)       ELT (cloud era)
 ─────────────────────────────       ─────────────────────────────
 Source                              Source
    │                                   │
    ▼                                   ▼
 Transform (Informatica, SSIS)       Load raw → Warehouse/Lake
    │                                   │
    ▼                                   ▼
 Load → Warehouse                    Transform (dbt, Spark SQL)
                                        │
                                        ▼
                                     Gold tables

 CPU: ETL server                     CPU: warehouse/Spark elastic
 Cost: fixed server                  Cost: per-query (scale down)
 Schema: rigid                       Schema: evolve in warehouse
```

ELT müasir default — compute warehouse-da elastic (Snowflake virtual warehouse), transformation SQL-də (dbt), raw saxlanılır (re-transform mümkün).

## Orkestrasiya (Orchestration) və Query Engines

ETL/ELT pipeline-ları DAG kimi:

- **Airflow** — ən populyar, Python DAG. Weakness: scheduler scalability.
- **Dagster** — asset-based, type-safe, data quality build-in.
- **Prefect** — dynamic DAG, hybrid execution.
- **dbt** — SQL-only transformation, Jinja, test, lineage, docs. Silver→Gold standart.

Query engine seçimi:

- **Spark SQL** — batch + stream + ML, Lakehouse default.
- **Trino / Presto** — federated (Postgres + S3 + Kafka join), interactive.
- **DuckDB** — embedded local, "SQLite for analytics", notebook üçün.
- **Athena / BigQuery / Redshift Spectrum** — serverless SQL S3 üzərində, pay-per-TB.
- **ClickHouse** — real-time OLAP, hot aggregation üçün.

## Data Mesh

Zhamak Dehghani (Thoughtworks, 2019). Texniki deyil, **organizational** yanaşma. Böyük şirkətlərdə tək central data team bottleneck olur — hər domain öz data-sını bilir, central team isə kontekst yoxdur.

Dörd prinsip:

1. **Domain-oriented ownership** — "orders" domain öz data product-ını saxlayır, DDD bounded context kimi.
2. **Data as a product** — domain team versiyalar, SLA, documentation, consumer support ilə təqdim edir.
3. **Self-serve platform** — central team Lakehouse + catalog + CI/CD + governance verir, idarə etmir.
4. **Federated governance** — global standartlar (PII tagging, schema registry) + domain autonomy, policy as code (OPA).

```
 ┌──────────────────────────────────────────────────────┐
 │        Self-serve Data Platform (central)            │
 │  S3 + Iceberg + Airflow + dbt + Catalog + CI/CD      │
 └──────────┬────────┬────────┬────────┬────────────────┘
            │        │        │        │
      ┌─────▼──┐ ┌──▼───┐ ┌──▼────┐ ┌─▼──────┐
      │Orders  │ │Users │ │Inven- │ │Payments│
      │domain  │ │domain│ │tory   │ │domain  │
      │team    │ │team  │ │domain │ │team    │
      └─────┬──┘ └──┬───┘ └──┬────┘ └─┬──────┘
            │       │        │        │
            ▼       ▼        ▼        ▼
       orders.  users.  inventory. payments.
       daily    active  stock       revenue
       (data product, published, SLA, versioned)
            │       │        │        │
            └───────┴────┬───┴────────┘
                         ▼
                  ML team, BI team, 
                  other domains consume
```

Mesh heç bir texnologiyanı məcbur etmir — Iceberg, Snowflake, Postgres hər şey, amma hər domain product-ını consumable şəkildə verir (catalog + schema + SLA).

## Data Catalog & Governance

Data swamp-dan qorunmaq üçün catalog vacibdir: **Amundsen** (Lyft, search + lineage), **DataHub** (LinkedIn, column-level lineage), **OpenMetadata** (open source, quality), **AWS Glue**, **Unity Catalog** (Databricks).

Governance aspektləri:
- **Access control** — table/row/column level, column masking (`REDACT(email)`)
- **PII tagging** — schema `@sensitive` tag, query-də auto mask
- **Audit log** — kim nə oxudu (GDPR compliance)
- **Data lineage** — gold metric source-ə qədər (impact analysis)
- **Quality tests** — dbt tests, Great Expectations, Monte Carlo

## Real Architectures

- **Netflix** — Iceberg pioner, S3 + Iceberg + Trino + Spark, 100+ PB.
- **Uber** — Hudi pioner, upsert-heavy (trip updates), HDFS + Hudi + Presto.
- **Databricks ekosistemi** — Delta + Unity Catalog + Photon engine.
- **Airbnb Minerva** — metric layer Gold üstündə, "daily_active_users" bir yerdə tərif.

## Laravel Integration

Laravel OLTP sistemi kimi çıxış edir, data platform-a event axır. PHP özü Spark/Flink deyil — export edir, sonra JVM/Python job emal edir.

### Event → Kafka → Lakehouse

```php
class OrderCreated
{
    public function __construct(public Order $order) {}
}

class PublishOrderToKafka
{
    public function handle(OrderCreated $event): void
    {
        $payload = [
            'event_id'     => (string) Str::uuid(),
            'event_type'   => 'order.created',
            'event_time'   => $event->order->created_at->toIso8601String(),
            'order_id'     => $event->order->id,
            'user_id'      => $event->order->user_id,
            'amount_cents' => $event->order->amount_cents,
            'currency'     => $event->order->currency,
            'items'        => $event->order->items->toArray(),
            'schema_version' => 2,
        ];

        app(KafkaProducer::class)->publish(
            topic: 'bronze.orders.v1',
            key:   (string) $event->order->id,
            value: $payload,
        );
    }
}
```

### S3 Parquet Export Job (birbaşa)

PHP-də Parquet yazmaq mümkündür (`flow-php/parquet`), adətən Kafka → Flink/Spark yolu seçilir. Daha sadə case — gündəlik batch export:

```php
class ExportOrdersToLakeCommand extends Command
{
    protected $signature = 'lake:export-orders {--date=}';

    public function handle(): void
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();
        $rows = Order::whereDate('created_at', $date)->with('items')->lazy(1000);

        $path   = "/tmp/orders-{$date}.parquet";
        $writer = new ParquetWriter($path, $this->schema());

        foreach ($rows as $order) {
            $writer->write([
                'order_id'     => $order->id,
                'user_id'      => $order->user_id,
                'amount_cents' => $order->amount_cents,
                'created_at'   => $order->created_at->valueOf(),
                'items_json'   => json_encode($order->items->toArray()),
            ]);
        }
        $writer->close();

        // Iceberg partition layout
        $s3Key = "bronze/orders/dt={$date}/part-00000.parquet";
        Storage::disk('s3-datalake')->put($s3Key, file_get_contents($path));

        // Iceberg REST catalog commit
        Http::withToken(config('iceberg.token'))
            ->post(config('iceberg.catalog_url') . '/v1/tables/bronze.orders/commit', [
                'added_files' => [[
                    'path'         => "s3://data-lake/{$s3Key}",
                    'partition'    => ['dt' => $date],
                    'record_count' => $writer->count(),
                ]],
            ]);

        unlink($path);
    }
}
```

Production-da Laravel yalnız event publish edir, Spark/Flink job Iceberg table-a yazır — PHP request-reply, JVM long-running stream üçündür.

## Cost & Storage Tiering

```
 Hot (often)         Warm (occasional)    Cold (archive)
 ─────────────       ───────────────────  ────────────────
 S3 Standard         S3 IA                S3 Glacier Deep
 $23/TB/ay           $12/TB/ay            $1-4/TB/ay
 Silver/Gold         Bronze history       Compliance/audit

 Warehouse: Snowflake/BigQuery $40-50/TB storage + compute $/credit
```

Lakehouse warehouse-dan xeyli ucuzdur — S3 + Spark compute scale-down edilə bilər. Warehouse-da "always-on virtual warehouse" dayandırılmalıdır.

## Nə Vaxt Hansını Seç? (When to Use What)

| Ssenari | Seçim |
|---------|-------|
| Yalnız BI, kiçik/orta şirkət, < 1 TB | Warehouse (Snowflake/BigQuery) |
| ML + BI, 100+ TB | Lakehouse (Iceberg/Delta) |
| Çox domain team, böyük org | Data Mesh + Lakehouse |
| Real-time + historical | Lakehouse + streaming (file 54) |
| Regulated industry | Warehouse + Unity Catalog governance |

## Interview Sualları (Interview Questions)

**1. Data lake və data warehouse arasında əsas fərqlər nələrdir?**
Warehouse structured, schema-on-write, proprietary columnar, SQL-first, storage bahalı, ACID hazır (Snowflake, BigQuery). Lake raw files (Parquet/Avro) ucuz object storage-də, schema-on-read, istənilən format, ACID yox, ML-friendly. Warehouse BI üçün, lake data science + ML üçün optimize edilib. Lakehouse ikisini birləşdirir.

**2. Lakehouse table format-ları (Delta/Iceberg/Hudi) niyə lazımdır?**
Raw S3 Parquet-də ACID yoxdur — partial write corrupt, concurrent write race condition, no updates (GDPR delete imkansız). Table format metadata log saxlayır: snapshot versioning, ACID transactions, schema evolution, time travel, MERGE INTO. Beləliklə lake üstündə warehouse-tier funksionallıq əldə olunur — BI + ML eyni data-da işləyir.

**3. Medallion architecture nədir və niyə Bronze-Silver-Gold bölünməsi vacibdir?**
Bronze raw, heç vaxt silinmir — audit, replay, regulatory. Silver cleaned, deduplicated, conformed schema — data scientist buradan oxuyur. Gold aggregated, business metric — BI dashboard source. Bu layering data quality-ni incremental edir, hər layer test olunur, failure olarsa aşağı layer-dən replay mümkündür, transformation logic-i reusable edir.

**4. ETL və ELT arasında fərq nədir, müasir sistemdə hansı üstündür?**
ETL — transform dedicated server-də (Informatica), sonra load. On-prem warehouse era, storage bahadır deyə raw saxlanılmır. ELT — raw load warehouse/lake-a, transform elastic compute-da (dbt, Spark SQL). Cloud era üstündür: storage ucuz (raw saxla), compute elastic scale, re-transform mümkün (logic dəyişəndə), SQL-based transformation version control-da (dbt + git).

**5. Iceberg vs Delta Lake — hansını seçərdin?**
Delta — Databricks-centric, mükəmməl Spark inteqrasiya, Unity Catalog ilə governance. Iceberg — engine-neutral (Trino, Flink, Snowflake, Spark hamısı first-class), hidden partitioning + partition evolution, Apache governance. Multi-engine mühitdə Iceberg, Databricks ekosisteminə bağlıdırsa Delta. Netflix, Apple, Stripe Iceberg seçib; Databricks müştəriləri Delta.

**6. Data Mesh nə vaxt məna verir, Lakehouse-u əvəz edirmi?**
Mesh organizational pattern, Lakehouse texniki platform — biri digərini əvəz etmir, birlikdə işləyir. Mesh böyük şirkətlər üçündür (Zalando 2000+ engineer, Netflix, JPMorgan) — central data team bottleneck olanda. Kiçik şirkətdə (< 50 engineer) overkill — central team daha sürətli. Mesh domain ownership + data-as-product + self-serve platform + federated governance — bunları tətbiq etmək üçün təşkilati maturity lazımdır.

**7. PHP/Laravel lakehouse ilə necə inteqrasiya olunur?**
Laravel OLTP service-dir, lakehouse-a birbaşa yazmaq antipattern olar. Düzgün yol: domain event-ləri Kafka-ya publish et (Avro schema registry ilə), Flink/Spark job Kafka-dan oxuyub Iceberg/Delta-ya yazır. Alternativ: CDC (Debezium) Postgres binlog → Kafka → lake. Kiçik miqyasda batch export (nightly) Parquet file yaz + S3 upload + Iceberg commit. PHP Parquet yaza bilər (flow-php) amma stream processing JVM/Python üçündür.

**8. Data governance lakehouse-də necə təmin olunur?**
Unity Catalog (Databricks), Lake Formation (AWS), və ya OpenMetadata + Ranger. Əsas: table/row/column-level access control, column masking PII üçün (`redact(email)` query-də avtomatik mask), audit log (kim nə oxudu), data lineage (gold metric source-ə qədər), data quality test (dbt tests, Great Expectations). Mesh-də federated — global policy (PII tag məcburi) + domain autonomy (öz schema). Policy-as-code (OPA) ilə enforce.

## Best Practices

1. **Bronze immutable** — raw data heç vaxt overwrite etmə, append-only
2. **Partition düzgün seç** — event_date istifadə et, user_id yox (skew)
3. **Small file problem qaç** — compaction/OPTIMIZE hər gün
4. **Schema registry** — Avro/Protobuf + Confluent, breaking change bloklansın
5. **Idempotent ingestion** — MERGE INTO ilə duplicate qorunması
6. **Column-level lineage** — dbt + DataHub, impact analysis üçün
7. **PII tagging source-də** — schema tag, downstream auto mask
8. **Cost monitoring** — BigQuery slot, Snowflake credit burn, per-query cost
9. **Snapshot expiration** — 7-30 gün Iceberg/Delta, storage blow-up qarşısı
10. **dbt for Silver→Gold** — SQL + test + docs + lineage tək yerdə
11. **Separate compute per workload** — BI, ML, streaming cluster-ləri ayır
12. **Data contract** — producer+consumer arası schema + SLA razılaşması
13. **Mesh yalnız lazım olanda** — < 50 engineer-də central team sürətli

## Əlaqəli Mövzular

- [Stream Processing](54-stream-processing.md)
- [CDC & Outbox Pattern](46-cdc-outbox-pattern.md)
- [Message Queues](05-message-queues.md)
- [Event-Driven Architecture](11-event-driven-architecture.md)
- [SQL vs NoSQL Selection](41-sql-vs-nosql-selection.md)
- [Data Partitioning](26-data-partitioning.md)
