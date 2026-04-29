# Complexity Cheatsheet (Lead)

## Konsept (Concept)

Bu fayl interview öncəsi **sürətli baxış** üçün nəzərdə tutulub. Bütün əsas data strukturları və alqoritmlərin time/space mürəkkəbliklərini bir yerdə toplayır.

### Böyük-O hiyerarxiyası (yaxşıdan pisə):
```
O(1)         — Constant
O(log n)     — Logarithmic
O(√n)        — Square root
O(n)         — Linear
O(n log n)   — Linearithmic (optimal comparison sort)
O(n²)        — Quadratic
O(n³)        — Cubic
O(2^n)       — Exponential
O(n!)        — Factorial
```

### Vizual müqayisə (n = 10):
```
O(1)        = 1
O(log n)    = ~3.3
O(√n)       = ~3.2
O(n)        = 10
O(n log n)  = ~33
O(n²)       = 100
O(2^n)      = 1024
O(n!)       = 3,628,800
```

## Data Structures

### Array (Dynamic Array)

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Access by index | O(1) | O(1) |
| Search | O(n) | O(n) |
| Insert (end) | O(1) amortized | O(n) |
| Insert (middle) | O(n) | O(n) |
| Delete (end) | O(1) | O(1) |
| Delete (middle) | O(n) | O(n) |

**Space**: O(n)

### Linked List (Singly)

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Access by index | O(n) | O(n) |
| Search | O(n) | O(n) |
| Insert (head) | O(1) | O(1) |
| Insert (tail, with pointer) | O(1) | O(1) |
| Insert (tail, no pointer) | O(n) | O(n) |
| Delete (head) | O(1) | O(1) |
| Delete (by value) | O(n) | O(n) |

**Space**: O(n)

### Doubly Linked List

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Access by index | O(n) | O(n) |
| Insert (head/tail) | O(1) | O(1) |
| Insert (after known node) | O(1) | O(1) |
| Delete (known node) | O(1) | O(1) |

**Space**: O(n) (amma hər node 2 pointer saxlayır)

### Stack

| Əməliyyat | Time |
|-----------|------|
| push | O(1) |
| pop | O(1) |
| top/peek | O(1) |
| isEmpty | O(1) |
| search | O(n) |

**Space**: O(n)

### Queue

| Əməliyyat | Time |
|-----------|------|
| enqueue | O(1) |
| dequeue | O(1) |
| front | O(1) |
| isEmpty | O(1) |

**Space**: O(n)

### Deque (Double-ended Queue)

| Əməliyyat | Time |
|-----------|------|
| pushFront / pushBack | O(1) |
| popFront / popBack | O(1) |
| front / back | O(1) |

**Space**: O(n)

### Hash Table (HashMap / HashSet)

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Insert | O(1) | O(n) |
| Delete | O(1) | O(n) |
| Search | O(1) | O(n) |
| Access by key | O(1) | O(n) |

**Space**: O(n)

**Qeyd**: Worst case bad hash function və ya collision-larla olur. Good hash function ilə Average case gözlənilir.

### Binary Search Tree (BST) — Unbalanced

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Search | O(log n) | O(n) |
| Insert | O(log n) | O(n) |
| Delete | O(log n) | O(n) |
| In-order traversal | O(n) | O(n) |
| Min/Max | O(log n) | O(n) |

**Space**: O(n)

### Balanced BST (AVL, Red-Black Tree)

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Search | O(log n) | O(log n) |
| Insert | O(log n) | O(log n) |
| Delete | O(log n) | O(log n) |

**Space**: O(n)

### Binary Heap (Min/Max Heap)

| Əməliyyat | Time |
|-----------|------|
| Find min/max | O(1) |
| Insert | O(log n) |
| Delete min/max | O(log n) |
| Heapify (build) | O(n) |
| Decrease/increase key | O(log n) |

**Space**: O(n)

### Trie (Prefix Tree)

| Əməliyyat | Time |
|-----------|------|
| Insert (length m) | O(m) |
| Search (length m) | O(m) |
| Prefix search | O(m) |
| Delete | O(m) |

