# Food Delivery System Design (DoorDash / Wolt)

## Nədir? (What is it?)

**Food delivery system** — müştərinin yaxın restoranlardan yemək sifariş verdiyi, platformanın driver (courier / dasher) təyin edərək yeməyi çatdırdığı paylanmış sistemdir. DoorDash, Wolt, Uber Eats, Glovo, Deliveroo belə işləyir.

Ride-sharing (fayl 37) ilə oxşardır — hər ikisində real-time matching, geospatial query və dispatch var. Fərq:
- **Əlavə aktor**: restoran (kitchen prep time gözləyir)
- **Order batching**: driver 2-3 yaxın sifarişi bir dəfəyə daşıya bilər
- **Just-in-time dispatch**: driver restorana tam hazır olan vaxt gəlməlidir (erkən gəlsə gözləyir, gec gəlsə yemək soyuyur)

## Tələblər (Requirements)

### Funksional

1. **Browse**: istifadəçi lokasiyasına görə restoran axtarışı, cuisine filter, menyu
2. **Order**: cart, place order, payment authorization (hold)
3. **Restaurant accept**: mətbəx sifarişi qəbul edib hazırlamağa başlayır, estimated ready time verir
4. **Dispatch**: driver təyin edilir, driver qəbul/rədd edir
5. **Pickup**: driver restorana gəlir, yeməyi götürdüyünü təsdiqləyir
6. **Delivery**: müştəriyə navigate, təhvil vermə (proof of delivery)
7. **Payment capture**: uğurlu çatdırılmadan sonra
8. **Rating**: iki tərəfli (customer ↔ restaurant, customer ↔ driver)

### Non-functional

- **ETA accuracy**: ±2-3 dəqiqə (müştəri gözləntisi)
- **Fair driver assignment**: bir driver ard-arda uzaq sifarişlər almamalıdır
- **Low dispatch latency**: sifariş hazır olduqda driver artıq yaxında olmalıdır
- **High OPH (Orders Per Hour)**: driver-in saatlıq gəliri — platforma üçün əsas KPI
- **Surge handling**: nahar (12:00-14:00) və şam (19:00-21:00) pik saatlarında sistem dayanmamalıdır
- **Availability**: 99.95%, payment path 99.99%

### Ölçü (Scale)

```
Daily orders: 1M
Active drivers: 100k (online konkret vaxtda 20-30k)
Restaurants: 500k (aktiv)
Peak QPS:
  - Order placement: 1M / 86400 × 8 (peak factor) ≈ 100 QPS average, 800 peak
  - Driver location updates: 30k × 1/5s = 6k QPS
  - Restaurant search: 50k QPS pik
Storage:
  - Orders: 1M × 2KB × 365 ≈ 700GB/year
  - Location telemetry: 30k × 17280 (5s interval) × 100B ≈ 50GB/day
```

## Aktorlar (Actors)

```
                    Platform (Backend)
                          │
        ┌─────────────────┼─────────────────┐
        ▼                 ▼                 ▼
     Customer         Restaurant          Driver
      (App)            (Tablet)           (App)
        │                 │                 │
        └────────┬────────┴────────┬────────┘
                 ▼                 ▼
            Dispatcher          ETA ML
             Service            Model
```

- **Customer**: sifariş verir, real-time track edir
- **Restaurant**: sifarişi qəbul edib hazırlayır, tablet və ya POS integrasiya
- **Driver (dasher / courier)**: offer qəbul edir, pickup və delivery edir
- **Dispatcher service**: driver təyinatı edən core component
- **Platform**: user, order, payment, rating xidmətləri

## Əsas Axınlar (Main Flows)

### 1. Browse & Order

```
Customer (lat, lng) ──► Search Service
                         │
                         ▼
                   Geospatial Index
                   (geohash / S2)
                         │
                         ▼
                   Nearby restaurants
                   (cuisine filter, rating sort)
                         │
                         ▼
                   Menu & pricing
                         │
                         ▼
                   Cart → Place order
                         │
                         ▼
                   Payment authorization (hold)
```

### 2. Restaurant → Dispatch → Delivery

```
Order PAID ──► Restaurant tablet notification
                   │
                   ▼
              Accept + estimated ready time (15 min)
                   │
                   ▼
              Status: PREPARING
                   │
                   ▼
              Dispatcher timer (wake up at ready - travel_time)
                   │
                   ▼
              Offer to N closest drivers (3-5)
                   │
                   ▼
              Driver accepts
                   │
                   ▼
              Navigate to restaurant → PICKED_UP
                   │
                   ▼
              Navigate to customer → DELIVERED
                   │
                   ▼
              Payment capture + rating prompt
```

