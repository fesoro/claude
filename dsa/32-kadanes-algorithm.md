# Kadane's Algorithm (Senior)

## Konsept (Concept)

**Kadane's Algorithm** — array daxilində **maksimum cəmli contiguous subarray**-i O(n) zamanda tapan klassik DP alqoritmidir. 1984-cü ildə Jay Kadane tərəfindən kəşf edilib.

Əsas ideya: **hər indeks üçün "bu nöqtədə bitən maksimum subarray cəmi nədir?"** sualına cavab verir. Əgər əvvəlki cəm mənfi olubsa, onu atmaq daha yaxşıdır — yeni başlayaq.

### Problem:
```
Input:  [-2, 1, -3, 4, -1, 2, 1, -5, 4]
Output: 6  (subarray [4, -1, 2, 1])
```

### Brute Force vs Kadane
- **Brute force**: O(n²) və ya O(n³) — bütün subarray-ləri yoxla
- **Kadane**: O(n), tək pass, O(1) yaddaş

## Necə İşləyir?

### Formulla:
```
currentMax = max(arr[i], currentMax + arr[i])
globalMax  = max(globalMax, currentMax)
```

### Addım-addım ([-2, 1, -3, 4, -1, 2, 1, -5, 4]):

| i | arr[i] | currentMax | globalMax |
|---|--------|------------|-----------|
| 0 | -2 | -2 | -2 |
| 1 | 1 | max(1, -2+1)=1 | 1 |
| 2 | -3 | max(-3, 1-3)=-2 | 1 |
| 3 | 4 | max(4, -2+4)=4 | 4 |
| 4 | -1 | max(-1, 4-1)=3 | 4 |
| 5 | 2 | max(2, 3+2)=5 | 5 |
| 6 | 1 | max(1, 5+1)=6 | **6** |
| 7 | -5 | max(-5, 6-5)=1 | 6 |
| 8 | 4 | max(4, 1+4)=5 | 6 |

### DP baxışı
`dp[i]` = i-ci indeksdə bitən maksimum subarray cəmi.
`dp[i] = max(arr[i], dp[i-1] + arr[i])`

## İmplementasiya (Implementation) - PHP

### 1. Əsas Kadane

```php
function kadane(array $arr): int {
    if (empty($arr)) return 0;
    $currentMax = $globalMax = $arr[0];
    for ($i = 1; $i < count($arr); $i++) {
        $currentMax = max($arr[$i], $currentMax + $arr[$i]);
        $globalMax = max($globalMax, $currentMax);
    }
    return $globalMax;
}
```

### 2. Subarray-i də qaytar

```php
function kadaneWithIndices(array $arr): array {
    $currentMax = $globalMax = $arr[0];
    $start = $end = 0;
    $tempStart = 0;
    for ($i = 1; $i < count($arr); $i++) {
        if ($arr[$i] > $currentMax + $arr[$i]) {
            $currentMax = $arr[$i];
            $tempStart = $i;
        } else {
            $currentMax += $arr[$i];
        }
        if ($currentMax > $globalMax) {
            $globalMax = $currentMax;
            $start = $tempStart;
            $end = $i;
        }
    }
    return ['sum' => $globalMax, 'start' => $start, 'end' => $end,
            'subarray' => array_slice($arr, $start, $end - $start + 1)];
}
```

### 3. Bütün elementlər mənfi olanda

```php
// Standart Kadane-də ən böyük mənfi ədədi qaytarır (doğru davranış)
function kadaneAllowNegative(array $arr): int {
    $currentMax = $globalMax = $arr[0];
    for ($i = 1; $i < count($arr); $i++) {
        $currentMax = max($arr[$i], $currentMax + $arr[$i]);
        $globalMax = max($globalMax, $currentMax);
    }
    return $globalMax;
}
```

### 4. Circular Maximum Subarray

```php
// Array dairəvi — end-dən start-a keçid mümkün
function maxCircularSubarray(array $arr): int {
    $totalSum = array_sum($arr);
    $maxNormal = kadane($arr);

    // Min subarray tap və onu totalSum-dan çıxart
    $minCurrent = $minGlobal = $arr[0];
    for ($i = 1; $i < count($arr); $i++) {
        $minCurrent = min($arr[$i], $minCurrent + $arr[$i]);
        $minGlobal = min($minGlobal, $minCurrent);
    }

    // Bütün ədədlər mənfidirsə, circular cavab 0 olar — o zaman adi Kadane qaytar
    if ($minGlobal === $totalSum) return $maxNormal;
    return max($maxNormal, $totalSum - $minGlobal);
}
```

### 5. Maximum Product Subarray

