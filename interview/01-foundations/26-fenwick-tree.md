# Fenwick Tree / Binary Indexed Tree (Lead ⭐⭐⭐⭐)

## İcmal
Fenwick Tree (Binary Indexed Tree, BIT) — prefix sum-ları effektiv saxlayan və yeniləyən data structure-dur. Segment tree-nin xüsusi, sadələşdirilmiş variantı kimi düşünmək olar: yalnız prefix sum (ya da prefix xor, min/max bəzi hallarda) üçün optimize olunub. O(log n) update + O(log n) prefix query, O(n) space. Kod həcmi segment tree-dən çox azdır — competitive programming-in "get it done fast" alətidir.

## Niyə Vacibdir
BIT-in elegantlığı özünəməxsusdur: ədədin binary representation-ına əsaslanaraq ağac qurmaq ideyası olduqca creative-dir. Bu struktur count inversions, 2D range sum, dynamic rank problems kimi tipik Lead interview suallarını həll edir. Competitive programmers BIT-i sürətlə yaza bilirlər — interview-da bu sürəti göstərmək faydalıdır. Real sistemlərdə: leaderboard, inventory tracking, streaming data aggregation.

## Əsas Anlayışlar

### Əsas İdea (Bit Manipulation)
`i & (-i)` — i-nin ən aşağı set bit-i (lowest significant bit, LSB):
- `6 = 110` → `6 & (-6) = 010 = 2` → 2 element "məsuliyyəti"
- `8 = 1000` → `8 & (-8) = 1000 = 8` → 8 element məsuliyyəti
- Hər BIT node-u bir range-i reprezentasiya edir, bu range-in uzunluğu LSB-dir

**BIT Array (1-indexed)**:
- `tree[i]` = `[i - LSB(i) + 1, i]` aralığının cəmi
- Yəni `tree[6]` = `[5, 6]`-nın cəmi (LSB(6)=2)
- `tree[8]` = `[1, 8]`-in cəmi (LSB(8)=8)

### Prefix Sum Query
`query(i)` — `[1, i]`-nin cəmini qaytar:
```
sum = 0
while i > 0:
    sum += tree[i]
    i -= i & (-i)   ← i-nin LSB-sini çıxart (parent-ə get)
return sum
```
`i = 7 = 111`:
- `tree[7]` əlavə et (range [7,7])
- `i = 7 - 1 = 6 = 110`
- `tree[6]` əlavə et (range [5,6])
- `i = 6 - 2 = 4 = 100`
- `tree[4]` əlavə et (range [1,4])
- `i = 4 - 4 = 0` → bitdi
Cəm = prefix sum [1,7]

### Point Update
`update(i, delta)` — `arr[i]` dəyərinə `delta` əlavə et:
```
while i <= n:
    tree[i] += delta
    i += i & (-i)   ← parent-ə get (LSB əlavə et)
```

### Range Sum Query
`range_sum(l, r) = query(r) - query(l - 1)`

### Complexity
| Əməliyyat | BIT | Naive Array |
|-----------|-----|-------------|
| Build | O(n log n) | O(n) |
| Point update | O(log n) | O(1) |
| Prefix sum | O(log n) | O(n) |
| Range sum | O(log n) | O(n) |
| Space | O(n) | O(n) |

### BIT vs Segment Tree
| | BIT | Segment Tree |
|--|-----|-------------|
| Implementation | ~10 sətir | ~50 sətir |
| Flexibility | Az (sum/xor) | Yüksək |
| Range update | Çətin (iki BIT) | Lazy ilə asan |
| Range min/max | Çox çətin | Asan |
| Memory | n | 4n |
| Cache performance | Daha yaxşı | Az yaxşı |

**Qayda**: Prefix sum + point update → BIT. Min/max/range update → Segment Tree.

### BIT ilə Range Update + Point Query
- `update(l, r, val)` — bütün `[l, r]`-ə val əlavə et
- `point_query(i)` — `arr[i]`-nin cari dəyərini qaytarmaq
- Difference array ilə: `bit_update(l, val); bit_update(r+1, -val)`
- `point_query(i) = prefix_sum(i)` (difference prefix sum = original dəyər)

