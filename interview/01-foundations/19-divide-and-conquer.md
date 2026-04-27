# Divide and Conquer (Senior ⭐⭐⭐)

## İcmal

Divide and Conquer — problemi daha kiçik alt-problemlərə bölür (divide), hər birini rekursiv həll edir (conquer), sonra nəticələri birləşdirir (combine). Merge Sort, Quick Sort, Binary Search, Karatsuba çarpımı klassik nümunələrdir. Interview-da bu pattern-i tanımaq və Master Theorem ilə complexity hesablamaq bacarığı vacibdir — çünki bu iki bacarıq rekursiv alqoritmləri şifahi izah etməyə imkan verir. D&C-nin güclü tərəfi: alt-problemlər müstəqil olduğundan paralel icra mümkündür.

## Niyə Vacibdir

Divide and Conquer paralel hesablama üçün fundamentaldır: Alt-problemlər bir-birindən asılı olmadığından paralel işlənə bilər. MapReduce, parallel sort, distributed computing bu ideyaya əsaslanır. FAANG interview-larında namizədlər rekursiya ağacını çəkib time complexity-ni Master Theorem ilə hesablamalı, problemi alt-problemlərə bölməyi gördükdə dərhal pattern-i tanımalıdır. D&C vs DP seçimi — overlapping subproblems varsa DP, yoxdursa D&C — bu fərqi aydın izah edə bilmək lazımdır.

## Əsas Anlayışlar

### D&C Şablonu

Hər D&C alqoritmi üç hissədən ibarətdir:
1. **Base case**: Problem kifayət qədər kiçikdir, birbaşa həll et (recursion-ı dayandır).
2. **Divide**: Problemi k alt-problemə böl (adətən k=2, hər alt-problem n/b ölçülü).
3. **Conquer + Combine**: Alt-problemləri rekursiv həll et, nəticələri birləşdir.

Combine addımının complexity-si T(n)-i müəyyən edir — bu ən kritik hissədir.

### Master Theorem

`T(n) = a·T(n/b) + f(n)` rekurransı üçün:
- `a` — alt-problem sayı (≥ 1)
- `b` — hər alt-problemin ölçüsü bölücüsü (> 1)
- `f(n)` — divide + combine işi

**Nəticə (3 hal):**
| Şərt | T(n) |
|------|------|
| `f(n) = O(n^(log_b a - ε))`, ε > 0 | `T(n) = Θ(n^(log_b a))` — recursion dominates |
| `f(n) = Θ(n^(log_b a) · log^k n)` | `T(n) = Θ(n^(log_b a) · log^(k+1) n)` — hər ikisi bərabər |
| `f(n) = Ω(n^(log_b a + ε))` | `T(n) = Θ(f(n))` — combine dominates |

**Nümunələr:**
- **Merge Sort**: `T(n) = 2T(n/2) + O(n)` → `log_2(2) = 1`, `f(n) = n^1` → Case 2 → `T(n) = O(n log n)`.
- **Binary Search**: `T(n) = T(n/2) + O(1)` → `log_2(1) = 0`, `f(n) = n^0 = 1` → Case 2 → `T(n) = O(log n)`.
- **Karatsuba**: `T(n) = 3T(n/2) + O(n)` → `log_2(3) ≈ 1.585`, `n^1.585 > n^1` → Case 1 → `T(n) = O(n^1.585)`.
- **Strassen**: `T(n) = 7T(n/2) + O(n²)` → `log_2(7) ≈ 2.807`, `n^2.807 > n^2` → Case 1 → `T(n) = O(n^2.807)`.

### Recursion Tree Metodu

Master Theorem işləməyəndə recursion tree çəkilir:
1. Hər level-in işini hesabla.
2. Level sayını tap (log_b(n) — n ölçüsü 1-ə çatana qədər).
3. Bütün level-lərin işini topla.
4. Geometric ya arithmetic series-ə görə simplify et.

Nümunə: `T(n) = 2T(n/2) + n²`:
- Level 0: n² iş
- Level 1: 2 × (n/2)² = n²/2 iş
- Level 2: 4 × (n/4)² = n²/4 iş
- Cəm: n²(1 + 1/2 + 1/4 + ...) = 2n² = O(n²) → f(n) dominates.

