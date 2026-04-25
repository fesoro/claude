# Union-Find (Senior)

## Konsept (Concept)

Union-Find elementleri qruplara (set-lere) bolmek ve iki elementin eyni qrupda olub-olmadigini yoxlamaq ucun istifade olunan data structure-dur. Iki esas emeliyyat var: **Find** (root-u tap) ve **Union** (iki set-i birlesdir).

```
Baslangic: her element oz set-inde
{0} {1} {2} {3} {4} {5}

union(0, 1): {0,1} {2} {3} {4} {5}
union(2, 3): {0,1} {2,3} {4} {5}
union(0, 2): {0,1,2,3} {4} {5}
union(4, 5): {0,1,2,3} {4,5}

find(1) = find(3)? Yes (eyni set-de)
find(1) = find(4)? No  (ferqli set-de)

Tree temsili:
  union(0,1): 0->1 (1 root)
  union(2,3): 2->3 (3 root)
  union(1,3): 1->3
              3
             /|\
            0  1  2

Path compression sonra:
              3
            / | \ \
           0  1  2  (flat tree)
```

### Optimizasiyalar:
- **Path Compression**: Find zamani butun node-lari birbase root-a bagla
- **Union by Rank**: Kicik agaci boyuk agaca bagla (derinliyi azalt)
- Her ikisi ile: amortized O(alpha(n)) ≈ O(1)

## Nece Isleyir? (How does it work?)

### Path Compression:
```
Before find(0):       After find(0):
    4                     4
    |                   / | \
    3                  0  1  3
    |                        |
    1                        2
    |
    0

find(0): 0->1->3->4, sonra 0->4, 1->4 olur
```

### Union by Rank:
```
rank[A]=2, rank[B]=1:
     A           A
    / \    +   B    =   / | \
   x   y       |      x  y  B
                z             |
                              z
Boyuk rank-li root olur (A)
```

## Implementasiya (Implementation)

