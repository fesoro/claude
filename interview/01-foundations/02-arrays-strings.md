# Array and String Fundamentals (Junior ⭐)

## İcmal
Array — eyni tipli elementlərin ardıcıl yaddaş bloklarında saxlandığı ən fundamental data structure-dur. String isə əksər dillərdə ya char array-ı, ya da immutable byte sequence-dir. Bu mövzu interview-larda ən çox rast gəlinən mövzudur — LeetCode-da "Easy" səviyyəsindən "Hard"-a qədər sualların böyük hissəsi array manipulyasiyasını əhatə edir.

## Niyə Vacibdir
Array-lər bütün data structure-ların təməlini təşkil edir — hash map, stack, queue, heap bunların hamısı alt qatda array-ə əsaslanır. Interviewer array sualları ilə namizədin index manipulation, edge case handling (boş array, tək element, overflow), in-place əməliyyatlar kimi temel bacarıqlarını ölçür. Google, Meta, Amazon kimi şirkətlərdə ilk round-da ən azı bir array sualı olur. Backend developer kimi real layihələrdə də — bulk data processing, pagination, filtering — array operation-ları daima işlədilir.

## Əsas Anlayışlar

### Memory Layout:
- **Contiguous memory**: Array elementləri yaddaşda ardıcıl yerləşir, buna görə index-lə müraciət O(1)-dir. `arr[i]` = `base_address + i × element_size`.
- **Cache-friendly**: Ardıcıl yaddaş adreslərinə müraciət CPU cache-dən yaxşı istifadə edir (spatial locality). Bu həqiqi axtarışı array-i linked list-dən 5-10x daha sürətli edir.
- **Random access**: Hər elementə O(1)-də çatmaq linked list-lə mümkün deyil (O(n) traverse lazımdır).
- **Pointer arithmetic**: C/C++-da `*(arr + i)` == `arr[i]`. Yüksək səviyyəli dillərdə bu gizlədilmişdir.

### Static vs Dynamic Array:
- **Static array**: Ölçüsü compile time-da sabitdir. C-dəki `int arr[10]`. Overflow halında undefined behavior (C) ya da exception.
- **Dynamic array** (Python `list`, Java `ArrayList`, Go `slice`): Dolu olduqda yeni, daha böyük yaddaş bloku ayrılır, elementlər köçürülür. **Amortized O(1) append**, worst case O(n) (resize zamanı).
- **Resize əmsalı**: Java `ArrayList` → ×1.5, CPython `list` → ~×1.125, Go slice → ×2. Bu fərq nadir olsa da praktik performansa təsir edir.
- **Java `ArrayList` vs `LinkedList`**: 90%+ hallarda `ArrayList` daha sürətlidir — cache locality fərqi. `LinkedList` yalnız çox tez-tez ortadan insert/delete lazım olanda üstündür.

### Vacib Əməliyyatlar və Komplekslikləri:
- **Access by index**: O(1) — random access array-in əsas üstünlüyüdür.
- **Search (unsorted)**: O(n) — linear scan lazımdır.
- **Search (sorted)**: O(log n) — binary search tətbiq oluna bilər.
- **Insert at end**: O(1) amortized (dynamic array). Resize olmadıqda O(1), resize zamanı O(n).
- **Insert at beginning/middle**: O(n) — elementlər sürüşdürülməlidir (shift right).
- **Delete at end**: O(1).
- **Delete at beginning/middle**: O(n) — elementlər sürüşdürülməlidir (shift left).
- **Reverse**: O(n) time, O(1) space (in-place two pointers ilə).

### String Xüsusiyyətləri:
- **Immutability**: Java `String`, Python `str` — dəyişilməzdir. Hər dəyişiklik yeni obyekt yaradır. `s = "hello"; s[0] = 'H'` → error (Python-da).
- **String concatenation trap**: Loop içində `str += char` → O(n²). Çünki hər `+` yeni string allocation + copy edir. Həll: `StringBuilder` (Java) ya list-join (Python).
- **Character encoding**: ASCII (128 char, 7-bit), Extended ASCII (256, 8-bit), Unicode (UTF-8 variable-length, UTF-16 2-4 bytes). Interview-da adətən ASCII fərz edilir — 26 hərflə işləyirik.
- **String slicing**: Python-da `s[i:j]` yeni string yaradır — O(j-i) time, O(j-i) space. `s[:]` isə tam kopyası — O(n).
- **String comparison**: İki string-in equality check-i O(min(len1, len2)) — hər hərf müqayisəsi lazımdır.
- **Java `String.equals()` vs `==`**: `equals()` content müqayisəsi, `==` reference (pointer) müqayisəsi. Interview-da bu fərq çox vacibdir — `==` ilə iki fərqli String obyekti "false" qaytarar, hətta məzmunları eyni olsalar belə.