**Space**: O(ALPHABET_SIZE × N × M) worst case

### Union-Find (Disjoint Set)

| Əməliyyat | With path compression + union by rank |
|-----------|-------------------------------------|
| find | O(α(n)) ≈ O(1) |
| union | O(α(n)) ≈ O(1) |

**Space**: O(n)

### B-Tree / B+ Tree

| Əməliyyat | Time |
|-----------|------|
| Search | O(log n) |
| Insert | O(log n) |
| Delete | O(log n) |

**Istifadə**: Database indexing, filesystem.

### Skip List

| Əməliyyat | Average | Worst |
|-----------|---------|-------|
| Search | O(log n) | O(n) |
| Insert | O(log n) | O(n) |
| Delete | O(log n) | O(n) |

### Bloom Filter

| Əməliyyat | Time |
|-----------|------|
| Insert | O(k) (k = hash funksiyalarının sayı) |
| Contains (probabilistic) | O(k) |

**Qeyd**: False positive var, false negative yoxdur.

## Sorting Algorithms

| Algorithm | Best | Average | Worst | Space | Stable | In-place |
|-----------|------|---------|-------|-------|--------|----------|
| Bubble Sort | O(n) | O(n²) | O(n²) | O(1) | Yes | Yes |
| Selection Sort | O(n²) | O(n²) | O(n²) | O(1) | No | Yes |
| Insertion Sort | O(n) | O(n²) | O(n²) | O(1) | Yes | Yes |
| Merge Sort | O(n log n) | O(n log n) | O(n log n) | O(n) | Yes | No |
| Quick Sort | O(n log n) | O(n log n) | O(n²) | O(log n) | No | Yes |
| Heap Sort | O(n log n) | O(n log n) | O(n log n) | O(1) | No | Yes |
| Counting Sort | O(n+k) | O(n+k) | O(n+k) | O(k) | Yes | No |
| Radix Sort | O(nk) | O(nk) | O(nk) | O(n+k) | Yes | No |
| Bucket Sort | O(n+k) | O(n+k) | O(n²) | O(n+k) | Yes | No |
| Tim Sort | O(n) | O(n log n) | O(n log n) | O(n) | Yes | No |
| Shell Sort | O(n log n) | O(n^1.25) | O(n²) | O(1) | No | Yes |

**Qeyd**:
- **Stable**: Bərabər elementlərin nisbi yeri dəyişmir.
- **In-place**: O(1) əlavə yaddaş istifadə edir.
- **k**: Counting/Radix üçün max value diapazonu.

### Python/PHP default sort-ları:
- **PHP sort()**: QuickSort və ya IntroSort (hybrid)
- **PHP usort()**: Tim sort (PHP 8+)
- **Python sort()**: Tim Sort, O(n log n) worst

## Searching Algorithms

| Algorithm | Best | Average | Worst | Space |
|-----------|------|---------|-------|-------|
| Linear Search | O(1) | O(n) | O(n) | O(1) |
| Binary Search | O(1) | O(log n) | O(log n) | O(1) |
| Jump Search | O(1) | O(√n) | O(√n) | O(1) |
| Interpolation Search | O(1) | O(log log n) | O(n) | O(1) |
| Exponential Search | O(1) | O(log n) | O(log n) | O(1) |
| BFS on Graph | O(V+E) | O(V+E) | O(V+E) | O(V) |
| DFS on Graph | O(V+E) | O(V+E) | O(V+E) | O(V) |

**Qeyd**: Binary search yalnız sortlanmış massivdə işləyir.

## Graph Algorithms

Burada V = vertex (təpə) sayı, E = edge (bağlantı) sayı.

