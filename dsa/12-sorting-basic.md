# Sorting - Basic (Middle)

## Konsept (Concept)

Sorting elementleri mueyen ardiciliga gore duzmekdir. Basic sorting algoritmleri sade, amma yavas (O(n^2)) olur. Kicik data setleri ve ya demek olar ki siralanmis massivler ucun uygun ola biler.

```
Unsorted: [64, 34, 25, 12, 22, 11, 90]
Sorted:   [11, 12, 22, 25, 34, 64, 90]

Stability (Sabitlik):
  Stable sort:   [3a, 2, 3b, 1] -> [1, 2, 3a, 3b]  (eyni elementlerin sirasi qorunur)
  Unstable sort: [3a, 2, 3b, 1] -> [1, 2, 3b, 3a]  (sira deyise biler)
```

### Sorting xususiyyetleri:
- **In-place**: Elave yaddas istifade etmir (O(1) space)
- **Stable**: Eyni deyerli elementlerin orijinal sirasi qorunur
- **Adaptive**: Artiq siralanmis data ucun daha tez isleyir
- **Online**: Datalari bir-bir qebul ede bilir

| Algoritm | Stable | In-place | Adaptive | Online |
|----------|--------|----------|----------|--------|
| Bubble | Beli | Beli | Beli | Xeyr |
| Selection | Xeyr | Beli | Xeyr | Xeyr |
| Insertion | Beli | Beli | Beli | Beli |

## Nece Isleyir? (How does it work?)

### Bubble Sort:
```
[64, 34, 25, 12]

Pass 1: [34, 25, 12, 64]  <- 64 sona getdi
  64>34 swap [34,64,25,12]
  64>25 swap [34,25,64,12]
  64>12 swap [34,25,12,64]

Pass 2: [25, 12, 34, 64]  <- 34 yerine getdi
  34>25 swap [25,34,12,64]
  34>12 swap [25,12,34,64]

Pass 3: [12, 25, 34, 64]  <- Tamam!
  25>12 swap [12,25,34,64]
```

### Selection Sort:
```
[64, 34, 25, 12]

Pass 1: min=12(index 3), swap with index 0 -> [12, 34, 25, 64]
Pass 2: min=25(index 2), swap with index 1 -> [12, 25, 34, 64]
Pass 3: min=34(index 2), artiq yerinde       -> [12, 25, 34, 64]
```

