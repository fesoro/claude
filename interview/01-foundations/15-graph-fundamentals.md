# Graph Fundamentals (Senior ⭐⭐⭐)

## İcmal

Graph — node-lar (vertices) və onları birləşdirən edge-lərdən ibarət data structure-dur. Tree-dən fərqli olaraq cycle-lar ola bilər, root yoxdur, hər node-dan hər node-a gedilə bilər (ya da gedilməyə bilər). Interview-larda graph sualları BFS/DFS-dən topological sort-a, Dijkstra-dan union-find-ə qədər geniş bir spektri əhatə edir. Graph-ı tanımaq interview-da ən kritik bacarıqlardan biridir — çünki "grid", "network", "dependency" kimi problem açar sözlər arxasında graph gizlənir.

## Niyə Vacibdir

Real dünya problemlərinin böyük qismi graph kimi modelləşdirilir: sosial şəbəkələr (Facebook friendship graph), routing (internet packet routing — Dijkstra), dependency management (npm packages, CI/CD pipeline — topological sort), recommendation systems (Netflix collab filtering), geographic maps (Google Maps). Senior backend developer kimi database schema relationships, microservice dependency graphs, cache invalidation graphs — hamısı graph problemidir. Google, Meta, Uber texniki interview-larında graph sualları mütləq olur.

## Əsas Anlayışlar

### Graph Terminologiyası

- **Vertex / Node**: Graph-ın elementi. `V` — bütün vertex-lərin sayı.
- **Edge**: İki node arasındakı bağlantı. `E` — bütün edge-lərin sayı.
- **Directed (digraph)**: Əlaqə bir istiqamətlidir. `A → B` A-dan B-yə gedir, B-dən A-ya yox.
- **Undirected**: Əlaqə iki tərəflidir. `A - B` deməkdir `A → B` və `B → A`.
- **Weighted**: Hər edge-in çəkisi (ağırlığı) var. Ağırlıqsız = weight=1.
- **Degree**: Node-un neçə edge-ə bağlı olduğu. Undirected: sadəcə `degree`.
- **In-degree / Out-degree**: Directed graph-da node-a gələn / çıxan edge sayı.
- **Path**: Edge-lər vasitəsilə bir node-dan başqasına gedən ardıcıllıq.
- **Simple Path**: Eyni node-u iki dəfə keçməyən path.
- **Cycle**: Eyni node-a qayıdan path (simple cycle: başlanğıc = son).
- **Connected Component**: Bir-birindən çatıla bilən node-lar qrupu. Bütün node-lardan başlamaq lazımdır.
- **DAG**: Directed Acyclic Graph — cycle olmayan directed graph. Topological sort buradan.
- **Dense graph**: `E ≈ V²`. **Sparse graph**: `E ≈ V`. Representation seçimi bundan asılıdır.
- **Bipartite graph**: Node-lar iki qrupa bölünür, edge-lər yalnız qruplar arasında.

### Graph Representation

**1. Adjacency List** (əksər hallarda optimal):
```python
graph = {
    'A': ['B', 'C'],
    'B': ['D'],
    'C': ['D', 'E'],
}
# Weighted: {A: [(B, 3), (C, 5)], ...}
```
- Space: O(V + E).
- Edge existence check: O(degree).
- Iterate neighbors: O(degree) — efficient.
- Sparse graph üçün ideal. Interview-da default seçim.

**2. Adjacency Matrix**:
```python
matrix = [[0]*n for _ in range(n)]
matrix[i][j] = 1  # i → j edge var
matrix[i][j] = weight  # weighted version
```
- Space: O(V²).
- Edge existence check: O(1).
- Dense graph ya quick edge check lazımdırsa.
- Interview-da matrix grid-lər üçün natural (hər hüceyrə node).

**3. Edge List**: `[(u, v, weight), ...]`.
- Kruskal's algorithm kimi alqoritmlər üçün (bütün edge-ləri sort etmək lazımdır).
- Space: O(E).

### BFS (Breadth-First Search)

- Queue ilə level-by-level axtarış.
- **Nə vaxt**: Shortest path (unweighted), level-order traversal, "minimum steps".
- Visited set-i unutma — infinite loop.
- O(V + E) time, O(V) space (queue + visited).

