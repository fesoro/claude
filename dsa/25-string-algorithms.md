# String Algorithms (String Algoritmleri)

## Konsept (Concept)

String algoritmleri metn emeliyyatlari, pattern matching ve string manipulation ucun istifade olunur. Naive axtaris O(mn) vaxt alir, amma KMP ve Rabin-Karp O(n) vaxtda isleyir.

```
Pattern Matching:
  Text:    "ABABDABACDABABCABAB"
  Pattern: "ABABCABAB"

  Naive: Her movqeyi yoxla -> O(m*n)
  KMP:   Failure function ile geri qayitma -> O(m+n)
  Rabin-Karp: Hash muqayisesi -> O(m+n) average

String Hashing:
  hash("abc") = a*31^2 + b*31^1 + c*31^0
  Rolling hash: kohen herfi cixar, yeni herfi elave et
```

## Nece Isleyir? (How does it work?)

### KMP (Knuth-Morris-Pratt):
```
Pattern: "ABABCABAB"
Failure function (longest proper prefix = suffix):
  Index:   0 1 2 3 4 5 6 7 8
  Pattern: A B A B C A B A B
  Fail:    0 0 1 2 0 1 2 3 4

Text:    ABABDABACDABABCABAB
Pattern: ABABC
         ^^^^X  (mismatch at index 4)
         -> fail[3]=2, pattern-i 2 movqe geri cek
              ABABCABAB
              Match tapildi!
```

### Rabin-Karp:
```
Text:    "abcdef", Pattern: "cde"
Hash pattern = hash("cde")

Rolling hash:
  hash("abc") != hash("cde") -> next
  hash("bcd") = hash("abc") - 'a' + 'd'  != hash("cde") -> next
  hash("cde") == hash("cde") -> verify characters -> MATCH!
```

## Implementasiya (Implementation)

