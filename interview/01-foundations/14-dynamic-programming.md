# Dynamic Programming Fundamentals (Senior ⭐⭐⭐)

## İcmal

Dynamic Programming (DP) — overlapping alt-problemləri olan optimizasiya problemlərini, hər alt-problemi bir dəfə həll edib nəticəni cache-ləyərək exponential-dan polynomial zamana endirən texnikadır. DP iki xüsusiyyəti olan problemlərə tətbiq olunur: Optimal Substructure (böyük problemin cavabı kiçik problemlərin optimal cavablarından qurulur) + Overlapping Subproblems (eyni alt-problemlər dəfələrlə hesablanır).

## Niyə Vacibdir

DP interviewer-lar üçün ən çətin bacarığı ölçən mövzudur. Google, Meta, Amazon, Microsoft senior/staff rollar üçün DP sualları standartdır. Real layihələrdə: edit distance (spell checker, git diff), knapsack (packing optimization), longest common subsequence (DNA alignment, file diff), coin change (cashier systems). DP-ni bilmək "algorithmic maturity"-nin ən açıq göstərgəsidir. Həm top-down (memoization), həm bottom-up (tabulation) yanaşmaları interview-da istifadə olunur.

## Əsas Anlayışlar

### DP-nin İki Şərti

1. **Optimal Substructure**: `f(n) = g(f(n-1), f(n-2), ...)` — böyük problemi kiçiklərdən qurmaq mümkündür. Məsələn, ən qısa yol problemi: `dist[v] = min(dist[u] + w(u,v))` — Dijkstra da DP kimi görünür.
2. **Overlapping Subproblems**: Eyni `f(k)` dəfələrlə hesablanır. Divide & Conquer-dən fərq: D&C-də alt-problemlər üst-üstə düşmür. Fibonacci-nin naive rekursiyasında `fib(3)` bir neçə dəfə hesablanır — bu overlapping-dir.

### Top-Down vs Bottom-Up

- **Top-Down (Memoization)**: Rekursiv yaz + cache (hash map ya array). Lazım olan alt-problemlər hesablanır. Call stack yükü var.
- **Bottom-Up (Tabulation)**: İterative olaraq ən kiçik alt-problemdən başla, böyüyünə qədər get. Space optimize olunur.
- Top-down: Yazması asandır, amma recursion overhead + stack overflow riski (dərin rekursiyada).
- Bottom-up: Daha effektiv, space-i O(n) → O(1)-ə endirmək mümkündür (rolling array).
- Interview-da: əvvəl top-down yaz (sürətli düşüncə), sonra bottom-up-a çevir (senioru göstər).

### DP Framework (5 addım)

1. **State-i müəyyən et**: `dp[i]` nəyi ifadə edir? Bu ən kritik addımdır.
2. **Recurrence relation-ı tap**: `dp[i]` əvvəlki state-lərdən necə hesablanır?
3. **Base case-ləri müəyyən et**: `dp[0]`, `dp[1]` nədir? Boundary-ləri yanlış qoyma.
4. **Sıranı müəyyən et**: Hansı state-lər hansından əvvəl hesablanmalıdır?
5. **Cavabı döndür**: `dp[n]`-mi, max-mı, son state-mi?

### 1D DP — Fibonacci Tipi

- `dp[i] = dp[i-1] + dp[i-2]`.
- Yalnız son 2 state lazımdır → O(1) space (rolling variables).
- **Problemlər**: Climbing stairs, House robber, Min cost climbing stairs, Fibonacci.
- House robber: `dp[i] = max(dp[i-1], dp[i-2] + nums[i])` — qonşuya girməmə constraint-i.

### 1D DP — Subarray/Subsequence Tipi