### BIT ilə Range Update + Range Query
- İki BIT: `B1`, `B2`
- Range update `[l, r, v]`:
  - `B1`: `update(l, v)`, `update(r+1, -v)`
  - `B2`: `update(l, v*(l-1))`, `update(r+1, -v*r)`
- Prefix sum `[1, i]` = `B1.query(i) * i - B2.query(i)`

### Order Statistics / Rank Problems
- Sorted array-i BIT ilə simulate et
- `rank(x)` = `x`-dən kiçik element sayı = `query(x-1)`
- `k-th smallest` = binary search üzərindəki BIT query
- Coordinate compression ilə birlikdə istifadə olunur

### Count Inversions (BIT ilə O(n log n))
- Sağdan sola get, hər element üçün: sol tərəfdə neçə element daha böyük?
- BIT-i sayac kimi istifadə et: `inversions += query(n) - query(arr[i])`
- Sonra `update(arr[i], 1)`

### 2D BIT
- 2D prefix sum: `tree[i][j]` = `(1,1)` dan `(i,j)`-yə qədər cəm
- Update: `update(x, y, delta)` → W-loop ilə `i, j` yenilə
- Query: `query(x, y)` → iki dəfə nested loop
- Complexity: O(log²n) update, O(log²n) query

## Praktik Baxış

### Interview Yanaşması
1. "Prefix sum + update" → BIT (Segment Tree-dən daha sürətli implement et)
2. "Count inversions" → BIT + coordinate compression
3. "K-th smallest in dynamic array" → BIT + binary search
4. BIT-in `i & (-i)` trick-ini izah et — interviewer-lar bunu bilmek istəyir

### Nədən Başlamaq
- 1-indexed array istifadə et
- `query` (LSB çıxar) + `update` (LSB əlavə et) — iki metod
- Range sum: `query(r) - query(l-1)`
- Build: n dəfə `update(i, arr[i])` çağır — O(n log n)

### Ümumi Follow-up Suallar
- "`i & (-i)` niyə LSB-ni verir? Binary ilə izah edin"
- "BIT-i 0-indexed etmək olarmı? Niyə 1-indexed üstünlüklüdür?"
- "2D BIT-i implement edin"
- "Count inversions üçün BIT necə istifadə edilir?"

### Namizədlərin Ümumi Səhvləri
- 0-indexed BIT-i qarışdırmaq (1-indexed daha asan)
- `update`-də `i <= n` şərtini unutmaq
- `query`-də `i > 0` şərtini unutmaq
- Build-i O(n) yerinə O(n log n) etmək (interview-da hər ikisi qəbulolunan)

### Yaxşı → Əla Cavab
- Yaxşı: BIT-i implement edir, `i & (-i)` trick-ini bilir
- Əla: 2D BIT-i bilir, count inversions-ı həll edir, segment tree ilə fərqi aydın izah edir, range update + range query üçün iki BIT trick-ini bilir

## Nümunələr

### Tipik Interview Sualı
"LeetCode 315 — Count of Smaller Numbers After Self: hər `i` üçün, `i`-dən sonra gələn özündən kiçik elementlərin sayını tapın."

### Güclü Cavab
BIT + coordinate compression ilə O(n log n) həll edərdim.

Əvvəlcə dəyərləri [1..k] aralığına compress edirəm (k = unique dəyər sayı). Sonra sağdan sola keçirəm: hər element üçün BIT-dən `query(val-1)` — bu, şimdiye qədər əlavə olunmuş elementlərin neçəsinin val-dən kiçik olduğunu verir. Sonra `update(val, 1)` — bu elementi BIT-ə əlavə edirəm.

Bu yanaşma BIT-i "sıralanmış sayac" kimi istifadə edir. Coordinate compression olmadan BIT-in ölçüsü 10^5 ola bilər — bu da qəbulolunan.

