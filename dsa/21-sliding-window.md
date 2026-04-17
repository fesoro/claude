# Sliding Window (Surusen Pencere)

## Konsept (Concept)

Sliding Window ardical alt-massiv/alt-string uzerinde isleyen texnikadir. Pencereni saga surub, sol terefden lazim olmayanları silir. Brute force O(n^2)-ni O(n)-e endirir.

```
Fixed Window (size=3):
[1, 3, 2, 6, -1, 4, 1, 8, 2]
 [-----]                       sum=6
    [-----]                    sum=11
       [-----]                 sum=7
          [--------]           sum=9
             [--------]        sum=13
                [--------]     sum=11

Variable Window:
"abcabcbb" -> longest substring without repeating chars
 [abc]abcbb     len=3
 a[bca]bcbb     len=3  (a tekrar, sola daralt)
  ...
 Cavab: "abc" = 3
```

## Nece Isleyir? (How does it work?)

### Fixed Window:
```
Max sum of subarray size k=3:
arr = [2, 1, 5, 1, 3, 2]

Window sum = 2+1+5 = 8
Slide: -2 +1 = 7 (sum = 8-2+1 = 7)
Slide: -1 +3 = 9 (sum = 7-1+3 = 9) <- max
Slide: -5 +2 = 6 (sum = 9-5+2 = 6)

Max = 9
```

