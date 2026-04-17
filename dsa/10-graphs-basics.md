# Graphs - Basics (Qraflar - Esaslar)

## Konsept (Concept)

Graph node-lardan (vertex) ve onlari birlesdiren edge-lerden ibaret data structure-dur. Tree xususi bir graph novudur (connected, acyclic). Graph-lar real dunyada sosial sebekeler, xeriteler, internet ve s. temsil edir.

```
Undirected Graph:          Directed Graph (Digraph):
    A --- B                    A --> B
    |   / |                    |   / |
    |  /  |                    v  v  v
    C --- D                    C --> D

Weighted Graph:
    A --5-- B
    |      / |
    3    2   4
    |  /     |
    C --1--- D
```

### Terminologiya:
- **Vertex (Node)**: Qrafin tepesi
- **Edge**: Iki vertex arasindaki baglanti
- **Degree**: Vertex-e bagli edge sayi (in-degree, out-degree directed-de)
- **Path**: Vertex-ler arasi yol
- **Cycle**: Baslangicdaki vertex-e qayidan path
- **Connected**: Her vertex-den her vertex-e path var
- **DAG**: Directed Acyclic Graph (cycle yoxdur)

## Nece Isleyir? (How does it work?)

### Temsil Usullari (Representations)

```
Graph:  A -- B
        |    |
        C -- D

1) Adjacency Matrix:
     A  B  C  D
A [  0  1  1  0 ]
B [  1  0  0  1 ]
C [  1  0  0  1 ]
D [  0  1  1  0 ]

Yaddas: O(V^2)
Edge yoxlama: O(1)
Butun qonsulari tapma: O(V)

2) Adjacency List:
A -> [B, C]
B -> [A, D]
C -> [A, D]
D -> [B, C]

Yaddas: O(V + E)
Edge yoxlama: O(degree)
Butun qonsulari tapma: O(degree)
```

### BFS (Breadth-First Search):
```
Baslangic: A

Queue: [A]  Visited: {A}
Step 1: Dequeue A, enqueue B,C -> Queue: [B,C]  Visited: {A,B,C}
Step 2: Dequeue B, enqueue D   -> Queue: [C,D]  Visited: {A,B,C,D}
Step 3: Dequeue C, D artiq var -> Queue: [D]     Visited: {A,B,C,D}
Step 4: Dequeue D              -> Queue: []      Visited: {A,B,C,D}

BFS order: A -> B -> C -> D (layer by layer)
```

### DFS (Depth-First Search):
```
Baslangic: A

Stack: [A]  Visited: {A}
Step 1: Pop A, push B,C  -> Stack: [C,B]  Visited: {A,B,C}
Step 2: Pop B, push D    -> Stack: [C,D]  Visited: {A,B,C,D}
Step 3: Pop D, C artiq   -> Stack: [C]    Visited: {A,B,C,D}
Step 4: Pop C            -> Stack: []     Visited: {A,B,C,D}

DFS order: A -> B -> D -> C (deep first)
```

## Implementasiya (Implementation)

