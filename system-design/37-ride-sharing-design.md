# Ride-Sharing System Design (Uber/Lyft)

## Nədir? (What is it?)

**Ride-sharing sistem** — real-time olaraq sərnişinləri sərbəst sürücülərlə tapan, mövqe izləyən, qiymətləndirən və ödəniş idarə edən paylanmış sistem. Uber, Lyft, Bolt, Didi kimi xidmətlər belə işləyir.

**Əsas tələblər:**
- **Funksional**: matching, ETA, real-time location, surge pricing, payment, rating
- **Non-functional**: low latency (<200ms matching), high availability (99.99%), scalability (milyardlarla trip/il)
- **Geospatial queries** çox sürətli olmalıdır — "5km radiusda sərbəst sürücülər"

## Əsas Konseptlər (Key Concepts)

### Sistem Tələbləri (funksional)

1. **Rider**: ride request, driver-i görmə, ETA, payment, rating
2. **Driver**: online/offline, request qəbul/rədd, navigation, earnings
3. **Matching**: optimal driver-rider birləşməsi
4. **Pricing**: base + distance + time + surge
5. **Tracking**: real-time GPS location
6. **Payment**: card, wallet, cash
7. **Rating & reviews**: iki tərəfli

### Ölçü Hesabları (Back-of-envelope)

```
Daily Active Users: 10M rider, 1M driver
Daily trips: 5M
Peak QPS:
  - Location updates: 1M drivers × 1/4s = 250K QPS
  - Ride requests: 5M / 86400 × 5 (peak factor) ≈ 300 QPS
  - ETA queries: 50K QPS

Storage:
  - Trip history: 5M × 1KB × 365 = ~1.8TB/year
  - Location history: 250K × 86400 × 100B ≈ 2TB/day
```

### Geospatial Indexing

Sürücüləri məkana görə tez tapmaq lazımdır.

#### 1. Quadtree

2D space-i recursive olaraq 4 hissəyə bölür.

```
┌────────┬────────┐
│   NW   │   NE   │
│   ┌────┼────┐   │
│   │ NW │ NE │   │
├───┼────┼────┼───┤
│   │ SW │ SE │   │
│   └────┼────┘   │
│   SW   │   SE   │
└────────┴────────┘

Hər quadrant-da maksimum N nöqtə olanda, quadrant daha 4-ə bölünür.
Query: log(N) time.
```

**Problem:** Dinamik update (drivers çox tez-tez mövqeyini dəyişir) bahalıdır.

#### 2. Geohash

Koordinatları Base32 string-ə çevirir. Oxşar geohash → coğrafi yaxın.

```
lat=40.7128, lng=-74.0060 (NYC) → "dr5ru"

dr5ru - New York
dr5rv - bir neçə yüz metr uzaqlıqda
dr5r - daha geniş ərazi (prefix)

Precision:
  1 char: ±2500km
  5 chars: ±2.4km
  7 chars: ±76m
  9 chars: ±2.4m
```

**Query**: `SELECT * FROM drivers WHERE geohash LIKE 'dr5ru%'`

#### 3. H3 (Uber)

Hexagonal hierarchical spatial index. Uber özü yaratdı.

```
Üstünlüklər:
- Altıbucaqlar daha bərabər qonşu məsafəsi verir
- 16 səviyyə (5000km² - 1m²)
- Kompakt ID (64-bit integer)
- Sürətli arithmetic operations
```

Uber bütün dispatch sistemini H3 üzərində qurub.

#### 4. S2 (Google)

Hilbert curve əsaslı, earth-i sferik cell-lərə bölür. Google Maps, Foursquare istifadə edir.

### Matching Algorithm

**Nearest Driver (sadə):**
```
1. Rider location → geohash
2. Yaxın cells-dən drivers tap
3. Sort by distance
4. Ən yaxın 5-ə request göndər
```

**Optimal Matching (kompleks):**
- **Hungarian algorithm** — assignment problem
- **Global dispatch** — bir neçə rider + driver-i eyni anda match et
- Uber-in real-time system-i hər 2 saniyəyə optimal matching çalışdırır

**Matching factors:**
- Distance / ETA
- Driver rating
- Ride type (UberX, UberPool, UberBlack)
- Driver acceptance rate history
- Destination preferences

