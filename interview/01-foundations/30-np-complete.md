# NP-Complete Problems (Architect ⭐⭐⭐⭐⭐)

## İcmal
NP-Complete — hesablama nəzəriyyəsinin əsas anlayışıdır: bu sinif problemlər polynomial vaxtda həll edilə bilməyəcək qədər çətin hesab edilir (əgər P ≠ NP olarsa), lakin verilmiş həllin düzgünlüyünü polynomial vaxtda yoxlamaq mümkündür. Əgər bir NP-Complete problemi polynomial vaxtda həll olunarsa, bütün NP problemlər həll olunar. Bu anlayış müsahibdə "optimal alqoritm yoxdur, yaxınlaşma alqoritmi istifadə edirəm" qərarını verməyi tələb edir.

## Niyə Vacibdir
Arxitektur qərarlar verərkən "bu problem NP-hard-dır mı?" sualı kritikdir — əgər bəlidirsə, optimal həll axtarmaq əvəzinə heuristic, approximation, ya da constraint-relaxation yanaşmasına keçmək lazımdır. Amazon-un shipment routing, Google Ads-in ad allocation, Kubernetes-in pod scheduling, VLSI circuit design — hamısı NP-hard problemlərlə qarşılaşır. Architect rol üçün: nəyin mümkün, nəyin mümkünsüz olduğunu bilmək, practical trade-off-ları idarə etmək bu mövzunun əsasıdır.

## Əsas Anlayışlar

### Complexity Siniflər

**P (Polynomial time)**
- Deterministic Turing Machine-də polynomial vaxtda həll olunan problemlər
- O(n^k) — k sabit
- Nümunələr: sorting, shortest path (Dijkstra), MST, matrix multiplication

**NP (Nondeterministic Polynomial time)**
- Verilmiş bir həllin düzgünlüyünü polynomial vaxtda yoxlamaq mümkündür
- (ya da: Nondeterministic TM-də polynomial vaxtda həll olunur)
- P ⊆ NP — hər P problemi NP-dir (həll varsa, yoxlamaq da asandır)
- Nümunələr: SAT, Hamiltonian Path, Knapsack, Graph Coloring

**NP-Hard**
- Ən azı bütün NP problemlər qədər çətin
- NP-dən olmaya bilər (cavabı verify etmək özü çətin ola bilər)
- Nümunələr: Halting Problem (NP-dən çətin), TSP optimization, Clique optimization

**NP-Complete**
- NP-Hard ∩ NP
- Həm NP-dədir (çözümü verify etmək polynom), həm NP-Hard
- Bütün NP-Complete problemlər bir-birinə polynomial reducible-dır

```
Hierarchy:
P ⊆ NP-Complete ⊆ NP-Hard
        ↑
     NP-Complete
        ↑
       NP ⊇ P
```

### Cook-Levin Theorem
SAT (Boolean Satisfiability) ilk NP-Complete kimi sübut edildi (1971). Bütün digər NP-Complete sübut edilməsi: "problem X-i SAT-a (ya da başqa NP-Complete-ə) polynomial reduction göstər".

### Klassik NP-Complete Problemlər

**SAT (3-SAT)**
- CNF formada boolean formula satisfying assignment varmı?
- `(A ∨ B ∨ C) ∧ (¬A ∨ D ∨ ¬B) ∧ ...`
- Real tətbiq: formal verification, hardware testing, constraint solving

**Knapsack (0/1)**
- n item, capacity W, value-i maximize et
- DP ilə O(nW) — pseudo-polynomial (W-nin bitlərinin sayına görə exponential)
- Fractional Knapsack: greedy ilə O(n log n) → NP deyil!

**Traveling Salesman Problem (TSP)**
- n şəhəri ziyarət et, ən qısa döngü tapın
- Exact: O(2^n * n²) bitmask DP
- Approximation: Christofides algorithm — optimal-dan ≤ 1.5x
- Practical: OR-Tools, Lin-Kernighan heuristic

**Graph Coloring (k-Coloring)**
- k rənglə graph-ı elə rəngləndir ki, qonşular eyni rəng olmaya
- k=2 (bipartite check): polynomial (BFS)
- k≥3: NP-Complete
- Tətbiq: register allocation, exam scheduling

**Clique Problem**
- k-clique: hamısı bir-birinə bağlı k vertex varmı?
- Decision problem: NP-Complete
- Tətbiq: social network analysis, drug discovery (molecular pattern)

**Hamiltonian Path / Cycle**
- Hər vertex-i tam bir dəfə ziyarət et
- Euler path ilə qarışdırma: Euler-da hər EDGE bir dəfə, Hamiltonian-da hər VERTEX
- Euler path: O(E) — polynomial (Hierholzer alqoritmi)
- Hamiltonian: NP-Complete

**Vertex Cover**
- k vertex seç ki, hər edge ən azı bir seçilmiş vertex-ə toxunsun
- 2-approximation: maksimal matching götür, iki endpoint-i seç
- Exact: NP-Complete

