# BFS and DFS Traversal (Middle ⭐⭐)

## İcmal
BFS (Breadth-First Search) — graph/tree-ni level-by-level (qatdan-qata) ziyarət edir, queue istifadə edir. DFS (Depth-First Search) — bir istiqamətdə mümkün qədər dərinə gedir, sonra geri qayıdır, stack (ya ya recursion) istifadə edir. Bu iki traversal alqoritmi graph, tree, matrix suallarının demək olar hamısının əsasını təşkil edir.

## Niyə Vacibdir
BFS/DFS ən universal alqoritm pattern-lərindən biridir. BFS: shortest path (unweighted), level-order, spreading problems (word ladder, rotten oranges). DFS: cycle detection, connected components, topological sort, backtracking. Google, Meta, Microsoft texniki interview-larında graph/matrix suallarında mütləq BFS ya DFS seçimi müzakirə olunur — "niyə bu sual üçün BFS-i seçdiniz?" sualı gəlir.

## Əsas Anlayışlar

### BFS (Breadth-First Search):
- **Data structure**: Queue (FIFO).
- **Qayda**: Hazırkı node-un bütün qonşularını queue-ya əlavə et, sonra növbəti işlə.
- **Xüsusiyyət**: Shortest path (unweighted graph-da). Level-order processing.
- **Visited set**: Cycle olan graph-larda ziyarət edilmişləri izlə.
- **Space**: O(w) — w = max width (ən geniş level). Dense graph-da O(V).
- **Time**: O(V + E) — hər vertex və edge bir dəfə ziyarət olunur.

### DFS (Depth-First Search):
- **Data structure**: Call stack (recursive) ya explicit stack (iterative).
- **Növlər**: Pre-order, In-order, Post-order (tree üçün).
- **Xüsusiyyət**: Cycle detection, path finding, backtracking, topological sort.
- **Space**: O(h) — h = height/depth. Dense, dərin graph-da O(V).
- **Time**: O(V + E).
- **Completed time**: DFS node-u tamamladığında (bütün qonşular ziyarət olunanda) post-order işlər.

### BFS vs DFS Müqayisəsi:
| Xüsusiyyət | BFS | DFS |
|---|---|---|
| Data structure | Queue | Stack / Recursion |
| Shortest path (unweighted) | Bəli | Xeyr |
| Memory | O(width) — geniş tree-də çox | O(depth) — dərin tree-də çox |
| Level-order | Təbii | Əlavə iş lazımdır |
| Cycle detect | Bəli | Bəli |
| Topological sort | Kahn's (in-degree) | Post-order DFS |
| All paths find | Xeyr (only shortest) | Bəli |
| Backtracking | Xeyr | Bəli |

### Graph-da Visited Tracking:
- **Tree-də**: Visited yoxlamaq lazım deyil (cycle yoxdur, parent-dən child-a gedilir).
- **Undirected graph-da**: Set ilə ziyarət edilənləri izlə.
- **Directed graph-da**: Cycle detection üçün "in progress" (grey) + "done" (black) state lazımdır.
- Visited-ı queue-ya əlavə edərkən (enqueue time) yoxla — dequeue time-da yox. Əks halda duplicate ziyarət.

### BFS Template:
```python
from collections import deque
def bfs(graph, start):
    visited = {start}
    queue = deque([start])
    while queue:
        node = queue.popleft()
        # node-u işlə
        for neighbor in graph[node]:
            if neighbor not in visited:
                visited.add(neighbor)    # enqueue zamanı əlavə et
                queue.append(neighbor)
```

### DFS Template (Recursive):
```python
def dfs(graph, node, visited=None):
    if visited is None:
        visited = set()
    visited.add(node)
    # node-u işlə
    for neighbor in graph[node]:
        if neighbor not in visited:
            dfs(graph, neighbor, visited)
```

### DFS Template (Iterative — Stack):
```python
def dfs_iterative(graph, start):
    visited = {start}
    stack = [start]
    while stack:
        node = stack.pop()
        # node-u işlə
        for neighbor in graph[node]:
            if neighbor not in visited:
                visited.add(neighbor)
                stack.append(neighbor)
```

