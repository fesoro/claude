# Interval Problems (Senior)

## Konsept (Concept)

Interval problemleri [start, end] cutleri ile isliyir. Tipik emeliyyatlar: merge, insert, overlap yoxlama, minimum meeting rooms tapmaq.

```
Intervals: [1,3] [2,6] [8,10] [15,18]

Overlap yoxlama:
  [1,3] ve [2,6] overlap edir (2 <= 3)
  [1,3] ve [8,10] overlap etmir (8 > 3)

Merge:
  [1,3] + [2,6] = [1,6]
  Result: [1,6] [8,10] [15,18]

Timeline:
1---3
  2------6
              8---10
                        15---18
|--|------|  |---|      |-----|
 [1,  6  ]  [8,10]    [15, 18]
```

### Esas pattern-ler:
1. **Sort by start**: Merge, insert ucun
2. **Sort by end**: Activity selection, meeting rooms ucun
3. **Sweep line**: Event-leri zamana gore islet

## Nece Isleyir? (How does it work?)

### Merge Intervals:
```
Input: [[1,3],[2,6],[8,10],[15,18]]
Sort by start (artiq sirali).

Current: [1,3]
  [2,6]: 2 <= 3 -> merge -> [1,6]
  [8,10]: 8 > 6 -> yeni interval
  [15,18]: 15 > 10 -> yeni interval

Result: [[1,6],[8,10],[15,18]]
```

