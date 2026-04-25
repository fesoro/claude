# Two Pointers (Middle)

## Konsept (Concept)

Two Pointers sorted array-de ve ya linked list-de iki index/pointer istifade edib problemi hell edir. Brute force O(n^2)-ni O(n)-e endirir.

```
Pattern 1 - Opposite Direction (qarsidan):
  [1, 2, 3, 4, 5, 6, 7]
   ^                  ^
   left            right
   -> iki terefden yaximlas <-

Pattern 2 - Same Direction (eyni):
  [1, 2, 0, 0, 3, 0, 4]
   ^
   slow
   ^
   fast
   -> fast qabaqdadir ->

Pattern 3 - Two Arrays:
  arr1: [1, 3, 5]    arr2: [2, 4, 6]
         ^                   ^
         i                   j
```

## Nece Isleyir? (How does it work?)

### Two Sum (Sorted):
```
arr = [2, 7, 11, 15]  target = 9

left=0, right=3: 2+15=17 > 9 -> right--
left=0, right=2: 2+11=13 > 9 -> right--
left=0, right=1: 2+7=9 == target -> TAPILDI! [0, 1]
```

### Three Sum:
```
arr = [-1, 0, 1, 2, -1, -4]
Sorted: [-4, -1, -1, 0, 1, 2]

i=0 (-4): left=1, right=5: -4+(-1)+2=-3 < 0 -> left++
         left=2, right=5: -4+(-1)+2=-3 < 0 -> left++
         ... (heç birisi 0 vermir)

i=1 (-1): left=2, right=5: -1+(-1)+2=0 ✓ -> [-1,-1,2]
          left=3, right=4: -1+0+1=0 ✓ -> [-1,0,1]

i=2 (-1): skip (duplicate)

Result: [[-1,-1,2], [-1,0,1]]
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Two Sum II - sorted array (LeetCode 167)
 * Time: O(n), Space: O(1)
 */
function twoSum(array $nums, int $target): array
{
    $left = 0;
    $right = count($nums) - 1;

    while ($left < $right) {
        $sum = $nums[$left] + $nums[$right];
        if ($sum === $target) {
            return [$left + 1, $right + 1]; // 1-indexed
        } elseif ($sum < $target) {
            $left++;
        } else {
            $right--;
        }
    }

    return [];
}

/**
 * Three Sum (LeetCode 15)
 * Time: O(n^2), Space: O(1)
 */
function threeSum(array $nums): array
{
    sort($nums);
    $result = [];
    $n = count($nums);

    for ($i = 0; $i < $n - 2; $i++) {
        if ($i > 0 && $nums[$i] === $nums[$i - 1]) continue; // skip duplicate

        $left = $i + 1;
        $right = $n - 1;
        $target = -$nums[$i];

        while ($left < $right) {
            $sum = $nums[$left] + $nums[$right];
            if ($sum === $target) {
                $result[] = [$nums[$i], $nums[$left], $nums[$right]];
                while ($left < $right && $nums[$left] === $nums[$left + 1]) $left++;
                while ($left < $right && $nums[$right] === $nums[$right - 1]) $right--;
                $left++;
                $right--;
            } elseif ($sum < $target) {
                $left++;
            } else {
                $right--;
            }
        }
    }

    return $result;
}

/**
 * Container With Most Water (LeetCode 11)
 * Time: O(n), Space: O(1)
 */
function maxArea(array $height): int
{
    $left = 0;
    $right = count($height) - 1;
    $maxWater = 0;

    while ($left < $right) {
        $width = $right - $left;
        $h = min($height[$left], $height[$right]);
        $maxWater = max($maxWater, $width * $h);

        if ($height[$left] < $height[$right]) {
            $left++;
        } else {
            $right--;
        }
    }

    return $maxWater;
}

/**
 * Trapping Rain Water (LeetCode 42)
 * Time: O(n), Space: O(1)
 */
function trap(array $height): int
{
    $left = 0;
    $right = count($height) - 1;
    $leftMax = 0;
    $rightMax = 0;
    $water = 0;

    while ($left < $right) {
        if ($height[$left] < $height[$right]) {
            $leftMax = max($leftMax, $height[$left]);
            $water += $leftMax - $height[$left];
            $left++;
        } else {
            $rightMax = max($rightMax, $height[$right]);
            $water += $rightMax - $height[$right];
            $right--;
        }
    }

    return $water;
}

/**
 * Remove Duplicates from Sorted Array (LeetCode 26)
 * Same-direction pointers
 * Time: O(n), Space: O(1)
 */
function removeDuplicates(array &$nums): int
{
    if (empty($nums)) return 0;

    $slow = 0;
    for ($fast = 1; $fast < count($nums); $fast++) {
        if ($nums[$fast] !== $nums[$slow]) {
            $slow++;
            $nums[$slow] = $nums[$fast];
        }
    }

    return $slow + 1;
}

/**
 * Move Zeroes (LeetCode 283)
 * Time: O(n), Space: O(1)
 */
function moveZeroes(array &$nums): void
{
    $slow = 0;

    for ($fast = 0; $fast < count($nums); $fast++) {
        if ($nums[$fast] !== 0) {
            [$nums[$slow], $nums[$fast]] = [$nums[$fast], $nums[$slow]];
            $slow++;
        }
    }
}

/**
 * Sort Colors (LeetCode 75) - Dutch National Flag
 * Three pointers
 * Time: O(n), Space: O(1)
 */
function sortColors(array &$nums): void
{
    $lo = 0;
    $mid = 0;
    $hi = count($nums) - 1;

    while ($mid <= $hi) {
        if ($nums[$mid] === 0) {
            [$nums[$lo], $nums[$mid]] = [$nums[$mid], $nums[$lo]];
            $lo++;
            $mid++;
        } elseif ($nums[$mid] === 1) {
            $mid++;
        } else {
            [$nums[$mid], $nums[$hi]] = [$nums[$hi], $nums[$mid]];
            $hi--;
        }
    }
}

/**
 * Linked List Cycle (LeetCode 141) - Fast/Slow pointers
 * Time: O(n), Space: O(1)
 */
function hasCycle(?object $head): bool
{
    $slow = $head;
    $fast = $head;

    while ($fast !== null && $fast->next !== null) {
        $slow = $slow->next;
        $fast = $fast->next->next;
        if ($slow === $fast) return true;
    }

    return false;
}

/**
 * Palindrome check with two pointers
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
 * Merge Sorted Arrays (two array pointers)
 * Time: O(m+n), Space: O(m+n)
 */
function mergeSorted(array $a, array $b): array
{
    $result = [];
    $i = $j = 0;

    while ($i < count($a) && $j < count($b)) {
        if ($a[$i] <= $b[$j]) {
            $result[] = $a[$i++];
        } else {
            $result[] = $b[$j++];
        }
    }

    while ($i < count($a)) $result[] = $a[$i++];
    while ($j < count($b)) $result[] = $b[$j++];

    return $result;
}

/**
 * 4Sum (LeetCode 18)
 * Time: O(n^3), Space: O(1)
 */
function fourSum(array $nums, int $target): array
{
    sort($nums);
    $n = count($nums);
    $result = [];

    for ($i = 0; $i < $n - 3; $i++) {
        if ($i > 0 && $nums[$i] === $nums[$i - 1]) continue;
        for ($j = $i + 1; $j < $n - 2; $j++) {
            if ($j > $i + 1 && $nums[$j] === $nums[$j - 1]) continue;

            $left = $j + 1;
            $right = $n - 1;

            while ($left < $right) {
                $sum = $nums[$i] + $nums[$j] + $nums[$left] + $nums[$right];
                if ($sum === $target) {
                    $result[] = [$nums[$i], $nums[$j], $nums[$left], $nums[$right]];
                    while ($left < $right && $nums[$left] === $nums[$left + 1]) $left++;
                    while ($left < $right && $nums[$right] === $nums[$right - 1]) $right--;
                    $left++;
                    $right--;
                } elseif ($sum < $target) {
                    $left++;
                } else {
                    $right--;
                }
            }
        }
    }

    return $result;
}

// --- Test ---
echo "Two Sum [2,7,11,15] t=9: " . implode(',', twoSum([2,7,11,15], 9)) . "\n"; // 1,2
echo "Max water: " . maxArea([1,8,6,2,5,4,8,3,7]) . "\n"; // 49
echo "Trap rain: " . trap([0,1,0,2,1,0,1,3,2,1,2,1]) . "\n"; // 6
echo "Palindrome 'racecar': " . (isPalindrome('racecar') ? 'yes' : 'no') . "\n"; // yes

$three = threeSum([-1,0,1,2,-1,-4]);
foreach ($three as $t) echo "[" . implode(',', $t) . "] ";
echo "\n"; // [-1,-1,2] [-1,0,1]
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space | Pattern |
|---------|------|-------|---------|
| Two Sum (sorted) | O(n) | O(1) | Opposite |
| Three Sum | O(n^2) | O(1) | Opposite |
| Container Water | O(n) | O(1) | Opposite |
| Trapping Rain | O(n) | O(1) | Opposite |
| Remove Duplicates | O(n) | O(1) | Same dir |
| Move Zeroes | O(n) | O(1) | Same dir |
| Linked List Cycle | O(n) | O(1) | Fast/Slow |

## Tipik Meseler (Common Problems)

### 1. 3Sum Closest (LeetCode 16)
```php
<?php
function threeSumClosest(array $nums, int $target): int
{
    sort($nums);
    $closest = $nums[0] + $nums[1] + $nums[2];

    for ($i = 0; $i < count($nums) - 2; $i++) {
        $left = $i + 1;
        $right = count($nums) - 1;

        while ($left < $right) {
            $sum = $nums[$i] + $nums[$left] + $nums[$right];
            if (abs($sum - $target) < abs($closest - $target)) {
                $closest = $sum;
            }
            if ($sum < $target) $left++;
            elseif ($sum > $target) $right--;
            else return $target;
        }
    }

    return $closest;
}
```

### 2. Reverse Words in String
```php
<?php
function reverseWords(string $s): string
{
    $words = preg_split('/\s+/', trim($s));
    $left = 0;
    $right = count($words) - 1;

    while ($left < $right) {
        [$words[$left], $words[$right]] = [$words[$right], $words[$left]];
        $left++;
        $right--;
    }

    return implode(' ', $words);
}
```

## Interview Suallari

1. **Two Pointers nece isleyir?**
   - Sorted array-de iki ucdan basla, cema gore yaxinlas ve ya uzaqlas
   - Sum boyukdurse right--, kicikdirse left++
   - Her addimda search space yarilir

2. **Container With Most Water nece O(n)-dir?**
   - Kicik teref hereket edir, cunki boyuk teref saxlanmalidir
   - Her addimda en az 1 pointer hereket edir -> max n addim

3. **Trapping Rain Water uce nece yanasmaq olar?**
   - Brute force: O(n^2) her index ucun sol/sag max tap
   - DP: O(n) space ile leftMax/rightMax massivleri
   - Two pointers: O(1) space, min(leftMax, rightMax) prinsipile

4. **Fast/Slow pointer niye cycle tapir?**
   - Fast 2x suretlidir, cycle varsa mutleq slow-a catir
   - Floyd's algorithm: meeting point-den ve head-den eyni suretde getsen, cycle basinda gorususurler

## PHP/Laravel ile Elaqe

- **Data merge**: Iki sorted collection-u birlesdir
- **Diff algorithm**: Iki versiyanin muqayisesi
- **Pagination merge**: Iki farkli source-dan siralanmis data merge
- **Queue processing**: Fast/slow consumer pattern
- **String processing**: Palindrome validation, string cleaning