### Surge Pricing

Supply/demand uyğunsuzluğunda qiymət artır.

```
Surge multiplier = max(1, demand / supply * factor)

Area-ları hex cell-lərə böl.
Hər cell-də:
  demand = son 5 dəq ride request sayı
  supply = sərbəst driver sayı

Multiplier: 1.0x, 1.5x, 2.0x, 3.0x ...
```

### ETA Calculation

```
Naive: distance / average_speed
Better: routing engine (OSRM, Google Maps, Valhalla)
Best: ML model with features:
  - Time of day, day of week
  - Weather
  - Traffic (real-time)
  - Historical ETA
  - Route characteristics
```

Uber-in **deepETA** modeli: XGBoost + neural network.

## Arxitektura

```
┌──────────────┐                         ┌──────────────┐
│ Rider App    │                         │ Driver App   │
└──────┬───────┘                         └──────┬───────┘
       │                                        │
       └────────────┬───────────────────────────┘
                    │
                    ▼
          ┌──────────────────┐
          │   API Gateway    │
          │  (Auth, RL)      │
          └────────┬─────────┘
                   │
    ┌──────────────┼──────────────┬───────────────┐
    │              │              │               │
    ▼              ▼              ▼               ▼
┌────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐
│ Trip   │   │ Location │   │ Matching │   │ Payment  │
│ Svc    │   │ Service  │   │ Service  │   │ Service  │
└───┬────┘   └────┬─────┘   └────┬─────┘   └────┬─────┘
    │             │              │              │
    │             ▼              ▼              │
    │      ┌──────────────┐┌──────────┐         │
    │      │ Geo Index    ││ Dispatch │         │
    │      │ (H3/Redis)   ││ Engine   │         │
    │      └──────────────┘└──────────┘         │
    │                                            │
    ▼                                            ▼
┌────────┐                               ┌──────────┐
│ MySQL  │                               │  Stripe  │
│(trips) │                               └──────────┘
└────────┘

Real-time: Kafka → Flink → Analytics
GPS: WebSocket / gRPC streaming
```

**Əsas mikroservislər:**
- **Trip Service** — trip lifecycle
- **Location Service** — GPS updates, geo index (Redis + H3)
- **Matching Service** — dispatch engine
- **Pricing Service** — surge, fare calculation
- **Payment Service** — Stripe/Braintree integration
- **Notification Service** — push, SMS
- **Rating Service** — hər iki tərəf
- **Driver Service** — onboarding, documents

## PHP/Laravel ilə Tətbiq

### Geohash ilə Driver Location

```php
<?php

namespace App\Services\Location;

class GeohashService
{
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    public function encode(float $lat, float $lng, int $precision = 7): string
    {
        $latRange = [-90.0, 90.0];
        $lngRange = [-180.0, 180.0];
        $hash = '';
        $bits = 0;
        $ch = 0;
        $even = true;

        while (strlen($hash) < $precision) {
            if ($even) {
                $mid = ($lngRange[0] + $lngRange[1]) / 2;
                if ($lng > $mid) {
                    $ch = ($ch << 1) | 1;
                    $lngRange[0] = $mid;
                } else {
                    $ch = $ch << 1;
                    $lngRange[1] = $mid;
                }
            } else {
                $mid = ($latRange[0] + $latRange[1]) / 2;
                if ($lat > $mid) {
                    $ch = ($ch << 1) | 1;
                    $latRange[0] = $mid;
                } else {
                    $ch = $ch << 1;
                    $latRange[1] = $mid;
                }
            }

            $even = !$even;
            if (++$bits === 5) {
                $hash .= self::BASE32[$ch];
                $bits = 0;
                $ch = 0;
            }
        }

        return $hash;
    }

    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
```

### Driver Location Service (Redis Geo)