### In-place Manipulation:
- In-place əməliyyatlar O(1) auxiliary space istifadə edir — extra array yaratmadan.
- **Array reverse**: Two pointers — left, right. Swap edərək ortaya doğru get.
- **Array rotate**: Üç dəfə reverse texnikası ilə O(n) time, O(1) space.
- **Remove duplicates**: İki pointer — slow (unique), fast (current). Fast dəyişdikdə slow-a yaz.
- In-place edərkən data itirməmək üçün sıra vacibdir — hansı pointer hansı istiqamətdə?

### Off-by-One Error:
- `arr[0]` ilk element, `arr[n-1]` sonuncu element. `arr[n]` → IndexError.
- Loop şərtlərində `< n` vs `<= n-1` fərqi — eyni şeydir, amma qarışdırılır.
- Slice-larda `arr[i:j]` — `i` daxil, `j` xaric (Python, Go). `arr[0:3]` → indeks 0, 1, 2.
- **Binary search-in ən çox xəta mənbəyi**: `left + 1` vs `left`, `right - 1` vs `right` seçimi.
- Fence-post problem: n element üçün n-1 boşluq. Divar tikərkən: 10 metr üçün neçə dirək?

### Multi-dimensional Arrays:
- **2D array**: Matrix. `arr[row][col]` — row-major sıralama (C, Java, Python).
- **Row-by-row traverse cache-friendly-dir**: `arr[0][0], arr[0][1], ..., arr[1][0]` — ardıcıl yaddaş.
- **Column-by-column traverse cache-unfriendly**: `arr[0][0], arr[1][0], ...` — yaddaşda uzaq yerlər atlanır.
- **Flatten**: 2D array-i 1D-yə: `index = row * cols + col`. Geri: `row = index // cols, col = index % cols`.
- **Matrix rotation 90° clockwise**: Transpose + reverse each row. O(n²) time, O(1) space.
- **Matrix spiral order**: Boundary shrinking — top/right/bottom/left pointers.
- **Matrix diagonal traverse**: İki pointer ya da direction flag ilə.

### Common Patterns:

**Prefix Sum**:
- Subarray sum-larını O(1)-də hesablamaq üçün. `prefix[i] = sum(arr[0..i-1])`.
- `sum(arr[l..r]) = prefix[r+1] - prefix[l]` — O(1) query.
- 2D prefix sum: `dp[i][j] = arr[i-1][j-1] + dp[i-1][j] + dp[i][j-1] - dp[i-1][j-1]`.
- Subarray count, range query, equilibrium index problemlərinin standart həlli.

**Suffix Sum**:
- Sağdan prefix sum. Product except self, minimum suffix kimi suallar üçün.

**Sliding Window**:
- Fixed window: `sum of size k subarray` — window-u bir addım sürüşdür, ilk elementi çıxar, yeni elementi əlavə et.
- Variable window: `longest substring without repeating characters` — iki pointer, şərt pozulduqda sol sıx.
- Xarici loop O(n), daxili loop amortized O(1) → overall O(n).

**Two Pointers**:
- Sorted array-də pair-lər tapmaq. O(n) ilə O(n²) brute force-u əvəzlər.
- Left, right dan ortaya: pair sum, palindrome check, container with most water.
- Slow, fast: cycle detection, middle of linked list, remove nth from end.

**Kadane's Algorithm**:
- Maximum subarray sum: `current_sum = max(num, current_sum + num)`.
- "Reset ya da davam et" qərarı — hər elementdə. O(n) time, O(1) space.
- DP-nin ən sadə nümunəsi.

**Counting/Frequency Array**:
- ASCII char-lar üçün `[0]*26` — O(1) space sayılır (sabit ölçü, n-dən asılı deyil).
- Anagram check, character frequency, ransom note problemlərinin standart həlli.

### Array Rotation Patterns:
- **K mövqe sağa rotate**: Reverse first n-k, then last k, then all. O(n) time, O(1) space.
- **K mövqe sola rotate**: Reverse first k, then last n-k, then all.
- Modulo ilə: `new_index = (old_index + k) % n`. k ≥ n olduqda `k = k % n`.

