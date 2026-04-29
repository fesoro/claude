# Divide and Conquer (Senior)

## Konsept (Concept)

Divide and Conquer problemi kicik alt-problemlere bolur, her birini ayrica hell edir ve neticeleri birlesdirir. DP-den ferqi: alt-problemler overlap etmir.

```
D&C uclu addim:
1. Divide: Problemi kicik hisselere bol
2. Conquer: Her hisseni recursive hell et
3. Combine: Neticeleri birlesdir

Merge Sort numumesi:
[38, 27, 43, 3, 9, 82]
    /               \          DIVIDE
[38, 27, 43]    [3, 9, 82]
  /     \          /    \      DIVIDE
[38] [27,43]   [3,9]  [82]
      / \       / \
    [27][43]  [3] [9]
      \ /       \ /            CONQUER
    [27,43]    [3,9]
       \ /       \ /           COMBINE
 [27,38,43]   [3,9,82]
        \       /              COMBINE
  [3, 9, 27, 38, 43, 82]
```

### D&C vs DP:
| | Divide & Conquer | Dynamic Programming |
|---|---|---|
| Alt-problemler | Overlap etmir | Overlap edir |
| Yanasmaq | Top-down | Top-down ve ya Bottom-up |
| Memoization | Lazim deyil | Lazimdir |
| Misal | Merge Sort | Fibonacci |

## Implementasiya (Implementation)

