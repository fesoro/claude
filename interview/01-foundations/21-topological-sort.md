# Topological Sort (Senior ⭐⭐⭐)

## İcmal
Topological sort — DAG (Directed Acyclic Graph) üzərindəki linear ordering-dir: hər u→v edge üçün u, v-dən əvvəl gəlir. Yalnız DAG-larda mümkündür — cycle varsa topological order mövcud deyil. İki klassik alqoritm var: Kahn (BFS-based) və DFS-based. Task scheduling, build systems, package dependency, course prerequisites bu data structure-un real dünya tətbiqləridir.

## Niyə Vacibdir
Topological sort dependency resolution-ın fundamental alqoritmidir. Webpack, Gradle, Cargo, npm — hamısı dependency graph üzərində topological sort işledir. Kubernetes-də pod startup order, CI/CD pipeline-da task order da eyni ideyaya əsaslanır. FAANG interview-larında "prerequisites", "tasks", "dependencies" sözlərini gördükdə topological sort işarəsidir. Senior rol üçün: yalnız alqoritmi bilmək deyil, cycle detection, multiple valid orderings, parallel execution imkanlarını da izah etmək lazımdır.

## Əsas Anlayışlar

### DAG (Directed Acyclic Graph)
- Directed: edge-lər istiqamətlidir (u→v, amma v→u yoxdur)
- Acyclic: cycle yoxdur (A→B→C→A kimi dövr olmaz)
- Topological sort yalnız DAG-larda mövcuddur
- Cycle varsa → topological sort mümkün deyil → bunu detect etmək lazımdır

### Kahn Alqoritmi (BFS-based)
1. Hər node-un in-degree-sini hesabla (neçə edge ona gəlir)
2. In-degree = 0 olan bütün node-ları queue-ya əlavə et
3. Queue boşalana kimi:
   - Node-u queue-dan çıxar, nəticəyə əlavə et
   - Bu node-un bütün qonşularının in-degree-sini azalt
   - In-degree 0 olan qonşuları queue-ya əlavə et
4. Nəticənin uzunluğu < node sayıdırsa → cycle var

**Complexity**: O(V + E)

**Üstünlükləri**:
- Cycle detection asandır (nəticə tam deyil)
- BFS-based, iterative → stack overflow riski yoxdur
- Multiple valid orderings arasından seçim asandır (priority queue ilə lexicographic)

### DFS-based Topological Sort
1. Bütün node-ları "unvisited" kimi işarələ
2. Hər unvisited node üçün DFS çağır
3. DFS: node-u "visiting" (grey) et, qonşulara DFS çağır, node-u "visited" (black) et, stack-ə push et
4. Stack-i tərsinə çevir → topological order
5. "visiting" node-a geri gəlsən → cycle var (back edge)

**Complexity**: O(V + E)

**Üstünlükləri**:
- Recursion ilə daha elegant kod
- SCC (Strongly Connected Components) alqoritmlərinə (Tarjan, Kosaraju) əsas verir

### Cycle Detection Fərqi
| Kahn | DFS |
|------|-----|
| Nəticə.length < V olarsa cycle var | Back edge (grey→grey) varsa cycle var |
| BFS, iterative | DFS, recursive |
| Queue boşaldıqda bitir | Bütün node-lar "black" olduqda bitir |

### Multiple Valid Orderings
Çox vaxt birdən çox valid topological order mövcuddur. Məsələn, in-degree 0 olan 3 node varsa, hər üçünü istənilən sırada seçmək olar. Kahn alqoritminin queue-suna priority queue əlavə edərək lexicographic (ən kiçik) order seçmək olar.

### Longest Path in DAG
- Adi graph-larda Longest Path NP-hard-dır
- DAG-da topological order + DP ilə O(V+E) həll edilir
- `dp[v] = max(dp[u] + weight(u,v)) for all u→v`
- Critical path (longest path) — project scheduling üçün vacibdir

### Parallel Execution
- Topological sort qatlarını (layers/levels) müəyyən edir
- Eyni qatdakı task-lar paralel işlədilə bilər
- "Level-0": in-degree 0 node-lar
- "Level-1": Level-0 kənarlandıqdan sonra in-degree 0 olanlar
- Bu, critical path analysis-in əsasıdır (PERT/CPM)

### Real Tətbiq Nümunələri

**Build System (Gradle/Maven/Webpack)**
- A.java B.java-ya depend edir → B əvvəl compile edilməlidir
- Circular dependency → build fail

**Package Manager (npm/pip/Cargo)**
- Package A, Package B-ni require edir
- Install order: dependencies əvvəl
- Version conflicts + cycle → error

**Course Schedule (LeetCode 207)**
- Kurs A üçün Kurs B prerequisite → B əvvəl götürülməlidir
- Circular prerequisite → mümkünsüz

**Database Migration (Schema Changes)**
- Table B, Table A-ya foreign key saxlayır
- Create order: A əvvəl yaradılmalıdır
- Drop order: B əvvəl silinməlidir (tərsinə)

### Edge Cases
- **Isolated nodes** (edge yoxdur): hamısı in-degree 0, istənilən sırada gəlir
- **Multiple components**: Kahn hər komponentlə işləyir
- **Self-loop** (u→u): cycle-dır, topological sort mümkün deyil
- **Boş graph**: boş nəticə

## Praktik Baxış

### Interview Yanaşması
1. "Dependency", "prerequisite", "task order" sözlərini gördükdə topological sort düşün
2. Əvvəlcə graph-ı qurmağı izah et (adjacency list + in-degree)
3. Cycle detection-ı mütləq qeyd et — "cycle varsa nə baş verir?"
4. Kahn seç (BFS, daha anlaşıqlı), DFS alternativini bil