## Order State Machine

```
CREATED ─► PAID ─► ACCEPTED ─► PREPARING ─► READY
                                               │
                                               ▼
                                          PICKED_UP ─► DELIVERED

Hər keçiddən: ─► CANCELLED / REFUNDED (compensation)
```

Hər keçid audit log-da qeyd olunur, event olaraq Kafka-ya publish olunur.

## Geospatial (Geo indexing)

Restoranlar və driver-lər məkana görə tez tapılmalıdır. Cross-reference fayl 71 (geospatial indexing).

- **Restoranlar**: static location → geohash / S2 cell ID əvvəlcədən hesablanır, PostgreSQL + PostGIS və ya Elasticsearch geo_point
- **Driver-lər**: dinamik location, Redis `GEOADD drivers:city:baku lng lat driver_id`
- Query: `GEOSEARCH drivers:city:baku FROMLONLAT ... BYRADIUS 5 km`

```
Driver app ──► Location update (every 5s)
                   │
                   ▼
              Kafka topic: driver.locations
                   │
                   ├─► Redis Geo (hot cache)
                   └─► Time-series DB (history, analytics)
```

## Dispatch Algoritmi (Dispatch Algorithm)

### 1. Auction / Offer Model

Sifariş hazır olmağa yaxın olanda dispatcher N ən yaxın driver-ə offer göndərir:

```
offer = {
    order_id,
    restaurant: {location, name},
    dropoff: {location, address},
    estimated_earning: $8.50,
    estimated_duration: 22 min,
    expires_at: +30s
}
```

Driver qəbul etməsə, offer digərinə keçir.

### 2. Bipartite Matching (Batch)

Pik saatlarda dispatch loop hər 10-30 saniyədən bir çalışır və Hungarian algoritmi ilə qlobal optimal matching edir:

```
Orders × Drivers → cost matrix (travel time + food readiness fit)
Minimize total travel time, subject to:
  - driver capacity (vehicle size, bag)
  - driver state == available
  - max concurrent deliveries (2-3)
```

### 3. Batching / Stacking

Bir driver-ə 2-3 yaxın sifariş "stack" edilir (OPH artırmaq üçün):
- Eyni restorandan iki sifariş → asan
- Yaxın restoranlardan iki sifariş → pickup sıralaması optimize edilir
- Constraint: total extra time < 5 min per customer

### 4. Dispatch Timing (Just-in-Time)

```
dispatch_time = ready_time - estimated_travel_time - buffer(2 min)
```

- **Erkən**: driver gözləyir → OPH aşağı, driver narazı
- **Gec**: yemək soyuyur → müştəri narazı
- ML model prep_time-i proqnozlaşdırır, bu vaxtı dinamik hesablayır

## ETA & Prep Time Prediction

### Prep Time (restoran-specific)

Feature-lər:
- restaurant historical prep time (last 30 days)
- current kitchen load (açıq sifarişlər)
- order items count & complexity (pizza vs salad)
- time of day (nahar pik)

Model: gradient boosting (XGBoost / LightGBM), regression target = minutes.

### Travel Time

- Mapbox / Google Directions API
- OSRM self-hosted (pik yükdə ucuz)
- Real-time traffic data

### Total ETA

```
ETA = prep_time + dispatch_wait + travel_to_restaurant
      + handoff_at_restaurant + travel_to_customer + handoff_at_customer
```

Müştəriyə **conservative** göstərilir (p80 quantile), driver-ə **aggressive** (p50).

## Surge Pricing

Tələb > təklif olduqda delivery fee artır, bonus driver-lərə:

```
surge_multiplier = f(open_orders / available_drivers)
delivery_fee = base_fee × surge_multiplier
driver_incentive = +$X per delivery
```

Ride-sharing (fayl 37) surge-ə tamamlayıcı konsept.

## Data Modeli (Data Model)

