# Lambda / Kappa Architecture (Architect)

Böyük həcmli data processing üçün arxitektura pattern-ləri.

**Lambda Architecture** iki paralel layer istifadə edir:
- **Batch Layer** — Bütün historical data-nı yenidən hesablayır (dəqiq, yavaş)
- **Speed Layer** — Son data-nı real-time emal edir (sürətli, approximate)
- **Serving Layer** — Hər iki layer-dən nəticəni birləşdirir

**Kappa Architecture** — Lambda-nın sadələşdirilmiş versiyası:
- Yalnız **Stream Processing** (batch + speed layer əvəzinə)
- Yenidən emal lazım olduqda stream replay edir
- Nathan Marz tərəfindən təklif edilmişdir (Lambda); Jay Kreps tərəfindən Kappa

---

## Lambda Architecture (Java + Spring Batch + Kafka Streams)

```
project/
│
├── batch-layer/                               # Historical data reprocessing
│   ├── batch-job/                             # Spring Batch
│   │   ├── src/main/java/com/example/batch/
│   │   │   ├── BatchApplication.java
│   │   │   ├── job/
│   │   │   │   ├── SalesReportJob.java        # Daily/weekly batch job
│   │   │   │   ├── UserBehaviorAnalysisJob.java
│   │   │   │   └── RevenueCalculationJob.java
│   │   │   ├── step/
│   │   │   │   ├── ReadFromS3Step.java        # Read historical data from S3
│   │   │   │   ├── TransformStep.java
│   │   │   │   └── WriteToHDFSStep.java       # Write batch views to storage
│   │   │   └── config/
│   │   │       └── BatchConfig.java
│   │   └── Dockerfile
│   │
│   └── batch-views/                           # Pre-computed batch results
│       └── (stored in HBase / Cassandra / PostgreSQL materialized views)
│
├── speed-layer/                               # Real-time processing
│   ├── stream-processor/                      # Kafka Streams / Flink
│   │   ├── src/main/java/com/example/stream/
│   │   │   ├── StreamApplication.java
│   │   │   ├── topology/
│   │   │   │   ├── SalesStreamTopology.java   # Kafka Streams topology
│   │   │   │   ├── UserActivityTopology.java
│   │   │   │   └── RevenueStreamTopology.java
│   │   │   ├── processor/
│   │   │   │   ├── SalesAggregator.java       # Rolling window aggregation
│   │   │   │   └── AnomalyDetector.java
│   │   │   └── store/
│   │   │       └── RealTimeViewStore.java     # Redis: recent views
│   │   └── Dockerfile
│   │
│   └── realtime-views/
│       └── (stored in Redis / Cassandra with short TTL)
│
├── serving-layer/                             # Merges batch + speed views
│   ├── src/main/java/com/example/serving/
│   │   ├── ServingApplication.java
│   │   ├── controller/
│   │   │   ├── SalesDashboardController.java
│   │   │   └── ReportController.java
│   │   └── service/
│   │       ├── ViewMerger.java               # Combines batch + realtime views
│   │       ├── BatchViewRepository.java      # Reads from batch storage
│   │       └── RealtimeViewRepository.java   # Reads from Redis
│   └── Dockerfile
│
└── infrastructure/
    ├── kafka/                                 # Event source
    ├── hdfs/ (or S3)                         # Batch storage
    ├── redis/                                 # Speed layer storage
    └── docker-compose.yml
```

---

## Kappa Architecture (Go + Kafka Streams)

```
project/
│
├── ingestion/                                 # Event ingestion
│   ├── cmd/main.go
│   └── internal/
│       ├── producer/
│       │   ├── kafka_producer.go              # Publish events to Kafka
│       │   └── event_schema.go               # Event versioning
│       └── handler/
│           └── ingest_handler.go              # HTTP → Kafka
│
├── stream-processor/                          # Single stream processing layer
│   ├── cmd/main.go
│   └── internal/
│       ├── topology/
│       │   ├── sales_topology.go              # Sales aggregation pipeline
│       │   ├── user_activity_topology.go
│       │   └── revenue_topology.go
│       │
│       ├── processor/
│       │   ├── windowed_aggregator.go         # Tumbling / sliding windows
│       │   ├── stream_joiner.go               # Stream-stream join
│       │   └── enricher.go                    # Lookup-based enrichment
│       │
│       ├── store/
│       │   ├── state_store.go                # In-memory RocksDB state
│       │   └── changelog_producer.go         # State → Kafka topic
│       │
│       └── replay/
│           └── replay_manager.go              # Re-process from offset 0
│
├── serving/                                   # Query layer
│   ├── cmd/main.go
│   └── internal/
│       ├── handler/
│       │   ├── dashboard_handler.go
│       │   └── report_handler.go
│       └── repository/
│           ├── materialized_view_repo.go      # Read from materialized views
│           └── redis_cache.go                 # Cache for hot queries
│
└── infrastructure/
    ├── kafka/
    │   └── docker-compose.yml
    └── terraform/
        └── kafka.tf
```

---

## Laravel (Analytics Pipeline)

```
project/
├── ingestion/
│   ├── app/Http/Controllers/
│   │   └── EventIngestionController.php      # Receives events, → Kafka/SQS
│   └── app/Jobs/
│       └── PublishEventJob.php
│
├── stream-processor/                          # PHP Kafka consumer (daemon)
│   ├── app/Console/Commands/
│   │   └── ConsumeEventsCommand.php           # php artisan consume:events
│   └── app/Services/
│       ├── StreamProcessor.php
│       ├── WindowAggregator.php
│       └── MaterializedViewUpdater.php
│
├── serving/
│   ├── app/Http/Controllers/
│   │   ├── AnalyticsDashboardController.php
│   │   └── ReportsController.php
│   └── app/Services/
│       └── ViewMerger.php
│
└── infrastructure/
    ├── kafka/
    └── redis/
```

---

## Lambda vs Kappa Müqayisəsi

```
                    LAMBDA              KAPPA
────────────────────────────────────────────────────
Layers              Batch + Speed       Only Stream
Complexity          Higher              Lower
Correctness         High (batch)        Depends on stream
Reprocessing        Full batch rerun    Kafka replay (offset 0)
Latency             Batch: hours        Minutes/seconds
                    Speed: seconds
Technology          Spark + Kafka Streams  Kafka Streams / Flink
Best for            Complex analytics   Simpler pipelines
                    Historical accuracy  Lower operational cost

Lambda seç əgər:
  ✓ Historical correctness vacibdir (financial, compliance)
  ✓ Complex aggregation batch layer-dən asandır
  ✓ Real-time + historical eyni anda lazımdır

Kappa seç əgər:
  ✓ Stream processor batch-i həm edə bilər
  ✓ Operational simplicity prioritet
  ✓ Kafka-da event retention uzundur (replay mümkün)
  ✓ Yeni başlayırsınız (simpler to operate)
```