```python
from collections import deque

def bfs(graph, start):
    visited = {start}
    queue = deque([start])
    while queue:
        node = queue.popleft()
        for neighbor in graph[node]:
            if neighbor not in visited:
                visited.add(neighbor)
                queue.append(neighbor)
```

### DFS (Depth-First Search)

- Stack/recursion ilə dərinfə axtarış.
- **Nə vaxt**: Cycle detection, topological sort, connected components, all paths, backtracking.
- O(V + E) time, O(V) space (call stack/recursion stack).

```python
def dfs(graph, node, visited=None):
    if visited is None:
        visited = set()
    visited.add(node)
    for neighbor in graph[node]:
        if neighbor not in visited:
            dfs(graph, neighbor, visited)
```

### Cycle Detection

**Undirected graph — DFS:**
- Visited node-un qonşusu parent-dən başqa ziyarət edilmişdirsə → cycle.
- Parent-i parameter kimi keçir.

**Directed graph — DFS with 3 colors (states):**
- `WHITE (0)`: Ziyarət edilməyib.
- `GRAY (1)`: DFS-dədir (recursion stack-dədir).
- `BLACK (2)`: DFS tamamlanıb.
- GRAY node-a GRAY yoldan çatılırsa → back edge = cycle.
- Bu fərq kritikdir: undirected-də parent check, directed-də color check.

### Topological Sort

- Yalnız DAG-da mümkündür. Cycle varsa topological sort yoxdur.
- "İlk A, sonra B" kimi dependency sırası. Build system, course prerequisites.
- **DFS-based**: Post-order bitən node-ları stack-ə at, stack-i reverse et. O(V+E).
- **Kahn's (BFS-based)**: In-degree 0 olan node-lardan başla. In-degree 0 olan-ları queue-ya at, işlədikdə qonşuların in-degree-sini azalt. Cycle varsa: bütün node-lar işlənmir. O(V+E).
- Interview-da Kahn's daha aydındır (BFS loop, az recursive), cycle detection da avtomatik.

### Shortest Path Alqoritmləri

- **BFS**: Unweighted graph-da. O(V + E).
- **Dijkstra**: Non-negative weighted graph. O((V+E) log V) min-heap priority queue ilə. Greedy.
- **Bellman-Ford**: Negative edge-lər olan graph. O(V×E). Negative cycle detection.
- **Floyd-Warshall**: Bütün node cütlər arasında shortest path. O(V³).
- **0-1 BFS**: Weight yalnız 0 ya 1 olarsa — deque ilə O(V+E).

### Dijkstra Algorithm (Detallar)

- Min-heap (priority queue) ilə. `(distance, node)` tuples.
- `dist[src] = 0`, bütün digərləri ∞.
- Hər addımda ən kiçik `dist` olan node-u işlə (extract-min).
- Qonşuları `dist[node] + weight` ilə relaxation et.
- Stale entry: `d > dist[u]` olarsa skip et (heap-də köhnə entry qalmış ola bilər).
- Negative edge: Dijkstra fail edir çünkü "finalized" node-un distansı sonra azala bilər.

### Minimum Spanning Tree (MST)

- Bütün node-ları birləşdirən minimum total weight-li edge qrupu.
- Yalnız undirected weighted graph-da. MST V-1 edge-dən ibarətdir.
- **Kruskal**: Edge-ləri weight-ə görə sort et, Union-Find ilə cycle yoxla, cycle yoxdursa əlavə et. O(E log E).
- **Prim**: Dijkstra-ya bənzər, min-heap ilə. Bir node-dan genişlən. O(E log V).
- Seçim: Sparse → Kruskal, Dense → Prim.

### Union-Find (Disjoint Set Union — DSU)

- Elementlər hansı group-a aiddir?
- `find(x)`: x-in root-unu tap. Path compression ilə.
- `union(x, y)`: x və y-nin qruplarını birləşdir. Union by rank/size ilə.
- Path compression + union by rank: amortized O(α(n)) ≈ O(1).
- **Tətbiqlər**: Connected components, cycle detection (undirected), Kruskal's MST.

### Strong Connected Components (SCC)

