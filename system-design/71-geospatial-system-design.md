# Geospatial System Design (Nearby Search)

## Nədir? (What is it?)

**Geospatial system** — lokasiya (lat, lng) əsaslı sorğuları effektiv cavablandıran sistemdir: "mənim 1 km radiusumda hansı restoranlar var?", "ən yaxın 5 sürücü kimdir?", "bu bbox daxilində neçə POI mövcuddur?". Uber, DoorDash, Yelp, Google Maps, Airbnb — hamısı bu problemi həll edir.

Ride-sharing (fayl 37) və food-delivery (fayl 64) sistemləri bu primitive-ləri istifadə edir, amma bu fayl **indexing strukturlarına** (geohash, quadtree, R-tree, S2, H3) və **verilənlər bazası seçiminə** (PostGIS, Redis GEO, Mongo 2dsphere) fokuslanır.

## Tələblər (Requirements)

### Funksional

1. **Radius search**: `(lat, lng, radius)` → bütün yaxın point-lər
2. **K-nearest neighbors (kNN)**: `(lat, lng, k)` → ən yaxın k obyekt
3. **Bounding box query**: `(min_lat, max_lat, min_lng, max_lng)` → xəritə viewport üçün
4. **Fast updates**: hərəkət edən obyektlər (sürücü, istifadəçi) saniyədə 1 dəfə yeniləyir
5. **Secondary filters**: geo + biznes predicate (açıq restoran, rating ≥ 4, cuisine = "sushi")

### Non-functional

- **Latency**: p99 < 20ms nearby search üçün
- **Throughput**: 10k-100k QPS pik saatda
- **Write rate**: driver location — 30k drivers × 1/5s = **6k writes/sec**
- **Accuracy**: 100m daxilində nəticə yetərlidir (consumer app), ölçü üçün daha dəqiq (billing)
- **Consistency**: eventual OK (3-5s stale driver konumu qəbul oluna bilər)

## Problemin Təbiəti (Challenges)

- **Sferik yer**: Dünya kürəvidir; flat (x,y) hesablama qısa məsafələrdə işləyir, uzunda yox
- **Density hotspots**: NYC-də 1 km² sahədə 10k POI, səhrada 0 — naive grid həll etmir
- **Moving objects**: sürücü koordinatları hər 5 saniyədə dəyişir — B-tree index write-amplification verir
- **Pole distortion**: Mercator projection polyuslarda uzanır, uniform grid orada pozulur
- **Scale**: milyardlarla point, minlərlə QPS, globally distributed

## Naive Yanaşma (Why SQL scan fails)

```sql
-- O(N) full table scan, index-siz
SELECT * FROM places
WHERE SQRT(POW(lat - :userLat, 2) + POW(lng - :userLng, 2)) < 0.01;
```

- B-tree index lat üzrə varsa belə, funksiya tətbiq olunduqda istifadə olunmur
- 10M point → hər query 10M row oxunur
- Flat Euclidean məsafəsi sferik yer üçün düzgün deyil
- **Nəticə**: 1-2 saniyə per query, ölçümlənmir

Düzgün cavab — space-i **hierarchical index**-lə bölmək.

## 1. Geohash

Geohash `(lat, lng)` cütünü **base32 string**-ə çevirir. Alqoritm:
1. Lat bit-i ilə lng bit-i növbələşdirilir (interleave)
2. Nəticə bit stream base32 (0-9, b-h, j-k, m-n, p-z) kodlanır
3. String uzunluğu → precision (daha uzun = daha kiçik cell)

```
Precision  Cell size            Misal
  4        39km × 20km          "u9tc"    (şəhər)
  6        1.2km × 0.6km        "u9tcgq"  (məhəllə)
  7        153m × 153m          "u9tcgqk" (küçə bloku)
  8        38m × 19m            "u9tcgqkf"(bina)
```

**Əsas xüsusiyyət**: yaxın nöqtələr çox vaxt ortaq prefix bölüşür.
```
Restaurant A: "u9tcgqkf"
Restaurant B: "u9tcgqke"  ← eyni 7-char prefix "u9tcgqk"
```

### Geohash Grid (visual)