```sql
users(id, email, phone, default_address, rating)
restaurants(id, name, location POINT, cuisine, prep_time_avg_min, rating, open_hours)
menu_items(id, restaurant_id, name, price, category, is_available)
orders(id, user_id, restaurant_id, driver_id NULL, state, subtotal, fee, surge_mult,
       created_at, accepted_at, ready_at, picked_up_at, delivered_at)
order_items(id, order_id, menu_item_id, qty, unit_price)
drivers(id, name, vehicle_type, location POINT, state, current_deliveries, rating)
dispatches(id, order_id, driver_id, offered_at, accepted_at, rejected_reason)
payments(id, order_id, provider, auth_ref, captured_amount, status, idempotency_key)
ratings(id, order_id, from_type, to_type, stars, comment)
```

## Storage & Infra Seçimləri

- **Orders, users, restaurants**: MySQL / PostgreSQL (ACID, payment path)
- **Driver location (hot)**: Redis Geo
- **Location history**: ClickHouse / TimescaleDB
- **Events**: Kafka (order.created, order.ready, dispatch.offered, etc.)
- **Real-time tracking**: WebSocket / gRPC streaming (Laravel Reverb)
- **Search**: Elasticsearch (restaurant + menu fuzzy search)

## Laravel Misalı (Order State Machine + Dispatcher)

```php
<?php
// app/Models/Order.php
class Order extends Model
{
    protected static array $transitions = [
        'CREATED'   => ['PAID', 'CANCELLED'],
        'PAID'      => ['ACCEPTED', 'CANCELLED'],
        'ACCEPTED'  => ['PREPARING', 'CANCELLED'],
        'PREPARING' => ['READY', 'CANCELLED'],
        'READY'     => ['PICKED_UP', 'CANCELLED'],
        'PICKED_UP' => ['DELIVERED'],
    ];

    public function transitionTo(string $next): void
    {
        $allowed = self::$transitions[$this->state] ?? [];
        if (!in_array($next, $allowed, true)) {
            throw new \DomainException("Invalid: {$this->state} -> {$next}");
        }
        DB::transaction(function () use ($next) {
            $this->update(['state' => $next]);
            event(new OrderStateChanged($this, $next));
        });
    }
}

// app/Listeners/ScheduleDispatchOnReady.php
class ScheduleDispatchOnReady
{
    public function handle(OrderStateChanged $event): void
    {
        if ($event->newState === 'ACCEPTED') {
            $fireAt = $event->order->ready_at->subMinutes(5);
            DispatchDriverJob::dispatch($event->order->id)->delay($fireAt);
        }
    }
}

// app/Jobs/DispatchDriverJob.php
class DispatchDriverJob implements ShouldQueue
{
    public function __construct(public int $orderId) {}

    public function handle(DriverFinder $finder, OfferService $offers): void
    {
        $order = Order::findOrFail($this->orderId);
        if (!in_array($order->state, ['PREPARING', 'READY'])) return;

        $candidates = $finder->nearby(
            $order->restaurant->lat, $order->restaurant->lng, 5, 5
        );

        foreach ($candidates as $driverId) {
            if ($offers->sendOffer($order, $driverId, ttl: 30)) return;
        }
        self::dispatch($this->orderId)->delay(now()->addSeconds(30));
    }
}

// app/Services/DriverFinder.php
class DriverFinder
{
    public function nearby(float $lat, float $lng, float $radiusKm, int $limit): array
    {
        return Redis::command('GEOSEARCH', [
            'drivers:available:baku',
            'FROMLONLAT', $lng, $lat,
            'BYRADIUS', $radiusKm, 'km',
            'ASC', 'COUNT', $limit,
        ]);
    }
}
```

**Horizon** dispatch job-larını idarə edir, **Reverb** customer app-ə real-time state göndərir.

## Failure Modes & Reliability

1. **Driver abandons** — timeout (pickup-dan 20 min keçdi, hərəkət yox) → re-dispatch, driver reliability score aşağı
2. **Restaurant closes / refuses** — PREPARING-dən CANCELLED-ə, refund başlat
3. **Payment fails at capture** — order DELIVERED olsa da capture uğursuz olsa, reconciliation job retry edir, chargeback queue-ya köçür
4. **Dispatcher offline** — order `awaiting_dispatch` queue-da qalır, failover instance götürür
5. **Exactly-once charge** — `payments.idempotency_key` unique, re-try təhlükəsiz
6. **Reconciliation job** — gündəlik: bütün DELIVERED orders captured olmalıdır, fərqlər alert

## Ümumi Arxitektura (Overall Architecture)

