# Advanced Dynamic Programming (Lead)

## Konsept (Concept)

**Advanced DP** — klassik 1D/2D DP-dən kənara çıxan dörd əsas kateqoriyadır:

1. **Bitmask DP** — State-lər binary mask ilə kodlaşdırılır (TSP, assignment)
2. **Digit DP** — Ədədlərin rəqəmləri üzərində DP (müəyyən xassəli ədədləri say)
3. **Tree DP** — Ağac strukturu üzərində post-order DP (diameter, tree robbery)
4. **Interval DP** — `dp[l][r]` = `[l, r]` aralığı üçün həll (matrix chain, palindrome partition)
5. **DP on Graphs** — DAG-da DP, shortest path DP

### Qeyd
Bütün advanced DP-lər üç komponent tələb edir:
- **State**: problem halını dəqiq təsvir edən minimum parametrlər
- **Transition**: hallar arasındakı əlaqə
- **Base case + answer**: hardan başlanır və cavab harada

## Necə İşləyir?

### Bitmask DP
`n ≤ 20` olanda istifadə olunur. `mask` binary rəqəmlə `n` element arasından hansılarının artıq seçildiyini göstərir.
```
dp[mask] = optimum result having processed the subset mask
transition: iterate next element i ∉ mask
```

### Digit DP
Ədədin hər rəqəmini sol-dan sağa emal et:
```
dp[pos][tight][prevState...]
tight = hələ də yuxarı limitdə saxlanılırıq?
```

### Tree DP
Post-order traversal — əvvəl övladlardan məlumat yığ, sonra parent-də birləşdir.

### Interval DP
Uzunluğu artıraraq: `for len = 2..n; for l = 0..n-len; r = l+len-1; dp[l][r] = ...`

## İmplementasiya (Implementation) - PHP

### 1. Bitmask DP — Travelling Salesman (TSP)

```php
// n şəhər, hər cüt arasında məsafə. Ən qısa Hamilton dövrəsini tap.
function tsp(array $dist): int {
    $n = count($dist);
    $INF = PHP_INT_MAX / 2;
    $dp = array_fill(0, 1 << $n, array_fill(0, $n, $INF));
    $dp[1][0] = 0; // start at city 0

    for ($mask = 1; $mask < (1 << $n); $mask++) {
        if (!($mask & 1)) continue; // must contain city 0
        for ($u = 0; $u < $n; $u++) {
            if (!($mask & (1 << $u))) continue;
            if ($dp[$mask][$u] === $INF) continue;
            for ($v = 0; $v < $n; $v++) {
                if ($mask & (1 << $v)) continue;
                $newMask = $mask | (1 << $v);
                $dp[$newMask][$v] = min(
                    $dp[$newMask][$v],
                    $dp[$mask][$u] + $dist[$u][$v]
                );
            }
        }
    }

    $fullMask = (1 << $n) - 1;
    $ans = $INF;
    for ($u = 1; $u < $n; $u++) {
        $ans = min($ans, $dp[$fullMask][$u] + $dist[$u][0]);
    }
    return $ans;
}
```

### 2. Bitmask DP — Assignment Problem

```php
// n işçi × n iş. Hər işi tam bir işçiyə ver, ümumi xərci minimize et.
function assignment(array $cost): int {
    $n = count($cost);
    $INF = PHP_INT_MAX / 2;
    $dp = array_fill(0, 1 << $n, $INF);
    $dp[0] = 0;
    for ($mask = 0; $mask < (1 << $n); $mask++) {
        if ($dp[$mask] === $INF) continue;
        $worker = substr_count(decbin($mask), '1');
        if ($worker === $n) continue;
        for ($job = 0; $job < $n; $job++) {
            if ($mask & (1 << $job)) continue;
            $newMask = $mask | (1 << $job);
            $dp[$newMask] = min($dp[$newMask], $dp[$mask] + $cost[$worker][$job]);
        }
    }
    return $dp[(1 << $n) - 1];
}
```

### 3. Digit DP — Count numbers ≤ N with digit sum = k

