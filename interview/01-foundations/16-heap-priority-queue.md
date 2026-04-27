# Heap / Priority Queue (Senior ⭐⭐⭐)

## İcmal

Heap — complete binary tree xüsusiyyətini qoruyan, əsasən array şəklində implement edilən data structure-dur. Min-heap-də hər node öz uşaqlarından kiçik ya bərabərdir, max-heap-də isə böyük ya bərabərdir. Priority Queue, heap üzərində qurulan abstract data type-dır: elementlər priority-yə görə çıxarılır. Bu mövzu interview-da tez-tez çıxır, çünki bir çox real problem "ən böyük/kiçik K elementi tap" pattern-inə aiddir.

## Niyə Vacibdir

Interviewerlər heap mövzusunu soruşanda namizədin axtarış/sıralama əvəzinə optimal data structure seçə bilib-bilmədiyini yoxlayırlar. "Top K elements", "median of data stream", "meeting rooms", "merge K sorted lists" kimi klassik suallar heap olmadan effektiv həll olunmur. Google, Meta, Amazon, Uber kimi şirkətlərdə scheduling, event processing, Dijkstra, Prim kimi alqoritmlər real istifadə nümunələrdir. Senior rol üçün sadə istifadə yetmir — implementation detallarını, heap property-nin necə qorunduğunu, lazy deletion kimi advanced texnikaları, build heap-in niyə O(n) olduğunu bilmək lazımdır.

## Əsas Anlayışlar

### Heap Növləri

- **Min-heap**: Root ən kiçik element; `parent <= children`. Extract-min O(log n).
- **Max-heap**: Root ən böyük element; `parent >= children`. Extract-max O(log n).
- **Binary heap**: Ən çox istifadə edilən, complete binary tree + heap property. Array ilə implement edilir.
- **Fibonacci heap**: Amortized O(1) decrease-key; Dijkstra üçün optimal (teorik). Praktikada az istifadə olunur — constant factors böyükdür.
- **Binomial heap**: Mergeable heap; O(log n) merge.
- **d-ary heap**: Hər node-da d uşaq. d=4 ya d=8: cache locality daha yaxşı. External memory-da üstün.

### Array Representation (0-indexed)

```
Array: [10, 15, 20, 17, 25]
Tree:
       10
      /  \
    15    20
   / \
  17  25
```

- `parent(i) = (i - 1) // 2`
- `left_child(i) = 2 * i + 1`
- `right_child(i) = 2 * i + 2`

Bu formula sayəsində pointer saxlamağa ehtiyac yoxdur. Array-ə birbaşa index ilə müraciət O(1)-dir.

### Əsas Əməliyyatlar və Complexity

| Əməliyyat | Binary Heap | Fibonacci Heap | Açıqlama |
|-----------|-------------|----------------|----------|
| Insert | O(log n) | O(1) amortized | Sift-up |
| Extract-min/max | O(log n) | O(log n) amortized | Sift-down |
| Peek (min/max) | O(1) | O(1) | Root |
| Decrease-key | O(log n) | O(1) amortized | Fibonacci-nin üstünlüyü |
| Build heap | O(n) | O(n) | Naive O(n log n) deyil! |
| Merge | O(n) | O(1) | Fibonacci üstün |
| Delete arbitrary | O(log n) + O(n) find | O(log n) amortized | Find O(n) bottleneck |

### Heapify Prosesi

**Sift-up (bubble-up)** — yeni element əlavə olunanda:
```
Array sonuna əlavə et → parent ilə müqayisə → lazım olsa swap → root-a qədər təkrar
```

**Sift-down (heapify-down)** — root çıxarılanda:
```
Son element-i root-a qoy → uşaqlarla müqayisə → ən kiçik uşaqla swap → leaf-ə qədər
```

### Build Heap — Niyə O(n)?

Naïve: n element-i birer birer insert et → O(n log n). Amma `heapify` O(n)-dir:
- Array-i al (hər sıra geçerli), yarısından başlayaraq sift-down et.
- Yarısı leaf — 0 iş. Sonrakı `n/4` elementlər height 1 — 1 swap max. ...
- Riyazi: `n * Σ(h/2^h) = 2n = O(n)`.
- Bu sübut interview-da çox sərbəst soruşulur.

### Heap Sort

- Max-heap qur: O(n)
- Hər dəfə max-ı (root) çıxar, array-in sonuna qoy: O(n log n)
- In-place, O(1) extra space
- **Əsas çatışmazlıq**: Cache-unfriendly (random memory access), QuickSort-dan praktikada yavaş.
- Worst case: O(n log n) — QuickSort-dan üstün (QuickSort worst O(n²)).
- Stable deyil.