```php
<?php

/**
 * Merge Sort - classic D&C
 * Time: O(n log n), Space: O(n)
 */
function mergeSort(array $arr): array
{
    if (count($arr) <= 1) return $arr;

    $mid = (int)(count($arr) / 2);
    $left = mergeSort(array_slice($arr, 0, $mid));
    $right = mergeSort(array_slice($arr, $mid));

    return merge($left, $right);
}

function merge(array $left, array $right): array
{
    $result = [];
    $i = $j = 0;
    while ($i < count($left) && $j < count($right)) {
        if ($left[$i] <= $right[$j]) $result[] = $left[$i++];
        else $result[] = $right[$j++];
    }
    while ($i < count($left)) $result[] = $left[$i++];
    while ($j < count($right)) $result[] = $right[$j++];
    return $result;
}

/**
 * Quick Sort - D&C with partitioning
 * Time: O(n log n) avg, O(n^2) worst, Space: O(log n)
 */
function quickSort(array &$arr, int $lo = 0, ?int $hi = null): void
{
    if ($hi === null) $hi = count($arr) - 1;
    if ($lo >= $hi) return;

    $pivot = partition($arr, $lo, $hi);
    quickSort($arr, $lo, $pivot - 1);
    quickSort($arr, $pivot + 1, $hi);
}

function partition(array &$arr, int $lo, int $hi): int
{
    $pivot = $arr[$hi];
    $i = $lo - 1;
    for ($j = $lo; $j < $hi; $j++) {
        if ($arr[$j] <= $pivot) {
            $i++;
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
    }
    [$arr[$i + 1], $arr[$hi]] = [$arr[$hi], $arr[$i + 1]];
    return $i + 1;
}

/**
 * Maximum Subarray - D&C approach (LeetCode 53)
 * Time: O(n log n), Space: O(log n)
 */
function maxSubArrayDC(array $nums, int $lo = 0, ?int $hi = null): int
{
    if ($hi === null) $hi = count($nums) - 1;
    if ($lo === $hi) return $nums[$lo];

    $mid = $lo + (int)(($hi - $lo) / 2);

    $leftMax = maxSubArrayDC($nums, $lo, $mid);
    $rightMax = maxSubArrayDC($nums, $mid + 1, $hi);

    // Cross-boundary max
    $leftSum = PHP_INT_MIN;
    $sum = 0;
    for ($i = $mid; $i >= $lo; $i--) {
        $sum += $nums[$i];
        $leftSum = max($leftSum, $sum);
    }

    $rightSum = PHP_INT_MIN;
    $sum = 0;
    for ($i = $mid + 1; $i <= $hi; $i++) {
        $sum += $nums[$i];
        $rightSum = max($rightSum, $sum);
    }

    return max($leftMax, $rightMax, $leftSum + $rightSum);
}

/**
 * Closest Pair of Points
 * Time: O(n log n), Space: O(n)
 */
function closestPair(array $points): float
{
    usort($points, fn($a, $b) => $a[0] - $b[0]);
    return closestPairRec($points, 0, count($points) - 1);
}

function closestPairRec(array &$points, int $lo, int $hi): float
{
    if ($hi - $lo < 3) {
        // Brute force kicik set ucun
        $minDist = PHP_FLOAT_MAX;
        for ($i = $lo; $i <= $hi; $i++) {
            for ($j = $i + 1; $j <= $hi; $j++) {
                $d = distance($points[$i], $points[$j]);
                $minDist = min($minDist, $d);
            }
        }
        return $minDist;
    }

    $mid = $lo + (int)(($hi - $lo) / 2);
    $midX = $points[$mid][0];

    $d = min(
        closestPairRec($points, $lo, $mid),
        closestPairRec($points, $mid + 1, $hi)
    );

    // Strip: midX-den d mesafedeki noqteler
    $strip = [];
    for ($i = $lo; $i <= $hi; $i++) {
        if (abs($points[$i][0] - $midX) < $d) {
            $strip[] = $points[$i];
        }
    }

    usort($strip, fn($a, $b) => $a[1] - $b[1]);

    for ($i = 0; $i < count($strip); $i++) {
        for ($j = $i + 1; $j < count($strip) && ($strip[$j][1] - $strip[$i][1]) < $d; $j++) {
            $d = min($d, distance($strip[$i], $strip[$j]));
        }
    }

    return $d;
}

function distance(array $p1, array $p2): float
{
    return sqrt(($p1[0] - $p2[0]) ** 2 + ($p1[1] - $p2[1]) ** 2);
}

/**
 * Power function - D&C
 * Time: O(log n), Space: O(log n)
 */
function power(float $base, int $exp): float
{
    if ($exp === 0) return 1;
    if ($exp < 0) return 1 / power($base, -$exp);

    if ($exp % 2 === 0) {
        $half = power($base, $exp / 2);
        return $half * $half;
    }
    return $base * power($base, $exp - 1);
}

/**
 * Count Inversions (merge sort variant)
 * Time: O(n log n), Space: O(n)
 */
function countInversions(array $arr): array
{
    if (count($arr) <= 1) return ['sorted' => $arr, 'count' => 0];

    $mid = (int)(count($arr) / 2);
    $left = countInversions(array_slice($arr, 0, $mid));
    $right = countInversions(array_slice($arr, $mid));

    $merged = mergeAndCount($left['sorted'], $right['sorted']);

    return [
        'sorted' => $merged['sorted'],
        'count' => $left['count'] + $right['count'] + $merged['count'],
    ];
}

function mergeAndCount(array $left, array $right): array
{
    $result = [];
    $count = 0;
    $i = $j = 0;

    while ($i < count($left) && $j < count($right)) {
        if ($left[$i] <= $right[$j]) {
            $result[] = $left[$i++];
        } else {
            $result[] = $right[$j++];
            $count += count($left) - $i; // Sol terefdeki qalan butun elementler inversion
        }
    }

    while ($i < count($left)) $result[] = $left[$i++];
    while ($j < count($right)) $result[] = $right[$j++];

    return ['sorted' => $result, 'count' => $count];
}

/**
 * Majority Element - D&C (LeetCode 169)
 * Time: O(n log n), Space: O(log n)
 */
function majorityElement(array $nums, int $lo = 0, ?int $hi = null): int
{
    if ($hi === null) $hi = count($nums) - 1;
    if ($lo === $hi) return $nums[$lo];

    $mid = $lo + (int)(($hi - $lo) / 2);
    $left = majorityElement($nums, $lo, $mid);
    $right = majorityElement($nums, $mid + 1, $hi);

    if ($left === $right) return $left;

    $leftCount = countInRange($nums, $left, $lo, $hi);
    $rightCount = countInRange($nums, $right, $lo, $hi);

    return $leftCount > $rightCount ? $left : $right;
}

function countInRange(array $nums, int $target, int $lo, int $hi): int
{
    $count = 0;
    for ($i = $lo; $i <= $hi; $i++) {
        if ($nums[$i] === $target) $count++;
    }
    return $count;
}

/**
 * Strassen Matrix Multiplication concept (simplified)
 * Normal: O(n^3), Strassen: O(n^2.807)
 */
function matrixMultiply(array $A, array $B): array
{
    $n = count($A);
    $C = array_fill(0, $n, array_fill(0, $n, 0));

    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            for ($k = 0; $k < $n; $k++) {
                $C[$i][$j] += $A[$i][$k] * $B[$k][$j];
            }
        }
    }

    return $C;
}

// --- Test ---
echo "Merge sort: " . implode(', ', mergeSort([38, 27, 43, 3, 9, 82])) . "\n";

echo "Max subarray [-2,1,-3,4,-1,2,1,-5,4]: " . maxSubArrayDC([-2,1,-3,4,-1,2,1,-5,4]) . "\n"; // 6

echo "Power 2^10: " . power(2, 10) . "\n"; // 1024

$inv = countInversions([2, 4, 1, 3, 5]);
echo "Inversions in [2,4,1,3,5]: " . $inv['count'] . "\n"; // 3

$points = [[2,3],[12,30],[40,50],[5,1],[12,10],[3,4]];
echo "Closest pair: " . round(closestPair($points), 2) . "\n";

echo "Majority in [2,2,1,1,1,2,2]: " . majorityElement([2,2,1,1,1,2,2]) . "\n"; // 2
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space |
|---------|------|-------|
| Merge Sort | O(n log n) | O(n) |
| Quick Sort | O(n log n) avg | O(log n) |
| Max Subarray D&C | O(n log n) | O(log n) |
| Closest Pair | O(n log n) | O(n) |
| Power | O(log n) | O(log n) |
| Count Inversions | O(n log n) | O(n) |
| Strassen Multiply | O(n^2.807) | O(n^2) |

### Master Theorem:
```
T(n) = aT(n/b) + O(n^d)

