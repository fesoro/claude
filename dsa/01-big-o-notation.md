# Big O Notation

## Konsept (Concept)

Big O notation algoritmin performansini tesvir edir -- input boyuyu duqce zamani ve ya yaddasi nece artir. Bu "en pis hal" (worst case) ucun ust sinirdir (upper bound).

```
Performans Sirasi (yavashdan suratliye):

O(n!)  >  O(2^n)  >  O(n^2)  >  O(n log n)  >  O(n)  >  O(log n)  >  O(1)
 |          |          |            |            |          |           |
 En        Cox       Yavas       Orta         Xetti      Suretli    En
 pis       yavas                                                    yaxsi
```

Vizual olaraq (n=16 ucun):
```
O(1)       = 1         |
O(log n)   = 4         ||||
O(n)       = 16        ||||||||||||||||
O(n log n) = 64        ||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
O(n^2)     = 256       (cox uzun...)
O(2^n)     = 65536     (ekrana sigmaz)
```

## Nece Isleyir? (How does it work?)

Big O hesablamasinin qaydalari:

1. **Sabitlari at (Drop constants):** O(2n) -> O(n)
2. **Kicik hisseleri at (Drop non-dominant terms):** O(n^2 + n) -> O(n^2)
3. **Ferqli inputlar ucun ferqli deyiskenler:** Iki ferqli massiv varsa, O(a + b) ve ya O(a * b)
4. **En pis hali gotur (Worst case):** Elementni axirda tapsan bele, O(n)

### Addim-addim misal:

```
function example(arr):          // n = arr.length
    sum = 0                     // O(1)
    for i in arr:               // O(n)
        sum += i                // O(1)
    for i in arr:               // O(n)
        for j in arr:           // O(n)
            print(i, j)         // O(1)
    return sum                  // O(1)

Toplam: O(1) + O(n) + O(n * n) + O(1) = O(n^2)
(dominant term saxlanilir)
```

## Implementasiya (Implementation)

### O(1) - Constant Time
```php
<?php
// Massivde indeksle erisim
function getFirst(array $arr): mixed {
    return $arr[0]; // Her zaman 1 emeliyyat
}

// Hash table lookup
function getByKey(array $map, string $key): mixed {
    return $map[$key]; // Ortalama O(1)
}

// Stack push/pop
function stackPush(array &$stack, mixed $val): void {
    $stack[] = $val; // Amortized O(1)
}
```

### O(log n) - Logarithmic Time
```php
<?php
// Binary Search
function binarySearch(array $arr, int $target): int {
    $left = 0;
    $right = count($arr) - 1;

    while ($left <= $right) {
        $mid = $left + intdiv($right - $left, 2);

        if ($arr[$mid] === $target) {
            return $mid;
        } elseif ($arr[$mid] < $target) {
            $left = $mid + 1;
        } else {
            $right = $mid - 1;
        }
    }

    return -1; // tapilmadi
}

// Her iterasiyada axtaris sahasini yariya endirir
// n=1000 -> ~10 addim, n=1000000 -> ~20 addim
```

### O(n) - Linear Time
```php
<?php
// Massivde axtaris
function linearSearch(array $arr, int $target): int {
    for ($i = 0; $i < count($arr); $i++) {
        if ($arr[$i] === $target) {
            return $i;
        }
    }
    return -1;
}

// Maksimum tapmaq
function findMax(array $arr): int {
    $max = $arr[0];
    foreach ($arr as $val) {
        if ($val > $max) {
            $max = $val;
        }
    }
    return $max;
}
```

### O(n log n) - Linearithmic Time
```php
<?php
// PHP-nin daxili sort funksiyasi
function sortArray(array &$arr): void {
    sort($arr); // Tim Sort - O(n log n)
}

// Merge Sort
function mergeSort(array $arr): array {
    $n = count($arr);
    if ($n <= 1) return $arr;

    $mid = intdiv($n, 2);
    $left = mergeSort(array_slice($arr, 0, $mid));
    $right = mergeSort(array_slice($arr, $mid));

    return merge($left, $right);
}

function merge(array $left, array $right): array {
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
```

