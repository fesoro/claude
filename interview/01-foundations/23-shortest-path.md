# Shortest Path Algorithms (Dijkstra, Bellman-Ford) (Senior ⭐⭐⭐)

## İcmal

Shortest path alqoritmləri graph-da iki node arasındakı minimum ağırlıqlı yolu tapır. Dijkstra (greedy + priority queue) yalnız non-negative weight-lərdə işləyir, Bellman-Ford isə mənfi edge-ləri dəstəkləyir və mənfi cycle-ı detect edir. Floyd-Warshall bütün cütlər arasındakı ən qısa yolu O(V³)-dədir. A* heuristic-lə Dijkstra-nı gücləndirir. Bu alqoritmlər GPS navigation, network routing, game AI kimi real sistemlərin əsasını təşkil edir.

## Niyə Vacibdir

Dijkstra Google Maps, OSPF network routing protocol, chip design-da wire routing kimi kritik sistemlərdə istifadə olunur. Bellman-Ford BGP (internet routing protokolü) üçün əsasdır. FAANG interview-larında "minimum cost path", "shortest time", "cheapest flight" kimi suallar birbaşa bu alqoritmləri test edir. Senior namizəddən gözlənilir: Alqoritmin yalnız işlədilməsi deyil — niyə mənfi edge-lərdə Dijkstra fail olur, Bellman-Ford-un V-1 iterasiyası niyə yetərlidir, A*-ın heuristic şərtləri nələrdir, 0-1 BFS nə vaxt lazımdır.

## Əsas Anlayışlar

### Dijkstra Alqoritmi

**İdея**: Hər addımda bilinən ən qısa yollu node-u seç (greedy seçim), qonşularını yenilə (relaxation).

**Əsas invariant**: Priority queue-dan çıxan node-un distansı artıq finaldır (dəyişməz). Bu cəhət niyə mənfi edge ilə işləmir: Mənfi edge olarsa, artıq "final" hesab etdiyimiz node-un distansı sonra azala bilər.

**Complexity**:
| Priority Queue | Time | Space | Nə zaman |
|----------------|------|-------|----------|
| Binary heap | O((V + E) log V) | O(V + E) | Sparse graph |
| Fibonacci heap | O(E + V log V) | O(V + E) | Dense, decrease-key çox |
| Array (dense) | O(V²) | O(V) | Dense graph (E ≈ V²) |

Dense graph (E ≈ V²) üçün: Array-based O(V²) binary heap O(V² log V)-dən daha yaxşıdır.

**Əsas Addımlar**:
1. `dist[source] = 0`, digərləri `∞`.
2. Priority queue: `(0, source)`.
3. Queue-dan `(d, u)` çıxar; `d > dist[u]` olarsa **skip et** (stale entry).
4. Hər qonşu `v` üçün: `dist[u] + weight(u,v) < dist[v]` olarsa, `dist[v]` yenilə, queue-ya əlavə et.
5. Queue boşalanadə bitir.

**Stale Entry problemi**: Heap-ə eyni node-u bir neçə dəfə əlavə etmək mümkündür. Köhnə entry-ləri skip etmək üçün `d > dist[u]` yoxlaması şərtdir.

### Dijkstra ilə Yol Rekonstruksiyası

```
prev[v] array-i saxla.
dist[v] yeniləndikdə: prev[v] = u
Son node-dan prev[] ilə geri get → yolu reverse et.
```

### Bellman-Ford Alqoritmi

**İdea**: Bütün edge-ləri V-1 dəfə relax et. Relax = distansı yaxşılaşdırmağa çalış.

**Niyə V-1 dəfə yetər**: Mümkün olan ən uzun sadə yol V-1 edge-dən ibarətdir. V-1 relaxation sonra bütün simple path-lar tapılmış olur. Daha uzun yol ya cycle-dır (mənfidirsə detect olunur) ya da optimaldır.

**Mənfi Cycle Detection**: V-ci iterasiyada hələ də yenilənmə varsa → mənfi cycle var. Bu cycle-dan keçən node-ların distansı həmişə azalacaq (−∞).

