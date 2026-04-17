# Network Flow (Şəbəkə Axını)

## Konsept (Concept)

**Network Flow** — istiqamətli qrafda **source** (s) və **sink** (t) nöqtələri arasında maksimum axın miqdarını tapan problem. Hər edge `u → v` üzərində `capacity(u,v)` limiti var.

### Tətbiqlər
- **Bipartite Matching** — işçilər ↔ vəzifələr
- **Min-Cut problems** — şəbəkəni minimum xərclə iki hissəyə ayırmaq
- **Image segmentation** — foreground/background
- **Transportation & logistics**
- **Project selection**

### Əsas teoremlər
- **Max-Flow Min-Cut**: maximum flow = minimum cut dəyəri
- **Integrality Theorem**: tam ədədli capacity-lərdə maksimum flow da tam ədədlidir

## Necə İşləyir?

### Ford-Fulkerson
1. Residual graph qur (başlanğıcda = original)
2. s-dən t-yə **augmenting path** tap (nə olursa olsun — DFS)
3. Bu yolun minimum capacity-ini (`bottleneck`) axına əlavə et
4. Residual capacity-ləri yenilə (forward azalır, reverse artır)
5. Augmenting path qalmayana qədər təkrar et

### Edmonds-Karp
Ford-Fulkerson-un BFS istifadə edən xüsusi halı. Path-ı **ən az edge-li** seçir. O(V · E²).

### Dinic's
BFS ilə "level graph" qur, DFS ilə blocking flow tap. O(V² · E). Unit capacity-də O(E · √V).

### Hopcroft-Karp (Bipartite Matching)
İki tərəfli qrafda maksimum matching. Effectively Dinic's — O(E · √V).

## İmplementasiya (Implementation) - PHP

### 1. Edmonds-Karp (BFS-based Ford-Fulkerson)

```php
class EdmondsKarp {
    private array $capacity; // [u][v] => capacity
    private int $n;

    public function __construct(int $n) {
        $this->n = $n;
        $this->capacity = array_fill(0, $n, array_fill(0, $n, 0));
    }

    public function addEdge(int $u, int $v, int $cap): void {
        $this->capacity[$u][$v] += $cap;
    }

    private function bfs(int $s, int $t, array &$parent): int {
        $parent = array_fill(0, $this->n, -1);
        $parent[$s] = -2;
        $queue = [[$s, PHP_INT_MAX]];
        while (!empty($queue)) {
            [$cur, $flow] = array_shift($queue);
            for ($next = 0; $next < $this->n; $next++) {
                if ($parent[$next] === -1 && $this->capacity[$cur][$next] > 0) {
                    $parent[$next] = $cur;
                    $newFlow = min($flow, $this->capacity[$cur][$next]);
                    if ($next === $t) return $newFlow;
                    $queue[] = [$next, $newFlow];
                }
            }
        }
        return 0;
    }

    public function maxFlow(int $s, int $t): int {
        $flow = 0;
        $parent = [];
        while (($newFlow = $this->bfs($s, $t, $parent)) > 0) {
            $flow += $newFlow;
            $cur = $t;
            while ($cur !== $s) {
                $prev = $parent[$cur];
                $this->capacity[$prev][$cur] -= $newFlow;
                $this->capacity[$cur][$prev] += $newFlow;
                $cur = $prev;
            }
        }
        return $flow;
    }
}

// İstifadə
$ek = new EdmondsKarp(6);
$ek->addEdge(0, 1, 16);
$ek->addEdge(0, 2, 13);
$ek->addEdge(1, 2, 10);
$ek->addEdge(1, 3, 12);
$ek->addEdge(2, 1, 4);
$ek->addEdge(2, 4, 14);
$ek->addEdge(3, 2, 9);
$ek->addEdge(3, 5, 20);
$ek->addEdge(4, 3, 7);
$ek->addEdge(4, 5, 4);
echo $ek->maxFlow(0, 5) . "\n"; // 23
```

### 2. Dinic's Algorithm