```php
function countWithDigitSum(int $N, int $k): int {
    $digits = array_map('intval', str_split((string)$N));
    $n = count($digits);
    $memo = [];

    $dp = function (int $pos, int $sum, bool $tight) use (&$dp, &$memo, $digits, $n, $k): int {
        if ($pos === $n) return $sum === $k ? 1 : 0;
        $key = "$pos|$sum|" . ($tight ? 1 : 0);
        if (isset($memo[$key])) return $memo[$key];
        $limit = $tight ? $digits[$pos] : 9;
        $res = 0;
        for ($d = 0; $d <= $limit; $d++) {
            if ($sum + $d > $k) break;
            $res += $dp($pos + 1, $sum + $d, $tight && $d === $limit);
        }
        return $memo[$key] = $res;
    };

    return $dp(0, 0, true);
}
```

### 4. Digit DP — Numbers without digit '4'

```php
function countNoFour(int $N): int {
    $digits = array_map('intval', str_split((string)$N));
    $n = count($digits);
    $memo = [];

    $dp = function (int $pos, bool $tight, bool $started) use (&$dp, &$memo, $digits, $n): int {
        if ($pos === $n) return 1;
        $key = "$pos|" . ($tight ? 1 : 0) . "|" . ($started ? 1 : 0);
        if (isset($memo[$key])) return $memo[$key];
        $limit = $tight ? $digits[$pos] : 9;
        $res = 0;
        for ($d = 0; $d <= $limit; $d++) {
            if ($d === 4) continue;
            $res += $dp($pos + 1, $tight && $d === $limit, $started || $d > 0);
        }
        return $memo[$key] = $res;
    };

    return $dp(0, true, false);
}
```

### 5. Tree DP — Diameter of Tree

```php
function treeDiameter(array $adj, int $n): int {
    $diameter = 0;
    $visited = array_fill(0, $n, false);

    $dfs = function (int $u) use (&$dfs, &$adj, &$diameter, &$visited): int {
        $visited[$u] = true;
        $max1 = $max2 = 0;
        foreach ($adj[$u] as $v) {
            if ($visited[$v]) continue;
            $depth = $dfs($v) + 1;
            if ($depth > $max1) { $max2 = $max1; $max1 = $depth; }
            elseif ($depth > $max2) { $max2 = $depth; }
        }
        $diameter = max($diameter, $max1 + $max2);
        return $max1;
    };

    $dfs(0);
    return $diameter;
}
```

### 6. Tree DP — House Robber III (Tree Robbery)

```php
class TreeNode {
    public ?TreeNode $left = null;
    public ?TreeNode $right = null;
    public function __construct(public int $val) {}
}

function robTree(?TreeNode $root): int {
    $dfs = function (?TreeNode $node) use (&$dfs): array {
        if (!$node) return [0, 0]; // [include, exclude]
        [$lIn, $lEx] = $dfs($node->left);
        [$rIn, $rEx] = $dfs($node->right);
        $include = $node->val + $lEx + $rEx;
        $exclude = max($lIn, $lEx) + max($rIn, $rEx);
        return [$include, $exclude];
    };
    [$a, $b] = $dfs($root);
    return max($a, $b);
}
```

### 7. Interval DP — Matrix Chain Multiplication

```php
// dims = [d0, d1, d2, ..., dn] → n matrices: M_i is dims[i] × dims[i+1]
function matrixChain(array $dims): int {
    $n = count($dims) - 1;
    $dp = array_fill(0, $n, array_fill(0, $n, 0));
    for ($len = 2; $len <= $n; $len++) {
        for ($i = 0; $i + $len - 1 < $n; $i++) {
            $j = $i + $len - 1;
            $dp[$i][$j] = PHP_INT_MAX;
            for ($k = $i; $k < $j; $k++) {
                $cost = $dp[$i][$k] + $dp[$k + 1][$j]
                      + $dims[$i] * $dims[$k + 1] * $dims[$j + 1];
                $dp[$i][$j] = min($dp[$i][$j], $cost);
            }
        }
    }
    return $dp[0][$n - 1];
}
```

### 8. Interval DP — Palindrome Partitioning II

```php
// Minimum cuts to make all parts palindrome
function minCuts(string $s): int {
    $n = strlen($s);
    $isPalin = array_fill(0, $n, array_fill(0, $n, false));
    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $i; $j < $n; $j++) {
            if ($s[$i] === $s[$j] && ($j - $i < 2 || $isPalin[$i + 1][$j - 1])) {
                $isPalin[$i][$j] = true;
            }
        }
    }
    $cuts = array_fill(0, $n, 0);
    for ($i = 0; $i < $n; $i++) {
        if ($isPalin[0][$i]) { $cuts[$i] = 0; continue; }
        $cuts[$i] = $i;
        for ($j = 1; $j <= $i; $j++) {
            if ($isPalin[$j][$i]) {
                $cuts[$i] = min($cuts[$i], $cuts[$j - 1] + 1);
            }
        }
    }
    return $cuts[$n - 1];
}
```

