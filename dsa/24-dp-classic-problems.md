# DP Classic Problems (Senior)

## Konsept (Concept)

Bu faylda en cox sorusulan DP meselelerinin tam helli verilir. Her mesele ucun state, transition, base case ve optimization gosterilir.

```
Klassik DP Pattern-leri:
1. Fibonacci-type: dp[i] = f(dp[i-1], dp[i-2])
2. Knapsack-type:  dp[i][w] = max(include, exclude)
3. String-type:    dp[i][j] = f(dp[i-1][j], dp[i][j-1], dp[i-1][j-1])
4. Partition-type: dp[i] = min/max over all partitions
5. Interval-type:  dp[i][j] = f(dp[i][k], dp[k][j])
```

## Implementasiya (Implementation)

### 1. Fibonacci (LeetCode 509)
```php
<?php
/**
 * State: dp[i] = i-ci Fibonacci reqemi
 * Transition: dp[i] = dp[i-1] + dp[i-2]
 * Time: O(n), Space: O(1)
 */
function fib(int $n): int
{
    if ($n <= 1) return $n;
    $a = 0;
    $b = 1;
    for ($i = 2; $i <= $n; $i++) {
        [$a, $b] = [$b, $a + $b];
    }
    return $b;
}
```

### 2. Climbing Stairs (LeetCode 70)
```php
<?php
/**
 * State: dp[i] = i-ci pillekeye catmaq yollarinin sayi
 * Transition: dp[i] = dp[i-1] + dp[i-2]
 * Time: O(n), Space: O(1)
 */
function climbStairs(int $n): int
{
    if ($n <= 2) return $n;
    $a = 1;
    $b = 2;
    for ($i = 3; $i <= $n; $i++) {
        [$a, $b] = [$b, $a + $b];
    }
    return $b;
}
```

### 3. House Robber (LeetCode 198)
```php
<?php
/**
 * State: dp[i] = ilk i evin max qazanci
 * Transition: dp[i] = max(dp[i-1], dp[i-2] + nums[i])
 * Time: O(n), Space: O(1)
 */
function rob(array $nums): int
{
    $prev2 = 0;
    $prev1 = 0;
    foreach ($nums as $num) {
        [$prev2, $prev1] = [$prev1, max($prev1, $prev2 + $num)];
    }
    return $prev1;
}

// House Robber II (LeetCode 213) - circular
function robII(array $nums): int
{
    $n = count($nums);
    if ($n === 1) return $nums[0];
    return max(
        robRange($nums, 0, $n - 2),
        robRange($nums, 1, $n - 1)
    );
}

function robRange(array $nums, int $start, int $end): int
{
    $prev2 = 0;
    $prev1 = 0;
    for ($i = $start; $i <= $end; $i++) {
        [$prev2, $prev1] = [$prev1, max($prev1, $prev2 + $nums[$i])];
    }
    return $prev1;
}
```

### 4. Coin Change (LeetCode 322)
```php
<?php
/**
 * State: dp[i] = i meblegi ucun minimum coin sayi
 * Transition: dp[i] = min(dp[i - coin] + 1) for each coin
 * Time: O(amount * coins), Space: O(amount)
 */
function coinChange(array $coins, int $amount): int
{
    $dp = array_fill(0, $amount + 1, $amount + 1);
    $dp[0] = 0;

    for ($i = 1; $i <= $amount; $i++) {
        foreach ($coins as $coin) {
            if ($coin <= $i && $dp[$i - $coin] + 1 < $dp[$i]) {
                $dp[$i] = $dp[$i - $coin] + 1;
            }
        }
    }

    return $dp[$amount] > $amount ? -1 : $dp[$amount];
}

// Coin Change 2 (LeetCode 518) - nece yol var?
function coinChange2(int $amount, array $coins): int
{
    $dp = array_fill(0, $amount + 1, 0);
    $dp[0] = 1;

    foreach ($coins as $coin) {
        for ($i = $coin; $i <= $amount; $i++) {
            $dp[$i] += $dp[$i - $coin];
        }
    }

    return $dp[$amount];
}
```

