# Hotel Booking App — DB Design (Middle ⭐⭐)

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                  Hotel Booking DB Stack                          │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Hotels, rooms, bookings, reviews         │
│ Redis                │ Availability cache, search cache, session│
│ Elasticsearch        │ Hotel search (geo, filters, ranking)     │
│ Kafka                │ Booking events, notification pipeline    │
│ ClickHouse           │ Analytics (revenue, occupancy rates)     │
└──────────────────────┴──────────────────────────────────────────┘

Niyə PostgreSQL (Airbnb-dən fərqli olaraq)?
  Hotel inventory daha strukturludur
  ACID kritik: double booking maliyyə cəriməsi gətirir
  PostGIS: "şəhər mərkəzinə 2km-dən yaxın" sorğuları
```

---

## Hotel vs Airbnb Fərqi

```
Airbnb:                          Hotel Booking:
  Unique listings (1-1)            Room types (1-N)
  1 ev = 1 availability            1 room type = 50 rooms
  Host flexible pricing            Rate plans (rack, corporate, promo)
  No overbooking concept           Overbooking strategy exist!
  
Hotel xüsusiyyətləri:
  Room types: "Deluxe Double", "Suite", "Standard Twin"
  1 room type-da 50 eyni otaq ola bilər
  Overbooking: 100 otaq üçün 105 rezerv (stat modeli)
  Rate plans: eyni otaq, fərqli qiymət (refundable, non-refund)
  Channel manager: Booking.com + Expedia + direct = sync