### O(n^2) - Quadratic Time
```php
<?php
// Bubble Sort
function bubbleSort(array &$arr): void {
    $n = count($arr);
    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($arr[$j] > $arr[$j + 1]) {
                [$arr[$j], $arr[$j + 1]] = [$arr[$j + 1], $arr[$j]];
            }
        }
    }
}

// Butun cutleri yoxla
function hasPairWithSum(array $arr, int $target): bool {
    $n = count($arr);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            if ($arr[$i] + $arr[$j] === $target) {
                return true;
            }
        }
    }
    return false;
}
```

### O(2^n) - Exponential Time
```php
<?php
// Fibonacci (naive recursive)
function fib(int $n): int {
    if ($n <= 1) return $n;
    return fib($n - 1) + fib($n - 2);
}

// Butun alt coxluqlari (subsets) yaratmaq
function subsets(array $arr): array {
    $result = [[]];
    foreach ($arr as $num) {
        $newSubsets = [];
        foreach ($result as $subset) {
            $newSubsets[] = array_merge($subset, [$num]);
        }
        $result = array_merge($result, $newSubsets);
    }
    return $result; // 2^n alt coxluq
}
```

### O(n!) - Factorial Time
```php
<?php
// Permutasiyalar
function permutations(array $arr): array {
    if (count($arr) <= 1) return [$arr];

    $result = [];
    for ($i = 0; $i < count($arr); $i++) {
        $rest = array_merge(array_slice($arr, 0, $i), array_slice($arr, $i + 1));
        foreach (permutations($rest) as $perm) {
            $result[] = array_merge([$arr[$i]], $perm);
        }
    }
    return $result;
}
```

### Space Complexity Misallari
```php
<?php
// O(1) space
function sum(array $arr): int {
    $total = 0; // Yalniz 1 deyisken
    foreach ($arr as $v) $total += $v;
    return $total;
}

// O(n) space
function duplicate(array $arr): array {
    $copy = []; // n element saxlayir
    foreach ($arr as $v) $copy[] = $v;
    return $copy;
}

// O(n^2) space
function matrix(int $n): array {
    $m = [];
    for ($i = 0; $i < $n; $i++) {
        $m[$i] = array_fill(0, $n, 0); // n x n matris
    }
    return $m;
}
```

### Amortized Analysis
```php
<?php
// PHP array (dynamic array) - append emeliyyati
// Adeten O(1), amma bezn massiv boyuduculer O(n)
// Ortalama (amortized): O(1)

$arr = [];
for ($i = 0; $i < 1000000; $i++) {
    $arr[] = $i; // Amortized O(1)
    // Arxa planda: massiv dolduqda 2x boyudulur
    // Kopyalama O(n), amma nadir bas verir
    // Toplam: n + n/2 + n/4 + ... = ~2n => O(n) / n = O(1) amortized
}
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Notation | Ad | n=10 | n=100 | n=1000 | Misal |
|----------|----|------|-------|--------|-------|
| O(1) | Constant | 1 | 1 | 1 | Array index erisimi |
| O(log n) | Logarithmic | 3 | 7 | 10 | Binary search |
| O(n) | Linear | 10 | 100 | 1000 | Linear search |
| O(n log n) | Linearithmic | 33 | 664 | 9966 | Merge sort |
| O(n^2) | Quadratic | 100 | 10000 | 10^6 | Bubble sort |
| O(2^n) | Exponential | 1024 | 10^30 | 10^301 | Subsets |
| O(n!) | Factorial | 3.6M | 10^158 | ... | Permutations |

### Best / Average / Worst Case
| Algoritm | Best | Average | Worst |
|----------|------|---------|-------|
| Linear Search | O(1) | O(n) | O(n) |
| Binary Search | O(1) | O(log n) | O(log n) |
| Bubble Sort | O(n) | O(n^2) | O(n^2) |
| Quick Sort | O(n log n) | O(n log n) | O(n^2) |
| Hash Lookup | O(1) | O(1) | O(n) |

## Tipik Meseleler (Common Problems)

### 1. Iki dongun var -- complexity nedir?
```php
<?php
function twoLoops(array $arr): void {
    // Dongu 1: O(n)
    foreach ($arr as $v) { /* ... */ }
    // Dongu 2: O(n)
    foreach ($arr as $v) { /* ... */ }
}
// Cavab: O(n) + O(n) = O(2n) = O(n)
```

### 2. Ic-ice dongulerde ferqli massivler
```php
<?php
function crossProduct(array $a, array $b): void {
    foreach ($a as $x) {        // O(a)
        foreach ($b as $y) {    // O(b)
            echo "$x, $y\n";
        }
    }
}
// Cavab: O(a * b) -- O(n^2) deyil, chunki ferqli massivlerdir!
```

### 3. Yarimyarim azalan dongu
```php
<?php
function halvingLoop(int $n): void {
    $i = $n;
    while ($i > 0) {
        echo $i . "\n";
        $i = intdiv($i, 2);
    }
}
// Cavab: O(log n) -- her addimda yarilanir
```

### 4. Rekursiv Fibonacci-nin complexity-si
```php
<?php
function fib(int $n): int {
    if ($n <= 1) return $n;
    return fib($n - 1) + fib($n - 2);
}
// Time: O(2^n) -- her cagiri 2 yeni cagiri yaradir
// Space: O(n) -- call stack derinliyi
```

### 5. O(n) vaxtda O(n^2) meselesin hell et
```php
<?php
// Problem: Massivde iki ededin cemi target-e berabermi?
// O(n^2) yanasmasi:
function twoSumBrute(array $arr, int $target): bool {
    for ($i = 0; $i < count($arr); $i++)
        for ($j = $i + 1; $j < count($arr); $j++)
            if ($arr[$i] + $arr[$j] === $target) return true;
    return false;
}