### 5. 0/1 Knapsack
```php
<?php
/**
 * State: dp[i][w] = ilk i esya ile w capacity-da max deyer
 * Transition: dp[i][w] = max(dp[i-1][w], dp[i-1][w-weight[i]] + value[i])
 * Time: O(n * W), Space: O(W)
 */
function knapsack(array $weights, array $values, int $W): int
{
    $n = count($weights);
    $dp = array_fill(0, $W + 1, 0);

    for ($i = 0; $i < $n; $i++) {
        for ($w = $W; $w >= $weights[$i]; $w--) { // Sondan basla (0/1 ucun)
            $dp[$w] = max($dp[$w], $dp[$w - $weights[$i]] + $values[$i]);
        }
    }

    return $dp[$W];
}

// Hansi esyalarin secildiyini tap
function knapsackWithItems(array $weights, array $values, int $W): array
{
    $n = count($weights);
    $dp = array_fill(0, $n + 1, array_fill(0, $W + 1, 0));

    for ($i = 1; $i <= $n; $i++) {
        for ($w = 0; $w <= $W; $w++) {
            $dp[$i][$w] = $dp[$i - 1][$w];
            if ($weights[$i - 1] <= $w) {
                $dp[$i][$w] = max($dp[$i][$w], $dp[$i - 1][$w - $weights[$i - 1]] + $values[$i - 1]);
            }
        }
    }

    // Backtrack
    $items = [];
    $w = $W;
    for ($i = $n; $i > 0; $i--) {
        if ($dp[$i][$w] !== $dp[$i - 1][$w]) {
            $items[] = $i - 1;
            $w -= $weights[$i - 1];
        }
    }

    return ['maxValue' => $dp[$n][$W], 'items' => array_reverse($items)];
}
```

### 6. Longest Common Subsequence - LCS (LeetCode 1143)
```php
<?php
/**
 * State: dp[i][j] = text1[0..i-1] ve text2[0..j-1]-in LCS uzunlugu
 * Transition: match -> dp[i-1][j-1]+1, else max(dp[i-1][j], dp[i][j-1])
 * Time: O(m*n), Space: O(m*n) -> O(n)
 */
function longestCommonSubsequence(string $text1, string $text2): int
{
    $m = strlen($text1);
    $n = strlen($text2);
    $prev = array_fill(0, $n + 1, 0);

    for ($i = 1; $i <= $m; $i++) {
        $curr = array_fill(0, $n + 1, 0);
        for ($j = 1; $j <= $n; $j++) {
            if ($text1[$i - 1] === $text2[$j - 1]) {
                $curr[$j] = $prev[$j - 1] + 1;
            } else {
                $curr[$j] = max($prev[$j], $curr[$j - 1]);
            }
        }
        $prev = $curr;
    }

    return $prev[$n];
}

// LCS string-ini qaytaran versiya
function lcsString(string $text1, string $text2): string
{
    $m = strlen($text1);
    $n = strlen($text2);
    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            if ($text1[$i - 1] === $text2[$j - 1]) {
                $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }
    }

    // Backtrack
    $result = '';
    $i = $m;
    $j = $n;
    while ($i > 0 && $j > 0) {
        if ($text1[$i - 1] === $text2[$j - 1]) {
            $result = $text1[$i - 1] . $result;
            $i--;
            $j--;
        } elseif ($dp[$i - 1][$j] > $dp[$i][$j - 1]) {
            $i--;
        } else {
            $j--;
        }
    }

    return $result;
}
```