```php
class Dinic {
    private array $edges = []; // [to, cap, flow]
    private array $graph;       // graph[u] = [edge_idx, ...]
    private array $level;
    private array $iter;
    private int $n;

    public function __construct(int $n) {
        $this->n = $n;
        $this->graph = array_fill(0, $n, []);
    }

    public function addEdge(int $u, int $v, int $cap): void {
        $this->graph[$u][] = count($this->edges);
        $this->edges[] = [$v, $cap, 0];
        $this->graph[$v][] = count($this->edges);
        $this->edges[] = [$u, 0, 0];
    }

    private function bfs(int $s, int $t): bool {
        $this->level = array_fill(0, $this->n, -1);
        $this->level[$s] = 0;
        $queue = [$s];
        while (!empty($queue)) {
            $u = array_shift($queue);
            foreach ($this->graph[$u] as $id) {
                [$to, $cap, $flow] = $this->edges[$id];
                if ($this->level[$to] < 0 && $flow < $cap) {
                    $this->level[$to] = $this->level[$u] + 1;
                    $queue[] = $to;
                }
            }
        }
        return $this->level[$t] >= 0;
    }

    private function dfs(int $u, int $t, int $pushed): int {
        if ($u === $t) return $pushed;
        for (; $this->iter[$u] < count($this->graph[$u]); $this->iter[$u]++) {
            $id = $this->graph[$u][$this->iter[$u]];
            [$to, $cap, $flow] = $this->edges[$id];
            if ($this->level[$u] + 1 !== $this->level[$to] || $cap - $flow <= 0) continue;
            $d = $this->dfs($to, $t, min($pushed, $cap - $flow));
            if ($d > 0) {
                $this->edges[$id][2] += $d;
                $this->edges[$id ^ 1][2] -= $d;
                return $d;
            }
        }
        return 0;
    }

    public function maxFlow(int $s, int $t): int {
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

### 3. Bipartite Matching (Hungarian-style BFS)

```php
// Sol tərəf: 0..leftSize-1, Sağ tərəf: leftSize..leftSize+rightSize-1
// Source = leftSize+rightSize, Sink = leftSize+rightSize+1
function bipartiteMatch(array $edges, int $leftSize, int $rightSize): int {
    $n = $leftSize + $rightSize + 2;
    $source = $leftSize + $rightSize;
    $sink = $source + 1;

    $ek = new EdmondsKarp($n);
    for ($i = 0; $i < $leftSize; $i++) $ek->addEdge($source, $i, 1);
    foreach ($edges as [$u, $v]) $ek->addEdge($u, $leftSize + $v, 1);
    for ($j = 0; $j < $rightSize; $j++) $ek->addEdge($leftSize + $j, $sink, 1);

    return $ek->maxFlow($source, $sink);
}
```

### 4. Hopcroft-Karp (Bipartite, O(E√V))

```php
class HopcroftKarp {
    private array $adj;
    private array $pairU;
    private array $pairV;
    private array $dist;
    private int $u, $v;
    private const INF = PHP_INT_MAX;

    public function __construct(int $u, int $v) {
        $this->u = $u;
        $this->v = $v;
        $this->adj = array_fill(0, $u + 1, []);
    }

    public function addEdge(int $from, int $to): void {
        // 1-indexed to reserve NIL=0
        $this->adj[$from + 1][] = $to + 1;
    }

    private function bfs(): bool {
        $queue = [];
        for ($u = 1; $u <= $this->u; $u++) {
            if ($this->pairU[$u] === 0) {
                $this->dist[$u] = 0;
                $queue[] = $u;
            } else {
                $this->dist[$u] = self::INF;
            }
        }
        $this->dist[0] = self::INF;
        while (!empty($queue)) {
            $u = array_shift($queue);
            if ($this->dist[$u] < $this->dist[0]) {
                foreach ($this->adj[$u] as $v) {
                    $pair = $this->pairV[$v];
                    if ($this->dist[$pair] === self::INF) {
                        $this->dist[$pair] = $this->dist[$u] + 1;
                        $queue[] = $pair;
                    }
                }
            }
        }
        return $this->dist[0] !== self::INF;
    }

    private function dfs(int $u): bool {
        if ($u === 0) return true;
        foreach ($this->adj[$u] as $v) {
            $pair = $this->pairV[$v];
            if ($this->dist[$pair] === $this->dist[$u] + 1) {
                if ($this->dfs($pair)) {
                    $this->pairV[$v] = $u;
                    $this->pairU[$u] = $v;
                    return true;
                }
            }
        }
        $this->dist[$u] = self::INF;
        return false;
    }

