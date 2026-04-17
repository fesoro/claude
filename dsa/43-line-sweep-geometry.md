# Line Sweep & Computational Geometry

## Konsept (Concept)

**Line Sweep** — həndəsi məsələlərdə məşhur texnikadır. "Virtual xətt" (adətən x və ya y oxuna paralel) **sol-dan sağa** hərəkət edir və **event-lər** (müəyyən x-də başlayan/bitən obyektlər) ardıcıl emal olunur.

### Computational Geometry əsas problemlər
- **Convex Hull** — verilmiş nöqtələrin ən kiçik konveks qabığı
- **Closest Pair of Points** — iki ən yaxın nöqtə
- **Segment Intersection** — çoxlu seqment arasında kəsişmələr
- **Rectangle Area Union** — çoxlu düzbucaqlının birləşmə sahəsi

### Faydalı faktlar
- **Orientation**: 3 nöqtənin istiqaməti (sol, sağ, kolinear) cross product ilə O(1)
- Kesişmə, paralellik, nöqtənin çoxbucaqlıda olması — bütün bunlar həndəsi primitivlərdir

## Necə İşləyir?

### Line Sweep ümumi sxem
```
1. Bütün event-ləri x (və ya y) koordinatı üzrə sort et
2. Active set (hazırda "live" obyektlər) saxla (balanced BST, multiset)
3. Hər event-də active set-i yenilə və cavab üçün hesabla
```

### Convex Hull (Andrew's monotone chain)
```
1. Nöqtələri (x, y) üzrə sort et
2. Yuxarı qabıq tap — sol-dan sağa
3. Aşağı qabıq tap — sağ-dan sola
4. Birləşdir (uç nöqtələr dublikat)
```

### Closest Pair (Divide & Conquer)
```
1. Nöqtələri x-ə görə sort et
2. Orta ilə iki yarıya böl
3. Hər yarıda rekursiya
4. "Strip" region-da brute force (7 nöqtədən çox yox)
```

## İmplementasiya (Implementation) - PHP

### 1. Geometric Primitives

```php
class Point {
    public function __construct(public float $x, public float $y) {}
}

// Cross product of vectors (b-a) × (c-a)
function cross(Point $a, Point $b, Point $c): float {
    return ($b->x - $a->x) * ($c->y - $a->y) - ($b->y - $a->y) * ($c->x - $a->x);
}

// Orientation: 1 = counterclockwise, -1 = clockwise, 0 = collinear
function orientation(Point $a, Point $b, Point $c): int {
    $v = cross($a, $b, $c);
    if ($v > 0) return 1;
    if ($v < 0) return -1;
    return 0;
}

function euclideanDist(Point $a, Point $b): float {
    return sqrt(($a->x - $b->x) ** 2 + ($a->y - $b->y) ** 2);
}
```

### 2. Convex Hull (Andrew's Monotone Chain)

```php
function convexHull(array $points): array {
    $n = count($points);
    if ($n < 3) return $points;
    usort($points, fn(Point $a, Point $b) =>
        $a->x <=> $b->x ?: $a->y <=> $b->y);

    // Lower hull
    $lower = [];
    foreach ($points as $p) {
        while (count($lower) >= 2
            && cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) {
            array_pop($lower);
        }
        $lower[] = $p;
    }

    // Upper hull
    $upper = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $p = $points[$i];
        while (count($upper) >= 2
            && cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) {
            array_pop($upper);
        }
        $upper[] = $p;
    }

    array_pop($lower);
    array_pop($upper);
    return array_merge($lower, $upper);
}
```

### 3. Convex Hull (Graham Scan)

```php
function grahamScan(array $points): array {
    $n = count($points);
    if ($n < 3) return $points;

    // Aşağı-ən sol nöqtəni tap
    $pivotIdx = 0;
    for ($i = 1; $i < $n; $i++) {
        if ($points[$i]->y < $points[$pivotIdx]->y
            || ($points[$i]->y === $points[$pivotIdx]->y
                && $points[$i]->x < $points[$pivotIdx]->x)) {
            $pivotIdx = $i;
        }
    }
    $pivot = $points[$pivotIdx];

    // Bucaq üzrə sort et
    usort($points, function (Point $a, Point $b) use ($pivot) {
        if ($a === $pivot) return -1;
        if ($b === $pivot) return 1;
        $o = orientation($pivot, $a, $b);
        if ($o === 0) return euclideanDist($pivot, $a) <=> euclideanDist($pivot, $b);
        return $o === 1 ? -1 : 1;
    });

    $stack = [$points[0], $points[1], $points[2]];
    for ($i = 3; $i < $n; $i++) {
        while (count($stack) > 1
            && orientation($stack[count($stack) - 2], end($stack), $points[$i]) !== 1) {
            array_pop($stack);
        }
        $stack[] = $points[$i];
    }
    return $stack;
}
```

### 4. Closest Pair of Points (Divide & Conquer)