### Interview-da Xüsusi Array Sualları:
- **Product of array except self**: Division olmadan prefix + suffix product. O(n) time, O(1) extra space (output-u saymadıqda).
- **Maximum subarray**: Kadane's — current_sum = max(num, current_sum + num).
- **Find missing number**: Gauss formula `n*(n+1)/2 - sum(arr)` ya da XOR trick — O(n) time, O(1) space.
- **Find duplicate**: Floyd's cycle detection (index-as-pointer) — O(n) time, O(1) space.
- **Merge intervals**: Sort by start + merge overlapping — O(n log n) sort + O(n) merge.
- **Dutch National Flag (3-way partition)**: 3 pointer — low, mid, high. Red-White-Blue sort.

## Praktik Baxış

### Interview-a Yanaşma:
Array sualına başlamadan əvvəl bu sualları soruş — bu həm edge case-ləri önləyir, həm də interviewer-a düşünülü bir namizəd təəssüratı yaradır:
- "Array sorted-durmu?" — bəli isə binary search, two pointer imkanları açılır.
- "Duplicate ola bilərmi?" — unikal fərz edilirmi?
- "Negative ədəd ola bilərmi?" — sum, product problemlərini əhəmiyyətli dərəcədə dəyişir.
- "Input boş ola bilərmi?" — `if not arr: return ...` ilk sətir olmalıdır.
- "In-place lazımdırmı?" — extra space istifadə etmək olarmı?
- "Output array ayrı olacaqmı?" — modify vs yeni array.

### Nədən Başlamaq Lazımdır:
1. Brute force həlli izah et — həmişə birinci. "İki nested loop ilə hər cütü yoxlayaram — O(n²)."
2. Kompleksliyini analiz et — "Bu O(n²) time, O(1) space-dir."
3. Bottleneck-i tap — "Daxili loop nə edir? Hash map ilə O(1)-ə endirə bilərəmmi?"
4. Optimization axtarma: Hash map, sorting, two pointers, prefix sum texnikalarından birini seç.
5. Space vs time trade-off-u müzakirə et — "O(n) space istifadə edərək O(n²)-i O(n)-ə çevirirəm."

### Follow-up Suallar:
- "Bu həlli in-place edə bilərsənmi?" — extra array olmadan.
- "Sorted array olsaydı, daha yaxşı edə bilərsənmi?" — binary search, two pointer.
- "Streaming data olsaydı (array-in hamısı yaddaşda olmasaydı) nə edərdiniz?" — sliding window, online algorithm.
- "N çox böyük olsa (10⁸ element), bu həll işləyərmi?" — RAM limit, disk-based processing.
- "2D matrix üçün bu alqoritmi necə adapt edərdiniz?" — row + column iteration.
- "Multiple arrays varsa (k arrays) nə dəyişərdi?" — merge k sorted arrays kimi suallar.
- "Həll parallelləşdirilə bilərmi?" — Map-reduce, partial sums.
- "Integer overflow ola bilərmi?" — sum, product hesablamada overflow ehtiyatı.

### Namizədlərin Ümumi Səhvləri:
- Boş array edge case-ini unudur: `if not arr: return ...` — bu mütləq lazımdır.
- String concatenation-da O(n²) yaratmaq — loop içində `+=` istifadə.
- Off-by-one: `arr[n]` — index out of bounds. Slice-da `arr[i:j]`-da j-nin xaric olduğunu unutmaq.
- In-place dəyişiklik edərkən original-ı korlamaq — hər iki pointer eyni istiqamətdə sürüşdürülür.
- Multi-dimensional array-də row/column qarışdırmaq — matrix-i transposed yazmaq.
- Prefix sum array-ini `n+1` ölçüdə qurmağı unutmaq — `prefix[0] = 0` sentinel vacibdir.
- `nums.sort()` ilə `sorted(nums)` fərqini bilməmək — biri in-place (None qaytarır), digəri yeni list.
- Sliding window-da sağ pointer əvəzinə sol pointeri sürüşdürmək — şərt pozulduqda hansı hərəkət edir?
- Dutch National Flag-da 3 pointer-ın invariantını pozmaq.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən Nədir:
- **Yaxşı cavab**: Doğru nəticə verir, bəlkə O(n²).
- **Əla cavab**: Brute force-dan başlayır, niyə optimal olmadığını izah edir, hash map / prefix sum ilə O(n)-ə optimize edir, in-place seçim varsa onu da müzakirə edir, edge case-ləri əvvəlcədən soruşur, time-space trade-off-u şüurlu olaraq seçir.