**Early termination**: Əgər bir iterasiyada heç bir yenilənmə olmursa → bitir.

**Complexity**: O(V · E) — Dijkstra-dan yavaş, amma daha ümumi.

### Floyd-Warshall (All-Pairs Shortest Path)

**DP formulu**: `dp[i][j] = min(dp[i][j], dp[i][k] + dp[k][j])` — k-nı intermediate node kimi sına.

- O(V³) time, O(V²) space.
- Mənfi edge: İşləyir. Mənfi cycle: `dp[i][i] < 0` ilə detect et.
- Kiçik, dense graph üçün (V ≤ 500).
- Path reconstruction: `next[i][j]` matrix saxla.

### SPFA (Shortest Path Faster Algorithm)

- Bellman-Ford-un queue-based optimizasiyası.
- Yalnız distansı yenilənən node-ları queue-ya əlavə et.
- Average O(E), worst case O(V·E).
- Competitive programming-da populyar, production-da nadir.

### A* Alqoritmi

- Dijkstra + heuristic function `h(v)` (v-dən hədəfə estimated distance).
- `f(v) = g(v) + h(v)` — g: mənbədən cari distans, h: heuristic.
- **Admissible heuristic**: `h(v) ≤ actual_distance(v, target)` — heç vaxt həqiqi distansı aşmır → optimal.
- **Consistent (monotone)**: `h(u) ≤ cost(u,v) + h(v)` — Dijkstra-nın invariantını qoruyur.
- GPS: Euclidean distance heuristic.
- Game pathfinding: Manhattan distance heuristic.
- Mənbədən hədəfə tək yol tapmaqda Dijkstra-dan çox sürətli.

### Bidirectional Dijkstra

- Source və target-dən eyni anda Dijkstra işlət.
- İki search circle birləşəndə bitir.
- Praktikada ~2x sürətlidir (explored node sayı azalır).
- Google Maps kimi real sistemlərdə istifadə olunur.

### 0-1 BFS (Binary Weight)

- Weight yalnız 0 ya da 1 olarsa: Deque istifadə et.
- Weight 0 edge: Node-u deque-nun **önünə** əlavə et.
- Weight 1 edge: **Arxasına** əlavə et.
- O(V + E) — Dijkstra-dan daha sürətli bu xüsusi halda.
- Tətbiq: Grid-də qapı açma/bağlama (0 cost), matrix transformation.

### Multi-Source Shortest Path

- Birdən çox mənbəydən ən qısa distansı tap.
- Dijkstra: Bütün mənbələri eyni anda priority queue-ya `(0, source)` kimi əlavə et.
- BFS (unweighted): Eyni şəkildə çoxlu start nöqtəsi.
- Tətbiq: "En yaxın bank filialına mesafə" kimi queries.

### Alqoritmlərin Müqayisəsi

| | Dijkstra | Bellman-Ford | Floyd-Warshall | A* |
|--|---------|--------------|----------------|-----|
| Mənfi edge | ✗ | ✓ | ✓ | ✗ (admissible ilə mümkün) |
| Mənfi cycle detect | ✗ | ✓ | ✓ | ✗ |
| Complexity | O((V+E)log V) | O(V·E) | O(V³) | O(b^d) b=branching |
| Single source | ✓ | ✓ | ✗ | ✓ (source→target) |
| All pairs | ✗ | ✗ | ✓ | ✗ |
| Əsas istifadə | Navigation, routing | BGP, negative wt | Small dense graph | GPS, game AI |

## Praktik Baxış

### Interview Yanaşması

1. Graph weighted-mi? → Dijkstra ya Bellman-Ford.
2. Mənfi edge varmı? → Bellman-Ford (Dijkstra yox).
3. Mənfi cycle detect lazımdır? → Bellman-Ford V-ci iterasiya.
4. Unweighted? → BFS (O(V+E) — Dijkstra-nın xüsusi halı, weight=1).
5. K hops məhdudiyyəti var? → Modified Bellman-Ford (K iterasiya).
6. Heuristic var, source→target? → A*.
7. Weight 0 ya 1? → 0-1 BFS.