```php
// Məhsul üçün min və max birlikdə saxlanmalıdır (mənfi × mənfi = müsbət)
function maxProductSubarray(array $arr): int {
    $maxSoFar = $minSoFar = $result = $arr[0];
    for ($i = 1; $i < count($arr); $i++) {
        $curr = $arr[$i];
        $tempMax = max($curr, $maxSoFar * $curr, $minSoFar * $curr);
        $minSoFar = min($curr, $maxSoFar * $curr, $minSoFar * $curr);
        $maxSoFar = $tempMax;
        $result = max($result, $maxSoFar);
    }
    return $result;
}
```

### 6. 2D Kadane (Maximum Sum Submatrix)

```php
function maxSumSubmatrix(array $matrix): int {
    $rows = count($matrix);
    if ($rows === 0) return 0;
    $cols = count($matrix[0]);
    $maxSum = PHP_INT_MIN;

    // Hər top row cütü üçün
    for ($top = 0; $top < $rows; $top++) {
        $temp = array_fill(0, $cols, 0);
        for ($bottom = $top; $bottom < $rows; $bottom++) {
            // bottom row-u temp-ə əlavə et
            for ($c = 0; $c < $cols; $c++) {
                $temp[$c] += $matrix[$bottom][$c];
            }
            // Temp array-inə Kadane tətbiq et
            $currentMax = $localMax = $temp[0];
            for ($c = 1; $c < $cols; $c++) {
                $currentMax = max($temp[$c], $currentMax + $temp[$c]);
                $localMax = max($localMax, $currentMax);
            }
            $maxSum = max($maxSum, $localMax);
        }
    }
    return $maxSum;
}
```

### 7. Maximum Absolute Subarray Sum