### Meeting Rooms II:
```
Intervals: [[0,30],[5,10],[15,20]]

Events (sweep line):
  0: start  -> rooms = 1
  5: start  -> rooms = 2
  10: end   -> rooms = 1
  15: start -> rooms = 2
  20: end   -> rooms = 1
  30: end   -> rooms = 0

Max rooms = 2
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Merge Intervals (LeetCode 56)
 * Time: O(n log n), Space: O(n)
 */
function mergeIntervals(array $intervals): array
{
    if (empty($intervals)) return [];

    usort($intervals, fn($a, $b) => $a[0] - $b[0]);

    $merged = [$intervals[0]];

    for ($i = 1; $i < count($intervals); $i++) {
        $last = &$merged[count($merged) - 1];

        if ($intervals[$i][0] <= $last[1]) {
            $last[1] = max($last[1], $intervals[$i][1]);
        } else {
            $merged[] = $intervals[$i];
        }
    }

    return $merged;
}

/**
 * Insert Interval (LeetCode 57)
 * Time: O(n), Space: O(n)
 */
function insertInterval(array $intervals, array $newInterval): array
{
    $result = [];
    $i = 0;
    $n = count($intervals);

    // Yeni interval-dan evvelkileri elave et
    while ($i < $n && $intervals[$i][1] < $newInterval[0]) {
        $result[] = $intervals[$i];
        $i++;
    }

    // Overlap eden interval-lari merge et
    while ($i < $n && $intervals[$i][0] <= $newInterval[1]) {
        $newInterval[0] = min($newInterval[0], $intervals[$i][0]);
        $newInterval[1] = max($newInterval[1], $intervals[$i][1]);
        $i++;
    }
    $result[] = $newInterval;

    // Qalan interval-lari elave et
    while ($i < $n) {
        $result[] = $intervals[$i];
        $i++;
    }

    return $result;
}

/**
 * Meeting Rooms (LeetCode 252)
 * Bir nefer butun iclasalara qatila bilermi?
 * Time: O(n log n), Space: O(1)
 */
function canAttendMeetings(array $intervals): bool
{
    usort($intervals, fn($a, $b) => $a[0] - $b[0]);

    for ($i = 1; $i < count($intervals); $i++) {
        if ($intervals[$i][0] < $intervals[$i - 1][1]) {
            return false;
        }
    }

    return true;
}

/**
 * Meeting Rooms II (LeetCode 253)
 * Minimum nece otaq lazimdir?
 * Time: O(n log n), Space: O(n)
 */
function minMeetingRooms(array $intervals): int
{
    $starts = [];
    $ends = [];

    foreach ($intervals as [$s, $e]) {
        $starts[] = $s;
        $ends[] = $e;
    }

    sort($starts);
    sort($ends);

    $rooms = 0;
    $maxRooms = 0;
    $s = 0;
    $e = 0;

    while ($s < count($starts)) {
        if ($starts[$s] < $ends[$e]) {
            $rooms++;
            $maxRooms = max($maxRooms, $rooms);
            $s++;
        } else {
            $rooms--;
            $e++;
        }
    }

    return $maxRooms;
}

/**
 * Non-overlapping Intervals (LeetCode 435)
 * Minimum nece interval silmek lazimdir ki overlap olmasin?
 * Time: O(n log n), Space: O(1)
 */
function eraseOverlapIntervals(array $intervals): int
{
    usort($intervals, fn($a, $b) => $a[1] - $b[1]); // End time-a gore

    $count = 0;
    $lastEnd = $intervals[0][1];

    for ($i = 1; $i < count($intervals); $i++) {
        if ($intervals[$i][0] < $lastEnd) {
            $count++; // Bu interval-i sil
        } else {
            $lastEnd = $intervals[$i][1];
        }
    }

    return $count;
}

/**
 * Interval List Intersections (LeetCode 986)
 * Iki siralanmis interval listinin kesismesi
 * Time: O(m + n), Space: O(m + n)
 */
function intervalIntersection(array $A, array $B): array
{
    $result = [];
    $i = $j = 0;

    while ($i < count($A) && $j < count($B)) {
        $start = max($A[$i][0], $B[$j][0]);
        $end = min($A[$i][1], $B[$j][1]);

        if ($start <= $end) {
            $result[] = [$start, $end];
        }

        if ($A[$i][1] < $B[$j][1]) {
            $i++;
        } else {
            $j++;
        }
    }

    return $result;
}

/**
 * Minimum Number of Arrows to Burst Balloons (LeetCode 452)
 * Time: O(n log n), Space: O(1)
 */
function findMinArrowShots(array $points): int
{
    usort($points, fn($a, $b) => $a[1] <=> $b[1]);

    $arrows = 1;
    $lastEnd = $points[0][1];

    for ($i = 1; $i < count($points); $i++) {
        if ($points[$i][0] > $lastEnd) {
            $arrows++;
            $lastEnd = $points[$i][1];
        }
    }

    return $arrows;
}

/**
 * Employee Free Time (LeetCode 759)
 * Butun ishcilerin bos vaxtlari
 * Time: O(n log n), Space: O(n)
 */
function employeeFreeTime(array $schedules): array
{
    // Butun interval-lari bir yerge topla
    $all = [];
    foreach ($schedules as $schedule) {
        foreach ($schedule as $interval) {
            $all[] = $interval;
        }
    }

    usort($all, fn($a, $b) => $a[0] - $b[0]);

    $result = [];
    $lastEnd = $all[0][1];

    for ($i = 1; $i < count($all); $i++) {
        if ($all[$i][0] > $lastEnd) {
            $result[] = [$lastEnd, $all[$i][0]]; // Bos vaxt
        }
        $lastEnd = max($lastEnd, $all[$i][1]);
    }

    return $result;
}

/**
 * Remove Covered Intervals (LeetCode 1288)
 * Time: O(n log n), Space: O(1)
 */
function removeCoveredIntervals(array $intervals): int
{
    // Start artan, eyni start-da end azalan sirala
    usort($intervals, function($a, $b) {
        return $a[0] === $b[0] ? $b[1] - $a[1] : $a[0] - $b[0];
    });

    $count = 0;
    $maxEnd = 0;

    foreach ($intervals as [$start, $end]) {
        if ($end > $maxEnd) {
            $count++;
            $maxEnd = $end;
        }
        // else: eyvallah interval bu interval-i cover edir
    }

    return $count;
}

// --- Test ---
$intervals = [[1,3],[2,6],[8,10],[15,18]];
echo "Merge: ";
foreach (mergeIntervals($intervals) as $i) echo "[{$i[0]},{$i[1]}] ";
echo "\n"; // [1,6] [8,10] [15,18]

$inserted = insertInterval([[1,3],[6,9]], [2,5]);
echo "Insert: ";
foreach ($inserted as $i) echo "[{$i[0]},{$i[1]}] ";
echo "\n"; // [1,5] [6,9]

echo "Can attend [[0,30],[5,10],[15,20]]: " .
    (canAttendMeetings([[0,30],[5,10],[15,20]]) ? 'yes' : 'no') . "\n"; // no

echo "Min rooms [[0,30],[5,10],[15,20]]: " .
    minMeetingRooms([[0,30],[5,10],[15,20]]) . "\n"; // 2

echo "Erase overlap [[1,2],[2,3],[3,4],[1,3]]: " .
    eraseOverlapIntervals([[1,2],[2,3],[3,4],[1,3]]) . "\n"; // 1

echo "Arrows [[10,16],[2,8],[1,6],[7,12]]: " .
    findMinArrowShots([[10,16],[2,8],[1,6],[7,12]]) . "\n"; // 2
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space |
|---------|------|-------|
| Merge Intervals | O(n log n) | O(n) |
| Insert Interval | O(n) | O(n) |
| Meeting Rooms | O(n log n) | O(1) |
| Meeting Rooms II | O(n log n) | O(n) |
| Non-overlapping | O(n log n) | O(1) |
| Interval Intersection | O(m + n) | O(m + n) |
| Arrows | O(n log n) | O(1) |

## Interview Suallari

1. **Iki interval overlap edirmi nece yoxlanir?**
   - `a.start <= b.end && b.start <= a.end`
   - Overlap ETMIR: `a.end < b.start || b.end < a.start`

2. **Niye end time-a gore siralayin bezi hallarda?**
   - Activity selection: en tez biten activity en cox bos vaxt buraxir
   - Non-overlapping: end time-a gore sirala, minimum silme
   - Start time-a gore: merge ucun daha uygun

3. **Meeting Rooms II nece O(n log n)-dir?**
   - Sort: O(n log n)
   - Sweep line: O(n) - start/end event-leri
   - Alternatov: min-heap ile de O(n log n)

4. **Sweep line algorithm nedir?**
   - Event-leri zamana gore sirala (start: +1, end: -1)
   - Soldan saga kecib aktiv interval sayini izle
   - Max aktiv = cavab (meeting rooms ucun)

## PHP/Laravel ile Elaqe

- **Scheduling**: Laravel ile goruse/event planlamasi
- **Booking systems**: Otaq/resurs reservation overlap yoxlama
- **Calendar**: Google Calendar kimi bos vaxt tapmaq
- **Time tracking**: Is saatlari merge/hesablama
- **Rate limiting**: Vaxt penceresi overlap yoxlama
- **Cron jobs**: Job overlap prevention