| Algorithm | Time | Space | Qeyd |
|-----------|------|-------|------|
| BFS | O(V+E) | O(V) | Shortest path (unweighted) |
| DFS | O(V+E) | O(V) | Topological sort, cycles |
| Dijkstra (binary heap) | O((V+E) log V) | O(V) | Non-negative weights |
| Dijkstra (Fibonacci heap) | O(E + V log V) | O(V) | Theoretical best |
| Bellman-Ford | O(V·E) | O(V) | Negative weights OK |
| Floyd-Warshall | O(V³) | O(V²) | All-pairs shortest path |
| Prim's MST (heap) | O((V+E) log V) | O(V) | Dense graph-da yaxşı |
| Kruskal's MST | O(E log E) | O(V) | Sparse graph-da yaxşı |
| Topological Sort | O(V+E) | O(V) | DAG-da |
| Tarjan's SCC | O(V+E) | O(V) | Strongly Connected Components |
| Kosaraju's SCC | O(V+E) | O(V) | Iki DFS |
| A* Search | O(E) | O(V) | Heuristic ilə |
| Johnson's | O(V² log V + VE) | O(V²) | Sparse all-pairs |

### Shortest Path alqoritmlərinin müqayisəsi:

| Algorithm | Single-source | All-pairs | Negative edges | Negative cycles |
|-----------|---------------|-----------|----------------|-----------------|
| BFS | Yes (unweighted) | No | N/A | N/A |
| Dijkstra | Yes | No | No | No |
| Bellman-Ford | Yes | No | Yes | Detect |
| Floyd-Warshall | No | Yes | Yes | Detect |

## Dynamic Programming Patterns

| Problem Type | Time | Space |
|--------------|------|-------|
| 0/1 Knapsack | O(nW) | O(nW) or O(W) |
| Unbounded Knapsack | O(nW) | O(W) |
| Longest Common Subsequence | O(mn) | O(mn) or O(min(m,n)) |
| Longest Increasing Subsequence | O(n²) standard, O(n log n) optimal | O(n) |
| Edit Distance | O(mn) | O(mn) or O(n) |
| Matrix Chain Multiplication | O(n³) | O(n²) |
| Coin Change | O(amount · coins) | O(amount) |
| Subset Sum | O(n · sum) | O(sum) |
| Fibonacci | O(n) | O(1) |
| Longest Palindromic Subsequence | O(n²) | O(n²) |
| Word Break | O(n²) | O(n) |

## String Algorithms

| Algorithm | Time | Space |
|-----------|------|-------|
| Naive String Match | O(nm) | O(1) |
| KMP | O(n+m) | O(m) |
| Rabin-Karp | O(n+m) average | O(1) |
| Boyer-Moore | O(n/m) best | O(m+σ) |
| Z-algorithm | O(n+m) | O(n+m) |
| Longest Palindromic Substring (DP) | O(n²) | O(n²) |
| Manacher's Algorithm | O(n) | O(n) |
| Suffix Array (naive) | O(n² log n) | O(n) |
| Suffix Array (optimal) | O(n log n) | O(n) |
| Suffix Tree | O(n) | O(n) |
| Trie Operations | O(m) | O(ALPHABET × N × M) |

## Bit Manipulation

| Əməliyyat | Time |
|-----------|------|
| AND, OR, XOR, NOT | O(1) |
| Left/Right shift | O(1) |
| Count set bits (Brian Kernighan) | O(k) (k = set bits) |
| Check power of 2 | O(1) |
| Reverse bits | O(log n) |
| Gray code | O(1) |

## Array Patterns

| Pattern | Time | Space |
|---------|------|-------|
| Two Pointers | O(n) | O(1) |
| Sliding Window (fixed) | O(n) | O(1) or O(k) |
| Sliding Window (variable) | O(n) | O(k) |
| Prefix Sum (1D) | O(n) build, O(1) query | O(n) |
| Prefix Sum (2D) | O(mn) build, O(1) query | O(mn) |
| Binary Search | O(log n) | O(1) |
| Monotonic Stack | O(n) | O(n) |
| Difference Array | O(1) update, O(n) query | O(n) |

## Recursion / Backtracking

| Problem | Time | Space |
|---------|------|-------|
| Permutations | O(n! · n) | O(n) |
| Combinations | O(C(n,k) · k) | O(k) |
| Subsets | O(2^n · n) | O(n) |
| N-Queens | O(n!) | O(n) |
| Sudoku Solver | O(9^(n·n)) | O(n·n) |
| Palindrome Partitioning | O(n · 2^n) | O(n) |

