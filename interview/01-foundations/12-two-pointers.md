# Two Pointers Technique (Middle ⭐⭐)

## İcmal
Two Pointers — eyni ya ayrı array/string üzərindəki iki pointer-ın hərəkəti ilə O(n²) yanaşmasını O(n)-ə endirən texnikadır. Pointer-lar ya eyni istiqamətdə (fast/slow), ya əks istiqamətdə (start/end), ya da iki ayrı array üzərindədir. Bu texnika sorted array, palindrome, pair sum, partition kimi məsələlərin standart həllidir.

## Niyə Vacibdir
Two pointers interviewer-lara namizədin O(n²) brute force-dan O(n) optimal hələ keçişi göstərən bir texnikadır. Amazon, Google, Meta easy-medium leetcode suallarının böyük qismini bu texnika ilə həll etmək mümkündür. Praktikdə: merge sort-un merge addımı, sliding window-un əsası, partition alqoritmi (quicksort) — hamısı two pointers istifadə edir.

## Əsas Anlayışlar

### Two Pointers Variantları:

**1. Opposite Direction (Ziddə doğru):**
- `left = 0`, `right = n-1`.
- Bir şərtə görə biri sola, digəri sağa gedir.
- Sorted array-lərdə pair tapmaq üçün.
- Palindrome check, two sum (sorted), container with most water.

**2. Same Direction (Eyni istiqamət — Fast/Slow):**
- `slow` və `fast` hər ikisi soldan başlayır.
- `fast` daha sürətli irəliləyir.
- Remove duplicates, linked list cycle, middle of list.

**3. Two Arrays:**
- Hər pointer ayrı array-dədir.
- Merge two sorted arrays, intersections, comparing sequences.

### Opposite Direction Nə Zaman:
- Array sorted-dur.
- İki elementin cəmi/fərqi/nisbəti tapılır.
- Reverse ya palindrome check.
- Container with most water kimi "boundary" problemləri.
- Trapping rain water (advanced variant).

### Same Direction Nə Zaman:
- Dublikatları silmək (in-place).
- Target deyil elementin üzərindən keçmək (remove elements).
- Linked list-in middle-ı ya ya cycle tapılır.
- Sliding window başlanğıcı (window-u sıxmaq lazım olduqda).
- Write pointer + read pointer pattern.

### Container With Most Water — Greedy Two Pointers:
- `left`, `right` pointer-ları başlayır.
- Area = `min(height[left], height[right]) * (right - left)`.
- Daha kiçik hündürlüyü olan pointer içəriyə gedir — çünki kiçik olanı tutduğumuz müddətcə area artmaz.
- Greedy argument: "kiçik pointer-ı içəriyə aparmaq heç bir potensial optimal həlli qaçırmır."
- O(n) time, O(1) space.

### Two Sum Sorted — Binary Search Alternativ:
- `left = 0`, `right = n-1`.
- `sum = nums[left] + nums[right]`.
- `sum == target` → tapıldı.
- `sum < target` → `left++` (daha böyük ədəd lazımdır).
- `sum > target` → `right--`.
- O(n) time, O(1) space. Hash map-dən daha az yaddaş.

### 3Sum — Sort + Two Pointers:
- Önce sort et: O(n log n).
- Hər element üçün (i), qalan iki element two pointers ilə tap: O(n).
- Toplam: O(n²) — optimal (bütün tripletleri saymaq O(n²) çıxış tələb edir).
- Dublikat atlamak: `if i > 0 and nums[i] == nums[i-1]: continue`.
- Left/right dublikat atlama da vacibdir — unique triples üçün.

### 4Sum Extension:
- 3Sum-un genişləndirilməsi: İki loop + two pointers.
- O(n³) time — n + n×two_pointers.
- Dublikat atlamaq: hər outer loop-da `if j > i+1 and nums[j] == nums[j-1]: continue`.

### In-Place Remove / Partition:
- `write_pointer = 0` (slow).
- `read_pointer = 0` (fast).
- Fast irəliləyir, şərtə uyğun elementləri slow-un yerinə yaz.
- Quicksort partition-ı bu pattern.
- "Remove Element": `if nums[fast] != val: nums[slow] = nums[fast]; slow++`.

### Dutch National Flag (3-way Partition):
- `[0, ..., low-1]`: 0-lar, `[low, ..., mid-1]`: 1-lər, `[mid, ..., high]`: 2-lər.
- Üç pointer: `low`, `mid`, `high`.
- `mid == 0` → swap with `low`, low++, mid++.
- `mid == 1` → mid++.
- `mid == 2` → swap with `high`, high--. Mid-i artırmırıq — yeni element yoxlanmalı.
- LeetCode Sort Colors problemi.

### Palindrome Check — Two Pointers:
- `left = 0`, `right = n-1`.
- `s[left] == s[right]` → hər ikisini içəriyə gətir.
- Yoxdursa palindrome deyil.
- Alphanumeric filter ilə: non-alphanumeric hərfləri skip et.
- "Valid Palindrome II": bir hərf silmək olar — biri çıxardıqda palindrome yoxla.

