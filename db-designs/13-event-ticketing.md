# Event Ticketing — DB Design (Senior ⭐⭐⭐)

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                 Event Ticketing DB Stack                         │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Events, venues, tickets, orders          │
│ Redis                │ Seat locks (TTL), queue, cache           │
│ Kafka                │ Ticket release events, notification      │
│ Elasticsearch        │ Event search (artist, venue, date, geo)  │
└──────────────────────┴──────────────────────────────────────────┘

Əsas problem:
  Taylor Swift konsert → 1M+ insan eyni anda bilet almağa çalışır
  Double booking: eyni oturacaq 2 nəfərə satılmamalıdır
  Fairness: waiting room + virtual queue
```

---

## Schema Design

```sql
-- ==================== VENUES ====================
CREATE TABLE venues (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name        VARCHAR(255) NOT NULL,
    address     TEXT NOT NULL,
    city        VARCHAR(100) NOT NULL,
    country     CHAR(2) NOT NULL,
    location    GEOGRAPHY(POINT, 4326),
    capacity    INT NOT NULL,
    timezone    VARCHAR(50) NOT NULL
);

-- Venue layout: sections/zones
CREATE TABLE venue_sections (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    venue_id    BIGINT NOT NULL REFERENCES venues(id),
    name        VARCHAR(100) NOT NULL,  -- 'Floor A', 'Section 101', 'VIP'
    section_type ENUM('seated', 'standing', 'vip') NOT NULL,
    row_count   SMALLINT,
    seats_per_row SMALLINT,
    total_capacity INT NOT NULL
);

-- Individual seats (for assigned seating)
CREATE TABLE seats (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    section_id  BIGINT NOT NULL REFERENCES venue_sections(id),
    row_label   VARCHAR(5),   -- 'A', 'B', ..., 'Z', 'AA'
    seat_number SMALLINT,
    seat_type   ENUM('standard', 'wheelchair', 'restricted_view', 'premium'),
    is_active   BOOLEAN DEFAULT TRUE
);

-- ==================== EVENTS ====================
CREATE TABLE events (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) UNIQUE NOT NULL,
    
    venue_id    BIGINT NOT NULL REFERENCES venues(id),
    
    -- Artists/Performers
    performers  JSONB DEFAULT '[]',   -- [{"name": "Taylor Swift", "headliner": true}]
    genre       TEXT[],               -- ['pop', 'country']
    
    -- Schedule
    event_date  TIMESTAMPTZ NOT NULL,
    doors_open  TIMESTAMPTZ,
    end_time    TIMESTAMPTZ,
    
    -- Sale window
    sale_start  TIMESTAMPTZ NOT NULL,
    sale_end    TIMESTAMPTZ,
    presale_start TIMESTAMPTZ,      -- Verified fan presale
    
    -- Status
    status      ENUM('draft', 'onsale', 'presale', 'sold_out',
                     'cancelled', 'postponed') DEFAULT 'draft',
    
    -- Limits
    max_tickets_per_order SMALLINT DEFAULT 4,
    
    -- Media
    image_url   TEXT,
    
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== TICKET TYPES ====================
-- Hər event üçün fərqli ticket kateqoriyaları

CREATE TABLE ticket_types (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    event_id    BIGINT NOT NULL REFERENCES events(id),
    section_id  BIGINT REFERENCES venue_sections(id),
    
    name        VARCHAR(100) NOT NULL,  -- 'General Admission', 'VIP', 'Floor'
    price       NUMERIC(10,2) NOT NULL,
    face_value  NUMERIC(10,2) NOT NULL,  -- original price (anti-scalping ref)
    
    total_quantity   INT NOT NULL,
    available_quantity INT NOT NULL,    -- decrements as sold
    
    -- Sale window
    sale_start  TIMESTAMPTZ,
    sale_end    TIMESTAMPTZ,
    
    -- Resale limits (anti-scalping)
    is_resaleable       BOOLEAN DEFAULT TRUE,
    max_resale_price    NUMERIC(10,2),  -- max resale price cap
    
    INDEX idx_event (event_id)
);