### Klassik D&C Alqoritmləri

**Merge Sort**
- Divide: n/2 + n/2.
- Conquer: Hər yarısı rekursiv sort.
- Combine: İki sorted array-i O(n) ilə merge et.
- **Stable sort** — eyni dəyərli elementlərin sırası qorunur.
- O(n log n) worst case — QuickSort-dan üstün (worst case-ə görə).
- **Çatışmazlıq**: O(n) extra space — in-place deyil.

**Quick Sort**
- Divide: Pivot seç, partition et (< pivot sol, > pivot sağ).
- Conquer: Hər partition-ı rekursiv sort.
- Combine: Artıq gerekmez (in-place partition).
- Average O(n log n), **worst case O(n²)** — pivot seçimi kritikdir.
- Worst case: Hər dəfə min ya max pivot seçilsə (already sorted array).
- Randomized pivot: O(n²) ehtimalı çox azdır (expected O(n log n)).
- **3-way partition (Dutch National Flag)**: Duplicate-lar çox olduqda O(n log n) → O(n) possible.
- **In-place** — O(log n) stack space (recursion).
- Praktikada Merge Sort-dan sürətli (cache friendly, az overhead).

**Binary Search (D&C olaraq)**
- Divide: Orta nöqtəyə bax.
- Conquer: Ya sol, ya sağ yarı.
- Combine: Lazım deyil.
- `T(n) = T(n/2) + O(1)` → O(log n).
- Iterative variant: O(1) space.

**Closest Pair of Points**
- Plane-dəki n nöqtə arasında ən yaxın cütü tap.
- Naive: O(n²) — bütün cütləri yoxla.
- D&C: O(n log n):
  1. x koordinatına görə sort.
  2. Ortadan böl: sol/sağ yarı üçün rekursiv ən yaxın cüt.
  3. Combine: `δ = min(d_left, d_right)`. Orta çizginin `±δ` zonanın içindəki nöqtə cütlərini yoxla.
  4. Key insight: Bu zonanın hər nöqtəsi üçün ən çox 7 nöqtəni yoxlamaq lazımdır (geometric argument).

**Matrix Multiplication**
- Naive üçüzlü dövrə: O(n³).
- Strassen: `T(n) = 7T(n/2) + O(n²)` → O(n^2.807).
- Coppersmith–Winograd: O(n^2.376) — praktikada az istifadə olunur.
- Strassen-in cache locality-si pisdir — praktikada n > 1000-dən faydalıdır.

**Karatsuba Multiplication (n-rəqəmli ədədlər)**
- Naive: O(n²) — klassik long multiplication.
- Karatsuba: 4 əvəzinə 3 çarpım → `T(n) = 3T(n/2) + O(n)` → O(n^1.585).
- Python-un `int.__mul__` böyük ədədlər üçün Karatsuba istifadə edir.

### Quick Select (D&C + Greedy hybrid)

- K-th smallest element: Sort etmək lazım deyil.
- Partition et (Quick Sort kimi), K-ci element hansı tərəfdədir?
- Average O(n), worst case O(n²) — randomized pivot ilə expected O(n).
- **Median-of-medians**: Worst case O(n) — amma constant factor böyük, praktikada yavaş.
- Interview-da: "K-th largest" üçün min-heap O(n log k) vs QuickSelect O(n avg) — trade-off izah et.

### Counting Inversions (Merge Sort Variantı)

- `arr[i] > arr[j]` olduqda `i < j` olarsa inversion.
- Key insight: Merge Sort-un merge addımında right-dan left-ə keçən elementlər inversion-u təmsil edir.
- Merge zamanı `left[i] > right[j]` olarsa, `left[i..]` hamısı `right[j]`-dən böyükdür → `len(left) - i` inversion.
- O(n log n).

### Skyline Problem (D&C)

- D&C ilə: Hər yarısı skyline-ı tap, merge et.
- Merge addımı O(n) — Merge Sort-un combine-ına bənzəyir.
- O(n log n) total.

### D&C vs DP

