# Big O Notation (Junior ⭐)

## İcmal
Big O Notation — alqoritmin performansını (sürət və yaddaş istifadəsi) input ölçüsü ilə əlaqədar olaraq ifadə edən riyazi notasiyadır. Interview-larda bu mövzu demək olar ki, hər alqoritm sualında ortaya çıxır, çünki interviewer namizədin nəinki problemi həll etdiyini, həm də bu həllin nə qədər effektiv olduğunu başa düşdüyünü yoxlamaq istəyir.

## Niyə Vacibdir
Hər hansı bir texniki interview-da Big O anlayışını bilmədən uğurlu olmaq mümkün deyil — bu, namizədin kompüter elmi əsaslarını nə dərəcədə mənimsədiyini ölçən universal bir metrikdir. FAANG, top startup-lar və böyük tech şirkətlər bu anlayışı həm LeetCode-typli sual həllərindəki analiz mərhələsində, həm də system design müzakirələrindəki scalability şərhləri zamanı tələb edir. Backend rollar üçün xüsusilə vacibdir, çünki serverda işləyən hər bir əməliyyatın performans izini başa düşmək lazım olur. Zəif Big O biliyi namizədi dərhal "junior" kateqoriyasına salar.

## Əsas Anlayışlar

### Əsas Komplekslik Sinifləri (yaxşıdan pisə doğru):
- **O(1) — Constant time**: Input ölçüsündən asılı olmayaraq eyni vaxt. Nümunə: array-ə index ilə müraciət, hash map-dən `get`. `n = 1` ya da `n = 10^9` — eyni vaxt.
- **O(log n) — Logarithmic**: Hər addımda problem yarıya bölünür. Nümunə: binary search, balanced BST axtarışı. `n = 1,000,000` üçün yalnız ~20 addım.
- **O(√n) — Square root**: Nadir hallarda çıxır; əsasən prime checking alqoritmlərində. `n = 1,000,000` → ~1,000 addım.
- **O(n) — Linear**: Input ilə mütənasib böyüyür. Nümunə: array-i iterate etmək, unsorted list-də axtarış. `n = 10^6` → 10^6 addım.
- **O(n log n) — Linearithmic**: Effektiv sort alqoritmləri üçün tipikdir. Nümunə: merge sort, heap sort, quicksort (average case). `n = 10^6` → ~20×10^6 addım.
- **O(n²) — Quadratic**: İç-içə loop-lar. Nümunə: bubble sort, selection sort, naive nested iteration. `n = 10,000` → 10^8 addım — çox yavaş.
- **O(n³) — Cubic**: Üç nested loop. Nümunə: naive matrix multiplication, Floyd-Warshall. `n = 1000` → 10^9 addım — production-da qəbul edilməz.
- **O(2ⁿ) — Exponential**: Hər element üçün iki seçim. Nümunə: recursive fibonacci (memoization olmadan), subset generation. `n = 50` → 10^15 addım.
- **O(n!) — Factorial**: Bütün permutasiyaları gəzmək. Nümunə: traveling salesman brute force, permutations. `n = 20` → 2.4×10^18 addım — praktik olaraq mümkün deyil.

### Space Complexity:
- **Auxiliary space**: Alqoritmin özü üçün istifadə etdiyi əlavə yaddaş (input daxil deyil).
- **Total space**: Input + auxiliary space. Interview-da çox vaxt "space complexity" auxiliary space deməkdir.
- Rekursiv funksiyalarda call stack də space hesablanır — dərinlik `d` olarsa, O(d) space lazımdır.
- In-place alqoritmlər O(1) auxiliary space istifadə edir, amma call stack-i nəzərə almırlar.
- Hash map istifadəsi demək olar ki, həmişə O(n) space əlavə edir — bu time-space trade-off-dur.
- Merge sort: O(n log n) time, O(n) space — extra array tələb edir.
- Quicksort: O(n log n) average time, O(log n) average space (call stack).
- Heap sort: O(n log n) time, O(1) auxiliary space — in-place sort.