// O(n) yanasmasi (hash table ile):
function twoSumOptimal(array $arr, int $target): bool {
    $seen = [];
    foreach ($arr as $num) {
        $complement = $target - $num;
        if (isset($seen[$complement])) return true;
        $seen[$num] = true;
    }
    return false;
}
```

## Interview Suallari

**S: O(n) ile O(n^2) arasindaki ferq nezer-negerpe olur?**
C: n=1000 ucun O(n)=1000, O(n^2)=1,000,000. n boyuduqce ferq drastik artir. Production-da bu ferq milisaniyeler ile saniyeler arasindadir.

**S: Amortized O(1) ne demekdir?**
C: Tek-tek emeliyyatlar bezn bahaldir (meselen, dynamic array resize O(n)), amma n emeliyyatin toplam deyeri O(n) oldugu ucun, ortalama her emeliyyat O(1)-dir.

**S: Best case ne vaxt ehmiyyetlidir?**
C: Nadir hallarda. Interview-da adeten worst case ve average case soruslur. Amma meselen, Insertion Sort artiq sorted massivde O(n)-dir, bu bilinmelidir.

**S: Space complexity-de call stack sayilirmi?**
C: Beli. Rekursiv funksiyalarda call stack derinliyi space complexity-ye daxildir. `fib(n)` ucun space O(n)-dir.

## PHP/Laravel ile Elaqe

```php
<?php
// PHP daxili funksiyalarin complexity-leri:

// O(1)
$arr[] = $val;              // append
isset($arr[$key]);          // hash lookup
count($arr);                // cached

// O(n)
in_array($val, $arr);       // linear search
array_search($val, $arr);   // linear search
array_reverse($arr);        // copy + reverse
array_merge($a, $b);        // O(n+m)

// O(n log n)
sort($arr);                 // introsort (quicksort + heapsort + insertion)
usort($arr, $callback);     // same with custom comparator

// Laravel Collection metodlari:
// $collection->contains($val)  -- O(n)
// $collection->first($callback) -- O(n) worst case
// $collection->sortBy('key')   -- O(n log n)
// $collection->groupBy('key')  -- O(n)
// $collection->keyBy('id')     -- O(n), sonra lookup O(1)

// Eloquent query optimization:
// N+1 problem: O(n) query evezine O(1) query
// User::with('posts')->get(); // Eager loading
// vs
// foreach (User::all() as $user) {
//     $user->posts; // Her user ucun ayri query = O(n) queries
// }
```