### K-way Merge

- K sorted list-i merge etmək üçün min-heap istifadə et.
- Hər listdən ilk elementi heap-ə əlavə et.
- Extract-min, həmin listin növbəti elementini əlavə et.
- Complexity: O(n log k), burada n — total element sayı, k — list sayı.
- Tətbiq: External sort (disk-dən K sorted chunk merge etmə), merge K sorted arrays.

### Top K Elements Pattern (Ən Vacib Pattern)

**K ən böyük element:**
```
Min-heap (k ölçülü) saxla.
Yeni element heap-in top-undan böyükdürsə: top-u çıxar, yenini əlavə et.
Complexity: O(n log k) — n elementdən keçirik, heap k ölçülü.
```

**K ən kiçik element:**
```
Max-heap (k ölçülü) saxla.
Yeni element heap-in top-undan kiçikdirsə: top-u çıxar, yenini əlavə et.
Complexity: O(n log k).
```

**Niyə min-heap k-ən-böyük üçün?**
- K-ölçülü min-heap: root həmişə bu K-nın ən kiçiyidir.
- Yeni element gəldikdə: root-dan böyüksə, root-u çıxar, yenini əlavə et.
- O(n log k) — hamısını sort etməkdən O(n log n) daha yaxşı.

### Lazy Deletion

- Heap-dən arbitrary elementləri silmək: find O(n) + sift O(log n) = O(n).
- **Lazy deletion**: Elementi "silindi" kimi işarələ, extract zamanı işarəlini atla.
- Prim, Dijkstra-nın bəzi implementasiyalarında istifadə edilir.
- Trade-off: Heap şişir, amma kod daha sadə olur.

### Two-Heap Pattern (Median Finding)

- Kiçik yarı üçün **max-heap** (`lo`), böyük yarı üçün **min-heap** (`hi`).
- İnvaryant: `lo.size() >= hi.size()` and `lo.size() - hi.size() <= 1`.
- Hər element əlavə olunanda balans qoru.
- Median: `len(lo) > len(hi)` → `lo`-nun top-u, bərabərdirsə iki top-un ortalması.
- Addım: `lo`-ya at → `lo`-nun top-unu `hi`-ya keçir → balans yoxla → lazımsa `hi`-dan `lo`-ya geri ver.

### Sliding Window Maximum — Deque + Heap Hybrid

- Ölçüsü k olan sliding window-da maximum.
- Naive: Hər window üçün max → O(n*k).
- Monotonic deque: O(n) — amma heap olmadan.
- Heap: Lazy deletion ilə O(n log n) — deque-dən yavaş amma daha intuitive.

### Python heapq Xüsusiyyətləri

- `heapq` min-heap-dir.
- Max-heap üçün: dəyərləri `(-val, val)` kimi saxla.
- `heapq.heappush(h, item)` — O(log n)
- `heapq.heappop(h)` — O(log n), minimum qaytarır
- `heapq.heappushpop(h, item)` — push + pop atomically, `heapreplace`-dən sürətli
- `heapq.heapreplace(h, item)` — pop + push atomically
- `heapq.heapify(list)` — O(n) in-place heap yaradır
- `heapq.nlargest(k, iterable)` — O(n log k)
- `heapq.nsmallest(k, iterable)` — O(n log k)

## Praktik Baxış

### Interview Yanaşması

1. "K ən böyük/kiçik" tələb edirsə → min/max heap ilə K-ölçülü heap.
2. "Dinamik data gəlir, median/max/min lazım" → two-heap pattern.
3. "Scheduling, task processing, prioritization" → priority queue.
4. "Graph alqoritmi (Dijkstra, Prim)" → heap lazım olduğunu qeyd et.
5. "Merge K sorted" → heap ilə K-way merge.

### Nədən Başlamaq