```
 Customer   Restaurant Tablet   Driver App
    │            │                 │
    └────────────┼─────────────────┘
                 ▼
         ┌───────────────┐
         │  API Gateway  │
         └───────┬───────┘
     ┌─────┬────┴────┬───────────┐
     ▼     ▼         ▼           ▼
  Search  Order   Payment   Dispatcher
    │       │        │          │
   ES    MySQL    Stripe    Redis Geo
            │
            ▼
          Kafka ──► ETA ML, Analytics, Notifications
```

## Interview Sualları (Interview Q&A)

**S1: Driver-i sifariş hazır olmamış təyin etmək olarmı?**
C: Bəli, amma vaxtı ML model proqnozlaşdırır. Driver restorana tam ready vaxtında gəlsin deyə `dispatch_time = ready_time - travel_time - buffer` formulu. Erkən gəlsə OPH aşağı düşür, gec gəlsə yemək soyuyur.

**S2: İki sifarişi eyni driver-ə batch etmək nə vaxt sərfəlidir?**
C: Restoranlar yaxın (<1 km) və customer ünvanları bir istiqamətdə olduqda. Constraint: ikinci müştərinin əlavə gözləmə vaxtı < 5 dəqiqə. Bu OPH-ni 30-40% artırır.

**S3: Driver sifarişi qəbul etmirsə nə olur?**
C: Offer TTL (30s) bitir, sistem növbəti ən yaxın driver-ə keçir. Bir neçə rədd sonra radius genişlənir (5 → 10 km), ya da incentive (bonus) əlavə olunur.

**S4: ETA niyə müştəriyə daha konservativ göstərilir?**
C: Məyusluq asymmetrik maliyyətlidir — 3 dəq gec gəlmək (pis) 3 dəq erkən gəlməkdən (yaxşı) daha pis hiss olunur. Bu səbəbdən müştəriyə p80 quantile, driver-ə p50 göstərilir.

**S5: Payment-də exactly-once necə təmin olunur?**
C: Hər charge attempt-ə unique idempotency_key verilir, Stripe (və ya digər PSP) eyni key ilə ikinci sorğunu tanıyır və keçmiş response qaytarır. DB-də `payments.idempotency_key UNIQUE` constraint. Reconciliation job gündəlik unmatched order/capture tapır.

**S6: Restoran hazırlıq vaxtını necə proqnozlaşdırırıq?**
C: Restoran-specific ML regression model. Feature-lər: tarixi prep time, hazırkı kitchen load (aktiv sifariş sayı), sifariş item-lərinin sayı və complexity, günün saatı, həftənin günü. Gradient boosting yaxşı işləyir.

**S7: Pik saatlarda driver tapılmasa nə edirik?**
C: Bir neçə strategiya: (1) surge pricing — delivery fee artır, driver-lərə bonus; (2) customer-ə longer ETA göstərib gözləməyi təklif etmək; (3) batching aggressive-ləşdirmək; (4) restoranlara "slow mode" — yeni sifariş qəbulunu dayandırmaq.

**S8: Driver location update-ləri niyə birbaşa MySQL-ə yazılmır?**
C: Çox write volume (30k QPS), və hot path query-lər radius search-dir. Redis Geo O(log N) ilə həll edir, MySQL-də PostGIS olsa belə belə yük write-ə uyğun deyil. History analytics üçün Kafka → ClickHouse.

## Best Practices

- **Ayrı read/write path**: order write MySQL-ə, read-heavy tracking Redis + WebSocket
- **Idempotency key hər side-effect endpoint-də** (order create, payment capture, driver accept)
- **State machine mərkəzləşdir**: hər keçid yalnız bir yerdən icazəli, event publish et
- **Dispatch loop şəffaf log**: niyə bu driver seçildi — debuggable decision log saxla
- **Fairness metrics**: driver earnings distribution monitor et, bir nəfər 10x götürmürsə
- **Graceful degradation**: ML model offline olsa, restoran average prep_time fallback
- **Backpressure**: restoran tablet offline olsa, sifariş SMS / çağırı ilə fallback
- **Reconciliation jobs**: gündəlik orders vs payments, orders vs dispatches match edir
- **Separate quotas**: customer app 99.95%, driver app 99.99% (driver onlayn qalmalıdır)
- **Testing**: chaos inject — driver disconnect, restaurant offline, payment timeout
- **Cross-reference**:
  - Fayl 37 — ride-sharing (oxşar matching, surge)
  - Fayl 71 — geospatial indexing (geohash / S2)
  - Fayl 41 — payment systems (idempotency, reconciliation)
  - Fayl 56 — real-time notifications (WebSocket, push)
