# Airbnb — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     Airbnb Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL (Amazon RDS)   │ Core: users, listings, bookings, payments│
│ Amazon RDS Aurora    │ Newer services (MySQL-compat)            │
│ Amazon DynamoDB      │ Messaging, notifications, feature flags  │
│ Amazon S3            │ Photos, documents, media                 │
│ Elasticsearch        │ Listing search (geo, filters, ranking)   │
│ Redis                │ Sessions, cache, rate limiting           │
│ Apache Kafka         │ Event streaming, data pipeline           │
│ Apache Hive + Presto │ Analytics (data warehouse)               │
│ Apache Airflow       │ Workflow orchestration (created by Airbnb)│
│ Druid                │ Real-time analytics                      │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Airbnb-in DB Tarixi

```
2008-2011: Ruby on Rails monolith + MySQL
  Single MySQL server
  Amazon EC2 üzərində
  "We literally stored everything in one database"
  
2012-2014: Sharding
  MySQL read replicas
  Application-level sharding başladı
  S3-ə foto köçürüldü

2014-2016: SOA (Service Oriented Architecture)
  Monolith parçalandı
  MySQL → per-service databases
  Redis caching layer əlavə edildi
  Elasticsearch listing search üçün

2016-2019: Scale
  DynamoDB: messaging, notifications
  Kafka: event streaming
  Presto/Hive: analytics data lake
  
  Airbnb-in "DataPortal" (internal data discovery tool)
  Apache Airflow: yaradıldı Airbnb tərəfindən (2015)!

2020-sonra: Microservice maturity
  Hundreds of services
  Each team owns their data
  Data mesh approach
```

---

## MySQL Schema: Core Booking

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid          VARCHAR(36) UNIQUE NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    phone         VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    
    profile_pic_url TEXT,
    bio           TEXT,
    languages     JSON,  -- ['en', 'az', 'ru']
    
    -- Verification
    is_email_verified  BOOLEAN DEFAULT FALSE,
    is_phone_verified  BOOLEAN DEFAULT FALSE,
    is_id_verified     BOOLEAN DEFAULT FALSE,  -- government ID
    
    -- Host/Guest stats (denormalized)
    host_rating    DECIMAL(3,2),
    guest_rating   DECIMAL(3,2),
    
    currency_pref  CHAR(3) DEFAULT 'USD',
    language_pref  VARCHAR(5) DEFAULT 'en',
    timezone       VARCHAR(50),
    
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ==================== LISTINGS ====================
CREATE TABLE listings (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid            VARCHAR(36) UNIQUE NOT NULL,
    host_id         BIGINT UNSIGNED NOT NULL,
    
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    
    -- Type
    property_type   VARCHAR(50) NOT NULL,  -- 'apartment', 'house', 'villa', 'cabin'
    room_type       ENUM('entire_place', 'private_room', 'shared_room') NOT NULL,
    
    -- Location
    country         CHAR(2) NOT NULL,
    city            VARCHAR(100) NOT NULL,
    state           VARCHAR(100),
    address         TEXT,         -- host-only visible
    neighborhood    VARCHAR(100), -- approximate for guests
    lat             DECIMAL(9,6) NOT NULL,
    lng             DECIMAL(9,6) NOT NULL,
    
    -- Capacity
    max_guests      TINYINT UNSIGNED NOT NULL,
    bedrooms        TINYINT UNSIGNED DEFAULT 0,
    beds            TINYINT UNSIGNED DEFAULT 0,
    bathrooms       DECIMAL(3,1) DEFAULT 1.0,  -- 1.5 = 1 full + 1 half
    
    -- Pricing (base)
    price_per_night DECIMAL(8,2) NOT NULL,
    currency        CHAR(3) DEFAULT 'USD',
    cleaning_fee    DECIMAL(8,2) DEFAULT 0,
    security_deposit DECIMAL(8,2) DEFAULT 0,
    
    -- Rules
    min_nights      SMALLINT DEFAULT 1,
    max_nights      SMALLINT DEFAULT 365,
    check_in_time   TIME DEFAULT '15:00:00',
    check_out_time  TIME DEFAULT '11:00:00',
    
    -- Amenities (bitmask or JSONB)
    amenities       JSON,
    -- ["wifi", "kitchen", "ac", "pool", "parking", "washer"]
    
    house_rules     TEXT,
    
    -- Status
    status          ENUM('draft', 'active', 'inactive', 'suspended') DEFAULT 'draft',
    is_instant_book BOOLEAN DEFAULT FALSE,
    
    -- Metrics (denormalized)
    rating          DECIMAL(3,2),
    review_count    INT DEFAULT 0,
    
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_host     (host_id),
    INDEX idx_location (country, city, status),
    INDEX idx_geo      (lat, lng)
) ENGINE=InnoDB;