### Nədən Başlamaq

- Graph-ı (weighted adjacency list) qurmağı izah et.
- Priority queue ilə Dijkstra şablonunu yaz — stale entry check-i unutma.
- Mənfi edge soruşursa: Bellman-Ford-a keç, niyə Dijkstra fail olur izah et.
- Path rekonstruksiyasını soruşursa: `prev[]` array əlavə et.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Mənfi edge-lərdə Dijkstra niyə fail olur? Konkret nümunə göstər."
- "Bellman-Ford-da niyə V-1 iterasiya yetərlidir?"
- "K addımdan az path tapın" — Bellman-Ford, K iterasiya.
- "Cheapest flights within K stops" — state-i (node, stops) ilə genişləndir.
- "Bu alqoritmi distributed sistemdə necə istifadə edərdiniz?"
- "A*-ın admissible heuristic şərti niyə lazımdır?"
- "Floyd-Warshall-ı O(V²) space-ə endirmək mümkündürmü?"

### Common Mistakes

- Mənfi edge-lərdə Dijkstra istifadə etmək — interviewer bunu bilərək soruşur.
- `d > dist[u]` (stale entry) yoxlamasını unutmaq — infinity loop riski.
- Bellman-Ford-da V-1 deyil V iterasiya etmək — mənfi cycle olmadan da xəta görünər.
- Priority queue-ya eyni node-u bir neçə dəfə əlavə edib, stale check etməmək.
- Floyd-Warshall-da loop sırası: k (intermediate) ən dışda olmalıdır!
- Bellman-Ford-un early termination optimizasiyasını bilməmək.

### Yaxşı → Əla Cavab

- **Yaxşı**: Dijkstra implement edir, O((V+E)logV) deyir.
- **Əla**: Mənfi edge-lərdə niyə fail olduğunu nümunə ilə sübut edir, Bellman-Ford-un V-1 iterasiyasının yetərli olduğunu izah edir, A*-ın heuristic şərtlərini bilir, bidirectional Dijkstra-nı qeyd edir, 0-1 BFS-i bilir, production sistemlərindən nümunə verir.

### Real Production Ssenariləri

- Google Maps: A* + bidirectional Dijkstra + hierarchical routing (contraction hierarchies).
- Internet routing: OSPF (Open Shortest Path First) → Dijkstra. BGP → Bellman-Ford variant.
- Game AI pathfinding: A* — NPC movement.
- Network packet routing: Dijkstra-based.
- Kubernetes pod scheduling: Graph-based resource optimization.

## Nümunələr

### Tipik Interview Sualı

"LeetCode 787 — Cheapest Flights Within K Stops: `n` şəhər, uçuş siyahısı, `src`, `dst`, `k` verilir. Ən çox `k` dayanacaqla `src`-dən `dst`-yə ən ucuz bilet qiymətini tapın."

### Güclü Cavab

Bu problemi modified Bellman-Ford ilə həll edərdim. Standard Dijkstra burada işləmir — çünki K stops məhdudiyyəti var; node-u bir dəfə "final" qəbul edib keçə bilmərəm.

Bellman-Ford-un əsas ideyası: K+1 iterasiya et (K stops = K+1 edge). Hər iterasiyada cari "round"un distanslarını **əvvəlki "round"un distanslarına** əsaslanaraq yenilə. Yenilənmə yalnız əvvəlki round-dan gəlməlidir — buna görə hər iterasiyada `prev_dist`-in kopyasını istifadə edirəm.

Dijkstra variant: State = (cost, node, stops_left). `(node, stops)` cütünü visited kimi izlə. Hər state yalnız bir dəfə expand edilir.

### Kod Nümunəsi

