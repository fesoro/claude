# Bit Manipulation (Lead)

## Konsept (Concept)

Bit manipulation ededlerin binary temsili uzerinde isleyen emeliyyatlardir. Coxsureti edir, yaddas qenaet edir ve bezi problemleri elegant hell edir.

```
Decimal -> Binary:
  5  = 0101
  3  = 0011
  10 = 1010

Operators:
  AND (&):  0101 & 0011 = 0001  (her ikisi 1 olmalidi)
  OR  (|):  0101 | 0011 = 0111  (en azi biri 1 olmalidi)
  XOR (^):  0101 ^ 0011 = 0110  (ferqli olmalidir)
  NOT (~):  ~0101 = 1010         (tersine cevir)
  Left Shift (<<):  0101 << 1 = 1010  (*2)
  Right Shift (>>): 0101 >> 1 = 0010  (/2)
```

### Vacib identitiler:
```
x & 0 = 0          x | 0 = x          x ^ 0 = x
x & x = x          x | x = x          x ^ x = 0
x & ~x = 0         x | ~x = all 1s
x & (x-1) = en sag 1-i silir
x & (-x) = en sag 1-i saxlayir (lowest set bit)
```

## Nece Isleyir? (How does it work?)

### XOR xususiyyetleri:
```
a ^ a = 0     (eyni ededler sifir verir)
a ^ 0 = a     (sifir ile identite)
a ^ b ^ a = b (cemiyyetli ve birlesdirici)

Misal - Single Number:
[4, 1, 2, 1, 2]
4 ^ 1 ^ 2 ^ 1 ^ 2
= 4 ^ (1 ^ 1) ^ (2 ^ 2)
= 4 ^ 0 ^ 0
= 4
```

