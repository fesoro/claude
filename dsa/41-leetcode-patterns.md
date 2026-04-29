# LeetCode Patterns (Lead)

## Konsept (Concept)

**LeetCode Patterns** — coding interviewlərdə rast gəlinən məsələlərin böyük hissəsini həll etməyə imkan verən **təkrarlanan həll strukturlarıdır**. Hər bir pattern müəyyən məsələ kateqoriyası üçün optimal yanaşma təklif edir.

Əsas fayda: 3000+ LeetCode məsələsini tək-tək öyrənmək əvəzinə **15-20 pattern** öyrənmək kifayətdir. Problemi görəndə hansı pattern-ə uyğun olduğunu tanımaq lazımdır.

## Necə İşləyir?

1. **Problemi oxu** və açar sözləri tap: "subarray", "contiguous", "sorted", "k-th largest", "shortest path"
2. **Constraint-lərə bax**: `n <= 10^5` → O(n log n), `n <= 20` → O(2^n) ola bilər
3. **Pattern seç** və template-i tətbiq et
4. **Edge case-ləri** düşün

### Pattern Tanıma Cədvəli

| Açar söz / Şərt | Pattern |
|-----------------|---------|
| "Contiguous subarray", "window" | Sliding Window |
| "Sorted array", "pair with sum" | Two Pointers |
| "Cycle in linked list" | Fast & Slow Pointers |
| "Overlapping intervals" | Merge Intervals |
| "Array 1..n, find missing" | Cyclic Sort |
| "Reverse linked list" | In-place Reversal |
| "Level order traversal" | Tree BFS |
| "All paths", "tree traversal" | Tree DFS |
| "Median of stream" | Two Heaps |
| "All subsets/permutations" | Subsets / Backtracking |
| "Sorted array + search" | Modified Binary Search |
| "Top K elements" | Heap (Top K) |
| "K sorted lists" | K-way Merge |
| "Dependency order" | Topological Sort |
| "Optimal substructure" | Dynamic Programming |
| "All combinations" | Backtracking |

## İmplementasiya (Implementation) - PHP

### 1. Sliding Window

```php
// Maximum sum subarray of size k
function maxSumSubarray(array $arr, int $k): int {
    $maxSum = 0;
    $windowSum = 0;
    for ($i = 0; $i < $k; $i++) $windowSum += $arr[$i];
    $maxSum = $windowSum;
    for ($i = $k; $i < count($arr); $i++) {
        $windowSum += $arr[$i] - $arr[$i - $k];
        $maxSum = max($maxSum, $windowSum);
    }
    return $maxSum;
}

// Longest substring without repeating characters
function longestUniqueSubstring(string $s): int {
    $map = [];
    $left = 0;
    $max = 0;
    for ($right = 0; $right < strlen($s); $right++) {
        $ch = $s[$right];
        if (isset($map[$ch]) && $map[$ch] >= $left) {
            $left = $map[$ch] + 1;
        }
        $map[$ch] = $right;
        $max = max($max, $right - $left + 1);
    }
    return $max;
}
```

### 2. Two Pointers

```php
// Pair with sum in sorted array
function pairWithSum(array $arr, int $target): array {
    $left = 0;
    $right = count($arr) - 1;
    while ($left < $right) {
        $sum = $arr[$left] + $arr[$right];
        if ($sum === $target) return [$left, $right];
        if ($sum < $target) $left++;
        else $right--;
    }
    return [];
}
```

### 3. Fast & Slow Pointers (Floyd's)

```php
class ListNode {
    public ?ListNode $next = null;
    public function __construct(public int $val) {}
}

function hasCycle(?ListNode $head): bool {
    $slow = $fast = $head;
    while ($fast && $fast->next) {
        $slow = $slow->next;
        $fast = $fast->next->next;
        if ($slow === $fast) return true;
    }
    return false;
}
```

### 4. Merge Intervals

