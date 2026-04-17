# Math Problems (Riyazi Meseleler)

## Konsept (Concept)

Interview-larda tez-tez qarşılaşılan riyazi mövzular: **GCD/LCM**, **prime numbers**, **modular arithmetic**, **fast exponentiation**, **combinatorics**. Bu mövzular həm alqoritm yazmaq, həm də riyazi baxışı göstərmək üçün vacibdir.

```
GCD/LCM:
GCD(12, 18) = 6 (ən böyük ortaq bölən)
LCM(12, 18) = 36 (ən kiçik ortaq böləndən əldə olunan katd)
LCM(a, b) = (a * b) / GCD(a, b)

Prime numbers:
2, 3, 5, 7, 11, 13, 17, 19, 23, ...

Modular arithmetic:
(a + b) % m = ((a % m) + (b % m)) % m
(a * b) % m = ((a % m) * (b % m)) % m

Fast Exponentiation (Binary Exponentiation):
x^n = x^(n/2) * x^(n/2)      eğer n even
x^n = x * x^((n-1)/2)^2      eğer n odd
```

### Əsas anlayışlar:
1. **Euclidean algorithm** — GCD tapmaq
2. **Sieve of Eratosthenes** — n-ə qədər bütün prime-ları tapmaq O(n log log n)
3. **Modular inverse** — `a * x ≡ 1 (mod m)` tənliyində x-in tapılması
4. **Factorial və binomial coefficients** — C(n, k) = n! / (k! * (n-k)!)
5. **Fermat's little theorem** — prime p üçün `a^(p-1) ≡ 1 (mod p)`

## Necə İşləyir? (How does it work?)

### Euclidean Algorithm (GCD):
```
GCD(48, 18):
48 = 18 * 2 + 12  -> GCD(18, 12)
18 = 12 * 1 + 6   -> GCD(12, 6)
12 = 6 * 2 + 0    -> GCD = 6

Qayda: GCD(a, b) = GCD(b, a % b), GCD(a, 0) = a
```

### Sieve of Eratosthenes:
```
n = 30

Start: [2,3,4,5,6,7,8,9,10,11,...,30]

p=2: 4,6,8,10,...,30 -> işarələ (composite)
p=3: 9,15,21,27 -> işarələ (6 artıq kəsilib)
p=5: 25 -> işarələ
p=7: 49 > 30 dayan (sqrt(30)≈5.47)

Prime-lar: [2, 3, 5, 7, 11, 13, 17, 19, 23, 29]
```

### Fast Exponentiation:
```
3^13 hesablamaq:
13 = 1101 (binary)

3^13 = 3^(8+4+1) = 3^8 * 3^4 * 3^1

Iterative:
result = 1
base = 3

n=13 odd: result = 1 * 3 = 3, base = 9, n = 6
n=6 even: base = 81, n = 3
n=3 odd:  result = 3 * 81 = 243, base = 6561, n = 1
n=1 odd:  result = 243 * 6561 = 1594323, n = 0

3^13 = 1594323 ✓
```

### Modular Inverse (Fermat's):
```
a^(-1) mod p = a^(p-2) mod p (eğer p prime)

Misal: 3^(-1) mod 7
= 3^5 mod 7
= 243 mod 7
= 5

Yoxlama: 3 * 5 = 15, 15 mod 7 = 1 ✓
```

### Binomial Coefficient (nCk):
```
C(n, k) = n! / (k! * (n-k)!)

C(5, 2) = 5! / (2! * 3!) = 120 / 12 = 10

Pascal triangle ile:
C(n, k) = C(n-1, k-1) + C(n-1, k)

     1
    1 1
   1 2 1
  1 3 3 1
 1 4 6 4 1
1 5 10 10 5 1
```

## Implementasiya (Implementation)

