# Network Flow (Architect)

## İcmal

**Network Flow** — yönlü weighted graph-da mənbədən (source) axıya (sink) maksimum axın tapmaq problemidir. Bipartite matching, job scheduling, capacity planning kimi real problemlər bu modellə həll edilir.

```
Capacity graph (s=source, t=sink):
       10        10
  s -------> A -------> t
  |          ^          ^
  10         |5         |10
  |          |          |
  +-------> B ----------+
       10        10

Max Flow = 20 (iki ayrı yol ilə)
```

---

## Niyə Vacibdir

**Real tətbiqlər:**
- **Bipartite matching**: İşçi-vəzifə təyinatı (optimal assignment)
- **Network bandwidth**: Maximum data throughput
- **Project scheduling**: Critical path analysis
- **Resource allocation**: Server yük balansı
- **Image segmentation**: Min-cut ilə ön plan/arxa plan ayrılması

**Texniki interviyularda soruşulan hallarda:**
- "N işçini M vəzifəyə optimal sat" — bipartite matching = max flow
- "Minimum edge silinməsi ilə graph-ı disconnect et" — min cut = max flow (Max-Flow Min-Cut teoremi)

---

## Əsas Anlayışlar

### Terminlər

| Termin | İzah |
|--------|------|
| Capacity c(u,v) | (u,v) kənarından maksimum axın |
| Flow f(u,v) | Actual axın — capacity-dən çox ola bilməz |
| Residual graph | Qalan kapasite: r(u,v) = c(u,v) - f(u,v) |
| Augmenting path | s-dən t-yə residual graph-da yol |
| Max-Flow Min-Cut | Max axın = Minimum cut kapasitəsi |

### Ford-Fulkerson Prinsipi
1. Residual graph-da s→t yol tap (augmenting path)
2. Bu yolun minimum kapasitəsi = bottleneck
3. Axını bu qədər artır, residual graph-ı yenilə
4. Yol tapılmasa, max flow əldə edilib

### Residual Graph
```
Original edge: A→B, capacity=10, flow=6
Residual:
  A→B: remaining = 10-6 = 4  (daha göndərə bilərik)
  B→A: back edge = 6         (geri ala bilərik)
```

### Dinic's Algorithm
BFS ilə level graph qur, DFS ilə blocking flow tap. O(V²E) — Edmonds-Karp O(VE²)-dən çox sürətlidir.

---

## Praktik Baxış

### Edmonds-Karp (BFS-based Ford-Fulkerson)

```php
class MaxFlow
{
    private array $graph; // adjacency list: [to, capacity, reverse_idx]
    private int $n;

    public function __construct(int $n)
    {
        $this->n = $n;
        $this->graph = array_fill(0, $n, []);
    }

    public function addEdge(int $from, int $to, int $cap): void
    {
        $this->graph[$from][] = [$to, $cap, count($this->graph[$to])];
        $this->graph[$to][] = [$from, 0, count($this->graph[$from]) - 1]; // reverse edge
    }

    // BFS: Augmenting path tap
    private function bfs(int $s, int $t, array &$parent): bool
    {
        $visited = array_fill(0, $this->n, false);
        $queue = [$s];
        $visited[$s] = true;

        while (!empty($queue)) {
            $u = array_shift($queue);
            foreach ($this->graph[$u] as $i => [$v, $cap, $_]) {
                if (!$visited[$v] && $cap > 0) {
                    $visited[$v] = true;
                    $parent[$v] = [$u, $i];
                    if ($v === $t) return true;
                    $queue[] = $v;
                }
            }
        }
        return false;
    }

    // Edmonds-Karp: O(VE²)
    public function maxflow(int $s, int $t): int
    {
        $flow = 0;
        $parent = array_fill(0, $this->n, null);

        while ($this->bfs($s, $t, $parent)) {
            // Bottleneck tap
            $pathFlow = PHP_INT_MAX;
            for ($v = $t; $v !== $s; $v = $parent[$v][0]) {
                [$u, $i] = $parent[$v];
                $pathFlow = min($pathFlow, $this->graph[$u][$i][1]);
            }

            // Axını yenilə
            for ($v = $t; $v !== $s; $v = $parent[$v][0]) {
                [$u, $i] = $parent[$v];
                $this->graph[$u][$i][1] -= $pathFlow;
                $revIdx = $this->graph[$u][$i][2];
                $this->graph[$v][$revIdx][1] += $pathFlow;
            }

            $flow += $pathFlow;
            $parent = array_fill(0, $this->n, null);
        }

        return $flow;
    }
}
```

