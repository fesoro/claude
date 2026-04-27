# Sorting Algorithms Comparison (Middle ⭐⭐)

## İcmal
Sorting alqoritmləri — elementlər siyahısını müəyyən sıraya (ascending/descending) düzən alqoritmlərdir. Interview-larda sort alqoritmlərini implement etmək istənmir (nadir hallarda istisna), amma complexity analizi, stability, in-place xüsusiyyətləri, "bu problem üçün hansı sort daha yaxşıdır?" sualları mütləq gəlir. O(n log n) lower bound-un niyə mövcud olduğunu bilmək senior namizədin nişanəsidir.

## Niyə Vacibdir
Sorting anlayışı hər yerdədir: database ORDER BY, binary search tələbatı, median finding, closest pair, meeting rooms. "Bu sualı önce sort etsəm daha sadə olurmu?" düşüncəsi çox mühim bir problem-solving intuition-dır. Meta, Google, Stripe interview-larında "stable sort nə vaxt vacibdir?", "timsort nədir?", "niyə quicksort merge sort-dan praktikada daha sürətlidir?" kimi suallar gəlir.

## Əsas Anlayışlar

### Sort Alqoritmlərinin Müqayisəsi:

| Alqoritm | Best | Average | Worst | Space | Stable |
|---|---|---|---|---|---|
| Bubble Sort | O(n) | O(n²) | O(n²) | O(1) | Bəli |
| Selection Sort | O(n²) | O(n²) | O(n²) | O(1) | Xeyr |
| Insertion Sort | O(n) | O(n²) | O(n²) | O(1) | Bəli |
| Merge Sort | O(n log n) | O(n log n) | O(n log n) | O(n) | Bəli |
| Quick Sort | O(n log n) | O(n log n) | O(n²) | O(log n) | Xeyr |
| Heap Sort | O(n log n) | O(n log n) | O(n log n) | O(1) | Xeyr |
| Counting Sort | O(n+k) | O(n+k) | O(n+k) | O(k) | Bəli |
| Radix Sort | O(nk) | O(nk) | O(nk) | O(n+k) | Bəli |
| Tim Sort | O(n) | O(n log n) | O(n log n) | O(n) | Bəli |
| Shell Sort | O(n log n) | O(n log²n) | O(n²) | O(1) | Xeyr |

### Stability:
- **Stable sort**: Eyni dəyərli elementlər orijinal sıranı qoruyur.
- Niyə vacibdir: "Önce ada, sonra yaşa görə sort et" — birinci sort-un ardıcıllığı qorunmalıdır.
- Stable: Merge sort, insertion sort, counting sort, timsort, bubble sort.
- Unstable: Quick sort (standart), heap sort, selection sort, shell sort.
- Interview-da: "Əgər eyni key-li elementlər varsa, onların orijinal sırası vacibdirmi?" soruşun.

### In-place vs Out-of-place:
- **In-place**: O(1) əlavə yaddaş (ya da O(log n) call stack).
- Merge sort in-place deyil: O(n) auxiliary array lazımdır.
- Quick sort in-place-dir: partitioning O(1) extra space (call stack O(log n)).
- Heap sort in-place + O(n log n) worst case — amma praktikada yavaş (poor cache performance).
- "In-place stable sort" — çox çətin, adətən O(n log n) space ya ya stable quicksort variant.

### O(n log n) Lower Bound:
- Comparison-based sorting üçün minimum Ω(n log n)-dir.
- Sübut: n! permutasiya mümkündür, hər müqayisə ikiyə bölür → log₂(n!) ≈ n log n müqayisə lazımdır.
- Stirling's approximation: `log(n!) ≈ n log n - n`.
- Counting/radix sort bu sərhədi keçir çünki comparison-based deyil — element dəyərlərindən istifadə edir.

### Merge Sort — Divide & Conquer:
- Array-i ortadan ikiyə böl, hər yarını rekursiv sort et, sorted yarıları merge et.
- Guaranteed O(n log n) — worst case yoxdur.
- Stable + predictable — external sort-da istifadə olunur (disk-based).
- Space: O(n) — auxiliary array.
- Linked list sort üçün ideal: array-dən fərqli olaraq O(1) space-lə merge edilə bilər (pointer manipulation).

### Quick Sort — Pivot Partition:
- Pivot seç, kiçikləri sola, böyükləri sağa yerləşdir, rekursiv.
- Average O(n log n), worst case O(n²) (sorted array + always pick first/last pivot).
- **Randomized pivot** worst case-i praktikada ortadan qaldırır.
- **Median of three**: İlk, orta, son elementin median-ı pivot — daha yaxşı performans.
- Cache-friendly: in-place, contiguous memory access.
- Praktikada merge sort-dan sürətlidir (constants smaller, cache better).
- **QuickSelect**: K-th smallest element O(n) average — quicksort partition-ı tətbiq et.