### Big O Qaydaları:
- **Sabitləri at**: `O(2n)` → `O(n)`, `O(500)` → `O(1)`, `O(5n²)` → `O(n²)`. Sabit çarpanlar böyümə sürətini dəyişmir.
- **Kiçik şərtləri at**: `O(n² + n)` → `O(n²)`, `O(n³ + n² + n)` → `O(n³)`. Dominant term qalır.
- **Fərqli input-lar fərqli dəyişənlərdir**: İki ayrı array `A` və `B` varsa, `O(a + b)` ya da `O(a × b)` — ikisini də `n` adlandırmaq səhvdir.
- **Drop Non-Dominant Terms**: `O(n³ + n²)` → `O(n³)`, `O(2^n + n²)` → `O(2^n)`.
- **Nested loops multiply**: İç-içə loop-lar çarpılır: O(n) × O(m) = O(n×m). Hər ikisi n-ə görə dəyişirsə O(n²).
- **Sequential loops add**: Ardıcıl loop-lar toplanır: O(n) + O(m) = O(n+m). Dominant-ı saxla.

### Best / Average / Worst Case:
- **Best case (Ω — Omega)**: Ən yaxşı ssenari. Nadir hallarda istifadə olunur. Məs: linear search-də target birinci elementdir — O(1). Practical deyil, amma interviewer soruşa bilər.
- **Average case (Θ — Theta)**: Orta gözlənilən performans. Əməli dəyəri olan ölçü. Real-world sistemlərdə planlaşdırma üçün vacib. Quicksort average O(n log n).
- **Worst case (O — Big O)**: Interview-larda standart ölçü. "Ən pis nə ola bilər?" sualının cavabı. Sistemin SLA-sını təmin etmək üçün vacib. Quicksort worst O(n²) — sorted array + bad pivot.
- **Quicksort breakdown**: Best O(n log n), Average O(n log n), Worst O(n²) — sorted array + pivot həmişə ən böyük ya da ən kiçik seçildikdə.
- **Hash map breakdown**: Best/Average O(1) lookup, Worst O(n) — bütün key-lər eyni bucket-a düşdükdə (hash collision). Good hash function ilə average case-ə etibar edilir.
- **Binary search breakdown**: Best O(1) — target ortadadır, Average/Worst O(log n).

### Amortized Analysis:
- Bəzi əməliyyatlar bəzən baha olsa da, uzun müddətdə ortalama cost-u aşağıdır.
- **Dynamic array append**: Çox vaxt O(1), amma resize baş verdikdə O(n). Amortized: O(1). Çünki n/2 element köçürüldükdə hər element ortalama 2 dəfə köçürülmüş olur.
- **Stack push/pop**: Amortized O(1), hər element ən çox bir dəfə push, bir dəfə pop edilir.
- **Splay tree access**: Amortized O(log n) — tez-tez istifadə olunan elementlər kökə yaxınlaşır.
- **Banker's method**: Hər "ucuz" əməliyyat "kredit" yığır, "baha" əməliyyat bu krediti xərcləyir. Formal proof metodu.
- Interview-da amortized soruşulursa: "ArrayList.add() bəzən O(n) olsa da, amortized O(1)-dir — çünki resize cəmi n/2-i köçürür."

### Rekursiyada Space Complexity:
- Hər rekursiv çağırış call stack-ə bir frame əlavə edir.
- `factorial(n)` → O(n) space (n dərinliyinə qədər stack böyüyür) + O(n) time.
- Tail recursion-da bəzi dillər (Kotlin, Scala, Haskell) stack frame-i reuse edə bilər — Java/Python etmir.
- **Binary tree DFS recursion**: O(h) space — h = height. Balanced tree-də O(log n), skewed (degenerate) tree-də O(n).
- **Recursive fibonacci**: O(n) space (call stack dərinliyi), O(2^n) time (memoization olmadan). Memoization ilə: O(n) time, O(n) space.
- **Merge sort recursion**: O(log n) stack frames, amma hər level O(n) temporary space — total O(n).