### Dinic's Algorithm (O(V²E) — Daha Sürətli)

```php
class Dinic
{
    private array $graph;
    private array $level;
    private array $iter; // next edge pointer (blocking flow üçün)
    private int $n;

    public function __construct(int $n)
    {
        $this->n = $n;
        $this->graph = array_fill(0, $n, []);
    }

    public function addEdge(int $from, int $to, int $cap): void
    {
        $this->graph[$from][] = [$to, $cap, count($this->graph[$to])];
        $this->graph[$to][] = [$from, 0, count($this->graph[$from]) - 1];
    }

    // BFS ilə level graph qur
    private function bfs(int $s, int $t): bool
    {
        $this->level = array_fill(0, $this->n, -1);
        $this->level[$s] = 0;
        $queue = [$s];

        while (!empty($queue)) {
            $v = array_shift($queue);
            foreach ($this->graph[$v] as [$u, $cap, $_]) {
                if ($cap > 0 && $this->level[$u] < 0) {
                    $this->level[$u] = $this->level[$v] + 1;
                    $queue[] = $u;
                }
            }
        }
        return $this->level[$t] >= 0;
    }

    // DFS ilə blocking flow
    private function dfs(int $v, int $t, int $f): int
    {
        if ($v === $t) return $f;

        for (; $this->iter[$v] < count($this->graph[$v]); $this->iter[$v]++) {
            $i = $this->iter[$v];
            [$u, $cap, $rev] = $this->graph[$v][$i];

            if ($cap > 0 && $this->level[$v] < $this->level[$u]) {
                $d = $this->dfs($u, $t, min($f, $cap));
                if ($d > 0) {
                    $this->graph[$v][$i][1] -= $d;
                    $this->graph[$u][$rev][1] += $d;
                    return $d;
                }
            }
        }
        return 0;
    }

    // Dinic's max flow: O(V²E), bipartite üçün O(E√V)
    public function maxflow(int $s, int $t): int
    {
        $flow = 0;
        while ($this->bfs($s, $t)) {
            $this->iter = array_fill(0, $this->n, 0);
            while (($f = $this->dfs($s, $t, PHP_INT_MAX)) > 0) {
                $flow += $f;
            }
        }
        return $flow;
    }
}
```

### Bipartite Matching (Max Flow ilə)

```php
// N işçi, M vəzifə — kim hansı vəzifəyə uyğundur?
// Max matching = max flow (s→worker→job→t, all capacity=1)
function bipartiteMatching(array $canDo, int $workers, int $jobs): int
{
    // Node 0 = source, 1..workers = işçilər, workers+1..workers+jobs = vəzifələr
    // workers+jobs+1 = sink
    $s = 0;
    $t = $workers + $jobs + 1;
    $dinic = new Dinic($t + 1);

    // Source → each worker
    for ($w = 1; $w <= $workers; $w++) {
        $dinic->addEdge($s, $w, 1);
    }

    // Worker → jobs they can do
    foreach ($canDo as [$worker, $job]) {
        $dinic->addEdge($worker, $workers + $job, 1);
    }

    // Each job → sink
    for ($j = 1; $j <= $jobs; $j++) {
        $dinic->addEdge($workers + $j, $t, 1);
    }

    return $dinic->maxflow($s, $t);
}

// Misal:
// Worker 1 can do jobs 1, 2
// Worker 2 can do jobs 2, 3
// Worker 3 can do job 3
$canDo = [[1,1],[1,2],[2,2],[2,3],[3,3]];
echo bipartiteMatching($canDo, 3, 3); // 3 (tam matching)
```