### Heap Sort:
- Max-heap qur (O(n)), hər dəfə max-ı çıxar (O(log n) × n = O(n log n)).
- In-place + O(n log n) guaranteed, amma poor cache locality.
- Priority queue-nun natural sort tətbiqi.
- Heap qurmaq: bottom-up heapify O(n) — naively O(n log n) görünür amma əslində O(n).

### Insertion Sort:
- Kiçik array-lər (n < 20) üçün optimal — timsort daxilindən istifadə edir.
- Nearly-sorted array-lər üçün O(n) (best case).
- Online algorithm: elementi gəldikcə insert edə bilər.
- Constants çox kiçikdir — bu səbəbdən kiçik array-lərdə merge sort-dan sürətlidir.

### Tim Sort (Python + Java default):
- Merge sort + insertion sort hybrid.
- Real-world data üçün optimize edilmiş (çox vaxt partially sorted-dur).
- Python `list.sort()` və Java `Arrays.sort(Object[])` timsort.
- Java `Arrays.sort(int[])` — dual-pivot quicksort.
- "Run" — artıq sorted olan alt-sequence-lər tap, merge et.
- Practical performance: çox vaxt near O(n) real data üzərində.

### Non-Comparison Sorts:
- **Counting sort**: Element dəyərləri bilinən aralıqdadırsa (k), O(n+k). Stable. Negative sayılar üçün offset lazımdır.
- **Radix sort**: Rəqəm-by-rəqəm counting sort. O(nk) — n element, k rəqəm/bit. LSD (least significant first) vs MSD.
- **Bucket sort**: Range-ə görə bucket-lara böl, hər bucket-u sort et. Float sort üçün ideal.

### External Sort:
- Data yaddaşa sığmır — disk-based sort.
- Merge sort-un disk versiyası: chunk-ları yükle, sort et, disk-ə yaz, sonra merge.
- K-way merge: K chunk-u eyni anda priority queue ilə merge.

### Sort-a əsaslanan Problem-Solving:
- **Meeting rooms**: Başlanğıc saatına görə sort → O(n log n + n).
- **Merge intervals**: Sort + greedy merge.
- **Closest pair of points**: Sort by x, divide & conquer.
- **Largest number**: Custom comparator (`"9" + "34"` vs `"34" + "9"`).
- **H-index**: Sort descending + linear scan.

## Praktik Baxış

**Interview-a yanaşma:**
Sort sualı görəndə soruş: "Data tipi nədir? Range varmı? Stability vacibdirmi? Memory məhdudiyyəti varmı?". "Array-i sort etmən lazımdır" deyiləndə: built-in istifadə et (Python `sorted()`, Java `Arrays.sort()`), amma "niyə bu sort-u seçdin?" soruşulsa hazır ol.

**Nədən başlamaq lazımdır:**
- Built-in sort O(n log n) — müsabiqə deyilsə custom implement gərəksizdir.
- Stability lazımdırsa explicit qeyd et: Java `Collections.sort()` stable, `Arrays.sort(int[])` not stable.
- Custom comparator: Python `key=lambda x: x[1]` ya Java `Comparator`.
- "Bu sualda sort-a ehtiyac varmı?" — bəzən counting sort ya hash map daha yaxşıdır.

**Follow-up suallar:**
- "Merge sort-u implement edin."
- "Quicksort-un worst case-i nədir? Necə önləmək olar?"
- "Niyə timsort real-world-da daha sürətlidir?"
- "Integer sıralamaq üçün O(n) alqoritm varmı?"
- "1 million record-u disk-dən sort et (external sort)."
- "Stable sort nə vaxt vacibdir? Real nümunə verin."
- "QuickSelect nədir? Niyə sort-dan daha sürətlidir?"

**Namizədlərin ümumi səhvləri:**
- "Merge sort O(1) space-dir" demək — O(n) space.
- Quick sort-un always sorted array-də O(n²) olduğunu unutmaq.
- Stable vs unstable fərqini bilməmək.
- Comparison sort-un O(n log n) lower bound-unu açıqlamaq bilməmək.
- Custom sort üçün comparator düzgün yazmamaq (transitivity kıtması).
- Python `list.sort()` vs `sorted()` fərqini bilməmək: biri in-place, digəri yeni list.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Cədvəli əzbərdən bilir.
- Əla cavab: "Niyə bu sort bu data üçün daha yaxşıdır?" izah edə bilir, timsort-u bilir, O(n log n) lower bound-un sübut ideyasını bilir, real-world trade-off (quicksort vs merge sort) müzakirə edə bilir, QuickSelect-i bilir.

## Nümunələr

### Tipik Interview Sualı
"Merge sort-u implement edin. Time ve space complexity-ni izah edin. Niyə quicksort-dan daha az seçilir?"

### Güclü Cavab
"Merge sort: divide — array-i ortadan böl; conquer — hər yarını rekursiv sort et; combine — iki sorted array-i merge et. O(n log n) time guaranteed, O(n) auxiliary space. Praktikada quicksort daha çox seçilir çünki: cache-friendly (in-place), average case-i quicksort daha az constant factor ilə gəlir, in-place partition yaddaş allocation olmadan işləyir. Amma linked list-lər üçün ya stable sort lazım olduqda merge sort üstündür. External sort-da (disk-based) merge sort standartdır."

