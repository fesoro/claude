# Binary Search (Junior ⭐)

## İcmal
Binary search — sorted array-lərdə hər addımda axtarış sahəsini yarıya bölərək O(log n) zamanda axtarış aparan alqoritimdir. Sadə görünsə də, düzgün implement etmək (off-by-one, infinite loop) çox çətindir. Interview-larda binary search həm birbaşa soruşulur, həm də O(n log n) optimallaşdırma addımı kimi istifadə olunur.

## Niyə Vacibdir
Binary search log-linear barrier-ı: sorted data ilə işləyərkən O(n) scan-i görən interviewer dərhal "binary search edə bilərsiniz?" soruşacaq. O(n) → O(log n) fərqi n=10⁶ üçün ~20 dəfə daha az işdir. Real layihələrdə: database B-tree axtarışı, Git bisect, sorted configuration lookup, dictionary lookup. Google, Meta, Apple texniki interview-larında "variant binary search" (rotated array, answer-space binary search) çox çıxır.

## Əsas Anlayışlar

### Klassik Binary Search:
```
left = 0, right = n - 1
while left <= right:
    mid = left + (right - left) // 2    # overflow-safe
    if arr[mid] == target: TAPILDI
    elif arr[mid] < target: left = mid + 1
    else: right = mid - 1
```
- `left <= right` terminasiya şərti: left > right olanda element yoxdur.
- `right = n-1` — sonuncu element daxildir.
- Target olmadıqda `-1` ya da müvafiq "not found" return edir.

### Off-by-One Variantları:
- `while left <= right` — `right = n-1` ilə işləyir, target tapılmadıqda `left > right`.
- `while left < right` — adətən `right = n` ilə işlər, `left == right` cavabdır. Lower bound üçün ideal.
- **`mid = left + (right - left) // 2`** — integer overflow-u önləyir. `(left + right) // 2` 32-bit int-də left = right = INT_MAX/2 olduqda overflow edə bilər. Java-da xüsusilə vacib.
- `mid = left + (right - left + 1) // 2` — "upper mid" — `left = mid` update-i olan variantlarda infinite loop-u önləyir.

### Lower Bound vs Upper Bound:
- **Lower bound (bisect_left)**: Target-ə bərabər ya böyük olan ilk element index-i. "Sol olmayan ilk pozisiya."
- **Upper bound (bisect_right)**: Target-dən böyük olan ilk element index-i. "Sağ olmayan ilk pozisiya."
- Bu variant "neçə occurrence var?", "insert position haradadır?" suallarını həll edir.
- `count = upper_bound(target) - lower_bound(target)` — exact occurrence sayı.
- Python-da `bisect` module: `bisect.bisect_left(arr, target)`, `bisect.bisect_right(arr, target)`.
- Java-da `Arrays.binarySearch()` — tapılmadıqda `-(insertion_point + 1)` qaytarır.

### Rotated Sorted Array:
- `[4,5,6,7,0,1,2]` — bir dəfə rotate edilib. Rotation point haradasa.
- Binary search modifikasiyası: sol yarı sorted mi, sağ yarı sorted mi?
- `arr[left] <= arr[mid]` — sol yarı sorted-dir:
  - target bu aralıqdadırsa (`arr[left] <= target < arr[mid]`) → right = mid - 1 (sol yarıda axtar)
  - əks halda → left = mid + 1 (sağ yarıda axtar)
- `arr[left] > arr[mid]` — sağ yarı sorted-dir:
  - target bu aralıqdadırsa (`arr[mid] < target <= arr[right]`) → left = mid + 1 (sağ yarıda axtar)
  - əks halda → right = mid - 1 (sol yarıda axtar)
- `<=` şərtinin vacibliyi: `arr[left] == arr[mid]` halını düzgün idarə etmək.