```php
<?php

class Graph
{
    private array $adjacencyList = [];
    private bool $directed;

    public function __construct(bool $directed = false)
    {
        $this->directed = $directed;
    }

    public function addVertex(string $vertex): void
    {
        if (!isset($this->adjacencyList[$vertex])) {
            $this->adjacencyList[$vertex] = [];
        }
    }

    public function addEdge(string $from, string $to, int $weight = 1): void
    {
        $this->addVertex($from);
        $this->addVertex($to);

        $this->adjacencyList[$from][] = ['vertex' => $to, 'weight' => $weight];
        if (!$this->directed) {
            $this->adjacencyList[$to][] = ['vertex' => $from, 'weight' => $weight];
        }
    }

    /**
     * BFS - Breadth-First Search
     * Queue istifade edir, layer-by-layer gedir
     * Time: O(V + E), Space: O(V)
     */
    public function bfs(string $start): array
    {
        $visited = [];
        $result = [];
        $queue = new SplQueue();

        $visited[$start] = true;
        $queue->enqueue($start);

        while (!$queue->isEmpty()) {
            $vertex = $queue->dequeue();
            $result[] = $vertex;

            foreach ($this->adjacencyList[$vertex] as $neighbor) {
                $next = $neighbor['vertex'];
                if (!isset($visited[$next])) {
                    $visited[$next] = true;
                    $queue->enqueue($next);
                }
            }
        }

        return $result;
    }

    /**
     * DFS - Depth-First Search (iterative)
     * Stack istifade edir, derine gedir
     * Time: O(V + E), Space: O(V)
     */
    public function dfs(string $start): array
    {
        $visited = [];
        $result = [];
        $stack = new SplStack();

        $stack->push($start);

        while (!$stack->isEmpty()) {
            $vertex = $stack->pop();
            if (isset($visited[$vertex])) {
                continue;
            }

            $visited[$vertex] = true;
            $result[] = $vertex;

            // Qonsulari stack-e elave et
            foreach (array_reverse($this->adjacencyList[$vertex]) as $neighbor) {
                if (!isset($visited[$neighbor['vertex']])) {
                    $stack->push($neighbor['vertex']);
                }
            }
        }

        return $result;
    }

    /**
     * DFS - Recursive versiya
     */
    public function dfsRecursive(string $start, array &$visited = [], array &$result = []): array
    {
        $visited[$start] = true;
        $result[] = $start;

        foreach ($this->adjacencyList[$start] as $neighbor) {
            if (!isset($visited[$neighbor['vertex']])) {
                $this->dfsRecursive($neighbor['vertex'], $visited, $result);
            }
        }

        return $result;
    }

    /**
     * BFS ile en qisa yol (unweighted graph)
     * Time: O(V + E), Space: O(V)
     */
    public function shortestPath(string $start, string $end): ?array
    {
        $visited = [$start => true];
        $parent = [$start => null];
        $queue = new SplQueue();
        $queue->enqueue($start);

        while (!$queue->isEmpty()) {
            $vertex = $queue->dequeue();

            if ($vertex === $end) {
                // Yolu geri qur
                $path = [];
                $current = $end;
                while ($current !== null) {
                    array_unshift($path, $current);
                    $current = $parent[$current];
                }
                return $path;
            }

            foreach ($this->adjacencyList[$vertex] as $neighbor) {
                $next = $neighbor['vertex'];
                if (!isset($visited[$next])) {
                    $visited[$next] = true;
                    $parent[$next] = $vertex;
                    $queue->enqueue($next);
                }
            }
        }

        return null; // Yol yoxdur
    }

    /**
     * Connected Components tapmaq (undirected graph)
     * Time: O(V + E), Space: O(V)
     */
    public function connectedComponents(): array
    {
        $visited = [];
        $components = [];

        foreach (array_keys($this->adjacencyList) as $vertex) {
            if (!isset($visited[$vertex])) {
                $component = [];
                $this->dfsRecursive($vertex, $visited, $component);
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Cycle detection (undirected graph)
     */
    public function hasCycle(): bool
    {
        $visited = [];

        foreach (array_keys($this->adjacencyList) as $vertex) {
            if (!isset($visited[$vertex])) {
                if ($this->detectCycleDFS($vertex, null, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function detectCycleDFS(string $vertex, ?string $parent, array &$visited): bool
    {
        $visited[$vertex] = true;

        foreach ($this->adjacencyList[$vertex] as $neighbor) {
            $next = $neighbor['vertex'];
            if (!isset($visited[$next])) {
                if ($this->detectCycleDFS($next, $vertex, $visited)) {
                    return true;
                }
            } elseif ($next !== $parent) {
                return true; // Cycle tapildi
            }
        }

        return false;
    }

    public function getAdjacencyList(): array
    {
        return $this->adjacencyList;
    }
}

// Adjacency Matrix implementasiyasi
class GraphMatrix
{
    private array $matrix;
    private array $vertices;
    private int $size;

    public function __construct(array $vertices)
    {
        $this->vertices = $vertices;
        $this->size = count($vertices);
        $this->matrix = array_fill(0, $this->size, array_fill(0, $this->size, 0));
    }

    public function addEdge(string $from, string $to, int $weight = 1): void
    {
        $i = array_search($from, $this->vertices);
        $j = array_search($to, $this->vertices);
        $this->matrix[$i][$j] = $weight;
        $this->matrix[$j][$i] = $weight; // undirected
    }

    public function hasEdge(string $from, string $to): bool
    {
        $i = array_search($from, $this->vertices);
        $j = array_search($to, $this->vertices);
        return $this->matrix[$i][$j] !== 0;
    }
}

// --- Test ---
$graph = new Graph();
$graph->addEdge('A', 'B');
$graph->addEdge('A', 'C');
$graph->addEdge('B', 'D');
$graph->addEdge('C', 'D');

echo "BFS: " . implode(' -> ', $graph->bfs('A')) . "\n";
// BFS: A -> B -> C -> D

echo "DFS: " . implode(' -> ', $graph->dfs('A')) . "\n";
// DFS: A -> B -> D -> C

$path = $graph->shortestPath('A', 'D');
echo "Shortest path A->D: " . implode(' -> ', $path) . "\n";
// Shortest path A->D: A -> B -> D

echo "Has cycle: " . ($graph->hasCycle() ? 'Yes' : 'No') . "\n";
// Has cycle: Yes

$components = $graph->connectedComponents();
echo "Connected components: " . count($components) . "\n";
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Emeliyyat | Adjacency List | Adjacency Matrix |
|-----------|---------------|-----------------|
| Add Vertex | O(1) | O(V^2) |
| Add Edge | O(1) | O(1) |
| Remove Edge | O(E) | O(1) |
| Has Edge | O(degree) | O(1) |
| All Neighbors | O(degree) | O(V) |
| BFS/DFS | O(V + E) | O(V^2) |
| Space | O(V + E) | O(V^2) |

**Ne zaman hansini istifade etmeli:**
- Adjacency List: Sparse graph (az edge), cogu halda bu daha yaxsidir
- Adjacency Matrix: Dense graph, ve ya edge yoxlama tez-tez lazimdir

## Tipik Meseler (Common Problems)

### 1. Number of Islands (LeetCode 200)
```php
<?php
function numIslands(array $grid): int
{
    $rows = count($grid);
    $cols = count($grid[0]);
    $count = 0;

    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            if ($grid[$i][$j] === '1') {
                $count++;
                floodFill($grid, $i, $j, $rows, $cols);
            }
        }
    }

    return $count;
}