### Min-Cut (Max-Flow Min-Cut Teoremi)

```php
// Max-Flow Min-Cut: minimum kapasitəli kənarları sil ki, s-t disconnected olsun
// Min cut = max flow

function minCut(Dinic $dinic, int $s, int $n): array
{
    // Max flow-u hesabla (graph dəyişir)
    // Sonra BFS ilə s-dən çatıla bilən nodeları tap (residual-da)
    // S tərəfindən T tərəfinə gedən saturated kənarlar = min cut kənarlar
    $reachable = array_fill(0, $n, false);
    $queue = [$s];
    $reachable[$s] = true;

    while (!empty($queue)) {
        $v = array_shift($queue);
        foreach ($dinic->graph[$v] as [$u, $cap, $_]) {
            if ($cap > 0 && !$reachable[$u]) {
                $reachable[$u] = true;
                $queue[] = $u;
            }
        }
    }

    $cutEdges = [];
    for ($v = 0; $v < $n; $v++) {
        if ($reachable[$v]) {
            foreach ($dinic->graph[$v] as [$u, $cap, $_]) {
                if (!$reachable[$u]) {
                    $cutEdges[] = [$v, $u];
                }
            }
        }
    }
    return $cutEdges;
}
```

---

## Nümunələr

### Project Selection (closure problem)

```php
// Hansı layihələri seçmək: hər layihənin mənfəəti/xərci var,
// bəzi layihələr digərindən asılıdır (A seçsən B də seçməlisən)
// Max profit = sum(positive profits) - min cut

function maxProjectProfit(array $profits, array $costs, array $deps): int
{
    $n = count($profits);
    $s = $n;     // source
    $t = $n + 1; // sink

    $dinic = new Dinic($n + 2);
    $totalPositive = 0;

    for ($i = 0; $i < $n; $i++) {
        if ($profits[$i] > 0) {
            $dinic->addEdge($s, $i, $profits[$i]);
            $totalPositive += $profits[$i];
        } else {
            $dinic->addEdge($i, $t, -$profits[$i]);
        }
    }

    foreach ($deps as [$a, $b]) {
        $dinic->addEdge($a, $b, PHP_INT_MAX); // a seçilsə b də seçilməlidir
    }

    return $totalPositive - $dinic->maxflow($s, $t);
}
```

### Network Reliability (Minimum Edge Connectivity)

```php
// Graf-da neçə kənar silinsə bağlantısı kəsilər?
// Her node cütü üçün max-flow tap (min-cut), minimumdur cavab
function edgeConnectivity(array $graph, int $n): int
{
    $minCut = PHP_INT_MAX;

    for ($t = 1; $t < $n; $t++) {
        $mf = new Dinic($n);
        foreach ($graph as [$u, $v, $cap]) {
            $mf->addEdge($u, $v, $cap);
            $mf->addEdge($v, $u, $cap); // undirected
        }
        $minCut = min($minCut, $mf->maxflow(0, $t));
    }

    return $minCut;
}
```

---

## Vaxt və Yaddaş Mürəkkəbliyi

| Alqoritm | Time Complexity | Qeyd |
|----------|----------------|------|
| Ford-Fulkerson (DFS) | O(E × max_flow) | Pseudo-polynomial |
| Edmonds-Karp (BFS) | O(VE²) | Polynomial, amma yavaş |
| Dinic's | O(V²E) | Ümumilikdə sürətli |
| Dinic's (bipartite) | O(E√V) | Matching üçün ideal |
| Push-Relabel | O(V²√E) | Dense graph üçün yaxşı |

**Praktik tövsiyə:**
- Bipartite matching → Dinic's O(E√V)
- General max flow → Dinic's O(V²E)
- Dense graph (V~E) → Push-Relabel

Space: O(V + E)

---

## Praktik Tapşırıqlar