```
        lng
    ──────────►
    ┌──┬──┬──┬──┐
  ▲ │bp│c0│c1│c4│
  │ ├──┼──┼──┼──┤
lat │bn│bn│c2│c5│
  │ ├──┼──┼──┼──┤
    │bk│bs│c3│c6│
    └──┴──┴──┴──┘

Query (X):
      ┌─────┐
      │  X  │  bbox radius
      └─────┘
Target cell + 8 neighbors ("Moore neighborhood")
→ scan: SELECT * WHERE geohash LIKE 'u9tcgq%'
        AND geohash IN (center, N, NE, E, SE, S, SW, W, NW)
```

### Sərhəd problemi (Edge problem)

İki nöqtə 1m aralı ola bilər, amma fərqli cell-lərdə → fərqli prefix. **Həll**: 8 qonşu cell-i də scan et (9 cell total), sonra dəqiq məsafə filter.

Redis `GEOADD` daxilində 52-bit geohash saxlayır, sorted set üzərində `ZRANGEBYSCORE`-a çevrilir — Laravel nümunəsi aşağıda.

## 2. Quadtree

**Quadtree** — space-i recursive olaraq 4 kvadranta bölür. Bir leaf node-da point sayı threshold-u aşanda, leaf 4 child-a split olunur.

```
┌────────────────────┐        ┌─────────┬──────────┐
│                    │        │    ·    │  ·    ·  │
│    ·       ·       │        │         │      ·   │
│                    │   →    ├────┬────┼──────────┤
│       ·    ·   ·   │        │ ·  │·  ·│  · · ·   │
│   ·                │        │    │    │     ·    │
└────────────────────┘        └────┴────┴──────────┘
  Split when count > threshold        NW / NE / SW / SE
```

### Range query (bbox)

```
function query(node, bbox):
    if not node.bounds.intersects(bbox):
        return []
    if node.isLeaf():
        return [p for p in node.points if bbox.contains(p)]
    results = []
    for child in node.children:
        results += query(child, bbox)
    return results
```

Average kompleksiyya **O(log N)** balanced data üçün; worst case (degenerate) O(N). Dense regionlarda avtomatik dərin, sparse regionlarda dayaz — density hotspot problemini həll edir.

**Harada**: Google Maps daxili, PostGIS köhnə versiyaları, oyunlarda collision detection.

## 3. R-tree

R-tree hər node-un **MBR (Minimum Bounding Rectangle)**-i saxlayır, child MBR-lər parent-in içində olur. B-tree-yə oxşar balanced-dir.

```
Root MBR ──► [Child1 MBR] [Child2 MBR] [Child3 MBR]
                │              │            │
                ▼              ▼            ▼
             points         points       points
```

- **Point olmayan geometries** üçün güclüdür: polygon (şəhər sərhədi), line (yol), circle
- Insert/delete MBR-ləri yenidən balansla
- **PostGIS GiST index** R-tree-ni istifadə edir
- MySQL `SPATIAL INDEX` də R-tree variantı (R-tree with quadratic split)

### PostGIS nümunəsi

```sql
CREATE TABLE places (
    id BIGSERIAL PRIMARY KEY,
    name TEXT,
    location GEOGRAPHY(POINT, 4326),
    rating REAL,
    is_open BOOLEAN
);

CREATE INDEX idx_places_loc ON places USING GIST(location);
CREATE INDEX idx_places_filter ON places (is_open, rating);

-- 500m radiusda açıq və rating >= 4 olan yerlər
SELECT id, name, ST_Distance(location, :userPoint) AS dist_m
FROM places
WHERE ST_DWithin(location, :userPoint, 500)
  AND is_open = TRUE
  AND rating >= 4
ORDER BY location <-> :userPoint  -- KNN, GiST index istifadə edir
LIMIT 20;
```

`ST_DWithin` R-tree candidate list-i alır, sonra haversine dəqiqlik filter edir.

## 4. S2 (Google)

**S2** — sferi 6 üzlü kubun içinə yazır, hər üzü 30 səviyyə hierarchical quadtree-yə bölür. Cell-lər 64-bit integer ID alır, yaxın cell-lər yaxın ID-lər (Hilbert curve ordering). Sferik — polyus distortion yoxdur. Hierarchical — zoom in/out sadə prefix ilə. Integer comparison çox sürətlidir. İstifadə: Google Maps, Foursquare Pilgrim, CockroachDB geospatial.