- Əvvəlcə brute force söylə (sort + access), sonra heap ilə optimallaşdır.
- Built-in heap istifadə et (Python `heapq`, Java `PriorityQueue`) — implementation yazmağı xahiş etmədikdə.
- Edge case-ləri qeyd et: boş heap, k > n, duplicate-lər, negative numbers.
- Python-da max-heap üçün `-value` trick-ini xatırla.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Heap-i array-dən başqa cür implement etmək olarmı?" (linked list — mümkün amma inefficient, pointer overhead).
- "Build heap niyə O(n)-dir, O(n log n) deyil?" — Riyazi sübut gözlənilir.
- "K-th largest element-i O(n)-də tapın" — QuickSelect (D&C).
- "Distributed sistemdə top-K necə tapılır?" — local top-K + merge.
- "Fibonacci heap-in üstünlüyü nədir?" — decrease-key O(1) amortized, Dijkstra üçün.
- "d-ary heap nə vaxt faydalıdır?" — d=4 cache locality üçün.

### Common Mistakes

- Python-da max-heap üçün `-value` trick-ini unutmaq.
- `heapq.heappush` vs `heapq.heapreplace` fərqini bilməmək.
- K ən böyük üçün max-heap istifadə edib O(n log n) cavab vermək (n ölçülü heap).
- Two-heap pattern-də ölçüləri balanslamağı unutmaq → median yanlış.
- Build heap-in O(n) olduğunu bilməmək (n insert = O(n log n) hesab etmək).
- Heap-ə object qoyarkən comparison error — `(priority, counter, item)` tuple istifadə et.

### Yaxşı → Əla Cavab

- **Yaxşı**: Heap istifadə edib doğru complexity verir.
- **Əla**: Niyə min-heap (k elementli) max-heap-dən daha yaxşı olduğunu izah edir, build heap-in O(n) olduğunu sübut edir, Fibonacci heap-in trade-off-larını bilir, Python-da max-heap trick-ini göstərir, production use case-lər verir.

### Real Production Ssenariləri

- Event-driven simulation: events priority-yə görə işlənir (simulation time).
- Job scheduler: yüksək priority job-lar əvvəl işlənir.
- Dijkstra, Prim: core alqoritmlər.
- Real-time leaderboard: top-K score.
- Stream processing: sliding window aggregate.
- OS process scheduler: CFS (Completely Fair Scheduler) red-black tree, amma priority queue konsepti.

## Nümunələr

### Tipik Interview Sualı

"Data stream-dən real-time median-ı tapın. `addNum(int num)` və `findMedian()` funksiyalarını implement edin."

### Güclü Cavab

Bu problemi iki heap-lə həll edərdim. Kiçik rəqəmlər üçün max-heap (`lo`), böyük rəqəmlər üçün min-heap (`hi`) saxlayıram. İnvaryant: `lo.size() >= hi.size()` və `lo.size() - hi.size() <= 1`.

`addNum` zamanı: Əvvəlcə `lo`-ya əlavə et (max-heap), sonra `lo`-nun top-unu `hi`-ya keçir. Əgər `hi.size() > lo.size()` olarsa, `hi`-nin top-unu `lo`-ya geri ver. Bu şəkildə hər zaman balans qorunur.

`findMedian` zamanı: `lo.size() == hi.size()` olarsa iki top-un ortalaması, əks halda `lo`-nun top-u qaytarılır.

Complexity: `addNum` — O(log n), `findMedian` — O(1). Space: O(n).

### Kod Nümunəsi