### Comparison-Based Sort Lower Bound:
- n! mümkün permutasiya üçün ən az Ω(n log n) müqayisə lazımdır.
- Sübut: Decision tree-nin hündürlüyü log₂(n!) ≈ n log n (Stirling's approximation ilə).
- Merge sort, heap sort bu lower bound-a çatır — optimaldir.
- Counting sort, radix sort bu sınırı keçir — comparison-based deyil, yalnız integer/bounded keys üçün işləyir.

### Master Theorem (Recursion Analysis):
- `T(n) = aT(n/b) + O(n^d)` formasındakı rekursiv funksiyaları analiz edir.
- Nümunə: `T(n) = 2T(n/2) + O(n)` → Merge Sort → O(n log n).
- Nümunə: `T(n) = T(n/2) + O(1)` → Binary Search → O(log n).
- Nümunə: `T(n) = 2T(n/2) + O(1)` → Binary Tree Traversal → O(n).
- Interview-da Master Theorem soruşulursa üç halı bilmək bəs edir: a < b^d → O(n^d); a = b^d → O(n^d log n); a > b^d → O(n^log_b(a)).

### Dörd Fundamental Sual:
1. Bu alqoritm üçün worst-case time complexity nədir?
2. Space complexity nədir (auxiliary)?
3. Trade-off nədir (daha az yaddaş üçün daha çox vaxt, ya əksi)?
4. Bu həllmi optimal, yoxsa daha yaxşı yol varmı?

### Cache Locality və Real Performance:
- Big O theoretical model — real hardware-da konstantlar önəmlidir.
- O(n²) ilə küçük n: `n = 100` üçün bubble sort quicksort-dan praktik olaraq sürətli ola bilər (cache warmth).
- Array vs linked list: Hər ikisi O(n) linear scan, amma array cache-friendly olduğu üçün 5-10x sürətli.
- Memory access pattern — sequential > random. Bu CPU prefetcher-in effektivliyinə görədir.
- Production-da benchmark lazımdır — Big O yalnız istiqaməti göstərir.

### Big O Nə Deyil:
- Big O dəqiq işləmə müddətini ölçmür — yalnız böyümə sürətini göstərir.
- O(1) "çox sürətli" demək deyil — O(1) ilə milyon əməliyyat da edə bilərsiniz.
- O(n²) hər zaman pis deyil — n=10 üçün 100 əməliyyat olduqca yaxşıdır.
- Aşağı Big O hər zaman daha yaxşı praktik performans vermir — konstantlar, cache behavior, hardware fərq yaradır.

### Interview-da Ümumi Tricky Suallar:
- `for i in range(n): for j in range(i, n)` → toplam = n(n+1)/2 → O(n²).
- İki nested loop amma daxili n deyil, `log n` iterasiya edirsə → O(n log n).
- Rekursiv funksiya: `f(n) = f(n/2) + O(1)` → O(log n) (Binary Search kimi).
- Rekursiv funksiya: `f(n) = 2*f(n/2) + O(n)` → O(n log n) (Merge Sort kimi).
- `string += char` loop içdə Python-da: O(n²) — hər `+` yeni string yaradır. `.join()` istifadə et.
- Java-da `StringBuilder` olmadan `String + String` loop: O(n²). `StringBuilder.append()`: O(1) amortized.

## Praktik Baxış

### Interview Strategiyası — Addım-addım:
1. **Brute force-u elan et**: "İlk öncə O(n²) brute force həll düşünürəm" — yazmadan əvvəl deyin.
2. **Bottleneck-i tap**: "Daxili loop nə axtarır? Hash map ilə O(1)-ə endirə bilərəmmi?"
3. **Optimize et**: "Hash map əlavə etsəm, lookup O(1) olar. Time O(n)-ə düşür, amma O(n) space əlavə gəlir."
4. **Trade-off vurğula**: "O(n) space xərclə O(n²) → O(n) time qazanıram. Bu trade-off məqsədəuyğundur."
5. **Edge case-ləri soruş**: "n=0 ya n=1 halları necə?"
6. **Complexity-ni sonunda təsdiq et**: "Final: O(n) time, O(n) space."

### Follow-up Suallar:
- "Bu həlli scale edə bilərsənmi? N = 10⁹ olsaydı nə baş verərdi?" — O(n²) artıq işləməz, distributed solution lazım ola bilər.
- "Space complexity-ni azalda bilərsənmi?" — hash map olmadan mümkündürmü? Bəzən sort + two pointer ilə O(1) space.
- "In-place edə bilərsənmi?" — auxiliary space olmadan.
- "Bu O(n log n)-dən daha yaxşı həll edilə bilərmi?" — comparison sort lower bound.
- "Best case nə zaman olur?" — Omega notation haqqında sual.
- "Amortized complexity nədir?" — ArrayList, Stack üçün.
- "Bu alqoritmin cache performance-ı necədir?" — array vs linked list fərqi.
- "Rekursiv versiya iterative-dan nə fərqlidir?" — space complexity fərqi.

### Namizədlərin Ümumi Səhvləri:
- `O(2n)` → `O(2n)` yazmaq, sabitləri atmamaq.
- İki fərqli input-un hər ikisini `n` adlandırmaq — `O(a × b)` deyil `O(n²)` demək.
- Rekursiv funksiyalarda space complexity-ni (call stack) unutmaq.
- Nested loop-ları gördükdə avtomatik `O(n²)` deməkdən əvvəl daxili loop-un dəyişənini yoxlamamaq.
- Amortized analizi bilməmək — "ArrayList.add() O(n)-dir" demək.
- String concatenation-ın Java/Python-da O(n²) olduğunu unutmaq.
- Hash map worst case-i (O(n)) ilə average case-i (O(1)) qarışdırmaq.
- Binary search rekursiv versiyasında space complexity O(log n)-i unutmaq.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən Nədir:
- **Yaxşı cavab**: Doğru Big O verir.
- **Əla cavab**: Best/average/worst ayrımını izah edir, bottleneck-i müəyyən edir, optimization proposal-ı irəli sürür, trade-off-ları müzakirə edir, amortized analysis-i bilir, cache locality-dən bəhs edir, Master Theorem tətbiq edə bilir.

## Nümunələr

### Tipik Interview Sualı
"Aşağıdakı kod parçasının time complexity-ni analiz edin:
```python
for i in range(n):
    for j in range(i, n):
        print(i, j)
```"

### Güclü Cavab
"Bu kod iki nested loop-dan ibarətdir. Xarici loop `n` dəfə işləyir. Daxili loop isə `i`-dən `n`-ə qədər gedir, yəni hər iterasiyada azalır. Toplam əməliyyat sayı: `n + (n-1) + (n-2) + ... + 1 = n(n+1)/2`. Big O notasiyasında sabitləri atırıq: O(n²). Space complexity isə yalnız iki dəyişən saxlayırıq — O(1). Bu quadratic growth-dur, yəni n=1000 olduqda ~500,000 əməliyyat, n=10,000 olduqda ~50,000,000 əməliyyat olacaq — bu problematikdir."

### Kod Nümunəsi — Brute Force → Optimal
```python
# O(n) nümunəsi — bir loop
def find_max(arr):
    max_val = arr[0]          # O(1)
    for num in arr:           # O(n) — n dəfə işləyir
        if num > max_val:     # O(1)
            max_val = num     # O(1)
    return max_val            # O(1)
# Toplam: O(n) time, O(1) space

# O(n²) nümunəsi — brute force nested loop
def has_duplicate_naive(arr):
    for i in range(len(arr)):             # O(n)
        for j in range(i + 1, len(arr)):  # O(n) worst case
            if arr[i] == arr[j]:
                return True
    return False
# Toplam: O(n²) time, O(1) space

# O(n) optimallaşdırma — hash set istifadəsi
def has_duplicate_optimal(arr):
    seen = set()              # O(n) space
    for num in arr:           # O(n) time
        if num in seen:       # O(1) average lookup
            return True
        seen.add(num)         # O(1) average insert
    return False
# Toplam: O(n) time, O(n) space — time-space trade-off!

# Logarithmic — binary search
def binary_search(arr, target):
    left, right = 0, len(arr) - 1
    while left <= right:              # O(log n) — hər dəfə yarıya bölür
        mid = (left + right) // 2
        if arr[mid] == target:
            return mid
        elif arr[mid] < target:
            left = mid + 1
        else:
            right = mid - 1
    return -1
# Toplam: O(log n) time, O(1) space

# Recursion-da space complexity nümunəsi
def factorial(n):
    if n <= 1:          # base case
        return 1
    return n * factorial(n - 1)   # O(n) call stack dərinliyi
# Time: O(n), Space: O(n) — call stack n dərinliyə çatır!

# Amortized O(1) — dynamic array append nümunəsi
class DynamicArray:
    def __init__(self):
        self.data = [None] * 1
        self.size = 0
        self.capacity = 1

    def append(self, val):
        if self.size == self.capacity:
            # Resize: O(n) — amma nadir baş verir
            self.capacity *= 2
            new_data = [None] * self.capacity
            for i in range(self.size):
                new_data[i] = self.data[i]  # O(n) copy
            self.data = new_data
        self.data[self.size] = val          # O(1)
        self.size += 1
# Amortized: O(1) per append — çünki her element
# average olaraq 2 dəfə köçürülür (geometric series)

# String concatenation — ən çox unudulan O(n²) trap
def build_string_bad(chars):
    result = ""
    for c in chars:
        result += c  # Her + yeni string yaradır — O(n²)!
    return result

def build_string_good(chars):
    return "".join(chars)  # O(n) — bir pass

# Master Theorem nümunəsi — Merge Sort
def merge_sort(arr):
    if len(arr) <= 1:
        return arr
    mid = len(arr) // 2
    left = merge_sort(arr[:mid])   # T(n/2)
    right = merge_sort(arr[mid:])  # T(n/2)
    return merge(left, right)      # O(n) merge step
# T(n) = 2T(n/2) + O(n) → Master Theorem → O(n log n)
```

### İkinci Nümunə — Tricky Complexity Analizi

**Sual**: Aşağıdakı funksiyanın time complexity-sini tapın:
```python
def mystery(n):
    i = 1
    count = 0
    while i < n:
        i *= 2      # hər addımda ikiqat artır
        count += 1
    return count
```

**Cavab prosesi**:
- `i` hər dəfə iki dəfə artır: 1, 2, 4, 8, ..., n
- Loop neçə dəfə işləyir? 2^k = n → k = log₂(n) iteration
- Yəni O(log n) time, O(1) space
- Bu tipik O(log n) pattern-dir — dəyişən hər addımda sabit faktorla çarpılır

**Eyni pattern-in başqa nümunəsi**:
```python
# i //= 2 — hər addımda yarıya bölür → yenə O(log n)
def descend(n):
    i = n
    while i >= 1:
        i //= 2   # 64, 32, 16, 8, 4, 2, 1 → log₂(n) addım
        print(i)
# O(log n) time, O(1) space
```

**Üçüncü tricky nümunə — iki dəyişənli loop**:
```python
def two_variable(a, b):
    for i in range(a):       # a dəfə
        for j in range(b):   # b dəfə
            print(i, j)
# O(a × b) — NOT O(n²)!
# Əgər a = b = n olsaydı, O(n²)
# Amma "n" yalnız bir dəyişən ola bilər
```

## Praktik Tapşırıqlar
1. **LeetCode #1: Two Sum (Easy)** — brute force O(n²)-dən hash map ilə O(n)-ə optimize et. Hər addımda complexity-ni sözlü izah et. Trade-off-u müzakirə et.
2. **LeetCode #217: Contains Duplicate (Easy)** — üç fərqli həll yaz: O(n²) nested loop, O(n log n) sorting, O(n) hash set. Hər birinin trade-off-larını müqayisə et.
3. **LeetCode #53: Maximum Subarray (Medium)** — Kadane's algorithm: niyə O(n²)-dən yaxşıdır? Space complexity nədir?
4. **LeetCode #136: Single Number (Easy)** — XOR ilə O(n) time, O(1) space həlli — niyə hash set-dən üstündür?
5. **Özünütəst**: Aşağıdakı hər əməliyyatın time/space complexity-sini sadalaya bilirsinizmi: array insert/delete/search, linked list insert/delete/search, hash map get/set, stack push/pop, queue enqueue/dequeue, BST search, heap insert/extract-min.
6. **Recursion tree**: `fibonacci(5)` üçün recursion tree çək. Neçə unikal subproblem var? Memoization olmadan/ilə time complexity müqayisəsi.
7. **Mücərrəd sual**: `T(n) = 2T(n/2) + O(n)` üçün Master Theorem tətbiq et — O(n log n) nəticəsini əsaslandır.
8. **Tricky sual**: Loop içində `for s in strs: result += s` — O(n) mi O(n²) mi? (Python-da O(n²), `.join()` ilə O(n).) Java-da `StringBuilder` istifadəsi niyə vacibdir?
9. **Space complexity quiz**: Recursive DFS ağac traversal-ında balanced tree vs degenerate tree-nin space complexity fərqini izah et.
10. **Amortized quiz**: Stack-ə n element push etmək ümumi O(n) cost-dadır. Amma bəzi push-lar resize etdikdə baha olur. Amortized per-operation cost-u izah et.

## Əlaqəli Mövzular
- **Array and String Fundamentals** — Big O-nu array əməliyyatlarında tətbiq etmək.
- **Sorting Algorithms Comparison** — Sort alqoritmlərinin complexity analizi (merge sort, quicksort, timsort).
- **Binary Search** — O(log n)-in real tətbiqi.
- **Hash Table / Hash Map** — O(1) average, O(n) worst case arasındakı fərq.
- **Dynamic Programming** — Exponential-dan polynomial-a optimize etmək (memoization).
- **Recursion and Base Cases** — Recursion tree ilə time complexity analizi.