## Master Theorem (Divide & Conquer)

T(n) = aT(n/b) + f(n)

| Halı | Şərt | Nəticə |
|------|------|--------|
| Case 1 | f(n) = O(n^(c)) c < log_b(a) | T(n) = Θ(n^log_b(a)) |
| Case 2 | f(n) = Θ(n^(log_b(a))) | T(n) = Θ(n^log_b(a) · log n) |
| Case 3 | f(n) = Ω(n^(c)) c > log_b(a) | T(n) = Θ(f(n)) |

### Tipik misallar:
- **Merge Sort**: T(n) = 2T(n/2) + O(n) → O(n log n)
- **Binary Search**: T(n) = T(n/2) + O(1) → O(log n)
- **Karatsuba**: T(n) = 3T(n/2) + O(n) → O(n^log2(3)) ≈ O(n^1.585)

## Space Complexity təsnifatı

### In-place (O(1) extra):
- Bubble, Selection, Insertion, Heap sort
- Reverse array, Rotate array
- Two pointers patterns

### O(log n) space:
- Recursive binary search
- Quick sort (average case, recursion stack)

### O(n) space:
- Merge sort
- BFS/DFS (visited set)
- Hash-based algorithms
- DP with 1D state

### O(n²) space:
- 2D DP (edit distance, LCS)
- Adjacency matrix
- Floyd-Warshall

## Tipik Məsələlər üçün Optimal Yanaşmalar

| Problem | Optimal Yanaşma | Complexity |
|---------|-----------------|------------|
| Kth largest element | Quick select / Heap | O(n) avg / O(n log k) |
| Top K frequent | HashMap + Heap | O(n log k) |
| Two sum | HashMap | O(n) |
| Longest substring no-repeat | Sliding window | O(n) |
| Valid parentheses | Stack | O(n) |
| Merge K sorted lists | Min heap | O(n log k) |
| Median of data stream | Two heaps | O(log n) insert |
| LRU Cache | LinkedHashMap / DLL + HashMap | O(1) |
| Word ladder | BFS | O(N·L²) |
| Shortest path in grid | BFS | O(mn) |
| Islands count | DFS/BFS/Union-Find | O(mn) |
| Subarray sum = K | Prefix sum + HashMap | O(n) |

## Interview Sualları

1. **Niyə quick sort worst case O(n²)-dir?**
   - Pivot həmişə ən kiçik/böyük seçiləndə. Random/median-of-three pivot seçimi bunu önləyir.

2. **Merge sort in-place ola bilməz?**
   - Bəli, lakin O(n log² n) time olur, praktikada qeyri-effektivdir.

3. **Hash table-da worst case niyə O(n)-dir?**
   - Bütün açarlar eyni bucket-a düşsə, linked list şəklinə düşür.

4. **HashMap vs Balanced BST: nə zaman hansı?**
   - **HashMap**: Average O(1), lakin sırasız.
   - **BST**: O(log n), ardıcıl iterasiya mümkün, range queries.

5. **Dijkstra negative weight ilə niyə işləmir?**
   - Greedy choice pozulur: bir node ziyarət olunduqdan sonra onun distance-ı yeniden kiçildilə bilər.

6. **Recursion vs iterative complexity fərqi?**
   - Recursion stack O(depth) əlavə yaddaş istifadə edir.

7. **Hansı sorting algorithm-i default seçilir?**
   - Tim sort (Python, Java, PHP 8+): stable, O(n log n), real dataset-lərdə yaxşı.

8. **O(log n) algoritm harada görürük?**
   - Binary search, balanced tree operations, heap operations, divide and conquer.

9. **Amortized analysis nədir?**
   - Hər əməliyyatın ortalama cost-u. Məsələn, dynamic array resize O(n) amma amortized O(1).

10. **Big-O vs Big-Θ vs Big-Ω?**
    - **O**: Upper bound (at most)
    - **Θ**: Tight bound (exactly)
    - **Ω**: Lower bound (at least)

## PHP/Laravel ilə Əlaqə

### PHP daxili funksiyalarının complexity-si:

