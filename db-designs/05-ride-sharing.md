# Ride Sharing App — DB Design (Senior ⭐⭐⭐)

## Tövsiyə olunan DB Stack
```
Core:         PostgreSQL        (istifadəçilər, sifarişlər, ödənişlər — ACID)
Location:     Redis + PostGIS   (real-time driver location, geospatial)
Trips:        PostgreSQL        (trip history, route polyline)
Cache:        Redis             (pricing cache, surge multiplier)
Matching:     Redis + Custom    (driver-passenger matching algorithm)
Analytics:    ClickHouse        (trip analytics, driver performance)
Queue:        Kafka             (trip events, notifications)
```

---

## Niyə PostGIS + Redis Geospatial?

```
Problemin mahiyyəti:
  "1km ətrafındakı aktiv sürücüləri tap"
  Bu sorğu saniyədə minlərlə icra edilir

Redis GEO commands:
  GEOADD drivers {lng} {lat} {driver_id}
  GEORADIUS drivers {lng} {lat} 1 km     -- O(N+log M)
  GEOPOS drivers {driver_id}             -- mövqe al
  
  Üstünlük: in-memory, ~microsecond latency
  
PostGIS (PostgreSQL extension):
  ST_DWithin: WHERE ST_DWithin(location, point, distance)
  ST_Distance: iki nöqtə arası məsafə
  Trip route: ST_LineString (polyline saxlamaq)
  
  Üstünlük: complex queries, trip history, heatmaps
  
Real-time location → Redis
Historical/complex → PostGIS
```

---

## Schema Design