    public function match(): int {
        $this->pairU = array_fill(0, $this->u + 1, 0);
        $this->pairV = array_fill(0, $this->v + 1, 0);
        $this->dist = array_fill(0, $this->u + 1, self::INF);
        $matching = 0;
        while ($this->bfs()) {
            for ($u = 1; $u <= $this->u; $u++) {
                if ($this->pairU[$u] === 0 && $this->dfs($u)) {
                    $matching++;
                }
            }
        }
        return $matching;
    }
}
```

### 5. Min-Cut Finding

```php
// Max-flow çalışdırdıqdan sonra, residual graph-da source-dan çata bilən node-ları tap.
// Onlar ilə qalanlar arasındakı edge-lər min-cut-u təşkil edir.
function findMinCut(EdmondsKarp $ek, int $s, int $n): array {
    // Burada residual capacity access-ə ehtiyac var — EdmondsKarp-i extend etmək lazım ola bilər
    // Sadəlik üçün konseptual cavab:
    // 1. maxFlow hesabla
    // 2. BFS/DFS source-dan residual qraf boyunca
    // 3. S tərəfdə olanlar ilə T tərəfdə olanlar arasındakı orijinal edge-ləri yığ
    return []; // placeholder — implementation-specific
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Alqoritm | Time | Space |
|----------|------|-------|
| Ford-Fulkerson (DFS) | O(E · max_flow) | O(V²) |
| Edmonds-Karp (BFS) | O(V · E²) | O(V²) |
| Dinic's | O(V² · E) | O(V + E) |
| Dinic's (unit capacity) | O(E · √V) | O(V + E) |
| Hopcroft-Karp | O(E · √V) | O(V + E) |

**Praktiki sürət**: Dinic's adətən Edmonds-Karp-dan 10-100x sürətli işləyir. Unit capacity qraflarda Hopcroft-Karp optimaldır.

## Tipik Məsələlər (Common Problems)

### 1. Maximum Bipartite Matching
İş axtarışı: N işçi, M vəzifə, əlaqələri var. Maksimum neçə işçi işə düzələ bilər?

### 2. Edge-Disjoint Paths
s-dən t-yə edge-ləri paylaşmayan neçə yol var? → max-flow unit capacity.

### 3. Minimum Vertex Cover (König's theorem)
Bipartite qrafda: `min vertex cover = max matching`. Həm də `max independent set = V - min vertex cover`.

### 4. Project Selection
Hər layihə gəlir/xərc. Bəzi layihələr bir-birindən asılı. Maksimum mənfəətli alt-çoxluq? → min-cut modeli.

### 5. Image Segmentation
Foreground/background ayırmaq. Piksellər node-dir, qonşu piksellər arasında edge. Source = "foreground", sink = "background". Min-cut optimal ayırmanı verir.

## Interview Sualları

**1. Max-flow nə deməkdir?**
Source-dan sink-ə göndərilə bilən maksimum "maye" miqdarı. Hər edge-in bir capacity (limit) var. Hər node üçün inflow = outflow (conservation).

**2. Max-Flow Min-Cut Teoremi?**
Maximum flow = Minimum cut. "Cut" — qrafı s və t-ni ayıran edge-lər çoxluğu. Minimum cut-un capacity cəmi = max flow.

**3. Residual graph nədir?**
Orijinal qraf + `reverse edge`-lər. `u→v`-də `capacity 10, flow 3` olsa, residual: `u→v: 7, v→u: 3`. Reverse edge alqoritmə səhvləri düzəltmək imkanı verir.

**4. Edmonds-Karp niyə O(V · E²)?**
Hər BFS O(E). Augmenting path sayı O(V·E)-dən çox ola bilməz (dərinlik analizi). Cəmi O(V · E²).

**5. Dinic-in "level graph"-ı nədir?**
BFS ilə hər node-a level (source-dan məsafə) təyin et. DFS yalnız `level[u]+1 = level[v]` olan edge-lərlə gedir. Bu shortest augmenting path-ları paralel tapmağı asanlaşdırır.

**6. Bipartite matching üçün niyə Hungarian algorithm?**
Weighted bipartite matching üçün. Adi unweighted üçün Hopcroft-Karp daha sadədir. Hungarian O(V³) weighted halda optimaldır.

**7. Flow ilə DP arasında əlaqə?**
DAG-da minimum cost flow ↔ DP ilə həll oluna bilər. Bəzən DP modelini min-cost flow kimi yenidən ifadə etmək daha effektiv olur.

**8. Min-cut-u tapdıqdan sonra hansı edge-lər cut-dur?**
Max-flow hesabla. Residual graph-da source-dan çata bilən node-ları (S) tap. Qalan node-lar (T). Min-cut = `{(u, v) : u ∈ S, v ∈ T, original edge}`.

**9. Flow alqoritmləri tam ədədsiz (float) işləyə bilərmi?**
Ford-Fulkerson üçün integrality teoremi yoxdur — sonsuz dövr edə bilər. Edmonds-Karp və Dinic's shortest path yanaşması sayəsində float capacity-lərdə də sonlu zamanda bitir.

**10. Real dünyada max-flow harada istifadə olunur?**
- Internet routing (ISP bandwidth allocation)
- Kidney exchange programs
- Airline crew assignment
- Sport tournament elimination
- Baseball elimination problem

## PHP/Laravel ilə Əlaqə

- **Rate limiting**: Leaky bucket / token bucket — flow concept-inin sadə tətbiqi.
- **Assignment systems**: course registration, shift scheduling — bipartite matching.
- **Fraud detection**: min-cut ilə "suspicious cluster"-ləri tapmaq.
- **Kubernetes scheduling**: pod ↔ node assignment flow kimi modelləşdirilə bilər.
- **Performance**: PHP-də network flow işlətmək mümkündür, amma saatlarla işləyə bilər. Praktiki məsələlərdə Python NetworkX, C++ LEMON, və ya CPLEX/Gurobi istifadə edin. PHP-dən yalnız API çağırışları ilə.