function floodFill(array &$grid, int $r, int $c, int $rows, int $cols): void
{
    if ($r < 0 || $r >= $rows || $c < 0 || $c >= $cols || $grid[$r][$c] !== '1') {
        return;
    }

    $grid[$r][$c] = '0'; // visited
    floodFill($grid, $r + 1, $c, $rows, $cols);
    floodFill($grid, $r - 1, $c, $rows, $cols);
    floodFill($grid, $r, $c + 1, $rows, $cols);
    floodFill($grid, $r, $c - 1, $rows, $cols);
}
```

### 2. Clone Graph (LeetCode 133)
```php
<?php
function cloneGraph($node, array &$cloned = []): ?object
{
    if ($node === null) return null;

    if (isset($cloned[$node->val])) {
        return $cloned[$node->val];
    }

    $copy = new Node($node->val);
    $cloned[$node->val] = $copy;

    foreach ($node->neighbors as $neighbor) {
        $copy->neighbors[] = cloneGraph($neighbor, $cloned);
    }

    return $copy;
}
```

### 3. Is Graph Bipartite? (LeetCode 785)
```php
<?php
function isBipartite(array $graph): bool
{
    $n = count($graph);
    $colors = array_fill(0, $n, -1);

    for ($i = 0; $i < $n; $i++) {
        if ($colors[$i] !== -1) continue;

        $queue = new SplQueue();
        $queue->enqueue($i);
        $colors[$i] = 0;

        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();
            foreach ($graph[$node] as $neighbor) {
                if ($colors[$neighbor] === -1) {
                    $colors[$neighbor] = 1 - $colors[$node];
                    $queue->enqueue($neighbor);
                } elseif ($colors[$neighbor] === $colors[$node]) {
                    return false;
                }
            }
        }
    }

    return true;
}
```

## Interview Suallari

1. **BFS ve DFS arasinda ferq nedir?**
   - BFS: Queue istifade edir, layer-by-layer, en qisa yol tapir (unweighted)
   - DFS: Stack/recursion istifade edir, derine gedir, yaddas az istifade edir
   - BFS shortest path ucun, DFS cycle detection/topological sort ucun daha uygundir

2. **Graph-i nece saxlayarsiniz? Adjacency list vs matrix?**
   - List: Sparse graph ucun yaxsidir, O(V+E) yaddas
   - Matrix: Dense graph ucun, edge lookup O(1)
   - Real proyektlerde demek olar ki hemise adjacency list istifade olunur

3. **Connected component nedir?**
   - Qrafin bir hissesidir ki, icindeki her vertex diger her vertex-e path ile catir
   - BFS/DFS ile tapilir, her unvisited vertex-den basla

4. **Bipartite graph nedir?**
   - Vertex-leri 2 qrupa bolmek olur ki, eyni qrupdaki vertex-ler arasinda edge yoxdur
   - BFS ile 2-coloring ile yoxlanir

## PHP/Laravel ile Elaqe

- **Social network**: User-ler arasi friend/follow elaqeleri graph-dir
- **Route planning**: Laravel-de xerite/naviqasiya xidmetleri BFS/DFS istifade edir
- **Dependency resolution**: Composer paket dependency-leri graph-dir
- **Recommendation system**: User-item graph ile oxsar istifadecileri tap
- **Database relations**: Laravel Eloquent relationships aslinda graph traversal-dir