```php
namespace App\Services\Location;

use Illuminate\Support\Facades\Redis;

class DriverLocationService
{
    private const GEO_KEY = 'drivers:online';
    private const DRIVER_TTL = 30; // seconds

    public function updateLocation(int $driverId, float $lat, float $lng): void
    {
        // Redis GEO — internally geohash istifadə edir
        Redis::geoadd(self::GEO_KEY, $lng, $lat, $driverId);
        Redis::setex("driver:{$driverId}:lastseen", self::DRIVER_TTL, now()->timestamp);
        Redis::hset("driver:{$driverId}:location", 'lat', $lat, 'lng', $lng);
    }

    public function findNearby(float $lat, float $lng, float $radiusKm = 5, int $limit = 10): array
    {
        // Redis GEORADIUS
        $results = Redis::georadius(
            self::GEO_KEY,
            $lng,
            $lat,
            $radiusKm,
            'km',
            ['WITHCOORD', 'WITHDIST', 'COUNT', $limit, 'ASC']
        );

        return array_map(fn($r) => [
            'driver_id' => (int) $r[0],
            'distance_km' => (float) $r[1],
            'lng' => (float) $r[2][0],
            'lat' => (float) $r[2][1],
        ], $results);
    }

    public function goOffline(int $driverId): void
    {
        Redis::zrem(self::GEO_KEY, $driverId);
        Redis::del("driver:{$driverId}:lastseen");
    }

    public function cleanupStale(): void
    {
        // Scheduled job — 30s-dən köhnə driver-ləri sil
        $all = Redis::zrange(self::GEO_KEY, 0, -1);
        foreach ($all as $driverId) {
            if (!Redis::exists("driver:{$driverId}:lastseen")) {
                Redis::zrem(self::GEO_KEY, $driverId);
            }
        }
    }
}
```

### Matching Service

```php
namespace App\Services\Matching;

class MatchingService
{
    public function __construct(
        private DriverLocationService $locations,
        private PricingService $pricing,
    ) {}

    public function findDriver(int $riderId, float $pickupLat, float $pickupLng, string $rideType): ?array
    {
        // 1. Radius-u mərhələli genişləndir
        foreach ([2, 5, 10, 20] as $radius) {
            $candidates = $this->locations->findNearby($pickupLat, $pickupLng, $radius, 20);
            $filtered = $this->filterByRideType($candidates, $rideType);
            $filtered = $this->filterByAcceptanceRate($filtered);

            if (count($filtered) >= 5) break;
        }

        if (empty($filtered)) return null;

        // 2. Score hər driver-i
        $scored = array_map(function ($driver) use ($pickupLat, $pickupLng) {
            $driver['eta_seconds'] = $this->estimateETA(
                $driver['lat'], $driver['lng'],
                $pickupLat, $pickupLng
            );
            $driver['score'] = $this->score($driver);
            return $driver;
        }, $filtered);

        // 3. Ən yüksək skorlu 5-ə ardıcıl request göndər
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $this->dispatchToDrivers(array_slice($scored, 0, 5), $riderId);
    }

    private function score(array $driver): float
    {
        // ETA daha az = daha yaxşı
        $etaScore = 1 / ($driver['eta_seconds'] + 1);
        // Rating və acceptance
        $qualityScore = ($driver['rating'] ?? 4.5) / 5.0;
        $acceptanceScore = ($driver['acceptance_rate'] ?? 0.8);
        return 0.6 * $etaScore + 0.2 * $qualityScore + 0.2 * $acceptanceScore;
    }

    private function estimateETA(float $dLat, float $dLng, float $pLat, float $pLng): int
    {
        // Simple — production-da routing engine
        $distance = (new GeohashService())->haversineDistance($dLat, $dLng, $pLat, $pLng);
        $avgSpeed = 8.33; // m/s ~ 30 km/h (city traffic)
        return (int) ($distance / $avgSpeed);
    }

    private function dispatchToDrivers(array $drivers, int $riderId): ?array
    {
        foreach ($drivers as $driver) {
            // Push notification driver app-a
            event(new \App\Events\RideRequested($driver['driver_id'], $riderId));

            // 15 saniyə gözlə
            $accepted = $this->waitForAcceptance($driver['driver_id'], $riderId, timeout: 15);
            if ($accepted) return $driver;
        }
        return null;
    }

    private function filterByRideType(array $candidates, string $type): array
    {
        $driverIds = array_column($candidates, 'driver_id');
        $eligibleIds = \DB::table('drivers')
            ->whereIn('id', $driverIds)
            ->where('ride_type', $type)
            ->where('status', 'online')
            ->pluck('id')
            ->toArray();

        return array_filter($candidates, fn($c) => in_array($c['driver_id'], $eligibleIds));
    }

    private function filterByAcceptanceRate(array $candidates): array
    {
        // Son 24 saatda acceptance rate >= 70%
        return array_filter($candidates, fn($c) => ($c['acceptance_rate'] ?? 1) >= 0.7);
    }

    private function waitForAcceptance(int $driverId, int $riderId, int $timeout): bool
    {
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            $status = Redis::get("ride_request:{$driverId}:{$riderId}:status");
            if ($status === 'accepted') return true;
            if ($status === 'declined') return false;
            usleep(500_000); // 500ms
        }
        return false;
    }
}
```

