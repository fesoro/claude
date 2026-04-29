# Backtracking (Senior)

## Konsept (Concept)

Backtracking butun mumkun helleri arasdiran, amma uygun olmayan yollardan geri donub vaxt qazanan algoritmdir. "Try, check, undo" prinsipi ile isleyir.

```
Backtracking sablonu:
  function backtrack(state, choices):
      if is_solution(state):
          add to results
          return
      for each choice in choices:
          if is_valid(choice):
              make choice        # secim et
              backtrack(state)   # davam et
              undo choice        # geri qayit (backtrack)

Decision tree [1,2,3] subsets ucun:
              []
         /    |    \
       [1]   [2]   [3]
      / \     |
   [1,2] [1,3] [2,3]
     |
  [1,2,3]
```

## Nece Isleyir? (How does it work?)

### N-Queens numumesi (N=4):
```
Try column by column:
Q . . .    Q . . .    Q . . .    . . Q .
. . . .    . . Q .    . . . Q    Q . . .
. . . .    . . . .    . Q . .    . . . Q
. . . .    . . . .    . . . .    . Q . .
Step 1     Step 2     CONFLICT!  SOLUTION!

Row 0: Q at col 0
Row 1: Try col 0 (conflict), col 1 (conflict), col 2 ✓
Row 2: Try all (conflicts!) -> BACKTRACK to row 1
Row 1: Try col 3 ✓
Row 2: Try col 1 ✓
Row 3: Try all (conflicts!) -> BACKTRACK...
... continue until solution found
```

## Implementasiya (Implementation)

