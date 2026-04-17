# Arrays (Massivler)

## Konsept (Concept)

Array ardicil yaddas bloklarinda saxlanilan elementler toplusudur. Her elementin indeksi var ve bu indeksle O(1) vaxtda erisile biler.

```
Statik Array (fixed size):
Index:   0     1     2     3     4
       +-----+-----+-----+-----+-----+
       | 10  | 20  | 30  | 40  | 50  |
       +-----+-----+-----+-----+-----+
Address: 100   104   108   112   116
         ^base
         
Address hesabi: base + (index * element_size)
arr[3] = 100 + (3 * 4) = 112 -> 40

Dinamik Array (resizable):
       +-----+-----+-----+-----+-----+-----+-----+-----+
       | 10  | 20  | 30  |     |     |     |     |     |
       +-----+-----+-----+-----+-----+-----+-----+-----+
       size=3, capacity=8
       
       Yeni element elave: amortized O(1)
       Dolduqda: yeni 2x boyda massiv yaradilir, elementler kopyalanir
```

## Nece Isleyir? (How does it work?)

### Statik vs Dinamik Array

**Statik Array:**
1. Yaradilarkn fixed size verilir
2. Boyutu deyise bilmez
3. C, Java-da `int arr[5]` kimi

**Dinamik Array:**
1. Bashlangicda kicik capacity ile yaradilir
2. Element elave olunduqda size artir
3. Size == capacity olduqda, yeni massiv yaradilir (2x boyda)
4. Kohne elementler kopyalanir
5. PHP-de butun array-ler dinamikdir

### Emeliyyatlar Addim-addim

**Elave etme (Insert at index i):**
```
Evvel:   [10, 20, 30, 40, 50]
Insert 25 at index 2:

Addim 1: Sona dog surushdir (shift right)
         [10, 20, 30, 30, 40, 50]  -- index 4->5
         [10, 20, 30, 30, 40, 50]  -- index 3->4
         [10, 20, __, 30, 40, 50]  -- index 2 bosh

Addim 2: Yeni elementi yerleshdir
         [10, 20, 25, 30, 40, 50]
```

**Silme (Delete at index i):**
```
Evvel:   [10, 20, 25, 30, 40, 50]
Delete index 2 (25):

Addim 1: Sola surushdir (shift left)
         [10, 20, 30, 40, 50, __]

Addim 2: Size azalt
         [10, 20, 30, 40, 50]
```

## Implementasiya (Implementation)

### Esas Emeliyyatlar PHP-de
```php
<?php
// --- YARADILMA ---
$arr = [];                        // Bosh dinamik array
$arr = [1, 2, 3, 4, 5];          // Deyerlerle yaradilma
$arr = array_fill(0, 10, 0);     // 10 element, hamisin 0

// --- ELAVE ETME ---
$arr[] = 6;                       // Sona elave: O(1) amortized
array_push($arr, 7);              // Sona elave: O(1) amortized
array_unshift($arr, 0);           // Evvele elave: O(n) -- hamisi surushur

// Ortaya elave (splice):
array_splice($arr, 2, 0, [99]);   // index 2-ye 99 elave: O(n)

// --- SILME ---
array_pop($arr);                  // Sondan silme: O(1)
array_shift($arr);                // Evvelden silme: O(n)
unset($arr[3]);                   // Indeksle silme: O(1) amma boshluq qalir
array_splice($arr, 3, 1);        // Indeksle silme + surushme: O(n)

// --- AXTARIS ---
$idx = array_search(30, $arr);    // Linear search: O(n)
$exists = in_array(30, $arr);     // Linear search: O(n)
```

### 2D Massivler
```php
<?php
// 2D massiv yaratmaq
$matrix = [
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9]
];

// Erisim: satir i, sutun j
echo $matrix[1][2]; // 6

// 2D massiv gezme
function printMatrix(array $matrix): void {
    $rows = count($matrix);
    $cols = count($matrix[0]);

    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            echo $matrix[$i][$j] . " ";
        }
        echo "\n";
    }
}

// Matris transpozisiyasi (transpose)
function transpose(array $matrix): array {
    $rows = count($matrix);
    $cols = count($matrix[0]);
    $result = [];

    for ($i = 0; $i < $cols; $i++) {
        for ($j = 0; $j < $rows; $j++) {
            $result[$i][$j] = $matrix[$j][$i];
        }
    }
    return $result;
}
```