## 5. H3 (Uber)

**H3** hexagonal cell-lər istifadə edir — hər hex-in 6 qonşusu **uniform məsafədə**-dir (kvadrat cell-lərdə diagonal qonşu uzaq olur).

```
    ⬡ ⬡ ⬡          Kvadrat:  N = 1.0,  NE = 1.41 (non-uniform)
   ⬡ ⬡ ⬡ ⬡         Hex:      hər qonşu = 1.0 (uniform)
    ⬡ ⬡ ⬡
```

- **Ring query**: "bu cell-dən 2-ring uzaqlıqda olanlar" — sadə funksiya (`h3_kRing`)
- **16 resolution səviyyəsi** (R0 = 4.25M km², R9 ≈ 0.1 km², R15 = 0.9 m²)
- Uber dispatch, surge pricing, supply/demand heatmap-larında əsas indexing

## 6. Verilənlər Bazası Seçimi (Database Options)

| Database | Index | Use case | Throughput |
|----------|-------|----------|------------|
| **PostGIS** | GiST (R-tree) | OGC-compliant, polygon + point, analytics | ~5-10k QPS |
| **MySQL Spatial** | R-tree | Basic geo, existing MySQL stack | ~3-5k QPS |
| **MongoDB 2dsphere** | B-tree on geohash | Flexible schema, mobile apps | ~10-20k QPS |
| **Elasticsearch geo_point** | BKD tree | Full-text + geo birlikdə | ~10k QPS |
| **Redis GEO** | Sorted set (geohash) | Hot moving objects, in-memory | **100k+ QPS** |
| **DynamoDB geohash** | Partition key prefix | Serverless, AWS-native | Pay per RCU |

MongoDB: `db.places.createIndex({location: "2dsphere"})` + `$near` / `$geoWithin` query-ləri. Elasticsearch: `geo_point` field + `geo_distance` filter. Hər iki halda underlying structure BKD / geohash əsaslıdır.

## Hərəkət Edən Obyektlər (Moving Objects)

Driver location 5 saniyədə bir yenilənir — bu **6k writes/sec** (30k online driver üçün). SQL indexing write-heavy yük üçün uyğun deyil (write amplification, VACUUM cost).

**Standard pattern**:
```
Driver app ──► Ingest gateway ──► Redis GEO (hot, current state)
                                │
                                └──► Kafka ──► ClickHouse (history, billing)
```

- **Redis GEO**: in-memory, O(log N) read/write, ~100k QPS per node
- **Kafka**: append-only log, replay-mumkun
- **ClickHouse / TimescaleDB**: columnar, analytics üçün

## Məsafə Riyaziyyatı (Distance Math)

### Flat Earth (kiçik məsafələr, <10 km)

```
dx = (lng2 - lng1) × cos(lat) × 111320
dy = (lat2 - lat1) × 110540
dist = sqrt(dx² + dy²)
```

Sürətli, amma uzun məsafədə səhv.

### Haversine (sphere, çox geniş istifadə)

```
a = sin²(Δlat/2) + cos(lat1) × cos(lat2) × sin²(Δlng/2)
c = 2 × atan2(√a, √(1-a))
d = R × c      // R = 6371 km
```

### Vincenty (ellipsoid, daha dəqiq, yavaş)

Vincenty ellipsoid formulu ~mm dəqiqlik verir, 10x yavaşdır. Geodesy / navigation üçün; interview-də adını bilmək kifayətdir. PostGIS `geography` type daxilən Vincenty-yə əsaslanan hesablama istifadə edir.

## Partitioning və Scale

Single Redis node ~100k QPS, amma qlobal sistemə bu azdır. **Shard by geohash prefix**:

```
shard_key = substr(geohash, 0, 3)   // ~150km × 150km cell
shards:
  "9q8" → us-west-1 cluster
  "dr5" → us-east-1 cluster
  "u09" → eu-west-1 cluster
```

- **Cross-region query**: nadir (şəhər sərhədində) — broadcast to overlapping shards
- **Hotspot mitigation**: NYC (dr5ru) öz dedicated cluster-i, tək prefix scaled horizontally