```php
function closestPair(array $points): float {
    usort($points, fn(Point $a, Point $b) => $a->x <=> $b->x);
    return closestPairHelper($points, 0, count($points) - 1);
}

function closestPairHelper(array $pts, int $lo, int $hi): float {
    if ($hi - $lo < 3) {
        $min = PHP_FLOAT_MAX;
        for ($i = $lo; $i <= $hi; $i++) {
            for ($j = $i + 1; $j <= $hi; $j++) {
                $min = min($min, euclideanDist($pts[$i], $pts[$j]));
            }
        }
        return $min;
    }
    $mid = intdiv($lo + $hi, 2);
    $midX = $pts[$mid]->x;
    $dl = closestPairHelper($pts, $lo, $mid);
    $dr = closestPairHelper($pts, $mid + 1, $hi);
    $d = min($dl, $dr);

    // "Strip" region
    $strip = [];
    for ($i = $lo; $i <= $hi; $i++) {
        if (abs($pts[$i]->x - $midX) < $d) $strip[] = $pts[$i];
    }
    usort($strip, fn(Point $a, Point $b) => $a->y <=> $b->y);

    for ($i = 0; $i < count($strip); $i++) {
        for ($j = $i + 1; $j < count($strip) && ($strip[$j]->y - $strip[$i]->y) < $d; $j++) {
            $d = min($d, euclideanDist($strip[$i], $strip[$j]));
        }
    }
    return $d;
}
```

### 5. Segment Intersection (Bentley-Ottmann konseptual)

```php
// İki seqmentin kəsişib-kəsişmədiyini yoxla
class Segment {
    public function __construct(public Point $a, public Point $b) {}
}

function onSegment(Point $p, Point $q, Point $r): bool {
    return $q->x <= max($p->x, $r->x) && $q->x >= min($p->x, $r->x)
        && $q->y <= max($p->y, $r->y) && $q->y >= min($p->y, $r->y);
}

function segmentsIntersect(Segment $s1, Segment $s2): bool {
    $o1 = orientation($s1->a, $s1->b, $s2->a);
    $o2 = orientation($s1->a, $s1->b, $s2->b);
    $o3 = orientation($s2->a, $s2->b, $s1->a);
    $o4 = orientation($s2->a, $s2->b, $s1->b);

    if ($o1 !== $o2 && $o3 !== $o4) return true;
    // Collinear cases
    if ($o1 === 0 && onSegment($s1->a, $s2->a, $s1->b)) return true;
    if ($o2 === 0 && onSegment($s1->a, $s2->b, $s1->b)) return true;
    if ($o3 === 0 && onSegment($s2->a, $s1->a, $s2->b)) return true;
    if ($o4 === 0 && onSegment($s2->a, $s1->b, $s2->b)) return true;
    return false;
}
```

### 6. Line Sweep: Meeting Rooms / Max Overlapping Intervals

```php
// [start, end] intervalları. Eyni anda neçə overlap var?
function maxOverlappingIntervals(array $intervals): int {
    $events = [];
    foreach ($intervals as [$s, $e]) {
        $events[] = [$s, 1];   // +1 at start
        $events[] = [$e, -1];  // -1 at end
    }
    usort($events, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
    // Qeyd: eyni x-də end-i start-dan əvvəl emal etmək üçün −1 əvvəl gəlsin
    $max = $current = 0;
    foreach ($events as [$t, $delta]) {
        $current += $delta;
        $max = max($max, $current);
    }
    return $max;
}
```

### 7. Rectangle Area Union (Klassik Sweep)

