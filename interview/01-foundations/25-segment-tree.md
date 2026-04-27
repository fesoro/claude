# Segment Tree (Lead ⭐⭐⭐⭐)

## İcmal

Segment Tree — array üzərindəki range query-lərini (məsələn, `[l, r]` aralığında minimum/maksimum/cəm) O(log n)-də cavablandıran, O(log n)-də hər hansı elementi yeniləyən ağac strukturudur. Array-in bölünməz elementlərindən (leaf) başlayaraq range-ləri birləşdirən tam binary tree-dir. Competitive programming-in əsas alətlərindən biridir; Lead-level interview-larda range query + mutable array problemi üçün bilinməlidir.

## Niyə Vacibdir

Segment tree-nin gücü odur ki, mutable array üzərindəki range query-lərini naive O(n)-dən O(log n)-ə endirir. Database-in aggregate query optimizer-ları, time-series database-lər (InfluxDB, Prometheus), game engine-lərinin collision detection sistemləri bu ideyadan istifadə edir. Lead interview-larında: "1 milyon sensor-dan real-time min/max range query necə effektiv cavablandırılır?" kimi suallar segment tree məntiqini tələb edir. Fenwick Tree (BIT) ilə müqayisəli düşüncə lazımdır.

## Əsas Anlayışlar

### Struktur

```
Array: [1, 3, 5, 7, 9, 11]
Segment Tree:
              [36]          ← cəm [0,5]
           /        \
        [9]          [27]   ← [0,2], [3,5]
       /   \        /    \
     [4]   [5]   [16]   [11]  ← [0,1],[2],[3,4],[5]
    /  \        /    \
  [1]  [3]   [7]    [9]    ← leaf-lər
```

- `n` elementli array üçün `4n` node-lu tree (worst case). Safe olaraq `4*n` istifadə et.
- 1-indexed: `node 1 = root`, `2i = left child`, `2i+1 = right child`.
- Leaf node-lar orijinal elementin dəyərini saxlayır.
- Interior node-lar: `combine(left_child, right_child)` — sum/min/max/gcd/...
- Height: `⌈log₂(n)⌉`, total nodes: `≤ 4n`.

### Build

```
build(node, start, end):
    if start == end:
        tree[node] = array[start]
    else:
        mid = (start + end) // 2
        build(2*node, start, mid)
        build(2*node+1, mid+1, end)
        tree[node] = combine(tree[2*node], tree[2*node+1])
```
Complexity: O(n) — hər node bir dəfə hesablanır.

### Query [l, r]

```
query(node, start, end, l, r):
    if [start,end] ∩ [l,r] = ∅ → neutral element qaytır (sum→0, min→∞)
    if [l,r] ⊇ [start,end]     → tree[node] qaytır (tam daxildir)
    else:                        → hər iki uşağı rekursiv soruş, birləşdir
```
Complexity: O(log n) — ən pis halda 4 * height node yoxlanır.

### Point Update

```
update(node, start, end, idx, val):
    if start == end:
        tree[node] = val; array[idx] = val
    else:
        mid = (start + end) // 2
        if idx <= mid: update(2*node, start, mid, idx, val)
        else:          update(2*node+1, mid+1, end, idx, val)
        tree[node] = combine(tree[2*node], tree[2*node+1])
```
Complexity: O(log n) — yalnız bir path güncəllənir.

### Lazy Propagation (Range Update)

**Problem**: Bütün `[l, r]` aralığını yeniləmək üçün hər elementi ayrıca update etmək O(n log n) olardı.

**Həll**: "Bu node-un bütün uşaqlarına tətbiq ediləcək pending update var" işarəsi saxla.
- Lazy dəyəri yalnız o uşaqlara ehtiyac duyulduqda push et (propagate).
- Range update + range query: hər ikisi O(log n).

**Lazy push mexanizmi**:
```
push_down(node, start, end):
    if lazy[node] != 0:
        mid = (start + end) // 2
        # Sol uşağa tətbiq et
        tree[2*node] += lazy[node] * (mid - start + 1)
        lazy[2*node] += lazy[node]
        # Sağ uşağa tətbiq et
        tree[2*node+1] += lazy[node] * (end - mid)
        lazy[2*node+1] += lazy[node]
        # Lazy-ni sıfırla
        lazy[node] = 0
```

### Segment Tree Növləri

- **Sum Segment Tree**: `combine = sum`, neutral = 0.
- **Min/Max Segment Tree**: `combine = min/max`, neutral = ∞ / -∞.
- **GCD Segment Tree**: `combine = gcd`, neutral = 0.
- **XOR Segment Tree**: `combine = xor`, neutral = 0.
- **Product Segment Tree**: `combine = multiply`, neutral = 1.
- **Count of Distinct**: Daha mürəkkəb — Merge Sort Tree ilə.
- **2D Segment Tree**: 2D range query, O(log²n) per operation.

