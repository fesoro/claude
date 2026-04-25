# Sorting - Advanced (Middle)

## Konsept (Concept)

Advanced sorting algoritmleri O(n log n) ve ya O(n) vaxtda isleyir. Merge Sort, Quick Sort, Heap Sort comparison-based sort-lardir (en yaxsi O(n log n)). Counting, Radix, Bucket sort non-comparison sort-lardir ve O(n) ola biler.

```
Comparison-based lower bound: O(n log n)
  - Merge Sort: O(n log n) always
  - Quick Sort: O(n log n) average, O(n^2) worst
  - Heap Sort:  O(n log n) always

Non-comparison (integer/special):
  - Counting Sort: O(n + k)
  - Radix Sort:    O(d * (n + k))
  - Bucket Sort:   O(n + k) average
```

## Nece Isleyir? (How does it work?)

### Merge Sort:
```
[38, 27, 43, 3, 9, 82, 10]

Bolme (Divide):
[38, 27, 43, 3]    [9, 82, 10]
[38, 27] [43, 3]   [9, 82] [10]
[38][27] [43][3]   [9][82] [10]

Birlesdirme (Merge):
[27,38] [3,43]     [9,82] [10]
[3, 27, 38, 43]    [9, 10, 82]
[3, 9, 10, 27, 38, 43, 82]
```

### Quick Sort:
```
[38, 27, 43, 3, 9, 82, 10]  pivot=10

Partition: [3, 9] [10] [38, 27, 43, 82]

Left:  [3, 9] pivot=9 -> [3] [9]
Right: [38, 27, 43, 82] pivot=82 -> [38, 27, 43] [82]
       [38, 27, 43] pivot=43 -> [38, 27] [43]
       [38, 27] pivot=27 -> [27] [38]

Result: [3, 9, 10, 27, 38, 43, 82]
```