```sql
-- ==================== İSTİFADƏÇİLƏR ====================
CREATE TABLE users (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type            VARCHAR(20) NOT NULL,  -- 'passenger', 'driver', 'both'
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(255) UNIQUE NOT NULL,
    phone           VARCHAR(20) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    avatar_url      TEXT,
    status          VARCHAR(20) DEFAULT 'active',
    rating          NUMERIC(3,2) DEFAULT 5.00,  -- ortalama reytinq
    trip_count      INT DEFAULT 0,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Sürücü profili
CREATE TABLE drivers (
    user_id         UUID PRIMARY KEY REFERENCES users(id),
    license_number  VARCHAR(50) UNIQUE NOT NULL,
    license_expiry  DATE NOT NULL,
    vehicle_id      UUID,   -- FK aşağıda
    background_check VARCHAR(20) DEFAULT 'pending',
    -- pending, approved, rejected
    documents_verified BOOLEAN DEFAULT FALSE,
    bank_account    VARCHAR(100),  -- ödəniş üçün (şifrəli)
    current_status  VARCHAR(20) DEFAULT 'offline',
    -- offline, available, on_trip, on_break
    is_online       BOOLEAN DEFAULT FALSE,
    last_seen       TIMESTAMPTZ,
    total_earnings  NUMERIC(12,2) DEFAULT 0
);

CREATE TABLE vehicles (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    driver_id       UUID NOT NULL REFERENCES users(id),
    make            VARCHAR(50) NOT NULL,   -- Toyota
    model           VARCHAR(50) NOT NULL,   -- Camry
    year            SMALLINT NOT NULL,
    color           VARCHAR(30) NOT NULL,
    plate_number    VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type    VARCHAR(20) NOT NULL,   -- 'economy', 'comfort', 'xl', 'premium'
    capacity        SMALLINT DEFAULT 4,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== COĞRAFI DATA (PostGIS) ====================
-- Extension aktiv et: CREATE EXTENSION postgis;

-- Driver location history (trip zamanı)
CREATE TABLE driver_locations (
    driver_id    UUID NOT NULL REFERENCES users(id),
    recorded_at  TIMESTAMPTZ NOT NULL,
    location     GEOGRAPHY(POINT, 4326) NOT NULL,  -- WGS84
    speed        NUMERIC(5,2),    -- km/saat
    bearing      SMALLINT,        -- istiqamət (0-359°)
    accuracy     NUMERIC(8,4),    -- metr
    PRIMARY KEY (driver_id, recorded_at)
);

-- PostGIS spatial index
CREATE INDEX idx_driver_loc_geo ON driver_locations USING GIST (location);

-- Xidmət zonaları
CREATE TABLE service_zones (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name         VARCHAR(100) NOT NULL,
    city         VARCHAR(100) NOT NULL,
    boundary     GEOGRAPHY(POLYGON, 4326) NOT NULL,
    is_active    BOOLEAN DEFAULT TRUE,
    surge_multiplier NUMERIC(3,2) DEFAULT 1.00
);

CREATE INDEX idx_zones_boundary ON service_zones USING GIST (boundary);

-- ==================== TRIPS ====================
CREATE TABLE trips (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    passenger_id     UUID NOT NULL REFERENCES users(id),
    driver_id        UUID REFERENCES users(id),
    vehicle_id       UUID REFERENCES vehicles(id),
    vehicle_type     VARCHAR(20) NOT NULL,
    
    -- Status machine
    status           VARCHAR(30) NOT NULL DEFAULT 'searching',
    -- searching, driver_assigned, driver_arriving, in_progress,
    -- completed, cancelled_by_passenger, cancelled_by_driver
    
    -- Lokasiyalar
    pickup_address   TEXT NOT NULL,
    pickup_location  GEOGRAPHY(POINT, 4326) NOT NULL,
    dropoff_address  TEXT NOT NULL,
    dropoff_location GEOGRAPHY(POINT, 4326) NOT NULL,
    
    -- Marşrut (tam yol)
    route_polyline   TEXT,    -- encoded polyline (Google Maps format)
    
    -- Zaman
    requested_at     TIMESTAMPTZ DEFAULT NOW(),
    driver_assigned_at TIMESTAMPTZ,
    driver_arrived_at  TIMESTAMPTZ,
    trip_started_at    TIMESTAMPTZ,
    trip_ended_at      TIMESTAMPTZ,
    cancelled_at       TIMESTAMPTZ,
    
    -- Məsafə / müddət
    estimated_duration INT,     -- dəqiqə
    estimated_distance NUMERIC(8,3),  -- km
    actual_duration    INT,
    actual_distance    NUMERIC(8,3),
    
    -- Qiymət
    base_fare        NUMERIC(8,2),
    surge_multiplier NUMERIC(3,2) DEFAULT 1.00,
    fare_amount      NUMERIC(8,2),
    discount_amount  NUMERIC(8,2) DEFAULT 0,
    tip_amount       NUMERIC(8,2) DEFAULT 0,
    total_amount     NUMERIC(8,2),
    currency         CHAR(3) DEFAULT 'AZN',
    
    -- Ləğvetmə
    cancellation_reason TEXT,
    cancellation_fee    NUMERIC(8,2) DEFAULT 0,
    
    created_at       TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_trips_passenger ON trips(passenger_id, requested_at DESC);
CREATE INDEX idx_trips_driver    ON trips(driver_id, requested_at DESC);
CREATE INDEX idx_trips_status    ON trips(status, requested_at DESC)
    WHERE status IN ('searching', 'driver_assigned', 'driver_arriving', 'in_progress');
CREATE INDEX idx_trips_pickup    ON trips USING GIST (pickup_location);

-- ==================== QİYMƏTLƏNDİRMƏ ====================
CREATE TABLE trip_ratings (
    trip_id          UUID PRIMARY KEY REFERENCES trips(id),
    passenger_rating NUMERIC(3,2),    -- sürücü üçün
    driver_rating    NUMERIC(3,2),    -- sərnişin üçün
    passenger_comment TEXT,
    driver_comment   TEXT,
    created_at       TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== ÖDƏNİŞLƏR ====================
CREATE TABLE payments (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    trip_id          UUID NOT NULL REFERENCES trips(id),
    passenger_id     UUID NOT NULL REFERENCES users(id),
    driver_id        UUID NOT NULL REFERENCES users(id),
    
    amount           NUMERIC(8,2) NOT NULL,
    platform_fee     NUMERIC(8,2) NOT NULL,  -- Uber komisyonu (25%)
    driver_earning   NUMERIC(8,2) NOT NULL,  -- 75%
    
    payment_method   VARCHAR(30) NOT NULL,   -- 'card', 'cash', 'wallet'
    provider_ref     VARCHAR(255),
    status           VARCHAR(20) DEFAULT 'pending',
    processed_at     TIMESTAMPTZ,
    created_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Sürücü earnings (gündəlik/həftəlik)
CREATE TABLE driver_earnings_summary (
    driver_id    UUID NOT NULL REFERENCES users(id),
    date         DATE NOT NULL,
    trip_count   INT DEFAULT 0,
    total_earned NUMERIC(10,2) DEFAULT 0,
    total_hours  NUMERIC(5,2) DEFAULT 0,
    PRIMARY KEY (driver_id, date)
);
```

