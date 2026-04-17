# Meet in the Middle (Ortada Qarşılaşma)

## Konsept (Concept)

**Meet in the Middle (MITM)** — brute force O(2^n)-in çox yavaş olduğu halda, **problemi iki yarıya böl və hər yarını ayrıca hesabla**, sonra onları birləşdir. Bu yolla O(2^n) → O(2^(n/2) · n) olur.

### Klassik nümunə
- **n = 40** üçün 2⁴⁰ ≈ 10¹² — çox yavaşdır (saatlarla işləyir)
- **MITM ilə**: 2²⁰ ≈ 10⁶ — millisaniyələr

### Prinsip
1. Giriş verilənini iki bərabər yarıya böl: A (n/2 element) və B (n/2 element)
2. A-nın bütün alt-çoxluqlarını hesabla (2^(n/2))
3. B-nin bütün alt-çoxluqlarını hesabla (2^(n/2))
4. Bu iki massivi sort et və ya hash-lə
5. Hər A-dakı S_a üçün B-də S_b tap ki, `S_a + S_b = target`

## Necə İşləyir?

### Addımlar
```
1. Split: arr → arr[0..n/2-1] və arr[n/2..n-1]
2. Enumerate all subsets of left half → list L
3. Enumerate all subsets of right half → list R
4. Sort R (və ya hash-lə)
5. For each x in L:
     find (target - x) in R using binary search / hash
```

### Niyə işləyir?
`2^(n/2) + 2^(n/2) << 2^n`. Birləşdirmə mərhələsi sort + binary search ilə O(2^(n/2) · n/2)-dir.

### Əsas MITM tətbiqləri
- **Subset Sum** (n ≤ 40)
- **4-Sum** (array-də dörd ədədin cəmi = target)
- **Knapsack with n ≤ 40**
- **String path counting**
- **Shortest path in implicit graph** (bidirectional BFS)

## İmplementasiya (Implementation) - PHP

### 1. Subset Sum (n ≤ 40)

```php
// Array-dəki hansısa alt-çoxluğun cəmi hədəfə bərabərmi?
function subsetSumMITM(array $arr, int $target): bool {
    $n = count($arr);
    $half = intdiv($n, 2);

    $leftSums = [];
    for ($mask = 0; $mask < (1 << $half); $mask++) {
        $sum = 0;
        for ($i = 0; $i < $half; $i++) {
            if ($mask & (1 << $i)) $sum += $arr[$i];
        }
        $leftSums[] = $sum;
    }

    $rightSums = [];
    $rightSize = $n - $half;
    for ($mask = 0; $mask < (1 << $rightSize); $mask++) {
        $sum = 0;
        for ($i = 0; $i < $rightSize; $i++) {
            if ($mask & (1 << $i)) $sum += $arr[$half + $i];
        }
        $rightSums[] = $sum;
    }

    sort($rightSums);
    foreach ($leftSums as $ls) {
        $need = $target - $ls;
        if (binarySearchExists($rightSums, $need)) return true;
    }
    return false;
}

function binarySearchExists(array $arr, int $val): bool {
    $l = 0;
    $r = count($arr) - 1;
    while ($l <= $r) {
        $m = intdiv($l + $r, 2);
        if ($arr[$m] === $val) return true;
        if ($arr[$m] < $val) $l = $m + 1;
        else $r = $m - 1;
    }
    return false;
}
```

### 2. Count Subsets With Sum ≤ K

```php
function countSubsetsAtMost(array $arr, int $K): int {
    $n = count($arr);
    $half = intdiv($n, 2);

    $L = enumerateSums(array_slice($arr, 0, $half));
    $R = enumerateSums(array_slice($arr, $half));
    sort($R);

    $count = 0;
    foreach ($L as $ls) {
        $remain = $K - $ls;
        if ($remain < 0) continue;
        // Count elements in R with value <= $remain
        $lo = 0; $hi = count($R);
        while ($lo < $hi) {
            $m = intdiv($lo + $hi, 2);
            if ($R[$m] <= $remain) $lo = $m + 1;
            else $hi = $m;
        }
        $count += $lo;
    }
    return $count;
}

function enumerateSums(array $arr): array {
    $n = count($arr);
    $sums = [];
    for ($mask = 0; $mask < (1 << $n); $mask++) {
        $s = 0;
        for ($i = 0; $i < $n; $i++) if ($mask & (1 << $i)) $s += $arr[$i];
        $sums[] = $s;
    }
    return $sums;
}
```

### 3. 4-Sum using MITM

