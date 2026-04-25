# Recursion (Middle)

## Konsept (Concept)

Recursion funksiya ozunu cagirmasidir. Her recursive funksiyada base case (dayanma serti) ve recursive case olmalidir. Call stack-de her cagiri saxlanir.

```
factorial(4):
  factorial(4) = 4 * factorial(3)
    factorial(3) = 3 * factorial(2)
      factorial(2) = 2 * factorial(1)
        factorial(1) = 1  <-- base case
      return 2 * 1 = 2
    return 3 * 2 = 6
  return 4 * 6 = 24

Call Stack:
  |  fact(1)  |  <- top (base case)
  |  fact(2)  |
  |  fact(3)  |
  |  fact(4)  |  <- bottom (ilk cagiri)
  +===========+
```

### Recursion novleri:
- **Direct**: Funksiya ozunu cagirir
- **Indirect**: A -> B -> A (dolayi recursion)
- **Tail Recursion**: Son emeliyyat recursive cagiridir (optimize oluna biler)
- **Head Recursion**: Ilk emeliyyat recursive cagiridir

## Nece Isleyir? (How does it work?)

### Fibonacci numumesi:
```
fib(5):
                    fib(5)
                   /      \
              fib(4)      fib(3)
             /    \       /    \
          fib(3) fib(2) fib(2) fib(1)
          /   \    |      |
       fib(2) fib(1)     ...
        |
       ...

Problem: fib(3) 2 defe, fib(2) 3 defe hesablanir!
Helli: Memoization (yadda saxla)
```

