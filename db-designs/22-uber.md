# Uber — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                      Uber Database Stack                         │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL (Schemaless)   │ Core trips, users, drivers (custom layer)│
│ PostgreSQL → MySQL   │ 2016: controversial migration (geospatial)│
│ Cassandra            │ Driver location history, analytics       │
│ Redis                │ Driver real-time location, dispatch cache│
│ Apache Kafka         │ Trip events, surge pricing, ETA updates  │
│ Elasticsearch        │ Search, logging, ELK stack               │
│ Amazon S3            │ Documents, receipts, media               │
│ MySQL + Docstore     │ Uber's custom document store on MySQL    │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Tarixi: PostgreSQL-dən MySQL-ə

```
Uber 2016: "Why Uber Engineering Switched from Postgres to MySQL"
  (Uber Engineering Blog post — böyük müzakirəyə səbəb oldu)

2012-2016: PostgreSQL
  İlkin stack: Python + PostgreSQL
  Geospatial: PostGIS extension
  
2016: MySQL-ə keçid — səbəblər:

1. Write Amplification:
   PostgreSQL MVCC: hər UPDATE → yeni row versiyası
   Index-lərin hamısı yenilənir (heap-based tables)
   Uber: driver location saniyəlik update → write amplification ~10x

2. Replication:
   PostgreSQL: WAL-based replication (logical deyil, physical)
   MySQL: binlog-based (row-level) → daha çevik
   Uber: multiple replicas, cross-datacenter → MySQL daha yaxşı

3. Upgrade Pain:
   PostgreSQL major version upgrade: in-place mümkün deyil
   pg_upgrade lazy evaluation problemi
   MySQL: daha asan in-place upgrade

4. Custom Storage Engine:
   MySQL: pluggable storage engine
   Uber: custom InnoDB fork imkanı istədi

Nəticə:
  MySQL + Schemaless (custom layer)
  PostgreSQL controversy: community reacts strongly
  "Postgres was fine, but Uber's specific use case..."
```

---

## Schemaless: Uber-in Custom DB Layer

```
Problem:
  SQL schema migration at scale = pain
  Trips cədvəli dəyişdikcə: ALTER TABLE çox yavaş
  100M+ row-lu cədvəldə ADD COLUMN saatlar çəkə bilər

Həll: Schemaless (2014, open-sourced)
  MySQL üzərində document store
  JSONB əvəzinə blob + secondary index trick

Arxitektura:
  ┌─────────────────────────────────────────┐
  │           Schemaless Layer              │
  │  (Go service, application-level)        │
  ├─────────────────────────────────────────┤
  │  Cell:     {row_key, column, ref_key}   │
  │  Value:    JSON blob (any schema)       │
  │  Index:    secondary lookup tables      │
  └──────────────────────┬──────────────────┘
                         │
                    MySQL InnoDB

MySQL tables:
  entities:    {row_key, column, ref_key, body (JSON blob)}
  cell_refs:   secondary index cells

Üstünlüklər:
  ✓ Schema migration yoxdur (JSON blob dəyişir)
  ✓ MySQL-in ACID-i qorunur
  ✓ Arbitrary queries: secondary index
  ✗ Query performance: raw SQL-dən az
  ✗ Type safety azalır
```

---

## MySQL Schema: Core Trip

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid          VARCHAR(36) UNIQUE NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    phone         VARCHAR(20) UNIQUE NOT NULL,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Payment default
    default_payment_method_id BIGINT UNSIGNED,
    
    status        ENUM('active', 'banned', 'suspended') DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_uuid  (uuid),
    INDEX idx_phone (phone)
) ENGINE=InnoDB;

