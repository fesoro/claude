# Recursion and Base Cases (Middle ⭐⭐)

## İcmal
Recursion — funksiyanın özünü çağırması ilə problemi alt-problemlərə parçalamasıdır. Base case olmadan recursion sonsuz loop-a girir; doğru base case isə stack overflow-u önləyir. Interview-larda recursion həm alqoritm suallarında (tree traversal, DFS, backtracking) həm də "bu alqoritmi recursive yazın" istəklərində çıxır.

## Niyə Vacibdir
Recursion tree traversal, divide & conquer, backtracking, dynamic programming kimi mövzuların hamısının təməlidir. Recursive düşüncə tərzi — "bu problemi necə bir kiçik versiyaya sadə edə bilərəm?" — sənaye-həyatında da lazımdır: JSON parsing, file system walk, XML/AST processing. Senior developer kimi recursion-ın nə vaxt iterative-ə çevriləcəyini (stack overflow, tail call optimization) bilmək gözlənilir.

## Əsas Anlayışlar

### Recursion-ın Üç Hissəsi:
1. **Base case**: Rekursiyananın dayanacağı şərt. Ən sadə alt-problem. Olmadan → infinite recursion.
2. **Recursive case**: Funksiya özünü daha kiçik input ilə çağırır. Problem hissələrə bölünür.
3. **Progress toward base case**: Hər çağırış base case-ə yaxınlaşmalıdır. Əks halda infinite loop.

### Call Stack:
- Hər rekursiv çağırış call stack-ə yeni frame əlavə edir.
- Frame: local variables + return address + parameters saxlayır.
- Dərinlik `d` olarsa, O(d) space lazımdır (stack space).
- **Stack overflow**: Dərinlik çox böyük olduqda (Python default: ~1000 level). `sys.setrecursionlimit()` ilə artırıla bilər.
- Java default stack size: ~512KB. Deep recursion `-Xss` flag ilə artırılır.

### Tail Recursion:
- Son əməliyyat rekursiv çağırışdır: `return f(n-1, acc)`.
- Bəzi dillər (Scheme, Erlang, Scala) tail call optimization (TCO) tətbiq edir.
- Java, Python TCO etmir — iterative-ə manuel çevirmək lazımdır.
- Tail recursive Fibonacci: `fib(n, a=0, b=1)` — O(n) time, O(1) space (TCO dillərdə).

### Recursion Tree Analizi:
- Hər rekursiv çağırışı node kimi çəkmək.
- Branching factor × levels = işin ölçüsü.
- `T(n) = 2T(n/2) + O(n)` → merge sort → O(n log n).
- `T(n) = 2T(n-1) + O(1)` → naive Fibonacci → O(2ⁿ).
- `T(n) = T(n-1) + O(1)` → factorial → O(n).
- `T(n) = T(n/2) + O(1)` → binary search → O(log n).

### Memoization (Top-Down DP):
- Eyni alt-problemi iki dəfə hesablamamaq üçün nəticəni cache-ləmək.
- `memo = {}` dict ilə ya `@functools.lru_cache` dekoratoru.
- Naive O(2ⁿ) Fibonacci → memoization ilə O(n) time, O(n) space.
- Memoization yalnız overlap olan alt-problemlərdə fayda verir.

### Head vs Tail Recursion:
- **Head recursion**: Rekursiv çağırış funksiya əvvəlindədir, işlər qayıdanda görülür.
- **Tail recursion**: Rekursiv çağırış funksiyanın sonuncusudur.
- `print` head-dədirsə: pre-order printing (əvvəl print, sonra recursion).
- `print` tail-dədirsə: post-order printing (əvvəl recursion, sonra print).

### Tree Recursion Pattern:
- Problem ağac kimi parçalanırsa: `solve(problem) = combine(solve(left), solve(right))`.
- Merge sort, quicksort, balanced BST əməliyyatları bu pattern.
- Return value-lar: leaf-lərdən yuxarıya qalxır, root-da combine edilir.

### Divide and Conquer vs Dynamic Programming:
- **D&C**: Alt-problemlər overlap etmir (merge sort-un hər bölünməsi fərqlidir).
- **DP**: Alt-problemlər overlap edir (Fibonacci-nin hər n üçün eyni f(n) defalarca hesablanır).
- Overlap varsa memoization/tabulation — DP.

### Backtracking ilə Fərq:
- **Pure recursion**: Problem hissələrə bölünür, hər hissə həll olunur, combine edilir.
- **Backtracking**: Bütün mümkün seçimləri sınayırıq, yanlış getsək geri qayıdırıq.
- Backtracking-in əsası: `choose → explore → unchoose (backtrack)`.