### Segment Tree vs Fenwick Tree (BIT)

| | Segment Tree | Fenwick Tree (BIT) |
|--|-------------|---------------------|
| Range sum query | O(log n) | O(log n) |
| Range min/max | O(log n) ✓ | Çox çətin ✗ |
| Point update | O(log n) | O(log n) |
| Range update (lazy) | O(log n) ✓ | O(log n) (difference array) |
| Implementation | Mürəkkəb (~50 sətir) | Sadə (~10 sətir) |
| Memory | 4n | n+1 |
| Flexibility | Yüksək (hər operation) | Aşağı (sum/xor optimal) |

**Seçim qaydası**: Yalnız prefix sum + point update lazımdırsa → BIT. Digər range query-lər (min/max, arbitrary function) → Segment Tree.

### Coordinate Compression

Segment tree üçün çox vaxt coordinate compression lazımdır:
- Dəyərlər böyükdür (10^9), amma sıxlıq azdır (yalnız 10^3 fərqli dəyər).
- Sıxıştır: dəyərləri 0..k-1 aralığına map et.
- Segment tree k ölçülü olsun.

```python
vals = sorted(set(all_values))
compress = {v: i for i, v in enumerate(vals)}
```

### Merge Sort Tree

- Hər node-da sorted array saxlayır (merge sort kimi).
- "l-r aralığında x-dən kiçik element sayı?" → O(log²n) binary search.
- Space: O(n log n).
- "Count of inversions in range" kimi problemlər üçün.

### Dynamic Segment Tree (Pointer-based)

- Array ölçüsü əvvəlcədən bilinmir ya çox böyükdür (10^18).
- Node-ları lazım olduqda yaradır (lazy node creation).
- Space: O(q log n), q — update sayı.
- Competitive programming-da çox istifadə olunur.

### Persistent Segment Tree

- Hər update yeni version yaradır, köhnə version qalır (functional/immutable style).
- "k anında [l,r] aralığında neçə element ≤ x idi?" — offline query.
- Space: O((n + q) log n) — hər update yalnız O(log n) yeni node yaradır.
- Tətbiq: "Count of elements in range [l,r] that are ≤ x" — mergeable/persistent segment tree.

## Praktik Baxış

### Interview Yanaşması

1. "Range query + update" → segment tree (ya da BIT).
2. Yalnız sum + point update → BIT daha sadə (interview-da lazımsa seç).
3. Min/max/custom function → Segment Tree mütləq.
4. Range update + range query → Lazy Propagation.
5. Template-i əzbər bil: build, query, update — 3 funksiya.

### Nədən Başlamaq

- `tree = [0] * (4 * n)` — safe allocation.
- Index: root = 1, left = 2i, right = 2i+1.
- Neutral element: sum→0, min→∞, max→-∞.
- `build`, `update`, `query` sırasıyla implement et.
- Test: `[1, 3, 5]` üçün sum [0,1] = 4, sum [1,2] = 8, update [1]=7, sum [0,2] = 13.

### Follow-up Suallar (İnterviewerlər soruşur)

- "n = 10^6 olsa, segment tree-nin memory-si nə qədərdir?" — 4 * 10^6 * 8 bytes = 32MB.
- "Lazy propagation-ı min query-ə necə tətbiq edərdiniz?" — push_down min-ü saxlayır.
- "2D range query üçün segment tree genişləndirilə bilərmi?" — 2D segment tree, O(log²n).
- "Persistent segment tree nə zaman lazımdır?" — Version-based query.
- "Segment tree vs Fenwick tree — niyə BIT daha sürətli (constant factor)?"

### Common Mistakes

- `4n` əvəzinə `2n` array ölçüsü vermək — index out of bounds.
- Lazy `push_down`-u query-dən **əvvəl** etməyi unutmaq → yanlış nəticə.
- Range tam örtülmədikdə neutral element yerinə yanlış dəyər qaytarmaq.
- Build-in bottom-up yeniləməyi unutmaq.
- Neutral element-i düzgün seçməmək — min üçün 0 yox, ∞ lazımdır.
- Lazy propagation-da range ölçüsünü (end - start + 1) unudmaq.

### Yaxşı → Əla Cavab

- **Yaxşı**: Sum/min segment tree implement edir, complexity bilir.
- **Əla**: Lazy propagation-ı izah edir (niyə lazımdır, necə push_down işləyir), BIT ilə müqayisə aparır, persistent/2D variantlarını bilir, real sistem use case-lərini göstərir (time-series DB, game engine).

### Real Production Ssenariləri