```php
<?php

/**
 * KMP Pattern Matching
 * Time: O(n + m), Space: O(m)
 */
function kmpSearch(string $text, string $pattern): array
{
    $n = strlen($text);
    $m = strlen($pattern);
    $matches = [];

    if ($m === 0) return [];

    // Failure function qur
    $fail = buildFailure($pattern);

    $j = 0; // pattern index
    for ($i = 0; $i < $n; $i++) {
        while ($j > 0 && $text[$i] !== $pattern[$j]) {
            $j = $fail[$j - 1];
        }

        if ($text[$i] === $pattern[$j]) {
            $j++;
        }

        if ($j === $m) {
            $matches[] = $i - $m + 1;
            $j = $fail[$j - 1];
        }
    }

    return $matches;
}

function buildFailure(string $pattern): array
{
    $m = strlen($pattern);
    $fail = array_fill(0, $m, 0);
    $len = 0;

    for ($i = 1; $i < $m; ) {
        if ($pattern[$i] === $pattern[$len]) {
            $len++;
            $fail[$i] = $len;
            $i++;
        } elseif ($len > 0) {
            $len = $fail[$len - 1];
        } else {
            $fail[$i] = 0;
            $i++;
        }
    }

    return $fail;
}

/**
 * Rabin-Karp Pattern Matching
 * Time: O(n + m) average, O(nm) worst, Space: O(1)
 */
function rabinKarp(string $text, string $pattern): array
{
    $n = strlen($text);
    $m = strlen($pattern);
    $matches = [];
    $base = 31;
    $mod = 1000000007;

    if ($m > $n) return [];

    // Pattern hash ve ilk window hash
    $patternHash = 0;
    $textHash = 0;
    $power = 1;

    for ($i = 0; $i < $m; $i++) {
        $patternHash = ($patternHash * $base + ord($pattern[$i])) % $mod;
        $textHash = ($textHash * $base + ord($text[$i])) % $mod;
        if ($i > 0) $power = ($power * $base) % $mod;
    }

    for ($i = 0; $i <= $n - $m; $i++) {
        if ($textHash === $patternHash) {
            // Hash match, character-leri verify et
            if (substr($text, $i, $m) === $pattern) {
                $matches[] = $i;
            }
        }

        if ($i < $n - $m) {
            // Rolling hash: kohen herfi cixar, yeni herfi elave et
            $textHash = ($textHash - ord($text[$i]) * $power % $mod + $mod) % $mod;
            $textHash = ($textHash * $base + ord($text[$i + $m])) % $mod;
        }
    }

    return $matches;
}

/**
 * Anagram Detection
 * Time: O(n), Space: O(1) - 26 herf
 */
function isAnagram(string $s, string $t): bool
{
    if (strlen($s) !== strlen($t)) return false;

    $count = array_fill(0, 26, 0);
    for ($i = 0; $i < strlen($s); $i++) {
        $count[ord($s[$i]) - ord('a')]++;
        $count[ord($t[$i]) - ord('a')]--;
    }

    foreach ($count as $c) {
        if ($c !== 0) return false;
    }
    return true;
}

/**
 * Group Anagrams (LeetCode 49)
 * Time: O(n * k log k) where k = max string length, Space: O(nk)
 */
function groupAnagrams(array $strs): array
{
    $groups = [];

    foreach ($strs as $s) {
        $key = str_split($s);
        sort($key);
        $key = implode('', $key);
        $groups[$key][] = $s;
    }

    return array_values($groups);
}

/**
 * Longest Palindromic Substring (LeetCode 5)
 * Expand from center
 * Time: O(n^2), Space: O(1)
 */
function longestPalindrome(string $s): string
{
    $start = 0;
    $maxLen = 1;

    for ($i = 0; $i < strlen($s); $i++) {
        // Tek merkez
        $len1 = expandFromCenter($s, $i, $i);
        // Cut merkez
        $len2 = expandFromCenter($s, $i, $i + 1);
        $len = max($len1, $len2);

        if ($len > $maxLen) {
            $maxLen = $len;
            $start = $i - (int)(($len - 1) / 2);
        }
    }

    return substr($s, $start, $maxLen);
}

function expandFromCenter(string $s, int $left, int $right): int
{
    while ($left >= 0 && $right < strlen($s) && $s[$left] === $s[$right]) {
        $left--;
        $right++;
    }
    return $right - $left - 1;
}

/**
 * Valid Palindrome (LeetCode 125)
 * Time: O(n), Space: O(1)
 */
function isPalindrome(string $s): bool
{
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $s));
    $left = 0;
    $right = strlen($s) - 1;

    while ($left < $right) {
        if ($s[$left] !== $s[$right]) return false;
        $left++;
        $right--;
    }

    return true;
}

/**
 * String Hashing
 * Polynomial rolling hash
 */
function stringHash(string $s, int $mod = 1000000007): int
{
    $hash = 0;
    $base = 31;

    for ($i = 0; $i < strlen($s); $i++) {
        $hash = ($hash * $base + ord($s[$i]) - ord('a') + 1) % $mod;
    }

    return $hash;
}

/**
 * Longest Common Prefix (LeetCode 14)
 * Time: O(S) S=total characters, Space: O(1)
 */
function longestCommonPrefix(array $strs): string
{
    if (empty($strs)) return '';

    $prefix = $strs[0];
    for ($i = 1; $i < count($strs); $i++) {
        while (strpos($strs[$i], $prefix) !== 0) {
            $prefix = substr($prefix, 0, -1);
            if (empty($prefix)) return '';
        }
    }

    return $prefix;
}

/**
 * Z-Algorithm - pattern matching alternative to KMP
 * Time: O(n + m), Space: O(n + m)
 */
function zAlgorithm(string $text, string $pattern): array
{
    $s = $pattern . '$' . $text;
    $n = strlen($s);
    $z = array_fill(0, $n, 0);
    $l = $r = 0;

    for ($i = 1; $i < $n; $i++) {
        if ($i < $r) {
            $z[$i] = min($r - $i, $z[$i - $l]);
        }
        while ($i + $z[$i] < $n && $s[$z[$i]] === $s[$i + $z[$i]]) {
            $z[$i]++;
        }
        if ($i + $z[$i] > $r) {
            $l = $i;
            $r = $i + $z[$i];
        }
    }

    $matches = [];
    $m = strlen($pattern);
    for ($i = $m + 1; $i < $n; $i++) {
        if ($z[$i] === $m) {
            $matches[] = $i - $m - 1;
        }
    }

    return $matches;
}

/**
 * Implement strStr (LeetCode 28)
 */
function strStr(string $haystack, string $needle): int
{
    $result = kmpSearch($haystack, $needle);
    return empty($result) ? -1 : $result[0];
}

// --- Test ---
echo "KMP 'ABABDABACDABABCABAB', 'ABABCABAB': ";
echo implode(', ', kmpSearch('ABABDABACDABABCABAB', 'ABABCABAB')) . "\n"; // 9

echo "Rabin-Karp 'hello world', 'world': ";
echo implode(', ', rabinKarp('hello world', 'world')) . "\n"; // 6

echo "Anagram 'listen','silent': " . (isAnagram('listen', 'silent') ? 'yes' : 'no') . "\n";

echo "Longest palindrome 'babad': " . longestPalindrome('babad') . "\n"; // bab or aba

$groups = groupAnagrams(["eat","tea","tan","ate","nat","bat"]);
echo "Anagram groups: " . count($groups) . "\n"; // 3

echo "Common prefix: " . longestCommonPrefix(["flower","flow","flight"]) . "\n"; // fl
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Time | Space | Qeyd |
|----------|------|-------|------|
| Naive search | O(mn) | O(1) | Sade amma yavas |
| KMP | O(m+n) | O(m) | Failure function |
| Rabin-Karp | O(m+n) avg | O(1) | Hash collision riski |
| Z-Algorithm | O(m+n) | O(m+n) | KMP alternativi |
| Anagram check | O(n) | O(1) | Frequency count |
| Palindrome | O(n^2) | O(1) | Expand center |

## Tipik Meseler (Common Problems)

### 1. Repeated Substring Pattern (LeetCode 459)
```php
<?php
function repeatedSubstringPattern(string $s): bool
{
    $doubled = $s . $s;
    $inner = substr($doubled, 1, strlen($doubled) - 2);
    return strpos($inner, $s) !== false;
}
```

### 2. Count and Say (LeetCode 38)
```php
<?php
function countAndSay(int $n): string
{
    $result = '1';
    for ($i = 2; $i <= $n; $i++) {
        $next = '';
        $count = 1;
        for ($j = 1; $j < strlen($result); $j++) {
            if ($result[$j] === $result[$j - 1]) {
                $count++;
            } else {
                $next .= $count . $result[$j - 1];
                $count = 1;
            }
        }
        $next .= $count . $result[strlen($result) - 1];
        $result = $next;
    }
    return $result;
}
```

### 3. String to Integer atoi (LeetCode 8)
```php
<?php
function myAtoi(string $s): int
{
    $s = ltrim($s);
    if (empty($s)) return 0;

    $sign = 1;
    $i = 0;
    if ($s[0] === '-' || $s[0] === '+') {
        $sign = $s[0] === '-' ? -1 : 1;
        $i++;
    }

    $result = 0;
    $max = 2147483647;
    $min = -2147483648;

    while ($i < strlen($s) && ctype_digit($s[$i])) {
        $digit = ord($s[$i]) - ord('0');
        if ($result > (int)(($max - $digit) / 10)) {
            return $sign === 1 ? $max : $min;
        }
        $result = $result * 10 + $digit;
        $i++;
    }

    return $sign * $result;
}
```

## Interview Suallari

1. **KMP vs Rabin-Karp ferqi?**
   - KMP: O(n+m) guaranteed, failure function lazim
   - Rabin-Karp: O(n+m) average, hash collision worst case O(nm)
   - Rabin-Karp multi-pattern search ucun daha yaxsidir

2. **String hashing niye istifade olunur?**
   - String muqayisesi O(n)-den O(1)-e enir
   - Rolling hash ile substring axtarisi suretlenir
   - Collision riski: iki ferqli string eyni hash verle biler

3. **Anagram detection nece effektiv edilir?**
   - Sort: O(n log n) - her ikisini sort et, muqayise et
   - Frequency count: O(n) - 26 olculuklu array ile
   - Hash: sorted string-i key kimi istifade et

4. **Palindrome nece yoxlanir?**
   - Two pointers: O(n) time, O(1) space
   - Expand from center: substring ucun
   - DP: O(n^2) butun substring-ler ucun
   - Manacher: O(n) en sureti amma murakkab

## PHP/Laravel ile Elaqe

- **`strpos()`**: PHP daxili string search (naive ve ya optimized)
- **`preg_match()`**: Regex engine NFA/DFA ile pattern matching
- **Full-text search**: Laravel Scout, Elasticsearch
- **Slug generation**: String manipulation
- **Input validation**: Email, URL, phone format yoxlama
- **Diff tools**: String comparison algoritmler (LCS)