```php
function maxAbsSubarraySum(array $arr): int {
    // max(|max subarray sum|, |min subarray sum|)
    $maxKadane = kadane($arr);
    $negArr = array_map(fn($x) => -$x, $arr);
    $minKadane = -kadane($negArr);
    return max(abs($maxKadane), abs($minKadane));
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Variant | Time | Space |
|---------|------|-------|
| Basic Kadane | O(n) | O(1) |
| Kadane + indices | O(n) | O(1) |
| Circular Kadane | O(n) | O(1) |
| Max Product | O(n) | O(1) |
| 2D Kadane | O(rows² × cols) | O(cols) |
| Absolute sum | O(n) | O(1) |

**2D Kadane analizi**: top/bottom cütləri O(rows²), hər cüt üçün Kadane O(cols). Cəmi O(rows² × cols).

## Tipik Məsələlər (Common Problems)

### 1. LeetCode 53: Maximum Subarray
```php
function maxSubArray(array $nums): int {
    $current = $max = $nums[0];
    for ($i = 1; $i < count($nums); $i++) {
        $current = max($nums[$i], $current + $nums[$i]);
        $max = max($max, $current);
    }
    return $max;
}
```

### 2. LeetCode 918: Maximum Sum Circular Subarray
```php
function maxSubarraySumCircular(array $nums): int {
    $total = array_sum($nums);
    $maxCur = $maxSum = $nums[0];
    $minCur = $minSum = $nums[0];
    for ($i = 1; $i < count($nums); $i++) {
        $maxCur = max($nums[$i], $maxCur + $nums[$i]);
        $maxSum = max($maxSum, $maxCur);
        $minCur = min($nums[$i], $minCur + $nums[$i]);
        $minSum = min($minSum, $minCur);
    }
    return $maxSum > 0 ? max($maxSum, $total - $minSum) : $maxSum;
}
```

### 3. LeetCode 152: Maximum Product Subarray
Yuxarıdakı `maxProductSubarray` funksiyası.

### 4. Best Time to Buy and Sell Stock (Kadane interpretation)
```php
// Difference array-ində Kadane
function maxProfit(array $prices): int {
    $diff = [];
    for ($i = 1; $i < count($prices); $i++) $diff[] = $prices[$i] - $prices[$i-1];
    if (empty($diff)) return 0;
    $maxP = kadane($diff);
    return max(0, $maxP);
}
```

### 5. K-Concatenation Maximum Sum (LC 1191)
```php
function kConcatenationMaxSum(array $arr, int $k): int {
    $MOD = 1_000_000_007;
    $sum = array_sum($arr);
    $singleMax = kadane($arr);
    if ($k === 1) return max(0, $singleMax);
    $twoConcat = kadane(array_merge($arr, $arr));
    if ($sum > 0) {
        return max(0, $twoConcat + ($k - 2) * $sum) % $MOD;
    }
    return max(0, $twoConcat) % $MOD;
}
```

## Interview Sualları

**1. Kadane alqoritmi niyə O(n)?**
Hər element tam bir dəfə ziyarət edilir. Hər addımda iki müqayisə edilir: `arr[i]` vs `currentMax + arr[i]`. Ümumi O(n).

**2. `max(arr[i], currentMax + arr[i])` nə deməkdir?**
Soruş: "əvvəlki subarray-i davam etdirim, yoxsa i-dən yenidən başlayım?" Əgər `currentMax` mənfidirsə, onu davam etdirmək zərərlidir → yenidən başla.

**3. Bütün elementlər mənfi olsa?**
Cavab ən böyük mənfi ədəd olur (ən az zərər). Bəzi variantlar boş subarray-i (0) icazə verir — bu spec məsələsidir.

**4. Circular Kadane necə işləyir?**
İki hal:
- Maksimum subarray "wrap around" etmir → adi Kadane
- Wrap edir → `totalSum - minSubarraySum`

Cavab bu iki haldan max-dır. Xüsusi hal: bütün mənfi olanda `totalSum = minSubarraySum` olur, o zaman adi Kadane qaytarılmalıdır.

**5. Max Product Subarray-də niyə min saxlamaq lazımdır?**
Çünki mənfi × mənfi = müsbət. Bir böyük mənfi məhsul gələcəkdə başqa mənfi ədədlə vurulub ən böyük məhsula çevrilə bilər.

**6. 2D Kadane-in mürəkkəbliyi?**
O(rows² × cols) və ya simmetrik olaraq O(cols² × rows). Kiçik ölçünü xaricdə saxlamaq daha yaxşıdır.

**7. Kadane-i DP kimi necə izah edərdin?**
`dp[i] = i-də bitən max subarray sum`. Recurrence: `dp[i] = max(arr[i], dp[i-1] + arr[i])`. Cavab: `max(dp)`. Space O(1)-ə optimize edilir, çünki yalnız `dp[i-1]` lazımdır.

**8. Subarray və subsequence arasında fərq?**
- **Subarray**: contiguous (ardıcıl indekslər)
- **Subsequence**: sıra qorunur, amma elementlər arasından atıla bilər
Kadane subarray üçündür, subsequence üçün başqa DP (LIS və s.) lazımdır.

**9. Prefix sum ilə Kadane arasında əlaqə?**
Kadane ekvivalentdir: `max(prefix[j] - min(prefix[0..j-1]))`. Prefix sum daha çox range sum üçündür, Kadane xüsusi halıdır (max subarray sum).

**10. Real dünyada Kadane harada istifadə olunur?**
- **Time series analysis**: maksimum gəlir dövrü
- **Stock trading**: maksimum qazanc (buy/sell bir dəfə)
- **Image processing**: parlaq region tapmaq (2D Kadane)
- **DNA sequence analysis**: yüksək skorlu segment

## PHP/Laravel ilə Əlaqə

- **Reporting modullarında**: günlük gəlir/zərər log-larında maksimum qazanc dövrü tapmaq.
- **Monitoring**: ardıcıl error count artımının maksimum "zərb" intervalı.
- **A/B test analizi**: iki variant arasındakı fərqin maksimum birləşmiş effekti.
- **Laravel Collection**:
  ```php
  $max = collect($arr)->reduce(function ($carry, $n) {
      $carry['current'] = max($n, $carry['current'] + $n);
      $carry['max'] = max($carry['max'], $carry['current']);
      return $carry;
  }, ['current' => $arr[0], 'max' => $arr[0]])['max'];
  ```
- **Caching**: 2D Kadane hesablamaları ağırdır — Redis-də cachelə.

---

## Praktik Tapşırıqlar

1. **LeetCode 53** — Maximum Subarray (Kadane klassiki)
2. **LeetCode 918** — Maximum Sum Circular Subarray (total - min subarray)
3. **LeetCode 152** — Maximum Product Subarray (min/max ikisi saxla)
4. **LeetCode 121** — Best Time to Buy and Sell Stock (Kadane variasiyası)
5. **LeetCode 1749** — Maximum Absolute Sum of Any Subarray (max + min Kadane)

### Step-by-step: Maximum Product Subarray

```
nums = [2, 3, -2, 4]

          i=0  i=1  i=2  i=3
maxProd:   2    6   -2    4   ← max(nums[i], maxP*nums[i], minP*nums[i])
minProd:   2    3  -12  -8   ← min(nums[i], maxP*nums[i], minP*nums[i])
result:    2    6    6    6

Mənfi ədəddə max↔min swap olur: -2*(-12)=24 — amma bu misalda 6 qalib.

nums = [2, -5, -2, -4, 3]:
maxProd:   2  -5   50  -100   3
minProd:   2 -10    2   -50 -300
result:    2   2   50    50   50 ✓
```

---

## Əlaqəli Mövzular

- [02-arrays.md](02-arrays.md) — Array əsasları
- [23-dynamic-programming.md](23-dynamic-programming.md) — Kadane = 1D DP
- [10-prefix-sum.md](10-prefix-sum.md) — Prefix sum ilə əlaqəsi (max prefix[j] - min prefix[i])
- [30-matrix-problems.md](30-matrix-problems.md) — 2D Kadane (max sum submatrix)