- Time-series database (InfluxDB, Prometheus): Range aggregate query.
- Game engine collision detection: Interval tree (segment tree variant).
- Computational geometry: Rectangle union area (coordinate compression + seg tree).
- Database query optimizer: Range aggregate (SUM, MIN, MAX) over index ranges.
- Network monitoring: Sliding window aggregate over time ranges.

## Nümunələr

### Tipik Interview Sualı

"LeetCode 307 — Range Sum Query - Mutable: `[l, r]` aralığının cəmini soruşan `sumRange(l, r)` və elementi yeniləyən `update(i, val)` metodlarını implement edin."

### Güclü Cavab

Bu problemi Segment Tree ilə həll edərdim.

Naive array: `sumRange` O(n), `update` O(1).
Prefix sum array: `sumRange` O(1), amma `update` O(n) — bütün prefix-ləri yenilər.
Segment Tree: **Hər iki əməliyyat O(log n)**.

Array-i 4n ölçülü tree array-ə çeviririm. Hər interior node öz aralığının cəmini saxlayır. `sumRange(l,r)` query-si rekursiv olaraq tamamilə daxil olan node-ları cəmləyir, kəsişəni uşaqlara göndərir. `update(i,val)` leaf-ə gedir, yuxarıya qalxaraq hər node-u yeniləyir.

Complexity: build O(n), query O(log n), update O(log n). Space: O(n).

### Kod Nümunəsi

```python
class SegmentTree:
    """Sum Segment Tree — 1-indexed, recursive"""
    def __init__(self, nums: list):
        self.n = len(nums)
        self.tree = [0] * (4 * self.n)
        if nums:
            self._build(nums, 1, 0, self.n - 1)

    def _build(self, nums: list, node: int, start: int, end: int):
        if start == end:
            self.tree[node] = nums[start]
            return
        mid = (start + end) // 2
        self._build(nums, 2*node, start, mid)
        self._build(nums, 2*node+1, mid+1, end)
        self.tree[node] = self.tree[2*node] + self.tree[2*node+1]  # combine

    def update(self, idx: int, val: int, node: int = 1, start: int = 0, end: int = None):
        if end is None:
            end = self.n - 1
        if start == end:
            self.tree[node] = val
            return
        mid = (start + end) // 2
        if idx <= mid:
            self.update(idx, val, 2*node, start, mid)
        else:
            self.update(idx, val, 2*node+1, mid+1, end)
        self.tree[node] = self.tree[2*node] + self.tree[2*node+1]

    def query(self, l: int, r: int, node: int = 1, start: int = 0, end: int = None) -> int:
        if end is None:
            end = self.n - 1
        if r < start or end < l:
            return 0           # aralıq xaricindədir — neutral element
        if l <= start and end <= r:
            return self.tree[node]  # tam daxildir
        mid = (start + end) // 2
        left_sum = self.query(l, r, 2*node, start, mid)
        right_sum = self.query(l, r, 2*node+1, mid+1, end)
        return left_sum + right_sum

# Min Segment Tree
class MinSegmentTree:
    def __init__(self, nums: list):
        self.n = len(nums)
        self.tree = [float('inf')] * (4 * self.n)
        if nums:
            self._build(nums, 1, 0, self.n - 1)

    def _build(self, nums, node, start, end):
        if start == end:
            self.tree[node] = nums[start]; return
        mid = (start + end) // 2
        self._build(nums, 2*node, start, mid)
        self._build(nums, 2*node+1, mid+1, end)
        self.tree[node] = min(self.tree[2*node], self.tree[2*node+1])

    def query_min(self, l, r, node=1, start=0, end=None):
        if end is None: end = self.n - 1
        if r < start or end < l: return float('inf')
        if l <= start and end <= r: return self.tree[node]
        mid = (start + end) // 2
        return min(self.query_min(l, r, 2*node, start, mid),
                   self.query_min(l, r, 2*node+1, mid+1, end))

# Lazy Propagation — Range Add + Range Sum
class LazySegmentTree:
    def __init__(self, n: int):
        self.n = n
        self.tree = [0] * (4 * n)
        self.lazy = [0] * (4 * n)

    def _push_down(self, node: int, start: int, end: int):
        if self.lazy[node] != 0:
            mid = (start + end) // 2
            # Sol uşağa tətbiq et
            self.tree[2*node] += self.lazy[node] * (mid - start + 1)
            self.lazy[2*node] += self.lazy[node]
            # Sağ uşağa tətbiq et
            self.tree[2*node+1] += self.lazy[node] * (end - mid)
            self.lazy[2*node+1] += self.lazy[node]
            # Lazy-ni sıfırla
            self.lazy[node] = 0

    def range_update(self, l: int, r: int, val: int,
                     node: int = 1, start: int = 0, end: int = None):
        if end is None: end = self.n - 1
        if r < start or end < l: return
        if l <= start and end <= r:
            self.tree[node] += val * (end - start + 1)
            self.lazy[node] += val
            return
        self._push_down(node, start, end)
        mid = (start + end) // 2
        self.range_update(l, r, val, 2*node, start, mid)
        self.range_update(l, r, val, 2*node+1, mid+1, end)
        self.tree[node] = self.tree[2*node] + self.tree[2*node+1]

    def range_query(self, l: int, r: int,
                    node: int = 1, start: int = 0, end: int = None) -> int:
        if end is None: end = self.n - 1
        if r < start or end < l: return 0
        if l <= start and end <= r: return self.tree[node]
        self._push_down(node, start, end)   # lazımı push_down ƏVVƏL!
        mid = (start + end) // 2
        return (self.range_query(l, r, 2*node, start, mid) +
                self.range_query(l, r, 2*node+1, mid+1, end))

# Fenwick Tree (BIT) — müqayisə üçün
class FenwickTree:
    """Sum BIT — O(log n) update/prefix query, O(1) space overhead"""
    def __init__(self, n: int):
        self.n = n
        self.tree = [0] * (n + 1)

    def update(self, i: int, delta: int):
        """i-ci indeksə delta əlavə et (1-indexed)"""
        while i <= self.n:
            self.tree[i] += delta
            i += i & (-i)   # LSB trick: növbəti parent

    def prefix_sum(self, i: int) -> int:
        """1-dən i-yə qədər cəm (1-indexed)"""
        total = 0
        while i > 0:
            total += self.tree[i]
            i -= i & (-i)   # LSB trick: parent-i tap
        return total

    def range_sum(self, l: int, r: int) -> int:
        """[l, r] cəm (1-indexed)"""
        return self.prefix_sum(r) - self.prefix_sum(l - 1)
```