-- ==================== TICKETS ====================
-- Individual ticket instances

CREATE TABLE tickets (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    ticket_type_id  BIGINT NOT NULL REFERENCES ticket_types(id),
    event_id        BIGINT NOT NULL REFERENCES events(id),
    
    -- For assigned seating
    seat_id         BIGINT REFERENCES seats(id),
    
    -- Ownership
    order_id        BIGINT REFERENCES orders(id),
    current_owner_id BIGINT REFERENCES users(id),
    
    -- Status
    status          ENUM('available', 'locked', 'sold', 'used',
                         'cancelled', 'transferred') NOT NULL DEFAULT 'available',
    
    -- Lock (during checkout)
    locked_by       BIGINT REFERENCES users(id),
    locked_until    TIMESTAMPTZ,
    
    -- Barcode/QR
    barcode         VARCHAR(100) UNIQUE,
    
    -- Transfer history
    original_buyer_id BIGINT REFERENCES users(id),
    
    INDEX idx_event  (event_id, status),
    INDEX idx_order  (order_id),
    INDEX idx_seat   (seat_id)
);

-- ==================== ORDERS ====================
CREATE TABLE orders (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id         BIGINT NOT NULL REFERENCES users(id),
    event_id        BIGINT NOT NULL REFERENCES events(id),
    
    status          ENUM('pending', 'payment_processing',
                         'confirmed', 'cancelled', 'refunded') NOT NULL,
    
    -- Pricing
    subtotal        NUMERIC(10,2) NOT NULL,
    service_fee     NUMERIC(10,2) DEFAULT 0,
    facility_fee    NUMERIC(10,2) DEFAULT 0,
    total           NUMERIC(10,2) NOT NULL,
    
    -- Payment
    payment_method  VARCHAR(50),
    payment_ref     VARCHAR(100),
    
    -- Anti-scalping tracking
    purchase_ip     INET,
    device_fingerprint VARCHAR(100),
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    confirmed_at    TIMESTAMPTZ,
    
    INDEX idx_user  (user_id, created_at DESC),
    INDEX idx_event (event_id, created_at DESC)
);

CREATE TABLE order_tickets (
    order_id    BIGINT NOT NULL REFERENCES orders(id),
    ticket_id   BIGINT NOT NULL REFERENCES tickets(id),
    PRIMARY KEY (order_id, ticket_id)
);
```

---

## Seat Locking: Race Condition Prevention

```
Problem: 10,000 insan eyni anda C14 oturacağını seçir

Həll: Temporary Lock (TTL-based)

Step 1: User oturacaq seçir
  → "Lock" ticket for 10 minutes (checkout window)
  
Step 2: Payment
  → Locked ticket satılır
  
Step 3: Payment fail / timeout
  → Lock expires → ticket available again

Redis lock (fast):
  SET ticket:lock:{ticket_id} {user_id} EX 600 NX
  -- NX: yalnız mövcud deyilsə set et
  -- EX 600: 10 dəqiqə TTL
  -- Return: OK (uğurlu) ya NULL (artıq locked)

PostgreSQL lock (persistent):
  UPDATE tickets
  SET status = 'locked',
      locked_by = :user_id,
      locked_until = NOW() + INTERVAL '10 minutes'
  WHERE id = :ticket_id
    AND status = 'available'
    AND (locked_until IS NULL OR locked_until < NOW());
  -- Affected rows = 0 → başqası lock etdi!

Hybrid approach:
  1. Redis SET NX → fast check
  2. PostgreSQL UPDATE → persistent state
  3. If Redis OK but PG fails → rollback Redis
```

---

## Virtual Queue (Waiting Room)

```
Taylor Swift problemi:
  1M+ insan eyni anda sale_start-da gəlir
  Serverlər çökür
  
Həll: Virtual Waiting Room