### Mutual Recursion:
- `isEven(n)` → `isOdd(n-1)` → `isEven(n-1)` → ...
- Nadir amma mövcuddur. Base case: `isEven(0) = true`, `isOdd(0) = false`.
- Parser-lərdə (LL(k) grammar) müstəqim soldan gəlmə kimi görünə bilər.

### Recursion → Iterative Çevirmə:
- Explicit stack istifadə et.
- Stack-ə `(node, state)` tuple-ları at.
- Call stack simulation: "nə return etmişdik?" state-ini track et.
- Tree DFS iterative = stack-ə node-ları at.

### Recursion-ın Zəiflikləri:
- Space: O(depth) call stack — skewed tree-də O(n) space.
- Stack overflow: Dərin recursion — Python default ~1000, Java ~5000-10000.
- Function call overhead: Iterative-dən əməliyyat başına daha çox overhead.
- Debugging çətin: Stack trace uzun olur.

## Praktik Baxış

**Interview-a yanaşma:**
Rekursiv sual alırsan: (1) Base case nədir? (2) Rekursiv case nə qayıtmalıdır? (3) Böyük problem kiçik problemin nəticəsinə necə əsaslanır? — bu üç sualı cavablandırsan, recursion özü yazılır. Complexity üçün recursion tree çək.

**Nədən başlamaq lazımdır:**
- Ən kiçik input üçün (0, 1, boş) cavabı yazın — base case.
- Bir step geri düşün: n-in cavabını n-1-in cavabından necə qurmaq olar?
- Space complexity-ni unutma: recursion depth O(space) deməkdir.
- "Memoization əlavə etmələyəmmi?" — overlap var mı?

**Follow-up suallar:**
- "Recursive həllinizi iterative-ə çevirə bilərsənmi?"
- "Stack overflow baş versə nə edərdiniz?"
- "Memoization əlavə etmək bu alqoritmi O(2ⁿ)-dən nəyə endirər?"
- "Tail recursive versiyasını yaza bilərsənmi?"
- "Bu recursion-ın time complexity-si nədir? Recursion tree çəkin."
- "Space complexity recursion depth-i niyə hesab edir?"
- "Bu həli backtracking sayılırmı, ya DP?"

**Namizədlərin ümumi səhvləri:**
- Base case unutmaq → infinite recursion.
- Base case lazım ola bilən bütün halları əhatə etməmək (n=0 və n=1 ayrı-ayrı lazım ola bilər).
- Space complexity-ni unudun: "O(1) space" deyib recursion depth-ini saymamaq.
- Memoization olmadan exponential alqoritm optimaldır kimi təqdim etmək.
- Return value-nu unudun: `return` yazmamaq.
- Backtracking-də `pop()` etməyi unutmaq — state-i bərpa etməmək.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Düzgün işləyən recursive kod yazır.
- Əla cavab: Recursion tree-ni çəkir, time/space complexity-ni izah edir, memoization əlavə edir, "niyə iterative daha yaxşı ola bilər?" müzakirə edir, tail recursion-ı qeyd edir, base case seçimini əsaslandırır.

## Nümunələr

### Tipik Interview Sualı
"Bütün alt-sətlər (subsets) siyahısını qaytarın. `nums = [1,2,3]` → `[[], [1], [2], [3], [1,2], [1,3], [2,3], [1,2,3]]`."

### Güclü Cavab
"Bu backtracking/recursive olmadan yanaşmaq olarsa da klassik recursive həll var. Hər element üçün iki seçim var: daxil et ya etmə. Bu binary tree: n=3 üçün 2³=8 subset. Recursion: `subsets(index, current) = subsets(index+1, current) + subsets(index+1, current+[nums[index]])`. Base case: `index == len(nums)` → `current` əlavə et. T(n) = 2T(n-1) + O(1) → O(2ⁿ) time (optimal çünki 2ⁿ nəticə var), O(n) space (recursion depth). Bu backtracking pattern-dir."