### İkinci Nümunə — Coordinate Compression + Segment Tree

```python
# Count Smaller Numbers After Self — O(n log n)
def count_smaller(nums: list) -> list:
    # Coordinate compression
    sorted_unique = sorted(set(nums))
    rank = {v: i + 1 for i, v in enumerate(sorted_unique)}  # 1-indexed
    n = len(sorted_unique)

    # BIT for counting
    bit = FenwickTree(n)
    result = []

    for num in reversed(nums):
        r = rank[num]
        # num-dən kiçik elementlərin sayı = prefix_sum(r-1)
        result.append(bit.prefix_sum(r - 1))
        bit.update(r, 1)   # num-u əlavə et

    return result[::-1]

# Range Sum Query — LeetCode 307
class NumArray:
    def __init__(self, nums: list):
        self.nums = nums[:]
        self.st = SegmentTree(nums)

    def update(self, index: int, val: int) -> None:
        self.st.update(index, val)

    def sumRange(self, left: int, right: int) -> int:
        return self.st.query(left, right)
```

## Praktik Tapşırıqlar

1. LeetCode #307 — Range Sum Query - Mutable — klassik segment tree.
2. LeetCode #315 — Count of Smaller Numbers After Self — BIT + coordinate compression.
3. LeetCode #699 — Falling Squares — lazy segment tree (coordinate compression).
4. LeetCode #850 — Rectangle Area II — coordinate compression + seg tree.
5. LeetCode #2407 — Longest Increasing Subsequence II — segment tree DP optimizasiyası.
6. Lazy tapşırığı: Range add + range min query üçün lazy segment tree implement et.
7. BIT tapşırığı: Prefix sum BIT implement et. `i & (-i)` niyə LSB-i verir izah et.
8. Design tapşırığı: Time-series metrika sistemi — 1M sensor, son 1 saat üçün min/max/avg query. Segment tree-ni necə istifadə edərdin?
9. 2D tapşırığı: 2D matrix-də range sum query + point update. O(log²n) impl düşün.
10. Özünütəst: `4n` array ölçüsünün niyə `2n`-dən daha güvənli olduğunu ağac quruluşu ilə izah et.

## Əlaqəli Mövzular

- **Fenwick Tree (BIT)** — Sadə prefix sum üçün alternativ. Trade-off-ları bil: BIT sadə amma min/max üçün çətin.
- **Balanced BST** — Range query üçün başqa yanaşma. `TreeMap.subMap()` range query edir.
- **Divide and Conquer** — Segment tree-nin recursive build-i D&C-dir.
- **Dynamic Programming** — Segment tree DP-ni O(n²) → O(n log n)-ə endirə bilər (LIS variant).
- **Merge Sort** — Merge Sort Tree segment tree-nin bir variantıdır (hər node sorted array).