### Matrix BFS — "Flood Fill" Pattern:
- 2D grid-də 4 istiqamət (up, down, left, right) ya 8 istiqamət.
- Başlanğıc hüceyrəni queue-ya at, ziyarət edilənlər olaraq işarələ.
- Hər dəfə qonşu hüceyrələrə genişlən.
- Boundary check: `0 <= r < rows and 0 <= c < cols`.
- "Rotten Oranges", "Number of Islands", "Pacific Atlantic Water Flow" bu pattern.

### Multi-Source BFS:
- Birdən çox başlanğıc nöqtəsi eyni anda queue-ya əlavə olunur.
- "Rotten Oranges": bütün çürük portağalları eyni anda queue-ya at.
- Minimum distance bütün mənbələrdən hesablanır — single source BFS-dən daha effektiv.
- "0/1 Matrix": bütün 0-ları əvvəlcə queue-ya at, 1-lərə məsafəni hesabla.

### Bipartite Check:
- BFS/DFS ilə ikiqöy boyama: qonşular fərqli rəng.
- Əgər qonşu eyni rənggə sahibdirsə — not bipartite.
- Graph disconnected ola bilər — hər vertex üçün başlamaq lazımdır.

### Topological Sort (Kahn's Algorithm — BFS variant):
- In-degree 0 olan node-lardan başla.
- Bütün qonşuların in-degree-sini azalt, 0-a düşəni queue-ya əlavə et.
- O(V + E). Cycle varsa — result.size() != V.

### Topological Sort (DFS Post-Order):
- Post-order DFS: node tamamlandıqda (bütün qonşular bitdikdə) stack-ə at.
- Stack-i reverse et → topological order.
- Cycle varsa → "in progress" node-a geri qayıdılır.

### Dijkstra — Weighted BFS:
- Priority queue (min-heap) istifadə edir.
- Hər addımda ən kiçik distance olan node-u işlə.
- `dist[v] = min(dist[v], dist[u] + weight(u,v))`.
- O((V + E) log V). Yalnız non-negative weights üçün.

### 0-1 BFS:
- Edge weight-ləri yalnız 0 ya 1 olduqda Dijkstra-dan sürətli.
- 0-weight edge-lər üçün deque-nun önünə at, 1-weight üçün arxaya.
- O(V + E) — priority queue lazım deyil.

