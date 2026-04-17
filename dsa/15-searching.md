# Searching (Axtaris)

## Konsept (Concept)

Searching massivde ve ya data structure-da mueyen elementi tapmaq prosesidir. Linear search siralanmamis data ucun, binary search siralanmis data ucun istifade olunur.

```
Linear Search: Sola-saga her elementi yoxla
[5, 3, 8, 1, 9, 2]  target=8
 ^  ^  ^ TAPILDI! index=2

Binary Search: Ortadan bol, yarini at
[1, 2, 3, 5, 8, 9]  target=8
         ^           mid=5, 8>5 saga get
               ^     mid=8, TAPILDI! index=4
```

## Nece Isleyir? (How does it work?)

### Binary Search:
```
arr = [1, 3, 5, 7, 9, 11, 13, 15]  target = 7

Step 1: lo=0, hi=7, mid=3 -> arr[3]=7 == target -> TAPILDI!

target = 11:
Step 1: lo=0, hi=7, mid=3 -> arr[3]=7 < 11 -> lo=4
Step 2: lo=4, hi=7, mid=5 -> arr[5]=11 == target -> TAPILDI!

target = 6:
Step 1: lo=0, hi=7, mid=3 -> arr[3]=7 > 6 -> hi=2
Step 2: lo=0, hi=2, mid=1 -> arr[1]=3 < 6 -> lo=2
Step 3: lo=2, hi=2, mid=2 -> arr[2]=5 < 6 -> lo=3
Step 4: lo=3 > hi=2 -> TAPILMADI
```