```php
// A, B, C, D dörd massivdir. a+b+c+d = 0 olan kombinasiya sayını tap.
function fourSumCount(array $A, array $B, array $C, array $D): int {
    $map = [];
    foreach ($A as $a) {
        foreach ($B as $b) {
            $key = $a + $b;
            $map[$key] = ($map[$key] ?? 0) + 1;
        }
    }
    $count = 0;
    foreach ($C as $c) {
        foreach ($D as $d) {
            $need = -($c + $d);
            if (isset($map[$need])) $count += $map[$need];
        }
    }
    return $count;
}
// O((n²) time, O(n²) space — amma naive O(n⁴)-dən çox sürətli
```

### 4. Closest Subset Sum to Target

```php
function closestSubsetSum(array $arr, int $target): int {
    $n = count($arr);
    $half = intdiv($n, 2);
    $L = enumerateSums(array_slice($arr, 0, $half));
    $R = enumerateSums(array_slice($arr, $half));
    sort($R);

    $best = PHP_INT_MAX;
    foreach ($L as $ls) {
        $need = $target - $ls;
        // Binary search ən yaxını
        $lo = 0; $hi = count($R) - 1;
        while ($lo <= $hi) {
            $m = intdiv($lo + $hi, 2);
            $candidate = $ls + $R[$m];
            if (abs($candidate - $target) < abs($best - $target)) {
                $best = $candidate;
            }
            if ($R[$m] < $need) $lo = $m + 1;
            else $hi = $m - 1;
        }
    }
    return $best;
}
```

### 5. Knapsack n ≤ 40 (MITM)

```php
// weights[], values[], capacity W. Max value ≤ W.
function knapsackMITM(array $weights, array $values, int $W): int {
    $n = count($weights);
    $half = intdiv($n, 2);

    $leftItems = [];
    for ($mask = 0; $mask < (1 << $half); $mask++) {
        $w = $v = 0;
        for ($i = 0; $i < $half; $i++) {
            if ($mask & (1 << $i)) { $w += $weights[$i]; $v += $values[$i]; }
        }
        if ($w <= $W) $leftItems[] = [$w, $v];
    }

    $rightItems = [];
    $rSize = $n - $half;
    for ($mask = 0; $mask < (1 << $rSize); $mask++) {
        $w = $v = 0;
        for ($i = 0; $i < $rSize; $i++) {
            if ($mask & (1 << $i)) {
                $w += $weights[$half + $i];
                $v += $values[$half + $i];
            }
        }
        if ($w <= $W) $rightItems[] = [$w, $v];
    }

    // Right-i weight-ə görə sort et, sonra prefix max value saxla
    usort($rightItems, fn($a, $b) => $a[0] <=> $b[0]);
    $maxV = 0;
    $cleaned = [];
    foreach ($rightItems as [$w, $v]) {
        $maxV = max($maxV, $v);
        $cleaned[] = [$w, $maxV];
    }

    $best = 0;
    foreach ($leftItems as [$lw, $lv]) {
        $remain = $W - $lw;
        $lo = 0; $hi = count($cleaned) - 1;
        $found = -1;
        while ($lo <= $hi) {
            $m = intdiv($lo + $hi, 2);
            if ($cleaned[$m][0] <= $remain) { $found = $m; $lo = $m + 1; }
            else $hi = $m - 1;
        }
        if ($found !== -1) $best = max($best, $lv + $cleaned[$found][1]);
    }
    return $best;
}
```

### 6. Bidirectional BFS (konseptual MITM)

