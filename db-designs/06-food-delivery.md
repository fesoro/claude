# Food Delivery App — DB Design (Middle ⭐⭐)

## Tövsiyə olunan DB Stack
```
Core:         PostgreSQL + PostGIS  (sifarişlər, restoranlar, geospatial)
Location:     Redis + PostGIS       (real-time kuryer tracking)
Menu:         PostgreSQL / MongoDB  (çox dəyişkən menu items)
Cache:        Redis                 (restaurant cache, session, cart)
Search:       Elasticsearch         (restoran/yemək axtarışı)
Analytics:    ClickHouse            (restoran analytics)
Queue:        Kafka                 (sifariş event-ləri)
```

---

## Niyə Hybrid Stack?

```
Menu data üçün MongoDB alternativ:
  ✓ Schema-free: pizza menyu vs sushi menyu tamamilə fərqli
  ✓ Nested documents: topping-lər, modifier qrupları
  ✗ ACID yoxdur: sifariş zamanı atomik lazım
  
Niyə PostgreSQL seçdik?
  ✓ JSONB: menu items üçün flexible schema
  ✓ PostGIS: restoran axtarışı (2km ətrafda)
  ✓ ACID: sifariş + ödəniş + stock atomik
  
Redis üstünlükləri:
  ✓ Cart: müvəqqəti, tez-tez yazılan
  ✓ Kuryer location: saniyəlik update
  ✓ Restaurant availability: cache (30 dəqiqə)
```

---

## Schema Design

