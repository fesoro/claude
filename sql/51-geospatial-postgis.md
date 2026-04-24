# Geospatial Databases (PostGIS, MySQL Spatial)

> **Seviyye:** Advanced ⭐⭐⭐

## Niye geo data adi DB-de pis ifade olunur?

Adi yanasma: `users.lat DECIMAL`, `users.lng DECIMAL`. Problem:

```sql
-- "5 km daxilinde olan istifadeciler" -- naive Euclidean
SELECT * FROM users 
WHERE SQRT(POWER(lat - 40.41, 2) + POWER(lng - 49.86, 2)) < 0.045;
-- 1) Yer yuvarlaqdir, Euclidean SEHV netice (xususile poles-de)
-- 2) Index istifade olunmur (full scan)
-- 3) Polygon (delivery zone) yoxlamaq mumkun deyil
```

Hell: **spatial database** — PostGIS, MySQL Spatial. Bunlarda:
- Yer kuresi geometriyasi
- Spatial index (R-tree, GiST)
- Spatial functions (distance, contains, intersect)

---

## PostGIS — PostgreSQL extension

```sql
-- Install (Ubuntu)
-- sudo apt install postgresql-15-postgis-3
CREATE EXTENSION postgis;
SELECT PostGIS_Version();
```

### GEOMETRY vs GEOGRAPHY

| Type | Sistem | Sureti | Doqiqlik |
|------|--------|--------|----------|
| `GEOMETRY` | Cartesian (flat plane) | Tez | Az areada doqiq |
| `GEOGRAPHY` | Spheroidal (Earth) | Yavas (3-5x) | Yer yuvarlaqligini nezere alir |

```sql
-- GEOGRAPHY (km, dunya seviyyesi)
CREATE TABLE places (
    id BIGSERIAL PRIMARY KEY,
    name TEXT,
    location GEOGRAPHY(POINT, 4326)
);

-- GEOMETRY (planar, kicik area)
CREATE TABLE city_zones (
    id BIGSERIAL PRIMARY KEY,
    boundary GEOMETRY(POLYGON, 4326)
);
```

---

## SRID ve WGS84

**SRID** (Spatial Reference System Identifier) — coordinate system kodu.

| SRID | Adi |
|------|-----|
| **4326** | WGS84 (GPS standard, lat/lng) |
| **3857** | Web Mercator (Google/OpenStreetMaps) |
| **2154** | RGF93 (France) |

```sql
-- Point yarat (lng, lat sirasi! standart bunda terskindir)
SELECT ST_SetSRID(ST_MakePoint(49.86, 40.41), 4326) AS baku;

-- WKT (Well-Known Text) format
SELECT ST_GeomFromText('POINT(49.86 40.41)', 4326);

-- WKB (Well-Known Binary) - daha kompakt

-- Conversion
SELECT ST_Transform(geom, 3857) FROM places;  -- 4326 -> 3857
```

---

## Geometry types

```sql
-- POINT
ST_MakePoint(lng, lat)              -- Baku centeri
'POINT(49.86 40.41)'

-- LINESTRING
'LINESTRING(0 0, 1 1, 2 2)'

-- POLYGON (ilk ve son point eyni!)
'POLYGON((0 0, 4 0, 4 4, 0 4, 0 0))'

-- MULTIPOLYGON (multi-island country)
'MULTIPOLYGON(((0 0, 1 0, 1 1, 0 0)), ((10 10, 11 10, 11 11, 10 10)))'
```

---

## Spatial functions (en cox istifade olunan)

```sql
-- 1. Distance (meter, GEOGRAPHY)
SELECT ST_Distance(
    'POINT(49.86 40.41)'::geography,  -- Baku
    'POINT(28.97 41.01)'::geography   -- Istanbul
);
-- 1737000 (1737 km)

-- 2. DWithin (radius daxilinde, INDEX-FRIENDLY!)
SELECT * FROM places
WHERE ST_DWithin(
    location,
    ST_MakePoint(49.86, 40.41)::geography,
    5000  -- 5 km
);

-- 3. Contains (polygon icinde nokte var?)
SELECT * FROM city_zones
WHERE ST_Contains(boundary, ST_MakePoint(49.86, 40.41)::geometry);

-- 4. Intersects (iki geometriya kesisirmi?)
SELECT * FROM delivery_zones a, delivery_zones b
WHERE a.id != b.id AND ST_Intersects(a.boundary, b.boundary);

-- 5. Buffer (etrafindaki area)
SELECT ST_Buffer(location::geometry, 0.01) FROM places;  -- ~1 km

-- 6. Area / Length
SELECT ST_Area(boundary::geography) FROM city_zones;     -- m²
SELECT ST_Length(route::geography) FROM tracks;          -- m

-- 7. Centroid (polygon-un mərkəzi)
SELECT ST_Centroid(boundary) FROM city_zones;

-- 8. ConvexHull (etrafini ehate edən polygon)
SELECT ST_ConvexHull(ST_Collect(location::geometry)) FROM places;
```

