# Binary Search Patterns (Middle)

## Konsept (Concept)

Binary search yalniz siralanmis massivde deyil, monotonic funksiyalarda da istifade olunur. "Answer on binary search" pattern-i cavabi binary search ile tapmagi bildirir: cavab araliginda search edib, serti yoxlayirsan.

```
Classic Binary Search:
  arr = [1, 3, 5, 7, 9, 11]  target=7
  Axtaris space: arr daxilinde

Answer Binary Search:
  "Minimum capacity to ship packages in D days"
  Axtaris space: [max(weight), sum(weights)]
  Sert: bu capacity ile D gunde gondere bilerikmi?

Pattern:
  lo = minimum possible answer
  hi = maximum possible answer
  while lo < hi:
      mid = (lo + hi) / 2
      if feasible(mid):
          hi = mid      // daha kicik cavab ola biler
      else:
          lo = mid + 1  // bu bes deyil, daha boyuk lazim
  return lo
```

## Nece Isleyir? (How does it work?)

### Search in Rotated Array:
```
[4, 5, 6, 7, 0, 1, 2]  target=0

Step 1: lo=0, hi=6, mid=3 -> arr[3]=7
  Left sorted [4,5,6,7], 0 < 4 -> saga get
  lo=4

Step 2: lo=4, hi=6, mid=5 -> arr[5]=1
  Right sorted [1,2], 0 < 1 -> sola get
  hi=4

Step 3: lo=4, hi=4, mid=4 -> arr[4]=0 -> TAPILDI!
```

