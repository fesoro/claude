# Prefix Sum (Middle)

## Konsept (Concept)

**Prefix Sum** — massivdə başdan itibarən cəmi hesablayıb saxlayan texnikadır. Bu, range sum query problemlərini O(1) həll etməyə imkan verir.

```
Array:      [3, 1, 4, 1, 5, 9, 2, 6]
Prefix Sum: [3, 4, 8, 9, 14, 23, 25, 31]

prefix[i] = nums[0] + nums[1] + ... + nums[i]

Range sum [l..r] = prefix[r] - prefix[l-1]
Misal: Sum[2..5] = prefix[5] - prefix[1] = 23 - 4 = 19
       = 4+1+5+9 = 19 ✓
```

### Ne vaxt istifadə edilir?
- **Range Sum Query** problemleri (immutable array)
- **Subarray Sum Equals K**
- **Count subarrays divisible by K**
- **Maximum subarray sum** (Kadane'ye alternativ)
- **2D Matrix range sum**
- **Difference Array** (range update)

### Variasiyaları:
1. **1D Prefix Sum**: tek-olculu
2. **2D Prefix Sum**: matrisdə sahe cemi
3. **Prefix Product**: vurma
4. **Prefix XOR**: XOR cemi
5. **Difference Array**: range update üçün prefix sum-un tərsi

## Necə İşləyir? (How does it work?)

### 1D Prefix Sum:
```
nums = [2, 4, -1, 3, 6]
prefix[0] = 2
prefix[1] = 2 + 4 = 6
prefix[2] = 6 + (-1) = 5
prefix[3] = 5 + 3 = 8
prefix[4] = 8 + 6 = 14

Range [1..3] cemi:
= prefix[3] - prefix[0]
= 8 - 2 = 6
Yoxlama: 4 + (-1) + 3 = 6 ✓
```

### 2D Prefix Sum:
```
matrix:
[3, 2, 1]
[1, 5, 4]
[4, 2, 3]

prefix[i][j] = sol üst küncdən (i,j)-ye qeder cem

prefix:
[3,  5,  6]
[4, 11, 16]
[8, 17, 25]

prefix[i][j] = matrix[i][j] + prefix[i-1][j] + prefix[i][j-1] - prefix[i-1][j-1]

Range sum (r1,c1) -> (r2,c2):
= prefix[r2][c2] - prefix[r1-1][c2] - prefix[r2][c1-1] + prefix[r1-1][c1-1]

Misal: sum(1,1)->(2,2) = 5+4+2+3 = 14
     = prefix[2][2] - prefix[0][2] - prefix[2][0] + prefix[0][0]
     = 25 - 6 - 8 + 3 = 14 ✓
```

### Subarray Sum Equals K:
```
nums = [1, 2, 3], k = 3

Prefix sum: [1, 3, 6]

Hash map ile izleyirik:
map = {0: 1} (baslangic)

i=0, prefix=1: 1-3=-2, map-də yoxdur. count=0. map={0:1, 1:1}
i=1, prefix=3: 3-3=0, map-də var (1 dəfə). count=1. map={0:1, 1:1, 3:1}
i=2, prefix=6: 6-3=3, map-də var (1 dəfə). count=2. map={...}

Result: 2 subarray [1,2] ve [3]
```

### Difference Array:
```
Original: [1, 3, 5, 7, 9]
Diff:     [1, 2, 2, 2, 2]  (diff[i] = a[i] - a[i-1])

Range update [1..3] += 4:
Diff: [1, 6, 2, 2, -4]  (diff[1] += 4, diff[4] -= 4)

Rekonstruksiya (prefix sum diff-den):
[1, 7, 9, 11, 7]
= original [1, 3+4, 5+4, 7+4, 9]
```

## Implementasiya (Implementation)

```php
<?php

class PrefixSum
{
    private array $prefix;

    public function __construct(array $nums)
    {
        $this->prefix = [];
        $sum = 0;
        foreach ($nums as $num) {
            $sum += $num;
            $this->prefix[] = $sum;
        }
    }

    // Range sum [l, r] (inclusive)
    public function sumRange(int $l, int $r): int
    {
        if ($l === 0) return $this->prefix[$r];
        return $this->prefix[$r] - $this->prefix[$l - 1];
    }
}

class PrefixSum2D
{
    private array $prefix;

    public function __construct(array $matrix)
    {
        if (empty($matrix)) {
            $this->prefix = [];
            return;
        }

        $m = count($matrix);
        $n = count($matrix[0]);
        // (m+1) x (n+1) matrix, sol ve yuxarı 0 padding
        $this->prefix = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $this->prefix[$i][$j] = $matrix[$i - 1][$j - 1]
                    + $this->prefix[$i - 1][$j]
                    + $this->prefix[$i][$j - 1]
                    - $this->prefix[$i - 1][$j - 1];
            }
        }
    }

    public function sumRegion(int $r1, int $c1, int $r2, int $c2): int
    {
        $r1++; $c1++; $r2++; $c2++;
        return $this->prefix[$r2][$c2]
             - $this->prefix[$r1 - 1][$c2]
             - $this->prefix[$r2][$c1 - 1]
             + $this->prefix[$r1 - 1][$c1 - 1];
    }
}

class PrefixSumProblems
{
    // Subarray Sum Equals K
    public function subarraySum(array $nums, int $k): int
    {
        $count = 0;
        $sum = 0;
        $map = [0 => 1]; // prefix sum -> count

        foreach ($nums as $num) {
            $sum += $num;
            if (isset($map[$sum - $k])) {
                $count += $map[$sum - $k];
            }
            $map[$sum] = ($map[$sum] ?? 0) + 1;
        }

        return $count;
    }

    // Continuous Subarray Sum multiple of K
    public function checkSubarraySum(array $nums, int $k): bool
    {
        $map = [0 => -1]; // remainder -> index
        $sum = 0;

        for ($i = 0; $i < count($nums); $i++) {
            $sum += $nums[$i];
            $remainder = $k !== 0 ? $sum % $k : $sum;

            if (isset($map[$remainder])) {
                if ($i - $map[$remainder] >= 2) return true;
            } else {
                $map[$remainder] = $i;
            }
        }

        return false;
    }

    // Subarrays Divisible by K
    public function subarraysDivByK(array $nums, int $k): int
    {
        $map = [0 => 1];
        $sum = 0;
        $count = 0;

        foreach ($nums as $num) {
            $sum += $num;
            $remainder = (($sum % $k) + $k) % $k; // menfi üçün normalize

            if (isset($map[$remainder])) {
                $count += $map[$remainder];
            }
            $map[$remainder] = ($map[$remainder] ?? 0) + 1;
        }

        return $count;
    }

    // Product of Array Except Self (prefix + suffix product)
    public function productExceptSelf(array $nums): array
    {
        $n = count($nums);
        $result = array_fill(0, $n, 1);

        // Left products
        $leftProduct = 1;
        for ($i = 0; $i < $n; $i++) {
            $result[$i] = $leftProduct;
            $leftProduct *= $nums[$i];
        }

        // Right products multiply
        $rightProduct = 1;
        for ($i = $n - 1; $i >= 0; $i--) {
            $result[$i] *= $rightProduct;
            $rightProduct *= $nums[$i];
        }

        return $result;
    }

    // Find Pivot Index
    public function pivotIndex(array $nums): int
    {
        $total = array_sum($nums);
        $leftSum = 0;

        for ($i = 0; $i < count($nums); $i++) {
            if ($leftSum === $total - $leftSum - $nums[$i]) {
                return $i;
            }
            $leftSum += $nums[$i];
        }

        return -1;
    }
}

class DifferenceArray
{
    private array $diff;
    private int $n;

    public function __construct(array $nums)
    {
        $this->n = count($nums);
        $this->diff = [$nums[0]];
        for ($i = 1; $i < $this->n; $i++) {
            $this->diff[] = $nums[$i] - $nums[$i - 1];
        }
    }

    // Range update [l..r] += val
    public function update(int $l, int $r, int $val): void
    {
        $this->diff[$l] += $val;
        if ($r + 1 < $this->n) {
            $this->diff[$r + 1] -= $val;
        }
    }

    // Cari array tapmaq
    public function result(): array
    {
        $nums = [$this->diff[0]];
        for ($i = 1; $i < $this->n; $i++) {
            $nums[] = $nums[$i - 1] + $this->diff[$i];
        }
        return $nums;
    }
}

// Istifade
$ps = new PrefixSum([1, 2, 3, 4, 5]);
echo $ps->sumRange(1, 3); // 2+3+4 = 9

$ps2d = new PrefixSum2D([
    [3, 0, 1, 4, 2],
    [5, 6, 3, 2, 1],
    [1, 2, 0, 1, 5]
]);
echo $ps2d->sumRegion(1, 1, 2, 2); // 6+3+2+0 = 11

$problems = new PrefixSumProblems();
echo $problems->subarraySum([1, 1, 1], 2); // 2

$diff = new DifferenceArray([1, 2, 3, 4, 5]);
$diff->update(1, 3, 10); // [1..3]-e 10 elave et
print_r($diff->result()); // [1, 12, 13, 14, 5]
```

## Vaxt və Yaddaş Mürəkkəbliyi (Time & Space Complexity)

| Əməliyyat | Time | Space |
|-----------|------|-------|
| 1D Prefix Build | O(n) | O(n) |
| 1D Range Query | O(1) | - |
| 2D Prefix Build | O(m*n) | O(m*n) |
| 2D Range Query | O(1) | - |
| Subarray Sum = K | O(n) | O(n) |
| Difference Array Update | O(1) | - |
| Difference Array Query | O(n) | - |

## Tipik Məsələlər (Common Problems)

### 1. Maximum Size Subarray Sum Equals K
```php
public function maxSubArrayLen(array $nums, int $k): int
{
    $sum = 0;
    $map = [0 => -1];
    $maxLen = 0;

    for ($i = 0; $i < count($nums); $i++) {
        $sum += $nums[$i];
        if (isset($map[$sum - $k])) {
            $maxLen = max($maxLen, $i - $map[$sum - $k]);
        }
        if (!isset($map[$sum])) {
            $map[$sum] = $i;
        }
    }

    return $maxLen;
}
// nums=[1,-1,5,-2,3], k=3 -> 4 ([1,-1,5,-2])
```

### 2. Contiguous Array (eqal 0 ve 1)
```php
public function findMaxLength(array $nums): int
{
    $map = [0 => -1];
    $sum = 0;
    $maxLen = 0;

    for ($i = 0; $i < count($nums); $i++) {
        $sum += $nums[$i] === 1 ? 1 : -1;

        if (isset($map[$sum])) {
            $maxLen = max($maxLen, $i - $map[$sum]);
        } else {
            $map[$sum] = $i;
        }
    }

    return $maxLen;
}
// nums=[0,1,0,0,1,1,0] -> 6 ([1,0,0,1,1,0] və ya oxşar)
```

### 3. Minimum Operations to Reduce X to Zero
```php
public function minOperations(array $nums, int $x): int
{
    $target = array_sum($nums) - $x;
    if ($target < 0) return -1;

    $left = 0; $sum = 0; $maxLen = -1;
    for ($right = 0; $right < count($nums); $right++) {
        $sum += $nums[$right];
        while ($sum > $target && $left <= $right) {
            $sum -= $nums[$left];
            $left++;
        }
        if ($sum === $target) {
            $maxLen = max($maxLen, $right - $left + 1);
        }
    }

    return $maxLen === -1 ? -1 : count($nums) - $maxLen;
}
```

### 4. Range Addition (Difference Array)
```php
public function getModifiedArray(int $length, array $updates): array
{
    $diff = array_fill(0, $length + 1, 0);
    foreach ($updates as [$start, $end, $inc]) {
        $diff[$start] += $inc;
        $diff[$end + 1] -= $inc;
    }

    $result = [];
    $sum = 0;
    for ($i = 0; $i < $length; $i++) {
        $sum += $diff[$i];
        $result[] = $sum;
    }
    return $result;
}
```

### 5. Number of Ways to Split Array
```php
public function waysToSplitArray(array $nums): int
{
    $total = array_sum($nums);
    $leftSum = 0;
    $count = 0;

    for ($i = 0; $i < count($nums) - 1; $i++) {
        $leftSum += $nums[$i];
        if ($leftSum >= $total - $leftSum) {
            $count++;
        }
    }

    return $count;
}
```

## Interview Sualları

1. **Prefix sum ile range sum-un time complexity-si niye O(1)?**
   - Əvvəlceden hesablanmış prefix array-dan iki index-in fərqini götürürük.

2. **Subarray Sum Equals K üçün niye hash map istifade edirik?**
   - `prefix[j] - prefix[i] = k` -> `prefix[i] = prefix[j] - k`. Keçmiş prefix-lerin sayını O(1) tapmaq üçün map lazımdır.

3. **2D prefix sum-da niye inclusion-exclusion prinsipi istifade olunur?**
   - Sol üst hissə iki dəfə hesablanıb, ondan bir dəfə çıxmaq lazımdır.

4. **Difference array nə vaxt prefix sum-dan üstündür?**
   - Range update çox və point query az olanda. O(1) update, O(n) reconstruct.

5. **Modular arithmetic ilə subarrays divisible by K niye remainder-leri saxlayır?**
   - `(sum1 - sum2) % k == 0` -> `sum1 % k == sum2 % k`. Eyni remainder-e malik prefix-ler arasında bölünebilir subarray var.

6. **Negative ededler olan massivdə prefix sum işləyir?**
   - Bəli, prefix sum concept-i müsbət/mənfi-den asılı deyil. Amma sliding window işləməyə bilər.

7. **Prefix sum vs Fenwick tree (BIT) fərqi?**
   - Prefix sum: O(1) query, O(n) update.
   - Fenwick tree: O(log n) query, O(log n) update. Dinamik array üçün üstündür.

## PHP/Laravel ilə Əlaqə

### Sales analytics:
```php
// Gunluk satis mebleqleri, range query ucun prefix sum
class SalesAnalytics
{
    private array $prefix;

    public function __construct()
    {
        $sales = DB::table('daily_sales')
            ->orderBy('date')
            ->pluck('amount')
            ->toArray();

        $this->prefix = [];
        $sum = 0;
        foreach ($sales as $s) {
            $sum += $s;
            $this->prefix[] = $sum;
        }
    }

    public function totalForPeriod(int $fromDay, int $toDay): float
    {
        return $fromDay === 0
            ? $this->prefix[$toDay]
            : $this->prefix[$toDay] - $this->prefix[$fromDay - 1];
    }
}
```

### Image processing (2D prefix):
```php
// Integral image, box filter ucun
class IntegralImage
{
    private array $integral;

    public function __construct(array $image)
    {
        // 2D prefix sum ile box blur O(1)-de hesablanır
    }

    public function boxSum(int $x1, int $y1, int $x2, int $y2): int
    {
        // Her pixel ucun O(1)
    }
}
```

### Real-time rate limiting (difference array):
```php
// N saniyelik window-da request sayı
Redis::pipeline(function ($pipe) use ($userId) {
    $now = time();
    $pipe->zadd("requests:$userId", $now, $now);
    $pipe->zremrangebyscore("requests:$userId", 0, $now - 60);
    $pipe->zcard("requests:$userId");
});
```

### Database heat map:
```php
// Laravel scheduler ile gunluk metrics
class UserActivityReport
{
    public function heatmap(Carbon $start, Carbon $end): array
    {
        $metrics = DB::table('hourly_metrics')
            ->whereBetween('hour', [$start, $end])
            ->get();

        // Prefix sum ile range query
    }
}
```

### Stock trading:
```php
// Continuous profit hesabı
$prices = [...]; // gunluk qiymetler
$priceDiff = [];
for ($i = 1; $i < count($prices); $i++) {
    $priceDiff[] = $prices[$i] - $prices[$i - 1];
}
// Kadane algorithm ile max subarray = max profit ardıcıllığı
```

### Real nümunələr:
1. **Cumulative sales reports** - dashboard-da periyodik cemiler
2. **Moving averages** - finance/trading
3. **Heatmap generation** - user activity analysis
4. **Log aggregation** - error counts over time
5. **Resource usage tracking** - CPU/memory history