```python
import heapq
from collections import defaultdict

# Standard Dijkstra — O((V+E) log V)
def dijkstra(n: int, edges: list, src: int) -> list:
    graph = defaultdict(list)
    for u, v, w in edges:
        graph[u].append((v, w))

    dist = [float('inf')] * n
    dist[src] = 0
    prev = [-1] * n
    heap = [(0, src)]

    while heap:
        d, u = heapq.heappop(heap)
        if d > dist[u]:    # stale entry — skip
            continue
        for v, w in graph[u]:
            if dist[u] + w < dist[v]:
                dist[v] = dist[u] + w
                prev[v] = u
                heapq.heappush(heap, (dist[v], v))

    return dist, prev

def reconstruct_path(prev: list, dst: int) -> list:
    """prev[] array-dən yolu rekonstruksiya et"""
    path = []
    while dst != -1:
        path.append(dst)
        dst = prev[dst]
    return path[::-1]   # reverse

# Bellman-Ford — O(V*E)
def bellman_ford(n: int, edges: list, src: int):
    """
    edges: [(u, v, weight)]
    Returns: (dist, None) or (None, True) if negative cycle
    """
    dist = [float('inf')] * n
    dist[src] = 0

    for _ in range(n - 1):    # V-1 iterasiya
        updated = False
        for u, v, w in edges:
            if dist[u] != float('inf') and dist[u] + w < dist[v]:
                dist[v] = dist[u] + w
                updated = True
        if not updated:
            break   # early termination — artıq dəyişiklik yoxdur

    # Mənfi cycle detection — V-ci iterasiya
    for u, v, w in edges:
        if dist[u] != float('inf') and dist[u] + w < dist[v]:
            return None, True   # mənfi cycle var

    return dist, False

# Cheapest Flights K Stops — Modified Bellman-Ford — O(K * E)
def find_cheapest_price(n: int, flights: list, src: int, dst: int, k: int) -> int:
    dist = [float('inf')] * n
    dist[src] = 0

    for _ in range(k + 1):    # K stops = K+1 edge
        temp = dist[:]        # əvvəlki round-un kopyası!
        for u, v, w in flights:
            if dist[u] != float('inf') and dist[u] + w < temp[v]:
                temp[v] = dist[u] + w
        dist = temp

    return dist[dst] if dist[dst] != float('inf') else -1

# Cheapest Flights K Stops — Dijkstra variant
def find_cheapest_price_dijkstra(n: int, flights: list, src: int, dst: int, k: int) -> int:
    graph = defaultdict(list)
    for u, v, w in flights:
        graph[u].append((v, w))

    # State: (cost, node, stops_remaining)
    heap = [(0, src, k + 1)]   # k+1 edge mümkündür
    visited = {}   # (node, stops) → min cost

    while heap:
        cost, node, stops = heapq.heappop(heap)
        if node == dst:
            return cost
        if stops == 0:
            continue
        state = (node, stops)
        if state in visited and visited[state] <= cost:
            continue
        visited[state] = cost
        for neighbor, price in graph[node]:
            heapq.heappush(heap, (cost + price, neighbor, stops - 1))
    return -1

# Floyd-Warshall — O(V³) — all-pairs shortest path
def floyd_warshall(n: int, edges: list) -> list:
    INF = float('inf')
    dist = [[INF] * n for _ in range(n)]
    for i in range(n):
        dist[i][i] = 0
    for u, v, w in edges:
        dist[u][v] = w

    for k in range(n):       # intermediate node — ən dışda!
        for i in range(n):
            for j in range(n):
                if dist[i][k] != INF and dist[k][j] != INF:
                    if dist[i][k] + dist[k][j] < dist[i][j]:
                        dist[i][j] = dist[i][k] + dist[k][j]

    # Mənfi cycle check
    for i in range(n):
        if dist[i][i] < 0:
            return None   # mənfi cycle

    return dist

# 0-1 BFS — O(V+E) — weight yalnız 0 ya 1
from collections import deque
def bfs_01(grid: list, start: tuple, end: tuple) -> int:
    rows, cols = len(grid), len(grid[0])
    dist = [[float('inf')] * cols for _ in range(rows)]
    sr, sc = start
    dist[sr][sc] = 0
    dq = deque([(0, sr, sc)])

    while dq:
        d, r, c = dq.popleft()
        if d > dist[r][c]:
            continue
        for dr, dc in [(0,1),(0,-1),(1,0),(-1,0)]:
            nr, nc = r+dr, c+dc
            if 0 <= nr < rows and 0 <= nc < cols:
                w = grid[nr][nc]   # 0 ya 1
                if dist[r][c] + w < dist[nr][nc]:
                    dist[nr][nc] = dist[r][c] + w
                    if w == 0:
                        dq.appendleft((dist[nr][nc], nr, nc))   # önə əlavə et
                    else:
                        dq.append((dist[nr][nc], nr, nc))       # arxaya əlavə et
    er, ec = end
    return dist[er][ec] if dist[er][ec] != float('inf') else -1

# Network Delay Time — LeetCode 743
def network_delay_time(times: list, n: int, k: int) -> int:
    """k mənbəsindən bütün node-lara çatmaq üçün minimum vaxt"""
    graph = defaultdict(list)
    for u, v, w in times:
        graph[u].append((v, w))

    dist = [float('inf')] * (n + 1)
    dist[k] = 0
    heap = [(0, k)]

    while heap:
        d, u = heapq.heappop(heap)
        if d > dist[u]:
            continue
        for v, w in graph[u]:
            if dist[u] + w < dist[v]:
                dist[v] = dist[u] + w
                heapq.heappush(heap, (dist[v], v))

    max_dist = max(dist[1:])
    return max_dist if max_dist != float('inf') else -1
```

