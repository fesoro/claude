# Union-Find / Disjoint Set (Senior ⭐⭐⭐)

## İcmal
Union-Find (Disjoint Set Union, DSU) — elementləri disjoint set-lərə bölən və iki əməliyyatı effektiv yerinə yetirən data structure-dur: `find` (hansı set-ə aid?) və `union` (iki set-i birləşdir). Path compression + union by rank ilə hər iki əməliyyat amortized O(α(n)) — praktikada O(1)-ə bərabərdir (α — inverse Ackermann funksiyası). Cycle detection, connected components, Kruskal MST alqoritminin əsasıdır.

## Niyə Vacibdir
Union-Find real sistemlərdə connectivity problemlərini həll edir: social network-da "A və B eyni friend group-undadırmı?", network topology-də "A node-undan B node-una yol varmı?", distributed sistemdə "bu iki server eyni cluster-dadırmı?". LeetCode-da "number of connected components", "minimum spanning tree", "accounts merge" kimi sualların hamısı Union-Find ilə optimal həll olunur. Senior namizəd path compression və union by rank-ı niyə, necə seçəcəyini izah etməlidir.

## Əsas Anlayışlar

### Naive İmplementasiya (Path Compression olmadan)
- `parent[i] = i` ilə başla (hər element öz-özünün root-u)
- `find(x)`: `parent[x] == x` olana kimi yuxarı get → O(n) worst case
- `union(x, y)`: `parent[find(x)] = find(y)` → O(n) worst case
- Zəncirli tree-lərdə O(n) degradasiya

### Path Compression
`find(x)` zamanı tapdığımız root-u birbaşa hər ziyarət edilmiş node-un parent-i et:
```
find(x):
    if parent[x] != x:
        parent[x] = find(parent[x])  # ← path compression
    return parent[x]
```
Bu, tree-nin yastılaşdırılmasını (flattening) təmin edir. Növbəti `find` çağırışı O(1) olur.

### Union by Rank
Kiçik tree-ni böyük tree-nin altına qoy — tree-nin hündürlüyünü minimumda saxlayır:
```
union(x, y):
    rx, ry = find(x), find(y)
    if rx == ry: return False  # artıq eyni set
    if rank[rx] < rank[ry]: rx, ry = ry, rx
    parent[ry] = rx
    if rank[rx] == rank[ry]: rank[rx] += 1
    return True
```

### Union by Size (Rank-ın alternati)
Rank əvəzinə node sayını izlə — daha intuitiv:
```
if size[rx] < size[ry]: rx, ry = ry, rx
parent[ry] = rx
size[rx] += size[ry]
```

### Amortized Complexity
| Yanaşma | find | union |
|---------|------|-------|
| Naive | O(n) | O(n) |
| Yalnız path compression | O(log n) amortized | O(log n) |
| Yalnız union by rank | O(log n) | O(log n) |
| Hər ikisi | O(α(n)) | O(α(n)) |

α(n) — inverse Ackermann funksiyası, praktikada ≤ 4 (bütün ağlabatan n üçün). Həqiqətən O(1) deyil, amma praktikada heç fərq olmuyor.

### Cycle Detection in Undirected Graph
- Edge (u, v) əlavə etmədən əvvəl: `find(u) == find(v)` olarsa → cycle var
- Yoxdursa: `union(u, v)` et
- Kruskal MST-nin əsas mexanizmi

### Minimum Spanning Tree (Kruskal)
1. Bütün edge-ləri weight-ə görə sırala
2. Hər edge üçün: əgər iki vertex fərqli set-dədirsə → edge-i MST-yə əlavə et, `union` et
3. V-1 edge əlavə ediləndə bitir
4. Complexity: O(E log E) sort + O(E α(V)) union-find = O(E log E)

### Weighted Union-Find
- Hər node üçün root-a nisbətən weight saxla
- `find` zamanı weight-i yenilə (path compression ilə birlikdə)
- "Relative weight" query-lərini dəstəkləyir
- LeetCode 399 — Evaluate Division bu pattern-ə aiddir