- Directed graph-da: Hər iki node-dan bir-birinə çatıla bilən maksimal alt-qraflar.
- **Kosaraju**: 2 DFS (original + transposed). O(V+E).
- **Tarjan**: Bir DFS, low-link values, stack. O(V+E).
- Tətbiq: Compiler optimization (dead code elimination), web crawling.

### Bipartite Graph Detection

- Node-lar iki qrupa bölünür: heç bir edge eyni qrupdakı node-ları birləşdirmir.
- BFS/DFS ilə 2 rənglə yoxlama. Conflict olarsa → not bipartite.
- Odd cycle varsa → not bipartite.
- Tətbiq: Matching problemləri (job assignment), scheduling.

### Euler Path / Hamiltonian Path

- **Euler path**: Hər edge-i bir dəfə keçən path. Şərt: 0 ya 2 odd-degree vertex (undirected).
- **Euler circuit**: Hər edge-i bir dəfə keçib başlanğıca qayıdan. Şərt: bütün vertex-lər even-degree.
- **Hamiltonian path**: Hər vertex-i bir dəfə keçən path. NP-complete.
- TSP (Traveling Salesman): Hamiltonian circuit + minimum weight. NP-hard.

### Grid as Graph

- Hər `(row, col)` hüceyrəsi node, 4 qonşu (ya 8) edge-dir.
- BFS: Shortest path grid-də (unweighted).
- DFS: Island counting, flood fill, connected regions.
- Visited-i 2D array ilə izlə ya hüceyrəni `'#'` ilə işarələ.

## Praktik Baxış

### Interview-a Yanaşma

Graph sualı görüncə soruşulacaq 4 sual:
1. Directed ya undirected?
2. Weighted ya unweighted?
3. Cycle ola bilərmi?
4. Disconnected ola bilərmi?

Bu dörd sual həllin istiqamətini müəyyən edir. Matrix grid-lər də graph-dır: hər hüceyrə node, qonşular edge. "Dependency", "prerequisite", "network" sözlərini görəndə graph düşün.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Bu graph-da cycle varmı? Necə yoxlarsınız?" — directed vs undirected fərq.
- "Topological sort-u izah edin. Nə zaman istifadə olunur?" — dependency sıralama.
- "Dijkstra-nın negative edge-lərlə işləmədiyini niyə bilirsiniz?" — invariant izah.
- "Union-Find-i implement edin" — path compression + rank.
- "Bu problemi BFS ilə həll etdiniz. Dijkstra niyə lazım deyil?" — unweighted = all edges weight 1.
- "MST-ni niyə istifadə edirik? Real nümunə?" — network cable minimum cost.
- "Bipartite yoxlamasını DFS-dən başqa necə edərdiniz?" — BFS coloring.

### Common Mistakes

- Directed graph-da undirected BFS/DFS yazmaq (ya əksi) — edge yönünü nəzərə almamaq.
- Disconnected graph-da yalnız bir node-dan başlamaq — hamısı ziyarət olunmaya bilər.
- Cycle detection üçün directed graph-da visited yalnız `True/False` — gray/black state lazımdır.
- Dijkstra-da stale entry yoxlamasını (`d > dist[u]`) unutmaq — heap-dən köhnə entry gəlir.
- Weighted graph-da BFS ilə shortest path tapmağa cəhd etmək — yalnız unweighted-də doğru.
- Union-Find-də path compression ya union by rank-ı unutmaq — O(n) əvəzinə O(log n) ya O(α(n)).
- Kahn's-da in-degree-ni yanlış hesablamaq.

### Yaxşı → Əla Cavab

- **Yaxşı cavab**: BFS/DFS düzgün tətbiq edir, visited saxlayır.
- **Əla cavab**: Graph representation seçimini izah edir (adjacency list vs matrix), directed vs undirected cycle detection fərqini bilir, Dijkstra-nı implement edə bilir, topological sort-un iki variantını bilir, Union-Find path compression + rank izah edir, real production use case-lər verir.

### Real Production Ssenariləri

- npm/yarn dependency graph → topological sort (circular dependency detection).
- Kubernetes pod dependency → DAG, topological sort.
- Google Maps shortest route → Dijkstra (+ A*).
- Social network friend recommendation → BFS (2 hops away).
- Fraud detection → graph anomaly detection.
- Compiler dead code elimination → SCC.

