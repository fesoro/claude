# Graphs - Advanced (Senior)

## Konsept (Concept)

Advanced graph algoritmleri weighted graph-larda en qisa yol, topological sort ve strongly connected component tapmaq ucun istifade olunur.

```
Weighted Directed Graph:
    A --4--> B --2--> E
    |        ^        ^
    2        1        3
    v        |        |
    C --3--> D --5--> F

En qisa yol A -> E:
  A->B->E = 4+2 = 6
  A->C->D->B->E = 2+3+1+2 = 8
  Cavab: 6
```

### Algoritmlerin muqayisesi:
| Algoritm | Negative edges | All pairs | Vaxt |
|----------|---------------|-----------|------|
| Dijkstra | Xeyr | Xeyr | O((V+E)logV) |
| Bellman-Ford | Beli | Xeyr | O(VE) |
| Floyd-Warshall | Beli | Beli | O(V^3) |
| A* | Xeyr | Xeyr | O(E) best case |

## Nece Isleyir? (How does it work?)

### Dijkstra Algoritmi:
```
Graph: A->B(4), A->C(2), B->E(2), C->D(3), D->B(1), D->E(5)

Start: A, dist = {A:0, B:INF, C:INF, D:INF, E:INF}

Step 1: Process A (dist=0)
  B: 0+4=4 < INF -> dist[B]=4
  C: 0+2=2 < INF -> dist[C]=2
  dist = {A:0, B:4, C:2, D:INF, E:INF}

Step 2: Process C (dist=2, minimum unvisited)
  D: 2+3=5 < INF -> dist[D]=5
  dist = {A:0, B:4, C:2, D:5, E:INF}

Step 3: Process B (dist=4)
  E: 4+2=6 < INF -> dist[E]=6
  dist = {A:0, B:4, C:2, D:5, E:6}

Step 4: Process D (dist=5)
  B: 5+1=6 > 4 -> deyismez
  E: 5+5=10 > 6 -> deyismez

Step 5: Process E (dist=6)
  Final: {A:0, B:4, C:2, D:5, E:6}
```

### Topological Sort:
```
DAG:  A -> B -> D
      |         ^
      v         |
      C --------+

In-degrees: A=0, B=1, C=1, D=2

Kahn's Algorithm:
Step 1: Queue = [A] (in-degree 0)
Step 2: Process A, decrement B,C. Queue = [B,C]
Step 3: Process B, decrement D. Queue = [C]
Step 4: Process C, decrement D. Queue = [D]
Step 5: Process D. Queue = []

Result: [A, B, C, D]
```

## Implementasiya (Implementation)