| Kriteriya | D&C | DP |
|-----------|-----|-----|
| Alt-problemlər | **Müstəqil** | **Üst-üstə düşür** |
| Memoization lazım? | Adətən yox | Bəli |
| Paralel icra | Asan | Çətin (dependency var) |
| Space | O(log n) stack | O(n) ya O(n²) |
| Nümunə | Merge Sort, Binary Search | Edit Distance, LCS |

Əgər alt-problemlər üst-üstə düşürsə → DP (D&C-nin hər dəfə yenidən həll etməsi O(exponential) ola bilər).

## Praktik Baxış

### Interview Yanaşması

1. "Problemi iki yarıya bölsəm nə olar?" deyə soruş.
2. Recursion ağacını çək (ən azı 2-3 level).
3. `T(n) = aT(n/b) + f(n)` rekurrensını yaz.
4. Master Theorem tətbiq et, complexity-ni göstər.
5. Combine addımının complexity-si nədir? — Bu T(n)-i müəyyən edir.
6. Base case-i unutma.

### Nədən Başlamaq

- Divide strategiyasını aydınlaşdır (yarıya, pivot-a görə, və s.).
- Combine strategiyasını aydınlaşdır: O(1)? O(n)? O(n log n)?
- Rekurrens yazdıqdan sonra Master Theorem tətbiq et.
- Alt-problemlər müstəqilmi? Əgər yox → D&C deyil, DP.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Bu alqoritmi parallelize edə bilərsinizmi?" — Alt-problemlər müstəqil = paralel icra.
- "Worst case yaxşılaşdırmaq üçün nə edərdiniz?" — Quick Sort → randomize pivot.
- "D&C yerinə iterative yanaşma mümkündürmü?" — Stack ilə, ya bottom-up.
- "MapReduce bu ideyadan necə istifadə edir?" — Map (divide), Reduce (combine).
- "Merge Sort vs Quick Sort — nə vaxt hansını seçərsiniz?"
- "Master Theorem işləmədiyi hal var? Nümunə?"

### Common Mistakes

- Base case-i unutmaq → infinite recursion → stack overflow.
- Combine addımının complexity-sini yanlış hesablamaq → yanlış T(n).
- Alt-problemlər müstəqil olmadığında D&C-ni tətbiq etmək (DP lazımdır).
- Merge Sort-un stable, Quick Sort-un in-place olduğunu qarışdırmaq.
- Quick Sort worst case-ini izah edə bilməmək (sorted array + first pivot).
- Master Theorem-in hər 3 halını bilməmək.

### Yaxşı → Əla Cavab

- **Yaxşı**: Alqoritmi implement edir, O(n log n) deyir.
- **Əla**: Master Theorem-i tətbiq edir, rekurrens yazır, parallelization imkanlarını, stable/in-place trade-off-larını izah edir, D&C vs DP fərqini concretely göstərir.

### Real Production Ssenariləri

- MapReduce: Google-un distributed computing modeli — Map (divide), Reduce (combine).
- External sort: 1TB data-nı 10 maşında sort et → merge K sorted chunks.
- FFT (Fast Fourier Transform): Signal processing, polynomial multiplication O(n log n).
- Closest pair: Geographic clustering, collision detection.
- Big integer arithmetic: Python, Java BigInteger → Karatsuba.

## Nümunələr

### Tipik Interview Sualı

"Array-dəki inversions sayını tapın. `[2, 4, 1, 3, 5]` üçün cavab 3-dür: (2,1), (4,1), (4,3)."

### Güclü Cavab

Bu problemi modified merge sort ilə O(n log n)-də həll edərdim. Naive yanaşma hər cütü yoxlamaq O(n²) verir.

Insight: Merge Sort-un merge addımında `right[j] < left[i]` olarsa, `left[i..]` bütün elementləri `right[j]`-dən böyükdür — bu `len(left) - i` inversion deməkdir.

Bu şəkildə merge etdikcə inversion sayını toplayıram. Sol yarıdakı inversions + sağ yarıdakı inversions + cross-inversions (merge zamanı) = total inversions.

`T(n) = 2T(n/2) + O(n)` → Master Theorem Case 2 → O(n log n).