```php
<?php

class MathProblems
{
    // GCD (Euclidean algorithm)
    public function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return abs($a);
    }

    // LCM
    public function lcm(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) return 0;
        return intval(abs($a * $b) / $this->gcd($a, $b));
    }

    // GCD of array
    public function gcdArray(array $nums): int
    {
        $result = $nums[0];
        for ($i = 1; $i < count($nums); $i++) {
            $result = $this->gcd($result, $nums[$i]);
            if ($result === 1) return 1;
        }
        return $result;
    }

    // Extended Euclidean: ax + by = gcd(a,b)
    public function extendedGcd(int $a, int $b): array
    {
        if ($b === 0) return [$a, 1, 0];
        [$g, $x1, $y1] = $this->extendedGcd($b, $a % $b);
        return [$g, $y1, $x1 - intval($a / $b) * $y1];
    }

    // Prime check (trial division)
    public function isPrime(int $n): bool
    {
        if ($n < 2) return false;
        if ($n === 2) return true;
        if ($n % 2 === 0) return false;

        for ($i = 3; $i * $i <= $n; $i += 2) {
            if ($n % $i === 0) return false;
        }
        return true;
    }

    // Sieve of Eratosthenes
    public function sieveOfEratosthenes(int $n): array
    {
        if ($n < 2) return [];

        $isPrime = array_fill(0, $n + 1, true);
        $isPrime[0] = $isPrime[1] = false;

        for ($i = 2; $i * $i <= $n; $i++) {
            if ($isPrime[$i]) {
                for ($j = $i * $i; $j <= $n; $j += $i) {
                    $isPrime[$j] = false;
                }
            }
        }

        $primes = [];
        for ($i = 2; $i <= $n; $i++) {
            if ($isPrime[$i]) $primes[] = $i;
        }
        return $primes;
    }

    // Count primes less than n
    public function countPrimes(int $n): int
    {
        if ($n < 2) return 0;
        $isPrime = array_fill(0, $n, true);
        $isPrime[0] = $isPrime[1] = false;
        $count = 0;

        for ($i = 2; $i < $n; $i++) {
            if ($isPrime[$i]) {
                $count++;
                for ($j = $i * $i; $j < $n; $j += $i) {
                    $isPrime[$j] = false;
                }
            }
        }
        return $count;
    }

    // Prime factorization
    public function primeFactors(int $n): array
    {
        $factors = [];
        for ($i = 2; $i * $i <= $n; $i++) {
            while ($n % $i === 0) {
                $factors[] = $i;
                $n = intval($n / $i);
            }
        }
        if ($n > 1) $factors[] = $n;
        return $factors;
    }

    // Fast Exponentiation (binary)
    public function fastPow(int $base, int $exp): int
    {
        if ($exp < 0) throw new InvalidArgumentException();
        $result = 1;
        while ($exp > 0) {
            if ($exp & 1) {
                $result *= $base;
            }
            $base *= $base;
            $exp >>= 1;
        }
        return $result;
    }

    // Modular exponentiation: (base^exp) % mod
    public function modPow(int $base, int $exp, int $mod): int
    {
        if ($mod === 1) return 0;
        $result = 1;
        $base = $base % $mod;
        while ($exp > 0) {
            if ($exp & 1) {
                $result = ($result * $base) % $mod;
            }
            $exp >>= 1;
            $base = ($base * $base) % $mod;
        }
        return $result;
    }

    // Modular inverse (Fermat's little theorem, p prime)
    public function modInverse(int $a, int $p): int
    {
        return $this->modPow($a, $p - 2, $p);
    }

    // Factorial
    public function factorial(int $n): int
    {
        if ($n < 0) throw new InvalidArgumentException();
        $result = 1;
        for ($i = 2; $i <= $n; $i++) $result *= $i;
        return $result;
    }

    // Binomial coefficient C(n, k)
    public function binomial(int $n, int $k): int
    {
        if ($k > $n - $k) $k = $n - $k;
        $result = 1;
        for ($i = 0; $i < $k; $i++) {
            $result *= ($n - $i);
            $result = intval($result / ($i + 1));
        }
        return $result;
    }

    // Pascal's Triangle
    public function pascalTriangle(int $numRows): array
    {
        $triangle = [];
        for ($i = 0; $i < $numRows; $i++) {
            $row = array_fill(0, $i + 1, 1);
            for ($j = 1; $j < $i; $j++) {
                $row[$j] = $triangle[$i - 1][$j - 1] + $triangle[$i - 1][$j];
            }
            $triangle[] = $row;
        }
        return $triangle;
    }

    // Fibonacci (fast with matrix exponentiation spirit - iterative)
    public function fibonacci(int $n): int
    {
        if ($n < 2) return $n;
        $prev = 0; $curr = 1;
        for ($i = 2; $i <= $n; $i++) {
            [$prev, $curr] = [$curr, $prev + $curr];
        }
        return $curr;
    }

    // Reverse integer (watch for overflow)
    public function reverse(int $x): int
    {
        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);
        $result = 0;
        while ($x > 0) {
            $result = $result * 10 + $x % 10;
            $x = intval($x / 10);
        }
        $result *= $sign;
        return ($result > PHP_INT_MAX || $result < PHP_INT_MIN) ? 0 : $result;
    }

    // Integer square root (binary search)
    public function mySqrt(int $x): int
    {
        if ($x < 2) return $x;
        $left = 1; $right = intval($x / 2);
        while ($left <= $right) {
            $mid = intval(($left + $right) / 2);
            if ($mid * $mid === $x) return $mid;
            if ($mid * $mid < $x) {
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }
        return $right;
    }

    // Happy Number (cycle detection)
    public function isHappy(int $n): bool
    {
        $seen = [];
        while ($n !== 1 && !isset($seen[$n])) {
            $seen[$n] = true;
            $sum = 0;
            while ($n > 0) {
                $digit = $n % 10;
                $sum += $digit * $digit;
                $n = intval($n / 10);
            }
            $n = $sum;
        }
        return $n === 1;
    }
}

// Istifade
$mp = new MathProblems();

echo $mp->gcd(48, 18); // 6
echo $mp->lcm(12, 18); // 36
print_r($mp->sieveOfEratosthenes(30)); // [2,3,5,7,11,13,17,19,23,29]
echo $mp->fastPow(3, 13); // 1594323
echo $mp->modPow(3, 13, 1000); // 323
echo $mp->binomial(5, 2); // 10
print_r($mp->primeFactors(84)); // [2, 2, 3, 7]
```