-- ==================== AVAILABILITY & PRICING ====================
-- Bu Airbnb-in ən kritik cədvəlindən biridir!
-- Hər gün üçün availability + qiymət

CREATE TABLE listing_availability (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    listing_id  BIGINT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    
    status      ENUM('available', 'blocked', 'booked') NOT NULL DEFAULT 'available',
    price       DECIMAL(8,2),  -- NULL = listing default price
    min_nights  SMALLINT,      -- override for specific dates
    
    UNIQUE INDEX idx_listing_date (listing_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- Host blocking calendar (alternative approach: blocked ranges)
CREATE TABLE listing_blocked_dates (
    listing_id  BIGINT UNSIGNED NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    reason      ENUM('owner_use', 'maintenance', 'other') DEFAULT 'other',
    PRIMARY KEY (listing_id, start_date)
) ENGINE=InnoDB;

-- Custom pricing rules (weekends, holidays, seasons)
CREATE TABLE pricing_rules (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    listing_id  BIGINT UNSIGNED NOT NULL,
    rule_type   ENUM('day_of_week', 'date_range', 'last_minute', 'early_bird'),
    
    -- Day of week: 0=Sun, 1=Mon, ..., 6=Sat
    days_of_week  JSON,  -- [5, 6] for Fri-Sat
    
    -- Date range
    start_date  DATE,
    end_date    DATE,
    
    -- Adjustment
    adjustment_type  ENUM('percent', 'fixed') NOT NULL,
    adjustment_value DECIMAL(8,2) NOT NULL,  -- +20 = +20%, -10 = -10%
    
    INDEX idx_listing (listing_id)
) ENGINE=InnoDB;

-- ==================== BOOKINGS ====================
CREATE TABLE bookings (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid            VARCHAR(36) UNIQUE NOT NULL,
    
    listing_id      BIGINT UNSIGNED NOT NULL,
    guest_id        BIGINT UNSIGNED NOT NULL,
    host_id         BIGINT UNSIGNED NOT NULL,
    
    -- Dates
    check_in        DATE NOT NULL,
    check_out       DATE NOT NULL,
    -- Duration = check_out - check_in (nights)
    
    -- Guests
    guest_count     TINYINT UNSIGNED NOT NULL,
    
    -- Status machine
    status          ENUM(
        'inquiry',       -- Guest messaged
        'pending',       -- Request sent (instant book or not)
        'accepted',      -- Host accepted
        'confirmed',     -- Payment processed
        'cancelled_guest',
        'cancelled_host',
        'completed',
        'disputed'
    ) NOT NULL DEFAULT 'pending',
    
    -- Pricing (snapshot at booking time)
    nightly_rate    DECIMAL(8,2) NOT NULL,
    nights          SMALLINT UNSIGNED NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,  -- nightly_rate * nights
    cleaning_fee    DECIMAL(8,2) DEFAULT 0,
    service_fee     DECIMAL(8,2) NOT NULL,   -- Airbnb fee (~14-16%)
    taxes           DECIMAL(8,2) DEFAULT 0,
    total_price     DECIMAL(10,2) NOT NULL,
    currency        CHAR(3) NOT NULL,
    
    -- Payout to host (after Airbnb commission ~3%)
    host_payout     DECIMAL(10,2),
    
    special_requests TEXT,
    
    requested_at    DATETIME NOT NULL,
    confirmed_at    DATETIME,
    cancelled_at    DATETIME,
    
    INDEX idx_listing (listing_id, check_in DESC),
    INDEX idx_guest   (guest_id, requested_at DESC),
    INDEX idx_host    (host_id, requested_at DESC),
    INDEX idx_dates   (check_in, check_out)
) ENGINE=InnoDB;

-- ==================== REVIEWS ====================
-- Airbnb: double-blind review (hər iki tərəf eyni anda görür)
CREATE TABLE reviews (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    booking_id    BIGINT UNSIGNED NOT NULL UNIQUE,
    reviewer_id   BIGINT UNSIGNED NOT NULL,
    reviewee_id   BIGINT UNSIGNED NOT NULL,
    listing_id    BIGINT UNSIGNED,
    
    review_type   ENUM('guest_to_host', 'host_to_guest') NOT NULL,
    
    -- Ratings
    overall_rating    TINYINT NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
    -- Guest reviews listing:
    cleanliness_rating   TINYINT,
    communication_rating TINYINT,
    checkin_rating       TINYINT,
    accuracy_rating      TINYINT,
    location_rating      TINYINT,
    value_rating         TINYINT,
    
    comment       TEXT,
    is_public     BOOLEAN DEFAULT FALSE,  -- double-blind: 14 days reveal
    revealed_at   DATETIME,
    
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing  (listing_id, created_at DESC),
    INDEX idx_reviewee (reviewee_id)
) ENGINE=InnoDB;
```

---

## Availability Check: Double Booking Prevention

```sql
-- Kritik: eyni tarix iki ayrı booking almasın!

-- Option 1: Pessimistic locking
SELECT id FROM listing_availability
WHERE listing_id = ?
  AND date BETWEEN ? AND ?
  AND status = 'available'
FOR UPDATE;

-- Hamısı available-dırsa → UPDATE et
UPDATE listing_availability
SET status = 'booked'
WHERE listing_id = ?
  AND date BETWEEN ? AND ?;

-- Option 2: Optimistic (version check)
-- listing_availability.version artırılır
-- Conflict → retry

-- Option 3: Unique constraint + INSERT
-- Hər tarixin yalnız bir "booked" record-u ola bilər
-- UNIQUE(listing_id, date, status='booked') → mümkün deyil birbaşa

-- Airbnb-in real approach:
-- listing_availability cədvəlindəki UNIQUE(listing_id, date)
-- Status update: available → booked
-- Concurrent update → bir transaction uğur qazanır, digəri fail
-- Fail olan → retry ya da "unavailable" xətası qaytarır
```

---

## Elasticsearch: Listing Search

```json
{
  "index": "listings",
  "mappings": {
    "properties": {
      "listing_id":     {"type": "long"},
      "title":          {"type": "text"},
      "description":    {"type": "text"},
      "property_type":  {"type": "keyword"},
      "room_type":      {"type": "keyword"},
      "location": {
        "type": "geo_point"
      },
      "city":           {"type": "keyword"},
      "country":        {"type": "keyword"},
      "price_per_night":{"type": "float"},
      "bedrooms":       {"type": "byte"},
      "max_guests":     {"type": "byte"},
      "amenities":      {"type": "keyword"},
      "rating":         {"type": "half_float"},
      "review_count":   {"type": "integer"},
      "is_instant_book":{"type": "boolean"},
      "is_available":   {"type": "boolean"},
      "available_dates":{"type": "date_range"}
    }
  }
}
```

```json
// Search query example:
// "Barcelona, 2 people, July 10-15, wifi + pool"
{
  "query": {
    "bool": {
      "filter": [
        {"term": {"city": "Barcelona"}},
        {"range": {"max_guests": {"gte": 2}}},
        {"term": {"amenities": "wifi"}},
        {"term": {"amenities": "pool"}},
        {"term": {"is_available": true}}
      ]
    }
  },
  "sort": [
    {"_score": "desc"},
    {"rating": "desc"},
    {"review_count": "desc"}
  ]
}
```

---

## DynamoDB: Messaging

```
Airbnb Messaging (host-guest communication):

DynamoDB seçiminin səbəbi:
  ✓ Simple access patterns (conversation → messages)
  ✓ High write throughput
  ✓ Automatic scaling
  ✓ TTL for old messages

Table: Messages
  PK: conversation_id
  SK: message_id (timestamp-based)
  Attributes: sender_id, content, type, is_read

Table: UserConversations (GSI)
  PK: user_id
  SK: last_message_at
  Attributes: conversation_id, other_user_id, unread_count
```

---

## Apache Airflow: Airbnb-in Töhfəsi

```
Airbnb 2015: Apache Airflow yaratdı

Problem:
  Data pipeline-lar mürəkkəb
  Cron jobs kifayət deyil (dependency management yoxdur)
  
Airflow:
  DAG (Directed Acyclic Graph) based workflow
  Python-da pipeline definition
  Retry, monitoring, alerting built-in
  
Airbnb istifadəsi:
  ✓ Data warehouse loading (MySQL → Hive)
  ✓ ML model training pipelines
  ✓ Report generation
  ✓ Email/notification batches
  
2016: Apache incubator-a verildi → Apache top-level project

Bugün:
  Airflow: en populyar workflow orchestrator
  Astronomer (Airflow-as-a-service) şirkəti yarandı
  "Airbnb's biggest contribution to open source"
```

---

## Scale Faktları

```
Numbers (2023):
  150M+ users
  7M+ active listings (191 countries)
  393M+ nights booked (2022)
  $9.4B revenue (2022)
  
  Listings: MySQL sharded (listing_id based)
  Search: Elasticsearch billions of documents
  Photos: Billions of images on S3
  
Infrastructure:
  Multi-AZ on AWS
  MySQL RDS → Aurora migration ongoing
  Kafka: hundreds of millions of events/day
  
Data team:
  Presto: ad-hoc analytics SQL
  Hive: batch processing
  Druid: real-time dashboards
  Airflow: pipeline orchestration
```

---

## Kritik Dizayn Qərarları

```
1. Availability Calendar:
   listing_availability: hər gün bir row
   Pros: simple queries, clear state per day
   Cons: 1 listing × 365 days = 365 rows (scale: 7M × 365 = 2.5B rows)
   
   Alternativ: blocked_ranges (start/end date)
   Pros: fewer rows
   Cons: complex availability check query

2. Price Snapshot in Booking:
   nightly_rate, cleaning_fee etc. kopyalanır
   Qiymət sonra dəyişsə də köhnə booking etkilənmir
   
3. Double-blind Reviews:
   Hər iki tərəf review yazır
   14 gün → hər ikisi eyni anda görünür
   Bias prevention: "A pozitiv review yazsa, B də pozitiv yazacaq"
   
4. Service Fee Structure:
   Guest service fee: ~14-16%
   Host service fee: ~3%
   Total Airbnb revenue: ~17-19% per booking
   
5. Instant Book:
   is_instant_book = true → host approval lazım deyil
   Conversion rate artır
   Trust threshold: verified guest only
```

---

## Airbnb-dən Öyrəniləcəklər

```
1. Calendar design is hard:
   Availability + pricing + rules = 3 ayrı cədvəl
   Conflict prevention: database-level constraints

2. Double-blind review:
   Bias azaltmaq üçün UX/DB co-design
   is_public flag + revealed_at timestamp

3. Price snapshot:
   Order/booking zamanı qiymətlər kopyalanmalı
   "What if host changes price after booking?"

4. Open source contributions:
   Airflow: workflow orchestration
   Airbnb-in biggest impact
   "Solve your own problem, share the solution"

5. AWS all-in:
   Early bet on cloud (2008-2012)
   "We couldn't afford own data centers as startup"
   AWS → competitive advantage

6. Geo search:
   Elasticsearch geo_point
   Bounding box + radius search
   "listings within X km of location"
```