### 7. Longest Increasing Subsequence - LIS (LeetCode 300)
```php
<?php
/**
 * O(n^2) versiya:
 * State: dp[i] = nums[i] ile biten LIS uzunlugu
 * Transition: dp[i] = max(dp[j] + 1) for all j < i where nums[j] < nums[i]
 */
function lisQuadratic(array $nums): int
{
    $n = count($nums);
    $dp = array_fill(0, $n, 1);

    for ($i = 1; $i < $n; $i++) {
        for ($j = 0; $j < $i; $j++) {
            if ($nums[$j] < $nums[$i]) {
                $dp[$i] = max($dp[$i], $dp[$j] + 1);
            }
        }
    }

    return max($dp);
}

/**
 * O(n log n) versiya - Patience Sorting
 * tails[i] = uzunlugu i+1 olan LIS-in en kicik son elementi
 */
function lengthOfLIS(array $nums): int
{
    $tails = [];

    foreach ($nums as $num) {
        $lo = 0;
        $hi = count($tails);

        while ($lo < $hi) {
            $mid = (int)(($lo + $hi) / 2);
            if ($tails[$mid] < $num) $lo = $mid + 1;
            else $hi = $mid;
        }

        $tails[$lo] = $num;
    }

    return count($tails);
}
```

### 8. Edit Distance (LeetCode 72)
```php
<?php
/**
 * State: dp[i][j] = word1[0..i-1] -> word2[0..j-1] minimum emeliyyat
 * Transition: match -> dp[i-1][j-1], else 1 + min(insert, delete, replace)
 * Time: O(m*n), Space: O(n)
 */
function minDistance(string $word1, string $word2): int
{
    $m = strlen($word1);
    $n = strlen($word2);
    $prev = range(0, $n);

    for ($i = 1; $i <= $m; $i++) {
        $curr = [$i];
        for ($j = 1; $j <= $n; $j++) {
            if ($word1[$i - 1] === $word2[$j - 1]) {
                $curr[$j] = $prev[$j - 1];
            } else {
                $curr[$j] = 1 + min(
                    $prev[$j],      // delete
                    $curr[$j - 1],   // insert
                    $prev[$j - 1]    // replace
                );
            }
        }
        $prev = $curr;
    }

    return $prev[$n];
}
```

### 9. Longest Palindromic Substring (LeetCode 5)
```php
<?php
/**
 * Expand around center yontemasi
 * Time: O(n^2), Space: O(1)
 */
function longestPalindrome(string $s): string
{
    $start = 0;
    $maxLen = 1;
    $n = strlen($s);

    for ($i = 0; $i < $n; $i++) {
        // Tek uzunluqlu
        [$l1, $r1] = expand($s, $i, $i);
        // Cut uzunluqlu
        [$l2, $r2] = expand($s, $i, $i + 1);

        if ($r1 - $l1 + 1 > $maxLen) {
            $start = $l1;
            $maxLen = $r1 - $l1 + 1;
        }
        if ($r2 - $l2 + 1 > $maxLen) {
            $start = $l2;
            $maxLen = $r2 - $l2 + 1;
        }
    }

    return substr($s, $start, $maxLen);
}

function expand(string $s, int $l, int $r): array
{
    while ($l >= 0 && $r < strlen($s) && $s[$l] === $s[$r]) {
        $l--;
        $r++;
    }
    return [$l + 1, $r - 1];
}

// DP versiya
function longestPalindromeDP(string $s): string
{
    $n = strlen($s);
    $dp = array_fill(0, $n, array_fill(0, $n, false));
    $start = 0;
    $maxLen = 1;

    // Tek herfly
    for ($i = 0; $i < $n; $i++) $dp[$i][$i] = true;

    // Iki herfly
    for ($i = 0; $i < $n - 1; $i++) {
        if ($s[$i] === $s[$i + 1]) {
            $dp[$i][$i + 1] = true;
            $start = $i;
            $maxLen = 2;
        }
    }

    // 3+ herfly
    for ($len = 3; $len <= $n; $len++) {
        for ($i = 0; $i <= $n - $len; $i++) {
            $j = $i + $len - 1;
            if ($s[$i] === $s[$j] && $dp[$i + 1][$j - 1]) {
                $dp[$i][$j] = true;
                $start = $i;
                $maxLen = $len;
            }
        }
    }

    return substr($s, $start, $maxLen);
}
```