### Kod Nümunəsi
```python
# Fibonacci — naive: O(2ⁿ), memoization: O(n), iterative: O(n) time O(1) space
def fib_naive(n: int) -> int:
    if n <= 1:          # base case
        return n
    return fib_naive(n - 1) + fib_naive(n - 2)   # O(2ⁿ)

from functools import lru_cache
@lru_cache(maxsize=None)
def fib_memo(n: int) -> int:
    if n <= 1:
        return n
    return fib_memo(n - 1) + fib_memo(n - 2)   # O(n) time, O(n) space

def fib_iterative(n: int) -> int:
    if n <= 1:
        return n
    a, b = 0, 1
    for _ in range(2, n + 1):
        a, b = b, a + b
    return b   # O(n) time, O(1) space — ən optimal

# Subsets — backtracking / recursion
def subsets(nums: list[int]) -> list[list[int]]:
    result = []

    def backtrack(index: int, current: list[int]):
        if index == len(nums):       # base case — bütün elementlər işləndi
            result.append(current[:])  # shallow copy vacibdir!
            return
        # Bu elementi daxil etmə
        backtrack(index + 1, current)
        # Bu elementi daxil et
        current.append(nums[index])
        backtrack(index + 1, current)
        current.pop()                # backtrack — state-i bərpa et

    backtrack(0, [])
    return result

# Power Function — divide and conquer recursion
def my_pow(x: float, n: int) -> float:
    if n == 0:
        return 1.0
    if n < 0:
        x, n = 1 / x, -n
    half = my_pow(x, n // 2)   # O(log n) dərinlik
    if n % 2 == 0:
        return half * half
    else:
        return half * half * x
# O(log n) time, O(log n) space (call stack)

# Flatten Nested List — real-world recursive problem
def flatten(nested):
    result = []
    def _flatten(item):
        if isinstance(item, list):
            for sub in item:        # recursive case
                _flatten(sub)
        else:
            result.append(item)     # base case
    _flatten(nested)
    return result

# Tree-based Recursion — merge sort
def merge_sort(arr: list[int]) -> list[int]:
    if len(arr) <= 1:    # base case
        return arr
    mid = len(arr) // 2
    left = merge_sort(arr[:mid])    # rekursiv sol
    right = merge_sort(arr[mid:])   # rekursiv sağ
    return merge(left, right)       # combine

def merge(l: list[int], r: list[int]) -> list[int]:
    result, i, j = [], 0, 0
    while i < len(l) and j < len(r):
        if l[i] <= r[j]:
            result.append(l[i]); i += 1
        else:
            result.append(r[j]); j += 1
    result.extend(l[i:]); result.extend(r[j:])
    return result
```

### İkinci Nümunə — Permutations

**Sual**: `nums` array-in bütün permutasiyalarını qaytarın. `nums = [1,2,3]` → 6 permutasiya.

**Cavab**: Backtracking. Hər mövqe üçün istifadə edilməmiş elementlər arasından seçim et. O(n! × n) time — n! permutasiya, hər biri n element.

```python
def permute(nums: list[int]) -> list[list[int]]:
    result = []

    def backtrack(current: list[int], remaining: list[int]):
        if not remaining:   # base case: bütün elementlər seçildi
            result.append(current[:])
            return
        for i, num in enumerate(remaining):
            current.append(num)
            # num-u remaining-dən çıxar
            backtrack(current, remaining[:i] + remaining[i+1:])
            current.pop()   # backtrack

    backtrack([], nums)
    return result

# Daha effektiv: swap-based, O(n) space
def permute_swap(nums: list[int]) -> list[list[int]]:
    result = []

    def backtrack(start: int):
        if start == len(nums):
            result.append(nums[:])
            return
        for i in range(start, len(nums)):
            nums[start], nums[i] = nums[i], nums[start]   # seç
            backtrack(start + 1)                           # davam et
            nums[start], nums[i] = nums[i], nums[start]   # geri qaytar

    backtrack(0)
    return result
```

## Praktik Tapşırıqlar
- LeetCode #509: Fibonacci Number (Easy) — naive + memoization + iterative. Space fərqini müqayisə et.
- LeetCode #78: Subsets (Medium) — recursive + iterative (bit manipulation).
- LeetCode #206: Reverse Linked List (Easy) — recursive version. Space complexity?
- LeetCode #50: Pow(x, n) (Medium) — divide & conquer recursion. O(log n).
- LeetCode #112: Path Sum (Easy) — tree recursion. Base case nədir?
- LeetCode #226: Invert Binary Tree (Easy) — simple recursion.
- LeetCode #46: Permutations (Medium) — backtracking. Swap vs remaining copy.
- LeetCode #131: Palindrome Partitioning (Medium) — backtracking + DP.
- Özünütəst: `fib(40)`-ı naive recursion ilə çağır, neçə saniyə çəkir? Memoization ilə?

## Əlaqəli Mövzular
- **Binary Tree** — demək olar bütün tree əməliyyatları rekursivdir.
- **BFS and DFS** — DFS natural olaraq rekursivdir (call stack = explicit stack).
- **Dynamic Programming** — top-down DP = recursion + memoization.
- **Backtracking** — recursion + undo (pop) əməliyyatı.
- **Divide and Conquer** — problem yarıya böl, rekursiv həll et, combine et.