### Kod Nümunəsi
```python
# Merge Sort — O(n log n) time, O(n) space
def merge_sort(arr: list[int]) -> list[int]:
    if len(arr) <= 1:          # base case
        return arr
    mid = len(arr) // 2
    left = merge_sort(arr[:mid])    # divide
    right = merge_sort(arr[mid:])
    return merge(left, right)       # conquer + combine

def merge(left: list[int], right: list[int]) -> list[int]:
    result = []
    i = j = 0
    while i < len(left) and j < len(right):
        if left[i] <= right[j]:    # <= stability üçün
            result.append(left[i])
            i += 1
        else:
            result.append(right[j])
            j += 1
    result.extend(left[i:])
    result.extend(right[j:])
    return result

# Quick Sort — O(n log n) avg, O(n²) worst
import random
def quick_sort(arr: list[int], low: int, high: int) -> None:
    if low < high:
        pivot_idx = partition(arr, low, high)
        quick_sort(arr, low, pivot_idx - 1)
        quick_sort(arr, pivot_idx + 1, high)

def partition(arr: list[int], low: int, high: int) -> int:
    # Randomized pivot — worst case-i önləyir
    rand_idx = random.randint(low, high)
    arr[rand_idx], arr[high] = arr[high], arr[rand_idx]
    pivot = arr[high]
    i = low - 1
    for j in range(low, high):
        if arr[j] <= pivot:
            i += 1
            arr[i], arr[j] = arr[j], arr[i]
    arr[i + 1], arr[high] = arr[high], arr[i + 1]
    return i + 1

# Counting Sort — O(n+k), integers only, k = value range
def counting_sort(arr: list[int]) -> list[int]:
    if not arr:
        return arr
    max_val = max(arr)
    min_val = min(arr)
    offset = min_val   # negative sayılar üçün
    count = [0] * (max_val - min_val + 1)
    for num in arr:
        count[num - offset] += 1
    result = []
    for val, cnt in enumerate(count):
        result.extend([val + offset] * cnt)
    return result

# Custom sort — meeting rooms problem
def can_attend_all_meetings(intervals: list[list[int]]) -> bool:
    intervals.sort(key=lambda x: x[0])   # start time-a görə sort
    for i in range(1, len(intervals)):
        if intervals[i][0] < intervals[i-1][1]:   # overlap
            return False
    return True

# QuickSelect — K-th Largest in O(n) average
def find_kth_largest(nums: list[int], k: int) -> int:
    k = len(nums) - k  # k-th largest = (n-k)-th smallest

    def quick_select(low, high):
        pivot_idx = partition(nums, low, high)
        if pivot_idx == k:
            return nums[pivot_idx]
        elif pivot_idx < k:
            return quick_select(pivot_idx + 1, high)
        else:
            return quick_select(low, pivot_idx - 1)

    return quick_select(0, len(nums) - 1)
```

### İkinci Nümunə — Largest Number

**Sual**: Integer array-dən ən böyük rəqəmi formalaşdırın. `nums = [3, 30, 34, 5, 9]` → `"9534330"`.

**Cavab**: Custom comparator ilə sort. `a + b > b + a` olarsa `a` əvvəl gəlməlidir (string concatenation müqayisəsi).

```python
from functools import cmp_to_key

def largest_number(nums: list[int]) -> str:
    strs = list(map(str, nums))

    def compare(a, b):
        if a + b > b + a:   # string concatenation müqayisəsi
            return -1        # a əvvəl
        elif a + b < b + a:
            return 1         # b əvvəl
        return 0

    strs.sort(key=cmp_to_key(compare))
    result = ''.join(strs)
    return '0' if result[0] == '0' else result   # edge: [0,0] → "0"
```

## Praktik Tapşırıqlar
- LeetCode #912: Sort an Array (Medium) — merge sort implement et. Recursion tree çək.
- LeetCode #56: Merge Intervals (Medium) — sort + merge. Custom comparator.
- LeetCode #148: Sort List (Medium) — linked list merge sort. O(1) extra space.
- LeetCode #215: Kth Largest Element (Medium) — quickselect O(n) average.
- LeetCode #252: Meeting Rooms (Easy) — sort + linear scan.
- LeetCode #179: Largest Number (Medium) — custom comparator.
- Özünütəst: Counting sort, radix sort, bucket sort-u nə zaman seçərsən? 3 real scenario.
- Dərinləşdirmək: Java `Arrays.sort()` primitive vs object üçün fərqli alqoritm istifadə edir — niyə?

## Əlaqəli Mövzular
- **Binary Search** — binary search sorted array tələb edir.
- **Heap / Priority Queue** — heap sort-un alt strukturu.
- **Divide and Conquer** — merge sort və quicksort bu pattern.
- **Two Pointers Technique** — merge step-i two pointers ilə işləyir.
- **Dynamic Programming** — LIS (Longest Increasing Subsequence) patience sorting / binary search ilə O(n log n).