- `dp[i]` = `nums[0..i]`-nin optimal dəyəri.
- **Max subarray (Kadane's)**: `dp[i] = max(nums[i], dp[i-1] + nums[i])` — O(n).
- **Coin change**: `dp[amount] = min(dp[amount - coin] + 1)` for each coin.
- **Word break**: `dp[i] = True` if `word[:i]` can be segmented.

### 2D DP — Matrix Tipi

- `dp[i][j]` = sol üst küncündən `(i,j)`-yə qədər optimal dəyər.
- **Unique paths**: `dp[i][j] = dp[i-1][j] + dp[i][j-1]`.
- **Minimum path sum**: `dp[i][j] = grid[i][j] + min(dp[i-1][j], dp[i][j-1])`.
- **Maximal square**: `dp[i][j] = min(dp[i-1][j], dp[i][j-1], dp[i-1][j-1]) + 1`.

### 2D DP — İki Sequence Tipi

- `dp[i][j]` = `s1[0..i]` + `s2[0..j]` üçün optimal.
- **Longest Common Subsequence (LCS)**: Match olarsa `dp[i-1][j-1]+1`, else `max(dp[i-1][j], dp[i][j-1])`.
- **Edit Distance**: Insert, delete, replace operasiyaları.
- **Interleaving Strings**: `dp[i][j] = (s1[i-1]==s3[i+j-1] and dp[i-1][j]) or (s2[j-1]==s3[i+j-1] and dp[i][j-1])`.
- Space optimization: yalnız son sıra lazımdır → O(n) space.

### Knapsack Pattern

- **0/1 Knapsack**: Hər item ya seçilir, ya seçilmir. `dp[w] = max(dp[w], dp[w-weight]+value)`. Sağdan sola iterate et (hər item bir dəfə).
- **Unbounded Knapsack**: Hər item sonsuz dəfə seçilə bilər. Soldan sağa iterate et. Coin change, rod cutting.
- **Partition Equal Subset Sum**: Bərabər cəmli iki subset — 0/1 knapsack variantı. `dp[target]` True/False.
- **Target Sum (+/-)**: +/- işarəsi vermə — knapsack variantıdır.

### Interval DP

- `dp[i][j]` = `arr[i..j]` aralığı üçün optimal.
- **Matrix chain multiplication**: `dp[i][j] = min(dp[i][k] + dp[k+1][j] + dims[i]*dims[k+1]*dims[j+1])`.
- **Burst balloons**: Tricky — son sıxılan balon k olsa: `dp[i][j] = dp[i][k-1] + balloons[i-1]*balloons[k]*balloons[j+1] + dp[k+1][j]`.
- **Palindrome partitioning**: minimum kəsimlər sayı.
- Bütün split nöqtələrini sınadıqdan sonra optimal-ı seç. O(n³) adətən.

### DP + Binary Search

- **Longest Increasing Subsequence (LIS)**: O(n²) DP-dən O(n log n)-ə.
- Patience sorting — binary search ilə "tails" array-ini güncəllə.
- `tails[pos] = num` — tails her zaman mümkün olan ən kiçik "tail"-ləri saxlayır.
- Tails-in uzunluğu = LIS-in uzunluğu. Tails özü LIS deyil.

### Space Optimization Texnikası

- Çox 2D DP-da yalnız son 1-2 sıra/sütun lazımdır.
- `dp[i][j]` → `dp[j]` (rolling array) — LCS, Coin Change, Knapsack: O(n*m) → O(m) space.
- House robber: `dp[i]` → 2 dəyişən `prev2, prev1` → O(1) space.
- Fibonacci: `a, b = b, a + b` — klassik O(1) space.

### DP vs Greedy

- **Greedy**: Hər addımda lokal optimal seç — global optimal ola bilər (activity selection, Dijkstra).
- **DP**: Global optimal üçün bütün seçimləri kəşf edir, əvvəlki state-ləri xatırlayır.
- Greedy daha sürətli amma həmişə işləmir. DP daha ağır amma daha universal.
- Coin change: US coins-da greedy işləyir, amma arbitrary coins-da DP lazımdır.

### DP vs Divide and Conquer

- D&C: Alt-problemlər müstəqildir (overlap yoxdur) — Merge Sort, Binary Search.
- DP: Alt-problemlər üst-üstə düşür (overlap var) — Fibonacci, LCS.
- Hər ikisi rekursivdir, amma DP cache-ləyir, D&C etmir.

### State Machine DP

- State-lər arasında keçidləri modelləşdirən DP.
- **Stock buy/sell**: State = `holding` ya `not_holding`. `dp[i][0] = max(dp[i-1][0], dp[i-1][1]+prices[i])`.
- **House robber with cooldown**: State = `rest`, `held`, `sold`.
- State machine DP-si real layihələrdə (iş axışı, protocol parsing) tətbiq olunur.

### Bitmask DP

- State = hansı elementlərin seçildiyi bir bitmask ilə.
- **TSP (Traveling Salesman)**: `dp[mask][i]` = mask ziyarət edilib i-də bitən min məsafə.
- `n ≤ 20` üçün işlər (2^20 ≈ 1M state).
- Assignment problem, Hamiltonian path da bitmask DP-dir.

## Praktik Baxış

### Interview-a Yanaşma

1. Problemi diqqətlə oxu — "optimal", "minimum", "maximum", "count of ways" sözlər DP-yə işarə.
2. Kiçik nümunəni (n=1, 2, 3) əllə hesabla. Pattern-i gör.
3. State-i müəyyən et: `dp[i]` nəyi ifadə edir? Bu ən kritik addımdır.
4. Recurrence-ı tap: `f(n)` = `f(n-1)` ilə necə əlaqəlidir?
5. Base case-ləri yaz, sıranı müəyyən et.
6. Əvvəl top-down/memoization yaz (daha asan düşünmək), sonra bottom-up çevir.
7. Space optimization soruşulursa: rolling array texnikasını tətbiq et.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Space-i azalda bilərsənmi?" — rolling array.
- "Bütün mümkün nəticələri (sadəcə count deyil) qaytara bilərsənmi?" — backtracking + DP.
- "Bu problemi greedy ilə həll etmək olarmı? Niyə olmaz?" — coin change ilə fərqi.
- "Recurrence-ı necə tapdınız? Arxasındakı intuisiyanı izah edin."
- "O(n log n)-ə endirmək olarmı?" — LIS, patience sort.
- "Bu distributed sistemdə necə scale olunur?" — approximate DP, sampling.
- "Bu DP bitmask DP-yəmi keçir?" — n kiçikdirsə bax.

### Common Mistakes

- DP state-ini düzgün müəyyən etməmək — `dp[i]` "i-ci element üçün" deyil, "ilk i element nəzərə alınaraq" olmalıdır.
- Base case-ləri unutmaq ya yanlış qoymaq — `dp[0]` çox vaxt edge case.
- 2D DP-da sıranı düzgün keçməmək (inner/outer loop order).
- Coin change-də `dp[0] = 0`, `dp[i] = infinity` başlanğıcı unutmaq.
- 0/1 Knapsack-da sağdan sola iterate etməmək → hər item birdən çox seçilir.
- Overlapping subproblems olmayan problemə DP tətbiq etmək (D&C lazımdır).
- Space optimization-da düzgün state-ləri saxlamamaq.

### Yaxşı → Əla Cavab

- **Yaxşı cavab**: DP table doldurar, nəticə qaytarır. O(n) time, O(n) space.
- **Əla cavab**: State-in intuisiyasını izah edir, recurrence-ı isbat edir, space optimization edir, "bu greedy ilə olmaz çünki..." izah edir, birdən çox DP variant müzakirə edir, edge case-ləri görür.

### Real Production Ssenariləri

- Git diff-in "minimum edit distance" core-u DP-dir (Myers diff algorithm).
- Compiler-lər matrix chain multiplication kimi optimizasiya edir.
- Bioinformatika: DNA alignment üçün LCS.
- Spell checker: edit distance + dictionary.
- Regex engine: DP ilə pattern matching.

## Nümunələr

### Tipik Interview Sualı

"Integer array `nums` verilmişdir. Ardıcıl olmayan elementləri seçərək maksimum cəmi tapın. Qonşu elementlər seçilə bilməz. `nums = [2,7,9,3,1]` → 12 (2+9+1). Bu 'House Robber' məsələsidir."

### Güclü Cavab

"State: `dp[i]` = ilk `i` ev nəzərə alınaraq əldə olunacaq maksimum pul. Recurrence: ya i-ci evi seç `dp[i-2] + nums[i]`, ya seçmə `dp[i-1]`. `dp[i] = max(dp[i-1], dp[i-2] + nums[i])`. Base case: `dp[0] = nums[0]`, `dp[1] = max(nums[0], nums[1])`. Space optimize: yalnız 2 dəyişən saxlamaq kifayədir — O(1) space. O(n) time."

Niyə greedy işləmir: Məsələn `[2, 1, 1, 2]` — greedy ən böyük 2-ləri seçər, amma `2+2=4` optimal, `2+1+2` mümkün deyil. Greedy burada işləsə də, ümumi halda işləmir.

### Kod Nümunəsi

```python
# House Robber — O(n) time, O(1) space
def rob(nums: list[int]) -> int:
    if not nums:
        return 0
    if len(nums) == 1:
        return nums[0]
    prev2, prev1 = nums[0], max(nums[0], nums[1])
    for i in range(2, len(nums)):
        curr = max(prev1, prev2 + nums[i])
        prev2, prev1 = prev1, curr
    return prev1

# Coin Change — Bottom-Up DP — O(amount * len(coins)) time, O(amount) space
def coin_change(coins: list[int], amount: int) -> int:
    dp = [float('inf')] * (amount + 1)
    dp[0] = 0    # base case: 0-ı etmək üçün 0 sikkə
    for i in range(1, amount + 1):
        for coin in coins:
            if coin <= i:
                dp[i] = min(dp[i], dp[i - coin] + 1)
    return dp[amount] if dp[amount] != float('inf') else -1

# Longest Common Subsequence — O(m*n) time, O(m*n) space
def lcs(text1: str, text2: str) -> int:
    m, n = len(text1), len(text2)
    dp = [[0] * (n + 1) for _ in range(m + 1)]
    for i in range(1, m + 1):
        for j in range(1, n + 1):
            if text1[i-1] == text2[j-1]:
                dp[i][j] = dp[i-1][j-1] + 1   # match
            else:
                dp[i][j] = max(dp[i-1][j], dp[i][j-1])   # skip
    return dp[m][n]

# Edit Distance (Levenshtein) — O(m*n) time, O(m*n) space → O(n) possible
def edit_distance(word1: str, word2: str) -> int:
    m, n = len(word1), len(word2)
    dp = [[0] * (n + 1) for _ in range(m + 1)]
    for i in range(m + 1): dp[i][0] = i    # word2 boş → hamısını sil
    for j in range(n + 1): dp[0][j] = j    # word1 boş → hamısını əlavə et
    for i in range(1, m + 1):
        for j in range(1, n + 1):
            if word1[i-1] == word2[j-1]:
                dp[i][j] = dp[i-1][j-1]   # eyni hərf — cost yoxdur
            else:
                dp[i][j] = 1 + min(
                    dp[i-1][j],     # sil word1[i]
                    dp[i][j-1],     # word2[j] əlavə et
                    dp[i-1][j-1]   # əvəzlə
                )
    return dp[m][n]

# 0/1 Knapsack — O(n*W) time, O(W) space
def knapsack(weights: list[int], values: list[int], W: int) -> int:
    dp = [0] * (W + 1)
    for i in range(len(weights)):
        # Sağdan sola — 0/1 üçün (hər item bir dəfə)
        for w in range(W, weights[i] - 1, -1):
            dp[w] = max(dp[w], dp[w - weights[i]] + values[i])
    return dp[W]

# Longest Increasing Subsequence — O(n log n) — patience sorting
import bisect
def lis(nums: list[int]) -> int:
    tails = []   # tails[i] = uzunluğu i+1 olan LIS-in ən kiçik son elementi
    for num in nums:
        pos = bisect.bisect_left(tails, num)
        if pos == len(tails):
            tails.append(num)    # yeni uzunluq
        else:
            tails[pos] = num     # mövcud pozisionu optimallaşdır
    return len(tails)
    # Diqqət: tails özü LIS deyil, uzunluğu LIS uzunluğunu verir

# Partition Equal Subset Sum — O(n*sum) time, O(sum) space
def can_partition(nums: list[int]) -> bool:
    total = sum(nums)
    if total % 2 != 0:
        return False
    target = total // 2
    dp = [False] * (target + 1)
    dp[0] = True
    for num in nums:
        for j in range(target, num - 1, -1):  # sağdan sola — 0/1 knapsack
            dp[j] = dp[j] or dp[j - num]
    return dp[target]

# Stock Buy Sell with Cooldown — State Machine DP
def max_profit_cooldown(prices: list[int]) -> int:
    n = len(prices)
    if n < 2:
        return 0
    # State: held = holdinq var, sold = bu gün satdım, rest = gözlədim
    held = -prices[0]
    sold = 0
    rest = 0
    for i in range(1, n):
        prev_held, prev_sold, prev_rest = held, sold, rest
        held = max(prev_held, prev_rest - prices[i])  # saxla ya al
        sold = prev_held + prices[i]                  # sat
        rest = max(prev_rest, prev_sold)              # cooldown gözlə
    return max(sold, rest)
```

### İkinci Nümunə — 2D DP (Interval)

```python
# Burst Balloons — Interval DP O(n³)
# Key insight: son sıxılan balon k olduqda düşün
def max_coins(nums: list[int]) -> int:
    nums = [1] + nums + [1]   # boundary sentinel-lər
    n = len(nums)
    dp = [[0] * n for _ in range(n)]

    # interval uzunluğuna görə artır
    for length in range(2, n):
        for left in range(n - length):
            right = left + length
            for k in range(left + 1, right):
                # [left, right] aralığında k sonuncu sıxılan
                coins = nums[left] * nums[k] * nums[right]
                dp[left][right] = max(
                    dp[left][right],
                    dp[left][k] + coins + dp[k][right]
                )
    return dp[0][n-1]

# Matrix Chain Multiplication — Interval DP O(n³)
def matrix_chain_order(dims: list[int]) -> int:
    n = len(dims) - 1   # n matrix
    dp = [[0] * n for _ in range(n)]
    for length in range(2, n + 1):
        for i in range(n - length + 1):
            j = i + length - 1
            dp[i][j] = float('inf')
            for k in range(i, j):
                cost = dp[i][k] + dp[k+1][j] + dims[i]*dims[k+1]*dims[j+1]
                dp[i][j] = min(dp[i][j], cost)
    return dp[0][n-1]
```

## Praktik Tapşırıqlar

1. LeetCode #70: Climbing Stairs (Easy) — Fibonacci DP. O(1) space-ə optimallaşdır.
2. LeetCode #198: House Robber (Medium) — klassik 1D DP. Rolling variables istifadə et.
3. LeetCode #322: Coin Change (Medium) — unbounded knapsack. `dp[0]=0`, `dp[i]=INF` başlanğıcını izah et.
4. LeetCode #1143: Longest Common Subsequence (Medium) — 2D DP. O(n) space variantını yaz.
5. LeetCode #72: Edit Distance (Hard) — LCS variantı. 3 operasiya arasında seç.
6. LeetCode #300: Longest Increasing Subsequence (Medium) — O(n log n) patience sort.
7. LeetCode #416: Partition Equal Subset Sum (Medium) — 0/1 knapsack. Sağdan sola iterate et.
8. LeetCode #312: Burst Balloons (Hard) — interval DP. Son sıxılan balon trick-i.
9. LeetCode #309: Best Time to Buy and Sell Stock with Cooldown — state machine DP.
10. Özünütəst: House Robber məsələsini top-down yazdıqdan sonra bottom-up → O(1) space-ə optimize et. Hər addımı şifahi izah et.

## Əlaqəli Mövzular

- **Recursion** — top-down DP = recursion + memoization. Call stack derinliyini nəzərə al.
- **Binary Search** — LIS O(n log n) üçün patience sorting + binary search.
- **Graph Fundamentals** — DAG-da ən qısa/uzun yol DP variantıdır (Bellman-Ford).
- **Divide and Conquer** — D&C-dən fərq: overlap olduqda DP, olmadıqda D&C.
- **Greedy Algorithms** — DP-nin xüsusi halı; greedy choice property olduqda DP-dən sürətli.
- **Bit Manipulation** — bitmask DP üçün bitwise operations lazımdır.