1. **LeetCode 1557** — Minimum Number of Vertices to Reach All Nodes
2. **LeetCode 1591** — Strange Printer II (min flow interpretation)
3. **SPOJ MATCHING** — Bipartite matching
4. **LeetCode 959** — Regions Cut By Slashes (flow interpretation)
5. **CSES Problem Set** — Download Speed (klassik max flow)

### Step-by-step: Bipartite Matching

```
Scenario: 4 işçi, 4 vəzifə
İşçi 1 → Vəzifə 1, 2
İşçi 2 → Vəzifə 2, 3
İşçi 3 → Vəzifə 3, 4
İşçi 4 → Vəzifə 4

Graph: s → {w1,w2,w3,w4}, {w→jobs}, {j1,j2,j3,j4} → t
Bütün capacity = 1

Max flow = 4 (hər işçi bir vəzifə alır)
Matching: 1→1, 2→2, 3→3, 4→4 (və ya 1→2, 2→3, 3→4, 4→... impossible)
Əslində: 1→1, 2→2, 3→3, 4→4
```

---

## Interview Sualları

**1. Max-Flow Min-Cut teoremi nədir?**
Hər axın şəbəkəsində maksimum axın, minimum kənar çıkarma dəstinin kapasitəsinə bərabərdir. Bu iki problem dual-dır — birini həll etmək digərini həll edir.

**2. Ford-Fulkerson niyə bəzən terminate etmir?**
İrrational kapasitələrlə sonsuz augmenting path ola bilər. BFS-based Edmonds-Karp həmişə bitir (O(VE²)).

**3. Back edge-lər niyə vacibdir?**
Əvvəlki yanlış axını "geri qaytarmağa" imkan verir. Olmasa, lokal optimumdan çıxa bilmirik.

**4. Bipartite matching niyə max flow ilə həll olunur?**
Hər işçi/vəzifəyə capacity=1 kənar əlavə edirik. Max flow = max matching. Dinic bipartite üçün O(E√V) — xüsusi optimallaşma.

**5. Dinic's niyə Edmonds-Karp-dan sürətlidir?**
Level graph + blocking flow. Hər BFS fazası bütün eyni uzunluqlu augmenting path-ları eyni anda işləyir. BFS faza sayı O(V)-dən çox ola bilməz.

**6. Real layihədə max flow harada istifadə olunsun?**
- Müştəri-server assignment (load balancing)
- Task scheduling with dependencies
- Supply chain optimization
- Network capacity analysis

---

## PHP/Laravel ilə Əlaqə

```php
// 1. Task Assignment Service
// N işçi, M tapşırıq — optimal assignment
class TaskAssignmentService
{
    public function assignOptimally(array $workers, array $tasks, array $skills): array
    {
        // workers[i] can do tasks[j] if skills[i][j] = true
        // Build bipartite graph, run max flow
        $canDo = [];
        foreach ($workers as $wi => $worker) {
            foreach ($tasks as $ti => $task) {
                if ($skills[$wi][$ti]) {
                    $canDo[] = [$wi + 1, $ti + 1];
                }
            }
        }
        $matching = bipartiteMatching($canDo, count($workers), count($tasks));
        return ['matched' => $matching, 'unmatched' => count($tasks) - $matching];
    }
}

// 2. Bandwidth allocation
// Microservice-lər arası request limit — max throughput tap

// 3. Inventory distribution
// Anbar→distribution center→mağazalar capacity modelləşdirməsi

// 4. A/B testing traffic routing
// User segment → feature experiment → metric collection
// Max flow ilə optimal traffic distribution
```

---

## Əlaqəli Mövzular

- [25-graphs-basics.md](25-graphs-basics.md) — Graph reprezentasiyası, BFS/DFS
- [26-graphs-advanced.md](26-graphs-advanced.md) — Dijkstra, Bellman-Ford, shortest path
- [36-graphs-mst.md](36-graphs-mst.md) — Minimum Spanning Tree (Kruskal, Prim)
- [28-union-find.md](28-union-find.md) — Graph component-ləri
- [21-greedy-algorithms.md](21-greedy-algorithms.md) — Greedy approach (MST ilə əlaqəsi)