## Nümunələr

### Tipik Interview Sualı
"Sıralanmamış bir `nums` integer array-i verilmişdir. Array-də `target` cəminə bərəbər olan iki elementin index-lərini qaytarın. Eyni element iki dəfə istifadə edilə bilməz. Tam olaraq bir cavab mövcuddur."

### Güclü Cavab
"İlk öncə brute force həlli düşünürəm: iki nested loop ilə hər cütü yoxlayıram — O(n²) time, O(1) space. Amma bu böyük input-lar üçün çox yavaşdır. Optimize edəcəm: hash map istifadə edərək hər element üçün `target - num`-un əvvəl görülüb-görülmədiyini yoxlaya bilərəm. Bu O(n) time, O(n) space verir. Trade-off: əlavə yaddaş istifadə edirik, amma dramatik sürət qazanırıq. Edge case: eyni element iki dəfə istifadə edilə bilməz — buna görə value əvəzinə index saxlayıram."

### Kod Nümunəsi
```python
# Two Sum — Brute Force: O(n²) time, O(1) space
def two_sum_brute(nums: list[int], target: int) -> list[int]:
    for i in range(len(nums)):
        for j in range(i + 1, len(nums)):  # i+1: eyni element yoxlanmır
            if nums[i] + nums[j] == target:
                return [i, j]
    return []

# Two Sum — Optimal: O(n) time, O(n) space
def two_sum(nums: list[int], target: int) -> list[int]:
    seen = {}  # {value: index}
    for i, num in enumerate(nums):
        complement = target - num
        if complement in seen:
            return [seen[complement], i]  # tapdıq
        seen[num] = i  # hələ tapılmadı, yadda saxla
    return []  # heç bir cüt yoxdur

# Array Reverse — O(n) time, O(1) space (in-place two pointers)
def reverse_array(arr: list) -> None:
    left, right = 0, len(arr) - 1
    while left < right:
        arr[left], arr[right] = arr[right], arr[left]
        left += 1
        right -= 1

# Prefix Sum qurmaq — O(n) time, O(n) space
def build_prefix(nums: list[int]) -> list[int]:
    prefix = [0] * (len(nums) + 1)   # prefix[0] = 0 sentinel
    for i, num in enumerate(nums):
        prefix[i + 1] = prefix[i] + num
    return prefix

def range_sum(prefix: list[int], left: int, right: int) -> int:
    # nums[left..right] cəmi — O(1) query
    return prefix[right + 1] - prefix[left]

# String: StringBuilder pattern — O(n) deyil O(n²) olmasın
def build_string_bad(chars: list[str]) -> str:
    result = ""
    for c in chars:
        result += c  # Her + yeni string yaradır — O(n²)!
    return result

def build_string_good(chars: list[str]) -> str:
    return "".join(chars)  # O(n) — list join

# Kadane's Algorithm — Maximum Subarray Sum — O(n) time, O(1) space
def max_subarray(nums: list[int]) -> int:
    if not nums:
        return 0
    max_sum = current_sum = nums[0]
    for num in nums[1:]:
        # Reset ya da davam et:
        current_sum = max(num, current_sum + num)
        max_sum = max(max_sum, current_sum)
    return max_sum

# Product of Array Except Self — O(n) time, O(1) extra space
def product_except_self(nums: list[int]) -> list[int]:
    n = len(nums)
    result = [1] * n
    # Sol tərəfdən prefix product
    prefix = 1
    for i in range(n):
        result[i] = prefix
        prefix *= nums[i]
    # Sağ tərəfdən suffix product — result-a vur
    suffix = 1
    for i in range(n - 1, -1, -1):
        result[i] *= suffix
        suffix *= nums[i]
    return result

# Sliding Window — Longest Subarray with Sum ≤ k
def longest_subarray(nums: list[int], k: int) -> int:
    left = 0
    current_sum = 0
    max_len = 0
    for right in range(len(nums)):
        current_sum += nums[right]
        while current_sum > k:
            current_sum -= nums[left]
            left += 1
        max_len = max(max_len, right - left + 1)
    return max_len

# Find Missing Number — Gauss Formula — O(n) time, O(1) space
def missing_number(nums: list[int]) -> int:
    n = len(nums)
    expected_sum = n * (n + 1) // 2   # Gauss: 0+1+2+...+n
    return expected_sum - sum(nums)

# Dutch National Flag — Sort 0s, 1s, 2s in place
def sort_colors(nums: list[int]) -> None:
    low, mid, high = 0, 0, len(nums) - 1
    while mid <= high:
        if nums[mid] == 0:
            nums[low], nums[mid] = nums[mid], nums[low]
            low += 1; mid += 1
        elif nums[mid] == 1:
            mid += 1
        else:  # nums[mid] == 2
            nums[mid], nums[high] = nums[high], nums[mid]
            high -= 1
            # mid artırmırıq — swap edilən yoxlanmamış ola bilər
```