```python
import heapq
from typing import List

class MedianFinder:
    def __init__(self):
        self.lo = []  # max-heap (mənfi dəyərlərlə simulate)
        self.hi = []  # min-heap

    def addNum(self, num: int) -> None:
        heapq.heappush(self.lo, -num)          # lo-ya əlavə et (max-heap)
        # lo-nun top-unu (max) hi-ya keçir
        heapq.heappush(self.hi, -heapq.heappop(self.lo))
        # balans: lo həmişə >= hi ölçülü
        if len(self.hi) > len(self.lo):
            heapq.heappush(self.lo, -heapq.heappop(self.hi))

    def findMedian(self) -> float:
        if len(self.lo) > len(self.hi):
            return -self.lo[0]               # tək ölçü: lo-nun max-ı
        return (-self.lo[0] + self.hi[0]) / 2.0  # bərabər: iki top ortalama

# Top K Frequent Elements — O(n log k)
from collections import Counter
def top_k_frequent(nums: List[int], k: int) -> List[int]:
    freq = Counter(nums)
    # k ölçülü min-heap — (frequency, element)
    heap = []
    for num, count in freq.items():
        heapq.heappush(heap, (count, num))
        if len(heap) > k:
            heapq.heappop(heap)   # ən az frequent-i çıxar
    return [num for count, num in heap]

# K-th Largest Element — O(n log k)
def find_kth_largest(nums: List[int], k: int) -> int:
    heap = nums[:k]
    heapq.heapify(heap)     # O(k) build heap
    for num in nums[k:]:    # qalan elementlər
        if num > heap[0]:   # ən kiçik k-nın top-undan böyüksə
            heapq.heapreplace(heap, num)   # O(log k) — pop + push atomically
    return heap[0]          # k ən böyüyün ən kiçiyi = k-ıncı ən böyük

# Merge K Sorted Lists — O(n log k)
from typing import Optional

class ListNode:
    def __init__(self, val=0, next=None):
        self.val = val
        self.next = next

def merge_k_lists(lists: List[Optional[ListNode]]) -> Optional[ListNode]:
    heap = []
    counter = 0   # tie-breaker (ListNode comparable deyil)
    for node in lists:
        if node:
            heapq.heappush(heap, (node.val, counter, node))
            counter += 1

    dummy = ListNode(0)
    curr = dummy
    while heap:
        val, _, node = heapq.heappop(heap)
        curr.next = node
        curr = curr.next
        if node.next:
            heapq.heappush(heap, (node.next.val, counter, node.next))
            counter += 1
    return dummy.next

# Meeting Rooms II — minimum otaq sayı — O(n log n)
def min_meeting_rooms(intervals: List[List[int]]) -> int:
    if not intervals:
        return 0
    intervals.sort(key=lambda x: x[0])   # başlanğıc vaxtına görə sort
    heap = []   # min-heap: bitiş vaxtları
    for start, end in intervals:
        if heap and heap[0] <= start:
            heapq.heapreplace(heap, end)   # mövcud otağı istifadə et
        else:
            heapq.heappush(heap, end)      # yeni otaq aç
    return len(heap)

# K Closest Points to Origin — O(n log k)
def k_closest(points: List[List[int]], k: int) -> List[List[int]]:
    heap = []  # max-heap (k ölçülü)
    for x, y in points:
        dist = -(x*x + y*y)   # max-heap üçün mənfi
        heapq.heappush(heap, (dist, [x, y]))
        if len(heap) > k:
            heapq.heappop(heap)
    return [point for dist, point in heap]
```

### Build Heap O(n) Sübut (Interview-da faydalı)

```
n/2 elementlər leaf — sift-down lazım yox: 0 iş
n/4 elementlər height 1 — max 1 swap: n/4 * 1
n/8 elementlər height 2 — max 2 swap: n/8 * 2
...

Cəm: Σ(h=0 to log n) (n/2^(h+1)) * h
    = n/2 * Σ(h=0 to log n) h/2^h
    ≤ n/2 * 2 = n
    = O(n)

(Σ(h=0 to ∞) h/2^h = 2 — tanınan series)
```

Bu sübut naïve O(n log n)-dən (hər elementi insert etmək) fərqlidir.

## Praktik Tapşırıqlar

1. LeetCode #215 — Kth Largest Element in Array (Quick Select ilə O(n) vs Heap ilə O(n log k)).
2. LeetCode #295 — Find Median from Data Stream — two-heap pattern.
3. LeetCode #347 — Top K Frequent Elements — min-heap k ölçülü.
4. LeetCode #378 — Kth Smallest Element in Sorted Matrix.
5. LeetCode #23 — Merge K Sorted Lists — K-way merge.
6. LeetCode #743 — Network Delay Time (Dijkstra ilə heap).
7. LeetCode #253 — Meeting Rooms II — interval + heap.
8. LeetCode #1337 — K Weakest Rows in Matrix.
9. Özünü yoxla: Python-da max-heap implement et — negation trick olmadan, `(priority, value)` tuple comparison ilə.
10. Design: Real-time leaderboard sistemi üçün data structure seç. 10M user, top-100 sürətli. Heap vs sorted set müqayisəsi.

## Əlaqəli Mövzular

- **Sorting Algorithms** — Heap Sort heap-in birbaşa tətbiqidir. O(n log n) guaranteed, amma cache-unfriendly.
- **Shortest Path (Dijkstra)** — Priority queue olmadan Dijkstra O(V²) olur, heap ilə O((V+E) log V).
- **Top-K / Kth Element** — heap-in əsas tətbiq sahəsi. QuickSelect (O(n) avg) alternativi var.
- **Two Pointers / Sliding Window** — bəzən heap ilə birlikdə (sliding window maximum).
- **Greedy Algorithms** — Huffman encoding, Prim MST heap istifadə edir.
- **Divide and Conquer** — QuickSelect (D&C) vs Heap for K-th largest — trade-off bilin.