### Counting Sort:
```
[4, 2, 2, 8, 3, 3, 1]  max=8

Count array (index 0-8):
[0, 1, 2, 1, 1, 0, 0, 0, 1]

Cumulative:
[0, 1, 3, 4, 5, 5, 5, 5, 6]

Output: [1, 2, 2, 3, 3, 4, 8]
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Merge Sort
 * Divide and conquer: yarilarini sirala, birlesdir
 * Time: O(n log n) always, Space: O(n), Stable: Beli
 */
function mergeSort(array $arr): array
{
    $n = count($arr);
    if ($n <= 1) return $arr;

    $mid = (int)($n / 2);
    $left = mergeSort(array_slice($arr, 0, $mid));
    $right = mergeSort(array_slice($arr, $mid));

    return merge($left, $right);
}

function merge(array $left, array $right): array
{
    $result = [];
    $i = $j = 0;

    while ($i < count($left) && $j < count($right)) {
        if ($left[$i] <= $right[$j]) {
            $result[] = $left[$i++];
        } else {
            $result[] = $right[$j++];
        }
    }

    while ($i < count($left)) $result[] = $left[$i++];
    while ($j < count($right)) $result[] = $right[$j++];

    return $result;
}

/**
 * Quick Sort (Lomuto partition)
 * Pivot sec, kicikleri sola, boyukleri saga
 * Time: O(n log n) avg, O(n^2) worst, Space: O(log n), Stable: Xeyr
 */
function quickSort(array &$arr, int $low = 0, ?int $high = null): void
{
    if ($high === null) $high = count($arr) - 1;
    if ($low >= $high) return;

    $pivot = partition($arr, $low, $high);
    quickSort($arr, $low, $pivot - 1);
    quickSort($arr, $pivot + 1, $high);
}

function partition(array &$arr, int $low, int $high): int
{
    // Median of three pivot selection
    $mid = (int)(($low + $high) / 2);
    if ($arr[$mid] < $arr[$low]) [$arr[$low], $arr[$mid]] = [$arr[$mid], $arr[$low]];
    if ($arr[$high] < $arr[$low]) [$arr[$low], $arr[$high]] = [$arr[$high], $arr[$low]];
    if ($arr[$mid] < $arr[$high]) [$arr[$mid], $arr[$high]] = [$arr[$high], $arr[$mid]];
    $pivot = $arr[$high];

    $i = $low - 1;
    for ($j = $low; $j < $high; $j++) {
        if ($arr[$j] <= $pivot) {
            $i++;
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
    }
    [$arr[$i + 1], $arr[$high]] = [$arr[$high], $arr[$i + 1]];
    return $i + 1;
}

/**
 * Heap Sort
 * Max-heap qur, root-u cixar, heapify et
 * Time: O(n log n) always, Space: O(1), Stable: Xeyr
 */
function heapSort(array &$arr): void
{
    $n = count($arr);

    // Max-heap qur (bottom-up)
    for ($i = (int)($n / 2) - 1; $i >= 0; $i--) {
        heapify($arr, $n, $i);
    }

    // Bir-bir root-u sona qoy
    for ($i = $n - 1; $i > 0; $i--) {
        [$arr[0], $arr[$i]] = [$arr[$i], $arr[0]];
        heapify($arr, $i, 0);
    }
}

function heapify(array &$arr, int $n, int $i): void
{
    $largest = $i;
    $left = 2 * $i + 1;
    $right = 2 * $i + 2;

    if ($left < $n && $arr[$left] > $arr[$largest]) $largest = $left;
    if ($right < $n && $arr[$right] > $arr[$largest]) $largest = $right;

    if ($largest !== $i) {
        [$arr[$i], $arr[$largest]] = [$arr[$largest], $arr[$i]];
        heapify($arr, $n, $largest);
    }
}

/**
 * Counting Sort
 * Her elementin sayini hesabla, cumulative ile yerine qoy
 * Time: O(n + k), Space: O(k), Stable: Beli
 * k = max deyerin rangi
 */
function countingSort(array $arr): array
{
    if (empty($arr)) return [];

    $max = max($arr);
    $min = min($arr);
    $range = $max - $min + 1;

    $count = array_fill(0, $range, 0);
    $output = array_fill(0, count($arr), 0);

    // Count
    foreach ($arr as $val) {
        $count[$val - $min]++;
    }

    // Cumulative
    for ($i = 1; $i < $range; $i++) {
        $count[$i] += $count[$i - 1];
    }

    // Output (stable - sondan basla)
    for ($i = count($arr) - 1; $i >= 0; $i--) {
        $output[$count[$arr[$i] - $min] - 1] = $arr[$i];
        $count[$arr[$i] - $min]--;
    }

    return $output;
}

/**
 * Radix Sort
 * Her reqem pozisiyasi ucun counting sort istifade et
 * Time: O(d * (n + k)), Space: O(n + k), Stable: Beli
 */
function radixSort(array $arr): array
{
    if (empty($arr)) return [];

    $max = max($arr);

    for ($exp = 1; (int)($max / $exp) > 0; $exp *= 10) {
        $arr = countingSortByDigit($arr, $exp);
    }

    return $arr;
}

function countingSortByDigit(array $arr, int $exp): array
{
    $n = count($arr);
    $output = array_fill(0, $n, 0);
    $count = array_fill(0, 10, 0);

    foreach ($arr as $val) {
        $digit = (int)($val / $exp) % 10;
        $count[$digit]++;
    }

    for ($i = 1; $i < 10; $i++) {
        $count[$i] += $count[$i - 1];
    }

    for ($i = $n - 1; $i >= 0; $i--) {
        $digit = (int)($arr[$i] / $exp) % 10;
        $output[$count[$digit] - 1] = $arr[$i];
        $count[$digit]--;
    }

    return $output;
}

/**
 * Bucket Sort
 * Elementleri bucket-lere bol, her bucket-i sirala
 * Time: O(n + k) average, Space: O(n + k), Stable: Beli
 */
function bucketSort(array $arr, int $bucketCount = 10): array
{
    if (empty($arr)) return [];

    $max = max($arr);
    $min = min($arr);
    $range = $max - $min + 1;
    $bucketSize = max(1, (int)ceil($range / $bucketCount));

    $buckets = array_fill(0, $bucketCount, []);

    foreach ($arr as $val) {
        $idx = min((int)(($val - $min) / $bucketSize), $bucketCount - 1);
        $buckets[$idx][] = $val;
    }

    $result = [];
    foreach ($buckets as &$bucket) {
        sort($bucket); // Her bucket-i sirala
        foreach ($bucket as $val) {
            $result[] = $val;
        }
    }

    return $result;
}

// --- Test ---
$arr = [38, 27, 43, 3, 9, 82, 10];

echo "Merge Sort:    " . implode(', ', mergeSort($arr)) . "\n";

$qArr = $arr;
quickSort($qArr);
echo "Quick Sort:    " . implode(', ', $qArr) . "\n";

$hArr = $arr;
heapSort($hArr);
echo "Heap Sort:     " . implode(', ', $hArr) . "\n";

echo "Counting Sort: " . implode(', ', countingSort($arr)) . "\n";
echo "Radix Sort:    " . implode(', ', radixSort($arr)) . "\n";
echo "Bucket Sort:   " . implode(', ', bucketSort($arr)) . "\n";
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Best | Average | Worst | Space | Stable |
|----------|------|---------|-------|-------|--------|
| Merge Sort | O(n log n) | O(n log n) | O(n log n) | O(n) | Beli |
| Quick Sort | O(n log n) | O(n log n) | O(n^2) | O(log n) | Xeyr |
| Heap Sort | O(n log n) | O(n log n) | O(n log n) | O(1) | Xeyr |
| Counting | O(n+k) | O(n+k) | O(n+k) | O(k) | Beli |
| Radix | O(d(n+k)) | O(d(n+k)) | O(d(n+k)) | O(n+k) | Beli |
| Bucket | O(n+k) | O(n+k) | O(n^2) | O(n+k) | Beli |

## Tipik Meseler (Common Problems)

### 1. Kth Largest Element (LeetCode 215) - Quick Select
```php
<?php
function findKthLargest(array $nums, int $k): int
{
    $target = count($nums) - $k;
    return quickSelect($nums, 0, count($nums) - 1, $target);
}