## Nümunələr

### Tipik Interview Sualı

"N kurs var, `prerequisites` siyahısı verilmişdir: `[a, b]` deməkdir b-ni tamamlamadan a-nı götürmək olmaz. Bütün kursları bitirə bilmək mümkündürmü? Bu 'Course Schedule' məsələsidir."

### Güclü Cavab

"Bu topological sort / cycle detection məsələsidir. Prerequisites directed graph-ı əmələ gətirir: `b → a`. Əgər bu graph-da cycle varsa — kursları bitirmək mümkün deyil. Kahn's algorithm ilə: in-degree hesabla, in-degree 0 olan kursları queue-ya at, işlədikdə qonşuların in-degree-sini azalt, 0-a düşəni queue-ya at. Sonda bütün kurslar işlənibsə — true, yoxdursa cycle var — false. O(V + E) time, O(V + E) space."

### Kod Nümunəsi

```python
from collections import defaultdict, deque
import heapq

# Course Schedule — Topological Sort (Kahn's BFS) — O(V+E)
def can_finish(num_courses: int, prerequisites: list[list[int]]) -> bool:
    in_degree = [0] * num_courses
    graph = defaultdict(list)
    for course, prereq in prerequisites:
        graph[prereq].append(course)
        in_degree[course] += 1
    queue = deque(c for c in range(num_courses) if in_degree[c] == 0)
    completed = 0
    while queue:
        course = queue.popleft()
        completed += 1
        for next_course in graph[course]:
            in_degree[next_course] -= 1
            if in_degree[next_course] == 0:
                queue.append(next_course)
    return completed == num_courses

# Course Schedule II — topological order qaytarmaq
def find_order(num_courses: int, prerequisites: list[list[int]]) -> list[int]:
    in_degree = [0] * num_courses
    graph = defaultdict(list)
    for a, b in prerequisites:
        graph[b].append(a)
        in_degree[a] += 1
    queue = deque(i for i in range(num_courses) if in_degree[i] == 0)
    order = []
    while queue:
        node = queue.popleft()
        order.append(node)
        for nxt in graph[node]:
            in_degree[nxt] -= 1
            if in_degree[nxt] == 0:
                queue.append(nxt)
    return order if len(order) == num_courses else []

# Dijkstra — O((V+E) log V)
def dijkstra(graph: dict, src: int, n: int) -> list[int]:
    dist = [float('inf')] * n
    dist[src] = 0
    heap = [(0, src)]   # (distance, node)
    while heap:
        d, u = heapq.heappop(heap)
        if d > dist[u]:      # stale entry — skip
            continue
        for v, weight in graph[u]:
            if dist[u] + weight < dist[v]:
                dist[v] = dist[u] + weight
                heapq.heappush(heap, (dist[v], v))
    return dist

# Union-Find — path compression + union by rank — amortized O(α(n))
class UnionFind:
    def __init__(self, n: int):
        self.parent = list(range(n))
        self.rank = [0] * n
        self.components = n

    def find(self, x: int) -> int:
        if self.parent[x] != x:
            self.parent[x] = self.find(self.parent[x])   # path compression
        return self.parent[x]

    def union(self, x: int, y: int) -> bool:
        px, py = self.find(x), self.find(y)
        if px == py:
            return False   # artıq eyni component — cycle
        # union by rank
        if self.rank[px] < self.rank[py]:
            px, py = py, px
        self.parent[py] = px
        if self.rank[px] == self.rank[py]:
            self.rank[px] += 1
        self.components -= 1
        return True

# Number of Connected Components — Union-Find ilə — O(n*α)
def count_components(n: int, edges: list[list[int]]) -> int:
    uf = UnionFind(n)
    for u, v in edges:
        uf.union(u, v)
    return uf.components

# Directed Graph Cycle Detection — DFS with 3 colors
def has_cycle_directed(graph: dict, n: int) -> bool:
    WHITE, GRAY, BLACK = 0, 1, 2
    color = [WHITE] * n

    def dfs(node):
        color[node] = GRAY
        for neighbor in graph.get(node, []):
            if color[neighbor] == GRAY:
                return True   # back edge = cycle
            if color[neighbor] == WHITE:
                if dfs(neighbor):
                    return True
        color[node] = BLACK
        return False

    return any(dfs(i) for i in range(n) if color[i] == WHITE)

# Bipartite Check — BFS coloring — O(V+E)
def is_bipartite(graph: list[list[int]]) -> bool:
    n = len(graph)
    color = [-1] * n
    for start in range(n):
        if color[start] != -1:
            continue
        queue = deque([start])
        color[start] = 0
        while queue:
            node = queue.popleft()
            for neighbor in graph[node]:
                if color[neighbor] == -1:
                    color[neighbor] = 1 - color[node]
                    queue.append(neighbor)
                elif color[neighbor] == color[node]:
                    return False   # eyni rəngli qonşu = not bipartite
    return True

# Clone Graph — BFS — O(V+E)
from typing import Optional
class Node:
    def __init__(self, val: int = 0, neighbors: list = None):
        self.val = val
        self.neighbors = neighbors or []

def clone_graph(node: Optional[Node]) -> Optional[Node]:
    if not node:
        return None
    clones = {node: Node(node.val)}   # orijinal → klon map
    queue = deque([node])
    while queue:
        curr = queue.popleft()
        for neighbor in curr.neighbors:
            if neighbor not in clones:
                clones[neighbor] = Node(neighbor.val)
                queue.append(neighbor)
            clones[curr].neighbors.append(clones[neighbor])
    return clones[node]

# Kruskal MST — O(E log E)
def kruskal_mst(n: int, edges: list[tuple]) -> int:
    edges.sort(key=lambda x: x[2])   # weight-ə görə sort
    uf = UnionFind(n)
    total = 0
    for u, v, w in edges:
        if uf.union(u, v):           # cycle yoxdur
            total += w
    return total
```