## API Design

```
GET /api/v1/places/nearby
  ?lat=40.7128
  &lng=-74.0060
  &radius=500          // metr
  &limit=20
  &filters=cuisine:sushi,rating_gte:4,open_now:true
  &sort=distance       // və ya "rating", "popularity"

Response:
{
  "results": [
    { "id": "p_123", "name": "Sushi Roxx", "distance_m": 142, "rating": 4.6, ... },
    ...
  ],
  "next_cursor": "..."   // paginated
}
```

- Radius max 5 km (abuse prevention)
- Rate limit: 100 req/min per user
- Cache (viewport, zoom) sadə query-lər üçün 30s TTL

## Laravel Nümunəsi (Repository + Redis GEO + PostGIS)

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class NearbyPlacesRepository
{
    // Fast path — Redis GEO (hot restaurants, updated every 10 min)
    public function nearbyFromCache(float $lat, float $lng, int $radiusM, int $limit): array
    {
        return Redis::command('GEOSEARCH', [
            'places:hot',
            'FROMLONLAT', $lng, $lat,
            'BYRADIUS', $radiusM, 'm',
            'ASC', 'COUNT', $limit, 'WITHCOORD', 'WITHDIST',
        ]);
    }

    // Accurate path — PostGIS with filters
    public function nearbyFromDb(
        float $lat, float $lng, int $radiusM, int $limit,
        array $filters = []
    ): array {
        $query = DB::table('places')
            ->select(
                'id', 'name', 'rating',
                DB::raw("ST_Distance(location, ST_MakePoint($lng, $lat)::geography) AS dist_m")
            )
            ->whereRaw(
                "ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)",
                [$lng, $lat, $radiusM]
            );

        if (!empty($filters['open_now'])) {
            $query->where('is_open', true);
        }
        if (!empty($filters['rating_gte'])) {
            $query->where('rating', '>=', $filters['rating_gte']);
        }

        return $query->orderBy('dist_m')->limit($limit)->get()->toArray();
    }
}