---

## Redis: Real-time Driver Tracking

```
# Aktiv sürücülərin real-time mövqeyi
# Hər 5 saniyə sürücü location göndərir

GEOADD drivers:economy {longitude} {latitude} {driver_id}
GEOADD drivers:comfort {longitude} {latitude} {driver_id}

# 1km ətrafındakı economy sürücüləri
GEORADIUS drivers:economy 49.8671 40.4093 1 km WITHCOORD WITHDIST COUNT 10 ASC

# Sürücünü sil (offline olduqda)
ZREM drivers:economy {driver_id}

# Surge pricing multiplier (zone-based)
GET surge:zone:{zone_id}         -- "1.8"
SET surge:zone:{zone_id} 1.8 EX 300  -- 5 dəqiqə cache

# Trip matching lock (race condition önlənir)
SET matching:trip:{trip_id} {driver_id} NX EX 30

# Driver current status
HSET driver:status:{driver_id} status "available" lat 40.4093 lng 49.8671 updated_at {ts}
```

---

## Qiymət Hesablama (Surge Pricing)

```sql
-- Surge multiplier hesablama
-- Active trip / available driver ratio əsasında

WITH stats AS (
    SELECT
        ST_DWithin(
            ST_MakePoint(49.8671, 40.4093)::geography,
            z.boundary, 0
        ) AS in_zone,
        COUNT(DISTINCT t.id) FILTER (WHERE t.status IN ('searching', 'in_progress')) AS active_trips,
        COUNT(DISTINCT d.user_id) FILTER (WHERE d.current_status = 'available') AS available_drivers
    FROM service_zones z
    LEFT JOIN trips t ON ST_DWithin(t.pickup_location, z.boundary, 0)
    LEFT JOIN drivers d ON d.is_online = TRUE
    WHERE z.is_active = TRUE
)
SELECT
    CASE
        WHEN available_drivers = 0           THEN 3.0
        WHEN active_trips::float / available_drivers > 2.5 THEN 2.0
        WHEN active_trips::float / available_drivers > 1.5 THEN 1.5
        ELSE 1.0
    END AS surge_multiplier
FROM stats;
```

---

## Kritik Dizayn Qərarları

```
1. Redis GEO + PostgreSQL PostGIS:
   Redis: real-time matching (sub-millisecond)
   PostGIS: trip route, historical analysis, heatmaps
   İki DB çünki real-time vs analytical querylər fərqlidir

2. Trip status machine:
   searching → driver_assigned → driver_arriving →
   in_progress → completed
   Hər keçid timestamp qeyd edilir → analytics üçün
   "Driver arrival time" = driver_arrived_at - driver_assigned_at

3. Matching lock (Redis NX):
   İki server eyni sürücüyə eyni trip vermə riski
   SETNX: yalnız bir server lock ala bilər
   EX 30: sürücü cavab verməsə lock azad olur

4. Driver earning ayrı cədvəl:
   Hər gün aggregate edilir (batch job)
   "Bu həftə nə qazandım?" sorğusu sürətli
   payments cədvəlindən hesablamaq bahalıdır

5. GEOGRAPHY tip (WGS84):
   Məsafə hesablamaları dəqiq (küre üzərindəki məsafə)
   GEOMETRY deyil GEOGRAPHY (metrəs, km dəqiq)
```

---

## Best Practices