### İkinci Nümunə — Rotate Array

**Sual**: `nums` array-ni `k` mövqe sağa rotate edin. In-place, O(1) extra space. `nums = [1,2,3,4,5,6,7], k = 3` → `[5,6,7,1,2,3,4]`.

**Düşüncə prosesi**:
- Brute force: Hər dəfə bütün array-i bir sürüş — O(n×k) time, O(1) space. k = n/2 olduqda O(n²).
- Extra array: Yeni array-ə `(i+k)%n` index-ə yaz — O(n) time, O(n) space.
- Üç reverse texnikası: O(n) time, O(1) space — ən optimal.

**Üç reverse necə işləyir?**
`[1,2,3,4,5,6,7], k=3`:
1. Bütün reverse: `[7,6,5,4,3,2,1]`
2. İlk k=3 reverse: `[5,6,7,4,3,2,1]`
3. Qalan n-k=4 reverse: `[5,6,7,1,2,3,4]` ✓

```python
def rotate(nums: list[int], k: int) -> None:
    n = len(nums)
    k = k % n  # k >= n ola bilər — modulo ilə normallaşdır

    def reverse(left: int, right: int) -> None:
        while left < right:
            nums[left], nums[right] = nums[right], nums[left]
            left += 1
            right -= 1

    reverse(0, n - 1)    # Step 1: bütün array reverse
    reverse(0, k - 1)    # Step 2: ilk k element reverse
    reverse(k, n - 1)    # Step 3: qalan n-k element reverse

# [1,2,3,4,5,6,7] → [7,6,5,4,3,2,1] → [5,6,7,4,3,2,1] → [5,6,7,1,2,3,4]

# Edge cases:
# k = 0: k % n = 0, rotate(0, -1) = no-op ✓
# k = n: k % n = 0, array dəyişmir ✓
# n = 1: yalnız bir element, fark etmez ✓
```

## Praktik Tapşırıqlar
1. **LeetCode #1: Two Sum (Easy)** — hash map həllinə çatana qədər optimize et. Trade-off-u şüurlu olaraq seç.
2. **LeetCode #53: Maximum Subarray (Medium)** — Kadane's algorithm. DP perspektivindən izah et. Niyə O(n²) brute force-dan yaxşıdır?
3. **LeetCode #238: Product of Array Except Self (Medium)** — prefix + suffix product. Division olmadan! Extra space O(1) variant-ını da yaz.
4. **LeetCode #121: Best Time to Buy and Sell Stock (Easy)** — single pass, running minimum. O(n) time, O(1) space.
5. **LeetCode #217: Contains Duplicate (Easy)** — üç fərqli həll yaz: O(n²), O(n log n) sort, O(n) set. Hər birinin trade-off-larını müqayisə et.
6. **LeetCode #189: Rotate Array (Medium)** — üç reverse texnikası ilə in-place O(1) space. Edge cases: k=0, k=n, n=1.
7. **LeetCode #56: Merge Intervals (Medium)** — sort by start + greedy merge. Sort etmədən mümkündürmü? Niyə yox?
8. **LeetCode #3: Longest Substring Without Repeating Characters (Medium)** — sliding window ilə O(n). Set vs frequency map.
9. **Özünütəst**: `[1,2,3,4,5]` array-ini `k=2` sağa rotate et — in-place, O(1) space, kod olmadan addım-addım izah et.
10. **2D matrix**: `n×n` matrix-i 90° clockwise rotate et — in-place. Hint: transpose + row reverse. O(n²) time, O(1) space.

## Əlaqəli Mövzular
- **Two Pointers Technique** — sorted array-lər üzərindəki əməliyyatlar, palindrome check.
- **Sliding Window Technique** — subarray / substring problemlərinin effektiv həlli.
- **Hash Table / Hash Map** — O(n²) brute force-u O(n)-ə çevirmək üçün.
- **Binary Search** — sorted array-lərdə axtarış O(log n)-ə çatdırır.
- **Dynamic Programming** — array-üzərindəki optimal alt-problem həlləri (Kadane's bu qrupdan).