| Function | Niye lazim |
|----------|------------|
| `ST_Distance` | Iki nokte arasi mesafe (m) |
| `ST_DWithin` | Radius search (index istifade edir!) |
| `ST_Contains` / `ST_Within` | Polygon icinde nokte? (geofencing) |
| `ST_Intersects` | Kesisirmi? (delivery zones overlap?) |
| `ST_Buffer` | Etrafda area yarat (radius zone) |
| `ST_Length` | Yol/track uzunlugu |
| `ST_Area` | Polygon sahesi |
| `ST_Centroid` | Mərkəz nokte |

---

## Spatial indexes (GiST, SP-GiST)

```sql
-- GiST index (en gen yayilmis)
CREATE INDEX idx_places_location ON places USING GIST (location);

-- SP-GiST (point-only, daha tez bir nece halda)
CREATE INDEX idx_places_loc_sp ON places USING SPGIST (location);

-- BRIN (cox boyuk table-da, az doqiq)
CREATE INDEX idx_places_loc_brin ON places USING BRIN (location);
```

**Hansi function-lar index istifade edir?**
- `ST_DWithin(geom, point, dist)` — BELI
- `ST_Distance(geom, point) < dist` — XEYR (full scan!)
- `ST_Intersects`, `ST_Contains`, `ST_Within` — BELI

```sql
-- YANLIS (full scan)
SELECT * FROM places WHERE ST_Distance(location, my_point) < 5000;

-- DOGRU (index istifade)
SELECT * FROM places WHERE ST_DWithin(location, my_point, 5000);
```

---

## MySQL Spatial

MySQL 5.7+ POINT, POLYGON destekleyir. SRID `0` default = Cartesian. SRID `4326` = WGS84.

```sql
CREATE TABLE places (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    location POINT NOT NULL SRID 4326,
    SPATIAL INDEX idx_location (location)
);

-- Insert (lat, lng sirasi 4326-da; ST_GeomFromText -de POINT(lng lat))
INSERT INTO places (name, location) VALUES (
    'Fountains Square',
    ST_GeomFromText('POINT(40.4093 49.8671)', 4326)
);

-- Distance (meter, sphere)
SELECT name, ST_Distance_Sphere(
    location,
    ST_GeomFromText('POINT(40.41 49.86)', 4326)
) AS distance_m
FROM places
ORDER BY distance_m
LIMIT 10;

-- DWithin emulation
SELECT * FROM places
WHERE ST_Distance_Sphere(location, ST_GeomFromText('POINT(40.41 49.86)', 4326)) < 5000;
```

| Feature | PostGIS | MySQL Spatial |
|---------|---------|---------------|
| Index types | GiST, SP-GiST, BRIN | R-tree (only) |
| Functions | 1000+ (gen) | ~100 (basic) |
| 3D / Topology | Beli | Xeyr |
| Geography type | Beli (sphere) | Yox (manual sphere distance) |
| Performance | Cox tez | Yaxsi, amma az feature |
| Production usage | Uber, Foursquare | Discord initial |

PostGIS daha guclu ve standartdir. MySQL kifayet edir basic use case-de.

---

## Use case 1: Uber-like proximity (en yaxin sofer)

```sql
-- Hazirki driver location-lari
CREATE TABLE drivers (
    id BIGINT PRIMARY KEY,
    name TEXT,
    is_online BOOLEAN,
    location GEOGRAPHY(POINT, 4326),
    updated_at TIMESTAMP
);
CREATE INDEX idx_drivers_loc ON drivers USING GIST (location);

-- User-in 3 km daxilindeki en yaxin 10 sofer
SELECT 
    id, name,
    ST_Distance(location, ST_MakePoint(49.86, 40.41)::geography) AS dist_m
FROM drivers
WHERE is_online = true
  AND ST_DWithin(location, ST_MakePoint(49.86, 40.41)::geography, 3000)
ORDER BY location <-> ST_MakePoint(49.86, 40.41)::geography  -- KNN, tez
LIMIT 10;
```