```php
<?php

/**
 * N-Queens (LeetCode 51)
 * Time: O(N!), Space: O(N^2)
 */
function solveNQueens(int $n): array
{
    $result = [];
    $board = array_fill(0, $n, array_fill(0, $n, '.'));
    placeQueens($board, 0, $n, $result);
    return $result;
}

function placeQueens(array &$board, int $row, int $n, array &$result): void
{
    if ($row === $n) {
        $solution = [];
        foreach ($board as $r) {
            $solution[] = implode('', $r);
        }
        $result[] = $solution;
        return;
    }

    for ($col = 0; $col < $n; $col++) {
        if (isQueenSafe($board, $row, $col, $n)) {
            $board[$row][$col] = 'Q';
            placeQueens($board, $row + 1, $n, $result);
            $board[$row][$col] = '.'; // backtrack
        }
    }
}

function isQueenSafe(array $board, int $row, int $col, int $n): bool
{
    // Yuxari yoxla
    for ($i = 0; $i < $row; $i++) {
        if ($board[$i][$col] === 'Q') return false;
    }
    // Sol yuxari diagonal
    for ($i = $row - 1, $j = $col - 1; $i >= 0 && $j >= 0; $i--, $j--) {
        if ($board[$i][$j] === 'Q') return false;
    }
    // Sag yuxari diagonal
    for ($i = $row - 1, $j = $col + 1; $i >= 0 && $j < $n; $i--, $j++) {
        if ($board[$i][$j] === 'Q') return false;
    }
    return true;
}

/**
 * Sudoku Solver (LeetCode 37)
 * Time: O(9^(empty cells)), Space: O(1)
 */
function solveSudoku(array &$board): bool
{
    for ($i = 0; $i < 9; $i++) {
        for ($j = 0; $j < 9; $j++) {
            if ($board[$i][$j] === '.') {
                for ($num = 1; $num <= 9; $num++) {
                    $ch = (string)$num;
                    if (isValidSudoku($board, $i, $j, $ch)) {
                        $board[$i][$j] = $ch;
                        if (solveSudoku($board)) return true;
                        $board[$i][$j] = '.'; // backtrack
                    }
                }
                return false; // Hec bir reqem uygun deyil
            }
        }
    }
    return true; // Bos xana yoxdur, hell tapildi
}

function isValidSudoku(array $board, int $row, int $col, string $ch): bool
{
    for ($i = 0; $i < 9; $i++) {
        if ($board[$row][$i] === $ch) return false;
        if ($board[$i][$col] === $ch) return false;
        $r = 3 * (int)($row / 3) + (int)($i / 3);
        $c = 3 * (int)($col / 3) + $i % 3;
        if ($board[$r][$c] === $ch) return false;
    }
    return true;
}

/**
 * Permutations (LeetCode 46)
 * Time: O(N * N!), Space: O(N)
 */
function permute(array $nums): array
{
    $result = [];
    permuteHelper($nums, 0, $result);
    return $result;
}

function permuteHelper(array &$nums, int $start, array &$result): void
{
    if ($start === count($nums)) {
        $result[] = $nums;
        return;
    }

    for ($i = $start; $i < count($nums); $i++) {
        [$nums[$start], $nums[$i]] = [$nums[$i], $nums[$start]];
        permuteHelper($nums, $start + 1, $result);
        [$nums[$start], $nums[$i]] = [$nums[$i], $nums[$start]]; // backtrack
    }
}

/**
 * Combinations (LeetCode 77)
 * Time: O(C(n,k) * k), Space: O(k)
 */
function combine(int $n, int $k): array
{
    $result = [];
    combineHelper(1, $n, $k, [], $result);
    return $result;
}

function combineHelper(int $start, int $n, int $k, array $current, array &$result): void
{
    if (count($current) === $k) {
        $result[] = $current;
        return;
    }

    // Pruning: qalan elementler bes etmelidi
    for ($i = $start; $i <= $n - ($k - count($current)) + 1; $i++) {
        $current[] = $i;
        combineHelper($i + 1, $n, $k, $current, $result);
        array_pop($current); // backtrack
    }
}

/**
 * Subsets (LeetCode 78)
 * Time: O(N * 2^N), Space: O(N)
 */
function subsets(array $nums): array
{
    $result = [];
    subsetsHelper($nums, 0, [], $result);
    return $result;
}

function subsetsHelper(array $nums, int $start, array $current, array &$result): void
{
    $result[] = $current;

    for ($i = $start; $i < count($nums); $i++) {
        $current[] = $nums[$i];
        subsetsHelper($nums, $i + 1, $current, $result);
        array_pop($current);
    }
}

/**
 * Subsets II (LeetCode 90) - with duplicates
 */
function subsetsWithDup(array $nums): array
{
    sort($nums);
    $result = [];
    subsetsWithDupHelper($nums, 0, [], $result);
    return $result;
}

function subsetsWithDupHelper(array $nums, int $start, array $current, array &$result): void
{
    $result[] = $current;

    for ($i = $start; $i < count($nums); $i++) {
        if ($i > $start && $nums[$i] === $nums[$i - 1]) continue; // skip duplicates
        $current[] = $nums[$i];
        subsetsWithDupHelper($nums, $i + 1, $current, $result);
        array_pop($current);
    }
}

/**
 * Word Search (LeetCode 79)
 * Time: O(M * N * 3^L), Space: O(L)
 */
function exist(array $board, string $word): bool
{
    $m = count($board);
    $n = count($board[0]);

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if (searchWord($board, $word, $i, $j, 0, $m, $n)) {
                return true;
            }
        }
    }

    return false;
}

function searchWord(array &$board, string $word, int $r, int $c, int $idx, int $m, int $n): bool
{
    if ($idx === strlen($word)) return true;
    if ($r < 0 || $r >= $m || $c < 0 || $c >= $n || $board[$r][$c] !== $word[$idx]) {
        return false;
    }

    $temp = $board[$r][$c];
    $board[$r][$c] = '#'; // visited

    $found = searchWord($board, $word, $r + 1, $c, $idx + 1, $m, $n)
          || searchWord($board, $word, $r - 1, $c, $idx + 1, $m, $n)
          || searchWord($board, $word, $r, $c + 1, $idx + 1, $m, $n)
          || searchWord($board, $word, $r, $c - 1, $idx + 1, $m, $n);

    $board[$r][$c] = $temp; // backtrack

    return $found;
}

/**
 * Combination Sum (LeetCode 39)
 * Time: O(N^(target/min)), Space: O(target/min)
 */
function combinationSum(array $candidates, int $target): array
{
    sort($candidates);
    $result = [];
    combSumHelper($candidates, $target, 0, [], $result);
    return $result;
}

function combSumHelper(array $cands, int $remaining, int $start, array $current, array &$result): void
{
    if ($remaining === 0) {
        $result[] = $current;
        return;
    }

    for ($i = $start; $i < count($cands); $i++) {
        if ($cands[$i] > $remaining) break; // pruning
        $current[] = $cands[$i];
        combSumHelper($cands, $remaining - $cands[$i], $i, $current, $result); // i: reuse allowed
        array_pop($current);
    }
}

/**
 * Palindrome Partitioning (LeetCode 131)
 * Time: O(N * 2^N), Space: O(N)
 */
function partition(string $s): array
{
    $result = [];
    partitionHelper($s, 0, [], $result);
    return $result;
}

function partitionHelper(string $s, int $start, array $current, array &$result): void
{
    if ($start === strlen($s)) {
        $result[] = $current;
        return;
    }

    for ($end = $start; $end < strlen($s); $end++) {
        $sub = substr($s, $start, $end - $start + 1);
        if ($sub === strrev($sub)) { // palindrome check
            $current[] = $sub;
            partitionHelper($s, $end + 1, $current, $result);
            array_pop($current);
        }
    }
}

// --- Test ---
echo "4-Queens solutions: " . count(solveNQueens(4)) . "\n"; // 2

$perms = permute([1, 2, 3]);
echo "Permutations of [1,2,3]: " . count($perms) . "\n"; // 6

$subs = subsets([1, 2, 3]);
echo "Subsets of [1,2,3]: " . count($subs) . "\n"; // 8

$combs = combinationSum([2, 3, 6, 7], 7);
echo "Combination sum for 7: " . count($combs) . "\n"; // 2: [2,2,3], [7]
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space |
|---------|------|-------|
| N-Queens | O(N!) | O(N^2) |
| Sudoku | O(9^81) worst | O(1) |
| Permutations | O(N * N!) | O(N) |
| Combinations | O(C(n,k) * k) | O(k) |
| Subsets | O(N * 2^N) | O(N) |
| Word Search | O(M*N*3^L) | O(L) |
| Combination Sum | O(N^(T/M)) | O(T/M) |

## Interview Suallari

1. **Backtracking ve brute force ferqi?**
   - Brute force butun imkanlari yoxlayir
   - Backtracking uygun olmayan yollardan erkenden geri donur (pruning)
   - Backtracking adeten daha suretlidir

2. **Subsets vs Permutations vs Combinations?**
   - Subsets: sira onemi yox, her element 0 ve ya 1 defe -> 2^N
   - Permutations: sira onemli, her element 1 defe -> N!
   - Combinations: sira onemi yox, k element sec -> C(N,K)

3. **Duplicate-lari nece handle edirsiniz?**
   - Evelce sort edin
   - `if (i > start && nums[i] === nums[i-1]) continue;`
   - Bu eyni seviyyede tekrar secimi atlayir

4. **Pruning nedir?**
   - Uygun olmadiqini bildiyimiz yollari erkenden kesmek
   - Meselen: Combination Sum-da `if (cands[i] > remaining) break;`
   - Vaxt murakkabliyini dramatik azaldir

## PHP/Laravel ile Elaqe

- **Form validation**: Mumkun field kombinasiyalarini yoxlamaq
- **Route matching**: Laravel router pattern matching backtracking istifade edir
- **Permission checking**: Complex permission rule-lari backtracking ile
- **Puzzle games**: Sudoku, crossword solver
- **Configuration generator**: Valid config kombinasiyalarini yaratmaq

---

## Praktik Tapşırıqlar

1. **LeetCode 46** — Permutations (klassik backtracking başlanğıcı)
2. **LeetCode 78** — Subsets (include/exclude qərar ağacı)
3. **LeetCode 39** — Combination Sum (təkrarla kombinasiya)
4. **LeetCode 51** — N-Queens (constraint validation ilə backtracking)
5. **LeetCode 79** — Word Search (2D grid backtracking + visited array)

### Step-by-step: Subsets [1,2,3]

```
decide(index=0, current=[]):
  ├─ include 1 → decide(1, [1])
  │    ├─ include 2 → decide(2, [1,2])
  │    │    ├─ include 3 → [1,2,3] ✓
  │    │    └─ skip 3   → [1,2]   ✓
  │    └─ skip 2 → decide(2, [1])
  │         ├─ include 3 → [1,3] ✓
  │         └─ skip 3   → [1]   ✓
  └─ skip 1 → ... (analoji)

Nəticə: 8 subset = 2^3 ✓
```

---

## Əlaqəli Mövzular

- [07-recursion.md](07-recursion.md) — Rekursiya (backtracking-in əsası)
- [21-greedy-algorithms.md](21-greedy-algorithms.md) — Greedy (daha sürətli, amma hər zaman optimal deyil)
- [23-dynamic-programming.md](23-dynamic-programming.md) — DP (backtracking + memoization)
- [25-graphs-basics.md](25-graphs-basics.md) — DFS (backtracking-in graph versiyası)
- [30-matrix-problems.md](30-matrix-problems.md) — Matrix DFS + backtracking
