# Graphs - MST (Lead)

## Konsept (Concept)

Minimum Spanning Tree (MST) connected, undirected, weighted graph-in butun vertex-lerini birlesdiren en az umumi weight-e sahib agacdir. MST-de V-1 edge olur ve cycle yoxdur.

```
Original Graph:           MST (total weight = 10):
    A --4-- B                A --4-- B
    |\ 8   /|                |       |
    2  \  2  3               2       2
    |    X   |               |       |
    C --1-- D                C --1-- D
    |       |                        |
    5       1                        1
    |       |                        |
    E --6-- F                E       F

MST edges: A-C(2), C-D(1), D-B(2), D-F(1), A-B(4)
Total: 2+1+2+1+4 = 10
```

### MST xususiyyetleri:
- Tam V-1 edge olur
- Unique weight-ler varsa, MST unique-dir
- Cycle elave olunmur
- Cut property: her cut-da en kicik edge MST-dedir

## Nece Isleyir? (How does it work?)

### Kruskal's Algorithm:
```
Butun edge-leri weight-e gore sirala, en kicikden basla:
Edge-ler: C-D(1), D-F(1), A-C(2), B-D(2), B-D(3), A-B(4), E-C(5), E-F(6), A-D(8)

Step 1: C-D(1) -> elave (cycle yox) -> {C,D}
Step 2: D-F(1) -> elave (cycle yox) -> {C,D,F}
Step 3: A-C(2) -> elave (cycle yox) -> {A,C,D,F}
Step 4: B-D(2) -> elave (cycle yox) -> {A,B,C,D,F}
Step 5: B-D(3) -> SKIP (cycle yaradir)
Step 6: A-B(4) -> SKIP (cycle yaradir)
Step 7: E-C(5) -> elave (cycle yox) -> {A,B,C,D,E,F} TAMAM!

MST edges: C-D(1), D-F(1), A-C(2), B-D(2), E-C(5) = 11
```