### 9. DP on DAG — Longest Path

```php
function longestPathDAG(int $n, array $edges): int {
    $adj = array_fill(0, $n, []);
    $inDeg = array_fill(0, $n, 0);
    foreach ($edges as [$u, $v, $w]) {
        $adj[$u][] = [$v, $w];
        $inDeg[$v]++;
    }
    $queue = [];
    for ($i = 0; $i < $n; $i++) if ($inDeg[$i] === 0) $queue[] = $i;
    $dist = array_fill(0, $n, 0);
    while (!empty($queue)) {
        $u = array_shift($queue);
        foreach ($adj[$u] as [$v, $w]) {
            $dist[$v] = max($dist[$v], $dist[$u] + $w);
            if (--$inDeg[$v] === 0) $queue[] = $v;
        }
    }
    return max($dist);
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| DP növü | Time | Space |
|---------|------|-------|
| Bitmask (TSP) | O(2^n · n²) | O(2^n · n) |
| Bitmask (Assignment) | O(2^n · n) | O(2^n) |
| Digit DP | O(log N · states) | O(log N · states) |
| Tree DP | O(n) | O(n) |
| Interval DP (MCM) | O(n³) | O(n²) |
| DP on DAG | O(V + E) | O(V) |

**Praktiki limit**: Bitmask DP-də n ≤ 20 (2²⁰ ≈ 10⁶).

## Tipik Məsələlər (Common Problems)

### 1. LeetCode 943 — Shortest Superstring (Bitmask DP)
Verilmiş sözlərin ən qısa birləşməsi. n ≤ 12 → bitmask.

### 2. LeetCode 902 — Numbers At Most N Given Digit Set (Digit DP)
```php
function atMostNGivenDigitSet(array $digits, int $n): int {
    $s = (string)$n;
    $k = strlen($s);
    $d = count($digits);
    $result = 0;
    for ($len = 1; $len < $k; $len++) $result += $d ** $len;
    for ($i = 0; $i < $k; $i++) {
        $found = false;
        foreach ($digits as $dg) {
            if ($dg < $s[$i]) $result += $d ** ($k - $i - 1);
            elseif ($dg === $s[$i]) $found = true;
        }
        if (!$found) return $result;
    }
    return $result + 1;
}
```

### 3. LeetCode 337 — House Robber III (Tree DP)
Yuxarıdakı `robTree`.

### 4. LeetCode 312 — Burst Balloons (Interval DP)
```php
function maxCoins(array $nums): int {
    $nums = [1, ...$nums, 1];
    $n = count($nums);
    $dp = array_fill(0, $n, array_fill(0, $n, 0));
    for ($len = 2; $len < $n; $len++) {
        for ($l = 0; $l + $len < $n; $l++) {
            $r = $l + $len;
            for ($k = $l + 1; $k < $r; $k++) {
                $dp[$l][$r] = max($dp[$l][$r],
                    $nums[$l] * $nums[$k] * $nums[$r] + $dp[$l][$k] + $dp[$k][$r]);
            }
        }
    }
    return $dp[0][$n - 1];
}
```

### 5. LeetCode 198 — House Robber (klassik, istinad)
Advanced olmayanı Tree DP-yə genişləndirdik.

## Interview Sualları

**1. Bitmask DP nə vaxt istifadə olunur?**
`n ≤ 20` olanda və hər element iki statuslu olanda (seçilib / seçilməyib). Məsələlər: TSP, assignment, subset enumeration with constraints.

**2. Bitmask əməliyyatları?**
- Set i-th bit: `mask | (1 << i)`
- Clear i-th bit: `mask & ~(1 << i)`
- Check i-th bit: `(mask >> i) & 1`
- Count bits: `substr_count(decbin($mask), '1')` və ya Brian Kernighan

**3. Digit DP-də `tight` flag nə üçündür?**
Əgər əvvəlki rəqəmlər tam olaraq N-ə bərabər gəlibsə, növbəti rəqəm N-in rəqəmindən böyük ola bilməz. `tight = true` olanda limit `digits[pos]`-dir; false olanda 9.

**4. Tree DP-də "parent-child" info necə ötürülür?**
Post-order DFS ilə — əvvəl bütün övladlar üçün DP hesablanır, sonra parent-də birləşdirilir. Re-rooting DP (bütün node-lar root kimi düşünülür) üçün 2 DFS lazımdır.

**5. Interval DP-də niyə uzunluq üzrə iterasiya?**
`dp[l][r]` kiçik subinterval-lardan asılıdır. Uzunluğu artırmaqla bütün lazımi alt-məsələlərin hazır olduğunu qarantiləyirik.

**6. Bitmask DP-də 2D mi, 1D mi?**
- **Assignment**: 1D (`dp[mask]`) — `popcount(mask)` mövqeyi verir
- **TSP**: 2D (`dp[mask][u]`) — hansı şəhərdə olduğumuzu da bilmək lazımdır

**7. Tree Robbery-də state nədir?**
Hər node üçün iki hal: `(include, exclude)` — node seçilib / seçilməyib. Cavab: `max(root_include, root_exclude)`.

**8. Digit DP-də `started` flag nə işə yarayır?**
Leading zero-ları idarə etmək üçün. Məsələn, "7" ədədi tək rəqəmdirsə, 5 rəqəm kimi "00007" emal edilməməlidir — `started` false olanda current digit "rəqəm sayılmır".

**9. Matrix Chain Multiplication-da dimensions niyə n+1?**
n matris var, amma dimensions n+1 ədəddir: M_i = dims[i] × dims[i+1]. İki matrisi vurmaq üçün dims[i+1] = qarşı matrisin dims[i+1]-i olmalıdır.

**10. Interval DP-dən bitmask DP-yə nə zaman keçmək?**
Əgər interval-lar "contiguous" deyilsə və hər hansı alt-çoxluq ola bilərsə — bitmask. Interval DP yalnız ardıcıl seqmentlər üçündür.

## PHP/Laravel ilə Əlaqə

- **Job scheduling**: assignment problem — hansı worker hansı tapşırığı görsün (kiçik sistem üçün).
- **Route optimization**: kuryer dispatch (TSP) — kiçik ölçüdə bitmask DP.
- **Analytics**: müəyyən xassəli ID-ləri saymaq (digit DP) — hesabat filterlərində.
- **Laravel Nova fields**: tree DP ilə category hierarchy üzərində aggregations.
- **Qeyd**: PHP-in rekursiya stack-i 256 dərinliklə məhduddur; böyük tree DP-lərdə iterativ yanaşma və ya `xdebug.max_nesting_level` artırılması lazım ola bilər.

---

## Praktik Tapşırıqlar

1. **LeetCode 309** — Best Time to Buy and Sell Stock with Cooldown (state machine DP)
2. **LeetCode 1235** — Maximum Profit in Job Scheduling (DP + binary search)
3. **LeetCode 410** — Split Array Largest Sum (binary search + greedy və ya DP)
4. **LeetCode 664** — Strange Printer (interval DP)
5. **LeetCode 1547** — Minimum Cost to Cut a Stick (interval DP, stone game tip)

### Step-by-step: Stock with Cooldown (state machine)

```
prices = [1, 2, 3, 0, 2]

States: hold / sold / rest
Transitions:
  hold[i] = max(hold[i-1], rest[i-1] - price[i])
  sold[i] = hold[i-1] + price[i]
  rest[i] = max(rest[i-1], sold[i-1])

i=0(p=1): hold=-1, sold=-∞,   rest=0
i=1(p=2): hold=-1, sold=1,    rest=0
i=2(p=3): hold=-1, sold=2,    rest=1
i=3(p=0): hold=1,  sold=-1,   rest=2
i=4(p=2): hold=1,  sold=3,    rest=2

answer = max(sold[4], rest[4]) = 3 ✓
```

---

## Əlaqəli Mövzular

- [23-dynamic-programming.md](23-dynamic-programming.md) — DP əsasları
- [24-dp-classic-problems.md](24-dp-classic-problems.md) — Klassik DP məsələləri
- [33-bit-manipulation.md](33-bit-manipulation.md) — Bitmask DP üçün bit ops
- [16-trees-basics.md](16-trees-basics.md) — Tree DP (post-order traversal)