## Vaxt və Yaddaş Mürəkkəbliyi (Time & Space Complexity)

| Əməliyyat | Time | Space |
|-----------|------|-------|
| GCD (Euclidean) | O(log(min(a,b))) | O(1) |
| LCM | O(log(min(a,b))) | O(1) |
| Prime check | O(√n) | O(1) |
| Sieve of Eratosthenes | O(n log log n) | O(n) |
| Prime factorization | O(√n) | O(log n) |
| Fast exponentiation | O(log n) | O(1) |
| Modular exponentiation | O(log n) | O(1) |
| Factorial (iterative) | O(n) | O(1) |
| Binomial coefficient | O(min(k, n-k)) | O(1) |
| Pascal triangle | O(n²) | O(n²) |

## Tipik Məsələlər (Common Problems)

### 1. Pow(x, n) - Real exponent
```php
public function myPow(float $x, int $n): float
{
    if ($n < 0) { $x = 1 / $x; $n = -$n; }
    $result = 1.0;
    while ($n > 0) {
        if ($n & 1) $result *= $x;
        $x *= $x;
        $n >>= 1;
    }
    return $result;
}
```

### 2. Ugly Number II (prime factors yalnız 2, 3, 5)
```php
public function nthUglyNumber(int $n): int
{
    $ugly = [1];
    $i2 = $i3 = $i5 = 0;
    while (count($ugly) < $n) {
        $next2 = $ugly[$i2] * 2;
        $next3 = $ugly[$i3] * 3;
        $next5 = $ugly[$i5] * 5;
        $next = min($next2, $next3, $next5);
        $ugly[] = $next;
        if ($next === $next2) $i2++;
        if ($next === $next3) $i3++;
        if ($next === $next5) $i5++;
    }
    return $ugly[$n - 1];
}
```

### 3. Greatest Common Divisor of Strings
```php
public function gcdOfStrings(string $str1, string $str2): string
{
    if ($str1 . $str2 !== $str2 . $str1) return "";
    $len = $this->gcd(strlen($str1), strlen($str2));
    return substr($str1, 0, $len);
}
```

### 4. Excel Column Title (base 26)
```php
public function convertToTitle(int $columnNumber): string
{
    $result = '';
    while ($columnNumber > 0) {
        $columnNumber--;
        $result = chr(ord('A') + $columnNumber % 26) . $result;
        $columnNumber = intval($columnNumber / 26);
    }
    return $result;
}
// 1 -> A, 26 -> Z, 27 -> AA
```

### 5. Perfect Squares (Lagrange's four-square theorem + DP)
```php
public function numSquares(int $n): int
{
    $dp = array_fill(0, $n + 1, PHP_INT_MAX);
    $dp[0] = 0;
    for ($i = 1; $i <= $n; $i++) {
        for ($j = 1; $j * $j <= $i; $j++) {
            $dp[$i] = min($dp[$i], $dp[$i - $j * $j] + 1);
        }
    }
    return $dp[$n];
}
```

### 6. Count Primes in Range
```php
public function primesInRange(int $l, int $r): int
{
    $isPrime = array_fill(0, $r + 1, true);
    $isPrime[0] = $isPrime[1] = false;
    for ($i = 2; $i * $i <= $r; $i++) {
        if ($isPrime[$i]) {
            for ($j = $i * $i; $j <= $r; $j += $i) {
                $isPrime[$j] = false;
            }
        }
    }
    $count = 0;
    for ($i = $l; $i <= $r; $i++) if ($isPrime[$i]) $count++;
    return $count;
}
```