### Variable Window:
```
Longest substring without repeating:
s = "abcabcbb"

left=0, right=0: {a}       len=1
left=0, right=1: {a,b}     len=2
left=0, right=2: {a,b,c}   len=3
left=0, right=3: 'a' tekrar! left++ -> left=1, {b,c,a} len=3
left=1, right=4: 'b' tekrar! left++ -> left=2, {c,a,b} len=3
left=2, right=5: 'c' tekrar! left++ -> left=3, {a,b,c} len=3
left=3, right=6: 'b' tekrar! left+=2 -> left=5, {c,b} len=2
left=5, right=7: 'b' tekrar! left++ -> left=6, {b} len=1

Max len = 3
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Max Sum Subarray of Size K (Fixed Window)
 * Time: O(n), Space: O(1)
 */
function maxSumSubarray(array $arr, int $k): int
{
    $n = count($arr);
    if ($n < $k) return -1;

    // Ilk pencereni hesabla
    $windowSum = 0;
    for ($i = 0; $i < $k; $i++) {
        $windowSum += $arr[$i];
    }

    $maxSum = $windowSum;

    // Pencereni surut
    for ($i = $k; $i < $n; $i++) {
        $windowSum += $arr[$i] - $arr[$i - $k];
        $maxSum = max($maxSum, $windowSum);
    }

    return $maxSum;
}

/**
 * Longest Substring Without Repeating Characters (LeetCode 3)
 * Variable window
 * Time: O(n), Space: O(min(n, charset))
 */
function lengthOfLongestSubstring(string $s): int
{
    $charIndex = [];
    $maxLen = 0;
    $left = 0;

    for ($right = 0; $right < strlen($s); $right++) {
        $ch = $s[$right];
        if (isset($charIndex[$ch]) && $charIndex[$ch] >= $left) {
            $left = $charIndex[$ch] + 1;
        }
        $charIndex[$ch] = $right;
        $maxLen = max($maxLen, $right - $left + 1);
    }

    return $maxLen;
}

/**
 * Minimum Window Substring (LeetCode 76)
 * Time: O(n), Space: O(charset)
 */
function minWindow(string $s, string $t): string
{
    $need = [];
    for ($i = 0; $i < strlen($t); $i++) {
        $need[$t[$i]] = ($need[$t[$i]] ?? 0) + 1;
    }

    $have = [];
    $formed = 0;
    $required = count($need);
    $left = 0;
    $minLen = PHP_INT_MAX;
    $minStart = 0;

    for ($right = 0; $right < strlen($s); $right++) {
        $ch = $s[$right];
        $have[$ch] = ($have[$ch] ?? 0) + 1;

        if (isset($need[$ch]) && $have[$ch] === $need[$ch]) {
            $formed++;
        }

        while ($formed === $required) {
            $windowLen = $right - $left + 1;
            if ($windowLen < $minLen) {
                $minLen = $windowLen;
                $minStart = $left;
            }

            $leftCh = $s[$left];
            $have[$leftCh]--;
            if (isset($need[$leftCh]) && $have[$leftCh] < $need[$leftCh]) {
                $formed--;
            }
            $left++;
        }
    }

    return $minLen === PHP_INT_MAX ? '' : substr($s, $minStart, $minLen);
}

/**
 * Maximum Average Subarray I (LeetCode 643) - Fixed Window
 * Time: O(n), Space: O(1)
 */
function findMaxAverage(array $nums, int $k): float
{
    $sum = array_sum(array_slice($nums, 0, $k));
    $maxSum = $sum;

    for ($i = $k; $i < count($nums); $i++) {
        $sum += $nums[$i] - $nums[$i - $k];
        $maxSum = max($maxSum, $sum);
    }

    return $maxSum / $k;
}

/**
 * Longest Repeating Character Replacement (LeetCode 424)
 * Time: O(n), Space: O(1)
 */
function characterReplacement(string $s, int $k): int
{
    $count = [];
    $maxCount = 0; // en cox tekrarlanan herfin sayi
    $left = 0;
    $maxLen = 0;

    for ($right = 0; $right < strlen($s); $right++) {
        $ch = $s[$right];
        $count[$ch] = ($count[$ch] ?? 0) + 1;
        $maxCount = max($maxCount, $count[$ch]);

        // Window size - maxCount > k ise, window coxdur
        while (($right - $left + 1) - $maxCount > $k) {
            $count[$s[$left]]--;
            $left++;
        }

        $maxLen = max($maxLen, $right - $left + 1);
    }

    return $maxLen;
}

/**
 * Permutation in String (LeetCode 567)
 * Time: O(n), Space: O(1)
 */
function checkInclusion(string $s1, string $s2): bool
{
    $len1 = strlen($s1);
    $len2 = strlen($s2);
    if ($len1 > $len2) return false;

    $count1 = array_fill(0, 26, 0);
    $count2 = array_fill(0, 26, 0);

    for ($i = 0; $i < $len1; $i++) {
        $count1[ord($s1[$i]) - ord('a')]++;
        $count2[ord($s2[$i]) - ord('a')]++;
    }

    if ($count1 === $count2) return true;

    for ($i = $len1; $i < $len2; $i++) {
        $count2[ord($s2[$i]) - ord('a')]++;
        $count2[ord($s2[$i - $len1]) - ord('a')]--;
        if ($count1 === $count2) return true;
    }

    return false;
}

/**
 * Subarray Product Less Than K (LeetCode 713)
 * Time: O(n), Space: O(1)
 */
function numSubarrayProductLessThanK(array $nums, int $k): int
{
    if ($k <= 1) return 0;

    $product = 1;
    $count = 0;
    $left = 0;

    for ($right = 0; $right < count($nums); $right++) {
        $product *= $nums[$right];

        while ($product >= $k) {
            $product /= $nums[$left];
            $left++;
        }

        $count += $right - $left + 1;
    }

    return $count;
}

/**
 * Minimum Size Subarray Sum (LeetCode 209)
 * Time: O(n), Space: O(1)
 */
function minSubArrayLen(int $target, array $nums): int
{
    $left = 0;
    $sum = 0;
    $minLen = PHP_INT_MAX;

    for ($right = 0; $right < count($nums); $right++) {
        $sum += $nums[$right];

        while ($sum >= $target) {
            $minLen = min($minLen, $right - $left + 1);
            $sum -= $nums[$left];
            $left++;
        }
    }

    return $minLen === PHP_INT_MAX ? 0 : $minLen;
}

// --- Test ---
echo "Max sum k=3: " . maxSumSubarray([2,1,5,1,3,2], 3) . "\n"; // 9
echo "Longest no repeat 'abcabcbb': " . lengthOfLongestSubstring('abcabcbb') . "\n"; // 3
echo "Min window: " . minWindow("ADOBECODEBANC", "ABC") . "\n"; // "BANC"
echo "Char replacement 'AABABBA' k=1: " . characterReplacement('AABABBA', 1) . "\n"; // 4
echo "Min subarray sum>=7: " . minSubArrayLen(7, [2,3,1,2,4,3]) . "\n"; // 2
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space | Window Type |
|---------|------|-------|-------------|
| Max Sum Subarray K | O(n) | O(1) | Fixed |
| Longest Substring | O(n) | O(k) | Variable |
| Min Window Substring | O(n) | O(k) | Variable |
| Char Replacement | O(n) | O(1) | Variable |
| Permutation in String | O(n) | O(1) | Fixed |
| Product Less Than K | O(n) | O(1) | Variable |
| Min Size Subarray Sum | O(n) | O(1) | Variable |

## Tipik Meseler (Common Problems)

### 1. Sliding Window Maximum (LeetCode 239)
```php
<?php
function maxSlidingWindow(array $nums, int $k): array
{
    $deque = []; // monotonic decreasing deque (index-ler)
    $result = [];

    for ($i = 0; $i < count($nums); $i++) {
        // Kohen elementleri sil
        while (!empty($deque) && $deque[0] <= $i - $k) {
            array_shift($deque);
        }

        // Kicik elementleri sil
        while (!empty($deque) && $nums[end($deque)] <= $nums[$i]) {
            array_pop($deque);
        }

        $deque[] = $i;

        if ($i >= $k - 1) {
            $result[] = $nums[$deque[0]];
        }
    }

    return $result;
}
```

### 2. Find All Anagrams (LeetCode 438)
```php
<?php
function findAnagrams(string $s, string $p): array
{
    $result = [];
    $pLen = strlen($p);
    $sLen = strlen($s);
    if ($sLen < $pLen) return [];

    $pCount = array_count_values(str_split($p));
    $wCount = [];

    for ($i = 0; $i < $sLen; $i++) {
        $wCount[$s[$i]] = ($wCount[$s[$i]] ?? 0) + 1;

        if ($i >= $pLen) {
            $left = $s[$i - $pLen];
            $wCount[$left]--;
            if ($wCount[$left] === 0) unset($wCount[$left]);
        }

        if ($i >= $pLen - 1 && $wCount == $pCount) {
            $result[] = $i - $pLen + 1;
        }
    }

    return $result;
}
```

## Interview Suallari

1. **Fixed vs Variable window ferqi?**
   - Fixed: Window size melumdur (k), yalniz kaydirmaq lazim
   - Variable: Serte gore window boyur/kicilir
   - Fixed daha sadedir, variable `while` ile left pointer hereketi edir

2. **Sliding window ne vaxt istifade olunur?**
   - Ardical subarray/substring problemleri
   - "Minimum/maximum length", "longest/shortest" ifadeleri
   - "Contains all characters", "sum equals k"

3. **Minimum window substring nece isleyir?**
   - Iki hash map: need (lazim olan) ve have (pencerede olan)
   - Right ile genislet, formed === required olanda left ile daralt
   - Her valid pencerede minimum uzunlugu yenile

4. **Sliding window vs Two Pointers?**
   - Sliding window: ardical subarray/substring, pencere surur
   - Two pointers: sorted array, iki ucdan bir-birine yaximlasir
   - Bezi problemlerde her ikisi de istifade oluna biler

## PHP/Laravel ile Elaqe

- **Rate limiting**: Son N saniyede nece request olub? (fixed window)
- **Moving average**: Son N gundeki ortalama giris (analytics)
- **Log analysis**: Son 1 saatdeki error sayi (monitoring)
- **Stream processing**: Laravel Horizon queue monitoring
- **Cache TTL**: Sliding window ile cache expiry