### Kod Nümunəsi

```python
from typing import List

# Counting Inversions — Modified Merge Sort — O(n log n)
def count_inversions(arr: List[int]) -> tuple:
    """(sorted_arr, inversion_count) qaytarır"""
    if len(arr) <= 1:
        return arr, 0

    mid = len(arr) // 2
    left, left_inv = count_inversions(arr[:mid])
    right, right_inv = count_inversions(arr[mid:])

    merged = []
    inversions = left_inv + right_inv
    i = j = 0

    while i < len(left) and j < len(right):
        if left[i] <= right[j]:
            merged.append(left[i])
            i += 1
        else:
            # left[i..] hamısı right[j]-dən böyükdür → cross inversions
            inversions += len(left) - i
            merged.append(right[j])
            j += 1

    merged.extend(left[i:])
    merged.extend(right[j:])
    return merged, inversions

# Quick Select — K-th smallest — Average O(n)
def quick_select(nums: List[int], k: int) -> int:
    """0-indexed: k=0 ən kiçik element"""
    def partition(left, right, pivot_idx):
        pivot = nums[pivot_idx]
        nums[pivot_idx], nums[right] = nums[right], nums[pivot_idx]
        store = left
        for i in range(left, right):
            if nums[i] < pivot:
                nums[store], nums[i] = nums[i], nums[store]
                store += 1
        nums[store], nums[right] = nums[right], nums[store]
        return store

    left, right = 0, len(nums) - 1
    while left <= right:
        import random
        pivot_idx = random.randint(left, right)   # randomized pivot
        pivot_idx = partition(left, right, pivot_idx)
        if pivot_idx == k:
            return nums[pivot_idx]
        elif pivot_idx < k:
            left = pivot_idx + 1
        else:
            right = pivot_idx - 1
    return -1   # unreachable

# Maximum Subarray — D&C yanaşması O(n log n)
# Not: Kadane O(n)-dir, bu D&C variant O(n log n)-dir — interview-da hər ikisini bil
def max_subarray_dc(nums: List[int]) -> int:
    def helper(left, right) -> int:
        if left == right:
            return nums[left]

        mid = (left + right) // 2
        left_max = helper(left, mid)
        right_max = helper(mid + 1, right)

        # mid-i keçən maksimal subarray
        left_sum = curr = 0
        for i in range(mid, left - 1, -1):
            curr += nums[i]
            left_sum = max(left_sum, curr)

        right_sum = curr = 0
        for i in range(mid + 1, right + 1):
            curr += nums[i]
            right_sum = max(right_sum, curr)

        return max(left_max, right_max, left_sum + right_sum)

    return helper(0, len(nums) - 1)

# Merge Sort — stable, O(n log n), O(n) space
def merge_sort(arr: List[int]) -> List[int]:
    if len(arr) <= 1:
        return arr
    mid = len(arr) // 2
    left = merge_sort(arr[:mid])
    right = merge_sort(arr[mid:])
    return merge(left, right)

def merge(left: List[int], right: List[int]) -> List[int]:
    result = []
    i = j = 0
    while i < len(left) and j < len(right):
        if left[i] <= right[j]:   # <=: stable sort
            result.append(left[i]); i += 1
        else:
            result.append(right[j]); j += 1
    result.extend(left[i:])
    result.extend(right[j:])
    return result

# Binary Search — iterative D&C — O(log n), O(1) space
def binary_search(arr: List[int], target: int) -> int:
    left, right = 0, len(arr) - 1
    while left <= right:
        mid = left + (right - left) // 2   # overflow prevention
        if arr[mid] == target:
            return mid
        elif arr[mid] < target:
            left = mid + 1
        else:
            right = mid - 1
    return -1

# Quick Sort — in-place, average O(n log n)
def quick_sort(arr: List[int], low: int = 0, high: int = None) -> None:
    if high is None:
        high = len(arr) - 1
    if low < high:
        import random
        # Randomized pivot: swap random element with last
        rand = random.randint(low, high)
        arr[rand], arr[high] = arr[high], arr[rand]

        pivot = arr[high]
        i = low - 1
        for j in range(low, high):
            if arr[j] <= pivot:
                i += 1
                arr[i], arr[j] = arr[j], arr[i]
        arr[i+1], arr[high] = arr[high], arr[i+1]
        pi = i + 1

        quick_sort(arr, low, pi - 1)
        quick_sort(arr, pi + 1, high)

# Closest Pair of Points — O(n log n)
import math
def closest_pair(points: List[tuple]) -> float:
    def brute_force(pts):
        min_d = float('inf')
        for i in range(len(pts)):
            for j in range(i+1, len(pts)):
                d = math.dist(pts[i], pts[j])
                min_d = min(min_d, d)
        return min_d

    def strip_closest(strip, delta):
        strip.sort(key=lambda p: p[1])
        min_d = delta
        for i in range(len(strip)):
            j = i + 1
            while j < len(strip) and strip[j][1] - strip[i][1] < min_d:
                min_d = min(min_d, math.dist(strip[i], strip[j]))
                j += 1
        return min_d

    def closest_rec(pts):
        if len(pts) <= 3:
            return brute_force(pts)
        mid = len(pts) // 2
        mid_x = pts[mid][0]
        d_left = closest_rec(pts[:mid])
        d_right = closest_rec(pts[mid:])
        delta = min(d_left, d_right)
        strip = [p for p in pts if abs(p[0] - mid_x) < delta]
        return min(delta, strip_closest(strip, delta))

    points.sort()
    return closest_rec(points)
```