### Prim's Algorithm:
```
Baslangic: A, MST = {A}

Step 1: A-nin edge-leri: A-B(4), A-C(2), A-D(8)
        En kicik: A-C(2) -> MST = {A,C}

Step 2: C-nin edge-leri: C-D(1), C-E(5)  + A-B(4), A-D(8)
        En kicik: C-D(1) -> MST = {A,C,D}

Step 3: D-nin edge-leri: D-B(2), D-F(1) + A-B(4), C-E(5)
        En kicik: D-F(1) -> MST = {A,C,D,F}

Step 4: Remaining: D-B(2), A-B(4), C-E(5), E-F(6)
        En kicik: D-B(2) -> MST = {A,B,C,D,F}

Step 5: Remaining: C-E(5), E-F(6)
        En kicik: C-E(5) -> MST = {A,B,C,D,E,F} TAMAM!
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Union-Find (Disjoint Set) - Kruskal ucun lazimdir
 */
class UnionFind
{
    private array $parent;
    private array $rank;

    public function __construct(array $elements)
    {
        foreach ($elements as $e) {
            $this->parent[$e] = $e;
            $this->rank[$e] = 0;
        }
    }

    /**
     * Path compression ile find
     * Amortized O(α(n)) ≈ O(1)
     */
    public function find($x)
    {
        if ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->find($this->parent[$x]);
        }
        return $this->parent[$x];
    }

    /**
     * Union by rank
     * Amortized O(α(n)) ≈ O(1)
     */
    public function union($x, $y): bool
    {
        $rootX = $this->find($x);
        $rootY = $this->find($y);

        if ($rootX === $rootY) return false; // Artiq eyni set-dedir

        if ($this->rank[$rootX] < $this->rank[$rootY]) {
            $this->parent[$rootX] = $rootY;
        } elseif ($this->rank[$rootX] > $this->rank[$rootY]) {
            $this->parent[$rootY] = $rootX;
        } else {
            $this->parent[$rootY] = $rootX;
            $this->rank[$rootX]++;
        }

        return true;
    }

    public function connected($x, $y): bool
    {
        return $this->find($x) === $this->find($y);
    }
}

/**
 * Kruskal's Algorithm
 * Edge-leri sirala, cycle yaratmayanlarini elave et
 * Time: O(E log E), Space: O(V)
 */
function kruskal(array $vertices, array $edges): array
{
    // Edge-leri weight-e gore sirala
    usort($edges, fn($a, $b) => $a['weight'] - $b['weight']);

    $uf = new UnionFind($vertices);
    $mst = [];
    $totalWeight = 0;

    foreach ($edges as $edge) {
        if ($uf->union($edge['from'], $edge['to'])) {
            $mst[] = $edge;
            $totalWeight += $edge['weight'];

            // V-1 edge tapildi, dayandir
            if (count($mst) === count($vertices) - 1) {
                break;
            }
        }
    }

    return ['edges' => $mst, 'totalWeight' => $totalWeight];
}

/**
 * Prim's Algorithm
 * Min-heap ile en yaxin vertex-i sec
 * Time: O((V + E) log V), Space: O(V)
 */
function prim(array $adjacencyList, string $start): array
{
    $inMST = [];
    $mst = [];
    $totalWeight = 0;
    $pq = new SplPriorityQueue();
    $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

    $inMST[$start] = true;

    // Start vertex-in qonsularini elave et
    foreach ($adjacencyList[$start] as $edge) {
        $pq->insert(
            ['from' => $start, 'to' => $edge['vertex'], 'weight' => $edge['weight']],
            -$edge['weight']
        );
    }

    while (!$pq->isEmpty() && count($inMST) < count($adjacencyList)) {
        $item = $pq->extract();
        $edge = $item['data'];

        if (isset($inMST[$edge['to']])) continue;

        $inMST[$edge['to']] = true;
        $mst[] = $edge;
        $totalWeight += $edge['weight'];

        foreach ($adjacencyList[$edge['to']] as $neighbor) {
            if (!isset($inMST[$neighbor['vertex']])) {
                $pq->insert(
                    ['from' => $edge['to'], 'to' => $neighbor['vertex'], 'weight' => $neighbor['weight']],
                    -$neighbor['weight']
                );
            }
        }
    }

    return ['edges' => $mst, 'totalWeight' => $totalWeight];
}

// --- Test ---
$vertices = ['A', 'B', 'C', 'D', 'E', 'F'];
$edges = [
    ['from' => 'A', 'to' => 'B', 'weight' => 4],
    ['from' => 'A', 'to' => 'C', 'weight' => 2],
    ['from' => 'A', 'to' => 'D', 'weight' => 8],
    ['from' => 'B', 'to' => 'D', 'weight' => 2],
    ['from' => 'C', 'to' => 'D', 'weight' => 1],
    ['from' => 'C', 'to' => 'E', 'weight' => 5],
    ['from' => 'D', 'to' => 'F', 'weight' => 1],
    ['from' => 'E', 'to' => 'F', 'weight' => 6],
];

echo "=== Kruskal's MST ===\n";
$result = kruskal($vertices, $edges);
foreach ($result['edges'] as $e) {
    echo "  {$e['from']}-{$e['to']} ({$e['weight']})\n";
}
echo "Total: {$result['totalWeight']}\n";

// Prim's test
$adjList = [
    'A' => [['vertex'=>'B','weight'=>4], ['vertex'=>'C','weight'=>2], ['vertex'=>'D','weight'=>8]],
    'B' => [['vertex'=>'A','weight'=>4], ['vertex'=>'D','weight'=>2]],
    'C' => [['vertex'=>'A','weight'=>2], ['vertex'=>'D','weight'=>1], ['vertex'=>'E','weight'=>5]],
    'D' => [['vertex'=>'A','weight'=>8], ['vertex'=>'B','weight'=>2], ['vertex'=>'C','weight'=>1], ['vertex'=>'F','weight'=>1]],
    'E' => [['vertex'=>'C','weight'=>5], ['vertex'=>'F','weight'=>6]],
    'F' => [['vertex'=>'D','weight'=>1], ['vertex'=>'E','weight'=>6]],
];

echo "\n=== Prim's MST ===\n";
$result = prim($adjList, 'A');
foreach ($result['edges'] as $e) {
    echo "  {$e['from']}-{$e['to']} ({$e['weight']})\n";
}
echo "Total: {$result['totalWeight']}\n";
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Time | Space | Qeyd |
|----------|------|-------|------|
| Kruskal | O(E log E) | O(V) | Edge sort + Union-Find |
| Prim (heap) | O((V+E) log V) | O(V) | Dense graph ucun yaxsi |
| Prim (matrix) | O(V^2) | O(V) | Very dense graph ucun |
| Union-Find ops | O(α(n)) ≈ O(1) | O(V) | Amortized |

**Kruskal vs Prim:**
- Kruskal: Sparse graph ucun daha yaxsi (E << V^2)
- Prim: Dense graph ucun daha yaxsi
- Kruskal Union-Find lazimdir, Prim Priority Queue lazimdir

## Tipik Meseler (Common Problems)

### 1. Min Cost to Connect All Points (LeetCode 1584)
```php
<?php
function minCostConnectPoints(array $points): int
{
    $n = count($points);
    $inMST = array_fill(0, $n, false);
    $minDist = array_fill(0, $n, PHP_INT_MAX);
    $minDist[0] = 0;
    $totalCost = 0;

    for ($i = 0; $i < $n; $i++) {
        // En yaxin vertex-i tap
        $u = -1;
        for ($j = 0; $j < $n; $j++) {
            if (!$inMST[$j] && ($u === -1 || $minDist[$j] < $minDist[$u])) {
                $u = $j;
            }
        }

        $inMST[$u] = true;
        $totalCost += $minDist[$u];

        // Mesafeleri yenile
        for ($v = 0; $v < $n; $v++) {
            if (!$inMST[$v]) {
                $dist = abs($points[$u][0] - $points[$v][0])
                      + abs($points[$u][1] - $points[$v][1]);
                $minDist[$v] = min($minDist[$v], $dist);
            }
        }
    }

    return $totalCost;
}
```

### 2. Redundant Connection (LeetCode 684)
```php
<?php
function findRedundantConnection(array $edges): array
{
    $n = count($edges);
    $uf = new UnionFind(range(1, $n));

    foreach ($edges as $edge) {
        if (!$uf->union($edge[0], $edge[1])) {
            return $edge; // Bu edge cycle yaradir
        }
    }

    return [];
}
```

### 3. Number of Connected Components (LeetCode 323)
```php
<?php
function countComponents(int $n, array $edges): int
{
    $uf = new UnionFind(range(0, $n - 1));
    $components = $n;

    foreach ($edges as [$u, $v]) {
        if ($uf->union($u, $v)) {
            $components--;
        }
    }

    return $components;
}
```

### 4. Minimum Cost to Repair Edges
```php
<?php
function minCostRepair(int $n, array $connections, array $broken): int
{
    // Broken edge-leri cost ile, sag olanlar 0 cost ile
    $edges = [];
    $brokenSet = [];
    foreach ($broken as $b) {
        $brokenSet[$b[0] . '-' . $b[1]] = true;
        $brokenSet[$b[1] . '-' . $b[0]] = true;
    }

    foreach ($connections as $conn) {
        $key = $conn[0] . '-' . $conn[1];
        $cost = isset($brokenSet[$key]) ? $conn[2] : 0;
        $edges[] = ['from' => $conn[0], 'to' => $conn[1], 'weight' => $cost];
    }

    usort($edges, fn($a, $b) => $a['weight'] - $b['weight']);
    $uf = new UnionFind(range(1, $n));
    $totalCost = 0;

    foreach ($edges as $e) {
        if ($uf->union($e['from'], $e['to'])) {
            $totalCost += $e['weight'];
        }
    }

    return $totalCost;
}
```

## Interview Suallari

1. **Kruskal ve Prim arasinda ferq?**
   - Kruskal: Global minimum edge-den baslar, Union-Find ile cycle yoxlayir
   - Prim: Bir vertex-den baslar, greedy olaraq en yaxin vertex-i elave edir
   - Kruskal sparse, Prim dense graph ucun daha effektivdir

2. **Union-Find nece isleyir?**
   - Her element oz parent-ini gosterir, root ozune isare edir
   - Find: Root-a qeder gedir (path compression ile)
   - Union: Iki root-u birlesdirir (rank ile)
   - Amortized O(α(n)) ≈ O(1) her emeliyyat

3. **MST unique olurmu?**
   - Butun edge weight-leri ferqlidirese, beli, unique-dir
   - Eyni weight-li edge-ler varsa, birden cox MST ola biler

4. **MST real dunyada harada istifade olunur?**
   - Network design (en az kabel ile butun kompyuterleri birlesdir)
   - Cluster analysis, image segmentation
   - Approximation algorithms (TSP ucun)

## PHP/Laravel ile Elaqe

- **Network infrastructure**: Server-leri en az cost ile birlesdirmek
- **Clustering**: Oxsar data point-leri qruplasdirmaq (MST-based clustering)
- **Circuit design**: Minimum wiring problem
- **Laravel**: Distributed system-lerde node-lari birlesdirmek, cache invalidation network

---

## Praktik Tapşırıqlar

1. **LeetCode 1584** — Min Cost to Connect All Points (Prim vs Kruskal)
2. **LeetCode 1135** — Connecting Cities With Minimum Cost (Kruskal + DSU)
3. **LeetCode 1168** — Optimize Water Distribution in a Village (virtual node MST)
4. **LeetCode 1489** — Find Critical and Pseudo-Critical Edges in MST
5. **LeetCode 1579** — Remove Max Number of Edges to Keep Graph Fully Traversable

### Step-by-step: Kruskal's Algorithm

```
edges (sorted by weight): (1-2,1), (3-4,2), (1-3,3), (2-4,4), (2-3,5)
nodes: 1,2,3,4 | DSU: each node is own parent

process (1-2,w=1): find(1)≠find(2) → union → MST cost=1
process (3-4,w=2): find(3)≠find(4) → union → MST cost=3
process (1-3,w=3): find(1)≠find(3) → union → MST cost=6
process (2-4,w=4): find(2)==find(4) → SKIP (cycle yaradar)

MST edges: [(1-2),(3-4),(1-3)], total weight=6 ✓
n-1 = 3 edge → tamamlandı
```

---

## Əlaqəli Mövzular

- [25-graphs-basics.md](25-graphs-basics.md) — Graph reprezentasiyası, BFS/DFS
- [28-union-find.md](28-union-find.md) — Kruskal-da DSU istifadəsi
- [18-heaps.md](18-heaps.md) — Prim-da min-heap
- [26-graphs-advanced.md](26-graphs-advanced.md) — Dijkstra (shortest path, MST ilə fərqi)
- [45-network-flow.md](45-network-flow.md) — Minimum cut (MST ilə əlaqəsi)