### Tail Recursion vs Normal:
```
Normal (not tail):
function factorial($n) {
    if ($n <= 1) return 1;
    return $n * factorial($n - 1);  // factorial-dan sonra * emeliyyati var
}

Tail Recursive:
function factorial($n, $acc = 1) {
    if ($n <= 1) return $acc;
    return factorial($n - 1, $n * $acc);  // son emeliyyat recursive cagiridir
}
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Factorial - classic recursion
 * Time: O(n), Space: O(n) call stack
 */
function factorial(int $n): int
{
    if ($n <= 1) return 1;           // base case
    return $n * factorial($n - 1);   // recursive case
}

/**
 * Factorial - tail recursive
 * Time: O(n), Space: O(n) PHP-de (PHP tail call optimize etmir)
 */
function factorialTail(int $n, int $acc = 1): int
{
    if ($n <= 1) return $acc;
    return factorialTail($n - 1, $n * $acc);
}

/**
 * Factorial - iterative (en yaxsi)
 * Time: O(n), Space: O(1)
 */
function factorialIterative(int $n): int
{
    $result = 1;
    for ($i = 2; $i <= $n; $i++) {
        $result *= $i;
    }
    return $result;
}

/**
 * Fibonacci - naive recursive
 * Time: O(2^n), Space: O(n)
 */
function fibNaive(int $n): int
{
    if ($n <= 1) return $n;
    return fibNaive($n - 1) + fibNaive($n - 2);
}

/**
 * Fibonacci - memoized (top-down DP)
 * Time: O(n), Space: O(n)
 */
function fibMemo(int $n, array &$memo = []): int
{
    if ($n <= 1) return $n;
    if (isset($memo[$n])) return $memo[$n];

    $memo[$n] = fibMemo($n - 1, $memo) + fibMemo($n - 2, $memo);
    return $memo[$n];
}

/**
 * Power function - recursive
 * Time: O(log n) - her addimda yariya bol
 */
function power(int $base, int $exp): int
{
    if ($exp === 0) return 1;
    if ($exp % 2 === 0) {
        $half = power($base, (int)($exp / 2));
        return $half * $half;
    }
    return $base * power($base, $exp - 1);
}

/**
 * Sum of digits
 * Time: O(log n), Space: O(log n)
 */
function digitSum(int $n): int
{
    if ($n < 10) return $n;
    return ($n % 10) + digitSum((int)($n / 10));
}

/**
 * Reverse string recursively
 */
function reverseString(string $s): string
{
    if (strlen($s) <= 1) return $s;
    return reverseString(substr($s, 1)) . $s[0];
}

/**
 * Check palindrome recursively
 */
function isPalindrome(string $s, int $lo = 0, ?int $hi = null): bool
{
    if ($hi === null) $hi = strlen($s) - 1;
    if ($lo >= $hi) return true;
    if ($s[$lo] !== $s[$hi]) return false;
    return isPalindrome($s, $lo + 1, $hi - 1);
}

/**
 * Tower of Hanoi
 * Time: O(2^n), Space: O(n)
 */
function hanoi(int $n, string $from = 'A', string $to = 'C', string $aux = 'B'): void
{
    if ($n === 1) {
        echo "Disk $n: $from -> $to\n";
        return;
    }
    hanoi($n - 1, $from, $aux, $to);
    echo "Disk $n: $from -> $to\n";
    hanoi($n - 1, $aux, $to, $from);
}

/**
 * Generate all subsets (Power Set)
 * Time: O(2^n), Space: O(n)
 */
function subsets(array $nums): array
{
    $result = [];
    generateSubsets($nums, 0, [], $result);
    return $result;
}

function generateSubsets(array $nums, int $index, array $current, array &$result): void
{
    $result[] = $current;

    for ($i = $index; $i < count($nums); $i++) {
        $current[] = $nums[$i];
        generateSubsets($nums, $i + 1, $current, $result);
        array_pop($current);  // backtrack
    }
}

/**
 * Permutations
 * Time: O(n!), Space: O(n)
 */
function permutations(array $nums): array
{
    $result = [];
    generatePermutations($nums, 0, $result);
    return $result;
}

function generatePermutations(array &$nums, int $start, array &$result): void
{
    if ($start === count($nums)) {
        $result[] = $nums;
        return;
    }

    for ($i = $start; $i < count($nums); $i++) {
        [$nums[$start], $nums[$i]] = [$nums[$i], $nums[$start]];
        generatePermutations($nums, $start + 1, $result);
        [$nums[$start], $nums[$i]] = [$nums[$i], $nums[$start]]; // backtrack
    }
}

/**
 * Flatten nested array
 */
function flattenArray(array $arr): array
{
    $result = [];
    foreach ($arr as $item) {
        if (is_array($item)) {
            $result = array_merge($result, flattenArray($item));
        } else {
            $result[] = $item;
        }
    }
    return $result;
}

/**
 * Binary search - recursive
 */
function binarySearchRec(array $arr, int $target, int $lo = 0, ?int $hi = null): int
{
    if ($hi === null) $hi = count($arr) - 1;
    if ($lo > $hi) return -1;

    $mid = $lo + (int)(($hi - $lo) / 2);
    if ($arr[$mid] === $target) return $mid;
    if ($arr[$mid] < $target) return binarySearchRec($arr, $target, $mid + 1, $hi);
    return binarySearchRec($arr, $target, $lo, $mid - 1);
}

// --- Test ---
echo "5! = " . factorial(5) . "\n";           // 120
echo "fib(10) = " . fibMemo(10) . "\n";       // 55
echo "2^10 = " . power(2, 10) . "\n";         // 1024
echo "digit sum 1234 = " . digitSum(1234) . "\n"; // 10
echo "reverse 'hello' = " . reverseString('hello') . "\n"; // olleh
echo "palindrome 'racecar' = " . (isPalindrome('racecar') ? 'yes' : 'no') . "\n";

echo "\nSubsets of [1,2,3]:\n";
foreach (subsets([1, 2, 3]) as $s) {
    echo "  [" . implode(',', $s) . "]\n";
}

echo "\nTower of Hanoi (3 disks):\n";
hanoi(3);
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space | Qeyd |
|---------|------|-------|------|
| Factorial | O(n) | O(n) | Linear recursion |
| Fibonacci naive | O(2^n) | O(n) | Exponential! |
| Fibonacci memo | O(n) | O(n) | Memoization ile |
| Power | O(log n) | O(log n) | Divide & conquer |
| Subsets | O(2^n) | O(n) | Backtracking |
| Permutations | O(n!) | O(n) | Backtracking |
| Tower of Hanoi | O(2^n) | O(n) | Classic recursion |

## Tipik Meseler (Common Problems)

### 1. Merge Two Sorted Lists (LeetCode 21)
```php
<?php
function mergeTwoLists(?ListNode $l1, ?ListNode $l2): ?ListNode
{
    if ($l1 === null) return $l2;
    if ($l2 === null) return $l1;

    if ($l1->val <= $l2->val) {
        $l1->next = mergeTwoLists($l1->next, $l2);
        return $l1;
    }
    $l2->next = mergeTwoLists($l1, $l2->next);
    return $l2;
}
```

### 2. Maximum Depth of Binary Tree (LeetCode 104)
```php
<?php
function maxDepth(?TreeNode $root): int
{
    if ($root === null) return 0;
    return 1 + max(maxDepth($root->left), maxDepth($root->right));
}
```

### 3. Letter Combinations of Phone (LeetCode 17)
```php
<?php
function letterCombinations(string $digits): array
{
    if (empty($digits)) return [];

    $map = ['2'=>'abc','3'=>'def','4'=>'ghi','5'=>'jkl',
            '6'=>'mno','7'=>'pqrs','8'=>'tuv','9'=>'wxyz'];
    $result = [];

    function backtrack(string $digits, int $i, string $current, array $map, array &$result): void {
        if ($i === strlen($digits)) {
            $result[] = $current;
            return;
        }
        foreach (str_split($map[$digits[$i]]) as $ch) {
            backtrack($digits, $i + 1, $current . $ch, $map, $result);
        }
    }

    backtrack($digits, 0, '', $map, $result);
    return $result;
}
```

## Interview Suallari

1. **Recursion ne vaxt iterasiyadan ustundur?**
   - Tree/graph traversal, divide & conquer
   - Backtracking problemleri (subsets, permutations)
   - Kodu daha oxunaqli edir
   - Problem ozluyunde recursive olanda (fractal, Hanoi)

2. **Stack overflow nece bas verir?**
   - Her recursive call stack frame elave edir
   - Base case yoxdur ve ya catilamirsa, stack dolur
   - PHP default stack limiti var (~100-500K calls)
   - Helli: iterative cevirme, tail recursion, stack artirma

3. **Memoization nedir?**
   - Eyvallah hesablanmis neticeleri cache-le
   - Fibonacci: O(2^n) -> O(n) enir
   - Top-down DP-nin esasidir
   - PHP-de array, static variable ve ya cache ile

4. **Her recursion iterative cevirmek olarmi?**
   - Beli, her recursion explicit stack ile iterative ola biler
   - Bezi hallarda iterative daha cetin olur (tree traversal)
   - Tail recursion asanliqla while loop-a cevrilir

## PHP/Laravel ile Elaqe

- **Nested categories**: E-commerce category tree recursive render
- **Menu builder**: Laravel menu sistemi recursive build
- **File system**: Directory tree recursive traversal
- **JSON/XML parsing**: Nested structure-lerin recursive parse
- **Laravel collections**: `flatten()`, `map()` daxili olaraq recursion istifade edir
- **Eloquent**: `$category->children` recursive eager loading
