# Bit Manipulation (Lead ⭐⭐⭐⭐)

## İcmal

Bit manipulation — ədədlərin binary representation-ı üzərindəki əməliyyatlardır: AND, OR, XOR, NOT, left/right shift. Bu əməliyyatlar hardware səviyyəsindədir — O(1) və çox sürətlidir. Integer-ın bitlərini bayraq kimi istifadə etmək (bitmask), XOR-un özü-özünü ləğv etmə xüsusiyyəti, power of 2 yoxlaması kimi klassik trick-lər interview-da tez-tez çıxır. Bu mövzu sizi average namizəddən fərqləndirir, çünki çox az developer bit-level düşünür. Həm əsas trick-lər, həm bitmask DP həm də real production tətbiqləri bilinməlidir.

## Niyə Vacibdir

Sistem proqramlaşdırması, OS kernel-i, network protocols, cryptography, hardware driver-lar bit manipulation olmadan mümkün deyil. Praktik nümunələr: IP address masking, Linux chmod permission flags (rwxrwxrwx = 9 bit), Redis-in compact data structures (Bitmap), image processing (pixel manipulation), Bloom filter. FAANG interview-larında bit manipulation sualları namizədin "low-level thinking" bacarığını yoxlayır. Lead üçün: Bitmask DP, XOR-based hash, two's complement detalları bilinməlidir.

## Əsas Anlayışlar

### Bitwise Operatorlar