### Trapping Rain Water:
- Two pointer approach: `left`, `right`.
- `left_max`, `right_max` saxla.
- Kiçik tərəfdə su toplanır: `water = max_side - height[pointer]`.
- O(n) time, O(1) space.

### Merge Two Sorted Arrays:
- `p1` birinci array-in sonunda, `p2` ikinci array-in sonunda.
- Böyüyü arxadan doldur — əks halda override problem.
- O(n+m) time, O(1) space (əgər result array-i büyük array-in özüdürsə).

### Minimum Size Subarray Sum:
- Two pointer / sliding window: sum >= target olduqda left irəlilə.
- O(n) time, O(1) space.
- Binary search variant: O(n log n) — sorted prefix sum üzərindən.

## Praktik Baxış

**Interview-a yanaşma:**
Two pointers-a keçmək üçün siqnallar: "Sorted array", "pair/triplet tapmaq", "in-place dəyişiklik", "subarray/substring", "palindrome". Brute force O(n²)-dən danışarkən "two pointers ilə O(n)-ə endirə bilərəm" — bu cümlə interviewer-ı xoşlandırır.

**Nədən başlamaq lazımdır:**
- Pointer-ların başlanğıc mövqeyini müəyyən et (karşıdan mı, eyni istiqamətdə mi?).
- Hərəkət şərtini müəyyən et: "Hansı pointer nə zaman hərəkət edir?".
- Loop termination: `left < right` (opposite) ya `fast < n` (same direction).
- Edge cases: boş array, tək element, bütün elementlər eyni, bütün elementlər sıfır.

**Follow-up suallar:**
- "3Sum-u O(n²)-dən yaxşı edə bilərsənmi? (Xeyr — O(n²) optimal-dır.)"
- "Bu həllin space complexity-si nədir?"
- "Unsorted array olsaydı nə dəyişərdi?"
- "Fast/slow pointer-ı niyə istifadə etdiniz linked list-də?"
- "Container with most water-da niyə kiçik pointer-ı içəriyə apardınız?"
- "4Sum problemi üçün alqoritmini genişləndir."
- "Dutch National Flag-i izah et. 3 pointer niyə lazımdır?"

**Namizədlərin ümumi səhvləri:**
- Opposite direction üçün sorted olmayan array-ə two pointers tətbiq etmək.
- Loop condition `<` əvəzinə `<=` yazmaq — off-by-one.
- 3Sum-da dublikatları atlamağı unutmaq → duplike triples.
- Container with most water-da "kiçik pointer içəriyə" intuisiyasını izah edə bilməmək.
- Two pointer-ı yanlış istiqamətdə hərəkət etdirmək.
- Dutch National Flag-da `mid` artırılmadığını unutmaq (2 görüncə).

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Two pointers ilə düzgün nəticə verir.
- Əla cavab: Niyə bu pattern-in işlədiyini isbat edə bilir (container with most water greedy argument), sorted tələbini vurğulayır, same vs opposite direction fərqini izah edir, edge case-ləri əhatə edir, space complexity-ni müzakirə edir.

## Nümunələr

### Tipik Interview Sualı
"Sorted integer array-i verilmişdir. İki element tapın ki, cəmləri `target`-ə bərəbər olsun. Index-ləri qaytarın (1-indexed). Düz bir cavab var. `numbers = [2,7,11,15], target = 9` → `[1,2]`."

### Güclü Cavab
"Sorted array olduğu üçün two pointers tətbiq edirəm. `left = 0`, `right = n-1`. Cəm target-dən kiçikdirsə `left++` (daha böyük ədəd lazımdır), böyükdürsə `right--`. Tapıldıqda qaytarıram. O(n) time, O(1) space. Hash map ilə O(n) time, O(n) space da mümkündür — amma sorted array-də two pointers daha effektiv yaddaş istifadə edir."