```php
<?php

class WeightedGraph
{
    private array $adjacencyList = [];
    private bool $directed;

    public function __construct(bool $directed = true)
    {
        $this->directed = $directed;
    }

    public function addEdge(string $from, string $to, int $weight): void
    {
        if (!isset($this->adjacencyList[$from])) $this->adjacencyList[$from] = [];
        if (!isset($this->adjacencyList[$to])) $this->adjacencyList[$to] = [];

        $this->adjacencyList[$from][] = ['vertex' => $to, 'weight' => $weight];
        if (!$this->directed) {
            $this->adjacencyList[$to][] = ['vertex' => $from, 'weight' => $weight];
        }
    }

    /**
     * Dijkstra - En qisa yol (negative weight olmayan graph)
     * Priority Queue (min-heap) istifade edir
     * Time: O((V + E) log V), Space: O(V)
     */
    public function dijkstra(string $start): array
    {
        $dist = [];
        $prev = [];
        $pq = new SplPriorityQueue();
        $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

        foreach (array_keys($this->adjacencyList) as $v) {
            $dist[$v] = PHP_INT_MAX;
            $prev[$v] = null;
        }

        $dist[$start] = 0;
        $pq->insert($start, 0); // negative priority for min-heap

        while (!$pq->isEmpty()) {
            $item = $pq->extract();
            $u = $item['data'];
            $d = -$item['priority']; // restore actual distance

            if ($d > $dist[$u]) continue; // skip outdated entry

            foreach ($this->adjacencyList[$u] as $edge) {
                $v = $edge['vertex'];
                $newDist = $dist[$u] + $edge['weight'];

                if ($newDist < $dist[$v]) {
                    $dist[$v] = $newDist;
                    $prev[$v] = $u;
                    $pq->insert($v, -$newDist);
                }
            }
        }

        return ['distances' => $dist, 'previous' => $prev];
    }

    /**
     * Dijkstra ile yolu geri qur
     */
    public function shortestPath(string $start, string $end): array
    {
        $result = $this->dijkstra($start);
        $path = [];
        $current = $end;

        while ($current !== null) {
            array_unshift($path, $current);
            $current = $result['previous'][$current];
        }

        return [
            'path' => $path,
            'distance' => $result['distances'][$end],
        ];
    }

    /**
     * Bellman-Ford - Negative weight destekleyir, negative cycle ashkar edir
     * Time: O(V * E), Space: O(V)
     */
    public function bellmanFord(string $start): array
    {
        $dist = [];
        $prev = [];
        $vertices = array_keys($this->adjacencyList);

        foreach ($vertices as $v) {
            $dist[$v] = PHP_INT_MAX;
            $prev[$v] = null;
        }
        $dist[$start] = 0;

        // V-1 defe butun edge-leri relax et
        for ($i = 0; $i < count($vertices) - 1; $i++) {
            foreach ($vertices as $u) {
                if ($dist[$u] === PHP_INT_MAX) continue;
                foreach ($this->adjacencyList[$u] as $edge) {
                    $v = $edge['vertex'];
                    $newDist = $dist[$u] + $edge['weight'];
                    if ($newDist < $dist[$v]) {
                        $dist[$v] = $newDist;
                        $prev[$v] = $u;
                    }
                }
            }
        }

        // Negative cycle yoxla
        foreach ($vertices as $u) {
            if ($dist[$u] === PHP_INT_MAX) continue;
            foreach ($this->adjacencyList[$u] as $edge) {
                if ($dist[$u] + $edge['weight'] < $dist[$edge['vertex']]) {
                    throw new RuntimeException("Negative cycle detected!");
                }
            }
        }

        return ['distances' => $dist, 'previous' => $prev];
    }

    /**
     * Floyd-Warshall - Butun cutler arasi en qisa yol
     * Time: O(V^3), Space: O(V^2)
     */
    public function floydWarshall(): array
    {
        $vertices = array_keys($this->adjacencyList);
        $n = count($vertices);
        $index = array_flip($vertices);

        // Initialize distance matrix
        $dist = array_fill(0, $n, array_fill(0, $n, PHP_INT_MAX));
        for ($i = 0; $i < $n; $i++) {
            $dist[$i][$i] = 0;
        }

        foreach ($vertices as $u) {
            foreach ($this->adjacencyList[$u] as $edge) {
                $dist[$index[$u]][$index[$edge['vertex']]] = $edge['weight'];
            }
        }

        // Her vertex-i ara node kimi yoxla
        for ($k = 0; $k < $n; $k++) {
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    if ($dist[$i][$k] !== PHP_INT_MAX && $dist[$k][$j] !== PHP_INT_MAX) {
                        $dist[$i][$j] = min($dist[$i][$j], $dist[$i][$k] + $dist[$k][$j]);
                    }
                }
            }
        }

        return ['distances' => $dist, 'vertices' => $vertices];
    }

    /**
     * Topological Sort - Kahn's Algorithm (BFS-based)
     * Yalniz DAG ucun isliyir
     * Time: O(V + E), Space: O(V)
     */
    public function topologicalSort(): array
    {
        $inDegree = [];
        foreach (array_keys($this->adjacencyList) as $v) {
            $inDegree[$v] = 0;
        }

        foreach ($this->adjacencyList as $u => $edges) {
            foreach ($edges as $edge) {
                $inDegree[$edge['vertex']]++;
            }
        }

        $queue = new SplQueue();
        foreach ($inDegree as $v => $degree) {
            if ($degree === 0) {
                $queue->enqueue($v);
            }
        }

        $result = [];
        while (!$queue->isEmpty()) {
            $u = $queue->dequeue();
            $result[] = $u;

            foreach ($this->adjacencyList[$u] as $edge) {
                $inDegree[$edge['vertex']]--;
                if ($inDegree[$edge['vertex']] === 0) {
                    $queue->enqueue($edge['vertex']);
                }
            }
        }

        if (count($result) !== count($this->adjacencyList)) {
            throw new RuntimeException("Graph has a cycle! Topological sort not possible.");
        }

        return $result;
    }

    /**
     * Strongly Connected Components - Kosaraju's Algorithm
     * Time: O(V + E), Space: O(V)
     */
    public function stronglyConnectedComponents(): array
    {
        $vertices = array_keys($this->adjacencyList);
        $visited = [];
        $finishOrder = [];

        // Pass 1: DFS ile finish order yaz
        foreach ($vertices as $v) {
            if (!isset($visited[$v])) {
                $this->dfsFinish($v, $visited, $finishOrder);
            }
        }

        // Transpose graph yarat
        $transpose = [];
        foreach ($vertices as $v) {
            $transpose[$v] = [];
        }
        foreach ($this->adjacencyList as $u => $edges) {
            foreach ($edges as $edge) {
                $transpose[$edge['vertex']][] = ['vertex' => $u, 'weight' => $edge['weight']];
            }
        }

        // Pass 2: Reverse finish order ile transpose-da DFS
        $visited = [];
        $components = [];
        foreach (array_reverse($finishOrder) as $v) {
            if (!isset($visited[$v])) {
                $component = [];
                $this->dfsCollect($v, $transpose, $visited, $component);
                $components[] = $component;
            }
        }

        return $components;
    }

    private function dfsFinish(string $v, array &$visited, array &$finishOrder): void
    {
        $visited[$v] = true;
        foreach ($this->adjacencyList[$v] as $edge) {
            if (!isset($visited[$edge['vertex']])) {
                $this->dfsFinish($edge['vertex'], $visited, $finishOrder);
            }
        }
        $finishOrder[] = $v;
    }

    private function dfsCollect(string $v, array &$graph, array &$visited, array &$component): void
    {
        $visited[$v] = true;
        $component[] = $v;
        foreach ($graph[$v] as $edge) {
            if (!isset($visited[$edge['vertex']])) {
                $this->dfsCollect($edge['vertex'], $graph, $visited, $component);
            }
        }
    }
}

// --- Test ---
$g = new WeightedGraph(true);
$g->addEdge('A', 'B', 4);
$g->addEdge('A', 'C', 2);
$g->addEdge('B', 'E', 2);
$g->addEdge('C', 'D', 3);
$g->addEdge('D', 'B', 1);
$g->addEdge('D', 'E', 5);

$result = $g->dijkstra('A');
echo "Dijkstra distances from A:\n";
foreach ($result['distances'] as $v => $d) {
    echo "  $v: $d\n";
}
// A:0, B:4, C:2, D:5, E:6

$path = $g->shortestPath('A', 'E');
echo "Shortest path A->E: " . implode('->', $path['path']) . " (dist: {$path['distance']})\n";

// Topological sort test
$dag = new WeightedGraph(true);
$dag->addEdge('A', 'B', 1);
$dag->addEdge('A', 'C', 1);
$dag->addEdge('B', 'D', 1);
$dag->addEdge('C', 'D', 1);
echo "Topological sort: " . implode(' -> ', $dag->topologicalSort()) . "\n";
// A -> B -> C -> D (or A -> C -> B -> D)
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Time | Space | Negative edges | Qeyd |
|----------|------|-------|---------------|------|
| Dijkstra (heap) | O((V+E)logV) | O(V) | Xeyr | En populyar |
| Bellman-Ford | O(VE) | O(V) | Beli | Negative cycle detect |
| Floyd-Warshall | O(V^3) | O(V^2) | Beli | All-pairs |
| Topological Sort | O(V+E) | O(V) | - | Yalniz DAG |
| Kosaraju SCC | O(V+E) | O(V) | - | 2x DFS |

## Tipik Meseler (Common Problems)

### 1. Network Delay Time (LeetCode 743)
```php
<?php
function networkDelayTime(array $times, int $n, int $k): int
{
    $graph = [];
    for ($i = 1; $i <= $n; $i++) $graph[$i] = [];
    foreach ($times as [$u, $v, $w]) {
        $graph[$u][] = [$v, $w];
    }

    $dist = array_fill(1, $n, PHP_INT_MAX);
    $dist[$k] = 0;
    $pq = new SplPriorityQueue();
    $pq->insert($k, 0);

    while (!$pq->isEmpty()) {
        $u = $pq->extract();
        foreach ($graph[$u] as [$v, $w]) {
            if ($dist[$u] + $w < $dist[$v]) {
                $dist[$v] = $dist[$u] + $w;
                $pq->insert($v, -$dist[$v]);
            }
        }
    }

    $max = max($dist);
    return $max === PHP_INT_MAX ? -1 : $max;
}
```

### 2. Course Schedule (LeetCode 207)
```php
<?php
function canFinish(int $numCourses, array $prerequisites): bool
{
    $graph = array_fill(0, $numCourses, []);
    $inDegree = array_fill(0, $numCourses, 0);

    foreach ($prerequisites as [$course, $prereq]) {
        $graph[$prereq][] = $course;
        $inDegree[$course]++;
    }

    $queue = new SplQueue();
    for ($i = 0; $i < $numCourses; $i++) {
        if ($inDegree[$i] === 0) $queue->enqueue($i);
    }

    $count = 0;
    while (!$queue->isEmpty()) {
        $u = $queue->dequeue();
        $count++;
        foreach ($graph[$u] as $v) {
            if (--$inDegree[$v] === 0) {
                $queue->enqueue($v);
            }
        }
    }

    return $count === $numCourses;
}
```

### 3. Cheapest Flights Within K Stops (LeetCode 787)
```php
<?php
function findCheapestPrice(int $n, array $flights, int $src, int $dst, int $k): int
{
    $dist = array_fill(0, $n, PHP_INT_MAX);
    $dist[$src] = 0;

    // Bellman-Ford, k+1 iteration ile
    for ($i = 0; $i <= $k; $i++) {
        $temp = $dist;
        foreach ($flights as [$u, $v, $w]) {
            if ($dist[$u] !== PHP_INT_MAX && $dist[$u] + $w < $temp[$v]) {
                $temp[$v] = $dist[$u] + $w;
            }
        }
        $dist = $temp;
    }

    return $dist[$dst] === PHP_INT_MAX ? -1 : $dist[$dst];
}
```

## Interview Suallari

1. **Dijkstra niye negative weight ile islemir?**
   - Dijkstra greedy yanasmadi: bir vertex process olunanda, onun en qisa yolu tapilmis hesab olunur
   - Negative edge bu ferzi pozur: sonra daha qisa yol tapila biler
   - Bele halda Bellman-Ford istifade edin

2. **Topological sort ne vaxt istifade olunur?**
   - Build systems (task dependency), course scheduling, compilation order
   - Yalniz DAG ucun mumkundur (cycle varsa, topological order yoxdur)

3. **Floyd-Warshall nece isleyir?**
   - Dynamic Programming: dist[i][j] = min(dist[i][j], dist[i][k] + dist[k][j])
   - Her vertex-i intermediate node kimi sinayir
   - All-pairs shortest path verir

4. **Dijkstra vs BFS ferqi?**
   - BFS: unweighted graph-da en qisa yol (hop count)
   - Dijkstra: weighted graph-da en qisa yol (total weight)

## PHP/Laravel ile Elaqe

- **Route optimization**: Delivery yollari, GPS naviqasiya Dijkstra istifade edir
- **Task scheduling**: Laravel queue job dependency-leri topological sort ile hell olunur
- **Network analysis**: Mikroservisler arasi latency hesablamasi
- **Build tools**: Composer/webpack dependency resolution topological sort istifade edir