a: alt-problem sayi, b: bolme faktoru, d: merge isi
- d < log_b(a): T(n) = O(n^(log_b a))
- d = log_b(a): T(n) = O(n^d log n)
- d > log_b(a): T(n) = O(n^d)

Merge Sort: T(n) = 2T(n/2) + O(n) -> a=2, b=2, d=1 -> O(n log n)
Binary Search: T(n) = T(n/2) + O(1) -> a=1, b=2, d=0 -> O(log n)
```

## Tipik Meseler (Common Problems)

### 1. Sort List (LeetCode 148) - Linked List Merge Sort
```php
<?php
function sortList(?ListNode $head): ?ListNode
{
    if ($head === null || $head->next === null) return $head;

    // Ortani tap (slow/fast pointer)
    $slow = $head;
    $fast = $head->next;
    while ($fast !== null && $fast->next !== null) {
        $slow = $slow->next;
        $fast = $fast->next->next;
    }

    $mid = $slow->next;
    $slow->next = null;

    $left = sortList($head);
    $right = sortList($mid);

    return mergeLists($left, $right);
}
```

### 2. Kth Largest (Quick Select)
```php
<?php
function findKthLargest(array $nums, int $k): int
{
    $target = count($nums) - $k;
    return quickSelect($nums, 0, count($nums) - 1, $target);
}

function quickSelect(array &$arr, int $lo, int $hi, int $k): int
{
    $pivot = partition($arr, $lo, $hi);
    if ($pivot === $k) return $arr[$pivot];
    if ($pivot < $k) return quickSelect($arr, $pivot + 1, $hi, $k);
    return quickSelect($arr, $lo, $pivot - 1, $k);
}
```

## Interview Suallari

1. **D&C ne vaxt istifade olunur?**
   - Problem tebii olaraq bolinir (array yariya, tree usaqlarina)
   - Alt-problemler musteqildir (overlap yoxdur)
   - Merge addimi effektiv ola biler

2. **Master Theorem nedir?**
   - T(n) = aT(n/b) + O(n^d) formalindaki recurrence-leri hell edir
   - a=alt-problem sayi, b=bolme faktoru, d=birlesdirme isi

3. **Merge sort vs Quick sort D&C cercivsinde?**
   - Merge: divide asan (ortadan bol), combine cetin (merge)
   - Quick: divide cetin (partition), combine asan (hec ne)

4. **Count inversions nece D&C ile hell olunur?**
   - Merge sort zamani sag array-den element evelce gelirse, inversion var
   - Sol array-de qalanlar hamisi inversion yaradir

## PHP/Laravel ile Elaqe

- **Parallel processing**: Data-ni bolusdurmek ve paralel islemek
- **MapReduce**: Boyuk data set-leri bol, islet, birlesdir
- **Image processing**: Boyuk seklin hisselerini ayrica islemek
- **Database sharding**: D&C prinsipi ile data bolgusu
- **Recursive API calls**: Tree structure data yukleme

---

## Praktik Tapşırıqlar

1. **LeetCode 23** — Merge K Sorted Lists (D&C ilə iterativ birləşdirmə)
2. **LeetCode 148** — Sort List (linked list üçün Merge Sort)
3. **LeetCode 215** — Kth Largest Element in Array (QuickSelect)
4. **LeetCode 53** — Maximum Subarray (D&C yanaşması ilə həll et)
5. **LeetCode 169** — Majority Element (D&C yanaşması vs Boyer-Moore)

### Step-by-step: Merge Sort trace

```
arr = [5, 2, 8, 1, 3]

Divide:
  [5, 2, 8]        [1, 3]
  [5] [2, 8]       [1] [3]
      [2] [8]

Merge (bottom-up):
  [2, 8]  →  [2, 5, 8]   +   [1, 3]
  [1, 2, 3, 5, 8] ✓

Hər merge addımda 2 sıralanmış hissəni birləşdiririk — O(n) işi O(log n) dəfə görürük.
```

---

## Əlaqəli Mövzular

- [07-recursion.md](07-recursion.md) — Rekursiya əsasları
- [13-sorting-advanced.md](13-sorting-advanced.md) — Merge Sort, Quick Sort (D&C nümunəsi)
- [23-dynamic-programming.md](23-dynamic-programming.md) — DP vs D&C (overlapping subproblems)
- [22-backtracking.md](22-backtracking.md) — Backtracking (D&C + pruning)
- [11-binary-search-patterns.md](11-binary-search-patterns.md) — Binary search (D&C-nin xüsusi halı)