### Answer Space Binary Search:
- Array-dəki element üzərində deyil, cavabın özü üzərində binary search.
- Pattern: "minimum X ki Y şərti ödənsin" ya da "maximum X ki Z şərti ödənsin."
- **Koko Eating Bananas**: Minimum eating speed ki H saatda bitirsin. Speed 1-dan max-a binary search.
- **Capacity to Ship Packages**: Minimum gəmi yükü ki D günə çatdırsın. Yük [max_weight, sum] aralığında binary search.
- Şərt: `feasible(x)` funksiyası monotonic olmalıdır — bütün x ≥ threshold üçün true, qabaqlar false. Ya da əksi.
- Real şirkət data: Git bisect — "hansı commit bug-ı yaratdı" binary search ilə O(log n) commit-də tapır.

### Peak Element:
- Sorted tələb yoxdur: `arr[mid] > arr[mid-1]` və `arr[mid] > arr[mid+1]` olarsa peak.
- Hər addımda böyük hissəyə get: `arr[mid] < arr[mid+1]` → peak sağdadır (left = mid + 1). Əks halda peak soldadır (right = mid).
- Garantiya: "Array-in sonlarına −∞ əlavə edirik" kimi düşünmək — ən azından bir peak mövcuddur.
- O(log n) time, O(1) space.

### Floating Point Binary Search:
- Real cavab axtararkən: precision `eps = 1e-9` qədər iterasiya et (30-50 iteration kifayət edir — daha çox deyil).
- "Square root of N" — binary search over float range [0, N].
- `while right - left > 1e-9: mid = (left + right) / 2`.
- `while` count-da 100 iteration da istifadə edilir — sabit count ilə precision garantiya olunur.

### Invariant Düşüncəsi:
- Binary search-in ən güclü tərəfi: **loop invariant** saxlamaq.
- "Cavab həmişə `[left, right]` aralığındadır" invariantını qoru.
- Hər addımda: "Bu update bu invariantı qoruyurmu?"
- `left = mid + 1` — mid artıq cavab olmaya bilər, left-i irəli çəkirik.
- `right = mid` — mid hələ də cavab ola bilər, right-ı mid-də saxlayırıq (xaric etmirik).
- Invariant pozulursa — left/right update-i səhvdir. Kağızda trace et.

### Template 1 vs Template 2 vs Template 3:
- **Template 1** (`left <= right`, `right = n-1`): Bir spesifik element axtararkən. Sadə. Loop bitəndə cavab tapılıb ya yoxdur. Postprocessing lazım deyil.
- **Template 2** (`left < right`, `right = n`): Lower bound, "first true" axtararkən. Loop bitəndə `left == right` — bu cavab mövqeyi. Postprocessing: `nums[left]` yoxla.
- **Template 3** (`left + 1 < right`): İki element qalır sonunda — manual yoxlama. "Peak element", "first bad version" kimi. Postprocessing: `nums[left]`, `nums[right]` hər ikisini yoxla.

### Binary Search Nə Vaxt İşləyir:
- Array (ya cavab space-i) sorted olmalıdır — ya da predicate monotonic.
- Predicate funksiyası monotonic olmalıdır: `false, false, ..., false, true, true, ..., true` (ya da əksi).
- "Birinci true olanı tap" ya "sonuncu false olanı tap" pattern-ləri.
- Cavab space sonlu və bounded olmalıdır.

### Binary Search Nə Vaxt İşLƏMƏZ:
- Unsorted array — əvvəlcə sort et O(n log n), sonra binary search O(log n) = total O(n log n).
- Predicate non-monotonic olduqda — əvvəl true, sonra false, sonra true — bölünmə mümkün deyil.
- Linked list — O(n) access time, binary search effektini tamamilə itirir.
- Hash map-da — sorted olmadığı üçün tətbiq olunmaz.

### 2D Matrix Binary Search:
- `m×n` matrix sorted row-by-row (hər sıra sorted, hər sıranın sonu növbəti sıranın başlanğıcından kiçik).
- Flat index: `mid = left + (right - left) // 2`, `row = mid // n`, `col = mid % n`.
- O(log(m×n)) time — sanki flat array kimi binary search.

## Praktik Baxış