### Surge Pricing

```php
class SurgeService
{
    public function multiplierFor(float $lat, float $lng): float
    {
        $cell = $this->toH3Cell($lat, $lng);

        $demand = (int) Redis::get("surge:{$cell}:demand") ?: 1;
        $supply = (int) Redis::get("surge:{$cell}:supply") ?: 1;

        $ratio = $demand / max(1, $supply);

        return match (true) {
            $ratio < 1 => 1.0,
            $ratio < 2 => 1.2,
            $ratio < 3 => 1.5,
            $ratio < 5 => 2.0,
            default => min(3.0, $ratio * 0.7),
        };
    }

    public function incrementDemand(float $lat, float $lng): void
    {
        $cell = $this->toH3Cell($lat, $lng);
        Redis::incr("surge:{$cell}:demand");
        Redis::expire("surge:{$cell}:demand", 300); // 5 dəq window
    }

    private function toH3Cell(float $lat, float $lng): string
    {
        // Gerçəkdə H3 library. Sadələşdirmə üçün geohash
        return (new GeohashService())->encode($lat, $lng, 6);
    }
}
```

### Trip Management

```php
class TripService
{
    public function create(Rider $rider, array $pickup, array $dropoff, string $type): Trip
    {
        $fare = (new PricingService())->estimate($pickup, $dropoff, $type);
        $surge = (new SurgeService())->multiplierFor($pickup['lat'], $pickup['lng']);

        $trip = Trip::create([
            'rider_id' => $rider->id,
            'pickup_lat' => $pickup['lat'],
            'pickup_lng' => $pickup['lng'],
            'dropoff_lat' => $dropoff['lat'],
            'dropoff_lng' => $dropoff['lng'],
            'ride_type' => $type,
            'status' => 'requested',
            'estimated_fare' => $fare * $surge,
            'surge_multiplier' => $surge,
        ]);

        // Dispatch
        dispatch(new MatchDriverJob($trip->id));

        return $trip;
    }

    public function updateLocation(int $tripId, float $lat, float $lng): void
    {
        Redis::geoadd("trip:{$tripId}:route", $lng, $lat, now()->timestamp);

        // WebSocket-la rider-ə göndər
        broadcast(new \App\Events\DriverLocationUpdated($tripId, $lat, $lng));
    }

    public function complete(int $tripId): void
    {
        $trip = Trip::findOrFail($tripId);
        $trip->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_fare' => $this->calculateFinalFare($trip),
        ]);

        // Payment
        dispatch(new ChargeRiderJob($trip->id));
    }
}
```

## Real-World Nümunələr

- **Uber** — H3 geospatial index, DISCO dispatch, Ringpop (sharding), Apache Flink (real-time)
- **Lyft** — Envoy proxy (uzaq keçmişdə), Python/Go microservices
- **Didi Chuxing** — Çinin ən böyük ride-sharing, AI-driven matching
- **Grab** — Southeast Asia, mikroservislər + Kafka
- **Bolt** — Avropa, Go + gRPC backend
- **Google Maps API** — routing, ETA (çox şirkət istifadə edir)
- **Valhalla** — open-source routing (Mapbox)

## Interview Sualları

**1. Milyonlarla driver mövqeyini necə efficient saxlayarsan?**
Redis GEO (sorted set + geohash internally). `GEOADD` mövqeyi yazır, `GEORADIUS` N km radius-da sorğu verir. Million+ drivers üçün sharding — hər region ayrı Redis cluster. Uber H3 hexagonal grid istifadə edir.