### Rollback / Persistent Union-Find
- Standard path compression geri alına bilmir (rollback yoxdur)
- Rollback üçün yalnız union by rank istifadə et, path compression istifadə etmə
- Stack-ə `(node, old_parent, old_rank)` saxla, undo zamanı geri yaz
- Online alqoritmlər (offline query-lərin ardıcıllığı) üçün vacibdir

### 2D Grid Connectivity
- Grid (r, c) → `r * cols + c` ilə 1D index-ə çevir
- Hər cell üçün 4 istiqamətdə `union` et
- `num_islands` = union zamanı birləşən set sayı

### Bipartite Check
- `colorMap` + Union-Find birlikdə istifadə olunur
- Edge (u, v) üçün: `u` və `v` eyni set-dədirsə → bipartite deyil (odd cycle)
- Alternativ: BFS/DFS 2-coloring

## Praktik Baxış

### Interview Yanaşması
1. "Connected components", "same group", "cycle in undirected graph" → Union-Find
2. Əvvəlcə path compression + union by rank template-ini yaz
3. Əlavə feature lazım? (component count, component size) — ayrıca saxla
4. 2D grid-də index konvertasiyasını izah et

### Nədən Başlamaq
- `parent`, `rank`/`size` array-ləri ilə initialize et
- `find` (with path compression) + `union` (with rank) implement et
- Problem-specific: `num_components`, `component_size` saxlaya bilirsənmi?

### Ümumi Follow-up Suallar
- "Path compression olmadan complexity nə olar?"
- "Bu alqoritmi undirected vs directed graph-da necə istifadə edərsiniz?"
- "Rollback əməliyyatını necə dəstəklərdiniz?"
- "Kruskal vs Prim — hansı zaman daha yaxşıdır?"

### Namizədlərin Ümumi Səhvləri
- Path compression recursive implementasiyada geri qaytarma unutmaq
- `find` qaytardığı dəyəri parent ilə yox etmək (`parent[x] = find(parent[x])` deyil, `return find(parent[x])` yazmaq)
- `union` zamanı `find(x) == find(y)` (artıq eyni set) yoxlamasını unutmaq
- 2D grid-də index konvertasiyasını səhv etmək

### Yaxşı → Əla Cavab
- Yaxşı: path compression + union by rank implementasiyası, O(α(n)) deyir
- Əla: rollback-ı bilir, weighted Union-Find-i izah edir, Kruskal MST-ni implement edir, α(n)-nin nə olduğunu izah edir

## Nümunələr

### Tipik Interview Sualı
"LeetCode 684 — Redundant Connection: Undirected graph-da əlavə olunan son edge-i tapın ki, graph tree olsun. (Yəni cycle yaradan edge-i tap.)"

### Güclü Cavab
Bu problemi Union-Find ilə həll edərdim. Əgər hər edge-i əlavə etmədən əvvəl iki vertex-in eyni connected component-də olub-olmadığını yoxlasam — `find(u) == find(y)` true olarsa, bu edge cycle yaradır (artıq qoşulublar) və cavabdır.

Hər edge-i sıra ilə emal edirəm: `union(u, v)`. Əgər artıq eyni set-dədirsə, bu redundant edge-dir. Problem şərtinə görə yalnız bir belə edge var, onu qaytarıram.

Complexity: O(n · α(n)) ≈ O(n), çünki n node, n edge var (tree n-1 edge, +1 redundant).