### Interview-a Yanaşma:
"Array sorted-durmu?" — əgər bəli, binary search düşün. "Condition monotonic-dirmi?" (bütün `true`-lar bir tərəfdə, `false`-lar digərində) — answer space binary search. Binary search yazarkən hər zaman:
1. Loop condition-ı seç (`<=` vs `<`) — bu hər şeyi müəyyənləşdirir.
2. `mid = left + (right - left) // 2` — həmişə bu.
3. Loop invariant-ı sözlü izah et: "Cavab həmişə `[left, right]` aralığındadır."
4. Left/right update-lərini invariantı qoruyacaq şəkildə yaz.
5. Terminasiya zamanı nə qaytarmaq lazımdır?

### Kod Yazmadan Əvvəl:
- Template seçimini əsaslandır: "Exact search mi, lower bound mu, answer space mi?"
- Edge case-ləri yoxla: boş array, tek element, target daha kiçik/böyük.
- Infinite loop riski: `left = mid` ya da `right = mid` hər ikisi eyni anda istifadə edilirsə — "upper mid" seç.

### Follow-up Suallar:
- "Left-most ya right-most occurrence-ı necə taparsınız?" — lower bound / upper bound.
- "Array rotated-dursa binary search işləyərmi? Necə modify edərdiniz?" — sol/sağ yarı sorted check.
- "Minimum in rotated sorted array-i tapın." — rotation point = minimum.
- "Answer space binary search nədir? Bir nümunə verin." — Koko bananas, ship capacity.
- "Infinite sorted array-də binary search edə bilərsənmi? (Ölçü bilinmir)" — Exponential search: 1, 2, 4, 8, ... bounds tap, sonra binary search.
- "2D matrix-də bir elementi O(log(m*n))-də tapın." — flat index.
- "Template 1, 2, 3 arasındakı fərqləri izah edin." — loop condition, initial right, postprocessing.
- "Binary search-in worst case neçə iteration edir?" — log₂(n). n=10⁶ → 20 iteration.

### Namizədlərin Ümumi Səhvləri:
- `mid = (left + right) // 2` — integer overflow potential (Java-da int, böyük ədədlərdə).
- `right = mid` əvəzinə `right = mid - 1` — mid potensial cavabsa çıxarmaq olmaz.
- Loop condition-ı yanlış seçmək: `left <= right` əvəzinə `left < right` — son elementi qaçırmaq.
- Target olmadıqda return dəyəri unutmaq (`-1`, `None`, `len(arr)`).
- Off-by-one: "Insert position" sualında 1 artıq/az qaytarmaq.
- Answer space-də `feasible(mid)` funksiyasını yanlış yazmaq — monotonicity pozulur.
- Rotated array-də `arr[left] < arr[mid]` əvəzinə `arr[left] <= arr[mid]` unudulur — duplicate edge case.
- `left = mid` istifadə edərkən infinite loop: left = 0, right = 1, mid = 0, left = 0 → sonsuz loop. "Upper mid" ilə həll: `mid = left + (right - left + 1) // 2`.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən Nədir:
- **Yaxşı cavab**: Klassik binary search-i düzgün yazır.
- **Əla cavab**: Lower/upper bound variantlarını bilir, answer space binary search-i izah edə bilir, rotated array variant-ını həll edir, invariant düşüncəsini sözlü izah edir, template seçimini əsaslandırır, edge case-ləri əvvəlcədən yoxlayır.

## Nümunələr

### Tipik Interview Sualı
"Sorted array-də target-in index-ini qaytarın. Yoxdursa, insert olunacağı index-i qaytarın (array-i dəyişdirmədən). `nums = [1,3,5,6], target = 5` → 2. `target = 2` → 1. `target = 7` → 4."

### Güclü Cavab
"Bu Lower Bound (bisect_left) məsələsidir. Məqsəd: target-dən kiçik olmayan ilk elementin index-ini tapmaq. Loop invariantım: cavab həmişə `[left, right]` aralığındadır. `arr[mid] >= target` olduqda `right = mid` (mid hələ də cavab namizədidir, xaric etmirik), əks halda `left = mid + 1` (mid çox kiçikdir, namizəd deyil). `left == right` olduqda bu insert position-dur. Template 2 istifadə edirəm: `while left < right`, `right = n`. O(log n) time, O(1) space. Edge case: target array-in hamısından böyükdür — `left = n` qaytarır ki, bu da düzgün insert positiondur."