**2. Quadtree vs Geohash vs H3?**
- **Quadtree**: dinamik data üçün pis (rebalancing çətin), static POI üçün yaxşı
- **Geohash**: sadə, string-based, prefix search asan; amma sərhəd problemləri (qonşu cell geohash tam fərqli ola bilər)
- **H3**: hexagonal, bərabər qonşu məsafəsi, 64-bit integer, Uber-in həlli

**3. Matching algorithm — nearest vs optimal?**
- **Nearest**: hər request müstəqil matched, sadə, lokal optimal
- **Global dispatch** (Uber): hər 2-3 saniyəyə batch matching, riders × drivers bipartite matching (Hungarian algorithm), globallı optimum
- Trade-off: latency vs efficiency

**4. Surge pricing necə işləyir, necə sui-istifadəsinin qarşısı alınır?**
Region-ları hex cell-lərə böl, hər cell-də demand/supply ratio hesabla. Ratio > threshold → multiplier artır. Sui-istifadə qarşısı:
- Smooth function (zərbə dəyişiklikləri olmasın)
- Transparent göstər (rider bilsin)
- Competitor monitoring
- Legal compliance (New York-da cap var)

**5. Real-time location update-lərini necə scale edirsən?**
- WebSocket / gRPC streaming
- Her N saniyədən (3-5s) bir update
- Kafka-ya yaz, downstream consumer-lar oxuyur
- Batching — 100 driver update-ni bir RPC-də
- Dead reckoning client-side (GPS itirsə interpolate)

**6. ETA necə hesablanır, niyə dəqiq deyil?**
- Routing engine (OSRM, Valhalla) — shortest path
- Historical travel times
- Real-time traffic (Google Maps API)
- ML model (deepETA — XGBoost)
Dəqiqliyi azaldan faktorlar: naməlum trafik hadisələri, driver davranışı, hava, GPS xətası.

**7. Driver iOS/Android app offline olsa ne olur?**
- Heartbeat hər 30 saniyə
- TTL geçsə Redis-dən sil
- Active trip varsa rider-ə "connection lost" göstər
- 2 dəq offline olsa trip cancel + refund option
- Reconnection sonra state sync

**8. Double-dispatch problemini necə həll edirsən?**
Driver bir neçə request-i eyni anda alırsa:
- Redis DistLock driver_id üzrə (hər driver bir anda yalnız 1 request)
- Request ID ilə idempotent — driver accept eləsə, başqa request cancel
- Optimistic locking (trip table-da version)

**9. Payment failure nə olur?**
- Trip complete olur, payment async
- Fail olsa retry 3 dəfə
- Hələ fail — user-ə outstanding balance əlavə et, gələn səfər tələb et
- Fraud detection — çox card fail olsa block

**10. Sistemi multi-region necə deploy edərsən?**
- Hər city öz region-unda (data locality)
- Driver/rider kəsişməsi city əsaslı
- Global services: auth, payment, billing
- Local services: location, matching, pricing
- Cross-region replication yalnız audit/analytics üçün

**11. Fraud detection?**
- GPS spoofing detection (sürət anomaliyaları)
- Collusion detection (eyni card + eyni cihaz)
- Pattern analysis (ML)
- Real-time scoring (Kafka Streams)
- Rate limiting (gündə max trip)

## Best Practices

1. **Geospatial index** — Redis GEO, H3, yaxud S2 — manual geohash yazma
2. **Real-time streaming** — Kafka + Flink/Spark, DB-ə hər location yazma
3. **Microservices** — matching, pricing, trip, payment ayrı deploy
4. **WebSocket/gRPC streaming** — HTTP polling yox
5. **Aggressive caching** — surge, supply/demand, ETA
6. **Monitor matching latency** — p99 200ms altda olsun
7. **Graceful degradation** — ML service fail olsa rule-based fallback
8. **Rate limit per device** — abuse qarşısı
9. **Payment retry strategiyası** — exponential backoff, 3 cəhd
10. **Comprehensive audit log** — hər trip-in hər mərhələsi
11. **A/B test** — matching, pricing algorithm-larını
12. **Regional sharding** — city/country əsasında
13. **PII məlumatlarını şifrələ** — GDPR, local regulations
14. **Driver onboarding avtomatlaşdır** — document verification
15. **Real-time dashboards** — demand/supply, surge areas, SLA