### Insertion Sort:
```
[64, 34, 25, 12]

Step 1: key=34, 64>34, shift -> [34, 64, 25, 12]
Step 2: key=25, 64>25, 34>25, shift -> [25, 34, 64, 12]
Step 3: key=12, 64>12, 34>12, 25>12, shift -> [12, 25, 34, 64]
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Bubble Sort
 * Qonsu elementleri muqayise et, boyugu saga surusdur
 * Time: O(n^2), Space: O(1), Stable: Beli
 */
function bubbleSort(array &$arr): void
{
    $n = count($arr);

    for ($i = 0; $i < $n - 1; $i++) {
        $swapped = false;

        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($arr[$j] > $arr[$j + 1]) {
                [$arr[$j], $arr[$j + 1]] = [$arr[$j + 1], $arr[$j]];
                $swapped = true;
            }
        }

        // Hec bir swap olmadisa, artiq siralanib
        if (!$swapped) break;
    }
}

/**
 * Selection Sort
 * Her step-de minimumu tap, basda yerlesddir
 * Time: O(n^2), Space: O(1), Stable: Xeyr
 */
function selectionSort(array &$arr): void
{
    $n = count($arr);

    for ($i = 0; $i < $n - 1; $i++) {
        $minIndex = $i;

        for ($j = $i + 1; $j < $n; $j++) {
            if ($arr[$j] < $arr[$minIndex]) {
                $minIndex = $j;
            }
        }

        if ($minIndex !== $i) {
            [$arr[$i], $arr[$minIndex]] = [$arr[$minIndex], $arr[$i]];
        }
    }
}

/**
 * Insertion Sort
 * Her elementi dogru yerine daxil et (kart oyunu kimi)
 * Time: O(n^2) worst, O(n) best, Space: O(1), Stable: Beli
 */
function insertionSort(array &$arr): void
{
    $n = count($arr);

    for ($i = 1; $i < $n; $i++) {
        $key = $arr[$i];
        $j = $i - 1;

        while ($j >= 0 && $arr[$j] > $key) {
            $arr[$j + 1] = $arr[$j];
            $j--;
        }

        $arr[$j + 1] = $key;
    }
}

/**
 * Optimized Bubble Sort - gec swap mevqeyini yadda saxla
 */
function bubbleSortOptimized(array &$arr): void
{
    $n = count($arr);
    $newN = $n;

    while ($newN > 1) {
        $lastSwap = 0;
        for ($j = 0; $j < $newN - 1; $j++) {
            if ($arr[$j] > $arr[$j + 1]) {
                [$arr[$j], $arr[$j + 1]] = [$arr[$j + 1], $arr[$j]];
                $lastSwap = $j + 1;
            }
        }
        $newN = $lastSwap;
    }
}

/**
 * Binary Insertion Sort - ikili axtaris ile daxil etme yeri tap
 * Muqayise sayi azalir: O(n log n), amma shift hele O(n^2)
 */
function binaryInsertionSort(array &$arr): void
{
    $n = count($arr);

    for ($i = 1; $i < $n; $i++) {
        $key = $arr[$i];

        // Binary search ile dogru yeri tap
        $lo = 0;
        $hi = $i - 1;
        while ($lo <= $hi) {
            $mid = (int)(($lo + $hi) / 2);
            if ($arr[$mid] > $key) {
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }

        // Shift elements
        for ($j = $i - 1; $j >= $lo; $j--) {
            $arr[$j + 1] = $arr[$j];
        }
        $arr[$lo] = $key;
    }
}

/**
 * Cocktail Shaker Sort (Bidirectional Bubble Sort)
 * Her iki istiqametde bubble sort
 */
function cocktailSort(array &$arr): void
{
    $start = 0;
    $end = count($arr) - 1;
    $swapped = true;

    while ($swapped) {
        $swapped = false;

        // Sola-saga
        for ($i = $start; $i < $end; $i++) {
            if ($arr[$i] > $arr[$i + 1]) {
                [$arr[$i], $arr[$i + 1]] = [$arr[$i + 1], $arr[$i]];
                $swapped = true;
            }
        }
        $end--;

        if (!$swapped) break;
        $swapped = false;

        // Saga-sola
        for ($i = $end; $i > $start; $i--) {
            if ($arr[$i] < $arr[$i - 1]) {
                [$arr[$i], $arr[$i - 1]] = [$arr[$i - 1], $arr[$i]];
                $swapped = true;
            }
        }
        $start++;
    }
}

// --- Comparison function ile sort ---
function insertionSortBy(array &$arr, callable $compare): void
{
    $n = count($arr);
    for ($i = 1; $i < $n; $i++) {
        $key = $arr[$i];
        $j = $i - 1;
        while ($j >= 0 && $compare($arr[$j], $key) > 0) {
            $arr[$j + 1] = $arr[$j];
            $j--;
        }
        $arr[$j + 1] = $key;
    }
}

// --- Test ---
$arr = [64, 34, 25, 12, 22, 11, 90];
$test1 = $test2 = $test3 = $arr;

bubbleSort($test1);
echo "Bubble:    " . implode(', ', $test1) . "\n";

selectionSort($test2);
echo "Selection: " . implode(', ', $test2) . "\n";

insertionSort($test3);
echo "Insertion: " . implode(', ', $test3) . "\n";

// Custom compare
$people = [
    ['name' => 'Ali', 'age' => 30],
    ['name' => 'Veli', 'age' => 25],
    ['name' => 'Aysel', 'age' => 28],
];
insertionSortBy($people, fn($a, $b) => $a['age'] - $b['age']);
foreach ($people as $p) echo "{$p['name']}({$p['age']}) ";
// Veli(25) Aysel(28) Ali(30)
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Best | Average | Worst | Space | Stable |
|----------|------|---------|-------|-------|--------|
| Bubble | O(n) | O(n^2) | O(n^2) | O(1) | Beli |
| Selection | O(n^2) | O(n^2) | O(n^2) | O(1) | Xeyr |
| Insertion | O(n) | O(n^2) | O(n^2) | O(1) | Beli |

**Ne vaxt istifade etmeli:**
- **Insertion Sort**: Kicik array (<50 element), demek olar ki siralanmis data, online data
- **Bubble Sort**: Yalniz oyrenmek ucun, praktikada hec vaxt
- **Selection Sort**: Swap bahalidiresa (yaddas emeliyyatlari), minimum swap lazimdir

## Tipik Meseler (Common Problems)

### 1. Sort Colors (LeetCode 75) - Dutch National Flag
```php
<?php
function sortColors(array &$nums): void
{
    $lo = 0;
    $mid = 0;
    $hi = count($nums) - 1;

    while ($mid <= $hi) {
        if ($nums[$mid] === 0) {
            [$nums[$lo], $nums[$mid]] = [$nums[$mid], $nums[$lo]];
            $lo++;
            $mid++;
        } elseif ($nums[$mid] === 1) {
            $mid++;
        } else {
            [$nums[$mid], $nums[$hi]] = [$nums[$hi], $nums[$mid]];
            $hi--;
        }
    }
}
```

### 2. Squares of a Sorted Array (LeetCode 977)
```php
<?php
function sortedSquares(array $nums): array
{
    $n = count($nums);
    $result = array_fill(0, $n, 0);
    $left = 0;
    $right = $n - 1;
    $pos = $n - 1;

    while ($left <= $right) {
        $leftSq = $nums[$left] * $nums[$left];
        $rightSq = $nums[$right] * $nums[$right];

        if ($leftSq > $rightSq) {
            $result[$pos] = $leftSq;
            $left++;
        } else {
            $result[$pos] = $rightSq;
            $right--;
        }
        $pos--;
    }

    return $result;
}
```

### 3. Custom Sort - K Closest Points (partially)
```php
<?php
function sortByDistance(array &$points): void
{
    // Insertion sort - kicik data ucun uygun
    $n = count($points);
    for ($i = 1; $i < $n; $i++) {
        $key = $points[$i];
        $keyDist = $key[0] * $key[0] + $key[1] * $key[1];
        $j = $i - 1;

        while ($j >= 0 && ($points[$j][0] ** 2 + $points[$j][1] ** 2) > $keyDist) {
            $points[$j + 1] = $points[$j];
            $j--;
        }
        $points[$j + 1] = $key;
    }
}
```

## Interview Suallari

1. **Stable sort nedir ve niye vacibdir?**
   - Eyni key-e sahib elementlerin orijinal sirasi qorunur
   - Multi-key sort zamani vacibdir: evelce ada gore, sonra yasa gore sirala
   - Insertion Sort ve Bubble Sort stable-dir, Selection Sort deyil

2. **Insertion sort ne vaxt O(n^2)-den yaxsi isleyir?**
   - Demek olar ki siralanmis array-de O(n) isleyir
   - Kicik array-lerde overhead azi oldugu ucun sureti yaxsidir
   - Bu sebebden quicksort/mergesort kicik hisselerde insertion sort istifade edir

3. **Niye real dunyada bubble sort istifade olunmur?**
   - O(n^2) performance, insertion sort eyni worst case-de daha az swap edir
   - Insertion sort adaptive-dir, bubble sort tam siralanmis olmasa yavasdir
   - PHP-nin sort() funksiyasi introsort (quicksort + heapsort + insertion) istifade edir

4. **Selection sort-un bir ustunluyu varmi?**
   - Minimum swap sayi: O(n), diger O(n^2) sort-lardan azdir
   - Swap emeliyyati bahalidiresa (boyuk objectler), selection sort secile biler

## PHP/Laravel ile Elaqe

- **PHP sort functions**: `sort()`, `usort()`, `array_multisort()` daxili olaraq Introsort istifade edir
- **Laravel collections**: `$collection->sortBy('field')` stable sort edir
- **Small dataset sorting**: Config, dropdown options kimi kicik listeler ucun
- **Teaching**: Bu algoritmler sorting konseptini basqa algoritmlere kecirir
- **Timsort**: Python ve Java istifade edir - insertion sort + merge sort hibrididir