### Interpolation Search:
```
arr = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]  target = 70

Formulle pos hesabla (xetti interpolasiya):
pos = lo + ((target - arr[lo]) * (hi - lo)) / (arr[hi] - arr[lo])
pos = 0 + ((70 - 10) * (9 - 0)) / (100 - 10) = 0 + (60*9)/90 = 6

arr[6] = 70 == target -> TAPILDI! (1 step-de!)
Binary search 3 step alardi.
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Linear Search
 * Time: O(n), Space: O(1)
 */
function linearSearch(array $arr, int $target): int
{
    foreach ($arr as $i => $val) {
        if ($val === $target) return $i;
    }
    return -1;
}

/**
 * Binary Search - Iterative
 * Time: O(log n), Space: O(1)
 */
function binarySearch(array $arr, int $target): int
{
    $lo = 0;
    $hi = count($arr) - 1;

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2); // overflow-dan qacmaq ucun

        if ($arr[$mid] === $target) {
            return $mid;
        } elseif ($arr[$mid] < $target) {
            $lo = $mid + 1;
        } else {
            $hi = $mid - 1;
        }
    }

    return -1;
}

/**
 * Binary Search - Recursive
 * Time: O(log n), Space: O(log n) call stack
 */
function binarySearchRecursive(array $arr, int $target, int $lo = 0, ?int $hi = null): int
{
    if ($hi === null) $hi = count($arr) - 1;
    if ($lo > $hi) return -1;

    $mid = $lo + (int)(($hi - $lo) / 2);

    if ($arr[$mid] === $target) return $mid;
    if ($arr[$mid] < $target) return binarySearchRecursive($arr, $target, $mid + 1, $hi);
    return binarySearchRecursive($arr, $target, $lo, $mid - 1);
}

/**
 * Lower Bound - target-in ilk gorunme yeri (ve ya insert position)
 * Time: O(log n), Space: O(1)
 */
function lowerBound(array $arr, int $target): int
{
    $lo = 0;
    $hi = count($arr);

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($arr[$mid] < $target) {
            $lo = $mid + 1;
        } else {
            $hi = $mid;
        }
    }

    return $lo;
}

/**
 * Upper Bound - target-den boyuk ilk elementin yeri
 * Time: O(log n), Space: O(1)
 */
function upperBound(array $arr, int $target): int
{
    $lo = 0;
    $hi = count($arr);

    while ($lo < $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($arr[$mid] <= $target) {
            $lo = $mid + 1;
        } else {
            $hi = $mid;
        }
    }

    return $lo;
}

/**
 * Interpolation Search
 * Uniform distribution-da O(log log n)
 * Worst case O(n)
 */
function interpolationSearch(array $arr, int $target): int
{
    $lo = 0;
    $hi = count($arr) - 1;

    while ($lo <= $hi && $target >= $arr[$lo] && $target <= $arr[$hi]) {
        if ($lo === $hi) {
            return $arr[$lo] === $target ? $lo : -1;
        }

        $pos = $lo + (int)((($target - $arr[$lo]) * ($hi - $lo)) / ($arr[$hi] - $arr[$lo]));

        if ($arr[$pos] === $target) return $pos;
        if ($arr[$pos] < $target) $lo = $pos + 1;
        else $hi = $pos - 1;
    }

    return -1;
}

/**
 * Exponential Search
 * Siniri tap, sonra binary search
 * Time: O(log n), unbounded/infinite arrays ucun uygun
 */
function exponentialSearch(array $arr, int $target): int
{
    $n = count($arr);
    if ($n === 0) return -1;
    if ($arr[0] === $target) return 0;

    // Range tap
    $bound = 1;
    while ($bound < $n && $arr[$bound] <= $target) {
        $bound *= 2;
    }

    // Binary search bu range-de
    $lo = (int)($bound / 2);
    $hi = min($bound, $n - 1);

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($arr[$mid] === $target) return $mid;
        if ($arr[$mid] < $target) $lo = $mid + 1;
        else $hi = $mid - 1;
    }

    return -1;
}

/**
 * Find First and Last Position (LeetCode 34)
 * Time: O(log n)
 */
function searchRange(array $nums, int $target): array
{
    $first = findFirst($nums, $target);
    if ($first === -1) return [-1, -1];
    $last = findLast($nums, $target);
    return [$first, $last];
}

function findFirst(array $arr, int $target): int
{
    $lo = 0;
    $hi = count($arr) - 1;
    $result = -1;

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($arr[$mid] === $target) {
            $result = $mid;
            $hi = $mid - 1; // Sola davam et
        } elseif ($arr[$mid] < $target) {
            $lo = $mid + 1;
        } else {
            $hi = $mid - 1;
        }
    }

    return $result;
}

function findLast(array $arr, int $target): int
{
    $lo = 0;
    $hi = count($arr) - 1;
    $result = -1;

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($arr[$mid] === $target) {
            $result = $mid;
            $lo = $mid + 1; // Saga davam et
        } elseif ($arr[$mid] < $target) {
            $lo = $mid + 1;
        } else {
            $hi = $mid - 1;
        }
    }

    return $result;
}

/**
 * Ternary Search - Unimodal funksiya ucun peak tapmaq
 * Time: O(log n)
 */
function ternarySearch(array $arr, int $lo, int $hi): int
{
    while ($hi - $lo > 2) {
        $m1 = $lo + (int)(($hi - $lo) / 3);
        $m2 = $hi - (int)(($hi - $lo) / 3);

        if ($arr[$m1] < $arr[$m2]) {
            $lo = $m1 + 1;
        } else {
            $hi = $m2 - 1;
        }
    }

    $maxIdx = $lo;
    for ($i = $lo; $i <= $hi; $i++) {
        if ($arr[$i] > $arr[$maxIdx]) $maxIdx = $i;
    }
    return $maxIdx;
}

// --- Test ---
$arr = [1, 3, 5, 7, 9, 11, 13, 15];

echo "Linear search 7: " . linearSearch($arr, 7) . "\n";       // 3
echo "Binary search 7: " . binarySearch($arr, 7) . "\n";       // 3
echo "Interpolation 7: " . interpolationSearch($arr, 7) . "\n"; // 3
echo "Exponential 7:   " . exponentialSearch($arr, 7) . "\n";   // 3

echo "Lower bound 7:  " . lowerBound($arr, 7) . "\n";  // 3
echo "Upper bound 7:  " . upperBound($arr, 7) . "\n";  // 4
echo "Lower bound 6:  " . lowerBound($arr, 6) . "\n";  // 3 (insert position)

$dupl = [1, 2, 2, 2, 3, 4, 5];
echo "Search range 2: " . implode(',', searchRange($dupl, 2)) . "\n"; // 1,3
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Best | Average | Worst | Space | Qeyd |
|----------|------|---------|-------|-------|------|
| Linear | O(1) | O(n) | O(n) | O(1) | Unsorted data |
| Binary | O(1) | O(log n) | O(log n) | O(1) | Sorted lazim |
| Interpolation | O(1) | O(log log n) | O(n) | O(1) | Uniform distribution |
| Exponential | O(1) | O(log n) | O(log n) | O(1) | Unbounded arrays |
| Ternary | O(1) | O(log n) | O(log n) | O(1) | Unimodal funksiya |

## Tipik Meseler (Common Problems)

### 1. Search Insert Position (LeetCode 35)
```php
<?php
function searchInsert(array $nums, int $target): int
{
    return lowerBound($nums, $target);
}
```

### 2. Sqrt(x) (LeetCode 69)
```php
<?php
function mySqrt(int $x): int
{
    if ($x < 2) return $x;
    $lo = 1;
    $hi = (int)($x / 2);

    while ($lo <= $hi) {
        $mid = $lo + (int)(($hi - $lo) / 2);
        if ($mid <= (int)($x / $mid)) {
            $lo = $mid + 1;
        } else {
            $hi = $mid - 1;
        }
    }

    return $hi;
}
```

### 3. Find Minimum in Rotated Sorted Array (LeetCode 153)
```php
<?php
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
```

## Interview Suallari

1. **Binary search ne vaxt istifade olunur?**
   - Data siralanmis olmalidir
   - Random access lazimdir (array, linked list-de olmaz)
   - Monotonic funksiyalarda da istifade olunur (answer on binary search)

2. **Lower bound ve upper bound ferqi?**
   - Lower bound: target >= olan ilk index (insert position)
   - Upper bound: target > olan ilk index
   - Count of target = upper_bound - lower_bound

3. **Niye `mid = lo + (hi - lo) / 2` yaziriq?**
   - `(lo + hi) / 2` integer overflow ede biler
   - Bu formula overflow-dan qacir
   - PHP-de boyuk reqemlerle bu problem ola biler

4. **Binary search-in edge case-leri?**
   - Bos massiv, 1 elementli massiv
   - Target massivde yoxdur
   - Butun elementler eynidir
   - Duplicate elementler (first/last occurrence)

## PHP/Laravel ile Elaqe

- **`in_array()`**: Linear search, O(n)
- **`array_search()`**: Linear search, ilk tapilani qaytarir
- **Database INDEX**: B-tree index binary search prinsipi ile isleyir
- **Laravel Scout**: Full-text search, amma daxili binary search istifade edir
- **Pagination**: Binary search ile sehife nomresini tapmaq
- **Rate limiter**: Sorted timestamp array-de binary search