```php
// İki tərəfdən (start və end) eyni zamanda BFS — orta layda görüşmək
function bidirectionalBFS(array $graph, int $start, int $end): int {
    if ($start === $end) return 0;
    $forward = [$start => 0];
    $backward = [$end => 0];
    $fq = [$start];
    $bq = [$end];
    while (!empty($fq) && !empty($bq)) {
        // Forward one step
        $next = [];
        foreach ($fq as $u) {
            foreach ($graph[$u] ?? [] as $v) {
                if (isset($forward[$v])) continue;
                $forward[$v] = $forward[$u] + 1;
                if (isset($backward[$v])) return $forward[$v] + $backward[$v];
                $next[] = $v;
            }
        }
        $fq = $next;

        // Backward one step
        $next = [];
        foreach ($bq as $u) {
            foreach ($graph[$u] ?? [] as $v) {
                if (isset($backward[$v])) continue;
                $backward[$v] = $backward[$u] + 1;
                if (isset($forward[$v])) return $forward[$v] + $backward[$v];
                $next[] = $v;
            }
        }
        $bq = $next;
    }
    return -1;
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Əməliyyat | Time | Space |
|-----------|------|-------|
| Subset enumeration | O(2^(n/2)) | O(2^(n/2)) |
| Sort | O(2^(n/2) · log(2^(n/2))) = O(2^(n/2) · n) | — |
| Binary search loop | O(2^(n/2) · n) | — |
| **Ümumi MITM** | **O(2^(n/2) · n)** | **O(2^(n/2))** |

**Müqayisə**:
- n = 40 naive: 2⁴⁰ = 10¹² əməliyyat
- n = 40 MITM: 2²⁰ · 40 ≈ 4 · 10⁷ — 25000x sürətli!

## Tipik Məsələlər (Common Problems)

### 1. LeetCode 1755 — Closest Subsequence Sum
Yuxarıdakı `closestSubsetSum` funksiyasına bənzər. n ≤ 40 → MITM məcburidir.

### 2. LeetCode 454 — 4Sum II
Yuxarıdakı `fourSumCount` — 4 array, cəmi 0.

### 3. LeetCode 956 — Tallest Billboard (həm də bitmask DP ilə)
n ≤ 20 → bitmask DP kifayətdir. Amma n = 40 olsaydı MITM lazım olardı.

### 4. Subset with Maximum XOR
MITM ilə: hər yarı üçün bütün XOR-ları hesabla, sort et, sonra hər cütü yoxla.

### 5. Partition Array Into Two Equal Halves
`n ≤ 30, sum ≤ 10^18` → DP işləmir (sum çox böyük), MITM lazımdır.

## Interview Sualları

**1. MITM nə vaxt istifadə olunur?**
Brute force 2^n kimi olanda və n ~ 30-40 arasında olanda. Adətən DP işləməyəndə — məsələn, sum çox böyük (10^18) olanda.

**2. MITM-in əsas çətinliyi?**
İki yarıdan gələn nəticələri effektiv birləşdirmək. Hash, binary search, two pointers kimi strukturlar lazım olur. Yaddaş da kritik: 2²⁰ × 8 byte ≈ 8 MB saymaq olar.

**3. Niyə tam bərabər yarıya bölünməlidir?**
2^a + 2^b minimumdur a = b = n/2 olanda (konveksvlik). Qeyri-bərabər bölmə səmərəsizdir.

**4. MITM vs Dynamic Programming?**
- **DP** — values kiçik olanda yaxşıdır (pseudo-polynomial)
- **MITM** — n kiçik, amma values böyük olanda
- Bəzən hər ikisi işləyir — MITM yaddaş daha çox istəyir

**5. 4-Sum MITM necə işləyir?**
Naive O(n⁴) → MITM O(n²). İki cüt hesabla, hash-də saxla, sonra yoxla. Klassik MITM tətbiqi.

**6. Bidirectional BFS MITM-dir?**
Bəli, konseptual olaraq. Start və end-dən eyni anda BFS edirsən — dərinliyi yarıya endirir. Search tree branching factor b olanda O(b^d) → O(b^(d/2)).

**7. MITM-də hash collision problemi?**
Əgər hash-də eyni key-dən çox element olsa, siyahı kimi saxlamaq lazımdır (counting variant). PHP-də assosiativ array bunu avtomatik dəstəkləyir.

**8. MITM ilə subset sum — values mənfi ola bilərmi?**
Bəli. Amma brute force 2^n subset enumeration edir, dəyərin işarəsi problem deyil — sort + binary search hər halda işləyir.

**9. MITM ilə knapsack-də niyə "prefix max" lazımdır?**
`w1 < w2` olsa belə `v1 > v2` mümkündür. Həqiqətən istifadəli sıra: weight artdıqca value da artır (dominated elementləri at). Prefix max bu təmizləməni edir.

**10. Ən böyük MITM-də n nə qədər ola bilər?**
Praktikada n ≤ 45. n = 45 olanda 2²² · 45 ≈ 2 · 10⁸ — sərhəddə. n = 50 olanda 2²⁵ yaddaş (~250 MB) əksər rəqəmsal mühitlərdə overflow edir.

## PHP/Laravel ilə Əlaqə

- **Reporting**: "40 müştəri arasından cəmi müəyyən hədəfə yaxın olanları tap" — MITM kömək edə bilər.
- **Combinatorial pricing**: kiçik paket konfiqurasiyalarının optimal kombinasiyaları.
- **Feature selection**: ML pipeline-da 40 feature arasından ən yaxşı alt-çoxluğu tapmaq.
- **Caching**: MITM bir dəfə hesablanır, nəticələr Redis-də cache edilir.
- **PHP məhdudiyyətləri**: 2²⁰ element saxlayan array ≥ 100 MB yaddaş tutar (PHP-də hər element ~50 byte). `memory_limit` artırılmalı və ya struktur optimize edilməlidir.
- **Batch job**: Queue job ilə iki yarı paralel hesablansın, sonra master job cavabı birləşdirsin.