### Massiv Rotation
```php
<?php
// Massivi k qeder saga firla
// [1,2,3,4,5], k=2 => [4,5,1,2,3]

// Yanasmasi 1: O(n) vaxt, O(n) yer
function rotateSimple(array $arr, int $k): array {
    $n = count($arr);
    $k = $k % $n;
    return array_merge(
        array_slice($arr, $n - $k),
        array_slice($arr, 0, $n - $k)
    );
}

// Yanasmasi 2: Reverse metodu - O(n) vaxt, O(1) yer
function rotateInPlace(array &$arr, int $k): void {
    $n = count($arr);
    $k = $k % $n;

    reverse($arr, 0, $n - 1);      // Butov terse cevir
    reverse($arr, 0, $k - 1);      // Ilk k-ni terse
    reverse($arr, $k, $n - 1);     // Qalan hisseyi terse
}

function reverse(array &$arr, int $start, int $end): void {
    while ($start < $end) {
        [$arr[$start], $arr[$end]] = [$arr[$end], $arr[$start]];
        $start++;
        $end--;
    }
}

// Misal: [1,2,3,4,5], k=2
// Addim 1: reverse all  -> [5,4,3,2,1]
// Addim 2: reverse 0..1 -> [4,5,3,2,1]
// Addim 3: reverse 2..4 -> [4,5,1,2,3]
```

### Prefix Sum
```php
<?php
// Prefix sum: rang emeliyyatlarini O(1)-e endirmek ucun
// prefix[i] = arr[0] + arr[1] + ... + arr[i-1]

function buildPrefixSum(array $arr): array {
    $n = count($arr);
    $prefix = array_fill(0, $n + 1, 0);

    for ($i = 0; $i < $n; $i++) {
        $prefix[$i + 1] = $prefix[$i] + $arr[$i];
    }
    return $prefix;
}

// [l, r] araligindaki cemin tapmaq: O(1)
function rangeSum(array $prefix, int $l, int $r): int {
    return $prefix[$r + 1] - $prefix[$l];
}

// Misal:
// arr    = [2, 4, 6, 8, 10]
// prefix = [0, 2, 6, 12, 20, 30]
// Sum(1,3) = prefix[4] - prefix[1] = 20 - 2 = 18  (4+6+8=18)
```

### Kadane's Algorithm -- Maksimum Alt Massiv
```php
<?php
// Ardicil elementlerin en boyuk cemini tap
function maxSubarraySum(array $arr): int {
    $maxSoFar = $arr[0];
    $maxEndingHere = $arr[0];

    for ($i = 1; $i < count($arr); $i++) {
        $maxEndingHere = max($arr[$i], $maxEndingHere + $arr[$i]);
        $maxSoFar = max($maxSoFar, $maxEndingHere);
    }

    return $maxSoFar;
}

// Misal: [-2, 1, -3, 4, -1, 2, 1, -5, 4]
// Cavab: 6 (alt massiv [4, -1, 2, 1])
```

