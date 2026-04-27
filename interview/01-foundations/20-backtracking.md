# Backtracking (Senior ⭐⭐⭐)

## İcmal
Backtracking — bütün mümkün həlləri sistematik şəkildə araşdıran, ümid verməyən yolları vaxtında bağlayan (pruning) rekursiv alqoritm texnikasıdır. Decision tree üzərində DFS kimi düşünmək olar: hər node-da seçim edirik, yanlış yolda geri qayıdırıq. N-Queens, Sudoku, permutasiyalar, kombinasiyalar backtracking-in klassik tətbiqləridir. Interview-da backtracking sualları namizədin rekursiv düşüncə qurmasını yoxlayır.

## Niyə Vacibdir
Backtracking constraint satisfaction problemlərinin əsas həll texnikasıdır. Real dünyada: compiler-in type inference, SAT solver-lər, scheduling constraint-lər, oyun alqoritmləri (chess engine-in minimax). FAANG interview-larında "bütün mümkün kombinasiyaları/permutasiyaları tap" tipli suallar backtracking-dədir. Senior namizəddən gözlənilən: yalnız brute force yazmaq deyil, effective pruning ilə space-i əhəmiyyətli dərəcədə azaltmaq.

## Əsas Anlayışlar

### Backtracking Şablonu
```
backtrack(state, choices):
    if is_solution(state):
        add state to results
        return
    for each choice in choices:
        if is_valid(state, choice):
            make_choice(state, choice)
            backtrack(state, remaining_choices)
            undo_choice(state, choice)   ← bu "backtrack" addımıdır
```
"Undo" addımı — state-i əvvəlki vəziyyətinə qaytarır. Bu in-place mutation istifadə edildikdə kritikdir.

### Pruning (Budama) Strategiyaları
Pruning olmadan backtracking eksponensial olur. Yaxşı pruning həll olunmayan yolları erkən kəsir:

- **Constraint propagation**: hər seçimdən sonra constraint-ləri yoxla (Sudoku-da bir row-da eyni rəqəm olmaz)
- **Bound calculation**: Upper bound (e.g., max possible sum) threshold-dan kiçikdirsə kəs
- **Symmetry breaking**: ekvivalent state-ləri bir dəfə araşdır (N-Queens-də sol yarı + simetrik)
- **Forward checking**: gələcək seçimlər hələ mümkündürmü? Mümkün deyilsə erkən qayıt

### Backtracking Problemlərinin Növləri

**Permutation (sıralama əhəmiyyətlidir)**
- `[1,2,3]`-dən bütün sıralamaları tap
- State: hazırki permutasiya, `used[]` array
- Choices: hələ istifadə olunmamış elementlər
- Complexity: O(n! * n) — n! permutasiya, hər birini kopyalamaq n

**Combination (sıralama əhəmiyyətsiz)**
- `[1..n]`-dən k elementi seç
- State: hazırki kombinasiya, başlanğıc indeksi
- Choices: `start`-dan sona qədər elementlər
- Complexity: O(C(n,k) * k)

**Subset (bütün alt çoxluqlar)**
- Hər element ya daxildir ya deyil — ikili seçim
- State: hazırki subset, hazırki indeks
- Complexity: O(2^n * n)

**Constraint Satisfaction**
- N-Queens: hər cərgəyə bir kraliça qoy, konflikt yoxdursa davam et
- Sudoku: hər boş xanaya 1-9 sına, geçersiz seçimi kəs
- Graph Coloring: hər vertex-ə rəng ver, qonşu eyni rəng olmasın

**Path Finding (backtracking ilə)**
- Word Search: grid-də söz tap
- Knight's Tour: hər xanaya bir dəfə get
- Hamiltonian Path: hər node-u bir dəfə ziyarət et