### 10. Partition Equal Subset Sum (LeetCode 416)
```php
<?php
/**
 * 0/1 Knapsack varianti
 * Time: O(n * sum), Space: O(sum)
 */
function canPartition(array $nums): bool
{
    $total = array_sum($nums);
    if ($total % 2 !== 0) return false;

    $target = $total / 2;
    $dp = array_fill(0, $target + 1, false);
    $dp[0] = true;

    foreach ($nums as $num) {
        for ($j = $target; $j >= $num; $j--) {
            $dp[$j] = $dp[$j] || $dp[$j - $num];
        }
    }

    return $dp[$target];
}
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space | Pattern |
|---------|------|-------|---------|
| Fibonacci | O(n) | O(1) | Linear |
| Climbing Stairs | O(n) | O(1) | Linear |
| House Robber | O(n) | O(1) | Linear |
| Coin Change | O(n*amount) | O(amount) | Unbounded knapsack |
| 0/1 Knapsack | O(n*W) | O(W) | Knapsack |
| LCS | O(m*n) | O(n) | String |
| LIS | O(n log n) | O(n) | Patience sort |
| Edit Distance | O(m*n) | O(n) | String |
| Palindrome | O(n^2) | O(1) | Interval |
| Partition Sum | O(n*sum) | O(sum) | Knapsack |

## Interview Suallari

1. **Coin Change 1 vs 2 ferqi?**
   - CC1: Minimum coin sayi (min problem)
   - CC2: Nece yol var (count problem)
   - CC1-de inner loop coins, CC2-de outer loop coins (duplicate-dan qac)

2. **0/1 Knapsack vs Unbounded ferqi?**
   - 0/1: Her esya 1 defe, dp loop sondan basla
   - Unbounded: Her esya sonsuz, dp loop basdan basla

3. **LCS ve LIS elaqesi?**
   - LIS aslinda LCS-e reduce oluna biler: LIS(A) = LCS(A, sorted(A))
   - Ikisi de O(n^2) DP, LIS O(n log n) ile optimize oluna biler

4. **Edit Distance harada istifade olunur?**
   - Spell checker, DNA sequence alignment, diff tools
   - Fuzzy search, auto-correct

## PHP/Laravel ile Elaqe

- **Text diff**: `similar_text()`, `levenshtein()` PHP daxili funksiyalaridir
- **Cache optimization**: DP memoization = Laravel cache pattern
- **Pricing engine**: Discount/bundle hesablamalari knapsack problemidir
- **Search suggestion**: Edit distance ile yaxin sozler tap
- **Route planning**: Shortest path DP ile hell olunur

---

## Praktik Tapşırıqlar

1. **LeetCode 62** — Unique Paths (grid DP klassiki)
2. **LeetCode 72** — Edit Distance (Levenshtein distance, 2D DP)
3. **LeetCode 516** — Longest Palindromic Subsequence (interval DP)
4. **LeetCode 312** — Burst Balloons (interval DP, last burst trick)
5. **LeetCode 1312** — Minimum Insertions to Make String Palindrome

### Step-by-step: Edit Distance ("horse" → "ros")

```
    ""  r  o  s
""   0  1  2  3
h    1  1  2  3
o    2  2  1  2
r    3  2  2  2
s    4  3  3  2
e    5  4  4  3  ← answer = 3

dp[i][j] = min(
  dp[i-1][j] + 1,    // delete
  dp[i][j-1] + 1,    // insert
  dp[i-1][j-1] + (s1[i]!=s2[j] ? 1 : 0)  // replace
)
```

---

## Əlaqəli Mövzular

- [23-dynamic-programming.md](23-dynamic-programming.md) — DP nəzəriyyəsi və şablonları
- [37-advanced-dp.md](37-advanced-dp.md) — Bitmask, Digit, Tree, Interval DP
- [07-recursion.md](07-recursion.md) — Top-down həll yanaşması
- [11-binary-search-patterns.md](11-binary-search-patterns.md) — DP + binary search (LIS O(n log n))