### Kod Nümunəsi
```python
# Klassik Binary Search — O(log n) time, O(1) space
def search(nums: list[int], target: int) -> int:
    left, right = 0, len(nums) - 1
    while left <= right:
        mid = left + (right - left) // 2   # overflow-safe hesab
        if nums[mid] == target:
            return mid
        elif nums[mid] < target:
            left = mid + 1
        else:
            right = mid - 1
    return -1   # tapılmadı

# Lower Bound — target-in ilk occurrence-ı ya insert position
# Template 2: while left < right, right = n (daxil)
def lower_bound(nums: list[int], target: int) -> int:
    left, right = 0, len(nums)   # right = n — boş array halını da tutur
    while left < right:
        mid = left + (right - left) // 2
        if nums[mid] < target:   # mid çox kiçik, namizəd deyil
            left = mid + 1
        else:                    # mid potensial cavab — saxla
            right = mid
    return left   # left == right — insert position ya birinci occurrence

# Upper Bound — target-dən böyük ilk element
def upper_bound(nums: list[int], target: int) -> int:
    left, right = 0, len(nums)
    while left < right:
        mid = left + (right - left) // 2
        if nums[mid] <= target:  # mid target-dən kiçik/bərabər, namizəd deyil
            left = mid + 1
        else:                    # mid potensial cavab
            right = mid
    return left

# First and Last Position — lower + upper bound kombinasiyası
def search_range(nums: list[int], target: int) -> list[int]:
    lb = lower_bound(nums, target)
    if lb == len(nums) or nums[lb] != target:
        return [-1, -1]   # target yoxdur
    ub = upper_bound(nums, target) - 1  # son occurrence indexi
    return [lb, ub]
# count = ub - lb + 1  →  upper_bound - lower_bound

# Search in Rotated Sorted Array — O(log n)
def search_rotated(nums: list[int], target: int) -> int:
    left, right = 0, len(nums) - 1
    while left <= right:
        mid = left + (right - left) // 2
        if nums[mid] == target:
            return mid
        # Sol yarının sorted olub-olmadığını yoxla
        if nums[left] <= nums[mid]:   # <= — bərabər halı üçün vacib
            # Sol yarı sorted: target bu aralıqdadırsa sol, yox sağa
            if nums[left] <= target < nums[mid]:
                right = mid - 1
            else:
                left = mid + 1
        else:
            # Sağ yarı sorted: target bu aralıqdadırsa sağ, yox sola
            if nums[mid] < target <= nums[right]:
                left = mid + 1
            else:
                right = mid - 1
    return -1

# Answer Space Binary Search — Koko Eating Bananas
def min_eating_speed(piles: list[int], h: int) -> int:
    def can_finish(speed: int) -> bool:
        # Bu sürətlə h saatda bitirə bilərikmi?
        hours = sum((p + speed - 1) // speed for p in piles)  # ceil division
        return hours <= h

    # Cavab 1-dən max(piles)-a qədər — minimum tapırıq
    left, right = 1, max(piles)
    while left < right:
        mid = left + (right - left) // 2
        if can_finish(mid):
            right = mid     # mid mümkündür, daha az sınayaq (saxla)
        else:
            left = mid + 1  # mid kifayət etmir, artır
    return left   # left == right — minimum feasible speed

# 2D Matrix Binary Search — O(log(m*n))
def search_matrix(matrix: list[list[int]], target: int) -> bool:
    if not matrix or not matrix[0]:
        return False
    m, n = len(matrix), len(matrix[0])
    left, right = 0, m * n - 1
    while left <= right:
        mid = left + (right - left) // 2
        row, col = mid // n, mid % n   # flat index → 2D index
        val = matrix[row][col]
        if val == target:
            return True
        elif val < target:
            left = mid + 1
        else:
            right = mid - 1
    return False

# Find Peak Element — sorted tələbi yoxdur — O(log n)
def find_peak_element(nums: list[int]) -> int:
    left, right = 0, len(nums) - 1
    while left < right:
        mid = left + (right - left) // 2
        if nums[mid] < nums[mid + 1]:
            # Sağa çıxış var, peak sağdadır
            left = mid + 1
        else:
            # nums[mid] >= nums[mid+1]: peak soldadır (mid daxil)
            right = mid
    return left   # left == right — peak pozisiyası
```