```

---

## Schema Design

```sql
-- ==================== HOTELS ====================
CREATE TABLE hotels (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name            VARCHAR(255) NOT NULL,
    brand           VARCHAR(100),        -- 'Hilton', 'Marriott', etc.
    
    -- Location
    country         CHAR(2) NOT NULL,
    city            VARCHAR(100) NOT NULL,
    address         TEXT NOT NULL,
    location        GEOGRAPHY(POINT, 4326) NOT NULL,
    
    -- Classification
    star_rating     SMALLINT CHECK (star_rating BETWEEN 1 AND 5),
    property_type   VARCHAR(50),  -- 'hotel', 'resort', 'hostel', 'apartment'
    
    -- Amenities
    amenities       JSONB DEFAULT '[]',
    -- ["pool", "spa", "gym", "restaurant", "parking", "wifi"]
    
    -- Policies
    check_in_from   TIME DEFAULT '14:00',
    check_out_until TIME DEFAULT '12:00',
    
    -- Stats
    review_score    NUMERIC(3,1),     -- 8.5 / 10
    review_count    INT DEFAULT 0,
    
    -- Status
    is_active       BOOLEAN DEFAULT TRUE,
    
    -- Contact
    phone           VARCHAR(30),
    email           VARCHAR(255),
    website         VARCHAR(255),
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_hotels_location ON hotels USING GIST (location);
CREATE INDEX idx_hotels_city ON hotels (city, star_rating, is_active);

-- ==================== ROOM TYPES ====================
CREATE TABLE room_types (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    hotel_id        BIGINT NOT NULL REFERENCES hotels(id),
    name            VARCHAR(100) NOT NULL,  -- 'Deluxe Double', 'Suite'
    description     TEXT,
    
    -- Capacity
    max_adults      SMALLINT NOT NULL DEFAULT 2,
    max_children    SMALLINT DEFAULT 0,
    total_rooms     SMALLINT NOT NULL,  -- bu tür otaqlardan neçəsi var
    
    -- Room details
    size_sqm        NUMERIC(5,1),
    bed_type        VARCHAR(50),  -- 'king', 'twin', 'double', 'bunk'
    floor           SMALLINT,
    view_type       VARCHAR(50),  -- 'sea_view', 'city_view', 'garden_view'
    
    -- Amenities
    amenities       JSONB DEFAULT '[]',
    -- ["ac", "minibar", "safe", "bathtub", "balcony"]
    
    -- Images
    photos          JSONB DEFAULT '[]',
    
    is_active       BOOLEAN DEFAULT TRUE,
    
    INDEX idx_room_hotel (hotel_id)
);

-- ==================== RATE PLANS ====================
-- Eyni otaq tipi, fərqli şərtlər = fərqli qiymət

CREATE TABLE rate_plans (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    room_type_id    BIGINT NOT NULL REFERENCES room_types(id),
    name            VARCHAR(100) NOT NULL,
    -- 'Free Cancellation', 'Non-refundable', 'Breakfast Included'
    
    -- Cancellation policy
    cancel_policy   ENUM('free', 'partial', 'non_refundable') NOT NULL,
    cancel_deadline_hours INT,  -- bron tarixinə neçə saat qalana qədər ödənişsiz ləğv
    
    -- Meal plan
    meal_plan       ENUM('room_only', 'breakfast', 'half_board', 'full_board', 'all_inclusive'),
    
    -- Discount vs base
    discount_pct    NUMERIC(4,1) DEFAULT 0,
    
    is_active       BOOLEAN DEFAULT TRUE
);

-- ==================== AVAILABILITY & PRICING ====================
-- Bu hotel booking-in ürəyidir

CREATE TABLE room_inventory (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    room_type_id    BIGINT NOT NULL REFERENCES room_types(id),
    rate_plan_id    BIGINT NOT NULL REFERENCES rate_plans(id),
    date            DATE NOT NULL,
    
    -- Inventory
    total_rooms     SMALLINT NOT NULL,   -- o gün mövcud otaq sayı
    booked_rooms    SMALLINT DEFAULT 0,  -- rezerv edilmiş
    blocked_rooms   SMALLINT DEFAULT 0,  -- hotel tərəfindən bloklanmış
    
    -- Overbooking
    overbooking_limit SMALLINT DEFAULT 0,  -- +2 = 2 əlavə rezerv icazəsi
    
    -- Available: total - booked - blocked + overbooking_limit
    
    -- Price
    price_per_night NUMERIC(10,2) NOT NULL,
    currency        CHAR(3) DEFAULT 'USD',
    
    -- Restrictions
    min_nights      SMALLINT DEFAULT 1,
    closed_to_arrival   BOOLEAN DEFAULT FALSE,  -- bu gün gəlmək olmaz
    closed_to_departure BOOLEAN DEFAULT FALSE,  -- bu gün çıxmaq olmaz
    
    UNIQUE (room_type_id, rate_plan_id, date)
);

CREATE INDEX idx_inventory_date ON room_inventory (date, room_type_id);

-- ==================== BOOKINGS ====================
CREATE TABLE bookings (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    confirmation_number VARCHAR(20) UNIQUE NOT NULL,  -- 'HTL-2024-XYZABC'
    
    -- Booking details
    hotel_id        BIGINT NOT NULL REFERENCES hotels(id),
    room_type_id    BIGINT NOT NULL REFERENCES room_types(id),
    rate_plan_id    BIGINT NOT NULL REFERENCES rate_plans(id),
    
    -- Guest
    guest_id        BIGINT REFERENCES users(id),  -- NULL = guest checkout
    guest_name      VARCHAR(200) NOT NULL,
    guest_email     VARCHAR(255) NOT NULL,
    guest_phone     VARCHAR(30),
    
    -- Stay
    check_in        DATE NOT NULL,
    check_out       DATE NOT NULL,
    nights          SMALLINT NOT NULL,
    adults          SMALLINT NOT NULL,
    children        SMALLINT DEFAULT 0,
    
    -- Pricing (snapshot)
    price_breakdown JSONB NOT NULL,
    -- {"nights": [{"date":"2024-07-01","price":120}, ...], "taxes": 20, "total": 380}
    total_price     NUMERIC(10,2) NOT NULL,
    currency        CHAR(3) NOT NULL,
    
    -- Status
    status          ENUM('pending', 'confirmed', 'checked_in',
                         'checked_out', 'cancelled', 'no_show') NOT NULL,
    
    -- Source
    channel         VARCHAR(50) DEFAULT 'direct',  -- 'booking_com', 'expedia', 'direct'
    
    -- Payment
    payment_status  ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    
    -- Cancellation
    cancellable_until TIMESTAMPTZ,
    cancelled_at    TIMESTAMPTZ,
    refund_amount   NUMERIC(10,2),
    
    special_requests TEXT,
    
    booked_at       TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_bookings_hotel ON bookings (hotel_id, check_in DESC);
CREATE INDEX idx_bookings_guest ON bookings (guest_id, booked_at DESC);
CREATE INDEX idx_bookings_dates ON bookings (check_in, check_out, hotel_id);
```

---

## Availability Check + Booking Transaction

```sql
-- Otaq müsait olub olmadığını yoxla (check_in → check_out arası hər gün)
SELECT
    ri.date,
    ri.total_rooms,
    ri.booked_rooms,
    ri.blocked_rooms,
    ri.overbooking_limit,
    (ri.total_rooms - ri.booked_rooms - ri.blocked_rooms + ri.overbooking_limit) AS available
FROM room_inventory ri
WHERE ri.room_type_id = :room_type_id
  AND ri.rate_plan_id = :rate_plan_id
  AND ri.date >= :check_in
  AND ri.date < :check_out   -- check_out günü sayılmır
  AND ri.closed_to_arrival = FALSE
ORDER BY ri.date;

-- Əgər available >= 1 bütün günlər üçün → booking mümkün

-- Booking transaction (concurrent booking prevention):
BEGIN;

-- Lock inventory rows
SELECT id, total_rooms, booked_rooms, overbooking_limit
FROM room_inventory
WHERE room_type_id = :room_type_id
  AND rate_plan_id = :rate_plan_id
  AND date BETWEEN :check_in AND :check_out - 1
FOR UPDATE;

-- Yenidən yoxla (race condition)
-- Hər gün üçün: booked_rooms < total_rooms + overbooking_limit - blocked_rooms

-- Artır
UPDATE room_inventory
SET booked_rooms = booked_rooms + 1
WHERE room_type_id = :room_type_id
  AND rate_plan_id = :rate_plan_id
  AND date >= :check_in AND date < :check_out;

-- Booking yarat
INSERT INTO bookings (...) VALUES (...);

COMMIT;
```

---

## Overbooking Strategy

```
Hotel overbooking nədir?
  Statistik model: bəzi rezervlər ləğv olunur
  100 otaqlı hotel: 103 rezerv qəbul edir
  Ortalama ləğvetmə faizi: 3-5%
  
  Risk: "walk": bütün rezervlər gəlsə → 3 nəfər başqa hoteldə yerləşdirilir
  
DB saxlama:
  overbooking_limit = 3  (3 əlavə rezerv)
  Booking check: booked_rooms <= total_rooms + overbooking_limit

Booking.com-un modeli:
  Property-level setting
  ML model: cancel probability per booking
  Dynamic overbooking limit
  
Walk compensation:
  Eyni ya daha yüksək kategoriyalı hotel
  Transport ödənişi
  Dinner voucher
  Loyalty points
```

---

## Channel Manager: Multi-OTA Sync

```
Problem:
  Booking.com-da 100 otaq var
  Expedia-da 100 otaq var
  Direct booking-da 100 otaq var
  
  Biri satılanda → hamısı güncəllənməli!
  
Həll: Channel Manager

  ┌──────────────┐     ┌──────────────────┐     ┌───────────────┐
  │ Booking.com  │────▶│  Channel Manager  │────▶│  Your Hotel   │
  │ Expedia      │────▶│  (API aggregator) │◀────│  PMS          │
  │ Airbnb       │────▶│                  │     │  (inventory)  │
  │ Direct site  │────▶│                  │     └───────────────┘
  └──────────────┘     └──────────────────┘

DB implications:
  channel VARCHAR(50) in bookings
  Inventory update idempotent (same booking ID → no double count)
  Kafka: inventory change → broadcast to all channels

OTA Webhook:
  POST /webhooks/booking-com/new-reservation
  → Parse → Insert booking → Update inventory → Confirm
```

---

## Redis Patterns

```
# Availability cache (per room type, date range)
SET avail:{room_type_id}:{check_in}:{check_out} {json} EX 300

# Hotel search results cache
SET search:{city}:{checkin}:{checkout}:{guests}:{filters_hash} {json} EX 180

# Price cache (rate plan calculations)
HSET price:{room_type_id}:{rate_plan_id}:{month} \
    2024-07-01 120.00 \
    2024-07-02 120.00 \
    2024-07-03 150.00   -- weekend premium

# Session
SET session:{token} {user_json} EX 86400

# Booking lock (prevent concurrent booking)
SET booking:lock:{room_type_id}:{check_in}:{check_out} {session_id} EX 600 NX
```

---

## Best Practices

```
✓ row-level lock (FOR UPDATE) → double booking prevention
✓ Overbooking_limit ayrı field → statistik model
✓ Price snapshot in booking → price dəyişsə belə köhnə booking dəyişmir
✓ Channel tracking → multi-OTA inventory sync
✓ Cancellation deadline → TIMESTAMPTZ (timezone aware!)
✓ closed_to_arrival / closed_to_departure flags

Anti-patterns:
✗ Availability-i yalnız "rooms_available" integer ilə saxlamaq
  → Niyə müsait deyil? (blocked? full? restriction?)
✗ Price real-time hesablama hər sorğuda
  → Cache + batch price update
✗ Check room_type only, not rate_plan
  → Same room, different rate = different inventory line
```