### İkinci Nümunə — Master Theorem Tətbiqi

```python
# Master Theorem nümunələri:
"""
T(n) = 2T(n/2) + O(n log n):
- a=2, b=2, f(n) = n log n
- log_b(a) = log_2(2) = 1
- f(n) = n log n = n^1 * log n → Case 2 (k=1)
- T(n) = O(n log² n)

T(n) = 4T(n/2) + O(n²):
- a=4, b=2, f(n) = n²
- log_b(a) = log_2(4) = 2
- f(n) = n^2 = n^(log_b a) → Case 2 (k=0)
- T(n) = O(n² log n)

T(n) = 4T(n/2) + O(n³):
- a=4, b=2, f(n) = n³
- log_b(a) = 2, f(n) = n³ → n³ = Ω(n^(2+1))
- Case 3: T(n) = Θ(f(n)) = O(n³)
"""
```

## Praktik Tapşırıqlar

1. LeetCode #912 — Sort an Array (Merge Sort implement et, stable olduğunu göstər).
2. LeetCode #215 — Kth Largest Element (Quick Select — average O(n)).
3. LeetCode #315 — Count of Smaller Numbers After Self (merge sort variation, inversions).
4. LeetCode #327 — Count of Range Sum (merge sort).
5. LeetCode #493 — Reverse Pairs (merge sort).
6. LeetCode #23 — Merge K Sorted Lists (D&C merge — O(n log k)).
7. Master Theorem tapşırığı: `T(n) = 4T(n/2) + O(n²)` üçün complexity tap.
8. Master Theorem tapşırığı: `T(n) = 2T(n/2) + O(n log n)` üçün complexity tap.
9. Design tapşırığı: Distributed sort — 1TB data-nı 10 maşında sort et. D&C-ni necə tətbiq edərdin? Merge step necə işlər?
10. Özünütəst: Quick Sort worst case-ni reproduce et (sorted input + first element pivot). Randomized pivot ilə fərqi time-la ölç.

## Əlaqəli Mövzular

- **Recursion / Recurrence Relations** — D&C rekursiva əsaslanır. Master Theorem rekurrensi həll edir.
- **Dynamic Programming** — Üst-üstə düşən alt-problemlər üçün D&C-nin alternativi. Overlap varsa DP.
- **Sorting Algorithms** — Merge Sort, Quick Sort D&C-nin ən əhəmiyyətli tətbiqləridir.
- **Binary Search** — D&C-nin sadə forması. O(log n) space-free variant.
- **Greedy Algorithms** — Bəzən D&C ilə birləşir (Quick Select = D&C + greedy pivot).
- **Graph Fundamentals** — Parallel BFS/DFS D&C-nin graph variant-ı ola bilər.