Pre-queue (saatlar əvvəl):
  User "gözləmə salonuna" daxil olur
  ZADD waiting_room:{event_id} {join_timestamp} {user_id}

Sale opens:
  Random shuffle within time window
  (ilk 1 saatda gələn hamı "bərabər" — fairness)
  
Position assignment:
  1. Random weighted sort
  2. ZRANK waiting_room:{event_id} {user_id} → position
  3. User öz növbəsini görür: "You are #45,231 in queue"
  
Batch release:
  Her 30 saniyə: növbəti N user-ə ticket seçim icazəsi verilir
  ZPOPMIN waiting_room:{event_id} {batch_size}
  
  Rate: capacity / expected_checkout_time
  Məsələn: 20,000 bilet, ortalama checkout 5 dəq
  Batch: 20,000 / (5*60/30) = ~2,000 user per batch

Redis:
  ZADD queue:{event_id} {timestamp} {user_id}
  ZCARD queue:{event_id}              → total in queue
  ZRANK queue:{event_id} {user_id}    → user position
  ZPOPMIN queue:{event_id} 2000       → next batch
```

---

## Anti-Scalping Measures

```
Scalping: bot-lar bilet alır → yüksək qiymətə satır

Texniki tədbirlər:

1. Purchase limits per account:
   max_tickets_per_order = 4
   Per user per event: DB CHECK
   
   SELECT COUNT(*) FROM order_tickets ot
   JOIN orders o ON o.id = ot.order_id
   JOIN tickets t ON t.id = ot.ticket_id
   WHERE o.user_id = :user_id
     AND t.event_id = :event_id
     AND o.status IN ('confirmed', 'pending');
   
   > max → reject

2. Bot detection:
   CAPTCHA (Google reCAPTCHA v3)
   Behavioral analysis (mouse movement, typing speed)
   Purchase IP rate limiting
   
   Redis:
   INCR purchases:{ip}:{hour}
   > threshold → suspicious flag

3. Verified Fan (Ticketmaster):
   Pre-registration required
   Purchase history scoring
   Only registered fans get presale access
   
4. Named tickets:
   tickets.current_owner = buyer name
   Transfer requires identity verification

5. Price cap on resale:
   max_resale_price = face_value * 1.1 (10% max markup)
   Ticket transfer API enforces this

6. Device fingerprinting:
   orders.device_fingerprint
   Same fingerprint > N purchases → flag
```

---

## Available Tickets Query

```sql
-- Bir event üçün mövcud biletlər (type + count)
SELECT
    tt.id,
    tt.name,
    tt.price,
    tt.total_quantity,
    COUNT(t.id) FILTER (WHERE t.status = 'available') AS available_now,
    COUNT(t.id) FILTER (WHERE t.status = 'locked'
                          AND t.locked_until > NOW()) AS currently_locked
FROM ticket_types tt
LEFT JOIN tickets t ON t.ticket_type_id = tt.id
WHERE tt.event_id = :event_id
GROUP BY tt.id, tt.name, tt.price, tt.total_quantity;

-- Assigned seating: mövcud oturacaqlar
SELECT s.row_label, s.seat_number, s.seat_type, t.status
FROM seats s
JOIN tickets t ON t.seat_id = s.id
WHERE t.event_id = :event_id
  AND s.section_id = :section_id
  AND t.status = 'available'
ORDER BY s.row_label, s.seat_number;
```

---

## Best Practices

```
✓ Optimistic/pessimistic lock for seat selection
✓ Short lock TTL (10 min) → expired locks auto-release
✓ Virtual queue → fair distribution under high load
✓ Barcode generation post-payment (prevent pre-generation)
✓ Device/IP rate limiting → bot prevention
✓ Face value tracking → anti-scalping enforcement

Anti-patterns:
✗ Selling ticket without lock → race condition
✗ Long lock window (30+ min) → inventory appears sold out
✗ No purchase limits → bots buy all tickets
✗ Barcode in response before payment → screenshot fraud
✗ No waiting room → server overload on popular events
```