`<->` operator — KNN (k-nearest neighbor), GiST index istifade edir, tez.

---

## Use case 2: Delivery zone (geofencing)

```sql
CREATE TABLE delivery_zones (
    id BIGINT PRIMARY KEY,
    restaurant_id BIGINT,
    name TEXT,
    boundary GEOGRAPHY(POLYGON, 4326)
);
CREATE INDEX idx_zones_boundary ON delivery_zones USING GIST (boundary);

-- "Bu addres restoranin delivery zonasinda?"
SELECT z.id, z.name
FROM delivery_zones z
WHERE z.restaurant_id = 42
  AND ST_Covers(z.boundary, ST_MakePoint($lng, $lat)::geography);
```

---

## Use case 3: Find nearby restaurants

```sql
CREATE TABLE restaurants (
    id BIGINT PRIMARY KEY,
    name TEXT,
    cuisine TEXT,
    rating NUMERIC(3,2),
    location GEOGRAPHY(POINT, 4326)
);
CREATE INDEX idx_rest_loc ON restaurants USING GIST (location);
CREATE INDEX idx_rest_cuisine ON restaurants (cuisine);

-- 2 km icinde 4+ rating-li sushi restorani
SELECT 
    id, name, rating,
    ST_Distance(location, ST_MakePoint(49.86, 40.41)::geography) AS dist
FROM restaurants
WHERE cuisine = 'sushi'
  AND rating >= 4.0
  AND ST_DWithin(location, ST_MakePoint(49.86, 40.41)::geography, 2000)
ORDER BY dist
LIMIT 20;
```

---

## Laravel packages

### `mstaack/laravel-postgis` (PostgreSQL)

```bash
composer require mstaack/laravel-postgis
```

```php
// Migration
use MStaack\LaravelPostgis\Schema\Blueprint;
use MStaack\LaravelPostgis\Schema\Builder;

Schema::create('places', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->point('location');           // GEOMETRY(POINT, 4326)
    $table->geography('boundary', 'POLYGON', 4326);
    $table->spatialIndex('location');
});

// Model
use MStaack\LaravelPostgis\Eloquent\PostgisTrait;
use MStaack\LaravelPostgis\Geometries\Point;

class Place extends Model {
    use PostgisTrait;
    
    protected $postgisFields = ['location'];
    protected $postgisTypes = [
        'location' => ['geomtype' => 'geography', 'srid' => 4326],
    ];
}

// Save
Place::create([
    'name' => 'Baku',
    'location' => new Point(40.4093, 49.8671),  // (lat, lng)
]);

// Query
$nearby = Place::whereRaw(
    'ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)',
    [49.86, 40.41, 5000]
)->get();
```

### `grimzy/laravel-mysql-spatial` (MySQL)

```bash
composer require grimzy/laravel-mysql-spatial
```

```php
use Grimzy\LaravelMysqlSpatial\Schema\Blueprint;
use Grimzy\LaravelMysqlSpatial\Eloquent\SpatialTrait;
use Grimzy\LaravelMysqlSpatial\Types\Point;

// Migration
Schema::create('places', function (Blueprint $table) {
    $table->id();
    $table->point('location', 4326);
    $table->spatialIndex('location');
});

// Model
class Place extends Model {
    use SpatialTrait;
    protected $spatialFields = ['location'];
}

// Insert
Place::create(['location' => new Point(40.41, 49.86, 4326)]);

// Distance (sphere, meters)
$nearest = Place::distanceSphere('location', new Point(40.41, 49.86), 5000)
    ->orderByDistance('location', new Point(40.41, 49.86))
    ->limit(10)
    ->get();
```

---

## GeoJSON I/O

```sql
-- PostgreSQL: GeoJSON-a cevir
SELECT ST_AsGeoJSON(location) FROM places WHERE id = 1;
-- {"type":"Point","coordinates":[49.86,40.41]}

-- GeoJSON-dan parse et
SELECT ST_GeomFromGeoJSON('{"type":"Point","coordinates":[49.86,40.41]}');
```

```php
// Laravel API response
class PlaceResource extends JsonResource {
    public function toArray($request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'geometry' => json_decode(
                DB::scalar("SELECT ST_AsGeoJSON(?::geography)", [$this->location])
            ),
        ];
    }
}
```

---

## Alternatives: H3, Geohash

Bu library-ler **integer-based encoding** istifade edir — adi DB-de ucuz indeksleyir, amma less precise.