```sql
-- ==================== İSTİFADƏÇİLƏR ====================
CREATE TABLE users (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email        VARCHAR(255) UNIQUE NOT NULL,
    phone        VARCHAR(20) UNIQUE NOT NULL,
    first_name   VARCHAR(100) NOT NULL,
    last_name    VARCHAR(100),
    password_hash VARCHAR(255),
    status       VARCHAR(20) DEFAULT 'active',
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE user_addresses (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      UUID NOT NULL REFERENCES users(id),
    label        VARCHAR(50),        -- 'Ev', 'İş', 'Digər'
    address_line TEXT NOT NULL,
    instructions TEXT,               -- "3-cü mərtəbə, zəng et"
    location     GEOGRAPHY(POINT, 4326) NOT NULL,
    is_default   BOOLEAN DEFAULT FALSE
);

-- Kuryerlər
CREATE TABLE couriers (
    user_id         UUID PRIMARY KEY REFERENCES users(id),
    vehicle_type    VARCHAR(20) NOT NULL,  -- 'bike', 'motorcycle', 'car', 'walk'
    license_plate   VARCHAR(20),
    current_status  VARCHAR(20) DEFAULT 'offline',
    -- offline, available, picking_up, delivering
    is_online       BOOLEAN DEFAULT FALSE,
    rating          NUMERIC(3,2) DEFAULT 5.00,
    delivery_count  INT DEFAULT 0,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== RESTORANLAR ====================
CREATE TABLE restaurants (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) UNIQUE NOT NULL,
    description     TEXT,
    
    -- Yer
    address         TEXT NOT NULL,
    city            VARCHAR(100) NOT NULL,
    location        GEOGRAPHY(POINT, 4326) NOT NULL,
    delivery_radius_km NUMERIC(4,1) DEFAULT 3.0,
    
    -- Görünüş
    logo_url        TEXT,
    cover_url       TEXT,
    
    -- Iş saatları
    working_hours   JSONB NOT NULL,
    -- {"monday": {"open": "09:00", "close": "22:00"}, ...}
    
    -- Status
    is_active       BOOLEAN DEFAULT TRUE,
    is_accepting_orders BOOLEAN DEFAULT TRUE,
    
    -- Statistika (denormalized)
    rating          NUMERIC(3,2) DEFAULT 0,
    rating_count    INT DEFAULT 0,
    
    -- Çatdırılma
    delivery_fee    NUMERIC(6,2) DEFAULT 0,
    min_order_amount NUMERIC(8,2) DEFAULT 0,
    avg_delivery_min INT DEFAULT 30,  -- ortalama çatdırılma dəqiqəsi
    
    -- Kategoriyalar (array)
    cuisine_types   TEXT[] DEFAULT '{}',  -- ['pizza', 'italian']
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Spatial index: "ətrafımdakı restoranlar"
CREATE INDEX idx_restaurants_location ON restaurants USING GIST (location)
    WHERE is_active = TRUE;
CREATE INDEX idx_restaurants_city     ON restaurants(city, is_active);

-- ==================== MENU ====================
CREATE TABLE menu_categories (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    restaurant_id UUID NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
    name          VARCHAR(100) NOT NULL,
    description   TEXT,
    image_url     TEXT,
    sort_order    SMALLINT DEFAULT 0,
    is_active     BOOLEAN DEFAULT TRUE,
    -- Zaman məhdudiyyəti (səhər menyu, nahar menyu)
    available_from TIME,
    available_until TIME
);

CREATE TABLE menu_items (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    restaurant_id   UUID NOT NULL REFERENCES restaurants(id),
    category_id     UUID NOT NULL REFERENCES menu_categories(id),
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    image_url       TEXT,
    base_price      NUMERIC(8,2) NOT NULL,
    
    -- Flexible attributes (JSONB)
    attributes      JSONB DEFAULT '{}',
    -- {"calories": 650, "allergens": ["gluten", "dairy"], "spicy_level": 2}
    
    -- Modifier qrupları (topping, size, etc.)
    modifier_groups JSONB DEFAULT '[]',
    -- [{"id": "size", "name": "Ölçü", "required": true, "max_select": 1,
    --   "options": [{"name": "Kiçik", "price": 0}, {"name": "Böyük", "price": 2}]}]
    
    is_available    BOOLEAN DEFAULT TRUE,
    is_featured     BOOLEAN DEFAULT FALSE,
    sort_order      SMALLINT DEFAULT 0,
    
    -- Stok (isteğe bağlı)
    track_inventory BOOLEAN DEFAULT FALSE,
    stock_count     INT,
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_menu_items_restaurant ON menu_items(restaurant_id, category_id)
    WHERE is_available = TRUE;

-- ==================== SİFARİŞLƏR ====================
CREATE TABLE orders (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_number      VARCHAR(20) UNIQUE NOT NULL,  -- '#12345'
    
    -- Tərəflər
    user_id           UUID NOT NULL REFERENCES users(id),
    restaurant_id     UUID NOT NULL REFERENCES restaurants(id),
    courier_id        UUID REFERENCES couriers(user_id),
    
    -- Status machine
    status            VARCHAR(30) NOT NULL DEFAULT 'pending',
    -- pending, confirmed, preparing, ready_for_pickup,
    -- courier_assigned, courier_picking_up, on_the_way,
    -- delivered, cancelled, refunded
    
    -- Ünvanlar
    delivery_address  TEXT NOT NULL,
    delivery_location GEOGRAPHY(POINT, 4326) NOT NULL,
    delivery_instructions TEXT,
    
    -- Qiymətlər
    subtotal          NUMERIC(8,2) NOT NULL,
    delivery_fee      NUMERIC(6,2) DEFAULT 0,
    service_fee       NUMERIC(6,2) DEFAULT 0,
    discount_amount   NUMERIC(8,2) DEFAULT 0,
    tip_amount        NUMERIC(6,2) DEFAULT 0,
    total_amount      NUMERIC(8,2) NOT NULL,
    
    -- Zaman izləmə
    placed_at         TIMESTAMPTZ DEFAULT NOW(),
    confirmed_at      TIMESTAMPTZ,
    preparing_at      TIMESTAMPTZ,
    ready_at          TIMESTAMPTZ,
    courier_assigned_at TIMESTAMPTZ,
    picked_up_at      TIMESTAMPTZ,
    delivered_at      TIMESTAMPTZ,
    cancelled_at      TIMESTAMPTZ,
    
    -- Estimated times
    estimated_prep_min   INT,
    estimated_delivery_min INT,
    
    -- Digər
    payment_method    VARCHAR(30),
    notes             TEXT,
    coupon_code       VARCHAR(50),
    
    created_at        TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_orders_user       ON orders(user_id, placed_at DESC);
CREATE INDEX idx_orders_restaurant ON orders(restaurant_id, placed_at DESC);
CREATE INDEX idx_orders_courier    ON orders(courier_id, placed_at DESC)
    WHERE courier_id IS NOT NULL;
CREATE INDEX idx_orders_active     ON orders(status, placed_at DESC)
    WHERE status NOT IN ('delivered', 'cancelled', 'refunded');

CREATE TABLE order_items (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id       UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    menu_item_id   UUID NOT NULL REFERENCES menu_items(id),
    -- Snapshot (qiymət sonradan dəyişsə belə)
    item_name      VARCHAR(255) NOT NULL,
    unit_price     NUMERIC(8,2) NOT NULL,
    quantity       SMALLINT NOT NULL,
    selected_mods  JSONB DEFAULT '[]',
    -- [{"group": "size", "option": "Böyük", "price": 2}]
    item_total     NUMERIC(8,2) NOT NULL,
    notes          TEXT  -- "Soğansız olsun"
);

-- ==================== REYTİNQ ====================
CREATE TABLE order_reviews (
    order_id          UUID PRIMARY KEY REFERENCES orders(id),
    user_id           UUID NOT NULL REFERENCES users(id),
    restaurant_rating SMALLINT CHECK (restaurant_rating BETWEEN 1 AND 5),
    courier_rating    SMALLINT CHECK (courier_rating BETWEEN 1 AND 5),
    food_rating       SMALLINT CHECK (food_rating BETWEEN 1 AND 5),
    comment           TEXT,
    created_at        TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Redis Dizaynı

```
# Cart (Müvəqqəti, 2 saat TTL)
HSET cart:{user_id} {item_id}:{modifiers_hash} {quantity}
EXPIRE cart:{user_id} 7200