### Power of 2 yoxlama:
```
8 = 1000
7 = 0111
8 & 7 = 0000  -> 0 ise, power of 2-dir!

6 = 0110
5 = 0101
6 & 5 = 0100  -> 0 deyil, power of 2 deyil
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Single Number (LeetCode 136)
 * Butun ededler cut defe, biri tek defe gorunur
 * Time: O(n), Space: O(1)
 */
function singleNumber(array $nums): int
{
    $result = 0;
    foreach ($nums as $num) {
        $result ^= $num; // XOR: eyni ededler bir-birini legv edir
    }
    return $result;
}

/**
 * Number of 1 Bits / Hamming Weight (LeetCode 191)
 * Time: O(number of 1 bits), Space: O(1)
 */
function hammingWeight(int $n): int
{
    $count = 0;
    while ($n !== 0) {
        $n &= ($n - 1); // En sag 1-i sil
        $count++;
    }
    return $count;
}

/**
 * Power of Two (LeetCode 231)
 * Time: O(1), Space: O(1)
 */
function isPowerOfTwo(int $n): bool
{
    return $n > 0 && ($n & ($n - 1)) === 0;
}

/**
 * Counting Bits (LeetCode 338)
 * 0-den n-e qeder her ededin 1-bit sayini tap
 * Time: O(n), Space: O(n)
 */
function countBits(int $n): array
{
    $result = array_fill(0, $n + 1, 0);
    for ($i = 1; $i <= $n; $i++) {
        $result[$i] = $result[$i >> 1] + ($i & 1);
        // i >> 1: yariya bol, i & 1: son bit
    }
    return $result;
}

/**
 * Reverse Bits (LeetCode 190)
 * Time: O(32) = O(1), Space: O(1)
 */
function reverseBits(int $n): int
{
    $result = 0;
    for ($i = 0; $i < 32; $i++) {
        $result = ($result << 1) | ($n & 1);
        $n >>= 1;
    }
    return $result;
}

/**
 * Missing Number (LeetCode 268)
 * [0, n] araliginda bir eded eksikdir
 * Time: O(n), Space: O(1)
 */
function missingNumber(array $nums): int
{
    $xor = count($nums);
    for ($i = 0; $i < count($nums); $i++) {
        $xor ^= $i ^ $nums[$i];
    }
    return $xor;
}

/**
 * Sum of Two Integers (LeetCode 371) - without + or -
 * Time: O(32) = O(1), Space: O(1)
 */
function getSum(int $a, int $b): int
{
    while ($b !== 0) {
        $carry = $a & $b;     // carry bitleri
        $a = $a ^ $b;         // cemlesdir (carry-siz)
        $b = $carry << 1;     // carry-ni saga kes
    }
    return $a;
}

/**
 * Bit manipulation utility functions
 */
class BitUtils
{
    // i-ci bit-i al
    public static function getBit(int $n, int $i): int
    {
        return ($n >> $i) & 1;
    }

    // i-ci bit-i 1 et
    public static function setBit(int $n, int $i): int
    {
        return $n | (1 << $i);
    }

    // i-ci bit-i 0 et
    public static function clearBit(int $n, int $i): int
    {
        return $n & ~(1 << $i);
    }

    // i-ci bit-i toggle et
    public static function toggleBit(int $n, int $i): int
    {
        return $n ^ (1 << $i);
    }

    // En sag 1-bit-i al
    public static function lowestSetBit(int $n): int
    {
        return $n & (-$n);
    }

    // Tek/cut yoxla
    public static function isOdd(int $n): bool
    {
        return ($n & 1) === 1;
    }

    // 2-ye vurma
    public static function multiplyBy2(int $n): int
    {
        return $n << 1;
    }

    // 2-ye bolme
    public static function divideBy2(int $n): int
    {
        return $n >> 1;
    }

    // Swap without temp
    public static function swap(int &$a, int &$b): void
    {
        $a ^= $b;
        $b ^= $a;
        $a ^= $b;
    }
}

/**
 * Subsets using bitmask (LeetCode 78)
 * Time: O(N * 2^N), Space: O(N)
 */
function subsetsBitmask(array $nums): array
{
    $n = count($nums);
    $result = [];

    for ($mask = 0; $mask < (1 << $n); $mask++) {
        $subset = [];
        for ($i = 0; $i < $n; $i++) {
            if ($mask & (1 << $i)) {
                $subset[] = $nums[$i];
            }
        }
        $result[] = $subset;
    }

    return $result;
}

/**
 * Single Number II (LeetCode 137)
 * Her eded 3 defe, biri 1 defe
 * Time: O(n), Space: O(1)
 */
function singleNumberII(array $nums): int
{
    $ones = 0;
    $twos = 0;

    foreach ($nums as $num) {
        $ones = ($ones ^ $num) & ~$twos;
        $twos = ($twos ^ $num) & ~$ones;
    }

    return $ones;
}

/**
 * Single Number III (LeetCode 260)
 * Iki eded tek defe, qalanlari cut defe
 * Time: O(n), Space: O(1)
 */
function singleNumberIII(array $nums): array
{
    $xor = 0;
    foreach ($nums as $num) $xor ^= $num;

    // En sag ferqli bit-i tap
    $diff = $xor & (-$xor);

    $a = 0;
    $b = 0;
    foreach ($nums as $num) {
        if ($num & $diff) {
            $a ^= $num;
        } else {
            $b ^= $num;
        }
    }

    return [$a, $b];
}

/**
 * Hamming Distance (LeetCode 461)
 * Time: O(1), Space: O(1)
 */
function hammingDistance(int $x, int $y): int
{
    $xor = $x ^ $y;
    $count = 0;
    while ($xor) {
        $xor &= ($xor - 1);
        $count++;
    }
    return $count;
}

// --- Test ---
echo "Single number [4,1,2,1,2]: " . singleNumber([4,1,2,1,2]) . "\n"; // 4
echo "Hamming weight 11: " . hammingWeight(11) . "\n"; // 3 (1011)
echo "Power of 2 (16): " . (isPowerOfTwo(16) ? 'yes' : 'no') . "\n"; // yes
echo "Missing number [3,0,1]: " . missingNumber([3,0,1]) . "\n"; // 2
echo "Sum 3+5: " . getSum(3, 5) . "\n"; // 8
echo "Hamming dist 1,4: " . hammingDistance(1, 4) . "\n"; // 2

echo "Count bits 0-5: " . implode(',', countBits(5)) . "\n"; // 0,1,1,2,1,2

echo "Bit 2 of 5(101): " . BitUtils::getBit(5, 2) . "\n"; // 1
echo "Set bit 1 of 5(101): " . BitUtils::setBit(5, 1) . "\n"; // 7(111)
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space |
|---------|------|-------|
| Single Number | O(n) | O(1) |
| Hamming Weight | O(k) k=bit count | O(1) |
| Power of Two | O(1) | O(1) |
| Counting Bits | O(n) | O(n) |
| Missing Number | O(n) | O(1) |
| Subsets Bitmask | O(N * 2^N) | O(N) |

## Tipik Meseler (Common Problems)

### 1. Bitwise AND of Range (LeetCode 201)
```php
<?php
function rangeBitwiseAnd(int $left, int $right): int
{
    $shift = 0;
    while ($left !== $right) {
        $left >>= 1;
        $right >>= 1;
        $shift++;
    }
    return $left << $shift;
}
```

### 2. UTF-8 Validation (LeetCode 393)
```php
<?php
function validUtf8(array $data): bool
{
    $remaining = 0;

    foreach ($data as $byte) {
        if ($remaining > 0) {
            if (($byte >> 6) !== 0b10) return false;
            $remaining--;
        } elseif (($byte >> 7) === 0) {
            $remaining = 0;
        } elseif (($byte >> 5) === 0b110) {
            $remaining = 1;
        } elseif (($byte >> 4) === 0b1110) {
            $remaining = 2;
        } elseif (($byte >> 3) === 0b11110) {
            $remaining = 3;
        } else {
            return false;
        }
    }

    return $remaining === 0;
}
```

## Interview Suallari

1. **XOR niye Single Number-da isleyir?**
   - a ^ a = 0 (eyni ededler sifir verir)
   - a ^ 0 = a (sifir identitedir)
   - Commutativity: sira onemsiz
   - Butun cut defe gorunen ededler legv olur, tek qalan qalir

2. **n & (n-1) ne edir?**
   - En sag 1-bit-i silir
   - Power of 2 yoxlamaq: n & (n-1) === 0
   - Bit count: nece defe bu emeliyyati ede bilersen

3. **Bit manipulation niye istifade olunur?**
   - O(1) space ile problemleri hell etmek
   - Hardware/driver proqramlasdirma
   - Bitmask ile set emeliyyatlari (permissions, features)
   - Sureti: CPU bit emeliyyatlarini coxsureti icra edir

4. **Bitmask ile subset nece yaradilir?**
   - n element ucun 2^n mask (0-dan 2^n-1-e)
   - mask-in i-ci bit-i 1-dirse, i-ci element subset-dedir
   - Butun subset-leri generate edir

## PHP/Laravel ile Elaqe

- **Permission systems**: Laravel bitmask permissions (read=4, write=2, exec=1)
- **Feature flags**: Bitmask ile feature on/off
- **Status tracking**: Bir integer-de bir nece boolean saxla
- **Color manipulation**: RGB bit operations
- **Network**: IP address, subnet mask bit emeliyyatlari
- **Encryption**: XOR cipher, hash functions

---

## Praktik Tapşırıqlar

1. **LeetCode 136** — Single Number (XOR ilə duplicatesiz element)
2. **LeetCode 191** — Number of 1 Bits (Hamming weight)
3. **LeetCode 338** — Counting Bits (DP + bit trick)
4. **LeetCode 371** — Sum of Two Integers (toplamasız cəm — bit ilə)
5. **LeetCode 268** — Missing Number (XOR ilə itən ədədi tap)

### Step-by-step: Single Number ilə XOR

```
nums = [4, 1, 2, 1, 2]

4 XOR 1 = 0100 XOR 0001 = 0101 = 5
5 XOR 2 = 0101 XOR 0010 = 0111 = 7
7 XOR 1 = 0111 XOR 0001 = 0110 = 6
6 XOR 2 = 0110 XOR 0010 = 0100 = 4 ✓

Əsas property: a XOR a = 0, a XOR 0 = a
Cüt sayda görünən hər ədəd öz-özünü cancel edir.
```

---

## Əlaqəli Mövzular

- [34-math-problems.md](34-math-problems.md) — Riyazi alqoritmlər
- [37-advanced-dp.md](37-advanced-dp.md) — Bitmask DP (state compression)
- [01-big-o-notation.md](01-big-o-notation.md) — Bit ops O(1) complexity