| Function | Complexity |
|----------|-----------|
| `count($array)` | O(1) |
| `array_push` / `array_pop` | O(1) amortized |
| `array_shift` / `array_unshift` | O(n) |
| `in_array` | O(n) |
| `isset($map[$key])` / `array_key_exists` | O(1) |
| `array_merge` | O(n+m) |
| `array_diff` | O(n × m) |
| `array_intersect` | O(n × m) |
| `sort` / `usort` | O(n log n) |
| `array_search` | O(n) |
| `array_unique` | O(n²) (pre PHP 8) / O(n log n) |
| `array_reverse` | O(n) |
| `array_slice` | O(n) |
| `array_map` / `array_filter` | O(n) |
| `str_contains` / `strpos` | O(n) |
| `preg_match` | depends on regex |

### Laravel query performance:
```php
// O(n) queries (n+1 problem) - PIS
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // Her iterasiyada yeni query
}

// O(1) query + O(n) processing - YAXSI
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count();
}
```

### Database index complexity:
- **B-tree index**: O(log n) lookup
- **Hash index**: O(1) equal lookup, no range
- **Full-text index**: variable
- **Composite index**: left-most prefix rule

### Redis complexity:
- `GET/SET/DEL` — O(1)
- `HGET/HSET` — O(1)
- `LPUSH/RPUSH/LPOP/RPOP` — O(1)
- `LRANGE` — O(N)
- `SADD/SREM` — O(1)
- `SINTER/SUNION` — O(N × M)
- `ZADD` — O(log N)
- `ZRANGEBYSCORE` — O(log N + M)

### Optimizasiya taktikaları:
1. **N+1 query** → eager loading
2. **O(n²) loop** → hash map O(n)
3. **Unindexed WHERE** → add index
4. **Large OFFSET** → cursor pagination (keyset)
5. **Unbounded query** → LIMIT əlavə et
6. **Synchronous heavy task** → queue job

### Interview hazırlıq ipuçları:
- **O(1), O(log n), O(n), O(n log n), O(n²)** — ən çox rast gələnlər
- Hər problem üçün **brute force → optimize** yolu düşün
- **Space vs time trade-off** ilə hazır ol (hash map ilə O(n²) → O(n))
- **Tipik pattern-ləri tanı**: two pointers, sliding window, DP, BFS/DFS
- **Həll etdikdən sonra** complexity-ni dəqiq say və optimizasiya yollarını göstər

---

## Praktik Tapşırıqlar

1. **LeetCode 56** — Merge Intervals (O(n log n) — sort + O(n) scan = niyə log n?)
2. **LeetCode 200** — Number of Islands (O(n×m) — hər cell bir dəfə ziyarət)
3. **LeetCode 23** — Merge K Sorted Lists (O(N log K) — N element, K heap-də)
4. **LeetCode 322** — Coin Change (O(amount × coins) — DP table complexity)
5. **LeetCode 46** — Permutations (O(n! × n) — niyə n! deyil n×n!?)

### Step-by-step: Complexity estimation workflow

```
Problem: "n ədəd var, hər cüt pair-in cəmini tap"

Naive: iki loop → O(n²) — n=10⁴ → 10⁸ op → TLE
Better: sort + two pointers → O(n log n) — n=10⁴ → ~10⁵ op ✓
Hash: O(n) time, O(n) space — trade-off

Qayda:
  n ≤ 10:   O(n!) backtracking ✓
  n ≤ 20:   O(2^n) bitmask DP ✓
  n ≤ 500:  O(n³) DP ✓
  n ≤ 5000: O(n²) ✓
  n ≤ 10⁵:  O(n log n) ✓
  n ≤ 10⁶:  O(n) lazım ✓
```

---

## Əlaqəli Mövzular

- [01-big-o-notation.md](01-big-o-notation.md) — Big O hesablama qaydaları
- [41-leetcode-patterns.md](41-leetcode-patterns.md) — Pattern → complexity uyğunlaşdırması
- [42-interview-strategy.md](42-interview-strategy.md) — Interview zamanı complexity analizi