-- ==================== DRIVERS ====================
CREATE TABLE drivers (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL UNIQUE,
    license_number  VARCHAR(50),
    license_expiry  DATE,
    
    -- Status
    approval_status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    is_online       BOOLEAN DEFAULT FALSE,
    current_status  ENUM('offline', 'available', 'en_route', 'on_trip') DEFAULT 'offline',
    
    -- Ratings (denormalized)
    rating          DECIMAL(3,2) DEFAULT 5.00,
    total_trips     INT DEFAULT 0,
    
    -- Current vehicle
    vehicle_id      BIGINT UNSIGNED,
    
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE vehicles (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    driver_id   BIGINT UNSIGNED NOT NULL,
    make        VARCHAR(50) NOT NULL,
    model       VARCHAR(50) NOT NULL,
    year        SMALLINT NOT NULL,
    color       VARCHAR(30) NOT NULL,
    plate       VARCHAR(20) NOT NULL,
    type        ENUM('UberX', 'UberXL', 'UberBlack', 'UberPool') NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    INDEX idx_driver (driver_id)
) ENGINE=InnoDB;

-- ==================== TRIPS ====================
CREATE TABLE trips (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid            VARCHAR(36) UNIQUE NOT NULL,
    
    rider_id        BIGINT UNSIGNED NOT NULL,
    driver_id       BIGINT UNSIGNED,
    vehicle_id      BIGINT UNSIGNED,
    
    -- Status machine
    status          ENUM(
        'requesting',    -- Rider sorğu göndərdi
        'searching',     -- Driver axtarılır
        'accepted',      -- Driver qəbul etdi
        'driver_arriving', -- Driver gəlir
        'in_progress',   -- Trip gedir
        'completed',     -- Tamamlandı
        'cancelled_rider',
        'cancelled_driver',
        'no_drivers'
    ) NOT NULL DEFAULT 'requesting',
    
    -- Locations
    pickup_lat      DECIMAL(10, 7) NOT NULL,
    pickup_lng      DECIMAL(10, 7) NOT NULL,
    pickup_address  VARCHAR(500),
    
    dropoff_lat     DECIMAL(10, 7),
    dropoff_lng     DECIMAL(10, 7),
    dropoff_address VARCHAR(500),
    
    -- Route (actual driven route)
    route_polyline  MEDIUMTEXT,   -- encoded polyline
    distance_km     DECIMAL(8,3),
    duration_sec    INT,
    
    -- Pricing
    fare_type       ENUM('standard', 'surge', 'flat') DEFAULT 'standard',
    surge_multiplier DECIMAL(4,2) DEFAULT 1.00,
    base_fare       DECIMAL(8,2),
    distance_fare   DECIMAL(8,2),
    time_fare       DECIMAL(8,2),
    surge_fare      DECIMAL(8,2) DEFAULT 0,
    total_fare      DECIMAL(8,2),
    currency        CHAR(3) DEFAULT 'USD',
    
    -- Timestamps
    requested_at    DATETIME NOT NULL,
    accepted_at     DATETIME,
    driver_arrived_at DATETIME,
    started_at      DATETIME,
    completed_at    DATETIME,
    cancelled_at    DATETIME,
    
    INDEX idx_rider      (rider_id, requested_at DESC),
    INDEX idx_driver     (driver_id, requested_at DESC),
    INDEX idx_status     (status, requested_at DESC),
    INDEX idx_uuid       (uuid)
) ENGINE=InnoDB;
```

---

## Redis: Real-Time Driver Location

```
Driver location: saniyəlik update (millions of drivers)

Redis GEO:
# Driver location update (hər 4 saniyə)
GEOADD drivers:available {lng} {lat} {driver_id}

# Nearby drivers (rider sorğusu)
GEORADIUS drivers:available {rider_lng} {rider_lat} 5 km
    COUNT 10 ASC WITHCOORD WITHDIST

# Driver status
HSET driver:state:{driver_id}
    status available
    lat 40.7128
    lng -74.0060
    heading 180
    speed 0
    updated_at 1699000000

EXPIRE driver:state:{driver_id} 30  -- 30 saniyə görünür

# Trip dispatch lock (double assignment prevention)
SET dispatch:lock:{trip_id} {driver_id} EX 30 NX

# Surge pricing cache (per geo cell)
SET surge:h3:{h3_cell_id} {multiplier} EX 60
```

---

## H3 Geospatial Indexing

```
Uber 2018: H3 (Hexagonal Hierarchical Geospatial Index)
  Açıq kaynak: github.com/uber/h3

Problem:
  Surge pricing: "Bu ərazidə neçə driver, neçə rider var?"
  Ərazini bölmək lazımdır
  
  Square grid problemi:
    Künclər mərkəzdən uzaq → qeyri-bərabər sıxlıq
    
  Hexagon üstünlükləri:
    Hər qonşu hexagon eyni məsafədə
    Multi-resolution (zoom levels: 0-15)
    Consistent area per cell

H3 Cell example:
  Resolution 9: ~0.1 km² per cell (Manhattan blok boyda)
  Resolution 8: ~0.7 km²
  Resolution 7: ~5.2 km²

Surge pricing hesabı:
  1. Son 5 dəqiqədə: hər H3 cell üçün
     - aktiv driver sayı
     - trip request sayı
  2. Ratio = requests / drivers
     ratio > 2.0 → surge 1.5x
     ratio > 3.5 → surge 2.0x
     ratio > 5.0 → surge 2.5x
     
Redis:
  ZINCRBY h3:demand:{resolution}:{timestamp} 1 {h3_cell_id}
  ZINCRBY h3:supply:{resolution}:{timestamp} 1 {h3_cell_id}
```

---

## Cassandra: Driver Location History

```cql
-- Driver hərəkət tarixi (analytics, dispute resolution)
CREATE TABLE driver_location_history (
    driver_id   UUID,
    recorded_at TIMESTAMP,
    lat         DOUBLE,
    lng         DOUBLE,
    speed_kmh   FLOAT,
    heading     SMALLINT,
    accuracy_m  FLOAT,
    trip_id     UUID,
    PRIMARY KEY (driver_id, recorded_at)
) WITH CLUSTERING ORDER BY (recorded_at DESC)
  AND default_time_to_live = 7776000;  -- 90 gün

-- Trip events log
CREATE TABLE trip_events (
    trip_id     UUID,
    event_time  TIMESTAMP,
    event_type  TEXT,
    -- 'status_change', 'location_update', 'fare_update'
    payload     TEXT,  -- JSON
    PRIMARY KEY (trip_id, event_time)
) WITH CLUSTERING ORDER BY (event_time ASC);

-- Surge pricing history (analytics)
CREATE TABLE surge_history (
    city_id     TEXT,
    hour_bucket TIMESTAMP,  -- truncated to hour
    h3_cell     TEXT,
    avg_surge   FLOAT,
    max_surge   FLOAT,
    trip_count  INT,
    PRIMARY KEY (city_id, hour_bucket, h3_cell)
);
```

---

## ETA Hesablaması

```
Uber ETA sistemi:

1. Map data: OpenStreetMap + custom traffic layer
2. Real-time traffic: GPS probe data (driver phones)
3. Historical patterns: time-of-day, day-of-week

MySQL:
  Road segments: id, from_node, to_node, distance, speed_limit
  Traffic: segment_id, timestamp, speed_kmh (Cassandra)

Algorithm:
  Dijkstra / A* on road network graph
  Edge weight = travel_time = distance / current_speed
  
ML model:
  Features: distance, time_of_day, weather, event_nearby
  Output: ETA in seconds
  
Kafka pipeline:
  Driver GPS → Kafka → Stream processing (Flink)
  → Traffic map update → ETA recalculation
  → Push to rider (WebSocket/push notification)
```

---

## Scale Faktları

```
Numbers (2023):
  131M+ monthly active users
  5.4M+ active drivers (globally)
  9.4B+ trips per year (2022)
  70+ countries, 10,000+ cities
  
  Peak: millions of concurrent trips
  
Infrastructure:
  ~5000 microservices
  1M+ messages/second (Kafka)
  
Driver location:
  4 second update interval
  Millions of location updates/minute
  Redis cluster: tens of millions of keys
  
H3:
  Used for surge, matching, analytics
  GitHub stars: 3K+, used by many companies
```

---

## Uber-dən Öyrəniləcəklər

```
1. PostgreSQL → MySQL migration:
   Use-case specific: write-heavy location updates
   Not a general recommendation
   "Right tool for right job"

2. Schemaless over rigid SQL:
   Rapid iteration zamanı
   Schema migration at scale çox ağrılıdır
   JSON blob + secondary index trade-off

3. Redis GEO for real-time location:
   Millions of driver coordinates
   Sorted set + GEORADIUS = O(N+log(M))
   Built-in geospatial support

4. H3 hexagonal indexing:
   Uniform neighbor distances
   Multi-resolution analysis
   Better than rectangular grids

5. Event sourcing for trips:
   Hər status dəyişikliyi → event
   Trip replay mümkündür (dispute resolution)
   Cassandra: natural time-series fit

6. Idempotent dispatch:
   Redis SET NX → tek driver assignment
   Race condition prevention
```