### N-Queens Analizi
- n x n lövhədə n kraliça qoy, bir-birini vurmadan
- Brute force: O(n^n) — n cərgənin hər birindən n seçim
- Backtracking: O(n!) — artıq tutulmuş sütun/diagonal istifadə etmə
- Constraint: `col[c]`, `diag1[r-c]`, `diag2[r+c]` arrays ilə O(1) check
- Açıq pruning effekti: n=8 üçün 8^8 = 16M yerinə 92 həll, ~2000 node yoxlanır

### State Space Ağacı
```
Seçim nöqtəsi: hər rekursiv çağırış
Kənar şərt: constraint yoxlaması → pruning
Həll: base case
Backtrack: `undo` sonrası yuxarıdakı seçim nöqtəsinə qayıt
```
Ağacın genişliyi = seçimlərin sayı, dərinliyi = həllin uzunluğu.

### Backtracking vs DFS
- DFS: mövcud graph üzərindəki axtarış
- Backtracking: həll ağacını dinamik yaradır, state-i dəyişdirib bərpa edir
- Backtracking-in undo addımı DFS-də yoxdur

### Backtracking vs DP
- DP: üst-üstə düşən alt-problemlər, optimal substructure
- Backtracking: bütün həlləri tap, optimal axtarmır (adətən)
- Bəzən kombinasiya: backtracking + memoization (top-down DP-yə çevrilir)

### Duplicate Handling
- Dublikat elementlər varsa (`[1,1,2]`), eyni kombinasiya bir neçə dəfə görsənə bilər
- Sort et + `if i > start and nums[i] == nums[i-1]: continue` ilə atla
- `used[]` array ilə `if nums[i] == nums[i-1] and not used[i-1]: continue`

## Praktik Baxış

### Interview Yanaşması
1. "Bütün mümkün ___" → backtracking
2. Decision tree-ni cəkilmişcə izah et: hər node seçimdir, leaf həlldir
3. Pruning şərtlərini əvvəlcə sözlü de
4. Template-i yaz, sonra problem-specific hissəni doldur
5. Complexity: O(choices^depth * cost_per_node)

### Nədən Başlamaq
- Base case: nə zaman həll tapıldı?
- Choices: hər addımda nə seçmək olar?
- Constraint: hansı seçimlər etibarsızdır?
- Undo: seçimi necə geri almaq lazım?

### Ümumi Follow-up Suallar
- "Pruning olmadan complexity nə olar?" (exponential göstər)
- "Bu problemi iterative yanaşma ilə həll etmək olarmı?" (explicit stack)
- "Yalnız bir həll lazımdırsa nə dəyişir?" (`return True` ilə erkən çıx)
- "Memoization əlavə etsəniz nə dəyişər?" (DP-yə keçid)

### Namizədlərin Ümumi Səhvləri
- `undo` addımını unutmaq (state corrupted olur)
- Result-a object reference əlavə etmək, copy deyil (`result.append(path[:])`)
- Duplicate handling-i unutmaq
- Pruning şərtini yanlış yazmaq (valid state-ləri kəsmək)
- Complexity-ni izah edə bilməmək

### Yaxşı → Əla Cavab
- Yaxşı: backtracking şablonu düzgün yazır, doğru cavablar verir
- Əla: pruning-i şərh edir, duplicate-ləri idarə edir, complexity-ni hesablayır, iterative alternativini bilir

## Nümunələr

### Tipik Interview Sualı
"N-Queens problemini həll edin: n×n lövhədə n kraliçanı elə yerləşdirin ki, heç biri digərini vura bilməsin. Bütün həlləri qaytarın."

### Güclü Cavab
Hər cərgəyə bir kraliça qoyacağam — bu şəkildə row konfliktini avtomatik aradan qaldırıram. Sütun və diagonal konfliktini 3 set ilə izləyirəm: `cols`, `pos_diag` (r+c), `neg_diag` (r-c). Hər cərgədə boş sütunları sınamaq əvəzinə, bu set-lərə baxaraq O(1) ilə etibarsız mövqeləri atıram — bu effektiv pruning-dir.