| Method | Sample | Precision | Use case |
|--------|--------|-----------|----------|
| **Geohash** | `gcpvj0u6yj` | Variable (length-le) | Caching, sharding |
| **H3 (Uber)** | `8928308280fffff` | Hexagon hierarchy | Aggregation, regions |
| **S2 (Google)** | `89c25c000000000` | Cell hierarchy | Maps, indexing |

```php
// Geohash (kepacin/php-geohash)
$hash = Geohash::encode(40.41, 49.86, 8);  // gcpvj0u6
// Database: WHERE geohash LIKE 'gcpvj0u%'  (~150m precision)

// H3 (kerber/h3-php)
$cell = h3LatLngToCell(40.41, 49.86, 9);
// Aggregation: GROUP BY h3_cell -> heatmap
```

**Trade-off:** Geohash/H3 — sharding/caching ucun aladir, real distance/contains query-leri ucun PostGIS daha doqiqdir.

---

## Real example: Wolt-like restaurant search

```php
class RestaurantSearchService {
    public function search(float $lat, float $lng, int $radius = 5000, ?string $cuisine = null): Collection
    {
        return Restaurant::query()
            ->select([
                'restaurants.*',
                DB::raw("ST_Distance(location, ST_MakePoint(?, ?)::geography) AS distance_m"),
            ])
            ->addBinding([$lng, $lat], 'select')
            ->whereRaw(
                'ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)',
                [$lng, $lat, $radius]
            )
            ->whereHas('zones', function ($q) use ($lat, $lng) {
                $q->whereRaw(
                    'ST_Covers(boundary, ST_MakePoint(?, ?)::geography)',
                    [$lng, $lat]
                );
            })
            ->when($cuisine, fn($q, $c) => $q->where('cuisine', $c))
            ->where('is_open', true)
            ->orderBy('distance_m')
            ->limit(50)
            ->get();
    }
}
```

---

## Interview suallari

**Q: Naive lat/lng saxlamaq vs PostGIS arasinda esas ferq?**
A: Naive: 2 DECIMAL sutun, Euclidean distance — **sehv** (yer yuvarlaqdir), index istifade etmir, polygon yoxlanisi yoxdur. PostGIS: GEOGRAPHY type yer kuresini nezere alir, GiST index ile `ST_DWithin` tez, `ST_Contains`/`ST_Intersects` ile geofencing/zone-lari yoxlamaq mumkundur, 1000+ funksiya var.

**Q: ST_Distance vs ST_DWithin — niye DWithin tovsiye olunur?**
A: `ST_Distance(...) < 5000` — full scan, hər row ucun distance hesablanir. `ST_DWithin(geom, point, 5000)` — index-friendly, **bounding box** ile evvelce filter edir, sonra deqiq distance. 1M row-da `ST_Distance` 10 san, `ST_DWithin` 50 ms. Hemise `ST_DWithin` istifade et.

**Q: GEOMETRY ile GEOGRAPHY arasinda ne vaxt hansini secersen?**
A: `GEOGRAPHY` — global app, dunya seviyyesinde (Uber, flight tracking). Doqiq, amma 3-5x yavas. `GEOMETRY` — kicik area, bir seher icinde, planar coordinate ile (CAD, urban planning). Tez. Cox app-da `GEOGRAPHY(POINT, 4326)` default secimdir — performance yaxsi, doqiqlik tam.

**Q: H3/Geohash niye lazimdir, eger PostGIS varsa?**
A: PostGIS doqiqdir, amma index B-tree ile sade JOIN/GROUP BY etmir. H3/Geohash integer ID-dir — adi B-tree index, sharding key, cache key kimi rahatdir. Use case: 1) **Heatmap** — `GROUP BY h3_cell` ile aggregation tez, 2) **Caching** — `Redis: nearby:gcpvj0u6` ile area-based cache, 3) **Sharding** — geography-based shard key. Hibrid yanasma populardir: PostGIS doqiq query, H3 aggregation/caching.

**Q: Laravel-de `ST_DWithin` query-ni Eloquent-de nece yazirsan?**
A: PostGIS function-lari raw lazimdir: `whereRaw('ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)', [$lng, $lat, $radius])`. Daha temiz ucun `mstaack/laravel-postgis` package istifade etmek olar — Point/Polygon class-lari, model-de `$postgisFields` deklarasiyasi. Distance ile sort: `addSelect(DB::raw('ST_Distance(...) AS dist'))->orderBy('dist')`. Spatial index migration-da `$table->spatialIndex('location')` yaratmaq vacibdir.
