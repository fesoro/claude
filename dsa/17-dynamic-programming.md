# Dynamic Programming (Dinamik Proqramlama)

## Konsept (Concept)

Dynamic Programming (DP) boyuk problemi kicik alt-problemlere bolub, her alt-problemi yalniz bir defe hell edib, neticesini saxlayir. Iki esl sert var: **overlapping subproblems** ve **optimal substructure**.

```
Fibonacci numumesi:

Naive recursion (tekrar hesablama):
fib(5) = fib(4) + fib(3)
fib(4) = fib(3) + fib(2)   <- fib(3) tekrar!
fib(3) = fib(2) + fib(1)   <- fib(2) tekrar!

DP ile (her birini 1 defe hesabla):
dp[0]=0, dp[1]=1
dp[2]=dp[0]+dp[1]=1
dp[3]=dp[1]+dp[2]=2
dp[4]=dp[2]+dp[3]=3
dp[5]=dp[3]+dp[4]=5
```

### Top-Down vs Bottom-Up:
```
Top-Down (Memoization):        Bottom-Up (Tabulation):
- Recursion + cache             - Iterative + table
- Lazy: yalniz lazim olani      - Eager: hamisi hesablanir
  hesabla                       - Daha az overhead (no recursion)
- Daha intuitiv                 - Space optimization mumkun
```

### DP Pattern-leri:
1. **Linear DP**: dp[i] eyvallah dp[i-1], dp[i-2]... den asilidir
2. **Grid DP**: dp[i][j] qonsu cell-lerden asilidir
3. **String DP**: dp[i][j] iki string-in prefix-lerinden asilidir
4. **Interval DP**: dp[i][j] [i..j] araligindan asilidir
5. **Tree DP**: dp[node] usaq node-lardan asilidir
6. **Bitmask DP**: dp[mask] set-in alt-setlerinden asilidir

## Nece Isleyir? (How does it work?)

### DP hell addamlari:
```
1. State mueyyenlesdir: dp[i] ne ifade edir?
2. Transition taplin: dp[i] nece hesablanir?
3. Base case yaz: dp[0] = ?, dp[1] = ?
4. Siralamanin istiqameti: soldan saga? asagidan yuxari?
5. Cavab harada: dp[n]? max(dp[i])?

Misal - Climbing Stairs:
  State:    dp[i] = i-ci pillekeye catmaq yollarinin sayi
  Transition: dp[i] = dp[i-1] + dp[i-2]  (1 ve ya 2 addim ata bilerik)
  Base case: dp[0] = 1, dp[1] = 1
  Answer:   dp[n]
```