// Driver location update (hot path)
class DriverLocationService
{
    public function update(int $driverId, float $lat, float $lng): void
    {
        Redis::command('GEOADD', ['drivers:online', $lng, $lat, "driver:$driverId"]);
        Redis::expire("driver:$driverId:seen", 30);  // offline detection

        // Fire-and-forget event for analytics pipeline
        event(new DriverLocationUpdated($driverId, $lat, $lng));
    }
}
```

PostGIS üçün: `clickbar/laravel-magellan` fluent builder, MongoDB üçün `jenssegers/mongodb` ilə `$near` — raw SQL da OK.

## Real Sistemlər (Real Systems)

- **Uber**: H3 hexagon indexing, regional shards, custom dispatch engine (Ringpop)
- **Google Maps**: S2 cells, PaxosDB + custom tile storage
- **Foursquare Pilgrim**: S2 + gözlənilən yer proqnozlaşdırma (movement prediction)
- **Yelp**: PostGIS + Elasticsearch hybrid (text + geo)
- **Tinder / Pokémon GO**: geohash bucket / S2 cell-based spawn və match grids

## Interview Sualları (Interview Q&A)

**S1: Geohash-in əsas problemi nədir?**
C: **Cell boundary**: iki yaxın nöqtə fərqli cell-lərdə ola bilər və prefix paylaşmaz. Həll — merkəz cell + 8 qonşunu scan etmək (Moore neighborhood), sonra dəqiq haversine məsafəsi ilə filter. Həmçinin geohash cell-ləri non-uniform ölçüdədir (polyusda daralır).

**S2: Quadtree vs R-tree fərqi?**
C: Quadtree — space-based bölmə (hər node 4 fixed kvadrant). R-tree — data-based (MBR children-ə uyğunlaşır, balanced). R-tree polygon/line üçün yaxşı, quadtree point-heavy workload üçün sadə və sürətli. PostGIS R-tree (GiST) istifadə edir.

**S3: Niyə Uber H3 seçib S2-ni yox?**
C: Hexagon-lar **uniform qonşu məsafəsi** verir — kvadratda diagonal qonşu 1.41x uzaqdır. Dispatch-də "2-ring içində driver axtar" kimi query-lər daha dəqiq nəticə verir. Heatmap vizualizasiya da daha təbii görünür. S2 Google ekosistemində mövcud idi, Uber öz problemi üçün H3 yazdı.

**S4: Driver location-ları niyə PostGIS-də saxlamırıq?**
C: 6k writes/sec GiST index-inə write amplification yaradır; VACUUM cost artır; read latency pik saatda degrade olur. **Redis GEO** in-memory sorted set üzərində geohash score-ları saxlayır, 100k+ QPS dayanıqlı. History üçün Kafka → ClickHouse.

**S5: 10 km radius daxilində açıq və 4+ rating olan sushi restoranı query-ni necə optimize edərdin?**
C: Iki addım: (1) GiST spatial index candidate list (10 km within) gətirir — bu ən selective filter deyil, 10 km-də minlərlə point var; (2) composite B-tree `(is_open, rating, cuisine)` və ya partial index `WHERE is_open = TRUE` qalan filter-ləri tətbiq edir. PostgreSQL planner `BitmapAnd` edir. Əgər cuisine çox selective olsa (az sushi restoranı), cuisine üzrə filter əvvəl tətbiq edilə bilər.

**S6: Haversine formulu qıcal ellipsoid üçün düzgün deyilmi?**
C: Haversine sfera fərz edir, ~0.3% error (yerin qütb yastılığından). Çox consumer app üçün bəsdir (100m-də 30cm səhv). Yüksək dəqiqlik (cargo tracking, geodesy) — Vincenty ellipsoid formulu, amma 10x slower. PostGIS `geography` type daxilən Vincenty istifadə edir.

**S7: Bounding box query ilə radius query-nin fərqi nədir?**
C: **Bbox** — min/max lat-lng rectangle, xəritə viewport üçün təbii (Google Maps pan/zoom). Index-də sürətli — hər iki oxda range scan. **Radius** — sferik dairə, daha təbii ("5 km daxilində"), amma hesablama baxımından dairə bbox-a çevrilir → candidate list alınır → dəqiq məsafə ilə filter (two-step refinement).

**S8: Qlobal sistemi necə shard edərdin?**
C: **Geohash prefix** ilə (2-3 char, ~150km cell). Hər shard regional cluster-ə mapped (us-west, eu-west). Request router user lat-lng-dən prefix hesablayır, düzgün shard-ə göndərir. **Cross-shard query** (sərhəddə) nadir — shard sərhədinin 5 km daxilində query-lər overlapping prefix-ləri də scan edir. Hotspot (NYC) tək prefix öz dedicated cluster-i olur.

## Best Practices

- **Redis GEO hot moving objects üçün**, PostGIS static data və analytics üçün — hybrid
- **Two-step refinement**: geo predicate ilə candidate, sonra dəqiq filter — həmişə
- **Index filter cardinality-sinə görə seç**: `ST_DWithin` az selective olsa, composite index əlavə et
- **Precision düzgün seç**: geohash-6 (1.2 km) restoran üçün, geohash-8 (38m) driver matching üçün
- **Neighbor scan unutma**: geohash/quadtree-də sərhəd problemi üçün 8 qonşunu da
- **Haversine default**, Vincenty yalnız dəqiqlik tələb olunan use case-də
- **Moving objects üçün TTL**: `drivers:online` 30s TTL ilə stale konumu təmizlə
- **Write path ayır**: hot (Redis) + durable (Kafka → warehouse) dual write
- **Shard geohash prefix ilə**, user lat-lng-dən direct route
- **Rate limit radius-u**: `radius <= 5000m`, abuse və expensive scan-ın qarşısını al
- **Cache viewport query-lərini** 30-60s TTL ilə — xəritə eyni ərazidə pan etməkdə təkrarlanır
- **Monitoring**: p99 latency per shard, hot shard-lar üçün re-split
- **Cross-reference**:
  - Fayl 37 — ride-sharing dispatch (Redis GEO real istifadə)
  - Fayl 64 — food delivery nearby (PostGIS + Redis hybrid)
  - Fayl 26 — data partitioning (geo shard strategiyası)
  - Fayl 49 — distributed cache (Redis scaling patterns)