### BFS/DFS seçiminin siqnalları:
- "Ən qısa yol" → BFS.
- "Bütün yollar" ya "ən uzun yol" → DFS.
- "Level-by-level" → BFS.
- "Cycle detection in directed graph" → DFS (grey node).
- "Topological sort" → DFS post-order ya BFS (Kahn's).
- "Backtracking" → DFS.
- "Connected components" → DFS ya BFS (hər ikisi işləyir).

## Praktik Baxış

**Interview-a yanaşma:**
BFS ya DFS seçimi üçün: "Shortest path lazımdırmı?" — BFS. "Bütün yolları/kombinasiyaları kəşf etmək lazımdırmı?" — DFS/backtracking. "Level-by-level işlər?" — BFS. "Cycle detection?" — DFS with colors.

**Nədən başlamaq lazımdır:**
- Graph-ı necə temsil edersiniz: adjacency list, adjacency matrix, edge list.
- Visited set-i müəyyən et. Nə vaxt əlavə etmək lazımdır?
- BFS üçün queue, DFS üçün recursion ya stack seç.
- Multi-source BFS düşün: "Çox başlanğıc nöqtəsi var mı?"
- Edge case: disconnected graph, single node.

**Follow-up suallar:**
- "Ağacın ən qısa yolunu BFS ilə tapın."
- "Connected components-i sayın."
- "Bipartite graph-ı yoxlayın."
- "DFS-i iterative stack ilə yazın."
- "0/1 matrix-dəki hər hüceyrənin ən yaxın 0-a məsafəsini tapın."
- "Cycle in undirected/directed graph-da detect edin."
- "Topological sort nədir? DFS vs Kahn's fərqi?"
- "Dijkstra niyə negative edge-lərdə işləməyir?"

**Namizədlərin ümumi səhvləri:**
- Graph-da visited check-i unutmaq → sonsuz loop.
- BFS-in shortest path verməsinin yalnız unweighted graph-da keçərli olduğunu bilməmək.
- Matrix BFS-də boundary check-i unutmaq (index out of bounds).
- Visited-ı queue-ya əlavə edərkən deyil, queue-dan çıxararkən işarələmək — duplicates olur.
- DFS-in depth-i O(V) ola biləcəyini unutmaq (stack overflow).
- Disconnected graph-ı düzgün handle etməmək — yalnız bir component-i görür.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: BFS/DFS implement edir, nəticə qaytarır.
- Əla cavab: Hansı traversal-ın niyə seçildiyini izah edir, multi-source BFS-i bilir, visited-ı nə zaman əlavə etmək lazım olduğunu izah edir (queue-ya əlavə zamanı), weighted graph üçün Dijkstra qeyd edir, topological sort-u hər iki üsulla bilir.

## Nümunələr

### Tipik Interview Sualı
"Şəbəkəsi (m×n grid) verilmişdir. `1` torpaq, `0` su. Adaların sayını tapın. Bir ada — su ilə əhatə olunmuş torpaq sahəsidir (horizontal/vertical birləşmə). `[[1,1,0],[0,1,0],[0,0,1]]` → 2."

### Güclü Cavab
"Bu klassik 'Number of Islands' məsələsidir. Hər '1' görüncə DFS/BFS ilə bütün birləşik torpağı ziyarət edib işarələyirik (ya '0' ya ya visited set). Hər yeni ziyarət edilməmiş '1' yeni adanın başlanğıcıdır, sayğacı artırırıq. O(m×n) time, O(m×n) space (visited). DFS seçirəm çünki shortest path tələb olunmur, bütün birləşik hüceyrələri tapmaq lazımdır."

### Kod Nümunəsi
```python
from collections import deque

# Number of Islands — DFS — O(m*n) time and space
def num_islands(grid: list[list[str]]) -> int:
    if not grid:
        return 0
    rows, cols = len(grid), len(grid[0])
    count = 0

    def dfs(r, c):
        # Boundary check + water + visited check
        if r < 0 or r >= rows or c < 0 or c >= cols or grid[r][c] != '1':
            return
        grid[r][c] = '0'    # ziyarət edildi (in-place modify — original bərpa edilmir!)
        dfs(r+1, c); dfs(r-1, c)
        dfs(r, c+1); dfs(r, c-1)

    for r in range(rows):
        for c in range(cols):
            if grid[r][c] == '1':
                dfs(r, c)     # ada tapıldı — bütün birləşik torpağı ziyarət et
                count += 1
    return count

# Word Ladder — BFS Shortest Path — O(n * L²)
def ladder_length(begin_word: str, end_word: str, word_list: list[str]) -> int:
    word_set = set(word_list)
    if end_word not in word_set:
        return 0
    queue = deque([(begin_word, 1)])   # (word, steps)
    visited = {begin_word}
    while queue:
        word, steps = queue.popleft()
        if word == end_word:
            return steps
        for i in range(len(word)):
            for c in 'abcdefghijklmnopqrstuvwxyz':
                new_word = word[:i] + c + word[i+1:]
                if new_word in word_set and new_word not in visited:
                    visited.add(new_word)
                    queue.append((new_word, steps + 1))
    return 0

# Rotten Oranges — Multi-Source BFS — O(m*n)
def oranges_rotting(grid: list[list[int]]) -> int:
    rows, cols = len(grid), len(grid[0])
    queue = deque()
    fresh = 0
    # Bütün çürük portağalları eyni anda başla
    for r in range(rows):
        for c in range(cols):
            if grid[r][c] == 2:
                queue.append((r, c, 0))    # çürük portağallar
            elif grid[r][c] == 1:
                fresh += 1
    if fresh == 0:
        return 0
    max_time = 0
    directions = [(0,1),(0,-1),(1,0),(-1,0)]
    while queue:
        r, c, time = queue.popleft()
        for dr, dc in directions:
            nr, nc = r + dr, c + dc
            if 0 <= nr < rows and 0 <= nc < cols and grid[nr][nc] == 1:
                grid[nr][nc] = 2    # çürüdü
                fresh -= 1
                max_time = max(max_time, time + 1)
                queue.append((nr, nc, time + 1))
    return max_time if fresh == 0 else -1

# Course Schedule — DFS Cycle Detection — O(V+E)
def can_finish(num_courses: int, prerequisites: list[list[int]]) -> bool:
    graph = [[] for _ in range(num_courses)]
    for course, prereq in prerequisites:
        graph[prereq].append(course)

    # 0: unvisited, 1: in progress (cycle check), 2: done
    state = [0] * num_courses

    def dfs(node):
        if state[node] == 1:   # cycle detected!
            return False
        if state[node] == 2:   # artıq işlənib
            return True
        state[node] = 1        # işlənir
        for neighbor in graph[node]:
            if not dfs(neighbor):
                return False
        state[node] = 2        # tamamlandı
        return True

    return all(dfs(i) for i in range(num_courses))

# Bipartite Check — BFS coloring — O(V+E)
def is_bipartite(graph: list[list[int]]) -> bool:
    color = {}
    for start in range(len(graph)):
        if start in color:
            continue
        color[start] = 0
        queue = deque([start])
        while queue:
            node = queue.popleft()
            for neighbor in graph[node]:
                if neighbor not in color:
                    color[neighbor] = 1 - color[node]   # fərqli rəng
                    queue.append(neighbor)
                elif color[neighbor] == color[node]:
                    return False   # eyni rəng — not bipartite
    return True
```

### İkinci Nümunə — Topological Sort

**Sual**: N kurs var, bəziləri ön şərt tələb edir. Kursların mümkün sırasını tapın. `numCourses = 4, prerequisites = [[1,0],[2,0],[3,1],[3,2]]` → `[0,1,2,3]` ya `[0,2,1,3]`.

**Cavab**: Kahn's BFS topological sort. In-degree 0 olan node-lardan başla.

```python
from collections import deque
def find_order(num_courses: int, prerequisites: list[list[int]]) -> list[int]:
    graph = [[] for _ in range(num_courses)]
    in_degree = [0] * num_courses
    for course, prereq in prerequisites:
        graph[prereq].append(course)
        in_degree[course] += 1

    # In-degree 0 olan kursları başlat
    queue = deque([i for i in range(num_courses) if in_degree[i] == 0])
    order = []

    while queue:
        node = queue.popleft()
        order.append(node)
        for neighbor in graph[node]:
            in_degree[neighbor] -= 1
            if in_degree[neighbor] == 0:
                queue.append(neighbor)

    return order if len(order) == num_courses else []
    # Cycle varsa bütün kurslar tamamlanmır
```

## Praktik Tapşırıqlar
- LeetCode #200: Number of Islands (Medium) — DFS/BFS seçimi. İkisini də sına.
- LeetCode #127: Word Ladder (Hard) — BFS shortest path. Bidirectional BFS optimize edir.
- LeetCode #994: Rotting Oranges (Medium) — multi-source BFS.
- LeetCode #102: Binary Tree Level Order Traversal (Medium) — tree BFS.
- LeetCode #785: Is Graph Bipartite? (Medium) — BFS coloring.
- LeetCode #207: Course Schedule (Medium) — DFS cycle detection / topological sort.
- LeetCode #210: Course Schedule II (Medium) — topological order qaytarır.
- LeetCode #133: Clone Graph (Medium) — BFS/DFS graph clone. HashMap + traversal.
- Özünütəst: DFS iterative (explicit stack) ilə yazın, recursive-dən fərqini izah edin.

## Əlaqəli Mövzular
- **Graph Fundamentals** — BFS/DFS graph traversal-ın əsas alqoritmləridir.
- **Binary Tree** — tree traversal-lar DFS-in xüsusi hallarıdır.
- **Stack and Queue** — BFS queue, DFS stack istifadə edir.
- **Topological Sort** — DFS (post-order) ya da Kahn's BFS.
- **Shortest Path** — Dijkstra weighted BFS variantıdır.