```
✓ Redis GEO real-time driver tracking üçün
✓ GEOGRAPHY (PostGIS) geospatial hesablamalar üçün
✓ Trip status-un hər keçid zamanı timestamp saxla
✓ Matching lock Redis NX ilə (double assignment önlənir)
✓ Surge multiplier cache-lə (hər sorğuda hesablamaq bahalı)
✓ Driver earnings günlük aggregate (reports sürətli)
✓ Route polyline encoded format (storage effektiv)

Anti-patterns:
✗ Driver location PostgreSQL-də real-time saxlamaq
✗ Surge-ı hər trip sorğusunda hesablamaq
✗ Matching-i lock olmadan etmək (race condition)
```

---

## Tanınmış Sistem: Uber

```
Uber DB Evolution:
  2010:   PostgreSQL (monolith)
  2014:   MySQL (scale problems ilə keçiş)
  2016:   Schemaless (Cassandra üzərində custom layer)
  2017+:  Docstore (MySQL üzərində custom layer)
  
Niyə PostgreSQL-dən MySQL-ə keçdilər?
  PostgreSQL replication: master fails → data loss (o dövrdə)
  MySQL semi-synchronous replication daha sağlam idi
  
Uber-in öz geospatial engine:
  H3 (hexagonal grid system — open-sourced)
  Hexagon-based indexing → efficient range queries
  
Bolt:
  PostgreSQL + PostGIS  → trips, geospatial
  Redis                 → real-time matching
  Kubernetes            → container orchestration
```

---

## Surge Pricing Algorithm

```
Surge pricing = Tələb/Təklif nisbətinə görə qiymət artımı

Data collection (real-time, H3 hexagon cells):
  Hər 30 saniyə:
  - Active requests per H3 cell
  - Available drivers per H3 cell
  
Redis:
  ZINCRBY surge:demand:{h3_cell}:{window} 1 {timestamp}
  ZINCRBY surge:supply:{h3_cell}:{window} 1 {timestamp}

Surge multiplier hesabı:
  ratio = demand_count / max(supply_count, 1)
  
  multiplier:
    ratio <= 1.0: 1.0x (normal)
    ratio 1.0-2.0: 1.2x
    ratio 2.0-3.0: 1.5x
    ratio 3.0-4.0: 2.0x
    ratio > 4.0:   2.5x (cap)

PostgreSQL - surge_history:
CREATE TABLE surge_events (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    h3_cell     VARCHAR(20) NOT NULL,
    multiplier  NUMERIC(4,2) NOT NULL,
    demand      INT,
    supply      INT,
    started_at  TIMESTAMPTZ NOT NULL,
    ended_at    TIMESTAMPTZ,
    INDEX idx_cell_time (h3_cell, started_at DESC)
);

User notification:
  Surge active → push notification
  "Prices are 2x right now due to high demand"
  Opt-in: user can wait for surge to end

Legal consideration:
  Some cities regulate surge (NYC, etc.)
  Max multiplier configurable per market
```

---

## Multi-Stop Trips

```
User A: "Pick me up → Drop at Mall → Then Airport"

DB design:

CREATE TABLE trip_stops (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    trip_id     BIGINT NOT NULL REFERENCES trips(id),
    stop_order  SMALLINT NOT NULL,  -- 1: pickup, 2: stop, N: dropoff
    
    address     TEXT NOT NULL,
    lat         DECIMAL(9,6) NOT NULL,
    lng         DECIMAL(9,6) NOT NULL,
    
    status      ENUM('pending', 'arrived', 'departed') DEFAULT 'pending',
    arrived_at  TIMESTAMPTZ,
    departed_at TIMESTAMPTZ,
    
    INDEX idx_trip (trip_id, stop_order)
);

Pricing for multi-stop:
  Total fare = SUM of fare for each segment
  + stop fee per intermediate stop ($2/stop)
  + wait time fee if driver waits > 2 min

ETA calculation:
  Stops: A → B → C
  ETA_total = ETA(A→B) + wait_B + ETA(B→C)
  Driver sees each stop sequentially
  
Cancellation:
  Cancel after 1st stop reached → fee applies for completed distance
```