### Grid DP numumesi:
```
Minimum Path Sum (sol yuxaridan sag asagiya):
Grid:           DP Table:
[1, 3, 1]      [1, 4, 5]
[1, 5, 1]  ->  [2, 7, 6]
[4, 2, 1]      [6, 8, 7]

dp[i][j] = grid[i][j] + min(dp[i-1][j], dp[i][j-1])
Cavab: dp[2][2] = 7
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Climbing Stairs (LeetCode 70)
 * dp[i] = dp[i-1] + dp[i-2]
 */

// Top-Down (Memoization)
function climbStairsMemo(int $n, array &$memo = []): int
{
    if ($n <= 2) return $n;
    if (isset($memo[$n])) return $memo[$n];
    $memo[$n] = climbStairsMemo($n - 1, $memo) + climbStairsMemo($n - 2, $memo);
    return $memo[$n];
}

// Bottom-Up (Tabulation)
function climbStairsTab(int $n): int
{
    if ($n <= 2) return $n;
    $dp = [0, 1, 2];
    for ($i = 3; $i <= $n; $i++) {
        $dp[$i] = $dp[$i - 1] + $dp[$i - 2];
    }
    return $dp[$n];
}

// Space Optimized O(1)
function climbStairs(int $n): int
{
    if ($n <= 2) return $n;
    $prev2 = 1;
    $prev1 = 2;
    for ($i = 3; $i <= $n; $i++) {
        $curr = $prev1 + $prev2;
        $prev2 = $prev1;
        $prev1 = $curr;
    }
    return $prev1;
}

/**
 * Minimum Path Sum (LeetCode 64)
 * dp[i][j] = grid[i][j] + min(dp[i-1][j], dp[i][j-1])
 * Time: O(m*n), Space: O(m*n) -> O(n) optimize oluna biler
 */
function minPathSum(array $grid): int
{
    $m = count($grid);
    $n = count($grid[0]);
    $dp = array_fill(0, $m, array_fill(0, $n, 0));

    $dp[0][0] = $grid[0][0];

    // Ilk setir
    for ($j = 1; $j < $n; $j++) {
        $dp[0][$j] = $dp[0][$j - 1] + $grid[0][$j];
    }
    // Ilk sutun
    for ($i = 1; $i < $m; $i++) {
        $dp[$i][0] = $dp[$i - 1][0] + $grid[$i][0];
    }

    for ($i = 1; $i < $m; $i++) {
        for ($j = 1; $j < $n; $j++) {
            $dp[$i][$j] = $grid[$i][$j] + min($dp[$i - 1][$j], $dp[$i][$j - 1]);
        }
    }

    return $dp[$m - 1][$n - 1];
}

/**
 * Unique Paths (LeetCode 62)
 * dp[i][j] = dp[i-1][j] + dp[i][j-1]
 */
function uniquePaths(int $m, int $n): int
{
    $dp = array_fill(0, $n, 1);

    for ($i = 1; $i < $m; $i++) {
        for ($j = 1; $j < $n; $j++) {
            $dp[$j] += $dp[$j - 1];
        }
    }

    return $dp[$n - 1];
}

/**
 * Maximum Subarray (Kadane's Algorithm) (LeetCode 53)
 * dp[i] = max(nums[i], dp[i-1] + nums[i])
 * Time: O(n), Space: O(1)
 */
function maxSubArray(array $nums): int
{
    $maxSum = $nums[0];
    $currentSum = $nums[0];

    for ($i = 1; $i < count($nums); $i++) {
        $currentSum = max($nums[$i], $currentSum + $nums[$i]);
        $maxSum = max($maxSum, $currentSum);
    }

    return $maxSum;
}

/**
 * House Robber (LeetCode 198)
 * dp[i] = max(dp[i-1], dp[i-2] + nums[i])
 * Time: O(n), Space: O(1)
 */
function rob(array $nums): int
{
    $n = count($nums);
    if ($n === 0) return 0;
    if ($n === 1) return $nums[0];

    $prev2 = 0;
    $prev1 = 0;

    foreach ($nums as $num) {
        $curr = max($prev1, $prev2 + $num);
        $prev2 = $prev1;
        $prev1 = $curr;
    }

    return $prev1;
}

/**
 * Word Break (LeetCode 139)
 * dp[i] = s[0..i] sozlukdeki sozlerle bolunebelirmi?
 * Time: O(n^2 * k), Space: O(n)
 */
function wordBreak(string $s, array $wordDict): bool
{
    $n = strlen($s);
    $dp = array_fill(0, $n + 1, false);
    $dp[0] = true;
    $wordSet = array_flip($wordDict);

    for ($i = 1; $i <= $n; $i++) {
        for ($j = 0; $j < $i; $j++) {
            if ($dp[$j] && isset($wordSet[substr($s, $j, $i - $j)])) {
                $dp[$i] = true;
                break;
            }
        }
    }

    return $dp[$n];
}

/**
 * Decode Ways (LeetCode 91)
 * dp[i] = s[0..i] nece decode oluna biler?
 * Time: O(n), Space: O(1)
 */
function numDecodings(string $s): int
{
    if ($s[0] === '0') return 0;
    $n = strlen($s);
    $prev2 = 1;
    $prev1 = 1;

    for ($i = 1; $i < $n; $i++) {
        $curr = 0;
        if ($s[$i] !== '0') {
            $curr += $prev1;
        }
        $twoDigit = (int)substr($s, $i - 1, 2);
        if ($twoDigit >= 10 && $twoDigit <= 26) {
            $curr += $prev2;
        }
        $prev2 = $prev1;
        $prev1 = $curr;
    }

    return $prev1;
}

// --- Test ---
echo "Climb 5 stairs: " . climbStairs(5) . "\n"; // 8
echo "Max subarray [-2,1,-3,4,-1,2,1,-5,4]: " . maxSubArray([-2,1,-3,4,-1,2,1,-5,4]) . "\n"; // 6
echo "House robber [2,7,9,3,1]: " . rob([2,7,9,3,1]) . "\n"; // 12
echo "Unique paths 3x7: " . uniquePaths(3, 7) . "\n"; // 28
echo "Word break: " . (wordBreak("leetcode", ["leet","code"]) ? 'true' : 'false') . "\n"; // true

$grid = [[1,3,1],[1,5,1],[4,2,1]];
echo "Min path sum: " . minPathSum($grid) . "\n"; // 7
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space | Space Optimized |
|---------|------|-------|-----------------|
| Climbing Stairs | O(n) | O(n) | O(1) |
| Min Path Sum | O(mn) | O(mn) | O(n) |
| Unique Paths | O(mn) | O(mn) | O(n) |
| Max Subarray | O(n) | O(1) | - |
| House Robber | O(n) | O(n) | O(1) |
| Word Break | O(n^2) | O(n) | - |

## Tipik Meseler (Common Problems)

### 1. Longest Increasing Subsequence (LeetCode 300)
```php
<?php
function lengthOfLIS(array $nums): int
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
// Time: O(n^2), O(n log n) binary search ile mumkun
```

### 2. Coin Change (LeetCode 322)
```php
<?php
function coinChange(array $coins, int $amount): int
{
    $dp = array_fill(0, $amount + 1, $amount + 1);
    $dp[0] = 0;

    for ($i = 1; $i <= $amount; $i++) {
        foreach ($coins as $coin) {
            if ($coin <= $i) {
                $dp[$i] = min($dp[$i], $dp[$i - $coin] + 1);
            }
        }
    }

    return $dp[$amount] > $amount ? -1 : $dp[$amount];
}
```

### 3. 0/1 Knapsack
```php
<?php
function knapsack(array $weights, array $values, int $capacity): int
{
    $n = count($weights);
    $dp = array_fill(0, $capacity + 1, 0);

    for ($i = 0; $i < $n; $i++) {
        for ($w = $capacity; $w >= $weights[$i]; $w--) {
            $dp[$w] = max($dp[$w], $dp[$w - $weights[$i]] + $values[$i]);
        }
    }

    return $dp[$capacity];
}
```

## Interview Suallari

1. **DP problemi nece taniyin?**
   - "Minimum/maximum", "nece yol var", "mumkundurmu" kimi suallar
   - Overlapping subproblems: eyni alt-problem tekrar hell olunur
   - Optimal substructure: optimal hell optimal alt-helllerden qurulur

2. **Top-down vs Bottom-up?**
   - Top-down: daha intuitiv, lazim olanlari hesabla, stack overflow riski
   - Bottom-up: daha effektiv, butun alt-problemleri hesabla, space optimize oluna biler
   - Interview-da evelce top-down yaz, sonra bottom-up cevir

3. **DP state nece mueyyenlesdirirsiniz?**
   - Problemi xarakterize eden en az parametrleri tap
   - Meselen: index, remaining capacity, previous choice
   - State kicik olmalidi (yoxsa TLE olur)

4. **Space optimization nece edilir?**
   - dp[i] yalniz dp[i-1] ve dp[i-2]-den asilidirgsa, 2 deyisken bes edir
   - 2D DP-de yalniz eyvallah setir lazimdirgsa, 1D array bes edir

## PHP/Laravel ile Elaqe

- **Caching**: Laravel Cache DP-nin memoization prinsipi ile eynidir
- **Route optimization**: Delivery route hesablamasi DP ile
- **Text diff**: `diff` aleti DP (LCS) istifade edir
- **Pricing calculations**: Discount/tax hesablamalari DP pattern
- **Auto-complete**: Edit distance ile en yaxin sozleri tap