function quickSelect(array &$arr, int $lo, int $hi, int $k): int
{
    $pivot = $arr[$hi];
    $i = $lo;
    for ($j = $lo; $j < $hi; $j++) {
        if ($arr[$j] <= $pivot) {
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
            $i++;
        }
    }
    [$arr[$i], $arr[$hi]] = [$arr[$hi], $arr[$i]];

    if ($i === $k) return $arr[$i];
    if ($i < $k) return quickSelect($arr, $i + 1, $hi, $k);
    return quickSelect($arr, $lo, $i - 1, $k);
}
```

### 2. Merge Sorted Arrays (LeetCode 88)
```php
<?php
function mergeSorted(array &$nums1, int $m, array $nums2, int $n): void
{
    $i = $m - 1;
    $j = $n - 1;
    $k = $m + $n - 1;

    while ($j >= 0) {
        if ($i >= 0 && $nums1[$i] > $nums2[$j]) {
            $nums1[$k--] = $nums1[$i--];
        } else {
            $nums1[$k--] = $nums2[$j--];
        }
    }
}
```

### 3. Sort an Array (LeetCode 912)
```php
<?php
function sortArray(array &$nums): array
{
    if (count($nums) <= 1) return $nums;

    $mid = (int)(count($nums) / 2);
    $left = sortArray(array_slice($nums, 0, $mid));
    $right = sortArray(array_slice($nums, $mid));

    return merge($left, $right);
}
```

## Interview Suallari

1. **Quick Sort niye practice-de en sureti sortdur?**
   - Cache-friendly: ardical yaddas erisimi
   - In-place: elave yaddas lazim deyil
   - Kicik constant factor
   - Worst case O(n^2) amma random pivot ile nedir impossible

2. **Merge Sort vs Quick Sort?**
   - Merge: Stable, guaranteed O(n log n), O(n) space lazim
   - Quick: Unstable, avg O(n log n), O(1) space, practice-de daha sureti
   - External sort (disk) ucun merge sort secilir

3. **Counting sort ne vaxt istifade olunur?**
   - Integer data, range kicikdir (k = O(n))
   - Meselen: yasa gore (0-150), qiymete gore (0-1000)
   - Range boyukdurse, radix sort istifade edin

4. **PHP-de sort() nece isleyir?**
   - Introsort: quicksort + heapsort + insertion sort
   - Quicksort baslar, derinlik limit kecse heapsort-a kecir
   - Kicik alt-massivlerde insertion sort istifade edir

## PHP/Laravel ile Elaqe

- **`sort()`, `usort()`, `uasort()`**: PHP daxili introsort
- **`$collection->sortBy()`**: Laravel collection stable sort
- **Database ORDER BY**: Database oz sort algoritmini istifade edir (merge sort variant)
- **Pagination**: Siralanmis data ucun LIMIT/OFFSET
- **Leaderboard**: Heap sort ile top-K tapmaq effektivdir