### İkinci Nümunə — Grid BFS

```python
# Number of Islands — BFS/DFS — O(m*n)
def num_islands(grid: list[list[str]]) -> int:
    if not grid:
        return 0
    rows, cols = len(grid), len(grid[0])
    count = 0

    def bfs(r, c):
        queue = deque([(r, c)])
        grid[r][c] = '0'   # visited işarəsi
        while queue:
            row, col = queue.popleft()
            for dr, dc in [(0,1),(0,-1),(1,0),(-1,0)]:
                nr, nc = row + dr, col + dc
                if 0 <= nr < rows and 0 <= nc < cols and grid[nr][nc] == '1':
                    grid[nr][nc] = '0'
                    queue.append((nr, nc))

    for r in range(rows):
        for c in range(cols):
            if grid[r][c] == '1':
                bfs(r, c)
                count += 1
    return count
```

## Praktik Tapşırıqlar

1. LeetCode #207: Course Schedule (Medium) — topological sort (Kahn's).
2. LeetCode #210: Course Schedule II (Medium) — topological order qaytarmaq.
3. LeetCode #743: Network Delay Time (Medium) — Dijkstra tətbiq et.
4. LeetCode #684: Redundant Connection (Medium) — Union-Find cycle detection.
5. LeetCode #133: Clone Graph (Medium) — BFS ile graph clone etmə.
6. LeetCode #323: Number of Connected Components (Medium) — Union-Find.
7. LeetCode #785: Is Graph Bipartite? (Medium) — BFS coloring.
8. LeetCode #200: Number of Islands (Medium) — grid BFS/DFS.
9. LeetCode #332: Reconstruct Itinerary (Hard) — Eulerian path, DFS.
10. Özünütəst: Dijkstra-nı negative weight-lər olan graph-da sınaqdan keçir. Niyə yanlış nəticə verir? Nümunəni çək.

## Əlaqəli Mövzular

- **BFS and DFS** — graph traversal-ın əsas alqoritmləri; burada tam kontekstlə tətbiq olunur.
- **Topological Sort** — DAG üçün DFS ya Kahn's BFS. Dependency resolution.
- **Union-Find** — connected components, cycle detection, MST (Kruskal's).
- **Shortest Path** — Dijkstra, Bellman-Ford, Floyd-Warshall ayrıca faylda.
- **Dynamic Programming** — DAG-da ən uzun/qısa yol DP variantıdır.
- **Backtracking** — bütün path-ları tapmaq üçün DFS + backtracking.