### İkinci Nümunə — Minimum in Rotated Sorted Array

**Sual**: Bir ya bir neçə dəfə rotate edilmiş sorted array-in minimumunu tapın. Duplicate yoxdur. `nums = [3,4,5,1,2]` → 1. `nums = [4,5,6,7,0,1,2]` → 0.

**Düşüncə prosesi**:
- Rotation point = minimum. Sağdakı element daima daha kiçikdir.
- `nums[mid] > nums[right]` → rotation point sağ yarıdadır (minimum sağda). `left = mid + 1`.
- `nums[mid] <= nums[right]` → sağ yarı sorted, minimum sol yarıdadır (mid daxil). `right = mid`.
- Loop bitdikdə `left == right` = minimum.

```python
def find_min(nums: list[int]) -> int:
    left, right = 0, len(nums) - 1

    # Artıq sorted-dursa (rotate olmayıb) direkt qaytar
    if nums[left] <= nums[right]:
        return nums[left]

    while left < right:
        mid = left + (right - left) // 2
        if nums[mid] > nums[right]:
            # Rotation point sağ yarıdadır — mid minimum ola bilməz
            left = mid + 1
        else:
            # Minimum sol yarıdadır (mid daxil — mid minimum ola bilər)
            right = mid
    return nums[left]   # left == right — minimum element

# Edge cases:
# [1] → nums[left] = 1 ✓
# [2,1] → nums[0]=2 > nums[1]=1 → left=1, return nums[1]=1 ✓
# [1,2,3] (not rotated) → nums[left] <= nums[right] → nums[0]=1 ✓
```

**İzah**: Klassik rotated array-in invariantı: minimum həmişə rotation point-in sağındadır. `nums[right]`-dan böyük olan `nums[mid]` — rotation point sağ yarıdadır. Əks halda sol yarıdadır.

## Praktik Tapşırıqlar
1. **LeetCode #704: Binary Search (Easy)** — klassik implement et. Bütün üç template-i yaz, fərqləri müzakirə et.
2. **LeetCode #35: Search Insert Position (Easy)** — lower bound. Off-by-one xəttini kağızda trace et.
3. **LeetCode #33: Search in Rotated Sorted Array (Medium)** — rotated variant. Hər branch üçün test case yaz.
4. **LeetCode #153: Find Minimum in Rotated Sorted Array (Medium)** — rotation point tapma.
5. **LeetCode #875: Koko Eating Bananas (Medium)** — answer space binary search. `feasible()` funksiyasının monotonicity-sini sübut et.
6. **LeetCode #410: Split Array Largest Sum (Hard)** — answer space. Çətin invariant. Minimum maximum partition sum.
7. **LeetCode #34: Find First and Last Position (Medium)** — lower + upper bound kombinasiyası. `count = ub - lb` formulası.
8. **LeetCode #162: Find Peak Element (Medium)** — sorted tələb yoxdur. Niyə O(log n)-dir?
9. **Özünütəst**: Binary search-in loop invariant-ını 3 template üçün sözlü izah et. Kod olmadan. "Hər addımda cavab `[left, right]` aralığındadır" invariantının necə qorunduğunu göstər.
10. **Tricky**: `[1,1,1,0,1,1,1]` — minimum tapın. Duplicate-li rotated array. Standard binary search işləyirmi? Niyə yox? Worst case O(n)-ə düşə bilir.

## Əlaqəli Mövzular
- **Sorting Algorithms Comparison** — binary search sorted array tələb edir; sort etmək O(n log n).
- **Two Pointers Technique** — sorted array-lərdə binary search-ə alternativ ola bilər.
- **Binary Search Tree** — BST-nin axtarışı binary search-in tree versiyasıdır.
- **Divide and Conquer** — binary search divide & conquer-in ən sadə nümunəsidir.
- **Dynamic Programming** — bəzən DP + binary search birlikdə (LIS O(n log n), patience sorting).