**Subset Sum / Partition**
- Set-dəki elementlər target-i verəcək şəkildə seçilə bilərmi?
- DP ilə O(n*target) — pseudo-polynomial
- Kriptografiyada istifadə edilirdi (knapsack cryptosystem)

**Independent Set**
- k vertex seç ki, heç biri bir-birinə bağlı olmasın
- Clique probleminin dual-ı (G-də independent set = G-nin tamamlayıcısında clique)

### Polynomial Reduction (Reduction Nümunəsi)
3-SAT → Independent Set:
- Hər clause üçün 3 vertex qrup
- Hər qrupun vertexləri bir-birinə bağlı (hamısı qrup daxilindəki konflikt)
- Bir literal `x` digər qrupda `¬x` olan vertex-ə bağlı (literal konflikt)
- 3-SAT satisfiable ⟺ Independent Set (k = clause sayı) mövcuddur

Bu tipli reduction-ları bilmək Architect-level interview-un əsasıdır.

### Approximation Alqoritmləri
NP-Complete problemlər üçün practical yanaşmalar:

**Greedy Approximation**:
- Vertex Cover: 2-approximation (optimal-dan ≤ 2x)
- Metric TSP: Nearest neighbor — ≤ 2x optimal
- Set Cover: ln(n) + 1 approximation

**Local Search**:
- Başlanğıc həllini yaxşılaşdır
- 2-opt, 3-opt (TSP üçün)
- Simulated Annealing: local optima-dan çıxmaq üçün random jump

**Exact Exponential**:
- Branch and Bound: pruning ilə exponential search
- Integer Linear Programming (ILP): industrial solver-lər (Gurobi, CPLEX)
- Fixed-Parameter Tractable (FPT): parametr kiçikdirsə efficient

### Pseudo-Polynomial Alqoritmler
Əgər ədədi input-lar kiçikdirsə, bəzi NP problemlər polynomial vaxtda həll olunur:
- Knapsack O(nW): W kiçikdirsə practical
- Subset Sum O(nT): T kiçikdirsə practical
- "Pseudo-polynomial": W-nin dəyəri polynomial, amma W-nin bit uzunluğu baxımından exponential

### P vs NP: Niyə Açıq Məsələdir?
- Millennium Prize Problems-dən biri (1M $ mükafat)
- "P = NP" olarsa: kriptografiya, security çöküş — RSA, AES güvənsiz
- Əksər araşdırmaçılar P ≠ NP düşünür
- Sübut: hər iki istiqamətdə çox çətin

### Əməli Nəticələr Arxitektur üçün
1. Problem NP-Hard olduğunu tanı → optimal həll axtarma
2. Input ölçüsü nədir? n≤20 → exact, n≤1000 → heuristic, n>10^5 → greedy
3. Domain-specific constraint var? → ILP ya da constraint programming
4. "Good enough" həll kafiyəmi? → approximation ratio qəbulolunan?

## Praktik Baxış

### Interview Yanaşması
1. Problemi tanı: reduction ilə bilinen NP-Complete-ə bənzəyirmi?
2. "Optimal həll mümkün deyil, approximation istifadə edirəm" de
3. Trade-off-ları açıq söylə: accuracy vs time
4. Practical yanaşma: ILP, heuristic, DP (small n), constraint relaxation

### Nədən Başlamaq
- Problem statement-ini NP-Complete siyahısı ilə müqayisə et
- Input ölçüsünü soruş: n nədir? Budget nədir?
- Approximation ratio qəbulolunanmı?

### Ümumi Follow-up Suallar
- "Bu problemi polynomial vaxtda həll edə bilmərsinizmi?" (NP-Complete olarsa: bilnmir)
- "Approximation alqoritminin quality guarantee-si nədir?"
- "P = NP olarsa nə baş verər?" (kriptografiya implications)
- "Practical sistemdə bu problemi necə yanaşırsınız?"

### Namizədlərin Ümumi Səhvləri
- NP-Complete ilə NP-Hard-ı qarışdırmaq
- Pseudo-polynomial alqoritmi polynomial kimi qəbul etmək
- "DP ilə həll edirəm" demək — Knapsack DP pseudo-polynomial-dır, polynomial deyil
- Approximation ratio-nu nəzərə almamaq

### Yaxşı → Əla Cavab
- Yaxşı: NP-Complete-i tanıyır, bəzi klassik problemləri bilir
- Əla: Cook-Levin theorem-i izah edir, polynomial reduction göstərə bilir, practical trade-off-ları (ILP, simulated annealing, approximation) məntiqli şəkildə seçir, P vs NP-nin tətbiqi nəticələrini söyləyir

## Nümunələr

### Tipik Interview Sualı
"Şirkətin 50 işçisi var, 20 layihə var. Hər layihəni ən uyğun işçilərə assign etmək lazımdır. Skill match-i, iş yükü balansını optimallaşdırmaq istəyirsiz. Necə yanaşırsınız?"