### Nədən Başlamaq
- Graph-ı qurmaq: `edges → adjacency list + in-degree array`
- Kahn: queue ilə BFS
- Sonunda: `len(result) == n` yoxla (cycle check)

### Ümumi Follow-up Suallar
- "Cycle var-yoxsa necə anlarsınız?"
- "Lexicographic topological order necə tapırsınız?"
- "Parallel execution üçün task levels-i necə müəyyən edərsiniz?"
- "Bu alqoritmi distributed sistemdə necə istifadə edərdiniz?" (event ordering)

### Namizədlərin Ümumi Səhvləri
- Cycle detection-ı unuduqda `len(result) < n` yoxlamasını etməmək
- In-degree array-i düzgün qurmamaq
- DFS-based approach-da "visiting"/"visited" fərqini qarışdırmaq (grey/black)
- Undirected graph-da topological sort tətbiq etməyə çalışmaq

### Yaxşı → Əla Cavab
- Yaxşı: Kahn alqoritmini implement edir, cycle detection edir
- Əla: DFS alternativini bilir, lexicographic order üçün priority queue-nu qeyd edir, parallel execution layers-i hesablayır, real build system nümunəsi verir

## Nümunələr

### Tipik Interview Sualı
"LeetCode 207 — Course Schedule: `numCourses` kurs var, `prerequisites[i] = [a, b]` — a-nı götürmək üçün əvvəlcə b lazımdır. Bütün kursları götürmək mümkündürmü?"

### Güclü Cavab
Bu problemi Kahn alqoritmi (BFS-based topological sort) ilə həll edərdim. Əvvəlcə directed graph qururam: `b → a` (b, a-nın prerequisite-i deməkdir). Sonra hər node üçün in-degree hesablayıram.

In-degree = 0 olan kursları (heç bir prerequisite olmayan) queue-ya əlavə edirəm. Kursları "tamamlaya-tamamlaya" queue-dan çıxarıram, qonşularının in-degree-sini azaldıram, sıfırlananları queue-ya əlavə edirəm.

Sonda: `tamamlanan kurs sayı == numCourses` → true. Deyilsə — cycle var, mümkünsüzdür.

Cycle detection avtomatikdir: əgər cycle varsa, o node-ların in-degree-si heç vaxt 0-a çatmır, queue-ya girmir.

Complexity: O(V + E) — V kurslar, E prerequisites.

### Kod Nümunəsi
```python
from collections import deque, defaultdict

# Kahn Alqoritmi
def can_finish(num_courses, prerequisites):
    graph = defaultdict(list)
    in_degree = [0] * num_courses

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

# Topological Sort nəticəsini qaytaran versiya (LeetCode 210)
def find_order(num_courses, prerequisites):
    graph = defaultdict(list)
    in_degree = [0] * num_courses

    for course, prereq in prerequisites:
        graph[prereq].append(course)
        in_degree[course] += 1

    queue = deque(c for c in range(num_courses) if in_degree[c] == 0)
    order = []

    while queue:
        course = queue.popleft()
        order.append(course)
        for next_course in graph[course]:
            in_degree[next_course] -= 1
            if in_degree[next_course] == 0:
                queue.append(next_course)

    return order if len(order) == num_courses else []

# DFS-based Topological Sort
def topological_sort_dfs(graph, n):
    WHITE, GREY, BLACK = 0, 1, 2
    color = [WHITE] * n
    result = []
    has_cycle = [False]

    def dfs(u):
        if has_cycle[0]: return
        color[u] = GREY
        for v in graph[u]:
            if color[v] == GREY:  # back edge → cycle
                has_cycle[0] = True
                return
            if color[v] == WHITE:
                dfs(v)
        color[u] = BLACK
        result.append(u)  # post-order

    for node in range(n):
        if color[node] == WHITE:
            dfs(node)

    if has_cycle[0]:
        return []
    return result[::-1]  # tərsinə çevir

# Parallel Task Execution Levels
def task_levels(n, prerequisites):
    """Hər level-da paralel işlənə bilən task-ları tap"""
    graph = defaultdict(list)
    in_degree = [0] * n

    for task, prereq in prerequisites:
        graph[prereq].append(task)
        in_degree[task] += 1

    levels = []
    queue = deque(t for t in range(n) if in_degree[t] == 0)

    while queue:
        level_size = len(queue)
        current_level = []
        for _ in range(level_size):
            task = queue.popleft()
            current_level.append(task)
            for next_task in graph[task]:
                in_degree[next_task] -= 1
                if in_degree[next_task] == 0:
                    queue.append(next_task)
        levels.append(current_level)

    return levels
```

## Praktik Tapşırıqlar
- LeetCode 207 — Course Schedule (cycle detection)
- LeetCode 210 — Course Schedule II (order qaytarmaq)
- LeetCode 269 — Alien Dictionary (topological sort + trie)
- LeetCode 310 — Minimum Height Trees
- LeetCode 444 — Sequence Reconstruction
- LeetCode 2115 — Find All Possible Recipes from Given Supplies
- **Design tapşırığı**: npm install əmrini simulate et — package dependency-lərini topological sort ilə həll et. Circular dependency-ni detect et və error mesajı ver
- **Parallel execution tapşırığı**: 10 task, dependency-lərlə verilmiş. Minimum neçə "round"-da hamısını tamamlamaq olar?

## Əlaqəli Mövzular
- **Graph DFS/BFS** — topological sort graph traversal-ın tətbiqidir
- **Union-Find** — cycle detection üçün alternativ (undirected graph-larda)
- **Shortest/Longest Path in DAG** — topological sort + DP
- **Dynamic Programming on DAG** — topological order dp üçün iteration sequence verir
- **SCC (Strongly Connected Components)** — Kosaraju, Tarjan — DFS-based topological sort istifadə edir