### İkinci Nümunə — Mənfi Edge Dijkstra Fail Sübut

```
Graph: A→B weight=4, A→C weight=2, C→B weight=-5
Dijkstra:
  1. dist[A]=0, process A: dist[B]=4, dist[C]=2
  2. Process C (dist=2): relax B → 2+(-5)=-3 < 4, dist[B]=-3
  3. Process B (dist=4 — stale!) ya dist[B]=-3?

Problem: B ilk dəfə dist=4 ilə finalize edilib.
Mənfi edge C→B=-5 sonra gəldi.
Amma Dijkstra B-ni "final" hesab etmişdi.

Bellman-Ford:
  Iteration 1: dist[B]=min(4, 2+(-5))=-3 ✓
  Sonrakı iterasiyalar: Dəyişiklik yoxdur.
  Düzgün cavab: dist[B]=-3
```

## Praktik Tapşırıqlar

1. LeetCode #743 — Network Delay Time (basic Dijkstra + all nodes).
2. LeetCode #787 — Cheapest Flights Within K Stops (modified Bellman-Ford).
3. LeetCode #1334 — Find the City With Smallest Number of Neighbors (Floyd-Warshall).
4. LeetCode #778 — Swim in Rising Water (Dijkstra, minimax variant).
5. LeetCode #1631 — Path With Minimum Effort (Dijkstra, minimax).
6. LeetCode #1514 — Path with Maximum Probability (Dijkstra, max-heap).
7. LeetCode #1368 — Minimum Cost to Make at Least One Valid Path (0-1 BFS).
8. Design tapşırığı: GPS navigation sistemi üçün shortest path. Real constraints: Milyonlarla node, real-time updates (bağlı yol), A*-ın heuristic-i nə olmalıdır?
9. Özünütəst: Mənfi edge-li bir graph-da Dijkstra-nın səhv nəticə verdiyi nümunəni cız.
10. Özünütəst: Floyd-Warshall-ın loop sırası dəyişsə (k ən içdə olsa) niyə yanlış cavab verər?

## Əlaqəli Mövzular

- **Heap / Priority Queue** — Dijkstra-nın əsas data structure-u. Priority queue olmadan O(V²).
- **Graph BFS/DFS** — Unweighted shortest path üçün BFS yetərlidir (Dijkstra-nın xüsusi halı).
- **Dynamic Programming** — Floyd-Warshall DP-dir; Bellman-Ford da DP ideyasına əsaslanır.
- **Greedy Algorithms** — Dijkstra greedy-dir. Greedy choice property-ni izah et.
- **Union-Find / Kruskal** — MST alqoritmləri ilə müqayisə. MST ≠ shortest path.