```php
function mergeIntervals(array $intervals): array {
    if (count($intervals) < 2) return $intervals;
    usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);
    $result = [$intervals[0]];
    for ($i = 1; $i < count($intervals); $i++) {
        $last = &$result[count($result) - 1];
        if ($intervals[$i][0] <= $last[1]) {
            $last[1] = max($last[1], $intervals[$i][1]);
        } else {
            $result[] = $intervals[$i];
        }
    }
    return $result;
}
```

### 5. Cyclic Sort

```php
// Find missing number in [1..n]
function findMissing(array $nums): int {
    $i = 0;
    while ($i < count($nums)) {
        $correct = $nums[$i] - 1;
        if ($nums[$i] < count($nums) && $nums[$i] !== $nums[$correct]) {
            [$nums[$i], $nums[$correct]] = [$nums[$correct], $nums[$i]];
        } else {
            $i++;
        }
    }
    for ($i = 0; $i < count($nums); $i++) {
        if ($nums[$i] !== $i + 1) return $i + 1;
    }
    return count($nums) + 1;
}
```

### 6. In-place LinkedList Reversal

```php
function reverseList(?ListNode $head): ?ListNode {
    $prev = null;
    $curr = $head;
    while ($curr) {
        $next = $curr->next;
        $curr->next = $prev;
        $prev = $curr;
        $curr = $next;
    }
    return $prev;
}
```

### 7. Tree BFS

```php
class TreeNode {
    public ?TreeNode $left = null;
    public ?TreeNode $right = null;
    public function __construct(public int $val) {}
}

function levelOrder(?TreeNode $root): array {
    if (!$root) return [];
    $queue = [$root];
    $result = [];
    while (!empty($queue)) {
        $level = [];
        $count = count($queue);
        for ($i = 0; $i < $count; $i++) {
            $node = array_shift($queue);
            $level[] = $node->val;
            if ($node->left) $queue[] = $node->left;
            if ($node->right) $queue[] = $node->right;
        }
        $result[] = $level;
    }
    return $result;
}
```

### 8. Tree DFS

```php
function pathSum(?TreeNode $root, int $target): bool {
    if (!$root) return false;
    if (!$root->left && !$root->right) return $root->val === $target;
    return pathSum($root->left, $target - $root->val)
        || pathSum($root->right, $target - $root->val);
}
```

### 9. Two Heaps (Median finder)

```php
class MedianFinder {
    private SplMaxHeap $low;
    private SplMinHeap $high;

    public function __construct() {
        $this->low = new SplMaxHeap();
        $this->high = new SplMinHeap();
    }

    public function addNum(int $num): void {
        $this->low->insert($num);
        $this->high->insert($this->low->extract());
        if ($this->high->count() > $this->low->count()) {
            $this->low->insert($this->high->extract());
        }
    }

    public function findMedian(): float {
        if ($this->low->count() > $this->high->count()) return $this->low->top();
        return ($this->low->top() + $this->high->top()) / 2;
    }
}
```

### 10. Subsets (Backtracking)

```php
function subsets(array $nums): array {
    $result = [[]];
    foreach ($nums as $n) {
        $len = count($result);
        for ($i = 0; $i < $len; $i++) {
            $result[] = array_merge($result[$i], [$n]);
        }
    }
    return $result;
}
```

### 11. Modified Binary Search

```php
// Search in rotated sorted array
function searchRotated(array $nums, int $target): int {
    $left = 0;
    $right = count($nums) - 1;
    while ($left <= $right) {
        $mid = intdiv($left + $right, 2);
        if ($nums[$mid] === $target) return $mid;
        if ($nums[$left] <= $nums[$mid]) {
            if ($nums[$left] <= $target && $target < $nums[$mid]) $right = $mid - 1;
            else $left = $mid + 1;
        } else {
            if ($nums[$mid] < $target && $target <= $nums[$right]) $left = $mid + 1;
            else $right = $mid - 1;
        }
    }
    return -1;
}
```