### Capacity to Ship (answer binary search):
```
weights = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], days = 5

lo = max(weights) = 10
hi = sum(weights) = 55

mid = 32: 1+2+3+4+5+6+7=28 | 8+9+10=27 -> 2 days (< 5) -> hi=32
mid = 21: 1+2+3+4+5+6=21 | 7+8=15 | 9+10=19 -> 3 days -> hi=21
mid = 15: 1+2+3+4+5=15 | 6+7=13 | 8=8 | 9=9 | 10=10 -> 5 days -> hi=15
mid = 12: ... -> 7 days (> 5) -> lo=13
mid = 14: ... -> 5 days -> hi=14
mid = 13: ... -> 6 days (> 5) -> lo=14

Result: 15
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Search in Rotated Sorted Array (LeetCode 33)
 * Time: O(log n), Space: O(1)
 */
function searchRotated(array $nums, int $target): int
{
    $lo = 0;
    $hi = count($nums) - 1;

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);

        if ($nums[$mid] === $target) return $mid;

        // Sol teref sorted
        if ($nums[$lo] <= $nums[$mid]) {
            if ($target >= $nums[$lo] && $target < $nums[$mid]) {
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }
        // Sag teref sorted
        else {
            if ($target > $nums[$mid] && $target <= $nums[$hi]) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }
    }

    return -1;
}

/**
 * Find Peak Element (LeetCode 162)
 * Time: O(log n), Space: O(1)
 */
function findPeakElement(array $nums): int
{
    $lo = 0;
    $hi = count($nums) - 1;

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);

        if ($nums[$mid] > $nums[$mid + 1]) {
            $hi = $mid; // Peak solda ve ya mid-de
        } else {
            $lo = $mid + 1; // Peak sagda
        }
    }

    return $lo;
}

/**
 * Capacity To Ship Packages Within D Days (LeetCode 1011)
 * Answer binary search pattern
 * Time: O(n * log(sum)), Space: O(1)
 */
function shipWithinDays(array $weights, int $days): int
{
    $lo = max($weights);
    $hi = array_sum($weights);

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);

        if (canShip($weights, $days, $mid)) {
            $hi = $mid;
        } else {
            $lo = $mid + 1;
        }
    }

    return $lo;
}

function canShip(array $weights, int $days, int $capacity): bool
{
    $daysNeeded = 1;
    $currentLoad = 0;

    foreach ($weights as $w) {
        if ($currentLoad + $w > $capacity) {
            $daysNeeded++;
            $currentLoad = 0;
        }
        $currentLoad += $w;
    }

    return $daysNeeded <= $days;
}

/**
 * Koko Eating Bananas (LeetCode 875)
 * Time: O(n * log(max)), Space: O(1)
 */
function minEatingSpeed(array $piles, int $h): int
{
    $lo = 1;
    $hi = max($piles);

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);

        $hoursNeeded = 0;
        foreach ($piles as $pile) {
            $hoursNeeded += (int)ceil($pile / $mid);
        }

        if ($hoursNeeded <= $h) {
            $hi = $mid;
        } else {
            $lo = $mid + 1;
        }
    }

    return $lo;
}

/**
 * Find Minimum in Rotated Sorted Array (LeetCode 153)
 * Time: O(log n), Space: O(1)
 */
function findMin(array $nums): int
{
    $lo = 0;
    $hi = count($nums) - 1;

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($nums[$mid] > $nums[$hi]) {
            $lo = $mid + 1;
        } else {
            $hi = $mid;
        }
    }

    return $nums[$lo];
}

/**
 * Search a 2D Matrix (LeetCode 74)
 * Time: O(log(m*n)), Space: O(1)
 */
function searchMatrix(array $matrix, int $target): bool
{
    $m = count($matrix);
    $n = count($matrix[0]);
    $lo = 0;
    $hi = $m * $n - 1;

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        $val = $matrix[(int)($mid / $n)][$mid % $n];

        if ($val === $target) return true;
        if ($val < $target) $lo = $mid + 1;
        else $hi = $mid - 1;
    }

    return false;
}

/**
 * Split Array Largest Sum (LeetCode 410)
 * Time: O(n * log(sum)), Space: O(1)
 */
function splitArray(array $nums, int $k): int
{
    $lo = max($nums);
    $hi = array_sum($nums);

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);

        // mid max sum ile nece hisseye bolune biler?
        $splits = 1;
        $currentSum = 0;
        foreach ($nums as $num) {
            if ($currentSum + $num > $mid) {
                $splits++;
                $currentSum = 0;
            }
            $currentSum += $num;
        }

        if ($splits <= $k) {
            $hi = $mid;
        } else {
            $lo = $mid + 1;
        }
    }

    return $lo;
}

/**
 * Median of Two Sorted Arrays (LeetCode 4)
 * Time: O(log(min(m,n))), Space: O(1)
 */
function findMedianSortedArrays(array $nums1, array $nums2): float
{
    if (count($nums1) > count($nums2)) {
        return findMedianSortedArrays($nums2, $nums1);
    }

    $m = count($nums1);
    $n = count($nums2);
    $lo = 0;
    $hi = $m;

    while ($lo <= $hi) {
        $i = $lo + (int)(($hi - $lo) / 2);
        $j = (int)(($m + $n + 1) / 2) - $i;

        $leftMax1 = $i === 0 ? PHP_INT_MIN : $nums1[$i - 1];
        $rightMin1 = $i === $m ? PHP_INT_MAX : $nums1[$i];
        $leftMax2 = $j === 0 ? PHP_INT_MIN : $nums2[$j - 1];
        $rightMin2 = $j === $n ? PHP_INT_MAX : $nums2[$j];

        if ($leftMax1 <= $rightMin2 && $leftMax2 <= $rightMin1) {
            if (($m + $n) % 2 === 0) {
                return (max($leftMax1, $leftMax2) + min($rightMin1, $rightMin2)) / 2.0;
            }
            return max($leftMax1, $leftMax2);
        } elseif ($leftMax1 > $rightMin2) {
            $hi = $i - 1;
        } else {
            $lo = $i + 1;
        }
    }

    return 0.0;
}

/**
 * Find First and Last Position (LeetCode 34)
 */
function searchRange(array $nums, int $target): array
{
    return [leftBound($nums, $target), rightBound($nums, $target)];
}

function leftBound(array $nums, int $target): int
{
    $lo = 0;
    $hi = count($nums) - 1;
    $result = -1;
    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($nums[$mid] === $target) { $result = $mid; $hi = $mid - 1; }
        elseif ($nums[$mid] < $target) $lo = $mid + 1;
        else $hi = $mid - 1;
    }
    return $result;
}

function rightBound(array $nums, int $target): int
{
    $lo = 0;
    $hi = count($nums) - 1;
    $result = -1;
    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($nums[$mid] === $target) { $result = $mid; $lo = $mid + 1; }
        elseif ($nums[$mid] < $target) $lo = $mid + 1;
        else $hi = $mid - 1;
    }
    return $result;
}

// --- Test ---
echo "Rotated search [4,5,6,7,0,1,2] t=0: " . searchRotated([4,5,6,7,0,1,2], 0) . "\n"; // 4
echo "Peak element [1,2,3,1]: " . findPeakElement([1,2,3,1]) . "\n"; // 2
echo "Ship days: " . shipWithinDays([1,2,3,4,5,6,7,8,9,10], 5) . "\n"; // 15
echo "Koko bananas: " . minEatingSpeed([3,6,7,11], 8) . "\n"; // 4
echo "Find min rotated: " . findMin([3,4,5,1,2]) . "\n"; // 1
echo "Median: " . findMedianSortedArrays([1,3], [2]) . "\n"; // 2.0
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space |
|---------|------|-------|
| Search Rotated | O(log n) | O(1) |
| Find Peak | O(log n) | O(1) |
| Ship Capacity | O(n log S) | O(1) |
| Koko Bananas | O(n log M) | O(1) |
| Find Min Rotated | O(log n) | O(1) |
| Median Two Arrays | O(log min(m,n)) | O(1) |
| Split Array | O(n log S) | O(1) |

## Interview Suallari

1. **"Answer on binary search" nece taniyin?**
   - "Minimum/maximum deyer tap ki, sert odenir"
   - Monotonic feasibility: capacity artdiqca, feasible olmasi daha asandir
   - Search space: [min_possible, max_possible]

2. **Rotated sorted array-de nece binary search edin?**
   - Her zaman bir teref sorted olur
   - Sorted terefi tap, target oradadi mi yoxla
   - Degilse, o biri terefe kec

3. **`lo < hi` vs `lo <= hi` ferqi?**
   - `lo <= hi`: exact match axtarirsan, -1 qaytara bilersen
   - `lo < hi`: boundary/minimum axtarirsan, lo = hi olanda cavab tapilir
   - Answer BS adeten `lo < hi` istifade edir

4. **Binary search-de `lo = mid` vs `lo = mid + 1`?**
   - `lo = mid`: sonsuz loop riski var (`lo < hi` ile)
   - `lo = mid + 1`: safe, mid artiq istisna olunur
   - `hi = mid`: mid hele namized ola biler (minimum axtarirsan)

## PHP/Laravel ile Elaqe

- **Database queries**: BETWEEN optimize binary search istifade edir
- **Pagination**: Sehife ve offset hesablamasi
- **Rate limiting**: Optimal rate limit deyeri binary search ile tapmaq
- **Load testing**: Maximum throughput binary search ile tapmaq
- **Configuration tuning**: Optimal cache size, pool size tapmaq