| Operator | Simvol | Python | Nümunə (5=101, 3=011) |
|----------|--------|--------|---------|
| AND | & | & | `5 & 3 = 1` (101 & 011 = 001) |
| OR | \| | \| | `5 \| 3 = 7` (101 \| 011 = 111) |
| XOR | ^ | ^ | `5 ^ 3 = 6` (101 ^ 011 = 110) |
| NOT | ~ | ~ | `~5 = -6` (two's complement) |
| Left Shift | << | << | `5 << 1 = 10` (1010) |
| Arithmetic Right Shift | >> | >> | `5 >> 1 = 2` (10) |

**Operator Precedence**: `+` `-` `*` `/` BITWISE OPERATORS-DAN önəmlidir! Həmişə mötərizə qoy: `(a & b) + c` deyil `a & b + c`.

### XOR-un Xüsusi Xassələri

- `a ^ a = 0` — özü ilə XOR = sıfır.
- `a ^ 0 = a` — sıfır ilə XOR = özü.
- `a ^ b ^ a = b` — iki dəfə XOR-la ləğv olur (commutative + associative).
- XOR associative: `(a^b)^c = a^(b^c)`.
- XOR commutative: `a^b = b^a`.
- **Tətbiq**: Bütün cütlər ləğv olur, tək element qalır → "single number" problemi.
- **Missing number**: `0^1^...^n ^ a[0]^...^a[n-1]` = itən ədəd.

### Two's Complement

- Mənfi ədədlər: Bütün bitləri tərsinə çevir, 1 əlavə et.
- `-n = ~n + 1`.
- `~5 = -6` (Python-da ∞-bit olduğundan; 32-bit-də `~5 = 0xFFFFFFFA`).
- `-1` binary-də hamısı 1: `11111...1111`.
- **`n & (-n)`** = n-nin ən sağdakı set bit-i (LSB isolation).
  - Niyə: `-n = ~n + 1`. `n & (~n + 1)`. 1 əlavəsi carry propagate edəndə yalnız en sağ bit active qalır.
- **`n & (n-1)`** = n-nin ən sağdakı set bit-ini sil.
  - Nümunə: `12 = 1100`. `12 & 11 = 1100 & 1011 = 1000`.

### Klassik Bit Tricks

**Bit Yoxlamaları**:
```python
n & 1          # n tək-mi? (LSB yoxla)
(n >> k) & 1   # k-cı bit set-mi? (0-indexed)
n & (1 << k)   # k-cı bit non-zero-mi?
```

**Bit Dəyişdirmə**:
```python
n | (1 << k)    # k-cı biti set et (1-ə çevir)
n & ~(1 << k)   # k-cı biti clear et (0-a çevir)
n ^ (1 << k)    # k-cı biti flip et (toggle)
```

**Sürətli Hesablamalar**:
```python
n << 1    # n * 2
n >> 1    # n // 2 (integer division)
n << k    # n * 2^k
n >> k    # n // 2^k
```

**Mühüm Trick-lər**:
```python
n & (n - 1) == 0  # n, 2-nin qüvvəti mi? (n > 0 lazımdır)
n & (-n)          # ən sağdakı set bit (LSB) — BIT/Fenwick Tree-də istifadə
n & (n - 1)       # ən sağdakı set bit-i sil
~n + 1 == -n      # two's complement check
x ^ y == 0        # x == y (bəzən equality check üçün)
```

**Bit Count (Hamming Weight / popcount)**:
```python
bin(n).count('1')           # Python-da ən sadə
n.bit_count()               # Python 3.10+
# Brian Kernighan alqoritmi — O(set bit sayı):
count = 0
while n:
    n &= n - 1    # ən sağ set bit-i sil
    count += 1
```

### Bitmask DP

Bitmask DP — üst-üstə düşən subsets üzərindəki DP. State: Hansı elementlərin seçildiyi bir bitmask ilə göstərilir.
- State sayı: `2^n` (n element).
- `n ≤ 20` üçün işlər (2^20 = ~1M state).
- Tipik problem: TSP, Assignment Problem, Hamiltonian Path.

**TSP (Traveling Salesman) — Bitmask DP**:
- `dp[mask][i]` = mask-dəki şəhərləri ziyarət edib, i-dən bitirən minimum məsafə.
- Transition: `dp[mask | (1 << j)][j] = min(dp[mask][i] + dist[i][j])` for j not in mask.
- Base: `dp[1][0] = 0` (yalnız 0-dan başlayan).
- Answer: `min(dp[(1<<n)-1][i] + dist[i][0])`.
- O(2^n * n²) time, O(2^n * n) space.

### XOR-based Hash / Fingerprint

- Sıra əhəmiyyətsiz olan collection-ların "fingerprint"-ini XOR ilə hesabla.
- `hash = a[0] ^ a[1] ^ ... ^ a[n-1]`
- İki set eyni elementlərə malikdirsə (sırasız) hash-ləri eynidir.
- Rolling XOR: Element əlavə/sil → O(1) hash update.
- Tətbiq: Duplicate packet detection, data integrity check.

### Gray Code

- Ardıcıl iki rəqəm yalnız bir bit fərqlənir.
- `gray(n) = n ^ (n >> 1)`
- `gray_to_binary(g)`: `b = g`, `g >>= 1` while `g`: `b ^= g`, `g >>= 1`. Return `b`.
- Rotary encoder-larda, error correction-da istifadə.

### Bitset Optimization

- Boolean array-i `n/64` integer-ə sıxış.
- Set operations (AND, OR, XOR) 64x daha sürətli.
- Graph algorithms üçün bitset-based optimallaşma.
- C++ `std::bitset`, Java `BitSet`.

### Bit Manipulation ilə Ümumi Problemlər

- **Single Number**: XOR hamısı → cütlər ləğv olur.
- **Missing Number**: `0^1^...^n ^ array XOR`.
- **Power of 2**: `n > 0 and n & (n-1) == 0`.
- **Sum without +**: `carry = (a&b)<<1`, `sum = a^b`, loop.
- **Reverse Bits**: 32-bit üçün bit-by-bit yerdəyişmə.
- **Number of 1 Bits**: Brian Kernighan.
- **Subset generation**: `for mask in range(1 << n):`.
- **Check subset**: `(sub & mask) == sub` — sub, mask-ın subset-i mi?
- **Iterate subsets of mask**: `sub = mask; while sub: process(sub); sub = (sub-1) & mask`.

## Praktik Baxış

### Interview Yanaşması

1. "Single number", "unique element", "xor" açar sözlər → XOR trick.
2. "Subsets", "all combinations" n≤20 → bitmask.
3. "Power of 2" → `n & (n-1) == 0`.
4. "Count set bits" → Brian Kernighan (`n & (n-1)` loop).
5. Hər zaman binary representation-ı kağızda göstər — interviewer görür ki düşünürsün.
6. Python-da `~n = -(n+1)` olduğunu yadda saxla.

### Nədən Başlamaq

- Problemi binary olaraq düşün.
- Klassik trick-lərin hansı işlədiyini müəyyən et.
- Bitwise operator seçimi: AND (iki şərt birgə), OR (birləşdirmə), XOR (fərq/ləğv etmə).
- Nümunəni binary şəklində trace et: `5 & 3 = 101 & 011 = 001 = 1`.

### Follow-up Suallar (İnterviewerlər soruşur)

- "`n & (n-1)` niyə ən sağdakı set bit-i silir? Binary nümunə göstər."
- "`n & (-n)` niyə LSB-i izole edir? Two's complement ilə izah et."
- "İki ədədi `+` olmadan toplamaq mümkündürmü?" — carry XOR trick.
- "Bitmask DP-ni nə zaman istifadə edərsiniz? n limiti nədir?"
- "32-bit vs 64-bit integer-də overflow necə baş verir?"
- "Bloom filter bit manipulation istifadə edir? Necə?"

### Common Mistakes

- Python-da `~n = -(n+1)` olduğunu unutmaq — 32-bit kimi davranmır.
- `1 << k` əvəzinə `2 ** k` yazmaq — funksional eyni, amma bit düşüncəsini göstərmir.
- Bitmask-da `n ≤ 20` limitini aşmaq — 2^25 = 33M state, memory/time explodes.
- `a & b + c` — operator precedence: `+` `&`-dən önəmlidir! `(a & b) + c` lazımdır.
- Python-da signed/unsigned fərqini bilməmək — Python integer arbitrary precision.
- Bit count-u counting bit-lər ilə həll edib Brian Kernighan-ı bilməmək.

### Yaxşı → Əla Cavab

- **Yaxşı**: XOR trick-ini, power of 2, bit count-u bilir.
- **Əla**: Bitmask DP-ni implement edir, two's complement-i izah edir, `n & (-n)` niyə LSB-dir sübut edir, Gray code-u bilir, hardware-level kontekstdən (Linux permission, Redis bitmap) nümunə verir, operator precedence pitfall-ını qeyd edir.

### Real Production Ssenariləri

- Linux permission: `chmod 755 = rwxr-xr-x = 111 101 101 = 0755`. `mode & 0x1FF`.
- Redis SETBIT/GETBIT: Milyardlarla flag O(1) memory. Active user tracking.
- Bloom filter: Bir element-i K hash function ilə K bit set et. O(1) lookup.
- Network: IP subnet masking `192.168.1.0/24 = & 255.255.255.0`.
- Cryptography: XOR cipher, AES S-box, SHA bitwise operations.
- Database: PostgreSQL `NULL` bitmap, row visibility bitmask.

## Nümunələr

### Tipik Interview Sualı

"LeetCode 260 — Single Number III: Array-dəki iki tək element hariç hamısı iki dəfə görünür. O(n) time, O(1) space ilə hər iki tək elementi tapın."

### Güclü Cavab

Bir tək element olsaydı — bütün XOR cavab olardı. İki tək element üçün daha çətin, çünki XOR-da birləşirlər.

**Strategiya**:
1. Bütün XOR-u hesabla: `xor = a ^ b` (a, b iki tək element).
2. a ≠ b olduğundan, xor ≠ 0. Ən azı bir bit fərqlənir.
3. `diff_bit = xor & (-xor)` — iki ədədin fərqləndiyi ən sağ bit.
4. Array-i iki qrupa böl: `diff_bit` set olan elementlər vs olmayan.
5. `a` bir qrupdadır, `b` digərində (fərqlənən bit belə bölür).
6. Hər qrupda ayrı XOR et → iki cavab.

O(n) time, O(1) space, yalnız XOR istifadə edir.

### Kod Nümunəsi

```python
from typing import List

# Single Number III — Two unknowns
def single_number_iii(nums: List[int]) -> List[int]:
    xor = 0
    for n in nums:
        xor ^= n
    diff_bit = xor & (-xor)   # a ilə b-nin fərqləndiyi ən sağ bit
    a, b = 0, 0
    for n in nums:
        if n & diff_bit:
            a ^= n   # diff_bit set olan qrup
        else:
            b ^= n   # diff_bit clear olan qrup
    return [a, b]

# Power of 2 yoxlaması
def is_power_of_two(n: int) -> bool:
    return n > 0 and (n & (n - 1)) == 0
    # 8 = 1000, 7 = 0111, 8 & 7 = 0000 ✓
    # 6 = 0110, 5 = 0101, 6 & 5 = 0100 ≠ 0 ✗

# Bit count — Brian Kernighan — O(set bit sayı)
def count_bits_kernighan(n: int) -> int:
    count = 0
    while n:
        n &= n - 1    # ən sağ set bit-i sil
        count += 1
    return count

# Two integers sum without +/- — O(32)
def get_sum(a: int, b: int) -> int:
    mask = 0xFFFFFFFF    # 32-bit mask
    while b & mask:
        carry = (a & b) << 1
        a = a ^ b
        b = carry
    # Python-da overflow handling
    if b == 0:
        return a
    # b != 0: mənfi ədəd sign extension
    return a & mask if a <= 0x7FFFFFFF else ~(a ^ mask)

# Bitmask — Subset Sum / Partition
def can_partition_bitmask(nums: List[int]) -> bool:
    total = sum(nums)
    if total % 2 != 0:
        return False
    target = total // 2
    # dp integer: dp-nin k-cı biti = target k ilə əldə edilə bilərmi
    dp = 1   # dp[0] = True (0 əldə etmək mümkündür)
    for n in nums:
        dp |= dp << n   # hər num üçün mövcud sum-lara n əlavə et
    return bool(dp & (1 << target))

# All Subsets generation — bitmask
def all_subsets(nums: List[int]) -> List[List[int]]:
    n = len(nums)
    result = []
    for mask in range(1 << n):   # 0 ilə 2^n - 1 arası
        subset = [nums[i] for i in range(n) if mask & (1 << i)]
        result.append(subset)
    return result

# TSP — Bitmask DP — O(2^n * n²)
def tsp_min_cost(dist: List[List[int]]) -> int:
    n = len(dist)
    INF = float('inf')
    # dp[mask][i] = mask şəhərlərini ziyarət edib i-dən bitirmə min məsafə
    dp = [[INF] * n for _ in range(1 << n)]
    dp[1][0] = 0   # başlanğıc: yalnız şəhər 0, mask = 1 (bit 0 set)

    for mask in range(1 << n):
        for u in range(n):
            if dp[mask][u] == INF:
                continue
            if not (mask >> u & 1):
                continue   # u, mask-dəyilsə skip
            for v in range(n):
                if mask >> v & 1:
                    continue   # v artıq ziyarət edilib
                new_mask = mask | (1 << v)
                dp[new_mask][v] = min(dp[new_mask][v], dp[mask][u] + dist[u][v])

    # Bütün şəhərləri ziyarət edib 0-a qayıt
    full_mask = (1 << n) - 1
    return min(dp[full_mask][i] + dist[i][0]
               for i in range(1, n) if dp[full_mask][i] != INF)

# Reverse Bits (32-bit integer)
def reverse_bits(n: int) -> int:
    result = 0
    for _ in range(32):
        result = (result << 1) | (n & 1)
        n >>= 1
    return result

# Hamming Distance — XOR + popcount
def hamming_distance(x: int, y: int) -> int:
    return bin(x ^ y).count('1')
    # ya da: count_bits_kernighan(x ^ y)

# Maximum XOR of Two Numbers — Greedy + prefix trick
def find_maximum_xor(nums: List[int]) -> int:
    max_xor = 0
    mask = 0
    for i in range(31, -1, -1):
        mask |= (1 << i)
        prefixes = {num & mask for num in nums}
        temp = max_xor | (1 << i)
        # temp-i əldə etmək mümkündürmü? iki prefix-in XOR-u temp-i verirmi?
        if any(temp ^ prefix in prefixes for prefix in prefixes):
            max_xor = temp
    return max_xor

# Counting Bits — DP + bit trick — O(n)
def count_bits_dp(n: int) -> List[int]:
    dp = [0] * (n + 1)
    for i in range(1, n + 1):
        dp[i] = dp[i >> 1] + (i & 1)
        # i-nin bit sayı = (i//2)-nin bit sayı + LSB
    return dp

# Gray Code generation
def gray_code(n: int) -> List[int]:
    return [i ^ (i >> 1) for i in range(1 << n)]
    # 0→0, 1→1, 2→3, 3→2, 4→6, 5→7, 6→5, 7→4

# Iterate over all subsets of mask — O(2^popcount(mask))
def sum_of_subsets(mask: int, vals: List[int]) -> int:
    total = 0
    sub = mask
    while sub:
        subset_sum = sum(vals[i] for i in range(len(vals)) if sub & (1 << i))
        total += subset_sum
        sub = (sub - 1) & mask   # mask-ın növbəti kiçik subset-i
    return total
```

### İkinci Nümunə — Linux Permission System

```python
# Linux chmod flag-larını bit manipulation ilə idarə et
OWNER_READ    = 0o400   # 100 000 000
OWNER_WRITE   = 0o200   # 010 000 000
OWNER_EXEC    = 0o100   # 001 000 000
GROUP_READ    = 0o040   # 000 100 000
GROUP_WRITE   = 0o020   # 000 010 000
GROUP_EXEC    = 0o010   # 000 001 000
OTHERS_READ   = 0o004   # 000 000 100
OTHERS_WRITE  = 0o002   # 000 000 010
OTHERS_EXEC   = 0o001   # 000 000 001

def check_permission(mode: int, flag: int) -> bool:
    return bool(mode & flag)

def add_permission(mode: int, flag: int) -> int:
    return mode | flag

def remove_permission(mode: int, flag: int) -> int:
    return mode & ~flag

# chmod 755 = rwxr-xr-x
mode_755 = 0o755
print(check_permission(mode_755, OWNER_WRITE))   # True
print(check_permission(mode_755, OTHERS_WRITE))  # False
print(oct(add_permission(mode_755, OTHERS_WRITE)))  # 0o757
```

## Praktik Tapşırıqlar

1. LeetCode #136 — Single Number (XOR — bütün XOR = tək element).
2. LeetCode #260 — Single Number III (two unknowns XOR + diff bit split).
3. LeetCode #191 — Number of 1 Bits (Brian Kernighan alqoritmi).
4. LeetCode #338 — Counting Bits (DP + `i>>1` trick).
5. LeetCode #190 — Reverse Bits (32-bit).
6. LeetCode #371 — Sum of Two Integers (without +).
7. LeetCode #78 — Subsets (bitmask enumeration).
8. LeetCode #318 — Maximum Product of Word Lengths (bitmask).
9. Bitmask DP tapşırığı: LeetCode #847 — Shortest Path Visiting All Nodes.
10. Özünütəst: `n & (n-1)` işini 5 nümunə ilə binary-də trace et: `12, 8, 6, 4, 1`. Intuisiyanı formalaşdır.

## Əlaqəli Mövzular

- **Dynamic Programming** — Bitmask DP, subset DP birlikdə istifadə olunur. State = subset.
- **Fenwick Tree (BIT)** — `i & (-i)` LSB trick birbaşa bit manipulation-dır.
- **Hashing** — XOR-based hash, rolling hash bit ops istifadə edir.
- **Backtracking** — All subsets bitmask ilə də implement oluna bilər.
- **Graph Fundamentals** — TSP bitmask DP-si graph problemidir.
- **Math / Number Theory** — GCD (binary GCD alqoritmi), prime sieve bit tricks.