### 12. Top K Elements (Min Heap)

```php
function topKFrequent(array $nums, int $k): array {
    $freq = array_count_values($nums);
    $heap = new SplPriorityQueue();
    foreach ($freq as $num => $count) {
        $heap->insert($num, -$count);
    }
    $result = [];
    for ($i = 0; $i < $k; $i++) $result[] = $heap->extract();
    return $result;
}
```

### 13. K-way Merge

```php
function mergeKSortedLists(array $lists): array {
    $heap = new SplMinHeap();
    foreach ($lists as $i => $list) {
        if (!empty($list)) $heap->insert([$list[0], $i, 0]);
    }
    $result = [];
    while (!$heap->isEmpty()) {
        [$val, $listIdx, $elemIdx] = $heap->extract();
        $result[] = $val;
        if ($elemIdx + 1 < count($lists[$listIdx])) {
            $heap->insert([$lists[$listIdx][$elemIdx + 1], $listIdx, $elemIdx + 1]);
        }
    }
    return $result;
}
```

### 14. Topological Sort (Kahn's)

```php
function topologicalSort(int $n, array $edges): array {
    $graph = array_fill(0, $n, []);
    $inDegree = array_fill(0, $n, 0);
    foreach ($edges as [$u, $v]) {
        $graph[$u][] = $v;
        $inDegree[$v]++;
    }
    $queue = [];
    for ($i = 0; $i < $n; $i++) if ($inDegree[$i] === 0) $queue[] = $i;
    $result = [];
    while (!empty($queue)) {
        $node = array_shift($queue);
        $result[] = $node;
        foreach ($graph[$node] as $nb) {
            if (--$inDegree[$nb] === 0) $queue[] = $nb;
        }
    }
    return count($result) === $n ? $result : [];
}
```

### 15. DP Template

```php
// Longest Increasing Subsequence
function lengthOfLIS(array $nums): int {
    $n = count($nums);
    $dp = array_fill(0, $n, 1);
    for ($i = 1; $i < $n; $i++) {
        for ($j = 0; $j < $i; $j++) {
            if ($nums[$j] < $nums[$i]) $dp[$i] = max($dp[$i], $dp[$j] + 1);
        }
    }
    return max($dp);
}
```

### 16. Backtracking Template