### Kod Nümunəsi
```python
# Two Sum II — Sorted — O(n) time, O(1) space
def two_sum_sorted(numbers: list[int], target: int) -> list[int]:
    left, right = 0, len(numbers) - 1
    while left < right:
        total = numbers[left] + numbers[right]
        if total == target:
            return [left + 1, right + 1]   # 1-indexed
        elif total < target:
            left += 1    # daha böyük ədəd lazımdır
        else:
            right -= 1   # daha kiçik ədəd lazımdır
    return []

# 3Sum — Sort + Two Pointers — O(n²)
def three_sum(nums: list[int]) -> list[list[int]]:
    nums.sort()
    result = []
    for i in range(len(nums) - 2):
        if i > 0 and nums[i] == nums[i-1]:    # xarici dublikat atla
            continue
        left, right = i + 1, len(nums) - 1
        while left < right:
            total = nums[i] + nums[left] + nums[right]
            if total == 0:
                result.append([nums[i], nums[left], nums[right]])
                while left < right and nums[left] == nums[left+1]:
                    left += 1          # sol dublikat atla
                while left < right and nums[right] == nums[right-1]:
                    right -= 1         # sağ dublikat atla
                left += 1
                right -= 1
            elif total < 0:
                left += 1
            else:
                right -= 1
    return result

# Container With Most Water — O(n)
def max_area(height: list[int]) -> int:
    left, right = 0, len(height) - 1
    max_water = 0
    while left < right:
        water = min(height[left], height[right]) * (right - left)
        max_water = max(max_water, water)
        if height[left] < height[right]:
            left += 1     # kiçik olanı içəriyə gətirir
        else:
            right -= 1
    return max_water

# Remove Duplicates from Sorted Array — In-place — O(n)
def remove_duplicates(nums: list[int]) -> int:
    if not nums:
        return 0
    write = 1   # slow pointer — yazma mövqeyi
    for read in range(1, len(nums)):    # fast pointer
        if nums[read] != nums[read - 1]:   # yeni element
            nums[write] = nums[read]
            write += 1
    return write   # unique elementlərin sayı

# Sort Colors (Dutch National Flag) — O(n), O(1) space
def sort_colors(nums: list[int]) -> None:
    low, mid, high = 0, 0, len(nums) - 1
    while mid <= high:
        if nums[mid] == 0:
            nums[low], nums[mid] = nums[mid], nums[low]
            low += 1; mid += 1
        elif nums[mid] == 1:
            mid += 1
        else:   # nums[mid] == 2
            nums[mid], nums[high] = nums[high], nums[mid]
            high -= 1   # mid artırmırıq — yeni element yoxlanmalı

# Trapping Rain Water — Two Pointers — O(n)
def trap(height: list[int]) -> int:
    left, right = 0, len(height) - 1
    left_max = right_max = 0
    water = 0
    while left < right:
        if height[left] < height[right]:
            if height[left] >= left_max:
                left_max = height[left]   # yeni max
            else:
                water += left_max - height[left]   # su toplanır
            left += 1
        else:
            if height[right] >= right_max:
                right_max = height[right]
            else:
                water += right_max - height[right]
            right -= 1
    return water

# Merge Sorted Array — Reverse Two Pointers — O(m+n)
def merge(nums1: list[int], m: int, nums2: list[int], n: int) -> None:
    p1, p2, write = m - 1, n - 1, m + n - 1
    while p1 >= 0 and p2 >= 0:
        if nums1[p1] > nums2[p2]:
            nums1[write] = nums1[p1]
            p1 -= 1
        else:
            nums1[write] = nums2[p2]
            p2 -= 1
        write -= 1
    # Nums2-nin qalanı (nums1 artıq öz yerindədir)
    nums1[:p2+1] = nums2[:p2+1]
```

### İkinci Nümunə — Valid Palindrome II

**Sual**: String verilmişdir. Ən çox bir hərf silib palindrome etmək mümkündürmü? `s = "abca"` → true (sil "c" ya "b").

**Cavab**: Opposite two pointers. Uyğunsuzluq olduqda: ya sol, ya sağ elementi sil, qalan hissə palindrome yoxla.

```python
def valid_palindrome(s: str) -> bool:
    def is_palindrome(l, r):
        while l < r:
            if s[l] != s[r]:
                return False
            l += 1; r -= 1
        return True

    left, right = 0, len(s) - 1
    while left < right:
        if s[left] != s[right]:
            # Bir elementi sil — iki variant yoxla
            return is_palindrome(left+1, right) or is_palindrome(left, right-1)
        left += 1
        right -= 1
    return True
```

## Praktik Tapşırıqlar
- LeetCode #167: Two Sum II — Input Array is Sorted (Medium) — klassik opposite direction.
- LeetCode #11: Container With Most Water (Medium) — greedy argument. Niyə işlədiyini izah et.
- LeetCode #15: 3Sum (Medium) — sort + two pointers. Dublikat atlamaq çətin hissədir.
- LeetCode #26: Remove Duplicates from Sorted Array (Easy) — same direction write/read.
- LeetCode #75: Sort Colors (Medium) — Dutch National Flag. 3 pointer.
- LeetCode #125: Valid Palindrome (Easy) — opposite direction + alphanumeric filter.
- LeetCode #283: Move Zeroes (Easy) — same direction partition. Sıfırları arxaya apar.
- LeetCode #42: Trapping Rain Water (Hard) — two pointer ya stack ya prefix max.
- Özünütəst: 4Sum məsələsini 3Sum-un genişləndirilməsi kimi həll et — nə əlavə etmək lazımdır?

## Əlaqəli Mövzular
- **Sliding Window Technique** — two pointers-ın dynamic window variantı.
- **Binary Search** — sorted array-də pair axtarışına alternativ.
- **Sorting Algorithms** — quicksort partition two pointers ilə işləyir.
- **Linked List** — fast/slow pointer cycle detection.
- **Array and String Fundamentals** — two pointers array manipulation-ın çoxunda istifadə olunur.