### Dutch National Flag (3-Way Partition)
```php
<?php
// Massivi 0, 1, 2 deyerleri ile siralama (sort colors)
function sortColors(array &$arr): void {
    $low = 0;
    $mid = 0;
    $high = count($arr) - 1;

    while ($mid <= $high) {
        if ($arr[$mid] === 0) {
            [$arr[$low], $arr[$mid]] = [$arr[$mid], $arr[$low]];
            $low++;
            $mid++;
        } elseif ($arr[$mid] === 1) {
            $mid++;
        } else { // 2
            [$arr[$mid], $arr[$high]] = [$arr[$high], $arr[$mid]];
            $high--;
        }
    }
}
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | Massiv | Dinamik Massiv |
|-----------|--------|----------------|
| Erisim (access) | O(1) | O(1) |
| Axtaris (search) | O(n) | O(n) |
| Sona elave | N/A | O(1) amortized |
| Evvele elave | O(n) | O(n) |
| Ortaya elave | O(n) | O(n) |
| Sondan silme | O(1) | O(1) |
| Evvelden silme | O(n) | O(n) |
| Ortadan silme | O(n) | O(n) |

Space: O(n)

## Tipik Meseleler (Common Problems)

### 1. Two Sum
```php
<?php
function twoSum(array $nums, int $target): array {
    $map = [];
    foreach ($nums as $i => $num) {
        $complement = $target - $num;
        if (isset($map[$complement])) {
            return [$map[$complement], $i];
        }
        $map[$num] = $i;
    }
    return [];
}
```

### 2. Move Zeroes to End
```php
<?php
function moveZeroes(array &$nums): void {
    $insertPos = 0;
    foreach ($nums as $num) {
        if ($num !== 0) {
            $nums[$insertPos++] = $num;
        }
    }
    while ($insertPos < count($nums)) {
        $nums[$insertPos++] = 0;
    }
}
```

### 3. Product of Array Except Self
```php
<?php
function productExceptSelf(array $nums): array {
    $n = count($nums);
    $result = array_fill(0, $n, 1);

    // Soldan saga prefix product
    $prefix = 1;
    for ($i = 0; $i < $n; $i++) {
        $result[$i] = $prefix;
        $prefix *= $nums[$i];
    }

    // Sagdan sola suffix product
    $suffix = 1;
    for ($i = $n - 1; $i >= 0; $i--) {
        $result[$i] *= $suffix;
        $suffix *= $nums[$i];
    }

    return $result;
}
// [1,2,3,4] -> [24,12,8,6]
// Bolme istifade etmeden, O(n) vaxt, O(1) elave yer
```

### 4. Maximum Subarray (Kadane)
Yuxaridaki implementasiyaya bax.

### 5. Merge Sorted Arrays
```php
<?php
function mergeSorted(array $a, array $b): array {
    $result = [];
    $i = $j = 0;
    while ($i < count($a) && $j < count($b)) {
        if ($a[$i] <= $b[$j]) {
            $result[] = $a[$i++];
        } else {
            $result[] = $b[$j++];
        }
    }
    while ($i < count($a)) $result[] = $a[$i++];
    while ($j < count($b)) $result[] = $b[$j++];
    return $result;
}
```

## Interview Suallari

**S: PHP-de array nece isleyir?**
C: PHP array eslinde ordered hash map-dir. Hem integer hem string key-leri destekleyir. Daxili olaraq hash table + doubly linked list istifade edir. Bu sebebden `$arr[] = val` O(1)-dir, amma yaddas istifadesi C massivine nisbeten daha coxdur.

**S: array_push vs $arr[] ferqi?**
C: `$arr[] = val` birbasa zend_hash emeliyyatidir ve daha suretlidir. `array_push()` funksiya cagirisi overhead-i var. Tek element elave edirsinizse `$arr[]` istifade edin.

**S: Niye massivde axtaris O(n), hash table-da O(1)?**
C: Massivde bilinmeyen deyeri tapmaq ucun her elementi yoxlamaq lazimdir. Hash table-da key hash olunur ve birbase yerine baxilir.

**S: In-place ne demekdir?**
C: Elave massiv yaratmadan, movcud massiv uzerinde deyisiklik etmek. Space complexity O(1) olur.

## PHP/Laravel ile Elaqe

```php
<?php
// PHP array internals:
// - Her element: zval (value) + bucket (hash, key, linked list pointer)
// - Packed array: ardicil integer key-ler -> daha az yaddas
// - Hash array: string key-ler ve ya bosluqlu integer key-ler

// Laravel Collection (array uzerinde wrapper):
use Illuminate\Support\Collection;

$collection = collect([1, 2, 3, 4, 5]);

$collection->map(fn($v) => $v * 2);        // [2, 4, 6, 8, 10]
$collection->filter(fn($v) => $v > 3);     // [4, 5]
$collection->reduce(fn($carry, $v) => $carry + $v, 0); // 15
$collection->chunk(2);                      // [[1,2], [3,4], [5]]
$collection->sliding(3);                    // [[1,2,3], [2,3,4], [3,4,5]]

// Laravel-de array helper-ler:
use Illuminate\Support\Arr;

Arr::get($array, 'user.name', 'default');   // Nested access
Arr::flatten($multiDimensional);             // 2D -> 1D
Arr::only($array, ['key1', 'key2']);         // Subset
Arr::pluck($records, 'name');               // Column extract

// Database: JSON sutunlar array kimi
// Migration: $table->json('tags');
// Model: protected $casts = ['tags' => 'array'];
```