Complexity: O(n!) — hər cərgədə əvvəlki seçimlərə görə seçim azalır. N=8 üçün bu 40320 seçim imkanı deməkdir, amma pruning sayəsinde əslinda çox az node araşdırılır.

### Kod Nümunəsi
```python
def solve_n_queens(n):
    results = []
    cols = set()
    pos_diag = set()  # r + c
    neg_diag = set()  # r - c

    board = [['.' ] * n for _ in range(n)]

    def backtrack(row):
        if row == n:
            results.append([''.join(r) for r in board])
            return
        for col in range(n):
            if col in cols or (row + col) in pos_diag or (row - col) in neg_diag:
                continue  # pruning
            cols.add(col)
            pos_diag.add(row + col)
            neg_diag.add(row - col)
            board[row][col] = 'Q'

            backtrack(row + 1)

            cols.remove(col)
            pos_diag.remove(row + col)
            neg_diag.remove(row - col)
            board[row][col] = '.'

    backtrack(0)
    return results

# Combination Sum — duplicate olmayan
def combination_sum(candidates, target):
    result = []
    def backtrack(start, path, remaining):
        if remaining == 0:
            result.append(path[:])
            return
        for i in range(start, len(candidates)):
            if candidates[i] > remaining:
                break  # pruning (sorted olarsa)
            path.append(candidates[i])
            backtrack(i, path, remaining - candidates[i])  # eyni elementi yenidən istifadə et
            path.pop()
    candidates.sort()
    backtrack(0, [], target)
    return result

# Permutations with duplicates
def permutations_unique(nums):
    nums.sort()
    result = []
    used = [False] * len(nums)

    def backtrack(path):
        if len(path) == len(nums):
            result.append(path[:])
            return
        for i in range(len(nums)):
            if used[i]:
                continue
            # Duplicate skip: eyni rəqəmi eyni mövqedə yenidən sınamaq mənasızdır
            if i > 0 and nums[i] == nums[i-1] and not used[i-1]:
                continue
            used[i] = True
            path.append(nums[i])
            backtrack(path)
            path.pop()
            used[i] = False

    backtrack([])
    return result

# Sudoku Solver
def solve_sudoku(board):
    def is_valid(row, col, num):
        box_row, box_col = 3 * (row // 3), 3 * (col // 3)
        for i in range(9):
            if board[row][i] == num: return False
            if board[i][col] == num: return False
            if board[box_row + i//3][box_col + i%3] == num: return False
        return True

    def backtrack():
        for r in range(9):
            for c in range(9):
                if board[r][c] == '.':
                    for num in '123456789':
                        if is_valid(r, c, num):
                            board[r][c] = num
                            if backtrack(): return True
                            board[r][c] = '.'  # undo
                    return False  # heç bir rəqəm keçmədi
        return True  # bütün xanalar dolu

    backtrack()
```

## Praktik Tapşırıqlar
- LeetCode 46 — Permutations
- LeetCode 47 — Permutations II (duplicates)
- LeetCode 39 — Combination Sum
- LeetCode 40 — Combination Sum II
- LeetCode 51 — N-Queens
- LeetCode 37 — Sudoku Solver
- LeetCode 79 — Word Search
- LeetCode 131 — Palindrome Partitioning
- LeetCode 93 — Restore IP Addresses
- **Özünü yoxla**: `[1,2,3]` üçün bütün subsets-ı iki üsulla tap: include/exclude recursion + bitmask. Hər ikisini anlat.
- **Pruning tapşırığı**: Combination Sum-da sort + early break olmadan benchmark; fərqi ölç

## Əlaqəli Mövzular
- **DFS/BFS** — backtracking DFS-ə əsaslanır, state undo fərqiylə
- **Dynamic Programming** — üst-üstə düşən alt-problemlər varsa backtracking + memo = DP
- **Divide and Conquer** — rekursiya strukturu bənzərdir, amma məqsəd fərqlidir
- **Greedy** — backtracking hər şeyi araşdırır, greedy tək yol seçir
- **Graph Algorithms** — Hamilton path, graph coloring backtracking ilə