### Kod Nümunəsi
```python
class UnionFind:
    def __init__(self, n):
        self.parent = list(range(n))
        self.rank = [0] * n
        self.size = [1] * n
        self.num_components = n

    def find(self, x):
        if self.parent[x] != x:
            self.parent[x] = self.find(self.parent[x])  # path compression
        return self.parent[x]

    def union(self, x, y):
        rx, ry = self.find(x), self.find(y)
        if rx == ry:
            return False  # artıq eyni component
        # Union by rank
        if self.rank[rx] < self.rank[ry]:
            rx, ry = ry, rx
        self.parent[ry] = rx
        self.size[rx] += self.size[ry]
        if self.rank[rx] == self.rank[ry]:
            self.rank[rx] += 1
        self.num_components -= 1
        return True

    def connected(self, x, y):
        return self.find(x) == self.find(y)

    def get_size(self, x):
        return self.size[self.find(x)]

# Redundant Connection
def find_redundant_connection(edges):
    n = len(edges)
    uf = UnionFind(n + 1)
    for u, v in edges:
        if not uf.union(u, v):
            return [u, v]

# Number of Islands — 2D grid
def num_islands(grid):
    if not grid: return 0
    rows, cols = len(grid), len(grid[0])
    uf = UnionFind(rows * cols)
    count = sum(grid[r][c] == '1' for r in range(rows) for c in range(cols))

    for r in range(rows):
        for c in range(cols):
            if grid[r][c] == '1':
                for dr, dc in [(0,1),(1,0)]:
                    nr, nc = r+dr, c+dc
                    if 0 <= nr < rows and 0 <= nc < cols and grid[nr][nc] == '1':
                        if uf.union(r*cols+c, nr*cols+nc):
                            count -= 1
    return count

# Accounts Merge — email-ləri birləşdir
def accounts_merge(accounts):
    from collections import defaultdict
    parent = {}
    email_to_name = {}

    def find(x):
        if parent[x] != x:
            parent[x] = find(parent[x])
        return parent[x]

    def union(x, y):
        parent[find(x)] = find(y)

    for account in accounts:
        name = account[0]
        for email in account[1:]:
            if email not in parent:
                parent[email] = email
            email_to_name[email] = name
            union(account[1], email)  # bütün email-ləri birinci email-ə birləşdir

    groups = defaultdict(list)
    for email in parent:
        groups[find(email)].append(email)

    return [[email_to_name[root]] + sorted(emails)
            for root, emails in groups.items()]

# Kruskal MST
def kruskal_mst(n, edges):
    edges.sort(key=lambda x: x[2])  # weight-ə görə sırala
    uf = UnionFind(n)
    mst_weight = 0
    mst_edges = []

    for u, v, w in edges:
        if uf.union(u, v):
            mst_weight += w
            mst_edges.append((u, v, w))
            if len(mst_edges) == n - 1:
                break

    return mst_weight, mst_edges
```

## Praktik Tapşırıqlar
- LeetCode 684 — Redundant Connection
- LeetCode 685 — Redundant Connection II (directed)
- LeetCode 200 — Number of Islands (BFS alternativ ilə müqayisə et)
- LeetCode 547 — Number of Provinces
- LeetCode 721 — Accounts Merge
- LeetCode 399 — Evaluate Division (weighted Union-Find)
- LeetCode 1584 — Min Cost to Connect All Points (Kruskal)
- LeetCode 952 — Largest Component Size by Common Factor
- **Performance tapşırığı**: n = 10^6 üçün Union-Find, Adjacency List + BFS müqayisə et. Hər ikisinin time/space-ni hesabla
- **Özünü yoxla**: Path compression olmadan, union by rank ilə n = 10 üçün tree hündürlüyünü izlə

## Əlaqəli Mövzular
- **Graph DFS/BFS** — connected components üçün alternativ, amma Union-Find daha effektiv
- **Minimum Spanning Tree** — Kruskal alqoritmi Union-Find-in əsas tətbiqidir
- **Topological Sort** — cycle detection üçün Union-Find (undirected), Kahn (directed)
- **Segment Tree** — hər ikisi range query data structure-dur, müxtəlif domain-lər
- **Graph Algorithms** — Prim MST, Dijkstra Union-Find olmadan da işləyir