```php
<?php

class UnionFind
{
    private array $parent;
    private array $rank;
    private int $count; // component sayi

    public function __construct(int $n)
    {
        $this->parent = range(0, $n - 1);
        $this->rank = array_fill(0, $n, 0);
        $this->count = $n;
    }

    /**
     * Find with path compression
     * Amortized O(α(n)) ≈ O(1)
     */
    public function find(int $x): int
    {
        if ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->find($this->parent[$x]); // path compression
        }
        return $this->parent[$x];
    }

    /**
     * Union by rank
     * Amortized O(α(n)) ≈ O(1)
     */
    public function union(int $x, int $y): bool
    {
        $rootX = $this->find($x);
        $rootY = $this->find($y);

        if ($rootX === $rootY) return false; // artiq eyni set-de

        // Rank-a gore birlesdir
        if ($this->rank[$rootX] < $this->rank[$rootY]) {
            $this->parent[$rootX] = $rootY;
        } elseif ($this->rank[$rootX] > $this->rank[$rootY]) {
            $this->parent[$rootY] = $rootX;
        } else {
            $this->parent[$rootY] = $rootX;
            $this->rank[$rootX]++;
        }

        $this->count--;
        return true;
    }

    public function connected(int $x, int $y): bool
    {
        return $this->find($x) === $this->find($y);
    }

    public function getCount(): int
    {
        return $this->count;
    }
}

/**
 * Weighted Union-Find (with size tracking)
 */
class WeightedUnionFind
{
    private array $parent;
    private array $size;

    public function __construct(int $n)
    {
        $this->parent = range(0, $n - 1);
        $this->size = array_fill(0, $n, 1);
    }

    public function find(int $x): int
    {
        if ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->find($this->parent[$x]);
        }
        return $this->parent[$x];
    }

    public function union(int $x, int $y): bool
    {
        $rootX = $this->find($x);
        $rootY = $this->find($y);

        if ($rootX === $rootY) return false;

        // Kicik agaci boyuge bagla
        if ($this->size[$rootX] < $this->size[$rootY]) {
            $this->parent[$rootX] = $rootY;
            $this->size[$rootY] += $this->size[$rootX];
        } else {
            $this->parent[$rootY] = $rootX;
            $this->size[$rootX] += $this->size[$rootY];
        }

        return true;
    }

    public function getSize(int $x): int
    {
        return $this->size[$this->find($x)];
    }
}

/**
 * String-based Union-Find (graph vertex-leri string olanda)
 */
class StringUnionFind
{
    private array $parent = [];
    private array $rank = [];

    public function find(string $x): string
    {
        if (!isset($this->parent[$x])) {
            $this->parent[$x] = $x;
            $this->rank[$x] = 0;
        }
        if ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->find($this->parent[$x]);
        }
        return $this->parent[$x];
    }

    public function union(string $x, string $y): void
    {
        $rx = $this->find($x);
        $ry = $this->find($y);
        if ($rx === $ry) return;

        if ($this->rank[$rx] < $this->rank[$ry]) {
            $this->parent[$rx] = $ry;
        } elseif ($this->rank[$rx] > $this->rank[$ry]) {
            $this->parent[$ry] = $rx;
        } else {
            $this->parent[$ry] = $rx;
            $this->rank[$rx]++;
        }
    }
}

// --- Applications ---

/**
 * Number of Connected Components (LeetCode 323)
 * Time: O(n + e * α(n)), Space: O(n)
 */
function countComponents(int $n, array $edges): int
{
    $uf = new UnionFind($n);
    foreach ($edges as [$u, $v]) {
        $uf->union($u, $v);
    }
    return $uf->getCount();
}

/**
 * Redundant Connection (LeetCode 684)
 * Time: O(n * α(n)), Space: O(n)
 */
function findRedundantConnection(array $edges): array
{
    $n = count($edges);
    $uf = new UnionFind($n + 1);

    foreach ($edges as $edge) {
        if (!$uf->union($edge[0], $edge[1])) {
            return $edge; // Bu edge cycle yaradir
        }
    }

    return [];
}

/**
 * Number of Islands (Union-Find approach)
 * Time: O(m * n * α(mn)), Space: O(mn)
 */
function numIslands(array $grid): int
{
    $m = count($grid);
    $n = count($grid[0]);
    $uf = new UnionFind($m * $n);
    $water = 0;

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($grid[$i][$j] === '0') {
                $water++;
                continue;
            }
            // Sag ve asagi qonsularla birlesdir
            if ($j + 1 < $n && $grid[$i][$j + 1] === '1') {
                $uf->union($i * $n + $j, $i * $n + $j + 1);
            }
            if ($i + 1 < $m && $grid[$i + 1][$j] === '1') {
                $uf->union($i * $n + $j, ($i + 1) * $n + $j);
            }
        }
    }

    return $uf->getCount() - $water;
}

/**
 * Accounts Merge (LeetCode 721)
 * Time: O(n * k * α(nk)), Space: O(nk)
 */
function accountsMerge(array $accounts): array
{
    $uf = new StringUnionFind();
    $emailToName = [];

    foreach ($accounts as $account) {
        $name = $account[0];
        $firstEmail = $account[1];

        for ($i = 1; $i < count($account); $i++) {
            $emailToName[$account[$i]] = $name;
            $uf->union($firstEmail, $account[$i]);
        }
    }

    // Qrupla
    $groups = [];
    foreach (array_keys($emailToName) as $email) {
        $root = $uf->find($email);
        $groups[$root][] = $email;
    }

    $result = [];
    foreach ($groups as $emails) {
        sort($emails);
        $result[] = array_merge([$emailToName[$emails[0]]], $emails);
    }

    return $result;
}

/**
 * Earliest time when everyone becomes friends (LeetCode 1101)
 */
function earliestFriends(array $logs, int $n): int
{
    usort($logs, fn($a, $b) => $a[0] - $b[0]);
    $uf = new UnionFind($n);

    foreach ($logs as [$time, $a, $b]) {
        $uf->union($a, $b);
        if ($uf->getCount() === 1) {
            return $time;
        }
    }

    return -1;
}

// --- Test ---
$uf = new UnionFind(6);
$uf->union(0, 1);
$uf->union(2, 3);
$uf->union(0, 2);
$uf->union(4, 5);

echo "0 and 3 connected: " . ($uf->connected(0, 3) ? 'yes' : 'no') . "\n"; // yes
echo "0 and 4 connected: " . ($uf->connected(0, 4) ? 'yes' : 'no') . "\n"; // no
echo "Components: " . $uf->getCount() . "\n"; // 2

echo "Redundant: " . implode(',', findRedundantConnection([[1,2],[1,3],[2,3]])) . "\n"; // 2,3

echo "Components 5 nodes: " . countComponents(5, [[0,1],[1,2],[3,4]]) . "\n"; // 2
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Emeliyyat | Naive | Path Compression | Path + Rank |
|-----------|-------|-----------------|-------------|
| Find | O(n) | O(log n) amort | O(α(n)) ≈ O(1) |
| Union | O(n) | O(log n) amort | O(α(n)) ≈ O(1) |
| Space | O(n) | O(n) | O(n) |

α(n) = inverse Ackermann function, praktikada 5-den kicikdir

## Interview Suallari

1. **Union-Find ne vaxt istifade olunur?**
   - "Connected components" tapmaq
   - "Cycle detection" (undirected graph)
   - "Merge groups/accounts"
   - Kruskal MST
   - Dynamic connectivity problems

2. **Path compression nece isleyir?**
   - Find zamani gecdiyin butun node-lari birbase root-a bagla
   - Gelecek find-lar O(1)-e yaximlasir
   - Tree flat olur (derinlik azalir)

3. **Union by Rank vs Union by Size?**
   - Rank: agacin derinliyini minimize edir
   - Size: boyuk agaca kicigi bagla
   - Ikisi de eyni amortized complexity verir

4. **BFS/DFS vs Union-Find?**
   - Union-Find: dynamic (edge-ler tedricen elave olunur)
   - BFS/DFS: static graph, bir defe traverse
   - Union-Find online, BFS/DFS offline

## PHP/Laravel ile Elaqe

- **Social networks**: User group/community merge
- **Database clustering**: Connected record-lari qruplasdirmaq
- **Image segmentation**: Oxsar pixel-leri birlesdirmek
- **Network connectivity**: Server cluster sagligi
- **Equivalence classes**: Synonym grouping, duplicate detection