## Interview Sualları

1. **Euclidean algorithm niye işləyir?**
   - `gcd(a, b) = gcd(b, a mod b)`. Əgər d a və b-ni bölürsə, d (a mod b)-ni də bölür. Ona görə GCD saxlanır.

2. **Sieve of Eratosthenes niye O(n log log n)-dir?**
   - Harmonic seriya analizi: sum(1/p) prime-lar üçün təxminən log log n-ə yaxınlaşır.

3. **Modular inverse niye Fermat's little theorem ile hesablanır?**
   - Əgər p prime və gcd(a, p) = 1, onda `a^(p-1) ≡ 1 (mod p)`, bu da `a^(p-2)` inverse olur.

4. **Fast exponentiation-ın əsasında nə durur?**
   - Divide and conquer: `x^n = (x^(n/2))^2` (n even), `x^n = x * x^(n-1)` (n odd).

5. **Binomial coefficient niye int-də overflow verir?**
   - n böyük olanda `n!` həddindən artıq böyüyür. Bunun üçün iterative method ilə bölmə paralel aparılmalıdır.

6. **Pow(x, n) üçün n negative olanda?**
   - `x^(-n) = 1 / x^n`. Lakin PHP-də `n = PHP_INT_MIN` olarsa, `-n` overflow verə bilər.

7. **GCD of strings-in lemma-sı?**
   - Əgər iki string-in gcd string-i var, onda `str1 + str2 == str2 + str1`.

8. **Fibonacci-ni O(log n)-də necə hesablamaq olar?**
   - Matrix exponentiation ilə: `[[F(n+1), F(n)], [F(n), F(n-1)]] = [[1,1],[1,0]]^n`.

9. **Floyd cycle detection Happy Number-də necə işləyir?**
   - Slow/fast pointer ile cycle detect edilir, amma HashSet sadə və kifayətdir.

## PHP/Laravel ilə Əlaqə

### GMP extension (böyük ədədlər):
```php
// PHP-də böyük ededler üçün GMP istifadə edilir
$big1 = gmp_init("123456789012345678901234567890");
$big2 = gmp_init("987654321098765432109876543210");

$sum = gmp_add($big1, $big2);
$gcd = gmp_gcd($big1, $big2);
$prime = gmp_prob_prime(12345);
$modPow = gmp_powm($big1, 100, $big2);
```

### BC Math (arbitrary precision):
```php
// Hesab emelliyatları
$a = bcmul("12345678901234", "98765432109876", 0);
$b = bcmod($a, "1000000007", 0);
$c = bcpow("2", "100", 0);

// Laravel-də karmaşık finance hesablamaları
$price = bcmul($unitPrice, $quantity, 4);
$tax = bcmul($price, "0.18", 4);
$total = bcadd($price, $tax, 2);
```

### Cryptography (Laravel Hash):
```php
// RSA, Diffie-Hellman-da modular exponentiation əsasdır
// Laravel password hashing: Bcrypt, Argon2 internal olaraq modular math istifade edir

Hash::make('password'); // Bcrypt
```

### Pagination hesabi (combinatorics):
```php
// Total pages hesabı
$totalItems = Product::count();
$perPage = 20;
$totalPages = intval(ceil($totalItems / $perPage));

// "Seyrek" pagination display (1 ... 5 6 7 ... 100)
function paginationLinks(int $current, int $total): array
{
    // Combinatorial logic
}
```

### Random number generation:
```php
// Cryptographically secure random
$token = bin2hex(random_bytes(16));

// Weighted random (probabilities)
function weightedRandom(array $weights): int
{
    $total = array_sum($weights);
    $rand = mt_rand(1, $total);
    $sum = 0;
    foreach ($weights as $i => $w) {
        $sum += $w;
        if ($rand <= $sum) return $i;
    }
    return count($weights) - 1;
}
```

### Real nümunələr:
1. **Interest calculation** - compound interest formulası
2. **Product recommendations** - cosine similarity (combinatorics)
3. **Lottery/raffle systems** - probability, combinations
4. **Cryptocurrency** - modular arithmetic, prime numbers
5. **Scheduling** - LCM ile periodic tasks (CRON)
6. **A/B testing** - statistical significance calculations
7. **Game development** - probability tables, damage calculations