Complexity: O(n log n) — hər element üçün O(log n) query + update.

### Kod Nümunəsi
```python
class BIT:
    def __init__(self, n):
        self.n = n
        self.tree = [0] * (n + 1)  # 1-indexed

    def update(self, i, delta=1):
        while i <= self.n:
            self.tree[i] += delta
            i += i & (-i)  # LSB əlavə et → parent

    def query(self, i):
        s = 0
        while i > 0:
            s += self.tree[i]
            i -= i & (-i)  # LSB çıxart → parent
        return s

    def range_query(self, l, r):
        return self.query(r) - self.query(l - 1)

# Count of Smaller Numbers After Self — O(n log n)
def count_smaller(nums):
    # Coordinate compression
    sorted_unique = sorted(set(nums))
    rank = {v: i+1 for i, v in enumerate(sorted_unique)}

    bit = BIT(len(sorted_unique))
    result = []

    for num in reversed(nums):
        r = rank[num]
        result.append(bit.query(r - 1))  # özündən kiçik sayı
        bit.update(r)                    # özünü əlavə et

    return result[::-1]

# Count Inversions — O(n log n)
def count_inversions(arr):
    n = len(arr)
    sorted_arr = sorted(set(arr))
    rank = {v: i+1 for i, v in enumerate(sorted_arr)}

    bit = BIT(len(sorted_arr))
    inversions = 0

    for i in range(n - 1, -1, -1):
        r = rank[arr[i]]
        inversions += bit.query(r - 1)  # sağda, özündən kiçik element sayı = inversion
        bit.update(r)

    return inversions

# 2D BIT — Range Sum Query
class BIT2D:
    def __init__(self, rows, cols):
        self.rows = rows
        self.cols = cols
        self.tree = [[0] * (cols + 1) for _ in range(rows + 1)]

    def update(self, r, c, delta):
        i = r
        while i <= self.rows:
            j = c
            while j <= self.cols:
                self.tree[i][j] += delta
                j += j & (-j)
            i += i & (-i)

    def query(self, r, c):
        s = 0
        i = r
        while i > 0:
            j = c
            while j > 0:
                s += self.tree[i][j]
                j -= j & (-j)
            i -= i & (-i)
        return s

    def range_query(self, r1, c1, r2, c2):
        return (self.query(r2, c2)
                - self.query(r1-1, c2)
                - self.query(r2, c1-1)
                + self.query(r1-1, c1-1))

# K-th Smallest in Dynamic Array — O(log²n)
def kth_smallest(bit, k, max_val):
    """BIT-də k-inci ən kiçik elementi tap"""
    lo, hi = 1, max_val
    while lo < hi:
        mid = (lo + hi) // 2
        if bit.query(mid) >= k:
            hi = mid
        else:
            lo = mid + 1
    return lo
```

## Praktik Tapşırıqlar
- LeetCode 307 — Range Sum Query - Mutable (BIT ilə, Seg Tree ilə müqayisə)
- LeetCode 315 — Count of Smaller Numbers After Self
- LeetCode 493 — Reverse Pairs
- LeetCode 327 — Count of Range Sum
- LeetCode 1649 — Create Sorted Array through Instructions
- **Özünü yoxla**: `[2, 3, 1, 4, 5]` üçün count inversions BIT-lə həll et; hər addımı izlə
- **2D tapşırığı**: Matrix-də `(r1,c1)` dan `(r2,c2)` aralığında cəm, update O(log²n) olsun

## Əlaqəli Mövzular
- **Segment Tree** — daha çevik, range min/max üçün zəruri; trade-off-ları bil
- **Prefix Sum Array** — statik array üçün O(1) query; update sonra O(n) rebuild lazımdır
- **Sorting / Merge Sort** — count inversions üçün alternativ O(n log n) yanaşma
- **Balanced BST** — dynamic order statistics üçün alternativ
- **Coordinate Compression** — BIT-in praktik tətbiqləri həmişə bu ilə birlikdə gəlir