```php
// Sadələşdirilmiş versiya — O(n² log n)
function rectangleUnionArea(array $rects): float {
    // $rects = [[x1, y1, x2, y2], ...]
    $xs = [];
    foreach ($rects as [$x1, , $x2, ]) { $xs[] = $x1; $xs[] = $x2; }
    $xs = array_values(array_unique($xs));
    sort($xs);

    $total = 0.0;
    for ($i = 0; $i < count($xs) - 1; $i++) {
        $stripX1 = $xs[$i];
        $stripX2 = $xs[$i + 1];
        $stripWidth = $stripX2 - $stripX1;

        // Bu strip-də hansı y intervalları var?
        $yIntervals = [];
        foreach ($rects as [$rx1, $ry1, $rx2, $ry2]) {
            if ($rx1 <= $stripX1 && $rx2 >= $stripX2) {
                $yIntervals[] = [$ry1, $ry2];
            }
        }
        // Birləşdir
        usort($yIntervals, fn($a, $b) => $a[0] <=> $b[0]);
        $yCoverage = 0;
        $currentEnd = -PHP_INT_MAX;
        foreach ($yIntervals as [$y1, $y2]) {
            if ($y1 > $currentEnd) {
                $yCoverage += $y2 - $y1;
                $currentEnd = $y2;
            } elseif ($y2 > $currentEnd) {
                $yCoverage += $y2 - $currentEnd;
                $currentEnd = $y2;
            }
        }
        $total += $stripWidth * $yCoverage;
    }
    return $total;
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Alqoritm | Time | Space |
|----------|------|-------|
| Convex Hull (Andrew) | O(n log n) | O(n) |
| Convex Hull (Graham) | O(n log n) | O(n) |
| Closest Pair (D&C) | O(n log n) | O(n) |
| Closest Pair (brute) | O(n²) | O(1) |
| Segment Intersection (2) | O(1) | O(1) |
| Bentley-Ottmann (n segs) | O((n+k) log n) | O(n) |
| Max Overlapping | O(n log n) | O(n) |
| Rectangle Union (basic) | O(n²) | O(n) |
| Rectangle Union (advanced) | O(n log n) | O(n) |

## Tipik Məsələlər (Common Problems)

### 1. LeetCode 252/253 — Meeting Rooms I/II
Yuxarıdakı `maxOverlappingIntervals`.

### 2. LeetCode 218 — The Skyline Problem
Line sweep + heap. Hər başlanğıc/bitmə event-i, max-heap-da active heights.

### 3. LeetCode 391 — Perfect Rectangle
Bütün kiçik düzbucaqlıların birləşməsi mükəmməl düzbucaqlı olurmu? — koordinat sweep + area checking.

### 4. Convex Hull Trick (DP Optimization)
DP transition-ı `dp[i] = min_{j < i}(dp[j] + a_j · x_i)` formatında — Convex Hull Trick ilə O(n log n) və ya O(n).

### 5. LeetCode 149 — Max Points on a Line
Hər nöqtə üçün digər nöqtələrə slope hesabla (hash map-də say). Ən tez-tez görünən slope → o xətt üzərindəki nöqtə sayı.

## Interview Sualları

**1. Line sweep nə vaxt istifadə olunur?**
Bir boyut üzrə hərəkət edən "xətt" ilə hər event-də aktiv set dəyişir və biz hər an üçün bir nəticə istəyirik. Klassik: meeting rooms, skyline, rectangle union, segment intersection.

**2. Convex hull nə üçün lazımdır?**
- Pattern recognition (shape analysis)
- Collision detection (sadə formalar)
- GIS (nöqtə toplularının sərhədi)
- DP optimization (Convex Hull Trick)

**3. Andrew vs Graham scan?**
- **Andrew**: x-ə görə sort, iki ayrı pass (lower + upper). Sadədir.
- **Graham**: polar angle-a görə sort (pivot-dan). Bir pass.
Andrew daha çox praktikada istifadə olunur.

**4. Closest pair-də O(n log n) necə əldə olunur?**
Naive O(n²). D&C: `T(n) = 2T(n/2) + O(n)` — strip-də yalnız 7 nöqtə yoxlanılır (geometric bound). Master theorem → O(n log n).

**5. Orientation testi niyə cross product ilə?**
`(b-a) × (c-a)` — işarə istiqaməti göstərir: müsbət → CCW, mənfi → CW, 0 → kolinear. Bölmə yoxdur, float dəqiqliyi daha yaxşıdır.

**6. Segment intersection çətinlikləri?**
Collinear overlapping case-ləri — nəzəri cəhətdən 0 orientation, amma həqiqi kəsişmə. Xüsusi yoxlama lazımdır.

**7. Bentley-Ottmann nə üçündür?**
N seqment arasında bütün kəsişmələri O((n+k) log n) zamanda tapır (k = kəsişmə sayı). Ən pis halda k = O(n²), amma adətən k << n² olur.

**8. Skyline problemi niyə klassikdir?**
Line sweep + priority queue kombinasiyasının ideal nümunəsidir. Hər bina üçün (start, height) və (end, height) event-ləri. Heap aktiv maksimum heigt-i saxlayır.

**9. Point in polygon — necə?**
Ray casting: nöqtədən bir yönə şüa at, neçə edge ilə kəsişdiyini say. Tək olsa — daxilində, cüt — xaricində. O(n).

**10. Float precision problemləri?**
Həndəsədə bəla mənbəyidir. Epsilon ilə müqayisə (`abs(a-b) < 1e-9`). Mümkün olanda **tam ədədli arithmetic** (cross product integer olanda işarə dəqiqdir) istifadə et.

## PHP/Laravel ilə Əlaqə

- **Geolocation**: PHP + PostgreSQL PostGIS ekstensiyası — həndəsi sorğular DB səviyyəsində.
- **Laravel + Google Maps**: nöqtə siyahısından convex hull çıxartmaq (delivery zone optimization).
- **Spatial index**: R-tree strukturu çox zaman SQL-də (PostGIS) və ya Elasticsearch-də işləyir.
- **Scheduling**: meeting rooms / calendar conflict — line sweep ilə həll olunur.
- **Image processing**: PHP-də GD/Imagick, convex hull ilə obyekt sərhədi.
- **Performance**: Böyük həndəsi sistemlər PHP-də deyil — C++ (CGAL), Python (Shapely), və ya Rust. PHP iş qatı bu kitabxanalara API-dir.