# Restoran cache (30 dəqiqə)
SET restaurant:{id} {json} EX 1800
SET restaurant:menu:{id} {json} EX 1800

# Kuryer real-time location
GEOADD couriers:available {lng} {lat} {courier_id}
GEORADIUS couriers:available {pickup_lng} {pickup_lat} 5 km COUNT 10 ASC

# Aktiv sifarişin son mövqeyi
HSET courier:location:{courier_id} lat {lat} lng {lng} updated_at {ts}
EXPIRE courier:location:{courier_id} 60

# Restoran "indi açıqdır?"
SET restaurant:open:{id} 1 EX 600   -- 10 dəqiqə cache

# Sifariş status (WebSocket push üçün)
PUBLISH order:updates:{order_id} {status_json}
```

---

## Kritik Dizayn Qərarları

```
1. JSONB modifier_groups (menu items):
   Pizza: size + crust + toppings
   Coffee: size + milk type + extra shots
   Hər restoran fərqli modifiers
   JSONB → flexible, amma query bahalı
   Trade-off: flexibility > strict schema

2. Order items snapshot:
   item_name, unit_price kopyalanır
   Menu dəyişsə, köhnə sifarişlər qorunur
   selected_mods JSONB: seçilmiş modifier-ların snapshot-u

3. Delivery location GEOGRAPHY:
   ST_DWithin(delivery_location, restaurant_location, delivery_radius)
   Çatdırılma zolasını yoxlamaq üçün
   Courier matching üçün geospatial sorğu

4. Estimated times:
   estimated_prep_min: AI/ML model (restoran history-ə görə)
   estimated_delivery_min: distance + traffic
   İstifadəçiyə göstərilən "~30 dəq"

5. Working hours JSONB:
   Hər gün fərqli saatlar
   Bayram günləri üçün exceptions
   Soft validation (DB-dən çox app layer-da)
```

---

## Best Practices

```
✓ Cart Redis-də (DB-yə yazma yoxdur — tez-tez dəyişir)
✓ Menu cache Redis-də (hər sorğuda DB-yə getmə)
✓ Order items snapshot (item_name, price kopyala)
✓ GEOGRAPHY tip location üçün
✓ Partial index aktiv sifarişlər üçün
✓ JSONB modifier schema restaurant tərəfindən idarə edilir
✓ Courier real-time location Redis GEO-da
✓ Working hours JSONB-da (flexible, hər gün fərqli)

Anti-patterns:
✗ Cart-ı DB-də saxlamaq (Redis daha uyğun)
✗ Menu-nu hər sorğuda DB-dən çəkmək
✗ Order items-da FK yalnız (snapshot yoxdur)
✗ Courier location-u saniyəlik DB-yə yazmaq
```

---

## Tanınmış Sistemlər

```
Wolt (Helsinki, 2014):
  PostgreSQL         → əsas data
  PostGIS            → geospatial (restoran/kuryer)
  Redis              → cache, sessions
  Kafka              → event streaming
  Kubernetes (GKE)   → infrastructure
  
  Wolt 2021-ci ildə DoorDash tərəfindən 8.1 milyarda alındı

Bolt Food:
  PostgreSQL + PostGIS → əsas
  Redis               → real-time tracking
  
DoorDash:
  PostgreSQL (Aurora) → core data
  Cassandra           → activity log
  DynamoDB            → feature flags, config
  Redis               → caching, rate limiting
  Kafka               → event streaming, order pipeline
```