### Güclü Cavab
Bu — Assignment Problem variantıdır, NP-Hard-a oxşayır (multi-dimensional constraints olduğunda). Əgər yalnız bir-bir assignment olsaydı, Hungarian Algorithm ilə O(n³)-dədir. Amma skill constraints + workload balance olduqda bu Integer Linear Programming probleminə çevrilir.

Practical yanaşmam:
1. Əvvəlcə problemi formalize edim: `x[i][j] ∈ {0,1}` — işçi i layihə j-yə assigndirmi?
2. Constraint-lər: hər layihə ≥1 işçi, hər işçinin workload limiti, skill match threshold
3. Objective: maximize total skill match score

Həll variantları:
- **Greedy**: hər layihəni ən uyğun işçiyə assign et — tez, amma optimal deyil
- **ILP (Gurobi/CPLEX)**: n=50 üçün practical, industrial-grade solver
- **Simulated Annealing**: initial assignment → random swap → accept/reject by temperature
- **Constraint Programming** (OR-Tools): constraint propagation + backtracking

n=50 üçün ILP seçərdim — Gurobi 50 variable-ı saniyələrlə həll edir. n=10000 olsaydı → heuristic lazımdır.

### Kod Nümunəsi
```python
# TSP — Bitmask DP (Exact, O(2^n * n²)) — n≤20 üçün
def tsp_exact(dist):
    n = len(dist)
    INF = float('inf')
    dp = [[INF] * n for _ in range(1 << n)]
    dp[1][0] = 0  # start: node 0, yalnız o ziyarət edilib

    for mask in range(1 << n):
        for u in range(n):
            if dp[mask][u] == INF:
                continue
            if not (mask >> u & 1):
                continue
            for v in range(n):
                if mask >> v & 1:
                    continue
                new_mask = mask | (1 << v)
                new_cost = dp[mask][u] + dist[u][v]
                if new_cost < dp[new_mask][v]:
                    dp[new_mask][v] = new_cost

    full = (1 << n) - 1
    return min(dp[full][i] + dist[i][0] for i in range(1, n))

# Nearest Neighbor Heuristic TSP — O(n²)
def tsp_nearest_neighbor(dist):
    n = len(dist)
    visited = [False] * n
    path = [0]
    visited[0] = True
    total = 0

    for _ in range(n - 1):
        curr = path[-1]
        best_dist, best_next = float('inf'), -1
        for v in range(n):
            if not visited[v] and dist[curr][v] < best_dist:
                best_dist = dist[curr][v]
                best_next = v
        path.append(best_next)
        visited[best_next] = True
        total += best_dist

    total += dist[path[-1]][0]  # başa qayıt
    return total, path

# 2-Approximation Vertex Cover
def vertex_cover_2approx(edges):
    cover = set()
    covered = set()
    for u, v in edges:
        if (u, v) not in covered and (v, u) not in covered:
            cover.add(u)
            cover.add(v)
            # Bu iki node-un bütün edge-lərini "covered" say
            covered.add((u, v))
    return cover

# Subset Sum — DP (pseudo-polynomial O(n*target))
def subset_sum(nums, target):
    dp = {0}
    for n in nums:
        dp = dp | {x + n for x in dp}
    return target in dp

# Backtracking — Exact Coloring (small n)
def graph_coloring(adj, k):
    n = len(adj)
    colors = [-1] * n

    def can_color(v, c):
        return all(colors[u] != c for u in adj[v])

    def backtrack(v):
        if v == n:
            return True
        for c in range(k):
            if can_color(v, c):
                colors[v] = c
                if backtrack(v + 1):
                    return True
                colors[v] = -1
        return False

    return backtrack(0), colors
```

## Praktik Tapşırıqlar
- LeetCode 698 — Partition to K Equal Sum Subsets (NP-hard, backtracking)
- LeetCode 1125 — Smallest Sufficient Team (bitmask DP)
- LeetCode 943 — Find the Shortest Superstring (TSP variant, bitmask DP)
- **Reduction tapşırığı**: 3-SAT-ı Independent Set-ə polynomial time reduce et. Addımları izah et
- **Approximation tapşırığı**: Greedy set cover implement et. ln(n) approximation ratio-nu nümunə ilə göstər
- **Design tapşırığı**: Kubernetes pod scheduling — node-lara pod-ları assign et, resource constraint-ləri qeyd et. NP-hard olduğunu izah et, practical yanaşmanı (Bin Packing + greedy) izah et

## Əlaqəli Mövzular
- **Dynamic Programming** — Knapsack, TSP — bitmask DP ilə exact NP-Complete həll
- **Backtracking** — NP-Complete-in brute force həll texnikası
- **Greedy Algorithms** — approximation alqoritmlərinin çoxu greedy-dir
- **Graph Algorithms** — Clique, Vertex Cover, Coloring graph problemləridir
- **Divide and Conquer** — Branch and Bound, exact exponential alqoritmlər D&C strukturuna malikdir