```php
function permute(array $nums): array {
    $result = [];
    $backtrack = function (array $current, array $remaining) use (&$backtrack, &$result) {
        if (empty($remaining)) {
            $result[] = $current;
            return;
        }
        foreach ($remaining as $i => $n) {
            $newRemaining = $remaining;
            array_splice($newRemaining, $i, 1);
            $backtrack([...$current, $n], $newRemaining);
        }
    };
    $backtrack([], $nums);
    return $result;
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Pattern | Time | Space |
|---------|------|-------|
| Sliding Window | O(n) | O(1) or O(k) |
| Two Pointers | O(n) | O(1) |
| Fast & Slow | O(n) | O(1) |
| Merge Intervals | O(n log n) | O(n) |
| Cyclic Sort | O(n) | O(1) |
| Tree BFS | O(n) | O(w) — width |
| Tree DFS | O(n) | O(h) — height |
| Two Heaps | O(log n) insert | O(n) |
| Subsets | O(2^n) | O(2^n) |
| Modified BS | O(log n) | O(1) |
| Top K | O(n log k) | O(k) |
| K-way Merge | O(N log k) | O(k) |
| Topo Sort | O(V+E) | O(V+E) |
| DP | Problemdən asılı | Problemdən asılı |
| Backtracking | O(b^d) | O(d) |

## Tipik Məsələlər (Common Problems)

### 1. Two Sum (Hash Map pattern)
```php
function twoSum(array $nums, int $target): array {
    $map = [];
    foreach ($nums as $i => $n) {
        if (isset($map[$target - $n])) return [$map[$target - $n], $i];
        $map[$n] = $i;
    }
    return [];
}
```

### 2. Valid Parentheses (Stack)
```php
function isValid(string $s): bool {
    $stack = [];
    $pairs = [')' => '(', ']' => '[', '}' => '{'];
    foreach (str_split($s) as $ch) {
        if (in_array($ch, ['(', '[', '{'])) $stack[] = $ch;
        elseif (array_pop($stack) !== $pairs[$ch]) return false;
    }
    return empty($stack);
}
```

### 3. Number of Islands (Grid DFS)
```php
function numIslands(array $grid): int {
    $count = 0;
    $rows = count($grid);
    $cols = count($grid[0]);
    $dfs = function ($r, $c) use (&$dfs, &$grid, $rows, $cols) {
        if ($r < 0 || $r >= $rows || $c < 0 || $c >= $cols || $grid[$r][$c] !== '1') return;
        $grid[$r][$c] = '0';
        $dfs($r+1, $c); $dfs($r-1, $c);
        $dfs($r, $c+1); $dfs($r, $c-1);
    };
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            if ($grid[$r][$c] === '1') { $count++; $dfs($r, $c); }
        }
    }
    return $count;
}
```

### 4. Coin Change (DP)
```php
function coinChange(array $coins, int $amount): int {
    $dp = array_fill(0, $amount + 1, $amount + 1);
    $dp[0] = 0;
    for ($i = 1; $i <= $amount; $i++) {
        foreach ($coins as $c) {
            if ($c <= $i) $dp[$i] = min($dp[$i], $dp[$i - $c] + 1);
        }
    }
    return $dp[$amount] > $amount ? -1 : $dp[$amount];
}
```

### 5. Word Search (Backtracking)
```php
function exist(array $board, string $word): bool {
    $rows = count($board);
    $cols = count($board[0]);
    $dfs = function ($r, $c, $i) use (&$dfs, &$board, $word, $rows, $cols) {
        if ($i === strlen($word)) return true;
        if ($r < 0 || $r >= $rows || $c < 0 || $c >= $cols || $board[$r][$c] !== $word[$i]) return false;
        $temp = $board[$r][$c];
        $board[$r][$c] = '#';
        $found = $dfs($r+1,$c,$i+1) || $dfs($r-1,$c,$i+1) || $dfs($r,$c+1,$i+1) || $dfs($r,$c-1,$i+1);
        $board[$r][$c] = $temp;
        return $found;
    };
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            if ($dfs($r, $c, 0)) return true;
        }
    }
    return false;
}
```

## Interview Sualları

**1. LeetCode pattern-ləri niyə vacibdir?**
3000+ məsələ arasında 80%-i 15-20 pattern-ə yerləşir. Pattern tanımaq problem-solving sürətini artırır və yeni məsələ gələndə tanış strukturu görməyə imkan verir.

**2. Sliding Window ilə Two Pointers arasındakı fərq?**
Sliding Window bir contiguous pəncərə saxlayır və onun ölçüsünü/mövqeyini dəyişir. Two Pointers isə iki müstəqil pointer ilə array üzərində hərəkət edir (tez-tez iki tərəfdən mərkəzə).

**3. Nə zaman BFS, nə zaman DFS?**
- **BFS**: qısa yol, level-by-level traversal, shortest path in unweighted graph
- **DFS**: all paths, tree traversal, cycle detection, topological sort, connected components

**4. Greedy-ni DP-dən necə ayırmaq olar?**
Greedy lokal optimal seçim edir və geri qayıtmır. DP bütün alt-problemləri saxlayır. Greedy işləyir yalnız **optimal substructure + greedy choice property** olanda.

**5. Constraint-lər hansı pattern-i göstərir?**
- `n ≤ 10` → Backtracking, permutations
- `n ≤ 20` → Bitmask DP, O(2^n)
- `n ≤ 500` → O(n^3)
- `n ≤ 5000` → O(n^2) DP
- `n ≤ 10^5` → O(n log n)
- `n ≤ 10^7` → O(n) və ya O(n log log n)
- `n ≤ 10^18` → O(log n) və ya riyazi formula

**6. Top K problemlərində hansı struktur?**
**Min-heap of size k**: hər element daxil olanda heap-a qoy, ölçü k-dan böyük olanda minimum atılır. Nəticə heap-dakı k element. Time: O(n log k).

**7. Two Heaps pattern nə üçündür?**
Median, sliding window median kimi problemlərdə — elementləri iki yerə böl: yarısı max-heap (aşağı yarı), yarısı min-heap (yuxarı yarı). Median heap-lərin top-larından alınır.

**8. Backtracking-də pruning nə deməkdir?**
Artıq nəticəyə gətirə bilməyəcək budaqları erkən kəsmək. Məsələn, sum hədəfdən böyüksə və bütün elementlər pozitiv isə — qayıt.

**9. DP-də top-down və bottom-up arasındakı fərq?**
- **Top-down (memoization)**: rekursiya + cache, yazması asan, lazy
- **Bottom-up (tabulation)**: iterativ, space optimize etmək asandır, stack overflow yoxdur

**10. Cyclic Sort nə zaman istifadə olunur?**
Array `[1..n]` və ya `[0..n-1]` diapazonundakı ədədlərlə dolu olanda. Hər element `arr[i] = i+1` olsun deyə yerini dəyişmək. Time O(n), Space O(1).

## PHP/Laravel ilə Əlaqə

- **Laravel Collection pipeline**: `filter`, `map`, `reduce` — bir çox LeetCode-style transformation-ları readable edir.
- **Redis + Laravel**: Top K elements üçün `ZSET`, caching pattern-lər üçün TTL istifadə olunur.
- **Eloquent N+1**: reallıqda Graph BFS-ə bənzər problem — child relation-ları batch yüklə (`with()`).
- **Job Queue**: Topological Sort ilə job dependency-lərini planla.
- **Pagination + Cursor**: Two Pointers kimi — başlanğıc və son göstəriciləri.

---

## Praktik Tapşırıqlar

Pattern tanıma məşqləri — hər problemi görmədən əvvəl "hansı pattern?" sualını ver:

1. **LeetCode 3** — Longest Substring Without Repeating (Sliding Window — variable)
2. **LeetCode 15** — 3Sum (Two Pointers — sorted array)
3. **LeetCode 102** — Binary Tree Level Order Traversal (BFS — level-by-level)
4. **LeetCode 647** — Palindromic Substrings (Expand Around Center)
5. **LeetCode 416** — Partition Equal Subset Sum (0/1 Knapsack DP)

### Step-by-step: Pattern identification flowchart

```
Problem gördükdə sorğular:

1. Array/String + subarray/substring?
   → Sliding Window və ya Two Pointers

2. "K-cu böyük/kiçik"?
   → Heap (priority queue)

3. Tree/Graph traversal?
   → BFS (level, shortest path) və ya DFS (paths, cycles)

4. Optimal substructure (alt problem nəticəsi lazım)?
   → Dynamic Programming

5. "Bütün kombinasiyalar/permutasiyalar"?
   → Backtracking

6. Sorted array + O(log n)?
   → Binary Search

7. Range query + update?
   → Fenwick Tree / Segment Tree

Əvvəlcə brute force → pattern tanı → optimallaşdır.
```

---

## Əlaqəli Mövzular

- [42-interview-strategy.md](42-interview-strategy.md) — Problem yanaşma framework
- [40-complexity-cheatsheet.md](40-complexity-cheatsheet.md) — Constraint → complexity cədvəli
- [08-two-pointers.md](08-two-pointers.md) — Two Pointer pattern
- [09-sliding-window.md](09-sliding-window.md) — Sliding Window pattern
- [23-dynamic-programming.md](23-dynamic-programming.md) — DP pattern recognition
